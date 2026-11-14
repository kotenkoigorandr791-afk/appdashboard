<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_timeline.php';
require_once dirname(__DIR__) . '/core/geolocation_immich.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_timeline', 300, 60);
$pdo = app_main_db()->getPdo();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

if (!empty($_GET['timezones'])) {
    $zones = timezone_identifiers_list();
    public_api_json(['ok' => true, 'timezones' => $zones, 'default' => 'UTC']);
}

$view = strtolower(trim((string)($_GET['view'] ?? 'day')));
if (!in_array($view, StepsGeolocationTimeline::VIEWS, true)) {
    public_api_json(['ok' => false, 'error' => 'invalid_view'], 400);
}
$timezone = StepsGeolocationTimeline::timezone((string)($_GET['tz'] ?? 'UTC'));
$include = isset($_GET['include'])
    ? array_values(array_intersect(array_filter(array_map('trim', explode(',', strtolower((string)$_GET['include'])))), ['trips', 'stops', 'visits', 'gaps']))
    : ['trips', 'stops', 'visits', 'gaps'];
if ($include === []) public_api_json(['ok' => false, 'error' => 'invalid_include'], 400);

try {
    $result = StepsGeolocationTimeline::entries($pdo, $owner, [
        'view' => $view,
        'at' => isset($_GET['at']) ? (string)$_GET['at'] : null,
        'from' => isset($_GET['from']) ? (string)$_GET['from'] : null,
        'to' => isset($_GET['to']) ? (string)$_GET['to'] : null,
        'timezone' => $timezone,
        'cursor' => isset($_GET['cursor']) ? (string)$_GET['cursor'] : '',
        'limit' => (int)($_GET['limit'] ?? 50),
        'q' => (string)($_GET['q'] ?? ''),
        'place_id' => (string)($_GET['place_id'] ?? ''),
        'device_id' => (string)($_GET['device_id'] ?? ''),
        'include' => $include,
    ]);
} catch (Throwable $e) {
    public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
}

$photos = StepsGeolocationImmich::timelinePhotos($pdo, $owner, $result['range']['from'], $result['range']['to']);
public_api_json([
    'ok' => true,
    'entries' => $result['entries'],
    'photos' => $photos,
    'next_cursor' => $result['next_cursor'],
    'range' => $result['range'],
    'processing' => $result['processing'],
    'timezone' => $result['range']['timezone'],
]);
