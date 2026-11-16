<?php
/**
 * Background worker for the central file index.
 *
 * Responsibilities:
 *  - initial indexing and reconciliation, resumable and batched through
 *    storage_index_queue (one directory per step);
 *  - Transactional Outbox delivery, so a crash between a filesystem operation
 *    and its index update can be repaired without scanning a directory;
 *  - discovery of user roots on startup (background only — never from HTTP).
 *
 * Directory listing in this file is legitimate: it is the indexing engine.
 */
declare(strict_types=1);

require_once __DIR__ . '/FileIndex.php';

final class FileIndexWorker
{
    /** @return array<string,mixed> */
    public static function runNextJob(int $maxDirectories = 25): array
    {
        $job = FileIndexDb::nextJob(['initial', 'reconcile']);
        if ($job === null) return ['ok' => true, 'action' => 'idle'];
        return self::processJob((int)$job['id'], $maxDirectories);
    }

    /** @return array<string,mixed> */
    public static function processJob(int $jobId, int $maxDirectories = 25): array
    {
        $job = self::jobById($jobId);
        if ($job === null) return ['ok' => false, 'error' => 'job_not_found'];
        $status = (string)$job['status'];
        if (in_array($status, ['completed', 'failed'], true)) return ['ok' => true, 'action' => 'finished'];

        $rootRelative = FileIndex::normalizeRelative((string)$job['root_path']);
        $appKey = (string)$job['app_key'];
        $userId = (string)$job['user_id'];
        if ($rootRelative === '') return self::failJob($job, 'invalid_root');

        $physicalBase = FileIndex::physicalPath($appKey, $rootRelative);
        if (!is_dir($physicalBase)) {
            // The directory was removed (deleted user, unmounted disk). Mark the
            // job as skipped instead of failed so it is not retried forever.
            FileIndexDb::updateJob($jobId, [
                'status' => 'completed',
                'finished_at' => time(),
                'last_error' => 'root_not_available',
            ]);
            return ['ok' => true, 'action' => 'skipped', 'job_id' => $jobId, 'reason' => 'root_not_available'];
        }

        $started = (int)$job['started_at'];
        if ($started <= 0) {
            $started = time();
            FileIndexDb::updateJob($jobId, ['status' => 'running', 'started_at' => $started, 'heartbeat_at' => time()]);
        }
        if (FileIndexDb::pendingQueueCount($jobId) === 0) {
            // A fresh/resumed job always starts from its root directory row.
            FileIndex::ensureRoot($appKey, $userId, $rootRelative);
            FileIndexDb::enqueueQueueItem($jobId, $rootRelative);
        }

        $files = 0;
        $dirs = 0;
        $bytes = 0;
        $errors = 0;
        $processedDirs = 0;

        // New directories are appended to the queue while we scan, so keep
        // draining until the per-tick budget is exhausted.
        while ($processedDirs < $maxDirectories) {
            $items = FileIndexDb::pendingQueueItems($jobId, min(10, $maxDirectories - $processedDirs));
            if ($items === []) break;
            foreach ($items as $item) {
                $dirRel = FileIndex::normalizeRelative((string)$item['relative_path']);
                try {
                    [$f, $d, $b, $err] = self::scanDirectory($job, $dirRel);
                    $files += $f;
                    $dirs += $d;
                    $bytes += $b;
                    $errors += $err;
                } catch (Throwable $e) {
                    $errors++;
                    FileIndexDb::updateJob($jobId, [
                        'last_error' => substr($e->getMessage(), 0, 4000),
                        'error_count' => (int)$job['error_count'] + $errors,
                    ]);
                }
                FileIndexDb::completeQueueItem((int)$item['id']);
                $processedDirs++;
                if ($processedDirs >= $maxDirectories) break;
            }
        }

        $scannedFiles = (int)$job['scanned_files'] + $files;
        $scannedDirs = (int)$job['scanned_directories'] + $dirs;
        $scannedBytes = (int)$job['scanned_bytes'] + $bytes;
        $fields = [
            'scanned_files' => $scannedFiles,
            'scanned_directories' => $scannedDirs,
            'scanned_bytes' => $scannedBytes,
            'heartbeat_at' => time(),
        ];
        if ($errors > 0) {
            $fields['error_count'] = (int)$job['error_count'] + $errors;
        }
        FileIndexDb::updateJob($jobId, $fields);

        if (FileIndexDb::pendingQueueCount($jobId) > 0) {
            return ['ok' => true, 'action' => 'progress', 'job_id' => $jobId, 'files' => $files, 'directories' => $dirs];
        }

        // The scan covered the whole root. Objects that the scan never touched
        // are gone from the filesystem: flag them instead of deleting rows.
        $marked = 0;
        if ($errors === 0 && is_dir($physicalBase)) {
            $marked = FileIndexDb::markUnseenUnderRoot($userId, $appKey, $rootRelative, $started);
        }
        $mode = (string)$job['mode'];
        FileIndexDb::updateJob($jobId, [
            'status' => 'completed',
            'finished_at' => time(),
            'last_error' => $errors > 0 ? 'completed_with_errors' : '',
        ]);
        FileIndexDb::clearQueue($jobId);
        FileIndexDb::metaSet($mode === 'reconcile' ? 'last_reconcile_completed_at' : 'last_index_completed_at', (string)time());
        return [
            'ok' => true,
            'action' => 'completed',
            'job_id' => $jobId,
            'files' => $files,
            'directories' => $dirs,
            'missing_marked' => $marked,
        ];
    }

    /**
     * Index one directory. scandir() is used deliberately here: this is the
     * background indexer, not a user request.
     *
     * @return array{0:int,1:int,2:int,3:int} files, dirs, bytes, errors
     */
    private static function scanDirectory(array $job, string $dirRel): array
    {
        $appKey = (string)$job['app_key'];
        $userId = (string)$job['user_id'];
        $rootRelative = FileIndex::normalizeRelative((string)$job['root_path']);
        $jobDiskId = isset($job['disk_id']) && $job['disk_id'] !== null ? (int)$job['disk_id'] : 0;
        $physical = FileIndex::physicalPath($appKey, $dirRel);
        $parentRow = FileIndex::findByPath($appKey, $userId, $dirRel, true);
        $parentId = $parentRow !== null ? (int)$parentRow['id'] : (int)FileIndex::ensureRoot($appKey, $userId, $rootRelative)['id'];
        $entries = @scandir($physical);
        if (!is_array($entries)) throw new RuntimeException('cannot_read_directory:' . $dirRel);

        $files = 0;
        $dirs = 0;
        $bytes = 0;
        $errors = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $childRel = FileIndex::normalizeRelative($dirRel . '/' . $entry);
            $full = $physical . '/' . $entry;
            if (is_link($full)) continue; // never follow symlinks out of the root
            $stat = @lstat($full);
            if ($stat === false) {
                $errors++;
                continue;
            }
            $isDir = is_dir($full);
            $diskId = $jobDiskId > 0 ? $jobDiskId : FileIndex::diskIdFor($appKey, $childRel);
            $meta = [
                'name' => $entry,
                'object_type' => $isDir ? 'directory' : 'file',
                'mime_type' => $isDir ? 'inode/directory' : (function_exists('mime_content_type') ? (string)@mime_content_type($full) : ''),
                'extension' => $isDir ? '' : strtolower(pathinfo($entry, PATHINFO_EXTENSION)),
                'size_bytes' => $isDir ? 0 : (int)$stat['size'],
                'mtime' => (int)$stat['mtime'],
                'created_at' => (int)$stat['ctime'],
                'disk_id' => $diskId,
                'status' => 'active',
            ];
            try {
                FileIndex::recordKnown($appKey, $userId, $rootRelative, $childRel, $parentId, $meta);
            } catch (Throwable $e) {
                $errors++;
                continue;
            }
            if ($isDir) {
                $dirs++;
                FileIndexDb::enqueueQueueItem((int)$job['id'], $childRel);
            } else {
                $files++;
                $bytes += (int)$stat['size'];
            }
        }
        return [$files, $dirs, $bytes, $errors];
    }

    /**
     * Deliver pending outbox events. Handlers are idempotent: replaying an
     * event after a crash converges to the same index state.
     *
     * @return array<string,int>
     */
    public static function processOutbox(int $limit = 100): array
    {
        FileIndexDb::recoverStaleOutbox(300);
        $events = FileIndexDb::claimOutbox($limit);
        $done = 0;
        $failed = 0;
        foreach ($events as $event) {
            try {
                self::applyEvent($event);
                FileIndexDb::completeOutbox((int)$event['id']);
                $done++;
            } catch (Throwable $e) {
                FileIndexDb::failOutbox((int)$event['id'], $e->getMessage(), (int)$event['attempts']);
                $failed++;
            }
        }
        if ($done > 0) FileIndexDb::metaSet('last_outbox_at', (string)time());
        return ['processed' => $done, 'failed' => $failed, 'claimed' => count($events)];
    }

    private static function applyEvent(array $event): void
    {
        $payload = json_decode((string)$event['payload'], true);
        if (!is_array($payload)) $payload = [];
        $appKey = (string)$event['app_key'];
        $userId = (string)$event['user_id'];
        $root = FileIndex::normalizeRelative((string)($payload['root_relative'] ?? FileIndex::userRoot($userId)));
        $type = (string)$event['event_type'];

        switch ($type) {
            case 'record':
            case 'changed':
                $rel = FileIndex::normalizeRelative((string)($payload['relative_path'] ?? ''));
                if ($rel === '') throw new RuntimeException('path_required');
                $row = FileIndex::findByPath($appKey, $userId, $rel, true);
                if (!file_exists(FileIndex::physicalPath($appKey, $rel))) {
                    FileIndex::recordDelete($appKey, $userId, $root, $rel, 'missing');
                    return;
                }
                FileIndex::recordFromFilesystem($appKey, $userId, $root, $rel);
                return;
            case 'move':
                $old = FileIndex::normalizeRelative((string)($payload['old_path'] ?? ''));
                $new = FileIndex::normalizeRelative((string)($payload['new_path'] ?? ''));
                if ($old === '' || $new === '') throw new RuntimeException('path_required');
                $existingNew = FileIndex::findByPath($appKey, $userId, $new, true);
                if ($existingNew !== null) return; // already applied
                FileIndex::recordMove($appKey, $userId, $root, $old, $new);
                return;
            case 'delete':
                $rel = FileIndex::normalizeRelative((string)($payload['relative_path'] ?? ''));
                if ($rel === '') throw new RuntimeException('path_required');
                FileIndex::recordDelete($appKey, $userId, $root, $rel, (string)($payload['status'] ?? 'deleted'));
                return;
            case 'restore':
                $rel = FileIndex::normalizeRelative((string)($payload['relative_path'] ?? ''));
                if ($rel === '') throw new RuntimeException('path_required');
                FileIndex::recordRestore($appKey, $userId, $root, $rel);
                return;
            case 'mkdir':
                $rel = FileIndex::normalizeRelative((string)($payload['relative_path'] ?? ''));
                if ($rel === '') throw new RuntimeException('path_required');
                FileIndex::recordDirectory($appKey, $userId, $root, $rel);
                return;
            case 'copied':
                $rel = FileIndex::normalizeRelative((string)($payload['relative_path'] ?? ''));
                if ($rel === '') throw new RuntimeException('path_required');
                self::indexSubtree($appKey, $userId, $root, $rel);
                return;
            default:
                // Unknown events are acknowledged so they do not block the queue.
                return;
        }
    }

    /**
     * Index exactly one directory level.
     *
     * This is the only place where a user-facing request may cause a directory
     * read: it is bounded to one level, skips symlinks and exists so legacy
     * data stays visible until the background indexer has walked the root.
     * Application code must call AppFiles/FileIndex instead.
     *
     * @return array{files:int,directories:int,errors:int,missing:int,indexed:bool}
     */
    public static function indexDirectory(string $appKey, string $userId, string $rootRelative, string $dirRelative = ''): array
    {
        $rootRelative = FileIndex::normalizeRelative($rootRelative);
        $dirRelative = FileIndex::normalizeRelative($dirRelative === '' ? $rootRelative : $dirRelative);
        FileIndex::assertInsideRoot($rootRelative, $dirRelative);
        $result = ['files' => 0, 'directories' => 0, 'errors' => 0, 'missing' => 0, 'indexed' => false];

        FileIndex::ensureRoot($appKey, $userId, $rootRelative);
        $dirRow = FileIndex::findByPath($appKey, $userId, $dirRelative, true);
        if ($dirRow === null) {
            FileIndex::recordDirectory($appKey, $userId, $rootRelative, $dirRelative);
            $dirRow = FileIndex::findByPath($appKey, $userId, $dirRelative, true);
        }
        $physical = FileIndex::physicalPath($appKey, $dirRelative);
        if ($dirRow === null || !is_dir($physical) || is_link($physical)) {
            return $result;
        }
        $entries = @scandir($physical);
        if (!is_array($entries)) {
            $result['errors']++;
            return $result;
        }

        $parentId = (int)$dirRow['id'];
        $seen = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $full = $physical . '/' . $entry;
            if (is_link($full)) continue;
            $childRel = FileIndex::normalizeRelative($dirRelative . '/' . $entry);
            $stat = @lstat($full);
            if ($stat === false) {
                $result['errors']++;
                continue;
            }
            $isDir = is_dir($full);
            try {
                FileIndex::recordKnown($appKey, $userId, $rootRelative, $childRel, $parentId, [
                    'name' => $entry,
                    'object_type' => $isDir ? 'directory' : 'file',
                    'mime_type' => $isDir ? 'inode/directory' : (function_exists('mime_content_type') ? (string)@mime_content_type($full) : ''),
                    'extension' => $isDir ? '' : strtolower(pathinfo($entry, PATHINFO_EXTENSION)),
                    'size_bytes' => $isDir ? 0 : (int)$stat['size'],
                    'mtime' => (int)$stat['mtime'],
                    'created_at' => (int)$stat['ctime'],
                    'status' => 'active',
                ]);
            } catch (Throwable $e) {
                $result['errors']++;
                continue;
            }
            $seen[] = $childRel;
            if ($isDir) $result['directories']++;
            else $result['files']++;
        }

        $result['missing'] = FileIndexDb::markMissingChildren($userId, $appKey, $parentId, $seen);
        FileIndexDb::mergeMetadata($parentId, ['indexed_at' => time()]);
        $result['indexed'] = true;
        return $result;
    }

    /**
     * Index an already-written subtree (used after a copy operation). Runs in
     * the worker; bounded memory via an explicit stack.
     */
    public static function indexSubtree(string $appKey, string $userId, string $rootRelative, string $startPath, int $maxEntries = 200000): int
    {
        $rootRelative = FileIndex::normalizeRelative($rootRelative);
        $startPath = FileIndex::normalizeRelative($startPath);
        FileIndex::assertInsideRoot($rootRelative, $startPath);
        $stack = [$startPath];
        $count = 0;
        while ($stack !== [] && $count < $maxEntries) {
            $dirRel = array_pop($stack);
            $physical = FileIndex::physicalPath($appKey, $dirRel);
            $entries = @scandir($physical);
            if (!is_array($entries)) continue;
            if ($dirRel !== $startPath) {
                FileIndex::recordDirectory($appKey, $userId, $rootRelative, $dirRel, ['mtime' => (int)@filemtime($physical)]);
                $count++;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $childRel = FileIndex::normalizeRelative($dirRel . '/' . $entry);
                $full = $physical . '/' . $entry;
                if (is_link($full)) continue;
                if (is_dir($full)) {
                    $stack[] = $childRel;
                    continue;
                }
                FileIndex::recordFromFilesystem($appKey, $userId, $rootRelative, $childRel);
                $count++;
                if ($count >= $maxEntries) break;
            }
        }
        return $count;
    }

    /**
     * Enqueue initial indexing for every existing user directory of the given
     * apps. Runs in the background at container start.
     *
     * @param list<string> $appKeys
     * @return array<string,int>
     */
    public static function discoverRoots(array $appKeys = ['cloud']): array
    {
        $queued = 0;
        $skipped = 0;
        foreach ($appKeys as $appKey) {
            $appKey = (string)preg_replace('/[^a-zA-Z0-9_\-]/', '', $appKey);
            if ($appKey === '') continue;
            try {
                $base = FileIndex::appBaseDir($appKey);
            } catch (Throwable $e) {
                continue;
            }
            if (!is_dir($base)) continue;
            $entries = @scandir($base) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $full = $base . '/' . $entry;
                if (!is_dir($full) || is_link($full)) continue;
                $root = FileIndex::normalizeRelative($entry);
                $job = FileIndex::enqueueInitialIndex($appKey, $entry, $root);
                if ($job > 0) $queued++;
            }
        }
        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /** @return array<string,mixed>|null */
    private static function jobById(int $jobId): ?array
    {
        return FileIndexDb::jobById($jobId);
    }

    private static function failJob(array $job, string $error): array
    {
        FileIndexDb::updateJob((int)$job['id'], [
            'status' => 'failed',
            'finished_at' => time(),
            'last_error' => $error,
        ]);
        return ['ok' => false, 'error' => $error, 'job_id' => (int)$job['id']];
    }
}
