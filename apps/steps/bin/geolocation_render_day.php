<?php
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_render.php';
$day = $argv[1] ?? '2026-11-12';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
$stmt = $pdo->prepare("SELECT user_email, COUNT(*) AS c FROM geo_location_points WHERE quality_state = 'accepted' AND source_at >= :from AND source_at < :to GROUP BY user_email ORDER BY c DESC LIMIT 1");
$stmt->execute([':from' => $day . ' 00:00:00+00', ':to' => gmdate('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00+00']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    fwrite(STDERR, "no points on $day\n");
    exit(2);
}
$owner = (string)$row['user_email'];
echo "owner={$owner} points={$row['c']}\n";
$job = StepsGeolocationRender::create($pdo, $owner, 'video', [
    'from' => $day . 'T00:00:00Z',
    'to' => gmdate('Y-m-d', strtotime($day . ' +1 day')) . 'T00:00:00Z',
    'format' => 'landscape',
    'theme' => 'dark',
    'title' => $day,
    'duration' => 8,
    'fps' => 8,
    'edge' => 640,
    'attribution' => 'OpenStreetMap contributors',
]);
echo "job={$job['id']}\n";
$claimed = StepsGeolocationRender::claim($pdo);
if (!$claimed) {
    fwrite(STDERR, "claim failed\n");
    exit(3);
}
StepsGeolocationRender::runJob($pdo, $claimed);
$done = StepsGeolocationRender::find($pdo, $owner, (string)$job['id']);
echo json_encode($done, JSON_UNESCAPED_SLASHES) . "\n";
if (($done['state'] ?? '') !== 'ready') exit(4);
