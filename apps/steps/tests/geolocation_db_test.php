<?php
/**
 * Integration test against the real local PostgreSQL/PostGIS database.
 *
 * Run inside the app image on the app network:
 *   php apps/steps/tests/geolocation_db_test.php
 * The test owns one fixture account, cleans it before and after, and never
 * touches other users' rows.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_quality.php';
require_once dirname(__DIR__) . '/core/geolocation_trips.php';

function geo_db_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-geolocation-db@example.test';
$runId = bin2hex(random_bytes(4));

$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, string $owner): void {
    foreach (['geo_location_points', 'geo_trips', 'geo_devices', 'geo_processor_runs', 'geo_audit_events'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
    }
};
$cleanup($pdo, $owner);

try {
    // PostGIS and the migrated schema are actually present.
    $version = (string)$pdo->query('SELECT postgis_version()')->fetchColumn();
    geo_db_assert($version !== '', 'PostGIS is unavailable');
    $tables = (int)$pdo->query("SELECT COUNT(*) FROM pg_tables WHERE tablename LIKE 'geo_%'")->fetchColumn();
    geo_db_assert($tables >= 20, 'geo tables missing: ' . $tables);

    $devices = [
        'active' => StepsGeolocationTrips::uuid(),
        'paused' => StepsGeolocationTrips::uuid(),
        'revoked' => StepsGeolocationTrips::uuid(),
        'deleted' => StepsGeolocationTrips::uuid(),
    ];
    $insertDevice = $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status, credential_hash) VALUES(:id, :owner, :name, 'android', 'mobile', :status, :credential)");
    foreach ($devices as $status => $id) {
        $insertDevice->execute([
            ':id' => $id,
            ':owner' => $owner,
            ':name' => 'QA ' . $status,
            ':status' => $status,
            ':credential' => $status === 'active' ? hash('sha256', 'qa') : null,
        ]);
    }
    $statuses = $pdo->prepare('SELECT status, COUNT(*) FROM geo_devices WHERE user_email = :owner GROUP BY status');
    $statuses->execute([':owner' => $owner]);
    $byStatus = array_column($statuses->fetchAll(PDO::FETCH_ASSOC), 'count', 'status');
    geo_db_assert(count($byStatus) === 4, 'device fixture incomplete');
    geo_db_assert((int)$byStatus['active'] === 1 && (int)$byStatus['deleted'] === 1, 'device statuses wrong');

    // Tombstones stay queryable for audit but never count as live devices.
    $live = $pdo->prepare("SELECT COUNT(*) FROM geo_devices WHERE user_email = :owner AND status <> 'deleted'");
    $live->execute([':owner' => $owner]);
    geo_db_assert((int)$live->fetchColumn() === 3, 'deleted device still treated as live');

    // Status rules are the same ones the API enforces.
    geo_db_assert(StepsGeolocationDomain::deviceAcceptsPoints('active'), 'active device rejected points');
    geo_db_assert(!StepsGeolocationDomain::deviceAcceptsPoints('paused'), 'paused device accepted points');
    geo_db_assert(!StepsGeolocationDomain::deviceAcceptsPoints('revoked'), 'revoked device accepted points');
    geo_db_assert(StepsGeolocationDomain::canTransitionDevice('paused', 'active'), 'paused device cannot resume');
    geo_db_assert(!StepsGeolocationDomain::canTransitionDevice('deleted', 'active'), 'deleted device reactivated');
    // Revoked devices come back only through the explicit enroll action.
    geo_db_assert(StepsGeolocationDomain::canTransitionDevice('revoked', 'active'), 'revoked device cannot re-enroll');

    // Points belong to their owner's device: a cross-account device id is rejected by the FK.
    $insertPoint = $pdo->prepare(
        "INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, accuracy_m)
         VALUES(:id, :owner, :device, :event, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), :accuracy)"
    );
    $base = time() - 3600;
    $pointIds = [];
    for ($i = 0; $i < 12; $i++) {
        $id = StepsGeolocationTrips::uuid();
        $pointIds[] = $id;
        $insertPoint->execute([
            ':id' => $id,
            ':owner' => $owner,
            ':device' => $devices['active'],
            ':event' => StepsGeolocationTrips::uuid(),
            ':at' => gmdate('c', $base + $i * 60),
            ':lat' => 50.4501 + $i * 0.0006,
            ':lon' => 30.5234,
            ':accuracy' => $i === 4 ? 50 : 8,
        ]);
    }
    $crossOwner = false;
    try {
        $insertPoint->execute([
            ':id' => StepsGeolocationTrips::uuid(),
            ':owner' => 'other-geolocation@example.test',
            ':device' => $devices['active'],
            ':event' => StepsGeolocationTrips::uuid(),
            ':at' => gmdate('c', $base),
            ':lat' => 50.45,
            ':lon' => 30.52,
            ':accuracy' => 8,
        ]);
    } catch (PDOException $e) {
        $crossOwner = true;
    }
    geo_db_assert($crossOwner, 'point accepted for a device owned by another account');

    // Duplicate client event ids are absorbed by the unique index.
    $event = StepsGeolocationTrips::uuid();
    $dup = $pdo->prepare(
        "INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, accuracy_m)
         VALUES(:id, :owner, :device, :event, 'mobile', :at, ST_SetSRID(ST_MakePoint(30.5, 50.4), 4326), 8)
         ON CONFLICT (user_email, device_id, client_event_id) WHERE client_event_id IS NOT NULL DO NOTHING"
    );
    $dup->execute([':id' => StepsGeolocationTrips::uuid(), ':owner' => $owner, ':device' => $devices['active'], ':event' => $event, ':at' => gmdate('c', $base + 600)]);
    $dup->execute([':id' => StepsGeolocationTrips::uuid(), ':owner' => $owner, ':device' => $devices['active'], ':event' => $event, ':at' => gmdate('c', $base + 600)]);
    $dupCount = $pdo->prepare('SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner AND client_event_id = :event');
    $dupCount->execute([':owner' => $owner, ':event' => $event]);
    geo_db_assert((int)$dupCount->fetchColumn() === 1, 'duplicate client event stored twice');

    // PostGIS distance is usable for candidate selection.
    $meters = (float)$pdo->query("SELECT ST_DistanceSphere(ST_MakePoint(30, 50), ST_MakePoint(30, 51))")->fetchColumn();
    geo_db_assert($meters > 110000 && $meters < 112000, 'unexpected PostGIS distance: ' . $meters);

    // Quality decisions persist beside the point without rewriting geometry.
    $quality = $pdo->prepare('SELECT id, source_at, accuracy_m, quality_state, ST_Y(position) AS latitude, ST_X(position) AS longitude FROM geo_location_points WHERE user_email = :owner ORDER BY source_at ASC');
    $quality->execute([':owner' => $owner]);
    $rows = $quality->fetchAll(PDO::FETCH_ASSOC);
    $previous = null;
    $flagged = 0;
    $update = $pdo->prepare('UPDATE geo_location_points SET quality_state = :state, quality_reason = :reason, correction_json = :correction WHERE id = :id');
    foreach ($rows as $row) {
        $decision = StepsGeolocationQuality::evaluate($row, $previous);
        if ($decision['state'] === 'flagged') $flagged++;
        $update->execute([
            ':state' => $decision['state'],
            ':reason' => $decision['reason'],
            ':correction' => json_encode(['flags' => $decision['flags'], 'processor_version' => StepsGeolocationQuality::PROCESSOR_VERSION]),
            ':id' => $row['id'],
        ]);
        $previous = $row;
    }
    $flaggedCount = $pdo->prepare('SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner AND quality_state = :state');
    $flaggedCount->execute([':owner' => $owner, ':state' => 'flagged']);
    geo_db_assert((int)$flaggedCount->fetchColumn() === $flagged, 'quality states not persisted');
    $geometryIntact = $pdo->prepare('SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner AND position IS NOT NULL');
    $geometryIntact->execute([':owner' => $owner]);
    geo_db_assert((int)$geometryIntact->fetchColumn() === count($rows), 'quality pass dropped geometry');

    // Segmentation over the accepted points produces a persisted, explainable trip.
    $accepted = $pdo->prepare("SELECT id, device_id, source_at, accuracy_m, quality_state, ST_Y(position) AS latitude, ST_X(position) AS longitude FROM geo_location_points WHERE user_email = :owner AND quality_state = 'accepted' ORDER BY source_at");
    $accepted->execute([':owner' => $owner]);
    $segment = StepsGeolocationTrips::segment($accepted->fetchAll(PDO::FETCH_ASSOC));
    geo_db_assert(count($segment['trips']) >= 1, 'segmentation produced no trip');
    $trip = $segment['trips'][0];
    $ids = implode(',', array_map(static fn(string $id): string => "'" . $id . "'", $trip['point_ids']));
    $shape = $pdo->query("SELECT ST_AsGeoJSON(ST_MakeLine(position ORDER BY source_at)) AS geojson, COALESCE(ST_Length(ST_MakeLine(position ORDER BY source_at)::geography), 0) AS distance_m FROM geo_location_points WHERE user_email = " . $pdo->quote($owner) . " AND id IN ($ids)")->fetch(PDO::FETCH_ASSOC);
    geo_db_assert(!empty($shape['geojson']), 'trip geometry could not be built');
    $tripId = StepsGeolocationTrips::uuid();
    $insertTrip = $pdo->prepare("INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, state, boundary_reason, processor_version, override_json) VALUES(:id, :owner, :from, :to, ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326), :distance, 'ready', :reason, :version, :override)");
    $insertTrip->execute([
        ':id' => $tripId,
        ':owner' => $owner,
        ':from' => $trip['started_at'],
        ':to' => $trip['ended_at'],
        ':geojson' => $shape['geojson'],
        ':distance' => (float)$shape['distance_m'],
        ':reason' => $trip['boundary_reason'],
        ':version' => StepsGeolocationTrips::PROCESSOR_VERSION,
        ':override' => json_encode(['source' => 'auto', 'point_ids' => $trip['point_ids']]),
    ]);
    $stored = $pdo->prepare('SELECT boundary_reason, processor_version, ST_IsValid(geometry) AS valid FROM geo_trips WHERE user_email = :owner AND id = :id');
    $stored->execute([':owner' => $owner, ':id' => $tripId]);
    $storedTrip = $stored->fetch(PDO::FETCH_ASSOC);
    geo_db_assert($storedTrip !== false, 'trip not stored');
    geo_db_assert($storedTrip['processor_version'] === StepsGeolocationTrips::PROCESSOR_VERSION, 'processor version missing');
    geo_db_assert($storedTrip['valid'] === true || $storedTrip['valid'] === 1 || $storedTrip['valid'] === 't', 'trip geometry invalid');

    // Durable job markers are deduplicated while a run is pending.
    $first = StepsGeolocationTrips::enqueue($pdo, $owner);
    $second = StepsGeolocationTrips::enqueue($pdo, $owner);
    geo_db_assert($first && !$second, 'trip job marker not deduplicated');

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
}
