<?php
/**
 * Integration test for migration 019, corrections and derived matched segments.
 * Run in the app container:
 *   php apps/steps/tests/geolocation_matching_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_quality.php';
require_once dirname(__DIR__) . '/core/geolocation_corrections.php';
require_once dirname(__DIR__) . '/core/geolocation_matching.php';

function geo_match_db_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-matching-db@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
$uuid = static fn(): string => StepsGeolocationQuality::uuid();
$cleanup = static function (PDO $pdo, string $owner): void {
    foreach (['geo_matched_segments', 'geo_quality_corrections', 'geo_match_settings', 'geo_trips', 'geo_location_points', 'geo_devices', 'geo_processor_runs', 'geo_audit_events'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
    }
    $pdo->exec("DELETE FROM geo_match_networks WHERE version = 'qa-matching-1'");
};
$cleanup($pdo, $owner);

try {
    $migration = (int)$pdo->query("SELECT COUNT(*) FROM geo_schema_migrations WHERE version = '019'")->fetchColumn();
    geo_match_db_assert($migration === 1, 'migration 019 not recorded');
    $columns = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'geo_location_points' AND column_name IN ('quality_score','quality_severity','quality_version','quality_details')")->fetchColumn();
    geo_match_db_assert($columns === 4, 'quality evidence columns missing');

    $device = $uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA', 'android', 'mobile', 'active')")
        ->execute([':id' => $device, ':owner' => $owner]);
    $points = [];
    $base = time() - 3600;
    for ($i = 0; $i < 6; $i++) {
        $id = $uuid();
        $points[] = $id;
        $pdo->prepare("INSERT INTO geo_location_points(id, user_email, device_id, source_type, source_at, position, accuracy_m, quality_state) VALUES(:id, :owner, :device, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 12, 'accepted')")
            ->execute([':id' => $id, ':owner' => $owner, ':device' => $device, ':at' => gmdate('c', $base + $i * 60), ':lon' => 30.5 + $i * 0.0005, ':lat' => 50.45]);
    }
    $tripId = $uuid();
    $pdo->prepare("INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, state, processor_version, override_json) VALUES(:id, :owner, :from, :to, ST_SetSRID(ST_GeomFromText('LINESTRING(30.5 50.45,30.5025 50.45)'), 4326), 180, 'ready', 'trips-1', :override)")
        ->execute([':id' => $tripId, ':owner' => $owner, ':from' => gmdate('c', $base), ':to' => gmdate('c', $base + 300), ':override' => json_encode(['point_ids' => $points])]);

    // Corrections are reversible and audited; raw coordinates never change.
    $before = $pdo->prepare('SELECT id, ST_X(position) AS lon, ST_Y(position) AS lat FROM geo_location_points WHERE user_email = :owner ORDER BY source_at');
    $before->execute([':owner' => $owner]);
    $beforeRows = $before->fetchAll(PDO::FETCH_ASSOC);
    $correction = StepsGeolocationCorrections::create($pdo, $owner, [
        'scope_type' => 'points',
        'point_ids' => array_slice($points, 1, 2),
        'effects' => ['exclude_display', 'exclude_export'],
        'reason' => 'qa',
        'note' => 'synthetic',
    ]);
    geo_match_db_assert($correction['point_count'] === 2, 'correction scope wrong');
    $excluded = $pdo->prepare("SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner AND quality_state = 'excluded_by_user'");
    $excluded->execute([':owner' => $owner]);
    geo_match_db_assert((int)$excluded->fetchColumn() === 2, 'correction did not exclude points');
    $after = $pdo->prepare('SELECT id, ST_X(position) AS lon, ST_Y(position) AS lat FROM geo_location_points WHERE user_email = :owner ORDER BY source_at');
    $after->execute([':owner' => $owner]);
    geo_match_db_assert($beforeRows === $after->fetchAll(PDO::FETCH_ASSOC), 'correction moved canonical points');
    StepsGeolocationCorrections::revoke($pdo, $owner, $correction['id']);
    $excluded->execute([':owner' => $owner]);
    geo_match_db_assert((int)$excluded->fetchColumn() === 0, 'undo did not restore states');
    $audit = $pdo->prepare("SELECT COUNT(*) FROM geo_audit_events WHERE user_email = :owner AND event_type = 'location_quality_correction'");
    $audit->execute([':owner' => $owner]);
    geo_match_db_assert((int)$audit->fetchColumn() === 1, 'correction audit missing');

    // Matching settings default privacy-safe and round-trip.
    $settings = StepsGeolocationMatching::settings($pdo, $owner);
    geo_match_db_assert($settings['geometry_mode'] === 'raw', 'unsafe default geometry');
    $saved = StepsGeolocationMatching::saveSettings($pdo, $owner, ['mode' => 'review_required', 'geometry_mode' => 'compare']);
    geo_match_db_assert($saved['mode'] === 'review_required' && $saved['geometry_mode'] === 'compare', 'matching settings not saved');

    // A versioned network and a derived segment co-exist with the raw line.
    $networkId = $uuid();
    $pdo->prepare("INSERT INTO geo_match_networks(id, region_code, kind, name, access, source, version, geometry) VALUES(:id, 'qa', 'road', 'QA Road', 'public', 'fixture', 'qa-matching-1', ST_SetSRID(ST_GeomFromText('LINESTRING(30.5 50.45,30.503 50.45)'), 4326))")
        ->execute([':id' => $networkId]);
    $segmentId = $uuid();
    $pdo->prepare("INSERT INTO geo_matched_segments(id, user_email, trip_id, point_ids, started_at, ended_at, raw_geometry, matched_geometry, mode, confidence, confidence_band, reasons_json, explanation, network_version, matcher_version, config_json, state) VALUES(:id, :owner, :trip, CAST(:points AS uuid[]), :from, :to, ST_SetSRID(ST_GeomFromText('LINESTRING(30.5 50.451,30.5025 50.451)'), 4326), ST_SetSRID(ST_GeomFromText('LINESTRING(30.5 50.45,30.503 50.45)'), 4326), 'road', 0.88, 'high', '[]', 'fixture', 'qa-matching-1', 'matcher-1', '{}', 'matched')")
        ->execute([':id' => $segmentId, ':owner' => $owner, ':trip' => $tripId, ':points' => '{' . implode(',', $points) . '}', ':from' => gmdate('c', $base), ':to' => gmdate('c', $base + 300)]);
    $segments = StepsGeolocationMatching::segments($pdo, $owner, ['trip_id' => $tripId]);
    geo_match_db_assert(count($segments) === 1, 'matched segment not readable');
    geo_match_db_assert(count($segments[0]['raw_geometry']) === 2 && count($segments[0]['matched_geometry']) === 2, 'raw/improved geometries not both returned');
    geo_match_db_assert($segments[0]['raw_geometry'][0][1] !== $segments[0]['matched_geometry'][0][1], 'fixture raw and matched unexpectedly equal');

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
}
