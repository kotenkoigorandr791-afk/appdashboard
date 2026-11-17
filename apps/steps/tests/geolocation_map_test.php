<?php
require_once __DIR__ . '/../core/geolocation_map.php';

function geo_map_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

// Viewport parsing rejects malformed, inverted and overly wide boxes.
geo_map_assert(StepsGeolocationMap::parseBbox('30.4,50.4,30.6,50.5') !== null, 'valid bbox rejected');
geo_map_assert(StepsGeolocationMap::parseBbox('') === null, 'empty bbox accepted');
geo_map_assert(StepsGeolocationMap::parseBbox('a,b,c,d') === null, 'non-numeric bbox accepted');
geo_map_assert(StepsGeolocationMap::parseBbox('30.6,50.4,30.4,50.5') === null, 'inverted bbox accepted');
geo_map_assert(StepsGeolocationMap::parseBbox('-179,-80,179,80') === null, 'huge bbox accepted');
geo_map_assert(StepsGeolocationMap::parseBbox('30.4,95,30.6,96') === null, 'out-of-range latitude accepted');

// Zoom policy: coarser geometry and precision as the viewport widens.
$zooms = [4, 8, 12, 16, 19];
for ($i = 1; $i < count($zooms); $i++) {
    geo_map_assert(StepsGeolocationMap::precisionForZoom($zooms[$i]) >= StepsGeolocationMap::precisionForZoom($zooms[$i - 1]), 'precision not monotonic');
    geo_map_assert(StepsGeolocationMap::cellForZoom($zooms[$i]) <= StepsGeolocationMap::cellForZoom($zooms[$i - 1]), 'cell not shrinking with zoom');
    geo_map_assert(StepsGeolocationMap::toleranceForZoom($zooms[$i]) <= StepsGeolocationMap::toleranceForZoom($zooms[$i - 1]), 'tolerance not shrinking with zoom');
}
geo_map_assert(StepsGeolocationMap::precisionForZoom(19) === 5, 'max precision wrong');
geo_map_assert(StepsGeolocationMap::RAW_ZOOM === 13, 'raw zoom threshold changed');

// Providers carry attribution and stay presentation-only settings.
foreach (StepsGeolocationMap::providers() as $key => $provider) {
    geo_map_assert($key !== '', 'provider key missing');
    geo_map_assert($provider['url'] !== '' && str_contains($provider['url'], '{z}'), 'provider url missing tiles: ' . $key);
    geo_map_assert($provider['attribution'] !== '', 'provider attribution missing: ' . $key);
}
geo_map_assert(StepsGeolocationMap::provider('missing')['name'] === 'OpenStreetMap', 'unknown provider fallback');

// Layer selection is limited to known layers and defaults safely.
geo_map_assert(StepsGeolocationMap::layers('trips,points') === ['trips', 'points'], 'layer filter wrong');
geo_map_assert(StepsGeolocationMap::layers('bogus') === StepsGeolocationMap::DEFAULT_LAYERS, 'unknown layers not defaulted');
geo_map_assert(in_array('heatmap', StepsGeolocationMap::layers('heatmap,explored,points'), true), 'opt-in layers missing');

echo "ok\n";
