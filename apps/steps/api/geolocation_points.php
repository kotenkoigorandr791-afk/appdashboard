<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_achievements.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_points', 120, 60);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function geo_points_uuid(): string
{
    $b = bin2hex(random_bytes(16));
    return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
}

function geo_points_filter(array $in, string $owner): array
{
    $where = ['user_email = :owner'];
    $params = [':owner' => $owner];
    $from = trim((string)($in['from'] ?? ''));
    $to = trim((string)($in['to'] ?? ''));
    if ($from !== '' && strtotime($from) !== false) { $where[] = 'source_at >= :from'; $params[':from'] = gmdate('c', strtotime($from)); }
    if ($to !== '' && strtotime($to) !== false) { $where[] = 'source_at <= :to'; $params[':to'] = gmdate('c', strtotime($to)); }
    $state = trim((string)($in['state'] ?? ''));
    if (in_array($state, ['pending', 'accepted', 'flagged', 'rejected'], true)) { $where[] = 'quality_state = :state'; $params[':state'] = $state; }
    $source = trim((string)($in['source'] ?? ''));
    if (in_array($source, ['mobile', 'import', 'api'], true)) { $where[] = 'source_type = :source'; $params[':source'] = $source; }
    $bbox = $in['bbox'] ?? null;
    if (is_string($bbox)) $bbox = array_map('trim', explode(',', $bbox));
    if (is_array($bbox) && count($bbox) === 4 && count(array_filter($bbox, 'is_numeric')) === 4) {
        [$minLon, $minLat, $maxLon, $maxLat] = array_map('floatval', array_values($bbox));
        $where[] = 'position && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)';
        $params[':minLon'] = min($minLon, $maxLon);
        $params[':minLat'] = min($minLat, $maxLat);
        $params[':maxLon'] = max($minLon, $maxLon);
        $params[':maxLat'] = max($minLat, $maxLat);
    }
    return [implode(' AND ', $where), $params];
}

if ($method === 'GET') {
    [$where, $params] = geo_points_filter($_GET, $owner);
    $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $count = $pdo->prepare("SELECT COUNT(*) FROM geo_location_points WHERE $where");
    $count->execute($params);
    $stmt = $pdo->prepare("SELECT id, source_at, source_type, quality_state, accuracy_m, ST_Y(position) AS latitude, ST_X(position) AS longitude FROM geo_location_points WHERE $where ORDER BY source_at DESC, id LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    public_api_json(['ok' => true, 'total' => (int)$count->fetchColumn(), 'points' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? '')));
$ids = array_values(array_filter((array)($body['ids'] ?? []), static fn($id): bool => is_string($id) && StepsGeolocationDomain::isUuid($id)));
if ($ids !== []) {
    $ids = array_slice($ids, 0, 5000);
    $where = 'user_email = :owner AND id IN (' . implode(',', array_map(static fn(string $id): string => $pdo->quote($id), $ids)) . ')';
    $params = [':owner' => $owner];
} else {
    [$where, $params] = geo_points_filter($body, $owner);
    if (!isset($params[':from']) && !isset($params[':bbox']) && !isset($params[':minLon'])) {
        public_api_json(['ok' => false, 'error' => 'filter_required'], 400);
    }
}

if ($action === 'count') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM geo_location_points WHERE $where");
    $stmt->execute($params);
    public_api_json(['ok' => true, 'count' => (int)$stmt->fetchColumn()]);
}
if ($action !== 'delete') public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);

try {
    $pdo->beginTransaction();
    $range = $pdo->prepare("SELECT MIN(source_at) AS a, MAX(source_at) AS b, COUNT(*) AS n FROM geo_location_points WHERE $where");
    $range->execute($params);
    $span = $range->fetch(PDO::FETCH_ASSOC);
    $del = $pdo->prepare("DELETE FROM geo_location_points WHERE $where");
    $del->execute($params);
    $deleted = $del->rowCount();
    if ($deleted > 0 && $span && $span['a'] !== null) {
        $line = "(SELECT ST_MakeLine(p.position ORDER BY p.source_at) FROM geo_location_points p WHERE p.user_email = t.user_email AND p.quality_state = 'accepted' AND p.source_at BETWEEN t.started_at AND t.ended_at)";
        $bind = [':o' => $owner, ':a' => $span['a'], ':b' => $span['b']];
        $pdo->prepare("UPDATE geo_trips t SET geometry = $line, distance_m = COALESCE(ST_Length($line::geography), 0), updated_at = now() WHERE t.user_email = :o AND t.deleted_at IS NULL AND t.started_at <= :b AND t.ended_at >= :a AND ST_NPoints($line) >= 2")->execute($bind);
        $pdo->prepare("UPDATE geo_trips t SET deleted_at = now(), state = 'archived', updated_at = now() WHERE t.user_email = :o AND t.deleted_at IS NULL AND t.started_at <= :b AND t.ended_at >= :a AND COALESCE(ST_NPoints($line), 0) < 2")->execute($bind);
    }
    $pdo->prepare('INSERT INTO geo_audit_events(id, user_email, actor_email, event_type, context_json) VALUES(:id, :o, :o, :e, :c)')
        ->execute([':id' => geo_points_uuid(), ':o' => $owner, ':e' => 'points.deleted', ':c' => json_encode(['count' => $deleted])]);
    $pdo->commit();
    StepsGeolocationAchievements::enqueueIfEnabled($pdo, $owner, 'delete');
    public_api_json(['ok' => true, 'deleted' => $deleted]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('geolocation points delete: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'delete_failed'], 500);
}
