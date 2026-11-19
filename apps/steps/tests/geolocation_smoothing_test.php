<?php
/**
 * Zone smoothing: dense cloud in, few routes out.
 *
 *   php apps/steps/tests/geolocation_smoothing_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_smoothing.php';

function geo_smooth_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

// Pure helpers first: a jittered straight line simplifies to its endpoints.
$line = [];
for ($i = 0; $i <= 40; $i++) {
    $line[] = [30.5 + $i * 0.0001, 50.45 + (($i % 2) ? 0.00002 : -0.00002)];
}
$simplified = StepsGeolocationSmoothing::simplify($line, 10.0);
geo_smooth_assert(count($simplified) === 2, 'a jittered straight line should collapse to 2 points, got ' . count($simplified));
geo_smooth_assert($simplified[0] === $line[0] && $simplified[1] === $line[40], 'simplification must keep the endpoints');

// A real corner survives the same tolerance.
$corner = [[30.5, 50.45], [30.501, 50.45], [30.501, 50.451]];
geo_smooth_assert(count(StepsGeolocationSmoothing::simplify($corner, 10.0)) === 3, 'a corner must not be flattened');

$points = [];
for ($i = 0; $i < 5; $i++) $points[] = ['source_at' => gmdate('c', 1000 + $i * 60), 'latitude' => 50.45, 'longitude' => 30.5];
for ($i = 0; $i < 5; $i++) $points[] = ['source_at' => gmdate('c', 5000 + $i * 60), 'latitude' => 50.45, 'longitude' => 30.5];
geo_smooth_assert(count(StepsGeolocationSmoothing::passes($points, 600)) === 2, 'a gap must split passes');

$owner = 'qa-geo-smooth@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
$pdo->prepare('DELETE FROM geo_location_points WHERE user_email = :o')->execute([':o' => $owner]);
$pdo->prepare('DELETE FROM geo_smooth_zones WHERE user_email = :o')->execute([':o' => $owner]);
$pdo->prepare('DELETE FROM geo_devices WHERE user_email = :o')->execute([':o' => $owner]);

try {
    $device = StepsGeolocationSmoothing::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :o, 'QA smooth', 'android', 'mobile', 'active')")
        ->execute([':id' => $device, ':o' => $owner]);
    $insert = $pdo->prepare(
        "INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, accuracy_m, quality_state)
         VALUES(:id, :o, :d, :e, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 20, 'accepted')"
    );
    // Three separate passes through a shopping-centre-sized area, each one a
    // chaotic 100-point cloud with roughly 30 m of jitter.
    $base = strtotime('2026-05-01T09:00:00Z');
    $total = 0;
    for ($pass = 0; $pass < 3; $pass++) {
        for ($i = 0; $i < 100; $i++) {
            $jitterLat = (($i * 37) % 11 - 5) * 0.00006;
            $jitterLon = (($i * 53) % 11 - 5) * 0.00009;
            $insert->execute([
                ':id' => StepsGeolocationSmoothing::uuid(), ':o' => $owner, ':d' => $device,
                ':e' => StepsGeolocationSmoothing::uuid(),
                ':at' => gmdate('c', $base + $pass * 3600 + $i * 20),
                ':lon' => 30.51 + $i * 0.00005 + $jitterLon,
                ':lat' => 50.46 + $i * 0.00004 + $jitterLat,
            ]);
            $total++;
        }
    }
    $bbox = ['minLon' => 30.5095, 'minLat' => 50.4595, 'maxLon' => 30.5165, 'maxLat' => 50.4645];
    $result = StepsGeolocationSmoothing::smooth($pdo, $owner, $bbox, gmdate('c', $base - 3600), gmdate('c', $base + 4 * 3600), 60, 600);
    geo_smooth_assert($result['paths'] === 3, 'expected one route per pass, got ' . $result['paths']);
    geo_smooth_assert($result['points'] === $total, 'smoothing should read every point in the zone, got ' . $result['points']);

    $features = StepsGeolocationSmoothing::viewport($pdo, $owner, $bbox, gmdate('c', $base - 3600), gmdate('c', $base + 4 * 3600));
    geo_smooth_assert(count($features) === 3, 'viewport should expose the derived routes');
    foreach ($features as $feature) {
        $coordinates = $feature['geometry']['coordinates'] ?? [];
        geo_smooth_assert(count($coordinates) >= 2 && count($coordinates) < 30, 'a route was not simplified: ' . count($coordinates));
        geo_smooth_assert($feature['properties']['layer'] === 'smooth', 'route layer name changed');
    }

    // Raw point markers inside the zone are replaced by the routes on the map.
    $payload = [
        'type' => 'FeatureCollection',
        'features' => [
            ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [30.512, 50.461]], 'properties' => ['layer' => 'point']],
            ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [31.000, 50.900]], 'properties' => ['layer' => 'point']],
        ],
        'meta' => ['counts' => []],
    ];
    $applied = StepsGeolocationSmoothing::applyToViewport($payload, $features);
    $kept = array_values(array_filter($applied['features'], static fn(array $f): bool => ($f['properties']['layer'] ?? '') === 'point'));
    geo_smooth_assert(count($kept) === 1, 'points inside the zone must be hidden, outside points kept');
    geo_smooth_assert(count($applied['features']) === 4, 'derived routes missing from the viewport');
    geo_smooth_assert(($applied['meta']['counts']['smoothed_points_hidden'] ?? 0) === 1, 'hidden point count missing from the viewport meta');

    // Cluster markers are raw points too, so they must be hidden the same way.
    $clusters = StepsGeolocationSmoothing::applyToViewport([
        'type' => 'FeatureCollection',
        'features' => [
            ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [30.512, 50.461]], 'properties' => ['layer' => 'point_cluster', 'count' => 120]],
            ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [31.000, 50.900]], 'properties' => ['layer' => 'point_cluster', 'count' => 12]],
        ],
        'meta' => ['counts' => []],
    ], $features);
    $keptClusters = array_filter($clusters['features'], static fn(array $f): bool => ($f['properties']['layer'] ?? '') === 'point_cluster');
    geo_smooth_assert(count($keptClusters) === 1, 'a cluster inside the zone must be hidden');

    // The chunk validator has to move when the zones change, otherwise the map
    // answers 304 and keeps drawing the raw cloud after smoothing.
    $signature = StepsGeolocationSmoothing::signature($features);
    geo_smooth_assert($signature !== '' && $signature === StepsGeolocationSmoothing::signature($features), 'zone signature must be stable');
    geo_smooth_assert(StepsGeolocationSmoothing::signature([]) !== $signature, 'zone signature must change when zones appear');

    // Smoothing again replaces the overlapping zone instead of stacking up.
    $again = StepsGeolocationSmoothing::smooth($pdo, $owner, $bbox, gmdate('c', $base - 3600), gmdate('c', $base + 4 * 3600), 80, 600);
    $zones = $pdo->prepare('SELECT COUNT(*) FROM geo_smooth_zones WHERE user_email = :o');
    $zones->execute([':o' => $owner]);
    geo_smooth_assert((int)$zones->fetchColumn() === 1, 're-smoothing must replace the zone');
    geo_smooth_assert($again['paths'] === 3, 're-smoothing changed the route count');

    $removed = StepsGeolocationSmoothing::remove($pdo, $owner, $bbox);
    geo_smooth_assert($removed === 1, 'zone removal failed');
    $paths = $pdo->prepare('SELECT COUNT(*) FROM geo_smooth_paths WHERE user_email = :o');
    $paths->execute([':o' => $owner]);
    geo_smooth_assert((int)$paths->fetchColumn() === 0, 'zone removal must cascade to its routes');

    echo "ok\n";
} finally {
    $pdo->prepare('DELETE FROM geo_location_points WHERE user_email = :o')->execute([':o' => $owner]);
    $pdo->prepare('DELETE FROM geo_smooth_zones WHERE user_email = :o')->execute([':o' => $owner]);
    $pdo->prepare('DELETE FROM geo_devices WHERE user_email = :o')->execute([':o' => $owner]);
}
