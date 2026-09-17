<?php
public_api_auth_user(true);
require_once dirname(__DIR__, 2) . '/lib/admin_security.php';
adminSecurityEnsureSchema();
$parts = public_api_parse_path();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$resource = $parts[1] ?? '';
$emailParam = isset($parts[2]) ? rawurldecode((string)$parts[2]) : '';
if ($resource === 'users') {
    public_api_require_role(2);
    if (!adminIpAllowed()) public_api_json(['ok' => false, 'error' => 'admin_ip_forbidden'], 403);
    $users = loadUsers();
    if ($emailParam === '' && $method === 'GET') {
        $list = [];
        foreach ($users as $email => $u) {
            $list[] = [
                'email' => $email,
                'name' => $u['name'] ?? '',
                'role' => $email === 'root@root' ? 2 : (int)($u['role'] ?? 0),
                'verified' => $email === 'root@root' ? true : !empty($u['verified']),
                'photo' => $u['photo'] ?? '',
            ];
        }
        public_api_json(['ok' => true, 'users' => $list]);
    }
    if ($emailParam === '') public_api_json(['ok' => false, 'error' => 'missing_email'], 400);
    if (!isset($users[$emailParam])) public_api_json(['ok' => false, 'error' => 'user_not_found'], 404);
    if ($method === 'GET') {
        $u = $users[$emailParam];
        public_api_json(['ok' => true, 'user' => [
            'email' => $emailParam,
            'name' => $u['name'] ?? '',
            'role' => $emailParam === 'root@root' ? 2 : (int)($u['role'] ?? 0),
            'verified' => $emailParam === 'root@root' ? true : !empty($u['verified']),
            'photo' => $u['photo'] ?? '',
            'language' => $u['language'] ?? 'en',
            'theme' => $u['theme'] ?? 'light',
        ]]);
    }
    if ($method === 'PUT') {
        if ($emailParam === 'root@root') public_api_json(['ok' => false, 'error' => 'cannot_modify_root'], 403);
        $actor = (string)($_SESSION['user']['email'] ?? '');
        $body = public_api_body();
        $fields = [];
        if (array_key_exists('role', $body)) {
            if ($actor !== 'root@root') public_api_json(['ok' => false, 'error' => 'root_only_role_change'], 403);
            $fields['role'] = max(0, min(2, (int)$body['role']));
        }
        if (array_key_exists('admin_capabilities', $body)) {
            if ($actor !== 'root@root') public_api_json(['ok' => false, 'error' => 'root_only_capability_change'], 403);
            require_once dirname(__DIR__, 2) . '/lib/admin_security.php';
            $fields['admin_capabilities'] = adminParseCapabilities($body['admin_capabilities']);
        }
        if (array_key_exists('verified', $body)) $fields['verified'] = !empty($body['verified']);
        if (array_key_exists('name', $body)) $fields['name'] = trim((string)$body['name']);
        if ($fields === []) public_api_json(['ok' => false, 'error' => 'no_fields'], 400);
        upsertUser($emailParam, $fields);
        if (function_exists('adminAuditLog')) adminAuditLog('api_user_update', $emailParam, 'ok', array_keys($fields));
        $u = getUserByEmail($emailParam) ?: [];
        public_api_json(['ok' => true, 'user' => [
            'email' => $emailParam,
            'name' => $u['name'] ?? '',
            'role' => (int)($u['role'] ?? 0),
            'verified' => !empty($u['verified']),
            'admin_capabilities' => adminParseCapabilities($u['admin_capabilities'] ?? ''),
        ]]);
    }
    if ($method === 'DELETE') {
        if ($emailParam === 'root@root') public_api_json(['ok' => false, 'error' => 'cannot_delete_root'], 403);
        if (!adminHasCapability('users')) public_api_json(['ok' => false, 'error' => 'capability_required'], 403);
        deleteUser($emailParam);
        adminAuditLog('api_user_delete', $emailParam, 'ok');
        public_api_json(['ok' => true]);
    }
}
public_api_json(['ok' => false, 'error' => 'unknown_admin_action'], 404);
