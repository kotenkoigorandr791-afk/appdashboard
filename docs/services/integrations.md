# External App Integrations

**Web UI**: `/apps/{name}/` **API Base**: `/public-api/v1/integrations` **Auth**: Bearer + per-app access

---

## Overview

AppDashboard acts as an **OAuth 2.0 / OIDC identity provider** for integrated apps. Users log in once to AppDashboard, then launch external apps with **Single Sign-On (SSO)** — no separate credentials needed.

| App | Purpose | SSO Protocol | Auto-Provision |
|-----|---------|--------------|----------------|
| **Dawarich** | RSS/Feed reader | OAuth 2.0 | Yes |
| **Gitea** | Git hosting | OAuth 2.0 | Yes |
| **Sentry** | Error tracking | OAuth 2.0 | Yes |
| **Immich** | Photo management | OAuth 2.0 / OIDC | Yes |
| **Kanboard** | Task/Project management | OAuth 2.0 | Yes |
| **Lopaka** | Custom app | Session/Proxy | No |

---

## User Functions

| Feature | Description |
|---------|-------------|
| **One-Click Launch** | Click app icon in dashboard → auto-login → land in app |
| **App Access Control** | Admin grants/revokes per user (Users → Edit → App Access) |
| **Unified Avatar/Profile** | Synced from AppDashboard (name, email, avatar) |
| **Admin Impersonation** | Admin can "Launch as user" for troubleshooting |

---

## API Endpoints

### Integration Management (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/integrations` | List configured integrations |
| `POST` | `/integrations` | Add integration. Body: `app_key`, `name`, `client_id`, `client_secret`, `auth_url`, `token_url`, `userinfo_url`, `scopes`, `icon_url?`, `launch_url` |
| `GET` | `/integrations/{id}` | Integration details |
| `PATCH` | `/integrations/{id}` | Update (enable/disable, credentials, URLs) |
| `DELETE` | `/integrations/{id}` | Remove integration |
| `GET` | `/integrations/{id}/launch` | Get SSO launch URL for current user (redirects to app) |

### User-Facing Launch

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/public-api/v1/apps` | List apps user has access to (with launch URLs) |
| `GET` | `/public-api/v1/apps/{key}/launch` | Redirect to app with SSO token |

---

## Web Routes

| Path | Description |
|------|-------------|
| `/apps/dawarich/` | Launch Dawarich (SSO redirect) |
| `/apps/gitea/` | Launch Gitea (SSO redirect) |
| `/apps/sentry/` | Launch Sentry (SSO redirect) |
| `/apps/immich/` | Launch Immich (SSO redirect) |
| `/apps/kanboard/` | Launch Kanboard (SSO redirect) |
| `/apps/lopaka/` | Launch Lopaka (proxy) |

---

## Adding a New Integration (Admin Guide)

### 1. Configure External App

**Dawarich** (RSS Reader):
```
# In Dawarich .env
OIDC_PROVIDER_URL=https://your-domain.com
OIDC_CLIENT_ID=dawarich
OIDC_CLIENT_SECRET=...
OIDC_SCOPES=openid profile email
```

**Gitea** (Git):
```
# In Gitea app.ini
[oauth2]
ENABLED = true
[openid]
ENABLED = true
# Add AppDashboard as OAuth2 application in Gitea Admin → Applications
```

**Sentry** (Errors):
```
# Sentry Organization Settings → Auth → Add SAML/OIDC Provider
# Entity ID: https://your-domain.com
# ACS URL: https://sentry.io/saml/acs/...
```

**Immich** (Photos):
```
# In Immich .env
OIDC_ENABLED=true
OIDC_ISSUER_URL=https://your-domain.com
OIDC_CLIENT_ID=immich
OIDC_CLIENT_SECRET=...
OIDC_SCOPES=openid profile email
```

**Kanboard** (Tasks):
```
# In Kanboard config.php
define('OAUTH2_CLIENT_ID', 'kanboard');
define('OAUTH2_CLIENT_SECRET', '...');
define('OAUTH2_AUTHORIZATION_ENDPOINT', 'https://your-domain.com/public-api/v1/oauth/authorize');
define('OAUTH2_TOKEN_ENDPOINT', 'https://your-domain.com/public-api/v1/oauth/token');
define('OAUTH2_USERINFO_ENDPOINT', 'https://your-domain.com/public-api/v1/oauth/userinfo');
```

**Lopaka** (Custom):
- Simple PHP app; proxied via `/apps/lopaka/` with session passed through

### 2. Register in AppDashboard

Admin → **Integrations** → **Add Integration**:

| Field | Example (Gitea) |
|-------|-----------------|
| App Key | `gitea` (unique slug) |
| Display Name | `Gitea` |
| Client ID | `gitea` (from Gitea OAuth app) |
| Client Secret | `...` |
| Auth URL | `https://git.yourdomain.com/login/oauth/authorize` |
| Token URL | `https://git.yourdomain.com/login/oauth/access_token` |
| Userinfo URL | `https://git.yourdomain.com/api/v1/user` |
| Scopes | `read:user,user:email` |
| Icon URL | `https://git.yourdomain.com/img/favicon.png` |
| Launch URL | `https://git.yourdomain.com` |

### 3. Grant Access

Admin → **Users** → Edit user → **App Access** → check `gitea` → Save

---

## API Proxy Endpoints

Some apps expose their API through AppDashboard for unified auth:

| App | Proxy Base | Notes |
|-----|------------|-------|
| Kanboard | `/public-api/v1/kanboard/*` | Full JSON-RPC API proxy |
| Dawarich | `/public-api/v1/dawarich/*` | If configured |

**Usage**:
```bash
# Instead of direct Kanboard API call:
curl -H "Authorization: Bearer <token>" https://your-domain.com/public-api/v1/kanboard/jsonrpc.php \
  -d '{"jsonrpc":"2.0","method":"getAllProjects","id":1}'
```

---

## MCP Tools (AI Assistant)

| Tool | Description |
|------|-------------|
| `list_integrations` | List user's accessible integrations |
| `launch_integration` | Get launch URL for integration |

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Invalid redirect_uri" | Check OAuth app in external app matches `https://your-domain.com/oauth/callback` exactly |
| User not auto-provisioned | Verify `userinfo_url` returns `sub` (unique ID), `email`, `name`; check scopes include `email` |
| Launch loops back to dashboard | External app not configured to trust AppDashboard OIDC; check issuer URL, client ID/secret |
| Kanboard API 401 | Ensure Kanboard `OAUTH2_*` config points to AppDashboard endpoints; user has `kanboard` app access |
| Immich "Invalid token" | Immich expects `access_token` in `Authorization: Bearer`; check `OIDC_CLIENT_SECRET` matches |
| Sentry SSO fails | Sentry uses SAML, not OIDC; configure SAML in Sentry with AppDashboard as IdP (Admin → Integrations → SAML) |
| Lopaka not loading | Check `/apps/lopaka/` proxy in `shell/routes.php`; verify Lopaka container running |