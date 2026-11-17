# Steps location REST API v1

Base path: `/apps/steps/api/v1.php`. Deployed call form:

```
/apps/steps/api/v1.php?path=<resource>[/<id>]
/apps/steps/api/v1.php?path=points&limit=50&offset=0
```

The reference nginx does not forward `PATH_INFO`, so `…/v1.php/points` is not a
valid URL there. The router still accepts `PATH_INFO` when a deployment is
configured to forward it, but `?path=` is the canonical, verified form.

Every response includes `api_version` (`v1`) and `request_id`. Send
`X-Request-Id` to pin your own correlation id (8–64 chars of `A-Za-z0-9._-`).
Breaking changes will ship as `v2`; `v1` behaviour is stable.

There are no public-link, public-embed or unauthenticated endpoints.

## Authentication

Create a scoped key in Settings → API keys (or `POST
/apps/steps/api/session_gateway.php?endpoint=api_keys` from the signed-in
session). The plaintext secret `stp_…` is shown once.

```
X-API-Key: stp_…
Authorization: Bearer stp_…
```

Keys are stored as SHA-256 hashes, are tenant-owned, and can be rotated or
revoked. `last_used_at` is refreshed at most once per minute. Dashboard sessions
are also accepted and act with all scopes.

## Scopes

`read:points`, `read:trips`, `read:places`, `read:visits`, `read:family`,
`read:immich`, `read:imports`, `read:exports`, `read:statistics`,
`read:achievements`, `write:points`, `write:exports`.

Default for a new key: `read:points`, `read:trips`, `read:places`,
`read:visits`, `read:imports`, `read:statistics`, `read:achievements`.

## Endpoints

| Method | Resource | Scope | Notes |
| --- | --- | --- | --- |
| GET | `/points` | `read:points` | `from`, `to`, `bbox=minLon,minLat,maxLon,maxLat`, `state`, `source`, `device_id`, `limit`, `offset` |
| POST | `/points` | `write:points` | One point object or `{points:[…]}` (max 500). Returns per-point processing status. |
| GET | `/trips` | `read:trips` | `from`, `to`, `limit`, `offset` |
| GET | `/trips/{id}` | `read:trips` | Bounded geometry only when `geometry_visible` is true |
| GET | `/places` | `read:places` | `limit`, `offset` |
| GET | `/visits` | `read:visits` | `from`, `to`, `limit`, `offset` |
| GET | `/family/permissions` | `read:family` | Outgoing grants and incoming grants for the caller |
| GET | `/immich/assets` | `read:immich` | Metadata only; no binaries or thumbnails |
| GET | `/imports` | `read:imports` | Stable ids and processing states |
| GET | `/exports` | `read:exports` | Export history |
| POST | `/exports` | `write:exports` | `{format, scope}`; returns 202 with the job id |
| GET | `/statistics` | `read:statistics` | `tz`, `from`, `to` |
| GET | `/achievements` | `read:achievements` | Private progress; empty when disabled |

All list endpoints are owner-scoped at the service layer, ordered
deterministically and bounded by `limit` (1–200, default 50). Responses include
`total`, `limit`, `offset` and `next_offset` where a total is cheap to compute.

Examples:

```
curl -H "X-API-Key: stp_…" \
  "https://example.com/apps/steps/api/v1.php?path=points&bbox=30.4,50.4,30.7,50.6&limit=100"
curl -H "X-API-Key: stp_…" \
  "https://example.com/apps/steps/api/v1.php?path=trips&from=2026-01-01T00:00:00Z&to=2026-02-01T00:00:00Z"
```

## Errors

Errors use a stable body:

```json
{
  "ok": false,
  "error": "scope_required",
  "message": "The API key does not grant the required scope.",
  "details": {"required_scope": "read:trips"},
  "api_version": "v1",
  "request_id": "req_…"
}
```

Catalogue: `unauthorized`, `invalid_api_key`, `api_key_expired`,
`scope_required`, `invalid_scope`, `not_found`, `invalid_request`, `invalid_id`,
`invalid_limit`, `invalid_offset`, `invalid_range`, `invalid_bounds`,
`invalid_body`, `method_not_allowed`, `unknown_resource`, `rate_limited`,
`internal_error`. Authorization failures for other owners return `not_found`
rather than leaking existence.

## Rate limits

Application/Redis limits, keyed by account + device + IP:

- reads: 600 requests/minute;
- writes: 120 requests/minute;
- API-key management: 60 requests/minute.

Exceeding a limit returns HTTP 429 with `error: rate_limited`.

## Contract tests

`php apps/steps/tests/geolocation_api_test.php` and
`php apps/steps/tests/geolocation_api_db_test.php` cover scope validation,
hashing, rotation/revocation, ownership isolation, pagination bounds and the
error catalogue. The OpenAPI document lives at `docs/geolocation_api_v1.yaml`.
