# Storage policy

## Single source of bulk data

`/storage` (mergerfs over `/disks/vdiskN`, loop-mounted from `/vdisks/vdiskN` images) is the
single source of bulk/persistent application data and the only thing capacity is reported from.

The entrypoint (`docker-entrypoint.sh:441-454`) already enforces this at runtime: once the pool
is mounted it re-exports `APPDATA_BASE_DIR=$STORAGE_ROOT/applications` and
`UPLOADS_BASE_DIR=$STORAGE_ROOT/uploads` after a one-time seed/migration from the legacy
`appdashboard-appdata` volume paths. Every `appdata_*` helper in `shell/storage_helper.php`
reads these env vars, so app code that calls `appdata_base_dir()`, `appdata_uploads_base_dir()`,
or `appdata_user_root_dir()` already resolves under `/storage` in the running container — it is
not a separate, bypassed location as long as the pool is mounted. If the pool is *not* mounted but
was previously unified (`$APPDATA_DATA_DIR/.storage-unified-appdata` exists), the entrypoint
refuses to start (`docker-entrypoint.sh:456-461`, `STORAGE_REQUIRE_POOL=1` by default).

## Allowed on named volumes (not the pool)

- PostgreSQL (`appdashboard-db` data dir), PgBouncer.
- Redis.
- `db.env` / secrets / encryption keys.
- PHP sessions (`APPDATA_SESSION_DIR`), PHP/Nginx temp dirs (`APPDATA_TMP_DIR`,
  `APPDATA_NGINX_TMP_DIR`), mosquitto runtime dir.
- Logs (`appdashboard-logs`, `appdashboard-waf-logs`).
- `storage_disks` state, restic config/job logs, `STORAGE_STATE_DIR`
  (`$APPDATA_DATA_DIR/storage`).

All of the above live on `appdashboard-data` (`/opt/AppDashboard/data`) or
`appdashboard-appdata` (`/opt/ApplicationData`, pre-unification) volumes.

## Everything else → `/storage`

Uploads, per-app data (films, cloud, spends, steps, recipes, smart_home, map, media_viewer),
models, media, backups. Target layout: `/storage/applications/<app>/...`,
`/storage/uploads/...`, `/storage/backup`.

## Consumer → target mapping

| Consumer | Helper used | Resolves to (pool mounted) |
|---|---|---|
| `apps/cloud/*`, `apps/films/*`, `apps/spends/*`, `apps/steps/*`, `apps/recipes/*`, `apps/smart_home/*`, `apps/map/*`, `apps/media_viewer/*` | `appdata_user_root_dir()`, `appdata_app_base_dir()` | `/storage/applications/<app>/<user>/...` |
| `shell/post_handlers.php`, `shell/core.php`, `lib/UserDataPurger.php`, `public-api/routes/shell.php` | `appdata_*` | `/storage/applications/...` |
| Uploads (avatars, support chat, avatar cache) | `appdata_uploads_base_dir()` | `/storage/uploads/...` |
| `lib/Storage/AppDataBackup.php`, `MergerFSManager.php`, `DiskDetector.php`, `DiskMigration.php`, `shell/storage/watcher.php` | `app_storage_root_path()` | `/storage` directly |
| `ssh_terminal_handler.php` | sessions only | `appdashboard-data` (metadata, not bulk) |
| `shell/backup/api.php` (restic state/logs) | `resticDataDir()` | `appdashboard-data/backup` (metadata) |

**Stage-0 decision taken:** keep `appdashboard-appdata` for sessions/tmp/nginx-tmp/mosquitto only
once the pool unification has happened; before unification it also legitimately holds the bulk
data that is about to be seeded into `/storage`. This matches the existing entrypoint design
rather than introducing a new split.

## Container-layer paths (Dockerfile) — audited

- `/var/www/.hyperframes` (`Dockerfile:92-93`): created but unused — `HOME`/`XDG_CACHE_HOME`
  already point at `/opt/ApplicationData/cloud_video_tmp/.hyperframes-*` (`Dockerfile:82-84`),
  which is volume-backed. The `/var/www/.hyperframes` directory is dead weight from an older
  layout; safe to remove in a follow-up, not a live data-loss risk today.
- `/run/php`, `/var/log/*`: ephemeral/log, volume-backed where it matters
  (`appdashboard-logs`).

## Known deliberate deviations from a strict "no host mounts" policy

The main `appdashboard` service currently bind-mounts `/:/host` (rw) and `${HOME}:/host-home`
(rw), and the repo itself at `.:/var/www/AppDashboard` (rw). These are **not** accidental
fallbacks for application data — they back a documented, user-facing feature (integrated restic
host backups, see `docker-compose.yml:273-279` and `README.docker.md`) and the documented live-
edit development workflow. Changing them (read-only repo mount, opt-in profile for host backup)
is a behavior-changing decision that affects a currently working feature and was flagged back to
the user rather than applied unilaterally in this pass; see the task progress log for the exact
blocker.
