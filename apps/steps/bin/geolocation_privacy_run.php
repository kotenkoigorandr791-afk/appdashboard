<?php
/**
 * Applies tenant retention, queued account purges and expired exports.
 *
 *   php apps/steps/bin/geolocation_privacy_run.php --loop
 *   php apps/steps/bin/geolocation_privacy_run.php --once
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_exports.php';
require_once dirname(__DIR__) . '/core/geolocation_privacy.php';

$once = in_array('--once', $argv ?? [], true);
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

do {
    try {
        $result = StepsGeolocationPrivacy::run($pdo);
        echo json_encode(['ok' => true, 'at' => gmdate('c')] + $result, JSON_UNESCAPED_SLASHES) . "\n";
    } catch (Throwable $e) {
        error_log('geolocation privacy worker: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'worker_failed']) . "\n";
    }
    if ($once) break;
    sleep(1800);
} while (true);
