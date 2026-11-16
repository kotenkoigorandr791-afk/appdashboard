<?php
/**
 * PostgreSQL smoke test for the storage state layer and the mandatory dump.
 *
 * Run inside the image against a throw-away PostgreSQL database:
 *   TEST_PG_HOST=... TEST_PG_PORT=5432 TEST_PG_NAME=appdashboard \
 *   TEST_PG_USER=appdashboard TEST_PG_PASSWORD=... \
 *   php tests/storage/pg_smoke.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
putenv('STORAGE_ROOT=/storage');
putenv('STORAGE_DISKS_ROOT=/disks');
putenv('APPDATA_DATA_DIR=/data');
putenv('STORAGE_STATE_DIR=/data/storage');
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
require_once $root . '/storage_helper.php';
require_once $root . '/lib/Storage/bootstrap.php';
require_once $root . '/lib/db_config.php';
require_once $root . '/project/backup/helpers.php';
require_once $root . '/project/backup/api.php';

$failures = 0;
$checks = 0;
function pgCheck(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
    if (!$ok) $failures++;
}

$host = (string)(getenv('TEST_PG_HOST') ?: '127.0.0.1');
$port = (string)(getenv('TEST_PG_PORT') ?: '5432');
$name = (string)(getenv('TEST_PG_NAME') ?: 'appdashboard');
$user = (string)(getenv('TEST_PG_USER') ?: 'appdashboard');
$pass = (string)(getenv('TEST_PG_PASSWORD') ?: 'test');

$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$name", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
StorageDb::$pdo = $pdo;
StorageDb::ensureSchema();
pgCheck('schema is created on PostgreSQL', true);

$id = StorageDb::insertDisk([
    'uuid' => 'marker:pg-smoke-1',
    'name' => 'hdd1',
    'mount_path' => '/disks/hdd1',
    'filesystem' => 'ext4',
    'total_bytes' => 1000,
    'used_bytes' => 100,
    'free_bytes' => 900,
    'status' => 'online',
    'identity_source' => 'marker',
]);
pgCheck('insertDisk returns the sequence id', $id > 0, (string)$id);
$disk = StorageDb::diskByUuid('marker:pg-smoke-1');
pgCheck('disk is readable by UUID', is_array($disk) && (int)$disk['id'] === $id);
StorageDb::updateDisk($id, ['free_bytes' => 800, 'status' => 'online']);
pgCheck('disk update works', (int)(StorageDb::diskById($id)['free_bytes'] ?? 0) === 800);

$mid = bin2hex(random_bytes(8));
StorageDb::createMigration($mid, $id, ['test' => true]);
pgCheck('migration row is created', StorageDb::migration($mid) !== null);
StorageDb::addMigrationFile($mid, 'documents/a.txt', 10, 'file');
StorageDb::addMigrationFile($mid, 'documents/a.txt', 10, 'file');
pgCheck('migration file upsert is idempotent', count(StorageDb::migrationFiles($mid)) === 1);
$file = StorageDb::migrationFiles($mid)[0];
StorageDb::updateMigrationFile((int)$file['id'], ['checksum' => 'abc', 'status' => 'done']);
StorageDb::updateMigration($mid, ['status' => 'migrating', 'migrated_files' => 1, 'progress' => 50.5]);
$migration = StorageDb::migration($mid);
pgCheck('migration updates keep float progress', $migration !== null && abs((float)$migration['progress'] - 50.5) < 0.001, json_encode($migration));
StorageDb::addEvent('disk_registered', 'pg smoke', $id, ['ok' => true], $mid);
$events = StorageDb::events(10, $id);
pgCheck('events are stored with JSON metadata', count($events) === 1 && !empty($events[0]['metadata']['ok']));

// Real pg_dump through the mandatory-backup path.
putenv('DB_HOST=' . $host);
putenv('DB_PORT=' . $port);
putenv('DB_NAME=' . $name);
putenv('DB_USER=' . $user);
putenv('DB_PASSWORD=' . $pass);
putenv('DB_SSLMODE=prefer');
$dump = AppDataBackup::dumpDatabase();
pgCheck('pg_dump succeeds against PostgreSQL', $dump['ok'], $dump['error']);
pgCheck('pg_dump output is a custom-format dump', $dump['ok'] && filesize($dump['path']) > 0 && file_get_contents($dump['path'], false, null, 0, 5) === 'PGDMP');

// Clean up this throw-away database's storage tables.
$pdo->exec('DROP TABLE IF EXISTS storage_events, storage_migration_files, storage_migrations, storage_disks CASCADE');
pgCheck('smoke tables cleaned up', true);

echo "\nResult: " . ($checks - $failures) . "/$checks checks passed\n";
exit($failures > 0 ? 1 : 0);
