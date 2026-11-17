<?php
/**
 * Achievements recalculation worker (task 27).
 *
 *   php apps/steps/bin/geolocation_achievements_run.php --loop
 *   php apps/steps/bin/geolocation_achievements_run.php --user=owner@example.com
 *
 * Pending geo_processor_runs rows of kind 'achievements' are produced by
 * imports, edits, deletions and preference changes. Every pass is bounded and
 * deterministic; disabled owners are skipped without touching stored rows.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_achievements.php';

/** @return array{processed:int,rows:int,skipped:int,failed:int} */
function steps_achievements_pass(PDO $pdo, string $user): array
{
    $sql = "SELECT id, user_email, details_json FROM geo_processor_runs WHERE kind = 'achievements' AND state IN ('pending','processing')";
    $params = [];
    if ($user !== '') { $sql .= ' AND user_email = :owner'; $params[':owner'] = $user; }
    $sql .= ' ORDER BY created_at ASC LIMIT 20';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = ['processed' => 0, 'rows' => 0, 'skipped' => 0, 'failed' => 0];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $run) {
        $owner = (string)$run['user_email'];
        $details = json_decode((string)$run['details_json'], true);
        $reason = is_array($details) ? (string)($details['reason'] ?? 'scheduled') : 'scheduled';
        try {
            $pdo->prepare("UPDATE geo_processor_runs SET state = 'processing', updated_at = now() WHERE id = :id")->execute([':id' => $run['id']]);
            $outcome = StepsGeolocationAchievements::recalculate($pdo, $owner, $reason);
            if (!empty($outcome['skipped'])) {
                $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id")
                    ->execute([':details' => json_encode(['skipped' => true, 'reason' => $outcome['reason'] ?? 'disabled'], JSON_UNESCAPED_SLASHES), ':id' => $run['id']]);
                $result['skipped']++;
                continue;
            }
            $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id")
                ->execute([':details' => json_encode($outcome, JSON_UNESCAPED_SLASHES), ':id' => $run['id']]);
            $result['processed']++;
            $result['rows'] += (int)($outcome['rows'] ?? 0);
            fwrite(STDOUT, "achievements: {$owner} " . (int)($outcome['rows'] ?? 0) . " row(s), reason {$reason}\n");
        } catch (Throwable $e) {
            $result['failed']++;
            error_log('geolocation achievements run failed: ' . $e->getMessage());
            try {
                $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', details_json = :details, updated_at = now() WHERE id = :id")
                    ->execute([':details' => json_encode(['error' => 'recalculate_failed']), ':id' => $run['id']]);
            } catch (Throwable $ignored) {
            }
        }
    }
    return $result;
}

$options = getopt('', ['user::', 'loop']);
$user = isset($options['user']) && is_string($options['user']) ? strtolower(trim($options['user'])) : '';
$loop = isset($options['loop']);
$db = app_main_db();
steps_geolocation_migrate($db);

do {
    try {
        $result = steps_achievements_pass($db->getPdo(), $user);
    } catch (Throwable $e) {
        error_log('geolocation achievements worker: ' . $e->getMessage());
        $result = ['processed' => 0, 'rows' => 0, 'skipped' => 0, 'failed' => 1];
    }
    if (!$loop) {
        fwrite(STDOUT, "achievements: {$result['processed']} run(s), {$result['rows']} row(s), {$result['skipped']} skipped, {$result['failed']} failed\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['processed'] === 0 && $result['skipped'] === 0) sleep(20);
} while (true);
