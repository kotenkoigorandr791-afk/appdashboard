<?php
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$sub = strtolower((string)($parts[1] ?? ''));
$resourceId = trim((string)($parts[2] ?? ''));
$nested = strtolower((string)($parts[3] ?? ''));
$legacyAction = strtolower((string)($_GET['api'] ?? ''));
$auth = public_api_auth_user(true);
$email = (string)($auth['email'] ?? '');

if ($sub === '' && $method === 'GET' && $legacyAction === '') {
    public_api_json(['ok' => true, 'endpoints' => [
        'GET /public-api/smart_home',
        'GET /public-api/smart_home/devices',
        'GET /public-api/smart_home/groups',
        'GET /public-api/smart_home/plans',
        'GET /public-api/smart_home/stats?period=24',
        'POST|PATCH /public-api/smart_home/devices',
        'DELETE /public-api/smart_home/devices/{device_id}',
        'POST /public-api/smart_home/control',
        'POST /public-api/smart_home/plans',
        'POST /public-api/smart_home/groups',
        'POST /public-api/smart_home/groups/{group_id}/members',
    ]]);
}

$_GET['user'] = $email;

if ($sub === 'stats' && $method === 'GET') {
    $_GET['api'] = 'monitor_stats';
    $_POST['period'] = max(1, min(720, (int)($_GET['period'] ?? 24)));
    require APP_ROOT . '/apps/smart_home/core/app.php';
    exit;
}
if ($sub === 'control' && $method === 'POST') {
    $_GET['api'] = 'control';
    require APP_ROOT . '/apps/smart_home/core/app.php';
    exit;
}
if ($sub === 'plans' && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $_GET['api'] = 'save_floor_plan';
    require APP_ROOT . '/apps/smart_home/core/app.php';
    exit;
}
if ($sub === 'groups' && $method === 'POST') {
    $_GET['api'] = $nested === 'members' ? 'add_member' : 'create_group';
    if ($nested === 'members' && $resourceId !== '') $_GET['group_id'] = $resourceId;
    require APP_ROOT . '/apps/smart_home/core/app.php';
    exit;
}
if ($sub === 'devices' && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $_GET['api'] = 'save_device';
    require APP_ROOT . '/apps/smart_home/core/app.php';
    exit;
}
if ($sub === 'devices' && $method === 'DELETE' && $resourceId !== '') {
    $_GET['api'] = 'delete_device';
    $_POST['device_id'] = $resourceId;
    require APP_ROOT . '/apps/smart_home/core/app.php';
    exit;
}

if ($method === 'GET' && in_array($sub, ['', 'state', 'devices', 'groups', 'plans'], true) && ($sub !== '' || $legacyAction === 'state')) {
    ob_start();
    require APP_ROOT . '/apps/smart_home/core/app.php';
    ob_end_clean();
    $db = sh_db();
    $settings = sh_load_user_settings($db, $email);
    $ctx = sh_resolve_home_context($db, $email, $settings);
    $devices = sh_fetch_scoped_devices($db, $email, $ctx);
    $myDevices = sh_fetch_my_devices($db, $email);
    $groups = [];
    $stmt = $db->prepare('SELECT g.*, gm.role AS member_role FROM smarthome_groups g LEFT JOIN smarthome_group_members gm ON gm.group_id = g.id AND gm.member_email = :email WHERE g.user_email = :owner OR gm.member_email = :member ORDER BY g.name');
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':owner', $email, PDO::PARAM_STR);
    $stmt->bindValue(':member', $email, PDO::PARAM_STR);
    $seen = [];
    foreach ($stmt->execute()->fetchAll(PDO::FETCH_ASSOC) as $group) {
        if (isset($seen[$group['id']])) continue;
        $seen[$group['id']] = true;
        $groups[] = $group;
    }
    $groups = sh_enrich_groups_list($db, $groups, $email);
    $payload = [
        'ok' => true,
        'settings' => $settings,
        'devices' => $devices,
        'my_devices' => $myDevices,
        'groups' => $groups,
        'floor_plan' => sh_fetch_floor_plan($db, $ctx['floor_owner']),
        'permissions' => ['can_edit_map' => (bool)$ctx['can_edit_map'], 'can_edit_endpoints' => sh_can_edit_endpoints()],
    ];
    if ($sub === 'devices') $payload = ['ok' => true, 'devices' => $devices, 'my_devices' => $myDevices];
    if ($sub === 'groups') $payload = ['ok' => true, 'groups' => $groups];
    if ($sub === 'plans') $payload = ['ok' => true, 'floor_plan' => $payload['floor_plan'], 'can_edit' => $payload['permissions']['can_edit_map']];
    public_api_json($payload);
}

public_api_json(['ok' => false, 'error' => 'unknown_smart_home_endpoint'], 404);
