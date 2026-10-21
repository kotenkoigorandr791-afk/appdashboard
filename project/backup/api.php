<?php
/**
 * Backup Manager — restic transport and primitives.
 *
 * All restic invocations go through this file. Long operations run in
 * worker.php so the web request can return a job id immediately.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const RESTIC_CONFIG_KEY = 'restic_backup_config_enc';

/* ---------------------------------------------------------------------
 * Configuration storage (AES-256-GCM, same mechanism as the old dashboard)
 * ------------------------------------------------------------------- */

function resticCryptKeyBinary(): string {
    $env = getenv('BACKUP_DASHBOARD_CRYPT_KEY');
    if ($env !== false && $env !== '') {
        return hash('sha256', $env, true);
    }
    $stored = getConfig('backup_dashboard_data_key', '');
    if ($stored === '') {
        $stored = bin2hex(random_bytes(32));
        setConfig('backup_dashboard_data_key', $stored);
    }
    return hash('sha256', $stored, true);
}

function resticEncryptPayload(string $plain): string {
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', resticCryptKeyBinary(), OPENSSL_RAW_DATA, $iv, $tag, '');
    if ($ct === false) {
        throw new RuntimeException('Encryption failed');
    }
    return (string)json_encode([
        'v' => 1,
        'iv' => base64_encode($iv),
        'ct' => base64_encode($ct),
        'tag' => base64_encode($tag),
    ], JSON_UNESCAPED_SLASHES);
}

function resticDecryptPayload(string $json): ?string {
    $w = json_decode($json, true);
    if (!is_array($w) || !isset($w['iv'], $w['tag']) || !array_key_exists('ct', $w)) return null;
    if ((string)$w['iv'] === '' || (string)$w['tag'] === '') return null;
    $iv = base64_decode((string)$w['iv'], true);
    $ct = base64_decode((string)$w['ct'], true);
    $tag = base64_decode((string)$w['tag'], true);
    if ($iv === false || $ct === false || $tag === false) return null;
    $pt = openssl_decrypt($ct, 'aes-256-gcm', resticCryptKeyBinary(), OPENSSL_RAW_DATA, $iv, $tag, '');
    return $pt === false ? null : $pt;
}

function resticDefaultSchedule(): array {
    return [
        'enabled' => false,
        'frequency' => 'daily',
        'time' => '03:00',
        'weekday' => 1,
        'monthday' => 1,
        'interval_hours' => 6,
    ];
}

function resticDefaultRetention(): array {
    return [
        'keep_last' => 0,
        'keep_hourly' => 0,
        'keep_daily' => 7,
        'keep_weekly' => 4,
        'keep_monthly' => 6,
        'keep_yearly' => 0,
        'keep_within' => '',
        'prune' => true,
    ];
}

function resticDefaultConfig(): array {
    return [
        'repository' => '',
        'password' => '',
        'sources' => [],
        'excludes' => [],
        'host' => 'appdashboard',
        'tags' => ['appdashboard'],
        'schedule' => resticDefaultSchedule(),
        'retention' => resticDefaultRetention(),
        // Named backup jobs (the UI list). Each one owns its destination
        // repository plus its own sources, schedule and retention policy.
        'backups' => [],
        'updated_at' => 0,
    ];
}

/** Sanitise a single named backup profile (list entry). */
function resticNormalizeProfile(array $in, ?array $previous = null): array {
    $previous = is_array($previous) ? $previous : [];
    $profile = [
        'id' => (string)($in['id'] ?? $previous['id'] ?? ''),
        'name' => trim((string)($in['name'] ?? $previous['name'] ?? '')),
        'description' => trim((string)($in['description'] ?? $previous['description'] ?? '')),
        // Each backup owns its own destination repository (path/URL, password
        // and snapshot host); the settings are private to the backup.
        'repository' => trim((string)($in['repository'] ?? $previous['repository'] ?? '')),
        'password' => '',
        'host' => trim((string)($in['host'] ?? $previous['host'] ?? '')),
        'sources' => [],
        'excludes' => [],
        'schedule' => array_replace(resticDefaultSchedule(), is_array($previous['schedule'] ?? null) ? $previous['schedule'] : []),
        'retention' => array_replace(resticDefaultRetention(), is_array($previous['retention'] ?? null) ? $previous['retention'] : []),
        'enabled' => (bool)($in['enabled'] ?? $previous['enabled'] ?? true),
        'created_at' => backupSafeInt($previous['created_at'] ?? time()),
        'updated_at' => time(),
    ];
    if ($profile['id'] === '' || !preg_match('/^[a-f0-9]{8,32}$/', $profile['id'])) {
        $profile['id'] = bin2hex(random_bytes(6));
    }
    if ($profile['repository'] !== '' && !resticValidRepository($profile['repository'])) {
        throw new InvalidArgumentException('backup_repo_invalid');
    }
    // An empty password means "keep the stored one" (the UI never echoes it back).
    $pwIn = (string)($in['password'] ?? '');
    $profile['password'] = $pwIn !== '' ? $pwIn : (string)($previous['password'] ?? '');
    if ($profile['host'] !== '' && !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $profile['host'])) {
        $profile['host'] = '';
    }
    $profile['sources'] = backupStringList($in['sources'] ?? $previous['sources'] ?? [], 100);
    foreach ($profile['sources'] as $source) {
        if (!resticValidSourcePath($source)) {
            throw new InvalidArgumentException('Invalid source path: ' . $source);
        }
    }
    $profile['excludes'] = backupStringList($in['excludes'] ?? $previous['excludes'] ?? [], 200);
    $schedIn = is_array($in['schedule'] ?? null) ? $in['schedule'] : [];
    $sched = array_replace($profile['schedule'], $schedIn);
    if (!in_array($sched['frequency'], ['hourly', 'daily', 'weekly', 'monthly'], true)) {
        $sched['frequency'] = 'daily';
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$sched['time'])) {
        $sched['time'] = '03:00';
    }
    $sched['enabled'] = !empty($sched['enabled']);
    $sched['weekday'] = backupClampInt($sched['weekday'], 1, 7, 1);
    $sched['monthday'] = backupClampInt($sched['monthday'], 1, 28, 1);
    $sched['interval_hours'] = backupClampInt($sched['interval_hours'], 1, 168, 6);
    $profile['schedule'] = $sched;
    $retIn = is_array($in['retention'] ?? null) ? $in['retention'] : [];
    $ret = array_replace($profile['retention'], $retIn);
    foreach (['keep_last', 'keep_hourly', 'keep_daily', 'keep_weekly', 'keep_monthly', 'keep_yearly'] as $k) {
        $profile['retention'][$k] = backupClampInt($ret[$k] ?? 0, 0, 10000, 0);
    }
    $within = trim((string)($ret['keep_within'] ?? ''));
    $profile['retention']['keep_within'] = preg_match('/^\d+[dmy]$/', $within) ? $within : '';
    $profile['retention']['prune'] = !empty($ret['prune'] ?? true);
    return $profile;
}

function resticProfilesPublic(array $profiles): array {
    return array_values(array_filter(array_map(static function ($p) {
        if (!is_array($p)) return null;
        $p['has_password'] = (string)($p['password'] ?? '') !== '';
        unset($p['password']);
        return $p;
    }, $profiles)));
}

function resticLoadConfig(): array {
    $row = getConfig(RESTIC_CONFIG_KEY, '');
    $cfg = resticDefaultConfig();
    if ($row !== '') {
        $inner = resticDecryptPayload($row);
        if ($inner !== null && $inner !== '') {
            $data = json_decode($inner, true);
            if (is_array($data)) {
                $cfg['repository'] = (string)($data['repository'] ?? '');
                $cfg['password'] = (string)($data['password'] ?? '');
                $cfg['sources'] = array_values(array_filter((array)($data['sources'] ?? []), 'is_string'));
                $cfg['excludes'] = array_values(array_filter((array)($data['excludes'] ?? []), 'is_string'));
                $cfg['host'] = (string)($data['host'] ?? $cfg['host']);
                $cfg['tags'] = array_values(array_filter((array)($data['tags'] ?? []), 'is_string'));            if (is_array($data['schedule'] ?? null)) {
                $cfg['schedule'] = array_replace($cfg['schedule'], $data['schedule']);
            }
            if (is_array($data['retention'] ?? null)) {
                $cfg['retention'] = array_replace($cfg['retention'], $data['retention']);
            }
            foreach ((array)($data['backups'] ?? []) as $profile) {
                if (!is_array($profile)) continue;
                try {
                    $cfg['backups'][] = resticNormalizeProfile([], $profile);
                } catch (Throwable $e) {
                    continue;
                }
            }
            $cfg['updated_at'] = backupSafeInt($data['updated_at'] ?? 0);
            }
        }
    }
    return resticMigrateLegacyProfiles($cfg);
}

/**
 * Older configurations stored a single implicit backup and/or a single shared
 * repository. Turn the top-level sources into the first named entry and inherit
 * the shared repository into any backup that has none of its own.
 */
function resticMigrateLegacyProfiles(array $cfg): array {
    $changed = false;
    if (empty($cfg['backups'])) {
        $sources = array_values(array_filter((array)($cfg['sources'] ?? []), 'is_string'));
        if ($sources !== []) {
            $profile = resticNormalizeProfile([
                'name' => 'Default backup',
                'sources' => $sources,
                'excludes' => $cfg['excludes'] ?? [],
                'schedule' => $cfg['schedule'] ?? [],
                'retention' => $cfg['retention'] ?? [],
                'enabled' => !empty($cfg['schedule']['enabled']),
            ]);
            $cfg['backups'] = [$profile];
            $changed = true;
        }
    }
    // Inherit the legacy shared repository into backups that have none yet.
    $gRepo = (string)($cfg['repository'] ?? '');
    $gPw = (string)($cfg['password'] ?? '');
    $gHost = (string)($cfg['host'] ?? '');
    if ($gRepo !== '' && !empty($cfg['backups'])) {
        foreach ($cfg['backups'] as &$profile) {
            if (!is_array($profile)) continue;
            if ((string)($profile['repository'] ?? '') !== '') continue;
            $profile['repository'] = $gRepo;
            if ((string)($profile['password'] ?? '') === '' && $gPw !== '') $profile['password'] = $gPw;
            if ((string)($profile['host'] ?? '') === '' && $gHost !== '') $profile['host'] = $gHost;
            $changed = true;
        }
        unset($profile);
    }
    if ($changed) resticPersistConfig($cfg);
    // No deadline is recorded for migrated profiles on purpose: the scheduler
    // treats a missing deadline as "due now", matching the old behaviour for
    // missed schedules.
    return $cfg;
}

function resticPersistConfig(array $cfg): void {
    $inner = (string)json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    setConfig(RESTIC_CONFIG_KEY, resticEncryptPayload($inner));
}

function resticPublicConfig(array $cfg): array {
    $out = $cfg;
    $out['has_password'] = (string)($cfg['password'] ?? '') !== '';
    unset($out['password']);
    $out['repository'] = (string)($cfg['repository'] ?? '');
    $out['repo_type'] = backupRepoIsLocal($out['repository']) ? 'local' : 'remote';
    $out['host_root'] = resticHostRoot();
    // Never leak per-backup repository passwords through the public payload.
    $out['backups'] = resticProfilesPublic($cfg['backups'] ?? []);
    $out['restic_version'] = resticVersion();
    return $out;
}

function resticSaveConfig(array $input, ?array $previous = null): array {
    $previous = $previous ?? resticLoadConfig();
    $cfg = resticDefaultConfig();
    $cfg['repository'] = trim((string)($input['repository'] ?? $previous['repository'] ?? ''));
    if ($cfg['repository'] !== '' && !resticValidRepository($cfg['repository'])) {
        throw new InvalidArgumentException('Invalid repository path or URL');
    }
    $password = array_key_exists('password', $input) ? (string)$input['password'] : '';
    if ($password === '') {
        $password = (string)($previous['password'] ?? '');
    }
    $cfg['password'] = $password;
    $sources = backupStringList($input['sources'] ?? $previous['sources'] ?? [], 100);
    foreach ($sources as $source) {
        if (!resticValidSourcePath($source)) {
            throw new InvalidArgumentException('Invalid source path: ' . $source);
        }
    }
    $cfg['sources'] = $sources;
    $cfg['excludes'] = backupStringList($input['excludes'] ?? $previous['excludes'] ?? [], 200);
    $host = trim((string)($input['host'] ?? $previous['host'] ?? 'appdashboard'));
    $cfg['host'] = preg_match('/^[A-Za-z0-9._-]{1,64}$/', $host) ? $host : 'appdashboard';
    $tags = backupStringList($input['tags'] ?? $previous['tags'] ?? ['appdashboard'], 20);
    $cfg['tags'] = $tags !== [] ? $tags : ['appdashboard'];

    $schedIn = is_array($input['schedule'] ?? null) ? $input['schedule'] : [];
    $schedPrev = is_array($previous['schedule'] ?? null) ? $previous['schedule'] : [];
    $sched = [
        'enabled' => !empty($schedIn['enabled'] ?? $schedPrev['enabled'] ?? false),
        'frequency' => (string)($schedIn['frequency'] ?? $schedPrev['frequency'] ?? 'daily'),
        'time' => (string)($schedIn['time'] ?? $schedPrev['time'] ?? '03:00'),
        'weekday' => backupClampInt($schedIn['weekday'] ?? $schedPrev['weekday'] ?? 1, 1, 7, 1),
        'monthday' => backupClampInt($schedIn['monthday'] ?? $schedPrev['monthday'] ?? 1, 1, 28, 1),
        'interval_hours' => backupClampInt($schedIn['interval_hours'] ?? $schedPrev['interval_hours'] ?? 6, 1, 168, 6),
    ];
    if (!in_array($sched['frequency'], ['hourly', 'daily', 'weekly', 'monthly'], true)) {
        $sched['frequency'] = 'daily';
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $sched['time'])) {
        $sched['time'] = '03:00';
    }
    $cfg['schedule'] = $sched;

    $retIn = is_array($input['retention'] ?? null) ? $input['retention'] : [];
    $retPrev = is_array($previous['retention'] ?? null) ? $previous['retention'] : [];
    foreach (['keep_last', 'keep_hourly', 'keep_daily', 'keep_weekly', 'keep_monthly', 'keep_yearly'] as $k) {
        $cfg['retention'][$k] = backupClampInt($retIn[$k] ?? $retPrev[$k] ?? 0, 0, 10000, 0);
    }
    $cfg['retention']['keep_within'] = (string)($retIn['keep_within'] ?? $retPrev['keep_within'] ?? '');
    $cfg['retention']['prune'] = !empty($retIn['prune'] ?? $retPrev['prune'] ?? true);
    $cfg['updated_at'] = time();

    // Named backups list (wizard entries). Passing `backups` replaces the list.
    if (array_key_exists('backups', $input)) {
        $cfg['backups'] = [];
        $incoming = is_array($input['backups']) ? array_values($input['backups']) : [];
        foreach ($incoming as $profile) {
            if (!is_array($profile)) continue;
            $prevId = isset($profile['id']) ? (string)$profile['id'] : '';
            $prev = null;
            foreach ((array)($previous['backups'] ?? []) as $candidate) {
                if ((string)($candidate['id'] ?? '') === $prevId && $prevId !== '') { $prev = $candidate; break; }
            }
            $cfg['backups'][] = resticNormalizeProfile($profile, $prev);
        }
    } else {
        // Preserve the stored list verbatim (including per-backup passwords).
        $cfg['backups'] = array_values((array)($previous['backups'] ?? []));
    }

    resticPersistConfig($cfg);

    // Recompute scheduler deadlines when schedule settings change.
    $state = resticLoadScheduleState();
    $state['next_run'] = $cfg['schedule']['enabled'] ? resticComputeNextRun($cfg['schedule'], time()) : 0;
    $state['profiles'] = [];
    foreach ($cfg['backups'] as $profile) {
        $state['profiles'][(string)$profile['id']] = !empty($profile['schedule']['enabled'])
            ? resticComputeNextRun($profile['schedule'], time())
            : 0;
    }
    resticSaveScheduleState($state);

    return $cfg;
}

function resticClearConfig(): void {
    setConfig(RESTIC_CONFIG_KEY, '');
    resticSaveScheduleState(['next_run' => 0, 'last_run' => 0, 'profiles' => []]);
}

/* ---------------------------------------------------------------------
 * Named backups list (wizard profiles)
 * ------------------------------------------------------------------- */

function resticFindProfile(array $cfg, string $id): ?array {
    foreach ((array)($cfg['backups'] ?? []) as $profile) {
        if ((string)($profile['id'] ?? '') === $id) return $profile;
    }
    return null;
}

function resticSaveProfileList(array $profiles): array {
    $cfg = resticLoadConfig();
    $cfg = resticSaveConfig(['backups' => array_values($profiles)], $cfg);
    return $cfg;
}

function resticCreateProfile(array $input): array {
    $cfg = resticLoadConfig();
    $profile = resticNormalizeProfile($input, null);
    if ($profile['name'] === '') {
        throw new InvalidArgumentException('backup_name_required');
    }
    $profiles = (array)($cfg['backups'] ?? []);
    $profiles[] = $profile;
    resticSaveProfileList($profiles);
    return $profile;
}

function resticUpdateProfile(string $id, array $input): array {
    $cfg = resticLoadConfig();
    $previous = resticFindProfile($cfg, $id);
    if ($previous === null) throw new InvalidArgumentException('backup_not_found');
    $profile = resticNormalizeProfile($input, $previous);
    if ($profile['name'] === '') {
        throw new InvalidArgumentException('backup_name_required');
    }
    $profiles = [];
    foreach ((array)($cfg['backups'] ?? []) as $candidate) {
        $profiles[] = ((string)($candidate['id'] ?? '') === $id) ? $profile : $candidate;
    }
    resticSaveProfileList($profiles);
    return $profile;
}

function resticDeleteProfile(string $id): void {
    $cfg = resticLoadConfig();
    $profiles = [];
    $found = false;
    foreach ((array)($cfg['backups'] ?? []) as $candidate) {
        if ((string)($candidate['id'] ?? '') === $id) { $found = true; continue; }
        $profiles[] = $candidate;
    }
    if (!$found) throw new InvalidArgumentException('backup_not_found');
    resticSaveProfileList($profiles);
}

/** First backup that has a configured repository (used as the default selection). */
function resticActiveProfileId(array $cfg): string {
    foreach ((array)($cfg['backups'] ?? []) as $profile) {
        if ((string)($profile['repository'] ?? '') !== '') return (string)($profile['id'] ?? '');
    }
    $list = array_values((array)($cfg['backups'] ?? []));
    return isset($list[0]) && is_array($list[0]) ? (string)($list[0]['id'] ?? '') : '';
}

/**
 * Resolve a restic "cfg" for a specific backup (repository/password/host and
 * sources). Falls back to the legacy top-level repository when the id is
 * unknown or empty, so old single-repository installs keep working.
 */
function resticRepoConfig(array $cfg, string $profileId = ''): array {
    $profile = $profileId !== '' ? resticFindProfile($cfg, $profileId) : null;
    if ($profile === null) return $cfg;
    $host = trim((string)($profile['host'] ?? ''));
    return array_replace($cfg, [
        'repository' => (string)($profile['repository'] ?? ''),
        'password' => (string)($profile['password'] ?? ''),
        'host' => $host !== '' ? $host : (string)($cfg['host'] ?? 'appdashboard'),
        'sources' => (array)($profile['sources'] ?? []),
        'excludes' => (array)($profile['excludes'] ?? []),
        'retention' => (array)($profile['retention'] ?? []),
    ]);
}

function resticValidRepository(string $repo): bool {
    if ($repo === '' || strlen($repo) > 2048) return false;
    if ($repo[0] === '-' || preg_match('/[\x00-\x1f\x7f]/', $repo)) return false;
    if ($repo[0] === '/') return true;
    return (bool)preg_match('/^[a-z][a-z0-9+.-]*:/i', $repo);
}

function resticValidSourcePath(string $path): bool {
    if ($path === '' || strlen($path) > 4096) return false;
    if (str_contains($path, "\0")) return false;
    return $path[0] === '/';
}

/* ---------------------------------------------------------------------
 * Runtime / process execution
 * ------------------------------------------------------------------- */

function resticDataDir(): string {
    $base = getenv('APPDATA_DATA_DIR');
    if ($base === false || trim((string)$base) === '') {
        $base = defined('APP_ROOT') ? APP_ROOT . '/data' : dirname(__DIR__, 2) . '/data';
    }
    $dir = rtrim((string)$base, '/') . '/backup';
    resticEnsureDir($dir);
    return $dir;
}

function resticEnsureDir(string $dir): void {
    if (is_dir($dir)) return;
    @mkdir($dir, 0770, true);
}

/**
 * Drop cache / runtime paths the current process cannot write.
 * A prior root-run restic leaves root-owned cache shards; www-data then fails
 * with "permission denied" while loading parent snapshots.
 */
function resticSanitizeRuntimeDirs(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $base = resticDataDir();
    foreach (['cache', 'tmp', 'stage', 'jobs', 'home'] as $name) {
        $dir = $base . '/' . $name;
        resticEnsureDir($dir);
        if (!is_writable($dir)) {
            @chmod($dir, 0770);
            if (!is_writable($dir) && function_exists('exec')) {
                @exec('rm -rf ' . escapeshellarg($dir));
                resticEnsureDir($dir);
            }
        }
    }
    $cache = $base . '/cache';
    if (!is_dir($cache) || !is_readable($cache)) return;
    $uid = function_exists('posix_geteuid') ? (int)posix_geteuid() : -1;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $node) {
            /** @var SplFileInfo $node */
            $path = $node->getPathname();
            if ($uid >= 0 && function_exists('fileowner')) {
                $owner = @fileowner($path);
                if ($owner !== false && (int)$owner !== $uid) {
                    if ($node->isDir()) {
                        @exec('rm -rf ' . escapeshellarg($path));
                    } else {
                        @unlink($path);
                    }
                    continue;
                }
            }
            if ($node->isDir() && !is_writable($path)) {
                @exec('rm -rf ' . escapeshellarg($path));
            } elseif ($node->isFile() && !is_writable($path)) {
                @unlink($path);
            }
        }
    } catch (Throwable $e) {
        // Cache may contain root-owned leftovers from privileged init; skip cleanup.
    }
}

function resticJobsDir(): string {
    $dir = resticDataDir() . '/jobs';
    resticEnsureDir($dir);
    return $dir;
}

function resticBinary(): string {
    $env = trim((string)getenv('RESTIC_BINARY'));
    if ($env !== '') return $env;
    foreach (['/usr/local/bin/restic', '/usr/bin/restic'] as $candidate) {
        if (is_executable($candidate)) return $candidate;
    }
    return 'restic';
}

function resticVersion(): string {
    static $version = null;
    if ($version !== null) return $version;
    $r = resticRun(['version'], [], 10);
    if (($r['code'] ?? 1) !== 0) return $version = '';
    $out = trim((string)($r['stdout'] ?? ''));
    $version = $out !== '' ? strtok($out, "\n") : '';
    return $version;
}

function resticPhpCli(): string {
    $candidates = [];
    if (defined('PHP_SAPI') && PHP_SAPI === 'cli' && defined('PHP_BINARY')) {
        $candidates[] = PHP_BINARY;
    }
    if (defined('PHP_BINDIR')) $candidates[] = PHP_BINDIR . '/php';
    $candidates[] = '/usr/local/bin/php';
    $candidates[] = '/usr/bin/php';
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && @is_executable($candidate)) return $candidate;
    }
    return 'php';
}

function resticBaseEnv(): array {
    resticSanitizeRuntimeDirs();
    $env = getenv();
    if (!is_array($env)) $env = [];
    $env['PATH'] = $env['PATH'] ?? '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
    $env['HOME'] = $env['HOME'] ?? (resticDataDir() . '/home');
    $env['RESTIC_CACHE_DIR'] = resticDataDir() . '/cache';
    $env['RESTIC_PROGRESS_FPS'] = '2';
    // Go preempts with SIGURG. Docker Desktop's host mount returns EIO when
    // that signal interrupts a large read, so restic skips every host file.
    $env['GODEBUG'] = 'asyncpreemptoff=1';
    $env['TMPDIR'] = $env['TMPDIR'] ?? (resticDataDir() . '/tmp');
    foreach (['HOME', 'RESTIC_CACHE_DIR', 'TMPDIR'] as $key) {
        if (!empty($env[$key])) resticEnsureDir((string)$env[$key]);
    }
    return $env;
}

/** Local repos live on the host; restic runs in the container and needs the mount path. */
function resticRepositoryLocation(string $repo): string {
    if (!backupRepoIsLocal($repo)) return $repo;
    $resolved = resticResolveHostPath($repo);
    return $resolved !== null ? $resolved : $repo;
}

function resticRepoEnv(array $cfg): array {
    return [
        'RESTIC_REPOSITORY' => resticRepositoryLocation((string)($cfg['repository'] ?? '')),
        'RESTIC_PASSWORD' => (string)($cfg['password'] ?? ''),
    ];
}

/**
 * Run restic and collect output. Lines from stdout are passed to $onLine.
 */
function resticRun(array $args, array $env = [], int $timeout = 300, ?callable $onLine = null, int $maxOutputBytes = 0, ?callable $onStart = null): array {
    $fullEnv = resticBaseEnv();
    foreach ($env as $k => $v) {
        $fullEnv[(string)$k] = (string)$v;
    }
    $cmd = array_merge([resticBinary()], array_map(static function ($a) { return (string)$a; }, $args));
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($cmd, $descriptors, $pipes, null, $fullEnv);
    if (!is_resource($proc)) {
        return ['code' => 127, 'stdout' => '', 'stderr' => 'Unable to start restic', 'output' => 'Unable to start restic', 'lines' => [], 'timed_out' => false, 'truncated' => false];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $status = proc_get_status($proc);
    if ($onStart !== null) {
        try { $onStart((int)($status['pid'] ?? 0)); } catch (Throwable $e) {}
    }

    $stdout = '';
    $stderr = '';
    $lineBuffer = '';
    $lines = [];
    $used = 0;
    $timedOut = false;
    $truncated = false;
    $start = microtime(true);
    $open = [1 => $pipes[1], 2 => $pipes[2]];

    while ($open !== []) {
        if ($timeout > 0 && (microtime(true) - $start) > $timeout) {
            $timedOut = true;
            @proc_terminate($proc);
            usleep(150000);
            @proc_terminate($proc, 9);
            break;
        }
        $read = array_values($open);
        $write = null;
        $except = null;
        $ready = @stream_select($read, $write, $except, 0, 200000);
        if ($ready === false) break;
        foreach ($read as $stream) {
            $key = array_search($stream, $open, true);
            $chunk = fread($stream, 65536);
            if ($chunk === '' && feof($stream)) {
                if ($key !== false) unset($open[$key]);
                @fclose($stream);
                continue;
            }
            if ($chunk === '') continue;
            $used += strlen($chunk);
            if ($stream === $pipes[1]) {
                $stdout .= $chunk;
                $lineBuffer .= $chunk;
                while (($pos = strpos($lineBuffer, "\n")) !== false) {
                    $line = substr($lineBuffer, 0, $pos);
                    $lineBuffer = substr($lineBuffer, $pos + 1);
                    $lines[] = $line;
                    if ($onLine !== null) {
                        try { $onLine($line); } catch (Throwable $e) {}
                    }
                }
            } else {
                $stderr .= $chunk;
                if ($maxOutputBytes > 0 && strlen($stderr) > $maxOutputBytes) {
                    $stderr = substr($stderr, 0, $maxOutputBytes);
                }
            }
            if ($maxOutputBytes > 0 && $used > $maxOutputBytes) {
                $truncated = true;
                @proc_terminate($proc);
                usleep(100000);
                @proc_terminate($proc, 9);
                break 2;
            }
        }
    }
    if ($lineBuffer !== '') {
        $lines[] = $lineBuffer;
        if ($onLine !== null) {
            try { $onLine($lineBuffer); } catch (Throwable $e) {}
        }
    }
    foreach ($open as $stream) @fclose($stream);
    $code = proc_close($proc);
    if ($timedOut && $code === 0) $code = 124;
    $output = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));
    return [
        'code' => $code,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'output' => $output,
        'lines' => $lines,
        'timed_out' => $timedOut,
        'truncated' => $truncated,
    ];
}

function resticErrorFromResult(array $result): string {
    $text = trim((string)($result['stderr'] ?? ''));
    if ($text === '') $text = trim((string)($result['output'] ?? ''));
    if ($text === '') return 'restic exited with code ' . (int)($result['code'] ?? 0);
    $lines = preg_split('/\R/u', $text) ?: [];
    $last = '';
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line !== '') $last = $line;
    }
    $last = preg_replace('/^(Fatal|Error):\s*/i', '', $last) ?? $last;
    return $last !== '' ? $last : 'restic failed';
}

/* ---------------------------------------------------------------------
 * Synchronous operations
 * ------------------------------------------------------------------- */

function resticRequireConfigured(array $cfg): void {
    if ((string)($cfg['repository'] ?? '') === '') {
        throw new RuntimeException(bt('backup_repo_not_configured'));
    }
    if ((string)($cfg['password'] ?? '') === '') {
        throw new RuntimeException(bt('backup_repo_missing_password'));
    }
}

function resticTestRepository(array $cfg): array {
    resticRequireConfigured($cfg);
    $r = resticRun(['cat', 'config'], resticRepoEnv($cfg), 30);
    if ((int)$r['code'] !== 0) {
        return ['ok' => false, 'error' => resticErrorFromResult($r)];
    }
    return ['ok' => true];
}

/**
 * Docker Desktop's host bind returns EIO on the large reads restic uses.
 * Copy the source onto the container filesystem first; restic then reads that.
 */
function resticStageHostSource(string $source): string {
    $bind = resticBindHome();
    if ($bind === null) return $source;
    if ($source !== $bind['dest'] && !str_starts_with($source, $bind['dest'] . '/')) return $source;
    if (!file_exists($source)) return $source;
    $stageRoot = resticDataDir() . '/stage';
    resticEnsureDir($stageRoot);
    $dest = $stageRoot . '/' . bin2hex(random_bytes(4)) . '-' . basename(rtrim($source, '/'));
    $rc = 1;
    if (function_exists('exec')) {
        @exec('cp -a ' . escapeshellarg($source) . ' ' . escapeshellarg($dest) . ' 2>/dev/null', $ignored, $rc);
    }
    if ($rc !== 0 || !file_exists($dest)) return $source;
    return $dest;
}

function resticEnsureLocalRepository(array $cfg): void {
    $repo = (string)($cfg['repository'] ?? '');
    if (!backupRepoIsLocal($repo)) return;
    $dir = rtrim(resticRepositoryLocation($repo), '/');
    if (is_file($dir . '/config')) return;
    resticInitRepository($cfg);
}

function resticInitRepository(array $cfg): array {
    resticRequireConfigured($cfg);
    if (backupRepoIsLocal((string)$cfg['repository'])) {
        $dir = rtrim(resticRepositoryLocation((string)$cfg['repository']), '/');
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create repository directory: ' . $dir);
        }
    }
    $r = resticRun(['init'], resticRepoEnv($cfg), 120);
    if ((int)$r['code'] !== 0) {
        throw new RuntimeException(resticErrorFromResult($r));
    }
    return ['ok' => true];
}

function resticSnapshots(array $cfg): array {
    resticRequireConfigured($cfg);
    $r = resticRun(['snapshots', '--json'], resticRepoEnv($cfg), 120);
    if ((int)$r['code'] !== 0) {
        throw new RuntimeException(resticErrorFromResult($r));
    }
    $data = json_decode((string)$r['stdout'], true);
    if (!is_array($data)) return [];
    $out = [];
    foreach ($data as $snap) {
        if (!is_array($snap)) continue;
        $summary = is_array($snap['summary'] ?? null) ? $snap['summary'] : [];
        $out[] = [
            'id' => (string)($snap['id'] ?? ''),
            'short_id' => (string)($snap['short_id'] ?? substr((string)($snap['id'] ?? ''), 0, 8)),
            'time' => (string)($snap['time'] ?? ''),
            'hostname' => (string)($snap['hostname'] ?? ''),
            'username' => (string)($snap['username'] ?? ''),
            'tags' => is_array($snap['tags'] ?? null) ? array_values($snap['tags']) : [],
            'paths' => is_array($snap['paths'] ?? null) ? array_values($snap['paths']) : [],
            'size' => backupSafeInt($summary['total_bytes_processed'] ?? $summary['data_added'] ?? 0),
            'files' => backupSafeInt($summary['total_files_processed'] ?? $summary['files_new'] ?? 0),
        ];
    }
    usort($out, static function ($a, $b) {
        return strcmp((string)$b['time'], (string)$a['time']);
    });
    return $out;
}

function resticStats(array $cfg): array {
    resticRequireConfigured($cfg);
    $r = resticRun(['stats', '--mode', 'raw-data', '--json'], resticRepoEnv($cfg), 120);
    if ((int)$r['code'] !== 0) {
        throw new RuntimeException(resticErrorFromResult($r));
    }
    $data = json_decode((string)$r['stdout'], true);
    if (!is_array($data)) return ['total_size' => 0, 'total_file_count' => 0, 'snapshots_count' => 0];
    $fileCount = backupSafeInt($data['total_file_count'] ?? 0);
    if ($fileCount === 0) {
        // raw-data reports blobs; restore-size is metadata-only and gives the file count.
        $restore = resticRun(['stats', '--mode', 'restore-size', '--json'], resticRepoEnv($cfg), 120);
        if ((int)$restore['code'] === 0) {
            $restoreData = json_decode((string)$restore['stdout'], true);
            if (is_array($restoreData)) $fileCount = backupSafeInt($restoreData['total_file_count'] ?? 0);
        }
    }
    return [
        'total_size' => backupSafeInt($data['total_size'] ?? 0),
        'total_file_count' => $fileCount,
        'total_blob_count' => backupSafeInt($data['total_blob_count'] ?? 0),
        'snapshots_count' => backupSafeInt($data['snapshots_count'] ?? 0),
    ];
}

function resticList(array $cfg, string $snapshot, string $path = ''): array {
    resticRequireConfigured($cfg);
    $snapshot = trim($snapshot);
    if ($snapshot === '' || !preg_match('/^[a-f0-9]{4,64}$/i', $snapshot)) {
        throw new InvalidArgumentException('Invalid snapshot id');
    }
    $args = ['ls', '--json', $snapshot];
    if ($path !== '' && $path !== '/') {
        $args[] = $path;
    }
    $r = resticRun($args, resticRepoEnv($cfg), 180, null, 12 * 1024 * 1024);
    if ((int)$r['code'] !== 0 && (int)$r['code'] !== 141) {
        throw new RuntimeException(resticErrorFromResult($r));
    }
    $parent = $path === '' ? '' : rtrim($path, '/');
    $entries = [];
    foreach ($r['lines'] as $line) {
        $line = trim((string)$line);
        if ($line === '' || $line[0] !== '{') continue;
        $node = json_decode($line, true);
        if (!is_array($node) || (($node['struct_type'] ?? '') !== 'node')) continue;
        $nodePath = (string)($node['path'] ?? '');
        if ($nodePath === '') continue;
        if ($parent !== '') {
            if ($nodePath === $parent) continue;
            $prefix = $parent . '/';
            if (!str_starts_with($nodePath, $prefix)) continue;
            $rest = substr($nodePath, strlen($prefix));
            if ($rest === '' || str_contains($rest, '/')) continue;
        } else {
            $rest = ltrim($nodePath, '/');
            if ($rest === '' || str_contains($rest, '/')) continue;
        }
        $type = (string)($node['type'] ?? 'file');
        $entries[$nodePath] = [
            'name' => (string)($node['name'] ?? basename($nodePath)),
            'path' => $nodePath,
            'type' => $type,
            'size' => backupSafeInt($node['size'] ?? 0),
            'mtime' => (string)($node['mtime'] ?? ''),
        ];
    }
    $entries = array_values($entries);
    usort($entries, static function ($a, $b) {
        $ad = ($a['type'] ?? '') === 'dir';
        $bd = ($b['type'] ?? '') === 'dir';
        if ($ad !== $bd) return $ad ? -1 : 1;
        return strcasecmp((string)$a['name'], (string)$b['name']);
    });
    return ['path' => $path, 'entries' => array_slice($entries, 0, 2000), 'truncated' => (bool)$r['truncated']];
}

/* ---------------------------------------------------------------------
 * Jobs (PostgreSQL; filesystem fallback for offline tests)
 * ------------------------------------------------------------------- */

function resticJobsDbAvailable(): bool {
    return function_exists('getDB');
}

function resticEnsureJobsSchema(): void {
    static $ready = false;
    if ($ready || !resticJobsDbAvailable()) return;
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS backup_jobs (
        id TEXT PRIMARY KEY,
        type TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'queued',
        created_at BIGINT NOT NULL DEFAULT 0,
        started_at BIGINT NOT NULL DEFAULT 0,
        finished_at BIGINT NOT NULL DEFAULT 0,
        progress TEXT NOT NULL DEFAULT '{}',
        summary TEXT NOT NULL DEFAULT '{}',
        error TEXT NOT NULL DEFAULT '',
        cancel_requested INTEGER NOT NULL DEFAULT 0,
        restic_pid INTEGER NOT NULL DEFAULT 0,
        worker_pid INTEGER NOT NULL DEFAULT 0,
        params TEXT NOT NULL DEFAULT '{}',
        password_enc TEXT NOT NULL DEFAULT '',
        log TEXT NOT NULL DEFAULT '',
        updated_at BIGINT NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_backup_jobs_created_at ON backup_jobs(created_at DESC)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_backup_jobs_status ON backup_jobs(status)");
    $ready = true;
    resticMigrateJobsFromFs();
}

function resticMigrateJobsFromFs(): void {
    static $migrated = false;
    if ($migrated || !resticJobsDbAvailable()) return;
    $migrated = true;
    $dir = resticJobsDir();
    $files = glob($dir . '/*.json') ?: [];
    if ($files === []) return;
    $db = getDB();
    foreach ($files as $file) {
        $data = json_decode((string)@file_get_contents($file), true);
        if (!is_array($data) || !preg_match('/^[a-f0-9]{16}$/', (string)($data['id'] ?? ''))) continue;
        $id = (string)$data['id'];
        $exists = $db->prepare('SELECT 1 FROM backup_jobs WHERE id = :id LIMIT 1');
        $exists->bindValue(':id', $id, PDO::PARAM_STR);
        if ($exists->execute()->fetch()) continue;
        $logPath = $dir . '/' . $id . '.log';
        $log = is_readable($logPath) ? (string)@file_get_contents($logPath) : '';
        $password = (string)($data['params']['password'] ?? $data['password'] ?? '');
        unset($data['params']['password'], $data['password']);
        $row = [
            'id' => $id,
            'type' => (string)($data['type'] ?? ''),
            'status' => (string)($data['status'] ?? 'queued'),
            'created_at' => backupSafeInt($data['created_at'] ?? 0),
            'started_at' => backupSafeInt($data['started_at'] ?? 0),
            'finished_at' => backupSafeInt($data['finished_at'] ?? 0),
            'progress' => (string)json_encode($data['progress'] ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'summary' => (string)json_encode($data['summary'] ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error' => (string)($data['error'] ?? ''),
            'cancel_requested' => !empty($data['cancel_requested']) ? 1 : 0,
            'restic_pid' => backupSafeInt($data['restic_pid'] ?? 0),
            'worker_pid' => backupSafeInt($data['worker_pid'] ?? 0),
            'params' => (string)json_encode($data['params'] ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'password_enc' => $password !== '' ? resticEncryptPayload($password) : '',
            'log' => $log,
            'updated_at' => time(),
        ];
        $stmt = $db->prepare('INSERT INTO backup_jobs(id,type,status,created_at,started_at,finished_at,progress,summary,error,cancel_requested,restic_pid,worker_pid,params,password_enc,log,updated_at)
            VALUES(:id,:type,:status,:created_at,:started_at,:finished_at,:progress,:summary,:error,:cancel_requested,:restic_pid,:worker_pid,:params,:password_enc,:log,:updated_at)');
        foreach ($row as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        @unlink($file);
        if (is_file($logPath)) @unlink($logPath);
    }
}

function resticJobPath(string $id): string {
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
        throw new InvalidArgumentException('Invalid job id');
    }
    return resticJobsDir() . '/' . $id . '.json';
}

function resticJobLogPath(string $id): string {
    return resticJobsDir() . '/' . $id . '.log';
}

function resticJobRowToArray(array $row): array {
    $progress = json_decode((string)($row['progress'] ?? '{}'), true);
    $summary = json_decode((string)($row['summary'] ?? '{}'), true);
    $params = json_decode((string)($row['params'] ?? '{}'), true);
    if (!is_array($progress)) $progress = [];
    if (!is_array($summary)) $summary = [];
    if (!is_array($params)) $params = [];
    $passwordEnc = (string)($row['password_enc'] ?? '');
    if ($passwordEnc !== '' && empty($params['password'])) {
        $plain = resticDecryptPayload($passwordEnc);
        if (is_string($plain) && $plain !== '') $params['password'] = $plain;
    }
    return [
        'id' => (string)($row['id'] ?? ''),
        'type' => (string)($row['type'] ?? ''),
        'status' => (string)($row['status'] ?? ''),
        'created_at' => backupSafeInt($row['created_at'] ?? 0),
        'started_at' => backupSafeInt($row['started_at'] ?? 0),
        'finished_at' => backupSafeInt($row['finished_at'] ?? 0),
        'progress' => $progress,
        'summary' => $summary,
        'error' => (string)($row['error'] ?? ''),
        'cancel_requested' => !empty($row['cancel_requested']),
        'restic_pid' => backupSafeInt($row['restic_pid'] ?? 0),
        'worker_pid' => backupSafeInt($row['worker_pid'] ?? 0),
        'params' => $params,
        '_log' => (string)($row['log'] ?? ''),
    ];
}

/** Prefer filesystem when a job file already exists (offline tests / in-flight legacy). */
function resticJobUsesFilesystem(string $id): bool {
    return is_file(resticJobPath($id));
}

function resticJobWrite(array $job): void {
    $id = (string)($job['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
        throw new InvalidArgumentException('Invalid job id');
    }
    $useFs = resticJobUsesFilesystem($id) || !resticJobsDbAvailable();
    if (!$useFs) {
        resticEnsureJobsSchema();
        $password = (string)($job['params']['password'] ?? $job['password'] ?? '');
        $params = $job['params'] ?? [];
        if (!is_array($params)) $params = [];
        unset($params['password'], $job['password']);
        $status = (string)($job['status'] ?? 'queued');
        if (in_array($status, ['success', 'error', 'cancelled'], true)) $password = '';
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO backup_jobs(id,type,status,created_at,started_at,finished_at,progress,summary,error,cancel_requested,restic_pid,worker_pid,params,password_enc,log,updated_at)
            VALUES(:id,:type,:status,:created_at,:started_at,:finished_at,:progress,:summary,:error,:cancel_requested,:restic_pid,:worker_pid,:params,:password_enc,COALESCE((SELECT log FROM backup_jobs bj WHERE bj.id = :id_log), \'\'),:updated_at)
            ON CONFLICT(id) DO UPDATE SET
                type = EXCLUDED.type,
                status = EXCLUDED.status,
                created_at = EXCLUDED.created_at,
                started_at = EXCLUDED.started_at,
                finished_at = EXCLUDED.finished_at,
                progress = EXCLUDED.progress,
                summary = EXCLUDED.summary,
                error = EXCLUDED.error,
                cancel_requested = EXCLUDED.cancel_requested,
                restic_pid = EXCLUDED.restic_pid,
                worker_pid = EXCLUDED.worker_pid,
                params = EXCLUDED.params,
                password_enc = EXCLUDED.password_enc,
                updated_at = EXCLUDED.updated_at');
        $bind = [
            ':id' => $id,
            ':id_log' => $id,
            ':type' => (string)($job['type'] ?? ''),
            ':status' => $status,
            ':created_at' => backupSafeInt($job['created_at'] ?? time()),
            ':started_at' => backupSafeInt($job['started_at'] ?? 0),
            ':finished_at' => backupSafeInt($job['finished_at'] ?? 0),
            ':progress' => (string)json_encode($job['progress'] ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':summary' => (string)json_encode($job['summary'] ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':error' => (string)($job['error'] ?? ''),
            ':cancel_requested' => !empty($job['cancel_requested']) ? 1 : 0,
            ':restic_pid' => backupSafeInt($job['restic_pid'] ?? 0),
            ':worker_pid' => backupSafeInt($job['worker_pid'] ?? 0),
            ':params' => (string)json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':password_enc' => $password !== '' ? resticEncryptPayload($password) : '',
            ':updated_at' => time(),
        ];
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return;
    }
    $path = resticJobPath($id);
    $tmp = $path . '.tmp.' . getmypid();
    $json = (string)json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        @file_put_contents($path, $json, LOCK_EX);
        return;
    }
    @chmod($tmp, 0600);
    @rename($tmp, $path);
}

function resticJobRead(string $id): array {
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) return [];
    $path = resticJobPath($id);
    if (is_readable($path)) {
        $data = json_decode((string)file_get_contents($path), true);
        if (is_array($data)) return $data;
    }
    if (resticJobsDbAvailable()) {
        resticEnsureJobsSchema();
        $stmt = getDB()->prepare('SELECT * FROM backup_jobs WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) return resticJobRowToArray($row);
    }
    return [];
}

function resticJobPublic(array $job): array {
    unset($job['password'], $job['params']['password'], $job['_log']);
    if (!empty($job['log_file'])) unset($job['log_file']);
    return $job;
}

function resticJobs(int $limit = 30): array {
    $limit = max(1, min(200, $limit));
    if (resticJobsDbAvailable()) {
        resticEnsureJobsSchema();
        $stmt = getDB()->prepare('SELECT * FROM backup_jobs ORDER BY created_at DESC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $res = $stmt->execute();
        $out = [];
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $out[] = resticJobPublic(resticJobRowToArray($row));
        }
        return $out;
    }
    $files = glob(resticJobsDir() . '/*.json') ?: [];
    usort($files, static function ($a, $b) {
        return (int)@filemtime($b) <=> (int)@filemtime($a);
    });
    $out = [];
    foreach (array_slice($files, 0, $limit) as $file) {
        $data = json_decode((string)@file_get_contents($file), true);
        if (!is_array($data)) continue;
        $out[] = resticJobPublic($data);
    }
    return $out;
}

function resticJobSpawn(string $type, array $params = []): array {
    $cfg = resticLoadConfig();
    $repo = resticRepoConfig($cfg, (string)($params['profile'] ?? ''));
    resticRequireConfigured($repo);
    resticSanitizeRuntimeDirs();
    $id = bin2hex(random_bytes(8));
    $job = [
        'id' => $id,
        'type' => $type,
        'status' => 'queued',
        'created_at' => time(),
        'started_at' => 0,
        'finished_at' => 0,
        'progress' => ['percent' => 0, 'files_done' => 0, 'total_files' => 0, 'bytes_done' => 0, 'total_bytes' => 0],
        'summary' => [],
        'error' => '',
        'cancel_requested' => false,
        'restic_pid' => 0,
        'worker_pid' => 0,
        'params' => array_merge([
            'repository' => (string)($repo['repository'] ?? ''),
            'password' => (string)($repo['password'] ?? ''),
            'host' => (string)($repo['host'] ?? 'appdashboard'),
            'sources' => $repo['sources'] ?? [],
            'tags' => $cfg['tags'] ?? [],
            'excludes' => $repo['excludes'] ?? [],
            'retention' => $repo['retention'] ?? [],
        ], $params),
    ];
    // Never let caller-supplied parameters replace the stored credentials.
    $job['params']['repository'] = (string)($repo['repository'] ?? '');
    $job['params']['password'] = (string)($repo['password'] ?? '');
    resticJobWrite($job);
    resticJobAppendLog($id, 'queued ' . $type);

    $logRedirect = resticJobUsesFilesystem($id) || !resticJobsDbAvailable()
        ? resticJobLogPath($id)
        : '/dev/null';
    $cmd = 'nohup ' . escapeshellarg(resticPhpCli()) . ' ' . escapeshellarg(__DIR__ . '/worker.php') . ' ' . escapeshellarg($id)
        . ' >> ' . escapeshellarg($logRedirect) . ' 2>&1 &';
    $started = false;
    if (function_exists('exec')) {
        @exec($cmd, $ignored, $rc);
        $started = true;
    } elseif (function_exists('proc_open')) {
        $desc = [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']];
        $proc = @proc_open(['/bin/sh', '-c', $cmd], $desc, $pipes);
        if (is_resource($proc)) {
            @proc_close($proc);
            $started = true;
        }
    }
    if (!$started) {
        $job['status'] = 'error';
        $job['error'] = 'Unable to start the job worker';
        $job['finished_at'] = time();
        unset($job['params']['password']);
        resticJobWrite($job);
    }
    return resticJobPublic($job);
}

function resticJobCancel(string $id): array {
    $job = resticJobRead($id);
    if ($job === []) throw new InvalidArgumentException('Job not found');
    if (in_array((string)($job['status'] ?? ''), ['queued', 'running'], true)) {
        $job['cancel_requested'] = true;
        if ((string)($job['status'] ?? '') === 'queued') {
            $job['status'] = 'cancelled';
            $job['finished_at'] = time();
        }
        $pid = (int)($job['restic_pid'] ?? 0);
        if ($pid > 0 && function_exists('posix_kill')) {
            @posix_kill($pid, 15);
        }
        resticJobWrite($job);
    }
    return resticJobPublic($job);
}

function resticJobAppendLog(string $id, string $line, bool $timestamp = true): void {
    $entry = ($timestamp ? '[' . gmdate('c') . '] ' : '') . rtrim($line, "\r\n") . "\n";
    if (!resticJobUsesFilesystem($id) && resticJobsDbAvailable()) {
        resticEnsureJobsSchema();
        $stmt = getDB()->prepare('UPDATE backup_jobs SET log = CASE
            WHEN length(log) > 200000 THEN right(log || :entry, 200000)
            ELSE log || :entry2
        END, updated_at = :ts WHERE id = :id');
        $stmt->bindValue(':entry', $entry, PDO::PARAM_STR);
        $stmt->bindValue(':entry2', $entry, PDO::PARAM_STR);
        $stmt->bindValue(':ts', time(), PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $stmt->execute();
        return;
    }
    @file_put_contents(resticJobLogPath($id), $entry, FILE_APPEND | LOCK_EX);
}

function resticJobTail(string $id, int $bytes = 20000): string {
    $path = resticJobLogPath($id);
    if (is_readable($path)) {
        $size = (int)@filesize($path);
        $fp = @fopen($path, 'rb');
        if (!$fp) return '';
        if ($size > $bytes) @fseek($fp, $size - $bytes);
        $data = (string)stream_get_contents($fp);
        @fclose($fp);
        return $data;
    }
    if (resticJobsDbAvailable()) {
        resticEnsureJobsSchema();
        $stmt = getDB()->prepare('SELECT log FROM backup_jobs WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        $log = is_array($row) ? (string)($row['log'] ?? '') : '';
        if ($log === '') return '';
        if ($bytes > 0 && strlen($log) > $bytes) return substr($log, -$bytes);
        return $log;
    }
    return '';
}

/* ---------------------------------------------------------------------
 * Worker-side execution
 * ------------------------------------------------------------------- */

function resticBackupArgs(array $job): array {
    $p = $job['params'] ?? [];
    $args = ['backup', '--json', '--host', (string)($p['host'] ?? 'appdashboard')];
    foreach ((array)($p['tags'] ?? []) as $tag) {
        if (is_string($tag) && $tag !== '') {
            $args[] = '--tag';
            $args[] = $tag;
        }
    }
    foreach ((array)($p['excludes'] ?? []) as $exclude) {
        if (is_string($exclude) && $exclude !== '') {
            $args[] = '--exclude';
            $args[] = $exclude;
        }
    }
    $sources = array_values(array_filter((array)($p['sources'] ?? []), 'is_string'));
    if ($sources === []) throw new RuntimeException('No backup sources configured');
    // Resolve host-relative paths (e.g. /home/… → /host/home/…).
    $sources = array_values(array_map(static function (string $s): string {
        $resolved = resticResolveHostPath($s);
        $resolved = $resolved !== null ? $resolved : $s;
        return resticStageHostSource($resolved);
    }, $sources));
    $root = resticHostRoot();
    $hostRoot = rtrim($root, '/') ?: '/';
    $needsPseudo = false;
    foreach ($sources as $source) {
        if ($source === $hostRoot || str_starts_with($source, $hostRoot . '/')) $needsPseudo = true;
    }
    if ($needsPseudo && $hostRoot !== '/' && $hostRoot !== '') {
        foreach (['/proc', '/sys', '/dev', '/run'] as $pseudo) {
            $args[] = '--exclude';
            $args[] = $hostRoot . $pseudo;
        }
    }
    foreach ($sources as $source) $args[] = $source;
    return $args;
}

function resticForgetArgs(array $job): array {
    $ret = is_array($job['params']['retention'] ?? null) ? $job['params']['retention'] : [];
    $map = [
        'keep_last' => '--keep-last',
        'keep_hourly' => '--keep-hourly',
        'keep_daily' => '--keep-daily',
        'keep_weekly' => '--keep-weekly',
        'keep_monthly' => '--keep-monthly',
        'keep_yearly' => '--keep-yearly',
    ];
    $args = ['forget'];
    $count = 0;
    $within = trim((string)($ret['keep_within'] ?? ''));
    if ($within !== '') {
        $args[] = '--keep-within';
        $args[] = $within;
        $count++;
    }
    foreach ($map as $key => $flag) {
        $n = backupSafeInt($ret[$key] ?? 0);
        if ($n > 0) {
            $args[] = $flag;
            $args[] = (string)$n;
            $count++;
        }
    }
    if ($count === 0) throw new RuntimeException('No retention rules configured');
    if (!empty($ret['prune'])) $args[] = '--prune';
    return $args;
}

function resticJobArgs(array $job): array {
    $p = $job['params'] ?? [];
    switch ((string)($job['type'] ?? '')) {
        case 'backup':
            return resticBackupArgs($job);
        case 'restore':
            $snapshot = (string)($p['snapshot'] ?? '');
            $target = (string)($p['target'] ?? '');
            if ($snapshot === '' || $target === '') throw new RuntimeException('Snapshot and target are required');
            // Restore targets are host paths: map them through the host mount so
            // files land on the host filesystem instead of inside the container.
            $resolvedTarget = resticResolveHostPath($target);
            if ($resolvedTarget === null) throw new RuntimeException('Invalid restore target');
            $args = ['restore', $snapshot, '--target', $resolvedTarget, '--verify'];
            $include = trim((string)($p['include'] ?? ''));
            if ($include !== '') {
                $args[] = '--include';
                $args[] = $include;
            }
            return $args;
        case 'forget':
            return resticForgetArgs($job);
        case 'delete_snapshot':
            $snapshot = (string)($p['snapshot'] ?? '');
            if ($snapshot === '') throw new RuntimeException('Snapshot is required');
            return ['forget', $snapshot, '--prune'];
        case 'check':
            return !empty($p['read_data']) ? ['check', '--read-data-subset=1%'] : ['check'];
        case 'prune':
            return ['prune'];
        case 'unlock':
            return ['unlock', '--remove-all'];
        case 'rebuild_index':
            return ['repair', 'index'];
        case 'init':
            return ['init'];
        default:
            throw new RuntimeException('Unknown job type');
    }
}

function resticProgressFromLine(array $job, string $line): array {
    $line = trim($line);
    if ($line === '' || $line[0] !== '{') return $job;
    $data = json_decode($line, true);
    if (!is_array($data)) return $job;
    $type = (string)($data['message_type'] ?? '');
    if ($type === 'status') {
        $job['progress'] = [
            'percent' => round((float)($data['percent_done'] ?? 0) * 100, 2),
            'files_done' => backupSafeInt($data['files_done'] ?? 0),
            'total_files' => backupSafeInt($data['total_files'] ?? 0),
            'bytes_done' => backupSafeInt($data['bytes_done'] ?? 0),
            'total_bytes' => backupSafeInt($data['total_bytes'] ?? 0),
        ];
        $current = $data['current_files'] ?? [];
        if (is_array($current) && $current !== []) {
            $job['progress']['current'] = (string)reset($current);
        }
    } elseif ($type === 'summary') {
        $job['summary'] = [
            'snapshot_id' => (string)($data['snapshot_id'] ?? ''),
            'files_new' => backupSafeInt($data['files_new'] ?? 0),
            'files_changed' => backupSafeInt($data['files_changed'] ?? 0),
            'total_files_processed' => backupSafeInt($data['total_files_processed'] ?? 0),
            'total_bytes_processed' => backupSafeInt($data['total_bytes_processed'] ?? 0),
            'data_added' => backupSafeInt($data['data_added'] ?? 0),
        ];
        $job['progress']['percent'] = 100;
        $job['progress']['files_done'] = $job['summary']['total_files_processed'];
        $job['progress']['bytes_done'] = $job['summary']['total_bytes_processed'];
    }
    return $job;
}

function resticRunJob(array $job): array {
    $p = $job['params'] ?? [];
    $env = [
        'RESTIC_REPOSITORY' => resticRepositoryLocation((string)($p['repository'] ?? '')),
        'RESTIC_PASSWORD' => (string)($p['password'] ?? ''),
    ];
    $args = resticJobArgs($job);
    // Maintenance commands can race with a just-finished backup/restore lock;
    // let restic wait briefly instead of failing the whole job.
    if (in_array((string)($job['type'] ?? ''), ['forget', 'delete_snapshot', 'prune', 'check', 'rebuild_index'], true)) {
        $args = array_merge(['--retry-lock', '2m'], $args);
    }

    if ((string)($job['type'] ?? '') === 'restore') {
        $target = resticResolveHostPath((string)($p['target'] ?? ''));
        if ($target !== null && !is_dir($target)) @mkdir($target, 0775, true);
    }
    if ((string)($job['type'] ?? '') === 'backup') {
        resticEnsureLocalRepository([
            'repository' => (string)($p['repository'] ?? ''),
            'password' => (string)($p['password'] ?? ''),
        ]);
    }

    $result = resticRun(
        $args,
        $env,
        0,
        function (string $line) use (&$job) {
            $job = resticProgressFromLine($job, $line);
            resticJobAppendLog((string)$job['id'], $line, false);
            $now = time();
            $lastWrite = (int)($job['_last_write'] ?? 0);
            if ($now !== $lastWrite) {
                $job['_last_write'] = $now;
                $current = resticJobRead((string)$job['id']);
                if (!empty($current['cancel_requested'])) $job['cancel_requested'] = true;
                resticJobWrite($job);
            }
        },
        0,
        function (int $pid) use (&$job) {
            $job['restic_pid'] = $pid;
            $job['worker_pid'] = getmypid();
            resticJobWrite($job);
        }
    );
    unset($job['_last_write']);
    if ((string)($job['type'] ?? '') === 'backup') {
        foreach (glob(resticDataDir() . '/stage/*') ?: [] as $staged) {
            if (is_dir($staged)) {
                @exec('rm -rf ' . escapeshellarg($staged));
            } else {
                @unlink($staged);
            }
        }
    }
    return ['result' => $result, 'job' => $job];
}

/* ---------------------------------------------------------------------
 * Scheduler
 * ------------------------------------------------------------------- */

function resticScheduleStatePath(): string {
    return resticDataDir() . '/schedule.json';
}

function resticLoadScheduleState(): array {
    $path = resticScheduleStatePath();
    if (!is_readable($path)) return ['next_run' => 0, 'last_run' => 0, 'profiles' => []];
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) return ['next_run' => 0, 'last_run' => 0, 'profiles' => []];
    $profiles = [];
    foreach ((array)($data['profiles'] ?? []) as $id => $t) {
        $profiles[(string)$id] = backupSafeInt($t);
    }
    return [
        'next_run' => backupSafeInt($data['next_run'] ?? 0),
        'last_run' => backupSafeInt($data['last_run'] ?? 0),
        'profiles' => $profiles,
    ];
}

function resticSaveScheduleState(array $state): void {
    $path = resticScheduleStatePath();
    if (!isset($state['profiles']) || !is_array($state['profiles'])) $state['profiles'] = [];
    @file_put_contents($path, (string)json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($path, 0600);
}

function resticComputeNextRun(array $schedule, int $from = 0): int {
    $from = $from > 0 ? $from : time();
    $time = (string)($schedule['time'] ?? '03:00');
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
        $time = '03:00';
        $m = [0, '03', '00'];
    }
    $frequency = (string)($schedule['frequency'] ?? 'daily');
    if ($frequency === 'hourly') {
        $hours = backupClampInt($schedule['interval_hours'] ?? 6, 1, 168, 6);
        return $from + $hours * 3600;
    }
    $tz = new DateTimeZone(date_default_timezone_get());
    $dt = new DateTime('@' . $from);
    $dt->setTimezone($tz);
    $hh = (int)$m[1];
    $mm = (int)$m[2];
    if ($frequency === 'weekly') {
        $weekday = backupClampInt($schedule['weekday'] ?? 1, 1, 7, 1);
        $dt->setTime($hh, $mm, 0);
        $current = (int)$dt->format('N');
        $delta = ($weekday - $current + 7) % 7;
        if ($delta === 0 && $dt->getTimestamp() <= $from) $delta = 7;
        $dt->modify('+' . $delta . ' days');
        return $dt->getTimestamp();
    }
    if ($frequency === 'monthly') {
        $monthday = backupClampInt($schedule['monthday'] ?? 1, 1, 28, 1);
        $dt->setDate((int)$dt->format('Y'), (int)$dt->format('n'), $monthday);
        $dt->setTime($hh, $mm, 0);
        if ($dt->getTimestamp() <= $from) $dt->modify('+1 month');
        return $dt->getTimestamp();
    }
    $dt->setTime($hh, $mm, 0);
    if ($dt->getTimestamp() <= $from) $dt->modify('+1 day');
    return $dt->getTimestamp();
}

function resticSchedulerTick(): void {
    $cfg = resticLoadConfig();
    $state = resticLoadScheduleState();
    if (!isset($state['profiles']) || !is_array($state['profiles'])) {
        $state['profiles'] = [];
    }

    $profiles = (array)($cfg['backups'] ?? []);
    $running = false;
    foreach (resticJobs(20) as $job) {
        if (($job['type'] ?? '') === 'backup' && in_array((string)($job['status'] ?? ''), ['queued', 'running'], true)) {
            $running = true;
            break;
        }
    }

    foreach ($profiles as $profile) {
        $id = (string)($profile['id'] ?? '');
        if ($id === '') continue;
        $schedule = is_array($profile['schedule'] ?? null) ? $profile['schedule'] : [];
        if (empty($schedule['enabled'])) {
            $state['profiles'][$id] = 0;
            continue;
        }
        $repo = resticRepoConfig($cfg, $id);
        if ((string)($repo['repository'] ?? '') === '' || (string)($repo['password'] ?? '') === '') {
            // This backup has no repository configured yet.
            $state['profiles'][$id] = 0;
            continue;
        }
        $next = backupSafeInt($state['profiles'][$id] ?? 0);
        if ($next <= 0) {
            // No deadline recorded yet: run as soon as possible, like the old
            // scheduler did for missed dates.
            $next = time();
        }
        if (time() < $next || $running) continue;
        try {
            resticJobSpawn('backup', [
                'profile' => $id,
                'profile_name' => (string)($profile['name'] ?? ''),
                'sources' => $profile['sources'] ?? [],
                'excludes' => $profile['excludes'] ?? [],
                'retention' => $profile['retention'] ?? [],
                'trigger' => 'schedule',
            ]);
            $running = true;
            $state['last_run'] = time();
            $state['profiles'][$id] = resticComputeNextRun($schedule, time());
        } catch (Throwable $e) {
            $state['last_run'] = time();
            $state['profiles'][$id] = resticComputeNextRun($schedule, time());
            @file_put_contents(resticDataDir() . '/scheduler.log', '[' . gmdate('c') . '] ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    // Legacy single-schedule fallback (config saved before the named list).
    $schedule = is_array($cfg['schedule'] ?? null) ? $cfg['schedule'] : [];
    if (empty($profiles) && !empty($schedule['enabled']) && !$running
        && (string)($cfg['repository'] ?? '') !== '' && (string)($cfg['password'] ?? '') !== '') {
        $next = backupSafeInt($state['next_run'] ?? 0);
        if ($next <= 0) {
            $state['next_run'] = resticComputeNextRun($schedule, time());
        } elseif (time() >= $next) {
            try {
                resticJobSpawn('backup', ['sources' => $cfg['sources'] ?? [], 'trigger' => 'schedule']);
                $state['last_run'] = time();
                $state['next_run'] = resticComputeNextRun($schedule, time());
            } catch (Throwable $e) {
                $state['last_run'] = time();
                $state['next_run'] = resticComputeNextRun($schedule, time());
                @file_put_contents(resticDataDir() . '/scheduler.log', '[' . gmdate('c') . '] ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            }
        }
    }

    // Keep the UI "next run" in sync with the earliest scheduled profile.
    $earliest = 0;
    foreach ($state['profiles'] as $t) {
        $t = backupSafeInt($t);
        if ($t > 0 && ($earliest === 0 || $t < $earliest)) $earliest = $t;
    }
    if ($earliest > 0) $state['next_run'] = $earliest;

    resticSaveScheduleState($state);
}

/* ---------------------------------------------------------------------
 * Host filesystem access
 * ------------------------------------------------------------------- */

function resticHostRoot(): string {
    $env = trim((string)getenv('RESTIC_HOST_ROOT'));
    if ($env !== '' && is_dir($env)) {
        $real = realpath($env);
        if ($real !== false) return rtrim($real, '/') ?: '/';
    }
    if (is_dir('/host')) {
        $real = realpath('/host');
        if ($real !== false) return rtrim($real, '/') ?: '/';
    }
    return '/';
}

/** Docker Desktop keeps the real /home on virtiofs, not under the /host bind of /. */
function resticVirtiofsHome(): ?string {
    foreach (['/host/run/host_virtiofs/home', '/host/run/host_mark/home'] as $candidate) {
        if (is_dir($candidate)) return $candidate;
    }
    return null;
}

function resticMapDockerDesktopHome(string $local): string {
    if ($local !== '/host/home' && !str_starts_with($local, '/host/home/')) return $local;
    if (file_exists($local)) return $local;
    $vfs = resticVirtiofsHome();
    if ($vfs === null) return $local;
    return $vfs . substr($local, strlen('/host/home'));
}

/** @return array{dest:string,prefix:string}|null */
function resticBindHome(): ?array {
    $dest = trim((string)getenv('RESTIC_BIND_HOME'));
    $prefix = rtrim(trim((string)getenv('RESTIC_BIND_HOME_PREFIX')), '/');
    if ($dest === '' || $prefix === '' || $prefix[0] !== '/' || !is_dir($dest)) return null;
    $real = realpath($dest);
    if ($real === false) return null;
    return ['dest' => rtrim($real, '/') ?: '/', 'prefix' => $prefix];
}

function resticHostDisplayPath(string $local): string {
    $bind = resticBindHome();
    if ($bind !== null && ($local === $bind['dest'] || str_starts_with($local, $bind['dest'] . '/'))) {
        return $bind['prefix'] . substr($local, strlen($bind['dest']));
    }
    $vfs = resticVirtiofsHome();
    if ($vfs !== null && ($local === $vfs || str_starts_with($local, $vfs . '/'))) {
        return '/home' . substr($local, strlen($vfs));
    }
    $root = rtrim(resticHostRoot(), '/');
    if ($root !== '' && $root !== '/' && ($local === $root || str_starts_with($local, $root . '/'))) {
        $rel = substr($local, strlen($root));
        return $rel === '' ? '/' : $rel;
    }
    return $local;
}

function resticResolveHostPath(string $path): ?string {
    $path = trim($path);
    if ($path === '' || str_contains($path, "\0")) return null;
    if ($path[0] !== '/') return null;
    $bind = resticBindHome();
    if ($bind !== null && ($path === $bind['prefix'] || str_starts_with($path, $bind['prefix'] . '/'))) {
        return $bind['dest'] . substr($path, strlen($bind['prefix']));
    }
    $root = resticHostRoot();
    if ($root === '/' || $root === '') return $path;
    if ($path === $root || str_starts_with($path, $root . '/')) return resticMapDockerDesktopHome($path);
    $mapped = resticMapDockerDesktopHome($root . ($path === '/' ? '' : '/' . ltrim($path, '/')));
    // Prefer the host mount when that view exists (UI browse of /etc, /home, …).
    if (file_exists($mapped)) return $mapped;
    // Container-local paths (tests, APPDATA) stay unmapped.
    if (file_exists($path)) return $path;
    $parent = dirname($path);
    if ($parent !== '/' && is_dir($parent) && is_writable($parent)) return $path;
    return $mapped;
}

function resticAssertInsideRoot(string $real): bool {
    $bind = resticBindHome();
    if ($bind !== null && ($real === $bind['dest'] || str_starts_with($real, $bind['dest'] . '/'))) return true;
    $root = resticHostRoot();
    if ($root === '/' || $root === '') return true;
    $rootReal = realpath($root);
    if ($rootReal === false) $rootReal = $root;
    return $real === $rootReal || str_starts_with($real, rtrim($rootReal, '/') . '/');
}

function resticHostBrowse(string $path, bool $onlyFolders = true, bool $showHidden = false): array {
    $local = resticResolveHostPath($path);
    if ($local === null) throw new InvalidArgumentException('Invalid path');
    $real = realpath($local);
    if ($real === false || !is_dir($real)) throw new RuntimeException('Not a directory');
    if (!resticAssertInsideRoot($real)) throw new RuntimeException('Path is outside the host root');
    $root = resticHostRoot();
    $parent = dirname($real);
    if ($root !== '/' && str_starts_with($real, rtrim($root, '/') . '/') && dirname($real) === rtrim($root, '/')) {
        $parent = $root;
    }
    if ($real === rtrim($root, '/')) $parent = $root;
    $dh = @opendir($real);
    if ($dh === false) throw new RuntimeException('Cannot read directory');
    $entries = [];
    while (($name = readdir($dh)) !== false) {
        if ($name === '.' || $name === '..') continue;
        if (!$showHidden && isset($name[0]) && $name[0] === '.') continue;
        $full = $real . '/' . $name;
        $isDir = is_dir($full);
        if ($onlyFolders && !$isDir) continue;
        $mtime = @filemtime($full);
        $entries[] = [
            'name' => $name,
            'path' => resticHostDisplayPath($full),
            'type' => $isDir ? 'dir' : 'file',
            'size' => $isDir ? 0 : backupSafeInt(@filesize($full) ?: 0),
            'mtime' => $mtime !== false ? gmdate('c', (int)$mtime) : '',
        ];
    }
    closedir($dh);
    usort($entries, static function ($a, $b) {
        $ad = ($a['type'] ?? '') === 'dir';
        $bd = ($b['type'] ?? '') === 'dir';
        if ($ad !== $bd) return $ad ? -1 : 1;
        return strcasecmp((string)$a['name'], (string)$b['name']);
    });
    $vfs = resticVirtiofsHome();
    if ($vfs !== null && $real === rtrim($root, '/') && !is_dir($real . '/home')) {
        $entries[] = [
            'name' => 'home',
            'path' => '/home',
            'type' => 'dir',
            'size' => 0,
            'mtime' => '',
        ];
        usort($entries, static function ($a, $b) {
            $ad = ($a['type'] ?? '') === 'dir';
            $bd = ($b['type'] ?? '') === 'dir';
            if ($ad !== $bd) return $ad ? -1 : 1;
            return strcasecmp((string)$a['name'], (string)$b['name']);
        });
    }
    return [
        'root' => resticHostDisplayPath($root),
        'path' => resticHostDisplayPath($real),
        'parent' => resticHostDisplayPath($parent),
        'entries' => $entries,
    ];
}

function resticHostMkdir(string $parentPath, string $name): array {
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..' || preg_match('#[/\\\\]#', $name)) {
        throw new InvalidArgumentException('Invalid folder name');
    }
    $parent = resticResolveHostPath($parentPath);
    if ($parent === null) throw new InvalidArgumentException('Invalid parent path');
    $real = realpath($parent);
    if ($real === false || !is_dir($real)) throw new RuntimeException('Parent not found');
    if (!resticAssertInsideRoot($real)) throw new RuntimeException('Path is outside the host root');
    if (!is_writable($real)) throw new RuntimeException('Write denied for parent directory');
    $new = $real . '/' . $name;
    if (file_exists($new)) throw new RuntimeException('Already exists');
    if (!@mkdir($new, 0775)) throw new RuntimeException('mkdir failed');
    return ['ok' => true, 'path' => resticHostDisplayPath(realpath($new) ?: $new)];
}

function resticDiskSpace(string $path): array {
    $local = resticResolveHostPath($path);
    if ($local === null) throw new InvalidArgumentException('Invalid path');
    $probe = $local;
    while (!is_dir($probe) && $probe !== '/' && dirname($probe) !== $probe) {
        $probe = dirname($probe);
    }
    $real = realpath($probe);
    if ($real === false) $real = $probe;
    $total = @disk_total_space($real);
    $free = @disk_free_space($real);
    if ($total === false || $free === false) throw new RuntimeException('Could not read disk space');
    $used = $total - $free;
    return [
        'path' => resticHostDisplayPath($real),
        'total' => (float)$total,
        'free' => (float)$free,
        'used' => (float)$used,
        'percentUsed' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
    ];
}

function resticHostIps(): array {
    $ips = [];
    $raw = trim((string)@shell_exec('hostname -I 2>/dev/null'));
    foreach (preg_split('/\s+/', $raw) ?: [] as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;
        if (str_starts_with($ip, '127.') || $ip === '0.0.0.0') continue;
        $label = preg_match('/^(10\.|100\.(6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\.)/', $ip) ? 'vpnNetwork' : 'localNetwork';
        $ips[$ip] = ['ip' => $ip, 'label' => $label];
    }
    return array_values($ips);
}

function resticPathRwTest(string $path): array {
    $local = resticResolveHostPath($path);
    if ($local === null) throw new InvalidArgumentException('Only absolute local paths are supported');
    $real = realpath($local);
    if ($real === false || !is_dir($real)) throw new RuntimeException('Directory does not exist');
    if (!resticAssertInsideRoot($real)) throw new RuntimeException('Path is outside the host root');
    if (!is_writable($real)) throw new RuntimeException('Write denied');
    $user = 'unknown';
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $pw = posix_getpwuid(posix_geteuid());
        if (is_array($pw) && isset($pw['name'])) $user = (string)$pw['name'];
    }
    $tmp = $real . '/.restic_backup_rw_' . bin2hex(random_bytes(6)) . '.tmp';
    $payload = 'restic_backup_rw_' . bin2hex(random_bytes(8));
    if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
        throw new RuntimeException('Write denied for ' . $real);
    }
    $read = @file_get_contents($tmp);
    @unlink($tmp);
    return ['ok' => $read === $payload, 'path' => resticHostDisplayPath($real), 'phpUser' => $user];
}

/** Remove finished job artifacts older than N days. */
function resticCleanupJobs(int $days = 30): void {
    $cutoff = time() - $days * 86400;
    if (resticJobsDbAvailable()) {
        resticEnsureJobsSchema();
        $stmt = getDB()->prepare("DELETE FROM backup_jobs WHERE finished_at > 0 AND finished_at < :cutoff AND status IN ('success','error','cancelled')");
        $stmt->bindValue(':cutoff', $cutoff, PDO::PARAM_INT);
        $stmt->execute();
    }
    foreach (glob(resticJobsDir() . '/*') ?: [] as $file) {
        if (@filemtime($file) < $cutoff) @unlink($file);
    }
}
