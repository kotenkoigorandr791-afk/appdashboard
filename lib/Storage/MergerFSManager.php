<?php
/**
 * MergerFS pool management.
 *
 * Mount/unmount must run as root. The web process (www-data) only requests a
 * reconcile; the root watcher performs it. All external commands use an argv
 * array through proc_open — no shell string concatenation.
 */
declare(strict_types=1);

final class MergerFSManager
{
    private const RUNTIME_XATTR = 'user.mergerfs.branches';

    public static function isMounted(?string $root = null): bool
    {
        $root = rtrim($root ?? storage_root(), '/') ?: '/';
        foreach (DiskDetector::parseMountInfoFile(storage_mountinfo_path()) as $mount) {
            $mp = rtrim((string)$mount['mount_point'], '/') ?: '/';
            if ($mp !== $root) continue;
            $fstype = strtolower((string)$mount['fstype']);
            $source = strtolower((string)$mount['source']);
            if (str_contains($fstype, 'mergerfs') || $source === 'mergerfs') return true;
        }
        return false;
    }

    /** @return list<string> mount paths of online branches, ordered by name */
    public static function desiredBranches(): array
    {
        StorageDb::ensureSchema();
        $mounts = DiskDetector::parseMountInfoFile(storage_mountinfo_path());
        $branches = [];
        foreach (StorageDb::disks() as $disk) {
            if (!in_array((string)$disk['status'], ['online', 'initializing'], true)) continue;
            $path = rtrim((string)$disk['mount_path'], '/');
            if ($path === '' || str_contains($path, ':') || str_contains($path, "\n")) continue;
            // Never pool a path whose physical mount vanished.
            if (DiskDetector::findMount($path, $mounts) === null) continue;
            $branches[] = $path;
        }
        $branches = array_values(array_unique($branches));
        sort($branches, SORT_NATURAL);
        return $branches;
    }

    /** @return array<string,mixed> */
    public static function status(): array
    {
        $root = storage_root();
        $mounted = self::isMounted($root);
        $runtime = $mounted ? self::runtimeBranches($root) : null;
        $probe = null;
        if ($mounted && is_dir($root)) {
            $probe = $root . '/.appdashboard-write-test-' . getmypid();
            $ok = @file_put_contents($probe, 'ok');
            if ($ok !== false) {
                @unlink($probe);
                $probe = true;
            } else {
                $probe = false;
            }
        }
        return [
            'mounted' => $mounted,
            'writable' => $probe === true,
            'root' => $root,
            'branches' => $runtime ?? [],
            'runtime_api' => $runtime !== null,
            'binary' => storage_mergerfs_binary(),
            'binary_available' => storage_command_exists(storage_mergerfs_binary()),
            'fuse_device' => is_readable('/dev/fuse'),
            'desired_branches' => self::desiredBranches(),
            'root_uid' => function_exists('posix_geteuid') ? (int)posix_geteuid() : -1,
            'checked_at' => time(),
        ];
    }

    /**
     * Bring the live pool in line with the registry. Root only.
     *
     * @return array{ok:bool,action:string,error?:string,branches?:list<string>}
     */
    public static function reconcile(): array
    {
        $uid = function_exists('posix_geteuid') ? (int)posix_geteuid() : -1;
        if ($uid !== 0) {
            return ['ok' => false, 'action' => 'none', 'error' => 'root_required'];
        }
        $root = storage_root();
        $lock = self::lock();
        if ($lock === null) {
            return ['ok' => false, 'action' => 'none', 'error' => 'reconcile_locked'];
        }
        try {
            return self::reconcileLocked($root);
        } finally {
            self::unlock($lock);
            self::writeState();
        }
    }

    /** @return array{ok:bool,action:string,error?:string,branches?:list<string>} */
    private static function reconcileLocked(string $root): array
    {
        $desired = self::desiredBranches();
        $mounted = self::isMounted($root);

        if ($desired === []) {
            if ($mounted) {
                $result = self::unmount($root);
                if (!$result['ok']) return ['ok' => false, 'action' => 'unmount', 'error' => $result['error']];
                storage_event('mergerfs_unmounted', 'No online disks; merged storage unmounted');
                return ['ok' => true, 'action' => 'unmounted', 'branches' => []];
            }
            return ['ok' => true, 'action' => 'none', 'branches' => []];
        }

        if (!$mounted) {
            // Mounting over a non-empty directory would hide existing files.
            if (is_dir($root)) {
                $entries = @scandir($root) ?: [];
                $extra = array_values(array_diff($entries, ['.', '..', 'lost+found']));
                if ($extra !== []) {
                    storage_event('mergerfs_mount_refused', 'Storage root ' . $root . ' is not empty and is not a mergerfs mount');
                    return ['ok' => false, 'action' => 'mount', 'error' => 'storage_root_not_empty'];
                }
            } else {
                @mkdir($root, 0775, true);
            }
            $result = self::mount($root, $desired);
            if (!$result['ok']) {
                storage_event('mergerfs_mount_failed', 'MergerFS mount failed: ' . ($result['error'] ?? ''));
                return ['ok' => false, 'action' => 'mount', 'error' => $result['error'] ?? 'mount_failed'];
            }
            storage_event('mergerfs_mounted', 'MergerFS mounted with ' . count($desired) . ' branch(es)', null, ['branches' => $desired]);
            return ['ok' => true, 'action' => 'mounted', 'branches' => $desired];
        }

        $current = self::runtimeBranches($root);
        $normalizedCurrent = $current === null ? [] : array_map(static fn($b) => rtrim($b, '/'), $current);
        sort($normalizedCurrent, SORT_NATURAL);
        $normalizedDesired = array_map(static fn($b) => rtrim($b, '/'), $desired);
        sort($normalizedDesired, SORT_NATURAL);
        if ($normalizedCurrent === $normalizedDesired && $current !== null) {
            return ['ok' => true, 'action' => 'none', 'branches' => $desired];
        }

        if ($current !== null) {
            $applied = self::applyRuntimeBranches($root, $normalizedCurrent, $normalizedDesired);
            if ($applied) {
                $after = self::runtimeBranches($root);
                if ($after !== null) {
                    $normalizedAfter = array_map(static fn($b) => rtrim($b, '/'), $after);
                    sort($normalizedAfter, SORT_NATURAL);
                    if ($normalizedAfter === $normalizedDesired) {
                        storage_event('mergerfs_branches_updated', 'MergerFS branches updated at runtime', null, ['branches' => $normalizedDesired]);
                        return ['ok' => true, 'action' => 'runtime_update', 'branches' => $normalizedDesired];
                    }
                }
            }
        }

        // Runtime API unavailable (older mergerfs) or it refused: remount.
        $unmount = self::unmount($root);
        if (!$unmount['ok']) {
            return ['ok' => false, 'action' => 'remount', 'error' => $unmount['error'] ?? 'unmount_failed'];
        }
        $mount = self::mount($root, $desired);
        if (!$mount['ok']) {
            // Try to restore the previous pool so the app keeps working.
            if ($normalizedCurrent !== []) self::mount($root, $normalizedCurrent);
            storage_event('mergerfs_remount_failed', 'MergerFS remount failed: ' . ($mount['error'] ?? ''));
            return ['ok' => false, 'action' => 'remount', 'error' => $mount['error'] ?? 'mount_failed'];
        }
        storage_event('mergerfs_remounted', 'MergerFS remounted with ' . count($desired) . ' branch(es)', null, ['branches' => $desired]);
        return ['ok' => true, 'action' => 'remounted', 'branches' => $desired];
    }

    /** @param list<string> $branches */
    public static function mount(string $root, array $branches): array
    {
        if ($branches === []) return ['ok' => false, 'error' => 'no_branches'];
        if (!storage_command_exists(storage_mergerfs_binary())) {
            return ['ok' => false, 'error' => 'mergerfs_not_installed'];
        }
        if (!file_exists('/dev/fuse') && storage_env('STORAGE_ALLOW_NO_FUSE', '') !== '1') {
            return ['ok' => false, 'error' => 'fuse_device_missing'];
        }
        if (!is_dir($root)) @mkdir($root, 0775, true);
        $settings = storage_settings();
        $minFree = max(1, (int)$settings['min_free_gb']);
        $options = implode(',', [
            'category.create=mfs',
            'minfreespace=' . $minFree . 'G',
            'moveonenospc=true',
            'dropcacheonclose=true',
            'cache.files=partial',
            'allow_other',
            'xattr=passthrough',
            'fsname=appdashboard-storage',
        ]);
        $branchArg = implode(':', $branches);
        $result = storage_run_command([storage_mergerfs_binary(), '-o', $options, $branchArg, $root], 60);
        if ((int)$result['code'] !== 0 || !self::isMounted($root)) {
            return ['ok' => false, 'error' => 'mount_failed: ' . trim((string)$result['output'])];
        }
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function unmount(string $root): array
    {
        if (!self::isMounted($root)) return ['ok' => true];
        $binary = storage_fusermount_binary();
        if (!storage_command_exists($binary)) return ['ok' => false, 'error' => 'fusermount_not_installed'];
        $result = storage_run_command([$binary, '-u', $root], 60);
        if ((int)$result['code'] !== 0 || self::isMounted($root)) {
            return ['ok' => false, 'error' => trim((string)$result['output']) ?: 'unmount_failed'];
        }
        return ['ok' => true];
    }

    /** @return list<string>|null null when the runtime interface is unavailable */
    public static function runtimeBranches(string $root): ?array
    {
        if (!storage_command_exists(storage_getfattr_binary())) return null;
        $pseudo = rtrim($root, '/') . '/.mergerfs';
        $r = storage_run_command([storage_getfattr_binary(), '-n', self::RUNTIME_XATTR, '--only-values', $pseudo], 15);
        if ((int)$r['code'] !== 0) return null;
        $value = trim((string)$r['stdout'], " \t\r\n\"");
        if ($value === '') return [];
        $out = [];
        foreach (explode(':', $value) as $branch) {
            $branch = trim($branch);
            if ($branch === '') continue;
            $eq = strpos($branch, '=');
            if ($eq !== false) $branch = substr($branch, 0, $eq);
            if ($branch !== '') $out[] = $branch;
        }
        return array_values(array_unique($out));
    }

    /** @param list<string> $current @param list<string> $desired */
    private static function applyRuntimeBranches(string $root, array $current, array $desired): bool
    {
        if (!storage_command_exists(storage_setfattr_binary())) return false;
        $pseudo = rtrim($root, '/') . '/.mergerfs';
        foreach (array_diff($desired, $current) as $branch) {
            $r = storage_run_command([storage_setfattr_binary(), '-n', self::RUNTIME_XATTR, '-v', '+<' . $branch, $pseudo], 30);
            if ((int)$r['code'] !== 0) return false;
        }
        foreach (array_diff($current, $desired) as $branch) {
            $r = storage_run_command([storage_setfattr_binary(), '-n', self::RUNTIME_XATTR, '-v', '-<' . $branch, $pseudo], 30);
            if ((int)$r['code'] !== 0) return false;
        }
        return true;
    }

    /** @return resource|null */
    private static function lock()
    {
        storage_ensure_dir(storage_state_dir());
        $fp = @fopen(storage_state_dir() . '/mergerfs.lock', 'c');
        if ($fp === false) return null;
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return null;
        }
        return $fp;
    }

    /** @param resource $fp */
    private static function unlock($fp): void
    {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }

    public static function writeState(): void
    {
        $state = self::status();
        $state['updated_at'] = time();
        $tmp = storage_state_dir() . '/mergerfs.json.tmp.' . getmypid();
        @file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, storage_state_dir() . '/mergerfs.json');
        @chmod(storage_state_dir() . '/mergerfs.json', 0644);
    }
}
