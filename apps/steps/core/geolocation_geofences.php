<?php
/**
 * Circular geofence evaluation (task 14).
 *
 * Transitions use hysteresis and a debounce counter, so GPS jitter around the
 * boundary cannot spam enter/exit events. Zones are circles today; the stored
 * polygon geometry leaves room for arbitrary shapes later.
 */
require_once __DIR__ . '/geolocation_quality.php';

final class StepsGeolocationGeofences
{
    public const EVALUATOR_VERSION = 'geofence-1';

    public static function thresholds(): array
    {
        $env = static function (string $key, float $default, float $min, float $max): float {
            $raw = getenv($key);
            if (!is_string($raw) || trim($raw) === '' || !is_numeric($raw)) return $default;
            return max($min, min($max, (float)$raw));
        };
        return [
            'hysteresis_m' => $env('GEO_GEOFENCE_HYSTERESIS_M', 30.0, 0.0, 500.0),
            'debounce_points' => $env('GEO_GEOFENCE_DEBOUNCE_POINTS', 2.0, 1.0, 20.0),
            'min_dwell_s' => $env('GEO_GEOFENCE_MIN_DWELL_S', 60.0, 0.0, 86400.0),
            'max_accuracy_m' => $env('GEO_GEOFENCE_MAX_ACCURACY_M', 200.0, 5.0, 5000.0),
        ];
    }

    /**
     * @param array{id?:string,latitude:float|string,longitude:float|string,radius_m:float|string,is_active?:bool} $zone
     * @param list<array<string,mixed>> $points ordered by time
     * @param 'inside'|'outside'|null $state state before this batch
     * @return array{state:?string,events:list<array<string,mixed>>}
     */
    public static function evaluate(array $zone, array $points, ?string $state, ?array $thresholds = null): array
    {
        $t = $thresholds ?? self::thresholds();
        $events = [];
        if (($zone['is_active'] ?? true) === false) return ['state' => $state, 'events' => []];

        $radius = (float)$zone['radius_m'];
        $zoneLat = (float)$zone['latitude'];
        $zoneLon = (float)$zone['longitude'];
        $insideNeed = max(0.0, $radius - $t['hysteresis_m']);
        $outsideNeed = $radius + $t['hysteresis_m'];
        $debounce = max(1, (int)$t['debounce_points']);
        $pendingInside = 0;
        $pendingOutside = 0;
        $lastInsideAt = null;

        foreach ($points as $point) {
            if (in_array((string)($point['quality_state'] ?? 'accepted'), StepsGeolocationQuality::EXCLUDED_STATES, true)) continue;
            $accuracy = $point['accuracy_m'] ?? null;
            if ($accuracy !== null && is_numeric($accuracy) && (float)$accuracy > $t['max_accuracy_m']) continue;
            $at = strtotime((string)($point['source_at'] ?? ''));
            if ($at === false) continue;
            $distance = StepsGeolocationQuality::haversineMeters($zoneLat, $zoneLon, (float)$point['latitude'], (float)$point['longitude']);
            $confidence = round(max(0.0, 1.0 - (($accuracy !== null && is_numeric($accuracy)) ? (float)$accuracy / $t['max_accuracy_m'] : 0.1)), 2);

            if ($state === null) {
                $state = $distance <= $radius ? 'inside' : 'outside';
                if ($state === 'inside') $lastInsideAt = $at;
                continue;
            }
            if ($state === 'outside') {
                $pendingOutside = 0;
                if ($distance > $insideNeed) {
                    $pendingInside = 0;
                    continue;
                }
                $pendingInside++;
                if ($pendingInside < $debounce) continue;
                $pendingInside = 0;
                $state = 'inside';
                $lastInsideAt = $at;
                $events[] = ['type' => 'enter', 'occurred_at' => (string)$point['source_at'], 'point_id' => (string)($point['id'] ?? ''), 'distance_m' => round($distance, 1), 'confidence' => $confidence];
                continue;
            }
            $pendingInside = 0;
            if ($distance < $outsideNeed) {
                $pendingOutside = 0;
                continue;
            }
            if ($lastInsideAt !== null && ($at - $lastInsideAt) < $t['min_dwell_s']) continue;
            $pendingOutside++;
            if ($pendingOutside < $debounce) continue;
            $pendingOutside = 0;
            $state = 'outside';
            $events[] = ['type' => 'exit', 'occurred_at' => (string)$point['source_at'], 'point_id' => (string)($point['id'] ?? ''), 'distance_m' => round($distance, 1), 'confidence' => $confidence];
        }

        return ['state' => $state, 'events' => $events];
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
             SELECT :id, :owner, 'geofences', 'pending', :version
              WHERE NOT EXISTS (
                    SELECT 1 FROM geo_processor_runs
                     WHERE user_email = :owner AND kind = 'geofences' AND state IN ('pending','processing')
              )"
        );
        $stmt->execute([':id' => self::uuid(), ':owner' => $owner, ':version' => self::EVALUATOR_VERSION]);
        return $stmt->rowCount() > 0;
    }
}
