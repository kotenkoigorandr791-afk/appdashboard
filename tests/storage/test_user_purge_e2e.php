<?php
/**
 * End-to-end account lifecycle test.
 *
 * Creates a throw-away user, writes data for every local AppDashboard app,
 * verifies the central file index picks it up, deletes the account through
 * UserDataPurger and then verifies that no rows, files, per-user tables or
 * schemas remain.
 *
 * Run inside the app container (PostgreSQL + volumes):
 *   docker compose exec -T appdashboard php /var/www/AppDashboard/tests/storage/test_user_purge_e2e.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
chdir($root);
require_once $root . '/db.php';
require_once $root . '/shell/storage_helper.php';
require_once $root . '/lib/Storage/bootstrap.php';
require_once $root . '/lib/Storage/FileIndexWorker.php';
require_once $root . '/lib/Storage/AppFiles.php';
require_once $root . '/lib/UserDataPurger.php';

$failures = 0;
$checks = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($ok || $detail === '' ? '' : ' — ' . $detail) . "\n";
    if (!$ok) $failures++;
}

$email = 'e2e-user-' . bin2hex(random_bytes(5)) . '@example.test';
$username = str_replace(['@', '.'], '_', $email);
$pdo = StorageDb::pdo();
$app = 'cloud';
$cloudBase = appdata_app_base_dir($app);
$cloudRoot = $cloudBase . '/' . $username;
$avatarRel = 'uploads/avatars/e2e_' . bin2hex(random_bytes(4)) . '.webp';
$avatarAbs = appdata_uploads_base_dir() . '/' . substr($avatarRel, strlen('uploads/avatars/'));
$schemaName = 'e2e_' . substr(preg_replace('/[^a-z0-9]+/', '_', strtolower($email)), 0, 40);
$tableName = 'e2e_tbl_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($email));

echo "Test user: $email\n";
echo "Cloud root: $cloudRoot\n";

// Cleanup from a previous failed run (best effort).
try { UserDataPurger::purge($email, ['delete_files' => true]); } catch (Throwable $e) {}

try {
    /* ---------------------------------------------------------------- */
    echo "\n[1] Create the account\n";
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO users (email,name,password,photo,role,verified,language,theme,color,trusted_devices,icon_sort_order,icon_style,wallpaper,must_change_password,created_at)
        VALUES (:e,:n,'x',:p,1,1,'en','light','#007bff','[]','alpha','animated','default',0,:c)
        ON CONFLICT (email) DO UPDATE SET name = EXCLUDED.name");
    $stmt->bindValue(':e', $email);
    $stmt->bindValue(':n', 'E2E User');
    $stmt->bindValue(':p', $avatarRel);
    $stmt->bindValue(':c', $now);
    $stmt->execute();
    $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e');
    $check->bindValue(':e', $email);
    $check->execute();
    check('user row created', (int)$check->fetchColumn() === 1);

    /* ---------------------------------------------------------------- */
    echo "\n[2] Add data for every local application\n";
    @mkdir($cloudRoot . '/docs/reports', 0775, true);
    file_put_contents($cloudRoot . '/hello.txt', "hello e2e\n");
    file_put_contents($cloudRoot . '/docs/report.txt', "report e2e\n");
    file_put_contents($cloudRoot . '/docs/reports/deep.bin', str_repeat('x', 256));
    @mkdir(dirname($avatarAbs), 0775, true);
    file_put_contents($avatarAbs, 'avatar-bytes');

    // Files for the other local apps (each app has its own volume/dir).
    foreach (['films', 'recipes', 'spends', 'steps', 'smart_home'] as $other) {
        $dir = appdata_app_base_dir($other) . '/' . $username;
        @mkdir($dir . '/data', 0775, true);
        file_put_contents($dir . '/data/e2e.txt', $other . " data\n");
    }

    // Steps rows.
    foreach ([
        "INSERT INTO steps_days (user_email,date,steps) VALUES (:e,'2026-01-01',1234) ON CONFLICT DO NOTHING",
        "INSERT INTO steps_settings (user_email,key,value) VALUES (:e,'e2e','1') ON CONFLICT DO NOTHING",
        "INSERT INTO steps_jobs (user_email,kind,job_id,state_json,updated_at) VALUES (:e,'e2e','job1','{}',0) ON CONFLICT DO NOTHING",
        "INSERT INTO steps_users (user_email,migrated_at) VALUES (:e,0) ON CONFLICT DO NOTHING",
    ] as $sql) {
        try {
            $s = $pdo->prepare($sql);
            $s->bindValue(':e', $email);
            $s->execute();
        } catch (Throwable $e) {}
    }

    // Smart Home settings.
    try {
        $s = $pdo->prepare("INSERT INTO smarthome_settings (user_email,server_domain,ap_domain) VALUES (:e,'e2e','e2e') ON CONFLICT (user_email) DO NOTHING");
        $s->bindValue(':e', $email);
        $s->execute();
    } catch (Throwable $e) {}

    // Notifications / metrics use user_email.
    try {
        $s = $pdo->prepare("INSERT INTO app_notifications (user_email,app_id,severity,title,message,event_type,context_json,is_read,created_at) VALUES (:e,'e2e','info','E2E','test','e2e','{}',0,0)");
        $s->bindValue(':e', $email);
        $s->execute();
    } catch (Throwable $e) {}

    // Cloud application tables.
    foreach ([
        "CREATE TABLE IF NOT EXISTS cloud_user_settings (email TEXT PRIMARY KEY, quota_mb BIGINT NOT NULL DEFAULT 0, session_limit_mb BIGINT NOT NULL DEFAULT 0, trash_retention_days BIGINT NOT NULL DEFAULT 7)",
        "CREATE TABLE IF NOT EXISTS cloud_storage_usage_stats (email TEXT PRIMARY KEY, stats_json TEXT NOT NULL DEFAULT '[]', root_total_bytes BIGINT NOT NULL DEFAULT 0, updated_at BIGINT NOT NULL DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS cloud_storage_requests (id BIGSERIAL PRIMARY KEY, email TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending', note TEXT NOT NULL DEFAULT '', current_quota_mb BIGINT NOT NULL DEFAULT 0, used_bytes BIGINT NOT NULL DEFAULT 0, created_at BIGINT NOT NULL DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS cloud_folder_state (email TEXT PRIMARY KEY, folder_json TEXT NOT NULL DEFAULT '[]', tree_json TEXT NOT NULL DEFAULT '', tree_hash TEXT NOT NULL DEFAULT '', tree_valid BIGINT NOT NULL DEFAULT 0, updated_at BIGINT NOT NULL DEFAULT 0)",
    ] as $ddl) {
        try { $pdo->exec($ddl); } catch (Throwable $e) {}
    }
    try {
        $s = $pdo->prepare('INSERT INTO cloud_user_settings (email,quota_mb) VALUES (:e,100) ON CONFLICT (email) DO NOTHING');
        $s->bindValue(':e', $email);
        $s->execute();
        $s = $pdo->prepare('INSERT INTO cloud_storage_usage_stats (email,stats_json) VALUES (:e,\'[]\') ON CONFLICT (email) DO NOTHING');
        $s->bindValue(':e', $email);
        $s->execute();
        $s = $pdo->prepare('INSERT INTO cloud_storage_requests (email) VALUES (:e)');
        $s->bindValue(':e', $email);
        $s->execute();
    } catch (Throwable $e) {}

    // Pending user + verification token.
    try { $pdo->prepare('DELETE FROM pending_users WHERE email = :e')->execute([':e' => $email]); } catch (Throwable $e) {}
    try {
        $s = $pdo->prepare('INSERT INTO pending_users (email,name,password,photo,role,language,theme,color,created_at,reg_method) VALUES (:e,\'x\',\'x\',\'x\',1,\'en\',\'light\',\'#fff\',:c,\'Email\')');
        $s->bindValue(':e', $email);
        $s->bindValue(':c', $now);
        $s->execute();
    } catch (Throwable $e) {}
    try {
        $s = $pdo->prepare('INSERT INTO verification_tokens (email,token,expires_at) VALUES (:e,:t,:x)');
        $s->bindValue(':e', $email);
        $s->bindValue(':t', bin2hex(random_bytes(8)));
        $s->bindValue(':x', time() + 3600);
        $s->execute();
    } catch (Throwable $e) {}

    // Per-user table and schema that must be dropped.
    try {
        $pdo->exec('DROP TABLE IF EXISTS "' . $tableName . '"');
        $pdo->exec('CREATE TABLE "' . $tableName . '" (id BIGSERIAL PRIMARY KEY, payload TEXT)');
        $pdo->exec('INSERT INTO "' . $tableName . '" (payload) VALUES (\'x\')');
        $pdo->exec('DROP SCHEMA IF EXISTS "' . $schemaName . '" CASCADE');
        $pdo->exec('CREATE SCHEMA "' . $schemaName . '"');
        $pdo->exec('CREATE TABLE "' . $schemaName . '".stuff (id BIGSERIAL PRIMARY KEY)');
    } catch (Throwable $e) {}
    check('per-user table and schema created', self_table_exists($pdo, $tableName) && self_schema_exists($pdo, $schemaName));

    /* ---------------------------------------------------------------- */
    echo "\n[3] Central index picks the data up\n";
    $job = FileIndex::enqueueInitialIndex($app, $username, $username);
    for ($i = 0; $i < 30; $i++) {
        $r = FileIndexWorker::processJob($job, 50);
        if (($r['action'] ?? '') === 'completed') break;
    }
    $listing = FileIndex::listDirectory($app, $username, $username, '');
    $names = array_column($listing['items'], 'name');
    check('root listing comes from PostgreSQL', in_array('hello.txt', $names, true) && in_array('docs', $names, true), json_encode($names));
    $deep = FileIndex::findByPath($app, $username, $username . '/docs/reports/deep.bin');
    check('nested file indexed', $deep !== null && (int)$deep['size_bytes'] === 256);
    $search = FileIndex::search($app, $username, $username, 'report');
    $searchNames = array_column($search, 'name');
    check('search finds indexed file', in_array('report.txt', $searchNames, true), json_encode($searchNames));
    $resolved = FileIndex::resolvePhysicalPath((int)$deep['id'], $app, $username, $username);
    check('file id resolves to the physical path', is_file($resolved), $resolved);

    // No symlinks may be created inside user data directories.
    $links = self_find_symlinks($cloudRoot);
    check('no symlinks in user data tree', $links === [], implode(', ', $links));

    /* ---------------------------------------------------------------- */
    echo "\n[3b] Every application reads and writes through the index\n";
    require_once APP_ROOT . '/lib/Storage/AppFiles.php';
    foreach (['films', 'recipes', 'spends', 'steps', 'smart_home'] as $other) {
        $otherRoot = appdata_app_base_dir($other) . '/' . $username;
        file_put_contents($otherRoot . '/data/e2e.txt', $other . " fresh\n");
        $jobId = FileIndex::enqueueInitialIndex($other, $username, $username);
        for ($i = 0; $i < 30; $i++) {
            $r = FileIndexWorker::processJob($jobId, 50);
            if (($r['action'] ?? '') === 'completed') break;
        }
        $listing = AppFiles::listByRoot($other, $username, $username, 'data');
        $otherNames = array_column($listing['entries'], 'name');
        check($other . ' listing comes from the index', in_array('e2e.txt', $otherNames, true), json_encode($otherNames));
        $row = AppFiles::find($other, $username, $username, $username . '/data/e2e.txt');
        check($other . ' file is indexed with its size', $row !== null && (int)$row['size_bytes'] === strlen($other . " fresh\n"), json_encode($row ?: []));
        $otherAbs = AppFiles::absolute($other, $username, $username, $username . '/data/e2e.txt');
        check($other . ' file id resolves to the physical path', is_file($otherAbs), $otherAbs);
        check($other . ' data tree has no symlinks', self_find_symlinks($otherRoot) === []);
    }

    // The AI assistant lists the same user files through the index.
    $aiListing = AppFiles::treeByRoot('cloud', $username, $username, 'docs');
    $aiPaths = array_column($aiListing, 'path');
    check('AI assistant recursive listing uses the index', in_array('report.txt', $aiPaths, true) && in_array('reports/deep.bin', $aiPaths, true), json_encode($aiPaths));
    check('AI assistant sees files written after indexing', AppFiles::existsAt('cloud', $username, $username, $username . '/hello.txt'));
    $stat = FileIndex::status();
    $indexedApps = array_map(static fn($row) => (string)$row['app_key'], $stat['by_app']);
    check('index reports objects for every local application', count(array_intersect($indexedApps, ['cloud', 'films', 'recipes', 'spends', 'steps', 'smart_home'])) === 6, json_encode($indexedApps));

    /* ---------------------------------------------------------------- */
    echo "\n[4] Delete the account (full purge)\n";
    $report = UserDataPurger::purge($email, ['delete_files' => true]);
    check('purge reported no errors', empty($report['errors']), json_encode($report['errors']));
    check('purge removed index rows', (int)($report['index']['files'] ?? 0) > 0, json_encode($report['index']));
    check('purge removed files', (int)($report['files']['count'] ?? 0) > 0, json_encode($report['files']));

    /* ---------------------------------------------------------------- */
    echo "\n[5] No leftovers\n";
    $variants = UserDataPurger::userVariants($email, 'E2E User');
    $ph = [];
    $bind = [];
    foreach ($variants as $i => $v) { $ph[] = ':v' . $i; $bind[':v' . $i] = $v; }
    $in = implode(',', $ph);
    $leftovers = [];
    foreach (['users', 'pending_users', 'verification_tokens'] as $table) {
        $s = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE lower(email) IN (' . $in . ')');
        foreach ($bind as $k => $v) $s->bindValue($k, $v);
        $s->execute();
        $leftovers[$table] = (int)$s->fetchColumn();
    }
    foreach (['active_sessions' => 'email'] as $table => $col) {
        try {
            $s = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE lower(' . $col . ') IN (' . $in . ')');
            foreach ($bind as $k => $v) $s->bindValue($k, $v);
            $s->execute();
            $leftovers[$table] = (int)$s->fetchColumn();
        } catch (Throwable $e) {}
    }
    foreach (['steps_days', 'steps_settings', 'steps_jobs', 'steps_users', 'smarthome_settings', 'app_notifications', 'cloud_user_settings', 'cloud_storage_usage_stats', 'cloud_storage_requests'] as $table) {
        $col = str_starts_with($table, 'steps_') || in_array($table, ['smarthome_settings', 'app_notifications'], true) ? 'user_email' : 'email';
        try {
            $s = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE lower(' . $col . ') IN (' . $in . ')');
            foreach ($bind as $k => $v) $s->bindValue($k, $v);
            $s->execute();
            $leftovers[$table] = (int)$s->fetchColumn();
        } catch (Throwable $e) {}
    }
    $s = $pdo->prepare('SELECT COUNT(*) FROM storage_files WHERE user_id IN (' . $in . ')');
    foreach ($bind as $k => $v) $s->bindValue($k, $v);
    $s->execute();
    $leftovers['storage_files'] = (int)$s->fetchColumn();
    $s = $pdo->prepare('SELECT COUNT(*) FROM storage_file_outbox WHERE user_id IN (' . $in . ')');
    foreach ($bind as $k => $v) $s->bindValue($k, $v);
    $s->execute();
    $leftovers['storage_file_outbox'] = (int)$s->fetchColumn();
    $s = $pdo->prepare('SELECT COUNT(*) FROM storage_index_jobs WHERE user_id IN (' . $in . ')');
    foreach ($bind as $k => $v) $s->bindValue($k, $v);
    $s->execute();
    $leftovers['storage_index_jobs'] = (int)$s->fetchColumn();

    $bad = array_filter($leftovers, static fn($n) => $n !== 0);
    check('all database rows are gone', $bad === [], json_encode($leftovers));
    check('per-user table dropped', !self_table_exists($pdo, $tableName));
    check('per-user schema dropped', !self_schema_exists($pdo, $schemaName));
    check('cloud data directory removed', !file_exists($cloudRoot), $cloudRoot);
    check('avatar removed', !file_exists($avatarAbs), $avatarAbs);
    foreach (['films', 'recipes', 'spends', 'steps', 'smart_home'] as $other) {
        check($other . ' user directory removed', !file_exists(appdata_app_base_dir($other) . '/' . $username));
    }

    echo "\n[6] Purge is idempotent\n";
    $second = UserDataPurger::purge($email, ['delete_files' => true]);
    check('second purge succeeds', empty($second['errors']), json_encode($second['errors']));
} catch (Throwable $e) {
    echo "\nFATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failures++;
    try { UserDataPurger::purge($email, ['delete_files' => true]); } catch (Throwable $ignored) {}
}

echo "\nResult: " . ($checks - $failures) . "/$checks checks passed\n";
if ($failures > 0) {
    echo "FAILED\n";
    exit(1);
}
echo "PASSED\n";

function self_table_exists(PDO $pdo, string $table): bool
{
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name=:t");
        $s->bindValue(':t', $table);
        $s->execute();
        return (int)$s->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function self_schema_exists(PDO $pdo, string $schema): bool
{
    try {
        $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=:s');
        $s->bindValue(':s', $schema);
        $s->execute();
        return (int)$s->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** @return list<string> */
function self_find_symlinks(string $dir): array
{
    $out = [];
    if (!is_dir($dir)) return $out;
    foreach (@scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . '/' . $entry;
        if (is_link($path)) { $out[] = $path; continue; }
        if (is_dir($path)) $out = array_merge($out, self_find_symlinks($path));
    }
    return $out;
}
