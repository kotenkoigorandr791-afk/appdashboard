<?php
/**
 * Read-only aggregation for the Storage Manager UI.
 */
declare(strict_types=1);

final class StorageMonitor
{
    /** @return list<array<string,mixed>> */
    public static function disks(): array
    {
        StorageDb::ensureSchema();
        $out = [];
        foreach (StorageDb::disks() as $disk) {
            $path = (string)$disk['mount_path'];
            if (in_array((string)$disk['status'], ['online', 'error'], true) && is_dir($path)) {
                $total = @disk_total_space($path);
                $free = @disk_free_space($path);
                if ($total !== false && $free !== false) {
                    $disk['total_bytes'] = (int)$total;
                    $disk['free_bytes'] = max(0, (int)$free);
                    $disk['used_bytes'] = max(0, (int)$total - (int)$free);
                }
            }
            $disk['percent_used'] = (int)$disk['total_bytes'] > 0
                ? round((int)$disk['used_bytes'] / (int)$disk['total_bytes'] * 100, 1)
                : 0.0;
            $disk['online'] = (string)$disk['status'] === 'online';
            $out[] = $disk;
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public static function summary(): array
    {
        $disks = self::disks();
        $total = 0;
        $used = 0;
        $free = 0;
        $online = 0;
        foreach ($disks as $disk) {
            if (in_array((string)$disk['status'], ['ready_to_remove', 'removing'], true)) continue;
            $total += (int)$disk['total_bytes'];
            $used += (int)$disk['used_bytes'];
            $free += (int)$disk['free_bytes'];
            if ((string)$disk['status'] === 'online') $online++;
        }
        $active = null;
        try {
            $active = StorageDb::activeMigration();
        } catch (Throwable $e) {}
        return [
            'total_bytes' => $total,
            'used_bytes' => $used,
            'free_bytes' => $free,
            'percent_used' => $total > 0 ? round($used / $total * 100, 1) : 0.0,
            'disk_count' => count($disks),
            'online_count' => $online,
            'mergerfs' => MergerFSManager::status(),
            'restic' => StorageBackupManager::status(),
            'active_migration' => $active,
            'settings' => storage_settings(),
            'storage_root' => storage_root(),
            'disks_root' => storage_disks_root(),
            'generated_at' => time(),
        ];
    }
}
