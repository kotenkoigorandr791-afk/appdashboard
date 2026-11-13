<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_stops.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_stops', 120, 60);
$pdo = app_main_db()->getPdo();

function geo_stops_audit(PDO $pdo, string $owner, string $event, array $context): void
{
    $stmt = $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)');
    $stmt->execute([':id' => StepsGeolocationStops::uuid(), ':owner' => $owner, ':actor' => $owner, ':event' => $event, ':context' => json_encode($context, JSON_UNESCAPED_SLASHES)]);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 100;
    $visits = $pdo->prepare(
        "SELECT v.id, v.place_id, v.stop_id, v.arrived_at, v.departed_at, v.confidence, v.state, v.override_json,
                p.name AS place_name, p.tag AS place_tag, p.color AS place_color,
                ST_Y(s.position) AS latitude, ST_X(s.position) AS longitude, s.radius_m, s.confidence AS stop_confidence
           FROM geo_visits v
      LEFT JOIN geo_places p ON p.id = v.place_id
      LEFT JOIN geo_stops s ON s.id = v.stop_id
          WHERE v.user_email = :owner
       ORDER BY v.arrived_at DESC
          LIMIT $limit"
    );
    $visits->execute([':owner' => $owner]);
    $rows = $visits->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) $row['override_json'] = json_decode((string)$row['override_json'], true) ?: [];
    unset($row);
    public_api_json(['ok' => true, 'visits' => $rows]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? '')));
$id = trim((string)($body['id'] ?? ''));
if (!in_array($action, ['confirm', 'reject', 'delete', 'correct'], true)) public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);

$find = $pdo->prepare('SELECT id, place_id, stop_id, arrived_at, departed_at, override_json FROM geo_visits WHERE user_email = :owner AND id = :id');
$find->execute([':owner' => $owner, ':id' => $id]);
$visit = $find->fetch(PDO::FETCH_ASSOC);
if ($visit === false) public_api_json(['ok' => false, 'error' => 'visit_not_found'], 404);

try {
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM geo_visits WHERE user_email = :owner AND id = :id');
        $stmt->execute([':owner' => $owner, ':id' => $id]);
        geo_stops_audit($pdo, $owner, 'visit.deleted', ['visit_id' => $id]);
        public_api_json(['ok' => true, 'deleted' => true]);
    }

    $override = json_decode((string)$visit['override_json'], true) ?: [];
    $override['manual'] = true;
    $override['review'] = $action === 'confirm' ? 'confirmed' : ($action === 'reject' ? 'rejected' : 'corrected');
    $placeId = $visit['place_id'];
    $arrived = (string)$visit['arrived_at'];
    $departed = $visit['departed_at'] !== null ? (string)$visit['departed_at'] : null;

    if ($action === 'correct') {
        if (array_key_exists('place_id', $body)) {
            $placeId = trim((string)$body['place_id']);
            if ($placeId !== '') {
                $check = $pdo->prepare('SELECT 1 FROM geo_places WHERE user_email = :owner AND id = :id AND deleted_at IS NULL');
                $check->execute([':owner' => $owner, ':id' => $placeId]);
                if (!$check->fetchColumn()) public_api_json(['ok' => false, 'error' => 'place_not_found'], 404);
            } else {
                $placeId = null;
            }
        }
        if (!empty($body['arrived_at']) && strtotime((string)$body['arrived_at']) !== false) $arrived = gmdate('c', strtotime((string)$body['arrived_at']));
        if (!empty($body['departed_at']) && strtotime((string)$body['departed_at']) !== false) $departed = gmdate('c', strtotime((string)$body['departed_at']));
        if ($departed !== null && strtotime($departed) < strtotime($arrived)) public_api_json(['ok' => false, 'error' => 'invalid_interval'], 409);
    }

    $stmt = $pdo->prepare('UPDATE geo_visits SET place_id = :place, arrived_at = :arrived, departed_at = :departed, override_json = :override, updated_at = now() WHERE user_email = :owner AND id = :id');
    $stmt->execute([
        ':place' => $placeId,
        ':arrived' => $arrived,
        ':departed' => $departed,
        ':override' => json_encode($override, JSON_UNESCAPED_SLASHES),
        ':owner' => $owner,
        ':id' => $id,
    ]);
    geo_stops_audit($pdo, $owner, 'visit.' . $action . 'ed', ['visit_id' => $id, 'place_id' => $placeId]);
    public_api_json(['ok' => true, 'visit_id' => $id, 'review' => $override['review']]);
} catch (Throwable $e) {
    error_log('geolocation stops api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'visit_update_failed'], 500);
}
