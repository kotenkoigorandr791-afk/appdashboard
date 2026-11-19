<?php
/**
 * Immich match worker. Only bounded date windows and metadata are fetched.
 *
 *   php apps/steps/bin/geolocation_immich_run.php --loop
 *   php apps/steps/bin/geolocation_immich_run.php --user=owner@example.com
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_immich.php';

/** @return array{processed:int,failed:int} */
function steps_immich_pass(PDO $pdo, string $user): array
{
    $sql = "SELECT id, user_email, input_from, input_to FROM geo_processor_runs WHERE kind = 'immich' AND state IN ('pending','processing')";
    $params = [];
    if ($user !== '') { $sql .= ' AND user_email = :owner'; $params[':owner'] = $user; }
    $sql .= ' ORDER BY created_at ASC LIMIT 5';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = ['processed' => 0, 'failed' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $run) {
        try {
            $pdo->prepare("UPDATE geo_processor_runs SET state = 'processing', updated_at = now() WHERE id = :id")->execute([':id' => $run['id']]);
            $counts = StepsGeolocationImmich::sync($pdo, (string)$run['user_email'], $run['input_from'] ? (string)$run['input_from'] : null, $run['input_to'] ? (string)$run['input_to'] : null);
            $pdo->prepare("UPDATE geo_processor_runs SET state = 'ready', details_json = :details, updated_at = now() WHERE id = :id")
                ->execute([':details' => json_encode($counts, JSON_UNESCAPED_SLASHES), ':id' => $run['id']]);
            $result['processed']++;
            fwrite(STDOUT, "immich: {$run['user_email']} matched={$counts['matched']} unmatched={$counts['unmatched']}\n");
        } catch (Throwable $e) {
            $result['failed']++;
            error_log('geolocation immich run failed: ' . $e->getMessage());
            try { $pdo->prepare("UPDATE geo_processor_runs SET state = 'failed', details_json = :details, updated_at = now() WHERE id = :id")->execute([':details' => json_encode(['error' => 'immich_failed']), ':id' => $run['id']]); } catch (Throwable $ignored) {}
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
    try { $result = steps_immich_pass($db->getPdo(), $user); }
    catch (Throwable $e) { error_log('geolocation immich worker: ' . $e->getMessage()); $result = ['processed' => 0, 'failed' => 1]; }
    if (!$loop) {
        fwrite(STDOUT, "immich: {$result['processed']} job(s), {$result['failed']} failed\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['processed'] === 0) sleep(20);
} while (true);
