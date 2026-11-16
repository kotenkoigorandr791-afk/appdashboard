<?php
/**
 * Privacy-aware mobility analytics (task 35).
 *
 * Aggregates only bounded, owner-authorized ranges. Metrics explain coverage,
 * quality and uncertainty and never infer a transport mode. Results are cached
 * by formula/range/timezone and can be recomputed through existing jobs.
 */
require_once __DIR__ . '/geolocation_statistics.php';
require_once __DIR__ . '/geolocation_quality.php';
require_once __DIR__ . '/geolocation_timeline.php';

final class StepsGeolocationAnalytics
{
    public const VERSION = 'analytics-1';
    public const MAX_DAYS = 366;
    public const CACHE_TTL_S = 300;

    public static function resolveRange(?string $from, ?string $to, string $timezone = 'UTC'): array
    {
        $timezone = StepsGeolocationTimeline::timezone($timezone);
        $zone = new DateTimeZone($timezone);
        $now = new DateTimeImmutable('now', $zone);
        $from = trim((string)$from);
        $to = trim((string)$to);
        if ($from === '' || $to === '') {
            $start = $now->modify('-29 days')->setTime(0, 0);
            $end = $now->setTime(23, 59, 59);
        } else {
            $start = (new DateTimeImmutable($from, $zone))->setTime(0, 0);
            $end = (new DateTimeImmutable($to, $zone))->setTime(23, 59, 59);
        }
        if ($end < $start) throw new InvalidArgumentException('invalid_range');
        if ($end->getTimestamp() - $start->getTimestamp() > self::MAX_DAYS * 86400) throw new InvalidArgumentException('range_too_large');
        return [
            'from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d'),
            'from_at' => $start->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'to_at' => $end->setTimezone(new DateTimeZone('UTC'))->format('c'),
            'label' => $start->format('Y-m-d') . ' – ' . $end->format('Y-m-d'),
            'timezone' => $timezone, 'days' => (int)(($end->getTimestamp() - $start->getTimestamp()) / 86400) + 1,
        ];
    }

    public static function summary(PDO $pdo, string $owner, array $opts = []): array
    {
        $timezone = StepsGeolocationTimeline::timezone((string)($opts['timezone'] ?? 'UTC'));
        $range = self::resolveRange($opts['from'] ?? null, $opts['to'] ?? null, $timezone);
        $compare = !empty($opts['compare']);
        $refresh = !empty($opts['refresh']);
        $rangeKey = $range['from'] . '_' . $range['to'] . '_' . ($compare ? 'c' : 'n');
        $cacheKey = [$owner, $rangeKey, $timezone, self::VERSION];
        if (!$refresh) {
            $cached = $pdo->prepare('SELECT payload_json, computed_at, coverage_percent FROM geo_analytics_cache WHERE user_email = :owner AND range_key = :range AND timezone = :tz AND formula_version = :version AND expires_at > now()');
            $cached->execute([':owner' => $owner, ':range' => $rangeKey, ':tz' => $timezone, ':version' => self::VERSION]);
            $row = $cached->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $payload = json_decode((string)$row['payload_json'], true);
                if (is_array($payload)) return $payload + ['cached' => true, 'computed_at' => $row['computed_at']];
            }
        }
        $current = self::periodMetrics($pdo, $owner, $range['from_at'], $range['to_at'], $range['from'], $range['to'], $timezone);
        $previous = null;
        if ($compare) {
            $start = new DateTimeImmutable($range['from'], new DateTimeZone($timezone));
            $end = new DateTimeImmutable($range['to'], new DateTimeZone($timezone));
            $days = (int)(($end->getTimestamp() - $start->getTimestamp()) / 86400) + 1;
            $previousEnd = $start->modify('-1 day');
            $previousStart = $previousEnd->modify('-' . ($days - 1) . ' days');
            $previous = self::periodMetrics($pdo, $owner, $previousStart->setTimezone(new DateTimeZone('UTC'))->format('c'), $previousEnd->setTime(23, 59, 59)->setTimezone(new DateTimeZone('UTC'))->format('c'), $previousStart->format('Y-m-d'), $previousEnd->format('Y-m-d'), $timezone);
            $previous['range'] = ['from' => $previousStart->format('Y-m-d'), 'to' => $previousEnd->format('Y-m-d')];
        }
        $payload = [
            'ok' => true,
            'formula_version' => self::VERSION,
            'range' => $range,
            'metrics' => $current['metrics'],
            'coverage' => $current['coverage'],
            'quality' => $current['quality'],
            'sources' => $current['sources'],
            'devices' => $current['devices'],
            'places' => $current['places'],
            'calendar' => $current['calendar'],
            'hours' => $current['hours'],
            'gaps' => $current['gaps'],
            'insights' => $current['insights'],
            'warnings' => $current['warnings'],
            'comparison' => $previous ? self::compare($current, $previous) : null,
            'previous' => $previous,
            'cached' => false,
            'computed_at' => gmdate('c'),
            'policy' => ['coordinates_returned' => false, 'transport_classification' => false],
        ];
        $upsert = $pdo->prepare(
            "INSERT INTO geo_analytics_cache(user_email, range_key, timezone, formula_version, payload_json, coverage_percent, computed_at, expires_at)
             VALUES(:owner, :range, :tz, :version, :payload, :coverage, now(), now() + interval '5 minutes')
             ON CONFLICT (user_email, range_key, timezone, formula_version) DO UPDATE SET payload_json = excluded.payload_json, coverage_percent = excluded.coverage_percent, computed_at = now(), expires_at = excluded.expires_at"
        );
        $upsert->execute([':owner' => $owner, ':range' => $rangeKey, ':tz' => $timezone, ':version' => self::VERSION, ':payload' => json_encode($payload, JSON_UNESCAPED_SLASHES), ':coverage' => $current['coverage']['quality_percent']]);
        return $payload;
    }

    public static function invalidate(PDO $pdo, string $owner): void
    {
        try { $pdo->prepare('DELETE FROM geo_analytics_cache WHERE user_email = :owner')->execute([':owner' => $owner]); } catch (Throwable $e) { error_log('analytics cache invalidation failed: ' . $e->getMessage()); }
        try {
            $stmt = $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version) SELECT :id, :owner, 'analytics', 'pending', :version WHERE NOT EXISTS (SELECT 1 FROM geo_processor_runs WHERE user_email = :owner AND kind = 'analytics' AND state IN ('pending','processing'))");
            $stmt->execute([':id' => StepsGeolocationStatistics::uuid(), ':owner' => $owner, ':version' => self::VERSION]);
        } catch (Throwable $e) { error_log('analytics enqueue failed: ' . $e->getMessage()); }
    }

    private static function periodMetrics(PDO $pdo, string $owner, string $fromAt, string $toAt, string $fromDate, string $toDate, string $timezone): array
    {
        $daily = StepsGeolocationStatistics::dailyRows($pdo, $owner, $timezone, $fromDate, $toDate);
        if ($daily === []) {
            try { StepsGeolocationStatistics::refresh($pdo, $owner, $timezone, $fromDate, $toDate); } catch (Throwable $e) { error_log('analytics statistics refresh: ' . $e->getMessage()); }
            $daily = StepsGeolocationStatistics::dailyRows($pdo, $owner, $timezone, $fromDate, $toDate);
        }
        $aggregate = StepsGeolocationStatistics::aggregate($daily);
        $points = $pdo->prepare("SELECT quality_state, COUNT(*) AS total, percentile_cont(0.5) WITHIN GROUP (ORDER BY accuracy_m) AS median_accuracy FROM geo_location_points WHERE user_email = :owner AND source_at BETWEEN :from AND :to GROUP BY quality_state");
        $points->execute([':owner' => $owner, ':from' => $fromAt, ':to' => $toAt]);
        $quality = ['accepted' => 0, 'flagged' => 0, 'suspect' => 0, 'rejected' => 0, 'excluded' => 0, 'pending' => 0, 'not_enough_evidence' => 0];
        $medianAccuracy = null;
        $totalPoints = 0;
        foreach ($points->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $state = (string)$row['quality_state'];
            $count = (int)$row['total'];
            $totalPoints += $count;
            if (in_array($state, ['accepted', 'flagged', 'suspect', 'rejected', 'pending', 'not_enough_evidence'], true)) $quality[$state] = ($quality[$state] ?? 0) + $count;
            else $quality['excluded'] += $count;
            if ($row['median_accuracy'] !== null) $medianAccuracy = max((float)$medianAccuracy, (float)$row['median_accuracy']);
        }
        $valid = $quality['accepted'] + $quality['flagged'] + $quality['suspect'];
        $qualityPercent = $totalPoints > 0 ? round(100 * $valid / $totalPoints, 1) : 0.0;
        $sources = [];
        $sourceStmt = $pdo->prepare("SELECT source_type, COUNT(*) AS total FROM geo_location_points WHERE user_email = :owner AND source_at BETWEEN :from AND :to GROUP BY source_type ORDER BY total DESC");
        $sourceStmt->execute([':owner' => $owner, ':from' => $fromAt, ':to' => $toAt]);
        foreach ($sourceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $sources[] = ['source' => (string)$row['source_type'], 'points' => (int)$row['total']];
        $devices = [];
        $deviceStmt = $pdo->prepare("SELECT device_id::text AS device_id, COUNT(*) AS total, MAX(source_at) AS last_at FROM geo_location_points WHERE user_email = :owner AND source_at BETWEEN :from AND :to GROUP BY device_id");
        $deviceStmt->execute([':owner' => $owner, ':from' => $fromAt, ':to' => $toAt]);
        foreach ($deviceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $devices[] = ['device_id' => (string)($row['device_id'] ?? ''), 'points' => (int)$row['total'], 'last_at' => $row['last_at']];
        $dateParams = [':owner' => $owner, ':from' => $fromDate, ':to' => $toDate, ':tz' => $timezone];
        $tripStmt = $pdo->prepare("SELECT COUNT(*) AS trips, COALESCE(SUM(distance_m), 0) AS distance, COALESCE(SUM(duration_s), 0) AS duration, COALESCE(MAX(distance_m), 0) AS longest FROM geo_trips WHERE user_email = :owner AND deleted_at IS NULL AND state <> 'archived' AND (started_at AT TIME ZONE :tz)::date BETWEEN :from AND :to");
        $tripStmt->execute($dateParams);
        $trip = $tripStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $visitStmt = $pdo->prepare("SELECT COUNT(*) AS visits, COUNT(DISTINCT place_id) AS unique_places, COALESCE(SUM(EXTRACT(EPOCH FROM (COALESCE(departed_at, now()) - arrived_at))), 0) AS dwell FROM geo_visits WHERE user_email = :owner AND (arrived_at AT TIME ZONE :tz)::date BETWEEN :from AND :to");
        $visitStmt->execute($dateParams);
        $visit = $visitStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $newPlaces = $pdo->prepare("SELECT COUNT(*) FROM geo_places WHERE user_email = :owner AND deleted_at IS NULL AND (created_at AT TIME ZONE :tz)::date BETWEEN :from AND :to");
        $newPlaces->execute($dateParams);
        $top = $pdo->prepare("SELECT p.id, p.name, p.tag, COUNT(*) AS visits, COALESCE(SUM(EXTRACT(EPOCH FROM (COALESCE(v.departed_at, now()) - v.arrived_at))), 0) AS dwell FROM geo_visits v JOIN geo_places p ON p.id = v.place_id WHERE v.user_email = :owner AND p.deleted_at IS NULL AND (v.arrived_at AT TIME ZONE :tz)::date BETWEEN :from AND :to GROUP BY p.id, p.name, p.tag ORDER BY visits DESC, dwell DESC LIMIT 10");
        $top->execute($dateParams);
        $places = [];
        foreach ($top->fetchAll(PDO::FETCH_ASSOC) as $row) $places[] = ['place_id' => (string)$row['id'], 'name' => (string)$row['name'], 'tag' => (string)$row['tag'], 'visits' => (int)$row['visits'], 'dwell_seconds' => (int)round((float)$row['dwell'])];
        $events = $pdo->prepare("SELECT COUNT(*) FROM geo_geofence_events WHERE user_email = :owner AND occurred_at BETWEEN :from_at AND :to_at");
        $events->execute([':owner' => $owner, ':from_at' => $fromAt, ':to_at' => $toAt]);
        $imports = $pdo->prepare("SELECT COUNT(*) FROM geo_import_files WHERE user_email = :owner AND created_at BETWEEN :from AND :to");
        $imports->execute([':owner' => $owner, ':from' => $fromAt, ':to' => $toAt]);
        $exports = $pdo->prepare("SELECT COUNT(*) FROM geo_exports WHERE user_email = :owner AND created_at BETWEEN :from AND :to");
        $exports->execute([':owner' => $owner, ':from' => $fromAt, ':to' => $toAt]);
        $renders = $pdo->prepare("SELECT COUNT(*) FROM geo_render_jobs WHERE user_email = :owner AND created_at BETWEEN :from AND :to");
        $renders->execute([':owner' => $owner, ':from' => $fromAt, ':to' => $toAt]);
        $hours = array_fill(0, 24, 0);
        $hourStmt = $pdo->prepare("SELECT EXTRACT(HOUR FROM (source_at AT TIME ZONE :tz))::int AS hour, COUNT(*) AS total FROM geo_location_points WHERE user_email = :owner AND source_at BETWEEN :from_at AND :to_at AND quality_state IN ('accepted','flagged','suspect') GROUP BY hour ORDER BY hour");
        $hourStmt->execute([':owner' => $owner, ':tz' => $timezone, ':from_at' => $fromAt, ':to_at' => $toAt]);
        foreach ($hourStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $hours[(int)$row['hour']] = (int)$row['total'];
        $calendar = [];
        foreach ($daily as $row) $calendar[] = ['day' => (string)$row['day'], 'distance_m' => (float)$row['distance_m'], 'points' => (int)$row['point_count'], 'active_seconds' => (int)$row['active_seconds']];
        $timesStmt = $pdo->prepare("SELECT source_at FROM geo_location_points WHERE user_email = :owner AND source_at BETWEEN :from AND :to AND quality_state = 'accepted' ORDER BY source_at ASC LIMIT 50000");
        $timesStmt->execute([':owner' => $owner, ':from' => $fromAt, ':to' => $toAt]);
        $times = array_map('strval', $timesStmt->fetchAll(PDO::FETCH_COLUMN));
        $gaps = StepsGeolocationTimeline::gaps($times, StepsGeolocationTimeline::gapThresholdS());
        $largestGap = 0;
        foreach ($gaps as $gap) $largestGap = max($largestGap, (int)$gap['duration_s']);
        $activeSeconds = (int)($aggregate['active_seconds'] ?? 0);
        $rangeSeconds = max(1, strtotime($toAt) - strtotime($fromAt));
        $timeCoverage = round(min(100, 100 * $activeSeconds / $rangeSeconds), 1);
        $confidence = $qualityPercent >= 75 && $timeCoverage >= 15 ? 'high' : ($qualityPercent >= 45 ? 'medium' : 'low');
        $warnings = [];
        if ($qualityPercent < 50) $warnings[] = 'Low-quality data: treat distance and comparisons as indicative.';
        if ($largestGap > 21600) $warnings[] = 'A data gap longer than 6 hours is present; no distance is manufactured across it.';
        if ($totalPoints === 0) $warnings[] = 'No points in this period.';
        $metrics = [
            'distance_m' => round((float)($trip['distance'] ?? 0), 1),
            'trip_count' => (int)($trip['trips'] ?? 0),
            'trip_duration_s' => (int)($trip['duration'] ?? 0),
            'longest_trip_m' => round((float)($trip['longest'] ?? 0), 1),
            'active_days' => (int)($aggregate['active_days'] ?? 0),
            'active_seconds' => $activeSeconds,
            'stops' => (int)($visit['visits'] ?? 0),
            'unique_places' => (int)($visit['unique_places'] ?? 0),
            'new_places' => (int)$newPlaces->fetchColumn(),
            'dwell_seconds' => (int)round((float)($visit['dwell'] ?? 0)),
            'geofence_events' => (int)$events->fetchColumn(),
            'imports' => (int)$imports->fetchColumn(),
            'exports' => (int)$exports->fetchColumn(),
            'renders' => (int)$renders->fetchColumn(),
            'points' => $totalPoints,
            'median_accuracy_m' => $medianAccuracy,
        ];
        return [
            'metrics' => $metrics,
            'quality' => $quality,
            'coverage' => ['quality_percent' => $qualityPercent, 'time_percent' => $timeCoverage, 'confidence' => $confidence, 'largest_gap_s' => $largestGap, 'gap_count' => count($gaps)],
            'sources' => $sources, 'devices' => $devices, 'places' => $places, 'calendar' => $calendar, 'hours' => $hours,
            'gaps' => array_slice($gaps, 0, 20),
            'insights' => [
                ['key' => 'active_days', 'value' => $metrics['active_days']],
                ['key' => 'new_places', 'value' => $metrics['new_places']],
                ['key' => 'quality_percent', 'value' => $qualityPercent],
            ],
            'warnings' => $warnings,
        ];
    }

    private static function compare(array $current, array $previous): array
    {
        $fields = ['distance_m', 'trip_count', 'active_days', 'unique_places', 'new_places', 'geofence_events'];
        $out = [];
        foreach ($fields as $field) {
            $a = (float)($current['metrics'][$field] ?? 0);
            $b = (float)($previous['metrics'][$field] ?? 0);
            $delta = $a - $b;
            $percent = null;
            if ($b > 0 && $current['coverage']['quality_percent'] >= 45 && $previous['coverage']['quality_percent'] >= 45) $percent = round(100 * $delta / $b, 1);
            $out[$field] = ['current' => $a, 'previous' => $b, 'absolute' => round($delta, 1), 'percent' => $percent, 'reliable' => $percent !== null];
        }
        return $out;
    }
}
