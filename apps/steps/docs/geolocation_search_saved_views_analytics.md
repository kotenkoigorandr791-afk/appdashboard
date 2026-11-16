# Search, saved views, and privacy-aware analytics

Task 35 adds a private exploration layer over the already-authorized Steps
location data. There is no search service, analytics service, public link,
separate database, or transport classifier.

## Search

`StepsGeolocationSearch` searches owner-scoped metadata: trips/titles/notes,
places/tags, visits, geofences, devices, imports, exports, achievements, quality
issues, jobs, Immich matches and active family grants. Family grants are listed
only for the authenticated viewer; other tenants' records never appear even as
a hint.

Requests are bounded by date (default last 90 days, maximum 3,660 days), result
cap, per-user rate limit and cursor pagination. Raw point coordinates are not a
search corpus. The resolved range, timezone and entity types are returned with
every response.

Natural-language shortcuts are resolved locally: `today`, `yesterday`,
`last 7/30/90 days`, `this/last week`, `this/last month`, month names, `YYYY-MM`,
exact dates, `quality issues`, `pending imports`, `offline devices`,
`new places` and `longest trips`. The resolved date range is always displayed.

Metadata has trigram indexes for trips, places, geofences, devices and import
filenames. Free-text query strings are never written to routine logs.

## Saved views

`geo_saved_views` stores only a private definition: name, context, date period,
timezone, allowlisted filters, geometry/layer choices and presentation flags.
It never stores result ids, coordinates, copied family data, access tokens or
public share secrets.

CRUD, duplicate, pin, default, reorder, export/import of the definition and
`apply` are implemented. `apply` resolves relative periods again in the current
timezone and the caller re-runs search with current permissions. A saved view
never grants access. Removing consent or reducing scope changes the underlying
query results without preserving cached data.

System templates: Today, Last 7 Days, Trips, Places, New Places, Quality
Issues, Pending Sync, Geofence Events, Import History, Data for Export.

## Analytics

`StepsGeolocationAnalytics` materializes bounded period summaries:

- distance, trips, duration, active days, stops/visits, unique/new places,
  geofence events, imports/exports/renders and point quality counts;
- median accuracy, source/device contribution, daily calendar, hour-of-day
  matrix, largest gaps and quality/time coverage;
- confidence (`high`, `medium`, `low`) and explicit warnings when coverage is
  weak or a gap is long;
- previous-period comparison with percentages suppressed when either period
  has inadequate coverage.

Formula version: `analytics-1`. No metric uses or creates `car`, `walk`, `bike`,
`train`, `boat` or `flight` labels. Cached payloads are scoped by owner, range,
timezone and formula version and expire after five minutes. Invalidation is
wired through the central statistics invalidation path, which is reached by
imports, corrections, place/trip changes, privacy changes and retention work.

## APIs

- `geolocation_search`: `GET` search; `mode=suggest` for bounded suggestions.
- `geolocation_saved_views`: `GET` list/templates/apply/export; `POST` create,
  update, delete, duplicate, pin, default, reorder and import.
- `geolocation_analytics`: `GET` summary; `POST` `recompute` or `refresh`.

All three are in the web session gateway and the mobile bearer-token allowlist,
use the existing endpoint authorization and rate limits, and return only owner
data. Family execution continues through the existing permissions layer.

## Jobs and operations

`apps/steps/bin/geolocation_analytics_run.php` refreshes pending analytics
caches through `geo_processor_runs`; it is idempotent and bounded. Search never
runs as a background wide scan. The analytics cache table is removed during
account purge. A saved view or analytics cache can be deleted at any time; no
raw history is deleted by either operation.

Incident disablement: remove the new endpoint entries or return empty results at
the gateway, stop the analytics worker, and leave raw location data untouched.
Rollback can drop/ignore `geo_saved_views`, `geo_analytics_cache` and their
indexes; migration 020 does not alter canonical coordinates.

## UX and accessibility

Web Settings → Location includes unified search, active resolved range,
suggestions, private saved views, coverage/analytics cards and a comparison
toggle. Android Location includes Search, Saved and Analytics tabs, a cancellable
debounced search field, saved-view apply/pin/delete actions and coverage metrics.
Both surfaces use text labels in addition to color and expose the same bounded
authorization states.

## Verification

- `geolocation_search_test.php` covers date shortcuts, allowlists and ranges.
- `geolocation_search_db_test.php` covers migration 020, tenant-safe search,
  saved-view CRUD/apply/duplicate, analytics metrics/cache and the no-transport
  policy.
- Existing map, privacy, statistics, quality, matching, API and Android build
  checks remain required.
