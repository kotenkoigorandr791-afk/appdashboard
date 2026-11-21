<?php
/**
 * Static guardrails for task 36 page loading and predictive map fetching.
 *
 *   php apps/steps/tests/geolocation_page_performance_test.php
 */
$root = dirname(__DIR__);
$head = (string)file_get_contents($root . '/views/head.php');
$body = (string)file_get_contents($root . '/views/body.php');
$scripts = (string)file_get_contents($root . '/views/scripts.php');
$api = (string)file_get_contents($root . '/api/geolocation_map.php');

function perf_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

// Initial HTML must not be parser-blocked by chart/cropper/map code.
perf_assert(!preg_match('/<script[^>]+src=["\'][^"\']*chart\.umd\.min\.js/i', $head), 'Chart.js is an eager head script');
perf_assert(!preg_match('/<script[^>]+src=["\'][^"\']*cropper/i', $head), 'Cropper is an eager head script');
perf_assert(!preg_match('/<link[^>]+leaflet/i', $head), 'Leaflet CSS is eagerly referenced');
perf_assert(str_contains($head, 'ensureStepsCharts'), 'chart lazy loader missing');
perf_assert(str_contains($head, 'imagesrcset'), 'responsive background preload missing');
foreach (['background_light-960.jpg', 'background_light-1920.jpg', 'background_dark-960.jpg', 'background_dark-1920.jpg'] as $image) {
    perf_assert(is_file($root . '/' . $image), 'responsive background missing: ' . $image);
}

// Map activation is explicit; no map API/bootstrap may run at parse time.
perf_assert(str_contains($body, 'function startMapWorkspace'), 'map activation function missing');
perf_assert(str_contains($body, "addEventListener('steps-map-shown'"), 'map activation is not driven by map mode');
perf_assert(!str_contains($body, "await loadSettings();\n                        try{\n                            const L=await ensureLeaflet();"), 'legacy eager map bootstrap is still present');
perf_assert(str_contains($body, 'function ensureLeaflet'), 'Leaflet loader missing');
perf_assert(str_contains($body, "X-Steps-Prefetch"), 'visible/prefetch request marker missing');
perf_assert(str_contains($body, 'AbortController'), 'viewport cancellation missing');
perf_assert(str_contains($body, 'prefetchAbort.abort()'), 'in-flight prefetch cancellation missing');
perf_assert(str_contains($body, 'shiftedBounds'), 'directional prefetch bounds missing');
perf_assert(str_contains($body, 'prefetchModes'), 'prefetch user modes missing');
perf_assert(str_contains($body, 'saveData'), 'Data Saver guard missing');
perf_assert(str_contains($body, "navigator.onLine===false"), 'offline guard missing');
perf_assert(str_contains($body, "document.hidden"), 'hidden-tab guard missing');
perf_assert(str_contains($body, 'chunkCache'), 'bounded chunk cache missing');
perf_assert(str_contains($body, 'viewportSeq'), 'stale-response sequence guard missing');
perf_assert(str_contains($body, 'bboxQuery()'), 'bounded viewport bbox missing');
perf_assert(str_contains($body, 'window.stepsMapPerf'), 'map performance counters missing');
perf_assert(str_contains($body, "extent:'1'"), 'bounded extent lookup missing');
// Background imports/removals must invalidate the cached viewport chunks.
perf_assert(str_contains($body, 'window.stepsMapInvalidate'), 'map cache invalidation hook missing');
perf_assert(str_contains($scripts, 'stepsMapInvalidate'), 'import completion does not refresh the map');

// Chart rendering is deferred until idle/interaction.
perf_assert(str_contains($scripts, 'requestIdleCallback'), 'chart paint is not deferred to idle');
perf_assert(str_contains($scripts, 'async function renderChart'), 'chart renderer is not async');
perf_assert(str_contains($scripts, 'await window.ensureStepsCharts()'), 'chart renderer does not wait for lazy assets');

// Server contract: bounded extent, chunk metadata and incident kill switches.
perf_assert(str_contains($api, 'prefetch_enabled'), 'prefetch feature flag missing');
perf_assert(str_contains($api, 'overlays_enabled'), 'overlay feature flag missing');
perf_assert(str_contains($api, "'extent'"), 'extent lookup missing');
perf_assert(str_contains($api, 'ETag:'), 'chunk ETag missing');
perf_assert(str_contains($api, 'HTTP_IF_NONE_MATCH'), 'conditional chunk request missing');
perf_assert(str_contains($api, 'bounded_chunks'), 'bounded chunk marker missing');

echo "ok\n";
