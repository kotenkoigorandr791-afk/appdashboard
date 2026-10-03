# Storage Usage Collector

Per-user disk usage chart in `Admin Panel → View Visit Statistics` (`#ap-storage-usage-card`).

Web requests never scan files or query external databases: they only read the pre-computed
cache table `storage_usage_cache`.

## Components

| Component | Path | Role |
|-----------|------|------|
| Cache table + helpers | `db.php` | `storage_usage_cache`, `loadStorageUsageSnapshot()`, `storageUsageRequestRefresh()`, `storageUsageConsumeRefreshRequest()` |
| Source collectors | `lib/storage_usage_sources.php` | read-only queries per source |
| Worker | `project/bin/storage_usage_collector.php` | one-shot / loop / dry-run |
| Supervisor program | `supervisord.conf` → `storage-usage-collector` | periodic refresh every `STORAGE_USAGE_INTERVAL` (default 900 s) |
| Admin action | `project/post_handlers.php` → `refresh_storage_usage` | sets the refresh flag only (never queries external DBs) |

## Sources

| Key | Data source |
|-----|-------------|
| `cloud` | own database, `cloud_storage_usage_stats` |
| `immich` | `asset` + `asset_exif.fileSizeInByte` joined with `"user"` on `email` |
| `gitea` | `repository.size` + `repository.lfs_size` (KiB → bytes) joined with `"user"` on `email`/`name` |
| `dawarich` | `active_storage_attachments` + `active_storage_blobs.byte_size`; owner from `assets.user_id`, or from `imports`/`exports` when the `assets` table is absent |

External connections use `default_transaction_read_only = on`, `connect_timeout` and
`statement_timeout` (`STORAGE_USAGE_TIMEOUT_MS`, default 20000 ms).

Rows without `asset_exif` are counted as items with 0 bytes.

## Behaviour

- Successful cycle: source rows are replaced in one transaction (`storageUsageReplaceSource()`).
- Failed cycle: previous rows are kept, only the source status changes
  (`ok` / `unavailable` / `error`).
- Statuses are stored in `app_meta` under `storage_usage_sources` and shown under the chart.
- Admin "Refresh" only sets `storage_usage_refresh_requested` in `app_meta`; the worker picks
  it up on its next cycle.

## Configuration

All variables are optional; unset credentials mark the source `unavailable`.

```bash
STORAGE_USAGE_ENABLED=1
STORAGE_USAGE_INTERVAL=900
STORAGE_USAGE_DB_HOST=host.docker.internal
STORAGE_USAGE_DB_PORT=5432
STORAGE_USAGE_TIMEOUT_MS=20000

STORAGE_USAGE_IMMICH_DB=immich
STORAGE_USAGE_IMMICH_DB_USER=postgres
STORAGE_USAGE_IMMICH_DB_PASSWORD=...

STORAGE_USAGE_GITEA_DB=gitea
STORAGE_USAGE_GITEA_DB_USER=gitea
STORAGE_USAGE_GITEA_DB_PASSWORD=...

STORAGE_USAGE_DAWARICH_DB=dawarich_development
STORAGE_USAGE_DAWARICH_DB_USER=postgres
STORAGE_USAGE_DAWARICH_DB_PASSWORD=...
```

Read-only database roles are recommended. The host PostgreSQL must be reachable from the
`appdashboard` container (`extra_hosts: host.docker.internal:host-gateway` is already set).

With the bundled compose profiles (`--profile gitea --profile immich`) the application
databases live in the docker network instead, so point the collector at them explicitly:

```bash
STORAGE_USAGE_DB_HOST=appdashboard-db
STORAGE_USAGE_IMMICH_DB_USER=postgres
STORAGE_USAGE_DAWARICH_DB=dawarich_development
```

## Manual runs

```bash
docker compose exec appdashboard php project/bin/storage_usage_collector.php --once
docker compose exec appdashboard php project/bin/storage_usage_collector.php --dry-run
docker compose exec appdashboard php project/bin/storage_usage_collector.php --source=immich
docker compose exec appdashboard php project/bin/storage_usage_collector.php --loop --interval=900
```

`--dry-run` performs no writes at all, including the refresh flag.