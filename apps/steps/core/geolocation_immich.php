<?php
/**
 * Immich adapter (task 21).
 *
 * This is the only place that talks to Immich. It uses the Immich HTTP API
 * only, keeps encrypted connection settings in PostgreSQL, and stores asset
 * references plus match provenance instead of copying photos into Steps.
 */
require_once __DIR__ . '/geolocation_domain.php';
require_once __DIR__ . '/geolocation_secrets.php';
require_once __DIR__ . '/geolocation_security.php';

final class StepsGeolocationImmichError extends RuntimeException
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

final class StepsGeolocationImmich
{
    public const DEFAULT_TIME_TOLERANCE_S = 300;
    public const DEFAULT_DISTANCE_TOLERANCE_M = 250;
    public const MAX_PAGE_SIZE = 100;
    public const MAX_PAGES = 20;

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    public static function normalizeUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            throw new StepsGeolocationImmichError('invalid_server_url');
        }
        if (isset($parts['user']) || isset($parts['pass'])) throw new StepsGeolocationImmichError('invalid_server_url');
        try {
            StepsGeolocationSecurity::validateOutboundUrl($url);
        } catch (StepsGeolocationSecurityError $e) {
            throw new StepsGeolocationImmichError('invalid_server_url');
        }
        return $url;
    }

    public static function options(?string $json = null): array
    {
        $saved = $json !== null ? (json_decode($json, true) ?: []) : [];
        $env = static function (string $key, float $default, float $min, float $max): float {
            $raw = getenv($key);
            return is_string($raw) && is_numeric($raw) ? max($min, min($max, (float)$raw)) : $default;
        };
        return [
            'time_tolerance_s' => max(30, min(3600, (int)($saved['time_tolerance_s'] ?? $env('GEO_IMMICH_TIME_TOLERANCE_S', self::DEFAULT_TIME_TOLERANCE_S, 30, 3600)))),
            'distance_tolerance_m' => max(10, min(2000, (int)($saved['distance_tolerance_m'] ?? $env('GEO_IMMICH_DISTANCE_TOLERANCE_M', self::DEFAULT_DISTANCE_TOLERANCE_M, 10, 2000)))),
        ];
    }

    public static function connection(PDO $pdo, string $owner): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM geo_immich_connections WHERE user_email = :owner');
        $stmt->execute([':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function shapeConnection(?array $row): ?array
    {
        if ($row === null) return null;
        return [
            'id' => (string)$row['id'],
            'state' => (string)$row['state'],
            'connected' => $row['state'] === 'active',
            'server_configured' => (string)($row['server_url_ciphertext'] ?? '') !== '',
            'credential_configured' => (string)($row['credential_ciphertext'] ?? '') !== '',
            'options' => self::options((string)($row['options_json'] ?? '{}')),
            'last_tested_at' => $row['last_tested_at'] ?? null,
            'last_sync_at' => $row['last_sync_at'] ?? null,
            'last_error' => (string)($row['last_error'] ?? ''),
            'disconnected_at' => $row['disconnected_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private static function audit(PDO $pdo, string $owner, string $actor, string $event, array $context): void
    {
        $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)')
            ->execute([':id' => self::uuid(), ':owner' => $owner, ':actor' => $actor, ':event' => $event, ':context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /** Prefer the compose-internal Immich URL for server-side fetches (no Cloudflare hairpin). */
    public static function requestBaseUrl(string $storedUrl = ''): string
    {
        $internal = rtrim(trim((string)getenv('IMMICH_INTERNAL_URL')), '/');
        if ($internal !== '' && preg_match('#^https?://#i', $internal)) return $internal;
        return rtrim(trim($storedUrl), '/');
    }

    private static function thumbCacheDir(string $owner): string
    {
        $base = rtrim((string)(getenv('APPDATA_BASE_DIR') ?: '/opt/ApplicationData'), '/');
        return $base . '/steps/immich-thumbs/' . substr(hash('sha256', strtolower($owner)), 0, 32);
    }

    private static function thumbCachePaths(string $owner, string $assetId, string $size): array
    {
        $dir = self::thumbCacheDir($owner);
        $key = preg_replace('/[^a-f0-9-]/i', '', $assetId) . '-' . ($size === 'preview' ? 'preview' : 'thumb');
        return ['dir' => $dir, 'bin' => $dir . '/' . $key . '.bin', 'meta' => $dir . '/' . $key . '.json'];
    }

    private static function readThumbCache(string $owner, string $assetId, string $size): ?array
    {
        $paths = self::thumbCachePaths($owner, $assetId, $size);
        if (!is_file($paths['bin']) || !is_file($paths['meta'])) return null;
        if (filemtime($paths['bin']) < time() - 86400 * 7) return null;
        $meta = json_decode((string)@file_get_contents($paths['meta']), true);
        $body = @file_get_contents($paths['bin']);
        if (!is_array($meta) || $body === false || $body === '') return null;
        return ['body' => $body, 'mime' => (string)($meta['mime'] ?? 'image/jpeg'), 'cached' => true];
    }

    private static function writeThumbCache(string $owner, string $assetId, string $size, string $body, string $mime): void
    {
        $paths = self::thumbCachePaths($owner, $assetId, $size);
        if (!is_dir($paths['dir'])) {
            @mkdir($paths['dir'], 0775, true);
        }
        @file_put_contents($paths['bin'], $body);
        @file_put_contents($paths['meta'], json_encode(['mime' => $mime, 'saved_at' => gmdate('c')], JSON_UNESCAPED_SLASHES));
    }

    private static function http(string $method, string $url, string $apiKey, ?array $json = null, int $maxBytes = 5242880): array
    {
        if (!function_exists('curl_init')) throw new StepsGeolocationImmichError('immich_http_unavailable', 500);
        $ch = curl_init($url);
        $headers = ['Accept: application/json', 'x-api-key: ' . $apiKey];
        $body = null;
        if ($json !== null) {
            $body = json_encode($json, JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }
        $secure = str_starts_with(strtolower($url), 'https://');
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_MAXFILESIZE => $maxBytes,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $secure,
            CURLOPT_SSL_VERIFYHOST => $secure ? 2 : 0,
            CURLOPT_POSTFIELDS => $body,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $mime = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        if ($response === false) throw new StepsGeolocationImmichError('immich_unreachable', 502);
        if (strlen((string)$response) > $maxBytes) throw new StepsGeolocationImmichError('immich_response_too_large', 502);
        return ['status' => $status, 'body' => (string)$response, 'mime' => $mime, 'error' => $error];
    }

    /** Test a candidate connection without persisting it. */
    public static function test(PDO $pdo, string $owner, ?string $url = null, ?string $apiKey = null): array
    {
        $existing = self::connection($pdo, $owner);
        $url = $url !== null && trim($url) !== '' ? self::normalizeUrl($url) : trim((string)steps_geo_decrypt((string)($existing['server_url_ciphertext'] ?? ''), $owner, 'server_url'));
        $apiKey = $apiKey !== null && trim($apiKey) !== '' ? trim($apiKey) : trim((string)steps_geo_decrypt((string)($existing['credential_ciphertext'] ?? ''), $owner, 'api_key'));
        if ($url === '' || $apiKey === '') throw new StepsGeolocationImmichError('immich_not_configured', 409);
        $response = self::http('GET', $url . '/api/server/about', $apiKey);
        if ($response['status'] === 401 || $response['status'] === 403) throw new StepsGeolocationImmichError('immich_unauthorized', 401);
        if ($response['status'] < 200 || $response['status'] >= 300) throw new StepsGeolocationImmichError('immich_test_failed', 502);
        $data = json_decode($response['body'], true);
        return ['ok' => true, 'server' => is_array($data) ? ($data['version'] ?? $data['name'] ?? 'Immich') : 'Immich'];
    }

    public static function configure(PDO $pdo, string $owner, string $url, string $apiKey, array $options = []): array
    {
        $url = self::normalizeUrl($url);
        $apiKey = trim($apiKey);
        if ($apiKey === '' || strlen($apiKey) > 2048) throw new StepsGeolocationImmichError('invalid_api_key');
        self::test($pdo, $owner, $url, $apiKey);
        $opts = self::options(json_encode($options));
        $existing = self::connection($pdo, $owner);
        $id = $existing ? (string)$existing['id'] : self::uuid();
        $stmt = $pdo->prepare(
            "INSERT INTO geo_immich_connections(id, user_email, server_url_ciphertext, credential_ciphertext, state, options_json, last_tested_at, disconnected_at, updated_at)
             VALUES(:id, :owner, :url, :key, 'active', :options, now(), NULL, now())
             ON CONFLICT (user_email) DO UPDATE SET server_url_ciphertext = EXCLUDED.server_url_ciphertext, credential_ciphertext = EXCLUDED.credential_ciphertext, state = 'active', options_json = EXCLUDED.options_json, last_tested_at = now(), last_error = '', disconnected_at = NULL, updated_at = now()"
        );
        $stmt->execute([
            ':id' => $id, ':owner' => $owner,
            ':url' => steps_geo_encrypt($url, $owner, 'server_url'),
            ':key' => steps_geo_encrypt($apiKey, $owner, 'api_key'),
            ':options' => json_encode($opts, JSON_UNESCAPED_SLASHES),
        ]);
        self::audit($pdo, $owner, $owner, 'immich.connected', ['connection_id' => $id, 'reconnect' => $existing !== null]);
        return self::shapeConnection(self::connection($pdo, $owner));
    }

    public static function rotate(PDO $pdo, string $owner, string $url, string $apiKey): array
    {
        return self::configure($pdo, $owner, $url, $apiKey);
    }

    public static function disconnect(PDO $pdo, string $owner): void
    {
        $existing = self::connection($pdo, $owner);
        if ($existing === null) throw new StepsGeolocationImmichError('immich_not_configured', 404);
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE geo_immich_connections SET state = 'disconnected', server_url_ciphertext = '', credential_ciphertext = '', disconnected_at = now(), updated_at = now() WHERE user_email = :owner")
                ->execute([':owner' => $owner]);
            $pdo->prepare('DELETE FROM geo_trip_photos WHERE user_email = :owner')->execute([':owner' => $owner]);
            $pdo->prepare('DELETE FROM geo_immich_assets WHERE user_email = :owner')->execute([':owner' => $owner]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, $owner, $owner, 'immich.disconnected', ['connection_id' => (string)$existing['id'], 'local_metadata_deleted' => true]);
    }

    public static function enqueue(PDO $pdo, string $owner, string $from, string $to): bool
    {
        $stmt = $pdo->prepare(
            "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, input_from, input_to, details_json)
             SELECT :id, :owner, 'immich', 'pending', 'immich-1', :from, :to, :details
              WHERE NOT EXISTS (SELECT 1 FROM geo_processor_runs WHERE user_email = :owner AND kind = 'immich' AND state IN ('pending','processing'))"
        );
        $stmt->execute([':id' => self::uuid(), ':owner' => $owner, ':from' => $from, ':to' => $to, ':details' => json_encode(['from' => $from, 'to' => $to], JSON_UNESCAPED_SLASHES)]);
        return $stmt->rowCount() > 0;
    }

    private static function jsonItems(array $response): array
    {
        $data = json_decode($response['body'], true);
        if (!is_array($data)) return [];
        $items = $data['assets']['items'] ?? $data['items'] ?? $data['assets'] ?? $data;
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    public static function parseAsset(array $asset): ?array
    {
        $id = trim((string)($asset['id'] ?? $asset['assetId'] ?? ''));
        if ($id === '') return null;
        $exif = is_array($asset['exifInfo'] ?? null) ? $asset['exifInfo'] : [];
        $captured = $exif['dateTimeOriginal'] ?? $asset['fileCreatedAt'] ?? $asset['createdAt'] ?? null;
        if (!is_string($captured) || strtotime($captured) === false) return null;
        $lat = $exif['latitude'] ?? $asset['latitude'] ?? null;
        $lon = $exif['longitude'] ?? $asset['longitude'] ?? null;
        $hasGps = is_numeric($lat) && is_numeric($lon) && StepsGeolocationDomain::isCoordinate((float)$lat, (float)$lon);
        return [
            'immich_asset_id' => $id,
            'captured_at' => gmdate('c', strtotime($captured)),
            'latitude' => $hasGps ? (float)$lat : null,
            'longitude' => $hasGps ? (float)$lon : null,
            'original_filename' => (string)($asset['originalFileName'] ?? $asset['originalPath'] ?? ''),
        ];
    }

    public static function matchPoint(PDO $pdo, string $owner, float $lat, float $lon, string $capturedAt, array $options): ?array
    {
        $tolerance = (int)$options['time_tolerance_s'];
        $distance = (float)$options['distance_tolerance_m'];
        $stmt = $pdo->prepare(
            "WITH target AS (
                 SELECT ST_SetSRID(ST_MakePoint(CAST(:lon AS double precision), CAST(:lat AS double precision)), 4326)::geography AS g,
                        CAST(:captured AS timestamptz) AS at
             )
             SELECT p.id, p.source_at, ST_Y(p.position) AS latitude, ST_X(p.position) AS longitude,
                    ST_Distance(p.position::geography, t.g) AS distance_m
               FROM geo_location_points p CROSS JOIN target t
              WHERE p.user_email = :owner AND p.quality_state <> 'rejected'
                AND ABS(EXTRACT(EPOCH FROM (p.source_at - t.at))) <= :tolerance
                AND ST_DWithin(p.position::geography, t.g, :radius)
           ORDER BY ABS(EXTRACT(EPOCH FROM (p.source_at - t.at))), distance_m
              LIMIT 1"
        );
        $stmt->execute([':owner' => $owner, ':lat' => $lat, ':lon' => $lon, ':captured' => $capturedAt, ':tolerance' => $tolerance, ':radius' => $distance]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return [
            'mode' => 'spatial_time',
            'point_id' => (string)$row['id'],
            'distance_m' => round((float)$row['distance_m'], 1),
            'time_delta_s' => (int)abs(strtotime((string)$row['source_at']) - strtotime($capturedAt)),
            'matched_at' => gmdate('c'),
        ];
    }

    private static function candidatePoint(PDO $pdo, string $owner, string $capturedAt, int $seconds = 900): ?array
    {
        $stmt = $pdo->prepare(
            "WITH target AS (SELECT CAST(:captured AS timestamptz) AS at)
             SELECT p.id, p.source_at, ST_Y(p.position) AS latitude, ST_X(p.position) AS longitude
               FROM geo_location_points p CROSS JOIN target t
              WHERE p.user_email = :owner AND p.quality_state = 'accepted'
                AND ABS(EXTRACT(EPOCH FROM (p.source_at - t.at))) <= :seconds
           ORDER BY ABS(EXTRACT(EPOCH FROM (p.source_at - t.at))) LIMIT 1"
        );
        $stmt->execute([':owner' => $owner, ':captured' => $capturedAt, ':seconds' => $seconds]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return ['mode' => 'time_candidate', 'point_id' => (string)$row['id'], 'latitude' => (float)$row['latitude'], 'longitude' => (float)$row['longitude'], 'time_delta_s' => (int)abs(strtotime((string)$row['source_at']) - strtotime($capturedAt)), 'matched_at' => gmdate('c')];
    }

    public static function sync(PDO $pdo, string $owner, ?string $from = null, ?string $to = null): array
    {
        $connection = self::connection($pdo, $owner);
        if ($connection === null || $connection['state'] !== 'active') throw new StepsGeolocationImmichError('immich_not_connected', 409);
        $storedUrl = trim((string)steps_geo_decrypt((string)$connection['server_url_ciphertext'], $owner, 'server_url'));
        $key = trim((string)steps_geo_decrypt((string)$connection['credential_ciphertext'], $owner, 'api_key'));
        if ($storedUrl === '' || $key === '') throw new StepsGeolocationImmichError('immich_credentials_unavailable', 409);
        $url = self::requestBaseUrl($storedUrl);
        $toAt = $to !== null && strtotime($to) !== false ? strtotime($to) : time();
        $fromAt = $from !== null && strtotime($from) !== false ? strtotime($from) : $toAt - 30 * 86400;
        if ($fromAt >= $toAt) throw new StepsGeolocationImmichError('invalid_range');
        $options = self::options((string)$connection['options_json']);
        $seen = [];
        $matched = 0;
        $unmatched = 0;
        $pages = 0;
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = self::http('POST', $url . '/api/search/metadata', $key, [
                'takenAfter' => gmdate('c', $fromAt), 'takenBefore' => gmdate('c', $toAt),
                'page' => $page, 'size' => self::MAX_PAGE_SIZE, 'withExif' => true,
            ]);
            if ($response['status'] < 200 || $response['status'] >= 300) throw new StepsGeolocationImmichError('immich_search_failed', 502);
            $items = self::jsonItems($response);
            if ($items === []) break;
            $pages++;
            foreach ($items as $raw) {
                $asset = self::parseAsset($raw);
                if ($asset === null) continue;
                $seen[] = $asset['immich_asset_id'];
                $match = null;
                $state = 'unmatched';
                if ($asset['latitude'] !== null && $asset['longitude'] !== null) {
                    $match = self::matchPoint($pdo, $owner, $asset['latitude'], $asset['longitude'], $asset['captured_at'], $options);
                    if ($match !== null) { $state = 'matched'; $matched++; } else { $unmatched++; }
                } else {
                    $candidate = self::candidatePoint($pdo, $owner, $asset['captured_at']);
                    if ($candidate !== null) $match = $candidate;
                    $unmatched++;
                }
                $matchJson = $match !== null ? $match : ['mode' => 'none', 'matched_at' => gmdate('c')];
                $stmt = $pdo->prepare(
                    "INSERT INTO geo_immich_assets(id, user_email, connection_id, immich_asset_id, captured_at, position, match_json, state, original_filename, match_distance_m, match_time_delta_s, match_point_id, last_seen_at, updated_at)
                     VALUES(:id, :owner, :connection, :asset, :captured, CASE WHEN CAST(:has_gps AS boolean) THEN ST_SetSRID(ST_MakePoint(CAST(:lon AS double precision), CAST(:lat AS double precision)), 4326) ELSE NULL END, :match, :state, :filename, :distance, :delta, :point, now(), now())
                     ON CONFLICT (connection_id, immich_asset_id) DO UPDATE SET captured_at = EXCLUDED.captured_at, position = EXCLUDED.position, match_json = EXCLUDED.match_json, state = EXCLUDED.state, original_filename = EXCLUDED.original_filename, match_distance_m = EXCLUDED.match_distance_m, match_time_delta_s = EXCLUDED.match_time_delta_s, match_point_id = EXCLUDED.match_point_id, last_seen_at = now(), updated_at = now(), deleted_at = NULL"
                );
                $stmt->execute([
                    ':id' => self::uuid(), ':owner' => $owner, ':connection' => $connection['id'], ':asset' => $asset['immich_asset_id'],
                    ':captured' => $asset['captured_at'], ':has_gps' => $asset['latitude'] !== null ? 'true' : 'false', ':lat' => $asset['latitude'], ':lon' => $asset['longitude'],
                    ':match' => json_encode($matchJson, JSON_UNESCAPED_SLASHES), ':state' => $state,
                    ':filename' => $asset['original_filename'],
                    ':distance' => $match['distance_m'] ?? null, ':delta' => $match['time_delta_s'] ?? null, ':point' => $match['point_id'] ?? null,
                ]);
            }
            if (count($items) < self::MAX_PAGE_SIZE) break;
        }
        if ($seen !== []) {
            $list = implode(',', array_map(static fn(string $id): string => $pdo->quote($id), array_slice(array_unique($seen), 0, 500)));
            $pdo->prepare("UPDATE geo_immich_assets SET state = 'unavailable', updated_at = now() WHERE connection_id = :connection AND user_email = :owner AND captured_at BETWEEN :from AND :to AND immich_asset_id NOT IN ($list) AND deleted_at IS NULL")
                ->execute([':connection' => $connection['id'], ':owner' => $owner, ':from' => gmdate('c', $fromAt), ':to' => gmdate('c', $toAt)]);
        }
        $pdo->prepare('UPDATE geo_immich_connections SET last_sync_at = now(), last_error = \'\', updated_at = now() WHERE user_email = :owner')->execute([':owner' => $owner]);
        self::audit($pdo, $owner, $owner, 'immich.synced', ['connection_id' => (string)$connection['id'], 'pages' => $pages, 'matched' => $matched, 'unmatched' => $unmatched, 'from' => gmdate('c', $fromAt), 'to' => gmdate('c', $toAt)]);
        return ['pages' => $pages, 'seen' => count($seen), 'matched' => $matched, 'unmatched' => $unmatched];
    }

    public static function writeGps(PDO $pdo, string $owner, string $assetId, string $pointId, bool $confirmed): void
    {
        if (!$confirmed) throw new StepsGeolocationImmichError('confirmation_required', 409);
        if (!StepsGeolocationDomain::isUuid($assetId) || !StepsGeolocationDomain::isUuid($pointId)) throw new StepsGeolocationImmichError('invalid_id');
        $stmt = $pdo->prepare('SELECT a.*, c.state AS connection_state, c.server_url_ciphertext, c.credential_ciphertext FROM geo_immich_assets a JOIN geo_immich_connections c ON c.id = a.connection_id WHERE a.id = :asset AND a.user_email = :owner AND a.deleted_at IS NULL');
        $stmt->execute([':asset' => $assetId, ':owner' => $owner]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$asset) throw new StepsGeolocationImmichError('asset_not_found', 404);
        if ($asset['connection_state'] !== 'active') throw new StepsGeolocationImmichError('immich_not_connected', 409);
        $point = $pdo->prepare('SELECT ST_Y(position) AS latitude, ST_X(position) AS longitude FROM geo_location_points WHERE id = :point AND user_email = :owner');
        $point->execute([':point' => $pointId, ':owner' => $owner]);
        $p = $point->fetch(PDO::FETCH_ASSOC);
        if (!$p) throw new StepsGeolocationImmichError('point_not_found', 404);
        $storedUrl = trim((string)steps_geo_decrypt((string)$asset['server_url_ciphertext'], $owner, 'server_url'));
        $key = trim((string)steps_geo_decrypt((string)$asset['credential_ciphertext'], $owner, 'api_key'));
        $url = self::requestBaseUrl($storedUrl);
        $response = self::http('PUT', $url . '/api/assets/' . rawurlencode((string)$asset['immich_asset_id']), $key, ['latitude' => (float)$p['latitude'], 'longitude' => (float)$p['longitude']]);
        if ($response['status'] < 200 || $response['status'] >= 300) throw new StepsGeolocationImmichError('immich_write_failed', 502);
        $pdo->prepare("UPDATE geo_immich_assets SET position = ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), state = 'matched', match_json = :match, match_point_id = :point, updated_at = now() WHERE id = :asset AND user_email = :owner")
            ->execute([':lon' => $p['longitude'], ':lat' => $p['latitude'], ':match' => json_encode(['mode' => 'user_writeback', 'point_id' => $pointId, 'written_at' => gmdate('c')], JSON_UNESCAPED_SLASHES), ':point' => $pointId, ':asset' => $assetId, ':owner' => $owner]);
        self::audit($pdo, $owner, $owner, 'immich.gps_written', ['asset_id' => $assetId, 'point_id' => $pointId]);
    }

    public static function thumbnail(PDO $pdo, string $owner, string $assetId, string $size = 'thumbnail'): ?array
    {
        if (!StepsGeolocationDomain::isUuid($assetId)) return null;
        $size = $size === 'preview' ? 'preview' : 'thumbnail';
        $cached = self::readThumbCache($owner, $assetId, $size);
        if ($cached !== null) return $cached;
        $stmt = $pdo->prepare("SELECT a.*, c.state AS connection_state, c.server_url_ciphertext, c.credential_ciphertext FROM geo_immich_assets a JOIN geo_immich_connections c ON c.id = a.connection_id WHERE a.id = :asset AND a.user_email = :owner AND a.deleted_at IS NULL AND a.state <> 'deleted' AND c.state = 'active'");
        $stmt->execute([':asset' => $assetId, ':owner' => $owner]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$asset) return null;
        $storedUrl = trim((string)steps_geo_decrypt((string)$asset['server_url_ciphertext'], $owner, 'server_url'));
        $key = trim((string)steps_geo_decrypt((string)$asset['credential_ciphertext'], $owner, 'api_key'));
        $url = self::requestBaseUrl($storedUrl);
        $response = self::http('GET', $url . '/api/assets/' . rawurlencode((string)$asset['immich_asset_id']) . '/thumbnail?size=' . rawurlencode($size), $key, null, 2097152);
        if ($response['status'] === 404 || $response['status'] === 410) {
            $pdo->prepare("UPDATE geo_immich_assets SET state = 'unavailable', updated_at = now() WHERE id = :asset AND user_email = :owner")->execute([':asset' => $assetId, ':owner' => $owner]);
            return null;
        }
        if ($response['status'] < 200 || $response['status'] >= 300) return null;
        $mime = $response['mime'] !== '' ? $response['mime'] : 'image/jpeg';
        if (str_contains($mime, ';')) $mime = trim(explode(';', $mime, 2)[0]);
        self::writeThumbCache($owner, $assetId, $size, $response['body'], $mime);
        return ['body' => $response['body'], 'mime' => $mime, 'cached' => false];
    }

    public static function mapClusters(PDO $pdo, string $owner, array $bbox, string $from, string $to, int $zoom, int $limit = 60): array
    {
        $connection = self::connection($pdo, $owner);
        if ($connection === null || $connection['state'] !== 'active') return [];
        $zoom = max(1, min(22, $zoom));
        if ($zoom < 11) $limit = min($limit, 18);
        elseif ($zoom < 14) $limit = min($limit, 36);
        else $limit = min($limit, 60);
        $precision = max(1, min(6, 7 - (int)floor($zoom / 3)));
        $divisor = $zoom < 12 ? 8 : ($zoom < 15 ? 14 : 20);
        $cell = max(0.0008, round(360.0 / (2 ** max(1, min(20, $zoom))) / $divisor, 6));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total, round(ST_Y(ST_Centroid(ST_Collect(position)))::numeric, $precision) AS lat,
                    round(ST_X(ST_Centroid(ST_Collect(position)))::numeric, $precision) AS lon,
                    (array_agg(id ORDER BY captured_at DESC))[1] AS asset_id,
                    (array_agg(immich_asset_id ORDER BY captured_at DESC))[1] AS immich_asset_id,
                    (array_agg(original_filename ORDER BY captured_at DESC))[1] AS original_filename,
                    (array_agg(captured_at ORDER BY captured_at DESC))[1] AS captured_at
               FROM geo_immich_assets
              WHERE user_email = :owner AND state = 'matched' AND deleted_at IS NULL AND position IS NOT NULL
                AND position && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
                AND captured_at BETWEEN :from AND :to
           GROUP BY ST_SnapToGrid(position, $cell) ORDER BY total DESC LIMIT $limit"
        );
        $stmt->execute([':owner' => $owner, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat'], ':from' => $from, ':to' => $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function timelinePhotos(PDO $pdo, string $owner, string $from, string $to, int $limit = 30): array
    {
        $connection = self::connection($pdo, $owner);
        if ($connection === null || $connection['state'] !== 'active') return [];
        $limit = max(1, min(60, $limit));
        $stmt = $pdo->prepare("SELECT id, captured_at, original_filename, ST_Y(position) AS latitude, ST_X(position) AS longitude FROM geo_immich_assets WHERE user_email = :owner AND state = 'matched' AND deleted_at IS NULL AND captured_at BETWEEN :from AND :to ORDER BY captured_at ASC LIMIT $limit");
        $stmt->execute([':owner' => $owner, ':from' => $from, ':to' => $to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function galleries(PDO $pdo, string $owner, int $limit = 60): array
    {
        $connection = self::connection($pdo, $owner);
        if ($connection === null || $connection['state'] !== 'active') return ['assets' => [], 'places' => [], 'trips' => []];
        $limit = max(1, min(200, $limit));
        $stmt = $pdo->prepare(
            "SELECT a.id, a.immich_asset_id, a.captured_at, a.original_filename, a.state, a.match_json,
                    ST_Y(a.position) AS latitude, ST_X(a.position) AS longitude,
                    (SELECT p.name FROM geo_places p WHERE p.user_email = a.user_email AND p.deleted_at IS NULL AND ST_DWithin(a.position::geography, p.position::geography, p.radius_m) ORDER BY p.name LIMIT 1) AS place_name,
                    (SELECT t.title FROM geo_trips t WHERE t.user_email = a.user_email AND t.deleted_at IS NULL AND a.captured_at BETWEEN t.started_at AND t.ended_at ORDER BY t.started_at DESC LIMIT 1) AS trip_title
               FROM geo_immich_assets a
              WHERE a.user_email = :owner AND a.deleted_at IS NULL AND a.state IN ('matched','unmatched')
           ORDER BY a.captured_at DESC LIMIT $limit"
        );
        $stmt->execute([':owner' => $owner]);
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $places = [];
        $trips = [];
        foreach ($assets as &$asset) {
            $asset['match_json'] = json_decode((string)$asset['match_json'], true) ?: [];
            if (!empty($asset['place_name'])) $places[$asset['place_name']] = ($places[$asset['place_name']] ?? 0) + 1;
            if (!empty($asset['trip_title'])) $trips[$asset['trip_title']] = ($trips[$asset['trip_title']] ?? 0) + 1;
        }
        unset($asset);
        return ['assets' => $assets, 'places' => $places, 'trips' => $trips];
    }
}
