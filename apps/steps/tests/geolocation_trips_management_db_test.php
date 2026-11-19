<?php
/**
 * Integration test for manual trip management commands.
 *
 * Run inside the app image on the app network:
 *   php apps/steps/tests/geolocation_trips_management_db_test.php
 *
 * The test owns one fixture account and never touches canonical points outside
 * it: create/edit/merge/split/archive/delete must only rewrite derived trips.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_trips.php';

function geo_tm_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-geo-trips-manage@example.test';
$other = 'qa-geo-trips-other@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, string $owner): void {
    foreach (['geo_trip_photos', 'geo_trip_places', 'geo_audit_events', 'geo_trips', 'geo_location_points', 'geo_visits', 'geo_stops', 'geo_immich_assets', 'geo_immich_connections', 'geo_places', 'geo_devices', 'geo_processor_runs'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
    }
};
$cleanup($pdo, $owner);
$cleanup($pdo, $other);

try {
    // v003 columns and link tables are present.
    $columns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'geo_trips'")->fetchAll(PDO::FETCH_COLUMN);
    geo_tm_assert(in_array('duration_s', $columns, true), 'duration_s column missing');
    geo_tm_assert(in_array('geometry_visible', $columns, true), 'geometry_visible column missing');
    geo_tm_assert((int)$pdo->query("SELECT COUNT(*) FROM pg_tables WHERE tablename IN ('geo_trip_places','geo_trip_photos')")->fetchColumn() === 2, 'trip link tables missing');

    $deviceId = StepsGeolocationTrips::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA trips', 'android', 'mobile', 'active')")
        ->execute([':id' => $deviceId, ':owner' => $owner]);

    $base = time() - 7200;
    $pointIds = [];
    $insertPoint = $pdo->prepare(
        "INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, accuracy_m, quality_state)
         VALUES(:id, :owner, :device, :event, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 6, 'accepted')"
    );
    for ($i = 0; $i < 10; $i++) {
        $id = StepsGeolocationTrips::uuid();
        $pointIds[] = $id;
        $insertPoint->execute([
            ':id' => $id,
            ':owner' => $owner,
            ':device' => $deviceId,
            ':event' => StepsGeolocationTrips::uuid(),
            ':at' => gmdate('c', $base + $i * 300),
            ':lon' => 30.5234 + $i * 0.001,
            ':lat' => 50.4501 + $i * 0.0008,
        ]);
    }
    $pointChecksum = (string)$pdo->query("SELECT md5(string_agg(id::text || ST_AsText(position) || source_at::text, ',' ORDER BY id)) FROM geo_location_points WHERE user_email = " . $pdo->quote($owner))->fetchColumn();

    // Create a coherent trip from a selected point set without touching points.
    $selected = array_slice($pointIds, 0, 6);
    $trip = StepsGeolocationTrips::create($pdo, $owner, [
        'title' => '  Morning   commute ',
        'notes' => 'Created from six points',
        'point_ids' => $selected,
    ]);
    geo_tm_assert($trip['title'] === 'Morning commute', 'title not normalized: ' . $trip['title']);
    geo_tm_assert((int)$trip['override_json']['point_count'] === 6, 'create did not keep selected point count');
    geo_tm_assert(($trip['override_json']['manual'] ?? false) === true, 'manual provenance missing');
    geo_tm_assert($trip['override_json']['origin'] === 'user_create', 'create origin missing');
    geo_tm_assert($trip['geometry_visible'] === true, 'geometry should be visible by default');
    geo_tm_assert((int)$trip['duration_s'] === 5 * 300, 'duration not calculated from point set: ' . $trip['duration_s']);
    geo_tm_assert((float)$trip['distance_m'] > 0, 'distance not calculated');
    geo_tm_assert(is_array($trip['geojson']) && $trip['geojson']['type'] === 'LineString', 'geometry not returned');

    // Links: place and photo must belong to the owner.
    $placeId = StepsGeolocationTrips::uuid();
    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m) VALUES(:id, :owner, 'Home', 'home', ST_SetSRID(ST_MakePoint(30.5234, 50.4501), 4326), 80)")
        ->execute([':id' => $placeId, ':owner' => $owner]);
    $connectionId = StepsGeolocationTrips::uuid();
    $assetId = StepsGeolocationTrips::uuid();
    $pdo->prepare("INSERT INTO geo_immich_connections(id, user_email, server_url_ciphertext, credential_ciphertext) VALUES(:id, :owner, 'cipher', 'cipher')")
        ->execute([':id' => $connectionId, ':owner' => $owner]);
    $pdo->prepare("INSERT INTO geo_immich_assets(id, user_email, connection_id, immich_asset_id, captured_at) VALUES(:id, :owner, :connection, 'asset-1', now())")
        ->execute([':id' => $assetId, ':owner' => $owner, ':connection' => $connectionId]);

    $trip = StepsGeolocationTrips::update($pdo, $owner, $trip['id'], [
        'title' => 'Morning commute',
        'notes' => 'Edited notes',
        'geometry_visible' => false,
        'place_ids' => [$placeId],
        'photo_ids' => [$assetId],
    ]);
    geo_tm_assert($trip['title'] === 'Morning commute', 'title update failed');
    geo_tm_assert($trip['notes'] === 'Edited notes', 'notes update failed');
    geo_tm_assert($trip['geometry_visible'] === false, 'geometry visibility update failed');
    geo_tm_assert($trip['place_ids'] === [$placeId], 'place link missing');
    geo_tm_assert($trip['photo_ids'] === [$assetId], 'photo link missing');
    geo_tm_assert(($trip['override_json']['origin'] ?? '') === 'user_edit', 'edit origin missing');
    geo_tm_assert(($trip['override_json']['overrides']['title'] ?? false) === true, 'title override not recorded');

    // Recalculate from the stored point set and expose duration/distance.
    $recalculated = StepsGeolocationTrips::recalculate($pdo, $owner, $trip['id']);
    geo_tm_assert((int)$recalculated['override_json']['point_count'] === 6, 'recalculate changed the point set');
    geo_tm_assert((int)$recalculated['duration_s'] === 5 * 300, 'recalculate duration wrong');

    // Corrections to the date range rebuild geometry from accepted points only.
    $extended = StepsGeolocationTrips::update($pdo, $owner, $trip['id'], [
        'started_at' => gmdate('c', $base),
        'ended_at' => gmdate('c', $base + 9 * 300),
    ]);
    geo_tm_assert((int)$extended['override_json']['point_count'] === 10, 'date edit did not rebuild from the new range');
    geo_tm_assert(($extended['override_json']['overrides']['dates'] ?? false) === true, 'date override not recorded');

    // A bounded refresh is queued by the existing job table.
    $pending = $pdo->prepare("SELECT COUNT(*) FROM geo_processor_runs WHERE user_email = :owner AND kind = 'trips' AND state = 'pending' AND input_from IS NOT NULL AND input_to IS NOT NULL");
    $pending->execute([':owner' => $owner]);
    geo_tm_assert((int)$pending->fetchColumn() >= 1, 'bounded refresh not queued');

    // Split at a selected point, then merge the halves back together.
    $splitAt = gmdate('c', $base + 5 * 300);
    $halves = StepsGeolocationTrips::split($pdo, $owner, $extended['id'], $splitAt);
    geo_tm_assert(count($halves) === 2, 'split did not return two trips');
    geo_tm_assert((int)$halves[0]['override_json']['point_count'] === 5, 'first split half has the wrong points');
    geo_tm_assert((int)$halves[1]['override_json']['point_count'] === 5, 'second split half has the wrong points');
    geo_tm_assert($halves[0]['boundary_reason'] !== '' && $halves[1]['boundary_reason'] === 'user_split', 'split boundary reason missing');

    $merged = StepsGeolocationTrips::merge($pdo, $owner, [$halves[0]['id'], $halves[1]['id']]);
    geo_tm_assert((int)$merged['override_json']['point_count'] === 10, 'merge lost points');
    geo_tm_assert(($merged['override_json']['origin'] ?? '') === 'user_merge', 'merge origin missing');
    geo_tm_assert(StepsGeolocationTrips::find($pdo, $owner, $halves[0]['id']) === null, 'merged source still visible');
    $archivedCount = $pdo->prepare('SELECT COUNT(*) FROM geo_trips WHERE user_email = :owner AND id IN (:a, :b) AND deleted_at IS NOT NULL');
    $archivedCount->execute([':owner' => $owner, ':a' => $halves[0]['id'], ':b' => $halves[1]['id']]);
    geo_tm_assert((int)$archivedCount->fetchColumn() === 2, 'merged sources were not safely retired');

    // Archive keeps the row and its links; delete is a tombstone with an audit trail.
    StepsGeolocationTrips::archive($pdo, $owner, $merged['id']);
    geo_tm_assert(StepsGeolocationTrips::find($pdo, $owner, $merged['id'])['state'] === 'archived', 'archive failed');
    $deletable = StepsGeolocationTrips::create($pdo, $owner, ['title' => 'Delete me', 'point_ids' => array_slice($pointIds, 6, 4)]);
    StepsGeolocationTrips::archive($pdo, $owner, $deletable['id'], true);
    geo_tm_assert(StepsGeolocationTrips::find($pdo, $owner, $deletable['id']) === null, 'deleted trip still readable');
    $tombstone = $pdo->prepare('SELECT state, deleted_at, override_json FROM geo_trips WHERE user_email = :owner AND id = :id');
    $tombstone->execute([':owner' => $owner, ':id' => $deletable['id']]);
    $row = $tombstone->fetch(PDO::FETCH_ASSOC);
    geo_tm_assert($row !== false && $row['deleted_at'] !== null && $row['state'] === 'archived', 'delete did not leave a tombstone');
    geo_tm_assert((json_decode((string)$row['override_json'], true)['origin'] ?? '') === 'user_delete', 'delete provenance missing');

    $events = $pdo->prepare('SELECT event_type, COUNT(*) AS total FROM geo_audit_events WHERE user_email = :owner GROUP BY event_type');
    $events->execute([':owner' => $owner]);
    $audit = array_column($events->fetchAll(PDO::FETCH_ASSOC), 'total', 'event_type');
    foreach (['trip.created', 'trip.updated', 'trip.recalculated', 'trip.split', 'trip.merged', 'trip.archived', 'trip.deleted'] as $event) {
        geo_tm_assert((int)($audit[$event] ?? 0) >= 1, 'missing audit event ' . $event);
    }

    // Owner scoping: a different account cannot read or mutate the trip.
    $otherTripId = StepsGeolocationTrips::uuid();
    $pdo->prepare(
        "INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, duration_s, title, state, boundary_reason)
         VALUES(:id, :owner, :from, :to, ST_SetSRID(ST_GeomFromText('LINESTRING(30.52 50.45, 30.54 50.46)'), 4326), 2200, 3600, 'Other account trip', 'ready', 'first_point')"
    )->execute([':id' => $otherTripId, ':owner' => $other, ':from' => gmdate('c', $base), ':to' => gmdate('c', $base + 3600)]);
    geo_tm_assert(StepsGeolocationTrips::find($pdo, $owner, $otherTripId) === null, 'owner read another account trip');
    $denied = false;
    try {
        StepsGeolocationTrips::update($pdo, $owner, $otherTripId, ['title' => 'stolen']);
    } catch (StepsGeolocationTripError $e) {
        $denied = $e->errorCode === 'trip_not_found';
    }
    geo_tm_assert($denied, 'owner edited another account trip');

    // Canonical location history is byte-for-byte untouched by derived operations.
    $afterChecksum = (string)$pdo->query("SELECT md5(string_agg(id::text || ST_AsText(position) || source_at::text, ',' ORDER BY id)) FROM geo_location_points WHERE user_email = " . $pdo->quote($owner))->fetchColumn();
    geo_tm_assert($pointChecksum === $afterChecksum, 'canonical location points were modified');
    $pointCount = $pdo->prepare('SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner');
    $pointCount->execute([':owner' => $owner]);
    geo_tm_assert((int)$pointCount->fetchColumn() === 10, 'point count changed');

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
    $cleanup($pdo, $other);
}
