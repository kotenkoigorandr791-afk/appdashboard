# Cloud Storage Application - Detailed Documentation

**Path:** `apps/cloud/`  
**Type:** Full Application (Flagship)  
**Description:** Comprehensive cloud storage and file management system with advanced editing capabilities, AI integration, and 3D viewing.

---

## Overview

The Cloud Storage application is the flagship application of the ServerKherson platform. It provides a comprehensive file management system with advanced features including:

- File Management: Upload, download, organize, share files with folder structure
- Code Editors: Monaco Editor (VS Code) and CodeMirror integration
- 3D Viewing: Three.js-based viewer for 3MF, STL, OBJ, FBX, DXF, DWG formats
- AI Assistant: LLM-powered chat assistant for code help and analysis
- Document Editing: LaTeX, Markdown, Photo editing, OnlyOffice integration
- Transcription: Audio-to-text conversion with Python/whisper
- OCR: Tesseract.js optical character recognition
- WebDAV: WebDAV server for remote file access

---

## File Structure

```
apps/cloud/
├── index.php                     # Main UI shell
├── api.php                       # REST API (file operations, user management)
├── ai.php                        # AI assistant integration endpoint
├── db.php                        # Cloud-specific database functions
├── compile.php                   # Code compilation service
├── format_code.php               # Code formatting service
├── onlyoffice_editor.php         # OnlyOffice document integration
├── 3mf-viewer.php               # 3D model viewer (3MF, STL, OBJ, FBX, DXF)
├── image_viewer.php             # Image viewing with zoom/pan
├── latex_editor.php             # LaTeX document editor
├── markdown_editor.php          # Markdown editor with live preview
├── photo_editor.php             # Photo editing capabilities
├── transcription_editor.php     # Audio transcription editor
├── zip_viewer.php              # ZIP archive viewer
├── webdav.php                  # WebDAV server endpoint
├── api/actions/                # API action handlers
├── assets/                     # JavaScript and CSS assets
├── i18n/                       # Translation files (6 languages)
├── includes/                   # Shared PHP includes
├── js/                         # JavaScript modules
├── storage/                    # User file storage (per-user directories)
├── temp/                       # Temporary files
├── temp_convert/               # File conversion workspace
├── transcription_uploads/      # Audio files for transcription
├── tts_output/                 # Text-to-speech output
└── templates.json              # File templates
```

## Key Features

### File Management
- Upload/download files and folders
- Create, rename, delete, move items
- Share files via public links or user-specific access
- File versioning and recycle bin
- Search and filter capabilities
- File properties and metadata viewing

### Code Editors
- **Monaco Editor**: VS Code-based editor with syntax highlighting, IntelliSense, themes
- **CodeMaker**: Lightweight editor for quick file edits
- Support for 100+ programming languages
- Real-time collaboration (via web sockets)

### 3D Viewing
- Three.js-powered 3D model viewer
- Supported formats: 3MF, STL, OBJ, FBX, DXF, DWG
- Interactive controls (rotate, zoom, pan)
- Material and lighting adjustments
- Cross-section and measurement tools

### AI Assistant
- Integrated chat interface with LLM (via LM Studio proxy)
- Code assistance: explanation, generation, debugging
- File analysis: summarize, translate, extract information
- Context-aware help based on current file

### Document Editing
- **LaTeX Editor**: Real-time preview, formula assistance
- **Markdown Editor**: Live preview, syntax highlighting, export options
- **Photo Editor**: Basic image editing (crop, resize, filters)
- **OnlyOffice Integration**: Full office suite compatibility
- **PDF Viewer**: Navigation, search, annotation

### Transcription & OCR
- **Audio Transcription**: Speech-to-text using Whisper.cpp
- **Video Transcription**: Extract audio and transcribe
- **OCR**: Text extraction from images using Tesseract.js
- Language support for multiple languages

### Configuration
- Per-user settings: theme, language, notifications
- Storage quotas and usage statistics
- API key management for external services
- Webhook configuration for integrations

## API Endpoints

The cloud application provides a comprehensive REST API under `/api/` (internally) and `/public-api/cloud/` (externally):

### File Operations
- `GET /api/files` - List files and folders
- `POST /api/files/upload` - Upload file
- `GET /api/files/{id}/download` - Download file
- `DELETE /api/files/{id}` - Delete file
- `POST /api/files/{id}/share` - Create share link
- `GET /api/files/{id}/properties` - Get file properties

### User Management
- `GET /api/user/profile` - Get user profile
- `PUT /api/user/profile` - Update user profile
- `GET /api/user/storage` - Get storage usage

### AI Operations
- `POST /api/ai/chat` - Send message to AI assistant
- `POST /api/ai/analyze` - Analyze file content
- `POST /api/ai/transcribe` - Transcribe audio file

## Security Features

- End-to-end encryption for shared files (optional)
- Virus scanning for uploaded files (ClamAV integration)
- Rate limiting on API endpoints
- Input validation and sanitization
- CSRF protection on all forms
- Secure file permissions (chmod, ownership)
- Audit logging for file operations

## Performance

- Chunked file upload/download for large files
- Client-side compression for text assets
- Server-side caching for frequently accessed files
- Database indexing for fast file lookups
- CDN-ready static asset serving

## Internationalization

Supports all 6 interface languages (en, uk, ru, be, pl, cs) with dynamic language switching.

## Dependencies

### PHP
- guzzlehttp/guzzle: HTTP client
- phpseclib/phpseclib: SFTP, encryption
- endroid/qr-code: QR code generation
- spatie/laravel-flysystem: File system abstraction

### JavaScript
- monaco-editor: VS Code editor component
- codemirror: In-browser code editor
- three.js: 3D rendering
- tesseract.js: OCR processing
- whisper.cpp WASM: Speech-to-text
- filepond: File upload handling
- dropzone.js: Drag-and-drop uploads

## Deployment Notes

- Requires sufficient storage volume mounted at `apps/cloud/storage/`
- Temporary directory `apps/cloud/temp/` needs write permissions
- FFmpeg required for video thumbnail generation
- Python 3.8+ required for transcription service
- Redis recommended for caching and session storage
