<?php
/**
 * Central PostgreSQL File Index — integration test.
 *
 * Uses an in-memory SQLite database and throw-away directories. It verifies
 * that listings/search/resolution come from the index, that mutations keep
 * ids stable, and that the cloud HTTP listing path contains no directory
 * scanning calls.
 *
 * Usage: php tests/storage/test_file_index.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/file-index-test-' . bin2hex(random_bytes(4));
@mkdir($tmp . '/appbase', 0775, true);
@mkdir($tmp . '/state', 0775, true);

putenv('APPDATA_BASE_DIR=' . $tmp . '/appbase');
putenv('APPDATA_DATA_DIR=' . $tmp . '/data');
putenv('STORAGE_STATE_DIR=' . $tmp . '/state');
putenv('STORAGE_ROOT=' . $tmp . '/storage');
putenv('STORAGE_DISKS_ROOT=' . $tmp . '/disks');
putenv('STORAGE_SKIP_FINDMNT=1');
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
require_once $root . '/storage_helper.php';
require_once $root . '/lib/Storage/bootstrap.php';

$failures = 0;
$checks = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
    if (!$ok) $failures++;
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
StorageDb::$pdo = $pdo;
FileIndexDb::$pdo = $pdo;
StorageDb::ensureSchema();
FileIndexDb::ensureSchema();

echo "Temp: $tmp\n";

echo "\n[1] Schema\n";
foreach (['storage_files', 'storage_file_outbox', 'storage_index_jobs', 'storage_index_queue', 'storage_index_meta'] as $table) {
    $found = (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $table . "'")->fetchColumn();
    check("table $table exists", $found);
}

$app = 'cloud';
$user = 'alice';
$base = appdata_app_base_dir($app);
@mkdir($base . '/' . $user . '/docs', 0775, true);
file_put_contents($base . '/' . $user . '/readme.txt', "hello\n");
file_put_contents($base . '/' . $user . '/docs/report.txt', "report\n");
@mkdir($base . '/' . $user . '/docs/nested', 0775, true);
file_put_contents($base . '/' . $user . '/docs/nested/deep.bin', str_repeat('x', 128));

echo "\n[2] Root and listing\n";
$rootRow = FileIndex::ensureRoot($app, $user, $user);
check('root row created', (int)$rootRow['id'] > 0);
$listing = FileIndex::listDirectory($app, $user, $user, '');
check('empty root listing from SQL', $listing['items'] === [] && $listing['directory'] !== null);

echo "\n[3] Indexing files and directories\n";
$jobId = FileIndexDb::enqueueJob('initial:test', $app, $user, $user, 'initial', null, ['root_relative' => $user]);
$result = FileIndexWorker::processJob($jobId, 100);
check('index job completes', ($result['action'] ?? '') === 'completed', json_encode($result));
$listing = FileIndex::listDirectory($app, $user, $user, '');
$names = array_column($listing['items'], 'name');
check('root lists indexed children', in_array('readme.txt', $names, true) && in_array('docs', $names, true), json_encode($names));
$docs = FileIndex::findByPath($app, $user, $user . '/docs');
check('nested directory indexed', $docs !== null && $docs['object_type'] === 'directory');
$deep = FileIndex::findByPath($app, $user, $user . '/docs/nested/deep.bin');
check('deep file indexed with size', $deep !== null && (int)$deep['size_bytes'] === 128, json_encode($deep ?: []));

echo "\n[4] Keyset pagination\n";
$page1 = FileIndex::listDirectory($app, $user, $user, '', ['limit' => 1]);
check('first page has one item and a cursor', count($page1['items']) === 1 && $page1['has_more'] && $page1['next'] !== null, json_encode($page1));
$page2 = FileIndex::listDirectory($app, $user, $user, '', [
    'limit' => 1,
    'after_name' => $page1['next']['name'],
    'after_id' => $page1['next']['id'],
]);
check('second page continues without overlap', count($page2['items']) === 1 && $page2['items'][0]['id'] !== $page1['items'][0]['id'], json_encode($page2));

echo "\n[5] Search and isolation\n";
$hits = FileIndex::search($app, $user, $user, 'report');
check('search finds nested file', count($hits) === 1 && $hits[0]['name'] === 'report.txt', json_encode($hits));
check('search is user-scoped', FileIndex::search($app, 'bob', 'bob', 'report') === []);
check('other user cannot read row by path', FileIndex::findByPath($app, 'bob', $user . '/readme.txt') === null);
check('other app cannot read row by path', FileIndex::findByPath('films', $user, $user . '/readme.txt') === null);
check('get() rejects wrong owner', FileIndex::get((int)$deep['id'], $app, 'bob') === null);

echo "\n[6] Stable ids across rename/move\n";
$docsId = (int)$docs['id'];
$deepId = (int)$deep['id'];
@rename($base . '/' . $user . '/docs', $base . '/' . $user . '/renamed');
$moved = FileIndex::recordMove($app, $user, $user, $user . '/docs', $user . '/renamed');
check('move updates rows', $moved >= 3, (string)$moved);
$renamed = FileIndex::findById($docsId);
check('directory keeps its id', $renamed !== null && $renamed['relative_path'] === $user . '/renamed');
$movedDeep = FileIndex::findById($deepId);
check('descendant keeps its id and path', $movedDeep !== null && $movedDeep['relative_path'] === $user . '/renamed/nested/deep.bin', json_encode($movedDeep ?: []));

echo "\n[7] Soft delete and restore\n";
$deleted = FileIndex::recordDelete($app, $user, $user, $user . '/renamed');
check('subtree marked deleted', $deleted >= 3, (string)$deleted);
$rootNames = array_column(FileIndex::listDirectory($app, $user, $user, '')['items'], 'name');
check('deleted subtree disappears from listing', !in_array('renamed', $rootNames, true), json_encode($rootNames));
check('row is preserved for recovery', FileIndex::findById($deepId) !== null && FileIndex::findById($deepId)['status'] === 'deleted');
FileIndex::recordRestore($app, $user, $user, $user . '/renamed');
check('restore reactivates the subtree root', FileIndex::findById($docsId)['status'] === 'active');

echo "\n[8] Physical resolution and path safety\n";
$path = FileIndex::resolvePhysicalPath($deepId, $app, $user, $user);
check('file id resolves to the real path', realpath($base . '/' . $user . '/renamed/nested/deep.bin') === $path, $path);
$threw = false;
try {
    FileIndex::resolvePhysicalPath($deepId, $app, 'bob', 'bob');
} catch (Throwable $e) {
    $threw = true;
}
check('foreign user cannot resolve the file', $threw);
$threw = false;
try {
    FileIndex::normalizeRelative('../etc/passwd');
} catch (Throwable $e) {
    $threw = true;
}
check('path traversal is rejected', $threw);
$threw = false;
try {
    FileIndex::assertInsideRoot('alice', 'bob/secret.txt');
} catch (Throwable $e) {
    $threw = true;
}
check('cross-user relative path is rejected', $threw);

echo "\n[9] Transactional outbox\n";
FileIndex::publish($app, $user, 'record', ['root_relative' => $user, 'relative_path' => $user . '/readme.txt']);
FileIndex::publish($app, $user, 'record', ['root_relative' => $user, 'relative_path' => $user . '/readme.txt']);
$counts = FileIndexDb::outboxCounts();
check('duplicate events are deduplicated', (int)$counts['pending'] === 1, json_encode($counts));
$processed = FileIndexWorker::processOutbox(10);
check('outbox event is processed', (int)$processed['processed'] === 1, json_encode($processed));
check('outbox is empty after processing', (int)FileIndexDb::outboxCounts()['pending'] === 0);

echo "\n[10] External change detection (worker path)\n";
file_put_contents($base . '/' . $user . '/readme.txt', "changed content\n");
FileIndex::publish($app, $user, 'changed', ['root_relative' => $user, 'relative_path' => $user . '/readme.txt']);
FileIndexWorker::processOutbox(10);
$row = FileIndex::findByPath($app, $user, $user . '/readme.txt');
check('changed size is reflected in the index', $row !== null && (int)$row['size_bytes'] === strlen("changed content\n"), json_encode($row ?: []));

echo "\n[11] Missing objects are flagged, never silently dropped\n";
unlink($base . '/' . $user . '/readme.txt');
sleep(1);
FileIndexDb::enqueueJob('reconcile:test', $app, $user, $user, 'reconcile', null, ['root_relative' => $user]);
FileIndexWorker::processJob((int)FileIndexDb::jobByKey('reconcile:test')['id'], 100);
$row = FileIndex::findByPath($app, $user, $user . '/readme.txt');
check('row is kept and marked missing', $row !== null && $row['status'] === 'missing', json_encode($row ?: []));

echo "\n[12] Stats and admin status\n";
$status = FileIndex::status();
check('status reports objects', (int)$status['files'] >= 2 && (int)$status['directories'] >= 2, json_encode($status));
check('status reports outbox and jobs', isset($status['outbox']) && is_array($status['jobs']));

echo "\n[13] No directory scanning in cloud HTTP listing/search\n";
$router = (string)file_get_contents($root . '/apps/cloud/api/actions/router.php');
$listStart = strpos($router, "if (\$action === 'list')");
$listEnd = strpos($router, "if (\$action === 'mkdir')");
$listBlock = $listStart !== false && $listEnd !== false ? substr($router, $listStart, $listEnd - $listStart) : '';
check('list action block was found', $listBlock !== '');
$forbidden = ['scandir(', 'glob(', 'DirectoryIterator', 'RecursiveDirectoryIterator', 'opendir('];
$found = [];
foreach ($forbidden as $needle) {
    if (str_contains($listBlock, $needle)) $found[] = $needle;
}
check('list action does not scan directories', $found === [], implode(', ', $found));
$searchStart = strpos($router, "if (\$action === 'search_tree')");
$searchBlock = $searchStart !== false ? substr($router, $searchStart) : '';
$found = [];
foreach ($forbidden as $needle) {
    if (str_contains($searchBlock, $needle)) $found[] = $needle;
}
check('search action does not scan directories', $found === [], implode(', ', $found));

echo "\nResult: " . ($checks - $failures) . "/$checks checks passed\n";
if ($failures > 0) {
    echo "FAILED\n";
    exit(1);
}
echo "PASSED\n";
