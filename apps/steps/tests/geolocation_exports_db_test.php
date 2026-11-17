<?php
/**
 * Integration test for geolocation exports and backups (task 20).
 *
 *   php apps/steps/tests/geolocation_exports_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_exports.php';
require_once dirname(__DIR__) . '/core/geolocation_import_parsers.php';

function geo_export_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function geo_export_rmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . '/' . $entry;
        if (is_dir($path)) geo_export_rmdir($path); else @unlink($path);
    }
    @rmdir($dir);
}

$owner = 'qa-geo-export@example.test';
$other = 'qa-geo-export-other@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, string $owner): void {
    foreach (['geo_artifacts', 'geo_exports', 'geo_audit_events', 'geo_processor_runs', 'geo_trip_photos', 'geo_trip_places', 'geo_visits', 'geo_stops', 'geo_trips', 'geo_places', 'geo_location_points', 'geo_devices'] as $table) {
        try { $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]); } catch (Throwable $ignored) {}
    }
    try { $pdo->prepare('DELETE FROM geo_family_groups WHERE owner_email = :owner')->execute([':owner' => $owner]); } catch (Throwable $ignored) {}
    try { $pdo->prepare('DELETE FROM geo_immich_connections WHERE user_email = :owner')->execute([':owner' => $owner]); } catch (Throwable $ignored) {}
    geo_export_rmdir(StepsGeolocationExports::userDir($owner));
};
$cleanup($pdo, $owner);
$cleanup($pdo, $other);

try {
    $device = StepsGeolocationExports::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA export phone', 'android', 'mobile', 'active')")
        ->execute([':id' => $device, ':owner' => $owner]);
    $place = StepsGeolocationExports::uuid();
    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, timezone) VALUES(:id, :owner, 'QA Home', 'home', ST_SetSRID(ST_MakePoint(30.5234, 50.4501), 4326), 150, 'UTC')")
        ->execute([':id' => $place, ':owner' => $owner]);
    $trip = StepsGeolocationExports::uuid();
    $pdo->prepare("INSERT INTO geo_trips(id, user_email, started_at, ended_at, title, geometry) VALUES(:id, :owner, '2025-05-01T08:00:00Z', '2025-05-01T09:00:00Z', 'QA trip', ST_SetSRID(ST_MakeLine(ST_MakePoint(30.5234,50.4501), ST_MakePoint(30.5334,50.4601)), 4326))")
        ->execute([':id' => $trip, ':owner' => $owner]);

    $insert = $pdo->prepare("INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, accuracy_m, quality_state)
        VALUES(:id, :owner, :device, :event, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 5, 'accepted')");
    $points = [
        ['2025-05-01T08:00:00Z', 50.4501, 30.5234],
        ['2025-05-01T08:10:00Z', 50.4502, 30.5235],
        ['2025-05-01T08:20:00Z', 50.4503, 30.5236],
        ['2025-05-02T08:00:00Z', 50.4600, 30.5330],
    ];
    foreach ($points as [$at, $lat, $lon]) {
        $insert->execute([':id' => StepsGeolocationExports::uuid(), ':owner' => $owner, ':device' => $device, ':event' => StepsGeolocationExports::uuid(), ':at' => $at, ':lat' => $lat, ':lon' => $lon]);
    }

    // GeoJSON stats/date scope and import round-trip.
    $job = StepsGeolocationExports::enqueue($pdo, $owner, 'geojson', ['type' => 'date', 'from' => '2025-05-01', 'to' => '2025-05-01']);
    geo_export_assert($job['state'] === 'pending', 'export was not queued');
    $ready = StepsGeolocationExports::process($pdo, $owner, $job['id']);
    geo_export_assert($ready['state'] === 'ready', 'geojson export did not become ready');
    geo_export_assert(count($ready['artifacts']) === 1, 'artifact row missing');
    $artifact = $ready['artifacts'][0];
    geo_export_assert($artifact['available'] === true, 'artifact is not available');
    geo_export_assert(is_file(StepsGeolocationExports::userDir($owner) . '/' . $job['id'] . '/' . $artifact['filename']), 'artifact file missing');
    $path = StepsGeolocationExports::userDir($owner) . '/' . $job['id'] . '/' . $artifact['filename'];
    geo_export_assert(hash_file('sha256', $path) === $artifact['checksum'], 'artifact checksum mismatch');
    geo_export_assert((int)$artifact['size_bytes'] === filesize($path), 'artifact size mismatch');
    $decoded = json_decode((string)file_get_contents($path), true);
    geo_export_assert(($decoded['type'] ?? '') === 'FeatureCollection' && count($decoded['features']) === 3, 'date scope exported the wrong point count');
    $detected = StepsGeoImportParsers::detect($path, 'export.geojson');
    geo_export_assert($detected !== null && $detected['format'] === 'geojson', 'export is not recognized by the import pipeline');
    $roundtrip = iterator_to_array(StepsGeoImportParsers::parse($detected['parser'], $path));
    geo_export_assert(count($roundtrip) === 3, 'GeoJSON round-trip lost points');
    geo_export_assert($roundtrip[0]['source_at'] === '2025-05-01T08:00:00+00:00', 'GeoJSON round-trip timestamp was not preserved');

    // GPX round-trip and device scope.
    $gpxJob = StepsGeolocationExports::enqueue($pdo, $owner, 'gpx', ['type' => 'device', 'device_id' => $device]);
    $gpx = StepsGeolocationExports::process($pdo, $owner, $gpxJob['id']);
    $gpxArtifact = $gpx['artifacts'][0];
    $gpxPath = StepsGeolocationExports::userDir($owner) . '/' . $gpxJob['id'] . '/' . $gpxArtifact['filename'];
    $gpxDetected = StepsGeoImportParsers::detect($gpxPath, 'export.gpx');
    geo_export_assert($gpxDetected !== null && in_array($gpxDetected['format'], ['gpx', 'strava_gpx'], true), 'GPX export is not importable');
    $gpxPoints = iterator_to_array(StepsGeoImportParsers::parse($gpxDetected['parser'], $gpxPath));
    geo_export_assert(count($gpxPoints) === 4, 'GPX round-trip lost points');

    // JSON with derived records.
    $jsonJob = StepsGeolocationExports::enqueue($pdo, $owner, 'json', ['type' => 'all', 'include_derived' => true]);
    $json = StepsGeolocationExports::process($pdo, $owner, $jsonJob['id']);
    $jsonPath = StepsGeolocationExports::userDir($owner) . '/' . $jsonJob['id'] . '/' . $json['artifacts'][0]['filename'];
    $jsonDoc = json_decode((string)file_get_contents($jsonPath), true);
    geo_export_assert(count($jsonDoc['points'] ?? []) === 4, 'JSON export lost points');
    geo_export_assert(($jsonDoc['derived']['trips'][0]['id'] ?? '') === $trip, 'derived trip missing');
    geo_export_assert(($jsonDoc['derived']['places'][0]['id'] ?? '') === $place, 'derived place missing');

    // Full backup includes account metadata but no Immich secrets.
    $backupJob = StepsGeolocationExports::enqueue($pdo, $owner, 'backup', ['type' => 'all']);
    $backup = StepsGeolocationExports::process($pdo, $owner, $backupJob['id']);
    $backupPath = StepsGeolocationExports::userDir($owner) . '/' . $backupJob['id'] . '/' . $backup['artifacts'][0]['filename'];
    $backupDoc = json_decode((string)file_get_contents($backupPath), true);
    geo_export_assert(($backupDoc['kind'] ?? '') === 'backup' && count($backupDoc['points'] ?? []) === 4, 'backup points missing');
    geo_export_assert(isset($backupDoc['account']['settings'], $backupDoc['account']['places'], $backupDoc['account']['trips'], $backupDoc['account']['family'], $backupDoc['account']['immich']), 'backup account sections missing');
    geo_export_assert(!str_contains((string)file_get_contents($backupPath), 'credential_ciphertext'), 'backup leaked an Immich credential field');

    // Only the owner can download, and expired artifacts are refused.
    geo_export_assert(StepsGeolocationExports::download($pdo, $other, $artifact['id']) === null, 'another account downloaded the artifact');
    geo_export_assert(StepsGeolocationExports::download($pdo, $owner, $artifact['id']) !== null, 'owner download failed');
    $pdo->prepare("UPDATE geo_artifacts SET expires_at = now() - interval '1 minute' WHERE id = :id")->execute([':id' => $artifact['id']]);
    geo_export_assert(StepsGeolocationExports::download($pdo, $owner, $artifact['id']) === null, 'expired artifact was downloadable');
    geo_export_assert(StepsGeolocationExports::cleanupExpired($pdo) >= 1, 'expired artifact was not cleaned up');
    geo_export_assert(!is_file($path), 'expired artifact file was not deleted');

    // Deletion removes files and records an audit event.
    geo_export_assert(StepsGeolocationExports::deleteExport($pdo, $owner, $gpxJob['id']) === true, 'export deletion failed');
    geo_export_assert(!is_file($gpxPath), 'deleted export file is still present');
    $audit = $pdo->prepare("SELECT COUNT(*) FROM geo_audit_events WHERE user_email = :owner AND event_type IN ('export.completed','export.downloaded','export.deleted')");
    $audit->execute([':owner' => $owner]);
    geo_export_assert((int)$audit->fetchColumn() >= 4, 'export audit events missing');

    // Scope ownership is checked before enqueueing.
    try {
        StepsGeolocationExports::enqueue($pdo, $owner, 'geojson', ['type' => 'device', 'device_id' => StepsGeolocationExports::uuid()]);
        throw new RuntimeException('foreign device was accepted');
    } catch (StepsGeolocationExportError $e) {
        geo_export_assert($e->errorCode === 'device_not_found', 'wrong device scope error');
    }

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
    $cleanup($pdo, $other);
}
