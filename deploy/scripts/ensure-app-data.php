#!/usr/bin/env php
<?php
/**
 * Seed the default application data on a fresh deployment.
 *
 * Creates the app schemas and inserts the bundled default content
 * (products, recipes, steps and config) so the application is usable
 * immediately after the first start.
 *
 * Safe to run on every boot: it only fills tables that are still empty and
 * never overwrites existing user data.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli_only');
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

require_once APP_ROOT . '/apps/recipes/core/db.php';
require_once APP_ROOT . '/apps/recipes/core/seed.php';
require_once APP_ROOT . '/apps/recipes/core/schema.php';

try {
    $db = getDB();
    if (!$db) {
        fwrite(STDERR, "app-seed: no database connection\n");
        exit(1);
    }
    // Creates the schema if needed and applies the seed.
    recipes_migrate_json_to_sqlite();

    $counts = [];
    foreach (['recipes_products', 'recipes_data', 'recipes_steps', 'recipes_config'] as $table) {
        try {
            $counts[$table] = (int)$db->querySingle('SELECT COUNT(*) FROM ' . $table);
        } catch (Throwable $e) {
            $counts[$table] = -1;
        }
    }
    echo 'app-seed: ' . json_encode($counts, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'app-seed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
