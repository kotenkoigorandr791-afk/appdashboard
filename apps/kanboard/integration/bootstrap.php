<?php
declare(strict_types=1);

function kanboard_bridge_user(): ?array
{
    return $GLOBALS['APPDASHBOARD_KB_USER'] ?? null;
}

function kanboard_bridge_lang_map(): array
{
    return [
        'en' => 'en_US',
        'ru' => 'ru_RU',
        'uk' => 'uk_UA',
        'ua' => 'uk_UA',
        'be' => 'ru_RU',
        'by' => 'ru_RU',
        'pl' => 'pl_PL',
        'cs' => 'cs_CZ',
    ];
}

function kanboard_bridge_query_bag(): array
{
    $bag = [];
    foreach (['lang', 'theme', 'color'] as $key) {
        if (isset($_GET[$key]) && (string)$_GET[$key] !== '') {
            $bag[$key] = (string)$_GET[$key];
        }
    }
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '') {
        $q = parse_url($ref, PHP_URL_QUERY);
        if (is_string($q) && $q !== '') {
            parse_str($q, $refBag);
            if (is_array($refBag)) {
                foreach (['lang', 'theme', 'color'] as $key) {
                    if (!isset($bag[$key]) && isset($refBag[$key]) && (string)$refBag[$key] !== '') {
                        $bag[$key] = (string)$refBag[$key];
                    }
                }
            }
        }
    }
    return $bag;
}

function kanboard_bridge_normalize_lang_code(string $lang): string
{
    $lang = strtolower(trim($lang));
    $map = kanboard_bridge_lang_map();
    return isset($map[$lang]) ? $lang : 'en';
}

function kanboard_bridge_normalize_theme(string $theme): string
{
    $theme = strtolower(trim($theme));
    if ($theme === 'device') {
        $theme = 'auto';
    }
    return in_array($theme, ['light', 'dark', 'auto'], true) ? $theme : 'light';
}

function kanboard_bridge_normalize_color(string $color): string
{
    $color = trim(rawurldecode($color));
    if (stripos($color, '%23') === 0) {
        $color = '#' . substr($color, 3);
    }
    if ($color !== '' && $color[0] !== '#') {
        $color = '#' . ltrim($color, '#');
    }
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#2563eb';
}

function kanboard_bridge_read_stored_prefs(): array
{
    $out = ['lang' => '', 'theme' => '', 'color' => ''];
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['ad_kb_prefs']) && is_array($_SESSION['ad_kb_prefs'])) {
        $s = $_SESSION['ad_kb_prefs'];
        $out['lang'] = (string)($s['lang'] ?? '');
        $out['theme'] = (string)($s['theme'] ?? '');
        $out['color'] = (string)($s['color'] ?? '');
    }
    if ($out['lang'] === '' && !empty($_COOKIE['ad_kb_lang'])) {
        $out['lang'] = (string)$_COOKIE['ad_kb_lang'];
    }
    if ($out['theme'] === '' && !empty($_COOKIE['ad_kb_theme'])) {
        $out['theme'] = (string)$_COOKIE['ad_kb_theme'];
    }
    if ($out['color'] === '' && !empty($_COOKIE['ad_kb_color'])) {
        $out['color'] = (string)$_COOKIE['ad_kb_color'];
    }
    return $out;
}

function kanboard_bridge_store_prefs(string $lang, string $theme, string $color): void
{
    $lang = kanboard_bridge_normalize_lang_code($lang);
    $theme = kanboard_bridge_normalize_theme($theme);
    $color = kanboard_bridge_normalize_color($color);
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['ad_kb_prefs'] = [
            'lang' => $lang,
            'theme' => $theme,
            'color' => $color,
        ];
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $opts = [
        'expires' => time() + 86400 * 365,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ];
    $colorCookie = ltrim($color, '#');
    setcookie('ad_kb_lang', $lang, $opts);
    setcookie('ad_kb_theme', $theme, $opts);
    setcookie('ad_kb_color', $colorCookie, $opts);
    $_COOKIE['ad_kb_lang'] = $lang;
    $_COOKIE['ad_kb_theme'] = $theme;
    $_COOKIE['ad_kb_color'] = $colorCookie;
}

function kanboard_bridge_apply_shell_prefs(): void
{
    $bag = kanboard_bridge_query_bag();
    $stored = kanboard_bridge_read_stored_prefs();
    $lang = kanboard_bridge_normalize_lang_code((string)($bag['lang'] ?? ($stored['lang'] !== '' ? $stored['lang'] : 'en')));
    $theme = kanboard_bridge_normalize_theme((string)($bag['theme'] ?? ($stored['theme'] !== '' ? $stored['theme'] : 'light')));
    $color = kanboard_bridge_normalize_color((string)($bag['color'] ?? ($stored['color'] !== '' ? $stored['color'] : '#2563eb')));
    if ($bag !== [] || $stored['lang'] === '' || $stored['theme'] === '' || $stored['color'] === '') {
        kanboard_bridge_store_prefs($lang, $theme, $color);
    }
    $map = kanboard_bridge_lang_map();
    $GLOBALS['APPDASHBOARD_KB_LOCALE'] = $map[$lang] ?? 'en_US';
    $GLOBALS['APPDASHBOARD_KB_THEME'] = $theme;
    $GLOBALS['APPDASHBOARD_KB_COLOR'] = $color;
}

function kanboard_bridge_locale(): string
{
    if (!empty($GLOBALS['APPDASHBOARD_KB_LOCALE'])) {
        return (string)$GLOBALS['APPDASHBOARD_KB_LOCALE'];
    }
    kanboard_bridge_apply_shell_prefs();
    return (string)($GLOBALS['APPDASHBOARD_KB_LOCALE'] ?? 'en_US');
}

function kanboard_bridge_theme(): string
{
    if (!empty($GLOBALS['APPDASHBOARD_KB_THEME'])) {
        return (string)$GLOBALS['APPDASHBOARD_KB_THEME'];
    }
    kanboard_bridge_apply_shell_prefs();
    return (string)($GLOBALS['APPDASHBOARD_KB_THEME'] ?? 'light');
}

function kanboard_bridge_color(): string
{
    if (!empty($GLOBALS['APPDASHBOARD_KB_COLOR'])) {
        return (string)$GLOBALS['APPDASHBOARD_KB_COLOR'];
    }
    kanboard_bridge_apply_shell_prefs();
    return (string)($GLOBALS['APPDASHBOARD_KB_COLOR'] ?? '#2563eb');
}

function kanboard_db_resolve(): array
{
    require_once dirname(__DIR__, 3) . '/lib/db_config.php';
    $cfg = app_db_load_config();
    $host = trim((string)($cfg['host'] ?? ''));
    $dockerHost = getenv('DB_HOST');
    $inDocker = is_file('/.dockerenv') || getenv('APP_IN_DOCKER') === '1';
    if ($host === '' || $host === '127.0.0.1' || $host === 'localhost') {
        if (is_string($dockerHost) && $dockerHost !== '') {
            $host = $dockerHost;
        } elseif ($inDocker) {
            $host = 'appdashboard-db';
        } else {
            $host = '127.0.0.1';
        }
    }
    $schema = getenv('KANBOARD_DB_SCHEMA');
    if (!is_string($schema) || trim($schema) === '') {
        $schema = 'kanboard';
    }
    $schema = preg_replace('/[^a-zA-Z0-9_]/', '', $schema) ?: 'kanboard';
    // Same database as the main AppDashboard container; isolate via schema.
    $database = trim((string)($cfg['name'] ?? 'appdashboard'));
    if ($database === '') {
        $database = 'appdashboard';
    }
    return [
        'host' => $host,
        'port' => (int)($cfg['port'] ?? 5432),
        'user' => (string)($cfg['user'] ?? 'appdashboard'),
        'password' => (string)($cfg['password'] ?? ''),
        'database' => $database,
        'schema' => $schema,
    ];
}

function kanboard_apply_schema(PDO $pdo, ?string $schema = null): void
{
    $schema = $schema ?? (string)(kanboard_db_resolve()['schema'] ?? 'kanboard');
    $schema = preg_replace('/[^a-zA-Z0-9_]/', '', $schema) ?: 'kanboard';
    $pdo->exec('CREATE SCHEMA IF NOT EXISTS ' . $schema);
    $pdo->exec('SET search_path TO ' . $schema . ', public');
}

function kanboard_ensure_database(): void
{
    $db = kanboard_db_resolve();
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $db['host'], $db['port'], $db['database']);
    try {
        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        kanboard_apply_schema($pdo, $db['schema']);
    } catch (Throwable $e) {
        error_log('kanboard_ensure_database: ' . $e->getMessage());
    }
}

function kanboard_pdo(): ?PDO
{
    kanboard_ensure_database();
    $db = kanboard_db_resolve();
    try {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s', $db['host'], $db['port'], $db['database']),
            $db['user'],
            $db['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        kanboard_apply_schema($pdo, $db['schema']);
        return $pdo;
    } catch (Throwable $e) {
        error_log('kanboard_pdo: ' . $e->getMessage());
        return null;
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
    kanboard_bridge_apply_shell_prefs();
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
    if (empty($GLOBALS['APPDASHBOARD_KB_LOCALE']) || empty($GLOBALS['APPDASHBOARD_KB_THEME']) || empty($GLOBALS['APPDASHBOARD_KB_COLOR'])) {
        kanboard_bridge_apply_shell_prefs();
    }
    return $user;
}
