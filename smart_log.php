<?php
if (defined('SMART_LOG_PHP_LOADED')) {
    return;
}
define('SMART_LOG_PHP_LOADED', '1');
function smart_log_db_config_path(): string {
    return '/opt/AppDashboard/data/db.env';
}
function smart_log_sqlite_path(): string {
    return smart_log_db_config_path();
}
function cloud_safe_path_label(string $path, string $fallback = '[hidden path]'): string {
    $normalized = trim(str_replace('\\', '/', $path));
    $normalized = trim($normalized, "\"' \t\n\r\0\x0B");
    if ($normalized === '') {
        return $fallback;
    }
    $normalized = rtrim($normalized, '/');
    if ($normalized === '') {
        return $fallback;
    }
    $base = basename($normalized);
    return $base !== '' && $base !== '.' ? $base : $fallback;
}
function cloud_sanitize_paths_in_text(string $text): string {
    if ($text === '') {
        return '';
    }
    $urlPlaceholders = [];
    $text = preg_replace_callback('/https?:\/\/\S+/i', static function (array $matches) use (&$urlPlaceholders): string {
        $key = '__CLOUD_SAFE_URL_' . count($urlPlaceholders) . '__';
        $urlPlaceholders[$key] = $matches[0];
        return $key;
    }, $text) ?? $text;
    $pattern = '/(?<!:)(?<![A-Za-z0-9_])((?:[A-Za-z]:[\\\\\\/]|\\/)(?:[^\\s"\'<>|]+(?:[\\\\\\/][^\\s"\'<>|]+)*))/u';
    $sanitized = preg_replace_callback($pattern, static function (array $matches): string {
        $raw = $matches[1] ?? '';
        if ($raw === '') {
            return '';
        }
        $trimmed = rtrim($raw, ".,:;!?)\\]}");
        $suffix = substr($raw, strlen($trimmed));
        return cloud_safe_path_label($trimmed) . $suffix;
    }, $text);
    if (!is_string($sanitized)) {
        $sanitized = $text;
    }
    if ($urlPlaceholders) {
        $sanitized = strtr($sanitized, $urlPlaceholders);
    }
    return $sanitized;
}
function cloud_safe_error_log(string $message): void {
    error_log(cloud_sanitize_paths_in_text($message));
}
function smart_log_ensure_schema(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS smart_app_logs (
        signature   TEXT PRIMARY KEY NOT NULL,
        app_id      TEXT NOT NULL DEFAULT '',
        level       TEXT NOT NULL DEFAULT 'error',
        script      TEXT NOT NULL DEFAULT '',
        source_file TEXT NOT NULL DEFAULT '',
        source_line INTEGER,
        message     TEXT NOT NULL,
        hit_count   INTEGER NOT NULL DEFAULT 1,
        first_seen  INTEGER NOT NULL,
        last_seen   INTEGER NOT NULL
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_smart_app_logs_app_last ON smart_app_logs(app_id, last_seen DESC)");
}
function smart_log_conn(): AppDatabase {
    static $db = null;
    if ($db instanceof AppDatabase) {
        return $db;
    }
    require_once dirname(__FILE__) . '/lib/app_db.php';
    $db = app_main_db();
    $db->enableExceptions(true);
    smart_log_ensure_schema($db);
    return $db;
}
function smart_log_normalize_message(string $msg): string {
    $msg = cloud_sanitize_paths_in_text($msg);
    $msg = preg_replace('/\b\d+\b/', '#', $msg) ?? $msg;
    return substr($msg, 0, 4000);
}
function smart_log_signature(string $appId, string $file, ?int $line, string $level, string $message): string {
    $norm = smart_log_normalize_message($message);
    return hash('sha256', $appId . "\x1e" . $file . "\x1e" . (string)(int)$line . "\x1e" . $level . "\x1e" . $norm);
}
function smart_log_script(): string {
    $s = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    return substr($s, 0, 1024);
}
function smart_log_errno_level(int $errno): string {
    switch ($errno) {
        case E_WARNING:
        case E_USER_WARNING:
        case E_COMPILE_WARNING:
        case E_CORE_WARNING:
            return 'warning';
        case E_NOTICE:
        case E_USER_NOTICE:
        case E_STRICT:
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            return 'notice';
        default:
            return 'error';
    }
}
function smart_log_app_id(): string {
    return defined('SMART_LOG_APP_ID') && is_string(SMART_LOG_APP_ID) && SMART_LOG_APP_ID !== '' ? SMART_LOG_APP_ID : 'unknown';
}
function smart_log_write(string $appId, string $level, string $message, string $sourceFile = '', ?int $sourceLine = null, bool $alsoPhpErrorLog = true): void {
    $message = cloud_sanitize_paths_in_text($message);
    $sourceFile = cloud_safe_path_label($sourceFile, '');
    if ($alsoPhpErrorLog) {
        cloud_safe_error_log($message);
    }
    static $in = false;
    if ($in) {
        return;
    }
    $in = true;
    try {
        $db = smart_log_conn();
        $script = smart_log_script();
        $sig = smart_log_signature($appId, $sourceFile, $sourceLine, $level, $message);
        $now = time();
        $stmt = $db->prepare('INSERT INTO smart_app_logs (signature,app_id,level,script,source_file,source_line,message,hit_count,first_seen,last_seen)
            VALUES (:sig,:app,:lvl,:scr,:sf,:sl,:msg,1,:ts,:ts)
            ON CONFLICT(signature) DO UPDATE SET hit_count = smart_app_logs.hit_count + 1, last_seen = excluded.last_seen, script = excluded.script');
        $stmt->bindValue(':sig', $sig, SQLITE3_TEXT);
        $stmt->bindValue(':app', substr($appId, 0, 128), SQLITE3_TEXT);
        $stmt->bindValue(':lvl', substr($level, 0, 32), SQLITE3_TEXT);
        $stmt->bindValue(':scr', $script, SQLITE3_TEXT);
        $stmt->bindValue(':sf', substr($sourceFile, 0, 1024), SQLITE3_TEXT);
        if ($sourceLine === null) {
            $stmt->bindValue(':sl', null, SQLITE3_NULL);
        } else {
            $stmt->bindValue(':sl', $sourceLine, SQLITE3_INTEGER);
        }
        $stmt->bindValue(':msg', substr($message, 0, 8000), SQLITE3_TEXT);
        $stmt->bindValue(':ts', $now, SQLITE3_INTEGER);
        $stmt->execute();
    } catch (Throwable $e) {
        cloud_safe_error_log('smart_log_write: ' . $e->getMessage());
    } finally {
        $in = false;
    }
}
function smart_log_bootstrap(string $appId): void {
    if (defined('SMART_LOG_ACTIVE')) {
        return;
    }
    define('SMART_LOG_ACTIVE', '1');
    if (!defined('SMART_LOG_APP_ID')) {
        define('SMART_LOG_APP_ID', $appId);
    }
    set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        $lvl = smart_log_errno_level($errno);
        $msg = $errstr . ' in ' . $errfile . ':' . $errline;
        smart_log_write(smart_log_app_id(), $lvl, $msg, $errfile, $errline, false);
        return false;
    });
    set_exception_handler(static function (Throwable $e): void {
        $msg = 'Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        smart_log_write(smart_log_app_id(), 'error', $msg, $e->getFile(), $e->getLine(), true);
    });
    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $t = $err['type'] ?? 0;
        if (!in_array($t, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }
        $file = (string)($err['file'] ?? '');
        $line = (int)($err['line'] ?? 0);
        $msg = (string)($err['message'] ?? '') . ' in ' . $file . ':' . $line;
        smart_log_write(smart_log_app_id(), 'critical', $msg, $file, $line ?: null, true);
    });
}
function smart_log_bootstrap_auto(string $callerDir): void {
    $dir = str_replace('\\', '/', $callerDir);
    $app = 'site';
    if (preg_match('#/apps/([^/]+)#', $dir, $m)) {
        $app = $m[1];
    }
    smart_log_bootstrap($app);
}
