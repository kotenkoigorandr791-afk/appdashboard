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
foreach ($definitions as $definition) {
    geo_ach_assert((int)$definition['target'] > 0, 'non-positive target: ' . $definition['id']);
    geo_ach_assert(in_array($definition['unit'], ['count', 'm', 'days', 'cells'], true), 'unexpected unit: ' . $definition['id']);
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

// Same metrics always produce the same row set, even when reordered.
$ordered = [];
foreach ([
    'coverage_cells' => 12, 'coverage_days' => 9, 'distance_m' => 1234.5678,
    'places' => 3, 'cities' => 4, 'regions' => 1, 'countries' => 2,
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

echo "ok\n";
