# AppDashboard

Self-hosted apps shell (PHP) with Docker Compose. One public domain drives all service URLs.

## Quick start

```bash
cp .env.example .env
# edit BASE_DOMAIN (and DB_PASSWORD if needed)
docker compose up -d --build
```

Open `http://localhost/` (local) or `https://<BASE_DOMAIN>/` (server).

## Single domain setting

Set once in `.env`:

```bash
BASE_DOMAIN=example.com
APP_PUBLIC_SCHEME=https
```

On container start, `docker-entrypoint.sh` derives:

| Env | Default |
|-----|---------|
| `APP_PUBLIC_BASE_URL` | `{scheme}://{BASE_DOMAIN}` |
| `AI_PUBLIC_HOST` / `AI_PUBLIC_URL` | `ai.{BASE_DOMAIN}` |
| `SEARCH_PUBLIC_URL` | `search.{BASE_DOMAIN}` |
| `GITEA_PUBLIC_URL` | `git.{BASE_DOMAIN}` |
| `IMMICH_PUBLIC_URL` | `photo.{BASE_DOMAIN}` |
| `DOCUMENT_PUBLIC_URL` | `document.{BASE_DOMAIN}` |
| `DOCKER_PUBLIC_URL` | `docker.{BASE_DOMAIN}` |
| `DAWARICH_PUBLIC_URL` | `dawarich.{BASE_DOMAIN}` |

Override any of those env vars if a service uses a non-standard host.

PHP helpers live in `lib/app_domains.php` (`app_public_url()`, `app_base_domain()`). Nginx is rendered from `nginx/appdashboard.conf.template` (no hardcoded production domain).

## Subdomains to configure (DNS / reverse proxy)

Point these hosts at the AppDashboard host (or the dedicated service behind it):

| Subdomain | Service | Notes |
|-----------|---------|--------|
| `{BASE_DOMAIN}` | AppDashboard shell | Main UI, OAuth, `/ssh-ws`, `/smart-home-ws` |
| `ai.{BASE_DOMAIN}` | OmniRoute / AI gateway | Public AI host; UI also at `/admin-omniroute/` (WS + path rewrite) |
| `search.{BASE_DOMAIN}` | SearXNG | Admin → AI settings (`search_engine_url`) |
| `git.{BASE_DOMAIN}` | Gitea | Compose profile `gitea` |
| `photo.{BASE_DOMAIN}` | Immich | Compose profile `immich` |
| `document.{BASE_DOMAIN}` | OnlyOffice | Document editing |
| `docker.{BASE_DOMAIN}` | Portainer | Compose profile `portainer` |
| `dawarich.{BASE_DOMAIN}` | Dawarich (Maps) | Compose profile `dawarich` |

Local defaults use `BASE_DOMAIN=localhost` and `APP_PUBLIC_SCHEME=http`.

## OmniRoute (`#adminpanel/omniroute`)

Admin Panel embeds OmniRoute at `/admin-omniroute/` (WebSocket Upgrade, `/providers` rewrite, Mixed Content fixes).

Upstream is the OmniRoute Next.js process (default port **20128**), not the public `ai.` hostname:

| Env | Default |
|-----|---------|
| `OMNIROUTE_UPSTREAM` | Docker: `http://host.docker.internal:20128`; bare metal: `http://127.0.0.1:20128` |

Start OmniRoute on the host listening on `20128`, then open `http://localhost/main#adminpanel/omniroute`.

## SSH terminal (WebSSH)

The interactive Admin Panel → SSH Terminal talks to a WebSocket worker:

- Process: `ssh-ws` under supervisord (`webssh/server.php`)
- Listen: `127.0.0.1:8765`
- Public path: `/ssh-ws` (nginx → WS proxy)

It **autostarts** with the container:

1. `deploy/scripts/ensure-ssh-ws.sh` installs `webssh` Composer deps and prepares `data/ssh_ws_tokens` + `data/.ssh`
2. `supervisord.conf` `[program:ssh-ws]` has `autostart=true` / `autorestart=true`
3. Same path on local Docker and server (entrypoint copies supervisord config on every boot)

Check status:

```bash
docker compose exec appdashboard supervisorctl -c /etc/supervisor/supervisord.conf status ssh-ws
docker compose exec appdashboard ss -lntp | grep 8765
```

Restart workers (includes ssh-ws):

```bash
sh deploy/scripts/restart-container-workers.sh
```

Put an SSH private key in the container data dir (`/opt/AppDashboard/data/.ssh/`, e.g. `id_ed25519`), then save the profile in the SSH Terminal settings (gear icon).

On first boot, if no key/profile exist, the entrypoint:

1. Generates `data/.ssh/id_ed25519`
2. Seeds a default profile from `SSH_DEFAULT_HOST` / `SSH_DEFAULT_USER` / `SSH_DEFAULT_PORT` (defaults: `host.docker.internal` / `root` / `22`)
3. Prints the public key — add it to the target host `~/.ssh/authorized_keys`

The WebSocket worker (`ssh-ws`) always autostarts; the profile is only the remote login target.

## Core services

| Service | Port (host) | Notes |
|---------|-------------|--------|
| `appdashboard` | `80` | App + nginx + workers |
| `appdashboard-db` | `—` | Postgres 16 (только внутри docker-сети) |
| `appdashboard-redis` | `—` | Redis (только внутри docker-сети) |

Optional profiles: `portainer`, `duplicati`, `gitea`, `immich`, `dawarich`.

```bash
docker compose --profile gitea --profile immich up -d
```

## Changing the domain

1. Update `BASE_DOMAIN` (and `APP_PUBLIC_SCHEME` if needed) in `.env`
2. `docker compose up -d` (entrypoint re-renders nginx and re-seeds empty DB URL keys)
3. Update DNS for the subdomains in the table above
4. Re-check OAuth redirect URLs / external apps if they cache the old host

Empty config keys are filled from env on boot; non-empty DB values are not overwritten.

## Useful commands

```bash
docker compose up -d --build
docker compose logs -f appdashboard
docker compose exec appdashboard supervisorctl -c /etc/supervisor/supervisord.conf status
BASE_DOMAIN=example.com APP_PUBLIC_SCHEME=https \
  deploy/scripts/render-nginx-conf.sh nginx/appdashboard.conf.template /tmp/appdashboard.conf
```

## Docs

- Docker notes historically lived in `README.docker.md` (this README is the source of truth now).
- Deeper docs: `docs/`
