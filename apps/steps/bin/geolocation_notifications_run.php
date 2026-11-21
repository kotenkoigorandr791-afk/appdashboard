<?php
/**
 * Worker for the notification outbox and webhook retries (task 24).
 *
 *   php apps/steps/bin/geolocation_notifications_run.php --loop
 *   php apps/steps/bin/geolocation_notifications_run.php --limit=100
 */
require_once __DIR__ . '/worker_limits.php';
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_notifications.php';

$options = getopt('', ['limit::', 'loop', 'offline']);
$limit = isset($options['limit']) && is_numeric($options['limit']) ? max(1, min(500, (int)$options['limit'])) : 50;
$loop = isset($options['loop']);
$offline = isset($options['offline']);

do {
    try {
        $db = app_main_db();
        steps_geolocation_migrate($db);
        $pdo = $db->getPdo();
        $queued = $offline ? StepsGeolocationNotifications::enqueueDeviceOffline($pdo) : 0;
        $result = StepsGeolocationNotifications::dispatch($pdo, $limit);
        fwrite(STDOUT, 'notifications: ' . $result['events'] . ' event(s), ' . $result['delivered'] . ' in-app, ' . $result['failed'] . ' failure(s), ' . $queued . ' offline notice(s)' . "\n");
    } catch (Throwable $e) {
        error_log('geolocation notifications worker: ' . $e->getMessage());
        fwrite(STDERR, 'notifications: failed: ' . $e->getMessage() . "\n");
        if (!$loop) exit(1);
    }
    if (!$loop) exit(0);
    sleep(15);
} while (true);
