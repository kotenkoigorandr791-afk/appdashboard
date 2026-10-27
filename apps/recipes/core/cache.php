<?php
// Redis-backed cache helpers for the recipes app.
//
// The config and state tables are read on every request but change very
// rarely. apps/recipes/core/init.php caches their contents in Redis for a
// short TTL; every write path calls recipes_cache_invalidate() so the cached
// copy never serves stale data.

if (!function_exists('recipes_cache_keys')) {
    function recipes_cache_keys(): array
    {
        return ['recipes:cfg', 'recipes:state'];
    }
}

if (!function_exists('recipes_cache_invalidate')) {
    function recipes_cache_invalidate(): void
    {
        try {
            require_once __DIR__ . '/../../../lib/redis.php';
            if (!function_exists('app_redis')) return;
            $redis = app_redis();
            if (!$redis) return;
            foreach (recipes_cache_keys() as $key) {
                try {
                    $redis->del(app_redis_key($key));
                } catch (Throwable $e) {
                    // A cache invalidation failure must never break the write
                    // that triggered it - the TTL will expire the entry.
                }
            }
        } catch (Throwable $e) {
            // Ignore: cache invalidation is best-effort.
        }
    }
}
