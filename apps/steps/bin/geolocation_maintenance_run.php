<?php
/**
 * Durable job maintenance: reap expired leases and prune diagnostic history.
 *
 *   php apps/steps/bin/geolocation_maintenance_run.php --loop
 *   php apps/steps/bin/geolocation_maintenance_run.php --once
 */
require_once __DIR__ . '/worker_limits.php';
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_jobs.php';
require_once dirname(__DIR__) . '/core/geolocation_observability.php';

$once = in_array('--once', $argv ?? [], true);
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

do {
    try {
        $reaped = StepsGeolocationJobs::reap($pdo);
        $cleanup = StepsGeolocationJobs::cleanup($pdo);
        $backlog = StepsGeolocationJobs::backlog($pdo);
        StepsGeolocationObservability::increment('geo_jobs_reaped_total', $reaped['requeued'] + $reaped['failed']);
        StepsGeolocationObservability::increment('geo_jobs_cleanup_total', $cleanup['deleted'] + $cleanup['geofence_duplicates'] + $cleanup['notifications']);
        echo json_encode(['ok' => true, 'at' => gmdate('c'), 'reaped' => $reaped, 'cleanup' => $cleanup, 'backlog' => $backlog], JSON_UNESCAPED_SLASHES) . "\n";
        try { $pdo->exec('ANALYZE geo_processor_runs'); } catch (Throwable $e) {}
    } catch (Throwable $e) {
        error_log('geolocation maintenance: ' . $e->getMessage());
        if ($once) exit(1);
    }
    if ($once) break;
    sleep(60);
} while (true);
