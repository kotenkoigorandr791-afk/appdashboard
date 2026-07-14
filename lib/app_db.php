<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/pgsql_compat.php';
function app_main_db(): AppDatabase {
    return app_open_main_db();
}
function app_notifications_ensure(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS app_notifications (
        id BIGSERIAL PRIMARY KEY,
        user_email TEXT NOT NULL DEFAULT '',
        app_id TEXT NOT NULL DEFAULT '',
        severity TEXT NOT NULL DEFAULT 'info',
        title TEXT NOT NULL,
        message TEXT NOT NULL DEFAULT '',
        event_type TEXT NOT NULL DEFAULT '',
        context_json TEXT NOT NULL DEFAULT '{}',
        is_read INTEGER NOT NULL DEFAULT 0,
        created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
        read_at BIGINT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS app_metrics (
        id BIGSERIAL PRIMARY KEY,
        app_id TEXT NOT NULL DEFAULT '',
        metric TEXT NOT NULL,
        value DOUBLE PRECISION NOT NULL DEFAULT 0,
        unit TEXT NOT NULL DEFAULT '',
        user_email TEXT NOT NULL DEFAULT '',
        context_json TEXT NOT NULL DEFAULT '{}',
        created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint)
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_app_notifications_user_read ON app_notifications(user_email, is_read, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_app_metrics_app_created ON app_metrics(app_id, created_at)");
}
function app_notify(string $email, string $appId, string $severity, string $title, string $message = '', string $eventType = '', array $context = []): void {
    try {
        $db = app_main_db();
        app_notifications_ensure($db);
        $severity = in_array($severity, ['info','success','warning','error','critical'], true) ? $severity : 'info';
        $st = $db->prepare("INSERT INTO app_notifications(user_email,app_id,severity,title,message,event_type,context_json,is_read,created_at) VALUES(:u,:a,:s,:t,:m,:e,:c,0,:n)");
        $st->bindValue(':u', $email, SQLITE3_TEXT);
        $st->bindValue(':a', $appId, SQLITE3_TEXT);
        $st->bindValue(':s', $severity, SQLITE3_TEXT);
        $st->bindValue(':t', $title, SQLITE3_TEXT);
        $st->bindValue(':m', $message, SQLITE3_TEXT);
        $st->bindValue(':e', $eventType, SQLITE3_TEXT);
        $st->bindValue(':c', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $st->bindValue(':n', time(), SQLITE3_INTEGER);
        $st->execute();
    } catch (Throwable $e) {
        error_log('app_notify: ' . $e->getMessage());
    }
}
function app_metric(string $email, string $appId, string $metric, float $value, string $unit = '', array $context = []): void {
    try {
        $db = app_main_db();
        app_notifications_ensure($db);
        $st = $db->prepare("INSERT INTO app_metrics(app_id,metric,value,unit,user_email,context_json,created_at) VALUES(:a,:m,:v,:u,:e,:c,:t)");
        $st->bindValue(':a', $appId, SQLITE3_TEXT);
        $st->bindValue(':m', $metric, SQLITE3_TEXT);
        $st->bindValue(':v', $value, SQLITE3_FLOAT);
        $st->bindValue(':u', $unit, SQLITE3_TEXT);
        $st->bindValue(':e', $email, SQLITE3_TEXT);
        $st->bindValue(':c', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $st->bindValue(':t', time(), SQLITE3_INTEGER);
        $st->execute();
    } catch (Throwable $e) {
        error_log('app_metric: ' . $e->getMessage());
    }
}
