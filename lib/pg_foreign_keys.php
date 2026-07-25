<?php
declare(strict_types=1);
function app_pg_foreign_key_definitions(): array {
    $u = ['users', 'email', 'CASCADE', 'CASCADE'];
    $fk = static function (string $table, string $column, array $ref, ?string $onDelete = null, ?string $onUpdate = null): array {
        return [
            'table' => $table,
            'column' => $column,
            'ref_table' => $ref[0],
            'ref_column' => $ref[1],
            'on_delete' => $onDelete ?? $ref[2],
            'on_update' => $onUpdate ?? $ref[3],
        ];
    };
    return [
        $fk('active_sessions', 'email', $u),
        $fk('shared_files', 'user_email', $u),
        $fk('reset_codes', 'email', $u),
        $fk('cloud_encryption_consents', 'email', $u),
        $fk('cloud_user_settings', 'email', $u),
        $fk('cloud_folder_state', 'email', $u),
        $fk('cloud_sync_logs', 'email', $u),
        $fk('cloud_storage_usage_stats', 'email', $u),
        $fk('cloud_ai_assistant_chats', 'email', $u),
        $fk('one_time_pins', 'email', $u),
        $fk('client_auth_tokens', 'email', $u),
        $fk('trusted_devices', 'email', $u),
        $fk('tg_bot_sessions', 'email', $u),
        $fk('oauth_connections', 'email', $u),
        $fk('quick_commands', 'email', $u),
        $fk('support_chat', 'user_email', $u),
        $fk('support_chat', 'sender_email', $u),
        $fk('monitor_disk_snapshots', 'snapshot_id', ['monitor_system_snapshots', 'id', 'CASCADE', 'NO ACTION']),
        $fk('monitor_app_events', 'user_email', $u, 'SET NULL', 'CASCADE'),
        $fk('monitor_http_requests', 'user_email', $u, 'SET NULL', 'CASCADE'),
        $fk('monitor_errors', 'user_email', $u, 'SET NULL', 'CASCADE'),
        $fk('monitor_alerts', 'rule_id', ['monitor_alert_rules', 'id', 'SET NULL', 'NO ACTION']),
        $fk('monitor_notifications', 'alert_id', ['monitor_alerts', 'id', 'CASCADE', 'NO ACTION']),
        $fk('gallery_hidden_albums', 'album_id', ['gallery_albums', 'id', 'CASCADE', 'NO ACTION']),
        $fk('gallery_album_stats', 'album_id', ['gallery_albums', 'id', 'CASCADE', 'NO ACTION']),
        $fk('map_location_data', 'token', ['map_location_tokens', 'token', 'NO ACTION', 'NO ACTION']),
        $fk('recipes_steps', 'recipe_id', ['recipes_data', 'id', 'CASCADE', 'NO ACTION']),
    ];
}
function app_pg_fk_constraint_name(string $table, string $column): string {
    $name = 'fk_' . $table . '_' . $column;
    return strlen($name) > 63 ? substr($name, 0, 63) : $name;
}
function app_pg_table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1');
    $stmt->execute(['public', $table]);
    return (bool)$stmt->fetchColumn();
}
function app_pg_fk_exists(PDO $pdo, string $name): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace WHERE n.nspname = 'public' AND c.conname = ? LIMIT 1");
    $stmt->execute([$name]);
    return (bool)$stmt->fetchColumn();
}
function app_pg_existing_tables(PDO $pdo): array {
    $rows = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll(PDO::FETCH_COLUMN);
    return array_fill_keys($rows ?: [], true);
}
function app_pg_existing_fks(PDO $pdo): array {
    $rows = $pdo->query("SELECT c.conname FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace WHERE n.nspname = 'public' AND c.contype = 'f'")->fetchAll(PDO::FETCH_COLUMN);
    return array_fill_keys($rows ?: [], true);
}
function ensure_pg_foreign_keys(AppDatabase $db): void {
    if (!function_exists('app_db_is_pgsql') || !app_db_is_pgsql()) {
        return;
    }
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    $pdo = $db->getPdo();
    $tables = app_pg_existing_tables($pdo);
    $constraints = app_pg_existing_fks($pdo);
    if (isset($tables['map_location_tokens'])) {
        try {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_map_location_tokens_token ON map_location_tokens(token)');
        } catch (Throwable $e) {
            error_log('ensure_pg_foreign_keys: map_location_tokens token index: ' . $e->getMessage());
        }
    }
    foreach (app_pg_foreign_key_definitions() as $def) {
        $table = $def['table'];
        $column = $def['column'];
        $refTable = $def['ref_table'];
        $refColumn = $def['ref_column'];
        if (!isset($tables[$table]) || !isset($tables[$refTable])) {
            continue;
        }
        $name = app_pg_fk_constraint_name($table, $column);
        if (isset($constraints[$name])) {
            continue;
        }
        $onDelete = strtoupper($def['on_delete']);
        $onUpdate = strtoupper($def['on_update']);
        $sql = sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s) ON DELETE %s ON UPDATE %s NOT VALID',
            pg_quote_ident($table),
            pg_quote_ident($name),
            pg_quote_ident($column),
            pg_quote_ident($refTable),
            pg_quote_ident($refColumn),
            $onDelete,
            $onUpdate
        );
        try {
            $pdo->exec($sql);
            $pdo->exec('ALTER TABLE ' . pg_quote_ident($table) . ' VALIDATE CONSTRAINT ' . pg_quote_ident($name));
        } catch (Throwable $e) {
            error_log('ensure_pg_foreign_keys: ' . $name . ': ' . $e->getMessage());
        }
    }
}
function pg_quote_ident(string $ident): string {
    return '"' . str_replace('"', '""', $ident) . '"';
}
