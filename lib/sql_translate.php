<?php
declare(strict_types=1);
function app_sql_pk_columns(string $table): array {
    static $map = [
        'users' => ['email'],
        'config' => ['key'],
        'app_meta' => ['key'],
        'active_sessions' => ['session_id'],
        'client_auth_tokens' => ['token_hash'],
        'client_auth_browser_requests' => ['request_id'],
        'visit_stats' => ['app_id', 'stat_date'],
        'shared_files' => ['share_id'],
        'reset_codes' => ['email'],
        'verification_tokens' => ['email'],
        'pending_users' => ['email'],
        'user_folder_paths' => ['app_key'],
        'app_access_levels' => ['app_id'],
        'cloud_user_settings' => ['email'],
        'cloud_folder_state' => ['email'],
        'cloud_storage_usage_stats' => ['email'],
        'cloud_encryption_consents' => ['email'],
        'cloud_ai_assistant_user_settings' => ['email'],
        'trusted_device_cookies' => ['cookie_hash'],
        'trusted_devices' => ['email', 'fingerprint'],
        'ip_lockouts' => ['ip', 'lock_type'],
        'one_time_pins' => ['id'],
        'quick_commands' => ['id'],
        'recipes_data' => ['id'],
        'recipes_products' => ['id'],
        'recipes_config' => ['key'],
        'recipes_state' => ['key'],
        'site_cookie_consents' => ['consent_key', 'device_id'],
        'smart_app_logs' => ['signature'],
        'smarthome_settings' => ['user_email'],
        'smarthome_floor_plans' => ['user_email'],
        'smarthome_token_index' => ['token'],
        'smarthome_control_queue' => ['id'],
        'canvas' => ['key'],
        'recipes_state' => ['key'],
        'smarthome_control_results' => ['id'],
        'smarthome_ws_tokens' => ['token'],
        'map_location_tokens' => ['token'],
        'viewer_meta' => ['id'],
        'tg_bot_sessions' => ['chat_id'],
        'monitor_metric_hourly' => ['bucket_start', 'metric_key'],
        'settings' => ['key'],
        'table_settings' => ['key'],
        'stores' => ['id'],
        'expenses' => ['date', 'store'],
        'incomes' => ['date'],
        'ocr_entries' => ['signature'],
        'steps' => ['date'],
        'video_job_state' => ['id'],
        'fit_takeout_job_state' => ['id'],
    ];
    return $map[$table] ?? [];
}
function app_sql_translate_replace(string $sql): string {
    if (!preg_match('/\bINSERT\s+OR\s+REPLACE\s+INTO\s+"?([a-z_]+)"?\s*\(([^)]+)\)\s*VALUES/is', $sql, $m)) {
        return preg_replace('/\bINSERT\s+OR\s+REPLACE\b/i', 'INSERT', $sql) ?? $sql;
    }
    $table = $m[1];
    $cols = array_values(array_filter(array_map(static fn($c) => trim(str_replace('"', '', $c)), explode(',', $m[2]))));
    $pk = app_sql_pk_columns($table);
    if (empty($pk)) $pk = [$cols[0] ?? 'id'];
    $updates = [];
    foreach ($cols as $col) {
        if (in_array($col, $pk, true)) continue;
        $updates[] = '"' . $col . '" = EXCLUDED."' . $col . '"';
    }
    $conflict = implode(', ', array_map(static fn($c) => '"' . $c . '"', $pk));
    $base = preg_replace('/\bINSERT\s+OR\s+REPLACE\b/i', 'INSERT', $sql) ?? $sql;
    $base = rtrim(trim($base), ';');
    if ($updates) return $base . ' ON CONFLICT (' . $conflict . ') DO UPDATE SET ' . implode(', ', $updates);
    return $base . ' ON CONFLICT (' . $conflict . ') DO NOTHING';
}
function app_sql_is_noop(string $sql): bool {
    $s = trim($sql);
    if ($s === '') return true;
    if (preg_match('/^PRAGMA\s+(journal_mode|busy_timeout|synchronous|cache_size|temp_store|foreign_keys)\b/i', $s)) return true;
    if (preg_match('/^PRAGMA\s+foreign_keys\s*=\s*ON/i', $s)) return true;
    return false;
}
function app_sql_translate_common(string $sql): string {
    $out = $sql;
    $out = preg_replace('/\bINTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT\b/i', 'BIGSERIAL PRIMARY KEY', $out) ?? $out;
    $out = preg_replace('/\bAUTOINCREMENT\b/i', '', $out) ?? $out;
    $out = preg_replace("/DEFAULT\s*\(\s*strftime\s*\(\s*'%s'\s*,\s*'now'\s*\)\s*\)/i", 'DEFAULT (extract(epoch from now())::bigint)', $out) ?? $out;
    $out = preg_replace("/DEFAULT\s*\(\s*datetime\s*\(\s*'now'\s*,\s*'localtime'\s*\)\s*\)/i", "DEFAULT (to_char(now(), 'YYYY-MM-DD HH24:MI:SS'))", $out) ?? $out;
    $out = preg_replace("/(?<![a-z_])datetime\s*\(\s*'now'\s*,\s*'localtime'\s*\)/i", "to_char(now(), 'YYYY-MM-DD HH24:MI:SS')", $out) ?? $out;
    $out = preg_replace("/datetime\s*\(\s*'now'\s*,\s*'-30 days'\s*(?:,\s*'localtime'\s*)?\)/i", "to_char(now() - interval '30 days', 'YYYY-MM-DD HH24:MI:SS')", $out) ?? $out;
    $out = preg_replace("/datetime\s*\(\s*'now'\s*,\s*'-' \|\| :period \|\| ' hours'\s*(?:,\s*'localtime'\s*)?\)/i", "to_char(now() - (CAST(:period AS integer) * interval '1 hour'), 'YYYY-MM-DD HH24:MI:SS')", $out) ?? $out;
    $out = preg_replace("/(?<![a-z_])strftime\s*\(\s*'%s'\s*,\s*'now'\s*\)/i", 'extract(epoch from now())::bigint', $out) ?? $out;
    $out = preg_replace('/\bBEGIN\s+IMMEDIATE\b/i', 'BEGIN', $out) ?? $out;
    return $out;
}
function app_sql_translate(string $sql): string {
    if (app_sql_is_noop($sql)) return '';
    $out = app_sql_translate_common($sql);
    if (preg_match('/\bINSERT\s+OR\s+REPLACE\b/i', $out)) return app_sql_translate_replace($out);
    $out = preg_replace('/\bINSERT\s+OR\s+IGNORE\b/i', 'INSERT', $out) ?? $out;
    if (preg_match('/\bINSERT\s+OR\s+IGNORE\b/i', $sql) && !preg_match('/\bON\s+CONFLICT\b/i', $out)) {
        if (preg_match('/\bINSERT\s+INTO\s+config\b/i', $out)) $out = rtrim($out, ';') . ' ON CONFLICT (key) DO NOTHING';
        elseif (preg_match('/\bINSERT\s+INTO\s+users\b/i', $out)) $out = rtrim($out, ';') . ' ON CONFLICT (email) DO NOTHING';
        elseif (preg_match('/\bINSERT\s+INTO\s+viewer_meta\b/i', $out)) $out = rtrim($out, ';') . ' ON CONFLICT (id) DO NOTHING';
        else $out = rtrim($out, ';') . ' ON CONFLICT DO NOTHING';
    }
    $out = preg_replace('/\bALTER\s+TABLE\s+([^\s]+)\s+ADD\s+COLUMN\s+/i', 'ALTER TABLE $1 ADD COLUMN IF NOT EXISTS ', $out) ?? $out;
    if (preg_match('/\bINSERT\s+INTO\s+config\s*\(/i', $out) && !preg_match('/\bON\s+CONFLICT\b/i', $out)) {
        $out = rtrim($out, ';') . ' ON CONFLICT (key) DO NOTHING';
    }
    if (preg_match('/\bON\s+CONFLICT\b/i', $out) && preg_match('/\bINSERT\s+INTO\s+"?(\w+)"?/i', $out, $tm)) {
        $tbl = $tm[1];
        $out = preg_replace('/\bROUND\s*\(\s*amount\s*\+\s*excluded\.amount\s*,/i', "ROUND(($tbl.amount+excluded.amount)::numeric,", $out) ?? $out;
    }
    $out = preg_replace('/\bROUND\s*\(\s*([^(),]+(?:\([^)]*\))?)\s*,\s*(\d+)\s*\)/i', 'ROUND(($1)::numeric, $2)', $out) ?? $out;
    return $out;
}
function app_sql_translate_query(string $sql): string {
    $out = app_sql_translate($sql);
    if (preg_match('/FROM\s+sqlite_master\b/i', $out)) {
        if (preg_match('/SELECT\s+sql\s+FROM\s+sqlite_master/i', $out)) {
            return "SELECT NULL::text AS sql WHERE false";
        }
        if (preg_match('/SELECT\s+1\s+FROM\s+sqlite_master\s+WHERE\s+type\s*=\s*[\'"]table[\'"]\s+AND\s+name\s*=\s*[\'"]([^\'"]+)[\'"]/i', $out, $m)) {
            $t = str_replace("'", "''", $m[1]);
            return "SELECT 1 FROM pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename = '$t' LIMIT 1";
        }
        if (preg_match('/SELECT\s+name\s+FROM\s+sqlite_master\s+WHERE\s+type\s*=\s*[\'"]table[\'"]\s+AND\s+name\s*=\s*:name/i', $out)) {
            return "SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename = :name LIMIT 1";
        }
        if (preg_match('/SELECT\s+name\s+FROM\s+sqlite_master\s+WHERE\s+type\s*=\s*[\'"]table[\'"]\s+AND\s+name\s*=\s*:n/i', $out)) {
            return "SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename = :n LIMIT 1";
        }
        if (preg_match('/SELECT\s+name,\s*type\s+FROM\s+sqlite_master/i', $out)) {
            return "SELECT tablename AS name, 'table'::text AS type FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename";
        }
        if (preg_match('/SELECT\s+name\s+FROM\s+sqlite_master\s+WHERE\s+type\s*=\s*[\'"]table[\'"]/i', $out)) {
            return "SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename";
        }
        if (preg_match('/SELECT\s+COUNT\s*\(\*\)\s+AS\s+c\s+FROM\s+sqlite_master\s+WHERE\s+name\s*=\s*:n/i', $out)) {
            return "SELECT COUNT(*)::int AS c FROM pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename = :n";
        }
        if (preg_match('/SELECT\s+COUNT\s*\(\*\)\s+AS\s+c\s+FROM\s+sqlite_master\s+WHERE\s+type\s*=\s*[\'"]index[\'"]\s+AND\s+tbl_name\s*=\s*:n/i', $out)) {
            return "SELECT COUNT(*)::int AS c FROM pg_catalog.pg_indexes WHERE schemaname = 'public' AND tablename = :n";
        }
    }
    if (preg_match('/\bORDER BY\s+[^;]*\browid\b/i', $out)) {
        $out = preg_replace('/\browid\b/i', 'id', $out) ?? $out;
    }
    if (strpos($out, 'expires_at > CURRENT_TIMESTAMP') !== false) {
        $out = str_replace('expires_at > CURRENT_TIMESTAMP', 'expires_at::timestamp > CURRENT_TIMESTAMP', $out);
    }
    if (preg_match('/^PRAGMA\s+table_info\s*\(\s*"?([^")]+)"?\s*\)/i', trim($out), $m)) {
        $t = str_replace("'", "''", $m[1]);
        return "SELECT ordinal_position AS cid, column_name AS name, data_type AS type, (is_nullable = 'NO')::int AS notnull, column_default AS dflt_value, 0 AS pk FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '$t' ORDER BY ordinal_position";
    }
    if (preg_match('/^PRAGMA\s+index_list\s*\(\s*"?([^")]+)"?\s*\)/i', trim($out), $m)) {
        $t = str_replace("'", "''", $m[1]);
        return "SELECT indexname AS name, 0 AS nonunique FROM pg_indexes WHERE schemaname = 'public' AND tablename = '$t'";
    }
    if (preg_match('/^PRAGMA\s+index_info\s*\(\s*"?([^")]+)"?\s*\)/i', trim($out), $m)) {
        $idx = str_replace("'", "''", $m[1]);
        return "SELECT a.attnum AS seqno, a.attname AS name FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) WHERE c.relname = '$idx' ORDER BY array_position(i.indkey, a.attnum)";
    }
    if (preg_match('/^PRAGMA\s+foreign_key_list\s*\(\s*"?([^")]+)"?\s*\)/i', trim($out), $m)) {
        $t = str_replace("'", "''", $m[1]);
        return "SELECT tc.constraint_name AS id, kcu.column_name AS \"from\", ccu.table_name AS \"table\", ccu.column_name AS \"to\", rc.update_rule AS on_update, rc.delete_rule AS on_delete FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema JOIN information_schema.referential_constraints rc ON rc.constraint_name = tc.constraint_name AND rc.constraint_schema = tc.table_schema WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public' AND tc.table_name = '$t' ORDER BY kcu.ordinal_position";
    }
    if (preg_match('/^PRAGMA\s+(page_count|page_size|freelist_count|integrity_check|quick_check)\b/i', trim($out))) {
        return "SELECT 1 AS v";
    }
    if (preg_match('/^VACUUM\b/i', trim($out))) {
        return 'VACUUM ANALYZE';
    }
    return $out;
}
