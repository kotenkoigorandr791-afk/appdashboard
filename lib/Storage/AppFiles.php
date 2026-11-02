<?php
/**
 * AppFiles — the single file API every sub-application talks to.
 *
 * Applications must not scan directories, stat arbitrary paths or build
 * listings themselves. They ask AppFiles, which answers from the central
 * PostgreSQL file index and performs real filesystem work only for the one
 * object being read or written.
 *
 * Two ownership modes are supported:
 *   - user mode   (cloud, steps, spends, smart_home): the owner is the user and
 *     the root is the user's directory inside the app.
 *   - folder mode (films, recipes): the owner is the top-level folder of the
 *     app directory ("uploads", "customers", "products", ...).
 *
 * @see FileIndex
 * @see FileIndexWorker::indexDirectory() the only place a scan may happen
 */
declare(strict_types=1);

require_once __DIR__ . '/FileIndex.php';
require_once __DIR__ . '/FileIndexWorker.php';

final class AppFiles
{
    /* ------------------------------------------------------------------
     * Roots
     * ---------------------------------------------------------------- */

    /** Relative root of a user inside an application, honouring root keys. */
    public static function userRootRelative(string $appKey, string $user, string $rootKey = 'main'): string
    {
        $user = FileIndex::sanitizeUser($user);
        $sub = '';
        if ($rootKey !== 'main' && function_exists('appdata_root_subdir')) {
            $sub = (string)appdata_root_subdir($rootKey);
        }
        $rel = $sub === '' ? $user : $user . '/' . ltrim($sub, '/');
        return FileIndex::normalizeRelative($rel);
    }

    /** Folder (shared app space) root derived from an app-relative path. */
    public static function folderRoot(string $relativePath): string
    {
        $rel = FileIndex::normalizeRelative($relativePath);
        if ($rel === '') throw new RuntimeException('invalid_root');
        return (string)explode('/', $rel)[0];
    }

    /**
     * Ensure the physical directory and its index row exist.
     *
     * @return array<string,mixed> the indexed root row
     */
    public static function ensureRoot(string $appKey, string $owner, string $rootRelative): array
    {
        $rootRelative = FileIndex::normalizeRelative($rootRelative);
        if ($rootRelative === '') throw new RuntimeException('invalid_root');
        $physical = FileIndex::physicalPath($appKey, $rootRelative);
        if (!is_dir($physical)) @mkdir($physical, 0775, true);
        return FileIndex::ensureRoot($appKey, $owner, $rootRelative);
    }

    /* ------------------------------------------------------------------
     * Listings
     * ---------------------------------------------------------------- */

    /**
     * Children of a directory, answered from the index.
     *
     * @param array{limit?:int,after_name?:string,after_id?:int,include_hidden?:bool,index_ttl?:int} $opts
     * @return array{path:string,entries:list<array<string,mixed>>,next:?array{name:string,id:int},has_more:bool}
     */
    public static function listByRoot(string $appKey, string $owner, string $rootRelative, string $path = '', array $opts = []): array
    {
        $rootRelative = FileIndex::normalizeRelative($rootRelative);
        $path = FileIndex::normalizeRelative($path);
        self::ensureRoot($appKey, $owner, $rootRelative);
        FileIndex::ensureIndexed($appKey, $owner, $rootRelative, $path, (int)($opts['index_ttl'] ?? 0));

        $result = FileIndex::listDirectory($appKey, $owner, $rootRelative, $path, [
            'limit' => (int)($opts['limit'] ?? 200),
            'after_name' => $opts['after_name'] ?? null,
            'after_id' => (int)($opts['after_id'] ?? 0),
        ]);
        $entries = [];
        $wantHidden = !empty($opts['include_hidden']);
        foreach ($result['items'] as $row) {
            $name = (string)$row['name'];
            if (!$wantHidden && str_starts_with($name, '.')) continue;
            $entries[] = self::entry($row, $path);
        }
        return [
            'path' => $path,
            'entries' => $entries,
            'next' => $result['next'],
            'has_more' => (bool)$result['has_more'],
        ];
    }

    /** User-scoped listing (the common case). */
    public static function listUser(string $appKey, string $user, string $rootKey = 'main', string $path = '', array $opts = []): array
    {
        $root = self::userRootRelative($appKey, $user, $rootKey);
        return self::listByRoot($appKey, FileIndex::sanitizeUser($user), $root, $path, $opts);
    }

    /** Folder-mode listing (shared app space, e.g. films/uploads). */
    public static function listFolder(string $appKey, string $folderRelative, array $opts = []): array
    {
        $folderRelative = FileIndex::normalizeRelative($folderRelative);
        $root = self::folderRoot($folderRelative);
        $path = $folderRelative === $root ? '' : substr($folderRelative, strlen($root) + 1);
        return self::listByRoot($appKey, $root, $root, $path, $opts);
    }

    /**
     * Recursive listing below a directory, answered from the index.
     *
     * @return list<array<string,mixed>>
     */
    public static function treeByRoot(string $appKey, string $owner, string $rootRelative, string $path = '', int $limit = 500, int $offset = 0): array
    {
        $rootRelative = FileIndex::normalizeRelative($rootRelative);
        $path = FileIndex::normalizeRelative($path);
        self::ensureRoot($appKey, $owner, $rootRelative);
        FileIndex::ensureIndexed($appKey, $owner, $rootRelative, $path);
        $relativeTo = $path === '' ? $rootRelative : FileIndex::normalizeRelative($rootRelative . '/' . $path);
        $out = [];
        foreach (FileIndex::listRecursive($appKey, $owner, $rootRelative, $path, $limit, $offset) as $row) {
            $out[] = self::entry($row, $relativeTo);
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public static function treeUser(string $appKey, string $user, string $rootKey = 'main', string $path = '', int $limit = 500, int $offset = 0): array
    {
        $root = self::userRootRelative($appKey, $user, $rootKey);
        return self::treeByRoot($appKey, FileIndex::sanitizeUser($user), $root, $path, $limit, $offset);
    }

    /** Normalise an index row for application consumers. */
    public static function entry(array $row, string $relativeTo = ''): array
    {
        $rel = (string)$row['relative_path'];
        $prefix = $relativeTo === '' ? '' : rtrim($relativeTo, '/') . '/';
        $path = ($prefix !== '' && str_starts_with($rel, $prefix)) ? substr($rel, strlen($prefix)) : $rel;
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'path' => $path,
            'relative_path' => $rel,
            'type' => (string)$row['object_type'] === 'directory' ? 'dir' : 'file',
            'size' => (string)$row['object_type'] === 'directory' ? null : (int)$row['size_bytes'],
            'mtime' => (int)$row['mtime'],
            'mime' => (string)$row['mime_type'],
            'extension' => (string)$row['extension'],
            'status' => (string)$row['status'],
        ];
    }

    /* ------------------------------------------------------------------
     * Lookups
     * ---------------------------------------------------------------- */

    /**
     * Index row for a path, without filesystem probing.
     *
     * When the row is unknown the parent level is indexed once (the single scan
     * lives in FileIndexWorker), so legacy data becomes visible on first use.
     *
     * @return array<string,mixed>|null
     */
    public static function find(string $appKey, string $owner, string $rootRelative, string $relativePath): ?array
    {
        $rel = self::fullPath($rootRelative, $relativePath);
        $row = FileIndex::findByPath($appKey, $owner, $rel, true);
        if ($row !== null) return $row;
        $parent = dirname($rel);
        if ($parent === '.' || $parent === '' || $parent === $rel) return null;
        try {
            FileIndex::ensureIndexed($appKey, $owner, $rootRelative, self::relativeToRoot($rootRelative, $parent));
        } catch (Throwable $e) {
            return null;
        }
        return FileIndex::findByPath($appKey, $owner, $rel, true);
    }

    /** Whether the index knows an active object at this app-relative path. */
    public static function existsAt(string $appKey, string $owner, string $rootRelative, string $relativePath): bool
    {
        $rootRelative = FileIndex::normalizeRelative($rootRelative);
        $relativePath = FileIndex::normalizeRelative($relativePath);
        if ($relativePath === $rootRelative) return true;
        $row = self::find($appKey, $owner, $rootRelative, $relativePath);
        return $row !== null && (string)$row['status'] === 'active';
    }

    /**
     * Validated physical path of an indexed object. Throws when the row is
     * missing, belongs to another owner/app, or the disk is offline.
     */
    public static function absolute(string $appKey, string $owner, string $rootRelative, string $relativePath): string
    {
        $row = self::find($appKey, $owner, $rootRelative, $relativePath);
        if ($row === null || (string)$row['status'] !== 'active') throw new RuntimeException('file_not_found');
        return FileIndex::resolvePhysicalPath((int)$row['id'], $appKey, $owner, $rootRelative, (string)$row['object_type'] !== 'directory');
    }

    /** User-scoped absolute path convenience wrapper. */
    public static function absoluteUser(string $appKey, string $user, string $rootKey, string $relativePath): string
    {
        $root = self::userRootRelative($appKey, $user, $rootKey);
        return self::absolute($appKey, FileIndex::sanitizeUser($user), $root, $relativePath);
    }

    /* ------------------------------------------------------------------
     * Mutations — call after the real filesystem operation succeeded
     * ---------------------------------------------------------------- */

    public static function recordWrite(string $appKey, string $owner, string $rootRelative, string $relativePath, array $meta = []): int
    {
        return FileIndex::recordFile($appKey, $owner, $rootRelative, $relativePath, $meta);
    }

    public static function recordDirectory(string $appKey, string $owner, string $rootRelative, string $relativePath, array $meta = []): int
    {
        return FileIndex::recordDirectory($appKey, $owner, $rootRelative, $relativePath, $meta);
    }

    public static function recordDelete(string $appKey, string $owner, string $rootRelative, string $relativePath, string $status = 'deleted'): int
    {
        return FileIndex::recordDelete($appKey, $owner, $rootRelative, $relativePath, $status);
    }

    public static function recordMove(string $appKey, string $owner, string $rootRelative, string $oldPath, string $newPath): int
    {
        return FileIndex::recordMove($appKey, $owner, $rootRelative, $oldPath, $newPath);
    }

    /** Re-read metadata of a known object from disk (never scans a directory). */
    public static function refresh(string $appKey, string $owner, string $rootRelative, string $relativePath): ?int
    {
        return FileIndex::recordFromFilesystem($appKey, $owner, $rootRelative, $relativePath);
    }

    /** Queue a full background index pass for a root. */
    public static function enqueueIndex(string $appKey, string $owner, string $rootRelative): int
    {
        return FileIndex::enqueueInitialIndex($appKey, $owner, $rootRelative);
    }

    /**
     * Normalise a path and make sure it is expressed relative to the app base
     * (callers may pass either "alice/docs/x" or "docs/x" with root "alice").
     */
    public static function fullPath(string $rootRelative, string $relativePath): string
    {
        $root = FileIndex::normalizeRelative($rootRelative);
        $rel = FileIndex::normalizeRelative($relativePath);
        if ($root !== '' && $rel !== $root && !str_starts_with($rel, $root . '/')) {
            $rel = FileIndex::normalizeRelative($root . '/' . $rel);
        }
        return $rel;
    }

    /** Strip the root prefix from a path so it is relative to the listed dir. */
    private static function relativeToRoot(string $rootRelative, string $path): string
    {
        $rootRelative = rtrim($rootRelative, '/');
        $path = rtrim($path, '/');
        if ($path === $rootRelative) return '';
        if (str_starts_with($path, $rootRelative . '/')) return substr($path, strlen($rootRelative) + 1);
        return $path;
    }
}
