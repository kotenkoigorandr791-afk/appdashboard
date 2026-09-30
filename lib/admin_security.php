<?php
declare(strict_types=1);

const ADMIN_CAPABILITIES = ['users', 'ssh', 'files', 'docker', 'secrets', 'integrations', 'database'];
const ADMIN_SESSION_IDLE_SECONDS = 7200;
const ADMIN_PASSWORD_MIN_LENGTH = 10;

function adminSecurityEnsureSchema(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (
        id BIGSERIAL PRIMARY KEY,
        email TEXT NOT NULL DEFAULT '',
        action TEXT NOT NULL DEFAULT '',
        target TEXT NOT NULL DEFAULT '',
        ip_address TEXT NOT NULL DEFAULT '',
        user_agent TEXT NOT NULL DEFAULT '',
        result TEXT NOT NULL DEFAULT 'ok',
        meta TEXT NOT NULL DEFAULT '{}',
        created_at INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_audit_created ON admin_audit_log(created_at DESC)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_audit_email ON admin_audit_log(email)");
    try { $db->exec("ALTER TABLE users ADD COLUMN admin_capabilities TEXT NOT NULL DEFAULT ''"); } catch (Exception $e) {}
    $done = true;
}

function adminMetaGet(string $key, string $default = ''): string {
    try {
        $db = getDB();
        $row = $db->querySingle("SELECT value FROM app_meta WHERE key=" . $db->quote($key));
        return is_string($row) ? $row : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function adminMetaSet(string $key, string $value): void {
    $db = getDB();
    monitorRetentionSetMeta($db, $key, $value);
}

function adminAuditLog(string $action, string $target = '', string $result = 'ok', array $meta = []): void {
    try {
        adminSecurityEnsureSchema();
        $db = getDB();
        $email = (string)($_SESSION['user']['email'] ?? '');
        $stmt = $db->prepare("INSERT INTO admin_audit_log(email,action,target,ip_address,user_agent,result,meta,created_at)
            VALUES(:e,:a,:t,:ip,:ua,:r,:m,:c)");
        $stmt->bindValue(':e', $email);
        $stmt->bindValue(':a', substr($action, 0, 128));
        $stmt->bindValue(':t', substr($target, 0, 512));
        $stmt->bindValue(':ip', function_exists('getClientIp') ? getClientIp() : (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $stmt->bindValue(':ua', substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512));
        $stmt->bindValue(':r', substr($result, 0, 64));
        $stmt->bindValue(':m', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $stmt->bindValue(':c', time(), PDO::PARAM_INT);
        $stmt->execute();
        $critical = ['role_change', 'user_delete', 'password_reset', 'ssh_init', 'fm_write', 'secrets_save'];
        if (in_array($action, $critical, true) && function_exists('notifyTelegramFailedAuth')) {
            notifyTelegramFailedAuth('admin_action', [
                'action' => $action,
                'target' => $target,
                'result' => $result,
                'email' => $email,
                'ip' => function_exists('getClientIp') ? getClientIp() : '',
            ]);
        }
    } catch (Throwable $e) {}
}

function adminIsAdminUser(?array $user = null): bool {
    $user = $user ?? ($_SESSION['user'] ?? null);
    if (!is_array($user)) return false;
    $email = (string)($user['email'] ?? '');
    if ($email === 'root@root') return true;
    return (int)($user['role'] ?? 0) === 2;
}

function adminDefaultCapabilities(): array {
    return ADMIN_CAPABILITIES;
}

function adminParseCapabilities($raw): array {
    if (is_array($raw)) {
        $list = $raw;
    } else {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '*') return adminDefaultCapabilities();
        $decoded = json_decode($raw, true);
        $list = is_array($decoded) ? $decoded : (preg_split('/\s*,\s*/', $raw) ?: []);
    }
    $out = [];
    foreach ($list as $c) {
        $c = strtolower(trim((string)$c));
        if (in_array($c, ADMIN_CAPABILITIES, true)) $out[] = $c;
    }
    return array_values(array_unique($out));
}

function adminUserCapabilities(?array $user = null): array {
    $user = $user ?? ($_SESSION['user'] ?? null);
    if (!adminIsAdminUser($user)) return [];
    $email = (string)($user['email'] ?? '');
    if ($email === 'root@root') return adminDefaultCapabilities();
    adminSecurityEnsureSchema();
    $caps = adminParseCapabilities($user['admin_capabilities'] ?? '');
    return $caps === [] ? adminDefaultCapabilities() : $caps;
}

function adminHasCapability(string $capability, ?array $user = null): bool {
    $capability = strtolower(trim($capability));
    if ($capability === '') return false;
    return in_array($capability, adminUserCapabilities($user), true);
}

function adminRequireCapability(string $capability): void {
    if (adminHasCapability($capability)) return;
    adminAuditLog('capability_denied', $capability, 'denied');
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'capability_required', 'capability' => $capability]);
    exit;
}

function adminIpAllowlist(): array {
    $fromEnv = trim((string)getenv('APP_ADMIN_IP_ALLOWLIST'));
    $fromMeta = trim(adminMetaGet('admin_ip_allowlist', ''));
    $raw = $fromEnv !== '' ? $fromEnv : $fromMeta;
    if ($raw === '') return [];
    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (filter_var($p, FILTER_VALIDATE_IP) || str_contains($p, '/')) $out[] = $p;
    }
    return array_values(array_unique($out));
}

function adminIpInCidr(string $ip, string $cidr): bool {
    if ($ip === $cidr) return true;
    if (!str_contains($cidr, '/')) return false;
    [$subnet, $mask] = explode('/', $cidr, 2);
    if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($subnet, FILTER_VALIDATE_IP)) return false;
    $mask = (int)$mask;
    $ipBin = @inet_pton($ip);
    $subBin = @inet_pton($subnet);
    if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) return false;
    $maxBits = strlen($ipBin) * 8;
    if ($mask < 0 || $mask > $maxBits) return false;
    $bytes = intdiv($mask, 8);
    $bits = $mask % 8;
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subBin, 0, $bytes)) return false;
    if ($bits === 0) return true;
    $maskByte = (~((1 << (8 - $bits)) - 1)) & 0xFF;
    return (ord($ipBin[$bytes]) & $maskByte) === (ord($subBin[$bytes]) & $maskByte);
}

function adminIpAllowed(?string $ip = null): bool {
    $list = adminIpAllowlist();
    if ($list === []) return true;
    $ip = $ip ?? (function_exists('getClientIp') ? getClientIp() : (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    foreach ($list as $entry) {
        if (adminIpInCidr($ip, $entry)) return true;
    }
    return false;
}

function adminRequireIpAllowlist(): void {
    if (adminIpAllowed()) return;
    adminAuditLog('ip_blocked', '', 'denied');
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'admin_ip_forbidden']);
    exit;
}

function adminWeakPasswords(): array {
    return ['root', 'password', 'admin', '123456', '12345678', 'qwerty', 'letmein', 'welcome', 'passw0rd', 'admin123', 'root@root', 'changeme'];
}

function validateAdminPasswordPolicy(string $password): ?string {
    if (mb_strlen($password) < ADMIN_PASSWORD_MIN_LENGTH) return 'password_too_short_admin';
    if (!preg_match('/\p{L}/u', $password)) return 'password_needs_letter';
    if (!preg_match('/\d/', $password)) return 'password_needs_digit';
    $lower = mb_strtolower($password);
    foreach (adminWeakPasswords() as $weak) {
        if ($lower === $weak) return 'password_too_common';
    }
    return null;
}

function passwordPolicyInputAttrs(bool $isAdmin): string {
    if (!$isAdmin) return ' data-min-length="' . PASSWORD_MIN_LENGTH . '"';
    return ' data-min-length="' . ADMIN_PASSWORD_MIN_LENGTH . '" data-weak="' . htmlspecialchars(json_encode(adminWeakPasswords()), ENT_QUOTES) . '"';
}

function adminMutationThrottle(string $key, int $max = 10, int $windowSec = 60): bool {
    $ip = function_exists('getClientIp') ? getClientIp() : '0.0.0.0';
    $bucket = 'admin_throttle:' . $key . ':' . $ip;
    $now = time();
    if ($redis = app_redis()) {
        try {
            $rk = app_redis_key($bucket);
            $count = (int)$redis->incr($rk);
            if ($count === 1) $redis->expire($rk, $windowSec);
            return $count <= $max;
        } catch (Throwable $e) {}
    }
    if (!isset($_SESSION['_admin_throttle']) || !is_array($_SESSION['_admin_throttle'])) {
        $_SESSION['_admin_throttle'] = [];
    }
    $entry = $_SESSION['_admin_throttle'][$bucket] ?? ['t' => $now, 'n' => 0];
    if (($now - (int)$entry['t']) > $windowSec) $entry = ['t' => $now, 'n' => 0];
    $entry['n'] = (int)$entry['n'] + 1;
    $_SESSION['_admin_throttle'][$bucket] = $entry;
    return $entry['n'] <= $max;
}

function adminRequireMutationThrottle(string $key, int $max = 10, int $windowSec = 60): void {
    if (adminMutationThrottle($key, $max, $windowSec)) return;
    if (!headers_sent()) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'rate_limited']);
    exit;
}

function adminEstablishSession(string $email, array $user): void {
    if (session_status() !== PHP_SESSION_ACTIVE) app_session_start();
    session_regenerate_id(true);
    appClearPendingPasswordFlow();
    $_SESSION['user'] = $user;
    $_SESSION['user']['email'] = $email;
    $_SESSION['auth_time'] = time();
    unset($_SESSION['admin_session']);
    adminPanelClearGate();
    setLastActiveUser($email);
    cleanupExpiredUserSessions($email);
    addUserToActiveSessions($email, session_id(), $_SESSION['user']);
    try {
        if (function_exists('bindCookieConsentToEmail')) {
            bindCookieConsentToEmail(function_exists('getConsentDeviceId') ? getConsentDeviceId() : '', $email);
        }
    } catch (Throwable $e) {
        error_log('[cookie_consent] bind failed: ' . $e->getMessage());
    }
}

function adminPanelCookieName(): string {
    return 'ap_admin_gate';
}

function adminPanelEnsureTokenSchema(): void {
    adminSecurityEnsureSchema();
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS admin_panel_tokens (
        token_hash TEXT PRIMARY KEY,
        email TEXT NOT NULL DEFAULT '',
        ip_address TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT 0,
        expires_at INTEGER NOT NULL DEFAULT 0,
        last_used_at INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_panel_tokens_email ON admin_panel_tokens(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_panel_tokens_expires ON admin_panel_tokens(expires_at)");
}

function adminPanelSetCookie(string $token, int $expiresAt): void {
    if (headers_sent()) return;
    $secure = function_exists('app_session_is_https') ? app_session_is_https() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(adminPanelCookieName(), $token, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function adminPanelClearCookie(): void {
    if (headers_sent()) return;
    $secure = function_exists('app_session_is_https') ? app_session_is_https() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(adminPanelCookieName(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function adminPanelRawToken(): string {
    $header = trim((string)($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''));
    if ($header !== '' && preg_match('/^[a-f0-9]{64}$/i', $header)) return strtolower($header);
    $cookie = trim((string)($_COOKIE[adminPanelCookieName()] ?? ''));
    if ($cookie !== '' && preg_match('/^[a-f0-9]{64}$/i', $cookie)) return strtolower($cookie);
    return '';
}

function adminPanelClearGate(?string $email = null): void {
    adminPanelEnsureTokenSchema();
    $token = adminPanelRawToken();
    $db = getDB();
    if ($token !== '') {
        $stmt = $db->prepare("DELETE FROM admin_panel_tokens WHERE token_hash = :h");
        $stmt->bindValue(':h', hash('sha256', $token));
        $stmt->execute();
    }
    if ($email !== null && $email !== '') {
        $stmt = $db->prepare("DELETE FROM admin_panel_tokens WHERE email = :e");
        $stmt->bindValue(':e', $email);
        $stmt->execute();
    }
    adminPanelClearCookie();
    unset($_SESSION['admin_panel_gate_until'], $_SESSION['admin_panel_gate_email']);
}

function adminPanelIssueGate(string $email): array {
    adminPanelEnsureTokenSchema();
    $db = getDB();
    $purge = $db->prepare("DELETE FROM admin_panel_tokens WHERE expires_at < :t OR email = :e");
    $purge->bindValue(':t', time(), PDO::PARAM_INT);
    $purge->bindValue(':e', $email);
    $purge->execute();
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $now = time();
    $ttl = defined('ADMIN_SESSION_IDLE_SECONDS') ? (int)ADMIN_SESSION_IDLE_SECONDS : 7200;
    $expires = $now + max(600, $ttl);
    $stmt = $db->prepare("INSERT INTO admin_panel_tokens(token_hash,email,ip_address,created_at,expires_at,last_used_at)
        VALUES(:h,:e,:ip,:c,:x,:u)");
    $stmt->bindValue(':h', $hash);
    $stmt->bindValue(':e', $email);
    $stmt->bindValue(':ip', function_exists('getClientIp') ? getClientIp() : (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $stmt->bindValue(':c', $now, PDO::PARAM_INT);
    $stmt->bindValue(':x', $expires, PDO::PARAM_INT);
    $stmt->bindValue(':u', $now, PDO::PARAM_INT);
    $stmt->execute();
    adminPanelSetCookie($raw, $expires);
    $_SESSION['admin_panel_gate_until'] = $expires;
    $_SESSION['admin_panel_gate_email'] = $email;
    return ['token' => $raw, 'expires_at' => $expires];
}

function adminPanelGateValid(?string $expectedEmail = null): bool {
    $email = $expectedEmail ?? (string)($_SESSION['user']['email'] ?? '');
    if ($email === '' || !adminIsAdminUser()) return false;
    if (!adminIpAllowed()) return false;
    $token = adminPanelRawToken();
    if ($token === '') return false;
    adminPanelEnsureTokenSchema();
    $stmt = getDB()->prepare("SELECT * FROM admin_panel_tokens WHERE token_hash = :h LIMIT 1");
    $stmt->bindValue(':h', hash('sha256', $token));
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    if ((int)($row['expires_at'] ?? 0) < time()) {
        adminPanelClearGate($email);
        return false;
    }
    if (!hash_equals(strtolower((string)($row['email'] ?? '')), strtolower($email))) {
        return false;
    }
    $touch = getDB()->prepare("UPDATE admin_panel_tokens SET last_used_at = :u WHERE token_hash = :h");
    $touch->bindValue(':u', time(), PDO::PARAM_INT);
    $touch->bindValue(':h', hash('sha256', $token));
    $touch->execute();
    $_SESSION['admin_panel_gate_until'] = (int)$row['expires_at'];
    $_SESSION['admin_panel_gate_email'] = $email;
    return true;
}

function adminPanelRequireGate(): void {
    if (adminPanelGateValid()) return;
    adminAuditLog('admin_gate_required', '', 'denied');
    if (!headers_sent()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok' => false,
        'success' => false,
        'error' => 'admin_auth_required',
        'admin_auth_required' => true,
    ]);
    exit;
}

function adminPanelIsAdminRequest(): bool {
    if (!empty($_POST['get_admin_panel']) || !empty($_POST['admin_panel_login']) || !empty($_POST['admin_panel_logout']) || !empty($_POST['admin_panel_status'])) {
        return true;
    }
    $keys = [
        'fm_list_dir','fm_get_file','fm_save_file','fm_mkdir','fm_mkfile','fm_delete','fm_rename','fm_upload','fm_dir_size',
        'reset_user_password','delete_user','delete_user_with_files','save_user_roles','save_email_settings',
        'ssh_terminal_init','ssh_terminal_exec','ssh_terminal_get_profile','ssh_terminal_ws_token',
        'quick_command_action','save_app_access_levels','save_app_access_security','save_stats_data','get_users_table','scan_user_files',
    ];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) return true;
    }
    return false;
}
