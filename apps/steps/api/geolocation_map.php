<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_map.php';
require_once dirname(__DIR__) . '/core/geolocation_permissions.php';
require_once dirname(__DIR__) . '/core/geolocation_immich.php';
require_once dirname(__DIR__) . '/core/geolocation_features.php';
require_once dirname(__DIR__) . '/core/geolocation_observability.php';
require_once dirname(__DIR__) . '/core/geolocation_smoothing.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$viewer = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_map', 300, 60);
steps_geolocation_migrate(app_main_db());
$pdo = app_main_db()->getPdo();
StepsGeolocationFeatures::requireEnabled($pdo, $viewer, 'map');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$flagOff = static function (string $name): bool {
    $v = strtolower(trim((string)getenv($name)));
    return in_array($v, ['1', 'true', 'on', 'yes'], true);
};
$mapConfig = [
    'prefetch_enabled' => !$flagOff('GEO_MAP_PREFETCH_DISABLED'),
    'overlays_enabled' => !$flagOff('GEO_MAP_OVERLAYS_DISABLED'),
    'bounded_chunks' => true,
];

if (!empty($_GET['settings']) || ($method === 'POST' && ($_GET['settings'] ?? '') !== '')) {
    if ($method === 'POST') {
        $body = public_api_body();
        if (!empty($body['provider']) && !isset(StepsGeolocationMap::providers()[$body['provider']])) {
            public_api_json(['ok' => false, 'error' => 'invalid_provider'], 400);
        }
        $layers = isset($body['layers']) && is_array($body['layers']) ? $body['layers'] : null;
        $saved = StepsGeolocationMap::savePreferences($pdo, $viewer, $body['provider'] ?? null, $layers);
        public_api_json(['ok' => true, 'settings' => $saved, 'providers' => StepsGeolocationMap::providers(), 'config' => $mapConfig]);
    }
    public_api_json(['ok' => true, 'settings' => StepsGeolocationMap::preferences($pdo, $viewer), 'providers' => StepsGeolocationMap::providers(), 'config' => $mapConfig]);
}

if ($method !== 'GET') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);

$from = (string)($_GET['from'] ?? gmdate('c', time() - 30 * 86400));
$to = (string)($_GET['to'] ?? gmdate('c'));
$wantsExtent = ($_GET['extent'] ?? '') === '1';
$bbox = StepsGeolocationMap::parseBbox((string)($_GET['bbox'] ?? ''));
if ($bbox === null && !$wantsExtent && trim((string)($_GET['bbox'] ?? '')) === '') {
    // No viewport yet: start from the owner's own data extent.
    $bbox = StepsGeolocationMap::dataBbox($pdo, $viewer, $from, $to);
}
if ($bbox === null && !$wantsExtent) {
    if (trim((string)($_GET['bbox'] ?? '')) === '') {
        public_api_json([
            'ok' => true,
            'geojson' => ['type' => 'FeatureCollection', 'features' => []],
            'bbox' => null,
            'range' => ['from' => gmdate('c', strtotime($from)), 'to' => gmdate('c', strtotime($to))],
            'meta' => ['counts' => [], 'bounded' => true],
            'config' => $mapConfig,
        ]);
    }
    public_api_json(['ok' => false, 'error' => 'invalid_bbox'], 400);
}
if (strtotime($from) === false || strtotime($to) === false || strtotime($from) >= strtotime($to)) {
    public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
}
if (strtotime($to) - strtotime($from) > 3660 * 86400) {
    public_api_json(['ok' => false, 'error' => 'range_too_large'], 400);
}
$fromIso = gmdate('c', strtotime($from));
$toIso = gmdate('c', strtotime($to));

$family = isset($_GET['family_email']) ? (string)$_GET['family_email'] : null;
$authorized = StepsGeolocationMap::authorize($pdo, $viewer, $family, $fromIso, $toIso);
if ($authorized['error'] !== null) {
    public_api_json(['ok' => false, 'error' => $authorized['error']], 403);
}
$target = $authorized['owner'];
// Family reads are clamped to the granted scope and never widened by the client.
$scope = is_array($authorized['scope'] ?? null) ? $authorized['scope'] : null;
if ($scope !== null && $scope['scope_from'] !== null && $scope['scope_to'] !== null) {
    $fromIso = gmdate('c', max(strtotime($fromIso), strtotime((string)$scope['scope_from'])));
    $toIso = gmdate('c', min(strtotime($toIso), strtotime((string)$scope['scope_to'])));
    if (strtotime($fromIso) >= strtotime($toIso)) {
        public_api_json(['ok' => false, 'error' => 'outside_granted_range'], 403);
    }
}

// Cheap extent lookup, used once when the map has no saved viewport.
// It returns only a padded bounding box, never points.
if ($wantsExtent) {
    $extent = StepsGeolocationMap::dataBbox($pdo, $target, $fromIso, $toIso);
    public_api_json([
        'ok' => true,
        'bbox' => $extent,
        'range' => ['from' => $fromIso, 'to' => $toIso],
        'meta' => ['counts' => [], 'bounded' => true],
        'config' => $mapConfig,
    ]);
}

$layers = StepsGeolocationMap::layers((string)($_GET['layers'] ?? ''));
if ($scope !== null && !empty($scope['places_only'])) {
    $layers = array_values(array_intersect($layers, ['places', 'visits', 'stops']));
    if ($layers === []) $layers = ['places', 'visits'];
}
$mapOpts = [
    'zoom' => (int)($_GET['zoom'] ?? 12),
    'from' => $fromIso,
    'to' => $toIso,
    'layers' => $layers,
    'limit' => (int)($_GET['limit'] ?? StepsGeolocationMap::MAX_POINTS),
    'device_id' => (string)($_GET['device_id'] ?? ''),
    'trip_id' => (string)($_GET['trip_id'] ?? ''),
    'place_id' => (string)($_GET['place_id'] ?? ''),
    'cursor_at' => (string)($_GET['cursor_at'] ?? ''),
    'cursor_id' => (string)($_GET['cursor_id'] ?? ''),
];
$cacheKey = StepsGeolocationObservability::cacheKey('map', $target, [
    'bbox' => $bbox, 'opts' => $mapOpts, 'viewer' => $viewer,
    'precision' => $scope['precision_m'] ?? 0, 'generation' => $scope['generation'] ?? 0,
    'places_only' => $scope['places_only'] ?? false, 'filters' => $_GET['layers'] ?? '',
]);
$queryStarted = microtime(true);
$result = StepsGeolocationObservability::cacheGet($cacheKey);
$cacheHit = is_array($result) && isset($result['features']) && isset($result['meta']);
if (!$cacheHit) {
    $result = StepsGeolocationMap::features($pdo, $target, $bbox, $mapOpts);
    StepsGeolocationObservability::cacheSet($cacheKey, $result, 20);
}
StepsGeolocationObservability::increment('geo_map_requests_total', 1, ['cache' => $cacheHit ? 'hit' : 'miss']);
StepsGeolocationObservability::timing('geo_map_query_ms', (microtime(true) - $queryStarted) * 1000, ['cache' => $cacheHit ? 'hit' : 'miss']);

// Smoothed zones replace their raw point cloud with the derived routes. This is
// applied after the cached chunk so the raw cache stays reusable.
$smoothZones = StepsGeolocationSmoothing::viewport($pdo, $target, $bbox, $fromIso, $toIso);
$result = StepsGeolocationSmoothing::applyToViewport($result, $smoothZones);
// The raw chunk is cached, so a conditional request would otherwise answer 304
// right after a smoothing pass and the browser would keep drawing the old cloud.
$smoothStamp = StepsGeolocationSmoothing::signature($smoothZones);
$smoothPaths = count($smoothZones);

if ($scope !== null && (float)$scope['precision_m'] > 0) {
    $result = StepsGeolocationPermissions::applyPrecision($result, (float)$scope['precision_m']);
}

// Immich references are owner-only. Family viewers never receive photo URLs.
$photos = [];
if (in_array('photos', $layers, true) && $target === $viewer) {
    if (!function_exists('oauth_sync_immich_steps') && is_file(APP_ROOT . '/project/oauth.php')) {
        require_once APP_ROOT . '/project/oauth.php';
    }
    if (function_exists('oauth_sync_immich_steps')) {
        try { oauth_sync_immich_steps($target); } catch (Throwable $ignored) {}
    }
    $zoom = (int)($_GET['zoom'] ?? 12);
    $photos = StepsGeolocationImmich::mapClusters($pdo, $target, $bbox, $fromIso, $toIso, $zoom, 60);
    foreach ($photos as $photo) {
        $assetId = (string)$photo['asset_id'];
        $result['features'][] = [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [(float)$photo['lon'], (float)$photo['lat']]],
            'properties' => [
                'layer' => 'photo_cluster',
                'count' => (int)$photo['total'],
                'asset_id' => $assetId,
                'immich_asset_id' => (string)($photo['immich_asset_id'] ?? ''),
                'original_filename' => (string)($photo['original_filename'] ?? ''),
                'captured_at' => isset($photo['captured_at']) ? (string)$photo['captured_at'] : null,
                'thumbnail' => '/apps/steps/api/session_gateway.php?endpoint=immich_thumbnail&id=' . rawurlencode($assetId),
                'preview' => '/apps/steps/api/session_gateway.php?endpoint=immich_thumbnail&id=' . rawurlencode($assetId) . '&size=preview',
            ],
        ];
    }
}

$chunkMeta = [
    'bbox' => $bbox,
    'zoom' => (int)($mapOpts['zoom'] ?? 12),
    'layers' => $layers,
    'range' => ['from' => $fromIso, 'to' => $toIso],
    'precision_m' => $scope !== null ? (float)$scope['precision_m'] : 0,
    'generation' => $scope['generation'] ?? 0,
    'smooth' => $smoothStamp,
    'smooth_paths' => $smoothPaths,
    'ttl' => 20,
];
$etag = '"' . substr(hash('sha256', $cacheKey . '|' . json_encode($chunkMeta)), 0, 32) . '"';
$result['meta']['chunk'] = $chunkMeta + ['etag' => $etag];
header('ETag: ' . $etag);
header('Vary: Cookie, Authorization');
header('X-Steps-Map-Chunk: bounded');
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
public_api_json([
    'ok' => true,
    'geojson' => ['type' => $result['type'], 'features' => $result['features']],
    'photos' => $photos,
    'meta' => $result['meta'],
    'config' => $mapConfig,
    'bbox' => $bbox,
    'range' => ['from' => $fromIso, 'to' => $toIso],
    'owner_email' => $target,
    'family' => $target !== $viewer,
    'scope' => $scope !== null ? [
        'access_level' => $scope['access_level'],
        'precision_m' => $scope['precision_m'],
        'generation' => $scope['generation'],
        'places_only' => $scope['places_only'],
    ] : null,
    'providers' => StepsGeolocationMap::providers(),
]);
