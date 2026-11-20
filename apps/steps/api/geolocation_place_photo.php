<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_place_photos.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_place_photo', 240, 60);
steps_geolocation_migrate(app_main_db());
$pdo = app_main_db()->getPdo();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$osmType = strtolower(trim((string)($_GET['osm_type'] ?? ($_POST['osm_type'] ?? ''))));
$osmId = (int)($_GET['osm_id'] ?? ($_POST['osm_id'] ?? 0));
if (!in_array($osmType, ['node', 'way', 'relation'], true) || $osmId <= 0) {
    public_api_json(['ok' => false, 'error' => 'invalid_osm'], 400);
}

if ($method === 'POST') {
    $body = public_api_body();
    $action = strtolower(trim((string)($body['action'] ?? 'refresh')));
    if ($action !== 'refresh') public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
    $exists = $pdo->prepare('SELECT 1 FROM geo_osm_places WHERE osm_type = :t AND osm_id = :i');
    $exists->execute([':t' => $osmType, ':i' => $osmId]);
    if (!$exists->fetchColumn()) public_api_json(['ok' => false, 'error' => 'place_not_found'], 404);
    StepsGeolocationPlacePhotos::requestRefresh($pdo, $osmType, $osmId);
    public_api_json(['ok' => true, 'status' => 'queued', 'osm_type' => $osmType, 'osm_id' => $osmId]);
}

if ($method !== 'GET') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);

$metaOnly = isset($_GET['meta']) && (string)$_GET['meta'] !== '0';
$meta = StepsGeolocationPlacePhotos::metaFor($pdo, $osmType, $osmId);
if ($metaOnly) {
    if ($meta === null) public_api_json(['ok' => false, 'error' => 'not_found'], 404);
    $ready = ($meta['status'] ?? '') === 'ready' && is_file(StepsGeolocationPlacePhotos::absolutePath($osmType, $osmId));
    public_api_json([
        'ok' => true,
        'osm_type' => $osmType,
        'osm_id' => $osmId,
        'status' => $meta['status'],
        'photo_url' => $ready ? StepsGeolocationPlacePhotos::apiUrl($osmType, $osmId) : null,
        'attribution' => (string)($meta['attribution'] ?? ''),
        'license_name' => (string)($meta['license_name'] ?? ''),
        'author' => (string)($meta['author'] ?? ''),
        'source_provider' => (string)($meta['source_provider'] ?? ''),
        'source_page_url' => (string)($meta['source_page_url'] ?? ''),
        'width_px' => $meta['width_px'],
        'height_px' => $meta['height_px'],
        'file_bytes' => $meta['file_bytes'],
        'fetched_at' => $meta['fetched_at'],
    ]);
}

if ($meta === null || ($meta['status'] ?? '') !== 'ready') {
    public_api_json(['ok' => false, 'error' => 'not_ready'], 404);
}
header_remove('Content-Type');
StepsGeolocationPlacePhotos::serveFile($osmType, $osmId);
exit;
