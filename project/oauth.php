<?php

require_once APP_ROOT . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function ensureOAuthProviderSchema(AppDatabase $db): void {
    if (app_db_is_pgsql()) {
        $db->exec("CREATE TABLE IF NOT EXISTS oauth_clients (
            client_id TEXT PRIMARY KEY,
            client_secret_hash TEXT NOT NULL,
            name TEXT NOT NULL DEFAULT '',
            redirect_uris TEXT NOT NULL DEFAULT '',
            created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS oauth_auth_codes (
            code_hash TEXT PRIMARY KEY,
            client_id TEXT NOT NULL,
            email TEXT NOT NULL,
            redirect_uri TEXT NOT NULL DEFAULT '',
            scope TEXT NOT NULL DEFAULT '',
            nonce TEXT NOT NULL DEFAULT '',
            code_challenge TEXT NOT NULL DEFAULT '',
            code_challenge_method TEXT NOT NULL DEFAULT '',
            auth_time BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
            expires_at BIGINT NOT NULL
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
            token_hash TEXT PRIMARY KEY,
            client_id TEXT NOT NULL,
            email TEXT NOT NULL,
            scope TEXT NOT NULL DEFAULT '',
            created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
            expires_at BIGINT NOT NULL,
            revoked INTEGER NOT NULL DEFAULT 0
        )");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_clients (
        client_id TEXT PRIMARY KEY,
        client_secret_hash TEXT NOT NULL,
        name TEXT NOT NULL DEFAULT '',
        redirect_uris TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_auth_codes (
        code_hash TEXT PRIMARY KEY,
        client_id TEXT NOT NULL,
        email TEXT NOT NULL,
        redirect_uri TEXT NOT NULL DEFAULT '',
        scope TEXT NOT NULL DEFAULT '',
        nonce TEXT NOT NULL DEFAULT '',
        code_challenge TEXT NOT NULL DEFAULT '',
        code_challenge_method TEXT NOT NULL DEFAULT '',
        auth_time INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        expires_at INTEGER NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
        token_hash TEXT PRIMARY KEY,
        client_id TEXT NOT NULL,
        email TEXT NOT NULL,
        scope TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        expires_at INTEGER NOT NULL,
        revoked INTEGER NOT NULL DEFAULT 0
    )");
}

function oauth_issuer(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}

function oauth_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function oauth_signing_keys(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $priv = getConfig('oauth_jwt_private_key', '');
    $pub = getConfig('oauth_jwt_public_key', '');
    $kid = getConfig('oauth_jwt_kid', '');
    if ($priv === '' || $pub === '' || $kid === '') {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $priv);
        $details = openssl_pkey_get_details($res);
        $pub = $details['key'];
        $kid = bin2hex(random_bytes(8));
        setConfig('oauth_jwt_private_key', $priv);
        setConfig('oauth_jwt_public_key', $pub);
        setConfig('oauth_jwt_kid', $kid);
    }
    $cache = ['private' => $priv, 'public' => $pub, 'kid' => $kid];
    return $cache;
}

function oauth_jwk(): array {
    $keys = oauth_signing_keys();
    $details = openssl_pkey_get_details(openssl_pkey_get_public($keys['public']));
    return [
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => $keys['kid'],
        'n' => oauth_base64url_encode($details['rsa']['n']),
        'e' => oauth_base64url_encode($details['rsa']['e']),
    ];
}

function oauth_sanitize_username(string $raw): string {
    $u = strtolower($raw);
    $u = preg_replace('/[^a-z0-9_.\-]/', '-', $u) ?? '';
    $u = preg_replace('/[-_.]{2,}/', '-', $u) ?? '';
    $u = trim($u, "-_.");
    if ($u === '') $u = 'user' . bin2hex(random_bytes(3));
    return $u;
}

function oauth_build_user_claims(array $user): array {
    $email = (string)$user['email'];
    $local = strstr($email, '@', true);
    $username = oauth_sanitize_username($local !== false ? $local : $email);
    $name = trim((string)($user['name'] ?? '')) ?: $username;
    $photo = trim((string)($user['photo'] ?? ''));
    $picture = $photo !== '' ? oauth_issuer() . '/' . ltrim($photo, '/') : '';
    return [
        'sub' => $email,
        'email' => $email,
        'email_verified' => (bool)($user['verified'] ?? false),
        'name' => $name,
        'preferred_username' => $username,
        'picture' => $picture,
        'role' => (int)($user['role'] ?? 0),
        'is_admin' => (int)($user['role'] ?? 0) >= 2,
    ];
}

function oauth_find_client(AppDatabase $db, string $clientId): ?array {
    if ($clientId === '') return null;
    $stmt = $db->prepare("SELECT * FROM oauth_clients WHERE client_id = :c LIMIT 1");
    $stmt->bindValue(':c', $clientId);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function oauth_redirect_uri_allowed(array $client, string $redirectUri): bool {
    if ($redirectUri === '') return false;
    $allowed = array_filter(array_map('trim', explode("\n", (string)($client['redirect_uris'] ?? ''))));
    return in_array($redirectUri, $allowed, true);
}

function oauth_upsert_client(string $clientId, string $clientSecret, string $name, array $redirectUris): void {
    $db = getDB();
    ensureOAuthProviderSchema($db);
    $stmt = $db->prepare("INSERT OR REPLACE INTO oauth_clients(client_id,client_secret_hash,name,redirect_uris) VALUES(:c,:h,:n,:r)");
    $stmt->bindValue(':c', $clientId);
    $stmt->bindValue(':h', password_hash($clientSecret, PASSWORD_DEFAULT));
    $stmt->bindValue(':n', $name);
    $stmt->bindValue(':r', implode("\n", $redirectUris));
    $stmt->execute();
}

function oauth_create_auth_code(AppDatabase $db, string $clientId, string $email, string $redirectUri, string $scope, string $nonce, string $codeChallenge, string $codeChallengeMethod, int $authTime): string {
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $now = time();
    $stmt = $db->prepare("INSERT INTO oauth_auth_codes(code_hash,client_id,email,redirect_uri,scope,nonce,code_challenge,code_challenge_method,auth_time,created_at,expires_at) VALUES(:h,:c,:e,:r,:s,:n,:cc,:cm,:at,:now,:x)");
    $stmt->bindValue(':h', $hash);
    $stmt->bindValue(':c', $clientId);
    $stmt->bindValue(':e', $email);
    $stmt->bindValue(':r', $redirectUri);
    $stmt->bindValue(':s', $scope);
    $stmt->bindValue(':n', $nonce);
    $stmt->bindValue(':cc', $codeChallenge);
    $stmt->bindValue(':cm', $codeChallengeMethod);
    $stmt->bindValue(':at', $authTime, SQLITE3_INTEGER);
    $stmt->bindValue(':now', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':x', $now + 120, SQLITE3_INTEGER);
    $stmt->execute();
    return $raw;
}

function oauth_consume_auth_code(AppDatabase $db, string $code, string $clientId, string $redirectUri, string $codeVerifier): ?array {
    if ($code === '') return null;
    $hash = hash('sha256', $code);
    $stmt = $db->prepare("SELECT * FROM oauth_auth_codes WHERE code_hash = :h LIMIT 1");
    $stmt->bindValue(':h', $hash);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $del = $db->prepare("DELETE FROM oauth_auth_codes WHERE code_hash = :h");
    $del->bindValue(':h', $hash);
    $del->execute();
    if (!$row) return null;
    if ((int)$row['expires_at'] < time()) return null;
    if ((string)$row['client_id'] !== $clientId) return null;
    if ($redirectUri !== '' && (string)$row['redirect_uri'] !== $redirectUri) return null;
    $challenge = (string)($row['code_challenge'] ?? '');
    if ($challenge !== '') {
        $method = (string)($row['code_challenge_method'] ?? 'plain');
        $computed = $method === 'S256' ? oauth_base64url_encode(hash('sha256', $codeVerifier, true)) : $codeVerifier;
        if (!hash_equals($challenge, $computed)) return null;
    }
    return $row;
}

function oauth_create_refresh_token(string $clientId, string $email, string $scope): string {
    $db = getDB();
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $now = time();
    $stmt = $db->prepare("INSERT INTO oauth_refresh_tokens(token_hash,client_id,email,scope,created_at,expires_at,revoked) VALUES(:h,:c,:e,:s,:n,:x,0)");
    $stmt->bindValue(':h', $hash);
    $stmt->bindValue(':c', $clientId);
    $stmt->bindValue(':e', $email);
    $stmt->bindValue(':s', $scope);
    $stmt->bindValue(':n', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':x', $now + 60 * 60 * 24 * 30, SQLITE3_INTEGER);
    $stmt->execute();
    return $raw;
}

function oauth_consume_refresh_token(AppDatabase $db, string $token, string $clientId): ?array {
    if ($token === '') return null;
    $hash = hash('sha256', $token);
    $stmt = $db->prepare("SELECT * FROM oauth_refresh_tokens WHERE token_hash = :h LIMIT 1");
    $stmt->bindValue(':h', $hash);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $del = $db->prepare("DELETE FROM oauth_refresh_tokens WHERE token_hash = :h");
    $del->bindValue(':h', $hash);
    $del->execute();
    if (!$row) return null;
    if ((int)$row['revoked'] === 1) return null;
    if ((int)$row['expires_at'] < time()) return null;
    if ((string)$row['client_id'] !== $clientId) return null;
    return $row;
}

function oauth_issue_tokens(array $client, array $user, string $scope, string $nonce, int $authTime): array {
    $keys = oauth_signing_keys();
    $now = time();
    $issuer = oauth_issuer();
    $claims = oauth_build_user_claims($user);
    $accessPayload = array_merge($claims, [
        'iss' => $issuer,
        'aud' => $client['client_id'],
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => $scope,
    ]);
    $accessToken = JWT::encode($accessPayload, $keys['private'], 'RS256', $keys['kid']);
    $idPayload = array_merge($claims, [
        'iss' => $issuer,
        'aud' => $client['client_id'],
        'iat' => $now,
        'exp' => $now + 3600,
        'auth_time' => $authTime,
    ]);
    if ($nonce !== '') $idPayload['nonce'] = $nonce;
    $idToken = JWT::encode($idPayload, $keys['private'], 'RS256', $keys['kid']);
    return [
        'access_token' => $accessToken,
        'id_token' => $idToken,
        'refresh_token' => oauth_create_refresh_token($client['client_id'], $user['email'], $scope),
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'scope' => $scope,
    ];
}

function oauth_verify_access_token(string $jwt): ?array {
    $keys = oauth_signing_keys();
    try {
        $decoded = JWT::decode($jwt, new Key($keys['public'], 'RS256'));
        return (array)$decoded;
    } catch (Throwable $e) {
        return null;
    }
}

function oauth_extract_client_credentials(): array {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (stripos($auth, 'Basic ') === 0) {
        $decoded = base64_decode(substr($auth, 6), true);
        if ($decoded !== false && str_contains($decoded, ':')) {
            [$id, $secret] = explode(':', $decoded, 2);
            return [urldecode($id), urldecode($secret)];
        }
    }
    return [(string)($_POST['client_id'] ?? ''), (string)($_POST['client_secret'] ?? '')];
}

function oauth_redirect_with_error(string $redirectUri, string $state, string $error): void {
    if ($redirectUri === '') {
        http_response_code(400);
        echo my_htmlspecialchars($error);
        exit;
    }
    $sep = str_contains($redirectUri, '?') ? '&' : '?';
    $params = ['error' => $error];
    if ($state !== '') $params['state'] = $state;
    header('Location: ' . $redirectUri . $sep . http_build_query($params));
    exit;
}

function oauth_route_discovery(): void {
    header('Content-Type: application/json; charset=utf-8');
    $issuer = oauth_issuer();
    echo json_encode([
        'issuer' => $issuer,
        'authorization_endpoint' => $issuer . '/authorize',
        'token_endpoint' => $issuer . '/token',
        'userinfo_endpoint' => $issuer . '/userinfo',
        'jwks_uri' => $issuer . '/.well-known/jwks.json',
        'response_types_supported' => ['code'],
        'subject_types_supported' => ['public'],
        'id_token_signing_alg_values_supported' => ['RS256'],
        'scopes_supported' => ['openid', 'email', 'profile', 'offline_access'],
        'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
        'claims_supported' => ['sub', 'email', 'email_verified', 'name', 'preferred_username', 'picture', 'role', 'is_admin'],
        'code_challenge_methods_supported' => ['S256', 'plain'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function oauth_route_jwks(): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['keys' => [oauth_jwk()]]);
    exit;
}

function oauth_route_authorize(): void {
    $db = getDB();
    ensureOAuthProviderSchema($db);
    $clientId = trim((string)($_GET['client_id'] ?? ''));
    $redirectUri = trim((string)($_GET['redirect_uri'] ?? ''));
    $responseType = trim((string)($_GET['response_type'] ?? ''));
    $scope = trim((string)($_GET['scope'] ?? 'openid'));
    $state = (string)($_GET['state'] ?? '');
    $nonce = (string)($_GET['nonce'] ?? '');
    $codeChallenge = (string)($_GET['code_challenge'] ?? '');
    $codeChallengeMethod = (string)($_GET['code_challenge_method'] ?? '');
    $client = oauth_find_client($db, $clientId);
    if (!$client || !oauth_redirect_uri_allowed($client, $redirectUri)) {
        http_response_code(400);
        echo 'invalid_client_or_redirect_uri';
        exit;
    }
    if ($responseType !== 'code') {
        oauth_redirect_with_error($redirectUri, $state, 'unsupported_response_type');
    }
    if (!clientAuthSessionOk()) {
        setPostLoginRedirect((string)($_SERVER['REQUEST_URI'] ?? '/authorize'));
        header('Location: /login');
        exit;
    }
    $email = (string)$_SESSION['user']['email'];
    $code = oauth_create_auth_code($db, $client['client_id'], $email, $redirectUri, $scope, $nonce, $codeChallenge, $codeChallengeMethod, (int)($_SESSION['auth_time'] ?? time()));
    $params = ['code' => $code];
    if ($state !== '') $params['state'] = $state;
    $sep = str_contains($redirectUri, '?') ? '&' : '?';
    header('Location: ' . $redirectUri . $sep . http_build_query($params));
    exit;
}

function oauth_route_token(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $db = getDB();
    ensureOAuthProviderSchema($db);
    [$clientId, $clientSecret] = oauth_extract_client_credentials();
    $client = oauth_find_client($db, $clientId);
    if (!$client || !password_verify($clientSecret, (string)$client['client_secret_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'invalid_client']);
        exit;
    }
    $grantType = (string)($_POST['grant_type'] ?? '');
    if ($grantType === 'authorization_code') {
        $code = (string)($_POST['code'] ?? '');
        $redirectUri = (string)($_POST['redirect_uri'] ?? '');
        $codeVerifier = (string)($_POST['code_verifier'] ?? '');
        $row = oauth_consume_auth_code($db, $code, $client['client_id'], $redirectUri, $codeVerifier);
        if (!$row) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_grant']);
            exit;
        }
        $user = getUserByEmail((string)$row['email']);
        if (!$user) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_grant']);
            exit;
        }
        echo json_encode(oauth_issue_tokens($client, $user, (string)$row['scope'], (string)$row['nonce'], (int)$row['auth_time']));
        exit;
    }
    if ($grantType === 'refresh_token') {
        $row = oauth_consume_refresh_token($db, (string)($_POST['refresh_token'] ?? ''), $client['client_id']);
        if (!$row) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_grant']);
            exit;
        }
        $user = getUserByEmail((string)$row['email']);
        if (!$user) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_grant']);
            exit;
        }
        echo json_encode(oauth_issue_tokens($client, $user, (string)$row['scope'], '', time()));
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => 'unsupported_grant_type']);
    exit;
}

function oauth_route_userinfo(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (stripos($auth, 'Bearer ') !== 0) {
        http_response_code(401);
        header('WWW-Authenticate: Bearer');
        echo json_encode(['error' => 'invalid_token']);
        exit;
    }
    $claims = oauth_verify_access_token(trim(substr($auth, 7)));
    $email = $claims ? (string)($claims['email'] ?? $claims['sub'] ?? '') : '';
    $user = $email !== '' ? getUserByEmail($email) : null;
    if (!$user) {
        http_response_code(401);
        header('WWW-Authenticate: Bearer error="invalid_token"');
        echo json_encode(['error' => 'invalid_token']);
        exit;
    }
    echo json_encode(oauth_build_user_claims($user), JSON_UNESCAPED_UNICODE);
    exit;
}

function oauth_gitea_auth_user(): ?array {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (stripos($auth, 'Bearer ') !== 0) return null;
    $claims = oauth_verify_access_token(trim(substr($auth, 7)));
    $email = $claims ? (string)($claims['email'] ?? $claims['sub'] ?? '') : '';
    return $email !== '' ? getUserByEmail($email) : null;
}

function oauth_route_gitea_user(): void {
    header('Content-Type: application/json; charset=utf-8');
    $user = oauth_gitea_auth_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['message' => 'invalid token']);
        exit;
    }
    $claims = oauth_build_user_claims($user);
    $syncEmail = (string)$claims['email'];
    $syncIsAdmin = (bool)$claims['is_admin'];
    echo json_encode([
        'id' => (int)sprintf('%u', crc32((string)$claims['email'])),
        'login' => $claims['preferred_username'],
        'full_name' => $claims['name'],
        'email' => $claims['email'],
        'avatar_url' => $claims['picture'],
        'is_admin' => $claims['is_admin'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    sleep(2);
    oauth_sync_plane_admin($syncEmail, $syncIsAdmin);
    exit;
}

function oauth_sync_plane_admin(string $email, bool $isAdmin): void {
    if ($email === '') return;
    try {
        $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=plane', 'plane', '2f7fc8329b05207b856e768e22355197a5003655b4279aa1', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        if (!$isAdmin) {
            $stmt = $pdo->prepare('DELETE FROM instance_admins WHERE user_id IN (SELECT id FROM users WHERE lower(email) = lower(:email))');
            $stmt->execute([':email' => $email]);
            return;
        }
        $stmt = $pdo->prepare("INSERT INTO instance_admins(created_at,updated_at,id,role,is_verified,instance_id,user_id)
            SELECT now(),now(),:id,20,true,(SELECT id FROM instances ORDER BY created_at DESC LIMIT 1),u.id
            FROM users u
            WHERE lower(u.email) = lower(:email)
            ON CONFLICT (instance_id,user_id) DO UPDATE SET role = 20, is_verified = true, updated_at = now()");
        $stmt->execute([':id' => oauth_uuid(), ':email' => $email]);
    } catch (\Exception $e) {
    }
}

function oauth_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function oauth_route_gitea_emails(): void {
    header('Content-Type: application/json; charset=utf-8');
    $user = oauth_gitea_auth_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['message' => 'invalid token']);
        exit;
    }
    echo json_encode([[
        'email' => (string)$user['email'],
        'verified' => (bool)($user['verified'] ?? false),
        'primary' => true,
    ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function oauth_ensure_bridge_schema(AppDatabase $db): void {
    if (app_db_is_pgsql()) {
        $db->exec("CREATE TABLE IF NOT EXISTS oauth_bridge_tokens (
            token_hash TEXT PRIMARY KEY,
            session_id TEXT NOT NULL,
            created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
            expires_at BIGINT NOT NULL
        )");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_bridge_tokens (
        token_hash TEXT PRIMARY KEY,
        session_id TEXT NOT NULL,
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        expires_at INTEGER NOT NULL
    )");
}

function oauth_create_bridge_token(string $sessionId): string {
    $db = getDB();
    oauth_ensure_bridge_schema($db);
    $raw = bin2hex(random_bytes(24));
    $hash = hash('sha256', $raw);
    $now = time();
    $stmt = $db->prepare("INSERT INTO oauth_bridge_tokens(token_hash,session_id,created_at,expires_at) VALUES(:h,:s,:n,:x)");
    $stmt->bindValue(':h', $hash);
    $stmt->bindValue(':s', $sessionId);
    $stmt->bindValue(':n', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':x', $now + 120, SQLITE3_INTEGER);
    $stmt->execute();
    return $raw;
}

function oauth_consume_bridge_token(string $raw): ?string {
    if ($raw === '') return null;
    $db = getDB();
    oauth_ensure_bridge_schema($db);
    $hash = hash('sha256', $raw);
    $stmt = $db->prepare("SELECT session_id, expires_at FROM oauth_bridge_tokens WHERE token_hash = :h LIMIT 1");
    $stmt->bindValue(':h', $hash);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $del = $db->prepare("DELETE FROM oauth_bridge_tokens WHERE token_hash = :h");
    $del->bindValue(':h', $hash);
    $del->execute();
    if (!$row) return null;
    if ((int)$row['expires_at'] < time()) return null;
    return (string)$row['session_id'];
}

function oauth_route_bridge(): void {
    $raw = (string)($_GET['t'] ?? '');
    $next = (string)($_GET['next'] ?? '');
    if ($raw === '' || $next === '' || (!preg_match('#^https://#', $next) && !str_starts_with($next, '/'))) {
        http_response_code(400);
        echo 'invalid_request';
        exit;
    }
    if (clientAuthSessionOk()) {
        header('Location: ' . $next);
        exit;
    }
    $sessionId = oauth_consume_bridge_token($raw);
    if (!$sessionId || !preg_match('/^[a-zA-Z0-9,\-]{20,256}$/', $sessionId)) {
        header('Location: /login');
        exit;
    }
    if (PHP_SESSION_ACTIVE === session_status()) session_write_close();
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_id($sessionId);
    session_start();
    if (!isset($_SESSION['user']['email'])) {
        session_destroy();
        header('Location: /login');
        exit;
    }
    session_write_close();
    header('Location: ' . $next);
    exit;
}

function omniroute_jwt_secret(): string {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $envFile = '/opt/docker/omniroute/omniroute_data/server.env';
    if (!is_readable($envFile)) {
        $cache = '';
        return $cache;
    }
    $content = (string)file_get_contents($envFile);
    if (preg_match('/^JWT_SECRET=(.+)$/m', $content, $m)) {
        $cache = trim($m[1]);
        return $cache;
    }
    $cache = '';
    return $cache;
}

function omniroute_issue_auth_token(): ?string {
    $secret = omniroute_jwt_secret();
    if ($secret === '') {
        return null;
    }
    $now = time();
    return JWT::encode(['authenticated' => true, 'iat' => $now, 'exp' => $now + 86400 * 30], $secret, 'HS256');
}

function oauth_route_omniroute_bridge(): void {
    if (!clientAuthSessionOk()) {
        setPostLoginRedirect((string)($_SERVER['REQUEST_URI'] ?? '/omniroute-bridge'));
        header('Location: /login');
        exit;
    }
    if ((int)($_SESSION['user']['role'] ?? 0) !== 2) {
        http_response_code(403);
        echo 'admin_required';
        exit;
    }
    $token = omniroute_issue_auth_token();
    if (!$token) {
        http_response_code(500);
        echo 'omniroute_jwt_unavailable';
        exit;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    setcookie('auth_token', $token, [
        'expires' => time() + 86400 * 30,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $next = trim((string)($_GET['next'] ?? '/admin-omniroute/home'));
    if (!str_starts_with($next, '/admin-omniroute/')) {
        $next = '/admin-omniroute/home';
    }
    header('Location: ' . $next);
    exit;
}

function oauth_dispatch(string $path): void {
    switch ($path) {
        case '/.well-known/openid-configuration': oauth_route_discovery(); return;
        case '/.well-known/jwks.json': oauth_route_jwks(); return;
        case '/authorize': oauth_route_authorize(); return;
        case '/login/oauth/authorize': oauth_route_authorize(); return;
        case '/token': oauth_route_token(); return;
        case '/login/oauth/access_token': oauth_route_token(); return;
        case '/userinfo': oauth_route_userinfo(); return;
        case '/api/v1/user': oauth_route_gitea_user(); return;
        case '/api/v1/user/emails': oauth_route_gitea_emails(); return;
        case '/oauth-bridge': oauth_route_bridge(); return;
        case '/omniroute-bridge': oauth_route_omniroute_bridge(); return;
    }
}
