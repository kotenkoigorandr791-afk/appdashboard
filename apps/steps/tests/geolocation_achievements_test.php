<?php
/**
 * Deterministic achievement rules (task 27).
 *
 *   php apps/steps/tests/geolocation_achievements_test.php
 */
require_once dirname(__DIR__) . '/core/geolocation_achievements.php';

function geo_ach_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$definitions = StepsGeolocationAchievements::definitions();
geo_ach_assert(count($definitions) >= 20, 'expected a meaningful rule set');
$ids = array_column($definitions, 'id');
geo_ach_assert(count($ids) === count(array_unique($ids)), 'duplicate achievement id');
geo_ach_assert(StepsGeolocationAchievements::RULES_VERSION !== '', 'missing rules version');
geo_ach_assert(StepsGeolocationAchievements::PUBLIC === false, 'achievements must stay private');

$families = array_values(array_unique(array_column($definitions, 'family')));
foreach (['countries', 'regions', 'cities', 'places', 'distance', 'coverage'] as $family) {
    geo_ach_assert(in_array($family, $families, true), 'missing achievement family: ' . $family);
}
// The steps half feeds the same ladder: walking and hybrid milestones exist.
foreach (['steps_total', 'steps_day', 'steps_goal', 'steps_streak', 'steps_walk', 'steps_trail'] as $family) {
    geo_ach_assert(in_array($family, $families, true), 'missing steps achievement family: ' . $family);
}
foreach ($definitions as $definition) {
    geo_ach_assert((int)$definition['target'] > 0, 'non-positive target: ' . $definition['id']);
    geo_ach_assert(in_array($definition['unit'], ['count', 'm', 'days', 'cells', 'steps'], true), 'unexpected unit: ' . $definition['id']);
    foreach (['transport', 'mode', 'activity', 'flight', 'drive'] as $forbidden) {
        geo_ach_assert(!str_contains($definition['family'], $forbidden) && !str_contains($definition['id'], $forbidden), 'transport achievement leaked: ' . $definition['id']);
    }
}

// Every definition belongs to a themed collection, and every collection resolves.
foreach ($definitions as $definition) {
    geo_ach_assert(isset(StepsGeolocationAchievements::COLLECTIONS[$definition['collection']]), 'unknown collection: ' . $definition['id']);
}
foreach (StepsGeolocationAchievements::COLLECTIONS as $id => $collection) {
    $matched = 0;
    foreach ($definitions as $definition) {
        foreach ($collection['prefixes'] as $prefix) {
            if (str_starts_with($definition['id'], $prefix)) { $matched++; break; }
        }
    }
    geo_ach_assert($matched > 0, 'empty collection: ' . $id);
}

$metrics = [
    'countries' => 2,
    'regions' => 1,
    'cities' => 4,
    'places' => 3,
    'distance_m' => 1234.5678,
    'coverage_days' => 9,
    'coverage_cells' => 12,
    'steps_total' => 250000,
    'steps_best_day' => 12500,
    'steps_active_days' => 40,
    'steps_goal_days' => 12,
    'steps_streak' => 8,
    'steps_distance_m' => 175000.0,
    'steps_trail_days' => 5,
];
$first = StepsGeolocationAchievements::evaluate($metrics);
$second = StepsGeolocationAchievements::evaluate($metrics);
geo_ach_assert(json_encode($first) === json_encode($second), 'evaluation is not deterministic');
geo_ach_assert(count($first) === count($definitions), 'evaluation row count differs from definitions');

$byId = [];
foreach ($first as $row) $byId[$row['achievement_id']] = $row;
geo_ach_assert($byId['countries_1']['earned'] === true, 'countries_1 should be earned');
geo_ach_assert($byId['countries_1']['progress'] === 1.0, 'earned progress must be capped at the target');
geo_ach_assert($byId['countries_3']['earned'] === false, 'countries_3 should not be earned');
geo_ach_assert($byId['cities_5']['progress'] === 4.0, 'city progress wrong');
geo_ach_assert($byId['places_3']['earned'] === true, 'places_3 should be earned');
geo_ach_assert($byId['coverage_days_7']['earned'] === true, 'coverage_days_7 should be earned');
geo_ach_assert($byId['coverage_days_30']['earned'] === false, 'coverage_days_30 should not be earned');
geo_ach_assert($byId['distance_100']['progress'] === 1234.57, 'distance progress must be rounded to centimetres');
geo_ach_assert($byId['steps_total_100000']['earned'] === true, 'steps_total_100000 should be earned');
geo_ach_assert($byId['steps_total_500000']['earned'] === false, 'steps_total_500000 should not be earned');
geo_ach_assert($byId['steps_day_10000']['earned'] === true, 'steps_day_10000 should be earned');
geo_ach_assert($byId['steps_goal_7']['earned'] === true, 'steps_goal_7 should be earned');
geo_ach_assert($byId['steps_streak_7']['earned'] === true, 'steps_streak_7 should be earned');
geo_ach_assert($byId['steps_streak_30']['earned'] === false, 'steps_streak_30 should not be earned');
geo_ach_assert($byId['steps_walk_200']['earned'] === false, 'steps_walk_200 should not be earned');
geo_ach_assert($byId['steps_trail_7']['earned'] === false, 'steps_trail_7 should not be earned');
geo_ach_assert($byId['steps_trail_7']['progress'] === 5.0, 'hybrid trail progress wrong');

// Same metrics always produce the same row set, even when reordered.
$ordered = [];
foreach ([
    'coverage_cells' => 12, 'coverage_days' => 9, 'distance_m' => 1234.5678,
    'places' => 3, 'cities' => 4, 'regions' => 1, 'countries' => 2,
    'steps_trail_days' => 5, 'steps_distance_m' => 175000.0, 'steps_streak' => 8,
    'steps_goal_days' => 12, 'steps_active_days' => 40, 'steps_best_day' => 12500,
    'steps_total' => 250000,
] as $key => $value) $ordered[$key] = $value;
geo_ach_assert(json_encode(StepsGeolocationAchievements::evaluate($ordered)) === json_encode($first), 'metric order changed the result');

// Missing metrics never make an achievement earned.
$empty = StepsGeolocationAchievements::evaluate([]);
foreach ($empty as $row) {
    geo_ach_assert($row['earned'] === false && $row['progress'] === 0.0, 'empty metrics earned an achievement: ' . $row['achievement_id']);
}

// No response field may expose a location or a public link.
$check = static function (array $rows) use (&$check): void {
    foreach ($rows as $row) {
        foreach ($row as $key => $value) {
            geo_ach_assert(!in_array((string)$key, ['latitude', 'longitude', 'position', 'geometry', 'share_url', 'public_url'], true), 'location field leaked: ' . $key);
            if (is_array($value)) $check([$value]);
        }
    }
};
$check($first);

geo_ach_assert(StepsGeolocationAchievements::normalizeReason('IMPORT') === 'import', 'reason normalization failed');
geo_ach_assert(StepsGeolocationAchievements::normalizeReason('unknown') === 'manual', 'unknown reason not normalized');

// The owner-facing bridge snapshot carries numbers only: no coordinates.
$highlights = StepsGeolocationAchievements::highlights($metrics);
geo_ach_assert($highlights['steps_total'] === 250000, 'step highlight missing');
geo_ach_assert($highlights['steps_trail_days'] === 5, 'hybrid highlight missing');
geo_ach_assert($highlights['route_distance_m'] === 1234.5678, 'track highlight missing');
foreach (array_keys($highlights) as $key) {
    geo_ach_assert(!in_array($key, ['latitude', 'longitude', 'position'], true), 'location field leaked: ' . $key);
}
$zero = StepsGeolocationAchievements::highlights([]);
geo_ach_assert($zero['steps_total'] === 0 && $zero['route_distance_m'] === 0.0, 'empty highlights must be zero');

echo "ok\n";
