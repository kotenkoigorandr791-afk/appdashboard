<?php
function public_api_ensure_token_schema(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS client_auth_tokens (
        token_hash TEXT PRIMARY KEY,
        email TEXT NOT NULL,
        device_id TEXT NOT NULL DEFAULT '',
        device_name TEXT NOT NULL DEFAULT '',
        platform TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        last_used_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        expires_at INTEGER NOT NULL,
        revoked INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_tokens_email ON client_auth_tokens(email)");
}
function public_api_issue_token(AppDatabase $db, string $email, string $deviceId, string $deviceName, string $platform): array {
    public_api_ensure_token_schema($db);
    $purge = $db->prepare('DELETE FROM client_auth_tokens WHERE expires_at < :t');
    $purge->bindValue(':t', time(), SQLITE3_INTEGER);
    $purge->execute();
    if ($deviceId !== '') {
        $del = $db->prepare('DELETE FROM client_auth_tokens WHERE email = :e AND device_id = :d');
        $del->bindValue(':e', $email, SQLITE3_TEXT);
        $del->bindValue(':d', $deviceId, SQLITE3_TEXT);
        $del->execute();
    }
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $now = time();
    $expiresAt = $now + (int)(getenv('PUBLIC_API_TOKEN_TTL') ?: (90 * 24 * 3600));
    $stmt = $db->prepare("INSERT OR REPLACE INTO client_auth_tokens(token_hash,email,device_id,device_name,platform,created_at,last_used_at,expires_at,revoked) VALUES(:h,:e,:d,:n,:p,:c,:u,:x,0)");
    $stmt->bindValue(':h', $tokenHash, SQLITE3_TEXT);
    $stmt->bindValue(':e', $email, SQLITE3_TEXT);
    $stmt->bindValue(':d', $deviceId, SQLITE3_TEXT);
    $stmt->bindValue(':n', $deviceName, SQLITE3_TEXT);
    $stmt->bindValue(':p', $platform, SQLITE3_TEXT);
    $stmt->bindValue(':c', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':u', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':x', $expiresAt, SQLITE3_INTEGER);
    $stmt->execute();
    return ['token' => $rawToken, 'expires_at' => $expiresAt, 'token_type' => 'Bearer'];
}
function public_api_validate_token(AppDatabase $db, string $rawToken, string $deviceId): ?array {
    if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/i', $rawToken)) return null;
    public_api_ensure_token_schema($db);
    $hash = hash('sha256', $rawToken);
    $stmt = $db->prepare("SELECT * FROM client_auth_tokens WHERE token_hash = :h AND revoked = 0 LIMIT 1");
    $stmt->bindValue(':h', $hash, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    if ((int)($row['expires_at'] ?? 0) < time()) return null;
    if ($deviceId !== '' && (string)($row['device_id'] ?? '') !== '' && !hash_equals((string)$row['device_id'], $deviceId)) return null;
    $upd = $db->prepare("UPDATE client_auth_tokens SET last_used_at = :u WHERE token_hash = :h");
    $upd->bindValue(':u', time(), SQLITE3_INTEGER);
    $upd->bindValue(':h', $hash, SQLITE3_TEXT);
    $upd->execute();
    return $row;
}
function public_api_revoke_token(AppDatabase $db, string $rawToken): void {
    if ($rawToken === '') return;
    $stmt = $db->prepare("UPDATE client_auth_tokens SET revoked = 1 WHERE token_hash = :h");
    $stmt->bindValue(':h', hash('sha256', $rawToken), SQLITE3_TEXT);
    $stmt->execute();
}
function public_api_revoke_all_for_email(AppDatabase $db, string $email): void {
    $stmt = $db->prepare("UPDATE client_auth_tokens SET revoked = 1 WHERE email = :e");
    $stmt->bindValue(':e', $email, SQLITE3_TEXT);
    $stmt->execute();
}
