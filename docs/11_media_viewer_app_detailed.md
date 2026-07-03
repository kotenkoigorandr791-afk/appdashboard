# Media Viewer Application - Detailed Documentation

**Path:** `apps/media_viewer/`  
**Total Lines of Code:** ~1,300  
**Main Entry:** `index.php` (1,292 lines)

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Core Components](#core-components)
4. [Features](#features)
5. [Configuration](#configuration)
6. [Frontend Architecture](#frontend-architecture)

---

## Overview

The Media Viewer application is a **photo and video gallery** with album support and view statistics tracking. It provides a clean interface for browsing media collections.

### Key Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 2 |
| Total JSON Files | 4 |
| Total Size | ~437 MB (includes installers) |
| Languages Supported | 4 (EN, UK, RU, BE) |
| Main File Size | 1,292 lines |

---

## File Structure

```
apps/media_viewer/
├── index.php                          # Main gallery interface (1,292 lines)
├── albums.json                        # Album definitions (680 bytes)
├── name.json                          # App name translations (112 bytes)
├── translations.json                  # UI translations (14.6 KB)
├── update_stats.php                   # Statistics updater (547 bytes)
├── icon.png                           # Application icon (63.88 KB)
├── Thumbs.db                          # Windows thumbnail cache (15.5 KB)
│
├── data/                              # Runtime data
│   ├── albums.json                    # Dynamic album data (2 bytes)
│   └── stats.json                     # View statistics (26 bytes)
│
├── previews/                          # Generated thumbnails (empty by default)
│
└── install/                           # Installation files (437 MB)
    ├── app-immich.apk                 # Immich Android app (144 MB)
    ├── ImmichDesktop-1.1.2-Archive.zip # Immich desktop archive (110 MB)
    ├── ImmichDesktop-Portable-1.1.2.exe # Immich portable (91 MB)
    └── Immich Desktop Setup 1.1.3.exe # Immich installer (91 MB)
```

---

## Core Components

### 1. Main Gallery (`index.php`)

**Purpose:** Photo and video gallery with album browsing.

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

// Album operations
function loadAlbums(): array
function getAlbumById(string $id): ?array
function getAlbumMedia(string $albumId): array

// Statistics
function recordView(string $mediaId): void
function getStatistics(): array
function updateStatsFile(): void
```

**URL Parameters:**
```php
$lang   = $_GET['lang']   ?? 'en';
$theme  = $_GET['theme']  ?? 'light';
$color  = $_GET['color']  ?? '#59b300';
$app    = $_GET['app']    ?? 'test2';
```

---

### 2. Albums Configuration (`albums.json`)

**Structure:**
```json
{
    "albums": [
        {
            "id": "album_001",
            "name": "Vacation 2024",
            "description": "Summer vacation photos",
            "cover": "previews/album_001_cover.jpg",
            "created_at": "2024-07-01T00:00:00Z",
            "updated_at": "2024-07-15T00:00:00Z",
            "media_count": 150,
            "is_public": true,
            "sort_order": 1
        },
        {
            "id": "album_002",
            "name": "Family Events",
            "description": "Family gatherings and celebrations",
            "cover": "previews/album_002_cover.jpg",
            "created_at": "2024-01-01T00:00:00Z",
            "updated_at": "2024-12-31T00:00:00Z",
            "media_count": 300,
            "is_public": true,
            "sort_order": 2
        }
    ]
}
```

---

### 3. Statistics System (`data/stats.json`)

**Structure:**
```json
{
    "total_views": 12345,
    "unique_visitors": 567,
    "media_views": {
        "media_001": 123,
        "media_002": 456
    },
    "last_updated": "2024-01-15T10:00:00Z"
}
```

**Statistics Functions:**
```php
function recordView(string $mediaId): void {
    $statsFile = 'data/stats.json';
    
    $stats = [];
    if (file_exists($statsFile)) {
        $stats = json_decode(file_get_contents($statsFile), true);
    }
    
    $stats['total_views'] = ($stats['total_views'] ?? 0) + 1;
    
    if (!isset($stats['media_views'])) {
        $stats['media_views'] = [];
    }
    
    $stats['media_views'][$mediaId] = ($stats['media_views'][$mediaId] ?? 0) + 1;
    $stats['last_updated'] = date('c');
    
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
}

function getStatistics(): array {
    $statsFile = 'data/stats.json';
    
    if (!file_exists($statsFile)) {
        return [
            'total_views' => 0,
            'unique_visitors' => 0,
            'media_views' => []
        ];
    }
    
    return json_decode(file_get_contents($statsFile), true);
}
```

---

### 4. Statistics Updater (`update_stats.php`)

**Purpose:** Background script to update and aggregate statistics.

**Content:**
```php
<?php
// update_stats.php - Update statistics from database

$statsFile = 'data/stats.json';
$db = getDB();

if (!$db) {
    die('Database connection failed');
}

// Get visit stats from database
$result = $db->query("
    SELECT COUNT(*) as total_views, 
           COUNT(DISTINCT email) as unique_visitors
    FROM visit_stats
    WHERE app_name = 'media_viewer'
");
$row = $result->fetchArray(SQLITE3_ASSOC);

$stats = [
    'total_views' => (int)($row['total_views'] ?? 0),
    'unique_visitors' => (int)($row['unique_visitors'] ?? 0),
    'media_views' => [],
    'last_updated' => date('c')
];

file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
echo "Statistics updated successfully\n";
```

---

## Features

### 1. Album Browsing

**Album View:**
- Grid layout with cover images
- Album name and description
- Media count display
- Creation/update dates
- Sort by name/date/count

### 2. Media Viewing

**Supported Formats:**
| Type | Extensions |
|------|------------|
| Images | JPG, JPEG, PNG, GIF, WEBP, BMP, SVG |
| Videos | MP4, WEBM, OGG, MOV, AVI |

**Viewer Features:**
- Full-screen mode
- Zoom and pan
- Slideshow mode
- Next/Previous navigation
- Thumbnail strip
- EXIF data display (for images)
- Video controls (play, pause, volume)

### 3. Statistics Tracking

**Tracked Metrics:**
- Total views
- Unique visitors
- Per-media view count
- Last viewed timestamp
- Most popular media

### 4. Responsive Design

**Breakpoints:**
- Desktop: > 1024px
- Tablet: 768px - 1024px
- Mobile: < 768px

**Features:**
- Touch-friendly navigation
- Swipe gestures for mobile
- Lazy loading images
- Responsive video embeds

---

## Configuration

### Albums Configuration (`albums.json`)

```json
{
    "albums": [
        {
            "id": "album_001",
            "name": "Album Name",
            "description": "Description",
            "cover": "path/to/cover.jpg",
            "created_at": "2024-01-01T00:00:00Z",
            "updated_at": "2024-01-15T00:00:00Z",
            "media_count": 100,
            "is_public": true,
            "sort_order": 1
        }
    ],
    "settings": {
        "items_per_page": 20,
        "slideshow_interval": 5000,
        "enable_zoom": true,
        "enable_download": true,
        "thumbnail_quality": 80
    }
}
```

### Translations (`translations.json`)

```json
{
    "en": {
        "title": "Media Gallery",
        "albums": "Albums",
        "photos": "Photos",
        "videos": "Videos",
        "view": "View",
        "download": "Download",
        "slideshow": "Slideshow",
        "fullscreen": "Fullscreen",
        "zoom_in": "Zoom In",
        "zoom_out": "Zoom Out",
        "previous": "Previous",
        "next": "Next",
        "close": "Close",
        "of": "of",
        "views": "views",
        "no_media": "No media in this album"
    },
    "uk": {
        "title": "Медіа Галерея",
        "albums": "Альбоми",
        "photos": "Фото",
        "videos": "Відео",
        "view": "Перегляд",
        "download": "Завантажити",
        "slideshow": "Слайдшоу",
        "fullscreen": "На весь екран",
        "previous": "Попереднє",
        "next": "Наступне",
        "close": "Закрити",
        "of": "з",
        "views": "переглядів"
    },
    "ru": {
        "title": "Медиа Галерея",
        "albums": "Альбомы",
        "photos": "Фото",
        "videos": "Видео",
        "view": "Просмотр",
        "download": "Скачать",
        "slideshow": "Слайд-шоу",
        "fullscreen": "На весь экран",
        "previous": "Предыдущее",
        "next": "Следующее",
        "close": "Закрыть",
        "of": "из",
        "views": "просмотров"
    },
    "be": {
        "title": "Медыя Галерэя",
        "albums": "Альбомы",
        "photos": "Фота",
        "videos": "Відэа",
        "view": "Прагляд",
        "download": "Спампаваць",
        "slideshow": "Слайд-шоу",
        "fullscreen": "На ўвесь экран",
        "previous": "Папярэдняе",
        "next": "Наступнае",
        "close": "Закрыць",
        "of": "з",
        "views": "праглядаў"
    }
}
```

---

## Frontend Architecture

### Main JavaScript Modules

```javascript
// Gallery Manager
class GalleryManager {
    constructor() {
        this.albums = [];
        this.currentAlbum = null;
        this.media = [];
        this.currentIndex = 0;
        this.slideshowTimer = null;
    }
    
    async loadAlbums() {
        const response = await fetch('albums.json');
        const data = await response.json();
        this.albums = data.albums;
        this.renderAlbums();
    }
    
    async loadAlbum(albumId) {
        const album = this.albums.find(a => a.id === albumId);
        this.currentAlbum = album;
        
        // Load media for this album
        const response = await fetch(`albums/${albumId}/media.json`);
        const data = await response.json();
        this.media = data.media;
        
        this.renderAlbumView();
    }
    
    renderAlbums() {
        const container = document.getElementById('albums-container');
        container.innerHTML = this.albums.map(album => `
            <div class="album-card" onclick="gallery.loadAlbum('${album.id}')">
                <img src="${album.cover}" alt="${album.name}">
                <div class="album-info">
                    <h3>${album.name}</h3>
                    <p>${album.description}</p>
                    <span class="media-count">${album.media_count} items</span>
                </div>
            </div>
        `).join('');
    }
    
    renderAlbumView() {
        const container = document.getElementById('media-container');
        container.innerHTML = this.media.map((item, index) => `
            <div class="media-item" onclick="gallery.openViewer(${index})">
                ${this.renderThumbnail(item)}
            </div>
        `).join('');
    }
    
    renderThumbnail(item) {
        if (item.type === 'image') {
            return `<img src="${item.thumbnail}" alt="${item.title}" loading="lazy">`;
        } else if (item.type === 'video') {
            return `
                <video src="${item.thumbnail}" muted></video>
                <div class="play-icon">▶</div>
            `;
        }
    }
    
    openViewer(index) {
        this.currentIndex = index;
        this.showViewer();
    }
    
    showViewer() {
        const item = this.media[this.currentIndex];
        const viewer = document.getElementById('viewer');
        
        viewer.innerHTML = `
            <div class="viewer-content">
                <div class="viewer-header">
                    <button onclick="gallery.closeViewer()">✕</button>
                    <span>${item.title}</span>
                    <div class="viewer-controls">
                        <button onclick="gallery.previous()">◀</button>
                        <button onclick="gallery.next()">▶</button>
                        <button onclick="gallery.toggleFullscreen()">⛶</button>
                    </div>
                </div>
                <div class="viewer-body">
                    ${this.renderMedia(item)}
                </div>
                <div class="viewer-footer">
                    <span>${this.currentIndex + 1} of ${this.media.length}</span>
                </div>
            </div>
        `;
        
        viewer.classList.add('active');
        recordView(item.id);
    }
    
    renderMedia(item) {
        if (item.type === 'image') {
            return `<img src="${item.url}" alt="${item.title}" class="zoomable">`;
        } else if (item.type === 'video') {
            return `
                <video src="${item.url}" controls autoplay>
                    Your browser does not support video.
                </video>
            `;
        }
    }
    
    next() {
        if (this.currentIndex < this.media.length - 1) {
            this.currentIndex++;
            this.showViewer();
        }
    }
    
    previous() {
        if (this.currentIndex > 0) {
            this.currentIndex--;
            this.showViewer();
        }
    }
    
    closeViewer() {
        document.getElementById('viewer').classList.remove('active');
        this.stopSlideshow();
    }
    
    startSlideshow() {
        this.slideshowTimer = setInterval(() => {
            if (this.currentIndex < this.media.length - 1) {
                this.next();
            } else {
                this.currentIndex = 0;
                this.showViewer();
            }
        }, 5000);
    }
    
    stopSlideshow() {
        if (this.slideshowTimer) {
            clearInterval(this.slideshowTimer);
            this.slideshowTimer = null;
        }
    }
}

// Statistics Tracker
function recordView(mediaId) {
    fetch('?action=record_view', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ media_id: mediaId })
    });
}

// Initialize
const gallery = new GalleryManager();
document.addEventListener('DOMContentLoaded', () => {
    gallery.loadAlbums();
});
```

---

## Security

### File Access Control

```php
function validateMediaPath($path): bool {
    // Prevent directory traversal
    if (strpos($path, '..') !== false) {
        return false;
    }
    
    // Ensure path is within allowed directories
    $allowedDirs = ['albums/', 'media/', 'previews/'];
    $realPath = realpath($path);
    
    foreach ($allowedDirs as $dir) {
        if (strpos($realPath, realpath($dir)) === 0) {
            return true;
        }
    }
    
    return false;
}
```

### Download Protection

```php
function serveMedia($path) {
    if (!validateMediaPath($path)) {
        http_response_code(403);
        die('Access denied');
    }
    
    if (!file_exists($path)) {
        http_response_code(404);
        die('File not found');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $path);
    
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    
    readfile($path);
}
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Thumbnails not showing | Missing preview files | Generate thumbnails on upload |
| Slideshow not working | JavaScript error | Check browser console |
| Stats not updating | File permissions | Check `data/` folder permissions |
| Videos not playing | Format not supported | Convert to MP4/WebM |

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
