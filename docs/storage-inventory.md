# Storage inventory

## Storage helpers

| Function | File | Env var | Default |
|---|---|---|---|
| `appdata_base_dir()` | `shell/storage_helper.php:3-7` | `APPDATA_BASE_DIR` | `/opt/ApplicationData` (re-exported to `/storage/applications` once the pool is unified, `docker-entrypoint.sh:450`) |
| `appdata_uploads_base_dir()` | `shell/storage_helper.php:8-12` | `UPLOADS_BASE_DIR` | `<appdata>/dashboard/uploads` (re-exported to `/storage/uploads`, `docker-entrypoint.sh:451`) |
| `appdata_data_dir()` | `shell/storage_helper.php:13-17` | `APPDATA_DATA_DIR` | `/opt/AppDashboard/data` (metadata, stays off the pool) |
| `appdata_user_root_dir()` / `app_storage_root_path()` | `shell/storage_helper.php:55-73` | `STORAGE_ROOT` | `/storage` |
| `storage_state_dir()` | `lib/Storage/bootstrap.php:64-77` | `STORAGE_STATE_DIR` or derived from `APPDATA_DATA_DIR` | fails fast if neither is set (fixed in this pass — previously fell back to `APP_ROOT/data`) |
| `resticDataDir()` | `shell/backup/api.php:491-499` | `APPDATA_DATA_DIR` | fails fast if unset (fixed in this pass — previously fell back to `APP_ROOT/data`) |

## Fallback removal (Stage 1 — done)

```
grep -rn "APP_ROOT . '/data'\|dirname(__DIR__,2) . '/data'" --include='*.php' .
```
→ empty (verified).

New `storage_assert_ready()` in `lib/Storage/bootstrap.php` validates `APPDATA_BASE_DIR`,
`APPDATA_DATA_DIR` exist/writable and, when `$requirePool` (default true), that `STORAGE_ROOT`
is an actual `fuse.mergerfs` mount (`storage_pool_available()` → `MergerFSManager::isMounted()`,
parses `/proc/self/mountinfo`). Called from `shell/bootstrap.php` on every web/CLI request through
that entrypoint. Verified both directions in the running container:
- missing `APPDATA_BASE_DIR`/`APPDATA_DATA_DIR` → `[storage] APPDATA_BASE_DIR is not set.`, exit 1.
- normal env → `OK`.

## Volumes (docker-compose.yml)

| Volume | Mount | Purpose |
|---|---|---|
| `appdashboard-data` | `/opt/AppDashboard/data` | metadata: `storage_disks` state, restic state/logs, misc app config (`db.env`, `secrets`, `news`, `ssh_ws_tokens`, `steps_tiles`) |
| `appdashboard-appdata` | `/opt/ApplicationData` | pre-unification bulk data + sessions/tmp/nginx-tmp/mosquitto |
| `appdashboard-vdisk{1,2,3}` | `/vdisks/vdisk{1,2,3}` | sparse ext4 images, loop-mounted to `/disks/vdiskN`, merged by mergerfs into `/storage` |
| `appdashboard-logs` | `/var/log/{nginx,bot,supervisor,php-fpm}` | logs |
| `appdashboard-waf-logs` | `/var/log/modsecurity` | WAF logs |

## Host bind mounts on the `appdashboard` service (verified in `docker-compose.yml`)

| Mount | Mode | Status |
|---|---|---|
| `.:/var/www/AppDashboard` | rw | documented dev live-edit workflow (README.docker.md); **not changed** this pass — see policy doc |
| `/:/host` | rw | backs the integrated restic "host backup" feature (`RESTIC_HOST_ROOT`); **not changed** this pass |
| `${HOME}:/host-home` | rw | same feature (`RESTIC_BIND_HOME`); **not changed** this pass |
| `/var/run/docker.sock` | rw | required for container management features |
| waf/nginx config mounts | ro | already read-only |

## Runtime verification performed

```
docker exec appdashboard sh -lc 'cat /proc/1/environ | tr "\0" "\n" | grep -E "APPDATA_BASE_DIR|STORAGE_ROOT"'
→ APPDATA_BASE_DIR=/storage/applications
→ STORAGE_ROOT=/storage

docker exec appdashboard sh -lc 'ls /storage'
→ applications backup documents files shared uploads appdashboard lost+found .appdashboard-disk-id
```

This confirms the running container's actual `appdata_*` consumers already resolve under
`/storage`, not a bypassed volume — the entrypoint's env re-export (`docker-entrypoint.sh:441-454`)
already implements most of the "single source" goal described in this task for the apps listed
in `docs/storage-policy.md`.

## Remaining write-site audit

Not run exhaustively in this pass (large surface: `file_put_contents`, `fopen(...,'w')`, `mkdir`,
`move_uploaded_file`, `unlink`, `rename`, `copy` across `apps/*`). The regression guard described
for Stage 6 (`scripts/storage/check-no-local-writes.sh`) was not implemented in this pass; see
the progress log for what remains.
