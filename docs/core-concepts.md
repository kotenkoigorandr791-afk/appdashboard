# Core Concepts

Understand these before using the API or configuring apps.

---

## Authentication Methods

AppDashboard supports **four** authentication modes. Use the right one for your client.

| Method | Used By | Header / Mechanism | Token Lifetime |
|--------|---------|-------------------|----------------|
| **Session Cookie** | Web browser (dashboard UI) | `session_id` cookie (HttpOnly, SameSite=Lax) | 2 hours sliding |
| **Bearer Token** | Mobile apps, scripts, API clients | `Authorization: Bearer <token>` | 1 hour access / 30 days refresh |
| **OAuth 2.0 / OIDC** | Third-party apps, SSO, MCP | `Authorization: Bearer <access_token>` | Per provider (typically 1h) |
| **Device ID + Credentials** | Desktop app (first login) | `X-Device-Id: <uuid>` + email/password | Returns Bearer tokens |

### Session (Web UI)
- Automatic on `/login`, `/register`, OAuth callback
- CSRF protected (double-submit cookie)
- Logout: `POST /logout` or close browser

### Bearer Token (API)
**Login**:
```bash
curl -X POST https://your-domain.com/public-api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Device-Id: $(uuidgen)" \
  -d '{"email":"user@example.com","password":"secret"}'
```

**Response**:
```json
{
  "access_token": "eyJ...",
  "refresh_token": "eyJ...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": { "id": 1, "email": "user@example.com", "name": "User" }
}
```

**Use**:
```bash
curl -H "Authorization: Bearer eyJ..." https://your-domain.com/public-api/v1/cloud/files
```

**Refresh**:
```bash
curl -X POST https://your-domain.com/public-api/v1/auth/refresh \
  -H "Authorization: Bearer <refresh_token>"
```

### OAuth 2.0 / OIDC (External Apps)

**Endpoints** (standard OIDC):
```
GET  /.well-known/openid-configuration
GET  /.well-known/oauth-authorization-server
GET  /public-api/v1/oauth/authorize?client_id=...&response_type=code&redirect_uri=...&scope=openid profile
POST /public-api/v1/oauth/token
GET  /public-api/v1/oauth/userinfo
POST /public-api/v1/oauth/revoke
```

**Scopes supported**: `openid`, `profile`, `email`, `cloud`, `films`, `smart_home`, `steps`, `spends`, `recipes`, `admin`

### Desktop Client Auth (Browser-Mediated)

For native apps that can't securely store secrets:

1. App calls `POST /public-api/v1/auth/client_auth/request` → returns `poll_url` + `device_code`
2. User opens `poll_url` in browser → logs in → approves
3. App polls `POST /public-api/v1/auth/client_auth/poll` with `device_code` until tokens returned

---

## App Access Control

**Every app (Cloud, Films, Steps, etc.) must be enabled per user.**

- Admin → Users → Edit user → **App Access** checkboxes
- Default: new users get **no** app access
- API returns `403 Forbidden` if user lacks access to that app's namespace
- MCP tools respect the same access control

---

## Public Sharing

Three mechanisms for sharing without login:

| Method | Use Case | Access Control |
|--------|----------|----------------|
| **Share Links** (`/s/{token}`) | Single file, movie, recipe, any resource | Optional password, expiry date, view/download limit |
| **WebDAV Public** | Mount folder read-only in OS file manager | Token in URL path: `/webdav/public/{token}/` |
| **SharedFile Viewer** (`/sharedFile`) | Preview images/PDF/text in browser | Token only, no password |

**Creating shares**:
- Web UI: Right-click any resource → "Share"
- API: `POST /public-api/v1/cloud/shares` (Cloud), similar for other apps
- MCP: `create_share` tool

---

## Multi-Language (i18n)

- **UI Language**: User profile → Language (persists in session + DB)
- **API Language**: `Accept-Language: ru,en;q=0.9` header
- **Translations**: `i18n/{lang}/*.json` chunks loaded via `i18n/load.php?lang={code}`
- Supported: `en`, `ru`, `de`, `es`, `fr`, `zh`, `ja`, `pt`, `it`, `pl`, `tr`, `ko`

---

## API Conventions

### Base URL
```
https://your-domain.com/public-api/v1/
```

### Versioning
- URL path: `/v1/`
- Breaking changes → `/v2/`
- Current version in response header: `API-Version: 1`

### Request Headers
| Header | Required | Description |
|--------|----------|-------------|
| `Authorization: Bearer <token>` | Yes (API) | Access token |
| `X-Device-Id: <uuid>` | Login only | Unique per client installation |
| `Accept-Language: <code>` | No | Response language |
| `Content-Type: application/json` | POST/PATCH | Request body format |
| `Idempotency-Key: <uuid>` | POST (mutating) | Safe retries |

### Response Format

**Success** (2xx):
```json
{
  "data": { ... },
  "meta": { "pagination": { ... } }
}
```

**Error** (4xx/5xx) — RFC 7807 Problem Details:
```json
{
  "type": "https://your-domain.com/errors/validation-failed",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The email field is required.",
  "instance": "/public-api/v1/auth/login",
  "errors": { "email": ["Required"] }
}
```

### Common Status Codes
| Code | Meaning |
|------|---------|
| 200 | OK |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request (malformed JSON, missing fields) |
| 401 | Unauthorized (token missing/expired/invalid) |
| 403 | Forbidden (no app access, not owner, admin only) |
| 404 | Not Found |
| 422 | Validation Failed |
| 429 | Rate Limited |
| 500 | Server Error |
| 503 | Maintenance Mode |

### Pagination
**Cursor-based** (preferred for large datasets):
```bash
GET /public-api/v1/cloud/files?cursor=eyJpZCI6MTAwfQ&limit=50
```
Response:
```json
{
  "data": [...],
  "meta": { "pagination": { "next_cursor": "eyJpZCI6MTUwfQ", "has_more": true } }
}
```

**Offset-based** (legacy):
```
GET /public-api/v1/cloud/files?page=2&per_page=50
```

### Rate Limiting
- **Authenticated**: 600 req/min per user
- **Auth endpoints**: 20 req/min per IP
- **Public shares**: 100 req/min per token
- Headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

---

## WebSocket (Real-Time)

**Endpoint**: `wss://your-domain.com/public-api/v1/smart_home/ws`

**Auth**: Pass token in query: `?token=<access_token>`

**Messages**:
```json
// Server → Client: device state change
{ "type": "device_update", "device_id": 42, "state": { "on": true, "brightness": 80 } }

// Server → Client: new device discovered
{ "type": "device_added", "device": { ... } }

// Client → Server: ping (keepalive)
{ "type": "ping" }
```

Reconnect with exponential backoff (1s, 2s, 4s, max 30s).

---

## File Storage Paths

| Type | Path (in container) | Volume |
|------|---------------------|--------|
| User uploads | `/var/www/html/storage/app/public/{user_id}/` | `appdashboard_data` |
| Thumbnails | `/var/www/html/storage/app/public/.thumbnails/` | `appdashboard_data` |
| OnlyOffice cache | `/var/www/html/storage/app/onlyoffice/` | `appdashboard_data` |
| Backups | `/var/www/html/storage/app/backups/` | `appdashboard_data` |
| MCP package | `/var/www/html/downloads/appdashboard-mcp.tgz` | `appdashboard_data` |

---

## Security Headers (Enforced by Nginx)

```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' wss:;
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
Strict-Transport-Security: max-age=31536000; includeSubDomains (HTTPS only)
```

---

## Maintenance Mode

Admin → Configuration → **Maintenance Mode** = ON

- Returns `503 Service Unavailable` for all non-admin routes
- Admin panel remains accessible
- Custom message configurable