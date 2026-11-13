<?php
require_once dirname(__DIR__) . '/core/geolocation_quality.php';
function geo_quality_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$now = time();
$at = static fn(int $offsetSeconds): string => gmdate('c', $now + $offsetSeconds);
$point = static function (array $overrides = []) use ($now, $at): array {
    return array_merge([
        'latitude' => 50.4501,
        'longitude' => 30.5234,
        'source_at' => $at(-60),
        'accuracy_m' => 10,
    ], $overrides);
};

// Trustworthy sample.
$ok = StepsGeolocationQuality::evaluate($point(), null);
geo_quality_assert($ok['state'] === 'accepted', 'valid point not accepted: ' . $ok['reason']);

// Impossible coordinates are rejected, not silently dropped.
$bad = StepsGeolocationQuality::evaluate($point(['latitude' => 120.0]), null);
geo_quality_assert($bad['state'] === 'rejected' && $bad['reason'] === 'invalid_coordinates', 'out-of-range latitude accepted');
$badTime = StepsGeolocationQuality::evaluate($point(['source_at' => 'not-a-date']), null);
geo_quality_assert($badTime['state'] === 'rejected' && $badTime['reason'] === 'invalid_timestamp', 'invalid timestamp accepted');

// Missing optional fields are tolerated and flagged, never fatal.
$missing = StepsGeolocationQuality::evaluate($point(['accuracy_m' => null]), null);
geo_quality_assert($missing['state'] === 'flagged' && in_array('accuracy_missing', $missing['flags'], true), 'missing accuracy not explained');
$weak = StepsGeolocationQuality::evaluate($point(['accuracy_m' => 900]), null);
geo_quality_assert($weak['state'] === 'flagged' && in_array('low_accuracy', $weak['flags'], true), 'low accuracy not flagged');

// Duplicate timestamps and future timestamps are explainable flags.
$prev = ['latitude' => 50.4501, 'longitude' => 30.5234, 'source_at' => $at(-120)];
$dup = StepsGeolocationQuality::evaluate($point(), ['latitude' => 50.4501, 'longitude' => 30.5234, 'source_at' => $at(-60)]);
geo_quality_assert(in_array('duplicate_timestamp', $dup['flags'], true), 'duplicate timestamp not flagged');
$future = StepsGeolocationQuality::evaluate($point(['source_at' => $at(3600)]), null);
geo_quality_assert(in_array('timestamp_future', $future['flags'], true), 'future timestamp not flagged');

// An impossible jump is flagged but keeps its coordinates for audit.
$jump = StepsGeolocationQuality::evaluate($point(['latitude' => 51.5]), $prev);
geo_quality_assert($jump['state'] === 'flagged' && in_array('jump', $jump['flags'], true), 'jump not flagged');
$speed = StepsGeolocationQuality::evaluate($point(['latitude' => 51.5]), $prev);
geo_quality_assert(in_array('impossible_speed', $speed['flags'], true), 'impossible speed not flagged');

// Valid high-speed travel (a flight) must not become a quality failure.
$flightPrev = ['latitude' => 50.0, 'longitude' => 30.0, 'source_at' => $at(-3600)];
$flight = ['latitude' => 60.0, 'longitude' => 30.0, 'source_at' => $at(0), 'accuracy_m' => 15];
$flying = StepsGeolocationQuality::evaluate($flight, $flightPrev);
geo_quality_assert($flying['state'] === 'accepted', 'valid high-speed travel flagged: ' . $flying['reason']);

// Thresholds are configurable and bounded.
$strict = ['max_accuracy_m' => 5.0, 'max_speed_mps' => 450.0, 'max_jump_m' => 5000.0, 'jump_window_s' => 60.0, 'max_future_s' => 300.0, 'max_age_days' => 3650.0];
$strictResult = StepsGeolocationQuality::evaluate($point(['accuracy_m' => 20]), null, $strict);
geo_quality_assert($strictResult['state'] === 'flagged', 'custom accuracy threshold ignored');
$defaults = StepsGeolocationQuality::thresholds();
geo_quality_assert($defaults['max_accuracy_m'] >= 5.0 && $defaults['max_speed_mps'] >= 10.0, 'threshold bounds invalid');
geo_quality_assert(StepsGeolocationQuality::haversineMeters(50.0, 30.0, 50.0, 30.0) == 0.0, 'zero distance wrong');
geo_quality_assert(StepsGeolocationQuality::haversineMeters(50.0, 30.0, 51.0, 30.0) > 100000, 'haversine distance too small');
echo "ok\n";
