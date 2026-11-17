<?php
/**
 * Family location permissions (task 23).
 *
 * Membership in a family group never reveals a location. A grant is a separate,
 * explicit consent from the person whose location is shared. Grants carry a
 * scope (current/recent/range/places), precision, expiry and a generation that
 * increments on every revocation so cached or in-flight readers can be told to
 * stop. Everything is deny-by-default and fails closed.
 */
require_once __DIR__ . '/geolocation_domain.php';

final class StepsGeolocationPermissionsError extends RuntimeException
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

final class StepsGeolocationPermissions
{
    public const LEVELS = ['none', 'current', 'recent', 'range', 'places'];
    public const CURRENT_WINDOW_S = 900;      // "current location" and the last 15 minutes
    public const RECENT_WINDOW_S = 604800;    // "recent history": last 7 days
    public const MAX_GRANTS = 50;

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function audit(PDO $pdo, string $owner, string $actor, string $event, array $context): void
    {
        $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)')
            ->execute([':id' => self::uuid(), ':owner' => $owner, ':actor' => $actor, ':event' => $event, ':context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /** @return list<array<string,mixed>> */
    public static function grants(PDO $pdo, string $owner): array
    {
        $stmt = $pdo->prepare(
            "SELECT g.id, g.owner_email, g.viewer_email, g.family_group_id, g.access_level,
                    g.starts_at, g.ends_at, g.range_from, g.range_to, g.precision_m, g.reason,
                    g.consent_at, g.paused_at, g.revoked_at, g.generation, g.last_used_at,
                    g.created_at, g.updated_at, fg.name AS family_group_name
               FROM geo_location_grants g
               LEFT JOIN geo_family_groups fg ON fg.id = g.family_group_id
              WHERE g.owner_email = :owner
           ORDER BY (g.revoked_at IS NULL) DESC, g.updated_at DESC"
        );
        $stmt->execute([':owner' => $owner]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['active'] = $row['revoked_at'] === null && $row['paused_at'] === null;
            $row['precision_m'] = (float)$row['precision_m'];
            $row['generation'] = (int)$row['generation'];
        }
        unset($row);
        return $rows;
    }

    /** Locations other people have granted to this viewer. */
    public static function incoming(PDO $pdo, string $viewer): array
    {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT ON (g.owner_email) g.owner_email, g.access_level, g.precision_m, fg.name AS family_group_name
               FROM geo_location_grants g
               LEFT JOIN geo_family_groups fg ON fg.id = g.family_group_id
              WHERE g.viewer_email = :viewer
                AND g.revoked_at IS NULL
                AND g.paused_at IS NULL
                AND g.access_level <> 'none'
                AND (g.ends_at IS NULL OR g.ends_at > now())
           ORDER BY g.owner_email, g.updated_at DESC"
        );
        $stmt->execute([':viewer' => $viewer]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array{viewer_email?:string,access_level?:string,family_group_id?:string,starts_at?:string,ends_at?:string,range_from?:string,range_to?:string,precision_m?:mixed,reason?:string,consent?:mixed} $input
     * @return array<string,mixed>
     */
    public static function grant(PDO $pdo, string $owner, array $input): array
    {
        $viewer = self::normalizeEmail((string)($input['viewer_email'] ?? ''));
        if (!filter_var($viewer, FILTER_VALIDATE_EMAIL) || strlen($viewer) > 254) throw new StepsGeolocationPermissionsError('invalid_email');
        if ($viewer === $owner) throw new StepsGeolocationPermissionsError('cannot_share_with_self');
        if (empty($input['consent'])) throw new StepsGeolocationPermissionsError('consent_required', 409);

        $level = strtolower(trim((string)($input['access_level'] ?? 'none')));
        if (!in_array($level, self::LEVELS, true) || $level === 'none') throw new StepsGeolocationPermissionsError('invalid_access_level');

        $precision = $input['precision_m'] ?? 0;
        if (!is_numeric($precision) || (float)$precision < 0 || (float)$precision > 5000) throw new StepsGeolocationPermissionsError('invalid_precision');
        $precision = round((float)$precision, 1);

        $parse = static function (?string $value): ?string {
            $value = $value !== null ? trim($value) : '';
            if ($value === '') return null;
            $ts = strtotime($value);
            if ($ts === false) throw new StepsGeolocationPermissionsError('invalid_date');
            return gmdate('c', $ts);
        };
        $startsAt = $parse($input['starts_at'] ?? null);
        $endsAt = $parse($input['ends_at'] ?? null);
        $rangeFrom = $parse($input['range_from'] ?? null);
        $rangeTo = $parse($input['range_to'] ?? null);
        if ($endsAt !== null && strtotime($endsAt) <= time()) throw new StepsGeolocationPermissionsError('expiry_in_past');
        if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) >= strtotime($endsAt)) throw new StepsGeolocationPermissionsError('invalid_interval');
        if ($rangeFrom !== null && $rangeTo !== null && strtotime($rangeFrom) >= strtotime($rangeTo)) throw new StepsGeolocationPermissionsError('invalid_range');
        if ($level === 'range' && ($rangeFrom === null || $rangeTo === null)) throw new StepsGeolocationPermissionsError('range_required');

        $groupId = trim((string)($input['family_group_id'] ?? ''));
        if ($groupId !== '') {
            if (!StepsGeolocationDomain::isUuid($groupId)) throw new StepsGeolocationPermissionsError('invalid_group');
            $member = $pdo->prepare("SELECT 1 FROM geo_family_members WHERE group_id = :group AND user_email = :viewer AND status = 'active'");
            $member->execute([':group' => $groupId, ':viewer' => $viewer]);
            if (!$member->fetchColumn()) throw new StepsGeolocationPermissionsError('viewer_not_in_group', 409);
            $ownerMember = $pdo->prepare("SELECT role FROM geo_family_members WHERE group_id = :group AND user_email = :owner AND status = 'active'");
            $ownerMember->execute([':group' => $groupId, ':owner' => $owner]);
            $role = $ownerMember->fetchColumn();
            if (!in_array($role, ['owner', 'admin'], true)) throw new StepsGeolocationPermissionsError('group_admin_required', 403);
        } else {
            $groupId = '';
        }

        $reason = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)($input['reason'] ?? '')) ?? '';
        $reason = function_exists('mb_substr') ? mb_substr(trim($reason), 0, 160) : substr(trim($reason), 0, 160);

        $pdo->beginTransaction();
        try {
            $count = $pdo->prepare('SELECT COUNT(*) FROM geo_location_grants WHERE owner_email = :owner AND revoked_at IS NULL');
            $count->execute([':owner' => $owner]);
            if ((int)$count->fetchColumn() >= self::MAX_GRANTS) throw new StepsGeolocationPermissionsError('grant_limit_reached', 409);

            // One active grant per viewer keeps the effective scope unambiguous.
            $previous = $pdo->prepare('SELECT generation FROM geo_location_grants WHERE owner_email = :owner AND viewer_email = :viewer ORDER BY updated_at DESC LIMIT 1');
            $previous->execute([':owner' => $owner, ':viewer' => $viewer]);
            $generation = ((int)($previous->fetchColumn() ?: 0)) + 1;

            $pdo->prepare(
                'UPDATE geo_location_grants SET revoked_at = now(), generation = generation + 1, updated_at = now()
                  WHERE owner_email = :owner AND viewer_email = :viewer AND revoked_at IS NULL'
            )->execute([':owner' => $owner, ':viewer' => $viewer]);

            $id = self::uuid();
            $pdo->prepare(
                "INSERT INTO geo_location_grants(id, owner_email, viewer_email, family_group_id, access_level, starts_at, ends_at,
                                                 range_from, range_to, precision_m, reason, consent_at, generation)
                 VALUES(:id, :owner, :viewer, :group, :level, :starts, :ends, :range_from, :range_to, :precision, :reason, now(), :generation)"
            )->execute([
                ':id' => $id,
                ':owner' => $owner,
                ':viewer' => $viewer,
                ':group' => $groupId !== '' ? $groupId : null,
                ':level' => $level,
                ':starts' => $startsAt,
                ':ends' => $endsAt,
                ':range_from' => $rangeFrom,
                ':range_to' => $rangeTo,
                ':precision' => $precision,
                ':reason' => $reason,
                ':generation' => $generation,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        self::audit($pdo, $owner, $owner, 'permissions.granted', [
            'grant_id' => $id,
            'viewer' => $viewer,
            'access_level' => $level,
            'ends_at' => $endsAt,
            'precision_m' => $precision,
        ]);
        self::notify($owner, $viewer, 'granted', $level, $endsAt);
        self::invalidate($pdo, $owner);
        return self::grantRow($pdo, $owner, $id);
    }

    private static function grantRow(PDO $pdo, string $owner, string $id): array
    {
        $stmt = $pdo->prepare('SELECT * FROM geo_location_grants WHERE owner_email = :owner AND id = :id');
        $stmt->execute([':owner' => $owner, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public static function revoke(PDO $pdo, string $owner, string $id): void
    {
        self::requireGrant($pdo, $owner, $id);
        $pdo->prepare('UPDATE geo_location_grants SET revoked_at = now(), paused_at = NULL, generation = generation + 1, updated_at = now() WHERE owner_email = :owner AND id = :id')
            ->execute([':owner' => $owner, ':id' => $id]);
        $grant = self::grantRow($pdo, $owner, $id);
        self::audit($pdo, $owner, $owner, 'permissions.revoked', ['grant_id' => $id, 'viewer' => (string)($grant['viewer_email'] ?? ''), 'generation' => (int)($grant['generation'] ?? 0)]);
        self::notify($owner, (string)($grant['viewer_email'] ?? ''), 'revoked', (string)($grant['access_level'] ?? ''), null);
        self::invalidate($pdo, $owner);
    }

    public static function pause(PDO $pdo, string $owner, string $id, bool $paused): void
    {
        self::requireGrant($pdo, $owner, $id);
        $pdo->prepare('UPDATE geo_location_grants SET paused_at = :paused, generation = generation + 1, updated_at = now() WHERE owner_email = :owner AND id = :id')
            ->execute([':paused' => $paused ? gmdate('c') : null, ':owner' => $owner, ':id' => $id]);
        $grant = self::grantRow($pdo, $owner, $id);
        self::audit($pdo, $owner, $owner, $paused ? 'permissions.paused' : 'permissions.resumed', ['grant_id' => $id, 'viewer' => (string)($grant['viewer_email'] ?? '')]);
        self::invalidate($pdo, $owner);
    }

    private static function requireGrant(PDO $pdo, string $owner, string $id): void
    {
        if (!StepsGeolocationDomain::isUuid($id)) throw new StepsGeolocationPermissionsError('invalid_grant');
        $stmt = $pdo->prepare('SELECT 1 FROM geo_location_grants WHERE owner_email = :owner AND id = :id');
        $stmt->execute([':owner' => $owner, ':id' => $id]);
        if (!$stmt->fetchColumn()) throw new StepsGeolocationPermissionsError('grant_not_found', 404);
    }

    private static function notify(string $owner, string $viewer, string $change, string $level, ?string $endsAt): void
    {
        if (!function_exists('app_notify')) return;
        $label = $change === 'granted' ? 'shares location with' : ($change === 'revoked' ? 'stopped sharing location with' : 'updated sharing with');
        $message = $owner . ' ' . $label . ' ' . $viewer . ($endsAt !== null ? ' until ' . substr($endsAt, 0, 10) : '');
        app_notify($owner, 'steps', 'info', 'Location sharing updated', $message, 'family_permission_changed', ['viewer' => $viewer, 'change' => $change, 'access_level' => $level]);
        if ($viewer !== '' && $viewer !== $owner) {
            app_notify($viewer, 'steps', 'info', 'Location sharing updated', $message, 'family_permission_changed', ['owner' => $owner, 'change' => $change, 'access_level' => $level]);
        }
        if (class_exists('StepsGeolocationNotifications')) {
            StepsGeolocationNotifications::enqueuePublic($owner, 'family_permission_changed', 'Location sharing updated', $message, ['viewer' => $viewer, 'change' => $change]);
            if ($viewer !== '' && $viewer !== $owner) {
                StepsGeolocationNotifications::enqueuePublic($viewer, 'family_permission_changed', 'Location sharing updated', $message, ['owner' => $owner, 'change' => $change]);
            }
        }
    }

    private static function invalidate(PDO $pdo, string $owner): void
    {
        try {
            if (class_exists('StepsGeolocationStatistics')) StepsGeolocationStatistics::invalidate($pdo, $owner);
        } catch (Throwable $e) {
            error_log('permissions invalidate: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the effective scope for a viewer. Returns a restrictive scope or a
     * denial; it never widens what the grant allows.
     *
     * @return array{owner:?string,error:?string,grant_id:?string,access_level:string,scope_from:?string,scope_to:?string,precision_m:float,generation:int,places_only:bool}
     */
    public static function authorize(PDO $pdo, string $viewer, ?string $target, ?string $from = null, ?string $to = null): array
    {
        $viewer = self::normalizeEmail($viewer);
        $target = $target !== null ? self::normalizeEmail($target) : '';
        $deny = static fn(string $code) => [
            'owner' => null, 'error' => $code, 'grant_id' => null, 'access_level' => 'none',
            'scope_from' => null, 'scope_to' => null, 'precision_m' => 0.0, 'generation' => 0, 'places_only' => false,
        ];
        if ($target === '' || $target === $viewer) {
            return [
                'owner' => $viewer, 'error' => null, 'grant_id' => null, 'access_level' => 'self',
                'scope_from' => $from, 'scope_to' => $to, 'precision_m' => 0.0, 'generation' => 0, 'places_only' => false,
            ];
        }
        if (!filter_var($viewer, FILTER_VALIDATE_EMAIL) || !filter_var($target, FILTER_VALIDATE_EMAIL)) return $deny('family_access_denied');

        $stmt = $pdo->prepare(
            "SELECT g.* FROM geo_location_grants g
              WHERE g.owner_email = :target AND g.viewer_email = :viewer
                AND g.revoked_at IS NULL AND g.paused_at IS NULL
                AND g.access_level <> 'none'
                AND (g.starts_at IS NULL OR g.starts_at <= now())
                AND (g.ends_at IS NULL OR g.ends_at > now())
                AND (g.family_group_id IS NULL OR EXISTS (
                        SELECT 1 FROM geo_family_members m
                         WHERE m.group_id = g.family_group_id AND m.user_email = :viewer AND m.status = 'active'))
           ORDER BY g.updated_at DESC LIMIT 1"
        );
        $stmt->execute([':target' => $target, ':viewer' => $viewer]);
        $grant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$grant) return $deny('family_access_denied');

        $now = time();
        $requestedFrom = $from !== null && strtotime($from) !== false ? strtotime($from) : $now - 86400;
        $requestedTo = $to !== null && strtotime($to) !== false ? strtotime($to) : $now;
        $level = (string)$grant['access_level'];
        $scopeFrom = $requestedFrom;
        $scopeTo = $requestedTo;
        $placesOnly = false;

        if ($level === 'current') {
            $earliest = $now - self::CURRENT_WINDOW_S;
            if ($requestedTo < $earliest) return $deny('historical_access_denied');
            $scopeFrom = max($requestedFrom, $earliest);
        } elseif ($level === 'recent') {
            $earliest = $now - self::RECENT_WINDOW_S;
            if ($requestedTo < $earliest) return $deny('historical_access_denied');
            $scopeFrom = max($requestedFrom, $earliest);
        } elseif ($level === 'range') {
            $rangeFrom = $grant['range_from'] !== null ? strtotime((string)$grant['range_from']) : null;
            $rangeTo = $grant['range_to'] !== null ? strtotime((string)$grant['range_to']) : null;
            if ($rangeFrom === null || $rangeTo === null) return $deny('family_access_denied');
            if ($requestedFrom < $rangeFrom - 1 || $requestedTo > $rangeTo + 1) return $deny('outside_granted_range');
            $scopeFrom = max($requestedFrom, $rangeFrom);
            $scopeTo = min($requestedTo, $rangeTo);
        } elseif ($level === 'places') {
            $placesOnly = true;
            $scopeFrom = $requestedFrom;
            $scopeTo = $requestedTo;
        } else {
            return $deny('family_access_denied');
        }

        try {
            $pdo->prepare('UPDATE geo_location_grants SET last_used_at = now() WHERE id = :id AND (last_used_at IS NULL OR last_used_at < now() - interval \'5 minutes\')')
                ->execute([':id' => $grant['id']]);
        } catch (Throwable $e) {
            error_log('permissions last_used: ' . $e->getMessage());
        }

        return [
            'owner' => $target,
            'error' => null,
            'grant_id' => (string)$grant['id'],
            'access_level' => $level,
            'scope_from' => gmdate('c', $scopeFrom),
            'scope_to' => gmdate('c', $scopeTo),
            'precision_m' => (float)($grant['precision_m'] ?? 0),
            'generation' => (int)($grant['generation'] ?? 1),
            'places_only' => $placesOnly,
        ];
    }

    /** Reduce coordinate precision for grants that request it. */
    public static function precisionDecimals(float $meters): int
    {
        if ($meters <= 0) return 0;
        $degrees = $meters / 111320.0;
        $decimals = (int)ceil(-log10($degrees));
        return max(2, min(7, $decimals));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function applyPrecision(array $payload, float $meters): array
    {
        $decimals = self::precisionDecimals($meters);
        if ($decimals === 0 || !isset($payload['features']) || !is_array($payload['features'])) return $payload;
        $round = static function (&$coords) use (&$round, $decimals): void {
            if (!is_array($coords)) return;
            if (isset($coords[0]) && is_numeric($coords[0])) {
                $coords[0] = round((float)$coords[0], $decimals);
                if (isset($coords[1]) && is_numeric($coords[1])) $coords[1] = round((float)$coords[1], $decimals);
                return;
            }
            foreach ($coords as &$child) $round($child);
        };
        foreach ($payload['features'] as &$feature) {
            if (isset($feature['geometry']['coordinates'])) $round($feature['geometry']['coordinates']);
        }
        unset($feature);
        $payload['meta']['precision_m'] = $meters;
        return $payload;
    }
}
