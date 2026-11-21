<?php
/**
 * File Index background worker.
 *
 * Usage:
 *   php shell/storage/index_worker.php --once
 *   php shell/storage/index_worker.php --loop
 *   php shell/storage/index_worker.php --discover
 *   php shell/storage/index_worker.php --reconcile --app=cloud --user=<user>
 *
 * Runs under supervisor. It is the only place (together with the inotify
 * watcher) that performs directory scans for the file index.
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
chdir($root);
require_once $root . '/db.php';
require_once $root . '/lib/Storage/bootstrap.php';

$args = $argv ?? [];
$loop = in_array('--loop', $args, true);
$discover = in_array('--discover', $args, true);
$reconcile = in_array('--reconcile', $args, true);
$appArg = '';
$userArg = '';
$diskArg = 0;
foreach ($args as $arg) {
    $arg = (string)$arg;
    if (str_starts_with($arg, '--app=')) $appArg = substr($arg, 6);
    if (str_starts_with($arg, '--user=')) $userArg = substr($arg, 7);
    if (str_starts_with($arg, '--disk=')) $diskArg = (int)substr($arg, 7);
}

$lock = @fopen(storage_state_dir() . '/file-index-worker.lock', 'c');
if ($lock === false) {
    fwrite(STDERR, "Cannot open file index worker lock\n");
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    if ($loop) exit(0);
    fwrite(STDERR, "File index worker already running\n");
    exit(0);
}

try {
    FileIndexDb::ensureSchema();
    FileIndexDb::recoverStaleJobs(300);
    FileIndexDb::recoverStaleOutbox(300);
} catch (Throwable $e) {
    fwrite(STDERR, 'File index schema: ' . $e->getMessage() . "\n");
    exit(1);
}

// Every local application stores its data on the APPDATA volume, so all of
// them are discovered (not only cloud). Folder-mode apps (films/recipes) are
// indexed by their top-level folder, user-mode apps by the user directory.
$indexedApps = ['cloud', 'films', 'recipes', 'spends', 'steps', 'smart_home'];
if (FileIndexDb::metaGet('initial_discovery_done', '0') !== '1' || $discover) {
    try {
        $result = FileIndexWorker::discoverRoots($indexedApps);
        if ($loop) fwrite(STDOUT, '[' . gmdate('c') . '] discovered roots: ' . json_encode($result) . "\n");
        FileIndexDb::metaSet('initial_discovery_done', '1');
    } catch (Throwable $e) {
        fwrite(STDERR, 'discover roots: ' . $e->getMessage() . "\n");
    }
}

if ($reconcile && $appArg !== '' && $userArg !== '') {
    $appKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', $appArg) ?: 'cloud';
    $rootRel = FileIndex::userRoot($userArg);
    $id = FileIndex::enqueueReconcile($appKey, $userArg, $rootRel, $diskArg > 0 ? $diskArg : null);
    fwrite(STDOUT, 'reconcile queued: ' . $id . "\n");
}

$tick = 0;
do {
    try {
        FileIndexDb::recoverStaleOutbox(300);
        $jobResult = FileIndexWorker::runNextJob(25);
        $outboxResult = FileIndexWorker::processOutbox(200);
        if ($loop && ($jobResult['action'] ?? '') !== 'idle') {
            fwrite(STDOUT, '[' . gmdate('c') . '] index: ' . json_encode($jobResult) . "\n");
        }
        if ($loop && ((int)($outboxResult['processed'] ?? 0) > 0 || (int)($outboxResult['failed'] ?? 0) > 0)) {
            fwrite(STDOUT, '[' . gmdate('c') . '] outbox: ' . json_encode($outboxResult) . "\n");
        }
        if (($jobResult['action'] ?? '') === 'idle' && (int)($outboxResult['claimed'] ?? 0) === 0) {
            if ($loop) sleep(2);
            else break;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, '[' . gmdate('c') . '] file index worker: ' . $e->getMessage() . "\n");
        if (!$loop) exit(1);
        sleep(5);
    }
    $tick++;
} while ($loop);

flock($lock, LOCK_UN);
fclose($lock);
exit(0);
