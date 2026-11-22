<?php
/**
 * Live location tunnel tickets and presence snapshot.
 *
 * Mobile opens a websocket with a short-lived ticket; the map page can read the
 * latest presence over HTTP while also listening on the same tunnel.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_live', 120, 60);
steps_geolocation_migrate(app_main_db());
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function steps_live_ticket_dirs(): array
{
    if (!function_exists('appdata_base_dir')) require_once dirname(__DIR__, 3) . '/shell/storage_helper.php';
    $dir = rtrim(appdata_base_dir(), '/') . '/steps_runtime/live_ws_tickets';
    if (!is_dir($dir)) @mkdir($dir, 0770, true);
    @chmod($dir, 0770);
    return [$dir];
}

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT ST_Y(position) AS latitude, ST_X(position) AS longitude, accuracy_m, bearing_degrees, source_at, updated_at, device_id
           FROM geo_live_presence WHERE user_email = :owner"
    );
    $stmt->execute([':owner' => $owner]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    public_api_json([
        'ok' => true,
        'presence' => $row ? [
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'accuracy_m' => isset($row['accuracy_m']) ? (float)$row['accuracy_m'] : null,
            'bearing_degrees' => isset($row['bearing_degrees']) ? (float)$row['bearing_degrees'] : null,
            'source_at' => (string)$row['source_at'],
            'updated_at' => (string)$row['updated_at'],
            'device_id' => $row['device_id'] !== null ? (string)$row['device_id'] : null,
        ] : null,
        'ws_path' => '/steps-live-ws',
    ]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? 'ticket')));
if ($action === 'ticket') {
    $role = strtolower(trim((string)($body['role'] ?? 'device')));
    if (!in_array($role, ['device', 'viewer'], true)) $role = 'device';
    $deviceId = trim((string)($body['device_id'] ?? ''));
    if ($role === 'device' && $deviceId !== '' && !StepsGeolocationDomain::isUuid($deviceId)) {
        public_api_json(['ok' => false, 'error' => 'invalid_device'], 400);
    }
    $ticket = bin2hex(random_bytes(24));
    $exp = time() + 120;
    $payload = [
        'email' => $owner,
        'role' => $role,
        'device_id' => $role === 'device' ? $deviceId : '',
        'exp' => $exp,
    ];
    $dirs = steps_live_ticket_dirs();
    if ($dirs === []) public_api_json(['ok' => false, 'error' => 'ticket_store_unavailable'], 503);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $wrote = false;
    foreach ($dirs as $dir) {
        if (!is_writable($dir)) continue;
        if (@file_put_contents($dir . '/' . $ticket . '.json', $json) !== false) $wrote = true;
    }
    if (!$wrote) public_api_json(['ok' => false, 'error' => 'ticket_store_unavailable'], 503);
    public_api_json([
        'ok' => true,
        'ticket' => $ticket,
        'expires_at' => gmdate('c', $exp),
        'ws_path' => '/steps-live-ws',
    ]);
}
if ($action === 'clear') {
    $pdo->prepare('DELETE FROM geo_live_presence WHERE user_email = :owner')->execute([':owner' => $owner]);
    public_api_json(['ok' => true, 'cleared' => true]);
}
public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
