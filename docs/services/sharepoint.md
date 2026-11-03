# Sharepoint — Public Share Landing Pages

**Web UI**: `/sharepoint/s/{token}` **Auth**: None (public) or password

---

## Overview

Sharepoint provides **branded, user-friendly landing pages** for public shares — similar to Dropbox/OneDrive share links. Unlike the minimal `/sharedFile` viewer or raw `/s/{token}` redirect, Sharepoint renders a full page with navigation, previews, and metadata.

---

## Features

| Feature | Description |
|---------|-------------|
| **Branded landing page** | Custom title, description, logo, theme (light/dark/auto) |
| **Folder navigation** | Tree view for shared folders; breadcrumb navigation |
| **File previews** | Images, PDF, video, audio, text render inline |
| **Download options** | Download individual files or entire folder as ZIP |
| **Password protection** | Optional password gate before content loads |
| **Expiry countdown** | Visual timer showing time until link expires |
| **Access logging** | Owner sees who accessed when (IP hashed, user agent) |
| **QR code** | Auto-generated for mobile sharing |
| **Responsive** | Works on mobile, tablet, desktop |

---

## Creating a Sharepoint Link

### Via Web UI
1. Cloud → Right-click file/folder → **Share** → **Create Sharepoint Link**
2. Configure:
   - **Title** (default: filename/folder name)
   - **Description** (markdown supported)
   - **Password** (optional)
   - **Expires at** (date/time or never)
   - **Theme**: Light / Dark / Auto (system)
   - **Allow download**: On/Off
3. Copy link: `https://your-domain.com/sharepoint/s/abc123xyz`

### Via API

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" \
  https://your-domain.com/public-api/v1/sharepoint/shares \
  -d '{
    "resource_type": "cloud_folder",
    "resource_id": 456,
    "title": "Project Assets",
    "description": "Design files for Q1 campaign\n\n- Logos\n- Mockups\n- Brand guidelines",
    "password": "optional-secret",
    "expires_at": "2026-06-30T23:59:00Z",
    "theme": "auto",
    "allow_download": true
  }'
```

**Response**:
```json
{
  "data": {
    "token": "abc123xyz",
    "url": "https://your-domain.com/sharepoint/s/abc123xyz",
    "qr_code_url": "https://your-domain.com/sharepoint/s/abc123xyz/qr",
    "expires_at": "2026-06-30T23:59:00Z"
  }
}
```

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/sharepoint/shares` | List your Sharepoint shares |
| `POST` | `/sharepoint/shares` | Create Sharepoint share (see above) |
| `GET` | `/sharepoint/shares/{token}` | Get share metadata |
| `PATCH` | `/sharepoint/shares/{token}` | Update (title, description, password, expiry, theme, allow_download) |
| `DELETE` | `/sharepoint/shares/{token}` | Revoke share |
| `GET` | `/sharepoint/shares/{token}/access-log` | Access log: `timestamp`, `ip_hash`, `action`, `user_agent` |
| `GET` | `/sharepoint/s/{token}` | Public landing page (HTML) |
| `GET` | `/sharepoint/s/{token}/qr` | QR code image (PNG) |
| `GET` | `/sharepoint/s/{token}/download` | Download all as ZIP (if folder) |
| `GET` | `/sharepoint/s/{token}/file/{file_id}` | Preview/download individual file |

---

## Public Page Structure

```
https://your-domain.com/sharepoint/s/{token}
```

**Page sections**:
1. **Header**: Title, description, expiry badge, QR code button
2. **Toolbar**: Theme toggle, download all (if folder), password prompt (if protected)
3. **Content**:
   - **Folder**: Grid/list of files with thumbnails, names, sizes, modified dates
   - **File**: Full preview (image/PDF/video/audio/text) with download button
4. **Footer**: Powered by AppDashboard, report abuse link

---

## Password Protection Flow

1. User visits `/sharepoint/s/{token}`
2. If password set → **Password modal** appears
3. User enters password → Validated via AJAX
4. On success → Content loads; session cookie set (1 hour)
5. Wrong password (5 attempts) → 15 min lockout per IP

---

## Access Logs (Owner Only)

```bash
curl -H "Authorization: Bearer $TOKEN" \
  https://your-domain.com/public-api/v1/sharepoint/shares/abc123xyz/access-log
```

**Response**:
```json
{
  "data": [
    {
      "timestamp": "2026-01-15T14:30:00Z",
      "ip_hash": "a1b2c3d4...",
      "action": "view",
      "user_agent": "Mozilla/5.0 (iPhone; ...)",
      "country": "DE"
    },
    {
      "timestamp": "2026-01-15T14:35:00Z",
      "ip_hash": "a1b2c3d4...",
      "action": "download",
      "file_id": 789,
      "user_agent": "Mozilla/5.0 (iPhone; ...)"
    }
  ]
}
```

---

## Configuration (Admin)

Admin → Configuration → **Sharing** → **Sharepoint**:

| Setting | Default | Description |
|---------|---------|-------------|
| Enabled | Yes | Toggle Sharepoint feature |
| Default theme | `auto` | `light`, `dark`, `auto` |
| Custom logo | — | URL to logo (SVG/PNG, max 200px) |
| Custom favicon | — | URL to favicon |
| Custom CSS | — | URL to custom stylesheet |
| Show "Powered by" | Yes | Display AppDashboard branding |
| Access log retention | 90 days | Auto-purge older logs |
| Max shares per user | 50 | Separate from regular shares limit |

---

## Differences from Other Sharing Methods

| Feature | `/s/{token}` | `/sharedFile` | `/sharepoint/s/{token}` |
|---------|--------------|---------------|------------------------|
| **UI** | Minimal redirect | Bare viewer | Full branded page |
| **Folder support** | Single file only | Single file only | ✅ Full folder tree |
| **Navigation** | No | No | ✅ Breadcrumbs, tree |
| **Custom branding** | No | No | ✅ Title, description, logo, theme |
| **Download all (ZIP)** | No | No | ✅ |
| **QR code** | No | No | ✅ |
| **Access log** | Basic | Basic | ✅ Detailed |
| **Password** | ✅ | ✅ | ✅ |
| **Expiry** | ✅ | ✅ | ✅ |

---

## Use Cases

| Scenario | Recommended Method |
|----------|-------------------|
| Quick file send to colleague | `/s/{token}` (simple link) |
| Embed image/PDF in chat/email | `/sharedFile?t={token}` (direct preview) |
| Share project folder with client | `/sharepoint/s/{token}` (professional landing page) |
| Public portfolio / asset library | `/sharepoint/s/{token}` with custom branding |
| Time-sensitive confidential docs | `/sharepoint/s/{token}` with password + expiry |

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Page shows "Not found" | Token expired/revoked; check owner's share list |
| Password not accepted | Case-sensitive; check for spaces; owner can reset via PATCH |
| Theme not applying | Check `theme` value (`light`/`dark`/`auto`); clear browser cache |
| Folder empty | Resource deleted or moved; verify `resource_id` exists |
| Download all fails | Folder too large (>2GB); use WebDAV public or individual downloads |
| QR code not loading | Check `/sharepoint/s/{token}/qr` endpoint; ensure GD/ImageMagick in container |
| Custom CSS not working | Verify URL accessible (CORS); check browser console for CSP errors |