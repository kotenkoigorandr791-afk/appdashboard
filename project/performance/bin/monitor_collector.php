#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}
ini_set('memory_limit', '64M');
set_time_limit(120);
$root = dirname(__DIR__);
require_once $root . '/../../db.php';
require_once $root . '/core/monitor_metrics.php';
$loop = in_array('--loop', $argv ?? [], true);
$intervalSec = 60;
$lockOwner = function_exists('posix_geteuid') ? (string)posix_geteuid() : get_current_user();
$lockPath = sys_get_temp_dir() . '/appdashboard-performance-monitor-collector-' . $lockOwner . '.lock';
$lockFp = fopen($lockPath, 'c');
if ($lockFp === false) {
    fwrite(STDERR, "Cannot open lock file\n");
    exit(1);
}
$runOnce = static function () use ($lockFp): int {
    if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
        return 0;
    }
    $includeSmart = false;
    $includeDrives = false;
    $includeModules = false;
    try {
        $db = getDB();
        if ($db) {
            $db->exec("CREATE TABLE IF NOT EXISTS app_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
            $lastSmart = (int)$db->querySingle("SELECT value FROM app_meta WHERE key = 'performance_monitor_smart_last'");
            $includeSmart = (time() - $lastSmart) >= 3600;
            $lastDrives = (int)$db->querySingle("SELECT value FROM app_meta WHERE key = 'performance_monitor_drives_last'");
            $includeDrives = (time() - $lastDrives) >= 3600;
            $lastModules = (int)$db->querySingle("SELECT value FROM app_meta WHERE key = 'performance_monitor_modules_last'");
            $includeModules = (time() - $lastModules) >= 86400;
        }
    } catch (Exception $e) {
        $includeSmart = false;
        $includeDrives = false;
        $includeModules = false;
    }
    $snapshotId = monitorRunCollectorCycle($includeSmart, $includeDrives, $includeModules);
    if ($includeSmart || $includeDrives || $includeModules) {
        try {
            $db = getDB();
            if ($db) {
                $db->exec("CREATE TABLE IF NOT EXISTS app_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
                $now = time();
                if ($includeSmart) {
                    monitorSetAppMeta($db, 'performance_monitor_smart_last', (string)$now);
                }
                if ($includeDrives) {
                    monitorSetAppMeta($db, 'performance_monitor_drives_last', (string)$now);
                }
                if ($includeModules) {
                    monitorSetAppMeta($db, 'performance_monitor_modules_last', (string)$now);
                }
            }
        } catch (Exception $e) {
            error_log('monitor_collector meta update failed: ' . $e->getMessage());
        }
    }
    flock($lockFp, LOCK_UN);
    return $snapshotId ? 0 : 0;
};
if (!$loop) {
    exit($runOnce());
}
while (true) {
    $runOnce();
    sleep($intervalSec);
}
