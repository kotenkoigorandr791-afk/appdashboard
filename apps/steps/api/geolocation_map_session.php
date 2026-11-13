<?php
/**
 * Web (cookie-session) counterpart of api/geolocation_map.php.
 *
 * The public /api/steps/* route only accepts a mobile Bearer token, so the
 * in-app map had no way to read location data. This endpoint exposes the same
 * viewport query to an authenticated dashboard session only.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_map.php';

$owner = get_current_user_email();
if (!$owner) {
    public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
}
$viewer = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_map_web', 300, 60);
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

if ($method !== 'GET') {
    public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$from = (string)($_GET['from'] ?? gmdate('c', time() - 30 * 86400));
$to = (string)($_GET['to'] ?? gmdate('c'));
$bbox = StepsGeolocationMap::parseBbox((string)($_GET['bbox'] ?? ''));
if ($bbox === null && trim((string)($_GET['bbox'] ?? '')) === '') {
    $bbox = StepsGeolocationMap::dataBbox($pdo, $viewer, $from, $to);
}
if ($bbox === null) {
    public_api_json(['ok' => false, 'error' => 'invalid_bbox'], 400);
}
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

$layers = StepsGeolocationMap::layers((string)($_GET['layers'] ?? ''));
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

public_api_json([
    'ok' => true,
    'geojson' => ['type' => $result['type'], 'features' => $result['features']],
    'meta' => $result['meta'],
    'bbox' => $bbox,
    'range' => ['from' => $fromIso, 'to' => $toIso],
    'owner_email' => $target,
    'family' => $target !== $viewer,
    'providers' => StepsGeolocationMap::providers(),
]);
