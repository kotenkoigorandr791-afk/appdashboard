<?php
/**
 * Versioned raw/improved/compare geometry endpoint (task 34).
 *
 * Every response includes raw coordinates, derived coordinates and the reason
 * for an abstention. The caller cannot request another owner's geometry; family
 * viewers must pass through the normal family authorization before this API is
 * allowed to return derived lines.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_matching.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_matching', 60, 60);
$pdo = app_main_db()->getPdo();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $body = public_api_body();
    $action = strtolower(trim((string)($body['action'] ?? '')));
    if ($action === 'settings') {
        public_api_json(['ok' => true, 'settings' => StepsGeolocationMatching::saveSettings($pdo, $owner, $body)]);
    }
    if ($action === 'run') {
        public_api_json(['ok' => true, 'queued' => StepsGeolocationMatching::enqueue($pdo, $owner)]);
    }
    public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
}

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
if ($from !== '' && strtotime($from) === false) public_api_json(['ok' => false, 'error' => 'invalid_from'], 400);
if ($to !== '' && strtotime($to) === false) public_api_json(['ok' => false, 'error' => 'invalid_to'], 400);
if ($from !== '' && $to !== '' && strtotime($to) <= strtotime($from)) public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
$mode = strtolower(trim((string)($_GET['geometry'] ?? '')));
if ($mode !== '' && !in_array($mode, StepsGeolocationMatching::GEOMETRY_MODES, true)) public_api_json(['ok' => false, 'error' => 'invalid_geometry_mode'], 400);

$settings = StepsGeolocationMatching::settings($pdo, $owner);
$segments = StepsGeolocationMatching::segments($pdo, $owner, [
    'from' => $from,
    'to' => $to,
    'trip_id' => trim((string)($_GET['trip_id'] ?? '')),
    'limit' => $_GET['limit'] ?? 50,
]);
public_api_json([
    'ok' => true,
    'settings' => $settings,
    'selected_geometry' => $mode !== '' ? $mode : $settings['geometry_mode'],
    'matcher_version' => StepsGeolocationMapMatcher::VERSION,
    'segments' => $segments,
    'summary' => StepsGeolocationMatching::summary($pdo, $owner),
    'defaults' => ['mode' => StepsGeolocationMatching::MODE_CONSERVATIVE, 'geometry_mode' => 'raw'],
]);
