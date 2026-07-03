<?php
declare(strict_types=1);
require_once __DIR__ . '/db_config.php';
function app_db_rate_limit_key(string $prefix, string $ip): string {
    return $prefix . ':' . preg_replace('/[^a-fA-F0-9:.]/', '', $ip);
}
function app_db_check_rate_limit(AppDatabase $db, string $ip, string $lockType, int $maxAttempts = 10, int $windowSec = 900): bool {
    $stmt = $db->prepare('SELECT attempts, locked_until FROM ip_lockouts WHERE ip = :ip AND lock_type = :t LIMIT 1');
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':t', $lockType, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return true;
    $lockedUntil = (int)($row['locked_until'] ?? 0);
    if ($lockedUntil > time()) return false;
    return (int)($row['attempts'] ?? 0) < $maxAttempts;
}
function app_db_record_failed_auth(AppDatabase $db, string $ip, string $lockType, int $maxAttempts = 10, int $lockSec = 1800): void {
    $now = time();
    $stmt = $db->prepare('INSERT INTO ip_lockouts (ip, lock_type, attempts, locked_until, last_attempt) VALUES (:ip, :t, 1, NULL, :now) ON CONFLICT (ip, lock_type) DO UPDATE SET attempts = ip_lockouts.attempts + 1, last_attempt = :now, locked_until = CASE WHEN ip_lockouts.attempts + 1 >= :max THEN :until ELSE ip_lockouts.locked_until END');
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':t', $lockType, SQLITE3_TEXT);
    $stmt->bindValue(':now', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':max', $maxAttempts, SQLITE3_INTEGER);
    $stmt->bindValue(':until', $now + $lockSec, SQLITE3_INTEGER);
    $stmt->execute();
}
function app_db_clear_auth_failures(AppDatabase $db, string $ip, string $lockType): void {
    $stmt = $db->prepare('DELETE FROM ip_lockouts WHERE ip = :ip AND lock_type = :t');
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':t', $lockType, SQLITE3_TEXT);
    $stmt->execute();
}
function app_db_validate_bearer_token(AppDatabase $db, string $rawToken, ?string $deviceId = null): ?array {
    $rawToken = trim($rawToken);
    if ($rawToken === '' || strlen($rawToken) < 32) return null;
    if (!preg_match('/^[a-f0-9]{64}$/i', $rawToken)) return null;
    $hash = hash('sha256', $rawToken);
    $stmt = $db->prepare('SELECT * FROM client_auth_tokens WHERE token_hash = :h AND revoked = 0 LIMIT 1');
    $stmt->bindValue(':h', $hash, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    if ((int)($row['expires_at'] ?? 0) < time()) return null;
    if ($deviceId !== null && $deviceId !== '' && (string)($row['device_id'] ?? '') !== '' && !hash_equals((string)$row['device_id'], $deviceId)) return null;
    $upd = $db->prepare('UPDATE client_auth_tokens SET last_used_at = :u WHERE token_hash = :h');
    $upd->bindValue(':u', time(), SQLITE3_INTEGER);
    $upd->bindValue(':h', $hash, SQLITE3_TEXT);
    $upd->execute();
    return $row;
}
