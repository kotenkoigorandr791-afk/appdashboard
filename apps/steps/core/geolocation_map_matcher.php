<?php
/**
 * Conservative, explainable map matching (task 34).
 *
 * Pure PHP and deterministic. It projects raw samples onto local, versioned
 * network candidates, scores every candidate with distance, accuracy, temporal
 * continuity and bearing, and only returns a derived line when one connected
 * candidate clearly wins. Ambiguity, gaps, poor accuracy, restricted networks
 * and unsuitable context all produce an explicit abstention instead of a snap.
 *
 * Raw coordinates are never modified. The returned mode is an internal
 * explanation only and is never a transport label.
 */
require_once __DIR__ . '/geolocation_quality.php';

final class StepsGeolocationMapMatcher
{
    public const VERSION = 'matcher-1';
    public const KIND_MODES = ['road', 'path', 'cycleway', 'rail', 'waterway', 'ferry', 'offroad'];

    public static function options(): array
    {
        return [
            'min_points' => 3,
            'max_gap_s' => 300,
            'max_accuracy_m' => 60.0,
            'max_snap_m' => 60.0,
            'min_coverage' => 0.75,
            'min_confidence' => 0.62,
            'ambiguity_margin' => 0.08,
            'mixed_mode_margin' => 0.12,
            'max_speed_mps' => 120.0,
            'stationary_radius_m' => 30.0,
        ];
    }

    /**
     * @param list<array<string,mixed>> $points
     * @param list<array<string,mixed>> $networks
     * @return array{version:string,segments:list<array<string,mixed>>,gaps:list<array<string,mixed>>,summary:array<string,mixed>}
     */
    public static function match(array $points, array $networks, array $options = []): array
    {
        $o = array_merge(self::options(), $options);
        $diagnostics = ['duplicates' => 0, 'out_of_order' => 0, 'invalid' => 0];
        $normalized = [];
        foreach ($points as $point) {
            $at = strtotime((string)($point['source_at'] ?? ''));
            if ($at === false || !is_numeric($point['latitude'] ?? null) || !is_numeric($point['longitude'] ?? null)) {
                $diagnostics['invalid']++;
                continue;
            }
            $point['_at'] = $at;
            $point['latitude'] = (float)$point['latitude'];
            $point['longitude'] = (float)$point['longitude'];
            $normalized[] = $point;
        }
        $seenAt = [];
        $seenPair = [];
        $deduped = [];
        $lastAt = null;
        foreach ($normalized as $point) {
            if ($lastAt !== null && $point['_at'] < $lastAt) $diagnostics['out_of_order']++;
            $lastAt = $point['_at'];
            $pair = $point['_at'] . '|' . round($point['latitude'], 7) . '|' . round($point['longitude'], 7);
            if (isset($seenPair[$pair])) { $diagnostics['duplicates']++; continue; }
            $seenPair[$pair] = true;
            if (isset($seenAt[$point['_at']])) { $diagnostics['duplicates']++; continue; }
            $seenAt[$point['_at']] = true;
            $deduped[] = $point;
        }
        usort($deduped, static fn(array $a, array $b): int => $a['_at'] <=> $b['_at']);

        $groups = [];
        $gaps = [];
        $current = [];
        $previous = null;
        foreach ($deduped as $point) {
            $state = (string)($point['quality_state'] ?? StepsGeolocationQuality::STATE_ACCEPTED);
            $blocked = in_array($state, StepsGeolocationQuality::EXCLUDED_STATES, true);
            if ($previous !== null) {
                $gap = (int)$point['_at'] - (int)$previous['_at'];
                if ($blocked || $gap > $o['max_gap_s']) {
                    if ($current !== []) $groups[] = $current;
                    $current = [];
                    if ($gap > $o['max_gap_s']) {
                        $gaps[] = [
                            'reason' => 'long_gap',
                            'after_point_id' => (string)($previous['id'] ?? ''),
                            'before_point_id' => (string)($point['id'] ?? ''),
                            'gap_s' => $gap,
                        ];
                    }
                }
            }
            if ($blocked) {
                $gaps[] = ['reason' => 'excluded_by_user', 'after_point_id' => (string)($point['id'] ?? ''), 'gap_s' => 0];
                $previous = $point;
                continue;
            }
            $current[] = $point;
            $previous = $point;
        }
        if ($current !== []) $groups[] = $current;

        $segments = [];
        foreach ($groups as $group) {
            $segments[] = self::matchGroup($group, $networks, $o);
        }
        $summary = ['matched' => 0, 'abstained' => 0, 'reasons' => [], 'diagnostics' => $diagnostics];
        foreach ($segments as $segment) {
            if ($segment['state'] === 'matched') $summary['matched']++;
            else $summary['abstained']++;
            foreach ($segment['reasons'] as $reason) $summary['reasons'][$reason] = ($summary['reasons'][$reason] ?? 0) + 1;
        }
        foreach ($gaps as $gap) $summary['reasons'][$gap['reason']] = ($summary['reasons'][$gap['reason']] ?? 0) + 1;
        ksort($summary['reasons']);
        return ['version' => self::VERSION, 'segments' => $segments, 'gaps' => $gaps, 'summary' => $summary];
    }

    private static function matchGroup(array $points, array $networks, array $o): array
    {
        $pointIds = array_values(array_map(static fn(array $p): string => (string)($p['id'] ?? ''), $points));
        $raw = array_values(array_map(static fn(array $p): array => [(float)$p['longitude'], (float)$p['latitude']], $points));
        $startedAt = gmdate('c', (int)$points[0]['_at']);
        $endedAt = gmdate('c', (int)$points[count($points) - 1]['_at']);
        $base = [
            'point_ids' => $pointIds,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'raw_geometry' => $raw,
            'matched_geometry' => null,
            'state' => 'abstained',
            'mode' => 'none',
            'confidence' => 0.0,
            'confidence_band' => 'none',
            'reasons' => [],
            'explanation' => '',
            'network_version' => '',
            'matcher_version' => self::VERSION,
        ];
        $context = strtolower((string)($o['context'] ?? ''));
        foreach ($points as $point) {
            $pointContext = strtolower((string)($point['context'] ?? ''));
            if ($pointContext !== '') $context = $pointContext;
        }
        if (in_array($context, ['air', 'aircraft', 'flight', 'indoor', 'underground', 'tunnel', 'station'], true)) {
            return self::abstain($base, ['unsuitable_context'], 'Context does not support public-network matching.');
        }
        if (!empty($o['privacy_zone_boundary'])) {
            return self::abstain($base, ['privacy_zone_boundary'], 'Raw samples are inside a protected/redacted boundary.');
        }
        if (!empty($o['approximate_only'])) {
            return self::abstain($base, ['approximate_permission'], 'The viewer only has approximate-location authorization.');
        }

        $assessment = StepsGeolocationQuality::assessSegment($points);
        if (!$assessment['eligible']) {
            $reasons = $assessment['reasons'];
            if (empty($reasons)) $reasons = ['not_enough_evidence'];
            return self::abstain($base, $reasons, 'Quality evidence is not sufficient for matching.');
        }
        if (count($points) < (int)$o['min_points']) {
            return self::abstain($base, ['not_enough_points'], 'Too few samples for a connected path.');
        }
        $span = (int)$points[count($points) - 1]['_at'] - (int)$points[0]['_at'];
        if ($span > 0 && self::groupDisplacement($points) / $span > $o['max_speed_mps']) {
            return self::abstain($base, ['impossible_speed'], 'Implied speed makes a road/path match unsafe.');
        }
        if ($assessment['stationary']) {
            return self::abstain($base, ['stationary'], 'Stationary jitter is rendered as-is, without a route.');
        }

        $candidates = [];
        $restrictedSeen = false;
        foreach ($networks as $network) {
            $kind = (string)($network['kind'] ?? '');
            if (!in_array($kind, self::KIND_MODES, true)) continue;
            $access = (string)($network['access'] ?? 'public');
            if ($access === 'private' || $access === 'restricted') { $restrictedSeen = true; continue; }
            $line = self::line($network);
            if (count($line) < 2) continue;
            $candidate = self::scoreNetwork($points, $line, $kind, $network, $o);
            if ($candidate !== null) $candidates[] = $candidate;
        }
        if ($candidates === []) {
            return self::abstain($base, [$restrictedSeen ? 'restricted_network' : 'network_missing'], 'No usable local network candidate is available.');
        }
        usort($candidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $best = $candidates[0];
        $base['network_version'] = (string)($best['network']['version'] ?? '');
        if ($best['score'] < $o['min_confidence']) {
            return self::abstain($base, ['not_enough_evidence'], 'No candidate reaches the conservative confidence threshold.');
        }
        if (count($candidates) > 1) {
            $second = $candidates[1];
            $margin = $best['score'] - $second['score'];
            $sameMode = self::kindMode($best['kind']) === self::kindMode($second['kind']);
            if ($margin < ($sameMode ? $o['ambiguity_margin'] : $o['mixed_mode_margin'])) {
                return self::abstain(
                    $base,
                    [$sameMode ? 'parallel_network_ambiguity' : 'mixed_network_modes'],
                    $sameMode
                        ? 'Two nearby connected candidates fit the samples almost equally well.'
                        : 'Road, path, rail or water candidates are too close to choose safely.'
                );
            }
        }
        if ($best['monotonic'] < 0.75) {
            return self::abstain($base, ['insufficient_connectivity'], 'The candidate path is not continuous in sample order.');
        }
        if ($best['kind'] === 'offroad' && !empty($o['forbid_offroad'])) {
            return self::abstain($base, ['offroad_candidate'], 'Off-road geometry is not eligible for snapping.');
        }
        $matched = self::subline($best['line'], $best['first_offset'], $best['last_offset']);
        if (count($matched) < 2) {
            return self::abstain($base, ['not_enough_evidence'], 'The matched subline has no usable shape.');
        }
        $confidence = round(max(0.0, min(0.99, $best['score'])), 3);
        $band = $confidence >= 0.8 ? 'high' : ($confidence >= 0.62 ? 'medium' : 'low');
        $mode = self::kindMode($best['kind']);
        $base['state'] = 'matched';
        $base['mode'] = $mode;
        $base['confidence'] = $confidence;
        $base['confidence_band'] = $band;
        $base['matched_geometry'] = $matched;
        $base['reasons'] = [];
        $base['explanation'] = sprintf(
            'Improved display follows a nearby mapped %s; median raw accuracy was %s m; confidence %s.',
            $mode,
            $assessment['median_accuracy_m'] === null ? 'unknown' : (string)round((float)$assessment['median_accuracy_m']),
            $band
        );
        return $base;
    }

    private static function abstain(array $base, array $reasons, string $explanation): array
    {
        $base['state'] = 'abstained';
        $base['reasons'] = array_values(array_unique($reasons));
        $base['explanation'] = $explanation;
        return $base;
    }

    private static function groupDisplacement(array $points): float
    {
        $first = $points[0];
        $last = $points[count($points) - 1];
        return StepsGeolocationQuality::haversineMeters((float)$first['latitude'], (float)$first['longitude'], (float)$last['latitude'], (float)$last['longitude']);
    }

    private static function kindMode(string $kind): string
    {
        return in_array($kind, self::KIND_MODES, true) ? $kind : 'none';
    }

    /** @return list<array{0:float,1:float}> lon/lat pairs */
    private static function line(array $network): array
    {
        $geometry = $network['geometry'] ?? $network['coordinates'] ?? [];
        if (!is_array($geometry)) return [];
        if (isset($geometry['coordinates']) && is_array($geometry['coordinates'])) $geometry = $geometry['coordinates'];
        $line = [];
        foreach ($geometry as $coordinate) {
            if (!is_array($coordinate) || count($coordinate) < 2 || !is_numeric($coordinate[0]) || !is_numeric($coordinate[1])) continue;
            $line[] = [(float)$coordinate[0], (float)$coordinate[1]];
        }
        return $line;
    }

    /**
     * @param list<array{0:float,1:float}> $line
     * @return array{distance:float,offset:float,bearing:float,coordinate:array{0:float,1:float}}|null
     */
    private static function project(float $lat, float $lon, array $line): ?array
    {
        $best = null;
        $offset = 0.0;
        $latScale = 111320.0;
        $lonScale = 111320.0 * cos(deg2rad($lat));
        for ($i = 0; $i + 1 < count($line); $i++) {
            $aLon = $line[$i][0]; $aLat = $line[$i][1];
            $bLon = $line[$i + 1][0]; $bLat = $line[$i + 1][1];
            $ax = ($aLon - $lon) * $lonScale; $ay = ($aLat - $lat) * $latScale;
            $bx = ($bLon - $lon) * $lonScale; $by = ($bLat - $lat) * $latScale;
            $dx = $bx - $ax; $dy = $by - $ay;
            $len2 = $dx * $dx + $dy * $dy;
            $t = $len2 > 0 ? max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $len2)) : 0.0;
            $px = $ax + $t * $dx; $py = $ay + $t * $dy;
            $distance = sqrt($px * $px + $py * $py);
            $segmentLength = sqrt($len2);
            if ($best === null || $distance < $best['distance']) {
                $best = [
                    'distance' => $distance,
                    'offset' => $offset + $t * $segmentLength,
                    'bearing' => self::bearing($aLat, $aLon, $bLat, $bLon),
                    'coordinate' => [$lon + $px / max(1e-9, $lonScale), $lat + $py / max(1e-9, $latScale)],
                ];
            }
            $offset += $segmentLength;
        }
        return $best;
    }

    private static function scoreNetwork(array $points, array $line, string $kind, array $network, array $o): ?array
    {
        $projections = [];
        $accuracies = [];
        foreach ($points as $point) {
            $projections[] = self::project((float)$point['latitude'], (float)$point['longitude'], $line);
            if (is_numeric($point['accuracy_m'] ?? null)) $accuracies[] = (float)$point['accuracy_m'];
        }
        $covered = 0;
        $distances = [];
        $offsets = [];
        $headingScores = [];
        $previousProjection = null;
        $previousPoint = null;
        foreach ($projections as $i => $projection) {
            $accuracy = is_numeric($points[$i]['accuracy_m'] ?? null) ? (float)$points[$i]['accuracy_m'] : 25.0;
            if (is_numeric($points[$i]['accuracy_m'] ?? null)) $accuracies[] = (float)$points[$i]['accuracy_m'];
            $limit = min((float)$o['max_snap_m'], max(20.0, $accuracy * 1.5 + 10.0));
            if ($projection !== null && $projection['distance'] <= $limit) {
                $covered++;
                $distances[] = $projection['distance'];
                $offsets[] = $projection['offset'];
            } else {
                $projection = null;
            }
            if ($projection !== null && $previousProjection !== null && $previousPoint !== null) {
                $rawDistance = StepsGeolocationQuality::haversineMeters(
                    (float)$previousPoint['latitude'], (float)$previousPoint['longitude'],
                    (float)$points[$i]['latitude'], (float)$points[$i]['longitude']
                );
                if ($rawDistance > 8) {
                    $rawBearing = self::bearing((float)$previousPoint['latitude'], (float)$previousPoint['longitude'], (float)$points[$i]['latitude'], (float)$points[$i]['longitude']);
                    $diff = abs(self::angleDiff($rawBearing, $projection['bearing']));
                    $headingScores[] = max(0.0, cos(deg2rad(min(90.0, $diff))));
                }
            }
            if ($projection !== null) {
                $previousProjection = $projection;
                $previousPoint = $points[$i];
            }
        }
        $coverage = count($points) > 0 ? $covered / count($points) : 0.0;
        if ($coverage < $o['min_coverage'] || $covered < 3) return null;
        sort($distances);
        $medianDistance = $distances === [] ? INF : (float)$distances[(int)floor((count($distances) - 1) / 2)];
        sort($accuracies);
        $medianAccuracy = $accuracies === [] ? 25.0 : (float)$accuracies[(int)floor((count($accuracies) - 1) / 2)];
        $accuracyScore = max(0.0, 1.0 - ($medianDistance / max(15.0, $medianAccuracy)));
        $monotonic = 0;
        $steps = 0;
        $backtrack = 0.0;
        for ($i = 1; $i < count($offsets); $i++) {
            $steps++;
            $delta = $offsets[$i] - $offsets[$i - 1];
            if ($delta >= -5.0) $monotonic++;
            else $backtrack -= $delta;
        }
        $continuity = $steps > 0 ? $monotonic / $steps : 0.0;
        if ($backtrack > 120) $continuity *= 0.4;
        $heading = $headingScores === [] ? 0.65 : array_sum($headingScores) / count($headingScores);
        $score = 0.38 * $accuracyScore + 0.24 * $coverage + 0.20 * $continuity + 0.18 * $heading;
        if (($kind === 'ferry' || $kind === 'waterway') && $medianDistance > 30) $score -= 0.05;
        return [
            'kind' => $kind,
            'network' => $network,
            'line' => $line,
            'score' => max(0.0, min(1.0, $score)),
            'coverage' => $coverage,
            'median_distance_m' => $medianDistance,
            'median_accuracy_m' => $medianAccuracy,
            'monotonic' => $continuity,
            'first_offset' => $offsets[0],
            'last_offset' => $offsets[count($offsets) - 1],
        ];
    }

    private static function bearing(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $phi1 = deg2rad($lat1); $phi2 = deg2rad($lat2);
        $dl = deg2rad($lon2 - $lon1);
        $y = sin($dl) * cos($phi2);
        $x = cos($phi1) * sin($phi2) - sin($phi1) * cos($phi2) * cos($dl);
        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }

    private static function angleDiff(float $a, float $b): float
    {
        $d = fmod($a - $b + 540.0, 360.0) - 180.0;
        return $d;
    }

    /** @param list<array{0:float,1:float}> $line */
    private static function subline(array $line, float $from, float $to): array
    {
        $reverse = $to < $from;
        $start = min($from, $to);
        $end = max($from, $to);
        $out = [];
        $offset = 0.0;
        $pointAt = static function (array $a, array $b, float $t): array {
            return [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t];
        };
        for ($i = 0; $i + 1 < count($line); $i++) {
            $aLat = $line[$i][1]; $bLat = $line[$i + 1][1];
            $segLength = StepsGeolocationQuality::haversineMeters($aLat, $line[$i][0], $bLat, $line[$i + 1][0]);
            $segStart = $offset;
            $segEnd = $offset + $segLength;
            if ($segEnd >= $start && $segStart <= $end && $segLength > 1e-9) {
                $t0 = max(0.0, ($start - $segStart) / $segLength);
                $t1 = min(1.0, ($end - $segStart) / $segLength);
                if ($t0 <= $t1) {
                    if ($out === []) $out[] = $pointAt($line[$i], $line[$i + 1], $t0);
                    if ($t1 > $t0) $out[] = $pointAt($line[$i], $line[$i + 1], $t1);
                }
            }
            $offset = $segEnd;
        }
        if (count($out) < 2) return [];
        if ($reverse) $out = array_reverse($out);
        return $out;
    }
}
