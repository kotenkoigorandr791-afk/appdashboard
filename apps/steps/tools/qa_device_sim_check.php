<?php
/**
 * QA helper: prints the stored geolocation state for one owner so a virtual
 * device simulation can be compared against the statistics payload.
 *
 *   php apps/steps/tools/qa_device_sim_check.php user@example.com [from] [to]
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';

$owner = $argv[1] ?? '';
if ($owner === '') {
    fwrite(STDERR, "usage: qa_device_sim_check.php owner@example.com [from] [to]\n");
    exit(2);
}
$from = $argv[2] ?? null;
$to = $argv[3] ?? null;

$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$stats = $pdo->prepare("SELECT position FROM geo_location_points WHERE user_email = :o ORDER BY source_at LIMIT 1");
$stats->execute([':o' => $owner]);
$counts = $pdo->prepare('SELECT quality_state, COUNT(*) AS n FROM geo_location_points WHERE user_email = :o GROUP BY quality_state ORDER BY quality_state');
$counts->execute([':o' => $owner]);
foreach ($counts->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo 'points ' . $row['quality_state'] . ': ' . $row['n'] . "\n";
}

$devices = $pdo->prepare('SELECT id, name, platform, source_type, last_seen_at FROM geo_devices WHERE user_email = :o ORDER BY created_at');
$devices->execute([':o' => $owner]);
foreach ($devices->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo 'device ' . $row['name'] . ' (' . $row['platform'] . '/' . $row['source_type'] . ') last=' . ($row['last_seen_at'] ?? '-') . "\n";
}

$raw = $pdo->prepare('SELECT ST_Y(position) AS lat, ST_X(position) AS lon, source_at FROM geo_location_points WHERE user_email = :o AND quality_state = \'accepted\' ORDER BY source_at');
$raw->execute([':o' => $owner]);
$rows = $raw->fetchAll(PDO::FETCH_ASSOC);
$haversine = static function (float $lat1, float $lon1, float $lat2, float $lon2): float {
    $r = 6371008.8;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
};
$expected = [];
for ($i = 1; $i < count($rows); $i++) {
    $day = substr((string)$rows[$i]['source_at'], 0, 10);
    $distance = $haversine((float)$rows[$i - 1]['lat'], (float)$rows[$i - 1]['lon'], (float)$rows[$i]['lat'], (float)$rows[$i]['lon']);
    $expected[$day]['distance'] = ($expected[$day]['distance'] ?? 0.0) + $distance;
    $expected[$day]['points'] = ($expected[$day]['points'] ?? 0) + 1;
}
foreach ($expected as $day => $values) {
    echo 'raw ' . $day . ' points=' . $values['points'] . ' distance_m=' . round($values['distance'], 1) . "\n";
}

$timezone = StepsGeolocationStatistics::timezone('UTC');
foreach (StepsGeolocationStatistics::dailyRows($pdo, $owner, $timezone) as $day) {
    echo 'stats ' . $day['day'] . ' distance_m=' . round((float)$day['distance_m'], 1)
        . ' points=' . (int)$day['point_count'] . ' accepted=' . (int)$day['accepted_count']
        . ' flagged=' . (int)$day['flagged_count'] . ' rejected=' . (int)$day['rejected_count']
        . ' country=' . (string)($day['country_code'] ?? '-') . ' city=' . (string)($day['city_name'] ?? '-') . "\n";
}
$countries = $pdo->prepare('SELECT country_code AS code, country_name AS name, COUNT(*) AS n FROM geo_statistics_daily WHERE user_email = :o GROUP BY country_code, country_name ORDER BY n DESC');
$countries->execute([':o' => $owner]);
foreach ($countries->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo 'day-country ' . $row['code'] . ' ' . $row['name'] . ' days=' . $row['n'] . "\n";
}
