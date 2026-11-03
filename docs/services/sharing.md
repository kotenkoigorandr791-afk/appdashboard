# Public Sharing

Share files, movies, recipes, and any resource with **no login required** for recipients.

---

## Sharing Methods

| Method | Use Case | Access Control | Recipient Needs |
|--------|----------|----------------|-----------------|
| **Share Links** (`/s/{token}`) | Single resource (file, movie, recipe) | Password, expiry, download limit, preview-only | Browser |
| **WebDAV Public** (`/webdav/public/{token}/`) | Folder sync (read-only) | Token in URL; optional password | OS file manager, WebDAV client |
| **SharedFile Viewer** (`/sharedFile`) | Preview images, PDF, text in browser | Token only | Browser |
| **Sharepoint** (`/sharepoint/s/{token}`) | Branded landing page | Password, expiry, custom message | Browser |

---

## Share Links (`/s/{token}`)

### Create via Web UI
1. Navigate to any resource (Cloud file, Film, Recipe, etc.)
2. Click **Share** → **Create Link**
3. Configure:
   - **Password** (optional)
   - **Expires** (date/time or never)
   - **Max downloads** (optional)
   - **Allow preview** (view in browser without download)
4. Copy link: `https://your-domain.com/s/abc123xyz`

### Create via API

**Cloud**:
```bash
curl -X POST -H "Authorization: Bearer $TOKEN" \
  https://your-domain.com/public-api/v1/cloud/shares \
  -d '{"file_id": 123, "password": "secret", "expires_at": "2026-12-31T23:59:00Z", "max_downloads": 10, "allow_preview": true}'
```

**Films**: `POST /public-api/v1/films/shares` (body: `movie_id` or `collection_id`)

**Recipes**: `POST /public-api/v1/recipes/shares` (body: `recipe_id`)

**Generic** (any resource):
```bash
curl -X POST -H "Authorization: Bearer $TOKEN" \
  https://your-domain.com/public-api/v1/shares \
  -d '{"resource_type": "cloud_file", "resource_id": 123, "password": "..."}'
```

### Access Share

```
GET https://your-domain.com/s/{token}
```

- If **password protected** → prompts for password
- If **preview allowed** → renders in browser (images, PDF, video, audio, text)
- If **download only** → forces download
- Tracks: view count, download count, last accessed, IP (hashed)

### Manage Shares

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/public-api/v1/cloud/shares` | List your shares |
| `GET` | `/public-api/v1/cloud/shares/{token}` | Share details + stats |
| `PATCH` | `/public-api/v1/cloud/shares/{token}` | Update (password, expiry, limits) |
| `DELETE` | `/public-api/v1/cloud/shares/{token}` | Revoke immediately |

---

## WebDAV Public Access

### Create Public WebDAV Share

1. Cloud → Right-click folder → **Share via WebDAV**
2. Generates: `https://your-domain.com/webdav/public/{token}/`

### Mount (Read-Only)

```bash
# Linux (davfs2)
mount -t davfs https://your-domain.com/webdav/public/abc123/ /mnt/share -o ro,uid=$UID

# macOS Finder
Cmd+K → https://your-domain.com/webdav/public/abc123/

# Windows
net use Z: https://your-domain.com/webdav/public/abc123/ /user:public "" /persistent:no
```

**Authentication**: None required (token in URL path). Optional: HTTP Basic with username `public` and password = share password.

**Limitations**: Read-only. No locking, no write, no property changes.

---

## SharedFile Viewer (`/sharedFile`)

### Purpose
Lightweight viewer for **images, PDFs, text files** — no UI chrome, direct content.

### URL Format
```
https://your-domain.com/sharedFile?t={token}
```

### Features
- Images: Full-screen, zoom, pan, download button
- PDF: Page navigation, zoom, text selection, download
- Text/Code: Syntax highlighting, line numbers, wrap toggle
- Video/Audio: Native browser player
- Responsive: Works on mobile

### API
```bash
# Create (same as share link with allow_preview=true)
POST /public-api/v1/cloud/shares { "file_id": 123, "allow_preview": true }

# Access
GET /sharedFile?t={token}
```

---

## Sharepoint (`/sharepoint/s/{token}`)

### Purpose
Branded landing page with **custom message, branding, file list** — like Dropbox/OneDrive share pages.

### URL Format
```
https://your-domain.com/sharepoint/s/{token}
```

### Features
- Custom title & description
- Folder tree navigation (if folder shared)
- File previews (images, PDF, video)
- Download all as ZIP
- Password protection
- Expiry countdown
- Access log (owner sees who accessed when)

### Create via API
```bash
POST /public-api/v1/sharepoint/shares
{
  "resource_type": "cloud_folder",
  "resource_id": 456,
  "title": "Project Assets",
  "description": "Design files for Q1 campaign",
  "password": "optional",
  "expires_at": "2026-06-30T23:59:00Z",
  "theme": "light"  // or "dark", "auto"
}
```

---

## MCP Share Tools

```json
// Create share
{
  "name": "create_share",
  "arguments": {
    "file_id": 123,
    "password": "secret",
    "expires_at": "2026-12-31T23:59:00Z",
    "max_downloads": 10,
    "allow_preview": true
  }
}
```

Returns share URL + token.

---

## Security Considerations

| Risk | Mitigation |
|------|------------|
| Token enumeration | 256-bit random tokens (`/s/{32-char}`); rate limited (100/min/IP) |
| Password brute-force | 5 attempts → 15 min lockout per IP |
| Hotlinking/embedding | `X-Frame-Options: SAMEORIGIN`; `Referrer-Policy: strict-origin` |
| Search engine indexing | `noindex, nofollow` on all share pages; `robots.txt` disallows `/s/`, `/sharepoint/` |
| Data leakage | Owner sees access log; can revoke anytime; expiry enforced |
| Large file DoS | Streaming download; nginx `limit_rate_after 10m limit_rate 1m` |

---

## Access Logs (Owner View)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/public-api/v1/cloud/shares/{token}/access-log` | List accesses: `timestamp`, `ip_hash`, `action` (view/download), `user_agent` |
| `GET` | `/public-api/v1/sharepoint/shares/{token}/access-log` | Same for sharepoint |

---

## Bulk Operations

| Action | Method |
|--------|--------|
| Revoke all expired shares | `POST /public-api/v1/cloud/shares/cleanup-expired` |
| Extend expiry for all | `PATCH /public-api/v1/cloud/shares/bulk` with `expires_at` |
| Export share list | `GET /public-api/v1/cloud/shares/export?format=csv` |

---

## Configuration (Admin)

Admin → Configuration → **Sharing**:

| Setting | Default | Description |
|---------|---------|-------------|
| Max shares per user | 100 | Prevent abuse |
| Default expiry | 30 days | Auto-expire if not set |
| Allow passwordless shares | Yes | Can require password globally |
| Max file size for preview | 100 MB | Larger = download only |
| Allowed preview MIME types | images, PDF, text, video, audio | Extend for custom types |
| Access log retention | 90 days | Auto-purge |
| Sharepoint theme | auto | light/dark/auto |
| Custom branding | — | Logo, favicolor, custom CSS URL |

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Share not found" | Token expired/revoked; check owner's share list |
| Password not working | Case-sensitive; check for trailing spaces; owner can reset via PATCH |
| Preview not loading | File type not in allowed MIME list; size > limit; check nginx `client_max_body_size` |
| WebDAV mount fails | Ensure `/webdav/public/{token}/` not blocked by proxy; try trailing slash |
| Download count not incrementing | Only counts actual file downloads, not preview views |
| Sharepoint page blank | Check `theme` value; verify resource still exists; check browser console for CSP errors |
| Token in URL leaks in logs | Use password protection; set short expiry; use Sharepoint for sensitive shares |