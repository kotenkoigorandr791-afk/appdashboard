<?php
function public_api_ensure_token_schema(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS client_auth_tokens (
        token_hash TEXT PRIMARY KEY,
        email TEXT NOT NULL,
        device_id TEXT NOT NULL DEFAULT '',
        device_name TEXT NOT NULL DEFAULT '',
        platform TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        last_used_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        expires_at INTEGER NOT NULL,
        revoked INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_tokens_email ON client_auth_tokens(email)");
}
function public_api_issue_token(AppDatabase $db, string $email, string $deviceId, string $deviceName, string $platform): array {
    public_api_ensure_token_schema($db);
    $purge = $db->prepare('DELETE FROM client_auth_tokens WHERE expires_at < :t');
    $purge->bindValue(':t', time(), PDO::PARAM_INT);
    $purge->execute();
    if ($deviceId !== '') {
        $del = $db->prepare('DELETE FROM client_auth_tokens WHERE email = :e AND device_id = :d');
        $del->bindValue(':e', $email, PDO::PARAM_STR);
        $del->bindValue(':d', $deviceId, PDO::PARAM_STR);
        $del->execute();
    }
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $now = time();
    $expiresAt = $now + (int)(getenv('PUBLIC_API_TOKEN_TTL') ?: (90 * 24 * 3600));
    $stmt = $db->prepare("INSERT INTO client_auth_tokens(token_hash,email,device_id,device_name,platform,created_at,last_used_at,expires_at,revoked) VALUES(:h,:e,:d,:n,:p,:c,:u,:x,0)");
    $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':d', $deviceId, PDO::PARAM_STR);
    $stmt->bindValue(':n', $deviceName, PDO::PARAM_STR);
    $stmt->bindValue(':p', $platform, PDO::PARAM_STR);
    $stmt->bindValue(':c', $now, PDO::PARAM_INT);
    $stmt->bindValue(':u', $now, PDO::PARAM_INT);
    $stmt->bindValue(':x', $expiresAt, PDO::PARAM_INT);
    $stmt->execute();
    return ['token' => $rawToken, 'expires_at' => $expiresAt, 'token_type' => 'Bearer'];
}
function public_api_validate_token(AppDatabase $db, string $rawToken, string $deviceId): ?array {
    if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/i', $rawToken)) return null;
    public_api_ensure_token_schema($db);
    $hash = hash('sha256', $rawToken);
    $stmt = $db->prepare("SELECT * FROM client_auth_tokens WHERE token_hash = :h AND revoked = 0 LIMIT 1");
    $stmt->bindValue(':h', $hash, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if ((int)($row['expires_at'] ?? 0) < time()) return null;
    if ($deviceId !== '' && (string)($row['device_id'] ?? '') !== '' && !hash_equals((string)$row['device_id'], $deviceId)) return null;
    $upd = $db->prepare("UPDATE client_auth_tokens SET last_used_at = :u WHERE token_hash = :h");
    $upd->bindValue(':u', time(), PDO::PARAM_INT);
    $upd->bindValue(':h', $hash, PDO::PARAM_STR);
    $upd->execute();
    return $row;
}
function public_api_revoke_token(AppDatabase $db, string $rawToken): void {
    if ($rawToken === '') return;
    $stmt = $db->prepare("UPDATE client_auth_tokens SET revoked = 1 WHERE token_hash = :h");
    $stmt->bindValue(':h', hash('sha256', $rawToken), PDO::PARAM_STR);
    $stmt->execute();
}
function public_api_revoke_all_for_email(AppDatabase $db, string $email): void {
    $stmt = $db->prepare("UPDATE client_auth_tokens SET revoked = 1 WHERE email = :e");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->execute();
}

function public_api_ensure_browser_auth_schema(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS client_auth_browser_requests (
        request_id TEXT PRIMARY KEY,
        poll_token_hash TEXT NOT NULL,
        browser_token_hash TEXT NOT NULL,
        callback_url TEXT NOT NULL DEFAULT '',
        device_id TEXT NOT NULL DEFAULT '',
        device_name TEXT NOT NULL DEFAULT '',
        platform TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending',
        email TEXT NOT NULL DEFAULT '',
        issued_token TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        approved_at INTEGER,
        expires_at INTEGER NOT NULL,
        claimed_at INTEGER
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_browser_requests_status ON client_auth_browser_requests(status, expires_at)");
}
function public_api_purge_browser_auth(AppDatabase $db): void {
    public_api_ensure_browser_auth_schema($db);
    $stmt = $db->prepare("DELETE FROM client_auth_browser_requests WHERE expires_at < :now OR (status = 'claimed' AND claimed_at IS NOT NULL AND claimed_at < :old)");
    $stmt->bindValue(':now', time(), PDO::PARAM_INT);
    $stmt->bindValue(':old', time() - 86400, PDO::PARAM_INT);
    $stmt->execute();
}
function public_api_browser_auth_matches(string $plain, string $hash): bool {
    return $plain !== '' && $hash !== '' && hash_equals($hash, hash('sha256', $plain));
}
function public_api_create_browser_auth_request(AppDatabase $db, string $deviceId, string $deviceName, string $platform, string $callbackUrl = ''): array {
    public_api_purge_browser_auth($db);
    $requestId = bin2hex(random_bytes(16));
    $pollToken = bin2hex(random_bytes(32));
    $browserToken = bin2hex(random_bytes(32));
    $expiresAt = time() + 900;
    $stmt = $db->prepare("INSERT INTO client_auth_browser_requests(request_id,poll_token_hash,browser_token_hash,callback_url,device_id,device_name,platform,status,expires_at) VALUES(:r,:p,:b,:c,:d,:n,:pl,'pending',:x)");
    $stmt->bindValue(':r', $requestId, PDO::PARAM_STR);
    $stmt->bindValue(':p', hash('sha256', $pollToken), PDO::PARAM_STR);
    $stmt->bindValue(':b', hash('sha256', $browserToken), PDO::PARAM_STR);
    $stmt->bindValue(':c', $callbackUrl, PDO::PARAM_STR);
    $stmt->bindValue(':d', $deviceId, PDO::PARAM_STR);
    $stmt->bindValue(':n', $deviceName, PDO::PARAM_STR);
    $stmt->bindValue(':pl', $platform, PDO::PARAM_STR);
    $stmt->bindValue(':x', $expiresAt, PDO::PARAM_INT);
    $stmt->execute();
    return [
        'request_id' => $requestId,
        'poll_token' => $pollToken,
        'browser_token' => $browserToken,
        'expires_at' => $expiresAt,
    ];
}
function public_api_get_browser_auth_request(AppDatabase $db, string $requestId): ?array {
    public_api_ensure_browser_auth_schema($db);
    $stmt = $db->prepare("SELECT * FROM client_auth_browser_requests WHERE request_id = :r LIMIT 1");
    $stmt->bindValue(':r', $requestId, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
