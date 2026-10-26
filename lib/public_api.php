<?php
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('public-api');
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/lib/public_api_auth.php';
function public_api_json(array $data, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function public_api_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    return str_contains((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"scheme":"https"');
}
function public_api_cors(): void {
    if (headers_sent()) return;
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Device-Id, X-Request-Id');
    header('Access-Control-Max-Age: 600');
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowed = array_filter(array_map('trim', explode(',', (string)getenv('APP_CORS_ORIGINS'))));
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '') {
        $scheme = public_api_is_https() ? 'https' : 'http';
        $allowed[] = $scheme . '://' . $host;
    }
    $allowed = array_values(array_unique($allowed));
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
}
function public_api_bootstrap(): void {
    if (PHP_SESSION_NONE === session_status() && !headers_sent()) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => public_api_is_https(), 'httponly' => true, 'samesite' => 'Strict']);
        if (class_exists('Redis')) {
            require_once __DIR__ . '/redis.php';
            if (app_redis()) app_redis_configure_php_sessions();
        }
        session_start();
    }
    public_api_cors();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        if (!headers_sent()) http_response_code(204);
        exit;
    }
    if ((string)getenv('PUBLIC_API_REQUIRE_HTTPS') === '1' && !public_api_is_https()) {
        public_api_json(['ok' => false, 'error' => 'https_required'], 403);
    }
}
function public_api_client_ip(): string {
    return function_exists('getClientIp') ? getClientIp() : (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}
function public_api_check_lockout(string $lockType = 'login'): void {
    if (!function_exists('getIpLockoutSeconds')) return;
    $sec = getIpLockoutSeconds(public_api_client_ip(), $lockType);
    if ($sec > 0) public_api_json(['ok' => false, 'error' => 'rate_limited', 'retry_after' => $sec], 429);
}
function public_api_record_fail(string $lockType = 'login'): void {
    if (function_exists('recordFailedAttempt')) recordFailedAttempt(public_api_client_ip(), $lockType);
}
function public_api_reset_fails(string $lockType = 'login'): void {
    if (function_exists('resetFailedAttempts')) resetFailedAttempts(public_api_client_ip(), $lockType);
}
function public_api_device_id(): string {
    $v = trim((string)($_SERVER['HTTP_X_DEVICE_ID'] ?? ''));
    if ($v === '') return '';
    if (!preg_match('/^[a-f0-9]{32,128}$/i', $v)) return '';
    return strtolower($v);
}
function public_api_bearer_token(): string {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '');
    if ($auth === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string)$name, 'Authorization') === 0) {
                    $auth = (string)$value;
                    break;
                }
            }
        }
    }
    if (preg_match('/^\s*Bearer\s+([A-Fa-f0-9]{64})\s*$/', $auth, $m)) return $m[1];
    return '';
}
function public_api_require_device_id(): string {
    $id = public_api_device_id();
    if ($id === '') public_api_json(['ok' => false, 'error' => 'device_id_required'], 400);
    return $id;
}
function public_api_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function public_api_form_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return is_array($_POST) ? $_POST : [];
    $ct = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($ct, 'application/x-www-form-urlencoded')) {
        $data = [];
        parse_str($raw, $data);
        return is_array($data) ? $data : [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function public_api_apply_user(string $email): void {
    // Hot path: every authenticated public-api request lands here. Resolving the
    // caller used to call loadUsers(), which SELECTs the whole users table and
    // hashes every row on each request - database cost grew linearly with the
    // number of accounts and became the concurrency bottleneck. A PK-indexed
    // single-row lookup keeps the cost constant per request.
    $user = getUserByEmail($email);
    if ($user === null && $email === 'root@root') {
        // Rare bootstrap path: make sure the built-in admin row exists.
        $user = loadUsers()[$email] ?? null;
    }
    if (!$user) public_api_json(['ok' => false, 'error' => 'user_not_found'], 404);
    if ($email === 'root@root') {
        $user['role'] = 2;
        $user['verified'] = true;
    }
    $user['verified'] = (bool)($user['verified'] ?? false);
    $_SESSION['user'] = $user;
    $_SESSION['user']['email'] = $email;
    $_SESSION['auth_time'] = time();
}
function public_api_auth_user(bool $requireDevice = true): array {
    public_api_check_lockout('login');
    $token = public_api_bearer_token();
    if ($token === '') public_api_json(['ok' => false, 'error' => 'missing_token'], 401);
    $deviceId = public_api_device_id();
    $db = getDB();
    if (!$db) public_api_json(['ok' => false, 'error' => 'db_unavailable'], 500);
    $row = public_api_validate_token($db, $token, $deviceId);
    if (!$row) {
        public_api_record_fail('login');
        public_api_json(['ok' => false, 'error' => 'invalid_token'], 401);
    }
    public_api_reset_fails('login');
    $email = (string)($row['email'] ?? '');
    $_SESSION['public_api_token_app_id'] = (string)($row['app_id'] ?? '');
    public_api_apply_user($email);
    return ['email' => $email, 'token_row' => $row];
}
function public_api_check_app(string $app): void {
    $tokenApp = (string)($_SESSION['public_api_token_app_id'] ?? '');
    if ($tokenApp !== '' && $tokenApp !== 'public_api' && $tokenApp !== $app) public_api_json(['ok' => false, 'error' => 'invalid_token_app'], 403);
    $role = (int)($_SESSION['user']['role'] ?? 0);
    if (!userMeetsAppMinRole($role, $app)) public_api_json(['ok' => false, 'error' => 'app_forbidden'], 403);
    if (appAccessRequestBlocked()) public_api_json(['ok' => false, 'error' => 'app_forbidden'], 403);
}
function public_api_parse_path(): array {
    $uri = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $path = preg_replace('#^/public-api(?:/v1)?/?#', '', $uri) ?? '';
    return array_values(array_filter(explode('/', trim($path, '/')), static fn($p) => $p !== ''));
}
function public_api_role(): int {
    $email = (string)($_SESSION['user']['email'] ?? '');
    if ($email === 'root@root') return 2;
    return (int)($_SESSION['user']['role'] ?? 0);
}
function public_api_require_role(int $minRole): void {
    $role = public_api_role();
    if ($role < $minRole) {
        $names = [0 => 'Guest', 1 => 'Manager', 2 => 'Admin'];
        public_api_json([
            'ok' => false,
            'error' => 'insufficient_role',
            'required' => $minRole,
            'current' => $role,
            'message' => 'This endpoint requires ' . ($names[$minRole] ?? (string)$minRole) . ' role. Your role: ' . ($names[$role] ?? (string)$role) . '.',
        ], 403);
    }
}
function public_api_host_base(): string {
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $cfVisitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
    $scheme = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $forwardedProto === 'https'
        || str_contains($cfVisitor, '"scheme":"https"')
        || public_api_is_https()) {
        $scheme = 'https';
    }
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}
