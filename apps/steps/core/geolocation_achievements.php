<?php
/**
 * Achievements and territory exploration (task 27).
 *
 * Achievements are optional gamification derived only from canonical,
 * owner-confirmed history. They are private to their owner by default: no
 * leaderboard, feed, public embed or public location link exists. The module is
 * controlled by a per-owner tenant preference; disabling it hides all data and
 * stops new evaluations without deleting canonical history or previous results.
 *
 * Every result is deterministic for an input watermark: definitions are
 * versioned, metrics are computed only from owner-scoped summaries/boundaries in
 * PostgreSQL/PostGIS, and each row stores its rule version, input watermark,
 * progress, earned state, first-earned time and recalculation reason.
 */
require_once __DIR__ . '/geolocation_statistics.php';

final class StepsGeolocationAchievements
{
    public const RULES_VERSION = 'achievements-2';
    public const DEFAULT_TIMEZONE = 'UTC';
    /** Achievements never leave the owner's account. */
    public const PUBLIC = false;
    public const REASONS = ['import', 'edit', 'delete', 'manual', 'scheduled', 'preference', 'rules'];

    /** Themed sets; a collection groups related achievement ids by prefix. */
    public const COLLECTIONS = [
        'explorer' => ['title_key' => 'achievement_collection_explorer', 'prefixes' => ['countries_', 'cities_']],
        'pathfinder' => ['title_key' => 'achievement_collection_pathfinder', 'prefixes' => ['regions_', 'distance_']],
        'local' => ['title_key' => 'achievement_collection_local', 'prefixes' => ['places_', 'coverage_']],
        // Walking milestones come from the daily step counter, so the steps half
        // of the app feeds the same private achievement set as the track half.
        'walker' => ['title_key' => 'achievement_collection_walker', 'prefixes' => ['steps_total_', 'steps_day_', 'steps_goal_', 'steps_streak_', 'steps_walk_']],
        // Hybrid milestones: days that satisfy the step goal *and* carry an
        // accepted track sample, which is the point where both halves meet.
        'trailblazer' => ['title_key' => 'achievement_collection_trailblazer', 'prefixes' => ['steps_trail_']],
    ];

    /** @return list<array{id:string,family:string,metric:string,target:int,unit:string,collection:string}> */
    public static function definitions(): array
    {
        $out = [];
        $add = static function (string $id, string $family, string $metric, int $target, string $unit, string $collection) use (&$out): void {
            $out[] = [
                'id' => $id,
                'family' => $family,
                'metric' => $metric,
                'target' => $target,
                'unit' => $unit,
                'collection' => $collection,
                'title_key' => 'achievement_family_' . $family,
            ];
        };

        foreach ([1 => 'explorer', 3 => 'explorer', 5 => 'explorer', 10 => 'explorer', 20 => 'explorer'] as $target => $collection) {
            $add('countries_' . $target, 'countries', 'countries', $target, 'count', $collection);
        }
        foreach ([1 => 'pathfinder', 3 => 'pathfinder', 5 => 'pathfinder', 10 => 'pathfinder'] as $target => $collection) {
            $add('regions_' . $target, 'regions', 'regions', $target, 'count', $collection);
        }
        foreach ([1 => 'explorer', 5 => 'explorer', 10 => 'explorer', 25 => 'explorer', 50 => 'explorer'] as $target => $collection) {
            $add('cities_' . $target, 'cities', 'cities', $target, 'count', $collection);
        }
        foreach ([1 => 'local', 3 => 'local', 5 => 'local', 10 => 'local', 25 => 'local'] as $target => $collection) {
            $add('places_' . $target, 'places', 'places', $target, 'count', $collection);
        }
        foreach ([100 => 'pathfinder', 500 => 'pathfinder', 1000 => 'pathfinder', 5000 => 'pathfinder', 10000 => 'pathfinder'] as $kilometres => $collection) {
            $add('distance_' . $kilometres, 'distance', 'distance_m', $kilometres * 1000, 'm', $collection);
        }
        foreach ([7 => 'local', 30 => 'local', 100 => 'local', 365 => 'local'] as $days => $collection) {
            $add('coverage_days_' . $days, 'coverage', 'coverage_days', $days, 'days', $collection);
        }
        foreach ([10 => 'local', 50 => 'local', 200 => 'local'] as $cells => $collection) {
            $add('coverage_cells_' . $cells, 'coverage', 'coverage_cells', $cells, 'cells', $collection);
        }
        // Step-counter milestones. They read steps_days/goal_steps, i.e. the same
        // owner-scoped data the steps screen renders, so the two halves of the
        // app report one consistent story instead of two parallel ladders.
        foreach ([10_000 => 'walker', 100_000 => 'walker', 500_000 => 'walker', 1_000_000 => 'walker', 5_000_000 => 'walker'] as $steps => $collection) {
            $add('steps_total_' . $steps, 'steps_total', 'steps_total', $steps, 'steps', $collection);
        }
        foreach ([5_000 => 'walker', 10_000 => 'walker', 15_000 => 'walker', 20_000 => 'walker', 30_000 => 'walker'] as $steps => $collection) {
            $add('steps_day_' . $steps, 'steps_day', 'steps_best_day', $steps, 'steps', $collection);
        }
        foreach ([7 => 'walker', 30 => 'walker', 100 => 'walker', 365 => 'walker'] as $days => $collection) {
            $add('steps_goal_' . $days, 'steps_goal', 'steps_goal_days', $days, 'days', $collection);
        }
        foreach ([3 => 'walker', 7 => 'walker', 30 => 'walker', 100 => 'walker'] as $days => $collection) {
            $add('steps_streak_' . $days, 'steps_streak', 'steps_streak', $days, 'days', $collection);
        }
        foreach ([50 => 'walker', 200 => 'walker', 500 => 'walker', 1_000 => 'walker', 5_000 => 'walker'] as $kilometres => $collection) {
            $add('steps_walk_' . $kilometres, 'steps_walk', 'steps_distance_m', $kilometres * 1000, 'm', $collection);
        }
        foreach ([7 => 'trailblazer', 30 => 'trailblazer', 100 => 'trailblazer'] as $days => $collection) {
            $add('steps_trail_' . $days, 'steps_trail', 'steps_trail_days', $days, 'days', $collection);
        }
        return $out;
    }

    /**
     * Presentation metadata for earned cards. Icons are semantic names, not
     * images, so the web and Android clients can render their own icon sets.
     *
     * @return array{icon:string,description_key:string}
     */
    private static function presentation(string $family): array
    {
        return match ($family) {
            'countries' => ['icon' => 'public', 'description_key' => 'achievement_desc_countries'],
            'regions' => ['icon' => 'map', 'description_key' => 'achievement_desc_regions'],
            'cities' => ['icon' => 'location_city', 'description_key' => 'achievement_desc_cities'],
            'places' => ['icon' => 'place', 'description_key' => 'achievement_desc_places'],
            'distance' => ['icon' => 'route', 'description_key' => 'achievement_desc_distance'],
            'steps_total' => ['icon' => 'footprints', 'description_key' => 'achievement_desc_steps_total'],
            'steps_day' => ['icon' => 'star', 'description_key' => 'achievement_desc_steps_day'],
            'steps_goal' => ['icon' => 'bullseye', 'description_key' => 'achievement_desc_steps_goal'],
            'steps_streak' => ['icon' => 'fire', 'description_key' => 'achievement_desc_steps_streak'],
            'steps_walk' => ['icon' => 'walking', 'description_key' => 'achievement_desc_steps_walk'],
            'steps_trail' => ['icon' => 'trail', 'description_key' => 'achievement_desc_steps_trail'],
            default => ['icon' => 'calendar', 'description_key' => 'achievement_desc_coverage'],
        };
    }

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    public static function normalizeReason(string $reason): string
    {
        $reason = strtolower(trim($reason));
        return in_array($reason, self::REASONS, true) ? $reason : 'manual';
    }

    /**
     * Tenant preference. Defaults to enabled; achievements stay private either way.
     * @return array{enabled:bool,timezone:string,updated_at:?string}
     */
    public static function settings(PDO $pdo, string $owner, bool $createDefault = false): array
    {
        $stmt = $pdo->prepare('SELECT enabled, timezone, updated_at FROM geo_achievement_settings WHERE user_email = :owner');
        $stmt->execute([':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            if ($createDefault) {
                $pdo->prepare('INSERT INTO geo_achievement_settings(user_email, enabled, timezone) VALUES(:owner, true, :tz) ON CONFLICT (user_email) DO NOTHING')
                    ->execute([':owner' => $owner, ':tz' => self::DEFAULT_TIMEZONE]);
            }
            return ['enabled' => true, 'timezone' => self::DEFAULT_TIMEZONE, 'updated_at' => null];
        }
        return [
            'enabled' => (bool)$row['enabled'],
            'timezone' => StepsGeolocationStatistics::timezone((string)$row['timezone']),
            'updated_at' => $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
        ];
    }

    public static function isEnabled(PDO $pdo, string $owner): bool
    {
        return self::settings($pdo, $owner)['enabled'];
    }

    /**
     * Update the tenant preference. Enabling queues a fresh evaluation, disabling
     * only freezes results and never touches canonical history.
     */
    public static function setEnabled(PDO $pdo, string $owner, bool $enabled, string $timezone = self::DEFAULT_TIMEZONE): array
    {
        $timezone = StepsGeolocationStatistics::timezone($timezone);
        $pdo->prepare(
            'INSERT INTO geo_achievement_settings(user_email, enabled, timezone, updated_at) VALUES(:owner, :enabled, :tz, now())
             ON CONFLICT (user_email) DO UPDATE SET enabled = EXCLUDED.enabled, timezone = EXCLUDED.timezone, updated_at = now()'
        )->execute([':owner' => $owner, ':enabled' => $enabled ? 'true' : 'false', ':tz' => $timezone]);
        if ($enabled) self::enqueue($pdo, $owner, 'preference');
        return self::settings($pdo, $owner);
    }

    /**
     * Step-counter metrics. The steps half of the app keeps its daily totals in
     * `steps_days` (same PostgreSQL database) and its personal goal/stride in
     * `steps_settings`, so walking milestones can be evaluated in the very same
     * deterministic pass as the location rules instead of living in a separate
     * gamification silo. Every value stays owner-scoped and private.
     *
     * @return array<string,int|float>
     */
    public static function stepsMetrics(PDO $pdo, string $owner): array
    {
        $owner = strtolower(trim($owner));
        $out = [
            'steps_total' => 0,
            'steps_best_day' => 0,
            'steps_active_days' => 0,
            'steps_goal_days' => 0,
            'steps_streak' => 0,
            'steps_distance_m' => 0.0,
            'steps_trail_days' => 0,
            'steps_goal' => 10000,
            'steps_stride_cm' => 70,
        ];
        if (!self::tableExists($pdo, 'steps_days')) return $out;

        // Personal goal and stride, with the same defaults the steps settings use.
        if (self::tableExists($pdo, 'steps_settings')) {
            $settings = $pdo->prepare('SELECT key, value FROM steps_settings WHERE user_email = :owner');
            $settings->execute([':owner' => $owner]);
            foreach ($settings->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['key'] === 'goal_steps' && (int)$row['value'] > 0) $out['steps_goal'] = (int)$row['value'];
                if ($row['key'] === 'stride_length' && (int)$row['value'] > 0) $out['steps_stride_cm'] = (int)$row['value'];
            }
        }

        $daily = $pdo->prepare(
            'SELECT COALESCE(SUM(steps), 0) AS total,
                    COALESCE(MAX(steps), 0) AS best,
                    COUNT(*) FILTER (WHERE steps > 0) AS active_days,
                    COUNT(*) FILTER (WHERE steps >= :goal) AS goal_days
               FROM steps_days
              WHERE user_email = :owner'
        );
        $daily->execute([':goal' => (int)$out['steps_goal'], ':owner' => $owner]);
        $row = $daily->fetch(PDO::FETCH_ASSOC) ?: [];
        $out['steps_total'] = (int)($row['total'] ?? 0);
        $out['steps_best_day'] = (int)($row['best'] ?? 0);
        $out['steps_active_days'] = (int)($row['active_days'] ?? 0);
        $out['steps_goal_days'] = (int)($row['goal_days'] ?? 0);
        $out['steps_distance_m'] = round($out['steps_total'] * ((int)$out['steps_stride_cm'] / 100), 2);

        // Longest run of consecutive active days. Computed in PHP from the stored
        // dates so the result does not depend on database interval arithmetic.
        $dates = $pdo->prepare('SELECT date FROM steps_days WHERE user_email = :owner AND steps > 0 ORDER BY date ASC');
        $dates->execute([':owner' => $owner]);
        $streak = 0;
        $bestStreak = 0;
        $previous = null;
        foreach ($dates->fetchAll(PDO::FETCH_COLUMN) as $raw) {
            $day = self::parseDay((string)$raw);
            if ($day === null) continue;
            if ($previous !== null && $day->getTimestamp() - $previous->getTimestamp() === 86400) $streak++;
            else $streak = 1;
            if ($streak > $bestStreak) $bestStreak = $streak;
            $previous = $day;
        }
        $out['steps_streak'] = $bestStreak;

        // Hybrid days: the step goal was met and the track half recorded accepted
        // samples that day. This is the explicit bridge between both halves.
        if (self::tableExists($pdo, 'geo_statistics_daily')) {
            $trail = $pdo->prepare(
                'SELECT COUNT(DISTINCT s.date)
                   FROM steps_days s
                   JOIN geo_statistics_daily g
                     ON g.user_email = s.user_email AND g.day = s.date::date
                  WHERE s.user_email = :owner AND s.steps >= :goal AND g.accepted_count > 0'
            );
            $trail->execute([':goal' => (int)$out['steps_goal'], ':owner' => $owner]);
            $out['steps_trail_days'] = (int)$trail->fetchColumn();
        }
        return $out;
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT to_regclass('public.' || :name)");
            $stmt->execute([':name' => $table]);
            return $stmt->fetchColumn() !== null;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function parseDay(string $raw): ?DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return null;
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'));
        return $day === false ? null : $day;
    }

    /**
     * Deterministic metrics for the current input. Only owner-scoped rows are read;
     * no live geocoder, no public data and no transport inference are involved.
     *
     * @return array<string,mixed>
     */
    public static function metrics(PDO $pdo, string $owner): array
    {
        $owner = strtolower(trim($owner));
        $daily = $pdo->prepare(
            "SELECT COUNT(DISTINCT NULLIF(country_code, '')) AS countries,
                    COUNT(DISTINCT NULLIF(city_name, '')) AS cities,
                    COUNT(*) FILTER (WHERE point_count > 0) AS coverage_days,
                    COALESCE(SUM(distance_m), 0) AS distance_m
               FROM geo_statistics_daily
              WHERE user_email = :owner"
        );
        $daily->execute([':owner' => $owner]);
        $row = $daily->fetch(PDO::FETCH_ASSOC) ?: [];

        $places = $pdo->prepare(
            'SELECT COUNT(DISTINCT v.place_id)
               FROM geo_visits v
               JOIN geo_places p ON p.id = v.place_id AND p.deleted_at IS NULL
              WHERE v.user_email = :owner AND v.place_id IS NOT NULL'
        );
        $places->execute([':owner' => $owner]);

        $cells = $pdo->prepare(
            "SELECT COUNT(*) FROM (
                SELECT DISTINCT FLOOR(ST_Y(position) + 90)::int AS gy, FLOOR(ST_X(position) + 180)::int AS gx
                  FROM geo_location_points
                 WHERE user_email = :owner AND quality_state <> 'rejected'
             ) cells"
        );
        $cells->execute([':owner' => $owner]);

        $regions = 0;
        $regionDataset = StepsGeolocationStatistics::boundaryDataset($pdo, 'region');
        if ($regionDataset !== null) {
            $regionStmt = $pdo->prepare(
                "SELECT COUNT(DISTINCT a.code)
                   FROM geo_location_points p
                   JOIN geo_boundary_areas a
                     ON a.dataset_version = :version AND a.kind = 'region' AND ST_Covers(a.geometry, p.position)
                  WHERE p.user_email = :owner AND p.quality_state <> 'rejected'"
            );
            $regionStmt->execute([':version' => $regionDataset['version'], ':owner' => $owner]);
            $regions = (int)$regionStmt->fetchColumn();
        }

        $steps = self::stepsMetrics($pdo, $owner);
        $sw = ['day_count' => 0, 'day_max' => ''];
        if (self::tableExists($pdo, 'steps_days')) {
            $stepsWatermark = $pdo->prepare(
                'SELECT COUNT(*) AS day_count, COALESCE(MAX(date), \'\') AS day_max FROM steps_days WHERE user_email = :owner'
            );
            $stepsWatermark->execute([':owner' => $owner]);
            $sw = $stepsWatermark->fetch(PDO::FETCH_ASSOC) ?: $sw;
        }

        $watermark = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM geo_location_points WHERE user_email = :owner) AS point_count,
                (SELECT COALESCE(MAX(source_at)::text, '') FROM geo_location_points WHERE user_email = :owner) AS point_max,
                (SELECT COUNT(*) FROM geo_visits WHERE user_email = :owner) AS visit_count,
                (SELECT COALESCE(MAX(updated_at)::text, '') FROM geo_visits WHERE user_email = :owner) AS visit_max,
                (SELECT COUNT(*) FROM geo_places WHERE user_email = :owner AND deleted_at IS NULL) AS place_count,
                (SELECT COALESCE(MAX(updated_at)::text, '') FROM geo_places WHERE user_email = :owner AND deleted_at IS NULL) AS place_max,
                (SELECT COUNT(*) FROM geo_imports WHERE user_email = :owner AND deleted_at IS NULL) AS import_count,
                (SELECT COALESCE(MAX(updated_at)::text, '') FROM geo_imports WHERE user_email = :owner AND deleted_at IS NULL) AS import_max"
        );
        $watermark->execute([':owner' => $owner]);
        $w = $watermark->fetch(PDO::FETCH_ASSOC) ?: [];
        $inputWatermark = hash('sha256', implode('|', [
            (string)($w['point_count'] ?? 0), (string)($w['point_max'] ?? ''),
            (string)($w['visit_count'] ?? 0), (string)($w['visit_max'] ?? ''),
            (string)($w['place_count'] ?? 0), (string)($w['place_max'] ?? ''),
            (string)($w['import_count'] ?? 0), (string)($w['import_max'] ?? ''),
            (string)($sw['day_count'] ?? 0), (string)($sw['day_max'] ?? ''),
            (string)$steps['steps_total'], (string)$steps['steps_goal'], (string)$steps['steps_stride_cm'],
            (string)($regionDataset['version'] ?? ''), self::RULES_VERSION,
        ]));

        return [
            'countries' => (int)($row['countries'] ?? 0),
            'regions' => $regions,
            'cities' => (int)($row['cities'] ?? 0),
            'places' => (int)$places->fetchColumn(),
            'distance_m' => round((float)($row['distance_m'] ?? 0), 2),
            'coverage_days' => (int)($row['coverage_days'] ?? 0),
            'coverage_cells' => (int)$cells->fetchColumn(),
            'steps_total' => (int)$steps['steps_total'],
            'steps_best_day' => (int)$steps['steps_best_day'],
            'steps_active_days' => (int)$steps['steps_active_days'],
            'steps_goal_days' => (int)$steps['steps_goal_days'],
            'steps_streak' => (int)$steps['steps_streak'],
            'steps_distance_m' => (float)$steps['steps_distance_m'],
            'steps_trail_days' => (int)$steps['steps_trail_days'],
            'steps_goal' => (int)$steps['steps_goal'],
            'input_watermark' => $inputWatermark,
            'boundary_versions' => [
                'country' => (string)(StepsGeolocationStatistics::boundaryDataset($pdo, 'country')['version'] ?? ''),
                'region' => (string)($regionDataset['version'] ?? ''),
                'city' => (string)(StepsGeolocationStatistics::boundaryDataset($pdo, 'city')['version'] ?? ''),
            ],
        ];
    }

    /**
     * Pure, deterministic evaluation shared by the worker, API and tests.
     * @param array<string,mixed> $metrics
     * @return list<array<string,mixed>>
     */
    public static function evaluate(array $metrics): array
    {
        $rows = [];
        foreach (self::definitions() as $definition) {
            $value = (float)($metrics[$definition['metric']] ?? 0);
            if ($value < 0) $value = 0.0;
            $progress = min($value, (float)$definition['target']);
            if ($definition['unit'] !== 'm') $progress = (float)(int)floor($progress);
            else $progress = round($progress, 2);
            $rows[] = [
                'achievement_id' => $definition['id'],
                'family' => $definition['family'],
                'collection' => $definition['collection'],
                'metric' => $definition['metric'],
                'target' => $definition['target'],
                'unit' => $definition['unit'],
                'progress' => $progress,
                'earned' => $value >= (float)$definition['target'],
            ];
        }
        return $rows;
    }

    /**
     * Recompute and persist progress. When the module is disabled this is a no-op
     * so no new evaluation happens and existing rows stay frozen.
     *
     * @return array{skipped:bool,reason?:string,rules_version:string,input_watermark?:string,rows?:int,enabled?:bool}
     */
    public static function recalculate(PDO $pdo, string $owner, string $reason = 'manual'): array
    {
        $owner = strtolower(trim($owner));
        $reason = self::normalizeReason($reason);
        $settings = self::settings($pdo, $owner);
        if (!$settings['enabled']) {
            return ['skipped' => true, 'reason' => 'disabled', 'rules_version' => self::RULES_VERSION, 'enabled' => false];
        }

        // Achievements read the owner-only daily summary, so bring it in line with
        // canonical points first; this is exactly the bounded PostgreSQL pass the
        // statistics worker uses and never calls a live service.
        StepsGeolocationStatistics::refresh($pdo, $owner, self::DEFAULT_TIMEZONE);
        $metrics = self::metrics($pdo, $owner);
        $evaluated = self::evaluate($metrics);

        $pdo->beginTransaction();
        try {
            $upsert = $pdo->prepare(
                "INSERT INTO geo_achievements(user_email, rule_version, achievement_id, family, collection, target, progress, earned, first_earned_at, input_watermark, reason, details_json, recalculated_at)
                 VALUES(:owner, :version, :achievement, :family, :collection, :target, :progress, :earned, :first_earned_at, :watermark, :reason, :details, now())
                 ON CONFLICT (user_email, rule_version, achievement_id) DO UPDATE SET
                     family = EXCLUDED.family,
                     collection = EXCLUDED.collection,
                     target = EXCLUDED.target,
                     progress = EXCLUDED.progress,
                     earned = EXCLUDED.earned,
                     first_earned_at = COALESCE(geo_achievements.first_earned_at, EXCLUDED.first_earned_at),
                     input_watermark = EXCLUDED.input_watermark,
                     reason = EXCLUDED.reason,
                     details_json = EXCLUDED.details_json,
                     recalculated_at = now()"
            );
            $details = json_encode([
                'boundary_versions' => $metrics['boundary_versions'],
                'metrics' => [
                    'countries' => $metrics['countries'],
                    'regions' => $metrics['regions'],
                    'cities' => $metrics['cities'],
                    'places' => $metrics['places'],
                    'distance_m' => $metrics['distance_m'],
                    'coverage_days' => $metrics['coverage_days'],
                    'coverage_cells' => $metrics['coverage_cells'],
                    'steps_total' => $metrics['steps_total'],
                    'steps_best_day' => $metrics['steps_best_day'],
                    'steps_active_days' => $metrics['steps_active_days'],
                    'steps_goal_days' => $metrics['steps_goal_days'],
                    'steps_streak' => $metrics['steps_streak'],
                    'steps_distance_m' => $metrics['steps_distance_m'],
                    'steps_trail_days' => $metrics['steps_trail_days'],
                ],
            ], JSON_UNESCAPED_SLASHES);
            foreach ($evaluated as $row) {
                $upsert->execute([
                    ':owner' => $owner,
                    ':version' => self::RULES_VERSION,
                    ':achievement' => $row['achievement_id'],
                    ':family' => $row['family'],
                    ':collection' => $row['collection'],
                    ':target' => $row['target'],
                    ':progress' => $row['progress'],
                    ':earned' => $row['earned'] ? 'true' : 'false',
                    ':first_earned_at' => $row['earned'] ? gmdate('c') : null,
                    ':watermark' => $metrics['input_watermark'],
                    ':reason' => $reason,
                    ':details' => $details,
                ]);
            }
            $done = $pdo->prepare(
                "UPDATE geo_processor_runs
                    SET state = 'ready', details_json = :details, updated_at = now()
                  WHERE user_email = :owner AND kind = 'achievements' AND state IN ('pending','processing')"
            );
            $done->execute([
                ':details' => json_encode([
                    'rules_version' => self::RULES_VERSION,
                    'input_watermark' => $metrics['input_watermark'],
                    'reason' => $reason,
                ], JSON_UNESCAPED_SLASHES),
                ':owner' => $owner,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return [
            'skipped' => false,
            'rules_version' => self::RULES_VERSION,
            'input_watermark' => $metrics['input_watermark'],
            'rows' => count($evaluated),
            'enabled' => true,
        ];
    }

    /** Queue one bounded, deduplicated evaluation for the owner. */
    public static function enqueue(PDO $pdo, string $owner, string $reason = 'edit'): bool
    {
        $owner = strtolower(trim($owner));
        if (!self::isEnabled($pdo, $owner)) return false;
        $reason = self::normalizeReason($reason);
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare(
                "SELECT id FROM geo_processor_runs
                  WHERE user_email = :owner AND kind = 'achievements' AND state IN ('pending','processing')
               ORDER BY created_at DESC LIMIT 1 FOR UPDATE"
            );
            $existing->execute([':owner' => $owner]);
            $id = $existing->fetchColumn();
            if ($id) {
                $pdo->prepare("UPDATE geo_processor_runs SET state = 'pending', details_json = :details, updated_at = now() WHERE id = :id")
                    ->execute([':details' => json_encode(['reason' => $reason], JSON_UNESCAPED_SLASHES), ':id' => $id]);
                $pdo->commit();
                return false;
            }
            $pdo->prepare(
                "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, details_json)
                 VALUES(:id, :owner, 'achievements', 'pending', :version, :details)"
            )->execute([
                ':id' => self::uuid(),
                ':owner' => $owner,
                ':version' => self::RULES_VERSION,
                ':details' => json_encode(['reason' => $reason], JSON_UNESCAPED_SLASHES),
            ]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Safe hook for edit/delete/import call sites that may not have loaded this file. */
    public static function enqueueIfEnabled(PDO $pdo, string $owner, string $reason = 'edit'): void
    {
        try {
            self::enqueue($pdo, $owner, $reason);
        } catch (Throwable $e) {
            error_log('achievements enqueue: ' . $e->getMessage());
        }
    }

    /**
     * Owner-facing progress. Hidden entirely while disabled, and never contains
     * public links, coordinates or another owner's data.
     *
     * @return array<string,mixed>
     */
    public static function progress(PDO $pdo, string $owner): array
    {
        $owner = strtolower(trim($owner));
        $settings = self::settings($pdo, $owner);
        $base = [
            'private' => true,
            'rules_version' => self::RULES_VERSION,
            'collections_definition' => self::COLLECTIONS,
        ];
        if (!$settings['enabled']) {
            return $base + [
                'enabled' => false,
                'frozen' => true,
                'achievements' => [],
                'earned_achievements' => [],
                'collections' => [],
                'earned_count' => 0,
                'total_count' => 0,
                'input_watermark' => null,
                'recalculated_at' => null,
                'highlights' => self::zeroHighlights(),
            ];
        }

        $count = $pdo->prepare('SELECT COUNT(*) FROM geo_achievements WHERE user_email = :owner AND rule_version = :version');
        $count->execute([':owner' => $owner, ':version' => self::RULES_VERSION]);
        if ((int)$count->fetchColumn() === 0) {
            self::recalculate($pdo, $owner, 'manual');
        }

        $stmt = $pdo->prepare(
            'SELECT achievement_id, family, collection, target, progress, earned, first_earned_at, input_watermark, reason, recalculated_at
               FROM geo_achievements
              WHERE user_email = :owner AND rule_version = :version
           ORDER BY family ASC, target ASC, achievement_id ASC'
        );
        $stmt->execute([':owner' => $owner, ':version' => self::RULES_VERSION]);
        $stored = [];
        $watermark = null;
        $recalculatedAt = null;
        $earnedCount = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $definition = null;
            foreach (self::definitions() as $candidate) {
                if ($candidate['id'] === $row['achievement_id']) { $definition = $candidate; break; }
            }
            if ($definition === null) continue;
            $progress = (float)$row['progress'];
            $target = (float)$row['target'];
            $earned = (bool)$row['earned'];
            if ($earned) $earnedCount++;
            $watermark = $row['input_watermark'] !== '' ? (string)$row['input_watermark'] : $watermark;
            $recalculatedAt = $row['recalculated_at'] !== null ? (string)$row['recalculated_at'] : $recalculatedAt;
            $presentation = self::presentation($definition['family']);
            $stored[] = [
                'id' => $definition['id'],
                'family' => $definition['family'],
                'collection' => $definition['collection'],
                'category' => $definition['collection'],
                'title_key' => $definition['title_key'],
                'description_key' => $presentation['description_key'],
                'icon' => $presentation['icon'],
                'tier' => $target,
                'target' => $target,
                'unit' => $definition['unit'],
                'progress' => $progress,
                'percent' => $target > 0 ? round(min(1, $progress / $target) * 100, 1) : 0.0,
                'earned' => $earned,
                'earned_at' => $earned ? ($row['first_earned_at'] ?? null) : null,
                'first_earned_at' => $row['first_earned_at'],
                'reason' => (string)$row['reason'],
            ];
        }

        $metrics = self::metrics($pdo, $owner);
        $processing = $metrics['input_watermark'] !== $watermark;
        if ($processing) self::enqueueIfEnabled($pdo, $owner, 'scheduled');

        $collections = [];
        foreach (self::COLLECTIONS as $id => $definition) {
            $ids = [];
            foreach ($stored as $item) {
                foreach ($definition['prefixes'] as $prefix) {
                    if (str_starts_with($item['id'], $prefix)) { $ids[] = $item['id']; break; }
                }
            }
            $earned = 0;
            foreach ($stored as $item) if (in_array($item['id'], $ids, true) && $item['earned']) $earned++;
            $collections[] = [
                'id' => $id,
                'title_key' => $definition['title_key'],
                'achievement_ids' => $ids,
                'earned' => $earned,
                'total' => count($ids),
                'complete' => $ids !== [] && $earned === count($ids),
            ];
        }

        $earnedAchievements = array_values(array_filter($stored, static fn(array $item): bool => $item['earned'] === true));
        usort($earnedAchievements, static function (array $a, array $b): int {
            return strcmp((string)($b['earned_at'] ?? ''), (string)($a['earned_at'] ?? ''));
        });

        return $base + [
            'enabled' => true,
            'frozen' => false,
            'achievements' => $stored,
            'earned_achievements' => $earnedAchievements,
            'collections' => $collections,
            'earned_count' => $earnedCount,
            'total_count' => count($stored),
            'input_watermark' => $watermark,
            'recalculated_at' => $recalculatedAt,
            'processing' => $processing,
            'highlights' => self::highlights($metrics),
        ];
    }

    /**
     * The bridge between both halves: a compact owner-only snapshot the UI can
     * show next to the achievement toggle (no coordinates, no public links).
     *
     * @param array<string,mixed> $metrics
     * @return array<string,int|float>
     */
    public static function highlights(array $metrics): array
    {
        return [
            'steps_total' => (int)($metrics['steps_total'] ?? 0),
            'steps_goal' => (int)($metrics['steps_goal'] ?? 0),
            'steps_goal_days' => (int)($metrics['steps_goal_days'] ?? 0),
            'steps_streak' => (int)($metrics['steps_streak'] ?? 0),
            'steps_trail_days' => (int)($metrics['steps_trail_days'] ?? 0),
            'steps_distance_m' => (float)($metrics['steps_distance_m'] ?? 0),
            'route_distance_m' => (float)($metrics['distance_m'] ?? 0),
        ];
    }

    /** @return array<string,int|float> */
    private static function zeroHighlights(): array
    {
        return self::highlights([]);
    }
}
