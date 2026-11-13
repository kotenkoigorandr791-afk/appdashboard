<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_geofences.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_geofences', 120, 60);
$pdo = app_main_db()->getPdo();

function geo_geofences_audit(PDO $pdo, string $owner, string $event, array $context): void
{
    $stmt = $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)');
    $stmt->execute([':id' => StepsGeolocationGeofences::uuid(), ':owner' => $owner, ':actor' => $owner, ':event' => $event, ':context' => json_encode($context, JSON_UNESCAPED_SLASHES)]);
}

function geo_geofences_find(PDO $pdo, string $owner, string $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, place_id, is_active, notification_json,
                ST_Y(ST_Centroid(geometry)) AS latitude, ST_X(ST_Centroid(geometry)) AS longitude,
                GREATEST(1, ST_Distance(ST_Centroid(geometry)::geography, ST_StartPoint(ST_ExteriorRing(geometry))::geography)) AS radius_m
           FROM geo_geofences WHERE user_email = :owner AND id = :id'
    );
    $stmt->execute([':owner' => $owner, ':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) return null;
    $row['notification_json'] = json_decode((string)$row['notification_json'], true) ?: [];
    return $row;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    if (!empty($_GET['events'])) {
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 100;
        $stmt = $pdo->prepare(
            "SELECT e.id, e.geofence_id, e.point_id, e.event_type, e.occurred_at, e.confidence, g.name AS geofence_name
               FROM geo_geofence_events e JOIN geo_geofences g ON g.id = e.geofence_id
              WHERE e.user_email = :owner ORDER BY e.occurred_at DESC LIMIT $limit"
        );
        $stmt->execute([':owner' => $owner]);
        public_api_json(['ok' => true, 'events' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    $stmt = $pdo->prepare(
        'SELECT id, name, place_id, is_active, notification_json,
                ST_Y(ST_Centroid(geometry)) AS latitude, ST_X(ST_Centroid(geometry)) AS longitude,
                GREATEST(1, ST_Distance(ST_Centroid(geometry)::geography, ST_StartPoint(ST_ExteriorRing(geometry))::geography)) AS radius_m
           FROM geo_geofences WHERE user_email = :owner ORDER BY name ASC'
    );
    $stmt->execute([':owner' => $owner]);
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($zones as &$zone) $zone['notification_json'] = json_decode((string)$zone['notification_json'], true) ?: [];
    unset($zone);
    public_api_json(['ok' => true, 'geofences' => $zones]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? '')));

try {
    if ($action === 'reprocess') {
        public_api_json(['ok' => true, 'queued' => StepsGeolocationGeofences::enqueue($pdo, $owner)]);
    }

    $id = trim((string)($body['id'] ?? ''));
    if (in_array($action, ['update', 'delete'], true) && !StepsGeolocationDomain::isUuid($id)) {
        public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
    }
    if ($action !== 'create' && $action !== 'update' && $action !== 'delete') {
        public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
    }

    if ($action === 'delete') {
        $zone = geo_geofences_find($pdo, $owner, $id);
        if ($zone === null) public_api_json(['ok' => false, 'error' => 'geofence_not_found'], 404);
        $pdo->prepare('UPDATE geo_geofences SET is_active = false, updated_at = now() WHERE user_email = :owner AND id = :id')
            ->execute([':owner' => $owner, ':id' => $id]);
        geo_geofences_audit($pdo, $owner, 'geofence.disabled', ['geofence_id' => $id]);
        public_api_json(['ok' => true, 'disabled' => true]);
    }

    $name = trim((string)($body['name'] ?? ''));
    $lat = $body['latitude'] ?? null;
    $lon = $body['longitude'] ?? null;
    $radius = $body['radius_m'] ?? null;
    if ($name === '') public_api_json(['ok' => false, 'error' => 'invalid_name'], 400);
    if (!is_numeric($lat) || !is_numeric($lon) || (float)$lat < -90 || (float)$lat > 90 || (float)$lon < -180 || (float)$lon > 180) {
        public_api_json(['ok' => false, 'error' => 'invalid_coordinates'], 400);
    }
    if (!is_numeric($radius) || (float)$radius < 25 || (float)$radius > 5000) public_api_json(['ok' => false, 'error' => 'invalid_radius'], 400);

    $notifications = [
        'notify' => (bool)($body['notify'] ?? true),
        'webhook' => (bool)($body['webhook'] ?? false),
        'quiet_hours' => is_array($body['quiet_hours'] ?? null) ? $body['quiet_hours'] : null,
    ];
    $placeId = trim((string)($body['place_id'] ?? ''));
    if ($placeId !== '') {
        $check = $pdo->prepare('SELECT 1 FROM geo_places WHERE user_email = :owner AND id = :id AND deleted_at IS NULL');
        $check->execute([':owner' => $owner, ':id' => $placeId]);
        if (!$check->fetchColumn()) public_api_json(['ok' => false, 'error' => 'place_not_found'], 404);
    } else {
        $placeId = null;
    }

    $isActive = array_key_exists('is_active', $body) ? (bool)$body['is_active'] : true;
    if ($action === 'create') {
        $newId = StepsGeolocationGeofences::uuid();
        $stmt = $pdo->prepare(
            "INSERT INTO geo_geofences(id, user_email, place_id, name, geometry, is_active, notification_json)
             VALUES(:id, :owner, :place, :name, ST_Buffer(ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography, :radius)::geometry, :active, :notifications)"
        );
        $stmt->execute([
            ':id' => $newId,
            ':owner' => $owner,
            ':place' => $placeId,
            ':name' => $name,
            ':lon' => (float)$lon,
            ':lat' => (float)$lat,
            ':radius' => (float)$radius,
            ':active' => $isActive,
            ':notifications' => json_encode($notifications, JSON_UNESCAPED_SLASHES),
        ]);
        geo_geofences_audit($pdo, $owner, 'geofence.created', ['geofence_id' => $newId, 'radius_m' => (float)$radius]);
        public_api_json(['ok' => true, 'geofence' => geo_geofences_find($pdo, $owner, $newId)]);
    }

    $zone = geo_geofences_find($pdo, $owner, $id);
    if ($zone === null) public_api_json(['ok' => false, 'error' => 'geofence_not_found'], 404);
    $stmt = $pdo->prepare(
        "UPDATE geo_geofences
            SET name = :name, place_id = :place, is_active = :active,
                geometry = ST_Buffer(ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography, :radius)::geometry,
                notification_json = :notifications, updated_at = now()
          WHERE user_email = :owner AND id = :id"
    );
    $stmt->execute([
        ':name' => $name,
        ':place' => $placeId,
        ':active' => $isActive,
        ':lon' => (float)$lon,
        ':lat' => (float)$lat,
        ':radius' => (float)$radius,
        ':notifications' => json_encode($notifications, JSON_UNESCAPED_SLASHES),
        ':owner' => $owner,
        ':id' => $id,
    ]);
    // Geometry changes invalidate the affected event range.
    $pdo->prepare('DELETE FROM geo_geofence_events WHERE user_email = :owner AND geofence_id = :id')->execute([':owner' => $owner, ':id' => $id]);
    StepsGeolocationGeofences::enqueue($pdo, $owner);
    geo_geofences_audit($pdo, $owner, 'geofence.updated', ['geofence_id' => $id, 'radius_m' => (float)$radius]);
    public_api_json(['ok' => true, 'geofence' => geo_geofences_find($pdo, $owner, $id)]);
} catch (Throwable $e) {
    error_log('geolocation geofences api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'geofence_update_failed'], 500);
}
