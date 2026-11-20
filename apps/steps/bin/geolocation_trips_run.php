<?php
/**
 * Incremental trip segmentation over quality-approved points.
 *
 * Runs under supervisor (`--loop`) or on demand:
 *   php apps/steps/bin/geolocation_trips_run.php --loop
 *   php apps/steps/bin/geolocation_trips_run.php --user=owner@example.com --limit=2000
 *
 * The last ready run stores the input watermark; each pass re-reads an overlap
 * window, replaces automatic trips in that window and never touches trips the
 * owner edited (`override_json.manual`), so rebuilds are deterministic.
 */
require_once __DIR__ . '/worker_limits.php';
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_trips.php';
require_once dirname(__DIR__) . '/core/geolocation_stops.php';

function steps_trips_rebuild_trip(PDO $pdo, string $owner, array $trip, string $version, array $thresholds): void
{
    $ids = array_values(array_filter($trip['point_ids'], static fn($id): bool => StepsGeolocationDomain::isUuid((string)$id)));
    if ($ids === []) return;
    $list = implode(',', array_map(static fn(string $id): string => "'" . $id . "'", $ids));
    $shape = $pdo->prepare(
        "SELECT ST_AsGeoJSON(ST_MakeLine(position ORDER BY source_at)) AS geojson,
                COALESCE(ST_Length(ST_MakeLine(position ORDER BY source_at)::geography), 0) AS distance_m,
                COUNT(*) AS point_count
           FROM geo_location_points
          WHERE user_email = :owner AND id IN ($list)"
    );
    $shape->execute([':owner' => $owner]);
    $row = $shape->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['geojson'] === null) return;
    $insert = $pdo->prepare(
        "INSERT INTO geo_trips(id, user_email, started_at, ended_at, geometry, distance_m, state, boundary_reason, processor_version, override_json)
         VALUES(:id, :owner, :started, :ended, ST_SetSRID(ST_GeomFromGeoJSON(:geojson), 4326), :distance, 'ready', :reason, :version, :override)"
    );
    $insert->execute([
        ':id' => StepsGeolocationTrips::uuid(),
        ':owner' => $owner,
        ':started' => $trip['started_at'],
        ':ended' => $trip['ended_at'],
        ':geojson' => $row['geojson'],
        ':distance' => (float)$row['distance_m'],
        ':reason' => $trip['boundary_reason'],
        ':version' => $version,
        ':override' => json_encode([
            'source' => 'auto',
            'thresholds' => $thresholds,
            'point_ids' => $ids,
            'point_count' => (int)$row['point_count'],
            'device_ids' => $trip['device_ids'],
        ], JSON_UNESCAPED_SLASHES),
    ]);
}

/** @return array{processed:int,trips:int,dropped:int,failed:int} */
function steps_trips_pass(PDO $pdo, string $user, int $limit, array $thresholds): array
{
    $owners = [];
    if ($user !== '') {
        $owners[] = $user;
    } else {
        // Pending runs are the work queue; scanning every accepted point just
        // to discover owners was a full-table aggregate on every worker tick.
        $stmt = $pdo->query("SELECT user_email FROM geo_processor_runs WHERE kind = 'trips' AND state = 'pending' GROUP BY user_email ORDER BY MIN(created_at) LIMIT 5");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $owners[] = (string)$row['user_email'];
    }

    $version = StepsGeolocationTrips::PROCESSOR_VERSION;
    $processed = 0;
    $tripsCreated = 0;
    $dropped = 0;
    $failed = 0;

    foreach ($owners as $owner) {
        // Do not create a run when no new accepted points exist. The old polling
        // loop re-read the same overlap window forever and produced tens of
        // thousands of empty runs per day.
        $latestStmt = $pdo->prepare("SELECT MAX(source_at) FROM geo_location_points WHERE user_email = :owner AND quality_state = 'accepted'");
        $latestStmt->execute([':owner' => $owner]);
        $latestPoint = $latestStmt->fetchColumn() ?: null;
        $closePending = $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = COALESCE(details_json, '{}'::jsonb) || CAST(:details AS jsonb), updated_at = now() WHERE user_email = :owner AND kind = 'trips' AND state = 'pending'");
        $closeNoop = static function () use ($closePending, $owner): void {
            $closePending->execute([':owner' => $owner, ':details' => json_encode(['processed' => 0, 'skipped' => 'no_new_points'], JSON_UNESCAPED_SLASHES)]);
        };
        if ($latestPoint === null) { $closeNoop(); continue; }
        $wmPre = $pdo->prepare("SELECT details_json->>'input_to' FROM geo_processor_runs WHERE user_email = :owner AND kind = 'trips' AND state = 'ready' ORDER BY created_at DESC LIMIT 1");
        $wmPre->execute([':owner' => $owner]);
        $watermarkPre = $wmPre->fetchColumn() ?: null;
        $pendingPre = $pdo->prepare("SELECT COUNT(*) FROM geo_processor_runs WHERE user_email = :owner AND kind = 'trips' AND state = 'pending' AND input_from IS NOT NULL AND input_to IS NOT NULL");
        $pendingPre->execute([':owner' => $owner]);
        if ($watermarkPre !== null && strtotime((string)$latestPoint) <= strtotime((string)$watermarkPre) && (int)$pendingPre->fetchColumn() === 0) { $closeNoop(); continue; }

        $runId = StepsGeolocationTrips::uuid();
        try {
            $pdo->beginTransaction();
            $run = $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, lease_until, heartbeat_at, worker_id, attempts, started_at) VALUES(:id, :owner, 'trips', 'processing', :version, now() + interval '1 hour', now(), 'legacy-worker', 1, now())");
            $run->execute([':id' => $runId, ':owner' => $owner, ':version' => $version]);

            $wm = $pdo->prepare("SELECT details_json->>'input_to' AS watermark FROM geo_processor_runs WHERE user_email = :owner AND kind = 'trips' AND state = 'ready' ORDER BY created_at DESC LIMIT 1");
            $wm->execute([':owner' => $owner]);
            $watermark = $wm->fetchColumn() ?: null;
            $from = $watermark !== null ? gmdate('c', strtotime($watermark) - (int)$thresholds['max_gap_s']) : gmdate('c', 0);

            // Manual trip edits enqueue bounded refreshes. Process their window
            // as well so a rebuild keeps automatic trips around the edit fresh.
            $pending = $pdo->prepare("SELECT id, input_from, input_to FROM geo_processor_runs WHERE user_email = :owner AND kind = 'trips' AND state = 'pending' AND input_from IS NOT NULL AND input_to IS NOT NULL");
            $pending->execute([':owner' => $owner]);
            $pendingRows = $pending->fetchAll(PDO::FETCH_ASSOC);
            $pendingIds = [];
            $boundTo = null;
            foreach ($pendingRows as $row) {
                $pendingIds[] = (string)$row['id'];
                if ($row['input_from'] !== null && strtotime((string)$row['input_from']) < strtotime($from)) {
                    $from = gmdate('c', strtotime((string)$row['input_from']));
                }
                if ($row['input_to'] !== null && ($boundTo === null || strtotime((string)$row['input_to']) > strtotime($boundTo))) {
                    $boundTo = (string)$row['input_to'];
                }
            }

            $toSql = $boundTo !== null ? ' AND source_at <= :to' : '';
            $stmt = $pdo->prepare(
                "SELECT id, device_id, source_at, accuracy_m, quality_state,
                        ST_Y(position) AS latitude, ST_X(position) AS longitude
                   FROM geo_location_points
                  WHERE user_email = :owner AND quality_state = 'accepted' AND source_at >= :from$toSql
               ORDER BY source_at ASC
                  LIMIT $limit"
            );
            $pointParams = [':owner' => $owner, ':from' => $from];
            if ($boundTo !== null) $pointParams[':to'] = $boundTo;
            $stmt->execute($pointParams);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $markPending = static function (PDO $pdo, array $ids, string $state, array $details): void {
                if ($ids === []) return;
                $list = implode(',', array_map(static fn(string $id): string => $pdo->quote($id), $ids));
                $pdo->exec("UPDATE geo_processor_runs SET state = '" . $state . "', details_json = COALESCE(details_json, '{}'::jsonb) || " . $pdo->quote(json_encode($details, JSON_UNESCAPED_SLASHES)) . "::jsonb, updated_at = now() WHERE id IN ($list)");
            };
            if ($rows === []) {
                $finish = $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id");
                $finish->execute([':details' => json_encode(['processed' => 0, 'trips' => 0, 'thresholds' => $thresholds]), ':id' => $runId]);
                $markPending($pdo, $pendingIds, 'ready', ['processed' => 0, 'covered_by' => $runId]);
                $closeNoop();
                $pdo->commit();
                continue;
            }

            $streams = [];
            foreach ($rows as $row) $streams[(string)$row['device_id']][] = $row;
            $merged = StepsGeolocationTrips::mergeStreams($streams, $thresholds);
            $result = StepsGeolocationTrips::segment($merged, $thresholds);

            $inputFrom = (string)$rows[0]['source_at'];
            $inputTo = (string)$rows[count($rows) - 1]['source_at'];
            $delete = $pdo->prepare(
                "DELETE FROM geo_trips
                  WHERE user_email = :owner AND deleted_at IS NULL
                    AND COALESCE(override_json->>'manual', 'false') <> 'true'
                    AND tstzrange(started_at, ended_at, '[]') && tstzrange(:from, :to, '[]')"
            );
            $delete->execute([':owner' => $owner, ':from' => $inputFrom, ':to' => $inputTo]);

            foreach ($result['trips'] as $trip) {
                steps_trips_rebuild_trip($pdo, $owner, $trip, $version, $thresholds);
                $tripsCreated++;
            }

            $details = [
                'processed' => count($merged),
                'trips' => count($result['trips']),
                'dropped' => $result['dropped'],
                'input_from' => $inputFrom,
                'input_to' => $inputTo,
                'thresholds' => $thresholds,
            ];
            $finish = $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', input_from = :from, input_to = :to, details_json = :details, updated_at = now() WHERE id = :id");
            $finish->execute([
                ':from' => $inputFrom,
                ':to' => $inputTo,
                ':details' => json_encode($details, JSON_UNESCAPED_SLASHES),
                ':id' => $runId,
            ]);
            $markPending($pdo, $pendingIds, 'ready', ['processed' => count($merged), 'covered_by' => $runId]);
            $closeNoop();
            $pdo->commit();
            $processed += count($merged);
            $dropped += $result['dropped'];
            if ($result['trips'] !== []) {
                try { StepsGeolocationStops::enqueue($pdo, $owner); } catch (Throwable $e) { error_log('geolocation stops enqueue: ' . $e->getMessage()); }
            }
            fwrite(STDOUT, "trips: {$owner} {$details['trips']} trip(s) from " . count($merged) . " point(s)\n");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $failed++;
            error_log('geolocation trips run failed: ' . $e->getMessage());
            try {
                $mark = $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', updated_at = now() WHERE id = :id");
                $mark->execute([':id' => $runId]);
                if (!empty($pendingIds)) {
                    $list = implode(',', array_map(static fn(string $id): string => $pdo->quote($id), $pendingIds));
                    $pdo->exec("UPDATE geo_processor_runs SET state = 'failed', updated_at = now() WHERE id IN ($list)");
                }
            } catch (Throwable $ignored) {
            }
        }
    }

    return ['processed' => $processed, 'trips' => $tripsCreated, 'dropped' => $dropped, 'failed' => $failed];
}

$options = getopt('', ['limit::', 'user::', 'loop']);
$limit = isset($options['limit']) && is_numeric($options['limit']) ? max(1, min(10000, (int)$options['limit'])) : 2000;
$user = isset($options['user']) && is_string($options['user']) ? strtolower(trim($options['user'])) : '';
$loop = isset($options['loop']);
$thresholds = StepsGeolocationTrips::thresholds();

do {
    try {
        $result = steps_trips_pass(app_main_db()->getPdo(), $user, $limit, $thresholds);
    } catch (Throwable $e) {
        error_log('geolocation trips worker: ' . $e->getMessage());
        $result = ['processed' => 0, 'trips' => 0, 'dropped' => 0, 'failed' => 1];
    }
    if (!$loop) {
        fwrite(STDOUT, "trips: {$result['processed']} point(s), {$result['trips']} trip(s), {$result['dropped']} dropped, {$result['failed']} run(s) failed\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['processed'] === 0) sleep(15);
    else usleep(100000);
} while (true);
