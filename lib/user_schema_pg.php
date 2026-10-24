<?php
declare(strict_types=1);
require_once __DIR__ . '/app_db.php';
function app_user_schema_id(string $appPrefix, string $email): string {
    $raw = strtolower($appPrefix . '_' . preg_replace('/[^a-zA-Z0-9._+-]+/', '_', $email));
    $raw = preg_replace('/_+/', '_', trim($raw, '_'));
    return substr($raw, 0, 55);
}
function app_user_schema_index_id(string $schema): string {
    return substr(preg_replace('/_+/', '_', preg_replace('/[^a-z0-9_]+/', '_', strtolower($schema))), 0, 40);
}
function app_user_schema_sql(string $schema): string {
    return '"' . str_replace('"', '""', $schema) . '"';
}
const APP_SPENDS_SHARED_SCHEMA = 'spends_shared';
const APP_SPENDS_ROLE = 'spends_app';
const APP_SPENDS_TABLES = ['settings' => 'key', 'table_settings' => 'key', 'stores' => 'id', 'expenses' => 'date,store', 'incomes' => 'date', 'ocr_entries' => 'signature'];
function app_spends_user_id(string $email): string {
    return strtolower(trim($email));
}
function app_user_db_connect(string $appPrefix, string $email): AppDatabase {
    if ($appPrefix === 'spends') {
        $uid = app_spends_user_id($email);
        $main = app_main_db();
        app_spends_shared_ensure($main);
        app_spends_migrate_legacy($main, $uid, $email);
        $lit = "'" . AppDatabase::escapeString($uid) . "'";
        return AppDatabase::connectUserSchema(APP_SPENDS_SHARED_SCHEMA, ["SELECT set_config('spends.user_id', $lit, false)", 'SET ROLE ' . APP_SPENDS_ROLE]);
    }
    $schema = app_user_schema_id($appPrefix, $email);
    $schemaSql = app_user_schema_sql($schema);
    app_user_schema_ensure(app_main_db(), $appPrefix, $schema);
    return AppDatabase::connectUserSchema($schemaSql);
}
function app_spends_shared_ensure(AppDatabase $db): void {
    require_once __DIR__ . '/schema_once.php';
    app_once_wrap('spends_shared', __FILE__, fn() => app_spends_shared_ensure_impl($db));
}
function app_spends_shared_ensure_impl(AppDatabase $db): void {
    static $done = false;
    if ($done) return;
    $reg = $db->query("SELECT to_regclass('" . APP_SPENDS_SHARED_SCHEMA . ".table_settings')")->fetch(PDO::FETCH_NUM);
    if ($reg && $reg[0] !== null) {
        $done = true;
        return;
    }
    $sc = APP_SPENDS_SHARED_SCHEMA;
    $role = APP_SPENDS_ROLE;
    $uid = "user_id TEXT NOT NULL DEFAULT current_setting('spends.user_id')";
    $db->exec('BEGIN');
    try {
        $db->exec("SELECT pg_advisory_xact_lock(7710001)");
        $again = $db->query("SELECT to_regclass('$sc.table_settings')")->fetch(PDO::FETCH_NUM);
        if (!$again || $again[0] === null) {
            if (!$db->query("SELECT 1 FROM pg_roles WHERE rolname = '$role'")->fetch(PDO::FETCH_NUM)) $db->exec("CREATE ROLE $role NOLOGIN");
            $db->exec("GRANT $role TO CURRENT_USER");
            $db->exec("CREATE SCHEMA IF NOT EXISTS $sc");
            $db->exec("CREATE TABLE IF NOT EXISTS $sc.settings ($uid, key TEXT NOT NULL, value TEXT, PRIMARY KEY (user_id, key))");
            $db->exec("CREATE TABLE IF NOT EXISTS $sc.stores ($uid, id TEXT NOT NULL, name TEXT NOT NULL, visible BIGINT NOT NULL DEFAULT 1, sort_order BIGINT NOT NULL DEFAULT 0, category TEXT NOT NULL DEFAULT 'other', PRIMARY KEY (user_id, id))");
            $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS spends_stores_user_name ON $sc.stores(user_id, name)");
            $db->exec("CREATE TABLE IF NOT EXISTS $sc.expenses ($uid, date TEXT NOT NULL, store TEXT NOT NULL, amount DOUBLE PRECISION NOT NULL DEFAULT 0, entry_source TEXT NOT NULL DEFAULT 'manual', ocr_raw_store TEXT, PRIMARY KEY (user_id, date, store))");
            $db->exec("CREATE INDEX IF NOT EXISTS spends_expenses_user_store ON $sc.expenses(user_id, store)");
            $db->exec("CREATE TABLE IF NOT EXISTS $sc.incomes ($uid, date TEXT NOT NULL, amount DOUBLE PRECISION NOT NULL DEFAULT 0, PRIMARY KEY (user_id, date))");
            $db->exec("CREATE TABLE IF NOT EXISTS $sc.ocr_entries ($uid, signature TEXT NOT NULL, date TEXT NOT NULL, store TEXT NOT NULL, amount DOUBLE PRECISION NOT NULL, ocr_raw_store TEXT, image_name TEXT, created_at TEXT NOT NULL, PRIMARY KEY (user_id, signature))");
            $db->exec("CREATE TABLE IF NOT EXISTS $sc.table_settings ($uid, key TEXT NOT NULL, value TEXT, PRIMARY KEY (user_id, key))");
            foreach (array_keys(APP_SPENDS_TABLES) as $t) {
                $db->exec("ALTER TABLE $sc.$t ENABLE ROW LEVEL SECURITY");
                $db->exec("ALTER TABLE $sc.$t FORCE ROW LEVEL SECURITY");
                $db->exec("DROP POLICY IF EXISTS spends_owner ON $sc.$t");
                $db->exec("CREATE POLICY spends_owner ON $sc.$t USING (user_id = current_setting('spends.user_id', true)) WITH CHECK (user_id = current_setting('spends.user_id', true))");
                $db->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON $sc.$t TO $role");
            }
            $db->exec("GRANT USAGE ON SCHEMA $sc TO $role");
        }
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
    $done = true;
}
function app_spends_migrate_legacy(AppDatabase $db, string $uid, string $email): void {
    static $seen = [];
    if (isset($seen[$uid])) return;
    $sc = APP_SPENDS_SHARED_SCHEMA;
    $marker = $db->prepare("SELECT 1 FROM $sc.settings WHERE user_id = :u AND key = 'shared_v1'");
    $marker->bindValue(':u', $uid, PDO::PARAM_STR);
    if ($marker->execute()->fetch(PDO::FETCH_NUM)) {
        $seen[$uid] = true;
        return;
    }
    $legacy = app_user_schema_sql(app_user_schema_id('spends', $email));
    $db->exec('BEGIN');
    try {
        $lock = $db->prepare('SELECT pg_advisory_xact_lock(hashtext(:k))');
        $lock->bindValue(':k', 'spends_migrate:' . $uid, PDO::PARAM_STR);
        $lock->execute();
        if (!$marker->execute()->fetch(PDO::FETCH_NUM)) {
            $copy = [
                'settings' => "key, value|key, value|",
                'table_settings' => "key, value|key, value|",
                'stores' => "id, name, visible, sort_order, category|id, name, visible, sort_order, COALESCE(category,'other')|",
                'expenses' => "date, store, amount, entry_source, ocr_raw_store|date, store, amount, COALESCE(entry_source,'manual'), ocr_raw_store|WHERE ABS(amount) > 0.0001",
                'incomes' => "date, amount|date, amount|WHERE ABS(amount) > 0.0001",
                'ocr_entries' => "signature, date, store, amount, ocr_raw_store, image_name, created_at|signature, date, store, amount, ocr_raw_store, image_name, created_at|",
            ];
            foreach ($copy as $t => $spec) {
                [$dst, $src, $where] = explode('|', $spec);
                $reg = $db->prepare('SELECT to_regclass(:r)');
                $reg->bindValue(':r', $legacy . '.' . $t, PDO::PARAM_STR);
                $r = $reg->execute()->fetch(PDO::FETCH_NUM);
                if (!$r || $r[0] === null) continue;
                $ins = $db->prepare("INSERT INTO $sc.$t (user_id, $dst) SELECT :u, $src FROM $legacy.$t $where ON CONFLICT DO NOTHING");
                $ins->bindValue(':u', $uid, PDO::PARAM_STR);
                $ins->execute();
            }
            $mk = $db->prepare("INSERT INTO $sc.settings (user_id, key, value) VALUES (:u, 'shared_v1', '1') ON CONFLICT DO NOTHING");
            $mk->bindValue(':u', $uid, PDO::PARAM_STR);
            $mk->execute();
        }
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
    $seen[$uid] = true;
}
function app_user_schema_ensure(AppDatabase $db, string $appPrefix, string $schema): void {
    static $schemasDone = [];
    if (isset($schemasDone[$schema])) return;
    $s = app_user_schema_sql($schema);
    $db->exec("CREATE SCHEMA IF NOT EXISTS $s");
    if ($appPrefix === 'tft') {
        $db->exec("CREATE TABLE IF NOT EXISTS $s.config (key TEXT PRIMARY KEY, value TEXT)");
        $db->exec("CREATE TABLE IF NOT EXISTS $s.canvas (key TEXT PRIMARY KEY, value TEXT)");
    }
    $schemasDone[$schema] = true;
}
