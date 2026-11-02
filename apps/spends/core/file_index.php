<?php
/**
 * Spends <-> central file index adapter.
 *
 * Backups are listed, counted and resolved through AppFiles/FileIndex only.
 * The app never enumerates its own data directory.
 */
declare(strict_types=1);

if (!function_exists('appdata_app_base_dir')) {
    require_once dirname(__DIR__, 3) . '/storage_helper.php';
}
if (!class_exists('AppFiles')) {
    require_once dirname(__DIR__, 3) . '/lib/Storage/AppFiles.php';
}

/**
 * Index coordinates of a user's backup directory.
 *
 * @return array{owner:string,root:string,path:string,dir:string,relative:string}|null
 */
function spends_index_backup_context(string $userEmail): ?array
{
    $dir = getUserBackupDir($userEmail);
    try {
        $base = rtrim(appdata_app_base_dir('spends'), '/');
    } catch (Throwable $e) {
        return null;
    }
    $dir = rtrim(str_replace('\\', '/', $dir), '/');
    if (!str_starts_with($dir, $base . '/')) return null;
    $relative = substr($dir, strlen($base) + 1);
    $root = AppFiles::folderRoot($relative);
    $path = $relative === $root ? '' : substr($relative, strlen($root) + 1);
    return ['owner' => $root, 'root' => $root, 'path' => $path, 'dir' => $dir, 'relative' => $relative];
}

/**
 * Backups of one user, newest first, from the index.
 *
 * @return list<array{name:string,size:int,mtime:int,path:string}>
 */
function spends_list_backups(string $userEmail): array
{
    $ctx = spends_index_backup_context($userEmail);
    if ($ctx === null) return [];
    try {
        $listing = AppFiles::listByRoot('spends', $ctx['owner'], $ctx['root'], $ctx['path'], ['limit' => 200]);
    } catch (Throwable $e) {
        error_log('spends_list_backups: ' . $e->getMessage());
        return [];
    }
    $out = [];
    foreach ($listing['entries'] as $row) {
        if ($row['type'] !== 'file' || !str_ends_with(strtolower((string)$row['name']), '.csv')) continue;
        $out[] = [
            'name' => (string)$row['name'],
            'size' => (int)$row['size'],
            'mtime' => (int)$row['mtime'],
            'path' => $ctx['dir'] . '/' . $row['name'],
        ];
    }
    usort($out, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

function spends_count_backups(string $userEmail): int
{
    return count(spends_list_backups($userEmail));
}

/** Register a backup file that was just written to disk. */
function spends_record_backup(string $userEmail, string $fileName): void
{
    $ctx = spends_index_backup_context($userEmail);
    if ($ctx === null) return;
    $fileName = basename($fileName);
    try {
        AppFiles::refresh('spends', $ctx['owner'], $ctx['root'], $ctx['relative'] . '/' . $fileName);
    } catch (Throwable $e) {
        error_log('spends_record_backup: ' . $e->getMessage());
    }
}

/** Mark a removed backup as deleted in the index. */
function spends_forget_backup(string $userEmail, string $fileName): void
{
    $ctx = spends_index_backup_context($userEmail);
    if ($ctx === null) return;
    $fileName = basename($fileName);
    try {
        AppFiles::recordDelete('spends', $ctx['owner'], $ctx['root'], $ctx['relative'] . '/' . $fileName, 'deleted');
    } catch (Throwable $e) {
        error_log('spends_forget_backup: ' . $e->getMessage());
    }
}

/** Validated absolute path of an indexed backup (download / view / restore). */
function spends_backup_path(string $userEmail, string $fileName): ?string
{
    $ctx = spends_index_backup_context($userEmail);
    if ($ctx === null) return null;
    $fileName = basename($fileName);
    try {
        return AppFiles::absolute('spends', $ctx['owner'], $ctx['root'], $ctx['relative'] . '/' . $fileName);
    } catch (Throwable $e) {
        return null;
    }
}
