# WAF

AppDashboard uses Nginx ModSecurity with OWASP Core Rule Set inside the `appdashboard` container.

## Request Flow

Internet or outer reverse proxy -> container Nginx with ModSecurity -> PHP-FPM unix socket, internal Node MCP HTTP, internal websocket/proxy routes, or static files.

PHP-FPM is not exposed by Docker. In the default compose file only Nginx port 80 is published for the application, so HTTP traffic passes through the WAF.

## Files

- `Dockerfile`: installs `libnginx-mod-http-modsecurity` and `modsecurity-crs`.
- `docker-compose.yml`: exposes `APP_WAF_RULE_ENGINE`.
- `docker-entrypoint.sh`: writes runtime WAF mode to `/etc/nginx/modsecurity/appdashboard-engine.conf`.
- `nginx/appdashboard.conf`: enables ModSecurity, security headers, and endpoint rate limits.
- `deploy/waf/appdashboard-modsecurity.conf`: ModSecurity and CRS loader.
- `deploy/waf/appdashboard-crs-exclusions.conf`: narrow application exclusions.

## Start, Stop, Restart

```bash
docker compose up -d --build
docker compose stop appdashboard
docker compose restart appdashboard
```

Use detection-only mode for false-positive analysis:

```bash
APP_WAF_RULE_ENGINE=DetectionOnly docker compose up -d --build appdashboard
```

Valid modes are `On`, `DetectionOnly`, and `Off`.

## Logs

```bash
docker compose logs -f appdashboard
docker exec appdashboard tail -f /var/log/modsecurity/audit.log
docker exec appdashboard tail -f /var/log/nginx/appdashboard_error.log
```

Audit events include timestamps, request metadata, status, rule IDs, and ModSecurity messages. Avoid pasting full audit entries into tickets when they may contain tokens, cookies, or uploaded content.

## Exclusions

Add only endpoint-specific exclusions in `deploy/waf/appdashboard-crs-exclusions.conf`.

```apache
SecRule REQUEST_URI "@beginsWith /some/path" "id:100100,phase:1,pass,nolog,ctl:ruleRemoveTargetById=941100;ARGS:field_name"
```

Do not disable CRS rule groups globally unless the whole application behavior requires it and the reason is documented.

## Current Exclusions

- WebDAV request bodies are not inspected so large `PUT`/sync uploads keep working; headers, path, cookies, and protocol rules still apply.
- Cloud AI prompt endpoints have focused XSS/SQLi CRS relaxations because users legitimately submit source code, HTML, SQL-like text, and shell snippets.
- Authorization headers on API/MCP routes are excluded from one protocol target to avoid token-format false positives.
- OAuth token secrets are excluded from one SQLi target to avoid false positives on random secret material.

## Rate Limits

- Auth/OAuth endpoints: `12r/m` per source IP, burst `20`.
- Public API/MCP/API endpoints: `120r/m` per source IP, burst `120`.
- Sensitive map token endpoint: API zone with burst `60`.

Application-level login lockout remains active in PHP.

## Security Headers

Nginx sends `Content-Security-Policy`, `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy`.

The CSP allows existing inline scripts and known CDNs used by the current app. Tighten it only after removing inline scripts.

## Verify

```bash
docker exec appdashboard nginx -t
docker exec appdashboard nginx -T | grep -E "modsecurity|appdashboard_auth|Content-Security-Policy"
curl -i http://localhost/
curl -i "http://localhost/?q=%3Cscript%3Ealert(1)%3C/script%3E"
curl -i "http://localhost/?id=1%20UNION%20SELECT%201,2,3"
curl -i -H "X-Test: () { :;}; /bin/sh" http://localhost/
```

Expected malicious test result in blocking mode is `403` with an audit log entry. Normal navigation should return `200` or the expected application redirect.

## Update CRS

```bash
docker compose build --pull appdashboard
docker compose up -d appdashboard
```

Review `/usr/share/modsecurity-crs/` inside the rebuilt container and retest before production rollout.

## Troubleshooting

1. Switch to `DetectionOnly`.
2. Reproduce the blocked request.
3. Inspect `/var/log/modsecurity/audit.log`.
4. Identify the exact rule ID, path, and parameter.
5. Add a narrow exclusion.
6. Return to `On` and retest.
