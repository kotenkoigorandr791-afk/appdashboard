<?php
/**
 * PostgreSQL persistence for the centralised file index.
 *
 * Every sub-application of AppDashboard stores its file and directory
 * metadata here. The physical filesystem remains the source of file
 * *contents*; the index is the only source of structure and metadata for
 * user-facing requests.
 *
 * SQLite is supported for the test-suite (FileIndexDb::$pdo), PostgreSQL is
 * used in production. No migration runner exists in this project, so the
 * schema is ensured once per process from canonical CREATE TABLE IF NOT EXISTS
 * statements.
 */
declare(strict_types=1);

require_once __DIR__ . '/StorageDb.php';

final class FileIndexDb
{
    public static ?PDO $pdo = null;
    private static bool $schemaReady = false;

    public const OBJECT_TYPES = ['file', 'directory'];
    public const STATUSES = ['active', 'deleted', 'missing', 'pending'];

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;
        return StorageDb::pdo();
    }

    public static function isSqlite(): bool
    {
        try {
            return self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array<string,mixed>|false */
    private static function fetchOne(PDOStatement $stmt)
    {
        $result = $stmt->execute();
        if (is_object($result) && method_exists($result, 'fetch')) return $result->fetch(PDO::FETCH_ASSOC);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    private static function fetchAll(PDOStatement $stmt): array
    {
        $result = $stmt->execute();
        if (is_object($result) && method_exists($result, 'fetchAll')) return $result->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function decode(array $row): array
    {
        foreach (['id', 'parent_id', 'disk_id', 'size_bytes', 'mtime', 'created_at', 'updated_at'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) $row[$key] = (int)$row[$key];
        }
        $meta = json_decode((string)($row['metadata'] ?? '{}'), true);
        $row['metadata'] = is_array($meta) ? $meta : [];
        return $row;
    }

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        $pdo = self::pdo();
        $pk = self::isSqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGSERIAL PRIMARY KEY';

        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_files (
            id $pk,
            user_id TEXT NOT NULL,
            app_key TEXT NOT NULL,
            disk_id BIGINT DEFAULT NULL,
            parent_id BIGINT DEFAULT NULL,
            name TEXT NOT NULL,
            relative_path TEXT NOT NULL,
            object_type TEXT NOT NULL DEFAULT 'file',
            mime_type TEXT NOT NULL DEFAULT '',
            extension TEXT NOT NULL DEFAULT '',
            size_bytes BIGINT NOT NULL DEFAULT 0,
            mtime BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0,
            updated_at BIGINT NOT NULL DEFAULT 0,
            content_hash TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'active',
            metadata TEXT NOT NULL DEFAULT '{}'
        )");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_storage_files_path ON storage_files(user_id, app_key, relative_path)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_files_parent ON storage_files(user_id, app_key, parent_id, object_type, name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_files_name ON storage_files(user_id, app_key, name)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_files_disk_path ON storage_files(disk_id, relative_path)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_files_updated ON storage_files(user_id, app_key, updated_at DESC)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_files_status ON storage_files(user_id, app_key, status)");

        // Transactional outbox: filesystem mutations survive restarts because
        // they are persisted before being handed to a worker.
        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_file_outbox (
            id $pk,
            event_key TEXT NOT NULL UNIQUE,
            user_id TEXT NOT NULL DEFAULT '',
            app_key TEXT NOT NULL DEFAULT '',
            event_type TEXT NOT NULL,
            payload TEXT NOT NULL DEFAULT '{}',
            status TEXT NOT NULL DEFAULT 'pending',
            attempts INTEGER NOT NULL DEFAULT 0,
            available_at BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0,
            processed_at BIGINT NOT NULL DEFAULT 0,
            last_error TEXT NOT NULL DEFAULT ''
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_file_outbox_pending ON storage_file_outbox(status, available_at, id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_file_outbox_app ON storage_file_outbox(app_key, user_id, id DESC)");

        // Resumable initial-index / reconciliation jobs, one per app+root.
        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_index_jobs (
            id $pk,
            job_key TEXT NOT NULL UNIQUE,
            user_id TEXT NOT NULL DEFAULT '',
            app_key TEXT NOT NULL DEFAULT '',
            root_path TEXT NOT NULL DEFAULT '',
            disk_id BIGINT DEFAULT NULL,
            mode TEXT NOT NULL DEFAULT 'initial',
            status TEXT NOT NULL DEFAULT 'pending',
            total_files BIGINT NOT NULL DEFAULT 0,
            total_directories BIGINT NOT NULL DEFAULT 0,
            total_bytes BIGINT NOT NULL DEFAULT 0,
            scanned_files BIGINT NOT NULL DEFAULT 0,
            scanned_directories BIGINT NOT NULL DEFAULT 0,
            scanned_bytes BIGINT NOT NULL DEFAULT 0,
            cursor TEXT NOT NULL DEFAULT '',
            error_count BIGINT NOT NULL DEFAULT 0,
            last_error TEXT NOT NULL DEFAULT '',
            started_at BIGINT NOT NULL DEFAULT 0,
            finished_at BIGINT NOT NULL DEFAULT 0,
            heartbeat_at BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0,
            updated_at BIGINT NOT NULL DEFAULT 0,
            metadata TEXT NOT NULL DEFAULT '{}'
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_index_jobs_status ON storage_index_jobs(status, id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_index_jobs_app ON storage_index_jobs(app_key, user_id)");

        // Background scan queue: one row per directory still to inspect. It
        // keeps indexing resumable and bounded in memory.
        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_index_queue (
            id $pk,
            job_id BIGINT NOT NULL,
            relative_path TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            created_at BIGINT NOT NULL DEFAULT 0,
            updated_at BIGINT NOT NULL DEFAULT 0
        )");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_storage_index_queue_path ON storage_index_queue(job_id, relative_path)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_index_queue_status ON storage_index_queue(job_id, status, id)");

        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_index_meta (
            meta_key TEXT PRIMARY KEY,
            meta_value TEXT NOT NULL DEFAULT '',
            updated_at BIGINT NOT NULL DEFAULT 0
        )");

        // pg_trgm makes substring search on large indexes usable. It is
        // optional: PostgreSQL without the extension keeps the btree index.
        if (!self::isSqlite()) {
            try {
                $pdo->exec('CREATE EXTENSION IF NOT EXISTS pg_trgm');
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_files_name_trgm ON storage_files USING gin (name gin_trgm_ops)");
            } catch (Throwable $e) {
                // Extension may require superuser privileges; search still works.
            }
            try {
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_files_active_parent ON storage_files(parent_id) WHERE status = 'active'");
            } catch (Throwable $e) {}
        } else {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_storage_files_active_parent ON storage_files(parent_id)");
        }

        self::$schemaReady = true;
    }

    /* ------------------------------------------------------------------
     * storage_files
     * ---------------------------------------------------------------- */

    /** @return array<string,mixed>|null */
    public static function findByPath(string $userId, string $appKey, string $relativePath, bool $includeDeleted = true): ?array
    {
        self::ensureSchema();
        $sql = 'SELECT * FROM storage_files WHERE user_id = :u AND app_key = :a AND relative_path = :p';
        if (!$includeDeleted) $sql .= " AND status = 'active'";
        $sql .= ' LIMIT 1';
        $stmt = self::pdo()->prepare($sql);
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $stmt->bindValue(':p', $relativePath, PDO::PARAM_STR);
        $row = self::fetchOne($stmt);
        return is_array($row) ? self::decode($row) : null;
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('SELECT * FROM storage_files WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $row = self::fetchOne($stmt);
        return is_array($row) ? self::decode($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public static function children(
        string $userId,
        string $appKey,
        ?int $parentId,
        int $limit = 200,
        ?string $afterName = null,
        ?int $afterId = null,
        bool $includeDeleted = false
    ): array {
        self::ensureSchema();
        $limit = max(1, min(1000, $limit));
        $sql = 'SELECT id,user_id,app_key,disk_id,parent_id,name,relative_path,object_type,mime_type,extension,size_bytes,mtime,created_at,updated_at,status
                FROM storage_files WHERE user_id = :u AND app_key = :a';
        if ($parentId === null) $sql .= ' AND parent_id IS NULL';
        else $sql .= ' AND parent_id = :parent';
        if (!$includeDeleted) $sql .= " AND status = 'active'";
        if ($afterName !== null) {
            $sql .= ' AND (name > :after_name OR (name = :after_name2 AND id > :after_id))';
        }
        $sql .= ' ORDER BY name ASC, id ASC LIMIT :lim';
        $stmt = self::pdo()->prepare($sql);
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        if ($parentId !== null) $stmt->bindValue(':parent', $parentId, PDO::PARAM_INT);
        if ($afterName !== null) {
            $stmt->bindValue(':after_name', $afterName, PDO::PARAM_STR);
            $stmt->bindValue(':after_name2', $afterName, PDO::PARAM_STR);
            $stmt->bindValue(':after_id', (int)$afterId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        return array_map([self::class, 'decode'], self::fetchAll($stmt));
    }

    /**
     * Search by partial name. Uses pg_trgm when available, a plain LIKE
     * otherwise. Always scoped to one user and one app.
     *
     * @return list<array<string,mixed>>
     */
    public static function search(string $userId, string $appKey, string $query, int $limit = 100, int $offset = 0, bool $includeDeleted = false, ?string $rootPrefix = null): array
    {
        self::ensureSchema();
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $sql = 'SELECT id,user_id,app_key,disk_id,parent_id,name,relative_path,object_type,mime_type,extension,size_bytes,mtime,created_at,updated_at,status
                FROM storage_files WHERE user_id = :u AND app_key = :a AND lower(name) LIKE :q';
        if (!$includeDeleted) $sql .= " AND status = 'active'";
        if ($rootPrefix !== null && $rootPrefix !== '') {
            $sql .= ' AND (relative_path = :root OR relative_path LIKE :root_like)';
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT :lim OFFSET :off';
        $stmt = self::pdo()->prepare($sql);
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $needle = function_exists('mb_strtolower') ? mb_strtolower($query) : strtolower($query);
        $stmt->bindValue(':q', '%' . $needle . '%', PDO::PARAM_STR);
        if ($rootPrefix !== null && $rootPrefix !== '') {
            $stmt->bindValue(':root', $rootPrefix, PDO::PARAM_STR);
            $stmt->bindValue(':root_like', $rootPrefix . '/%', PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        return array_map([self::class, 'decode'], self::fetchAll($stmt));
    }

    /**
     * Insert or update one object. Returns the stable row id.
     *
     * @param array<string,mixed> $fields
     */
    public static function upsert(string $userId, string $appKey, string $relativePath, array $fields): int
    {
        self::ensureSchema();
        $existing = self::findByPath($userId, $appKey, $relativePath, true);
        $now = time();
        $name = (string)($fields['name'] ?? ($relativePath === '' ? '' : basename($relativePath)));
        $type = in_array((string)($fields['object_type'] ?? 'file'), self::OBJECT_TYPES, true)
            ? (string)$fields['object_type'] : 'file';
        if ($relativePath === '') $type = 'directory';
        $statusValue = (string)($fields['status'] ?? 'active');
        $status = in_array($statusValue, self::STATUSES, true) ? $statusValue : 'active';
        $metadata = $fields['metadata'] ?? [];
        if (is_array($metadata)) $metadata = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $parentId = $fields['parent_id'] ?? null;
        if (is_string($parentId)) $parentId = (int)$parentId;
        $bind = [
            'user_id' => $userId,
            'app_key' => $appKey,
            'disk_id' => isset($fields['disk_id']) ? (int)$fields['disk_id'] : null,
            'parent_id' => $parentId !== null ? (int)$parentId : null,
            'name' => $name,
            'relative_path' => $relativePath,
            'object_type' => $type,
            'mime_type' => (string)($fields['mime_type'] ?? ''),
            'extension' => (string)($fields['extension'] ?? strtolower(pathinfo($name, PATHINFO_EXTENSION))),
            'size_bytes' => max(0, (int)($fields['size_bytes'] ?? 0)),
            'mtime' => (int)($fields['mtime'] ?? $now),
            'created_at' => (int)($fields['created_at'] ?? ($existing['created_at'] ?? $now)),
            'updated_at' => $now,
            'content_hash' => (string)($fields['content_hash'] ?? ($existing['content_hash'] ?? '')),
            'status' => $status,
            'metadata' => (string)$metadata,
        ];
        if ($existing !== null) {
            $sets = [];
            $update = $bind;
            unset($update['user_id'], $update['app_key'], $update['relative_path']);
            // `updated_at` is part of the binding set already; adding it twice
            // breaks PostgreSQL with "multiple assignments to same column".
            foreach ($update as $col => $_) $sets[] = $col . ' = :' . $col;
            $stmt = self::pdo()->prepare('UPDATE storage_files SET ' . implode(', ', $sets) . ' WHERE id = :id');
            $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
            foreach ($update as $col => $value) {
                $type = ($value === null) ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
                $stmt->bindValue(':' . $col, $value, $type);
            }
            $stmt->execute();
            return (int)$existing['id'];
        }
        $sql = 'INSERT INTO storage_files
            (user_id,app_key,disk_id,parent_id,name,relative_path,object_type,mime_type,extension,size_bytes,mtime,created_at,updated_at,content_hash,status,metadata)
            VALUES (:user_id,:app_key,:disk_id,:parent_id,:name,:relative_path,:object_type,:mime_type,:extension,:size_bytes,:mtime,:created_at,:updated_at,:content_hash,:status,:metadata)';
        if (!self::isSqlite()) $sql .= ' RETURNING id';
        $stmt = self::pdo()->prepare($sql);
        foreach ($bind as $col => $value) {
            $type = ($value === null) ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $stmt->bindValue(':' . $col, $value, $type);
        }
        $stmt->execute();
        if (!self::isSqlite()) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $id = (int)($row['id'] ?? 0);
        } else {
            $id = (int)self::pdo()->lastInsertId();
        }
        return $id;
    }

    /** Merge keys into a row's metadata document without touching other columns. */
    public static function mergeMetadata(int $id, array $meta): void
    {
        self::ensureSchema();
        $row = self::findById($id);
        if ($row === null) return;
        $merged = array_merge(is_array($row['metadata']) ? $row['metadata'] : [], $meta);
        $stmt = self::pdo()->prepare('UPDATE storage_files SET metadata = :m, updated_at = :t WHERE id = :id');
        $stmt->bindValue(':m', (string)json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $stmt->bindValue(':t', time(), PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) return;
        self::ensureSchema();
        $stmt = self::pdo()->prepare('UPDATE storage_files SET status = :s, updated_at = :t WHERE id = :id');
        $stmt->bindValue(':s', $status, PDO::PARAM_STR);
        $stmt->bindValue(':t', time(), PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Rename/move one object and every indexed descendant in a single
     * statement, keeping stable ids. Returns the affected row count.
     */
    public static function moveSubtree(string $userId, string $appKey, string $oldPath, string $newPath, ?int $newParentId): int
    {
        self::ensureSchema();
        $oldPrefix = $oldPath . '/';
        $newPrefix = $newPath === '' ? '' : $newPath . '/';
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            // Descendants first, using substr from the old prefix length.
            $sql = 'UPDATE storage_files
                    SET relative_path = :new_prefix || ' . (self::isSqlite() ? 'substr' : 'substr') . '(relative_path, :cut),
                        updated_at = :t
                    WHERE user_id = :u AND app_key = :a AND relative_path LIKE :like';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':new_prefix', $newPrefix, PDO::PARAM_STR);
            $stmt->bindValue(':cut', strlen($oldPrefix) + 1, PDO::PARAM_INT);
            $stmt->bindValue(':t', time(), PDO::PARAM_INT);
            $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
            $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
            $stmt->bindValue(':like', $oldPrefix . '%', PDO::PARAM_STR);
            $stmt->execute();
            $affected = $stmt->rowCount();

            $stmt = $pdo->prepare('UPDATE storage_files SET relative_path = :new_path, name = :name, parent_id = :parent, updated_at = :t
                                   WHERE user_id = :u AND app_key = :a AND relative_path = :old_path');
            $stmt->bindValue(':new_path', $newPath, PDO::PARAM_STR);
            $stmt->bindValue(':name', $newPath === '' ? '' : basename($newPath), PDO::PARAM_STR);
            $stmt->bindValue(':parent', $newParentId, $newParentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':t', time(), PDO::PARAM_INT);
            $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
            $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
            $stmt->bindValue(':old_path', $oldPath, PDO::PARAM_STR);
            $stmt->execute();
            $affected += $stmt->rowCount();
            if (self::isSqlite()) {
                // SQLite needs a distinct update for parent links of direct children;
                // PostgreSQL descendants retain their parent_id by design.
            }
            $pdo->commit();
            return $affected;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Soft-delete an object and all descendants. */
    public static function markSubtreeDeleted(string $userId, string $appKey, string $relativePath, string $status = 'deleted'): int
    {
        if (!in_array($status, self::STATUSES, true)) return 0;
        self::ensureSchema();
        $now = time();
        $stmt = self::pdo()->prepare('UPDATE storage_files SET status = :s, updated_at = :t
            WHERE user_id = :u AND app_key = :a AND (relative_path = :p OR relative_path LIKE :like)');
        $stmt->bindValue(':s', $status, PDO::PARAM_STR);
        $stmt->bindValue(':t', $now, PDO::PARAM_INT);
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $stmt->bindValue(':p', $relativePath, PDO::PARAM_STR);
        $stmt->bindValue(':like', $relativePath . '/%', PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /** Reactivate an object and all descendants (trash restore). */
    public static function restoreSubtree(string $userId, string $appKey, string $relativePath): int
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare("UPDATE storage_files SET status = 'active', updated_at = :t
            WHERE user_id = :u AND app_key = :a AND (relative_path = :p OR relative_path LIKE :like)");
        $stmt->bindValue(':t', time(), PDO::PARAM_INT);
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $stmt->bindValue(':p', $relativePath, PDO::PARAM_STR);
        $stmt->bindValue(':like', $relativePath . '/%', PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public static function deleteSubtree(string $userId, string $appKey, string $relativePath): int
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('DELETE FROM storage_files
            WHERE user_id = :u AND app_key = :a AND (relative_path = :p OR relative_path LIKE :like)');
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $stmt->bindValue(':p', $relativePath, PDO::PARAM_STR);
        $stmt->bindValue(':like', $relativePath . '/%', PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Active objects below a directory prefix (recursive listing without a
     * filesystem walk). Ordered by path so callers can paginate.
     *
     * @return list<array<string,mixed>>
     */
    public static function descendants(string $userId, string $appKey, string $prefix, int $limit = 500, int $offset = 0): array
    {
        self::ensureSchema();
        $limit = max(1, min(5000, $limit));
        $prefix = rtrim($prefix, '/');
        $stmt = self::pdo()->prepare("SELECT id,user_id,app_key,disk_id,parent_id,name,relative_path,object_type,mime_type,extension,size_bytes,mtime,created_at,updated_at,status
            FROM storage_files WHERE user_id = :u AND app_key = :a AND status = 'active'
              AND relative_path LIKE :like
            ORDER BY relative_path ASC, id ASC LIMIT :lim OFFSET :off");
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $stmt->bindValue(':like', ($prefix === '' ? '' : $prefix . '/') . '%', PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
        return array_map([self::class, 'decode'], self::fetchAll($stmt));
    }

    /**
     * Flag indexed children that a fresh scan of their parent did not find.
     * Rows are kept (never dropped silently) so recovery stays possible.
     *
     * @param list<string> $seenPaths relative paths that still exist
     */
    public static function markMissingChildren(string $userId, string $appKey, int $parentId, array $seenPaths): int
    {
        self::ensureSchema();
        $seen = array_fill_keys(array_map('strval', $seenPaths), true);
        $stmt = self::pdo()->prepare("SELECT id, relative_path FROM storage_files
            WHERE user_id = :u AND app_key = :a AND parent_id = :p AND status = 'active'");
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $stmt->bindValue(':p', $parentId, PDO::PARAM_INT);
        $stmt->execute();
        $missing = 0;
        $upd = self::pdo()->prepare('UPDATE storage_files SET status = :s, updated_at = :t WHERE id = :id');
        foreach (self::fetchAll($stmt) as $row) {
            if (isset($seen[(string)$row['relative_path']])) continue;
            $upd->bindValue(':s', 'missing', PDO::PARAM_STR);
            $upd->bindValue(':t', time(), PDO::PARAM_INT);
            $upd->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
            $upd->execute();
            $missing++;
        }
        return $missing;
    }

    /** @return list<array<string,mixed>> */
    public static function filesOnDisk(int $diskId, int $limit = 500, int $afterId = 0): array
    {
        self::ensureSchema();
        $limit = max(1, min(5000, $limit));
        $stmt = self::pdo()->prepare('SELECT * FROM storage_files WHERE disk_id = :d AND id > :after ORDER BY id ASC LIMIT :lim');
        $stmt->bindValue(':d', $diskId, PDO::PARAM_INT);
        $stmt->bindValue(':after', $afterId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        return array_map([self::class, 'decode'], self::fetchAll($stmt));
    }

    /** @return array<string,int|float> */
    /** @return list<array<string,mixed>> */
    /**
     * Every active object under a root, for tree/usage views. Metadata only,
     * never file contents.
     *
     * @return list<array<string,mixed>>
     */
    public static function treeUnderRoot(string $userId, string $appKey, string $rootPath, int $limit = 200000): array
    {
        self::ensureSchema();
        $limit = max(1, min(500000, $limit));
        $stmt = self::pdo()->prepare("SELECT id,parent_id,relative_path,name,object_type,size_bytes,mtime
            FROM storage_files WHERE user_id = :u AND app_key = :a AND status = 'active'
              AND (relative_path = :root OR relative_path LIKE :like)
            ORDER BY relative_path ASC LIMIT :lim");
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $stmt->bindValue(':root', $rootPath, PDO::PARAM_STR);
        $stmt->bindValue(':like', $rootPath . '/%', PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        return array_map([self::class, 'decode'], self::fetchAll($stmt));
    }

    /**
     * Remove every indexed object, queued event and indexing job belonging to
     * a deleted user. Called only from the user-purge flow.
     *
     * @param list<string> $userIds
     * @return array{files:int,outbox:int,jobs:int,queue:int}
     */
    public static function purgeUser(array $userIds): array
    {
        self::ensureSchema();
        $userIds = array_values(array_unique(array_filter(array_map('strval', $userIds), static fn($v) => $v !== '')));
        if ($userIds === []) return ['files' => 0, 'outbox' => 0, 'jobs' => 0, 'queue' => 0];
        $ph = [];
        $bind = [];
        foreach ($userIds as $i => $id) {
            $ph[] = ':u' . $i;
            $bind[':u' . $i] = $id;
        }
        $in = implode(',', $ph);
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $jobIds = [];
            $stmt = $pdo->prepare('SELECT id FROM storage_index_jobs WHERE user_id IN (' . $in . ')');
            foreach ($bind as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $jobIds[] = (int)$row['id'];

            $queue = 0;
            if ($jobIds !== []) {
                $qph = implode(',', array_fill(0, count($jobIds), '?'));
                $q = $pdo->prepare('DELETE FROM storage_index_queue WHERE job_id IN (' . $qph . ')');
                $q->execute($jobIds);
                $queue = $q->rowCount();
            }
            $jobs = $pdo->prepare('DELETE FROM storage_index_jobs WHERE user_id IN (' . $in . ')');
            foreach ($bind as $k => $v) $jobs->bindValue($k, $v, PDO::PARAM_STR);
            $jobs->execute();

            $outbox = $pdo->prepare('DELETE FROM storage_file_outbox WHERE user_id IN (' . $in . ')');
            foreach ($bind as $k => $v) $outbox->bindValue($k, $v, PDO::PARAM_STR);
            $outbox->execute();

            $files = $pdo->prepare('DELETE FROM storage_files WHERE user_id IN (' . $in . ')');
            foreach ($bind as $k => $v) $files->bindValue($k, $v, PDO::PARAM_STR);
            $files->execute();
            $pdo->commit();
            return [
                'files' => $files->rowCount(),
                'outbox' => $outbox->rowCount(),
                'jobs' => $jobs->rowCount(),
                'queue' => $queue,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public static function rootDirectories(int $limit = 1000): array
    {
        self::ensureSchema();
        $limit = max(1, min(5000, $limit));
        $stmt = self::pdo()->prepare("SELECT * FROM storage_files WHERE parent_id IS NULL AND object_type = 'directory' AND status = 'active' ORDER BY id ASC LIMIT :lim");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        return array_map([self::class, 'decode'], self::fetchAll($stmt));
    }

    public static function stats(): array
    {
        self::ensureSchema();
        $pdo = self::pdo();
        $out = [
            'files' => 0,
            'directories' => 0,
            'users' => 0,
            'apps' => 0,
            'bytes' => 0,
            'deleted' => 0,
            'missing' => 0,
        ];
        $rows = self::fetchAll($pdo->query("SELECT object_type, COUNT(*) AS c, COALESCE(SUM(size_bytes),0) AS b FROM storage_files WHERE status = 'active' GROUP BY object_type"));
        foreach ($rows as $row) {
            if ((string)$row['object_type'] === 'directory') $out['directories'] = (int)$row['c'];
            else $out['files'] += (int)$row['c'];
            $out['bytes'] += (int)$row['b'];
        }
        $out['users'] = (int)($pdo->query("SELECT COUNT(DISTINCT user_id) FROM storage_files WHERE status = 'active'")->fetchColumn() ?: 0);
        $out['apps'] = (int)($pdo->query("SELECT COUNT(DISTINCT app_key) FROM storage_files WHERE status = 'active'")->fetchColumn() ?: 0);
        $out['deleted'] = (int)($pdo->query("SELECT COUNT(*) FROM storage_files WHERE status = 'deleted'")->fetchColumn() ?: 0);
        $out['missing'] = (int)($pdo->query("SELECT COUNT(*) FROM storage_files WHERE status = 'missing'")->fetchColumn() ?: 0);
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public static function statsByApp(): array
    {
        self::ensureSchema();
        return self::fetchAll(self::pdo()->query("SELECT app_key, COUNT(*) AS objects, COALESCE(SUM(size_bytes),0) AS bytes
            FROM storage_files WHERE status = 'active' GROUP BY app_key ORDER BY objects DESC"));
    }

    /** @return list<array<string,mixed>> */
    public static function statsByDisk(): array
    {
        self::ensureSchema();
        return self::fetchAll(self::pdo()->query("SELECT disk_id, COUNT(*) AS objects, COALESCE(SUM(size_bytes),0) AS bytes
            FROM storage_files WHERE status = 'active' AND disk_id IS NOT NULL GROUP BY disk_id ORDER BY objects DESC"));
    }

    /* ------------------------------------------------------------------
     * Transactional outbox
     * ---------------------------------------------------------------- */

    /** @param array<string,mixed> $payload */
    public static function enqueue(string $eventKey, string $appKey, string $userId, string $eventType, array $payload = []): void
    {
        self::ensureSchema();
        $now = time();
        $sql = 'INSERT INTO storage_file_outbox (event_key,user_id,app_key,event_type,payload,status,attempts,available_at,created_at,processed_at,last_error)
                VALUES (:key,:u,:a,:type,:payload,\'pending\',0,:now,:now2,0,\'\')
                ON CONFLICT(event_key) DO NOTHING';
        $stmt = self::pdo()->prepare($sql);
        $stmt->bindValue(':key', $eventKey, PDO::PARAM_STR);
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $stmt->bindValue(':type', $eventType, PDO::PARAM_STR);
        $stmt->bindValue(':payload', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $stmt->bindValue(':now', $now, PDO::PARAM_INT);
        $stmt->bindValue(':now2', $now, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** @return list<array<string,mixed>> */
    public static function claimOutbox(int $limit = 100): array
    {
        self::ensureSchema();
        $limit = max(1, min(1000, $limit));
        $now = time();
        $pdo = self::pdo();
        $stmt = $pdo->prepare("SELECT * FROM storage_file_outbox WHERE status = 'pending' AND available_at <= :now ORDER BY id ASC LIMIT :lim");
        $stmt->bindValue(':now', $now, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $rows = self::fetchAll($stmt);
        $claimed = [];
        $update = $pdo->prepare("UPDATE storage_file_outbox SET status = 'processing', attempts = attempts + 1, processed_at = :now WHERE id = :id AND status = 'pending'");
        foreach ($rows as $row) {
            $update->bindValue(':now', $now, PDO::PARAM_INT);
            $update->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
            $update->execute();
            if ($update->rowCount() === 1) $claimed[] = $row;
        }
        return $claimed;
    }

    public static function completeOutbox(int $id): void
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare("UPDATE storage_file_outbox SET status = 'completed', processed_at = :t, last_error = '' WHERE id = :id");
        $stmt->bindValue(':t', time(), PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public static function failOutbox(int $id, string $error, int $attempts = 0): void
    {
        self::ensureSchema();
        // Exponential backoff capped at one hour; after 10 attempts the event
        // stays failed for the admin UI instead of retrying forever.
        $attempts = max(1, $attempts);
        $delay = min(3600, 5 * (2 ** min(10, $attempts - 1)));
        $status = $attempts >= 10 ? 'failed' : 'pending';
        $stmt = self::pdo()->prepare('UPDATE storage_file_outbox SET status = :s, last_error = :e, available_at = :at WHERE id = :id');
        $stmt->bindValue(':s', $status, PDO::PARAM_STR);
        $stmt->bindValue(':e', substr($error, 0, 4000), PDO::PARAM_STR);
        $stmt->bindValue(':at', time() + $delay, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** Recover events left 'processing' by a killed worker. */
    public static function recoverStaleOutbox(int $olderThanSeconds = 300): int
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare("UPDATE storage_file_outbox SET status = 'pending', available_at = :now
            WHERE status = 'processing' AND processed_at < :cutoff");
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $stmt->bindValue(':cutoff', time() - max(30, $olderThanSeconds), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /** @return array<string,int> */
    public static function outboxCounts(): array
    {
        self::ensureSchema();
        $out = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        $rows = self::fetchAll(self::pdo()->query('SELECT status, COUNT(*) AS c FROM storage_file_outbox GROUP BY status'));
        foreach ($rows as $row) $out[(string)$row['status']] = (int)$row['c'];
        return $out;
    }

    /* ------------------------------------------------------------------
     * Index jobs
     * ---------------------------------------------------------------- */

    /** @param array<string,mixed> $metadata */
    public static function enqueueJob(string $jobKey, string $appKey, string $userId, string $rootPath, string $mode = 'initial', ?int $diskId = null, array $metadata = []): int
    {
        self::ensureSchema();
        $now = time();
        $existing = self::jobByKey($jobKey);
        if ($existing !== null) {
            if (in_array((string)$existing['status'], ['completed', 'failed', 'paused'], true)) {
                $stmt = self::pdo()->prepare("UPDATE storage_index_jobs SET status = 'pending', last_error = '', updated_at = :t WHERE id = :id");
                $stmt->bindValue(':t', $now, PDO::PARAM_INT);
                $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
                $stmt->execute();
            }
            return (int)$existing['id'];
        }
        $sql = 'INSERT INTO storage_index_jobs
            (job_key,user_id,app_key,root_path,disk_id,mode,status,total_files,total_directories,total_bytes,scanned_files,scanned_directories,scanned_bytes,cursor,error_count,last_error,started_at,finished_at,heartbeat_at,created_at,updated_at,metadata)
            VALUES (:key,:u,:a,:root,:disk,:mode,\'pending\',0,0,0,0,0,0,\'\',0,\'\',0,0,0,:now,:now,:meta)';
        if (!self::isSqlite()) $sql .= ' RETURNING id';
        $stmt = self::pdo()->prepare($sql);
        $stmt->bindValue(':key', $jobKey, PDO::PARAM_STR);
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $stmt->bindValue(':root', $rootPath, PDO::PARAM_STR);
        $stmt->bindValue(':disk', $diskId, $diskId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':mode', $mode, PDO::PARAM_STR);
        $stmt->bindValue(':now', $now, PDO::PARAM_INT);
        $stmt->bindValue(':meta', json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $stmt->execute();
        if (!self::isSqlite()) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($row['id'] ?? 0);
        }
        return (int)self::pdo()->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public static function jobByKey(string $jobKey): ?array
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('SELECT * FROM storage_index_jobs WHERE job_key = :k LIMIT 1');
        $stmt->bindValue(':k', $jobKey, PDO::PARAM_STR);
        $row = self::fetchOne($stmt);
        return is_array($row) ? self::decodeJob($row) : null;
    }

    /** @return array<string,mixed>|null */
    public static function jobById(int $id): ?array
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('SELECT * FROM storage_index_jobs WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $row = self::fetchOne($stmt);
        return is_array($row) ? self::decodeJob($row) : null;
    }

    /** @return array<string,mixed>|null */
    public static function nextJob(array $modes = ['initial', 'reconcile']): ?array
    {
        self::ensureSchema();
        $placeholders = [];
        $bind = [];
        foreach (array_values($modes) as $i => $mode) {
            $placeholders[] = ':m' . $i;
            $bind[':m' . $i] = (string)$mode;
        }
        if ($placeholders === []) return null;
        $stmt = self::pdo()->prepare("SELECT * FROM storage_index_jobs WHERE status = 'pending' AND mode IN (" . implode(',', $placeholders) . ') ORDER BY id ASC LIMIT 1');
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $row = self::fetchOne($stmt);
        return is_array($row) ? self::decodeJob($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public static function jobs(int $limit = 50): array
    {
        self::ensureSchema();
        $limit = max(1, min(500, $limit));
        $stmt = self::pdo()->prepare('SELECT * FROM storage_index_jobs ORDER BY id DESC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        return array_map([self::class, 'decodeJob'], self::fetchAll($stmt));
    }

    /** @param array<string,mixed> $fields */
    public static function updateJob(int $id, array $fields): void
    {
        self::ensureSchema();
        $allowed = ['status', 'total_files', 'total_directories', 'total_bytes', 'scanned_files', 'scanned_directories',
            'scanned_bytes', 'cursor', 'error_count', 'last_error', 'started_at', 'finished_at', 'heartbeat_at', 'metadata', 'root_path', 'disk_id'];
        $sets = [];
        $bind = [':id' => $id, ':updated_at' => time()];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) continue;
            $value = $fields[$col];
            if ($col === 'metadata' && is_array($value)) $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $sets[] = $col . ' = :' . $col;
            $bind[':' . $col] = $value;
        }
        if ($sets === []) return;
        $sets[] = 'updated_at = :updated_at';
        $stmt = self::pdo()->prepare('UPDATE storage_index_jobs SET ' . implode(', ', $sets) . ' WHERE id = :id');
        foreach ($bind as $k => $v) {
            $type = $v === null ? PDO::PARAM_NULL : (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
    }

    public static function enqueueQueueItem(int $jobId, string $relativePath): void
    {
        self::ensureSchema();
        $now = time();
        $stmt = self::pdo()->prepare('INSERT INTO storage_index_queue (job_id,relative_path,status,created_at,updated_at)
            VALUES (:j,:p,\'pending\',:t,:t2) ON CONFLICT(job_id, relative_path) DO NOTHING');
        $stmt->bindValue(':j', $jobId, PDO::PARAM_INT);
        $stmt->bindValue(':p', $relativePath, PDO::PARAM_STR);
        $stmt->bindValue(':t', $now, PDO::PARAM_INT);
        $stmt->bindValue(':t2', $now, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** @return list<array<string,mixed>> */
    public static function pendingQueueItems(int $jobId, int $limit = 20): array
    {
        self::ensureSchema();
        $limit = max(1, min(500, $limit));
        $stmt = self::pdo()->prepare("SELECT * FROM storage_index_queue WHERE job_id = :j AND status = 'pending' ORDER BY id ASC LIMIT :lim");
        $stmt->bindValue(':j', $jobId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        return self::fetchAll($stmt);
    }

    public static function completeQueueItem(int $id): void
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare("UPDATE storage_index_queue SET status = 'done', updated_at = :t WHERE id = :id");
        $stmt->bindValue(':t', time(), PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public static function pendingQueueCount(int $jobId): int
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare("SELECT COUNT(*) FROM storage_index_queue WHERE job_id = :j AND status = 'pending'");
        $stmt->bindValue(':j', $jobId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public static function clearQueue(int $jobId): void
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('DELETE FROM storage_index_queue WHERE job_id = :j');
        $stmt->bindValue(':j', $jobId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Objects under a root that the last full scan did not touch. They are
     * flagged missing instead of being deleted, so an offline disk never loses
     * index metadata.
     */
    public static function markUnseenUnderRoot(string $userId, string $appKey, string $rootPath, int $scanStartedAt): int
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare("UPDATE storage_files SET status = 'missing', updated_at = :now
            WHERE user_id = :u AND app_key = :a AND status = 'active' AND updated_at < :started
              AND (relative_path = :root OR relative_path LIKE :like)");
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $stmt->bindValue(':u', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':a', $appKey, PDO::PARAM_STR);
        $stmt->bindValue(':started', $scanStartedAt, PDO::PARAM_INT);
        $stmt->bindValue(':root', $rootPath, PDO::PARAM_STR);
        $stmt->bindValue(':like', $rootPath . '/%', PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /** Requeue jobs interrupted by a container restart. */
    public static function recoverStaleJobs(int $olderThanSeconds = 300): int
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare("UPDATE storage_index_jobs SET status = 'pending', updated_at = :now
            WHERE status = 'running' AND heartbeat_at < :cutoff");
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $stmt->bindValue(':cutoff', time() - max(30, $olderThanSeconds), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function decodeJob(array $row): array
    {
        foreach (['id', 'disk_id', 'total_files', 'total_directories', 'total_bytes', 'scanned_files', 'scanned_directories',
            'scanned_bytes', 'error_count', 'started_at', 'finished_at', 'heartbeat_at', 'created_at', 'updated_at'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) $row[$key] = (int)$row[$key];
        }
        $meta = json_decode((string)($row['metadata'] ?? '{}'), true);
        $row['metadata'] = is_array($meta) ? $meta : [];
        return $row;
    }

    public static function metaGet(string $key, string $default = ''): string
    {
        self::ensureSchema();
        $stmt = self::pdo()->prepare('SELECT meta_value FROM storage_index_meta WHERE meta_key = :k LIMIT 1');
        $stmt->bindValue(':k', $key, PDO::PARAM_STR);
        $row = self::fetchOne($stmt);
        return is_array($row) ? (string)$row['meta_value'] : $default;
    }

    public static function metaSet(string $key, string $value): void
    {
        self::ensureSchema();
        $sql = 'INSERT INTO storage_index_meta (meta_key, meta_value, updated_at) VALUES (:k,:v,:t)
                ON CONFLICT(meta_key) DO UPDATE SET meta_value = :v2, updated_at = :t2';
        $stmt = self::pdo()->prepare($sql);
        $stmt->bindValue(':k', $key, PDO::PARAM_STR);
        $stmt->bindValue(':v', $value, PDO::PARAM_STR);
        $stmt->bindValue(':t', time(), PDO::PARAM_INT);
        $stmt->bindValue(':v2', $value, PDO::PARAM_STR);
        $stmt->bindValue(':t2', time(), PDO::PARAM_INT);
        $stmt->execute();
    }
}
