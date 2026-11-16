<?php
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';

function geo_stats_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$points = [
    ['source_at' => '2026-01-01T08:00:00Z', 'latitude' => 50.4500, 'longitude' => 30.5200, 'quality_state' => 'accepted', 'source_type' => 'mobile', 'accuracy_m' => 5],
    ['source_at' => '2026-01-01T08:10:00Z', 'latitude' => 50.4510, 'longitude' => 30.5210, 'quality_state' => 'accepted', 'source_type' => 'mobile', 'accuracy_m' => 120],
    ['source_at' => '2026-01-01T08:20:00Z', 'latitude' => 50.4520, 'longitude' => 30.5220, 'quality_state' => 'flagged', 'source_type' => 'import', 'accuracy_m' => null],
    ['source_at' => '2026-01-01T08:30:00Z', 'latitude' => 50.4530, 'longitude' => 30.5230, 'quality_state' => 'rejected', 'source_type' => 'import', 'accuracy_m' => 10],
    ['source_at' => '2026-01-02T09:00:00Z', 'latitude' => 50.4540, 'longitude' => 30.5240, 'quality_state' => 'accepted', 'source_type' => 'api', 'accuracy_m' => 80],
];
$daily = StepsGeolocationStatistics::groupDaily($points, 'UTC', [
    ['day' => '2026-01-01', 'code' => 'UA', 'name' => 'Ukraine', 'count' => 3],
], [
    ['day' => '2026-01-01', 'code' => 'kyiv', 'name' => 'Kyiv', 'count' => 3],
]);
geo_stats_assert(count($daily) === 2, 'daily grouping should produce two days');
geo_stats_assert($daily['2026-01-01']['point_count'] === 4, 'point count per day wrong');
geo_stats_assert($daily['2026-01-01']['accepted_count'] === 2 && $daily['2026-01-01']['flagged_count'] === 1 && $daily['2026-01-01']['rejected_count'] === 1, 'quality counts wrong');
geo_stats_assert($daily['2026-01-01']['country_code'] === 'UA' && $daily['2026-01-01']['city_name'] === 'Kyiv', 'boundary assignment missing');
geo_stats_assert($daily['2026-01-01']['distance_m'] > 0, 'within-day distance missing');
geo_stats_assert($daily['2026-01-02']['distance_m'] == 0, 'cross-day distance must not be counted');
geo_stats_assert($daily['2026-01-01']['active_seconds'] === 1200, 'active time wrong: ' . $daily['2026-01-01']['active_seconds']);
geo_stats_assert($daily['2026-01-01']['sources']['import'] === 2, 'source counts wrong');
geo_stats_assert($daily['2026-01-01']['accuracy'][4] === 1, 'accuracy bucket <=200 wrong');
geo_stats_assert($daily['2026-01-01']['accuracy'][6] === 1, 'missing accuracy bucket wrong');

$sequence = StepsGeolocationStatistics::countrySequences([
    ['day' => '2026-02-01', 'country_code' => 'UA', 'country_name' => 'Ukraine'],
    ['day' => '2026-02-02', 'country_code' => 'UA', 'country_name' => 'Ukraine'],
    ['day' => '2026-02-04', 'country_code' => 'UA', 'country_name' => 'Ukraine'],
    ['day' => '2026-02-05', 'country_code' => 'PL', 'country_name' => 'Poland'],
]);
geo_stats_assert(count($sequence) === 3, 'missing calendar day should split a stay sequence');
geo_stats_assert($sequence[0]['days'] === 2 && $sequence[1]['days'] === 1 && $sequence[2]['days'] === 1, 'stay sequence day counts wrong');

$rows = [];
for ($i = 0; $i < 190; $i++) {
    $rows[] = ['day' => gmdate('Y-m-d', strtotime('2025-01-01') + $i * 86400), 'country_code' => 'UA', 'country_name' => 'Ukraine'];
}
$warnings = StepsGeolocationStatistics::residencyWarnings($rows, 183, 183);
geo_stats_assert(count($warnings) === 1, '183-day warning missing');
geo_stats_assert($warnings[0]['days'] === 183, 'warning window size wrong');
geo_stats_assert(StepsGeolocationStatistics::residencyWarnings($rows, 183, 250) === [], 'threshold above window must not warn');

$serialized = json_encode(array_merge($daily, $sequence, $warnings));
foreach (['transport', 'mode_of_transport', 'activity_type'] as $forbidden) {
    geo_stats_assert(stripos($serialized, $forbidden) === false, 'statistics leaked transport concept: ' . $forbidden);
}

echo "ok\n";
