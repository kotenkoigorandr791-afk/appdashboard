<?php
declare(strict_types=1);
if (!defined('APP_DB_CONFIG_LOADED')) {
    define('APP_DB_CONFIG_LOADED', true);
}
function app_db_config_path(): string {
    $env = getenv('APP_DB_CONFIG');
    if (is_string($env) && $env !== '' && is_file($env)) return $env;
    if (is_file('/opt/AppDashboard/data/db.env')) return '/opt/AppDashboard/data/db.env';
    return dirname(__DIR__) . '/data/db.env';
}
function app_db_load_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'port' => '5432',
        'name' => 'appdashboard',
        'user' => 'appdashboard',
        'password' => '',
        'sslmode' => 'prefer',
    ];
    $path = app_db_config_path();
    if (is_file($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = strtolower(trim($k));
            $v = trim($v);
            if ($k === 'db_driver') $cfg['driver'] = $v;
            if ($k === 'db_host') $cfg['host'] = $v;
            if ($k === 'db_port') $cfg['port'] = $v;
            if ($k === 'db_name') $cfg['name'] = $v;
            if ($k === 'db_user') $cfg['user'] = $v;
            if ($k === 'db_password') $cfg['password'] = $v;
            if ($k === 'db_sslmode') $cfg['sslmode'] = $v;
        }
    }
    $envMap = [
        'DB_DRIVER' => 'driver',
        'DB_HOST' => 'host',
        'DB_PORT' => 'port',
        'DB_NAME' => 'name',
        'DB_USER' => 'user',
        'DB_PASSWORD' => 'password',
        'DB_SSLMODE' => 'sslmode',
    ];
    foreach ($envMap as $env => $key) {
        $value = getenv($env);
        if (is_string($value) && $value !== '') {
            $cfg[$key] = $value;
        }
    }
    return $cfg;
}
function app_db_is_pgsql(): bool {
    return app_db_load_config()['driver'] === 'pgsql';
}
function app_db_skip_session_init(): bool {
    return getenv('APP_DB_SKIP_SESSION_INIT') === '1';
}
function app_pdo_connect(bool $fresh = false): PDO {
    static $pdo = null;
    if (!$fresh && $pdo instanceof PDO) {
        if (!app_db_skip_session_init()) $pdo->exec('SET search_path TO public');
        return $pdo;
    }
    $c = app_db_load_config();
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $c['host'], $c['port'], $c['name'], $c['sslmode']);
    $new = new PDO($dsn, $c['user'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
    if (!app_db_skip_session_init()) {
        $new->exec("SET client_encoding TO 'UTF8'");
        $new->exec("SET timezone TO 'UTC'");
        $new->exec('SET search_path TO public');
    }
    if (!$fresh) $pdo = $new;
    return $new;
}
function app_db_dsn_label(): string {
    $c = app_db_load_config();
    return $c['name'] . '@' . $c['host'] . ':' . $c['port'];
}
