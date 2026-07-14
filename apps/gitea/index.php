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
$theme = in_array((string)($_GET['theme'] ?? ''), ['light', 'dark'], true) ? (string)$_GET['theme'] : '';
$giteaTheme = $theme === 'dark' ? 'gitea-dark' : 'gitea';
if (!$isLoggedIn) {
    $u = 'https://serverkherson.xyz/login';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><script>var u=' . json_encode($u) . ';if(window!==window.top)window.top.location.href=u;else window.location.href=u;</script></head><body></body></html>';
    exit;
}
if ($currentHost === 'serverkherson.xyz') {
    gitea_sync_user_theme((string)($_SESSION['user']['email'] ?? ''), $giteaTheme);
    gitea_clear_sessions();
    $u = 'https://git.serverkherson.xyz/user/oauth2/AppDashboard';
    if ($theme !== '') $u .= '?' . http_build_query(['theme' => $giteaTheme]);
    header('Location: ' . $u);
    exit;
}
$bridgeToken = oauth_create_bridge_token(session_id());
$qs = http_build_query(array_filter([
    'app' => 'gitea',
    'lang' => (string)($_GET['lang'] ?? ''),
    'theme' => $theme,
    'color' => (string)($_GET['color'] ?? ''),
]));
$u = 'https://serverkherson.xyz/oauth-bridge?t=' . urlencode($bridgeToken) . '&next=' . urlencode('/main?' . $qs);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><script>var u=' . json_encode($u) . ';if(window!==window.top)window.top.location.href=u;else window.location.href=u;</script></head><body></body></html>';
exit;

function gitea_clear_sessions(): void {
    try {
        $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=gitea', 'gitea', 'gitea', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $pdo->exec("DELETE FROM session");
    } catch (\Exception $e) {
    }
}

function gitea_sync_user_theme(string $email, string $theme): void {
    if ($email === '' || !in_array($theme, ['gitea', 'gitea-dark'], true)) return;
    try {
        $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=gitea', 'gitea', 'gitea', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $stmt = $pdo->prepare('UPDATE "user" SET theme = :theme WHERE lower(email) = lower(:email)');
        $stmt->execute([':theme' => $theme, ':email' => $email]);
    } catch (\Exception $e) {
    }
}
