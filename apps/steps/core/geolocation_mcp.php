<?php
/**
 * Read-only geolocation service contract for MCP (task 29).
 *
 * MCP never receives database access. Every call resolves the caller through
 * the authenticated Steps API, applies the task-23 family capability, caps its
 * interval and page size, rounds coordinates, and records redacted usage.
 */
require_once __DIR__ . '/geolocation_security.php';
require_once __DIR__ . '/geolocation_permissions.php';
require_once __DIR__ . '/geolocation_timeline.php';
require_once __DIR__ . '/geolocation_statistics.php';

final class StepsGeolocationMcpError extends RuntimeException
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

final class StepsGeolocationMcp
{
    public const MAX_RANGE_DAYS = 31;
    public const MAX_LIMIT = 100;
    public const TOOLS = [
        'get_current_location',
        'search_places',
        'search_visits',
        'get_timeline',
        'get_trips',
        'get_statistics',
        'get_immich_photos',
    ];
    private const FORBIDDEN_PATTERN = '/(create|update|delete|remove|edit|write|import|export|share|revoke|grant|purge|upload|post|set_|save|mutation|connect|disconnect)/i';

    public static function uuid(): string
    {
        return StepsGeolocationSecurity::uuid();
    }

    /** Reject anything that is not a declared read-only tool before dispatch. */
    public static function assertReadOnly(string $tool): string
    {
        $tool = strtolower(trim($tool));
        if (str_starts_with($tool, 'geolocation_')) $tool = substr($tool, strlen('geolocation_'));
        if (preg_match(self::FORBIDDEN_PATTERN, $tool)) throw new StepsGeolocationMcpError('mcp_read_only', 403);
        if (!in_array($tool, self::TOOLS, true)) throw new StepsGeolocationMcpError('unknown_tool', 404);
        return $tool;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function run(PDO $pdo, string $viewer, string $tool, array $args, string $requestId = ''): array
    {
        $viewer = StepsGeolocationSecurity::normalizeOwner($viewer);
        if ($viewer === '') throw new StepsGeolocationMcpError('unauthorized', 401);
        $tool = self::assertReadOnly($tool);
        $target = StepsGeolocationSecurity::normalizeOwner((string)($args['target'] ?? $args['subject'] ?? $viewer));
        if ($target === '') $target = $viewer;
        [$from, $to, $fromAt, $toAt] = self::range($tool, $args);
        $scope = self::scope($pdo, $viewer, $target, $fromAt, $toAt);
        $placesOnly = (bool)$scope['places_only'];
        if ($placesOnly && in_array($tool, ['get_current_location', 'get_trips', 'get_statistics', 'get_immich_photos'], true)) {
            throw new StepsGeolocationMcpError('places_only_scope_denied', 403);
        }
        $detail = !empty($args['detail']);
        $decimals = self::decimals($scope, $detail);
        $limit = self::limit($args['limit'] ?? null);
        $effectiveTarget = (string)$scope['owner'];

        switch ($tool) {
            case 'get_current_location':
                [$payload, $count] = self::currentLocation($pdo, $effectiveTarget, $decimals, $detail, (string)$scope['scope_from']);
                break;
            case 'search_places':
                [$payload, $count] = self::places($pdo, $effectiveTarget, $from, $to, $args, $decimals, $limit, (bool)($scope['access_level'] === 'self'));
                break;
            case 'search_visits':
                [$payload, $count] = self::visits($pdo, $effectiveTarget, $from, $to, $args, $limit);
                break;
            case 'get_timeline':
                [$payload, $count] = self::timeline($pdo, $effectiveTarget, $from, $to, $args, $decimals, $limit, $placesOnly);
                break;
            case 'get_trips':
                [$payload, $count] = self::trips($pdo, $effectiveTarget, $from, $to, $decimals, $limit, $detail);
                break;
            case 'get_statistics':
                [$payload, $count] = self::statistics($pdo, $effectiveTarget, $from, $to, $args);
                break;
            case 'get_immich_photos':
                [$payload, $count] = self::immichPhotos($pdo, $viewer, $effectiveTarget, $from, $to, $args, $decimals, $limit, $detail);
                break;
            default:
                throw new StepsGeolocationMcpError('unknown_tool', 404);
        }

        $audit = [
            'tool' => $tool,
            'target_is_self' => $target === $viewer,
            'access_level' => (string)$scope['access_level'],
            'precision_m' => (float)$scope['precision_m'],
            'precision_decimals' => $decimals,
            'result_count' => $count,
            'correlation_id' => substr(preg_replace('/[^a-zA-Z0-9._:-]/', '', $requestId) ?: '', 0, 80),
        ];
        StepsGeolocationSecurity::audit($pdo, $viewer, $viewer, 'mcp.tool_used', $audit);
        if ($target !== $viewer) {
            StepsGeolocationSecurity::audit($pdo, $target, $viewer, 'mcp.shared_access', $audit);
        }

        return [
            'tool' => 'geolocation_' . $tool,
            'read_only' => true,
            'target_is_self' => $target === $viewer,
            'access_level' => (string)$scope['access_level'],
            'range' => ['from' => $from, 'to' => $to],
            'precision_decimals' => $decimals,
            'limited' => $limit,
            'result_count' => $count,
            'result' => $payload,
        ];
    }

    /**
     * @return array{0:string,1:string,2:int,3:int}
     */
    private static function range(string $tool, array $args): array
    {
        $now = time();
        $defaults = match ($tool) {
            'get_current_location' => [$now - 900, $now + 60],
            'search_places', 'search_visits', 'get_immich_photos' => [$now - 30 * 86400, $now],
            'get_statistics' => [$now - 7 * 86400, $now],
            default => [$now - 7 * 86400, $now],
        };
        $parse = static function ($value, int $fallback, bool $end): int {
            $value = trim((string)$value);
            if ($value === '') return $fallback;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $ts = strtotime($value . ' 00:00:00 UTC');
                return $end ? $ts + 86400 : $ts;
            }
            $ts = strtotime($value);
            if ($ts === false) throw new StepsGeolocationMcpError('invalid_range');
            return $ts;
        };
        $fromAt = $parse($args['from'] ?? null, $defaults[0], false);
        $toAt = $parse($args['to'] ?? null, $defaults[1], true);
        if ($toAt <= $fromAt) throw new StepsGeolocationMcpError('invalid_range');
        if ($toAt - $fromAt > self::MAX_RANGE_DAYS * 86400) throw new StepsGeolocationMcpError('range_too_large', 400);
        return [gmdate('c', $fromAt), gmdate('c', $toAt), $fromAt, $toAt];
    }

    /**
     * @return array<string,mixed>
     */
    private static function scope(PDO $pdo, string $viewer, string $target, int $fromAt, int $toAt): array
    {
        $scope = StepsGeolocationPermissions::authorize($pdo, $viewer, $target, gmdate('c', $fromAt), gmdate('c', $toAt));
        if (!empty($scope['error'])) throw new StepsGeolocationMcpError((string)$scope['error'], 403);
        return $scope;
    }

    private static function decimals(array $scope, bool $detail): int
    {
        $meters = (float)($scope['precision_m'] ?? 0);
        if ($meters > 0) return StepsGeolocationPermissions::precisionDecimals($meters);
        if ($detail) return 6;
        return 3;
    }

    private static function limit($raw): int
    {
        if ($raw === null || $raw === '') return 50;
        if (!is_numeric($raw)) throw new StepsGeolocationMcpError('invalid_limit');
        return max(1, min(self::MAX_LIMIT, (int)$raw));
    }

    /** @return array{0:array<string,mixed>,1:int} */
    private static function currentLocation(PDO $pdo, string $owner, int $decimals, bool $detail, string $scopeFrom): array
    {
        $stmt = $pdo->prepare(
            "SELECT ST_Y(position) AS latitude, ST_X(position) AS longitude, source_at, accuracy_m
               FROM geo_location_points
              WHERE user_email = :owner AND quality_state = 'accepted' AND source_at >= :scope_from
           ORDER BY source_at DESC LIMIT 1"
        );
        $stmt->execute([':owner' => $owner, ':scope_from' => $scopeFrom]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return [['location' => null], 0];
        $location = [
            'latitude' => StepsGeolocationSecurity::roundCoordinate($row['latitude'], $decimals),
            'longitude' => StepsGeolocationSecurity::roundCoordinate($row['longitude'], $decimals),
            'observed_at' => $row['source_at'],
        ];
        if ($detail) $location['accuracy_m'] = $row['accuracy_m'] !== null ? (float)$row['accuracy_m'] : null;
        return [['location' => $location], 1];
    }

    /** @return array{0:array<string,mixed>,1:int} */
    private static function places(PDO $pdo, string $owner, string $from, string $to, array $args, int $decimals, int $limit, bool $isSelf): array
    {
        $query = trim((string)($args['q'] ?? ''));
        $query = function_exists('mb_strtolower') ? mb_strtolower($query) : strtolower($query);
        $placeId = trim((string)($args['place_id'] ?? ''));
        if ($placeId !== '' && !StepsGeolocationDomain::isUuid($placeId)) throw new StepsGeolocationMcpError('invalid_place');
        $sql = "SELECT p.id, p.name, p.tag, p.radius_m, ST_Y(p.position) AS latitude, ST_X(p.position) AS longitude,
                       COUNT(v.id) AS visit_count, MAX(v.arrived_at) AS last_visited_at
                  FROM geo_places p
             LEFT JOIN geo_visits v ON v.place_id = p.id AND v.user_email = p.user_email
                       AND v.arrived_at >= :from AND v.arrived_at < :to
                 WHERE p.user_email = :owner AND p.deleted_at IS NULL AND p.is_active";
        $params = [':owner' => $owner, ':from' => $from, ':to' => $to];
        if ($placeId !== '') { $sql .= ' AND p.id = :place'; $params[':place'] = $placeId; }
        $sql .= ' GROUP BY p.id, p.name, p.tag, p.radius_m, p.position';
        // A family grant is derived from visits; saved-but-never-visited places stay private.
        if (!$isSelf) $sql .= ' HAVING COUNT(v.id) > 0';
        $sql .= ' ORDER BY visit_count DESC, p.name ASC LIMIT ' . ($limit + 1);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $places = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            if ($query !== '') {
                $haystack = function_exists('mb_strtolower') ? mb_strtolower((string)$row['name'] . ' ' . (string)$row['tag']) : strtolower((string)$row['name'] . ' ' . (string)$row['tag']);
                if (!str_contains($haystack, $query)) continue;
            }
            $places[] = [
                'id' => (string)$row['id'],
                'name' => (string)$row['name'],
                'tag' => (string)$row['tag'],
                'latitude' => StepsGeolocationSecurity::roundCoordinate($row['latitude'], $decimals),
                'longitude' => StepsGeolocationSecurity::roundCoordinate($row['longitude'], $decimals),
                'radius_m' => (float)$row['radius_m'],
                'visit_count' => (int)$row['visit_count'],
                'last_visited_at' => $row['last_visited_at'],
            ];
        }
        return [['places' => $places], count($places)];
    }

    /** @return array{0:array<string,mixed>,1:int} */
    private static function visits(PDO $pdo, string $owner, string $from, string $to, array $args, int $limit): array
    {
        $placeId = trim((string)($args['place_id'] ?? ''));
        if ($placeId !== '' && !StepsGeolocationDomain::isUuid($placeId)) throw new StepsGeolocationMcpError('invalid_place');
        $sql = "SELECT v.id, v.place_id, p.name AS place_name, p.tag AS place_tag,
                       v.arrived_at, v.departed_at, v.confidence, v.state
                  FROM geo_visits v LEFT JOIN geo_places p ON p.id = v.place_id AND p.user_email = v.user_email
                 WHERE v.user_email = :owner AND v.arrived_at >= :from AND v.arrived_at < :to";
        $params = [':owner' => $owner, ':from' => $from, ':to' => $to];
        if ($placeId !== '') { $sql .= ' AND v.place_id = :place'; $params[':place'] = $placeId; }
        $sql .= ' ORDER BY v.arrived_at DESC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $visits = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $visits[] = [
                'id' => (string)$row['id'],
                'place_id' => $row['place_id'] !== null ? (string)$row['place_id'] : null,
                'place_name' => $row['place_name'] !== null ? (string)$row['place_name'] : null,
                'place_tag' => $row['place_tag'] !== null ? (string)$row['place_tag'] : null,
                'arrived_at' => $row['arrived_at'],
                'departed_at' => $row['departed_at'],
                'confidence' => $row['confidence'] !== null ? (float)$row['confidence'] : null,
                'state' => (string)$row['state'],
            ];
        }
        return [['visits' => $visits], count($visits)];
    }

    /** @return array{0:array<string,mixed>,1:int} */
    private static function timeline(PDO $pdo, string $owner, string $from, string $to, array $args, int $decimals, int $limit, bool $placesOnly): array
    {
        $include = $placesOnly ? ['visits'] : ['trips', 'stops', 'visits', 'gaps'];
        if (!$placesOnly && is_array($args['include'] ?? null)) {
            $include = array_values(array_intersect($include, array_map('strval', $args['include'])));
            if ($include === []) throw new StepsGeolocationMcpError('invalid_include');
        }
        $detail = !empty($args['detail']);
        $timezone = StepsGeolocationTimeline::timezone((string)($args['timezone'] ?? 'UTC'));
        $result = StepsGeolocationTimeline::entries($pdo, $owner, [
            'view' => 'interval',
            'from' => $from,
            'to' => $to,
            'timezone' => $timezone,
            'include' => $include,
            'limit' => $limit,
            'q' => (string)($args['q'] ?? ''),
        ]);
        foreach ($result['entries'] as &$entry) {
            if (isset($entry['bbox']) && is_array($entry['bbox'])) {
                $bbox = $entry['bbox'];
                if ($detail) {
                    foreach (['minLon', 'minLat', 'maxLon', 'maxLat'] as $key) {
                        if (isset($bbox[$key])) $bbox[$key] = StepsGeolocationSecurity::roundCoordinate($bbox[$key], $decimals);
                    }
                    $entry['bbox'] = $bbox;
                } else {
                    $entry['center'] = [
                        'latitude' => StepsGeolocationSecurity::roundCoordinate(((float)$bbox['minLat'] + (float)$bbox['maxLat']) / 2, $decimals),
                        'longitude' => StepsGeolocationSecurity::roundCoordinate(((float)$bbox['minLon'] + (float)$bbox['maxLon']) / 2, $decimals),
                    ];
                    unset($entry['bbox']);
                }
            }
        }
        unset($entry);
        return [[
            'entries' => $result['entries'],
            'next_cursor' => $result['next_cursor'],
            'range' => $result['range'],
            'processing' => $placesOnly ? ['quality' => []] : ['quality' => $result['processing']['quality'] ?? []],
        ], count($result['entries'])];
    }

    /** @return array{0:array<string,mixed>,1:int} */
    private static function trips(PDO $pdo, string $owner, string $from, string $to, int $decimals, int $limit, bool $detail): array
    {
        $stmt = $pdo->prepare(
            "SELECT id, started_at, ended_at, distance_m, duration_s, title, state,
                    ST_XMin(geometry) AS min_lon, ST_YMin(geometry) AS min_lat,
                    ST_XMax(geometry) AS max_lon, ST_YMax(geometry) AS max_lat
               FROM geo_trips
              WHERE user_email = :owner AND deleted_at IS NULL
                AND started_at >= :from AND started_at < :to
           ORDER BY started_at DESC LIMIT $limit"
        );
        $stmt->execute([':owner' => $owner, ':from' => $from, ':to' => $to]);
        $trips = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $trip = [
                'id' => (string)$row['id'],
                'started_at' => $row['started_at'],
                'ended_at' => $row['ended_at'],
                'distance_m' => (float)$row['distance_m'],
                'duration_s' => (int)$row['duration_s'],
                'title' => (string)$row['title'],
                'state' => (string)$row['state'],
            ];
            if ($detail && $row['min_lat'] !== null) {
                $trip['bbox'] = [
                    'minLat' => StepsGeolocationSecurity::roundCoordinate($row['min_lat'], $decimals),
                    'minLon' => StepsGeolocationSecurity::roundCoordinate($row['min_lon'], $decimals),
                    'maxLat' => StepsGeolocationSecurity::roundCoordinate($row['max_lat'], $decimals),
                    'maxLon' => StepsGeolocationSecurity::roundCoordinate($row['max_lon'], $decimals),
                ];
            }
            $trips[] = $trip;
        }
        return [['trips' => $trips], count($trips)];
    }

    /** @return array{0:array<string,mixed>,1:int} */
    private static function statistics(PDO $pdo, string $owner, string $from, string $to, array $args): array
    {
        $timezone = StepsGeolocationStatistics::timezone((string)($args['timezone'] ?? 'UTC'));
        try {
            $summary = StepsGeolocationStatistics::summary($pdo, $owner, $timezone, substr($from, 0, 10), substr($to, 0, 10));
        } catch (InvalidArgumentException $e) {
            throw new StepsGeolocationMcpError('invalid_range');
        }
        return [$summary, (int)($summary['metrics']['days'] ?? 0)];
    }

    /** @return array{0:array<string,mixed>,1:int} */
    private static function immichPhotos(PDO $pdo, string $viewer, string $owner, string $from, string $to, array $args, int $decimals, int $limit, bool $detail): array
    {
        if ($owner !== $viewer) throw new StepsGeolocationMcpError('photo_access_denied', 403);
        $params = [':owner' => $owner, ':from' => $from, ':to' => $to];
        $where = 'user_email = :owner AND deleted_at IS NULL AND captured_at >= :from AND captured_at < :to';
        $bbox = trim((string)($args['bbox'] ?? ''));
        if ($bbox !== '') {
            $parts = array_map('trim', explode(',', $bbox));
            if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) throw new StepsGeolocationMcpError('invalid_bbox');
            [$minLon, $minLat, $maxLon, $maxLat] = array_map('floatval', $parts);
            if ($minLon >= $maxLon || $minLat >= $maxLat || $minLat < -90 || $maxLat > 90 || $minLon < -180 || $maxLon > 180) {
                throw new StepsGeolocationMcpError('invalid_bbox');
            }
            $where .= ' AND position IS NOT NULL AND ST_Within(position, ST_MakeEnvelope(:min_lon, :min_lat, :max_lon, :max_lat, 4326))';
            $params += [':min_lon' => $minLon, ':min_lat' => $minLat, ':max_lon' => $maxLon, ':max_lat' => $maxLat];
        }
        $stmt = $pdo->prepare(
            "SELECT id, immich_asset_id, captured_at, original_filename, state, ST_Y(position) AS latitude, ST_X(position) AS longitude
               FROM geo_immich_assets WHERE $where ORDER BY captured_at DESC LIMIT $limit"
        );
        $stmt->execute($params);
        $photos = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $photo = [
                'id' => (string)$row['id'],
                'immich_asset_id' => (string)$row['immich_asset_id'],
                'captured_at' => $row['captured_at'],
                'original_filename' => (string)$row['original_filename'],
                'state' => (string)$row['state'],
            ];
            if ($detail) {
                $photo['latitude'] = $row['latitude'] !== null ? StepsGeolocationSecurity::roundCoordinate($row['latitude'], $decimals) : null;
                $photo['longitude'] = $row['longitude'] !== null ? StepsGeolocationSecurity::roundCoordinate($row['longitude'], $decimals) : null;
            }
            $photos[] = $photo;
        }
        return [['photos' => $photos], count($photos)];
    }
}
