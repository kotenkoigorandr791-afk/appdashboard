<?php
/**
 * Explainable location quality pipeline (tasks 10 and 34).
 *
 * Quality decisions never rewrite the recorded geometry. A point keeps its
 * coordinates and receives a transparent score, severity, machine-readable
 * reason codes and a processor version. Raw points remain canonical; excluded
 * states only affect derived views and processors.
 */
final class StepsGeolocationQuality
{
    public const PROCESSOR_VERSION = 'quality-2';
    public const STATE_PENDING = 'pending';
    public const STATE_ACCEPTED = 'accepted';
    public const STATE_SUSPECT = 'suspect';
    public const STATE_FLAGGED = 'flagged';
    public const STATE_REJECTED = 'rejected';
    public const STATE_EXCLUDED_BY_USER = 'excluded_by_user';
    public const STATE_PRIVATE_REDACTED = 'private_redacted';
    public const STATE_NOT_ENOUGH_EVIDENCE = 'not_enough_evidence';

    public const EXCLUDED_STATES = [
        self::STATE_REJECTED,
        self::STATE_EXCLUDED_BY_USER,
        self::STATE_PRIVATE_REDACTED,
    ];

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
            'max_segment_accuracy_m' => $env('GEO_QUALITY_MAX_SEGMENT_ACCURACY_M', 60.0, 5.0, 500.0),
            // 300 km/h ≈ 83.3 m/s — above that is treated as spoof / teleport.
            'max_speed_mps' => $env('GEO_QUALITY_MAX_SPEED_MPS', 83.333, 10.0, 2000.0),
            'max_jump_m' => $env('GEO_QUALITY_MAX_JUMP_M', 5000.0, 100.0, 100000.0),
            'jump_window_s' => $env('GEO_QUALITY_JUMP_WINDOW_S', 60.0, 1.0, 3600.0),
            'max_future_s' => $env('GEO_QUALITY_MAX_FUTURE_S', 300.0, 0.0, 86400.0),
            'max_age_days' => $env('GEO_QUALITY_MAX_AGE_DAYS', 3650.0, 1.0, 36500.0),
            'max_segment_gap_s' => $env('GEO_QUALITY_MAX_SEGMENT_GAP_S', 900.0, 30.0, 86400.0),
            'stationary_radius_m' => $env('GEO_QUALITY_STATIONARY_RADIUS_M', 30.0, 5.0, 500.0),
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

    /** @return list<string> */
    public static function reasonsFromDetails(array $details): array
    {
        $values = $details['flags'] ?? $details['reason_codes'] ?? [];
        return is_array($values) ? array_values(array_unique(array_filter(array_map('strval', $values)))) : [];
    }

    /**
     * @param array{latitude?:mixed,longitude?:mixed,source_at?:mixed,accuracy_m?:mixed} $point
     * @param array{latitude?:mixed,longitude?:mixed,source_at?:mixed}|null $previous
     * @return array{state:string,reason:string,flags:list<string>,reasons:list<string>,score:float,severity:string,version:string}
     */
    public static function evaluate(array $point, ?array $previous, ?array $thresholds = null): array
    {
        $t = $thresholds ?? self::thresholds();
        $lat = $point['latitude'] ?? null;
        $lon = $point['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lon)) {
            return self::decision(self::STATE_REJECTED, 'invalid_coordinates', ['invalid_coordinates'], 0.0, 'critical');
        }
        $lat = (float)$lat;
        $lon = (float)$lon;
        if (!is_finite($lat) || !is_finite($lon) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return self::decision(self::STATE_REJECTED, 'invalid_coordinates', ['invalid_coordinates'], 0.0, 'critical');
        }
        $at = $point['source_at'] ?? null;
        $timestamp = is_string($at) || is_numeric($at) ? strtotime((string)$at) : false;
        if ($timestamp === false || $timestamp === null) {
            return self::decision(self::STATE_REJECTED, 'invalid_timestamp', ['invalid_timestamp'], 0.0, 'critical');
        }
        $flags = [];
        $score = 1.0;
        if ($timestamp - time() > $t['max_future_s']) {
            $flags[] = 'timestamp_future';
            $score -= 0.2;
        }
        if ($timestamp < time() - (int)($t['max_age_days'] * 86400)) {
            $flags[] = 'timestamp_too_old';
            $score -= 0.2;
        }

        $accuracy = $point['accuracy_m'] ?? null;
        $sourceType = (string)($point['source_type'] ?? '');
        if ($accuracy === null || !is_numeric($accuracy)) {
            // GPX/TCX exports commonly omit horizontal accuracy. Imported history
            // is still usable; mobile samples without accuracy stay flagged.
            if ($sourceType !== 'import') {
                $flags[] = 'accuracy_missing';
                $score -= 0.12;
            }
        } elseif ((float)$accuracy > $t['max_accuracy_m']) {
            $flags[] = 'low_accuracy';
            $score -= 0.28;
        } elseif ((float)$accuracy > $t['max_segment_accuracy_m']) {
            $flags[] = 'accuracy_degraded';
            $score -= 0.14;
        }

        if ($previous !== null && is_numeric($previous['latitude'] ?? null) && is_numeric($previous['longitude'] ?? null)) {
            $prevAt = strtotime((string)($previous['source_at'] ?? ''));
            if ($prevAt !== false) {
                $deltaS = $timestamp - $prevAt;
                if ($deltaS === 0) {
                    $flags[] = 'duplicate_timestamp';
                    $score -= 0.1;
                } elseif ($deltaS < 0) {
                    $flags[] = 'timestamp_order';
                    $score -= 0.15;
                } else {
                    $distance = self::haversineMeters((float)$previous['latitude'], (float)$previous['longitude'], $lat, $lon);
                    if ($distance > $t['max_jump_m'] && $deltaS <= $t['jump_window_s']) {
                        $flags[] = 'jump';
                        $score -= 0.35;
                    }
                    // Impossible speed is a quality flag only; fast but valid
                    // travel (flights, trains) stays accepted.
                    if (($distance / $deltaS) > $t['max_speed_mps']) {
                        $flags[] = 'impossible_speed';
                        $score -= 0.4;
                    }
                    // Sampled movement far below reported accuracy is likely
                    // stationary jitter; it must not create loops or roads.
                    if (isset($t['stationary_radius_m']) && $distance < $t['stationary_radius_m'] && $deltaS < 300) {
                        $score -= 0.02;
                    }
                }
            }
        }

        $score = round(max(0.05, min(1.0, $score)), 3);
        if ($flags === []) return self::decision(self::STATE_ACCEPTED, 'ok', [], $score, $score >= 0.85 ? 'ok' : 'info');
        // Teleports / spoofed GPS (impossible ground speed) must not feed stops,
        // visits or the map. Keep the sample for audit as rejected.
        if (in_array('impossible_speed', $flags, true)) {
            return self::decision(self::STATE_REJECTED, 'impossible_speed', $flags, max(0.05, $score), 'critical');
        }
        if (in_array('jump', $flags, true) && $score < 0.5) {
            return self::decision(self::STATE_REJECTED, 'jump', $flags, max(0.05, $score), 'critical');
        }
        $severity = ($score < 0.45 || in_array('jump', $flags, true)) ? 'critical' : 'warning';
        return self::decision(self::STATE_FLAGGED, $flags[0], $flags, $score, $severity);
    }

    private static function decision(string $state, string $reason, array $flags, float $score, string $severity): array
    {
        return [
            'state' => $state,
            'reason' => $reason,
            'flags' => array_values($flags),
            'reasons' => array_values($flags),
            'score' => $score,
            'severity' => $severity,
            'version' => self::PROCESSOR_VERSION,
        ];
    }

    /**
     * Segment-level evidence used by the conservative matcher. This function
     * never decides a transport mode; it only reports whether matching is safe.
     *
     * @param list<array<string,mixed>> $points
     * @return array{eligible:bool,reasons:list<string>,score:float,median_accuracy_m:float|null,coverage:float,point_count:int,duration_s:int,stationary:bool}
     */
    public static function assessSegment(array $points, ?array $thresholds = null): array
    {
        $t = $thresholds ?? self::thresholds();
        $valid = [];
        $reasons = [];
        foreach ($points as $point) {
            $state = (string)($point['quality_state'] ?? self::STATE_ACCEPTED);
            if (in_array($state, self::EXCLUDED_STATES, true)) {
                $reasons[] = $state === self::STATE_PRIVATE_REDACTED ? 'privacy_zone_boundary' : 'excluded_by_user';
                continue;
            }
            $at = strtotime((string)($point['source_at'] ?? ''));
            if ($at === false) { $reasons[] = 'invalid_timestamp'; continue; }
            if (!is_numeric($point['latitude'] ?? null) || !is_numeric($point['longitude'] ?? null)) { $reasons[] = 'invalid_coordinates'; continue; }
            $point['_at'] = $at;
            $valid[] = $point;
        }
        usort($valid, static fn(array $a, array $b): int => [$a['_at'], (string)($a['id'] ?? '')] <=> [$b['_at'], (string)($b['id'] ?? '')]);
        $count = count($valid);
        $duration = $count > 1 ? (int)$valid[$count - 1]['_at'] - (int)$valid[0]['_at'] : 0;
        if ($count < 3) $reasons[] = 'not_enough_points';
        $accuracies = [];
        $sources = [];
        $maxSubGap = 0;
        $maxDisplacement = 0.0;
        for ($i = 0; $i < $count; $i++) {
            if (is_numeric($valid[$i]['accuracy_m'] ?? null)) $accuracies[] = (float)$valid[$i]['accuracy_m'];
            $sources[(string)($valid[$i]['source_type'] ?? 'unknown')] = true;
            if ($i > 0) {
                $gap = (int)$valid[$i]['_at'] - (int)$valid[$i - 1]['_at'];
                $maxSubGap = max($maxSubGap, $gap);
                if ($gap > $t['max_segment_gap_s']) $reasons[] = 'long_gap';
                $maxDisplacement = max($maxDisplacement, self::haversineMeters(
                    (float)$valid[0]['latitude'], (float)$valid[0]['longitude'],
                    (float)$valid[$i]['latitude'], (float)$valid[$i]['longitude']
                ));
            }
        }
        sort($accuracies);
        $median = $accuracies === [] ? null : (float)$accuracies[(int)floor((count($accuracies) - 1) / 2)];
        if ($count > 1 && $duration > 0 && $maxSubGap <= $t['max_segment_gap_s'] && ($maxDisplacement / max(1, $duration)) > $t['max_speed_mps']) {
            $reasons[] = 'impossible_speed';
        }
        if ($median !== null && $median > $t['max_segment_accuracy_m']) $reasons[] = 'low_accuracy';
        if (count($sources) > 1) $reasons[] = 'mixed_sources';
        if ($maxDisplacement < $t['stationary_radius_m'] && $duration >= 300) $reasons[] = 'stationary';
        if ($duration > 0 && $count / max(1, $duration / 60) < 0.05) $reasons[] = 'sparse_samples';
        $reasons = array_values(array_unique($reasons));
        $coverage = count($points) > 0 ? round($count / count($points), 3) : 0.0;
        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'score' => $reasons === [] ? max(0.0, min(1.0, 1.0 - (($median ?? 0) / max(1, $t['max_segment_accuracy_m'])) * 0.4)) : 0.0,
            'median_accuracy_m' => $median,
            'coverage' => $coverage,
            'point_count' => $count,
            'duration_s' => $duration,
            'stationary' => in_array('stationary', $reasons, true),
        ];
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
