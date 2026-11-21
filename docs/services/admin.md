# Admin Panel

**Web UI**: `/shell/*`, `/database` **API Base**: `/public-api/v1/admin`, `/config`, `/docker`, `/shell`, `/backup`, `/performance` **Auth**: Bearer + `admin` role

---

## User Functions (Admin Only)

| Area | Functions |
|------|-----------|
| **User Management** | Create, edit, delete, impersonate, role assignment, app access, 2FA reset |
| **System Configuration** | Site settings, email/SMTP, OAuth providers, maintenance mode, feature flags |
| **Database Admin** | Query runner, table browser, migrations, backup/restore, vacuum/analyze |
| **Docker Management** | Container list, start/stop/restart, logs, stats, image cleanup |
| **Shell Access** | Audited command execution (allowlisted commands only) |
| **Backup Manager** | Create, schedule, list, restore, download, verify, offsite (S3/Rclone) |
| **Performance Monitor** | Real-time metrics, slow queries, memory/CPU, request traces |
| **Audit Logs** | User actions, admin actions, security events, API calls (searchable) |
| **Storage Analyzer** | Disk usage by app, user, file type; find large/old files |

---

## API Endpoints

### Admin — Users & Access

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/admin/users` | List users. Query: `search`, `role`, `app_access`, `cursor`, `limit` |
| `POST` | `/admin/users` | Create user. Body: `email`, `password?`, `name`, `role` (user/admin), `app_access[]`, `send_invite?` |
| `GET` | `/admin/users/{id}` | User details + app access + last login + storage used |
| `PATCH` | `/admin/users/{id}` | Update user (email, name, role, app_access, active, password_reset) |
| `DELETE` | `/admin/users/{id}` | Delete user (transfers data to admin or purges) |
| `POST` | `/admin/users/{id}/impersonate` | Generate impersonation token (audited) |
| `POST` | `/admin/users/{id}/2fa/reset` | Disable 2FA for user |
| `GET` | `/admin/roles` | List roles & permissions |
| `POST` | `/admin/roles` | Create custom role |
| `GET` | `/admin/audit-logs` | Search audit logs. Query: `user_id`, `action`, `date_from`, `date_to`, `ip`, `cursor`, `limit` |

### Config — System Settings

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/config` | All settings (grouped by section) |
| `GET` | `/config/{section}` | Single section (auth, email, oauth, cloud, films, smart_home, steps, spends, recipes, integrations, mcp, security, ui) |
| `PATCH` | `/config/{section}` | Update section settings |
| `POST` | `/config/test-email` | Send test email |
| `POST` | `/config/test-oauth` | Test OAuth provider config |

### Docker — Container Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/docker/containers` | List containers (all project containers). Query: `all` (include stopped) |
| `GET` | `/docker/containers/{name}` | Container details: status, ports, mounts, health, resources |
| `GET` | `/docker/containers/{name}/logs` | Logs. Query: `tail`, `since`, `until`, `follow` (WebSocket) |
| `GET` | `/docker/containers/{name}/stats` | Real-time CPU, memory, network, disk I/O |
| `POST` | `/docker/containers/{name}/start` | Start container |
| `POST` | `/docker/containers/{name}/stop` | Stop container. Query: `timeout` |
| `POST` | `/docker/containers/{name}/restart` | Restart container |
| `GET` | `/docker/images` | List images with size, tags, created |
| `POST` | `/docker/images/prune` | Remove unused images |
| `GET` | `/docker/system/df` | Disk usage breakdown |

### Shell — Command Execution

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/shell/exec` | Execute command. Body: `command`, `args[]`, `timeout?`, `working_dir?` |

**Allowlisted commands** (configured in Admin → Config → Security):
```json
[
  "php", "composer", "npm", "node", "mysql", "pg_dump", "pg_restore",
  "rsync", "tar", "gzip", "find", "du", "df", "free", "top", "htop",
  "curl", "wget", "git", "redis-cli", "supervisorctl"
]
```

**Audit**: Every execution logged with user, command, args, exit code, duration, output (truncated).

### Backup — Backup Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/backup` | List backups. Query: `type` (full/db/files/config), `cursor`, `limit` |
| `POST` | `/backup` | Create backup. Body: `type` (full/db/files/config), `name?`, `compress?`, `encrypt?` |
| `GET` | `/backup/{id}` | Backup metadata: size, status, duration, files_count, checksum |
| `GET` | `/backup/{id}/download` | Download backup file |
| `DELETE` | `/backup/{id}` | Delete backup |
| `POST` | `/backup/{id}/restore` | Restore. Body: `components[]` (db/files/config), `force?` |
| `GET` | `/backup/schedules` | List scheduled backups |
| `POST` | `/backup/schedules` | Create schedule. Body: `name`, `type`, `cron`, `retention_days`, `offsite?` |
| `PATCH` | `/backup/schedules/{id}` | Update schedule |
| `DELETE` | `/backup/schedules/{id}` | Delete schedule |
| `POST` | `/backup/verify/{id}` | Verify backup integrity |
| `GET` | `/backup/offsite/providers` | List configured offsite (S3, Rclone, SFTP) |

### Performance — Monitoring

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/performance/metrics` | Current: CPU, memory, disk, network, DB connections, Redis, queue |
| `GET` | `/performance/metrics/history` | Time series. Query: `metric`, `from`, `to`, `interval` |
| `GET` | `/performance/slow-queries` | Slow DB queries. Query: `limit`, `since` |
| `GET` | `/performance/requests` | Request traces. Query: `slow_only`, `limit`, `since` |
| `GET` | `/performance/php-fpm` | PHP-FPM pool status |
| `GET` | `/performance/opcache` | OPcache status & stats |
| `POST` | `/performance/opcache/reset` | Reset OPcache |

---

## Web Routes

| Path | Description |
|------|-------------|
| `/shell/backup/` | Backup Manager UI (create, schedule, restore, download, verify) |
| `/shell/storage/` | Storage Analyzer UI (treemap, largest files, by app/user) |
| `/shell/performance/*` | Performance UI (charts, slow queries, request traces) |
| `/database` | Database Admin (Adminer-like: query, tables, export, import) |

---

## Default Admin Credentials

| Email | Password | Role |
|-------|----------|------|
| `root@root` | `12345T` | `admin` |

> **Change immediately** after first login. Create personal admin account, then disable/remove `root@root`.

---

## Role Permissions

| Permission | admin | user |
|------------|-------|------|
| Access `/shell/*` | ✅ | ❌ |
| Access `/database` | ✅ | ❌ |
| `POST /admin/*` | ✅ | ❌ |
| `POST /config/*` | ✅ | ❌ |
| `POST /docker/*` | ✅ | ❌ |
| `POST /shell/exec` | ✅ | ❌ |
| `POST /backup/*` | ✅ | ❌ |
| `GET /performance/*` | ✅ | ❌ |
| Impersonate users | ✅ | ❌ |
| Manage OAuth providers | ✅ | ❌ |
| View audit logs | ✅ | ❌ |

---

## Backup Strategy (Recommended)

| Schedule | Type | Retention | Offsite |
|----------|------|-----------|---------|
| Daily 02:00 | Full (DB + files + config) | 30 days | S3 (encrypted) |
| Hourly | Database only | 7 days | S3 |
| Weekly (Sun 03:00) | Full | 90 days | S3 + Rclone (second region) |
| Before update | Manual full | 1 year | Local + S3 |

**Verify**: Monthly test restore to staging.

---

## Offsite Backup Providers

| Provider | Config Fields |
|----------|---------------|
| **S3 Compatible** | `endpoint`, `region`, `bucket`, `access_key`, `secret_key`, `path_prefix`, `encryption_key` |
| **Rclone** | `remote_name`, `remote_path`, `rclone_config` (base64) |
| **SFTP** | `host`, `port`, `username`, `password/key`, `path` |

---

## Maintenance Mode

Admin → Configuration → **Maintenance Mode**:

- **ON**: Returns `503 Service Unavailable` for all non-admin routes
- **Custom message**: HTML displayed to users
- **Allowed IPs**: Comma-separated CIDR (admin access during maintenance)
- **API**: Returns `503` with `Retry-After` header

---

## Security Settings (Admin → Config → Security)

| Setting | Description |
|---------|-------------|
| Allowed shell commands | JSON array of allowed binaries |
| Max login attempts | Before temporary lockout (default: 5) |
| Lockout duration | Minutes (default: 15) |
| Password min length | Default: 12 |
| Password require: upper, lower, number, symbol | Checkboxes |
| Session lifetime | Minutes (default: 120) |
| API rate limit | Requests/minute per user (default: 600) |
| Trusted proxies | CIDR for `X-Forwarded-For` (default: 172.16.0.0/12) |
| CORS origins | Allowed origins for API (default: APP_URL only) |
| Content Security Policy | Custom CSP header (advanced) |

---

## Audit Log Events

| Category | Events |
|----------|--------|
| **Auth** | login, logout, login_failed, password_reset, 2fa_enabled, 2fa_disabled, impersonation_start, impersonation_end |
| **User** | user_created, user_updated, user_deleted, role_changed, app_access_changed |
| **Admin** | config_changed, backup_created, backup_restored, shell_executed, docker_action, maintenance_toggled |
| **Data** | file_uploaded, file_deleted, file_shared, db_query_executed |
| **Security** | suspicious_activity, rate_limit_exceeded, invalid_token, csrf_failure |

**Retention**: 1 year (configurable). Export: CSV/JSON.

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Access denied" on admin routes | Verify user has `admin` role in DB: `SELECT role FROM users WHERE email='...'` |
| Docker commands fail | Check `docker.sock` mounted in container: `-v /var/run/docker.sock:/var/run/docker.sock` |
| Shell command not allowed | Add to allowlist in Admin → Config → Security; or use full path `/usr/bin/php` |
| Backup restore fails | Check backup checksum (`GET /backup/{id}`); verify DB not locked; try `pg_restore --clean` |
| Performance metrics empty | Ensure `prometheus-node-exporter` and `cadvisor` running; check `/performance/metrics` |
| Slow queries not showing | Enable `pg_stat_statements` in PostgreSQL: `shared_preload_libraries = 'pg_stat_statements'` |
| Audit logs missing | Check `AUDIT_LOG_ENABLED=true` in `.env`; verify DB table `audit_logs` exists |
| Storage analyzer wrong sizes | Run `docker compose exec appdashboard php project/cli.php storage:analyze` |