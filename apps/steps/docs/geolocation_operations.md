# Geolocation operations, scaling and health (task 31)

## Source of truth and queue

PostgreSQL is the only source of truth for geolocation work. Redis stores
metrics, cache entries and invalidation versions; losing Redis never loses a
job. The durable queue is `geo_processor_runs`, extended by migration `017`:

- `state`: `pending`, `processing`, `ready`, `failed`, `stale`, `cancelled`
- `priority`: `critical=0`, `interactive=10`, `normal=50`, `bulk=100`
- `attempts` / `max_attempts` / `next_attempt_at` for retry with exponential backoff
- `worker_id` / `lease_until` / `heartbeat_at` for crash recovery
- `cancel_requested` for cooperative cancellation
- `correlation_id` / `last_error` for redacted diagnostics

Workers claim with `FOR UPDATE SKIP LOCKED`, honor a per-user concurrency cap
(`GEO_MAX_ACTIVE_JOBS_PER_USER`, default 2) and renew the 300 s lease while
running. A restarted worker never double-runs a live lease; `reap` requeues an
expired lease or fails it after the attempt limit. API enqueue paths apply
backpressure (`429 queue_full`) at `GEO_MAX_PENDING_JOBS_PER_USER` (default 20).

`geolocation_maintenance_run.php` runs every 60 s under supervisord and does
`reap`, retention cleanup, backlog sampling and `ANALYZE` of hot tables. Job
retention is `GEO_JOB_RETENTION_DAYS` (default 30), keeping the newest 20
terminal rows per owner/kind. Cleanup also removes exact duplicate geofence
events and stale pending geofence notifications.

## Hot-path changes

- Points queries are owner/date bounded and use the existing
  `(user_email, source_at DESC)` plus GiST position indexes. Map viewports are
  capped at 500 points and 90 degrees; date ranges over 3660 days are rejected.
- Map features are cached for 20 s by owner target, bbox, range, zoom, layers,
  filters, precision, grant generation and cache version. Any accepted point,
  privacy correction/deletion or cache-version bump invalidates the owner.
- Ingestion emits accepted/duplicate metrics and bumps the owner cache version.
- Geofence polling only processes new points, writes events with
  `ON CONFLICT DO NOTHING`, and uses a deterministic notification dedupe key.
  Trips/stops/statistics use their watermark and do not rescan an empty backlog.
- Feature gates (`capture`, `family`, `immich`, `video`, `achievements`, `map`,
  `mcp`) are checked at the endpoint and can be disabled per tenant without
  deleting data.

## Health and readiness

`GET /apps/steps/api/geolocation_health.php` is available to localhost or with
`X-Geo-Health-Token: $GEO_HEALTH_TOKEN`. It returns `ok`, `degraded` or `fail`
and never includes coordinates, emails or secrets. Checks:

- PostGIS availability/version and migration version
- queue backlog: pending/processing counts, oldest pending age, stalled leases
- disk free/used percentage and artifact bytes
- Redis ping (optional; a Redis outage is degraded, not fatal)
- redacted counters and average timings

Use `503` (`status=fail`) for load-balancer removal; keep `degraded` serving.

## Metrics and alerts

Redis hash `geo:metrics` stores counters and `_sum_ms`/`_count` timings exposed
by the health endpoint:

- `geo_ingest_points_total{result=accepted|duplicate}`
- `geo_map_requests_total{cache=hit|miss}`
- `geo_map_query_ms{...}` average
- map/export/video/job counters added by workers

Recommended alerts:

| Alert | Condition | Action |
| --- | --- | --- |
| Queue age | `pending_age_s > GEO_QUEUE_AGE_ALERT_S` (900) | check worker processes and DB locks |
| Stalled leases | `stalled > 0` for 2 min | inspect maintenance/reap logs |
| Job failures | failed rate rising over 5 min | inspect redacted `last_error` and upstream |
| PostGIS down | health `fail` | restore DB; APIs return 503 |
| Disk | `used_percent >= 90` | clean artifacts/exports, expand volume |
| Redis down | health degraded/redis false | queue still works; fix cache/metrics |
| Map latency | p95 query > 500 ms | check plan/index bloat and cache hit rate |

## Runbook

1. `docker compose ps` and `docker compose logs --tail=200 appdashboard`; confirm
   `steps-geolocation-maintenance` and each worker is running.
2. `curl -s http://127.0.0.1:8080/apps/steps/api/geolocation_health.php | jq`.
3. If queue age grows: check `pg_stat_activity` for locks/long queries, then
   `pg_stat_statements` for the top statements. Do not kill workers blindly;
   expired leases recover on their own.
4. If geofence duplicates reappear: verify migration 017's unique index exists;
   run `geolocation_maintenance_run.php` once for cleanup.
5. If map latency regresses: inspect `EXPLAIN (ANALYZE, BUFFERS)` for the
   viewport query, check cache hit ratio and `pg_stat_user_indexes`.
6. If DB CPU is saturated: reduce API concurrency at the proxy, keep interactive
   jobs ahead of bulk imports, and scale read capacity before raising workers.
7. Roll back a capability with a feature flag (see rollout doc), not by
   deleting data.

## Capacity test

Run inside the app container:

```bash
# One tenant is throttled by the 600 read/min API key limit.
php apps/steps/bin/geolocation_load_test.php --seed=50000 --concurrency=32 --duration=20
# Multi-tenant random viewports (exercises the bbox/date plans).
php apps/steps/bin/geolocation_load_test.php --seed=320000 --users=64 --concurrency=64 --duration=20
# Repeated viewports (--bboxes=16) model map refreshes.
php apps/steps/bin/geolocation_load_test.php --seed=0 --users=64 --concurrency=64 --duration=20 --bboxes=16
```

The harness seeds deterministic points per owner and drives
`v1.php?path=points` through PHP `curl_multi`; each request gets a different
viewport unless `--bboxes` is set. It prints RPS, p50/p95/p99, max latency and
error counts, then revokes its temporary keys. Data remain for repeat runs; pass
`--users=` for separate tenants and `--seed=0` to reuse.

Measured 2026-11-14 on this host (12 CPU, PostGIS 16-3.5, 2 GB shared buffers,
64 tenants / 320k points, 64 concurrent readers, 20 s, zero errors):

| Workload | Requests | RPS | p50 | p95 | p99 |
| --- | ---: | ---: | ---: | ---: | ---: |
| Before (32 tenants / 160k, random) | 3,928 | 195.5 | 152 ms | 266 ms | 343 ms |
| After, repeated viewports | 7,471 | **371.7** | 163 ms | 277 ms | 403 ms |
| After, random viewports | 7,086 | **351.9** | 172 ms | 287 ms | 404 ms |

That is an ~80% throughput increase on the same host. The gains came from:

- worker owner discovery now reads pending `geo_processor_runs` instead of
  grouping the whole points table on every tick (the old query was 101 ms and
  ran ~12x/s);
- `geo_location_points_api_list_idx` covers the point page (no heap fetches),
  with quality/owner indexes for queue and filter paths;
- API-key requests no longer start a PHP/Redis session (`X-API-Key` was missed
  by the session check), cutting a session write from every read.

The single-tenant run still hits `rate_limited`: that is the deliberate 600
read/min key limit, not a database ceiling. The points plan uses
`geo_location_points_user_time_idx` for ordering and the covering index for the
page; date scoping keeps the 30-day window bounded.

Capacity model: `active_users ≈ (sustained_RPS × 60) / requests_per_user_per_minute`.
At the observed 352–372 RPS and a conservative 6 map/API reads per active user
per minute, the current host supports roughly **3,500 concurrently active users**
with p95 under 300 ms; at 2 reads/min it is ~10,500. Total registered users can
be much higher because most are idle. Keep DB CPU below ~70%, leave headroom for
imports/renders, and re-measure after changing workers, data volume or hardware.
This is one host, not a production guarantee; scale the app/DB before raising
concurrency beyond ~64, and give each tenant its own rate limit.

### Load balancer

The host front door renders an nginx `upstream appdashboard_backend` with
`least_conn`, keepalive and failure ejection. Set a comma-separated
`APP_UPSTREAMS=127.0.0.1:8080,127.0.0.1:8081` (or run the renderer with it) to
spread traffic across multiple app instances; one value preserves single-node
behavior. Database connections already go through pgbouncer, so the app tier is
the only hop that needs horizontal fan-out.

### Query plan checks

```sql
EXPLAIN (ANALYZE, BUFFERS)
SELECT id, source_at FROM geo_location_points
 WHERE user_email = :owner AND source_at >= :from AND source_at <= :to
   AND position && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
 ORDER BY source_at DESC, id DESC LIMIT 50;
```

Expect `geo_location_points_api_list_idx` or `geo_location_points_user_time_idx`,
no sequential scan, and bounded buffers. Run `VACUUM (ANALYZE)` after large
imports so index-only scans stay effective.
