<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/app_session.php';
app_session_start();
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/project/oauth.php';
$isLoggedIn = isset($_SESSION['user']['email']) && getUserFromActiveSessions(session_id()) !== null;
$currentHost = (string)($_SERVER['HTTP_HOST'] ?? '');
$theme = in_array((string)($_GET['theme'] ?? ''), ['light', 'dark'], true) ? (string)$_GET['theme'] : '';
if (!$isLoggedIn) {
    $u = 'https://serverkherson.xyz/login';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><script>var u=' . json_encode($u) . ';if(window!==window.top)window.top.location.href=u;else window.location.href=u;</script></head><body></body></html>';
    exit;
}
if ($currentHost === 'serverkherson.xyz') {
    oauth_provision_user_services((string)($_SESSION['user']['email'] ?? ''), $_SESSION['user'] ?? []);
    $params = ['autoLaunch' => '1'];
    if ($theme !== '') {
        $params['theme'] = $theme;
        $params['colorTheme'] = $theme;
        $params['color-theme'] = $theme;
        $params['appearance'] = $theme;
    }
    header('Location: https://photo.serverkherson.xyz/auth/login?' . http_build_query($params));
    exit;
}
$bridgeToken = oauth_create_bridge_token(session_id());
$qs = http_build_query(array_filter([
    'app' => 'immich',
    'lang' => (string)($_GET['lang'] ?? ''),
    'theme' => $theme,
    'color' => (string)($_GET['color'] ?? ''),
]));
$u = 'https://serverkherson.xyz/oauth-bridge?t=' . urlencode($bridgeToken) . '&next=' . urlencode('/main?' . $qs);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><script>var u=' . json_encode($u) . ';if(window!==window.top)window.top.location.href=u;else window.location.href=u;</script></head><body></body></html>';
exit;
