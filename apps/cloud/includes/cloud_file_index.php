<?php
/**
 * Cloud <-> central File Index adapter.
 *
 * All cloud listings, searches and metadata reads go through the shared
 * PostgreSQL index. The adapter never scans a directory: physical filesystem
 * access is limited to real write operations performed by the caller.
 */
declare(strict_types=1);

if (!class_exists('FileIndex')) {
    require_once dirname(__DIR__, 3) . '/lib/Storage/FileIndex.php';
}

function cloud_index_enabled(): bool
{
    return FileIndex::isEnabled();
}

/**
 * App-relative root of the current cloud view (main, archive, documents,
 * photos, pdf, trash or a group folder).
 */
function cloud_index_root(string $user, string $rootKey): string
{
    $base = rtrim(appdata_app_base_dir('cloud'), '/');
    $root = rtrim(user_root($user, $rootKey), '/');
    if (!str_starts_with($root, $base . '/')) {
        throw new RuntimeException('cloud_index_outside_app_root');
    }
    return FileIndex::normalizeRelative(substr($root, strlen($base) + 1));
}

/** @return array{root:string,relative:string} */
function cloud_index_paths(string $user, string $rootKey, string $path): array
{
    $root = cloud_index_root($user, $rootKey);
    $path = safe_path($path);
    $relative = $path === '' ? $root : FileIndex::normalizeRelative($root . '/' . $path);
    FileIndex::assertInsideRoot($root, $relative);
    return ['root' => $root, 'relative' => $relative];
}

/** Convert an index row to the cloud `list` item shape. */
function cloud_index_item(array $row, string $root, string $requestedPath): array
{
    $rel = (string)$row['relative_path'];
    $suffix = ($root !== '' && str_starts_with($rel, $root . '/')) ? substr($rel, strlen($root) + 1) : $rel;
    return [
        'name' => (string)$row['name'],
        'path' => $suffix,
        'type' => (string)$row['object_type'] === 'directory' ? 'dir' : 'file',
        'size' => (string)$row['object_type'] === 'directory' ? null : (int)$row['size_bytes'],
        'mtime' => (int)$row['mtime'],
        'id' => (int)$row['id'],
    ];
}

/**
 * List one directory from PostgreSQL.
 *
 * @return array{items:list<array<string,mixed>>,has_more:bool,next:?array,indexing:bool}
 */
function cloud_index_list(
    string $user,
    string $rootKey,
    string $path,
    int $limit = 200,
    ?string $afterName = null,
    ?int $afterId = null
): array {
    $paths = cloud_index_paths($user, $rootKey, $path);
    $root = $paths['root'];
    FileIndex::ensureRoot('cloud', $user, $root);
    $result = FileIndex::listDirectory('cloud', $user, $root, safe_path($path), [
        'limit' => max(1, min(FileIndex::MAX_PAGE, $limit)),
        'after_name' => $afterName,
        'after_id' => $afterId ?? 0,
    ]);
    if ($result['directory'] === null) {
        // Not indexed yet: queue a background job instead of scanning here.
        FileIndex::enqueueInitialIndex('cloud', $user, $root);
        return ['items' => [], 'has_more' => false, 'next' => null, 'indexing' => true];
    }
    $items = [];
    foreach ($result['items'] as $row) {
        $suffix = (string)$row['relative_path'];
        if ($rootKey === 'main' && safe_path($path) === '' && str_starts_with((string)$row['name'], '.')) continue;
        if (preg_match('/\.zip\.[A-Za-z0-9]{6,}$/', (string)$row['name'])) continue;
        $item = cloud_index_item($row, $root, safe_path($path));
        if ($item['path'] === '') continue;
        if (cloud_is_group_root($rootKey)) {
            $checkRel = $item['path'];
            if (cloud_is_hidden_group_tree_path($checkRel)) continue;
        }
        $items[] = $item;
    }
    usort($items, static function ($a, $b) {
        if ($a['type'] !== $b['type']) return $a['type'] === 'dir' ? -1 : 1;
        return strcasecmp((string)$a['name'], (string)$b['name']);
    });
    return [
        'items' => $items,
        'has_more' => (bool)$result['has_more'],
        'next' => $result['next'],
        'indexing' => false,
    ];
}

/** @return list<array<string,mixed>> */
function cloud_index_search(string $user, string $rootKey, string $q, int $limit = 200): array
{
    $root = cloud_index_root($user, $rootKey);
    $rows = FileIndex::search('cloud', $user, $root, $q, max(1, min(FileIndex::MAX_PAGE, $limit)));
    $out = [];
    foreach ($rows as $row) {
        $item = cloud_index_item($row, $root, '');
        if ($rootKey === 'main' && str_starts_with((string)$item['name'], '.')) continue;
        $out[] = $item;
    }
    return $out;
}

/**
 * Flat tree of the user's main space, built from the index (no disk scan).
 * Shape matches the legacy scan_full_tree() output used by the UI cache.
 *
 * @return list<array<string,mixed>>
 */
function cloud_index_tree(string $user, string $rootKey = 'main', int $limit = 200000): array
{
    $root = cloud_index_root($user, $rootKey);
    $rows = FileIndexDb::treeUnderRoot($user, 'cloud', $root, $limit);
    $items = [];
    foreach ($rows as $row) {
        $rel = (string)$row['relative_path'];
        $suffix = ($rel === $root) ? '' : (str_starts_with($rel, $root . '/') ? substr($rel, strlen($root) + 1) : $rel);
        if ($suffix === '') continue;
        if ($rootKey === 'main' && cloud_is_hidden_main_tree_path($suffix)) continue;
        if (cloud_is_hidden_group_tree_path($suffix)) continue;
        $items[] = [
            'name' => (string)$row['name'],
            'path' => $suffix,
            'type' => (string)$row['object_type'] === 'directory' ? 'dir' : 'file',
            'size' => (string)$row['object_type'] === 'directory' ? null : (int)$row['size_bytes'],
            'mtime' => (int)$row['mtime'],
            'id' => (int)$row['id'],
        ];
    }
    return $items;
}

/**
 * Folder properties (size/counts/preview) computed from the index.
 *
 * @return array<string,mixed>|null
 */
function cloud_index_folder_properties(string $user, string $rootKey, string $path, int $limit = 500000): ?array
{
    $paths = cloud_index_paths($user, $rootKey, $path);
    $dir = FileIndex::findByPath('cloud', $user, $paths['relative']);
    if ($dir === null || (string)$dir['object_type'] !== 'directory') return null;
    $rows = FileIndexDb::treeUnderRoot($user, 'cloud', $paths['relative'], $limit);
    $totalSize = 0;
    $fileCount = 0;
    $folderCount = 0;
    $items = [];
    foreach ($rows as $row) {
        if ((string)$row['relative_path'] === $paths['relative']) continue;
        $isDir = (string)$row['object_type'] === 'directory';
        if ($isDir) $folderCount++;
        else { $fileCount++; $totalSize += (int)$row['size_bytes']; }
        if (count($items) < 100) {
            $rel = (string)$row['relative_path'];
            $suffix = str_starts_with($rel, $paths['root'] . '/') ? substr($rel, strlen($paths['root']) + 1) : $rel;
            $items[] = [
                'name' => (string)$row['name'],
                'path' => $suffix,
                'type' => $isDir ? 'dir' : 'file',
                'size' => $isDir ? null : (int)$row['size_bytes'],
                'modified' => (int)$row['mtime'],
            ];
        }
    }
    return [
        'type' => 'directory',
        'size' => $totalSize,
        'items' => $fileCount + $folderCount,
        'files' => $fileCount,
        'folders' => $folderCount,
        'created' => date('c', (int)$dir['created_at']),
        'modified' => date('c', (int)$dir['mtime']),
        'items_preview' => $items,
        'indexed' => true,
    ];
}

/**
 * Persist the flat tree in the legacy cache table for compatibility and
 * return it. The index remains the source of truth.
 *
 * @return array{items:list<array<string,mixed>>,hash:string}
 */
function cloud_index_tree_cache($db, string $email, string $user, string $rootKey = 'main'): array
{
    $items = cloud_index_tree($user, $rootKey);
    $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) $json = '[]';
    $hash = md5($json);
    try {
        $check = $db->prepare('SELECT COUNT(*) AS cnt FROM cloud_folder_state WHERE email = :e');
        $check->bindValue(':e', $email, PDO::PARAM_STR);
        $cnt = (int)($check->execute()->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        if ($cnt === 0) {
            $stmt = $db->prepare("INSERT INTO cloud_folder_state (email, folder_json, tree_json, tree_hash, tree_valid, updated_at) VALUES (:e, '[]', :j, :h, 1, :t)");
        } else {
            $stmt = $db->prepare('UPDATE cloud_folder_state SET tree_json = :j, tree_hash = :h, tree_valid = 1, updated_at = :t WHERE email = :e');
        }
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $stmt->bindValue(':j', $json, PDO::PARAM_STR);
        $stmt->bindValue(':h', $hash, PDO::PARAM_STR);
        $stmt->bindValue(':t', time(), PDO::PARAM_INT);
        $stmt->execute();
    } catch (Throwable $e) {
        cloud_index_log('tree cache: ' . $e->getMessage());
    }
    return ['items' => $items, 'hash' => $hash];
}

/**
 * Folder totals for the storage treemap, aggregated from the index.
 *
 * @return array{root_total_bytes:int,root_files_count:int,root_subfolders_count:int,folders:list<array<string,mixed>>}
 */
function cloud_index_usage(string $user, int $limit = 500000): array
{
    $root = cloud_index_root($user, 'main');
    $rows = FileIndexDb::treeUnderRoot($user, 'cloud', $root, $limit);
    $folders = ['' => [
        'path' => '/', 'name' => '/', 'size_bytes' => 0, 'files_count' => 0,
        'subfolders_count' => 0, 'children_preview' => [], 'depth' => 0,
    ]];
    $sizes = [];
    $fileCounts = [];
    $subCounts = [];
    $children = [];
    $filesTotal = 0;
    $bytesTotal = 0;
    foreach ($rows as $row) {
        $rel = (string)$row['relative_path'];
        $suffix = ($rel === $root) ? '' : (str_starts_with($rel, $root . '/') ? substr($rel, strlen($root) + 1) : $rel);
        if ($suffix === '') continue;
        $parts = explode('/', $suffix);
        $parent = count($parts) > 1 ? implode('/', array_slice($parts, 0, -1)) : '';
        if (count($children[$parent] ?? []) < 8) $children[$parent][] = (string)$row['name'];

        if ((string)$row['object_type'] === 'directory') {
            $folders[$suffix] = $folders[$suffix] ?? [
                'path' => $suffix, 'name' => (string)$row['name'], 'size_bytes' => 0, 'files_count' => 0,
                'subfolders_count' => 0, 'children_preview' => [], 'depth' => max(0, count($parts) - 1),
            ];
            // Every ancestor, including the root, gains one subfolder.
            for ($i = 0; $i < count($parts); $i++) {
                $ancestor = implode('/', array_slice($parts, 0, $i));
                $subCounts[$ancestor] = ($subCounts[$ancestor] ?? 0) + 1;
            }
            continue;
        }
        $size = (int)$row['size_bytes'];
        $bytesTotal += $size;
        $filesTotal++;
        // Every ancestor folder (and the root) gains this file's bytes.
        for ($i = 0; $i < count($parts); $i++) {
            $ancestor = implode('/', array_slice($parts, 0, $i));
            $sizes[$ancestor] = ($sizes[$ancestor] ?? 0) + $size;
            $fileCounts[$ancestor] = ($fileCounts[$ancestor] ?? 0) + 1;
        }
    }
    foreach ($folders as $path => &$folder) {
        $folder['size_bytes'] = (int)($sizes[$path] ?? 0);
        $folder['files_count'] = (int)($fileCounts[$path] ?? 0);
        $folder['subfolders_count'] = (int)($subCounts[$path] ?? 0);
        $folder['children_preview'] = array_slice($children[$path] ?? [], 0, 8);
    }
    unset($folder);
    $out = array_values($folders);
    usort($out, static fn($a, $b) => ((int)$b['size_bytes']) <=> ((int)$a['size_bytes']));
    if (count($out) > 1500) $out = array_slice($out, 0, 1500);
    return [
        'root_total_bytes' => $bytesTotal,
        'root_files_count' => $filesTotal,
        'root_subfolders_count' => count($folders) - 1,
        'folders' => $out,
    ];
}

/** Record a known file (after upload/save). */
function cloud_index_touch_file(string $user, string $rootKey, string $path): void
{
    try {
        $paths = cloud_index_paths($user, $rootKey, $path);
        if (!file_exists(FileIndex::physicalPath('cloud', $paths['relative']))) return;
        FileIndex::recordFromFilesystem('cloud', $user, $paths['root'], $paths['relative']);
        cloud_index_publish($user, $rootKey, 'record', ['root_relative' => $paths['root'], 'relative_path' => $paths['relative']]);
    } catch (Throwable $e) {
        cloud_index_log('touch_file ' . $path . ': ' . $e->getMessage());
    }
}

/** Record a known directory (after mkdir). */
function cloud_index_touch_dir(string $user, string $rootKey, string $path): void
{
    try {
        $paths = cloud_index_paths($user, $rootKey, $path);
        FileIndex::recordDirectory('cloud', $user, $paths['root'], $paths['relative'], [
            'mtime' => (int)@filemtime(FileIndex::physicalPath('cloud', $paths['relative'])),
        ]);
        cloud_index_publish($user, $rootKey, 'mkdir', ['root_relative' => $paths['root'], 'relative_path' => $paths['relative']]);
    } catch (Throwable $e) {
        cloud_index_log('touch_dir ' . $path . ': ' . $e->getMessage());
    }
}

function cloud_index_touch_move(string $user, string $rootKey, string $oldPath, string $newPath, ?string $newRootKey = null): void
{
    $newRootKey = $newRootKey ?? $rootKey;
    try {
        if ($newRootKey === $rootKey) {
            $paths = cloud_index_paths($user, $rootKey, '');
            $oldRel = FileIndex::normalizeRelative($paths['root'] . '/' . safe_path($oldPath));
            $newRel = FileIndex::normalizeRelative($paths['root'] . '/' . safe_path($newPath));
            FileIndex::recordMove('cloud', $user, $paths['root'], $oldRel, $newRel);
            cloud_index_publish($user, $rootKey, 'move', ['root_relative' => $paths['root'], 'old_path' => $oldRel, 'new_path' => $newRel]);
            return;
        }
        // Cross-root move: old root loses the object, new root gains it.
        $oldPaths = cloud_index_paths($user, $rootKey, $oldPath);
        FileIndex::recordDelete('cloud', $user, $oldPaths['root'], $oldPaths['relative']);
        cloud_index_publish($user, $rootKey, 'delete', ['root_relative' => $oldPaths['root'], 'relative_path' => $oldPaths['relative']]);
        $newPaths = cloud_index_paths($user, $newRootKey, $newPath);
        if (file_exists(FileIndex::physicalPath('cloud', $newPaths['relative']))) {
            FileIndex::recordFromFilesystem('cloud', $user, $newPaths['root'], $newPaths['relative']);
            cloud_index_publish($user, $newRootKey, 'record', ['root_relative' => $newPaths['root'], 'relative_path' => $newPaths['relative']]);
        }
    } catch (Throwable $e) {
        cloud_index_log('move ' . $oldPath . ': ' . $e->getMessage());
    }
}

function cloud_index_touch_delete(string $user, string $rootKey, string $path, string $status = 'deleted'): void
{
    try {
        $paths = cloud_index_paths($user, $rootKey, $path);
        FileIndex::recordDelete('cloud', $user, $paths['root'], $paths['relative'], $status);
        cloud_index_publish($user, $rootKey, 'delete', ['root_relative' => $paths['root'], 'relative_path' => $paths['relative'], 'status' => $status]);
    } catch (Throwable $e) {
        cloud_index_log('delete ' . $path . ': ' . $e->getMessage());
    }
}

function cloud_index_touch_restore(string $user, string $rootKey, string $path): void
{
    try {
        $paths = cloud_index_paths($user, $rootKey, $path);
        if (!file_exists(FileIndex::physicalPath('cloud', $paths['relative']))) return;
        FileIndex::recordRestore('cloud', $user, $paths['root'], $paths['relative']);
        if (is_dir(FileIndex::physicalPath('cloud', $paths['relative']))) {
            FileIndex::recordDirectory('cloud', $user, $paths['root'], $paths['relative']);
        } else {
            FileIndex::recordFromFilesystem('cloud', $user, $paths['root'], $paths['relative']);
        }
        cloud_index_publish($user, $rootKey, 'restore', ['root_relative' => $paths['root'], 'relative_path' => $paths['relative']]);
    } catch (Throwable $e) {
        cloud_index_log('restore ' . $path . ': ' . $e->getMessage());
    }
}

/**
 * The caller copied a directory tree (a real write operation). The index is
 * updated by a background job, never by scanning during this request.
 */
function cloud_index_queue_copied_tree(string $user, string $rootKey, string $path): void
{
    try {
        $paths = cloud_index_paths($user, $rootKey, $path);
        FileIndex::publish('cloud', $user, 'copied', [
            'root_relative' => $paths['root'],
            'relative_path' => $paths['relative'],
        ]);
    } catch (Throwable $e) {
        cloud_index_log('queue_copied ' . $path . ': ' . $e->getMessage());
    }
}

/** @param array<string,mixed> $payload */
function cloud_index_publish(string $user, string $rootKey, string $eventType, array $payload): void
{
    try {
        FileIndex::publish('cloud', $user, $eventType, $payload + ['root_key' => $rootKey]);
    } catch (Throwable $e) {
        cloud_index_log('publish ' . $eventType . ': ' . $e->getMessage());
    }
}

function cloud_index_log(string $message): void
{
    if (function_exists('cloud_safe_error_log')) {
        cloud_safe_error_log('file-index: ' . $message);
        return;
    }
    error_log('cloud file-index: ' . $message);
}
