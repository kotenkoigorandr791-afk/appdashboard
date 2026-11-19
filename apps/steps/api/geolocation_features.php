<?php
/**
 * Owner-scoped rollout flags (task 32). Flags never delete data; disabling a
 * capability only blocks new use and can be reverted independently.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_features.php';

public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner((string)$owner);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
public_api_rate_limit('geolocation_features', 60, 60);

if ($method === 'GET') {
    public_api_json(['ok' => true, 'features' => StepsGeolocationFeatures::all($pdo, $owner), 'available' => StepsGeolocationFeatures::FLAGS]);
}
if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);

$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? 'set')));
if ($action !== 'set') public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
try {
    $features = StepsGeolocationFeatures::set($pdo, $owner, (string)($body['flag'] ?? ''), !empty($body['enabled']));
    public_api_json(['ok' => true, 'features' => $features]);
} catch (StepsGeolocationFeatureError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
}
