<?php
require_once __DIR__ . '/../../../lib/user_schema_pg.php';
require_once __DIR__ . '/../models/StepsModel.php';
function steps_user(?string $set = null): string {
    static $u = '';
    if ($set !== null) $u = strtolower(trim($set));
    return $u;
}
function steps_bind_user($stmt): void {
    $stmt->bindValue(':u', steps_user(), PDO::PARAM_STR);
}
function steps_store_ensure(AppDatabase $db): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $r = $db->query("SELECT to_regclass('public.steps_users')")->fetch(PDO::FETCH_NUM);
    if ($r && $r[0]) return;
    $db->exec('SELECT pg_advisory_lock(710101)');
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS steps_days (user_email TEXT NOT NULL, date TEXT NOT NULL, steps BIGINT NOT NULL DEFAULT 0, PRIMARY KEY (user_email, date))");
        $db->exec("CREATE TABLE IF NOT EXISTS steps_settings (user_email TEXT NOT NULL, key TEXT NOT NULL, value TEXT NOT NULL DEFAULT '', PRIMARY KEY (user_email, key))");
        $db->exec("CREATE TABLE IF NOT EXISTS steps_jobs (user_email TEXT NOT NULL, kind TEXT NOT NULL, job_id TEXT NOT NULL DEFAULT '', state_json TEXT NOT NULL DEFAULT '{}', updated_at BIGINT NOT NULL DEFAULT 0, PRIMARY KEY (user_email, kind))");
        $db->exec("CREATE TABLE IF NOT EXISTS steps_users (user_email TEXT PRIMARY KEY, migrated_at BIGINT NOT NULL DEFAULT 0)");
    } finally {
        $db->exec('SELECT pg_advisory_unlock(710101)');
    }
}
function steps_job_save(AppDatabase $db, string $kind, string $jobId, array $state): void {
    if (!isset($state['job_id']) || (string)$state['job_id'] === '') $state['job_id'] = $jobId;
    $stmt = $db->prepare('INSERT INTO steps_jobs (user_email, kind, job_id, state_json, updated_at) VALUES (:u, :k, :jid, :js, :ts) ON CONFLICT (user_email, kind) DO UPDATE SET job_id = excluded.job_id, state_json = excluded.state_json, updated_at = excluded.updated_at');
    steps_bind_user($stmt);
    $stmt->bindValue(':k', $kind, PDO::PARAM_STR);
    $stmt->bindValue(':jid', $jobId, PDO::PARAM_STR);
    $stmt->bindValue(':js', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
    $stmt->bindValue(':ts', time(), PDO::PARAM_INT);
    $stmt->execute();
}
function steps_job_load(AppDatabase $db, string $kind): array {
    $stmt = $db->prepare('SELECT job_id, state_json, updated_at FROM steps_jobs WHERE user_email = :u AND kind = :k');
    steps_bind_user($stmt);
    $stmt->bindValue(':k', $kind, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['job_id' => '', 'state' => [], 'updated_at' => 0];
    $st = json_decode((string)$row['state_json'], true);
    return ['job_id' => (string)$row['job_id'], 'state' => is_array($st) ? $st : [], 'updated_at' => (int)$row['updated_at']];
}
function steps_legacy_table_exists(AppDatabase $db, string $schemaSql, string $table): bool {
    $stmt = $db->prepare('SELECT to_regclass(:n)');
    $stmt->bindValue(':n', $schemaSql . '.' . $table, PDO::PARAM_STR);
    $r = $stmt->execute()->fetch(PDO::FETCH_NUM);
    return $r && $r[0];
}
function steps_migrate_legacy_schema(AppDatabase $db, string $email): void {
    $schemaSql = app_user_schema_sql(app_user_schema_id('steps', $email));
    if (steps_legacy_table_exists($db, $schemaSql, 'steps')) {
        $stmt = $db->prepare("INSERT INTO steps_days (user_email, date, steps) SELECT :u, date, steps FROM $schemaSql.steps WHERE date ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}\$' ON CONFLICT DO NOTHING");
        steps_bind_user($stmt);
        $stmt->execute();
        $res = $db->query("SELECT date, steps FROM $schemaSql.steps WHERE date !~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}\$'");
        $odd = [];
        while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
            $iso = normalizeStoredDate((string)$r['date']);
            if ($iso) $odd[$iso] = max($odd[$iso] ?? 0, (int)$r['steps']);
        }
        foreach ($odd as $d => $s) upsertStepIgnore($db, $d, $s);
    }
    if (steps_legacy_table_exists($db, $schemaSql, 'settings')) {
        $stmt = $db->prepare("INSERT INTO steps_settings (user_email, key, value) SELECT :u, key, value FROM $schemaSql.settings ON CONFLICT DO NOTHING");
        steps_bind_user($stmt);
        $stmt->execute();
    }
    foreach (['video' => 'video_job_state', 'fit' => 'fit_takeout_job_state', 'zeep' => 'zeep_job_state'] as $kind => $table) {
        if (!steps_legacy_table_exists($db, $schemaSql, $table)) continue;
        $stmt = $db->prepare("INSERT INTO steps_jobs (user_email, kind, job_id, state_json, updated_at) SELECT :u, :k, job_id, state_json, updated_at FROM $schemaSql.$table WHERE id = 1 ON CONFLICT DO NOTHING");
        steps_bind_user($stmt);
        $stmt->bindValue(':k', $kind, PDO::PARAM_STR);
        $stmt->execute();
    }
}
function upsertStepIgnore(AppDatabase $db, string $date, int $steps): void {
    $stmt = $db->prepare('INSERT INTO steps_days (user_email, date, steps) VALUES (:u, :d, :s) ON CONFLICT (user_email, date) DO NOTHING');
    steps_bind_user($stmt);
    $stmt->bindValue(':d', $date, PDO::PARAM_STR);
    $stmt->bindValue(':s', $steps, PDO::PARAM_INT);
    $stmt->execute();
}
function steps_migrate_sqlite_file(AppDatabase $db, string $dir): void {
    $path = "$dir/user_data.sqlite3";
    if (!is_file($path) || !class_exists('SQLite3')) return;
    try {
        $sqlite = new SQLite3($path);
        $res = $sqlite->query('SELECT date, steps FROM steps');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $iso = normalizeStoredDate((string)$row['date']);
            if ($iso) upsertStepIgnore($db, $iso, (int)$row['steps']);
        }
        $res = $sqlite->query('SELECT key, value FROM settings');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $stmt = $db->prepare('INSERT INTO steps_settings (user_email, key, value) VALUES (:u, :k, :v) ON CONFLICT DO NOTHING');
            steps_bind_user($stmt);
            $stmt->bindValue(':k', (string)$row['key'], PDO::PARAM_STR);
            $stmt->bindValue(':v', (string)$row['value'], PDO::PARAM_STR);
            $stmt->execute();
        }
        $sqlite->close();
    } catch (Throwable $e) {
        error_log('steps sqlite migrate: ' . $e->getMessage());
    }
}
function steps_merge_dir(string $from, string $to): void {
    if (!is_dir($to)) {
        @rename($from, $to);
        return;
    }
    foreach (array_diff(scandir($from) ?: [], ['.', '..']) as $item) {
        $src = "$from/$item";
        $dst = "$to/$item";
        if (!file_exists($dst)) @rename($src, $dst);
        elseif (is_dir($src) && is_dir($dst) && !is_link($src)) steps_merge_dir($src, $dst);
    }
    @rmdir($from);
}
function steps_user_bootstrap(AppDatabase $db, string $email, string $userDir): void {
    $stmt = $db->prepare('SELECT 1 FROM steps_users WHERE user_email = :u');
    steps_bind_user($stmt);
    if ($stmt->execute()->fetch(PDO::FETCH_NUM)) return;
    $legacy = dirname($userDir) . '/' . legacyEmailFolderName($email);
    if (is_dir($legacy) && !is_link($legacy) && $legacy !== $userDir) steps_merge_dir($legacy, $userDir);
    try {
        steps_migrate_legacy_schema($db, $email);
        steps_migrate_sqlite_file($db, $userDir);
    } catch (Throwable $e) {
        error_log('steps legacy migrate: ' . $e->getMessage());
        return;
    }
    $ins = $db->prepare('INSERT INTO steps_users (user_email, migrated_at) VALUES (:u, :t) ON CONFLICT DO NOTHING');
    steps_bind_user($ins);
    $ins->bindValue(':t', time(), PDO::PARAM_INT);
    $ins->execute();
}
