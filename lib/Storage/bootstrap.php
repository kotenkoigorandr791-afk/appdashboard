<?php
/**
 * Dynamic Storage Manager — shared bootstrap and configuration.
 *
 * The storage layer unions the physical disks bind-mounted into /disks into a
 * single MergerFS mount at /storage. Every path can be overridden with an env
 * variable so the whole subsystem is testable without FUSE or PostgreSQL.
 */
declare(strict_types=1);

require_once __DIR__ . '/StorageDb.php';
require_once __DIR__ . '/DiskDetector.php';
require_once __DIR__ . '/DiskRegistry.php';
require_once __DIR__ . '/MergerFSManager.php';
require_once __DIR__ . '/DiskHealthChecker.php';
require_once __DIR__ . '/StorageMonitor.php';
require_once __DIR__ . '/StorageBackupManager.php';
require_once __DIR__ . '/DiskMigration.php';
require_once __DIR__ . '/MigrationRecovery.php';
require_once __DIR__ . '/StorageManager.php';
require_once __DIR__ . '/FileIndexDb.php';
require_once __DIR__ . '/FileIndex.php';
require_once __DIR__ . '/FileIndexWorker.php';

function storage_env(string $name, string $default = ''): string {
    $value = getenv($name);
    if ($value === false || trim((string)$value) === '') return $default;
    return trim((string)$value);
}

function storage_root(): string {
    return rtrim(storage_env('STORAGE_ROOT', '/storage'), '/') ?: '/storage';
}

function storage_disks_root(): string {
    return rtrim(storage_env('STORAGE_DISKS_ROOT', '/disks'), '/') ?: '/disks';
}

function storage_mountinfo_path(): string {
    return storage_env('STORAGE_MOUNTINFO_FILE', '/proc/self/mountinfo');
}

function storage_mergerfs_binary(): string {
    return storage_env('STORAGE_MERGERFS_BINARY', '') ?: 'mergerfs';
}

function storage_fusermount_binary(): string {
    $explicit = storage_env('STORAGE_FUSERMOUNT_BINARY', '');
    if ($explicit !== '') return $explicit;
    foreach (['fusermount3', 'fusermount'] as $binary) {
        if (storage_command_exists($binary)) return $binary;
    }
    return 'fusermount';
}

function storage_setfattr_binary(): string {
    return storage_env('STORAGE_SETFATTR_BINARY', '') ?: 'setfattr';
}

function storage_getfattr_binary(): string {
    return storage_env('STORAGE_GETFATTR_BINARY', '') ?: 'getfattr';
}

function storage_state_dir(): string {
    $explicit = storage_env('STORAGE_STATE_DIR', '');
    if ($explicit !== '') {
        storage_ensure_dir($explicit);
        return rtrim($explicit, '/');
    }
    $data = storage_env('APPDATA_DATA_DIR', '');
    if ($data === '') {
        storage_fail('APPDATA_DATA_DIR is not set and STORAGE_STATE_DIR was not given; refusing to use a repo-relative fallback.');
    }
    $dir = rtrim($data, '/') . '/storage';
    storage_ensure_dir($dir);
    return $dir;
}

/** Fail fast with a clear message instead of falling back to a repo/tmp path. */
function storage_fail(string $message): never {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, '[storage] ' . $message . PHP_EOL);
        exit(1);
    }
    throw new RuntimeException('[storage] ' . $message);
}

/**
 * Validate that every storage root is set, exists, is writable, and that
 * /storage (when required) is actually the mergerfs pool mount. Call this
 * once from each web/CLI entrypoint before any storage write.
 */
function storage_assert_ready(bool $requirePool = true): void {
    $appdataBase = storage_env('APPDATA_BASE_DIR', '');
    if ($appdataBase === '') storage_fail('APPDATA_BASE_DIR is not set.');
    $appdataData = storage_env('APPDATA_DATA_DIR', '');
    if ($appdataData === '') storage_fail('APPDATA_DATA_DIR is not set.');
    foreach ([$appdataBase, $appdataData] as $dir) {
        if (!is_dir($dir) || !is_writable($dir)) {
            storage_fail("Required storage directory is missing or not writable: $dir");
        }
    }
    if ($requirePool) {
        $root = storage_root();
        if (!is_dir($root)) storage_fail("STORAGE_ROOT ($root) does not exist.");
        if (!storage_pool_available()) {
            storage_fail("STORAGE_ROOT ($root) is not a mounted mergerfs pool. Refusing to write bulk data elsewhere.");
        }
        if (!is_writable($root)) storage_fail("STORAGE_ROOT ($root) is not writable.");
    }
}

function storage_ensure_dir(string $dir, int $mode = 0775): void {
    if ($dir === '' || is_dir($dir)) return;
    @mkdir($dir, $mode, true);
}

/** Run an external command without a shell (array argv). */
function storage_run_command(array $argv, int $timeout = 60, array $extraEnv = []): array {
    if ($argv === []) {
        return ['code' => 127, 'stdout' => '', 'stderr' => 'Empty command', 'output' => 'Empty command'];
    }
    $cmd = array_values(array_map(static fn($v) => (string)$v, $argv));
    $env = getenv();
    if (!is_array($env)) $env = [];
    foreach ($extraEnv as $k => $v) $env[(string)$k] = (string)$v;
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($cmd, $descriptors, $pipes, null, $env);
    if (!is_resource($proc)) {
        return ['code' => 127, 'stdout' => '', 'stderr' => 'Unable to start: ' . $cmd[0], 'output' => 'Unable to start: ' . $cmd[0]];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $start = microtime(true);
    $open = [1 => $pipes[1], 2 => $pipes[2]];
    $timedOut = false;
    while ($open !== []) {
        if ($timeout > 0 && (microtime(true) - $start) > $timeout) {
            $timedOut = true;
            @proc_terminate($proc);
            usleep(100000);
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
            if ($key === 1) $stdout .= $chunk; else $stderr .= $chunk;
            if (strlen($stdout) > 4 * 1024 * 1024) $stdout = substr($stdout, -4 * 1024 * 1024);
            if (strlen($stderr) > 1024 * 1024) $stderr = substr($stderr, -1024 * 1024);
        }
    }
    foreach ($open as $stream) @fclose($stream);
    $code = proc_close($proc);
    if ($timedOut && $code === 0) $code = 124;
    return [
        'code' => $code,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'output' => trim($stdout . ($stderr !== '' ? "\n" . $stderr : '')),
        'timed_out' => $timedOut,
    ];
}

function storage_command_exists(string $binary): bool {
    if ($binary === '') return false;
    if (str_contains($binary, '/')) return @is_executable($binary);
    $path = getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
    foreach (explode(':', (string)$path) as $dir) {
        if ($dir !== '' && @is_executable($dir . '/' . $binary)) return true;
    }
    return false;
}

function storage_bytes_from_setting(string $value, int $defaultBytes): int {
    $value = trim($value);
    if ($value === '') return $defaultBytes;
    if (preg_match('/^(\d+)\s*([KMGTP]?)B?$/i', $value, $m)) {
        $n = (int)$m[1];
        $mult = ['K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3, 'T' => 1024 ** 4, 'P' => 1024 ** 5];
        $unit = strtoupper($m[2]);
        return $n * ($unit !== '' ? ($mult[$unit] ?? 1) : 1);
    }
    return $defaultBytes;
}

function storage_default_settings(): array {
    return [
        'reserve_percent' => 12,
        'min_free_gb' => 1,
        'watch_interval_seconds' => 30,
        'backup_required' => true,
        'backup_profile' => '',
        'delete_source_after_migration' => false,
        'max_copy_parallel' => 1,
        'placement_policy' => 'mfs',
    ];
}

function storage_settings_path(): string {
    return storage_state_dir() . '/settings.json';
}

function storage_settings(bool $reset = false): array {
    static $cache = null;
    if ($reset) $cache = null;
    if (is_array($cache)) return $cache;
    $settings = storage_default_settings();
    $path = storage_settings_path();
    if (is_readable($path)) {
        $data = json_decode((string)@file_get_contents($path), true);
        if (is_array($data)) $settings = array_replace($settings, $data);
    }
    $settings['reserve_percent'] = max(0, min(50, (int)$settings['reserve_percent']));
    $settings['min_free_gb'] = max(0, min(100000, (int)$settings['min_free_gb']));
    $settings['watch_interval_seconds'] = max(5, min(3600, (int)$settings['watch_interval_seconds']));
    $settings['backup_required'] = !empty($settings['backup_required']);
    $settings['delete_source_after_migration'] = !empty($settings['delete_source_after_migration']);
    $settings['max_copy_parallel'] = max(1, min(8, (int)$settings['max_copy_parallel']));
    $settings['placement_policy'] = in_array((string)$settings['placement_policy'], ['mfs', 'lfs', 'epmfs', 'ff'], true)
        ? (string)$settings['placement_policy'] : 'mfs';
    $settings['backup_profile'] = preg_replace('/[^a-f0-9]/i', '', (string)$settings['backup_profile']) ?? '';
    $cache = $settings;
    return $cache;
}

function storage_save_settings(array $input): array {
    $current = storage_settings(true);
    $next = $current;
    foreach (['reserve_percent', 'min_free_gb', 'watch_interval_seconds', 'max_copy_parallel'] as $key) {
        if (array_key_exists($key, $input)) $next[$key] = (int)$input[$key];
    }
    foreach (['backup_required', 'delete_source_after_migration'] as $key) {
        if (array_key_exists($key, $input)) $next[$key] = !empty($input[$key]);
    }
    if (array_key_exists('backup_profile', $input)) {
        $next['backup_profile'] = preg_replace('/[^a-f0-9]/i', '', (string)$input['backup_profile']) ?? '';
    }
    if (array_key_exists('placement_policy', $input)) {
        $policy = (string)$input['placement_policy'];
        $next['placement_policy'] = in_array($policy, ['mfs', 'lfs', 'epmfs', 'ff'], true) ? $policy : 'mfs';
    }
    $next = array_intersect_key($next, storage_default_settings());
    storage_ensure_dir(storage_state_dir());
    $tmp = storage_settings_path() . '.tmp.' . getmypid();
    @file_put_contents($tmp, json_encode($next, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    @rename($tmp, storage_settings_path());
    @chmod(storage_settings_path(), 0660);
    return storage_settings(true);
}

/** True when the merged pool is currently mounted and usable. */
function storage_pool_available(): bool {
    return MergerFSManager::isMounted(storage_root());
}

/**
 * Block writes from application modules while a destructive storage operation
 * (disk migration) is running. Called by storage_helper.php on write paths.
 */
function storage_writes_blocked(): bool {
    return StorageManager::writesBlocked();
}

function storage_event(string $type, string $message, ?int $diskId = null, array $metadata = [], ?string $migrationId = null): void {
    try {
        StorageDb::addEvent($type, $message, $diskId, $metadata, $migrationId);
    } catch (Throwable $e) {
        // Event logging must never break a storage operation.
    }
}
