<?php
$token = (string)($_GET['share_token'] ?? '');
$repo = new ShareRepository();
$share = $repo->findByToken($token);
if (!$share) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$relative = (string)($_GET['download_item'] ?? $_GET['file'] ?? '');
$access = ShareAccessPolicy::check($share, 'download', ['child_path' => $relative, 'file' => $relative]);
if (!$access->isAllowed()) {
    http_response_code(AccessResult::httpStatus($access->reason));
    echo $access->reason;
    exit;
}
$resource = $access->resource;

if ($share->resource_type === 'folder') {
    $base = (string)$resource->get('path');
    $filePath = sp_safe_join($base, $relative);
    if ($filePath === null || !is_file($filePath)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    $fileResource = new Resource('file', $relative, 'cloud', basename($filePath), [
        'path' => $filePath,
        'name' => basename($filePath),
        'owner_email' => $share->owner_email,
    ]);
    ShareFileHandler::stream($share, $fileResource, true);
}

ShareFileHandler::stream($share, $resource, true);
