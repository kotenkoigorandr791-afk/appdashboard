<?php
require_once __DIR__ . '/../core/geolocation_stops.php';

function geo_stops_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$t = StepsGeolocationStops::thresholds();
$t['radius_m'] = 60.0;
$t['min_duration_s'] = 300.0;
$t['max_gap_s'] = 900.0;
$t['merge_gap_s'] = 600.0;
$t['merge_radius_m'] = 80.0;
$t['min_points'] = 3.0;
$t['max_accuracy_m'] = 200.0;

$base = 1_800_000_000;
$make = static function (int $offset, float $lat, float $lon, array $extra = []) use ($base): array {
    return array_merge([
        'id' => 's' . $offset,
        'device_id' => 'dev-a',
        'source_at' => gmdate('c', $base + $offset),
        'latitude' => $lat,
        'longitude' => $lon,
        'accuracy_m' => 8,
        'quality_state' => 'accepted',
    ], $extra);
};

// A ten minute dwell with GPS jitter inside the radius is one stop, not many.
$jitter = [];
for ($i = 0; $i < 12; $i++) {
    $jitter[] = $make($i * 50, 50.45 + ($i % 3) * 0.00002, 30.52 + ($i % 2) * 0.00002);
}
$detected = StepsGeolocationStops::detect($jitter, $t);
geo_stops_assert(count($detected['stops']) === 1, 'jitter produced ' . count($detected['stops']) . ' stops');
geo_stops_assert($detected['stops'][0]['duration_s'] >= 500, 'dwell duration wrong');
geo_stops_assert($detected['stops'][0]['confidence'] > 0.5, 'confidence missing');

// A long stay is measured from arrival to departure.
$longStay = [];
for ($i = 0; $i < 30; $i++) $longStay[] = $make($i * 300, 50.45, 30.52);
$longDetected = StepsGeolocationStops::detect($longStay, $t);
geo_stops_assert(count($longDetected['stops']) === 1, 'long stay not detected');
geo_stops_assert($longDetected['stops'][0]['duration_s'] === 8700, 'long stay duration wrong: ' . $longDetected['stops'][0]['duration_s']);

// Passing through quickly is movement, not a stop.
$passBy = [$make(0, 50.45, 30.52), $make(120, 50.451, 30.521), $make(240, 50.453, 30.523)];
geo_stops_assert(StepsGeolocationStops::detect($passBy, $t)['stops'] === [], 'short pass-by became a stop');

// Two dwells at the same place split by GPS noise merge into one stop.
$fragmented = array_merge(
    [$make(0, 50.45, 30.52), $make(300, 50.45, 30.52)],
    [$make(420, 50.45, 30.52), $make(720, 50.45, 30.52)],
);
$fragmentedDetected = StepsGeolocationStops::detect($fragmented, $t);
geo_stops_assert(count($fragmentedDetected['stops']) === 1, 'fragmented dwell not merged');
geo_stops_assert($fragmentedDetected['stops'][0]['duration_s'] === 720, 'merged dwell duration wrong');

// Noisy samples above the accuracy threshold are ignored, not treated as a move.
$noisy = $jitter;
$noisy[4]['accuracy_m'] = 900;
geo_stops_assert(count(StepsGeolocationStops::detect($noisy, $t)['stops']) === 1, 'noisy sample created an extra stop');

// Thresholds are configurable.
$strict = $t;
$strict['min_duration_s'] = 3600.0;
geo_stops_assert(StepsGeolocationStops::detect($jitter, $strict)['stops'] === [], 'custom duration threshold ignored');

// Sparse 3-point dwell on a long route must not become a place (adaptive min ~ ceil(sqrt(N)*factor)).
$sparseLong = [];
for ($i = 0; $i < 100; $i++) {
    $lat = 50.40 + $i * 0.001;
    $lon = 30.40 + $i * 0.001;
    $sparseLong[] = $make($i * 60, $lat, $lon);
}
// Inject a short 3-sample dwell mid-route that would pass fixed min_points=3.
$sparseLong[40] = $make(40 * 60, 50.45, 30.52);
$sparseLong[41] = $make(40 * 60 + 180, 50.4501, 30.5201);
$sparseLong[42] = $make(40 * 60 + 360, 50.45005, 30.52005);
$sparseDetected = StepsGeolocationStops::detect($sparseLong, $t);
geo_stops_assert($sparseDetected['stops'] === [], 'sparse dwell on long route became a stop');
geo_stops_assert(($jitterDetected = StepsGeolocationStops::detect($jitter, $t)) && ($jitterDetected['stops'][0]['adaptive_min_points'] ?? 0) >= 3, 'adaptive_min_points missing');

echo "ok\n";
