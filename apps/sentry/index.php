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
    if (app_is_embedded_request()) {
        app_embedded_login_notice_html(oauth_login_url());
    }
    oauth_redirect_html(oauth_login_url());
}
if ($theme !== '') {
    sentry_sync_user_theme((string)($_SESSION['user']['email'] ?? ''), $theme);
    sentry_sync_all_user_themes($theme);
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

function sentry_glitchtip_container(): string {
    foreach (['glitchtip_web', 'glitchtip-web'] as $name) {
        $running = trim((string)@shell_exec('docker inspect -f "{{.State.Running}}" ' . escapeshellarg($name) . ' 2>/dev/null'));
        if ($running === 'true') {
            return $name;
        }
    }
    return '';
}

function sentry_run_glitchtip_py(string $py): void {
    $container = sentry_glitchtip_container();
    if ($container === '') {
        return;
    }
    $cmd = 'docker exec ' . escapeshellarg($container) . ' python manage.py shell -c ' . escapeshellarg($py) . ' 2>/dev/null';
    @shell_exec($cmd);
}

function sentry_sync_user_theme(string $email, string $theme): void {
    if ($email === '' || !in_array($theme, ['light', 'dark'], true)) {
        return;
    }
    $emailJs = json_encode($email, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $themeJs = json_encode($theme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($emailJs === false || $themeJs === false) {
        return;
    }
    $py = <<<PY
from django.contrib.auth import get_user_model
User = get_user_model()
email = {$emailJs}
theme = {$themeJs}
u = User.objects.filter(email__iexact=email).first()
if u is None:
    raise SystemExit(0)
opts = dict(u.options or {})
opts["preferredTheme"] = theme
u.options = opts
u.save(update_fields=["options"])
print("ok")
PY;
    sentry_run_glitchtip_py($py);
}

function sentry_sync_all_user_themes(string $theme): void {
    if (!in_array($theme, ['light', 'dark'], true)) {
        return;
    }
    $themeJs = json_encode($theme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($themeJs === false) {
        return;
    }
    $py = <<<PY
from django.contrib.auth import get_user_model
User = get_user_model()
theme = {$themeJs}
for u in User.objects.all().iterator():
    opts = dict(u.options or {})
    if opts.get("preferredTheme") == theme:
        continue
    opts["preferredTheme"] = theme
    u.options = opts
    u.save(update_fields=["options"])
print("ok")
PY;
    sentry_run_glitchtip_py($py);
}
