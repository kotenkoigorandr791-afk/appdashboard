# Films Application - Detailed Documentation

**Path:** `apps/films/`  
**Total Lines of Code:** ~4,000  
**Main Entry:** `index.php` (4,022 lines)

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Core Components](#core-components)
4. [Database Schema](#database-schema)
5. [Features](#features)
6. [API Reference](#api-reference)
7. [Configuration](#configuration)
8. [Frontend Architecture](#frontend-architecture)

---

## Overview

The Films application is a **movie collection manager** with user comments system. It allows users to browse, search, and manage a movie database with community-style comments.

### Key Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 3 |
| Total JSON Files | 50+ |
| Languages Supported | 4 (EN, UK, RU, BE) |
| Main File Size | 4,022 lines |

---

## File Structure

```
apps/films/
├── index.php                          # Main interface (4,022 lines)
├── movies.json                        # Movie database
├── config.json                        # Application configuration
├── translations.php                   # UI translations (PHP array)
├── name.json                          # App name translations
├── background.png                     # Background image
├── icon.png                           # Application icon
├── Thumbs.db                          # Windows thumbnail cache
├── php_errors.log                     # PHP error log
├── QWEN.md                            # AI assistant notes
│
├── comments/                          # User comments (JSON files per movie)
│   └── {movie_id}.json                # Comments for each movie
│
└── .qwen/                             # Qwen Code settings
    ├── settings.json
    └── settings.json.orig
```

---

## Core Components

### 1. Main Interface (`index.php`)

**Purpose:** Main movie browsing interface with search, filtering, and comments.

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
function get_current_user_data(): array

// Movie operations
function loadMovies(): array
function searchMovies(string $query): array
function getMovieById(string $id): ?array
function filterMovies(array $filters): array

// Comments operations
function loadComments(string $movieId): array
function addComment(string $movieId, string $user, string $comment): bool
function deleteComment(string $movieId, int $commentId): bool
function updateComment(string $movieId, int $commentId, string $comment): bool
```

**URL Parameters:**
```php
$lang   = $_GET['lang']   ?? 'en';    // en, ru, uk, be
$theme  = $_GET['theme']  ?? 'light'; // light, dark
$color  = $_GET['color']  ?? '#85b503'; // Hex color code
```

---

### 2. Movie Database (`movies.json`)

**Structure:**
```json
{
    "movies": [
        {
            "id": "movie_001",
            "title": "Movie Title",
            "original_title": "Original Title",
            "year": 2024,
            "genre": ["Action", "Drama"],
            "director": "Director Name",
            "cast": ["Actor 1", "Actor 2"],
            "rating": 8.5,
            "duration": 120,
            "description": "Movie description...",
            "poster": "posters/movie_001.jpg",
            "trailer": "https://youtube.com/...",
            "language": "en",
            "country": "USA",
            "added_at": "2024-01-15T10:00:00Z",
            "views": 1234
        }
    ]
}
```

**Fields:**
| Field | Type | Description |
|-------|------|-------------|
| id | string | Unique movie identifier |
| title | string | Localized title |
| original_title | string | Original title |
| year | integer | Release year |
| genre | array | Genre list |
| director | string | Director name |
| cast | array | Cast members |
| rating | float | Rating (0-10) |
| duration | integer | Duration in minutes |
| description | string | Plot summary |
| poster | string | Poster image path |
| trailer | string | Trailer URL |
| language | string | Original language |
| country | string | Country of origin |
| added_at | string | ISO timestamp |
| views | integer | View count |

---

### 3. Comments System

**Comment File Structure (`comments/{movie_id}.json`):**
```json
{
    "comments": [
        {
            "id": 1,
            "user": "username",
            "comment": "Great movie!",
            "rating": 9,
            "created_at": "2024-01-15T12:00:00Z",
            "updated_at": null,
            "likes": 5,
            "dislikes": 0
        }
    ]
}
```

**Comment Functions:**
```php
function loadComments(string $movieId): array {
    $file = "comments/{$movieId}.json";
    if (!file_exists($file)) {
        return ['comments' => []];
    }
    return json_decode(file_get_contents($file), true);
}

function addComment(string $movieId, string $user, string $comment, int $rating = 0): bool {
    $data = loadComments($movieId);
    
    $newComment = [
        'id' => time(),
        'user' => $user,
        'comment' => $comment,
        'rating' => $rating,
        'created_at' => date('c'),
        'updated_at' => null,
        'likes' => 0,
        'dislikes' => 0
    ];
    
    $data['comments'][] = $newComment;
    
    return file_put_contents(
        "comments/{$movieId}.json",
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function deleteComment(string $movieId, int $commentId, string $user): bool {
    $data = loadComments($movieId);
    
    $data['comments'] = array_filter(
        $data['comments'],
        fn($c) => $c['id'] !== $commentId || $c['user'] !== $user
    );
    
    return file_put_contents(
        "comments/{$movieId}.json",
        json_encode(array_values($data['comments']), JSON_PRETTY_PRINT)
    ) !== false;
}
```

---

## Database Schema

### Shared Database Tables

The Films app uses the main `PostgreSQL appdashboard (/opt/AppDashboard/data/db.env)` database for:

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

**`visit_stats` table:**
```sql
CREATE TABLE visit_stats (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    app_name   TEXT NOT NULL,
    email      TEXT,
    visit_time INTEGER NOT NULL DEFAULT (strftime('%s','now'))
)
```

---

## Features

### 1. Movie Browsing

**Grid View:**
- Poster thumbnails
- Title and year display
- Rating badges
- Hover effects

**List View:**
- Detailed information
- Sortable columns
- Quick actions

### 2. Search & Filtering

**Search Options:**
- By title
- By director
- By cast member
- By genre
- By year range

**Filters:**
```javascript
{
    genre: ["Action", "Drama"],
    year_min: 2020,
    year_max: 2024,
    rating_min: 7.0,
    language: "en",
    country: "USA"
}
```

**Sort Options:**
- Title (A-Z, Z-A)
- Year (newest, oldest)
- Rating (highest, lowest)
- Views (most, least)
- Date added (newest, oldest)

### 3. Movie Details

**Detail View:**
- Full poster image
- Title and original title
- Release year and country
- Director and cast list
- Genre tags
- Rating display
- Duration
- Full description
- Trailer embed
- User comments

### 4. Comments System

**Features:**
- User authentication required
- Rating (0-10) with comment
- Edit own comments
- Delete own comments
- Like/dislike system
- Sort by date/rating/likes
- Pagination for long threads

**Comment Form:**
```html
<form id="comment-form" data-movie-id="movie_001">
    <textarea name="comment" placeholder="Write your review..."></textarea>
    <select name="rating">
        <option value="10">10 - Excellent</option>
        <option value="9">9 - Great</option>
        ...
        <option value="1">1 - Terrible</option>
    </select>
    <button type="submit">Post Comment</button>
</form>
```

### 5. User Roles

**Role-based Access:**
| Role | Value | Permissions |
|------|-------|-------------|
| Guest | 0 | View movies, post comments |
| Manager | 1 | Edit movie data |
| Admin | 2 | Full access, delete comments |

---

## API Reference

### Movie Endpoints

#### Get All Movies
```http
GET /index.php?action=get_movies

Response:
{
    "success": true,
    "movies": [...],
    "total": 100
}
```

#### Search Movies
```http
GET /index.php?action=search&q=matrix

Response:
{
    "success": true,
    "movies": [...],
    "query": "matrix"
}
```

#### Filter Movies
```http
POST /index.php?action=filter
Content-Type: application/json

{
    "genre": ["Action"],
    "year_min": 2020,
    "year_max": 2024,
    "rating_min": 7.0,
    "sort": "rating_desc"
}
```

#### Get Movie Details
```http
GET /index.php?action=get_movie&id=movie_001

Response:
{
    "success": true,
    "movie": { ... }
}
```

---

### Comments Endpoints

#### Get Comments
```http
GET /index.php?action=get_comments&movie_id=movie_001

Response:
{
    "success": true,
    "comments": [
        {
            "id": 1,
            "user": "username",
            "comment": "Great movie!",
            "rating": 9,
            "created_at": "2024-01-15T12:00:00Z",
            "likes": 5
        }
    ]
}
```

#### Add Comment
```http
POST /index.php?action=add_comment
Content-Type: application/json

{
    "movie_id": "movie_001",
    "comment": "Excellent film!",
    "rating": 9
}
```

#### Update Comment
```http
POST /index.php?action=update_comment
Content-Type: application/json

{
    "movie_id": "movie_001",
    "comment_id": 1,
    "comment": "Updated review..."
}
```

#### Delete Comment
```http
POST /index.php?action=delete_comment
Content-Type: application/json

{
    "movie_id": "movie_001",
    "comment_id": 1
}
```

#### Rate Comment
```http
POST /index.php?action=rate_comment
Content-Type: application/json

{
    "movie_id": "movie_001",
    "comment_id": 1,
    "vote": "like" // or "dislike"
}
```

---

## Configuration

### Application Config (`config.json`)

```json
{
    "items_per_page": 20,
    "enable_comments": true,
    "require_auth_for_comments": true,
    "enable_ratings": true,
    "default_sort": "rating_desc",
    "enable_trailers": true,
    "poster_path": "posters/",
    "cache_ttl": 3600
}
```

### Translations (`translations.php`)

```php
<?php
$translations = [
    'en' => [
        'title' => 'Movie Manager',
        'search' => 'Search movies...',
        'filter' => 'Filter',
        'sort' => 'Sort by',
        'rating' => 'Rating',
        'year' => 'Year',
        'genre' => 'Genre',
        'director' => 'Director',
        'cast' => 'Cast',
        'duration' => 'Duration',
        'description' => 'Description',
        'trailer' => 'Trailer',
        'comments' => 'Comments',
        'add_comment' => 'Add Comment',
        'your_rating' => 'Your Rating',
        'submit' => 'Submit',
        'minutes' => 'min'
    ],
    'uk' => [
        'title' => 'Менеджер фільмів',
        'search' => 'Пошук фільмів...',
        'filter' => 'Фільтр',
        // ... more translations
    ],
    'ru' => [
        'title' => 'Менеджер фильмов',
        'search' => 'Поиск фильмов...',
        // ... more translations
    ],
    'be' => [
        'title' => 'Менеджэр фільмаў',
        'search' => 'Пошук фільмаў...',
        // ... more translations
    ]
];
```

---

## Frontend Architecture

### Main JavaScript Modules

```javascript
// Movie Browser
class MovieBrowser {
    constructor() {
        this.movies = [];
        this.filteredMovies = [];
        this.currentPage = 1;
        this.itemsPerPage = 20;
        this.view = 'grid'; // grid or list
    }
    
    async loadMovies() {
        const response = await fetch('?action=get_movies');
        const data = await response.json();
        this.movies = data.movies;
        this.filteredMovies = data.movies;
        this.render();
    }
    
    filter(filters) {
        this.filteredMovies = this.movies.filter(movie => {
            return Object.entries(filters).every(([key, value]) => {
                if (Array.isArray(value)) {
                    return value.includes(movie[key]);
                }
                if (key === 'year_min') return movie.year >= value;
                if (key === 'year_max') return movie.year <= value;
                if (key === 'rating_min') return movie.rating >= value;
                return movie[key] === value;
            });
        });
        this.render();
    }
    
    sort(criteria) {
        const [field, order] = criteria.split('_');
        this.filteredMovies.sort((a, b) => {
            if (order === 'asc') {
                return a[field] > b[field] ? 1 : -1;
            } else {
                return a[field] < b[field] ? 1 : -1;
            }
        });
        this.render();
    }
    
    render() {
        const container = document.getElementById('movies-container');
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        const pageMovies = this.filteredMovies.slice(start, end);
        
        container.innerHTML = pageMovies.map(movie => this.renderMovieCard(movie)).join('');
    }
    
    renderMovieCard(movie) {
        return `
            <div class="movie-card" data-id="${movie.id}">
                <img src="${movie.poster}" alt="${movie.title}">
                <div class="movie-info">
                    <h3>${movie.title} (${movie.year})</h3>
                    <div class="rating">★ ${movie.rating}</div>
                    <div class="genres">${movie.genre.map(g => `<span>${g}</span>`).join('')}</div>
                </div>
            </div>
        `;
    }
}

// Comments Manager
class CommentsManager {
    constructor(movieId) {
        this.movieId = movieId;
        this.comments = [];
    }
    
    async loadComments() {
        const response = await fetch(`?action=get_comments&movie_id=${this.movieId}`);
        const data = await response.json();
        this.comments = data.comments;
        this.render();
    }
    
    async addComment(text, rating) {
        const response = await fetch('?action=add_comment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                movie_id: this.movieId,
                comment: text,
                rating: rating
            })
        });
        
        const data = await response.json();
        if (data.success) {
            await this.loadComments();
        }
    }
    
    async deleteComment(commentId) {
        const response = await fetch('?action=delete_comment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                movie_id: this.movieId,
                comment_id: commentId
            })
        });
        
        const data = await response.json();
        if (data.success) {
            await this.loadComments();
        }
    }
    
    render() {
        const container = document.getElementById('comments-container');
        container.innerHTML = this.comments.map(comment => `
            <div class="comment" data-id="${comment.id}">
                <div class="comment-header">
                    <span class="user">${comment.user}</span>
                    <span class="rating">★ ${comment.rating}</span>
                    <span class="date">${new Date(comment.created_at).toLocaleDateString()}</span>
                </div>
                <div class="comment-text">${comment.comment}</div>
                <div class="comment-actions">
                    <button class="like">👍 ${comment.likes}</button>
                    <button class="dislike">👎 ${comment.dislikes}</button>
                </div>
            </div>
        `).join('');
    }
}
```

---

## Security

### Authentication

All write operations require valid session:

```php
session_start();
if (!isset($_SESSION['user']['email'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Authentication required']));
}
```

### Comment Moderation

**Admin Actions:**
```php
function canDeleteComment($userRole, $commentUser, $currentUser): bool {
    // Admins can delete any comment
    if ($userRole >= 2) return true;
    
    // Users can delete their own comments
    return $commentUser === $currentUser;
}
```

### Input Validation

```php
function validateComment($comment): array {
    $errors = [];
    
    if (empty($comment) || strlen($comment) < 10) {
        $errors[] = 'Comment must be at least 10 characters';
    }
    
    if (strlen($comment) > 5000) {
        $errors[] = 'Comment must be less than 5000 characters';
    }
    
    // Sanitize HTML
    $comment = strip_tags($comment);
    
    return ['valid' => empty($errors), 'errors' => $errors, 'comment' => $comment];
}
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Comments not loading | File permissions | Check `comments/` folder permissions |
| Search not working | JSON parse error | Validate `movies.json` syntax |
| Posters not showing | Wrong path | Verify `poster_path` in config |
| Rating not saving | Auth required | Ensure user is logged in |

### Debug Mode

```php
// Enable in index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
