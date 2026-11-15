<?php
/**
 * Matching settings, durable jobs and read access for derived geometry.
 *
 * Matching is opt-in and conservative by default. Reads always expose raw and
 * matched representations; the display preference only selects which layer a
 * client starts with.
 */
require_once __DIR__ . '/geolocation_quality.php';
require_once __DIR__ . '/geolocation_map_matcher.php';

final class StepsGeolocationMatching
{
    public const MODE_DISABLED = 'disabled';
    public const MODE_CONSERVATIVE = 'conservative';
    public const MODE_REVIEW_REQUIRED = 'review_required';
    public const GEOMETRY_MODES = ['raw', 'improved', 'compare'];

    public static function settings(PDO $pdo, string $owner): array
    {
        $stmt = $pdo->prepare('SELECT mode, geometry_mode, updated_at FROM geo_match_settings WHERE user_email = :owner');
        $stmt->execute([':owner' => $owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'mode' => $row['mode'] ?? self::MODE_CONSERVATIVE,
            'geometry_mode' => $row['geometry_mode'] ?? 'raw',
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    public static function saveSettings(PDO $pdo, string $owner, array $input): array
    {
        $current = self::settings($pdo, $owner);
        $mode = (string)($input['mode'] ?? $current['mode']);
        if (!in_array($mode, [self::MODE_DISABLED, self::MODE_CONSERVATIVE, self::MODE_REVIEW_REQUIRED], true)) {
            $mode = $current['mode'];
        }
        $geometry = (string)($input['geometry_mode'] ?? $current['geometry_mode']);
        if (!in_array($geometry, self::GEOMETRY_MODES, true)) $geometry = $current['geometry_mode'];
        $stmt = $pdo->prepare(
            "INSERT INTO geo_match_settings(user_email, mode, geometry_mode, updated_at)
             VALUES(:owner, :mode, :geometry, now())
             ON CONFLICT (user_email) DO UPDATE SET mode = excluded.mode, geometry_mode = excluded.geometry_mode, updated_at = now()"
        );
        $stmt->execute([':owner' => $owner, ':mode' => $mode, ':geometry' => $geometry]);
        self::enqueue($pdo, $owner);
        return self::settings($pdo, $owner);
    }

    public static function enqueue(PDO $pdo, string $owner): bool
    {
        $stmt = $pdo->prepare(
            "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, priority)
             SELECT :id, :owner, 'matching', 'pending', :version, 60
              WHERE NOT EXISTS (
                    SELECT 1 FROM geo_processor_runs
                     WHERE user_email = :owner AND kind = 'matching' AND state IN ('pending','processing')
              )"
        );
        $stmt->execute([':id' => StepsGeolocationQuality::uuid(), ':owner' => $owner, ':version' => StepsGeolocationMapMatcher::VERSION]);
        return $stmt->rowCount() > 0;
    }

    public static function segments(PDO $pdo, string $owner, array $filters = []): array
    {
        $from = (string)($filters['from'] ?? '');
        $to = (string)($filters['to'] ?? '');
        $tripId = (string)($filters['trip_id'] ?? '');
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));
        $sql = "SELECT id, trip_id, point_ids, started_at, ended_at, mode, confidence, confidence_band, reasons_json, explanation, network_version, matcher_version, state, created_at,
                       ST_AsGeoJSON(raw_geometry) AS raw_geojson,
                       ST_AsGeoJSON(matched_geometry) AS matched_geojson
                  FROM geo_matched_segments WHERE user_email = :owner";
        $params = [':owner' => $owner];
        if ($from !== '') { $sql .= ' AND ended_at >= :from'; $params[':from'] = $from; }
        if ($to !== '') { $sql .= ' AND started_at <= :to'; $params[':to'] = $to; }
        if ($tripId !== '' && StepsGeolocationCorrectionsUuid::valid($tripId)) { $sql .= ' AND trip_id = :trip'; $params[':trip'] = $tripId; }
        $sql .= " ORDER BY started_at DESC LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['reasons'] = json_decode((string)$row['reasons_json'], true) ?: [];
            unset($row['reasons_json']);
            $raw = json_decode((string)$row['raw_geojson'], true);
            $matched = $row['matched_geojson'] !== null ? json_decode((string)$row['matched_geojson'], true) : null;
            unset($row['raw_geojson'], $row['matched_geojson']);
            $row['raw_geometry'] = $raw['coordinates'] ?? [];
            $row['matched_geometry'] = $matched['coordinates'] ?? null;
            $row['confidence'] = (float)$row['confidence'];
            $out[] = $row;
        }
        return $out;
    }

    public static function summary(PDO $pdo, string $owner): array
    {
        $stmt = $pdo->prepare("SELECT state, confidence_band, COUNT(*) AS c FROM geo_matched_segments WHERE user_email = :owner GROUP BY state, confidence_band");
        $stmt->execute([':owner' => $owner]);
        $summary = ['matched' => 0, 'abstained' => 0, 'stale' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $state = (string)$row['state'];
            if (isset($summary[$state])) $summary[$state] += (int)$row['c'];
            $band = (string)$row['confidence_band'];
            if ($state === 'matched' && isset($summary[$band])) $summary[$band] += (int)$row['c'];
        }
        return $summary;
    }

    public static function invalidate(PDO $pdo, string $owner): void
    {
        $stmt = $pdo->prepare("UPDATE geo_matched_segments SET state = 'stale', updated_at = now() WHERE user_email = :owner AND state = 'matched'");
        $stmt->execute([':owner' => $owner]);
    }
}

/** Small UUID helper kept here to avoid a dependency cycle with corrections. */
final class StepsGeolocationCorrectionsUuid
{
    public static function valid(string $value): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }
}
