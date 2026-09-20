# Dawarich (Maps) Application - Detailed Documentation

**Path:** `apps/dawarich/`
**Type:** SSO Bridge (maps)
**Description:** Interactive maps for exploring locations, routes, and geographic data.

---

## Overview

Dawarich is a Maps application that acts as an SSO bridge to an external Dawarich instance. It provides location tracking, timeline, places, and photo integration.

## File Structure

```
apps/dawarich/
├── index.php        # SSO bridge entry point
├── description.json # App description (multilingual)
├── name.json        # Display name (multilingual)
└── icon.svg         # App icon
```

## Configuration Files

| File | Purpose |
|------|---------|
| `description.json` | App metadata and description (en, ru, uk, be, pl, cs) |
| `name.json` | Display name and menu button layout |
| `icon.svg` | App icon |

## Authentication

Acts as an SSO bridge: authenticates users via the main app dashboard session and provisions external service accounts via `oauth_provision_user_services()`.

### Theme Sync
When user changes theme in the main app, `dawarich_sync_user_theme()` syncs it to the external Dawarich instance.

## External Integration

Connected to external Dawarich service. Configuration via environment variables:
- `APP_AUTH_SERVICE_BRIDGE=1` — enables bridge mode

## Data Storage

External PostgreSQL database (`dawarich_development`).
