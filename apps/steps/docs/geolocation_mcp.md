# Geolocation MCP tools (task 29)

Steps exposes a narrow **read-only** location contract to the existing
`appdashboard-mcp` server. MCP never connects to PostgreSQL directly and has no
tool that can mutate, export, import or share location data.

## Authentication

* Endpoint: `POST /apps/steps/api/mcp_read.php`
* `Authorization: Bearer <64-hex Public API token>`
* `X-Device-Id: <32-128 hex>` (required by the Public API)
* Optional `X-Request-Id` is echoed and written to the audit event.
* The account must have access to the `steps` app.

The endpoint accepts only `tool` plus optional `arguments` (or flat fields).
Unknown tools return `404`; mutation, export, share, grant and purge names are
rejected with `403 mcp_read_only` **before** dispatch.

## Tools

| MCP tool | Purpose |
| --- | --- |
| `geolocation_get_current_location` | Latest accepted fix |
| `geolocation_search_places` | Saved places and visit counts |
| `geolocation_search_visits` | Visit events in a bounded interval |
| `geolocation_get_timeline` | Trips, stops, visits and gaps |
| `geolocation_get_trips` | Trip summaries |
| `geolocation_get_statistics` | Aggregated movement statistics |
| `geolocation_get_immich_photos` | Owner-only matched photo metadata |

## Permissions and limits

All calls pass through the task-23 family capability in
`StepsGeolocationPermissions::authorize()`:

* No active grant: family calls fail with `403 family_access_denied`.
* `current`: the latest fix must be inside the current window.
* `recent`: history is clamped to the last seven days.
* `range`: requests outside the granted interval are denied.
* `places`: only places and visits are available; current location, trips,
  statistics and photos are denied.
* Revocation and pause apply immediately; grants carry a generation.
* `get_immich_photos` is always owner-only; family grants never expose photos.

Intervals are capped at **31 days** (`range_too_large` beyond that), results at
**100** rows, and empty intervals/invalid limits fail closed. Requests are
bounded to the active grant; a caller can never widen it by asking for more.

Coordinates are rounded to 3 decimals for summaries (roughly 110 m). `detail:
true` returns up to 6 decimals, and a grant's `precision_m` still wins.
Timeline map bounds are removed for summaries and rounded for detailed reads.

## Audit and logging

Each call writes `mcp.tool_used` for the caller, and `mcp.shared_access` for the
owner when a family target is read. Audit context contains the tool, access
level, precision, result count and correlation id — never coordinates, tokens
or raw arguments. Audit retention is controlled by the tenant privacy policy.

## Running

```bash
cd appdashboard-mcp
npm run build
```

The group is `geolocation`; disable it with `MCP_GROUPS` like any other group.
