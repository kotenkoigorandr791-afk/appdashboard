<?php
require_once __DIR__ . '/../../../lib/app_db.php';
if (!defined('RECIPES_DB_TABLE_PREFIX')) {
    define('RECIPES_DB_TABLE_PREFIX', 'recipes_');
}
if (!defined('RECIPES_STEPS_TABLE')) {
    define('RECIPES_STEPS_TABLE', 'recipes_steps');
}
function getDB(): AppDatabase {
    return app_main_db();
}
