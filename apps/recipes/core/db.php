<?php
require_once __DIR__ . '/../../../lib/app_db.php';
if (!defined('RECIPES_DB_TABLE_PREFIX')) {
    define('RECIPES_DB_TABLE_PREFIX', 'recipes_');
}
if (!defined('RECIPES_STEPS_TABLE')) {
    define('RECIPES_STEPS_TABLE', 'recipes_steps');
}
// Absolute paths to the app data folders. The products and recipes folders
// are symlinked into the application data directory by the deploy scripts,
// and the relative "products"/"recipes" paths only resolve when the working
// directory happens to be the app root (not the case on the CLI).
if (!defined('RECIPES_APP_DIR')) {
    define('RECIPES_APP_DIR', dirname(__DIR__));
}
if (!defined('RECIPES_PRODUCTS_DIR')) {
    define('RECIPES_PRODUCTS_DIR', RECIPES_APP_DIR . '/products');
}
if (!defined('RECIPES_RECIPES_DIR')) {
    define('RECIPES_RECIPES_DIR', RECIPES_APP_DIR . '/recipes');
}
if (!function_exists('getDB')) {
    function getDB(): AppDatabase {
        return app_main_db();
    }
}
