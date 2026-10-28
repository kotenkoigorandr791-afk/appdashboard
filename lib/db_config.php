<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/storage_helper.php';
if (!defined('APP_DB_CONFIG_LOADED')) {
    define('APP_DB_CONFIG_LOADED', true);
}
function app_db_config_path(): string {
    $env = getenv('APP_DB_CONFIG');
    if (is_string($env) && $env !== '' && is_readable($env)) return $env;
    return appdata_data_dir() . '/db.env';
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
        'persistent' => '1',
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
            if ($k === 'db_persistent') $cfg['persistent'] = $v;
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
        'DB_PERSISTENT' => 'persistent',
    ];
    foreach ($envMap as $env => $key) {
        $value = getenv($env);
        if (is_string($value) && $value !== '') {
            $cfg[$key] = $value;
        }
    }
    $cfg['host'] = app_db_normalize_host((string)$cfg['host']);
    if ($cfg['host'] === '') {
        $cfg['host'] = 'appdashboard-db';
    }
    return $cfg;
}
function app_db_is_pgsql(): bool {
    return true;
}
function app_db_normalize_host(string $host): string {
    $host = trim($host);
    if ($host === '') return '';
    // host-gateway is the Docker *host* alias from extra_hosts, never the
    // Postgres service. Using it as a DB host yields
    // SQLSTATE[08006] "could not translate host name".
    if (strcasecmp($host, 'host-gateway') === 0) {
        $fallback = getenv('APPDASHBOARD_DB_HOST');
        if (is_string($fallback) && trim($fallback) !== '' && strcasecmp(trim($fallback), 'host-gateway') !== 0) {
            return trim($fallback);
        }
        return 'appdashboard-db';
    }
    return $host;
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
function app_db_truthy(string $value): bool {
    $value = strtolower(trim($value));
    return $value !== '' && !in_array($value, ['0', 'false', 'no', 'off'], true);
}
function app_db_persistent_enabled(): bool {
    static $enabled = null;
    if ($enabled === null) {
        $enabled = app_db_truthy((string)app_db_load_config()['persistent']);
    }
    return $enabled;
}
/**
 * Reclaim a persistent connection before a new request uses it.
 *
 * A persistent socket is shared between requests in the same FPM worker, so
 * anything the previous request left behind must be cleared:
 * - ROLLBACK aborts any transaction the previous request never closed,
 *   whether it was opened via PDO::beginTransaction() or a raw `BEGIN`
 *   (recipes' post handlers use raw SQL, so PDO::inTransaction() cannot be
 *   trusted here). It also unblocks a session left in an *aborted*
 *   transaction (an error that left the block open), a state in which no
 *   SELECT-based check could even run. Outside of a transaction ROLLBACK is
 *   a no-op: PostgreSQL only emits a WARNING, the statement still succeeds
 *   and PHP raises no error.
 * - search_path is restored because user-schema connections
 *   (AppDatabase::connectUserSchema) point it at a custom schema, and with
 *   APP_DB_SKIP_SESSION_INIT=1 no other code resets it on a reused socket.
 */
function app_pdo_checkout_reset(PDO $pdo): void {
    $pdo->exec('ROLLBACK');
    $pdo->exec('SET search_path TO public');
}
function app_pdo_open(array $c, ?bool $persistent = null): PDO {
    $timeout = max(1, (int)$c['connect_timeout']);
    $host = app_db_normalize_host((string)$c['host']);
    if ($host === '') {
        throw new PDOException('Database host is not configured');
    }
    if ($persistent === null) {
        $persistent = app_db_persistent_enabled();
    }
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;connect_timeout=%d', $host, $c['port'], $c['name'], $c['sslmode'], $timeout);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ];
    if ($persistent) {
        $options[PDO::ATTR_PERSISTENT] = true;
    }
    $pdo = new PDO($dsn, $c['user'], $c['password'], $options);
    if ($persistent) {
        // A pooled socket may have been left dirty by the previous request.
        app_pdo_checkout_reset($pdo);
    }
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
    if ($c['driver'] !== 'pgsql') {
        throw new RuntimeException('Only PostgreSQL is supported');
    }
    $retries = max(1, (int)$c['connect_retries']);
    $delayMs = max(0, (int)$c['connect_retry_delay_ms']);
    $last = null;
    // fresh=true must always use a non-persistent connection so it really
    // opens a brand-new socket, bypassing the persistent pool (reconnect()
    // and connectUserSchema() rely on this, e.g. after a database restart).
    $usePersistent = !$fresh && app_db_persistent_enabled();
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        try {
            $new = app_pdo_open($c, $usePersistent);
            if (!$fresh) $pdo = $new;
            return $new;
        } catch (PDOException $e) {
            $last = $e;
            if (!app_db_is_transient_connection_error($e) || $attempt === $retries) break;
            if ($usePersistent) {
                // The pooled socket is dead (e.g. database restarted). A retry
                // delay would not fix a broken pool entry; fall back to a
                // fresh non-persistent connection immediately.
                $usePersistent = false;
                continue;
            }
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
