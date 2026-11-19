<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_quality.php';
require_once dirname(__DIR__) . '/core/geolocation_ingestion.php';
require_once dirname(__DIR__) . '/core/geolocation_achievements.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
function geo_api_point(PDO $pdo, string $owner, array $point): array {
    return StepsGeolocationIngestion::point($pdo, $owner, $point);
}
$action = (string)($_GET['action'] ?? 'point');
if ($action === 'points' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
    $q = trim((string)($_GET['q'] ?? ''));
    $where = 'user_email = :owner';
    $params = [':owner' => $owner];
    if ($q !== '') {
        $where .= ' AND (CAST(ST_Y(position) AS TEXT) ILIKE :q OR CAST(ST_X(position) AS TEXT) ILIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    $pdo = app_main_db()->getPdo();
    $stmt = $pdo->prepare("SELECT id, source_at, ST_Y(position) AS latitude, ST_X(position) AS longitude, accuracy_m FROM geo_location_points WHERE $where ORDER BY source_at DESC LIMIT $limit");
    $stmt->execute($params);
    public_api_json(['ok' => true, 'points' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
if ($action === 'stream') {
    header('Content-Type: text/event-stream'); header('Cache-Control: no-cache');
    $after = (string)($_SERVER['HTTP_LAST_EVENT_ID'] ?? '');
    $pdo = app_main_db()->getPdo();
    $stmt = $pdo->prepare('SELECT id, source_at, ST_X(position) AS longitude, ST_Y(position) AS latitude FROM geo_location_points WHERE user_email = :owner AND id::text > :after ORDER BY received_at DESC LIMIT 1');
    $stmt->execute([':owner' => $owner, ':after' => $after]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { echo 'id: ' . $row['id'] . "\nevent: location.current\ndata: " . json_encode($row) . "\n\n"; } else echo ": keepalive\n\n";
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
public_api_rate_limit('geolocation', 600, 60);
$body = public_api_body();
$pdo = app_main_db()->getPdo();
if ($action === 'delete') {
    $ids = array_values(array_filter($body['ids'] ?? [], static fn($id) => is_string($id) && preg_match('/^[0-9a-f-]{36}$/i', $id)));
    if ($ids === [] || count($ids) > 500) public_api_json(['ok' => false, 'error' => 'invalid_points'], 400);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM geo_location_points WHERE user_email = ? AND id IN ($placeholders)");
    $stmt->execute(array_merge([$owner], $ids));
    try { StepsGeolocationQuality::enqueue($pdo, $owner); } catch (Throwable $e) { error_log('geolocation quality enqueue: ' . $e->getMessage()); }
    StepsGeolocationAchievements::enqueueIfEnabled($pdo, $owner, 'delete');
    public_api_json(['ok' => true, 'deleted' => $stmt->rowCount()]);
}
$points = $action === 'batch' ? ($body['points'] ?? []) : [$body];
if (!is_array($points) || count($points) === 0 || count($points) > 500) public_api_json(['ok' => false, 'error' => 'invalid_batch'], 400);
$results = [];
foreach ($points as $point) {
    if (!is_array($point)) { $results[] = ['status' => 'rejected', 'error' => 'invalid_point']; continue; }
    try { $results[] = geo_api_point($pdo, $owner, $point); }
    catch (Throwable $e) { error_log('geolocation point: ' . $e->getMessage()); $results[] = ['status' => 'error', 'error' => 'server_error']; }
}
// Only a durable marker: the existing app worker path performs the quality pass.
if (in_array('accepted', array_column($results, 'status'), true)) {
    try { StepsGeolocationQuality::enqueue($pdo, $owner); } catch (Throwable $e) { error_log('geolocation quality enqueue: ' . $e->getMessage()); }
    try { StepsGeolocationAchievements::enqueueIfEnabled($pdo, $owner, 'edit'); } catch (Throwable $e) { error_log('geolocation achievements enqueue: ' . $e->getMessage()); }
}
public_api_json(['ok' => true, 'results' => $results]);
