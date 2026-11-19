<?php
/**
 * Incremental location statistics worker.
 *
 *   php apps/steps/bin/geolocation_statistics_run.php --loop
 *   php apps/steps/bin/geolocation_statistics_run.php --user=owner@example.com
 *
 * Pending geo_processor_runs rows describe bounded local-date ranges produced
 * by ingestion, edits and imports. Everything is aggregated inside PostgreSQL;
 * derived trip/stop/visit rows stay the only source for movement metrics.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';

/** @return array{processed:int,days:int,failed:int} */
function steps_statistics_pass(PDO $pdo, string $user, string $timezone): array
{
    $processed = 0;
    $days = 0;
    $failed = 0;
    $sql = "SELECT id, user_email, input_from, input_to FROM geo_processor_runs WHERE kind = 'statistics' AND state IN ('pending','processing')";
    $params = [];
    if ($user !== '') { $sql .= ' AND user_email = :owner'; $params[':owner'] = $user; }
    $sql .= ' ORDER BY created_at ASC LIMIT 20';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($runs as $run) {
        $owner = (string)$run['user_email'];
        $from = $run['input_from'] !== null ? substr((string)$run['input_from'], 0, 10) : null;
        $to = $run['input_to'] !== null ? substr((string)$run['input_to'], 0, 10) : null;
        if ($from !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = gmdate('Y-m-d', strtotime((string)$run['input_from']));
        if ($to !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = gmdate('Y-m-d', strtotime((string)$run['input_to']));
        try {
            $pdo->prepare("UPDATE geo_processor_runs SET state = 'processing', updated_at = now() WHERE id = :id")->execute([':id' => $run['id']]);
            $count = StepsGeolocationStatistics::refresh($pdo, $owner, $timezone, $from, $to);
            $details = json_encode(['days' => $count, 'timezone' => $timezone, 'from' => $from, 'to' => $to], JSON_UNESCAPED_SLASHES);
            $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id")
                ->execute([':details' => $details, ':id' => $run['id']]);
            $processed++;
            $days += $count;
            fwrite(STDOUT, "statistics: {$owner} {$count} day(s)\n");
        } catch (Throwable $e) {
            $failed++;
            error_log('geolocation statistics run failed: ' . $e->getMessage());
            try { $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', details_json = :details, updated_at = now() WHERE id = :id")->execute([':details' => json_encode(['error' => 'refresh_failed']), ':id' => $run['id']]); } catch (Throwable $ignored) {}
        }
    }
    return ['processed' => $processed, 'days' => $days, 'failed' => $failed];
}

$options = getopt('', ['user::', 'loop', 'tz::']);
$user = isset($options['user']) && is_string($options['user']) ? strtolower(trim($options['user'])) : '';
$loop = isset($options['loop']);
$timezone = StepsGeolocationStatistics::timezone(isset($options['tz']) && is_string($options['tz']) ? $options['tz'] : (getenv('GEO_STATS_TIMEZONE') ?: 'UTC'));
$db = app_main_db();
steps_geolocation_migrate($db);

do {
    try {
        $result = steps_statistics_pass($db->getPdo(), $user, $timezone);
    } catch (Throwable $e) {
        error_log('geolocation statistics worker: ' . $e->getMessage());
        $result = ['processed' => 0, 'days' => 0, 'failed' => 1];
    }
    if (!$loop) {
        fwrite(STDOUT, "statistics: {$result['processed']} run(s), {$result['days']} day(s), {$result['failed']} failed\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['processed'] === 0) sleep(20);
} while (true);
