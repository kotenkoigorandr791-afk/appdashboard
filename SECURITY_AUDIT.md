# Security Audit

Date: 2026-10-24. Scope: PHP app (`project/`, `apps/`, `lib/`, `public-api/`, root PHP files, `nginx/`), local dev Docker stack at `http://localhost/` (passive scan only).
Excluded: third-party vendored apps (kanboard, gitea, immich etc.) beyond scanner output, Python sidecar, production, authenticated multi-role testing, ZAP active scan, manual IDOR matrix.

## Executive summary
The app has a sound baseline (db_admin/backup/webdav/performance return 403/401 unauthenticated, sensitive dirs blocked, JWT-gated OnlyOffice endpoints, CSP and security headers present). One real SSRF / arbitrary-write primitive was confirmed by code review and fixed. Vulnerable dependencies remain (not upgraded, see below). This is not a statement that the application is fully secure.

## Tools actually run
- Composer audit: `php composer.phar audit --locked` (PHP 8.5.4) - 3 advisories in 2 packages.
- Semgrep (docker `semgrep/semgrep` latest, rules `p/php`, `p/security-audit`) - 318 raw results.
- Gitleaks (docker `zricethezav/gitleaks`, `detect --no-git --redact`) - 172 leaks reported; the report file was not persisted (container path), results NOT triaged.
- Trivy (docker `aquasec/trivy`, `fs --scanners vuln,misconfig,secret`, HIGH/CRITICAL) - lockfile vulns in `appdashboard-mcp`, `.opencode`, `apps/lopaka`, `webssh`, `composer.lock`; 1 Dockerfile misconfiguration in each of `Dockerfile` and `deploy/python-sidecar/Dockerfile`. Not triaged in detail.
- OWASP ZAP (docker `ghcr.io/zaproxy/zaproxy:stable`, `zap-baseline.py -t http://localhost/`) - passive only.
- Playwright (chromium) new regression spec.
- Not run: Psalm and PHPStan (`composer require --dev vimeo/psalm` failed to install in this environment; PHPStan not attempted), ZAP active scan, ASVS checklist walkthrough (not performed formally).

## Findings
### High - SSRF and arbitrary file overwrite via OnlyOffice callback (FIXED)
`apps/cloud/onlyoffice_editor.php` (`download_remote_content`, callback handler). The callback body `url` was fetched server-side with no host restriction, redirects followed, TLS verification disabled, and the response was written into the target file. Requires a valid `oo_token` (issued to any logged-in editor session). Impact: internal network requests and replacement of the user's file with attacker-chosen content. Fix: URL must be http(s) without userinfo and its host must equal the document server host (or be listed in `ONLYOFFICE_CALLBACK_HOSTS`, comma-separated); redirects disabled; protocols restricted; TLS verification enabled; `file_get_contents` fallback removed. Deployment note: if the document server calls back with an internal hostname, set `ONLYOFFICE_CALLBACK_HOSTS`.

### Medium - Vulnerable dependencies (UNRESOLVED)
`guzzlehttp/guzzle` < 7.15.2 (CVE-2026-69246 high: noncanonical host bypasses host checks; CVE-2026-69245 medium), `phpseclib/phpseclib` < 3.0.57 (CVE-2026-84308). A targeted `composer update guzzlehttp/guzzle phpseclib/phpseclib` was not executed (blocked by the safe-change policy). Recommended: run it and the test suite.

### Medium - ZAP passive: CSP `unsafe-inline`/`unsafe-eval`/wildcard, missing SRI, no CSRF tokens on 5 forms (UNRESOLVED)
Needs architectural change (inline scripts). CSRF on forms not validated with an exploit; session cookie SameSite behaviour not exercised.

### Low - nginx version disclosure (FIXED in config)
`server_tokens off` added to `nginx/appdashboard.conf` and `.template`; takes effect after the image is rebuilt/restarted (not restarted here). Also: a cookie without HttpOnly (2 instances), `X-Content-Type-Options` missing on some responses (static assets).

### Low - TLS verification disabled in other curl calls (UNRESOLVED)
`apps/cloud/onlyoffice_editor.php` Telegram photo fetch (~line 116, user-influenced URL, also an SSRF candidate), `project/core.php:2539`, `apps/kanboard/app/Core/Http/Client.php:263` (vendored). Not verified for exploitability.

### Informational
- `project/core.php` `create_browser_login` builds `browser_url` from `HTTP_HOST`; nginx uses `server_name _`. Only reflected to the caller; low impact.
- Semgrep SQL hits in `apps/spends/controllers/Handlers.php`, `project/performance/controllers/MonitorController.php`, `db_admin.php`, `apps/cloud/compile.php` were reviewed: false positives (int casts, escaping, identifier quoting, admin-only tool). `echoed-request` (131) and `tainted-filename` (159) results were not triaged.

## Fixed and modified files
- `apps/cloud/onlyoffice_editor.php`
- `nginx/appdashboard.conf`, `nginx/appdashboard.conf.template`
- `tests/e2e/onlyoffice-callback-security.spec.ts` (new)

## Retesting
- New Playwright spec: 2 passed (unsigned callback/download rejected). It does not exercise the allowlist itself (needs a signed token; untested).
- `php -l` on the edited PHP file: OK; `nginx -t` in the running container: OK (on the old image config).
- Semgrep/ZAP/Composer audit were not re-run after the fix; the full existing e2e suite was not run.

## Remaining risks / untested
Authorization matrix and IDOR testing, share-link expiry/revocation, upload/download traversal, password reset, ZAP active scan, gitleaks triage, trivy detail, Psalm/PHPStan taint analysis.

## Recommendations
Update the vulnerable packages; triage gitleaks/semgrep `tainted-filename`; remove `unsafe-eval`; add CSRF tokens; enable TLS verification everywhere; restrict `server_name`; rebuild image to apply nginx change.

---
# Remediation round 2 (2026-10-24)

## Re-run scans
- Semgrep (`p/php`, `p/security-audit`): 318 -> 294 results (`tainted-filename` 159 -> 136, `curl-ssl-verifypeer-off` 3 -> 1). Remaining hits are mostly sinks behind authenticated, path-normalising helpers; not individually triaged.
- Trivy fs (HIGH/CRITICAL, vuln only): composer.lock now clean; `appdashboard-mcp` 12 -> 1; `.opencode` 4, `apps/lopaka/src` 8, `webssh/composer.lock` 2 unchanged.
- ZAP baseline (passive): unchanged, 0 FAIL, 14 WARN (CSP, SRI, CSRF tokens, cookie HttpOnly, nginx banner).
- ZAP active scan: NOT run. No isolated test database/stack was provisioned; running it against the dev stack was not considered safe.
- Gitleaks on `project/`: 0 leaks. The earlier whole-tree run (172) is still untriaged.
- `composer audit --locked`: no advisories. Composer lock only updated (host lacks ext-curl, `--no-install` used); `vendor/` must be refreshed by rebuilding the image.

## New findings and fixes
| Severity | File | Issue | Status |
|---|---|---|---|
| High | `apps/cloud/utilities/projects/zip-viewer/src/controller.php` | Hardcoded OnlyOffice JWT secret in source | Fixed: uses `getOnlyofficeJwtSecret()`. The old value is in git history; rotate it if it was ever the live secret. |
| High | `onlyoffice_editor.php`, `zip-viewer/src/controller.php` | Storage `user` taken from the query string; any logged-in user could address another user's storage (IDOR) | Fixed: bound to the session via new `cloud_session_storage_user()` in `apps/cloud/includes/auth_gate.php`. Signed-token download/callback branches still use the token-bound user. |
| High | `tts_api.php`, `transcription_api.php`, `transcription-editor`, `tts-editor`, `zip-viewer` `resolve_user_file_path()` | Fallback branch accepted `..` segments and resolved arbitrary files via `realpath` | Fixed: paths with `..` segments rejected before the fallback. |
| Medium | `project/core.php:2539` | Telegram photo fetch with TLS verification off | Fixed |
| Medium | `guzzlehttp/guzzle`, `phpseclib/phpseclib` | Known CVEs | Fixed in `composer.lock` (7.15.2, 3.0.57) |
| Medium | `appdashboard-mcp/package-lock.json` | Vulnerable npm deps incl. CRITICAL `proxy-addr` | Lock fixed via `npm audit fix --package-lock-only`; 1 high remains (needs `--force`/major bump). `node_modules` is root-owned, so rebuild via `deploy/scripts/build-appdashboard-mcp.sh`. |

## Not done (decisions or environment needed)
- Dockerfile DS-0002 (root user) in both Dockerfiles: nginx/supervisor need root; requires an architecture change.
- `.opencode`, `apps/lopaka/src` (pnpm), `webssh` dependency updates.
- CSRF tokens on 5 flagged forms, CSP `unsafe-eval`/`unsafe-inline`, SRI, HttpOnly on 2 cookies, `server_name _`, `browser_url` from `HTTP_HOST`.
- `echoed-request` (132) XSS triage, remaining `tainted-filename` triage, gitleaks whole-tree triage.
- Image rebuild/restart to apply nginx and PHP changes to the running stack.

## Tests
`tests/e2e/onlyoffice-callback-security.spec.ts`: 4 passed (unsigned OnlyOffice callback/download rejected; anonymous tts/transcription requests not served). Authenticated IDOR and traversal regression tests were not written (need seeded multi-user fixtures). Full e2e suite not run. `php -l` OK on all edited PHP files.

---
# Remediation round 3

Fixed:
- `webssh/composer.lock`: updated (lock only), `composer audit` clean.
- `apps/lopaka/src/pnpm-lock.yaml` + new `pnpm-workspace.yaml` (overrides from `pnpm audit --fix`): vulnerabilities 27 -> 1 moderate.
- `project/core.php` `create_browser_login`: `browser_url` now uses `APP_PUBLIC_BASE_URL` when set instead of the request `Host` header.

Reviewed, no change needed:
- CSRF on login/register/OTP forms: ZAP "Absence of Anti-CSRF Tokens" is a false positive. `apSecRequireCsrf()` (`project/bootstrap.php`) is enforced for all POSTs; an unauthenticated POST to `/login` without a token returned 403 `csrf_forbidden`.
- Semgrep `echoed-request` samples (`apps/recipes/styles.php`, `project/routes.php`, `ShareController.php`, `apps/cloud/ai.php`): values are regex-validated, escaped or JSON-encoded. Only a sample was reviewed, not all 132.
- `ap_device_id` cookie (no HttpOnly): set client-side for a non-secret device identifier; HttpOnly would break its use.

Deferred (architecture or product decision, no commit made since no architecture change was applied):
- Run containers as non-root (`Dockerfile`, `deploy/python-sidecar/Dockerfile`): nginx/supervisor need root and the sidecar shares volume ownership with PHP; needs a redesign and a full image rebuild/test.
- CSP `unsafe-eval`/`unsafe-inline` and SRI for CDN scripts: requires front-end refactoring.
- `server_name _` / real domain in nginx: requires deployment-specific domain configuration.
- `appdashboard-mcp`: 1 high-severity npm issue needs a major version bump.

Tests: full Playwright chromium suite run with the test account after installing the browser: 87 passed, 33 failed, 3 skipped. The failures are in UI specs (weather, monaco, localized app names, notifications, etc.); I did not compare against a pre-change baseline, so I cannot state that none are related to these changes. The earlier security fixes were already committed by another process in `e4dfb056`.

---
# Remediation round 4

- `appdashboard-mcp`: zod bumped to ^4.6.7 (no source imports zod; `tsc` build passes; `npm audit` 0 vulnerabilities). Rebuild the MCP package to ship it.
- `apps/lopaka/src`: `fflate` override added; `pnpm audit` reports no known vulnerabilities.
- CSP: `'unsafe-eval'` removed from all nginx configs (`nginx/`, `deploy/nginx/`). Verified: login, `/`, `/main` show no CSP/eval console errors; monaco/cloud-file/kanboard specs 20 passed, 4 failed (the same specs failed before the change; no monaco regressions). `'unsafe-inline'` and CDN SRI remain.
- `deploy/python-sidecar/Dockerfile`: `USER www-data` added (verified write access to the shared volume and `/health` as uid 33 in the running container; the image itself was not rebuilt).
- Main `appdashboard` image NOT converted to non-root. In `docker-compose.yml` the container is `privileged: true`, mounts the whole host `/` read-write at `/host`, and mounts `docker.sock`. Dropping root in the image would be cosmetic while those remain, and removing them disables the integrated restic backup/restore and Docker management features. This is a product decision: to truly close DS-0002/privilege exposure, drop `privileged`, the `/host` mount and the docker socket (or move those features into a dedicated helper service), then run nginx (already on 8080), php-fpm, mosquitto and supervisord as `www-data`.
