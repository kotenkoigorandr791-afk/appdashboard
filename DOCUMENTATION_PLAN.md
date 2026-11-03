# Documentation Rewrite Plan

## Objective
Create user-facing documentation covering **ALL** functionality: service functions, API endpoints with descriptions, and user workflows. Zero implementation details.

---

## Documentation Structure

### 1. Getting Started
- Installation (Docker compose)
- First login / Registration
- Default credentials (root@root / 12345T)
- Dashboard overview

### 2. Core Concepts
- Authentication methods (Session, Bearer, OAuth, Device ID)
- App access control (install/enable per user)
- Public sharing (share links, WebDAV, MCP)
- Multi-language support

### 3. Services Reference (One section per service)

#### 3.1 Cloud — File Storage & Collaboration
**User Functions:**
- File manager (upload, download, move, rename, delete, folders)
- WebDAV sync (mount on desktop/mobile)
- OnlyOffice collaborative editing (docx, xlsx, pptx)
- Audio/video transcription (speech-to-text)
- Text translation (multi-language)
- Public file sharing (links with optional password/expiry)
- Shared file viewer (no login required)

**API Endpoints (`/public-api/v1/cloud`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/files` | List files/folders (supports pagination, sorting) |
| POST | `/files` | Upload file(s) |
| GET | `/files/{id}` | Get file metadata |
| PATCH | `/files/{id}` | Rename/move file |
| DELETE | `/files/{id}` | Delete file/folder |
| GET | `/files/{id}/download` | Download file |
| GET | `/files/{id}/preview` | Preview file (images, PDF, text) |
| POST | `/folders` | Create folder |
| GET | `/shares` | List public shares |
| POST | `/shares` | Create public share link |
| PATCH | `/shares/{token}` | Update share (password, expiry, permissions) |
| DELETE | `/shares/{token}` | Revoke share |
| POST | `/transcribe` | Start transcription job |
| GET | `/transcribe/{jobId}` | Get transcription status/result |
| POST | `/translate` | Translate text |
| GET | `/webdav/*` | WebDAV protocol endpoint (RFC 4918) |

**Web Routes:**
- `/apps/cloud/` — File manager UI
- `/webdav/*` — WebDAV server
- `/sharedFile` — Public file viewer
- `/sharepoint/s/{token}` — Public share access

---

#### 3.2 Films — Media Library
**User Functions:**
- Movie library management (add, edit, delete, metadata)
- Video streaming (adaptive bitrate, subtitles)
- Movie metadata auto-fetch (TMDB, etc.)
- Multi-language support (UI + content)
- Public movie sharing
- Watch progress tracking

**API Endpoints (`/public-api/v1/films`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/movies` | List movies (filter, search, sort, pagination) |
| POST | `/movies` | Add movie (file upload or external link) |
| GET | `/movies/{id}` | Get movie details + metadata |
| PATCH | `/movies/{id}` | Update movie metadata |
| DELETE | `/movies/{id}` | Delete movie |
| GET | `/movies/{id}/stream` | Stream video (HLS/DASH, range requests) |
| GET | `/movies/{id}/subtitles` | List/get subtitles |
| POST | `/movies/{id}/subtitles` | Upload subtitles |
| GET | `/genres` | List genres |
| GET | `/search` | Search movies |

**Web Routes:**
- `/apps/films/` — Film library UI

---

#### 3.3 Smart Home — IoT & Automation
**User Functions:**
- Device dashboard (lights, switches, sensors, climate, covers, media)
- Real-time WebSocket updates
- Scene management (activate, create, edit)
- MQTT broker integration (connect external devices)
- Automation rules (triggers, conditions, actions)
- Load testing / performance monitoring
- Device grouping by room/area

**API Endpoints (`/public-api/v1/smart_home`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/devices` | List all devices (state, capabilities) |
| GET | `/devices/{id}` | Get device details |
| POST | `/devices/{id}/action` | Execute device action (turn on, set brightness, etc.) |
| GET | `/scenes` | List scenes |
| POST | `/scenes` | Create scene |
| POST | `/scenes/{id}/activate` | Activate scene |
| PATCH | `/scenes/{id}` | Update scene |
| DELETE | `/scenes/{id}` | Delete scene |
| GET | `/rooms` | List rooms/areas |
| GET | `/mqtt/status` | MQTT connection status |
| POST | `/mqtt/publish` | Publish to MQTT topic |
| WS | `/ws` | WebSocket for real-time updates |
| GET | `/load-test` | Run load test |

**Web Routes:**
- `/apps/smart_home/` — Smart home dashboard UI

---

#### 3.4 Steps — Step Counter & Health Sync
**User Functions:**
- Step data recording (manual + auto from Health Connect)
- Health Connect (Android) sync setup & management
- Conflict resolution (local vs cloud data)
- Data backup/restore (export/import)
- Daily/weekly/monthly statistics & charts
- Profile & settings (goals, units, privacy)
- Multi-device support

**API Endpoints (`/public-api/v1/steps`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/data` | Get step data (date range, aggregation) |
| POST | `/data` | Submit step data |
| PATCH | `/data/{id}` | Update step entry |
| DELETE | `/data/{id}` | Delete step entry |
| GET | `/health-connect/status` | Sync status |
| POST | `/health-connect/sync` | Trigger manual sync |
| GET | `/health-connect/conflicts` | List data conflicts |
| POST | `/health-connect/conflicts/{id}/resolve` | Resolve conflict (choose local/cloud/merge) |
| GET | `/backups` | List backups |
| POST | `/backups` | Create backup |
| GET | `/backups/{id}/download` | Download backup |
| POST | `/backups/restore` | Restore from backup |
| GET | `/profile` | Get user profile |
| PATCH | `/profile` | Update profile (goals, units, etc.) |
| GET | `/settings` | Get app settings |
| PATCH | `/settings` | Update settings |
| GET | `/stats` | Aggregated statistics (daily/weekly/monthly) |

**Web Routes:**
- `/apps/steps/` — Steps dashboard UI

---

#### 3.5 Spends — Expense Tracking
**User Functions:**
- Expense entry (amount, category, date, notes, attachments)
- Category management (custom categories, icons, budgets)
- Recurring expenses (subscriptions, bills)
- Multi-currency support
- Charts & reports (by category, time, account)
- Data sharing (family/household)
- Export (CSV, PDF)

**API Endpoints (`/public-api/v1/spends`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/expenses` | List expenses (filter by date, category, account, search) |
| POST | `/expenses` | Create expense |
| GET | `/expenses/{id}` | Get expense details |
| PATCH | `/expenses/{id}` | Update expense |
| DELETE | `/expenses/{id}` | Delete expense |
| GET | `/categories` | List categories |
| POST | `/categories` | Create category |
| PATCH | `/categories/{id}` | Update category |
| DELETE | `/categories/{id}` | Delete category |
| GET | `/accounts` | List accounts/wallets |
| POST | `/accounts` | Create account |
| GET | `/recurring` | List recurring expenses |
| POST | `/recurring` | Create recurring expense |
| GET | `/reports/summary` | Summary report (totals by period) |
| GET | `/reports/by-category` | Breakdown by category |
| GET | `/reports/by-account` | Breakdown by account |
| GET | `/export` | Export data (CSV) |

**Web Routes:**
- `/apps/spends/` — Expense tracker UI

---

#### 3.6 Recipes — Recipe Management
**User Functions:**
- Recipe CRUD (title, description, ingredients, steps, photos, tags)
- Ingredient management (units, quantities, notes)
- Step-by-step cooking instructions
- Meal planning / weekly menu
- Shopping list generation from meal plan
- Recipe sharing (public links, export)
- Search & filter (tags, ingredients, time, difficulty)
- Import from URLs (schema.org, JSON-LD)

**API Endpoints (`/public-api/v1/recipes`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/recipes` | List recipes (search, filter, pagination) |
| POST | `/recipes` | Create recipe |
| GET | `/recipes/{id}` | Get full recipe |
| PATCH | `/recipes/{id}` | Update recipe |
| DELETE | `/recipes/{id}` | Delete recipe |
| GET | `/recipes/{id}/shopping-list` | Generate shopping list |
| POST | `/recipes/import` | Import from URL |
| GET | `/tags` | List all tags |
| GET | `/meal-plan` | Get meal plan (date range) |
| POST | `/meal-plan` | Add recipe to meal plan |
| DELETE | `/meal-plan/{id}` | Remove from meal plan |
| GET | `/shares` | List public shares |
| POST | `/shares` | Create public share |

**Web Routes:**
- `/apps/recipes/` — Recipe manager UI

---

#### 3.7 External App Integrations (SSO/Launch)
**Services:** Dawarich (RSS), Gitea (Git), Sentry (Errors), Immich (Photos), Kanboard (Tasks), Lopaka

**User Functions:**
- Single Sign-On (OAuth/OIDC) to external apps
- Launch apps from dashboard
- User provisioning (auto-create accounts)
- App access control (enable/disable per user)

**API Endpoints (`/public-api/v1/integrations`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | List configured integrations |
| POST | `/` | Add integration (OAuth config) |
| GET | `/{id}` | Get integration details |
| PATCH | `/{id}` | Update integration |
| DELETE | `/{id}` | Remove integration |
| GET | `/{id}/launch` | Get launch URL (SSO redirect) |

**Proxy Endpoints:**
- `/public-api/v1/kanboard/*` — Kanboard API proxy
- `/public-api/v1/dawarich/*` — Dawarich API proxy (if configured)

**Web Routes:**
- `/apps/{dawarich|gitea|sentry|immich|kanboard|lopaka}/` — Launch/SSO entrypoints

---

#### 3.8 Admin Panel (Admin only)
**User Functions:**
- User management (create, edit, delete, impersonate, roles)
- App access control (grant/revoke per user)
- System configuration (site settings, email, OAuth providers)
- Database administration (query, backup, migrations)
- Docker container management (start, stop, logs, stats)
- Shell command execution (audited)
- Backup management (create, schedule, restore, download)
- Performance monitoring (metrics, slow queries, memory)
- Audit logs (user actions, admin actions, security events)

**API Endpoints:**
| Section | Base Path | Key Endpoints |
|---------|-----------|---------------|
| Admin | `/public-api/v1/admin` | Users, roles, app access, audit logs |
| Config | `/public-api/v1/config` | System settings, email, OAuth |
| Docker | `/public-api/v1/docker` | Containers, images, logs, stats |
| Shell | `/public-api/v1/shell` | Execute command (POST /exec) |
| Backup | `/public-api/v1/backup` | Create, list, restore, download, schedule |
| Performance | `/public-api/v1/performance` | Metrics, slow queries, system stats |

**Web Routes:**
- `/project/backup/` — Backup manager UI
- `/project/storage/` — Storage analyzer UI
- `/project/performance/*` — Performance UI
- `/database` — Database admin panel

---

#### 3.9 Authentication & OAuth
**User Functions:**
- Email/password login + registration
- Email verification
- Password reset
- OAuth providers (Google, GitHub, Microsoft, etc.)
- Desktop client authorization (browser-mediated)
- API token management (create, revoke, device ID)
- MCP OAuth flow (for AI assistants)

**API Endpoints (`/public-api/v1/auth`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/login` | Login (email/password + device ID) → returns access + refresh token |
| GET | `/me` | Get current user info |
| POST | `/revoke` | Revoke current token |
| POST | `/refresh` | Refresh access token |
| POST | `/client_auth/request` | Request desktop auth (returns poll URL) |
| POST | `/client_auth/poll` | Poll for desktop auth completion |

**OAuth Endpoints (`/public-api/v1/oauth`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/authorize` | Authorization endpoint (user consent) |
| POST | `/token` | Token exchange (authorization_code, refresh_token, client_credentials) |
| GET | `/userinfo` | OpenID Connect userinfo |
| POST | `/revoke` | Revoke token |
| GET | `/clients` | List OAuth clients (admin) |
| POST | `/clients` | Create OAuth client (admin) |

**Web Routes:**
- `/login`, `/register`, `/verify` — Web auth
- `/oauth/approve` — Consent screen
- `/client-auth` — Desktop auth flow
- `/.well-known/openid-configuration` — OIDC discovery
- `/.well-known/oauth-authorization-server` — OAuth discovery

---

#### 3.10 MCP (Model Context Protocol) Server
**User Functions:**
- AI assistant integration (Claude, Cursor, etc.)
- Tool exposure: cloud files, films, recipes, smart home, steps, spends
- OAuth authentication for AI clients
- Server download (self-hosted)

**Endpoints:**
| Method | Path | Description |
|--------|------|-------------|
| GET | `/mcp` | MCP server info / download page |
| POST | `/mcp` | MCP JSON-RPC endpoint |
| GET | `/downloads/appdashboard-mcp.tgz` | Download packaged MCP server |

**Tools Exposed to AI:**
- Cloud: list_files, read_file, write_file, search_files, create_share
- Films: list_movies, get_movie, search_movies
- Recipes: list_recipes, get_recipe, search_recipes
- Smart Home: list_devices, control_device, list_scenes, activate_scene
- Steps: get_steps, get_stats
- Spends: list_expenses, get_expenses, get_categories

---

#### 3.11 Public Sharing (Sharepoint)
**User Functions:**
- Public links for any resource (files, movies, recipes, etc.)
- Optional password protection
- Expiry dates
- View/download tracking
- Unlock code for sensitive shares

**Web Routes:**
- `/s/{token}` — Generic share access
- `/sharepoint/*` — Sharepoint-style interface

---

### 4. API Reference (Consolidated)
- Authentication headers (`Authorization: Bearer`, `X-Device-Id`)
- Error format (RFC 7807 Problem Details)
- Pagination (cursor/offset)
- Rate limiting
- Versioning strategy
- WebSocket connection guide

### 5. User Guides (Task-oriented)
- "Set up WebDAV on Windows/macOS/Linux"
- "Connect Health Connect (Android) to Steps"
- "Add OAuth provider (Google/GitHub)"
- "Create public share link"
- "Configure MQTT for Smart Home"
- "Import recipes from website"
- "Set up MCP for Claude Desktop"
- "Run backup & restore"
- "Manage user access to apps"

### 6. Administrator Guides
- Initial setup & configuration
- Email/SMTP configuration
- OAuth provider setup
- Docker deployment & updates
- Backup strategy
- Performance tuning
- Security hardening
- Log aggregation

### 7. Troubleshooting
- Common errors & solutions
- Health check endpoints
- Debug mode
- Log locations

---

## Verification Checklist (Must cover 100%)

- [ ] Every service in `apps/` documented
- [ ] Every route in `project/routes.php` documented
- [ ] Every route in `public-api/routes/` documented
- [ ] Every WebSocket endpoint documented
- [ ] Every MCP tool documented
- [ ] Every admin function documented
- [ ] Every OAuth flow documented
- [ ] Every public sharing method documented
- [ ] All authentication methods documented
- [ ] All user-facing settings documented

---

## Output Format
- **Primary:** Markdown files in `docs/` (one per section above)
- **Navigation:** `mkdocs.yml` or similar for site generation
- **API Reference:** OpenAPI/Swagger spec (auto-generated from code annotations if possible)
- **Searchable:** Full-text search enabled

---

## Priority Order
1. Services reference (Sections 3.1–3.11) — core value
2. Authentication & OAuth (3.9) — prerequisite for API use
3. API Reference (Section 4) — developer reference
4. User Guides (Section 5) — task help
5. Admin Guides (Section 6) — operations
6. Troubleshooting (Section 7) — support