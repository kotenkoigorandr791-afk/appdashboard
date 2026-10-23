<?php
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/../auth_common.php';
require_once __DIR__ . '/../video_job_sqlite.php';
require_once __DIR__ . '/../utils/google_fit_takeout/fit_takeout_state.php';
require_once __DIR__ . '/../utils/zeep_life/zeep_state.php';
function getUserDB(string $userDir): ?AppDatabase {
    static $ready = false;
    $email = get_current_user_email();
    if (!$email) return null;
    try {
        $db = app_main_db();
        steps_user($email);
        if (!$ready) {
            steps_store_ensure($db);
            steps_user_bootstrap($db, $email, $userDir);
            $ready = true;
        }
        return $db;
    } catch (Exception $e) {
        error_log('User DB init failed: ' . $e->getMessage());
        return null;
    }
}
