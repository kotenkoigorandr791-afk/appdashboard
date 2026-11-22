<?php
/**
 * External filesystem change watcher for the central file index.
 *
 * Watches every physical MergerFS branch under /disks (not the merged
 * /storage view) with inotifywait, coalesces events and applies them to
 * PostgreSQL in small batches. inotify queue overflow and all catch-up work
 * is delegated to resumable reconciliation jobs, so a lost event can never
 * lose metadata permanently.
 *
 * Runs as root under supervisor. If inotifywait is not installed it degrades
 * to periodic background reconciliation (still no scanning in HTTP requests).
 *
 * Usage: php shell/storage/index_watcher.php --loop
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
chdir($root);
require_once $root . '/db.php';
require_once $root . '/lib/Storage/bootstrap.php';

$loop = in_array('--loop', $argv ?? [], true);
$stateDir = storage_state_dir();
$logFile = $stateDir . '/file-index-watcher.log';

function file_index_watch_log(string $message): void
{
    global $logFile;
    @file_put_contents($logFile, '[' . gmdate('c') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
}

$lock = @fopen($stateDir . '/file-index-watcher.lock', 'c');
if ($lock === false) {
    fwrite(STDERR, "Cannot open file index watcher lock\n");
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    if ($loop) exit(0);
    fwrite(STDERR, "File index watcher already running\n");
    exit(0);
}

try {
    FileIndexDb::ensureSchema();
} catch (Throwable $e) {
    fwrite(STDERR, 'File index schema: ' . $e->getMessage() . "\n");
    exit(1);
}

/**
 * Map a physical branch path to index coordinates.
 *
 * Branches contain the unified layout: <branch>/applications/<app>/<user>/...
 * The app base is <branch>/applications/<app>, so the index's relative path is
 * "<user>/...".
 *
 * @return array{app:string,user:string,root:string,path:string}|null
 */
function file_index_map_branch_path(string $branch, string $path): ?array
{
    $branch = rtrim($branch, '/');
    if (!str_starts_with($path, $branch . '/')) return null;
    $rel = ltrim(substr($path, strlen($branch)), '/');
    $parts = explode('/', $rel);
    if (count($parts) < 3 || $parts[0] !== 'applications') return null;
    $app = (string)preg_replace('/[^a-zA-Z0-9_\-]/', '', $parts[1]);
    $user = (string)$parts[2];
    if ($app === '' || $user === '') return null;
    $relative = implode('/', array_slice($parts, 2));
    try {
        $relative = FileIndex::normalizeRelative($relative);
    } catch (Throwable $e) {
        return null;
    }
    return ['app' => $app, 'user' => $user, 'root' => $user, 'path' => $relative];
}

/** @return list<string> mounted branch paths that contain an applications dir */
function file_index_watch_branches(): array
{
    $branches = [];
    try {
        foreach (StorageDb::disks(false) as $disk) {
            if ((string)$disk['status'] !== 'online') continue;
            $mount = rtrim((string)$disk['mount_path'], '/');
            if ($mount === '' || !is_dir($mount)) continue;
            $branches[] = $mount;
        }
    } catch (Throwable $e) {}
    return array_values(array_unique($branches));
}

/** Queue reconciliation for every known indexed root (overflow recovery). */
function file_index_queue_reconcile_all(): int
{
    $queued = 0;
    foreach (FileIndexDb::rootDirectories(2000) as $row) {
        try {
            FileIndex::enqueueReconcile((string)$row['app_key'], (string)$row['user_id'], (string)$row['relative_path'], $row['disk_id'] !== null ? (int)$row['disk_id'] : null);
            $queued++;
        } catch (Throwable $e) {}
    }
    return $queued;
}

function file_index_apply_batch(array $batch): void
{
    foreach ($batch as $event) {
        [$op, $info, $extra] = $event;
        try {
            if ($op === 'upsert') {
                $full = FileIndex::physicalPath($info['app'], $info['path']);
                if (!file_exists($full)) {
                    FileIndex::recordDelete($info['app'], $info['user'], $info['root'], $info['path'], 'missing');
                } elseif (is_dir($full)) {
                    FileIndex::recordDirectory($info['app'], $info['user'], $info['root'], $info['path'], ['mtime' => (int)@filemtime($full)]);
                } else {
                    FileIndex::recordFromFilesystem($info['app'], $info['user'], $info['root'], $info['path']);
                }
            } elseif ($op === 'delete') {
                FileIndex::recordDelete($info['app'], $info['user'], $info['root'], $info['path']);
            } elseif ($op === 'move') {
                $from = $extra;
                if ((string)$from['app'] !== (string)$info['app'] || (string)$from['user'] !== (string)$info['user'] || (string)$from['root'] !== (string)$info['root']) {
                    // Cross-root move: the destination is recorded independently.
                    FileIndex::recordDelete($from['app'], $from['user'], $from['root'], $from['path']);
                    file_index_apply_batch([['upsert', $info, null]]);
                } else {
                    FileIndex::recordMove($info['app'], $info['user'], $info['root'], $from['path'], $info['path']);
                }
            }
        } catch (Throwable $e) {
            file_index_watch_log($op . ' ' . $info['path'] . ': ' . $e->getMessage());
        }
    }
}

if (!storage_command_exists('inotifywait')) {
    file_index_watch_log('inotifywait not available; falling back to periodic reconciliation');
    $reconcileEvery = max(1800, (int)storage_env('FILE_INDEX_RECONCILE_SECONDS', '21600'));
    $last = 0;
    do {
        if (time() - $last >= $reconcileEvery) {
            $last = time();
            $queued = file_index_queue_reconcile_all();
            file_index_watch_log('reconcile queued for ' . $queued . ' root(s)');
        }
        if (!$loop) break;
        sleep(60);
    } while (true);
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

$branches = file_index_watch_branches();
if ($branches === []) {
    file_index_watch_log('no online physical branches to watch');
    if ($loop) sleep(30);
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

$cmd = ['inotifywait', '-m', '-r', '-q', '--format', '%w%f|%e'];
foreach (['create', 'delete', 'moved_from', 'moved_to', 'close_write', 'attrib'] as $eventName) {
    $cmd[] = '-e';
    $cmd[] = $eventName;
}
foreach ($branches as $branch) $cmd[] = $branch;

$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = @proc_open($cmd, $descriptors, $pipes);
if (!is_resource($proc)) {
    file_index_watch_log('cannot start inotifywait');
    exit(1);
}
stream_set_blocking($pipes[0], false);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
file_index_watch_log('watching ' . count($branches) . ' branch(es)');

$pendingMoveFrom = null;
$batch = [];
$lastFlush = time();
$lastReconcile = time();
$running = true;

while ($running) {
    $read = [$pipes[1], $pipes[2]];
    $write = null;
    $except = null;
    @stream_select($read, $write, $except, 0, 500000);
    foreach ($read as $stream) {
        $line = fgets($stream);
        if ($line === false) continue;
        $line = trim($line);
        if ($line === '') continue;
        if ($stream === $pipes[2]) {
            if (stripos($line, 'overflow') !== false || stripos($line, 'IN_Q_OVERFLOW') !== false) {
                $queued = file_index_queue_reconcile_all();
                file_index_watch_log('inotify overflow: reconcile queued for ' . $queued . ' root(s)');
            } else {
                file_index_watch_log('stderr: ' . $line);
            }
            continue;
        }
        $parts = explode('|', $line, 2);
        if (count($parts) !== 2) continue;
        $path = rtrim($parts[0], '/');
        $events = explode(',', strtoupper($parts[1]));
        if (in_array('ISDIR', $events, true)) {
            // ISDIR is a flag; keep event names only.
        }
        $info = null;
        foreach ($branches as $branch) {
            $info = file_index_map_branch_path($branch, $path);
            if ($info !== null) break;
        }
        if ($info === null) continue;

        $has = static fn(string $name): bool => in_array($name, $events, true);
        if ($has('MOVED_FROM')) {
            $pendingMoveFrom = $info;
            continue;
        }
        if ($has('MOVED_TO')) {
            if ($pendingMoveFrom !== null) {
                $batch[] = ['move', $info, $pendingMoveFrom];
                $pendingMoveFrom = null;
            } else {
                $batch[] = ['upsert', $info, null];
            }
            continue;
        }
        if ($has('DELETE')) {
            $batch[] = ['delete', $info, null];
            continue;
        }
        if ($has('CREATE') || $has('CLOSE_WRITE') || $has('ATTRIB')) {
            $batch[] = ['upsert', $info, null];
        }
        // MergerFS reports moves as DELETE+CREATE across branches; the
        // destination upsert above reconciles the destination and the batched
        // delete marks the old row.
    }

    if (count($batch) >= 200 || (time() - $lastFlush) >= 2) {
        if ($batch !== []) {
            file_index_apply_batch($batch);
            $batch = [];
        }
        $lastFlush = time();
    }
    if ((time() - $lastReconcile) >= 3600) {
        // Periodic safety net for events inotify cannot observe (e.g. changes
        // made while the container was down).
        file_index_queue_reconcile_all();
        $lastReconcile = time();
    }
    $status = proc_get_status($proc);
    if (empty($status['running'])) {
        file_index_watch_log('inotifywait exited, restarting in 5s');
        @proc_close($proc);
        sleep(5);
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            file_index_watch_log('cannot restart inotifywait');
            exit(1);
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
    }
    if (!$loop) $running = false;
}

if ($batch !== []) file_index_apply_batch($batch);
@proc_terminate($proc);
flock($lock, LOCK_UN);
fclose($lock);
exit(0);
