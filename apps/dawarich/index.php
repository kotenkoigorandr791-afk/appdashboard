<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('dawarich');
require_once APP_ROOT . '/lib/app_session.php';
app_session_start();
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/project/oauth.php';
$theme = in_array((string)($_GET['theme'] ?? ''), ['light', 'dark'], true) ? (string)$_GET['theme'] : '';
if (!isset($_SESSION['user']['email']) || !ensureAuthenticatedSession()) {
    oauth_redirect_html(oauth_login_url());
}
if (getenv('APP_AUTH_SERVICE_BRIDGE') !== '1') {
    $email = (string)($_SESSION['user']['email'] ?? '');
    oauth_provision_user_services($email, $_SESSION['user'] ?? []);
    if ($theme !== '') {
        dawarich_sync_user_theme($email, $theme);
        dawarich_sync_all_user_themes($theme);
    }
    oauth_redirect_iframe(oauth_iframe_app_url('dawarich') . '/users/sign_in');
}
oauth_redirect_html(oauth_app_bridge_url('dawarich', $theme));

function dawarich_db(): ?PDO {
    static $pdo = null;
    static $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    $cfg = dawarich_db_config();
    if ($cfg === null) return null;
    try {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s', $cfg['host'], $cfg['port'], $cfg['dbname']),
            $cfg['user'],
            $cfg['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
    } catch (\Exception $e) {
        $pdo = null;
    }
    return $pdo;
}

function dawarich_db_config(): ?array {
    static $cfg = null;
    if ($cfg !== null) return $cfg ?: null;
    $envFile = '/opt/docker/dawarich/.env';
    $vars = [];
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $vars[trim($k)] = trim($v, " \t\"'");
        }
    }
    $password = (string)($vars['DATABASE_PASSWORD'] ?? $vars['POSTGRES_PASSWORD'] ?? '');
    if ($password === '') {
        $cfg = false;
        return null;
    }
    $cfg = [
        'host' => '127.0.0.1',
        'port' => 5435,
        'dbname' => (string)($vars['DATABASE_NAME'] ?? $vars['POSTGRES_DB'] ?? 'dawarich_development'),
        'user' => (string)($vars['DATABASE_USERNAME'] ?? $vars['POSTGRES_USER'] ?? 'postgres'),
        'password' => $password,
    ];
    return $cfg;
}

function dawarich_sync_user_theme(string $email, string $theme): void {
    if ($email === '' || !in_array($theme, ['light', 'dark'], true)) return;
    $pdo = dawarich_db();
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare('UPDATE users SET theme = :theme, updated_at = NOW() WHERE lower(email) = lower(:email) AND deleted_at IS NULL');
        $stmt->execute([':theme' => $theme, ':email' => $email]);
    } catch (\Exception $e) {
    }
}

function dawarich_sync_all_user_themes(string $theme): void {
    if (!in_array($theme, ['light', 'dark'], true)) return;
    $pdo = dawarich_db();
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare('UPDATE users SET theme = :theme, updated_at = NOW() WHERE deleted_at IS NULL');
        $stmt->execute([':theme' => $theme]);
    } catch (\Exception $e) {
    }
}
