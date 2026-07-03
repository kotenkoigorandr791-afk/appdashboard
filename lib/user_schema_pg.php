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
function app_user_db_connect(string $appPrefix, string $email): AppDatabase {
    $schema = app_user_schema_id($appPrefix, $email);
    $schemaSql = app_user_schema_sql($schema);
    app_user_schema_ensure(app_main_db(), $appPrefix, $schema);
    return AppDatabase::connectUserSchema($schemaSql);
}
function app_user_schema_ensure(AppDatabase $db, string $appPrefix, string $schema): void {
    static $schemasDone = [];
    $s = app_user_schema_sql($schema);
    if (!isset($schemasDone[$schema])) {
        $db->exec("CREATE SCHEMA IF NOT EXISTS $s");
        $schemasDone[$schema] = true;
    }
    $idx = app_user_schema_index_id($schema);
    if ($appPrefix === 'spends') {
        $db->exec("CREATE TABLE IF NOT EXISTS $s.settings (key TEXT PRIMARY KEY, value TEXT)");
        $db->exec("CREATE TABLE IF NOT EXISTS $s.stores (id TEXT PRIMARY KEY, name TEXT NOT NULL, visible BIGINT NOT NULL DEFAULT 1, sort_order BIGINT NOT NULL DEFAULT 0)");
        $db->exec("CREATE TABLE IF NOT EXISTS $s.expenses (date TEXT NOT NULL, store TEXT NOT NULL, amount DOUBLE PRECISION NOT NULL DEFAULT 0, entry_source TEXT NOT NULL DEFAULT 'manual', ocr_raw_store TEXT, PRIMARY KEY (date, store))");
        $db->exec("CREATE TABLE IF NOT EXISTS $s.incomes (date TEXT PRIMARY KEY, amount DOUBLE PRECISION NOT NULL DEFAULT 0)");
        $db->exec("CREATE TABLE IF NOT EXISTS $s.ocr_entries (signature TEXT PRIMARY KEY, date TEXT NOT NULL, store TEXT NOT NULL, amount DOUBLE PRECISION NOT NULL, ocr_raw_store TEXT, image_name TEXT, created_at TEXT NOT NULL)");
        $db->exec("CREATE TABLE IF NOT EXISTS $s.table_settings (key TEXT PRIMARY KEY, value TEXT)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_{$idx}_expenses_date ON $s.expenses(date)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_{$idx}_expenses_store ON $s.expenses(store)");
    } elseif ($appPrefix === 'steps') {
        $db->exec("CREATE TABLE IF NOT EXISTS $s.steps (date TEXT PRIMARY KEY, steps BIGINT NOT NULL DEFAULT 0)");
        $db->exec("CREATE TABLE IF NOT EXISTS $s.settings (key TEXT PRIMARY KEY, value TEXT NOT NULL DEFAULT '')");
        $db->exec("CREATE TABLE IF NOT EXISTS $s.video_jobs (job_id TEXT PRIMARY KEY, state_json TEXT NOT NULL DEFAULT '{}', updated_at BIGINT NOT NULL DEFAULT 0)");
        $db->exec("CREATE TABLE IF NOT EXISTS $s.fit_takeout_jobs (job_id TEXT PRIMARY KEY, state_json TEXT NOT NULL DEFAULT '{}', updated_at BIGINT NOT NULL DEFAULT 0)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_{$idx}_steps_date ON $s.steps(date)");
    } elseif ($appPrefix === 'tft') {
        $db->exec("CREATE TABLE IF NOT EXISTS $s.config (key TEXT PRIMARY KEY, value TEXT)");
        $db->exec("CREATE TABLE IF NOT EXISTS $s.canvas (key TEXT PRIMARY KEY, value TEXT)");
    }
}
