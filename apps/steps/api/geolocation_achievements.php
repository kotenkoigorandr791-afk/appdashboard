<?php
/**
 * Achievements API (task 27). Owner-only; there is no public variant.
 *
 * GET  -> progress, earned state, collections and the tenant preference.
 * POST -> {action: "preference", enabled: bool, timezone?: string}
 *         {action: "recompute"}
 *
 * When the preference is disabled the response carries no achievement data, so
 * the module can be hidden entirely without deleting history.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_achievements.php';
require_once dirname(__DIR__) . '/core/geolocation_features.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_achievements', 120, 60);
$pdo = app_main_db()->getPdo();
StepsGeolocationFeatures::requireEnabled($pdo, $owner, 'achievements');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    try {
        public_api_json(['ok' => true] + StepsGeolocationAchievements::progress($pdo, $owner));
    } catch (Throwable $e) {
        error_log('geolocation achievements api: ' . $e->getMessage());
        public_api_json(['ok' => false, 'error' => 'achievements_failed'], 500);
    }
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? 'recompute')));
try {
    if ($action === 'preference') {
        if (!array_key_exists('enabled', $body)) public_api_json(['ok' => false, 'error' => 'invalid_preference'], 400);
        $timezone = (string)($body['timezone'] ?? 'UTC');
        $settings = StepsGeolocationAchievements::setEnabled($pdo, $owner, (bool)$body['enabled'], $timezone);
        $progress = StepsGeolocationAchievements::progress($pdo, $owner);
        public_api_json(['ok' => true, 'settings' => $settings] + $progress);
    }
    if ($action === 'recompute') {
        $result = StepsGeolocationAchievements::recalculate($pdo, $owner, (string)($body['reason'] ?? 'manual'));
        public_api_json(['ok' => true, 'result' => $result] + StepsGeolocationAchievements::progress($pdo, $owner));
    }
    public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
} catch (Throwable $e) {
    error_log('geolocation achievements api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'achievements_failed'], 500);
}
