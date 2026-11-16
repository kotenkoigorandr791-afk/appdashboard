# Steps page performance and predictive map loading (task 36)

## What changed

- The initial Steps document no longer loads or initializes Leaflet, map tiles, or
  the map viewport API. Map startup is bound to the first `steps-map-shown` event.
- Chart.js is loaded asynchronously after first paint (`requestIdleCallback`) and on
  demand for chart interactions. Cropper had no call sites and was removed from the
  initial page.
- The full-size 5001px background photo is replaced by responsive 960px/1920px
  variants with an `imagesrcset` preload and a low fetch priority.
- Initial map requests always carry a bounded viewport `bbox`. A cheap `extent=1`
  lookup is used once when no safe viewport is stored.
- The map client uses request sequencing and `AbortController`, one in-flight
  prefetch request, a 24-entry in-memory chunk cache, and a directional
  look-ahead scheduler with two prefetch chunks per movement at most.
- Prefetch modes are `Off`, `Wi-Fi only`, `Balanced` (default), and `Aggressive`.
  Data Saver, `2g`/`3g`/`slow-2g`, offline, hidden tabs, missing network hints, and
  server errors all suppress or stop prefetch.
- Map responses carry an opaque `ETag`, `Vary: Cookie, Authorization`,
  `X-Steps-Map-Chunk: bounded`, and an expiry/scope marker in `meta.chunk`.
- Incident switches: `GEO_MAP_PREFETCH_DISABLED=1` and
  `GEO_MAP_OVERLAYS_DISABLED=1`. `GEO_FEATURES_DISABLED=map` remains the full map
  kill switch.

No new service, database, CDN, queue, or second auth mechanism is introduced.
Family authorization, precision, retention, coordinate redaction, and CSRF/session
checks remain server-side and unchanged.

## Measurement method

- Before revision: `7b1aeefe` (parent of the task 36 change).
- After revision: the task 36 working tree/commit.
- Environment: Windows, Chrome `157.0.8103.78` headless, content viewport
  `758x482`, no CPU or network throttling; nginx Brotli enabled; local Docker
  stack. Cache was cleared with CDP `Network.clearBrowserCache` for cold runs.
- Authenticated fixture: existing local `root@root` account with 6,961 accepted
  location points and 111 trips in `geo_location_points` / `geo_trips`. No real
  coordinates, credentials, or tokens are stored in this document or in the
  committed benchmark script.
- Three independent cold/warm/map/pan runs were taken before and after. Values
  below are medians. FCP/LCP/TBT come from PerformanceObserver and paint entries;
  bytes/requests come from Resource Timing `transferSize`. "Open map" is the wall
  time from clicking Map until Leaflet, at least one loaded tile, and a non-loading
  summary are present.
- Harness: `apps/steps/tests/performance/benchmark_page_map.mjs`. It is sanitized
  and prints no cookie, full resource URLs, coordinates, or response bodies.

## Performance results

| Scenario | Before (median) | After (median) | Change | Target/budget | Pass/Fail |
| --- | ---: | ---: | ---: | ---: | --- |
| Signed-in dashboard, cold | TTFB 223 ms; FCP 928 ms; LCP 928 ms; 796,685 B; 25 req; 2 map API calls; Leaflet 2, Chart 3, Cropper 2 | TTFB 229 ms; FCP 592 ms; LCP 592 ms; 181,961 B; 16 req; 0 map API calls; Leaflet 0, Chart 3, Cropper 0 | FCP/LCP -36.2%; bytes -77.2%; requests -36%; map bootstrap -100% | FCP/LCP <= 1,000 ms; HTML+critical <= 200 KB; map work = 0 | Pass |
| Signed-in dashboard, warm | FCP/LCP 580 ms; 5,043 B; 25 req | FCP/LCP 268 ms; 2,898 B; 16 req | FCP/LCP -53.8%; bytes -42.5%; requests -36% | FCP/LCP <= 400 ms | Pass |
| Open map mode | 169 ms; map already initialized while hidden | 412 ms; Leaflet CSS+JS 40.5 KB and API 2.5 KB loaded on demand | +243 ms (+143.8%) | <= 800 ms | Pass, but a measured regression vs preloading. This is the intentional trade-off for zero map work on the dashboard. |
| First visible viewport | 43–81 ms (median 56 ms), 335 B, empty; request had no bounded `bbox` before `didFit` | 61 ms, 1,210 B, 1 place, bounded bbox; no duplicate post-fit request | +5 ms; bounded response is larger because it contains an authorized place | API <= 250 ms; <= 500 points/chunks | Pass |
| Slow pan (24-step, ~1.1 s) | +1 map API call; 50 ms | +1 visible call + 1 prefetch; 2.3 KB; prefetch 2.3 KB | One directional chunk added; no fan-out | Visible first; <= 2 prefetch requests per move | Pass |
| Rapid pan (8-step flick) | +1 map API call; no stale guard | +1 visible call; no broad prefetch; AbortController/sequence guard active | Same request count; stale responses cannot overwrite newer state | <= 2 deltas per gesture; zero stale renders | Pass (INP not instrumented) |
| Zoom and date/layer change | Not measured | Not measured | Not measured | <= 300 ms to stable viewport | Not measured — follow-up |
| Large history / dense viewport | Not measured before | 148 features, 108,369 B; 1.008 s cold cache miss, 40–45 ms warm cache; PostGIS plan 0.414 ms / 13 shared buffers / 500-row index-only scan | N/A | API <= 1.5 s cold, <= 250 ms warm; <= 500 rows | Pass after; before unknown |
| Constrained mobile network/CPU | Not measured | Responsive 960px background is 39 KB vs 574 KB original; no throttled browser run | Background bytes -93.2% | TTI <= 3 s; long tasks <= 200 ms | Not fully measured — follow-up |

INP was not captured by this harness; TBT stayed at 0 ms and no application
exceptions occurred in the after runs. The only network error in the successful
after runs was the existing `favicon.ico` 404.

## Predictive prefetch effect

Measured after the slow and rapid pan on the authenticated fixture:

- visible viewport requests: 3
- prefetch requests: 2
- prefetch bytes: 4,542 B
- prefetch hit rate: 0% (the test did not return to a prefetched viewport)
- stale/aborted: 0 in this run; cancellation is covered by the sequence guard and
  the static regression test
- prefetch concurrency: 1
- server errors: 0

Prefetch only starts after visible work completes, uses `priority: 'low'`, and
does not run for unknown/slow network classes, Data Saver, background tabs,
offline mode, or a disabled feature flag.

## SQL/PostGIS

The high-load viewport query used the existing
`geo_location_points_api_list_idx` index-only path:

```text
Limit (actual time=0.096..0.303 rows=500)
  -> Index Only Scan using geo_location_points_api_list_idx
       Filter: position && ST_MakeEnvelope(...) AND quality_state = 'accepted'
       Heap Fetches: 0
       Buffers: shared hit=13
Planning Time: 9.969 ms
Execution Time: 0.414 ms
```

No new index was required. The map cache key includes target, viewer, effective
precision, permission generation, date range, bbox, zoom, layers, geometry mode,
and filters. The client cache is memory-only and is cleared on date, layer, family,
and authentication changes; it is not shared across accounts or scopes. ETag
revalidation returned `304` with 0 bytes in the verification run.

## Verification run

```text
php -l apps/steps/views/head.php
php -l apps/steps/views/body.php
php -l apps/steps/views/scripts.php
php -l apps/steps/api/geolocation_map.php
php apps/steps/tests/geolocation_page_performance_test.php
php apps/steps/tests/geolocation_map_test.php
php apps/steps/tests/geolocation_map_db_test.php
php apps/steps/tests/geolocation_privacy_db_test.php
php apps/steps/tests/geolocation_permissions_db_test.php
php apps/steps/tests/geolocation_security_test.php
```

All of the above passed. Browser verification was run against the authenticated
local session; cold loads made no map API calls and loaded no Leaflet assets.

## Rollout and rollback

1. Deploy with `GEO_MAP_PREFETCH_DISABLED=1` if the environment is sensitive to
   extra background requests; visible map loading is unaffected.
2. Use `GEO_MAP_OVERLAYS_DISABLED=1` to stop dynamic overlay layers while keeping
   the map shell available.
3. Disable the whole map with the existing `GEO_FEATURES_DISABLED=map` kill switch.
4. Roll back the page/API commit to return to eager map/chart loading. No schema or
   stored data migration is involved.
5. Watch `geo_map_requests_total`, `geo_map_query_ms`, and
   `window.stepsMapPerf` (`visible`, `prefetch`, `prefetchHits`, `cancelled`,
   `stale`, `bytes`, `prefetchBytes`, `errors`). Alert on error growth or
   prefetch bytes above 786 KB/minute.

## Known follow-ups

- Measure zoom/date/layer switching and a throttled mobile CPU/network profile
  with the same harness; these were not captured in this pass.
- Add an INP/long-task interaction benchmark for dragging and switching layers.
- Consider a small skeleton for the chart area while Chart.js is loading.
- Prefetch remains in-memory only; no persistent coordinate cache is introduced.
