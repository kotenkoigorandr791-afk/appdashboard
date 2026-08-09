<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../auth_common.php';
require_once __DIR__ . '/../video_job_sqlite.php';
require_once __DIR__ . '/../utils/google_fit_takeout/fit_takeout_state.php';

if (!defined('UPLOAD_SESSIONS_DIR')) {
    $stepsUpload = null;
    $stepsHelper = dirname(__DIR__, 3) . '/storage_helper.php';
    if (is_file($stepsHelper)) {
        require_once $stepsHelper;
        try {
            $stepsUpload = rtrim(appdata_app_base_dir('steps'), '/') . '/upload_sessions';
        } catch (Throwable $e) {
            $stepsUpload = null;
        }
    }
    if ($stepsUpload === null) {
        $link = __DIR__ . '/../upload_sessions';
        $stepsUpload = (is_link($link) && realpath($link)) ? realpath($link) : $link;
    }
    define('UPLOAD_SESSIONS_DIR', $stepsUpload);
}
if (!defined('TABLE_PAGE_SIZE')) define('TABLE_PAGE_SIZE', 50);

@mkdir(UPLOAD_SESSIONS_DIR, 0775, true);
$stepsUploadLink = __DIR__ . '/../upload_sessions';
if (!file_exists($stepsUploadLink) && is_dir(UPLOAD_SESSIONS_DIR)) {
    @symlink(UPLOAD_SESSIONS_DIR, $stepsUploadLink);
}
