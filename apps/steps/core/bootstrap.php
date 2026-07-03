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

if (!defined('UPLOAD_SESSIONS_DIR')) define('UPLOAD_SESSIONS_DIR', __DIR__ . '/../upload_sessions');
if (!defined('TABLE_PAGE_SIZE')) define('TABLE_PAGE_SIZE', 50);

@mkdir(UPLOAD_SESSIONS_DIR, 0775, true);
