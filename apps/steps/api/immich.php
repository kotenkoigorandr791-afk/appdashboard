<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_immich.php';
require_once dirname(__DIR__) . '/core/geolocation_features.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_immich', 30, 60);
$pdo = app_main_db()->getPdo();
StepsGeolocationFeatures::requireEnabled($pdo, $owner, 'immich');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET') {
        $connection = StepsGeolocationImmich::connection($pdo, $owner);
        $gallery = StepsGeolocationImmich::galleries($pdo, $owner, (int)($_GET['limit'] ?? 60));
        public_api_json([
            'ok' => true,
            'connection' => StepsGeolocationImmich::shapeConnection($connection),
            'options' => StepsGeolocationImmich::options((string)($connection['options_json'] ?? '{}')),
            'gallery' => $gallery,
        ]);
    }
    if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
    $body = public_api_body();
    $action = strtolower(trim((string)($body['action'] ?? '')));
    if ($action === 'test') {
        public_api_json(['ok' => true] + StepsGeolocationImmich::test($pdo, $owner, isset($body['server_url']) ? (string)$body['server_url'] : null, isset($body['api_key']) ? (string)$body['api_key'] : null));
    }
    if ($action === 'connect' || $action === 'rotate') {
        $connection = StepsGeolocationImmich::configure($pdo, $owner, (string)($body['server_url'] ?? ''), (string)($body['api_key'] ?? ''), is_array($body['options'] ?? null) ? $body['options'] : []);
        public_api_json(['ok' => true, 'connection' => $connection], 201);
    }
    if ($action === 'disconnect') {
        StepsGeolocationImmich::disconnect($pdo, $owner);
        public_api_json(['ok' => true, 'connection' => StepsGeolocationImmich::shapeConnection(StepsGeolocationImmich::connection($pdo, $owner))]);
    }
    if ($action === 'sync') {
        $from = isset($body['from']) ? (string)$body['from'] : gmdate('c', time() - 30 * 86400);
        $to = isset($body['to']) ? (string)$body['to'] : gmdate('c');
        if (strtotime($from) === false || strtotime($to) === false || strtotime($from) >= strtotime($to)) public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
        public_api_json(['ok' => true, 'queued' => StepsGeolocationImmich::enqueue($pdo, $owner, gmdate('c', strtotime($from)), gmdate('c', strtotime($to)))]);
    }
    if ($action === 'write_gps') {
        StepsGeolocationImmich::writeGps($pdo, $owner, (string)($body['asset_id'] ?? ''), (string)($body['point_id'] ?? ''), !empty($body['confirm']));
        public_api_json(['ok' => true]);
    }
    public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
} catch (StepsGeolocationImmichError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
} catch (Throwable $e) {
    error_log('geolocation immich api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'immich_failed'], 500);
}
