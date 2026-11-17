<?php
/**
 * Bounded location import worker.
 *
 *   php apps/steps/bin/geolocation_import_run.php --loop
 *   php apps/steps/bin/geolocation_import_run.php --user=owner@example.com
 *   php apps/steps/bin/geolocation_import_run.php --import=<uuid>
 *
 * Parsers stream from the app data volume and feed the same ingestion
 * repository as mobile uploads. Progress and per-file errors stay in
 * PostgreSQL, so the status API can show partial failures.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_imports.php';
require_once dirname(__DIR__) . '/core/geolocation_achievements.php';

/** @return array{imports:int,points:int,failed:int} */
function steps_import_pass(PDO $pdo, string $user, string $importId, int $limit): array
{
    $sql = "SELECT id, user_email FROM geo_imports WHERE state IN ('pending','processing') AND deleted_at IS NULL";
    $params = [];
    if ($importId !== '') { $sql .= ' AND id = :import'; $params[':import'] = $importId; }
    if ($user !== '') { $sql .= ' AND user_email = :owner'; $params[':owner'] = $user; }
    $sql .= " ORDER BY created_at ASC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = ['imports' => 0, 'points' => 0, 'failed' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        try {
            $counts = StepsGeolocationImports::process($pdo, (string)$row['user_email'], (string)$row['id']);
            $result['imports']++;
            $result['points'] += (int)$counts['points'];
            if ((int)$counts['failed'] > 0) $result['failed']++;
            fwrite(STDOUT, "import: {$row['id']} {$counts['points']} point(s), {$counts['accepted']} accepted, {$counts['duplicate']} duplicate, {$counts['failed']} failed file(s)\n");
            StepsGeolocationAchievements::enqueueIfEnabled($pdo, (string)$row['user_email'], 'import');
        } catch (Throwable $e) {
            $result['failed']++;
            error_log('geolocation import failed: ' . $e->getMessage());
            try {
                $pdo->prepare("UPDATE geo_imports SET state = 'failed', error_json = :error, updated_at = now() WHERE id = :id")
                    ->execute([':error' => json_encode(['error' => 'import_failed'], JSON_UNESCAPED_SLASHES), ':id' => $row['id']]);
            } catch (Throwable $ignored) {}
        }
    }
    return $result;
}

$options = getopt('', ['user::', 'import::', 'loop', 'limit::']);
$user = isset($options['user']) && is_string($options['user']) ? strtolower(trim($options['user'])) : '';
$importId = isset($options['import']) && is_string($options['import']) ? trim($options['import']) : '';
$loop = isset($options['loop']);
$limit = isset($options['limit']) && is_numeric($options['limit']) ? max(1, min(20, (int)$options['limit'])) : 3;
$db = app_main_db();
steps_geolocation_migrate($db);

do {
    try {
        $result = steps_import_pass($db->getPdo(), $user, $importId, $limit);
    } catch (Throwable $e) {
        error_log('geolocation import worker: ' . $e->getMessage());
        $result = ['imports' => 0, 'points' => 0, 'failed' => 1];
    }
    if (!$loop) {
        fwrite(STDOUT, "import: {$result['imports']} import(s), {$result['points']} point(s), {$result['failed']} failed\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['imports'] === 0) sleep(15);
} while (true);
