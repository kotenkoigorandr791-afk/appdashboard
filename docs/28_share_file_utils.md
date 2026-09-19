# Share File Utils Documentation

**Location:** `share_file_utils/`  
**Type:** File Viewer / Preview Module  
**Description:** PHP-based file viewer and preview module that can be embedded in AppDashboard apps or external services.

---

## Overview

Share File Utils is a standalone PHP module that provides a multi-format file viewer with syntax highlighting, 3D model rendering, and office document support. It can be accessed independently or embedded via iframes.

---

## File Structure

```
share_file_utils/
├── index.php                     # Main entry point (lightweight)
├── bootstrap.php                  # Core initialization (constants, config)
├── handlers.php                   # Request handlers
├── lib/                         # Shared PHP libraries
│   └── include.php              # Helper functions
├── views/                         # Template views
│   └── main.php                 # Viewer page template
└── assets/                        # Static assets
    ├── backgrounds/             # Theme background images
    └── ...                      # CSS/JS assets
```

## Key Features

### Supported File Formats

| Category | Supported Formats |
|----------|------------------|
| **Text** | Text, Markdown, JSON, XML, HTML, CSS, JavaScript, PHP, Python, TypeScript, YAML |
| **Images** | JPEG, PNG, WEBP, GIF, BMP, TIFF |
| **Video** | MP4, AVI, MKV, WEBM |
| **Audio** | MP3, WAV, OGG |
| **PDF** | PDF documents |
| **3D Models** | 3MF, STL, OBJ, FBX, DXF |
| **Archives** | ZIP |

### Viewer Features

- **Text files**: Monaco Editor with syntax highlighting for 20+ languages, find/replace, line numbers
- **Images**: Zoom, pan, rotate (light/dark themes, customizable colors)
- **PDFs**: Built-in PDF viewer with navigation
- **Media**: Playback controls (MP3, MP4, etc.)
- **Office documents**: ONLYOFFICE viewer (read-only)
- **3D models**: Three.js-based viewer with rotation, pan, zoom
- **Archives**: ZIP file support with preview
- **Themes**: Light/Dark mode
- **Languages**: 6 interface languages (en, ru, uk, be, pl, cs)

## URL Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| `path` | Required | Full path to file to display |
| `fullscreen` | `0` | Toggle fullscreen mode (`1` for fullscreen) |
| `lang` | `en` | UI language code (en, ru, uk, be, pl, cs) |
| `theme` | `light` | Color theme (light, dark) |
| `color` | `4299e1` | Accent color hex (without #) |

### URL Example

```
/share_file_utils/?path=/absolute/file/path/example.txt&fullscreen=0&lang=en&theme=light&color=4299e1
```

## API Endpoints (Internal)

The module provides additional endpoints via `handlers.php`:

### File Operations
- `GET /share_file_utils` - Main viewer page
- `GET /share_file_utils?path=...` - Display specific file
- `POST /share_file_utils` - Upload new file
- `DELETE /share_file_utils?path=...` - Delete file

### Preview
- `GET /share_file_utils?path=...&size=thumbnail` - Get thumbnail

### File Management
- `GET /share_file_utils?action=list` - List files in directory
- `POST /share_file_utils` - Create new directory

## Security Features

- **Path validation**: Prevents directory traversal attacks
- **File type whitelist**: Only allowed formats are served
- **Access controls**: User authentication required for write operations
- **Rate limiting**: Prevents abuse of file operations
- **Secure defaults**: Safe MIME type handling

## Internationalization

Supports 6 interface languages (en, ru, uk, be, pl, cs) with dynamic language switching.

## Dependencies

### PHP
- GD extension for image processing
- JSON support
- Fileinfo for MIME type detection

### JavaScript
- Monaco Editor for text syntax highlighting
- Three.js for 3D model rendering
- FileSaver.js for downloads

## Deployment

### Standalone

Place `share_file_utils/` in a web-accessible directory and ensure:

```bash
chmod -R 755 share_file_utils/
chmod 644 share_file_utils/*.php
```

### Embedded in AppDashboard

The module can be embedded via iframe in any AppDashboard app:

```html
<iframe src="/share_file_utils/?path=/path/to/file" 
        style="width:100%;height:800px;border:none;">
</iframe>
```

### Configuration

Edit `share_file_utils/bootstrap.php` to configure:

- Allowed file types
- Maximum file size
- Theme settings
- Language defaults

## Best Practices

1. **Always validate paths**: Never trust user-supplied file paths
2. **Use HTTPS**: Serve the viewer over HTTPS for sensitive content
3. **Set proper MIME types**: Ensure correct Content-Type headers
4. **Cache thumbnails**: Enable caching for frequently accessed files
5. **Monitor logs**: Check `share_file_utils/` logs for errors

## Version

Current version: 1.0 (as of September 2026)
