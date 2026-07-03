# Detailed Applications Documentation

This document provides comprehensive documentation for all 10 applications in the `/apps/` directory.

---

## Table of Contents

1. [Cloud Storage](#1-cloud-storage)
2. [Films (Movie Manager)](#2-films-movie-manager)
3. [Map](#3-map)
4. [Media Viewer](#4-media-viewer)
5. [Performance (Server Monitor)](#5-performance-server-monitor)
6. [Recipes](#6-recipes)
7. [Spends (Expense Tracker)](#7-spends-expense-tracker)
8. [Steps (Steps Counter)](#8-steps-steps-counter)
9. [TFT Generator](#9-tft-generator)
10. [Viewer (IoT Dashboard)](#10-viewer-iot-dashboard)

---

## 1. Cloud Storage

**Path:** `apps/cloud/`  
**Size:** ~15,000+ lines of code  
**Main File:** `index.php` (1,595 lines), `api.php` (5,048 lines)

### Overview

The flagship application - a comprehensive cloud storage and file management system with advanced editing capabilities, AI integration, and 3D viewing.

### File Structure

```
apps/cloud/
├── index.php                    # Main UI shell
├── api.php                      # REST API (5,048 lines)
├── ai.php                       # AI assistant endpoint
├── db.php                       # Cloud-specific DB functions
├── compile.php                  # Code compilation
├── format_code.php              # Code formatting
├── de_format_code.php           # German code formatting
├── remove_comments.php          # Comment removal
├── save_config.php              # Configuration saver
│
├── Editors
│   ├── latex_editor.php         # LaTeX document editor
│   ├── markdown_editor.php      # Markdown with live preview
│   ├── photo_editor.php         # Photo editing
│   ├── onlyoffice_editor.php    # OnlyOffice integration
│   └── transcription_editor.php # Audio transcription editor
│
├── Viewers
│   ├── 3mf-viewer.php           # 3D model viewer
│   ├── image_viewer.php         # Image viewer
│   ├── pdf_viewer_template.php  # PDF viewer
│   └── zip_viewer.php           # ZIP archive viewer
│
├── AI & Transcription
│   ├── transcribe.py            # Python transcription script
│   └── transcription_api.php    # Transcription API
│
├── Configuration
│   ├── composer.json            # PHP dependencies
│   ├── ai-config.json           # AI API settings
│   ├── templates.json           # LaTeX templates
│   └── translation_config.json  # Translation settings
│
├── Directories
│   ├── assets/                  # Frontend assets (JS, CSS, i18n)
│   ├── js/                      # 3D libraries (Three.js ecosystem)
│   ├── data/                    # User data storage
│   ├── temp/                    # Temporary files
│   ├── transcriptions/          # Transcription results
│   ├── upload_sessions/         # Upload session tracking
│   ├── vendor/                  # PHP dependencies
│   └── exiftool/                # EXIF metadata tool
```

### Features

#### File Management
- Upload/download with progress tracking (up to 500MB)
- Folder structure with expand/collapse state persistence
- File sharing with token-based access
- Recycle bin functionality
- Advanced search with filters
- Resumable upload sessions

#### Code Editors
- **Monaco Editor** (VS Code's editor) with AMD loading
- **CodeMirror** for lightweight editing
- Syntax highlighting for 20+ languages:
  - JavaScript, XML, CSS, HTML, PHP, Python, SQL, YAML
  - Shell, C-like, Ruby, Go, Swift, Kotlin, Lua, Perl, R, TOML, Vue, Octave
- Auto-closing brackets/tags
- Code folding and hinting
- Format code functionality

#### 3D Viewing
- **Three.js** rendering engine
- Supported formats: 3MF, STL, OBJ, FBX, DXF, DWG
- Orbit camera controls
- Material/texture support (MTL loader)
- Full-screen mode

#### AI Integration
- Model: `meta-llama/Llama-3.3-70B-Instruct`
- Chat history stored in `ai_assistant_chats` table
- Context-aware conversations
- Code generation and explanation
- Configurable via `ai-config.json`

#### Document Editing
- **LaTeX Editor** with templates:
  - Empty document
  - Lab report
  - Technical report
  - Math paper
  - Course work
- **Markdown Editor** with live preview
- **OnlyOffice** integration for DOCX/XLSX/PPTX

#### Transcription & OCR
- Python-based audio transcription
- Tesseract.js for OCR
- Upload audio files for automatic transcription
- Editor for reviewing and correcting transcriptions

### Database Tables

```sql
-- Shared database: /mnt/server/PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)

-- User cloud settings
cloud_user_settings (
    email PRIMARY KEY,
    quota_mb DEFAULT 500,
    session_limit_mb DEFAULT 0,
    sync_local_path,
    sync_interval_sec DEFAULT 300,
    sync_mode DEFAULT 'mtime_size',
    sync_enabled,
    last_sync_at,
    updated_at
)

-- Folder UI state
cloud_folder_state (
    email PRIMARY KEY,
    folder_json DEFAULT '[]',
    updated_at
)

-- Storage usage stats
cloud_storage_usage_stats (
    id PRIMARY KEY,
    email,
    total_bytes,
    file_count,
    folder_count,
    updated_at
)

-- AI chat history
ai_assistant_chats (
    id PRIMARY KEY,
    user_email,
    chat_name,
    created_at,
    updated_at
)

-- Shared files
shared_files (
    share_id PRIMARY KEY,
    file_name,
    file_path,
    user_email,
    created_at,
    expires
)
```

### API Endpoints

```
# User Management
GET    /api.php?action=get_users
POST   /api.php?action=create_user
PUT    /api.php?action=update_user
DELETE /api.php?action=delete_user

# File Operations
GET    /api.php?action=files&path=/folder
POST   /api.php?action=upload
DELETE /api.php?action=delete&path=/file.txt
POST   /api.php?action=rename
POST   /api.php?action=move
POST   /api.php?action=copy
POST   /api.php?action=create-folder
POST   /api.php?action=create-file

# Sharing
GET    /api.php?action=share&path=/file.txt
POST   /api.php?action=share-create
DELETE /api.php?action=share-delete

# AI
POST   /api.php?action=ai-chat
GET    /api.php?action=ai-history
DELETE /api.php?action=ai-delete-chat

# Storage
GET    /api.php?action=storage-usage

# Upload Sessions
POST   /api.php?action=upload-session-create
PUT    /api.php?action=upload-session-chunk
POST   /api.php?action=upload-session-complete
```

### Configuration

**ai-config.json:**
```json
{
    "api_key": "",
    "endpoint": "https://api.example.com/v1",
    "model": "meta-llama/Llama-3.3-70B-Instruct",
    "max_tokens": 4096,
    "temperature": 0.7
}
```

**templates.json:**
```json
{
    "empty": { "en": "...", "ru": "...", "uk": "...", "be": "..." },
    "lab_report": { "en": "...", "ru": "...", "uk": "...", "be": "..." },
    "technical_report": { "en": "...", "ru": "...", "uk": "...", "be": "..." },
    "math_paper": { "en": "...", "ru": "...", "uk": "...", "be": "..." },
    "course_work": { "en": "...", "ru": "...", "uk": "...", "be": "..." }
}
```

### Frontend Libraries

**3D Libraries (js/):**
- `three.min.js` - Three.js core
- `3MFLoader.js` - 3MF format loader
- `STLLoader.js` - STL format loader
- `OBJLoader.js` - OBJ format loader
- `FBXLoader.js` - FBX format loader
- `MTLLoader.js` - Material loader
- `OrbitControls.js` - Camera controls
- `dxf-parser.min.js` - DXF parser
- `libredwg-web.umd.min.js` - DWG parser

**Editors:**
- Monaco Editor 0.52.2
- CodeMirror 5.65.16
- highlight.js 11.9.0

**Other:**
- Tesseract.js 6 (OCR)
- Plyr 3.7.8 (video player)
- QRCode.js 1.0.0 (QR generation)

---

## 2. Films (Movie Manager)

**Path:** `apps/films/`  
**Size:** ~3,717 lines  
**Main File:** `index.php`

### Overview

A movie and video library management system with local and online content support, ratings, and comments.

### File Structure

```
apps/films/
├── index.php                    # Main interface
├── config.json                  # App configuration
├── movies.json                  # Movie database
├── name.json                    # App name translations
├── translations.php             # UI translations
├── background.png               # Background image
├── icon.png                     # App icon
├── comments/                    # User comments (JSON per movie)
└── php_errors.log
```

### Features

#### Movie Library
- Categories: Action, Comedy, Drama, Horror, Romance, Sci-Fi, Thriller, Animation, Documentary, Adventure
- Dual content: Online streaming links + local file uploads
- Movie metadata: title, description, year, genre, rating, poster
- Search and filter by genre
- Hidden movies support (via config)

#### Video Player
- Custom controls: play/pause, seek, volume, fullscreen
- Playback speed control
- Progress saving (lastPosition)
- Keyboard shortcuts:
  - Space: Play/Pause
  - Left/Right: Seek ±10 seconds
  - Up/Down: Volume ±5%
- Picture-in-Picture mode
- AirPlay support

#### Rating System
- 5-star rating
- Average rating calculation
- User-specific ratings

#### Comments
- Per-movie comment threads
- Comments stored as JSON files
- Admin moderation

#### Admin Panel
- Add/edit/delete movies
- Manage comments
- Hide/show movies
- Bulk operations

### Configuration

**config.json:**
```json
{
    "hidden_movies": ["movie_id_1", "movie_id_2"],
    "video_settings": {
        "seek_step": 10,
        "volume_step": 5
    }
}
```

**movies.json:**
```json
{
    "movies": [
        {
            "id": "unique_id",
            "title": {"en": "...", "ru": "..."},
            "description": {"en": "...", "ru": "..."},
            "year": 2023,
            "genre": ["Action", "Sci-Fi"],
            "rating": 4.5,
            "poster": "poster.jpg",
            "video_url": "https://...",
            "local_file": "uploads/movie.mp4"
        }
    ]
}
```

### Database

Uses JSON files instead of SQLite:
- `movies.json` - Movie database
- `comments/{movie_id}.json` - Comments per movie
- Shared auth via `PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)`

---

## 3. Map

**Path:** `apps/map/`  
**Main File:** `index.php`

### Overview

An interactive maps application with per-user data storage and file upload support.

### File Structure

```
apps/map/
├── index.php                    # Map interface
├── name.json                    # App name translations
├── icon.png                     # App icon
├── uploads/                     # User uploaded files
├── user_data/                   # Per-user data
│   └── {username}/
│       └── data.sqlite3
└── php_errors.log
```

### Features

#### Map Display
- Interactive map with pan/zoom
- API key integration (VlPMGl445dR0JkzdMN6a)
- Marker placement
- Custom overlays

#### User Data
- Per-user PostgreSQL databases
- Complete data isolation
- File upload support for markers
- Session-based user identification

#### Access Control
- Role-based permissions
- Guest/Manager/Admin levels

### Database

**Per-user database:** `user_data/{username}/data.sqlite3`

Tables created dynamically based on user needs.

---

## 4. Media Viewer

**Path:** `apps/media_viewer/`  
**Main File:** `index.php`

### Overview

A photo album gallery with view statistics and role-based access control.

### File Structure

```
apps/media_viewer/
├── index.php                    # Gallery interface
├── albums.json                  # Album definitions
├── name.json                    # App name
├── translations.json            # UI translations
├── update_stats.php             # Statistics updater
├── icon.png                     # App icon
├── data/
│   ├── albums.json              # Runtime data
│   └── stats.json               # View counts
├── previews/                    # Generated thumbnails
├── install/                     # Installation scripts
└── Thumbs.db
```

### Features

#### Album Management
- Grid layout with customizable columns
- External URL support for images
- Album descriptions
- Sort order control

#### View Statistics
- Per-album view counter
- Statistics stored in `data/stats.json`
- Real-time updates

#### Access Control
- Role-based visibility (Guest/Manager/Admin)
- Per-album access levels
- Hidden albums support

#### Upload
- Drag & drop upload
- Clipboard paste support
- Automatic thumbnail generation
- Progress indicator

#### Admin Panel
- Add/edit/delete albums
- Reorder albums
- Set access levels
- View statistics

### Configuration

**translations.json:**
```json
{
    "grid_columns": 4,
    "font_size": 14,
    "albums_per_page": 20,
    "access_levels": {
        "guest": 0,
        "manager": 1,
        "admin": 2
    }
}
```

### Database

- `albums.json` - Album definitions
- `data/stats.json` - View statistics
- Shared auth via `PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)`

---

## 5. Performance (Server Monitor)

**Path:** `apps/performance/`  
**Size:** ~1,608 lines  
**Main File:** `index.php`, `server_monitor.php`

### Overview

A real-time server monitoring dashboard with comprehensive system metrics visualization.

### File Structure

```
apps/performance/
├── index.php                    # Dashboard UI
├── server_monitor.php           # Metrics API
├── name.json                    # App name
├── translations.php             # UI translations
├── style.css                    # Dashboard styles
├── icon.png                     # App icon
├── modules_cache.json           # Apache modules cache
├── cache/                       # Cached metrics
└── Thumbs.db
```

### Features

#### System Metrics
- **CPU Usage:** Real-time utilization percentage
- **Memory:** RAM usage with breakdown
- **Swap:** Swap memory usage
- **Disk I/O:** Read/write operations per second
- **Network:** Upload/download speed per interface
- **Uptime:** System uptime display

#### Module Information
- Apache modules listing
- PHP extensions listing
- Cached for performance (5-second TTL)

#### Connected Devices
- Drive information
- Mount points
- Storage capacity

#### Visualization
- Real-time charts
- Color-coded metrics
- Live vs Cache data toggle
- Debug mode support

### API Integration

**External API:** `http://127.0.0.1:3550/api/4`

**Endpoints:**
```
GET /cpu       - CPU metrics
GET /memory    - Memory usage
GET /disk      - Disk I/O
GET /network   - Network interfaces
GET /swap      - Swap usage
GET /uptime    - System uptime
```

### Caching

**Cache Directory:** `cache/`

- Serialized PHP data
- 5-second TTL for most metrics
- Manual cache refresh option
- Debug mode bypasses cache

### Database

- Cache stored as serialized files in `cache/`
- Shared auth via `PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)`

---

## 6. Recipes

**Path:** `apps/recipes/`  
**Main File:** `index.php`

### Overview

A recipe management system with ingredient-based search and multi-language support.

### File Structure

```
apps/recipes/
├── index.php                    # Main interface
├── name.json                    # App name
├── translations.json            # UI translations
├── recipe_state.json            # Current recipe state
├── styles.php                   # Dynamic CSS
├── dark-background.jpg          # Dark theme background
├── light-background.jpg         # Light theme background
├── default_recipe.jpg           # Default recipe image
├── icon.png                     # App icon
├── recipes/                     # Recipe storage
│   └── {recipe_id}/
│       └── recipe.json
├── products/                    # Product database
│   └── {product_id}/
│       └── product.json
└── Thumbs.db
```

### Features

#### Recipe Management
- Create/edit/delete recipes
- Ingredients with quantities and units
- Step-by-step instructions
- Recipe images
- Multi-language names and descriptions

#### Product Database
- Multi-language product names (EN/UK/RU/BE)
- Categories and tags
- Nutritional information (optional)

#### Smart Search
- **Ingredient matching:** Find recipes with available ingredients
- **Partial matching:** Shows recipes with some missing ingredients
- **Missing ingredients display:** Lists up to 2 missing items
- Filter by category

#### Cooking Mode
- Step-by-step view
- Timer support for steps
- Mark steps as completed
- Undo functionality

#### Recipe Sharing
- Image generation for sharing
- Social media integration
- Print-friendly format

#### Pagination
- Configurable items per page (default 12)
- Page navigation
- Sort options

### Data Structure

**Recipe JSON:**
```json
{
    "id": "recipe_id",
    "name": {"en": "...", "ru": "...", "uk": "...", "be": "..."},
    "description": {"en": "...", "ru": "..."},
    "ingredients": [
        {
            "product_id": "prod_123",
            "quantity": 200,
            "unit": "grams"
        }
    ],
    "steps": [
        {
            "text": {"en": "...", "ru": "..."},
            "timer_seconds": 300,
            "image": "step1.jpg"
        }
    ],
    "prep_time_minutes": 30,
    "cook_time_minutes": 45,
    "servings": 4,
    "image": "recipe.jpg"
}
```

**Product JSON:**
```json
{
    "id": "prod_123",
    "name": {
        "en": "Flour",
        "uk": "Борошно",
        "ru": "Мука",
        "be": "Мука"
    },
    "category": "Baking",
    "units": ["grams", "kg", "cups"]
}
```

### Database

- Recipe data: `recipes/` directory (JSON)
- Product data: `products/` directory (JSON)
- State: `recipe_state.json`
- Shared auth: `PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)`

---

## 7. Spends (Expense Tracker)

**Path:** `apps/spends/`  
**Size:** ~2,625 lines  
**Main File:** `index.php`

### Overview

A comprehensive expense tracking system with per-store spending, budget tracking, and chart visualization.

### File Structure

```
apps/spends/
├── index.php                    # Main interface
├── name.json                    # App name
├── translations.json            # UI translations
├── global_settings.json         # Global settings
├── default_profile.jpg          # Default profile image
├── icon.png                     # App icon
├── table_settings.png           # Table settings visual
├── расходы 2023-2024.xlsx       # Sample expense file
├── backups/                     # Data backups
├── upload_sessions/             # Upload tracking
├── utils/                       # Import utilities
└── php_errors.log
```

### Features

#### Expense Tracking
- Per-store expense entries
- Date-based organization
- Amount tracking with currency support
- Table view with sorting

#### Store Management
- Multiple stores with custom names
- Store photos with cropping
- Visibility toggle
- Sort order control

#### Budget Tracking
- Monthly budget setting
- Budget vs actual comparison
- Currency selection
- Progress indicators

#### Visualization
- Interactive charts
- Downloadable as image
- Share to clipboard
- Copy for social media

#### Data Import
- Excel/CSV import
- CSV merge (combines by date/store)
- Utils for data processing
- Sample file included

#### Database Viewer
- Browse tables: expenses, stores, settings, table_settings
- Edit data directly
- Export functionality

#### Backup/Restore
- Automatic backups
- Manual backup creation
- Restore from backup
- Organized by date

#### Table Settings
- Sort by column
- Rows per page
- Date range filter
- Persistent settings

### Database Schema

**Per-user database:** `APPS_DATA_DIR/{user}/data.sqlite3`

```sql
-- User settings
settings (
    key PRIMARY KEY,
    value
)
-- Keys: monthly_budget, currency

-- Stores
stores (
    id PRIMARY KEY,
    name,
    visible DEFAULT 1,
    sort_order DEFAULT 0
)

-- Expenses
expenses (
    date,
    store,
    amount,
    PRIMARY KEY (date, store)
)
CREATE INDEX idx_expenses_date ON expenses(date);
CREATE INDEX idx_expenses_store ON expenses(store);

-- Table display settings
table_settings (
    key PRIMARY KEY,
    value
)
-- Keys: sort_by, rows_per_page, start_date
```

### Configuration

**global_settings.json:**
```json
{
    "current_user": "user@example.com",
    "table_settings": {
        "sort_by": "date",
        "rows_per_page": 50,
        "start_date": "2024-01-01"
    }
}
```

### Unique Features

- **Mouse wheel adjustment:** Scroll to change date/values
- **Invalid date detection:** Auto-fix invalid dates
- **Excel-style editing:** Hotkeys (Ctrl+A, Ctrl+S, Delete)
- **Shift+select:** Range selection

---

## 8. Steps (Steps Counter)

**Path:** `apps/steps/`  
**Main File:** `index.php`

### Overview

A daily step counter with goal tracking, multiple view modes, and video analysis for automatic step counting.

### File Structure

```
apps/steps/
├── index.php                    # Main interface
├── name.json                    # App name
├── translations.json            # UI translations
├── default_profile.jpg          # Default profile image
├── icon.png                     # App icon
├── steps.png                    # Steps icon
├── distance.png                 # Distance icon
├── goal.png                     # Goal icon
├── kkal.png                     # Calories icon
├── upload_sessions/             # Upload tracking
├── utils/                       # Video analysis tools
│   ├── analize.php              # Video analysis script
│   └── bin/                     # Tesseract man pages
└── php_errors.log
```

### Features

#### Step Tracking
- Daily step entries
- Date-based organization
- Multiple data types: Steps, Distance (km), Calories

#### View Modes
- **Year View:** Calendar with monthly summaries
- **Month View:** Daily breakdown
- **Week View:** Weekly progress
- **Today View:** Current day details

#### Goal Tracking
- Configurable daily step goal (default 10,000)
- Goal achievement indicators
- Weekly goal tracking
- Progress visualization

#### Charts
- Bar chart for daily values
- Line chart for trends
- Zoom functionality
- Interactive tooltips

#### Data Entry
- Excel-like table editor
- Hotkey support:
  - Ctrl+A: Select all
  - Ctrl+S: Save
  - Delete: Clear cell
  - Shift+click: Range select
- Search with filters (=, range)

#### Video Analysis
- Upload walking/exercise videos
- Automatic step counting via computer vision
- Results imported to table

#### User Settings
- Goal steps (default 10,000)
- Height (cm, default 170)
- Weight (kg, default 70)
- Stride length (cm, default 70)
- Birth date (for age calculation)

#### Backup/Restore
- Backup management from Excel view
- Restore from backup
- Organized by date

### Database Schema

**Per-user database:** `{userDir}/user_data.sqlite3`

```sql
-- Daily steps
steps (
    date PRIMARY KEY,
    steps
)

-- User settings
settings (
    key PRIMARY KEY,
    value
)
-- Keys: goal_steps, height, weight, stride_length, birth_date
```

### Default Settings

```json
{
    "goal_steps": 10000,
    "height": 170,
    "weight": 70,
    "stride_length": 70,
    "birth_date": "1994-01-01"
}
```

### Calculations

**Distance:** `steps × stride_length / 100000` (km)

**Calories:** Based on weight, distance, and age

**Age:** Calculated from birth_date

### Unique Features

- **Video analysis:** Automatic step counting from videos
- **Excel-style editing:** Full spreadsheet-like experience
- **Date bounds calculation:** Efficient data loading
- **Weekly goal tracking:** 7-day rolling averages

---

## 9. TFT Generator

**Path:** `apps/tft-generator/`  
**Main File:** `index.php`

### Overview

A visual code generator for TFT (Thin Film Transistor) displays with drawing tools and touch mode support.

### File Structure

```
apps/tft-generator/
├── index.php                    # Generator interface
├── name.json                    # App name
├── style.css                    # Styles
├── translations.js              # Client translations
├── icon.png                     # App icon
├── userdata/                    # Per-user storage
│   └── {email}/
│       └── data.sqlite3
├── upload_sessions/             # Upload tracking
└── Thumbs.db
```

### Features

#### Drawing Tools

| Tool | Hotkey | Description |
|------|--------|-------------|
| Point | P | Draw single point |
| Line | L | Draw line between two points |
| Circle | C | Draw circle with center and radius |
| Rectangle | R | Draw axis-aligned rectangle |
| Round Rectangle | O | Draw rectangle with rounded corners |
| Triangle | T | Draw triangle |
| Text | J | Add text label |
| Selection | S | Select and manipulate objects |
| Move | M | Move canvas view |
| Eraser | E | Erase objects |
| Clear | X | Clear entire canvas |

#### Canvas Manipulation
- Move canvas view
- Object selection
- Multi-object selection
- Eraser tool
- Clear canvas

#### Object Snapping
- Endpoint snap
- Midpoint snap
- Center snap
- Corner snap
- Visual snap indicators

#### Touch Mode
- Virtual cursor
- Gesture support:
  - Pinch zoom
  - Two-finger tap (right-click)
  - Two-finger drag (pan)
- Touch-friendly UI

#### Code Generation
- Generate embedded C/C++ code
- Copy to clipboard
- Configurable output format
- Color definitions
- Drawing function calls

#### File Operations
- Load PNG images
- Save canvas as PNG
- Save/load configuration
- Export generated code

#### Settings
- Canvas size
- Grid display
- Snap tolerance
- Cursor constraint modes:
  - Free
  - Horizontal
  - Vertical
- Origin point (4 corners)

### Database Schema

**Per-user database:** `userdata/{email}/data.sqlite3`

```sql
-- Canvas configuration
config (
    key PRIMARY KEY,
    value
)

-- Canvas state
canvas (
    key PRIMARY KEY,
    value
)
```

### Drawing Workflow

1. Select drawing tool (hotkey or toolbar)
2. Click/drag on canvas to draw
3. Use selection tool to move/resize
4. Enable snap points for precision
5. Generate code when complete

### Code Output Example

```c
// Generated TFT Code
#include "tft.h"

#define COLOR_RED    0xF800
#define COLOR_GREEN  0x07E0
#define COLOR_BLUE   0x001F

void draw_scene() {
    tft_drawPoint(100, 100, COLOR_RED);
    tft_drawLine(50, 50, 150, 150, COLOR_GREEN);
    tft_drawCircle(200, 200, 50, COLOR_BLUE);
    tft_drawRect(10, 10, 100, 100, COLOR_RED);
    tft_drawText(50, 250, "Hello TFT");
}
```

### Unique Features

- **Touch mode:** Full touch device support
- **Object snapping:** Precision drawing
- **Code generation:** Ready-to-use embedded code
- **Per-user storage:** Isolated projects

---

## 10. Viewer (IoT Dashboard)

**Path:** `apps/viewer/`  
**Main File:** `index.php`

### Overview

A web dashboard for IoT device management with tile-based interface and token-based authentication.

### File Structure

```
apps/viewer/
├── index.php                    # Dashboard interface
├── name.json                    # App name
├── config.json                  # Dashboard configuration
├── history.json                 # View history
├── dashboard_token.txt          # Auth token
├── icon.png                     # App icon
└── Thumbs.db
```

### Features

#### Tile-Based Interface
- Customizable grid layout
- Configurable tile size (default 37px)
- Responsive design
- Configurable tiles per row (default 20)

#### Device Management
- Multiple device endpoints
- Priority ordering
- Hidden device support
- Per-site token usage toggle

#### Registered Devices

| Name | URL | Priority | Token |
|------|-----|----------|-------|
| Doc station | device201.serverkherson.xyz | 5 | Yes |
| Work Table Lamp | device203.serverkherson.xyz | - | Yes |
| Kitchen | device204.serverkherson.xyz | - | Yes |
| Torch | device205.serverkherson.xyz | - | Yes |
| Printer | device241.serverkherson.xyz | - | No |
| Lamp | device202.serverkherson.xyz | - | Yes |

#### Authentication
- Token-based device auth
- Dashboard token in `dashboard_token.txt`
- Per-site token toggle
- Secure communication

#### View History
- Track dashboard access
- History stored in `history.json`
- Timestamp and user tracking

#### Customization
- Theme: Dark/Light
- Language: EN/RU/UK/BE
- Sidebar width (default 367px)
- Header color (default #4d7000)

### Configuration

**config.json:**
```json
{
    "theme": "dark",
    "language": "en",
    "tileSize": 37,
    "sidebarWidth": 367,
    "headerColor": "#4d7000",
    "tilesPerRow": 20,
    "token": "dashboard_secret_token",
    "sites": [
        {
            "name": "Doc station",
            "url": "device201.serverkherson.xyz",
            "priority": 5,
            "hidden": false,
            "use_token": true
        },
        {
            "name": "Work Table Lamp",
            "url": "device203.serverkherson.xyz",
            "priority": 0,
            "hidden": false,
            "use_token": true
        }
    ]
}
```

### Database

- Configuration: `config.json`
- History: `history.json`
- Token: `dashboard_token.txt`
- Shared auth: `PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)`

### Unique Features

- **Priority ordering:** Devices sorted by priority
- **Token flexibility:** Per-site token usage
- **Hidden sites:** Hide devices from view
- **View history:** Access tracking

---

## Common Patterns Across All Apps

### Authentication

All apps share the central authentication system:

```php
session_start();
require_once '../../db.php';

// Check session
if (!isset($_SESSION['user']) || !isset($_SESSION['auth_time'])) {
    // Redirect to login or show limited access
}

// Get user role
$userRole = $_SESSION['user']['role'] ?? 0;
// 0 = Guest, 1 = Manager, 2 = Admin/Root
```

### Database Access Pattern

```php
app_main_db() via lib/app_db.php

function getDB(): ?SQLite3 {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_FILE);
        $db->enableExceptions(true);
        $db->busyTimeout(3000);
    }
    return $db;
}
```

### Per-User Data Pattern

Apps that need per-user storage:

```php
function getUserDBPath($userEmail): string {
    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $userEmail);
    $dir = APPS_DATA_DIR . $safe;
    if (!file_exists($dir)) mkdir($dir, 0755, true);
    return $dir . '/data.sqlite3';
}
```

### Internationalization

All apps support 4 languages:

```php
$lang = $_GET['lang'] ?? 'en';
$translations = [
    'en' => [...],
    'uk' => [...],
    'ru' => [...],
    'be' => [...]
];
$t = $translations[$lang];
```

### Theme Support

```php
$theme = $_GET['theme'] ?? 'light';
// light, dark
```

### Upload Sessions

File uploads use session tracking:

```php
const UPLOAD_SESSIONS_DIR = __DIR__ . '/upload_sessions';

function create_upload_session($target_path, $user) {
    $upload_id = bin2hex(random_bytes(16));
    $session_data = [
        'id' => $upload_id,
        'user' => $user,
        'target_path' => $target_path,
        'created' => time()
    ];
    file_put_contents(
        UPLOAD_SESSIONS_DIR . "/{$upload_id}.json",
        json_encode($session_data)
    );
    return $upload_id;
}
```

---

## Summary Table

| App | LOC | Database | Key Features |
|-----|-----|----------|--------------|
| **cloud** | 15,000+ | SQLite3 (shared) | File management, AI, LaTeX, 3D, OCR |
| **films** | 3,717 | JSON | Video player, ratings, comments |
| **map** | ~500 | Per-user SQLite | Interactive maps, user data |
| **media_viewer** | ~300 | JSON | Albums, view stats, thumbnails |
| **performance** | 1,608 | Cache files | CPU/RAM/Disk/Network monitoring |
| **recipes** | ~400 | JSON | Ingredient matching, timers |
| **spends** | 2,625 | Per-user SQLite | Budget, charts, Excel import |
| **steps** | ~400 | Per-user SQLite | Video analysis, Excel editor |
| **tft-generator** | ~300 | Per-user SQLite | Drawing tools, code generation |
| **viewer** | ~200 | JSON | IoT tiles, token auth |
