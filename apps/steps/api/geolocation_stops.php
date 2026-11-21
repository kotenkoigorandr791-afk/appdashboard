<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_stops.php';
require_once dirname(__DIR__) . '/core/geolocation_place_resolver.php';
require_once dirname(__DIR__) . '/core/geolocation_observability.php';
require_once dirname(__DIR__) . '/core/geolocation_place_photos.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_stops', 120, 60);
steps_geolocation_migrate(app_main_db());
$pdo = app_main_db()->getPdo();

function geo_stops_audit(PDO $pdo, string $owner, string $event, array $context): void
{
    $stmt = $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :owner, :actor, :event, :context)');
    $stmt->execute([':id' => StepsGeolocationStops::uuid(), ':owner' => $owner, ':actor' => $owner, ':event' => $event, ':context' => json_encode($context, JSON_UNESCAPED_SLASHES)]);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 100;
    $only = trim((string)($_GET['id'] ?? ''));
    $where = 'v.user_email = :owner';
    $params = [':owner' => $owner];
    if ($only !== '') {
        if (!StepsGeolocationDomain::isUuid($only)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
        $where .= ' AND v.id = :id';
        $params[':id'] = $only;
    }
    $visits = $pdo->prepare(
        "SELECT v.id, v.place_id, v.stop_id, v.arrived_at, v.departed_at, v.confidence, v.state, v.override_json,
                v.osm_type, v.osm_id, v.display_name, v.category, v.address, v.detection_status, v.detection_method, v.resolved_at,
                p.name AS place_name, p.tag AS place_tag, p.color AS place_color,
                ph.status AS photo_status, ph.attribution AS photo_attribution, ph.license_name AS photo_license,
                ST_Y(s.position) AS latitude, ST_X(s.position) AS longitude, s.radius_m, s.confidence AS stop_confidence,
                GREATEST(0, EXTRACT(EPOCH FROM (COALESCE(v.departed_at, v.arrived_at) - v.arrived_at))::int) AS duration_s
           FROM geo_visits v
      LEFT JOIN geo_places p ON p.id = v.place_id
      LEFT JOIN geo_stops s ON s.id = v.stop_id
      LEFT JOIN geo_osm_place_photos ph ON ph.osm_type = v.osm_type AND ph.osm_id = v.osm_id
          WHERE $where
       ORDER BY v.arrived_at DESC
          LIMIT $limit"
    );
    $visits->execute($params);
    $rows = $visits->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_column($rows, 'id');
    $byVisit = [];
    if ($ids !== []) {
        $in = implode(',', array_map([$pdo, 'quote'], $ids));
        $cand = $pdo->prepare("SELECT visit_id, id, osm_type, osm_id, rank, score, selected, details_json FROM geo_visit_candidates WHERE user_email = :owner AND visit_id IN ($in) AND rank <= " . ($only !== '' ? 20 : 5) . ' ORDER BY visit_id, rank');
        $cand->execute([':owner' => $owner]);
        foreach ($cand->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $d = json_decode((string)$c['details_json'], true) ?: [];
            $byVisit[$c['visit_id']][] = ['id' => (string)$c['id'], 'osm_type' => $c['osm_type'], 'osm_id' => (int)$c['osm_id'], 'rank' => (int)$c['rank'], 'score' => (float)$c['score'], 'selected' => (bool)$c['selected']] + $d;
        }
    }
    foreach ($rows as &$row) {
        $row['override_json'] = json_decode((string)$row['override_json'], true) ?: [];
        $row['place_name'] = $row['display_name'] !== '' ? $row['display_name'] : $row['place_name'];
        $row['candidates'] = $byVisit[$row['id']] ?? [];
        $row['duration_s'] = (int)$row['duration_s'];
        $row['photo_url'] = null;
        if (($row['photo_status'] ?? '') === 'ready' && $row['osm_type'] && $row['osm_id'] !== null
            && StepsGeolocationPlacePhotos::isReadyOnDisk((string)$row['osm_type'], (int)$row['osm_id'])) {
            // Cookie session (web) needs session_gateway; Bearer clients still
            // accept the same path via gateway when opened in WebView with cookies,
            // and Android loads bytes through the authenticated API client separately.
            $row['photo_url'] = StepsGeolocationPlacePhotos::publicUrl((string)$row['osm_type'], (int)$row['osm_id']);
            $row['photo_api_url'] = StepsGeolocationPlacePhotos::apiUrl((string)$row['osm_type'], (int)$row['osm_id']);
        }
    }
    unset($row);
    public_api_json(['ok' => true, 'visits' => $rows, 'profile' => StepsGeolocationPlaceResolver::profile($pdo, $owner)]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? '')));
$id = trim((string)($body['id'] ?? ''));
if ($action === 'refresh_photo') {
    $osmType = strtolower(trim((string)($body['osm_type'] ?? '')));
    $osmId = (int)($body['osm_id'] ?? 0);
    if ($osmType === '' || $osmId <= 0) {
        if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
        $look = $pdo->prepare('SELECT osm_type, osm_id FROM geo_visits WHERE user_email = :owner AND id = :id');
        $look->execute([':owner' => $owner, ':id' => $id]);
        $v = $look->fetch(PDO::FETCH_ASSOC);
        if ($v === false || $v['osm_id'] === null) public_api_json(['ok' => false, 'error' => 'no_osm'], 404);
        $osmType = (string)$v['osm_type'];
        $osmId = (int)$v['osm_id'];
    }
    StepsGeolocationPlacePhotos::requestRefresh($pdo, $osmType, $osmId);
    geo_stops_audit($pdo, $owner, 'place.photo_refresh', ['osm_type' => $osmType, 'osm_id' => $osmId]);
    public_api_json(['ok' => true, 'status' => 'queued', 'osm_type' => $osmType, 'osm_id' => $osmId]);
}
if (!in_array($action, ['confirm', 'reject', 'delete', 'correct', 'select_candidate', 'assign', 'block', 'retry'], true)) public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);

$find = $pdo->prepare('SELECT id, place_id, stop_id, arrived_at, departed_at, override_json FROM geo_visits WHERE user_email = :owner AND id = :id');
$find->execute([':owner' => $owner, ':id' => $id]);
$visit = $find->fetch(PDO::FETCH_ASSOC);
if ($visit === false) public_api_json(['ok' => false, 'error' => 'visit_not_found'], 404);

try {
    if ($action === 'select_candidate') {
        $res = StepsGeolocationPlaceResolver::selectCandidate($pdo, $owner, $id, trim((string)($body['candidate_id'] ?? '')));
        geo_stops_audit($pdo, $owner, 'visit.candidate_selected', ['visit_id' => $id]);
        StepsGeolocationObservability::invalidateOwner($owner);
        public_api_json(['ok' => true, 'visit_id' => $id] + $res);
    }
    if ($action === 'assign') {
        $res = StepsGeolocationPlaceResolver::assign($pdo, $owner, $id, $body);
        geo_stops_audit($pdo, $owner, 'visit.assigned', ['visit_id' => $id, 'place_id' => $res['place_id']]);
        StepsGeolocationObservability::invalidateOwner($owner);
        public_api_json(['ok' => true, 'visit_id' => $id] + $res);
    }
    if ($action === 'block') {
        $res = StepsGeolocationPlaceResolver::block($pdo, $owner, $id);
        geo_stops_audit($pdo, $owner, 'visit.blocked', ['visit_id' => $id]);
        StepsGeolocationObservability::invalidateOwner($owner);
        public_api_json(['ok' => true, 'visit_id' => $id] + $res);
    }
    if ($action === 'retry') {
        StepsGeolocationPlaceResolver::retry($pdo, $owner, $id);
        StepsGeolocationObservability::invalidateOwner($owner);
        public_api_json(['ok' => true, 'visit_id' => $id, 'status' => 'pending']);
    }
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
    if ($action === 'confirm') {
        $pdo->prepare("UPDATE geo_visits SET detection_status = 'confirmed' WHERE user_email = :owner AND id = :id AND (place_id IS NOT NULL OR osm_id IS NOT NULL)")->execute([':owner' => $owner, ':id' => $id]);
    } elseif ($action === 'correct' && $placeId !== null) {
        $pdo->prepare("UPDATE geo_visits v SET display_name = p.name, category = p.tag, osm_type = NULL, osm_id = NULL, detection_status = 'manual', detection_method = 'manual_place' FROM geo_places p WHERE v.id = :id AND v.user_email = :owner AND p.id = v.place_id")->execute([':owner' => $owner, ':id' => $id]);
    }
    geo_stops_audit($pdo, $owner, 'visit.' . $action . 'ed', ['visit_id' => $id, 'place_id' => $placeId]);
    StepsGeolocationObservability::invalidateOwner($owner);
    public_api_json(['ok' => true, 'visit_id' => $id, 'review' => $override['review']]);
} catch (InvalidArgumentException $e) {
    public_api_json(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
    if (in_array($e->getMessage(), ['visit_not_found', 'candidate_not_found'], true)) public_api_json(['ok' => false, 'error' => $e->getMessage()], 404);
    error_log('geolocation stops api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'visit_update_failed'], 500);
} catch (Throwable $e) {
    error_log('geolocation stops api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'visit_update_failed'], 500);
}
