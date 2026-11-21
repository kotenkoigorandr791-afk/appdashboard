<?php
/**
 * Integration test for location statistics (task 18).
 *
 *   php apps/steps/tests/geolocation_statistics_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';

function geo_stats_db_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-geo-stats@example.test';
$other = 'qa-geo-stats-other@example.test';
$version = 'qa-boundaries-' . bin2hex(random_bytes(4));
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, string $owner, string $version): void {
    foreach (['geo_statistics_cache', 'geo_statistics_daily', 'geo_visits', 'geo_stops', 'geo_trips', 'geo_places', 'geo_location_points', 'geo_devices', 'geo_processor_runs'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
    }
    $pdo->prepare('DELETE FROM geo_boundary_datasets WHERE version = :version')->execute([':version' => $version]);
};
$cleanup($pdo, $owner, $version);
$cleanup($pdo, $other, $version);

try {
    // Synthetic, explicitly versioned boundaries from the fixture.
    $pdo->prepare("INSERT INTO geo_boundary_datasets(version, kind, source, feature_count) VALUES(:version, 'country', 'qa-fixture', 2)")->execute([':version' => $version]);
    $pdo->prepare("INSERT INTO geo_boundary_datasets(version, kind, source, feature_count) VALUES(:version, 'city', 'qa-fixture', 1)")->execute([':version' => $version]);
    $pdo->prepare("INSERT INTO geo_boundary_areas(dataset_version, kind, code, name, geometry) VALUES(:version, 'country', 'UA', 'Ukraine', ST_SetSRID(ST_GeomFromText('POLYGON((30 50,31 50,31 51,30 51,30 50))'), 4326))")->execute([':version' => $version]);
    $pdo->prepare("INSERT INTO geo_boundary_areas(dataset_version, kind, code, name, geometry) VALUES(:version, 'country', 'PL', 'Poland', ST_SetSRID(ST_GeomFromText('POLYGON((20 50,21 50,21 51,20 51,20 50))'), 4326))")->execute([':version' => $version]);
    $pdo->prepare("INSERT INTO geo_boundary_areas(dataset_version, kind, code, name, parent_code, geometry) VALUES(:version, 'city', 'kyiv', 'Kyiv', 'UA', ST_SetSRID(ST_GeomFromText('POLYGON((30.4 50.4,30.6 50.4,30.6 50.6,30.4 50.6,30.4 50.4))'), 4326))")->execute([':version' => $version]);

    $deviceId = StepsGeolocationStatistics::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA stats', 'android', 'mobile', 'active')")
        ->execute([':id' => $deviceId, ':owner' => $owner]);
    $otherDevice = StepsGeolocationStatistics::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'Other stats', 'android', 'mobile', 'active')")
        ->execute([':id' => $otherDevice, ':owner' => $other]);

    $point = $pdo->prepare(
        "INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, accuracy_m, quality_state)
         VALUES(:id, :owner, :device, :event, :source, :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), :accuracy, :state)"
    );
    $base = strtotime('2026-01-01T07:00:00Z');
    for ($day = 0; $day < 3; $day++) {
        for ($i = 0; $i < 4; $i++) {
            $point->execute([
                ':id' => StepsGeolocationStatistics::uuid(), ':owner' => $owner, ':device' => $deviceId,
                ':event' => StepsGeolocationStatistics::uuid(), ':source' => $i === 3 ? 'import' : 'mobile',
                ':at' => gmdate('c', $base + $day * 86400 + $i * 600),
                ':lon' => 30.50 + $i * 0.001, ':lat' => 50.45 + $i * 0.001,
                ':accuracy' => $i === 0 ? 5 : ($i === 1 ? 120 : null),
                ':state' => $i === 3 ? 'flagged' : 'accepted',
            ]);
        }
    }
    // A rejected sample still counts as a point but not as movement.
    $point->execute([
        ':id' => StepsGeolocationStatistics::uuid(), ':owner' => $owner, ':device' => $deviceId,
        ':event' => StepsGeolocationStatistics::uuid(), ':source' => 'import',
        ':at' => gmdate('c', $base + 2 * 86400 + 3600), ':lon' => 30.55, ':lat' => 50.47,
        ':accuracy' => 300, ':state' => 'rejected',
    ]);
    // A short teleport to another country on the same day: the daily summary
    // keeps the dominant country, countries_json still records the outlier,
    // but visited-country metrics ignore countries that never dominate a day.
    $point->execute([
        ':id' => StepsGeolocationStatistics::uuid(), ':owner' => $owner, ':device' => $deviceId,
        ':event' => StepsGeolocationStatistics::uuid(), ':source' => 'import',
        ':at' => gmdate('c', $base + 86400 + 7200), ':lon' => 20.50, ':lat' => 50.50,
        ':accuracy' => 5, ':state' => 'accepted',
    ]);
    // A full day spent in Poland must appear in visited countries.
    for ($i = 0; $i < 4; $i++) {
        $point->execute([
            ':id' => StepsGeolocationStatistics::uuid(), ':owner' => $owner, ':device' => $deviceId,
            ':event' => StepsGeolocationStatistics::uuid(), ':source' => 'mobile',
            ':at' => gmdate('c', $base + 3 * 86400 + $i * 600),
            ':lon' => 20.50 + $i * 0.001, ':lat' => 50.50 + $i * 0.001,
            ':accuracy' => 5, ':state' => 'accepted',
        ]);
    }
    // Other account data must never leak into these metrics.
    $point->execute([
        ':id' => StepsGeolocationStatistics::uuid(), ':owner' => $other, ':device' => $otherDevice,
        ':event' => StepsGeolocationStatistics::uuid(), ':source' => 'mobile',
        ':at' => gmdate('c', $base), ':lon' => 20.5, ':lat' => 50.5, ':accuracy' => 5, ':state' => 'accepted',
    ]);

    $placeId = StepsGeolocationStatistics::uuid();
    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, timezone) VALUES(:id, :owner, 'Cafe', 'cafe', ST_SetSRID(ST_MakePoint(30.501, 50.451), 4326), 80, 'UTC')")
        ->execute([':id' => $placeId, ':owner' => $owner]);
    $pdo->prepare("INSERT INTO geo_visits(id, user_email, place_id, arrived_at, departed_at, confidence, state) VALUES(:id, :owner, :place, :arrived, :departed, 0.9, 'ready')")
        ->execute([':id' => StepsGeolocationStatistics::uuid(), ':owner' => $owner, ':place' => $placeId, ':arrived' => gmdate('c', $base), ':departed' => gmdate('c', $base + 3600)]);
    $pdo->prepare("INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, duration_s, title, state, boundary_reason) VALUES(:id, :owner, :from, :to, ST_SetSRID(ST_GeomFromText('LINESTRING(30.5 50.45,30.51 50.46)'), 4326), 1500, 1200, 'QA trip', 'ready', 'first_point')")
        ->execute([':id' => StepsGeolocationStatistics::uuid(), ':owner' => $owner, ':from' => gmdate('c', $base), ':to' => gmdate('c', $base + 1200)]);

    $days = StepsGeolocationStatistics::refresh($pdo, $owner, 'UTC');
    geo_stats_db_assert($days === 4, 'expected four daily rows, got ' . $days);

    $first = StepsGeolocationStatistics::summary($pdo, $owner, 'UTC', '2026-01-01', '2026-01-04');
    geo_stats_db_assert($first['metrics']['point_count'] === 18, 'point count should include rejected points');
    geo_stats_db_assert($first['metrics']['rejected_points'] === 1, 'rejected point count wrong');
    geo_stats_db_assert($first['metrics']['active_days'] === 4, 'active days wrong');
    geo_stats_db_assert($first['metrics']['route_distance_m'] > 0, 'route distance missing');
    $visited = array_column($first['metrics']['countries'], 'code');
    geo_stats_db_assert(count($visited) === 2 && in_array('UA', $visited, true) && in_array('PL', $visited, true), 'dominant countries missing: ' . implode(',', $visited));
    $mixedDay = $first['daily'][1];
    geo_stats_db_assert($mixedDay['country_code'] === 'UA', 'dominant country of a mixed day is wrong');
    geo_stats_db_assert(count($mixedDay['countries_json']) === 2, 'a mixed day must report both countries');
    geo_stats_db_assert($first['daily'][3]['country_code'] === 'PL', 'full Poland day must dominate');
    geo_stats_db_assert(in_array('Kyiv', $first['metrics']['cities'], true), 'city boundary lookup failed');
    geo_stats_db_assert($first['metrics']['visits'] === 1 && $first['metrics']['dwell_seconds'] === 3600, 'visit metrics wrong');
    geo_stats_db_assert($first['most_visited_places'][0]['name'] === 'Cafe', 'top place wrong');
    geo_stats_db_assert($first['movement_patterns']['trips'] === 1, 'trip pattern wrong');
    geo_stats_db_assert($first['coverage']['days_with_data'] === 4 && $first['coverage']['missing_days'] === 0, 'coverage wrong');
    geo_stats_db_assert($first['boundary']['country_dataset']['version'] === $version, 'boundary version not reported');
    geo_stats_db_assert($first['source_health']['sources']['import'] === 5, 'source health wrong');
    geo_stats_db_assert($first['accuracy']['missing'] === 6, 'missing accuracy count wrong');

    // Repeated runs are stable for the same input.
    $second = StepsGeolocationStatistics::summary($pdo, $owner, 'UTC', '2026-01-01', '2026-01-04');
    geo_stats_db_assert($first['metrics'] === $second['metrics'], 'metrics are not stable across refreshes');
    geo_stats_db_assert($first['daily'] === $second['daily'], 'daily rows are not stable across refreshes');

    // A correction updates the summaries after a bounded refresh.
    $beforeDistance = $first['metrics']['route_distance_m'];
    $pdo->prepare("UPDATE geo_location_points SET position = ST_SetSRID(ST_MakePoint(30.70, 50.70), 4326) WHERE user_email = :owner AND source_at = :at")
        ->execute([':owner' => $owner, ':at' => gmdate('c', $base + 600)]);
    StepsGeolocationStatistics::refresh($pdo, $owner, 'UTC', '2026-01-01', '2026-01-01');
    $corrected = StepsGeolocationStatistics::summary($pdo, $owner, 'UTC', '2026-01-01', '2026-01-03');
    geo_stats_db_assert($corrected['metrics']['route_distance_m'] > $beforeDistance, 'correction did not change distance');

    // Cache invalidation queues a durable, bounded refresh.
    StepsGeolocationStatistics::invalidate($pdo, $owner, '2026-01-01', '2026-01-03');
    $cache = $pdo->prepare('SELECT COUNT(*) FROM geo_statistics_cache WHERE user_email = :owner');
    $cache->execute([':owner' => $owner]);
    geo_stats_db_assert((int)$cache->fetchColumn() === 0, 'cache was not invalidated');
    $queued = $pdo->prepare("SELECT COUNT(*) FROM geo_processor_runs WHERE user_email = :owner AND kind = 'statistics' AND state = 'pending'");
    $queued->execute([':owner' => $owner]);
    geo_stats_db_assert((int)$queued->fetchColumn() >= 1, 'invalidation did not queue statistics');

    // A full refresh removes stale days, e.g. after an import was deleted.
    $pdo->prepare("INSERT INTO geo_statistics_daily(user_email, day, timezone, processor_version, point_count) VALUES(:owner, '1999-01-01', 'UTC', 'stale', 5)")
        ->execute([':owner' => $owner]);
    StepsGeolocationStatistics::refresh($pdo, $owner, 'UTC');
    $stale = $pdo->prepare("SELECT COUNT(*) FROM geo_statistics_daily WHERE user_email = :owner AND day = '1999-01-01'");
    $stale->execute([':owner' => $owner]);
    geo_stats_db_assert((int)$stale->fetchColumn() === 0, 'full refresh left a stale daily row');

    // An account without boundary data gets an explicit assumption, not a guess.
    StepsGeolocationStatistics::refresh($pdo, $other, 'UTC');
    $otherSummary = StepsGeolocationStatistics::summary($pdo, $other, 'UTC');
    geo_stats_db_assert($otherSummary['metrics']['point_count'] === 1, 'other owner point count leaked');

    $checkKeys = static function (array $value, array $forbidden) use (&$checkKeys): void {
        foreach ($value as $key => $child) {
            geo_stats_db_assert(!in_array((string)$key, $forbidden, true), 'statistics leaked transport field: ' . $key);
            if (is_array($child)) $checkKeys($child, $forbidden);
        }
    };
    $checkKeys([$first, $corrected], ['transport', 'mode_of_transport', 'activity_type', 'transport_mode']);

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner, $version);
    $cleanup($pdo, $other, $version);
    $pdo->prepare("DELETE FROM geo_boundary_areas WHERE dataset_version = :version")->execute([':version' => $version]);
}
