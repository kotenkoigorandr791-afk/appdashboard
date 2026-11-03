# Set Up WebDAV Access

Mount your Cloud storage as a network drive on any OS.

---

## Prerequisites

- AppDashboard running with Cloud app enabled
- Your user has `cloud` app access (Admin → Users → Edit → App Access)
- HTTPS recommended (required for some clients)

---

## Get Your WebDAV URL

```
https://your-domain.com/webdav
```

> **Note**: Use the same domain as your dashboard. No `/public-api` prefix.

---

## Windows

### Option A: Map Network Drive (Built-in)

1. Open **This PC** → **Map network drive**
2. Drive: `Z:` (or any letter)
3. Folder: `https://your-domain.com/webdav`
4. ✅ **Connect using different credentials**
5. Click **Finish**
6. Enter:
   - **Username**: your AppDashboard email
   - **Password**: your AppDashboard password (or app token)
7. ✅ **Remember my credentials**

### Option B: WinSCP (More Reliable)

1. Download [WinSCP](https://winscp.net/)
2. New Site:
   - Protocol: **WebDAV**
   - Host: `your-domain.com`
   - Port: `443`
   - User: your email
   - Password: your password
3. Save → Login → Shows as drive letter

---

## macOS

### Finder (Built-in)

1. Finder → **Go** → **Connect to Server** (⌘K)
2. Server Address: `https://your-domain.com/webdav`
3. Click **Connect**
4. Enter email + password
5. Appears in Finder sidebar under **Locations**

### Mountain Duck (Paid, Better Sync)

1. Install [Mountain Duck](https://mountainduck.io/)
2. New Bookmark → WebDAV/HTTPS
3. Same credentials
4. Mounts as native disk with sync status icons

---

## Linux

### GNOME Files (Nautilus) / KDE Dolphin

1. File Manager → **Other Locations** / **Network**
2. Enter: `davs://your-domain.com/webdav`
3. Enter credentials when prompted

### Command Line (davfs2)

```bash
# Install
sudo apt install davfs2        # Debian/Ubuntu
sudo dnf install davfs2        # Fedora
sudo pacman -S davfs2          # Arch

# Configure
sudo usermod -aG davfs2 $USER
# Log out/in

# Mount
mkdir -p ~/cloud
mount -t davfs https://your-domain.com/webdav ~/cloud -o uid=$UID

# Auto-mount (optional)
echo "https://your-domain.com/webdav  ~/cloud  davfs  user,noauto,uid=$UID  0  0" | sudo tee -a /etc/fstab
```

### rclone (Advanced, Sync)

```bash
# Configure
rclone config
# n) new → name: appdashboard → type: webdav → url: https://your-domain.com/webdav → user: email → pass: password

# Sync
rclone sync ~/local/folder appdashboard:remote/folder --progress
rclone mount appdashboard: ~/mount --vfs-cache-mode full
```

---

## Mobile

### iOS (Files App)

1. Files app → **Browse** → **...** → **Connect to Server**
2. Server: `https://your-domain.com/webdav`
3. Enter credentials
4. Appears under **Shared**

### Android

- **Solid Explorer** / **FX File Explorer** / **WebDAV Navigator**: Add WebDAV connection
- **DAVx⁵**: For CalDAV/CardDAV (not file sync)

---

## Authentication Options

| Method | How | Best For |
|--------|-----|----------|
| **Email + Password** | Direct credentials | Quick setup |
| **App Token** | Generate in Profile → API Tokens | Scripts, rclone, long-term |
| **OAuth** | Not supported directly on WebDAV | N/A |

**App Token** (recommended for automation):
1. Profile → **API Tokens** → **Create Token**
2. Name: `WebDAV-rclone`
3. Scopes: `cloud`
4. Use token as **password** (username = email)

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| "Authentication failed" | Check email/password; try app token; verify `cloud` app access |
| "Could not connect" | Verify URL (trailing slash?); check HTTPS cert; try HTTP for testing |
| Read-only on Windows | Windows WebDAV client bug; use WinSCP or rclone |
| Slow / timeouts | Increase timeout in client; check nginx `proxy_read_timeout` |
| Files not appearing | Refresh; check Cloud app in browser; verify file permissions |
| "File too large" | Increase `client_max_body_size` in nginx (default 2GB) |
| Certificate error | Use valid TLS cert (Let's Encrypt); or add CA to trust store |

---

## Public WebDAV (Read-Only Sharing)

Share a folder publicly without login:

1. Cloud → Right-click folder → **Share via WebDAV**
2. Copy URL: `https://your-domain.com/webdav/public/abc123/`
3. Recipient mounts with **no credentials** (or username `public`, password = share password)

---

## Security Notes

- Always use **HTTPS** in production
- WebDAV transmits credentials in headers (Basic Auth over TLS)
- Use **app tokens** instead of main password for scripts
- Public shares: set expiry + password for sensitive data
- Audit: Admin → Audit Logs → filter `webdav`