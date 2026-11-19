<?php
/**
 * Country boundary dataset test.
 *
 * The bundled 1:10m borders must be streamed (the file is larger than a default
 * PHP memory limit) and must resolve well-known border locations. The former
 * 1:110m file placed a Polish village on the Ukrainian side of the border by
 * kilometres, which this test locks down.
 *
 *   php apps/steps/tests/geolocation_boundaries_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';

function geo_boundary_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$file = dirname(__DIR__) . '/resources/boundaries/' . StepsGeolocationStatistics::BUNDLED_COUNTRY_BOUNDARIES['file'];
geo_boundary_assert(is_file($file), 'bundled boundary file is missing: ' . $file);

$count = 0;
$codes = [];
foreach (StepsGeolocationStatistics::readGeoJsonFeatures($file) as $feature) {
    $count++;
    $code = (string)($feature['properties'][StepsGeolocationStatistics::BUNDLED_COUNTRY_BOUNDARIES['code_property']] ?? '');
    if ($code !== '') $codes[$code] = true;
}
geo_boundary_assert($count > 200, 'streamed feature count looks wrong: ' . $count);
foreach (['UA', 'PL', 'DE', 'PE'] as $code) {
    geo_boundary_assert(isset($codes[$code]), 'bundled dataset lacks ' . $code);
}

$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
$dataset = StepsGeolocationStatistics::ensureBundledBoundaries($pdo);
geo_boundary_assert($dataset !== null, 'bundled dataset was not imported');
geo_boundary_assert($dataset['version'] === StepsGeolocationStatistics::BUNDLED_COUNTRY_BOUNDARIES['version'], 'active dataset is not the bundled version: ' . $dataset['version']);
geo_boundary_assert(!in_array($dataset['version'], StepsGeolocationStatistics::LEGACY_BUNDLED_COUNTRY_VERSIONS, true), 'legacy dataset still active');

$probe = $pdo->prepare(
    "SELECT a.code FROM geo_boundary_areas a
      WHERE a.dataset_version = :version AND a.kind = 'country'
        AND ST_Covers(a.geometry, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326))
      LIMIT 1"
);
// Ground truth from OSM: Polish village west of the PL/UA border, Ukrainian
// border village, a German town west of the DE/PL line and its Polish twin,
// plus one city per continent-sized dataset region.
$cases = [
    ['PL Kobylnica Woloska', 23.1000, 49.9900, 'PL'],
    ['UA Krakovets', 23.1600, 49.9900, 'UA'],
    ['DE Gorlitz', 14.9800, 51.1500, 'DE'],
    ['PL Zgorzelec', 14.9950, 51.1500, 'PL'],
    ['UA Kyiv', 30.5200, 50.4500, 'UA'],
    ['PE Lima', -77.0400, -12.0400, 'PE'],
];
foreach ($cases as $case) {
    $probe->execute([':version' => $dataset['version'], ':lon' => $case[1], ':lat' => $case[2]]);
    $code = $probe->fetchColumn();
    geo_boundary_assert($code === $case[3], $case[0] . ' resolved to ' . var_export($code, true) . ', expected ' . $case[3]);
}

echo "ok\n";
