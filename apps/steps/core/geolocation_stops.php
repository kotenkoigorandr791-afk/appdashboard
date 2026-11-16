<?php
/**
 * Stop detection (task 12).
 *
 * A stop is a dwell: usable samples stay inside a radius for a minimum
 * duration. Fragmented dwells caused by GPS noise are merged before they reach
 * the store, so jitter never turns into a crowd of visits. Speed is never used
 * to classify anything.
 */
require_once __DIR__ . '/geolocation_quality.php';

final class StepsGeolocationStops
{
    public const PROCESSOR_VERSION = 'stops-1';

    public static function thresholds(): array
    {
        $env = static function (string $key, float $default, float $min, float $max): float {
            $raw = getenv($key);
            if (!is_string($raw) || trim($raw) === '' || !is_numeric($raw)) return $default;
            return max($min, min($max, (float)$raw));
        };
        return [
            'radius_m' => $env('GEO_STOP_RADIUS_M', 60.0, 10.0, 2000.0),
            'min_duration_s' => $env('GEO_STOP_MIN_DURATION_S', 300.0, 60.0, 86400.0),
            'max_gap_s' => $env('GEO_STOP_MAX_GAP_S', 900.0, 60.0, 86400.0),
            'max_accuracy_m' => $env('GEO_STOP_MAX_ACCURACY_M', 200.0, 5.0, 5000.0),
            'merge_gap_s' => $env('GEO_STOP_MERGE_GAP_S', 600.0, 0.0, 86400.0),
            'merge_radius_m' => $env('GEO_STOP_MERGE_RADIUS_M', 80.0, 5.0, 5000.0),
            'min_points' => $env('GEO_STOP_MIN_POINTS', 3.0, 2.0, 100.0),
        ];
    }

    /**
     * @param list<array<string,mixed>> $points
     * @return array{stops:list<array<string,mixed>>,thresholds:array<string,float>}
     */
    public static function detect(array $points, ?array $thresholds = null): array
    {
        $t = $thresholds ?? self::thresholds();
        $usable = [];
        foreach ($points as $point) {
            if (($point['quality_state'] ?? 'accepted') === 'rejected') continue;
            $at = strtotime((string)($point['source_at'] ?? ''));
            if ($at === false) continue;
            $accuracy = $point['accuracy_m'] ?? null;
            if ($accuracy !== null && is_numeric($accuracy) && (float)$accuracy > $t['max_accuracy_m']) continue;
            $point['_at'] = $at;
            $usable[] = $point;
        }
        usort($usable, static fn(array $a, array $b): int => [$a['_at'], (string)($a['id'] ?? '')] <=> [$b['_at'], (string)($b['id'] ?? '')]);

        $clusters = [];
        $current = null;
        foreach ($usable as $point) {
            if ($current === null) {
                $current = self::cluster($point);
                continue;
            }
            $distance = StepsGeolocationQuality::haversineMeters(
                $current['anchor_lat'], $current['anchor_lon'],
                (float)$point['latitude'], (float)$point['longitude']
            );
            $gap = $point['_at'] - $current['last_at'];
            if ($distance <= $t['radius_m'] && $gap <= $t['max_gap_s']) {
                $current['point_ids'][] = (string)$point['id'];
                $current['last_at'] = $point['_at'];
                $current['ended_at'] = (string)$point['source_at'];
                $current['sum_lat'] += (float)$point['latitude'];
                $current['sum_lon'] += (float)$point['longitude'];
                continue;
            }
            $clusters[] = $current;
            $current = self::cluster($point);
        }
        if ($current !== null) $clusters[] = $current;

        $stops = [];
        foreach ($clusters as $cluster) {
            $count = count($cluster['point_ids']);
            $duration = $cluster['last_at'] - $cluster['start_at'];
            if ($count < (int)$t['min_points'] || $duration < $t['min_duration_s']) continue;
            $stops[] = [
                'started_at' => $cluster['started_at'],
                'ended_at' => $cluster['ended_at'],
                'latitude' => $cluster['sum_lat'] / $count,
                'longitude' => $cluster['sum_lon'] / $count,
                'radius_m' => (float)$t['radius_m'],
                'duration_s' => $duration,
                'point_ids' => $cluster['point_ids'],
                'confidence' => round(min(1.0, 0.5 + 0.05 * $count), 2),
            ];
        }

        // Merge fragments of the same dwell, e.g. a short walk to the window
        // and back inside the same café.
        $merged = [];
        foreach ($stops as $stop) {
            $lastIndex = count($merged) - 1;
            if ($lastIndex >= 0) {
                $previous = $merged[$lastIndex];
                $gap = strtotime($stop['started_at']) - strtotime($previous['ended_at']);
                $distance = StepsGeolocationQuality::haversineMeters($previous['latitude'], $previous['longitude'], $stop['latitude'], $stop['longitude']);
                if ($gap <= $t['merge_gap_s'] && $distance <= $t['merge_radius_m']) {
                    $merged[$lastIndex]['ended_at'] = $stop['ended_at'];
                    $merged[$lastIndex]['point_ids'] = array_merge($previous['point_ids'], $stop['point_ids']);
                    $merged[$lastIndex]['duration_s'] = strtotime($stop['ended_at']) - strtotime($previous['started_at']);
                    $merged[$lastIndex]['confidence'] = max($previous['confidence'], $stop['confidence']);
                    continue;
                }
            }
            $merged[] = $stop;
        }

        return ['stops' => $merged, 'thresholds' => $t];
    }

    private static function cluster(array $point): array
    {
        return [
            'anchor_lat' => (float)$point['latitude'],
            'anchor_lon' => (float)$point['longitude'],
            'start_at' => $point['_at'],
            'last_at' => $point['_at'],
            'started_at' => (string)$point['source_at'],
            'ended_at' => (string)$point['source_at'],
            'point_ids' => [(string)$point['id']],
            'sum_lat' => (float)$point['latitude'],
            'sum_lon' => (float)$point['longitude'],
        ];
    }

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    public static function enqueue(PDO $pdo, string $owner): bool
    {
        $stmt = $pdo->prepare(
            "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version)
             SELECT :id, :owner, 'stops', 'pending', :version
              WHERE NOT EXISTS (
                    SELECT 1 FROM geo_processor_runs
                     WHERE user_email = :owner AND kind = 'stops' AND state IN ('pending','processing')
              )"
        );
        $stmt->execute([':id' => self::uuid(), ':owner' => $owner, ':version' => self::PROCESSOR_VERSION]);
        return $stmt->rowCount() > 0;
    }
}
