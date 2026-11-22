<?php
/**
 * Docker smoke test: real MergerFS/FUSE mount over bind-mounted disks.
 *
 * Run inside the image with /disks/hdd1..hddN bind mounts, /storage empty and
 * /data writable. Uses SQLite so no PostgreSQL is required.
 *
 * docker run --rm --privileged --device /dev/fuse \
 *   -v "$PWD":/var/www/AppDashboard -v /tmp/data:/data \
 *   -v /tmp/hdd1:/disks/hdd1 ... --entrypoint php image \
 *   /var/www/AppDashboard/tests/storage/docker_smoke.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
putenv('STORAGE_ROOT=/storage');
putenv('STORAGE_DISKS_ROOT=/disks');
putenv('APPDATA_DATA_DIR=/data');
putenv('STORAGE_STATE_DIR=/data/storage');
putenv('STORAGE_SKIP_FINDMNT=0');
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
require_once $root . '/shell/storage_helper.php';
require_once $root . '/lib/Storage/bootstrap.php';

$failures = 0;
$checks = 0;
function smokeCheck(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
    if (!$ok) $failures++;
}

@mkdir('/data', 0775, true);
$pdo = new PDO('sqlite:/data/docker-smoke.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
StorageDb::$pdo = $pdo;
StorageDb::ensureSchema();

$candidates = (new DiskDetector())->detectAll();
echo "Detected " . count($candidates) . " candidate(s) under /disks\n";
foreach ($candidates as $c) {
    echo '  - ' . $c['name'] . ' mounted=' . (int)$c['mounted'] . ' fs=' . $c['filesystem'] . ' uuid=' . $c['uuid'] . ' errors=' . implode(',', $c['errors']) . "\n";
}
smokeCheck('at least three real mounts detected', count(array_filter($candidates, static fn($c) => $c['mounted'])) >= 3);
smokeCheck('all candidates are compatible', count(array_filter($candidates, static fn($c) => $c['compatible'])) === count($candidates));

$stats = DiskRegistry::sync($candidates);
$disks = StorageDb::disks();
smokeCheck('disks registered', count($disks) >= 3, json_encode($stats));
smokeCheck('all disks online', count(array_filter($disks, static fn($d) => $d['status'] === 'online')) === count($disks));

$branches = MergerFSManager::desiredBranches();
smokeCheck('branches match online disks', count($branches) === count($disks), json_encode($branches));
$mount = MergerFSManager::mount(storage_root(), $branches);
smokeCheck('mergerfs mount succeeds', !empty($mount['ok']), json_encode($mount));
smokeCheck('pool is mounted', MergerFSManager::isMounted(storage_root()));

$status = MergerFSManager::status();
smokeCheck('pool is writable', !empty($status['writable']), json_encode($status));
smokeCheck('runtime branch API is available', !empty($status['runtime_api']), json_encode($status));
smokeCheck('runtime branches == desired', count($status['branches'] ?? []) === count($branches), json_encode($status['branches'] ?? []));

StorageManager::ensurePoolLayout();
foreach (['files', 'documents', 'uploads', 'shared'] as $dir) {
    smokeCheck('pool dir ' . $dir, is_dir(storage_root() . '/' . $dir));
}

$payload = 'merged-storage-' . bin2hex(random_bytes(6));
$written = @file_put_contents(storage_root() . '/files/smoke.txt', $payload);
smokeCheck('write through /storage', $written !== false);
$found = 0;
foreach ($branches as $branch) {
    if (is_file($branch . '/files/smoke.txt') && file_get_contents($branch . '/files/smoke.txt') === $payload) $found++;
}
smokeCheck('file landed on exactly one physical disk', $found === 1, 'found on ' . $found);
smokeCheck('read back through /storage', file_get_contents(storage_root() . '/files/smoke.txt') === $payload);

// Runtime add: bind a seventh disk after the pool is already mounted.
$before = count($branches);
$extra = '/extra/hdd7';
if (is_dir($extra) && !is_dir('/disks/hdd7')) {
    @mkdir('/disks/hdd7', 0775, true);
    $mountedExtra = false;
    if (function_exists('exec')) {
        @exec('mount --bind ' . escapeshellarg($extra) . ' /disks/hdd7 2>&1', $ignored, $rc);
        $mountedExtra = $rc === 0;
    }
    smokeCheck('seventh disk can be bind-mounted at runtime', $mountedExtra);
    if ($mountedExtra) {
        DiskRegistry::sync((new DiskDetector())->detectAll());
        $reconcile = MergerFSManager::reconcile();
        smokeCheck('new disk is added at runtime', !empty($reconcile['ok']) && in_array($reconcile['action'], ['runtime_update', 'remounted'], true), json_encode($reconcile));
        smokeCheck('branch count grew', count(MergerFSManager::status()['branches'] ?? []) === $before + 1, json_encode(MergerFSManager::status()['branches'] ?? []));
        smokeCheck('new disk data is readable through the pool', is_file('/disks/hdd7/data.txt'));
    }
}

$unmount = MergerFSManager::unmount(storage_root());
smokeCheck('mergerfs unmount succeeds', !empty($unmount['ok']), json_encode($unmount));
smokeCheck('pool is no longer mounted', !MergerFSManager::isMounted(storage_root()));

echo "\nResult: " . ($checks - $failures) . "/$checks checks passed\n";
exit($failures > 0 ? 1 : 0);
