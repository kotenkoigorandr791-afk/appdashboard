<?php
/**
 * Every local application through the central file index.
 *
 * Verifies that cloud (incl. the AI assistant), films, recipes, spends, steps
 * and smart_home read and write their user files through AppFiles/FileIndex:
 * listings come from SQL, mutations keep the index in sync, ids stay stable and
 * no application-level listing code scans a directory.
 *
 * Usage: php tests/storage/test_apps_file_index.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/app-files-test-' . bin2hex(random_bytes(4));
@mkdir($tmp . '/appbase', 0775, true);
@mkdir($tmp . '/state', 0775, true);
@mkdir($tmp . '/data', 0775, true);

putenv('APPDATA_BASE_DIR=' . $tmp . '/appbase');
putenv('APPDATA_DATA_DIR=' . $tmp . '/data');
putenv('UPLOADS_BASE_DIR=' . $tmp . '/appbase/dashboard/uploads');
putenv('STORAGE_STATE_DIR=' . $tmp . '/state');
putenv('STORAGE_ROOT=' . $tmp . '/storage');
putenv('STORAGE_DISKS_ROOT=' . $tmp . '/disks');
putenv('STORAGE_SKIP_FINDMNT=1');
putenv('FILE_INDEX_ENABLED=1');
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
require_once $root . '/storage_helper.php';
require_once $root . '/lib/Storage/bootstrap.php';
require_once $root . '/lib/Storage/AppFiles.php';
require_once $root . '/apps/recipes/core/file_index.php';
require_once $root . '/apps/films/core/file_index.php';

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

echo "Temp appbase: $tmp/appbase\n";

function t_write(string $path, string $content = "x"): void
{
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
    file_put_contents($path, $content);
}

function t_base(string $app): string
{
    return rtrim(appdata_app_base_dir($app), '/');
}

/* ------------------------------------------------------------------ */
echo "\n[1] Cloud: user listing through the index\n";
$user = 'alice';
t_write(t_base('cloud') . '/alice/hello.txt', "hello\n");
t_write(t_base('cloud') . '/alice/docs/report.txt', "report\n");
t_write(t_base('cloud') . '/alice/docs/reports/deep.bin', str_repeat('d', 300));
$cloud = AppFiles::listUser('cloud', $user, 'main', '');
$names = array_column($cloud['entries'], 'name');
check('cloud root listing comes from SQL', in_array('hello.txt', $names, true) && in_array('docs', $names, true), json_encode($names));
$docs = AppFiles::listUser('cloud', $user, 'main', 'docs');
check('cloud nested directory listed', in_array('report.txt', array_column($docs['entries'], 'name'), true), json_encode($docs['entries']));
$deepRow = AppFiles::find('cloud', $user, $user, $user . '/docs/reports/deep.bin');
check('cloud deep file indexed with size', $deepRow !== null && (int)$deepRow['size_bytes'] === 300, json_encode($deepRow ?: []));
$abs = AppFiles::absoluteUser('cloud', $user, 'main', 'docs/reports/deep.bin');
check('cloud file id resolves to physical path', is_file($abs), $abs);
check('cloud search scoped to the user', count(AppFiles::treeUser('cloud', $user, 'main', 'docs')) === 3, json_encode(AppFiles::treeUser('cloud', $user, 'main', 'docs')));
check('cloud IDs are stable across repeat listings', AppFiles::listUser('cloud', $user, 'main', '')['entries'][0]['id'] === $cloud['entries'][0]['id']);

/* ------------------------------------------------------------------ */
echo "\n[2] Cloud documents root is a separate indexed root\n";
t_write(t_base('cloud') . '/alice/.documents/contract.pdf', "pdf\n");
$docRoot = AppFiles::userRootRelative('cloud', $user, 'documents');
check('documents root maps to the .documents subdir', $docRoot === 'alice/.documents', $docRoot);
$docList = AppFiles::listUser('cloud', $user, 'documents', '');
check('documents listing from the index', in_array('contract.pdf', array_column($docList['entries'], 'name'), true), json_encode($docList['entries']));

/* ------------------------------------------------------------------ */
echo "\n[3] AI assistant: file tools use the index\n";
$aiRoot = AppFiles::userRootRelative('cloud', $user, 'main');
$flat = AppFiles::listByRoot('cloud', $user, $aiRoot, 'docs', ['limit' => 100]);
check('assistant list_directory gets entries from the index', array_column($flat['entries'], 'name') === ['report.txt', 'reports'] || in_array('report.txt', array_column($flat['entries'], 'name'), true), json_encode($flat['entries']));
$recursive = AppFiles::treeByRoot('cloud', $user, $aiRoot, 'docs', 100);
$recursivePaths = array_column($recursive, 'path');
check('assistant recursive listing uses one SQL prefix query', in_array('report.txt', $recursivePaths, true) && in_array('reports/deep.bin', $recursivePaths, true), json_encode($recursivePaths));
// A write performed by the assistant is reflected immediately.
t_write(t_base('cloud') . '/alice/docs/new-from-ai.md', "ai\n");
AppFiles::refresh('cloud', $user, $aiRoot, $user . '/docs/new-from-ai.md');
check('assistant write is indexed', AppFiles::existsAt('cloud', $user, $aiRoot, $user . '/docs/new-from-ai.md'));
// A rename keeps the id stable.
$beforeId = (int)AppFiles::find('cloud', $user, $aiRoot, $user . '/docs/new-from-ai.md')['id'];
rename(t_base('cloud') . '/alice/docs/new-from-ai.md', t_base('cloud') . '/alice/docs/renamed-by-ai.md');
AppFiles::recordMove('cloud', $user, $aiRoot, $user . '/docs/new-from-ai.md', $user . '/docs/renamed-by-ai.md');
$moved = AppFiles::find('cloud', $user, $aiRoot, $user . '/docs/renamed-by-ai.md');
check('assistant rename keeps the id', $moved !== null && (int)$moved['id'] === $beforeId, json_encode($moved ?: []));
AppFiles::recordDelete('cloud', $user, $aiRoot, $user . '/docs/renamed-by-ai.md');
check('assistant delete is reflected in the index', !AppFiles::existsAt('cloud', $user, $aiRoot, $user . '/docs/renamed-by-ai.md'));

/* ------------------------------------------------------------------ */
echo "\n[4] Films: shared uploads/comments folders\n";
$filmFile = t_base('films') . '/uploads/movie_123.mp4';
t_write($filmFile, str_repeat('v', 512));
check('films media is visible through the index', films_index_file_exists($filmFile));
check('films media is not visible for unknown paths', !films_index_file_exists(t_base('films') . '/uploads/missing.mp4'));
films_index_record($filmFile);
$filmsRel = films_index_relpath($filmFile);
$filmsRoot = AppFiles::folderRoot($filmsRel);
$filmsList = AppFiles::listByRoot('films', $filmsRoot, $filmsRoot, '');
check('films uploads listing from the index', in_array('movie_123.mp4', array_column($filmsList['entries'], 'name'), true), json_encode($filmsList['entries']));
$commentFile = t_base('films') . '/comments/m1.json';
t_write($commentFile, '{"comments":[]}');
films_index_record($commentFile);
check('films comment file indexed', films_index_file_exists($commentFile));
films_index_forget($filmFile);
check('films delete removes the row from listings', !films_index_file_exists($filmFile));

/* ------------------------------------------------------------------ */
echo "\n[5] Recipes: product media in the shared products folder\n";
$productImage = t_base('recipes') . '/products/p1/image.webp';
t_write($productImage, str_repeat('i', 64));
check('recipes media existence comes from the index', recipes_index_file_exists(recipes_index_relpath('products', 'p1', 'image.webp')));
check('recipes missing media reports false', !recipes_index_file_exists(recipes_index_relpath('products', 'p1', 'nope.webp')));
recipes_index_record(recipes_index_relpath('products', 'p1', 'image.webp'));
$recipeList = AppFiles::listFolder('recipes', 'products/p1');
check('recipes product folder listing from the index', in_array('image.webp', array_column($recipeList['entries'], 'name'), true), json_encode($recipeList['entries']));
t_write(t_base('recipes') . '/products/p1/product.json', '{"id":"p1"}');
recipes_index_record('products/p1/product.json');
check('recipes registers product.json without a scan', AppFiles::existsAt('recipes', 'products', 'products', 'products/p1/product.json'));

/* ------------------------------------------------------------------ */
echo "\n[6] Spends: per-user backup folder\n";
$safe = 'alice_example_com';
t_write(t_base('spends') . '/backups/' . $safe . '/budget.csv', "Date,Store\n");
t_write(t_base('spends') . '/backups/' . $safe . '/older.csv', "Date,Store\n");
$spendsRoot = 'backups';
$spendsList = AppFiles::listByRoot('spends', $spendsRoot, $spendsRoot, $safe, ['limit' => 50]);
$spendsNames = array_column($spendsList['entries'], 'name');
check('spends backup listing from the index', in_array('budget.csv', $spendsNames, true) && in_array('older.csv', $spendsNames, true), json_encode($spendsNames));
$spendsAbs = AppFiles::absolute('spends', $spendsRoot, $spendsRoot, $spendsRoot . '/' . $safe . '/budget.csv');
check('spends backup resolves to a real file', is_file($spendsAbs), $spendsAbs);
AppFiles::recordDelete('spends', $spendsRoot, $spendsRoot, $spendsRoot . '/' . $safe . '/older.csv');
check('spends deleted backup disappears from the listing', !in_array('older.csv', array_column(AppFiles::listByRoot('spends', $spendsRoot, $spendsRoot, $safe)['entries'], 'name'), true));

/* ------------------------------------------------------------------ */
echo "\n[7] Steps: per-user backups folder\n";
t_write(t_base('steps') . '/' . $safe . '/backups/Steps_backup_2026-01-01_00-00-00.csv', "date,steps\n");
$stepsRoot = $safe;
$stepsList = AppFiles::listByRoot('steps', $stepsRoot, $stepsRoot, 'backups');
check('steps backup listing from the index', in_array('Steps_backup_2026-01-01_00-00-00.csv', array_column($stepsList['entries'], 'name'), true), json_encode($stepsList['entries']));
check('steps backup resolves to a real file', is_file(AppFiles::absolute('steps', $stepsRoot, $stepsRoot, $stepsRoot . '/backups/Steps_backup_2026-01-01_00-00-00.csv')));

/* ------------------------------------------------------------------ */
echo "\n[8] Smart Home: data folder is indexed too\n";
$shRoot = 'data';
t_write(t_base('smart_home') . '/' . $shRoot . '/alice/devices.json', '{"devices":[]}');
$shList = AppFiles::listByRoot('smart_home', $shRoot, $shRoot, 'alice');
check('smart_home data listing from the index', in_array('devices.json', array_column($shList['entries'], 'name'), true), json_encode($shList['entries']));

/* ------------------------------------------------------------------ */
echo "\n[9] Isolation between applications and users\n";
check('another app cannot read cloud rows', AppFiles::find('films', $user, $user, $user . '/hello.txt') === null);
check('another user cannot read cloud rows', AppFiles::find('cloud', 'bob', 'bob', $user . '/hello.txt') === null);
$threw = false;
try {
    AppFiles::absolute('cloud', 'bob', 'bob', $user . '/hello.txt');
} catch (Throwable $e) {
    $threw = true;
}
check('foreign user cannot resolve an indexed file', $threw);
$threw = false;
try {
    AppFiles::recordWrite('cloud', $user, $user, 'bob/secret.txt');
} catch (Throwable $e) {
    $threw = true;
}
check('cross-user writes are rejected', $threw);
check('index reports objects for every application', count(FileIndex::status()['by_app'] ?? []) >= 5, json_encode(FileIndex::status()['by_app'] ?? []));

/* ------------------------------------------------------------------ */
echo "\n[10] Application listing code contains no directory scans\n";
$targets = [
    'cloud ai assistant' => ['apps/cloud/api/actions/ai_agent.php', "\$tool === 'list_directory'", "\$tool === 'stat_path'"],
    'spends backups' => ['apps/spends/controllers/Handlers.php', "if (isset(\$_POST['backup_action']))", "if (isset(\$_GET['download_backup']))"],
    'steps backups' => ['apps/steps/controllers/PostHandlers.php', "isset(\$_POST['create_backup'])", "isset(\$_POST['get_backups_list'])"],
    'recipes thumbnails' => ['apps/recipes/core/image.php', 'function recipes_product_thumb_public_path', 'function recipes_normalize_product_name'],
    'recipes media index' => ['apps/recipes/core/file_index.php', 'function recipes_index_file_exists', 'function recipes_index_record'],
    'films uploads' => ['apps/films/controllers/ApiController.php', 'move_uploaded_file', 'films_index_path'],
];
$forbidden = ['scandir(', 'glob(', 'DirectoryIterator', 'RecursiveDirectoryIterator', 'opendir('];
foreach ($targets as $label => [$file, $from, $to]) {
    $src = (string)file_get_contents($root . '/' . $file);
    $start = strpos($src, $from);
    $end = $start === false ? false : strpos($src, $to, $start + strlen($from));
    $block = ($start === false || $end === false) ? '' : substr($src, $start, $end - $start);
    check($label . ' block found', $block !== '', $file);
    $found = [];
    foreach ($forbidden as $needle) {
        if ($block !== '' && str_contains($block, $needle)) $found[] = $needle;
    }
    check($label . ' listing does not scan directories', $found === [], implode(', ', $found));
}

echo "\n[10b] Whole-file checks for index adapters\n";
$cleanFiles = [
    'apps/cloud/includes/cloud_file_index.php',
    'apps/cloud/webdav.php',
    'apps/films/core/file_index.php',
    'apps/recipes/core/file_index.php',
    'apps/spends/core/file_index.php',
    'apps/steps/core/file_index.php',
    'lib/Storage/AppFiles.php',
];
foreach ($cleanFiles as $file) {
    $src = (string)file_get_contents($root . '/' . $file);
    $found = [];
    foreach ($forbidden as $needle) {
        if (str_contains($src, $needle)) $found[] = $needle;
    }
    check(basename($file) . ' never scans a directory', $found === [], implode(', ', $found));
}

// The cloud sync manifest must list from the index (filesystem walk only as a
// fallback when the index is unavailable).
$sync = (string)file_get_contents($root . '/apps/cloud/includes/cloud_sync_engine.php');
$manifestStart = strpos($sync, 'function cloud_sync_manifest_build');
$manifestEnd = $manifestStart === false ? false : strpos($sync, 'function cloud_sync_manifest_page', $manifestStart);
$manifestBlock = ($manifestStart === false || $manifestEnd === false) ? '' : substr($sync, $manifestStart, $manifestEnd - $manifestStart);
check('cloud sync manifest block found', $manifestBlock !== '');
check('cloud sync manifest reads the index', str_contains($manifestBlock, 'AppFiles::treeByRoot'), 'AppFiles::treeByRoot missing');
check('cloud sync manifest iterates the index first', strpos($manifestBlock, 'AppFiles::treeByRoot') < strpos($manifestBlock, 'RecursiveDirectoryIterator'), 'index call must precede the fallback');

/* ------------------------------------------------------------------ */
echo "\n[11] Repository carries no runtime symlinks\n";
$links = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $item) {
    $path = (string)$item->getPathname();
    if (str_contains($path, '/vendor/') || str_contains($path, '/node_modules/') || str_contains($path, '/.venv')) continue;
    if (str_contains($path, '/.git/') || str_contains($path, '/tests/storage/')) continue;
    if (is_link($path)) $links[] = substr($path, strlen($root) + 1);
}
$runtimeLinks = array_values(array_filter($links, static fn($p) => !str_contains($p, '.venv') && !str_contains($p, '.bin/')));
check('no runtime symlinks in the code tree', $runtimeLinks === [], implode(', ', $runtimeLinks));

echo "\nResult: " . ($checks - $failures) . "/$checks checks passed\n";
if ($failures > 0) {
    echo "FAILED\n";
    exit(1);
}
echo "PASSED\n";
