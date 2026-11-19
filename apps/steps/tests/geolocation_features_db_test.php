<?php
/**
 * Independent tenant rollout flags.
 *
 *   php apps/steps/tests/geolocation_features_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_features.php';

function geo_features_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-geo-flags@example.test';
$other = 'qa-geo-flags-other@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
$pdo->prepare('DELETE FROM geo_feature_flags WHERE user_email IN (:owner, :other)')->execute([':owner' => $owner, ':other' => $other]);
try {
    $defaults = StepsGeolocationFeatures::all($pdo, $owner);
    foreach (StepsGeolocationFeatures::FLAGS as $flag) geo_features_assert($defaults[$flag] === true, 'default flag disabled: ' . $flag);
    StepsGeolocationFeatures::set($pdo, $owner, 'video', false);
    geo_features_assert(StepsGeolocationFeatures::enabled($pdo, $owner, 'video') === false, 'video flag not disabled');
    geo_features_assert(StepsGeolocationFeatures::enabled($pdo, $other, 'video') === true, 'flag leaked to another tenant');
    geo_features_assert(StepsGeolocationFeatures::enabled($pdo, $owner, 'family') === true, 'unrelated flag changed');
    try {
        StepsGeolocationFeatures::requireEnabled($pdo, $owner, 'video');
        throw new RuntimeException('disabled feature passed requireEnabled');
    } catch (StepsGeolocationFeatureError $e) {
        geo_features_assert($e->errorCode === 'feature_disabled' && $e->status === 403, 'feature error wrong');
    }
    StepsGeolocationFeatures::set($pdo, $owner, 'video', true);
    geo_features_assert(StepsGeolocationFeatures::enabled($pdo, $owner, 'video') === true, 'feature did not re-enable');
    try {
        StepsGeolocationFeatures::set($pdo, $owner, 'nope', true);
        throw new RuntimeException('unknown flag accepted');
    } catch (StepsGeolocationFeatureError $e) {
        geo_features_assert($e->errorCode === 'invalid_feature', 'unknown flag error wrong');
    }
    putenv('GEO_FEATURES_DISABLED=capture');
    geo_features_assert(StepsGeolocationFeatures::enabled($pdo, $owner, 'capture') === false, 'global kill switch ignored');
    putenv('GEO_FEATURES_DISABLED=');
    echo "ok\n";
} finally {
    $pdo->prepare('DELETE FROM geo_feature_flags WHERE user_email IN (:owner, :other)')->execute([':owner' => $owner, ':other' => $other]);
}
