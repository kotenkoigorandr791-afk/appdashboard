<?php
declare(strict_types=1);
require_once __DIR__ . '/cloud_sync_protocol.php';
require_once __DIR__ . '/cloud_sync_bus.php';
function cloud_sync_resolve_scope(string $user, string $rootKey, string $basePath): array {
    $rootKey = in_array($rootKey, CLOUD_SYNC_ALLOWED_ROOTS, true) ? $rootKey : 'clientsync';
    $path = safe_path($basePath);
    if ($rootKey === 'main') remap_clientsync_path($rootKey, $path);
    $base = user_root($user, $rootKey);
    $full = $path !== '' ? $base . '/' . $path : $base;
    if (!is_dir($full) && !@mkdir($full, 0775, true)) {
        return ['ok' => false, 'error' => 'cannot create base path'];
    }
    return ['ok' => true, 'root' => $rootKey, 'path' => $path, 'base' => $base, 'full' => $full];
}
function cloud_sync_user_mode(string $email): string {
    $db = getDB();
    if (!$db || $email === '') return 'mtime_size';
    ensureCloudUserSettingsSchema($db);
    try {
        $stmt = $db->prepare('SELECT sync_mode FROM cloud_user_settings WHERE email = :e LIMIT 1');
        $stmt->bindValue(':e', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $sm = (string)($row['sync_mode'] ?? 'mtime_size');
        return ($sm === 'mtime_size_hash') ? 'mtime_size_hash' : 'mtime_size';
    } catch (Exception $e) {
        return 'mtime_size';
    }
}
function cloud_sync_entry_from_file(string $full, string $rel, string $syncMode): array {
    $mtimeVal = @filemtime($full);
    $mtimeMs = $mtimeVal === false ? 0 : (int)round((float)$mtimeVal * 1000);
    $mtime = $mtimeMs > 0 ? (int)floor($mtimeMs / 1000) : 0;
    $entry = [
        'path' => $rel,
        'type' => 'file',
        'mtime' => $mtime,
        'mtime_ms' => $mtimeMs,
        'size' => (int)@filesize($full),
    ];
    if ($syncMode === 'mtime_size_hash') {
        $entry['sha1'] = sha1_file($full) ?: '';
    }
    return $entry;
}
function cloud_sync_manifest_build(string $user, string $rootKey, string $basePath, string $email = ''): array {
    $scope = cloud_sync_resolve_scope($user, $rootKey, $basePath);
    if (!$scope['ok']) return $scope;
    $syncMode = cloud_sync_user_mode($email);
    $files = [];
    $dirs = [];
    $rootPrefix = str_replace('\\', '/', $scope['full']);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scope['full'], FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $node) {
        $full = str_replace('\\', '/', $node->getPathname());
        $rel = ltrim(str_replace($rootPrefix, '', $full), '/');
        if ($rel === '') continue;
        if ($scope['root'] === 'clientsync' && should_hide_clientsync_virtual_path($rel, $node->isDir())) continue;
        if ($node->isDir()) {
            $dirs[$rel] = ['path' => $rel, 'type' => 'dir'];
            continue;
        }
        if (!$node->isFile()) continue;
        $files[] = cloud_sync_entry_from_file($full, $rel, $syncMode);
    }
    usort($files, static fn($a, $b) => strcmp($a['path'], $b['path']));
    $entries = array_values($dirs);
    usort($entries, static fn($a, $b) => strcmp($a['path'], $b['path']));
    foreach ($files as $file) $entries[] = $file;
    return [
        'ok' => true,
        'scope' => $scope,
        'sync_mode' => $syncMode,
        'entries' => $entries,
        'generated_at' => time(),
    ];
}
function cloud_sync_manifest_page(string $user, string $rootKey, string $basePath, int $offset, int $limit, string $email = '', ?array $cachedEntries = null): array {
    if ($cachedEntries === null) {
        $built = cloud_sync_manifest_build($user, $rootKey, $basePath, $email);
        if (!$built['ok']) return $built;
        $cachedEntries = $built['entries'];
        $scope = $built['scope'];
        $syncMode = $built['sync_mode'];
    } else {
        $scope = cloud_sync_resolve_scope($user, $rootKey, $basePath);
        if (!$scope['ok']) return $scope;
        $syncMode = cloud_sync_user_mode($email);
    }
    $total = count($cachedEntries);
    $slice = array_slice($cachedEntries, max(0, $offset), max(1, min($limit, CLOUD_SYNC_MANIFEST_PAGE)));
    return [
        'ok' => true,
        'op' => 'manifest',
        'root' => $scope['root'],
        'base_path' => $basePath,
        'sync_mode' => $syncMode,
        'offset' => $offset,
        'total' => $total,
        'done' => ($offset + count($slice)) >= $total,
        'entries' => $slice,
        'generated_at' => time(),
    ];
}
function cloud_sync_full_path(array $scope, string $relPath): array {
    $rel = safe_path($relPath);
    if ($rel === '' && $relPath !== '') return ['ok' => false, 'error' => 'invalid path'];
    $full = $rel === '' ? $scope['full'] : $scope['full'] . '/' . $rel;
    $rootPrefix = str_replace('\\', '/', $scope['full']);
    $norm = str_replace('\\', '/', $full);
    if ($rel !== '' && !str_starts_with($norm, $rootPrefix)) return ['ok' => false, 'error' => 'path traversal'];
    return ['ok' => true, 'rel' => $rel, 'full' => $full];
}
function cloud_sync_read_file(array $scope, string $relPath, int $offset, int $length): array {
    $p = cloud_sync_full_path($scope, $relPath);
    if (!$p['ok']) return $p;
    if (!is_file($p['full'])) return ['ok' => false, 'error' => 'not found'];
    $size = (int)filesize($p['full']);
    $length = max(1, min($length > 0 ? $length : CLOUD_SYNC_CHUNK_BYTES, CLOUD_SYNC_CHUNK_BYTES));
    $offset = max(0, $offset);
    $fh = fopen($p['full'], 'rb');
    if (!$fh) return ['ok' => false, 'error' => 'read failed'];
    fseek($fh, $offset);
    $data = fread($fh, $length);
    fclose($fh);
    $read = strlen((string)$data);
    return [
        'ok' => true,
        'op' => 'read_ok',
        'path' => $p['rel'],
        'offset' => $offset,
        'data_b64' => base64_encode((string)$data),
        'bytes' => $read,
        'eof' => ($offset + $read) >= $size,
        'size' => $size,
    ];
}
function cloud_sync_write_file(array $scope, string $email, string $relPath, int $offset, string $dataB64, bool $truncate, bool $close): array {
    $p = cloud_sync_full_path($scope, $relPath);
    if (!$p['ok']) return $p;
    if ($p['rel'] === '') return ['ok' => false, 'error' => 'invalid path'];
    $raw = base64_decode($dataB64, true);
    if ($raw === false) return ['ok' => false, 'error' => 'invalid base64'];
    $dir = dirname($p['full']);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return ['ok' => false, 'error' => 'mkdir failed'];
    if ($truncate || !is_file($p['full'])) {
        $fh = @fopen($p['full'], 'wb');
    } else {
        $fh = @fopen($p['full'], 'r+b');
    }
    if (!$fh) return ['ok' => false, 'error' => 'open failed'];
    if ($offset > 0) fseek($fh, $offset);
    $written = fwrite($fh, $raw);
    fclose($fh);
    if ($written === false) return ['ok' => false, 'error' => 'write failed'];
    $mtimeVal = @filemtime($p['full']);
    $mtimeMs = $mtimeVal === false ? 0 : (int)round((float)$mtimeVal * 1000);
    if ($close && $email !== '') {
        cloud_sync_bus_publish($email, $scope['root'], 'modified', $p['rel'], [
            'entry' => cloud_sync_entry_from_file($p['full'], $p['rel'], cloud_sync_user_mode($email)),
        ]);
    }
    return [
        'ok' => true,
        'op' => 'write_ok',
        'path' => $p['rel'],
        'bytes' => (int)$written,
        'mtime_ms' => $mtimeMs,
        'size' => (int)@filesize($p['full']),
    ];
}
function cloud_sync_mkdir(array $scope, string $email, string $relPath): array {
    $p = cloud_sync_full_path($scope, $relPath);
    if (!$p['ok']) return $p;
    if (is_dir($p['full'])) return ['ok' => true, 'op' => 'mkdir_ok', 'path' => $p['rel']];
    if (!@mkdir($p['full'], 0775, true)) return ['ok' => false, 'error' => 'mkdir failed'];
    if ($email !== '') {
        cloud_sync_bus_publish($email, $scope['root'], 'created', $p['rel'], ['type' => 'dir']);
    }
    return ['ok' => true, 'op' => 'mkdir_ok', 'path' => $p['rel']];
}
function cloud_sync_delete_path(array $scope, string $email, string $relPath, bool $isDir): array {
    $p = cloud_sync_full_path($scope, $relPath);
    if (!$p['ok']) return $p;
    if (!file_exists($p['full'])) return ['ok' => false, 'error' => 'not found'];
    $ok = $isDir ? @rmdir($p['full']) : @unlink($p['full']);
    if (!$ok) return ['ok' => false, 'error' => 'delete failed'];
    if ($email !== '') {
        cloud_sync_bus_publish($email, $scope['root'], 'deleted', $p['rel'], ['type' => $isDir ? 'dir' : 'file']);
    }
    return ['ok' => true, 'op' => 'delete_ok', 'path' => $p['rel']];
}
function cloud_sync_rename_path(array $scope, string $email, string $from, string $to): array {
    $a = cloud_sync_full_path($scope, $from);
    $b = cloud_sync_full_path($scope, $to);
    if (!$a['ok'] || !$b['ok']) return ['ok' => false, 'error' => 'invalid path'];
    if (!file_exists($a['full'])) return ['ok' => false, 'error' => 'not found'];
    if (file_exists($b['full'])) return ['ok' => false, 'error' => 'exists'];
    if (!@rename($a['full'], $b['full'])) return ['ok' => false, 'error' => 'rename failed'];
    if ($email !== '') {
        $extra = ['from' => $a['rel']];
        if (is_file($b['full'])) {
            $extra['entry'] = cloud_sync_entry_from_file($b['full'], $b['rel'], cloud_sync_user_mode($email));
        } elseif (is_dir($b['full'])) {
            $extra['type'] = 'dir';
        }
        cloud_sync_bus_publish($email, $scope['root'], 'moved', $b['rel'], $extra);
    }
    return ['ok' => true, 'op' => 'rename_ok', 'from' => $a['rel'], 'to' => $b['rel']];
}
function cloud_sync_touch_last(string $email): void {
    if ($email === '') return;
    $db = getDB();
    if (!$db) return;
    ensureCloudUserSettingsSchema($db);
    $upd = $db->prepare("UPDATE cloud_user_settings SET last_sync_at = :t, updated_at = strftime('%s','now') WHERE email = :e");
    $upd->bindValue(':t', time(), SQLITE3_INTEGER);
    $upd->bindValue(':e', $email, SQLITE3_TEXT);
    @$upd->execute();
}
function cloud_sync_publish_router_event(string $email, string $rootKey, string $actionName, string $path, array $busExtra = []): void {
    $map = [
        'create_file' => 'created',
        'save_file' => 'modified',
        'upload' => 'modified',
        'delete' => 'deleted',
        'mkdir' => 'created',
        'rename' => 'moved',
        'move' => 'moved',
    ];
    $kind = $map[$actionName] ?? 'modified';
    $extra = array_merge(['action' => $actionName], $busExtra);
    if ($email === '' || $path === '') {
        cloud_sync_bus_publish($email, $rootKey, $kind, $path, $extra);
        return;
    }
    $user = email_to_cloud_username($email);
    $scope = cloud_sync_resolve_scope($user, $rootKey, '');
    if (!$scope['ok']) {
        cloud_sync_bus_publish($email, $rootKey, $kind, $path, $extra);
        return;
    }
    if ($kind === 'deleted') {
        if (!isset($extra['type'])) {
            $extra['type'] = 'file';
        }
    } elseif ($actionName === 'mkdir') {
        $extra['type'] = 'dir';
    } elseif ($kind === 'moved') {
        $p = cloud_sync_full_path($scope, $path);
        if ($p['ok'] && is_dir($p['full'])) {
            $extra['type'] = 'dir';
        } elseif ($p['ok'] && is_file($p['full'])) {
            $extra['entry'] = cloud_sync_entry_from_file($p['full'], $p['rel'], cloud_sync_user_mode($email));
        } elseif (!empty($extra['from'])) {
            $fromP = cloud_sync_full_path($scope, (string)$extra['from']);
            if ($fromP['ok'] && is_dir($fromP['full'])) {
                $extra['type'] = 'dir';
            }
        }
    } else {
        $p = cloud_sync_full_path($scope, $path);
        if ($p['ok'] && is_dir($p['full'])) {
            $extra['type'] = 'dir';
        } elseif ($p['ok'] && is_file($p['full'])) {
            $extra['entry'] = cloud_sync_entry_from_file($p['full'], $p['rel'], cloud_sync_user_mode($email));
        }
    }
    cloud_sync_bus_publish($email, $rootKey, $kind, $path, $extra);
}
