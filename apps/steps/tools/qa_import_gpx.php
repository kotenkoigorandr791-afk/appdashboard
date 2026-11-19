<?php
/**
 * QA helper: import a GPX file for one owner through the real import pipeline.
 *
 *   php apps/steps/tools/qa_import_gpx.php <file.gpx> [owner@example.com]
 *
 * It uses StepsGeolocationImports::acceptFile/process exactly like the web
 * upload does, so the resulting rows are identical to a user import.
 */
$root = dirname(__DIR__, 3);
require_once $root . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_imports.php';
require_once dirname(__DIR__) . '/core/geolocation_achievements.php';

$path = $argv[1] ?? '';
$owner = strtolower(trim((string)($argv[2] ?? 'root@root')));
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "usage: php qa_import_gpx.php <file.gpx> [owner]\n");
    exit(2);
}

$db = app_main_db();
steps_geolocation_migrate($db);
$pdo = $db->getPdo();

$import = StepsGeolocationImports::acceptFile($path, basename($path), $owner, ['delete_source' => false], false);
fwrite(STDOUT, 'import_id=' . ($import['id'] ?? '?') . ' state=' . ($import['state'] ?? '?') . PHP_EOL);
$counts = StepsGeolocationImports::process($pdo, $owner, (string)($import['id'] ?? ''));
fwrite(STDOUT, 'processed=' . json_encode($counts, JSON_UNESCAPED_SLASHES) . PHP_EOL);

$row = StepsGeolocationImports::find($pdo, $owner, (string)($import['id'] ?? ''));
fwrite(STDOUT, 'final_state=' . ($row['state'] ?? '?') . PHP_EOL);
StepsGeolocationAchievements::enqueueIfEnabled($pdo, $owner, 'import');
