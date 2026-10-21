<?php
$token = (string)($_GET['share_token'] ?? '');
$repo = new ShareRepository();
$share = $repo->findByToken($token);
if (!$share) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$context = [
    'file' => (string)($_GET['file'] ?? $_GET['content_path'] ?? ''),
    'subpath' => (string)($_GET['subpath'] ?? ''),
];
$access = ShareAccessPolicy::check($share, 'view', $context);
if (!$access->isAllowed()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(AccessResult::httpStatus($access->reason));
    echo json_encode(['error' => $access->reason]);
    exit;
}

$handler = ResourceResolver::handler($share);
$resource = $access->resource;

if (isset($_GET['callback'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 0, 'status' => 1]);
    exit;
}

if ($share->resource_type === 'app' && $handler) {
    $path = strtolower(trim((string)($_GET['content_path'] ?? $_GET['path'] ?? ''), '/'));
    if ($path === '' || $path === 'data') {
        $filters = $_GET;
        unset($filters['share_token'], $filters['content_path'], $filters['path']);
        $data = method_exists($handler, 'loadData') ? $handler->loadData($share, $resource, $filters) : [];
        sp_json_response(['ok' => true, 'share' => $share->toArray(), 'data' => $data]);
    }
    if (method_exists($handler, 'getContent')) {
        $content = $handler->getContent($share, $resource, $path);
        if (is_array($content) && !empty($content['data_uri'])) {
            $parts = explode(',', (string)$content['data_uri'], 2);
            header('Content-Type: ' . (str_contains($parts[0], ';') ? explode(';', substr($parts[0], 5))[0] : 'application/octet-stream'));
            echo base64_decode($parts[1] ?? '');
            exit;
        }
        if (is_array($content) && !empty($content['path']) && is_file($content['path'])) {
            $bytes = (string)file_get_contents($content['path']);
            sp_stream_bytes($bytes, (string)($content['mime'] ?? 'application/octet-stream'), (string)($content['name'] ?? 'file'), false);
            exit;
        }
    }
    http_response_code(404);
    echo 'Not found';
    exit;
}

if ($share->resource_type === 'folder') {
    $relative = (string)($_GET['file'] ?? $_GET['content_path'] ?? '');
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
    ShareFileHandler::stream($share, $fileResource, false);
}

ShareFileHandler::stream($share, $resource, false);
