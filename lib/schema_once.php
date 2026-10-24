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
function app_once_stamp(string $key, string $file): string {
    return $key . ':' . (string)@filemtime($file) . ':' . (string)@filesize($file);
}
function app_once_done(string $key, string $file): bool {
    static $local = [];
    $stamp = app_once_stamp($key, $file);
    if (isset($local[$stamp])) return true;
    $redis = app_redis();
    if (!$redis) return false;
    try {
        if ($redis->exists(app_redis_key('schema_ok:' . $stamp))) {
            $local[$stamp] = true;
            return true;
        }
    } catch (Throwable $e) {
    }
    return false;
}
function app_once_mark(string $key, string $file): void {
    $redis = app_redis();
    if (!$redis) return;
    try {
        $redis->set(app_redis_key('schema_ok:' . app_once_stamp($key, $file)), '1', ['EX' => 86400]);
    } catch (Throwable $e) {
    }
}
function app_once_wrap(string $key, string $file, callable $fn): void {
    if (app_once_done($key, $file)) return;
    $fn();
    app_once_mark($key, $file);
}
