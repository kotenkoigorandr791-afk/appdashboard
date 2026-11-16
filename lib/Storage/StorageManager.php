<?php
/**
 * Facade for the dynamic storage subsystem.
 */
declare(strict_types=1);

final class StorageManager
{
    public static function isEnabled(): bool
    {
        return storage_env('STORAGE_ENABLED', '1') !== '0';
    }

    /** Detect disks and update the registry. Safe to call from web or CLI. */
    public static function detectAndSync(): array
    {
        if (!self::isEnabled()) {
            return ['registered' => 0, 'updated' => 0, 'offline' => 0, 'rejected' => 0, 'conflicts' => 0];
        }
        StorageDb::ensureSchema();
        $detector = new DiskDetector();
        $detected = $detector->detectAll();
        return DiskRegistry::sync($detected);
    }

    /** Ask the root watcher to reconcile the pool on its next tick. */
    public static function requestReconcile(): void
    {
        storage_ensure_dir(storage_state_dir());
        @file_put_contents(storage_state_dir() . '/reconcile.request', (string)json_encode(['requested_at' => time(), 'pid' => getmypid()]), LOCK_EX);
        @chmod(storage_state_dir() . '/reconcile.request', 0664);
    }

    public static function reconcileRequested(): bool
    {
        return is_file(storage_state_dir() . '/reconcile.request');
    }

    public static function clearReconcileRequest(): void
    {
        @unlink(storage_state_dir() . '/reconcile.request');
    }

    /** Create the standard shared directories once the pool is mounted. */
    public static function ensurePoolLayout(): void
    {
        if (!MergerFSManager::isMounted(storage_root())) return;
        $wwwUid = -1;
        if (function_exists('posix_geteuid') && posix_geteuid() === 0 && function_exists('posix_getpwnam')) {
            $www = @posix_getpwnam('www-data');
            if (is_array($www) && isset($www['uid'])) $wwwUid = (int)$www['uid'];
        }
        foreach (['files', 'documents', 'uploads', 'shared'] as $dir) {
            $path = storage_root() . '/' . $dir;
            if (!is_dir($path)) @mkdir($path, 0775, true);
            // The watcher runs as root; hand the shared top-level directories
            // to the application user so PHP can create files inside them.
            if ($wwwUid >= 0) {
                @chown($path, $wwwUid);
                @chgrp($path, $wwwUid);
                @chmod($path, 0775);
            }
        }
    }

    /* ------------------------------------------------------------------
     * Maintenance write lock
     * ---------------------------------------------------------------- */

    private static function lockPath(): string
    {
        return storage_state_dir() . '/.write-lock';
    }

    public static function beginMaintenance(string $migrationId): void
    {
        storage_ensure_dir(storage_state_dir());
        $data = [
            'migration_id' => $migrationId,
            'pid' => getmypid(),
            'started_at' => time(),
            'heartbeat' => time(),
        ];
        $tmp = self::lockPath() . '.tmp.' . getmypid();
        @file_put_contents($tmp, (string)json_encode($data), LOCK_EX);
        @chmod($tmp, 0660);
        @rename($tmp, self::lockPath());
    }

    public static function heartbeat(string $migrationId): void
    {
        $path = self::lockPath();
        $data = null;
        if (is_readable($path)) {
            $decoded = json_decode((string)@file_get_contents($path), true);
            if (is_array($decoded) && (string)($decoded['migration_id'] ?? '') === $migrationId) $data = $decoded;
        }
        if ($data === null) {
            self::beginMaintenance($migrationId);
            return;
        }
        $data['heartbeat'] = time();
        $data['pid'] = getmypid();
        @file_put_contents($path, (string)json_encode($data), LOCK_EX);
    }

    public static function endMaintenance(?string $migrationId = null): void
    {
        $path = self::lockPath();
        if (!is_file($path)) return;
        if ($migrationId !== null) {
            $decoded = json_decode((string)@file_get_contents($path), true);
            if (is_array($decoded) && (string)($decoded['migration_id'] ?? '') !== $migrationId) return;
        }
        @unlink($path);
    }

    /** @return array<string,mixed>|null */
    public static function maintenanceLock(): ?array
    {
        $path = self::lockPath();
        if (!is_readable($path)) return null;
        $data = json_decode((string)@file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    public static function writesBlocked(): bool
    {
        $lock = self::maintenanceLock();
        if ($lock === null) return false;
        $heartbeat = (int)($lock['heartbeat'] ?? 0);
        $pid = (int)($lock['pid'] ?? 0);
        $alive = false;
        if ($pid > 0 && function_exists('posix_kill')) {
            $alive = @posix_kill($pid, 0);
        }
        // A dead worker must not lock the application forever.
        if ($heartbeat > 0 && (time() - $heartbeat) > 600 && !$alive) {
            @unlink(self::lockPath());
            return false;
        }
        return true;
    }

    public static function assertWritesAllowed(): void
    {
        if (self::writesBlocked()) {
            throw new RuntimeException('storage_maintenance_in_progress');
        }
    }

    /* ------------------------------------------------------------------
     * Background worker
     * ---------------------------------------------------------------- */

    /** @return array{ok:bool,error:string} */
    public static function spawnWorker(string $migrationId, array $extraArgs = []): array
    {
        $worker = dirname(__DIR__, 2) . '/project/storage/worker.php';
        if (!is_file($worker)) return ['ok' => false, 'error' => 'worker_missing'];
        $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $argv = [$php, $worker, '--migration=' . $migrationId];
        foreach ($extraArgs as $arg) $argv[] = (string)$arg;
        $uid = function_exists('posix_geteuid') ? (int)posix_geteuid() : -1;
        if ($uid === 0 && storage_command_exists('runuser')) {
            array_unshift($argv, 'runuser', '-u', 'www-data', '--');
        }
        storage_ensure_dir(storage_state_dir());
        $log = storage_state_dir() . '/worker-' . preg_replace('/[^a-f0-9]/i', '', $migrationId) . '.log';
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $log, 'a'],
            2 => ['file', $log, 'a'],
        ];
        $proc = @proc_open($argv, $descriptors, $pipes);
        if (!is_resource($proc)) return ['ok' => false, 'error' => 'proc_open_failed'];
        $status = proc_get_status($proc);
        if (empty($status['running'])) {
            $code = (int)($status['exitcode'] ?? -1);
            proc_close($proc);
            return ['ok' => false, 'error' => 'worker_exited_immediately:' . $code];
        }
        // Intentionally do not proc_close(): the worker is long-running and is
        // reparented when this request ends.
        return ['ok' => true, 'error' => ''];
    }

    /** @return array<string,mixed> */
    public static function bootstrapStatus(): array
    {
        $detected = [];
        if (is_dir(storage_disks_root())) {
            $detector = new DiskDetector();
            foreach ($detector->detectAll() as $candidate) {
                $detected[] = [
                    'name' => (string)$candidate['name'],
                    'mount_path' => (string)$candidate['mount_path'],
                    'mounted' => (bool)$candidate['mounted'],
                    'uuid' => (string)$candidate['uuid'],
                    'filesystem' => (string)$candidate['filesystem'],
                    'compatible' => (bool)$candidate['compatible'],
                    'writable' => (bool)$candidate['writable'],
                    'errors' => $candidate['errors'] ?? [],
                ];
            }
        }
        return [
            'enabled' => self::isEnabled(),
            'storage_root' => storage_root(),
            'disks_root' => storage_disks_root(),
            'disks_root_exists' => is_dir(storage_disks_root()),
            'detected' => $detected,
            'mergerfs' => MergerFSManager::status(),
            'write_blocked' => self::writesBlocked(),
        ];
    }
}
