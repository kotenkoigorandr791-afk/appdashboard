<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('gitea');
require_once APP_ROOT . '/lib/app_session.php';
app_session_start();
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/project/oauth.php';
$isLoggedIn = isset($_SESSION['user']['email']) && getUserFromActiveSessions(session_id()) !== null;
$themeParam = (string)($_GET['theme'] ?? '');
$cookieTheme = (string)($_COOKIE['browser_theme'] ?? '');
$theme = in_array($themeParam, ['light', 'dark', 'auto'], true) ? $themeParam : (in_array($cookieTheme, ['light', 'dark'], true) ? $cookieTheme : 'light');
$giteaTheme = $theme === 'auto' ? 'gitea-auto' : ($theme === 'dark' ? 'gitea-dark' : 'gitea-light');
if (!$isLoggedIn) {
    oauth_redirect_html(oauth_login_url());
}
if (getenv('APP_AUTH_SERVICE_BRIDGE') !== '1') {
    oauth_provision_user_services((string)($_SESSION['user']['email'] ?? ''), $_SESSION['user'] ?? []);
    gitea_sync_user_avatar((string)($_SESSION['user']['email'] ?? ''), (string)($_SESSION['user']['photo'] ?? ''));
    gitea_sync_user_theme((string)($_SESSION['user']['email'] ?? ''), $giteaTheme);
    gitea_sync_all_user_themes($giteaTheme);
    gitea_clear_sessions();
    $u = oauth_external_app_url('gitea') . '/user/oauth2/AppDashboard';
    if ($theme !== '') $u .= '?' . http_build_query(['theme' => $giteaTheme]);
    header('Location: ' . $u);
    exit;
}
oauth_redirect_html(oauth_app_bridge_url('gitea', $theme));

function gitea_clear_sessions(): void {
    $pdo = oauth_gitea_db();
    if (!$pdo) return;
    try {
        $pdo->exec("DELETE FROM session");
    } catch (\Exception $e) {
    }
}

function gitea_sync_user_theme(string $email, string $theme): void {
    if ($email === '' || !in_array($theme, ['gitea-light', 'gitea-dark', 'gitea-auto'], true)) return;
    $pdo = oauth_gitea_db();
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare('UPDATE "user" SET theme = :theme WHERE lower(email) = lower(:email)');
        $stmt->execute([':theme' => $theme, ':email' => $email]);
    } catch (\Exception $e) {
    }
}

function gitea_sync_all_user_themes(string $theme): void {
    if (!in_array($theme, ['gitea-light', 'gitea-dark', 'gitea-auto'], true)) return;
    $pdo = oauth_gitea_db();
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare('UPDATE "user" SET theme = :theme WHERE theme <> :theme');
        $stmt->execute([':theme' => $theme]);
    } catch (\Exception $e) {
    }
}

function gitea_sync_user_avatar(string $email, string $photo): void {
    if ($email === '') return;
    $avatar = gitea_avatar_url($photo);
    if ($avatar === '') return;
    $pdo = oauth_gitea_db();
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare('UPDATE "user" SET avatar = :avatar, avatar_email = :email, use_custom_avatar = false WHERE lower(email) = lower(:email)');
        $stmt->execute([':avatar' => $avatar, ':email' => $email]);
    } catch (\Exception $e) {
    }
}

function gitea_avatar_url(string $photo): string {
    $photo = trim($photo);
    if ($photo === '' || $photo === 'default_avatar.png') return '';
    if (preg_match('#^https?://#i', $photo)) return $photo;
    return oauth_dashboard_photo_url($photo);
}
