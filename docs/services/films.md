# Films — Media Library

**Web UI**: `/apps/films/` **API Base**: `/public-api/v1/films` **Auth**: Bearer + `films` app access

---

## User Functions

| Feature | Description |
|---------|-------------|
| **Movie Library** | Add movies via file upload or external link; auto-fetch metadata (TMDB, OMDB) |
| **Streaming** | Adaptive bitrate (HLS/DASH); resume playback; multiple quality options |
| **Subtitles** | Upload `.srt`, `.vtt`, `.ass`; select language; offset adjustment |
| **Metadata** | Title, year, genre, director, cast, plot, poster, backdrop, ratings (IMDb, TMDB) |
| **Multi-Language** | UI + content in 12 languages; per-movie audio/subtitle tracks |
| **Watch Progress** | Auto-save position; continue watching; mark watched/unwatched |
| **Collections** | Group movies (e.g., "Marvel", "80s Horror"); custom artwork |
| **Public Sharing** | Share single movie or collection via link (optional password/expiry) |
| **Search & Filter** | By title, genre, year, rating, watched status, collection |
| **Statistics** | Total movies, hours watched, genre breakdown, yearly stats |

---

## API Endpoints

### Movies

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/movies` | List movies. Query: `cursor`, `limit`, `search`, `genre`, `year_from`, `year_to`, `watched`, `collection_id`, `sort` (title/year/added/rating), `order` |
| `POST` | `/movies` | Add movie. Multipart: `file` (video), `metadata?` (JSON), `poster?`, `backdrop?`. Or JSON: `external_url`, `metadata` |
| `GET` | `/movies/{id}` | Full movie details + metadata + streams + subtitles + progress |
| `PATCH` | `/movies/{id}` | Update metadata. Body: any metadata field, `watched`, `progress_seconds` |
| `DELETE` | `/movies/{id}` | Delete movie (removes file + metadata + subtitles) |
| `GET` | `/movies/{id}/stream` | Stream video. Supports `Range` header for seeking. Query: `quality` (auto/1080p/720p/480p) |
| `GET` | `/movies/{id}/hls` | HLS manifest (`.m3u8`) for adaptive streaming |
| `GET` | `/movies/{id}/dash` | DASH manifest (`.mpd`) |
| `POST` | `/movies/{id}/transcode` | Trigger re-transcode (admin/owner). Body: `qualities[]` |
| `GET` | `/movies/{id}/transcode/status` | Transcode job status |

### Subtitles

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/movies/{id}/subtitles` | List subtitle tracks |
| `POST` | `/movies/{id}/subtitles` | Upload subtitle file. Multipart: `file`, `language`, `label?`, `default?` |
| `GET` | `/movies/{id}/subtitles/{sub_id}` | Download subtitle file |
| `PATCH` | `/movies/{id}/subtitles/{sub_id}` | Update track: `language`, `label`, `default`, `offset_ms` |
| `DELETE` | `/movies/{id}/subtitles/{sub_id}` | Delete subtitle track |
| `POST` | `/movies/{id}/subtitles/search` | Search OpenSubtitles. Body: `query`, `language` |

### Collections

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/collections` | List collections |
| `POST` | `/collections` | Create collection. Body: `name`, `description?`, `poster?` |
| `GET` | `/collections/{id}` | Collection details + movies |
| `PATCH` | `/collections/{id}` | Update collection |
| `DELETE` | `/collections/{id}` | Delete collection (movies remain) |
| `POST` | `/collections/{id}/movies` | Add movie to collection. Body: `movie_id` |
| `DELETE` | `/collections/{id}/movies/{movie_id}` | Remove movie from collection |

### Genres & Search

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/genres` | List all genres with movie counts |
| `GET` | `/search` | Unified search. Query: `q`, `type` (movie/collection), `limit` |
| `GET` | `/stats` | Library statistics |

### Public Shares

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/shares` | List your movie shares |
| `POST` | `/shares` | Create share. Body: `movie_id` or `collection_id`, `password?`, `expires_at?`, `allow_download?` |
| `PATCH` | `/shares/{token}` | Update share |
| `DELETE` | `/shares/{token}` | Revoke share |

---

## Web Routes

| Path | Description |
|------|-------------|
| `/apps/films/` | Film library UI (grid/list, player, collections sidebar) |

---

## MCP Tools (AI Assistant)

| Tool | Description |
|------|-------------|
| `list_movies` | List movies with filters |
| `get_movie` | Full movie details |
| `search_movies` | Search by title/genre/year |

---

## Supported Formats

| Container | Video Codecs | Audio Codecs | Notes |
|-----------|--------------|--------------|-------|
| MP4 | H.264, H.265 (HEVC), AV1 | AAC, MP3, Opus, AC3 | Native browser playback |
| MKV | H.264, H.265, AV1, VP9 | AAC, FLAC, Opus, DTS, TrueHD | Requires transcode for browser |
| WebM | VP8, VP9, AV1 | Opus, Vorbis | Native |
| AVI, MOV, TS | Various | Various | Transcode on upload |

**Transcoding**: Automatic HLS/DASH generation for non-browser formats. Uses FFmpeg (CPU) or VAAPI/NVENC (hardware) if configured.

---

## Configuration (Admin)

Admin → Configuration → **Films**:

| Setting | Description |
|---------|-------------|
| TMDB API Key | For auto-metadata fetch (free at themoviedb.org) |
| OMDB API Key | Alternative metadata source |
| OpenSubtitles API Key | For subtitle search |
| Transcode qualities | Which HLS/DASH renditions to generate (default: 1080p, 720p, 480p) |
| Hardware acceleration | `none`, `vaapi` (Intel/AMD), `nvenc` (NVIDIA), `videotoolbox` (Apple Silicon) |
| Max concurrent transcodes | Default: 2 |
| Thumbnail interval | Generate preview thumbnails every N seconds (default: 60) |
| External storage | Mount path for movies outside container (Docker volume) |

---

## Keyboard Shortcuts (Player)

| Key | Action |
|-----|--------|
| `Space` / `K` | Play/Pause |
| `←` / `→` | Seek -10s / +10s |
| `J` / `L` | Seek -30s / +30s |
| `↑` / `↓` | Volume up/down |
| `M` | Mute |
| `F` | Fullscreen |
| `C` | Toggle subtitles |
| `S` | Next subtitle track |
| `1`–`9` | Quality preset (1=lowest) |
| `Shift+>` / `Shift+<` | Speed up/down |

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Video won't play (black screen) | Check browser codec support; transcode to H.264/MP4; verify FFmpeg in container |
| Subtitles out of sync | Use offset adjustment in UI or `offset_ms` API; re-download correct subtitle |
| Transcode stuck/failed | Check `docker compose logs appdashboard` for FFmpeg errors; verify hardware accel config |
| Metadata not fetching | Verify TMDB/OMDB API keys; check rate limits; manual edit via PATCH |
| Large file upload fails | Increase `client_max_body_size` (nginx), `upload_max_filesize` (PHP), chunked upload in UI |
| HLS/DASH not loading | CORS headers on video segments; check `Access-Control-Allow-Origin: *` in nginx |
| External storage not showing | Verify Docker volume mount in `docker-compose.yml`; check permissions `www-data:www-data` |