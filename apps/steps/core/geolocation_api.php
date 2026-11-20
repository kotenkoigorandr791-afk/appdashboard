<?php
/**
 * Authenticated REST API v1 (task 28).
 *
 * API keys are tenant-owned database rows. Only a SHA-256 hash is stored; the
 * plaintext secret is shown exactly once at issue/rotate time. Keys are scoped,
 * can be rotated and revoked, and carry creation/rotation/last-used metadata.
 *
 * Controllers stay thin: every query helper scopes by the authenticated owner at
 * this service layer, applies deterministic ordering, explicit limits, viewport
 * and date predicates, and returns stable identifiers plus processing states.
 * Error bodies and request correlation ids are shared by every endpoint.
 */
final class StepsGeolocationApiException extends RuntimeException
{
    public string $errorCode;
    public int $status;
    /** @var array<string,mixed> */
    public array $details;

    public function __construct(string $errorCode, int $status = 400, array $details = [])
    {
        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->details = $details;
        parent::__construct($errorCode);
    }
}

final class StepsGeolocationApi
{
    public const VERSION = 'v1';
    /** The API has no unauthenticated or public-link surface. */
    public const PUBLIC = false;
    public const KEY_PREFIX = 'stp_';
    public const MAX_LIMIT = 200;
    public const DEFAULT_LIMIT = 50;
    public const SCOPES = [
        'read:points',
        'read:trips',
        'read:places',
        'read:visits',
        'read:family',
        'read:immich',
        'read:imports',
        'read:exports',
        'read:statistics',
        'read:achievements',
        'write:points',
        'write:exports',
    ];
    /** Read-only scopes issued by default so a new key cannot mutate anything. */
    public const DEFAULT_SCOPES = [
        'read:points', 'read:trips', 'read:places', 'read:visits',
        'read:imports', 'read:statistics', 'read:achievements',
    ];
    public const ERROR_CATALOG = [
        'unauthorized' => 'Authentication is required.',
        'missing_api_key' => 'Provide an API key in X-API-Key or as a Bearer token.',
        'invalid_api_key' => 'The API key is invalid, rotated or revoked.',
        'api_key_expired' => 'The API key has expired.',
        'scope_required' => 'The API key does not grant the required scope.',
        'invalid_scope' => 'One or more requested scopes are not supported.',
        'not_found' => 'The requested resource does not exist or is not owned by this account.',
        'invalid_request' => 'The request is malformed.',
        'invalid_id' => 'The identifier is not a valid UUID.',
        'invalid_limit' => 'The limit is outside the allowed range.',
        'invalid_offset' => 'The offset is invalid.',
        'invalid_range' => 'The date range is invalid.',
        'invalid_bounds' => 'The viewport bounds are invalid.',
        'invalid_body' => 'The request body is not a JSON object.',
        'method_not_allowed' => 'The HTTP method is not supported for this resource.',
        'unknown_resource' => 'The API resource is not part of v1.',
        'rate_limited' => 'Too many requests; retry later.',
        'internal_error' => 'The request could not be completed.',
    ];

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    /** Stable correlation id echoed from the client when it is well formed. */
    public static function requestId(): string
    {
        if (isset($GLOBALS['steps_api_request_id']) && is_string($GLOBALS['steps_api_request_id'])) {
            return $GLOBALS['steps_api_request_id'];
        }
        $candidate = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_CORRELATION_ID'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._-]{8,64}$/', $candidate)) {
            $candidate = 'req_' . bin2hex(random_bytes(8));
        }
        $GLOBALS['steps_api_request_id'] = $candidate;
        if (!headers_sent()) header('X-Request-Id: ' . $candidate);
        return $candidate;
    }

    /** @param array<string,mixed> $body */
    public static function respond(array $body, int $status = 200): void
    {
        $body['api_version'] = self::VERSION;
        $body['request_id'] = self::requestId();
        public_api_json($body, $status);
    }

    /** @param array<string,mixed> $details */
    public static function fail(string $code, int $status = 400, array $details = []): void
    {
        $body = [
            'ok' => false,
            'error' => $code,
            'message' => self::ERROR_CATALOG[$code] ?? $code,
            'request_id' => self::requestId(),
        ];
        if ($details !== []) $body['details'] = $details;
        public_api_json($body, $status);
    }

    public static function failFrom(StepsGeolocationApiException $e): void
    {
        self::fail($e->errorCode, $e->status, $e->details);
    }

    // ---------------------------------------------------------------------
    // API keys
    // ---------------------------------------------------------------------

    public static function validateScopes(array $scopes): array
    {
        $out = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope)) throw new StepsGeolocationApiException('invalid_scope', 400);
            $scope = strtolower(trim($scope));
            if ($scope === '') continue;
            if (!in_array($scope, self::SCOPES, true)) throw new StepsGeolocationApiException('invalid_scope', 400, ['scope' => $scope]);
            $out[$scope] = true;
        }
        return array_keys($out);
    }

    /** @return list<array<string,mixed>> */
    public static function listKeys(PDO $pdo, string $owner): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, key_prefix, scopes_json, created_at, rotated_at, last_used_at, revoked_at, expires_at
               FROM geo_api_keys WHERE user_email = :owner
           ORDER BY (revoked_at IS NULL) DESC, created_at DESC'
        );
        $stmt->execute([':owner' => $owner]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scopes = json_decode((string)$row['scopes_json'], true);
            $rows[] = [
                'id' => (string)$row['id'],
                'name' => (string)$row['name'],
                'prefix' => (string)$row['key_prefix'],
                'scopes' => is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [],
                'created_at' => $row['created_at'],
                'rotated_at' => $row['rotated_at'],
                'last_used_at' => $row['last_used_at'],
                'revoked_at' => $row['revoked_at'],
                'expires_at' => $row['expires_at'],
                'active' => $row['revoked_at'] === null && ($row['expires_at'] === null || strtotime((string)$row['expires_at']) > time()),
            ];
        }
        return $rows;
    }

    /**
     * @return array{id:string,name:string,prefix:string,scopes:list<string>,secret:string}
     */
    public static function issueKey(PDO $pdo, string $owner, string $name, array $scopes = self::DEFAULT_SCOPES): array
    {
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '';
        $name = function_exists('mb_substr') ? mb_substr(trim($name), 0, 80) : substr(trim($name), 0, 80);
        if ($name === '') throw new StepsGeolocationApiException('invalid_request', 400, ['field' => 'name']);
        $scopes = self::validateScopes($scopes);
        if ($scopes === []) throw new StepsGeolocationApiException('invalid_scope', 400, ['field' => 'scopes']);

        $active = $pdo->prepare('SELECT COUNT(*) FROM geo_api_keys WHERE user_email = :owner AND revoked_at IS NULL');
        $active->execute([':owner' => $owner]);
        if ((int)$active->fetchColumn() >= 20) throw new StepsGeolocationApiException('invalid_request', 409, ['reason' => 'key_limit_reached']);

        $secret = self::KEY_PREFIX . bin2hex(random_bytes(24));
        $id = self::uuid();
        $prefix = substr($secret, 0, 12);
        $pdo->prepare(
            'INSERT INTO geo_api_keys(id, user_email, name, key_prefix, key_hash, scopes_json, expires_at)
             VALUES(:id, :owner, :name, :prefix, :hash, :scopes, NULL)'
        )->execute([
            ':id' => $id,
            ':owner' => $owner,
            ':name' => $name,
            ':prefix' => $prefix,
            ':hash' => hash('sha256', $secret),
            ':scopes' => json_encode($scopes, JSON_UNESCAPED_SLASHES),
        ]);
        return ['id' => $id, 'name' => $name, 'prefix' => $prefix, 'scopes' => $scopes, 'secret' => $secret];
    }

    /** Rotate the secret in place, keeping the key id and metadata. */
    public static function rotateKey(PDO $pdo, string $owner, string $id): array
    {
        self::requireUuid($id);
        $row = self::keyRow($pdo, $owner, $id);
        if ($row === null) throw new StepsGeolocationApiException('not_found', 404);
        if ($row['revoked_at'] !== null) throw new StepsGeolocationApiException('invalid_api_key', 409);
        $secret = self::KEY_PREFIX . bin2hex(random_bytes(24));
        $pdo->prepare('UPDATE geo_api_keys SET key_prefix = :prefix, key_hash = :hash, rotated_at = now(), last_used_at = NULL WHERE user_email = :owner AND id = :id')
            ->execute([
                ':prefix' => substr($secret, 0, 12),
                ':hash' => hash('sha256', $secret),
                ':owner' => $owner,
                ':id' => $id,
            ]);
        $scopes = json_decode((string)$row['scopes_json'], true);
        return [
            'id' => $id,
            'name' => (string)$row['name'],
            'prefix' => substr($secret, 0, 12),
            'scopes' => is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [],
            'secret' => $secret,
        ];
    }

    public static function revokeKey(PDO $pdo, string $owner, string $id): void
    {
        self::requireUuid($id);
        $stmt = $pdo->prepare('UPDATE geo_api_keys SET revoked_at = COALESCE(revoked_at, now()) WHERE user_email = :owner AND id = :id');
        $stmt->execute([':owner' => $owner, ':id' => $id]);
        if ($stmt->rowCount() === 0) throw new StepsGeolocationApiException('not_found', 404);
    }

    private static function keyRow(PDO $pdo, string $owner, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM geo_api_keys WHERE user_email = :owner AND id = :id');
        $stmt->execute([':owner' => $owner, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function requireUuid(string $id): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            throw new StepsGeolocationApiException('invalid_id', 400);
        }
    }

    /** Resolve a presented key to its owner and scopes; null means invalid. */
    public static function authenticate(PDO $pdo, string $secret): ?array
    {
        $secret = trim($secret);
        if ($secret === '' || !str_starts_with($secret, self::KEY_PREFIX) || strlen($secret) > 128) return null;
        $stmt = $pdo->prepare('SELECT * FROM geo_api_keys WHERE key_hash = :hash LIMIT 1');
        $stmt->execute([':hash' => hash('sha256', $secret)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if ($row['revoked_at'] !== null) return null;
        if ($row['expires_at'] !== null && strtotime((string)$row['expires_at']) <= time()) return null;
        $pdo->prepare("UPDATE geo_api_keys SET last_used_at = now() WHERE id = :id AND (last_used_at IS NULL OR last_used_at < now() - interval '60 seconds')")
            ->execute([':id' => $row['id']]);
        $scopes = json_decode((string)$row['scopes_json'], true);
        return [
            'id' => (string)$row['id'],
            'user_email' => (string)$row['user_email'],
            'name' => (string)$row['name'],
            'prefix' => (string)$row['key_prefix'],
            'scopes' => is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [],
        ];
    }

    public static function hasScope(array $key, string $scope): bool
    {
        return in_array($scope, (array)($key['scopes'] ?? []), true);
    }

    public static function requireScope(array $key, string $scope): void
    {
        if (!self::hasScope($key, $scope)) throw new StepsGeolocationApiException('scope_required', 403, ['required_scope' => $scope]);
    }

    // ---------------------------------------------------------------------
    // Query helpers (service-layer ownership enforcement)
    // ---------------------------------------------------------------------

    public static function limit($value): int
    {
        if ($value === null || $value === '') return self::DEFAULT_LIMIT;
        if (!is_numeric($value)) throw new StepsGeolocationApiException('invalid_limit', 400);
        $limit = (int)$value;
        if ($limit < 1 || $limit > self::MAX_LIMIT) throw new StepsGeolocationApiException('invalid_limit', 400, ['max' => self::MAX_LIMIT]);
        return $limit;
    }

    public static function offset($value): int
    {
        if ($value === null || $value === '') return 0;
        if (!is_numeric($value)) throw new StepsGeolocationApiException('invalid_offset', 400);
        $offset = (int)$value;
        if ($offset < 0 || $offset > 1000000) throw new StepsGeolocationApiException('invalid_offset', 400);
        return $offset;
    }

    /** @return array{from:?string,to:?string} */
    public static function range($from, $to): array
    {
        $parse = static function ($value): ?string {
            if ($value === null || $value === '') return null;
            if (!is_string($value) || strlen($value) > 40 || strtotime($value) === false) throw new StepsGeolocationApiException('invalid_range', 400);
            return gmdate('c', strtotime($value));
        };
        $fromIso = $parse($from);
        $toIso = $parse($to);
        if ($fromIso !== null && $toIso !== null && strcmp($fromIso, $toIso) > 0) throw new StepsGeolocationApiException('invalid_range', 400);
        return ['from' => $fromIso, 'to' => $toIso];
    }

    /** @return array{minLon:float,minLat:float,maxLon:float,maxLat:float}|null */
    public static function bbox($value): ?array
    {
        if ($value === null || $value === '') return null;
        $parts = is_string($value) ? explode(',', $value) : (is_array($value) ? $value : []);
        if (count($parts) !== 4) throw new StepsGeolocationApiException('invalid_bounds', 400);
        foreach ($parts as $part) {
            if (!is_numeric($part)) throw new StepsGeolocationApiException('invalid_bounds', 400);
        }
        [$minLon, $minLat, $maxLon, $maxLat] = array_map('floatval', array_values($parts));
        if ($minLon < -180 || $maxLon > 180 || $minLat < -90 || $maxLat > 90) throw new StepsGeolocationApiException('invalid_bounds', 400);
        if ($minLon >= $maxLon || $minLat >= $maxLat) throw new StepsGeolocationApiException('invalid_bounds', 400);
        return ['minLon' => $minLon, 'minLat' => $minLat, 'maxLon' => $maxLon, 'maxLat' => $maxLat];
    }

    /** @return array{total:int,items:list<array<string,mixed>>,limit:int,offset:int,next_offset:?int} */
    public static function points(PDO $pdo, string $owner, array $filters): array
    {
        $limit = self::limit($filters['limit'] ?? null);
        $offset = self::offset($filters['offset'] ?? null);
        $range = self::range($filters['from'] ?? null, $filters['to'] ?? null);
        $bbox = self::bbox($filters['bbox'] ?? null);
        $where = ['user_email = :owner'];
        $params = [':owner' => $owner];
        if ($range['from'] !== null) { $where[] = 'source_at >= :from'; $params[':from'] = $range['from']; }
        if ($range['to'] !== null) { $where[] = 'source_at <= :to'; $params[':to'] = $range['to']; }
        $state = trim((string)($filters['state'] ?? ''));
        if (in_array($state, ['pending', 'accepted', 'flagged', 'rejected'], true)) { $where[] = 'quality_state = :state'; $params[':state'] = $state; }
        $source = trim((string)($filters['source'] ?? ''));
        if (in_array($source, ['mobile', 'import', 'api'], true)) { $where[] = 'source_type = :source'; $params[':source'] = $source; }
        $device = trim((string)($filters['device_id'] ?? ''));
        if ($device !== '') { self::requireUuid($device); $where[] = 'device_id = :device'; $params[':device'] = $device; }
        if ($bbox !== null) {
            $where[] = 'position && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)';
            $params[':minLon'] = $bbox['minLon'];
            $params[':minLat'] = $bbox['minLat'];
            $params[':maxLon'] = $bbox['maxLon'];
            $params[':maxLat'] = $bbox['maxLat'];
        }
        $whereSql = implode(' AND ', $where);
        $countKey = class_exists('StepsGeolocationObservability') ? StepsGeolocationObservability::cacheKey('map', $owner, ['points_total', $whereSql, $params]) : '';
        $cachedTotal = $countKey !== '' ? StepsGeolocationObservability::cacheGet($countKey) : null;
        if ($cachedTotal !== null && isset($cachedTotal['total'])) {
            $total = (int)$cachedTotal['total'];
        } else {
            $count = $pdo->prepare("SELECT COUNT(*) FROM geo_location_points WHERE $whereSql");
            $count->execute($params);
            $total = (int)$count->fetchColumn();
            if ($countKey !== '') StepsGeolocationObservability::cacheSet($countKey, ['total' => $total], 10);
        }
        $stmt = $pdo->prepare(
            "SELECT id, device_id, source_type, source_at, received_at, quality_state, accuracy_m,
                    ST_Y(position) AS latitude, ST_X(position) AS longitude
               FROM geo_location_points WHERE $whereSql
           ORDER BY source_at DESC, id DESC LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['id'] = (string)$row['id'];
            $row['device_id'] = $row['device_id'] !== null ? (string)$row['device_id'] : null;
            $row['latitude'] = (float)$row['latitude'];
            $row['longitude'] = (float)$row['longitude'];
            $row['accuracy_m'] = $row['accuracy_m'] !== null ? (float)$row['accuracy_m'] : null;
            $items[] = $row;
        }
        return ['total' => $total, 'items' => $items, 'limit' => $limit, 'offset' => $offset, 'next_offset' => $offset + $limit < $total ? $offset + $limit : null];
    }

    /** @return array{total:int,items:list<array<string,mixed>>,limit:int,offset:int,next_offset:?int} */
    public static function trips(PDO $pdo, string $owner, array $filters): array
    {
        $limit = self::limit($filters['limit'] ?? null);
        $offset = self::offset($filters['offset'] ?? null);
        $range = self::range($filters['from'] ?? null, $filters['to'] ?? null);
        $where = ['user_email = :owner', 'deleted_at IS NULL'];
        $params = [':owner' => $owner];
        if ($range['from'] !== null) { $where[] = 'started_at >= :from'; $params[':from'] = $range['from']; }
        if ($range['to'] !== null) { $where[] = 'started_at <= :to'; $params[':to'] = $range['to']; }
        $whereSql = implode(' AND ', $where);
        $count = $pdo->prepare("SELECT COUNT(*) FROM geo_trips WHERE $whereSql");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT id, started_at, ended_at, distance_m, duration_s, title, notes, state, processor_version,
                    geometry_visible, ST_XMin(geometry) AS min_lon, ST_YMin(geometry) AS min_lat,
                    ST_XMax(geometry) AS max_lon, ST_YMax(geometry) AS max_lat
               FROM geo_trips WHERE $whereSql
           ORDER BY started_at DESC, id DESC LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = [
                'id' => (string)$row['id'],
                'started_at' => $row['started_at'],
                'ended_at' => $row['ended_at'],
                'distance_m' => (float)$row['distance_m'],
                'duration_s' => (int)$row['duration_s'],
                'title' => (string)$row['title'],
                'state' => (string)$row['state'],
                'processor_version' => (string)$row['processor_version'],
                'bbox' => $row['min_lon'] !== null ? [
                    'minLon' => (float)$row['min_lon'], 'minLat' => (float)$row['min_lat'],
                    'maxLon' => (float)$row['max_lon'], 'maxLat' => (float)$row['max_lat'],
                ] : null,
            ];
            $items[] = $item;
        }
        return ['total' => $total, 'items' => $items, 'limit' => $limit, 'offset' => $offset, 'next_offset' => $offset + $limit < $total ? $offset + $limit : null];
    }

    /** @return array<string,mixed> */
    public static function trip(PDO $pdo, string $owner, string $id): array
    {
        self::requireUuid($id);
        $stmt = $pdo->prepare(
            "SELECT id, started_at, ended_at, distance_m, duration_s, title, notes, state, processor_version, geometry_visible,
                    CASE WHEN geometry_visible THEN ST_AsGeoJSON(geometry) ELSE NULL END AS geometry
               FROM geo_trips WHERE user_email = :owner AND id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':owner' => $owner, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new StepsGeolocationApiException('not_found', 404);
        $row['id'] = (string)$row['id'];
        $row['distance_m'] = (float)$row['distance_m'];
        $row['duration_s'] = (int)$row['duration_s'];
        $row['geometry_visible'] = (bool)$row['geometry_visible'];
        $row['geometry'] = $row['geometry'] !== null ? json_decode((string)$row['geometry'], true) : null;
        return $row;
    }

    /** @return array{total:int,items:list<array<string,mixed>>,limit:int,offset:int,next_offset:?int} */
    public static function places(PDO $pdo, string $owner, array $filters): array
    {
        $limit = self::limit($filters['limit'] ?? null);
        $offset = self::offset($filters['offset'] ?? null);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM geo_places WHERE user_email = :owner AND deleted_at IS NULL');
        $stmt->execute([':owner' => $owner]);
        $total = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT id, name, tag, ST_Y(position) AS latitude, ST_X(position) AS longitude, radius_m, color, notes, timezone, is_active, created_at, updated_at
               FROM geo_places WHERE user_email = :owner AND deleted_at IS NULL
           ORDER BY is_active DESC, name ASC, id ASC LIMIT $limit OFFSET $offset"
        );
        $stmt->execute([':owner' => $owner]);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['id'] = (string)$row['id'];
            $row['latitude'] = (float)$row['latitude'];
            $row['longitude'] = (float)$row['longitude'];
            $row['radius_m'] = (float)$row['radius_m'];
            $row['is_active'] = (bool)$row['is_active'];
            $items[] = $row;
        }
        return ['total' => $total, 'items' => $items, 'limit' => $limit, 'offset' => $offset, 'next_offset' => $offset + $limit < $total ? $offset + $limit : null];
    }

    /** @return array{total:int,items:list<array<string,mixed>>,limit:int,offset:int,next_offset:?int} */
    public static function visits(PDO $pdo, string $owner, array $filters): array
    {
        $limit = self::limit($filters['limit'] ?? null);
        $offset = self::offset($filters['offset'] ?? null);
        $range = self::range($filters['from'] ?? null, $filters['to'] ?? null);
        $where = ['v.user_email = :owner'];
        $params = [':owner' => $owner];
        if ($range['from'] !== null) { $where[] = 'v.arrived_at >= :from'; $params[':from'] = $range['from']; }
        if ($range['to'] !== null) { $where[] = 'v.arrived_at <= :to'; $params[':to'] = $range['to']; }
        $whereSql = implode(' AND ', $where);
        $count = $pdo->prepare("SELECT COUNT(*) FROM geo_visits v WHERE $whereSql");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT v.id, v.place_id, v.arrived_at, v.departed_at, v.confidence, v.state, p.name AS place_name, p.tag AS place_tag
               FROM geo_visits v LEFT JOIN geo_places p ON p.id = v.place_id AND p.deleted_at IS NULL
              WHERE $whereSql ORDER BY v.arrived_at DESC, v.id DESC LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['id'] = (string)$row['id'];
            $row['place_id'] = $row['place_id'] !== null ? (string)$row['place_id'] : null;
            $row['confidence'] = $row['confidence'] !== null ? (float)$row['confidence'] : null;
            $items[] = $row;
        }
        return ['total' => $total, 'items' => $items, 'limit' => $limit, 'offset' => $offset, 'next_offset' => $offset + $limit < $total ? $offset + $limit : null];
    }

    public static function requireOwner(?string $rowOwner, string $owner): void
    {
        if ($rowOwner === null || strcasecmp(trim($rowOwner), trim($owner)) !== 0) {
            throw new StepsGeolocationApiException('not_found', 404);
        }
    }
}
