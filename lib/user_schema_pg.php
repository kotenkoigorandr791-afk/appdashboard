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
        require_once __DIR__ . '/schema_once.php';
        $readyKey = 'spends_ready:' . $uid;
        if (!app_once_done($readyKey, __FILE__)) {
            $main = app_main_db();
            app_spends_shared_ensure($main);
            app_spends_subscriptions_ensure($main);
            app_spends_goals_ensure($main);
            app_spends_tags_ensure($main);
            app_spends_migrate_legacy($main, $uid, $email);
            app_once_mark($readyKey, __FILE__);
        }
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

/**
 * Recurring expenses (subscriptions). Created separately from the main schema
 * because app_spends_shared_ensure_impl() returns early once table_settings
 * exists, so already provisioned installs would never get the new table.
 */
function app_spends_subscriptions_ensure(AppDatabase $db): void {
    require_once __DIR__ . '/schema_once.php';
    app_once_wrap('spends_subscriptions', __FILE__, fn() => app_spends_subscriptions_ensure_impl($db));
}
function app_spends_tags_ensure(AppDatabase $db): void {
    require_once __DIR__ . '/schema_once.php';
    app_once_wrap('spends_tags', __FILE__, fn() => app_spends_tags_ensure_impl($db));
}
function app_spends_goals_ensure(AppDatabase $db): void {
    require_once __DIR__ . '/schema_once.php';
    app_once_wrap('spends_goals', __FILE__, fn() => app_spends_goals_ensure_impl($db));
}
function app_spends_tags_ensure_impl(AppDatabase $db): void {
    static $done = false;
    if ($done) return;
    $sc = APP_SPENDS_SHARED_SCHEMA;
    $role = APP_SPENDS_ROLE;
    $reg = $db->query("SELECT to_regclass('$sc.tags')")->fetch(PDO::FETCH_NUM);
    if ($reg && $reg[0] !== null) {
        $done = true;
        return;
    }
    $uid = "user_id TEXT NOT NULL DEFAULT current_setting('spends.user_id')";
    $db->exec('BEGIN');
    try {
        $db->exec('SELECT pg_advisory_xact_lock(7710004)');
        $db->exec("CREATE TABLE IF NOT EXISTS $sc.tags ($uid, id TEXT NOT NULL, name TEXT NOT NULL, color TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY (user_id, id))");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS spends_tags_name ON $sc.tags(user_id, lower(name))");
        $db->exec("CREATE TABLE IF NOT EXISTS $sc.expense_tags ($uid, date TEXT NOT NULL, store TEXT NOT NULL, tag_id TEXT NOT NULL, PRIMARY KEY (user_id, date, store, tag_id))");
        foreach (['tags', 'expense_tags'] as $tbl) {
            $db->exec("ALTER TABLE $sc.$tbl ENABLE ROW LEVEL SECURITY");
            $db->exec("ALTER TABLE $sc.$tbl FORCE ROW LEVEL SECURITY");
            $db->exec("DROP POLICY IF EXISTS spends_owner ON $sc.$tbl");
            $db->exec("CREATE POLICY spends_owner ON $sc.$tbl USING (user_id = current_setting('spends.user_id', true)) WITH CHECK (user_id = current_setting('spends.user_id', true))");
            $db->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON $sc.$tbl TO $role");
        }
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
    $done = true;
}
function app_spends_goals_ensure_impl(AppDatabase $db): void {
    static $done = false;
    if ($done) return;
    $sc = APP_SPENDS_SHARED_SCHEMA;
    $role = APP_SPENDS_ROLE;
    $reg = $db->query("SELECT to_regclass('$sc.goals')")->fetch(PDO::FETCH_NUM);
    if ($reg && $reg[0] !== null) {
        $done = true;
        return;
    }
    $uid = "user_id TEXT NOT NULL DEFAULT current_setting('spends.user_id')";
    $db->exec('BEGIN');
    try {
        $db->exec('SELECT pg_advisory_xact_lock(7710003)');
        $again = $db->query("SELECT to_regclass('$sc.goals')")->fetch(PDO::FETCH_NUM);
        if (!$again || $again[0] === null) {
            $db->exec("CREATE TABLE IF NOT EXISTS $sc.goals ($uid, id TEXT NOT NULL, name TEXT NOT NULL, description TEXT, target_amount DOUBLE PRECISION NOT NULL DEFAULT 0, current_amount DOUBLE PRECISION NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'USD', target_date TEXT, goal_type TEXT NOT NULL DEFAULT 'savings', is_archived BIGINT NOT NULL DEFAULT 0, icon TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY (user_id, id))");
            $db->exec("CREATE INDEX IF NOT EXISTS spends_goals_user ON $sc.goals(user_id)");
            $db->exec("ALTER TABLE $sc.goals ENABLE ROW LEVEL SECURITY");
            $db->exec("ALTER TABLE $sc.goals FORCE ROW LEVEL SECURITY");
            $db->exec("DROP POLICY IF EXISTS spends_owner ON $sc.goals");
            $db->exec("CREATE POLICY spends_owner ON $sc.goals USING (user_id = current_setting('spends.user_id', true)) WITH CHECK (user_id = current_setting('spends.user_id', true))");
            $db->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON $sc.goals TO $role");
        }
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
    $done = true;
}
function app_spends_subscriptions_ensure_impl(AppDatabase $db): void {
    static $done = false;
    if ($done) return;
    $sc = APP_SPENDS_SHARED_SCHEMA;
    $role = APP_SPENDS_ROLE;
    $reg = $db->query("SELECT to_regclass('$sc.subscriptions')")->fetch(PDO::FETCH_NUM);
    if ($reg && $reg[0] !== null) {
        $done = true;
        return;
    }
    $uid = "user_id TEXT NOT NULL DEFAULT current_setting('spends.user_id')";
    $db->exec('BEGIN');
    try {
        $db->exec('SELECT pg_advisory_xact_lock(7710002)');
        $again = $db->query("SELECT to_regclass('$sc.subscriptions')")->fetch(PDO::FETCH_NUM);
        if (!$again || $again[0] === null) {
            $db->exec("CREATE TABLE IF NOT EXISTS $sc.subscriptions ($uid, id TEXT NOT NULL, store TEXT NOT NULL, amount DOUBLE PRECISION NOT NULL DEFAULT 0, frequency TEXT NOT NULL DEFAULT 'monthly', day_of_month BIGINT NOT NULL DEFAULT 0, interval_days BIGINT NOT NULL DEFAULT 0, start_date TEXT NOT NULL, end_date TEXT, last_run TEXT, created_at TEXT NOT NULL, PRIMARY KEY (user_id, id))");
            $db->exec("CREATE INDEX IF NOT EXISTS spends_subscriptions_user ON $sc.subscriptions(user_id)");
            $db->exec("ALTER TABLE $sc.subscriptions ENABLE ROW LEVEL SECURITY");
            $db->exec("ALTER TABLE $sc.subscriptions FORCE ROW LEVEL SECURITY");
            $db->exec("DROP POLICY IF EXISTS spends_owner ON $sc.subscriptions");
            $db->exec("CREATE POLICY spends_owner ON $sc.subscriptions USING (user_id = current_setting('spends.user_id', true)) WITH CHECK (user_id = current_setting('spends.user_id', true))");
            $db->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON $sc.subscriptions TO $role");
        }
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
    $done = true;
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
