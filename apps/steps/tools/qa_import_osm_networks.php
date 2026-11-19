<?php
/**
 * Import an Overpass `out geom;` highway extract as versioned match networks.
 *
 *   php apps/steps/tools/qa_import_osm_networks.php \
 *       owner@example.com /tmp/roads.json qa-osm-kyiv-1 "2026-11-13T10:49:21Z" "2026-11-13T12:39:08Z"
 *
 * Only ways within 60 m of the owner's raw trace in the given window are kept,
 * so the fixture stays small while still being real OSM geometry.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';

$owner = (string)($argv[1] ?? '');
$file = (string)($argv[2] ?? '');
$version = (string)($argv[3] ?? 'qa-osm-1');
$from = (string)($argv[4] ?? '');
$to = (string)($argv[5] ?? '');
if ($owner === '' || $file === '' || !is_file($file) || $from === '' || $to === '') {
    fwrite(STDERR, "usage: qa_import_osm_networks.php owner file version from to\n");
    exit(2);
}

$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$kinds = [
    'motorway' => 'road', 'motorway_link' => 'road', 'trunk' => 'road', 'trunk_link' => 'road',
    'primary' => 'road', 'primary_link' => 'road', 'secondary' => 'road', 'secondary_link' => 'road',
    'tertiary' => 'road', 'tertiary_link' => 'road', 'unclassified' => 'road', 'residential' => 'road',
    'living_street' => 'road', 'service' => 'road', 'road' => 'road',
    'footway' => 'path', 'path' => 'path', 'pedestrian' => 'path', 'steps' => 'path', 'track' => 'path',
    'cycleway' => 'cycleway',
];

$trace = $pdo->prepare(
    "SELECT ST_SetSRID(ST_MakeLine(position ORDER BY source_at), 4326) AS line
       FROM geo_location_points
      WHERE user_email = :owner AND quality_state = 'accepted' AND source_at BETWEEN :from AND :to"
);
$trace->execute([':owner' => $owner, ':from' => $from, ':to' => $to]);
$line = $trace->fetchColumn();
if (!is_string($line) || $line === '') {
    fwrite(STDERR, "no trace points in the window\n");
    exit(1);
}

$pdo->prepare('DELETE FROM geo_match_networks WHERE version = :version')->execute([':version' => $version]);
$insert = $pdo->prepare(
    "INSERT INTO geo_match_networks(id, region_code, kind, name, access, source, version, geometry)
     VALUES(:id, :region, :kind, :name, :access, 'osm-overpass', :version, ST_SetSRID(ST_GeomFromGeoJSON(:geometry), 4326))
     ON CONFLICT DO NOTHING"
);
$data = json_decode((string)file_get_contents($file), true);
$elements = is_array($data['elements'] ?? null) ? $data['elements'] : [];
$inserted = 0;
foreach ($elements as $element) {
    $highway = (string)($element['tags']['highway'] ?? '');
    if (!isset($kinds[$highway])) continue;
    $geometry = $element['geometry'] ?? [];
    if (!is_array($geometry) || count($geometry) < 2) continue;
    $coordinates = [];
    foreach ($geometry as $node) {
        if (!is_numeric($node['lon'] ?? null) || !is_numeric($node['lat'] ?? null)) continue;
        $coordinates[] = [(float)$node['lon'], (float)$node['lat']];
    }
    if (count($coordinates) < 2) continue;
    $insert->execute([
        ':id' => '00000000-0000-4000-8000-' . str_pad((string)((int)($element['id'] ?? 0) % 1000000000000), 12, '0', STR_PAD_LEFT),
        ':region' => 'qa',
        ':kind' => $kinds[$highway],
        ':name' => (string)($element['tags']['name'] ?? $highway),
        ':access' => 'public',
        ':version' => $version,
        ':geometry' => json_encode(['type' => 'LineString', 'coordinates' => $coordinates]),
    ]);
    $inserted++;
}

// Keep only geometry under the trace: the fixture stays small and the worker
// reads a network that actually covers the samples.
$trim = $pdo->prepare(
    "DELETE FROM geo_match_networks
      WHERE version = :version
        AND NOT ST_DWithin(geometry::geography, :trace::geography, 60)"
);
$trim->execute([':version' => $version, ':trace' => $line]);
$kept = $pdo->prepare('SELECT kind, COUNT(*) FROM geo_match_networks WHERE version = :version GROUP BY kind ORDER BY kind');
$kept->execute([':version' => $version]);
foreach ($kept->fetchAll(PDO::FETCH_NUM) as $row) {
    echo 'network ' . $row[0] . ': ' . $row[1] . PHP_EOL;
}
echo 'imported ' . $inserted . " way(s)\n";
