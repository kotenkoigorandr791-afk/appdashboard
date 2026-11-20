<?php
/**
 * Resolves pending visits to OpenStreetMap places through the backend-only
 * Overpass client.
 *
 *   php apps/steps/bin/geolocation_osm_run.php --loop
 *   php apps/steps/bin/geolocation_osm_run.php --user=owner@example.com
 *
 * Only the centre of a confirmed stop and a search radius leave the server.
 */
require_once __DIR__ . '/worker_limits.php';
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_place_resolver.php';
require_once dirname(__DIR__) . '/core/geolocation_observability.php';

/** @return array{processed:int,resolved:int,deferred:int,failed:int} */
function steps_osm_pass(PDO $pdo, StepsGeolocationOsm $osm, string $user, int $limit): array
{
    $sql = "SELECT v.id, v.user_email, v.resolve_attempts, v.departed_at, s.started_at, s.ended_at, ST_Y(s.position) AS lat, ST_X(s.position) AS lon, s.radius_m, s.override_json AS stop_override
              FROM geo_visits v JOIN geo_stops s ON s.id = v.stop_id
             WHERE v.place_id IS NULL AND COALESCE(v.override_json->>'manual', 'false') <> 'true'
               AND (v.detection_status = 'pending' OR (v.detection_status = 'provisional' AND v.detection_method = 'stop_open'))
               AND (v.retry_after IS NULL OR v.retry_after <= now())";
    $params = [];
    if ($user !== '') { $sql .= ' AND v.user_email = :owner'; $params[':owner'] = $user; }
    $sql .= " ORDER BY v.arrived_at DESC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $out = ['processed' => 0, 'resolved' => 0, 'deferred' => 0, 'failed' => 0];
    $owners = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $visit) {
        $out['processed']++;
        try {
            $result = StepsGeolocationPlaceResolver::resolve($pdo, $osm, $visit);
            if (in_array($result['status'], ['confirmed', 'provisional', 'unidentified'], true) && ($result['detail'] ?? '') !== 'stop_open') { $out['resolved']++; $owners[(string)$visit['user_email']] = true; }
            else $out['deferred']++;
        } catch (Throwable $e) {
            $out['failed']++;
            error_log('geolocation osm resolve failed: ' . $e->getMessage());
        }
    }
    foreach (array_keys($owners) as $owner) StepsGeolocationObservability::invalidateOwner($owner);
    return $out;
}

function steps_osm_claim_jobs(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, user_email FROM geo_processor_runs WHERE kind = 'osm_places' AND state = 'pending' ORDER BY created_at LIMIT 10");
    $jobs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $claimed = [];
    foreach ($jobs as $job) {
        $upd = $pdo->prepare("UPDATE geo_processor_runs SET state = 'processing', updated_at = now() WHERE id = :id AND state = 'pending'");
        $upd->execute([':id' => $job['id']]);
        if ($upd->rowCount() > 0) $claimed[] = $job;
    }
    return $claimed;
}

function steps_osm_finish_job(PDO $pdo, string $id, array $result): void
{
    $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = COALESCE(details_json, '{}'::jsonb) || CAST(:d AS jsonb), updated_at = now() WHERE id = :id")
        ->execute([':id' => $id, ':d' => json_encode($result, JSON_UNESCAPED_SLASHES)]);
}

$options = getopt('', ['limit::', 'user::', 'loop']);
$limit = isset($options['limit']) && is_numeric($options['limit']) ? max(1, min(200, (int)$options['limit']) ) : 20;
$user = isset($options['user']) && is_string($options['user']) ? strtolower(trim($options['user'])) : '';
$loop = isset($options['loop']);
$db = app_main_db();
steps_geolocation_migrate($db);
$lastPurge = 0;
do {
    try {
        $pdo = app_main_db()->getPdo();
        $osm = new StepsGeolocationOsm($pdo);
        $jobs = $user === '' ? steps_osm_claim_jobs($pdo) : [];
        $aggregate = ['processed' => 0, 'resolved' => 0, 'deferred' => 0, 'failed' => 0];
        if ($jobs !== []) {
            foreach ($jobs as $job) {
                $result = steps_osm_pass($pdo, $osm, (string)$job['user_email'], $limit);
                foreach ($result as $k => $v) $aggregate[$k] += $v;
                steps_osm_finish_job($pdo, (string)$job['id'], $result);
            }
        } else {
            $result = steps_osm_pass($pdo, $osm, $user, $limit);
            foreach ($result as $k => $v) $aggregate[$k] += $v;
        }
        if (time() - $lastPurge > 3600) { $osm->purgeExpired(); $lastPurge = time(); }
        $result = $aggregate;
    } catch (Throwable $e) {
        error_log('geolocation osm worker: ' . $e->getMessage());
        $result = ['processed' => 0, 'resolved' => 0, 'deferred' => 0, 'failed' => 1];
    }
    if (!$loop) {
        fwrite(STDOUT, "osm: {$result['processed']} visit(s), {$result['resolved']} resolved, {$result['deferred']} deferred, {$result['failed']} failed\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['processed'] === 0) sleep(5);
    else usleep(200000);
} while (true);
