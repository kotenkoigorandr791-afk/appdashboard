<?php
/**
 * Restic backup module integration test.
 *
 * Runs real restic against a temporary repository. Config storage is stubbed
 * with an in-memory array so the test does not need PostgreSQL.
 *
 * Usage: php tests/backup/test_restic_backup.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/restic-backup-test-' . bin2hex(random_bytes(4));
@mkdir($tmp, 0775, true);

$GLOBALS['_test_config'] = [];
function getConfig(string $key, string $default = ''): string {
    return array_key_exists($key, $GLOBALS['_test_config']) ? (string)$GLOBALS['_test_config'][$key] : $default;
}
function setConfig(string $key, string $value): void {
    $GLOBALS['_test_config'][$key] = $value;
}

putenv('APPDATA_DATA_DIR=' . $tmp . '/data');
putenv('RESTIC_HOST_ROOT=' . $tmp . '/hostroot');
putenv('RESTIC_BINARY=' . trim((string)shell_exec('command -v restic')));

if (!defined('APP_ROOT')) define('APP_ROOT', $root);
require_once $root . '/project/backup/i18n.php';
backup_boot_i18n('en');
require_once $root . '/project/backup/api.php';

$failures = 0;
$checks = 0;
function check(string $label, bool $condition, string $detail = ''): void {
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  ok   $label\n";
        return;
    }
    $failures++;
    echo "  FAIL $label" . ($detail !== '' ? " — $detail" : '') . "\n";
}

function waitJob(string $id, int $timeout = 180): array {
    $deadline = time() + $timeout;
    do {
        $job = resticJobRead($id);
        if ($job !== [] && in_array((string)($job['status'] ?? ''), ['success', 'error', 'cancelled'], true)) {
            return $job;
        }
        usleep(300000);
    } while (time() < $deadline);
    return resticJobRead($id);
}

function runJobChecked(string $type, array $params = []): array {
    $job = resticJobSpawn($type, $params);
    $done = waitJob((string)$job['id']);
    return $done;
}

echo "Repository: $tmp\n";
$repo = $tmp . '/repo';
$source = $tmp . '/src';
@mkdir($source . '/nested', 0775, true);
file_put_contents($source . '/file1.txt', "hello restic\n");
file_put_contents($source . '/nested/file2.txt', "second file\n");
file_put_contents($source . '/skip.tmp', "excluded\n");
file_put_contents($source . '/big.bin', random_bytes(1024 * 1024));
$password = 'test-password';

echo "\n[1] Configuration + encryption\n";
$saved = resticSaveConfig([
    'repository' => $repo,
    'password' => $password,
    'sources' => [$source],
    'excludes' => ['*.tmp'],
    'host' => 'test-host',
    'tags' => ['test'],
    'schedule' => ['enabled' => false, 'frequency' => 'daily', 'time' => '03:00'],
    'retention' => ['keep_last' => 2, 'keep_daily' => 0, 'prune' => true],
]);
check('config saved', ($saved['repository'] ?? '') === $repo);
$raw = getConfig(RESTIC_CONFIG_KEY, '');
check('password not stored in plaintext', $raw !== '' && strpos($raw, $password) === false);
$loaded = resticLoadConfig();
check('password decrypts', ($loaded['password'] ?? '') === $password);
check('sources preserved', ($loaded['sources'] ?? []) === [$source]);
check('public config hides password', !array_key_exists('password', resticPublicConfig($loaded)) && resticPublicConfig($loaded)['has_password'] === true);

echo "\n[2] Repository init + test\n";
resticInitRepository($loaded);
$test = resticTestRepository($loaded);
check('repository reachable', !empty($test['ok']), (string)($test['error'] ?? ''));
$wrong = resticRun(['cat', 'config'], ['RESTIC_REPOSITORY' => $repo, 'RESTIC_PASSWORD' => 'wrong-password'], 30);
check('repository rejects wrong password (encrypted)', (int)$wrong['code'] !== 0);
$configFile = '';
foreach (glob($repo . '/config') ?: [] as $f) { $configFile = (string)file_get_contents($f); }
check('repository config is not plaintext', $configFile !== '' && strpos($configFile, $repo) === false);

echo "\n[3] Backup job\n";
$job = runJobChecked('backup');
check('backup job succeeded', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));
check('backup reported snapshot', (string)($job['summary']['snapshot_id'] ?? '') !== '');
$snapshots = resticSnapshots($loaded);
check('snapshot listed', count($snapshots) === 1, json_encode($snapshots));
$snapshotId = (string)($snapshots[0]['id'] ?? '');
check('exclude pattern applied', !in_array($source . '/skip.tmp', array_column(resticList($loaded, $snapshotId, $source)['entries'], 'path'), true));
$plainInRepo = false;
foreach (glob($repo . '/data/*/*') ?: [] as $blob) {
    $bytes = (string)@file_get_contents($blob);
    if ($bytes !== '' && (str_contains($bytes, 'hello restic') || str_contains($bytes, 'changed content'))) {
        $plainInRepo = true;
        break;
    }
}
check('backup data is encrypted at rest', !$plainInRepo);

echo "\n[4] Browse snapshot\n";
$listing = resticList($loaded, $snapshotId, $source);
$names = array_column($listing['entries'], 'name');
sort($names);
check('source listing has file1.txt', in_array('file1.txt', $names, true), json_encode($names));
check('source listing has nested dir', in_array('nested', $names, true), json_encode($names));
check('excluded file missing from listing', !in_array('skip.tmp', $names, true));

echo "\n[5] Restore single path\n";
$restoreOne = $tmp . '/restore-one';
$job = runJobChecked('restore', ['snapshot' => $snapshotId, 'target' => $restoreOne, 'include' => $source . '/file1.txt']);
check('restore job succeeded', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));
$restoredFile = $restoreOne . $source . '/file1.txt';
check('single file restored', is_file($restoredFile) && trim((string)file_get_contents($restoredFile)) === 'hello restic', $restoredFile);

echo "\n[6] Restore full snapshot\n";
$restoreAll = $tmp . '/restore-all';
$job = runJobChecked('restore', ['snapshot' => $snapshotId, 'target' => $restoreAll]);
check('full restore job succeeded', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));
check('nested file restored', is_file($restoreAll . $source . '/nested/file2.txt'));

echo "\n[7] Stats\n";
$stats = resticStats($loaded);
check('stats snapshot count >= 1', (int)$stats['snapshots_count'] >= 1);
check('stats file count > 0', (int)$stats['total_file_count'] > 0);

echo "\n[8] Incremental backup + retention prune\n";
file_put_contents($source . '/file1.txt', "changed content\n");
file_put_contents($source . '/nested/added.txt', "only in the second snapshot\n");
$job = runJobChecked('backup');
check('second (incremental) backup succeeded', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));
$secondId = (string)($job['summary']['snapshot_id'] ?? '');
$secondSummary = $job['summary'] ?? [];
$firstBytes = 0;
$firstSnap = resticSnapshots($loaded);
foreach ($firstSnap as $s) { $firstBytes = max($firstBytes, (int)($s['size'] ?? 0)); }
check('incremental backup reported a snapshot', $secondId !== '');
check('incremental backup changed only a few files', (int)($secondSummary['files_changed'] ?? -1) >= 1, json_encode($secondSummary));
check('incremental backup added less data than a full run', (int)($secondSummary['data_added'] ?? PHP_INT_MAX) < $firstBytes / 2, 'data_added=' . (int)($secondSummary['data_added'] ?? -1) . ' first=' . $firstBytes);
check('two snapshots after second backup', count(resticSnapshots($loaded)) === 2);
$incRestore = $tmp . '/restore-incremental';
$job = runJobChecked('restore', ['snapshot' => $secondId, 'target' => $incRestore]);
check('incremental snapshot restores', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));
check('incremental restore has changed content', trim((string)@file_get_contents($incRestore . $source . '/file1.txt')) === 'changed content');
check('incremental restore has new file', is_file($incRestore . $source . '/nested/added.txt'));
$cfg = resticLoadConfig();
$cfg['retention']['keep_last'] = 1;
$cfg['retention']['keep_hourly'] = 0;
$cfg['retention']['keep_daily'] = 0;
$cfg['retention']['keep_weekly'] = 0;
$cfg['retention']['keep_monthly'] = 0;
$cfg['retention']['keep_yearly'] = 0;
$job = runJobChecked('forget', ['retention' => $cfg['retention']]);
check('forget/prune succeeded', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));
check('one snapshot after prune', count(resticSnapshots($loaded)) === 1, 'retention=' . json_encode($cfg['retention']) . ' log=' . resticJobTail((string)$job['id'], 3000));

echo "\n[9] Maintenance\n";
$job = runJobChecked('check');
check('check succeeded', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));
$job = runJobChecked('rebuild_index');
check('rebuild index succeeded', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));
$job = runJobChecked('unlock');
check('unlock succeeded', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));
$job = runJobChecked('prune');
check('prune succeeded', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));

echo "\n[10] Delete snapshot\n";
$remaining = resticSnapshots($loaded);
foreach ($remaining as $snap) {
    $job = runJobChecked('delete_snapshot', ['snapshot' => (string)($snap['id'] ?? '')]);
    check('delete snapshot succeeded', ($job['status'] ?? '') === 'success', (string)($job['error'] ?? ''));
}
check('repository empty after delete', count(resticSnapshots($loaded)) === 0);

echo "\n[11] Host filesystem access\n";
$hostRoot = $tmp . '/hostroot';
@mkdir($hostRoot . '/etc', 0775, true);
@mkdir($hostRoot . '/home', 0775, true);
file_put_contents($hostRoot . '/etc/hostname', "testhost\n");
check('host root detected', resticHostRoot() === $hostRoot, resticHostRoot());
$browse = resticHostBrowse('/etc', false, false);
check('host path resolves under /host', $browse['path'] === '/etc' && is_dir($hostRoot . '/etc'), $browse['path']);
check('host file visible', in_array('hostname', array_column($browse['entries'], 'name'), true));
$mk = resticHostMkdir('/home', 'backup-test-dir');
check('host mkdir works', is_dir($hostRoot . '/home/backup-test-dir'), json_encode($mk));
$rw = resticPathRwTest('/home');
check('host read/write test works', !empty($rw['ok']), json_encode($rw));
$disk = resticDiskSpace('/home');
check('host disk space works', ($disk['total'] ?? 0) > 0);

echo "\n[12] Schedule\n";
$saved = resticSaveConfig([
    'repository' => $repo,
    'password' => $password,
    'sources' => [$source],
    'excludes' => ['*.tmp'],
    'host' => 'test-host',
    'schedule' => ['enabled' => true, 'frequency' => 'daily', 'time' => '03:30'],
    'retention' => ['keep_last' => 1, 'prune' => true],
]);
check('schedule persisted', !empty($saved['schedule']['enabled']) && $saved['schedule']['time'] === '03:30');
$from = strtotime('2026-10-17 01:00:00');
check('daily next run is today', resticComputeNextRun($saved['schedule'], $from) === strtotime('2026-10-17 03:30:00'));
check('daily next run rolls to tomorrow', resticComputeNextRun($saved['schedule'], strtotime('2026-10-17 04:00:00')) === strtotime('2026-10-18 03:30:00'));
$hourly = resticComputeNextRun(['frequency' => 'hourly', 'interval_hours' => 6], $from);
check('hourly next run respects interval', $hourly === $from + 6 * 3600);
$weekly = resticComputeNextRun(['frequency' => 'weekly', 'weekday' => 1, 'time' => '02:00'], $from);
check('weekly next run targets Monday', (int)date('N', $weekly) === 1 && $weekly > $from);
$monthly = resticComputeNextRun(['frequency' => 'monthly', 'monthday' => 5, 'time' => '02:00'], $from);
check('monthly next run targets day 5', (int)date('j', $monthly) === 5 && $monthly > $from);

// The wizard creates named backups; the scheduler runs each enabled one.
$profile = resticCreateProfile([
    'name' => 'Scheduled backup',
    'description' => 'hourly test run',
    'sources' => [$source],
    'excludes' => ['*.tmp'],
    'schedule' => ['enabled' => true, 'frequency' => 'hourly', 'interval_hours' => 1],
    'retention' => ['keep_last' => 1, 'prune' => true],
]);
check('profile created with id', (string)($profile['id'] ?? '') !== '');
$profileId = (string)$profile['id'];
check('profile listed after create', count(resticLoadConfig()['backups']) >= 2, json_encode(array_column(resticLoadConfig()['backups'], 'id')));

resticSaveScheduleState(['next_run' => time() - 5, 'last_run' => 0, 'profiles' => []]);
resticSchedulerTick();
$scheduled = null;
foreach (resticJobs(10) as $candidate) {
    if (($candidate['type'] ?? '') === 'backup' && ($candidate['params']['trigger'] ?? '') === 'schedule'
        && ($candidate['params']['profile'] ?? '') === $profileId) {
        $scheduled = $candidate;
        break;
    }
}
check('scheduler starts a scheduled backup when due', $scheduled !== null, json_encode(resticJobs(3)));
if ($scheduled !== null) {
    $scheduledDone = waitJob((string)$scheduled['id']);
    check('scheduled backup succeeds', ($scheduledDone['status'] ?? '') === 'success', (string)($scheduledDone['error'] ?? ''));
    check('scheduled job carries the profile name', ($scheduled['params']['profile_name'] ?? '') === 'Scheduled backup');
}
$stateAfter = resticLoadScheduleState();
check('scheduler advances next run', (int)($stateAfter['profiles'][$profileId] ?? 0) > time());
$schedCfg = resticLoadConfig();
check('scheduled backup produced a snapshot', count(resticSnapshots($schedCfg)) >= 1);

resticUpdateProfile($profileId, ['name' => 'Scheduled backup renamed', 'sources' => [$source], 'schedule' => ['enabled' => false]]);
check('profile updated', (string)(resticFindProfile(resticLoadConfig(), $profileId)['name'] ?? '') === 'Scheduled backup renamed');
resticDeleteProfile($profileId);
check('profile deleted', resticFindProfile(resticLoadConfig(), $profileId) === null);

// Restore the earlier retention config for the remaining checks.
resticSaveConfig([
    'repository' => $repo,
    'password' => $password,
    'sources' => [$source],
    'excludes' => ['*.tmp'],
    'host' => 'test-host',
    'retention' => ['keep_last' => 1, 'prune' => true],
]);

echo "\n[13] Validation\n";
$threw = false;
try { resticSaveConfig(['repository' => '--bad', 'password' => 'x']); } catch (InvalidArgumentException $e) { $threw = true; }
check('invalid repository rejected', $threw);
$threw = false;
try { resticSaveConfig(['repository' => $repo, 'password' => 'x', 'sources' => ['relative/path']]); } catch (InvalidArgumentException $e) { $threw = true; }
check('relative source rejected', $threw);

echo "\nResult: " . ($checks - $failures) . "/$checks checks passed\n";
if ($failures > 0) {
    echo "FAILED\n";
    exit(1);
}
echo "PASSED\n";
