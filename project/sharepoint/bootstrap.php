<?php
/**
 * SharePoint bootstrap — shared by the public /s/{token} routes, the app share
 * APIs and the admin screen. Intentionally lightweight: no shell auth redirect.
 */

if (!defined('SHAREPOINT_ROOT')) {
    define('SHAREPOINT_ROOT', __DIR__);
}
if (!defined('SHAREPOINT_PUBLIC_PREFIX')) {
    define('SHAREPOINT_PUBLIC_PREFIX', '/s');
}
if (!defined('SHAREPOINT_ASSET_BASE')) {
    define('SHAREPOINT_ASSET_BASE', '/project/sharepoint/assets');
}

require_once dirname(__DIR__, 2) . '/db.php';
if (is_file(dirname(__DIR__, 2) . '/lib/app_version.php')) {
    require_once dirname(__DIR__, 2) . '/lib/app_version.php';
}

if (is_file(dirname(__DIR__, 2) . '/lib/app_session.php')) {
    require_once dirname(__DIR__, 2) . '/lib/app_session.php';
    if (function_exists('app_session_start') && session_status() !== PHP_SESSION_ACTIVE) {
        @app_session_start();
    }
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

require_once SHAREPOINT_ROOT . '/interfaces/ResourceHandlerInterface.php';
require_once SHAREPOINT_ROOT . '/interfaces/AppHandlerInterface.php';
require_once SHAREPOINT_ROOT . '/services/Share.php';
require_once SHAREPOINT_ROOT . '/services/Resource.php';
require_once SHAREPOINT_ROOT . '/services/AccessResult.php';
require_once SHAREPOINT_ROOT . '/services/ShareTokenService.php';
require_once SHAREPOINT_ROOT . '/services/ShareRepository.php';
require_once SHAREPOINT_ROOT . '/services/ShareAccessPolicy.php';
require_once SHAREPOINT_ROOT . '/services/ResourceResolver.php';
require_once SHAREPOINT_ROOT . '/services/ShareService.php';
require_once SHAREPOINT_ROOT . '/registry.php';
require_once SHAREPOINT_ROOT . '/lib/files.php';
require_once SHAREPOINT_ROOT . '/controllers/FileHandler.php';
require_once SHAREPOINT_ROOT . '/controllers/FolderHandler.php';
require_once SHAREPOINT_ROOT . '/controllers/AppHandler.php';
require_once SHAREPOINT_ROOT . '/handlers/RecipeShareHandler.php';
require_once SHAREPOINT_ROOT . '/handlers/ExpenseChartShareHandler.php';
require_once SHAREPOINT_ROOT . '/handlers/StepRoutineShareHandler.php';
require_once SHAREPOINT_ROOT . '/handlers/FilmShareHandler.php';
require_once SHAREPOINT_ROOT . '/handlers/SmartHomeSceneShareHandler.php';
require_once SHAREPOINT_ROOT . '/renderers/base.php';
require_once SHAREPOINT_ROOT . '/renderers/file.php';
require_once SHAREPOINT_ROOT . '/renderers/folder.php';
require_once SHAREPOINT_ROOT . '/renderers/recipe.php';
require_once SHAREPOINT_ROOT . '/renderers/expense_chart.php';
require_once SHAREPOINT_ROOT . '/renderers/step_routine.php';
require_once SHAREPOINT_ROOT . '/renderers/film.php';
require_once SHAREPOINT_ROOT . '/renderers/smart_home_scene.php';

// ── Default registry ─────────────────────────────────────────────────────────
ShareRegistry::register('file', new ShareFileHandler());
ShareRegistry::register('folder', new ShareFolderHandler());

ShareRegistry::registerApp(new RecipeShareHandler());
ShareRegistry::registerApp(new ExpenseChartShareHandler());
ShareRegistry::registerApp(new StepRoutineShareHandler());
ShareRegistry::registerApp(new FilmShareHandler());
ShareRegistry::registerApp(new SmartHomeSceneShareHandler());

// ── Small, dependency-free helpers ───────────────────────────────────────────
if (!function_exists('sp_h')) {
    function sp_h(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sp_lang')) {
    function sp_lang(): string
    {
        static $lang = null;
        if ($lang !== null) {
            return $lang;
        }
        $requested = strtolower(trim((string)($_GET['lang'] ?? '')));
        if (in_array($requested, ['en', 'uk', 'ru', 'be', 'pl', 'cs'], true)) {
            $_SESSION['lang'] = $requested;
        }
        $candidate = strtolower((string)($requested !== '' ? $requested : ($_SESSION['lang'] ?? 'en')));
        $lang = in_array($candidate, ['en', 'uk', 'ru', 'be', 'pl', 'cs'], true) ? $candidate : 'en';
        return $lang;
    }
}

if (!function_exists('sp_languages')) {
    /** @return array<string,string> */
    function sp_languages(): array
    {
        return [
            'en' => 'English',
            'uk' => 'Українська',
            'ru' => 'Русский',
            'be' => 'Беларуская',
            'pl' => 'Polski',
            'cs' => 'Čeština',
        ];
    }
}

if (!function_exists('sp_lang_name')) {
    function sp_lang_name(?string $code = null): string
    {
        $languages = sp_languages();
        $code = $code ?? sp_lang();
        return $languages[$code] ?? $languages['en'];
    }
}

if (!function_exists('sp_theme')) {
    function sp_theme(): string
    {
        $candidate = strtolower((string)($_GET['theme'] ?? $_COOKIE['theme'] ?? 'auto'));
        return in_array($candidate, ['light', 'dark', 'auto'], true) ? $candidate : 'auto';
    }
}

if (!function_exists('sp_translations')) {
    /** @return array<string,string> */
    function sp_translations(?string $lang = null): array
    {
        static $cache = [];
        $lang = $lang ?? sp_lang();
        $lang = preg_replace('/[^a-z]/', '', strtolower($lang));
        if ($lang === '') {
            $lang = 'en';
        }
        if (isset($cache[$lang])) {
            return $cache[$lang];
        }
        $load = static function (string $code): array {
            $file = SHAREPOINT_ROOT . '/i18n/' . $code . '.json';
            if (!is_file($file)) {
                return [];
            }
            $data = json_decode((string)file_get_contents($file), true);
            return is_array($data) ? $data : [];
        };
        $fallback = $load('en');
        $data = $lang === 'en' ? $fallback : $load($lang);
        return $cache[$lang] = array_merge($fallback, $data);
    }
}

if (!function_exists('sp_t')) {
    function sp_t(string $key, array $replace = []): string
    {
        $map = sp_translations();
        $key = strtolower($key);
        $text = $map[$key] ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string)$value, $text);
        }
        return $text;
    }
}

if (!function_exists('sp_asset')) {
    function sp_asset(string $path): string
    {
        $file = SHAREPOINT_ROOT . '/assets/' . ltrim($path, '/');
        $version = is_file($file) ? (string)filemtime($file) : '1';
        return SHAREPOINT_ASSET_BASE . '/' . ltrim($path, '/') . '?v=' . $version;
    }
}

if (!function_exists('sp_app_version_info')) {
    function sp_app_version_info(): array
    {
        if (!function_exists('appdashboard_version_info')) {
            return ['label' => '', 'date' => '', 'tooltip' => ''];
        }
        $info = appdashboard_version_info();
        $label = trim((string)($info['label'] ?? ''));
        $date = '';
        if ($label !== '') {
            $date = (($info['source'] ?? '') === 'env')
                ? (string)($info['build_date_human'] ?? '')
                : (string)($info['updated_at_human'] ?? '');
        }
        $tt = [];
        foreach (['branch' => $info['branch'] ?? '', 'full' => $info['full'] ?? '', 'updated_at' => $info['updated_at'] ?? '', 'build_date' => $info['build_date'] ?? ''] as $k => $v) {
            if (trim((string)$v) !== '') {
                $tt[] = $k . ': ' . $v;
            }
        }
        return ['label' => $label, 'date' => $date, 'tooltip' => implode("\n", $tt)];
    }
}

if (!function_exists('sp_footer_version_html')) {
    function sp_footer_version_html(): string
    {
        $info = sp_app_version_info();
        if ($info['label'] === '') {
            return '';
        }
        $text = $info['label'];
        if ($info['date'] !== '') {
            $text .= ' · ' . $info['date'];
        }
        return '<span class="sp-footer__version" title="' . sp_h($info['tooltip'] !== '' ? $info['tooltip'] : $info['label']) . '">' . sp_h(sp_t('version')) . ': ' . sp_h($text) . '</span>';
    }
}

if (!function_exists('sp_public_url')) {
    function sp_public_url(string $token, string $suffix = ''): string
    {
        // Prefer the origin the request actually came from. In production the
        // Host header is the public domain; during local development it keeps
        // generated links on localhost instead of sending the viewer to the
        // production host (where the session and route may differ).
        $scheme = 'http';
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            $scheme = 'https';
        }
        $host = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ''))[0]);
        $base = $host !== '' ? $scheme . '://' . $host : (function_exists('app_public_url') ? app_public_url('') : '');
        if ($base === '') {
            $base = $scheme . '://localhost';
        }
        return rtrim($base, '/') . SHAREPOINT_PUBLIC_PREFIX . '/' . rawurlencode($token) . $suffix;
    }
}

if (!function_exists('sp_session_user')) {
    function sp_session_user(): array
    {
        if (isset($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['email'])) {
            return $_SESSION['user'];
        }
        foreach (['email', 'user_email'] as $key) {
            if (!empty($_SESSION[$key])) {
                return ['email' => (string)$_SESSION[$key], 'role' => (int)($_SESSION['role'] ?? 0)];
            }
        }
        $email = '';
        foreach (array_unique(array_filter([session_id(), (string)($_COOKIE['PHPSESSID'] ?? '')])) as $sid) {
            try {
                $stmt = getDB()->prepare('SELECT email, user_data FROM active_sessions WHERE session_id = :sid LIMIT 1');
                $stmt->bindValue(':sid', $sid, PDO::PARAM_STR);
                $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['email'])) {
                    $data = json_decode((string)($row['user_data'] ?? ''), true) ?: [];
                    return ['email' => (string)$row['email'], 'role' => (int)($data['role'] ?? 0)];
                }
            } catch (Throwable $e) {
            }
        }
        return [];
    }
}

if (!function_exists('sp_current_email')) {
    function sp_current_email(): string
    {
        $user = sp_session_user();
        return strtolower(trim((string)($user['email'] ?? '')));
    }
}

if (!function_exists('sp_is_admin')) {
    function sp_is_admin(): bool
    {
        $user = sp_session_user();
        return !empty($user['email']) && (int)($user['role'] ?? 0) >= 2;
    }
}

if (!function_exists('sp_csrf_token')) {
    function sp_csrf_token(): string
    {
        if (empty($_SESSION['sharepoint_csrf'])) {
            $_SESSION['sharepoint_csrf'] = bin2hex(random_bytes(24));
        }
        return (string)$_SESSION['sharepoint_csrf'];
    }
}

if (!function_exists('sp_verify_csrf')) {
    function sp_verify_csrf(?string $token): bool
    {
        $expected = (string)($_SESSION['sharepoint_csrf'] ?? '');
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}

if (!function_exists('sp_json_response')) {
    function sp_json_response(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('sp_find_share')) {
    function sp_find_share(string $token): ?Share
    {
        $repo = new ShareRepository();
        return $repo->findByToken($token);
    }
}

if (!function_exists('sp_request_body')) {
    function sp_request_body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return $_POST;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_merge($_POST, $decoded) : $_POST;
    }
}

if (!function_exists('sp_format_bytes')) {
    function sp_format_bytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}

if (!function_exists('sp_time_ago')) {
    function sp_time_ago(?string $timestamp): string
    {
        if (!$timestamp) return '';
        $ts = strtotime($timestamp);
        if (!$ts) return '';
        return date('M j, Y H:i', $ts);
    }
}

if (!function_exists('sp_jwt_encode')) {
    function sp_jwt_encode(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $h = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $h . '.' . $p, $secret, true);
        return $h . '.' . $p . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    }
}
