<?php
/**
 * Immich adapter test with a mock HTTP server (task 21).
 *
 *   php apps/steps/tests/geolocation_immich_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_immich.php';

function geo_immich_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function geo_immich_cleanup(PDO $pdo, string $owner): void
{
    foreach (['geo_trip_photos', 'geo_immich_assets', 'geo_processor_runs', 'geo_location_points', 'geo_devices'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
    }
    $pdo->prepare('DELETE FROM geo_immich_connections WHERE user_email = :owner')->execute([':owner' => $owner]);
    $pdo->prepare('DELETE FROM geo_audit_events WHERE user_email = :owner')->execute([':owner' => $owner]);
}

$owner = 'qa-immich@example.test';
$keyFile = sys_get_temp_dir() . '/steps-geo-key-' . bin2hex(random_bytes(4)) . '.key';
putenv('STEPS_GEOLOCATION_KEY_FILE=' . $keyFile);
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
geo_immich_cleanup($pdo, $owner);

$port = random_int(21000, 28999);
$router = __DIR__ . '/fixtures/immich_router.php';
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$process = proc_open('/usr/local/bin/php -S 127.0.0.1:' . $port . ' ' . escapeshellarg($router), $descriptors, $pipes, dirname(__DIR__, 3));
geo_immich_assert(is_resource($process), 'could not start Immich mock server');
try {
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($socket) { fclose($socket); $ready = true; break; }
        usleep(100000);
    }
    geo_immich_assert($ready, 'Immich mock server did not start');

    $device = StepsGeolocationImmich::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA immich', 'android', 'mobile', 'active')")
        ->execute([':id' => $device, ':owner' => $owner]);
    $point = $pdo->prepare("INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, quality_state) VALUES(:id, :owner, :device, :event, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 'accepted')");
    $p1 = StepsGeolocationImmich::uuid();
    $p2 = StepsGeolocationImmich::uuid();
    $point->execute([':id' => $p1, ':owner' => $owner, ':device' => $device, ':event' => StepsGeolocationImmich::uuid(), ':at' => '2026-10-01T10:00:00Z', ':lon' => 30.5234, ':lat' => 50.4501]);
    $point->execute([':id' => $p2, ':owner' => $owner, ':device' => $device, ':event' => StepsGeolocationImmich::uuid(), ':at' => '2026-10-01T10:05:00Z', ':lon' => 30.5235, ':lat' => 50.4502]);

    $url = 'http://127.0.0.1:' . $port;
    $connection = StepsGeolocationImmich::configure($pdo, $owner, $url, 'test-api-key', ['time_tolerance_s' => 120, 'distance_tolerance_m' => 100]);
    geo_immich_assert($connection['connected'] === true, 'connection was not saved');
    $raw = $pdo->prepare('SELECT server_url_ciphertext, credential_ciphertext FROM geo_immich_connections WHERE user_email = :owner');
    $raw->execute([':owner' => $owner]);
    $cipher = $raw->fetch(PDO::FETCH_ASSOC);
    geo_immich_assert(!str_contains((string)$cipher['server_url_ciphertext'], $url), 'server URL stored in plaintext');
    geo_immich_assert(!str_contains((string)$cipher['credential_ciphertext'], 'test-api-key'), 'API key stored in plaintext');

    $counts = StepsGeolocationImmich::sync($pdo, $owner, '2026-10-01T00:00:00Z', '2026-10-02T00:00:00Z');
    geo_immich_assert($counts['seen'] === 2 && $counts['matched'] === 1 && $counts['unmatched'] === 1, 'sync counts wrong: ' . json_encode($counts));
    $assets = $pdo->prepare('SELECT id, immich_asset_id, state, match_point_id, match_json FROM geo_immich_assets WHERE user_email = :owner ORDER BY immich_asset_id');
    $assets->execute([':owner' => $owner]);
    $rows = $assets->fetchAll(PDO::FETCH_ASSOC);
    geo_immich_assert(count($rows) === 2, 'assets were not persisted');
    geo_immich_assert($rows[0]['state'] === 'matched' && $rows[0]['match_point_id'] === $p1, 'spatial match missing');
    geo_immich_assert($rows[1]['state'] === 'unmatched', 'missing-GPS asset was not flagged');
    $candidate = json_decode((string)$rows[1]['match_json'], true);
    geo_immich_assert(($candidate['mode'] ?? '') === 'time_candidate' && ($candidate['point_id'] ?? '') === $p2, 'time candidate missing');

    $clusters = StepsGeolocationImmich::mapClusters($pdo, $owner, ['minLon' => 30.5, 'minLat' => 50.4, 'maxLon' => 30.6, 'maxLat' => 50.5], '2026-10-01T00:00:00Z', '2026-10-02T00:00:00Z', 14);
    geo_immich_assert(count($clusters) === 1, 'map photo cluster missing');
    $gallery = StepsGeolocationImmich::galleries($pdo, $owner);
    geo_immich_assert(count($gallery['assets']) === 2, 'photo gallery missing assets');
    $thumb = StepsGeolocationImmich::thumbnail($pdo, $owner, (string)$rows[0]['id']);
    geo_immich_assert($thumb !== null && str_starts_with($thumb['body'], "\xFF\xD8"), 'thumbnail was not fetched on demand');
    geo_immich_assert(StepsGeolocationImmich::thumbnail($pdo, 'qa-immich-other@example.test', (string)$rows[0]['id']) === null, 'another account fetched a thumbnail');

    // Explicit confirmation is required before writing metadata to Immich.
    try {
        StepsGeolocationImmich::writeGps($pdo, $owner, (string)$rows[1]['id'], $p2, false);
        throw new RuntimeException('writeback without confirmation was accepted');
    } catch (StepsGeolocationImmichError $e) {
        geo_immich_assert($e->errorCode === 'confirmation_required', 'wrong writeback guard');
    }
    StepsGeolocationImmich::writeGps($pdo, $owner, (string)$rows[1]['id'], $p2, true);
    $written = $pdo->prepare('SELECT state, match_point_id FROM geo_immich_assets WHERE id = :id');
    $written->execute([':id' => $rows[1]['id']]);
    geo_immich_assert($written->fetchColumn() === 'matched', 'writeback did not mark the asset matched');

    // Disconnecting deletes local references but does not call Immich deletion.
    StepsGeolocationImmich::disconnect($pdo, $owner);
    $remaining = $pdo->prepare('SELECT COUNT(*) FROM geo_immich_assets WHERE user_email = :owner');
    $remaining->execute([':owner' => $owner]);
    geo_immich_assert((int)$remaining->fetchColumn() === 0, 'local assets survived disconnect');
    $state = $pdo->prepare('SELECT state FROM geo_immich_connections WHERE user_email = :owner');
    $state->execute([':owner' => $owner]);
    geo_immich_assert($state->fetchColumn() === 'disconnected', 'connection did not disconnect');

    echo "ok\n";
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process);
    }
    @unlink($keyFile);
    geo_immich_cleanup($pdo, $owner);
}
