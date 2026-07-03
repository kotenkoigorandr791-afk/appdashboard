<?php
require_once __DIR__ . '/../../../lib/app_db.php';
function getDB(): AppDatabase {
    return app_main_db();
}
