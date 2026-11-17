<?php
/**
 * Bounded quality pass over pending location points.
 *
 * Runs under the existing supervisor worker path:
 *   php apps/steps/bin/geolocation_quality_run.php --loop
 * One-off runs are also supported:
 *   php apps/steps/bin/geolocation_quality_run.php --limit=500
 *   php apps/steps/bin/geolocation_quality_run.php --user=owner@example.com
 *
 * Each owner gets a geo_processor_runs row that records processor version,
 * input window and a JSON summary, so a failed pass is visible and retryable.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_quality.php';
require_once dirname(__DIR__) . '/core/geolocation_trips.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';
require_once dirname(__DIR__) . '/core/geolocation_observability.php';
require_once dirname(__DIR__) . '/core/geolocation_matching.php';

/**
 * @return array{processed:int,failed:int}
 */
function steps_quality_pass(PDO $pdo, string $user, int $limit, array $thresholds): array
{
    $owners = [];
    if ($user !== '') {
        $owners[] = $user;
    } else {
        $stmt = $pdo->query("SELECT user_email FROM geo_processor_runs WHERE kind = 'quality' AND state = 'pending' GROUP BY user_email ORDER BY MIN(created_at) LIMIT 5");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $owners[] = (string)$row['user_email'];
    }

    $processed = 0;
    $failed = 0;
    foreach ($owners as $owner) {
        $closePending = $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = COALESCE(details_json, '{}'::jsonb) || CAST(:details AS jsonb), updated_at = now() WHERE user_email = :owner AND kind = 'quality' AND state = 'pending'");
        $runId = StepsGeolocationQuality::uuid();
        try {
            $pdo->beginTransaction();
            $run = $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, details_json, lease_until, heartbeat_at, worker_id, attempts, started_at) VALUES(:id, :owner, 'quality', 'processing', :version, '{}'::jsonb, now() + interval '1 hour', now(), 'legacy-worker', 1, now())");
            $run->execute([':id' => $runId, ':owner' => $owner, ':version' => StepsGeolocationQuality::PROCESSOR_VERSION]);

            $points = $pdo->prepare(
                "SELECT id, device_id, source_type, source_at, accuracy_m, ST_Y(position) AS latitude, ST_X(position) AS longitude
                   FROM geo_location_points
                  WHERE user_email = :owner AND quality_state = 'pending'
               ORDER BY received_at ASC
                  LIMIT $limit"
            );
            $points->execute([':owner' => $owner]);
            $rows = $points->fetchAll(PDO::FETCH_ASSOC);

            $prevStmt = $pdo->prepare(
                "SELECT source_at, ST_Y(position) AS latitude, ST_X(position) AS longitude
                   FROM geo_location_points
                  WHERE user_email = :owner AND device_id = :device AND id <> :id
                    AND source_at < :at AND quality_state <> 'rejected'
               ORDER BY source_at DESC LIMIT 1"
            );
            $update = $pdo->prepare(
                'UPDATE geo_location_points
                    SET quality_state = :state, quality_reason = :reason, correction_json = :correction,
                        quality_score = :score, quality_severity = :severity, quality_version = :version, quality_details = :details
                  WHERE id = :id AND user_email = :owner'
            );

            $counts = ['accepted' => 0, 'flagged' => 0, 'rejected' => 0];
            foreach ($rows as $row) {
                $prevStmt->execute([
                    ':owner' => $owner,
                    ':device' => $row['device_id'],
                    ':id' => $row['id'],
                    ':at' => $row['source_at'],
                ]);
                $previous = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                $decision = StepsGeolocationQuality::evaluate($row, $previous, $thresholds);
                $counts[$decision['state']]++;
                $update->execute([
                    ':state' => $decision['state'],
                    ':reason' => $decision['reason'],
                    ':correction' => json_encode([
                        'flags' => $decision['flags'],
                        'processor_version' => StepsGeolocationQuality::PROCESSOR_VERSION,
                    ], JSON_UNESCAPED_SLASHES),
                    ':score' => $decision['score'],
                    ':severity' => $decision['severity'],
                    ':version' => StepsGeolocationQuality::PROCESSOR_VERSION,
                    ':details' => json_encode(['reasons' => $decision['reasons'], 'severity' => $decision['severity']], JSON_UNESCAPED_SLASHES),
                    ':id' => $row['id'],
                    ':owner' => $owner,
                ]);
            }

            $summary = $counts + ['processed' => count($rows), 'thresholds' => $thresholds];
            $finish = $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id");
            $finish->execute([':details' => json_encode($summary, JSON_UNESCAPED_SLASHES), ':id' => $runId]);
            $pdo->commit();
            $closePending->execute([':owner' => $owner, ':details' => json_encode(['processed' => count($rows), 'skipped' => $rows === [] ? 'no_pending_points' : 'processed'], JSON_UNESCAPED_SLASHES)]);
            if ($counts['accepted'] + $counts['flagged'] + $counts['rejected'] > 0) StepsGeolocationObservability::invalidateOwner($owner);
            $processed += count($rows);
            if ($counts['accepted'] > 0) {
                try { StepsGeolocationTrips::enqueue($pdo, $owner); } catch (Throwable $e) { error_log('geolocation trips enqueue: ' . $e->getMessage()); }
                try { StepsGeolocationMatching::enqueue($pdo, $owner); } catch (Throwable $e) { error_log('geolocation matching enqueue: ' . $e->getMessage()); }
            }
            $statsFrom = $rows !== [] ? (string)$rows[0]['source_at'] : null;
            $statsTo = $rows !== [] ? (string)$rows[count($rows) - 1]['source_at'] : null;
            StepsGeolocationStatistics::invalidate($pdo, $owner, $statsFrom, $statsTo);
            fwrite(STDOUT, "quality: {$owner} processed " . count($rows) . " point(s)\n");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $failed++;
            error_log('geolocation quality run failed: ' . $e->getMessage());
            try {
                $mark = $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', updated_at = now() WHERE id = :id");
                $mark->execute([':id' => $runId]);
            } catch (Throwable $ignored) {
            }
        }
    }
    return ['processed' => $processed, 'failed' => $failed];
}

$options = getopt('', ['limit::', 'user::', 'loop']);
$limit = isset($options['limit']) && is_numeric($options['limit']) ? max(1, min(2000, (int)$options['limit'])) : 500;
$user = isset($options['user']) && is_string($options['user']) ? strtolower(trim($options['user'])) : '';
$loop = isset($options['loop']);
$thresholds = StepsGeolocationQuality::thresholds();

do {
    try {
        $result = steps_quality_pass(app_main_db()->getPdo(), $user, $limit, $thresholds);
    } catch (Throwable $e) {
        error_log('geolocation quality worker: ' . $e->getMessage());
        $result = ['processed' => 0, 'failed' => 1];
    }
    if (!$loop) {
        fwrite(STDOUT, "quality: {$result['processed']} point(s) processed, {$result['failed']} owner run(s) failed\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['processed'] === 0) sleep(15);
} while (true);
