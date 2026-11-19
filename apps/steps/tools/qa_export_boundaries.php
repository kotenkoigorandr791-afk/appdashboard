<?php
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
$pdo = app_main_db()->getPdo();
$version = $argv[1] ?? 'natural-earth-10m-5.1.2';
$tolerance = (float)($argv[2] ?? 0.002);
$out = $argv[3] ?? '/tmp/boundaries_simplified.geojson';
$stmt = $pdo->prepare(
    "SELECT code, name, ST_AsGeoJSON(ST_SimplifyPreserveTopology(geometry, :tolerance), 5) AS geometry
       FROM geo_boundary_areas WHERE dataset_version = :v AND kind = 'country' ORDER BY code"
);
$stmt->execute([':tolerance' => $tolerance, ':v' => $version]);
$features = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $geometry = json_decode((string)$row['geometry'], true);
    if (!is_array($geometry)) continue;
    $features[] = ['type' => 'Feature', 'properties' => ['ISO_A2' => $row['code'], 'NAME' => $row['name']], 'geometry' => $geometry];
}
file_put_contents($out, json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_SLASHES));
echo 'wrote ' . count($features) . ' features to ' . $out . ' (' . filesize($out) . " bytes)\n";
