<?php
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';

$owner = $argv[1] ?? 'root@root';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$datasets = $pdo->query("SELECT kind, version, feature_count, source FROM geo_boundary_datasets ORDER BY kind")->fetchAll(PDO::FETCH_ASSOC);
foreach ($datasets as $row) {
    echo 'dataset ' . $row['kind'] . ' version=' . $row['version'] . ' features=' . $row['feature_count'] . ' source=' . $row['source'] . PHP_EOL;
}

$active = StepsGeolocationStatistics::boundaryDataset($pdo, 'country');
$version = (string)($active['version'] ?? '');
echo 'active country version: ' . var_export($version, true) . PHP_EOL;
$stmt = $pdo->prepare("SELECT code, name FROM geo_boundary_areas WHERE dataset_version = :v AND kind = 'country' ORDER BY code");
$stmt->execute([':v' => $version]);
$codes = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $codes[$row['code']] = $row['name'];
echo 'countries in dataset: ' . implode(', ', array_keys($codes)) . PHP_EOL;

$perDay = $pdo->prepare(
    "SELECT (p.source_at AT TIME ZONE 'UTC')::date::text AS day, COALESCE(a.code, '--') AS code, COUNT(*) AS n
       FROM geo_location_points p
       LEFT JOIN geo_boundary_areas a ON a.dataset_version = :v AND a.kind = 'country' AND ST_Covers(a.geometry, p.position)
      WHERE p.user_email = :o AND p.quality_state NOT IN ('rejected','excluded_by_user','private_redacted')
   GROUP BY day, code ORDER BY day, n DESC"
);
$perDay->execute([':v' => $version, ':o' => $owner]);
foreach ($perDay->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo 'points ' . $row['day'] . ' ' . $row['code'] . '=' . $row['n'] . PHP_EOL;
}

// Border crossing sanity: a point just west of the PL/UA line and one just east.
$probe = $pdo->prepare(
    "SELECT ST_X(ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)) x, (
        SELECT a.code FROM geo_boundary_areas a
         WHERE a.dataset_version = :v AND a.kind = 'country'
           AND ST_Covers(a.geometry, ST_SetSRID(ST_MakePoint(:lon2, :lat2), 4326))
         LIMIT 1) AS code"
);
foreach ([
    ['PL Korczowa', 23.10, 49.99],
    ['UA Krakovets', 23.16, 49.99],
    ['border west 200m', 23.135, 49.995],
    ['border east 200m', 23.139, 49.995],
] as $case) {
    $probe->execute([':lon' => $case[1], ':lat' => $case[2], ':lon2' => $case[1], ':lat2' => $case[2], ':v' => $version]);
    $row = $probe->fetch(PDO::FETCH_ASSOC);
    echo 'probe ' . $case[0] . ' -> ' . var_export($row['code'], true) . PHP_EOL;
}
