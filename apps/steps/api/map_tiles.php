<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_map.php';
require_once dirname(__DIR__) . '/core/geolocation_tiles.php';
public_api_bootstrap();
$email = get_current_user_email();
if (!$email) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}
if (PHP_SESSION_ACTIVE === session_status()) session_write_close();
public_api_rate_limit('geolocation_tiles', 1500, 60);

$provider = strtolower(trim((string)($_GET['provider'] ?? 'esri_dark')));
$coords = StepsGeolocationTiles::tile(
    (string)($_GET['z'] ?? ''),
    (string)($_GET['x'] ?? ''),
    (string)($_GET['y'] ?? '')
);
$fail = static function (int $status, string $error): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
};
if ($coords === null) $fail(400, 'invalid_tile');
if (StepsGeolocationTiles::provider($provider) === null) $fail(400, 'invalid_provider');

$tile = StepsGeolocationTiles::get($provider, $coords['z'], $coords['x'], $coords['y']);
if ($tile === null) {
    header('Cache-Control: public, max-age=60');
    $fail(502, 'tile_unavailable');
}

$etag = '"' . substr(hash('sha256', $tile['body']), 0, 32) . '"';
header('Content-Type: ' . $tile['mime']);
header('X-Content-Type-Options: nosniff');
header('ETag: ' . $etag);
header('X-Tile-Cache: ' . ($tile['cached'] ? ($tile['stale'] ? 'stale' : 'hit') : 'miss'));
header('Cache-Control: public, max-age=604800, immutable');
if ($tile['stale']) {
    // Serve the last good tile, but let the browser retry the upstream soon.
    header('Cache-Control: public, max-age=300, stale-while-revalidate=86400');
    header('Warning: 110 - "Response is stale"');
}
$ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . strlen($tile['body']));
echo $tile['body'];
exit;
