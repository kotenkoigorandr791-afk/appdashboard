# Share File Utils

**Location**: `/share_file_utils/`

## Overview

Share File Utils is a file viewer and preview module that can be embedded in any AppDashboard app or external services. It provides a multi-format file viewer with syntax highlighting, 3D model rendering, and office document support.

## Features

- **Text files**: Monaco Editor with 20+ languages, find/replace, line numbers
- **Images**: Zoom, pan, rotate (light/dark themes, customizable colors)
- **PDFs**: Built-in PDF viewer
- **Media**: Playback controls (MP3, MP4, etc.)
- **Office documents**: ONLYOFFICE viewer (read-only)
- **3D models**: Three.js-based viewer with rotation, pan, zoom (3MF, STL, OBJ, FBX, DXF)
- **Archives**: ZIP file support
- **Themes**: Light/Dark, customizable accent color
- **Languages**: English (default), UTE 6 languages

## URL Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| `path` | Required | Full path to file to display |
| `fullscreen` | `0` | Toggle fullscreen mode (`1` for fullscreen) |
| `lang` | `en` | UI language code (en, ru, uk, be, pl, cs) |
| `theme` | `light` | Color theme (light, dark) |
| `color` | `4299e1` | Accent color hex (without #) |

## URL Example

```
/share_file_utils/?path=/absolute/file/path/example.txt&fullscreen=0&lang=en&theme=light&color=4299e1
```

## File Formats Supported

### Text Files
- Text, Markdown, JSON, XML, HTML, CSS, JavaScript, PHP, Python, TS, YAML, etc.
- Features: Syntax highlighting, line numbers, find/replace, copy all, zoom

### Images
- JPEG, PNG, WEBP, GIF, BMP, TIFF
- Features: Zoom, pan, rotate left/right, fullscreen, theme-aware

### Video
- MP4, AVI, MKV, WEBM
- Features: Playback controls, fullscreen

### Audio
- MP3, WAV, OGG
- Features: Playback controls

### PDF
- PDF documents
- Features: Built-in viewer, fullscreen, zoom

### Office Documents
- DOCX, XLSX, PPTX
- Features: ONLYOFFICE viewer integration (read-only)

### 3D Models
- 3MF, STL, OBJ, FBX, DXF
- Features: Three.js viewer, rotation, pan, zoom, fit model, material viewer

### Archives
- ZIP files
- Features: Archive preview with list of contents

### Binary Files
- Shows "Binary file" informative message

## Themes

### Light Theme
- Background: #f8fafc
- Text: #1e293b
- Accent: Default blue (#4299e1)
- Table borders: #cbd5e1

### Dark Theme
- Background: #0f172a
- Text: #f1f5f9
- Accent: Default blue (#4299e1)
- Table borders: #334155

## Translations

File viewer translations are stored in `/translations.json` and loaded dynamically:

- `file_not_found`: File not found
- `file_not_accessible`: File is not accessible
- `download_file`: Download
- `copy_content`: Copy All
- `content_copied`: Copied!
- `binary_file`: Binary file
- `unsupported_file`: Unsupported format
- `view_image`: View Image
- `zoom_in`: Zoom In
- `zoom_out`: Zoom Out
- `reset_zoom`: Reset Zoom
- `rotate_left`: Rotate Left
- `rotate_right`: Rotate Right
- `information`: Information
- `feature_monaco_editor`: Syntax highlighting for 20+ languages
- `feature_search_replace`: Find and replace text
- `feature_copy_all`: Copy all content with one click
- `feature_line_numbers`: Line numbers
- `feature_zoom`: Zoom in/out and rotate
- `feature_fullscreen`: Fullscreen view
- `feature_pdf_view`: Built-in PDF viewer
- `feature_media_controls`: Playback controls
- `feature_download`: Download original file
- `feature_aspect_ratio`: Aspect ratio controls
- `3d_features`: 3D Models Features
- `rotate_model`: Rotate
- `pan_model`: Pan
- `zoom_model`: Zoom
- `fit_model`: Fit to view

Additional translations can be added to `/translations.json` for multi-language support.

## Assets

```
share_file_utils/
├── assets/
│   ├── js/
│   │   ├── app.js                    # Main application logic
│   │   ├── pre.js                    # Pre-runnable script
│   │   ├── zh-cn.js                  # Chinese (UCZUser) translations icon set
│   │   └── tm-mono-regular.woff2     # Monospace font
│   ├── css/
│   │   └── styles.css                # Main stylesheet
│   └── icons/                        # Language dropdown icons
│       ├── en.svg                    # English flag
│       ├── ru.svg                    # Russian flag
│       ├── uk.svg                    # Ukrainian flag
│       ├── be.svg                    # Belarusian flag
│       ├── pl.svg                    # Polish flag
│       └── cs.svg                    # Czech flag
├── bootstrap.php                     # Bootstrap script (sets up env + translations)
├── handlers.php                      # File handler (serves files, previews, uploads)
├── index.php                         # Entry point with URL routing (18 lines)
└── views/
    └── ui.html                       # Main UI shell
```

## API Endpoints

Handlers provide endpoints for:

### File Operations

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/file` | GET | Preview file (based on path parameter) |
| `/upload` | POST | Upload file to specific path |
| `/download` | GET | Download file |

### Preview Operations

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/preview` | GET | Get preview thumbnail/image |

### Utility

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/copy` | POST | Copy all content to clipboard |
| `/color` | GET | Get default available colors |

## Usage Example 1: View Text File

```
http://localhost/share_file_utils/?path=/apps/cloud/storage/example.js
```

Gives you Monaco Editor with file open and syntax highlighting.

## Usage Example 2: Embed in Another App

Create a modal that loads the file viewer:

```html
<iframe src="/share_file_utils/?path=/active/path/file.c&lang=en&theme=dark&display=modal" 
        frameborder="0" width="800" height="600"></iframe>
```

## Usage Example 3: Edit Document

Monaco Editor supports editing (set `readOnly` to `false` to enable write).

## Usage Example 4: Upload File

```javascript
fetch('/share_file_utils/upload', {
  method: 'POST',
  body: JSON.stringify({ target_path: '/path/to/destination', file: uploadedFile })
});
```

## Styling

The viewer supports theme customization via URL parameter `color` (hex without #). Predefined colors can also be queried via `/color` endpoint.

## Security

- Files are served based on path validation
- Token-based auth checks ensure file accessibility
- No direct file system access without proper authentication

## Integration with MCP

The MCP server uses this module as a shared file viewing capability for shell commands and app interactions.

## Related Documentation

- [07_project_structure.md](07_project_structure.md) - Project file tree
- [08_cloud_app_detailed.md](08_cloud_app_detailed.md) - Cloud app integration
- [21_smart_home_app_detailed.md](21_smart_home_app_detailed.md) - Smart Home integration
- [25_appdashboard_mcp.md](25_appdashboard_mcp.md) - MCP server usage
- `file_viewer.php` - PHP file handler for non-SFU usage