<?php
/**
 * Integration test for the location import pipeline (task 19).
 *
 *   php apps/steps/tests/geolocation_imports_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_ingestion.php';
require_once dirname(__DIR__) . '/core/geolocation_imports.php';

function geo_import_db_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function geo_import_db_rmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . '/' . $entry;
        if (is_dir($path)) geo_import_db_rmdir($path); else @unlink($path);
    }
    @rmdir($dir);
}

$owner = 'qa-geo-import@example.test';
$other = 'qa-geo-import-other@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, string $owner) use ($db): void {
    foreach (['geo_import_points', 'geo_import_files', 'geo_imports', 'geo_point_sources', 'geo_statistics_cache', 'geo_statistics_daily', 'geo_trips', 'geo_visits', 'geo_stops', 'geo_location_points', 'geo_devices', 'geo_processor_runs'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
    }
    geo_import_db_rmdir(StepsGeolocationImports::userDir($owner));
};
$cleanup($pdo, $owner);
$cleanup($pdo, $other);

try {
    // An unrelated mobile point must survive every import removal.
    $mobileDevice = StepsGeolocationIngestion::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA mobile', 'android', 'mobile', 'active')")
        ->execute([':id' => $mobileDevice, ':owner' => $owner]);
    $mobile = StepsGeolocationIngestion::point($pdo, $owner, [
        'device_id' => $mobileDevice, 'client_event_id' => StepsGeolocationIngestion::uuid(),
        'source_type' => 'mobile', 'source_at' => '2025-05-20T10:00:00Z',
        'latitude' => 50.6, 'longitude' => 30.7, 'accuracy_m' => 8,
    ]);
    geo_import_db_assert($mobile['status'] === 'accepted', 'mobile fixture point was not accepted');

    $fixture = __DIR__ . '/fixtures/geo_import/sample.gpx';
    $tmp = sys_get_temp_dir() . '/qa-import-' . bin2hex(random_bytes(4)) . '.gpx';
    copy($fixture, $tmp);
    $import = StepsGeolocationImports::acceptFile($tmp, 'qa-route.gpx', $owner, ['delete_source' => false], false);
    geo_import_db_assert($import['state'] === 'pending' && $import['format'] === 'strava_gpx', 'import was not queued with a detected format');
    geo_import_db_assert(!empty($import['files'][0]['storage_path']) && is_file($import['files'][0]['storage_path']), 'source file was not stored on the app data volume');
    $importId = $import['id'];

    $counts = StepsGeolocationImports::process($pdo, $owner, $importId);
    geo_import_db_assert($counts['points'] === 3 && $counts['accepted'] === 3, 'GPX import counts wrong: ' . json_encode($counts));
    $ready = StepsGeolocationImports::find($pdo, $owner, $importId);
    geo_import_db_assert($ready['state'] === 'ready', 'import did not become ready');
    $points = $pdo->prepare("SELECT quality_state, source_type, device_id FROM geo_location_points WHERE user_email = :owner AND source_type = 'import'");
    $points->execute([':owner' => $owner]);
    $rows = $points->fetchAll(PDO::FETCH_ASSOC);
    geo_import_db_assert(count($rows) === 3, 'imported points missing');
    $deviceIds = [];
    foreach ($rows as $row) {
        geo_import_db_assert($row['quality_state'] === 'pending', 'imported point skipped the canonical quality pipeline');
        geo_import_db_assert($row['source_type'] === 'import', 'imported point has the wrong source type');
        $deviceIds[$row['device_id']] = true;
    }
    geo_import_db_assert(count($deviceIds) === 1, 'import used more than one device');
    $importDevice = $pdo->prepare("SELECT source_type FROM geo_devices WHERE id = :id AND user_email = :owner");
    $importDevice->execute([':id' => array_key_first($deviceIds), ':owner' => $owner]);
    geo_import_db_assert($importDevice->fetchColumn() === 'import', 'import device is not marked as an import source');
    $sources = $pdo->prepare('SELECT COUNT(*) FROM geo_point_sources WHERE user_email = :owner');
    $sources->execute([':owner' => $owner]);
    geo_import_db_assert((int)$sources->fetchColumn() === 3, 'source provenance missing');
    $links = $pdo->prepare('SELECT COUNT(*) FROM geo_import_points WHERE import_file_id = :file AND user_email = :owner');
    $links->execute([':file' => $ready['files'][0]['id'], ':owner' => $owner]);
    geo_import_db_assert((int)$links->fetchColumn() === 3, 'per-file point links missing');

    // Uploading the same bytes again is reported as a duplicate, not a new job.
    $same = sys_get_temp_dir() . '/qa-import-same-' . bin2hex(random_bytes(4)) . '.gpx';
    copy($fixture, $same);
    $duplicate = StepsGeolocationImports::acceptFile($same, 'qa-route-copy.gpx', $owner, [], false);
    geo_import_db_assert(!empty($duplicate['duplicate']) && $duplicate['id'] === $importId, 'duplicate file was not detected');
    @unlink($same);

    // Overlapping export: two shared points and one new point.
    $overlap = sys_get_temp_dir() . '/qa-import-overlap-' . bin2hex(random_bytes(4)) . '.gpx';
    file_put_contents($overlap, '<?xml version="1.0"?><gpx version="1.1"><trk><trkseg>'
        . '<trkpt lat="50.4501" lon="30.5234"><time>2025-05-01T08:00:00Z</time></trkpt>'
        . '<trkpt lat="50.4511" lon="30.5244"><time>2025-05-01T08:05:00Z</time></trkpt>'
        . '<trkpt lat="50.4531" lon="30.5264"><time>2025-05-01T08:15:00Z</time></trkpt>'
        . '</trkseg></trk></gpx>');
    $overlapImport = StepsGeolocationImports::acceptFile($overlap, 'qa-overlap.gpx', $owner, [], false);
    $overlapCounts = StepsGeolocationImports::process($pdo, $owner, $overlapImport['id']);
    geo_import_db_assert($overlapCounts['accepted'] === 1 && $overlapCounts['duplicate'] === 2, 'overlap dedupe wrong: ' . json_encode($overlapCounts));
    $importedCount = $pdo->prepare("SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner AND source_type = 'import'");
    $importedCount->execute([':owner' => $owner]);
    geo_import_db_assert((int)$importedCount->fetchColumn() === 4, 'overlap introduced duplicate points');

    // Removing the second import keeps points still owned by the first.
    $removed = StepsGeolocationImports::remove($pdo, $owner, $overlapImport['id']);
    geo_import_db_assert($removed['deleted_points'] === 1 && $removed['kept_points'] === 2, 'shared-point removal wrong: ' . json_encode($removed));
    $importedCount->execute([':owner' => $owner]);
    geo_import_db_assert((int)$importedCount->fetchColumn() === 3, 'shared points were deleted with their second import');

    // Removing the first import deletes only its remaining points.
    $removed = StepsGeolocationImports::remove($pdo, $owner, $importId);
    geo_import_db_assert($removed['deleted_points'] === 3, 'first import removal wrong: ' . json_encode($removed));
    $remaining = $pdo->prepare('SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner');
    $remaining->execute([':owner' => $owner]);
    geo_import_db_assert((int)$remaining->fetchColumn() === 1, 'unrelated mobile point was removed');
    $mobileStill = $pdo->prepare("SELECT quality_state, ST_AsText(position) FROM geo_location_points WHERE user_email = :owner AND source_type = 'mobile'");
    $mobileStill->execute([':owner' => $owner]);
    $mobileRow = $mobileStill->fetch(PDO::FETCH_ASSOC);
    geo_import_db_assert($mobileRow !== false && $mobileRow['quality_state'] === 'pending', 'mobile point changed during import removal');

    // Failed files stay visible with an error instead of disappearing.
    $broken = sys_get_temp_dir() . '/qa-import-broken-' . bin2hex(random_bytes(4)) . '.gpx';
    copy($fixture, $broken);
    $failedImport = StepsGeolocationImports::acceptFile($broken, 'qa-broken.gpx', $owner, [], false);
    $brokenPath = $failedImport['files'][0]['storage_path'];
    @unlink($brokenPath);
    StepsGeolocationImports::process($pdo, $owner, $failedImport['id']);
    $failed = StepsGeolocationImports::find($pdo, $owner, $failedImport['id']);
    geo_import_db_assert($failed['state'] === 'failed' && $failed['files'][0]['state'] === 'failed', 'failed import is not visible');
    geo_import_db_assert(($failed['files'][0]['error_json']['error'] ?? '') === 'source_missing', 'failed file did not report its error');

    // Safe source deletion after success.
    $clean = sys_get_temp_dir() . '/qa-import-clean-' . bin2hex(random_bytes(4)) . '.gpx';
    copy($fixture, $clean);
    $cleanImport = StepsGeolocationImports::acceptFile($clean, 'qa-clean.gpx', $owner, ['delete_source' => true], false);
    $cleanPath = $cleanImport['files'][0]['storage_path'];
    StepsGeolocationImports::process($pdo, $owner, $cleanImport['id']);
    geo_import_db_assert(!is_file($cleanPath), 'source file was not deleted after a successful import');
    $cleanReady = StepsGeolocationImports::find($pdo, $owner, $cleanImport['id']);
    geo_import_db_assert(($cleanReady['options']['source_deleted'] ?? false) === true, 'source deletion was not recorded: ' . json_encode($cleanReady['options']) . ' state=' . $cleanReady['state']);

    // Owner scoping.
    geo_import_db_assert(StepsGeolocationImports::find($pdo, $other, $cleanImport['id']) === null, 'another account can read the import');

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
    $cleanup($pdo, $other);
}
