# NexOrbit

NexOrbit is a self-hosted PHP dashboard that integrates multiple personal and team applications. It includes authentication, OAuth 2.0, shared files, admin panel, public API, Telegram integration, MCP server, WebSSH, automated backups and various application modules.

## Branded & Secure

- **App Name**: NexOrbit
- **OAuth Providers**: Google, GitHub
- **Customizable Branding**: Custom app name, logo, favicon
- **Digital Signatures**: App signing for enhanced security and trust
- **Session & Token Auth**: Multiple secure authentication methods
- **Full OAuth 2.0 Provider**: Authorization server with OpenID Connect support

## Core Services

| Service | URL | Description |
|---------|-----|-------------|
| **NexOrbit** | `http://localhost/` or `https://<BASE_DOMAIN>/` | Main dashboard UI, session-based auth, shared files, admin panel, OAuth login |
| **AI Gateway** | `https://ai.<BASE_DOMAIN>` | OmniRoute AI routing, supports multiple LLM providers |
| **Search Engine** | `https://search.<BASE_DOMAIN>` | SearXNG meta search engine |
| **Git** | `https://git.<BASE_DOMAIN>` | Gitea self-hosted Git service |
| **Photos** | `https://photo.<BASE_DOMAIN>` | Immich photo/video management |
| **Documents** | `https://document.<BASE_DOMAIN>` | OnlyOffice document editing |
| **Docker** | `https://docker.<BASE_DOMAIN>` | Portainer container management |
| **Maps** | `https://dawarich.<BASE_DOMAIN>` | Dawarich mapping tool |
| **WebSSH** | `https://<BASE_DOMAIN>/ssh-ws` | Interactive SSH terminal in browser |

## Authentication Methods

| Method | Usage | Token Type |
|--------|-------|------------|
| **Session** | Web browser login | Cookie (`session_id`) |
| **Bearer (Password Grant)** | Mobile apps, CLI, scripts | JWT Access + Refresh tokens |
| **OAuth 2.0 / OIDC** | Third-party apps, MCP, SSO | OAuth Access Token |
| **Desktop Client Auth** | Native desktop apps | JWT (browser-mediated) |

- **Session Auth**: Web UI at `/login`, `/register`, `/verify`, `/logout`
- **Bearer Auth**: API base at `/public-api/v1/auth`, endpoints: `/auth/login`, `/auth/me`, `/auth/refresh`, `/auth/revoke`
- **OAuth**: Authorization server with discovery at `/.well-known/openid-configuration`, supports all standard flows (authorization code, client credentials, PKCE)

### Supported OAuth Providers

- **Google** — Social login via Google accounts
- **GitHub** — Social login via GitHub accounts

Configure providers in Admin Panel → Configuration → OAuth Providers

## Key Features

- **Branding**: Fully customizable app name (NexOrbit), logo, and favicon
- **OAuth 2.0**: Full Authorization Server + OpenID Provider support (3rd-party SSO, AI assistants)
- **Social Login**: Google and GitHub OAuth integration
- **App Signing**: Digital signatures for enhanced security and trust
- **Shared Files**: Public/private file sharing without login
- **Admin Panel**: User management, app access, security settings, backups
- **Public API**: RESTful API for Cloud, Films, Steps, Spends, Recipes, etc.
- **Telegram Bot**: Integration with Telegram for notifications
- **MCP Server**: Model Context Protocol integration for AI assistants
- **WebSSH**: Browser-based SSH terminal with auto-start
- **Automated Backups**: Restic-based backups (no Duplicati required)
- **App Modules**: Cloud, Films, Recipes, Maps, Media Viewer, Performance, Spends, Steps

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

## Backups (restic)

Backups are built into the `appdashboard` container — no Duplicati service is needed. Open **Admin Panel → Backup** to configure the repository, sources, schedule, retention, and to browse/restore snapshots.

- `restic` is installed in the image; jobs run in the background and can be cancelled.
- The host filesystem is mounted read-write at `/host`; use paths such as `/home`, `/etc`, or `/mnt/main_backup` in the UI (they resolve under `/host`).
- Restore targets are host paths too: `/restore` writes to `/host/restore`.
- The scheduler runs every minute inside the container via Supervisor (`backup-scheduler`).
- Repository credentials are encrypted with AES-256-GCM in the app database; backup data is encrypted by restic itself.

```bash
docker compose exec appdashboard restic version
```

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
| `appdashboard-db` | `—` | PostgreSQL 16, internal Docker network |
| `appdashboard-pgbouncer` | `—` | PostgreSQL connection pool, internal Docker network |
| `appdashboard-redis` | `—` | Redis, internal Docker network |
| `appdashboard-py` | `—` | Python application sidecar, internal Docker network |

Optional profiles: `portainer`, `gitea`, `immich`, `dawarich`.

```bash
docker compose --profile gitea --profile immich up -d
```

## Storage usage

`storage-usage-collector` fills `storage_usage_cache` with per-user disk usage for Cloud,
Immich, Gitea and Dawarich; the chart reads only that cache. External database credentials
come from `STORAGE_USAGE_*` variables in `.env` (unset credentials mark the source
unavailable). See [docs/30_storage_usage_collector.md](docs/30_storage_usage_collector.md).

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

- Docker setup: [README.docker.md](README.docker.md)
- Application architecture and modules: [docs/00_documentation_index.md](docs/00_documentation_index.md)
- MCP server: [docs/25_appdashboard_mcp.md](docs/25_appdashboard_mcp.md)
- Public API: [docs/27_public_api.md](docs/27_public_api.md)
