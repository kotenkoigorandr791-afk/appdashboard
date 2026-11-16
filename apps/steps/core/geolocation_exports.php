<?php
/**
 * Export and account backup jobs (task 20).
 *
 * Jobs and artifact ownership live in shared PostgreSQL; files live on the
 * existing APPDATA volume. Queries stream rows from PostgreSQL and serializers
 * write directly to the artifact, so a large history never has to fit in PHP
 * memory. GeoJSON and GPX exports can be fed back into the import pipeline.
 */
require_once __DIR__ . '/geolocation_domain.php';

final class StepsGeolocationExportError extends RuntimeException
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

final class StepsGeolocationExports
{
    public const PROCESSOR_VERSION = 'exports-1';
    public const SCHEMA_VERSION = 'geolocation-export-1';
    public const FORMATS = ['json', 'geojson', 'gpx', 'backup'];
    public const SCOPES = ['all', 'date', 'device', 'trip', 'place'];

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    public static function ttlSeconds(): int
    {
        $raw = getenv('GEO_EXPORT_TTL_HOURS');
        $hours = is_string($raw) && is_numeric($raw) ? (float)$raw : 168.0;
        return (int)(max(1.0, min(720.0, $hours)) * 3600);
    }

    public static function baseDir(): string
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        $helper = dirname(__DIR__, 3) . '/storage_helper.php';
        if (is_file($helper)) require_once $helper;
        if (function_exists('appdata_app_base_dir')) {
            try { $cached = rtrim(appdata_app_base_dir('steps'), '/') . '/geo_exports'; return $cached; } catch (Throwable $e) {}
        }
        $cached = dirname(__DIR__) . '/upload_sessions/geo_exports';
        return $cached;
    }

    public static function userDir(string $owner): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._@-]/', '_', $owner) ?: 'user';
        return self::baseDir() . '/' . $safe;
    }

    public static function contentType(string $format): string
    {
        return [
            'json' => 'application/json; charset=utf-8',
            'geojson' => 'application/geo+json; charset=utf-8',
            'gpx' => 'application/gpx+xml; charset=utf-8',
            'backup' => 'application/json; charset=utf-8',
        ][$format] ?? 'application/octet-stream';
    }

    public static function extension(string $format): string
    {
        return $format === 'geojson' ? 'geojson' : ($format === 'gpx' ? 'gpx' : 'json');
    }

    public static function validateScope(PDO $pdo, string $owner, array $scope): array
    {
        $type = strtolower(trim((string)($scope['type'] ?? 'all')));
        if (!in_array($type, self::SCOPES, true)) throw new StepsGeolocationExportError('invalid_scope');
        $clean = ['type' => $type, 'include_derived' => !empty($scope['include_derived'])];
        if ($type === 'date') {
            $from = trim((string)($scope['from'] ?? ''));
            $to = trim((string)($scope['to'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from .= 'T00:00:00Z';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to .= 'T23:59:59Z';
            if (strtotime($from) === false || strtotime($to) === false || strtotime($from) > strtotime($to)) throw new StepsGeolocationExportError('invalid_range');
            $clean['from'] = gmdate('c', strtotime($from));
            $clean['to'] = gmdate('c', strtotime($to));
        } elseif ($type === 'device') {
            $id = trim((string)($scope['device_id'] ?? ''));
            if (!StepsGeolocationDomain::isUuid($id)) throw new StepsGeolocationExportError('invalid_device');
            $stmt = $pdo->prepare('SELECT id FROM geo_devices WHERE user_email = :owner AND id = :id');
            $stmt->execute([':owner' => $owner, ':id' => $id]);
            if (!$stmt->fetchColumn()) throw new StepsGeolocationExportError('device_not_found', 404);
            $clean['device_id'] = $id;
        } elseif ($type === 'trip') {
            $id = trim((string)($scope['trip_id'] ?? ''));
            if (!StepsGeolocationDomain::isUuid($id)) throw new StepsGeolocationExportError('invalid_trip');
            $stmt = $pdo->prepare('SELECT id FROM geo_trips WHERE user_email = :owner AND id = :id AND deleted_at IS NULL');
            $stmt->execute([':owner' => $owner, ':id' => $id]);
            if (!$stmt->fetchColumn()) throw new StepsGeolocationExportError('trip_not_found', 404);
            $clean['trip_id'] = $id;
        } elseif ($type === 'place') {
            $id = trim((string)($scope['place_id'] ?? ''));
            if (!StepsGeolocationDomain::isUuid($id)) throw new StepsGeolocationExportError('invalid_place');
            $stmt = $pdo->prepare('SELECT id FROM geo_places WHERE user_email = :owner AND id = :id AND deleted_at IS NULL');
            $stmt->execute([':owner' => $owner, ':id' => $id]);
            if (!$stmt->fetchColumn()) throw new StepsGeolocationExportError('place_not_found', 404);
            $clean['place_id'] = $id;
        }
        return $clean;
    }

    public static function enqueue(PDO $pdo, string $owner, string $format, array $scope): array
    {
        $format = strtolower(trim($format));
        if (!in_array($format, self::FORMATS, true)) throw new StepsGeolocationExportError('invalid_format');
        $cleanScope = $format === 'backup' ? ['type' => 'all', 'include_derived' => true] : self::validateScope($pdo, $owner, $scope);
        $id = self::uuid();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO geo_exports(id, user_email, format, scope_json, state, options_json) VALUES(:id, :owner, :format, :scope, 'pending', :options)");
            $stmt->execute([':id' => $id, ':owner' => $owner, ':format' => $format, ':scope' => json_encode($cleanScope, JSON_UNESCAPED_SLASHES), ':options' => json_encode(['ttl_seconds' => self::ttlSeconds()], JSON_UNESCAPED_SLASHES)]);
            $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, details_json) VALUES(:id, :owner, 'export', 'pending', :version, :details)")
                ->execute([':id' => self::uuid(), ':owner' => $owner, ':version' => self::PROCESSOR_VERSION, ':details' => json_encode(['export_id' => $id], JSON_UNESCAPED_SLASHES)]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        self::audit($pdo, $owner, 'export.created', ['export_id' => $id, 'format' => $format, 'scope' => $cleanScope]);
        return self::find($pdo, $owner, $id);
    }

    public static function find(PDO $pdo, string $owner, string $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM geo_exports WHERE id = :id AND user_email = :owner');
        $stmt->execute([':id' => $id, ':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::shape($pdo, $owner, $row) : null;
    }

    public static function history(PDO $pdo, string $owner, int $limit = 25): array
    {
        $stmt = $pdo->prepare("SELECT * FROM geo_exports WHERE user_email = :owner ORDER BY created_at DESC LIMIT $limit");
        $stmt->execute([':owner' => $owner]);
        return array_map(static fn(array $row): array => self::shape($pdo, $owner, $row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function shape(PDO $pdo, string $owner, array $row): array
    {
        $stmt = $pdo->prepare('SELECT id, kind, format, filename, size_bytes, checksum, expires_at, download_count, last_downloaded_at, deleted_at FROM geo_artifacts WHERE export_id = :export AND user_email = :owner ORDER BY created_at DESC');
        $stmt->execute([':export' => $row['id'], ':owner' => $owner]);
        $artifacts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $artifact) {
            $artifact['size_bytes'] = (int)$artifact['size_bytes'];
            $artifact['download_count'] = (int)$artifact['download_count'];
            $artifact['available'] = $artifact['deleted_at'] === null
                && $artifact['expires_at'] !== null && strtotime((string)$artifact['expires_at']) > time();
            $artifacts[] = $artifact;
        }
        return [
            'id' => (string)$row['id'],
            'format' => (string)$row['format'],
            'state' => (string)$row['state'],
            'scope' => json_decode((string)$row['scope_json'], true) ?: [],
            'errors' => json_decode((string)($row['error_json'] ?? '{}'), true) ?: [],
            'created_at' => $row['created_at'] ?? null,
            'started_at' => $row['started_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'artifacts' => $artifacts,
        ];
    }

    /** Stream export rows. Never fetches the full history. */
    private static function pointRows(PDO $pdo, string $owner, array $scope): PDOStatement
    {
        $sql = "SELECT p.id, p.device_id, p.source_type, p.source_at, p.received_at, p.source_offset_minutes,
                       p.accuracy_m, p.altitude_m, p.speed_mps, p.bearing_degrees, p.quality_state, p.quality_reason,
                       ST_Y(p.position) AS latitude, ST_X(p.position) AS longitude
                  FROM geo_location_points p
                 WHERE p.user_email = :owner";
        $params = [':owner' => $owner];
        $type = (string)($scope['type'] ?? 'all');
        if ($type === 'date') {
            $sql .= ' AND p.source_at BETWEEN :from AND :to';
            $params[':from'] = $scope['from'];
            $params[':to'] = $scope['to'];
        } elseif ($type === 'device') {
            $sql .= ' AND p.device_id = :device';
            $params[':device'] = $scope['device_id'];
        } elseif ($type === 'trip') {
            $trip = $pdo->prepare('SELECT started_at, ended_at, override_json FROM geo_trips WHERE user_email = :owner AND id = :id AND deleted_at IS NULL');
            $trip->execute([':owner' => $owner, ':id' => $scope['trip_id']]);
            $row = $trip->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new StepsGeolocationExportError('trip_not_found', 404);
            $override = json_decode((string)$row['override_json'], true) ?: [];
            $pointIds = array_values(array_filter((array)($override['point_ids'] ?? []), static fn($id): bool => is_string($id) && StepsGeolocationDomain::isUuid($id)));
            if ($pointIds !== []) {
                $sql .= ' AND p.id IN (' . implode(',', array_map(static fn(string $id): string => $pdo->quote($id), $pointIds)) . ')';
            } else {
                $sql .= ' AND p.source_at BETWEEN :from AND :to';
                $params[':from'] = $row['started_at'];
                $params[':to'] = $row['ended_at'];
            }
        } elseif ($type === 'place') {
            $sql .= ' AND EXISTS (SELECT 1 FROM geo_places pl WHERE pl.user_email = p.user_email AND pl.id = :place AND pl.deleted_at IS NULL AND ST_DWithin(p.position::geography, pl.position::geography, pl.radius_m))';
            $params[':place'] = $scope['place_id'];
        }
        $sql .= ' ORDER BY p.source_at ASC, p.id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private static function pointArray(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'source_at' => $row['source_at'],
            'received_at' => $row['received_at'],
            'source_offset_minutes' => $row['source_offset_minutes'] !== null ? (int)$row['source_offset_minutes'] : null,
            'source_type' => (string)$row['source_type'],
            'device_id' => $row['device_id'],
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'accuracy_m' => $row['accuracy_m'] !== null ? (float)$row['accuracy_m'] : null,
            'altitude_m' => $row['altitude_m'] !== null ? (float)$row['altitude_m'] : null,
            'speed_mps' => $row['speed_mps'] !== null ? (float)$row['speed_mps'] : null,
            'bearing_degrees' => $row['bearing_degrees'] !== null ? (float)$row['bearing_degrees'] : null,
            'quality_state' => (string)$row['quality_state'],
            'quality_reason' => (string)$row['quality_reason'],
        ];
    }

    private static function gpxPoint(array $row): string
    {
        $lat = number_format((float)$row['latitude'], 7, '.', '');
        $lon = number_format((float)$row['longitude'], 7, '.', '');
        $xml = '<trkpt lat="' . $lat . '" lon="' . $lon . '">';
        if ($row['altitude_m'] !== null) $xml .= '<ele>' . number_format((float)$row['altitude_m'], 2, '.', '') . '</ele>';
        $xml .= '<time>' . gmdate('c', strtotime((string)$row['source_at'])) . '</time>';
        if ($row['accuracy_m'] !== null) $xml .= '<hdop>' . number_format((float)$row['accuracy_m'] / 5.0, 2, '.', '') . '</hdop>';
        $xml .= '</trkpt>';
        return $xml;
    }

    private static function geoJsonFeature(array $row): string
    {
        $properties = [
            'id' => (string)$row['id'],
            'layer' => 'point',
            'time' => gmdate('c', strtotime((string)$row['source_at'])),
            'source_at' => $row['source_at'],
            'source_type' => (string)$row['source_type'],
            'device_id' => $row['device_id'],
            'accuracy' => $row['accuracy_m'] !== null ? (float)$row['accuracy_m'] : null,
            'altitude' => $row['altitude_m'] !== null ? (float)$row['altitude_m'] : null,
            'quality_state' => (string)$row['quality_state'],
        ];
        return json_encode([
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [(float)$row['longitude'], (float)$row['latitude']]],
            'properties' => $properties,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array{path:string,size:int,checksum:string} */
    private static function writeArtifact(PDO $pdo, string $owner, array $export, string $dir): array
    {
        $format = (string)$export['format'];
        $scope = json_decode((string)$export['scope_json'], true) ?: [];
        $filename = 'steps-' . $format . '-' . gmdate('Ymd-His') . '-' . substr($export['id'], 0, 8) . '.' . self::extension($format);
        $path = $dir . '/' . $filename;
        $tmp = $path . '.part';
        $fh = fopen($tmp, 'wb');
        if (!$fh) throw new RuntimeException('artifact_storage_unavailable');
        $hash = hash_init('sha256');
        $size = 0;
        $write = static function (string $data) use ($fh, $hash, &$size): void {
            if ($data === '') return;
            $written = fwrite($fh, $data);
            if ($written === false) throw new RuntimeException('artifact_write_failed');
            $size += $written;
            hash_update($hash, $data);
        };
        try {
            if ($format === 'gpx') {
                $write('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
                $write('<gpx version="1.1" creator="AppDashboard Steps" xmlns="http://www.topografix.com/GPX/1/1">' . "\n");
                $write('<metadata><name>Steps location export</name><time>' . gmdate('c') . '</time></metadata>' . "\n<trk><name>Steps history</name><trkseg>\n");
                $rows = self::pointRows($pdo, $owner, $scope);
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) $write(self::gpxPoint($row));
                $write("</trkseg></trk>\n</gpx>\n");
            } elseif ($format === 'geojson') {
                $write('{"type":"FeatureCollection","name":"Steps location export","features":[');
                $rows = self::pointRows($pdo, $owner, $scope);
                $first = true;
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    if (!$first) $write(',');
                    $first = false;
                    $write(self::geoJsonFeature($row));
                }
                $write(']}');
            } elseif ($format === 'json') {
                $write('{"schema_version":' . json_encode(self::SCHEMA_VERSION) . ',"format":"json","timezone":"UTC","exported_at":' . json_encode(gmdate('c')) . ',"scope":' . json_encode($scope, JSON_UNESCAPED_SLASHES) . ',"fields":' . json_encode([
                    'id' => 'canonical point UUID', 'source_at' => 'UTC source timestamp', 'received_at' => 'UTC server receipt timestamp',
                    'source_type' => 'mobile|import|api', 'latitude' => 'WGS84 degrees', 'longitude' => 'WGS84 degrees',
                    'accuracy_m' => 'horizontal accuracy in metres or null', 'altitude_m' => 'metres or null',
                    'quality_state' => 'pending|accepted|flagged|rejected', 'speed_mps' => 'descriptive only, never a transport class',
                ], JSON_UNESCAPED_SLASHES) . ',"points":[');
                $rows = self::pointRows($pdo, $owner, $scope);
                $first = true;
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    if (!$first) $write(',');
                    $first = false;
                    $write(json_encode(self::pointArray($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
                $write(']');
                if (!empty($scope['include_derived'])) {
                    $write(',"derived":' . json_encode(self::derived($pdo, $owner, $scope), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
                $write('}');
            } else {
                $write('{"schema_version":' . json_encode(self::SCHEMA_VERSION) . ',"kind":"backup","timezone":"UTC","exported_at":' . json_encode(gmdate('c')) . ',"points":[');
                $rows = self::pointRows($pdo, $owner, $scope);
                $first = true;
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    if (!$first) $write(',');
                    $first = false;
                    $write(json_encode(self::pointArray($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
                $write('],"account":' . json_encode(self::accountBackup($pdo, $owner), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                $write('}');
            }
            fclose($fh);
            $checksum = hash_final($hash);
            if (!@rename($tmp, $path)) throw new RuntimeException('artifact_finalize_failed');
            return ['path' => $path, 'filename' => $filename, 'size' => $size, 'checksum' => $checksum];
        } catch (Throwable $e) {
            if (is_resource($fh)) fclose($fh);
            @unlink($tmp);
            throw $e;
        }
    }

    public static function process(PDO $pdo, string $owner, string $exportId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM geo_exports WHERE id = :id AND user_email = :owner');
        $stmt->execute([':id' => $exportId, ':owner' => $owner]);
        $export = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$export) throw new StepsGeolocationExportError('export_not_found', 404);
        if (in_array((string)$export['state'], ['ready', 'processing', 'deleted'], true)) return self::find($pdo, $owner, $exportId);
        $pdo->prepare("UPDATE geo_exports SET state = 'processing', started_at = now(), updated_at = now(), error_json = '{}'::jsonb WHERE id = :id")->execute([':id' => $exportId]);
        $dir = self::userDir($owner) . '/' . $exportId;
        try {
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('artifact_storage_unavailable');
            $artifact = self::writeArtifact($pdo, $owner, $export, $dir);
            $expires = gmdate('c', time() + self::ttlSeconds());
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO geo_artifacts(id, user_email, export_id, kind, storage_path, checksum, expires_at, size_bytes, format, filename, content_type) VALUES(:id, :owner, :export, 'export', :path, :checksum, :expires, :size, :format, :filename, :content)")
                    ->execute([':id' => self::uuid(), ':owner' => $owner, ':export' => $exportId, ':path' => $artifact['path'], ':checksum' => $artifact['checksum'], ':expires' => $expires, ':size' => $artifact['size'], ':format' => $export['format'], ':filename' => $artifact['filename'], ':content' => self::contentType((string)$export['format'])]);
                $pdo->prepare("UPDATE geo_exports SET state = 'ready', completed_at = now(), expires_at = :expires, updated_at = now() WHERE id = :id")
                    ->execute([':expires' => $expires, ':id' => $exportId]);
                $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE user_email = :owner AND kind = 'export' AND state IN ('pending','processing') AND details_json->>'export_id' = :export")
                    ->execute([':details' => json_encode(['export_id' => $exportId, 'size_bytes' => $artifact['size']], JSON_UNESCAPED_SLASHES), ':owner' => $owner, ':export' => $exportId]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            self::audit($pdo, $owner, 'export.completed', ['export_id' => $exportId, 'format' => $export['format'], 'size_bytes' => $artifact['size'], 'checksum' => $artifact['checksum'], 'expires_at' => $expires]);
            return self::find($pdo, $owner, $exportId);
        } catch (Throwable $e) {
            @unlink($artifact['path'] ?? '');
            $pdo->prepare("UPDATE geo_exports SET state = 'failed', error_json = :error, updated_at = now() WHERE id = :id")
                ->execute([':error' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_SLASHES), ':id' => $exportId]);
            $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', updated_at = now() WHERE user_email = :owner AND kind = 'export' AND details_json->>'export_id' = :export")
                ->execute([':owner' => $owner, ':export' => $exportId]);
            self::audit($pdo, $owner, 'export.failed', ['export_id' => $exportId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /** Authorize at download time and verify the stored checksum. */
    public static function download(PDO $pdo, string $owner, string $artifactId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT a.id, a.export_id, a.storage_path, a.filename, a.content_type, a.checksum, a.size_bytes, a.expires_at, e.format
               FROM geo_artifacts a JOIN geo_exports e ON e.id = a.export_id
              WHERE a.id = :id AND a.user_email = :owner AND e.user_email = :owner
                AND a.deleted_at IS NULL AND e.state = 'ready'
                AND a.expires_at IS NOT NULL AND a.expires_at > now()"
        );
        $stmt->execute([':id' => $artifactId, ':owner' => $owner]);
        $artifact = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$artifact) return null;
        if (!is_file((string)$artifact['storage_path'])) return null;
        if (!hash_equals((string)$artifact['checksum'], (string)hash_file('sha256', (string)$artifact['storage_path']))) {
            self::deleteArtifact($pdo, $owner, $artifactId);
            return null;
        }
        $pdo->prepare('UPDATE geo_artifacts SET download_count = download_count + 1, last_downloaded_at = now() WHERE id = :id AND user_email = :owner')
            ->execute([':id' => $artifactId, ':owner' => $owner]);
        self::audit($pdo, $owner, 'export.downloaded', ['artifact_id' => $artifactId, 'export_id' => $artifact['export_id'], 'format' => $artifact['format']]);
        return $artifact;
    }

    public static function deleteArtifact(PDO $pdo, string $owner, string $artifactId): bool
    {
        $stmt = $pdo->prepare('SELECT id, export_id, storage_path FROM geo_artifacts WHERE id = :id AND user_email = :owner AND deleted_at IS NULL');
        $stmt->execute([':id' => $artifactId, ':owner' => $owner]);
        $artifact = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$artifact) return false;
        if ($artifact['storage_path'] !== '' && is_file($artifact['storage_path'])) @unlink($artifact['storage_path']);
        $pdo->prepare('UPDATE geo_artifacts SET deleted_at = now() WHERE id = :id AND user_email = :owner')->execute([':id' => $artifactId, ':owner' => $owner]);
        $pdo->prepare("UPDATE geo_exports SET state = 'deleted', updated_at = now() WHERE id = :id AND user_email = :owner")->execute([':id' => $artifact['export_id'], ':owner' => $owner]);
        self::audit($pdo, $owner, 'export.deleted', ['export_id' => $artifact['export_id'], 'artifact_id' => $artifactId]);
        return true;
    }

    public static function deleteExport(PDO $pdo, string $owner, string $exportId): bool
    {
        $stmt = $pdo->prepare('SELECT id, storage_path FROM geo_artifacts WHERE export_id = :export AND user_email = :owner AND deleted_at IS NULL');
        $stmt->execute([':export' => $exportId, ':owner' => $owner]);
        $artifacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($artifacts === []) {
            $exists = $pdo->prepare('SELECT 1 FROM geo_exports WHERE id = :id AND user_email = :owner');
            $exists->execute([':id' => $exportId, ':owner' => $owner]);
            if (!$exists->fetchColumn()) return false;
        }
        foreach ($artifacts as $artifact) {
            if ($artifact['storage_path'] !== '' && is_file($artifact['storage_path'])) @unlink($artifact['storage_path']);
            $pdo->prepare('UPDATE geo_artifacts SET deleted_at = now() WHERE id = :id AND user_email = :owner')->execute([':id' => $artifact['id'], ':owner' => $owner]);
        }
        $pdo->prepare("UPDATE geo_exports SET state = 'deleted', updated_at = now() WHERE id = :id AND user_email = :owner")->execute([':id' => $exportId, ':owner' => $owner]);
        self::audit($pdo, $owner, 'export.deleted', ['export_id' => $exportId]);
        return true;
    }

    public static function cleanupExpired(PDO $pdo): int
    {
        $stmt = $pdo->query("SELECT id, user_email, storage_path FROM geo_artifacts WHERE deleted_at IS NULL AND expires_at IS NOT NULL AND expires_at <= now() LIMIT 100");
        $count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $artifact) {
            if ($artifact['storage_path'] !== '' && is_file($artifact['storage_path'])) @unlink($artifact['storage_path']);
            $pdo->prepare('UPDATE geo_artifacts SET deleted_at = now() WHERE id = :id')->execute([':id' => $artifact['id']]);
            $pdo->prepare("UPDATE geo_exports SET state = 'stale', updated_at = now() WHERE id = (SELECT export_id FROM geo_artifacts WHERE id = :id) AND state = 'ready'")->execute([':id' => $artifact['id']]);
            self::audit($pdo, (string)$artifact['user_email'], 'export.expired', ['artifact_id' => $artifact['id']]);
            $count++;
        }
        return $count;
    }

    private static function derived(PDO $pdo, string $owner, array $scope): array
    {
        $derived = ['trips' => [], 'places' => [], 'visits' => []];
        $params = [':owner' => $owner];
        $tripSql = 'SELECT id, title, notes, started_at, ended_at, distance_m, duration_s, boundary_reason, state, geometry_visible, ST_AsGeoJSON(geometry) AS geometry FROM geo_trips WHERE user_email = :owner AND deleted_at IS NULL';
        if (($scope['type'] ?? '') === 'trip') { $tripSql .= ' AND id = :trip'; $params[':trip'] = $scope['trip_id']; }
        $tripSql .= ' ORDER BY started_at';
        $stmt = $pdo->prepare($tripSql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['geometry_visible'] = !in_array($row['geometry_visible'], [false, 0, '0', 'f', 'false'], true);
            $row['geometry'] = $row['geometry'] !== null ? json_decode((string)$row['geometry'], true) : null;
            $derived['trips'][] = $row;
        }
        $stmt = $pdo->prepare('SELECT id, name, tag, ST_Y(position) AS latitude, ST_X(position) AS longitude, radius_m, color, notes, timezone, is_active FROM geo_places WHERE user_email = :owner AND deleted_at IS NULL');
        $stmt->execute([':owner' => $owner]);
        $derived['places'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $visitSql = "SELECT v.id, v.place_id, v.stop_id, v.arrived_at, v.departed_at, v.confidence, p.name AS place_name
                       FROM geo_visits v LEFT JOIN geo_places p ON p.id = v.place_id
                      WHERE v.user_email = :owner";
        $vParams = [':owner' => $owner];
        if (($scope['type'] ?? '') === 'date') {
            $visitSql .= ' AND v.arrived_at BETWEEN :from AND :to';
            $vParams[':from'] = $scope['from'];
            $vParams[':to'] = $scope['to'];
        }
        $visitSql .= ' ORDER BY v.arrived_at';
        $stmt = $pdo->prepare($visitSql);
        $stmt->execute($vParams);
        $derived['visits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $derived;
    }

    private static function accountBackup(PDO $pdo, string $owner): array
    {
        $settings = [];
        try {
            $stmt = $pdo->prepare('SELECT key, value FROM steps_settings WHERE user_email = :owner');
            $stmt->execute([':owner' => $owner]);
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('backup settings: ' . $e->getMessage());
        }
        $list = static function (PDO $pdo, string $sql, array $params): array {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };
        $places = $list($pdo, 'SELECT id, name, tag, ST_Y(position) AS latitude, ST_X(position) AS longitude, radius_m, color, notes, timezone, is_active, created_at, updated_at FROM geo_places WHERE user_email = :owner ORDER BY created_at', [':owner' => $owner]);
        $trips = $list($pdo, "SELECT id, title, notes, started_at, ended_at, distance_m, duration_s, boundary_reason, state, geometry_visible, override_json, ST_AsGeoJSON(geometry) AS geometry, created_at, updated_at FROM geo_trips WHERE user_email = :owner AND deleted_at IS NULL ORDER BY started_at", [':owner' => $owner]);
        foreach ($trips as &$trip) {
            $trip['override_json'] = json_decode((string)$trip['override_json'], true) ?: [];
            $trip['geometry'] = $trip['geometry'] !== null ? json_decode((string)$trip['geometry'], true) : null;
            $trip['geometry_visible'] = !in_array($trip['geometry_visible'], [false, 0, '0', 'f', 'false'], true);
        }
        unset($trip);
        $groups = $list($pdo, 'SELECT DISTINCT g.id, g.owner_email, g.name, g.created_at, g.updated_at FROM geo_family_groups g LEFT JOIN geo_family_members gm ON gm.group_id = g.id WHERE g.owner_email = :owner OR gm.user_email = :member', [':owner' => $owner, ':member' => $owner]);
        $members = $list($pdo, 'SELECT group_id, user_email, role, status, invited_at, accepted_at, removed_at FROM geo_family_members WHERE group_id IN (SELECT id FROM geo_family_groups WHERE owner_email = :owner UNION SELECT group_id FROM geo_family_members WHERE user_email = :member)', [':owner' => $owner, ':member' => $owner]);
        $grants = $list($pdo, 'SELECT id, owner_email, viewer_email, family_group_id, access_level, starts_at, ends_at, range_from, range_to, revoked_at, created_at, updated_at FROM geo_location_grants WHERE owner_email = :owner OR viewer_email = :owner', [':owner' => $owner]);
        $connections = $list($pdo, 'SELECT id, user_email, state, server_url_ciphertext IS NOT NULL AS server_url_configured, credential_ciphertext IS NOT NULL AS credential_configured, created_at, updated_at FROM geo_immich_connections WHERE user_email = :owner', [':owner' => $owner]);
        $assets = $list($pdo, 'SELECT id, connection_id, immich_asset_id, captured_at, ST_Y(position) AS latitude, ST_X(position) AS longitude, match_json, created_at FROM geo_immich_assets WHERE user_email = :owner ORDER BY captured_at', [':owner' => $owner]);
        return [
            'settings' => $settings,
            'places' => $places,
            'trips' => $trips,
            'trip_places' => $list($pdo, 'SELECT trip_id, place_id FROM geo_trip_places WHERE user_email = :owner', [':owner' => $owner]),
            'trip_photos' => $list($pdo, 'SELECT trip_id, asset_id FROM geo_trip_photos WHERE user_email = :owner', [':owner' => $owner]),
            'stops' => $list($pdo, 'SELECT id, started_at, ended_at, ST_Y(position) AS latitude, ST_X(position) AS longitude, radius_m, confidence, state, override_json, created_at, updated_at FROM geo_stops WHERE user_email = :owner AND deleted_at IS NULL', [':owner' => $owner]),
            'visits' => $list($pdo, 'SELECT id, place_id, stop_id, arrived_at, departed_at, confidence, state, override_json, created_at, updated_at FROM geo_visits WHERE user_email = :owner', [':owner' => $owner]),
            'devices' => $list($pdo, 'SELECT id, name, platform, app_version, source_type, status, last_seen_at, last_upload_at, created_at, updated_at FROM geo_devices WHERE user_email = :owner', [':owner' => $owner]),
            'imports' => $list($pdo, 'SELECT id, source_type, state, format, progress_json, created_at, updated_at FROM geo_imports WHERE user_email = :owner', [':owner' => $owner]),
            'family' => ['groups' => $groups, 'members' => $members, 'grants' => $grants],
            'immich' => ['connections' => $connections, 'assets' => $assets, 'note' => 'Connection credentials are intentionally not exported; re-link Immich after restore. No image binaries are included.'],
            'geofences' => $list($pdo, 'SELECT id, place_id, name, ST_AsGeoJSON(geometry) AS geometry_geojson, is_active, notification_json, created_at, updated_at FROM geo_geofences WHERE user_email = :owner', [':owner' => $owner]),
            'notification_preferences' => $list($pdo, 'SELECT preferences_json, updated_at FROM geo_notification_preferences WHERE user_email = :owner', [':owner' => $owner]),
            'map_preferences' => $list($pdo, 'SELECT provider, layers_json, updated_at FROM geo_map_preferences WHERE user_email = :owner', [':owner' => $owner]),
        ];
    }

    private static function audit(PDO $pdo, string $owner, string $event, array $context): void
    {
        try {
            $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)')
                ->execute([':id' => self::uuid(), ':owner' => $owner, ':actor' => $owner, ':event' => $event, ':context' => json_encode($context, JSON_UNESCAPED_SLASHES)]);
        } catch (Throwable $e) {
            error_log('export audit: ' . $e->getMessage());
        }
    }
}
