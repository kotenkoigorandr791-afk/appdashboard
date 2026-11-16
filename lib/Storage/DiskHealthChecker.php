<?php
/**
 * Non-destructive disk diagnostics. Never formats, never deletes data.
 */
declare(strict_types=1);

final class DiskHealthChecker
{
    /**
     * @param array<string,mixed> $disk
     * @return array{ok:bool,status:string,report:array<string,mixed>}
     */
    public static function check(array $disk): array
    {
        $path = (string)($disk['mount_path'] ?? '');
        $expectedUuid = (string)($disk['uuid'] ?? '');
        $detector = new DiskDetector();
        $report = $detector->inspect($path);
        $ok = true;
        $status = 'online';
        $errors = (array)($report['errors'] ?? []);

        if (empty($report['mounted'])) {
            $ok = false;
            $status = 'offline';
            $errors[] = 'mount_missing';
        } elseif (!empty($report['uuid']) && $expectedUuid !== '' && (string)$report['uuid'] !== $expectedUuid) {
            $ok = false;
            $status = 'error';
            $errors[] = 'identity_mismatch';
            storage_event('disk_identity_mismatch', 'Disk at ' . $path . ' reports ' . $report['uuid'] . ' instead of ' . $expectedUuid, (int)$disk['id'], [
                'expected' => $expectedUuid,
                'actual' => (string)$report['uuid'],
            ]);
        } elseif (!empty($errors)) {
            $ok = false;
            $status = 'error';
        }

        if ($status === 'online' && in_array((string)($disk['status'] ?? ''), ['ready_to_remove', 'removing'], true)) {
            $status = (string)$disk['status'];
        }

        $fields = [
            'status' => $status,
            'last_health_check_at' => time(),
            'metadata' => array_replace((array)($disk['metadata'] ?? []), [
                'last_health' => [
                    'ok' => $ok,
                    'checked_at' => time(),
                    'errors' => $errors,
                    'warnings' => $report['warnings'] ?? [],
                    'filesystem' => (string)($report['filesystem'] ?? ''),
                    'source' => (string)($report['source'] ?? ''),
                ],
            ]),
        ];
        if (!empty($report['mounted']) && empty($report['errors']) && (string)($report['uuid'] ?? '') === $expectedUuid) {
            $fields['total_bytes'] = (int)($report['total_bytes'] ?? 0);
            $fields['used_bytes'] = (int)($report['used_bytes'] ?? 0);
            $fields['free_bytes'] = (int)($report['free_bytes'] ?? 0);
            $fields['filesystem'] = (string)($report['filesystem'] ?? '');
            if ($status === 'online') $fields['last_seen_at'] = time();
        }
        StorageDb::updateDisk((int)$disk['id'], $fields);

        return [
            'ok' => $ok,
            'status' => $status,
            'report' => [
                'errors' => array_values(array_unique($errors)),
                'warnings' => $report['warnings'] ?? [],
                'mounted' => (bool)($report['mounted'] ?? false),
                'writable' => (bool)($report['writable'] ?? false),
                'readable' => (bool)($report['readable'] ?? false),
                'filesystem' => (string)($report['filesystem'] ?? ''),
                'uuid' => (string)($report['uuid'] ?? ''),
                'identity_source' => (string)($report['identity_source'] ?? ''),
                'total_bytes' => (int)($report['total_bytes'] ?? 0),
                'used_bytes' => (int)($report['used_bytes'] ?? 0),
                'free_bytes' => (int)($report['free_bytes'] ?? 0),
                'checked_at' => time(),
            ],
        ];
    }
}
