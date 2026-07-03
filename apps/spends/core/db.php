<?php
define('UPLOAD_SESSIONS_DIR', __DIR__ . '/../upload_sessions');
@mkdir(UPLOAD_SESSIONS_DIR, 0775, true);
require_once __DIR__ . '/../../../lib/app_db.php';
function getDB(): AppDatabase {
    return app_main_db();
}
