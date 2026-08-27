# Project Structure - Complete File Tree

**ServerKherson** is a comprehensive PHP-based multi-application web platform. This document provides a complete structural overview of the entire project.

---

## Root Directory Structure

```
/mnt/server/
├── index.php                          # Main entry point (routing, auth, UI shell)
├── db.php                             # Database layer (PostgreSQL connection & functions)
├── db_admin.php                       # Database admin interface with SQL console
├── backup_dashboard.php               # Backup management with AES-256-GCM encryption
├── file_viewer.php                    # File viewing/downloading handler
├── ssh_terminal_handler.php           # SSH terminal WebSocket handler
├── doc_viewer.php                     # Document viewer handler
├── composer.json                      # Root PHP dependencies
├── composer.lock                      # Composer lock file
├── data/db.env                        # PostgreSQL credentials (appdashboard)
├── default_avatar.png                 # Default user avatar image
├── default_icon.png                   # Default application icon
├── google.png                         # Google OAuth icon
├── google7cb515c0a41e4548.html        # Google Search Console verification
├── icon.png                           # Main application icon
├── home.html                          # Static home page
├── privacy.html                       # Privacy policy page
├── privacy-terms.png                  # Privacy terms image
├── .htaccess                          # Apache URL rewriting & security rules
├── client_apps_architecture.md        # Client applications architecture documentation
├── php_errors.log                     # PHP error log file
│
├── docs/                              # Documentation directory
│   ├── 00_documentation_index.md
│   ├── 01_project_overview_and_architecture.md
│   ├── 02_root_level_files_and_core_system.md
│   ├── 03_applications_directory_summary.md
│   ├── 04_internationalization_i18n.md
│   ├── 05_webssh_terminal.md
│   ├── 06_detailed_applications_documentation.md
│   └── 07_project_structure.md        # This file
│
├── apps/                              # 10 sub-applications
│   ├── cloud/                         # Cloud Storage (flagship app, ~15K LOC)
│   ├── films/                         # Movie Manager
│   ├── map/                           # Maps Application
│   ├── media_viewer/                  # Media Gallery
│   ├── performance/                   # Server Monitor
│   ├── recipes/                       # Recipe Manager
│   ├── spends/                        # Expense Tracker
│   ├── steps/                         # Steps Counter with OCR
│   ├── lopaka/                 # TFT Display Code Generator
│   └── viewer/                        # Network Device Viewer
│
├── i18n/                              # Internationalization files
│   ├── en.json                        # English translations
│   ├── uk.json                        # Ukrainian translations
│   ├── ru.json                        # Russian translations
│   └── be.json                        # Belarusian translations
│
├── webssh/                            # SSH terminal WebSocket application
│   ├── server.php                     # WebSocket server
│   ├── index.php                      # Web UI
│   └── composer.json                  # Dependencies
│
├── uploads/                           # User uploads
│   └── avatars/                       # User avatar images
│
└── preview/                           # App description screenshots
```

---

## Applications Directory - Detailed Structure

### 1. Cloud Storage (`apps/cloud/`)

**Size:** ~15,000+ lines of code | **Main Files:** `index.php` (1,650 lines), `api.php` (5,363 lines)

```
apps/cloud/
├── index.php                          # Main UI with theme/language support (1,650 lines)
├── api.php                            # REST API for file operations (5,363 lines)
├── ai.php                             # AI assistant endpoint (534 lines)
├── db.php                             # Cloud-specific database functions
├── compile.php                        # Code compilation service (637 lines)
├── format_code.php                    # Code formatting service
├── de_format_code.php                 # German code formatting
├── remove_comments.php                # Comment removal service
├── save_config.php                    # Configuration saver
├── transcription_api.php              # Audio transcription API (216 lines)
├── transcribe.py                      # Python transcription script
│
├── Editors
│   ├── latex_editor.php               # LaTeX document editor (5,164 lines)
│   ├── markdown_editor.php            # Markdown with live preview (1,567 lines)
│   ├── photo_editor.php               # Photo editing (1,460 lines)
│   ├── onlyoffice_editor.php          # OnlyOffice integration (400 lines)
│   └── transcription_editor.php       # Audio transcription editor
│
├── Viewers
│   ├── 3mf-viewer.php                 # 3D model viewer (344 lines)
│   ├── image_viewer.php               # Image viewer with zoom/pan
│   ├── pdf_viewer_template.php        # PDF viewer template
│   └── zip_viewer.php                 # ZIP archive viewer
│
├── Configuration Files
│   ├── composer.json                  # PHP dependencies (PhpOffice suite)
│   ├── composer.lock                  # Composer lock file
│   ├── ai-config.json                 # AI API configuration
│   ├── templates.json                 # LaTeX document templates
│   ├── translation_config.json        # Translation service settings
│   └── name.json                      # App name translations
│
├── Frontend Assets (assets/)
│   ├── app.js                         # Main application logic
│   ├── ai-assistant.js                # AI assistant UI interactions
│   ├── advanced-search.js             # Advanced search functionality
│   ├── upload.js                      # File upload with progress tracking
│   ├── monaco-editor.js               # Monaco code editor integration
│   ├── properties-modal.js            # File properties modal
│   ├── storage-usage-modal.js         # Storage usage display
│   ├── completions.js                 # Code completion definitions
│   ├── styles.css                     # Main application styles
│   └── i18n/                          # Cloud-specific translations
│       ├── en.json                    # English
│       ├── uk.json                    # Ukrainian
│       ├── ru.json                    # Russian
│       └── be.json                    # Belarusian
│
├── 3D Libraries (js/)
│   ├── three.min.js                   # Three.js 3D rendering engine
│   ├── 3MFLoader.js                   # 3MF file format loader
│   ├── STLLoader.js                   # STL file format loader
│   ├── OBJLoader.js                   # OBJ file format loader
│   ├── FBXLoader.js                   # FBX file format loader
│   ├── MTLLoader.js                   # MTL material loader
│   ├── OrbitControls.js               # 3D camera controls
│   ├── dxf-parser.min.js              # DXF file parser
│   └── libredwg-web.umd.min.js        # DWG file parser
│
├── Data Directories
│   ├── data/                          # User data storage
│   │   └── users.json                 # Cloud user definitions
│   ├── temp/                          # Temporary compilation files
│   ├── temp_convert/                  # File conversion temp files
│   ├── transcriptions/                # Audio transcription results
│   ├── transcription_uploads/         # Uploaded audio files
│   ├── upload_sessions/               # Resumable upload session tracking
│   ├── storage/                       # User file storage (per-user folders)
│   └── vendor/                        # Cloud-specific PHP dependencies
│
├── EXIF Tool (exiftool/)
│   └── exiftool_files/                # ExifTool Perl installation
│       ├── exiftool.pl                # Main ExifTool script
│       ├── lib/                       # Perl libraries
│       │   ├── Archive/Zip.pm         # ZIP archive handling
│       │   ├── Compress/Raw/          # Compression libraries
│       │   ├── CryptX/                # Cryptography
│       │   └── auto/                  # Compiled Perl modules (.xs.dll)
│       └── exiftool                   # ExifTool executable
│
└── Other Files
    ├── icon.png                       # Cloud app icon
    ├── Thumbs.db                      # Windows thumbnail cache
    └── php_errors.log                 # PHP error log
```

---

### 2. Films - Movie Manager (`apps/films/`)

**Size:** ~3,700 lines of code | **Main File:** `index.php` (4,022 lines)

```
apps/films/
├── index.php                          # Main interface with movie browsing (4,022 lines)
├── movies.json                        # Movie database
├── config.json                        # Application configuration
├── translations.php                   # UI translations (PHP array)
├── name.json                          # App name translations
├── background.png                     # Background image
├── icon.png                           # Application icon
├── Thumbs.db                          # Windows thumbnail cache
├── php_errors.log                     # PHP error log
├── QWEN.md                            # AI assistant notes
│
├── comments/                          # User comments (JSON files per movie)
│   └── {movie_id}.json                # Comments for each movie
│
└── .qwen/                             # Qwen Code settings
    ├── settings.json
    └── settings.json.orig
```

---

### 3. Map Application (`apps/map/`)

**Size:** ~400 lines of code | **Main File:** `index.php` (2,627 lines)

```
apps/map/
├── index.php                          # Map interface with user uploads (2,627 lines)
├── name.json                          # App name translations
├── icon.png                           # Application icon
├── php_errors.log                     # PHP error log
│
├── uploads/                           # User uploaded map files
│   └── {user}/                        # Per-user upload folders
│
└── user_data/                         # Per-user data storage
    └── {email}/
        └── favorites.json             # User's favorite locations
```

---

### 4. Media Viewer - Gallery (`apps/media_viewer/`)

**Size:** ~300 lines of code | **Main File:** `index.php` (1,292 lines)

```
apps/media_viewer/
├── index.php                          # Main gallery interface (1,292 lines)
├── albums.json                        # Album definitions
├── name.json                          # App name translations
├── translations.json                  # UI translations (14.6 KB)
├── update_stats.php                   # Statistics updater (547 bytes)
├── icon.png                           # Application icon (63.88 KB)
├── Thumbs.db                          # Windows thumbnail cache
│
├── data/                              # Runtime data
│   ├── albums.json                    # Dynamic album data
│   └── stats.json                     # View statistics
│
├── previews/                          # Generated thumbnails (empty by default)
│
└── install/                           # Installation files (437 MB total)
    ├── app-immich.apk                 # Immich Android app (144 MB)
    ├── ImmichDesktop-1.1.2-Archive.zip # Immich desktop archive (110 MB)
    ├── ImmichDesktop-Portable-1.1.2.exe # Immich portable executable (91 MB)
    └── Immich Desktop Setup 1.1.3.exe # Immich installer (91 MB)
```

---

### 5. Performance - Server Monitor (`apps/performance/`)

**Size:** ~1,600 lines of code | **Main File:** `index.php` (1,848 lines)

```
apps/performance/
├── index.php                          # Dashboard UI with metrics (1,848 lines)
├── server_monitor.php                 # Metrics API endpoint (547 lines)
├── translations.php                   # UI translations (PHP array)
├── name.json                          # App name translations
├── style.css                          # Dashboard styles
├── icon.png                           # Application icon
├── Thumbs.db                          # Windows thumbnail cache
├── modules_cache.json                 # Apache modules cache
│
└── cache/                             # Cached metrics data
    ├── 07ad4185e5908ed773df0b7ec5802894.cache
    ├── 0900d06b05cf35a915bec729b224696c.cache
    ├── 1b75573da9707cf7acf77dc9e004cfea.cache
    ├── 5385df90d0d0ad8955159a5307d34b38.cache
    ├── 953161cceb3838fda554caf6e94a6063.cache
    ├── ec98341c96670c380420d5c98c919bab.cache
    └── modules_cache.json             # Apache modules cache copy
```

---

### 6. Recipes - Recipe Manager (`apps/recipes/`)

**Size:** ~2,740 lines of code | **Main File:** `index.php` (2,740 lines)

```
apps/recipes/
├── index.php                          # Main recipe interface (2,740 lines)
├── styles.php                         # Dynamic CSS styles (42 KB)
├── translations.json                  # UI translations (16 KB)
├── name.json                          # App name translations
├── recipe_state.json                  # Recipe state persistence (68 bytes)
├── icon.png                           # Application icon (89 KB)
├── Thumbs.db                          # Windows thumbnail cache
│
├── Background Images
│   ├── dark-background.jpg            # Dark theme background (128 KB)
│   ├── light-background.jpg           # Light theme background (7 KB)
│   └── default_recipe.jpg             # Default recipe image (8 KB)
│
├── products/                          # Product database (37 products)
│   └── {product_id}/
│       ├── image.{jpg,jpeg,png,webp}  # Product image
│       └── product.json               # Product metadata (~200 bytes)
│
└── recipes/                           # Recipe storage (8 recipes)
    └── {recipe_id}/
        ├── image.{jpg,webp}           # Recipe image (46-267 KB)
        └── recipe.json                # Recipe data (2-9 KB)
```

**Product IDs:** 68c5c4921bcc1, 68c5c8b31dbcb, 68c5c8c38b6c2, 68c5fb208caa4, 68c8929a4524d, 68c892c3586df, 68c892d48b993, 68c892fd9ae9e, 68c8930ccd84f, 68c896b670c49, 68c896c025fa7, 68c896d20e64c, 68c896e103e4b, 68c89c4682300, 68ce678e56cf8, 68cf1d1f4e30f, 68d02a60a79c1, 68d086a9011df, 68d086cf0649f, 68ebf8fb12878, 68ebf93c3137b, 695d3f2da1102, 6992e41655a4d, 6992e43854308, 6992e45d0b602, 6992e5244b9b4, 6992e555e6357, 6992e58bad1d3

**Recipe IDs:** 68c89566beb7b, 68c89c25897d0, 68d0881ca27d3, 68ebfab2ea21f, 695d3e06106e4, 6992e70e67836

---

### 7. Spends - Expense Tracker (`apps/spends/`)

**Size:** ~2,600 lines of code | **Main File:** `index.php` (2,629 lines)

```
apps/spends/
├── index.php                          # Main expense tracking interface (2,629 lines)
├── name.json                          # App name translations
├── translations.json                  # UI translations
├── global_settings.json               # Global application settings
├── icon.png                           # Application icon
├── default_profile.jpg                # Default profile image
├── table_settings.png                 # Table settings visual guide
├── Thumbs.db                          # Windows thumbnail cache
├── php_errors.log                     # PHP error log
├── расходы 2023-2024.xlsx             # Sample Excel import file
│
├── backups/                           # Data backups
│   └── {timestamp}_backup.sqlite3     # Per-user database backups
│
├── upload_sessions/                   # Resumable upload tracking
│   └── {session_id}.json              # Session metadata
│
└── utils/                             # Utility functions
    └── ...                            # Helper scripts
```

**Per-User Database Schema:**
```sql
settings (key TEXT PRIMARY KEY, value TEXT)
stores (id TEXT PRIMARY KEY, name TEXT, visible INTEGER, sort_order INTEGER)
expenses (date TEXT, store TEXT, amount REAL, PRIMARY KEY (date, store))
table_settings (key TEXT PRIMARY KEY, value TEXT)
```

---

### 8. Steps - Activity Tracker (`apps/steps/`)

**Size:** ~3,300 lines of code | **Main File:** `index.php` (3,317 lines)

```
apps/steps/
├── index.php                          # Main activity tracking interface (3,317 lines)
├── name.json                          # App name translations
├── translations.json                  # UI translations
├── icon.png                           # Application icon
├── default_profile.jpg                # Default profile image
├── Thumbs.db                          # Windows thumbnail cache
├── php_errors.log                     # PHP error log
│
├── Icons
│   ├── steps.png                      # Steps counter icon
│   ├── distance.png                   # Distance tracking icon
│   ├── goal.png                       # Goal setting icon
│   └── kkal.png                       # Calories tracking icon
│
├── upload_sessions/                   # Upload session tracking
│   └── {session_id}.json              # Session metadata
│
└── utils/                             # Utility functions including OCR
    └── bin/                           # Tesseract OCR binaries
        ├── tesseract.exe              # Tesseract OCR executable
        └── tessdata/                  # Tesseract language data
```

**Features:**
- Steps tracking with daily goals
- Distance calculation from steps
- Calorie burn estimation
- CSV/JSON data import
- OCR support for screenshot processing (Tesseract)

---

### 9. TFT Generator - Display Code Generator (`apps/lopaka/`)

**Size:** ~300 lines of code | **Main File:** `index.php` (1,616 lines)

```
apps/lopaka/
├── index.php                          # Generator interface (1,616 lines)
├── name.json                          # App name translations
├── style.css                          # Generator styles
├── translations.js                    # Client-side translations
├── icon.png                           # Application icon
├── Thumbs.db                          # Windows thumbnail cache
│
├── userdata/                          # Per-user project storage
│   └── {email}/
│       └── data.sqlite3               # User's project database
│
└── upload_sessions/                   # Upload session tracking
    └── {session_id}.json              # Session metadata
```

**Features:**
- TFT display code generation
- Visual configuration interface
- Per-user project storage in SQLite
- Touch support for mobile devices

---

### 10. Viewer - Network Device Monitor (`apps/viewer/`)

**Size:** ~500 lines of code | **Main File:** `index.php` (1,683 lines)

```
apps/viewer/
├── index.php                          # Device viewer interface (1,683 lines)
├── name.json                          # App name translations
├── config.json                        # Application configuration
├── history.json                       # View history tracking
├── dashboard_token.txt                # Dashboard authentication token
├── icon.png                           # Application icon
└── Thumbs.db                          # Windows thumbnail cache
```

**Features:**
- Network device discovery
- Device status monitoring
- View history tracking
- Dashboard integration with token auth

---

## WebSSH Application (`webssh/`)

```
webssh/
├── server.php                         # WebSocket server for SSH connections
├── index.php                          # Web terminal UI
├── composer.json                      # PHP dependencies (phpseclib, ratchet)
└── composer.lock                      # Composer lock file
```

**Features:**
- Browser-based SSH terminal
- Encrypted credential storage
- WebSocket communication
- Session management

---

## Internationalization Files (`i18n/`)

```
i18n/
├── en.json                            # English translations
├── uk.json                            # Ukrainian translations
├── ru.json                            # Russian translations
└── be.json                            # Belarusian translations
```

**Supported Languages:**
- `en` - English (default)
- `uk` - Ukrainian
- `ru` - Russian
- `be` - Belarusian

**URL Parameters:** `?lang=en&theme=light&color=#007bff`

---

## Database Structure (PostgreSQL appdashboard)

### Main Tables

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `users` | User accounts | email, password_hash, role, avatar, preferences |
| `pending_users` | Awaiting verification | email, otp_code, created_at |
| `active_sessions` | Current sessions | session_id, email, auth_time, expires_at |
| `client_auth_tokens` | API access tokens | token_hash, email, device_id, expires_at |
| `one_time_pins` | OTP codes | email, otp_code, attempts, expires_at |
| `ip_lockouts` | Brute-force protection | ip_address, lockout_until, attempts |
| `config` | App configuration | key, value |
| `visit_stats` | App visit statistics | app_name, email, visit_time |
| `shared_files` | Shared file metadata | share_id, file_name, file_path, expires |
| `user_app_restrictions` | Per-user app access | email, app_name, allowed |
| `cloud_user_settings` | Cloud quotas/sync | email, quota_mb, sync_enabled |
| `cloud_folder_state` | Folder UI state | email, folder_json |
| `cloud_storage_usage_stats` | Storage usage | email, total_bytes, file_count |
| `ai_assistant_chats` | AI chat history | id, user_email, chat_name |

### Database Functions (db.php)

```php
// Core functions
getDB()                              // SQLite connection singleton
getUserFromActiveSessions($sid)      // Get user by session ID
getUserRoleByEmail($email)           // Get user role (0/1/2)
isUserRoot()                         // Check if user is admin
createClientAuthToken($email, ...)   // Create API token
validateClientAuthToken($token)      // Validate API token
generateOTP($email)                  // Generate one-time pin
validateOTP($email, $otp)            // Validate OTP
getConfig($key)                      // Get config value
setConfig($key, $value)              // Set config value
recordVisit($appName)                // Record app visit
getVisitStats()                      // Get visit statistics
```

---

## File Statistics

### Total Files by Application

| Application | PHP Files | JS Files | JSON Files | Total Size |
|-------------|-----------|----------|------------|------------|
| **cloud** | 20+ | 15+ | 10+ | ~50 MB |
| **films** | 3 | 0 | 50+ | ~5 MB |
| **map** | 1 | 0 | 2 | ~1 MB |
| **media_viewer** | 2 | 0 | 4 | ~437 MB |
| **performance** | 3 | 0 | 2 | ~1 MB |
| **recipes** | 3 | 0 | 80+ | ~4.2 MB |
| **spends** | 5+ | 0 | 10+ | ~10 MB |
| **steps** | 3 | 0 | 5+ | ~5 MB |
| **lopaka** | 1 | 1 | 2 | ~1 MB |
| **viewer** | 1 | 0 | 3 | ~1 MB |

### Lines of Code Summary

| Application | PHP LOC | JS LOC | Total LOC |
|-------------|---------|--------|-----------|
| **cloud** | ~10,000 | ~5,000 | ~15,000+ |
| **films** | ~3,700 | ~300 | ~4,000 |
| **spends** | ~2,600 | ~200 | ~2,800 |
| **performance** | ~1,600 | ~400 | ~2,000 |
| **map** | ~400 | ~100 | ~500 |
| **recipes** | ~400 | ~50 | ~450 |
| **steps** | ~400 | ~100 | ~500 |
| **viewer** | ~500 | ~50 | ~550 |
| **lopaka** | ~300 | ~100 | ~400 |
| **media_viewer** | ~300 | ~100 | ~400 |

---

## Security Files

| File | Purpose |
|------|---------|
| `.htaccess` | Apache URL rewriting, security blocks, LM Studio proxy |
| `db.php` | Session validation, IP lockout, OTP validation |
| `ssh_terminal_handler.php` | Encrypted SSH credentials |
| `backup_dashboard.php` | AES-256-GCM encrypted backups |

### .htaccess Rules

```apache
# Block direct access to sensitive files
<FilesMatch "\.(sqlite3|db|sql)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# URL rewriting for clean URLs
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?q=$1 [L,QSA]

# LM Studio AI proxy
ProxyPass /lm-studio http://localhost:1234
ProxyPassReverse /lm-studio http://localhost:1234
```

---

## Environment Variables (Production)

```bash
# SSH credential encryption (used by ssh_terminal_handler.php)
export SSH_CREDENTIALS_CRYPT_KEY="your-32-char-secret-key-here"

# Backup encryption (used by backup_dashboard.php)
export BACKUP_DASHBOARD_CRYPT_KEY="your-32-char-secret-key-here"

# LM Studio AI proxy configuration
export LM_STUDIO_URL="http://localhost:1234"
```

---

## Common Development Patterns

### Authentication Pattern

```php
session_start();
require_once '../../db.php';

// Get current user from session
$currentUser = get_current_user_from_sessions();
$userRole = getUserRoleByEmail($email);

// Check if user is root/admin
if (!isUserRoot()) {
    die('Access denied');
}
```

### Per-User Database Pattern

```php
function getUserDBPath($userEmail) {
    $safe = preg_replace('/[@.]/', '_', $userEmail);
    $dir = APPS_DATA_DIR . $safe;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir . '/data.sqlite3';
}

function getUserSQLite($userEmail) {
    static $cache = [];
    if (isset($cache[$userEmail])) return $cache[$userEmail];
    
    $path = getUserDBPath($userEmail);
    $db = new SQLite3($path);
    $db->exec('PRAGMA journal_mode=WAL');
    $cache[$userEmail] = $db;
    return $db;
}
```

### Upload Session Pattern

```php
const UPLOAD_SESSIONS_DIR = __DIR__ . '/upload_sessions';

function create_upload_session($target_path, $user) {
    $upload_id = bin2hex(random_bytes(16));
    $session_data = [
        'id' => $upload_id,
        'user' => $user,
        'target_path' => $target_path,
        'created' => time(),
        'active' => true
    ];
    file_put_contents(
        UPLOAD_SESSIONS_DIR . '/' . $upload_id . '.json',
        json_encode($session_data, JSON_PRETTY_PRINT)
    );
    return $upload_id;
}
```

### Internationalization Pattern

```php
$lang = $_GET['lang'] ?? 'en';
$theme = $_GET['theme'] ?? 'light';
$color = $_GET['color'] ?? '#85b503';

// Validate inputs
$safeLang = in_array($lang, ['en','ru','uk','be'], true) ? $lang : 'en';
$safeTheme = in_array($theme, ['light','dark'], true) ? $theme : 'light';

// Load translations
$translations = json_decode(
    file_get_contents("i18n/{$safeLang}.json"),
    true
);
```

---

## API Endpoints Summary

### Cloud Storage API (`apps/cloud/api.php`)

```
GET    /api.php?action=files              # List files
POST   /api.php?action=upload             # Upload file
DELETE /api.php?action=delete             # Delete file/folder
POST   /api.php?action=rename             # Rename file/folder
POST   /api.php?action=move               # Move file/folder
POST   /api.php?action=copy               # Copy file/folder
POST   /api.php?action=create-folder      # Create folder
POST   /api.php?action=create-file        # Create file
GET    /api.php?action=share              # Generate share link
POST   /api.php?action=transcribe         # Transcribe audio
POST   /api.php?action=compile            # Compile code
POST   /api.php?action=format             # Format code
```

### Performance Monitor API (`apps/performance/server_monitor.php`)

```
GET /performance/cpu      # CPU usage percentage
GET /performance/mem      # Memory usage percentage
GET /performance/memswap  # Swap memory usage
GET /performance/diskio   # Disk I/O statistics
GET /performance/network  # Network traffic
GET /performance/uptime   # System uptime
```

### AI Assistant API (`apps/cloud/ai.php`)

```
POST /ai.php?action=chat           # Chat with AI
POST /ai.php?action=save_history   # Save chat history
POST /ai.php?action=load_history   # Load chat history
POST /ai.php?action=clear_history  # Clear chat history
```

### Client Auth Token API

```
POST /api/client-auth/             # Create auth token
GET  /api/client-auth/validate     # Validate token
DELETE /api/client-auth/revoke     # Revoke token
```

---

## Third-Party Libraries

### PHP Dependencies (Composer)

| Library | Purpose | Used By |
|---------|---------|---------|
| `phpseclib/phpseclib` | SSH/SFTP encryption | webssh, ssh_terminal_handler |
| `ratchet/pawl` | WebSocket client | webssh |
| `phpoffice/phpword` | Word document processing | cloud |
| `phpoffice/phpspreadsheet` | Excel processing | cloud, spends |
| `phpoffice/phppresentation` | PowerPoint processing | cloud |
| `phpmailer/phpmailer` | Email sending | index.php, db.php |
| `google/apiclient` | Google OAuth | index.php |

### JavaScript Libraries (CDN)

| Library | Purpose | Used By |
|---------|---------|---------|
| `Three.js` | 3D rendering | cloud (3mf-viewer) |
| `Monaco Editor` | Code editor (VS Code) | cloud |
| `CodeMirror` | Lightweight code editor | cloud |
| `Tesseract.js` | OCR in browser | cloud, steps |
| `Plyr` | Media player | cloud |
| `Highlight.js` | Syntax highlighting | cloud |
| `QRCode.js` | QR code generation | cloud |

---

## Deployment Checklist

### Prerequisites

- [ ] PHP 8.0+ with SQLite3 extension
- [ ] Apache with mod_rewrite enabled
- [ ] Composer installed
- [ ] Write permissions for `uploads/`, `apps/*/upload_sessions/`

### Installation Steps

1. Clone repository to `/mnt/server`
2. Run `composer install` in root
3. Run `composer install` in `apps/cloud/`
4. Run `composer install` in `webssh/`
5. Set environment variables:
   ```bash
   export SSH_CREDENTIALS_CRYPT_KEY="your-secret-key"
   export BACKUP_DASHBOARD_CRYPT_KEY="your-secret-key"
   ```
6. Ensure `.htaccess` is active
7. Access via browser and login with `root@root` / `root`

### Optional Components

- [ ] LM Studio running on `localhost:1234` for AI features
- [ ] FFmpeg installed for transcription features
- [ ] Python with whisper for audio transcription
- [ ] Tesseract OCR installed for steps app

---

## Troubleshooting

### Common Issues

| Issue | Possible Cause | Solution |
|-------|---------------|----------|
| Database locked | WAL mode not working | Check file permissions, increase busy timeout |
| Session expires quickly | Cookie settings | Verify `authTimeout` in index.php (12h default) |
| Upload fails | php.ini limits | Increase `upload_max_filesize` and `post_max_size` |
| WebSocket disconnect | Proxy config | Configure WSS proxy for webssh |
| OTP not sending | SMTP config | Verify SMTP settings in Admin Panel |
| 3D viewer blank | CORS issue | Check CDN accessibility |
| AI not responding | LM Studio down | Start LM Studio on port 1234 |

### Log Files

```bash
# PHP errors
tail -f /mnt/server/php_errors.log
tail -f /mnt/server/apps/*/php_errors.log

# Apache errors
tail -f /var/log/apache2/error.log

# SQLite WAL files (if locking issues)
ls -la /mnt/server/*.sqlite3-wal
```

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
