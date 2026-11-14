<?php
// Minimal Immich API mock used only by geolocation_immich_test.php.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
header('Content-Type: application/json');
if ($path === '/api/server/about') {
    echo json_encode(['name' => 'Mock Immich', 'version' => '1.0.0-test']);
    return;
}
if ($path === '/api/search/metadata') {
    echo json_encode(['assets' => ['items' => [
        [
            'id' => 'asset-gps-1',
            'originalFileName' => 'gps.jpg',
            'fileCreatedAt' => '2026-10-01T10:00:00Z',
            'exifInfo' => ['latitude' => 50.4501, 'longitude' => 30.5234, 'dateTimeOriginal' => '2026-10-01T10:00:00Z'],
        ],
        [
            'id' => 'asset-no-gps-2',
            'originalFileName' => 'no-gps.jpg',
            'fileCreatedAt' => '2026-10-01T10:05:00Z',
            'exifInfo' => [],
        ],
    ]]]);
    return;
}
if (preg_match('#^/api/assets/([^/]+)/thumbnail$#', $path, $m)) {
    header('Content-Type: image/jpeg');
    echo "\xFF\xD8\xFFmock-jpeg";
    return;
}
if (preg_match('#^/api/assets/([^/]+)$#', $path, $m) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'PUT') {
    echo json_encode(['id' => $m[1], 'updated' => true]);
    return;
}
http_response_code(404);
echo json_encode(['error' => 'not_found']);
