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
    $rangeSql = '';
    $params = [':owner' => $owner];
    $rangeFrom = trim((string)($_GET['from'] ?? ''));
    $rangeTo = trim((string)($_GET['to'] ?? ''));
    if ($rangeFrom !== '' && $rangeTo !== '' && strtotime($rangeFrom) !== false && strtotime($rangeTo) !== false) {
        $rangeSql = ' AND started_at <= :to AND ended_at >= :from';
        $params[':from'] = gmdate('c', strtotime($rangeFrom));
        $params[':to'] = gmdate('c', strtotime($rangeTo));
    }
    $stmt = $pdo->prepare(
        "SELECT id, started_at, ended_at, distance_m, duration_s, title, notes, state, boundary_reason,
                processor_version, geometry_visible, override_json
           FROM geo_trips
          WHERE user_email = :owner AND deleted_at IS NULL" . $stateFilter . $rangeSql .
        " ORDER BY started_at DESC LIMIT $limit"
    );
    $stmt->execute($params);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trips as &$trip) {
        $trip['override_json'] = json_decode((string)$trip['override_json'], true) ?: [];
        $trip['geometry_visible'] = !in_array($trip['geometry_visible'], [false, 0, '0', 'f', 'false'], true);
    }
    unset($trip);
    public_api_json(['ok' => true, 'trips' => $trips]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? '')));

try {
    if ($action === 'from_points') {
        require_once dirname(__DIR__) . '/core/geolocation_map.php';
        $from = gmdate('c', strtotime((string)($body['from'] ?? '')) ?: time() - 86400);
        $to = gmdate('c', strtotime((string)($body['to'] ?? '')) ?: time());
        $stmt = $pdo->prepare(StepsGeolocationMap::trackCte(false) . ' SELECT MIN(source_at) AS s, MAX(source_at) AS e, COUNT(*) AS n FROM grouped GROUP BY grp HAVING COUNT(*) >= 3 ORDER BY MIN(source_at) LIMIT 60');
        $stmt->execute([':owner' => $owner, ':from' => $from, ':to' => $to]);
        $cover = $pdo->prepare("SELECT COALESCE(SUM(GREATEST(0, EXTRACT(EPOCH FROM (LEAST(ended_at, :e::timestamptz) - GREATEST(started_at, :s::timestamptz))))), 0) FROM geo_trips WHERE user_email = :owner AND deleted_at IS NULL AND state <> 'archived' AND started_at <= :e2::timestamptz AND ended_at >= :s2::timestamptz");
        $created = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $span = max(1, strtotime($g['e']) - strtotime($g['s']));
            $cover->execute([':e' => $g['e'], ':s' => $g['s'], ':e2' => $g['e'], ':s2' => $g['s'], ':owner' => $owner]);
            if ((float)$cover->fetchColumn() >= $span * 0.5) continue;
            try {
                StepsGeolocationTrips::create($pdo, $owner, ['from' => $g['s'], 'to' => $g['e'], 'title' => '']);
                $created++;
            } catch (StepsGeolocationTripError $e) {
            }
        }
        public_api_json(['ok' => true, 'created' => $created]);
    }
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
