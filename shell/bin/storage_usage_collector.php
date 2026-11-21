#!/usr/bin/env php
<?php
/**
 * Per-user storage usage collector.
 *
 * Reads per-account space from the applications that already track it and writes
 * it into `storage_usage_cache`, which the admin panel only reads. Runs as a
 * supervisor loop so the web request never waits for I/O.
 *
 * Usage:
 *   php project/bin/storage_usage_collector.php                 # one cycle
 *   php project/bin/storage_usage_collector.php --loop          # resident loop
 *   php project/bin/storage_usage_collector.php --source=immich # single source
 *   php project/bin/storage_usage_collector.php --dry-run       # query only, no writes
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/db.php';
require APP_ROOT . '/lib/storage_usage_sources.php';

$options = getopt('', ['loop', 'source::', 'interval::', 'dry-run', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "storage_usage_collector [--loop] [--source=cloud|immich|gitea|dawarich] [--interval=SECONDS] [--dry-run]\n");
    exit(0);
}

$loop        = isset($options['loop']);
$dryRun      = isset($options['dry-run']);
$onlySource  = isset($options['source']) ? (string)$options['source'] : '';
$intervalSec = isset($options['interval'])
    ? max(60, (int)$options['interval'])
    : max(300, (int)storageUsageEnv('STORAGE_USAGE_INTERVAL', '900'));

if (storageUsageEnv('STORAGE_USAGE_ENABLED', '1') === '0') {
    fwrite(STDOUT, date('c') . " disabled via STORAGE_USAGE_ENABLED=0\n");
    exit(0);
}

$log = static function (string $line): void {
    fwrite(STDOUT, date('c') . ' ' . $line . "\n");
};

$runCycle = static function () use ($log, $dryRun, $onlySource): int {
    $defs = storageUsageSourceDefs();
    if ($onlySource !== '') {
        if (!isset($defs[$onlySource])) {
            $log("unknown source: {$onlySource}");
            return 1;
        }
        $defs = [$onlySource => $defs[$onlySource]];
    } elseif (!$dryRun && storageUsageConsumeRefreshRequest()) {
        $log('refresh requested from admin panel');
    }

    $exit = 0;
    foreach ($defs as $key => $def) {
        $started  = microtime(true);
        $result   = storageUsageCollectSource($key, $def);
        $measured = time();
        $elapsed  = round((microtime(true) - $started) * 1000);

        if ($result['status'] === 'ok') {
            $stored = 0;
            if (!$dryRun) {
                try {
                    $stored = storageUsageReplaceSource($key, $result['rows'], $measured);
                } catch (Throwable $e) {
                    $result['status'] = 'error';
                    $result['message'] = $e->getMessage();
                }
            } else {
                $stored = count($result['rows']);
            }
            if ($result['status'] === 'ok') {
                $total = array_sum(array_column($result['rows'], 'bytes'));
                $log(sprintf('%s ok users=%d total=%s (%dms)', $key, $stored, storageUsageHumanBytes($total), $elapsed));
                if (!$dryRun) {
                    storageUsageSetSourceStatus($key, 'ok', '', $measured);
                }
                continue;
            }
        }

        $exit = 1;
        $log(sprintf('%s %s: %s (%dms)', $key, $result['status'], substr($result['message'], 0, 200), $elapsed));
        if (!$dryRun) {
            // keep the previous rows — only the status changes
            storageUsageSetSourceStatus($key, $result['status'], $result['message'], 0);
        }
    }
    return $exit;
};

if (!$loop) {
    exit($runCycle());
}

$log(sprintf('loop started, interval=%ds', $intervalSec));
while (true) {
    $runCycle();
    sleep($intervalSec);
}