<?php
/**
 * Storage watcher (root).
 *
 * Usage:
 *   php project/storage/watcher.php --once
 *   php project/storage/watcher.php --loop
 *
 * Detects disks, reconciles MergerFS and resumes interrupted migrations.
 * Runs before php-fpm at container start and then continuously under
 * supervisor.
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
chdir($root);
require_once $root . '/db.php';
require_once $root . '/lib/Storage/bootstrap.php';

$once = in_array('--loop', $argv ?? [], true) ? false : true;
$interval = max(5, (int)storage_settings()['watch_interval_seconds']);

$lockPath = storage_state_dir() . '/watcher.lock';
$lock = @fopen($lockPath, 'c');
if ($lock === false) {
    fwrite(STDERR, "Cannot open watcher lock\n");
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    if ($once) exit(0);
    fwrite(STDERR, "Storage watcher already running\n");
    exit(0);
}
@chmod($lockPath, 0664);

$startedAt = time();
$lastRecovery = 0;
$lastSummary = '';
do {
    try {
        StorageDb::ensureSchema();
        $stats = StorageManager::detectAndSync();
        $summaryKey = json_encode($stats);
        if ($summaryKey !== $lastSummary && ($stats['registered'] ?? 0) + ($stats['offline'] ?? 0) + ($stats['rejected'] ?? 0) + ($stats['conflicts'] ?? 0) > 0) {
            storage_event('disk_scan', 'Disk scan completed', null, $stats);
            $lastSummary = (string)$summaryKey;
        }

        if (StorageManager::isEnabled()) {
            $result = MergerFSManager::reconcile();
            StorageManager::clearReconcileRequest();
            if (($result['action'] ?? '') !== 'none') {
                @file_put_contents(
                    storage_state_dir() . '/last-reconcile.json',
                    (string)json_encode($result + ['at' => time()], JSON_UNESCAPED_SLASHES),
                    LOCK_EX
                );
            }
        }
        StorageManager::ensurePoolLayout();
        MergerFSManager::writeState();
        StorageMonitor::recordUsage();

        if (time() - $lastRecovery >= 60) {
            $lastRecovery = time();
            MigrationRecovery::resumeAll();
        }
    } catch (Throwable $e) {
        @file_put_contents(
            storage_state_dir() . '/watcher.log',
            '[' . gmdate('c') . '] ' . $e->getMessage() . "\n",
            FILE_APPEND | LOCK_EX
        );
        if ($once) {
            fwrite(STDERR, 'Storage watcher: ' . $e->getMessage() . "\n");
            exit(1);
        }
    }

    if ($once) break;
    sleep($interval);
    clearstatcache();
    $interval = max(5, (int)storage_settings(true)['watch_interval_seconds']);
} while (true);

flock($lock, LOCK_UN);
fclose($lock);
