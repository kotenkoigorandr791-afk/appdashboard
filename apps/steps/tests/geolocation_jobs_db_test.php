<?php
/**
 * Durable job queue, lease, retry, cancel, reaping and dedupe checks.
 *
 *   php apps/steps/tests/geolocation_jobs_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_jobs.php';

function geo_jobs_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-geo-jobs@example.test';
$worker = 'qa-worker:1';
$db = app_main_db();
$pdo = $db->getPdo();
steps_geolocation_migrate($db);
$pdo->prepare('DELETE FROM geo_processor_runs WHERE user_email = :owner')->execute([':owner' => $owner]);
$index = $pdo->query("SELECT 1 FROM pg_indexes WHERE indexname = 'geo_geofence_events_dedupe_idx'")->fetchColumn();
geo_jobs_assert((bool)$index, 'geofence dedupe index missing');
$flags = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'geo_feature_flags'")->fetchColumn();
geo_jobs_assert((bool)$flags, 'feature flags table missing');

try {
    putenv('GEO_MAX_ACTIVE_JOBS_PER_USER=1');
    $a = StepsGeolocationJobs::enqueue($pdo, $owner, 'qa_job', ['priority' => 'bulk', 'max_attempts' => 3]);
    $b = StepsGeolocationJobs::enqueue($pdo, $owner, 'qa_job', ['priority' => 'critical', 'max_attempts' => 2]);
    geo_jobs_assert($a !== $b, 'enqueue ids not unique');
    $dedupe = StepsGeolocationJobs::enqueue($pdo, $owner, 'qa_dedupe', ['dedupe_key' => 'same']);
    $dedupeAgain = StepsGeolocationJobs::enqueue($pdo, $owner, 'qa_dedupe', ['dedupe_key' => 'same']);
    geo_jobs_assert($dedupe === $dedupeAgain, 'dedupe key did not return existing job');

    $claimed = StepsGeolocationJobs::claim($pdo, $worker, ['qa_job'], 5);
    geo_jobs_assert(count($claimed) === 1, 'per-user concurrency limit not enforced');
    geo_jobs_assert($claimed[0]['id'] === $b, 'priority/order not honored');
    $activeId = (string)$claimed[0]['id'];
    $otherClaim = StepsGeolocationJobs::claim($pdo, $worker, ['qa_job'], 5);
    geo_jobs_assert($otherClaim === [], 'second concurrent job claimed for same user');
    geo_jobs_assert(StepsGeolocationJobs::heartbeat($pdo, $activeId, $worker), 'heartbeat failed');
    geo_jobs_assert(!StepsGeolocationJobs::heartbeat($pdo, $activeId, 'wrong-worker'), 'wrong worker renewed lease');
    geo_jobs_assert(StepsGeolocationJobs::cancel($pdo, $activeId, $owner), 'cancel failed');
    geo_jobs_assert(StepsGeolocationJobs::isCancelled($pdo, $activeId), 'cancel not visible');
    geo_jobs_assert(!StepsGeolocationJobs::heartbeat($pdo, $activeId, $worker), 'heartbeat continued after cancel');

    $next = StepsGeolocationJobs::claim($pdo, $worker, ['qa_job'], 1);
    geo_jobs_assert(count($next) === 1, 'pending job not claimed after cancellation');
    geo_jobs_assert($next[0]['id'] === $a, 'wrong pending job claimed');
    geo_jobs_assert(StepsGeolocationJobs::fail($pdo, (string)$next[0]['id'], 'upstream 500', true) === 'retry', 'retryable failure did not retry');
    $state = $pdo->prepare('SELECT state, attempts, next_attempt_at > now() FROM geo_processor_runs WHERE id = :id');
    $state->execute([':id' => $next[0]['id']]);
    $row = $state->fetch(PDO::FETCH_NUM);
    geo_jobs_assert($row[0] === 'pending' && (int)$row[1] === 1 && $row[2] === true, 'backoff state wrong');
    $pdo->prepare('UPDATE geo_processor_runs SET next_attempt_at = now() WHERE id = :id')->execute([':id' => $next[0]['id']]);
    $retry = StepsGeolocationJobs::claim($pdo, $worker, ['qa_job'], 1);
    geo_jobs_assert(count($retry) === 1 && (int)$retry[0]['attempts'] === 2, 'retry not claimed');
    StepsGeolocationJobs::complete($pdo, (string)$retry[0]['id'], ['ok' => true]);
    geo_jobs_assert($pdo->query("SELECT state FROM geo_processor_runs WHERE id = " . $pdo->quote($retry[0]['id']))->fetchColumn() === 'ready', 'completion state wrong');

    // Expired lease is requeued; exhausted attempts fail instead of looping.
    $pdo->prepare('DELETE FROM geo_processor_runs WHERE user_email = :owner')->execute([':owner' => $owner]);
    $expired = StepsGeolocationJobs::enqueue($pdo, $owner, 'qa_job', ['max_attempts' => 3]);
    $claimed = StepsGeolocationJobs::claim($pdo, $worker, ['qa_job'], 1);
    geo_jobs_assert($claimed[0]['id'] === $expired, 'expired test job not claimed');
    $pdo->prepare("UPDATE geo_processor_runs SET lease_until = now() - interval '1 minute' WHERE id = :id")->execute([':id' => $expired]);
    $reaped = StepsGeolocationJobs::reap($pdo);
    geo_jobs_assert($reaped['requeued'] >= 1, 'expired lease not requeued');
    geo_jobs_assert($pdo->query("SELECT state FROM geo_processor_runs WHERE id = " . $pdo->quote($expired))->fetchColumn() === 'pending', 'reaped state wrong');

    $pdo->prepare('DELETE FROM geo_processor_runs WHERE user_email = :owner')->execute([':owner' => $owner]);
    $dead = StepsGeolocationJobs::enqueue($pdo, $owner, 'qa_job', ['max_attempts' => 1]);
    $claimed = StepsGeolocationJobs::claim($pdo, $worker, ['qa_job'], 1);
    $pdo->prepare("UPDATE geo_processor_runs SET lease_until = now() - interval '1 minute' WHERE id = :id")->execute([':id' => $dead]);
    StepsGeolocationJobs::reap($pdo);
    geo_jobs_assert($pdo->query("SELECT state FROM geo_processor_runs WHERE id = " . $pdo->quote($dead))->fetchColumn() === 'failed', 'exhausted lease not failed');

    // Retention cleanup keeps the newest 20 rows per owner/kind.
    $old = $pdo->prepare("INSERT INTO geo_processor_runs(id,user_email,kind,state,processor_version,details_json,created_at) VALUES(:id,:owner,'qa_cleanup','ready','qa','{}',now() - interval '2 days')");
    for ($i = 0; $i < 25; $i++) $old->execute([':id' => StepsGeolocationJobs::uuid(), ':owner' => $owner]);
    StepsGeolocationJobs::cleanup($pdo, 1);
    $left = $pdo->prepare("SELECT COUNT(*) FROM geo_processor_runs WHERE user_email = :owner AND kind = 'qa_cleanup'");
    $left->execute([':owner' => $owner]);
    geo_jobs_assert((int)$left->fetchColumn() === 20, 'cleanup retention count wrong');

    StepsGeolocationJobs::enqueue($pdo, $owner, 'qa_job', ['priority' => 'bulk']);
    try {
        StepsGeolocationJobs::assertCapacity($pdo, $owner, 'qa_job', 1);
        throw new RuntimeException('capacity was not enforced');
    } catch (StepsGeolocationJobsError $e) {
        geo_jobs_assert($e->errorCode === 'queue_full' && $e->status === 429, 'capacity error wrong');
    }

    // Exact duplicate geofence transitions are rejected by the unique index.
    $placeId = StepsGeolocationJobs::uuid();
    $geofenceId = StepsGeolocationJobs::uuid();
    $pdo->prepare("INSERT INTO geo_places(id,user_email,name,tag,position,radius_m) VALUES(:id,:owner,'QA place','test',ST_SetSRID(ST_MakePoint(30.5,50.4),4326),100)")
        ->execute([':id' => $placeId, ':owner' => $owner]);
    $pdo->prepare("INSERT INTO geo_geofences(id,user_email,place_id,name,geometry) VALUES(:id,:owner,:place,'QA fence',ST_SetSRID(ST_MakePoint(30.5,50.4),4326))")
        ->execute([':id' => $geofenceId, ':owner' => $owner, ':place' => $placeId]);
    $event = $pdo->prepare("INSERT INTO geo_geofence_events(id,user_email,geofence_id,event_type,occurred_at) VALUES(:id,:owner,:fence,'enter',:at)");
    $at = gmdate('c', time() - 60);
    $event->execute([':id' => StepsGeolocationJobs::uuid(), ':owner' => $owner, ':fence' => $geofenceId, ':at' => $at]);
    $duplicateRejected = false;
    try {
        $event->execute([':id' => StepsGeolocationJobs::uuid(), ':owner' => $owner, ':fence' => $geofenceId, ':at' => $at]);
    } catch (PDOException $e) {
        $duplicateRejected = true;
    }
    geo_jobs_assert($duplicateRejected, 'duplicate geofence event was accepted');
    echo "ok\n";
} finally {
    putenv('GEO_MAX_ACTIVE_JOBS_PER_USER');
    $pdo->prepare('DELETE FROM geo_processor_runs WHERE user_email = :owner')->execute([':owner' => $owner]);
    $pdo->prepare('DELETE FROM geo_geofence_events WHERE user_email = :owner')->execute([':owner' => $owner]);
    $pdo->prepare('DELETE FROM geo_geofences WHERE user_email = :owner')->execute([':owner' => $owner]);
    $pdo->prepare('DELETE FROM geo_places WHERE user_email = :owner')->execute([':owner' => $owner]);
}
