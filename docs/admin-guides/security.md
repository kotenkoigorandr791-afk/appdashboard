# Security Hardening

Comprehensive security configuration for production deployments.

---

## Network Security

### Firewall Rules

```bash
# Allow only necessary ports
# 80/443 → Reverse Proxy (Traefik/Nginx)
# 22 → SSH (restrict to admin IPs)
# 5432 → PostgreSQL (INTERNAL ONLY)
# 6379 → Redis (INTERNAL ONLY)

# Example UFW
ufw default deny incoming
ufw default allow outgoing
ufw allow from 10.0.0.0/8 to any port 5432  # DB internal
ufw allow from 10.0.0.0/8 to any port 6379  # Redis internal
ufw allow 22/tcp comment 'SSH from admin IPs only'
ufw allow 80,443/tcp comment 'HTTP/HTTPS'
ufw enable
```

### Docker Network Isolation

```yaml
# docker-compose.prod.yml
networks:
  frontend:
    external: true  # Traefik network
  backend:
    driver: overlay
    internal: true  # No internet access
    attachable: true
```

- **App containers**: `frontend` + `backend`
- **DB/Redis**: `backend` only
- **OnlyOffice**: `backend` only

---

## Container Hardening

### Security Options

```yaml
# In docker-compose.prod.yml for each service
security_opt:
  - no-new-privileges:true
cap_drop:
  - ALL
cap_add: []  # Only add if absolutely necessary
read_only: true
tmpfs:
  - /tmp:noexec,nosuid,size=100m
  - /var/cache:noexec,nosuid,size=50m
  - /var/run:noexec,nosuid,size=10m
user: "1000:1000"  # Non-root (www-data in image)
```

### Image Security

```bash
# Scan for vulnerabilities
docker scout cves ghcr.io/yourorg/appdashboard:latest
trivy image ghcr.io/yourorg/appdashboard:latest

# Build with minimal base
# Dockerfile uses php:8.3-fpm-alpine (small, minimal)
```

---

## Application Security

### Headers (Enforced by Nginx)

```nginx
# In nginx.prod.conf
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' wss:; frame-ancestors 'self';" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
```

### CSP Report-Only (Testing)

```nginx
add_header Content-Security-Policy-Report-Only "default-src 'self'; ...; report-uri /csp-report;" always;
```

### Rate Limiting

```nginx
# Global
limit_req_zone $binary_remote_addr zone=api:10m rate=100r/s;
limit_req_zone $binary_remote_addr zone=auth:10m rate=5r/s;

# Apply
location /public-api/v1/auth/ {
    limit_req zone=auth burst=10 nodelay;
}
location /public-api/v1/ {
    limit_req zone=api burst=200 nodelay;
}
```

---

## Authentication Security

### Password Policy (Admin → Config → Security)

| Setting | Recommended |
|---------|-------------|
| Min length | 12 |
| Require: upper, lower, number, symbol | All enabled |
| Max age | 90 days (optional) |
| History | 5 |
| Breach check | Enable (HaveIBeenPwned) |

### Two-Factor Authentication

- **Enforce for admins**: Admin → Config → Security → `require_2fa_for_admins: true`
- **TOTP only** (no SMS)
- **Backup codes**: 10 generated, one-time use

### Session Security

```env
SESSION_LIFETIME=120          # 2 hours
SESSION_SECURE_COOKIE=true    # HTTPS only
SESSION_HTTP_ONLY=true        # No JS access
SESSION_SAME_SITE=lax         # CSRF protection
SESSION_DOMAIN=.your-domain.com
```

### JWT Security

```env
JWT_ALGORITHM=RS256           # Asymmetric (not HS256)
JWT_PRIVATE_KEY_FILE=/run/secrets/jwt_private
JWT_PUBLIC_KEY_FILE=/run/secrets/jwt_public
JWT_ACCESS_TTL=3600           # 1 hour
JWT_REFRESH_TTL=2592000       # 30 days
```

**Generate keys**:
```bash
openssl genrsa -out jwt_private.pem 2048
openssl rsa -in jwt_private.pem -pubout -out jwt_public.pem
```

### OAuth Security

| Setting | Value |
|---------|-------|
| PKCE required | Always for public clients |
| Refresh token rotation | Enabled |
| Refresh token reuse detection | Enabled (revoke on reuse) |
| Authorization code lifetime | 10 minutes |
| Access token lifetime | 1 hour (max) |
| Scope validation | Strict |

---

## Database Security

### Connection

```env
DB_SSLMODE=require          # Force TLS
DB_SSLROOTCERT=/run/secrets/db_ca.crt
DB_SSLCERT=/run/secrets/db_client.crt
DB_SSLKEY=/run/secrets/db_client.key
```

### Permissions

```sql
-- App user (minimal privileges)
GRANT CONNECT ON DATABASE appdashboard TO appdashboard;
GRANT USAGE ON SCHEMA public TO appdashboard;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO appdashboard;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO appdashboard;

-- No DDL, no TRUNCATE, no admin functions
```

### Auditing

```sql
-- Enable pgaudit
CREATE EXTENSION pgaudit;
SET pgaudit.log = 'all, -misc';
SET pgaudit.log_level = 'log';
```

---

## File Storage Security

### Upload Validation

```php
// In CloudController
$allowedMimes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic',
    'application/pdf',
    'video/mp4', 'video/webm', 'video/quicktime',
    'audio/mpeg', 'audio/wav', 'audio/ogg',
    'text/plain', 'text/csv', 'text/markdown',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    // ... office types
];
$maxSize = 2 * 1024 * 1024 * 1024; // 2 GB
```

### Path Traversal Prevention

- All file ops use **database IDs**, not paths
- Storage path: `/var/www/html/storage/app/public/{user_id}/{uuid}.{ext}`
- No user-supplied paths in filesystem operations

### Scan Uploads (Optional)

```bash
# ClamAV integration
# 1. Run clamd in sidecar
# 2. On upload: clamscan --fdpass <file>
# 3. Quarantine if infected
```

---

## Secrets Management

### Docker Secrets (Production)

```yaml
secrets:
  db_password:
    file: ./secrets/db_password.txt
  jwt_private:
    file: ./secrets/jwt_private.pem
  onlyoffice_jwt:
    file: ./secrets/onlyoffice_jwt.txt

services:
  appdashboard:
    secrets:
      - db_password
      - jwt_private
      - onlyoffice_jwt
```

### In Application

```php
// Read from /run/secrets/
$dbPassword = file_get_contents('/run/secrets/db_password');
$jwtPrivate = file_get_contents('/run/secrets/jwt_private');
```

### Rotation

| Secret | Frequency | Procedure |
|--------|-----------|-----------|
| DB Password | 90 days | 1. Generate new 2. Update secret 3. Rolling restart |
| JWT Keys | 1 year | 1. Generate new keypair 2. Deploy public 3. Rotate private |
| OAuth Client Secrets | 90 days | Per provider console + AppDashboard update |
| Backup Encryption | Per backup | Auto-generated; store in password manager |

---

## Monitoring & Alerting

### Security Events to Alert

| Event | Severity | Action |
|-------|----------|--------|
| 5+ failed logins in 5 min | High | Block IP; notify admin |
| Admin login from new IP | Medium | Email admin |
| OAuth client created | Medium | Audit log |
| Shell command executed | High | Alert + audit |
| Backup downloaded | Medium | Log |
| Maintenance mode toggled | High | Alert |
| 2FA disabled | High | Alert |
| Impersonation started | Critical | Alert + audit |

### Log Sources

| Source | Location | Key Fields |
|--------|----------|------------|
| Nginx access | `/var/log/nginx/access.log` | IP, method, path, status, ua |
| PHP-FPM | `/var/log/php-fpm/error.log` | Error, trace, user |
| App audit | DB `audit_logs` table | User, action, ip, resource |
| Docker | `docker logs` | Container, stdout/stderr |
| PostgreSQL | `log_min_duration_statement` | Query, duration, user |

---

## Incident Response

### Compromised Account

1. Admin → Users → **Revoke all tokens** + **Reset password** + **Disable 2FA**
2. Check audit logs for suspicious activity
3. Force re-auth on all devices

### Compromised Server

1. Isolate network (block all except admin IPs)
2. Snapshot disks for forensics
3. Rotate ALL secrets (DB, JWT, OAuth, Backup)
4. Restore from known-good backup
5. Post-mortem: root cause analysis

### Data Breach

1. Assess scope (what data, how many users)
2. Notify affected users (GDPR: 72 hours)
3. Report to authorities if required
4. Implement additional controls

---

## Compliance Checklist

| Standard | Requirements | AppDashboard Coverage |
|----------|--------------|----------------------|
| **GDPR** | Data portability, deletion, consent, DPA | Export API, Delete account, Consent logs |
| **SOC 2** | Access control, encryption, monitoring, audit | RBAC, TLS, Audit logs, Alerts |
| **HIPAA** | PHI protection, audit, access control | Not certified (no BAA) |
| **ISO 27001** | Risk management, policies, procedures | Documented in this guide |

---

## Security Testing

### Automated

```bash
# Dependency scanning
composer audit
npm audit

# SAST
phpstan analyse --level=5
psalm --show-info=false

# Container scan
trivy fs .
docker scout cves .
```

### Manual (Quarterly)

- [ ] Penetration test (authenticated)
- [ ] OAuth flow review
- [ ] File upload bypass attempts
- [ ] IDOR testing (cross-user access)
- [ ] Rate limit bypass
- [ ] CSP effectiveness
- [ ] Backup encryption verification
- [ ] Secret rotation drill