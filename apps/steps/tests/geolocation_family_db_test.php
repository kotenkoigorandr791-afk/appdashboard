<?php
/**
 * Integration test for private family groups (task 22).
 *
 *   php apps/steps/tests/geolocation_family_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_family.php';

function geo_family_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function geo_family_expect(string $code, callable $fn): void
{
    try { $fn(); } catch (StepsGeolocationFamilyError $e) { geo_family_assert($e->errorCode === $code, 'expected ' . $code . ', got ' . $e->errorCode); return; }
    throw new RuntimeException('expected ' . $code);
}

$owner = 'qa-family-owner@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
$cleanup = static function (PDO $pdo, string $owner): void {
    $ids = $pdo->prepare('SELECT DISTINCT group_id AS id FROM geo_family_members WHERE user_email = :owner');
    $ids->execute([':owner' => $owner]);
    foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $pdo->prepare('DELETE FROM geo_location_grants WHERE family_group_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM geo_family_invitations WHERE group_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM geo_family_members WHERE group_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM geo_family_groups WHERE id = :id')->execute([':id' => $id]);
    }
    $pdo->prepare('DELETE FROM geo_audit_events WHERE user_email = :owner OR actor_email = :owner')->execute([':owner' => $owner]);
};
$cleanup($pdo, $owner);

try {
    $group = StepsGeolocationFamily::create($pdo, $owner, 'QA Family');
    $groupId = $group['id'];
    geo_family_assert($group['member_count'] === 1 && $group['role'] === 'owner', 'owner membership missing');

    $invite = StepsGeolocationFamily::invite($pdo, $owner, $groupId, 'qa-family-member1@example.test', 'member');
    geo_family_assert(strlen($invite['token']) === 64, 'invitation token missing');
    $hash = $pdo->prepare('SELECT token_hash FROM geo_family_invitations WHERE id = :id');
    $hash->execute([':id' => $invite['invitation']['id']]);
    geo_family_assert($hash->fetchColumn() === hash('sha256', $invite['token']), 'token is not stored as a hash');
    geo_family_assert(!str_contains((string)file_get_contents(__FILE__), $invite['token']), 'test leaked token');
    $joined = StepsGeolocationFamily::accept($pdo, 'qa-family-member1@example.test', $invite['token']);
    geo_family_assert($joined['member_count'] === 2, 'accepted member missing');
    geo_family_expect('invitation_used', fn() => StepsGeolocationFamily::accept($pdo, 'qa-family-member1@example.test', $invite['token']));
    $wrong = StepsGeolocationFamily::invite($pdo, $owner, $groupId, 'qa-family-wrong@example.test');
    geo_family_expect('invitation_email_mismatch', fn() => StepsGeolocationFamily::accept($pdo, 'qa-family-other@example.test', $wrong['token']));
    StepsGeolocationFamily::cancelInvitation($pdo, $owner, $wrong['invitation']['id']);

    // Fill to the hard limit: owner + 14 accepted members = 15.
    for ($i = 2; $i <= 14; $i++) {
        $email = 'qa-family-member' . $i . '@example.test';
        $i2 = StepsGeolocationFamily::invite($pdo, $owner, $groupId, $email);
        StepsGeolocationFamily::accept($pdo, $email, $i2['token']);
    }
    $full = StepsGeolocationFamily::get($pdo, $owner, $groupId);
    geo_family_assert($full['member_count'] === 15, 'group did not reach 15 members');
    geo_family_expect('group_full', fn() => StepsGeolocationFamily::invite($pdo, $owner, $groupId, 'qa-family-16@example.test'));

    // A pre-existing invitation still cannot bypass the limit at acceptance.
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO geo_family_invitations(id, group_id, inviter_email, invitee_email, role, token_hash, expires_at) VALUES(:id, :group, :owner, 'qa-family-16@example.test', 'member', :hash, now() + interval '1 hour')")
        ->execute([':id' => StepsGeolocationFamily::uuid(), ':group' => $groupId, ':owner' => $owner, ':hash' => hash('sha256', $token)]);
    geo_family_expect('group_full', fn() => StepsGeolocationFamily::accept($pdo, 'qa-family-16@example.test', $token));

    // Expired and cancelled invitations do not grant membership.
    $expiredToken = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO geo_family_invitations(id, group_id, inviter_email, invitee_email, role, token_hash, expires_at) VALUES(:id, :group, :owner, 'qa-family-expired@example.test', 'member', :hash, now() - interval '1 minute')")
        ->execute([':id' => StepsGeolocationFamily::uuid(), ':group' => $groupId, ':owner' => $owner, ':hash' => hash('sha256', $expiredToken)]);
    geo_family_expect('invitation_expired', fn() => StepsGeolocationFamily::accept($pdo, 'qa-family-expired@example.test', $expiredToken));

    // Remove a member and verify group-scoped access is revoked immediately.
    $member = 'qa-family-member14@example.test';
    $grantId = StepsGeolocationFamily::uuid();
    $pdo->prepare("INSERT INTO geo_location_grants(id, owner_email, viewer_email, family_group_id, access_level) VALUES(:id, :owner, :member, :group, 'current')")
        ->execute([':id' => $grantId, ':owner' => $owner, ':member' => $member, ':group' => $groupId]);
    StepsGeolocationFamily::removeMember($pdo, $owner, $groupId, $member);
    $grant = $pdo->prepare('SELECT revoked_at FROM geo_location_grants WHERE id = :id');
    $grant->execute([':id' => $grantId]);
    geo_family_assert($grant->fetchColumn() !== null, 'group grant survived member removal');
    geo_family_assert(StepsGeolocationFamily::role($pdo, $groupId, $member) === null, 'removed member still active');

    // Admin can invite members but cannot invite another admin.
    StepsGeolocationFamily::changeRole($pdo, $owner, $groupId, 'qa-family-member13@example.test', 'admin');
    geo_family_expect('owner_required', fn() => StepsGeolocationFamily::invite($pdo, 'qa-family-member13@example.test', $groupId, 'qa-family-admin2@example.test', 'admin'));

    // Basic role and transfer checks preserve audit history.
    StepsGeolocationFamily::transfer($pdo, $owner, $groupId, 'qa-family-member13@example.test');
    $adminGroups = StepsGeolocationFamily::groups($pdo, 'qa-family-member13@example.test');
    geo_family_assert(($adminGroups[0]['role'] ?? '') === 'owner', 'transfer did not grant ownership');
    $audit = $pdo->prepare("SELECT COUNT(*) FROM geo_audit_events WHERE event_type IN ('family.group_created','family.invited','family.joined','family.member_removed','family.ownership_transferred')");
    $audit->execute();
    geo_family_assert((int)$audit->fetchColumn() >= 5, 'family audit events missing');

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
}
