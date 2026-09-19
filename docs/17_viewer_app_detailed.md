# Viewer (IoT Dashboard) Application - Detailed Documentation

**Path:** `apps/viewer/`  
**Type:** Full Application  
**Description:** Web dashboard for viewing and managing linked web content panels.

---

## Overview

Viewer is a web-based dashboard that aggregates and displays linked web content panels. It acts as a unified interface for monitoring multiple web-based services and IoT dashboards in a single view.

---

## File Structure

```
apps/viewer/
├── index.php                     # Main entry point
├── controllers/                  # MVC controllers
│   ├── ViewController.php        # Main controller
│   └── PanelController.php       # Panel management
├── core/                         # Core models, services, helpers
│   ├── ViewerModel.php           # Panel model
│   └── PanelService.php          # Panel service
├── i18n/                         # Translation files
│   ├── en.json
│   ├── uk.json
│   ├── ru.json
│   ├── be.json
│   ├── pl.json
│   └── cs.json
├── views/                        # View templates
│   ├── main.php                  # Dashboard view
│   ├── scripts.php               # JS includes
│   └── styles.php                # CSS includes
├── description.json              # App metadata (multilingual)
├── name.json                     # Display name (multilingual)
├── history.json                  # Panel navigation history
└── icon.svg                      # App icon
```

## Configuration Files

| File | Purpose |
|------|---------|
| `description.json` | App description (6 languages) |
| `name.json` | Display name and menu button layout |
| `icon.svg` | App icon |
| `history.json` | Navigation history |

## Key Features

### Dashboard
- Web panel aggregation (embed multiple web pages)
- Custom layouts (grid, tabs, fullscreen)
- Auto-refresh intervals
- Dark/light theme support
- Panel navigation history

### Panel Management
- Add/edit/remove panels
- Custom panel URLs and titles
- Panel size and arrangement
- Visibility toggles
- Default panel selection

### Navigation
- Browser history integration (`history.json`)
- Back/forward navigation
- Bookmark panels
- Panel search

### Layout Options
- Grid layout (2x2, 3x3, custom)
- Tabbed interface
- Fullscreen single panel
- Split-screen modes

## API Endpoints

### Panels
- `GET /api/viewer/panels` - List saved panels
- `POST /api/viewer/panels` - Save panel configuration
- `PUT /api/viewer/panels/{id}` - Update panel
- `DELETE /api/viewer/panels/{id}` - Delete panel

### History
- `GET /api/viewer/history` - Get navigation history
- `POST /api/viewer/history` - Save navigation event

## Security Features

- Panel URL validation
- X-Frame-Options for embedded content
- Content Security Policy (CSP) for iframes
- User-specific panel configurations

## Internationalization

Supports all 6 interface languages (en, uk, ru, be, pl, cs) via per-app i18n files.

## Deployment Notes

- No database required (configurations can be file-based or DB-backed)
- Embedded iframes require proper CORS settings
- Panel sources must be accessible from the server
- HTTPS required for mixed-content prevention

## Panel Types

| Type | Description |
|------|-------------|
| Web URL | Embedded iframe to external/internal URL |
| Local App | Link to another app within the dashboard |
| Custom HTML | Inline HTML/JS content panel |
