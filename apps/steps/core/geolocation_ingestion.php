<?php
/**
 * Canonical location ingestion (tasks 02, 19).
 *
 * Mobile uploads and every file importer call this repository, so a point has
 * exactly one validated canonical shape and the same device authorization,
 * duplicate and provenance rules. Import-specific source identities live in
 * geo_point_sources; per-file ownership lives in geo_import_points, which lets
 * a removal delete only points that no other import still owns.
 */
require_once __DIR__ . '/geolocation_domain.php';
require_once __DIR__ . '/geolocation_observability.php';

final class StepsGeolocationIngestion
{
    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    /** @return array{status:string,id?:string,client_event_id?:string,error?:string} */
    public static function point(PDO $pdo, string $owner, array $point): array
    {
        $device = (string)($point['device_id'] ?? '');
        $event = (string)($point['client_event_id'] ?? '');
        $lat = $point['latitude'] ?? null;
        $lon = $point['longitude'] ?? null;
        $source = (string)($point['source_type'] ?? 'mobile');
        $at = (string)($point['source_at'] ?? '');
        if (!StepsGeolocationDomain::isUuid($device) || !StepsGeolocationDomain::isUuid($event)
            || !is_numeric($lat) || !is_numeric($lon)
            || !StepsGeolocationDomain::isCoordinate((float)$lat, (float)$lon)
            || !StepsGeolocationDomain::isSourceType($source) || strtotime($at) === false) {
            return ['status' => 'rejected', 'error' => 'invalid_point'];
        }
        $namespace = trim((string)($point['source_namespace'] ?? ''));
        $sourceEventId = trim((string)($point['source_event_id'] ?? ''));
        $importFileId = trim((string)($point['import_file_id'] ?? ''));
        if ($namespace !== '' && $sourceEventId !== '') {
            $known = $pdo->prepare('SELECT point_id FROM geo_point_sources WHERE user_email = :owner AND namespace = :namespace AND event_id = :event LIMIT 1');
            $known->execute([':owner' => $owner, ':namespace' => $namespace, ':event' => $sourceEventId]);
            $knownId = $known->fetchColumn();
            if ($knownId) {
                if ($importFileId !== '' && StepsGeolocationDomain::isUuid($importFileId)) self::linkImport($pdo, $owner, $importFileId, (string)$knownId);
                return ['status' => 'duplicate', 'id' => (string)$knownId, 'client_event_id' => $event];
            }
            if (!empty($point['dedupe_nearby'])) {
                // Points from the same import file are distinct samples of a
                // track, even when consecutive samples are a few metres apart.
                // Only points from other files/sources are nearby duplicates.
                $sameFile = StepsGeolocationDomain::isUuid($importFileId)
                    ? ' AND NOT EXISTS (
                            SELECT 1 FROM geo_import_points same_file
                             WHERE same_file.point_id = p.id AND same_file.user_email = :owner
                               AND same_file.import_file_id = :file)'
                    : '';
                $nearby = $pdo->prepare(
                    "SELECT p.id FROM geo_location_points p
                      WHERE p.user_email = :owner AND p.quality_state <> 'rejected'
                        AND p.source_at BETWEEN :from AND :to
                        AND ST_DWithin(p.position::geography, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography, :radius)"
                    . $sameFile . "
                   ORDER BY ST_Distance(p.position::geography, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography) ASC
                      LIMIT 1"
                );
                $atTs = strtotime($at);
                $nearbyParams = [
                    ':owner' => $owner, ':from' => gmdate('c', $atTs - 30), ':to' => gmdate('c', $atTs + 30),
                    ':lon' => (float)$lon, ':lat' => (float)$lat, ':radius' => 25,
                ];
                if ($sameFile !== '') $nearbyParams[':file'] = $importFileId;
                $nearby->execute($nearbyParams);
                $nearbyId = $nearby->fetchColumn();
                if ($nearbyId) {
                    self::linkSource($pdo, $owner, (string)$nearbyId, $namespace, $sourceEventId);
                    if ($importFileId !== '' && StepsGeolocationDomain::isUuid($importFileId)) self::linkImport($pdo, $owner, $importFileId, (string)$nearbyId);
                    return ['status' => 'duplicate', 'id' => (string)$nearbyId, 'client_event_id' => $event];
                }
            }
        }

        $platform = StepsGeolocationDomain::normalizeDeviceName((string)($point['platform'] ?? ''));
        $name = StepsGeolocationDomain::normalizeDeviceName((string)($point['device_name'] ?? ''));
        $appVersion = StepsGeolocationDomain::normalizeDeviceName((string)($point['app_version'] ?? ''));
        $pdo->beginTransaction();
        try {
            // The row lock makes a concurrent revoke wait, so a revoked device
            // can never slip a point in after the revoke was acknowledged.
            $lock = $pdo->prepare('SELECT status, name, platform, app_version FROM geo_devices WHERE user_email = :owner AND id = :device FOR UPDATE');
            $lock->execute([':owner' => $owner, ':device' => $device]);
            $existingDevice = $lock->fetch(PDO::FETCH_ASSOC);
            if ($existingDevice) {
                $status = (string)$existingDevice['status'];
                if (!StepsGeolocationDomain::deviceAcceptsPoints($status)) {
                    $pdo->rollBack();
                    return ['status' => 'rejected', 'error' => $status === 'paused' ? 'device_paused' : 'device_revoked'];
                }
                $stmt = $pdo->prepare('UPDATE geo_devices SET name = :name, platform = :platform, app_version = :app_version, last_seen_at = now(), updated_at = now() WHERE user_email = :owner AND id = :device');
                $stmt->execute([
                    ':name' => $name !== '' ? $name : (string)$existingDevice['name'],
                    ':platform' => $platform !== '' ? $platform : (string)$existingDevice['platform'],
                    ':app_version' => $appVersion !== '' ? $appVersion : (string)$existingDevice['app_version'],
                    ':owner' => $owner,
                    ':device' => $device,
                ]);
                if ($source === 'mobile') {
                    $pdo->prepare(
                        "UPDATE geo_devices
                            SET status = 'revoked', updated_at = now()
                          WHERE user_email = :owner
                            AND source_type = 'mobile'
                            AND id <> :device
                            AND status IN ('active', 'paused')"
                    )->execute([':owner' => $owner, ':device' => $device]);
                }
            } else {
                $deviceStmt = $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, app_version, last_seen_at, last_upload_at) VALUES(:id, :owner, :name, :platform, :source, :app_version, now(), now()) ON CONFLICT (user_email, id) DO NOTHING");
                $deviceStmt->execute([
                    ':id' => $device,
                    ':owner' => $owner,
                    ':name' => $name !== '' ? $name : ($platform === '' ? 'Imported device' : ucfirst($platform) . ' device'),
                    ':platform' => $platform,
                    ':source' => $source,
                    ':app_version' => $appVersion,
                ]);
                // One account, one connected mobile device. Registering a new
                // phone retires the previous pairing instead of leaving stale
                // devices able to keep uploading in parallel.
                if ($source === 'mobile') {
                    $retire = $pdo->prepare(
                        "UPDATE geo_devices
                            SET status = 'revoked', updated_at = now()
                          WHERE user_email = :owner
                            AND source_type = 'mobile'
                            AND id <> :device
                            AND status IN ('active', 'paused')"
                    );
                    $retire->execute([':owner' => $owner, ':device' => $device]);
                }
            }
            if ($namespace !== '' && $sourceEventId !== '') {
                $again = $pdo->prepare('SELECT point_id FROM geo_point_sources WHERE user_email = :owner AND namespace = :namespace AND event_id = :event LIMIT 1');
                $again->execute([':owner' => $owner, ':namespace' => $namespace, ':event' => $sourceEventId]);
                $againId = $again->fetchColumn();
                if ($againId) {
                    $pdo->commit();
                    return ['status' => 'duplicate', 'id' => (string)$againId, 'client_event_id' => $event];
                }
            }
            $sql = "INSERT INTO geo_location_points(id,user_email,device_id,client_event_id,device_sequence,source_type,source_at,source_offset_minutes,position,accuracy_m,altitude_m,speed_mps,bearing_degrees)
                    VALUES(:id,:owner,:device,:event,:sequence,:source,:source_at,:offset,ST_SetSRID(ST_MakePoint(:lon,:lat),4326),:accuracy,:altitude,:speed,:bearing)
                    ON CONFLICT (user_email,device_id,client_event_id) WHERE client_event_id IS NOT NULL DO NOTHING RETURNING id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => self::uuid(), ':owner' => $owner, ':device' => $device, ':event' => $event,
                ':sequence' => isset($point['device_sequence']) ? (int)$point['device_sequence'] : null,
                ':source' => $source, ':source_at' => gmdate('c', strtotime($at)),
                ':offset' => isset($point['source_offset_minutes']) ? (int)$point['source_offset_minutes'] : null,
                ':lon' => (float)$lon, ':lat' => (float)$lat,
                ':accuracy' => $point['accuracy_m'] ?? null, ':altitude' => $point['altitude_m'] ?? null,
                ':speed' => $point['speed_mps'] ?? null, ':bearing' => $point['bearing_degrees'] ?? null,
            ]);
            $id = $stmt->fetchColumn();
            if (!$id) {
                $find = $pdo->prepare('SELECT id FROM geo_location_points WHERE user_email = :owner AND device_id = :device AND client_event_id = :event LIMIT 1');
                $find->execute([':owner' => $owner, ':device' => $device, ':event' => $event]);
                $id = $find->fetchColumn();
            }
            if ($id && $namespace !== '' && $sourceEventId !== '') self::linkSource($pdo, $owner, (string)$id, $namespace, $sourceEventId);
            if ($id && $importFileId !== '' && StepsGeolocationDomain::isUuid($importFileId)) self::linkImport($pdo, $owner, $importFileId, (string)$id);
            if ($id) {
                $touch = $pdo->prepare('UPDATE geo_devices SET last_upload_at = now(), last_seen_at = now() WHERE user_email = :owner AND id = :device');
                $touch->execute([':owner' => $owner, ':device' => $device]);
            }
            $pdo->commit();
            if ($id) {
                StepsGeolocationObservability::increment('geo_ingest_points_total', 1, ['result' => 'accepted']);
                StepsGeolocationObservability::invalidateOwner($owner);
            } else {
                StepsGeolocationObservability::increment('geo_ingest_points_total', 1, ['result' => 'duplicate']);
            }
            return $id ? ['status' => 'accepted', 'id' => (string)$id, 'client_event_id' => $event] : ['status' => 'duplicate', 'client_event_id' => $event];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function linkSource(PDO $pdo, string $owner, string $pointId, string $namespace, string $eventId): void
    {
        if ($namespace === '' || $eventId === '') return;
        $stmt = $pdo->prepare('INSERT INTO geo_point_sources(id, user_email, point_id, namespace, event_id) VALUES(:id, :owner, :point, :namespace, :event) ON CONFLICT (user_email, namespace, event_id) DO NOTHING');
        $stmt->execute([':id' => self::uuid(), ':owner' => $owner, ':point' => $pointId, ':namespace' => $namespace, ':event' => $eventId]);
    }

    public static function linkImport(PDO $pdo, string $owner, string $importFileId, string $pointId): void
    {
        $stmt = $pdo->prepare('INSERT INTO geo_import_points(import_file_id, point_id, user_email) VALUES(:file, :point, :owner) ON CONFLICT DO NOTHING');
        $stmt->execute([':file' => $importFileId, ':point' => $pointId, ':owner' => $owner]);
    }
}
