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

function geo_trips_audit(PDO $pdo, string $owner, string $event, array $context): void
{
    $stmt = $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)');
    $stmt->execute([':id' => StepsGeolocationTrips::uuid(), ':owner' => $owner, ':actor' => $owner, ':event' => $event, ':context' => json_encode($context, JSON_UNESCAPED_SLASHES)]);
}

/** @return array{geojson:?string,distance_m:float,point_count:int} */
function geo_trips_shape(PDO $pdo, string $owner, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        "SELECT ST_AsGeoJSON(ST_MakeLine(position ORDER BY source_at)) AS geojson,
                COALESCE(ST_Length(ST_MakeLine(position ORDER BY source_at)::geography), 0) AS distance_m,
                COUNT(*) AS point_count
           FROM geo_location_points
          WHERE user_email = :owner AND quality_state = 'accepted' AND source_at >= :from AND source_at <= :to"
    );
    $stmt->execute([':owner' => $owner, ':from' => $from, ':to' => $to]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return ['geojson' => $row['geojson'] ?? null, 'distance_m' => (float)($row['distance_m'] ?? 0), 'point_count' => (int)($row['point_count'] ?? 0)];
}

function geo_trips_insert(PDO $pdo, string $owner, string $from, string $to, string $reason, array $shape, string $title = ''): ?string
{
    if ($shape['geojson'] === null || $shape['point_count'] < 2) return null;
    $id = StepsGeolocationTrips::uuid();
    $stmt = $pdo->prepare(
        "INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, title, state, boundary_reason, processor_version, override_json)
         VALUES(:id, :owner, :from, :to, ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326), :distance, :title, 'ready', :reason, :version, :override)"
    );
    $stmt->execute([
        ':id' => $id,
        ':owner' => $owner,
        ':from' => $from,
        ':to' => $to,
        ':geojson' => $shape['geojson'],
        ':distance' => $shape['distance_m'],
        ':title' => $title,
        ':reason' => $reason,
        ':version' => StepsGeolocationTrips::PROCESSOR_VERSION,
        ':override' => json_encode(['source' => 'manual', 'point_count' => $shape['point_count']], JSON_UNESCAPED_SLASHES),
    ]);
    return $id;
}

/** @return array<string,mixed>|null */
function geo_trips_find(PDO $pdo, string $owner, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, started_at, ended_at, geometry IS NOT NULL AS has_geometry, distance_m, title, notes, state, boundary_reason, processor_version, override_json FROM geo_trips WHERE user_email = :owner AND id = :id AND deleted_at IS NULL');
    $stmt->execute([':owner' => $owner, ':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    if (!empty($_GET['debug']) && !empty($_GET['trip_id'])) {
        $trip = geo_trips_find($pdo, $owner, (string)$_GET['trip_id']);
        if ($trip === null) public_api_json(['ok' => false, 'error' => 'trip_not_found'], 404);
        $points = $pdo->prepare('SELECT id, device_id, source_at, accuracy_m, quality_state, ST_Y(position) AS latitude, ST_X(position) AS longitude FROM geo_location_points WHERE user_email = :owner AND source_at >= :from AND source_at <= :to ORDER BY source_at');
        $points->execute([':owner' => $owner, ':from' => $trip['started_at'], ':to' => $trip['ended_at']]);
        $rows = $points->fetchAll(PDO::FETCH_ASSOC);
        $thresholds = StepsGeolocationTrips::thresholds();
        $segment = StepsGeolocationTrips::segment($rows, $thresholds);
        public_api_json(['ok' => true, 'trip' => $trip, 'geojson' => StepsGeolocationTrips::geojson($rows, $segment['trips'], $thresholds)]);
    }
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 100;
    $stmt = $pdo->prepare("SELECT id, started_at, ended_at, distance_m, title, notes, state, boundary_reason, processor_version, override_json FROM geo_trips WHERE user_email = :owner AND deleted_at IS NULL ORDER BY started_at DESC LIMIT $limit");
    $stmt->execute([':owner' => $owner]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trips as &$trip) $trip['override_json'] = json_decode((string)$trip['override_json'], true) ?: [];
    unset($trip);
    public_api_json(['ok' => true, 'trips' => $trips]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? '')));
if ($action === 'reprocess') {
    public_api_json(['ok' => true, 'queued' => StepsGeolocationTrips::enqueue($pdo, $owner)]);
}
if (!in_array($action, ['rename', 'merge', 'split'], true)) public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);

try {
    if ($action === 'rename') {
        $id = (string)($body['id'] ?? '');
        if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
        $trip = geo_trips_find($pdo, $owner, $id);
        if ($trip === null) public_api_json(['ok' => false, 'error' => 'trip_not_found'], 404);
        $title = trim((string)($body['title'] ?? ''));
        $stmt = $pdo->prepare("UPDATE geo_trips SET title = :title, override_json = jsonb_set(COALESCE(override_json, '{}'::jsonb), '{manual}', 'true'::jsonb), updated_at = now() WHERE user_email = :owner AND id = :id");
        $stmt->execute([':title' => $title, ':owner' => $owner, ':id' => $id]);
        geo_trips_audit($pdo, $owner, 'trip.renamed', ['trip_id' => $id, 'title' => $title]);
        public_api_json(['ok' => true, 'trip' => geo_trips_find($pdo, $owner, $id)]);
    }

    if ($action === 'merge') {
        $ids = array_values(array_filter((array)($body['ids'] ?? []), static fn($v): bool => StepsGeolocationDomain::isUuid((string)$v)));
        if (count($ids) < 2) public_api_json(['ok' => false, 'error' => 'invalid_ids'], 400);
        $trips = [];
        foreach ($ids as $id) {
            $trip = geo_trips_find($pdo, $owner, $id);
            if ($trip === null) public_api_json(['ok' => false, 'error' => 'trip_not_found'], 404);
            $trips[] = $trip;
        }
        usort($trips, static fn(array $a, array $b): int => strtotime((string)$a['started_at']) <=> strtotime((string)$b['started_at']));
        $from = (string)$trips[0]['started_at'];
        $to = (string)$trips[count($trips) - 1]['ended_at'];
        $shape = geo_trips_shape($pdo, $owner, $from, $to);
        $pdo->beginTransaction();
        $idsSql = implode(',', array_map(static fn(string $id): string => "'" . $id . "'", $ids));
        $pdo->exec("DELETE FROM geo_trips WHERE user_email = " . $pdo->quote($owner) . " AND id IN ($idsSql)");
        $mergedId = geo_trips_insert($pdo, $owner, $from, $to, 'user_merge', $shape);
        if ($mergedId === null) {
            $pdo->rollBack();
            public_api_json(['ok' => false, 'error' => 'merge_failed'], 409);
        }
        $pdo->commit();
        geo_trips_audit($pdo, $owner, 'trip.merged', ['trip_ids' => $ids, 'trip_id' => $mergedId]);
        public_api_json(['ok' => true, 'trip' => geo_trips_find($pdo, $owner, $mergedId)]);
    }

    // split
    $id = (string)($body['id'] ?? '');
    $at = strtotime((string)($body['at'] ?? ''));
    if (!StepsGeolocationDomain::isUuid($id) || $at === false) public_api_json(['ok' => false, 'error' => 'invalid_request'], 400);
    $trip = geo_trips_find($pdo, $owner, $id);
    if ($trip === null) public_api_json(['ok' => false, 'error' => 'trip_not_found'], 404);
    if ($at <= strtotime((string)$trip['started_at']) || $at >= strtotime((string)$trip['ended_at'])) {
        public_api_json(['ok' => false, 'error' => 'split_outside_trip'], 409);
    }
    $splitAt = gmdate('c', $at);
    $first = geo_trips_shape($pdo, $owner, (string)$trip['started_at'], $splitAt);
    $second = geo_trips_shape($pdo, $owner, $splitAt, (string)$trip['ended_at']);
    if ($first['geojson'] === null || $second['geojson'] === null) public_api_json(['ok' => false, 'error' => 'split_failed'], 409);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE geo_trips SET ended_at = :to, geometry = ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326), distance_m = :distance, override_json = jsonb_set(COALESCE(override_json, '{}'::jsonb), '{manual}', 'true'::jsonb), updated_at = now() WHERE user_email = :owner AND id = :id");
    $stmt->execute([':to' => $splitAt, ':geojson' => $first['geojson'], ':distance' => $first['distance_m'], ':owner' => $owner, ':id' => $id]);
    $newId = geo_trips_insert($pdo, $owner, $splitAt, (string)$trip['ended_at'], 'user_split', $second);
    $pdo->commit();
    geo_trips_audit($pdo, $owner, 'trip.split', ['trip_id' => $id, 'new_trip_id' => $newId, 'at' => $splitAt]);
    public_api_json(['ok' => true, 'trips' => [geo_trips_find($pdo, $owner, $id), $newId !== null ? geo_trips_find($pdo, $owner, $newId) : null]]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('geolocation trips api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'trip_update_failed'], 500);
}
