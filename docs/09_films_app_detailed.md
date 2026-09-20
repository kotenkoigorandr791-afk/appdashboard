# Films (Movie Manager) Application - Detailed Documentation

**Path:** `apps/films/`  
**Type:** Full Application  
**Description:** Personal movie collection manager with TMDB integration.

---

## Overview

Films is a personal movie collection manager that allows users to catalog, organize, and manage their movie library with detailed information fetched from The Movie Database (TMDB).

---

## File Structure

```
apps/films/
├── index.php                     # Main entry point
├── controllers/                  # API controllers
│   ├── ApiController.php         # API endpoints for movies
│   └── StreamController.php      # Streaming handler
├── core/                         # Core files
│   ├── db.php                    # Database functions
│   ├── helpers.php               # Helper functions
│   ├── session.php               # Session management
│   └── upload_errors.php         # Upload error handling
├── models/                       # Models
│   ├── MovieModel.php            # Movie model
└── config.json                   # TMDB API configuration
├── background.png                # Default background image
└── comments/                     # User comments per movie
```

## Configuration Files

| File | Purpose |
|------|---------|
| `config.json` | TMDB API key and configuration |
| `background.png` | Default page background |

## Key Features

### Movie Catalog
- Add movies by TMDB ID or manual entry
- Automatic metadata fetching (title, overview, poster, backdrop)
- Personal ratings and watched status tracking
- Custom tags and categories
- Bulk import/export of collections

### Search & Discovery
- Search by title, genre, year
- Filter by rating, watched status, recently added
- Sort by various criteria (title, date, rating)
- Collections and watchlists

### User Features
- Personal ratings (1-10 scale)
- Watch status tracking (watched/watching/to-watch)
- Custom notes and comments
- Custom posters and backgrounds
- Progress tracking for multi-part movies

### TMDB Integration
- Pull movie data: title, release date, runtime, genres
- Fetch poster and backdrop images
- Get cast and crew information
- Retrieve similar movies recommendations
- Rate movies on TMDB (optional)

### Data Storage
Movies stored in PostgreSQL database (`films` schema):

```sql
Schema: films
├── movies
│   ├── id
│   ├── tmdb_id
│   ├── title
│   ├── overview
│   ├── poster_path
│   ├── backdrop_path
│   ├── release_date
│   ├── rating
│   ├── watched_status
│   ├── custom_rating
│   └── ...
└── users
    ├── user_movies
    │   ├── id
    │   ├── user_id
    │   ├── movie_id
    │   ├── watched_status
    │   ├── custom_rating
    │   └── comments
```

## API Integration

### TMDB API
Uses public TMDB API for movie data fetching. Requires API key configured in `config.json`.

### PHP Endpoints
- `GET /api/movies/search` - Search movies
- `GET /api/movies/tmdb/{id}` - Get movie details by TMDB ID
- `POST /api/movies` - Add new movie
- `PUT /api/movies/{id}` - Update movie data
- `DELETE /api/movies/{id}` - Remove movie

## Security Features

- API key stored server-side (not exposed to frontend)
- Rate limiting on TMDB API calls
- Input validation for manual entries
- File permissions for data storage

## Performance

- Database queries with indexing
- TMDB API caching
- Pagination for large collections
- Optimized image handling

## Internationalization

Supports all 6 interface languages (en, uk, ru, be, pl, cs).

## Deployment Notes

- TMDB API key required (free tier available)
- PostgreSQL schema setup needed
- Write permissions for database
