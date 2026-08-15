<?php
declare(strict_types=1);
function app_redis_config(): array {
    return [
        'host' => (string)(getenv('REDIS_HOST') ?: 'appdashboard-redis'),
        'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        'password' => (string)(getenv('REDIS_PASSWORD') ?: ''),
        'database' => (int)(getenv('REDIS_DATABASE') ?: 0),
        'prefix' => (string)(getenv('REDIS_PREFIX') ?: 'appdashboard:'),
        'timeout' => (float)(getenv('REDIS_TIMEOUT') ?: 1.5),
    ];
}
function app_redis(): ?Redis {
    static $redis = false;
    if ($redis instanceof Redis) return $redis;
    if ($redis === null || !class_exists('Redis')) return null;
    $cfg = app_redis_config();
    try {
        $r = new Redis();
        if (!$r->connect($cfg['host'], $cfg['port'], $cfg['timeout'])) {
            $redis = null;
            return null;
        }
        if ($cfg['password'] !== '' && !$r->auth($cfg['password'])) {
            $redis = null;
            return null;
        }
        if ($cfg['database'] > 0) $r->select($cfg['database']);
        $redis = $r;
        return $redis;
    } catch (Throwable $e) {
        $redis = null;
        return null;
    }
}
function app_redis_key(string $key): string {
    return app_redis_config()['prefix'] . $key;
}
function app_redis_configure_php_sessions(): void {
    if (!class_exists('Redis') || !app_redis()) return;
    $cfg = app_redis_config();
    $savePath = 'tcp://' . $cfg['host'] . ':' . $cfg['port'] . '?database=' . $cfg['database'] . '&timeout=' . $cfg['timeout'] . '&prefix=' . rawurlencode($cfg['prefix'] . 'php_session:');
    if ($cfg['password'] !== '') $savePath .= '&auth=' . rawurlencode($cfg['password']);
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', $savePath);
}
