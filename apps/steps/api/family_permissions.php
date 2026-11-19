<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_permissions.php';
require_once dirname(__DIR__) . '/core/geolocation_features.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_permissions', 60, 60);
$pdo = app_main_db()->getPdo();
StepsGeolocationFeatures::requireEnabled($pdo, $owner, 'family');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET') {
        public_api_json([
            'ok' => true,
            'grants' => StepsGeolocationPermissions::grants($pdo, $owner),
            'incoming' => StepsGeolocationPermissions::incoming($pdo, $owner),
            'levels' => StepsGeolocationPermissions::LEVELS,
            'current_window_minutes' => (int)(StepsGeolocationPermissions::CURRENT_WINDOW_S / 60),
            'recent_window_days' => (int)(StepsGeolocationPermissions::RECENT_WINDOW_S / 86400),
        ]);
    }
    if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
    $body = public_api_body();
    $action = strtolower(trim((string)($body['action'] ?? '')));
    $id = trim((string)($body['id'] ?? ''));

    if ($action === 'grant') {
        public_api_json(['ok' => true, 'grant' => StepsGeolocationPermissions::grant($pdo, $owner, $body)]);
    }
    if ($action === 'revoke') {
        StepsGeolocationPermissions::revoke($pdo, $owner, $id);
        public_api_json(['ok' => true, 'revoked' => true]);
    }
    if ($action === 'pause' || $action === 'resume') {
        StepsGeolocationPermissions::pause($pdo, $owner, $id, $action === 'pause');
        public_api_json(['ok' => true, 'paused' => $action === 'pause']);
    }
    public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
} catch (StepsGeolocationPermissionsError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
} catch (Throwable $e) {
    error_log('geolocation permissions api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'permissions_update_failed'], 500);
}
