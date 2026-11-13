<?php
/**
 * Timeline integration test against the local PostGIS database.
 *   php apps/steps/tests/geolocation_timeline_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_timeline.php';

function geo_timeline_db_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-timeline@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, string $owner): void {
    foreach (['geo_visits', 'geo_stops', 'geo_trips', 'geo_location_points', 'geo_devices', 'geo_places', 'geo_processor_runs'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
    }
};
$cleanup($pdo, $owner);

$uuid = static function (): string {
    $b = bin2hex(random_bytes(16));
    return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
};

try {
    $device = $uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'Timeline phone', 'android', 'mobile', 'active')")
        ->execute([':id' => $device, ':owner' => $owner]);

    $base = strtotime('2026-11-13T08:00:00Z');
    $insertPoint = $pdo->prepare("INSERT INTO geo_location_points(id, user_email, device_id, source_type, source_at, position, accuracy_m, quality_state) VALUES(:id, :owner, :device, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 8, :state)");
    // Morning points, a one hour canonical gap, then afternoon points.
    for ($i = 0; $i < 4; $i++) {
        $insertPoint->execute([':id' => $uuid(), ':owner' => $owner, ':device' => $device, ':at' => gmdate('c', $base + $i * 600), ':lon' => 30.52 + $i * 0.0004, ':lat' => 50.45, ':state' => 'accepted']);
    }
    for ($i = 0; $i < 4; $i++) {
        $insertPoint->execute([':id' => $uuid(), ':owner' => $owner, ':device' => $device, ':at' => gmdate('c', $base + 3600 + 1800 + $i * 600), ':lon' => 30.53 + $i * 0.0004, ':lat' => 50.46, ':state' => $i === 0 ? 'flagged' : 'accepted']);
    }

    $placeId = $uuid();
    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, color, timezone) VALUES(:id, :owner, 'Cafe', 'favorite', ST_SetSRID(ST_MakePoint(30.5212, 50.45), 4326), 100, '#8b5cf6', 'Europe/Kyiv')")
        ->execute([':id' => $placeId, ':owner' => $owner]);
    $stopId = $uuid();
    $pdo->prepare("INSERT INTO geo_stops(id, user_email, started_at, ended_at, position, radius_m, confidence, state) VALUES(:id, :owner, :from, :to, ST_SetSRID(ST_MakePoint(30.5213, 50.45), 4326), 60, 0.9, 'ready')")
        ->execute([':id' => $stopId, ':owner' => $owner, ':from' => gmdate('c', $base + 1800), ':to' => gmdate('c', $base + 3600)]);
    $visitId = $uuid();
    $pdo->prepare("INSERT INTO geo_visits(id, user_email, place_id, stop_id, arrived_at, departed_at, confidence, state) VALUES(:id, :owner, :place, :stop, :from, :to, 0.9, 'ready')")
        ->execute([':id' => $visitId, ':owner' => $owner, ':place' => $placeId, ':stop' => $stopId, ':from' => gmdate('c', $base + 1800), ':to' => gmdate('c', $base + 3600)]);
    $tripId = $uuid();
    $pdo->prepare("INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, title, state, boundary_reason, processor_version) VALUES(:id, :owner, :from, :to, ST_SetSRID(ST_GeomFromText('LINESTRING(30.52 50.45, 30.54 50.46)'), 4326), 2400, 'Morning drive', 'ready', 'first_point', 'trips-1')")
        ->execute([':id' => $tripId, ':owner' => $owner, ':from' => gmdate('c', $base + 3600), ':to' => gmdate('c', $base + 7200)]);
    $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version) VALUES(:id, :owner, 'trips', 'ready', 'trips-1')")
        ->execute([':id' => $uuid(), ':owner' => $owner]);

    $opts = [
        'view' => 'interval',
        'from' => gmdate('c', $base - 3600),
        'to' => gmdate('c', $base + 86400),
        'timezone' => 'Europe/Kyiv',
        'limit' => 50,
    ];
    $result = StepsGeolocationTimeline::entries($pdo, $owner, $opts);
    $kinds = array_count_values(array_column($result['entries'], 'kind'));
    geo_timeline_db_assert(($kinds['trip'] ?? 0) === 1, 'trip missing from timeline');
    geo_timeline_db_assert(($kinds['stop'] ?? 0) === 1, 'stop missing from timeline');
    geo_timeline_db_assert(($kinds['visit'] ?? 0) === 1, 'visit missing from timeline');
    geo_timeline_db_assert(($kinds['gap'] ?? 0) === 1, 'canonical gap missing from timeline');

    $trip = null;
    foreach ($result['entries'] as $entry) if ($entry['kind'] === 'trip') $trip = $entry;
    geo_timeline_db_assert($trip !== null && is_array($trip['bbox']), 'trip has no bounded map link');
    geo_timeline_db_assert(str_ends_with((string)$trip['local_started_at'], '+02:00') || str_ends_with((string)$trip['local_started_at'], '+03:00'), 'trip not rendered in display timezone');

    // Cursor pagination returns the next page without duplicates.
    $page1 = StepsGeolocationTimeline::entries($pdo, $owner, array_merge($opts, ['limit' => 2]));
    geo_timeline_db_assert(count($page1['entries']) === 2 && $page1['next_cursor'] !== null, 'first page wrong');
    $page2 = StepsGeolocationTimeline::entries($pdo, $owner, array_merge($opts, ['cursor' => $page1['next_cursor'], 'limit' => 2]));
    $ids1 = array_column($page1['entries'], 'id');
    $ids2 = array_column($page2['entries'], 'id');
    geo_timeline_db_assert(array_intersect($ids1, $ids2) === [], 'cursor repeated entries');

    // Search and per-place history are tenant-scoped and filter correctly.
    $search = StepsGeolocationTimeline::entries($pdo, $owner, ['q' => 'morning'] + $opts);
    geo_timeline_db_assert(count($search['entries']) === 1 && $search['entries'][0]['kind'] === 'trip', 'search did not match the trip');
    $placeHistory = StepsGeolocationTimeline::entries($pdo, $owner, ['place_id' => $placeId] + $opts);
    $placeKinds = array_count_values(array_column($placeHistory['entries'], 'kind'));
    geo_timeline_db_assert(($placeKinds['visit'] ?? 0) === 1, 'place history missed the visit');
    geo_timeline_db_assert(($placeKinds['stop'] ?? 0) === 1, 'place history missed the nearby stop');

    // Processing and quality state are visible next to the timeline.
    geo_timeline_db_assert(isset($result['processing']['quality']['flagged']) && (int)$result['processing']['quality']['flagged'] === 1, 'quality state missing');
    $kindsSeen = array_column($result['processing']['runs'], 'kind');
    geo_timeline_db_assert(in_array('trips', $kindsSeen, true), 'processor run state missing');

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
}
