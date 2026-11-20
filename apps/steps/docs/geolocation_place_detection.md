# Automatic place detection and OSM integration

## Architecture
GPS points -> `geolocation_stops_run.php` (stop clusters, visits) -> `geolocation_osm_run.php` (resolver worker) -> `geo_visits` enriched with name, category, address, status, confidence and stored alternatives (`geo_visit_candidates`). Web (`geolocation_map`, `geolocation_stops`) and Android (`/api/steps/geolocation_stops`) read the same rows. Nothing calls Overpass per GPS update; only the worker does, and only for visits that are pending or provisional past `retry_after`.

Modules (`core/`): `geolocation_place_config.php` (all thresholds, env overrides), `geolocation_stops.php` (detection), `geolocation_osm.php` (Overpass client, cache, normalization), `geolocation_place_ranking.php` (scoring), `geolocation_place_profiles.php` (profile mappings), `geolocation_place_resolver.php` (persistence, manual corrections).

## Stop detection
Defaults: 300 s minimum, 50 m radius, 3 observations, 50 m preferred accuracy (fixes worse than `GEO_STOP_MAX_ACCURACY_M`=200 are ignored), gaps up to 900 s keep a stop alive. Accuracy-weighted centre. Stops still open or younger than `GEO_STOP_CONFIRM_AFTER_S` are provisional. Nearby but separate stops are merged only when the gap is below `GEO_STOP_MERGE_GAP_S` and the centres are within `GEO_STOP_MERGE_RADIUS_M`.
Env: `GEO_STOP_RADIUS_M`, `GEO_STOP_MIN_DURATION_S`, `GEO_STOP_MIN_POINTS`, `GEO_STOP_PREFERRED_ACCURACY_M`, `GEO_STOP_MAX_ACCURACY_M`, `GEO_STOP_MAX_GAP_S`, `GEO_STOP_MERGE_GAP_S`, `GEO_STOP_MERGE_RADIUS_M`, `GEO_STOP_CONFIRM_AFTER_S`.

## Overpass
Backend only. Only the stop centre and search radius (`GEO_OSM_SEARCH_RADIUS_M`, 100 m) are sent; no user, profile or track data.
- Providers (`GEO_OVERPASS_PROVIDERS`, comma separated): overpass-api.de, overpass.private.coffee, ovp.dresel.systems.
- `GEO_OVERPASS_TIMEOUT_S`, `GEO_OVERPASS_MAX_ATTEMPTS`, `GEO_OVERPASS_BACKOFF_MS` (exponential), `GEO_OVERPASS_COOLDOWN_S` (provider skipped after 429/5xx/timeout, honours Retry-After), `GEO_OVERPASS_MIN_INTERVAL_MS`.
- Failover goes to the next provider; if all fail the visit stays `pending` and is retried with backoff (max 6 attempts, then `unidentified`).
- Concurrency/dedupe: a PostgreSQL advisory lock per cache key; a second identical request returns `busy` and is retried later.
- Logging: `osm.fetched`, `osm.provider_error`, `osm.unavailable` (module `steps.geolocation`).

## Cache
`geo_osm_query_cache` maps a grid-snapped query key (`GEO_OSM_CACHE_GRID_DEG`, 0.0005 deg) to element references; objects live once in `geo_osm_places` (type+id, original tags in `tags_json`, `fetched_at`). TTL `GEO_OSM_CACHE_TTL_S` (30 d), empty/failed results `GEO_OSM_NEGATIVE_TTL_S` (1 h). Expired rows are purged by the worker and refreshed on the next lookup.

## Normalization and tag mappings
Category is the first present key of: amenity, shop, office, healthcare, tourism, leisure, craft, man_made, railway, aeroway, public_transport, military, industrial, landuse, building, sport (building is ignored when amenity exists). Stored: type, id, name, category, subcategory, address (`addr:*`), coordinates (centre), geometry (polygon), original tags, retrieval time. Elements without tags are dropped. Match types used by ranking: building, venue (point/area POI), park, transport, area, unknown.

## Scoring
Per candidate, normalized factors in [0,1]: distance (accuracy-corrected), inside polygon, share of observations inside, accuracy, duration, distribution, category, profile relevance, name. Weighted average (`GEO_RANK_W_*`: distance 3, inside 3, accuracy 1, duration 1, distribution 1, category 1.5, profile 1.5, name 1) multiplied by spatial support `0.35 + 0.65*max(distance, inside)`, so a far object cannot win on non-spatial factors. A crowding penalty applies when several candidates tie. Ambiguous buildings (several businesses inside) are capped at `GEO_RANK_AMBIGUOUS_CAP` (0.55) and never confirmed; the building/area is offered with the businesses as alternatives.
Thresholds: `confirmed_confidence` 0.62 -> `confirmed`; between `min_confidence` 0.45 and 0.62, or an open stop -> `provisional`; below -> `unidentified` (neutral, no name). Statuses: `confirmed`, `provisional`, `manual`, `unidentified`, `pending`.

## Profiles
Stored as the existing `occupation` setting (`steps_settings`), edited in web and Android settings; ids: student, school_student, office_worker, remote_worker, freelancer, entrepreneur, self_employed, industrial_worker, driver_courier, taxi_driver, retail_worker, teacher, healthcare_worker, military, public_servant, retired, unemployed, homemaker, athlete, frequent_traveler, working_student (student + office_worker), other, prefer_not_to_say. A profile maps to tag patterns (e.g. student -> amenity=university/college/library/school; office_worker -> office=*, building=office; athlete -> leisure=sports_centre/fitness_centre/stadium). It only changes the profile factor (matching raises, others stay neutral); it never excludes a candidate. `other`, `prefer_not_to_say` and unknown values are neutral. Never sent to Overpass. Changing it through `geolocation_place_prefs` re-queues low-confidence automatic visits.

## Migration
`core/geolocation_schema.php` version 023: tables `geo_osm_places`, `geo_osm_query_cache`, `geo_user_prefs`, `geo_place_blocks`, `geo_visit_candidates`; columns on `geo_visits`: `osm_type`, `osm_id`, `display_name`, `category`, `address`, `detection_status`, `detection_method`, `resolved_at`, `resolve_attempts`, `retry_after`. Runs automatically with `steps_geolocation_migrate`; personal-place visits are backfilled as `manual`/`confirmed`.

## Endpoints
- `GET geolocation_stops[?id=&limit=]`: visits with name, category, address, status, method, confidence, duration, candidates (ranked alternatives), profile.
- `POST geolocation_stops` actions: `confirm`, `reject`, `correct`, `select_candidate` (`candidate_id`), `assign` (`name`, `category`, `tag`, optional `radius_m`; Home/Work/etc. create or update a personal place), `block` (never assign this OSM object again), `retry`, `delete`.
- `GET/POST geolocation_place_prefs`: profile and valid profile ids.
- `geolocation_map` visits layer: one marker per place (grouped visits) with `place_key`, `place_name`, `category`, `address`, `detection_status`, `visit_count`, `dwell_s`, `visit_ids`, `arrived_at`, `departed_at`, `confidence`.
- v1 API `visits`: additive fields `osm_type`, `osm_id`, `category`, `address`, `detection_status`; `place_name` prefers the detected name.
Android uses the same endpoints through `/api/steps/<endpoint>`. Personal places and manual corrections always override OSM names and survive reprocessing.

## Web
Map: right-click menu (add place here, select points within 100 m, draw selection area, copy coordinates; on a visit marker also edit place). Visit markers are coloured by status (dashed outline when provisional) and stop markers overlapping a visit are hidden. Popup: name, category, arrival/departure, duration, confidence, address, visit count. Editor: pick an alternative, custom name/category, Home/Work/School/Gym/Parents/Favorite tag, block the OSM object, detect again. Sidebar "Visited places" lists the visits of the selected range.

## Android
Location library > Visits: name, category, confidence, status chip, duration; "Edit place" opens candidates, custom name/category/tag, detect again, never suggest. Last loaded visits (titles, times, categories; no coordinates) are cached in an app-private file and shown read-only when offline. No new services or background work; no GPS goes to Overpass. Profile is edited in Settings > Personal (occupation).

## Operations
Worker `steps-geolocation-osm` (supervisord) or `php apps/steps/bin/geolocation_osm_run.php [--limit=N] [--user=EMAIL] [--loop]`. Tests: `geolocation_place_detection_test.php`, `geolocation_place_detection_db_test.php`, `geolocation_stops_test.php`, Playwright `e2e/steps/steps-places.spec.ts`, Android `VisitCacheTest`.

## Limitations
Overpass data quality and public-server availability vary; unnamed buildings stay generic. Indoor multi-business buildings are never resolved to one business automatically. Visits are not re-resolved when OSM data changes unless retried or the cache expires. Android offline mode is read-only and limited to the last loaded visit list.
