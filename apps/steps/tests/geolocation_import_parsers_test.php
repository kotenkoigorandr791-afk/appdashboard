<?php
require_once dirname(__DIR__) . '/core/geolocation_import_parsers.php';

function geo_import_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$dir = __DIR__ . '/fixtures/geo_import';
$cases = [
    'sample.gpx' => ['strava_gpx', 3],
    'sample.tcx' => ['tcx', 2],
    'sample.geojson' => ['geojson', 2],
    'sample_owntracks.ndjson' => ['owntracks', 2],
    'sample_google_records.json' => ['google_timeline', 2],
    'sample_google_semantic.json' => ['google_timeline', 2],
    'sample_immich.json' => ['immich', 2],
    'sample.csv' => ['csv', 2],
    'sample_exif.jpg' => ['exif', 1],
];
foreach ($cases as $name => [$format, $expected]) {
    $path = $dir . '/' . $name;
    geo_import_assert(is_file($path), 'fixture missing: ' . $name);
    $detected = StepsGeoImportParsers::detect($path, $name);
    geo_import_assert($detected !== null && $detected['format'] === $format, 'format detection failed for ' . $name);
    $points = [];
    foreach (StepsGeoImportParsers::parse($detected['parser'], $path) as $point) $points[] = $point;
    geo_import_assert(count($points) === $expected, $name . ' should yield ' . $expected . ' point(s), got ' . count($points));
    $ids = [];
    foreach ($points as $point) {
        geo_import_assert(isset($point['source_at'], $point['latitude'], $point['longitude'], $point['source_namespace'], $point['source_event_id']), $name . ' emitted an incomplete canonical draft');
        geo_import_assert($point['latitude'] >= -90 && $point['latitude'] <= 90 && $point['longitude'] >= -180 && $point['longitude'] <= 180, $name . ' emitted invalid coordinates');
        geo_import_assert(!array_key_exists('transport', $point) && !array_key_exists('activity_type', $point), $name . ' emitted a transport label');
        geo_import_assert(!in_array($point['source_event_id'], $ids, true), $name . ' emitted duplicate source ids');
        $ids[] = $point['source_event_id'];
    }
}

// Google semantic imports only place visits, never the activity segment.
$semantic = [];
foreach (StepsGeoImportParsers::parse('google_timeline', $dir . '/sample_google_semantic.json') as $point) $semantic[] = $point;
geo_import_assert(count($semantic) === 2, 'activity segment leaked into Google semantic import');
geo_import_assert($semantic[0]['latitude'] < 50.51 && $semantic[1]['latitude'] > 50.51, 'place visits parsed in the wrong order');

// EXIF timestamps and GPS coordinates come from the embedded tags.
$exif = iterator_to_array(StepsGeoImportParsers::parse('exif', $dir . '/sample_exif.jpg'));
geo_import_assert(abs($exif[0]['latitude'] - 50.53361111) < 0.000001, 'EXIF latitude wrong');
geo_import_assert(abs($exif[0]['longitude'] - 30.60055556) < 0.000001, 'EXIF longitude wrong');
geo_import_assert($exif[0]['source_at'] === '2025-05-09T17:00:00+00:00', 'EXIF timestamp wrong');

// Stable ids make re-imports idempotent.
$first = StepsGeoImportParsers::stableEventId('gpx', '2025-05-01T08:00:00+00:00', 50.4501, 30.5234);
$second = StepsGeoImportParsers::stableEventId('gpx', '2025-05-01T08:00:00+00:00', 50.4501, 30.5234);
geo_import_assert($first === $second && preg_match('/^[0-9a-f-]{36}$/', $first) === 1, 'stable event id is not deterministic');

geo_import_assert(StepsGeoImportParsers::detect($dir . '/sample.gpx', 'sample.exe')['format'] === 'strava_gpx', 'content sniffing should beat the extension');

echo "ok\n";
