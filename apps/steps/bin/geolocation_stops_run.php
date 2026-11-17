<?php
/**
 * Derives stops and place visits from accepted points.
 *
 *   php apps/steps/bin/geolocation_stops_run.php --loop
 *   php apps/steps/bin/geolocation_stops_run.php --user=owner@example.com
 *
 * Automatic stops/visits inside the recompute window are replaced; rows the
 * owner reviewed (override_json.manual) are preserved.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_stops.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';

/** @return array{processed:int,stops:int,visits:int,failed:int} */
function steps_stops_pass(PDO $pdo, string $user, int $limit, array $thresholds): array
{
    $owners = [];
    if ($user !== '') {
        $owners[] = $user;
    } else {
        $stmt = $pdo->query("SELECT user_email FROM geo_processor_runs WHERE kind = 'stops' AND state = 'pending' GROUP BY user_email ORDER BY MIN(created_at) LIMIT 5");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $owners[] = (string)$row['user_email'];
    }

    $processed = 0;
    $stopsMade = 0;
    $visitsMade = 0;
    $failed = 0;
    $version = StepsGeolocationStops::PROCESSOR_VERSION;

    foreach ($owners as $owner) {
        // Avoid recreating the same derived rows every polling cycle.
        $latestStmt = $pdo->prepare("SELECT MAX(source_at) FROM geo_location_points WHERE user_email = :owner AND quality_state = 'accepted'");
        $latestStmt->execute([':owner' => $owner]);
        $latestPoint = $latestStmt->fetchColumn() ?: null;
        $closePending = $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = COALESCE(details_json, '{}'::jsonb) || CAST(:details AS jsonb), updated_at = now() WHERE user_email = :owner AND kind = 'stops' AND state = 'pending'");
        $closeNoop = static function () use ($closePending, $owner): void {
            $closePending->execute([':owner' => $owner, ':details' => json_encode(['processed' => 0, 'skipped' => 'no_new_points'], JSON_UNESCAPED_SLASHES)]);
        };
        if ($latestPoint === null) { $closeNoop(); continue; }
        $wmPre = $pdo->prepare("SELECT details_json->>'input_to' FROM geo_processor_runs WHERE user_email = :owner AND kind = 'stops' AND state = 'ready' ORDER BY created_at DESC LIMIT 1");
        $wmPre->execute([':owner' => $owner]);
        $watermarkPre = $wmPre->fetchColumn() ?: null;
        if ($watermarkPre !== null && strtotime((string)$latestPoint) <= strtotime((string)$watermarkPre)) { $closeNoop(); continue; }

        $runId = StepsGeolocationStops::uuid();
        try {
            $pdo->beginTransaction();
            $run = $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, lease_until, heartbeat_at, worker_id, attempts, started_at) VALUES(:id, :owner, 'stops', 'processing', :version, now() + interval '1 hour', now(), 'legacy-worker', 1, now())");
            $run->execute([':id' => $runId, ':owner' => $owner, ':version' => $version]);

            $wm = $pdo->prepare("SELECT details_json->>'input_to' AS watermark FROM geo_processor_runs WHERE user_email = :owner AND kind = 'stops' AND state = 'ready' ORDER BY created_at DESC LIMIT 1");
            $wm->execute([':owner' => $owner]);
            $watermark = $wm->fetchColumn() ?: null;
            $overlap = (int)($thresholds['max_gap_s'] + $thresholds['merge_gap_s']);
            $from = $watermark !== null ? gmdate('c', strtotime($watermark) - $overlap) : gmdate('c', 0);

            $points = $pdo->prepare(
                "SELECT id, device_id, source_at, accuracy_m, quality_state,
                        ST_Y(position) AS latitude, ST_X(position) AS longitude
                   FROM geo_location_points
                  WHERE user_email = :owner AND quality_state = 'accepted' AND source_at >= :from
               ORDER BY source_at ASC
                  LIMIT $limit"
            );
            $points->execute([':owner' => $owner, ':from' => $from]);
            $rows = $points->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                $finish = $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id");
                $finish->execute([':details' => json_encode(['processed' => 0, 'stops' => 0, 'visits' => 0, 'thresholds' => $thresholds]), ':id' => $runId]);
                $closeNoop();
                $pdo->commit();
                continue;
            }

            $result = StepsGeolocationStops::detect($rows, $thresholds);
            $inputFrom = (string)$rows[0]['source_at'];
            $inputTo = (string)$rows[count($rows) - 1]['source_at'];

            $pdo->prepare(
                "DELETE FROM geo_visits
                  WHERE user_email = :owner AND COALESCE(override_json->>'manual', 'false') <> 'true'
                    AND arrived_at >= :from AND arrived_at <= :to"
            )->execute([':owner' => $owner, ':from' => $inputFrom, ':to' => $inputTo]);
            $pdo->prepare(
                "DELETE FROM geo_stops
                  WHERE user_email = :owner AND COALESCE(override_json->>'manual', 'false') <> 'true'
                    AND started_at >= :from AND started_at <= :to"
            )->execute([':owner' => $owner, ':from' => $inputFrom, ':to' => $inputTo]);

            $insertStop = $pdo->prepare(
                "INSERT INTO geo_stops(id, user_email, started_at, ended_at, position, radius_m, confidence, state, override_json)
                 VALUES(:id, :owner, :started, :ended, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326), :radius, :confidence, 'ready', :override)"
            );
            $findPlace = $pdo->prepare(
                "SELECT id FROM geo_places
                  WHERE user_email = :owner AND is_active AND deleted_at IS NULL
                    AND ST_DWithin(position::geography, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography, radius_m)
               ORDER BY ST_Distance(position::geography, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography) ASC
                  LIMIT 1"
            );
            $insertVisit = $pdo->prepare(
                "INSERT INTO geo_visits(id, user_email, place_id, stop_id, arrived_at, departed_at, confidence, state, override_json)
                 VALUES(:id, :owner, :place, :stop, :arrived, :departed, :confidence, 'ready', :override)"
            );

            foreach ($result['stops'] as $stop) {
                $stopId = StepsGeolocationStops::uuid();
                $insertStop->execute([
                    ':id' => $stopId,
                    ':owner' => $owner,
                    ':started' => $stop['started_at'],
                    ':ended' => $stop['ended_at'],
                    ':lon' => $stop['longitude'],
                    ':lat' => $stop['latitude'],
                    ':radius' => $stop['radius_m'],
                    ':confidence' => $stop['confidence'],
                    ':override' => json_encode([
                        'source' => 'auto',
                        'processor_version' => $version,
                        'point_ids' => $stop['point_ids'],
                        'duration_s' => $stop['duration_s'],
                        'thresholds' => $thresholds,
                    ], JSON_UNESCAPED_SLASHES),
                ]);
                $stopsMade++;

                $findPlace->execute([':owner' => $owner, ':lon' => $stop['longitude'], ':lat' => $stop['latitude']]);
                $placeId = $findPlace->fetchColumn() ?: null;
                if ($placeId === null) continue;
                $insertVisit->execute([
                    ':id' => StepsGeolocationStops::uuid(),
                    ':owner' => $owner,
                    ':place' => $placeId,
                    ':stop' => $stopId,
                    ':arrived' => $stop['started_at'],
                    ':departed' => $stop['ended_at'],
                    ':confidence' => $stop['confidence'],
                    ':override' => json_encode(['source' => 'auto', 'processor_version' => $version], JSON_UNESCAPED_SLASHES),
                ]);
                $visitsMade++;
            }

            $details = ['processed' => count($rows), 'stops' => count($result['stops']), 'visits' => $visitsMade, 'input_from' => $inputFrom, 'input_to' => $inputTo, 'thresholds' => $thresholds];
            $closeNoop();
            $finish = $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', input_from = :from, input_to = :to, details_json = :details, updated_at = now() WHERE id = :id");
            $finish->execute([':from' => $inputFrom, ':to' => $inputTo, ':details' => json_encode($details, JSON_UNESCAPED_SLASHES), ':id' => $runId]);
            $pdo->commit();
            $processed += count($rows);
            StepsGeolocationStatistics::invalidate($pdo, $owner, $inputFrom, $inputTo);
            fwrite(STDOUT, "stops: {$owner} {$details['stops']} stop(s), {$visitsMade} visit(s)\n");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $failed++;
            error_log('geolocation stops run failed: ' . $e->getMessage());
            try {
                $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', updated_at = now() WHERE id = :id")->execute([':id' => $runId]);
            } catch (Throwable $ignored) {
            }
        }
    }

    return ['processed' => $processed, 'stops' => $stopsMade, 'visits' => $visitsMade, 'failed' => $failed];
}

$options = getopt('', ['limit::', 'user::', 'loop']);
$limit = isset($options['limit']) && is_numeric($options['limit']) ? max(1, min(10000, (int)$options['limit'])) : 2000;
$user = isset($options['user']) && is_string($options['user']) ? strtolower(trim($options['user'])) : '';
$loop = isset($options['loop']);
$thresholds = StepsGeolocationStops::thresholds();

do {
    try {
        $result = steps_stops_pass(app_main_db()->getPdo(), $user, $limit, $thresholds);
    } catch (Throwable $e) {
        error_log('geolocation stops worker: ' . $e->getMessage());
        $result = ['processed' => 0, 'stops' => 0, 'visits' => 0, 'failed' => 1];
    }
    if (!$loop) {
        fwrite(STDOUT, "stops: {$result['processed']} point(s), {$result['stops']} stop(s), {$result['visits']} visit(s), {$result['failed']} run(s) failed\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['processed'] === 0) sleep(15);
    else usleep(100000);
} while (true);
