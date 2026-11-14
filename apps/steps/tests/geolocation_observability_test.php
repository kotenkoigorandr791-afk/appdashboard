<?php
/**
 * Cache versioning and health endpoint checks.
 *
 *   php apps/steps/tests/geolocation_observability_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_observability.php';

function geo_obs_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
$owner = 'qa-geo-observability@example.test';
$parts = ['bbox' => [30, 50, 31, 51], 'zoom' => 12];
$keyBefore = StepsGeolocationObservability::cacheKey('map', $owner, $parts);
StepsGeolocationObservability::cacheSet($keyBefore, ['features' => [], 'meta' => ['count' => 0]], 20);
$cached = StepsGeolocationObservability::cacheGet($keyBefore);
if ($cached !== null) {
    geo_obs_assert(($cached['meta']['count'] ?? null) === 0, 'cache round trip lost data');
    StepsGeolocationObservability::invalidateOwner($owner);
    $keyAfter = StepsGeolocationObservability::cacheKey('map', $owner, $parts);
    geo_obs_assert($keyAfter !== $keyBefore, 'invalidation did not change the key');
    geo_obs_assert(StepsGeolocationObservability::cacheGet($keyBefore) !== null, 'old cache entry disappeared unexpectedly');
}
StepsGeolocationObservability::increment('qa_observability_total', 2, ['result' => 'ok']);
StepsGeolocationObservability::timing('qa_observability_ms', 12.5, ['result' => 'ok']);
$health = StepsGeolocationObservability::health($pdo);
geo_obs_assert(in_array($health['status'], ['ok', 'degraded'], true), 'health status is fail: ' . json_encode($health['checks']));
geo_obs_assert(isset($health['checks']['postgis']['ok']), 'postgis check missing');
geo_obs_assert(isset($health['checks']['queue']['pending']), 'queue check missing');
geo_obs_assert(isset($health['checks']['storage']), 'storage check missing');
echo "ok\n";
