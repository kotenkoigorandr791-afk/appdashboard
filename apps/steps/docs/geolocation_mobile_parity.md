# Geolocation mobile parity

This document records the bearer-token mobile surface and the Android screen
that consumes it. It is intentionally narrower than the full task-33 roadmap:
only capabilities that are implemented and verified are listed as available.

## Mobile bearer endpoints

`/api/steps/*` is token-authenticated by `public_api_bootstrap()`. The Steps
router forwards this explicit allowlist to the existing endpoint
implementations, so owner scoping, permissions, validation and audit logging
stay in one place:

| Endpoint | Methods | Purpose |
| --- | --- | --- |
| `geolocation`, `geolocation_map` | GET | Map layers and settings |
| `geolocation_trips` | GET, POST | Trips, archive/update/create/reprocess |
| `geolocation_stops` | GET, POST | Visits and visit review |
| `geolocation_achievements` | GET, POST | Private achievement progress and preference |
| `places` | GET, POST | Personal places |
| `geofences` | GET, POST | Geofences and entry/exit events |
| `devices` | GET, POST | Location devices, pause/resume/revoke |
| `geolocation_quality` | GET, POST | Quality issues, corrections, match settings |
| `geolocation_matching` | GET, POST | Raw/improved geometry and derived segments |
| `geolocation_search` | GET | Unified authorized private search |
| `geolocation_saved_views` | GET, POST | Private saved-view CRUD/apply/templates |
| `geolocation_analytics` | GET, POST | Coverage-aware analytics summaries |

All routes reject missing or invalid tokens before the endpoint runs. No
endpoint in this list is public, and no stored secret is returned by the
location-library screen.

## Android location library

`LocationLibraryScreen` is available from the bottom navigation as **Location**.
It has wrapped tabs for Places, Trips, Visits, Achievements, Geofences,
Devices, Quality, Search and Saved views. Each tab has explicit loading, empty,
disabled and error states, a manual refresh, and no coordinates or tokens in
the UI. Search is debounced and cancellable; saved-view apply switches to the
Search tab with the saved query.

Supported actions:

- delete a place;
- archive a trip;
- confirm or reject a visit;
- pause/resume/revoke a device;
- disable a geofence.

Destructive and privacy-sensitive actions require a confirmation dialog. The
screen reloads from the server after every mutation, so it never treats an
optimistic local change as saved.

## Remaining task-33 work

The following areas are not exposed in Android yet and remain follow-up work:

- import upload/preview/validation and retry;
- export creation, download/share and revocation;
- day/route renders gallery and job controls;
- Immich configuration and matched-photo gallery;
- family groups, invitations, consent and per-category permissions;
- API-key lifecycle and API documentation entry point;
- full timeline playback, layer detail sheets and entity editing beyond the
  actions listed above.

Web already contains dedicated settings for most of these areas; the remaining
work is mobile UI plus the corresponding bearer-token route allowlist.
