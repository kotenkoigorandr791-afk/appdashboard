<?php
declare(strict_types=1);
function app_session_is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}
function app_session_cookie_params(bool $adminStrict = false): array {
    return [
        'lifetime' => 0,
        'path' => '/',
        'secure' => app_session_is_https(),
        'httponly' => true,
        'samesite' => $adminStrict ? 'Strict' : 'Lax',
    ];
}
function app_session_refresh_cookie_params(bool $adminStrict = false): void {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $params = app_session_cookie_params($adminStrict);
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($params);
    }
    if (!headers_sent()) {
        setcookie(session_name(), session_id(), [
            'expires' => 0,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }
}
function app_session_start(): void {
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) return;
    require_once __DIR__ . '/redis.php';
    app_redis_configure_php_sessions();
    $adminStrict = false;
    session_set_cookie_params(app_session_cookie_params($adminStrict));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
    if (!empty($_SESSION['admin_session']) || (isset($_SESSION['user']['role']) && (int)$_SESSION['user']['role'] === 2)) {
        app_session_refresh_cookie_params(true);
    }
}
