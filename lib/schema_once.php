<?php
declare(strict_types=1);
require_once __DIR__ . '/redis.php';
function app_schema_once(string $name, string $file, callable $fn): void {
    static $local = [];
    $stamp = $name . ':' . (string)@filemtime($file) . ':' . (string)@filesize($file);
    if (isset($local[$stamp])) return;
    $redis = app_redis();
    $key = app_redis_key('schema_ok:' . $stamp);
    if ($redis) {
        try {
            if ($redis->exists($key)) {
                $local[$stamp] = true;
                return;
            }
        } catch (Throwable $e) {
        }
    }
    $fn();
    $local[$stamp] = true;
    if ($redis) {
        try {
            $redis->set($key, '1', ['EX' => 86400]);
        } catch (Throwable $e) {
        }
    }
}
