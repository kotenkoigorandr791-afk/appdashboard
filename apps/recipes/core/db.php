<?php
require_once __DIR__ . '/../../../lib/app_db.php';
require_once __DIR__ . '/../../../lib/redis.php';
function recipes_cache_gen(): string {
    $r = app_redis();
    if (!$r) return '0';
    try { return (string)($r->get(app_redis_key('recipes:gen')) ?: '0'); } catch (Throwable $e) { return '0'; }
}
function recipes_cache_bump(): void {
    $r = app_redis();
    if (!$r) return;
    try { $r->incr(app_redis_key('recipes:gen')); } catch (Throwable $e) {}
}
function recipes_cached_json(string $name, callable $build, int $ttl = 30): string {
    $r = app_redis();
    $key = app_redis_key('recipes:c:' . $name . ':' . recipes_cache_gen());
    if ($r) {
        try {
            $hit = $r->get($key);
            if (is_string($hit) && $hit !== '') return $hit;
        } catch (Throwable $e) {}
    }
    $out = (string)$build();
    if ($r) {
        try { $r->set($key, $out, ['EX' => $ttl]); } catch (Throwable $e) {}
    }
    return $out;
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) {
    recipes_cache_bump();
    register_shutdown_function('recipes_cache_bump');
}
if (!defined('RECIPES_DB_TABLE_PREFIX')) {
    define('RECIPES_DB_TABLE_PREFIX', 'recipes_');
}
if (!defined('RECIPES_STEPS_TABLE')) {
    define('RECIPES_STEPS_TABLE', 'recipes_steps');
}
if (!function_exists('getDB')) {
    function getDB(): AppDatabase {
        return app_main_db();
    }
}
