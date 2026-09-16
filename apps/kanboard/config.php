<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/db_config.php';
$cfg = app_db_load_config();
$host = (string)($cfg['host'] ?? '127.0.0.1');
if ($host === '127.0.0.1' || $host === 'localhost') {
    $dockerHost = getenv('DB_HOST');
    if (is_string($dockerHost) && $dockerHost !== '') {
        $host = $dockerHost;
    } elseif (is_file('/.dockerenv') || getenv('APP_IN_DOCKER') === '1') {
        $host = 'appdashboard-db';
    }
}

define('DEBUG', false);
define('LOG_DRIVER', 'system');
define('DATA_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'data');
define('PLUGINS_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'plugins');
define('FILES_DIR', DATA_DIR . DIRECTORY_SEPARATOR . 'files');
define('CACHE_DIR', DATA_DIR . DIRECTORY_SEPARATOR . 'cache');
define('PLUGIN_INSTALLER', false);
define('MAIL_CONFIGURATION', false);
define('DB_RUN_MIGRATIONS', true);
define('DB_DRIVER', 'postgres');
define('DB_USERNAME', (string)($cfg['user'] ?? 'appdashboard'));
define('DB_PASSWORD', (string)($cfg['password'] ?? ''));
define('DB_HOSTNAME', $host);
define('DB_NAME', getenv('KANBOARD_DB_NAME') ?: 'kanboard');
define('DB_PORT', (int)($cfg['port'] ?? 5432));
define('LDAP_AUTH', false);
define('REVERSE_PROXY_AUTH', false);
define('REMEMBER_ME_AUTH', false);
define('ENABLE_HSTS', false);
define('ENABLE_XFRAME', false);
define('HIDE_LOGIN_FORM', true);
define('DISABLE_LOGOUT', true);
define('ENABLE_URL_REWRITE', false);
define('SESSION_HANDLER', 'db');
define('CACHE_DRIVER', 'memory');
define('BRUTEFORCE_CAPTCHA', 0);
define('BRUTEFORCE_LOCKDOWN', 0);
