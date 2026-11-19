<?php
if (!defined('UPLOAD_SESSIONS_DIR')) {
    $spendsUploadSessions = null;
    $spendsStorageHelper = dirname(__DIR__, 3) . '/storage_helper.php';
    if (is_file($spendsStorageHelper)) {
        require_once $spendsStorageHelper;
        try {
            $spendsUploadSessions = rtrim(appdata_app_base_dir('spends'), '/') . '/upload_sessions';
        } catch (Throwable $e) {
            $spendsUploadSessions = null;
        }
    }
    if ($spendsUploadSessions === null) {
        // Never fall back to the code tree (host bind mount).
        $spendsUploadSessions = rtrim(sys_get_temp_dir(), '/') . '/appdashboard_spends/upload_sessions';
    }
    define('UPLOAD_SESSIONS_DIR', $spendsUploadSessions);
}
@mkdir(UPLOAD_SESSIONS_DIR, 0775, true);
// Data lives on the APPDATA volume; no symlinks in the repository.
require_once __DIR__ . '/../../../lib/app_db.php';
if (!function_exists('getDB')) {
    function getDB(): AppDatabase {
        return app_main_db();
    }
}
