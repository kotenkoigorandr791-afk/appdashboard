<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_places.php';
require_once dirname(__DIR__) . '/core/geolocation_stops.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_places', 120, 60);
$pdo = app_main_db()->getPdo();

function geo_places_shape(array $row): array
{
    return [
        'id' => (string)$row['id'],
        'name' => (string)$row['name'],
        'tag' => (string)$row['tag'],
        'latitude' => (float)$row['latitude'],
        'longitude' => (float)$row['longitude'],
        'radius_m' => (float)$row['radius_m'],
        'color' => (string)($row['color'] ?? ''),
        'notes' => (string)($row['notes'] ?? ''),
        'timezone' => (string)$row['timezone'],
        'is_active' => (bool)$row['is_active'],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function geo_places_find(PDO $pdo, string $owner, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, tag, ST_Y(position) AS latitude, ST_X(position) AS longitude, radius_m, color, notes, timezone, is_active, created_at, updated_at FROM geo_places WHERE user_email = :owner AND id = :id AND deleted_at IS NULL');
    $stmt->execute([':owner' => $owner, ':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function geo_places_audit(PDO $pdo, string $owner, string $event, array $context): void
{
    $stmt = $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)');
    $stmt->execute([':id' => StepsGeolocationPlaces::uuid(), ':owner' => $owner, ':actor' => $owner, ':event' => $event, ':context' => json_encode($context, JSON_UNESCAPED_SLASHES)]);
}

function geo_places_singleton_conflict(PDO $pdo, string $owner, string $tag, string $exceptId = ''): bool
{
    if (!StepsGeolocationPlaces::isSingletonTag($tag)) return false;
    $stmt = $pdo->prepare('SELECT 1 FROM geo_places WHERE user_email = :owner AND tag = :tag AND is_active AND deleted_at IS NULL AND id <> :id LIMIT 1');
    $stmt->execute([':owner' => $owner, ':tag' => $tag, ':id' => $exceptId === '' ? '00000000-0000-4000-8000-000000000000' : $exceptId]);
    return (bool)$stmt->fetchColumn();
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT id, name, tag, ST_Y(position) AS latitude, ST_X(position) AS longitude, radius_m, color, notes, timezone, is_active, created_at, updated_at FROM geo_places WHERE user_email = :owner AND deleted_at IS NULL ORDER BY is_active DESC, name ASC');
    $stmt->execute([':owner' => $owner]);
    public_api_json(['ok' => true, 'places' => array_map('geo_places_shape', $stmt->fetchAll(PDO::FETCH_ASSOC)), 'tag_version' => StepsGeolocationPlaces::TAG_VERSION, 'builtin_tags' => StepsGeolocationPlaces::BUILTIN_TAGS]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? '')));

try {
    if ($action === 'delete') {
        $id = trim((string)($body['id'] ?? ''));
        if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
        $place = geo_places_find($pdo, $owner, $id);
        if ($place === null) public_api_json(['ok' => false, 'error' => 'place_not_found'], 404);
        $stmt = $pdo->prepare('UPDATE geo_places SET is_active = false, deleted_at = now(), updated_at = now() WHERE user_email = :owner AND id = :id');
        $stmt->execute([':owner' => $owner, ':id' => $id]);
        geo_places_audit($pdo, $owner, 'place.deleted', ['place_id' => $id, 'tag' => $place['tag']]);
        try { StepsGeolocationStops::enqueue($pdo, $owner); } catch (Throwable $e) { error_log('place recalculation enqueue: ' . $e->getMessage()); }
        public_api_json(['ok' => true, 'deleted' => true]);
    }

    if (!in_array($action, ['create', 'update'], true)) public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
    $id = trim((string)($body['id'] ?? ''));
    $existing = null;
    if ($action === 'update') {
        if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
        $existing = geo_places_find($pdo, $owner, $id);
        if ($existing === null) public_api_json(['ok' => false, 'error' => 'place_not_found'], 404);
    }

    $validated = StepsGeolocationPlaces::validate($body, $action === 'update');
    if ($validated['place'] === null) public_api_json(['ok' => false, 'error' => 'invalid_place', 'details' => $validated['errors']], 400);
    $place = $existing !== null ? array_merge($existing, $validated['place']) : $validated['place'];

    if (($place['is_active'] ?? true) && geo_places_singleton_conflict($pdo, $owner, (string)$place['tag'], $existing['id'] ?? '')) {
        public_api_json(['ok' => false, 'error' => 'duplicate_' . $place['tag'], 'tag' => $place['tag']], 409);
    }

    if ($action === 'create') {
        $newId = StepsGeolocationPlaces::uuid();
        $stmt = $pdo->prepare(
            "INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, color, notes, timezone, is_active)
             VALUES(:id, :owner, :name, :tag, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), :radius, :color, :notes, :timezone, :active)"
        );
        $stmt->execute([
            ':id' => $newId,
            ':owner' => $owner,
            ':name' => $place['name'],
            ':tag' => $place['tag'],
            ':lon' => $place['longitude'],
            ':lat' => $place['latitude'],
            ':radius' => $place['radius_m'],
            ':color' => $place['color'] ?? '',
            ':notes' => $place['notes'] ?? '',
            ':timezone' => $place['timezone'] ?? 'UTC',
            ':active' => $place['is_active'] ?? true,
        ]);
        geo_places_audit($pdo, $owner, 'place.created', ['place_id' => $newId, 'tag' => $place['tag']]);
        try { StepsGeolocationStops::enqueue($pdo, $owner); } catch (Throwable $e) { error_log('place recalculation enqueue: ' . $e->getMessage()); }
        public_api_json(['ok' => true, 'place' => geo_places_find($pdo, $owner, $newId)]);
    }

    $stmt = $pdo->prepare(
        "UPDATE geo_places
            SET name = :name, tag = :tag, position = ST_SetSRID(ST_MakePoint(:lon, :lat), 4326),
                radius_m = :radius, color = :color, notes = :notes, timezone = :timezone,
                is_active = :active, updated_at = now()
          WHERE user_email = :owner AND id = :id"
    );
    $stmt->execute([
        ':name' => $place['name'],
        ':tag' => $place['tag'],
        ':lon' => $place['longitude'],
        ':lat' => $place['latitude'],
        ':radius' => $place['radius_m'],
        ':color' => $place['color'] ?? '',
        ':notes' => $place['notes'] ?? '',
        ':timezone' => $place['timezone'] ?? 'UTC',
        ':active' => $place['is_active'] ?? true,
        ':owner' => $owner,
        ':id' => $id,
    ]);
    geo_places_audit($pdo, $owner, 'place.updated', ['place_id' => $id, 'tag' => $place['tag']]);
    try { StepsGeolocationStops::enqueue($pdo, $owner); } catch (Throwable $e) { error_log('place recalculation enqueue: ' . $e->getMessage()); }
    public_api_json(['ok' => true, 'place' => geo_places_find($pdo, $owner, $id)]);
} catch (Throwable $e) {
    error_log('geolocation places api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'place_update_failed'], 500);
}
