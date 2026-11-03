# Missing API Endpoints Report

**Generated:** 2026-11-03  
**Scope:** Public API endpoints under `/public-api/` and `/api/` (cloud app internal)  
**Reference Documentation:** `docs/api-reference.md` (Endpoint Index section, lines 273-409)

---

## Executive Summary

The current `docs/api-reference.md` documents **~90 endpoints** across 10 categories. However, the actual codebase implements **200+ endpoints** across 15+ categories. 

**Major gaps:**
1. **Cloud app internal actions** (`apps/cloud/api/actions/router.php`) — **50+ actions** completely undocumented
2. **Kanboard API** (`public-api/routes/kanboard.php`) — **15 REST endpoints** completely undocumented  
3. **Shell/Admin API** (`public-api/routes/shell.php`) — **30+ admin actions** completely undocumented
4. **Recipes API** — **12+ actions** via GET/POST params, not RESTful — not documented
5. **Films API** — **11 actions** via POST `action` param, not RESTful — documented incorrectly as REST
6. **Spends API** — **10+ endpoints** (version, apk, profile, sync, OCR) not documented
7. **Steps API** — **15+ endpoints** not documented
8. **Docker API** — **15+ endpoints** partially documented
9. **Backup API** — **8 endpoints** partially documented
10. **Integrations API** — **4 service-specific endpoints** documented as generic CRUD

---

## Category-by-Category Analysis

### 1. Auth (`/public-api/v1/auth/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| POST `/login` | POST `/login` ✅ | OK |
| POST `/refresh` | POST `/refresh` ✅ | OK |
| GET `/me` | GET `/me` ✅ | OK |
| POST `/revoke` | POST `/revoke` ✅ | OK |
| POST `/client_auth/request` | POST `/client_auth/request` ✅ | OK |
| POST `/client_auth/poll` | POST `/client_auth/poll` ✅ | OK |
| POST `/2fa/enable` | **Not found in code** | ❌ Phantom |
| POST `/2fa/confirm` | **Not found in code** | ❌ Phantom |
| POST `/2fa/disable` | **Not found in code** | ❌ Phantom |
| — | GET `/` (returns endpoint list) | 📝 Missing |

**Note:** The 2FA endpoints are documented but don't exist in `public-api/routes/auth.php`.

---

### 2. OAuth (`/public-api/v1/oauth/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET `/authorize` | **Not implemented** | ❌ Missing |
| POST `/token` | POST `/token` ✅ | OK |
| GET `/userinfo` | **Not implemented** | ❌ Missing |
| POST `/revoke` | POST `/revoke` ✅ | OK |
| POST `/introspect` | **Not implemented** | ❌ Missing |
| GET `/jwks` | **Not implemented** | ❌ Missing |
| GET/POST `/clients` (admin) | **Not implemented** | ❌ Missing |
| — | POST `/register` (client registration) | 📝 Missing |
| — | GET `/client` (client lookup) | 📝 Missing |
| — | POST `/approve` (authorization code flow) | 📝 Missing |

**Note:** The documented endpoints don't match the actual implementation. The code implements a different OAuth flow (register/token/client/approve/revoke).

---

### 3. Apps (`/public-api/v1/apps/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET `/apps` | GET `/` or `/list` ✅ | OK (path differs) |
| GET `/apps/{key}/launch` | **Not implemented** | ❌ Missing |
| — | GET `/access_levels` (admin) | 📝 Missing |
| — | POST `/access_levels` (admin) | 📝 Missing |

---

### 4. Cloud (`/public-api/v1/cloud/`)

**Documented (REST-style):**
- GET/POST `/files`, GET/PATCH/DELETE `/files/{id}`, GET `/files/{id}/download`, GET `/files/{id}/preview`
- POST `/folders`
- GET/POST `/shares`, PATCH/DELETE `/shares/{token}`
- POST `/transcribe`, GET `/transcribe/{job_id}`
- POST `/translate`
- `* /webdav/*`

**Actual Implementation:**
The cloud route (`public-api/routes/cloud.php`) delegates to `apps/cloud/api.php` which uses **action-based API** (`action=` param). Additionally, there's a separate **action router** at `apps/cloud/api/actions/router.php` with **50+ actions**.

#### Cloud Actions (from `apps/cloud/api/actions/router.php`) — **ALL UNDOCUMENTED**

| Action | Method | Description |
|--------|--------|-------------|
| `get_encryption_notice_status` | GET | Check encryption consent status |
| `set_encryption_notice_status` | POST | Accept encryption notice |
| `get_state` | GET | Full user state (quota, settings, photo, CSRF) |
| `get_properties` | GET | File/folder metadata + preview |
| `move` | POST | Move file/folder (cross-root support) |
| `copy` | POST | Copy file/folder (cross-root support) |
| `move_to_trash` | POST | Move to trash |
| `restore_from_trash` | POST | Restore from trash |
| `empty_trash` | POST | Empty trash |
| `set_trash_retention` | POST | Set trash retention days |
| `share_file` / `share_folder` | POST | Create share link |
| `internal_share_metadata` | GET | Share preview metadata |
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
| `get_sync_log` | GET | Sync activity log |
| `set_sync_profile` | POST | **Deprecated** (returns 403) |
| `sync_manifest` | GET/POST | **Deprecated** (returns 410, use WebDAV) |
| `client_token_generate` | POST | Generate WebDAV client token |
| `client_token_revoke_all` | POST | Revoke all client tokens |
| `client_token_revoke` | POST | Revoke specific client token |
| `client_token_list` | GET | List active client tokens |
| `sync_manifest_client` | GET/POST | **Deprecated** (returns 410) |
| `rename` | POST | Rename file/folder |

#### Cloud Direct PHP Files (from `public-api/routes/cloud.php` allowedDirect)
- `compile.php`, `format_code.php`, `de_format_code.php`, `remove_comments.php`
- `transcription_api.php`, `translate_api_simple.php`, `tts_api.php`, `ai.php`

---

### 5. Films (`/public-api/v1/films/`)

**Documented (REST-style):**
- GET/POST `/movies`, GET/PATCH/DELETE `/movies/{id}`
- GET `/movies/{id}/stream`, GET/POST `/movies/{id}/subtitles`
- GET/POST `/collections`, GET `/genres`, GET `/search`
- GET/POST `/shares`

**Actual Implementation (Action-based via POST `action`):**

| Action | Method | Documented Equivalent |
|--------|--------|----------------------|
| `save_position` | POST | — |
| `save_video_settings` | POST | — |
| `get_video_settings` | POST | — |
| `add_movie` | POST | POST `/movies` |
| `get_movies` | POST | GET `/movies` |
| `get_all_movies` | POST | — (admin) |
| `toggle_visibility` | POST | PATCH `/movies/{id}` (partial) |
| `delete_movie` | POST | DELETE `/movies/{id}` |
| `add_comment` | POST | POST `/movies/{id}/comments` |
| `delete_comment` | POST | DELETE `/movies/{id}/comments` |
| `get_comments` | POST | GET `/movies/{id}/comments` |

**Missing from docs:** Video settings, position saving, get_all_movies (admin), comment management. The REST endpoints documented don't exist — everything goes through POST with `action` parameter.

---

### 6. Smart Home (`/public-api/v1/smart_home/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET `/devices` | GET `/devices` ✅ | OK |
| GET/POST `/devices/{id}/action` | POST `/control` | ⚠️ Different path |
| GET/POST `/rooms` | GET `/groups` | ⚠️ Different name |
| GET/POST `/scenes` | — | ❌ Not found |
| POST `/scenes/{id}/activate` | — | ❌ Not found |
| GET/POST `/automations` | — | ❌ Not found |
| GET `/mqtt/status` | — | ❌ Not found |
| POST `/mqtt/publish` | — | ❌ Not found |
| WS `/ws` | — | ❌ Not found |
| — | GET `/` or `/state` | 📝 Missing |
| — | GET `/stats?period=24` | 📝 Missing |
| — | POST/PUT/PATCH `/plans` | 📝 Missing |
| — | POST `/groups` | 📝 Missing |
| — | POST `/groups/{id}/members` | 📝 Missing |
| — | POST/PUT/PATCH `/devices` | 📝 Missing |
| — | DELETE `/devices/{id}` | 📝 Missing |

---

### 7. Steps (`/public-api/v1/steps/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET/POST `/data` | GET `/` (with `from`/`to`), POST `/` (ingest) | ⚠️ Partial |
| GET `/health-connect/status` | — | ❌ Not found |
| POST `/health-connect/sync` | — | ❌ Not found |
| GET/POST `/health-connect/conflicts` | GET/POST `/conflicts` | ⚠️ Path differs |
| GET/POST `/backups` | POST `/` (form: `get_backups_list`, `create_backup`) | ⚠️ Different mechanism |
| GET `/profile` | GET `/profile` ✅ | OK |
| PATCH `/profile` | POST `/profile` | ⚠️ Method differs |
| GET `/settings` | GET `/settings` ✅ | OK |
| PATCH `/settings` | POST `/settings` | ⚠️ Method differs |
| GET `/stats` | — | ❌ Not found |
| — | GET `/version` | 📝 Missing |
| — | GET `/apk` | 📝 Missing |
| — | GET `/sync` (with revision) | 📝 Missing |
| — | POST `/sync` | 📝 Missing |
| — | POST `/ingest` / `/webhook` / `/sync` | 📝 Missing |
| — | POST `/conflicts/{id}` (resolve) | 📝 Missing |
| — | POST `/` (form: `get_table_page`) | 📝 Missing |

---

### 8. Spends (`/public-api/v1/spends/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET/POST `/expenses` | — | ❌ Not found |
| GET/PATCH/DELETE `/expenses/{id}` | — | ❌ Not found |
| GET/POST `/categories` | — | ❌ Not found |
| GET/POST `/accounts` | — | ❌ Not found |
| GET/POST `/recurring` | — | ❌ Not found |
| GET `/reports/*` | — | ❌ Not found |
| GET/POST `/exchange-rates` | — | ❌ Not found |
| GET/POST `/export` | — | ❌ Not found |
| POST `/import` | — | ❌ Not found |
| — | GET `/version` | 📝 Missing |
| — | GET `/apk` | 📝 Missing |
| — | GET `/profile` | 📝 Missing |
| — | GET `/sync` (with revision) | 📝 Missing |
| — | POST `/sync` | 📝 Missing |
| — | GET/POST `/utils/expense_ocr_app` | 📝 Missing |
| — | GET/POST `/ocr/{start|status|resolve|set_dates|cancel|finalize|stores|active_job}` | 📝 Missing |

**Note:** The documented endpoints appear to be aspirational — the actual spends API is a mobile sync protocol with OCR support, not a REST CRUD API.

---

### 9. Recipes (`/public-api/v1/recipes/`)

**Documented (REST-style):**
- GET/POST `/recipes`, GET/PATCH/DELETE `/recipes/{id}`
- POST `/recipes/{id}/scale`, GET `/recipes/{id}/shopping-list`
- POST `/import`, GET/POST `/tags`, GET/POST `/meal-plan`, GET/POST `/shares`

**Actual Implementation (Action-based via GET/POST params):**

| GET Endpoints | POST Actions |
|---------------|--------------|
| `get_products` | `edit_product_name` |
| `get_recipes` | `add_product` |
| `get_recipe_full` | `add_recipe` / `edit_recipe` |
| | `add_recipe_pretranslated` / `edit_recipe_pretranslated` |
| | `delete_recipe` |
| | `delete_product` |
| | `save_state` |
| | `save_config` |

**Missing from docs:** All product management, recipe import/export, pretranslated variants, state/config management. The REST endpoints documented don't exist.

---

### 10. Kanboard (`/public-api/v1/kanboard/`) — **COMPLETELY UNDOCUMENTED**

| Method | Path | Description |
|--------|------|-------------|
| GET | `/me` | Current user info |
| GET | `/projects` | List user's projects |
| GET | `/projects/{id}` | Get project details |
| PATCH | `/projects/{id}` | Update project |
| GET | `/projects/{id}/board` | Full board with swimlanes/columns/tasks |
| GET | `/projects/{id}/columns` | List columns |
| GET | `/projects/{id}/members` | List project members |
| GET | `/projects/{id}/tasks` | List tasks (filter by status) |
| POST | `/projects/{id}/tasks` | Create task |
| GET | `/projects/{id}/stats` | Project statistics |
| GET | `/my-tasks` | User's assigned tasks across projects |
| GET | `/tasks/{id}` | Get task with comments |
| PATCH | `/tasks/{id}` | Update task (incl. move, status) |
| DELETE | `/tasks/{id}` | Delete task |
| GET | `/tasks/{id}/comments` | Get task comments |
| POST | `/tasks/{id}/comments` | Add comment |

---

### 11. Admin (`/public-api/v1/admin/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET/POST `/users` | GET `/users` ✅ | OK |
| GET/PATCH/DELETE `/users/{id}` | GET/PUT/DELETE `/users/{email}` | ⚠️ Uses email not ID |
| POST `/users/{id}/impersonate` | **Not implemented** | ❌ Missing |
| GET `/audit-logs` | **Not implemented** | ❌ Missing |

---

### 12. Config (`/public-api/v1/config/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET/PATCH `/config/{section}` | GET `/config` (list keys), GET `/config/{key}`, PUT `/config/{key}` | ⚠️ Different paths/methods |

---

### 13. Docker (`/public-api/v1/docker/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET/POST `/containers` | GET `/containers` ✅ | OK |
| POST `/containers/{name}/{action}` | POST `/containers/{id}/{start,stop,restart,pause,unpause,kill,remove,pull,update,recreate}` | ⚠️ More actions |
| — | GET `/containers/{id}/stats` | 📝 Missing |
| — | GET `/containers/{id}/logs` | 📝 Missing |
| — | GET `/containers/{id}/inspect` | 📝 Missing |
| — | POST `/images/pull` | 📝 Missing |
| — | DELETE `/images/{id}` | 📝 Missing |
| — | GET `/images/{id}/inspect` | 📝 Missing |
| — | DELETE `/volumes/{id}` | 📝 Missing |
| — | GET `/stacks` | 📝 Missing |
| — | GET/POST `/stacks/{id}/compose` | 📝 Missing (returns 404) |

---

### 14. Shell (`/public-api/v1/shell/`) — **COMPLETELY UNDOCUMENTED**

All endpoints are `POST /shell` with `action` parameter.

#### User Actions (no admin required):
| Action | Description |
|--------|-------------|
| `change_password` | Change own password |
| `change_photo` | Upload avatar |
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

#### Admin Actions (role=2 required):
| Action | Description |
|--------|-------------|
| `get_app_access_levels` | List app access levels |
| `set_app_access_level` | Set app min role |
| `fm_list_dir` | File manager: list directory |
| `fm_get_file` | File manager: get file content |
| `fm_save_file` | File manager: save file |
| `fm_mkdir` | File manager: create directory |
| `fm_mkfile` | File manager: create file |
| `fm_delete` | File manager: delete file/dir |
| `fm_rename` | File manager: rename |
| `fm_dir_size` | File manager: directory size |
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
| `list_alerts` | List fired alerts |
| `get_metrics_hourly` | Get hourly metrics |

---

### 15. Backup (`/public-api/v1/backup/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET/POST `/backup` | GET `/status`, POST `/run` | ⚠️ Different paths |
| POST `/backup/{id}/restore` | **Not implemented** | ❌ Missing |
| — | GET `/logs` (list), GET `/logs/{id}` (detail) | 📝 Missing |
| — | GET `/disk?path=...` | 📝 Missing |
| — | GET `/host_fs?path=...` | 📝 Missing |
| — | POST `/host_fs/mkdir` | 📝 Missing |
| — | POST `/path_rw_test` | 📝 Missing |

---

### 16. Performance (`/public-api/v1/performance/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET `/metrics` | GET `/monitor` | ⚠️ Different path |
| GET `/slow-queries` | **Not implemented** | ❌ Missing |
| — | GET `/live` (or `?endpoint=`) | 📝 Missing |

---

### 17. Integrations (`/public-api/v1/integrations/`)

| Documented | Actual Implementation | Status |
|------------|----------------------|--------|
| GET/POST `/integrations` | **Not implemented** | ❌ Missing |
| GET/PATCH/DELETE `/integrations/{id}` | **Not implemented** | ❌ Missing |
| GET `/integrations/{id}/launch` | **Not implemented** | ❌ Missing |
| — | GET `/integrations/immich` | 📝 Missing |
| — | GET `/integrations/gitea` | 📝 Missing |
| — | GET `/integrations/kanboard` | 📝 Missing |
| — | GET `/integrations/sentry` | 📝 Missing |

**Note:** The actual endpoints are service-specific credential providers, not generic CRUD.

---

## Summary Statistics

| Category | Documented | Actually Implemented | Missing from Docs | Phantom (Doc'd but not real) |
|----------|------------|---------------------|-------------------|------------------------------|
| Auth | 9 | 7 + 1 helper | 1 | 3 (2FA) |
| OAuth | 7 | 5 | 4 | 3 |
| Apps | 2 | 3 | 2 | 1 |
| Cloud | 14 | 50+ actions + 8 direct | 50+ | 0 |
| Films | 10 | 11 actions | 10 | 10 (REST don't exist) |
| Smart Home | 10 | 10 | 6 | 4 |
| Steps | 10 | 15+ | 12 | 3 |
| Spends | 9 | 10+ | 10+ | 9 (REST don't exist) |
| Recipes | 9 | 12 actions | 12 | 9 (REST don't exist) |
| Kanboard | 0 | 15 | 15 | 0 |
| Admin | 4 | 3 | 3 | 2 |
| Config | 1 | 3 | 3 | 0 |
| Docker | 2 | 13+ | 11+ | 0 |
| Shell | 1 | 30+ | 30+ | 0 |
| Backup | 2 | 8 | 6 | 0 |
| Performance | 2 | 2 | 1 | 1 |
| Integrations | 3 | 4 | 4 | 3 |
| **TOTAL** | **~95** | **~200+** | **~180+** | **~35** |

---

## Recommendations

### Priority 1: Document the Major Missing APIs
1. **Kanboard API** — Full REST API, 15 endpoints, production-ready
2. **Cloud Actions API** — 50+ actions, core file management functionality
3. **Shell/Admin API** — 30+ actions, file manager, monitoring, user management
4. **Backup API** — 8 endpoints, restic integration

### Priority 2: Fix Incorrect Documentation
1. **Films API** — Document as action-based, not REST
2. **Recipes API** — Document as action-based, not REST  
3. **Spends API** — Document as mobile sync + OCR, not REST CRUD
4. **OAuth API** — Document actual endpoints (register/token/client/approve/revoke)
5. **Smart Home** — Align documented paths with implementation

### Priority 3: Add Missing Endpoints to Docs
1. **Steps API** — Version, APK, sync, conflict resolution, table page
2. **Docker API** — Stats, logs, inspect, images, volumes, stacks
3. **Integrations API** — Service-specific credential endpoints
4. **Admin API** — Fix paths (email vs ID), add missing audit-logs note

### Structural Suggestion
The documentation should have **two sections**:
1. **Public REST API** (`/public-api/v1/`) — for external developers
2. **Internal Action APIs** (`/api/` and action-based) — for first-party clients (web UI, mobile apps, MCP)

The current docs mix both and incorrectly present action-based APIs as REST.

---

## Files to Update

Primary: `docs/api-reference.md` (Endpoint Index section, lines 273-409)

Related implementation files for reference:
- `public-api/routes/*.php` (17 route files)
- `apps/cloud/api/actions/router.php` (50+ actions)
- `apps/films/controllers/ApiController.php` (11 actions)
- `apps/recipes/controllers/get_handlers.php` + `post_handlers.php` (12 actions)
- `public-api/routes/shell.php` (30+ admin actions)
- `public-api/routes/kanboard.php` (15 REST endpoints)