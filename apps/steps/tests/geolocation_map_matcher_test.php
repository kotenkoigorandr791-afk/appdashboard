<?php
/**
 * Deterministic situation matrix for the conservative matcher (task 34).
 * Pure PHP: no database, no network, no external service.
 */
require_once dirname(__DIR__) . '/core/geolocation_map_matcher.php';

function geo_match_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function geo_match_track(float $baseLon, float $baseLat, float $bearingDeg, float $lengthM, int $count, float $offsetM = 0.0, float $jitterM = 0.0, int $stepS = 60, int $start = 1700000000, string $source = 'mobile'): array
{
    $points = [];
    $rad = deg2rad($bearingDeg);
    for ($i = 0; $i < $count; $i++) {
        $distance = $count > 1 ? $lengthM * $i / ($count - 1) : 0;
        $east = sin($rad) * $distance + ($offsetM * cos($rad));
        $north = cos($rad) * $distance - ($offsetM * sin($rad));
        $north += (($i % 2) ? 1 : -1) * $jitterM;
        $east += (($i % 3) ? 1 : -1) * $jitterM * 0.5;
        $points[] = [
            'id' => sprintf('00000000-0000-4000-8000-%012d', $i + 1),
            'latitude' => $baseLat + $north / 111320.0,
            'longitude' => $baseLon + $east / (111320.0 * cos(deg2rad($baseLat))),
            'source_at' => gmdate('c', $start + $i * $stepS),
            'accuracy_m' => 12,
            'quality_state' => 'accepted',
            'source_type' => $source,
        ];
    }
    return $points;
}

function geo_match_line(float $baseLon, float $baseLat, float $bearingDeg, float $lengthM, float $offsetM = 0.0): array
{
    $rad = deg2rad($bearingDeg);
    $line = [];
    for ($i = 0; $i <= 20; $i++) {
        $distance = $lengthM * $i / 20;
        $east = sin($rad) * $distance + ($offsetM * cos($rad));
        $north = cos($rad) * $distance - ($offsetM * sin($rad));
        $line[] = [$baseLon + $east / (111320.0 * cos(deg2rad($baseLat))), $baseLat + $north / 111320.0];
    }
    return $line;
}

function geo_match_network(string $id, string $kind, array $line, string $access = 'public'): array
{
    return ['id' => $id, 'kind' => $kind, 'access' => $access, 'version' => 'fixture-1', 'geometry' => $line];
}

$lon = 30.0;
$lat = 50.0;

// 1. Noisy urban trace along one connected road.
$urban = geo_match_track($lon, $lat, 90, 900, 12, 0, 7);
$road = geo_match_network('road-1', 'road', geo_match_line($lon, $lat, 90, 1000));
$r = StepsGeolocationMapMatcher::match($urban, [$road]);
geo_match_assert($r['segments'][0]['state'] === 'matched' && $r['segments'][0]['mode'] === 'road', 'urban road not matched');
geo_match_assert($r['segments'][0]['raw_geometry'] === array_map(static fn($p) => [$p['longitude'], $p['latitude']], $urban), 'raw geometry changed');

// 2. Lawn/building crossing with a connected road within accuracy.
$lawn = geo_match_track($lon, $lat, 90, 500, 10, 18, 4);
foreach ($lawn as &$lawnPoint) $lawnPoint['accuracy_m'] = 26;
unset($lawnPoint);
$r = StepsGeolocationMapMatcher::match($lawn, [$road]);
geo_match_assert($r['segments'][0]['state'] === 'matched', 'supported lawn crossing not corrected');
geo_match_assert(count($r['segments'][0]['matched_geometry']) >= 2, 'matched shape missing');

// 3. Two parallel roads with equal evidence: abstain.
$parallel = geo_match_track($lon, $lat, 90, 500, 10, 0, 2);
foreach ($parallel as &$parallelPoint) $parallelPoint['accuracy_m'] = 30;
unset($parallelPoint);
$r = StepsGeolocationMapMatcher::match($parallel, [
    geo_match_network('road-a', 'road', geo_match_line($lon, $lat, 90, 600, 20)),
    geo_match_network('road-b', 'road', geo_match_line($lon, $lat, 90, 600, -20)),
]);
geo_match_assert($r['segments'][0]['state'] === 'abstained', 'parallel roads were guessed');
geo_match_assert(in_array('parallel_network_ambiguity', $r['segments'][0]['reasons'], true), 'parallel reason missing: ' . json_encode($r['segments'][0]['reasons']));

// 4. Urban canyon discontinuity: impossible speed abstains.
$canyon = geo_match_track($lon, $lat, 90, 300, 4, 0, 0, 5);
$canyon[] = array_merge($canyon[3], ['id' => '00000000-0000-4000-8000-000000000099', 'latitude' => $lat + 0.02, 'longitude' => $lon + 0.02, 'source_at' => gmdate('c', 1700000015 + 5)]);
$r = StepsGeolocationMapMatcher::match($canyon, [$road]);
geo_match_assert($r['segments'][0]['state'] === 'abstained', 'impossible jump matched');

// 5. Pedestrian park footpath.
$park = geo_match_track($lon, $lat, 45, 400, 10, 0, 4);
$r = StepsGeolocationMapMatcher::match($park, [geo_match_network('path-1', 'path', geo_match_line($lon, $lat, 45, 500))]);
geo_match_assert($r['segments'][0]['state'] === 'matched' && $r['segments'][0]['mode'] === 'path', 'park path not matched');

// 6. Legitimate open-grass movement with no mapped path.
$r = StepsGeolocationMapMatcher::match($park, []);
geo_match_assert($r['segments'][0]['state'] === 'abstained' && in_array('network_missing', $r['segments'][0]['reasons'], true), 'open ground was snapped');

// 7. Forest trail keeps trail geometry; a far road is not used.
$trail = geo_match_track($lon, $lat, 20, 700, 12, 0, 5);
$r = StepsGeolocationMapMatcher::match($trail, [geo_match_network('trail-1', 'path', geo_match_line($lon, $lat, 20, 800))]);
geo_match_assert($r['segments'][0]['state'] === 'matched' && $r['segments'][0]['mode'] === 'path', 'trail not matched to path');

// 8. Cycling beside a road with a parallel cycleway: no transport label, abstain on ambiguity.
$cycle = geo_match_track($lon, $lat, 90, 500, 10, 0, 2);
foreach ($cycle as &$cyclePoint) $cyclePoint['accuracy_m'] = 30;
unset($cyclePoint);
$r = StepsGeolocationMapMatcher::match($cycle, [
    geo_match_network('road', 'road', geo_match_line($lon, $lat, 90, 600, 12)),
    geo_match_network('cycle', 'cycleway', geo_match_line($lon, $lat, 90, 600, -12)),
]);
geo_match_assert($r['segments'][0]['state'] === 'abstained', 'ambiguous road/cycleway guessed');
geo_match_assert(!in_array('car', $r['segments'][0], true), 'transport label leaked');

// 9. Rail corridor only.
$rail = geo_match_track($lon, $lat, 90, 1200, 14, 0, 5);
$r = StepsGeolocationMapMatcher::match($rail, [geo_match_network('rail', 'rail', geo_match_line($lon, $lat, 90, 1400))]);
geo_match_assert($r['segments'][0]['state'] === 'matched' && $r['segments'][0]['mode'] === 'rail', 'rail not matched');

// 10. Tunnel / long gap must not invent a connection.
$before = geo_match_track($lon, $lat, 90, 100, 3);
$after = geo_match_track($lon + 0.02, $lat, 90, 100, 3, 0, 0, 60, 1700007200);
$r = StepsGeolocationMapMatcher::match(array_merge($before, $after), [$road]);
geo_match_assert($r['gaps'] !== [] && $r['gaps'][0]['reason'] === 'long_gap', 'long gap not preserved');

// 11. Boat on a mapped river/canal.
$river = geo_match_track($lon, $lat, 90, 800, 12, 0, 6);
$r = StepsGeolocationMapMatcher::match($river, [geo_match_network('river', 'waterway', geo_match_line($lon, $lat, 90, 900))]);
geo_match_assert($r['segments'][0]['state'] === 'matched' && $r['segments'][0]['mode'] === 'waterway', 'waterway not matched');

// 12. Open lake with no mapped lane: raw water geometry.
$r = StepsGeolocationMapMatcher::match($river, []);
geo_match_assert($r['segments'][0]['state'] === 'abstained' && $r['segments'][0]['matched_geometry'] === null, 'open lake received a road');

// 13. Ferry corridor with terminals.
$ferry = geo_match_track($lon, $lat, 90, 1500, 14, 0, 4);
$r = StepsGeolocationMapMatcher::match($ferry, [geo_match_network('ferry', 'ferry', geo_match_line($lon, $lat, 90, 1600))]);
geo_match_assert($r['segments'][0]['state'] === 'matched' && $r['segments'][0]['mode'] === 'ferry', 'ferry not matched');

// 14. Shoreline drift between water and road: abstain.
$shore = geo_match_track($lon, $lat, 90, 500, 10, 0, 3);
$r = StepsGeolocationMapMatcher::match($shore, [
    geo_match_network('water', 'waterway', geo_match_line($lon, $lat, 90, 600, 10)),
    geo_match_network('bank-road', 'road', geo_match_line($lon, $lat, 90, 600, -10)),
]);
geo_match_assert($r['segments'][0]['state'] === 'abstained', 'shoreline drift forced onto a road');

// 15. Aircraft / large altitude jump context: unsuitable.
$flight = geo_match_track($lon, $lat, 90, 2000, 8);
$flight[0]['context'] = 'aircraft';
$r = StepsGeolocationMapMatcher::match($flight, [$road]);
geo_match_assert($r['segments'][0]['state'] === 'abstained' && in_array('unsuitable_context', $r['segments'][0]['reasons'], true), 'aircraft matched to road');

// 16. Indoor concourse.
$indoor = geo_match_track($lon, $lat, 90, 100, 6);
$indoor[0]['context'] = 'indoor';
$r = StepsGeolocationMapMatcher::match($indoor, [$road]);
geo_match_assert($r['segments'][0]['state'] === 'abstained', 'indoor trace matched to road');

// 17. Private road network.
$r = StepsGeolocationMapMatcher::match($urban, [geo_match_network('private', 'road', geo_match_line($lon, $lat, 90, 1000), 'private')]);
geo_match_assert($r['segments'][0]['state'] === 'abstained' && in_array('restricted_network', $r['segments'][0]['reasons'], true), 'private road used');

// 18. Missing/outdated network coverage.
$r = StepsGeolocationMapMatcher::match($urban, []);
geo_match_assert(in_array('network_missing', $r['segments'][0]['reasons'], true), 'network coverage limitation not explained');

// 19. Sparse two-point samples.
$r = StepsGeolocationMapMatcher::match(array_slice($urban, 0, 2), [$road]);
geo_match_assert($r['segments'][0]['state'] === 'abstained', 'sparse samples matched');

// 20. Stationary jitter at home.
$jitter = [];
for ($i = 0; $i < 10; $i++) {
    $jitter[] = ['id' => sprintf('00000000-0000-4000-8000-0000000001%02d', $i), 'latitude' => $lat + ($i % 2 ? 0.00001 : -0.00001), 'longitude' => $lon + ($i % 3 ? 0.00001 : -0.00001), 'source_at' => gmdate('c', 1700000000 + $i * 60), 'accuracy_m' => 15, 'quality_state' => 'accepted'];
}
$r = StepsGeolocationMapMatcher::match($jitter, [$road]);
geo_match_assert($r['segments'][0]['state'] === 'abstained' && in_array('stationary', $r['segments'][0]['reasons'], true), 'stationary jitter created a route');

// 21. Family approximate-location permission must abstain.
$r = StepsGeolocationMapMatcher::match($urban, [$road], ['approximate_only' => true]);
geo_match_assert($r['segments'][0]['state'] === 'abstained' && in_array('approximate_permission', $r['segments'][0]['reasons'], true), 'approximate permission reconstructed exact geometry');

// 22. Hidden/private boundary must abort matching.
$r = StepsGeolocationMapMatcher::match($urban, [$road], ['privacy_zone_boundary' => true]);
geo_match_assert(in_array('privacy_zone_boundary', $r['segments'][0]['reasons'], true), 'privacy boundary ignored');

// 23. Duplicate and out-of-order samples are deduplicated before matching.
$shuffled = array_merge($urban, [$urban[1], $urban[2]]);
usort($shuffled, static fn(array $a, array $b): int => $a['source_at'] <=> $b['source_at']);
$r = StepsGeolocationMapMatcher::match($shuffled, [$road]);
geo_match_assert($r['summary']['diagnostics']['duplicates'] >= 2, 'duplicates not diagnosed');
$allIds = [];
foreach ($r['segments'] as $segment) $allIds = array_merge($allIds, $segment['point_ids']);
geo_match_assert(count($allIds) === count(array_unique($allIds)), 'duplicate point ids in output');

// 24. Imported precision remains source-specific; mixed sources abstain.
$gpx = geo_match_track($lon, $lat, 90, 300, 8, 0, 0, 60, 1700000000, 'import');
$r = StepsGeolocationMapMatcher::match($gpx, [$road]);
geo_match_assert($r['segments'][0]['state'] === 'matched', 'high-precision import not eligible');
$mixed = array_merge($gpx, geo_match_track($lon, $lat, 90, 300, 8, 0, 20, 60, 1700000030, 'mobile'));
$r = StepsGeolocationMapMatcher::match($mixed, [$road]);
geo_match_assert(in_array('mixed_sources', $r['segments'][0]['reasons'], true), 'mixed source provenance not respected');

echo "ok\n";
