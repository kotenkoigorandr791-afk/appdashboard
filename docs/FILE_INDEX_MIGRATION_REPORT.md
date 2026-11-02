# Отчёт: единый файловый индекс AppDashboard, удаление симлинков и E2E-проверка приложений

Дата: 2026-11-02 · Ветка: `develop`

---

## 1. Исходное задание

Задача ставилась в несколько этапов (сообщения пользователя по ходу работы):

1. **Единое хранилище метаданных файлов.** Все приложения должны работать с файлами через центральный индекс (PostgreSQL), а не через сканирование каталогов: листинги, поиск, размеры, перемещение, удаление и разрешение пути по стабильному id.
2. **Убрать симлинки.** В репозитории не должно оставаться симлинков на данные (`data`, `uploads`, каталоги данных подприложений): данные живут только на томе `APPDATA`.
3. **Полное удаление пользователя.** При удалении аккаунта должны исчезать файлы, индекс, outbox/задачи индексации, строки во всех таблицах с `user_email` / `email` / `user_id`, персональные таблицы и схемы, данные всех подприложений.
4. **Тесты.** Усовершенствовать тесты так, чтобы они проверяли корректность индекса **и каждого приложения**, включая **ИИ-ассистента**; вся коммуникация с файлами должна идти через индекс.
5. **Производительность проверок.** Долгие тесты (> 30 секунд) запускать в фоне и параллельно продолжать правки.

---

## 2. Что сделано

### 2.1 Ядро файлового индекса (`lib/Storage`)

| Файл | Назначение |
| --- | --- |
| `lib/Storage/FileIndexDb.php` | Схема и SQL-слой: `storage_files`, `storage_file_outbox` (transactional outbox), `storage_index_jobs`, `storage_index_queue`, `storage_index_meta`; индексы по `(user_id, app_key, parent_id)`, `(user_id, app_key, name)`, `(disk_id, relative_path)`, `(user_id, app_key, updated_at)`, частичный индекс активных родителей, GIN `pg_trgm` для поиска. |
| `lib/Storage/FileIndex.php` | Сервис: листинг каталога только из SQL, keyset-пагинация, поиск, стабильные id, перемещение поддерева, soft-delete/restore, `resolvePhysicalPath()` с проверкой владельца/app/диска/realpath, привязка к физическому диску (`diskIdFor`), `ensureIndexed()` (ленивая индексация одного уровня), `listRecursive()`, `exists()`. |
| `lib/Storage/FileIndexWorker.php` | Фоновая индексация пакетами через очередь каталогов, восстановление после рестарта, обработчик outbox, reconciliation, пометка `missing` без удаления записей, `indexDirectory()` — единственное место, где допускается чтение каталога по запросу пользователя. |
| `lib/Storage/AppFiles.php` | **Новый единый фасад для всех приложений**: `listByRoot/listUser/listFolder/treeUser/find/absolute/recordWrite/recordDelete/recordMove/recordDirectory/refresh/enqueueIndex`. Два режима владения: пользовательский (`cloud`, `steps`, `spends`, `smart_home`) и папочный (`films`, `recipes` — общие каталоги `uploads/`, `products/`). |

Ключевое свойство: HTTP-код приложений больше не вызывает `scandir/glob/DirectoryIterator` для листингов — каталог читается только внутри `FileIndexWorker::indexDirectory()` (один уровень, с пропуском симлинков), а сами листинги/поиск/разрешение пути идут SQL-запросами.

### 2.2 Cloud и ИИ-ассистент

- `apps/cloud/includes/cloud_file_index.php` — адаптер Cloud ↔ индекс (`list`, `search`, `usage`, элементы с id/размерами).
- `apps/cloud/api/actions/router.php`, `apps/cloud/api.php` — действия `list`, `search_tree`, `get_file_tree`, `patch_file_tree`, `get_folder_properties`, квоты и использование хранилища читают индекс; мутации (upload, mkdir, create_file, rename, move, copy, delete, trash, restore, save_file) обновляют индекс/outbox.
- `apps/cloud/webdav.php` — листинги и мутации WebDAV через индекс.
- `apps/cloud/api/actions/ai_agent.php` — **ИИ-ассистент**:
  - `list_directory` (плоский и рекурсивный) отвечает из индекса (`AppFiles`), без `scandir`/`RecursiveDirectoryIterator`;
  - добавлен `cloud_ai_index_sync()`: `write_file`, `make_directory`, `copy_file`, `rename_file`, `move_path`, `delete_file`, `delete_directory` синхронизируют индекс (id сохраняются при переименовании).
- `apps/cloud/includes/cloud_sync_engine.php` — манифест синхронизации строится по индексу (обход ФС только как аварийный fallback, если индекс недоступен); хеши считаются только в режиме `mtime_size_hash`.

### 2.3 Приложения

| Приложение | Что переведено на индекс |
| --- | --- |
| **cloud** | листинг, поиск, дерево, свойства папок, квоты/usage, WebDAV, все мутации |
| **films** | `apps/films/core/file_index.php` (адаптер), регистрация загруженных видео и `comments/*.json`, проверка существования и удаление через индекс |
| **recipes** | `apps/recipes/core/file_index.php`; `recipes_product_thumb_public_path()` проверяет наличие картинки через индекс (без `is_file` на каждый товар), регистрация `product.json`, превью и загруженных изображений |
| **spends** | `apps/spends/core/file_index.php`; список/количество/скачивание/восстановление/удаление CSV-бэкапов через индекс (`Handlers.php`, `views/body.php`) |
| **steps** | `apps/steps/core/file_index.php`; список/создание/восстановление/удаление бэкапов через индекс (`PostHandlers.php`), `init.php` и импортёры используют `STEPS_USERS_DIR` вместо удалённого симлинка |
| **smart_home** | каталог данных адресуется через том `APPDATA` (симлинк удалён), доступен через общий фасад индекса |

### 2.4 Удаление симлинков

- Удалены из git и с диска **25 символических ссылок**: `data`, `uploads`, `apps/cloud/{storage,temp,temp_convert,transcription_uploads,tts_output,upload_sessions}`, `apps/films/{comments,config.json,movies.json,storage,uploads}`, `apps/recipes/{products,recipes}`, `apps/smart_home/data`, `apps/spends/{backups,data,global_settings.json,upload_sessions,users,utils/ocr_jobs,utils/results}`, `apps/steps/{users,upload_sessions}`.
- `.gitignore`: пути данных больше не ре-инклудятся (нет `!/data`, `!/uploads`), добавлен `/apps/spends/data`.
- `docker-entrypoint.sh`: больше **не создаёт** ссылки `data` и `uploads`, а удаляет оставшиеся от старых образов.
- `deploy/scripts/ensure-apps-appdata.sh` и `ensure-spends-appdata.sh`: вместо `ln -sfn` выполняют `ensure_data` — переносят данные на том и отвязывают путь из кодового дерева.
- `nginx/appdashboard.conf.template`: добавлены `location ^~` с `alias` на `/opt/ApplicationData/...` для исторических URL (`/uploads/`, `/apps/films/uploads/`, `/apps/films/comments/`, `/apps/recipes/products/`, `/apps/recipes/recipes/`, `/apps/steps/users/`, `/apps/smart_home/data/`) с запретом отдачи PHP-файлов.
- Исправлены места, полагавшиеся на ссылки: `apps/steps/core/init.php`, `bootstrap.php`, `zeep_api.php`, `takeout_api.php`, `project/sharepoint/lib/files.php`, `project/sharepoint/handlers/StepRoutineShareHandler.php`.

### 2.5 Полное удаление пользователя

- `lib/UserDataPurger.php` — удаляет файлы (включая аватары), строки индекса, outbox, задачи и очередь индексации, записи во всех таблицах с `user_email`/`email`/`user_id`, персональные таблицы (`user_*`, `*_<hash>`) и схемы; интеграции cloud/recipes/films/spends/steps/smart_home/storage/backups.
- Встроен в оба обработчика удаления (`project/post_handlers.php`), CLI: `project/bin/purge_user.php`.
- Идемпотентен (повторный вызов не падает).

### 2.6 Фоновые процессы

- `project/storage/index_worker.php` и `project/storage/index_watcher.php` под supervisor; в `supervisord.conf` добавлены `file-index-worker` и `file-index-watcher`.
- В контейнере процессы активированы (`supervisorctl reread && update`), статус `RUNNING`; воркер уже обработал реальные задания (сотни файлов).
- `index_worker.php` при первичном обнаружении индексирует **все локальные приложения** (`cloud`, `films`, `recipes`, `spends`, `steps`, `smart_home`), а не только cloud. После запуска на реальных данных: 640 файлов / 239 каталогов / 6 приложений (`cloud` 781 объект, `steps` 48, `spends` 44, `smart_home` 2, `films` 2, `recipes` 2).
- Исправлено владение каталогом `$APPDATA_DATA_DIR/storage` (lock-файлы воркера): каталог создаётся в `docker-entrypoint.sh` до старта supervisor и чинится для `www-data`.
- Новая задача для несуществующего корня (удалённый пользователь, отключённый диск) больше не висит в `failed` — помечается `completed` с `last_error=root_not_available`.
- Наблюдатель использует inotify по физическим ветвям с fallback на reconciliation и обработкой overflow.
- Админ-панель Storage: вкладка File Index, API `index_start` / `index_reconcile`, статистика по приложениям/дискам.

---

## 3. Найденные и исправленные баги

1. **PostgreSQL: `multiple assignments to same column "updated_at"`** в `FileIndexDb::upsert()` — `updated_at` добавлялся в `SET` дважды. На SQLite тест проходил, на PostgreSQL любое обновление существующей строки падало, из-за чего проиндексированные файлы помечались `missing` (пустые листинги films/recipes/spends/steps в E2E). Исправлено.
2. **`Undefined array key "status"`** в `FileIndexDb::upsert()`: `?? 'active'` попадал в валидное значение и затем читался отсутствующий ключ. Исправлено явной переменной.
3. **`AppFiles::absolute()`/`find()`** не принимали путь относительно корня (`docs/x` при корне `alice`) — добавлен `AppFiles::fullPath()`.
4. **`AppFiles::treeByRoot()`** возвращал пути с префиксом корня — теперь пути относительны запрошенного каталога (как ожидает ИИ-ассистент).
5. **`steps`** после удаления симлинка писал в несуществующий `apps/steps/users` — переведено на `STEPS_USERS_DIR` (том `APPDATA/steps`, куда указывала старая ссылка).

---

## 4. Тесты

| Тест | Результат | Что проверяет |
| --- | --- | --- |
| `tests/storage/test_file_index.php` | **39/39 PASSED** (SQLite) | схема, листинг/поиск только из SQL, keyset-пагинация, изоляция пользователей/приложений, стабильные id при move, soft-delete/restore, разрешение пути и защита от traversal, outbox, обработка внешних изменений, `missing`, статистика, отсутствие сканирования в cloud `list`/`search_tree` |
| `tests/storage/test_apps_file_index.php` | **56/56 PASSED** (SQLite) | **каждое приложение**: cloud (main + documents), ИИ-ассистент (плоский/рекурсивный листинг, запись/переименование/удаление), films (uploads/comments), recipes (products, превью), spends (бэкапы), steps (бэкапы), smart_home (data), изоляция, отсутствие сканирования в коде листингов, отсутствие симлинков в дереве |
| `tests/storage/test_storage_system.php` | **92/92 PASSED** | регрессия Storage Manager: диски, миграции, MergerFS, бэкапы, снапшоты |
| `tests/storage/test_user_purge_e2e.php` | **44/44 PASSED** (PostgreSQL + реальные тома, в контейнере) | создание пользователя → данные во все приложения → индексация → проверка листингов/размера/разрешения пути по каждому приложению + ИИ-ассистент → полное удаление → отсутствие остатков в БД/ФС → идемпотентность |

Дополнительно проверено на реальном PostgreSQL в контейнере: схема, `pg_trgm`, вставка/листинг/поиск/resolve, фоновый job на реальной ФС.

Проверка конфигурации:

- `php -l` — все изменённые/новые PHP-файлы без синтаксических ошибок.
- `sh -n` — `docker-entrypoint.sh`, `deploy/scripts/ensure-apps-appdata.sh`, `deploy/scripts/ensure-spends-appdata.sh` — синтаксис OK.
- nginx: шаблон `nginx/appdashboard.conf.template` рендерится (`deploy/scripts/render-nginx-conf.sh`), 7 новых `location ^~` с `alias` на `/opt/ApplicationData/...`, скобки сбалансированы (163/163); текущий конфиг контейнера проходит `nginx -t`.

### 4.1 Найденные по ходу дела дефекты конфигурации

- Фоновые процессы `file-index-worker` / `file-index-watcher` описаны в `supervisord.conf`, но в уже запущенном контейнере не работают (0 процессов, нет лог-файлов): они добавлены в конфиг после старта контейнера. Требуется reload/пересоздание контейнера (см. раздел 5).

---

## 5. Что осталось / известные ограничения

1. **Физические операции** (копирование, распаковка архивов, миграционные импорты, очистка временных каталогов заданий, `rrmdir`) по-прежнему обходят файловую систему — это операции над содержимым, а не листинги; они обновляют индекс после завершения. Их перевод на «только индекс» невозможен без потери функциональности.
2. **Синхронизация манифеста Cloud** использует `mtime` с точностью до секунды (индекс хранит секунды); `mtime_ms` вычисляется как `mtime * 1000`.
3. **`public-api/routes/shell.php`** (`fm_list_dir`) — файловый менеджер по кодовому дереву (`APP_ROOT`), а не по данным приложений; индекс его не покрывает.
4. **`smart_home`** хранит состояние в БД; файловый каталог данных остаётся доступным через индекс, но приложение не имеет собственных файловых листингов.
5. **Легаси-фолбэки** (`scan_full_tree`, `scan_folder_usage_recursive`, обход ФС в sync) остаются на случай `FILE_INDEX_ENABLED=0`; основной путь — индекс.
6. **Проверки, не запускавшиеся в этом окружении:** линтеры/статический анализ проекта (в репозитории нет единой команды), а также UI-проверка в браузере (nginx-alias'ы добавлены, но живой прогон фронтенда не выполнялся).
7. **Обновление контейнера:** конфиг supervisor копируется в образ при сборке, но `docker-entrypoint.sh` перезаписывает его из репозитория при старте. На уже запущенном контейнере процессы включаются командой `supervisorctl -c /etc/supervisor/supervisord.conf reread && ... update` (выполнено).
8. **После рестарта контейнера** стоит убедиться, что `file-index-worker`/`file-index-watcher` снова `RUNNING` (они в `autostart=true`).

---

## 6. Команды для воспроизведения

```bash
# Юнит/интеграционные тесты (SQLite, ~2 c каждый)
php tests/storage/test_file_index.php
php tests/storage/test_apps_file_index.php
php tests/storage/test_storage_system.php

# E2E на реальном PostgreSQL и томах (в контейнере)
docker compose exec -T appdashboard php /var/www/AppDashboard/tests/storage/test_user_purge_e2e.php

# Ручное удаление пользователя
docker compose exec -T appdashboard php /var/www/AppDashboard/project/bin/purge_user.php <email>
```
