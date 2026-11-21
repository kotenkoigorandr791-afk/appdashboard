<?php
declare(strict_types=1);

require_once __DIR__ . '/integration/bootstrap.php';

// The dashboard image exports APP_VERSION for its own release number (e.g.
// "1.0.1"). Kanboard reuses that constant to gate plugin compatibility
// (Plugin\AppDashboard requires ">=1.2.20"), so the inherited value silently
// disabled the AppDashboard plugin and left /apps/kanboard/ stuck on the
// login screen. Pin the vendored Kanboard release instead; deployments can
// override it with KANBOARD_APP_VERSION.
if (!defined('APP_VERSION')) {
    $kbAppVersion = trim((string)getenv('KANBOARD_APP_VERSION'));
    if (!preg_match('/^\d+\.\d+\.\d+$/', $kbAppVersion) || version_compare($kbAppVersion, '1.2.20', '<')) {
        $kbAppVersion = '1.2.47';
    }
    define('APP_VERSION', $kbAppVersion);
}

$kb = kanboard_db_resolve();

// Runtime data (attachments, thumbnails, cache) belongs on the APPDATA volume.
// The code tree is a bind mount on the host, so writing there bypasses the
// volume, the quotas and the backup profile. Never fall back to the repository.
$kbDataDir = getenv('KANBOARD_DATA_DIR');
if (!is_string($kbDataDir) || trim($kbDataDir) === '') {
    $kbDataDir = null;
    $kbStorageHelper = dirname(__DIR__, 2) . '/shell/storage_helper.php';
    if (is_file($kbStorageHelper)) {
        require_once $kbStorageHelper;
        try {
            $kbDataDir = rtrim(appdata_app_base_dir('kanboard'), '/') . '/data';
        } catch (Throwable $e) {
            $kbDataDir = null;
        }
    }
}
if (!is_string($kbDataDir) || trim($kbDataDir) === '') {
    // Last-resort fallback is still a volume-backed temp dir, never __DIR__.
    $kbDataDir = rtrim(sys_get_temp_dir(), '/\\') . '/appdashboard-kanboard-data';
}
$kbDataDir = rtrim($kbDataDir, '/\\');
foreach ([$kbDataDir, $kbDataDir . '/files', $kbDataDir . '/cache'] as $kbDir) {
    if (!is_dir($kbDir)) @mkdir($kbDir, 0775, true);
}

define('DEBUG', false);
define('LOG_DRIVER', 'system');
define('DATA_DIR', $kbDataDir);
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
