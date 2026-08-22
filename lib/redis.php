<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/storage_helper.php';
function app_redis_load_file_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = [];
    $path = getenv('APP_DB_CONFIG');
    if (!is_string($path) || $path === '' || !is_readable($path)) $path = appdata_data_dir() . '/db.env';
    if (is_readable($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $cfg[strtoupper(trim($k))] = trim($v);
        }
    }
    return $cfg;
}
function app_redis_config(): array {
    $fileCfg = app_redis_load_file_config();
    return [
        'host' => (string)(getenv('REDIS_HOST') ?: ($fileCfg['REDIS_HOST'] ?? 'appdashboard-redis')),
        'port' => (int)(getenv('REDIS_PORT') ?: ($fileCfg['REDIS_PORT'] ?? 6379)),
        'password' => (string)(getenv('REDIS_PASSWORD') ?: ($fileCfg['REDIS_PASSWORD'] ?? '')),
        'database' => (int)(getenv('REDIS_DATABASE') ?: ($fileCfg['REDIS_DATABASE'] ?? 0)),
        'prefix' => (string)(getenv('REDIS_PREFIX') ?: ($fileCfg['REDIS_PREFIX'] ?? 'appdashboard:')),
        'timeout' => (float)(getenv('REDIS_TIMEOUT') ?: ($fileCfg['REDIS_TIMEOUT'] ?? 1.5)),
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
