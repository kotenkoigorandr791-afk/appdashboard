# Lopaka (TFT Generator) Application - Detailed Documentation

**Path:** `apps/lopaka/`  
**Type:** Full Application  
**Description:** Embedded display editor for TFT/e-ink screens.

---

## Overview

Lopaka is a web-based editor for designing display layouts for small embedded screens (TFT, e-ink, OLED). Supports various screen sizes and provides a visual layout designer with code generation.

---

## File Structure

```
apps/lopaka/
├── index.php                     # Main editor
├── assets/                       # Fonts, templates, icons
│   ├── fonts/                    # Font files (.ttf, .bin)
│   ├── icons/                    # Icon SVGs
│   ├── templates/                # Layout templates
│   ├── app-*.css                 # Compiled CSS
│   └── app-*.js                  # Compiled JS
├── description.json              # App description (multilingual)
├── name.json                     # Display name (multilingual)
├── icon.svg                      # App icon
├── icons/                        # Additional icons
└── font-preview.html             # Font testing page
```

## Configuration Files

| File | Purpose |
|------|---------|
| `description.json` | App metadata (6 languages) |
| `name.json` | Display name and menu button layout |
| `icon.svg` | App icon |
| `font-preview.html` | Font testing and preview |

## Key Features

### Display Support
- Various screen sizes: 5x7, 6x9, 6x10, 6x12, 6x13, 4x6
- Multiple font formats: TTF, BDF, binary font data
- Preview for each screen resolution
- Color and monochrome modes

### Editor
- Drag-and-drop layout designer
- Real-time preview
- Code generation (C/C++, Python, Arduino)
- Template system for common layouts
- Font embedding and subsetting
- Icon library integration

### Assets
- Pre-loaded fonts: Fira Code, Courier Prime, FreeMono, Adafruit
- Icon sets: alignment, shapes, symbols, brand icons
- Templates for common use cases (weather, clock, sensor data)
- Export options for different frameworks

### Output Formats
- Arduino/C++ code
- Python (MicroPython/CircuitPython)
- Raw binary data
- Header files for embedded projects

## API Endpoints

### Editor
- `GET /api/lopaka/templates` - List templates
- `POST /api/lopaka/preview` - Generate preview
- `POST /api/lopaka/export` - Export layout code
- `GET /api/lopaka/fonts` - List available fonts

## Internationalization

Supports all 6 interface languages (en, uk, ru, be, pl, cs).

## Dependencies

### JavaScript
- Canvas API for preview rendering
- FileSaver.js for exports
- Font loading API

### PHP
- GD/Imagick for server-side rendering (optional)

## Deployment Notes

- No database required
- Static file serving for assets
- Font files need read permissions
- HTTPS recommended for font loading
