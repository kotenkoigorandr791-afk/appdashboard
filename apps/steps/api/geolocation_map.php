<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_map.php';
require_once dirname(__DIR__) . '/core/geolocation_permissions.php';
require_once dirname(__DIR__) . '/core/geolocation_immich.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$viewer = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_map', 300, 60);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (!empty($_GET['settings']) || ($method === 'POST' && ($_GET['settings'] ?? '') !== '')) {
    if ($method === 'POST') {
        $body = public_api_body();
        if (!empty($body['provider']) && !isset(StepsGeolocationMap::providers()[$body['provider']])) {
            public_api_json(['ok' => false, 'error' => 'invalid_provider'], 400);
        }
        $layers = isset($body['layers']) && is_array($body['layers']) ? $body['layers'] : null;
        $saved = StepsGeolocationMap::savePreferences($pdo, $viewer, $body['provider'] ?? null, $layers);
        public_api_json(['ok' => true, 'settings' => $saved, 'providers' => StepsGeolocationMap::providers()]);
    }
    public_api_json(['ok' => true, 'settings' => StepsGeolocationMap::preferences($pdo, $viewer), 'providers' => StepsGeolocationMap::providers()]);
}

if ($method !== 'GET') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);

$from = (string)($_GET['from'] ?? gmdate('c', time() - 30 * 86400));
$to = (string)($_GET['to'] ?? gmdate('c'));
$bbox = StepsGeolocationMap::parseBbox((string)($_GET['bbox'] ?? ''));
if ($bbox === null && trim((string)($_GET['bbox'] ?? '')) === '') {
    // No viewport yet: start from the owner's own data extent.
    $bbox = StepsGeolocationMap::dataBbox($pdo, $viewer, $from, $to);
}
if ($bbox === null) public_api_json(['ok' => false, 'error' => 'invalid_bbox'], 400);
if (strtotime($from) === false || strtotime($to) === false || strtotime($from) >= strtotime($to)) {
    public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
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

$layers = StepsGeolocationMap::layers((string)($_GET['layers'] ?? ''));
if ($scope !== null && !empty($scope['places_only'])) {
    $layers = array_values(array_intersect($layers, ['places', 'visits', 'stops']));
    if ($layers === []) $layers = ['places', 'visits'];
}
$result = StepsGeolocationMap::features($pdo, $target, $bbox, [
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
]);

if ($scope !== null && (float)$scope['precision_m'] > 0) {
    $result = StepsGeolocationPermissions::applyPrecision($result, (float)$scope['precision_m']);
}

// Immich references are owner-only. Family viewers never receive photo URLs.
$photos = [];
if (in_array('photos', $layers, true) && $target === $viewer) {
    $photos = StepsGeolocationImmich::mapClusters($pdo, $target, $bbox, $fromIso, $toIso, (int)($_GET['zoom'] ?? 12), 100);
    foreach ($photos as $photo) {
        $result['features'][] = [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [(float)$photo['lon'], (float)$photo['lat']]],
            'properties' => ['layer' => 'photo_cluster', 'count' => (int)$photo['total'], 'asset_id' => (string)$photo['asset_id'], 'thumbnail' => '/apps/steps/api/session_gateway.php?endpoint=immich_thumbnail&id=' . urlencode((string)$photo['asset_id'])],
        ];
    }
}

public_api_json([
    'ok' => true,
    'geojson' => ['type' => $result['type'], 'features' => $result['features']],
    'photos' => $photos,
    'meta' => $result['meta'],
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
