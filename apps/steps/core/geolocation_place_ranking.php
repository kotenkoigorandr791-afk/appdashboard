<?php
require_once __DIR__ . '/geolocation_place_config.php';
require_once __DIR__ . '/geolocation_place_profiles.php';

final class StepsGeolocationPlaceRanking
{
    private const TRANSPORT_KEYS = ['railway', 'aeroway', 'public_transport'];
    private const TRANSPORT_AMENITIES = ['bus_station', 'taxi', 'ferry_terminal', 'parking', 'fuel', 'charging_station'];
    private const AREA_KEYS = ['landuse', 'natural'];
    private const BUSINESS_KEYS = ['shop', 'amenity', 'office', 'healthcare', 'tourism', 'leisure', 'craft', 'man_made', 'industrial', 'military'];

    private static function meters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dy = ($lat2 - $lat1) * 111320.0;
        $dx = ($lon2 - $lon1) * 111320.0 * cos(deg2rad(($lat1 + $lat2) / 2));
        return sqrt($dx * $dx + $dy * $dy);
    }

    /** @param list<array{0:float,1:float}> $ring [lon,lat] */
    public static function pointInRing(float $lat, float $lon, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $lat) !== ($yj > $lat)) && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) $inside = !$inside;
        }
        return $inside;
    }

    private static function segmentDistance(float $lat, float $lon, array $a, array $b): float
    {
        $cos = cos(deg2rad($lat));
        $ax = ($a[0] - $lon) * 111320.0 * $cos; $ay = ($a[1] - $lat) * 111320.0;
        $bx = ($b[0] - $lon) * 111320.0 * $cos; $by = ($b[1] - $lat) * 111320.0;
        $dx = $bx - $ax; $dy = $by - $ay;
        $len2 = $dx * $dx + $dy * $dy;
        $t = $len2 > 0 ? max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $len2)) : 0.0;
        return sqrt(($ax + $t * $dx) ** 2 + ($ay + $t * $dy) ** 2);
    }

    /** @return array{0:float,1:bool} distance in metres (0 when inside) and whether inside */
    public static function distanceTo(array $candidate, float $lat, float $lon): array
    {
        $g = $candidate['geometry'] ?? null;
        if (is_array($g) && ($g['type'] ?? '') === 'Polygon' && !empty($g['coordinates'][0])) {
            $ring = $g['coordinates'][0];
            if (self::pointInRing($lat, $lon, $ring)) return [0.0, true];
            $min = INF;
            for ($i = 0, $n = count($ring) - 1; $i < $n; $i++) $min = min($min, self::segmentDistance($lat, $lon, $ring[$i], $ring[$i + 1]));
            return [$min === INF ? self::meters($lat, $lon, $candidate['lat'], $candidate['lon']) : $min, false];
        }
        if (is_array($g) && ($g['type'] ?? '') === 'LineString' && count($g['coordinates'] ?? []) >= 2) {
            $c = $g['coordinates'];
            $min = INF;
            for ($i = 0, $n = count($c) - 1; $i < $n; $i++) $min = min($min, self::segmentDistance($lat, $lon, $c[$i], $c[$i + 1]));
            return [$min, false];
        }
        return [self::meters($lat, $lon, $candidate['lat'], $candidate['lon']), false];
    }

    public static function matchType(array $c): string
    {
        $tags = $c['tags'] ?? [];
        $name = ($c['name'] ?? '') !== '';
        foreach (self::TRANSPORT_KEYS as $k) if (isset($tags[$k])) return 'transport';
        if (isset($tags['amenity']) && in_array($tags['amenity'], self::TRANSPORT_AMENITIES, true)) return 'transport';
        if (($tags['leisure'] ?? '') === 'park' || ($tags['leisure'] ?? '') === 'garden' || ($tags['leisure'] ?? '') === 'playground' || ($tags['amenity'] ?? '') === 'park') return 'park';
        foreach (self::BUSINESS_KEYS as $k) if (isset($tags[$k]) && $name) return 'named_business';
        if ($name && ($c['category'] ?? '') !== '' && !isset($tags['building'])) return 'named_business';
        if (isset($tags['building'])) return 'building';
        foreach (self::AREA_KEYS as $k) if (isset($tags[$k])) return 'area';
        return 'unknown';
    }

    private static function categoryScore(string $type): float
    {
        return ['named_business' => 0.9, 'building' => 0.65, 'transport' => 0.8, 'park' => 0.55, 'area' => 0.3, 'unknown' => 0.4][$type] ?? 0.4;
    }

    /**
     * @param array<string,mixed> $stop {lat,lon,radius_m,duration_s}
     * @param list<array{lat:float,lon:float,accuracy_m:?float}> $observations
     * @param list<array<string,mixed>> $candidates normalized OSM elements
     * @param array<string,bool> $blocked "type:id" => true
     * @return array{selected:?array<string,mixed>,confidence:float,status:string,candidates:list<array<string,mixed>>,ambiguous:bool}
     */
    public static function rank(array $stop, array $observations, array $candidates, string $profile, ?array $weights = null, ?array $thresholds = null, array $blocked = [], ?float $searchRadius = null): array
    {
        $w = $weights ?? StepsGeolocationPlaceConfig::weights();
        $t = $thresholds ?? StepsGeolocationPlaceConfig::thresholds();
        $searchRadius = $searchRadius ?? (float)StepsGeolocationPlaceConfig::osm()['search_radius_m'];
        $lat = (float)$stop['lat'];
        $lon = (float)$stop['lon'];
        $stopRadius = max(10.0, (float)($stop['radius_m'] ?? 50.0));
        $duration = (float)($stop['duration_s'] ?? 0);

        $accs = [];
        $spreadSq = 0.0;
        foreach ($observations as $o) {
            if (isset($o['accuracy_m']) && $o['accuracy_m'] !== null) $accs[] = (float)$o['accuracy_m'];
            $spreadSq += self::meters($lat, $lon, (float)$o['lat'], (float)$o['lon']) ** 2;
        }
        sort($accs);
        $medianAcc = $accs === [] ? 30.0 : $accs[intdiv(count($accs), 2)];
        $spread = $observations === [] ? $stopRadius : sqrt($spreadSq / count($observations));
        $accuracyScore = max(0.0, min(1.0, 1.0 - ($medianAcc - 5.0) / 150.0));
        $durationScore = min(1.0, $duration / 1800.0);
        $distributionScore = 1.0 / (1.0 + ($spread / $stopRadius) ** 2);
        $inside = static function (array $c) use ($observations, $medianAcc): float {
            if ($observations === []) return 0.0;
            $hit = 0.0;
            $near = max(25.0, min(60.0, $medianAcc));
            foreach ($observations as $o) {
                [$d, $in] = self::distanceTo($c, (float)$o['lat'], (float)$o['lon']);
                if ($in) $hit += 1.0;
                elseif ($d <= $near) $hit += 0.6 * (1.0 - $d / ($near * 1.5));
            }
            return min(1.0, $hit / count($observations));
        };

        $scored = [];
        foreach ($candidates as $c) {
            if (isset($blocked[$c['osm_type'] . ':' . $c['osm_id']])) continue;
            [$d, $contains] = self::distanceTo($c, $lat, $lon);
            if ($d > $searchRadius * 1.5 && !$contains) continue;
            $effective = max(0.0, $d - min($medianAcc, 50.0));
            $type = self::matchType($c);
            $parts = [
                'distance' => 1.0 / (1.0 + ($effective / 25.0) ** 2),
                'inside' => $inside($c),
                'accuracy' => $accuracyScore,
                'duration' => $durationScore,
                'distribution' => $distributionScore,
                'category' => self::categoryScore($type),
                'profile' => StepsGeolocationPlaceProfiles::relevance($profile, (array)($c['tags'] ?? [])),
                'name' => ($c['name'] ?? '') !== '' ? 1.0 : 0.3,
            ];
            $sum = 0.0; $wsum = 0.0;
            foreach ($parts as $k => $v) { $sum += ($w[$k] ?? 0.0) * $v; $wsum += ($w[$k] ?? 0.0); }
            $scored[] = [
                'element' => $c, 'score' => ($wsum > 0 ? $sum / $wsum : 0.0) * (0.35 + 0.65 * max($parts['distance'], $parts['inside'])), 'parts' => $parts,
                'distance_m' => round($d, 1), 'contains_center' => $contains, 'match_type' => $type, 'ambiguous' => false,
            ];
        }
        usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        $buildings = [];
        foreach ($scored as $i => $s) {
            if ($s['match_type'] === 'building' && ($s['element']['geometry']['type'] ?? '') === 'Polygon') $buildings[$i] = $s['element']['geometry']['coordinates'][0];
        }
        foreach ($buildings as $bi => $ring) {
            $inner = [];
            foreach ($scored as $i => $s) {
                if ($i === $bi || $s['match_type'] !== 'named_business' || ($s['element']['name'] ?? '') === '') continue;
                if (self::pointInRing((float)$s['element']['lat'], (float)$s['element']['lon'], $ring)) $inner[] = $i;
            }
            if (count($inner) >= 2 && $scored[$bi]['contains_center']) {
                $scored[$bi]['ambiguous'] = true;
                foreach ($inner as $i) $scored[$i]['ambiguous'] = true;
            }
        }

        $max = (int)$t['max_candidates'];
        $scored = array_slice($scored, 0, $max);
        $out = [];
        foreach ($scored as $rank => $s) {
            $margin = isset($scored[$rank + 1]) ? $s['score'] - $scored[$rank + 1]['score'] : 1.0;
            $close = 0;
            foreach ($scored as $other) if ($other !== $s && abs($other['score'] - $s['score']) < 0.05 && $other['match_type'] !== 'area') $close++;
            $confidence = $s['score'] * ($margin < 0.05 ? 0.9 : 1.0) * (1.0 - 0.06 * min(5, $close));
            if ($s['ambiguous']) $confidence = min($confidence, (float)$t['ambiguous_cap']);
            $out[] = $s + ['rank' => $rank + 1, 'confidence' => round(max(0.0, min(1.0, $confidence)), 3)];
        }
        $best = $out[0] ?? null;
        $confidence = $best['confidence'] ?? 0.0;
        $selected = $best !== null && $confidence >= (float)$t['min_confidence'] ? $best : null;
        $status = $selected === null ? 'unidentified' : ($confidence >= (float)$t['confirmed_confidence'] ? 'confirmed' : 'provisional');
        return ['selected' => $selected, 'confidence' => round($confidence, 3), 'status' => $status, 'candidates' => $out, 'ambiguous' => $best !== null && $best['ambiguous']];
    }
}
