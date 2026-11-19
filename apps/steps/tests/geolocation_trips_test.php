<?php
require_once __DIR__ . '/../core/geolocation_trips.php';

function geo_trips_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$t = StepsGeolocationTrips::thresholds();
$t['max_gap_s'] = 900.0;
$t['min_distance_m'] = 0.0;
$t['min_points'] = 2.0;
$t['max_accuracy_m'] = 200.0;

$base = 1_800_000_000;
$make = static function (int $offset, float $lat, float $lon, array $extra = []) use ($base, $t): array {
    return array_merge([
        'id' => 'p' . $offset,
        'device_id' => 'dev-a',
        'source_at' => gmdate('c', $base + $offset),
        'latitude' => $lat,
        'longitude' => $lon,
        'accuracy_m' => 10,
        'quality_state' => 'accepted',
    ], $extra);
};

// One continuous car journey with walking-like samples in the middle stays one
// trip: speed changes are metadata, not boundaries.
$journey = [];
$lat = 50.0;
for ($i = 0; $i < 20; $i++) {
    // 5..10 m/s wobble mimics traffic lights and slow segments.
    $lat += ($i % 5 === 0) ? 0.00002 : 0.00035;
    $journey[] = $make($i * 60, $lat, 30.0);
}
$result = StepsGeolocationTrips::segment($journey, $t);
geo_trips_assert(count($result['trips']) === 1, 'continuous journey split into ' . count($result['trips']) . ' trips');
geo_trips_assert($result['trips'][0]['boundary_reason'] === 'first_point', 'unexpected first boundary reason');
geo_trips_assert($result['trips'][0]['distance_m'] > 100, 'distance not accumulated');

// A short GPS gap is preserved; a long inactivity gap starts a new trip with a
// recorded reason.
$shortGap = array_merge($journey, [$make(20 * 60 + 300, $lat + 0.001, 30.0)]);
geo_trips_assert(count(StepsGeolocationTrips::segment($shortGap, $t)['trips']) === 1, 'short gap split the trip');

$longGap = array_merge($journey, [
    $make(20 * 60 + 4000, $lat + 0.001, 30.0),
    $make(20 * 60 + 4060, $lat + 0.0012, 30.0),
]);
$split = StepsGeolocationTrips::segment($longGap, $t);
geo_trips_assert(count($split['trips']) === 2, 'long inactivity did not split');
geo_trips_assert($split['trips'][1]['boundary_reason'] === 'long_inactivity', 'missing inactivity reason');

// An impossible discontinuity (kilometres within seconds) is explainable; a
// fast but physically possible sample is not a boundary.
$impossible = [
    $make(0, 50.0, 30.0),
    $make(10, 50.0001, 30.0),
    $make(20, 51.0, 30.0),
    $make(30, 51.0002, 30.0),
];
$jump = StepsGeolocationTrips::segment($impossible, $t);
geo_trips_assert($jump['trips'][0]['boundary_reason'] === 'first_point', 'first trip reason changed');
geo_trips_assert(count($jump['trips']) === 2 && $jump['trips'][1]['boundary_reason'] === 'impossible_discontinuity', 'impossible discontinuity not split');

// A teleport sampled with a wider than one-minute gap (real uploads drift to
// 80-120 s) must still split and must not inflate the trip distance.
$drifted = [
    $make(0, 50.0, 30.0),
    $make(10, 50.0002, 30.0),
    $make(98, 49.5, 30.0),
    $make(108, 49.5002, 30.0),
];
$driftResult = StepsGeolocationTrips::segment($drifted, $t);
geo_trips_assert(count($driftResult['trips']) === 2, 'drifted teleport not split');
geo_trips_assert($driftResult['trips'][1]['boundary_reason'] === 'impossible_discontinuity', 'missing drifted discontinuity reason');
geo_trips_assert($driftResult['trips'][0]['distance_m'] < 1000, 'teleport inflated the trip distance: ' . $driftResult['trips'][0]['distance_m']);

// Explicit user boundary starts a new trip at the chosen sample.
$userBoundary = StepsGeolocationTrips::segment($journey, $t, [$base + 600]);
geo_trips_assert(count($userBoundary['trips']) === 2, 'user boundary ignored');
geo_trips_assert($userBoundary['trips'][1]['boundary_reason'] === 'user_boundary', 'missing user boundary reason');

// Noisy samples above the accuracy threshold are skipped without splitting.
$noisy = $journey;
$noisy[5]['accuracy_m'] = 900;
$kept = StepsGeolocationTrips::segment($noisy, $t);
geo_trips_assert(count($kept['trips']) === 1, 'noisy sample split the trip');

// Rejected points never reach a trip.
$rejected = $journey;
$rejected[3]['quality_state'] = 'rejected';
geo_trips_assert(count(StepsGeolocationTrips::segment($rejected, $t)['trips']) === 1, 'rejected point changed segmentation');

// Device overlap: a second device reporting inside the overlap window is a
// duplicate view, outside it both streams are kept.
$streamA = [$make(0, 50.0, 30.0), $make(60, 50.001, 30.0)];
$streamB = [$make(30, 50.0, 30.0, ['device_id' => 'dev-b', 'id' => 'b1']), $make(1200, 50.01, 30.0, ['device_id' => 'dev-b', 'id' => 'b2'])];
$merged = StepsGeolocationTrips::mergeStreams(['dev-a' => $streamA, 'dev-b' => $streamB], $t);
geo_trips_assert(count($merged) === 3, 'overlap rule kept ' . count($merged) . ' samples');
geo_trips_assert(!in_array('b1', array_column($merged, 'id'), true), 'overlapping duplicate kept');
geo_trips_assert(in_array('b2', array_column($merged, 'id'), true), 'outside-overlap sample dropped');

// Debug GeoJSON has one line per trip plus raw points.
$geo = StepsGeolocationTrips::geojson($journey, $result['trips'], $t);
$lines = array_filter($geo['features'], static fn(array $f): bool => $f['geometry']['type'] === 'LineString');
geo_trips_assert(count($lines) === 1, 'debug output missing trip line');
geo_trips_assert($geo['type'] === 'FeatureCollection', 'debug output is not GeoJSON');

echo "ok\n";
