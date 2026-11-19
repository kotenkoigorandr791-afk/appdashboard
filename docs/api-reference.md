# API Reference

Complete technical reference for the AppDashboard Public API v1.

---

## Base URL

```
https://your-domain.com/public-api/v1/
```

All endpoints require **HTTPS** in production.

---

## Authentication

### Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Authorization: Bearer <token>` | Yes (most endpoints) | OAuth/JWT access token |
| `X-Device-Id: <uuid>` | Login only | Unique client identifier |

### Token Types

| Token | Lifetime | Use |
|-------|----------|-----|
| Access Token | 1 hour | API calls |
| Refresh Token | 30 days | Obtain new access token |
| Device Code | 30 min | Desktop client auth flow |

---

## Error Format (RFC 7807)

All errors return `application/problem+json`:

```json
{
  "type": "https://your-domain.com/errors/validation-failed",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The email field is required.",
  "instance": "/public-api/v1/auth/login",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 12 characters."]
  }
}
```

### Common Error Types

| Type URI | Title | Status | Meaning |
|----------|-------|--------|---------|
| `/errors/unauthorized` | Unauthorized | 401 | Missing/invalid/expired token |
| `/errors/forbidden` | Forbidden | 403 | Insufficient permissions (role, app access) |
| `/errors/not-found` | Not Found | 404 | Resource doesn't exist |
| `/errors/validation-failed` | Validation Failed | 422 | Request body invalid |
| `/errors/rate-limited` | Rate Limited | 429 | Too many requests |
| `/errors/server-error` | Server Error | 500 | Unexpected error |
| `/errors/maintenance` | Maintenance Mode | 503 | System in maintenance |

---

## Pagination

### Cursor-Based (Preferred)

**Request**:
```
GET /public-api/v1/cloud/files?cursor=eyJpZCI6MTAwfQ&limit=50
```

**Response**:
```json
{
  "data": [...],
  "meta": {
    "pagination": {
      "next_cursor": "eyJpZCI6MTUwfQ",
      "has_more": true
    }
  }
}
```

- `cursor`: Opaque string from previous response
- `limit`: 1–200 (default: 50)
- No `previous_cursor` (forward only)

### Offset-Based (Legacy)

```
GET /public-api/v1/cloud/files?page=2&per_page=50
```

**Response**:
```json
{
  "data": [...],
  "meta": {
    "pagination": {
      "current_page": 2,
      "per_page": 50,
      "total": 1234,
      "last_page": 25
    }
  }
}
```

---

## Rate Limiting

| Tier | Limit | Window |
|------|-------|--------|
| Authenticated API | 600 | minute |
| Auth endpoints (`/auth/*`) | 20 | minute per IP |
| Public shares | 100 | minute per token |
| MCP tools | 100 | minute per token |

### Headers

| Header | Description |
|--------|-------------|
| `X-RateLimit-Limit` | Max requests in window |
| `X-RateLimit-Remaining` | Requests left in window |
| `X-RateLimit-Reset` | Unix timestamp when window resets |
| `Retry-After` | Seconds to wait (on 429) |

---

## Versioning

- **Current**: `v1` (in URL path)
- **Header**: `API-Version: 1` in responses
- **Deprecation**: 6 months notice via `Sunset` header + changelog
- **Breaking changes** → new version (`/v2/`)

---

## Common Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `cursor` | string | Pagination cursor |
| `limit` | int | Page size (1–200) |
| `page` | int | Page number (offset) |
| `per_page` | int | Items per page (offset) |
| `sort` | string | Field to sort by |
| `order` | string | `asc` or `desc` |
| `search` | string | Full-text search |
| `date_from` | date (YYYY-MM-DD) | Filter start date |
| `date_to` | date (YYYY-MM-DD) | Filter end date |

---

## Date/Time Format

- **Input**: ISO 8601 (`YYYY-MM-DD`, `YYYY-MM-DDTHH:MM:SSZ`)
- **Output**: ISO 8601 UTC (`2026-01-15T14:30:00Z`)
- **Timezone**: User's timezone (from profile) for display; UTC for storage

---

## File Uploads

### Multipart Form Data

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" \
  -F "file=@/path/to/image.jpg" \
  -F "parent_id=root" \
  https://your-domain.com/public-api/v1/cloud/files
```

### Chunked Upload (Large Files > 100 MB)

1. `POST /cloud/files/initiate` → `{ upload_id, chunk_size, chunk_count }`
2. `PUT /cloud/files/{upload_id}/chunk/{index}` (repeat for each chunk)
3. `POST /cloud/files/{upload_id}/complete` → final file object

---

## WebSocket API

### Smart Home Real-Time

```
wss://your-domain.com/public-api/v1/smart_home/ws?token=<access_token>
```

**Message Format**:
```json
// Server → Client
{ "type": "device_update", "device_id": 42, "capability": "light", "state": { "on": true } }
{ "type": "device_added", "device": { ... } }
{ "type": "device_removed", "device_id": 42 }
{ "type": "scene_activated", "scene_id": 5 }

// Client → Server
{ "type": "ping" }
{ "type": "subscribe", "device_ids": [1,2,3] }
```

**Reconnection**: Exponential backoff (1s, 2s, 4s, 8s, max 30s).

---

## Idempotency

For mutating operations (POST, PATCH, DELETE), include:

```
Idempotency-Key: <uuid-v4>
```

- Safe to retry with same key within 24 hours
- Returns original response on duplicate
- Stored per-user per-key

---

## CORS

- **Allowed Origins**: Configured in Admin → Config → Security
- **Credentials**: `Access-Control-Allow-Credentials: true`
- **Methods**: `GET, POST, PATCH, DELETE, OPTIONS`
- **Headers**: `Authorization, Content-Type, X-Device-Id, Idempotency-Key, Accept-Language`

---

## OpenAPI / Swagger

**Spec URL**: `https://your-domain.com/public-api/v1/openapi.json`

**Swagger UI**: `https://your-domain.com/public-api/v1/docs`

Generated from PHP attributes (`#[OA\Get]`, etc.) in controllers.

---

## SDKs / Client Libraries

| Language | Package | Install |
|----------|---------|---------|
| **JavaScript/TypeScript** | `@appdashboard/api-client` | `npm i @appdashboard/api-client` |
| **Python** | `appdashboard-api` | `pip install appdashboard-api` |
| **Go** | `github.com/appdashboard/go-client` | `go get github.com/appdashboard/go-client` |
| **PHP** | `appdashboard/php-sdk` | `composer require appdashboard/php-sdk` |

**Example (JS)**:
```typescript
import { AppDashboardClient } from '@appdashboard/api-client';

const client = new AppDashboardClient({
  baseUrl: 'https://your-domain.com',
  accessToken: 'eyJ...',
  deviceId: 'uuid-v4'
});

const files = await client.cloud.listFiles({ parentId: 'root' });
const movie = await client.films.getMovie(123);
await client.smartHome.controlDevice(42, 'light', 'turn_on', { brightness: 80 });
```

---

## Endpoint Index (Quick Reference)

### Auth
| Method | Path | Description |
|--------|------|-------------|
| GET | `/auth` | List available auth endpoints |
| POST | `/auth/login` | Username/password login, returns access token |
| POST | `/auth/refresh` | Refresh access token using current token |
| GET | `/auth/me` | Get current user profile |
| POST | `/auth/revoke` | Revoke current access token |
| POST | `/auth/client_auth/request` | Initiate browser-based device auth flow |
| POST | `/auth/client_auth/poll` | Poll for browser auth completion |

### OAuth
| Method | Path | Description |
|--------|------|-------------|
| GET | `/oauth` | List available OAuth endpoints |
| POST | `/oauth/register` | Register new OAuth client |
| POST | `/oauth/token` | Token endpoint (authorization_code, refresh_token grants) |
| GET | `/oauth/client` | Lookup client by client_id |
| POST | `/oauth/approve` | Approve authorization code (user consent) |
| POST | `/oauth/revoke` | Revoke refresh token |

### Apps
| Method | Path | Description |
|--------|------|-------------|
| GET | `/apps` or `/apps/list` | List available apps for current user |
| GET | `/apps/access_levels` | Get app access levels (admin) |
| POST | `/apps/access_levels` | Set app minimum role (admin) |

### Cloud (REST — File Operations)
| Method | Path | Description |
|--------|------|-------------|
| GET | `/cloud/files` | List files (supports `cursor`, `limit`, `parent_id`, `root`) |
| POST | `/cloud/files` | Upload file (multipart) or create folder |
| GET | `/cloud/files/{id}` | Get file metadata |
| PATCH | `/cloud/files/{id}` | Rename, move, or update file metadata |
| DELETE | `/cloud/files/{id}` | Delete file/folder (moves to trash) |
| GET | `/cloud/files/{id}/download` | Download file |
| GET | `/cloud/files/{id}/preview` | Get file preview (text, image, PDF, video) |
| POST | `/cloud/folders` | Create folder |
| GET/POST | `/cloud/shares` | List or create share links |
| PATCH/DELETE | `/cloud/shares/{token}` | Update or delete share |
| POST | `/cloud/transcribe` | Start transcription job |
| GET | `/cloud/transcribe/{job_id}` | Get transcription status/result |
| POST | `/cloud/translate` | Translate text |
| * | `/cloud/webdav/*` | WebDAV endpoint (all methods) |

### Cloud Actions (Action-based API — `POST /cloud?action=...`)
All Cloud Actions use `POST /public-api/v1/cloud` with `action` parameter (form or JSON). Most require authentication.

| Action | Method | Description |
|--------|--------|-------------|
| `get_encryption_notice_status` | GET | Check if user accepted encryption notice |
| `set_encryption_notice_status` | POST | Accept encryption notice |
| `get_state` | GET | Full user state (quota, settings, photo, CSRF token) |
| `get_properties` | GET | File/folder metadata + preview |
| `move` | POST | Move file/folder (cross-root support) |
| `copy` | POST | Copy file/folder (cross-root support) |
| `move_to_trash` | POST | Move to trash |
| `restore_from_trash` | POST | Restore from trash |
| `empty_trash` | POST | Empty trash |
| `set_trash_retention` | POST | Set trash retention days |
| `share_file` / `share_folder` | POST | Create share link with options |
| `internal_share_metadata` | GET | Get share preview metadata |
| `import_internal_share` | POST | Import shared file to user storage |
| `set_quota` | POST | Set user quota (admin only) |
| `request_storage` | POST | Request more storage (user) |
| `get_storage_request` | GET | Get user's storage request status |
| `admin_storage_overview` | GET | Admin: paginated user list with quota/usage |
| `admin_storage_analytics` | GET | Admin: storage analytics (top users, totals) |
| `admin_set_user_quota` | POST | Admin: set specific user's quota |
| `admin_get_storage_request` | GET | Admin: get user's pending request |
| `admin_resolve_storage_request` | POST | Admin: approve/reject storage request |
| `set_session_limit` | POST | Set session storage limit |
| `set_hide_lang_switcher` | POST | Hide language switcher setting |
| `get_sync_log` | GET | Sync activity log (paginated) |
| `set_sync_profile` | POST | **Deprecated** — returns 403 |
| `sync_manifest` | GET/POST | **Deprecated** — returns 410, use WebDAV |
| `client_token_generate` | POST | Generate WebDAV client token |
| `client_token_revoke_all` | POST | Revoke all client tokens |
| `client_token_revoke` | POST | Revoke specific client token |
| `client_token_list` | GET | List active client tokens |
| `sync_manifest_client` | GET/POST | **Deprecated** — returns 410 |
| `rename` | POST | Rename file/folder |

### Cloud Direct Endpoints (Legacy PHP Scripts)
These are served directly via `/cloud/{script}.php`:
| Script | Description |
|--------|-------------|
| `compile.php` | Code compilation |
| `format_code.php` / `de_format_code.php` / `remove_comments.php` | Code formatting utilities |
| `transcription_api.php` | Transcription processing |
| `translate_api_simple.php` | Simple translation |
| `tts_api.php` | Text-to-speech |
| `ai.php` | AI processing |

### Films (Action-based API — `POST /films?action=...`)
The Films API uses action-based POST requests, not REST. All endpoints require `action` parameter.

| Action | Method | Description |
|--------|--------|-------------|
| `save_position` | POST | Save video playback position |
| `save_video_settings` | POST | Save seek/volume step settings |
| `get_video_settings` | POST | Get video playback settings |
| `add_movie` | POST | Add movie (requires level 1+) |
| `get_movies` | POST | List visible movies |
| `get_all_movies` | POST | List all movies with visibility (admin, level 2+) |
| `toggle_visibility` | POST | Toggle movie visibility (level 2+) |
| `delete_movie` | POST | Delete movie (level 2+) |
| `add_comment` | POST | Add comment/rating to movie |
| `delete_comment` | POST | Delete comment (level 2+) |
| `get_comments` | POST | Get comments for movie |

### Smart Home
| Method | Path | Description |
|--------|------|-------------|
| GET | `/smart_home` or `/smart_home/state` | Full state (devices, groups, floor plan, settings) |
| GET | `/smart_home/devices` | List devices |
| GET | `/smart_home/groups` | List groups |
| GET | `/smart_home/plans` | Get floor plan |
| GET | `/smart_home/stats` | Get monitoring stats (`?period=24`) |
| POST | `/smart_home/control` | Control device |
| POST | `/smart_home/plans` | Save floor plan |
| POST | `/smart_home/groups` | Create group |
| POST | `/smart_home/groups/{group_id}/members` | Add group member |
| POST/PUT/PATCH | `/smart_home/devices` | Save device |
| DELETE | `/smart_home/devices/{device_id}` | Delete device |
| WS | `/smart_home/ws` | WebSocket real-time updates |

### Steps
| Method | Path | Description |
|--------|------|-------------|
| GET | `/steps` | List steps data (`from`, `to` required) |
| GET | `/steps?metrics=1` | Get metrics with steps |
| POST | `/steps` | Ingest steps data (Health Connect payload) |
| POST | `/steps/ingest` | Alias for ingest |
| POST | `/steps/webhook` | Webhook endpoint |
| POST | `/steps/sync` | Sync endpoint |
| GET | `/steps/conflicts` | List pending conflicts |
| POST | `/steps/conflicts/{id}` | Resolve conflict (`action`: skip/replace/add) |
| GET | `/steps/backups` | List backups (form: `get_backups_list`) |
| POST | `/steps/backups` | Create backup (form: `create_backup`) |
| POST | `/steps` (form) | Table page (form: `get_table_page`) |
| GET | `/steps/profile` | Get user profile |
| POST | `/steps/profile` | Update profile |
| GET | `/steps/settings` | Get user settings |
| POST | `/steps/settings` | Update settings |
| GET | `/steps/version` | Get app version info |
| GET | `/steps/apk` | Download APK |

### Spends (Mobile Sync + OCR API)
The Spends API is a mobile synchronization protocol with OCR support, not a REST CRUD API.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/spends/version` | Get app version info |
| GET | `/spends/apk` | Download APK |
| GET | `/spends/profile` | Get user profile |
| GET | `/spends/sync` | Pull sync data (`?revision=...`) |
| POST | `/spends/sync` | Push sync data |
| GET | `/spends/utils/expense_ocr_app` | OCR utility endpoint |
| POST | `/spends/ocr/start` | Start OCR job |
| GET | `/spends/ocr/status` | Get OCR job status (`?job_id=...`) |
| POST | `/spends/ocr/resolve` | Resolve OCR job |
| POST | `/spends/ocr/set_dates` | Set dates for OCR job |
| POST | `/spends/ocr/cancel` | Cancel OCR job |
| POST | `/spends/ocr/finalize` | Finalize OCR job |
| GET | `/spends/ocr/stores` | Get OCR stores |
| GET | `/spends/ocr/active_job` | Get active OCR job |

### Recipes (Action-based API — GET/POST with `action` parameter)
The Recipes API uses action-based requests via query parameters or form fields.

**GET Actions:**
| Action | Method | Description |
|--------|--------|-------------|
| `get_products` | GET | List all products |
| `get_recipes` | GET | List recipes (paginated, `?recipe_page=...`) |
| `get_recipe_full` | GET | Get full recipe with steps (`?recipe_id=...`) |

**POST Actions:**
| Action | Method | Description |
|--------|--------|-------------|
| `edit_product_name` | POST | Create or update product (with translation) |
| `add_product` | POST | Add new product with image |
| `add_recipe` / `edit_recipe` | POST | Create or update recipe |
| `add_recipe_pretranslated` / `edit_recipe_pretranslated` | POST | Create/update with pre-translated content |
| `delete_recipe` | POST | Delete recipe |
| `delete_product` | POST | Delete product |
| `save_state` | POST | Save UI state |
| `save_config` | POST | Save app configuration |

### Kanboard (Full REST API)
Kanboard integration provides a complete REST API proxy.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/kanboard/me` | Current Kanboard user |
| GET | `/kanboard/projects` | List user's projects |
| GET | `/kanboard/projects/{id}` | Get project details |
| PATCH | `/kanboard/projects/{id}` | Update project |
| GET | `/kanboard/projects/{id}/board` | Full board (swimlanes, columns, tasks) |
| GET | `/kanboard/projects/{id}/columns` | List columns |
| GET | `/kanboard/projects/{id}/members` | List project members |
| GET | `/kanboard/projects/{id}/tasks` | List tasks (`?status=open|closed`) |
| POST | `/kanboard/projects/{id}/tasks` | Create task |
| GET | `/kanboard/projects/{id}/stats` | Project statistics |
| GET | `/kanboard/my-tasks` | User's assigned tasks |
| GET | `/kanboard/tasks/{id}` | Get task with comments |
| PATCH | `/kanboard/tasks/{id}` | Update task (move, status, fields) |
| DELETE | `/kanboard/tasks/{id}` | Delete task |
| GET | `/kanboard/tasks/{id}/comments` | Get task comments |
| POST | `/kanboard/tasks/{id}/comments` | Add comment |

### Integrations (Service Credentials)
Service-specific credential endpoints for MCP integrations.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/integrations/immich` | Get Immich API key (`?refresh=1` to force renew) |
| GET | `/integrations/gitea` | Get Gitea access token (`?refresh=1`) |
| GET | `/integrations/kanboard` | Get Kanboard API token (`?refresh=1`) |
| GET | `/integrations/sentry` | Get Sentry API token (`?refresh=1`) |

### Admin
| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/users` | List all users (role 2, IP allowed) |
| GET | `/admin/users/{email}` | Get user by email |
| PUT | `/admin/users/{email}` | Update user (role, name, verified, capabilities) |
| DELETE | `/admin/users/{email}` | Delete user (not root@root) |

### Config
| Method | Path | Description |
|--------|------|-------------|
| GET | `/config` | List all config keys (role 2) |
| GET | `/config/{key}` | Get config value (role 2) |
| PUT | `/config/{key}` | Set config value (role 2) |

### Docker
| Method | Path | Description |
|--------|------|-------------|
| GET | `/docker/containers` | List all containers (role 1) |
| GET | `/docker/containers/{id}/stats` | Container stats (role 1) |
| GET | `/docker/containers/{id}/logs` | Container logs (role 1, `?tail=200`) |
| GET | `/docker/containers/{id}/inspect` | Container inspect (role 2) |
| POST | `/docker/containers/{id}/{action}` | Container action: start, stop, restart, pause, unpause, kill (role 2) |
| POST | `/docker/containers/{id}/remove` | Remove container (role 2) |
| POST | `/docker/containers/{id}/pull` | Pull container image (role 2) |
| POST | `/docker/containers/{id}/update` | Pull image & recreate container (role 2) |
| POST | `/docker/containers/{id}/recreate` | Recreate container (role 2) |
| POST | `/docker/images/pull` | Pull image by name (role 2) |
| DELETE | `/docker/images/{id}` | Remove image (role 2) |
| GET | `/docker/images/{id}/inspect` | Inspect image (role 2) |
| DELETE | `/docker/volumes/{id}` | Remove volume (role 2) |
| GET | `/docker/stacks` | List compose stacks (role 1) |
| GET | `/docker/stacks/{id}/compose` | **Unavailable** (returns 404) |
| POST | `/docker/stacks/{id}` | **Unavailable** (returns 404) |

### Shell (Admin Actions — `POST /shell` with `action` parameter)
All Shell endpoints require role 2 (admin). Request body contains `action` and parameters.

**User Actions (no admin role required):**
| Action | Description |
|--------|-------------|
| `change_password` | Change own password |
| `change_photo` | Upload avatar (base64) |
| `reset_photo` | Reset to default avatar |
| `settings` | Update language/theme/color |
| `get_shared_files` | List user's shared files |
| `delete_shared_file` | Delete own share |
| `update_shared_file_expiration` | Update share expiration |
| `send_support_message` | Send to support chat |
| `send_messenger_message` | Send peer message |
| `get_support_messages` | Get support chat history |
| `get_messenger_messages` | Get peer chat history |
| `list_messenger_chats` | List peer conversations |

**Admin Actions (role 2 required):**
| Action | Description |
|--------|-------------|
| `get_app_access_levels` | List app access levels |
| `set_app_access_level` | Set app minimum role |
| `fm_list_dir` | File manager: list directory |
| `fm_get_file` | File manager: get file content (base64 for binary) |
| `fm_save_file` | File manager: save file content |
| `fm_mkdir` | File manager: create directory |
| `fm_mkfile` | File manager: create file |
| `fm_delete` | File manager: delete file/directory |
| `fm_rename` | File manager: rename file/directory |
| `fm_dir_size` | File manager: get directory size |
| `list_json_files` | List root JSON files |
| `get_json_file` | Get JSON file content |
| `save_json_file` | Save JSON file |
| `list_quick_commands` | List quick commands |
| `create_quick_command` | Create quick command |
| `update_quick_command` | Update quick command |
| `delete_quick_command` | Delete quick command |
| `list_alert_rules` | List monitor alert rules |
| `create_alert_rule` | Create alert rule |
| `delete_alert_rule` | Delete alert rule |
| `list_alerts` | List fired alerts (last 200) |
| `get_metrics_hourly` | Get hourly metrics (`?since=...`, `?metric=...`) |

### Backup (Restic Integration)
| Method | Path | Description |
|--------|------|-------------|
| GET | `/backup/status` | Repository status, snapshots, stats, jobs (role 1) |
| POST | `/backup/run` | Start backup job (role 2) |
| GET | `/backup/logs` | List recent jobs (role 2) |
| GET | `/backup/logs/{job_id}` | Get job details + tail logs (role 2) |
| GET | `/backup/disk` | Disk space for path (`?path=...`) (role 1) |
| GET | `/backup/host_fs` | Browse host filesystem (`?path=...`, `?onlyFolders=true`, `?showHidden=false`) (role 2) |
| POST | `/backup/host_fs/mkdir` | Create host directory (role 2) |
| POST | `/backup/path_rw_test` | Verify read/write access to path (role 2) |

### Performance
| Method | Path | Description |
|--------|------|-------------|
| GET | `/performance/monitor` | System metrics (CPU, memory, swap, drives, disk I/O, network, uptime) (role 2) |
| GET | `/performance/live` or `?endpoint=...` | Live monitoring server |

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| `v1` | 2026-01-01 | Initial release |
| `v1.1` | 2026-11-03 | Complete rewrite of Endpoint Index: added 100+ missing endpoints (Kanboard, Cloud Actions, Shell, Backup, Docker, etc.), fixed incorrect REST vs action-based APIs (Films, Recipes, Spends, OAuth, Smart Home), removed phantom endpoints (2FA, OAuth authorize/userinfo/introspect/jwks, Smart Home scenes/automations/MQTT, Admin impersonate/audit-logs, Integrations generic CRUD), added descriptions column |