<?php
/**
 * Bounded analytics cache refresh using the existing durable job queue.
 * It never sends coordinates to another service and never creates public data.
 */
require_once __DIR__ . '/worker_limits.php';
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_analytics.php';

function steps_analytics_pass(PDO $pdo, int $limit = 20): array
{
    $owners = $pdo->query("SELECT user_email FROM geo_processor_runs WHERE kind = 'analytics' AND state = 'pending' GROUP BY user_email ORDER BY MIN(created_at) LIMIT " . max(1, min(100, $limit)))->fetchAll(PDO::FETCH_COLUMN);
    $done = 0;
    $failed = 0;
    foreach ($owners as $owner) {
        $runId = StepsGeolocationStatistics::uuid();
        try {
            $pdo->prepare("INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, lease_until, heartbeat_at, attempts, started_at) VALUES(:id, :owner, 'analytics', 'processing', :version, now() + interval '30 minutes', now(), 1, now())")
                ->execute([':id' => $runId, ':owner' => $owner, ':version' => StepsGeolocationAnalytics::VERSION]);
            StepsGeolocationAnalytics::summary($pdo, (string)$owner, ['timezone' => 'UTC', 'refresh' => true, 'compare' => true]);
            $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id")
                ->execute([':details' => json_encode(['formula_version' => StepsGeolocationAnalytics::VERSION]), ':id' => $runId]);
            $done++;
        } catch (Throwable $e) {
            $failed++;
            error_log('geolocation analytics run failed: ' . $e->getMessage());
            try { $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', last_error = :error, updated_at = now() WHERE id = :id")->execute([':error' => mb_substr($e->getMessage(), 0, 500), ':id' => $runId]); } catch (Throwable $ignored) {}
        }
    }
    return ['processed' => $done, 'failed' => $failed];
}

steps_geolocation_migrate(app_main_db());
$result = steps_analytics_pass(app_main_db()->getPdo());
fwrite(STDOUT, "analytics: {$result['processed']} owner(s) refreshed, {$result['failed']} failed\n");
exit($result['failed'] > 0 ? 1 : 0);
