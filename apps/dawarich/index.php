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
if (!$isLoggedIn) {
    $u = 'https://serverkherson.xyz/login';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><script>var u=' . json_encode($u) . ';if(window!==window.top)window.top.location.href=u;else window.location.href=u;</script></head><body></body></html>';
    exit;
}
if ($currentHost === 'serverkherson.xyz') {
    if ($theme !== '') {
        dawarich_sync_user_theme((string)($_SESSION['user']['email'] ?? ''), $theme);
        dawarich_sync_all_user_themes($theme);
    }
    $u = 'https://dawarich.serverkherson.xyz/users/sign_in';
    header('Location: ' . $u);
    exit;
}
$bridgeToken = oauth_create_bridge_token(session_id());
$qs = http_build_query(array_filter([
    'app' => 'dawarich',
    'lang' => (string)($_GET['lang'] ?? ''),
    'theme' => $theme,
    'color' => (string)($_GET['color'] ?? ''),
]));
$u = 'https://serverkherson.xyz/oauth-bridge?t=' . urlencode($bridgeToken) . '&next=' . urlencode('/main?' . $qs);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><script>var u=' . json_encode($u) . ';if(window!==window.top)window.top.location.href=u;else window.location.href=u;</script></head><body></body></html>';
exit;

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
