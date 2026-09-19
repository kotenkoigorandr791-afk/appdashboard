# WAF Documentation

## Overview

AppDashboard uses **Nginx ModSecurity** with **OWASP Core Rule Set (CRS)** inside the `appdashboard` container to protect against web application attacks.

---

## Request Flow

```
Client → Nginx (ModSecurity) → PHP-FPM → Application
```

### Processing Pipeline

1. **Nginx** receives incoming HTTP request
2. **ModSecurity** processes request through CRS rules
3. **Allowed requests** forwarded to **PHP-FPM**
4. **PHP application** processes business logic
5. **Response** passes back through ModSecurity (optional)

---

## Configuration

### Nginx Integration

ModSecurity is enabled in the main server block:

```nginx
# nginx/appdashboard.conf.template
modsecurity on;
modsecurity_rules_file /etc/nginx/modsecurity/main.conf;
```

### Main Config (`/etc/nginx/modsecurity/main.conf`)

```
Include /etc/nginx/modsecurity/modsecurity.conf
Include /opt/coreruleset/crs-setup.conf
Include /opt/coreruleset/rules/*.conf
```

### Key ModSecurity Settings (`modsecurity.conf`)

| Setting | Value | Description |
|---------|-------|-------------|
| `SecRuleEngine` | `On` | Enable ModSecurity |
| `SecRequestBodyAccess` | `On` | Inspect request bodies |
| `SecResponseBodyAccess` | `Off` | Disable response body inspection (performance) |
| `SecRequestBodyLimit` | `13107200` | Max request body size (12.5 MB) |
| `SecRequestBodyNoFilesLimit` | `131072` | Max request body without files (128 KB) |
| `SecAuditEngine` | `RelevantOnly` | Log only relevant transactions |
| `SecAuditLog` | `/var/log/modsec_audit.log` | Audit log path |

---

## OWASP CRS Rules

### Rule Sets Included

- `REQUEST-900-EXCLUSION-RULES-BEFORE-CRS.conf`
- `REQUEST-901-INITIALIZATION.conf`
- `REQUEST-905-COMMON-EXCEPTIONS.conf`
- `REQUEST-910-IP-REPUTATION.conf`
- `REQUEST-911-METHOD-ENFORCEMENT.conf`
- `REQUEST-912-DOS-PROTECTION.conf`
- `REQUEST-913-SCANNER-DETECTION.conf`
- `REQUEST-920-PROTOCOL-ENFORCEMENT.conf`
- `REQUEST-921-PROTOCOL-ATTACK.conf`
- `REQUEST-930-APPLICATION-ATTACK-LFI.conf`
- `REQUEST-931-APPLICATION-ATTACK-RFI.conf`
- `REQUEST-932-APPLICATION-ATTACK-RCE.conf`
- `REQUEST-933-APPLICATION-ATTACK-PHP.conf`
- `REQUEST-934-APPLICATION-ATTACK-NODEJS.conf`
- `REQUEST-941-APPLICATION-ATTACK-XSS.conf`
- `REQUEST-942-APPLICATION-ATTACK-SQLI.conf`
- `REQUEST-943-APPLICATION-ATTACK-SESSION-FIXATION.conf`
- `REQUEST-944-APPLICATION-ATTACK-JAVA.conf`
- `REQUEST-950-DATA-LEAKAGES.conf`
- `RESPONSE-951-DATA-LEAKAGES.conf`
- `RESPONSE-952-DATA-LEAKAGES.conf`
- `RESPONSE-953-DATA-LEAKAGES.conf`
- `RESPONSE-954-DATA-LEAKAGES.conf`
- `RESPONSE-959-BLOCKING-EVALUATION.conf`
- `RESPONSE-980-CORRELATION.conf`

---

## AppDashboard-Specific Exclusions

### Rule Exclusions

The following rules are excluded for specific paths to prevent false positives:

| Path | Excluded Rules | Reason |
|------|----------------|--------|
| `/public-api/*` | `942100, 942200` (SQLi) | API accepts structured queries |
| `/mcp/*` | `942100, 942200` (SQLi) | MCP protocol uses SQL-like syntax |
| `/api/steps*` | `942100, 942200` (SQLi) | Step counter accepts JSON data |
| `/webdav*` | `942100, 942200, 931130` (RFI) | WebDAV needs file operations |
| `/project/performance/*` | `942100, 942200` (SQLi) | Performance API |

### Custom Rules

Additional custom rules in `/etc/nginx/modsecurity/custom/`:

- Rate limiting for auth endpoints
- GeoIP blocking for known malicious regions
- Custom header validation

---

## Tuning & Maintenance

### Testing Rules

```bash
# Test configuration
nginx -t

# View ModSecurity logs
tail -f /var/log/modsec_audit.log

# Check rule hits
grep "id:\"942" /var/log/modsec_audit.log | head -20
```

### Handling False Positives

1. Identify rule ID from audit log
2. Add exclusion in `crs-setup.conf` or path-specific location block
3. Test with legitimate traffic
4. Reload nginx: `nginx -s reload`

### Rule Updates

OWASP CRS is updated via container rebuild:

```bash
docker compose build --no-cache appdashboard
docker compose up -d appdashboard
```

---

## Performance Impact

| Metric | Value |
|--------|-------|
| Average latency overhead | ~5-15 ms |
| Memory per worker | +2-5 MB |
| CPU overhead | ~2-5% |

---

## Logging & Monitoring

### Audit Log Format

JSON format for SIEM integration:

```json
{
  "transaction": {
    "client_ip": "1.2.3.4",
    "method": "POST",
    "uri": "/api/data",
    "status": 403,
    "rule_id": "942100",
    "msg": "SQL Injection Attack Detected"
  }
}
```

### Key Log Locations

- `/var/log/modsec_audit.log` - Full audit log
- `/var/log/nginx/error.log` - Nginx errors (includes ModSecurity)
- `/var/log/nginx/access.log` - Access log with ModSecurity tags

---

## Security Headers

In addition to ModSecurity, the following headers are set:

| Header | Value |
|--------|-------|
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-Content-Type-Options` | `nosniff` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self' wss: https:` |

---

## Deployment

### Docker Compose

ModSecurity runs inside the `appdashboard` container with:

- Shared volume for CRS rules: `/opt/coreruleset/`
- Custom config volume: `/etc/nginx/modsecurity/`
- Log volume: `/var/log/`

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `MODSECURITY_ENABLED` | Enable/disable ModSecurity | `1` |
| `MODSECURITY_AUDIT_LOG` | Audit log path | `/var/log/modsec_audit.log` |
| `CRS_PARANOIA_LEVEL` | CRS paranoia level (1-4) | `2` |
