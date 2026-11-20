<?php
/**
 * Downloads and stores local WebP place photos for OSM objects.
 *
 *   php apps/steps/bin/geolocation_place_photos_run.php --loop
 *   php apps/steps/bin/geolocation_place_photos_run.php --limit=20
 *   php apps/steps/bin/geolocation_place_photos_run.php --refresh=node:123
 */
require_once __DIR__ . '/worker_limits.php';
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_place_photos.php';

$options = getopt('', ['limit::', 'loop', 'refresh::']);
$limit = isset($options['limit']) && is_numeric($options['limit']) ? max(1, min(100, (int)$options['limit'])) : 15;
$loop = isset($options['loop']);
$db = app_main_db();
steps_geolocation_migrate($db);

if (isset($options['refresh']) && is_string($options['refresh']) && preg_match('/^(node|way|relation):(\d+)$/i', $options['refresh'], $m)) {
    $pdo = $db->getPdo();
    StepsGeolocationPlacePhotos::requestRefresh($pdo, $m[1], (int)$m[2]);
    fwrite(STDOUT, "queued refresh for {$m[1]}:{$m[2]}\n");
}

$seeded = false;
do {
    try {
        $pdo = $db->getPdo();
        if (!$seeded) {
            $n = StepsGeolocationPlacePhotos::ensureAllPending($pdo);
            if ($n > 0) fwrite(STDOUT, "seeded $n place photo job(s)\n");
            $seeded = true;
        }
        $result = StepsGeolocationPlacePhotos::processBatch($pdo, $limit);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        error_log('geolocation place photos worker: ' . $msg);
        $result = ['ready' => 0, 'missing' => 0, 'failed' => 1, 'skipped' => 0];
        if (stripos($msg, 'no connection') !== false
            || stripos($msg, 'server closed') !== false
            || stripos($msg, 'broken pipe') !== false
            || stripos($msg, 'SQLSTATE') !== false) {
            try { $db->reconnect(); } catch (Throwable $ignored) {}
            sleep(5);
        }
    }
    if (!$loop) {
        fwrite(STDOUT, sprintf(
            "place-photos: ready=%d missing=%d failed=%d skipped=%d\n",
            $result['ready'], $result['missing'], $result['failed'], $result['skipped']
        ));
        exit($result['failed'] > 0 && $result['ready'] === 0 ? 1 : 0);
    }
    $worked = ($result['ready'] + $result['missing'] + $result['failed'] + $result['skipped']) > 0;
    if (!$worked) sleep(8);
    else usleep(400000);
} while (true);
