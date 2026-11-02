<?php
/**
 * Recovers interrupted migrations after a container restart.
 */
declare(strict_types=1);

final class MigrationRecovery
{
    /** @return list<array<string,mixed>> */
    public static function resumable(): array
    {
        StorageDb::ensureSchema();
        $out = [];
        foreach (StorageDb::migrations(50) as $migration) {
            if (in_array((string)$migration['status'], ['pending', 'checking', 'preparing', 'backup_running', 'migrating', 'verifying', 'removing_branch', 'paused'], true)) {
                $out[] = $migration;
            }
        }
        return $out;
    }

    /**
     * Resume the single most recent interrupted migration when its disks are
     * still present. Returns a human-readable status per migration.
     *
     * @return list<array<string,mixed>>
     */
    public static function resumeAll(): array
    {
        $results = [];
        $active = StorageDb::activeMigration();
        if ($active === null) return $results;
        // Paused (conflict/cancel) operations need an explicit operator resume;
        // only crash-interrupted states are resumed automatically.
        if ((string)$active['status'] === 'paused') return $results;
        $lockPath = storage_state_dir() . '/migration-' . (string)$active['id'] . '.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock !== false) {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                fclose($lock);
                return $results; // a worker is already running this migration
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $disk = StorageDb::diskById((int)$active['source_disk_id']);
        if ($disk === null) {
            StorageDb::updateMigration((string)$active['id'], ['status' => 'paused', 'error' => 'source_disk_missing']);
            return [['id' => (string)$active['id'], 'resumed' => false, 'error' => 'source_disk_missing']];
        }
        $source = rtrim((string)$disk['mount_path'], '/');
        if (DiskDetector::findMount($source, DiskDetector::parseMountInfoFile(storage_mountinfo_path())) === null) {
            // Keep the operation paused; never delete anything while the source
            // identity is unknown.
            StorageDb::updateMigration((string)$active['id'], ['status' => 'paused', 'error' => 'source_disk_not_mounted']);
            storage_event('migration_recovery_waiting', 'Migration paused: source disk is not mounted', (int)$disk['id'], [], (string)$active['id']);
            return [['id' => (string)$active['id'], 'resumed' => false, 'error' => 'source_disk_not_mounted']];
        }
        $spawn = StorageManager::spawnWorker((string)$active['id']);
        storage_event('migration_recovered', 'Migration worker resumed after restart', (int)$disk['id'], ['spawn' => $spawn], (string)$active['id']);
        $results[] = ['id' => (string)$active['id'], 'resumed' => $spawn['ok'], 'error' => $spawn['error']];
        return $results;
    }

    public static function notify(): void
    {
        StorageManager::requestReconcile();
    }
}
