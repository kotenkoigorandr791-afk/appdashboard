<?php
declare(strict_types=1);
require_once __DIR__ . '/security/request.php';
function app_session_is_https(): bool {
    return appSecurityIsHttps();
}
function app_session_cookie_params(): array {
    return [
        'lifetime' => 0,
        'path' => '/',
        'secure' => app_session_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}
function app_session_start(): void {
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) return;
    require_once __DIR__ . '/redis.php';
    app_redis_configure_php_sessions();
    session_set_cookie_params(app_session_cookie_params());
    ini_set('session.gc_maxlifetime', '43200');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}
