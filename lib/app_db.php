<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/pgsql_compat.php';
function app_main_db(): AppDatabase {
    return app_open_main_db();
}
