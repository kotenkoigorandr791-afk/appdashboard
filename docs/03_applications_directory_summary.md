# Applications Directory Documentation

The `apps/` directory contains 10 sub-applications that form the ServerKherson platform ecosystem.

> **Note:** For comprehensive documentation of each application with full details, API endpoints, database schemas, and configuration options, see **[06_detailed_applications_documentation.md](06_detailed_applications_documentation.md)**.

---

## 1. Cloud Storage Manager (`apps/cloud/`)

**The flagship application** - A comprehensive cloud storage and file management system with advanced editing capabilities.

### Files Overview

#### Core PHP Files

| File | Purpose |
|------|---------|
| `index.php` (1,595 lines) | Main UI shell with theme/language support |
| `api.php` (5,048 lines) | REST API for file operations, user management |
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
| `composer.json` | PHP dependencies (PhpOffice suite) |
| `ai-config.json` | AI configuration settings |
| `templates.json` | Document templates |
| `translation_config.json` | Translation service settings |
| `name.json` | App name translations |

### Frontend Assets (`assets/`)

| File | Purpose |
|------|---------|
| `app.js` | Main application logic |
| `ai-assistant.js` | AI assistant UI interactions |
| `advanced-search.js` | Search functionality |
| `upload.js` | File upload handling with progress |
| `monaco-editor.js` | Monaco code editor integration |
| `styles.css` | Main application styles |
| `i18n/` | Cloud-specific translations |

### 3D Libraries (`js/`)

| File | Purpose |
|------|---------|
| `three.min.js` | Three.js 3D rendering engine |
| `3MFLoader.js` | 3MF file format loader |
| `STLLoader.js` | STL file format loader |
| `OBJLoader.js` | OBJ file format loader |
| `FBXLoader.js` | FBX file format loader |
| `MTLLoader.js` | MTL material loader |
| `OrbitControls.js` | 3D camera controls |
| `dxf-parser.min.js` | DXF file parser |
| `libredwg-web.umd.min.js` | DWG file parser |

### Data Directories

| Directory | Purpose |
|-----------|---------|
| `data/` | User data storage |
| `temp/` | Temporary files |
| `transcriptions/` | Audio transcription results |
| `upload_sessions/` | Upload session tracking |
| `storage/` | User file storage |
| `vendor/` | Cloud-specific PHP dependencies |

### Key Features

1. **File Management**:
   - Upload/download with progress tracking
   - Folder structure with expand/collapse
   - File sharing with tokens
   - Recycle bin functionality
   - Search within files

2. **Editors**:
   - Monaco Editor integration (VS Code editor)
   - CodeMirror for lightweight editing
   - Syntax highlighting for 20+ languages
   - LaTeX compilation
   - Markdown with live preview
   - Photo editing

3. **3D Viewing**:
   - Three.js-based 3D renderer
   - Support for 3MF, STL, OBJ, FBX, DXF, DWG
   - Orbit controls for camera manipulation
   - Material/texture support

4. **AI Integration**:
   - AI assistant chat interface
   - Code generation/help
   - Text analysis
   - LM Studio proxy integration

5. **Transcription**:
   - Audio-to-text conversion
   - Python-based transcription service
   - Editor for reviewing transcriptions

6. **Office Integration**:
   - OnlyOffice document editing
   - PhpWord/PhpSpreadsheet/PhpPresentation
   - DOCX, XLSX, PPTX support

### API Endpoints (`api.php`)

```
GET    /api.php?action=files         - List files
POST   /api.php?action=upload        - Upload file
DELETE /api.php?action=delete        - Delete file/folder
POST   /api.php?action=rename        - Rename file/folder
POST   /api.php?action=move          - Move file/folder
POST   /api.php?action=copy          - Copy file/folder
POST   /api.php?action=create-folder - Create folder
POST   /api.php?action=create-file   - Create file
GET    /api.php?action=share         - Generate share link
POST   /api.php?action=transcribe    - Transcribe audio
POST   /api.php?action=compile       - Compile code
POST   /api.php?action=format        - Format code
```

### Upload Sessions

The cloud app implements resumable uploads with session tracking:
- Sessions stored in `upload_sessions/` directory
- Each session has JSON metadata file
- Supports large file uploads (up to 500MB)
- Session cleanup on completion/timeout

---

## 2. Movie Manager (`apps/films/`)

**Purpose**: Movie collection manager with user comments.

### Files

| File | Purpose |
|------|---------|
| `index.php` (3,717 lines) | Main interface with movie browsing |
| `movies.json` | Movie database |
| `config.json` | App configuration |
| `translations.php` | UI translations |
| `name.json` | App name |
| `background.png` | Background image |
| `icon.png` | App icon |
| `comments/` | User comments (JSON files per movie) |

### Features

- Movie catalog browsing
- Movie details view
- User comments system
- Search and filtering
- Per-user data isolation

---

## 3. Maps Application (`apps/map/`)

**Purpose**: Interactive maps with user file uploads.

### Files

| File | Purpose |
|------|---------|
| `index.php` | Map interface |
| `name.json` | App name translations |
| `icon.png` | App icon |
| `uploads/` | User uploaded map files |
| `user_data/{email}/favorites.json` | Per-user favorite locations |

### Features

- Interactive map display
- User file uploads
- Favorites system
- Per-user data storage

---

## 4. Media Gallery (`apps/media_viewer/`)

**Purpose**: Photo and video gallery with album support.

### Files

| File | Purpose |
|------|---------|
| `index.php` | Main gallery interface |
| `albums.json` | Album definitions |
| `name.json` | App name |
| `translations.json` | UI translations |
| `update_stats.php` | Statistics updater |
| `icon.png` | App icon |
| `data/albums.json` | Runtime album data |
| `data/stats.json` | View statistics |
| `previews/` | Generated thumbnails |
| `install/` | Installation scripts |

### Features

- Album-based organization
- Thumbnail generation
- View statistics tracking
- Photo/video viewing

---

## 5. Server Performance Monitor (`apps/performance/`)

**Purpose**: Real-time server monitoring dashboard.

### Files

| File | Purpose |
|------|---------|
| `index.php` (1,608 lines) | Dashboard UI with metrics display |
| `server_monitor.php` | Metrics API endpoint |
| `name.json` | App name |
| `translations.php` | UI translations |
| `style.css` | Dashboard styles |
| `icon.png` | App icon |
| `modules_cache.json` | Apache modules cache |
| `cache/` | Cached metrics data |

### API Endpoints (`server_monitor.php`)

```
/performance/cpu      - CPU usage metrics
/performance/mem      - Memory usage
/performance/memswap  - Swap memory
/performance/diskio   - Disk I/O statistics
/performance/network  - Network traffic
/performance/uptime   - System uptime
```

### Features

- Real-time CPU monitoring
- Memory and swap usage
- Disk I/O statistics
- Network traffic monitoring
- System uptime display
- Apache modules info
- Cached metrics for performance

---

## 6. Recipe Manager (`apps/recipes/`)

**Purpose**: Recipe collection manager with product database.

### Files

| File | Purpose |
|------|---------|
| `index.php` | Main recipe interface |
| `name.json` | App name |
| `translations.json` | UI translations |
| `styles.php` | Dynamic CSS styles |
| `recipe_state.json` | Recipe state persistence |
| `icon.png` | App icon |
| `dark-background.jpg` | Dark theme background |
| `light-background.jpg` | Light theme background |
| `default_recipe.jpg` | Default recipe image |
| `products/{id}/product.json` | Product database |
| `recipes/` | Recipe data storage |

### Features

- Recipe creation and editing
- Product/ingredient database
- Theme support (light/dark)
- Recipe state persistence
- Image support for recipes

---

## 7. Expense Tracker (`apps/spends/`)

**Purpose**: Personal finance and expense tracking.

### Files

| File | Purpose |
|------|---------|
| `index.php` (2,625 lines) | Main expense tracking interface |
| `name.json` | App name |
| `translations.json` | UI translations |
| `global_settings.json` | Global app settings |
| `icon.png` | App icon |
| `default_profile.jpg` | Default profile image |
| `table_settings.png` | Table settings visual |
| `расходы 2023-2024.xlsx` | Sample Excel import file |
| `backups/` | Data backups |
| `upload_sessions/` | Upload session tracking |
| `utils/` | Utility functions |

### Database (Per-user SQLite)

```sql
settings          -- User settings
stores            -- Store definitions
expenses          -- Expense records (date, store, amount)
table_settings    -- Table display settings
```

### Features

- Expense tracking by date and store
- Excel import support
- Per-user PostgreSQL databases
- Data backups
- Upload session management
- Table customization

---

## 8. Steps Counter (`apps/steps/`)

**Purpose**: Activity and steps tracking with OCR support.

### Files

| File | Purpose |
|------|---------|
| `index.php` | Main activity tracking interface |
| `name.json` | App name |
| `translations.json` | UI translations |
| `icon.png` | App icon |
| `default_profile.jpg` | Default profile image |
| `steps.png` | Steps icon |
| `distance.png` | Distance icon |
| `goal.png` | Goal icon |
| `kkal.png` | Calories icon |
| `upload_sessions/` | Upload session tracking |
| `utils/` | Utilities including Tesseract OCR |
| `utils/bin/` | Tesseract binaries and man pages |

### Features

- Steps tracking
- Distance calculation
- Goal setting
- Calorie tracking
- CSV/JSON data import
- OCR support (Tesseract) for screenshot processing

---

## 9. TFT Display Generator (`apps/tft-generator/`)

**Purpose**: Code generator for TFT displays.

### Files

| File | Purpose |
|------|---------|
| `index.php` | Generator interface |
| `name.json` | App name |
| `style.css` | Generator styles |
| `translations.js` | Client-side translations |
| `icon.png` | App icon |
| `userdata/{email}/data.sqlite3` | Per-user project database |
| `upload_sessions/` | Upload session tracking |

### Features

- TFT display code generation
- Per-user project storage
- SQLite-based project data
- Visual configuration interface

---

## 10. Network Device Viewer (`apps/viewer/`)

**Purpose**: Network device monitoring and viewing.

### Files

| File | Purpose |
|------|---------|
| `index.php` | Device viewer interface |
| `name.json` | App name |
| `config.json` | App configuration |
| `history.json` | View history |
| `icon.png` | App icon |
| `dashboard_token.txt` | Dashboard authentication token |

### Features

- Network device discovery
- Device status monitoring
- View history tracking
- Dashboard integration

---

## Common Patterns Across Apps

### Authentication

All apps use the central authentication system:
```php
session_start();
require_once '../../db.php';
// Check $_SESSION['user'] and $_SESSION['auth_time']
// Validate against active_sessions table
```

### Database Access

Apps share the central database:
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

### Per-User Data

Many apps create per-user PostgreSQL databases:
```php
function getUserDBPath($userEmail) {
    $safe = getSafeFolderName($userEmail);
    $dir  = APPS_DATA_DIR . $safe;
    return $dir . '/data.sqlite3';
}
```

### Internationalization

Apps support 4 languages via URL parameters:
```php
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$theme = isset($_GET['theme']) ? $_GET['theme'] : 'light';
$color = isset($_GET['color']) ? $_GET['color'] : '#85b503';
```

### Upload Sessions

File uploads use session tracking:
```php
const UPLOAD_SESSIONS_DIR = __DIR__ . '/upload_sessions';
function create_upload_session($target_path, $user) {
    $upload_id = bin2hex(random_bytes(16));
    // Store session metadata
}
```
