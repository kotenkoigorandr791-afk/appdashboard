<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_exports.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_exports', 30, 60);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $devices = $pdo->prepare("SELECT id, name, platform FROM geo_devices WHERE user_email = :owner AND status <> 'deleted' ORDER BY name");
    $devices->execute([':owner' => $owner]);
    $trips = $pdo->prepare("SELECT id, title, started_at, ended_at FROM geo_trips WHERE user_email = :owner AND deleted_at IS NULL ORDER BY started_at DESC LIMIT 100");
    $trips->execute([':owner' => $owner]);
    $places = $pdo->prepare("SELECT id, name FROM geo_places WHERE user_email = :owner AND deleted_at IS NULL ORDER BY name");
    $places->execute([':owner' => $owner]);
    public_api_json([
        'ok' => true,
        'exports' => StepsGeolocationExports::history($pdo, $owner),
        'formats' => StepsGeolocationExports::FORMATS,
        'scopes' => StepsGeolocationExports::SCOPES,
        'ttl_hours' => (int)round(StepsGeolocationExports::ttlSeconds() / 3600),
        'options' => [
            'devices' => $devices->fetchAll(PDO::FETCH_ASSOC),
            'trips' => $trips->fetchAll(PDO::FETCH_ASSOC),
            'places' => $places->fetchAll(PDO::FETCH_ASSOC),
        ],
    ]);
}
if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);

$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? 'create')));
if ($action === 'delete') {
    $id = trim((string)($body['id'] ?? ''));
    if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
    if (!StepsGeolocationExports::deleteExport($pdo, $owner, $id)) public_api_json(['ok' => false, 'error' => 'export_not_found'], 404);
    public_api_json(['ok' => true, 'deleted' => $id]);
}
if ($action !== 'create') public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
$format = strtolower(trim((string)($body['format'] ?? 'geojson')));
$scope = is_array($body['scope'] ?? null) ? $body['scope'] : [];
try {
    $export = StepsGeolocationExports::enqueue($pdo, $owner, $format, $scope);
    public_api_json(['ok' => true, 'export' => $export], 202);
} catch (StepsGeolocationExportError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
} catch (Throwable $e) {
    error_log('geolocation export enqueue: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'export_failed'], 500);
}
