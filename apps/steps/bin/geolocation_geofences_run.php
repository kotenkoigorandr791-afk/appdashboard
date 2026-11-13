<?php
/**
 * Emits enter/exit events for active circular zones.
 *
 *   php apps/steps/bin/geolocation_geofences_run.php --loop
 *   php apps/steps/bin/geolocation_geofences_run.php --user=owner@example.com
 *
 * Zones are stored as polygons (`ST_Buffer` of the place centre), so polygon
 * support later only needs a different radius source. Transition state is read
 * from the latest committed event and new events are inserted transactionally
 * with the evaluator version recorded in the audit trail.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_geofences.php';

/** @return array{processed:int,events:int,failed:int} */
function steps_geofences_pass(PDO $pdo, string $user, int $limit, array $thresholds): array
{
    $owners = [];
    if ($user !== '') {
        $owners[] = $user;
    } else {
        $stmt = $pdo->query('SELECT user_email FROM geo_geofences WHERE is_active GROUP BY user_email LIMIT 5');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $owners[] = (string)$row['user_email'];
    }

    $processed = 0;
    $eventsMade = 0;
    $failed = 0;

    foreach ($owners as $owner) {
        $runId = StepsGeolocationGeofences::uuid();
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version) VALUES(:id, :owner, 'geofences', 'processing', :version)")
                ->execute([':id' => $runId, ':owner' => $owner, ':version' => StepsGeolocationGeofences::EVALUATOR_VERSION]);

            $wm = $pdo->prepare("SELECT details_json->>'input_to' FROM geo_processor_runs WHERE user_email = :owner AND kind = 'geofences' AND state = 'ready' ORDER BY created_at DESC LIMIT 1");
            $wm->execute([':owner' => $owner]);
            $watermark = $wm->fetchColumn() ?: null;
            $from = $watermark !== null ? gmdate('c', strtotime($watermark) - 3600) : gmdate('c', 0);

            $points = $pdo->prepare(
                "SELECT id, source_at, accuracy_m, quality_state, ST_Y(position) AS latitude, ST_X(position) AS longitude
                   FROM geo_location_points
                  WHERE user_email = :owner AND quality_state = 'accepted' AND source_at >= :from
               ORDER BY source_at ASC
                  LIMIT $limit"
            );
            $points->execute([':owner' => $owner, ':from' => $from]);
            $rows = $points->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id")
                    ->execute([':details' => json_encode(['processed' => 0, 'events' => 0]), ':id' => $runId]);
                $pdo->commit();
                continue;
            }

            $zones = $pdo->prepare(
                "SELECT id, name, is_active, notification_json,
                        ST_Y(ST_Centroid(geometry)) AS latitude,
                        ST_X(ST_Centroid(geometry)) AS longitude,
                        GREATEST(1, ST_Distance(ST_Centroid(geometry)::geography, ST_StartPoint(ST_ExteriorRing(geometry))::geography)) AS radius_m
                   FROM geo_geofences
                  WHERE user_email = :owner AND is_active"
            );
            $zones->execute([':owner' => $owner]);

            $lastEvent = $pdo->prepare('SELECT event_type FROM geo_geofence_events WHERE user_email = :owner AND geofence_id = :zone ORDER BY occurred_at DESC LIMIT 1');
            $insert = $pdo->prepare(
                "INSERT INTO geo_geofence_events(id, user_email, geofence_id, point_id, event_type, occurred_at, confidence)
                 VALUES(:id, :owner, :zone, :point, :type, :occurred, :confidence)"
            );

            foreach ($zones->fetchAll(PDO::FETCH_ASSOC) as $zone) {
                if ((int)$zone['radius_m'] <= 0) continue;
                $zone['radius_m'] = (float)$zone['radius_m'];
                $lastEvent->execute([':owner' => $owner, ':zone' => $zone['id']]);
                $previous = $lastEvent->fetchColumn() ?: null;
                $state = $previous === 'enter' ? 'inside' : ($previous === 'exit' ? 'outside' : null);
                $evaluation = StepsGeolocationGeofences::evaluate($zone, $rows, $state, $thresholds);
                foreach ($evaluation['events'] as $event) {
                    $insert->execute([
                        ':id' => StepsGeolocationGeofences::uuid(),
                        ':owner' => $owner,
                        ':zone' => $zone['id'],
                        ':point' => $event['point_id'] !== '' ? $event['point_id'] : null,
                        ':type' => $event['type'],
                        ':occurred' => $event['occurred_at'],
                        ':confidence' => $event['confidence'],
                    ]);
                    $eventsMade++;
                }
            }

            $details = [
                'processed' => count($rows),
                'events' => $eventsMade,
                'input_from' => (string)$rows[0]['source_at'],
                'input_to' => (string)$rows[count($rows) - 1]['source_at'],
                'evaluator_version' => StepsGeolocationGeofences::EVALUATOR_VERSION,
                'thresholds' => $thresholds,
            ];
            $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', input_from = :from, input_to = :to, details_json = :details, updated_at = now() WHERE id = :id")
                ->execute([':from' => $details['input_from'], ':to' => $details['input_to'], ':details' => json_encode($details, JSON_UNESCAPED_SLASHES), ':id' => $runId]);
            $pdo->commit();
            $processed += count($rows);
            fwrite(STDOUT, "geofences: {$owner} {$eventsMade} event(s) from " . count($rows) . " point(s)\n");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $failed++;
            error_log('geolocation geofences run failed: ' . $e->getMessage());
            try {
                $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', updated_at = now() WHERE id = :id")->execute([':id' => $runId]);
            } catch (Throwable $ignored) {
            }
        }
    }

    return ['processed' => $processed, 'events' => $eventsMade, 'failed' => $failed];
}

$options = getopt('', ['limit::', 'user::', 'loop']);
$limit = isset($options['limit']) && is_numeric($options['limit']) ? max(1, min(10000, (int)$options['limit'])) : 3000;
$user = isset($options['user']) && is_string($options['user']) ? strtolower(trim($options['user'])) : '';
$loop = isset($options['loop']);
$thresholds = StepsGeolocationGeofences::thresholds();

do {
    try {
        $result = steps_geofences_pass(app_main_db()->getPdo(), $user, $limit, $thresholds);
    } catch (Throwable $e) {
        error_log('geolocation geofences worker: ' . $e->getMessage());
        $result = ['processed' => 0, 'events' => 0, 'failed' => 1];
    }
    if (!$loop) {
        fwrite(STDOUT, "geofences: {$result['processed']} point(s), {$result['events']} event(s), {$result['failed']} run(s) failed\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['processed'] === 0) sleep(15);
} while (true);
