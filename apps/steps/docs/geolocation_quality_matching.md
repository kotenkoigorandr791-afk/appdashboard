# Explainable quality, corrections, and contextual map matching

Task 34 adds a derived, optional geometry layer without changing the canonical
location record.

## Data model

- `geo_location_points` keeps raw coordinates immutable. Quality evidence is
  stored beside it: `quality_state`, `quality_score`, `quality_severity`,
  `quality_reason`, `quality_version`, `quality_details` and `correction_json`.
- `geo_quality_corrections` stores an owner-scoped, auditable scope and effect
  list plus previous point states. Undo restores only points still owned by that
  correction.
- `geo_matched_segments` stores a versioned derived line, raw point ids, raw
  geometry, confidence, internal mode, reasons, matcher version, network version
  and configuration. It never replaces `geo_location_points` or `geo_trips`.
- `geo_match_networks` stores local, versioned line candidates by region and
  access. No external map-matching API receives coordinates.
- `geo_match_settings` defaults to `conservative` matching and `raw` display.

Migration `019` is forward-only and idempotent. Raw points and raw trip geometry
are not rewritten.

## Quality states

`accepted`, `suspect`, `flagged`, `rejected`, `excluded_by_user`,
`private_redacted`, `not_enough_evidence` and `pending` are supported. Existing
raw reads still filter `rejected`; new derived reads also hide
`excluded_by_user` and `private_redacted`. Corrections are reversible and never
hide a hidden/private area or address another owner's points.

## Matcher

`StepsGeolocationMapMatcher` is pure PHP and deterministic. It:

- orders and deduplicates samples and records duplicate/out-of-order diagnostics;
- splits on long gaps and excluded/private points, preserving the gap;
- abstains for insufficient samples, low accuracy, impossible speed, stationary
  jitter, mixed sources, privacy/approximate scopes and unsuitable context;
- projects samples onto local network candidates and scores accuracy,
  coverage, monotonic continuity and bearing;
- abstains when road, path, rail or water candidates are too close to call, or
  when a candidate is not connected in sample order;
- only returns a matched line when one connected candidate clears the
  conservative threshold; otherwise `matched_geometry` is null and a reason is
  stored.

The internal `road/path/cycleway/rail/waterway/ferry/offroad` mode explains a
match only. It is never a transport label and never adds a user-facing mode.

## API

- `GET /apps/steps/api/session_gateway.php?endpoint=geolocation_quality`
  returns counts, bounded quality issues, active corrections, settings, matching
  summary and job runs.
- `POST geolocation_quality` actions: `reprocess`, `match`, `settings`,
  `correct`, `undo`.
- `GET ...?endpoint=geolocation_matching&from=&to=&trip_id=&geometry=` returns
  raw and matched coordinates, confidence, reasons and explanations.
- `POST geolocation_matching` actions: `settings`, `run`.
- `/api/steps/geolocation_quality` and `/api/steps/geolocation_matching` use the
  existing bearer-token allowlist for Android.

Every read is owner-scoped and bounded. Family reads continue through the
existing consent/scope/precision authorization path.

## Jobs and operations

`php apps/steps/bin/geolocation_matching_run.php` is the durable worker. It uses
the existing `geo_processor_runs` queue, owner leasing and bounded trip/network
reads, and replaces only that owner's derived segments. A disabled setting is a
successful no-op. The quality worker enqueues matching after quality changes.

Global incident response is to set the owner/project default to `disabled` and
stop the matching worker; raw history remains available. Rollback can stop the
worker and ignore `geo_matched_segments`/`geo_match_networks`; migration 019 does
not alter canonical coordinates.

## UI

Web Settings → Location contains geometry mode (raw/improved/compare), matching
mode (disabled/conservative/review-required), issue list with correction, range
corrections, active correction undo and matcher run controls. The map shows the
derived line as a dashed `Improved (derived)` layer with confidence/explanation.

Android Location → Quality lists quality issues and active corrections, offers
exclude/undo actions through the existing paired-device token API, and never
computes matching on the phone.

## Verification

- `apps/steps/tests/geolocation_map_matcher_test.php` covers the required
  situation matrix (urban, lawn, parallel roads, canyon, park, open ground,
  trail, cycleway, rail, tunnel gap, water/ferry/lake, shoreline, aircraft,
  indoor, private network, missing network, sparse, stationary, approximate
  permission, privacy boundary, duplicates and mixed sources).
- `apps/steps/tests/geolocation_matching_db_test.php` covers migration 019,
  reversible corrections, audit, settings and raw/matched read separation.
- Existing quality, map, trips, statistics, geofence, privacy and API tests must
  remain green.
