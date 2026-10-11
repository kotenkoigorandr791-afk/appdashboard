<?php
// docker_dashboard.php
require_once __DIR__ . '/lib/app_session.php';
app_session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/admin_security.php';
require_once __DIR__ . '/lib/embed_context.php';
require_once __DIR__ . '/lib/docker_client.php';
require_once __DIR__ . '/smart_log.php';
smart_log_bootstrap('dashboard');
adminPanelRequireGate();
session_write_close();
$dockerCfg = loadDockerConfig();
$supportedLangs = ['en','ru','uk','be','pl','cs'];
$lang = in_array($_GET['lang'] ?? '', $supportedLangs, true) ? $_GET['lang'] : 'en';
$theme = ($_GET['theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $_GET['color'] ?? '') ? $_GET['color'] : '#007bff';
$embed = app_is_embedded_request();
$configuredDomain = trim((string)($dockerCfg['domain'] ?? 'localhost'));
$displayDomain = trim((string)($_GET['docker_domain'] ?? $configuredDomain));
if ($displayDomain === '') {
    $displayDomain = 'localhost:9000';
}
$localIpAddr = $_SERVER['SERVER_ADDR'] ?? '';
if (!filter_var($localIpAddr, FILTER_VALIDATE_IP) || $localIpAddr === '::1' || $localIpAddr === '127.0.0.1' || strpos($localIpAddr, '100.') === 0) {
    $localIpAddr = gethostbyname(php_uname('n'));
    if ($localIpAddr === php_uname('n') || !filter_var($localIpAddr, FILTER_VALIDATE_IP) || strpos($localIpAddr, '100.') === 0) {
        $localIpAddr = 'localhost';
    }
}
$jwtToken = trim((string)($dockerCfg['token'] ?? ''));
$i18n = [
    'en' => ['title' => 'Docker Manager','containers' => 'Containers','networks' => 'Networks','volumes' => 'Volumes','images' => 'Images','system' => 'System','search' => 'Search by name or image...','connected' => 'Connected','api_error' => 'API error','total_containers' => 'Total containers','running' => 'Running','images_count' => 'Images','docker_version' => 'Docker version','containers_list' => 'Containers list','refresh' => 'Refresh','logs' => 'Logs','inspect' => 'Inspect JSON','resources' => 'Resources','start' => 'Start','stop' => 'Stop','restart' => 'Restart','pause' => 'Pause','resume' => 'Resume','pull' => 'Pull','update' => 'Update','recreate' => 'Recreate','remove' => 'Delete','name' => 'Name','image' => 'Image','status' => 'Status','ports' => 'Ports','created' => 'Created','repository' => 'Repository','tag' => 'Tag','size' => 'Size','driver' => 'Driver','scope' => 'Scope','mountpoint' => 'Mountpoint','system_info' => 'System information','docker' => 'Docker','docker_engine' => 'Docker Engine','disk_usage' => 'Disk usage','main_interface' => 'Open ','api_docs' => 'Docker Engine API Docs','swagger' => 'Docker Engine API','invalid_id' => 'Invalid container ID','back' => 'Back to list','container_logs' => 'Container logs','stats_snapshot' => 'Resources snapshot','memory' => 'Memory','network' => 'Network','raw_json' => 'Raw JSON','error' => 'Error','no_containers' => 'No containers','no_images' => 'No images','no_data' => 'No data','hide_panel' => 'Click the row again to hide the panel. Logs, inspect and resources open here.','api_missing_token' => 'Docker API token is not configured.','docker_version' => 'Docker version','instance_id' => 'Instance ID','docker_version_info' => 'Version (info)','docker_api' => 'Docker API','os_arch' => 'OS / arch','containers_total' => 'Total containers','running_total' => 'Running','stopped_total' => 'Stopped','images_total' => 'Images','volumes_total' => 'Volumes','cpu_cores' => 'CPU cores','ram_total' => 'Total RAM','layers_size' => 'Image layers','images_in_report' => 'Images in report','containers_in_report' => 'Containers in report','volumes_in_report' => 'Volumes in report','endpoint' => 'Endpoint','endpoint_name' => 'Name','endpoint_status' => 'Status','unavailable' => 'Unavailable'],
    'ru' => ['title' => 'Менеджер Docker','containers' => 'Контейнеры','networks' => 'Сети','volumes' => 'Тома','images' => 'Образы','system' => 'Система','search' => 'Поиск по имени или образу...','connected' => 'Подключено к ','api_error' => 'Ошибка API','total_containers' => 'Всего контейнеров','running' => 'Запущено','images_count' => 'Образов','docker_version' => 'Docker версия','containers_list' => 'Список контейнеров','refresh' => 'Обновить','logs' => 'Логи','inspect' => 'Inspect JSON','resources' => 'Ресурсы','start' => 'Запуск','stop' => 'Стоп','restart' => 'Рестарт','pause' => 'Пауза','resume' => 'Продолжить','pull' => 'Pull','update' => 'Обновить','recreate' => 'Пересоздать','remove' => 'Удалить','name' => 'Имя','image' => 'Образ','status' => 'Статус','ports' => 'Порты','created' => 'Создан','repository' => 'Репозиторий','tag' => 'Тег','size' => 'Размер','driver' => 'Драйвер','scope' => 'Область','mountpoint' => 'Точка монтирования','system_info' => 'Информация о системе','docker' => 'Docker','docker_engine' => 'Docker Engine','disk_usage' => 'Использование диска','main_interface' => 'Открыть ','api_docs' => 'Документация API Docker','swagger' => 'Docker Engine API','invalid_id' => 'Некорректный ID контейнера','back' => 'К списку','container_logs' => 'Логи контейнера','stats_snapshot' => 'Ресурсы','memory' => 'Память','network' => 'Сеть','raw_json' => 'Сырой JSON','error' => 'Ошибка','no_containers' => 'Нет контейнеров','no_images' => 'Нет образов','no_data' => 'Нет данных','hide_panel' => 'Нажмите на строку ещё раз, чтобы скрыть панель. Логи, inspect и ресурсы открываются здесь.','api_missing_token' => 'Токен Docker API не настроен.','docker_version' => 'Версия Docker','instance_id' => 'ID экземпляра','docker_version_info' => 'Версия (info)','docker_api' => 'API Docker','os_arch' => 'ОС / арх','containers_total' => 'Контейнеров всего','running_total' => 'Запущено','stopped_total' => 'Остановлено','images_total' => 'Образов','volumes_total' => 'Томов','cpu_cores' => 'CPU ядер','ram_total' => 'RAM всего','layers_size' => 'Слои образов','images_in_report' => 'Образов в отчёте','containers_in_report' => 'Контейнеров в отчёте','volumes_in_report' => 'Томов в отчёте','endpoint' => 'Эндпоинт','endpoint_name' => 'Имя','endpoint_status' => 'Статус','unavailable' => 'Недоступно'],
    'uk' => ['title' => 'Менеджер Docker','containers' => 'Контейнери','networks' => 'Мережі','volumes' => 'Томи','images' => 'Образи','system' => 'Система','search' => 'Пошук за назвою або образом...','connected' => 'Підключено до ','api_error' => 'Помилка API','total_containers' => 'Усього контейнерів','running' => 'Запущено','images_count' => 'Образів','docker_version' => 'Версія Docker','containers_list' => 'Список контейнерів','refresh' => 'Оновити','logs' => 'Логи','inspect' => 'Inspect JSON','resources' => 'Ресурси','start' => 'Запуск','stop' => 'Стоп','restart' => 'Рестарт','pause' => 'Пауза','resume' => 'Продовжити','pull' => 'Pull','update' => 'Оновити','recreate' => 'Пересоздати','remove' => 'Видалити','name' => "Ім'я",'image' => 'Образ','status' => 'Статус','ports' => 'Порти','created' => 'Створено','repository' => 'Репозиторій','tag' => 'Тег','size' => 'Розмір','driver' => 'Драйвер','scope' => 'Область','mountpoint' => 'Точка монтування','system_info' => 'Інформація про систему','docker' => 'Docker','docker_engine' => 'Docker Engine','disk_usage' => 'Використання диска','main_interface' => 'Відкрити ','api_docs' => 'Документація API Docker','swagger' => 'Docker Engine API','invalid_id' => 'Некоректний ID контейнера','back' => 'До списку','container_logs' => 'Логи контейнера','stats_snapshot' => 'Ресурси','memory' => "Пам'ять",'network' => 'Мережа','raw_json' => 'Сирий JSON','error' => 'Помилка','no_containers' => 'Немає контейнерів','no_images' => 'Немає образів','no_data' => 'Немає даних','hide_panel' => 'Натисніть рядок ще раз, щоб сховати панель. Логи, inspect і ресурси відкриваються тут.','api_missing_token' => 'Токен Docker API не налаштований.','docker_version' => 'Версія Docker','instance_id' => 'ID екземпляра','docker_version_info' => 'Версія (info)','docker_api' => 'API Docker','os_arch' => 'ОС / арх','containers_total' => 'Контейнерів усього','running_total' => 'Запущено','stopped_total' => 'Зупинено','images_total' => 'Образів','volumes_total' => 'Томів','cpu_cores' => 'Ядер CPU','ram_total' => 'RAM усього','layers_size' => 'Шари образів','images_in_report' => 'Образів у звіті','containers_in_report' => 'Контейнерів у звіті','volumes_in_report' => 'Томів у звіті','endpoint' => 'Ендпоінт','endpoint_name' => "Ім'я",'endpoint_status' => 'Статус','unavailable' => 'Недоступно'],
    'be' => ['title' => 'Менеджар Docker','containers' => 'Кантэйнеры','networks' => 'Сеткі','volumes' => 'Тамы','images' => 'Вобразы','system' => 'Сістэма','search' => 'Пошук па назве або вобразе...','connected' => 'Падключана да ','api_error' => 'Памылка API','total_containers' => 'Усяго кантэйнераў','running' => 'Запушчана','images_count' => 'Вобразаў','docker_version' => 'Версія Docker','containers_list' => 'Спіс кантэйнераў','refresh' => 'Абнавіць','logs' => 'Логі','inspect' => 'Inspect JSON','resources' => 'Рэсурсы','start' => 'Запуск','stop' => 'Стоп','restart' => 'Рэстарт','pause' => 'Паўза','resume' => 'Працягнуць','pull' => 'Pull','update' => 'Абнавіць','recreate' => 'Перастварыць','remove' => 'Выдаліць','name' => 'Імя','image' => 'Вобраз','status' => 'Статус','ports' => 'Парты','created' => 'Створана','repository' => 'Рэпазіторый','tag' => 'Тэг','size' => 'Памер','driver' => 'Драйвер','scope' => 'Вобласць','mountpoint' => 'Кропка мантавання','system_info' => 'Інфармацыя аб сістэме','docker' => 'Docker','docker_engine' => 'Docker Engine','disk_usage' => 'Выкарыстанне дыска','main_interface' => 'Адкрыць ','api_docs' => 'Дакументацыя API Docker','swagger' => 'Docker Engine API','invalid_id' => 'Некарэктны ID кантэйнера','back' => 'Да спісу','container_logs' => 'Логі кантэйнера','stats_snapshot' => 'Рэсурсы','memory' => "Памяць",'network' => 'Сетка','raw_json' => 'Сыры JSON','error' => 'Памылка','no_containers' => 'Няма кантэйнераў','no_images' => 'Няма вобразаў','no_data' => 'Няма даных','hide_panel' => 'Націсніце радок яшчэ раз, каб схаваць панэль. Логі, inspect і рэсурсы адкрываюцца тут.','api_missing_token' => 'Токен Docker API не наладжаны.','docker_version' => 'Версія Docker','instance_id' => 'ID экзэмпляра','docker_version_info' => 'Версія (info)','docker_api' => 'API Docker','os_arch' => 'АС / арх','containers_total' => 'Усяго кантэйнераў','running_total' => 'Запушчана','stopped_total' => 'Спынена','images_total' => 'Вобразаў','volumes_total' => 'Тамоў','cpu_cores' => 'Ядраў CPU','ram_total' => 'Усяго RAM','layers_size' => 'Слаі вобразаў','images_in_report' => 'Вобразаў у справаздачы','containers_in_report' => 'Кантэйнераў у справаздачы','volumes_in_report' => 'Тамоў у справаздачы','endpoint' => 'Эндпоінт','endpoint_name' => 'Імя','endpoint_status' => 'Статус','unavailable' => 'Недаступна'],
    'pl' => ['title' => 'Menedżer Docker','containers' => 'Kontenery','networks' => 'Sieci','volumes' => 'Woluminy','images' => 'Obrazy','system' => 'System','search' => 'Szukaj po nazwie lub obrazie...','connected' => 'Połączono','api_error' => 'Błąd API','total_containers' => 'Wszystkie kontenery','running' => 'Uruchomione','images_count' => 'Obrazy','docker_version' => 'Wersja Docker','containers_list' => 'Lista kontenerów','refresh' => 'Odśwież','logs' => 'Logi','inspect' => 'Inspect JSON','resources' => 'Zasoby','start' => 'Start','stop' => 'Stop','restart' => 'Restart','pause' => 'Pauza','resume' => 'Wznów','pull' => 'Pull','update' => 'Aktualizuj','recreate' => 'Odtwórz','remove' => 'Usuń','name' => 'Nazwa','image' => 'Obraz','status' => 'Status','ports' => 'Porty','created' => 'Utworzono','repository' => 'Repozytorium','tag' => 'Tag','size' => 'Rozmiar','driver' => 'Sterownik','scope' => 'Zakres','mountpoint' => 'Punkt montowania','system_info' => 'Informacje o systemie','docker' => 'Docker','docker_engine' => 'Docker Engine','disk_usage' => 'Użycie dysku','main_interface' => 'Otwórz ','api_docs' => 'Dokumentacja API Docker','swagger' => 'Docker Engine API','invalid_id' => 'Nieprawidłowe ID kontenera','back' => 'Powrót do listy','container_logs' => 'Logi kontenera','stats_snapshot' => 'Zasoby','memory' => 'Pamięć','network' => 'Sieć','raw_json' => 'Surowe JSON','error' => 'Błąd','no_containers' => 'Brak kontenerów','no_images' => 'Brak obrazów','no_data' => 'Brak danych','hide_panel' => 'Kliknij wiersz ponownie, aby ukryć panel. Logi, inspect i zasoby otwierają się tutaj.','api_missing_token' => 'Token Docker API nie jest skonfigurowany.','docker_version' => 'Wersja Docker','instance_id' => 'ID instancji','docker_version_info' => 'Wersja (info)','docker_api' => 'API Docker','os_arch' => 'System / arch','containers_total' => 'Wszystkie kontenery','running_total' => 'Uruchomione','stopped_total' => 'Zatrzymane','images_total' => 'Obrazy','volumes_total' => 'Woluminy','cpu_cores' => 'Rdzenie CPU','ram_total' => 'RAM ogółem','layers_size' => 'Warstwy obrazów','images_in_report' => 'Obrazów w raporcie','containers_in_report' => 'Kontenerów w raporcie','volumes_in_report' => 'Woluminów w raporcie','endpoint' => 'Endpoint','endpoint_name' => 'Nazwa','endpoint_status' => 'Status','unavailable' => 'Niedostępne'],
    'cs' => ['title' => 'Manažer Docker','containers' => 'Kontejnery','networks' => 'Sítě','volumes' => 'Svazky','images' => 'Obrazy','system' => 'Systém','search' => 'Hledat podle názvu nebo obrazu...','connected' => 'Připojeno','api_error' => 'Chyba API','total_containers' => 'Celkem kontejnerů','running' => 'Spuštěno','images_count' => 'Obrazy','docker_version' => 'Verze Docker','containers_list' => 'Seznam kontejnerů','refresh' => 'Obnovit','logs' => 'Logy','inspect' => 'Inspect JSON','resources' => 'Zdroje','start' => 'Start','stop' => 'Stop','restart' => 'Restart','pause' => 'Pauza','resume' => 'Pokračovat','pull' => 'Pull','update' => 'Aktualizovat','recreate' => 'Přetvořit','remove' => 'Smazat','name' => 'Název','image' => 'Obraz','status' => 'Stav','ports' => 'Porty','created' => 'Vytvořeno','repository' => 'Repozitář','tag' => 'Značka','size' => 'Velikost','driver' => 'Ovladač','scope' => 'Oblast','mountpoint' => 'Přípojný bod','system_info' => 'Informace o systému','docker' => 'Docker','docker_engine' => 'Docker Engine','disk_usage' => 'Využití disku','main_interface' => 'Otevřít ','api_docs' => 'Dokumentace API Docker','swagger' => 'Docker Engine API','invalid_id' => 'Neplatné ID kontejneru','back' => 'Zpět na seznam','container_logs' => 'Logy kontejneru','stats_snapshot' => 'Zdroje','memory' => 'Paměť','network' => 'Síť','raw_json' => 'Surový JSON','error' => 'Chyba','no_containers' => 'Žádné kontejnery','no_images' => 'Žádné obrazy','no_data' => 'Žádná data','hide_panel' => 'Klikněte na řádek znovu pro skrytí panelu. Logy, inspect a zdroje se otevírají zde.','api_missing_token' => 'Token Docker API není nakonfigurován.','docker_version' => 'Verze Docker','instance_id' => 'ID instance','docker_version_info' => 'Verze (info)','docker_api' => 'API Docker','os_arch' => 'OS / architektura','containers_total' => 'Celkem kontejnerů','running_total' => 'Spuštěno','stopped_total' => 'Zastaveno','images_total' => 'Obrazy','volumes_total' => 'Svazky','cpu_cores' => 'Jádra CPU','ram_total' => 'Celkem RAM','layers_size' => 'Vrstvy obrazů','images_in_report' => 'Obrazů ve zprávě','containers_in_report' => 'Kontejnerů ve zprávě','volumes_in_report' => 'Svazků ve zprávě','endpoint' => 'Endpoint','endpoint_name' => 'Název','endpoint_status' => 'Stav','unavailable' => 'Nedostupné']
];
foreach (array_unique(['en', $lang]) as $translationLang) {
    $translationPath = __DIR__ . '/i18n/' . preg_replace('/[^a-z0-9_-]/i', '', $translationLang) . '.json';
    if (is_readable($translationPath)) {
        $fileTranslations = json_decode(file_get_contents($translationPath), true);
        if (is_array($fileTranslations)) {
            $i18n[$translationLang] = array_merge($i18n[$translationLang] ?? [], $fileTranslations);
        }
    }
}
$dockerExtraTranslations = [
    'en' => ['docker_stack_editor' => 'Web editor', 'docker_update_stack' => 'Update the stack', 'docker_stack_updated' => 'Stack updated', 'docker_invalid_stack' => 'Invalid stack ID', 'docker_stack_not_found' => 'Stack not found', 'docker_stack_file' => 'Compose file', 'docker_edit_stack' => 'Edit stack', 'docker_compose_detect' => 'Compose file', 'docker_compose_detect_btn' => 'Auto-detect', 'docker_compose_no_access' => 'Docker socket not accessible', 'docker_compose_not_found' => 'Compose file not detected', 'docker_compose_readonly' => 'Compose file auto-detected via Docker Compose CLI. Read-only view.', 'docker_compose_create_hint' => 'To manage through Docker, create a stack manually in Docker.'],
    'ru' => ['docker_stack_editor' => 'Web editor', 'docker_update_stack' => 'Обновить stack', 'docker_stack_updated' => 'Stack обновлён', 'docker_invalid_stack' => 'Некорректный ID stack', 'docker_stack_not_found' => 'Stack не найден', 'docker_stack_file' => 'Compose файл', 'docker_edit_stack' => 'Редактировать stack', 'docker_compose_detect' => 'Compose файл', 'docker_compose_detect_btn' => 'Определить', 'docker_compose_no_access' => 'Docker сокет недоступен', 'docker_compose_not_found' => 'Файл compose не обнаружен', 'docker_compose_readonly' => 'Compose файл автоопределён через Docker Compose CLI. Только чтение.', 'docker_compose_create_hint' => 'Чтобы управлять через Docker, создайте стек вручную в Docker.'],
    'uk' => ['docker_stack_editor' => 'Web editor', 'docker_update_stack' => 'Оновити stack', 'docker_stack_updated' => 'Stack оновлено', 'docker_invalid_stack' => 'Некоректний ID stack', 'docker_stack_not_found' => 'Stack не знайдено', 'docker_stack_file' => 'Compose файл', 'docker_edit_stack' => 'Редагувати stack', 'docker_compose_detect' => 'Compose файл', 'docker_compose_detect_btn' => 'Визначити', 'docker_compose_no_access' => 'Docker сокет недоступний', 'docker_compose_not_found' => 'Файл compose не виявлено', 'docker_compose_readonly' => 'Compose файл автовизначено через Docker Compose CLI. Тільки читання.', 'docker_compose_create_hint' => 'Щоб керувати через Docker, створіть стек вручну в Docker.'],
    'be' => ['docker_stack_editor' => 'Web editor', 'docker_update_stack' => 'Абнавіць stack', 'docker_stack_updated' => 'Stack абноўлены', 'docker_invalid_stack' => 'Некарэктны ID stack', 'docker_stack_not_found' => 'Stack не знойдзены', 'docker_stack_file' => 'Compose файл', 'docker_edit_stack' => 'Рэдагаваць stack', 'docker_compose_detect' => 'Compose файл', 'docker_compose_detect_btn' => 'Вызначыць', 'docker_compose_no_access' => 'Docker сокет недаступны', 'docker_compose_not_found' => 'Файл compose не выяўлены', 'docker_compose_readonly' => 'Compose файл аўтавызначаны праз Docker Compose CLI. Толькі чытанне.', 'docker_compose_create_hint' => 'Каб кіраваць праз Docker, стварыце стэк уручную ў Docker.'],
    'pl' => ['docker_stack_editor' => 'Web editor', 'docker_update_stack' => 'Aktualizuj stack', 'docker_stack_updated' => 'Stack zaktualizowany', 'docker_invalid_stack' => 'Nieprawidłowe ID stack', 'docker_stack_not_found' => 'Stack nie znaleziony', 'docker_stack_file' => 'Plik compose', 'docker_edit_stack' => 'Edytuj stack', 'docker_compose_detect' => 'Plik compose', 'docker_compose_detect_btn' => 'Wykryj', 'docker_compose_no_access' => 'Gniazdo Docker niedostępne', 'docker_compose_not_found' => 'Plik compose nie wykryty', 'docker_compose_readonly' => 'Plik compose wykryty automatycznie przez Docker Compose CLI. Widok tylko do odczytu.', 'docker_compose_create_hint' => 'Aby zarządzać przez Docker, utwórz stack ręcznie w Docker.'],
    'cs' => ['docker_stack_editor' => 'Web editor', 'docker_update_stack' => 'Aktualizovat stack', 'docker_stack_updated' => 'Stack aktualizován', 'docker_invalid_stack' => 'Neplatné ID stack', 'docker_stack_not_found' => 'Stack nenalezen', 'docker_stack_file' => 'Soubor compose', 'docker_edit_stack' => 'Upravit stack', 'docker_compose_detect' => 'Soubor compose', 'docker_compose_detect_btn' => 'Zjistit', 'docker_compose_no_access' => 'Socket Docker není dostupný', 'docker_compose_not_found' => 'Soubor compose nezjištěn', 'docker_compose_readonly' => 'Soubor compose automaticky zjištěn přes Docker Compose CLI. Zobrazení pouze pro čtení.', 'docker_compose_create_hint' => 'Pro správu přes Docker vytvořte stack ručně v Docker.']
];
foreach ($dockerExtraTranslations as $translationLang => $translations) {
    $i18n[$translationLang] = array_merge($translations, $i18n[$translationLang] ?? []);
}
function t($key) {
    global $i18n, $lang;
    return $i18n[$lang][$key] ?? $i18n['en'][$key] ?? $key;
}
function tf($key, array $vars = []) {
    $text = t($key);
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}
function dockerUrl(array $params = []) {
    $query = $_GET;
    unset($query['message']);
    foreach ($params as $k => $v) {
        if ($v === null) unset($query[$k]); else $query[$k] = $v;
    }
    return '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function callDockerAPI($endpoint, $method = 'GET', $data = null, $timeout = 120, $retryAuth = true) {
    return docker_call((string)$endpoint, (string)$method, $data, (int)$timeout);
}
function splitDockerImageRef($image) {
    $image = preg_replace('/@sha256:[a-f0-9]+$/i', '', (string)$image);
    $p = strrpos($image, ':');
    if ($p === false) {
        return [$image, 'latest'];
    }
    $suffix = substr($image, $p + 1);
    if (strpos($suffix, '/') !== false) {
        return [$image, 'latest'];
    }
    return [substr($image, 0, $p), $suffix];
}
function dockerErrorFromResult(array $result) {
    if (is_array($result['data'] ?? null) && isset($result['data']['message'])) {
        return (string)$result['data']['message'];
    }
    if (is_string($result['data'] ?? null) && $result['data'] !== '') {
        return (string)$result['data'];
    }
    return 'HTTP ' . (int)($result['code'] ?? 0);
}
function dockerActionMessage(array $result, array $okCodes, $successKey, array $vars = []) {
    if (in_array((int)($result['code'] ?? 0), $okCodes, true)) {
        return tf($successKey, $vars);
    }
    return tf('docker_action_error', ['error' => dockerErrorFromResult($result)]);
}
function validStackIdParam($id) {
    return is_string($id) && preg_match('/^\d+$/', $id);
}
function dockerStackById($id) {
    return null;
}
function dockerStackFileContent($id) {
    return [null, t('docker_stack_not_found')];
}
function dockerUpdateStackFile(array $stack, $content, $prune = false, $pullImage = false) {
    return ['code' => 400, 'data' => ['message' => t('docker_stack_not_found')]];
}
function dockerRedirect($message) {
    header('Location: ' . dockerUrl(['message' => $message, 'action' => null, 'id' => null, 'image' => null, 'view' => null]));
    exit;
}
function dockerImageRefFromImage(array $image) {
    if (!empty($image['RepoTags']) && is_array($image['RepoTags'])) {
        foreach ($image['RepoTags'] as $tag) {
            if (is_string($tag) && $tag !== '' && $tag !== '<none>:<none>') {
                return $tag;
            }
        }
    }
    return (string)($image['Id'] ?? '');
}
function stripNulls($v) {
    if (!is_array($v)) {
        return $v;
    }
    foreach ($v as $k => $x) {
        if ($x === null) {
            unset($v[$k]);
        } elseif (is_array($x)) {
            $v[$k] = stripNulls($x);
        }
    }
    return $v;
}
function buildNetworkingConfigFromInspect(array $inspect) {
    $networks = $inspect['NetworkSettings']['Networks'] ?? [];
    if ($networks === []) {
        return [];
    }
    $endpoints = [];
    foreach ($networks as $name => $conf) {
        if (!is_array($conf)) {
            continue;
        }
        $ep = [];
        if (!empty($conf['Aliases'])) {
            $ep['Aliases'] = $conf['Aliases'];
        }
        if (!empty($conf['IPAMConfig'])) {
            $ep['IPAMConfig'] = $conf['IPAMConfig'];
        }
        if (!empty($conf['Links'])) {
            $ep['Links'] = $conf['Links'];
        }
        if (!empty($conf['DriverOpts'])) {
            $ep['DriverOpts'] = $conf['DriverOpts'];
        }
        $endpoints[$name] = $ep;
    }
    return ['EndpointsConfig' => $endpoints];
}
function buildContainerCreateBody(array $inspect) {
    $cfg = $inspect['Config'] ?? [];
    $hc = $inspect['HostConfig'] ?? [];
    $body = [
        'Hostname' => $cfg['Hostname'] ?? '',
        'Domainname' => $cfg['Domainname'] ?? '',
        'User' => $cfg['User'] ?? '',
        'AttachStdin' => false,
        'AttachStdout' => true,
        'AttachStderr' => true,
        'Tty' => $cfg['Tty'] ?? false,
        'OpenStdin' => $cfg['OpenStdin'] ?? false,
        'StdinOnce' => false,
        'Env' => $cfg['Env'] ?? [],
        'Cmd' => $cfg['Cmd'] ?? null,
        'Image' => $cfg['Image'] ?? '',
        'Volumes' => $cfg['Volumes'] ?? null,
        'WorkingDir' => $cfg['WorkingDir'] ?? '',
        'Entrypoint' => $cfg['Entrypoint'] ?? null,
        'Labels' => $cfg['Labels'] ?? [],
        'StopSignal' => $cfg['StopSignal'] ?? null,
        'StopTimeout' => isset($cfg['StopTimeout']) ? $cfg['StopTimeout'] : null,
        'Shell' => $cfg['Shell'] ?? null,
        'Healthcheck' => $cfg['Healthcheck'] ?? null,
        'HostConfig' => $hc,
    ];
    $nw = buildNetworkingConfigFromInspect($inspect);
    if ($nw !== []) {
        $body['NetworkingConfig'] = $nw;
    }
    return stripNulls($body);
}
function dockerContainerInspect($id) {
    return callDockerAPI(dockerApi('containers/' . rawurlencode($id) . '/json'));
}
function dockerPullImage($imageRef) {
    list($from, $tag) = splitDockerImageRef($imageRef);
    $q = http_build_query(['fromImage' => $from, 'tag' => $tag], '', '&', PHP_QUERY_RFC3986);
    return callDockerAPI(dockerApi('images/create?' . $q), 'POST', null, 600);
}
function dockerFindHostContainer() {
    global $containers;
    $list = is_array($containers['data'] ?? null) ? $containers['data'] : [];
    foreach ($list as $c) {
        $labels = $c['Labels'] ?? [];
        $name = str_replace('/', '', $c['Names'][0] ?? '');
        if (!empty($labels['io.docker.server'])) return $c['Id'];
        if (stripos($name, 'docker') !== false) return $c['Id'];
    }
    foreach ($list as $c) {
        if (($c['State'] ?? '') === 'running') return $c['Id'];
    }
    return null;
}

function decodeDockerStream($raw) {
    $output = '';
    $offset = 0;
    $len = strlen($raw);
    while ($offset + 8 <= $len) {
        $hdr = substr($raw, $offset, 8);
        $frameLen = unpack('N', substr($hdr, 4, 4))[1];
        $offset += 8;
        $frameLen = min($frameLen, $len - $offset);
        if ($frameLen > 0) $output .= substr($raw, $offset, $frameLen);
        $offset += $frameLen;
    }
    return trim($output);
}

function dockerExecReadOutput($containerId, $command, $timeout = 30) {
    $createResult = callDockerAPI(
        dockerApi('containers/' . rawurlencode($containerId) . '/exec'),
        'POST',
        ['Cmd' => ['sh', '-c', $command], 'AttachStdout' => true, 'AttachStderr' => true, 'Tty' => false],
        $timeout
    );
    if ($createResult['code'] !== 201 || !is_array($createResult['data']) || empty($createResult['data']['Id'])) return null;
    $execId = $createResult['data']['Id'];
    $startResult = callDockerAPI(dockerApi('exec/' . rawurlencode($execId) . '/start'), 'POST', ['Detach' => false, 'Tty' => false], $timeout);
    $raw = is_string($startResult['raw'] ?? null) ? $startResult['raw'] : '';
    if (($startResult['code'] ?? 0) !== 200 || $raw === '') return null;
    return decodeDockerStream($raw);
}

function detectComposeFile($projectName) {
    $hostContainerId = dockerFindHostContainer();
    if ($hostContainerId === null) return [null, t('docker_compose_no_access')];
    $escaped = escapeshellarg($projectName);
    $cmd = 'docker compose -p ' . $escaped . ' config 2>&1; docker-compose -p ' . $escaped . ' config 2>&1';
    $output = dockerExecReadOutput($hostContainerId, $cmd);
    if ($output === null || trim($output) === '') return [null, t('docker_compose_not_found')];
    if (preg_match('/command not found|not a docker command|no such command|is not a docker command/i', $output)) return [null, t('docker_compose_not_found') . ' (CLI)'];
    return [$output, null];
}

function dockerRecreateContainer($id, $imageOverride = null) {
    $insp = dockerContainerInspect($id);
    if ($insp['code'] !== 200 || !is_array($insp['data'])) {
        return 'Error: inspect failed: ' . (is_array($insp['data']) && isset($insp['data']['message']) ? $insp['data']['message'] : 'HTTP ' . $insp['code']);
    }
    $inspect = $insp['data'];
    $name = ltrim($inspect['Name'] ?? '', '/');
    $body = buildContainerCreateBody($inspect);
    if ($imageOverride !== null && $imageOverride !== '') {
        $body['Image'] = $imageOverride;
    }
    $state = $inspect['State']['Status'] ?? '';
    $shouldStart = in_array($state, ['running', 'restarting', 'paused'], true);
    if ($state === 'running' || $state === 'paused' || $state === 'restarting') {
        $st = callDockerAPI(dockerApi('containers/' . rawurlencode($id) . '/stop'), 'POST', null, 120);
        if ($st['code'] !== 204 && $st['code'] !== 304) {
            return 'Error: stop failed: ' . (is_array($st['data']) && isset($st['data']['message']) ? $st['data']['message'] : 'HTTP ' . $st['code']);
        }
    }
    $rm = callDockerAPI(dockerApi('containers/' . rawurlencode($id) . '?force=true'), 'DELETE', null, 120);
    if ($rm['code'] !== 204) {
        return 'Error: remove failed: ' . (is_array($rm['data']) && isset($rm['data']['message']) ? $rm['data']['message'] : 'HTTP ' . $rm['code']);
    }
    $cr = callDockerAPI(dockerApi('containers/create?name=' . rawurlencode($name)), 'POST', $body, 120);
    if ($cr['code'] !== 201 || !is_array($cr['data']) || empty($cr['data']['Id'])) {
        return 'Error: create failed: ' . (is_array($cr['data']) && isset($cr['data']['message']) ? $cr['data']['message'] : 'HTTP ' . $cr['code']);
    }
    $newId = $cr['data']['Id'];
    if ($shouldStart) {
        $st2 = callDockerAPI(dockerApi('containers/' . rawurlencode($newId) . '/start'), 'POST', null, 120);
        if ($st2['code'] !== 204 && $st2['code'] !== 304) {
            return 'Error: start after recreate failed: ' . (is_array($st2['data']) && isset($st2['data']['message']) ? $st2['data']['message'] : 'HTTP ' . $st2['code']);
        }
        return 'Container recreated and started';
    }
    return 'Container recreated (was stopped)';
}
function getDockerEndpointId() {
    return 1;
}
function dockerApi($suffix) {
    return ltrim($suffix, '/');
}
function mainSiteUrl() {
    global $displayDomain;
    return 'https://' . ltrim($displayDomain, '/');
}
function dockerDecodeLogStream($raw) {
    if (!is_string($raw) || $raw === '') {
        return '';
    }
    $out = '';
    $n = strlen($raw);
    $i = 0;
    while ($i + 8 <= $n) {
        $size = (ord($raw[$i + 4]) << 24) | (ord($raw[$i + 5]) << 16) | (ord($raw[$i + 6]) << 8) | ord($raw[$i + 7]);
        if ($size < 0 || $i + 8 + $size > $n) {
            break;
        }
        $out .= substr($raw, $i + 8, $size);
        $i += 8 + $size;
    }
    if ($out === '' && $raw !== '') {
        return $raw;
    }
    return $out;
}
function renderHighlightedLogs($text) {
    $lines = preg_split('/\R/u', (string)$text);
    if ($lines === false) {
        $lines = [(string)$text];
    }
    $html = '<div class="log-code">';
    foreach ($lines as $line) {
        $raw = (string)$line;
        $cls = 'log-line';
        if (preg_match('/\b(error|fatal|panic|critical|exception|failed|failure)\b/i', $raw)) {
            $cls .= ' log-error';
        } elseif (preg_match('/\b(warn|warning|deprecated)\b/i', $raw)) {
            $cls .= ' log-warn';
        } elseif (preg_match('/\b(debug|trace|verbose)\b/i', $raw)) {
            $cls .= ' log-debug';
        } elseif (preg_match('/\b(info|notice|success|started|listening|ready)\b/i', $raw)) {
            $cls .= ' log-info';
        }
        if (preg_match('/^\s*[\{\[]/', $raw)) {
            $cls .= ' log-json';
        }
        $escaped = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
        $escaped = preg_replace('/^(\s*(?:\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:[.,]\d+)?Z?|\[[^\]]+\]))/u', '<span class="log-time">$1</span>', $escaped);
        $escaped = preg_replace('/\b(ERROR|ERR|FATAL|PANIC|CRITICAL|EXCEPTION|FAILED|FAILURE)\b/i', '<span class="log-level log-level-error">$1</span>', $escaped);
        $escaped = preg_replace('/\b(WARN|WARNING|DEPRECATED)\b/i', '<span class="log-level log-level-warn">$1</span>', $escaped);
        $escaped = preg_replace('/\b(INFO|NOTICE|SUCCESS|STARTED|LISTENING|READY)\b/i', '<span class="log-level log-level-info">$1</span>', $escaped);
        $escaped = preg_replace('/\b(DEBUG|TRACE|VERBOSE)\b/i', '<span class="log-level log-level-debug">$1</span>', $escaped);
        $escaped = preg_replace('/(&quot;[^&]*?&quot;)(\s*:)/u', '<span class="log-json-key">$1</span>$2', $escaped);
        $html .= '<div class="' . $cls . '">' . ($escaped === '' ? '&nbsp;' : $escaped) . '</div>';
    }
    return $html . '</div>';
}
function formatBytesValue($bytes) {
    $bytes = (float)$bytes;
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return rtrim(rtrim(number_format($bytes, $i === 0 ? 0 : 2, '.', ''), '0'), '.') . ' ' . $units[$i];
}
function clampPercent($value) {
    $value = is_numeric($value) ? (float)$value : 0.0;
    return max(0, min(100, $value));
}
function statBarHtml($label, $valueText, $percent, $class = '') {
    $p = clampPercent($percent);
    return '<div class="metric ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"><div class="metric-head"><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><strong>' . htmlspecialchars($valueText, ENT_QUOTES, 'UTF-8') . '</strong></div><div class="metric-bar"><span style="width:' . htmlspecialchars((string)$p, ENT_QUOTES, 'UTF-8') . '%"></span></div></div>';
}
function viewPageShell($title, $innerHtml) {
    header('Content-Type: text/html; charset=UTF-8');
    global $theme, $accent, $embed;
    $bg = $theme === 'light' ? '#f3f6fb' : '#0f1419';
    $text = $theme === 'light' ? '#0f172a' : '#f4f7fb';
    $surface = $theme === 'light' ? '#ffffff' : 'rgba(18,24,32,0.8)';
    $surfaceHover = $theme === 'light' ? '#eef3fb' : 'rgba(255,255,255,0.10)';
    $border = $theme === 'light' ? '#d7deeb' : 'rgba(255,255,255,0.14)';
    $muted = $theme === 'light' ? '#64748b' : 'rgba(255,255,255,0.42)';
    $scrollTrack = $theme === 'light' ? '#e6ebf3' : 'rgba(255,255,255,0.04)';
    $scrollThumb = $theme === 'light' ? '#aeb9c9' : 'rgba(255,255,255,0.22)';
    $scrollThumbHover = $theme === 'light' ? '#8794a8' : 'rgba(255,255,255,0.35)';
    $accentEsc = htmlspecialchars($accent, ENT_QUOTES, 'UTF-8');
    $bar = $embed ? '' : '<div class="bar"><a href="' . htmlspecialchars(dockerUrl(['view' => null, 'id' => null]), ENT_QUOTES, 'UTF-8') . '">&larr; ' . htmlspecialchars(t('back'), ENT_QUOTES, 'UTF-8') . '</a></div>';
    echo '<!DOCTYPE html><html lang="' . htmlspecialchars($GLOBALS['lang'], ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><style>:root{--bg:' . $bg . ';--surface:' . $surface . ';--surface-hover:' . $surfaceHover . ';--border:' . $border . ';--text:' . $text . ';--muted:' . $muted . ';--accent:' . $accentEsc . ';--danger:#f87171;--ok:#4ade80;--warn:#fbbf24;--scroll-track:' . $scrollTrack . ';--scroll-thumb:' . $scrollThumb . ';--scroll-thumb-hover:' . $scrollThumbHover . '}*{box-sizing:border-box;scrollbar-width:thin;scrollbar-color:var(--scroll-thumb) var(--scroll-track)}::-webkit-scrollbar{width:9px;height:9px}::-webkit-scrollbar-track{background:var(--scroll-track);border-radius:8px}::-webkit-scrollbar-thumb{background:var(--scroll-thumb);border:2px solid var(--scroll-track);border-radius:8px}::-webkit-scrollbar-thumb:hover{background:var(--scroll-thumb-hover)}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:var(--bg);color:var(--text);margin:0;padding:' . ($embed ? '16px' : '20px') . ';line-height:1.5}a{color:var(--accent)}h1{font-size:22px;margin:0 0 18px}.bar{margin-bottom:16px}.view-panel{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px}
.log-toolbar{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px}
.log-toolbar h1{margin:0;font-size:18px}
.log-copy-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;border:1px solid var(--border);background:var(--surface-hover);color:var(--text);cursor:pointer;font-size:13px;font-weight:500;transition:all .2s ease;flex-shrink:0;margin-left:auto}
.log-copy-btn:hover{background:var(--accent);color:#fff;border-color:var(--accent);transform:scale(1.04)}.log-copy-btn:active{transform:scale(0.96)}
.log-copy-btn .ico{transition:transform .25s ease}.log-copy-btn.copied{background:var(--ok);color:#000;border-color:var(--ok)}.log-copy-btn.copied .ico{transform:rotateY(360deg)}
.log-code,.json-pre{white-space:pre-wrap;word-break:break-word;background:rgba(0,0,0,.16);border:1px solid var(--border);padding:12px;border-radius:8px;font:13px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:var(--text);max-height:70vh;overflow:auto}.log-line{padding:1px 6px;border-left:3px solid transparent}.log-error{background:rgba(248,113,113,.10);border-left-color:var(--danger)}.log-warn{background:rgba(251,191,36,.10);border-left-color:var(--warn)}.log-info{border-left-color:var(--accent)}.log-debug{color:var(--muted)}.log-json{color:#c4b5fd}.log-time{color:#7dd3fc}.log-level{font-weight:700}.log-level-error{color:var(--danger)}.log-level-warn{color:var(--warn)}.log-level-info{color:var(--ok)}.log-level-debug{color:#93c5fd}.log-json-key{color:#f0abfc}.stats-grid-view{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}.metric{background:var(--surface-hover);border:1px solid var(--border);border-radius:8px;padding:14px}.metric-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:10px;font-size:13px;color:var(--muted)}.metric-head strong{color:var(--text);font-size:13px;text-align:right}.metric-bar{height:10px;border-radius:999px;background:rgba(0,0,0,.22);overflow:hidden;border:1px solid var(--border)}.metric-bar span{display:block;height:100%;background:var(--accent);border-radius:999px}.metric.warn .metric-bar span{background:var(--warn)}.metric.danger .metric-bar span{background:var(--danger)}.metric.ok .metric-bar span{background:var(--ok)}.detail-list{display:grid;gap:8px;margin-top:14px;color:var(--muted);font-size:13px}.detail-list strong{color:var(--text)}details{margin-top:16px}summary{cursor:pointer;color:var(--accent);margin-bottom:10px}</style></head><body>' . $bar . $innerHtml . '</body></html>';
}
$view = $_GET['view'] ?? '';
function validContainerIdParam($id) {
    return is_string($id) && preg_match('/^[a-fA-F0-9]{12,64}$/', $id);
}
if ($view === 'logs' && isset($_GET['id'])) {
    $lid = $_GET['id'];
    if (!validContainerIdParam($lid)) {
        viewPageShell(t('error'), '<h1>' . htmlspecialchars(t('invalid_id'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $lr = callDockerAPI(dockerApi('containers/' . rawurlencode($lid) . '/logs?stdout=true&stderr=true&tail=500'), 'GET', null, 90);
    $rawLog = is_string($lr['raw'] ?? null) ? $lr['raw'] : '';
    $text = ($lr['code'] === 200) ? dockerDecodeLogStream($rawLog) : ('HTTP ' . $lr['code']);
    $copyId = 'log_' . bin2hex(random_bytes(4));
    $innerHtml = '<div class="view-panel"><div class="log-toolbar"><h1>' . htmlspecialchars(t('container_logs'), ENT_QUOTES, 'UTF-8') . '</h1><button class="log-copy-btn" onclick="copyLogText(\'' . $copyId . '\',this)">' . ico('copy') . ' Copy</button></div>' . renderHighlightedLogs($text) . '</div><script id="' . $copyId . '-script">function copyLogText(id,btn){var e=document.querySelector(\'#\'+id+\' ~ .log-code, #\'+id+\'-script ~ .log-code\');if(!e){var all=document.querySelectorAll(\'.log-code\');if(all.length)e=all[all.length-1];}if(!e)return;var t=document.createElement(\'textarea\');t.value=e.textContent;document.body.appendChild(t);t.select();document.execCommand(\'copy\');document.body.removeChild(t);btn.innerHTML=\'\u2713 Copied!\';setTimeout(function(){btn.innerHTML=\'\u2398 Copy\';},2000);btn.classList.toggle(\'copied\',btn.innerHTML.indexOf(\'Copied\')>-1);setTimeout(function(){btn.classList.remove(\'copied\');},3000);}<\/script>';
    viewPageShell(t('container_logs'), $innerHtml);
    exit;
}
if ($view === 'inspect' && isset($_GET['id'])) {
    $iid = $_GET['id'];
    if (!validContainerIdParam($iid)) {
        viewPageShell(t('error'), '<h1>' . htmlspecialchars(t('invalid_id'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $ir = dockerContainerInspect($iid);
    $json = ($ir['code'] === 200 && is_array($ir['data'])) ? json_encode($ir['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ('Error: HTTP ' . $ir['code']);
    viewPageShell(t('inspect'), '<div class="view-panel"><h1>' . htmlspecialchars(t('inspect'), ENT_QUOTES, 'UTF-8') . '</h1><pre class="json-pre">' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '</pre></div>');
    exit;
}
if ($view === 'image_inspect' && isset($_GET['id'])) {
    $imageId = trim((string)$_GET['id']);
    if ($imageId === '') {
        viewPageShell(t('error'), '<h1>' . htmlspecialchars(t('docker_invalid_image'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $ir = callDockerAPI(dockerApi('images/' . rawurlencode($imageId) . '/json'));
    $json = json_encode($ir['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    viewPageShell(t('docker_image_inspect'), '<div class="view-panel"><h1>' . htmlspecialchars(t('docker_image_inspect'), ENT_QUOTES, 'UTF-8') . '</h1><pre class="json-pre">' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '</pre></div>');
    exit;
}
if ($view === 'volume_inspect' && isset($_GET['id'])) {
    $volumeName = trim((string)$_GET['id']);
    if ($volumeName === '') {
        viewPageShell(t('error'), '<h1>' . htmlspecialchars(t('docker_invalid_volume'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $vr = callDockerAPI(dockerApi('volumes/' . rawurlencode($volumeName)));
    $json = json_encode($vr['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    viewPageShell(t('docker_volume_inspect'), '<div class="view-panel"><h1>' . htmlspecialchars(t('docker_volume_inspect'), ENT_QUOTES, 'UTF-8') . '</h1><pre class="json-pre">' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '</pre></div>');
    exit;
}
if ($view === 'stats' && isset($_GET['id'])) {
    $sid = $_GET['id'];
    if (!validContainerIdParam($sid)) {
        viewPageShell(t('error'), '<h1>' . htmlspecialchars(t('invalid_id'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $sr = callDockerAPI(dockerApi('containers/' . rawurlencode($sid) . '/stats?stream=false'), 'GET', null, 30);
    $html = '<div class="view-panel"><h1>' . htmlspecialchars(t('stats_snapshot'), ENT_QUOTES, 'UTF-8') . '</h1>';
    if ($sr['code'] === 200 && is_array($sr['data'])) {
        $d = $sr['data'];
        $mem = $d['memory_stats'] ?? [];
        $mu = $mem['usage'] ?? 0;
        $ml = $mem['limit'] ?? 0;
        $mp = ($ml > 0) ? round(100 * $mu / $ml, 1) : 0;
        $cpuDelta = (float)(($d['cpu_stats']['cpu_usage']['total_usage'] ?? 0) - ($d['precpu_stats']['cpu_usage']['total_usage'] ?? 0));
        $systemDelta = (float)(($d['cpu_stats']['system_cpu_usage'] ?? 0) - ($d['precpu_stats']['system_cpu_usage'] ?? 0));
        $onlineCpus = (int)($d['cpu_stats']['online_cpus'] ?? 0);
        $cpuPercent = ($cpuDelta > 0 && $systemDelta > 0 && $onlineCpus > 0) ? round(($cpuDelta / $systemDelta) * $onlineCpus * 100, 1) : 0;
        $pidsCurrent = (int)($d['pids_stats']['current'] ?? 0);
        $pidsLimit = (int)($d['pids_stats']['limit'] ?? 0);
        $pidsPercent = $pidsLimit > 0 ? round(100 * $pidsCurrent / $pidsLimit, 1) : 0;
        $rx = 0;
        $tx = 0;
        if (!empty($d['networks']) && is_array($d['networks'])) {
            foreach ($d['networks'] as $net) {
                if (!is_array($net)) {
                    continue;
                }
                $rx += (int)($net['rx_bytes'] ?? 0);
                $tx += (int)($net['tx_bytes'] ?? 0);
            }
        }
        $blockRead = 0;
        $blockWrite = 0;
        $ioRows = $d['blkio_stats']['io_service_bytes_recursive'] ?? [];
        if (is_array($ioRows)) {
            foreach ($ioRows as $io) {
                if (!is_array($io)) {
                    continue;
                }
                $op = strtolower((string)($io['op'] ?? ''));
                if ($op === 'read') {
                    $blockRead += (int)($io['value'] ?? 0);
                } elseif ($op === 'write') {
                    $blockWrite += (int)($io['value'] ?? 0);
                }
            }
        }
        $html .= '<div class="stats-grid-view">';
        $html .= statBarHtml('CPU', $cpuPercent . '% / ' . $onlineCpus . ' cores', min($cpuPercent, 100), $cpuPercent >= 85 ? 'danger' : ($cpuPercent >= 65 ? 'warn' : 'ok'));
        $html .= statBarHtml(t('memory'), formatBytesValue($mu) . ' / ' . ($ml ? formatBytesValue($ml) : '—') . ' (' . $mp . '%)', $mp, $mp >= 85 ? 'danger' : ($mp >= 65 ? 'warn' : 'ok'));
        $html .= statBarHtml('PIDs', $pidsCurrent . ($pidsLimit ? ' / ' . $pidsLimit : ''), $pidsPercent, $pidsPercent >= 85 ? 'danger' : ($pidsPercent >= 65 ? 'warn' : 'ok'));
        $html .= statBarHtml(t('network'), 'RX ' . formatBytesValue($rx) . ' / TX ' . formatBytesValue($tx), 100, '');
        $html .= statBarHtml('Block I/O read', formatBytesValue($blockRead), 100, '');
        $html .= statBarHtml('Block I/O write', formatBytesValue($blockWrite), 100, '');
        $html .= '</div><div class="detail-list">';
        $html .= '<div><strong>ID:</strong> ' . htmlspecialchars((string)($d['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<div><strong>Name:</strong> ' . htmlspecialchars((string)($d['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<div><strong>Read:</strong> ' . htmlspecialchars((string)($d['read'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '</div>';
    } else {
        $html .= '<p>' . htmlspecialchars(t('error'), ENT_QUOTES, 'UTF-8') . ' HTTP ' . (int)($sr['code'] ?? 0) . '</p>';
    }
    $html .= '</div>';
    viewPageShell(t('resources'), $html);
    exit;
}
if ($view === 'stats_data' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $sid = $_GET['id'];
    if (!validContainerIdParam($sid)) {
        echo json_encode(['ok' => false, 'error' => 'invalid_id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $sr = callDockerAPI(dockerApi('containers/' . rawurlencode($sid) . '/stats?stream=false'), 'GET', null, 20);
    if (($sr['code'] ?? 0) !== 200 || !is_array($sr['data'])) {
        echo json_encode(['ok' => false, 'error' => 'http_' . (int)($sr['code'] ?? 0)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $d = $sr['data'];
    $mem = $d['memory_stats'] ?? [];
    $mu = (int)($mem['usage'] ?? 0);
    $ml = (int)($mem['limit'] ?? 0);
    $cpuDelta = (float)(($d['cpu_stats']['cpu_usage']['total_usage'] ?? 0) - ($d['precpu_stats']['cpu_usage']['total_usage'] ?? 0));
    $systemDelta = (float)(($d['cpu_stats']['system_cpu_usage'] ?? 0) - ($d['precpu_stats']['system_cpu_usage'] ?? 0));
    $onlineCpus = (int)($d['cpu_stats']['online_cpus'] ?? 0);
    $cpuPercent = ($cpuDelta > 0 && $systemDelta > 0 && $onlineCpus > 0) ? (($cpuDelta / $systemDelta) * $onlineCpus * 100) : 0.0;
    $rx = 0;
    $tx = 0;
    if (!empty($d['networks']) && is_array($d['networks'])) {
        foreach ($d['networks'] as $net) {
            if (!is_array($net)) continue;
            $rx += (int)($net['rx_bytes'] ?? 0);
            $tx += (int)($net['tx_bytes'] ?? 0);
        }
    }
    $blockRead = 0;
    $blockWrite = 0;
    $ioRows = $d['blkio_stats']['io_service_bytes_recursive'] ?? [];
    if (is_array($ioRows)) {
        foreach ($ioRows as $io) {
            if (!is_array($io)) continue;
            $op = strtolower((string)($io['op'] ?? ''));
            if ($op === 'read') $blockRead += (int)($io['value'] ?? 0);
            if ($op === 'write') $blockWrite += (int)($io['value'] ?? 0);
        }
    }
    echo json_encode(['ok' => true, 'ts' => time(), 'stats' => ['cpu_total' => round($cpuPercent, 2), 'mem_used' => $mu, 'mem_limit' => $ml, 'pids' => (int)($d['pids_stats']['current'] ?? 0), 'net_rx' => $rx, 'net_tx' => $tx, 'blk_read' => $blockRead, 'blk_write' => $blockWrite]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_stack') {
    $stackId = (string)($_POST['id'] ?? '');
    if (!validStackIdParam($stackId)) {
        dockerRedirect(t('docker_invalid_stack'));
    }
    $stack = dockerStackById($stackId);
    if ($stack === null) {
        dockerRedirect(t('docker_stack_not_found'));
    }
    $result = dockerUpdateStackFile($stack, (string)($_POST['stack_file_content'] ?? ''), isset($_POST['prune']), isset($_POST['pull_image']));
    header('Location: ' . dockerUrl(['view' => 'stack_editor', 'id' => $stackId, 'action' => null, 'message' => dockerActionMessage($result, [200, 204], 'docker_stack_updated')]));
    exit;
}
if ($view === 'compose_project' && isset($_GET['project'])) {
    $projectName = trim((string)$_GET['project']);
    if ($projectName === '') { viewPageShell(t('error'), '<h1>Invalid project</h1>'); exit; }
    [$content, $error] = detectComposeFile($projectName);
    $innerHtml = '<div class="view-panel"><div class="log-toolbar"><h1>' . htmlspecialchars(t('docker_compose_detect') . ' · ' . $projectName, ENT_QUOTES, 'UTF-8') . '</h1></div>';
    if ($error !== null) {
        $innerHtml .= '<p style="color:var(--danger);margin-bottom:12px;padding:10px 12px;background:rgba(255,50,50,0.08);border-radius:8px;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        $innerHtml .= '<p style="color:var(--muted);font-size:13px;">' . htmlspecialchars(t('docker_compose_create_hint'), ENT_QUOTES, 'UTF-8') . '</p>';
    } else {
        $innerHtml .= '<textarea readonly spellcheck="false" style="width:100%;min-height:62vh;resize:vertical;background:rgba(0,0,0,.16);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:12px;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;">' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</textarea>';
        $innerHtml .= '<p style="color:var(--muted);margin-top:8px;font-size:12px;">' . htmlspecialchars(t('docker_compose_readonly'), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $innerHtml .= '</div>';
    viewPageShell(t('docker_compose_detect'), $innerHtml);
    exit;
}

if ($view === 'stack_editor' && isset($_GET['id'])) {
    $stackId = (string)$_GET['id'];
    if (!validStackIdParam($stackId)) {
        viewPageShell(t('error'), '<h1>' . htmlspecialchars(t('docker_invalid_stack'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    $stack = dockerStackById($stackId);
    if ($stack === null) {
        viewPageShell(t('error'), '<h1>' . htmlspecialchars(t('docker_stack_not_found'), ENT_QUOTES, 'UTF-8') . '</h1>');
        exit;
    }
    [$content, $fileError] = dockerStackFileContent($stackId);
    $stackName = (string)($stack['Name'] ?? ('#' . $stackId));
    $innerHtml = '<div class="view-panel"><form method="post" action="' . htmlspecialchars(dockerUrl(['view' => 'stack_editor', 'id' => $stackId, 'action' => null]), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="action" value="save_stack"><input type="hidden" name="id" value="' . htmlspecialchars($stackId, ENT_QUOTES, 'UTF-8') . '"><div class="log-toolbar"><h1>' . htmlspecialchars(t('docker_stack_editor') . ' · ' . $stackName, ENT_QUOTES, 'UTF-8') . '</h1><button class="log-copy-btn" type="submit">' . ico('check') . ' ' . htmlspecialchars(t('docker_update_stack'), ENT_QUOTES, 'UTF-8') . '</button></div>';
    if (trim((string)($_GET['message'] ?? '')) !== '') {
        $innerHtml .= '<p style="background:var(--accent-muted);border:1px solid var(--border);border-radius:8px;margin-bottom:12px;padding:10px 12px;">' . htmlspecialchars((string)$_GET['message'], ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if ($fileError !== null) {
        $innerHtml .= '<p style="color:var(--danger);margin-bottom:12px;">' . htmlspecialchars($fileError, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $innerHtml .= '<textarea name="stack_file_content" spellcheck="false" style="width:100%;min-height:62vh;resize:vertical;background:rgba(0,0,0,.16);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:12px;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;">' . htmlspecialchars((string)$content, ENT_QUOTES, 'UTF-8') . '</textarea><div class="detail-list"><label><input type="checkbox" name="prune" value="1"> Prune services</label><label><input type="checkbox" name="pull_image" value="1"> Pull latest image</label><div><strong>Endpoint:</strong> ' . (int)($stack['EndpointId'] ?? getDockerEndpointId()) . '</div><div><strong>ID:</strong> ' . htmlspecialchars($stackId, ENT_QUOTES, 'UTF-8') . '</div></div></form></div>';
    viewPageShell(t('docker_stack_editor'), $innerHtml);
    exit;
}
$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'stop_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/stop'), 'POST');
    dockerRedirect(dockerActionMessage($result, [204, 304], 'docker_container_stopped'));
}

if ($action === 'start_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/start'), 'POST');
    dockerRedirect(dockerActionMessage($result, [204, 304], 'docker_container_started'));
}

if ($action === 'restart_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/restart'), 'POST');
    dockerRedirect(dockerActionMessage($result, [204], 'docker_container_restarted'));
}

if ($action === 'remove_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '?force=true'), 'DELETE');
    dockerRedirect(dockerActionMessage($result, [204], 'docker_container_removed'));
}
if ($action === 'pull_image' && isset($_GET['id'])) {
    $insp = dockerContainerInspect($_GET['id']);
    $img = ($insp['code'] === 200 && is_array($insp['data'])) ? ($insp['data']['Config']['Image'] ?? '') : '';
    if ($img === '') {
        $message = t('docker_container_image_missing');
    } else {
        $pr = dockerPullImage($img);
        $raw = is_string($pr['raw'] ?? null) ? $pr['raw'] : '';
        if ($pr['code'] !== 200) {
            $message = tf('docker_image_pull_http_error', ['code' => $pr['code']]);
        } elseif ($raw !== '' && (strpos($raw, '"error"') !== false || strpos($raw, '"errorDetail"') !== false)) {
            $message = t('docker_image_pull_docker_error');
        } else {
            $message = tf('docker_image_pulled', ['image' => $img]);
        }
    }
    dockerRedirect($message);
}
if ($action === 'pull_named_image' && isset($_GET['image'])) {
    $img = trim((string)$_GET['image']);
    if ($img === '') {
        $message = t('docker_invalid_image');
    } else {
        $pr = dockerPullImage($img);
        $raw = is_string($pr['raw'] ?? null) ? $pr['raw'] : '';
        if ($pr['code'] !== 200) {
            $message = tf('docker_image_pull_http_error', ['code' => $pr['code']]);
        } elseif ($raw !== '' && (strpos($raw, '"error"') !== false || strpos($raw, '"errorDetail"') !== false)) {
            $message = t('docker_image_pull_docker_error');
        } else {
            $message = tf('docker_image_pulled', ['image' => $img]);
        }
    }
    dockerRedirect($message);
}
if ($action === 'remove_image' && isset($_GET['id'])) {
    $imageId = trim((string)$_GET['id']);
    if ($imageId === '') {
        dockerRedirect(t('docker_invalid_image'));
    }
    $result = callDockerAPI(dockerApi('images/' . rawurlencode($imageId) . '?force=true&noprune=false'), 'DELETE', null, 120);
    dockerRedirect(dockerActionMessage($result, [200, 202, 204], 'docker_image_removed'));
}
if ($action === 'remove_volume' && isset($_GET['id'])) {
    $volumeName = trim((string)$_GET['id']);
    if ($volumeName === '') {
        dockerRedirect(t('docker_invalid_volume'));
    }
    $result = callDockerAPI(dockerApi('volumes/' . rawurlencode($volumeName) . '?force=true'), 'DELETE', null, 120);
    dockerRedirect(dockerActionMessage($result, [204], 'docker_volume_removed'));
}
if ($action === 'update_container' && isset($_GET['id'])) {
    $insp = dockerContainerInspect($_GET['id']);
    $img = ($insp['code'] === 200 && is_array($insp['data'])) ? ($insp['data']['Config']['Image'] ?? '') : '';
    if ($img === '') {
        dockerRedirect(t('docker_container_image_missing'));
    }
    $pr = dockerPullImage($img);
    $raw = is_string($pr['raw'] ?? null) ? $pr['raw'] : '';
    if ($pr['code'] !== 200) {
        dockerRedirect(tf('docker_pull_http_error', ['code' => $pr['code']]));
    }
    if ($raw !== '' && (strpos($raw, '"error"') !== false || strpos($raw, '"errorDetail"') !== false)) {
        dockerRedirect(t('docker_image_pull_docker_error'));
    }
    $message = dockerRecreateContainer($_GET['id'], null);
    if (strpos($message, 'Error') !== 0) {
        $message = tf('docker_container_updated', ['message' => $message]);
    }
    dockerRedirect($message);
}
if ($action === 'recreate_container' && isset($_GET['id'])) {
    $msg = dockerRecreateContainer($_GET['id'], null);
    dockerRedirect($msg);
}
if ($action === 'pause_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/pause'), 'POST', null, 60);
    dockerRedirect(dockerActionMessage($result, [204], 'docker_container_paused'));
}
if ($action === 'unpause_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/unpause'), 'POST', null, 60);
    dockerRedirect(dockerActionMessage($result, [204], 'docker_container_unpaused'));
}
if ($action === 'kill_container' && isset($_GET['id'])) {
    $result = callDockerAPI(dockerApi('containers/' . rawurlencode($_GET['id']) . '/kill'), 'POST', null, 60);
    dockerRedirect(dockerActionMessage($result, [204], 'docker_container_killed'));
}

$message = $_GET['message'] ?? '';
$epId = getDockerEndpointId();
$containers = callDockerAPI(dockerApi('containers/json?all=true'));
$stacks = ['code' => 200, 'data' => []];
$stats = callDockerAPI(dockerApi('info'));
$images = callDockerAPI(dockerApi('images/json'));
$systemInfo = ['code' => 200, 'data' => ['Name' => 'Local Docker socket', 'URL' => docker_socket_path(), 'Status' => 1]];
$dockerStatus = callDockerAPI(dockerApi('version'));
$networksList = callDockerAPI(dockerApi('networks'));
$volumesList = callDockerAPI(dockerApi('volumes'));
$dockerVersionInfo = callDockerAPI(dockerApi('version'));
$systemDfData = callDockerAPI(dockerApi('system/df'));
$apiError = null;
$cd = $containers['data'];
if ($containers['code'] !== 200) {
    $apiError = is_array($cd) && isset($cd['message']) ? $cd['message'] : ('HTTP ' . $containers['code']);
} elseif (!is_array($cd)) {
    $apiError = 'Invalid API response';
} elseif (isset($cd['message']) && !array_key_exists(0, $cd)) {
    $apiError = $cd['message'];
}
$stacksMap = [];
$containersInStack = [];
$usedVolumesMap = [];
if (($stacks['code'] ?? 0) === 200 && is_array($stacks['data'])) {
    foreach ($stacks['data'] as $stack) {
        if (!is_array($stack)) continue;
        $stackEndpointId = (int)($stack['EndpointId'] ?? $stack['EndpointID'] ?? 0);
        if ($stackEndpointId > 0 && $stackEndpointId !== (int)$epId) continue;
        $stackName = trim((string)($stack['Name'] ?? ''));
        if ($stackName === '') continue;
        $stacksMap[$stackName] = [
            'name' => $stackName,
            'containers' => 0,
            'id' => isset($stack['Id']) ? (int)$stack['Id'] : null,
            'type' => (int)($stack['Type'] ?? 0),
            'endpoint_id' => $stackEndpointId,
            'status' => (string)($stack['Status'] ?? '')
        ];
    }
}
if (is_array($containers['data'])) {
    foreach ($containers['data'] as $c) {
        if (!is_array($c)) continue;
        $labels = is_array($c['Labels'] ?? null) ? $c['Labels'] : [];
        $stackName = trim((string)($labels['com.docker.compose.project'] ?? $labels['com.docker.stack.namespace'] ?? ''));
        if ($stackName !== '') {
            $cname = str_replace('/', '', (string)($c['Names'][0] ?? ''));
            if (!isset($stacksMap[$stackName])) $stacksMap[$stackName] = ['name' => $stackName, 'containers' => 0, 'id' => null, 'type' => 0, 'endpoint_id' => (int)$epId, 'status' => ''];
            $stacksMap[$stackName]['containers']++;
            if (!isset($containersInStack[$stackName])) $containersInStack[$stackName] = [];
            $containersInStack[$stackName][] = ['id' => (string)($c['Id'] ?? ''), 'name' => $cname, 'image' => (string)($c['Image'] ?? ''), 'state' => (string)($c['State'] ?? '')];
        }
        if (!empty($c['Mounts']) && is_array($c['Mounts'])) {
            foreach ($c['Mounts'] as $mount) {
                $vn = (string)($mount['Name'] ?? '');
                if ($vn !== '') $usedVolumesMap[$vn] = true;
            }
        }
    }
}
if ($stacksMap !== []) {
    uasort($stacksMap, static function ($a, $b) {
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
}
$messageIsError = $message !== '' && (
    strpos($message, 'Error') === 0 ||
    strpos($message, t('error')) === 0 ||
    strpos($message, t('api_error')) === 0
);
$startupModals = [];
if ($apiError) {
    $startupModals[] = [
        'type' => 'error',
        'title' => t('api_error'),
        'message' => 'API: ' . $apiError . ' (endpoint #' . (int)$epId . ')',
    ];
}
if ($message !== '') {
    $startupModals[] = [
        'type' => $messageIsError ? 'error' : 'success',
        'title' => $messageIsError ? t('error') : t('docker_action_done'),
        'message' => $message,
    ];
}
function ico($n) {
    $ico = [
        'brand' => '<svg class="ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'cube' => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 16-7 4V10l7-4v10z"/><path d="m3.27 6.96 7 4.01"/><path d="M12 22V12"/><path d="m3.27 6.96-1.02 5.64a4 4 0 0 0 1.57 3.98L12 22l8.18-5.42a4 4 0 0 0 1.57-3.98L21 6.96 12 2 3.27 6.96z"/></svg>',
        'layers' => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.05 4.32a2 2 0 0 1-1.9 0L2 17.65"/><path d="m22 12.65-9.05 4.32a2 2 0 0 1-1.9 0L2 12.65"/></svg>',
        'info' => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
        'refresh' => '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>',
        'play' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg>',
        'stop' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="5" width="14" height="14" rx="1"/></svg>',
        'restart' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M21 21v-5h-5"/></svg>',
        'trash' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>',
        'pause' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>',
        'download' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'update' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>',
        'duplicate' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>',
        'kill' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
        'dot' => '<svg class="ico" width="8" height="8" viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="12" cy="12" r="5"/></svg>',
        'check' => '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        'x' => '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
        'chevron' => '<svg class="ico chevron-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>',
        'network' => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>',
        'volume' => '<svg class="ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/></svg>',
        'external' => '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
        'menu' => '<svg class="ico" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>',
        'file' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'chart' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'search' => '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
        'copy' => '<svg class="ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>',
    ];
    return $ico[$n] ?? '';
}

?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('title'), ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root {
            --bg: <?php echo $theme === 'light' ? '#f3f6fb' : '#0f1419'; ?>;
            --surface: <?php echo $theme === 'light' ? '#ffffff' : 'rgba(18,24,32,0.8)'; ?>;
            --surface-hover: <?php echo $theme === 'light' ? '#eef3fb' : 'rgba(255,255,255,0.10)'; ?>;
            --border: <?php echo $theme === 'light' ? '#d7deeb' : 'rgba(255,255,255,0.14)'; ?>;
            --text: <?php echo $theme === 'light' ? '#0f172a' : '#f4f7fb'; ?>;
            --muted: <?php echo $theme === 'light' ? '#64748b' : 'rgba(255,255,255,0.42)'; ?>;
            --accent: <?php echo htmlspecialchars($accent, ENT_QUOTES, 'UTF-8'); ?>;
            --accent-muted: <?php echo $theme === 'light' ? 'rgba(37,99,235,0.1)' : 'rgba(' . intval(substr($accent,1,2),16) . ',' . intval(substr($accent,3,2),16) . ',' . intval(substr($accent,5,2),16) . ',0.14)'; ?>;
            --danger: #f87171;
            --danger-bg: rgba(248, 113, 113, 0.12);
            --ok: #4ade80;
            --ok-bg: rgba(74, 222, 128, 0.12);
            --warn: #fbbf24;
            --warn-bg: rgba(251, 191, 36, 0.12);
            --scroll-track: <?php echo $theme === 'light' ? '#e6ebf3' : 'rgba(255,255,255,0.04)'; ?>;
            --scroll-thumb: <?php echo $theme === 'light' ? '#aeb9c9' : 'rgba(255,255,255,0.22)'; ?>;
            --scroll-thumb-hover: <?php echo $theme === 'light' ? '#8794a8' : 'rgba(255,255,255,0.35)'; ?>;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; scrollbar-width: thin; scrollbar-color: var(--scroll-thumb) var(--scroll-track); }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--scroll-track); border-radius: 8px; }
        ::-webkit-scrollbar-thumb { background: var(--scroll-thumb); border: 2px solid var(--scroll-track); border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--scroll-thumb-hover); }
        .ico { display: inline-block; vertical-align: middle; flex-shrink: 0; }
        .chevron-ico { transition: transform 0.2s ease; opacity: 0.65; }
        .c-row.expanded .chevron-ico { transform: rotate(90deg); opacity: 1; }
        .c-row { cursor: pointer; }
        .c-row:focus { outline: 2px solid rgba(34, 211, 238, 0.45); outline-offset: -2px; }
        .c-name-col { width: 18%; max-width: 220px; }
        .c-image-col { width: 28%; }
        .c-status-col { width: 14%; }
        .c-ports-col { width: 24%; }
        .c-created-col { width: 16%; }
        .c-name-cell { width: 18%; max-width: 220px; }
        .c-name-wrap { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .c-name-wrap strong { display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mono-clip { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; }
        .image-cell { max-width: 320px; font-size: 12px; }
        .ports-cell { max-width: 280px; font-size: 11px; }
        .containers-table-wrap { --table-scroll-left: 0px; }
        .containers-table-wrap .c-actions td { overflow: hidden; }
        .containers-table-wrap .c-panel { transform: translateX(var(--table-scroll-left)); width: calc(100% - var(--table-scroll-left)); min-width: 560px; transition: transform 0.05s linear; }
        .c-panel { padding: 14px 16px; background: rgba(0,0,0,0.22); border: 1px solid var(--border); border-radius: 10px; }
        .c-panel-hint { font-size: 12px; color: var(--muted); margin-bottom: 10px; }
        .c-quick { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
        .c-quick a { font-size: 12px; color: var(--accent); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .c-quick a:hover { text-decoration: underline; }
        .stack-open-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--accent-muted); color: var(--accent); text-decoration: none; font-size: 12px; font-weight: 600; }
        .stack-open-btn:hover { filter: brightness(1.08); }
        .stack-container-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; }
        .stack-container-card { background: rgba(0,0,0,0.18); border: 1px solid var(--border); border-radius: 10px; padding: 12px; cursor: pointer; transition: border-color 0.2s, background 0.2s; }
        .stack-container-card:hover { border-color: rgba(34, 211, 238, 0.4); background: rgba(0,0,0,0.28); }
        .stack-card-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; min-width: 0; }
        .scc-name { font-size: 13px; font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
        .stack-card-image { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--muted); min-width: 0; }
        .stack-card-image .mono-clip { font-size: 11px; color: var(--muted); }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100%;
            background: var(--surface);
            border-right: 1px solid var(--border);
            z-index: 100;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 22px 20px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(34, 211, 238, 0.12) 0%, transparent 100%);
        }
        .brand-row {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--accent);
        }
        .sidebar-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }
        .sidebar-header p {
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
        }
        .sidebar-nav { padding: 16px 0; }
        .nav-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--muted);
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            border-left: 3px solid transparent;
        }
        .nav-item:hover { background: var(--surface-hover); color: var(--text); }
        .nav-item.active {
            background: var(--accent-muted);
            color: var(--accent);
            border-left-color: var(--accent);
        }
        .nav-icon { display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; }
        .sidebar-footer {
            margin-top: auto;
            padding: 14px 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .sidebar-footer a {
            font-size: 12px;
            color: var(--muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 4px;
            border-radius: 8px;
        }
        .sidebar-footer a:hover { color: var(--text); background: var(--surface-hover); }
        .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99; }
        .sidebar-backdrop.show { display: block; }
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .filter-bar .btn-icon {
            padding: 10px 12px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            cursor: pointer;
        }
        .filter-bar .btn-icon { display: none; }
        .toolbar-search {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }
        .toolbar-search input {
            flex: 1;
            min-width: 0;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
        }
        .toolbar-search input::placeholder { color: var(--muted); }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 44px;
        }
        .page-title { font-size: 22px; font-weight: 600; }
        .connection-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: var(--accent-muted);
            border: 1px solid rgba(34, 211, 238, 0.25);
            border-radius: 20px;
            color: var(--accent);
            font-size: 13px;
        }
        .connection-status.err {
            background: var(--danger-bg);
            border-color: rgba(248, 113, 113, 0.35);
            color: var(--danger);
        }
        .status-dot {
            width: 8px;
            height: 8px;
            background: var(--accent);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        .connection-status.err .status-dot {
            background: var(--danger);
            animation: none;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.45; }
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            transition: border-color 0.2s;
        }
        .stat-card:hover { border-color: rgba(34, 211, 238, 0.35); }
        .stat-title { color: var(--muted); font-size: 13px; margin-bottom: 8px; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text); }
        .stat-value.accent { color: var(--accent); font-size: 22px; }
        .stats-grid-view { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:14px; }
        .monitor-tile { background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.05)); border:1px solid var(--border); border-radius:12px; padding:12px; box-shadow:0 8px 18px rgba(0,0,0,0.14); }
        .monitor-tile .metric-head { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:8px; font-size:12px; color:var(--muted); }
        .monitor-tile .metric-head strong { color:var(--text); font-size:13px; }
        .monitor-canvas-wrap { background:rgba(0,0,0,0.16); border:1px solid var(--border); border-radius:10px; padding:6px 6px 2px; }
        .monitor-canvas-wrap canvas { width:100%; height:86px; display:block; }
        .monitor-scale { margin-top:6px; font-size:11px; color:var(--muted); display:flex; justify-content:space-between; }
        .section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        .section-title-wrap { display: flex; align-items: center; gap: 10px; }
        .section-title { font-size: 17px; font-weight: 600; }
        .section-title .ico { color: var(--accent); }
        .table-container { overflow-x: auto; }
        .table-container::-webkit-scrollbar { width: 8px; height: 8px; }
        .table-container::-webkit-scrollbar-track { background: var(--scroll-track); }
        .table-container::-webkit-scrollbar-thumb { background: var(--scroll-thumb); border: 2px solid var(--scroll-track); border-radius: 8px; }
        .table-container::-webkit-scrollbar-thumb:hover { background: var(--scroll-thumb-hover); }
        table { width: 100%; border-collapse: collapse; min-width: 640px; }
        th {
            text-align: left;
            padding: 10px 12px;
            background: rgba(34, 211, 238, 0.06);
            font-weight: 600;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        tr:hover td { background: <?php echo $theme === 'light' ? 'rgba(15,23,42,0.03)' : 'rgba(255,255,255,0.02)'; ?>; }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-badge .ico { color: inherit; opacity: 0.9; }
        .status-running { background: var(--ok-bg); color: var(--ok); }
        .status-exited { background: var(--danger-bg); color: var(--danger); }
        .status-created { background: rgba(96, 165, 250, 0.15); color: #93c5fd; }
        .status-paused { background: var(--warn-bg); color: var(--warn); }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            max-width: 420px;
        }
        .btn {
            padding: 6px 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 500;
            transition: background 0.15s, transform 0.1s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #fff;
            border: 1px solid transparent;
        }
        .btn:hover { filter: brightness(1.08); }
        .btn .ico { opacity: 0.95; }
        .btn-start { background: #059669; }
        .btn-stop { background: #b91c1c; }
        .btn-restart { background: #2563eb; }
        .btn-remove { background: #475569; border-color: var(--border); }
        .btn-pause { background: #a16207; }
        .btn-unpause { background: #0d9488; }
        .btn-pull { background: #7c3aed; }
        .btn-update { background: #0891b2; border-color: rgba(34, 211, 238, 0.4); }
        .btn-recreate { background: #4338ca; }
        .btn-kill { background: #9f1239; }
        .btn-ghost {
            background: var(--surface-hover);
            color: var(--text);
            border-color: var(--border);
        }
        .tag-pill {
            background: var(--surface-hover);
            color: var(--muted);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            border: 1px solid var(--border);
        }
        .info-grid { display: grid; gap: 10px; color: var(--muted); font-size: 14px; }
        .info-grid strong { color: var(--text); }
        .docker-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 22px;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(8px);
            z-index: 500;
        }
        .docker-modal.active { display: flex; }
        .docker-modal-card {
            width: min(1040px, 96vw);
            height: min(760px, 88vh);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 22px 70px rgba(0,0,0,0.35);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .message-modal-card {
            width: min(440px, 94vw);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.45);
            overflow: hidden;
        }
        .message-modal-body {
            display: flex;
            gap: 14px;
            padding: 22px;
        }
        .message-modal-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: var(--accent-muted);
            color: var(--accent);
        }
        .message-modal-icon.success { background: var(--ok-bg); color: var(--ok); }
        .message-modal-icon.error { background: var(--danger-bg); color: var(--danger); }
        .message-modal-icon.warn { background: var(--warn-bg); color: var(--warn); }
        .message-modal-content { min-width: 0; }
        .message-modal-content h3 { font-size: 16px; margin: 0 0 8px; color: var(--text); }
        .message-modal-content p { font-size: 14px; line-height: 1.55; color: var(--muted); margin: 0; word-break: break-word; }
        .message-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 14px 18px;
            border-top: 1px solid var(--border);
            background: <?php echo $theme === 'light' ? 'rgba(15,23,42,0.025)' : 'rgba(0,0,0,0.16)'; ?>;
        }
        .docker-modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-hover);
        }
        .docker-modal-title { font-size: 15px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .docker-modal-close {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
        }
        .docker-modal-frame { flex: 1; width: 100%; border: 0; background: var(--bg); }
        h3 { font-size: 15px; color: var(--text); margin-bottom: 12px; }
        @media (max-width: 900px) {
            .filter-bar .btn-icon { display: block; }
            .sidebar { transform: translateX(-100%); transition: transform 0.25s ease; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .top-bar { flex-wrap: wrap; gap: 12px; }
            .containers-table-wrap .c-panel { min-width: 480px; }
        }
    </style>
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand-row"><?php echo ico('brand'); ?><h2><?php echo htmlspecialchars(t('title'), ENT_QUOTES, 'UTF-8'); ?></h2></div>
            <p><?php echo htmlspecialchars($displayDomain, ENT_QUOTES, 'UTF-8'); ?> · <span title="API">CE</span></p>
        </div>
        <div class="sidebar-nav">
            <div class="nav-item active" onclick="showSection('stacks', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('layers'); ?></span>
                <span>Stacks</span>
            </div>
            <div class="nav-item" onclick="showSection('containers', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('cube'); ?></span>
                <span><?php echo htmlspecialchars(t('containers'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="nav-item" onclick="showSection('networks', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('network'); ?></span>
                <span><?php echo htmlspecialchars(t('networks'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="nav-item" onclick="showSection('volumes', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('volume'); ?></span>
                <span><?php echo htmlspecialchars(t('volumes'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="nav-item" onclick="showSection('images', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('layers'); ?></span>
                <span><?php echo htmlspecialchars(t('images'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="nav-item" onclick="showSection('info', this); closeSidebar();">
                <span class="nav-icon"><?php echo ico('info'); ?></span>
                <span><?php echo htmlspecialchars(t('system'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="sidebar-footer">
            <a href="http://<?php echo htmlspecialchars($localIpAddr, ENT_QUOTES, 'UTF-8'); ?>:9000" target="_blank" rel="noopener noreferrer"><?php echo ico('external'); ?> <?php echo htmlspecialchars(t('main_interface'), ENT_QUOTES, 'UTF-8'); ?><?php echo htmlspecialchars($localIpAddr, ENT_QUOTES, 'UTF-8'); ?>:9000</a>
            <a href="https://docs.docker.com/reference/api/engine/" target="_blank" rel="noopener noreferrer"><?php echo ico('info'); ?> <?php echo htmlspecialchars(t('api_docs'), ENT_QUOTES, 'UTF-8'); ?></a>
            <a href="https://docs.docker.com/reference/api/engine/version/v1.51/" target="_blank" rel="noopener noreferrer"><?php echo ico('layers'); ?> <?php echo htmlspecialchars(t('swagger'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="filter-bar" id="containerFilterBar">
            <button type="button" class="btn-icon" onclick="toggleSidebar()" aria-label="Menu"><?php echo ico('menu'); ?></button>
            <div class="toolbar-search">
                <?php echo ico('search'); ?>
                <input type="search" id="containerFilter" placeholder="<?php echo htmlspecialchars(t('search'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" aria-label="<?php echo htmlspecialchars(t('search'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="top-bar">
            <h1 class="page-title" id="pageTitle">Stacks</h1>
            <div class="connection-status<?php echo $apiError ? ' err' : ''; ?>">
                <div class="status-dot"></div>
                <span><?php echo htmlspecialchars($apiError ? t('api_error') : rtrim(t('connected')), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-title"><?php echo htmlspecialchars(t('total_containers'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="stat-value" id="statTotalContainers"><?php echo is_array($containers['data']) ? count($containers['data']) : 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><?php echo htmlspecialchars(t('running'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="stat-value" id="statRunning">
                    <?php 
                    $running = 0;
                    if (is_array($containers['data'])) {
                        foreach ($containers['data'] as $c) {
                            if ($c['State'] === 'running') $running++;
                        }
                    }
                    echo $running;
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><?php echo htmlspecialchars(t('images_count'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="stat-value" id="statImages"><?php echo (is_array($images['data']) && ($images['code'] ?? 0) === 200) ? count($images['data']) : 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><?php echo htmlspecialchars(t('docker_version'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="stat-value accent" id="statVersion">
                    <?php echo (is_array($stats['data']) && isset($stats['data']['ServerVersion'])) ? htmlspecialchars($stats['data']['ServerVersion']) : 'N/A'; ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Stacks</div>
                <div class="stat-value" id="statStacks"><?php echo count($stacksMap); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Unused volumes</div>
                <div class="stat-value" id="statUnusedVolumes">0</div>
            </div>
        </div>
        <div class="section" id="chartsSection" style="display:none;margin-bottom:16px;">
            <div class="section-header"><div class="section-title-wrap"><?php echo ico('chart'); ?><div class="section-title" id="monitorTitle">Live monitoring</div></div></div>
            <div class="stats-grid-view">
                <div class="monitor-tile"><div class="metric-head"><span>CPU total</span><strong id="mCpu">0%</strong></div><div class="monitor-canvas-wrap"><canvas id="chartCpu"></canvas></div><div class="monitor-scale"><span>0</span><span id="sCpu">max 0%</span></div></div>
                <div class="monitor-tile"><div class="metric-head"><span>Memory</span><strong id="mMem">0 B</strong></div><div class="monitor-canvas-wrap"><canvas id="chartMem"></canvas></div><div class="monitor-scale"><span>0</span><span id="sMem">max 0 B</span></div></div>
                <div class="monitor-tile"><div class="metric-head"><span>Network RX/TX</span><strong id="mNet">0 B / 0 B</strong></div><div class="monitor-canvas-wrap"><canvas id="chartNet"></canvas></div><div class="monitor-scale"><span>0</span><span id="sNet">max 0 B</span></div></div>
                <div class="monitor-tile"><div class="metric-head"><span>Block I/O</span><strong id="mIo">0 B / 0 B</strong></div><div class="monitor-canvas-wrap"><canvas id="chartIo"></canvas></div><div class="monitor-scale"><span>0</span><span id="sIo">max 0 B</span></div></div>
                <div class="monitor-tile"><div class="metric-head"><span>PIDs</span><strong id="mPids">0</strong></div><div class="monitor-canvas-wrap"><canvas id="chartPids"></canvas></div><div class="monitor-scale"><span>0</span><span id="sPids">max 0</span></div></div>
                <div class="monitor-tile"><div class="metric-head"><span>Container</span><strong id="mContainers">n/a</strong></div><div class="monitor-canvas-wrap"><canvas id="chartContainers"></canvas></div><div class="monitor-scale"><span>0</span><span id="sContainer">CPU/PIDs scale</span></div></div>
            </div>
        </div>
        <div id="stacksSection" class="section">
            <div class="section-header"><div class="section-title-wrap"><?php echo ico('layers'); ?><div class="section-title">Stacks</div></div></div>
            <div class="table-container containers-table-wrap"><table><thead><tr><th>Name</th><th>Containers</th><th>Type</th><th><?php echo htmlspecialchars(t('docker_stack_file'), ENT_QUOTES, 'UTF-8'); ?></th><th>Open</th></tr></thead><tbody>
            <?php if (!empty($stacksMap)): foreach ($stacksMap as $stackName => $stackData): $sc=(int)($stackData['containers']??0); $stackId=$stackData['id'] ?? null; $stackType=(int)($stackData['type'] ?? 0); ?>
                <tr class="c-row" tabindex="0" data-search="<?php echo htmlspecialchars(strtolower((string)$stackName), ENT_QUOTES, 'UTF-8'); ?>"><td><span class="c-name-wrap"><?php echo ico('chevron'); ?><strong><?php echo htmlspecialchars((string)$stackName, ENT_QUOTES, 'UTF-8'); ?></strong></span></td><td><?php echo $sc; ?></td><td><?php echo $stackType === 2 ? 'Compose' : ($stackType === 1 ? 'Swarm' : ''); ?></td><td><?php if ($stackId !== null): ?><a class="stack-open-btn" href="<?php echo htmlspecialchars(dockerUrl(['view' => 'stack_editor', 'id' => (string)$stackId, 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_stack_editor') . ' · ' . (string)$stackName, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('file'); ?> <?php echo htmlspecialchars(t('docker_edit_stack'), ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?><a class="stack-open-btn" href="<?php echo htmlspecialchars(dockerUrl(['view' => 'compose_project', 'project' => (string)$stackName, 'id' => null, 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_compose_detect') . ' · ' . (string)$stackName, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('file'); ?> <?php echo htmlspecialchars(t('docker_compose_detect_btn'), ENT_QUOTES, 'UTF-8'); ?></a><?php endif; ?></td><td><a class="stack-open-btn" href="#" onclick="event.preventDefault();showSection('containers',document.querySelector('.nav-item[onclick*=\'containers\']'));applyContainerFilter(<?php echo htmlspecialchars(json_encode((string)$stackName, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>);">Show containers</a></td></tr>
                <tr class="c-actions" style="display:none"><td colspan="5"><div class="c-panel"><div class="c-panel-hint">Containers in stack</div><?php if (!empty($containersInStack[$stackName])): ?><div class="stack-container-grid"><?php foreach ($containersInStack[$stackName] as $stackContainer): $scState = (string)$stackContainer['state']; ?><div class="stack-container-card" onclick="event.stopPropagation();showSection('containers',document.querySelector('.nav-item[onclick*=\'containers\']'));applyContainerFilter(<?php echo htmlspecialchars(json_encode((string)$stackContainer['name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>);" title="Click to navigate to this container"><div class="stack-card-top"><span class="scc-name"><?php echo htmlspecialchars((string)$stackContainer['name'], ENT_QUOTES, 'UTF-8'); ?></span><span class="status-badge status-<?php echo $scState === 'running' ? 'running' : ($scState === 'exited' ? 'exited' : ($scState === 'paused' ? 'paused' : 'created')); ?>"><?php echo ico('dot'); ?> <?php echo htmlspecialchars($scState, ENT_QUOTES, 'UTF-8'); ?></span></div><div class="stack-card-image"><?php echo ico('cube'); ?> <span class="mono-clip"><?php echo htmlspecialchars((string)$stackContainer['image'], ENT_QUOTES, 'UTF-8'); ?></span></div></div><?php endforeach; ?></div><?php else: ?><div class="detail-list"><div>No containers</div></div><?php endif; ?></div></td></tr>
            <?php endforeach; else: ?><tr><td colspan="5" style="text-align:center;padding:40px;">No stacks found</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        
        <div id="containersSection" class="section" style="display:none;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <?php echo ico('cube'); ?>
                    <div class="section-title"><?php echo htmlspecialchars(t('containers_list'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <button type="button" class="btn btn-ghost" onclick="location.reload()"><?php echo ico('refresh'); ?> <?php echo htmlspecialchars(t('refresh'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            <div class="table-container containers-table-wrap">
                <table>
                    <colgroup>
                        <col class="c-name-col">
                        <col class="c-image-col">
                        <col class="c-status-col">
                        <col class="c-ports-col">
                        <col class="c-created-col">
                    </colgroup>
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('name'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('image'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('status'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('ports'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('created'), ENT_QUOTES, 'UTF-8'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (is_array($containers['data']) && count($containers['data']) > 0): ?>
                            <?php foreach ($containers['data'] as $container): ?>
                            <?php
                            $st = $container['State'] ?? '';
                            $cid = rawurlencode($container['Id']);
                            $cname = str_replace('/', '', $container['Names'][0] ?? 'N/A');
                            $cimg = $container['Image'] ?? '';
                            $labels = is_array($container['Labels'] ?? null) ? $container['Labels'] : [];
                            $stackNameForContainer = trim((string)($labels['com.docker.compose.project'] ?? $labels['com.docker.stack.namespace'] ?? ''));
                            $searchBlob = strtolower($cname . ' ' . $cimg . ' ' . $stackNameForContainer);
                            $ports = [];
                            if (isset($container['Ports']) && is_array($container['Ports']) && count($container['Ports']) > 0) {
                                foreach ($container['Ports'] as $port) {
                                    $pp = $port['PrivatePort'] ?? '';
                                    $typ = $port['Type'] ?? 'tcp';
                                    if (isset($port['PublicPort'])) {
                                        $ports[] = $port['PublicPort'] . ':' . $pp . '/' . $typ;
                                    } elseif ($pp !== '') {
                                        $ports[] = $pp . '/' . $typ;
                                    }
                                }
                            }
                            $portsText = count($ports) > 0 ? implode(', ', $ports) : '-';
                            ?>
                            <tr class="c-row" tabindex="0" data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>" data-stack="<?php echo htmlspecialchars($stackNameForContainer, ENT_QUOTES, 'UTF-8'); ?>" data-container-id="<?php echo htmlspecialchars((string)$container['Id'], ENT_QUOTES, 'UTF-8'); ?>" data-container-name="<?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="c-name-cell" title="<?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?>"><div class="c-name-wrap"><?php echo ico('chevron'); ?><strong><?php echo htmlspecialchars($cname); ?></strong></div></td>
                                <td title="<?php echo htmlspecialchars($cimg ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?>"><span class="mono-clip image-cell"><?php echo htmlspecialchars($cimg ?: 'N/A'); ?></span></td>
                                <td>
                                    <span class="status-badge status-<?php echo $st === 'running' ? 'running' : ($st === 'exited' ? 'exited' : ($st === 'paused' ? 'paused' : 'created')); ?>">
                                        <?php echo ico('dot'); ?> <?php echo htmlspecialchars($st); ?>
                                    </span>
                                </td>
                                <td title="<?php echo htmlspecialchars($portsText, ENT_QUOTES, 'UTF-8'); ?>"><span class="mono-clip ports-cell"><?php echo htmlspecialchars($portsText); ?></span></td>
                                <td><?php $cr = $container['Created'] ?? 0; echo date('d.m.Y H:i', is_numeric($cr) ? (int)$cr : strtotime((string)$cr)); ?></td>
                            </tr>
                            <tr class="c-actions" style="display:none">
                                <td colspan="5">
                                    <div class="c-panel">
                                        <div class="c-panel-hint"><?php echo htmlspecialchars(t('hide_panel'), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="c-quick">
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['view' => 'logs', 'id' => $container['Id'], 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('logs') . ' · ' . $cname, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('file'); ?> <?php echo htmlspecialchars(t('logs'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['view' => 'inspect', 'id' => $container['Id'], 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('inspect') . ' · ' . $cname, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('layers'); ?> <?php echo htmlspecialchars(t('inspect'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['view' => 'stats', 'id' => $container['Id'], 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('resources') . ' · ' . $cname, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('chart'); ?> <?php echo htmlspecialchars(t('resources'), ENT_QUOTES, 'UTF-8'); ?></a>
                                        </div>
                                        <div class="action-buttons">
                                            <?php if ($st !== 'running'): ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'start_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-start" title="<?php echo htmlspecialchars(t('start'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('start') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('play'); ?> <?php echo htmlspecialchars(t('start'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php else: ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'stop_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-stop" title="<?php echo htmlspecialchars(t('stop'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('stop') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('stop'); ?> <?php echo htmlspecialchars(t('stop'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php endif; ?>
                                            <?php if ($st === 'running'): ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'pause_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-pause" title="<?php echo htmlspecialchars(t('pause'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('pause') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('pause'); ?> <?php echo htmlspecialchars(t('pause'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php endif; ?>
                                            <?php if ($st === 'paused'): ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'unpause_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-unpause" title="<?php echo htmlspecialchars(t('resume'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('resume') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('play'); ?> <?php echo htmlspecialchars(t('resume'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php endif; ?>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'restart_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-restart" title="<?php echo htmlspecialchars(t('restart'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('restart') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('restart'); ?> <?php echo htmlspecialchars(t('restart'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'pull_image', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-pull" title="<?php echo htmlspecialchars(t('pull'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('pull') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('download'); ?> <?php echo htmlspecialchars(t('pull'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'update_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-update" title="<?php echo htmlspecialchars(t('update'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('update') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('update'); ?> <?php echo htmlspecialchars(t('update'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'recreate_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-recreate" title="<?php echo htmlspecialchars(t('recreate'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('recreate') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('duplicate'); ?> <?php echo htmlspecialchars(t('recreate'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php if (in_array($st, ['running', 'paused', 'restarting'], true)): ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'kill_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-kill" title="<?php echo htmlspecialchars(t('docker_kill'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_kill') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('kill'); ?> <?php echo htmlspecialchars(t('docker_kill'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            <?php endif; ?>
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'remove_container', 'id' => $container['Id'], 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-remove" title="<?php echo htmlspecialchars(t('remove'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('remove') . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('trash'); ?> <?php echo htmlspecialchars(t('remove'), ENT_QUOTES, 'UTF-8'); ?></a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <?php echo htmlspecialchars(t('no_containers'), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="imagesSection" class="section" style="display: none;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <?php echo ico('layers'); ?>
                    <div class="section-title"><?php echo htmlspecialchars(t('images'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div class="table-container containers-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('repository'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('tag'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th>ID</th>
                            <th><?php echo htmlspecialchars(t('size'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('created'), ENT_QUOTES, 'UTF-8'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (is_array($images['data']) && count($images['data']) > 0): ?>
                            <?php foreach ($images['data'] as $image): ?>
                                <?php
                                $tag = (isset($image['RepoTags'][0]) && $image['RepoTags'][0] !== '') ? $image['RepoTags'][0] : '';
                                if ($tag === '<none>:<none>' || $tag === '') {
                                    $repo = '<none>';
                                    $itag = 'dangling';
                                } else {
                                    $parts = explode(':', $tag, 2);
                                    $repo = $parts[0];
                                    $itag = $parts[1] ?? 'latest';
                                }
                                $idShort = isset($image['Id']) ? (strpos($image['Id'], 'sha256:') === 0 ? substr($image['Id'], 7, 12) : substr($image['Id'], 0, 12)) : '';
                                $imageRef = dockerImageRefFromImage($image);
                                $imageLabel = $tag !== '' && $tag !== '<none>:<none>' ? $tag : $idShort;
                                $imageSearch = strtolower(trim($repo . ' ' . $itag . ' ' . $idShort . ' ' . $imageRef));
                                ?>
                                <tr class="c-row" tabindex="0" data-search="<?php echo htmlspecialchars($imageSearch, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td>
                                        <span class="c-name-wrap"><?php echo ico('chevron'); ?><strong><?php echo htmlspecialchars($repo); ?></strong></span>
                                    </td>
                                    <td><span class="tag-pill"><?php echo htmlspecialchars($itag); ?></span></td>
                                    <td style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($idShort); ?></td>
                                    <td><?php echo round(($image['Size'] ?? 0) / 1024 / 1024, 2); ?> MB</td>
                                    <td><?php echo date('d.m.Y', (int)($image['Created'] ?? 0)); ?></td>
                                </tr>
                                <tr class="c-actions" style="display:none">
                                    <td colspan="5">
                                        <div class="c-panel">
                                            <div class="c-panel-hint"><?php echo htmlspecialchars(t('docker_resource_panel_hint'), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="c-quick">
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['view' => 'image_inspect', 'id' => $imageRef, 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_image_inspect') . ' · ' . $imageLabel, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('layers'); ?> <?php echo htmlspecialchars(t('inspect'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            </div>
                                            <div class="action-buttons">
                                                <?php if ($tag !== '' && $tag !== '<none>:<none>'): ?>
                                                    <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'pull_named_image', 'image' => $tag, 'id' => null, 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-pull" title="<?php echo htmlspecialchars(t('pull'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('pull') . ' ' . $tag . '?', ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('download'); ?> <?php echo htmlspecialchars(t('pull'), ENT_QUOTES, 'UTF-8'); ?></a>
                                                <?php endif; ?>
                                                <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'remove_image', 'id' => $imageRef, 'image' => null, 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-remove" title="<?php echo htmlspecialchars(t('remove'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_confirm_remove_image'), ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('trash'); ?> <?php echo htmlspecialchars(t('remove'), ENT_QUOTES, 'UTF-8'); ?></a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px;">
                                    <?php echo htmlspecialchars(t('no_images'), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="networksSection" class="section" style="display: none;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <?php echo ico('network'); ?>
                    <div class="section-title"><?php echo htmlspecialchars(t('networks'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div class="table-container containers-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('name'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('driver'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('scope'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th>ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $nets = ($networksList['code'] === 200 && is_array($networksList['data'])) ? $networksList['data'] : [];
                        if (is_array($nets) && count($nets) > 0):
                            foreach ($nets as $nw):
                                if (!is_array($nw)) {
                                    continue;
                                }
                                $nid = isset($nw['Id']) ? substr($nw['Id'], 0, 12) : '';
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($nw['Name'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($nw['Driver'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($nw['Scope'] ?? ''); ?></td>
                                <td style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($nid); ?></td>
                            </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px;"><?php echo htmlspecialchars(t('no_data'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)($networksList['code'] ?? 0); ?>)</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="volumesSection" class="section" style="display: none;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <?php echo ico('volume'); ?>
                    <div class="section-title"><?php echo htmlspecialchars(t('volumes'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div class="table-container containers-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('name'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('driver'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?php echo htmlspecialchars(t('mountpoint'), ENT_QUOTES, 'UTF-8'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $vols = ($volumesList['code'] === 200 && is_array($volumesList['data']) && isset($volumesList['data']['Volumes'])) ? $volumesList['data']['Volumes'] : [];
                        if (is_array($vols) && count($vols) > 0):
                            foreach ($vols as $vo):
                                if (!is_array($vo)) {
                                    continue;
                                }
                                $volumeName = (string)($vo['Name'] ?? '');
                                $volumeSearch = strtolower(trim($volumeName . ' ' . ($vo['Driver'] ?? '') . ' ' . ($vo['Mountpoint'] ?? '')));
                                $isVolumeUsed = isset($usedVolumesMap[$volumeName]);
                        ?>
                            <tr class="c-row" tabindex="0" data-search="<?php echo htmlspecialchars($volumeSearch, ENT_QUOTES, 'UTF-8'); ?>">
                                <td><span class="c-name-wrap"><?php echo ico('chevron'); ?><strong><?php echo htmlspecialchars($volumeName); ?></strong><?php if (!$isVolumeUsed): ?><span class="tag-pill" style="margin-left:8px;background:var(--danger-bg);color:var(--danger);">unused</span><?php endif; ?></span></td>
                                <td><?php echo htmlspecialchars($vo['Driver'] ?? ''); ?></td>
                                <td style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($vo['Mountpoint'] ?? ''); ?></td>
                            </tr>
                            <tr class="c-actions" style="display:none">
                                <td colspan="3">
                                    <div class="c-panel">
                                        <div class="c-panel-hint"><?php echo htmlspecialchars(t('docker_resource_panel_hint'), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="c-quick">
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['view' => 'volume_inspect', 'id' => $volumeName, 'action' => null, 'embed' => '1']), ENT_QUOTES, 'UTF-8'); ?>" onclick="openDockerModal(event, this.href, <?php echo htmlspecialchars(json_encode(t('docker_volume_inspect') . ' · ' . $volumeName, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo ico('volume'); ?> <?php echo htmlspecialchars(t('inspect'), ENT_QUOTES, 'UTF-8'); ?></a>
                                        </div>
                                        <div class="action-buttons">
                                            <a href="<?php echo htmlspecialchars(dockerUrl(['action' => 'remove_volume', 'id' => $volumeName, 'view' => null]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-remove" title="<?php echo htmlspecialchars(t('remove'), ENT_QUOTES, 'UTF-8'); ?>" data-confirm="<?php echo htmlspecialchars(t('docker_confirm_remove_volume'), ENT_QUOTES, 'UTF-8'); ?>" onclick="confirmDockerAction(event, this)"><?php echo ico('trash'); ?> <?php echo htmlspecialchars(t('remove'), ENT_QUOTES, 'UTF-8'); ?></a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 40px;"><?php echo htmlspecialchars(t('no_data'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)($volumesList['code'] ?? 0); ?>)</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="infoSection" class="section" style="display: none;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <?php echo ico('info'); ?>
                    <div class="section-title"><?php echo htmlspecialchars(t('system_info'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div style="padding: 4px 4px 8px;">
                <h3><?php echo htmlspecialchars(t('docker'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="info-grid">
                    <div><strong><?php echo htmlspecialchars(t('docker_version'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo ($dockerStatus['code'] === 200 && is_array($dockerStatus['data'])) ? htmlspecialchars($dockerStatus['data']['Version'] ?? '—') : ('HTTP ' . (int)($dockerStatus['code'] ?? 0)); ?></div>
                    <div><strong><?php echo htmlspecialchars(t('instance_id'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo ($dockerStatus['code'] === 200 && is_array($dockerStatus['data'])) ? htmlspecialchars(substr((string)($dockerStatus['data']['InstanceID'] ?? ''), 0, 36)) : '—'; ?></div>
                </div>
                <h3 style="margin-top: 22px;"><?php echo ico('layers'); ?> <?php echo htmlspecialchars(t('docker_engine'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="info-grid">
                    <div><strong><?php echo htmlspecialchars(t('docker_version_info'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? htmlspecialchars($stats['data']['ServerVersion'] ?? 'N/A') : 'N/A'; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('docker_api'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo ($dockerVersionInfo['code'] === 200 && is_array($dockerVersionInfo['data'])) ? htmlspecialchars($dockerVersionInfo['data']['ApiVersion'] ?? '—') : ('HTTP ' . (int)($dockerVersionInfo['code'] ?? 0)); ?></div>
                    <div><strong><?php echo htmlspecialchars(t('os_arch'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo ($dockerVersionInfo['code'] === 200 && is_array($dockerVersionInfo['data'])) ? htmlspecialchars(($dockerVersionInfo['data']['Os'] ?? '') . ' / ' . ($dockerVersionInfo['data']['Arch'] ?? '')) : '—'; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('containers_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['Containers'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('running_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['ContainersRunning'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('stopped_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['ContainersStopped'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('images_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['Images'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('volumes_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['Volumes'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('cpu_cores'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo is_array($stats['data'] ?? null) ? (int)($stats['data']['NCPU'] ?? 0) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('ram_total'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (is_array($stats['data'] ?? null) && isset($stats['data']['MemTotal'])) ? round($stats['data']['MemTotal'] / 1024 / 1024 / 1024, 2) : 0; ?> GB</div>
                </div>
                <h3 style="margin-top: 22px;"><?php echo htmlspecialchars(t('disk_usage'), ENT_QUOTES, 'UTF-8'); ?> (system df)</h3>
                <?php if ($systemDfData['code'] === 200 && is_array($systemDfData['data'])): ?>
                <?php $sdf = $systemDfData['data']; ?>
                <div class="info-grid">
                    <div><strong><?php echo htmlspecialchars(t('layers_size'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo isset($sdf['LayersSize']) ? round($sdf['LayersSize'] / 1024 / 1024 / 1024, 2) : 0; ?> GB</div>
                    <div><strong><?php echo htmlspecialchars(t('images_in_report'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo isset($sdf['Images']) && is_array($sdf['Images']) ? count($sdf['Images']) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('containers_in_report'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo isset($sdf['Containers']) && is_array($sdf['Containers']) ? count($sdf['Containers']) : 0; ?></div>
                    <div><strong><?php echo htmlspecialchars(t('volumes_in_report'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo isset($sdf['Volumes']) && is_array($sdf['Volumes']) ? count($sdf['Volumes']) : 0; ?></div>
                </div>
                <?php else: ?>
                <p style="color: var(--muted); font-size: 14px;"><?php echo htmlspecialchars(t('unavailable'), ENT_QUOTES, 'UTF-8'); ?> (HTTP <?php echo (int)($systemDfData['code'] ?? 0); ?>)</p>
                <?php endif; ?>
                <?php if (isset($systemInfo['data']['URL'])): ?>
                <h3 style="margin-top: 22px;"><?php echo htmlspecialchars(t('endpoint'), ENT_QUOTES, 'UTF-8'); ?> #<?php echo (int)$epId; ?></h3>
                <div class="info-grid">
                    <div><strong><?php echo htmlspecialchars(t('endpoint_name'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($systemInfo['data']['Name'] ?? ''); ?></div>
                    <div><strong>URL:</strong> <?php echo htmlspecialchars($systemInfo['data']['URL'] ?? 'N/A'); ?></div>
                    <div><strong><?php echo htmlspecialchars(t('endpoint_status'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo isset($systemInfo['data']['Status']) ? (int)$systemInfo['data']['Status'] : 'N/A'; ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="docker-modal" id="noticeModal" aria-hidden="true" onclick="closeNoticeModalOnBackdrop(event)">
        <div class="message-modal-card" role="dialog" aria-modal="true" aria-labelledby="noticeModalTitle">
            <div class="message-modal-body">
                <div class="message-modal-icon" id="noticeModalIcon"><?php echo ico('info'); ?></div>
                <div class="message-modal-content">
                    <h3 id="noticeModalTitle"></h3>
                    <p id="noticeModalText"></p>
                </div>
            </div>
            <div class="message-modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeNoticeModal()"><?php echo htmlspecialchars(t('docker_modal_ok'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>

    <div class="docker-modal" id="confirmModal" aria-hidden="true" onclick="closeConfirmModalOnBackdrop(event)">
        <div class="message-modal-card" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
            <div class="message-modal-body">
                <div class="message-modal-icon warn"><?php echo ico('info'); ?></div>
                <div class="message-modal-content">
                    <h3 id="confirmModalTitle"><?php echo htmlspecialchars(t('docker_modal_confirm_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p id="confirmModalText"></p>
                </div>
            </div>
            <div class="message-modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeConfirmModal()"><?php echo htmlspecialchars(t('docker_modal_cancel'), ENT_QUOTES, 'UTF-8'); ?></button>
                <button type="button" class="btn btn-update" onclick="submitConfirmedAction()"><?php echo htmlspecialchars(t('docker_modal_confirm'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>

    <div class="docker-modal" id="dockerModal" aria-hidden="true" onclick="closeDockerModalOnBackdrop(event)">
        <div class="docker-modal-card" role="dialog" aria-modal="true" aria-labelledby="dockerModalTitle">
            <div class="docker-modal-head">
                <div class="docker-modal-title" id="dockerModalTitle"></div>
                <button type="button" class="docker-modal-close" onclick="closeDockerModal()" aria-label="Close">&times;</button>
            </div>
            <iframe class="docker-modal-frame" id="dockerModalFrame" title="Docker details"></iframe>
        </div>
    </div>
    
    <script>
        const sectionTitles = <?php echo json_encode(['stacks' => 'Stacks', 'containers' => t('containers'), 'networks' => t('networks'), 'volumes' => t('volumes'), 'images' => t('images'), 'info' => t('system')], JSON_UNESCAPED_UNICODE); ?>;
        const startupModals = <?php echo json_encode($startupModals, JSON_UNESCAPED_UNICODE); ?>;
        const modalIcons = <?php echo json_encode(['success' => ico('check'), 'error' => ico('x'), 'info' => ico('info')], JSON_UNESCAPED_UNICODE); ?>;
        const modalLabels = <?php echo json_encode(['noticeTitle' => t('docker_modal_notice_title'), 'confirmTitle' => t('docker_modal_confirm_title')], JSON_UNESCAPED_UNICODE); ?>;
        let noticeQueue = [];
        let pendingActionHref = '';
        let liveBusy = false;
        let monitorContainerId = '';
        let monitorTimer = null;
        const chartHistory = {};
        function toBytesText(v){
            const n = Number(v||0);
            if(!Number.isFinite(n)||n<=0)return '0 B';
            const u=['B','KB','MB','GB','TB']; let x=n; let i=0;
            while(x>=1024&&i<u.length-1){x/=1024;i++;}
            return `${x.toFixed(i===0?0:2)} ${u[i]}`;
        }
        function pushMetric(name,val){
            if(!chartHistory[name])chartHistory[name]=[];
            chartHistory[name].push(Number(val)||0);
            if(chartHistory[name].length>36)chartHistory[name].shift();
        }
        function drawChart(canvasId, keys, scaleLabelId, unit){
            const c=document.getElementById(canvasId); if(!c)return 0;
            const dpr=window.devicePixelRatio||1;
            const cw=c.clientWidth||300; const ch=c.clientHeight||86;
            c.width=Math.floor(cw*dpr); c.height=Math.floor(ch*dpr);
            const ctx=c.getContext('2d'); ctx.setTransform(dpr,0,0,dpr,0,0);
            const w=cw; const h=ch; const p={l:30,r:8,t:8,b:18};
            const pw=w-p.l-p.r; const ph=h-p.t-p.b;
            ctx.clearRect(0,0,w,h);
            ctx.fillStyle='rgba(0,0,0,0.08)'; ctx.fillRect(p.l,p.t,pw,ph);
            ctx.strokeStyle='rgba(255,255,255,0.09)'; ctx.lineWidth=1;
            for(let i=0;i<=4;i++){const y=p.t+(ph*i/4);ctx.beginPath();ctx.moveTo(p.l,y);ctx.lineTo(p.l+pw,y);ctx.stroke();}
            let max=1;
            keys.forEach(key=>{const arr=chartHistory[key]||[];if(arr.length){max=Math.max(max,...arr);}});
            const colors=['#22d3ee','#f59e0b','#4ade80'];
            keys.forEach((key,idx)=>{
                const arr=chartHistory[key]||[]; if(arr.length<2)return;
                ctx.strokeStyle=colors[idx]||'#22d3ee'; ctx.lineWidth=2; ctx.beginPath();
                arr.forEach((v,i)=>{const x=p.l+(i*(pw/(arr.length-1))); const y=p.t+ph-((v/max)*ph); if(i===0)ctx.moveTo(x,y); else ctx.lineTo(x,y);});
                ctx.stroke();
            });
            ctx.fillStyle='rgba(148,163,184,0.8)'; ctx.font='10px sans-serif';
            ctx.fillText('0',6,p.t+ph+2);
            const topLabel=unit==='bytes'?toBytesText(max):(unit==='percent'?`${max.toFixed(0)}%`:Math.round(max).toString());
            ctx.fillText(topLabel,4,p.t+8);
            const sl=document.getElementById(scaleLabelId);
            if(sl)sl.textContent=unit==='bytes'?`max ${toBytesText(max)}`:(unit==='percent'?`max ${max.toFixed(1)}%`:`max ${Math.round(max)}`);
            return max;
        }
        function updateLiveUi(stats){
            if(!stats)return;
            const set=(id,val)=>{const el=document.getElementById(id);if(el)el.textContent=val;};
            set('mCpu', `${Number(stats.cpu_total||0).toFixed(1)}%`);
            set('mMem', `${toBytesText(stats.mem_used)} / ${toBytesText(stats.mem_limit)}`);
            set('mNet', `${toBytesText(stats.net_rx)} / ${toBytesText(stats.net_tx)}`);
            set('mIo', `${toBytesText(stats.blk_read)} / ${toBytesText(stats.blk_write)}`);
            set('mPids', String(stats.pids || 0));
            set('mContainers', monitorContainerId ? monitorContainerId.slice(0,12) : 'n/a');
            pushMetric('cpu',stats.cpu_total); pushMetric('mem',stats.mem_used); pushMetric('rx',stats.net_rx); pushMetric('tx',stats.net_tx); pushMetric('br',stats.blk_read); pushMetric('bw',stats.blk_write); pushMetric('pids',stats.pids);
            if(monitorContainerId)try{localStorage.setItem('docker_chart_'+monitorContainerId,JSON.stringify(chartHistory));}catch(e){}
            drawChart('chartCpu',['cpu'],'sCpu','percent');
            drawChart('chartMem',['mem'],'sMem','bytes');
            drawChart('chartNet',['rx','tx'],'sNet','bytes');
            drawChart('chartIo',['br','bw'],'sIo','bytes');
            drawChart('chartPids',['pids'],'sPids','count');
            drawChart('chartContainers',['cpu','pids'],'sContainer','count');
        }
        async function refreshLive(){
            if(document.hidden||liveBusy||!monitorContainerId)return;
            liveBusy=true;
            try{
                const u=new URL(window.location.href);
                u.searchParams.set('view','stats_data');
                u.searchParams.set('id',monitorContainerId);
                u.searchParams.set('_ts',Date.now().toString());
                const r=await fetch(u.toString(),{credentials:'same-origin'});
                const d=await r.json();
                if(d&&d.ok&&d.stats)updateLiveUi(d.stats);
            }catch(e){}
            liveBusy=false;
        }
        function startContainerMonitor(containerId, containerName){
            if(!containerId)return;
            monitorContainerId=containerId;
            Object.keys(chartHistory).forEach(k=>{chartHistory[k]=[];});
            const sec=document.getElementById('chartsSection');
            if(sec)sec.style.display='block';
            const mt=document.getElementById('monitorTitle');
            if(mt)mt.textContent=`Live monitoring: ${containerName||containerId.slice(0,12)}`;
            var saved=localStorage.getItem('docker_chart_'+containerId);
            if(saved){try{var p=JSON.parse(saved);if(p&&typeof p==='object'){Object.keys(p).forEach(function(k){if(Array.isArray(p[k]))chartHistory[k]=p[k];});}}catch(e){}}
            if(Object.keys(chartHistory).some(function(k){return chartHistory[k].length>0;})){
                drawChart('chartCpu',['cpu'],'sCpu','percent');
                drawChart('chartMem',['mem'],'sMem','bytes');
                drawChart('chartNet',['rx','tx'],'sNet','bytes');
                drawChart('chartIo',['br','bw'],'sIo','bytes');
                drawChart('chartPids',['pids'],'sPids','count');
                drawChart('chartContainers',['cpu','pids'],'sContainer','count');
            }
            refreshLive();
            if(monitorTimer)clearInterval(monitorTimer);
            monitorTimer=setInterval(refreshLive,5000);
        }
        function applyContainerFilter(stackName){
            const cf=document.getElementById('containerFilter');
            if(cf)cf.value=stackName||'';
            const q=(stackName||'').trim().toLowerCase();
            document.querySelectorAll('#containersSection .c-row').forEach(row=>{
                const s=(row.getAttribute('data-search')||'').toLowerCase();
                const ok=!q||s.indexOf(q)!==-1;
                row.style.display=ok?'':'none';
                const det=row.nextElementSibling;
                if(det&&det.classList.contains('c-actions')&&!ok){det.style.display='none';row.classList.remove('expanded');}
            });
        }
        function showSection(section, el) {
            ['stacks','containers','networks','volumes','images','info'].forEach(s => {
                const n = document.getElementById(s + 'Section');
                if (n) n.style.display = 'none';
            });
            const sec = document.getElementById(section + 'Section');
            if (sec) sec.style.display = 'block';
            document.getElementById('pageTitle').innerText = sectionTitles[section] || section;
            document.querySelectorAll('.nav-item').forEach(item => { item.classList.remove('active'); });
            if (el) el.classList.add('active');
            const fb = document.getElementById('containerFilterBar');
            if (fb) fb.style.display = (section === 'containers' || section === 'stacks') ? 'flex' : 'none';
            const charts = document.getElementById('chartsSection');
            if (charts && section !== 'containers') charts.style.display = 'none';
            if (charts && section === 'containers' && monitorContainerId) charts.style.display = 'block';
        }
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarBackdrop').classList.toggle('show');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarBackdrop').classList.remove('show');
        }
        function openDockerModal(e, href, title) {
            e.preventDefault();
            e.stopPropagation();
            const modal = document.getElementById('dockerModal');
            const frame = document.getElementById('dockerModalFrame');
            document.getElementById('dockerModalTitle').textContent = title || '';
            frame.src = href;
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }
        function closeDockerModal() {
            const modal = document.getElementById('dockerModal');
            const frame = document.getElementById('dockerModalFrame');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            frame.src = 'about:blank';
        }
        function closeDockerModalOnBackdrop(e) {
            if (e.target && e.target.id === 'dockerModal') closeDockerModal();
        }
        function setModalIcon(target, type) {
            target.classList.remove('success', 'error', 'warn');
            if (type === 'success' || type === 'error' || type === 'warn') target.classList.add(type);
            target.innerHTML = modalIcons[type] || modalIcons.info || '';
        }
        function openNoticeModal(notice) {
            const modal = document.getElementById('noticeModal');
            const icon = document.getElementById('noticeModalIcon');
            const type = notice.type || 'info';
            setModalIcon(icon, type);
            document.getElementById('noticeModalTitle').textContent = notice.title || modalLabels.noticeTitle || '';
            document.getElementById('noticeModalText').textContent = notice.message || '';
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }
        function closeNoticeModal() {
            const modal = document.getElementById('noticeModal');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            if (noticeQueue.length > 0) openNoticeModal(noticeQueue.shift());
        }
        function closeNoticeModalOnBackdrop(e) {
            if (e.target && e.target.id === 'noticeModal') closeNoticeModal();
        }
        function confirmDockerAction(e, link) {
            e.preventDefault();
            e.stopPropagation();
            pendingActionHref = link.href;
            document.getElementById('confirmModalTitle').textContent = modalLabels.confirmTitle || '';
            document.getElementById('confirmModalText').textContent = link.getAttribute('data-confirm') || '';
            const modal = document.getElementById('confirmModal');
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }
        function closeConfirmModal() {
            pendingActionHref = '';
            const modal = document.getElementById('confirmModal');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }
        function closeConfirmModalOnBackdrop(e) {
            if (e.target && e.target.id === 'confirmModal') closeConfirmModal();
        }
        function submitConfirmedAction() {
            const href = pendingActionHref;
            closeConfirmModal();
            if (href) window.location.href = href;
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDockerModal();
                closeConfirmModal();
                closeNoticeModal();
            }
        });
        document.querySelectorAll('.containers-table-wrap').forEach(wrap => {
            const syncPanelOffset = () => {
                wrap.style.setProperty('--table-scroll-left', `${wrap.scrollLeft}px`);
            };
            wrap.addEventListener('scroll', syncPanelOffset, { passive: true });
            syncPanelOffset();
        });
        document.querySelectorAll('.c-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('a')) return;
                const open = !this.classList.contains('expanded');
                document.querySelectorAll('.c-row').forEach(r => { r.classList.remove('expanded'); });
                document.querySelectorAll('.c-actions').forEach(a => { a.style.display = 'none'; });
                if (open) {
                    this.classList.add('expanded');
                    const next = this.nextElementSibling;
                    if (next && next.classList.contains('c-actions')) next.style.display = 'table-row';
                    if (this.closest('#containersSection')) {
                        startContainerMonitor(this.getAttribute('data-container-id') || '', this.getAttribute('data-container-name') || '');
                    }
                }
            });
            row.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
            });
        });
        const cf = document.getElementById('containerFilter');
        if (cf) cf.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('#containersSection .c-row, #imagesSection .c-row, #volumesSection .c-row, #stacksSection .c-row').forEach(row => {
                const s = row.getAttribute('data-search') || '';
                const ok = !q || s.indexOf(q) !== -1;
                row.style.display = ok ? '' : 'none';
                const det = row.nextElementSibling;
                if (det && det.classList.contains('c-actions')) {
                    if (!ok) { det.style.display = 'none'; row.classList.remove('expanded'); }
                }
            });
        });
        if (startupModals.length > 0) {
            noticeQueue = startupModals.slice(1);
            openNoticeModal(startupModals[0]);
        }
        showSection('stacks', document.querySelector('.sidebar .nav-item.active'));
        setTimeout(function(){
            document.querySelectorAll('#stacksSection .c-row').forEach(function(r){
                var cells=r.querySelectorAll('td');
                if(cells.length>=2&&cells[1].textContent.trim()==='1'){r.click();}
            });
        },100);
    </script>
</body>
</html>
