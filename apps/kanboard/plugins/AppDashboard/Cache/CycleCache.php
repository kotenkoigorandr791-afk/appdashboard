<?php

namespace Kanboard\Plugin\AppDashboard\Cache;

/**
 * Redis cache for the board cycle panel (stats, burnup, assignees).
 *
 * Falls back to a per-request static array when Redis is unavailable so the
 * board keeps working in stripped-down environments.
 */
class CycleCache
{
    const TTL = 45;
    const PREFIX = 'appdashboard:kb:cycle:';

    /** @var \Redis|null|false */
    private static $redis = null;

    /** @var array */
    private static $memory = array();

    public static function get(int $projectId)
    {
        $key = self::key($projectId);

        if (isset(self::$memory[$key])) {
            return self::$memory[$key];
        }

        $redis = self::redis();
        if ($redis === null) {
            return null;
        }

        try {
            $value = $redis->get($key);
        } catch (\Throwable $e) {
            return null;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $data = json_decode($value, true);
        if (! is_array($data)) {
            return null;
        }

        self::$memory[$key] = $data;
        return $data;
    }

    public static function set(int $projectId, array $data): void
    {
        $key = self::key($projectId);
        self::$memory[$key] = $data;

        $redis = self::redis();
        if ($redis === null) {
            return;
        }

        try {
            $redis->setex($key, self::ttl(), json_encode($data, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
        }
    }

    public static function forget(int $projectId): void
    {
        $key = self::key($projectId);
        unset(self::$memory[$key]);

        $redis = self::redis();
        if ($redis === null) {
            return;
        }

        try {
            $redis->del($key);
        } catch (\Throwable $e) {
        }
    }

    private static function key(int $projectId): string
    {
        return self::PREFIX.$projectId;
    }

    private static function ttl(): int
    {
        $ttl = (int) (getenv('APPDASHBOARD_CYCLE_CACHE_TTL') ?: self::TTL);
        return $ttl > 0 ? $ttl : self::TTL;
    }

    /**
     * @return \Redis|null
     */
    private static function redis()
    {
        if (self::$redis !== null) {
            return self::$redis === false ? null : self::$redis;
        }

        if (! class_exists('Redis')) {
            self::$redis = false;
            return null;
        }

        $host = getenv('REDIS_HOST') ?: 'appdashboard-redis';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);
        $timeout = 0.2;

        try {
            $redis = new \Redis();
            if (! $redis->connect($host, $port, $timeout)) {
                self::$redis = false;
                return null;
            }
            $redis->setOption(\Redis::OPT_READ_TIMEOUT, 0.5);
            self::$redis = $redis;
        } catch (\Throwable $e) {
            self::$redis = false;
            return null;
        }

        return self::$redis;
    }
}
