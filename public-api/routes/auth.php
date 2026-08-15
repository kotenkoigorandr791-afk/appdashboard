<?php
$parts = public_api_parse_path();
$action = $parts[1] ?? '';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($action === '' && $method === 'GET') {
    public_api_json(['ok' => true, 'endpoints' => ['login','me','revoke','refresh']]);
}
if ($action === 'login' && $method === 'POST') {
    public_api_check_lockout('login');
    public_api_require_device_id();
    $body = public_api_body();
    $email = trim((string)($body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');
    $deviceName = trim((string)($body['device_name'] ?? ''));
    $platform = trim((string)($body['platform'] ?? 'mobile'));
    if ($email === '' || $password === '') public_api_json(['ok' => false, 'error' => 'missing_credentials'], 400);
    if (function_exists('validatePasswordPolicy')) {
        // login only validates stored hash, not incoming password policy
    }
    $users = loadUsers();
    if (!isset($users[$email]) || !appPasswordVerify($password, (string)($users[$email]['password'] ?? ''))) {
        public_api_record_fail('login');
        public_api_json(['ok' => false, 'error' => 'invalid_credentials'], 401);
    }
    if (appPasswordNeedsRehash((string)$users[$email]['password'])) {
        $users[$email]['password'] = appPasswordHash($password);
        upsertUser($email, $users[$email]);
    }
    if ($email !== 'root@root' && empty($users[$email]['verified'])) {
        public_api_json(['ok' => false, 'error' => 'email_not_verified'], 403);
    }
    $db = getDB();
    if (!$db) public_api_json(['ok' => false, 'error' => 'db_unavailable'], 500);
    public_api_reset_fails('login');
    $tokenData = public_api_issue_token($db, $email, public_api_device_id(), $deviceName, $platform);
    public_api_json(['ok' => true, 'token' => $tokenData['token'], 'expires_at' => $tokenData['expires_at'], 'token_type' => 'Bearer', 'user' => ['email' => $email, 'name' => $users[$email]['name'] ?? '', 'role' => (int)($users[$email]['role'] ?? 0)]]);
}
if ($action === 'me' && $method === 'GET') {
    $auth = public_api_auth_user(true);
    $users = loadUsers();
    $email = $auth['email'];
    public_api_json(['ok' => true, 'user' => ['email' => $email, 'name' => $users[$email]['name'] ?? '', 'role' => (int)($users[$email]['role'] ?? 0)]]);
}
if ($action === 'revoke' && $method === 'POST') {
    $token = public_api_bearer_token();
    if ($token === '') public_api_json(['ok' => false, 'error' => 'missing_token'], 401);
    $db = getDB();
    if ($db) public_api_revoke_token($db, $token);
    public_api_json(['ok' => true]);
}
if ($action === 'refresh' && $method === 'POST') {
    $auth = public_api_auth_user(true);
    $body = public_api_body();
    $db = getDB();
    if (!$db) public_api_json(['ok' => false, 'error' => 'db_unavailable'], 500);
    public_api_revoke_token($db, public_api_bearer_token());
    $users = loadUsers();
    $email = $auth['email'];
    $tokenData = public_api_issue_token($db, $email, public_api_device_id(), trim((string)($body['device_name'] ?? '')), trim((string)($body['platform'] ?? 'mobile')));
    public_api_json(['ok' => true, 'token' => $tokenData['token'], 'expires_at' => $tokenData['expires_at'], 'token_type' => 'Bearer']);
}
public_api_json(['ok' => false, 'error' => 'unknown_auth_action'], 404);
