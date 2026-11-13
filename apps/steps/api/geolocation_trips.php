<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_trips.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_trips', 120, 60);
$pdo = app_main_db()->getPdo();

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    $id = trim((string)($_GET['id'] ?? $_GET['trip_id'] ?? ''));
    if ($id !== '') {
        if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
        $trip = StepsGeolocationTrips::find($pdo, $owner, $id);
        if ($trip === null) public_api_json(['ok' => false, 'error' => 'trip_not_found'], 404);
        if (!empty($_GET['debug'])) {
            $points = $pdo->prepare('SELECT id, device_id, source_at, accuracy_m, quality_state, ST_Y(position) AS latitude, ST_X(position) AS longitude FROM geo_location_points WHERE user_email = :owner AND source_at >= :from AND source_at <= :to ORDER BY source_at');
            $points->execute([':owner' => $owner, ':from' => $trip['started_at'], ':to' => $trip['ended_at']]);
            $rows = $points->fetchAll(PDO::FETCH_ASSOC);
            $thresholds = StepsGeolocationTrips::thresholds();
            $segment = StepsGeolocationTrips::segment($rows, $thresholds);
            public_api_json(['ok' => true, 'trip' => $trip, 'geojson' => StepsGeolocationTrips::geojson($rows, $segment['trips'], $thresholds)]);
        }
        public_api_json(['ok' => true, 'trip' => $trip]);
    }

    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 100;
    $includeArchived = !empty($_GET['include_archived']);
    $stateFilter = $includeArchived ? '' : " AND state <> 'archived'";
    $stmt = $pdo->prepare(
        "SELECT id, started_at, ended_at, distance_m, duration_s, title, notes, state, boundary_reason,
                processor_version, geometry_visible, override_json
           FROM geo_trips
          WHERE user_email = :owner AND deleted_at IS NULL" . $stateFilter .
        " ORDER BY started_at DESC LIMIT $limit"
    );
    $stmt->execute([':owner' => $owner]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trips as &$trip) {
        $trip['override_json'] = json_decode((string)$trip['override_json'], true) ?: [];
        $trip['geometry_visible'] = (bool)$trip['geometry_visible'];
    }
    unset($trip);
    public_api_json(['ok' => true, 'trips' => $trips]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? '')));

try {
    if ($action === 'reprocess') {
        public_api_json(['ok' => true, 'queued' => StepsGeolocationTrips::enqueue($pdo, $owner)]);
    }
    if ($action === 'create') {
        public_api_json(['ok' => true, 'trip' => StepsGeolocationTrips::create($pdo, $owner, $body)]);
    }
    if ($action === 'update' || $action === 'rename') {
        $id = (string)($body['id'] ?? '');
        if ($action === 'rename') $body = ['title' => (string)($body['title'] ?? '')];
        public_api_json(['ok' => true, 'trip' => StepsGeolocationTrips::update($pdo, $owner, $id, $body)]);
    }
    if ($action === 'recalculate') {
        public_api_json(['ok' => true, 'trip' => StepsGeolocationTrips::recalculate($pdo, $owner, (string)($body['id'] ?? ''))]);
    }
    if ($action === 'archive' || $action === 'delete') {
        StepsGeolocationTrips::archive($pdo, $owner, (string)($body['id'] ?? ''), $action === 'delete');
        public_api_json(['ok' => true]);
    }
    if ($action === 'merge') {
        public_api_json(['ok' => true, 'trip' => StepsGeolocationTrips::merge($pdo, $owner, (array)($body['ids'] ?? []))]);
    }
    if ($action === 'split') {
        public_api_json(['ok' => true, 'trips' => StepsGeolocationTrips::split($pdo, $owner, (string)($body['id'] ?? ''), (string)($body['at'] ?? ''))]);
    }
    public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
} catch (StepsGeolocationTripError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
} catch (Throwable $e) {
    error_log('geolocation trips api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'trip_update_failed'], 500);
}
