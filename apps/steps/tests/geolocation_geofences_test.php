<?php
require_once __DIR__ . '/../core/geolocation_geofences.php';

function geo_geofences_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$t = StepsGeolocationGeofences::thresholds();
$t['hysteresis_m'] = 30.0;
$t['debounce_points'] = 2.0;
$t['min_dwell_s'] = 60.0;
$t['max_accuracy_m'] = 200.0;

$zone = ['id' => 'z1', 'latitude' => 50.45, 'longitude' => 30.52, 'radius_m' => 100, 'is_active' => true];
$base = 1_800_000_000;
$point = static function (int $offset, float $lat, float $lon, array $extra = []) use ($base): array {
    return array_merge([
        'id' => 'g' . $offset,
        'source_at' => gmdate('c', $base + $offset),
        'latitude' => $lat,
        'longitude' => $lon,
        'accuracy_m' => 6,
        'quality_state' => 'accepted',
    ], $extra);
};
// ~0.0009 degrees latitude is ~100 m at this latitude.
$latOut = 50.4512;
$latIn = 50.45;

// Jitter around the boundary after entering must not spam events.
$jitter = [
    $point(0, $latIn, 30.52),
    $point(60, $latIn, 30.52),
    $point(120, 50.4508, 30.52),   // just outside the raw radius, inside hysteresis band
    $point(180, 50.4507, 30.52),   // still inside radius + hysteresis
    $point(240, 50.4509, 30.52),
    $point(300, 50.4510, 30.52),   // inside hysteresis band, not a confirmed exit
    $point(360, $latIn, 30.52),
];
$result = StepsGeolocationGeofences::evaluate($zone, $jitter, 'inside', $t);
geo_geofences_assert($result['state'] === 'inside', 'jitter changed state');
geo_geofences_assert($result['events'] === [], 'jitter produced ' . count($result['events']) . ' event(s)');

// A real departure produces exactly one exit event after debounce.
$departure = [$point(0, 50.4525, 30.52), $point(120, 50.4530, 30.52), $point(240, 50.4535, 30.52)];
$leave = StepsGeolocationGeofences::evaluate($zone, $departure, 'inside', $t);
geo_geofences_assert(count($leave['events']) === 1 && $leave['events'][0]['type'] === 'exit', 'departure not detected once');
geo_geofences_assert($leave['state'] === 'outside', 'state did not leave');

// Arrival needs the debounce window; a single spike inside is ignored.
$arrival = [$point(0, $latIn, 30.52), $point(60, $latIn, 30.52)];
$enter = StepsGeolocationGeofences::evaluate($zone, $arrival, 'outside', $t);
geo_geofences_assert(count($enter['events']) === 1 && $enter['events'][0]['type'] === 'enter', 'arrival not detected');
geo_geofences_assert($enter['events'][0]['confidence'] > 0, 'confidence missing');

$spike = [$point(0, $latIn, 30.52), $point(60, 50.4535, 30.52)];
geo_geofences_assert(StepsGeolocationGeofences::evaluate($zone, $spike, 'outside', $t)['events'] === [], 'single spike created an entry');

// Disabled zones never emit events.
$disabled = $zone;
$disabled['is_active'] = false;
geo_geofences_assert(StepsGeolocationGeofences::evaluate($disabled, $arrival, 'outside', $t)['events'] === [], 'disabled zone emitted events');

// Accuracy above the threshold is ignored rather than flipping the state.
$noisy = [$point(0, $latIn, 30.52, ['accuracy_m' => 900]), $point(60, $latIn, 30.52, ['accuracy_m' => 900])];
geo_geofences_assert(StepsGeolocationGeofences::evaluate($zone, $noisy, 'outside', $t)['events'] === [], 'noisy samples created an event');

echo "ok\n";
