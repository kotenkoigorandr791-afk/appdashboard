<?php
$parts = public_api_parse_path();
$action = $parts[1] ?? '';
$sub = $parts[2] ?? '';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($action === '' && $method === 'GET') {
    public_api_json(['ok' => true, 'endpoints' => ['login','me','revoke','refresh','client_auth']]);
}
if ($action === 'login' && $method === 'POST') {
    public_api_check_lockout('login');
    public_api_require_device_id();
    $body = public_api_body();
    $email = trim((string)($body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');
    $deviceName = trim((string)($body['device_name'] ?? ''));
    $platform = trim((string)($body['platform'] ?? 'mcp'));
    if ($email === '' || $password === '') public_api_json(['ok' => false, 'error' => 'missing_credentials'], 400);
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
    $tokenData = public_api_issue_token($db, $email, public_api_device_id(), $deviceName, $platform, null, 'public_api');
    $role = $email === 'root@root' ? 2 : (int)($users[$email]['role'] ?? 0);
    public_api_json(['ok' => true, 'token' => $tokenData['token'], 'expires_at' => $tokenData['expires_at'], 'token_type' => 'Bearer', 'user' => ['email' => $email, 'name' => $users[$email]['name'] ?? '', 'role' => $role]]);
}
if ($action === 'me' && $method === 'GET') {
    $auth = public_api_auth_user(true);
    $users = loadUsers();
    $email = $auth['email'];
    $role = $email === 'root@root' ? 2 : (int)($users[$email]['role'] ?? 0);
    public_api_json(['ok' => true, 'user' => ['email' => $email, 'name' => $users[$email]['name'] ?? '', 'role' => $role, 'photo' => $users[$email]['photo'] ?? '', 'language' => $users[$email]['language'] ?? 'en', 'theme' => $users[$email]['theme'] ?? 'light']]);
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
    $tokenData = public_api_issue_token($db, $auth['email'], public_api_device_id(), trim((string)($body['device_name'] ?? '')), trim((string)($body['platform'] ?? 'mcp')), null, 'public_api');
    public_api_json(['ok' => true, 'token' => $tokenData['token'], 'expires_at' => $tokenData['expires_at'], 'token_type' => 'Bearer']);
}
if ($action === 'client_auth') {
    $db = getDB();
    if (!$db) public_api_json(['ok' => false, 'error' => 'db_unavailable'], 500);
    $body = public_api_body();
    if ($sub === 'request' && $method === 'POST') {
        $deviceId = public_api_require_device_id();
        $deviceName = trim((string)($body['device_name'] ?? 'MCP Client'));
        $platform = trim((string)($body['platform'] ?? 'mcp'));
        $req = public_api_create_browser_auth_request($db, $deviceId, $deviceName, $platform, '');
        $browserUrl = public_api_host_base() . '/client-auth?request_id=' . urlencode($req['request_id']) . '&browser_token=' . urlencode($req['browser_token']);
        public_api_json([
            'ok' => true,
            'request_id' => $req['request_id'],
            'poll_token' => $req['poll_token'],
            'browser_url' => $browserUrl,
            'auth_url' => $browserUrl,
            'expires_at' => $req['expires_at'],
        ]);
    }
    if ($sub === 'poll' && $method === 'POST') {
        $requestId = trim((string)($body['request_id'] ?? ''));
        $pollToken = trim((string)($body['poll_token'] ?? ''));
        if ($requestId === '' || $pollToken === '') public_api_json(['ok' => false, 'error' => 'missing_request'], 400);
        public_api_purge_browser_auth($db);
        $row = public_api_get_browser_auth_request($db, $requestId);
        if (!$row || !public_api_browser_auth_matches($pollToken, (string)($row['poll_token_hash'] ?? ''))) {
            public_api_json(['ok' => false, 'error' => 'request_not_found'], 404);
        }
        if ((int)($row['expires_at'] ?? 0) < time()) {
            public_api_json(['ok' => true, 'status' => 'expired', 'approved' => false]);
        }
        $status = (string)($row['status'] ?? 'pending');
        if ($status === 'pending') {
            public_api_json(['ok' => true, 'status' => 'pending', 'approved' => false]);
        }
        if ($status === 'claimed') {
            public_api_json(['ok' => false, 'error' => 'already_claimed'], 409);
        }
        if ($status !== 'approved') {
            public_api_json(['ok' => false, 'error' => 'request_invalid'], 409);
        }
        $email = (string)($row['email'] ?? '');
        $token = (string)($row['issued_token'] ?? '');
        if ($email === '' || $token === '') public_api_json(['ok' => false, 'error' => 'token_missing'], 409);
        $deviceId = public_api_device_id();
        if ($deviceId !== '' && (string)($row['device_id'] ?? '') !== '' && $deviceId !== (string)$row['device_id']) {
            public_api_json(['ok' => false, 'error' => 'device_mismatch'], 403);
        }
        $upd = $db->prepare("UPDATE client_auth_browser_requests SET status = 'claimed', claimed_at = :t, issued_token = '' WHERE request_id = :r");
        $upd->bindValue(':t', time(), PDO::PARAM_INT);
        $upd->bindValue(':r', $requestId, PDO::PARAM_STR);
        $upd->execute();
        $users = loadUsers();
        $role = $email === 'root@root' ? 2 : (int)($users[$email]['role'] ?? 0);
        public_api_json([
            'ok' => true,
            'status' => 'approved',
            'approved' => true,
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => ['email' => $email, 'name' => $users[$email]['name'] ?? '', 'role' => $role],
        ]);
    }
    public_api_json(['ok' => false, 'error' => 'unknown_client_auth_action'], 404);
}
public_api_json(['ok' => false, 'error' => 'unknown_auth_action'], 404);
