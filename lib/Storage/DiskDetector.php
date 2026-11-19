<?php
/**
 * Discovers physical disks that are bind-mounted into the container.
 *
 * Identity is never derived from the mount directory name alone: the detector
 * prefers an on-disk marker, then the filesystem UUID, then the mount source.
 * A directory that merely exists inside the container is NOT a disk.
 */
declare(strict_types=1);

final class DiskDetector
{
    private string $disksRoot;
    private string $mountinfoFile;

    public const MARKER_FILE = '.appdashboard-disk-id';
    public const DEFAULT_FILESYSTEMS = 'ext2,ext3,ext4,xfs,btrfs,f2fs,jfs,reiserfs,zfs';

    public function __construct(?string $disksRoot = null, ?string $mountinfoFile = null)
    {
        $this->disksRoot = rtrim($disksRoot ?? storage_disks_root(), '/') ?: '/';
        $this->mountinfoFile = $mountinfoFile ?? storage_mountinfo_path();
    }

    /** @return list<array<string,mixed>> */
    public function detectAll(): array
    {
        if (!is_dir($this->disksRoot)) return [];
        $entries = @scandir($this->disksRoot);
        if (!is_array($entries)) return [];
        $out = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $this->disksRoot . '/' . $entry;
            if (!is_dir($path)) continue;
            $out[] = $this->inspect($path);
        }
        usort($out, static fn($a, $b) => strcmp((string)$a['name'], (string)$b['name']));
        return $out;
    }

    /**
     * Inspect one candidate directory. Always returns a result, even when the
     * directory is not a mount point, so the UI can explain why it was skipped.
     *
     * @return array<string,mixed>
     */
    public function inspect(string $path): array
    {
        $real = realpath($path);
        if ($real === false) $real = $path;
        $real = rtrim($real, '/') ?: '/';
        $mounts = self::parseMountInfoFile($this->mountinfoFile);
        $mount = self::findMount($real, $mounts);

        $result = [
            'name' => basename($real),
            'mount_path' => $real,
            'disks_root' => $this->disksRoot,
            'mounted' => $mount !== null,
            'mount' => $mount,
            'filesystem' => (string)($mount['fstype'] ?? ''),
            'source' => (string)($mount['source'] ?? ''),
            'device' => (string)($mount['major_minor'] ?? ''),
            'total_bytes' => 0,
            'used_bytes' => 0,
            'free_bytes' => 0,
            'readable' => false,
            'writable' => false,
            'uuid' => '',
            'identity_source' => '',
            'compatible' => false,
            'errors' => [],
            'warnings' => [],
            'checked_at' => time(),
        ];

        if ($mount === null) {
            $result['errors'][] = 'not_a_mount_point';
            return $result;
        }
        $storageRoot = storage_root();
        if ($real === '/' || $real === $storageRoot || str_starts_with($real, $storageRoot . '/')) {
            $result['errors'][] = 'protected_mount_point';
            return $result;
        }
        if ($real === rtrim($this->disksRoot, '/')) {
            $result['errors'][] = 'disks_root_is_not_a_disk';
            return $result;
        }
        $fs = strtolower((string)($mount['fstype'] ?? ''));
        if ($fs === 'fuse.mergerfs' || str_contains($fs, 'mergerfs')) {
            $result['errors'][] = 'mergerfs_is_not_a_branch';
            return $result;
        }
        // Docker bind mounts do not always expose the underlying host
        // filesystem: Docker Desktop reports virtiofs as "fakeowner", other
        // runtimes report "none" or "overlay". Since /disks entries are
        // explicit operator-configured mounts, unknown filesystems are allowed
        // after the writable probe (strict mode can still enforce the list).
        $pseudo = ['proc', 'sysfs', 'devtmpfs', 'devpts', 'debugfs', 'tracefs', 'securityfs', 'pstore', 'bpf', 'configfs', 'fusectl', 'mqueue', 'hugetlbfs', 'autofs', 'binfmt_misc', 'rpc_pipefs', 'nsfs'];
        if (in_array($fs, $pseudo, true) || str_starts_with($fs, 'cgroup')) {
            $result['errors'][] = 'unsupported_filesystem:' . $fs;
            return $result;
        }
        $allowed = array_filter(array_map('trim', explode(',', strtolower(storage_env('STORAGE_ALLOWED_FILESYSTEMS', self::DEFAULT_FILESYSTEMS)))));
        if ($allowed !== [] && !in_array($fs, $allowed, true)) {
            if (storage_env('STORAGE_STRICT_FILESYSTEMS', '0') === '1') {
                $result['errors'][] = 'unsupported_filesystem:' . $fs;
                return $result;
            }
            $result['warnings'][] = 'filesystem_not_in_allowlist:' . $fs;
        }

        if (!is_readable($real) || !is_dir($real)) {
            $result['errors'][] = 'not_readable';
            return $result;
        }
        $result['readable'] = true;

        $total = @disk_total_space($real);
        $free = @disk_free_space($real);
        if ($total === false || $free === false) {
            $result['errors'][] = 'statvfs_failed';
            return $result;
        }
        $result['total_bytes'] = (int)$total;
        $result['free_bytes'] = max(0, (int)$free);
        $result['used_bytes'] = max(0, (int)$total - (int)$free);

        // A read-only mount is never pooled: migration needs to copy off it and
        // the pool must never advertise capacity it cannot write to.
        $opts = (string)($mount['options'] ?? '') . ',' . (string)($mount['super_options'] ?? '');
        $readOnlyMount = preg_match('/(^|,)ro(,|$)/', $opts) === 1;
        $writable = !$readOnlyMount && is_writable($real);
        if ($writable) {
            $probe = $real . '/.appdashboard-write-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
            $ok = @file_put_contents($probe, 'ok', LOCK_EX);
            if ($ok !== false) {
                @unlink($probe);
            } else {
                $writable = false;
            }
        }
        $result['writable'] = $writable;
        if (!$writable) {
            $result['errors'][] = $readOnlyMount ? 'read_only_filesystem' : 'not_writable';
            return $result;
        }

        $identity = $this->resolveIdentity($real, $mount, true);
        $result['uuid'] = $identity['uuid'];
        $result['identity_source'] = $identity['source'];
        if ($identity['uuid'] === '') {
            $result['errors'][] = 'identity_unavailable';
            return $result;
        }
        if (!empty($identity['warning'])) $result['warnings'][] = $identity['warning'];

        $result['compatible'] = $result['errors'] === [];
        $result['metadata'] = [
            'source' => $result['source'],
            'device' => $result['device'],
            'mount_options' => (string)($mount['options'] ?? ''),
            'marker' => $identity['marker'],
        ];
        return $result;
    }

    /** Exact mount point lookup. A parent mount never qualifies as a disk. */
    public static function findMount(string $path, array $mounts): ?array
    {
        $path = rtrim($path, '/') ?: '/';
        foreach ($mounts as $mount) {
            $mp = rtrim((string)($mount['mount_point'] ?? ''), '/') ?: '/';
            if ($mp === $path) return $mount;
        }
        return null;
    }

    /** @return list<array<string,mixed>> */
    public static function parseMountInfoFile(string $file): array
    {
        if (!is_readable($file)) return [];
        $raw = @file_get_contents($file);
        return is_string($raw) ? self::parseMountInfo($raw) : [];
    }

    /** @return list<array<string,mixed>> */
    public static function parseMountInfo(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = preg_split('/\s+/', $line) ?: [];
            $sep = array_search('-', $parts, true);
            if ($sep === false || $sep < 6 || !isset($parts[$sep + 1])) continue;
            $out[] = [
                'mount_id' => (int)($parts[0] ?? 0),
                'parent_id' => (int)($parts[1] ?? 0),
                'major_minor' => (string)($parts[2] ?? ''),
                'root' => self::unescape((string)($parts[3] ?? '/')),
                'mount_point' => self::unescape((string)($parts[4] ?? '/')),
                'options' => (string)($parts[5] ?? ''),
                'fstype' => (string)($parts[$sep + 1] ?? ''),
                'source' => self::unescape((string)($parts[$sep + 2] ?? '')),
                'super_options' => (string)($parts[$sep + 3] ?? ''),
            ];
        }
        return $out;
    }

    private static function unescape(string $value): string
    {
        return preg_replace_callback('/\\\\([0-7]{3})/', static function ($m) {
            return chr((int)octdec($m[1]));
        }, $value) ?? $value;
    }

    /**
     * Resolve a stable identity.
     *
     * @return array{uuid:string,source:string,marker:array<string,mixed>|null,warning:string}
     */
    private function resolveIdentity(string $path, array $mount, bool $allowWriteMarker): array
    {
        $markerPath = $path . '/' . self::MARKER_FILE;
        $marker = null;
        if (is_file($markerPath)) {
            $raw = (string)@file_get_contents($markerPath);
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && preg_match('/^[A-Za-z0-9._:-]{8,128}$/', (string)($decoded['uuid'] ?? ''))) {
                $marker = $decoded;
            } elseif (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', trim($raw))) {
                $marker = ['uuid' => trim($raw)];
            }
            if (is_array($marker)) {
                $kind = (string)($marker['kind'] ?? 'marker');
                $prefix = $kind === 'fsuuid' ? 'fsuuid:' : 'marker:';
                return ['uuid' => $prefix . (string)$marker['uuid'], 'source' => $kind === 'fsuuid' ? 'filesystem_uuid' : 'marker', 'marker' => $marker, 'warning' => ''];
            }
        }

        $fsUuid = $this->lookupFilesystemUuid($path, $mount);
        if ($fsUuid !== '') {
            $newMarker = $this->writeMarker($path, $fsUuid, $allowWriteMarker, 'fsuuid');
            return ['uuid' => 'fsuuid:' . $fsUuid, 'source' => 'filesystem_uuid', 'marker' => $newMarker, 'warning' => ''];
        }

        // No UUID exposed by the container (common with bind mounts). A marker
        // written onto the disk is the stable identity; when the disk is
        // read-only fall back to the mount source hash, never to the directory
        // name alone.
        $generated = self::generateUuid();
        $newMarker = $this->writeMarker($path, $generated, $allowWriteMarker, 'marker');
        if (is_array($newMarker)) {
            return ['uuid' => 'marker:' . $generated, 'source' => 'generated_marker', 'marker' => $newMarker, 'warning' => ''];
        }
        $source = (string)($mount['source'] ?? '');
        $device = (string)($mount['major_minor'] ?? '');
        $fstype = (string)($mount['fstype'] ?? '');
        if ($source !== '' && !str_starts_with($source, '/dev/loop')) {
            return ['uuid' => 'source:' . substr(hash('sha256', $source . '|' . $fstype), 0, 40), 'source' => 'mount_source', 'marker' => null, 'warning' => 'identity_from_mount_source'];
        }
        return ['uuid' => 'dev:' . substr(hash('sha256', $device . '|' . $fstype), 0, 40), 'source' => 'device_number', 'marker' => null, 'warning' => 'identity_not_stable_across_reboots'];
    }

    private function lookupFilesystemUuid(string $path, array $mount): string
    {
        if (storage_env('STORAGE_SKIP_FINDMNT', '') !== '1' && storage_command_exists('findmnt')) {
            $r = storage_run_command(['findmnt', '-n', '-o', 'UUID', '--target', $path], 10);
            $value = trim((string)($r['stdout'] ?? ''));
            if ((int)$r['code'] === 0 && self::isUuidLike($value)) return $value;
        }
        $source = (string)($mount['source'] ?? '');
        if ($source !== '' && str_starts_with($source, '/dev/') && storage_command_exists('blkid')) {
            $r = storage_run_command(['blkid', '-s', 'UUID', '-o', 'value', $source], 10);
            $value = trim((string)($r['stdout'] ?? ''));
            if ((int)$r['code'] === 0 && self::isUuidLike($value)) return $value;
        }
        // Docker often exposes /dev/disk/by-uuid even without block devices.
        if ($source !== '' && str_starts_with($source, '/dev/disk/by-uuid/')) {
            $value = basename($source);
            if (self::isUuidLike($value)) return $value;
        }
        return '';
    }

    /** @return array<string,mixed>|null */
    private function writeMarker(string $path, string $uuid, bool $allowWrite, string $kind = 'marker'): ?array
    {
        $marker = ['uuid' => $uuid, 'kind' => $kind, 'version' => 1, 'created_at' => time(), 'host' => (string)gethostname()];
        if (!$allowWrite) return null;
        $file = $path . '/' . self::MARKER_FILE;
        if (is_file($file)) {
            $decoded = json_decode((string)@file_get_contents($file), true);
            return is_array($decoded) ? $decoded : ['uuid' => $uuid, 'kind' => $kind];
        }
        $fp = @fopen($file, 'x');
        if ($fp === false) return null;
        $json = (string)json_encode($marker, JSON_UNESCAPED_SLASHES);
        @fwrite($fp, $json);
        @fflush($fp);
        @fclose($fp);
        @chmod($file, 0644);
        return $marker;
    }

    public static function isUuidLike(string $value): bool
    {
        $value = trim($value);
        if ($value === '') return false;
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)
            || (bool)preg_match('/^[0-9a-f]{16,64}$/i', $value);
    }

    public static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $h = bin2hex($bytes);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
    }
}
