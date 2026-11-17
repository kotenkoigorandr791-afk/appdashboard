<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_family.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_family', 60, 60);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET') {
        public_api_json([
            'ok' => true,
            'groups' => StepsGeolocationFamily::groups($pdo, $owner),
            'pending_invitations' => StepsGeolocationFamily::pendingFor($pdo, $owner),
            'max_members' => StepsGeolocationFamily::MAX_MEMBERS,
            'one_or_more_groups' => true,
        ]);
    }
    if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
    $body = public_api_body();
    $action = strtolower(trim((string)($body['action'] ?? '')));
    $groupId = trim((string)($body['group_id'] ?? ''));
    $email = StepsGeolocationFamily::normalizeEmail((string)($body['email'] ?? ''));

    if ($action === 'create') {
        public_api_json(['ok' => true, 'group' => StepsGeolocationFamily::create($pdo, $owner, (string)($body['name'] ?? ''))], 201);
    }
    if ($action === 'accept') {
        public_api_json(['ok' => true, 'group' => StepsGeolocationFamily::accept($pdo, $owner, (string)($body['token'] ?? ''))]);
    }
    if ($action === 'leave') {
        if (!StepsGeolocationDomain::isUuid($groupId)) public_api_json(['ok' => false, 'error' => 'invalid_group'], 400);
        StepsGeolocationFamily::removeMember($pdo, $owner, $groupId, $owner, true);
        public_api_json(['ok' => true]);
    }
    if ($action === 'cancel_invitation') {
        $id = trim((string)($body['invitation_id'] ?? ''));
        if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_invitation'], 400);
        StepsGeolocationFamily::cancelInvitation($pdo, $owner, $id);
        public_api_json(['ok' => true]);
    }
    if (!StepsGeolocationDomain::isUuid($groupId)) public_api_json(['ok' => false, 'error' => 'invalid_group'], 400);
    if ($action === 'invite') {
        public_api_json(['ok' => true] + StepsGeolocationFamily::invite($pdo, $owner, $groupId, $email, (string)($body['role'] ?? 'member')), 201);
    }
    if ($action === 'remove_member') {
        StepsGeolocationFamily::removeMember($pdo, $owner, $groupId, $email);
        public_api_json(['ok' => true]);
    }
    if ($action === 'role') {
        StepsGeolocationFamily::changeRole($pdo, $owner, $groupId, $email, (string)($body['role'] ?? ''));
        public_api_json(['ok' => true]);
    }
    if ($action === 'rename') {
        public_api_json(['ok' => true, 'group' => StepsGeolocationFamily::rename($pdo, $owner, $groupId, (string)($body['name'] ?? ''))]);
    }
    if ($action === 'transfer') {
        StepsGeolocationFamily::transfer($pdo, $owner, $groupId, $email);
        public_api_json(['ok' => true]);
    }
    public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
} catch (StepsGeolocationFamilyError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
} catch (Throwable $e) {
    error_log('geolocation family api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'family_update_failed'], 500);
}
