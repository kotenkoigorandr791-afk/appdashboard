<?php
/**
 * Storage migration worker.
 *
 * Usage: php shell/storage/worker.php --migration=<16 hex id>
 *
 * Runs one resumable migration outside the HTTP request.
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
chdir($root);
require_once $root . '/db.php';
require_once $root . '/lib/Storage/bootstrap.php';

$migrationId = '';
foreach (($argv ?? []) as $arg) {
    if (str_starts_with((string)$arg, '--migration=')) {
        $migrationId = substr((string)$arg, strlen('--migration='));
    }
}
if (!preg_match('/^[a-f0-9]{16}$/', $migrationId)) {
    fwrite(STDERR, "Invalid migration id\n");
    exit(2);
}

try {
    StorageDb::ensureSchema();
    DiskMigration::run($migrationId);
} catch (Throwable $e) {
    fwrite(STDERR, 'Storage worker: ' . $e->getMessage() . "\n");
    try {
        StorageDb::updateMigration($migrationId, [
            'status' => 'failed',
            'error' => substr($e->getMessage(), 0, 4000),
            'completed_at' => time(),
        ]);
        StorageManager::endMaintenance($migrationId);
    } catch (Throwable $ignored) {}
    exit(1);
}
exit(0);
