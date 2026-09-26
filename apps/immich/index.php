<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('immich');
require_once APP_ROOT . '/lib/app_session.php';
app_session_start();
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/project/oauth.php';
$theme = in_array((string)($_GET['theme'] ?? ''), ['light', 'dark'], true) ? (string)$_GET['theme'] : '';
if (!isset($_SESSION['user']['email']) || !ensureAuthenticatedSession()) {
    oauth_redirect_html(oauth_login_url());
}
if (getenv('APP_AUTH_SERVICE_BRIDGE') !== '1') {
    oauth_provision_user_services((string)($_SESSION['user']['email'] ?? ''), $_SESSION['user'] ?? []);
    $params = ['autoLaunch' => '1'];
    if ($theme !== '') {
        $params['theme'] = $theme;
        $params['colorTheme'] = $theme;
        $params['color-theme'] = $theme;
        $params['appearance'] = $theme;
    }
    oauth_redirect_iframe(oauth_iframe_app_url('immich') . '/auth/login?' . http_build_query($params));
}
oauth_redirect_html(oauth_app_bridge_url('immich', $theme));
