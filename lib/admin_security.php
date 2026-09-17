<?php
declare(strict_types=1);

const ADMIN_CAPABILITIES = ['users', 'ssh', 'files', 'docker', 'secrets', 'integrations', 'database'];
const ADMIN_SESSION_IDLE_SECONDS = 7200;
const ADMIN_STEPUP_TTL_SECONDS = 900;
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
    $db->exec("CREATE TABLE IF NOT EXISTS admin_totp (
        email TEXT PRIMARY KEY,
        secret TEXT NOT NULL DEFAULT '',
        enabled INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL DEFAULT 0,
        confirmed_at INTEGER NOT NULL DEFAULT 0
    )");
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
        $critical = ['role_change', 'user_delete', 'password_reset', 'ssh_init', 'fm_write', 'secrets_save', 'totp_disable'];
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

function adminTotpBase32Encode(string $data): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function adminTotpBase32Decode(string $b32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
    $bits = '';
    for ($i = 0, $l = strlen($b32); $i < $l; $i++) {
        $val = strpos($alphabet, $b32[$i]);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
    }
    return $out;
}

function adminTotpGenerateSecret(int $bytes = 20): string {
    return adminTotpBase32Encode(random_bytes(max(10, $bytes)));
}

function adminTotpCode(string $secret, ?int $time = null, int $digits = 6, int $period = 30): string {
    $time = $time ?? time();
    $key = adminTotpBase32Decode($secret);
    $counter = pack('N*', 0, intdiv($time, $period));
    $hash = hash_hmac('sha1', $counter, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % (10 ** $digits);
    return str_pad((string)$value, $digits, '0', STR_PAD_LEFT);
}

function adminTotpVerify(string $secret, string $code, int $window = 1): bool {
    $code = preg_replace('/\s+/', '', $code) ?? '';
    if (!preg_match('/^\d{6}$/', $code)) return false;
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(adminTotpCode($secret, $now + ($i * 30)), $code)) return true;
    }
    return false;
}

function adminTotpGet(string $email): ?array {
    adminSecurityEnsureSchema();
    $stmt = getDB()->prepare("SELECT * FROM admin_totp WHERE email=:e LIMIT 1");
    $stmt->bindValue(':e', $email);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function adminTotpIsEnabled(string $email): bool {
    $row = adminTotpGet($email);
    return $row && !empty($row['enabled']) && (string)($row['secret'] ?? '') !== '';
}

function adminTotpSavePending(string $email, string $secret): void {
    adminSecurityEnsureSchema();
    $stmt = getDB()->prepare("INSERT INTO admin_totp(email,secret,enabled,created_at,confirmed_at)
        VALUES(:e,:s,0,:c,0)
        ON CONFLICT(email) DO UPDATE SET secret=excluded.secret, enabled=0, created_at=excluded.created_at, confirmed_at=0");
    $stmt->bindValue(':e', $email);
    $stmt->bindValue(':s', $secret);
    $stmt->bindValue(':c', time(), PDO::PARAM_INT);
    $stmt->execute();
}

function adminTotpConfirm(string $email): void {
    adminSecurityEnsureSchema();
    $stmt = getDB()->prepare("UPDATE admin_totp SET enabled=1, confirmed_at=:t WHERE email=:e");
    $stmt->bindValue(':t', time(), PDO::PARAM_INT);
    $stmt->bindValue(':e', $email);
    $stmt->execute();
}

function adminTotpDisable(string $email): void {
    adminSecurityEnsureSchema();
    $stmt = getDB()->prepare("DELETE FROM admin_totp WHERE email=:e");
    $stmt->bindValue(':e', $email);
    $stmt->execute();
}

function adminTotpProvisioningUri(string $email, string $secret, string $issuer = 'AppDashboard'): string {
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email)
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

function adminStepupValid(): bool {
    $until = (int)($_SESSION['admin_stepup_until'] ?? 0);
    return $until > time();
}

function adminStepupGrant(int $ttl = ADMIN_STEPUP_TTL_SECONDS): void {
    $_SESSION['admin_stepup_until'] = time() + max(60, $ttl);
}

function adminStepupClear(): void {
    unset($_SESSION['admin_stepup_until']);
}

function adminRequireStepup(): void {
    if (adminStepupValid()) return;
    adminAuditLog('stepup_required', '', 'denied');
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'stepup_required', 'stepup_required' => true]);
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
    adminStepupClear();
    if (adminIsAdminUser($_SESSION['user'])) {
        $_SESSION['admin_session'] = 1;
        app_session_refresh_cookie_params(true);
    } else {
        unset($_SESSION['admin_session']);
        app_session_refresh_cookie_params(false);
    }
    setLastActiveUser($email);
    cleanupExpiredUserSessions($email);
    addUserToActiveSessions($email, session_id(), $_SESSION['user']);
}
