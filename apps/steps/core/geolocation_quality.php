<?php
/**
 * Location quality pipeline (task 10).
 *
 * Quality decisions are explainable and never rewrite the recorded geometry:
 * a point keeps its coordinates and receives quality_state, quality_reason and
 * a correction_json holding the flags and processor version. A point is either
 * accepted, accepted-but-flagged (excluded from derived routes by later
 * processors), or rejected. Nothing here infers a transport type from speed or
 * bearing.
 */
final class StepsGeolocationQuality
{
    public const PROCESSOR_VERSION = 'quality-1';
    public const STATE_PENDING = 'pending';
    public const STATE_ACCEPTED = 'accepted';
    public const STATE_FLAGGED = 'flagged';
    public const STATE_REJECTED = 'rejected';

    /** Tenant-safe thresholds; environment overrides are bounded. */
    public static function thresholds(): array
    {
        $env = static function (string $key, float $default, float $min, float $max): float {
            $raw = getenv($key);
            if (!is_string($raw) || trim($raw) === '' || !is_numeric($raw)) return $default;
            return max($min, min($max, (float)$raw));
        };
        return [
            'max_accuracy_m' => $env('GEO_QUALITY_MAX_ACCURACY_M', 150.0, 5.0, 5000.0),
            'max_speed_mps' => $env('GEO_QUALITY_MAX_SPEED_MPS', 450.0, 10.0, 2000.0),
            'max_jump_m' => $env('GEO_QUALITY_MAX_JUMP_M', 5000.0, 100.0, 100000.0),
            'jump_window_s' => $env('GEO_QUALITY_JUMP_WINDOW_S', 60.0, 1.0, 3600.0),
            'max_future_s' => $env('GEO_QUALITY_MAX_FUTURE_S', 300.0, 0.0, 86400.0),
            'max_age_days' => $env('GEO_QUALITY_MAX_AGE_DAYS', 3650.0, 1.0, 36500.0),
        ];
    }

    public static function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param array{latitude?:mixed,longitude?:mixed,source_at?:mixed,accuracy_m?:mixed} $point
     * @param array{latitude?:mixed,longitude?:mixed,source_at?:mixed}|null $previous
     * @return array{state:string,reason:string,flags:list<string>}
     */
    public static function evaluate(array $point, ?array $previous, ?array $thresholds = null): array
    {
        $t = $thresholds ?? self::thresholds();
        $lat = $point['latitude'] ?? null;
        $lon = $point['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lon)) {
            return ['state' => self::STATE_REJECTED, 'reason' => 'invalid_coordinates', 'flags' => ['invalid_coordinates']];
        }
        $lat = (float)$lat;
        $lon = (float)$lon;
        if (!is_finite($lat) || !is_finite($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return ['state' => self::STATE_REJECTED, 'reason' => 'invalid_coordinates', 'flags' => ['invalid_coordinates']];
        }
        $at = $point['source_at'] ?? null;
        $timestamp = is_string($at) || is_numeric($at) ? strtotime((string)$at) : false;
        if ($timestamp === false || $timestamp === null) {
            return ['state' => self::STATE_REJECTED, 'reason' => 'invalid_timestamp', 'flags' => ['invalid_timestamp']];
        }
        $flags = [];
        if ($timestamp - time() > $t['max_future_s']) $flags[] = 'timestamp_future';
        if ($timestamp < time() - (int)($t['max_age_days'] * 86400)) $flags[] = 'timestamp_too_old';

        $accuracy = $point['accuracy_m'] ?? null;
        $sourceType = (string)($point['source_type'] ?? '');
        if ($accuracy === null || !is_numeric($accuracy)) {
            // GPX/TCX exports commonly omit horizontal accuracy. Imported history
            // is still usable; mobile samples without accuracy stay flagged.
            if ($sourceType !== 'import') $flags[] = 'accuracy_missing';
        } elseif ((float)$accuracy > $t['max_accuracy_m']) {
            $flags[] = 'low_accuracy';
        }

        if ($previous !== null && is_numeric($previous['latitude'] ?? null) && is_numeric($previous['longitude'] ?? null)) {
            $prevAt = strtotime((string)($previous['source_at'] ?? ''));
            if ($prevAt !== false) {
                $deltaS = $timestamp - $prevAt;
                if ($deltaS === 0) {
                    $flags[] = 'duplicate_timestamp';
                } elseif ($deltaS < 0) {
                    $flags[] = 'timestamp_order';
                } else {
                    $distance = self::haversineMeters((float)$previous['latitude'], (float)$previous['longitude'], $lat, $lon);
                    if ($distance > $t['max_jump_m'] && $deltaS <= $t['jump_window_s']) {
                        $flags[] = 'jump';
                    }
                    // Impossible speed is a quality flag only; fast but valid
                    // travel (flights, trains) stays accepted.
                    if (($distance / $deltaS) > $t['max_speed_mps']) {
                        $flags[] = 'impossible_speed';
                    }
                }
            }
        }

        if ($flags === []) return ['state' => self::STATE_ACCEPTED, 'reason' => 'ok', 'flags' => []];
        return ['state' => self::STATE_FLAGGED, 'reason' => $flags[0], 'flags' => $flags];
    }

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    /**
     * Durable marker in shared PostgreSQL; Redis is never the queue. Repeated
     * ingestion while a run is pending creates no extra rows.
     */
    public static function enqueue(PDO $pdo, string $owner): bool
    {
        $stmt = $pdo->prepare(
            "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version)
             SELECT :id, :owner, 'quality', 'pending', :version
              WHERE NOT EXISTS (
                    SELECT 1 FROM geo_processor_runs
                     WHERE user_email = :owner AND kind = 'quality' AND state IN ('pending','processing')
              )"
        );
        $stmt->execute([':id' => self::uuid(), ':owner' => $owner, ':version' => self::PROCESSOR_VERSION]);
        return $stmt->rowCount() > 0;
    }
}
