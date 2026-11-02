# AppDashboard Docker

AppDashboard is a self-hosted PHP dashboard with bundled PostgreSQL, PgBouncer, Redis and a Python sidecar. The main service is available at `http://localhost/`.

## Start locally

```bash
cp .env.example .env
docker compose up -d --build
```

The default local database password is `appdashboard_dev_password`. Set a unique `DB_PASSWORD` before deploying beyond a disposable local environment.

## Services

| Service | Purpose |
|---|---|
| `appdashboard` | nginx, PHP-FPM, background workers and WebSocket proxies |
| `appdashboard-db` | PostgreSQL 16 |
| `appdashboard-pgbouncer` | PostgreSQL connection pool |
| `appdashboard-redis` | application cache and queues |
| `appdashboard-py` | Python APIs for application features |

## Common commands

```bash
docker compose ps
docker compose logs -f appdashboard
docker compose exec appdashboard supervisorctl -c /etc/supervisor/supervisord.conf status
docker compose down
```

The Compose configuration mounts the working tree into the application container, so source edits are immediately visible. Rebuild after changing system dependencies, Dockerfiles or frontend assets that require a build step.

## Public deployment

Set the public hostname in `.env` before starting the stack:

```bash
BASE_DOMAIN=example.com
APP_PUBLIC_SCHEME=https
```

The entrypoint derives URLs for the integrated AI gateway, search, Gitea, Immich, OnlyOffice, Portainer and Dawarich services. See [README.md](README.md) for the full subdomain map and feature documentation.

## Virtual disks

`appdashboard-vdisk1..3` volumes hold sparse ext4 images (`VDISK1_SIZE`/`VDISK2_SIZE`/`VDISK3_SIZE`, default 20G/30G/40G). The entrypoint loop-mounts them as `/disks/vdisk1..3`, MergerFS merges them into `/storage`, and application data lives in `/storage/applications` and `/storage/uploads`. Usage per disk is sampled every 30 minutes into `storage_usage_samples` (kept 30 days) and charted in Admin Panel → Storage → Disks.
