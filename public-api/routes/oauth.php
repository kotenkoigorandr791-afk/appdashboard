<?php
$parts = public_api_parse_path();
$action = $parts[1] ?? '';
$sub = $parts[2] ?? '';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$db = getDB();
if (!$db) public_api_json(['ok' => false, 'error' => 'db_unavailable'], 500);
public_api_ensure_oauth_schema($db);

if ($action === '' && $method === 'GET') {
    public_api_json(['ok' => true, 'endpoints' => ['register', 'token', 'client', 'approve', 'revoke']]);
}

if ($action === 'register' && $method === 'POST') {
    public_api_check_lockout('login');
    $body = public_api_form_body();
    $client = public_api_oauth_register_client($db, $body);
    if (isset($client['error'])) public_api_json(['ok' => false, 'error' => $client['error']], 400);
    public_api_json($client, 201);
}

if ($action === 'token' && $method === 'POST') {
    public_api_check_lockout('login');
    $body = public_api_form_body();
    $grantType = trim((string)($body['grant_type'] ?? ''));
    $clientId = public_api_client_id_from_request();
    $clientSecret = public_api_client_secret_from_request();
    $client = public_api_oauth_verify_secret($db, $clientId, $clientSecret);
    if (!$client) public_api_json(['ok' => false, 'error' => 'invalid_client'], 401);
    if ($grantType === 'authorization_code') {
        $code = trim((string)($body['code'] ?? ''));
        $verifier = trim((string)($body['code_verifier'] ?? ''));
        $redirectUri = trim((string)($body['redirect_uri'] ?? ''));
        if ($code === '' || $verifier === '') public_api_json(['ok' => false, 'error' => 'invalid_grant'], 400);
        $row = public_api_oauth_consume_code($db, $code, $clientId);
        if (!$row) public_api_json(['ok' => false, 'error' => 'invalid_grant'], 400);
        if ((string)($row['redirect_uri'] ?? '') !== $redirectUri) public_api_json(['ok' => false, 'error' => 'invalid_grant'], 400);
        if (!public_api_oauth_pkce_verify((string)($row['code_challenge'] ?? ''), $verifier, (string)($row['code_challenge_method'] ?? 'S256'))) {
            public_api_json(['ok' => false, 'error' => 'invalid_grant'], 400);
        }
        $email = (string)($row['email'] ?? '');
        $users = loadUsers();
        if (!isset($users[$email])) public_api_json(['ok' => false, 'error' => 'user_not_found'], 400);
        $deviceId = 'oauth_' . bin2hex(random_bytes(8));
        $tokens = public_api_oauth_issue_tokens($db, $client, $email, $deviceId, 'mcp');
        $role = $email === 'root@root' ? 2 : (int)($users[$email]['role'] ?? 0);
        $tokens['user'] = ['email' => $email, 'name' => $users[$email]['name'] ?? '', 'role' => $role];
        public_api_json($tokens);
    }
    if ($grantType === 'refresh_token') {
        $refresh = trim((string)($body['refresh_token'] ?? ''));
        if ($refresh === '') public_api_json(['ok' => false, 'error' => 'invalid_grant'], 400);
        $rotated = public_api_oauth_rotate_refresh($db, $refresh, $clientId);
        if (!$rotated) public_api_json(['ok' => false, 'error' => 'invalid_grant'], 400);
        $email = (string)$rotated['email'];
        $users = loadUsers();
        if (!isset($users[$email])) public_api_json(['ok' => false, 'error' => 'user_not_found'], 400);
        $tokens = public_api_oauth_issue_tokens($db, $client, $email, (string)$rotated['device_id'], 'mcp');
        $tokens['refresh_token'] = (string)$rotated['new_refresh_token'];
        $role = $email === 'root@root' ? 2 : (int)($users[$email]['role'] ?? 0);
        $tokens['user'] = ['email' => $email, 'name' => $users[$email]['name'] ?? '', 'role' => $role];
        public_api_json($tokens);
    }
    public_api_json(['ok' => false, 'error' => 'unsupported_grant_type'], 400);
}

if ($action === 'client' && $method === 'GET') {
    $clientId = trim((string)($_GET['client_id'] ?? $_GET['id'] ?? ''));
    $redirectUri = trim((string)($_GET['redirect_uri'] ?? ''));
    if ($clientId === '') public_api_json(['ok' => false, 'error' => 'missing_client_id'], 400);
    $client = public_api_oauth_get_client($db, $clientId);
    if (!$client) public_api_json(['ok' => true, 'found' => false]);
    public_api_json(['ok' => true, 'found' => true, 'client_id' => $clientId, 'client_name' => $client['client_name'], 'redirect_uris' => json_decode((string)($client['redirect_uris'] ?? '[]'), true), 'redirect_ok' => $redirectUri === '' ? null : public_api_oauth_redirect_ok($db, $clientId, $redirectUri) !== null]);
}

if ($action === 'approve' && $method === 'POST') {
    $sessionUser = isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
    $email = (string)($sessionUser['email'] ?? '');
    if ($email === '') public_api_json(['ok' => false, 'error' => 'not_authenticated'], 401);
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    $selfOrigin = public_api_host_base();
    if ($origin !== '' && $origin !== $selfOrigin) public_api_json(['ok' => false, 'error' => 'origin_mismatch'], 403);
    $body = public_api_body();
    $clientId = trim((string)($body['client_id'] ?? ''));
    $redirectUri = trim((string)($body['redirect_uri'] ?? ''));
    $codeChallenge = trim((string)($body['code_challenge'] ?? ''));
    $codeChallengeMethod = trim((string)($body['code_challenge_method'] ?? 'S256'));
    $state = trim((string)($body['state'] ?? ''));
    if ($clientId === '' || $redirectUri === '') public_api_json(['ok' => false, 'error' => 'missing_params'], 400);
    if ($codeChallengeMethod !== 'S256' && $codeChallengeMethod !== 'plain') public_api_json(['ok' => false, 'error' => 'invalid_code_challenge_method'], 400);
    if (!public_api_oauth_valid_code_challenge($codeChallenge)) public_api_json(['ok' => false, 'error' => 'invalid_code_challenge'], 400);
    $client = public_api_oauth_redirect_ok($db, $clientId, $redirectUri);
    if (!$client) public_api_json(['ok' => false, 'error' => 'client_not_allowed'], 403);
    $users = loadUsers();
    if (!isset($users[$email])) public_api_json(['ok' => false, 'error' => 'user_not_found'], 404);
    $result = public_api_oauth_create_code($db, $clientId, $redirectUri, $codeChallenge, $codeChallengeMethod, $state, $email);
    $role = $email === 'root@root' ? 2 : (int)($users[$email]['role'] ?? 0);
    public_api_json(array_merge(['ok' => true, 'code' => $result['code'], 'state' => $state, 'redirect_uri' => $redirectUri, 'client_name' => $client['client_name']], ['user' => ['email' => $email, 'name' => $users[$email]['name'] ?? '', 'role' => $role]]));
}

if ($action === 'revoke' && $method === 'POST') {
    $body = public_api_form_body();
    $refresh = trim((string)($body['token'] ?? ''));
    if ($refresh !== '') public_api_oauth_revoke_refresh($db, $refresh);
    public_api_json(['ok' => true]);
}

public_api_json(['ok' => false, 'error' => 'unknown_oauth_action'], 404);