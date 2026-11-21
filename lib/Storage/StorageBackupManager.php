<?php
/**
 * Bridges the storage pool into the existing Restic BackupManager.
 *
 * The pool is snapshotted through its stable /storage path, so files that move
 * between physical disks do not change the repository layout or history.
 */
declare(strict_types=1);

final class StorageBackupManager
{
    private static function restic(): bool
    {
        if (function_exists('resticLoadConfig')) return true;
        $root = dirname(__DIR__, 2);
        if (!is_file($root . '/shell/backup/i18n.php') || !is_file($root . '/shell/backup/api.php')) return false;
        require_once $root . '/shell/backup/i18n.php';
        if (!isset($GLOBALS['backupI18n'])) backup_boot_i18n('en');
        require_once $root . '/shell/backup/api.php';
        return function_exists('resticLoadConfig');
    }

    /** @return list<array<string,mixed>> */
    public static function profiles(): array
    {
        if (!self::restic()) return [];
        try {
            if (class_exists('AppDataBackup')) {
                try { AppDataBackup::syncEnvProfiles(); AppDataBackup::ensureProfile(); } catch (Throwable $e) {}
            }
            $cfg = resticLoadConfig();
            return array_values((array)($cfg['backups'] ?? []));
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return array<string,mixed>|null */
    public static function selectedProfile(): ?array
    {
        if (!self::restic()) return null;
        try {
            $cfg = resticLoadConfig();
            $id = (string)(storage_settings()['backup_profile'] ?? '');
            if ($id !== '') {
                $profile = resticFindProfile($cfg, $id);
                return is_array($profile) ? $profile : null;
            }
            foreach ((array)($cfg['backups'] ?? []) as $profile) {
                if (!is_array($profile)) continue;
                if ((string)($profile['repository'] ?? '') !== '' && (string)($profile['password'] ?? '') !== '') return $profile;
            }
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    public static function status(): array
    {
        $profile = self::selectedProfile();
        $lastBackup = 0;
        try {
            foreach (StorageDb::disks() as $disk) {
                $lastBackup = max($lastBackup, (int)($disk['last_backup_at'] ?? 0));
            }
            foreach (StorageDb::events(50) as $event) {
                if ((string)($event['event_type'] ?? '') === 'backup_completed') {
                    $lastBackup = max($lastBackup, (int)($event['created_at'] ?? 0));
                    break;
                }
            }
        } catch (Throwable $e) {
            // No schema yet.
        }
        $configured = $profile !== null
            && (string)($profile['repository'] ?? '') !== ''
            && (string)($profile['password'] ?? '') !== '';
        return [
            'restic_available' => function_exists('resticBinary') ? is_executable(resticBinary()) : storage_command_exists('restic'),
            'configured' => $configured,
            'profile_id' => (string)($profile['id'] ?? ''),
            'profile_name' => (string)($profile['name'] ?? ''),
            'last_backup_at' => $lastBackup,
            'sources_available' => self::sourcesAvailable(),
        ];
    }

    /** @return array{ok:bool,offline:list<string>} */
    public static function sourcesAvailable(): array
    {
        $offline = [];
        try {
            foreach (StorageDb::disks() as $disk) {
                if (in_array((string)$disk['status'], ['offline', 'error', 'migrating', 'removing', 'ready_to_remove'], true)) {
                    $offline[] = (string)$disk['name'];
                }
            }
        } catch (Throwable $e) {
            return ['ok' => true, 'offline' => []];
        }
        return ['ok' => $offline === [], 'offline' => $offline];
    }

    /**
     * Queue a storage snapshot through the existing restic job worker.
     *
     * @return array{ok:bool,job:array<string,mixed>,error:string}
     */
    public static function spawnBackup(string $trigger = 'storage'): array
    {
        if (!self::restic()) return ['ok' => false, 'job' => [], 'error' => 'restic_not_available'];
        $profile = self::selectedProfile();
        if ($profile === null) return ['ok' => false, 'job' => [], 'error' => 'backup_profile_not_configured'];
        $repo = resticRepoConfig(resticLoadConfig(), (string)$profile['id']);
        if ((string)($repo['repository'] ?? '') === '' || (string)($repo['password'] ?? '') === '') {
            return ['ok' => false, 'job' => [], 'error' => 'backup_repo_not_configured'];
        }
        $availability = self::sourcesAvailable();
        if (!$availability['ok']) {
            return ['ok' => false, 'job' => [], 'error' => 'disks_offline: ' . implode(', ', $availability['offline'])];
        }
        if (!MergerFSManager::isMounted(storage_root())) {
            return ['ok' => false, 'job' => [], 'error' => 'storage_pool_not_mounted'];
        }
        try {
            $job = resticJobSpawn('backup', [
                'profile' => (string)$profile['id'],
                'profile_name' => (string)$profile['name'],
                'sources' => [storage_root()],
                'trigger' => $trigger,
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'job' => [], 'error' => $e->getMessage()];
        }
        return ['ok' => true, 'job' => $job, 'error' => ''];
    }

    /**
     * Run a storage snapshot through the existing restic job worker and wait.
     *
     * @return array{ok:bool,job_id:string,error:string}
     */
    public static function runBackup(string $trigger = 'storage', int $timeout = 3600): array
    {
        $spawn = self::spawnBackup($trigger);
        if (!$spawn['ok']) return ['ok' => false, 'job_id' => '', 'error' => $spawn['error']];
        $job = $spawn['job'];
        $profile = self::selectedProfile();
        $jobId = (string)($job['id'] ?? '');
        $deadline = time() + max(60, $timeout);
        while (time() < $deadline) {
            $current = resticJobRead($jobId);
            $status = (string)($current['status'] ?? '');
            if (in_array($status, ['success', 'error', 'cancelled'], true)) {
                if ($status === 'success') {
                    try {
                        StorageDb::touchBackupTime();
                        StorageDb::addEvent('backup_completed', 'Storage backup completed (' . $trigger . ')', null, ['job_id' => $jobId, 'profile' => (string)$profile['id']]);
                    } catch (Throwable $e) {}
                    return ['ok' => true, 'job_id' => $jobId, 'error' => ''];
                }
                $error = (string)($current['error'] ?? $status);
                try {
                    StorageDb::addEvent('backup_failed', 'Storage backup failed: ' . $error, null, ['job_id' => $jobId, 'profile' => (string)$profile['id']]);
                } catch (Throwable $e) {}
                return ['ok' => false, 'job_id' => $jobId, 'error' => $error];
            }
            sleep(2);
        }
        return ['ok' => false, 'job_id' => $jobId, 'error' => 'backup_timeout'];
    }

    /**
     * Add the merged storage path to an existing backup profile's sources.
     *
     * @return array<string,mixed>
     */
    public static function attachStorageToProfile(string $profileId): array
    {
        if (!self::restic()) throw new RuntimeException('restic_not_available');
        $cfg = resticLoadConfig();
        $profile = resticFindProfile($cfg, $profileId);
        if ($profile === null) throw new InvalidArgumentException('backup_not_found');
        $sources = array_values((array)($profile['sources'] ?? []));
        $root = storage_root();
        if (!in_array($root, $sources, true)) $sources[] = $root;
        $updated = resticUpdateProfile($profileId, ['sources' => $sources]);
        $settings = storage_settings(true);
        $settings['backup_profile'] = $profileId;
        storage_save_settings($settings);
        return $updated;
    }

    /** Guard used by the existing scheduler to avoid snapshots of missing disks. */
    public static function schedulerSourcesAvailable(): bool
    {
        if (storage_env('STORAGE_ENABLED', '1') === '0') return true;
        try {
            $disks = StorageDb::disks();
        } catch (Throwable $e) {
            return true;
        }
        if ($disks === []) return true;
        foreach ($disks as $disk) {
            if (in_array((string)$disk['status'], ['offline', 'error', 'migrating', 'removing'], true)) return false;
        }
        return true;
    }
}

function storage_scheduler_sources_available(): bool
{
    return StorageBackupManager::schedulerSourcesAvailable();
}
