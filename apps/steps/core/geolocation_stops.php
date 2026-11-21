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
require_once __DIR__ . '/geolocation_place_config.php';

final class StepsGeolocationStops
{
    public const PROCESSOR_VERSION = 'stops-1';

    public static function thresholds(): array
    {
        return StepsGeolocationPlaceConfig::stop();
    }

    /**
     * @param list<array<string,mixed>> $points
     * @return array{stops:list<array<string,mixed>>,thresholds:array<string,float>}
     */
    public static function detect(array $points, ?array $thresholds = null, ?int $now = null): array
    {
        $t = $thresholds ?? self::thresholds();
        $now = $now ?? time();
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
                $current['obs'][] = [(float)$point['latitude'], (float)$point['longitude'], isset($point['accuracy_m']) && is_numeric($point['accuracy_m']) ? (float)$point['accuracy_m'] : null];
                continue;
            }
            $clusters[] = $current;
            $current = self::cluster($point);
        }
        if ($current !== null) $clusters[] = $current;

        // Longer tracks need denser dwells, but watermark batches can be thousands
        // of points — capping the sqrt input keeps café-length stops detectable.
        $routePoints = count($usable);
        $adaptiveMin = (int)max(
            (int)$t['min_points'],
            min((int)$t['max_adaptive_points'], (int)ceil(sqrt(max(1, min(400, $routePoints))) * (float)$t['density_factor']))
        );

        $stops = [];
        foreach ($clusters as $cluster) {
            $count = count($cluster['point_ids']);
            $duration = $cluster['last_at'] - $cluster['start_at'];
            if ($count < $adaptiveMin || $duration < $t['min_duration_s']) continue;
            [$centerLat, $centerLon, $medianAccuracy] = self::center($cluster['obs'], (float)$t['preferred_accuracy_m']);
            $share = $routePoints > 0 ? $count / $routePoints : 0.0;
            $stops[] = [
                'started_at' => $cluster['started_at'],
                'ended_at' => $cluster['ended_at'],
                'latitude' => $centerLat,
                'longitude' => $centerLon,
                'radius_m' => (float)$t['radius_m'],
                'duration_s' => $duration,
                'point_ids' => $cluster['point_ids'],
                'median_accuracy_m' => $medianAccuracy,
                'provisional' => $now - $cluster['last_at'] < (float)$t['confirm_after_s'],
                'confidence' => round(min(1.0, 0.4 + 0.35 * min(1.0, $count / max(1, $adaptiveMin)) + 0.25 * min(1.0, $share * 8.0)), 2),
                'adaptive_min_points' => $adaptiveMin,
                'route_points' => $routePoints,
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
                    $merged[$lastIndex]['provisional'] = $stop['provisional'];
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
            'obs' => [[(float)$point['latitude'], (float)$point['longitude'], isset($point['accuracy_m']) && is_numeric($point['accuracy_m']) ? (float)$point['accuracy_m'] : null]],
        ];
    }

    /**
     * Centre weighted by 1/accuracy^2, using only fixes at or under the
     * preferred accuracy when enough of them exist.
     *
     * @param list<array{0:float,1:float,2:?float}> $obs
     * @return array{0:float,1:float,2:?float}
     */
    public static function center(array $obs, float $preferred): array
    {
        $good = array_values(array_filter($obs, static fn(array $o): bool => $o[2] === null || $o[2] <= $preferred));
        $use = count($good) >= 2 ? $good : $obs;
        $sw = 0.0; $sl = 0.0; $so = 0.0;
        foreach ($use as $o) {
            $acc = max(3.0, $o[2] ?? $preferred);
            $wt = 1.0 / ($acc * $acc);
            $sw += $wt; $sl += $wt * $o[0]; $so += $wt * $o[1];
        }
        $accs = array_values(array_filter(array_map(static fn(array $o): ?float => $o[2], $obs), static fn($v): bool => $v !== null));
        sort($accs);
        return [$sl / $sw, $so / $sw, $accs === [] ? null : $accs[intdiv(count($accs), 2)]];
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
