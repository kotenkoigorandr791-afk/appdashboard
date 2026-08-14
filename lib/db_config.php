<?php
declare(strict_types=1);
if (!defined('APP_DB_CONFIG_LOADED')) {
    define('APP_DB_CONFIG_LOADED', true);
}
function app_db_config_path(): string {
    $env = getenv('APP_DB_CONFIG');
    if (is_string($env) && $env !== '' && is_readable($env)) return $env;
    if (is_readable('/opt/AppDashboard/data/db.env')) return '/opt/AppDashboard/data/db.env';
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
        'connect_timeout' => '3',
        'connect_retries' => '5',
        'connect_retry_delay_ms' => '300',
    ];
    $path = app_db_config_path();
    if (is_readable($path)) {
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
            if ($k === 'db_connect_timeout') $cfg['connect_timeout'] = $v;
            if ($k === 'db_connect_retries') $cfg['connect_retries'] = $v;
            if ($k === 'db_connect_retry_delay_ms') $cfg['connect_retry_delay_ms'] = $v;
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
        'DB_CONNECT_TIMEOUT' => 'connect_timeout',
        'DB_CONNECT_RETRIES' => 'connect_retries',
        'DB_CONNECT_RETRY_DELAY_MS' => 'connect_retry_delay_ms',
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
function app_db_is_transient_connection_error(Throwable $e): bool {
    if (!$e instanceof PDOException) return false;
    $state = (string)$e->getCode();
    $message = strtolower($e->getMessage());
    if (in_array($state, ['08000','08001','08003','08004','08006','08007','57P01','57P02','57P03'], true)) return true;
    foreach (['connection', 'server closed', 'timeout', 'could not connect', 'connection refused', 'terminating connection'] as $needle) {
        if (str_contains($message, $needle)) return true;
    }
    return false;
}
function app_pdo_open(array $c): PDO {
    $timeout = max(1, (int)$c['connect_timeout']);
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;connect_timeout=%d', $c['host'], $c['port'], $c['name'], $c['sslmode'], $timeout);
    $pdo = new PDO($dsn, $c['user'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
    if (!app_db_skip_session_init()) {
        $pdo->exec("SET client_encoding TO 'UTF8'");
        $pdo->exec("SET timezone TO 'UTC'");
        $pdo->exec('SET search_path TO public');
    }
    return $pdo;
}
function app_pdo_connect(bool $fresh = false): PDO {
    static $pdo = null;
    if (!$fresh && $pdo instanceof PDO) {
        try {
            if (!app_db_skip_session_init()) $pdo->exec('SET search_path TO public');
            return $pdo;
        } catch (PDOException $e) {
            if (!app_db_is_transient_connection_error($e)) throw $e;
            $pdo = null;
        }
    }
    $c = app_db_load_config();
    $retries = max(1, (int)$c['connect_retries']);
    $delayMs = max(0, (int)$c['connect_retry_delay_ms']);
    $last = null;
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        try {
            $new = app_pdo_open($c);
            if (!$fresh) $pdo = $new;
            return $new;
        } catch (PDOException $e) {
            $last = $e;
            if (!app_db_is_transient_connection_error($e) || $attempt === $retries) break;
            usleep($delayMs * 1000 * $attempt);
        }
    }
    if ($last instanceof PDOException) throw $last;
    throw new PDOException('Database connection failed');
}
function app_db_dsn_label(): string {
    $c = app_db_load_config();
    return $c['name'] . '@' . $c['host'] . ':' . $c['port'];
}
