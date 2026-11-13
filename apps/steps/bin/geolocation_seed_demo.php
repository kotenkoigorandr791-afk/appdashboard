<?php
/**
 * Seeds a self-contained demo account for manual testing.
 *
 *   php apps/steps/bin/geolocation_seed_demo.php --owner=demo@steps.local
 *
 * Re-running replaces only this account's geolocation rows. Raw points keep
 * their geometry; quality flags and a segmented trip are derived on top.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_quality.php';
require_once dirname(__DIR__) . '/core/geolocation_trips.php';

$options = getopt('', ['owner::']);
$owner = isset($options['owner']) && is_string($options['owner']) && trim($options['owner']) !== ''
    ? strtolower(trim($options['owner']))
    : 'demo@steps.local';

$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$pdo->beginTransaction();
try {
    foreach (['geo_location_points', 'geo_trips', 'geo_devices', 'geo_processor_runs', 'geo_audit_events'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
    }

    $phone = StepsGeolocationTrips::uuid();
    $watch = StepsGeolocationTrips::uuid();
    $device = $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status, last_seen_at) VALUES(:id, :owner, :name, :platform, 'mobile', :status, now())");
    $device->execute([':id' => $phone, ':owner' => $owner, ':name' => 'Pixel demo', ':platform' => 'android', ':status' => 'active']);
    $device->execute([':id' => $watch, ':owner' => $owner, ':name' => 'Watch demo', ':platform' => 'android', ':status' => 'paused']);

    $base = time() - 7200;
    $insert = $pdo->prepare(
        "INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, device_sequence, source_type, source_at, position, accuracy_m)
         VALUES(:id, :owner, :device, :event, :sequence, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), :accuracy)
         RETURNING id"
    );
    $points = [];
    $lat = 50.4501;
    $lon = 30.5234;
    for ($i = 0; $i < 30; $i++) {
        // A 20 minute stop in the middle creates a second, explainable trip.
        $offset = $i * 60 + ($i >= 15 ? 1200 : 0);
        $lat += 0.0004;
        $lon += 0.00012;
        $insert->execute([
            ':id' => StepsGeolocationTrips::uuid(),
            ':owner' => $owner,
            ':device' => $phone,
            ':event' => StepsGeolocationTrips::uuid(),
            ':sequence' => $i + 1,
            ':at' => gmdate('c', $base + $offset),
            ':lat' => $lat,
            ':lon' => $lon,
            ':accuracy' => $i === 7 ? 320 : 6 + ($i % 4),
        ]);
        $points[] = $insert->fetchColumn();
    }

    // A nearby second device sample during the first trip: suppressed as overlap.
    $insert->execute([
        ':id' => StepsGeolocationTrips::uuid(),
        ':owner' => $owner,
        ':device' => $watch,
        ':event' => StepsGeolocationTrips::uuid(),
        ':sequence' => 1,
        ':at' => gmdate('c', $base + 300),
        ':lat' => $lat,
        ':lon' => $lon,
        ':accuracy' => 9,
    ]);

    $read = $pdo->prepare('SELECT id, device_id, source_at, accuracy_m, quality_state, ST_Y(position) AS latitude, ST_X(position) AS longitude FROM geo_location_points WHERE user_email = :owner ORDER BY source_at');
    $read->execute([':owner' => $owner]);
    $rows = $read->fetchAll(PDO::FETCH_ASSOC);

    $update = $pdo->prepare('UPDATE geo_location_points SET quality_state = :state, quality_reason = :reason, correction_json = :correction WHERE id = :id');
    $previous = null;
    $flagged = 0;
    $accepted = 0;
    $usable = [];
    foreach ($rows as $row) {
        $decision = StepsGeolocationQuality::evaluate($row, $previous);
        if ($decision['state'] === 'flagged') $flagged++;
        if ($decision['state'] === 'accepted') {
            $accepted++;
            $usable[] = $row;
        }
        $update->execute([
            ':state' => $decision['state'],
            ':reason' => $decision['reason'],
            ':correction' => json_encode(['flags' => $decision['flags'], 'processor_version' => StepsGeolocationQuality::PROCESSOR_VERSION]),
            ':id' => $row['id'],
        ]);
        $previous = $row;
    }

    $streams = [];
    foreach ($usable as $row) $streams[$row['device_id']][] = $row;
    $segment = StepsGeolocationTrips::segment(StepsGeolocationTrips::mergeStreams($streams));
    foreach ($segment['trips'] as $index => $trip) {
        $ids = implode(',', array_map(static fn(string $id): string => "'" . $id . "'", $trip['point_ids']));
        $insertTrip = $pdo->prepare("INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, title, state, boundary_reason, processor_version, override_json) SELECT :id, :owner, :from, :to, ST_MakeLine(position ORDER BY source_at), COALESCE(ST_Length(ST_MakeLine(position ORDER BY source_at)::geography), 0), :title, 'ready', :reason, :version, :override FROM geo_location_points WHERE user_email = :owner AND id IN ($ids)");
        $insertTrip->execute([
            ':id' => StepsGeolocationTrips::uuid(),
            ':owner' => $owner,
            ':from' => $trip['started_at'],
            ':to' => $trip['ended_at'],
            ':title' => 'Demo trip ' . ($index + 1),
            ':reason' => $trip['boundary_reason'],
            ':version' => StepsGeolocationTrips::PROCESSOR_VERSION,
            ':override' => json_encode(['source' => 'seed', 'point_ids' => $trip['point_ids']]),
        ]);
    }

    $audit = $pdo->prepare("INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :owner, 'demo.seeded', :context)");
    $audit->execute([':id' => StepsGeolocationTrips::uuid(), ':owner' => $owner, ':context' => json_encode(['devices' => 2, 'points' => count($rows), 'trip_count' => count($segment['trips'])])]);

    $pdo->commit();
    fwrite(STDOUT, "seeded {$owner}: 2 devices, " . count($rows) . " points ({$accepted} accepted, {$flagged} flagged), " . count($segment['trips']) . " trip(s)\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}
