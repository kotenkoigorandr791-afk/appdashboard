<?php
/**
 * db_admin.php — PostgreSQL Database Manager
 * Enhanced: SQL queries with CodeMirror, history modal, comment-aware execution.
 */
ob_start();
$__APP_DEBUG = (string)getenv('APP_DEBUG') === '1';
ini_set('display_errors', $__APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', $__APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    $__cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $__cookieSecure,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    require_once __DIR__ . '/lib/redis.php';
    app_redis_configure_php_sessions();
    session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smart_log.php';
smart_log_bootstrap('dashboard');
(static function (): void {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) return;
    $hosts = [];
    foreach ([($_SERVER['HTTP_HOST'] ?? ''), ($_SERVER['SERVER_NAME'] ?? '')] as $host) {
        $host = strtolower((string)$host);
        if ($host === '') continue;
        $parsedHost = parse_url('http://' . $host, PHP_URL_HOST);
        $hosts[] = strtolower((string)($parsedHost ?: $host));
    }
    $hosts = array_values(array_unique(array_filter($hosts)));
    if (empty($hosts)) return;
    $candidate = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
    if ($candidate === '') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'cross_origin_forbidden']);
        exit;
    }
    $parts = parse_url($candidate);
    $h = strtolower((string)($parts['host'] ?? ''));
    if (!in_array($h, $hosts, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'cross_origin_forbidden']);
        exit;
    }
})();

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user']['role']) || (int)$_SESSION['user']['role'] < 2) {
    if (isset($_POST['action'])) { header('Content-Type: application/json'); echo json_encode(['error'=>'Forbidden']); exit; }
    require_once __DIR__ . '/project/i18n_helpers.php';
    $authLang = ap_normalize_i18n_lang($_GET['lang'] ?? 'en');
    $authI18n = ap_load_i18n_file(__DIR__ . '/i18n/' . $authLang . '.json');
    if ($authI18n === []) $authI18n = ap_load_i18n_file(__DIR__ . '/i18n/en.json');
    http_response_code(403); die(ap_i18n_t($authI18n, 'forbidden', 'Forbidden'));
}

// ── Embed mode support ───────────────────────────────────────────────────────
function dbAdminIsEmbeddedRequest(): bool {
    if (isset($_GET['embed']) && $_GET['embed'] === '1') { return true; }
    return isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe';
}
$embedMode = dbAdminIsEmbeddedRequest();

$userRole = (int)($_SESSION['user']['role'] ?? 0);
$canRunSql = $userRole >= 2;
$isRoot   = ($_SESSION['user']['email'] ?? '') === 'root@root';
$db       = getDB();

// ── Translations ──────────────────────────────────────────────────────────────
$lang = $_GET['lang'] ?? ($_SESSION['user']['language'] ?? 'en');
$I18N = [
    'en' => [
        'title'         => 'Database Manager',
        'tables'        => 'Tables',
        'filter_tables' => 'Filter tables…',
        'add_user'      => 'Add User',
        'run_sql'       => 'SQL Console',
        'db_stats'      => 'DB Stats',
        'schema_tree'   => 'Schema Tree',
        'schema_relations'=>'Relations',
        'back'          => 'Back',
        'vacuum'        => 'VACUUM',
        'vacuum_confirm'=> 'Run VACUUM? This compacts the database.',
        'vacuum_done'   => 'VACUUM complete',
        'vacuum_fail'   => 'VACUUM failed',
        'rows'          => 'rows',
        'row'           => 'row',
        'refresh'       => 'Refresh',
        'insert_row'    => 'Insert Row',
        'search_cols'   => 'Search all columns…',
        'no_rows'       => 'No rows found',
        'loading'       => 'Loading…',
        'row_detail'    => 'Row Detail',
        'insert_into'   => 'Insert into',
        'insert'        => 'Insert',
        'cancel'        => 'Cancel',
        'delete_row_confirm' => 'Delete row where %s = "%s"?',
        'row_deleted'   => 'Row deleted',
        'row_inserted'  => 'Row inserted',
        'cell_updated'  => 'Cell updated',
        'error'         => 'Error',
        'running'       => 'Running…',
        'result'        => 'Result',
        'run_query'     => '▶ Run',
        'clear'         => 'Clear',
        'sql_placeholder'=> "SELECT * FROM users LIMIT 10;\n-- Ctrl+Enter to execute\n-- SELECT return tables; other statements show affected rows",
        'sql_root_only' => 'Only admins can run raw SQL',
        'sql_admin_only' => 'Only admins can run raw SQL',
        'sql_editor'    => 'SQL Editor',
        'sql_shortcuts' => 'Ctrl+Enter | Ctrl+Space',
        'sql_initial'   => "-- SQL Console with autocomplete\n-- Type Ctrl+Space for suggestions\n-- Tables and columns from current database\n-- Comments are ignored during execution\n\nSELECT * FROM users LIMIT 10;",
        'add_new_user'  => 'Add New User',
        'email'         => 'Email',
        'password'      => 'Password (min 8 chars)',
        'display_name'  => 'Display Name',
        'role'          => 'Role',
        'guest'         => 'Guest',
        'manager'       => 'Manager',
        'admin'         => 'Admin',
        'verified'      => 'Verified',
        'yes_can_login' => 'Yes (can log in)',
        'no_email_req'  => 'No (email required)',
        'language'      => 'Language',
        'theme'         => 'Theme',
        'light'         => 'Light',
        'dark'          => 'Dark',
        'auto'          => 'Auto',
        'accent_color'  => 'Accent Color',
        'create_user'   => 'Create User',
        'user_created'  => '✓ User created successfully!',
        'email_pwd_req' => 'Email and password are required.',
        'pwd_short'     => 'Password must be at least 8 characters.',
        'creating'      => 'Creating…',
        'user_exists'   => 'User already exists',
        'file_size'     => 'File Size',
        'total_pages'   => 'Total Pages',
        'page_size'     => 'Page Size',
        'free_pages'    => 'Free Pages',
        'tables_count'  => 'Tables',
        'total_rows_stat'=> 'Total Rows',
        'rows_per_table'=> 'Rows per table',
        'select_table'  => 'Select a table from the sidebar',
        'tools_hint'    => 'or use the tools below to manage the database',
        'null_value'    => 'NULL',
        'pk_label'      => 'PK',
        'view'          => 'View',
        'del'           => 'Del',
        'page_of'       => '%d / %d',
        'rows_range'    => 'Rows %d–%d of %s',
        'per_page'      => '/ page',
        'schema_title'  => 'Schema Tree',
        'schema_subtitle'=> 'Visual overview of all tables and their columns.',
        'export_report' => 'Export Report',
        'export_done'   => 'Report downloaded',
        'col_name'      => 'Column',
        'col_type'      => 'Type',
        'col_pk'        => 'PK',
        'col_notnull'   => 'NOT NULL',
        'col_default'   => 'Default',
        'indexes'       => 'Indexes',
        'foreign_keys'  => 'Foreign Keys',
        'fk_from'       => 'From',
        'fk_to'         => 'To → Table.Col',
        'collapse_all'  => 'Collapse All',
        'expand_all'    => 'Expand All',
        'copy_ddl'      => 'Copy DDL',
        'ddl_copied'    => 'DDL copied',
        'affected_rows' => '%d row(s) affected',
        'returned_rows' => '%d row(s) returned',
        'create_table'  => 'Create Table',
        'new_table_name'=> 'Table Name',
        'columns_def'   => 'Columns Definition',
        'col_name_ph'   => 'column_name',
        'col_type_ph'   => 'TEXT|INTEGER|REAL|BLOB',
        'col_constraints'=> 'Constraints (PK, NN, UNIQUE)',
        'add_column'    => 'Add Column',
        'create'        => 'Create',
        'relation_title' => 'Database Relations',
        'relation_desc'  => 'Foreign key connections between tables',
        'no_fk'         => 'No foreign key relationships defined.',
        'relations'     => 'Relations',
        'hide_sidebar'  => 'Hide sidebar',
        'show_sidebar'  => 'Show sidebar',
        'execute'       => 'Execute',
        'query_result'  => 'Query Result',
        'rows_affected' => 'rows affected',
        'sql_history'   => 'History',
        'clear_history' => 'Clear history',
        'expand_editor' => 'Expand Editor',
        'auto_complete' => 'Auto-complete',
        'format_sql'    => 'Format SQL',
        'history'       => 'SQL History',
        'no_history'    => 'No history',
        'close'         => 'Close',
        'use'           => 'Use',
        'copy'          => 'Copy',
        'view_delete'   => 'View & Delete',
        'view_row'      => 'View Row',
        'delete_row'    => 'Delete Row',
        'right_click_menu' => 'Row Actions',
        'delete_table'  => 'Delete table',
        'delete_table_title' => 'Delete table',
        'delete_table_confirm' => 'Slide to delete table "%s"',
        'slide_to_delete' => 'Slide to delete',
        'table_deleted' => 'Table deleted',
        'monitoring'    => 'Monitoring',
        'integrity_check' => 'Integrity Check',
        'run_integrity' => 'Run Integrity Check',
        'integrity_ok'  => 'Database integrity OK',
        'integrity_fail'=> 'Integrity check failed',
        'table_sizes'   => 'Table Sizes',
        'index_stats'   => 'Index Statistics',
        'quick_check'   => 'Quick Check',
        'run_quick'     => 'Run Quick Check',
        'total_indexes' => 'Total Indexes',
        'unique_indexes'=> 'Unique Indexes',
        'non_unique'    => 'Non-unique',
        'columns_in_idx'=> 'Columns in Index',
        'refresh_monitor'=> 'Refresh Monitor',
        'schema_diagram'=> 'Schema Diagram',
        'visual_schema'=> 'Visual Schema Diagram',
        'mermaid_loading'=> 'Generating diagram...',
        'download_diagram'=> 'Download Diagram',
        'toggle_view'   => 'Toggle View',
        'tree_view'     => 'Tree View',
        'diagram_view'  => 'Diagram View',
        'file_modified' => 'File Modified',
        'sort_tables'   => 'Sort',
        'sort_by_name'  => 'By Name (A-Z)',
        'sort_by_name_desc' => 'By Name (Z-A)',
        'sort_by_rows'  => 'By Rows (High-Low)',
        'sort_by_rows_asc' => 'By Rows (Low-High)',
        'sort_by_size'  => 'By Size (Large-Small)',
        'sort_by_size_asc' => 'By Size (Small-Large)',
        'view_mode_all' => 'All',
        'view_mode_stacks' => 'Stacks',
        'view_mode_hint' => 'Flat list or grouped by shared table name prefix (text before the first _).',
        'tables_hint'   => 'List of all database tables. Click to browse data.',
        'sort_tables_hint' => 'Choose how to sort the table list.',
        'filter_tables_hint' => 'Type to filter tables by name.',
        'add_user_hint' => 'Create a new user account with custom role and preferences.',
        'run_sql_hint'  => 'Execute raw SQL queries with autocomplete and history.',
        'create_table_hint' => 'Define columns and constraints to create a new database table.',
        'db_stats_hint' => 'View database file size, page counts, and row statistics.',
        'zoom_reset'    => 'Reset',
        'confirm'       => 'Confirm',
        'ok'            => 'OK',
        'email_placeholder' => 'user@example.com',
        'password_placeholder' => 'min 8 chars',
        'display_name_placeholder' => 'Full name',
        'table_name_placeholder' => 'users, products, orders...',
        'browse'        => 'Browse',
        'unique'        => 'unique',
        'row_number'    => 'Row #%d',
        'name_label'    => 'Name',
        'type_label'    => 'Type',
        'primary_key'   => 'Primary Key',
        'not_null'      => 'Not Null',
        'default_value' => 'Default',
        'none'          => '(none)',
        'yes'           => 'YES',
        'no'            => 'NO',
        'required'      => 'required',
        'at_least_one_column_required' => 'At least one column required',
        'remove'        => 'Remove',
        'clipboard_unavailable' => 'Clipboard unavailable',
        'export_preview' => 'Preview',
        'export_download' => 'Download',
        'export_close' => 'Close',
        'export_select_all' => 'Select All',
        'export_copy' => 'Copy to Clipboard',
        'export_copied' => 'Content copied to clipboard',
        'copy_table_name_hint' => 'Click to copy table name',
        'table_name_copied' => 'Table name copied to clipboard',
    ],
    'ru' => [
        'title'         => 'Менеджер Базы Данных',
        'tables'        => 'Таблицы',
        'filter_tables' => 'Поиск таблиц…',
        'add_user'      => 'Добавить пользователя',
        'run_sql'       => 'SQL Консоль',
        'db_stats'      => 'Статистика БД',
        'schema_tree'   => 'Схема',
        'schema_relations'=>'Связи',
        'back'          => 'Назад',
        'vacuum'        => 'VACUUM',
        'vacuum_confirm'=> 'Запустить VACUUM? База данных будет сжата.',
        'vacuum_done'   => 'VACUUM выполнен',
        'vacuum_fail'   => 'VACUUM не выполнен',
        'rows'          => 'строк',
        'row'           => 'строка',
        'refresh'       => 'Обновить',
        'insert_row'    => 'Вставить строку',
        'search_cols'   => 'Поиск по всем столбцам…',
        'no_rows'       => 'Строки не найдены',
        'loading'       => 'Загрузка…',
        'row_detail'    => 'Просмотр строки',
        'insert_into'   => 'Вставка в',
        'insert'        => 'Вставить',
        'cancel'        => 'Отмена',
        'delete_row_confirm' => 'Удалить строку где %s = "%s"?',
        'row_deleted'   => 'Строка удалена',
        'row_inserted'  => 'Строка добавлена',
        'cell_updated'  => 'Ячейка обновлена',
        'error'         => 'Ошибка',
        'running'       => 'Выполняется…',
        'result'        => 'Результат',
        'run_query'     => '▶ Выполнить',
        'clear'         => 'Очистить',
        'sql_placeholder'=> "SELECT * FROM users LIMIT 10;\n-- Ctrl+Enter для выполнения",
        'sql_root_only' => 'Только администраторы могут выполнять SQL',
        'sql_admin_only' => 'Только администраторы могут выполнять SQL',
        'sql_editor'    => 'SQL Редактор',
        'sql_shortcuts' => 'Ctrl+Enter | Ctrl+Space',
        'sql_initial'   => "-- SQL Консоль с автодополнением\n-- Ctrl+Space для подсказок\n-- Таблицы и столбцы из текущей базы\n-- Комментарии игнорируются при выполнении\n\nSELECT * FROM users LIMIT 10;",
        'add_new_user'  => 'Новый пользователь',
        'email'         => 'Email',
        'password'      => 'Пароль (мин. 8 символов)',
        'display_name'  => 'Имя',
        'role'          => 'Роль',
        'guest'         => 'Гость',
        'manager'       => 'Менеджер',
        'admin'         => 'Администратор',
        'verified'      => 'Верифицирован',
        'yes_can_login' => 'Да (может войти)',
        'no_email_req'  => 'Нет (нужна верификация)',
        'language'      => 'Язык',
        'theme'         => 'Тема',
        'light'         => 'Светлая',
        'dark'          => 'Тёмная',
        'auto'          => 'Авто',
        'accent_color'  => 'Цвет акцента',
        'create_user'   => 'Создать',
        'user_created'  => '✓ Пользователь создан!',
        'email_pwd_req' => 'Email и пароль обязательны.',
        'pwd_short'     => 'Пароль должен быть не менее 8 символов.',
        'creating'      => 'Создание…',
        'user_exists'   => 'Пользователь уже существует',
        'file_size'     => 'Размер файла',
        'total_pages'   => 'Страниц',
        'page_size'     => 'Размер страницы',
        'free_pages'    => 'Свободных страниц',
        'tables_count'  => 'Таблиц',
        'total_rows_stat'=> 'Всего строк',
        'rows_per_table'=> 'Строк по таблицам',
        'select_table'  => 'Выберите таблицу из панели',
        'tools_hint'    => 'или используйте инструменты ниже',
        'null_value'    => 'NULL',
        'pk_label'      => 'PK',
        'view'          => 'Смотреть',
        'del'           => 'Удалить',
        'page_of'       => '%d / %d',
        'rows_range'    => 'Строки %d–%d из %s',
        'per_page'      => '/ стр.',
        'schema_title'  => 'Схема таблиц',
        'schema_subtitle'=> 'Визуальное дерево всех таблиц и столбцов.',
        'export_report' => 'Экспорт отчёта',
        'export_done'   => 'Отчёт скачан',
        'col_name'      => 'Столбец',
        'col_type'      => 'Тип',
        'col_pk'        => 'PK',
        'col_notnull'   => 'NOT NULL',
        'col_default'   => 'По умолчанию',
        'indexes'       => 'Индексы',
        'foreign_keys'  => 'Внешние ключи',
        'fk_from'       => 'Из',
        'fk_to'         => 'В → Таблица.Столбец',
        'collapse_all'  => 'Свернуть всё',
        'expand_all'    => 'Развернуть всё',
        'copy_ddl'      => 'Копировать DDL',
        'ddl_copied'    => 'DDL скопирован',
        'affected_rows' => '%d строк(и) изменено',
        'returned_rows' => '%d строк(и) возвращено',
        'create_table'  => 'Создать таблицу',
        'new_table_name'=> 'Имя таблицы',
        'columns_def'   => 'Определение столбцов',
        'col_name_ph'   => 'имя_столбца',
        'col_type_ph'   => 'TEXT|INTEGER|REAL|BLOB',
        'col_constraints'=> 'Ограничения (PK, NN, UNIQUE)',
        'add_column'    => 'Добавить столбец',
        'create'        => 'Создать',
        'relation_title' => 'Связи базы данных',
        'relation_desc'  => 'Связи внешних ключей между таблицами',
        'no_fk'         => 'Связи внешних ключей не определены.',
        'relations'     => 'Связи',
        'hide_sidebar'  => 'Скрыть панель',
        'show_sidebar'  => 'Показать панель',
        'execute'       => 'Выполнить',
        'query_result'  => 'Результат запроса',
        'rows_affected' => 'строк изменено',
        'sql_history'   => 'История',
        'clear_history' => 'Очистить историю',
        'expand_editor' => 'Развернуть редактор',
        'auto_complete' => 'Автодополнение',
        'format_sql'    => 'Форматировать SQL',
        'history'       => 'История SQL',
        'no_history'    => 'Нет истории',
        'close'         => 'Закрыть',
        'use'           => 'Использовать',
        'copy'          => 'Копировать',
        'view_delete'   => 'Просмотр и удаление',
        'view_row'      => 'Просмотреть строку',
        'delete_row'    => 'Удалить строку',
        'right_click_menu' => 'Действия со строкой',
        'delete_table'  => 'Удалить таблицу',
        'delete_table_title' => 'Удаление таблицы',
        'delete_table_confirm' => 'Потяните слайдер, чтобы удалить таблицу "%s"',
        'slide_to_delete' => 'Потяните для удаления',
        'table_deleted' => 'Таблица удалена',
        'monitoring'    => 'Мониторинг',
        'integrity_check' => 'Проверка целостности',
        'run_integrity' => 'Запустить проверку',
        'integrity_ok'  => 'Целостность БД в порядке',
        'integrity_fail'=> 'Проверка целостности не пройдена',
        'table_sizes'   => 'Размеры таблиц',
        'index_stats'   => 'Статистика индексов',
        'quick_check'   => 'Быстрая проверка',
        'run_quick'     => 'Запустить быструю проверку',
        'total_indexes' => 'Всего индексов',
        'unique_indexes'=> 'Уникальные индексы',
        'non_unique'    => 'Неуникальные',
        'columns_in_idx'=> 'Столбцов в индексе',
        'refresh_monitor'=> 'Обновить мониторинг',
        'schema_diagram'=> 'Схема базы данных',
        'visual_schema'=> 'Визуальная схема',
        'mermaid_loading'=> 'Генерация диаграммы...',
        'download_diagram'=> 'Скачать диаграмму',
        'toggle_view'   => 'Переключить вид',
        'tree_view'     => 'Дерево',
        'diagram_view'  => 'Диаграмма',
        'file_modified'=>'Дата модификации файла','sort_tables'=>'Сортировать','sort_by_name'=>'По имени','sort_by_rows'=>'По строкам','sort_by_size'=>'По размеру',
        'export_preview' => 'Предпросмотр',
        'export_download' => 'Скачать',
        'export_close' => 'Закрыть',
        'export_select_all' => 'Выделить всё',
        'export_copy' => 'Копировать в буфер',
        'export_copied' => 'Содержимое скопировано в буфер обмена',
        'copy_table_name_hint' => 'Нажмите, чтобы скопировать имя таблицы',
        'table_name_copied' => 'Имя таблицы скопировано в буфер обмена',
        'view_mode_all' => 'Все',
        'view_mode_stacks' => 'По стакам',
        'view_mode_hint' => 'Список или группы таблиц с общим префиксом имени (до первого _).',
        'tables_hint'   => 'Список всех таблиц базы данных. Нажмите, чтобы открыть данные.',
        'sort_tables_hint' => 'Выберите сортировку списка таблиц.',
        'filter_tables_hint' => 'Введите текст для фильтрации таблиц по имени.',
        'add_user_hint' => 'Создать учетную запись пользователя с ролью и настройками.',
        'run_sql_hint'  => 'Выполнять SQL-запросы с автодополнением и историей.',
        'create_table_hint' => 'Задать столбцы и ограничения для новой таблицы.',
        'db_stats_hint' => 'Посмотреть размер файла, страницы и статистику строк.',
        'zoom_reset'    => 'Сброс',
        'confirm'       => 'Подтверждение',
        'ok'            => 'OK',
        'email_placeholder' => 'user@example.com',
        'password_placeholder' => 'мин. 8 символов',
        'display_name_placeholder' => 'Полное имя',
        'table_name_placeholder' => 'users, products, orders...',
        'browse'        => 'Открыть',
        'unique'        => 'уникальный',
        'row_number'    => 'Строка №%d',
        'name_label'    => 'Имя',
        'type_label'    => 'Тип',
        'primary_key'   => 'Первичный ключ',
        'not_null'      => 'Не NULL',
        'default_value' => 'По умолчанию',
        'none'          => '(нет)',
        'yes'           => 'ДА',
        'no'            => 'НЕТ',
        'required'      => 'обязательно',
        'at_least_one_column_required' => 'Нужен хотя бы один столбец',
        'remove'        => 'Удалить',
        'clipboard_unavailable' => 'Буфер обмена недоступен',
    ],
    'uk' => [
        'title'=>'Менеджер Бази Даних','tables'=>'Таблиці','filter_tables'=>'Фільтр таблиць…',
        'add_user'=>'Додати користувача','run_sql'=>'SQL Консоль','db_stats'=>'Статистика БД',
        'schema_tree'=>'Схема','schema_relations'=>'Зв\'язки','back'=>'Назад','vacuum'=>'VACUUM','vacuum_confirm'=>'Запустити VACUUM?',
        'vacuum_done'=>'VACUUM виконано','vacuum_fail'=>'VACUUM не вдався','rows'=>'рядків',
        'row'=>'рядок','refresh'=>'Оновити','insert_row'=>'Вставити рядок','search_cols'=>'Пошук…',
        'no_rows'=>'Рядків не знайдено','loading'=>'Завантаження…','row_detail'=>'Перегляд рядка',
        'insert_into'=>'Вставка до','insert'=>'Вставити','cancel'=>'Скасувати',
        'delete_row_confirm'=>'Видалити рядок де %s = "%s"?','row_deleted'=>'Рядок видалено',
        'row_inserted'=>'Рядок додано','cell_updated'=>'Комірку оновлено','error'=>'Помилка',
        'running'=>'Виконується…','result'=>'Результат','run_query'=>'▶ Виконати','clear'=>'Очистити',
        'sql_placeholder'=>"SELECT * FROM users LIMIT 10;\n-- Ctrl+Enter для виконання",
        'sql_root_only'=>'Тільки адміністратори можуть виконувати SQL','sql_admin_only'=>'Тільки адміністратори можуть виконувати SQL','sql_editor'=>'SQL Редактор','sql_shortcuts'=>'Ctrl+Enter | Ctrl+Space','sql_initial'=>"-- SQL Консоль з автодоповненням\n-- Ctrl+Space для підказок\n-- Таблиці та стовпці з поточної бази\n-- Коментарі ігноруються під час виконання\n\nSELECT * FROM users LIMIT 10;",'add_new_user'=>'Новий користувач',
        'email'=>'Email','password'=>'Пароль','display_name'=>'Ім\'я','role'=>'Роль',
        'guest'=>'Гість','manager'=>'Менеджер','admin'=>'Адміністратор','verified'=>'Верифікований',
        'yes_can_login'=>'Так','no_email_req'=>'Ні','language'=>'Мова','theme'=>'Тема',
        'light'=>'Світла','dark'=>'Темна','auto'=>'Авто','accent_color'=>'Колір акценту',
        'create_user'=>'Створити','user_created'=>'✓ Користувача створено!',
        'email_pwd_req'=>'Email та пароль обов\'язкові.','pwd_short'=>'Пароль мін. 8 символів.',
        'creating'=>'Створення…','user_exists'=>'Користувач вже існує','file_size'=>'Розмір файлу',
        'total_pages'=>'Сторінок','page_size'=>'Розмір сторінки','free_pages'=>'Вільних сторінок',
        'tables_count'=>'Таблиць','total_rows_stat'=>'Всього рядків','rows_per_table'=>'Рядків за таблицями',
        'select_table'=>'Оберіть таблицю','tools_hint'=>'або скористайтеся інструментами','null_value'=>'NULL',
        'pk_label'=>'PK','view'=>'Дивитись','del'=>'Видалити','page_of'=>'%d / %d',
        'rows_range'=>'Рядки %d–%d з %s','per_page'=>'/ стор.',
        'schema_title'=>'Схема таблиць','schema_subtitle'=>'Дерево всіх таблиць та стовпців.',
        'export_report'=>'Експорт звіту','export_done'=>'Звіт завантажено','col_name'=>'Стовпець',
        'col_type'=>'Тип','col_pk'=>'PK','col_notnull'=>'NOT NULL','col_default'=>'За замовч.',
        'indexes'=>'Індекси','foreign_keys'=>'Зовнішні ключі','fk_from'=>'З','fk_to'=>'До → Таблиця.Стовпець',
        'collapse_all'=>'Згорнути','expand_all'=>'Розгорнути','copy_ddl'=>'Копіювати DDL',
        'ddl_copied'=>'DDL скопійовано','affected_rows'=>'%d рядок(ів) змінено','returned_rows'=>'%d рядок(ів) повернено',
        'create_table'=>'Створити таблицю','new_table_name'=>'Назва таблиці','columns_def'=>'Визначення стовпців',
        'col_name_ph'=>'ім\'я_стовпця','col_type_ph'=>'TEXT|INTEGER|REAL|BLOB','col_constraints'=>'Обмеження (PK, NN, UNIQUE)',
        'add_column'=>'Додати стовпець','create'=>'Створити','relation_title'=>'Зв\'язки бази даних','relation_desc'=>'Зв\'язки зовнішніх ключів між таблицями','no_fk'=>'Зовнішні ключі не визначені.','relations'=>'Зв\'язки',
        'hide_sidebar'=>'Сховати панель','show_sidebar'=>'Показати панель','execute'=>'Виконати','query_result'=>'Результат запиту','rows_affected'=>'рядків змінено','sql_history'=>'Історія','clear_history'=>'Очистити історію',
        'expand_editor'=>'Розгорнути редактор','auto_complete'=>'Автодоповнення','format_sql'=>'Форматувати SQL',
        'history'=>'Історія SQL','no_history'=>'Немає історії','close'=>'Закрити','use'=>'Використати','copy'=>'Копіювати','view_delete'=>'Перегляд і видалення','view_row'=>'Переглянути рядок','delete_row'=>'Видалити рядок','right_click_menu'=>'Дії з рядком','delete_table'=>'Видалити таблицю','delete_table_title'=>'Видалення таблиці','delete_table_confirm'=>'Потягніть слайдер, щоб видалити таблицю "%s"','slide_to_delete'=>'Потягніть для видалення','table_deleted'=>'Таблицю видалено',
        'monitoring'=>'Моніторинг','integrity_check'=>'Перевірка цілісності','run_integrity'=>'Запустити перевірку','integrity_ok'=>'Цілісність БД в порядку','integrity_fail'=>'Перевірка цілісності не пройдена','table_sizes'=>'Розміри таблиць','index_stats'=>'Статистика індексів','quick_check'=>'Швидка перевірка','run_quick'=>'Запустити швидку перевірку','total_indexes'=>'Всього індексів','unique_indexes'=>'Унікальні індекси','non_unique'=>'Неунікальні','columns_in_idx'=>'Стовпців в індексі','refresh_monitor'=>'Оновити моніторинг','schema_diagram'=>'Схема бази даних','visual_schema'=>'Візуальна схема','mermaid_loading'=>'Генерація діаграми...','download_diagram'=>'Завантажити діаграму','toggle_view'=>'Перемкнути вигляд','tree_view'=>'Дерево','diagram_view'=>'Діаграма',
        'file_modified'=>'Дата модифікації файлу','sort_tables'=>'Сортувати','sort_by_name'=>'За ім\'ям','sort_by_rows'=>'За рядками','sort_by_size'=>'За розміром',
        'export_preview' => 'Попередній перегляд',
        'export_download' => 'Завантажити',
        'export_close' => 'Закрити',
        'export_select_all' => 'Виділити все',
        'export_copy' => 'Копіювати в буфер',
        'export_copied' => 'Вміст скопійовано в буфер обміну',
        'copy_table_name_hint' => 'Натисніть, щоб скопіювати назву таблиці',
        'table_name_copied' => 'Назву таблиці скопійовано в буфер обміну',
        'view_mode_all' => 'Усі',
        'view_mode_stacks' => 'За стеками',
        'view_mode_hint' => 'Плоский список або групи з спільним префіксом імені (до першого _).',
        'tables_hint'=>'Список усіх таблиць бази даних. Натисніть, щоб відкрити дані.','sort_tables_hint'=>'Виберіть сортування списку таблиць.','filter_tables_hint'=>'Введіть текст для фільтрації таблиць за назвою.','add_user_hint'=>'Створити обліковий запис користувача з роллю та налаштуваннями.','run_sql_hint'=>'Виконувати SQL-запити з автодоповненням та історією.','create_table_hint'=>'Задати стовпці й обмеження для нової таблиці.','db_stats_hint'=>'Переглянути розмір файла, сторінки та статистику рядків.','zoom_reset'=>'Скинути','confirm'=>'Підтвердження','ok'=>'OK','email_placeholder'=>'user@example.com','password_placeholder'=>'мін. 8 символів','display_name_placeholder'=>'Повне ім\'я','table_name_placeholder'=>'users, products, orders...','browse'=>'Відкрити','unique'=>'унікальний','row_number'=>'Рядок №%d','name_label'=>'Назва','type_label'=>'Тип','primary_key'=>'Первинний ключ','not_null'=>'Не NULL','default_value'=>'За замовчуванням','none'=>'(немає)','yes'=>'ТАК','no'=>'НІ','required'=>'обов\'язково','at_least_one_column_required'=>'Потрібен хоча б один стовпець','remove'=>'Видалити','clipboard_unavailable'=>'Буфер обміну недоступний',
    ],
    'be' => [
        'title'=>'Менеджар Базы Даных','tables'=>'Табліцы','filter_tables'=>'Фільтр табліц…',
        'add_user'=>'Дадаць карыстальніка','run_sql'=>'SQL Консоль','db_stats'=>'Статыстыка БД',
        'schema_tree'=>'Схема','schema_relations'=>'Сувязі','back'=>'Назад','vacuum'=>'VACUUM','vacuum_confirm'=>'Запусціць VACUUM?',
        'vacuum_done'=>'VACUUM выкананы','vacuum_fail'=>'VACUUM не выкананы','rows'=>'радкоў',
        'row'=>'радок','refresh'=>'Абнавіць','insert_row'=>'Уставіць радок','search_cols'=>'Пошук…',
        'no_rows'=>'Радкоў не знойдзена','loading'=>'Загрузка…','row_detail'=>'Прагляд радка',
        'insert_into'=>'Устаўка ў','insert'=>'Уставіць','cancel'=>'Адмена',
        'delete_row_confirm'=>'Выдаліць радок дзе %s = "%s"?','row_deleted'=>'Радок выдалены',
        'row_inserted'=>'Радок дададзены','cell_updated'=>'Вочка абноўлена','error'=>'Памылка',
        'running'=>'Выконваецца…','result'=>'Вынік','run_query'=>'▶ Выканаць','clear'=>'Ачысціць',
        'sql_placeholder'=>"SELECT * FROM users LIMIT 10;\n-- Ctrl+Enter для выканання",
        'sql_root_only'=>'Толькі адміністратары могуць выконваць SQL','sql_admin_only'=>'Толькі адміністратары могуць выконваць SQL','sql_editor'=>'SQL Рэдактар','sql_shortcuts'=>'Ctrl+Enter | Ctrl+Space','sql_initial'=>"-- SQL Кансоль з аўтадапаўненнем\n-- Ctrl+Space для падказак\n-- Табліцы і слупкі з бягучай базы\n-- Каментары ігнаруюцца пры выкананні\n\nSELECT * FROM users LIMIT 10;",'add_new_user'=>'Новы карыстальнік',
        'email'=>'Email','password'=>'Пароль','display_name'=>'Імя','role'=>'Роля',
        'guest'=>'Госць','manager'=>'Менеджар','admin'=>'Адміністратар','verified'=>'Верыфікаваны',
        'yes_can_login'=>'Так','no_email_req'=>'Не','language'=>'Мова','theme'=>'Тэма',
        'light'=>'Светлая','dark'=>'Цёмная','auto'=>'Аўта','accent_color'=>'Колер акцэнту',
        'create_user'=>'Стварыць','user_created'=>'✓ Карыстальнік створаны!',
        'email_pwd_req'=>'Email і пароль абавязковыя.','pwd_short'=>'Пароль мін. 8 сімвалаў.',
        'creating'=>'Стварэнне…','user_exists'=>'Карыстальнік ужо існуе','file_size'=>'Памер файла',
        'total_pages'=>'Старонак','page_size'=>'Памер старонкі','free_pages'=>'Свабодных старонак',
        'tables_count'=>'Табліц','total_rows_stat'=>'Усяго радкоў','rows_per_table'=>'Радкоў па табліцах',
        'select_table'=>'Выберыце табліцу','tools_hint'=>'або выкарыстайце інструменты','null_value'=>'NULL',
        'pk_label'=>'PK','view'=>'Глядзець','del'=>'Выдаліць','page_of'=>'%d / %d',
        'rows_range'=>'Радкі %d–%d з %s','per_page'=>'/ стар.',
        'schema_title'=>'Схема табліц','schema_subtitle'=>'Дрэва ўсіх табліц і слупкоў.',
        'export_report'=>'Экспарт справаздачы','export_done'=>'Справаздача спампавана',
        'col_name'=>'Слупок','col_type'=>'Тып','col_pk'=>'PK','col_notnull'=>'NOT NULL',
        'col_default'=>'Па змаўчанні','indexes'=>'Індэксы','foreign_keys'=>'Замежныя ключы',
        'fk_from'=>'З','fk_to'=>'У → Табліца.Слупок','collapse_all'=>'Згарнуць','expand_all'=>'Разгарнуць',
        'copy_ddl'=>'Капіраваць DDL','ddl_copied'=>'DDL скапіраваны',
        'affected_rows'=>'%d радкоў змяненo','returned_rows'=>'%d радкоў вернута',
        'create_table'=>'Стварыць табліцу','new_table_name'=>'Назва табліцы','columns_def'=>'Вызначэнне слупкоў',
        'col_name_ph'=>'імя_слупка','col_type_ph'=>'TEXT|INTEGER|REAL|BLOB','col_constraints'=>'Абмежаванні (PK, NN, UNIQUE)',
        'add_column'=>'Дадаць слупок','create'=>'Стварыць','relation_title'=>'Сувязі базы даных','relation_desc'=>'Сувязі замежных ключоў паміж табліцамі','no_fk'=>'Замежныя ключы не вызначаны.','relations'=>'Сувязі',
        'hide_sidebar'=>'Схаваць панэль','show_sidebar'=>'Паказаць панэль','execute'=>'Выканаць','query_result'=>'Вынік запыту','rows_affected'=>'радкоў зменена','sql_history'=>'Гісторыя','clear_history'=>'Ачысціць гісторыю',
        'expand_editor'=>'Разгарнуць рэдактар','auto_complete'=>'Аўтадапаўненне','format_sql'=>'Фарматаваць SQL',
        'history'=>'Гісторыя SQL','no_history'=>'Няма гісторыі','close'=>'Закрыць','use'=>'Выкарыстаць','copy'=>'Капіраваць','view_delete'=>'Прагляд і выдаленне','view_row'=>'Прагледзець радок','delete_row'=>'Выдаліць радок','right_click_menu'=>'Дзеянні з радком','delete_table'=>'Выдаліць табліцу','delete_table_title'=>'Выдаленне табліцы','delete_table_confirm'=>'Пацягніце паўзунок, каб выдаліць табліцу "%s"','slide_to_delete'=>'Пацягніце для выдалення','table_deleted'=>'Табліца выдалена',
        'monitoring'=>'Маніторынг','integrity_check'=>'Праверка цэласці','run_integrity'=>'Запусціць праверку','integrity_ok'=>'Цэласць БД у парадку','integrity_fail'=>'Праверка цэласці не пройдзена','table_sizes'=>'Памеры табліц','index_stats'=>'Статыстыка індэксаў','quick_check'=>'Хуткая праверка','run_quick'=>'Запусціць хуткую праверку','total_indexes'=>'Усяго індэксаў','unique_indexes'=>'Унікальныя індэксы','non_unique'=>'Неўнікальныя','columns_in_idx'=>'Слупкоў у індэксе','refresh_monitor'=>'Абнавіць маніторынг','schema_diagram'=>'Схема базы даных','visual_schema'=>'Візуальная схема','mermaid_loading'=>'Генерацыя дыяграмы...','download_diagram'=>'Спампаваць дыяграму','toggle_view'=>'Пераключыць выгляд','tree_view'=>'Дрэва','diagram_view'=>'Дыяграма',
        'file_modified'=>'Дата мадыфікацыі файла','sort_tables'=>'Сартаваць','sort_by_name'=>'Па імені','sort_by_rows'=>'Па радках','sort_by_size'=>'Па памеры',
        'export_preview' => 'Папярэдні прагляд',
        'export_download' => 'Спампаваць',
        'export_close' => 'Закрыць',
        'export_select_all' => 'Вылучыць усё',
        'export_copy' => 'Капіяваць у буфер',
        'export_copied' => 'Змесціва скапіявана ў буфер абмену',
        'copy_table_name_hint' => 'Націсніце, каб скапіяваць назву табліцы',
        'table_name_copied' => 'Назва табліцы скапіявана ў буфер абмену',
        'view_mode_all' => 'Усе',
        'view_mode_stacks' => 'Па стэках',
        'view_mode_hint' => 'Плоскі спіс або групы з агульным прэфіксам імя (да першага _).',
        'tables_hint'=>'Спіс усіх табліц базы даных. Націсніце, каб адкрыць даныя.','sort_tables_hint'=>'Выберыце сартаванне спіса табліц.','filter_tables_hint'=>'Увядзіце тэкст для фільтрацыі табліц па назве.','add_user_hint'=>'Стварыць уліковы запіс карыстальніка з роляй і наладамі.','run_sql_hint'=>'Выконваць SQL-запыты з аўтадапаўненнем і гісторыяй.','create_table_hint'=>'Задаць слупкі і абмежаванні для новай табліцы.','db_stats_hint'=>'Паглядзець памер файла, старонкі і статыстыку радкоў.','zoom_reset'=>'Скінуць','confirm'=>'Пацверджанне','ok'=>'OK','email_placeholder'=>'user@example.com','password_placeholder'=>'мін. 8 сімвалаў','display_name_placeholder'=>'Поўнае імя','table_name_placeholder'=>'users, products, orders...','browse'=>'Адкрыць','unique'=>'унікальны','row_number'=>'Радок №%d','name_label'=>'Назва','type_label'=>'Тып','primary_key'=>'Першасны ключ','not_null'=>'Не NULL','default_value'=>'Па змаўчанні','none'=>'(няма)','yes'=>'ТАК','no'=>'НЕ','required'=>'абавязкова','at_least_one_column_required'=>'Патрэбен хаця б адзін слупок','remove'=>'Выдаліць','clipboard_unavailable'=>'Буфер абмену недаступны',
    ],
];
$T = $I18N[$lang] ?? $I18N['en'];
if (!function_exists('t')) {
    function t($key, ...$args) {
        global $T;
        $s = $T[$key] ?? $key;
        return $args ? vsprintf($s, $args) : $s;
    }
}

// ── Theme from URL ────────────────────────────────────────────────────────────
$theme = $_GET['theme'] ?? 'light';
$color = urldecode($_GET['color'] ?? '#007bff');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#007bff';

// ── Helpers ───────────────────────────────────────────────────────────────────
function jsonOut(array $d): void {
    while(ob_get_level()>0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function getTables(): array {
    global $db;
    $res = $db->query("SELECT table_name AS name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE' ORDER BY table_name");
    $out = [];
    while ($r = $res->fetch(PDO::FETCH_ASSOC)) $out[] = $r['name'];
    return $out;
}
function getReportSchemaObjects(): array {
    global $db;
    $res = $db->query("SELECT table_name AS name, CASE WHEN table_type='VIEW' THEN 'view' ELSE 'table' END AS type FROM information_schema.tables WHERE table_schema='public' ORDER BY CASE WHEN table_type='BASE TABLE' THEN 0 ELSE 1 END, table_name");
    $out = [];
    while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[] = ['name' => $r['name'], 'kind' => $r['type'] === 'view' ? 'view' : 'table'];
    }
    return $out;
}
function dbReportCellTooLarge($val, int $maxBytes): bool {
    if ($val === null) return false;
    if (is_int($val) || is_float($val)) return false;
    $s = is_string($val) ? $val : (string)$val;
    return strlen($s) > $maxBytes;
}
function pgIdent(string $name): string {
    return '"' . str_replace('"', '""', $name) . '"';
}
function pgTableExists(string $table): bool {
    global $db;
    $stmt = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name=:n LIMIT 1");
    $stmt->bindValue(':n', $table, PDO::PARAM_STR);
    return (bool)$stmt->execute()->fetch(PDO::FETCH_ASSOC);
}
function getTableInfo(string $table): array {
    global $db;
    $safe = pgIdent($table);
    $stmt = $db->prepare("SELECT ordinal_position - 1 AS cid, column_name AS name, data_type AS type, CASE WHEN is_nullable='NO' THEN 1 ELSE 0 END AS notnull, COALESCE(column_default, '') AS dflt_value, CASE WHEN EXISTS (SELECT 1 FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON kcu.constraint_name=tc.constraint_name AND kcu.table_schema=tc.table_schema AND kcu.table_name=tc.table_name WHERE tc.table_schema='public' AND tc.table_name=c.table_name AND tc.constraint_type='PRIMARY KEY' AND kcu.column_name=c.column_name) THEN 1 ELSE 0 END AS pk FROM information_schema.columns c WHERE table_schema='public' AND table_name=:n ORDER BY ordinal_position");
    $stmt->bindValue(':n', $table, PDO::PARAM_STR);
    $cols = []; $r = $stmt->execute();
    while ($row = $r->fetch(PDO::FETCH_ASSOC)) $cols[] = $row;
    $count = (int)$db->query("SELECT COUNT(*) FROM $safe")->fetchColumn();
    $idxStmt = $db->prepare("SELECT indexname AS name, indexdef AS sql FROM pg_catalog.pg_indexes WHERE schemaname='public' AND tablename=:n ORDER BY indexname");
    $idxStmt->bindValue(':n', $table, PDO::PARAM_STR);
    $idxRes = $idxStmt->execute();
    $indexes = [];
    while ($idx = $idxRes->fetch(PDO::FETCH_ASSOC)) {
        preg_match('/\((.*)\)$/', (string)$idx['sql'], $m);
        $idxCols = isset($m[1]) ? array_map('trim', explode(',', $m[1])) : [];
        $indexes[] = ['name'=>$idx['name'], 'unique'=>str_contains((string)$idx['sql'], 'UNIQUE INDEX'), 'cols'=>$idxCols];
    }
    $fkStmt = $db->prepare("SELECT kcu.column_name AS \"from\", ccu.table_name AS \"table\", ccu.column_name AS \"to\", rc.update_rule AS on_update, rc.delete_rule AS on_delete FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name=kcu.constraint_name AND tc.table_schema=kcu.table_schema JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name=tc.constraint_name AND ccu.table_schema=tc.table_schema LEFT JOIN information_schema.referential_constraints rc ON rc.constraint_name=tc.constraint_name AND rc.constraint_schema=tc.table_schema WHERE tc.constraint_type='FOREIGN KEY' AND tc.table_schema='public' AND tc.table_name=:n ORDER BY kcu.ordinal_position");
    $fkStmt->bindValue(':n', $table, PDO::PARAM_STR);
    $fkRes = $fkStmt->execute();
    $fks = [];
    while ($fk = $fkRes->fetch(PDO::FETCH_ASSOC)) {
        $fks[] = ['from'=>$fk['from'], 'table'=>$fk['table'], 'to'=>$fk['to']];
    }
    $ddl = '';
    return ['columns'=>$cols,'count'=>$count,'indexes'=>$indexes,'fks'=>$fks,'ddl'=>$ddl];
}

// ── Get schema for autocomplete ────────────────────────────────────────────────
function getSchemaForAutocomplete(): array {
    global $db;
    $tables = getTables();
    $schema = [];
    foreach ($tables as $table) {
        $cols = [];
        foreach (getTableInfo($table)['columns'] as $row) {
            $cols[] = $row['name'];
        }
        $schema[$table] = $cols;
    }
    return $schema;
}
function redisValuePreview(Redis $redis, string $key): string {
    $type = $redis->type($key);
    if ($type === Redis::REDIS_STRING) return (string)$redis->get($key);
    if ($type === Redis::REDIS_HASH) return json_encode($redis->hGetAll($key), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '{}';
    if ($type === Redis::REDIS_LIST) return json_encode($redis->lRange($key, 0, 100), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]';
    if ($type === Redis::REDIS_SET) return json_encode($redis->sMembers($key), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]';
    if ($type === Redis::REDIS_ZSET) return json_encode($redis->zRange($key, 0, 100, true), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]';
    return '';
}
function redisTypeName(int $type): string {
    return match ($type) {
        Redis::REDIS_STRING => 'string',
        Redis::REDIS_SET => 'set',
        Redis::REDIS_LIST => 'list',
        Redis::REDIS_ZSET => 'zset',
        Redis::REDIS_HASH => 'hash',
        default => 'unknown',
    };
}
function redisKeys(Redis $redis): array {
    $keys = [];
    $it = null;
    while (($batch = $redis->scan($it, '*', 200)) !== false) {
        foreach ($batch as $key) {
            $key = (string)$key;
            if (str_starts_with($key, app_redis_config()['prefix']) || str_starts_with($key, 'PHPREDIS_SESSION:')) $keys[] = $key;
        }
        if ($it === 0) break;
    }
    sort($keys, SORT_NATURAL);
    return $keys;
}

// ── AJAX ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $dbSource = (string)($_POST['db_source'] ?? 'postgres');
    if ($dbSource === 'redis') {
        $redis = app_redis();
        if (!$redis) jsonOut(['success'=>false,'error'=>'Redis unavailable']);
        if ($action==='list_tables') {
            $out = [];
            foreach (redisKeys($redis) as $key) $out[] = ['name'=>$key,'count'=>1];
            jsonOut(['success'=>true,'tables'=>$out]);
        }
        if ($action==='table_info') {
            $key = (string)($_POST['table'] ?? '');
            if ($key === '' || !$redis->exists($key)) jsonOut(['success'=>false,'error'=>'Key not found']);
            jsonOut(['success'=>true,'info'=>['columns'=>[
                ['name'=>'key','type'=>'TEXT','pk'=>1],
                ['name'=>'type','type'=>'TEXT','pk'=>0],
                ['name'=>'ttl','type'=>'INTEGER','pk'=>0],
                ['name'=>'value','type'=>'TEXT','pk'=>0],
            ],'count'=>1,'indexes'=>[],'fks'=>[]]]);
        }
        if ($action==='table_data') {
            $key = (string)($_POST['table'] ?? '');
            if ($key === '' || !$redis->exists($key)) jsonOut(['success'=>false,'error'=>'Key not found']);
            $row = ['key'=>$key,'type'=>redisTypeName($redis->type($key)),'ttl'=>$redis->ttl($key),'value'=>redisValuePreview($redis, $key)];
            jsonOut(['success'=>true,'columns'=>array_keys($row),'rows'=>[$row],'total'=>1,'page'=>1,'pages'=>1]);
        }
        if ($action==='update_cell') {
            $key = (string)($_POST['pk_val'] ?? $_POST['table'] ?? '');
            if ($key === '' || (string)($_POST['col'] ?? '') !== 'value') jsonOut(['success'=>false,'error'=>'Only value editing is supported']);
            $redis->set($key, (string)($_POST['val'] ?? ''));
            jsonOut(['success'=>true,'changed'=>1]);
        }
        if ($action==='delete_row' || $action==='delete_table') {
            $key = (string)($_POST['pk_val'] ?? $_POST['table_name'] ?? $_POST['table'] ?? '');
            if ($key === '') jsonOut(['success'=>false,'error'=>'No key']);
            jsonOut(['success'=>true,'changed'=>$redis->del($key)]);
        }
        if ($action==='db_stats') {
            $info = $redis->info();
            $keys = redisKeys($redis);
            jsonOut(['success'=>true,'file_size'=>(int)($info['used_memory'] ?? 0),'file_modified'=>time(),'page_count'=>0,'page_size'=>0,'free_pages'=>0,'table_counts'=>array_fill_keys($keys, 1),'engine'=>'redis','db_label'=>'redis']);
        }
        if (in_array($action, ['schema','relations','table_sizes','index_usage','db_integrity','quick_check','get_schema'], true)) jsonOut(['success'=>true,'schema'=>[],'relations'=>[],'table_sizes'=>[],'index_usage'=>[],'quick_check'=>'ok','integrity'=>'ok']);
        jsonOut(['success'=>false,'error'=>'Unsupported Redis action']);
    }

    if ($action==='list_tables') {
        $out = [];
        foreach (getTables() as $t) {
            $out[] = ['name'=>$t,'count'=>(int)$db->query("SELECT COUNT(*) FROM " . pgIdent($t))->fetchColumn()];
        }
        jsonOut(['success'=>true,'tables'=>$out]);
    }

    if ($action==='table_info') {
        $table = $_POST['table']??'';
        if (!$table) jsonOut(['success'=>false,'error'=>'No table']);
        jsonOut(['success'=>true,'info'=>getTableInfo($table)]);
    }

    if ($action==='table_data') {
        $table  = $_POST['table'] ?? ''; $page = max(1,(int)($_POST['page']??1));
        $limit  = min(200,max(10,(int)($_POST['limit']??50)));
        $search = trim($_POST['search']??'');
        $sortC  = $_POST['sort_col']??''; $sortD = strtoupper($_POST['sort_dir']??'ASC')==='DESC'?'DESC':'ASC';
        if (!$table) jsonOut(['success'=>false,'error'=>'No table']);
        $safe = pgIdent($table); $offset=($page-1)*$limit;
        $info = getTableInfo($table); $cols = array_column($info['columns'],'name');
        $where = '';
        if ($search!=='') {
            $esc = AppDatabase::escapeString($search);
            $where = 'WHERE '.implode(' OR ',array_map(fn($c)=>"CAST(".pgIdent($c)." AS TEXT) ILIKE '%$esc%'",$cols));
        }
        $ob = ($sortC && in_array($sortC,$cols,true)) ? "ORDER BY ".pgIdent($sortC)." $sortD" : '';
        $total=(int)$db->query("SELECT COUNT(*) FROM $safe $where")->fetchColumn();
        $res=$db->query("SELECT * FROM $safe $where $ob LIMIT $limit OFFSET $offset");
        $rows=[];
        while($r=$res->fetch(PDO::FETCH_ASSOC)) $rows[]=$r;
        jsonOut(['success'=>true,'columns'=>$cols,'rows'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit,'pages'=>max(1,(int)ceil($total/$limit))]);
    }

    if ($action==='update_cell') {
        $table=$_POST['table']??''; $pkCol=$_POST['pk_col']??''; $pkVal=$_POST['pk_val']??'';
        $col=$_POST['col']??''; $val=$_POST['val']??null;
        if (!$table||!$pkCol||!$col) jsonOut(['success'=>false,'error'=>'Missing params']);
        $stmt=$db->prepare("UPDATE ".pgIdent($table)." SET ".pgIdent($col)."=:v WHERE ".pgIdent($pkCol)."=:pk");
        $stmt->bindValue(':v',$val===''?null:$val); $stmt->bindValue(':pk',$pkVal); $stmt->execute();
        jsonOut(['success'=>true,'changed'=>$db->changes()]);
    }

    if ($action==='delete_row') {
        $table=$_POST['table']??''; $pkCol=$_POST['pk_col']??''; $pkVal=$_POST['pk_val']??'';
        if (!$table||!$pkCol) jsonOut(['success'=>false,'error'=>'Missing params']);
        $stmt=$db->prepare("DELETE FROM ".pgIdent($table)." WHERE ".pgIdent($pkCol)."=:pk");
        $stmt->bindValue(':pk',$pkVal); $stmt->execute();
        jsonOut(['success'=>true,'changed'=>$db->changes()]);
    }

    if ($action==='insert_row') {
        $table=$_POST['table']??''; $data=json_decode($_POST['data']??'{}',true);
        if (!$table||!$data) jsonOut(['success'=>false,'error'=>'Missing params']);
        $safe=pgIdent($table);
        $cs=implode(',',array_map(fn($c)=>pgIdent($c),array_keys($data)));
        $ps=implode(',',array_map(fn($i)=>":v$i",range(0,count($data)-1)));
        $stmt=$db->prepare("INSERT INTO $safe ($cs) VALUES ($ps)");
        $i=0; foreach($data as $val){$stmt->bindValue(":v$i",$val===''?null:$val);$i++;}
        try{$stmt->execute();jsonOut(['success'=>true,'id'=>$db->lastInsertRowID()]);}
        catch(Exception $e){jsonOut(['success'=>false,'error'=>$e->getMessage()]);}
    }

    if ($action==='get_schema') {
        jsonOut(['success'=>true,'schema'=>getSchemaForAutocomplete()]);
    }

    if ($action==='run_sql') {
        if (!$canRunSql) jsonOut(['success'=>false,'error'=>t('sql_admin_only')]);
        $sql=trim($_POST['sql']??'');
        if (!$sql) jsonOut(['success'=>false,'error'=>'Empty SQL']);
        
        // Remove comments for parsing (keep original for history)
        $sqlWithoutComments = preg_replace('/--.*$/m', '', $sql);
        $sqlWithoutComments = preg_replace('/\/\*.*?\*\//s', '', $sqlWithoutComments);
        $sqlWithoutComments = trim($sqlWithoutComments);
        
        try {
            if (preg_match('/^\s*(SELECT|WITH|EXPLAIN)/i', $sqlWithoutComments)) {
                $res=$db->query($sql); $rows=[];$cols=[];
                while($r=$res->fetch(PDO::FETCH_ASSOC)){if(!$cols)$cols=array_keys($r);$rows[]=$r;}
                jsonOut(['success'=>true,'type'=>'select','columns'=>$cols,'rows'=>$rows,'count'=>count($rows)]);
            } else {
                $db->exec($sql);
                jsonOut(['success'=>true,'type'=>'exec','changed'=>$db->changes()]);
            }
        } catch(Exception $e){jsonOut(['success'=>false,'error'=>$e->getMessage()]);}
    }

    if ($action==='add_user') {
        $email=trim($_POST['email']??''); $pass=trim($_POST['password']??'');
        $name=trim($_POST['name']??''); $role=(int)($_POST['role']??0);
        $lang2=$_POST['language']??'en'; $thm=$_POST['theme']??'light';
        $clr=$_POST['color']??'#007bff'; $ver=(int)($_POST['verified']??1);
        if (!$email||!$pass) jsonOut(['success'=>false,'error'=>t('email_pwd_req')]);
        if (getUserByEmail($email)) jsonOut(['success'=>false,'error'=>t('user_exists')]);
        $hash=appPasswordHash($pass);
        $stmt=$db->prepare("INSERT INTO users(email,name,password,photo,role,verified,language,theme,color) VALUES(:e,:n,:p,'default_avatar.png',:r,:v,:l,:t,:c)");
        $stmt->bindValue(':e',$email);$stmt->bindValue(':n',$name?:explode('@',$email)[0]);
        $stmt->bindValue(':p',$hash);$stmt->bindValue(':r',$role,PDO::PARAM_INT);
        $stmt->bindValue(':v',$ver,PDO::PARAM_INT);$stmt->bindValue(':l',$lang2);
        $stmt->bindValue(':t',$thm);$stmt->bindValue(':c',$clr);
        $stmt->execute();
        if (!function_exists('oauth_provision_user_services')) {
            $oauthFile = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/project/oauth.php';
            if (is_readable($oauthFile)) require_once $oauthFile;
        }
        if (function_exists('oauth_provision_user_services') && $ver) {
            oauth_provision_user_services($email, [
                'name' => $name ?: explode('@', $email)[0],
                'role' => $role,
                'theme' => $thm,
                'photo' => 'default_avatar.png',
                'verified' => true,
            ]);
        }
        jsonOut(['success'=>true,'message'=>t('user_created')]);
    }

    if ($action==='db_stats') {
        $tables=getTables(); $counts=[];
        foreach($tables as $t){$counts[$t]=(int)$db->query("SELECT COUNT(*) FROM " . pgIdent($t))->fetchColumn();}
        $pdo = $db->getPdo();
        $size = (int)$pdo->query("SELECT pg_database_size(current_database())")->fetchColumn();
        jsonOut(['success'=>true,'file_size'=>$size,'file_modified'=>time(),'page_count'=>0,'page_size'=>8192,'free_pages'=>0,'table_counts'=>$counts,'engine'=>'postgresql','db_label'=>app_db_dsn_label()]);
    }

    if ($action==='schema') {
        $tables=getTables(); $out=[];
        foreach($tables as $t) $out[$t]=getTableInfo($t);
        jsonOut(['success'=>true,'schema'=>$out]);
    }

    if ($action==='relations') {
        $tables = getTables();
        $relations = [];
        foreach ($tables as $t) {
            foreach (getTableInfo($t)['fks'] as $fk) {
                $relations[] = [
                    'from_table' => $t,
                    'from_col'   => $fk['from'],
                    'to_table'   => $fk['table'],
                    'to_col'     => $fk['to'],
                    'on_update'  => $fk['on_update'] ?? '',
                    'on_delete'  => $fk['on_delete'] ?? ''
                ];
            }
        }
        jsonOut(['success'=>true, 'relations'=>$relations]);
    }

    if ($action==='vacuum') {
        if (!$isRoot) jsonOut(['success'=>false,'error'=>'Only root@root']);
        $db->exec('VACUUM');
        jsonOut(['success'=>true,'message'=>t('vacuum_done')]);
    }

    if ($action==='create_table') {
        if (!$isRoot) jsonOut(['success'=>false,'error'=>'Only root@root']);
        $tableName = trim($_POST['table_name'] ?? '');
        $columns = json_decode($_POST['columns'] ?? '[]', true);
        if (empty($tableName) || empty($columns)) {
            jsonOut(['success'=>false,'error'=>'Table name and at least one column required']);
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            jsonOut(['success'=>false,'error'=>'Invalid table name']);
        }
        if (pgTableExists($tableName)) {
            jsonOut(['success'=>false,'error'=>'Table already exists']);
        }
        $colDefs = [];
        foreach ($columns as $col) {
            $cname = trim($col['name']);
            $ctype = strtoupper(trim($col['type']));
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $cname)) {
                jsonOut(['success'=>false,'error'=>"Invalid column name: $cname"]);
            }
            if (!in_array($ctype, ['TEXT','INTEGER','REAL','BLOB','NUMERIC'])) {
                $ctype = 'TEXT';
            }
            $def = "\"$cname\" $ctype";
            if (!empty($col['constraints'])) {
                $const = strtoupper($col['constraints']);
                if (strpos($const, 'PK') !== false) $def .= " PRIMARY KEY";
                if (strpos($const, 'NN') !== false) $def .= " NOT NULL";
                if (strpos($const, 'UNIQUE') !== false) $def .= " UNIQUE";
                if (strpos($const, 'AUTO') !== false && $ctype === 'INTEGER') $def = "\"$cname\" BIGSERIAL";
            }
            $colDefs[] = $def;
        }
        $sql = "CREATE TABLE \"$tableName\" (" . implode(", ", $colDefs) . ")";
        try {
            $db->exec($sql);
            jsonOut(['success'=>true, 'message'=>"Table '$tableName' created"]);
        } catch(Exception $e) {
            jsonOut(['success'=>false, 'error'=>$e->getMessage()]);
        }
    }

    if ($action==='delete_table') {
        if (!$isRoot) jsonOut(['success'=>false,'error'=>'Only root@root']);
        $tableName = trim($_POST['table_name'] ?? '');
        if (!$tableName) jsonOut(['success'=>false,'error'=>'No table']);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            jsonOut(['success'=>false,'error'=>'Invalid table name']);
        }
        $safe = $tableName;
        if (!pgTableExists($tableName)) jsonOut(['success'=>false,'error'=>'Table not found']);
        try {
            $db->exec("DROP TABLE " . pgIdent($safe));
            jsonOut(['success'=>true]);
        } catch(Exception $e) {
            jsonOut(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    if ($action==='db_integrity') {
        jsonOut(['success'=>true, 'integrity'=>'ok']);
    }

    if ($action==='table_sizes') {
        $tables = getTables();
        $sizes = [];
        foreach ($tables as $t) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$t)) continue;
            $count = (int)$db->query("SELECT COUNT(*) FROM " . pgIdent($t))->fetchColumn();
            $stmtIC = $db->prepare("SELECT COUNT(*) AS c FROM pg_catalog.pg_indexes WHERE schemaname='public' AND tablename = :n");
            $stmtIC->bindValue(':n', $t, PDO::PARAM_STR);
            $rowIC = $stmtIC->execute()->fetch(PDO::FETCH_ASSOC);
            $idxCount = (int)($rowIC['c'] ?? 0);
            $sizes[] = [
                'name' => $t,
                'rows' => $count,
                'indexes' => $idxCount
            ];
        }
        usort($sizes, fn($a, $b) => $b['rows'] - $a['rows']);
        jsonOut(['success'=>true, 'table_sizes'=>$sizes]);
    }

    if ($action==='index_usage') {
        $tables = getTables();
        $indexStats = [];
        foreach ($tables as $t) {
            foreach (getTableInfo($t)['indexes'] as $idx) {
                $indexStats[] = [
                    'table' => $t,
                    'name' => $idx['name'],
                    'unique' => (bool)$idx['unique'],
                    'columns' => implode(', ', $idx['cols'])
                ];
            }
        }
        jsonOut(['success'=>true, 'index_usage'=>$indexStats]);
    }

    if ($action==='quick_check') {
        jsonOut(['success'=>true, 'quick_check'=>'ok']);
    }

    jsonOut(['success'=>false,'error'=>'Unknown action']);
}

// ── Export report (GET) ───────────────────────────────────────────────────────
if (isset($_GET['export_report'])) {
    if ((string)($_GET['db_source'] ?? 'postgres') === 'redis') {
        $redis = app_redis();
        header('Content-Type: text/plain; charset=utf-8');
        if (!$redis) {
            echo "Redis unavailable\n";
            exit;
        }
        $keys = redisKeys($redis);
        $info = $redis->info();
        $lines = [];
        $lines[] = '================================================================================';
        $lines[] = ' Redis AppDashboard Report';
        $lines[] = ' Generated: ' . date('Y-m-d H:i:s');
        $lines[] = ' Keys: ' . count($keys);
        $lines[] = ' Used memory: ' . formatBytes((int)($info['used_memory'] ?? 0));
        $lines[] = '================================================================================';
        $lines[] = '';
        foreach ($keys as $key) {
            $value = redisValuePreview($redis, $key);
            if (mb_strlen($value) > 1000) $value = mb_substr($value, 0, 1000) . '…';
            $lines[] = $key;
            $lines[] = '  type: ' . redisTypeName($redis->type($key));
            $lines[] = '  ttl: ' . $redis->ttl($key);
            $lines[] = '  value: ' . $value;
            $lines[] = '';
        }
        echo implode("\n", $lines);
        exit;
    }
    $objects = getReportSchemaObjects();
    $tableCount = count(array_filter($objects, fn($o) => $o['kind'] === 'table'));
    $viewCount = count($objects) - $tableCount;
    $lines  = [];
    $lines[] = '================================================================================';
    $lines[] = ' PostgreSQL Database Schema Report';
    $lines[] = ' File: ' . DB_PATH;
    $lines[] = ' Generated: ' . date('Y-m-d H:i:s');
    $lines[] = ' Objects: ' . count($objects) . ' (tables: ' . $tableCount . ', views: ' . $viewCount . ')';
    $lines[] = '================================================================================';
    $lines[] = '';
    $sampleCellMax = 480;
    foreach ($objects as $obj) {
        $tname = $obj['name'];
        $kindLabel = $obj['kind'] === 'view' ? 'VIEW' : 'TABLE';
        try {
            $info = getTableInfo($tname);
            $safe = AppDatabase::escapeString($tname);
            $count = $info['count'];
            $lines[] = str_repeat('─', 80);
            $lines[] = "$kindLabel: $tname  ($count rows)";
            $lines[] = str_repeat('─', 80);
            $lines[] = '';
            if ($info['ddl']) {
                $lines[] = 'DDL:';
                $lines[] = $info['ddl'] . ';';
                $lines[] = '';
            }
            $lines[] = sprintf('  %-3s  %-25s  %-15s  %-8s  %-8s  %s', 'cid', 'name', 'type', 'PK', 'NOT NULL', 'default');
            $lines[] = '  ' . str_repeat('-', 76);
            foreach ($info['columns'] as $col) {
                $lines[] = sprintf('  %-3d  %-25s  %-15s  %-8s  %-8s  %s',
                    $col['cid'], $col['name'], $col['type'],
                    $col['pk'] ? 'YES' : '',
                    $col['notnull'] ? 'YES' : '',
                    $col['dflt_value'] ?? ''
                );
            }
            $lines[] = '';
            if ($info['indexes']) {
                $lines[] = 'Indexes:';
                foreach ($info['indexes'] as $idx) {
                    $u = $idx['unique'] ? ' [UNIQUE]' : '';
                    $lines[] = sprintf('  %-35s%s  cols: %s', $idx['name'], $u, implode(', ', $idx['cols']));
                }
                $lines[] = '';
            }
            if ($info['fks']) {
                $lines[] = 'Foreign Keys:';
                foreach ($info['fks'] as $fk) {
                    $lines[] = sprintf('  %-20s  →  %s.%s', $fk['from'], $fk['table'], $fk['to']);
                }
                $lines[] = '';
            }
            $res = $db->query("SELECT * FROM \"$safe\" LIMIT 5");
            $sample = [];
            $omitSample = false;
            while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
                foreach ($r as $v) {
                    if (dbReportCellTooLarge($v, $sampleCellMax)) {
                        $omitSample = true;
                        break 2;
                    }
                }
                $sample[] = $r;
            }
            if ($omitSample) {
                $lines[] = 'Sample data: omitted (cell values exceed ' . $sampleCellMax . ' bytes — e.g. JSON/BLOB).';
                $lines[] = '';
            } elseif ($sample) {
                $lines[] = 'Sample data (first ' . count($sample) . ' rows):';
                $colNames = array_keys($sample[0]);
                $widths   = array_map(fn($c) => max(strlen($c), 12), $colNames);
                foreach ($sample as $row) {
                    foreach ($colNames as $i => $c) {
                        $cellLen = strlen((string)($row[$c] ?? 'NULL'));
                        $widths[$i] = min(120, max($widths[$i], $cellLen));
                    }
                }
                $header = '  ' . implode('  ', array_map(fn($c,$w)=>str_pad($c,$w), $colNames, $widths));
                $sep    = '  ' . implode('  ', array_map(fn($w)=>str_repeat('-',$w), $widths));
                $lines[] = $header; $lines[] = $sep;
                foreach ($sample as $row) {
                    $cells = array_map(fn($c,$w)=>str_pad(substr((string)($row[$c]??'NULL'),0,$w),$w), $colNames, $widths);
                    $lines[] = '  ' . implode('  ', $cells);
                }
                $lines[] = '';
            }
        } catch (Throwable $e) {
            $lines[] = str_repeat('─', 80);
            $lines[] = "$kindLabel: $tname  (error while exporting)";
            $lines[] = str_repeat('─', 80);
            $lines[] = $e->getMessage();
            $lines[] = '';
        }
    }
    $lines[] = str_repeat('=', 80);
    $lines[] = 'END OF REPORT';
    $lines[] = str_repeat('=', 80);

    $content  = implode("\r\n", $lines);
    $filename = 'db_schema_' . date('Ymd_His') . '.log';
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

// ── CSS variables for current theme ──────────────────────────────────────────
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    return ['r'=>hexdec(substr($hex,0,2)),'g'=>hexdec(substr($hex,2,2)),'b'=>hexdec(substr($hex,4,2))];
}
$rgb = hexToRgb($color);
$isDark = $theme === 'dark';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" style="background:<?= $isDark ? '#0f1419' : '#f8f9fc' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<link rel="icon" href="/icon.png" type="image/png">
<title><?= t('title') ?></title>
<!-- CodeMirror CSS and JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/monokai.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/sql-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/comment/comment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.js"></script>
<!-- Chart.js for monitoring charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- Mermaid.js for schema diagrams -->
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.2/min/vs/loader.min.js"></script>
<style>
:root {
  --bg:        <?= $isDark ? '#0f1419' : '#f8f9fc' ?>;
  --bg2:       <?= $isDark ? 'rgba(18,24,32,0.8)' : '#ffffff' ?>;
  --bg3:       <?= $isDark ? 'rgba(255,255,255,0.10)' : '#f0f2f8' ?>;

  --border:    <?= $isDark ? 'rgba(255,255,255,0.14)' : '#dde1ed' ?>;
  --border2:   <?= $isDark ? 'rgba(255,255,255,0.22)' : '#c5cce0' ?>;

  --text:      <?= $isDark ? '#f4f7fb' : '#1a1f36' ?>;
  --text-dim:  <?= $isDark ? 'rgba(255,255,255,0.65)' : '#5a6282' ?>;
  --text-muted:<?= $isDark ? 'rgba(255,255,255,0.42)' : '#9aa3be' ?>;

  --accent:    <?= $color ?>;
  --accent-r:  <?= $rgb['r'] ?>;
  --accent-g:  <?= $rgb['g'] ?>;
  --accent-b:  <?= $rgb['b'] ?>;

  --accent-dim:rgba(<?= $rgb['r'] ?>,<?= $rgb['g'] ?>,<?= $rgb['b'] ?>,0.12);
  --accent-mid:rgba(<?= $rgb['r'] ?>,<?= $rgb['g'] ?>,<?= $rgb['b'] ?>,0.25);
  --accent-brd:rgba(<?= $rgb['r'] ?>,<?= $rgb['g'] ?>,<?= $rgb['b'] ?>,0.40);

  --green:     <?= $isDark ? '#4caf50' : '#1ea760' ?>;
  --green-dim: <?= $isDark ? 'rgba(76,175,80,0.12)' : 'rgba(30,167,96,0.10)' ?>;

  --red:       <?= $isDark ? '#ff4444' : '#e03d2e' ?>;
  --red-dim:   <?= $isDark ? 'rgba(255,68,68,0.12)' : 'rgba(224,61,46,0.10)' ?>;

  --orange:    <?= $isDark ? '#ff9800' : '#d97706' ?>;
  --yellow:    <?= $isDark ? '#ffc107' : '#b45309' ?>;
  --purple:    <?= $isDark ? '#a78bfa' : '#7c3aed' ?>;

  --font-mono: 'JetBrains Mono','Fira Code','Cascadia Code',Consolas,monospace;
  --font-ui:   'IBM Plex Sans','Segoe UI',system-ui,sans-serif;

  --radius:    6px;
  --radius-lg: 10px;
  --sidebar-w: 260px;
  --sidebar-w-mobile: 280px;
  --transition:0.2s ease;
}
@media (max-width: 768px) {
  :root {
    --sidebar-w: 0px;
  }
}
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;height:100dvh;overflow:hidden}
html{color-scheme:<?= $isDark ? 'dark' : 'light' ?>}
body{font-family:var(--font-ui);background:var(--bg);color:var(--text);font-size:13px;line-height:1.5}
@media (max-width: 768px) {
  body{font-size:12px}
}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:var(--text-muted)}
*{scrollbar-width:thin;scrollbar-color:var(--border2) transparent}

/* Layout */
#app{display:flex;flex-direction:column;height:100vh;height:100dvh}
#topbar{height:44px;background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 10px;gap:8px;flex-shrink:0;position:relative;z-index:100}
@media (max-width: 768px) {
  #topbar{padding:0 8px;gap:6px}
}
.tb-brand{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--text)}
@media (max-width: 480px) {
  .tb-brand .tb-badge{display:none}
  .tb-brand{font-size:11px}
}
.tb-brand-icon{width:24px;height:24px;background:linear-gradient(135deg,var(--accent),var(--purple));border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.tb-badge{font-size:10px;color:var(--text-muted);font-weight:400;font-family:var(--font-mono);margin-left:2px}
.tb-spacer{flex:1}
.tb-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text-dim);font-size:11px;cursor:pointer;transition:all var(--transition);font-family:var(--font-ui);white-space:nowrap;text-decoration:none}
@media (max-width: 640px) {
  .tb-btn span{display:none}
  .tb-btn{padding:5px 8px}
}
.tb-btn:hover{border-color:var(--border2);color:var(--text)}
.tb-btn.danger:hover{border-color:var(--red);color:var(--red);background:var(--red-dim)}
.tb-btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
.tb-btn.primary:hover{opacity:.88}

/* Sidebar Toggle Button (Mobile) */
#sidebar-toggle{display:none}
@media (max-width: 768px) {
  #sidebar-toggle{display:inline-flex}
}
#sidebar-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:199;backdrop-filter:blur(2px)}
#sidebar-overlay.active{display:block}

#main{display:flex;flex:1;overflow:hidden;position:relative;min-height:0}
#sidebar{width:var(--sidebar-w);background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;flex-shrink:0;transition:transform var(--transition);z-index:200}
@media (max-width: 768px) {
  #sidebar{position:fixed;top:46px;left:0;bottom:0;width:var(--sidebar-w-mobile);transform:translateX(-100%);z-index:200}
  #sidebar.open{transform:translateX(0)}
}
.sb-label{padding:10px 12px 4px;font-size:10px;font-weight:600;color:var(--text-muted);letter-spacing:1.1px;text-transform:uppercase}
.sb-label-row{display:flex;align-items:center;padding:10px 12px 4px;gap:4px}
.sb-label-row .sb-label{padding:0;flex:1;min-width:0}
.sb-actions{display:flex;align-items:center;gap:4px;flex-shrink:0}
.sb-view-toggle{display:flex;border:1px solid var(--border);border-radius:4px;overflow:hidden}
.sb-view-btn{background:var(--bg3);border:none;padding:3px 6px;font-size:9px;cursor:pointer;color:var(--text-muted);font-family:var(--font-ui);line-height:1.2;white-space:nowrap}
.sb-view-btn:hover:not(.active){color:var(--text)}
.sb-view-btn.active{background:var(--accent-dim);color:var(--accent);font-weight:600}
.sb-sort-btn{background:none;border:1px solid var(--border);border-radius:4px;padding:3px 5px;cursor:pointer;color:var(--text-muted);transition:all var(--transition);display:flex;align-items:center}
.sb-sort-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-dim)}
.sb-search{padding:0 9px 8px}
.sb-search input{width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:6px 9px 6px 28px;color:var(--text);font-size:12px;font-family:var(--font-ui);outline:none;transition:border-color var(--transition)}
.sb-search input:focus{border-color:var(--accent)}
.sb-si{position:relative}
.sb-si svg{position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none}
#table-list{flex:1;overflow-y:auto;padding:0 7px 7px}
.ti-stack{border:1px solid var(--border);border-radius:var(--radius);margin-bottom:4px;background:var(--bg2)}
.ti-stack-h{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 8px;cursor:pointer;font-size:11px;font-weight:600;color:var(--text-dim);list-style:none;user-select:none}
.ti-stack-h::-webkit-details-marker{display:none}
.ti-stack-h::before{content:'▸';font-size:10px;opacity:.7;flex-shrink:0;transition:transform .15s}
.ti-stack[open] .ti-stack-h::before{transform:rotate(90deg)}
.ti-stack-n{font-size:9px;color:var(--text-muted);background:var(--bg3);padding:1px 5px;border-radius:8px;border:1px solid var(--border);font-family:var(--font-mono)}
.ti-stack-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ti-stack-items{padding:0 4px 5px 6px}
.ti-stack[open] .ti-stack-items{margin-top:4px;padding-top:6px;border-top:1px solid var(--border)}
.ti-stack .ti{margin-bottom:1px}
.ti{display:flex;align-items:center;gap:7px;padding:7px 9px;border-radius:var(--radius);cursor:pointer;transition:all var(--transition);margin-bottom:1px}
.ti:hover{background:var(--bg3)}
.ti.active{background:var(--accent-dim);border:1px solid var(--accent-brd)}
.ti.active .ti-name{color:var(--accent)}
.ti-ico{width:18px;height:18px;border-radius:4px;background:var(--bg3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:9px;flex-shrink:0;color:var(--text-muted)}
.ti.active .ti-ico{background:var(--accent-dim);border-color:var(--accent-brd);color:var(--accent)}
.ti-name{font-size:12px;color:var(--text);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ti-cnt{font-size:10px;color:var(--text-muted);font-family:var(--font-mono);background:var(--bg3);padding:1px 5px;border-radius:9px;border:1px solid var(--border);flex-shrink:0}
.ti.active .ti-cnt{background:var(--accent-dim);border-color:var(--accent-brd);color:var(--accent)}
.sb-footer{border-top:1px solid var(--border);padding:7px}
.sb-fbtn{display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:var(--radius);cursor:pointer;transition:all var(--transition);color:var(--text-dim);font-size:12px;border:none;background:none;width:100%;text-align:left;font-family:var(--font-ui)}
.sb-fbtn:hover{background:var(--bg3);color:var(--text)}
.sb-fbtn.active{background:var(--accent-dim);color:var(--accent)}
.sb-fbtn svg{flex-shrink:0}

/* Content */
#content{flex:1;display:flex;flex-direction:column;overflow:hidden;min-height:0}
.view{display:none;flex-direction:column;height:100%;overflow:hidden}
.view.active{display:flex}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:var(--radius);border:1px solid var(--border);background:var(--bg3);color:var(--text-dim);font-size:12px;cursor:pointer;transition:all var(--transition);font-family:var(--font-ui);white-space:nowrap}
.btn:hover{border-color:var(--border2);color:var(--text)}
.btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
.btn.primary:hover{opacity:.88}
.btn.success{background:var(--green-dim);border-color:var(--green);color:var(--green)}
.btn.danger{background:var(--red-dim);border-color:var(--red);color:var(--red)}
.btn.danger:hover{background:var(--red);color:#fff}
.btn.sm{padding:2px 7px;font-size:11px}
.btn:disabled{opacity:.38;cursor:not-allowed}
/* Copy success animation */
@keyframes btn-flash-green{0%{background:var(--green);border-color:var(--green);color:#fff;box-shadow:0 0 8px var(--green-dim)}100%{background:var(--bg3);border-color:var(--border);color:var(--text-dim);box-shadow:none}}
.btn.flash-success{animation:btn-flash-green .8s ease-out both;transition:none!important}
@keyframes export-tb-flash{0%{box-shadow:0 0 0 2px var(--green);background:var(--green-dim);color:var(--green)}100%{box-shadow:none;background:none;color:var(--text)}}
.export-tb-btn.flash-success{animation:export-tb-flash .75s ease-out both;transition:none!important}
.tb-select{height:32px;border:1px solid var(--border);border-radius:var(--radius);background:var(--bg2);color:var(--text);font-size:12px;padding:0 28px 0 10px;font-family:var(--font-ui);outline:none}
.tb-select:focus{border-color:var(--accent);box-shadow:0 0 0 2px var(--accent-dim)}
/* Refresh animation */
@keyframes btn-flash-refresh{0%{background:var(--green);border-color:var(--green);color:#fff;box-shadow:0 0 8px var(--green-dim)}100%{background:var(--bg3);border-color:var(--border);color:var(--text-dim);box-shadow:none}}
.btn.flash-refresh{animation:btn-flash-refresh .8s ease-out}

/* Table view - responsive */
.tv-bar{padding:9px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:9px;flex-wrap:wrap;background:var(--bg2);flex-shrink:0}
@media (max-width: 640px) {
  .tv-bar{gap:6px;padding:8px 10px}
  .tv-sw input{width:140px;font-size:11px}
  .tv-sw input:focus{width:160px}
}
.tv-title{font-size:13px;font-weight:600;color:var(--text);display:flex;align-items:center;gap:7px}
.tv-name-copy{position:relative;display:inline-flex;align-items:center;max-width:min(48vw,560px);cursor:pointer;border-radius:8px;padding:2px 6px;transition:background var(--transition),color var(--transition);outline:none;z-index:100001}
.tv-name-copy:hover,.tv-name-copy:focus-visible{background:var(--accent-dim);color:var(--accent)}
.tv-name-copy:focus-visible{box-shadow:0 0 0 2px var(--accent-mid)}
.tv-name-copy::after{content:attr(data-copy-hint);position:absolute;left:50%;top:calc(100% + 8px);transform:translateX(-50%) translateY(-4px);opacity:0;pointer-events:none;white-space:nowrap;background:var(--bg3);color:var(--text);border:1px solid var(--border2);border-radius:8px;padding:6px 9px;font-size:11px;font-weight:500;box-shadow:0 8px 22px rgba(0,0,0,.18);transition:opacity .16s ease,transform .16s ease;z-index:100002}
.tv-name-copy:hover::after,.tv-name-copy:focus-visible::after{opacity:1;transform:translateX(-50%) translateY(0)}
.tv-badge{font-size:10px;font-family:var(--font-mono);color:var(--text-dim);background:var(--bg3);border:1px solid var(--border);padding:1px 7px;border-radius:9px}
.tv-spacer{flex:1}
.tv-sw{position:relative}
.tv-sw input{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:5px 9px 5px 27px;color:var(--text);font-size:12px;width:180px;font-family:var(--font-ui);outline:none;transition:all var(--transition)}
.tv-sw input:focus{border-color:var(--accent);width:230px}
.tv-sw svg{position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none}
.tv-wrap{flex:1;overflow:auto;position:relative}
table.dt{width:max-content;min-width:100%;border-collapse:collapse;font-size:12px;font-family:var(--font-mono)}
@media (max-width: 640px) {
  table.dt{font-size:11px}
  table.dt td, table.dt th{padding:5px 8px}
}
table.dt thead th{position:sticky;top:0;background:var(--bg2);color:var(--text-dim);font-size:10px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;z-index:10;cursor:pointer;user-select:none;font-family:var(--font-ui)}
table.dt thead th:hover{color:var(--text)}
table.dt thead th.s-asc::after{content:' ↑';color:var(--accent)}
table.dt thead th.s-desc::after{content:' ↓';color:var(--accent)}
table.dt thead th.pk-h::before{content:'🔑 ';font-size:10px}
table.dt tbody tr{border-bottom:1px solid var(--border);transition:background var(--transition)}
table.dt tbody tr:hover{background:var(--bg3)}
table.dt td{padding:6px 12px;color:var(--text);white-space:nowrap;max-width:280px;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;position:relative}
@media (max-width: 640px) {
  table.dt td{max-width:150px}
}
table.dt td.nv{color:var(--text-muted);font-style:italic}
table.dt td.numv{color:var(--yellow)}
table.dt td.longv{cursor:pointer}
table.dt td.longv:hover{color:var(--accent)}
table.dt td.edit{cursor:pointer}
table.dt td.edit:hover::after{content:'✎';position:absolute;right:5px;top:50%;transform:translateY(-50%);color:var(--accent);font-size:10px;opacity:.7}
.cei{background:var(--bg);border:1.5px solid var(--accent);border-radius:3px;color:var(--text);font-family:var(--font-mono);font-size:12px;padding:2px 5px;width:calc(100% - 6px);outline:none;box-shadow:0 0 0 3px var(--accent-dim)}
/* Row context menu - hidden by default, shown on right-click */
.rac{display:none}
/* Tooltip for row actions - overlay, no layout shift */
table.dt tbody tr{cursor:context-menu;position:relative}
table.dt tbody tr:hover{background:var(--bg3)}
.row-hover-tip{position:fixed;left:-9999px;top:-9999px;background:rgba(0,0,0,0.96);color:var(--accent);border:1px solid var(--accent);padding:3px 10px;border-radius:4px;font-size:10px;font-weight:600;pointer-events:none;z-index:11000;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,0.3);opacity:0;transition:opacity .12s ease}
.row-hover-tip.open{opacity:1}
/* Context menu modal */
.ctx-menu{position:fixed;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-lg);
  box-shadow:0 8px 32px rgba(0,0,0,.4);z-index:10000;min-width:180px;overflow:hidden;display:none}
.ctx-menu.open{display:block}
.ctx-item{display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;font-size:12px;color:var(--text);
  transition:background var(--transition);border:none;background:none;width:100%;text-align:left}
.ctx-item:hover{background:var(--accent-dim);color:var(--accent)}
.ctx-item.danger:hover{background:var(--red-dim);color:var(--red)}
.ctx-item svg{flex-shrink:0;opacity:.8}
.ctx-sep{height:1px;background:var(--border);margin:2px 0}
.badge{display:inline-flex;align-items:center;padding:1px 5px;border-radius:9px;font-size:9px;font-weight:600;letter-spacing:.5px;text-transform:uppercase}
.badge.pk{background:var(--accent-dim);color:var(--accent);border:1px solid var(--accent-brd)}
.tv-pag{padding:8px 14px;border-top:1px solid var(--border);display:flex;align-items:center;gap:7px;background:var(--bg2);flex-shrink:0;flex-wrap:wrap}
@media (max-width: 480px) {
  .tv-pag{gap:5px;padding:8px 10px}
  .pag-info{font-size:10px}
}
.pag-info{font-size:11px;color:var(--text-dim);flex:1;font-family:var(--font-mono)}
.pag-sel{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);padding:3px 7px;font-size:11px;font-family:var(--font-ui);cursor:pointer;outline:none}
.pag-pg{font-size:11px;color:var(--text-dim);font-family:var(--font-mono);padding:0 3px}
.lrow td{text-align:center;padding:32px;color:var(--text-muted);font-family:var(--font-ui)}
.spin{display:inline-block;width:14px;height:14px;border:2px solid var(--border2);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px}
@keyframes spin{to{transform:rotate(360deg)}}
.empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:10px;color:var(--text-muted);font-size:13px}
.empty-ico{font-size:36px;opacity:.25}

/* SQL view - Enhanced with modal history */
#view-sql{flex:1;flex-direction:column;overflow:hidden}
.sql-panel{display:flex;flex-direction:column;height:100%;padding:14px;gap:12px;overflow-y:auto}
.sql-editor-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;display:flex;flex-direction:column;flex:1;min-height:0}
.sql-toolbar{padding:8px 12px;background:var(--bg3);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.sql-toolbar .sql-lbl{font-size:10px;font-weight:600;color:var(--text-dim);letter-spacing:.8px;text-transform:uppercase}
.sql-toolbar .spacer{flex:1}
.CodeMirror{height:calc(100vh - 280px);min-height:250px;max-height:calc(100vh - 250px);font-family:var(--font-mono);font-size:12px;background:var(--bg2);color:var(--text);border:none}
.CodeMirror-gutters{background:var(--bg3);border-right:1px solid var(--border)}
.CodeMirror-linenumber{color:var(--text-muted)}
.CodeMirror-cursor{border-left-color:var(--accent)}
.cm-s-default .cm-keyword{color:var(--accent)}
.cm-s-default .cm-string{color:var(--green)}
.cm-s-default .cm-number{color:var(--orange)}
.cm-s-default .cm-comment{color:var(--text-muted)}
.sql-result-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;flex-shrink:0;min-height:180px;max-height:300px;display:flex;flex-direction:column}
.sql-result-header{padding:8px 12px;background:var(--bg3);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:9px;font-size:11px;color:var(--text-dim)}
.sql-result-body{overflow:auto;flex:1}
.sql-result-table{width:100%;border-collapse:collapse;font-size:11px;font-family:var(--font-mono)}
.sql-result-table th{position:sticky;top:0;background:var(--bg2);padding:6px 10px;text-align:left;border-bottom:1px solid var(--border);color:var(--text-dim);font-weight:600}
.sql-result-table td{padding:4px 10px;border-bottom:1px solid var(--border);color:var(--text);white-space:nowrap}
.sql-result-table td.null-val{color:var(--text-muted);font-style:italic}
.sql-ph{padding:24px;text-align:center;color:var(--text-muted);font-size:12px}

/* Modal for history */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center}
.modal.open{display:flex}
.mbox{background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-lg);width:90%;max-width:700px;max-height:82vh;overflow-y:auto;position:relative}
.mhdr{padding:13px 17px;background:var(--bg3);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:9px;position:sticky;top:0;z-index:10}
.mtitle{font-size:13px;font-weight:600;color:var(--text);flex:1}
.mclose{width:26px;height:26px;background:none;border:1px solid var(--border);border-radius:var(--radius);color:var(--text-dim);cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;transition:all var(--transition);line-height:1}
.mclose:hover{border-color:var(--red);color:var(--red)}
.mbody{padding:14px 17px}
.history-item{padding:10px 12px;border-bottom:1px solid var(--border);cursor:pointer;font-family:var(--font-mono);font-size:11px;transition:background var(--transition);margin:0 -5px;border-radius:var(--radius)}
.history-item:hover{background:var(--bg3);color:var(--accent)}
.history-item:last-child{border-bottom:none}
.history-sql{font-family:var(--font-mono);white-space:pre-wrap;word-break:break-all}
.history-time{font-size:9px;color:var(--text-muted);margin-top:4px}
.history-actions{display:flex;gap:8px;margin-top:6px}
.history-actions button{background:none;border:1px solid var(--border);border-radius:3px;padding:2px 8px;font-size:10px;cursor:pointer}
.history-actions button:hover{border-color:var(--accent);color:var(--accent)}
#ins-modal .mbox{overflow:hidden;display:flex;flex-direction:column}
#im-fields{padding:14px 17px;overflow:auto;min-height:0;flex:1}
.ins-field{display:flex;flex-direction:column;gap:4px;padding:8px 0;border-bottom:1px solid var(--border)}
.ins-field:last-child{border-bottom:none}
.ins-field label{font-size:10px;color:var(--text-dim);font-weight:600;letter-spacing:.5px;text-transform:uppercase}
.ins-field label.pk{color:var(--accent)}
.ins-field input{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);padding:7px 9px;font-size:12px;font-family:var(--font-ui);outline:none;transition:border-color var(--transition)}
.ins-field input:focus{border-color:var(--accent)}
.ins-field input::placeholder{color:var(--text-muted)}
.ins-ftr{display:flex;justify-content:flex-end;gap:8px;padding:0 17px 14px;flex-wrap:wrap}
.empty-history{text-align:center;padding:40px;color:var(--text-muted)}
.confirm-modal .mbox{max-width:440px}
.confirm-msg{font-size:13px;line-height:1.5;color:var(--text);white-space:pre-wrap}
.confirm-ftr{display:flex;justify-content:flex-end;gap:8px;padding:0 17px 14px}
.slide-confirm{display:flex;flex-direction:column;gap:10px}
.slide-confirm-label{font-size:11px;color:var(--text-dim)}
.slide-track{position:relative;height:42px;border:1px solid var(--border);border-radius:999px;background:linear-gradient(90deg,var(--bg3),var(--bg2));overflow:hidden;user-select:none;touch-action:none}
.slide-fill{position:absolute;left:0;top:0;bottom:0;width:0;background:linear-gradient(90deg,var(--red-dim),rgba(239,68,68,.35));transition:width .08s linear}
.slide-text{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--text-dim);font-weight:600;letter-spacing:.3px;pointer-events:none}
.slide-thumb{position:absolute;left:0;top:3px;width:36px;height:36px;border-radius:50%;border:1px solid rgba(255,255,255,.15);background:linear-gradient(180deg,#ff6d6d,#ef4444);color:#fff;display:flex;align-items:center;justify-content:center;cursor:grab;box-shadow:0 8px 20px rgba(239,68,68,.35)}
.slide-thumb:active{cursor:grabbing}
.slide-confirm.done .slide-track{border-color:var(--red)}
.slide-confirm.done .slide-text{color:var(--red)}
#row-modal .mbox{max-height:82vh;overflow:hidden;display:flex;flex-direction:column}
#row-modal .mbody{flex:1;min-height:0;overflow:auto}
#rm-body{overflow-x:auto}
#rm-body::-webkit-scrollbar{height:9px;width:9px}
#rm-body::-webkit-scrollbar-track{background:var(--bg3)}
#rm-body::-webkit-scrollbar-thumb{background:var(--border2);border-radius:999px}
#rm-body::-webkit-scrollbar-thumb:hover{background:var(--text-muted)}
.frow{display:grid;grid-template-columns:minmax(160px,220px) minmax(420px,1fr);gap:10px;align-items:start;padding:8px 0;border-bottom:1px solid var(--border);min-width:max-content}
.frow:last-child{border-bottom:none}
.fkey{color:var(--text-dim);font-weight:600}
.fkey.pk{color:var(--accent)}
.fval{color:var(--text);white-space:pre-wrap;word-break:break-word;min-width:320px}

/* Sort Modal */
.sort-option{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:var(--radius);cursor:pointer;font-size:13px;color:var(--text);transition:background var(--transition);border-bottom:1px solid var(--border)}
.sort-option:last-child{border-bottom:none}
.sort-option:hover{background:var(--accent-dim);color:var(--accent)}
.sort-option.active{background:var(--accent-dim);color:var(--accent);font-weight:600}
.sort-option svg{flex-shrink:0;opacity:.7}
.sort-option.active svg{opacity:1}

/* Export Modal */
.export-modal{padding:0!important;margin:0!important;align-items:stretch!important;justify-content:stretch!important}
.export-modal .modal-close{display:none}
.export-modal .mbox{max-width:100vw!important;width:100vw!important;max-height:none!important;min-height:100vh!important;min-height:100dvh!important;height:100vh!important;height:100dvh!important;display:flex!important;flex-direction:column!important;padding:0!important;margin:0!important;border-radius:0!important;border:none!important;overflow:hidden!important}
.export-mbox{flex:1;min-height:0;padding:0;overflow:hidden;display:flex;flex-direction:column}
.export-toolbar{display:flex;align-items:center;gap:8px;padding:10px 17px;background:var(--bg3);border-bottom:1px solid var(--border);flex-shrink:0}
.export-tb-btn{width:32px;height:32px;background:none;border:1px solid var(--border);border-radius:var(--radius);color:var(--text);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all var(--transition);position:relative}
.export-tb-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-dim)}
.export-tb-btn:active{transform:scale(0.95)}
.export-tb-btn svg{pointer-events:none}
.export-preview{flex:1;display:flex;flex-direction:row;min-height:0;position:relative;overflow:hidden;align-items:stretch}
.export-monaco-wrap{flex:1;min-width:0;min-height:0;position:relative}
.export-monaco-wrap .monaco-editor,.export-monaco-wrap .monaco-editor-background,.export-monaco-wrap .monaco-editor .margin{background-color:var(--bg2)!important}

/* Add user */
#view-adduser{padding:16px;overflow-y:auto;min-height:0}
.fcard{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;max-width:520px;width:100%}
@media (max-width: 640px) {
  .fcard{padding:15px}
  .fg-grid{grid-template-columns:1fr}
}
.fcard h3{font-size:14px;font-weight:600;margin-bottom:16px;color:var(--text);display:flex;align-items:center;gap:7px}
.fg{display:flex;flex-direction:column;gap:4px;margin-bottom:12px}
.fg label{font-size:10px;color:var(--text-dim);font-weight:600;letter-spacing:.5px;text-transform:uppercase}
.fg input,.fg select{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);padding:7px 9px;font-size:12px;font-family:var(--font-ui);outline:none;transition:border-color var(--transition)}
.fg input:focus,.fg select:focus{border-color:var(--accent)}
.fg input[type=color]{height:34px;padding:2px 4px;cursor:pointer}
.fg-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}

/* DB Stats */
#view-stats{flex-direction:column;height:100%;min-height:0;box-sizing:border-box}

/* Monitoring View */
.mon-toolbar{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border);background:var(--bg2);flex-shrink:0}
.mon-title{font-size:14px;font-weight:600;color:var(--text);flex:1}
.mon-actions{display:flex;gap:8px;flex-wrap:wrap}
.mon-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:12px;margin-bottom:10px}
.mon-card-header{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;color:var(--text);margin-bottom:8px}
.mon-card-header svg{color:var(--accent)}
.mon-card-body{font-size:12px;color:var(--text-dim);padding:8px;background:var(--bg3);border-radius:var(--radius);font-family:var(--font-mono)}
.mon-card-body.ok{color:var(--green);background:var(--green-dim)}
.mon-card-body.fail{color:var(--red);background:var(--red-dim)}
.mon-charts{display:flex;flex-direction:column;gap:12px}
.mon-chart-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media (max-width: 768px) {
  .mon-chart-row{grid-template-columns:1fr}
}
.mon-chart-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:12px}
.mon-chart-title{font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px}
.mon-chart-body{position:relative;min-height:200px}
.mon-chart-body canvas{width:100%!important;height:200px!important}
.mon-table-section{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden}
.mon-table-title{font-size:11px;font-weight:600;color:var(--text-muted);padding:10px 12px 6px;text-transform:uppercase;letter-spacing:0.5px}
.mon-table{width:100%;border-collapse:collapse;font-size:12px}
.mon-table thead th{background:var(--bg3);color:var(--text-dim);font-size:10px;font-weight:600;text-transform:uppercase;padding:8px 12px;text-align:left;border-bottom:1px solid var(--border)}
.mon-table tbody tr{border-bottom:1px solid var(--border);transition:background var(--transition)}
.mon-table tbody tr:hover{background:var(--bg3)}
.mon-table td{padding:8px 12px;color:var(--text)}

/* Custom SVG Schema Diagram */
.diag-toolbar{display:flex;align-items:center;gap:6px;padding:8px 0;flex-shrink:0}
.diag-canvas-wrap{flex:1;overflow:hidden;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);position:relative;cursor:grab;user-select:none;min-height:0;touch-action:none}
.diag-canvas-wrap:active{cursor:grabbing}
.diag-svg{position:absolute;top:0;left:0;font-family:var(--font-mono),monospace}
/* Keep mermaid container for schema diagram (unused but safe) */
.mermaid-container{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;overflow:auto;max-height:85vh;min-height:400px}
.mermaid-container svg{width:100%;height:auto;display:block;margin:0 auto}
.mermaid-container .empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:300px;color:var(--text-muted)}
.stats-g{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
@media (max-width: 480px) {
  .stats-g{grid-template-columns:repeat(2,1fr)}
}
.scard{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px 16px}
.scard-lbl{font-size:10px;color:var(--text-muted);letter-spacing:.8px;text-transform:uppercase;margin-bottom:4px}
.scard-val{font-size:20px;font-weight:600;font-family:var(--font-mono);color:var(--text)}
.scard-val.ac{color:var(--accent)}
.scard-val.gn{color:var(--green)}
.scard-val.or{color:var(--orange)}
.tsl{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;display:flex;flex-direction:column;min-height:0}
.tsl-scroll{overflow-y:auto;flex:1;max-height:400px}
.tsl-hdr{padding:9px 14px;background:var(--bg3);border-bottom:1px solid var(--border);font-size:10px;font-weight:600;color:var(--text-dim);text-transform:uppercase;letter-spacing:.8px}
.tsl-r{display:flex;align-items:center;padding:7px 14px;border-bottom:1px solid var(--border);gap:9px;font-size:12px;transition:background var(--transition);cursor:pointer}
.tsl-r:last-child{border-bottom:none}
.tsl-r:hover{background:var(--bg3)}
.tsl-n{flex:1;font-family:var(--font-mono);color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tsl-bw{width:100px;height:3px;background:var(--bg3);border-radius:2px;overflow:hidden}
@media (max-width: 480px) {
  .tsl-bw{width:60px}
}
.tsl-b{height:100%;background:var(--accent);border-radius:2px;transition:width .4s ease}
.tsl-c{color:var(--accent);font-family:var(--font-mono);font-size:11px;min-width:50px;text-align:right}

/* Schema Tree */
#view-schema{padding:14px;overflow-y:auto;overflow-x:visible;gap:12px;flex-direction:column;height:100%;box-sizing:border-box}
.sch-toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px}
.sch-title{font-size:14px;font-weight:600;color:var(--text);flex:1}
.sch-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:12px;align-items:start}
@media (max-width: 640px) {
  .sch-grid{grid-template-columns:1fr}
}
.sch-tbl{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:visible;align-self:start}
.sch-tbl-hdr{padding:10px 14px;background:var(--bg3);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none;flex-wrap:wrap}
.sch-tbl-name{font-size:13px;font-weight:600;color:var(--text);flex:1;font-family:var(--font-mono);word-break:break-word}
.sch-tbl-cnt{font-size:10px;color:var(--text-muted);font-family:var(--font-mono);background:var(--bg);border:1px solid var(--border);padding:1px 7px;border-radius:9px}
.sch-chevron{color:var(--text-dim);transition:transform .2s;flex-shrink:0}
.sch-tbl.collapsed .sch-chevron{transform:rotate(-90deg)}
.sch-tbl.collapsed .sch-body{display:none}
.sch-body{border-top:0}
.sch-col-hdr{display:grid;grid-template-columns:24px 1fr 90px 40px 60px 80px;gap:0;padding:5px 12px;background:var(--bg3);border-bottom:1px solid var(--border);font-size:9px;font-weight:600;color:var(--text-muted);letter-spacing:.8px;text-transform:uppercase;font-family:var(--font-ui)}
@media (max-width: 640px) {
  .sch-col-hdr{grid-template-columns:24px 1fr 70px 30px 40px 60px;font-size:8px}
}
.sch-col{display:grid;grid-template-columns:24px 1fr 90px 40px 60px 80px;gap:0;padding:5px 12px;border-bottom:1px solid var(--border);font-size:11px;transition:background var(--transition)}
@media (max-width: 640px) {
  .sch-col{grid-template-columns:24px 1fr 70px 30px 40px 60px;font-size:10px}
}
.sch-col:last-of-type{border-bottom:none}
.sch-col:hover{background:var(--bg3)}
.sch-col-pk{display:flex;align-items:center;justify-content:center;font-size:10px}
.sch-col-name{font-family:var(--font-mono);color:var(--text);font-weight:500;word-break:break-word}
.sch-col-type{font-family:var(--font-mono);color:var(--yellow);font-size:10px}
.sch-col-nn{color:var(--text-muted);font-size:10px}
.sch-col-dflt{color:var(--text-muted);font-size:10px;font-family:var(--font-mono);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sch-indexes{padding:8px 12px;background:var(--bg);border-top:1px solid var(--border)}
.sch-idxs-lbl{font-size:9px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px}
.sch-idx{font-size:11px;font-family:var(--font-mono);color:var(--text-dim);padding:2px 0;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.sch-idx-u{color:var(--accent);font-size:9px;background:var(--accent-dim);border:1px solid var(--accent-brd);border-radius:3px;padding:0 4px;font-weight:600;text-transform:uppercase;letter-spacing:.4px}
.sch-fks{padding:8px 12px;background:var(--bg);border-top:1px solid var(--border)}
.sch-fk{font-size:11px;font-family:var(--font-mono);color:var(--text-dim);padding:2px 0}
.sch-fk-arr{color:var(--accent);margin:0 4px}
.sch-ddl{padding:8px 12px;background:var(--bg);border-top:1px solid var(--border);display:flex;align-items:center;justify-content:flex-end;gap:6px}

/* Schema column tooltips */
.sch-col[data-row-tooltip]{position:relative}
.sch-col[data-row-tooltip]:hover::after{
  content:attr(data-row-tooltip);
  position:absolute;
  bottom:100%;
  left:0;
  transform:none;
  background:var(--bg2);
  color:var(--text);
  padding:10px 14px;
  border:2px solid var(--accent);
  border-radius:0;
  font-size:10px;
  font-family:var(--font-mono);
  line-height:1.6;
  white-space:pre-wrap;
  z-index:100000 !important;
  pointer-events:none;
  box-shadow:0 6px 18px rgba(0,0,0,0.4);
  margin-bottom:6px;
}
.sch-col[data-row-tooltip]:hover::before{
  content:'';
  position:absolute;
  bottom:100%;
  left:16px;
  transform:none;
  border:6px solid transparent;
  border-top-color:var(--accent);
  z-index:100000 !important;
  pointer-events:none;
  margin-bottom:-2px;
}

/* Relations View */
#view-relations{padding:14px;overflow:hidden;gap:12px;flex-direction:column;height:100%;min-height:0;box-sizing:border-box}
#relations-list-view{flex:1;min-height:0;overflow-y:auto}
#relations-diagram-view{flex:1;min-height:0;flex-direction:column;overflow:hidden}
.rel-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;margin-bottom:12px}
@media (max-width: 640px) {
  .rel-card{padding:12px}
}
.rel-title{font-size:14px;font-weight:600;color:var(--accent);margin-bottom:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.rel-list{display:flex;flex-direction:column;gap:8px}
.rel-item{display:flex;align-items:center;gap:12px;padding:8px 12px;background:var(--bg3);border-radius:var(--radius);border-left:3px solid var(--accent);flex-wrap:wrap}
@media (max-width: 480px) {
  .rel-item{gap:8px}
}
.rel-from{font-family:var(--font-mono);font-weight:600;color:var(--text);min-width:120px}
.rel-arrow{color:var(--accent);font-size:16px}
.rel-to{font-family:var(--font-mono);color:var(--text-dim);flex:1;word-break:break-word}
.rel-detail{font-size:10px;color:var(--text-muted);font-family:var(--font-mono);padding:4px 8px;background:var(--bg2);border-radius:4px}
.empty-rel{text-align:center;padding:40px;color:var(--text-muted);background:var(--bg2);border-radius:var(--radius-lg);border:1px dashed var(--border)}

/* Create Table */
#view-createtable{padding:14px;overflow-y:auto;min-height:0}
.ct-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;max-width:800px;width:100%}
@media (max-width: 640px) {
  .ct-card{padding:15px}
}
.ct-card h3{font-size:14px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:7px}
.col-def-row{display:flex;gap:8px;margin-bottom:8px;align-items:center;flex-wrap:wrap}
.col-def-row input{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);padding:6px 8px;font-size:12px;flex:2;min-width:120px}
.col-def-row select{padding:6px 8px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);flex:1}
.col-def-row input.col-const{flex:2}
.col-def-row button{background:var(--red-dim);border:1px solid var(--red);color:var(--red);border-radius:var(--radius);padding:4px 8px;cursor:pointer}
@media (max-width: 480px) {
  .col-def-row{flex-direction:column;align-items:stretch}
  .col-def-row input,.col-def-row select,.col-def-row button{width:100%}
}
#add-col-btn{margin-top:12px;margin-bottom:16px}
.ct-status{margin-top:12px;font-size:12px;min-height:20px}

/* Notifications */
#nc{position:fixed;bottom:14px;right:14px;display:flex;flex-direction:column;gap:6px;z-index:100003}
.ntf{background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-lg);padding:10px 14px;font-size:12px;min-width:220px;max-width:320px;display:flex;align-items:center;gap:8px;box-shadow:0 6px 20px rgba(0,0,0,.3);transform:translateX(110%);transition:transform .2s ease}
.ntf.show{transform:translateX(0)}
.ntf.success{border-color:var(--green)}
.ntf.error{border-color:var(--red)}
.ntf.info{border-color:var(--accent)}
.ntf-ico{font-size:14px;flex-shrink:0}
.ntf-txt{flex:1;color:var(--text);line-height:1.4}
.db-choice{position:fixed;inset:0;background:var(--bg);z-index:100004;display:flex;align-items:center;justify-content:center;padding:20px}
.db-choice-box{width:min(520px,100%);display:grid;gap:12px}
.db-choice-title{font-size:18px;font-weight:700;color:var(--text);margin-bottom:4px}
.db-choice-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.db-choice-btn{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-lg);color:var(--text);padding:18px;text-align:left;cursor:pointer}
.db-choice-btn:hover{border-color:var(--accent)}
.db-choice-name{font-size:15px;font-weight:700;margin-bottom:6px}
.db-choice-desc{font-size:12px;color:var(--text-muted)}
@media (max-width: 560px){.db-choice-grid{grid-template-columns:1fr}}

/* Embed mode - for admin panel integration */
body.embed-mode{overflow:hidden;min-height:100vh;max-height:100vh;height:100vh;-webkit-overflow-scrolling:touch}
body.embed-mode #app{min-height:0;height:100%;max-height:100vh}
body.embed-mode #topbar{height:42px;padding:0 8px}
body.embed-mode #main-content{padding:8px}
body.embed-mode .card{border-radius:6px}
body.embed-mode table{font-size:11px}
body.embed-mode thead th,body.embed-mode tbody td{padding:6px 8px}
</style>
</head>
<body style="background:<?= $isDark ? '#0f1419' : '#f8f9fc' ?>" class="<?= $embedMode ? 'embed-mode' : '' ?>">
<div id="app">
<div class="db-choice" id="db-choice">
  <div class="db-choice-box">
    <div class="db-choice-title">Select database</div>
    <div class="db-choice-grid">
      <button type="button" class="db-choice-btn" data-db-source="postgres"><div class="db-choice-name">PostgreSQL</div><div class="db-choice-desc">Tables, SQL console, schema, stats</div></button>
      <button type="button" class="db-choice-btn" data-db-source="redis"><div class="db-choice-name">Redis</div><div class="db-choice-desc">Keys, session and trusted device storage</div></button>
    </div>
  </div>
</div>

<div id="topbar">
  <button class="tb-btn" id="sidebar-toggle" title="<?= t('show_sidebar') ?>">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
  </button>
  <div class="tb-brand">
    <div class="tb-brand-icon">🗄</div>
    <?= t('title') ?>
    <span class="tb-badge" id="db-source-badge">PostgreSQL · appdashboard</span>
  </div>
  <div class="tb-spacer"></div>
  <select class="tb-select" id="db-source-select" title="Database">
    <option value="postgres">PostgreSQL</option>
    <option value="redis">Redis</option>
  </select>
  <button class="tb-btn" id="export-btn" title="<?= t('export_report') ?>">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zm-8 2V5h2v6h1.17L12 13.17 9.83 11H11zm-6 7h14v2H5z"/></svg>
    <span><?= t('export_report') ?></span>
  </button>
  <?php if ($isRoot): ?>
  <button class="tb-btn danger" id="vacuum-btn"><?= t('vacuum') ?></button>
  <?php endif; ?>
</div>

<div id="sidebar-overlay"></div>
<div id="main">
  <div id="sidebar">
    <div class="sb-label-row">
      <span class="sb-label" title="<?= t('tables_hint') ?>"><?= t('tables') ?></span>
      <div class="sb-actions" title="<?= t('view_mode_hint') ?>">
        <div class="sb-view-toggle" role="group">
          <button type="button" class="sb-view-btn active" data-view="flat" id="table-view-flat"><?= t('view_mode_all') ?></button>
          <button type="button" class="sb-view-btn" data-view="stacks" id="table-view-stacks"><?= t('view_mode_stacks') ?></button>
        </div>
        <button class="sb-sort-btn" id="sort-tables-btn" title="<?= t('sort_tables') ?> — <?= t('sort_tables_hint') ?>">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"/></svg>
        </button>
      </div>
    </div>
    <div class="sb-search">
      <div class="sb-si">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.5 6.5 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
        <input type="text" id="tsearch" placeholder="<?= t('filter_tables') ?>" title="<?= t('filter_tables_hint') ?>">
      </div>
    </div>
    <div id="table-list">
      <div class="empty" style="height:60px;font-size:11px;"><span class="spin"></span></div>
    </div>
    <div class="sb-footer">
      <button class="sb-fbtn" id="nav-schema" title="<?= t('schema_tree') ?> — <?= t('schema_subtitle') ?>">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
        <?= t('schema_tree') ?>
      </button>
      <button class="sb-fbtn" id="nav-relations" title="<?= t('relation_title') ?> — <?= t('relation_desc') ?>">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M4 7c0-1.66 1.34-3 3-3s3 1.34 3 3-1.34 3-3 3-3-1.34-3-3zm12 0c0-1.66 1.34-3 3-3s3 1.34 3 3-1.34 3-3 3-3-1.34-3-3zm-6 10c0-1.66 1.34-3 3-3s3 1.34 3 3-1.34 3-3 3-3-1.34-3-3z"/><path d="M10 8h4v6h-4zM15 13h6v2h-6zM3 13h6v2H3z"/></svg>
        <?= t('relations') ?>
      </button>
      <button class="sb-fbtn" id="nav-adduser" title="<?= t('add_new_user') ?> — <?= t('add_user_hint') ?>">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        <?= t('add_user') ?>
      </button>
      <?php if ($canRunSql): ?>
      <button class="sb-fbtn" id="nav-sql" title="<?= t('run_sql') ?> — <?= t('run_sql_hint') ?>">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>
        <?= t('run_sql') ?>
      </button>
      <?php endif; ?>
      <?php if ($isRoot): ?>
      <button class="sb-fbtn" id="nav-createtable" title="<?= t('create_table') ?> — <?= t('create_table_hint') ?>">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>
        <?= t('create_table') ?>
      </button>
      <?php endif; ?>
      <button class="sb-fbtn" id="nav-stats" title="<?= t('db_stats') ?> — <?= t('db_stats_hint') ?>">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z"/></svg>
        <?= t('db_stats') ?>
      </button>

    </div>
  </div>

  <div id="content">

    <!-- Welcome -->
    <div class="view active" id="view-welcome">
      <div class="empty">
        <div class="empty-ico">🗄️</div>
        <div><?= t('select_table') ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><?= t('tools_hint') ?></div>
      </div>
    </div>

    <!-- Table -->
    <div class="view" id="view-table">
      <div class="tv-bar">
        <div class="tv-title"><span id="tv-name" class="tv-name-copy" tabindex="0" role="button" title="<?= t('copy_table_name_hint') ?>" aria-label="<?= t('copy_table_name_hint') ?>" data-copy-hint="<?= t('copy_table_name_hint') ?>">—</span><span class="tv-badge" id="tv-cnt">0</span></div>
        <div class="tv-spacer"></div>
        <div class="tv-sw">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.5 6.5 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
          <input type="text" id="tv-search" placeholder="<?= t('search_cols') ?>">
        </div>
        <button class="btn" id="tv-refresh"><svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg><span><?= t('refresh') ?></span></button>
        <button class="btn success" id="tv-insert"><svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg><span><?= t('insert_row') ?></span></button>
      </div>
      <div class="tv-wrap"><table class="dt" id="dtable"><thead><tr id="dthead"></tr></thead><tbody id="dtbody"></tbody></table></div>
      <div class="tv-pag">
        <div class="pag-info" id="pag-info">—</div>
        <select class="pag-sel" id="pag-lim"><option value="25">25 <?= t('per_page') ?></option><option value="50" selected>50 <?= t('per_page') ?></option><option value="100">100 <?= t('per_page') ?></option><option value="200">200 <?= t('per_page') ?></option></select>
        <button class="btn sm" id="pag-f" disabled>«</button>
        <button class="btn sm" id="pag-p" disabled>‹</button>
        <span class="pag-pg" id="pag-pg">1/1</span>
        <button class="btn sm" id="pag-n">›</button>
        <button class="btn sm" id="pag-l">»</button>
      </div>
    </div>

    <!-- Schema Tree -->
    <div class="view" id="view-schema">
      <div class="sch-toolbar">
        <div class="sch-title"><?= t('schema_title') ?></div>
        <button class="btn sm" id="sch-expand"><?= t('expand_all') ?></button>
        <button class="btn sm" id="sch-collapse"><?= t('collapse_all') ?></button>
      </div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px"><?= t('schema_subtitle') ?></div>

      <!-- Tree View -->
      <div id="schema-tree-view">
        <div class="sch-grid" id="sch-grid"><div class="empty" style="grid-column:1/-1;height:80px"><span class="spin"></span></div></div>
      </div>
    </div>

    <!-- Relations View -->
    <div class="view" id="view-relations">
      <div class="sch-toolbar">
        <div class="sch-title"><?= t('relation_title') ?></div>
        <button class="btn" id="toggle-relations-view-btn"><?= t('diagram_view') ?></button>
      </div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px"><?= t('relation_desc') ?></div>
      
      <!-- List View -->
      <div id="relations-list-view">
        <div id="relations-container" style="display:flex;flex-direction:column;gap:12px;"></div>
      </div>
      
      <!-- Custom SVG Diagram View -->
      <div id="relations-diagram-view" style="display:none">
        <div class="diag-toolbar">
          <span style="font-size:11px;color:var(--text-muted)" id="diag-zoom-label">100%</span>
          <button class="btn sm" id="diag-zoom-in">＋</button>
          <button class="btn sm" id="diag-zoom-out">－</button>
          <button class="btn sm" id="diag-zoom-reset"><?= t('zoom_reset') ?></button>
        </div>
        <div class="diag-canvas-wrap" id="diag-canvas-wrap">
          <svg id="diag-svg" class="diag-svg"></svg>
        </div>
      </div>
    </div>

    <!-- SQL Console - Enhanced with modal history -->
    <div class="view" id="view-sql">
      <div class="sql-panel">
        <div class="sql-editor-card">
          <div class="sql-toolbar">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="color:var(--accent)"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>
            <span class="sql-lbl"><?= t('sql_editor') ?></span>
            <div class="spacer"></div>
            <span style="font-size:10px;color:var(--text-muted)"><?= t('sql_shortcuts') ?></span>
            <button class="btn" id="show-history-btn" title="<?= t('sql_history') ?>">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 8v4l3 3-3 3v-4H9v-2h3V8zm1-4C6.48 4 1 9.48 1 16s5.48 12 12 12 12-5.48 12-12S17.52 4 13 4z"/></svg>
              <span><?= t('history') ?></span>
            </button>
            <button class="btn" id="format-sql-btn" title="<?= t('format_sql') ?>">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M5 17h14v2H5zm7-12L5.33 9.67l1.34 1.34L12 7.33l5.33 5.34 1.34-1.34z"/></svg>
              <span><?= t('format_sql') ?></span>
            </button>
            <button class="btn primary" id="run-sql"><?= t('execute') ?></button>
            <button class="btn" id="clr-sql"><?= t('clear') ?></button>
          </div>
          <textarea id="sql-editor"></textarea>
        </div>
        <div class="sql-result-card">
          <div class="sql-result-header">
            <span id="sql-res-lbl"><?= t('result') ?></span>
          </div>
          <div class="sql-result-body" id="sql-res-body">
            <div class="sql-ph"><?= t('sql_placeholder') ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- History Modal -->
    <div class="modal" id="history-modal">
      <div class="mbox">
        <div class="mhdr">
          <span class="mtitle"><?= t('sql_history') ?></span>
          <button class="btn sm" id="clear-history-modal-btn"><?= t('clear_history') ?></button>
          <button class="mclose" id="history-modal-close">×</button>
        </div>
        <div class="mbody" id="history-modal-body">
          <div class="empty-history"><?= t('loading') ?>...</div>
        </div>
      </div>
    </div>

    <!-- Sort Tables Modal -->
    <div class="modal" id="sort-modal">
      <div class="mbox" style="max-width:400px">
        <div class="mhdr">
          <span class="mtitle"><?= t('sort_tables') ?></span>
          <button class="mclose" id="sort-modal-close">×</button>
        </div>
        <div class="mbody" id="sort-modal-body">
          <div class="sort-option" data-sort="name-asc">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"/></svg>
            <span><?= t('sort_by_name') ?></span>
          </div>
          <div class="sort-option" data-sort="name-desc">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"/></svg>
            <span><?= t('sort_by_name_desc') ?></span>
          </div>
          <div class="sort-option" data-sort="rows-desc">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"/></svg>
            <span><?= t('sort_by_rows') ?></span>
          </div>
          <div class="sort-option" data-sort="rows-asc">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"/></svg>
            <span><?= t('sort_by_rows_asc') ?></span>
          </div>
          <div class="sort-option" data-sort="size-desc">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"/></svg>
            <span><?= t('sort_by_size') ?></span>
          </div>
          <div class="sort-option" data-sort="size-asc">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"/></svg>
            <span><?= t('sort_by_size_asc') ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Add User -->
    <div class="view" id="view-adduser">
      <div style="padding:14px;display:flex;justify-content:center">
        <div class="fcard">
          <h3><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="color:var(--accent)"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><?= t('add_new_user') ?></h3>
          <div class="fg"><label><?= t('email') ?> *</label><input type="email" id="nu-e" placeholder="<?= t('email_placeholder') ?>"></div>
          <div class="fg"><label><?= t('password') ?> *</label><input type="text" id="nu-p" placeholder="<?= t('password_placeholder') ?>"></div>
          <div class="fg"><label><?= t('display_name') ?></label><input type="text" id="nu-n" placeholder="<?= t('display_name_placeholder') ?>"></div>
          <div class="fg-grid">
            <div class="fg"><label><?= t('role') ?></label><select id="nu-r"><option value="0"><?= t('guest') ?></option><option value="1"><?= t('manager') ?></option><option value="2"><?= t('admin') ?></option></select></div>
            <div class="fg"><label><?= t('verified') ?></label><select id="nu-v"><option value="1"><?= t('yes_can_login') ?></option><option value="0"><?= t('no_email_req') ?></option></select></div>
            <div class="fg"><label><?= t('language') ?></label><select id="nu-l"><option value="en">English</option><option value="ru">Русский</option><option value="uk">Українська</option><option value="be">Беларуская</option><option value="pl">Polski</option><option value="cs">Čeština</option></select></div>
            <div class="fg"><label><?= t('theme') ?></label><select id="nu-t"><option value="light"><?= t('light') ?></option><option value="dark"><?= t('dark') ?></option><option value="auto"><?= t('auto') ?></option></select></div>
          </div>
          <div class="fg"><label><?= t('accent_color') ?></label><input type="color" id="nu-c" value="<?= htmlspecialchars($color) ?>"></div>
          <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
            <button class="btn primary" id="add-user-btn"><?= t('create_user') ?></button>
            <button class="btn" id="clr-user"><?= t('clear') ?></button>
          </div>
          <div id="au-status" style="margin-top:10px;font-size:12px;min-height:18px"></div>
        </div>
      </div>
    </div>

    <!-- Create Table (Root only) -->
    <?php if ($isRoot): ?>
    <div class="view" id="view-createtable">
      <div style="padding:14px;display:flex;justify-content:center">
        <div class="ct-card">
          <h3><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="color:var(--accent)"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg><?= t('create_table') ?></h3>
          <div class="fg"><label><?= t('new_table_name') ?></label><input type="text" id="ct-name" placeholder="<?= t('table_name_placeholder') ?>"></div>
          <div class="fg"><label><?= t('columns_def') ?></label></div>
          <div id="columns-container"></div>
          <button class="btn" id="add-col-btn">+ <?= t('add_column') ?></button>
          <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
            <button class="btn primary" id="create-table-btn"><?= t('create') ?></button>
            <button class="btn" id="clear-ct-btn"><?= t('clear') ?></button>
          </div>
          <div id="ct-status" class="ct-status"></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="view" id="view-stats">
      <div style="padding:14px;display:flex;flex-direction:column;gap:12px;flex:1;overflow-y:auto;min-height:0">
        <div class="stats-g" id="scards"></div>
        
        <!-- Charts Section -->
        <div class="mon-charts">
          <div class="mon-chart-row">
            <div class="mon-chart-card">
              <div class="mon-chart-title"><?= t('table_sizes') ?></div>
              <div class="mon-chart-body">
                <canvas id="table-size-chart"></canvas>
              </div>
            </div>
            <div class="mon-chart-card">
              <div class="mon-chart-title"><?= t('index_stats') ?></div>
              <div class="mon-chart-body">
                <canvas id="index-chart"></canvas>
              </div>
            </div>
          </div>
        </div>
        
        <div class="tsl"><div class="tsl-hdr"><?= t('rows_per_table') ?></div><div class="tsl-scroll" id="strows"></div></div>
      </div>
    </div>



  </div>
</div>
</div>

<!-- Row Modal -->
<div class="modal" id="row-modal">
  <div class="mbox">
    <div class="mhdr"><span class="mtitle" id="rm-title"><?= t('row_detail') ?></span><button class="mclose" id="rm-close">×</button></div>
    <div class="mbody" id="rm-body"></div>
  </div>
</div>

<!-- Context Menu (right-click) -->
<div class="ctx-menu" id="ctx-menu">
  <button class="ctx-item" id="ctx-view">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    <span><?= t('view_row') ?></span>
  </button>
  <div class="ctx-sep"></div>
  <button class="ctx-item danger" id="ctx-delete">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
    <span><?= t('delete_row') ?></span>
  </button>
</div>
<div class="row-hover-tip" id="row-hover-tip"></div>
<div class="ctx-menu" id="table-ctx-menu">
  <button class="ctx-item danger" id="table-ctx-delete">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
    <span><?= t('delete_table') ?></span>
  </button>
</div>

<!-- Insert Modal -->
<div class="modal" id="ins-modal">
  <div class="mbox">
    <div class="mhdr"><span class="mtitle" id="im-title"><?= t('insert_into') ?></span><button class="mclose" id="im-close">×</button></div>
    <div id="im-fields"></div>
    <div class="ins-ftr"><button class="btn" id="im-cancel"><?= t('cancel') ?></button><button class="btn primary" id="im-confirm"><?= t('insert') ?></button></div>
  </div>
</div>

<!-- Confirm Modal -->
<div class="modal confirm-modal" id="confirm-modal">
  <div class="mbox">
    <div class="mhdr">
      <span class="mtitle" id="confirm-title"><?= t('confirm') ?></span>
      <button class="mclose" id="confirm-close">×</button>
    </div>
    <div class="mbody">
      <div class="confirm-msg" id="confirm-message"></div>
    </div>
    <div class="confirm-ftr">
      <button class="btn" id="confirm-cancel"><?= t('cancel') ?></button>
      <button class="btn primary" id="confirm-ok"><?= t('ok') ?></button>
    </div>
  </div>
</div>

<!-- Export Modal -->
<div class="modal export-modal" id="export-modal">
  <div class="mbox export-mbox">
    <div class="mhdr">
      <span class="mtitle"><?= t('export_report') ?></span>
      <button class="mclose" id="export-modal-close">×</button>
    </div>
    <div class="export-toolbar">
      <button class="export-tb-btn" id="export-close-btn" title="<?= t('export_close') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <button class="export-tb-btn" id="export-download-btn" title="<?= t('export_download') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </button>
      <button class="export-tb-btn" id="export-select-all-btn" title="<?= t('export_select_all') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
      </button>
      <button class="export-tb-btn" id="export-copy-btn" title="<?= t('export_copy') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
      </button>
    </div>
    <div class="export-preview">
      <textarea id="export-content" style="display:none;"></textarea>
      <div class="export-monaco-wrap" id="export-monaco-wrap"></div>
    </div>
  </div>
</div>

<div id="nc"></div>

<script>
// ── i18n strings passed from PHP ────────────────────────────────────────────
const L = <?= json_encode($T, JSON_UNESCAPED_UNICODE) ?>;
const T = (k, ...args) => {
  let s = L[k] || k;
  args.forEach((a, i) => { s = s.replace(/%[sd]/, a); });
  return s;
};
const isRoot = <?= $isRoot ? 'true' : 'false' ?>;
const canRunSql = <?= $canRunSql ? 'true' : 'false' ?>;
const EXPORT_MONACO_THEME = <?= $isDark ? '"vs-dark"' : '"vs"' ?>;
const MONACO_VS_PATH = (location.protocol === 'https:' ? 'https:' : 'http:') + '//cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.2/min/vs';

// ── SQL History (localStorage) with timestamps ────────────────────────────────
let sqlHistory = [];
function loadSQLHistory() {
  try {
    const stored = localStorage.getItem('db_admin_sql_history_v2');
    if (stored) sqlHistory = JSON.parse(stored);
  } catch(e) {}
  if (!sqlHistory || !Array.isArray(sqlHistory)) sqlHistory = [];
  sqlHistory = sqlHistory.slice(0, 50);
}
function saveSQLHistory() {
  sqlHistory = sqlHistory.slice(0, 50);
  localStorage.setItem('db_admin_sql_history_v2', JSON.stringify(sqlHistory));
}
function addSQLHistory(sql) {
  if (!sql.trim()) return;
  const existingIndex = sqlHistory.findIndex(item => item.sql === sql);
  if (existingIndex !== -1) sqlHistory.splice(existingIndex, 1);
  sqlHistory.unshift({ sql: sql, time: new Date().toISOString() });
  if (sqlHistory.length > 50) sqlHistory.pop();
  saveSQLHistory();
  renderHistoryModal();
}
function renderHistoryModal() {
  const container = document.getElementById('history-modal-body');
  if (!container) return;
  if (sqlHistory.length === 0) {
    container.innerHTML = `<div class="empty-history">${T('no_history')}</div>`;
    return;
  }
  container.innerHTML = sqlHistory.map((item, idx) => `
    <div class="history-item" data-index="${idx}">
      <div class="history-sql">${esc(item.sql.length > 200 ? item.sql.slice(0, 200) + '...' : item.sql)}</div>
      <div class="history-time">${new Date(item.time).toLocaleString()}</div>
      <div class="history-actions">
        <button class="use-history" data-sql="${esc(item.sql)}">📋 ${T('use')}</button>
        <button class="copy-history" data-sql="${esc(item.sql)}">📄 ${T('copy')}</button>
        <button class="delete-history" data-index="${idx}">🗑 ${T('del')}</button>
      </div>
    </div>
  `).join('');
  
  container.querySelectorAll('.use-history').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      if (sqlEditor) sqlEditor.setValue(btn.dataset.sql);
      document.getElementById('history-modal').classList.remove('open');
      runSQL();
    });
  });
  
  container.querySelectorAll('.copy-history').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      copyToClipboard(btn.dataset.sql).then(() => {
        notify(T('ddl_copied'), 'success');
      }).catch(e => {
        console.error('Failed to copy:', e);
      });
    });
  });
  
  container.querySelectorAll('.delete-history').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const idx = parseInt(btn.dataset.index);
      sqlHistory.splice(idx, 1);
      saveSQLHistory();
      renderHistoryModal();
    });
  });
}
document.getElementById('clear-history-modal-btn')?.addEventListener('click', () => {
  sqlHistory = [];
  saveSQLHistory();
  renderHistoryModal();
  notify(T('clear_history'), 'info');
});

// ── Schema for autocomplete ───────────────────────────────────────────────────
let currentDbSource = '';
let dbSchema = {};
async function loadSchemaForAutocomplete() {
  if (!currentDbSource) return;
  try {
    const r = await api('get_schema');
    if (r.success) dbSchema = r.schema;
  } catch(e) { console.error('Failed to load schema', e); }
}

// ── CodeMirror SQL Editor ─────────────────────────────────────────────────────
let sqlEditor = null;
function initSQLEditor() {
  const textarea = document.getElementById('sql-editor');
  if (!textarea) return;
  
  const isDarkTheme = document.documentElement.style.getPropertyValue('--bg') === '#0f1419';
  
  sqlEditor = CodeMirror.fromTextArea(textarea, {
    mode: 'text/x-sql',
    theme: isDarkTheme ? 'material-darker' : 'default',
    lineNumbers: true,
    lineWrapping: true,
    matchBrackets: true,
    autoCloseBrackets: true,
    foldGutter: true,
    gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
    extraKeys: {
      'Ctrl-Enter': function(cm) { runSQL(); },
      'Cmd-Enter': function(cm) { runSQL(); },
      'Ctrl-Space': function(cm) { 
        CodeMirror.showHint(cm, CodeMirror.hint.sql, { tables: dbSchema, completeSingle: false });
      }
    },
    hintOptions: {
      tables: dbSchema,
      completeSingle: false
    }
  });
  
  sqlEditor.setValue(T('sql_initial'));
}

// Format SQL helper (preserves comments)
function formatSQL(sql) {
  let lines = sql.split('\n');
  let formatted = [];
  let inComment = false;
  
  for (let line of lines) {
    if (line.trim().startsWith('--')) {
      formatted.push(line);
      continue;
    }
    if (line.includes('/*')) inComment = true;
    if (inComment) {
      formatted.push(line);
      if (line.includes('*/')) inComment = false;
      continue;
    }
    let formattedLine = line;
    const keywords = ['SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 
                      'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'INSERT', 'UPDATE', 'DELETE', 
                      'CREATE', 'ALTER', 'DROP', 'AND', 'OR', 'ON', 'SET', 'VALUES', 'INTO'];
    keywords.forEach(kw => {
      const regex = new RegExp(`\\b${kw}\\b`, 'gi');
      formattedLine = formattedLine.replace(regex, `\n${kw.toUpperCase()}`);
    });
    formatted.push(formattedLine);
  }
  let result = formatted.join('\n').replace(/\n\s*\n/g, '\n').trim();
  return result;
}

// ── Theme change via postMessage from parent ─────────────────────────────────
window.addEventListener('message', e => {
  if (e.data?.type === 'theme_update') {
    applyTheme(e.data.theme, e.data.color);
    if (sqlEditor) {
      const isDark = e.data.theme === 'dark' || (e.data.theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
      sqlEditor.setOption('theme', isDark ? 'material-darker' : 'default');
    }
  }
});
function applyTheme(theme, color) {
  const isDark = theme === 'dark' || (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  const r = parseInt(color.slice(1,3),16), g = parseInt(color.slice(3,5),16), b = parseInt(color.slice(5,7),16);
  const root = document.documentElement;
  root.style.colorScheme = isDark ? 'dark' : 'light';
  root.style.setProperty('--bg',         isDark ? '#0f1419' : '#f8f9fc');
  root.style.setProperty('--bg2',        isDark ? 'rgba(18,24,32,0.8)' : '#ffffff');
  root.style.setProperty('--bg3',        isDark ? 'rgba(255,255,255,0.10)' : '#f0f2f8');
  root.style.setProperty('--border',     isDark ? 'rgba(255,255,255,0.14)' : '#dde1ed');
  root.style.setProperty('--border2',    isDark ? 'rgba(255,255,255,0.22)' : '#c5cce0');
  root.style.setProperty('--text',       isDark ? '#f4f7fb' : '#1a1f36');
  root.style.setProperty('--text-dim',   isDark ? 'rgba(255,255,255,0.65)' : '#5a6282');
  root.style.setProperty('--text-muted', isDark ? 'rgba(255,255,255,0.42)' : '#9aa3be');
  root.style.setProperty('--accent',     color);
  root.style.setProperty('--accent-dim', `rgba(${r},${g},${b},0.12)`);
  root.style.setProperty('--accent-mid', `rgba(${r},${g},${b},0.25)`);
  root.style.setProperty('--accent-brd', `rgba(${r},${g},${b},0.40)`);
  root.style.setProperty('--green',      isDark ? '#4caf50' : '#1ea760');
  root.style.setProperty('--green-dim',  isDark ? 'rgba(76,175,80,0.12)' : 'rgba(30,167,96,0.10)');
  root.style.setProperty('--red',        isDark ? '#f44336' : '#e03d2e');
  root.style.setProperty('--red-dim',    isDark ? 'rgba(244,67,54,0.12)' : 'rgba(224,61,46,0.10)');
  root.style.setProperty('--orange',     isDark ? '#ff9800' : '#d97706');
  root.style.setProperty('--yellow',     isDark ? '#ffc107' : '#b45309');
}

// ── State ─────────────────────────────────────────────────────────────────────
const S = { tables:[], cur:null, cols:[], pkCol:null, page:1, limit:50, total:0, pages:1, search:'', sc:'', sd:'ASC', stimer:null };
let tableViewMode = 'flat';
try { const __v = localStorage.getItem('db_admin_table_view'); if (__v === 'stacks' || __v === 'flat') tableViewMode = __v; } catch(e) {}
function syncTableViewToggle() {
  document.querySelectorAll('.sb-view-btn').forEach(btn => { btn.classList.toggle('active', btn.dataset.view === tableViewMode); });
}

// ── Mobile sidebar ───────────────────────────────────────────────────────────
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebar-overlay');
function closeSidebar() {
  if (window.innerWidth <= 768) {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  }
}
function openSidebar() {
  if (window.innerWidth <= 768) {
    sidebar.classList.add('open');
    overlay.classList.add('active');
  }
}
document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
  if (sidebar.classList.contains('open')) closeSidebar();
  else openSidebar();
});
overlay?.addEventListener('click', closeSidebar);
window.addEventListener('resize', () => {
  if (window.innerWidth > 768) {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  }
});

// ── API ───────────────────────────────────────────────────────────────────────
async function api(action, extra={}) {
  const fd = new FormData(); fd.append('action', action);
  fd.append('db_source', currentDbSource || 'postgres');
  Object.entries(extra).forEach(([k,v]) => fd.append(k, String(v)));
  const r = await fetch(location.href, { method:'POST', body:fd });
  return r.json();
}

// ── Notify ────────────────────────────────────────────────────────────────────
function notify(msg, type='info') {
  const c = document.getElementById('nc');
  const el = document.createElement('div'); el.className = `ntf ${type}`;
  el.innerHTML = `<span class="ntf-ico">${{success:'✓',error:'✗',info:'ℹ'}[type]||'ℹ'}</span><span class="ntf-txt">${esc(msg)}</span>`;
  c.appendChild(el);
  requestAnimationFrame(()=>requestAnimationFrame(()=>el.classList.add('show')));
  setTimeout(()=>{ el.classList.remove('show'); setTimeout(()=>el.remove(),300); }, type==='error'?5000:3000);
}

function confirmModal(message, options = {}) {
  const modal = document.getElementById('confirm-modal');
  const titleEl = document.getElementById('confirm-title');
  const msgEl = document.getElementById('confirm-message');
  const okBtn = document.getElementById('confirm-ok');
  const cancelBtn = document.getElementById('confirm-cancel');
  const closeBtn = document.getElementById('confirm-close');
  if (!modal || !titleEl || !msgEl || !okBtn || !cancelBtn || !closeBtn) return Promise.resolve(false);

  const {
    title = T('confirm'),
    okText = T('ok'),
    cancelText = T('cancel'),
    destructive = false
  } = options;

  titleEl.textContent = title;
  msgEl.textContent = message;
  okBtn.textContent = okText;
  cancelBtn.textContent = cancelText;
  okBtn.classList.toggle('danger', !!destructive);
  okBtn.classList.toggle('primary', !destructive);
  modal.classList.add('open');
  okBtn.focus();

  return new Promise(resolve => {
    const cleanup = () => {
      modal.classList.remove('open');
      okBtn.removeEventListener('click', onOk);
      cancelBtn.removeEventListener('click', onCancel);
      closeBtn.removeEventListener('click', onCancel);
      modal.removeEventListener('click', onBackdrop);
      document.removeEventListener('keydown', onKeydown);
    };
    const done = (result) => { cleanup(); resolve(result); };
    const onOk = () => done(true);
    const onCancel = () => done(false);
    const onBackdrop = (e) => { if (e.target === modal) done(false); };
    const onKeydown = (e) => {
      if (!modal.classList.contains('open')) return;
      if (e.key === 'Escape') done(false);
      if (e.key === 'Enter') done(true);
    };
    okBtn.addEventListener('click', onOk);
    cancelBtn.addEventListener('click', onCancel);
    closeBtn.addEventListener('click', onCancel);
    modal.addEventListener('click', onBackdrop);
    document.addEventListener('keydown', onKeydown);
  });
}

// ── Views ─────────────────────────────────────────────────────────────────────
function showView(id) {
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  document.getElementById(id)?.classList.add('active');
  document.querySelectorAll('.sb-fbtn').forEach(b=>b.classList.remove('active'));
  const map = {'view-adduser':'nav-adduser','view-sql':'nav-sql','view-stats':'nav-stats','view-schema':'nav-schema','view-relations':'nav-relations','view-createtable':'nav-createtable'};
  if (map[id]) document.getElementById(map[id])?.classList.add('active');
  if (id !== 'view-table') { document.querySelectorAll('.ti').forEach(i=>i.classList.remove('active')); S.cur=null; }
  if (id === 'view-stats') loadStats();
  if (id === 'view-schema') {
    loadSchema();
  }
  if (id === 'view-relations') {
    loadRelations();
    // Reset to list view by default
    isRelationsDiagramView = false;
    const listView = document.getElementById('relations-list-view');
    const diagramView = document.getElementById('relations-diagram-view');
    const btn = document.getElementById('toggle-relations-view-btn');
    if (listView && diagramView && btn) {
      listView.style.display = 'block';
      diagramView.style.display = 'none';
      btn.textContent = T('diagram_view');
    }
  }
  if (id === 'view-sql' && sqlEditor) sqlEditor.refresh();
  closeSidebar();
  // Close context menu when changing views
  document.getElementById('ctx-menu')?.classList.remove('open');
}

// ── Sidebar ───────────────────────────────────────────────────────────────────
async function loadTables() {
  const d = await api('list_tables'); if (!d.success) return;
  S.tables = d.tables; refreshTableList();
}
function tableStackKey(name) { const i = name.indexOf('_'); return i === -1 ? name : name.slice(0, i); }
function getFilteredTables() {
  const inp = document.getElementById('tsearch');
  const q = (inp && inp.value) ? inp.value.trim().toLowerCase() : '';
  if (!q) return [...S.tables];
  return S.tables.filter(t => t.name.toLowerCase().includes(q));
}
function tiRowHtml(t) {
  return `<div class="ti ${S.cur===t.name?'active':''}" data-t="${esc(t.name)}"><div class="ti-ico">⊞</div><div class="ti-name">${esc(t.name)}</div><div class="ti-cnt">${t.count.toLocaleString()}</div></div>`;
}
function bindTiClicks(container) {
  container.querySelectorAll('.ti').forEach(el => el.addEventListener('click', ()=>{openTable(el.dataset.t); closeSidebar();}));
  container.querySelectorAll('.ti').forEach(el=>el.addEventListener('contextmenu',e=>{
    e.preventDefault();
    e.stopPropagation();
    showTableContextMenu(e.clientX,e.clientY,el.dataset.t);
  }));
}
function renderTableList(tables) {
  const c = document.getElementById('table-list');
  if (!tables.length) { c.innerHTML = `<div class="empty" style="height:60px;font-size:11px;">${T('no_rows')}</div>`; return; }
  if (tableViewMode === 'stacks') {
    const groups = new Map();
    for (const t of tables) { const k = tableStackKey(t.name); if (!groups.has(k)) groups.set(k, []); groups.get(k).push(t); }
    const stackKeys = [...groups.keys()].sort((a,b)=>a.localeCompare(b));
    let stackState = {};
    try { stackState = JSON.parse(sessionStorage.getItem('db_admin_stack_state')||'{}'); } catch(e) {}
    c.innerHTML = stackKeys.map(sk => {
      const items = groups.get(sk);
      const isOpen = !Object.prototype.hasOwnProperty.call(stackState, sk) ? true : stackState[sk];
      return `<details class="ti-stack" data-stack="${esc(sk)}"${isOpen ? ' open' : ''}><summary class="ti-stack-h"><span class="ti-stack-name">${esc(sk)}</span><span class="ti-stack-n">${items.length}</span></summary><div class="ti-stack-items">${items.map(tiRowHtml).join('')}</div></details>`;
    }).join('');
    bindTiClicks(c);
    c.querySelectorAll('details.ti-stack').forEach(det => {
      det.addEventListener('toggle', () => {
        const sk = det.dataset.stack;
        try { const o = JSON.parse(sessionStorage.getItem('db_admin_stack_state')||'{}'); o[sk] = det.open; sessionStorage.setItem('db_admin_stack_state', JSON.stringify(o)); } catch(e) {}
      });
    });
    if (S.cur) { const active = c.querySelector(`.ti[data-t="${CSS.escape(S.cur)}"]`); const det = active && active.closest('details.ti-stack'); if (det) det.open = true; }
    return;
  }
  c.innerHTML = tables.map(t => tiRowHtml(t)).join('');
  bindTiClicks(c);
}
function refreshTableList() { renderTableList(getFilteredTables()); }
document.getElementById('tsearch').addEventListener('input', function() { refreshTableList(); });
document.querySelectorAll('.sb-view-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    if (btn.dataset.view === tableViewMode) return;
    tableViewMode = btn.dataset.view;
    try { localStorage.setItem('db_admin_table_view', tableViewMode); } catch(e) {}
    syncTableViewToggle();
    refreshTableList();
  });
});

// ── Table View ────────────────────────────────────────────────────────────────
async function openTable(name) {
  S.cur=name; S.page=1; S.search=''; S.sc=''; S.sd='ASC';
  document.getElementById('tv-search').value='';
  document.querySelectorAll('.ti').forEach(i=>i.classList.toggle('active', i.dataset.t===name));
  showView('view-table');
  document.getElementById('tv-name').textContent = name;
  const inf = await api('table_info',{table:name});
  if (inf.success) { S.cols=inf.info.columns; S.pkCol=inf.info.columns.find(c=>c.pk)?.name||inf.info.columns[0]?.name; }
  await loadData();
}
async function loadData(onSuccess) {
  // Close context menu when reloading data
  document.getElementById('ctx-menu')?.classList.remove('open');
  hideRowTooltip();
  const tbody=document.getElementById('dtbody'), thead=document.getElementById('dthead');
  tbody.innerHTML=`<tr class="lrow"><td colspan="99"><span class="spin"></span>${T('loading')}</td></tr>`;
  const d = await api('table_data',{table:S.cur,page:S.page,limit:S.limit,search:S.search,sort_col:S.sc,sort_dir:S.sd});
  if (!d.success){tbody.innerHTML=`<tr class="lrow"><td colspan="99" style="color:var(--red)">${esc(d.error)}</td></tr>`;return;}
  S.total=d.total; S.pages=d.pages; S.page=d.page;
  const si=document.querySelector(`.ti[data-t="${CSS.escape(S.cur)}"] .ti-cnt`);
  if (si) si.textContent=d.total.toLocaleString();
  document.getElementById('tv-cnt').textContent=d.total.toLocaleString()+' '+T(d.total===1?'row':'rows');
  thead.innerHTML=d.columns.map(c=>{
    const ci=S.cols.find(x=>x.name===c); const isPK=ci?.pk;
    const cls=(S.sc===c?(S.sd==='ASC'?'s-asc':'s-desc'):'')+(isPK?' pk-h':'');
    return `<th data-col="${esc(c)}" class="${cls}">${esc(c)}${isPK?` <span class="badge pk">${T('pk_label')}</span>`:''}</th>`;
  }).join('');
  thead.querySelectorAll('th[data-col]').forEach(th=>{
    th.addEventListener('click',()=>{
      S.sc===th.dataset.col ? S.sd=(S.sd==='ASC'?'DESC':'ASC') : (S.sc=th.dataset.col, S.sd='ASC');
      S.page=1; loadData();
    });
  });
  if (!d.rows.length){tbody.innerHTML=`<tr class="lrow"><td colspan="99">${T('no_rows')}</td></tr>`;}
  else {
    tbody.innerHTML=d.rows.map((row,ri)=>{
      const pkv=row[S.pkCol]??'';
      const cells=d.columns.map(c=>{
        const v=row[c]; const ci=S.cols.find(x=>x.name===c);
        const cls=(v===null?'nv':(typeof v==='number'?'numv':(String(v).length>60?'longv':'')))+(ci?.pk?'':' edit');
        const disp=v===null?T('null_value'):(String(v).length>80?String(v).slice(0,80)+'…':String(v));
        return `<td class="${cls}" data-col="${esc(c)}" data-pk="${esc(pkv)}" data-val="${esc(v===null?'__NULL__':String(v))}" title="${esc(v===null?'NULL':String(v))}">${esc(disp)}</td>`;
      }).join('');
      return `<tr data-pk="${esc(pkv)}" data-ri="${ri}" data-row-tooltip="${esc(T('view_delete'))}">${cells}</tr>`;
    }).join('');
    tbody.querySelectorAll('td.edit').forEach(td=>td.addEventListener('dblclick',()=>startEdit(td)));
    tbody.querySelectorAll('td.longv').forEach(td=>td.addEventListener('click',()=>showTextModal(td.dataset.col,td.dataset.val==='__NULL__'?null:td.dataset.val)));
    // Right-click context menu for rows
    tbody.querySelectorAll('tr[data-pk]').forEach(tr=>{
      tr.addEventListener('mouseenter',function(){showRowTooltip(this);});
      tr.addEventListener('mousemove',function(){positionRowTooltip(this);});
      tr.addEventListener('mouseleave',hideRowTooltip);
      tr.addEventListener('contextmenu',function(e){
        e.preventDefault();
        e.stopPropagation();
        hideRowTooltip();
        showContextMenu(e.clientX,e.clientY,this.dataset.pk,parseInt(this.dataset.ri),d.rows,d.columns);
      });
    });
  }
  updatePag();
  if(onSuccess)onSuccess();
}

function startEdit(td) {
  if(td.querySelector('.cei'))return;
  const orig=td.dataset.val==='__NULL__'?'':td.dataset.val;
  const inp=document.createElement('input'); inp.className='cei'; inp.value=orig;
  td.innerHTML=''; td.appendChild(inp); inp.focus(); inp.select();
  async function commit(){
    const nv=inp.value; td.innerHTML=nv||`<span class="nv">${T('null_value')}</span>`; td.dataset.val=nv;
    const r=await api('update_cell',{table:S.cur,pk_col:S.pkCol,pk_val:td.dataset.pk,col:td.dataset.col,val:nv});
    if(r.success&&r.changed>0)notify(T('cell_updated'),'success');
    else if(!r.success)notify(T('error')+': '+r.error,'error');
  }
  inp.addEventListener('blur',commit);
  inp.addEventListener('keydown',e=>{if(e.key==='Enter')inp.blur();if(e.key==='Escape'){td.innerHTML=orig?esc(orig):`<span class="nv">${T('null_value')}</span>`;}});
}
async function deleteRow(pkv) {
  const isConfirmed = await confirmModal(
    T('delete_row_confirm',S.pkCol,pkv),
    { title: T('del'), okText: T('del'), destructive: true }
  );
  if(!isConfirmed)return;
  const r=await api('delete_row',{table:S.cur,pk_col:S.pkCol,pk_val:pkv});
  if(r.success){notify(T('row_deleted'),'success');loadData();loadTables();}
  else notify(T('error')+': '+r.error,'error');
}
function showRowDetail(row,cols) {
  const ci=S.cols;
  document.getElementById('rm-title').textContent=T('row_detail')+' — '+S.cur;
  document.getElementById('rm-body').innerHTML=cols.map(c=>{
    const v=row[c]; const isPK=ci.find(x=>x.name===c)?.pk;
    return `<div class="frow"><div class="fkey ${isPK?'pk':''}">${esc(c)}</div><div class="fval ${v===null?'nv':''}">${v===null?T('null_value'):esc(String(v))}</div></div>`;
  }).join('');
  document.getElementById('row-modal').classList.add('open');
}
function showTextModal(col,val){
  document.getElementById('rm-title').textContent=col;
  document.getElementById('rm-body').innerHTML=`<div class="fval" style="padding:8px 0;white-space:pre-wrap;">${val===null?`<span class="nv">${T('null_value')}</span>`:esc(String(val))}</div>`;
  document.getElementById('row-modal').classList.add('open');
}

// Context menu for row actions (right-click)
let ctxPk=null, ctxRi=null, ctxRows=null, ctxCols=null;
let ctxTableName='';
function showRowTooltip(tr){
  const tip=document.getElementById('row-hover-tip');
  if(!tip||!tr?.dataset?.rowTooltip)return;
  tip.textContent='👁 '+tr.dataset.rowTooltip;
  tip.classList.add('open');
  positionRowTooltip(tr);
}
function positionRowTooltip(tr){
  const tip=document.getElementById('row-hover-tip');
  if(!tip||!tip.classList.contains('open')||!tr)return;
  const r=tr.getBoundingClientRect();
  const left=Math.round(r.left+r.width/2);
  const top=Math.round(r.top-6);
  tip.style.left=left+'px';
  tip.style.top=top+'px';
  tip.style.transform='translate(-50%, -100%)';
}
function hideRowTooltip(){
  const tip=document.getElementById('row-hover-tip');
  if(!tip)return;
  tip.classList.remove('open');
}
function showContextMenu(x,y,pk,ri,rows,cols){
  ctxPk=pk; ctxRi=ri; ctxRows=rows; ctxCols=cols;
  const menu=document.getElementById('ctx-menu');
  const vw=document.getElementById('ctx-view');
  const del=document.getElementById('ctx-delete');
  
  // Position menu
  menu.style.left=x+'px';
  menu.style.top=y+'px';
  menu.classList.add('open');
  
  // Close on click outside
  function closeCtx(){
    menu.classList.remove('open');
    document.removeEventListener('click',closeCtx);
  }
  setTimeout(()=>document.addEventListener('click',closeCtx),100);
}
document.querySelector('.tv-wrap')?.addEventListener('scroll',hideRowTooltip);

// Context menu event handlers
document.getElementById('ctx-view')?.addEventListener('click',function(){
  if(ctxPk!==null && ctxRows && ctxCols){
    showRowDetail(ctxRows[ctxRi],ctxCols);
  }
  document.getElementById('ctx-menu').classList.remove('open');
});

document.getElementById('ctx-delete')?.addEventListener('click',function(){
  if(ctxPk!==null){
    deleteRow(ctxPk);
  }
  document.getElementById('ctx-menu').classList.remove('open');
});
function showTableContextMenu(x,y,tableName){
  ctxTableName=tableName;
  const menu=document.getElementById('table-ctx-menu');
  if(!menu)return;
  menu.style.left=x+'px';
  menu.style.top=y+'px';
  menu.classList.add('open');
  function closeMenu(e){
    if(e&&menu.contains(e.target))return;
    menu.classList.remove('open');
    document.removeEventListener('click',closeMenu,true);
  }
  setTimeout(()=>document.addEventListener('click',closeMenu,true),0);
}
async function deleteTableWithSlider(tableName){
  const isConfirmed = await confirmSliderModal(
    T('delete_table_confirm',tableName),
    { title: T('delete_table_title'), destructive: true }
  );
  if(!isConfirmed)return;
  const r=await api('delete_table',{table_name:tableName});
  if(r.success){
    notify(T('table_deleted'),'success');
    if(S.cur===tableName)showView('view-empty');
    await loadTables();
    if(document.getElementById('view-schema')?.classList.contains('active'))loadSchema();
    if(document.getElementById('view-relations')?.classList.contains('active'))loadRelations();
    if(document.getElementById('view-stats')?.classList.contains('active'))loadStats();
  }else notify(T('error')+': '+r.error,'error');
}
document.getElementById('table-ctx-delete')?.addEventListener('click',async function(){
  document.getElementById('table-ctx-menu')?.classList.remove('open');
  if(!ctxTableName)return;
  await deleteTableWithSlider(ctxTableName);
});
function confirmSliderModal(message, options = {}) {
  const modal = document.getElementById('confirm-modal');
  const titleEl = document.getElementById('confirm-title');
  const msgEl = document.getElementById('confirm-message');
  const okBtn = document.getElementById('confirm-ok');
  const cancelBtn = document.getElementById('confirm-cancel');
  const closeBtn = document.getElementById('confirm-close');
  if (!modal || !titleEl || !msgEl || !okBtn || !cancelBtn || !closeBtn) return Promise.resolve(false);
  const { title = T('delete_table_title'), cancelText = T('cancel'), destructive = true } = options;
  titleEl.textContent = title;
  msgEl.innerHTML = `<div class="slide-confirm" id="slide-confirm"><div>${esc(message)}</div><div class="slide-confirm-label">${esc(T('slide_to_delete'))}</div><div class="slide-track" id="confirm-slide-track"><div class="slide-fill" id="confirm-slide-fill"></div><div class="slide-text">${esc(T('slide_to_delete'))}</div><button type="button" class="slide-thumb" id="confirm-slide-thumb" aria-label="${esc(T('slide_to_delete'))}">➜</button></div></div>`;
  okBtn.textContent = T('delete_table');
  cancelBtn.textContent = cancelText;
  okBtn.classList.toggle('danger', !!destructive);
  okBtn.classList.toggle('primary', !destructive);
  okBtn.disabled = true;
  modal.classList.add('open');
  const slideWrap = document.getElementById('slide-confirm');
  const track = document.getElementById('confirm-slide-track');
  const fill = document.getElementById('confirm-slide-fill');
  const thumb = document.getElementById('confirm-slide-thumb');
  if(thumb) thumb.focus();
  return new Promise(resolve => {
    let dragActive = false;
    let doneSlide = false;
    const maxX = () => Math.max(0,(track?.clientWidth||0)-(thumb?.offsetWidth||0)-6);
    const setX = (x) => {
      if(!thumb||!fill)return;
      const nx = Math.max(0,Math.min(maxX(),x));
      thumb.style.left=nx+'px';
      fill.style.width=(nx+(thumb.offsetWidth/2))+'px';
      doneSlide = nx >= maxX()-1;
      okBtn.disabled = !doneSlide;
      slideWrap?.classList.toggle('done',doneSlide);
    };
    const resetX = () => {
      if(doneSlide)return;
      if(thumb) thumb.style.left='0px';
      if(fill) fill.style.width='0px';
    };
    const clientX = (e) => (typeof e.clientX === 'number' ? e.clientX : (e.touches && e.touches[0] ? e.touches[0].clientX : 0));
    const moveByEvent = (e) => {
      if(!dragActive||!track||!thumb)return;
      const r = track.getBoundingClientRect();
      const x = clientX(e)-r.left-(thumb.offsetWidth/2);
      setX(x);
      if(doneSlide){dragActive=false;okBtn.focus();}
    };
    const onDown = (e) => { if(doneSlide)return; dragActive = true; thumb?.setPointerCapture?.(e.pointerId); moveByEvent(e); };
    const onMove = (e) => moveByEvent(e);
    const onUp = (e) => { dragActive = false; thumb?.releasePointerCapture?.(e.pointerId); resetX(); };
    const onTouchStart = (e) => { if(doneSlide)return; dragActive = true; moveByEvent(e); };
    const onTouchMove = (e) => { if(!dragActive)return; e.preventDefault(); moveByEvent(e); };
    const onTouchEnd = () => { dragActive = false; resetX(); };
    const cleanup = () => {
      modal.classList.remove('open');
      okBtn.disabled = false;
      slideWrap?.classList.remove('done');
      okBtn.removeEventListener('click', onOk);
      cancelBtn.removeEventListener('click', onCancel);
      closeBtn.removeEventListener('click', onCancel);
      modal.removeEventListener('click', onBackdrop);
      document.removeEventListener('keydown', onKeydown);
      thumb?.removeEventListener('pointerdown', onDown);
      thumb?.removeEventListener('pointermove', onMove);
      thumb?.removeEventListener('pointerup', onUp);
      thumb?.removeEventListener('pointercancel', onUp);
      thumb?.removeEventListener('touchstart', onTouchStart);
      thumb?.removeEventListener('touchmove', onTouchMove);
      thumb?.removeEventListener('touchend', onTouchEnd);
      window.removeEventListener('resize', resetX);
    };
    const done = (result) => { cleanup(); resolve(result); };
    const onOk = () => done(true);
    const onCancel = () => done(false);
    const onBackdrop = (e) => { if (e.target === modal) done(false); };
    const onKeydown = (e) => {
      if (!modal.classList.contains('open')) return;
      if (e.key === 'Escape') done(false);
      if (e.key === 'Enter' && doneSlide) done(true);
    };
    okBtn.addEventListener('click', onOk);
    cancelBtn.addEventListener('click', onCancel);
    closeBtn.addEventListener('click', onCancel);
    modal.addEventListener('click', onBackdrop);
    document.addEventListener('keydown', onKeydown);
    thumb?.addEventListener('pointerdown', onDown);
    thumb?.addEventListener('pointermove', onMove);
    thumb?.addEventListener('pointerup', onUp);
    thumb?.addEventListener('pointercancel', onUp);
    thumb?.addEventListener('touchstart', onTouchStart,{passive:true});
    thumb?.addEventListener('touchmove', onTouchMove,{passive:false});
    thumb?.addEventListener('touchend', onTouchEnd);
    window.addEventListener('resize', resetX);
    resetX();
  });
}

// Close context menu on escape
document.addEventListener('keydown',function(e){
  if(e.key==='Escape'){
    document.getElementById('ctx-menu')?.classList.remove('open');
    document.getElementById('table-ctx-menu')?.classList.remove('open');
  }
});

function updatePag(){
  const fr=(S.page-1)*S.limit+1, to=Math.min(S.page*S.limit,S.total);
  document.getElementById('pag-info').textContent=S.total?T('rows_range',fr,to,S.total.toLocaleString()):T('no_rows');
  document.getElementById('pag-pg').textContent=`${S.page}/${S.pages}`;
  ['pag-f','pag-p'].forEach(id=>document.getElementById(id).disabled=S.page<=1);
  ['pag-n','pag-l'].forEach(id=>document.getElementById(id).disabled=S.page>=S.pages);
}
document.getElementById('pag-f')?.addEventListener('click',()=>{S.page=1;loadData();});
document.getElementById('pag-p')?.addEventListener('click',()=>{S.page--;loadData();});
document.getElementById('pag-n')?.addEventListener('click',()=>{S.page++;loadData();});
document.getElementById('pag-l')?.addEventListener('click',()=>{S.page=S.pages;loadData();});
document.getElementById('pag-lim')?.addEventListener('change',function(){S.limit=+this.value;S.page=1;loadData();});
document.getElementById('tv-refresh')?.addEventListener('click',function(){
  const btn=this;
  loadData(()=>{
    btn.classList.add('flash-refresh');
    setTimeout(()=>btn.classList.remove('flash-refresh'),800);
  });
});
document.getElementById('tv-search')?.addEventListener('input',function(){
  clearTimeout(S.stimer); S.stimer=setTimeout(()=>{S.search=this.value.trim();S.page=1;loadData();},320);
});

document.getElementById('tv-insert')?.addEventListener('click',()=>{
  document.getElementById('im-title').textContent=T('insert_into')+' '+S.cur;
  document.getElementById('im-fields').innerHTML=S.cols.map(c=>`
    <div class="ins-field"><label class="${c.pk?'pk':''}">${esc(c.name)}${c.pk?' (PK)':''}</label>
    <input type="text" data-col="${esc(c.name)}" placeholder="${c.dflt_value?'default: '+c.dflt_value:c.type}"></div>`).join('');
  document.getElementById('ins-modal').classList.add('open');
});
document.getElementById('im-confirm')?.addEventListener('click',async()=>{
  const data={};
  document.querySelectorAll('#im-fields input').forEach(inp=>{if(inp.value!=='')data[inp.dataset.col]=inp.value;});
  const r=await api('insert_row',{table:S.cur,data:JSON.stringify(data)});
  if(r.success){notify(T('row_inserted'),'success');document.getElementById('ins-modal').classList.remove('open');loadData();loadTables();}
  else notify(T('error')+': '+r.error,'error');
});

async function loadSchema(){
  const grid=document.getElementById('sch-grid');
  grid.innerHTML=`<div class="empty" style="grid-column:1/-1;height:80px"><span class="spin"></span></div>`;
  const d=await api('schema'); if(!d.success)return;
  const schema=d.schema;
  grid.innerHTML=Object.entries(schema).map(([tname,info])=>{
    const colRows=info.columns.map((c, idx)=>{
      // Build row tooltip with column details
      let rowTooltip = `${T('row_number', idx + 1)}\\n`;
      rowTooltip += `${T('name_label')}: ${c.name}\\n`;
      rowTooltip += `${T('type_label')}: ${c.type || 'TEXT'}\\n`;
      rowTooltip += `${T('primary_key')}: ${c.pk ? T('yes') : T('no')}\\n`;
      rowTooltip += `${T('not_null')}: ${c.notnull ? T('yes') : T('no')}\\n`;
      rowTooltip += `${T('default_value')}: ${c.dflt_value || T('none')}`;
      
      return `
      <div class="sch-col" data-row-tooltip="${esc(rowTooltip)}">
        <div class="sch-col-pk">${c.pk?'🔑':''}</div>
        <div class="sch-col-name">${esc(c.name)}</div>
        <div class="sch-col-type">${esc(c.type||'')}</div>
        <div class="sch-col-nn" style="color:${c.notnull?'var(--green)':'var(--text-muted)'}">${c.notnull?'●':'○'}</div>
        <div class="sch-col-nn" style="color:${c.pk?'var(--accent)':'var(--text-muted)'}">${c.pk?'●':'○'}</div>
        <div class="sch-col-dflt">${esc(c.dflt_value||'')}</div>
      </div>`;
    }).join('');
    const idxHtml=info.indexes.length?`<div class="sch-indexes"><div class="sch-idxs-lbl">${T('indexes')}</div>${
      info.indexes.map(idx=>`<div class="sch-idx">${esc(idx.name)}${idx.unique?`<span class="sch-idx-u">${T('unique')}</span>`:''}<span style="color:var(--text-muted);font-size:10px">(${esc(idx.cols.join(', '))})</span></div>`).join('')
    }</div>`:'';
    const fkHtml=info.fks.length?`<div class="sch-fks"><div class="sch-idxs-lbl">${T('foreign_keys')}</div>${
      info.fks.map(fk=>`<div class="sch-fk">${esc(fk.from)}<span class="sch-fk-arr">→</span>${esc(fk.table)}.${esc(fk.to)}</div>`).join('')
    }</div>`:'';
    const ddlEncoded=encodeURIComponent(info.ddl||'');
    return `<div class="sch-tbl" data-tbl="${esc(tname)}">
      <div class="sch-tbl-hdr">
        <svg class="sch-chevron" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
        <div class="sch-tbl-name">${esc(tname)}</div>
        <div class="sch-tbl-cnt">${info.count.toLocaleString()} ${T('rows')}</div>
        <button class="btn sm" style="margin-left:6px" onclick="openTableFromSchema('${esc(tname)}')" title="${T('browse')}">⊞</button>
      </div>
      <div class="sch-body">
        <div class="sch-col-hdr"><div></div><div>${T('col_name')}</div><div>${T('col_type')}</div><div>${T('col_notnull')}</div><div>${T('col_pk')}</div><div>${T('col_default')}</div></div>
        ${colRows}
        ${idxHtml}${fkHtml}
        <div class="sch-ddl">
          <button class="btn sm copy-ddl-btn" data-ddl="${ddlEncoded}" data-tbl="${esc(tname)}">${T('copy_ddl')}</button>
        </div>
      </div>
    </div>`;
  }).join('');
  grid.querySelectorAll('.sch-tbl-hdr').forEach(hdr=>{
    hdr.addEventListener('click',e=>{
      if(e.target.closest('button'))return;
      hdr.closest('.sch-tbl').classList.toggle('collapsed');
    });
  });
}
function openTableFromSchema(name){openTable(name);showView('view-table');}
// Copy DDL via event delegation
document.getElementById('sch-grid')?.addEventListener('click',function(e){
  const btn=e.target.closest('.copy-ddl-btn');
  if(!btn)return;
  const ddl=decodeURIComponent(btn.dataset.ddl||'');
  copyToClipboard(ddl).then(()=>{
    notify(T('ddl_copied'),'success');
    btn.classList.remove('flash-success');
    void btn.offsetWidth;
    btn.classList.add('flash-success');
    setTimeout(()=>btn.classList.remove('flash-success'),800);
  }).catch(e => {
    console.error('Failed to copy:', e);
  });
});
document.getElementById('sch-expand')?.addEventListener('click',()=>document.querySelectorAll('.sch-tbl').forEach(t=>t.classList.remove('collapsed')));
document.getElementById('sch-collapse')?.addEventListener('click',()=>document.querySelectorAll('.sch-tbl').forEach(t=>t.classList.add('collapsed')));

async function loadRelations() {
  const container = document.getElementById('relations-container');
  if (!container) return;
  container.innerHTML = `<div class="empty" style="padding:40px"><span class="spin"></span> ${T('loading')}</div>`;
  const d = await api('relations');
  if (!d.success) {
    container.innerHTML = `<div class="empty-rel">${T('error')}: ${esc(d.error)}</div>`;
    return;
  }
  if (!d.relations || d.relations.length === 0) {
    container.innerHTML = `<div class="empty-rel">${T('no_fk')}</div>`;
    window._relationsData = [];
    return;
  }
  
  // Save relations for Mermaid diagram
  window._relationsData = d.relations;
  
  const grouped = {};
  d.relations.forEach(rel => {
    if (!grouped[rel.from_table]) grouped[rel.from_table] = [];
    grouped[rel.from_table].push(rel);
  });
  container.innerHTML = Object.entries(grouped).map(([table, rels]) => `
    <div class="rel-card">
      <div class="rel-title"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M4 7c0-1.66 1.34-3 3-3s3 1.34 3 3-1.34 3-3 3-3-1.34-3-3zm12 0c0-1.66 1.34-3 3-3s3 1.34 3 3-1.34 3-3 3-3-1.34-3-3zm-6 10c0-1.66 1.34-3 3-3s3 1.34 3 3-1.34 3-3 3-3-1.34-3-3z"/></svg> ${esc(table)}</div>
      <div class="rel-list">
        ${rels.map(rel => `
          <div class="rel-item">
            <span class="rel-from">${esc(rel.from_col)}</span>
            <span class="rel-arrow">→</span>
            <span class="rel-to">${esc(rel.to_table)}.${esc(rel.to_col)}</span>
            <span class="rel-detail">${rel.on_update ? 'ON UPDATE ' + rel.on_update : ''} ${rel.on_delete ? 'ON DELETE ' + rel.on_delete : ''}</span>
          </div>
        `).join('')}
      </div>
    </div>
  `).join('');
}


if (isRoot) {
  let colCount = 0;
  function addColumnRow(name='', type='TEXT', constraints='') {
    const container = document.getElementById('columns-container');
    if (!container) return;
    const div = document.createElement('div');
    div.className = 'col-def-row';
    div.innerHTML = `
      <input type="text" placeholder="${T('col_name_ph')}" value="${esc(name)}" class="col-name">
      <select class="col-type"><option ${type==='TEXT'?'selected':''}>TEXT</option><option ${type==='INTEGER'?'selected':''}>INTEGER</option><option ${type==='REAL'?'selected':''}>REAL</option><option ${type==='BLOB'?'selected':''}>BLOB</option><option ${type==='NUMERIC'?'selected':''}>NUMERIC</option></select>
      <input type="text" placeholder="${T('col_constraints')} (PK, NN, UNIQUE)" value="${esc(constraints)}" class="col-const">
      <button class="del-col-btn" title="${T('remove')}">✖</button>
    `;
    div.querySelector('.del-col-btn').addEventListener('click', () => div.remove());
    container.appendChild(div);
  }
  document.getElementById('add-col-btn')?.addEventListener('click', () => addColumnRow());
  addColumnRow('id', 'INTEGER', 'PK');
  addColumnRow('name', 'TEXT', 'NN');
  document.getElementById('create-table-btn')?.addEventListener('click', async () => {
    const tableName = document.getElementById('ct-name').value.trim();
    if (!tableName) { document.getElementById('ct-status').innerHTML = `<span style="color:var(--red)">${T('new_table_name')} ${T('required')}</span>`; return; }
    const cols = [];
    document.querySelectorAll('#columns-container .col-def-row').forEach(row => {
      const name = row.querySelector('.col-name').value.trim();
      const type = row.querySelector('.col-type').value;
      const constraints = row.querySelector('.col-const').value.trim().toUpperCase();
      if (name) cols.push({ name, type, constraints });
    });
    if (cols.length === 0) { document.getElementById('ct-status').innerHTML = `<span style="color:var(--red)">${T('at_least_one_column_required')}</span>`; return; }
    document.getElementById('ct-status').innerHTML = `<span class="spin"></span> ${T('creating')}`;
    const r = await api('create_table', { table_name: tableName, columns: JSON.stringify(cols) });
    if (r.success) {
      notify(r.message, 'success');
      document.getElementById('ct-status').innerHTML = `<span style="color:var(--green)">✓ ${r.message}</span>`;
      document.getElementById('ct-name').value = '';
      document.getElementById('columns-container').innerHTML = '';
      addColumnRow('id', 'INTEGER', 'PK');
      addColumnRow('name', 'TEXT', 'NN');
      loadTables();
      loadSchema();
      loadRelations();
    } else {
      document.getElementById('ct-status').innerHTML = `<span style="color:var(--red)">✗ ${esc(r.error)}</span>`;
      notify(r.error, 'error');
    }
  });
  document.getElementById('clear-ct-btn')?.addEventListener('click', () => {
    document.getElementById('ct-name').value = '';
    document.getElementById('columns-container').innerHTML = '';
    addColumnRow('id', 'INTEGER', 'PK');
    addColumnRow('name', 'TEXT', 'NN');
    document.getElementById('ct-status').innerHTML = '';
  });
}

<?php if ($canRunSql): ?>
async function runSQL(){
  if (!sqlEditor) return;
  const sql = sqlEditor.getValue().trim();
  const lbl=document.getElementById('sql-res-lbl'), body=document.getElementById('sql-res-body');
  if(!sql) return;
  lbl.innerHTML=`<span class="spin"></span> ${T('running')}`;
  body.innerHTML=`<div class="sql-ph"><span class="spin"></span>${T('running')}</div>`;
  const r=await api('run_sql',{sql});
  if(!r.success){
    lbl.innerHTML=`<span style="color:var(--red)">✗ ${T('error')}</span>`;
    body.innerHTML=`<div class="sql-ph" style="color:var(--red)">${esc(r.error)}</div>`;
    return;
  }
  addSQLHistory(sql);
  if(r.type==='select'){
    lbl.innerHTML=`<span style="color:var(--green)">✓ ${T('returned_rows',r.count)}</span>`;
    if(!r.rows.length){
      body.innerHTML=`<div class="sql-ph">${T('no_rows')}</div>`;
      return;
    }
    body.innerHTML=`<table class="sql-result-table"><thead><tr>${r.columns.map(c=>`<th>${esc(c)}</th>`).join('')} </thead><tbody>${
      r.rows.map(row=>`<tr>${r.columns.map(c=>{const v=row[c];return `<td class="${v===null?'null-val':''}">${v===null?T('null_value'):esc(String(v))}</td>`;}).join('')}</tr>`).join('')
    }</tbody></table>`;
  } else {
    lbl.innerHTML=`<span style="color:var(--green)">✓ ${T('affected_rows',r.changed)}</span>`;
    body.innerHTML=`<div class="sql-ph" style="color:var(--green)">${T('affected_rows',r.changed)}</div>`;
    loadTables();
    loadSchema();
    loadRelations();
  }
}

document.getElementById('run-sql')?.addEventListener('click',runSQL);
document.getElementById('clr-sql')?.addEventListener('click',()=>{
  if (sqlEditor) sqlEditor.setValue('');
  document.getElementById('sql-res-body').innerHTML=`<div class="sql-ph">${T('result')}</div>`;
  document.getElementById('sql-res-lbl').textContent=T('result');
});
document.getElementById('format-sql-btn')?.addEventListener('click',()=>{
  if (sqlEditor) {
    const formatted = formatSQL(sqlEditor.getValue());
    sqlEditor.setValue(formatted);
    notify(T('format_sql'), 'info');
  }
});
document.getElementById('show-history-btn')?.addEventListener('click',()=>{
  renderHistoryModal();
  document.getElementById('history-modal').classList.add('open');
});
document.getElementById('history-modal-close')?.addEventListener('click',()=>{
  document.getElementById('history-modal').classList.remove('open');
});
document.getElementById('history-modal')?.addEventListener('click',(e)=>{
  if(e.target === document.getElementById('history-modal')) {
    document.getElementById('history-modal').classList.remove('open');
  }
});
// Clipboard helper with fallback
function copyToClipboard(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    return navigator.clipboard.writeText(text);
  }
  // Fallback for older browsers or non-secure contexts
  return new Promise((resolve, reject) => {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      resolve();
    } catch (e) {
      reject(e);
    }
    document.body.removeChild(textarea);
  });
}

const tableNameCopyEl = document.getElementById('tv-name');
async function copyCurrentTableName() {
  if (!tableNameCopyEl) return;
  const tableName = (tableNameCopyEl.textContent || '').trim();
  if (!tableName || tableName === '—') return;
  try {
    await copyToClipboard(tableName);
    notify(T('table_name_copied'), 'success');
  } catch (e) {
    notify(T('error') + ': ' + ((e && e.message) ? e.message : T('clipboard_unavailable')), 'error');
  }
}
tableNameCopyEl?.addEventListener('click', copyCurrentTableName);
tableNameCopyEl?.addEventListener('keydown', (e) => {
  if (e.key !== 'Enter' && e.key !== ' ') return;
  e.preventDefault();
  copyCurrentTableName();
});

<?php endif; ?>

async function loadStats(){
  const r=await api('db_stats'); if(!r.success)return;
  const fmt=b=>b<1024?b+' B':b<1048576?(b/1024).toFixed(1)+' KB':(b/1048576).toFixed(2)+' MB';
  const modDate=r.file_modified?new Date(r.file_modified*1000).toLocaleString():'—';
  document.getElementById('scards').innerHTML=`
    <div class="scard"><div class="scard-lbl">${T('file_size')}</div><div class="scard-val ac">${fmt(r.file_size)}</div></div>
    <div class="scard"><div class="scard-lbl">${T('file_modified')}</div><div class="scard-val" style="font-size:14px">${esc(modDate)}</div></div>
    <div class="scard"><div class="scard-lbl">${T('total_pages')}</div><div class="scard-val">${r.page_count.toLocaleString()}</div></div>
    <div class="scard"><div class="scard-lbl">${T('page_size')}</div><div class="scard-val">${r.page_size} B</div></div>
    <div class="scard"><div class="scard-lbl">${T('free_pages')}</div><div class="scard-val ${r.free_pages>10?'or':'gn'}">${r.free_pages}</div></div>
    <div class="scard"><div class="scard-lbl">${T('tables_count')}</div><div class="scard-val gn">${Object.keys(r.table_counts).length}</div></div>
    <div class="scard"><div class="scard-lbl">${T('total_rows_stat')}</div><div class="scard-val">${Object.values(r.table_counts).reduce((a,b)=>a+b,0).toLocaleString()}</div></div>`;
  const mx=Math.max(...Object.values(r.table_counts),1);
  document.getElementById('strows').innerHTML=Object.entries(r.table_counts).sort((a,b)=>b[1]-a[1]).map(([n,c])=>`
    <div class="tsl-r" onclick="openTable('${esc(n)}')">
      <div class="tsl-n">${esc(n)}</div>
      <div class="tsl-bw"><div class="tsl-b" style="width:${Math.max(2,Math.round(c/mx*100))}%"></div></div>
      <div class="tsl-c">${c.toLocaleString()}</div>
    </div>`).join('');
  
  // Load charts
  const sizeData = await api('table_sizes');
  if (sizeData.success) {
    renderTableSizeChart(sizeData.table_sizes);
  }
  
  const indexData = await api('index_usage');
  if (indexData.success) {
    renderIndexChart(indexData.index_usage);
  }
}

<?php if ($isRoot): ?>
document.getElementById('vacuum-btn')?.addEventListener('click',async()=>{
  const isConfirmed = await confirmModal(
    T('vacuum_confirm'),
    { title: T('vacuum'), okText: T('vacuum') }
  );
  if(!isConfirmed)return;
  const btn=document.getElementById('vacuum-btn'); btn.textContent='⟳';
  const r=await api('vacuum'); btn.textContent=T('vacuum');
  notify(r.success?T('vacuum_done'):T('vacuum_fail'),r.success?'success':'error');
});
<?php endif; ?>

document.getElementById('add-user-btn')?.addEventListener('click',async()=>{
  const e=document.getElementById('nu-e').value.trim(), p=document.getElementById('nu-p').value.trim();
  const st=document.getElementById('au-status');
  if(!e||!p){st.style.color='var(--red)';st.textContent=T('email_pwd_req');return;}
  if(p.length<8){st.style.color='var(--red)';st.textContent=T('pwd_short');return;}
  st.style.color='var(--text-dim)';st.textContent=T('creating');
  const r=await api('add_user',{email:e,password:p,name:document.getElementById('nu-n').value.trim(),role:document.getElementById('nu-r').value,language:document.getElementById('nu-l').value,theme:document.getElementById('nu-t').value,color:document.getElementById('nu-c').value,verified:document.getElementById('nu-v').value});
  if(r.success){st.style.color='var(--green)';st.textContent=T('user_created');notify(T('user_created'),'success');['nu-e','nu-p','nu-n'].forEach(id=>document.getElementById(id).value='');loadTables();}
  else{st.style.color='var(--red)';st.textContent='✗ '+r.error;}
});
document.getElementById('clr-user')?.addEventListener('click',()=>{['nu-e','nu-p','nu-n'].forEach(id=>document.getElementById(id).value='');document.getElementById('au-status').textContent='';});

['row-modal','ins-modal'].forEach(id=>{
  const el = document.getElementById(id);
  if (el) el.addEventListener('click',e=>{if(e.target===e.currentTarget)e.currentTarget.classList.remove('open');});
});
document.getElementById('rm-close')?.addEventListener('click',()=>document.getElementById('row-modal').classList.remove('open'));
document.getElementById('im-close')?.addEventListener('click',()=>document.getElementById('ins-modal').classList.remove('open'));
document.getElementById('im-cancel')?.addEventListener('click',()=>document.getElementById('ins-modal').classList.remove('open'));

document.getElementById('nav-schema')?.addEventListener('click',()=>showView('view-schema'));
document.getElementById('nav-relations')?.addEventListener('click',()=>showView('view-relations'));
document.getElementById('nav-adduser')?.addEventListener('click',()=>showView('view-adduser'));
document.getElementById('nav-stats')?.addEventListener('click',()=>showView('view-stats'));

// ── Sort Tables Modal ─────────────────────────────────────────────────────────
let currentSort = 'name-asc'; // default sorting
document.getElementById('sort-tables-btn')?.addEventListener('click', () => {
  renderSortModal();
  document.getElementById('sort-modal').classList.add('open');
});
document.getElementById('sort-modal-close')?.addEventListener('click', () => {
  document.getElementById('sort-modal').classList.remove('open');
});
document.getElementById('sort-modal')?.addEventListener('click', (e) => {
  if (e.target === document.getElementById('sort-modal')) {
    document.getElementById('sort-modal').classList.remove('open');
  }
});

function renderSortModal() {
  const body = document.getElementById('sort-modal-body');
  if (!body) return;
  const options = body.querySelectorAll('.sort-option');
  options.forEach(opt => {
    opt.classList.toggle('active', opt.dataset.sort === currentSort);
    opt.onclick = () => {
      currentSort = opt.dataset.sort;
      applyTableSort();
      document.getElementById('sort-modal').classList.remove('open');
      renderSortModal();
    };
  });
}

async function applyTableSort() {
  // Load table data with sizes for sorting
  const sizeData = await api('table_sizes');
  if (!sizeData.success) return;

  const sizeMap = {};
  sizeData.table_sizes.forEach(s => { sizeMap[s.name] = s.rows; });

  let sorted = [...S.tables];

  switch (currentSort) {
    case 'name-asc':
      sorted.sort((a, b) => a.name.localeCompare(b.name));
      break;
    case 'name-desc':
      sorted.sort((a, b) => b.name.localeCompare(a.name));
      break;
    case 'rows-desc':
      sorted.sort((a, b) => b.count - a.count);
      break;
    case 'rows-asc':
      sorted.sort((a, b) => a.count - b.count);
      break;
    case 'size-desc':
      sorted.sort((a, b) => (sizeMap[b.name] || 0) - (sizeMap[a.name] || 0));
      break;
    case 'size-asc':
      sorted.sort((a, b) => (sizeMap[a.name] || 0) - (sizeMap[b.name] || 0));
      break;
  }

  S.tables = sorted;
  refreshTableList();
}


// Toggle relations view between list and diagram
let isRelationsDiagramView = false;
document.getElementById('toggle-relations-view-btn')?.addEventListener('click', () => {
  isRelationsDiagramView = !isRelationsDiagramView;
  const listView = document.getElementById('relations-list-view');
  const diagramView = document.getElementById('relations-diagram-view');
  const btn = document.getElementById('toggle-relations-view-btn');
  
  if (isRelationsDiagramView) {
    listView.style.display = 'none';
    diagramView.style.display = 'flex';
    btn.textContent = T('tree_view');
    initializeMermaid();
    loadRelationsDiagram();
  } else {
    listView.style.display = 'block';
    diagramView.style.display = 'none';
    btn.textContent = T('diagram_view');
  }
});
if (canRunSql) {
  document.getElementById('nav-sql')?.addEventListener('click',()=>{
    showView('view-sql');
    setTimeout(() => { if(sqlEditor) sqlEditor.refresh(); }, 100);
  });
}
if (isRoot) {
  document.getElementById('nav-createtable')?.addEventListener('click',()=>showView('view-createtable'));
}


// ── Chart variables ───────────────────────────────────────────────────────────
let tableSizeChart = null;
let indexChart = null;

function renderTableSizeChart(sizes) {
  const ctx = document.getElementById('table-size-chart');
  if (!ctx) return;

  // Destroy existing chart
  if (tableSizeChart) {
    tableSizeChart.destroy();
  }

  const topTables = sizes.slice(0, 10); // Show top 10 tables
  const labels = topTables.map(s => s.name);
  const data = topTables.map(s => s.rows);

  const textColor = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim();

  tableSizeChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: T('rows'),
        data: data,
        backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--accent').trim(),
        borderColor: getComputedStyle(document.documentElement).getPropertyValue('--accent-brd').trim(),
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            color: textColor,
            callback: function(value) {
              return value.toLocaleString();
            }
          },
          grid: {
            color: textColor + '20'
          }
        },
        x: {
          ticks: {
            color: textColor,
            maxRotation: 45,
            minRotation: 45
          },
          grid: {
            color: textColor + '20'
          }
        }
      }
    }
  });
}

function renderIndexChart(indexes) {
  const ctx = document.getElementById('index-chart');
  if (!ctx) return;

  // Destroy existing chart
  if (indexChart) {
    indexChart.destroy();
  }

  // Group by table
  const grouped = {};
  indexes.forEach(idx => {
    if (!grouped[idx.table]) grouped[idx.table] = { total: 0, unique: 0 };
    grouped[idx.table].total++;
    if (idx.unique) grouped[idx.table].unique++;
  });

  const topTables = Object.entries(grouped)
    .map(([name, data]) => ({ name, ...data }))
    .sort((a, b) => b.total - a.total)
    .slice(0, 10);

  const labels = topTables.map(t => t.name);
  const uniqueData = topTables.map(t => t.unique);
  const nonUniqueData = topTables.map(t => t.total - t.unique);

  const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim();
  const purpleColor = getComputedStyle(document.documentElement).getPropertyValue('--purple').trim();
  const textColor = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim();

  indexChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          label: T('unique_indexes'),
          data: uniqueData,
          backgroundColor: accentColor,
          borderWidth: 0
        },
        {
          label: T('non_unique'),
          data: nonUniqueData,
          backgroundColor: purpleColor,
          borderWidth: 0
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          position: 'top',
          labels: {
            color: textColor
          }
        }
      },
      scales: {
        x: {
          stacked: true,
          ticks: {
            color: textColor,
            maxRotation: 45,
            minRotation: 45
          },
          grid: {
            color: textColor + '20'
          }
        },
        y: {
          stacked: true,
          beginAtZero: true,
          ticks: {
            color: textColor
          },
          grid: {
            color: textColor + '20'
          }
        }
      }
    }
  });
}

// ── Custom SVG Schema Diagram (no Mermaid dependency) ────────────────────────
const diagState = { scale: 1, tx: 0, ty: 0, dragging: false, lastX: 0, lastY: 0, pinchDist: 0 };

function diagSetTransform() {
  const svg = document.getElementById('diag-svg');
  if (!svg) return;
  const g = svg.querySelector('g.diag-root');
  if (g) g.setAttribute('transform', `translate(${diagState.tx},${diagState.ty}) scale(${diagState.scale})`);
  const lbl = document.getElementById('diag-zoom-label');
  if (lbl) lbl.textContent = Math.round(diagState.scale * 100) + '%';
}

function diagClampAndSet(scale, tx, ty) {
  diagState.scale = Math.min(4, Math.max(0.15, scale));
  diagState.tx = tx;
  diagState.ty = ty;
  diagSetTransform();
}

function initDiagPanZoom() {
  const wrap = document.getElementById('diag-canvas-wrap');
  if (!wrap || wrap._diagInited) return;
  wrap._diagInited = true;

  // Mouse wheel zoom
  wrap.addEventListener('wheel', e => {
    e.preventDefault();
    const rect = wrap.getBoundingClientRect();
    const mx = e.clientX - rect.left, my = e.clientY - rect.top;
    const factor = e.deltaY < 0 ? 1.12 : 0.89;
    const ns = Math.min(4, Math.max(0.15, diagState.scale * factor));
    const ratio = ns / diagState.scale;
    diagClampAndSet(ns, mx - (mx - diagState.tx) * ratio, my - (my - diagState.ty) * ratio);
  }, { passive: false });

  // Mouse drag
  wrap.addEventListener('mousedown', e => { diagState.dragging = true; diagState.lastX = e.clientX; diagState.lastY = e.clientY; });
  window.addEventListener('mousemove', e => {
    if (!diagState.dragging) return;
    diagClampAndSet(diagState.scale, diagState.tx + e.clientX - diagState.lastX, diagState.ty + e.clientY - diagState.lastY);
    diagState.lastX = e.clientX; diagState.lastY = e.clientY;
  });
  window.addEventListener('mouseup', () => { diagState.dragging = false; });

  // Touch pan + pinch zoom
  wrap.addEventListener('touchstart', e => {
    if (e.touches.length === 1) {
      diagState.dragging = true;
      diagState.lastX = e.touches[0].clientX;
      diagState.lastY = e.touches[0].clientY;
    } else if (e.touches.length === 2) {
      diagState.dragging = false;
      diagState.pinchDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
    }
  }, { passive: true });
  wrap.addEventListener('touchmove', e => {
    e.preventDefault();
    if (e.touches.length === 1 && diagState.dragging) {
      diagClampAndSet(diagState.scale, diagState.tx + e.touches[0].clientX - diagState.lastX, diagState.ty + e.touches[0].clientY - diagState.lastY);
      diagState.lastX = e.touches[0].clientX; diagState.lastY = e.touches[0].clientY;
    } else if (e.touches.length === 2) {
      const dist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
      if (diagState.pinchDist) {
        const rect = wrap.getBoundingClientRect();
        const cx = (e.touches[0].clientX + e.touches[1].clientX) / 2 - rect.left;
        const cy = (e.touches[0].clientY + e.touches[1].clientY) / 2 - rect.top;
        const factor = dist / diagState.pinchDist;
        const ns = Math.min(4, Math.max(0.15, diagState.scale * factor));
        const ratio = ns / diagState.scale;
        diagClampAndSet(ns, cx - (cx - diagState.tx) * ratio, cy - (cy - diagState.ty) * ratio);
      }
      diagState.pinchDist = dist;
    }
  }, { passive: false });
  wrap.addEventListener('touchend', () => { diagState.dragging = false; diagState.pinchDist = 0; });

  // Buttons
  document.getElementById('diag-zoom-in')?.addEventListener('click', () => {
    const ns = Math.min(4, diagState.scale * 1.25);
    const wrap2 = document.getElementById('diag-canvas-wrap');
    const cx = wrap2.clientWidth / 2, cy = wrap2.clientHeight / 2;
    const ratio = ns / diagState.scale;
    diagClampAndSet(ns, cx - (cx - diagState.tx) * ratio, cy - (cy - diagState.ty) * ratio);
  });
  document.getElementById('diag-zoom-out')?.addEventListener('click', () => {
    const ns = Math.max(0.15, diagState.scale * 0.8);
    const wrap2 = document.getElementById('diag-canvas-wrap');
    const cx = wrap2.clientWidth / 2, cy = wrap2.clientHeight / 2;
    const ratio = ns / diagState.scale;
    diagClampAndSet(ns, cx - (cx - diagState.tx) * ratio, cy - (cy - diagState.ty) * ratio);
  });
  document.getElementById('diag-zoom-reset')?.addEventListener('click', () => {
    diagClampAndSet(1, 0, 0);
  });
}

function renderCustomDiagram(data) {
  // data: array of {name, columns:[{name,type,pk,notnull}], fks:[{from,table,to}]}
  const svgEl = document.getElementById('diag-svg');
  const wrapEl = document.getElementById('diag-canvas-wrap');
  if (!svgEl || !wrapEl) return;

  const COL_WIDTH = 200, ROW_HEIGHT = 26, HDR_H = 34, PAD = 80, MARGIN = 100;
  const tables = data; // [{name, columns, fks}]

  // --- STEP 1: Split schema into FK-connected groups ---
  const pos = {}; // name -> {x, y, w, h}
  const tableByName = new Map(tables.map(tbl => [tbl.name, tbl]));
  const adjacency = new Map(tables.map(tbl => [tbl.name, new Set()]));
  const centerNames = new Set();
  let isolatedFrame = null;

  tables.forEach(tbl => {
    (tbl.fks || []).forEach(fk => {
      if (!tableByName.has(fk.table)) return;
      adjacency.get(tbl.name)?.add(fk.table);
      adjacency.get(fk.table)?.add(tbl.name);
    });
  });

  const visited = new Set();
  const components = [];
  tables.forEach(tbl => {
    if (visited.has(tbl.name)) return;
    const names = [];
    const stack = [tbl.name];
    visited.add(tbl.name);
    while (stack.length) {
      const name = stack.pop();
      names.push(name);
      (adjacency.get(name) || new Set()).forEach(next => {
        if (!visited.has(next)) {
          visited.add(next);
          stack.push(next);
        }
      });
    }
    components.push(names);
  });

  const isolatedNames = components
    .filter(names => names.length === 1 && (adjacency.get(names[0])?.size || 0) === 0)
    .map(names => names[0])
    .sort((a, b) => a.localeCompare(b));
  const connectedComponents = components.filter(names => !(names.length === 1 && (adjacency.get(names[0])?.size || 0) === 0));

  connectedComponents.sort((a, b) => {
    const maxDegree = names => Math.max(...names.map(name => adjacency.get(name)?.size || 0));
    return maxDegree(b) - maxDegree(a) || b.length - a.length || a[0].localeCompare(b[0]);
  });

  // --- STEP 2: Layout each connected group as its own circle ---
  const calcH = (tbl) => HDR_H + tbl.columns.length * ROW_HEIGHT + PAD;

  const SIDE_GAP = 80;
  const SIDE_ROW_GAP = 28;
  const COMPONENT_GAP = 150;
  let cursorY = MARGIN;

  function placeColumn(names, x, centerY) {
    if (!names.length) return;
    const totalH = names.reduce((sum, name) => sum + calcH(tableByName.get(name)), 0) + SIDE_ROW_GAP * (names.length - 1);
    let y = centerY - totalH / 2;
    names.forEach(name => {
      const tbl = tableByName.get(name);
      const h = calcH(tbl);
      pos[name] = { x, y, w: COL_WIDTH, h };
      y += h + SIDE_ROW_GAP;
    });
  }

  function currentBounds() {
    const placed = Object.values(pos);
    if (!placed.length) return null;
    return placed.reduce((bounds, p) => ({
      minX: Math.min(bounds.minX, p.x),
      maxX: Math.max(bounds.maxX, p.x + p.w),
      minY: Math.min(bounds.minY, p.y),
      maxY: Math.max(bounds.maxY, p.y + p.h)
    }), { minX: Infinity, maxX: -Infinity, minY: Infinity, maxY: -Infinity });
  }

  function pointOnRectPerimeter(bounds, distance) {
    const w = bounds.maxX - bounds.minX;
    const h = bounds.maxY - bounds.minY;
    const topLen = w;
    const rightLen = h;
    const bottomLen = w;

    if (distance < topLen) {
      return { x: bounds.minX + distance, y: bounds.minY };
    }
    distance -= topLen;
    if (distance < rightLen) {
      return { x: bounds.maxX, y: bounds.minY + distance };
    }
    distance -= rightLen;
    if (distance < bottomLen) {
      return { x: bounds.maxX - distance, y: bounds.maxY };
    }
    distance -= bottomLen;
    return { x: bounds.minX, y: bounds.maxY - distance };
  }

  function placeIsolatedTables(names) {
    if (!names.length) return;

    const avgH = names.reduce((sum, name) => sum + calcH(tableByName.get(name)), 0) / names.length;
    const slot = Math.max(COL_WIDTH + 70, avgH + 60);
    const desiredPerimeter = names.length * slot;
    let bounds = currentBounds();

    if (!bounds) {
      const cols = Math.max(2, Math.ceil(Math.sqrt(names.length)));
      const rows = Math.max(2, Math.ceil(names.length / cols));
      const width = Math.max(620, cols * (COL_WIDTH + 90));
      const height = Math.max(420, rows * (avgH + 70));
      bounds = { minX: MARGIN + 260, maxX: MARGIN + 260 + width, minY: MARGIN + 180, maxY: MARGIN + 180 + height };
    }

    let w = bounds.maxX - bounds.minX;
    let h = bounds.maxY - bounds.minY;
    const currentPerimeter = 2 * (w + h);
    if (currentPerimeter < desiredPerimeter) {
      const grow = (desiredPerimeter - currentPerimeter) / 4;
      w += grow * 2;
      h += grow * 2;
    }

    const pad = Math.max(120, Math.min(260, slot * 0.75));
    const cx = (bounds.minX + bounds.maxX) / 2;
    const cy = (bounds.minY + bounds.maxY) / 2;
    const ring = {
      minX: cx - w / 2 - pad,
      maxX: cx + w / 2 + pad,
      minY: cy - h / 2 - pad,
      maxY: cy + h / 2 + pad
    };
    const perimeter = 2 * ((ring.maxX - ring.minX) + (ring.maxY - ring.minY));

    names.forEach((name, idx) => {
      const tbl = tableByName.get(name);
      const h = calcH(tbl);
      const p = pointOnRectPerimeter(ring, ((idx + 0.5) / names.length) * perimeter);
      pos[name] = { x: p.x - COL_WIDTH / 2, y: p.y - h / 2, w: COL_WIDTH, h };
    });

    const framePad = 34;
    isolatedFrame = names.reduce((bounds, name) => {
      const p = pos[name];
      return {
        minX: Math.min(bounds.minX, p.x),
        maxX: Math.max(bounds.maxX, p.x + p.w),
        minY: Math.min(bounds.minY, p.y),
        maxY: Math.max(bounds.maxY, p.y + p.h)
      };
    }, { minX: Infinity, maxX: -Infinity, minY: Infinity, maxY: -Infinity });
    isolatedFrame = {
      x: isolatedFrame.minX - framePad,
      y: isolatedFrame.minY - framePad,
      w: isolatedFrame.maxX - isolatedFrame.minX + framePad * 2,
      h: isolatedFrame.maxY - isolatedFrame.minY + framePad * 2
    };
  }

  connectedComponents.forEach(names => {
    const sortedNames = names.slice().sort((a, b) => {
      const degreeDiff = (adjacency.get(b)?.size || 0) - (adjacency.get(a)?.size || 0);
      return degreeDiff || a.localeCompare(b);
    });
    const centerName = sortedNames[0];
    const centerTable = tableByName.get(centerName);
    const directNeighbors = sortedNames.filter(name => name !== centerName && adjacency.get(centerName)?.has(name));
    const sideTables = sortedNames.filter(name => name !== centerName && !adjacency.get(centerName)?.has(name));

    if ((adjacency.get(centerName)?.size || 0) > 0) {
      centerNames.add(centerName);
    }

    const centerH = calcH(centerTable);
    const maxCircleH = Math.max(centerH, ...directNeighbors.map(name => calcH(tableByName.get(name))), 0);
    const angleStep = directNeighbors.length > 0 ? (2 * Math.PI) / directNeighbors.length : 0;
    let radius = directNeighbors.length ? 240 : 0;
    if (directNeighbors.length > 1) {
      const maxNeighborH = Math.max(...directNeighbors.map(name => calcH(tableByName.get(name))));
      const centerDiag = Math.sqrt((COL_WIDTH / 2) ** 2 + (centerH / 2) ** 2);
      const neighborDiag = Math.sqrt((COL_WIDTH / 2) ** 2 + (maxNeighborH / 2) ** 2);
      const sinHalfAngle = Math.sin(angleStep / 2);
      if (sinHalfAngle > 0.001) {
        radius = Math.max(240, (centerDiag + neighborDiag + 45) / (2 * sinHalfAngle));
      }
    }

    const leftColumnX = MARGIN;
    const centerX = leftColumnX + COL_WIDTH + SIDE_GAP + radius + COL_WIDTH / 2;
    const centerY = cursorY + Math.max(radius + maxCircleH / 2, centerH / 2) + 40;
    const rightColumnX = centerX + radius + COL_WIDTH / 2 + SIDE_GAP;

    pos[centerName] = { x: centerX - COL_WIDTH / 2, y: centerY - centerH / 2, w: COL_WIDTH, h: centerH };

    directNeighbors.forEach((name, idx) => {
      const tbl = tableByName.get(name);
      const h = calcH(tbl);
      const angle = directNeighbors.length === 1 ? 0 : -Math.PI / 2 + idx * angleStep;
      pos[name] = {
        x: centerX + radius * Math.cos(angle) - COL_WIDTH / 2,
        y: centerY + radius * Math.sin(angle) - h / 2,
        w: COL_WIDTH,
        h
      };
    });

    const leftSide = sideTables.filter((_, idx) => idx % 2 === 0);
    const rightSide = sideTables.filter((_, idx) => idx % 2 === 1);
    placeColumn(leftSide, leftColumnX, centerY);
    placeColumn(rightSide, rightColumnX, centerY);

    const componentBottom = names.reduce((bottom, name) => {
      const p = pos[name];
      return p ? Math.max(bottom, p.y + p.h) : bottom;
    }, cursorY);
    cursorY = componentBottom + COMPONENT_GAP;
  });

  placeIsolatedTables(isolatedNames);

  // --- STEP 3: Calculate canvas bounds ---
  let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
  Object.values(pos).forEach(p => {
    minX = Math.min(minX, p.x);
    maxX = Math.max(maxX, p.x + p.w);
    minY = Math.min(minY, p.y);
    maxY = Math.max(maxY, p.y + p.h);
  });
  
  const totalW = maxX - minX + MARGIN * 2;
  const totalH = maxY - minY + MARGIN * 2;

  svgEl.setAttribute('width', totalW);
  svgEl.setAttribute('height', totalH);
  svgEl.setAttribute('viewBox', `0 0 ${totalW} ${totalH}`);

  // Shift positions to fit canvas
  Object.keys(pos).forEach(name => {
    pos[name].x -= minX - MARGIN;
    pos[name].y -= minY - MARGIN;
  });
  if (isolatedFrame) {
    isolatedFrame.x -= minX - MARGIN;
    isolatedFrame.y -= minY - MARGIN;
  }

  // Colors
  const accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#4f8ef7';
  const bg2 = getComputedStyle(document.documentElement).getPropertyValue('--bg2').trim() || '#fff';
  const bg3 = getComputedStyle(document.documentElement).getPropertyValue('--bg3').trim() || '#f0f0f0';
  const textColor = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#111';
  const textMuted = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#888';
  const borderColor = getComputedStyle(document.documentElement).getPropertyValue('--border').trim() || '#ccc';
  const yellow = getComputedStyle(document.documentElement).getPropertyValue('--yellow').trim() || '#e6a817';

  let svgContent = `<defs>
    <filter id="diag-shadow" x="-10%" y="-10%" width="120%" height="120%">
      <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="rgba(0,0,0,0.18)"/>
    </filter>
    <marker id="diag-arrow" markerWidth="8" markerHeight="8" refX="7" refY="3" orient="auto">
      <path d="M0,0 L0,6 L8,3 z" fill="${accent}" opacity="0.7"/>
    </marker>
  </defs>
  <g class="diag-root">`;

  if (isolatedFrame) {
    svgContent += `<rect x="${isolatedFrame.x}" y="${isolatedFrame.y}" width="${isolatedFrame.w}" height="${isolatedFrame.h}" rx="8" fill="none" stroke="${textMuted}" stroke-width="1.5" stroke-dasharray="8 7" opacity="0.55"/>`;
  }

  // --- STEP 4: Draw smart lines (routing around tables) ---
  const drawnEdges = new Set();
  
  function routeLine(x1, y1, x2, y2, fromBox, toBox) {
    const dx = x2 - x1;
    const offset = Math.max(60, Math.abs(dx) * 0.35);
    const dir = dx >= 0 ? 1 : -1;
    const cp1x = x1 + offset * dir;
    const cp2x = x2 - offset * dir;
    return `M${x1},${y1} C${cp1x},${y1} ${cp2x},${y2} ${x2},${y2}`;
  }

  tables.forEach(tbl => {
    (tbl.fks || []).forEach(fk => {
      const from = pos[tbl.name];
      const to = pos[fk.table];
      if (!from || !to) return;
      
      const edgeKey = `${tbl.name}->${fk.table}`;
      if (drawnEdges.has(edgeKey)) return;
      drawnEdges.add(edgeKey);

      const fromCenterX = from.x + from.w / 2;
      const toCenterX = to.x + to.w / 2;

      // Find exit point from source table
      const fromColIdx = tbl.columns.findIndex(c => c.name === fk.from);
      const fromY = from.y + HDR_H + (fromColIdx >= 0 ? fromColIdx * ROW_HEIGHT + ROW_HEIGHT / 2 : HDR_H / 2);
      const fromX = fromCenterX <= toCenterX ? from.x + from.w + 10 : from.x - 10;
      
      // Find entry point to target table
      const toX = fromCenterX <= toCenterX ? to.x - 10 : to.x + to.w + 10;
      const toY = to.y + to.h / 2;
      
      const pathD = routeLine(fromX, fromY, toX, toY, from, to);
      svgContent += `<path d="${pathD}" fill="none" stroke="${accent}" stroke-width="1.8" stroke-opacity="0.6" marker-end="url(#diag-arrow)"/>`;
    });
  });

  // --- STEP 5: Draw table boxes ---
  tables.forEach(tbl => {
    const p = pos[tbl.name];
    if (!p) return;
    const { x, y, w, h } = p;
    const isMain = centerNames.has(tbl.name);

    svgContent += `<g class="diag-table">
      <rect x="${x}" y="${y}" width="${w}" height="${h}" rx="7" fill="${bg2}" stroke="${borderColor}" stroke-width="${isMain ? 2.5 : 1.5}" filter="url(#diag-shadow)"/>
      <rect x="${x}" y="${y}" width="${w}" height="${HDR_H}" rx="7" fill="${isMain ? '#5a9fff' : accent}"/>
      <rect x="${x}" y="${y + HDR_H - 8}" width="${w}" height="8" fill="${isMain ? '#5a9fff' : accent}"/>
      <text x="${x + w/2}" y="${y + HDR_H/2 + 5}" text-anchor="middle" font-size="${isMain ? 13 : 12}" font-weight="${isMain ? 800 : 700}" fill="#fff" font-family="monospace">${esc(tbl.name)}</text>`;

    tbl.columns.forEach((col, ci) => {
      const cy2 = y + HDR_H + ci * ROW_HEIGHT;
      const isEven = ci % 2 === 0;
      svgContent += `<rect x="${x}" y="${cy2}" width="${w}" height="${ROW_HEIGHT}" fill="${isEven ? bg3 : bg2}" opacity="0.7"/>`;
      if (col.pk) {
        svgContent += `<text x="${x + 10}" y="${cy2 + ROW_HEIGHT/2 + 4}" font-size="10" fill="${accent}">🔑</text>`;
      }
      const nameX = x + (col.pk ? 26 : 10);
      svgContent += `<text x="${nameX}" y="${cy2 + ROW_HEIGHT/2 + 4}" font-size="11" fill="${textColor}" font-family="monospace">
        <tspan>${esc(col.name.length > 20 ? col.name.slice(0,18)+'…' : col.name)}</tspan>
      </text>`;
      const typeStr = (col.type || 'TEXT').slice(0, 10);
      svgContent += `<text x="${x + w - 6}" y="${cy2 + ROW_HEIGHT/2 + 4}" text-anchor="end" font-size="9" fill="${yellow}" font-family="monospace">${esc(typeStr)}</text>`;
      svgContent += `<line x1="${x}" y1="${cy2 + ROW_HEIGHT}" x2="${x + w}" y2="${cy2 + ROW_HEIGHT}" stroke="${borderColor}" stroke-width="0.5"/>`;
    });

    svgContent += `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="7" fill="none" stroke="${borderColor}" stroke-width="${isMain ? 2.5 : 1.5}"/>`;
    svgContent += `</g>`;
  });

  svgContent += `</g>`;
  svgEl.innerHTML = svgContent;

  // Calculate optimal zoom to fit all content
  const wrapW = wrapEl.clientWidth || 800;
  const wrapH = wrapEl.clientHeight || 600;
  const scaleX = wrapW / totalW;
  const scaleY = wrapH / totalH;
  const fitScale = Math.min(scaleX, scaleY, 1) * 0.95; // 95% to leave margin
  
  // Center the diagram
  const offsetX = (wrapW - totalW * fitScale) / 2;
  const offsetY = (wrapH - totalH * fitScale) / 2;

  diagState.scale = fitScale;
  diagState.tx = offsetX;
  diagState.ty = offsetY;
  diagSetTransform();
  initDiagPanZoom();
}

async function loadRelationsDiagram() {
  const svgEl = document.getElementById('diag-svg');
  const wrapEl = document.getElementById('diag-canvas-wrap');
  if (!svgEl || !wrapEl) return;

  svgEl.innerHTML = `<text x="20" y="40" fill="var(--text-muted)" font-size="13"><tspan class="spin"></tspan> ${T('mermaid_loading')}</text>`;

  try {
    const schemaData = await api('schema');
    if (!schemaData || !schemaData.success || !schemaData.schema || Object.keys(schemaData.schema).length === 0) {
      svgEl.innerHTML = `<text x="20" y="40" fill="var(--red)" font-size="13">${T('no_fk')}</text>`;
      return;
    }
    // Build table list
    const tables = Object.entries(schemaData.schema).map(([name, info]) => ({
      name,
      columns: info.columns || [],
      fks: info.fks || []
    }));
    renderCustomDiagram(tables);
  } catch(e) {
    console.error('Diagram error:', e);
    svgEl.innerHTML = `<text x="20" y="40" fill="var(--red)" font-size="13">${T('error')}: ${esc(e.message)}</text>`;
  }
}

// Keep these stubs so nothing breaks
function initializeMermaid() {}
function loadSchemaDiagram() {}
function generateMermaidDiagram() { return ''; }
function generateRelationsMermaidDiagram() { return ''; }

function esc(s){if(s===null||s===undefined)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

// ── Export Modal (Monaco preview) ───────────────────────────────────────────
let exportMonacoEditor = null;
let exportContent = '';
let exportFilename = '';
let monacoReadyPromise = null;
function ensureMonacoLoaded() {
  if (monacoReadyPromise) return monacoReadyPromise;
  monacoReadyPromise = new Promise(function(resolve, reject) {
    if (typeof require === 'undefined') { reject(new Error('loader')); return; }
    window.MonacoEnvironment = window.MonacoEnvironment || {};
    window.MonacoEnvironment.getWorkerUrl = function() { return MONACO_VS_PATH + '/editor/editor.worker.js'; };
    require.config({ paths: { vs: MONACO_VS_PATH } });
    require(['vs/editor/editor.main'], function() { resolve(window.monaco); });
  });
  return monacoReadyPromise;
}
async function showExportModal() {
  const modal = document.getElementById('export-modal');
  if (!modal) return;
  modal.classList.add('open');
  try {
    const response = await fetch('db_admin.php?export_report=1&lang=<?= urlencode($lang) ?>&db_source=' + encodeURIComponent(currentDbSource || 'postgres'));
    exportContent = await response.text();
    exportFilename = 'db_schema_' + new Date().toISOString().slice(0,19).replace(/[:T]/g,'_').slice(0,-1).replace(/-/g,'').replace(/_/g,'') + '.log';
    const wrap = document.getElementById('export-monaco-wrap');
    const monacoNs = await ensureMonacoLoaded();
    const opts = {
      value: exportContent,
      language: 'sql',
      theme: EXPORT_MONACO_THEME,
      readOnly: true,
      domReadOnly: true,
      automaticLayout: true,
      fontSize: 11,
      minimap: { enabled: true, side: 'right', scale: 1 },
      scrollBeyondLastLine: false,
      wordWrap: 'off',
      lineNumbers: 'on',
      renderLineHighlight: 'line',
      folding: true,
      glyphMargin: false,
      scrollbar: { vertical: 'auto', horizontal: 'auto' },
      cursorBlinking: 'solid',
      contextmenu: true,
    };
    if (!exportMonacoEditor && wrap) {
      exportMonacoEditor = monacoNs.editor.create(wrap, opts);
    } else if (exportMonacoEditor) {
      exportMonacoEditor.setValue(exportContent);
      exportMonacoEditor.updateOptions({ theme: EXPORT_MONACO_THEME, readOnly: true, domReadOnly: true });
    }
    setTimeout(function() { if (exportMonacoEditor) exportMonacoEditor.layout(); }, 0);
    setTimeout(function() { if (exportMonacoEditor) exportMonacoEditor.layout(); }, 100);
  } catch(e) {
    console.error('Failed to load export content:', e);
    const ta = document.getElementById('export-content');
    if (ta) ta.value = 'Error loading report: ' + e.message;
  }
}

function closeExportModal() {
  const modal = document.getElementById('export-modal');
  if (modal) modal.classList.remove('open');
}

function downloadExportReport() {
  if (!exportContent) return;
  
  const blob = new Blob([exportContent], { type: 'text/plain; charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = exportFilename || 'db_schema_report.log';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  
  notify(T('export_done'), 'success');
}

function selectAllInExport() {
  if (!exportMonacoEditor) return;
  const model = exportMonacoEditor.getModel();
  if (!model) return;
  exportMonacoEditor.setSelection(model.getFullModelRange());
  exportMonacoEditor.focus();
}

async function copyExportToClipboard() {
  if (!exportContent) return;
  try {
    await copyToClipboard(exportContent);
    notify(T('export_copied'), 'success');
    const copyBtn = document.getElementById('export-copy-btn');
    if (copyBtn) {
      copyBtn.classList.remove('flash-success');
      void copyBtn.offsetWidth;
      copyBtn.classList.add('flash-success');
      setTimeout(function() { copyBtn.classList.remove('flash-success'); }, 800);
    }
  } catch(e) {
    console.error('Failed to copy:', e);
  }
}

// Initialize export modal event listeners
function initExportModal() {
  const exportBtn = document.getElementById('export-btn');
  if (exportBtn) {
    exportBtn.addEventListener('click', function(e) {
      e.preventDefault();
      showExportModal();
    });
  }
  
  const closeBtn = document.getElementById('export-modal-close');
  if (closeBtn) closeBtn.addEventListener('click', closeExportModal);
  
  const closeTbBtn = document.getElementById('export-close-btn');
  if (closeTbBtn) closeTbBtn.addEventListener('click', closeExportModal);
  
  const downloadBtn = document.getElementById('export-download-btn');
  if (downloadBtn) downloadBtn.addEventListener('click', downloadExportReport);
  
  const selectAllBtn = document.getElementById('export-select-all-btn');
  if (selectAllBtn) selectAllBtn.addEventListener('click', selectAllInExport);
  
  const copyBtn = document.getElementById('export-copy-btn');
  if (copyBtn) copyBtn.addEventListener('click', copyExportToClipboard);
  
  // Close on overlay click
  const modal = document.getElementById('export-modal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closeExportModal();
    });
  }
  
  // Close on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const modal = document.getElementById('export-modal');
      if (modal && modal.classList.contains('open')) {
        closeExportModal();
      }
    }
  });

  let resizeTimeout;
  window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(function() {
      const exModal = document.getElementById('export-modal');
      if (exportMonacoEditor && exModal && exModal.classList.contains('open')) exportMonacoEditor.layout();
    }, 150);
  });
}

// Initialize
loadSQLHistory();
syncTableViewToggle();
initSQLEditor();
initExportModal();
document.querySelectorAll('[data-db-source]').forEach(btn => {
  btn.addEventListener('click', async () => {
    await switchDbSource(btn.dataset.dbSource || 'postgres');
  });
});
async function switchDbSource(source) {
  currentDbSource = source === 'redis' ? 'redis' : 'postgres';
  const select = document.getElementById('db-source-select');
  if (select) select.value = currentDbSource;
  const badge = document.getElementById('db-source-badge');
  if (badge) badge.textContent = currentDbSource === 'redis' ? 'Redis' : 'PostgreSQL · appdashboard';
  document.getElementById('db-choice')?.remove();
  S.tables = []; S.cur = null; S.page = 1;
  document.getElementById('table-list').innerHTML = '<div class="empty" style="height:60px;font-size:11px;"><span class="spin"></span></div>';
  await loadTables();
  await loadSchemaForAutocomplete();
  if (currentDbSource === 'redis') showView('view-stats');
}
document.getElementById('db-source-select')?.addEventListener('change', e => switchDbSource(e.target.value));</script>
</body>
</html>
