<?php
require_once __DIR__ . '/db.php';
function tgMiniCookieSecure(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}
function tgMiniRememberSecret(): string {
    $token = trim(getConfig('tg_bot_token', ''));
    if ($token !== '') return hash('sha256', $token, true);
    return hash('sha256', __DIR__, true);
}
function tgMiniRememberBuild(string $email, int $ttl = 2592000): string {
    $exp = time() + max(60, $ttl);
    $data = $email . '|' . $exp;
    $sig = hash_hmac('sha256', $data, tgMiniRememberSecret());
    return base64_encode($data . '|' . $sig);
}
function tgMiniRememberRead(?string $raw): ?string {
    if (!is_string($raw) || $raw === '') return null;
    $dec = base64_decode($raw, true);
    if (!is_string($dec) || $dec === '') return null;
    $parts = explode('|', $dec);
    if (count($parts) !== 3) return null;
    [$email, $expRaw, $sig] = $parts;
    $exp = (int)$expRaw;
    if ($email === '' || $exp <= time()) return null;
    $calc = hash_hmac('sha256', $email . '|' . $exp, tgMiniRememberSecret());
    if (!hash_equals($calc, $sig)) return null;
    $users = loadUsers();
    if (!isset($users[$email])) return null;
    $u = $users[$email];
    if ((int)($u['role'] ?? 0) < 2 || (int)($u['verified'] ?? 0) !== 1) return null;
    return $email;
}
function tgMiniRememberSet(string $email): void {
    setcookie('tg_mini_auth', tgMiniRememberBuild($email), ['expires' => time() + 2592000, 'path' => '/', 'secure' => tgMiniCookieSecure(), 'httponly' => true, 'samesite' => 'Lax']);
}
function tgMiniSessionStart(): void {
    if (PHP_SESSION_NONE !== session_status()) return;
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => tgMiniCookieSecure(), 'httponly' => true, 'samesite' => 'Lax']);
    if (function_exists('ini_set')) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
    }
    session_start();
}
function tgMiniRestoreSessionFromCookie(): bool {
    tgMiniSessionStart();
    $email = tgMiniRememberRead($_COOKIE['tg_mini_auth'] ?? null);
    if ($email === null) return false;
    $users = loadUsers();
    $u = $users[$email] ?? null;
    if (!is_array($u)) return false;
    session_regenerate_id(true);
    $_SESSION['user'] = $u;
    $_SESSION['user']['email'] = $email;
    $_SESSION['auth_time'] = time();
    setLastActiveUser($email);
    cleanupExpiredUserSessions($email);
    addUserToActiveSessions($email, session_id(), $_SESSION['user']);
    return true;
}
