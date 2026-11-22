<?php
declare(strict_types=1);
require_once __DIR__ . '/db_config.php';
function app_client_auth_token_idle_ttl(): int {
    // Pairing tokens that are never used again should not stay valid forever.
    $ttl = (int)(getenv('CLIENT_AUTH_TOKEN_IDLE_TTL') ?: (30 * 24 * 3600));
    return max(3600, $ttl);
}
function app_client_auth_token_idle_apps(): array {
    // Only companion-app pairing tokens age out automatically; API keys, cloud
    // sessions and MCP clients keep their own explicit lifetimes.
    return ['steps', 'kanboard', 'spends'];
}
function app_client_auth_tokens_expire_idle(AppDatabase $db): int {
    $apps = app_client_auth_token_idle_apps();
    $placeholders = implode(',', array_fill(0, count($apps), '?'));
    $stmt = $db->prepare("UPDATE client_auth_tokens SET revoked = 1, token_plain = '', device_id = '' WHERE revoked = 0 AND last_used_at < ? AND app_id IN ($placeholders)");
    $stmt->bindValue(1, time() - app_client_auth_token_idle_ttl(), PDO::PARAM_INT);
    foreach ($apps as $i => $app) $stmt->bindValue($i + 2, $app, PDO::PARAM_STR);
    $stmt->execute();
    return (int)$db->changes();
}
function appClientAuthTokenAppId($appId): string {
    $appId = strtolower(trim((string)$appId));
    if ($appId === '' || !preg_match('/^[a-z0-9_]{1,32}$/', $appId)) return '';
    return $appId;
}
function app_client_auth_tokens_ensure_app_column(AppDatabase $db): void {
    require_once __DIR__ . '/schema_once.php';
    if (!app_schema_should_run('cat_app_id_column', __FILE__)) return;
    try {
        $hasAppId = (bool)$db->querySingle("SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name='client_auth_tokens' AND column_name='app_id' LIMIT 1");
        if ($hasAppId) return;
        $db->exec("ALTER TABLE client_auth_tokens ADD COLUMN IF NOT EXISTS app_id TEXT NOT NULL DEFAULT ''");
        $db->exec("UPDATE client_auth_tokens SET app_id = 'mcp' WHERE app_id = '' AND device_id = 'mcp_external' AND platform = 'mcp'");
        $db->exec("UPDATE client_auth_tokens SET app_id = 'steps' WHERE app_id = '' AND device_id = 'mcp_external'");
        $db->exec("UPDATE client_auth_tokens SET app_id = 'public_api' WHERE app_id = '' AND (platform = 'oauth' OR device_id LIKE 'oauth%')");
        $db->exec("UPDATE client_auth_tokens SET app_id = 'cloud' WHERE app_id = ''");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_tokens_email_app ON client_auth_tokens(email, app_id)");
    } catch (Throwable $e) {}
}
function app_client_auth_tokens_ensure_plain_column(AppDatabase $db): void {
    require_once __DIR__ . '/schema_once.php';
    if (!app_schema_should_run('cat_plain_column', __FILE__)) return;
    try {
        $db->exec("ALTER TABLE client_auth_tokens ADD COLUMN IF NOT EXISTS token_plain TEXT NOT NULL DEFAULT ''");
    } catch (Throwable $e) {}
}
function app_db_rate_limit_key(string $prefix, string $ip): string {
    return $prefix . ':' . preg_replace('/[^a-fA-F0-9:.]/', '', $ip);
}
function app_db_check_rate_limit(AppDatabase $db, string $ip, string $lockType, int $maxAttempts = 10, int $windowSec = 900): bool {
    $stmt = $db->prepare('SELECT attempts, locked_until FROM ip_lockouts WHERE ip = :ip AND lock_type = :t LIMIT 1');
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindValue(':t', $lockType, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) return true;
    $lockedUntil = (int)($row['locked_until'] ?? 0);
    if ($lockedUntil > time()) return false;
    return (int)($row['attempts'] ?? 0) < $maxAttempts;
}
function app_db_record_failed_auth(AppDatabase $db, string $ip, string $lockType, int $maxAttempts = 10, int $lockSec = 1800): void {
    $now = time();
    $stmt = $db->prepare('INSERT INTO ip_lockouts (ip, lock_type, attempts, locked_until, last_attempt) VALUES (:ip, :t, 1, NULL, :now) ON CONFLICT (ip, lock_type) DO UPDATE SET attempts = ip_lockouts.attempts + 1, last_attempt = :now, locked_until = CASE WHEN ip_lockouts.attempts + 1 >= :max THEN :until ELSE ip_lockouts.locked_until END');
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindValue(':t', $lockType, PDO::PARAM_STR);
    $stmt->bindValue(':now', $now, PDO::PARAM_INT);
    $stmt->bindValue(':max', $maxAttempts, PDO::PARAM_INT);
    $stmt->bindValue(':until', $now + $lockSec, PDO::PARAM_INT);
    $stmt->execute();
}
function app_db_clear_auth_failures(AppDatabase $db, string $ip, string $lockType): void {
    $stmt = $db->prepare('DELETE FROM ip_lockouts WHERE ip = :ip AND lock_type = :t');
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindValue(':t', $lockType, PDO::PARAM_STR);
    $stmt->execute();
}
function app_db_validate_bearer_token(AppDatabase $db, string $rawToken, ?string $deviceId = null): ?array {
    $rawToken = trim($rawToken);
    if ($rawToken === '' || strlen($rawToken) < 32) return null;
    if (!preg_match('/^[a-f0-9]{64}$/i', $rawToken)) return null;
    $hash = hash('sha256', $rawToken);
    $stmt = $db->prepare('SELECT * FROM client_auth_tokens WHERE token_hash = :h AND revoked = 0 LIMIT 1');
    $stmt->bindValue(':h', $hash, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if ((int)($row['expires_at'] ?? 0) < time()) return null;
    // Idle expiry: a token nobody used for the idle TTL is dead even if its
    // absolute expiry is still in the future.
    if ((int)($row['last_used_at'] ?? 0) < time() - app_client_auth_token_idle_ttl()
        && in_array((string)($row['app_id'] ?? ''), app_client_auth_token_idle_apps(), true)) {
        $dead = $db->prepare("UPDATE client_auth_tokens SET revoked = 1, token_plain = '', device_id = '' WHERE token_hash = :h AND revoked = 0");
        $dead->bindValue(':h', $hash, PDO::PARAM_STR);
        $dead->execute();
        return null;
    }
    if ($deviceId !== null && $deviceId !== '' && (string)($row['device_id'] ?? '') !== '' && !hash_equals((string)$row['device_id'], $deviceId)) return null;
    $upd = $db->prepare('UPDATE client_auth_tokens SET last_used_at = :u WHERE token_hash = :h');
    $upd->bindValue(':u', time(), PDO::PARAM_INT);
    $upd->bindValue(':h', $hash, PDO::PARAM_STR);
    $upd->execute();
    return $row;
}
