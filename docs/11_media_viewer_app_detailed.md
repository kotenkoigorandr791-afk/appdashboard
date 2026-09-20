# Media Viewer Application - Detailed Documentation

**Path:** `apps/media_viewer/`  
**Type:** Full Application  
**Description:** Media Gallery with face recognition and EXIF viewing.

---

## Overview

Media Viewer is a gallery application that provides image and video browsing with advanced features including face recognition, EXIF data viewing, and tagging.

---

## File Structure

```
apps/media_viewer/
├── index.php                     # Main entry point
├── controllers/                  # MVC controllers
│   ├── MediaController.php
│   └── GalleryController.php
├── core/                         # Core models, services, helpers
│   ├── MediaModel.php
│   ├── FaceRecognition.php
│   └── ExifParser.php
├── background_light.jpg          # Light theme background
├── background_dark.jpg           # Dark theme background
├── i18n/                         # Translation files (6 languages)
└── assets/                       # Static assets
```

## Configuration Files

| File | Purpose |
|------|---------|
| `background_light.jpg` | Light theme background image |
| `background_dark.jpg` | Dark theme background image |
| `i18n/` | Translation files (en, uk, ru, be, pl, cs) |

## Key Features

### Image Gallery
- Grid/list view toggle
- Infinite scroll and lazy loading
- Thumbnail generation for fast browsing
- Metadata overlay (EXIF data)
- Face detection and labeling
- Batch operations (move, delete, tag)
- Favorites and collections
- Search and filter by date, location, person

### Face Recognition
- Automatic face detection in images
- Face clustering (group same person)
- Manual labeling and training
- Face search across all photos
- Privacy controls for face data

### EXIF Data
- View camera model, aperture, ISO, shutter speed
- GPS coordinates and map view
- Date/time information
- Custom metadata tags
- Edit and remove EXIF data

### Editing
- Basic image editing (crop, rotate, resize)
- Filter and adjustment tools
- Batch editing for multiple images
- Watermarking (optional)

### Data Storage
Media stored as files in `data/` directory:

```
apps/media_viewer/data/
├── photos/           # Photo files
├── metadata/         # EXIF and face data JSON
├── thumbnails/       # Generated thumbnails
└── faces/            # Face recognition embeddings
```

## API Endpoints

### Media Operations
- `GET /api/media` - List media items (with filtering)
- `POST /api/media/upload` - Upload photo/video
- `GET /api/media/{id}` - Get media details
- `DELETE /api/media/{id}` - Delete media item
- `POST /api/media/{id}/face` - Detect faces
- `GET /api/media/{id}/exif` - Get EXIF data
- `PUT /api/media/{id}/tags` - Update tags

### Face Operations
- `GET /api/faces` - List detected faces
- `POST /api/faces/label` - Label a face
- `GET /api/faces/search` - Search by face

## Security Features

- File permission restrictions on media storage
- Rate limiting on uploads
- EXIF data sanitization (removes GPS if configured)
- Face data privacy controls

## Performance

- Lazy loading and pagination
- Thumbnail caching
- WebP conversion for faster loading
- Client-side image rendering when possible
- Background processing for face detection

## Internationalization

Supports all 6 interface languages (en, uk, ru, be, pl, cs).

## Dependencies

### PHP
- Intervention/Image: Image processing
- OpenCV/face detection: Face recognition
- PHP EXIF: Metadata reading

### JavaScript
- PhotoSwipe: Image lightbox
- React/Vue components: Gallery UI
- Canvas API: Image editing

## Deployment Notes

- Sufficient disk space for media storage
- PHP image extensions (GD/Imagick)
- Redis recommended for face embeddings cache
- Background worker for face recognition
