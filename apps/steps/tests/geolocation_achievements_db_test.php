<?php
/**
 * Integration test for private achievements (task 27).
 *
 *   php apps/steps/tests/geolocation_achievements_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';
require_once dirname(__DIR__) . '/core/geolocation_achievements.php';

function geo_ach_db_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-geo-ach@example.test';
$other = 'qa-geo-ach-other@example.test';
$version = 'qa-ach-boundaries-' . bin2hex(random_bytes(4));
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$cleanup = static function (PDO $pdo, string $owner, string $version): void {
    foreach (['geo_achievements', 'geo_achievement_settings', 'geo_statistics_cache', 'geo_statistics_daily', 'geo_visits', 'geo_stops', 'geo_trips', 'geo_places', 'geo_location_points', 'geo_devices', 'geo_processor_runs'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_email = :owner")->execute([':owner' => $owner]);
    }
    $pdo->prepare('DELETE FROM geo_boundary_areas WHERE dataset_version = :version')->execute([':version' => $version]);
    $pdo->prepare('DELETE FROM geo_boundary_datasets WHERE version = :version')->execute([':version' => $version]);
};
$cleanup($pdo, $owner, $version);
$cleanup($pdo, $other, $version);

try {
    foreach (['country', 'region', 'city'] as $kind) {
        $pdo->prepare('INSERT INTO geo_boundary_datasets(version, kind, source, feature_count) VALUES(:version, :kind, :source, 1)')
            ->execute([':version' => $version, ':kind' => $kind, ':source' => 'qa-fixture']);
    }
    $pdo->prepare("INSERT INTO geo_boundary_areas(dataset_version, kind, code, name, geometry) VALUES(:version, 'country', 'UA', 'Ukraine', ST_SetSRID(ST_GeomFromText('POLYGON((30 50,31 50,31 51,30 51,30 50))'), 4326))")->execute([':version' => $version]);
    $pdo->prepare("INSERT INTO geo_boundary_areas(dataset_version, kind, code, name, geometry) VALUES(:version, 'region', 'UA-KY', 'Kyiv region', ST_SetSRID(ST_GeomFromText('POLYGON((30.3 50.3,30.7 50.3,30.7 50.7,30.3 50.7,30.3 50.3))'), 4326))")->execute([':version' => $version]);
    $pdo->prepare("INSERT INTO geo_boundary_areas(dataset_version, kind, code, name, geometry) VALUES(:version, 'city', 'kyiv', 'Kyiv', ST_SetSRID(ST_GeomFromText('POLYGON((30.4 50.4,30.6 50.4,30.6 50.6,30.4 50.6,30.4 50.4))'), 4326))")->execute([':version' => $version]);

    $device = StepsGeolocationAchievements::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'QA ach', 'android', 'mobile', 'active')")
        ->execute([':id' => $device, ':owner' => $owner]);
    $otherDevice = StepsGeolocationAchievements::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :owner, 'Other ach', 'android', 'mobile', 'active')")
        ->execute([':id' => $otherDevice, ':owner' => $other]);

    $insertPoint = $pdo->prepare(
        "INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, quality_state)
         VALUES(:id, :owner, :device, :event, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), :state)"
    );
    $base = strtotime('2026-02-01T08:00:00Z');
    for ($i = 0; $i < 3; $i++) {
        $insertPoint->execute([
            ':id' => StepsGeolocationAchievements::uuid(), ':owner' => $owner, ':device' => $device,
            ':event' => StepsGeolocationAchievements::uuid(), ':at' => gmdate('c', $base + $i * 600),
            ':lon' => 30.500 + $i * 0.002, ':lat' => 50.450 + $i * 0.002, ':state' => 'accepted',
        ]);
    }
    $insertPoint->execute([
        ':id' => StepsGeolocationAchievements::uuid(), ':owner' => $other, ':device' => $otherDevice,
        ':event' => StepsGeolocationAchievements::uuid(), ':at' => gmdate('c', $base),
        ':lon' => 20.5, ':lat' => 50.5, ':state' => 'accepted',
    ]);

    $place = StepsGeolocationAchievements::uuid();
    $pdo->prepare("INSERT INTO geo_places(id, user_email, name, tag, position, radius_m, timezone) VALUES(:id, :owner, 'Cafe', 'cafe', ST_SetSRID(ST_MakePoint(30.501, 50.451), 4326), 60, 'UTC')")
        ->execute([':id' => $place, ':owner' => $owner]);
    $pdo->prepare("INSERT INTO geo_visits(id, user_email, place_id, arrived_at, departed_at, confidence, state) VALUES(:id, :owner, :place, :arrived, :departed, 0.9, 'ready')")
        ->execute([':id' => StepsGeolocationAchievements::uuid(), ':owner' => $owner, ':place' => $place, ':arrived' => gmdate('c', $base), ':departed' => gmdate('c', $base + 1800)]);

    $settings = StepsGeolocationAchievements::settings($pdo, $owner);
    geo_ach_db_assert($settings['enabled'] === true, 'achievements must be enabled by default');
    geo_ach_db_assert(StepsGeolocationAchievements::PUBLIC === false, 'achievements must stay private');

    StepsGeolocationStatistics::refresh($pdo, $owner, 'UTC');
    $progress = StepsGeolocationAchievements::progress($pdo, $owner);
    geo_ach_db_assert($progress['enabled'] === true, 'enabled module reported disabled');
    geo_ach_db_assert($progress['private'] === true, 'privacy marker missing');
    geo_ach_db_assert(count($progress['achievements']) >= 20, 'achievement rows missing');
    $byId = [];
    foreach ($progress['achievements'] as $row) $byId[$row['id']] = $row;
    geo_ach_db_assert($byId['countries_1']['earned'] === true, 'country achievement not earned');
    geo_ach_db_assert($byId['regions_1']['earned'] === true, 'region achievement not earned');
    geo_ach_db_assert($byId['cities_1']['earned'] === true, 'city achievement not earned');
    geo_ach_db_assert($byId['places_1']['earned'] === true, 'place achievement not earned');
    geo_ach_db_assert($byId['coverage_days_7']['earned'] === false, 'coverage_days_7 should not be earned');
    geo_ach_db_assert($byId['distance_100']['progress'] > 100, 'distance progress missing');
    geo_ach_db_assert($byId['distance_100']['progress'] < 100000, 'distance progress exceeded target');
    geo_ach_db_assert(!empty($progress['collections']), 'collections missing');

    // Deterministic rebuild: same input, same progress and watermark.
    $firstWatermark = $progress['input_watermark'];
    $firstDistance = $byId['distance_100']['progress'];
    $firstEarned = $byId['countries_1']['first_earned_at'];
    StepsGeolocationAchievements::recalculate($pdo, $owner, 'manual');
    $again = StepsGeolocationAchievements::progress($pdo, $owner);
    $byIdAgain = [];
    foreach ($again['achievements'] as $row) $byIdAgain[$row['id']] = $row;
    geo_ach_db_assert($again['input_watermark'] === $firstWatermark, 'watermark changed without input change');
    geo_ach_db_assert($byIdAgain['distance_100']['progress'] === $firstDistance, 'distance progress is not deterministic');
    geo_ach_db_assert($byIdAgain['countries_1']['first_earned_at'] === $firstEarned, 'first-earned time was not preserved');

    // An explicit historical first-earned time survives recalculation.
    $pdo->prepare("UPDATE geo_achievements SET first_earned_at = '2020-01-01T00:00:00Z' WHERE user_email = :owner AND achievement_id = 'countries_1'")
        ->execute([':owner' => $owner]);
    StepsGeolocationAchievements::recalculate($pdo, $owner, 'manual');
    $preserved = $pdo->prepare("SELECT first_earned_at FROM geo_achievements WHERE user_email = :owner AND achievement_id = 'countries_1'");
    $preserved->execute([':owner' => $owner]);
    geo_ach_db_assert(str_starts_with((string)$preserved->fetchColumn(), '2020-01-01'), 'first-earned time overwritten');

    // Owner isolation.
    $otherMetrics = StepsGeolocationAchievements::metrics($pdo, $other);
    geo_ach_db_assert($otherMetrics['places'] === 0, 'other owner place metric leaked');
    geo_ach_db_assert($otherMetrics['distance_m'] === 0.0, 'other owner distance leaked into metrics');

    // Disabling freezes and hides without deleting rows.
    $rowsBefore = $pdo->prepare('SELECT COUNT(*) FROM geo_achievements WHERE user_email = :owner');
    $rowsBefore->execute([':owner' => $owner]);
    $countBefore = (int)$rowsBefore->fetchColumn();
    $snapshot = $pdo->prepare("SELECT achievement_id, progress, earned FROM geo_achievements WHERE user_email = :owner ORDER BY achievement_id");
    $snapshot->execute([':owner' => $owner]);
    $before = $snapshot->fetchAll(PDO::FETCH_ASSOC);
    StepsGeolocationAchievements::setEnabled($pdo, $owner, false);
    geo_ach_db_assert(StepsGeolocationAchievements::enqueue($pdo, $owner, 'edit') === false, 'disabled module queued an evaluation');
    $frozen = StepsGeolocationAchievements::progress($pdo, $owner);
    geo_ach_db_assert($frozen['enabled'] === false && $frozen['achievements'] === [], 'disabled module exposed data');
    geo_ach_db_assert($frozen['frozen'] === true, 'frozen marker missing');
    $skipped = StepsGeolocationAchievements::recalculate($pdo, $owner, 'manual');
    geo_ach_db_assert(!empty($skipped['skipped']), 'disabled module recalculated');
    $rowsAfter = $pdo->prepare('SELECT COUNT(*) FROM geo_achievements WHERE user_email = :owner');
    $rowsAfter->execute([':owner' => $owner]);
    geo_ach_db_assert((int)$rowsAfter->fetchColumn() === $countBefore, 'disabling deleted stored achievements');
    $snapshot2 = $pdo->prepare("SELECT achievement_id, progress, earned FROM geo_achievements WHERE user_email = :owner ORDER BY achievement_id");
    $snapshot2->execute([':owner' => $owner]);
    geo_ach_db_assert($snapshot2->fetchAll(PDO::FETCH_ASSOC) === $before, 'disabling changed stored progress');

    // Re-enabling restores the same rows and queues evaluation.
    StepsGeolocationAchievements::setEnabled($pdo, $owner, true);
    $restored = StepsGeolocationAchievements::progress($pdo, $owner);
    geo_ach_db_assert($restored['enabled'] === true && count($restored['achievements']) === $countBefore, 're-enable did not restore rows');
    $queued = $pdo->prepare("SELECT COUNT(*) FROM geo_processor_runs WHERE user_email = :owner AND kind = 'achievements'");
    $queued->execute([':owner' => $owner]);
    geo_ach_db_assert((int)$queued->fetchColumn() >= 1, 'achievement recalculation was not queued');

    // A deletion changes the watermark and progress remains deterministic.
    $pdo->prepare("DELETE FROM geo_location_points WHERE user_email = :owner AND id = (SELECT id FROM geo_location_points WHERE user_email = :owner ORDER BY source_at DESC LIMIT 1)")
        ->execute([':owner' => $owner]);
    StepsGeolocationAchievements::recalculate($pdo, $owner, 'delete');
    $afterDelete = StepsGeolocationAchievements::progress($pdo, $owner);
    geo_ach_db_assert($afterDelete['input_watermark'] !== $firstWatermark, 'deletion did not change the input watermark');

    // Nothing in the response exposes a location or public link.
    $checkKeys = static function (array $value) use (&$checkKeys): void {
        foreach ($value as $key => $child) {
            geo_ach_db_assert(!in_array((string)$key, ['latitude', 'longitude', 'position', 'geometry', 'share_url', 'public_url', 'phone', 'email'], true), 'achievement response leaked: ' . $key);
            if (is_array($child)) $checkKeys($child);
        }
    };
    $checkKeys($afterDelete);

    echo "ok\n";
} finally {
    $cleanup($pdo, $owner, $version);
    $cleanup($pdo, $other, $version);
}
