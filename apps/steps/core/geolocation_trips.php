<?php
/**
 * Continuous trip segmentation (task 11).
 *
 * Trips are built from temporal and spatial continuity only: speed, altitude
 * and bearing stay descriptive metadata and never classify a journey. A trip
 * is closed only by a long inactivity gap, an impossible discontinuity, a user
 * boundary or an explicit configured boundary, and every split records the
 * reason that started the next trip.
 */
require_once __DIR__ . '/geolocation_quality.php';
require_once __DIR__ . '/geolocation_domain.php';
require_once __DIR__ . '/geolocation_statistics.php';

final class StepsGeolocationTripError extends RuntimeException
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

final class StepsGeolocationTrips
{
    public const PROCESSOR_VERSION = 'trips-1';

    public static function thresholds(): array
    {
        $env = static function (string $key, float $default, float $min, float $max): float {
            $raw = getenv($key);
            if (!is_string($raw) || trim($raw) === '' || !is_numeric($raw)) return $default;
            return max($min, min($max, (float)$raw));
        };
        return [
            'max_gap_s' => $env('GEO_TRIP_MAX_GAP_S', 900.0, 60.0, 86400.0),
            'max_accuracy_m' => $env('GEO_TRIP_MAX_ACCURACY_M', 200.0, 5.0, 5000.0),
            'max_jump_m' => $env('GEO_TRIP_MAX_JUMP_M', 5000.0, 100.0, 100000.0),
            'jump_window_s' => $env('GEO_TRIP_JUMP_WINDOW_S', 60.0, 1.0, 3600.0),
            'max_speed_mps' => $env('GEO_TRIP_MAX_SPEED_MPS', 450.0, 10.0, 2000.0),
            'min_distance_m' => $env('GEO_TRIP_MIN_DISTANCE_M', 100.0, 0.0, 50000.0),
            'min_points' => $env('GEO_TRIP_MIN_POINTS', 3.0, 2.0, 100.0),
            'overlap_s' => $env('GEO_TRIP_OVERLAP_S', 120.0, 0.0, 3600.0),
        ];
    }

    /**
     * Merges per-device streams in UTC order. A second device's sample that
     * falls inside the overlap window of the device currently reporting is a
     * duplicate view of the same movement and is suppressed; outside the
     * window both streams are kept.
     */
    public static function mergeStreams(array $streams, ?array $thresholds = null): array
    {
        $t = $thresholds ?? self::thresholds();
        $flat = [];
        foreach ($streams as $deviceId => $points) {
            foreach ($points as $point) {
                $point['device_id'] = (string)($point['device_id'] ?? $deviceId);
                $point['_at'] = strtotime((string)($point['source_at'] ?? ''));
                if ($point['_at'] === false) continue;
                $flat[] = $point;
            }
        }
        usort($flat, static fn(array $a, array $b): int => [$a['_at'], (string)$a['device_id']] <=> [$b['_at'], (string)$b['device_id']]);
        $out = [];
        $activeDevice = null;
        $activeAt = 0;
        foreach ($flat as $point) {
            if ($activeDevice !== null && $point['device_id'] !== $activeDevice && abs($point['_at'] - $activeAt) <= $t['overlap_s']) {
                continue;
            }
            $out[] = $point;
            $activeDevice = $point['device_id'];
            $activeAt = $point['_at'];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $points usable samples in any order
     * @param list<int> $userBoundaryTimes epoch seconds of explicit user splits
     * @return array{trips:list<array<string,mixed>>,dropped:int,thresholds:array<string,float>}
     */
    public static function segment(array $points, ?array $thresholds = null, array $userBoundaryTimes = []): array
    {
        $t = $thresholds ?? self::thresholds();
        $usable = [];
        foreach ($points as $point) {
            if (($point['quality_state'] ?? 'accepted') === 'rejected') continue;
            $at = strtotime((string)($point['source_at'] ?? ''));
            if ($at === false) continue;
            $accuracy = $point['accuracy_m'] ?? null;
            if ($accuracy !== null && is_numeric($accuracy) && (float)$accuracy > $t['max_accuracy_m']) continue;
            $point['_at'] = $at;
            $usable[] = $point;
        }
        usort($usable, static fn(array $a, array $b): int => [$a['_at'], (string)($a['id'] ?? '')] <=> [$b['_at'], (string)($b['id'] ?? '')]);

        $trips = [];
        $dropped = 0;
        $current = null;
        $previous = null;
        $startReason = 'first_point';

        $finish = static function (?array $trip) use ($t, &$trips, &$dropped): void {
            if ($trip === null) return;
            $count = count($trip['point_ids']);
            if ($count < (int)$t['min_points'] || $trip['distance_m'] < $t['min_distance_m']) {
                $dropped++;
                return;
            }
            $trip['point_count'] = $count;
            $trips[] = $trip;
        };
        $open = static fn(array $point, string $reason): array => [
            'started_at' => (string)$point['source_at'],
            'ended_at' => (string)$point['source_at'],
            'distance_m' => 0.0,
            'point_ids' => [(string)$point['id']],
            'boundary_reason' => $reason,
            'device_ids' => [(string)$point['device_id']],
        ];

        foreach ($usable as $point) {
            if ($previous !== null && $point['_at'] === $previous['_at']) continue;
            if ($current === null) {
                $current = $open($point, $startReason);
                $previous = $point;
                continue;
            }
            $gap = $point['_at'] - $previous['_at'];
            $reason = null;
            if (in_array($point['_at'], $userBoundaryTimes, true)) {
                $reason = 'user_boundary';
            } elseif ($gap > $t['max_gap_s']) {
                $reason = 'long_inactivity';
            } elseif ($gap > 0 && $gap <= $t['jump_window_s']) {
                $distance = StepsGeolocationQuality::haversineMeters(
                    (float)$previous['latitude'], (float)$previous['longitude'],
                    (float)$point['latitude'], (float)$point['longitude']
                );
                if ($distance > $t['max_jump_m'] && ($distance / $gap) > $t['max_speed_mps']) {
                    $reason = 'impossible_discontinuity';
                }
            }
            if ($reason !== null) {
                $finish($current);
                $current = $open($point, $reason);
                $startReason = $reason;
                $previous = $point;
                continue;
            }
            $current['distance_m'] += StepsGeolocationQuality::haversineMeters(
                (float)$previous['latitude'], (float)$previous['longitude'],
                (float)$point['latitude'], (float)$point['longitude']
            );
            $current['ended_at'] = (string)$point['source_at'];
            $current['point_ids'][] = (string)$point['id'];
            $device = (string)$point['device_id'];
            if (!in_array($device, $current['device_ids'], true)) $current['device_ids'][] = $device;
            $previous = $point;
        }
        $finish($current);

        return ['trips' => $trips, 'dropped' => $dropped, 'thresholds' => $t];
    }

    /** Visual debug output: one LineString per trip plus the raw samples. */
    public static function geojson(array $points, array $trips, array $thresholds = []): array
    {
        $byId = [];
        foreach ($points as $point) $byId[(string)$point['id']] = $point;
        $features = [];
        foreach ($trips as $trip) {
            $coordinates = [];
            foreach ($trip['point_ids'] as $id) {
                if (!isset($byId[$id])) continue;
                $coordinates[] = [(float)$byId[$id]['longitude'], (float)$byId[$id]['latitude']];
            }
            if (count($coordinates) < 2) continue;
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates],
                'properties' => [
                    'started_at' => $trip['started_at'],
                    'ended_at' => $trip['ended_at'],
                    'distance_m' => round((float)$trip['distance_m'], 1),
                    'boundary_reason' => $trip['boundary_reason'],
                    'point_count' => count($trip['point_ids']),
                ],
            ];
        }
        foreach ($points as $point) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [(float)$point['longitude'], (float)$point['latitude']]],
                'properties' => [
                    'quality_state' => $point['quality_state'] ?? null,
                    'accuracy_m' => $point['accuracy_m'] ?? null,
                    'source_at' => $point['source_at'] ?? null,
                ],
            ];
        }
        return ['type' => 'FeatureCollection', 'features' => $features, 'thresholds' => $thresholds];
    }

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    public static function enqueue(PDO $pdo, string $owner): bool
    {
        $stmt = $pdo->prepare(
            "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version)
             SELECT :id, :owner, 'trips', 'pending', :version
              WHERE NOT EXISTS (
                    SELECT 1 FROM geo_processor_runs
                     WHERE user_email = :owner AND kind = 'trips' AND state IN ('pending','processing')
              )"
        );
        $stmt->execute([':id' => self::uuid(), ':owner' => $owner, ':version' => self::PROCESSOR_VERSION]);
        return $stmt->rowCount() > 0;
    }

    /** Queue a bounded segmentation refresh for the edited time window. */
    public static function enqueueRange(PDO $pdo, string $owner, string $from, string $to, string $reason = 'manual_edit'): bool
    {
        $stmt = $pdo->prepare(
            "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, input_from, input_to, details_json)
             SELECT :id, :owner, 'trips', 'pending', :version, :from, :to, :details
              WHERE NOT EXISTS (
                    SELECT 1 FROM geo_processor_runs
                     WHERE user_email = :owner AND kind = 'trips' AND state = 'pending'
                       AND input_from = :from AND input_to = :to
              )"
        );
        $stmt->execute([
            ':id' => self::uuid(),
            ':owner' => $owner,
            ':version' => self::PROCESSOR_VERSION,
            ':from' => $from,
            ':to' => $to,
            ':details' => json_encode(['reason' => $reason, 'from' => $from, 'to' => $to], JSON_UNESCAPED_SLASHES),
        ]);
        // Trip edits change movement summaries; keep the statistics cache honest.
        try { StepsGeolocationStatistics::invalidate($pdo, $owner, $from, $to); } catch (Throwable $e) { error_log('statistics invalidation: ' . $e->getMessage()); }
        return $stmt->rowCount() > 0;
    }

    public static function audit(PDO $pdo, string $owner, string $event, array $context, string $actor = ''): void
    {
        $stmt = $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)');
        $stmt->execute([
            ':id' => self::uuid(),
            ':owner' => $owner,
            ':actor' => $actor !== '' ? $actor : $owner,
            ':event' => $event,
            ':context' => json_encode($context, JSON_UNESCAPED_SLASHES),
        ]);
    }

    private static function normalizeText(string $value, int $max): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private static function isoOrNull($value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        $at = strtotime($value);
        return $at === false ? null : gmdate('c', $at);
    }

    /** @return list<array{id:string,source_at:string}> */
    private static function loadPoints(PDO $pdo, string $owner, ?string $from = null, ?string $to = null, array $ids = []): array
    {
        if ($ids !== []) {
            $ids = array_values(array_unique(array_filter($ids, static fn($id): bool => StepsGeolocationDomain::isUuid((string)$id))));
            if ($ids === []) return [];
            $list = implode(',', array_map(static fn($id): string => $pdo->quote((string)$id), $ids));
            $stmt = $pdo->prepare(
                "SELECT id, source_at FROM geo_location_points
                  WHERE user_email = :owner AND quality_state = 'accepted' AND id IN ($list)
               ORDER BY source_at, id"
            );
            $stmt->execute([':owner' => $owner]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($from === null || $to === null) return [];
        $stmt = $pdo->prepare(
            "SELECT id, source_at FROM geo_location_points
              WHERE user_email = :owner AND quality_state = 'accepted'
                AND source_at BETWEEN :from AND :to
           ORDER BY source_at, id"
        );
        $stmt->execute([':owner' => $owner, ':from' => $from, ':to' => $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<array{id:string,source_at:string}> $points */
    private static function shape(PDO $pdo, string $owner, array $points): ?array
    {
        if (count($points) < 2) return null;
        $ids = implode(',', array_map(static fn($row): string => $pdo->quote((string)$row['id']), $points));
        $stmt = $pdo->prepare(
            "SELECT ST_AsGeoJSON(ST_MakeLine(position ORDER BY source_at, id)) AS geojson,
                    COALESCE(ST_Length(ST_MakeLine(position ORDER BY source_at, id)::geography), 0) AS distance_m,
                    COUNT(*) AS point_count,
                    MIN(source_at) AS started_at,
                    MAX(source_at) AS ended_at
               FROM geo_location_points
              WHERE user_email = :owner AND id IN ($ids)"
        );
        $stmt->execute([':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['geojson'] === null) return null;
        $started = (string)$row['started_at'];
        $ended = (string)$row['ended_at'];
        return [
            'geojson' => (string)$row['geojson'],
            'distance_m' => (float)$row['distance_m'],
            'point_count' => (int)$row['point_count'],
            'started_at' => $started,
            'ended_at' => $ended,
            'duration_s' => max(0, strtotime($ended) - strtotime($started)),
            'point_ids' => array_map(static fn(array $p): string => (string)$p['id'], $points),
        ];
    }

    private static function insert(PDO $pdo, string $owner, array $shape, string $reason, array $override, string $title = '', string $notes = '', bool $visible = true): string
    {
        $id = self::uuid();
        $stmt = $pdo->prepare(
            "INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, duration_s, title, notes, state, boundary_reason, processor_version, geometry_visible, override_json)
             VALUES(:id, :owner, :started, :ended, ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326), :distance, :duration, :title, :notes, 'ready', :reason, :version, :visible, :override)"
        );
        $stmt->execute([
            ':id' => $id,
            ':owner' => $owner,
            ':started' => $shape['started_at'],
            ':ended' => $shape['ended_at'],
            ':geojson' => $shape['geojson'],
            ':distance' => $shape['distance_m'],
            ':duration' => $shape['duration_s'],
            ':title' => $title,
            ':notes' => $notes,
            ':reason' => $reason,
            ':version' => $override['processor_version'] ?? 'manual-1',
            ':visible' => $visible ? 'true' : 'false',
            ':override' => json_encode($override, JSON_UNESCAPED_SLASHES),
        ]);
        return $id;
    }

    /** @return array<string,mixed>|null */
    public static function find(PDO $pdo, string $owner, string $id): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT id, started_at, ended_at, distance_m, duration_s, title, notes, state, boundary_reason,
                    processor_version, geometry_visible, override_json, created_at, updated_at,
                    ST_AsGeoJSON(geometry) AS geojson
               FROM geo_trips
              WHERE user_email = :owner AND id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':owner' => $owner, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['override_json'] = json_decode((string)$row['override_json'], true) ?: [];
        $row['geometry_visible'] = !in_array($row['geometry_visible'], [false, 0, '0', 'f', 'false'], true);
        $row['has_geometry'] = $row['geojson'] !== null;
        $row['geojson'] = $row['geojson'] !== null ? json_decode((string)$row['geojson'], true) : null;
        $row['place_ids'] = self::linkIds($pdo, 'geo_trip_places', 'place_id', $owner, $id);
        $row['photo_ids'] = self::linkIds($pdo, 'geo_trip_photos', 'asset_id', $owner, $id);
        return $row;
    }

    /** @return list<string> */
    private static function linkIds(PDO $pdo, string $table, string $column, string $owner, string $tripId): array
    {
        $stmt = $pdo->prepare("SELECT $column AS id FROM $table WHERE user_email = :owner AND trip_id = :trip ORDER BY created_at");
        $stmt->execute([':owner' => $owner, ':trip' => $tripId]);
        return array_map(static fn(array $row): string => (string)$row['id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function pointSetFor(PDO $pdo, string $owner, array $trip): array
    {
        $override = is_array($trip['override_json'] ?? null) ? $trip['override_json'] : [];
        $ids = isset($override['point_ids']) && is_array($override['point_ids']) ? $override['point_ids'] : [];
        if ($ids !== []) return self::loadPoints($pdo, $owner, null, null, $ids);
        return self::loadPoints($pdo, $owner, (string)$trip['started_at'], (string)$trip['ended_at']);
    }

    private static function overrideFor(string $origin, array $shape, array $overrides = [], array $extra = []): array
    {
        return array_merge([
            'source' => 'manual',
            'manual' => true,
            'origin' => $origin,
            'point_ids' => $shape['point_ids'],
            'point_count' => $shape['point_count'],
            'overrides' => $overrides,
            'updated_at' => gmdate('c'),
        ], $extra);
    }

    /** @return array<string,mixed> */
    public static function create(PDO $pdo, string $owner, array $data): array
    {
        $pointIds = isset($data['point_ids']) && is_array($data['point_ids']) ? $data['point_ids'] : [];
        $from = self::isoOrNull($data['from'] ?? ($data['started_at'] ?? null));
        $to = self::isoOrNull($data['to'] ?? ($data['ended_at'] ?? null));
        if ($pointIds === []) {
            if ($from === null || $to === null || strtotime($from) >= strtotime($to)) {
                throw new StepsGeolocationTripError('invalid_range');
            }
            $points = self::loadPoints($pdo, $owner, $from, $to);
        } else {
            $points = self::loadPoints($pdo, $owner, null, null, $pointIds);
        }
        $shape = self::shape($pdo, $owner, $points);
        if ($shape === null) throw new StepsGeolocationTripError('trip_needs_points', 409);
        $title = self::normalizeText((string)($data['title'] ?? ''), 120);
        $notes = self::normalizeText((string)($data['notes'] ?? ''), 4000);
        $override = self::overrideFor('user_create', $shape, [
            'range' => $pointIds === [],
            'point_set' => $pointIds !== [],
            'title' => $title !== '',
            'notes' => $notes !== '',
        ], ['created_by' => $owner]);
        $pdo->beginTransaction();
        try {
            $id = self::insert($pdo, $owner, $shape, 'user_created', $override, $title, $notes);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, $owner, 'trip.created', ['trip_id' => $id, 'from' => $shape['started_at'], 'to' => $shape['ended_at'], 'point_count' => $shape['point_count']]);
        self::enqueueRange($pdo, $owner, $shape['started_at'], $shape['ended_at'], 'trip_created');
        $trip = self::find($pdo, $owner, $id);
        if ($trip === null) throw new StepsGeolocationTripError('trip_not_found', 404);
        return $trip;
    }

    /** @return array<string,mixed> */
    public static function update(PDO $pdo, string $owner, string $id, array $data): array
    {
        if (!StepsGeolocationDomain::isUuid($id)) throw new StepsGeolocationTripError('invalid_id');
        $trip = self::find($pdo, $owner, $id);
        if ($trip === null) throw new StepsGeolocationTripError('trip_not_found', 404);
        $fields = [];
        $overrides = [];
        $params = [':owner' => $owner, ':id' => $id];
        if (array_key_exists('title', $data)) {
            $fields[] = 'title = :title';
            $params[':title'] = self::normalizeText((string)$data['title'], 120);
            $overrides['title'] = true;
        }
        if (array_key_exists('notes', $data)) {
            $fields[] = 'notes = :notes';
            $params[':notes'] = self::normalizeText((string)$data['notes'], 4000);
            $overrides['notes'] = true;
        }
        if (array_key_exists('geometry_visible', $data)) {
            $fields[] = 'geometry_visible = :visible';
            $params[':visible'] = !empty($data['geometry_visible']) ? 'true' : 'false';
            $overrides['geometry_visible'] = true;
        }
        $started = array_key_exists('started_at', $data) ? self::isoOrNull($data['started_at']) : (string)$trip['started_at'];
        $ended = array_key_exists('ended_at', $data) ? self::isoOrNull($data['ended_at']) : (string)$trip['ended_at'];
        $rangeChanged = array_key_exists('started_at', $data) || array_key_exists('ended_at', $data);
        if ($rangeChanged) {
            if ($started === null || $ended === null || strtotime($started) >= strtotime($ended)) {
                throw new StepsGeolocationTripError('invalid_range');
            }
            $shape = self::shape($pdo, $owner, self::loadPoints($pdo, $owner, $started, $ended));
            if ($shape === null) throw new StepsGeolocationTripError('trip_needs_points', 409);
            $fields[] = 'started_at = :started';
            $fields[] = 'ended_at = :ended';
            $fields[] = 'geometry = ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326)';
            $fields[] = 'distance_m = :distance';
            $fields[] = 'duration_s = :duration';
            $params[':started'] = $shape['started_at'];
            $params[':ended'] = $shape['ended_at'];
            $params[':geojson'] = $shape['geojson'];
            $params[':distance'] = $shape['distance_m'];
            $params[':duration'] = $shape['duration_s'];
            $overrides['dates'] = true;
            $overridePatch = self::overrideFor('user_edit', $shape, $overrides, ['created_by' => $trip['override_json']['created_by'] ?? $owner]);
        } else {
            $overridePatch = ['manual' => true, 'source' => 'manual', 'origin' => 'user_edit', 'updated_at' => gmdate('c'), 'overrides' => $overrides];
        }
        $fields[] = "override_json = COALESCE(override_json, '{}'::jsonb) || :patch::jsonb";
        $fields[] = 'updated_at = now()';
        $params[':patch'] = json_encode($overridePatch, JSON_UNESCAPED_SLASHES);
        $pdo->beginTransaction();
        try {
            if (array_key_exists('place_ids', $data)) self::syncLinks($pdo, $owner, $id, 'geo_trip_places', 'place_id', 'geo_places', $data['place_ids']);
            if (array_key_exists('photo_ids', $data)) self::syncLinks($pdo, $owner, $id, 'geo_trip_photos', 'asset_id', 'geo_immich_assets', $data['photo_ids']);
            $stmt = $pdo->prepare('UPDATE geo_trips SET ' . implode(', ', $fields) . ' WHERE user_email = :owner AND id = :id');
            $stmt->execute($params);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, $owner, 'trip.updated', ['trip_id' => $id, 'fields' => array_keys($overrides)]);
        if ($rangeChanged) self::enqueueRange($pdo, $owner, (string)$trip['started_at'], (string)$trip['ended_at'], 'trip_dates_updated');
        $updated = self::find($pdo, $owner, $id);
        if ($updated === null) throw new StepsGeolocationTripError('trip_not_found', 404);
        return $updated;
    }

    private static function syncLinks(PDO $pdo, string $owner, string $tripId, string $table, string $column, string $sourceTable, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), static fn(string $id): bool => StepsGeolocationDomain::isUuid($id))));
        if ($ids !== []) {
            $list = implode(',', array_map(static fn(string $id): string => $pdo->quote($id), $ids));
            $notDeleted = $sourceTable === 'geo_places' ? ' AND deleted_at IS NULL' : '';
            $exists = $pdo->prepare("SELECT COUNT(*) FROM $sourceTable WHERE user_email = :owner AND id IN ($list)$notDeleted");
            $exists->execute([':owner' => $owner]);
            if ((int)$exists->fetchColumn() !== count($ids)) throw new StepsGeolocationTripError('invalid_' . $column . 's');
        }
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner AND trip_id = :trip")->execute([':owner' => $owner, ':trip' => $tripId]);
        $insert = $pdo->prepare("INSERT INTO $table(trip_id, $column, user_email) VALUES(:trip, :ref, :owner)");
        foreach ($ids as $ref) $insert->execute([':trip' => $tripId, ':ref' => $ref, ':owner' => $owner]);
    }

    /** @return array<string,mixed> */
    public static function recalculate(PDO $pdo, string $owner, string $id): array
    {
        if (!StepsGeolocationDomain::isUuid($id)) throw new StepsGeolocationTripError('invalid_id');
        $trip = self::find($pdo, $owner, $id);
        if ($trip === null) throw new StepsGeolocationTripError('trip_not_found', 404);
        $shape = self::shape($pdo, $owner, self::pointSetFor($pdo, $owner, $trip));
        if ($shape === null) throw new StepsGeolocationTripError('trip_needs_points', 409);
        $override = self::overrideFor('user_recalculate', $shape, $trip['override_json']['overrides'] ?? [], [
            'created_by' => $trip['override_json']['created_by'] ?? $owner,
        ]);
        $stmt = $pdo->prepare(
            "UPDATE geo_trips
                SET started_at = :started, ended_at = :ended,
                    geometry = ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326),
                    distance_m = :distance, duration_s = :duration,
                    override_json = :override, updated_at = now()
              WHERE user_email = :owner AND id = :id"
        );
        $stmt->execute([
            ':started' => $shape['started_at'],
            ':ended' => $shape['ended_at'],
            ':geojson' => $shape['geojson'],
            ':distance' => $shape['distance_m'],
            ':duration' => $shape['duration_s'],
            ':override' => json_encode($override, JSON_UNESCAPED_SLASHES),
            ':owner' => $owner,
            ':id' => $id,
        ]);
        self::audit($pdo, $owner, 'trip.recalculated', [
            'trip_id' => $id,
            'distance_m' => $shape['distance_m'],
            'duration_s' => $shape['duration_s'],
            'point_count' => $shape['point_count'],
        ]);
        self::enqueueRange($pdo, $owner, (string)$trip['started_at'], (string)$trip['ended_at'], 'trip_recalculated');
        $updated = self::find($pdo, $owner, $id);
        if ($updated === null) throw new StepsGeolocationTripError('trip_not_found', 404);
        return $updated;
    }

    public static function archive(PDO $pdo, string $owner, string $id, bool $deleted = false): void
    {
        if (!StepsGeolocationDomain::isUuid($id)) throw new StepsGeolocationTripError('invalid_id');
        $trip = self::find($pdo, $owner, $id);
        if ($trip === null) throw new StepsGeolocationTripError('trip_not_found', 404);
        $stmt = $pdo->prepare(
            "UPDATE geo_trips
                SET state = 'archived', deleted_at = :deleted_at, updated_at = now(),
                    override_json = COALESCE(override_json, '{}'::jsonb) || :patch::jsonb
              WHERE user_email = :owner AND id = :id"
        );
        $stmt->execute([
            ':deleted_at' => $deleted ? gmdate('c') : null,
            ':patch' => json_encode(['manual' => true, 'source' => 'manual', 'origin' => $deleted ? 'user_delete' : 'user_archive', 'updated_at' => gmdate('c')], JSON_UNESCAPED_SLASHES),
            ':owner' => $owner,
            ':id' => $id,
        ]);
        self::audit($pdo, $owner, $deleted ? 'trip.deleted' : 'trip.archived', [
            'trip_id' => $id,
            'started_at' => $trip['started_at'],
            'ended_at' => $trip['ended_at'],
            'title' => $trip['title'],
            'distance_m' => $trip['distance_m'],
            'point_count' => $trip['override_json']['point_count'] ?? null,
        ]);
        self::enqueueRange($pdo, $owner, (string)$trip['started_at'], (string)$trip['ended_at'], $deleted ? 'trip_deleted' : 'trip_archived');
    }

    /** @return array<string,mixed> */
    public static function merge(PDO $pdo, string $owner, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), static fn(string $id): bool => StepsGeolocationDomain::isUuid($id))));
        if (count($ids) < 2) throw new StepsGeolocationTripError('invalid_ids');
        $trips = [];
        foreach ($ids as $id) {
            $trip = self::find($pdo, $owner, $id);
            if ($trip === null) throw new StepsGeolocationTripError('trip_not_found', 404);
            $trips[] = $trip;
        }
        usort($trips, static fn(array $a, array $b): int => strtotime((string)$a['started_at']) <=> strtotime((string)$b['started_at']));
        $points = [];
        foreach ($trips as $trip) {
            foreach (self::pointSetFor($pdo, $owner, $trip) as $point) $points[(string)$point['id']] = $point;
        }
        usort($points, static fn(array $a, array $b): int => [$a['source_at'], $a['id']] <=> [$b['source_at'], $b['id']]);
        $shape = self::shape($pdo, $owner, $points);
        if ($shape === null) throw new StepsGeolocationTripError('merge_failed', 409);
        $titles = array_values(array_unique(array_filter(array_map(static fn(array $trip): string => trim((string)$trip['title']), $trips))));
        $title = self::normalizeText(implode(' + ', $titles), 120);
        $override = self::overrideFor('user_merge', $shape, ['merged_from' => $ids], ['created_by' => $owner]);
        $pdo->beginTransaction();
        try {
            $mergedId = self::insert($pdo, $owner, $shape, 'user_merge', $override, $title);
            $list = implode(',', array_map(static fn(string $id): string => $pdo->quote($id), $ids));
            foreach (['geo_trip_places' => 'place_id', 'geo_trip_photos' => 'asset_id'] as $table => $column) {
                $pdo->exec("INSERT INTO $table(trip_id, $column, user_email) SELECT " . $pdo->quote($mergedId) . ", $column, user_email FROM $table WHERE user_email = " . $pdo->quote($owner) . " AND trip_id IN ($list) ON CONFLICT DO NOTHING");
            }
            $pdo->exec("UPDATE geo_trips SET deleted_at = now(), state = 'archived', updated_at = now() WHERE user_email = " . $pdo->quote($owner) . " AND id IN ($list) AND deleted_at IS NULL");
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, $owner, 'trip.merged', ['trip_ids' => $ids, 'trip_id' => $mergedId, 'point_count' => $shape['point_count']]);
        self::enqueueRange($pdo, $owner, $shape['started_at'], $shape['ended_at'], 'trips_merged');
        $merged = self::find($pdo, $owner, $mergedId);
        if ($merged === null) throw new StepsGeolocationTripError('trip_not_found', 404);
        return $merged;
    }

    /** @return list<array<string,mixed>> */
    public static function split(PDO $pdo, string $owner, string $id, string $at): array
    {
        if (!StepsGeolocationDomain::isUuid($id)) throw new StepsGeolocationTripError('invalid_id');
        $trip = self::find($pdo, $owner, $id);
        if ($trip === null) throw new StepsGeolocationTripError('trip_not_found', 404);
        $splitAt = strtotime($at);
        if ($splitAt === false || $splitAt <= strtotime((string)$trip['started_at']) || $splitAt >= strtotime((string)$trip['ended_at'])) {
            throw new StepsGeolocationTripError('split_outside_trip', 409);
        }
        $points = self::pointSetFor($pdo, $owner, $trip);
        $index = null;
        foreach ($points as $i => $point) {
            if (strtotime((string)$point['source_at']) >= $splitAt) { $index = $i; break; }
        }
        if ($index === null || $index < 2 || count($points) - $index < 2) throw new StepsGeolocationTripError('split_failed', 409);
        $firstShape = self::shape($pdo, $owner, array_slice($points, 0, $index));
        $secondShape = self::shape($pdo, $owner, array_slice($points, $index));
        if ($firstShape === null || $secondShape === null) throw new StepsGeolocationTripError('split_failed', 409);
        $base = $trip['override_json']['overrides'] ?? [];
        $firstOverride = self::overrideFor('user_split', $firstShape, $base, ['created_by' => $owner]);
        $secondOverride = self::overrideFor('user_split', $secondShape, $base, ['created_by' => $owner]);
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(
                "UPDATE geo_trips
                    SET ended_at = :ended, geometry = ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326),
                        distance_m = :distance, duration_s = :duration,
                        override_json = :override, updated_at = now()
                  WHERE user_email = :owner AND id = :id"
            );
            $update->execute([
                ':ended' => $firstShape['ended_at'],
                ':geojson' => $firstShape['geojson'],
                ':distance' => $firstShape['distance_m'],
                ':duration' => $firstShape['duration_s'],
                ':override' => json_encode($firstOverride, JSON_UNESCAPED_SLASHES),
                ':owner' => $owner,
                ':id' => $id,
            ]);
            $newId = self::insert($pdo, $owner, $secondShape, 'user_split', $secondOverride, trim((string)$trip['title']));
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, $owner, 'trip.split', ['trip_id' => $id, 'new_trip_id' => $newId, 'at' => $secondShape['started_at']]);
        self::enqueueRange($pdo, $owner, (string)$trip['started_at'], (string)$trip['ended_at'], 'trip_split');
        $first = self::find($pdo, $owner, $id);
        $second = self::find($pdo, $owner, $newId);
        return array_values(array_filter([$first, $second]));
    }
}
