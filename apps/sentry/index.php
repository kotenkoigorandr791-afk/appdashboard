<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('sentry-app');
require_once APP_ROOT . '/lib/app_session.php';
app_session_start();
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/project/oauth.php';
$theme = in_array((string)($_GET['theme'] ?? ''), ['light', 'dark'], true) ? (string)$_GET['theme'] : '';
if (!isset($_SESSION['user']['email']) || !ensureAuthenticatedSession()) {
    // Embedded documents must never get a silent login: show an explicit action.
    if (app_is_embedded_request()) {
        app_embedded_login_notice_html(oauth_login_url());
    }
    oauth_redirect_html(oauth_login_url());
}
if (getenv('APP_AUTH_SERVICE_BRIDGE') !== '1') {
    oauth_provision_user_services((string)($_SESSION['user']['email'] ?? ''), $_SESSION['user'] ?? []);
    $callback = rtrim(oauth_external_app_url('sentry'), '/') . '/accounts/oidc/appdashboard/login/callback/';
    $authQs = http_build_query([
        'client_id' => 'sentry',
        'redirect_uri' => $callback,
        'response_type' => 'code',
        'scope' => 'openid email profile',
    ]);
    oauth_redirect_iframe(rtrim(oauth_public_origin(), '/') . '/authorize?' . $authQs);
}
oauth_redirect_html(oauth_app_bridge_url('sentry', $theme));
