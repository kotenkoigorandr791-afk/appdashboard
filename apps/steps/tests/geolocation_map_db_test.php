<?php
/**
 * Integration test for the map viewport API against the local PostGIS database.
 *   php apps/steps/tests/geolocation_map_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_map.php';

function geo_map_db_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-map-owner@example.test';
$viewer = 'qa-map-viewer@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, array $owners): void {
    foreach ($owners as $email) {
        foreach (['geo_location_grants', 'geo_visits', 'geo_stops', 'geo_trips', 'geo_location_points', 'geo_devices', 'geo_places', 'geo_map_preferences'] as $table) {
            $column = $table === 'geo_location_grants' ? 'owner_email' : 'user_email';
            $pdo->prepare("DELETE FROM $table WHERE $column = :owner")->execute([':owner' => $email]);
        }
    }
    $pdo->prepare("DELETE FROM geo_location_grants WHERE viewer_email IN (:a, :b)")->execute([':a' => $owners[0], ':b' => $owners[1]]);
};
$cleanup($pdo, [$owner, $viewer]);

$uuid = static function (): string {
    $b = bin2hex(random_bytes(16));
    return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
};

try {
    $tables = (int)$pdo->query("SELECT COUNT(*) FROM pg_tables WHERE tablename = 'geo_map_preferences'")->fetchColumn();
    geo_map_db_assert($tables === 1, 'migration 002 did not create geo_map_preferences');

    $device = $uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'Map phone', 'android', 'mobile', 'active')")
        ->execute([':id' => $device, ':owner' => $owner]);

    $insert = $pdo->prepare("INSERT INTO geo_location_points(id, user_email, device_id, source_type, source_at, position, accuracy_m, quality_state) VALUES(:id, :owner, :device, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 8, 'accepted')");
    $base = time() - 3600;
    for ($i = 0; $i < 6; $i++) {
        $insert->execute([':id' => $uuid(), ':owner' => $owner, ':device' => $device, ':at' => gmdate('c', $base + $i * 60), ':lon' => 30.52 + $i * 0.0005, ':lat' => 50.45]);
    }

    $placeId = $uuid();
    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, timezone) VALUES(:id, :owner, 'Home', 'home', ST_SetSRID(ST_MakePoint(30.52, 50.45), 4326), 120, 'Europe/Kyiv')")
        ->execute([':id' => $placeId, ':owner' => $owner]);
    $stopId = $uuid();
    $pdo->prepare("INSERT INTO geo_stops(id, user_email, started_at, ended_at, position, radius_m, confidence, state) VALUES(:id, :owner, :from, :to, ST_SetSRID(ST_MakePoint(30.52, 50.45), 4326), 60, 0.8, 'ready')")
        ->execute([':id' => $stopId, ':owner' => $owner, ':from' => gmdate('c', $base), ':to' => gmdate('c', $base + 600)]);
    $pdo->prepare("INSERT INTO geo_visits(id, user_email, place_id, stop_id, arrived_at, departed_at, confidence, state) VALUES(:id, :owner, :place, :stop, :from, :to, 0.8, 'ready')")
        ->execute([':id' => $uuid(), ':owner' => $owner, ':place' => $placeId, ':stop' => $stopId, ':from' => gmdate('c', $base), ':to' => gmdate('c', $base + 600)]);
    $tripId = $uuid();
    $pdo->prepare("INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, state, boundary_reason, processor_version) VALUES(:id, :owner, :from, :to, ST_SetSRID(ST_GeomFromText('LINESTRING(30.52 50.45, 30.521 50.451)'), 4326), 140, 'ready', 'first_point', 'trips-1')")
        ->execute([':id' => $tripId, ':owner' => $owner, ':from' => gmdate('c', $base), ':to' => gmdate('c', $base + 600)]);

    $bbox = StepsGeolocationMap::parseBbox('30.5,50.44,30.54,50.46');
    geo_map_db_assert($bbox !== null, 'fixture bbox invalid');
    $range = ['from' => gmdate('c', $base - 60), 'to' => gmdate('c', $base + 3600)];

    // Raw zoom returns individual points with cursor pagination.
    $detail = StepsGeolocationMap::features($pdo, $owner, $bbox, $range + ['zoom' => 15, 'layers' => ['points', 'trips', 'stops', 'places', 'visits'], 'limit' => 2]);
    geo_map_db_assert($detail['meta']['counts']['points'] === 2 && $detail['meta']['truncated'] === true, 'point pagination wrong');
    geo_map_db_assert(is_array($detail['meta']['next_cursor']), 'next cursor missing');
    $cursor = $detail['meta']['next_cursor'];
    $next = StepsGeolocationMap::features($pdo, $owner, $bbox, $range + ['zoom' => 15, 'layers' => ['points'], 'limit' => 2, 'cursor_at' => $cursor['at'], 'cursor_id' => $cursor['id']]);
    geo_map_db_assert($next['meta']['counts']['points'] === 2, 'cursor page empty');

    $layersSeen = array_column(array_map(static fn(array $f): array => ['layer' => $f['properties']['layer']], $detail['features']), 'layer');
    geo_map_db_assert(in_array('point', $layersSeen, true), 'point layer missing');
    geo_map_db_assert(in_array('trip', $layersSeen, true), 'trip layer missing');
    geo_map_db_assert(in_array('stop', $layersSeen, true), 'stop layer missing');
    geo_map_db_assert(in_array('place', $layersSeen, true), 'place layer missing');
    geo_map_db_assert(in_array('visit', $layersSeen, true), 'visit layer missing');

    // Aggregated zoom returns clusters, heatmap and explored cells.
    $coarse = StepsGeolocationMap::features($pdo, $owner, $bbox, $range + ['zoom' => 10, 'layers' => ['points', 'heatmap', 'speed', 'explored']]);
    $kinds = array_map(static fn(array $f): string => $f['properties']['layer'], $coarse['features']);
    geo_map_db_assert(in_array('point_cluster', $kinds, true), 'point clustering missing');
    geo_map_db_assert(in_array('heatmap', $kinds, true), 'heatmap layer missing');
    geo_map_db_assert(in_array('speed', $kinds, true), 'speed layer missing');
    geo_map_db_assert(in_array('explored', $kinds, true), 'explored layer missing');

    // Precision follows zoom (coordinates are rounded before rendering).
    $point = null;
    foreach ($detail['features'] as $feature) if ($feature['properties']['layer'] === 'point') { $point = $feature; break; }
    geo_map_db_assert($point !== null, 'no raw point to inspect');
    $decimals = strlen(substr((string)$point['geometry']['coordinates'][0], strpos((string)$point['geometry']['coordinates'][0], '.') + 1));
    geo_map_db_assert($decimals <= StepsGeolocationMap::precisionForZoom(15), 'coordinate precision too high: ' . $decimals);

    // Family data is invisible without an unrevoked grant.
    geo_map_db_assert(StepsGeolocationMap::authorize($pdo, $viewer, $owner, $range['from'], $range['to'])['error'] === 'family_access_denied', 'unauthorized family read allowed');
    $grantId = $uuid();
    $pdo->prepare("INSERT INTO geo_location_grants(id, owner_email, viewer_email, access_level, range_from, range_to) VALUES(:id, :owner, :viewer, 'recent', :from, :to)")
        ->execute([':id' => $grantId, ':owner' => $owner, ':viewer' => $viewer, ':from' => $range['from'], ':to' => $range['to']]);
    geo_map_db_assert(StepsGeolocationMap::authorize($pdo, $viewer, $owner, $range['from'], $range['to'])['error'] === null, 'granted family read denied');
    $pdo->prepare('UPDATE geo_location_grants SET revoked_at = now() WHERE id = :id')->execute([':id' => $grantId]);
    geo_map_db_assert(StepsGeolocationMap::authorize($pdo, $viewer, $owner, $range['from'], $range['to'])['error'] === 'family_access_denied', 'revoked grant still allowed');

    // Tile provider settings round-trip per user.
    $saved = StepsGeolocationMap::savePreferences($pdo, $viewer, 'carto_dark', ['trips', 'heatmap']);
    geo_map_db_assert($saved['provider'] === 'carto_dark' && in_array('heatmap', $saved['layers'], true), 'settings not saved');
    geo_map_db_assert(StepsGeolocationMap::preferences($pdo, $viewer)['provider'] === 'carto_dark', 'settings not loaded');
    $fallback = StepsGeolocationMap::savePreferences($pdo, $viewer, 'unknown-provider', null);
    geo_map_db_assert($fallback['provider'] === 'carto_dark', 'invalid provider not rejected');

    echo "ok\n";
} finally {
    $cleanup($pdo, [$owner, $viewer]);
}
