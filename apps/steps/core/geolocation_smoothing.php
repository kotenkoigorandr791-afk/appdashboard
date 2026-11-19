<?php
/**
 * Zone smoothing: replace a dense cloud of samples with the few routes that
 * actually crossed the place.
 *
 * A shopping centre, a station hall or a market can produce thousands of
 * jittered samples that carry almost no information. Smoothing keeps the raw
 * points canonical (statistics, exports and privacy rules still see them) and
 * derives one simplified line per pass through the selected rectangle. The map
 * then draws those lines and hides the raw points inside the zone.
 */
require_once __DIR__ . '/geolocation_quality.php';

final class StepsGeolocationSmoothing
{
    public const DEFAULT_TOLERANCE_M = 25.0;
    public const MIN_TOLERANCE_M = 5.0;
    public const MAX_TOLERANCE_M = 500.0;
    public const DEFAULT_GAP_S = 600;
    public const MAX_ZONE_POINTS = 20000;
    public const LAYER = 'smooth';

    public static function tolerance(float $value): float
    {
        if ($value <= 0) return self::DEFAULT_TOLERANCE_M;
        return max(self::MIN_TOLERANCE_M, min(self::MAX_TOLERANCE_M, $value));
    }

    public static function gapSeconds(int $value): int
    {
        if ($value <= 0) return self::DEFAULT_GAP_S;
        return max(60, min(86400, $value));
    }

    public static function uuid(): string
    {
        $b = bin2hex(random_bytes(16));
        return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
    }

    /**
     * Split one ordered track into separate passes when the sampler goes quiet.
     * Each pass is one route through the selected area.
     *
     * @param list<array{source_at:string,latitude:float|string,longitude:float|string}> $points
     * @return list<list<array{source_at:string,latitude:float|string,longitude:float|string}>>
     */
    public static function passes(array $points, int $gapS = self::DEFAULT_GAP_S): array
    {
        $passes = [];
        $current = [];
        $previousAt = null;
        foreach ($points as $point) {
            $at = strtotime((string)($point['source_at'] ?? ''));
            if ($at === false) continue;
            if ($previousAt !== null && $at - $previousAt > $gapS && $current !== []) {
                $passes[] = $current;
                $current = [];
            }
            $current[] = $point;
            $previousAt = $at;
        }
        if ($current !== []) $passes[] = $current;
        return $passes;
    }

    /**
     * Douglas-Peucker simplification in metres, using a local equirectangular
     * projection. Keeps the visible shape of a pass while dropping the jitter.
     *
     * @param list<array{0:float,1:float}> $coordinates lon/lat pairs
     * @return list<array{0:float,1:float}>
     */
    public static function simplify(array $coordinates, float $toleranceM): array
    {
        $count = count($coordinates);
        if ($count <= 2 || $toleranceM <= 0) return array_values($coordinates);
        $lat0 = 0.0;
        foreach ($coordinates as $coordinate) $lat0 += (float)$coordinate[1];
        $lat0 /= $count;
        $latScale = 111320.0;
        $lonScale = 111320.0 * max(0.05, cos(deg2rad($lat0)));
        $projected = [];
        foreach ($coordinates as $coordinate) {
            $projected[] = [(float)$coordinate[0] * $lonScale, (float)$coordinate[1] * $latScale];
        }
        $keep = array_fill(0, $count, false);
        $keep[0] = true;
        $keep[$count - 1] = true;
        $stack = [[0, $count - 1]];
        while ($stack !== []) {
            [$first, $last] = array_pop($stack);
            if ($last <= $first + 1) continue;
            $ax = $projected[$first][0]; $ay = $projected[$first][1];
            $bx = $projected[$last][0]; $by = $projected[$last][1];
            $dx = $bx - $ax; $dy = $by - $ay;
            $length = sqrt($dx * $dx + $dy * $dy);
            $worst = 0.0;
            $worstIndex = null;
            for ($i = $first + 1; $i < $last; $i++) {
                $px = $projected[$i][0]; $py = $projected[$i][1];
                if ($length > 0) {
                    $t = max(0.0, min(1.0, (($px - $ax) * $dx + ($py - $ay) * $dy) / ($length * $length)));
                    $distance = sqrt(($ax + $t * $dx - $px) ** 2 + ($ay + $t * $dy - $py) ** 2);
                } else {
                    $distance = sqrt(($px - $ax) ** 2 + ($py - $ay) ** 2);
                }
                if ($distance > $worst) { $worst = $distance; $worstIndex = $i; }
            }
            if ($worstIndex !== null && $worst > $toleranceM) {
                $keep[$worstIndex] = true;
                $stack[] = [$first, $worstIndex];
                $stack[] = [$worstIndex, $last];
            }
        }
        $out = [];
        foreach ($coordinates as $index => $coordinate) {
            if ($keep[$index]) $out[] = [(float)$coordinate[0], (float)$coordinate[1]];
        }
        return count($out) >= 2 ? $out : array_values([$coordinates[0], $coordinates[count($coordinates) - 1]]);
    }

    /**
     * Derive one simplified route per pass through the rectangle and replace
     * previous zones that overlap it.
     *
     * @param array{minLon:float,minLat:float,maxLon:float,maxLat:float} $bbox
     * @return array{zone_id:string,tolerance_m:float,paths:int,points:int,passes:list<array<string,mixed>>}
     */
    public static function smooth(PDO $pdo, string $owner, array $bbox, string $from, string $to, float $toleranceM = 0.0, int $gapS = 0): array
    {
        $owner = strtolower(trim($owner));
        $toleranceM = self::tolerance($toleranceM);
        $gapS = self::gapSeconds($gapS);
        $stmt = $pdo->prepare(
            "SELECT source_at, ST_Y(position) AS latitude, ST_X(position) AS longitude
               FROM geo_location_points
              WHERE user_email = :owner AND quality_state NOT IN ('rejected','excluded_by_user','private_redacted')
                AND position && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
                AND source_at BETWEEN :from AND :to
           ORDER BY source_at ASC, id ASC
              LIMIT " . self::MAX_ZONE_POINTS
        );
        $stmt->execute([
            ':owner' => $owner,
            ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'],
            ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat'],
            ':from' => $from, ':to' => $to,
        ]);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($points === []) {
            return ['zone_id' => '', 'tolerance_m' => $toleranceM, 'paths' => 0, 'points' => 0, 'passes' => []];
        }

        $passes = self::passes($points, $gapS);
        $zoneId = self::uuid();
        $area = sprintf('POLYGON((%F %F,%F %F,%F %F,%F %F,%F %F))',
            $bbox['minLon'], $bbox['minLat'],
            $bbox['maxLon'], $bbox['minLat'],
            $bbox['maxLon'], $bbox['maxLat'],
            $bbox['minLon'], $bbox['maxLat'],
            $bbox['minLon'], $bbox['minLat']
        );

        $pdo->beginTransaction();
        try {
            // A new smoothing of the same area supersedes older zones there.
            $pdo->prepare('DELETE FROM geo_smooth_zones WHERE user_email = :owner AND ST_Intersects(area, ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326))')
                ->execute([':owner' => $owner, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat']]);
            $pdo->prepare(
                "INSERT INTO geo_smooth_zones(id, user_email, area, tolerance_m, min_gap_s, path_count, point_count)
                 VALUES(:id, :owner, ST_SetSRID(ST_GeomFromText(:area), 4326), :tolerance, :gap, :paths, :points)"
            )->execute([':id' => $zoneId, ':owner' => $owner, ':area' => $area, ':tolerance' => $toleranceM, ':gap' => $gapS, ':paths' => 0, ':points' => count($points)]);
            $insert = $pdo->prepare(
                "INSERT INTO geo_smooth_paths(id, zone_id, user_email, started_at, ended_at, point_count, geometry)
                 VALUES(:id, :zone, :owner, :from, :to, :points, ST_SetSRID(ST_GeomFromGeoJSON(:geometry), 4326))"
            );
            $paths = 0;
            $summary = [];
            foreach ($passes as $pass) {
                $coordinates = [];
                foreach ($pass as $point) {
                    $coordinates[] = [(float)$point['longitude'], (float)$point['latitude']];
                }
                if (count($coordinates) < 2) continue;
                $simplified = self::simplify($coordinates, $toleranceM);
                if (count($simplified) < 2) continue;
                $distance = 0.0;
                for ($i = 1; $i < count($coordinates); $i++) {
                    $distance += StepsGeolocationQuality::haversineMeters(
                        (float)$coordinates[$i - 1][1], (float)$coordinates[$i - 1][0],
                        (float)$coordinates[$i][1], (float)$coordinates[$i][0]
                    );
                }
                $insert->execute([
                    ':id' => self::uuid(), ':zone' => $zoneId, ':owner' => $owner,
                    ':from' => (string)$pass[0]['source_at'], ':to' => (string)$pass[count($pass) - 1]['source_at'],
                    ':points' => count($pass),
                    ':geometry' => json_encode(['type' => 'LineString', 'coordinates' => $simplified], JSON_UNESCAPED_SLASHES),
                ]);
                $paths++;
                $summary[] = [
                    'started_at' => (string)$pass[0]['source_at'],
                    'ended_at' => (string)$pass[count($pass) - 1]['source_at'],
                    'raw_points' => count($pass),
                    'kept_points' => count($simplified),
                    'distance_m' => round($distance, 1),
                ];
            }
            $pdo->prepare('UPDATE geo_smooth_zones SET path_count = :paths, updated_at = now() WHERE id = :id')
                ->execute([':paths' => $paths, ':id' => $zoneId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return ['zone_id' => $zoneId, 'tolerance_m' => $toleranceM, 'paths' => $paths, 'points' => count($points), 'passes' => $summary];
    }

    /**
     * Remove every zone that overlaps a rectangle.
     *
     * @param array{minLon:float,minLat:float,maxLon:float,maxLat:float} $bbox
     */
    public static function remove(PDO $pdo, string $owner, array $bbox): int
    {
        $owner = strtolower(trim($owner));
        $stmt = $pdo->prepare('DELETE FROM geo_smooth_zones WHERE user_email = :owner AND ST_Intersects(area, ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326))');
        $stmt->execute([':owner' => $owner, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat']]);
        return $stmt->rowCount();
    }

    /**
     * Zones and their derived routes for one viewport.
     *
     * @param array{minLon:float,minLat:float,maxLon:float,maxLat:float} $bbox
     * @return list<array<string,mixed>>
     */
    public static function viewport(PDO $pdo, string $owner, array $bbox, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            "SELECT z.id AS zone_id, z.tolerance_m, z.path_count,
                    p.id, p.started_at, p.ended_at, p.point_count,
                    ST_AsGeoJSON(p.geometry) AS geojson,
                    ST_AsGeoJSON(z.area) AS area
               FROM geo_smooth_zones z
               JOIN geo_smooth_paths p ON p.zone_id = z.id
              WHERE z.user_email = :owner
                AND z.area && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
                AND p.started_at <= :to AND p.ended_at >= :from
           ORDER BY p.started_at ASC LIMIT 500"
        );
        $stmt->execute([':owner' => $owner, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat'], ':from' => $from, ':to' => $to]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $geometry = json_decode((string)$row['geojson'], true);
            if (!is_array($geometry)) continue;
            $rows[] = [
                'type' => 'Feature',
                'geometry' => $geometry,
                'properties' => [
                    'layer' => self::LAYER,
                    'id' => (string)$row['id'],
                    'zone_id' => (string)$row['zone_id'],
                    'started_at' => $row['started_at'],
                    'ended_at' => $row['ended_at'],
                    'point_count' => (int)$row['point_count'],
                    'tolerance_m' => (float)$row['tolerance_m'],
                    'area' => json_decode((string)$row['area'], true),
                ],
            ];
        }
        return $rows;
    }

    /**
     * Fingerprint of the zones a viewer is about to draw. The map chunk itself
     * is cached raw, so this value has to reach the response validator: without
     * it a smoothing pass would answer 304 and the client would keep drawing the
     * raw cloud it already had.
     *
     * @param list<array<string,mixed>> $zones
     */
    public static function signature(array $zones): string
    {
        $parts = [];
        foreach ($zones as $zone) {
            $properties = $zone['properties'] ?? [];
            $parts[] = ($properties['zone_id'] ?? '') . ':' . ($properties['id'] ?? '')
                . ':' . ($properties['point_count'] ?? 0) . ':' . ($properties['tolerance_m'] ?? 0);
        }
        sort($parts);
        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }

    /**
     * Drop raw point markers that fall inside a smoothed zone and add the
     * derived routes. Called after the ordinary viewport query, so the cached
     * chunk stays raw and each viewer applies its own zones.
     *
     * @param array{type:string,features:list<array<string,mixed>>,meta:array<string,mixed>} $result
     * @param array{minLon:float,minLat:float,maxLon:float,maxLat:float} $bbox
     * @return array{type:string,features:list<array<string,mixed>>,meta:array<string,mixed>}
     */
    public static function applyToViewport(array $result, array $zones): array
    {
        if ($zones === []) return $result;
        $polygons = [];
        foreach ($zones as $zone) {
            $area = $zone['properties']['area'] ?? null;
            if (is_array($area)) $polygons[] = $area;
        }
        if ($polygons === []) return $result;
        $inside = static function (float $lon, float $lat) use ($polygons): bool {
            foreach ($polygons as $polygon) {
                $rings = ($polygon['type'] ?? '') === 'Polygon' ? [$polygon['coordinates'][0] ?? []] : [];
                foreach ($rings as $ring) {
                    $isInside = false;
                    $n = count($ring);
                    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                        $xi = (float)($ring[$i][0] ?? 0); $yi = (float)($ring[$i][1] ?? 0);
                        $xj = (float)($ring[$j][0] ?? 0); $yj = (float)($ring[$j][1] ?? 0);
                        if ((($yi > $lat) !== ($yj > $lat)) && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                            $isInside = !$isInside;
                        }
                    }
                    if ($isInside) return true;
                }
            }
            return false;
        };
        $features = [];
        $hidden = 0;
        foreach ($result['features'] as $feature) {
            $layer = (string)($feature['properties']['layer'] ?? '');
            $geometry = $feature['geometry'] ?? [];
            if (in_array($layer, ['point', 'point_cluster'], true) && ($geometry['type'] ?? '') === 'Point') {
                $coordinates = $geometry['coordinates'] ?? [0, 0];
                if ($inside((float)($coordinates[0] ?? 0), (float)($coordinates[1] ?? 0))) {
                    $hidden++;
                    continue;
                }
            }
            $features[] = $feature;
        }
        foreach ($zones as $zone) $features[] = $zone;
        $result['features'] = $features;
        $result['meta']['counts']['smooth'] = count($zones);
        $result['meta']['counts']['smoothed_points_hidden'] = $hidden;
        return $result;
    }
}
