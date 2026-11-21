<?php
/**
 * Durable geolocation export/backup worker.
 *
 *   php apps/steps/bin/geolocation_export_run.php --loop
 *   php apps/steps/bin/geolocation_export_run.php --user=owner@example.com
 *   php apps/steps/bin/geolocation_export_run.php --export=<uuid>
 *
 * Artifacts are streamed to the existing app data volume. Expired artifacts are
 * removed by the same worker; download endpoints authorize again on every hit.
 */
require_once __DIR__ . '/worker_limits.php';
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_exports.php';

/** @return array{exports:int,failed:int,expired:int} */
function steps_export_pass(PDO $pdo, string $user, string $exportId, int $limit): array
{
    $sql = "SELECT id, user_email FROM geo_exports WHERE state = 'pending'";
    $params = [];
    if ($exportId !== '') { $sql .= ' AND id = :export'; $params[':export'] = $exportId; }
    if ($user !== '') { $sql .= ' AND user_email = :owner'; $params[':owner'] = $user; }
    $sql .= " ORDER BY created_at ASC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = ['exports' => 0, 'failed' => 0, 'expired' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        try {
            $ready = StepsGeolocationExports::process($pdo, (string)$row['user_email'], (string)$row['id']);
            $result['exports']++;
            $bytes = 0;
            foreach (($ready['artifacts'] ?? []) as $artifact) $bytes += (int)($artifact['size_bytes'] ?? 0);
            fwrite(STDOUT, "export: {$row['id']} {$ready['format']} {$bytes} byte(s)\n");
        } catch (Throwable $e) {
            $result['failed']++;
            error_log('geolocation export failed: ' . $e->getMessage());
        }
    }
    try { $result['expired'] = StepsGeolocationExports::cleanupExpired($pdo); } catch (Throwable $e) { error_log('geolocation export cleanup: ' . $e->getMessage()); }
    return $result;
}

$options = getopt('', ['user::', 'export::', 'loop', 'limit::']);
$user = isset($options['user']) && is_string($options['user']) ? strtolower(trim($options['user'])) : '';
$exportId = isset($options['export']) && is_string($options['export']) ? trim($options['export']) : '';
$loop = isset($options['loop']);
$limit = isset($options['limit']) && is_numeric($options['limit']) ? max(1, min(10, (int)$options['limit'])) : 2;
$db = app_main_db();
steps_geolocation_migrate($db);

do {
    try {
        $result = steps_export_pass($db->getPdo(), $user, $exportId, $limit);
    } catch (Throwable $e) {
        error_log('geolocation export worker: ' . $e->getMessage());
        $result = ['exports' => 0, 'failed' => 1, 'expired' => 0];
    }
    if (!$loop) {
        fwrite(STDOUT, "exports: {$result['exports']} job(s), {$result['failed']} failed, {$result['expired']} expired artifact(s)\n");
        exit($result['failed'] > 0 ? 1 : 0);
    }
    if ($result['exports'] === 0) sleep(15);
} while (true);
