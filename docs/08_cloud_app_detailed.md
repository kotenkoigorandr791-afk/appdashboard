# Cloud Storage Application - Detailed Documentation

**Path:** `apps/cloud/`  
**Total Lines of Code:** ~15,000+  
**Main Entry:** `index.php` (1,650 lines)  
**API Endpoint:** `api.php` (5,363 lines)

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Core Components](#core-components)
4. [Database Schema](#database-schema)
5. [API Reference](#api-reference)
6. [Frontend Architecture](#frontend-architecture)
7. [Features Documentation](#features-documentation)
8. [Configuration](#configuration)
9. [Security](#security)
10. [Third-Party Integrations](#third-party-integrations)

---

## Overview

The Cloud Storage application is the **flagship application** of the ServerKherson platform. It provides a comprehensive file management system with advanced features including:

- **File Management**: Upload, download, organize, share files with folder structure
- **Code Editors**: Monaco Editor (VS Code) and CodeMirror integration
- **3D Viewing**: Three.js-based viewer for 3MF, STL, OBJ, FBX, DXF, DWG formats
- **AI Assistant**: LLM-powered chat assistant for code help and analysis
- **Document Editing**: LaTeX, Markdown, Photo editing, OnlyOffice integration
- **Transcription**: Audio-to-text conversion with Python/whisper
- **OCR**: Tesseract.js optical character recognition

### Key Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 20+ |
| Total JavaScript Files | 15+ |
| Total Configuration Files | 10+ |
| Supported File Formats | 50+ |
| Code Editors | 2 (Monaco, CodeMirror) |
| 3D Formats Supported | 6 (3MF, STL, OBJ, FBX, DXF, DWG) |
| Languages Supported | 4 (EN, UK, RU, BE) |
| Max Upload Size | 500 MB |

---

## File Structure

```
apps/cloud/
├── Core Files
│   ├── index.php                      # Main UI shell (1,650 lines)
│   ├── api.php                        # REST API (5,363 lines)
│   ├── ai.php                         # AI assistant endpoint (534 lines)
│   └── db.php                         # Cloud database functions (443 lines)
│
├── Service Endpoints
│   ├── compile.php                    # Code compilation service (637 lines)
│   ├── format_code.php                # Code formatting service
│   ├── de_format_code.php             # German code formatting
│   ├── remove_comments.php            # Comment removal service
│   ├── save_config.php                # Configuration saver
│   └── transcription_api.php          # Audio transcription API (216 lines)
│
├── Editors
│   ├── latex_editor.php               # LaTeX document editor (5,164 lines)
│   ├── markdown_editor.php            # Markdown with live preview (1,567 lines)
│   ├── photo_editor.php               # Photo editing (1,460 lines)
│   ├── onlyoffice_editor.php          # OnlyOffice integration (400 lines)
│   └── transcription_editor.php       # Audio transcription editor (884 lines)
│
├── Viewers
│   ├── 3mf-viewer.php                 # 3D model viewer (344 lines)
│   ├── image_viewer.php               # Image viewer with zoom/pan (462 lines)
│   ├── pdf_viewer_template.php        # PDF viewer with annotations (1,118 lines)
│   └── zip_viewer.php                 # ZIP archive viewer (1,907 lines)
│
├── Configuration
│   ├── composer.json                  # PHP dependencies
│   ├── ai-config.json                 # AI API configuration
│   ├── templates.json                 # LaTeX document templates
│   ├── translation_config.json        # Translation service settings
│   └── name.json                      # App name translations
│
├── Frontend Assets (assets/)
│   ├── app.js                         # Main application logic
│   ├── ai-assistant.js                # AI assistant UI
│   ├── advanced-search.js             # Advanced search
│   ├── upload.js                      # File upload handling
│   ├── monaco-editor.js               # Monaco integration
│   ├── properties-modal.js            # File properties
│   ├── storage-usage-modal.js         # Storage usage display
│   ├── completions.js                 # Code completion definitions
│   ├── styles.css                     # Main styles
│   └── i18n/                          # Cloud translations
│       ├── en.json
│       ├── uk.json
│       ├── ru.json
│       └── be.json
│
├── 3D Libraries (js/)
│   ├── three.min.js                   # Three.js engine
│   ├── 3MFLoader.js                   # 3MF format loader
│   ├── STLLoader.js                   # STL format loader
│   ├── OBJLoader.js                   # OBJ format loader
│   ├── FBXLoader.js                   # FBX format loader
│   ├── MTLLoader.js                   # MTL material loader
│   ├── OrbitControls.js               # Camera controls
│   ├── dxf-parser.min.js              # DXF parser
│   └── libredwg-web.umd.min.js        # DWG parser
│
├── Data Directories
│   ├── data/                          # User metadata
│   ├── temp/                          # Temporary files
│   ├── transcriptions/                # Transcription results
│   ├── upload_sessions/               # Upload session tracking
│   └── storage/                       # User file storage
│
└── EXIF Tool (exiftool/)
    └── exiftool_files/                # ExifTool installation
```

---

## Core Components

### 1. Main UI Shell (`index.php`)

**Purpose:** Serves as the main entry point for the cloud application, providing the file browser UI with theme and language support.

**Key Features:**
- Theme switching (light/dark)
- Language switching (EN/UK/RU/BE)
- Custom accent color support
- Sidebar navigation
- Search functionality
- Settings menu

**URL Parameters:**
```php
$lang   = $_GET['lang']   ?? 'en';    // en, ru, uk, be
$theme  = $_GET['theme']  ?? 'light'; // light, dark
$color  = $_GET['color']  ?? '#4a90e2'; // Hex color code
```

**Loaded Libraries:**
```html
<!-- CodeMirror -->
cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/

<!-- Monaco Editor -->
cdn.jsdelivr.net/npm/monaco-editor@0.52.2/

<!-- Tesseract.js (OCR) -->
cdn.jsdelivr.net/npm/tesseract.js@6/

<!-- Plyr (Media Player) -->
cdn.jsdelivr.net/npm/plyr@3.7.8/

<!-- Highlight.js -->
cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/

<!-- QRCode.js -->
cdn.jsdelivr.net/npm/qrcodejs@1.0.0/
```

**JavaScript Modules:**
```javascript
assets/app.js                 // Main application logic
assets/ai-assistant.js        // AI chat interface
assets/upload.js              // File upload handling
assets/advanced-search.js     // Search functionality
assets/monaco-editor.js       // Monaco editor integration
assets/properties-modal.js    // File properties
assets/storage-usage-modal.js // Quota display
```

---

### 2. REST API (`api.php`)

**Purpose:** Comprehensive REST API for all file and user operations.

**Authentication:**
- Session-based authentication via `active_sessions` table
- Client auth token support via `X-Client-Auth` header
- User role validation

**Key Functions:**

```php
// Database connection
function getDB(): ?SQLite3

// User authentication
function get_current_user_from_sessions(): string
function get_email_from_client_auth_header(): ?string
function email_to_cloud_username(string $email): string

// Upload sessions
function create_upload_session($target_path, $user): string
function get_upload_session($upload_id): ?array
function update_upload_session($upload_id): void
function delete_upload_session($upload_id): void
function cleanup_old_upload_sessions(): void

// File operations
function listFiles($path, $user): array
function uploadFile($path, $user, $content): bool
function deleteFile($path, $user): bool
function renameFile($oldPath, $newPath, $user): bool
function moveFile($source, $destination, $user): bool
function copyFile($source, $destination, $user): bool
function createFolder($path, $user): bool
function shareFile($path, $user, $expires): string

// User management
function getUsers(): array
function createUser($name, $quota_mb, $session_limit_mb): bool
function updateUser($name, $quota_mb, $session_limit_mb): bool
function deleteUser($name): bool

// Storage management
function getStorageUsage($user): array
function calculateFolderSize($path): int
```

**Request Format:**
```php
// All requests support:
GET    - Query parameters
POST   - JSON body or form data
DELETE - Query parameters
OPTIONS - CORS preflight
```

**Response Format:**
```json
{
    "success": true,
    "data": { ... },
    "error": null
}
```

---

### 3. AI Assistant (`ai.php`)

**Purpose:** AI-powered chat assistant for code help, analysis, and general queries.

**Configuration (`ai-config.json`):**
```json
{
    "api_key": "your-api-key",
    "api_endpoint": "http://localhost:1234/v1",
    "default_model": "meta-llama/Llama-3.3-70B-Instruct",
    "available_models": ["model1", "model2"],
    "system_prompts": {
        "general": "You are a helpful assistant...",
        "programmer": "You are an expert programmer...",
        "analyst": "You are a data analyst..."
    }
}
```

**API Actions:**
```php
POST action=chat              // Chat with AI
POST action=save_history      // Save chat to DB
POST action=load_history      // Load chat history
POST action=clear_history     // Clear chat history
POST action=ai_chat_append    // Append Q/A to DB
POST action=ai_chat_load      // Load paginated chat
POST action=ai_chat_clear     // Clear chat DB
```

**Chat Request Format:**
```json
{
    "action": "chat",
    "model": "meta-llama/Llama-3.3-70B-Instruct",
    "role": "programmer",
    "message": "How do I sort an array in PHP?",
    "file_content": "<?php $arr = [3,1,2]; ?>",
    "file_name": "test.php",
    "additional_files": [
        {"name": "helper.php", "content": "..."}
    ],
    "history": [
        {"role": "user", "content": "..."},
        {"role": "assistant", "content": "..."}
    ]
}
```

**Response Format:**
```json
{
    "response": "To sort an array in PHP, use sort() function...",
    "model": "meta-llama/Llama-3.3-70B-Instruct",
    "usage": {
        "prompt_tokens": 50,
        "completion_tokens": 100,
        "total_tokens": 150
    }
}
```

---

### 4. Database Functions (`db.php`)

**Purpose:** Cloud-specific database operations using PDO.

**Database Class:**
```php
class Database {
    private static $instance = null;
    private $pdo;
    
    // Singleton pattern
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Ensure tables exist
    private function ensureTablesExist() {
        // Creates: active_sessions, users, app_meta,
        // shared_files, cloud_user_settings, cloud_folder_state,
        // cloud_storage_usage_stats, ai_assistant_chats
    }
}
```

**Tables Managed:**
- `active_sessions` - User sessions
- `users` - User accounts
- `cloud_user_settings` - Per-user quotas
- `cloud_folder_state` - Folder UI state
- `cloud_storage_usage_stats` - Storage statistics
- `ai_assistant_chats` - AI chat history
- `shared_files` - Shared file metadata

---

## Database Schema

### Cloud-Specific Tables

#### `cloud_user_settings`
```sql
CREATE TABLE cloud_user_settings (
    email              TEXT PRIMARY KEY,
    quota_mb           INTEGER NOT NULL DEFAULT 500,
    session_limit_mb   INTEGER NOT NULL DEFAULT 0,
    hide_lang_switcher INTEGER NOT NULL DEFAULT 0,
    updated_at         INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    FOREIGN KEY (email) REFERENCES users(email)
        ON DELETE CASCADE ON UPDATE CASCADE
)
```

#### `cloud_folder_state`
```sql
CREATE TABLE cloud_folder_state (
    email       TEXT PRIMARY KEY,
    folder_json TEXT NOT NULL DEFAULT '[]',
    updated_at  INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    FOREIGN KEY (email) REFERENCES users(email)
        ON DELETE CASCADE ON UPDATE CASCADE
)
```

**folder_json Format:**
```json
[
    "/Documents",
    "/Documents/Work",
    "/Photos/2024"
]
```

#### `cloud_storage_usage_stats`
```sql
CREATE TABLE cloud_storage_usage_stats (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    email       TEXT NOT NULL,
    total_bytes INTEGER NOT NULL DEFAULT 0,
    file_count  INTEGER NOT NULL DEFAULT 0,
    folder_count INTEGER NOT NULL DEFAULT 0,
    updated_at  INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    FOREIGN KEY (email) REFERENCES users(email)
        ON DELETE CASCADE ON UPDATE CASCADE
)
```

#### `ai_assistant_chats`
```sql
CREATE TABLE ai_assistant_chats (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_email   TEXT NOT NULL,
    chat_name    TEXT NOT NULL,
    created_at   INTEGER NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at   INTEGER NOT NULL DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (user_email) REFERENCES users(email)
        ON DELETE CASCADE ON UPDATE CASCADE
)
```

**Chat Messages Table:**
```sql
CREATE TABLE ai_chat_messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    chat_id    INTEGER NOT NULL,
    role       TEXT NOT NULL, -- 'user' or 'assistant'
    content    TEXT NOT NULL,
    model      TEXT,
    tokens     INTEGER,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
    FOREIGN KEY (chat_id) REFERENCES ai_assistant_chats(id)
        ON DELETE CASCADE ON UPDATE CASCADE
)
```

---

## API Reference

### User Management Endpoints

#### GET Users
```http
GET /api.php?action=get_users
Authorization: Bearer {token}

Response:
{
    "success": true,
    "users": [
        {
            "name": "user_name",
            "quota_mb": 500,
            "session_limit_mb": 0,
            "storage_used_mb": 123.45
        }
    ]
}
```

#### Create User
```http
POST /api.php?action=create_user
Content-Type: application/json

{
    "name": "new_user",
    "quota_mb": 500,
    "session_limit_mb": 0
}

Response:
{
    "success": true,
    "message": "User created successfully"
}
```

#### Update User
```http
PUT /api.php?action=update_user
Content-Type: application/json

{
    "name": "user_name",
    "quota_mb": 1000,
    "session_limit_mb": 100
}
```

#### Delete User
```http
DELETE /api.php?action=delete_user&name=user_name
```

---

### File Operations Endpoints

#### List Files
```http
GET /api.php?action=files&path=/Documents
Authorization: Bearer {token}

Response:
{
    "success": true,
    "files": [
        {
            "name": "report.pdf",
            "path": "/Documents/report.pdf",
            "type": "file",
            "size": 1048576,
            "modified": "2024-01-15T10:30:00Z"
        },
        {
            "name": "Photos",
            "path": "/Documents/Photos",
            "type": "folder",
            "modified": "2024-01-14T08:00:00Z"
        }
    ]
}
```

#### Upload File
```http
POST /api.php?action=upload
Content-Type: multipart/form-data

file: {binary}
path: /Documents/
filename: report.pdf

Response:
{
    "success": true,
    "path": "/Documents/report.pdf",
    "size": 1048576
}
```

**Resumable Upload:**
```http
POST /api.php?action=upload
Content-Type: application/json

{
    "upload_id": "abc123...",
    "path": "/Documents/large_file.zip",
    "chunk": "base64_encoded_chunk",
    "chunk_index": 5,
    "total_chunks": 10,
    "chunk_size": 5242880
}
```

#### Delete File/Folder
```http
DELETE /api.php?action=delete&path=/Documents/old_file.txt
```

#### Rename File/Folder
```http
POST /api.php?action=rename
Content-Type: application/json

{
    "old_path": "/Documents/old_name.txt",
    "new_path": "/Documents/new_name.txt"
}
```

#### Move File/Folder
```http
POST /api.php?action=move
Content-Type: application/json

{
    "source": "/Documents/file.txt",
    "destination": "/Archive/file.txt"
}
```

#### Copy File/Folder
```http
POST /api.php?action=copy
Content-Type: application/json

{
    "source": "/Documents/file.txt",
    "destination": "/Backup/file.txt"
}
```

#### Create Folder
```http
POST /api.php?action=create-folder
Content-Type: application/json

{
    "path": "/Documents/New Folder"
}
```

#### Share File
```http
POST /api.php?action=share
Content-Type: application/json

{
    "path": "/Documents/report.pdf",
    "expires": "2024-12-31T23:59:59Z"
}

Response:
{
    "success": true,
    "share_id": "abc123xyz",
    "share_url": "/file_viewer.php?token=abc123xyz"
}
```

---

### Service Endpoints

#### Transcribe Audio
```http
POST /api.php?action=transcribe
Content-Type: application/json

{
    "audio_path": "/Audio/meeting.mp3",
    "language": "en"
}

Response:
{
    "success": true,
    "transcription_path": "/transcriptions/meeting_transcription.txt"
}
```

#### Compile Code
```http
POST /api.php?action=compile
Content-Type: application/json

{
    "code": "print('Hello World')",
    "language": "python",
    "input": "",
    "version": "3.9"
}

Response:
{
    "success": true,
    "output": "Hello World\n",
    "errors": "",
    "execution_time": 0.023
}
```

#### Format Code
```http
POST /api.php?action=format
Content-Type: application/json

{
    "code": "function test( ){console.log('test')}",
    "language": "javascript"
}

Response:
{
    "success": true,
    "formatted": "function test() {\n    console.log('test');\n}"
}
```

---

### Storage Management Endpoints

#### Get Storage Usage
```http
GET /api.php?action=storage-usage

Response:
{
    "success": true,
    "usage": {
        "total_bytes": 524288000,
        "quota_bytes": 524288000,
        "percent_used": 45.5,
        "file_count": 1234,
        "folder_count": 56
    }
}
```

#### Get Folder State
```http
GET /api.php?action=get-folder-state

Response:
{
    "success": true,
    "expanded": ["/Documents", "/Documents/Work"]
}
```

#### Save Folder State
```http
POST /api.php?action=save-folder-state
Content-Type: application/json

{
    "expanded": ["/Documents", "/Documents/Work", "/Photos"]
}
```

---

## Frontend Architecture

### Main Application (`assets/app.js`)

**Core Modules:**

```javascript
// File Browser
class FileBrowser {
    constructor() {
        this.currentPath = '/';
        this.selectedFiles = [];
        this.folderState = [];
    }
    
    async loadFolder(path) { ... }
    async deleteFile(path) { ... }
    async renameFile(oldPath, newPath) { ... }
    async createFolder(name) { ... }
    toggleFolder(path) { ... }
}

// Context Menu
class ContextMenu {
    constructor() {
        this.menu = document.getElementById('context-menu');
    }
    
    show(x, y, items) { ... }
    hide() { ... }
}

// Search
class SearchManager {
    constructor() {
        this.searchTimeout = null;
    }
    
    async search(query, filters) { ... }
    highlightResults() { ... }
}

// Theme Manager
class ThemeManager {
    setTheme(theme) { ... }
    setColor(color) { ... }
    persist() { ... }
}
```

### Upload Handler (`assets/upload.js`)

**Features:**
- Drag & drop support
- Progress tracking
- Resumable uploads
- Concurrent uploads (max 3)
- File type validation

```javascript
class UploadManager {
    constructor() {
        this.queue = [];
        this.active = 0;
        this.maxConcurrent = 3;
    }
    
    async upload(file, path) {
        const uploadId = await this.createSession(file, path);
        const chunks = this.chunkFile(file);
        
        for (const chunk of chunks) {
            await this.uploadChunk(uploadId, chunk);
        }
        
        await this.finalize(uploadId);
    }
    
    async uploadChunk(uploadId, chunk) {
        const formData = new FormData();
        formData.append('upload_id', uploadId);
        formData.append('chunk', chunk.blob);
        formData.append('chunk_index', chunk.index);
        
        const response = await fetch('api.php?action=upload', {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }
}
```

### Monaco Editor Integration (`assets/monaco-editor.js`)

**Features:**
- AMD module loading
- Syntax highlighting for 20+ languages
- Auto-completion
- Code folding
- Minimap
- Multi-cursor editing

```javascript
require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs' }});

require(['vs/editor/editor.main'], function() {
    const editor = monaco.editor.create(document.getElementById('editor'), {
        value: '',
        language: 'javascript',
        theme: 'vs-dark',
        automaticLayout: true,
        minimap: { enabled: true },
        fontSize: 14,
        wordWrap: 'on',
        autoClosingBrackets: 'always',
        formatOnPaste: true,
        suggestOnTriggerCharacters: true,
        quickSuggestions: true
    });
});
```

### AI Assistant (`assets/ai-assistant.js`)

**Features:**
- Chat interface
- Message history
- File context attachment
- Model selection
- Role selection (general/programmer/analyst)

```javascript
class AIAssistant {
    constructor() {
        this.chatId = null;
        this.history = [];
        this.model = 'meta-llama/Llama-3.3-70B-Instruct';
        this.role = 'general';
    }
    
    async sendMessage(message, fileContext = null) {
        const response = await fetch('ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'chat',
                model: this.model,
                role: this.role,
                message: message,
                file_content: fileContext?.content,
                file_name: fileContext?.name,
                history: this.history
            })
        });
        
        const data = await response.json();
        this.history.push({ role: 'user', content: message });
        this.history.push({ role: 'assistant', content: data.response });
        
        return data.response;
    }
}
```

---

## Features Documentation

### 1. File Management

#### Folder Structure
- Tree-view with expand/collapse
- State persistence in `cloud_folder_state` table
- Breadcrumb navigation
- Drag & drop reordering

#### File Operations
- **Upload**: Drag & drop or click to browse
- **Download**: Single or bulk download as ZIP
- **Delete**: Move to recycle bin (soft delete)
- **Rename**: Inline editing with validation
- **Move/Copy**: Drag & drop or context menu
- **Share**: Generate time-limited share links

#### Search
- Full-text search in file names
- Filter by type (file/folder)
- Filter by date range
- Filter by size range
- Advanced search modal

---

### 2. Code Editors

#### Monaco Editor
**Supported Languages:**
- JavaScript, TypeScript
- HTML, CSS, SCSS
- PHP, Python, Ruby
- Java, C, C++, C#
- Go, Rust, Swift, Kotlin
- SQL, YAML, JSON, XML
- Shell, PowerShell, Perl
- Lua, R, TOML, Vue

**Features:**
- IntelliSense auto-completion
- Syntax validation
- Code folding
- Minimap navigation
- Multi-cursor editing
- Find/Replace with regex
- Go to definition
- Peek references

#### CodeMirror
**Used For:**
- Lightweight editing
- Quick edits
- Embedded editors

**Modes Loaded:**
```javascript
javascript, xml, css, htmlmixed, php, python
sql, yaml, shell, clike, ruby, go, swift
kotlin, lua, perl, r, toml, vue, octave
```

---

### 3. 3D Viewing

#### Supported Formats

| Format | Extension | Loader |
|--------|-----------|--------|
| 3MF | .3mf | ThreeMFLoader |
| STL | .stl | STLLoader |
| OBJ | .obj | OBJLoader + MTLLoader |
| FBX | .fbx | FBXLoader |
| DXF | .dxf | DXFParser |
| DWG | .dwg | LibreDWG |

#### Viewer Features
- Orbit camera controls
- Pan/zoom
- Full-screen mode
- Wireframe toggle
- Material editing
- Lighting adjustment
- Export to PNG

#### Usage Example
```javascript
import * as THREE from 'three';
import { ThreeMFLoader } from 'three/addons/loaders/3MFLoader.js';

const loader = new ThreeMFLoader();
loader.load('api.php?action=raw&path=/model.3mf', function(object) {
    scene.add(object);
});
```

---

### 4. AI Assistant

#### Models
- **Default**: `meta-llama/Llama-3.3-70B-Instruct`
- **Alternative**: `qwen2.5-coder-1.5b-instruct` (for code)

#### Roles
- **General**: General assistant for any queries
- **Programmer**: Code generation, debugging, explanations
- **Analyst**: Data analysis, reports, insights

#### Features
- Context-aware conversations
- File attachment support
- Chat history persistence
- Model switching
- Token usage tracking

---

### 5. Document Editing

#### LaTeX Editor
**Templates:**
- Empty document
- Lab report
- Technical report
- Math paper
- Course work

**Features:**
- Live preview
- Syntax highlighting
- Template insertion
- Compilation to PDF
- Equation support

#### Markdown Editor
**Features:**
- Live preview with highlight.js
- Table of contents generation
- Image embedding
- Code block syntax highlighting
- Export to HTML/PDF

#### Photo Editor
**Tools:**
- Crop
- Rotate (left/right)
- Draw (brush, color, width)
- Text overlay
- Emoji insertion
- Filters (brightness, contrast, saturation, hue, blur)
- Shapes (line, rectangle, circle, arrow)
- Undo/Redo
- Zoom/Pan

---

### 6. Transcription & OCR

#### Audio Transcription
**Process:**
1. Upload audio file (MP3, WAV, M4A, etc.)
2. Select language (Polish, English, etc.)
3. Python script processes with whisper
4. Result saved to `transcriptions/` folder
5. Editor for review and correction

**Supported Languages:**
- Polish (pl)
- English (en)
- Ukrainian (uk)
- Russian (ru)
- Belarusian (be)
- German (de)
- French (fr)
- Spanish (es)

#### OCR (Tesseract.js)
**Features:**
- Browser-based OCR
- 100+ languages supported
- PDF text extraction
- Image to text conversion

---

### 7. OnlyOffice Integration

**Supported Formats:**
- **Word**: DOC, DOCX, ODT, RTF, TXT
- **Excel**: XLS, XLSX, ODS, CSV
- **PowerPoint**: PPT, PPTX, ODP

**Configuration:**
```php
$DOC_SERV_URL = 'https://document.serverkherson.xyz';
$JWT_SECRET = 'uZbUAlqEAONtLYmVrZVNjuqOefiQZR';
```

**Callback Handler:**
```php
if (isset($_GET['callback'])) {
    $body = json_decode(file_get_contents('php://input'), true);
    
    if ($body['status'] === 2) {
        // Document saved, download new version
        $content = file_get_contents($body['url']);
        file_put_contents($fullPath, $content);
    }
}
```

---

## Configuration

### AI Configuration (`ai-config.json`)

```json
{
    "api_key": "your-api-key-here",
    "api_endpoint": "http://localhost:1234/v1",
    "default_model": "meta-llama/Llama-3.3-70B-Instruct",
    "available_models": [
        "meta-llama/Llama-3.3-70B-Instruct",
        "qwen2.5-coder-1.5b-instruct"
    ],
    "system_prompts": {
        "general": "You are a helpful assistant. Provide clear, concise responses.",
        "programmer": "You are an expert programmer. Provide code solutions.",
        "analyst": "You are a data analyst. Use tables for structured data."
    },
    "max_tokens": 4096,
    "temperature": 0.7,
    "timeout": 60000
}
```

### Translation Configuration (`translation_config.json`)

```json
{
    "api_url": "http://localhost:1234/v1",
    "model": "qwen2.5-coder-1.5b-instruct"
}
```

### LaTeX Templates (`templates.json`)

```json
{
    "templates": [
        {
            "id": "empty",
            "name": "Empty Document",
            "content": "\\documentclass{article}\n\\begin{document}\n\n\\end{document}"
        },
        {
            "id": "lab_report",
            "name": "Lab Report",
            "content": "\\documentclass{article}\n\\title{Lab Report}\n\\begin{document}\n\\maketitle\n\\section{Introduction}\n\\section{Methods}\n\\section{Results}\n\\section{Discussion}\n\\end{document}"
        }
    ]
}
```

---

## Security

### Authentication

**Session Validation:**
```php
session_start();
if (!isset($_SESSION['user']['email'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Not authenticated']));
}

// Validate against database
$sessRow = getUserFromActiveSessions(session_id());
if (!$sessRow || $sessRow['email'] !== $_SESSION['user']['email']) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Invalid session']));
}
```

**Client Auth Token:**
```php
function get_email_from_client_auth_header(): ?string {
    $raw = $_SERVER['HTTP_X_CLIENT_AUTH'] ?? '';
    if ($raw === '') return null;
    
    $db = getDB();
    $stmt = $db->prepare('SELECT email FROM client_auth_tokens 
                          WHERE token_hash = :h AND revoked = 0 AND expires_at > :now');
    $stmt->bindValue(':h', hash('sha256', $raw));
    $stmt->bindValue(':now', time());
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    return $row ? $row['email'] : null;
}
```

### File Access Control

**Path Validation:**
```php
function validatePath($path, $user) {
    // Prevent directory traversal
    $path = str_replace('..', '', $path);
    $path = str_replace('\\', '/', $path);
    
    // Ensure path starts with user's storage
    $basePath = "storage/{$user}/";
    $fullPath = $basePath . ltrim($path, '/');
    
    // Resolve real path and verify it's within user's folder
    $realPath = realpath($fullPath);
    $realBase = realpath($basePath);
    
    if ($realPath === false || strpos($realPath, $realBase) !== 0) {
        throw new Exception('Invalid path');
    }
    
    return $fullPath;
}
```

### Upload Security

**Blocked Extensions:**
```php
$blockedExtensions = [
    'phar', 'phtml', 'cgi', 'pl', 'exe', 'sh', 'bat', 'cmd',
    'com', 'dll', 'jsp', 'asp', 'aspx', 'war'
];

function isExtensionAllowed($filename) {
    global $blockedExtensions;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return !in_array($ext, $blockedExtensions);
}
```

**Size Limits:**
```php
const MAX_UPLOAD_BYTES = 500 * 1024 * 1024; // 500MB hard cap

function checkQuota($user, $additionalBytes) {
    $usage = getStorageUsage($user);
    $quota = getUserQuota($user);
    
    if ($usage['total_bytes'] + $additionalBytes > $quota) {
        throw new Exception('Quota exceeded');
    }
}
```

---

## Third-Party Integrations

### PHP Dependencies (Composer)

```json
{
    "require": {
        "phpoffice/phpword": "^1.1",
        "phpoffice/phpspreadsheet": "^1.29",
        "phpoffice/phppresentation": "^1.0"
    }
}
```

**Installed Packages:**
- `phpoffice/phpword` - Word document processing
- `phpoffice/phpspreadsheet` - Excel spreadsheet processing
- `phpoffice/phppresentation` - PowerPoint presentation processing

### JavaScript Libraries (CDN)

| Library | Version | Purpose |
|---------|---------|---------|
| Three.js | 0.170.0 | 3D rendering |
| Monaco Editor | 0.52.2 | Code editor |
| CodeMirror | 5.65.16 | Lightweight editor |
| Tesseract.js | 6.x | OCR |
| Plyr | 3.7.8 | Media player |
| Highlight.js | 11.9.0 | Syntax highlighting |
| QRCode.js | 1.0.0 | QR code generation |

### EXIF Tool

**Location:** `apps/cloud/exiftool/exiftool_files/`

**Usage:**
```php
function getExifData($imagePath) {
    $exiftool = __DIR__ . '/exiftool/exiftool_files/exiftool';
    $command = escapeshellcmd($exiftool) . ' -json ' . escapeshellarg($imagePath);
    $output = shell_exec($command);
    return json_decode($output, true);
}
```

**Supported Metadata:**
- Camera settings (ISO, aperture, shutter speed)
- GPS coordinates
- Date/time
- Image dimensions
- File information

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Upload fails at 50% | Chunk size too large | Reduce chunk size in upload.js |
| 3D viewer blank | CORS error | Check CDN accessibility |
| AI not responding | LM Studio not running | Start LM Studio on port 1234 |
| Monaco not loading | AMD conflict | Ensure Tesseract loads before Monaco |
| Transcription fails | FFmpeg not found | Install FFmpeg at configured path |
| OnlyOffice callback fails | JWT mismatch | Verify JWT_SECRET matches server |

### Debug Mode

**Enable Debug Logging:**
```php
// In api.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
```

**Check Logs:**
```bash
tail -f /mnt/server/apps/cloud/php_errors.log
```

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
