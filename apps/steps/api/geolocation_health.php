<?php
/**
 * Health/readiness and redacted metrics (task 31).
 *
 * Localhost or an operator-supplied X-Geo-Health-Token may read it; no user
 * session is required so the container health check works during an outage.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_security.php';
require_once dirname(__DIR__) . '/core/geolocation_jobs.php';
require_once dirname(__DIR__) . '/core/geolocation_observability.php';

$remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$local = $remote === '127.0.0.1' || $remote === '::1' || str_starts_with($remote, '127.');
$expected = (string)getenv('GEO_HEALTH_TOKEN');
$presented = (string)($_SERVER['HTTP_X_GEO_HEALTH_TOKEN'] ?? '');
$authorized = $local || ($expected !== '' && $presented !== '' && hash_equals($expected, $presented));
if (!$authorized) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);

try {
    $pdo = app_main_db()->getPdo();
    $health = StepsGeolocationObservability::health($pdo);
    public_api_json(['ok' => $health['status'] !== 'fail'] + $health, $health['status'] === 'fail' ? 503 : 200);
} catch (Throwable $e) {
    error_log('geolocation health: ' . $e->getMessage());
    public_api_json(['ok' => false, 'status' => 'fail', 'error' => 'health_failed', 'at' => gmdate('c')], 503);
}
