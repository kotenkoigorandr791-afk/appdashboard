# Achievements and territory exploration

Achievements are optional, private gamification derived only from confirmed,
owner-scoped location history. There is no leaderboard, feed, public embed or
public location link, and no transport-specific achievement.

## Rules and determinism

- Rules live in `core/geolocation_achievements.php` under a versioned id
  (`achievements-1`). Each definition has a family, metric, target, unit and
  themed collection.
- Metrics are computed in PostgreSQL/PostGIS from `geo_statistics_daily`,
  `geo_visits`/`geo_places`, accepted points (1° coverage cells) and the
  optionally imported, versioned `region` boundary dataset.
- `geo_achievements` stores `rule_version`, `input_watermark`, `progress`,
  `earned`, `first_earned_at`, `recalculated_at` and `reason` for every row, so
  the same input always rebuilds the same result and the first-earned time is
  preserved across rebuilds.

## Preference

`geo_achievement_settings` holds the per-owner tenant preference (enabled by
default). While disabled:

- the evaluator refuses to run and queued work is skipped;
- the API/UI return no achievement data (results are hidden, not deleted);
- canonical history is untouched.

Re-enabling queues a fresh evaluation and restores the stored first-earned
times.

## Recalculation

Imports, point/place edits and deletions queue a deduplicated
`geo_processor_runs` row of kind `achievements`. The worker
`bin/geolocation_achievements_run.php --loop` recalculates them; the settings
panel can also request a synchronous recompute. Run the migration with
`php apps/steps/bin/geolocation_migrate.php`.

## API

- `GET /apps/steps/api/session_gateway.php?endpoint=geolocation_achievements`
- `GET /apps/steps/api/v1.php/achievements` (scope `read:achievements`)
- `POST /apps/steps/api/v1.php` is not used for achievements; preference changes
  go through the owner-session endpoint above.

No achievement response contains coordinates or public links.
