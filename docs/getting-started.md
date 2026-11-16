# Getting Started

## Prerequisites

- Docker 24+ & Docker Compose 2.20+
- 2 GB RAM minimum (4 GB recommended)
- Domain or local hostname (for OAuth/WebDAV)
- Ports 80/443 available (or customize in `docker-compose.yml`)

---

## Installation

### 1. Clone & Configure

```bash
git clone https://github.com/your-repo/apps-dashboard.git
cd apps-dashboard
cp .env.example .env
```

Edit `.env` — **minimum required**:

```env
# Required
DB_PASSWORD=your_strong_password_here
APP_URL=https://your-domain.com  # or http://localhost

# Optional but recommended
REDIS_PASSWORD=your_redis_password
JWT_SECRET=your_jwt_secret_64_chars_min
```

### 2. Start Services

```bash
docker compose up -d --build
```

First build takes 3–5 minutes (PHP extensions, OnlyOffice, dependencies).

### 3. Verify

| Check | Command |
|-------|---------|
| App running | `curl -I http://localhost` → `200 OK` |
| Health endpoint | `curl http://localhost/health` → `{"status":"ok"}` |
| Database | `docker compose exec appdashboard-db pg_isready` |
| Logs | `docker compose logs -f appdashboard` |

### 4. First Login

Open `https://your-domain.com` (or `http://localhost`)

- **Email**: `root@root`
- **Password**: `12345T`

**Immediately**: Go to **Profile → Change Password** and **Admin → Users** to create your personal admin account.

---

## Post-Install Configuration

### Email (Required for Registration/Password Reset)

Admin → **Configuration → Email**:

```env
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="AppDashboard"
```

Test: Admin → Configuration → **Send Test Email**.

### OAuth Providers (Google, GitHub, Microsoft, etc.)

Admin → **Configuration → OAuth Providers** → Add Provider:

| Field | Example (Google) |
|-------|------------------|
| Name | `google` |
| Client ID | `12345.apps.googleusercontent.com` |
| Client Secret | `GOCSPX-...` |
| Scopes | `openid email profile` |
| Auth URL | `https://accounts.google.com/o/oauth2/v2/auth` |
| Token URL | `https://oauth2.googleapis.com/token` |
| Userinfo URL | `https://www.googleapis.com/oauth2/v3/userinfo` |

Callback URL for all providers: `https://your-domain.com/oauth/callback`

### HTTPS (Production)

**Option A**: Reverse proxy (Traefik, Nginx Proxy Manager, Caddy) — handles SSL automatically.

**Option B**: Built-in Let's Encrypt (requires domain pointing to server):

```bash
# In .env
ENABLE_SSL=true
SSL_EMAIL=admin@yourdomain.com
```

Then restart: `docker compose up -d --build`

---

## Directory Structure (Docker Volumes)

| Volume | Purpose | Backup? |
|--------|---------|---------|
| `appdashboard_data` | Uploaded files, OnlyOffice cache, MCP package | **Yes** |
| `appdashboard_db` | PostgreSQL data | **Yes** (use Admin → Backup) |
| `appdashboard_redis` | Sessions, cache | No (ephemeral) |

---

## Updating

```bash
cd apps-dashboard
git pull
docker compose up -d --build  # Rebuilds images
docker compose exec appdashboard php project/cli.php migrate  # Run migrations if any
```

Check `CHANGELOG.md` for breaking changes.

---

## Uninstall / Reset

```bash
docker compose down -v  # Removes volumes = ALL DATA LOST
```

---

## Troubleshooting First Start

| Symptom | Fix |
|---------|-----|
| `502 Bad Gateway` | Wait 30s for PHP-FPM to start; check `docker compose logs appdashboard` |
| Database connection failed | Verify `DB_PASSWORD` matches in `.env` and `docker-compose.yml` |
| OnlyOffice not loading | Ensure `ONLYOFFICE_JWT_SECRET` in `.env` matches `docker-compose.yml` |
| OAuth redirect mismatch | Check `APP_URL` in `.env` exactly matches browser URL (scheme + host) |
| Permission denied on uploads | `docker compose exec appdashboard chown -R www-data:www-data /var/www/html/storage` |

---

## Next Steps

1. [Core Concepts](core-concepts.md) — Understand auth, app access, sharing
2. [Services Reference](services/) — Explore each app's features
3. [User Guides](user-guides/) — Step-by-step tutorials