<?php
require_once dirname(__DIR__, 3) . '/project/sharepoint/api_bootstrap.php';
require_once dirname(__DIR__, 3) . '/storage_helper.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    sp_json_response(['success' => false, 'error' => 'method_not_allowed'], 405);
}
$owner = sharepoint_api_owner();
$input = sp_request_body();
$action = strtolower(trim((string)($input['action'] ?? 'create')));

if ($action !== 'create') {
    // List/revoke/delete share the same authenticated contract as other apps.
    $GLOBALS['SHAREPOINT_API_PREPARED_INPUT'] = $input;
    sharepoint_api_handle('cloud');
    exit;
}

$rootKey = strtolower(trim((string)($input['root'] ?? $input['cloud_root'] ?? 'main')));
$validRoots = ['main', 'archive', 'temp', 'documents', 'photos', 'pdf', 'trash'];
if (!in_array($rootKey, $validRoots, true)) {
    sp_json_response(['success' => false, 'error' => 'invalid root'], 400);
}

$rawPath = str_replace('\\', '/', trim((string)($input['path'] ?? '')));
if ($rawPath === '') {
    sp_json_response(['success' => false, 'error' => 'path required'], 400);
}

try {
    $relative = appdata_normalize_relpath($rawPath);
} catch (Throwable $e) {
    sp_json_response(['success' => false, 'error' => 'invalid path'], 400);
}

$username = sp_email_to_cloud_username($owner);
$rootDir = appdata_user_root_dir('cloud', $username, $rootKey, true);
$fullPath = $relative === '' ? $rootDir : $rootDir . '/' . $relative;
$real = realpath($fullPath);
$realRoot = realpath($rootDir);
if ($real === false || $realRoot === false || !str_starts_with($real, $realRoot)) {
    sp_json_response(['success' => false, 'error' => 'file not found'], 404);
}
if (!is_file($real) && !is_dir($real)) {
    sp_json_response(['success' => false, 'error' => 'file not found'], 404);
}

$isFolder = is_dir($real);
$input['resource_type'] = $isFolder ? 'folder' : 'file';
$input['resource_app'] = 'cloud';
$input['resource_id'] = $relative !== '' ? $relative : basename($real);
$input['resource_data'] = [
    'path' => str_replace('\\', '/', $real),
    'is_folder' => $isFolder,
    'name' => basename($real),
    'cloud_root' => $rootKey,
];
if (empty($input['title'])) {
    $input['title'] = basename($real);
}
if (empty($input['permissions'])) {
    $input['permissions'] = ['view', 'download'];
}

try {
    $service = new ShareService();
    $share = $service->create($input, $owner);

    // Transitional mirror: keep the legacy table in sync for the old admin view
    // and for any client that still builds ?sharedFile= links.
    try {
        $db = getDB();
        $db->exec('ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS is_folder INTEGER NOT NULL DEFAULT 0');
        $expires = $share->expires_at ? strtotime($share->expires_at) : null;
        $stmt = $db->prepare(
            'INSERT INTO shared_files (share_id, file_name, file_path, is_folder, user_email, created_at, expires, expires_option,
                password_hash, view_only, disable_download, disable_copy, max_views, view_count)
             VALUES (:id, :name, :path, :folder, :email, NOW(), :expires, :option, :password, :view_only, :download, :copy, :max_views, 0)
             ON CONFLICT (share_id) DO UPDATE SET file_name = EXCLUDED.file_name, file_path = EXCLUDED.file_path,
                is_folder = EXCLUDED.is_folder, expires = EXCLUDED.expires, expires_option = EXCLUDED.expires_option'
        );
        $stmt->bindValue(':id', $share->token, PDO::PARAM_STR);
        $stmt->bindValue(':name', basename($real), PDO::PARAM_STR);
        $stmt->bindValue(':path', str_replace('\\', '/', $real), PDO::PARAM_STR);
        $stmt->bindValue(':folder', $isFolder ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':email', $owner, PDO::PARAM_STR);
        $stmt->bindValue(':expires', $expires, $expires === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':option', $share->expires_option, PDO::PARAM_STR);
        $stmt->bindValue(':password', (string)$share->password_hash, PDO::PARAM_STR);
        $stmt->bindValue(':view_only', $share->hasPermission('download') ? 0 : 1, PDO::PARAM_INT);
        $stmt->bindValue(':download', $share->hasPermission('download') ? 0 : 1, PDO::PARAM_INT);
        $stmt->bindValue(':copy', $share->hasPermission('download') ? 0 : 1, PDO::PARAM_INT);
        $stmt->bindValue(':max_views', $share->max_views, PDO::PARAM_INT);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('sharepoint legacy mirror: ' . $e->getMessage());
    }

    sp_json_response(['success' => true, 'share' => $service->describe($share)]);
} catch (InvalidArgumentException $e) {
    sp_json_response(['success' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('sharepoint cloud create: ' . $e->getMessage());
    sp_json_response(['success' => false, 'error' => 'create_failed'], 500);
}
