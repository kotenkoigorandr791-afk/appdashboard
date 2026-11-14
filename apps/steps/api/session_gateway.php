<?php
/**
 * Cookie-session gateway for the web dashboard.
 *
 * /api/steps/* only accepts a mobile Bearer token, so the in-app map, timeline
 * and device screens had no way to read their data. This gateway resumes the
 * same PHP session the page uses, verifies the signed-in user, and then runs the
 * existing endpoint implementation unchanged.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once dirname(__DIR__, 3) . '/lib/app_session.php';
    app_session_start();
}

$endpoint = strtolower(trim((string)($_GET['endpoint'] ?? '')));
$allowed = [
    'geolocation_map',
    'geolocation_timeline',
    'geolocation_stops',
    'geolocation_trips',
    'geolocation_quality',
    'geolocation_statistics',
    'geolocation_imports',
    'geolocation_exports',
    'geolocation_export_download',
    'family',
    'immich',
    'immich_thumbnail',
    'places',
    'geofences',
    'devices',
    'family_permissions',
    'notifications',
    'geolocation_renders',
    'geolocation_points',
];
if (!preg_match('/^[a-z0-9_]+$/', $endpoint) || !in_array($endpoint, $allowed, true)) {
    public_api_json(['ok' => false, 'error' => 'unknown_endpoint'], 404);
}

$owner = get_current_user_email();
if (!$owner) {
    public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
}

// State-changing calls stay same-origin; SameSite=Lax already blocks most
// cross-site POSTs, and this rejects the rest before the endpoint runs.
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($origin !== '' && strcasecmp((string)parse_url($origin, PHP_URL_HOST), $host) !== 0) {
        public_api_json(['ok' => false, 'error' => 'forbidden_origin'], 403);
    }
}

$file = __DIR__ . '/' . $endpoint . '.php';
if (!is_file($file)) {
    public_api_json(['ok' => false, 'error' => 'endpoint_unavailable'], 501);
}
require $file;
