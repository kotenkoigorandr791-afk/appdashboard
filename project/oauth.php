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
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
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
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        expires_at INTEGER NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
        token_hash TEXT PRIMARY KEY,
        client_id TEXT NOT NULL,
        email TEXT NOT NULL,
        scope TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
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

function oauth_user_picture_url(string $photo): string {
    $photo = trim($photo);
    if ($photo === '' || $photo === 'default_avatar.png') return '';
    if (preg_match('#^https?://#i', $photo)) return $photo;
    return oauth_issuer() . '/' . ltrim($photo, '/');
}

function oauth_build_user_claims(array $user): array {
    $email = (string)$user['email'];
    $local = strstr($email, '@', true);
    $username = oauth_sanitize_username($local !== false ? $local : $email);
    $name = trim((string)($user['name'] ?? '')) ?: $username;
    $picture = oauth_user_picture_url((string)($user['photo'] ?? ''));
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
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
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
    $stmt = $db->prepare("INSERT INTO oauth_clients(client_id,client_secret_hash,name,redirect_uris) VALUES(:c,:h,:n,:r)");
    $stmt->bindValue(':c', $clientId);
    $stmt->bindValue(':h', appPasswordHash($clientSecret));
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
    $stmt->bindValue(':at', $authTime, PDO::PARAM_INT);
    $stmt->bindValue(':now', $now, PDO::PARAM_INT);
    $stmt->bindValue(':x', $now + 120, PDO::PARAM_INT);
    $stmt->execute();
    return $raw;
}

function oauth_consume_auth_code(AppDatabase $db, string $code, string $clientId, string $redirectUri, string $codeVerifier): ?array {
    if ($code === '') return null;
    $hash = hash('sha256', $code);
    $stmt = $db->prepare("SELECT * FROM oauth_auth_codes WHERE code_hash = :h LIMIT 1");
    $stmt->bindValue(':h', $hash);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
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
    $stmt->bindValue(':n', $now, PDO::PARAM_INT);
    $stmt->bindValue(':x', $now + 60 * 60 * 24 * 30, PDO::PARAM_INT);
    $stmt->execute();
    return $raw;
}

function oauth_consume_refresh_token(AppDatabase $db, string $token, string $clientId): ?array {
    if ($token === '') return null;
    $hash = hash('sha256', $token);
    $stmt = $db->prepare("SELECT * FROM oauth_refresh_tokens WHERE token_hash = :h LIMIT 1");
    $stmt->bindValue(':h', $hash);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
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
    if (!$client || !appPasswordVerify($clientSecret, (string)$client['client_secret_hash'])) {
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
    $aud = strtolower(trim((string)($claims['aud'] ?? '')));
    echo json_encode(oauth_build_user_claims($user), JSON_UNESCAPED_UNICODE);
    if (in_array($aud, ['immich', 'dawarich', 'gitea'], true)) {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        sleep(2);
        oauth_provision_user_services($email);
    }
    exit;
}

function oauth_env_file(string $path): array {
    $vars = [];
    if (!is_readable($path)) return $vars;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $vars[trim($k)] = trim($v, " \t\"'");
    }
    return $vars;
}

function oauth_dawarich_db(): ?PDO {
    static $pdo = null;
    static $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    $vars = oauth_env_file('/opt/docker/dawarich/.env');
    $password = (string)($vars['DATABASE_PASSWORD'] ?? $vars['POSTGRES_PASSWORD'] ?? '');
    if ($password === '') return null;
    try {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s', '127.0.0.1', 5435, (string)($vars['DATABASE_NAME'] ?? $vars['POSTGRES_DB'] ?? 'dawarich_development')),
            (string)($vars['DATABASE_USERNAME'] ?? $vars['POSTGRES_USER'] ?? 'postgres'),
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
    } catch (\Exception $e) {
        $pdo = null;
    }
    return $pdo;
}

function oauth_immich_db(): ?PDO {
    static $pdo = null;
    static $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    $vars = oauth_env_file('/opt/docker/immich/.env');
    $user = (string)($vars['DB_USERNAME'] ?? 'postgres');
    $password = (string)($vars['DB_PASSWORD'] ?? 'postgres');
    $dbname = (string)($vars['DB_DATABASE_NAME'] ?? 'immich');
    $hosts = ['127.0.0.1'];
    $ip = trim((string)shell_exec("docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' immich_postgres 2>/dev/null"));
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) $hosts[] = $ip;
    foreach ($hosts as $host) {
        foreach ([5436, 5432] as $port) {
            if ($host === '127.0.0.1' && $port === 5432) continue;
            try {
                $pdo = new PDO(
                    sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $dbname),
                    $user,
                    $password,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
                );
                return $pdo;
            } catch (\Exception $e) {
                $pdo = null;
            }
        }
    }
    return null;
}

function oauth_immich_api_token(): string {
    return preg_replace('/\W/', '', base64_encode(random_bytes(32))) ?: bin2hex(random_bytes(24));
}

function oauth_sync_immich_dawarich(string $email): void {
    $email = trim($email);
    if ($email === '') return;
    $immich = oauth_immich_db();
    $dawarich = oauth_dawarich_db();
    if (!$immich && !$dawarich) return;
    $immichUserId = null;
    if ($immich) {
        try {
            $stmt = $immich->prepare('SELECT id FROM "user" WHERE lower(email) = lower(:email) AND "deletedAt" IS NULL LIMIT 1');
            $stmt->execute([':email' => $email]);
            $immichUserId = $stmt->fetchColumn() ?: null;
        } catch (\Exception $e) {
            $immichUserId = null;
        }
    }
    $dawarichSettings = null;
    $dawarichId = null;
    if ($dawarich) {
        try {
            $stmt = $dawarich->prepare('SELECT id, settings, api_key FROM users WHERE lower(email) = lower(:email) AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                $dawarichId = (int)$row['id'];
                $raw = $row['settings'];
                if (is_string($raw)) {
                    $dawarichSettings = json_decode($raw, true);
                } elseif (is_array($raw)) {
                    $dawarichSettings = $raw;
                } else {
                    $dawarichSettings = [];
                }
                if (!is_array($dawarichSettings)) $dawarichSettings = [];
                if (trim((string)($row['api_key'] ?? '')) === '') {
                    $apiKey = bin2hex(random_bytes(32));
                    $upd = $dawarich->prepare('UPDATE users SET api_key = :k, updated_at = NOW() WHERE id = :id');
                    $upd->execute([':k' => $apiKey, ':id' => $dawarichId]);
                }
            }
        } catch (\Exception $e) {
            $dawarichId = null;
            $dawarichSettings = null;
        }
    }
    if ($immichUserId === null) return;
    $immichUrl = 'https://photo.serverkherson.xyz';
    $token = '';
    $existing = is_array($dawarichSettings) ? trim((string)($dawarichSettings['immich_api_key'] ?? '')) : '';
    if ($existing !== '' && $immich) {
        try {
            $hashHex = hash('sha256', $existing);
            $chk = $immich->prepare('SELECT 1 FROM api_key WHERE "userId" = :uid AND key = decode(:hex, \'hex\') LIMIT 1');
            $chk->execute([':uid' => $immichUserId, ':hex' => $hashHex]);
            if ($chk->fetchColumn()) $token = $existing;
        } catch (\Exception $e) {
            $token = '';
        }
    }
    if ($token === '' && $immich) {
        try {
            $immich->prepare('DELETE FROM api_key WHERE "userId" = :uid AND name = :name')->execute([
                ':uid' => $immichUserId,
                ':name' => 'Dawarich',
            ]);
            $token = oauth_immich_api_token();
            $ins = $immich->prepare('INSERT INTO api_key (name, key, "userId", permissions) VALUES (:name, decode(:hex, \'hex\'), :uid, :perms)');
            $ins->execute([
                ':name' => 'Dawarich',
                ':hex' => hash('sha256', $token),
                ':uid' => $immichUserId,
                ':perms' => '{asset.read,asset.view,asset.update}',
            ]);
        } catch (\Exception $e) {
            $token = '';
        }
    }
    if ($token === '' || $dawarichId === null || !is_array($dawarichSettings)) return;
    $dawarichSettings['immich_url'] = $immichUrl;
    $dawarichSettings['immich_api_key'] = $token;
    $layers = $dawarichSettings['enabled_map_layers'] ?? [];
    if (!is_array($layers)) $layers = [];
    if (!in_array('Photos', $layers, true)) $layers[] = 'Photos';
    $dawarichSettings['enabled_map_layers'] = array_values($layers);
    try {
        $upd = $dawarich->prepare('UPDATE users SET settings = CAST(:settings AS jsonb), updated_at = NOW() WHERE id = :id');
        $upd->execute([
            ':settings' => json_encode($dawarichSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':id' => $dawarichId,
        ]);
    } catch (\Exception $e) {
    }
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
    oauth_sync_plane_user($syncEmail, $syncIsAdmin, (string)$claims['picture']);
    exit;
}

function oauth_sync_plane_admin(string $email, bool $isAdmin, string $theme = ''): void {
    oauth_sync_plane_user($email, $isAdmin, '', $theme);
}

function oauth_plane_db(): ?PDO {
    static $pdo = null;
    static $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    $vars = oauth_env_file('/opt/docker/plane/.env');
    $user = (string)($vars['POSTGRES_USER'] ?? 'plane');
    $password = (string)($vars['POSTGRES_PASSWORD'] ?? 'plane');
    $dbname = (string)($vars['POSTGRES_DB'] ?? $vars['PGDATABASE'] ?? 'plane');
    try {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s', '127.0.0.1', 5432, $dbname),
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
    } catch (\Exception $e) {
        $pdo = null;
    }
    return $pdo;
}

function oauth_gitea_db(): ?PDO {
    static $pdo = null;
    static $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    try {
        $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=gitea', 'gitea', 'gitea', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
    } catch (\Exception $e) {
        $pdo = null;
    }
    return $pdo;
}

function oauth_devise_password(string $plain = ''): string {
    if ($plain === '') $plain = bin2hex(random_bytes(32));
    $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    return $hash ? str_replace('$2y$', '$2a$', $hash) : '';
}

function oauth_split_name(string $name, string $fallback): array {
    $name = trim($name);
    if ($name === '') $name = $fallback;
    $parts = preg_split('/\s+/', $name, 2) ?: [$fallback];
    $first = (string)($parts[0] ?? $fallback);
    $last = (string)($parts[1] ?? '');
    return [$first, $last];
}

function oauth_ensure_immich_user(string $email, string $name, bool $isAdmin): void {
    $pdo = oauth_immich_db();
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare('SELECT id, "oauthId" FROM "user" WHERE lower(email) = lower(:email) AND "deletedAt" IS NULL LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (trim((string)($row['oauthId'] ?? '')) === '') {
                $upd = $pdo->prepare("UPDATE \"user\" SET \"oauthId\" = :oid, name = CASE WHEN name = '' OR name IS NULL THEN :name ELSE name END, \"isAdmin\" = :admin, \"updatedAt\" = now() WHERE id = :id");
                $upd->execute([':oid' => $email, ':name' => $name, ':admin' => $isAdmin ? 't' : 'f', ':id' => $row['id']]);
            } elseif ($isAdmin) {
                $pdo->prepare('UPDATE "user" SET "isAdmin" = true, "updatedAt" = now() WHERE id = :id')->execute([':id' => $row['id']]);
            }
            return;
        }
        $ins = $pdo->prepare('INSERT INTO "user" (email, name, password, "isAdmin", "oauthId", "shouldChangePassword", status) VALUES (:email, :name, :password, :admin, :oid, false, :status)');
        $ins->execute([
            ':email' => $email,
            ':name' => $name !== '' ? $name : $email,
            ':password' => '',
            ':admin' => $isAdmin ? 't' : 'f',
            ':oid' => $email,
            ':status' => 'active',
        ]);
    } catch (\Exception $e) {
    }
}

function oauth_ensure_dawarich_user(string $email, string $name, bool $isAdmin, string $theme): void {
    $pdo = oauth_dawarich_db();
    if (!$pdo) return;
    $theme = in_array($theme, ['light', 'dark'], true) ? $theme : 'dark';
    try {
        $stmt = $pdo->prepare('SELECT id, provider, uid FROM users WHERE lower(email) = lower(:email) AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $upd = $pdo->prepare("UPDATE users SET provider = COALESCE(NULLIF(provider, ''), 'openid_connect'), uid = COALESCE(NULLIF(uid, ''), :uid), admin = :admin, theme = :theme, status = 1, plan = 1, active_until = COALESCE(active_until, NOW() + INTERVAL '1000 years'), updated_at = NOW() WHERE id = :id");
            $upd->execute([':uid' => $email, ':admin' => $isAdmin ? 't' : 'f', ':theme' => $theme, ':id' => $row['id']]);
            return;
        }
        [$first, $last] = oauth_split_name($name, strstr($email, '@', true) ?: 'user');
        $ins = $pdo->prepare("INSERT INTO users (email, encrypted_password, api_key, provider, uid, first_name, last_name, theme, status, plan, admin, created_at, updated_at, active_until, settings, subscription_source, points_count, sign_in_count, failed_attempts, failed_otp_attempts, otp_required_for_login)
            VALUES (:email, :password, :api_key, 'openid_connect', :uid, :first, :last, :theme, 1, 1, :admin, NOW(), NOW(), NOW() + INTERVAL '1000 years', CAST(:settings AS jsonb), 0, 0, 0, 0, 0, false)");
        $ins->execute([
            ':email' => strtolower($email),
            ':password' => oauth_devise_password(),
            ':api_key' => bin2hex(random_bytes(32)),
            ':uid' => $email,
            ':first' => $first,
            ':last' => $last,
            ':theme' => $theme,
            ':admin' => $isAdmin ? 't' : 'f',
            ':settings' => json_encode([
                'fog_of_war_meters' => '100',
                'meters_between_routes' => '1000',
                'minutes_between_routes' => '60',
                'onboarding_completed' => true,
                'enabled_map_layers' => ['Routes', 'Heatmap', 'Visits', 'Photos'],
            ], JSON_UNESCAPED_SLASHES),
        ]);
    } catch (\Exception $e) {
    }
}

function oauth_ensure_gitea_user(string $email, string $name, bool $isAdmin, string $theme, string $photo = ''): void {
    $pdo = oauth_gitea_db();
    if (!$pdo) return;
    $giteaTheme = $theme === 'dark' ? 'gitea-dark' : 'gitea-light';
    $username = oauth_sanitize_username(strstr($email, '@', true) ?: $email);
    $avatar = '';
    $photo = trim($photo);
    if ($photo !== '' && $photo !== 'default_avatar.png') {
        $avatar = preg_match('#^https?://#i', $photo) ? $photo : ('https://serverkherson.xyz/' . ltrim($photo, '/'));
    }
    try {
        $stmt = $pdo->prepare('SELECT id FROM "user" WHERE lower(email) = lower(:email) LIMIT 1');
        $stmt->execute([':email' => $email]);
        $userId = $stmt->fetchColumn();
        if (!$userId) {
            $base = $username;
            $n = 0;
            while (true) {
                $candidate = $n === 0 ? $base : ($base . $n);
                $chk = $pdo->prepare('SELECT 1 FROM "user" WHERE lower_name = :n LIMIT 1');
                $chk->execute([':n' => $candidate]);
                if (!$chk->fetchColumn()) {
                    $username = $candidate;
                    break;
                }
                $n++;
                if ($n > 50) {
                    $username = $base . bin2hex(random_bytes(2));
                    break;
                }
            }
            $now = time();
            $ins = $pdo->prepare('INSERT INTO "user" (lower_name, name, full_name, email, keep_email_private, email_notifications_preference, passwd, passwd_hash_algo, must_change_password, login_type, login_source, login_name, type, rands, salt, language, description, created_unix, updated_unix, max_repo_creation, is_active, is_admin, is_restricted, allow_create_organization, prohibit_login, avatar, avatar_email, use_custom_avatar, num_following, visibility, repo_admin_change_team_access, diff_view_style, theme, keep_activity_private)
                VALUES (:lower, :name, :full, :email, false, :notif, :passwd, :algo, false, 0, 1, :login_name, 0, :rands, :salt, :lang, :descr, :now, :now, -1, true, :admin, false, true, false, :avatar, :email2, false, 0, 0, false, :diff, :theme, false)
                RETURNING id');
            $ins->execute([
                ':lower' => $username,
                ':name' => $username,
                ':full' => $name !== '' ? $name : $username,
                ':email' => $email,
                ':notif' => 'enabled',
                ':passwd' => '',
                ':algo' => '',
                ':login_name' => $email,
                ':rands' => bin2hex(random_bytes(16)),
                ':salt' => '',
                ':lang' => '',
                ':descr' => '',
                ':now' => $now,
                ':admin' => $isAdmin ? 't' : 'f',
                ':avatar' => $avatar,
                ':email2' => $email,
                ':diff' => '',
                ':theme' => $giteaTheme,
            ]);
            $userId = (int)$ins->fetchColumn();
            $pdo->prepare('INSERT INTO email_address (uid, email, lower_email, is_activated, is_primary) SELECT :uid, :email, :lower, true, true WHERE NOT EXISTS (SELECT 1 FROM email_address WHERE lower_email = :lower2)')
                ->execute([':uid' => $userId, ':email' => $email, ':lower' => strtolower($email), ':lower2' => strtolower($email)]);
        } else {
            $pdo->prepare("UPDATE \"user\" SET is_admin = :admin, theme = :theme, full_name = CASE WHEN full_name = '' OR full_name IS NULL THEN :full ELSE full_name END, login_source = 1, login_name = COALESCE(NULLIF(login_name, ''), :login), updated_unix = :now WHERE id = :id")
                ->execute([
                    ':admin' => $isAdmin ? 't' : 'f',
                    ':theme' => $giteaTheme,
                    ':full' => $name,
                    ':login' => $email,
                    ':now' => time(),
                    ':id' => $userId,
                ]);
        }
        $ext = $pdo->prepare("INSERT INTO external_login_user (external_id, user_id, login_source_id, provider, email, name, nick_name, avatar_url, raw_data)
            VALUES (:ext, :uid, 1, 'AppDashboard', :email, :name, :nick, :avatar, CAST(:raw AS jsonb))
            ON CONFLICT (external_id, login_source_id) DO UPDATE SET user_id = EXCLUDED.user_id, email = EXCLUDED.email, name = EXCLUDED.name, nick_name = EXCLUDED.nick_name, avatar_url = EXCLUDED.avatar_url");
        $nick = oauth_sanitize_username(strstr($email, '@', true) ?: $email);
        $ext->execute([
            ':ext' => $email,
            ':uid' => (int)$userId,
            ':email' => $email,
            ':name' => $name !== '' ? $name : $nick,
            ':nick' => $nick,
            ':avatar' => $avatar,
            ':raw' => json_encode(['sub' => $email, 'email' => $email, 'name' => $name, 'preferred_username' => $nick], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (\Exception $e) {
    }
}

function oauth_ensure_plane_user(string $email, string $name, bool $isAdmin, string $avatar = '', string $theme = ''): void {
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    $pdo = oauth_plane_db();
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE lower(email) = lower(:email) LIMIT 1');
        $stmt->execute([':email' => $email]);
        $userId = $stmt->fetchColumn();
        [$first, $last] = oauth_split_name($name, strstr($email, '@', true) ?: 'user');
        $display = oauth_sanitize_username(strstr($email, '@', true) ?: $email);
        if (!$userId) {
            $userId = oauth_uuid();
            $ins = $pdo->prepare('INSERT INTO users (id, username, email, password, first_name, last_name, avatar, display_name, date_joined, created_at, updated_at, last_location, created_location, is_superuser, is_managed, is_password_expired, is_active, is_staff, is_email_verified, is_password_autoset, token, user_timezone, last_login_ip, last_logout_ip, last_login_medium, last_login_uagent, is_bot, is_email_valid, is_password_reset_required)
                VALUES (:id, :username, :email, :password, :first, :last, :avatar, :display, now(), now(), now(), :empty, :empty2, false, false, false, true, false, true, true, :token, :tz, :empty3, :empty4, :empty5, :empty6, false, false, false)');
            $ins->execute([
                ':id' => $userId,
                ':username' => bin2hex(random_bytes(16)),
                ':email' => strtolower($email),
                ':password' => '',
                ':first' => $first,
                ':last' => $last,
                ':avatar' => $avatar,
                ':display' => $display,
                ':empty' => '',
                ':empty2' => '',
                ':token' => bin2hex(random_bytes(32)),
                ':tz' => 'UTC',
                ':empty3' => '',
                ':empty4' => '',
                ':empty5' => '',
                ':empty6' => '',
            ]);
            $prof = $pdo->prepare('INSERT INTO profiles (id, created_at, updated_at, theme, is_tour_completed, onboarding_step, is_onboarded, billing_address_country, has_billing_address, company_name, user_id, is_mobile_onboarded, mobile_onboarding_step, mobile_timezone_auto_set, language, is_smooth_cursor_enabled, start_of_the_week, is_app_rail_docked, background_color, goals, has_marketing_email_consent, is_navigation_tour_completed, is_subscribed_to_changelog, notification_view_mode, product_tour)
                VALUES (:id, now(), now(), CAST(:theme AS jsonb), true, CAST(:onboarding AS jsonb), true, :country, false, :company, :user_id, false, CAST(:mobile AS jsonb), false, :lang, false, 0, true, :bg, CAST(:goals AS jsonb), true, false, false, :nvm, CAST(:product AS jsonb))');
            $defaultTheme = ['theme' => in_array($theme, ['light', 'dark'], true) ? $theme : 'system'];
            $prof->execute([
                ':id' => oauth_uuid(),
                ':theme' => json_encode($defaultTheme, JSON_UNESCAPED_SLASHES),
                ':onboarding' => '{"workspace_join": true, "profile_complete": true, "workspace_create": true, "workspace_invite": true}',
                ':country' => 'INDIA',
                ':company' => '',
                ':user_id' => $userId,
                ':mobile' => '{"workspace_join": false, "profile_complete": false, "workspace_create": false}',
                ':lang' => 'en',
                ':bg' => '#0acd60',
                ':goals' => '{}',
                ':nvm' => 'full',
                ':product' => '{"pages": false, "cycles": false, "intake": false, "modules": false, "work_items": false}',
            ]);
        } elseif ($avatar !== '') {
            $pdo->prepare('UPDATE users SET avatar = :avatar, updated_at = now() WHERE id = :id')->execute([':avatar' => $avatar, ':id' => $userId]);
        }
        if (in_array($theme, ['light', 'dark'], true)) {
            $planeTheme = $theme === 'dark'
                ? ['theme' => 'dark', 'primary' => '#3f76ff', 'background' => '#0f172a', 'sidebarBackground' => '#111827', 'text' => '#f8fafc', 'sidebarText' => '#f8fafc', 'palette' => '#0f172a,#f8fafc,#3f76ff,#111827,#f8fafc', 'darkPalette' => '#0f172a,#f8fafc,#3f76ff,#111827,#f8fafc']
                : ['theme' => 'light', 'primary' => '#3f76ff', 'background' => '#ffffff', 'sidebarBackground' => '#f8fafc', 'text' => '#111827', 'sidebarText' => '#111827', 'palette' => '#ffffff,#111827,#3f76ff,#f8fafc,#111827', 'darkPalette' => ''];
            $pdo->prepare('UPDATE profiles SET theme = CAST(:theme AS jsonb), updated_at = now() WHERE user_id = :id')
                ->execute([':theme' => json_encode($planeTheme, JSON_UNESCAPED_SLASHES), ':id' => $userId]);
        }
        if (!$isAdmin) {
            $pdo->prepare('DELETE FROM instance_admins WHERE user_id = :id')->execute([':id' => $userId]);
            return;
        }
        $pdo->prepare("INSERT INTO instance_admins(created_at,updated_at,id,role,is_verified,instance_id,user_id)
            SELECT now(),now(),:id,20,true,(SELECT id FROM instances ORDER BY created_at DESC LIMIT 1),:uid
            ON CONFLICT (instance_id,user_id) DO UPDATE SET role = 20, is_verified = true, updated_at = now()")
            ->execute([':id' => oauth_uuid(), ':uid' => $userId]);
    } catch (\Exception $e) {
    }
}

function oauth_provision_user_services(string $email, array $user = []): void {
    $email = trim($email);
    if ($email === '') return;
    if ($user === []) {
        $user = function_exists('getUserByEmail') ? (getUserByEmail($email) ?: []) : [];
    }
    $name = trim((string)($user['name'] ?? ''));
    $isAdmin = (int)($user['role'] ?? 0) >= 2;
    $theme = in_array((string)($user['theme'] ?? ''), ['light', 'dark'], true) ? (string)$user['theme'] : 'light';
    $photo = (string)($user['photo'] ?? '');
    $avatar = oauth_user_picture_url($photo);
    oauth_ensure_immich_user($email, $name !== '' ? $name : $email, $isAdmin);
    oauth_ensure_dawarich_user($email, $name, $isAdmin, $theme);
    oauth_ensure_gitea_user($email, $name, $isAdmin, $theme, $photo);
    oauth_ensure_plane_user($email, $name, $isAdmin, $avatar, $theme);
    oauth_sync_immich_dawarich($email);
}

function oauth_sync_plane_user(string $email, bool $isAdmin, string $avatar, string $theme = ''): void {
    oauth_ensure_plane_user($email, '', $isAdmin, $avatar, $theme);
}

function oauth_provision_all_dashboard_users(): void {
    if (!function_exists('getDB')) return;
    try {
        $db = getDB();
        $res = $db->query('SELECT email, name, role, theme, photo, verified FROM users');
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            if (empty($row['verified']) && ($row['email'] ?? '') !== 'root@root') continue;
            oauth_provision_user_services((string)$row['email'], $row);
        }
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
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
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
    $stmt->bindValue(':n', $now, PDO::PARAM_INT);
    $stmt->bindValue(':x', $now + 120, PDO::PARAM_INT);
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
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
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
    if (str_starts_with($path, '/plane-oauth/')) $path = substr($path, 12);
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
