<?php
/**
 * Location statistics (task 18).
 *
 * Everything is derived from canonical points, confirmed trips/stops/visits and
 * places. No metric depends on an inferred transport mode: speed and bearing
 * stay descriptive and are never grouped into categories. Days are assigned to
 * an explicit IANA timezone supplied by the caller (UTC by default), and
 * country/city names come only from an explicitly versioned boundary dataset
 * imported into PostgreSQL. When no dataset is present the response says so
 * instead of calling a live geocoder.
 */
require_once __DIR__ . '/geolocation_quality.php';
require_once __DIR__ . '/geolocation_timeline.php';

final class StepsGeolocationStatistics
{
    public const PROCESSOR_VERSION = 'stats-2';
    public const RESIDENCY_WINDOW_DAYS = 183;
    public const RESIDENCY_THRESHOLD_DAYS = 183;
    public const ACCURACY_BUCKETS = [10, 25, 50, 100, 200];

    public static function timezone(string $timezone): string
    {
        return StepsGeolocationTimeline::timezone($timezone);
    }

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    /** Latest imported boundary dataset for a kind, or null. */
    public static function boundaryDataset(PDO $pdo, string $kind): ?array
    {
        $stmt = $pdo->prepare("SELECT version, kind, source, feature_count, imported_at FROM geo_boundary_datasets WHERE kind = :kind ORDER BY imported_at DESC, version DESC LIMIT 1");
        $stmt->execute([':kind' => $kind]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['feature_count'] = (int)$row['feature_count'];
        return $row;
    }

    /**
     * Version and file of the country boundaries shipped inside the image.
     * Statistics never call a live geocoder: naming a country only works from
     * an explicitly versioned dataset, so we bundle one and import it lazily.
     */
    public const BUNDLED_COUNTRY_BOUNDARIES = [
        // 1:10m borders keep a crossing near a 1-2 km tolerance, while the
        // former 1:110m file could put a village on the wrong side of a
        // border by kilometres. The file is gzipped to keep the image small.
        'version' => 'natural-earth-10m-5.1.2',
        'file' => 'ne_10m_admin_0_countries.geojson.gz',
        'code_property' => 'ISO_A2',
        'name_property' => 'NAME',
    ];

    /** Earlier bundled datasets that are upgraded automatically. */
    public const LEGACY_BUNDLED_COUNTRY_VERSIONS = ['natural-earth-5.1.2'];

    /**
     * Stream the features of a GeoJSON FeatureCollection without decoding the
     * whole file. A 1:10m country file is ~13 MB and would otherwise exceed the
     * default PHP memory limit of a request. Gzipped input is decompressed
     * in memory (a few MB) and parsed feature by feature.
     *
     * @return \Generator<int,array<string,mixed>>
     */
    public static function readGeoJsonFeatures(string $path): \Generator
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') return;
        if (str_ends_with(strtolower($path), '.gz')) {
            $decoded = @gzdecode($raw);
            if ($decoded === false || $decoded === '') return;
            $raw = $decoded;
            unset($decoded);
        }
        $start = strpos($raw, '"features"');
        if ($start === false) return;
        $cursor = strpos($raw, '[', $start);
        if ($cursor === false) return;
        $length = strlen($raw);
        $cursor++;
        while ($cursor < $length) {
            $char = $raw[$cursor];
            if ($char === ']') return;
            if ($char !== '{') { $cursor++; continue; }
            $depth = 0;
            $inString = false;
            $escaped = false;
            $end = null;
            for ($i = $cursor; $i < $length; $i++) {
                $c = $raw[$i];
                if ($inString) {
                    if ($escaped) { $escaped = false; continue; }
                    if ($c === '\\') { $escaped = true; continue; }
                    if ($c === '"') $inString = false;
                    continue;
                }
                if ($c === '"') { $inString = true; continue; }
                if ($c === '{' || $c === '[') { $depth++; continue; }
                if ($c === '}' || $c === ']') {
                    $depth--;
                    if ($depth === 0) { $end = $i; break; }
                }
            }
            if ($end === null) return;
            $feature = json_decode(substr($raw, $cursor, $end - $cursor + 1), true);
            if (is_array($feature)) yield $feature;
            $cursor = $end + 1;
        }
    }

    /**
     * Import the bundled country boundaries once, if no dataset exists yet.
     * Safe to call on every refresh: after the first run it is a single
     * indexed lookup. Failures are logged and never break statistics.
     */
    public static function ensureBundledBoundaries(PDO $pdo): ?array
    {
        $existing = self::boundaryDataset($pdo, 'country');
        if ($existing !== null && !in_array((string)$existing['version'], self::LEGACY_BUNDLED_COUNTRY_VERSIONS, true)) {
            return $existing;
        }

        $file = self::BUNDLED_COUNTRY_BOUNDARIES['file'];
        $path = dirname(__DIR__) . '/resources/boundaries/' . $file;
        if (!is_file($path)) {
            error_log('boundaries: bundled dataset missing at ' . $path);
            return $existing;
        }

        // A render/refresh may already be inside a transaction; importing then
        // would break the caller's unit of work, so defer to the next refresh.
        if ($pdo->inTransaction()) return $existing;

        $version = self::BUNDLED_COUNTRY_BOUNDARIES['version'];
        $kind = 'country';
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO geo_boundary_datasets(version, kind, source, feature_count, imported_at)
                 VALUES(:version, :kind, :source, :count, now())
                 ON CONFLICT (version, kind) DO UPDATE SET source = EXCLUDED.source, imported_at = now()"
            )->execute([':version' => $version, ':kind' => $kind, ':source' => $file, ':count' => 0]);

            $pdo->prepare('DELETE FROM geo_boundary_areas WHERE dataset_version = :version AND kind = :kind')
                ->execute([':version' => $version, ':kind' => $kind]);

            $insert = $pdo->prepare(
                "INSERT INTO geo_boundary_areas(dataset_version, kind, code, name, parent_code, geometry)
                 VALUES(:version, :kind, :code, :name, '', ST_SetSRID(ST_GeomFromGeoJSON(:geometry), 4326))
                 ON CONFLICT (dataset_version, kind, code) DO UPDATE SET name = EXCLUDED.name, geometry = EXCLUDED.geometry"
            );
            $imported = 0;
            $codeProperty = self::BUNDLED_COUNTRY_BOUNDARIES['code_property'];
            $nameProperty = self::BUNDLED_COUNTRY_BOUNDARIES['name_property'];
            foreach (self::readGeoJsonFeatures($path) as $feature) {
                $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
                $code = trim((string)($props[$codeProperty] ?? $props['code'] ?? $props['id'] ?? ''));
                $name = trim((string)($props[$nameProperty] ?? $props['name'] ?? ''));
                $geometry = $feature['geometry'] ?? null;
                if ($code === '' || $name === '' || !is_array($geometry)) continue;
                if (strlen($code) > 40) $code = substr($code, 0, 40);
                try {
                    $insert->execute([
                        ':version' => $version, ':kind' => $kind, ':code' => $code,
                        ':name' => $name, ':geometry' => json_encode($geometry),
                    ]);
                    $imported++;
                } catch (Throwable $e) {
                    error_log('boundaries: feature skipped: ' . $e->getMessage());
                }
            }
            if ($imported === 0) {
                $pdo->rollBack();
                error_log('boundaries: bundled dataset has no usable features');
                return $existing;
            }
            $pdo->prepare('UPDATE geo_boundary_datasets SET feature_count = :count WHERE version = :version AND kind = :kind')
                ->execute([':count' => $imported, ':version' => $version, ':kind' => $kind]);
            foreach (self::LEGACY_BUNDLED_COUNTRY_VERSIONS as $legacy) {
                if ($legacy === $version) continue;
                $pdo->prepare('DELETE FROM geo_boundary_areas WHERE dataset_version = :version AND kind = :kind')
                    ->execute([':version' => $legacy, ':kind' => $kind]);
                $pdo->prepare('DELETE FROM geo_boundary_datasets WHERE version = :version AND kind = :kind')
                    ->execute([':version' => $legacy, ':kind' => $kind]);
            }
            $pdo->commit();
            error_log('boundaries: imported ' . $imported . ' bundled country feature(s) as ' . $version);
            // The new dataset changes every owner's country results.
            try {
                $pdo->exec('DELETE FROM geo_statistics_cache');
                $owners = $pdo->query('SELECT DISTINCT user_email FROM geo_location_points')->fetchAll(PDO::FETCH_COLUMN);
                foreach ($owners as $owner) self::enqueue($pdo, (string)$owner);
            } catch (Throwable $e) {
                error_log('boundaries: refresh scheduling failed: ' . $e->getMessage());
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('boundaries: bundled import failed: ' . $e->getMessage());
            return $existing;
        }

        return self::boundaryDataset($pdo, 'country');
    }

    /**
     * @param list<array{source_at:string,latitude:float,longitude:float,quality_state?:string,accuracy_m?:mixed}> $points
     * @param list<array{code:string,name:string,count:int}> $countries
     * @return array<string,array<string,mixed>>
     */
    public static function groupDaily(array $points, string $timezone, array $countries = [], array $cities = []): array
    {
        $tz = new DateTimeZone(self::timezone($timezone));
        $thresholds = StepsGeolocationQuality::thresholds();
        $days = [];
        $previous = null;
        foreach ($points as $point) {
            $at = strtotime((string)($point['source_at'] ?? ''));
            if ($at === false) continue;
            $day = (new DateTimeImmutable('@' . $at))->setTimezone($tz)->format('Y-m-d');
            if (!isset($days[$day])) {
                $days[$day] = [
                    'day' => $day, 'point_count' => 0, 'accepted_count' => 0, 'flagged_count' => 0, 'rejected_count' => 0, 'excluded_count' => 0, 'private_redacted_count' => 0,
                    'distance_m' => 0.0, 'active_seconds' => 0, 'first_at' => null, 'last_at' => null,
                    'accuracy_sum' => 0.0, 'accuracy_count' => 0, 'accuracy' => array_fill(0, count(self::ACCURACY_BUCKETS) + 2, 0),
                    'sources' => [],
                ];
            }
            $rawState = (string)($point['quality_state'] ?? 'accepted');
            $excluded = in_array($rawState, StepsGeolocationQuality::EXCLUDED_STATES, true) || $rawState === StepsGeolocationQuality::STATE_NOT_ENOUGH_EVIDENCE;
            $state = in_array($rawState, ['accepted', 'flagged', 'rejected'], true) ? $rawState : ($excluded ? 'excluded' : 'accepted');
            $days[$day]['point_count']++;
            $days[$day][$state . '_count']++;
            $source = (string)($point['source_type'] ?? 'unknown');
            $days[$day]['sources'][$source] = ($days[$day]['sources'][$source] ?? 0) + 1;
            $accuracy = isset($point['accuracy_m']) && is_numeric($point['accuracy_m']) ? (float)$point['accuracy_m'] : null;
            if ($accuracy !== null) {
                $days[$day]['accuracy_sum'] += $accuracy;
                $days[$day]['accuracy_count']++;
                $bucket = count(self::ACCURACY_BUCKETS);
                foreach (self::ACCURACY_BUCKETS as $index => $limit) {
                    if ($accuracy <= $limit) { $bucket = $index; break; }
                }
                $days[$day]['accuracy'][$bucket] = ($days[$day]['accuracy'][$bucket] ?? 0) + 1;
            } else {
                $days[$day]['accuracy'][count(self::ACCURACY_BUCKETS) + 1]++;
            }
            $atIso = gmdate('c', $at);
            if ($days[$day]['first_at'] === null || strcmp($atIso, (string)$days[$day]['first_at']) < 0) $days[$day]['first_at'] = $atIso;
            if ($days[$day]['last_at'] === null || strcmp($atIso, (string)$days[$day]['last_at']) > 0) $days[$day]['last_at'] = $atIso;

            if ($previous !== null && !$excluded && !in_array((string)($previous['quality_state'] ?? 'accepted'), StepsGeolocationQuality::EXCLUDED_STATES, true)) {
                $prevAt = strtotime((string)$previous['source_at']);
                if ($prevAt !== false) {
                    $gap = $at - $prevAt;
                    if ($gap > 0 && $gap <= 21600) $days[$day]['active_seconds'] += $gap;
                    $prevDay = (new DateTimeImmutable('@' . $prevAt))->setTimezone($tz)->format('Y-m-d');
                    // Only continuous evidence contributes distance. A GPS teleport
                    // (app restart, edited sample, another city minutes later) used
                    // to add hundreds of kilometres to a single day, so a segment
                    // must fit the configured gap and maximum speed. Unusual
                    // samples are still counted, just not stitched into the route.
                    if ($prevDay === $day && $gap > 0 && $gap <= $thresholds['max_segment_gap_s']) {
                        $segment = StepsGeolocationQuality::haversineMeters(
                            (float)$previous['latitude'], (float)$previous['longitude'],
                            (float)$point['latitude'], (float)$point['longitude']
                        );
                        if ($segment <= $thresholds['max_jump_m'] || ($segment / $gap) <= $thresholds['max_speed_mps']) {
                            $days[$day]['distance_m'] += $segment;
                        }
                    }
                }
            }
            if ($state !== 'rejected') $previous = $point;
        }

        $countryByDay = [];
        $countryListByDay = [];
        foreach ($countries as $row) {
            $day = (string)$row['day'];
            if (!isset($countryByDay[$day]) || (int)$row['count'] > (int)$countryByDay[$day]['count']) $countryByDay[$day] = $row;
            // A day can touch more than one country (a border crossing, a short
            // visit). Keep every country so metrics never drop a whole country
            // just because another one dominated that day.
            $countryListByDay[$day][] = ['code' => (string)$row['code'], 'name' => (string)$row['name'], 'points' => (int)$row['count']];
        }
        foreach ($countryListByDay as &$list) {
            usort($list, static fn(array $a, array $b): int => $b['points'] <=> $a['points']);
        }
        unset($list);
        $cityByDay = [];
        foreach ($cities as $row) {
            $day = (string)$row['day'];
            if (!isset($cityByDay[$day]) || (int)$row['count'] > (int)$cityByDay[$day]['count']) $cityByDay[$day] = $row;
        }
        foreach ($days as &$day) {
            $day['country_code'] = $countryByDay[$day['day']]['code'] ?? null;
            $day['country_name'] = $countryByDay[$day['day']]['name'] ?? null;
            $day['countries'] = $countryListByDay[$day['day']] ?? [];
            $day['city_name'] = $cityByDay[$day['day']]['name'] ?? null;
            $day['accuracy_avg'] = $day['accuracy_count'] > 0 ? round($day['accuracy_sum'] / $day['accuracy_count'], 2) : null;
            unset($day['accuracy_sum'], $day['accuracy_count']);
            $day['distance_m'] = round($day['distance_m'], 2);
            $day['active_seconds'] = (int)$day['active_seconds'];
            ksort($day['sources']);
        }
        unset($day);
        ksort($days);
        return $days;
    }

    /**
     * Consecutive stay sequence per country, split by a missing calendar day.
     * @param list<array{day:string,country_code:?string,country_name:?string}> $daily
     * @return list<array<string,mixed>>
     */
    public static function countrySequences(array $daily): array
    {
        usort($daily, static fn(array $a, array $b): int => strcmp((string)$a['day'], (string)$b['day']));
        $sequences = [];
        $current = null;
        $previousDay = null;
        foreach ($daily as $row) {
            $code = (string)($row['country_code'] ?? '');
            if ($code === '') { $current = null; $previousDay = (string)$row['day']; continue; }
            $day = (string)$row['day'];
            $continues = $current !== null && $current['country_code'] === $code
                && $previousDay !== null && (strtotime($day) - strtotime($previousDay)) === 86400;
            if ($continues) {
                $current['end'] = $day;
                $current['days']++;
            } else {
                if ($current !== null) $sequences[] = $current;
                $current = ['country_code' => $code, 'country_name' => (string)($row['country_name'] ?? ''), 'start' => $day, 'end' => $day, 'days' => 1];
            }
            $previousDay = $day;
        }
        if ($current !== null) $sequences[] = $current;
        return $sequences;
    }

    /**
     * Rolling-window residency warning. Days are counted per country inside a
     * window of `window_days`; a country at or above `threshold_days` produces
     * a warning. Both defaults are 183 so the result states the tax-residency
     * style assumption explicitly.
     * @param list<array{day:string,country_code:?string,country_name:?string}> $daily
     * @return list<array<string,mixed>>
     */
    public static function residencyWarnings(array $daily, int $windowDays = self::RESIDENCY_WINDOW_DAYS, int $thresholdDays = self::RESIDENCY_THRESHOLD_DAYS): array
    {
        $byCountry = [];
        foreach ($daily as $row) {
            $code = (string)($row['country_code'] ?? '');
            if ($code === '') continue;
            $byCountry[$code][] = (string)$row['day'];
        }
        $warnings = [];
        foreach ($byCountry as $code => $days) {
            sort($days);
            $count = count($days);
            $left = 0;
            $best = null;
            for ($right = 0; $right < $count; $right++) {
                while (strtotime($days[$right]) - strtotime($days[$left]) >= $windowDays * 86400) $left++;
                $inWindow = $right - $left + 1;
                if ($inWindow >= $thresholdDays && ($best === null || $inWindow > $best['days'])) {
                    $best = [
                        'country_code' => $code,
                        'country_name' => '',
                        'days' => $inWindow,
                        'window_from' => $days[$left],
                        'window_to' => $days[$right],
                        'window_days' => $windowDays,
                        'threshold_days' => $thresholdDays,
                    ];
                }
            }
            if ($best !== null) $warnings[] = $best;
        }
        return $warnings;
    }

    public static function accuracyBuckets(): array
    {
        return ['<=10' => 0, '<=25' => 1, '<=50' => 2, '<=100' => 3, '<=200' => 4, '>200' => 5, 'missing' => 6];
    }

    /** Empty daily row in the database shape. */
    private static function dailyInsert(PDO $pdo, string $owner, string $timezone, array $day, string $boundaryVersion): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO geo_statistics_daily(user_email, day, timezone, processor_version, boundary_version, point_count, accepted_count, flagged_count, rejected_count, distance_m, active_seconds, first_at, last_at, accuracy_avg, accuracy_json, sources_json, country_code, country_name, city_name, countries_json, updated_at)
             VALUES(:owner, :day, :tz, :version, :boundary, :points, :accepted, :flagged, :rejected, :distance, :active, :first, :last, :accuracy_avg, :accuracy, :sources, :country_code, :country_name, :city, :countries, now())
             ON CONFLICT (user_email, day, timezone) DO UPDATE SET processor_version = EXCLUDED.processor_version, boundary_version = EXCLUDED.boundary_version, point_count = EXCLUDED.point_count, accepted_count = EXCLUDED.accepted_count, flagged_count = EXCLUDED.flagged_count, rejected_count = EXCLUDED.rejected_count, distance_m = EXCLUDED.distance_m, active_seconds = EXCLUDED.active_seconds, first_at = EXCLUDED.first_at, last_at = EXCLUDED.last_at, accuracy_avg = EXCLUDED.accuracy_avg, accuracy_json = EXCLUDED.accuracy_json, sources_json = EXCLUDED.sources_json, country_code = EXCLUDED.country_code, country_name = EXCLUDED.country_name, city_name = EXCLUDED.city_name, countries_json = EXCLUDED.countries_json, updated_at = now()"
        );
        $stmt->execute([
            ':owner' => $owner, ':day' => $day['day'], ':tz' => $timezone, ':version' => self::PROCESSOR_VERSION,
            ':boundary' => $boundaryVersion,
            ':points' => $day['point_count'], ':accepted' => $day['accepted_count'], ':flagged' => $day['flagged_count'], ':rejected' => $day['rejected_count'],
            ':distance' => $day['distance_m'], ':active' => $day['active_seconds'], ':first' => $day['first_at'], ':last' => $day['last_at'],
            ':accuracy_avg' => $day['accuracy_avg'],
            ':accuracy' => json_encode($day['accuracy'], JSON_UNESCAPED_SLASHES),
            ':sources' => json_encode($day['sources'], JSON_UNESCAPED_SLASHES),
            ':country_code' => $day['country_code'], ':country_name' => $day['country_name'], ':city' => $day['city_name'],
            ':countries' => json_encode($day['countries'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @return list<array{day:string,code:string,name:string,count:int}> */
    private static function resolveAreas(PDO $pdo, string $kind, string $version, string $owner, string $timezone, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            "WITH pts AS MATERIALIZED (
                SELECT (p.source_at AT TIME ZONE :tz)::date::text AS day, p.position,
                       ST_X(ST_SnapToGrid(p.position, 0.01)) AS cx, ST_Y(ST_SnapToGrid(p.position, 0.01)) AS cy
                  FROM geo_location_points p
                 WHERE p.user_email = :owner AND p.quality_state NOT IN ('rejected','excluded_by_user','private_redacted')
                   AND (p.source_at AT TIME ZONE :tz2)::date BETWEEN :from AND :to
             ), cells AS (SELECT DISTINCT cx, cy FROM pts),
             whole AS MATERIALIZED (
                SELECT c.cx, c.cy, a.code, a.name
                  FROM cells c
                  JOIN geo_boundary_areas a ON a.dataset_version = :version AND a.kind = :kind
                   AND ST_Covers(a.geometry, ST_MakeEnvelope(c.cx - 0.006, c.cy - 0.006, c.cx + 0.006, c.cy + 0.006, 4326))
             )
             SELECT day, code, name, COUNT(*) AS count FROM (
                SELECT p.day, w.code, w.name FROM pts p JOIN whole w ON w.cx = p.cx AND w.cy = p.cy
                UNION ALL
                SELECT p.day, a.code, a.name FROM (
                    SELECT * FROM pts q WHERE NOT EXISTS (SELECT 1 FROM whole w WHERE w.cx = q.cx AND w.cy = q.cy)
                ) p
                  JOIN geo_boundary_areas a ON a.dataset_version = :version2 AND a.kind = :kind2 AND ST_Covers(a.geometry, p.position)
             ) r GROUP BY day, code, name"
        );
        $stmt->execute([':tz' => $timezone, ':tz2' => $timezone, ':owner' => $owner, ':from' => $from, ':to' => $to, ':version' => $version, ':kind' => $kind, ':version2' => $version, ':kind2' => $kind]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Recompute daily rows for a local-date range, or the full history. */
    public static function refresh(PDO $pdo, string $owner, string $timezone = 'UTC', ?string $from = null, ?string $to = null): int
    {
        $timezone = self::timezone($timezone);
        $countryDataset = self::ensureBundledBoundaries($pdo);
        $cityDataset = self::boundaryDataset($pdo, 'city');
        $boundaryVersion = $countryDataset['version'] ?? '';
        $fullRefresh = $from === null || $to === null;

        if ($fullRefresh) {
            $bounds = $pdo->prepare("SELECT MIN((source_at AT TIME ZONE :tz)::date) AS from_day, MAX((source_at AT TIME ZONE :tz)::date) AS to_day FROM geo_location_points WHERE user_email = :owner");
            $bounds->execute([':tz' => $timezone, ':owner' => $owner]);
            $boundsRow = $bounds->fetch(PDO::FETCH_ASSOC) ?: [];
            $from = (string)($boundsRow['from_day'] ?? '');
            $to = (string)($boundsRow['to_day'] ?? '');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$to) || strcmp($from, $to) > 0) {
            if ($fullRefresh) {
                $pdo->prepare('DELETE FROM geo_statistics_daily WHERE user_email = :owner AND timezone = :tz')
                    ->execute([':owner' => $owner, ':tz' => $timezone]);
            }
            $pdo->prepare('DELETE FROM geo_statistics_cache WHERE user_email = :owner AND timezone = :tz')->execute([':owner' => $owner, ':tz' => $timezone]);
            return 0;
        }

        $stmt = $pdo->prepare(
            "SELECT source_at, source_type, quality_state, accuracy_m,
                    ST_Y(position) AS latitude, ST_X(position) AS longitude
               FROM geo_location_points
              WHERE user_email = :owner
                AND (source_at AT TIME ZONE :tz)::date BETWEEN :from AND :to
           ORDER BY source_at ASC, id ASC"
        );
        $stmt->execute([':owner' => $owner, ':tz' => $timezone, ':from' => $from, ':to' => $to]);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countries = [];
        if ($countryDataset !== null) {
            $countries = self::resolveAreas($pdo, 'country', (string)$countryDataset['version'], $owner, $timezone, $from, $to);
        }
        $cities = [];
        if ($cityDataset !== null) {
            $cities = self::resolveAreas($pdo, 'city', (string)$cityDataset['version'], $owner, $timezone, $from, $to);
        }

        $daily = self::groupDaily($points, $timezone, $countries, $cities);
        $pdo->beginTransaction();
        try {
            if ($fullRefresh) {
                $pdo->prepare('DELETE FROM geo_statistics_daily WHERE user_email = :owner AND timezone = :tz')
                    ->execute([':owner' => $owner, ':tz' => $timezone]);
            } else {
                $pdo->prepare('DELETE FROM geo_statistics_daily WHERE user_email = :owner AND timezone = :tz AND day BETWEEN :from AND :to')
                    ->execute([':owner' => $owner, ':tz' => $timezone, ':from' => $from, ':to' => $to]);
            }
            foreach ($daily as $day) self::dailyInsert($pdo, $owner, $timezone, $day, $boundaryVersion);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        // A changed daily set invalidates the owner+version cache entry.
        $pdo->prepare('DELETE FROM geo_statistics_cache WHERE user_email = :owner AND timezone = :tz')->execute([':owner' => $owner, ':tz' => $timezone]);
        return count($daily);
    }

    /** Daily rows in a date range, oldest first. */
    public static function dailyRows(PDO $pdo, string $owner, string $timezone, ?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT * FROM geo_statistics_daily WHERE user_email = :owner AND timezone = :tz';
        $params = [':owner' => $owner, ':tz' => self::timezone($timezone)];
        if ($from !== null && $to !== null) {
            $sql .= ' AND day BETWEEN :from AND :to';
            $params[':from'] = $from;
            $params[':to'] = $to;
        }
        $sql .= ' ORDER BY day ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['point_count'] = (int)$row['point_count'];
            $row['accepted_count'] = (int)$row['accepted_count'];
            $row['flagged_count'] = (int)$row['flagged_count'];
            $row['rejected_count'] = (int)$row['rejected_count'];
            $row['distance_m'] = (float)$row['distance_m'];
            $row['active_seconds'] = (int)$row['active_seconds'];
            $row['accuracy_json'] = json_decode((string)$row['accuracy_json'], true) ?: [];
            $row['sources_json'] = json_decode((string)$row['sources_json'], true) ?: [];
            $row['countries_json'] = json_decode((string)($row['countries_json'] ?? '[]'), true) ?: [];
            $rows[] = $row;
        }
        return $rows;
    }

    /** Aggregated statistics for one explicit range. */
    public static function summary(PDO $pdo, string $owner, string $timezone = 'UTC', ?string $from = null, ?string $to = null): array
    {
        $timezone = self::timezone($timezone);
        $allDaily = self::dailyRows($pdo, $owner, $timezone);
        if ($allDaily === []) self::refresh($pdo, $owner, $timezone);

        $all = self::dailyRows($pdo, $owner, $timezone);
        $rangeFrom = $from;
        $rangeTo = $to;
        if (($rangeFrom === null || $rangeTo === null) && $all !== []) {
            $rangeFrom = (string)$all[0]['day'];
            $rangeTo = (string)$all[count($all) - 1]['day'];
        }
        if ($rangeFrom === null || $rangeTo === null) {
            $rangeFrom = $rangeTo = gmdate('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeTo) || strcmp($rangeFrom, $rangeTo) > 0) {
            throw new InvalidArgumentException('invalid_range');
        }
        $daily = array_values(array_filter($all, static fn(array $row): bool => strcmp((string)$row['day'], $rangeFrom) >= 0 && strcmp((string)$row['day'], $rangeTo) <= 0));

        $metrics = self::aggregate($daily);
        $metrics['visits'] = 0;
        $metrics['dwell_seconds'] = 0;
        $metrics['places'] = 0;
        $visitStmt = $pdo->prepare(
            "SELECT COUNT(*) AS visits, COUNT(DISTINCT v.place_id) AS places,
                    COALESCE(SUM(EXTRACT(EPOCH FROM (COALESCE(v.departed_at, NOW()) - v.arrived_at))), 0) AS dwell_seconds
               FROM geo_visits v
              WHERE v.user_email = :owner
                AND (v.arrived_at AT TIME ZONE :tz)::date BETWEEN :from AND :to"
        );
        $visitStmt->execute([':owner' => $owner, ':tz' => $timezone, ':from' => $rangeFrom, ':to' => $rangeTo]);
        $visit = $visitStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $metrics['visits'] = (int)($visit['visits'] ?? 0);
        $metrics['places'] = (int)($visit['places'] ?? 0);
        $metrics['dwell_seconds'] = (int)round((float)($visit['dwell_seconds'] ?? 0));

        $topStmt = $pdo->prepare(
            "SELECT v.place_id::text AS place_id, p.name, COUNT(*) AS visits,
                    COALESCE(SUM(EXTRACT(EPOCH FROM (COALESCE(v.departed_at, NOW()) - v.arrived_at))), 0) AS dwell_seconds
               FROM geo_visits v JOIN geo_places p ON p.id = v.place_id
              WHERE v.user_email = :owner AND p.deleted_at IS NULL
                AND (v.arrived_at AT TIME ZONE :tz)::date BETWEEN :from AND :to
           GROUP BY v.place_id, p.name ORDER BY visits DESC, dwell_seconds DESC LIMIT 10"
        );
        $topStmt->execute([':owner' => $owner, ':tz' => $timezone, ':from' => $rangeFrom, ':to' => $rangeTo]);
        $mostVisited = [];
        foreach ($topStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['visits'] = (int)$row['visits'];
            $row['dwell_seconds'] = (int)round((float)$row['dwell_seconds']);
            $mostVisited[] = $row;
        }

        $tripStmt = $pdo->prepare(
            "SELECT COUNT(*) AS trips, COALESCE(AVG(distance_m), 0) AS avg_distance_m,
                    COALESCE(AVG(GREATEST(0, duration_s)), 0) AS avg_duration_s
               FROM geo_trips
              WHERE user_email = :owner AND deleted_at IS NULL AND state <> 'archived'
                AND (started_at AT TIME ZONE :tz)::date BETWEEN :from AND :to"
        );
        $tripStmt->execute([':owner' => $owner, ':tz' => $timezone, ':from' => $rangeFrom, ':to' => $rangeTo]);
        $trip = $tripStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $weekdayStmt = $pdo->prepare(
            "SELECT EXTRACT(ISODOW FROM (started_at AT TIME ZONE :tz))::int AS weekday, COUNT(*) AS trips, COALESCE(AVG(distance_m), 0) AS avg_distance_m
               FROM geo_trips
              WHERE user_email = :owner AND deleted_at IS NULL AND state <> 'archived'
                AND (started_at AT TIME ZONE :tz)::date BETWEEN :from AND :to
           GROUP BY weekday ORDER BY weekday"
        );
        $weekdayStmt->execute([':owner' => $owner, ':tz' => $timezone, ':from' => $rangeFrom, ':to' => $rangeTo]);
        $byWeekday = [];
        foreach ($weekdayStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byWeekday[(int)$row['weekday']] = ['weekday' => (int)$row['weekday'], 'trips' => (int)$row['trips'], 'avg_distance_m' => round((float)$row['avg_distance_m'], 2)];
        }

        $dayCount = (int)((strtotime($rangeTo) - strtotime($rangeFrom)) / 86400) + 1;
        $comparisonTo = gmdate('Y-m-d', strtotime($rangeFrom) - 86400);
        $comparisonFrom = gmdate('Y-m-d', strtotime($comparisonTo) - ($dayCount - 1) * 86400);
        $previousDaily = array_values(array_filter($all, static fn(array $row): bool => strcmp((string)$row['day'], $comparisonFrom) >= 0 && strcmp((string)$row['day'], $comparisonTo) <= 0));
        $previous = self::aggregate($previousDaily);
        $previousVisit = $pdo->prepare(
            "SELECT COUNT(*) AS visits, COUNT(DISTINCT v.place_id) AS places, COALESCE(SUM(EXTRACT(EPOCH FROM (COALESCE(v.departed_at, NOW()) - v.arrived_at))), 0) AS dwell_seconds
               FROM geo_visits v WHERE v.user_email = :owner AND (v.arrived_at AT TIME ZONE :tz)::date BETWEEN :from AND :to"
        );
        $previousVisit->execute([':owner' => $owner, ':tz' => $timezone, ':from' => $comparisonFrom, ':to' => $comparisonTo]);
        $pv = $previousVisit->fetch(PDO::FETCH_ASSOC) ?: [];
        $previous['visits'] = (int)($pv['visits'] ?? 0);
        $previous['places'] = (int)($pv['places'] ?? 0);
        $previous['dwell_seconds'] = (int)round((float)($pv['dwell_seconds'] ?? 0));

        $monthly = self::periods($daily, 'month');
        $yearly = self::periods($daily, 'year');
        $countryDataset = self::boundaryDataset($pdo, 'country');
        $cityDataset = self::boundaryDataset($pdo, 'city');
        $warnings = self::residencyWarnings($all);
        $countryNames = [];
        foreach ($all as $row) {
            if (!empty($row['country_code'])) $countryNames[(string)$row['country_code']] = (string)$row['country_name'];
        }
        foreach ($warnings as &$warning) $warning['country_name'] = $countryNames[$warning['country_code']] ?? '';
        unset($warning);

        $gaps = [];
        $cursor = strtotime($rangeFrom);
        $end = strtotime($rangeTo);
        $dayMap = [];
        foreach ($daily as $row) $dayMap[(string)$row['day']] = true;
        $missing = 0;
        while ($cursor <= $end) {
            $day = gmdate('Y-m-d', $cursor);
            if (!isset($dayMap[$day])) { $missing++; $gaps[] = $day; }
            $cursor += 86400;
        }

        $latest = $pdo->prepare('SELECT MAX(source_at) FROM geo_location_points WHERE user_email = :owner');
        $latest->execute([':owner' => $owner]);
        $latestPoint = $latest->fetchColumn() ?: null;

        $deviceStmt = $pdo->prepare(
            "SELECT d.id::text AS id, d.name, d.platform, d.status, d.last_seen_at, d.last_upload_at,
                    COUNT(p.id) AS points
               FROM geo_devices d
          LEFT JOIN geo_location_points p ON p.user_email = d.user_email AND p.device_id = d.id
              WHERE d.user_email = :owner AND d.status <> 'deleted'
           GROUP BY d.id, d.name, d.platform, d.status, d.last_seen_at, d.last_upload_at
           ORDER BY d.last_seen_at DESC NULLS LAST"
        );
        $deviceStmt->execute([':owner' => $owner]);
        $devices = $deviceStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($devices as &$device) $device['points'] = (int)$device['points'];
        unset($device);

        $accuracy = ['buckets' => self::accuracyBuckets(), 'counts' => array_fill(0, count(self::ACCURACY_BUCKETS) + 2, 0), 'missing' => 0, 'avg_m' => null];
        $sources = [];
        $accuracySum = 0.0;
        $accuracyCount = 0;
        foreach ($daily as $row) {
            foreach ((array)$row['accuracy_json'] as $index => $count) $accuracy['counts'][(int)$index] = ($accuracy['counts'][(int)$index] ?? 0) + (int)$count;
            foreach ((array)$row['sources_json'] as $source => $count) $sources[$source] = ($sources[$source] ?? 0) + (int)$count;
            if ($row['accuracy_avg'] !== null) { $accuracySum += (float)$row['accuracy_avg'] * max(1, (int)$row['point_count']); $accuracyCount += max(1, (int)$row['point_count']); }
        }
        ksort($sources);
        $accuracy['avg_m'] = $accuracyCount > 0 ? round($accuracySum / $accuracyCount, 2) : null;
        $accuracy['missing'] = (int)($accuracy['counts'][count(self::ACCURACY_BUCKETS) + 1] ?? 0);

        return [
            'processor_version' => self::PROCESSOR_VERSION,
            'timezone' => $timezone,
            'range' => ['from' => $rangeFrom, 'to' => $rangeTo, 'days' => $dayCount],
            'boundary' => [
                'country_dataset' => $countryDataset,
                'city_dataset' => $cityDataset,
                'assumption' => $countryDataset === null
                    ? 'No country boundary dataset is imported; countries and the 183-day residency warning are unavailable.'
                    : 'Countries and cities are resolved against the imported, versioned boundary datasets; no live geocoder is called.',
            ],
            'metrics' => $metrics,
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'comparison' => ['range' => ['from' => $comparisonFrom, 'to' => $comparisonTo], 'metrics' => $previous],
            'country_sequence' => self::countrySequences($all),
            'residency' => ['window_days' => self::RESIDENCY_WINDOW_DAYS, 'threshold_days' => self::RESIDENCY_THRESHOLD_DAYS, 'warnings' => $warnings],
            'most_visited_places' => $mostVisited,
            'movement_patterns' => [
                'trips' => (int)($trip['trips'] ?? 0),
                'avg_distance_m' => round((float)($trip['avg_distance_m'] ?? 0), 2),
                'avg_duration_s' => round((float)($trip['avg_duration_s'] ?? 0), 2),
                'by_weekday' => array_values($byWeekday),
            ],
            'coverage' => [
                'days_total' => $dayCount,
                'days_with_data' => count($daily),
                'coverage_ratio' => $dayCount > 0 ? round(count($daily) / $dayCount, 4) : 0,
                'missing_days' => $missing,
                'gaps' => array_slice($gaps, 0, 50),
            ],
            'accuracy' => $accuracy,
            'source_health' => [
                'sources' => $sources,
                'devices' => $devices,
                'latest_point_at' => $latestPoint,
            ],
            'assumptions' => [
                'Days are grouped in the requested IANA timezone: ' . $timezone . '.',
                'Country and city names come only from explicitly imported boundary dataset versions. A day is summarised by its dominant country. Visited-country metrics count only those dominant daily countries, so GPS teleports or sparse outliers in another country do not appear as visits.',
                'Distance uses consecutive accepted samples within the same local day; cross-midnight segments are counted on the following day only when both samples share that day.',
                'No statistic uses or infers a mode of transport.',
            ],
        ];
    }

    public static function aggregate(array $daily): array
    {
        $metrics = [
            'point_count' => 0, 'accepted_points' => 0, 'flagged_points' => 0, 'rejected_points' => 0,
            'route_distance_m' => 0.0, 'active_days' => 0, 'active_seconds' => 0,
            'countries' => [], 'cities' => [],
        ];
        $countries = [];
        $cities = [];
        foreach ($daily as $row) {
            $metrics['point_count'] += (int)$row['point_count'];
            $metrics['accepted_points'] += (int)$row['accepted_count'];
            $metrics['flagged_points'] += (int)$row['flagged_count'];
            $metrics['rejected_points'] += (int)$row['rejected_count'];
            $metrics['route_distance_m'] += (float)$row['distance_m'];
            $metrics['active_seconds'] += (int)$row['active_seconds'];
            if ((int)$row['point_count'] > 0) $metrics['active_days']++;
            // Visited countries follow the dominant daily country only. Sparse
            // GPS teleports (a handful of points in Peru while the day is in
            // Ukraine) stay in countries_json for diagnostics but must not
            // inflate the visited-country list shown next to the map.
            $code = (string)($row['country_code'] ?? '');
            if ($code !== '') $countries[$code] = (string)($row['country_name'] ?? '');
            if (!empty($row['city_name'])) $cities[(string)$row['city_name']] = true;
        }
        $metrics['route_distance_m'] = round($metrics['route_distance_m'], 2);
        $metrics['countries'] = array_values(array_map(static fn($code, $name): array => ['code' => $code, 'name' => $name], array_keys($countries), array_values($countries)));
        $metrics['cities'] = array_keys($cities);
        return $metrics;
    }

    private static function periods(array $daily, string $kind): array
    {
        $groups = [];
        foreach ($daily as $row) {
            $key = $kind === 'month' ? substr((string)$row['day'], 0, 7) : substr((string)$row['day'], 0, 4);
            if (!isset($groups[$key])) $groups[$key] = [];
            $groups[$key][] = $row;
        }
        $out = [];
        foreach ($groups as $key => $rows) {
            $metrics = self::aggregate($rows);
            $out[] = ['period' => $key, 'metrics' => $metrics];
        }
        return $out;
    }

    /** Durable incremental refresh marker, reusing geo_processor_runs. */
    public static function enqueue(PDO $pdo, string $owner, ?string $from = null, ?string $to = null): bool
    {
        $existing = $pdo->prepare("SELECT id, input_from, input_to FROM geo_processor_runs WHERE user_email = :owner AND kind = 'statistics' AND state IN ('pending','processing') ORDER BY created_at DESC LIMIT 1 FOR UPDATE");
        $pdo->beginTransaction();
        try {
            $existing->execute([':owner' => $owner]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $stmt = $pdo->prepare("UPDATE geo_processor_runs SET state = 'pending', input_from = LEAST(COALESCE(input_from, :from1), :from2), input_to = GREATEST(COALESCE(input_to, :to1), :to2), updated_at = now() WHERE id = :id");
                $stmt->execute([':from1' => $from, ':from2' => $from, ':to1' => $to, ':to2' => $to, ':id' => $row['id']]);
                $pdo->commit();
                return false;
            }
            $stmt = $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, input_from, input_to) VALUES(:id, :owner, 'statistics', 'pending', :version, :from, :to)");
            $stmt->execute([':id' => self::uuid(), ':owner' => $owner, ':version' => self::PROCESSOR_VERSION, ':from' => $from, ':to' => $to]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Drop cached output and queue a bounded recomputation. */
    public static function invalidate(PDO $pdo, string $owner, ?string $from = null, ?string $to = null): void
    {
        try {
            $pdo->prepare('DELETE FROM geo_statistics_cache WHERE user_email = :owner')->execute([':owner' => $owner]);
            self::enqueue($pdo, $owner, $from, $to);
        } catch (Throwable $e) {
            error_log('geolocation statistics invalidation: ' . $e->getMessage());
        }
        try {
            if (!class_exists('StepsGeolocationAnalytics')) {
                $analyticsFile = __DIR__ . '/geolocation_analytics.php';
                if (is_file($analyticsFile)) require_once $analyticsFile;
            }
            if (class_exists('StepsGeolocationAnalytics')) StepsGeolocationAnalytics::invalidate($pdo, $owner);
        } catch (Throwable $e) {
            error_log('analytics invalidation: ' . $e->getMessage());
        }
        // Imports, edits and deletions all end here, so achievements are queued
        // from the same canonical invalidation point. The evaluator skips owners
        // who disabled the module, and never deletes canonical history.
        try {
            if (!class_exists('StepsGeolocationAchievements')) {
                $achievementsFile = __DIR__ . '/geolocation_achievements.php';
                if (is_file($achievementsFile)) require_once $achievementsFile;
            }
            if (class_exists('StepsGeolocationAchievements')) {
                StepsGeolocationAchievements::enqueueIfEnabled($pdo, $owner, 'scheduled');
            }
        } catch (Throwable $e) {
            error_log('achievements invalidation: ' . $e->getMessage());
        }
    }
}
