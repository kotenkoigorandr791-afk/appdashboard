# Geolocation testing, acceptance and rollout (task 32)

## Migrations 017–018

`steps_geolocation_migrate()` applies `017` inside its advisory-locked
transaction. It is additive: job columns with defaults, `geo_feature_flags`,
queue/lease/owner indexes, a geofence lookup index, a linear duplicate-event
cleanup and a unique `(user_email, geofence_id, event_type, occurred_at)` index,
then `ANALYZE`. Existing rows keep working; old workers can still insert
`processing` runs because the new columns have defaults.

Migration `018` adds the API list covering index, the quality/owner index and the
active-geofence owner index. Rollback drops only those indexes:

```sql
DROP INDEX IF EXISTS geo_location_points_api_list_idx;
DROP INDEX IF EXISTS geo_location_points_quality_owner_idx;
DROP INDEX IF EXISTS geo_geofences_active_owner_idx;
DELETE FROM geo_schema_migrations WHERE version = '018';
```

Validation on a populated database (no new container required):

```bash
php apps/steps/tests/geolocation_jobs_db_test.php
php apps/steps/tests/geolocation_features_db_test.php
php apps/steps/tests/geolocation_observability_test.php
php apps/steps/tests/geolocation_api_db_test.php
php apps/steps/tests/geolocation_privacy_db_test.php
php apps/steps/tests/geolocation_security_test.php
php apps/steps/tests/geolocation_mcp_db_test.php
```

Rollback is module-owned schema only, in this order (after stopping workers and
taking a backup):

```sql
DROP INDEX IF EXISTS geo_geofence_events_dedupe_idx;
DROP INDEX IF EXISTS geo_geofence_events_lookup_idx;
DROP TABLE IF EXISTS geo_feature_flags;
DROP INDEX IF EXISTS geo_processor_runs_created_idx;
DROP INDEX IF EXISTS geo_processor_runs_kind_state_idx;
DROP INDEX IF EXISTS geo_processor_runs_owner_kind_idx;
DROP INDEX IF EXISTS geo_processor_runs_lease_idx;
DROP INDEX IF EXISTS geo_processor_runs_queue_idx;
ALTER TABLE geo_processor_runs
  DROP COLUMN IF EXISTS priority,
  DROP COLUMN IF EXISTS attempts,
  DROP COLUMN IF EXISTS max_attempts,
  DROP COLUMN IF EXISTS next_attempt_at,
  DROP COLUMN IF EXISTS lease_until,
  DROP COLUMN IF EXISTS heartbeat_at,
  DROP COLUMN IF EXISTS cancel_requested,
  DROP COLUMN IF EXISTS worker_id,
  DROP COLUMN IF EXISTS correlation_id,
  DROP COLUMN IF EXISTS last_error,
  DROP COLUMN IF EXISTS started_at,
  DROP COLUMN IF EXISTS finished_at;
DELETE FROM geo_schema_migrations WHERE version = '017';
```

Dropping columns discards job diagnostics, not user location/trip/photo data.
Keep the geofence dedupe index unless you intentionally accept duplicate events.

## Feature flags

Flags default enabled. A tenant override lives in `geo_feature_flags`; global
kill switches use `GEO_FEATURES_DISABLED=capture,map` or
`GEO_FEATURE_VIDEO=false`. Flags never delete rows and can be reverted
independently:

```bash
curl -X POST .../geolocation_features.php \
  -H 'Content-Type: application/json' \
  -d '{"action":"set","flag":"video","enabled":false}'
```

Flags cover `capture`, `family`, `immich`, `video`, `achievements`, `map`,
`mcp`. `capture` gates new points/imports but leaves reads available; the others
gate their endpoint family. Audit events record every change.

## Acceptance checklist

- [ ] Migration 017 applies cleanly to an empty DB and to a populated DB; no
      duplicate geofence event survives.
- [ ] Worker restart during processing: lease expires, job is reaped and retried;
      completed work is not duplicated.
- [ ] Retryable failure backs off, non-retryable/exhausted failure becomes
      `failed`; cancel of pending/processing work is observed cooperatively.
- [ ] Duplicate client events and exact geofence transitions are rejected.
- [ ] Health returns `ok` with PostGIS/Redis available and `degraded` when a
      non-critical dependency is down; redacted logs contain correlation IDs.
- [ ] Family access remains capped at 15 members, grant-scoped and precision
      clamped; no public location/trip link exists.
- [ ] Immich is the only photo integration; MP4 rendering still works; videos
      are owner-only artifacts.
- [ ] Continuous trips are not split by transport inference.
- [ ] Map viewport/date bounds and `429 queue_full` backpressure behave.
- [ ] Load test records RPS/p50/p95/p99/errors with dataset size and hardware.
- [ ] Backup/restore and account deletion validation still pass.

## Rollout and rollback

1. Take a DB backup and record migration version/health baseline.
2. Deploy the additive migration and new workers; keep old API traffic.
3. Enable flags for internal tenants, run smoke/queue/load checks, then expand
   cohorts. Watch queue age, DB CPU, map p95 and ingest errors.
4. Roll back a cohort with its feature flag first. Roll back the release only if
   the endpoint/worker code is faulty; the additive schema does not require an
   immediate down-migration.
5. After stabilization, run retention cleanup and archive the load-test report.

User-visible status: keep serving existing reads during a capture/video/map
kill switch; the API returns `403 feature_disabled` only for the disabled
capability, and the UI can show a banner without deleting history.
