<?php
/**
 * Reversible, owner-scoped location corrections (task 34).
 *
 * Corrections never move or delete raw coordinates. They mark a bounded scope
 * as excluded from derived views or as do-not-match, keep the previous quality
 * state for a safe undo, write a redacted audit event, and enqueue derived-data
 * recomputation. Coordinates and notes are never written to ordinary logs.
 */
require_once __DIR__ . '/geolocation_quality.php';
require_once __DIR__ . '/geolocation_matching.php';

final class StepsGeolocationCorrectionError extends RuntimeException
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

final class StepsGeolocationCorrections
{
    public const MAX_POINTS = 5000;
    public const MAX_RANGE_DAYS = 3660;

    public const EFFECTS = [
        'exclude_display',
        'exclude_analytics',
        'exclude_export',
        'exclude_family',
        'exclude_geofences',
        'exclude_render',
        'raw_only',
        'do_not_match',
        'known_gap',
    ];

    public static function listForOwner(PDO $pdo, string $owner, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $pdo->prepare("SELECT id, scope_type, scope_json, effects_json, reason, note, state, created_at, revoked_at FROM geo_quality_corrections WHERE user_email = :owner ORDER BY created_at DESC LIMIT $limit");
        $stmt->execute([':owner' => $owner]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['scope_json'] = json_decode((string)$row['scope_json'], true) ?: [];
            $row['effects_json'] = json_decode((string)$row['effects_json'], true) ?: [];
            $row['point_count'] = (int)($row['scope_json']['point_count'] ?? count($row['scope_json']['point_ids'] ?? []));
            unset($row['scope_json']['point_ids']);
            $rows[] = $row;
        }
        return $rows;
    }

    public static function create(PDO $pdo, string $owner, array $input): array
    {
        $scopeType = strtolower(trim((string)($input['scope_type'] ?? '')));
        if (!in_array($scopeType, ['points', 'range', 'trip', 'import'], true)) throw new StepsGeolocationCorrectionError('invalid_scope');
        $effects = array_values(array_unique(array_filter(array_map('strval', (array)($input['effects'] ?? [])))));
        foreach ($effects as $effect) if (!in_array($effect, self::EFFECTS, true)) throw new StepsGeolocationCorrectionError('invalid_effect');
        if ($effects === []) throw new StepsGeolocationCorrectionError('effects_required');
        $reason = mb_substr(trim((string)($input['reason'] ?? 'user_correction')), 0, 200);
        $note = mb_substr(trim((string)($input['note'] ?? '')), 0, 500);
        [$ids, $scope] = self::resolveScope($pdo, $owner, $scopeType, $input);
        if ($ids === []) throw new StepsGeolocationCorrectionError('scope_empty');
        if (count($ids) > self::MAX_POINTS) throw new StepsGeolocationCorrectionError('scope_too_large');
        $id = StepsGeolocationQuality::uuid();
        $exclude = false;
        foreach ($effects as $effect) if (str_starts_with($effect, 'exclude_')) $exclude = true;
        $rows = self::loadStates($pdo, $owner, $ids);
        if ($rows === []) throw new StepsGeolocationCorrectionError('scope_empty');
        $previous = [];
        foreach ($rows as $row) $previous[(string)$row['id']] = (string)$row['quality_state'];
        $scope['point_ids'] = array_keys($previous);
        $scope['point_count'] = count($previous);
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare("INSERT INTO geo_quality_corrections(id, user_email, scope_type, scope_json, effects_json, reason, note, previous_states_json) VALUES(:id, :owner, :scope_type, :scope, :effects, :reason, :note, :previous)");
            $insert->execute([
                ':id' => $id,
                ':owner' => $owner,
                ':scope_type' => $scopeType,
                ':scope' => json_encode($scope, JSON_UNESCAPED_SLASHES),
                ':effects' => json_encode($effects, JSON_UNESCAPED_SLASHES),
                ':reason' => $reason,
                ':note' => $note,
                ':previous' => json_encode($previous, JSON_UNESCAPED_SLASHES),
            ]);
            $correction = json_encode(['id' => $id, 'effects' => $effects, 'reason' => $reason], JSON_UNESCAPED_SLASHES);
            $stateSql = $exclude ? ", quality_state = 'excluded_by_user'" : '';
            $update = $pdo->prepare("UPDATE geo_location_points SET correction_json = COALESCE(correction_json, '{}'::jsonb) || jsonb_build_object('last_correction', CAST(:correction AS jsonb))$stateSql WHERE user_email = :owner AND id = ANY(CAST(:ids AS uuid[]))");
            $update->execute([':correction' => $correction, ':owner' => $owner, ':ids' => self::uuidArray(array_keys($previous))]);
            self::audit($pdo, $owner, 'location_quality_correction', ['correction_id' => $id, 'scope_type' => $scopeType, 'effects' => $effects, 'point_count' => count($previous)]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        StepsGeolocationQuality::enqueue($pdo, $owner);
        StepsGeolocationMatching::enqueue($pdo, $owner);
        return self::get($pdo, $owner, $id);
    }

    public static function revoke(PDO $pdo, string $owner, string $id): array
    {
        $correction = self::get($pdo, $owner, $id);
        if (($correction['state'] ?? '') !== 'active') throw new StepsGeolocationCorrectionError('correction_not_active', 409);
        $previous = $correction['previous_states_json'] ?? [];
        $ids = array_keys($previous);
        $pdo->beginTransaction();
        try {
            if ($ids !== []) {
                $restore = $pdo->prepare("UPDATE geo_location_points SET quality_state = :previous, correction_json = COALESCE(correction_json, '{}'::jsonb) - 'last_correction' WHERE user_email = :owner AND id = :id AND quality_state = 'excluded_by_user' AND correction_json->'last_correction'->>'id' = :correction");
                foreach ($previous as $pointId => $state) {
                    $restore->execute([':previous' => (string)$state, ':owner' => $owner, ':id' => (string)$pointId, ':correction' => $id]);
                }
            }
            $pdo->prepare("UPDATE geo_quality_corrections SET state = 'revoked', revoked_at = now() WHERE id = :id AND user_email = :owner")
                ->execute([':id' => $id, ':owner' => $owner]);
            self::audit($pdo, $owner, 'location_quality_correction_undo', ['correction_id' => $id, 'point_count' => count($ids)]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        StepsGeolocationQuality::enqueue($pdo, $owner);
        StepsGeolocationMatching::enqueue($pdo, $owner);
        return self::get($pdo, $owner, $id);
    }

    public static function get(PDO $pdo, string $owner, string $id): array
    {
        if (!self::isUuid($id)) throw new StepsGeolocationCorrectionError('invalid_correction');
        $stmt = $pdo->prepare('SELECT * FROM geo_quality_corrections WHERE id = :id AND user_email = :owner');
        $stmt->execute([':id' => $id, ':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new StepsGeolocationCorrectionError('correction_not_found', 404);
        foreach (['scope_json', 'effects_json', 'previous_states_json'] as $field) $row[$field] = json_decode((string)$row[$field], true) ?: [];
        $row['point_count'] = (int)($row['scope_json']['point_count'] ?? 0);
        unset($row['scope_json']['point_ids']);
        return $row;
    }

    private static function resolveScope(PDO $pdo, string $owner, string $scopeType, array $input): array
    {
        if ($scopeType === 'points') {
            $ids = [];
            foreach ((array)($input['point_ids'] ?? []) as $id) {
                $id = trim((string)$id);
                if (!self::isUuid($id)) throw new StepsGeolocationCorrectionError('invalid_point');
                $ids[$id] = true;
            }
            return [array_keys($ids), ['type' => 'points']];
        }
        if ($scopeType === 'trip') {
            $tripId = trim((string)($input['trip_id'] ?? ''));
            if (!self::isUuid($tripId)) throw new StepsGeolocationCorrectionError('invalid_trip');
            $stmt = $pdo->prepare('SELECT started_at, ended_at, override_json FROM geo_trips WHERE id = :id AND user_email = :owner AND deleted_at IS NULL');
            $stmt->execute([':id' => $tripId, ':owner' => $owner]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$trip) throw new StepsGeolocationCorrectionError('trip_not_found', 404);
            $override = json_decode((string)$trip['override_json'], true) ?: [];
            if (!empty($override['point_ids']) && is_array($override['point_ids'])) {
                $ids = array_values(array_filter(array_map('strval', $override['point_ids']), [self::class, 'isUuid']));
                return [$ids, ['type' => 'trip', 'trip_id' => $tripId]];
            }
            return [self::idsInRange($pdo, $owner, (string)$trip['started_at'], (string)$trip['ended_at']), ['type' => 'trip', 'trip_id' => $tripId]];
        }
        if ($scopeType === 'import') {
            $importId = trim((string)($input['import_id'] ?? ''));
            if (!self::isUuid($importId)) throw new StepsGeolocationCorrectionError('invalid_import');
            $stmt = $pdo->prepare("SELECT p.id FROM geo_import_points ip JOIN geo_import_files f ON f.id = ip.import_file_id JOIN geo_location_points p ON p.id = ip.point_id WHERE (f.id = :id OR f.import_id = :id) AND ip.user_email = :owner LIMIT " . (self::MAX_POINTS + 1));
            $stmt->execute([':id' => $importId, ':owner' => $owner]);
            $ids = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            return [$ids, ['type' => 'import', 'import_id' => $importId]];
        }
        $from = strtotime((string)($input['from'] ?? ''));
        $to = strtotime((string)($input['to'] ?? ''));
        if ($from === false || $to === false || $to <= $from) throw new StepsGeolocationCorrectionError('invalid_range');
        if ($to - $from > self::MAX_RANGE_DAYS * 86400) throw new StepsGeolocationCorrectionError('range_too_large');
        $scope = ['type' => 'range', 'from' => gmdate('c', $from), 'to' => gmdate('c', $to)];
        $deviceId = trim((string)($input['device_id'] ?? ''));
        if ($deviceId !== '') {
            if (!self::isUuid($deviceId)) throw new StepsGeolocationCorrectionError('invalid_device');
            $scope['device_id'] = $deviceId;
        }
        return [self::idsInRange($pdo, $owner, $scope['from'], $scope['to'], $deviceId), $scope];
    }

    private static function idsInRange(PDO $pdo, string $owner, string $from, string $to, string $deviceId = ''): array
    {
        $sql = 'SELECT id FROM geo_location_points WHERE user_email = :owner AND source_at BETWEEN :from AND :to';
        $params = [':owner' => $owner, ':from' => $from, ':to' => $to];
        if ($deviceId !== '') { $sql .= ' AND device_id = :device'; $params[':device'] = $deviceId; }
        $sql .= ' ORDER BY source_at ASC LIMIT ' . (self::MAX_POINTS + 1);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function loadStates(PDO $pdo, string $owner, array $ids): array
    {
        $stmt = $pdo->prepare('SELECT id, quality_state FROM geo_location_points WHERE user_email = :owner AND id = ANY(CAST(:ids AS uuid[]))');
        $stmt->execute([':owner' => $owner, ':ids' => self::uuidArray($ids)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function audit(PDO $pdo, string $owner, string $type, array $context): void
    {
        try {
            $stmt = $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :type, :context)');
            $stmt->execute([
                ':id' => StepsGeolocationQuality::uuid(),
                ':owner' => $owner,
                ':actor' => $owner,
                ':type' => $type,
                ':context' => json_encode($context, JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $e) {
            error_log('location correction audit failed: ' . $e->getMessage());
        }
    }

    private static function uuidArray(array $ids): string
    {
        return '{' . implode(',', array_map(static fn(string $id): string => '"' . str_replace('"', '', $id) . '"', $ids)) . '}';
    }

    public static function isUuid(string $value): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }
}
