# TFT Generator Application - Detailed Documentation

**Path:** `apps/tft-generator/`  
**Total Lines of Code:** ~1,700  
**Main Entry:** `index.php` (1,616 lines)

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Core Components](#core-components)
4. [Database Schema](#database-schema)
5. [Features](#features)
6. [API Reference](#api-reference)
7. [Frontend Architecture](#frontend-architecture)

---

## Overview

The TFT Generator application is a **code generator for TFT displays** with a visual configuration interface, per-user project storage, and touch support for mobile devices.

### Key Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 1 |
| Total JSON Files | 2 |
| Languages Supported | 4 (EN, UK, RU, BE) |
| Main File Size | 1,616 lines |
| Database Type | Per-user SQLite |

---

## File Structure

```
apps/tft-generator/
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

---

## Core Components

### 1. Main Interface (`index.php`)

**Purpose:** Visual TFT display code generator with configuration UI.

**Key Functions:**

```php
// Database connection (shared)
function getDB(): ?SQLite3 {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_FILE);
        $db->enableExceptions(true);
        $db->busyTimeout(3000);
    }
    return $db;
}

// User authentication
function getCurrentUserEmail(): string {
    $db = getDB();
    if (!$db) return '';
    
    $session_id = session_id();
    if ($session_id) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid');
            $stmt->bindValue(':sid', $session_id, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['email'])) return $row['email'];
        } catch (Exception $e) {}
    }
    
    if (isset($_COOKIE['PHPSESSID'])) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid');
            $stmt->bindValue(':sid', $_COOKIE['PHPSESSID'], SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['email'])) return $row['email'];
        } catch (Exception $e) {}
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return 'ip_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
}

// Per-user database
function getUserDB(): ?SQLite3 {
    static $userDb = null;
    if ($userDb !== null) return $userDb;
    
    $email = getCurrentUserEmail();
    $folderName = preg_replace('/[@.]/', '_', $email ?: 'default_user');
    $userDir = USER_DATA_BASE_DIR . '/' . $folderName;
    
    if (!is_dir($userDir)) {
        mkdir($userDir, 0775, true);
    }
    
    $dbPath = $userDir . '/data.sqlite3';
    try {
        $userDb = new SQLite3($dbPath);
        $userDb->enableExceptions(true);
        $userDb->busyTimeout(3000);
        
        // Create tables
        $userDb->exec("CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value TEXT)");
        $userDb->exec("CREATE TABLE IF NOT EXISTS canvas (key TEXT PRIMARY KEY, value TEXT)");
        
        return $userDb;
    } catch (Exception $e) {
        error_log("User DB init failed: " . $e->getMessage());
        return null;
    }
}

// Configuration operations
function loadConfig(): array {
    $db = getUserDB();
    if (!$db) return getDefaultConfig();
    
    $config = getDefaultConfig();
    try {
        $result = $db->query('SELECT key, value FROM config');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $config[$row['key']] = json_decode($row['value'], true);
        }
    } catch (Exception $e) {
        error_log("Load config failed: " . $e->getMessage());
    }
    
    return $config;
}

function saveConfig(array $config): bool {
    $db = getUserDB();
    if (!$db) return false;
    
    try {
        $db->exec('BEGIN');
        foreach ($config as $key => $value) {
            $stmt = $db->prepare('INSERT OR REPLACE INTO config (key, value) VALUES (:k, :v)');
            $stmt->bindValue(':k', $key, SQLITE3_TEXT);
            $stmt->bindValue(':v', json_encode($value), SQLITE3_TEXT);
            $stmt->execute();
        }
        $db->exec('COMMIT');
        return true;
    } catch (Exception $e) {
        error_log("Save config failed: " . $e->getMessage());
        return false;
    }
}

// Canvas operations
function loadCanvas(): array {
    $db = getUserDB();
    if (!$db) return [];
    
    $canvas = [];
    try {
        $result = $db->query('SELECT key, value FROM canvas');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $canvas[$row['key']] = json_decode($row['value'], true);
        }
    } catch (Exception $e) {
        error_log("Load canvas failed: " . $e->getMessage());
    }
    
    return $canvas;
}

function saveCanvas(array $canvas): bool {
    $db = getUserDB();
    if (!$db) return false;
    
    try {
        $db->exec('BEGIN');
        foreach ($canvas as $key => $value) {
            $stmt = $db->prepare('INSERT OR REPLACE INTO canvas (key, value) VALUES (:k, :v)');
            $stmt->bindValue(':k', $key, SQLITE3_TEXT);
            $stmt->bindValue(':v', json_encode($value), SQLITE3_TEXT);
            $stmt->execute();
        }
        $db->exec('COMMIT');
        return true;
    } catch (Exception $e) {
        error_log("Save canvas failed: " . $e->getMessage());
        return false;
    }
}

// Default configuration
function getDefaultConfig(): array {
    return [
        'display' => [
            'width' => 240,
            'height' => 320,
            'orientation' => 'portrait',
            'color_depth' => 16
        ],
        'pins' => [
            'cs' => 5,
            'dc' => 16,
            'rst' => 17,
            'sck' => 18,
            'mosi' => 23,
            'miso' => 19,
            'bl' => 2
        ],
        'driver' => 'ILI9341',
        'spi_frequency' => 40000000,
        'font' => 'Arial',
        'font_size' => 12
    ];
}

// Code generation
function generateCCode(array $config, array $canvas): string {
    $code = "// TFT Display Code - Generated by ServerKherson TFT Generator\n\n";
    $code .= "#include <SPI.h>\n";
    $code .= "#include <Adafruit_GFX.h>\n";
    $code .= "#include <Adafruit_ILI9341.h>\n\n";
    
    // Pin definitions
    $code .= "// Pin definitions\n";
    $code .= "#define TFT_CS    {$config['pins']['cs']}\n";
    $code .= "#define TFT_DC    {$config['pins']['dc']}\n";
    $code .= "#define TFT_RST   {$config['pins']['rst']}\n";
    $code .= "#define TFT_SCK   {$config['pins']['sck']}\n";
    $code .= "#define TFT_MOSI  {$config['pins']['mosi']}\n";
    $code .= "#define TFT_MISO  {$config['pins']['miso']}\n";
    $code .= "#define TFT_BL    {$config['pins']['bl']}\n\n";
    
    // Display initialization
    $code .= "// Display initialization\n";
    $code .= "Adafruit_ILI9341 tft = Adafruit_ILI9341(TFT_CS, TFT_DC, TFT_RST);\n\n";
    
    $code .= "void setup() {\n";
    $code .= "    tft.begin();\n";
    $code .= "    tft.setRotation({$config['display']['orientation']});\n";
    $code .= "    tft.fillScreen(ILI9341_BLACK);\n";
    $code .= "}\n\n";
    
    $code .= "void loop() {\n";
    
    // Generate drawing commands from canvas
    foreach ($canvas['elements'] ?? [] as $element) {
        $code .= generateElementCode($element);
    }
    
    $code .= "}\n";
    
    return $code;
}

function generateElementCode(array $element): string {
    switch ($element['type']) {
        case 'rectangle':
            return "    tft.fillRect({$element['x']}, {$element['y']}, {$element['width']}, {$element['height']}, {$element['color']});\n";
        
        case 'circle':
            return "    tft.fillCircle({$element['x']}, {$element['y']}, {$element['radius']}, {$element['color']});\n";
        
        case 'line':
            return "    tft.drawLine({$element['x1']}, {$element['y1']}, {$element['x2']}, {$element['y2']}, {$element['color']});\n";
        
        case 'text':
            return "    tft.setCursor({$element['x']}, {$element['y']});\n    tft.setTextColor({$element['color']});\n    tft.setTextSize({$element['size']});\n    tft.println(\"{$element['text']}\");\n";
        
        case 'triangle':
            return "    tft.fillTriangle({$element['x1']}, {$element['y1']}, {$element['x2']}, {$element['y2']}, {$element['x3']}, {$element['y3']}, {$element['color']});\n";
        
        default:
            return "";
    }
}
```

**URL Parameters:**
```php
$lang   = $_GET['lang']   ?? 'en';
$theme  = $_GET['theme']  ?? 'light';
$color  = $_GET['color']  ?? '#85b503';
```

---

## Database Schema

### Per-User Database (`userdata/{email}/data.sqlite3`)

Each user has their own PostgreSQL database with the following schema:

#### `config` Table
```sql
CREATE TABLE config (
    key   TEXT PRIMARY KEY,
    value TEXT
);

-- Example data:
-- ('display', '{"width":240,"height":320,"orientation":0,"color_depth":16}')
-- ('pins', '{"cs":5,"dc":16,"rst":17,"sck":18,"mosi":23,"miso":19,"bl":2}')
-- ('driver', 'ILI9341')
-- ('spi_frequency', '40000000')
```

#### `canvas` Table
```sql
CREATE TABLE canvas (
    key   TEXT PRIMARY KEY,
    value TEXT
);

-- Example data:
-- ('elements', '[{"type":"rectangle","x":10,"y":10,"width":100,"height":50,"color":"ILI9341_RED"}]')
-- ('settings', '{"grid":true,"snap":true,"zoom":1}')
```

---

## Features

### 1. Display Configuration

**Display Settings:**
- Width and height (pixels)
- Orientation (portrait/landscape, 0-3)
- Color depth (16-bit, 24-bit)
- Driver selection (ILI9341, ST7735, HX8357, etc.)
- SPI frequency

**Supported Drivers:**
- ILI9341 (240x320)
- ST7735 (128x160)
- HX8357 (320x480)
- ILI9340 (240x320)
- ST7789 (240x240, 240x320)

### 2. Pin Configuration

**Configurable Pins:**
- CS (Chip Select)
- DC (Data/Command)
- RST (Reset)
- SCK (SPI Clock)
- MOSI (SPI Data Out)
- MISO (SPI Data In)
- BL (Backlight)

**Default Pin Mapping (ESP32):**
```
CS   → GPIO 5
DC   → GPIO 16
RST  → GPIO 17
SCK  → GPIO 18
MOSI → GPIO 23
MISO → GPIO 19
BL   → GPIO 2
```

### 3. Visual Canvas Editor

**Drawing Tools:**
- Rectangle (filled, outline)
- Circle (filled, outline)
- Line
- Text
- Triangle
- Custom shapes

**Canvas Features:**
- Drag and drop elements
- Resize handles
- Grid snapping
- Zoom in/out
- Undo/Redo
- Element properties panel
- Layer ordering

### 4. Code Generation

**Generated Code:**
- Arduino C/C++ code
- PlatformIO compatible
- Adafruit_GFX library
- Driver-specific initialization

**Code Features:**
- Pin definitions
- Display initialization
- Drawing commands
- Comment annotations
- Clean formatting

### 5. Project Management

**Features:**
- Save projects to database
- Load saved projects
- Export project as JSON
- Import project from JSON
- Multiple projects per user

### 6. Touch Support

**Mobile Features:**
- Touch-friendly UI
- Drag with finger
- Pinch to zoom
- Mobile-responsive layout
- Touch-optimized controls

---

## API Reference

### Configuration Endpoints

#### Get Configuration
```http
GET /index.php?action=get_config

Response:
{
    "success": true,
    "config": {
        "display": {
            "width": 240,
            "height": 320,
            "orientation": 0,
            "color_depth": 16
        },
        "pins": {
            "cs": 5,
            "dc": 16,
            "rst": 17,
            "sck": 18,
            "mosi": 23,
            "miso": 19,
            "bl": 2
        },
        "driver": "ILI9341",
        "spi_frequency": 40000000
    }
}
```

#### Save Configuration
```http
POST /index.php?action=save_config
Content-Type: application/json

{
    "display": {
        "width": 240,
        "height": 320
    },
    "pins": {
        "cs": 5
    },
    "driver": "ILI9341"
}
```

---

### Canvas Endpoints

#### Get Canvas
```http
GET /index.php?action=get_canvas

Response:
{
    "success": true,
    "canvas": {
        "elements": [
            {
                "type": "rectangle",
                "x": 10,
                "y": 10,
                "width": 100,
                "height": 50,
                "color": "ILI9341_RED"
            }
        ],
        "settings": {
            "grid": true,
            "snap": true,
            "zoom": 1
        }
    }
}
```

#### Save Canvas
```http
POST /index.php?action=save_canvas
Content-Type: application/json

{
    "elements": [...],
    "settings": {...}
}
```

#### Add Element
```http
POST /index.php?action=add_element
Content-Type: application/json

{
    "type": "rectangle",
    "x": 10,
    "y": 10,
    "width": 100,
    "height": 50,
    "color": "ILI9341_RED"
}
```

#### Update Element
```http
POST /index.php?action=update_element
Content-Type: application/json

{
    "id": "element_001",
    "x": 20,
    "y": 20,
    "width": 120,
    "height": 60
}
```

#### Delete Element
```http
POST /index.php?action=delete_element
Content-Type: application/json

{
    "id": "element_001"
}
```

---

### Code Generation Endpoints

#### Generate Code
```http
POST /index.php?action=generate_code
Content-Type: application/json

{
    "config": {...},
    "canvas": {...}
}

Response:
{
    "success": true,
    "code": "// TFT Display Code...\n#include <SPI.h>\n..."
}
```

#### Download Code
```http
GET /index.php?action=download_code

Response:
Content-Type: text/plain
Content-Disposition: attachment; filename="tft_code.ino"

// TFT Display Code...
```

---

### Project Endpoints

#### Save Project
```http
POST /index.php?action=save_project
Content-Type: application/json

{
    "name": "My Project",
    "config": {...},
    "canvas": {...}
}
```

#### Load Project
```http
GET /index.php?action=load_project&name=My%20Project

Response:
{
    "success": true,
    "project": {
        "name": "My Project",
        "config": {...},
        "canvas": {...}
    }
}
```

#### List Projects
```http
GET /index.php?action=list_projects

Response:
{
    "success": true,
    "projects": [
        {"name": "Project 1", "updated_at": "2024-01-15T10:00:00Z"},
        {"name": "Project 2", "updated_at": "2024-01-16T15:00:00Z"}
    ]
}
```

#### Delete Project
```http
POST /index.php?action=delete_project
Content-Type: application/json

{
    "name": "My Project"
}
```

---

## Frontend Architecture

### Main JavaScript Modules

```javascript
// Canvas Editor
class CanvasEditor {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.elements = [];
        this.selectedElement = null;
        this.isDragging = false;
        this.dragOffset = { x: 0, y: 0 };
        this.gridSize = 10;
        this.snapToGrid = true;
        this.zoom = 1;
        
        this.initEvents();
        this.render();
    }
    
    initEvents() {
        this.canvas.addEventListener('mousedown', (e) => this.onMouseDown(e));
        this.canvas.addEventListener('mousemove', (e) => this.onMouseMove(e));
        this.canvas.addEventListener('mouseup', (e) => this.onMouseUp(e));
        this.canvas.addEventListener('touchstart', (e) => this.onTouchStart(e));
        this.canvas.addEventListener('touchmove', (e) => this.onTouchMove(e));
        this.canvas.addEventListener('touchend', (e) => this.onTouchEnd(e));
    }
    
    addElement(element) {
        element.id = 'element_' + Date.now();
        element.x = this.snap(element.x);
        element.y = this.snap(element.y);
        this.elements.push(element);
        this.render();
        this.saveCanvas();
    }
    
    snap(value) {
        if (this.snapToGrid) {
            return Math.round(value / this.gridSize) * this.gridSize;
        }
        return value;
    }
    
    getElementAt(x, y) {
        for (let i = this.elements.length - 1; i >= 0; i--) {
            const el = this.elements[i];
            if (this.isPointInElement(x, y, el)) {
                return el;
            }
        }
        return null;
    }
    
    isPointInElement(x, y, el) {
        switch (el.type) {
            case 'rectangle':
                return x >= el.x && x <= el.x + el.width &&
                       y >= el.y && y <= el.y + el.height;
            case 'circle':
                const dx = x - el.x;
                const dy = y - el.y;
                return Math.sqrt(dx * dx + dy * dy) <= el.radius;
            case 'text':
                // Approximate text bounds
                return x >= el.x && x <= el.x + 100 &&
                       y >= el.y - 20 && y <= el.y;
            default:
                return false;
        }
    }
    
    onMouseDown(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / this.zoom;
        const y = (e.clientY - rect.top) / this.zoom;
        
        this.selectedElement = this.getElementAt(x, y);
        
        if (this.selectedElement) {
            this.isDragging = true;
            this.dragOffset = {
                x: x - this.selectedElement.x,
                y: y - this.selectedElement.y
            };
        }
        
        this.render();
    }
    
    onMouseMove(e) {
        if (!this.isDragging || !this.selectedElement) return;
        
        const rect = this.canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / this.zoom;
        const y = (e.clientY - rect.top) / this.zoom;
        
        this.selectedElement.x = this.snap(x - this.dragOffset.x);
        this.selectedElement.y = this.snap(y - this.dragOffset.y);
        
        this.render();
    }
    
    onMouseUp(e) {
        this.isDragging = false;
        this.saveCanvas();
    }
    
    render() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Draw grid
        if (this.gridSize > 0) {
            this.ctx.strokeStyle = '#e0e0e0';
            this.ctx.lineWidth = 1;
            
            for (let x = 0; x < this.canvas.width; x += this.gridSize * this.zoom) {
                this.ctx.beginPath();
                this.ctx.moveTo(x, 0);
                this.ctx.lineTo(x, this.canvas.height);
                this.ctx.stroke();
            }
            
            for (let y = 0; y < this.canvas.height; y += this.gridSize * this.zoom) {
                this.ctx.beginPath();
                this.ctx.moveTo(0, y);
                this.ctx.lineTo(this.canvas.width, y);
                this.ctx.stroke();
            }
        }
        
        // Draw elements
        this.elements.forEach(el => {
            this.drawElement(el);
        });
        
        // Draw selection highlight
        if (this.selectedElement) {
            this.drawSelectionHighlight(this.selectedElement);
        }
    }
    
    drawElement(el) {
        this.ctx.save();
        this.ctx.scale(this.zoom, this.zoom);
        
        switch (el.type) {
            case 'rectangle':
                this.ctx.fillStyle = this.getColorValue(el.color);
                this.ctx.fillRect(el.x, el.y, el.width, el.height);
                if (el.stroke) {
                    this.ctx.strokeStyle = el.strokeColor;
                    this.ctx.strokeRect(el.x, el.y, el.width, el.height);
                }
                break;
            
            case 'circle':
                this.ctx.beginPath();
                this.ctx.arc(el.x, el.y, el.radius, 0, Math.PI * 2);
                this.ctx.fillStyle = this.getColorValue(el.color);
                this.ctx.fill();
                break;
            
            case 'line':
                this.ctx.beginPath();
                this.ctx.moveTo(el.x1, el.y1);
                this.ctx.lineTo(el.x2, el.y2);
                this.ctx.strokeStyle = this.getColorValue(el.color);
                this.ctx.stroke();
                break;
            
            case 'text':
                this.ctx.fillStyle = this.getColorValue(el.color);
                this.ctx.font = `${el.size || 12}px Arial`;
                this.ctx.fillText(el.text, el.x, el.y);
                break;
        }
        
        this.ctx.restore();
    }
    
    getColorValue(color) {
        // Convert ILI9341_RED to actual color
        const colorMap = {
            'ILI9341_BLACK': '#000000',
            'ILI9341_WHITE': '#FFFFFF',
            'ILI9341_RED': '#FF0000',
            'ILI9341_GREEN': '#00FF00',
            'ILI9341_BLUE': '#0000FF',
            'ILI9341_YELLOW': '#FFFF00',
            'ILI9341_CYAN': '#00FFFF',
            'ILI9341_MAGENTA': '#FF00FF'
        };
        return colorMap[color] || color;
    }
    
    drawSelectionHighlight(el) {
        this.ctx.save();
        this.ctx.scale(this.zoom, this.zoom);
        this.ctx.strokeStyle = '#4a90e2';
        this.ctx.lineWidth = 2 / this.zoom;
        this.ctx.setLineDash([5, 5]);
        
        switch (el.type) {
            case 'rectangle':
                this.ctx.strokeRect(el.x - 2, el.y - 2, el.width + 4, el.height + 4);
                break;
            case 'circle':
                this.ctx.beginPath();
                this.ctx.arc(el.x, el.y, el.radius + 2, 0, Math.PI * 2);
                this.ctx.stroke();
                break;
        }
        
        this.ctx.restore();
    }
    
    async saveCanvas() {
        await fetch('?action=save_canvas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                elements: this.elements,
                settings: {
                    grid: this.gridSize,
                    snap: this.snapToGrid,
                    zoom: this.zoom
                }
            })
        });
    }
    
    async loadCanvas() {
        const response = await fetch('?action=get_canvas');
        const data = await response.json();
        
        if (data.success) {
            this.elements = data.canvas.elements || [];
            if (data.canvas.settings) {
                this.gridSize = data.canvas.settings.grid || 10;
                this.snapToGrid = data.canvas.settings.snap !== false;
                this.zoom = data.canvas.settings.zoom || 1;
            }
            this.render();
        }
    }
}

// Code Generator
class CodeGenerator {
    async generate() {
        const config = await this.loadConfig();
        const canvas = await this.loadCanvas();
        
        const response = await fetch('?action=generate_code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ config, canvas })
        });
        
        const data = await response.json();
        if (data.success) {
            this.showCode(data.code);
        }
    }
    
    showCode(code) {
        const modal = document.getElementById('code-modal');
        const codeBlock = document.getElementById('generated-code');
        codeBlock.textContent = code;
        modal.classList.add('active');
    }
    
    downloadCode() {
        const code = document.getElementById('generated-code').textContent;
        const blob = new Blob([code], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'tft_code.ino';
        a.click();
        URL.revokeObjectURL(url);
    }
    
    async loadConfig() {
        const response = await fetch('?action=get_config');
        const data = await response.json();
        return data.config;
    }
    
    async loadCanvas() {
        const response = await fetch('?action=get_canvas');
        const data = await response.json();
        return data.canvas;
    }
}

// Initialize
const canvasEditor = new CanvasEditor('canvas');
const codeGenerator = new CodeGenerator();

document.addEventListener('DOMContentLoaded', () => {
    canvasEditor.loadCanvas();
});
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Canvas not loading | Database empty | Create first element |
| Code generation fails | Missing config | Save configuration first |
| Touch not working | Event listener issue | Check browser compatibility |
| Elements not saving | Database locked | Check WAL files |

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
