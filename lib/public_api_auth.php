<?php
function public_api_ensure_token_schema(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS client_auth_tokens (
        token_hash TEXT PRIMARY KEY,
        email TEXT NOT NULL,
        app_id TEXT NOT NULL DEFAULT '',
        device_id TEXT NOT NULL DEFAULT '',
        device_name TEXT NOT NULL DEFAULT '',
        platform TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        last_used_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        expires_at INTEGER NOT NULL,
        revoked INTEGER NOT NULL DEFAULT 0
    )");
    app_client_auth_tokens_ensure_app_column($db);
    $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_tokens_email ON client_auth_tokens(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_tokens_email_app ON client_auth_tokens(email, app_id)");
}
function public_api_issue_token(AppDatabase $db, string $email, string $deviceId, string $deviceName, string $platform, ?int $ttl = null, string $appId = ''): array {
    public_api_ensure_token_schema($db);
    $appId = appClientAuthTokenAppId($appId);
    $purge = $db->prepare('DELETE FROM client_auth_tokens WHERE expires_at < :t');
    $purge->bindValue(':t', time(), PDO::PARAM_INT);
    $purge->execute();
    if ($deviceId !== '') {
        $del = $db->prepare('DELETE FROM client_auth_tokens WHERE email = :e AND app_id = :a AND device_id = :d');
        $del->bindValue(':e', $email, PDO::PARAM_STR);
        $del->bindValue(':a', $appId, PDO::PARAM_STR);
        $del->bindValue(':d', $deviceId, PDO::PARAM_STR);
        $del->execute();
    }
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $now = time();
    if ($ttl === null) {
        $ttl = (int)(getenv('PUBLIC_API_TOKEN_TTL') ?: (90 * 24 * 3600));
    }
    $ttl = max(60, min((int)$ttl, (int)(getenv('PUBLIC_API_TOKEN_TTL_MAX') ?: (365 * 24 * 3600))));
    $expiresAt = $now + $ttl;
    $stmt = $db->prepare("INSERT INTO client_auth_tokens(token_hash,email,app_id,device_id,device_name,platform,created_at,last_used_at,expires_at,revoked) VALUES(:h,:e,:a,:d,:n,:p,:c,:u,:x,0)");
    $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':a', $appId, PDO::PARAM_STR);
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
    $tokenDeviceId = (string)($row['device_id'] ?? '');
    if ($deviceId !== '' && $tokenDeviceId !== '' && $tokenDeviceId !== 'mcp_external' && !hash_equals($tokenDeviceId, $deviceId)) return null;
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

function public_api_ensure_oauth_schema(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_clients (
        client_id TEXT PRIMARY KEY,
        client_secret_hash TEXT NOT NULL,
        client_name TEXT NOT NULL DEFAULT '',
        redirect_uris TEXT NOT NULL DEFAULT '[]',
        grant_types TEXT NOT NULL DEFAULT '[\"authorization_code\",\"refresh_token\"]',
        scopes TEXT NOT NULL DEFAULT '[\"mcp\"]',
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        revoked INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
        code_hash TEXT PRIMARY KEY,
        client_id TEXT NOT NULL,
        redirect_uri TEXT NOT NULL,
        code_challenge TEXT NOT NULL DEFAULT '',
        code_challenge_method TEXT NOT NULL DEFAULT 'S256',
        email TEXT NOT NULL,
        state TEXT NOT NULL DEFAULT '',
        scope TEXT NOT NULL DEFAULT 'mcp',
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        expires_at INTEGER NOT NULL,
        consumed INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_oauth_codes_client ON oauth_authorization_codes(client_id)");
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
        refresh_token_hash TEXT PRIMARY KEY,
        client_id TEXT NOT NULL,
        email TEXT NOT NULL,
        device_id TEXT NOT NULL DEFAULT '',
        scope TEXT NOT NULL DEFAULT 'mcp',
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        expires_at INTEGER NOT NULL,
        revoked INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_oauth_refresh_client ON oauth_refresh_tokens(client_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_oauth_refresh_email ON oauth_refresh_tokens(email)");
}
function public_api_oauth_purge(AppDatabase $db): void {
    public_api_ensure_oauth_schema($db);
    $stmt = $db->prepare("DELETE FROM oauth_authorization_codes WHERE expires_at < :now OR (consumed = 1 AND created_at < :old)");
    $stmt->bindValue(':now', time(), PDO::PARAM_INT);
    $stmt->bindValue(':old', time() - 86400, PDO::PARAM_INT);
    $stmt->execute();
    $stmt = $db->prepare("DELETE FROM oauth_refresh_tokens WHERE expires_at < :now");
    $stmt->bindValue(':now', time(), PDO::PARAM_INT);
    $stmt->execute();
}
function public_api_oauth_loopback_uri(string $uri): bool {
    $p = parse_url($uri);
    if ($p === false || ($p['scheme'] ?? '') !== 'http') return false;
    if (isset($p['user']) || isset($p['pass'])) return false;
    $host = strtolower(trim((string)($p['host'] ?? ''), '[]'));
    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
}
function public_api_oauth_redirect_match(string $registered, string $used): bool {
    $a = parse_url($registered);
    $b = parse_url($used);
    if ($a === false || $b === false) return false;
    if (($a['scheme'] ?? '') !== ($b['scheme'] ?? '')) return false;
    if (strtolower(trim((string)($a['host'] ?? ''), '[]')) !== strtolower(trim((string)($b['host'] ?? ''), '[]'))) return false;
    $aPath = ($a['path'] ?? '') === '' ? '/' : (string)$a['path'];
    $bPath = ($b['path'] ?? '') === '' ? '/' : (string)$b['path'];
    if ($aPath !== $bPath) return false;
    return (($a['query'] ?? '') === ($b['query'] ?? ''));
}
function public_api_oauth_valid_code_challenge(string $cc): bool {
    return $cc !== '' && strlen($cc) >= 43 && strlen($cc) <= 128 && preg_match('/^[A-Za-z0-9._~-]+$/', $cc) === 1;
}
function public_api_oauth_pkce_s256(string $verifier): string {
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
}
function public_api_oauth_pkce_verify(string $challenge, string $verifier, string $method): bool {
    if ($verifier === '' || strlen($verifier) < 43 || strlen($verifier) > 128) return false;
    if ($method === 'S256') return hash_equals($challenge, public_api_oauth_pkce_s256($verifier));
    if ($method === 'plain') return hash_equals($challenge, $verifier);
    return false;
}
function public_api_oauth_register_client(AppDatabase $db, array $body): array {
    public_api_oauth_purge($db);
    $redirectUris = $body['redirect_uris'] ?? null;
    if (!is_array($redirectUris)) $redirectUris = array_filter(explode("\n", (string)($body['redirect_uris_string'] ?? '')));
    $valid = [];
    foreach ($redirectUris as $uri) {
        $uri = trim((string)$uri);
        $u = parse_url($uri);
        if ($u === false || isset($u['query'], $u['fragment'])) continue;
        if (public_api_oauth_loopback_uri($uri)) $valid[] = $uri;
    }
    $valid = array_values(array_unique($valid));
    if ($valid === []) return ['error' => 'redirect_uri_not_allowed'];
    $clientId = bin2hex(random_bytes(16));
    $clientSecret = bin2hex(random_bytes(32));
    $name = trim((string)($body['client_name'] ?? 'opencode'));
    if ($name === '') $name = 'opencode';
    $stmt = $db->prepare('INSERT INTO oauth_clients(client_id,client_secret_hash,client_name,redirect_uris,grant_types,scopes,created_at,revoked) VALUES(:id,:h,:n,:ru,:gt,:sc,:c,0)');
    $stmt->bindValue(':id', $clientId, PDO::PARAM_STR);
    $stmt->bindValue(':h', hash('sha256', $clientSecret), PDO::PARAM_STR);
    $stmt->bindValue(':n', $name, PDO::PARAM_STR);
    $stmt->bindValue(':ru', json_encode($valid), PDO::PARAM_STR);
    $stmt->bindValue(':gt', json_encode(['authorization_code', 'refresh_token']), PDO::PARAM_STR);
    $stmt->bindValue(':sc', json_encode(['mcp']), PDO::PARAM_STR);
    $stmt->bindValue(':c', time(), PDO::PARAM_INT);
    $stmt->execute();
    return [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'client_name' => $name,
        'redirect_uris' => $valid,
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'token_endpoint_auth_method' => 'client_secret_post',
        'scope' => 'mcp',
        'client_id_issued_at' => time(),
        'client_secret_expires_at' => 0,
    ];
}
function public_api_oauth_get_client(AppDatabase $db, string $clientId): ?array {
    public_api_ensure_oauth_schema($db);
    $stmt = $db->prepare("SELECT * FROM oauth_clients WHERE client_id = :id LIMIT 1");
    $stmt->bindValue(':id', $clientId, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)($row['revoked'] ?? 0) === 1) return null;
    return $row;
}
function public_api_oauth_redirect_ok(AppDatabase $db, string $clientId, string $redirectUri): ?array {
    $client = public_api_oauth_get_client($db, $clientId);
    if (!$client || !public_api_oauth_loopback_uri($redirectUri)) return null;
    $uris = json_decode((string)($client['redirect_uris'] ?? '[]'), true);
    if (!is_array($uris)) return null;
    foreach ($uris as $ru) {
        if (public_api_oauth_redirect_match((string)$ru, $redirectUri)) return $client;
    }
    return null;
}
function public_api_oauth_verify_secret(AppDatabase $db, string $clientId, string $secret): ?array {
    if ($secret === '') return null;
    $client = public_api_oauth_get_client($db, $clientId);
    if (!$client) return null;
    return hash_equals((string)($client['client_secret_hash'] ?? ''), hash('sha256', $secret)) ? $client : null;
}
function public_api_oauth_create_code(AppDatabase $db, string $clientId, string $redirectUri, string $codeChallenge, string $codeChallengeMethod, string $state, string $email): array {
    public_api_oauth_purge($db);
    $code = bin2hex(random_bytes(32));
    $stmt = $db->prepare('INSERT INTO oauth_authorization_codes(code_hash,client_id,redirect_uri,code_challenge,code_challenge_method,email,state,scope,created_at,expires_at,consumed) VALUES(:h,:c,:ru,:cc,:cm,:e,:s,:sc,:t,:x,0)');
    $stmt->bindValue(':h', hash('sha256', $code), PDO::PARAM_STR);
    $stmt->bindValue(':c', $clientId, PDO::PARAM_STR);
    $stmt->bindValue(':ru', $redirectUri, PDO::PARAM_STR);
    $stmt->bindValue(':cc', $codeChallenge, PDO::PARAM_STR);
    $stmt->bindValue(':cm', $codeChallengeMethod, PDO::PARAM_STR);
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':s', $state, PDO::PARAM_STR);
    $stmt->bindValue(':sc', 'mcp', PDO::PARAM_STR);
    $stmt->bindValue(':t', time(), PDO::PARAM_INT);
    $stmt->bindValue(':x', time() + 600, PDO::PARAM_INT);
    $stmt->execute();
    return ['code' => $code, 'redirect_uri' => $redirectUri, 'state' => $state, 'expires_at' => time() + 600];
}
function public_api_oauth_consume_code(AppDatabase $db, string $code, string $clientId): ?array {
    public_api_oauth_purge($db);
    $stmt = $db->prepare('SELECT * FROM oauth_authorization_codes WHERE code_hash = :h LIMIT 1');
    $stmt->bindValue(':h', hash('sha256', $code), PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row || (string)($row['client_id'] ?? '') !== $clientId) return null;
    if ((int)($row['expires_at'] ?? 0) < time() || (int)($row['consumed'] ?? 0) === 1) return null;
    $upd = $db->prepare('UPDATE oauth_authorization_codes SET consumed = 1 WHERE code_hash = :h');
    $upd->bindValue(':h', hash('sha256', $code), PDO::PARAM_STR);
    $upd->execute();
    return $row;
}
function public_api_oauth_issue_tokens(AppDatabase $db, array $client, string $email, string $deviceId, string $scope): array {
    $accessTtl = (int)(getenv('PUBLIC_API_OAUTH_ACCESS_TTL') ?: 3600);
    $access = public_api_issue_token($db, $email, $deviceId, (string)($client['client_name'] ?? 'appdashboard'), 'oauth', $accessTtl, 'public_api');
    $refresh = bin2hex(random_bytes(32));
    $rTtl = (int)(getenv('PUBLIC_API_OAUTH_REFRESH_TTL') ?: (30 * 24 * 3600));
    $stmt = $db->prepare('INSERT INTO oauth_refresh_tokens(refresh_token_hash,client_id,email,device_id,scope,created_at,expires_at,revoked) VALUES(:h,:c,:e,:d,:s,:t,:x,0)');
    $stmt->bindValue(':h', hash('sha256', $refresh), PDO::PARAM_STR);
    $stmt->bindValue(':c', (string)($client['client_id'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':d', $deviceId, PDO::PARAM_STR);
    $stmt->bindValue(':s', $scope, PDO::PARAM_STR);
    $stmt->bindValue(':t', time(), PDO::PARAM_INT);
    $stmt->bindValue(':x', time() + $rTtl, PDO::PARAM_INT);
    $stmt->execute();
    return [
        'access_token' => $access['token'],
        'token_type' => 'Bearer',
        'expires_in' => $accessTtl,
        'refresh_token' => $refresh,
        'scope' => $scope,
    ];
}
function public_api_oauth_rotate_refresh(AppDatabase $db, string $refreshToken, string $clientId): ?array {
    public_api_oauth_purge($db);
    $stmt = $db->prepare('SELECT * FROM oauth_refresh_tokens WHERE refresh_token_hash = :h LIMIT 1');
    $stmt->bindValue(':h', hash('sha256', $refreshToken), PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)($row['revoked'] ?? 0) === 1) return null;
    if ((string)($row['client_id'] ?? '') !== $clientId) return null;
    if ((int)($row['expires_at'] ?? 0) < time()) return null;
    $stmt = $db->prepare('UPDATE oauth_refresh_tokens SET revoked = 1 WHERE refresh_token_hash = :h');
    $stmt->bindValue(':h', hash('sha256', $refreshToken), PDO::PARAM_STR);
    $stmt->execute();
    $newRefresh = bin2hex(random_bytes(32));
    $rTtl = (int)(getenv('PUBLIC_API_OAUTH_REFRESH_TTL') ?: (30 * 24 * 3600));
    $stmt = $db->prepare('INSERT INTO oauth_refresh_tokens(refresh_token_hash,client_id,email,device_id,scope,created_at,expires_at,revoked) VALUES(:h,:c,:e,:d,:s,:t,:x,0)');
    $stmt->bindValue(':h', hash('sha256', $newRefresh), PDO::PARAM_STR);
    $stmt->bindValue(':c', $clientId, PDO::PARAM_STR);
    $stmt->bindValue(':e', (string)($row['email'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':d', (string)($row['device_id'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':s', (string)($row['scope'] ?? 'mcp'), PDO::PARAM_STR);
    $stmt->bindValue(':t', time(), PDO::PARAM_INT);
    $stmt->bindValue(':x', time() + $rTtl, PDO::PARAM_INT);
    $stmt->execute();
    return [
        'email' => (string)$row['email'],
        'device_id' => (string)$row['device_id'],
        'scope' => (string)($row['scope'] ?? 'mcp'),
        'new_refresh_token' => $newRefresh,
        'expires_at' => time() + $rTtl,
    ];
}
function public_api_oauth_revoke_refresh(AppDatabase $db, string $refreshToken): void {
    if ($refreshToken === '') return;
    $stmt = $db->prepare('UPDATE oauth_refresh_tokens SET revoked = 1 WHERE refresh_token_hash = :h');
    $stmt->bindValue(':h', hash('sha256', $refreshToken), PDO::PARAM_STR);
    $stmt->execute();
}
function public_api_oauth_revoke_all_for_email(AppDatabase $db, string $email): void {
    public_api_ensure_oauth_schema($db);
    $stmt = $db->prepare('UPDATE oauth_refresh_tokens SET revoked = 1 WHERE email = :e');
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->execute();
    public_api_revoke_all_for_email($db, $email);
}
function public_api_client_secret_from_request(): string {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '');
    if (preg_match('/^\s*Basic\s+(.+)$/i', $auth, $m)) {
        $decoded = base64_decode(trim((string)$m[1]), true);
        if ($decoded !== false && str_contains($decoded, ':')) {
            [$c, $s] = explode(':', $decoded, 2);
            if ($c !== '' && $s !== '') return $s;
        }
    }
    $body = public_api_form_body();
    return trim((string)($body['client_secret'] ?? ''));
}
function public_api_client_id_from_request(): string {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '');
    if (preg_match('/^\s*Basic\s+(.+)$/i', $auth, $m)) {
        $decoded = base64_decode(trim((string)$m[1]), true);
        if ($decoded !== false && str_contains($decoded, ':')) {
            [$c] = explode(':', $decoded, 2);
            if ($c !== '') return $c;
        }
    }
    $body = public_api_form_body();
    return trim((string)($body['client_id'] ?? ''));
}
