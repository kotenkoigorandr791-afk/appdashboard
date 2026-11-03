# Troubleshooting

Common issues, diagnostics, and solutions.

---

## Quick Diagnostics

### Health Checks

```bash
# Overall
curl -s https://your-domain.com/health | jq .

# Database
curl -s https://your-domain.com/health/db | jq .

# Redis
curl -s https://your-domain.com/health/redis | jq .

# OnlyOffice
curl -s https://your-domain.com/health/onlyoffice | jq .
```

### Container Status

```bash
docker compose ps
docker compose logs -f --tail=100 appdashboard
docker compose logs -f --tail=100 appdashboard-db
docker stats --no-stream
```

### System Resources

```bash
# Disk
df -h /var/lib/docker/volumes/appdashboard_data/_data

# Memory
free -h

# CPU
top -bn1 | head -20
```

---

## Common Issues

### 1. "502 Bad Gateway" / "504 Gateway Timeout"

| Cause | Fix |
|-------|-----|
| PHP-FPM not ready | Wait 30s after deploy; check `docker compose logs appdashboard` |
| Request too long | Increase `proxy_read_timeout` in nginx (default 60s → 300s) |
| PHP memory limit | Increase `memory_limit` in PHP.ini (default 256M → 512M) |
| DB query timeout | Check slow queries; add indexes; increase `statement_timeout` |

### 2. Database Connection Failed

| Symptom | Fix |
|---------|-----|
| `SQLSTATE[08006] connection refused` | Check DB container running: `docker compose ps appdashboard-db` |
| `password authentication failed` | Verify `DB_PASSWORD` matches in `.env` and DB secret |
| `too many connections` | Increase `max_connections` in PostgreSQL; check for connection leaks |
| `SSL connection required` | Set `DB_SSLMODE=require` and provide certs |

### 3. File Upload Fails

| Error | Fix |
|-------|-----|
| `413 Request Entity Too Large` | Increase `client_max_body_size` in nginx (e.g., `2G`); check PHP `upload_max_filesize`, `post_max_size` |
| `500` on large files | Enable chunked upload in Cloud UI; increase PHP `max_execution_time`, `max_input_time` |
| Permissions denied | `docker compose exec appdashboard chown -R www-data:www-data /var/www/html/storage` |
| Disk full | `docker system prune -a`; clean old backups; increase volume size |

### 4. OnlyOffice Not Loading

| Symptom | Fix |
|-------|-----|
| "Document not found" / gray screen | Verify `ONLYOFFICE_JWT_SECRET` matches in `.env` AND `docker-compose.yml` AND OnlyOffice container |
| CORS error | Check OnlyOffice `WOPI_ENABLED=true`; nginx `Access-Control-Allow-Origin` |
| JWT error in logs | Regenerate secret; update all three places; restart both containers |
| "Failed to load document" | Check OnlyOffice container logs: `docker compose logs onlyoffice` |

### 5. WebDAV Mount Fails

| Client | Fix |
|--------|-----|
| Windows | Use WinSCP or rclone; built-in client has bugs |
| macOS | Try `https://domain/webdav/` with trailing slash |
| Linux (davfs2) | Add `use_locks 0` to `/etc/davfs2/davfs2.conf` |
| All | Verify `/webdav` not blocked by proxy; check nginx `location /webdav` |

### 6. OAuth Login Fails

| Error | Fix |
|-------|-----|
| `redirect_uri_mismatch` | Must match **exactly** in provider console AND AppDashboard |
| `invalid_client` | Client ID/Secret wrong; check for trailing spaces |
| `access_denied` | User denied consent; check scopes allowed |
| `invalid_grant` (refresh) | Refresh token expired (30 days); re-authenticate |
| State mismatch | Clear cookies; try incognito; check `SESSION_DOMAIN` |

### 7. Health Connect Sync Issues

| Issue | Fix |
|-------|-----|
| "Not connected" | Install Health Connect app; grant all 4 permissions |
| Sync stuck | Open Health Connect app → **Sync now**; check phone internet |
| No step data | Verify phone has step sensor; Health Connect → Data → Steps shows data |
| Conflicts not resolving | Steps app → Conflicts → Choose strategy → Apply |

### 8. MCP Connection Fails

| Error | Fix |
|-------|-----|
| "Unauthorized" | Token expired → restart AI client; check scopes include `mcp` + data scopes |
| "Connection failed" | Verify `APPDASHBOARD_URL` reachable; check OAuth client redirect URI |
| Tools empty | User lacks app access; check Admin → Users → App Access |
| Rate limited | Reduce calls; check Admin → Config → MCP → Rate Limit |

### 9. Scheduled Backups Not Running

| Check | Fix |
|-------|-----|
| Cron syntax | Use standard 5-field cron; test with `crontab -l` |
| Container timezone | Set `TZ=UTC` in compose; cron runs in container time |
| Permissions | Backup script needs write to `/var/www/html/storage/app/backups` |
| Disk space | `df -h` on backup volume |

### 10. Performance Degradation

| Symptom | Investigation |
|---------|---------------|
| Slow page loads | Check `/performance/slow-queries`; add DB indexes |
| High CPU | `docker stats`; check PHP-FPM workers; enable OPcache |
| High memory | Check Redis memory; PHP memory leaks; restart containers |
| Slow DB | `pg_stat_statements`; vacuum analyze; check connection pool |

---

## Log Locations

| Component | Location | Access |
|-----------|----------|--------|
| Nginx (app) | `/var/log/nginx/access.log`, `error.log` | `docker compose logs appdashboard` |
| PHP-FPM | `/var/log/php-fpm/error.log` | `docker compose logs appdashboard` |
| Application | `/var/www/html/storage/logs/laravel.log` | `docker compose exec appdashboard tail -f storage/logs/laravel.log` |
| PostgreSQL | `stderr` (configured via `log_destination`) | `docker compose logs appdashboard-db` |
| Redis | `stderr` | `docker compose logs redis` |
| OnlyOffice | `/var/log/onlyoffice/` | `docker compose logs onlyoffice` |
| Audit logs | Database `audit_logs` table | Admin → Audit Logs UI |

---

## Debug Mode

### Enable Temporarily

```bash
# In .env
APP_DEBUG=true
LOG_LEVEL=debug

# Restart
docker compose restart appdashboard
```

**Disable immediately after debugging** — exposes stack traces.

### SQL Query Logging

```bash
# In PostgreSQL (postgresql.conf)
log_min_duration_statement = 0  # Log all queries
log_statement = 'all'

# Or for specific session
SET log_statement = 'all';
```

### Xdebug (Development Only)

```yaml
# docker-compose.override.yml
appdashboard:
  environment:
    - XDEBUG_MODE=debug
    - XDEBUG_CLIENT_HOST=host.docker.internal
    - XDEBUG_CLIENT_PORT=9003
  ports:
    - "9003:9003"
```

---

## Maintenance Commands

### Clear Caches

```bash
docker compose exec appdashboard php project/cli.php cache:clear
docker compose exec appdashboard php project/cli.php config:clear
docker compose exec appdashboard php project/cli.php route:clear
docker compose exec appdashboard php project/cli.php view:clear
```

### Database Maintenance

```bash
# Vacuum analyze (run weekly)
docker compose exec appdashboard-db psql -U appdashboard -d appdashboard -c "VACUUM ANALYZE;"

# Reindex (monthly, off-peak)
docker compose exec appdashboard-db psql -U appdashboard -d appdashboard -c "REINDEX DATABASE appdashboard;"

# Check bloat
docker compose exec appdashboard-db psql -U appdashboard -d appdashboard -c "
SELECT schemaname, tablename, 
       pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size,
       n_dead_tup
FROM pg_stat_user_tables
ORDER BY n_dead_tup DESC LIMIT 20;"
```

### Storage Cleanup

```bash
# Remove orphaned files (no DB reference)
docker compose exec appdashboard php project/cli.php storage:cleanup

# Regenerate thumbnails
docker compose exec appdashboard php project/cli.php cloud:thumbnails:regenerate

# Analyze storage usage
docker compose exec appdashboard php project/cli.php storage:analyze
```

### Queue Workers

```bash
# Restart workers
docker compose exec appdashboard php project/cli.php queue:restart

# Check failed jobs
docker compose exec appdashboard php project/cli.php queue:failed

# Retry failed
docker compose exec appdashboard php project/cli.php queue:retry all
```

---

## Emergency Procedures

### Locked Out (Admin)

```bash
# 1. Access DB directly
docker compose exec appdashboard-db psql -U appdashboard -d appdashboard

# 2. Reset admin password
UPDATE users SET password = '$argon2id$v=19$m=65536,t=4,p=1$...' WHERE email = 'root@root';

# 3. Or create new admin
INSERT INTO users (email, password, name, role, email_verified_at, created_at, updated_at)
VALUES ('admin@new.com', '$argon2id$...', 'Admin', 'admin', NOW(), NOW(), NOW());

# 4. Generate password hash
docker compose exec appdashboard php -r "echo password_hash('NewPass123!', PASSWORD_ARGON2ID);"
```

### Database Corruption

```bash
# 1. Stop app
docker compose stop appdashboard

# 2. Check DB integrity
docker compose exec appdashboard-db pg_dump -U appdashboard -Fc appdashboard > /tmp/backup.dump

# 3. If dump fails, try pg_dumpall or pg_basebackup

# 4. Restore to new DB
docker compose exec -T appdashboard-db pg_restore -U appdashboard -c -d appdashboard_new < /tmp/backup.dump

# 5. Swap databases in .env
```

### Full Disk

```bash
# 1. Find large files
docker compose exec appdashboard find /var/www/html/storage -type f -size +100M -ls

# 2. Clean Docker
docker system prune -a --volumes

# 3. Clean old backups
docker compose exec appdashboard find /var/www/html/storage/app/backups -mtime +30 -delete

# 4. Compress logs
docker compose exec appdashboard find /var/log -name "*.log" -mtime +7 -exec gzip {} \;
```

---

## Support Channels

| Channel | For |
|---------|-----|
| GitHub Issues | Bugs, feature requests |
| Discussions | Questions, community help |
| Discord/Slack | Real-time chat (if available) |
| Email (security) | security@your-domain.com |

---

## Information to Include in Bug Reports

1. **AppDashboard version** (Git tag / Docker image tag)
2. **Deployment method** (Docker Compose, Kubernetes, etc.)
3. **OS & Docker version**
4. **Steps to reproduce**
5. **Expected vs actual behavior**
6. **Relevant logs** (filter sensitive data)
7. **Browser/Client** (for UI issues)
8. **Network topology** (reverse proxy, load balancer)