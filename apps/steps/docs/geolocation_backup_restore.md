# Location backup and restore

Exports are created from **Settings → Location data → Export and backup**. Jobs run in the
existing `steps-geolocation-exports` worker; large histories stream from PostgreSQL into a
file on the app data volume, so the web request only queues the job.

## Formats

| Format | Purpose | Re-import |
| --- | --- | --- |
| GeoJSON | RFC 7946 `FeatureCollection` of canonical points. Properties include `time`, `source_at`, `accuracy`, `device_id`, `quality_state`. | Yes, Location data → Import. |
| GPX | GPX 1.1 track with `<time>`, `<ele>` and `<hdop>` (accuracy ÷ 5). | Yes, including Strava-compatible GPX. |
| JSON | Structured points plus optional derived trips, places and visits (`include_derived`). Timestamps are UTC ISO-8601; `source_offset_minutes` is the original offset when known. | Points are not a generic import format; use GeoJSON/GPX for point round-trips. |
| Backup | Full account document: settings, points, places, trips and their links, stops, visits, devices, import metadata, family groups/members/grants, geofence and notification/map preferences, Immich link and asset metadata. | See below. |

PostGIS geometry is portable in the backup as GeoJSON geometry objects (`Point`,
`LineString`) and in JSON/GeoJSON as WGS84 longitude/latitude pairs. No database dump or
proprietary geometry encoding is used.

## Scopes and retention

An export can cover all history, a date range (UTC, inclusive), one device, one trip, or one
place (points inside its radius). Backup jobs always cover the whole account. Derived records
are optional for JSON exports.

Artifacts expire after `GEO_EXPORT_TTL_HOURS` (default 168 hours / 7 days). Expired artifacts
are deleted by the worker and are refused by the download endpoint. Users can delete a job and
its artifact immediately. Every create, complete, download, expiry and delete writes a
`geo_audit_events` row.

## Restore procedure

1. Restore the PostgreSQL database and its PostGIS extension. The same extension preflight as
   normal startup applies: `CREATE EXTENSION IF NOT EXISTS postgis` must succeed and
   `postgis_full_version()` must be non-empty.
2. Apply migrations with `php apps/steps/bin/geolocation_migrate.php`.
3. Restore account settings and metadata from the backup document. Re-link Immich afterwards:
   the backup intentionally omits credential and server URL ciphertext, and never contains
   Immich image binaries.
4. For points, export/convert the `points` array to GeoJSON or GPX and import it through
   **Location data → Import** (or `StepsGeolocationImports`). This replays the normal
   validation, quality and provenance pipeline. Do not insert `geo_location_points` directly.
5. Recompute statistics/trips/stops after import using their workers.

Point IDs, import IDs and derived rows are informational; a restore creates new canonical
rows through the import pipeline. Keep the backup's `schema_version` when migrating formats.

## Authorization

Artifacts are stored per owner and referenced by database ownership. Downloads go through
`api/geolocation_export_download.php`, re-check the signed-in session, scope by owner, verify
the SHA-256 checksum before streaming, and never use a public URL. Guessing another user's
artifact ID returns `404`.
