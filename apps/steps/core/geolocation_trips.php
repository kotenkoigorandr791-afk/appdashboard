<?php
/**
 * Continuous trip segmentation (task 11).
 *
 * Trips are built from temporal and spatial continuity only: speed, altitude
 * and bearing stay descriptive metadata and never classify a journey. A trip
 * is closed only by a long inactivity gap, an impossible discontinuity, a user
 * boundary or an explicit configured boundary, and every split records the
 * reason that started the next trip.
 */
require_once __DIR__ . '/geolocation_quality.php';

final class StepsGeolocationTrips
{
    public const PROCESSOR_VERSION = 'trips-1';

    public static function thresholds(): array
    {
        $env = static function (string $key, float $default, float $min, float $max): float {
            $raw = getenv($key);
            if (!is_string($raw) || trim($raw) === '' || !is_numeric($raw)) return $default;
            return max($min, min($max, (float)$raw));
        };
        return [
            'max_gap_s' => $env('GEO_TRIP_MAX_GAP_S', 900.0, 60.0, 86400.0),
            'max_accuracy_m' => $env('GEO_TRIP_MAX_ACCURACY_M', 200.0, 5.0, 5000.0),
            'max_jump_m' => $env('GEO_TRIP_MAX_JUMP_M', 5000.0, 100.0, 100000.0),
            'jump_window_s' => $env('GEO_TRIP_JUMP_WINDOW_S', 60.0, 1.0, 3600.0),
            'max_speed_mps' => $env('GEO_TRIP_MAX_SPEED_MPS', 450.0, 10.0, 2000.0),
            'min_distance_m' => $env('GEO_TRIP_MIN_DISTANCE_M', 100.0, 0.0, 50000.0),
            'min_points' => $env('GEO_TRIP_MIN_POINTS', 3.0, 2.0, 100.0),
            'overlap_s' => $env('GEO_TRIP_OVERLAP_S', 120.0, 0.0, 3600.0),
        ];
    }

    /**
     * Merges per-device streams in UTC order. A second device's sample that
     * falls inside the overlap window of the device currently reporting is a
     * duplicate view of the same movement and is suppressed; outside the
     * window both streams are kept.
     */
    public static function mergeStreams(array $streams, ?array $thresholds = null): array
    {
        $t = $thresholds ?? self::thresholds();
        $flat = [];
        foreach ($streams as $deviceId => $points) {
            foreach ($points as $point) {
                $point['device_id'] = (string)($point['device_id'] ?? $deviceId);
                $point['_at'] = strtotime((string)($point['source_at'] ?? ''));
                if ($point['_at'] === false) continue;
                $flat[] = $point;
            }
        }
        usort($flat, static fn(array $a, array $b): int => [$a['_at'], (string)$a['device_id']] <=> [$b['_at'], (string)$b['device_id']]);
        $out = [];
        $activeDevice = null;
        $activeAt = 0;
        foreach ($flat as $point) {
            if ($activeDevice !== null && $point['device_id'] !== $activeDevice && abs($point['_at'] - $activeAt) <= $t['overlap_s']) {
                continue;
            }
            $out[] = $point;
            $activeDevice = $point['device_id'];
            $activeAt = $point['_at'];
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $points usable samples in any order
     * @param list<int> $userBoundaryTimes epoch seconds of explicit user splits
     * @return array{trips:list<array<string,mixed>>,dropped:int,thresholds:array<string,float>}
     */
    public static function segment(array $points, ?array $thresholds = null, array $userBoundaryTimes = []): array
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

        $trips = [];
        $dropped = 0;
        $current = null;
        $previous = null;
        $startReason = 'first_point';

        $finish = static function (?array $trip) use ($t, &$trips, &$dropped): void {
            if ($trip === null) return;
            $count = count($trip['point_ids']);
            if ($count < (int)$t['min_points'] || $trip['distance_m'] < $t['min_distance_m']) {
                $dropped++;
                return;
            }
            $trip['point_count'] = $count;
            $trips[] = $trip;
        };
        $open = static fn(array $point, string $reason): array => [
            'started_at' => (string)$point['source_at'],
            'ended_at' => (string)$point['source_at'],
            'distance_m' => 0.0,
            'point_ids' => [(string)$point['id']],
            'boundary_reason' => $reason,
            'device_ids' => [(string)$point['device_id']],
        ];

        foreach ($usable as $point) {
            if ($previous !== null && $point['_at'] === $previous['_at']) continue;
            if ($current === null) {
                $current = $open($point, $startReason);
                $previous = $point;
                continue;
            }
            $gap = $point['_at'] - $previous['_at'];
            $reason = null;
            if (in_array($point['_at'], $userBoundaryTimes, true)) {
                $reason = 'user_boundary';
            } elseif ($gap > $t['max_gap_s']) {
                $reason = 'long_inactivity';
            } elseif ($gap > 0 && $gap <= $t['jump_window_s']) {
                $distance = StepsGeolocationQuality::haversineMeters(
                    (float)$previous['latitude'], (float)$previous['longitude'],
                    (float)$point['latitude'], (float)$point['longitude']
                );
                if ($distance > $t['max_jump_m'] && ($distance / $gap) > $t['max_speed_mps']) {
                    $reason = 'impossible_discontinuity';
                }
            }
            if ($reason !== null) {
                $finish($current);
                $current = $open($point, $reason);
                $startReason = $reason;
                $previous = $point;
                continue;
            }
            $current['distance_m'] += StepsGeolocationQuality::haversineMeters(
                (float)$previous['latitude'], (float)$previous['longitude'],
                (float)$point['latitude'], (float)$point['longitude']
            );
            $current['ended_at'] = (string)$point['source_at'];
            $current['point_ids'][] = (string)$point['id'];
            $device = (string)$point['device_id'];
            if (!in_array($device, $current['device_ids'], true)) $current['device_ids'][] = $device;
            $previous = $point;
        }
        $finish($current);

        return ['trips' => $trips, 'dropped' => $dropped, 'thresholds' => $t];
    }

    /** Visual debug output: one LineString per trip plus the raw samples. */
    public static function geojson(array $points, array $trips, array $thresholds = []): array
    {
        $byId = [];
        foreach ($points as $point) $byId[(string)$point['id']] = $point;
        $features = [];
        foreach ($trips as $trip) {
            $coordinates = [];
            foreach ($trip['point_ids'] as $id) {
                if (!isset($byId[$id])) continue;
                $coordinates[] = [(float)$byId[$id]['longitude'], (float)$byId[$id]['latitude']];
            }
            if (count($coordinates) < 2) continue;
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates],
                'properties' => [
                    'started_at' => $trip['started_at'],
                    'ended_at' => $trip['ended_at'],
                    'distance_m' => round((float)$trip['distance_m'], 1),
                    'boundary_reason' => $trip['boundary_reason'],
                    'point_count' => count($trip['point_ids']),
                ],
            ];
        }
        foreach ($points as $point) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [(float)$point['longitude'], (float)$point['latitude']]],
                'properties' => [
                    'quality_state' => $point['quality_state'] ?? null,
                    'accuracy_m' => $point['accuracy_m'] ?? null,
                    'source_at' => $point['source_at'] ?? null,
                ],
            ];
        }
        return ['type' => 'FeatureCollection', 'features' => $features, 'thresholds' => $thresholds];
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
             SELECT :id, :owner, 'trips', 'pending', :version
              WHERE NOT EXISTS (
                    SELECT 1 FROM geo_processor_runs
                     WHERE user_email = :owner AND kind = 'trips' AND state IN ('pending','processing')
              )"
        );
        $stmt->execute([':id' => self::uuid(), ':owner' => $owner, ':version' => self::PROCESSOR_VERSION]);
        return $stmt->rowCount() > 0;
    }
}
