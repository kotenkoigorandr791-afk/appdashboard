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

/**
 * Re-emit the PHP session cookie. lifetime=0 keeps a browser-session cookie;
 * a positive lifetime (e.g. 365 days) is required for "remember me"/trusted devices
 * so closing the browser does not drop the session id on every platform.
 */
function app_session_refresh_cookie(int $lifetimeSeconds = 0): void {
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) return;
    $params = app_session_cookie_params();
    $lifetimeSeconds = max(0, $lifetimeSeconds);
    $params['lifetime'] = $lifetimeSeconds;
    if ($lifetimeSeconds > 0) {
        $gc = (int)ini_get('session.gc_maxlifetime');
        if ($gc < $lifetimeSeconds) {
            ini_set('session.gc_maxlifetime', (string)$lifetimeSeconds);
        }
    }
    $expires = $lifetimeSeconds > 0 ? time() + $lifetimeSeconds : 0;
    setcookie(session_name(), session_id(), [
        'expires' => $expires,
        'path' => $params['path'],
        'secure' => !empty($params['secure']),
        'httponly' => !empty($params['httponly']),
        'samesite' => (string)($params['samesite'] ?? 'Lax'),
    ]);
}
