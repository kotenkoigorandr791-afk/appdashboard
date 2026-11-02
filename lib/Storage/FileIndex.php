<?php
/**
 * Centralised file index — the single source of file/directory metadata for
 * every AppDashboard sub-application.
 *
 * User-facing code must never scan a directory to build a listing. It asks
 * FileIndex for the children of an indexed directory (a single SQL query) and
 * resolves file ids to physical paths only for the specific object it needs.
 *
 * Relative paths are stored relative to the *application* base directory
 * (appdata_app_base_dir($appKey)), e.g. "alice/documents/report.pdf". Every
 * row carries user_id, app_key and disk_id, so isolation is enforced in SQL
 * and never inferred from a client-supplied path.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/FileIndexDb.php';

final class FileIndex
{
    /** Maximum page size for directory listings and searches. */
    public const MAX_PAGE = 500;

    public static function isEnabled(): bool
    {
        return storage_env('FILE_INDEX_ENABLED', '1') !== '0';
    }

    /* ------------------------------------------------------------------
     * Paths and isolation
     * ---------------------------------------------------------------- */

    /** Sanitise a single path component / user name the same way storage_helper does. */
    public static function sanitizeUser(string $user): string
    {
        $user = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $user);
        return $user === '' || $user === null ? 'guest' : $user;
    }

    /** Normalise an app-relative path and reject traversal. */
    public static function normalizeRelative(string $path): string
    {
        if (str_contains($path, "\0")) throw new RuntimeException('invalid_path');
        $path = str_replace('\\', '/', $path);
        $path = trim($path);
        $parts = explode('/', $path);
        $out = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') throw new RuntimeException('invalid_path');
            $out[] = $part;
        }
        $normalized = implode('/', $out);
        if (strlen($normalized) > 4000) throw new RuntimeException('path_too_long');
        return $normalized;
    }

    /** The user's directory relative to the app base (generic apps). */
    public static function userRoot(string $user): string
    {
        return self::sanitizeUser($user);
    }

    /** Assert a normalized app-relative path stays inside the caller's user root. */
    public static function assertInsideRoot(string $rootRelative, string $relativePath): void
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        if ($rootRelative === '') throw new RuntimeException('invalid_root');
        if ($relativePath === $rootRelative) return;
        if (!str_starts_with($relativePath, $rootRelative . '/')) {
            throw new RuntimeException('path_outside_root');
        }
    }

    /** Absolute physical root of an application's data. */
    public static function appBaseDir(string $appKey): string
    {
        if (function_exists('appdata_app_base_dir')) return appdata_app_base_dir($appKey);
        throw new RuntimeException('storage_helper_not_loaded');
    }

    /** Absolute path of an indexed object (no directory scans involved). */
    public static function physicalPath(string $appKey, string $relativePath): string
    {
        $rel = self::normalizeRelative($relativePath);
        $base = rtrim(self::appBaseDir($appKey), '/');
        return $rel === '' ? $base : $base . '/' . $rel;
    }

    /* ------------------------------------------------------------------
     * Reads
     * ---------------------------------------------------------------- */

    /** @return array<string,mixed>|null */
    public static function findByPath(string $appKey, string $userId, string $relativePath, bool $includeDeleted = true): ?array
    {
        return FileIndexDb::findByPath($userId, $appKey, self::normalizeRelative($relativePath), $includeDeleted);
    }

    /** Raw row by id (internal/admin use; still app/user-scoped by callers). */
    public static function findById(int $fileId): ?array
    {
        return FileIndexDb::findById($fileId);
    }

    /** @return array<string,mixed>|null */
    public static function get(int $fileId, string $appKey, string $userId, ?string $rootRelative = null): ?array
    {
        $row = FileIndexDb::findById($fileId);
        if ($row === null) return null;
        if ((string)$row['app_key'] !== $appKey || (string)$row['user_id'] !== $userId) return null;
        if ($rootRelative !== null) {
            try {
                self::assertInsideRoot($rootRelative, (string)$row['relative_path']);
            } catch (Throwable $e) {
                return null;
            }
        }
        return $row;
    }

    /**
     * Children of a directory, from PostgreSQL only.
     *
     * @param array{limit?:int,after_name?:string,after_id?:int,include_deleted?:bool} $opts
     * @return array{items:list<array<string,mixed>>,directory:?array<string,mixed>,next:?array{name:string,id:int},has_more:bool,total:int}
     */
    public static function listDirectory(string $appKey, string $userId, string $rootRelative, string $path = '', array $opts = []): array
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $path = self::normalizeRelative($path);
        if ($path === '') $parentPath = $rootRelative;
        else {
            $parentPath = self::normalizeRelative($rootRelative . '/' . $path);
            self::assertInsideRoot($rootRelative, $parentPath);
        }

        $directory = self::findByPath($appKey, $userId, $parentPath, false);
        if ($directory === null) {
            // The root row is created on first use; a missing nested directory
            // means the caller is asking about a path that is not indexed.
            return ['items' => [], 'directory' => null, 'next' => null, 'has_more' => false, 'total' => 0];
        }
        if ((string)$directory['object_type'] !== 'directory') throw new RuntimeException('not_a_directory');

        $limit = max(1, min(self::MAX_PAGE, (int)($opts['limit'] ?? 200)));
        $afterName = array_key_exists('after_name', $opts) && $opts['after_name'] !== null ? (string)$opts['after_name'] : null;
        $afterId = isset($opts['after_id']) ? (int)$opts['after_id'] : 0;
        $rows = FileIndexDb::children($userId, $appKey, (int)$directory['id'], $limit + 1, $afterName, $afterId, !empty($opts['include_deleted']));
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_splice($rows, $limit);
        $next = null;
        if ($hasMore && $rows !== []) {
            $last = end($rows);
            $next = ['name' => (string)$last['name'], 'id' => (int)$last['id']];
        }
        return [
            'items' => $rows,
            'directory' => $directory,
            'next' => $next,
            'has_more' => $hasMore,
            'total' => count($rows) + ($hasMore ? 1 : 0),
        ];
    }

    /**
     * Partial-name search scoped to one user and one app root.
     *
     * @return list<array<string,mixed>>
     */
    public static function search(string $appKey, string $userId, string $rootRelative, string $query, int $limit = 100, int $offset = 0): array
    {
        $query = trim($query);
        if ($query === '') return [];
        return FileIndexDb::search($userId, $appKey, $query, $limit, $offset, false, self::normalizeRelative($rootRelative));
    }

    /**
     * Every active object below a directory, from SQL only (recursive view for
     * the assistant and tree widgets).
     *
     * @return list<array<string,mixed>>
     */
    public static function listRecursive(string $appKey, string $userId, string $rootRelative, string $path = '', int $limit = 500, int $offset = 0): array
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $path = self::normalizeRelative($path);
        $prefix = $path === '' ? $rootRelative : self::normalizeRelative($rootRelative . '/' . $path);
        self::assertInsideRoot($rootRelative, $prefix);
        return FileIndexDb::descendants($userId, $appKey, $prefix, $limit, $offset);
    }

    /** Whether an active index row exists for the path (no filesystem access). */
    public static function exists(string $appKey, string $userId, string $rootRelative, string $relativePath): bool
    {
        try {
            $row = self::findByPath($appKey, $userId, self::normalizeRelative($relativePath), false);
        } catch (Throwable $e) {
            return false;
        }
        return $row !== null && (string)$row['status'] === 'active';
    }

    /**
     * Make sure one directory level is present in the index.
     *
     * The single-level scan lives in the worker; user-facing code only ever asks
     * the index. Repeated calls are cheap: the directory row records when it was
     * last scanned, and a fresh row is never rescanned.
     */
    public static function ensureIndexed(string $appKey, string $userId, string $rootRelative, string $path = '', int $ttlSeconds = 0): bool
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $path = self::normalizeRelative($path);
        $dirRel = $path === '' ? $rootRelative : self::normalizeRelative($rootRelative . '/' . $path);
        self::assertInsideRoot($rootRelative, $dirRel);
        $row = self::findByPath($appKey, $userId, $dirRel, true);
        if ($row !== null) {
            $indexedAt = (int)($row['metadata']['indexed_at'] ?? 0);
            if ($indexedAt > 0 && (time() - $indexedAt) <= max(0, $ttlSeconds)) return true;
        }
        if (!class_exists('FileIndexWorker')) {
            require_once __DIR__ . '/FileIndexWorker.php';
        }
        $r = FileIndexWorker::indexDirectory($appKey, $userId, $rootRelative, $dirRel);
        return (bool)$r['indexed'];
    }

    /**
     * Resolve a stable file id to a physical path, validating ownership,
     * application isolation, the user root and disk availability.
     */
    public static function resolvePhysicalPath(int $fileId, string $appKey, string $userId, string $rootRelative, bool $mustBeFile = true): string
    {
        $row = self::get($fileId, $appKey, $userId, $rootRelative);
        if ($row === null) throw new RuntimeException('file_not_found');
        if ((string)$row['status'] !== 'active') throw new RuntimeException('file_not_available');
        if ($mustBeFile && (string)$row['object_type'] !== 'file') throw new RuntimeException('not_a_file');
        if (!$mustBeFile && (string)$row['object_type'] !== 'directory') throw new RuntimeException('not_a_directory');

        $diskId = $row['disk_id'] !== null ? (int)$row['disk_id'] : 0;
        if ($diskId > 0) {
            $disk = StorageDb::diskById($diskId);
            if ($disk === null || (string)$disk['status'] !== 'online') {
                throw new RuntimeException('disk_offline');
            }
        }

        $full = self::physicalPath($appKey, (string)$row['relative_path']);
        $base = realpath(self::appBaseDir($appKey));
        $real = realpath($full);
        if ($base === false || $real === false) throw new RuntimeException('file_not_found');
        if ($real !== $base && !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('path_outside_root');
        }
        return $real;
    }

    /* ------------------------------------------------------------------
     * Writes (index updates)
     * ---------------------------------------------------------------- */

    /** Create/refresh the root directory row for a user+app. */
    public static function ensureRoot(string $appKey, string $userId, string $rootRelative): array
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        if ($rootRelative === '') throw new RuntimeException('invalid_root');
        $existing = self::findByPath($appKey, $userId, $rootRelative, true);
        if ($existing !== null && (string)$existing['object_type'] === 'directory' && (string)$existing['status'] === 'active') {
            return $existing;
        }
        FileIndexDb::upsert($userId, $appKey, $rootRelative, [
            'name' => basename($rootRelative),
            'object_type' => 'directory',
            'parent_id' => null,
            'status' => 'active',
            'extension' => '',
            'mime_type' => 'inode/directory',
        ]);
        return (array)self::findByPath($appKey, $userId, $rootRelative, false);
    }

    /**
     * Insert or update a single object after a real filesystem operation.
     * Missing parent directories are created in the index only.
     *
     * @param array<string,mixed> $meta
     */
    public static function record(string $appKey, string $userId, string $rootRelative, string $relativePath, array $meta = []): int
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $relativePath = self::normalizeRelative($relativePath);
        self::assertInsideRoot($rootRelative, $relativePath);
        self::ensureRoot($appKey, $userId, $rootRelative);
        $parentId = self::ensureParentChain($appKey, $userId, $rootRelative, $relativePath);

        $fields = $meta;
        $fields['parent_id'] = $parentId;
        if (!isset($fields['disk_id']) || (int)$fields['disk_id'] <= 0) {
            $fields['disk_id'] = self::diskIdFor($appKey, $relativePath);
        }
        return FileIndexDb::upsert($userId, $appKey, $relativePath, $fields);
    }

    /** Record a directory (physical directory already exists). */
    public static function recordDirectory(string $appKey, string $userId, string $rootRelative, string $relativePath, array $meta = []): int
    {
        if ($relativePath === $rootRelative) {
            $row = self::ensureRoot($appKey, $userId, $rootRelative);
            return (int)$row['id'];
        }
        $meta['object_type'] = 'directory';
        $meta['mime_type'] = 'inode/directory';
        $meta['size_bytes'] = 0;
        return self::record($appKey, $userId, $rootRelative, $relativePath, $meta);
    }

    /**
     * Fast path for the indexer: the parent id is already known, so no parent
     * chain lookups are needed for every scanned entry.
     *
     * @param array<string,mixed> $meta
     */
    public static function recordKnown(string $appKey, string $userId, string $rootRelative, string $relativePath, int $parentId, array $meta = []): int
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $relativePath = self::normalizeRelative($relativePath);
        self::assertInsideRoot($rootRelative, $relativePath);
        $meta['parent_id'] = $parentId > 0 ? $parentId : null;
        if (!isset($meta['disk_id']) || (int)$meta['disk_id'] <= 0) {
            $meta['disk_id'] = self::diskIdFor($appKey, $relativePath);
        }
        return FileIndexDb::upsert($userId, $appKey, $relativePath, $meta);
    }

    /** Record a file (physical file already exists and is known). */
    public static function recordFile(string $appKey, string $userId, string $rootRelative, string $relativePath, array $meta = []): int
    {
        $meta['object_type'] = 'file';
        return self::record($appKey, $userId, $rootRelative, $relativePath, $meta);
    }

    /**
     * Refresh metadata from the filesystem for a known object. This never
     * scans a directory.
     */
    public static function recordFromFilesystem(string $appKey, string $userId, string $rootRelative, string $relativePath): ?int
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $relativePath = self::normalizeRelative($relativePath);
        self::assertInsideRoot($rootRelative, $relativePath);
        $full = self::physicalPath($appKey, $relativePath);
        if (!file_exists($full)) {
            FileIndexDb::markSubtreeDeleted($userId, $appKey, $relativePath, 'missing');
            return null;
        }
        $stat = @lstat($full);
        $isDir = is_dir($full);
        $meta = [
            'name' => basename($relativePath),
            'object_type' => $isDir ? 'directory' : 'file',
            'mime_type' => $isDir ? 'inode/directory' : (function_exists('mime_content_type') ? (string)@mime_content_type($full) : ''),
            'extension' => $isDir ? '' : strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)),
            'size_bytes' => $isDir ? 0 : (int)($stat['size'] ?? 0),
            'mtime' => (int)($stat['mtime'] ?? time()),
            'created_at' => (int)($stat['ctime'] ?? time()),
            'status' => 'active',
        ];
        return self::record($appKey, $userId, $rootRelative, $relativePath, $meta);
    }

    /** Keep ids stable while renaming/moving an object and its subtree. */
    public static function recordMove(string $appKey, string $userId, string $rootRelative, string $oldPath, string $newPath): int
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $oldPath = self::normalizeRelative($oldPath);
        $newPath = self::normalizeRelative($newPath);
        self::assertInsideRoot($rootRelative, $oldPath);
        self::assertInsideRoot($rootRelative, $newPath);
        if ($oldPath === $newPath) return 0;
        if ($newPath === $oldPath || str_starts_with($newPath . '/', $oldPath . '/')) {
            throw new RuntimeException('cannot_move_into_itself');
        }
        $parentId = self::ensureParentChain($appKey, $userId, $rootRelative, $newPath);
        return FileIndexDb::moveSubtree($userId, $appKey, $oldPath, $newPath, $parentId);
    }

    /** Soft-delete an object and its descendants (files stay on disk until purged). */
    public static function recordDelete(string $appKey, string $userId, string $rootRelative, string $relativePath, string $status = 'deleted'): int
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $relativePath = self::normalizeRelative($relativePath);
        self::assertInsideRoot($rootRelative, $relativePath);
        if ($relativePath === $rootRelative) throw new RuntimeException('cannot_delete_root');
        return FileIndexDb::markSubtreeDeleted($userId, $appKey, $relativePath, $status);
    }

    /** Restore a soft-deleted object (after a trash restore). */
    public static function recordRestore(string $appKey, string $userId, string $rootRelative, string $relativePath): int
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $relativePath = self::normalizeRelative($relativePath);
        self::assertInsideRoot($rootRelative, $relativePath);
        $row = self::findByPath($appKey, $userId, $relativePath, true);
        if ($row === null) return self::recordFromFilesystem($appKey, $userId, $rootRelative, $relativePath) ?? 0;
        FileIndexDb::restoreSubtree($userId, $appKey, $relativePath);
        return (int)$row['id'];
    }

    /** Remove rows permanently (after the physical delete). */
    public static function forget(string $appKey, string $userId, string $rootRelative, string $relativePath): int
    {
        self::assertInsideRoot(self::normalizeRelative($rootRelative), self::normalizeRelative($relativePath));
        return FileIndexDb::deleteSubtree($userId, $appKey, self::normalizeRelative($relativePath));
    }

    /**
     * Persist an operation that must also reach external consumers. The event
     * is stored in PostgreSQL before any worker sees it; Redis only accelerates
     * delivery, so a restart cannot lose metadata.
     *
     * @param array<string,mixed> $payload
     */
    public static function publish(string $appKey, string $userId, string $eventType, array $payload = []): void
    {
        $key = hash('sha256', $appKey . '|' . $userId . '|' . $eventType . '|' . json_encode($payload, JSON_UNESCAPED_SLASHES));
        FileIndexDb::enqueue($key, $appKey, $userId, $eventType, $payload);
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------- */

    private static function ensureParentChain(string $appKey, string $userId, string $rootRelative, string $relativePath): ?int
    {
        if ($relativePath === $rootRelative) return null;
        $parentRel = self::normalizeRelative(dirname($relativePath));
        $parentRel = $parentRel === '.' ? '' : $parentRel;
        if ($parentRel === '' || $parentRel === $rootRelative) {
            $root = self::ensureRoot($appKey, $userId, $rootRelative);
            return (int)$root['id'];
        }
        $existing = self::findByPath($appKey, $userId, $parentRel, true);
        if ($existing !== null) return (int)$existing['id'];
        // Index-only parent: physical directory existence is the caller's job.
        $grandParentId = self::ensureParentChain($appKey, $userId, $rootRelative, $parentRel);
        FileIndexDb::upsert($userId, $appKey, $parentRel, [
            'name' => basename($parentRel),
            'object_type' => 'directory',
            'mime_type' => 'inode/directory',
            'parent_id' => $grandParentId,
            'status' => 'active',
        ]);
        $row = self::findByPath($appKey, $userId, $parentRel, true);
        return $row !== null ? (int)$row['id'] : null;
    }

    /**
     * Determine the physical disk holding an object. Works on both the merged
     * pool and per-disk branches; never guesses from /storage alone.
     */
    public static function diskIdFor(string $appKey, string $relativePath): ?int
    {
        $rel = self::normalizeRelative($relativePath);
        $app = preg_replace('/[^a-zA-Z0-9_\-]/', '', $appKey);
        if ($app === '') return null;
        $suffix = '/applications/' . $app . '/' . $rel;
        try {
            $disks = StorageDb::disks(false);
        } catch (Throwable $e) {
            return null;
        }
        foreach ($disks as $disk) {
            if ((string)$disk['status'] !== 'online') continue;
            $mount = rtrim((string)$disk['mount_path'], '/');
            if ($mount === '') continue;
            $candidate = $mount . $suffix;
            clearstatcache(true, $candidate);
            if (file_exists($candidate) || is_link($candidate)) return (int)$disk['id'];
        }
        // The app may live outside the pool (legacy paths or tests); use the
        // actual app base path to find the mount that owns it.
        $base = rtrim(self::appBaseDir($appKey), '/');
        $full = $base . '/' . $rel;
        clearstatcache(true, $full);
        if (!file_exists($full)) return null;
        $real = realpath($full);
        if ($real === false) return null;
        foreach ($disks as $disk) {
            $mountReal = realpath((string)$disk['mount_path']);
            if ($mountReal === false) continue;
            if ($real === $mountReal || str_starts_with($real, $mountReal . DIRECTORY_SEPARATOR)) {
                return (int)$disk['id'];
            }
        }
        return null;
    }

    /** @return array<string,mixed> */
    public static function status(): array
    {
        $stats = FileIndexDb::stats();
        $outbox = FileIndexDb::outboxCounts();
        $jobs = FileIndexDb::jobs(20);
        $lastIndex = (int)FileIndexDb::metaGet('last_index_completed_at', '0');
        $lastReconcile = (int)FileIndexDb::metaGet('last_reconcile_completed_at', '0');
        return [
            'enabled' => self::isEnabled(),
            'files' => (int)$stats['files'],
            'directories' => (int)$stats['directories'],
            'users' => (int)$stats['users'],
            'apps' => (int)$stats['apps'],
            'bytes' => (int)$stats['bytes'],
            'deleted' => (int)$stats['deleted'],
            'missing' => (int)$stats['missing'],
            'by_app' => FileIndexDb::statsByApp(),
            'by_disk' => FileIndexDb::statsByDisk(),
            'outbox' => $outbox,
            'pending_events' => (int)$outbox['pending'] + (int)$outbox['processing'],
            'failed_events' => (int)$outbox['failed'],
            'jobs' => $jobs,
            'active_jobs' => count(array_filter($jobs, static fn($j) => in_array((string)$j['status'], ['pending', 'running'], true))),
            'last_index_at' => $lastIndex,
            'last_reconcile_at' => $lastReconcile,
            'database' => 'postgresql',
            'generated_at' => time(),
        ];
    }

    /** Queue an initial index job for one app+user root. */
    public static function enqueueInitialIndex(string $appKey, string $userId, string $rootRelative, ?int $diskId = null): int
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $key = 'initial:' . $appKey . ':' . $userId . ':' . $rootRelative;
        return FileIndexDb::enqueueJob($key, $appKey, $userId, $rootRelative, 'initial', $diskId, ['root_relative' => $rootRelative]);
    }

    /** Queue a background reconciliation (optional disk scoping). */
    public static function enqueueReconcile(string $appKey, string $userId, string $rootRelative, ?int $diskId = null): int
    {
        $rootRelative = self::normalizeRelative($rootRelative);
        $key = 'reconcile:' . $appKey . ':' . $userId . ':' . $rootRelative . ':' . (int)$diskId;
        return FileIndexDb::enqueueJob($key, $appKey, $userId, $rootRelative, 'reconcile', $diskId, ['root_relative' => $rootRelative]);
    }
}
