<?php
require_once __DIR__ . '/../core/geolocation_timeline.php';

function geo_timeline_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

// Unknown timezones fall back to UTC instead of breaking the view.
geo_timeline_assert(StepsGeolocationTimeline::timezone('Europe/Kyiv') === 'Europe/Kyiv', 'valid timezone rejected');
geo_timeline_assert(StepsGeolocationTimeline::timezone('Mars/Olympus') === 'UTC', 'invalid timezone not defaulted');
geo_timeline_assert(StepsGeolocationTimeline::timezone('') === 'UTC', 'empty timezone not defaulted');

// Day views follow daylight saving instead of assuming 24 hours.
$spring = StepsGeolocationTimeline::range('day', '2026-03-29', null, null, 'Europe/Kyiv');
$springHours = (strtotime($spring['to']) - strtotime($spring['from'])) / 3600;
geo_timeline_assert(abs($springHours - 23) < 0.01, 'spring DST day is ' . $springHours . 'h, expected 23h');
$autumn = StepsGeolocationTimeline::range('day', '2026-10-25', null, null, 'Europe/Kyiv');
$autumnHours = (strtotime($autumn['to']) - strtotime($autumn['from'])) / 3600;
geo_timeline_assert(abs($autumnHours - 25) < 0.01, 'autumn DST day is ' . $autumnHours . 'h, expected 25h');

$normal = StepsGeolocationTimeline::range('day', '2026-11-13', null, null, 'Europe/Kyiv');
geo_timeline_assert(strtotime($normal['to']) - strtotime($normal['from']) === 86400, 'normal day is not 24h');
geo_timeline_assert($normal['label'] === '2026-11-13', 'day label wrong');

$month = StepsGeolocationTimeline::range('month', '2026-02-15', null, null, 'Europe/Kyiv');
geo_timeline_assert($month['label'] === '2026-02', 'month label wrong');
geo_timeline_assert((new DateTimeImmutable($month['to_local']))->format('d') === '01', 'month does not end on the first');

$year = StepsGeolocationTimeline::range('year', '2026-06-01', null, null, 'UTC');
geo_timeline_assert($year['label'] === '2026', 'year label wrong');
geo_timeline_assert(str_starts_with($year['from'], '2026-01-01'), 'year start wrong');

$interval = StepsGeolocationTimeline::range('interval', null, '2026-11-01T00:00:00+02:00', '2026-11-05T00:00:00+02:00', 'Europe/Kyiv');
geo_timeline_assert($interval['view'] === 'interval', 'interval view lost');
$invalid = false;
try {
    StepsGeolocationTimeline::range('interval', null, '2026-11-05', '2026-11-01', 'UTC');
} catch (InvalidArgumentException $e) {
    $invalid = true;
}
geo_timeline_assert($invalid, 'inverted interval accepted');

// Local rendering is explicit about the display zone.
$local = StepsGeolocationTimeline::localTime('2026-11-13T10:00:00Z', 'Europe/Kyiv');
geo_timeline_assert(str_ends_with($local, '+02:00'), 'winter offset wrong: ' . $local);
$summer = StepsGeolocationTimeline::localTime('2026-07-13T10:00:00Z', 'Europe/Kyiv');
geo_timeline_assert(str_ends_with($summer, '+03:00'), 'summer offset wrong: ' . $summer);

// Cursors are opaque and round-trip.
$cursor = StepsGeolocationTimeline::cursorEncode('2026-11-13T10:00:00Z', 'trip', 'abc');
geo_timeline_assert(!str_contains($cursor, '2026'), 'cursor leaks the timestamp');
$decoded = StepsGeolocationTimeline::cursorDecode($cursor);
geo_timeline_assert($decoded !== null && $decoded['id'] === 'abc' && $decoded['kind'] === 'trip', 'cursor round-trip failed');
geo_timeline_assert(StepsGeolocationTimeline::cursorDecode('not-base64!') === null, 'invalid cursor accepted');

// Gaps only appear above the configured threshold.
$base = 1_800_000_000;
$times = [gmdate('c', $base), gmdate('c', $base + 60), gmdate('c', $base + 60 + 3600)];
$gaps = StepsGeolocationTimeline::gaps($times, 1800);
geo_timeline_assert(count($gaps) === 1 && $gaps[0]['duration_s'] === 3600, 'gap detection wrong');
geo_timeline_assert(StepsGeolocationTimeline::gaps($times, 7200) === [], 'short gap kept');
geo_timeline_assert(StepsGeolocationTimeline::gaps([], 1800) === [], 'empty times produced a gap');

echo "ok\n";
