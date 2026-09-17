<?php
declare(strict_types=1);

require_once __DIR__ . '/integration/bootstrap.php';

$kb = kanboard_db_resolve();

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
define('DB_USERNAME', $kb['user']);
define('DB_PASSWORD', $kb['password']);
define('DB_HOSTNAME', $kb['host']);
define('DB_NAME', $kb['database']);
define('DB_SCHEMA', $kb['schema']);
define('DB_PORT', $kb['port']);
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
