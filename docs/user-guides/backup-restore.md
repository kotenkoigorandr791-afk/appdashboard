# Backup & Restore

Protect your data with automated backups and tested restore procedures.

---

## What Gets Backed Up

| Component | Included | Method |
|-----------|----------|--------|
| **PostgreSQL Database** | All tables (users, apps data, config, audit) | `pg_dump` (custom format) |
| **User Files** | Cloud uploads, thumbnails, OnlyOffice cache, receipts | `tar.gz` (incremental capable) |
| **Configuration** | `.env`, `docker-compose.yml`, nginx configs, SSL certs | `tar.gz` |
| **MCP Package** | Built `appdashboard-mcp.tgz` | Copied |

---

## Backup Types

| Type | Contents | Size | Time | Use Case |
|------|----------|------|------|----------|
| **Full** | DB + Files + Config + MCP | Large | 5–30 min | Disaster recovery, migration |
| **Database Only** | DB dump | Medium | 1–5 min | Hourly, point-in-time recovery |
| **Files Only** | User uploads | Large | 5–20 min | Daily (if DB backed up hourly) |
| **Config Only** | Config files | Tiny | <1 min | Before changes |

---

## Automated Schedules (Recommended)

Admin → **Backup** → **Schedules** → **Add Schedule**

### Production Setup

| Schedule | Type | Cron | Retention | Offsite |
|----------|------|------|-----------|---------|
| Hourly DB | Database | `0 * * * *` | 7 days | S3 |
| Daily Full | Full | `0 2 * * *` | 30 days | S3 + Rclone |
| Weekly Full | Full | `0 3 * * 0` | 90 days | S3 (different bucket) |
| Pre-Update | Manual Full | — | 1 year | Local + S3 |

### Schedule Config

```json
{
  "name": "Daily Full Backup",
  "type": "full",
  "cron": "0 2 * * *",
  "retention_days": 30,
  "compress": true,
  "encrypt": true,
  "encryption_key": "auto-generated-or-custom",
  "offsite": {
    "provider": "s3",
    "config": {
      "endpoint": "s3.eu-central-1.amazonaws.com",
      "bucket": "appdashboard-backups",
      "path_prefix": "daily/",
      "encryption_key": "different-from-local"
    }
  }
}
```

---

## Manual Backup

### Via Web UI

Admin → **Backup** → **Create Backup** → Select type → **Start**

### Via API

```bash
# Full backup
curl -X POST -H "Authorization: Bearer $TOKEN" \
  https://your-domain.com/public-api/v1/backup \
  -d '{"type": "full", "name": "pre-migration-2026-01-15"}'

# Database only
curl -X POST -H "Authorization: Bearer $TOKEN" \
  https://your-domain.com/public-api/v1/backup \
  -d '{"type": "database"}'
```

### Via CLI (SSH to Server)

```bash
# Database
docker compose exec appdashboard-db pg_dump -U appdashboard -Fc appdashboard > backup_$(date +%F).dump

# Files
docker compose exec appdashboard tar -czf /tmp/files_$(date +%F).tar.gz -C /var/www/html/storage/app/public .

# Download
docker cp appdashboard:/tmp/backup_2026-01-15.dump ./backup_2026-01-15.dump
```

---

## Restore

### Via Web UI

Admin → **Backup** → List → Click **Restore** on desired backup → Select components → **Confirm**

### Via API

```bash
# Restore everything
curl -X POST -H "Authorization: Bearer $TOKEN" \
  https://your-domain.com/public-api/v1/backup/backup_123/restore \
  -d '{"components": ["database", "files", "config"]}'

# Restore only database
curl -X POST -H "Authorization: Bearer $TOKEN" \
  https://your-domain.com/public-api/v1/backup/backup_123/restore \
  -d '{"components": ["database"]}'
```

### Via CLI (Disaster Recovery)

```bash
# 1. Stop app (keep DB running)
docker compose stop appdashboard

# 2. Restore database
docker compose exec -T appdashboard-db pg_restore -U appdashboard -c -d appdashboard < backup.dump

# 3. Restore files
docker compose exec -T appdashboard tar -xzf - -C /var/www/html/storage/app/public < files.tar.gz

# 4. Restore config (if needed)
# Extract .env, docker-compose.yml from config backup

# 5. Start app
docker compose start appdashboard

# 6. Run migrations (if DB schema changed)
docker compose exec appdashboard php project/cli.php migrate
```

---

## Offsite Backup Providers

### S3 Compatible (AWS, MinIO, Wasabi, Backblaze B2, etc.)

Admin → Config → **Backup** → **Offsite Providers** → **Add S3**

| Field | Example |
|-------|---------|
| Name | `AWS EU Central` |
| Endpoint | `s3.eu-central-1.amazonaws.com` |
| Region | `eu-central-1` |
| Bucket | `appdashboard-backups-prod` |
| Access Key | `AKIA...` |
| Secret Key | `...` |
| Path Prefix | `production/` |
| Encryption Key | (auto or custom 32-char) |

### Rclone (Any Cloud)

```bash
# On server, configure rclone once
docker compose exec appdashboard rclone config
# Creates /root/.config/rclone/rclone.conf

# In AppDashboard: Admin → Backup → Offsite → Add Rclone
# Remote Name: myremote
# Remote Path: backups/appdashboard/
# Rclone Config: (base64 of rclone.conf)
```

### SFTP

| Field | Example |
|-------|---------|
| Host | `backup.example.com` |
| Port | `22` |
| Username | `backupuser` |
| Password / Key | (SSH key preferred) |
| Path | `/backups/appdashboard/` |

---

## Encryption

- **At rest**: AES-256-GCM (configurable per backup)
- **Key management**: 
  - Auto-generated per backup (stored encrypted in DB)
  - Or custom key (you manage)
- **Offsite**: Separate encryption key recommended

**To decrypt manually**:
```bash
# If you have encryption key
openssl enc -d -aes-256-gcm -K <key_hex> -iv <iv_hex> -in backup.enc -out backup.tar.gz
```

---

## Verify Backups

### Automated Verification

Admin → Backup → **Schedules** → Enable **Verify after upload**

Runs: `pg_restore --list` + `tar -tzf` + checksum match

### Manual Verification

```bash
# Download backup
curl -H "Authorization: Bearer $TOKEN" \
  "https://your-domain.com/public-api/v1/backup/backup_123/download" -o backup.enc

# Verify (if not encrypted)
tar -tzf backup.tar.gz | head -20
pg_restore --list backup.dump | head -20

# Test restore to staging (recommended monthly)
# 1. Spin up staging Docker Compose
# 2. Restore backup
# 3. Smoke test critical paths
```

---

## Retention & Cleanup

| Policy | Implementation |
|--------|----------------|
| Keep last N backups | Schedule `retention_days` |
| Keep yearly | Manual "Pre-Update" backups (1 year) |
| Auto-delete expired | Cron job runs daily (built-in) |
| Offsite independent | Separate retention per provider |

---

## Migration / Server Move

1. **Source**: Create **Full** backup (manual)
2. **Download**: All 3 files (DB, Files, Config)
3. **Target**: Fresh Docker Compose install
4. **Restore**: Via CLI (see above)
5. **Update DNS**: Point domain to new server
6. **Test**: All apps, OAuth, WebDAV, MCP
7. **Decommission**: Old server after 48h verification

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Backup fails "no space" | Clean Docker: `docker system prune -a`; increase disk; exclude large dirs |
| `pg_dump` hangs | Check DB locks: `SELECT * FROM pg_locks WHERE NOT granted;`; kill long queries |
| Restore fails "schema mismatch" | Run migrations after restore: `php project/cli.php migrate` |
| Offsite upload fails | Check credentials; bucket exists; network egress allowed; try `rclone test` |
| Encryption key lost | **Data unrecoverable**; store keys in password manager |
| Backup takes too long | Enable incremental files backup; increase `pg_dump` parallel jobs (`-j 4`) |
| Files backup missing uploads | Check volume mount: `docker compose exec appdashboard ls /var/www/html/storage/app/public` |