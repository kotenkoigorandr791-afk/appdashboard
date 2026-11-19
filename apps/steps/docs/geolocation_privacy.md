# Geolocation privacy and security (task 30)

All location data is tenant-owned. Every query filters by the normalized owner,
and sensitive responses, logs and audit contexts pass through
`StepsGeolocationSecurity`. There are no public location, trip or share links.

## Controls

* **Authorization** — API and services resolve the caller, enforce app access,
  and use owner predicates. `requireOwner()` fails closed on mismatched rows.
* **Family capabilities** — read access is a separate, consented grant with a
  level, optional precision and expiry. Revocation increments a generation.
* **Coordinate minimization** — summaries are rounded according to the tenant
  policy; MCP uses 3 decimals unless a detail read and the grant permit more.
* **SSRF** — Immich URLs are scheme/host/credential validated and resolved.
  Loopback, link-local, private and reserved addresses are denied, including
  the cloud metadata IP. A LAN Immich host must be explicitly allowlisted:
  `GEO_SSRF_ALLOW_PRIVATE_HOSTS=immich-server,10.0.0.5`.
* **Uploads** — nginx caps the Steps API at 260 MB; PHP rejects declared bodies
  over the import limit (`GEO_IMPORT_MAX_BYTES`, default 256 MB) with `413`.
* **Transport** — session cookies are `Secure` on HTTPS, `HttpOnly`,
  `SameSite=Strict`; the Public API enforces HTTPS in production.
* **Secrets** — Immich URLs/keys and webhook secrets remain AES-GCM encrypted
  at rest; no secret is returned to MCP or written to audit.
* **Audit** — reads, sharing, exports, credential changes and deletion events
  are recorded with redacted context (no coordinates or credentials).

## Privacy API

`/apps/steps/api/geolocation_privacy.php` (session, owner only)

* `GET` — policy, row counts and recent redacted audit.
* `POST {action:"policy"}` — `summary_precision_decimals` 0–4,
  `retention_days` 0–3650, `audit_retention_days` 30–3650.
* `POST {action:"correct_point", point_id, latitude, longitude, observed_at?}`
  — corrects one owner point and queues derived rebuilds.
* `POST {action:"delete_range", from, to}` — deletes points and overlapping
  trips/stops/visits/geofence events, then queues rebuilds.
* `POST {action:"purge", confirmation:"PURGE"}` — removes every tenant-owned
  row, export, import file and credential for the account. `async:true` queues
  the worker instead. The final `privacy.account_purged` audit event remains.
* Writes are rate limited to 30/minute.

A background worker (`apps/steps/bin/geolocation_privacy_run.php --loop`,
supervised as `steps-geolocation-privacy`) applies retention, prunes audit rows
and processes queued purges and expired exports.

## Retention and backups

Retention deletes points, derived data, Immich metadata and old audit rows older
than the configured cutoff. Database backups follow the platform backup
retention policy; deleted tenant data is never restored into another account.
Immich binaries are never copied into Steps.
