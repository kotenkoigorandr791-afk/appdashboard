<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
function geo_api_uuid(): string {
    $b = bin2hex(random_bytes(16));
    return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
}
function geo_api_point(PDO $pdo, string $owner, array $point): array {
    $device = (string)($point['device_id'] ?? '');
    $event = (string)($point['client_event_id'] ?? '');
    $lat = $point['latitude'] ?? null;
    $lon = $point['longitude'] ?? null;
    $source = (string)($point['source_type'] ?? 'mobile');
    $at = (string)($point['source_at'] ?? '');
    if (!StepsGeolocationDomain::isUuid($device) || !StepsGeolocationDomain::isUuid($event) || !is_numeric($lat) || !is_numeric($lon) || !StepsGeolocationDomain::isCoordinate((float)$lat, (float)$lon) || !StepsGeolocationDomain::isSourceType($source) || strtotime($at) === false) return ['status' => 'rejected', 'error' => 'invalid_point'];
    $deviceStmt = $pdo->prepare("INSERT INTO geo_devices(id,user_email,name,source_type) VALUES(:id,:owner,:name,:source) ON CONFLICT (user_email,id) DO NOTHING");
    $deviceStmt->execute([':id' => $device, ':owner' => $owner, ':name' => 'Unnamed device', ':source' => $source]);
    $sql = "INSERT INTO geo_location_points(id,user_email,device_id,client_event_id,device_sequence,source_type,source_at,source_offset_minutes,position,accuracy_m,altitude_m,speed_mps,bearing_degrees) VALUES(:id,:owner,:device,:event,:sequence,:source,:source_at,:offset,ST_SetSRID(ST_MakePoint(:lon,:lat),4326),:accuracy,:altitude,:speed,:bearing) ON CONFLICT (user_email,device_id,client_event_id) WHERE client_event_id IS NOT NULL DO NOTHING RETURNING id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => geo_api_uuid(), ':owner' => $owner, ':device' => $device, ':event' => $event, ':sequence' => isset($point['device_sequence']) ? (int)$point['device_sequence'] : null, ':source' => $source, ':source_at' => gmdate('c', strtotime($at)), ':offset' => isset($point['source_offset_minutes']) ? (int)$point['source_offset_minutes'] : null, ':lon' => (float)$lon, ':lat' => (float)$lat, ':accuracy' => $point['accuracy_m'] ?? null, ':altitude' => $point['altitude_m'] ?? null, ':speed' => $point['speed_mps'] ?? null, ':bearing' => $point['bearing_degrees'] ?? null]);
    $id = $stmt->fetchColumn();
    return $id ? ['status' => 'accepted', 'id' => $id] : ['status' => 'duplicate'];
}
$action = (string)($_GET['action'] ?? 'point');
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
$points = $action === 'batch' ? ($body['points'] ?? []) : [$body];
if (!is_array($points) || count($points) === 0 || count($points) > 500) public_api_json(['ok' => false, 'error' => 'invalid_batch'], 400);
$pdo = app_main_db()->getPdo();
$results = [];
foreach ($points as $point) $results[] = is_array($point) ? geo_api_point($pdo, $owner, $point) : ['status' => 'rejected', 'error' => 'invalid_point'];
public_api_json(['ok' => true, 'results' => $results]);
