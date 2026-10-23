<?php
declare(strict_types=1);
require_once __DIR__ . '/app_session.php';
require_once __DIR__ . '/redis.php';
function app_guard_deny(int $code, string $error): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $error, 'message' => $error]);
    exit;
}
function app_guard_session_user(string $bucket = '', int $maxPerMinute = 0): string {
    app_session_start();
    $email = strtolower(trim((string)($_SESSION['user']['email'] ?? '')));
    if ($email === '') {
        app_guard_deny(401, 'unauthorized');
    }
    session_write_close();
    if ($bucket !== '' && $maxPerMinute > 0) {
        $redis = app_redis();
        if ($redis) {
            try {
                $key = app_redis_key('rl:' . $bucket . ':' . sha1($email) . ':' . intdiv(time(), 60));
                $n = (int)$redis->incr($key);
                if ($n === 1) $redis->expire($key, 70);
                if ($n > $maxPerMinute) {
                    header('Retry-After: 60');
                    app_guard_deny(429, 'rate_limited');
                }
            } catch (Throwable $e) {
            }
        }
    }
    return $email;
}
