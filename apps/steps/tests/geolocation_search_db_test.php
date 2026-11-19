<?php
/**
 * Integration checks for task 35 search, saved views and analytics.
 * Run inside the app container against the local PostGIS database.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_search.php';
require_once dirname(__DIR__) . '/core/geolocation_saved_views.php';
require_once dirname(__DIR__) . '/core/geolocation_analytics.php';

function geo_s35_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$owner = 'qa-search-saved@example.test';
$other = 'qa-search-other@example.test';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();
$uuid = static fn(): string => StepsGeolocationStatistics::uuid();
$cleanup = static function (PDO $pdo, string $email): void {
    foreach (['geo_saved_views', 'geo_analytics_cache', 'geo_statistics_cache', 'geo_statistics_daily', 'geo_matched_segments', 'geo_quality_corrections', 'geo_location_points', 'geo_trips', 'geo_visits', 'geo_stops', 'geo_places', 'geo_geofences', 'geo_import_points', 'geo_import_files', 'geo_imports', 'geo_devices', 'geo_processor_runs', 'geo_audit_events'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $email]);
    }
};
$cleanup($pdo, $owner);
$cleanup($pdo, $other);

try {
    $migration = (int)$pdo->query("SELECT COUNT(*) FROM geo_schema_migrations WHERE version = '020'")->fetchColumn();
    geo_s35_assert($migration === 1, 'migration 020 not recorded');
    $device = $uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA Phone', 'android', 'mobile', 'active')")->execute([':id' => $device, ':owner' => $owner]);
    $place = $uuid();
    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, timezone) VALUES(:id, :owner, 'Workplace', 'work', ST_SetSRID(ST_MakePoint(30.5, 50.45), 4326), 120, 'UTC')")->execute([':id' => $place, ':owner' => $owner]);
    $trip = $uuid();
    // The analytics range below is one UTC day, so the trip is anchored to a
    // known instant and that same day is queried: using "two hours ago" broke
    // the test whenever the suite ran just after midnight.
    $tripStart = gmdate('c', time() - 300);
    $tripEnd = gmdate('c', time() - 120);
    $tripDay = substr($tripStart, 0, 10);
    $pdo->prepare("INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, duration_s, title, notes, state, processor_version) VALUES(:id, :owner, :started, :ended, ST_SetSRID(ST_GeomFromText('LINESTRING(30.5 50.45,30.502 50.451)'), 4326), 230, 3600, 'Weekend trip', 'private note', 'ready', 'trips-1')")
        ->execute([':id' => $trip, ':owner' => $owner, ':started' => $tripStart, ':ended' => $tripEnd]);
    for ($i = 0; $i < 6; $i++) {
        $pdo->prepare("INSERT INTO geo_location_points(id, user_email, device_id, source_type, source_at, position, accuracy_m, quality_state) VALUES(:id, :owner, :device, 'mobile', now() - interval '3 hours' + (:i || ' minutes')::interval, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 12, :state)")
            ->execute([':id' => $uuid(), ':owner' => $owner, ':device' => $device, ':i' => $i * 20, ':lon' => 30.5 + $i * 0.0003, ':lat' => 50.45, ':state' => $i === 5 ? 'flagged' : 'accepted']);
    }
    $otherPlace = $uuid();
    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, timezone) VALUES(:id, :owner, 'Other Secret Work', 'work', ST_SetSRID(ST_MakePoint(30.6, 50.46), 4326), 120, 'UTC')")->execute([':id' => $otherPlace, ':owner' => $other]);

    $search = StepsGeolocationSearch::search($pdo, $owner, ['q' => 'work']);
    geo_s35_assert(count($search['results']) === 1 && $search['results'][0]['type'] === 'place', 'place search wrong');
    geo_s35_assert($search['results'][0]['id'] === $place, 'search returned another tenant');
    $tripSearch = StepsGeolocationSearch::search($pdo, $owner, ['q' => 'weekend']);
    geo_s35_assert(count($tripSearch['results']) === 1 && $tripSearch['results'][0]['type'] === 'trip', 'trip search wrong');
    $otherSearch = StepsGeolocationSearch::search($pdo, $owner, ['q' => 'secret']);
    geo_s35_assert($otherSearch['results'] === [], 'search leaked another tenant');

    $view = StepsGeolocationSavedViews::create($pdo, $owner, ['name' => 'Work', 'context' => 'places', 'date_period' => 'last_30_days', 'query' => ['q' => 'work', 'types' => ['place']]]);
    geo_s35_assert($view['name'] === 'Work' && $view['query']['q'] === 'work', 'saved view create failed');
    $applied = StepsGeolocationSavedViews::apply($pdo, $owner, $view['id'], 'UTC');
    geo_s35_assert($applied['range']['from'] <= $applied['range']['to'], 'saved view apply range failed');
    $copy = StepsGeolocationSavedViews::duplicate($pdo, $owner, $view['id']);
    geo_s35_assert($copy['id'] !== $view['id'], 'duplicate reused id');
    StepsGeolocationSavedViews::delete($pdo, $owner, $copy['id']);
    $list = StepsGeolocationSavedViews::list($pdo, $owner);
    geo_s35_assert(count($list['views']) === 1 && count($list['templates']) >= 8, 'saved view list/templates wrong');

    $analytics = StepsGeolocationAnalytics::summary($pdo, $owner, ['from' => $tripDay, 'to' => $tripDay, 'timezone' => 'UTC', 'compare' => true]);
    geo_s35_assert($analytics['metrics']['trip_count'] === 1, 'analytics trip count wrong');
    geo_s35_assert($analytics['coverage']['quality_percent'] > 0, 'analytics coverage missing');
    geo_s35_assert($analytics['policy']['transport_classification'] === false, 'analytics classified transport');
    $cache = (int)$pdo->query("SELECT COUNT(*) FROM geo_analytics_cache WHERE user_email = " . $pdo->quote($owner))->fetchColumn();
    geo_s35_assert($cache === 1, 'analytics cache missing');

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner);
    $cleanup($pdo, $other);
}
