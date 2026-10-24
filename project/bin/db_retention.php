#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}
define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/lib/app_db.php';
$loop = in_array('--loop', $argv ?? [], true);
$days = static fn(string $env, int $def): int => max(1, (int)(getenv($env) ?: $def)) * 86400;
function retentionEpoch(int $age): string { return '(extract(epoch from now())::bigint - ' . $age . ')'; }
function retentionTs(int $age): string { return "(now() - interval '" . $age . " seconds')"; }
function retentionIdent(string $s): bool { return (bool)preg_match('/^[a-z_][a-z0-9_]*$/', $s); }
function retentionTableExists(AppDatabase $db, string $t): bool {
    return (bool)$db->querySingle("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='" . $t . "' LIMIT 1");
}
function retentionPurge(AppDatabase $db, string $table, string $where, int $batch = 5000): int {
    if (!retentionIdent($table) || !retentionTableExists($db, $table)) return 0;
    $total = 0;
    do {
        $db->exec("DELETE FROM $table WHERE ctid IN (SELECT ctid FROM $table WHERE $where LIMIT $batch)");
        $n = (int)$db->changes();
        $total += $n;
        if ($n >= $batch) usleep(200000);
    } while ($n >= $batch);
    return $total;
}
function retentionIndex(AppDatabase $db, string $table, string $name, string $cols): void {
    if (!retentionIdent($table) || !retentionIdent($name) || !retentionTableExists($db, $table)) return;
    try { $db->exec("CREATE INDEX IF NOT EXISTS $name ON $table($cols)"); } catch (Throwable $e) {}
}
$run = static function () use ($days): void {
    $db = app_main_db();
    $db->enableExceptions(true);
    $audit = $days('RETENTION_AUDIT_DAYS', 180);
    $hist = $days('RETENTION_HISTORY_DAYS', 30);
    $notif = $days('RETENTION_NOTIFICATION_DAYS', 90);
    $rules = [
        ['admin_audit_log', 'created_at < ' . retentionEpoch($audit)],
        ['smarthome_params_history', 'recorded_at < ' . retentionTs($hist)],
        ['smarthome_monitor_logs', 'created_at < ' . retentionTs($hist)],
        ['smarthome_errors', 'created_at < ' . retentionTs($hist)],
        ['app_notifications', 'is_read = 1 AND created_at < ' . retentionEpoch($notif)],
        ['app_notifications', 'created_at < ' . retentionEpoch($notif * 3)],
        ['monitor_notifications', 'created_at < ' . retentionEpoch($notif)],
        ['cloud_sync_logs', 'ts < ' . retentionEpoch($hist)],
        ['app_metrics', 'created_at < ' . retentionEpoch($hist)],
        ['client_auth_tokens', 'expires_at < ' . retentionEpoch(86400) . ' OR revoked = 1 AND last_used_at < ' . retentionEpoch($hist)],
        ['tg_bot_sessions', 'authorized_at < ' . retentionEpoch($days('RETENTION_TG_SESSION_DAYS', 90))],
    ];
    retentionIndex($db, 'active_sessions', 'idx_active_sessions_last_activity', 'last_activity');
    retentionIndex($db, 'client_auth_tokens', 'idx_client_auth_tokens_expires', 'expires_at');
    retentionIndex($db, 'smarthome_params_history', 'idx_sh_params_history_recorded', 'recorded_at');
    retentionIndex($db, 'smarthome_errors', 'idx_sh_errors_created', 'created_at');
    retentionIndex($db, 'cloud_sync_logs', 'idx_cloud_sync_logs_ts', 'ts');
    retentionIndex($db, 'monitor_notifications', 'idx_monitor_notifications_created', 'created_at');
    foreach (['idx_config_key_unique', 'idx_users_email_unique', 'idx_sh_monitor_logs_created', 'idx_smarthome_monitor_logs_created_at'] as $dup) {
        try { $db->exec('DROP INDEX IF EXISTS ' . $dup); } catch (Throwable $e) {}
    }
    foreach (['monitor_disk_snapshots', 'users', 'site_cookie_consents', 'visit_stats', 'cloud_sync_logs'] as $t) {
        if (!retentionTableExists($db, $t)) continue;
        try { $db->exec("ALTER TABLE $t SET (autovacuum_vacuum_scale_factor = 0.05, autovacuum_analyze_scale_factor = 0.05)"); } catch (Throwable $e) {}
    }
    foreach ($rules as [$table, $where]) {
        try {
            $n = retentionPurge($db, $table, $where);
            fwrite(STDOUT, date('c') . " $table deleted=$n\n");
        } catch (Throwable $e) {
            fwrite(STDERR, date('c') . " $table error=" . $e->getMessage() . "\n");
        }
    }
};
if (!$loop) {
    $run();
    exit(0);
}
while (true) {
    $run();
    sleep(6 * 3600);
}
