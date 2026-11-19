<?php
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';

$owner = $argv[1] ?? 'root@root';
$from = $argv[2] ?? '2026-11-06';
$to = $argv[3] ?? '2026-11-13';
$pdo = app_main_db()->getPdo();
echo 'range refresh ' . StepsGeolocationStatistics::refresh($pdo, $owner, 'UTC', $from, $to) . " day(s)\n";
$stmt = $pdo->prepare('SELECT day, distance_m, point_count FROM geo_statistics_daily WHERE user_email = :o AND day BETWEEN :f AND :t ORDER BY day');
$stmt->execute([':o' => $owner, ':f' => $from, ':t' => $to]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo implode(' | ', $row) . PHP_EOL;
}
