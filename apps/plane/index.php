<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('plane');
require_once APP_ROOT . '/lib/app_session.php';
app_session_start();
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/project/oauth.php';
$isLoggedIn = isset($_SESSION['user']['email']) && getUserFromActiveSessions(session_id()) !== null;
$theme = in_array((string)($_GET['theme'] ?? ''), ['light', 'dark'], true) ? (string)$_GET['theme'] : '';
if (!$isLoggedIn) {
    oauth_redirect_html(oauth_login_url());
}
if (getenv('APP_AUTH_SERVICE_BRIDGE') !== '1') {
    oauth_provision_user_services((string)($_SESSION['user']['email'] ?? ''), $_SESSION['user'] ?? []);
    oauth_sync_plane_admin((string)($_SESSION['user']['email'] ?? ''), (int)($_SESSION['user']['role'] ?? 0) >= 2, $theme);
    $params = ['next_path' => $theme !== '' ? '/?theme=' . $theme : '/'];
    if ($theme !== '') $params['theme'] = $theme;
    header('Location: ' . oauth_external_app_url('plane') . '/auth/gitea/?' . http_build_query($params));
    exit;
}
oauth_redirect_html(oauth_app_bridge_url('plane', $theme));
