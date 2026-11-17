<?php
require_once dirname(__DIR__) . '/core/geolocation_search.php';
require_once dirname(__DIR__) . '/core/geolocation_saved_views.php';
require_once dirname(__DIR__) . '/core/geolocation_analytics.php';

function geo_search_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$now = '2026-11-15T12:00:00+00:00';
$today = StepsGeolocationSearch::parse('today', 'UTC', null, null, $now);
geo_search_assert($today['from'] === '2026-11-15' && $today['to'] === '2026-11-15' && $today['text'] === '', 'today shortcut wrong');
$lastWeek = StepsGeolocationSearch::parse('last 7 days quality issues', 'UTC', null, null, $now);
geo_search_assert($lastWeek['from'] === '2026-11-09' && $lastWeek['to'] === '2026-11-15', 'last 7 days wrong');
geo_search_assert(str_contains($lastWeek['text'], 'quality issues'), 'residual text lost');
$month = StepsGeolocationSearch::parse('october 2026', 'UTC', null, null, $now);
geo_search_assert($month['from'] === '2026-10-01' && $month['to'] === '2026-10-31', 'month shortcut wrong');
$day = StepsGeolocationSearch::parse('2026-10-12', 'UTC', null, null, $now);
geo_search_assert($day['from'] === '2026-10-12' && $day['to'] === '2026-10-12', 'exact day wrong');
$explicit = StepsGeolocationSearch::parse('gym', 'Europe/Kyiv', '2026-10-01', '2026-10-03', $now);
geo_search_assert($explicit['from'] === '2026-10-01' && $explicit['text'] === 'gym', 'explicit range wrong');
try {
    StepsGeolocationSearch::parse('', 'UTC', '2026-01-01', '2025-01-01', $now);
    throw new RuntimeException('invalid range accepted');
} catch (InvalidArgumentException $e) {
}
$range = StepsGeolocationSavedViews::resolvePeriod('last_7_days', null, null, 'Europe/Kyiv');
geo_search_assert($range['from'] !== $range['to'] && $range['timezone'] === 'Europe/Kyiv', 'saved period resolution wrong');
$query = StepsGeolocationSavedViews::validateQuery([
    'q' => 'work', 'types' => ['place', 'bogus'], 'source_type' => 'mobile',
    'geometry_mode' => 'compare', 'layers' => ['improved', 'bogus'], 'min_distance_m' => -5,
]);
geo_search_assert($query['types'] === ['place'], 'query type allowlist failed');
geo_search_assert($query['layers'] === ['improved'], 'layer allowlist failed');
geo_search_assert($query['min_distance_m'] === 0.0, 'numeric bound failed');
$analytics = StepsGeolocationAnalytics::resolveRange('2026-11-01', '2026-11-07', 'UTC');
geo_search_assert($analytics['from'] === '2026-11-01' && $analytics['days'] === 7, 'analytics range wrong');
echo "ok\n";
