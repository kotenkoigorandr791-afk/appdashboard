# Applications Directory Documentation

The `apps/` directory contains **13** sub-applications that form the ServerKherson platform ecosystem.

> **Note:** For comprehensive documentation of each application with full details, API endpoints, database schemas, and configuration options, see **[06_detailed_applications_documentation.md](06_detailed_applications_documentation.md)**.

---

## 1. Cloud Storage Manager (`apps/cloud/`)

**The flagship application** - A comprehensive cloud storage and file management system with advanced editing capabilities.

### Files Overview

#### Core PHP Files

| File | Purpose |
|------|---------|
| `index.php` | Main UI shell with theme/language support |
| `api.php` | REST API for file operations, user management |
| `ai.php` | AI assistant integration endpoint |
| `db.php` | Cloud-specific database functions |

#### Editors & Viewers

| File | Purpose |
|------|---------|
| `3mf-viewer.php` | 3D model viewer (3MF, STL, OBJ, FBX, DXF formats) |
| `image_viewer.php` | Image viewing with zoom/pan |
| `latex_editor.php` | LaTeX document editor |
| `markdown_editor.php` | Markdown editor with live preview |
| `onlyoffice_editor.php` | OnlyOffice document integration |
| `photo_editor.php` | Photo editing capabilities |
| `transcription_editor.php` | Audio transcription editor |
| `zip_viewer.php` | ZIP archive viewer |

#### Backend Services

| File | Purpose |
|------|---------|
| `compile.php` | Code compilation service |
| `format_code.php` | Code formatting service |
| `transcription_api.php` | Transcription API endpoint |
| `transcribe.py` | Python transcription script |

#### Configuration Files

| File | Purpose |
|------|---------|
| `description.json` | App metadata |
| `name.json` | Display name |
| `icon.svg` | App icon |
| `templates.json` | File templates |
| `translation_config.json` | i18n configuration |

#### Data Directories

- `storage/` — User file storage (per-user directories)
- `temp/` — Temporary files
- `temp_convert/` — Conversion workspace
- `transcription_uploads/` — Audio files for transcription
- `tts_output/` — Text-to-speech output

---

## 2. Dawarich (Maps) (`apps/dawarich/`)

**Maps Application / SSO Bridge** - Provides interactive maps with timeline, places, and photo integration. SSO bridge for external Dawarich instance.

### Files Overview

| File | Purpose |
|------|---------|
| `index.php` | SSO bridge entry point |
| `description.json` | App metadata |
| `name.json` | Display name |
| `icon.svg` | App icon |

**Data Storage**: External PostgreSQL database (`dawarich_development`) via dedicated connection.

**Type**: **SSO Bridge App** - Acts as authentication proxy to external service.

---

## 3. Films (`apps/films/`)

**Movie Manager** - Personal movie collection manager with TMDB integration.

### Files Overview

#### Core Files

| File/Directory | Purpose |
|----------------|---------|
| `index.php` | Main entry point |
| `controllers/` | MVC controllers |
| `core/` | Core models and services |
| `config.json` | TMDB API configuration |
| `background.png` | Default background |

#### Data Directories

- `comments/` — User comments per movie

**Type**: **Full App** - Complete standalone application with UI.

---

## 4. Gitea (`apps/gitea/`)

**Git Service** - Self-hosted Git service (SSO bridge to external Gitea instance).

### Files Overview

| File | Purpose |
|------|---------|
| `index.php` | SSO bridge entry point |
| `description.json` | App metadata |
| `name.json` | Display name |
| `icon.svg` | App icon |

**Type**: **SSO Bridge App** - Authentication proxy to external Gitea.

---

## 5. Immich (`apps/immich/`)

**Photo/Video Backup** - Self-hosted photo/video backup solution (SSO bridge).

### Files Overview

| File | Purpose |
|------|---------|
| `index.php` | SSO bridge entry point |
| `description.json` | App metadata |
| `name.json` | Display name |
| `icon.svg` | App icon |

**Type**: **SSO Bridge App** - Authentication proxy to external Immich.

---

## 6. Kanboard (`apps/kanboard/`)

**Kanban Boards** - Full Kanboard installation with AppDashboard plugin.

### Files Overview

| Directory | Purpose |
|-----------|---------|
| `app/` | Kanboard core application |
| `plugins/AppDashboard/` | AppDashboard integration plugin |
| `assets/` | Static assets |
| `autoload.php` | Composer autoloader |
| `cli/` | CLI commands |

**Type**: **Full App** - Complete Kanboard instance embedded.

---

## 7. Lopaka (`apps/lopaka/`)

**TFT Display Generator** - Embedded display editor for TFT/e-ink screens.

### Files Overview

| File/Directory | Purpose |
|----------------|---------|
| `index.php` | Main editor |
| `assets/` | Fonts, templates, icons |
| `description.json` | App metadata |
| `name.json` | Display name |
| `icon.svg` | App icon |
| `font-preview.html` | Font testing page |

**Type**: **Full App** - Embedded display layout editor.

---

## 8. Media Viewer (`apps/media_viewer/`)

**Media Gallery** - Image/video gallery with face recognition and EXIF viewing.

### Files Overview

| File/Directory | Purpose |
|----------------|---------|
| `index.php` | Main entry point |
| `controllers/` | MVC controllers |
| `core/` | Core models and services |
| `background_light.jpg` / `background_dark.jpg` | Theme backgrounds |

**Type**: **Full App** - Complete media gallery with ML features.

---

## 9. Recipes (`apps/recipes/`)

**Recipe Manager** - Personal recipe collection with meal planning.

### Files Overview

| File/Directory | Purpose |
|----------------|---------|
| `index.php` | Main entry point |
| `controllers/` | MVC controllers |
| `core/` | Core models and services |
| `bg_remove.py` | Background removal script |
| `color-tone.js` | Color palette utility |
| `dark-background.jpg` | Theme background |

**Type**: **Full App** - Complete recipe management with AI features.

---

## 10. Smart Home (`apps/smart_home/`)

**Smart Home Controller** - MQTT-based home automation dashboard.

### Files Overview

| File/Directory | Purpose |
|----------------|---------|
| `index.php` | Main dashboard |
| `core/` | Core models and MQTT handlers |
| `views/` | Dashboard views and scripts |
| `assets/` | Background images |
| `SmartHomeWsHandler.php` | WebSocket handler for real-time updates |
| `mqtt_auth_sync.php` | MQTT authentication sync |
| `mqtt_worker.php` | Background MQTT worker |
| `ws_control_bridge.php` | WebSocket control bridge |
| `ws_dispatch_tick.php` | WebSocket tick dispatcher |
| `ws_server.php` | WebSocket server |
| `smart-home-ws.service` | Systemd service file |

**Data Storage**: SQLite databases per user in `data/`; MQTT broker external.

**Type**: **Full App** - Real-time IoT dashboard with WebSocket.

---

## 11. Spends (`apps/spends/`)

**Expense Tracker** - Personal finance tracker with categories and reports.

### Files Overview

| File/Directory | Purpose |
|----------------|---------|
| `index.php` | Main entry point |
| `controllers/` | MVC controllers |
| `core/` | Core models and services |
| `data/` | User data files |
| `backups/` | Automated backups |
| `default_profile.jpg` | Default profile image |

**Data Storage**: Per-user SQLite databases.

**Type**: **Full App** - Complete expense tracking application.

---

## 12. Steps (`apps/steps/`)

**Steps Counter** - Pedometer and activity tracker with webhook support.

### Files Overview

| File | Purpose |
|------|---------|
| `index.php` | Main dashboard |
| `auth_common.php` | Shared auth helpers |
| `auth_app.php` | App-specific auth |
| `auth_web.php` | Web auth handlers |
| `background_*.jpg` | Theme backgrounds |
| `steps-mobile-app/` | Mobile app source (Flutter/React Native) |
| `steps.webp` | App icon |

**Features**:
- Step counting with daily/weekly/monthly stats
- Webhook endpoint for mobile app sync (`/public-api/steps`)
- Goal tracking and achievements
- Multi-device sync

**Type**: **Full App** - Activity tracker with mobile integration.

---

## 13. Viewer (`apps/viewer/`)

**Network Device Viewer** - Network topology visualization and device monitoring.

### Files Overview

| File/Directory | Purpose |
|----------------|---------|
| `index.php` | Main entry point |
| `controllers/` | MVC controllers |
| `core/` | Core models and services |
| `i18n/` | Translation files |
| `description.json` | App metadata |
| `name.json` | Display name |
| `history.json` | Device history |
| `icon.svg` | App icon |

**Type**: **Full App** - Network monitoring dashboard.

---

## Removed / Relocated Applications

| Old Path | Status | New Location |
|----------|--------|--------------|
| `apps/map/` | Renamed | `apps/dawarich/` |
| `apps/performance/` | Moved | `project/performance/` |

---

## App Classification Summary

| Category | Apps |
|----------|------|
| **Full Apps** | cloud, films, kanboard, lopaka, media_viewer, recipes, smart_home, spends, steps, viewer |
| **SSO Bridges** | dawarich, gitea, immich |
| **Total** | **13 apps** |

---

## Shared Infrastructure

All apps share:
- **Authentication**: Central auth via `project/index.php` session
- **Database**: PostgreSQL (appdashboard) for user/auth data
- **i18n**: 6 languages (en, uk, ru, be, pl, cs)
- **Theme**: Dark/light mode via CSS variables
- **API**: `/public-api/` for external integrations
