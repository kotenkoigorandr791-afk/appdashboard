<?php
/**
 * Dynamic Storage Manager — integration test.
 *
 * Uses throw-away directories and a synthetic /proc/self/mountinfo, plus an
 * in-memory SQLite database. It never touches real disks or user data.
 *
 * Usage: php tests/storage/test_storage_system.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/storage-system-test-' . bin2hex(random_bytes(4));
@mkdir($tmp, 0775, true);

$GLOBALS['_test_config'] = [];
function getConfig(string $key, string $default = ''): string
{
    return array_key_exists($key, $GLOBALS['_test_config']) ? (string)$GLOBALS['_test_config'][$key] : $default;
}
function setConfig(string $key, string $value): void
{
    $GLOBALS['_test_config'][$key] = $value;
}

putenv('STORAGE_ROOT=' . $tmp . '/storage');
putenv('STORAGE_DISKS_ROOT=' . $tmp . '/disks');
putenv('STORAGE_MOUNTINFO_FILE=' . $tmp . '/mountinfo');
putenv('STORAGE_STATE_DIR=' . $tmp . '/state');
putenv('APPDATA_DATA_DIR=' . $tmp . '/data');
putenv('STORAGE_SKIP_FINDMNT=1');
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
require_once $root . '/shell/storage_helper.php';
require_once $root . '/lib/db_config.php';
require_once $root . '/shell/backup/i18n.php';
backup_boot_i18n('en');
require_once $root . '/lib/Storage/bootstrap.php';

@mkdir($tmp . '/storage', 0775, true);
@mkdir($tmp . '/disks', 0775, true);
@mkdir($tmp . '/state', 0775, true);
@mkdir($tmp . '/data', 0775, true);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
StorageDb::$pdo = $pdo;

$failures = 0;
$checks = 0;
function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  ok   $label\n";
        return;
    }
    $failures++;
    echo "  FAIL $label" . ($detail !== '' ? " — $detail" : '') . "\n";
}

function writeMountinfo(array $lines): void
{
    global $tmp;
    file_put_contents($tmp . '/mountinfo', implode("\n", $lines) . "\n");
}

function mountLine(string $path, string $fstype = 'ext4', string $source = '/dev/sdz1', string $dev = '8:17'): string
{
    return '120 1 ' . $dev . ' 0 ' . $path . ' rw,relatime - ' . $fstype . ' ' . $source . ' rw';
}

function diskByName(string $name): ?array
{
    foreach (StorageDb::disks() as $disk) {
        if ((string)$disk['name'] === $name) return $disk;
    }
    return null;
}

function callPrivate(string $method, ...$args)
{
    $ref = new ReflectionMethod(DiskMigration::class, $method);
    $ref->setAccessible(true);
    return $ref->invoke(null, ...$args);
}

function newMigration(array $disk, array $targets, array $files): string
{
    $id = bin2hex(random_bytes(8));
    StorageDb::createMigration($id, (int)$disk['id'], ['targets' => $targets, 'pre_backup' => 'skipped', 'post_backup' => 'skipped']);
    $bytes = 0;
    foreach ($files as $file) {
        StorageDb::addMigrationFile($id, $file['rel_path'], (int)$file['size'], $file['type']);
        $bytes += (int)$file['size'];
    }
    StorageDb::updateMigration($id, [
        'status' => 'migrating',
        'total_files' => count($files),
        'total_bytes' => $bytes,
    ]);
    return $id;
}

function targetForDisk(array $disk): array
{
    $free = (int)@disk_free_space($disk['mount_path']);
    return [
        'id' => (int)$disk['id'],
        'name' => (string)$disk['name'],
        'mount_path' => (string)$disk['mount_path'],
        'total_bytes' => (int)@disk_total_space($disk['mount_path']),
        'free_bytes' => $free,
        'reserved_bytes' => 0,
        'usable_bytes' => $free,
    ];
}

function scanFiles(string $dir): array
{
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $node) {
        /** @var SplFileInfo $node */
        $rel = ltrim(substr($node->getPathname(), strlen($dir)), '/');
        if ($rel === '' || str_starts_with($rel, '.appdashboard-')) continue;
        if ($node->isLink()) $out[] = ['rel_path' => $rel, 'type' => 'link', 'size' => 0];
        elseif ($node->isDir()) $out[] = ['rel_path' => $rel, 'type' => 'dir', 'size' => 0];
        elseif ($node->isFile()) $out[] = ['rel_path' => $rel, 'type' => 'file', 'size' => (int)$node->getSize()];
    }
    return $out;
}

function hashOf(string $path): string
{
    return is_file($path) ? (string)hash_file('sha256', $path) : '';
}

echo "Temp: $tmp\n";
StorageDb::ensureSchema();
check('schema: storage_disks created', (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='storage_disks'")->fetchColumn());
check('schema: storage_migrations created', (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='storage_migrations'")->fetchColumn());
check('schema: storage_events created', (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='storage_events'")->fetchColumn());

echo "\n[1] First start with one disk\n";
@mkdir($tmp . '/disks/hdd1', 0775, true);
file_put_contents($tmp . '/disks/hdd1/existing.txt', "existing data\n");
writeMountinfo([
    mountLine('/', 'ext4', '/dev/root', '8:1'),
    mountLine($tmp . '/disks/hdd1', 'ext4', '/dev/sdb1', '8:17'),
]);
$detector = new DiskDetector();
$detected = $detector->detectAll();
check('one candidate detected', count($detected) === 1, json_encode($detected));
check('disk is a real mount point', !empty($detected[0]['mounted']));
check('disk is writable and compatible', !empty($detected[0]['compatible']) && !empty($detected[0]['writable']));
check('marker identity created', is_file($tmp . '/disks/hdd1/' . DiskDetector::MARKER_FILE));
$stats = DiskRegistry::sync($detected);
check('registry registers one disk', $stats['registered'] === 1, json_encode($stats));
$hdd1 = diskByName('hdd1');
check('disk row is online', $hdd1 !== null && $hdd1['status'] === 'online');
check('existing data is untouched', file_get_contents($tmp . '/disks/hdd1/existing.txt') === "existing data\n");

echo "\n[2] First start with three disks\n";
@mkdir($tmp . '/disks/hdd2/sub', 0775, true);
@mkdir($tmp . '/disks/hdd3', 0775, true);
writeMountinfo([
    mountLine('/', 'ext4', '/dev/root', '8:1'),
    mountLine($tmp . '/disks/hdd1', 'ext4', '/dev/sdb1', '8:17'),
    mountLine($tmp . '/disks/hdd2', 'ext4', '/dev/sdc1', '8:33'),
    mountLine($tmp . '/disks/hdd3', 'ext4', '/dev/sdd1', '8:49'),
]);
$stats = DiskRegistry::sync((new DiskDetector())->detectAll());
check('two more disks registered', $stats['registered'] === 2, json_encode($stats));
check('three disks total', count(StorageDb::disks()) === 3);

echo "\n[3] Fourth disk added via compose\n";
@mkdir($tmp . '/disks/hdd4', 0775, true);
writeMountinfo([
    mountLine('/', 'ext4', '/dev/root', '8:1'),
    mountLine($tmp . '/disks/hdd1', 'ext4', '/dev/sdb1', '8:17'),
    mountLine($tmp . '/disks/hdd2', 'ext4', '/dev/sdc1', '8:33'),
    mountLine($tmp . '/disks/hdd3', 'ext4', '/dev/sdd1', '8:49'),
    mountLine($tmp . '/disks/hdd4', 'ext4', '/dev/sde1', '8:65'),
]);
$stats = DiskRegistry::sync((new DiskDetector())->detectAll());
check('fourth disk registered', $stats['registered'] === 1 && count(StorageDb::disks()) === 4, json_encode($stats));

echo "\n[4] Repeated detection is idempotent\n";
$before = count(StorageDb::disks());
$stats = DiskRegistry::sync((new DiskDetector())->detectAll());
$after = count(StorageDb::disks());
check('no duplicate rows', $before === $after && $after === 4);
check('nothing re-registered', $stats['registered'] === 0, json_encode($stats));
$registeredEvents = 0;
foreach (StorageDb::events(200) as $event) if ($event['event_type'] === 'disk_registered') $registeredEvents++;
check('one registration event per disk', $registeredEvents === 4, (string)$registeredEvents);

echo "\n[5] Container restart (fresh sync)\n";
$debugDetected = (new DiskDetector())->detectAll();
check('debug: detector still sees disks', count($debugDetected) === 4, json_encode(array_map(static fn($d) => [$d['name'], $d['mounted'], $d['compatible'], $d['writable'], $d['uuid'], $d['errors']], $debugDetected)));
$stats = DiskRegistry::sync($debugDetected);
check('restart keeps all disks online', count(array_filter(StorageDb::disks(), static fn($d) => $d['status'] === 'online')) === 4, json_encode(array_map(static fn($d) => $d['name'] . ':' . $d['status'] . ':' . $d['uuid'], StorageDb::disks())));

echo "\n[6] Expected disk missing\n";
writeMountinfo([
    mountLine('/', 'ext4', '/dev/root', '8:1'),
    mountLine($tmp . '/disks/hdd1', 'ext4', '/dev/sdb1', '8:17'),
    mountLine($tmp . '/disks/hdd2', 'ext4', '/dev/sdc1', '8:33'),
    mountLine($tmp . '/disks/hdd4', 'ext4', '/dev/sde1', '8:65'),
]);
DiskRegistry::sync((new DiskDetector())->detectAll());
$hdd3 = diskByName('hdd3');
check('missing disk is offline, not empty', $hdd3 !== null && $hdd3['status'] === 'offline');
check('missing disk keeps its data', is_dir($tmp . '/disks/hdd3'));
$desired = MergerFSManager::desiredBranches();
check('offline disk is excluded from the pool', !in_array($tmp . '/disks/hdd3', $desired, true), json_encode($desired));

echo "\n[7] Plain directory and Docker bind-mount filesystems\n";
@mkdir($tmp . '/disks/fake', 0775, true);
@mkdir($tmp . '/disks/virtio', 0775, true);
file_put_contents($tmp . '/disks/virtio/virtio.txt', 'virtio');
writeMountinfo([
    mountLine('/', 'ext4', '/dev/root', '8:1'),
    mountLine($tmp . '/disks/hdd1', 'ext4', '/dev/sdb1', '8:17'),
    mountLine($tmp . '/disks/hdd2', 'ext4', '/dev/sdc1', '8:33'),
    mountLine($tmp . '/disks/hdd4', 'ext4', '/dev/sde1', '8:65'),
    mountLine($tmp . '/disks/virtio', 'fakeowner', 'virtiofs', '0:77'),
]);
$detected = (new DiskDetector())->detectAll();
$fake = null;
$virtio = null;
foreach ($detected as $candidate) {
    if ($candidate['name'] === 'fake') $fake = $candidate;
    if ($candidate['name'] === 'virtio') $virtio = $candidate;
}
check('plain directory is rejected', $fake !== null && empty($fake['mounted']));
check('plain directory is not registered', diskByName('fake') === null);
check('Docker Desktop fakeowner bind mount is accepted', $virtio !== null && !empty($virtio['compatible']), json_encode($virtio));
check('unknown filesystem is reported as a warning', $virtio !== null && in_array('filesystem_not_in_allowlist:fakeowner', $virtio['warnings'], true));
@unlink($tmp . '/disks/virtio/virtio.txt');
@rmdir($tmp . '/disks/virtio');

echo "\n[8] Space precheck and conflicts\n";
$srcDir = $tmp . '/disks/hdd1';
@mkdir($srcDir . '/documents/empty', 0775, true);
file_put_contents($srcDir . '/documents/a.txt', str_repeat('a', 1024 * 1024));
file_put_contents($srcDir . '/documents/b.bin', random_bytes(2 * 1024 * 1024));
@symlink('a.txt', $srcDir . '/documents/link-a');
$settings = storage_settings(true);
$settings['backup_required'] = false;
$settings['reserve_percent'] = 0;
$settings['min_free_gb'] = 0;
storage_save_settings($settings);
$hdd1 = diskByName('hdd1');
check('hdd1 is online before precheck', $hdd1 !== null && $hdd1['status'] === 'online', json_encode(array_map(static fn($d) => $d['name'] . ':' . $d['status'], StorageDb::disks())));
$precheck = DiskMigration::precheck((int)$hdd1['id']);
check('precheck finds enough space', $precheck['enough_space'], json_encode([
    'required' => $precheck['required_bytes'], 'usable' => $precheck['total_usable_bytes'],
]));
check('precheck counts files', $precheck['source_files'] >= 3, (string)$precheck['source_files']);
check('precheck can start', $precheck['can_start']);

$huge = $srcDir . '/huge.bin';
$fp = @fopen($huge, 'wb');
$sparseOk = false;
if ($fp !== false) {
    $sparseOk = @ftruncate($fp, 50 * 1024 ** 4);
    fclose($fp);
}
if ($sparseOk) {
    $precheckHuge = DiskMigration::precheck((int)$hdd1['id']);
    check('huge source is blocked (not enough space)', !$precheckHuge['enough_space'] && $precheckHuge['shortfall_bytes'] > 0);
    @unlink($huge);
} else {
    check('sparse file unavailable; insufficient-space check skipped', true);
}

@mkdir($tmp . '/disks/hdd2/documents', 0775, true);
file_put_contents($tmp . '/disks/hdd2/documents/a.txt', 'different content');
$precheckConflict = DiskMigration::precheck((int)$hdd1['id']);
check('conflicting file blocks removal', $precheckConflict['conflicts_count'] === 1 && !$precheckConflict['can_start'], json_encode($precheckConflict['conflicts']));
@unlink($tmp . '/disks/hdd2/documents/a.txt');
$precheckClean = DiskMigration::precheck((int)$hdd1['id']);
check('precheck is clean after conflict removal', $precheckClean['can_start']);

echo "\n[9] Copy + integrity verification\n";
$hdd1 = diskByName('hdd1');
$hdd2 = diskByName('hdd2');
$hdd4 = diskByName('hdd4');
$targets = [targetForDisk($hdd2), targetForDisk($hdd4)];
$files = scanFiles($srcDir);
$id = newMigration($hdd1, $targets, $files);
StorageDb::updateDisk((int)$hdd1['id'], ['status' => 'migrating']);
$copyResult = callPrivate('phaseCopy', $id);
check('copy phase completes', $copyResult === 'ok', $copyResult);
$verifyResult = callPrivate('phaseVerify', $id);
check('verify phase passes', $verifyResult === true);
check('source files are still present', is_file($srcDir . '/documents/a.txt') && is_file($srcDir . '/documents/b.bin'));
$rows = StorageDb::migrationFiles($id, ['done', 'skipped']);
check('all files recorded as done/skipped', count($rows) === count($files), count($rows) . '/' . count($files));
$copied = 0;
foreach ($rows as $row) {
    $target = StorageDb::diskById((int)$row['target_disk_id']);
    if ($target === null) continue;
    $dst = rtrim((string)$target['mount_path'], '/') . '/' . (string)$row['rel_path'];
    if ($row['file_type'] === 'file' && hashOf($dst) === (string)$row['checksum']) $copied++;
}
check('copied files match their checksums', $copied >= 2, (string)$copied);
StorageDb::updateDisk((int)$hdd1['id'], ['status' => 'online']);

echo "\n[10] Conflict during copy does not overwrite\n";
@mkdir($tmp . '/disks/hdd2/documents', 0775, true);
@mkdir($tmp . '/disks/hdd4/documents', 0775, true);
file_put_contents($tmp . '/disks/hdd2/documents/a.txt', 'conflicting target hdd2');
file_put_contents($tmp . '/disks/hdd4/documents/a.txt', 'conflicting target');
$id = newMigration($hdd1, $targets, [['rel_path' => 'documents/a.txt', 'type' => 'file', 'size' => 1024 * 1024]]);
$copyResult = callPrivate('phaseCopy', $id);
$migration = StorageDb::migration($id);
check('conflicting copy pauses migration', $copyResult === 'stopped' && $migration['status'] === 'paused', json_encode($migration));
check('target file was not overwritten', file_get_contents($tmp . '/disks/hdd4/documents/a.txt') === 'conflicting target' && file_get_contents($tmp . '/disks/hdd2/documents/a.txt') === 'conflicting target hdd2');
check('source file still exists', is_file($srcDir . '/documents/a.txt'));
@unlink($tmp . '/disks/hdd4/documents/a.txt');
@unlink($tmp . '/disks/hdd2/documents/a.txt');

echo "\n[11] Copy failure keeps the source\n";
file_put_contents($tmp . '/not-a-directory', 'x');
$badTargets = [[
    'id' => 999,
    'name' => 'broken',
    'mount_path' => $tmp . '/not-a-directory/target',
    'total_bytes' => 1024 ** 4,
    'free_bytes' => 1024 ** 4,
    'reserved_bytes' => 0,
    'usable_bytes' => 1024 ** 4,
]];
$id = newMigration($hdd1, $badTargets, [['rel_path' => 'documents/a.txt', 'type' => 'file', 'size' => 1024 * 1024]]);
$copyResult = callPrivate('phaseCopy', $id);
$migration = StorageDb::migration($id);
check('copy failure marks migration failed', $copyResult === 'stopped' && $migration['status'] === 'failed', json_encode($migration));
check('source survives copy failure', is_file($srcDir . '/documents/a.txt'));

echo "\n[12] Interrupted migration resumes\n";
$resumeSource = $srcDir . '/documents/resume.txt';
file_put_contents($resumeSource, random_bytes(4096));
$id = newMigration($hdd1, $targets, scanFiles($srcDir));
$hidden = $resumeSource . '.hidden';
rename($resumeSource, $hidden);
$copyResult = callPrivate('phaseCopy', $id);
$migration = StorageDb::migration($id);
check('missing source pauses migration', $copyResult === 'stopped' && $migration['status'] === 'paused', json_encode($migration));
check('source data was not deleted on pause', file_exists($hidden));
rename($hidden, $resumeSource);
$row = StorageDb::migrationFiles($id)[0];
StorageDb::updateMigrationFile((int)$row['id'], ['status' => 'pending', 'error' => '']);
StorageDb::updateMigration($id, ['status' => 'migrating', 'error' => '']);
$copyResult = callPrivate('phaseCopy', $id);
check('resume copies the file', $copyResult === 'ok', $copyResult);
check('resumed migration verifies', callPrivate('phaseVerify', $id) === true);

echo "\n[13] Corrupted target fails verification\n";
$corruptSource = $srcDir . '/documents/corrupt.txt';
file_put_contents($corruptSource, random_bytes(2048));
$id = newMigration($hdd1, $targets, scanFiles($srcDir));
check('copy for corruption test', callPrivate('phaseCopy', $id) === 'ok');
$row = null;
foreach (StorageDb::migrationFiles($id) as $candidate) {
    if ((string)$candidate['rel_path'] === 'documents/corrupt.txt') { $row = $candidate; break; }
}
check('corrupt file row exists', $row !== null);
$targetDisk = StorageDb::diskById((int)$row['target_disk_id']);
$corruptTarget = rtrim((string)$targetDisk['mount_path'], '/') . '/' . (string)$row['rel_path'];
file_put_contents($corruptTarget, 'corrupted');
$verifyResult = callPrivate('phaseVerify', $id);
$migration = StorageDb::migration($id);
check('checksum mismatch fails verification', $verifyResult === false && $migration['status'] === 'failed', json_encode($migration));
check('source still exists after failed verification', is_file($corruptSource));
@unlink($corruptSource);

echo "\n[14] Write lock\n";
StorageManager::beginMaintenance('test-migration');
check('writes are blocked during migration', StorageManager::writesBlocked());
$threw = false;
try { app_storage_write_guard(); } catch (Throwable $e) { $threw = true; }
check('storage_helper guard blocks writes', $threw);
StorageManager::endMaintenance('test-migration');
check('writes unblocked after migration', !StorageManager::writesBlocked());

echo "\n[15] MergerFS status / pool detection\n";
writeMountinfo([
    mountLine('/', 'ext4', '/dev/root', '8:1'),
    mountLine($tmp . '/disks/hdd1', 'ext4', '/dev/sdb1', '8:17'),
    mountLine($tmp . '/disks/hdd2', 'ext4', '/dev/sdc1', '8:33'),
    mountLine($tmp . '/disks/hdd4', 'ext4', '/dev/sde1', '8:65'),
    mountLine($tmp . '/storage', 'fuse.mergerfs', 'mergerfs', '0:42'),
]);
check('mergerfs mount detected', MergerFSManager::isMounted($tmp . '/storage'));
$desired = MergerFSManager::desiredBranches();
check('all online disks are desired branches', count($desired) === 3, json_encode($desired));
check('offline hdd3 not in the pool', !in_array($tmp . '/disks/hdd3', $desired, true));
$status = MergerFSManager::status();
check('pool status reports mounted', !empty($status['mounted']));

echo "\n[16] Health check detects missing and mismatched identity\n";
$hdd3 = diskByName('hdd3');
$health = DiskHealthChecker::check($hdd3);
check('missing disk health is offline', $health['status'] === 'offline' && !$health['ok']);
$hdd1 = diskByName('hdd1');
$marker = json_decode((string)file_get_contents($tmp . '/disks/hdd1/' . DiskDetector::MARKER_FILE), true);
$marker['uuid'] = '00000000-0000-4000-8000-000000000000';
file_put_contents($tmp . '/disks/hdd1/' . DiskDetector::MARKER_FILE, json_encode($marker));
$health = DiskHealthChecker::check($hdd1);
check('identity mismatch is an error', $health['status'] === 'error' && in_array('identity_mismatch', $health['report']['errors'], true), json_encode($health));
file_put_contents($tmp . '/disks/hdd1/' . DiskDetector::MARKER_FILE, json_encode(['uuid' => preg_replace('/^marker:/', '', (string)$hdd1['uuid']), 'kind' => 'marker']));
StorageDb::updateDisk((int)$hdd1['id'], ['status' => 'online']);

echo "\n[17] Restic integration (backup + restore)\n";
$restic = trim((string)shell_exec('command -v restic'));
putenv('RESTIC_BINARY=' . $restic);
if (!is_executable($restic)) {
    check('restic is installed (skipped)', true);
} else {
    // Load the existing BackupManager primitives through the bridge.
    StorageBackupManager::profiles();
    $repo = $tmp . '/restic-repo';
    $restoreDir = $tmp . '/restore';
    @mkdir($tmp . '/storage/files', 0775, true);
    file_put_contents($tmp . '/storage/files/hello.txt', "hello storage\n");
    $profileId = 'abcdef123456';
    $cfg = resticSaveConfig([
        'repository' => $repo,
        'password' => 'storage-test-password',
        'sources' => [$tmp . '/storage'],
        'host' => 'storage-test',
        'backups' => [[
            'id' => $profileId,
            'name' => 'Storage',
            'repository' => $repo,
            'password' => 'storage-test-password',
            'sources' => [$tmp . '/storage'],
            'host' => 'storage-test',
            'enabled' => true,
            'schedule' => ['enabled' => false],
            'retention' => ['keep_last' => 1, 'prune' => false],
        ]],
    ]);
    resticInitRepository($cfg);
    $run = resticRun(['backup', '--json', '--host', 'storage-test', $tmp . '/storage'], resticRepoEnv($cfg), 180);
    check('restic backup succeeds', (int)$run['code'] === 0, trim((string)$run['output']));
    $snapshots = resticSnapshots($cfg);
    check('storage snapshot exists', count($snapshots) === 1, json_encode($snapshots));
    $snapshotId = (string)($snapshots[0]['id'] ?? '');
    @mkdir($restoreDir, 0775, true);
    $restore = resticRun(['restore', $snapshotId, '--target', $restoreDir, '--verify'], resticRepoEnv($cfg), 180);
    check('restic restore succeeds', (int)$restore['code'] === 0, trim((string)$restore['output']));
    $restored = $restoreDir . $tmp . '/storage/files/hello.txt';
    check('restored file matches source', is_file($restored) && file_get_contents($restored) === "hello storage\n", $restored);

    storage_save_settings(['backup_profile' => $profileId] + storage_settings(true));
    $attached = StorageBackupManager::attachStorageToProfile($profileId);
    check('profile includes /storage source', in_array($tmp . '/storage', (array)($attached['sources'] ?? []), true), json_encode($attached['sources'] ?? []));
    check('selected backup profile resolves', (string)(StorageBackupManager::selectedProfile()['id'] ?? '') === $profileId);
    StorageDb::updateDisk((int)diskByName('hdd3')['id'], ['status' => 'online']);
    check('storage sources are available', StorageBackupManager::sourcesAvailable()['ok']);
    StorageDb::updateDisk((int)diskByName('hdd3')['id'], ['status' => 'offline']);
    check('offline disk blocks storage backups', !StorageBackupManager::sourcesAvailable()['ok']);
    check('scheduler guard blocks when a disk is offline', !storage_scheduler_sources_available());
}

echo "\n[18] Migration recovery state\n";
$hdd1 = diskByName('hdd1');
$recoveryId = newMigration($hdd1, $targets, [['rel_path' => 'documents/recovery.txt', 'type' => 'file', 'size' => 16]]);
file_put_contents($srcDir . '/documents/recovery.txt', 'recovery-test-16');
StorageDb::updateMigration($recoveryId, ['status' => 'migrating']);
$resumable = MigrationRecovery::resumable();
check('interrupted migration is discoverable', count(array_filter($resumable, static fn($m) => $m['id'] === $recoveryId)) === 1);

echo "\n[19] Mandatory application user-data backup\n";
@mkdir($tmp . '/userdata/uploads', 0775, true);
file_put_contents($tmp . '/userdata/docs.txt', "user document\n");
file_put_contents($tmp . '/userdata/uploads/photo.bin', random_bytes(4096));
putenv('APPDATA_BASE_DIR=' . $tmp . '/userdata');
putenv('UPLOADS_BASE_DIR=' . $tmp . '/userdata/uploads');
@mkdir($tmp . '/bin', 0775, true);
$fakeDump = $tmp . '/bin/pg_dump';
file_put_contents($fakeDump, "#!/bin/sh\nout=''\nfor a in \"\$@\"; do case \"\$a\" in --file=*) out=\"\${a#--file=}\";; esac; done\n[ -n \"\$out\" ] && printf 'fake-postgres-dump' > \"\$out\"\nexit 0\n");
@chmod($fakeDump, 0755);
$oldPath = getenv('PATH');
putenv('PATH=' . $tmp . '/bin:' . $oldPath);
putenv('PG_DUMP_BINARY=' . $fakeDump);
$dump = AppDataBackup::dumpDatabase();
check('pg_dump creates a database dump', $dump['ok'] && is_file($dump['path']), json_encode($dump));
check('database dump is non-empty', is_file($dump['path']) && filesize($dump['path']) > 0);
putenv('PG_DUMP_BINARY');
putenv('PATH=' . $oldPath);

$mandatory = AppDataBackup::ensureProfile();
check('mandatory profile exists', is_array($mandatory) && $mandatory['id'] === AppDataBackup::PROFILE_ID, json_encode($mandatory));
check('mandatory profile is marked required', !empty($mandatory['mandatory']) && !empty($mandatory['enabled']));
check('mandatory profile includes user files', in_array($tmp . '/userdata', (array)$mandatory['sources'], true), json_encode($mandatory['sources'] ?? []));
check('mandatory profile includes the db dump dir', in_array(AppDataBackup::dumpDir(), (array)$mandatory['sources'], true));
check('mandatory profile never auto-prunes', empty($mandatory['retention']['prune']));
$deleteBlocked = false;
try { resticDeleteProfile(AppDataBackup::PROFILE_ID); } catch (Throwable $e) { $deleteBlocked = true; }
check('mandatory profile cannot be deleted', $deleteBlocked);
$afterDelete = resticFindProfile(resticLoadConfig(), AppDataBackup::PROFILE_ID);
check('mandatory profile survives a delete attempt', $afterDelete !== null);
$savedList = resticLoadConfig()['backups'] ?? [];
$withoutMandatory = array_values(array_filter($savedList, static fn($p) => (string)($p['id'] ?? '') !== AppDataBackup::PROFILE_ID));
resticSaveConfig(['backups' => $withoutMandatory], resticLoadConfig());
check('generic list save cannot drop the mandatory profile', resticFindProfile(resticLoadConfig(), AppDataBackup::PROFILE_ID) !== null);
putenv('APPDASHBOARD_BACKUPS_JSON=' . json_encode([[
    'id' => 'feedfacecafe',
    'name' => 'Compose backup',
    'repository' => $tmp . '/restic-repo',
    'password' => 'storage-test-password',
    'sources' => [$tmp . '/userdata'],
    'schedule' => ['enabled' => false],
]]));
AppDataBackup::syncEnvProfiles();
$envProfile = resticFindProfile(resticLoadConfig(), 'feedfacecafe');
check('compose backup profile is created', $envProfile !== null && $envProfile['name'] === 'Compose backup');
check('compose profile keeps the mandatory one', resticFindProfile(resticLoadConfig(), AppDataBackup::PROFILE_ID) !== null);
putenv('APPDASHBOARD_BACKUPS_JSON');

$appRepo = resticRepoConfig(resticLoadConfig(), AppDataBackup::PROFILE_ID);
$appBackup = resticRun(['backup', '--json', '--host', 'appdata-test', $tmp . '/userdata'], resticRepoEnv($appRepo), 180);
check('user-data backup succeeds', (int)$appBackup['code'] === 0, trim((string)$appBackup['output']));
$appSnapshots = resticSnapshots($appRepo);
check('user-data snapshot exists', count($appSnapshots) >= 1, json_encode($appSnapshots));
$appSnapshot = (string)($appSnapshots[0]['id'] ?? '');
$appRestore = $tmp . '/appdata-restore';
@mkdir($appRestore, 0775, true);
$appRestoreRun = resticRun(['restore', $appSnapshot, '--target', $appRestore, '--verify'], resticRepoEnv($appRepo), 180);
check('user-data restore succeeds', (int)$appRestoreRun['code'] === 0, trim((string)$appRestoreRun['output']));
$restoredUserFile = $appRestore . $tmp . '/userdata/docs.txt';
check('restored user file matches', is_file($restoredUserFile) && file_get_contents($restoredUserFile) === "user document\n", $restoredUserFile);

$appData = AppDataBackup::syncEnvProfiles();
check('compose sync remains idempotent', is_array($appData));

 echo "\n[20] Six disks: register, lose one, keep data\n";
@mkdir($tmp . '/disks/hdd5', 0775, true);
@mkdir($tmp . '/disks/hdd6', 0775, true);
file_put_contents($tmp . '/disks/hdd5/five.txt', 'five');
file_put_contents($tmp . '/disks/hdd6/six.txt', 'six');
writeMountinfo([
    mountLine('/', 'ext4', '/dev/root', '8:1'),
    mountLine($tmp . '/disks/hdd1', 'ext4', '/dev/sdb1', '8:17'),
    mountLine($tmp . '/disks/hdd2', 'ext4', '/dev/sdc1', '8:33'),
    mountLine($tmp . '/disks/hdd4', 'ext4', '/dev/sde1', '8:65'),
    mountLine($tmp . '/disks/hdd5', 'ext4', '/dev/sdf1', '8:81'),
    mountLine($tmp . '/disks/hdd6', 'ext4', '/dev/sdg1', '8:97'),
]);
$stats = DiskRegistry::sync((new DiskDetector())->detectAll());
check('two additional disks register', $stats['registered'] === 2 && count(StorageDb::disks()) === 6, json_encode($stats));
$sixDesired = MergerFSManager::desiredBranches();
check('five mounted branches are pooled (hdd3 missing)', count($sixDesired) === 5, json_encode($sixDesired));
check('hdd5 data is untouched', file_get_contents($tmp . '/disks/hdd5/five.txt') === 'five');
writeMountinfo([
    mountLine('/', 'ext4', '/dev/root', '8:1'),
    mountLine($tmp . '/disks/hdd1', 'ext4', '/dev/sdb1', '8:17'),
    mountLine($tmp . '/disks/hdd2', 'ext4', '/dev/sdc1', '8:33'),
    mountLine($tmp . '/disks/hdd4', 'ext4', '/dev/sde1', '8:65'),
    mountLine($tmp . '/disks/hdd6', 'ext4', '/dev/sdg1', '8:97'),
]);
DiskRegistry::sync((new DiskDetector())->detectAll());
check('lost hdd5 becomes offline', (diskByName('hdd5')['status'] ?? '') === 'offline');
check('lost hdd5 keeps its files', file_get_contents($tmp . '/disks/hdd5/five.txt') === 'five');
check('pool drops hdd5 only', count(MergerFSManager::desiredBranches()) === 4 && !in_array($tmp . '/disks/hdd5', MergerFSManager::desiredBranches(), true));

 echo "\nResult: " . ($checks - $failures) . "/$checks checks passed\n";
if ($failures > 0) {
    echo "FAILED\n";
    exit(1);
}
echo "PASSED\n";
