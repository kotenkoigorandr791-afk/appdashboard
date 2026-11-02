<?php
/**
 * Registers detected disks idempotently and keeps their lifecycle status.
 */
declare(strict_types=1);

final class DiskRegistry
{
    /**
     * @param list<array<string,mixed>> $detected
     * @return array{registered:int,updated:int,offline:int,rejected:int,conflicts:int}
     */
    public static function sync(array $detected): array
    {
        StorageDb::ensureSchema();
        $stats = ['registered' => 0, 'updated' => 0, 'offline' => 0, 'rejected' => 0, 'conflicts' => 0];
        $seenUuids = [];
        $seenInScan = [];

        foreach ($detected as $candidate) {
            $path = (string)($candidate['mount_path'] ?? '');
            if ($path === '') continue;
            $existingByPath = StorageDb::diskByPath($path);

            if (empty($candidate['mounted'])) {
                if ($existingByPath !== null && !in_array((string)$existingByPath['status'], ['offline', 'ready_to_remove'], true)) {
                    StorageDb::updateDisk((int)$existingByPath['id'], ['status' => 'offline']);
                    storage_event('disk_offline', 'Mount point disappeared: ' . $path, (int)$existingByPath['id'], ['path' => $path]);
                    $stats['offline']++;
                }
                continue;
            }

            if (empty($candidate['compatible']) || empty($candidate['writable'])) {
                $stats['rejected']++;
                if ($existingByPath !== null) {
                    if ((string)$existingByPath['status'] !== 'error') {
                        StorageDb::updateDisk((int)$existingByPath['id'], ['status' => 'error']);
                        storage_event('disk_error', 'Disk is not usable: ' . implode(', ', (array)($candidate['errors'] ?? [])), (int)$existingByPath['id'], ['errors' => $candidate['errors'] ?? []]);
                    }
                    $seenUuids[] = (string)$existingByPath['uuid'];
                } else {
                    storage_event('disk_rejected', 'Rejected ' . $path . ': ' . implode(', ', (array)($candidate['errors'] ?? [])));
                }
                continue;
            }

            $uuid = (string)($candidate['uuid'] ?? '');
            if ($uuid === '') {
                $stats['rejected']++;
                continue;
            }
            if (isset($seenInScan[$uuid])) {
                storage_event('disk_duplicate_mount', 'Disk ' . $uuid . ' is mounted more than once; ignoring ' . $path);
                $stats['conflicts']++;
                continue;
            }
            $seenInScan[$uuid] = true;
            $seenUuids[] = $uuid;
            $existing = StorageDb::diskByUuid($uuid);

            $fields = [
                'name' => (string)($candidate['name'] ?? basename($path)),
                'mount_path' => $path,
                'filesystem' => (string)($candidate['filesystem'] ?? ''),
                'total_bytes' => (int)($candidate['total_bytes'] ?? 0),
                'used_bytes' => (int)($candidate['used_bytes'] ?? 0),
                'free_bytes' => (int)($candidate['free_bytes'] ?? 0),
                'identity_source' => (string)($candidate['identity_source'] ?? ''),
                'metadata' => (array)($candidate['metadata'] ?? []),
                'last_seen_at' => time(),
                'last_health_check_at' => time(),
            ];

            if ($existing !== null) {
                // Never overwrite a path that belongs to another identity.
                if ($existingByPath !== null && (string)$existingByPath['uuid'] !== $uuid) {
                    storage_event('disk_identity_conflict', 'Mount ' . $path . ' changed identity from ' . $existingByPath['uuid'] . ' to ' . $uuid);
                    $stats['conflicts']++;
                    continue;
                }
                if (!in_array((string)$existing['status'], ['ready_to_remove', 'removing'], true)) {
                    $fields['status'] = 'online';
                }
                StorageDb::updateDisk((int)$existing['id'], $fields);
                if ((string)$existing['status'] !== (string)($fields['status'] ?? $existing['status'])) {
                    storage_event('disk_online', 'Disk ' . $fields['name'] . ' is online at ' . $path, (int)$existing['id']);
                }
                $stats['updated']++;
                continue;
            }

            if ($existingByPath !== null) {
                storage_event('disk_identity_conflict', 'Refusing to register ' . $uuid . ': ' . $path . ' already belongs to ' . $existingByPath['uuid']);
                $stats['conflicts']++;
                continue;
            }

            $fields['uuid'] = $uuid;
            $fields['status'] = 'online';
            $fields['created_at'] = time();
            $id = StorageDb::insertDisk($fields);
            storage_event('disk_registered', 'Registered disk ' . $fields['name'] . ' (' . $uuid . ') at ' . $path, $id, [
                'filesystem' => $fields['filesystem'],
                'total_bytes' => $fields['total_bytes'],
                'identity_source' => $fields['identity_source'],
            ]);
            $stats['registered']++;
        }

        // Anything registered but not present right now is offline — never an
        // empty disk. ready_to_remove stays ready (the operator may unplug it).
        foreach (StorageDb::disks() as $disk) {
            if (in_array((string)$disk['uuid'], $seenUuids, true)) continue;
            $status = (string)$disk['status'];
            if (in_array($status, ['offline', 'ready_to_remove', 'removing'], true)) continue;
            StorageDb::updateDisk((int)$disk['id'], ['status' => 'offline']);
            storage_event('disk_offline', 'Expected disk is missing: ' . $disk['name'], (int)$disk['id'], ['mount_path' => $disk['mount_path']]);
            $stats['offline']++;
        }

        return $stats;
    }
}
