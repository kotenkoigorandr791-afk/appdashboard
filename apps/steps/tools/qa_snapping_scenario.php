<?php
/**
 * End-to-end road-snapping scenario against real imported OSM roads.
 *
 *   php apps/steps/tools/qa_snapping_scenario.php [qa-snap@example.test]
 *
 * Inserts a walk sampled 12 m beside a real road, runs the matching worker with
 * matching enabled for that owner, and reports the derived segment plus the
 * distance between the raw trace and the snapped line. A second trace 300 m
 * away from the road must abstain instead of snapping.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_matching.php';
require_once dirname(__DIR__) . '/core/geolocation_map_matcher.php';

function qa_snap_uuid(): string
{
    $b = bin2hex(random_bytes(16));
    return substr($b, 0, 8) . '-' . substr($b, 8, 4) . '-4' . substr($b, 13, 3) . '-' . dechex((hexdec($b[16]) & 3) | 8) . substr($b, 17, 3) . '-' . substr($b, 20);
}

$owner = (string)($argv[1] ?? 'qa-snap@example.test');
$networkVersion = (string)($argv[2] ?? 'qa-osm-kyiv-1');
$reportOnly = (string)($argv[3] ?? '') === 'report';
$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$road = $pdo->prepare(
    "SELECT id, ST_AsGeoJSON(geometry) AS geojson, ST_Length(geometry::geography) AS length_m
       FROM geo_match_networks
      WHERE version = :v AND kind = 'road' AND access = 'public' AND ST_NPoints(geometry) >= 3
   ORDER BY ST_Length(geometry::geography) DESC
      LIMIT 20"
);
$road->execute([':v' => $networkVersion]);
$candidates = $road->fetchAll(PDO::FETCH_ASSOC);
$chosen = null;
foreach ($candidates as $candidate) {
    if ((float)$candidate['length_m'] >= 200) { $chosen = $candidate; break; }
}
if ($chosen === null) {
    fwrite(STDERR, "no usable road network in version $networkVersion\n");
    exit(1);
}
$geometry = json_decode((string)$chosen['geojson'], true);
$line = $geometry['coordinates'] ?? [];
echo 'road ' . $chosen['id'] . ' length=' . round((float)$chosen['length_m']) . " m\n";

$haversine = static function (float $lat1, float $lon1, float $lat2, float $lon2): float {
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
};
// Sample the road by fraction of its own length so the trace is a real walk.
$segments = [];
$total = 0.0;
for ($i = 1; $i < count($line); $i++) {
    $length = $haversine((float)$line[$i - 1][1], (float)$line[$i - 1][0], (float)$line[$i][1], (float)$line[$i][0]);
    $segments[] = [$line[$i - 1], $line[$i], $length];
    $total += $length;
}
if ($total < 150) {
    fwrite(STDERR, "chosen road is too short: " . round($total) . "\n");
    exit(1);
}

/** @return array<int,array{0:float,1:float}> lon/lat pairs along the road */
$sample = static function (float $fromFraction, float $toFraction, int $count, float $offsetM) use ($segments, $total): array {
    $points = [];
    for ($i = 0; $i < $count; $i++) {
        $target = $total * ($fromFraction + ($toFraction - $fromFraction) * ($i / max(1, $count - 1)));
        $walked = 0.0;
        foreach ($segments as $segment) {
            if ($walked + $segment[2] >= $target || $segment === end($segments)) {
                $ratio = $segment[2] > 0 ? min(1.0, max(0.0, ($target - $walked) / $segment[2])) : 0.0;
                $lon = $segment[0][0] + ($segment[1][0] - $segment[0][0]) * $ratio;
                $lat = $segment[0][1] + ($segment[1][1] - $segment[0][1]) * $ratio;
                $bearing = atan2($segment[1][0] - $segment[0][0], $segment[1][1] - $segment[0][1]);
                $dLat = cos($bearing) * $offsetM / 111320.0;
                $dLon = sin($bearing) * $offsetM / (111320.0 * cos(deg2rad($lat)));
                $points[] = [$lon + $dLon, $lat + $dLat];
                break;
            }
            $walked += $segment[2];
        }
    }
    return $points;
};

if (!$reportOnly) {
$pdo->prepare('DELETE FROM geo_matched_segments WHERE user_email = :o')->execute([':o' => $owner]);
$pdo->prepare('DELETE FROM geo_location_points WHERE user_email = :o')->execute([':o' => $owner]);
$pdo->prepare('DELETE FROM geo_trips WHERE user_email = :o')->execute([':o' => $owner]);
$pdo->prepare('DELETE FROM geo_devices WHERE user_email = :o')->execute([':o' => $owner]);
$pdo->prepare("DELETE FROM geo_processor_runs WHERE user_email = :o AND kind IN ('matching','trips')")->execute([':o' => $owner]);

$deviceId = qa_snap_uuid();
$pdo->prepare("INSERT INTO geo_devices(id, user_email, name, platform, source_type, status) VALUES(:id, :o, 'QA snapping', 'android', 'mobile', 'active')")
    ->execute([':id' => $deviceId, ':o' => $owner]);
StepsGeolocationMatching::saveSettings($pdo, $owner, ['mode' => StepsGeolocationMatching::MODE_CONSERVATIVE]);

$insertPoint = $pdo->prepare(
    "INSERT INTO geo_location_points(id, user_email, device_id, client_event_id, source_type, source_at, position, accuracy_m, quality_state)
     VALUES(:id, :o, :d, :e, 'mobile', :at, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), 10, 'accepted')"
);

$base = time() - 7200;
$snapTrace = $sample(0.0, 0.6, 40, 12.0);
foreach ($snapTrace as $i => $coordinate) {
    $insertPoint->execute([
        ':id' => qa_snap_uuid(), ':o' => $owner, ':d' => $deviceId, ':e' => qa_snap_uuid(),
        ':at' => gmdate('c', $base + $i * 20), ':lon' => $coordinate[0], ':lat' => $coordinate[1],
    ]);
}
$farTrace = $sample(0.0, 0.6, 40, 300.0);
$farBase = $base + 4000;
foreach ($farTrace as $i => $coordinate) {
    $insertPoint->execute([
        ':id' => qa_snap_uuid(), ':o' => $owner, ':d' => $deviceId, ':e' => qa_snap_uuid(),
        ':at' => gmdate('c', $farBase + $i * 20), ':lon' => $coordinate[0], ':lat' => $coordinate[1],
    ]);
}

$tripInsert = $pdo->prepare(
    "INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, duration_s, title, state, boundary_reason)
     VALUES(:id, :o, :from, :to, ST_SetSRID(ST_GeomFromGeoJSON(:geometry), 4326), :distance, :duration, 'QA snap', 'ready', 'first_point')"
);
foreach ([[$snapTrace, $base], [$farTrace, $farBase]] as $pair) {
    [$trace, $start] = $pair;
    $coordinates = [];
    $distance = 0.0;
    foreach ($trace as $i => $coordinate) {
        $coordinates[] = $coordinate;
        if ($i > 0) $distance += $haversine($trace[$i - 1][1], $trace[$i - 1][0], $coordinate[1], $coordinate[0]);
    }
    $tripInsert->execute([
        ':id' => qa_snap_uuid(), ':o' => $owner,
        ':from' => gmdate('c', $start), ':to' => gmdate('c', $start + (count($trace) - 1) * 20),
        ':geometry' => json_encode(['type' => 'LineString', 'coordinates' => $coordinates]),
        ':distance' => round($distance, 1), ':duration' => (count($trace) - 1) * 20,
    ]);
}

echo "inserted 80 samples for $owner\n";
echo "run: php apps/steps/bin/geolocation_matching_run.php --user=$owner\n";
}

// Read back the derived segments for the report.
$report = $pdo->prepare(
    "SELECT state, mode, confidence, confidence_band, reasons_json, explanation,
            ST_AsGeoJSON(raw_geometry) AS raw_geojson, ST_AsGeoJSON(matched_geometry) AS matched_geojson
       FROM geo_matched_segments WHERE user_email = :o ORDER BY started_at"
);
$report->execute([':o' => $owner]);
foreach ($report->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo 'segment ' . $row['state'] . ' mode=' . $row['mode'] . ' confidence=' . $row['confidence'] . ' band=' . $row['confidence_band'] . ' reasons=' . $row['reasons_json'] . PHP_EOL;
    if ($row['state'] !== 'matched' || !is_string($row['matched_geojson'])) continue;
    $matched = json_decode((string)$row['matched_geojson'], true)['coordinates'] ?? [];
    $raw = json_decode((string)$row['raw_geojson'], true)['coordinates'] ?? [];
    $maxOffset = 0.0;
    foreach ($raw as $coordinate) {
        $nearest = null;
        foreach ($line as $i => $roadCoordinate) {
            if ($i === 0) continue;
            $a = $line[$i - 1];
            $b = $roadCoordinate;
            $d = $haversine((float)$coordinate[1], (float)$coordinate[0], (float)$b[1], (float)$b[0]);
            if ($nearest === null || $d < $nearest) $nearest = $d;
        }
        $maxOffset = max($maxOffset, (float)$nearest);
    }
    $rawLength = 0.0;
    for ($i = 1; $i < count($raw); $i++) $rawLength += $haversine((float)$raw[$i - 1][1], (float)$raw[$i - 1][0], (float)$raw[$i][1], (float)$raw[$i][0]);
    $matchedLength = 0.0;
    for ($i = 1; $i < count($matched); $i++) $matchedLength += $haversine((float)$matched[$i - 1][1], (float)$matched[$i - 1][0], (float)$matched[$i][1], (float)$matched[$i][0]);
    printf("  raw=%.1f m matched=%.1f m raw-start-offset=%.1f m explanation=%s\n", $rawLength, $matchedLength, $maxOffset, $row['explanation']);
}
