<?php
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';

$owner = $argv[1] ?? 'root@root';
$pdo = app_main_db()->getPdo();
$days = StepsGeolocationStatistics::refresh($pdo, $owner, 'UTC');
echo 'refreshed ' . $days . " day(s)\n";
$stmt = $pdo->prepare('SELECT day, distance_m, point_count, accepted_count, flagged_count FROM geo_statistics_daily WHERE user_email = :o ORDER BY day');
$stmt->execute([':o' => $owner]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo implode(' | ', $row) . PHP_EOL;
}
