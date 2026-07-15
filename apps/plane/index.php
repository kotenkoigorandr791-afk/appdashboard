<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 2));
$__cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
if (PHP_SESSION_NONE === session_status()) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $__cookieSecure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/project/oauth.php';
$isLoggedIn = isset($_SESSION['user']['email']) && getUserFromActiveSessions(session_id()) !== null;
$currentHost = (string)($_SERVER['HTTP_HOST'] ?? '');
if (!$isLoggedIn) {
    $u = 'https://serverkherson.xyz/login';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><script>var u=' . json_encode($u) . ';if(window!==window.top)window.top.location.href=u;else window.location.href=u;</script></head><body></body></html>';
    exit;
}
if ($currentHost === 'serverkherson.xyz') {
    oauth_sync_plane_admin((string)($_SESSION['user']['email'] ?? ''), (int)($_SESSION['user']['role'] ?? 0) >= 2);
    header('Location: https://plane.serverkherson.xyz/auth/gitea/?next_path=/');
    exit;
}
$bridgeToken = oauth_create_bridge_token(session_id());
$qs = http_build_query(array_filter([
    'app' => 'plane',
    'lang' => (string)($_GET['lang'] ?? ''),
    'theme' => (string)($_GET['theme'] ?? ''),
    'color' => (string)($_GET['color'] ?? ''),
]));
$u = 'https://serverkherson.xyz/oauth-bridge?t=' . urlencode($bridgeToken) . '&next=' . urlencode('/main?' . $qs);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><script>var u=' . json_encode($u) . ';if(window!==window.top)window.top.location.href=u;else window.location.href=u;</script></head><body></body></html>';
exit;
