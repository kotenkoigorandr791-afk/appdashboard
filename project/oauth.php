<?php

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/lib/app_domains.php';

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

function oauth_request_scheme(): string {
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $https = $forwardedProto === 'https'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
    return $https ? 'https' : 'http';
}

function oauth_request_host(): string {
    $forwardedHost = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0]);
    return $forwardedHost !== '' ? $forwardedHost : (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function oauth_is_loopback_origin(string $origin): bool {
    if ($origin === '') {
        return true;
    }
    $host = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?: ''));
    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
}

function oauth_normalize_public_origin(string $origin): string {
    $origin = rtrim($origin, '/');
    if ($origin === '' || oauth_is_loopback_origin($origin)) {
        return $origin;
    }
    if (str_starts_with($origin, 'http://')) {
        return 'https://' . substr($origin, strlen('http://'));
    }
    return $origin;
}

function oauth_is_internal_service_host(string $host): bool {
    $host = strtolower(trim($host));
    if ($host === '' || oauth_is_loopback_origin('http://' . $host)) {
        return true;
    }
    if (in_array($host, ['appdashboard'], true)) {
        return true;
    }
    return !str_contains($host, '.') && preg_match('/^[a-z0-9-]+$/', $host) === 1;
}

function oauth_public_origin(): string {
    $configured = function_exists('getConfig') ? rtrim(trim(getConfig('app_public_base_url', '')), '/') : '';
    $fromEnv = function_exists('app_public_url') ? rtrim(app_public_url(''), '/') : '';
    foreach ([$configured, $fromEnv] as $candidate) {
        if ($candidate === '') {
            continue;
        }
        if (oauth_is_loopback_origin($candidate)) {
            return $candidate;
        }
        return oauth_normalize_public_origin($candidate);
    }
    $request = rtrim(oauth_request_scheme() . '://' . oauth_request_host(), '/');
    $host = strtolower((string)(parse_url($request, PHP_URL_HOST) ?: ''));
    if ($request !== '' && !oauth_is_internal_service_host($host)) {
        return oauth_normalize_public_origin($request);
    }
    return 'http://localhost';
}

function oauth_internal_discovery_url(): string {
    $issuer = oauth_issuer();
    $configured = trim((string)(getenv('OAUTH_INTERNAL_DISCOVERY_URL') ?: ''));
    $candidates = array_values(array_filter([
        $configured !== '' ? $configured : null,
        'http://appdashboard/.well-known/openid-configuration',
        'http://host.docker.internal/.well-known/openid-configuration',
        $issuer . '/.well-known/openid-configuration',
    ]));
    foreach ($candidates as $url) {
        $url = rtrim($url, '/');
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        if (!is_string($body) || $body === '') {
            continue;
        }
        $json = json_decode($body, true);
        if (is_array($json) && (string)($json['issuer'] ?? '') === $issuer) {
            return $url;
        }
    }
    return $issuer . '/.well-known/openid-configuration';
}

function oauth_meta_get(string $key): string {
    if (!function_exists('getDB')) {
        return '';
    }
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT value FROM app_meta WHERE key = :k LIMIT 1');
        $stmt->bindValue(':k', $key);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? trim((string)($row['value'] ?? '')) : '';
    } catch (\Exception $e) {
        return '';
    }
}

function oauth_meta_set(string $key, string $value): void {
    if (!function_exists('getDB')) {
        return;
    }
    try {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO app_meta(key, value) VALUES(:k, :v) ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value');
        $stmt->bindValue(':k', $key);
        $stmt->bindValue(':v', $value);
        $stmt->execute();
    } catch (\Exception $e) {
    }
}

function oauth_issuer(): string {
    return oauth_public_origin();
}

function oauth_service_url(string $key, string $default): string {
    static $envKeys = [
        'gitea_url' => 'GITEA_PUBLIC_URL',
        'immich_url' => 'IMMICH_PUBLIC_URL',
        'dawarich_url' => 'DAWARICH_PUBLIC_URL',
    ];
    if (isset($envKeys[$key])) {
        $fromEnv = trim((string)(getenv($envKeys[$key]) ?: ''));
        if ($fromEnv !== '') {
            return rtrim($fromEnv, '/');
        }
    }
    $configured = function_exists('getConfig') ? rtrim(trim(getConfig($key, '')), '/') : '';
    return $configured !== '' ? $configured : rtrim($default, '/');
}

function oauth_external_app_url(string $app): string {
    return match ($app) {
        'gitea' => oauth_service_url('gitea_url', app_public_url('git')),
        'immich' => oauth_service_url('immich_url', app_public_url('photo')),
        'dawarich' => oauth_service_url('dawarich_url', app_public_url('dawarich')),
        default => oauth_public_origin(),
    };
}

function oauth_embed_app_path(string $app): string {
    return match ($app) {
        'gitea' => '/embed/git',
        'immich' => '/embed/photo',
        default => '',
    };
}

function oauth_iframe_app_url(string $app): string {
    $embedPath = oauth_embed_app_path($app);
    $external = oauth_external_app_url($app);
    if ($embedPath === '') {
        return $external;
    }
    $origin = oauth_public_origin();
    $ext = parse_url($external) ?: [];
    $pub = parse_url($origin) ?: [];
    $extPort = (int)($ext['port'] ?? ((($ext['scheme'] ?? '') === 'https') ? 443 : 80));
    $pubPort = (int)($pub['port'] ?? ((($pub['scheme'] ?? '') === 'https') ? 443 : 80));
    $extHost = strtolower((string)($ext['host'] ?? ''));
    $pubHost = strtolower((string)($pub['host'] ?? ''));
    if ($extHost === $pubHost && $extPort !== $pubPort) {
        return rtrim($origin, '/') . $embedPath;
    }
    return $external;
}

function oauth_redirect_iframe(string $url): void {
    header('Location: ' . $url);
    exit;
}

function oauth_redirect_html(string $url): void {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><script>var u=' . json_encode($url) . ';if(window!==window.top)window.top.location.href=u;else window.location.href=u;</script></head><body></body></html>';
    exit;
}

function oauth_login_url(): string {
    return oauth_public_origin() . '/login';
}

function oauth_bridge_url(string $next): string {
    return oauth_public_origin() . '/oauth-bridge?t=' . urlencode(oauth_create_bridge_token(session_id())) . '&next=' . urlencode($next);
}

function oauth_app_return_url(string $app, string $theme = ''): string {
    $qs = http_build_query(array_filter([
        'app' => $app,
        'lang' => (string)($_GET['lang'] ?? ''),
        'theme' => $theme,
        'color' => (string)($_GET['color'] ?? ''),
    ], static fn($value): bool => $value !== ''));
    return '/main' . ($qs !== '' ? '?' . $qs : '');
}

function oauth_app_bridge_url(string $app, string $theme = ''): string {
    return oauth_bridge_url(oauth_app_return_url($app, $theme));
}

function oauth_dashboard_photo_url(string $photo): string {
    $photo = trim($photo);
    if ($photo === '' || $photo === 'default_avatar.png') return '';
    if (preg_match('#^https?://#i', $photo)) return $photo;
    return oauth_public_origin() . '/' . ltrim($photo, '/');
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
    $hash = appPasswordHash($clientSecret);
    $uris = implode("\n", $redirectUris);
    $stmt = $db->prepare("INSERT INTO oauth_clients(client_id,client_secret_hash,name,redirect_uris) VALUES(:c,:h,:n,:r) ON CONFLICT (client_id) DO UPDATE SET client_secret_hash = EXCLUDED.client_secret_hash, name = EXCLUDED.name, redirect_uris = EXCLUDED.redirect_uris");
    $stmt->bindValue(':c', $clientId);
    $stmt->bindValue(':h', $hash);
    $stmt->bindValue(':n', $name);
    $stmt->bindValue(':r', $uris);
    $stmt->execute();
}
function oauth_verify_client_secret(string $secret, string $hash): bool {
    if ($secret === '' || $hash === '') return false;
    $info = password_get_info($hash);
    if (($info['algoName'] ?? '') === 'argon2id') return password_verify($secret, $hash);
    if (($info['algoName'] ?? '') === 'bcrypt') return password_verify($secret, $hash);
    return false;
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
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($auth === '' && function_exists('getallheaders')) {
        foreach (getallheaders() ?: [] as $name => $value) {
            if (strcasecmp((string)$name, 'Authorization') === 0) {
                $auth = (string)$value;
                break;
            }
        }
    }
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

function oauth_discovery_endpoints(): array {
    $issuer = oauth_issuer();
    $host = strtolower(oauth_request_host());
    $apiBase = $issuer;
    if (oauth_is_loopback_origin($issuer) && oauth_is_internal_service_host($host)) {
        $apiBase = 'http://appdashboard';
    }
    return [
        'issuer' => $issuer,
        'authorization_endpoint' => $issuer . '/authorize',
        'token_endpoint' => $apiBase . '/token',
        'userinfo_endpoint' => $apiBase . '/userinfo',
        'jwks_uri' => $apiBase . '/.well-known/jwks.json',
    ];
}

function oauth_route_discovery(): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(oauth_discovery_endpoints(), [
        'response_types_supported' => ['code'],
        'subject_types_supported' => ['public'],
        'id_token_signing_alg_values_supported' => ['RS256'],
        'scopes_supported' => ['openid', 'email', 'profile', 'offline_access'],
        'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
        'claims_supported' => ['sub', 'email', 'email_verified', 'name', 'preferred_username', 'picture', 'role', 'is_admin'],
        'code_challenge_methods_supported' => ['S256', 'plain'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
    ]), JSON_UNESCAPED_SLASHES);
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
    try {
        $db = getDB();
        ensureOAuthProviderSchema($db);
        [$clientId, $clientSecret] = oauth_extract_client_credentials();
        $client = oauth_find_client($db, $clientId);
        $storedHash = is_array($client) ? (string)($client['client_secret_hash'] ?? '') : '';
        if (!$client || !oauth_verify_client_secret($clientSecret, $storedHash)) {
            http_response_code(401);
            echo json_encode(['error' => 'invalid_client']);
            exit;
        }
        if ($clientSecret !== '' && appPasswordNeedsRehash($storedHash)) {
            $up = $db->prepare('UPDATE oauth_clients SET client_secret_hash = :h WHERE client_id = :c');
            $up->bindValue(':h', appPasswordHash($clientSecret));
            $up->bindValue(':c', (string)$client['client_id']);
            $up->execute();
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
    } catch (Throwable $e) {
        error_log('OAuth token error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'server_error']);
        exit;
    }
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

function oauth_docker_env(string $container): array {
    $vars = [];
    $container = preg_replace('/[^A-Za-z0-9_.-]/', '', $container) ?? '';
    if ($container === '') return $vars;
    $out = shell_exec('docker exec ' . escapeshellarg($container) . ' env 2>/dev/null');
    if (!is_string($out) || $out === '') return $vars;
    foreach (explode("\n", $out) as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $vars[trim($k)] = trim($v, " \t\"'");
    }
    return $vars;
}

function oauth_pgsql_reachable(string $host, int $port, float $timeout = 0.2): bool {
    if (!filter_var($host, FILTER_VALIDATE_IP)) {
        $resolved = @gethostbyname($host);
        if ($resolved === '' || $resolved === $host || !filter_var($resolved, FILTER_VALIDATE_IP)) return false;
        $host = $resolved;
    }
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, $timeout);
    if ($fp) {
        @fclose($fp);
        return true;
    }
    return false;
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
    $user = (string)($vars['DB_USERNAME'] ?? getenv('IMMICH_DB_USER') ?: 'immich');
    $password = (string)($vars['DB_PASSWORD'] ?? getenv('IMMICH_DB_PASSWORD') ?: 'immich_dev_password');
    $dbname = (string)($vars['DB_DATABASE_NAME'] ?? 'immich');
    $hosts = array_values(array_filter([
        getenv('IMMICH_DB_HOST') ?: null,
        'immich-db',
        '127.0.0.1',
        'host.docker.internal',
    ]));
    foreach (['immich-db', 'immich_postgres'] as $container) {
        $ip = trim((string)shell_exec("docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' {$container} 2>/dev/null"));
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && !in_array($ip, $hosts, true)) $hosts[] = $ip;
    }
    foreach (['172.17.0.1', '172.19.0.1'] as $extra) {
        if (!in_array($extra, $hosts, true)) $hosts[] = $extra;
    }
    foreach ($hosts as $host) {
        foreach ([5436, 5432] as $port) {
            if ($host === '127.0.0.1' && $port === 5432) continue;
            if (!oauth_pgsql_reachable($host, $port)) continue;
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
    $immichUrl = oauth_external_app_url('immich');
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
    exit;
}



function oauth_gitea_login_source_id(): int {
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    $id = 1;
    $pdo = oauth_gitea_db();
    if (!$pdo) {
        return $id;
    }
    try {
        $row = $pdo->query("SELECT id FROM login_source WHERE lower(name) = 'appdashboard' AND is_active = true ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($row) {
            $id = (int)$row;
        }
    } catch (\Exception $e) {
    }
    return $id;
}

function oauth_gitea_db(): ?PDO {
    static $pdo = null;
    static $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    $hosts = array_values(array_filter([
        getenv('GITEA_DB_HOST') ?: null,
        'gitea-db',
        '127.0.0.1',
        'host.docker.internal',
    ]));
    foreach (['172.17.0.1', '172.19.0.1'] as $extra) {
        if (!in_array($extra, $hosts, true)) $hosts[] = $extra;
    }
    $passwords = array_values(array_unique(array_filter([
        getenv('GITEA_DB_PASSWORD') ?: null,
        'gitea_dev_password',
        'gitea',
    ])));
    foreach ($hosts as $host) {
        if (!oauth_pgsql_reachable($host, 5432)) continue;
        foreach ($passwords as $password) {
            try {
                $pdo = new PDO(sprintf('pgsql:host=%s;port=%d;dbname=gitea', $host, 5432), 'gitea', $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3,
                ]);
                return $pdo;
            } catch (\Exception $e) {
                $pdo = null;
            }
        }
    }
    return null;
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

function oauth_immich_cluster_group_id(PDO $pdo): ?string {
    try {
        $id = $pdo->query('SELECT id FROM cluster_group ORDER BY "createdAt" ASC LIMIT 1')->fetchColumn();
        if (is_string($id) && $id !== '') return $id;
        $id = $pdo->query('SELECT id FROM cluster_group ORDER BY "createdAt" ASC LIMIT 1 FOR UPDATE')->fetchColumn();
        if (is_string($id) && $id !== '') return $id;
        $ins = $pdo->prepare('INSERT INTO cluster_group (name) VALUES (:name) RETURNING id');
        $ins->execute([':name' => 'default']);
        $id = $ins->fetchColumn();
        return is_string($id) && $id !== '' ? $id : null;
    } catch (\Exception $e) {
        return null;
    }
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
        $clusterGroupId = oauth_immich_cluster_group_id($pdo);
        if ($clusterGroupId === null) return;
        $ins = $pdo->prepare('INSERT INTO "user" (email, name, password, "isAdmin", "oauthId", "shouldChangePassword", status, "clusterGroupId") VALUES (:email, :name, :password, :admin, :oid, false, :status, :clusterGroupId)');
        $ins->execute([
            ':email' => $email,
            ':name' => $name !== '' ? $name : $email,
            ':password' => '',
            ':admin' => $isAdmin ? 't' : 'f',
            ':oid' => $email,
            ':status' => 'active',
            ':clusterGroupId' => $clusterGroupId,
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
    $giteaTheme = $theme === 'auto' ? 'gitea-auto' : ($theme === 'dark' ? 'gitea-dark' : 'gitea-light');
    $username = oauth_sanitize_username(strstr($email, '@', true) ?: $email);
    $avatar = '';
    $photo = trim($photo);
    if ($photo !== '' && $photo !== 'default_avatar.png') {
    $avatar = oauth_dashboard_photo_url($photo);
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
            $loginSourceId = oauth_gitea_login_source_id();
            $ins = $pdo->prepare('INSERT INTO "user" (lower_name, name, full_name, email, keep_email_private, email_notifications_preference, passwd, passwd_hash_algo, must_change_password, login_type, login_source, login_name, type, rands, salt, language, description, created_unix, updated_unix, max_repo_creation, is_active, is_admin, is_restricted, allow_create_organization, prohibit_login, avatar, avatar_email, use_custom_avatar, num_following, visibility, repo_admin_change_team_access, diff_view_style, theme, keep_activity_private)
                VALUES (:lower, :name, :full, :email, false, :notif, :passwd, :algo, false, 0, :login_source, :login_name, 0, :rands, :salt, :lang, :descr, :now, :now, -1, true, :admin, false, true, false, :avatar, :email2, false, 0, 0, false, :diff, :theme, false)
                RETURNING id');
            $ins->execute([
                ':lower' => $username,
                ':name' => $username,
                ':full' => $name !== '' ? $name : $username,
                ':email' => $email,
                ':notif' => 'enabled',
                ':passwd' => '',
                ':algo' => '',
                ':login_source' => oauth_gitea_login_source_id(),
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
            $loginSourceId = oauth_gitea_login_source_id();
            $pdo->prepare("UPDATE \"user\" SET is_admin = :admin, theme = :theme, full_name = CASE WHEN full_name = '' OR full_name IS NULL THEN :full ELSE full_name END, login_source = :login_source, login_name = COALESCE(NULLIF(login_name, ''), :login), updated_unix = :now WHERE id = :id")
                ->execute([
                    ':admin' => $isAdmin ? 't' : 'f',
                    ':theme' => $giteaTheme,
                    ':full' => $name,
                    ':login' => $email,
                    ':login_source' => $loginSourceId,
                    ':now' => time(),
                    ':id' => $userId,
                ]);
        }
        $loginSourceId = oauth_gitea_login_source_id();
        $ext = $pdo->prepare("INSERT INTO external_login_user (external_id, user_id, login_source_id, provider, email, name, nick_name, avatar_url, raw_data)
            VALUES (:ext, :uid, :login_source, 'AppDashboard', :email, :name, :nick, :avatar, CAST(:raw AS jsonb))
            ON CONFLICT (external_id, login_source_id) DO UPDATE SET user_id = EXCLUDED.user_id, email = EXCLUDED.email, name = EXCLUDED.name, nick_name = EXCLUDED.nick_name, avatar_url = EXCLUDED.avatar_url");
        $nick = oauth_sanitize_username(strstr($email, '@', true) ?: $email);
        $ext->execute([
            ':ext' => $email,
            ':uid' => (int)$userId,
            ':login_source' => $loginSourceId,
            ':email' => $email,
            ':name' => $name !== '' ? $name : $nick,
            ':nick' => $nick,
            ':avatar' => $avatar,
            ':raw' => json_encode(['sub' => $email, 'email' => $email, 'name' => $name, 'preferred_username' => $nick], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
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
    $theme = in_array((string)($user['theme'] ?? ''), ['light', 'dark', 'auto'], true) ? (string)$user['theme'] : 'light';
    $photo = (string)($user['photo'] ?? '');
    $avatar = oauth_user_picture_url($photo);
    oauth_ensure_immich_user($email, $name !== '' ? $name : $email, $isAdmin);
    oauth_ensure_dawarich_user($email, $name, $isAdmin, $theme);
    oauth_ensure_gitea_user($email, $name, $isAdmin, $theme, $photo);
    oauth_sync_immich_dawarich($email);
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
    session_id($sessionId);
    require_once APP_ROOT . '/lib/app_session.php';
    app_session_start();
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
