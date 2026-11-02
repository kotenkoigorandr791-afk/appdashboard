<?php
/**
 * Storage state in PostgreSQL (SQLite is supported for tests via StorageDb::$pdo).
 *
 * The app has no standalone migration runner: schema is ensured once per
 * process from the canonical CREATE TABLE IF NOT EXISTS statements below.
 */
declare(strict_types=1);

final class StorageDb
{
    public static ?PDO $pdo = null;
    private static bool $schemaReady = false;

    public const STATUSES = ['online', 'offline', 'initializing', 'migrating', 'removing', 'error', 'ready_to_remove'];
    public const MIGRATION_STATUSES = [
        'pending', 'checking', 'preparing', 'backup_running', 'migrating',
        'verifying', 'removing_branch', 'completed', 'failed', 'paused',
    ];
    public const TERMINAL_MIGRATION_STATUSES = ['completed', 'failed'];

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;
        if (!function_exists('getDB')) {
            throw new RuntimeException('Database is not available');
        }
        $db = getDB();
        if ($db instanceof PDO) return $db;
        // The application wraps PDO in AppDatabase (db.php); storage needs the
        // raw PDO for its prepared statements and schema DDL.
        if (is_object($db) && method_exists($db, 'getPdo')) {
            $pdo = $db->getPdo();
            if ($pdo instanceof PDO) return $pdo;
        }
        throw new RuntimeException('Database connection is not a PDO instance');
    }

    /** @return array<string,mixed>|false */
    private static function fetchOne(PDOStatement $stmt, int $mode = PDO::FETCH_ASSOC)
    {
        $result = $stmt->execute();
        if (is_object($result) && method_exists($result, 'fetch')) return $result->fetch($mode);
        return $stmt->fetch($mode);
    }

    /** @return list<array<string,mixed>> */
    private static function rowsOf($source): array
    {
        if (is_object($source) && method_exists($source, 'fetchAll')) return $source->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rows = [];
        if (is_object($source) && method_exists($source, 'fetch')) {
            while (($row = $source->fetch(PDO::FETCH_ASSOC)) !== false && $row !== null) $rows[] = $row;
        }
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private static function fetchAll(PDOStatement $stmt): array
    {
        $result = $stmt->execute();
        if (is_object($result) && method_exists($result, 'fetchAll')) return $result->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (is_object($result) && method_exists($result, 'fetch')) return self::rowsOf($result);
        return self::rowsOf($stmt);
    }

    public static function isSqlite(): bool
    {
        try {
            return self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        $pdo = self::pdo();
        $pk = self::isSqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGSERIAL PRIMARY KEY';

        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_disks (
            id $pk,
            uuid TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL DEFAULT '',
            mount_path TEXT NOT NULL UNIQUE,
            filesystem TEXT NOT NULL DEFAULT '',
            total_bytes BIGINT NOT NULL DEFAULT 0,
            used_bytes BIGINT NOT NULL DEFAULT 0,
            free_bytes BIGINT NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'initializing',
            identity_source TEXT NOT NULL DEFAULT '',
            metadata TEXT NOT NULL DEFAULT '{}',
            last_seen_at BIGINT NOT NULL DEFAULT 0,
            last_health_check_at BIGINT NOT NULL DEFAULT 0,
            last_backup_at BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0,
            updated_at BIGINT NOT NULL DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_disks_status ON storage_disks(status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_disks_last_seen ON storage_disks(last_seen_at DESC)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_migrations (
            id TEXT PRIMARY KEY,
            source_disk_id BIGINT NOT NULL REFERENCES storage_disks(id),
            source_uuid TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'pending',
            total_files BIGINT NOT NULL DEFAULT 0,
            migrated_files BIGINT NOT NULL DEFAULT 0,
            total_bytes BIGINT NOT NULL DEFAULT 0,
            migrated_bytes BIGINT NOT NULL DEFAULT 0,
            progress DOUBLE PRECISION NOT NULL DEFAULT 0,
            current_file TEXT NOT NULL DEFAULT '',
            speed_bytes DOUBLE PRECISION NOT NULL DEFAULT 0,
            error_count BIGINT NOT NULL DEFAULT 0,
            error TEXT NOT NULL DEFAULT '',
            cancel_requested INTEGER NOT NULL DEFAULT 0,
            started_at BIGINT NOT NULL DEFAULT 0,
            completed_at BIGINT NOT NULL DEFAULT 0,
            metadata TEXT NOT NULL DEFAULT '{}',
            created_at BIGINT NOT NULL DEFAULT 0,
            updated_at BIGINT NOT NULL DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_migrations_source ON storage_migrations(source_disk_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_migrations_status ON storage_migrations(status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_migrations_created ON storage_migrations(created_at DESC)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_migration_files (
            id $pk,
            migration_id TEXT NOT NULL REFERENCES storage_migrations(id) ON DELETE CASCADE,
            rel_path TEXT NOT NULL,
            file_type TEXT NOT NULL DEFAULT 'file',
            size_bytes BIGINT NOT NULL DEFAULT 0,
            checksum TEXT NOT NULL DEFAULT '',
            target_disk_id BIGINT NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'pending',
            error TEXT NOT NULL DEFAULT '',
            created_at BIGINT NOT NULL DEFAULT 0,
            updated_at BIGINT NOT NULL DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_migration_files_migration ON storage_migration_files(migration_id, status)");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_storage_migration_files_path ON storage_migration_files(migration_id, rel_path)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_events (
            id $pk,
            disk_id BIGINT DEFAULT NULL REFERENCES storage_disks(id) ON DELETE SET NULL,
            migration_id TEXT DEFAULT NULL,
            event_type TEXT NOT NULL DEFAULT '',
            message TEXT NOT NULL DEFAULT '',
            metadata TEXT NOT NULL DEFAULT '{}',
            created_at BIGINT NOT NULL DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_events_created ON storage_events(created_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_events_disk ON storage_events(disk_id, created_at DESC)");

        // Capacity history: one sample per disk every 30 minutes, kept 30 days.
        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_usage_samples (
            id $pk,
            disk_id BIGINT NOT NULL REFERENCES storage_disks(id) ON DELETE CASCADE,
            total_bytes BIGINT NOT NULL DEFAULT 0,
            used_bytes BIGINT NOT NULL DEFAULT 0,
            free_bytes BIGINT NOT NULL DEFAULT 0,
            recorded_at BIGINT NOT NULL DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_usage_disk_time ON storage_usage_samples(disk_id, recorded_at DESC)");

        self::$schemaReady = true;
    }

    /** @return array<string,mixed>|null */
    public static function diskByUuid(string $uuid): ?array
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('SELECT * FROM storage_disks WHERE uuid = :uuid LIMIT 1');
        $stmt->bindValue(':uuid', $uuid, PDO::PARAM_STR);
        $row = self::fetchOne($stmt);
        return is_array($row) ? self::decodeDisk($row) : null;
    }

    /** @return array<string,mixed>|null */
    public static function diskById(int $id): ?array
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('SELECT * FROM storage_disks WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $row = self::fetchOne($stmt);
        return is_array($row) ? self::decodeDisk($row) : null;
    }

    /** @return array<string,mixed>|null */
    public static function diskByPath(string $path): ?array
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('SELECT * FROM storage_disks WHERE mount_path = :p LIMIT 1');
        $stmt->bindValue(':p', $path, PDO::PARAM_STR);
        $row = self::fetchOne($stmt);
        return is_array($row) ? self::decodeDisk($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public static function disks(bool $includeRemoved = true): array
    {
        self::ensureSchema();
        $sql = 'SELECT * FROM storage_disks';
        if (!$includeRemoved) $sql .= " WHERE status NOT IN ('ready_to_remove','removing')";
        $sql .= ' ORDER BY name ASC, id ASC';
        $rows = self::rowsOf(self::pdo()->query($sql));
        return array_values(array_map([self::class, 'decodeDisk'], $rows));
    }

    /** @param array<string,mixed> $disk */
    public static function insertDisk(array $disk): int
    {
        self::ensureSchema();
        $now = time();
        $sql = 'INSERT INTO storage_disks
            (uuid,name,mount_path,filesystem,total_bytes,used_bytes,free_bytes,status,identity_source,metadata,last_seen_at,last_health_check_at,last_backup_at,created_at,updated_at)
            VALUES (:uuid,:name,:mount_path,:filesystem,:total_bytes,:used_bytes,:free_bytes,:status,:identity_source,:metadata,:last_seen_at,:last_health_check_at,:last_backup_at,:created_at,:updated_at)';
        // RETURNING is race-free on PostgreSQL; SQLite (tests) uses lastInsertId.
        if (!self::isSqlite()) $sql .= ' RETURNING id';
        $stmt = self::pdo()->prepare($sql);
        $bind = [
            ':uuid' => (string)$disk['uuid'],
            ':name' => (string)($disk['name'] ?? ''),
            ':mount_path' => (string)$disk['mount_path'],
            ':filesystem' => (string)($disk['filesystem'] ?? ''),
            ':total_bytes' => (int)($disk['total_bytes'] ?? 0),
            ':used_bytes' => (int)($disk['used_bytes'] ?? 0),
            ':free_bytes' => (int)($disk['free_bytes'] ?? 0),
            ':status' => self::normalizeStatus((string)($disk['status'] ?? 'online')),
            ':identity_source' => (string)($disk['identity_source'] ?? ''),
            ':metadata' => json_encode($disk['metadata'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':last_seen_at' => (int)($disk['last_seen_at'] ?? $now),
            ':last_health_check_at' => (int)($disk['last_health_check_at'] ?? 0),
            ':last_backup_at' => (int)($disk['last_backup_at'] ?? 0),
            ':created_at' => (int)($disk['created_at'] ?? $now),
            ':updated_at' => $now,
        ];
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->execute();
        $id = 0;
        if (!self::isSqlite()) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $id = (int)($row['id'] ?? 0);
        }
        if ($id <= 0) $id = (int)self::pdo()->lastInsertId();
        $disk['id'] = $id;
        return $id;
    }

    /** @param array<string,mixed> $fields */
    public static function updateDisk(int $id, array $fields): void
    {
        self::ensureSchema();
        $allowed = ['name', 'mount_path', 'filesystem', 'total_bytes', 'used_bytes', 'free_bytes', 'status', 'identity_source', 'metadata', 'last_seen_at', 'last_health_check_at', 'last_backup_at'];
        $sets = [];
        $bind = [':id' => $id, ':updated_at' => time()];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) continue;
            $value = $fields[$col];
            if ($col === 'metadata' && is_array($value)) $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($col === 'status') $value = self::normalizeStatus((string)$value);
            $sets[] = $col . ' = :' . $col;
            $bind[':' . $col] = is_int($value) ? $value : (string)$value;
        }
        if ($sets === []) return;
        $sets[] = 'updated_at = :updated_at';
        $stmt = self::pdo()->prepare('UPDATE storage_disks SET ' . implode(', ', $sets) . ' WHERE id = :id');
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->execute();
    }

    public static function markAllOffline(array $seenUuids): void
    {
        self::ensureSchema();
        $seen = array_flip($seenUuids);
        foreach (self::disks() as $disk) {
            if (isset($seen[(string)$disk['uuid']])) continue;
            if (in_array((string)$disk['status'], ['offline', 'ready_to_remove'], true)) continue;
            self::updateDisk((int)$disk['id'], ['status' => 'offline']);
        }
    }

    /** @return array<string,mixed>|null */
    public static function migration(string $id): ?array
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('SELECT * FROM storage_migrations WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
        $row = self::fetchOne($stmt);
        return is_array($row) ? self::decodeMigration($row) : null;
    }

    /** @return array<string,mixed>|null */
    public static function activeMigration(): ?array
    {
        self::ensureSchema();
        $placeholders = implode(',', array_fill(0, count(self::MIGRATION_STATUSES) - 2, '?'));
        $stmt = self::pdo()->prepare('SELECT * FROM storage_migrations WHERE status IN (' . $placeholders . ') ORDER BY created_at DESC LIMIT 1');
        $i = 1;
        foreach (self::MIGRATION_STATUSES as $status) {
            if (in_array($status, self::TERMINAL_MIGRATION_STATUSES, true)) continue;
            $stmt->bindValue($i++, $status, PDO::PARAM_STR);
        }
        $row = self::fetchOne($stmt);
        return is_array($row) ? self::decodeMigration($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public static function migrations(int $limit = 20): array
    {
        self::ensureSchema();
        $limit = max(1, min(200, $limit));
        $stmt = self::pdo()->prepare('SELECT * FROM storage_migrations ORDER BY created_at DESC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $rows = self::fetchAll($stmt);
        return array_values(array_map([self::class, 'decodeMigration'], $rows));
    }

    public static function createMigration(string $id, int $sourceDiskId, array $metadata = []): void
    {
        self::ensureSchema();
        $now = time();
        $disk = self::diskById($sourceDiskId);
        $stmt = self::pdo()->prepare('INSERT INTO storage_migrations
            (id,source_disk_id,source_uuid,status,total_files,migrated_files,total_bytes,migrated_bytes,progress,current_file,speed_bytes,error_count,error,cancel_requested,started_at,completed_at,metadata,created_at,updated_at)
            VALUES (:id,:source_disk_id,:source_uuid,:status,0,0,0,0,0,\'\',0,0,\'\',0,:started_at,0,:metadata,:created_at,:updated_at)');
        $bind = [
            ':id' => $id,
            ':source_disk_id' => $sourceDiskId,
            ':source_uuid' => (string)($disk['uuid'] ?? ''),
            ':status' => 'pending',
            ':started_at' => $now,
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':created_at' => $now,
            ':updated_at' => $now,
        ];
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->execute();
    }

    /** @param array<string,mixed> $fields */
    public static function updateMigration(string $id, array $fields): void
    {
        self::ensureSchema();
        $allowed = ['status', 'total_files', 'migrated_files', 'total_bytes', 'migrated_bytes', 'progress', 'current_file', 'speed_bytes', 'error_count', 'error', 'cancel_requested', 'started_at', 'completed_at', 'metadata'];
        $sets = [];
        $bind = [':id' => $id, ':updated_at' => time()];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) continue;
            $value = $fields[$col];
            if ($col === 'metadata' && is_array($value)) $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($col === 'status') {
                if (!in_array((string)$value, self::MIGRATION_STATUSES, true)) {
                    throw new InvalidArgumentException('Invalid migration status: ' . $value);
                }
            }
            $sets[] = $col . ' = :' . $col;
            $bind[':' . $col] = is_float($value) ? $value : (is_int($value) ? $value : (string)$value);
        }
        if ($sets === []) return;
        $sets[] = 'updated_at = :updated_at';
        $stmt = self::pdo()->prepare('UPDATE storage_migrations SET ' . implode(', ', $sets) . ' WHERE id = :id');
        foreach ($bind as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : (is_float($v) ? PDO::PARAM_STR : PDO::PARAM_STR);
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
    }

    /** @return list<array<string,mixed>> */
    public static function migrationFiles(string $migrationId, array $statuses = []): array
    {
        self::ensureSchema();
        $sql = 'SELECT * FROM storage_migration_files WHERE migration_id = :mid';
        $bind = [':mid' => $migrationId];
        if ($statuses !== []) {
            $parts = [];
            foreach (array_values($statuses) as $i => $status) {
                $parts[] = ':s' . $i;
                $bind[':s' . $i] = (string)$status;
            }
            $sql .= ' AND status IN (' . implode(',', $parts) . ')';
        }
        $sql .= ' ORDER BY id ASC';
        $stmt = self::pdo()->prepare($sql);
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        return self::fetchAll($stmt);
    }

    public static function addMigrationFile(string $migrationId, string $relPath, int $size = 0, string $fileType = 'file'): void
    {
        self::ensureSchema();
        $now = time();
        $stmt = self::pdo()->prepare('INSERT INTO storage_migration_files (migration_id,rel_path,file_type,size_bytes,checksum,target_disk_id,status,error,created_at,updated_at)
            VALUES (:mid,:rel,:file_type,:size,0,0,\'pending\',\'\',:created_at,:updated_at)
            ON CONFLICT(migration_id, rel_path) DO NOTHING');
        $bind = [':mid' => $migrationId, ':rel' => $relPath, ':file_type' => $fileType, ':size' => $size, ':created_at' => $now, ':updated_at' => $now];
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        try { $stmt->execute(); } catch (Throwable $e) { /* duplicate path race */ }
    }

    /** @param array<string,mixed> $fields */
    public static function updateMigrationFile(int $fileId, array $fields): void
    {
        self::ensureSchema();
        $allowed = ['checksum', 'target_disk_id', 'status', 'error', 'size_bytes', 'file_type'];
        $sets = [];
        $bind = [':id' => $fileId, ':updated_at' => time()];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) continue;
            $sets[] = $col . ' = :' . $col;
            $bind[':' . $col] = is_int($fields[$col]) ? $fields[$col] : (string)$fields[$col];
        }
        if ($sets === []) return;
        $sets[] = 'updated_at = :updated_at';
        $stmt = self::pdo()->prepare('UPDATE storage_migration_files SET ' . implode(', ', $sets) . ' WHERE id = :id');
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->execute();
    }

    public static function addEvent(string $type, string $message, ?int $diskId = null, array $metadata = [], ?string $migrationId = null): void
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('INSERT INTO storage_events (disk_id,migration_id,event_type,message,metadata,created_at)
            VALUES (:disk_id,:migration_id,:event_type,:message,:metadata,:created_at)');
        $bind = [
            ':disk_id' => $diskId,
            ':migration_id' => $migrationId,
            ':event_type' => $type,
            ':message' => substr($message, 0, 4000),
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':created_at' => time(),
        ];
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->execute();
    }

    /** @return list<array<string,mixed>> */
    public static function events(int $limit = 50, ?int $diskId = null): array
    {
        self::ensureSchema();
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT * FROM storage_events';
        $bind = [':lim' => $limit];
        if ($diskId !== null) {
            $sql .= ' WHERE disk_id = :disk_id';
            $bind[':disk_id'] = $diskId;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT :lim';
        $stmt = self::pdo()->prepare($sql);
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_INT);
        $rows = self::fetchAll($stmt);
        foreach ($rows as &$row) {
            $row['metadata'] = self::jsonDecode((string)($row['metadata'] ?? '{}'));
        }
        unset($row);
        return $rows ?: [];
    }

    /**
     * Persist capacity samples, at most one per disk per 30 minutes.
     *
     * @param list<array<string,mixed>> $disks
     * @return int number of inserted samples
     */
    public static function recordUsageSamples(array $disks, bool $force = false): int
    {
        self::ensureSchema();
        $now = time();
        $interval = 1800;
        $inserted = 0;
        $insert = self::pdo()->prepare('INSERT INTO storage_usage_samples (disk_id,total_bytes,used_bytes,free_bytes,recorded_at)
            VALUES (:disk_id,:total_bytes,:used_bytes,:free_bytes,:recorded_at)');
        $last = self::pdo()->prepare('SELECT recorded_at FROM storage_usage_samples WHERE disk_id = :disk_id ORDER BY recorded_at DESC LIMIT 1');
        foreach ($disks as $disk) {
            $id = (int)($disk['id'] ?? 0);
            if ($id <= 0 || (string)($disk['status'] ?? '') !== 'online') continue;
            $last->bindValue(':disk_id', $id, PDO::PARAM_INT);
            $row = self::fetchOne($last);
            $previous = is_array($row) ? (int)($row['recorded_at'] ?? 0) : 0;
            if (!$force && $previous > 0 && ($now - $previous) < $interval) continue;
            $insert->bindValue(':disk_id', $id, PDO::PARAM_INT);
            $insert->bindValue(':total_bytes', max(0, (int)($disk['total_bytes'] ?? 0)), PDO::PARAM_INT);
            $insert->bindValue(':used_bytes', max(0, (int)($disk['used_bytes'] ?? 0)), PDO::PARAM_INT);
            $insert->bindValue(':free_bytes', max(0, (int)($disk['free_bytes'] ?? 0)), PDO::PARAM_INT);
            $insert->bindValue(':recorded_at', $now, PDO::PARAM_INT);
            $insert->execute();
            $inserted++;
        }
        self::pruneUsageSamples(30);
        return $inserted;
    }

    public static function pruneUsageSamples(int $days = 30): void
    {
        $cutoff = time() - max(1, $days) * 86400;
        $stmt = self::pdo()->prepare('DELETE FROM storage_usage_samples WHERE recorded_at < :cutoff');
        $stmt->bindValue(':cutoff', $cutoff, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Per-disk capacity history for the last N hours, downsampled for a chart.
     *
     * @return array<int,list<array{t:int,used:int,total:int,free:int}>>
     */
    public static function usageHistory(int $hours = 24, int $maxPoints = 48): array
    {
        self::ensureSchema();
        $hours = max(1, min(24 * 30, $hours));
        $cutoff = time() - $hours * 3600;
        $stmt = self::pdo()->prepare('SELECT disk_id,total_bytes,used_bytes,free_bytes,recorded_at
            FROM storage_usage_samples WHERE recorded_at >= :cutoff ORDER BY recorded_at ASC');
        $stmt->bindValue(':cutoff', $cutoff, PDO::PARAM_INT);
        $rows = self::fetchAll($stmt);
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['disk_id']][] = [
                't' => (int)$row['recorded_at'],
                'used' => (int)$row['used_bytes'],
                'total' => (int)$row['total_bytes'],
                'free' => (int)$row['free_bytes'],
            ];
        }
        foreach ($grouped as $id => $points) {
            $count = count($points);
            if ($count > $maxPoints) {
                $step = (int)ceil($count / $maxPoints);
                $sampled = [];
                for ($i = 0; $i < $count; $i += $step) $sampled[] = $points[$i];
                if (end($sampled) !== end($points)) $sampled[] = end($points);
                $grouped[$id] = $sampled;
            }
        }
        return $grouped;
    }

    public static function touchBackupTime(): void
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('UPDATE storage_disks SET last_backup_at = :ts, updated_at = :ts2');
        $sta = time();
        $stmt->bindValue(':ts', $sta, PDO::PARAM_INT);
        $stmt->bindValue(':ts2', $sta, PDO::PARAM_INT);
        $stmt->execute();
    }

    private static function normalizeStatus(string $status): string
    {
        return in_array($status, self::STATUSES, true) ? $status : 'error';
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function decodeDisk(array $row): array
    {
        foreach (['id', 'total_bytes', 'used_bytes', 'free_bytes', 'last_seen_at', 'last_health_check_at', 'last_backup_at', 'created_at', 'updated_at'] as $key) {
            if (isset($row[$key])) $row[$key] = (int)$row[$key];
        }
        $row['metadata'] = self::jsonDecode((string)($row['metadata'] ?? '{}'));
        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function decodeMigration(array $row): array
    {
        foreach (['total_files', 'migrated_files', 'total_bytes', 'migrated_bytes', 'error_count', 'cancel_requested', 'started_at', 'completed_at', 'created_at', 'updated_at'] as $key) {
            if (isset($row[$key])) $row[$key] = (int)$row[$key];
        }
        foreach (['progress', 'speed_bytes'] as $key) {
            if (isset($row[$key])) $row[$key] = (float)$row[$key];
        }
        $row['metadata'] = self::jsonDecode((string)($row['metadata'] ?? '{}'));
        return $row;
    }

    /** @return array<string,mixed> */
    private static function jsonDecode(string $json): array
    {
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
