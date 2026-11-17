<?php
/**
 * Durable, bounded and idempotent map-matching worker.
 *
 * Runs inside the existing PHP worker container. It never calls an external
 * service and never writes raw coordinates: only derived geo_matched_segments
 * rows are replaced. A disabled setting is a successful no-op.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_map_matcher.php';
require_once dirname(__DIR__) . '/core/geolocation_matching.php';
require_once dirname(__DIR__) . '/core/geolocation_trips.php';

function steps_matching_geojson(array $coordinates): ?string
{
    if (count($coordinates) < 2) return null;
    return json_encode(['type' => 'LineString', 'coordinates' => array_values($coordinates)], JSON_UNESCAPED_SLASHES);
}

/**
 * @return array{processed:int,failed:int}
 */
function steps_matching_pass(PDO $pdo, string $user = '', int $limit = 200): array
{
    $owners = [];
    if ($user !== '') {
        $owners[] = $user;
    } else {
        $stmt = $pdo->query("SELECT user_email FROM geo_processor_runs WHERE kind = 'matching' AND state = 'pending' GROUP BY user_email ORDER BY MIN(created_at) LIMIT 5");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $row) $owners[] = (string)$row;
    }
    $processed = 0;
    $failed = 0;
    foreach ($owners as $owner) {
        $runId = StepsGeolocationQuality::uuid();
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, details_json, lease_until, heartbeat_at, worker_id, attempts, started_at) VALUES(:id, :owner, 'matching', 'processing', :version, '{}'::jsonb, now() + interval '1 hour', now(), 'matching-worker', 1, now())")
                ->execute([':id' => $runId, ':owner' => $owner, ':version' => StepsGeolocationMapMatcher::VERSION]);
            $settings = StepsGeolocationMatching::settings($pdo, $owner);
            if ($settings['mode'] === StepsGeolocationMatching::MODE_DISABLED) {
                $finish = $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id");
                $finish->execute([':details' => json_encode(['skipped' => 'disabled', 'processed' => 0]), ':id' => $runId]);
                $pdo->commit();
                continue;
            }
            $tripStmt = $pdo->prepare("SELECT id, started_at, ended_at, override_json FROM geo_trips WHERE user_email = :owner AND deleted_at IS NULL AND ended_at >= now() - interval '365 days' ORDER BY started_at DESC LIMIT $limit");
            $tripStmt->execute([':owner' => $owner]);
            $trips = $tripStmt->fetchAll(PDO::FETCH_ASSOC);
            $matched = 0;
            $abstained = 0;
            foreach ($trips as $trip) {
                $override = json_decode((string)$trip['override_json'], true) ?: [];
                $pointIds = array_values(array_filter(array_map('strval', (array)($override['point_ids'] ?? [])), [StepsGeolocationCorrectionsUuid::class, 'valid']));
                $sql = "SELECT p.id, p.source_type, p.source_at, p.accuracy_m, p.quality_state,
                               ST_Y(p.position) AS latitude, ST_X(p.position) AS longitude,
                               p.correction_json
                          FROM geo_location_points p
                         WHERE p.user_email = :owner";
                $params = [':owner' => $owner];
                if ($pointIds !== []) {
                    $sql .= " AND p.id = ANY(CAST(:ids AS uuid[]))";
                    $params[':ids'] = '{' . implode(',', array_map(static fn(string $id): string => '"' . $id . '"', $pointIds)) . '}';
                } else {
                    $sql .= " AND p.source_at BETWEEN :from AND :to";
                    $params[':from'] = $trip['started_at'];
                    $params[':to'] = $trip['ended_at'];
                }
                $sql .= " AND p.quality_state NOT IN ('rejected','excluded_by_user','private_redacted') ORDER BY p.source_at ASC, p.id ASC LIMIT 2000";
                $pointStmt = $pdo->prepare($sql);
                $pointStmt->execute($params);
                $points = $pointStmt->fetchAll(PDO::FETCH_ASSOC);
                // A user correction can opt a range out of matching without
                // hiding it from raw views/analytics.
                $points = array_values(array_filter($points, static function (array $point): bool {
                    $correction = json_decode((string)($point['correction_json'] ?? '{}'), true) ?: [];
                    $effects = (array)($correction['last_correction']['effects'] ?? []);
                    return !array_intersect($effects, ['do_not_match', 'raw_only']);
                }));
                if ($points === []) continue;
                $lons = array_column($points, 'longitude');
                $lats = array_column($points, 'latitude');
                $bbox = "ST_MakeEnvelope(" . min($lons) . ',' . min($lats) . ',' . max($lons) . ',' . max($lats) . ",4326)";
                $networkStmt = $pdo->query("SELECT id, kind, name, access, source, version, ST_AsGeoJSON(geometry) AS geometry FROM geo_match_networks WHERE ST_Intersects(geometry, $bbox) LIMIT 500");
                $networks = [];
                foreach ($networkStmt->fetchAll(PDO::FETCH_ASSOC) as $network) {
                    $geometry = json_decode((string)$network['geometry'], true);
                    $network['geometry'] = $geometry['coordinates'] ?? [];
                    $networks[] = $network;
                }
                $privacy = !empty($override['privacy_boundary']);
                $result = StepsGeolocationMapMatcher::match($points, $networks, ['privacy_zone_boundary' => $privacy]);
                $pdo->prepare('DELETE FROM geo_matched_segments WHERE user_email = :owner AND trip_id = :trip')->execute([':owner' => $owner, ':trip' => $trip['id']]);
                $insert = $pdo->prepare("INSERT INTO geo_matched_segments(id, user_email, trip_id, point_ids, started_at, ended_at, raw_geometry, matched_geometry, mode, confidence, confidence_band, reasons_json, explanation, network_version, matcher_version, config_json, state) VALUES(:id, :owner, :trip, CAST(:points AS uuid[]), :from, :to, ST_SetSRID(ST_GeomFromGeoJSON(:raw), 4326), :matched, :mode, :confidence, :band, :reasons, :explanation, :network_version, :version, :config, :state)");
                foreach ($result['segments'] as $segment) {
                    $raw = steps_matching_geojson($segment['raw_geometry']);
                    if ($raw === null) continue;
                    $matchedJson = steps_matching_geojson((array)($segment['matched_geometry'] ?? []));
                    $insert->execute([
                        ':id' => StepsGeolocationQuality::uuid(),
                        ':owner' => $owner,
                        ':trip' => $trip['id'],
                        ':points' => '{' . implode(',', array_map(static fn(string $id): string => '"' . $id . '"', $segment['point_ids'])) . '}',
                        ':from' => $segment['started_at'],
                        ':to' => $segment['ended_at'],
                        ':raw' => $raw,
                        ':matched' => $matchedJson === null ? null : $matchedJson,
                        ':mode' => $segment['mode'],
                        ':confidence' => $segment['confidence'],
                        ':band' => $segment['confidence_band'],
                        ':reasons' => json_encode($segment['reasons'], JSON_UNESCAPED_SLASHES),
                        ':explanation' => $segment['explanation'],
                        ':network_version' => $segment['network_version'],
                        ':version' => StepsGeolocationMapMatcher::VERSION,
                        ':config' => json_encode(['options' => StepsGeolocationMapMatcher::options(), 'mode' => $settings['mode'], 'network_count' => count($networks)], JSON_UNESCAPED_SLASHES),
                        ':state' => $segment['state'],
                    ]);
                    if ($segment['state'] === 'matched') $matched++; else $abstained++;
                }
                $processed++;
            }
            $details = ['trips' => count($trips), 'segments_matched' => $matched, 'segments_abstained' => $abstained, 'network_mode' => $settings['mode']];
            $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id")
                ->execute([':details' => json_encode($details, JSON_UNESCAPED_SLASHES), ':id' => $runId]);
            $pdo->commit();
            fwrite(STDOUT, "matching: {$owner} {$matched} matched, {$abstained} abstained\n");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $failed++;
            error_log('geolocation matching run failed: ' . $e->getMessage());
            try { $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', last_error = :error, updated_at = now() WHERE id = :id")->execute([':error' => mb_substr($e->getMessage(), 0, 500), ':id' => $runId]); } catch (Throwable $ignored) {}
        }
    }
    return ['processed' => $processed, 'failed' => $failed];
}

$options = getopt('', ['user::', 'limit::', 'loop']);
$user = isset($options['user']) ? strtolower(trim((string)$options['user'])) : '';
$limit = isset($options['limit']) && is_numeric($options['limit']) ? max(1, min(1000, (int)$options['limit'])) : 200;
$loop = isset($options['loop']);
do {
    try {
        steps_geolocation_migrate(app_main_db());
        $result = steps_matching_pass(app_main_db()->getPdo(), $user, $limit);
    } catch (Throwable $e) {
        error_log('geolocation matching worker: ' . $e->getMessage());
        $result = ['processed' => 0, 'failed' => 1];
    }
    if (!$loop) {
        fwrite(STDOUT, "matching: {$result['processed']} trip set(s) processed, {$result['failed']} owner run(s) failed\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['processed'] === 0) sleep(15);
} while (true);
