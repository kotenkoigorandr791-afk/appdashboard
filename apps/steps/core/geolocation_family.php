<?php
/**
 * Private family groups (task 22).
 *
 * Group roles only control group administration. Location sharing stays in
 * geo_location_grants (task 23), so membership alone never reveals a location.
 * All membership changes lock the group row and count active members in the
 * same transaction; invitations are opaque, hashed and expiring.
 */
require_once __DIR__ . '/geolocation_domain.php';

final class StepsGeolocationFamilyError extends RuntimeException
{
    public $errorCode;
    public $status;

    public function __construct(string $errorCode, int $status = 400)
    {
        $this->errorCode = $errorCode;
        $this->status = $status;
        parent::__construct($errorCode);
    }
}

final class StepsGeolocationFamily
{
    public const MAX_MEMBERS = 15;
    public const INVITE_TTL_HOURS = 168;
    public const MAX_GROUPS = 20;

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function normalizeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        return function_exists('mb_substr') ? mb_substr($name, 0, 80) : substr($name, 0, 80);
    }

    private static function audit(PDO $pdo, string $owner, string $actor, string $event, array $context): void
    {
        $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)')
            ->execute([':id' => self::uuid(), ':owner' => $owner, ':actor' => $actor, ':event' => $event, ':context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    private static function notify(string $email, string $title, string $message, string $event, array $context = []): void
    {
        if (function_exists('app_notify')) app_notify($email, 'steps', 'info', $title, $message, $event, $context);
    }

    private static function groupRow(PDO $pdo, string $groupId, bool $lock = false): array
    {
        $stmt = $pdo->prepare('SELECT id, owner_email, name, created_at, updated_at FROM geo_family_groups WHERE id = :id' . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([':id' => $groupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new StepsGeolocationFamilyError('group_not_found', 404);
        return $row;
    }

    public static function role(PDO $pdo, string $groupId, string $email): ?string
    {
        $stmt = $pdo->prepare("SELECT role FROM geo_family_members WHERE group_id = :group AND user_email = :email AND status = 'active'");
        $stmt->execute([':group' => $groupId, ':email' => $email]);
        $role = $stmt->fetchColumn();
        return $role === false ? null : (string)$role;
    }

    private static function requireRole(PDO $pdo, array $group, string $actor, array $allowed): string
    {
        $role = self::role($pdo, (string)$group['id'], $actor);
        if ($role === null) throw new StepsGeolocationFamilyError('group_access_denied', 403);
        if (!in_array($role, $allowed, true)) throw new StepsGeolocationFamilyError('group_admin_required', 403);
        return $role;
    }

    private static function activeCount(PDO $pdo, string $groupId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM geo_family_members WHERE group_id = :group AND status = 'active'");
        $stmt->execute([':group' => $groupId]);
        return (int)$stmt->fetchColumn();
    }

    /** @return array<string,mixed> */
    public static function create(PDO $pdo, string $owner, string $name): array
    {
        $name = self::normalizeName($name);
        if ($name === '') throw new StepsGeolocationFamilyError('invalid_name');
        $count = $pdo->prepare('SELECT COUNT(*) FROM geo_family_groups WHERE owner_email = :owner');
        $count->execute([':owner' => $owner]);
        if ((int)$count->fetchColumn() >= self::MAX_GROUPS) throw new StepsGeolocationFamilyError('group_limit_reached', 409);
        $id = self::uuid();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO geo_family_groups(id, owner_email, name) VALUES(:id, :owner, :name)')
                ->execute([':id' => $id, ':owner' => $owner, ':name' => $name]);
            $pdo->prepare("INSERT INTO geo_family_members(group_id, user_email, role, status, accepted_at) VALUES(:group, :owner, 'owner', 'active', now())")
                ->execute([':group' => $id, ':owner' => $owner]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, $owner, $owner, 'family.group_created', ['group_id' => $id, 'name' => $name]);
        return self::get($pdo, $owner, $id);
    }

    public static function rename(PDO $pdo, string $actor, string $groupId, string $name): array
    {
        $group = self::groupRow($pdo, $groupId, true);
        self::requireRole($pdo, $group, $actor, ['owner', 'admin']);
        $name = self::normalizeName($name);
        if ($name === '') throw new StepsGeolocationFamilyError('invalid_name');
        $pdo->prepare('UPDATE geo_family_groups SET name = :name, updated_at = now() WHERE id = :id')
            ->execute([':name' => $name, ':id' => $groupId]);
        self::audit($pdo, (string)$group['owner_email'], $actor, 'family.group_renamed', ['group_id' => $groupId, 'name' => $name]);
        return self::get($pdo, $actor, $groupId);
    }

    /**
     * @return array{invitation:array<string,mixed>,token:string}
     */
    public static function invite(PDO $pdo, string $actor, string $groupId, string $email, string $role = 'member'): array
    {
        $email = self::normalizeEmail($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) throw new StepsGeolocationFamilyError('invalid_email');
        if ($email === $actor) throw new StepsGeolocationFamilyError('cannot_invite_self');
        if (!in_array($role, ['admin', 'member'], true)) throw new StepsGeolocationFamilyError('invalid_role');
        $pdo->beginTransaction();
        try {
            $group = self::groupRow($pdo, $groupId, true);
            $actorRole = self::requireRole($pdo, $group, $actor, ['owner', 'admin']);
            if ($role === 'admin' && $actorRole !== 'owner') throw new StepsGeolocationFamilyError('owner_required', 403);
            if (self::role($pdo, $groupId, $email) !== null) throw new StepsGeolocationFamilyError('already_member', 409);
            $active = self::activeCount($pdo, $groupId);
            $pending = $pdo->prepare(
                "SELECT COUNT(*) FROM geo_family_invitations i
                  WHERE i.group_id = :group AND i.accepted_at IS NULL AND i.cancelled_at IS NULL AND i.expires_at > now()
                    AND NOT EXISTS (SELECT 1 FROM geo_family_members m WHERE m.group_id = i.group_id AND m.user_email = i.invitee_email AND m.status = 'active')"
            );
            $pending->execute([':group' => $groupId]);
            $existing = $pdo->prepare('SELECT id, expires_at FROM geo_family_invitations WHERE group_id = :group AND invitee_email = :email AND accepted_at IS NULL AND cancelled_at IS NULL ORDER BY created_at DESC LIMIT 1');
            $existing->execute([':group' => $groupId, ':email' => $email]);
            $pendingRow = $existing->fetch(PDO::FETCH_ASSOC);
            $pendingCount = (int)$pending->fetchColumn() - ($pendingRow ? 1 : 0);
            if ($active + $pendingCount >= self::MAX_MEMBERS) throw new StepsGeolocationFamilyError('group_full', 409);
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires = gmdate('c', time() + self::INVITE_TTL_HOURS * 3600);
            $invitationId = $pendingRow ? (string)$pendingRow['id'] : self::uuid();
            if ($pendingRow) {
                $pdo->prepare("UPDATE geo_family_invitations SET inviter_email = :inviter, role = :role, token_hash = :hash, expires_at = :expires, created_at = now() WHERE id = :id")
                    ->execute([':inviter' => $actor, ':role' => $role, ':hash' => $tokenHash, ':expires' => $expires, ':id' => $invitationId]);
            } else {
                $pdo->prepare('INSERT INTO geo_family_invitations(id, group_id, inviter_email, invitee_email, role, token_hash, expires_at) VALUES(:id, :group, :inviter, :email, :role, :hash, :expires)')
                    ->execute([':id' => $invitationId, ':group' => $groupId, ':inviter' => $actor, ':email' => $email, ':role' => $role, ':hash' => $tokenHash, ':expires' => $expires]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, (string)$group['owner_email'], $actor, 'family.invited', ['group_id' => $groupId, 'invitation_id' => $invitationId, 'role' => $role]);
        self::notify($email, 'Family invitation', 'You were invited to join "' . $group['name'] . '". Open Steps and enter the invitation token.', 'family_invitation', ['group_id' => $groupId]);
        return ['invitation' => self::invitationShape($pdo, $invitationId), 'token' => $token];
    }

    private static function invitationShape(PDO $pdo, string $id): array
    {
        $stmt = $pdo->prepare('SELECT id, group_id, inviter_email, invitee_email, role, expires_at, accepted_at, cancelled_at, created_at FROM geo_family_invitations WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public static function accept(PDO $pdo, string $actor, string $token): array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 200) throw new StepsGeolocationFamilyError('invalid_invitation');
        $hash = hash('sha256', $token);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM geo_family_invitations WHERE token_hash = :hash');
            $stmt->execute([':hash' => $hash]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invite) throw new StepsGeolocationFamilyError('invalid_invitation', 404);
            // Lock the group before the invitation, matching invite()'s order.
            $group = self::groupRow($pdo, (string)$invite['group_id'], true);
            $locked = $pdo->prepare('SELECT * FROM geo_family_invitations WHERE id = :id FOR UPDATE');
            $locked->execute([':id' => $invite['id']]);
            $invite = $locked->fetch(PDO::FETCH_ASSOC);
            if (!$invite) throw new StepsGeolocationFamilyError('invalid_invitation', 404);
            if ($invite['cancelled_at'] !== null || $invite['accepted_at'] !== null) throw new StepsGeolocationFamilyError('invitation_used', 409);
            if (strtotime((string)$invite['expires_at']) <= time()) throw new StepsGeolocationFamilyError('invitation_expired', 410);
            if (self::normalizeEmail((string)$invite['invitee_email']) !== $actor) throw new StepsGeolocationFamilyError('invitation_email_mismatch', 403);
            if (self::activeCount($pdo, (string)$group['id']) >= self::MAX_MEMBERS) throw new StepsGeolocationFamilyError('group_full', 409);
            $pdo->prepare(
                "INSERT INTO geo_family_members(group_id, user_email, role, status, invited_at, accepted_at, removed_at)
                 VALUES(:group, :actor, :role, 'active', :invited, now(), NULL)
                 ON CONFLICT (group_id, user_email) DO UPDATE SET role = EXCLUDED.role, status = 'active', invited_at = COALESCE(geo_family_members.invited_at, EXCLUDED.invited_at), accepted_at = now(), removed_at = NULL"
            )->execute([':group' => $group['id'], ':actor' => $actor, ':role' => $invite['role'], ':invited' => $invite['created_at']]);
            $pdo->prepare('UPDATE geo_family_invitations SET accepted_at = now(), accepted_by = :actor WHERE id = :id')
                ->execute([':actor' => $actor, ':id' => $invite['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, (string)$group['owner_email'], $actor, 'family.joined', ['group_id' => (string)$group['id'], 'invitation_id' => (string)$invite['id'], 'role' => (string)$invite['role']]);
        self::notify((string)$group['owner_email'], 'Family member joined', $actor . ' joined "' . $group['name'] . '".', 'family_member_joined', ['group_id' => (string)$group['id']]);
        return self::get($pdo, $actor, (string)$group['id']);
    }

    public static function cancelInvitation(PDO $pdo, string $actor, string $invitationId): void
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM geo_family_invitations WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $invitationId]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invite) throw new StepsGeolocationFamilyError('invitation_not_found', 404);
            $group = self::groupRow($pdo, (string)$invite['group_id'], true);
            self::requireRole($pdo, $group, $actor, ['owner', 'admin']);
            if ($invite['accepted_at'] !== null) throw new StepsGeolocationFamilyError('invitation_used', 409);
            $pdo->prepare('UPDATE geo_family_invitations SET cancelled_at = now() WHERE id = :id AND cancelled_at IS NULL')->execute([':id' => $invitationId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, (string)$group['owner_email'], $actor, 'family.invitation_cancelled', ['group_id' => (string)$group['id'], 'invitation_id' => $invitationId]);
    }

    public static function removeMember(PDO $pdo, string $actor, string $groupId, string $email, bool $leaving = false): void
    {
        $email = self::normalizeEmail($email);
        $pdo->beginTransaction();
        try {
            $group = self::groupRow($pdo, $groupId, true);
            $actorRole = $leaving ? self::requireRole($pdo, $group, $actor, ['admin', 'member']) : self::requireRole($pdo, $group, $actor, ['owner', 'admin']);
            if ($email === (string)$group['owner_email']) throw new StepsGeolocationFamilyError('owner_cannot_leave', 409);
            if ($leaving && $email !== $actor) throw new StepsGeolocationFamilyError('invalid_member');
            $targetRole = self::role($pdo, $groupId, $email);
            if ($targetRole === null) throw new StepsGeolocationFamilyError('member_not_found', 404);
            if ($targetRole === 'admin' && $actorRole !== 'owner') throw new StepsGeolocationFamilyError('owner_required', 403);
            $pdo->prepare("UPDATE geo_family_members SET status = :status, removed_at = now() WHERE group_id = :group AND user_email = :email")
                ->execute([':status' => $leaving ? 'left' : 'removed', ':group' => $groupId, ':email' => $email]);
            // Group-scoped location grants die with membership. Direct grants are
            // managed by task 23 and are not touched here.
            $pdo->prepare('UPDATE geo_location_grants SET revoked_at = now(), updated_at = now() WHERE owner_email = :owner AND viewer_email = :email AND family_group_id = :group AND revoked_at IS NULL')
                ->execute([':owner' => $group['owner_email'], ':email' => $email, ':group' => $groupId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, (string)$group['owner_email'], $actor, $leaving ? 'family.left' : 'family.member_removed', ['group_id' => $groupId, 'member' => $email]);
        self::notify((string)$group['owner_email'], $leaving ? 'Family member left' : 'Family member removed', $email . ($leaving ? ' left ' : ' was removed from ') . '"' . $group['name'] . '".', 'family_member_removed', ['group_id' => $groupId]);
    }

    public static function changeRole(PDO $pdo, string $actor, string $groupId, string $email, string $role): void
    {
        $email = self::normalizeEmail($email);
        if (!in_array($role, ['admin', 'member'], true)) throw new StepsGeolocationFamilyError('invalid_role');
        $pdo->beginTransaction();
        try {
            $group = self::groupRow($pdo, $groupId, true);
            self::requireRole($pdo, $group, $actor, ['owner']);
            if ($email === (string)$group['owner_email']) throw new StepsGeolocationFamilyError('owner_role_fixed', 409);
            if (self::role($pdo, $groupId, $email) === null) throw new StepsGeolocationFamilyError('member_not_found', 404);
            $pdo->prepare("UPDATE geo_family_members SET role = :role WHERE group_id = :group AND user_email = :email AND status = 'active'")
                ->execute([':role' => $role, ':group' => $groupId, ':email' => $email]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, (string)$group['owner_email'], $actor, 'family.role_changed', ['group_id' => $groupId, 'member' => $email, 'role' => $role]);
    }

    public static function transfer(PDO $pdo, string $actor, string $groupId, string $email): void
    {
        $email = self::normalizeEmail($email);
        $pdo->beginTransaction();
        try {
            $group = self::groupRow($pdo, $groupId, true);
            self::requireRole($pdo, $group, $actor, ['owner']);
            if (self::role($pdo, $groupId, $email) === null) throw new StepsGeolocationFamilyError('member_not_found', 404);
            if ($email === (string)$group['owner_email']) throw new StepsGeolocationFamilyError('already_owner');
            $pdo->prepare('UPDATE geo_family_groups SET owner_email = :email, updated_at = now() WHERE id = :id')->execute([':email' => $email, ':id' => $groupId]);
            $pdo->prepare("UPDATE geo_family_members SET role = 'admin' WHERE group_id = :group AND user_email = :actor")->execute([':group' => $groupId, ':actor' => $actor]);
            $pdo->prepare("UPDATE geo_family_members SET role = 'owner' WHERE group_id = :group AND user_email = :email")->execute([':group' => $groupId, ':email' => $email]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, $email, $actor, 'family.ownership_transferred', ['group_id' => $groupId, 'from' => $actor, 'to' => $email]);
        self::audit($pdo, $actor, $actor, 'family.ownership_transferred', ['group_id' => $groupId, 'from' => $actor, 'to' => $email]);
    }

    /** @return list<array<string,mixed>> */
    public static function groups(PDO $pdo, string $actor): array
    {
        $stmt = $pdo->prepare(
            "SELECT g.id, g.owner_email, g.name, g.created_at, g.updated_at, m.role
               FROM geo_family_groups g JOIN geo_family_members m ON m.group_id = g.id
              WHERE m.user_email = :actor AND m.status = 'active'
           ORDER BY g.name, g.created_at"
        );
        $stmt->execute([':actor' => $actor]);
        $groups = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $groups[] = self::shape($pdo, $actor, $row);
        return $groups;
    }

    public static function get(PDO $pdo, string $actor, string $groupId): array
    {
        $row = self::groupRow($pdo, $groupId);
        if (self::role($pdo, $groupId, $actor) === null) throw new StepsGeolocationFamilyError('group_access_denied', 403);
        $row['role'] = self::role($pdo, $groupId, $actor);
        return self::shape($pdo, $actor, $row);
    }

    private static function shape(PDO $pdo, string $actor, array $row): array
    {
        $memberStmt = $pdo->prepare('SELECT user_email, role, status, invited_at, accepted_at, removed_at FROM geo_family_members WHERE group_id = :group AND status = :status ORDER BY CASE role WHEN \'owner\' THEN 0 WHEN \'admin\' THEN 1 ELSE 2 END, user_email');
        $memberStmt->execute([':group' => $row['id'], ':status' => 'active']);
        $members = $memberStmt->fetchAll(PDO::FETCH_ASSOC);
        $pending = [];
        if (in_array((string)$row['role'], ['owner', 'admin'], true)) {
            $inviteStmt = $pdo->prepare("SELECT id, inviter_email, invitee_email, role, expires_at, created_at FROM geo_family_invitations WHERE group_id = :group AND accepted_at IS NULL AND cancelled_at IS NULL AND expires_at > now() ORDER BY created_at DESC");
            $inviteStmt->execute([':group' => $row['id']]);
            $pending = $inviteStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'owner_email' => (string)$row['owner_email'],
            'role' => (string)$row['role'],
            'member_count' => count($members),
            'max_members' => self::MAX_MEMBERS,
            'members' => $members,
            'invitations' => $pending,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** Invitations addressed to the current account, without token material. */
    public static function pendingFor(PDO $pdo, string $email): array
    {
        $stmt = $pdo->prepare(
            "SELECT i.id, i.group_id, g.name AS group_name, i.inviter_email, i.role, i.expires_at, i.created_at
               FROM geo_family_invitations i JOIN geo_family_groups g ON g.id = i.group_id
              WHERE i.invitee_email = :email AND i.accepted_at IS NULL AND i.cancelled_at IS NULL AND i.expires_at > now()
           ORDER BY i.created_at DESC"
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
