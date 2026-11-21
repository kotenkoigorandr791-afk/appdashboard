<?php
require_once dirname(__DIR__, 3) . '/lib/redis.php';

function monitor_cache_key(string $suffix): string {
    return app_redis_key('monitor:' . $suffix);
}

function monitor_cache_get_json(string $suffix): ?array {
    $redis = app_redis();
    if (!$redis) {
        return null;
    }
    try {
        $raw = $redis->get(monitor_cache_key($suffix));
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    } catch (Throwable $e) {
        return null;
    }
}

function monitor_cache_set_json(string $suffix, array $payload, int $ttlSec = 12): void {
    $redis = app_redis();
    if (!$redis) {
        return;
    }
    try {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return;
        }
        $redis->setex(monitor_cache_key($suffix), max(1, $ttlSec), $json);
    } catch (Throwable $e) {
    }
}

function monitor_cache_bust(string $suffix = ''): void {
    $redis = app_redis();
    if (!$redis) {
        return;
    }
    try {
        if ($suffix !== '') {
            $redis->del(monitor_cache_key($suffix));
            return;
        }
        $redis->del(monitor_cache_key('display_payload'));
        foreach (['1h', '6h', '24h', '7d', '30d'] as $period) {
            $redis->del(monitor_cache_key('stats:' . $period));
        }
        $redis->del(monitor_cache_key('uptime_payload'));
    } catch (Throwable $e) {
    }
}

/**
 * Shared cache + singleflight: concurrent admins share one DB/docker build.
 */
function monitor_cache_singleflight(string $suffix, callable $builder, int $ttlSec = 12, int $waitMs = 2500): array {
    $cached = monitor_cache_get_json($suffix);
    if (is_array($cached)) {
        $cached['_cache'] = 'hit';
        return $cached;
    }

    $redis = app_redis();
    $lockKey = monitor_cache_key($suffix . ':lock');
    $gotLock = false;
    if ($redis) {
        try {
            $gotLock = (bool)$redis->set($lockKey, (string)getmypid(), ['NX', 'EX' => max(3, min(30, $ttlSec + 5))]);
        } catch (Throwable $e) {
            $gotLock = false;
        }
    }

    if (!$gotLock && $redis) {
        $deadline = microtime(true) + ($waitMs / 1000);
        while (microtime(true) < $deadline) {
            usleep(40000);
            $cached = monitor_cache_get_json($suffix);
            if (is_array($cached)) {
                $cached['_cache'] = 'wait';
                return $cached;
            }
        }
    }

    try {
        $payload = $builder();
        if (!is_array($payload)) {
            $payload = [];
        }
        monitor_cache_set_json($suffix, $payload, $ttlSec);
        $payload['_cache'] = $gotLock ? 'miss' : 'fallback';
        return $payload;
    } finally {
        if ($gotLock && $redis) {
            try {
                $redis->del($lockKey);
            } catch (Throwable $e) {
            }
        }
    }
}
