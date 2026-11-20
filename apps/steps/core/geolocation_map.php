<?php
/**
 * Map rendering layer (task 15).
 *
 * Read-only viewport queries: bbox + time + owner predicates, server-side
 * aggregation/simplification by zoom and cursor pagination, so a large history
 * never ships as raw GeoJSON. Tile providers are presentation settings; tiles
 * stay external HTTP resources and no new map service is added.
 */
final class StepsGeolocationMap
{
    public const RAW_ZOOM = 13;
    // Only states that hide a point from every view are filtered out. Pending
    // and flagged points are still real records: a large import can stay in the
    // quality queue for a while and must not disappear from the map meanwhile.
    private const VISIBLE_QUALITY = "quality_state NOT IN ('rejected','excluded_by_user','private_redacted')";
    public const MAX_POINTS = 500;
    public const MAX_SPAN_DEG = 90.0;

    public const PROVIDERS = [
        'osm' => [
            'name' => 'OpenStreetMap',
            'url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => '© OpenStreetMap contributors',
            'max_zoom' => 19,
        ],
        'carto_light' => [
            'name' => 'CARTO Positron',
            'url' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
            'attribution' => '© OpenStreetMap contributors © CARTO',
            'max_zoom' => 20,
        ],
        'carto_dark' => [
            'name' => 'CARTO Dark Matter',
            'url' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
            'attribution' => '© OpenStreetMap contributors © CARTO',
            'max_zoom' => 20,
        ],
        'opentopomap' => [
            'name' => 'OpenTopoMap',
            'url' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
            'attribution' => '© OpenStreetMap contributors, SRTM | © OpenTopoMap',
            'max_zoom' => 17,
        ],
        'esri_dark' => [
            'name' => 'Esri Dark Gray Canvas',
            'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}',
            'attribution' => 'Tiles © Esri',
            'max_zoom' => 16,
        ],
        'esri_imagery' => [
            'name' => 'Esri World Imagery',
            'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            'attribution' => 'Tiles © Esri',
            'max_zoom' => 19,
        ],
        'esri_street' => [
            'name' => 'Esri World Street Map',
            'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
            'attribution' => 'Tiles © Esri',
            'max_zoom' => 19,
        ],
    ];

    public const DEFAULT_PROVIDER = 'esri_dark';

    public const DEFAULT_LAYERS = ['trips', 'stops', 'places', 'visits'];

    public static function providers(): array
    {
        return self::PROVIDERS;
    }

    public static function provider(string $key): array
    {
        return self::PROVIDERS[$key] ?? self::PROVIDERS['osm'];
    }

    public static function layers(string $raw): array
    {
        $allowed = array_merge(self::DEFAULT_LAYERS, ['points', 'heatmap', 'explored', 'photos', 'speed', 'improved']);
        $requested = array_filter(array_map('trim', explode(',', strtolower($raw))));
        $layers = array_values(array_intersect($requested, $allowed));
        return $layers === [] ? self::DEFAULT_LAYERS : $layers;
    }

    /** @return array{minLon:float,minLat:float,maxLon:float,maxLat:float}|null */
    public static function parseBbox(string $raw): ?array
    {
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) !== 4) return null;
        foreach ($parts as $part) if (!is_numeric($part)) return null;
        [$minLon, $minLat, $maxLon, $maxLat] = array_map('floatval', $parts);
        if ($minLat < -90 || $maxLat > 90 || $minLon < -180 || $maxLon > 180) return null;
        if ($minLat >= $maxLat || $minLon >= $maxLon) return null;
        if (($maxLon - $minLon) > self::MAX_SPAN_DEG || ($maxLat - $minLat) > self::MAX_SPAN_DEG) return null;
        return ['minLon' => $minLon, 'minLat' => $minLat, 'maxLon' => $maxLon, 'maxLat' => $maxLat];
    }

    /** Data extent for an owner, used when the client has no viewport yet. */
    public static function dataBbox(PDO $pdo, string $owner, string $from, string $to): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT ST_XMin(extent) AS min_lon, ST_YMin(extent) AS min_lat, ST_XMax(extent) AS max_lon, ST_YMax(extent) AS max_lat
               FROM (SELECT ST_Extent(position) AS extent FROM geo_location_points
                      WHERE user_email = :owner AND source_at BETWEEN :from AND :to AND " . self::VISIBLE_QUALITY . ") s
              WHERE extent IS NOT NULL"
        );
        $stmt->execute([':owner' => $owner, ':from' => $from, ':to' => $to]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $pad = 0.01;
        return [
            'minLon' => max(-180.0, (float)$row['min_lon'] - $pad),
            'minLat' => max(-90.0, (float)$row['min_lat'] - $pad),
            'maxLon' => min(180.0, (float)$row['max_lon'] + $pad),
            'maxLat' => min(90.0, (float)$row['max_lat'] + $pad),
        ];
    }

    public static function precisionForZoom(int $zoom): int
    {
        if ($zoom >= 16) return 5;
        if ($zoom >= 12) return 4;
        if ($zoom >= 8) return 3;
        return 2;
    }

    public static function cellForZoom(int $zoom): float
    {
        $cell = 360.0 / (2 ** max(0, min(20, $zoom))) / 20;
        return max(0.0005, round($cell, 6));
    }

    public static function toleranceForZoom(int $zoom): float
    {
        return max(0.00002, self::cellForZoom($zoom) / 50);
    }

    public static function heatCellForZoom(int $zoom): float
    {
        return max(0.002, round(self::cellForZoom($zoom) * 3, 6));
    }

    /**
     * A viewer may read another account's data only through an unrevoked grant.
     */
    public static function authorize(PDO $pdo, string $viewer, ?string $target, ?string $from = null, ?string $to = null): array
    {
        require_once __DIR__ . '/geolocation_permissions.php';
        // Single authorization path for every family read: consent grants,
        // scoped windows, precision, pause/revocation and fail-closed denial.
        $scope = StepsGeolocationPermissions::authorize($pdo, $viewer, $target, $from, $to);
        return ['owner' => $scope['owner'], 'error' => $scope['error'], 'scope' => $scope];
    }

    public static function preferences(PDO $pdo, string $owner): array
    {
        $stmt = $pdo->prepare('SELECT provider, layers_json, updated_at FROM geo_map_preferences WHERE user_email = :owner');
        $stmt->execute([':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'provider' => $row['provider'] ?? self::DEFAULT_PROVIDER,
            'layers' => isset($row['layers_json']) ? (json_decode((string)$row['layers_json'], true) ?: self::DEFAULT_LAYERS) : self::DEFAULT_LAYERS,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    public static function savePreferences(PDO $pdo, string $owner, ?string $provider, ?array $layers): array
    {
        $provider = isset(self::PROVIDERS[$provider ?? '']) ? $provider : self::preferences($pdo, $owner)['provider'];
        $layers = $layers === null ? self::preferences($pdo, $owner)['layers'] : self::layers(implode(',', $layers));
        $stmt = $pdo->prepare(
            "INSERT INTO geo_map_preferences(user_email, provider, layers_json, updated_at)
             VALUES(:owner, :provider, :layers, now())
             ON CONFLICT (user_email) DO UPDATE SET provider = excluded.provider, layers_json = excluded.layers_json, updated_at = now()"
        );
        $stmt->execute([':owner' => $owner, ':provider' => $provider, ':layers' => json_encode($layers)]);
        return self::preferences($pdo, $owner);
    }

    /**
     * @param array{minLon:float,minLat:float,maxLon:float,maxLat:float} $bbox
     * @return array{features:list<array<string,mixed>>,meta:array<string,mixed>}
     */
    public static function features(PDO $pdo, string $owner, array $bbox, array $opts = []): array
    {
        $zoom = max(1, min(20, (int)($opts['zoom'] ?? 12)));
        $from = (string)($opts['from'] ?? gmdate('c', time() - 30 * 86400));
        $to = (string)($opts['to'] ?? gmdate('c'));
        $layers = $opts['layers'] ?? self::DEFAULT_LAYERS;
        $precision = self::precisionForZoom($zoom);
        $cell = self::cellForZoom($zoom);
        $tolerance = self::toleranceForZoom($zoom);
        $limit = max(1, min(self::MAX_POINTS, (int)($opts['limit'] ?? self::MAX_POINTS)));
        $device = trim((string)($opts['device_id'] ?? ''));
        $trip = trim((string)($opts['trip_id'] ?? ''));
        $place = trim((string)($opts['place_id'] ?? ''));
        $cursorAt = (string)($opts['cursor_at'] ?? '');
        $cursorId = (string)($opts['cursor_id'] ?? '');

        $where = 'user_email = :owner AND position && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326) AND source_at BETWEEN :from AND :to';
        $params = [
            ':owner' => $owner,
            ':minLon' => $bbox['minLon'],
            ':minLat' => $bbox['minLat'],
            ':maxLon' => $bbox['maxLon'],
            ':maxLat' => $bbox['maxLat'],
            ':from' => $from,
            ':to' => $to,
        ];
        if ($device !== '') {
            $where .= ' AND device_id = :device';
            $params[':device'] = $device;
        }

        $features = [];
        $meta = ['zoom' => $zoom, 'precision' => $precision, 'cell' => $cell, 'counts' => [], 'truncated' => false, 'next_cursor' => null];

        $pointsFree = array_values(array_diff($layers, ['points']));
        $wantsPoints = in_array('points', $layers, true);

        if (in_array('trips', $layers, true)) {
            $sql = "SELECT id, started_at, ended_at, distance_m, title, boundary_reason, geometry_visible,
                           ST_AsGeoJSON(ST_SimplifyPreserveTopology(geometry, $tolerance)) AS geojson
                      FROM geo_trips
                     WHERE user_email = :owner AND deleted_at IS NULL AND geometry IS NOT NULL
                       AND geometry && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
                       AND started_at <= :to AND ended_at >= :from"
                . ($trip !== '' ? ' AND id = :trip' : '')
                . ' ORDER BY started_at DESC LIMIT 200';
            $tripParams = [':owner' => $owner, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat'], ':from' => $from, ':to' => $to];
            if ($trip !== '') $tripParams[':trip'] = $trip;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($tripParams);
            $tripRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tripRows as $row) {
                if (in_array($row['geometry_visible'], [false, 0, '0', 'f', 'false'], true)) continue;
                $geometry = json_decode((string)$row['geojson'], true);
                if (!is_array($geometry)) continue;
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $geometry,
                    'properties' => [
                        'layer' => 'trip',
                        'id' => (string)$row['id'],
                        'title' => (string)$row['title'],
                        'started_at' => $row['started_at'],
                        'ended_at' => $row['ended_at'],
                        'distance_m' => (float)$row['distance_m'],
                        'boundary_reason' => (string)$row['boundary_reason'],
                    ],
                ];
            }
            $meta['counts']['trips'] = count($tripRows);
            $tracks = self::trackFeatures($pdo, $owner, $bbox, $from, $to, $tolerance, $device);
            $storedVertices = 0;
            foreach ($features as $feature) {
                $coords = $feature['geometry']['coordinates'] ?? [];
                $storedVertices += is_array($coords) ? count($coords) : 0;
            }
            $trackVertices = 0;
            foreach ($tracks as $feature) {
                $coords = $feature['geometry']['coordinates'] ?? [];
                $trackVertices += is_array($coords) ? count($coords) : 0;
            }
            // Imported archives often have no trip rows, or only a short live trip.
            // The travelled line is the accepted points in time order.
            if ($trackVertices > $storedVertices) {
                $features = $tracks;
                $meta['counts']['trips'] = count($tracks);
                $meta['trips_source'] = 'points';
            } else {
                $meta['trips_source'] = 'trips';
            }
        }

        if (in_array('improved', $layers, true)) {
            $stmt = $pdo->prepare(
                "SELECT id, trip_id, mode, confidence, confidence_band, explanation,
                        ST_AsGeoJSON(ST_SimplifyPreserveTopology(matched_geometry, $tolerance)) AS geojson
                   FROM geo_matched_segments
                  WHERE user_email = :owner AND state = 'matched' AND matched_geometry IS NOT NULL
                    AND matched_geometry && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
                    AND started_at <= :to AND ended_at >= :from
               ORDER BY started_at DESC LIMIT 200"
            );
            $stmt->execute([':owner' => $owner, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat'], ':from' => $from, ':to' => $to]);
            $matchedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($matchedRows as $row) {
                $geometry = json_decode((string)$row['geojson'], true);
                if (!is_array($geometry)) continue;
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $geometry,
                    'properties' => [
                        'layer' => 'matched',
                        'id' => (string)$row['id'],
                        'trip_id' => (string)($row['trip_id'] ?? ''),
                        'mode' => (string)$row['mode'],
                        'confidence' => (float)$row['confidence'],
                        'confidence_band' => (string)$row['confidence_band'],
                        'explanation' => (string)$row['explanation'],
                    ],
                ];
            }
            $meta['counts']['improved'] = count($matchedRows);
        }

        if (in_array('stops', $layers, true)) {
            $aggregate = $zoom < self::RAW_ZOOM;
            if ($aggregate) {
                $sql = "SELECT round(ST_Y(ST_Centroid(ST_Collect(position)))::numeric, $precision) AS lat,
                               round(ST_X(ST_Centroid(ST_Collect(position)))::numeric, $precision) AS lon,
                               COUNT(*) AS total, MIN(started_at) AS first_seen, MAX(started_at) AS last_seen
                          FROM geo_stops
                         WHERE user_email = :owner AND deleted_at IS NULL
                           AND position && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
                           AND started_at BETWEEN :from AND :to
                      GROUP BY ST_SnapToGrid(position, $cell) ORDER BY total DESC LIMIT $limit";
            } else {
                $sql = "SELECT round(ST_Y(position)::numeric, $precision) AS lat,
                               round(ST_X(position)::numeric, $precision) AS lon,
                               1 AS total, started_at AS first_seen, started_at AS last_seen,
                               id, radius_m, confidence
                          FROM geo_stops
                         WHERE user_email = :owner AND deleted_at IS NULL
                           AND position && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
                           AND started_at BETWEEN :from AND :to
                      ORDER BY started_at DESC LIMIT $limit";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':owner' => $owner, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat'], ':from' => $from, ':to' => $to]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [(float)$row['lon'], (float)$row['lat']]],
                    'properties' => [
                        'layer' => 'stop',
                        'cluster' => (int)$row['total'] > 1,
                        'count' => (int)$row['total'],
                        'id' => $row['id'] ?? null,
                        'radius_m' => isset($row['radius_m']) ? (float)$row['radius_m'] : null,
                        'confidence' => isset($row['confidence']) ? (float)$row['confidence'] : null,
                        'started_at' => $row['first_seen'],
                    ],
                ];
            }
            $meta['counts']['stops'] = count($rows);
        }

        if (in_array('places', $layers, true) || in_array('visits', $layers, true)) {
            if (in_array('places', $layers, true)) {
                $stmt = $pdo->prepare(
                    "SELECT id, name, tag, color, radius_m,
                            round(ST_Y(position)::numeric, $precision) AS lat,
                            round(ST_X(position)::numeric, $precision) AS lon
                       FROM geo_places
                      WHERE user_email = :owner AND is_active AND deleted_at IS NULL
                        AND position && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)"
                    . ($place !== '' ? ' AND id = :place' : '')
                    . ' ORDER BY name ASC LIMIT 200'
                );
                $placeParams = [':owner' => $owner, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat']];
                if ($place !== '') $placeParams[':place'] = $place;
                $stmt->execute($placeParams);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => ['type' => 'Point', 'coordinates' => [(float)$row['lon'], (float)$row['lat']]],
                        'properties' => [
                            'layer' => 'place',
                            'id' => (string)$row['id'],
                            'name' => (string)$row['name'],
                            'tag' => (string)$row['tag'],
                            'color' => (string)$row['color'],
                            'radius_m' => (float)$row['radius_m'],
                        ],
                    ];
                }
                $meta['counts']['places'] = count($rows);
            }

            if (in_array('visits', $layers, true)) {
                $stmt = $pdo->prepare(
                    "SELECT v.id, v.arrived_at, v.departed_at, v.confidence, p.name AS place_name, p.tag AS place_tag,
                            round(ST_Y(s.position)::numeric, $precision) AS lat,
                            round(ST_X(s.position)::numeric, $precision) AS lon
                       FROM geo_visits v
                       LEFT JOIN geo_places p ON p.id = v.place_id
                       LEFT JOIN geo_stops s ON s.id = v.stop_id
                      WHERE v.user_email = :owner AND s.position IS NOT NULL
                        AND s.position && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
                        AND v.arrived_at BETWEEN :from AND :to
                   ORDER BY v.arrived_at DESC LIMIT 200"
                );
                $stmt->execute([':owner' => $owner, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat'], ':from' => $from, ':to' => $to]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => ['type' => 'Point', 'coordinates' => [(float)$row['lon'], (float)$row['lat']]],
                        'properties' => [
                            'layer' => 'visit',
                            'id' => (string)$row['id'],
                            'place_name' => (string)($row['place_name'] ?? ''),
                            'place_tag' => (string)($row['place_tag'] ?? ''),
                            'arrived_at' => $row['arrived_at'],
                            'departed_at' => $row['departed_at'],
                            'confidence' => $row['confidence'] !== null ? (float)$row['confidence'] : null,
                        ],
                    ];
                }
                $meta['counts']['visits'] = count($rows);
            }
        }

        if ($wantsPoints) {
            if ($zoom < self::RAW_ZOOM) {
                $stmt = $pdo->prepare(
                    "SELECT round(ST_Y(ST_Centroid(ST_Collect(position)))::numeric, $precision) AS lat,
                            round(ST_X(ST_Centroid(ST_Collect(position)))::numeric, $precision) AS lon,
                            COUNT(*) AS total
                       FROM geo_location_points
                      WHERE $where AND " . self::VISIBLE_QUALITY . "
                   GROUP BY ST_SnapToGrid(position, $cell)
                   ORDER BY total DESC LIMIT $limit"
                );
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => ['type' => 'Point', 'coordinates' => [(float)$row['lon'], (float)$row['lat']]],
                        'properties' => ['layer' => 'point_cluster', 'cluster' => true, 'count' => (int)$row['total']],
                    ];
                }
                $meta['counts']['point_clusters'] = count($rows);
                // The summary line reports raw points, so an aggregated view must
                // still count every point inside the clusters.
                $meta['counts']['points'] = array_sum(array_map(static fn(array $row): int => (int)$row['total'], $rows));
            } else {
                $sql = "SELECT id, source_at, accuracy_m, device_id, quality_state,
                               round(ST_Y(position)::numeric, $precision) AS lat,
                               round(ST_X(position)::numeric, $precision) AS lon
                          FROM geo_location_points
                         WHERE $where AND " . self::VISIBLE_QUALITY
                    . ($cursorAt !== '' && $cursorId !== '' ? ' AND (source_at, id) > (:cursor_at, :cursor_id)' : '')
                    . " ORDER BY source_at ASC, id ASC LIMIT " . ($limit + 1);
                $pointParams = $params;
                if ($cursorAt !== '' && $cursorId !== '') {
                    $pointParams[':cursor_at'] = $cursorAt;
                    $pointParams[':cursor_id'] = $cursorId;
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($pointParams);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($rows) > $limit) {
                    $rows = array_slice($rows, 0, $limit);
                    $meta['truncated'] = true;
                }
                foreach ($rows as $row) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => ['type' => 'Point', 'coordinates' => [(float)$row['lon'], (float)$row['lat']]],
                        'properties' => [
                            'layer' => 'point',
                            'id' => (string)$row['id'],
                            'device_id' => (string)($row['device_id'] ?? ''),
                            'source_at' => $row['source_at'],
                            'accuracy_m' => $row['accuracy_m'] !== null ? (float)$row['accuracy_m'] : null,
                            'quality_state' => (string)$row['quality_state'],
                        ],
                    ];
                }
                if ($rows !== []) {
                    $last = $rows[count($rows) - 1];
                    $meta['next_cursor'] = ['at' => $last['source_at'], 'id' => (string)$last['id']];
                }
                $meta['counts']['points'] = count($rows);
            }
        }

        if (in_array('heatmap', $layers, true)) {
            // Density grid: roughly one street width, so repeated passes on the
            // same street land in the same cell instead of neighbouring ones.
            $densityCell = max(0.0002, min(0.002, self::heatCellForZoom($zoom) / 4));
            // Heat is a density overlay: a street walked once is already drawn by
            // the trip track, so only cells passed by two or more separate routes
            // are returned. Otherwise heat just hides the underlying track.
            $heatMinWeight = 2;
            $segParams = [':owner' => $owner, ':from' => $from, ':to' => $to, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat']];
            if ($device !== '') $segParams[':device'] = $device;
            $pointSql = self::trackCte($device !== '') . ", segs AS (
                    SELECT grp, source_at, position,
                           lag(position) OVER (PARTITION BY grp ORDER BY source_at) AS prev_pos
                      FROM grouped
                ), lines AS (
                    SELECT grp, ST_MakeLine(prev_pos, position) AS line,
                           ST_SnapToGrid(ST_LineInterpolatePoint(ST_MakeLine(prev_pos, position), 0.5), $densityCell) AS cell
                      FROM segs
                     WHERE prev_pos IS NOT NULL AND NOT ST_Equals(position, prev_pos)
                ), visible AS (
                    SELECT * FROM lines WHERE line && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
                ), wt AS (
                    -- Every pass counts, so a road driven out and back, or reused by
                    -- several trips, becomes dense; a one-way street does not.
                    SELECT cell, COUNT(*)::int AS weight FROM visible GROUP BY cell
                ), dense AS (
                    SELECT v.grp, v.line, wt.weight
                      FROM visible v JOIN wt ON wt.cell = v.cell
                     WHERE wt.weight >= $heatMinWeight
                )
                SELECT weight, ST_AsGeoJSON(ST_LineMerge(ST_Collect(line))) AS geojson
                  FROM dense
              GROUP BY grp, weight ORDER BY weight DESC LIMIT 3000";
            $pointStmt = $pdo->prepare($pointSql);
            $pointStmt->execute($segParams);
            $rows = $pointStmt->fetchAll(PDO::FETCH_ASSOC);
            $max = 1;
            foreach ($rows as $row) $max = max($max, (int)$row['weight']);
            foreach ($rows as $row) {
                $geometry = json_decode((string)($row['geojson'] ?? ''), true);
                if (!is_array($geometry)) continue;
                $weight = (int)$row['weight'];
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $geometry,
                    'properties' => [
                        'layer' => 'heatmap',
                        'route' => true,
                        'weight' => $weight,
                        // Spread the visible range over every dense cell, with a
                        // floor so a street passed twice is still a warm hint.
                        'intensity' => round(0.35 + 0.65 * ($weight - $heatMinWeight) / max(1, $max - $heatMinWeight), 3),
                    ],
                ];
            }
            $meta['counts']['heatmap'] = count($rows);
            $meta['heatmap_mode'] = $rows === [] ? 'empty' : 'route';
        }

        if (in_array('speed', $layers, true)) {
            $segParams = [':owner' => $owner, ':from' => $from, ':to' => $to, ':minLon' => $bbox['minLon'], ':minLat' => $bbox['minLat'], ':maxLon' => $bbox['maxLon'], ':maxLat' => $bbox['maxLat']];
            if ($device !== '') $segParams[':device'] = $device;
            $speedSql = self::trackCte($device !== '') . ", segs AS (
                    SELECT grp, source_at, position,
                           lag(position) OVER w AS prev_pos,
                           lag(source_at) OVER w AS prev_at
                      FROM grouped WINDOW w AS (PARTITION BY grp ORDER BY source_at)
                ), spd AS (
                    SELECT grp, source_at, prev_pos, position,
                           ST_Distance(position::geography, prev_pos::geography) / GREATEST(EXTRACT(EPOCH FROM (source_at - prev_at)), 1) AS v
                      FROM segs WHERE prev_pos IS NOT NULL AND NOT ST_Equals(position, prev_pos)
                ), sm AS (
                    SELECT *, AVG(v) OVER (PARTITION BY grp ORDER BY source_at ROWS BETWEEN 2 PRECEDING AND 2 FOLLOWING) * 3.6 AS kmh FROM spd
                ), bk AS (
                    SELECT *, CASE WHEN kmh < 3 THEN 0 WHEN kmh < 6 THEN 1 WHEN kmh < 12 THEN 2 WHEN kmh < 25 THEN 3 WHEN kmh < 50 THEN 4 ELSE 5 END AS b FROM sm
                ), prevb AS (
                    SELECT *, lag(b) OVER (PARTITION BY grp ORDER BY source_at) AS pb FROM bk
                ), runs AS (
                    SELECT *, SUM(CASE WHEN pb IS DISTINCT FROM b THEN 1 ELSE 0 END) OVER (PARTITION BY grp ORDER BY source_at) AS run FROM prevb
                )
                SELECT b, AVG(kmh) AS kmh, MIN(source_at) AS started_at,
                       ST_AsGeoJSON(ST_LineMerge(ST_Collect(ST_MakeLine(prev_pos, position)))) AS geojson
                  FROM runs
                 WHERE ST_MakeLine(prev_pos, position) && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
              GROUP BY grp, run, b ORDER BY MIN(source_at) LIMIT 3000";
            $speedStmt = $pdo->prepare($speedSql);
            $speedStmt->execute($segParams);
            $rows = $speedStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $geometry = json_decode((string)($row['geojson'] ?? ''), true);
                if (!is_array($geometry)) continue;
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $geometry,
                    'properties' => ['layer' => 'speed', 'bucket' => (int)$row['b'], 'kmh' => round((float)$row['kmh'], 1), 'started_at' => $row['started_at']],
                ];
            }
            $meta['counts']['speed'] = count($rows);
        }
        if (in_array('explored', $layers, true)) {
            $stmt = $pdo->prepare(
                "SELECT round(ST_Y(ST_Centroid(ST_Collect(position)))::numeric, $precision) AS lat,
                        round(ST_X(ST_Centroid(ST_Collect(position)))::numeric, $precision) AS lon,
                        COUNT(*) AS visits
                   FROM geo_location_points
                  WHERE $where AND quality_state = 'accepted'
               GROUP BY ST_SnapToGrid(position, $cell)
               ORDER BY visits DESC LIMIT $limit"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [(float)$row['lon'], (float)$row['lat']]],
                    'properties' => ['layer' => 'explored', 'visits' => (int)$row['visits']],
                ];
            }
            $meta['counts']['explored'] = count($rows);
        }

        $meta['layers'] = $layers;
        return ['type' => 'FeatureCollection', 'features' => $features, 'meta' => $meta];
    }

    public static function trackCte(bool $withDevice): string
    {
        return "WITH ordered AS (
                    SELECT source_at, position,
                           lag(source_at) OVER (ORDER BY source_at, id) AS prev_at,
                           lag(position) OVER (ORDER BY source_at, id) AS prev_pos
                      FROM geo_location_points
                     WHERE user_email = :owner
                       AND quality_state = 'accepted'
                       AND source_at BETWEEN :from AND :to"
            . ($withDevice ? ' AND device_id = :device' : '') . "
                ), flagged AS (
                    SELECT source_at, position,
                           CASE WHEN prev_at IS NULL
                                  OR source_at - prev_at > interval '30 minutes'
                                  OR ST_Distance(position::geography, prev_pos::geography) > 2000
                                  OR ST_Distance(position::geography, prev_pos::geography)
                                     / GREATEST(EXTRACT(EPOCH FROM (source_at - prev_at)), 1) > 40
                                THEN 1 ELSE 0 END AS brk
                      FROM ordered
                ), grouped AS (
                    SELECT source_at, position, SUM(brk) OVER (ORDER BY source_at) AS grp FROM flagged
                )";
    }
    /**
     * Continuous tracks from accepted points, split on long gaps.
     * This is what an imported archive looks like when trip rows were never built.
     *
     * @param array{minLon:float,minLat:float,maxLon:float,maxLat:float} $bbox
     * @return list<array<string,mixed>>
     */
    public static function trackFeatures(PDO $pdo, string $owner, array $bbox, string $from, string $to, float $tolerance, string $device = ''): array
    {
        $tolerance = max(0.00001, min(0.05, $tolerance));
        $sql = "WITH ordered AS (
                    SELECT source_at, position,
                           lag(source_at) OVER (ORDER BY source_at, id) AS prev_at,
                           lag(position) OVER (ORDER BY source_at, id) AS prev_pos
                      FROM geo_location_points
                     WHERE user_email = :owner
                       AND quality_state = 'accepted'
                       AND source_at BETWEEN :from AND :to"
            . ($device !== '' ? ' AND device_id = :device' : '') . "
                ), flagged AS (
                    SELECT source_at, position,
                           CASE WHEN prev_at IS NULL
                                  OR source_at - prev_at > interval '30 minutes'
                                  OR ST_Distance(position::geography, prev_pos::geography) > 2000
                                  OR ST_Distance(position::geography, prev_pos::geography)
                                     / GREATEST(EXTRACT(EPOCH FROM (source_at - prev_at)), 1) > 40
                                THEN 1 ELSE 0 END AS brk
                      FROM ordered
                ), grouped AS (
                    SELECT source_at, position, SUM(brk) OVER (ORDER BY source_at) AS grp FROM flagged
                ), lines AS (
                    SELECT MIN(source_at) AS started_at, MAX(source_at) AS ended_at, COUNT(*) AS n,
                           ST_MakeLine(position ORDER BY source_at) AS line
                      FROM grouped GROUP BY grp HAVING COUNT(*) >= 2
                )
                SELECT started_at, ended_at, n,
                       ST_AsGeoJSON(ST_SimplifyPreserveTopology(line, $tolerance)) AS geojson,
                       COALESCE(ST_Length(line::geography), 0) AS distance_m
                  FROM lines
                 WHERE line && ST_MakeEnvelope(:minLon, :minLat, :maxLon, :maxLat, 4326)
              ORDER BY started_at
                 LIMIT 150";
        $params = [
            ':owner' => $owner,
            ':from' => $from,
            ':to' => $to,
            ':minLon' => $bbox['minLon'],
            ':minLat' => $bbox['minLat'],
            ':maxLon' => $bbox['maxLon'],
            ':maxLat' => $bbox['maxLat'],
        ];
        if ($device !== '') $params[':device'] = $device;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $features = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $geometry = json_decode((string)$row['geojson'], true);
            if (!is_array($geometry) || ($geometry['type'] ?? '') !== 'LineString') continue;
            if (count($geometry['coordinates'] ?? []) < 2) continue;
            $features[] = [
                'type' => 'Feature',
                'geometry' => $geometry,
                'properties' => [
                    'layer' => 'trip',
                    'id' => '',
                    'title' => '',
                    'started_at' => $row['started_at'],
                    'ended_at' => $row['ended_at'],
                    'distance_m' => (float)$row['distance_m'],
                    'boundary_reason' => 'imported_track',
                ],
            ];
        }
        return $features;
    }
}
