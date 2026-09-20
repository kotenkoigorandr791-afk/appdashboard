# Detailed Applications Documentation

This document provides comprehensive documentation for all **13** applications in the `/apps/` directory, plus the `project/performance/` module.

---

## Table of Contents

1. [Cloud Storage](#1-cloud-storage)
2. [Dawarich (Maps)](#2-dawarich-maps)
3. [Films (Movie Manager)](#3-films-movie-manager)
4. [Gitea (Git Service)](#4-gitea-git-service)
5. [Immich (Photo Backup)](#5-immich-photo-backup)
6. [Kanboard (Kanban Boards)](#6-kanboard-kanban-boards)
7. [Lopaka (TFT Generator)](#7-lopaka-tft-generator)
8. [Media Viewer](#8-media-viewer)
9. [Performance Monitor](#9-performance-monitor)
10. [Recipes](#10-recipes)
11. [Spends (Expense Tracker)](#11-spends-expense-tracker)
12. [Steps (Steps Counter)](#12-steps-steps-counter)
13. [Viewer (IoT Dashboard)](#13-viewer-iot-dashboard)

---

## 1. Cloud Storage

**Path:** `apps/cloud/`
**Main File:** `index.php`, `api.php`

### Overview

The flagship application — a comprehensive cloud storage and file management system with advanced editing capabilities, AI integration, and 3D viewing.

### File Structure

```
apps/cloud/
├── index.php                     # Main UI shell
├── api.php                       # REST API
├── ai.php                        # AI assistant endpoint
├── db.php                        # Cloud-specific DB functions
├── compile.php                   # Code compilation
├── format_code.php               # Code formatting
├── onlyoffice_editor.php         # OnlyOffice integration
├── 3mf-viewer.php               # 3D model viewer
├── api/actions/                  # API action handlers
├── assets/                       # JS/CSS assets
├── i18n/                         # Translation files
├── includes/                     # Shared includes
├── js/                           # JavaScript modules
├── templates.json                # File templates
├── translation_config.json       # i18n config
├── webdav.php                    # WebDAV server
└── storage/                      # User file storage
```

### Major Features
- File management (upload/download, folders, sharing, recycle bin)
- Code editors (Monaco Editor (VS Code), CodeMirror)
- 3D viewing (Three.js: 3MF, STL, OBJ, FBX, DXF, DWG)
- AI integration (LM Studio proxy, chat)
- Document editing (LaTeX, Markdown, OnlyOffice, Photo editor)
- Transcription (Python/whisper, Tesseract.js OCR)
- WebDAV support (`/webdav` and `apps/cloud/webdav.php`)

---

## 2. Dawarich (Maps)

**Path:** `apps/dawarich/`
**Main File:** `index.php`

### Overview

Maps application / SSO bridge to external Dawarich instance. Provides interactive maps with timeline, places, and photo integration.

### File Structure
```
apps/dawarich/
├── index.php                     # SSO bridge entry point
├── description.json              # App metadata
├── name.json                     # Display name
└── icon.svg                      # App icon
```

### Data Storage
External PostgreSQL database (`dawarich_development`) via dedicated connection.

### Type
**SSO Bridge** — authentication proxy to external Dawarich.

---

## 3. Films (Movie Manager)

**Path:** `apps/films/`

### Overview

Personal movie collection manager with TMDB integration.

### File Structure
```
apps/films/
├── index.php
├── controllers/
├── core/
├── config.json                   # TMDB API configuration
├── background.png
└── comments/
```

### Type
**Full App** — complete standalone application.

---

## 4. Gitea (Git Service)

**Path:** `apps/gitea/`

### Overview

SSO bridge to external Gitea Git service.

### File Structure
```
apps/gitea/
├── index.php
├── description.json
├── name.json
└── icon.svg
```

### Type
**SSO Bridge** — authentication proxy.

---

## 5. Immich (Photo Backup)

**Path:** `apps/immich/`

### Overview

SSO bridge to external Immich photo/video backup service.

### File Structure
```
apps/immich/
├── index.php
├── description.json
├── name.json
└── icon.svg
```

### Type
**SSO Bridge** — authentication proxy.

---

## 6. Kanboard (Kanban Boards)

**Path:** `apps/kanboard/`

### Overview

Complete Kanboard installation with AppDashboard plugin integration.

### File Structure
```
apps/kanbanard/
├── app/                          # Kanboard core
├── plugins/AppDashboard/         # AppDashboard plugin
├── assets/                       # Static assets
├── autoload.php                  # Composer autoloader
├── cli/                          # CLI commands
├── autoload_classmap.php
└── composer.json
```

### Type
**Full App** — embedded Kanboard instance.

---

## 7. Lopaka (TFT Generator)

**Path:** `apps/lopaka/`

### Overview

TFT/e-ink display editor for embedded screens.

### File Structure
```
apps/lopaka/
├── index.php                     # Main editor
├── assets/                       # Fonts, templates, icons
├── description.json
├── name.json
├── icon.svg
├── icons/
└── font-preview.html
```

### Type
**Full App**

---

## 8. Media Viewer

**Path:** `apps/media_viewer/`

### Overview

Image/video gallery with face recognition and EXIF viewing.

### File Structure
```
apps/media_viewer/
├── index.php
├── controllers/
├── core/
├── background_light.jpg
└── background_dark.jpg
```

### Type
**Full App**

---

## 9. Performance Monitor

**Path:** `project/performance/`

### Overview

Server performance monitoring with real-time metrics dashboard. Note: this application lives under `project/`, not `apps/`.

### File Structure
```
project/performance/
├── bin/monitor_collector.php     # Metrics collection daemon
├── controllers/MonitorController.php
├── core/
├── views/
├── i18n/
├── server_monitor.php            # Metrics API endpoint
├── monitor_stats.php             # Stats endpoint
├── index.php                     # UI entry
├── style.css
├── icon.svg
└── name.json
```

### API Endpoints

GET /performance/cpu      — CPU usage percentage
GET /performance/mem      — Memory usage percentage
GET /performance/memswap  — Swap memory usage
GET /performance/diskio   — Disk I/O statistics
GET /performance/network  — Network traffic
GET /performance/uptime   — System uptime

### Type
**Performance Module** — accessed at `/project/performance/index.php`

---

## 10. Recipes

**Path:** `apps/recipes/`

### Overview

Personal recipe collection with meal planning and AI features.

### File Structure
```
apps/recipes/
├── index.php
├── controllers/
├── core/
├── bg_remove.py                  # Background removal (AI)
├── color-tone.js
└── dark-background.jpg
```

### Type
**Full App**

---

## 11. Spends (Expense Tracker)

**Path:** `apps/spends/`

### Overview

Personal finance tracker with categories and reports.

### File Structure
```
apps/spends/
├── index.php
├── controllers/
├── core/
├── data/
├── backups/
└── default_profile.jpg
```

### Data Storage
Per-user SQLite databases.

### Type
**Full App**

---

## 12. Steps (Steps Counter)

**Path:** `apps/steps/`

### Overview

Pedometer and activity tracker with webhook support for mobile app sync.

### File Structure
```
apps/steps/
├── index.php
├── auth_common.php
├── auth_app.php
├── auth_web.php
├── background_*.jpg
├── steps-mobile-app/             # Mobile app source
└── steps.webp
```

### Type
**Full App**

---

## 13. Viewer (IoT Dashboard)

**Path:** `apps/viewer/`

### Overview

Network topology visualization and device monitoring dashboard.

### File Structure
```
apps/viewer/
├── index.php
├── controllers/
├── core/
├── i18n/
├── description.json
├── name.json
├── icon.svg
├── history.json
└── views/
```

### Type
**Full App**
