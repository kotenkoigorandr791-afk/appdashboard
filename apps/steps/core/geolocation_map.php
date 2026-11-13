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
        $allowed = array_merge(self::DEFAULT_LAYERS, ['points', 'heatmap', 'explored']);
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
                      WHERE user_email = :owner AND source_at BETWEEN :from AND :to AND quality_state = 'accepted') s
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
        $target = $target !== null ? strtolower(trim($target)) : '';
        if ($target === '' || $target === $viewer) return ['owner' => $viewer, 'error' => null];
        $stmt = $pdo->prepare(
            "SELECT 1 FROM geo_location_grants
              WHERE owner_email = :target AND viewer_email = :viewer AND revoked_at IS NULL
                AND access_level IN ('current','recent','range','places')
                AND (starts_at IS NULL OR starts_at <= now())
                AND (ends_at IS NULL OR ends_at > now())
                AND (range_from IS NULL OR range_from <= :from)
                AND (range_to IS NULL OR range_to >= :to)
              LIMIT 1"
        );
        $stmt->execute([':target' => $target, ':viewer' => $viewer, ':from' => $from, ':to' => $to]);
        if (!$stmt->fetchColumn()) return ['owner' => null, 'error' => 'family_access_denied'];
        return ['owner' => $target, 'error' => null];
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
            $sql = "SELECT id, started_at, ended_at, distance_m, title, boundary_reason,
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
                      WHERE $where AND quality_state = 'accepted'
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
            } else {
                $sql = "SELECT id, source_at, accuracy_m, device_id, quality_state,
                               round(ST_Y(position)::numeric, $precision) AS lat,
                               round(ST_X(position)::numeric, $precision) AS lon
                          FROM geo_location_points
                         WHERE $where AND quality_state <> 'rejected'"
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
            $heatCell = self::heatCellForZoom($zoom);
            $stmt = $pdo->prepare(
                "SELECT round(ST_Y(ST_Centroid(ST_Collect(position)))::numeric, $precision) AS lat,
                        round(ST_X(ST_Centroid(ST_Collect(position)))::numeric, $precision) AS lon,
                        COUNT(*) AS weight
                   FROM geo_location_points
                  WHERE $where AND quality_state = 'accepted'
               GROUP BY ST_SnapToGrid(position, $heatCell)
               ORDER BY weight DESC LIMIT $limit"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $max = 1;
            foreach ($rows as $row) $max = max($max, (int)$row['weight']);
            foreach ($rows as $row) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [(float)$row['lon'], (float)$row['lat']]],
                    'properties' => ['layer' => 'heatmap', 'weight' => (int)$row['weight'], 'intensity' => round((int)$row['weight'] / $max, 3)],
                ];
            }
            $meta['counts']['heatmap'] = count($rows);
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
}
