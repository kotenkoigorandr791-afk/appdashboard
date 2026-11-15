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
    public const RULES_VERSION = 'achievements-1';
    public const DEFAULT_TIMEZONE = 'UTC';
    /** Achievements never leave the owner's account. */
    public const PUBLIC = false;
    public const REASONS = ['import', 'edit', 'delete', 'manual', 'scheduled', 'preference', 'rules'];

    /** Themed sets; a collection groups related achievement ids by prefix. */
    public const COLLECTIONS = [
        'explorer' => ['title_key' => 'achievement_collection_explorer', 'prefixes' => ['countries_', 'cities_']],
        'pathfinder' => ['title_key' => 'achievement_collection_pathfinder', 'prefixes' => ['regions_', 'distance_']],
        'local' => ['title_key' => 'achievement_collection_local', 'prefixes' => ['places_', 'coverage_']],
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
        ];
    }
}
