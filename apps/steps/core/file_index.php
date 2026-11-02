<?php
/**
 * Steps <-> central file index adapter.
 *
 * CSV backups are listed, resolved and counted through AppFiles/FileIndex.
 */
declare(strict_types=1);

if (!function_exists('appdata_app_base_dir')) {
    require_once dirname(__DIR__, 3) . '/storage_helper.php';
}
if (!class_exists('AppFiles')) {
    require_once dirname(__DIR__, 3) . '/lib/Storage/AppFiles.php';
}

/**
 * @return array{owner:string,root:string,path:string,dir:string,relative:string}|null
 */
function steps_index_user_context(string $userDir): ?array
{
    try {
        $base = rtrim(appdata_app_base_dir('steps'), '/');
    } catch (Throwable $e) {
        return null;
    }
    $userDir = rtrim(str_replace('\\', '/', $userDir), '/');
    if (!str_starts_with($userDir, $base . '/')) return null;
    $relative = substr($userDir, strlen($base) + 1);
    $root = AppFiles::folderRoot($relative);
    return [
        'owner' => $root,
        'root' => $root,
        'path' => $relative,
        'dir' => $userDir,
        'relative' => $relative,
    ];
}

/**
 * Backup files of one user directory, newest first.
 *
 * @return list<array{name:string,size:int,mtime:int,path:string}>
 */
function steps_list_backups(string $userDir): array
{
    $ctx = steps_index_user_context($userDir);
    if ($ctx === null) return [];
    try {
        $listing = AppFiles::listByRoot('steps', $ctx['owner'], $ctx['root'], $ctx['path'] . '/backups', ['limit' => 500]);
    } catch (Throwable $e) {
        error_log('steps_list_backups: ' . $e->getMessage());
        return [];
    }
    $out = [];
    foreach ($listing['entries'] as $row) {
        if ($row['type'] !== 'file' || !str_starts_with((string)$row['name'], 'Steps_backup_')) continue;
        if (!str_ends_with(strtolower((string)$row['name']), '.csv')) continue;
        $out[] = [
            'name' => (string)$row['name'],
            'size' => (int)$row['size'],
            'mtime' => (int)$row['mtime'],
            'path' => $ctx['dir'] . '/backups/' . $row['name'],
        ];
    }
    usort($out, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

function steps_record_backup(string $userDir, string $fileName): void
{
    $ctx = steps_index_user_context($userDir);
    if ($ctx === null) return;
    try {
        AppFiles::refresh('steps', $ctx['owner'], $ctx['root'], $ctx['relative'] . '/backups/' . basename($fileName));
    } catch (Throwable $e) {
        error_log('steps_record_backup: ' . $e->getMessage());
    }
}

function steps_forget_backup(string $userDir, string $fileName): void
{
    $ctx = steps_index_user_context($userDir);
    if ($ctx === null) return;
    try {
        AppFiles::recordDelete('steps', $ctx['owner'], $ctx['root'], $ctx['relative'] . '/backups/' . basename($fileName), 'deleted');
    } catch (Throwable $e) {
        error_log('steps_forget_backup: ' . $e->getMessage());
    }
}

function steps_backup_path(string $userDir, string $fileName): ?string
{
    $ctx = steps_index_user_context($userDir);
    if ($ctx === null) return null;
    try {
        return AppFiles::absolute('steps', $ctx['owner'], $ctx['root'], $ctx['relative'] . '/backups/' . basename($fileName));
    } catch (Throwable $e) {
        return null;
    }
}
