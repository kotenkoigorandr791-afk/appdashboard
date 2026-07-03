<?php
// serve_avatar.php — serves locally stored avatar images
// Files are stored in /mnt/server/uploads/avatars/ by the Immich backend
// This proxy allows the browser to load them via the project's URL

$path = $_GET['path'] ?? '';
if (!$path) {
    http_response_code(400);
    echo 'Missing path parameter';
    exit;
}

// Security: prevent directory traversal
if (strpos($path, '..') !== false || strpos($path, './') !== false) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Base path: project root is /mnt/server/apps/media_viewer/,
// files are stored relative to /mnt/server/
$basePath = realpath(__DIR__ . '/../..');
if (!$basePath) {
    http_response_code(500);
    exit;
}

$fullPath = realpath($basePath . '/' . $path);

// Ensure resolved path is within the base path (no traversal)
if (!$fullPath || strncmp($fullPath, $basePath, strlen($basePath)) !== 0) {
    error_log("serve_avatar: path traversal blocked - $path");
    http_response_code(404);
    exit;
}

if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit;
}

// Only allow image MIME types
$mime = mime_content_type($fullPath);
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp'];

if (!in_array($mime, $allowedMimes)) {
    http_response_code(403);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

readfile($fullPath);
