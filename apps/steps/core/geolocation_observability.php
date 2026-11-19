<?php
/**
 * Redacted geolocation metrics, structured logs, cache and health checks
 * (task 31). Redis is optional: every method degrades to a no-op or database
 * fallback and never blocks a request for long.
 */
require_once __DIR__ . '/geolocation_security.php';
require_once __DIR__ . '/geolocation_jobs.php';

final class StepsGeolocationObservability
{
    private static function redis()
    {
        if (!function_exists('app_redis')) {
            $file = dirname(__DIR__, 3) . '/lib/redis.php';
            if (is_file($file)) require_once $file;
        }
        return function_exists('app_redis') ? app_redis() : null;
    }

    private static function redisKey(string $key): string
    {
        return function_exists('app_redis_key') ? app_redis_key($key) : 'appdashboard:' . $key;
    }

    /** @param array<string,scalar|null> $labels */
    private static function field(string $name, array $labels = []): string
    {
        $name = preg_replace('/[^a-zA-Z0-9._:-]/', '_', $name) ?: 'unknown';
        if ($labels === []) return $name;
        ksort($labels);
        $parts = [];
        foreach ($labels as $key => $value) {
            $parts[] = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$key) . '=' . preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$value);
        }
        return $name . '{' . implode(',', $parts) . '}';
    }

    public static function increment(string $name, float $value = 1, array $labels = []): void
    {
        $redis = self::redis();
        if (!$redis) return;
        try { $redis->hIncrByFloat(self::redisKey('geo:metrics'), self::field($name, $labels), $value); } catch (Throwable $e) {}
    }

    public static function timing(string $name, float $milliseconds, array $labels = []): void
    {
        $redis = self::redis();
        if (!$redis) return;
        try {
            $field = self::field($name, $labels);
            $redis->hIncrByFloat(self::redisKey('geo:metrics'), $field . '_sum_ms', max(0, $milliseconds));
            $redis->hIncrBy(self::redisKey('geo:metrics'), $field . '_count', 1);
        } catch (Throwable $e) {}
    }

    public static function metrics(): array
    {
        $redis = self::redis();
        if (!$redis) return ['available' => false, 'counters' => []];
        try {
            $raw = $redis->hGetAll(self::redisKey('geo:metrics')) ?: [];
            $counters = [];
            foreach ($raw as $key => $value) $counters[(string)$key] = (float)$value;
            $latencies = [];
            foreach ($counters as $key => $value) {
                if (str_ends_with($key, '_sum_ms')) {
                    $base = substr($key, 0, -7);
                    $count = $counters[$base . '_count'] ?? 0;
                    if ($count > 0) $latencies[$base] = ['avg_ms' => round($value / $count, 2), 'count' => (int)$count];
                }
            }
            return ['available' => true, 'counters' => $counters, 'latency' => $latencies, 'at' => gmdate('c')];
        } catch (Throwable $e) {
            return ['available' => false, 'counters' => []];
        }
    }

    public static function log(string $level, string $event, array $context = [], string $correlationId = ''): void
    {
        $entry = [
            'ts' => gmdate('c'),
            'level' => strtolower($level),
            'module' => 'steps.geolocation',
            'event' => preg_replace('/[^a-zA-Z0-9._-]/', '_', $event) ?: 'unknown',
            'correlation_id' => substr(preg_replace('/[^A-Za-z0-9._:-]/', '', $correlationId) ?: '', 0, 80),
            'context' => StepsGeolocationSecurity::redactContext($context),
        ];
        error_log(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // ---------------------------------------------------------------------
    // Safe derived-result cache
    // ---------------------------------------------------------------------

    public static function version(string $owner, string $namespace = 'map'): int
    {
        $redis = self::redis();
        if (!$redis) return 0;
        try {
            $key = self::redisKey('geo:ver:' . hash('sha256', StepsGeolocationSecurity::normalizeOwner($owner)) . ':' . $namespace);
            $value = $redis->get($key);
            return $value === false ? 0 : (int)$value;
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function invalidateOwner(string $owner, string $namespace = 'map'): void
    {
        $redis = self::redis();
        if (!$redis) return;
        try {
            $key = self::redisKey('geo:ver:' . hash('sha256', StepsGeolocationSecurity::normalizeOwner($owner)) . ':' . $namespace);
            $redis->incr($key);
            $redis->expire($key, 86400);
        } catch (Throwable $e) {}
    }

    public static function cacheKey(string $namespace, string $owner, array $parts): string
    {
        $version = self::version($owner, $namespace);
        return 'geo:cache:' . $namespace . ':' . hash('sha256', StepsGeolocationSecurity::normalizeOwner($owner) . '|' . json_encode($parts, JSON_UNESCAPED_SLASHES) . '|' . $version);
    }

    public static function cacheGet(string $key): ?array
    {
        $redis = self::redis();
        if (!$redis) return null;
        try {
            $raw = $redis->get($key);
            if ($raw === false || !is_string($raw)) return null;
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function cacheSet(string $key, array $value, int $ttl = 20): void
    {
        $redis = self::redis();
        if (!$redis) return;
        try { $redis->setex($key, max(1, min(300, $ttl)), json_encode($value, JSON_UNESCAPED_SLASHES)); } catch (Throwable $e) {}
    }

    // ---------------------------------------------------------------------
    // Health/readiness
    // ---------------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function health(PDO $pdo): array
    {
        $checks = [];
        $ok = true;
        $degraded = false;

        $started = microtime(true);
        try {
            $version = (string)$pdo->query('SELECT postgis_full_version()')->fetchColumn();
            $checks['postgis'] = ['ok' => $version !== '', 'version' => strstr($version, 'POSTGIS', true) ?: substr($version, 0, 60)];
            if ($version === '') { $ok = false; $degraded = true; }
        } catch (Throwable $e) {
            $checks['postgis'] = ['ok' => false, 'error' => 'unavailable'];
            $ok = false;
            $degraded = true;
        }
        $checks['db'] = ['ok' => true, 'latency_ms' => round((microtime(true) - $started) * 1000, 2)];

        try {
            $checks['migration'] = ['version' => (string)$pdo->query('SELECT COALESCE(MAX(version), \'\') FROM geo_schema_migrations')->fetchColumn()];
        } catch (Throwable $e) {
            $checks['migration'] = ['version' => 'unknown'];
        }

        try {
            $backlog = StepsGeolocationJobs::backlog($pdo);
            $checks['queue'] = $backlog;
            if ($backlog['pending_age_s'] > max(60, (int)(getenv('GEO_QUEUE_AGE_ALERT_S') ?: 900))) $degraded = true;
            if ($backlog['stalled'] > 0) $degraded = true;
        } catch (Throwable $e) {
            $checks['queue'] = ['error' => 'unavailable'];
            $degraded = true;
        }

        try {
            $root = dirname(__DIR__, 3);
            $free = @disk_free_space($root);
            $total = @disk_total_space($root);
            $percent = $total && $total > 0 ? round(100 * (1 - $free / $total), 1) : null;
            $checks['storage'] = ['free_bytes' => $free !== false ? (int)$free : null, 'used_percent' => $percent];
            if ($percent !== null && $percent >= (float)(getenv('GEO_STORAGE_ALERT_PERCENT') ?: 90)) $degraded = true;
            $size = (float)$pdo->query('SELECT COALESCE(SUM(size_bytes),0) FROM geo_artifacts WHERE deleted_at IS NULL')->fetchColumn();
            $checks['artifacts'] = ['bytes' => (int)$size];
        } catch (Throwable $e) {
            $checks['storage'] = ['error' => 'unavailable'];
        }

        $redis = self::redis();
        if ($redis) {
            try {
                $checks['redis'] = ['ok' => (string)$redis->ping() !== '', 'used_memory' => null];
            } catch (Throwable $e) {
                $checks['redis'] = ['ok' => false];
                $degraded = true;
            }
        } else {
            $checks['redis'] = ['ok' => false, 'optional' => true];
        }

        if (!$ok) $status = 'fail';
        elseif ($degraded) $status = 'degraded';
        else $status = 'ok';
        return [
            'status' => $status,
            'ready' => $status !== 'fail',
            'at' => gmdate('c'),
            'checks' => $checks,
            'metrics' => self::metrics(),
        ];
    }
}
