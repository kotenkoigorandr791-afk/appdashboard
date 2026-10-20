<?php
/**
 * Backup Manager — automatic backup scheduler.
 *
 * Usage:
 *   php project/backup/scheduler.php --loop   (supervisord)
 *   php project/backup/scheduler.php --once   (tests / manual tick)
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
require_once $root . '/db.php';
require_once $root . '/smart_log.php';
require_once __DIR__ . '/i18n.php';
backup_boot_i18n('en');
require_once __DIR__ . '/api.php';

$once = in_array('--once', $argv, true);
$lockPath = resticDataDir() . '/scheduler.lock';
$lock = @fopen($lockPath, 'c');
if ($lock === false) {
    fwrite(STDERR, "Cannot open scheduler lock\n");
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    if ($once) exit(0);
    fwrite(STDERR, "Scheduler already running\n");
    exit(0);
}

try {
    @chmod($lockPath, 0660);
    do {
        resticSchedulerTick();
        resticCleanupJobs(30);
        if ($once) break;
        sleep(30);
        clearstatcache();
    } while (true);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
