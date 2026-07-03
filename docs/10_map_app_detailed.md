# Map Application - Detailed Documentation

**Path:** `apps/map/`  
**Total Lines of Code:** ~500  
**Main Entry:** `index.php` (2,627 lines)

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

The Map application is an **interactive maps platform** with user file uploads and favorites system. It allows users to view maps, upload custom map files, and save favorite locations.

### Key Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 1 |
| Total JSON Files | 2 |
| Languages Supported | 4 (EN, UK, RU, BE) |
| Main File Size | 2,627 lines |

---

## File Structure

```
apps/map/
├── index.php                          # Main map interface (2,627 lines)
├── name.json                          # App name translations
├── icon.png                           # Application icon
├── php_errors.log                     # PHP error log
│
├── uploads/                           # User uploaded map files
│   └── {user}/                        # Per-user upload folders
│       └── {files}                    # Uploaded map files
│
└── user_data/                         # Per-user data storage
    └── {email}/
        └── favorites.json             # User's favorite locations
```

---

## Core Components

### 1. Main Interface (`index.php`)

**Purpose:** Interactive map interface with upload and favorites functionality.

**Key Functions:**

```php
// Database connection
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
function get_current_user_from_sessions(): string
function getUserRoleByEmail(string $email): int
function isUserRoot(): bool
function getCurrentUserEmail(): ?string

// File operations
function getUserUploadPath(string $email): string
function saveUploadedFile(array $file, string $email): bool
function getUserUploads(string $email): array
function deleteUploadedFile(string $email, string $filename): bool

// Favorites operations
function getFavoritesPath(string $email): string
function loadFavorites(string $email): array
function saveFavorite(string $email, array $location): bool
function removeFavorite(string $email, string $id): bool
```

**URL Parameters:**
```php
$lang   = $_GET['lang']   ?? 'en';
$theme  = $_GET['theme']  ?? 'light';
$color  = $_GET['color']  ?? '#59b300';
```

---

### 2. User Upload System

**Upload Path Structure:**
```
user_data/
└── {email}/
    └── uploads/
        ├── map1.kml
        ├── route.gpx
        └── area.geojson
```

**Upload Handler:**
```php
function handleFileUpload(string $email): array {
    $uploadDir = getUserUploadPath($email);
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    
    $allowedTypes = ['kml', 'gpx', 'geojson', 'json', 'kmz'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    if (!isset($_FILES['mapfile'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    $file = $_FILES['mapfile'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    // Sanitize filename
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $targetPath = $uploadDir . '/' . $safeName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $targetPath];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}
```

---

### 3. Favorites System

**Favorites File Structure (`user_data/{email}/favorites.json`):**
```json
{
    "favorites": [
        {
            "id": "fav_001",
            "name": "Home",
            "latitude": 50.4501,
            "longitude": 30.5234,
            "zoom": 15,
            "category": "home",
            "notes": "My home location",
            "created_at": "2024-01-15T10:00:00Z"
        },
        {
            "id": "fav_002",
            "name": "Work",
            "latitude": 50.4427,
            "longitude": 30.5080,
            "zoom": 16,
            "category": "work",
            "notes": "Office location",
            "created_at": "2024-01-16T09:00:00Z"
        }
    ]
}
```

**Favorites Functions:**
```php
function loadFavorites(string $email): array {
    $file = getFavoritesPath($email);
    if (!file_exists($file)) {
        return ['favorites' => []];
    }
    return json_decode(file_get_contents($file), true);
}

function saveFavorite(string $email, array $location): bool {
    $data = loadFavorites($email);
    
    $favorite = [
        'id' => uniqid('fav_'),
        'name' => $location['name'],
        'latitude' => (float)$location['lat'],
        'longitude' => (float)$location['lng'],
        'zoom' => (int)($location['zoom'] ?? 15),
        'category' => $location['category'] ?? 'general',
        'notes' => $location['notes'] ?? '',
        'created_at' => date('c')
    ];
    
    $data['favorites'][] = $favorite;
    
    return file_put_contents(
        getFavoritesPath($email),
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function removeFavorite(string $email, string $id): bool {
    $data = loadFavorites($email);
    
    $data['favorites'] = array_filter(
        $data['favorites'],
        fn($f) => $f['id'] !== $id
    );
    
    return file_put_contents(
        getFavoritesPath($email),
        json_encode(array_values($data['favorites']), JSON_PRETTY_PRINT)
    ) !== false;
}
```

---

## Database Schema

### Shared Database Tables

The Map app uses the main `PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)` database for authentication:

**`users` table:**
```sql
CREATE TABLE users (
    email      TEXT PRIMARY KEY,
    name       TEXT NOT NULL DEFAULT '',
    password   TEXT NOT NULL DEFAULT '',
    photo      TEXT NOT NULL DEFAULT 'default_avatar.png',
    role       INTEGER NOT NULL DEFAULT 0,
    verified   INTEGER NOT NULL DEFAULT 0,
    language   TEXT NOT NULL DEFAULT 'en',
    theme      TEXT NOT NULL DEFAULT 'light',
    color      TEXT NOT NULL DEFAULT '#007bff',
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
)
```

**`active_sessions` table:**
```sql
CREATE TABLE active_sessions (
    session_id    TEXT PRIMARY KEY,
    email         TEXT NOT NULL,
    user_data     TEXT NOT NULL DEFAULT '{}',
    login_time    INTEGER NOT NULL,
    last_activity INTEGER NOT NULL,
    ip_address    TEXT NOT NULL DEFAULT '',
    user_agent    TEXT NOT NULL DEFAULT '',
    device        TEXT NOT NULL DEFAULT '',
    browser       TEXT NOT NULL DEFAULT ''
)
```

---

## Features

### 1. Interactive Map Display

**Map Provider:** OpenStreetMap / Leaflet.js

**Features:**
- Pan and zoom
- Satellite/hybrid/terrain layers
- Scale bar
- Compass
- Full-screen mode
- Location search
- Coordinates display

### 2. File Upload

**Supported Formats:**
| Format | Extension | Description |
|--------|-----------|-------------|
| KML | .kml | Keyhole Markup Language |
| KMZ | .kmz | Compressed KML |
| GPX | .gpx | GPS Exchange Format |
| GeoJSON | .geojson, .json | Geographic JSON |

**Upload Process:**
1. Select file from device
2. Validate file type and size
3. Upload to user's folder
4. Parse and display on map
5. Option to save as overlay

### 3. Favorites System

**Categories:**
- Home
- Work
- Favorite places
- Custom categories

**Features:**
- Save current view location
- Custom names and notes
- Quick navigation
- Import/Export favorites
- Share favorites

### 4. Location Search

**Search Options:**
- Address search (geocoding)
- Coordinates search
- Place name search
- Recent searches history

### 5. Measurement Tools

**Tools:**
- Distance measurement
- Area measurement
- Radius tool

---

## API Reference

### File Upload Endpoints

#### Upload Map File
```http
POST /index.php?action=upload
Content-Type: multipart/form-data

mapfile: {binary}

Response:
{
    "success": true,
    "filename": "route.gpx",
    "path": "user_data/email_com/route.gpx"
}
```

#### Get User Uploads
```http
GET /index.php?action=uploads

Response:
{
    "success": true,
    "uploads": [
        {
            "filename": "route.gpx",
            "path": "user_data/email_com/route.gpx",
            "size": 1024,
            "uploaded_at": "2024-01-15T10:00:00Z"
        }
    ]
}
```

#### Delete Upload
```http
POST /index.php?action=delete_upload
Content-Type: application/json

{
    "filename": "route.gpx"
}
```

---

### Favorites Endpoints

#### Get Favorites
```http
GET /index.php?action=favorites

Response:
{
    "success": true,
    "favorites": [
        {
            "id": "fav_001",
            "name": "Home",
            "latitude": 50.4501,
            "longitude": 30.5234,
            "zoom": 15,
            "category": "home",
            "notes": "My home"
        }
    ]
}
```

#### Add Favorite
```http
POST /index.php?action=add_favorite
Content-Type: application/json

{
    "name": "Work",
    "lat": 50.4427,
    "lng": 30.5080,
    "zoom": 16,
    "category": "work",
    "notes": "Office"
}
```

#### Remove Favorite
```http
POST /index.php?action=remove_favorite
Content-Type: application/json

{
    "id": "fav_001"
}
```

#### Update Favorite
```http
POST /index.php?action=update_favorite
Content-Type: application/json

{
    "id": "fav_001",
    "name": "New Home",
    "notes": "Updated address"
}
```

---

### Map Services

#### Geocode Address
```http
GET /index.php?action=geocode&address=Kyiv,Ukraine

Response:
{
    "success": true,
    "results": [
        {
            "latitude": 50.4501,
            "longitude": 30.5234,
            "display_name": "Kyiv, Ukraine"
        }
    ]
}
```

#### Reverse Geocode
```http
GET /index.php?action=reverse&lat=50.4501&lng=30.5234

Response:
{
    "success": true,
    "address": "Kyiv, Ukraine"
}
```

---

## Frontend Architecture

### Main JavaScript Modules

```javascript
// Map Manager
class MapManager {
    constructor(containerId) {
        this.map = L.map(containerId).setView([50.4501, 30.5234], 10);
        this.overlays = [];
        this.markers = [];
        
        // Base layers
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);
        
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '© Esri'
        });
    }
    
    async loadFile(path) {
        const response = await fetch(path);
        const data = await response.json();
        
        if (data.type === 'FeatureCollection') {
            this.loadGeoJSON(data);
        } else if (data.kml) {
            this.loadKML(data.kml);
        } else if (data.gpx) {
            this.loadGPX(data.gpx);
        }
    }
    
    loadGeoJSON(geojson) {
        const layer = L.geoJSON(geojson).addTo(this.map);
        this.overlays.push(layer);
        this.map.fitBounds(layer.getBounds());
    }
    
    addMarker(lat, lng, options = {}) {
        const marker = L.marker([lat, lng], options).addTo(this.map);
        this.markers.push(marker);
        return marker;
    }
    
    flyTo(lat, lng, zoom) {
        this.map.flyTo([lat, lng], zoom);
    }
}

// Favorites Manager
class FavoritesManager {
    constructor() {
        this.favorites = [];
    }
    
    async loadFavorites() {
        const response = await fetch('?action=favorites');
        const data = await response.json();
        this.favorites = data.favorites;
        this.render();
    }
    
    async addFavorite(name, lat, lng, zoom, category, notes) {
        const response = await fetch('?action=add_favorite', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name, lat, lng, zoom, category, notes
            })
        });
        
        const data = await response.json();
        if (data.success) {
            await this.loadFavorites();
        }
    }
    
    async removeFavorite(id) {
        const response = await fetch('?action=remove_favorite', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const data = await response.json();
        if (data.success) {
            await this.loadFavorites();
        }
    }
    
    render() {
        const container = document.getElementById('favorites-list');
        container.innerHTML = this.favorites.map(fav => `
            <div class="favorite-item" data-id="${fav.id}">
                <div class="favorite-name">${fav.name}</div>
                <div class="favorite-category">${fav.category}</div>
                <button onclick="mapManager.flyTo(${fav.latitude}, ${fav.longitude}, ${fav.zoom})">
                    Navigate
                </button>
                <button onclick="favoritesManager.removeFavorite('${fav.id}')">
                    Delete
                </button>
            </div>
        `).join('');
    }
}

// Upload Manager
class UploadManager {
    async upload(file) {
        const formData = new FormData();
        formData.append('mapfile', file);
        
        const response = await fetch('?action=upload', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.success) {
            await mapManager.loadFile(data.path);
        }
        return data;
    }
}
```

---

## Security

### File Upload Security

```php
function validateUploadFile($file): array {
    $allowedTypes = ['kml', 'gpx', 'geojson', 'json', 'kmz'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowedTypes)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File too large'];
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    
    $allowedMimes = [
        'application/vnd.google-earth.kml+xml',
        'application/gpx+xml',
        'application/geo+json',
        'application/json'
    ];
    
    if (!in_array($mimeType, $allowedMimes) && !in_array($ext, ['kml', 'gpx'])) {
        return ['valid' => false, 'error' => 'Invalid MIME type'];
    }
    
    return ['valid' => true];
}
```

### Path Sanitization

```php
function sanitizePath($path): string {
    // Remove directory traversal attempts
    $path = str_replace('..', '', $path);
    $path = str_replace('\\', '/', $path);
    
    // Remove special characters
    $path = preg_replace('/[^a-zA-Z0-9._-]/', '_', $path);
    
    return $path;
}
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Upload fails | File too large | Increase max file size limit |
| Map not loading | API key missing | Add OpenStreetMap/Google Maps key |
| Favorites not saving | Permission denied | Check user_data folder permissions |
| KML not displaying | Invalid format | Validate KML syntax |

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
