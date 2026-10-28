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
            $redis->set($key, '1', ['EX' => 604800]);
        } catch (Throwable $e) {
        }
    }
}
/**
 * Claim-based gate for DDL / schema bootstrap blocks.
 *
 * Returns true only for the first caller for this ($name, $file) version pair,
 * false for every later one (same request or any other FPM child). The claim is
 * written BEFORE the DDL runs, so concurrent processes do not race on the
 * catalog locks; because the stamp embeds mtime+size of $file, editing the file
 * makes the gate open again for the next deploy.
 *
 * Schema statements (CREATE ... IF NOT EXISTS / ALTER ... ADD COLUMN) are catalog
 * writes that block: before this gate the per-request ensure* helpers ran them on
 * every write path, and the slow-query log showed 1.5-11s DDL on hot endpoints.
 */
function app_schema_should_run(string $name, string $file): bool {
    static $local = [];
    $stamp = $name . ':' . (string)@filemtime($file) . ':' . (string)@filesize($file);
    if (isset($local[$stamp])) return false;
    $local[$stamp] = true;
    $redis = app_redis();
    $key = app_redis_key('schema_ok:' . $stamp);
    if ($redis) {
        try {
            if ($redis->exists($key)) return false;
            $redis->set($key, '1', ['EX' => 604800]);
            return true;
        } catch (Throwable $e) {
            return true; // Redis unavailable: fall back to running the DDL
        }
    }
    return true;
}

/**
 * Drop every schema_ok stamp (used when the schema bootstrap had to be rerun, e.g.
 * after a restore or a failed migration, so the ensure* gates open again).
 */
function app_schema_forget_all(): void {
    $redis = app_redis();
    if (!$redis) return;
    $prefix = app_redis_key('schema_ok:');
    try {
        $it = null;
        for ($guard = 0; $guard < 200; $guard++) {
            $keys = $redis->scan($it, $prefix . '*', 500);
            if (!is_array($keys) || !$keys) break;
            $redis->del($keys);
        }
    } catch (Throwable $e) {
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
        $redis->set(app_redis_key('schema_ok:' . app_once_stamp($key, $file)), '1', ['EX' => 604800]);
    } catch (Throwable $e) {
    }
}
function app_once_wrap(string $key, string $file, callable $fn): void {
    if (app_once_done($key, $file)) return;
    $fn();
    app_once_mark($key, $file);
}
