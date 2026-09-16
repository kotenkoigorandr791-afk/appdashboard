<?php
declare(strict_types=1);

function kanboard_bridge_user(): ?array
{
    return $GLOBALS['APPDASHBOARD_KB_USER'] ?? null;
}

function kanboard_bridge_locale(): string
{
    $lang = strtolower((string)($_GET['lang'] ?? 'en'));
    $map = [
        'en' => 'en_US',
        'ru' => 'ru_RU',
        'uk' => 'uk_UA',
        'ua' => 'uk_UA',
        'be' => 'ru_RU',
        'by' => 'ru_RU',
        'pl' => 'pl_PL',
        'cs' => 'cs_CZ',
    ];
    return $map[$lang] ?? 'en_US';
}

function kanboard_bridge_theme(): string
{
    $theme = strtolower((string)($_GET['theme'] ?? 'light'));
    if ($theme === 'device') {
        $theme = 'auto';
    }
    return in_array($theme, ['light', 'dark', 'auto'], true) ? $theme : 'light';
}

function kanboard_bridge_color(): string
{
    $color = (string)($_GET['color'] ?? '#2563eb');
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#2563eb';
}

function kanboard_ensure_database(): void
{
    require_once dirname(__DIR__, 3) . '/lib/db_config.php';
    $cfg = app_db_load_config();
    $host = (string)($cfg['host'] ?? '127.0.0.1');
    if ($host === '127.0.0.1' || $host === 'localhost') {
        $dockerHost = getenv('DB_HOST');
        if (is_string($dockerHost) && $dockerHost !== '') {
            $host = $dockerHost;
        } elseif (is_file('/.dockerenv') || getenv('APP_IN_DOCKER') === '1') {
            $host = 'appdashboard-db';
        }
    }
    $port = (int)($cfg['port'] ?? 5432);
    $user = (string)($cfg['user'] ?? 'appdashboard');
    $pass = (string)($cfg['password'] ?? '');
    $dbname = getenv('KANBOARD_DB_NAME') ?: 'kanboard';
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, (string)($cfg['name'] ?? 'appdashboard'));
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $st = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :n');
        $st->execute([':n' => $dbname]);
        if (!$st->fetchColumn()) {
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $dbname) ?: 'kanboard';
            $pdo->exec('CREATE DATABASE ' . $safe);
        }
    } catch (Throwable $e) {
        error_log('kanboard_ensure_database: ' . $e->getMessage());
    }
}

function kanboard_resolve_dashboard_user(): ?array
{
    require_once dirname(__DIR__, 3) . '/lib/app_session.php';
    require_once dirname(__DIR__, 3) . '/db.php';
    app_session_start();
    $email = '';
    if (!empty($_SESSION['user']['email'])) {
        $sid = session_id();
        if ($sid !== '' && function_exists('getUserFromActiveSessions') && getUserFromActiveSessions($sid) !== null) {
            $email = (string)$_SESSION['user']['email'];
        }
    }
    if ($email === '' && !empty($_COOKIE['PHPSESSID']) && function_exists('getUserFromActiveSessions')) {
        $row = getUserFromActiveSessions((string)$_COOKIE['PHPSESSID']);
        if (is_array($row) && !empty($row['email'])) {
            $email = (string)$row['email'];
        }
    }
    if ($email === '') {
        session_write_close();
        kanboard_prepare_kb_session_id();
        return null;
    }
    $user = function_exists('getUserByEmail') ? (getUserByEmail($email) ?: []) : [];
    $name = trim((string)($user['name'] ?? ($_SESSION['user']['name'] ?? '')));
    if ($name === '') {
        $name = (string)(strstr($email, '@', true) ?: $email);
    }
    $role = (int)($user['role'] ?? ($_SESSION['user']['role'] ?? 0));
    $local = strtolower((string)(strstr($email, '@', true) ?: $email));
    $username = preg_replace('/[^a-z0-9_\-\.]/', '_', $local) ?: 'user';
    $photo = (string)($user['photo'] ?? ($_SESSION['user']['photo'] ?? ''));
    if ($photo === '' || $photo === 'default_avatar.png') {
        $fromQuery = kanboard_user_data_from_query();
        if (!empty($fromQuery['photo'])) {
            $photo = (string)$fromQuery['photo'];
        }
    }
    $out = [
        'email' => strtolower($email),
        'name' => $name,
        'username' => $username,
        'role' => $role,
        'is_manager' => $role >= 1,
        'photo' => $photo,
    ];
    session_write_close();
    kanboard_prepare_kb_session_id();
    return $out;
}

function kanboard_user_data_from_query(): array
{
    $raw = (string)($_GET['user_data'] ?? '');
    if ($raw === '') {
        return [];
    }
    $json = base64_decode($raw, true);
    if (!is_string($json) || $json === '') {
        return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function kanboard_prepare_kb_session_id(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $kbSid = (string)($_COOKIE['KB_SID'] ?? '');
    if ($kbSid !== '' && preg_match('/^[A-Za-z0-9,-]{16,128}$/', $kbSid)) {
        session_id($kbSid);
        return;
    }
    if (function_exists('session_create_id')) {
        session_id(session_create_id());
        return;
    }
    session_id(bin2hex(random_bytes(16)));
}

function kanboard_require_login_or_redirect(): array
{
    $user = kanboard_resolve_dashboard_user();
    if ($user === null) {
        $u = '/login';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><script>var u=' . json_encode($u) . ';if(window!==window.top)window.top.location.href=u;else window.location.href=u;</script></head><body></body></html>';
        exit;
    }
    $GLOBALS['APPDASHBOARD_KB_USER'] = $user;
    $GLOBALS['APPDASHBOARD_KB_LOCALE'] = kanboard_bridge_locale();
    $GLOBALS['APPDASHBOARD_KB_THEME'] = kanboard_bridge_theme();
    $GLOBALS['APPDASHBOARD_KB_COLOR'] = kanboard_bridge_color();
    return $user;
}
