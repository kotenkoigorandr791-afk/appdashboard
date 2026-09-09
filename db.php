<?php
/**
 * db.php — PostgreSQL database layer (credentials: /opt/AppDashboard/data/db.env)
 * Data directory: /opt/AppDashboard/data/
 */
require_once __DIR__ . '/lib/db_config.php';
require_once __DIR__ . '/lib/pgsql_compat.php';
require_once __DIR__ . '/lib/db_auth_security.php';
require_once __DIR__ . '/lib/redis.php';
define('DB_PATH', '/opt/AppDashboard/data/db.env');
function appPasswordHash(string $password): string {
    $hash = password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
        'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
        'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
    ]);
    if ($hash === false) {
        error_log('appPasswordHash: ARGON2ID failed, falling back to BCRYPT for password hashing');
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    if ($hash === false) {
        error_log('appPasswordHash: BCRYPT also failed, using emergency fallback');
        $hash = '$2y$12$' . password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    }
    return $hash;
}
function appPasswordVerify(string $password, string $hash): bool {
    if ($hash === '' || $hash === false) return false;
    return password_verify($password, $hash);
}
function appPasswordNeedsRehash(string $hash): bool {
    return $hash === '' || password_needs_rehash($hash, PASSWORD_ARGON2ID, [
        'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
        'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
        'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
    ]);
}
function appClearPendingPasswordFlow(): void {
    unset($_SESSION['pending_oauth_data'], $_SESSION['pending_google_data'], $_SESSION['pending_set_password_only']);
}
function getDB(): AppDatabase {
    static $db = null;
    if ($db === null) {
        $db = AppDatabase::connect();
        $db->enableExceptions(true);
        $schemaStamp = '/opt/AppDashboard/data/.schema_init_stamp';
        $schemaKey = hash_file('sha256', __FILE__) . ':' . (is_file(__DIR__ . '/lib/pg_foreign_keys.php') ? hash_file('sha256', __DIR__ . '/lib/pg_foreign_keys.php') : '');
        if (!is_file($schemaStamp) || trim((string)@file_get_contents($schemaStamp)) !== $schemaKey || !app_db_schema_ready($db)) {
            initDB($db);
            @file_put_contents($schemaStamp, $schemaKey, LOCK_EX);
        }
    }
    return $db;
}
function app_db_schema_ready(AppDatabase $db): bool {
    try {
        return (bool)$db->querySingle("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='config' LIMIT 1");
    } catch (Throwable $e) {
        return false;
    }
}

function app_db_ensure_unique_index(AppDatabase $db, string $table, array $columns, string $indexName): void {
    $columnList = implode(',', $columns);
    try {
        $partition = implode(', ', $columns);
        $db->exec("DELETE FROM $table t USING (SELECT ctid, ROW_NUMBER() OVER (PARTITION BY $partition ORDER BY ctid) AS rn FROM $table) d WHERE t.ctid = d.ctid AND d.rn > 1");
    } catch (Throwable $e) {
    }
    try {
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS $indexName ON $table($columnList)");
    } catch (Throwable $e) {
    }
}
 
function ensure_cloud_encryption_consents_schema(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_encryption_consents (
        email         TEXT NOT NULL PRIMARY KEY,
        status        TEXT NOT NULL DEFAULT 'accepted' CHECK(status IN ('accepted')),
        consented_at  BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
        updated_at    BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    app_db_ensure_unique_index($db, 'cloud_encryption_consents', ['email'], 'idx_cloud_encryption_consents_email_unique');
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_encryption_consents_email ON cloud_encryption_consents(email)");
}
function ensureSupportChatSchema(AppDatabase $db): void {
    if (app_db_is_pgsql()) {
        $db->exec("CREATE TABLE IF NOT EXISTS support_chat (
            id BIGSERIAL PRIMARY KEY,
            user_email TEXT NOT NULL,
            sender_email TEXT NOT NULL,
            message TEXT NOT NULL DEFAULT '',
            media_path TEXT,
            media_type TEXT,
            recipient_email TEXT,
            chat_type TEXT NOT NULL DEFAULT 'support',
            is_deleted INTEGER NOT NULL DEFAULT 0,
            is_edited INTEGER NOT NULL DEFAULT 0,
            is_read INTEGER NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
            edited_at BIGINT
        )");
        foreach ([
            "ALTER TABLE support_chat ADD COLUMN IF NOT EXISTS media_path TEXT",
            "ALTER TABLE support_chat ADD COLUMN IF NOT EXISTS media_type TEXT",
            "ALTER TABLE support_chat ADD COLUMN IF NOT EXISTS recipient_email TEXT",
            "ALTER TABLE support_chat ADD COLUMN IF NOT EXISTS chat_type TEXT NOT NULL DEFAULT 'support'",
            "ALTER TABLE support_chat ADD COLUMN IF NOT EXISTS is_deleted INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE support_chat ADD COLUMN IF NOT EXISTS is_edited INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE support_chat ADD COLUMN IF NOT EXISTS is_read INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE support_chat ADD COLUMN IF NOT EXISTS edited_at BIGINT",
        ] as $sql) {
            try { $db->exec($sql); } catch (Throwable $e) {}
        }
        $db->exec("CREATE INDEX IF NOT EXISTS idx_support_chat_user ON support_chat(user_email)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_support_chat_created ON support_chat(created_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_support_chat_pair ON support_chat(chat_type,user_email,recipient_email)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_support_chat_sender ON support_chat(sender_email)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_support_chat_recipient ON support_chat(recipient_email)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_lower_email ON users(lower(email))");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_lower_name ON users(lower(name))");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_lower_email_pattern ON users(lower(email) text_pattern_ops)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_lower_name_pattern ON users(lower(name) text_pattern_ops)");
        $db->exec("CREATE TABLE IF NOT EXISTS chat_blocks (
            blocker_email TEXT NOT NULL,
            blocked_email TEXT NOT NULL,
            created_at BIGINT NOT NULL,
            PRIMARY KEY (blocker_email, blocked_email)
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS chat_hidden (
            email TEXT NOT NULL,
            chat_type TEXT NOT NULL,
            user_email TEXT NOT NULL,
            recipient_email TEXT,
            hidden_at BIGINT NOT NULL
        )");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_chat_hidden_unique ON chat_hidden(email,chat_type,user_email,COALESCE(recipient_email,''))");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS support_chat (
        id          BIGSERIAL PRIMARY KEY,
        user_email  TEXT NOT NULL,
        sender_email TEXT NOT NULL,
        message     TEXT NOT NULL DEFAULT '',
        media_path  TEXT,
        media_type  TEXT,
        recipient_email TEXT,
        chat_type TEXT NOT NULL DEFAULT 'support',
        is_deleted  INTEGER NOT NULL DEFAULT 0,
        is_edited   INTEGER NOT NULL DEFAULT 0,
        is_read     INTEGER NOT NULL DEFAULT 0,
        created_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        edited_at   INTEGER,
        FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (sender_email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_support_chat_user ON support_chat(user_email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_support_chat_created ON support_chat(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_support_chat_pair ON support_chat(chat_type,user_email,recipient_email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_support_chat_sender ON support_chat(sender_email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_support_chat_recipient ON support_chat(recipient_email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_lower_email ON users(lower(email))");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_users_lower_name ON users(lower(name))");
    try { $db->exec("ALTER TABLE support_chat ADD COLUMN media_path TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE support_chat ADD COLUMN media_type TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE support_chat ADD COLUMN recipient_email TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE support_chat ADD COLUMN chat_type TEXT NOT NULL DEFAULT 'support'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE support_chat ADD COLUMN is_deleted INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE support_chat ADD COLUMN is_edited INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE support_chat ADD COLUMN is_read INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE support_chat ADD COLUMN edited_at INTEGER"); } catch (Exception $e) {}
    $db->exec("CREATE TABLE IF NOT EXISTS chat_blocks (
        blocker_email TEXT NOT NULL,
        blocked_email TEXT NOT NULL,
        created_at BIGINT NOT NULL,
        PRIMARY KEY (blocker_email, blocked_email)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS chat_hidden (
        email TEXT NOT NULL,
        chat_type TEXT NOT NULL,
        user_email TEXT NOT NULL,
        recipient_email TEXT,
        hidden_at BIGINT NOT NULL
    )");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_chat_hidden_unique ON chat_hidden(email,chat_type,user_email,COALESCE(recipient_email,''))");
}
function ensureTelegramBotSchema(AppDatabase $db): void {
    if (app_db_is_pgsql()) {
        $db->exec("CREATE TABLE IF NOT EXISTS tg_bot_sessions (
            chat_id TEXT PRIMARY KEY,
            email TEXT NOT NULL,
            authorized_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
            telegram_user_id TEXT NOT NULL DEFAULT ''
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS tg_bot_link_tokens (
            token_hash TEXT PRIMARY KEY,
            email TEXT NOT NULL,
            created_by TEXT NOT NULL DEFAULT '',
            created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
            expires_at BIGINT NOT NULL,
            used_at BIGINT,
            used_chat_id TEXT NOT NULL DEFAULT ''
        )");
        foreach ([
            "ALTER TABLE tg_bot_sessions ADD COLUMN telegram_user_id TEXT NOT NULL DEFAULT ''",
            "ALTER TABLE tg_bot_link_tokens ADD COLUMN created_by TEXT NOT NULL DEFAULT ''",
            "ALTER TABLE tg_bot_link_tokens ADD COLUMN used_at BIGINT",
            "ALTER TABLE tg_bot_link_tokens ADD COLUMN used_chat_id TEXT NOT NULL DEFAULT ''",
        ] as $sql) {
            try { $db->exec($sql); } catch (Throwable $e) {}
        }
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS tg_bot_sessions (
            chat_id      TEXT PRIMARY KEY,
            email        TEXT NOT NULL,
            authorized_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            telegram_user_id TEXT NOT NULL DEFAULT '',
            FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
        )");
        try { $db->exec("ALTER TABLE tg_bot_sessions ADD COLUMN telegram_user_id TEXT NOT NULL DEFAULT ''"); } catch (Exception $e) {}
        $db->exec("CREATE TABLE IF NOT EXISTS tg_bot_link_tokens (
            token_hash TEXT PRIMARY KEY,
            email TEXT NOT NULL,
            created_by TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            expires_at INTEGER NOT NULL,
            used_at INTEGER,
            used_chat_id TEXT NOT NULL DEFAULT '',
            FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
        )");
        foreach ([
            "ALTER TABLE tg_bot_link_tokens ADD COLUMN created_by TEXT NOT NULL DEFAULT ''",
            "ALTER TABLE tg_bot_link_tokens ADD COLUMN used_at INTEGER",
            "ALTER TABLE tg_bot_link_tokens ADD COLUMN used_chat_id TEXT NOT NULL DEFAULT ''",
        ] as $sql) {
            try { $db->exec($sql); } catch (Throwable $e) {}
        }
    }
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tg_bot_sessions_email ON tg_bot_sessions(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tg_bot_sessions_tg_uid ON tg_bot_sessions(telegram_user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tg_bot_link_tokens_email ON tg_bot_link_tokens(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tg_bot_link_tokens_expires ON tg_bot_link_tokens(expires_at)");
}
function ensureOAuthConnectionsSchema(AppDatabase $db): void {
    if (!app_db_is_pgsql()) {
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_connections (
        id BIGSERIAL PRIMARY KEY,
        email TEXT NOT NULL,
        provider TEXT NOT NULL,
        provider_user_id TEXT NOT NULL,
        provider_email TEXT NOT NULL DEFAULT '',
        provider_name TEXT NOT NULL DEFAULT '',
        provider_photo TEXT NOT NULL DEFAULT '',
        linked_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
        UNIQUE(provider, provider_user_id),
        UNIQUE(email, provider)
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_oauth_connections_email ON oauth_connections(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_oauth_connections_provider ON oauth_connections(provider, provider_user_id)");
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_pending_links (
        email TEXT PRIMARY KEY,
        provider TEXT NOT NULL,
        provider_user_id TEXT NOT NULL,
        provider_email TEXT NOT NULL DEFAULT '',
        provider_name TEXT NOT NULL DEFAULT '',
        provider_photo TEXT NOT NULL DEFAULT '',
        created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint)
    )");
}
function ensureNotificationsSchema(AppDatabase $db): void {
    if (app_db_is_pgsql()) {
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
        $db->exec("CREATE TABLE IF NOT EXISTS app_notification_settings (
            email TEXT PRIMARY KEY,
            browser_enabled INTEGER NOT NULL DEFAULT 0,
            telegram_enabled INTEGER NOT NULL DEFAULT 1,
            updated_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint)
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
        foreach ([
            "ALTER TABLE app_notifications ADD COLUMN event_type TEXT NOT NULL DEFAULT ''",
            "ALTER TABLE app_notifications ADD COLUMN context_json TEXT NOT NULL DEFAULT '{}'",
            "ALTER TABLE app_notification_settings ADD COLUMN browser_enabled INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE app_notification_settings ADD COLUMN telegram_enabled INTEGER NOT NULL DEFAULT 1",
            "ALTER TABLE app_metrics ADD COLUMN unit TEXT NOT NULL DEFAULT ''",
        ] as $sql) {
            try { $db->exec($sql); } catch (Throwable $e) {}
        }
        $db->exec("CREATE INDEX IF NOT EXISTS idx_app_notifications_user_read ON app_notifications(user_email, is_read, created_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_app_notifications_created ON app_notifications(created_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_app_metrics_app_created ON app_metrics(app_id, created_at)");
        return;
    }
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
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        read_at INTEGER,
        FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS app_notification_settings (
        email TEXT PRIMARY KEY,
        browser_enabled INTEGER NOT NULL DEFAULT 0,
        telegram_enabled INTEGER NOT NULL DEFAULT 1,
        updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS app_metrics (
        id BIGSERIAL PRIMARY KEY,
        app_id TEXT NOT NULL DEFAULT '',
        metric TEXT NOT NULL,
        value REAL NOT NULL DEFAULT 0,
        unit TEXT NOT NULL DEFAULT '',
        user_email TEXT NOT NULL DEFAULT '',
        context_json TEXT NOT NULL DEFAULT '{}',
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
    )");
    foreach ([
        "ALTER TABLE app_notifications ADD COLUMN event_type TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE app_notifications ADD COLUMN context_json TEXT NOT NULL DEFAULT '{}'",
        "ALTER TABLE app_notification_settings ADD COLUMN browser_enabled INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE app_notification_settings ADD COLUMN telegram_enabled INTEGER NOT NULL DEFAULT 1",
        "ALTER TABLE app_metrics ADD COLUMN unit TEXT NOT NULL DEFAULT ''",
    ] as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }
    $db->exec("CREATE INDEX IF NOT EXISTS idx_app_notifications_user_read ON app_notifications(user_email, is_read, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_app_notifications_created ON app_notifications(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_app_metrics_app_created ON app_metrics(app_id, created_at)");
}
function createAppNotification(string $userEmail, string $appId, string $severity, string $title, string $message = '', string $eventType = '', array $context = []): int {
    $db = getDB();
    ensureNotificationsSchema($db);
    $severity = in_array($severity, ['info','success','warning','error','critical'], true) ? $severity : 'info';
    $stmt = $db->prepare("INSERT INTO app_notifications(user_email,app_id,severity,title,message,event_type,context_json,is_read,created_at) VALUES(:u,:a,:s,:t,:m,:e,:c,0,:n)");
    $stmt->bindValue(':u', $userEmail, PDO::PARAM_STR);
    $stmt->bindValue(':a', $appId, PDO::PARAM_STR);
    $stmt->bindValue(':s', $severity, PDO::PARAM_STR);
    $stmt->bindValue(':t', $title, PDO::PARAM_STR);
    $stmt->bindValue(':m', $message, PDO::PARAM_STR);
    $stmt->bindValue(':e', $eventType, PDO::PARAM_STR);
    $stmt->bindValue(':c', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
    $stmt->bindValue(':n', time(), PDO::PARAM_INT);
    $stmt->execute();
    return (int)$db->lastInsertRowID();
}
function recordAppMetric(string $userEmail, string $appId, string $metric, float $value, string $unit = '', array $context = []): void {
    $db = getDB();
    ensureNotificationsSchema($db);
    $stmt = $db->prepare("INSERT INTO app_metrics(app_id,metric,value,unit,user_email,context_json,created_at) VALUES(:a,:m,:v,:u,:e,:c,:t)");
    $stmt->bindValue(':a', $appId, PDO::PARAM_STR);
    $stmt->bindValue(':m', $metric, PDO::PARAM_STR);
    $stmt->bindValue(':v', $value, PDO::PARAM_STR);
    $stmt->bindValue(':u', $unit, PDO::PARAM_STR);
    $stmt->bindValue(':e', $userEmail, PDO::PARAM_STR);
    $stmt->bindValue(':c', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
    $stmt->bindValue(':t', time(), PDO::PARAM_INT);
    $stmt->execute();
}

// ─────────────────────────────────────────────────────────────────────────────
// Schema initialisation  (runs once on first connection)
// ─────────────────────────────────────────────────────────────────────────────
function initDB(AppDatabase $db): void {
    if (app_db_is_pgsql()) {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            email           TEXT PRIMARY KEY,
            name            TEXT NOT NULL DEFAULT '',
            password        TEXT NOT NULL DEFAULT '',
            photo           TEXT NOT NULL DEFAULT 'default_avatar.png',
            role            INTEGER NOT NULL DEFAULT 0,
            verified        INTEGER NOT NULL DEFAULT 0,
            language        TEXT NOT NULL DEFAULT 'en',
            theme           TEXT NOT NULL DEFAULT 'light',
            color           TEXT NOT NULL DEFAULT '#007bff',
            trusted_devices TEXT NOT NULL DEFAULT '[]',
            icon_sort_order TEXT NOT NULL DEFAULT 'alpha',
            icon_style      TEXT NOT NULL DEFAULT 'animated',
            wallpaper       TEXT NOT NULL DEFAULT 'default',
            must_change_password INTEGER NOT NULL DEFAULT 0,
            created_at      TEXT NOT NULL DEFAULT ''
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS pending_users (
            email       TEXT PRIMARY KEY,
            name        TEXT NOT NULL DEFAULT '',
            password    TEXT NOT NULL DEFAULT '',
            photo       TEXT NOT NULL DEFAULT 'default_avatar.png',
            role        INTEGER NOT NULL DEFAULT 0,
            language    TEXT NOT NULL DEFAULT 'en',
            theme       TEXT NOT NULL DEFAULT 'light',
            color       TEXT NOT NULL DEFAULT '#007bff',
            created_at  TEXT NOT NULL DEFAULT '',
            reg_method  TEXT NOT NULL DEFAULT 'Email'
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS active_sessions (
            session_id    TEXT PRIMARY KEY,
            email         TEXT NOT NULL,
            user_data     TEXT NOT NULL DEFAULT '{}',
            login_time    INTEGER NOT NULL,
            last_activity INTEGER NOT NULL,
            ip_address    TEXT NOT NULL DEFAULT '',
            user_agent    TEXT NOT NULL DEFAULT '',
            device        TEXT NOT NULL DEFAULT '',
            browser       TEXT NOT NULL DEFAULT '',
            FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sessions_email ON active_sessions(email)");
        $db->exec("CREATE TABLE IF NOT EXISTS reset_codes (
            email   TEXT PRIMARY KEY,
            code    TEXT NOT NULL,
            expires INTEGER NOT NULL,
            FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS verification_tokens (
            token   TEXT PRIMARY KEY,
            email   TEXT NOT NULL,
            expires INTEGER NOT NULL
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_verification_tokens_email ON verification_tokens(email)");
        try { $db->exec("ALTER TABLE users ADD COLUMN icon_sort_order TEXT NOT NULL DEFAULT 'alpha'"); } catch (Throwable $e) {}
        try { $db->exec("ALTER TABLE users ADD COLUMN icon_style TEXT NOT NULL DEFAULT 'animated'"); } catch (Throwable $e) {}
        try { $db->exec("ALTER TABLE users ADD COLUMN wallpaper TEXT NOT NULL DEFAULT 'default'"); } catch (Throwable $e) {}
        ensure_cloud_encryption_consents_schema($db);
        ensureTrustedDevicesSchema($db);
        ensureTrustedDeviceCookiesSchema($db);
        ensureSupportChatSchema($db);
        ensureTelegramBotSchema($db);
        ensureOAuthConnectionsSchema($db);
        ensureNotificationsSchema($db);
        ensureVerificationTokensSchema($db);
        require_once __DIR__ . '/lib/pg_foreign_keys.php';
        ensure_pg_foreign_keys($db);
    }

    // Enable foreign key support
    $db->exec("SELECT 1");
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        email           TEXT PRIMARY KEY,
        name            TEXT NOT NULL DEFAULT '',
        password        TEXT NOT NULL DEFAULT '',
        photo           TEXT NOT NULL DEFAULT 'default_avatar.png',
        role            INTEGER NOT NULL DEFAULT 0,
        verified        INTEGER NOT NULL DEFAULT 0,
        language        TEXT NOT NULL DEFAULT 'en',
        theme           TEXT NOT NULL DEFAULT 'light',
        color           TEXT NOT NULL DEFAULT '#007bff',
        trusted_devices TEXT NOT NULL DEFAULT '[]',
        icon_sort_order TEXT NOT NULL DEFAULT 'alpha',
        icon_style      TEXT NOT NULL DEFAULT 'animated',
        wallpaper       TEXT NOT NULL DEFAULT 'default',
        must_change_password INTEGER NOT NULL DEFAULT 0,
        created_at      TEXT NOT NULL DEFAULT ''
    )");
    app_db_ensure_unique_index($db, 'users', ['email'], 'idx_users_email_unique');
    try { $db->exec("ALTER TABLE users ADD COLUMN trusted_devices TEXT NOT NULL DEFAULT '[]'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN icon_sort_order TEXT NOT NULL DEFAULT 'alpha'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN icon_style TEXT NOT NULL DEFAULT 'animated'"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE users ADD COLUMN wallpaper TEXT NOT NULL DEFAULT 'default'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    $rootStmt = $db->prepare("INSERT INTO users (email,name,password,photo,role,verified,language,theme,color,trusted_devices,icon_sort_order,icon_style,wallpaper,must_change_password,created_at)
        VALUES('root@root','Root Administrator',:password,'default_avatar.png',2,1,'en','light','#007bff','[]','alpha','animated','default',1,:created_at)
        ON CONFLICT (email) DO NOTHING");
    $rootStmt->bindValue(':password', appPasswordHash('root'), PDO::PARAM_STR);
    $rootStmt->bindValue(':created_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
    $rootStmt->execute();

    // ── Pending users (awaiting email verification) ──────────────────────────
    // No FK to users — pending users do NOT yet exist in the users table
    $db->exec("CREATE TABLE IF NOT EXISTS pending_users (
        email       TEXT PRIMARY KEY,
        name        TEXT NOT NULL DEFAULT '',
        password    TEXT NOT NULL DEFAULT '',
        photo       TEXT NOT NULL DEFAULT 'default_avatar.png',
        role        INTEGER NOT NULL DEFAULT 0,
        language    TEXT NOT NULL DEFAULT 'en',
        theme       TEXT NOT NULL DEFAULT 'light',
        color       TEXT NOT NULL DEFAULT '#007bff',
        created_at  TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
        reg_method  TEXT NOT NULL DEFAULT 'Email'
    )");

    // ── Active sessions ──────────────────────────────────────────────────────
    // Added FOREIGN KEY with cascading delete
    $db->exec("CREATE TABLE IF NOT EXISTS active_sessions (
        session_id    TEXT PRIMARY KEY,
        email         TEXT NOT NULL,
        user_data     TEXT NOT NULL DEFAULT '{}',
        login_time    INTEGER NOT NULL,
        last_activity INTEGER NOT NULL,
        ip_address    TEXT NOT NULL DEFAULT '',
        user_agent    TEXT NOT NULL DEFAULT '',
        device        TEXT NOT NULL DEFAULT '',
        browser       TEXT NOT NULL DEFAULT '',
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_sessions_email ON active_sessions(email)");

    // ── Visit statistics ─────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS visit_stats (
        app_id  TEXT NOT NULL,
        stat_date TEXT NOT NULL,   -- YYYY-MM-DD
        visits  INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (app_id, stat_date)
    )");
    app_db_ensure_unique_index($db, 'visit_stats', ['app_id', 'stat_date'], 'idx_visit_stats_app_date_unique');
    $db->exec("CREATE INDEX IF NOT EXISTS idx_stats_date ON visit_stats(stat_date)");

    // ── Shared files ─────────────────────────────────────────────────────────
    // Added FOREIGN KEY with cascading delete
    $db->exec("CREATE TABLE IF NOT EXISTS shared_files (
        share_id    TEXT PRIMARY KEY,
        file_name   TEXT NOT NULL DEFAULT '',
        file_path   TEXT NOT NULL DEFAULT '',
        user_email  TEXT NOT NULL DEFAULT '',
        created_at  TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP),
        expires     INTEGER DEFAULT NULL,
        expires_option TEXT NOT NULL DEFAULT 'never',
        password_hash TEXT NOT NULL DEFAULT '',
        view_only INTEGER NOT NULL DEFAULT 0,
        disable_download INTEGER NOT NULL DEFAULT 0,
        disable_copy INTEGER NOT NULL DEFAULT 0,
        max_views INTEGER NOT NULL DEFAULT 0,
        view_count INTEGER NOT NULL DEFAULT 0,
        FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_shared_files_user ON shared_files(user_email)");
    try { $db->exec("ALTER TABLE shared_files ADD COLUMN expires_option TEXT NOT NULL DEFAULT 'never'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE shared_files ADD COLUMN password_hash TEXT NOT NULL DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE shared_files ADD COLUMN view_only INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE shared_files ADD COLUMN disable_download INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE shared_files ADD COLUMN disable_copy INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE shared_files ADD COLUMN max_views INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE shared_files ADD COLUMN view_count INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}

    // ── Password reset codes ─────────────────────────────────────────────────
    // Added FOREIGN KEY with cascading delete
    $db->exec("CREATE TABLE IF NOT EXISTS reset_codes (
        email   TEXT PRIMARY KEY,
        code    TEXT NOT NULL,
        expires INTEGER NOT NULL,
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");

    // ── Email verification tokens ─────────────────────────────────────────────
    // No FK to users — tokens are created for pending (not-yet-registered) users
    $db->exec("CREATE TABLE IF NOT EXISTS verification_tokens (
        token   TEXT PRIMARY KEY,
        email   TEXT NOT NULL,
        expires INTEGER NOT NULL
    )");
    app_db_ensure_unique_index($db, 'verification_tokens', ['token'], 'idx_verification_tokens_token_unique');
    $db->exec("CREATE INDEX IF NOT EXISTS idx_verification_tokens_email ON verification_tokens(email)");

    // ── Auth/SMTP configuration ──────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS config (
        key   TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT ''
    )");
    app_db_ensure_unique_index($db, 'config', ['key'], 'idx_config_key_unique');
    
    $defaults = [
        'google_client_id'     => '',
        'google_client_secret' => '',
        'github_client_id'     => '',
        'github_client_secret' => '',
        'omniroute_google_client_id' => '',
        'omniroute_google_client_secret' => '',
        'smtp_host'            => '',
        'smtp_port'            => '587',
        'smtp_username'        => '',
        'smtp_password'        => '',
        'tg_bot_token'         => '',
        'tg_webapp_base_url'   => 'https://serverkherson.xyz',
        'docker_host'          => 'https://docker.serverkherson.xyz',
        'docker_domain'        => 'docker.serverkherson.xyz',
        'docker_token'         => '',
        'docker_username'      => '',
        'docker_password'      => '',
    ];
    $stmt = $db->prepare("INSERT INTO config(key,value) VALUES(:k,:v) ON CONFLICT (key) DO NOTHING");
    foreach ($defaults as $k => $v) {
        $stmt->bindValue(':k', $k);
        $stmt->bindValue(':v', $v);
        $stmt->execute();
    }
    $db->exec("UPDATE config SET value='https://serverkherson.xyz' WHERE key='tg_webapp_base_url' AND trim(value)=''");
    $db->exec("INSERT INTO config(key,value) VALUES('tg_webapp_base_url','https://serverkherson.xyz') ON CONFLICT (key) DO NOTHING");

    // OnlyOffice JWT — keep DB in sync with env when provided, otherwise generate once.
    $envOnlyofficeSecret = getenv('ONLYOFFICE_JWT_SECRET');
    if (is_string($envOnlyofficeSecret)) {
        $envOnlyofficeSecret = trim($envOnlyofficeSecret);
    }
    $oo = @$db->querySingle("SELECT value FROM config WHERE key='onlyoffice_jwt_secret' LIMIT 1");
    $desiredOnlyofficeSecret = $envOnlyofficeSecret !== false && $envOnlyofficeSecret !== ''
        ? $envOnlyofficeSecret
        : (($oo === false || $oo === null || $oo === '') ? bin2hex(random_bytes(32)) : (string)$oo);
    if ($oo !== $desiredOnlyofficeSecret) {
        $stmt = $db->prepare('INSERT INTO config(key,value) VALUES(:k,:v) ON CONFLICT (key) DO UPDATE SET value=excluded.value');
        $stmt->bindValue(':k', 'onlyoffice_jwt_secret');
        $stmt->bindValue(':v', $desiredOnlyofficeSecret);
        $stmt->execute();
    }

    // ── App access levels (min user role 0–2 per app; missing row => default 2) ─
    $db->exec("CREATE TABLE IF NOT EXISTS app_access_levels (
        app_id   TEXT PRIMARY KEY,
        min_role INTEGER NOT NULL CHECK(min_role BETWEEN 0 AND 2)
    )");
    $legacyUar = $db->querySingle("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='user_app_restrictions' LIMIT 1");
    if ($legacyUar) {
        $db->exec("DROP TABLE user_app_restrictions");
    }

    // ── User folder paths ─────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS user_folder_paths (
        app_key   TEXT PRIMARY KEY,
        path      TEXT NOT NULL DEFAULT ''
    )");

    // ── Last active user (replaces last_active_user.json) ────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS app_meta (
        key   TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT ''
    )");
    app_db_ensure_unique_index($db, 'app_meta', ['key'], 'idx_app_meta_key_unique');

     // ── Cloud user settings (quota and session limits) ────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_user_settings (
        email            TEXT    PRIMARY KEY,
        quota_mb         INTEGER NOT NULL DEFAULT 500,
        session_limit_mb INTEGER NOT NULL DEFAULT 0,
        sync_local_path  TEXT    NOT NULL DEFAULT '',
        sync_interval_sec INTEGER NOT NULL DEFAULT 300,
        sync_mode        TEXT    NOT NULL DEFAULT 'mtime_size',
        sync_enabled     INTEGER NOT NULL DEFAULT 0,
        last_sync_at     INTEGER NOT NULL DEFAULT 0,
        updated_at       INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    app_db_ensure_unique_index($db, 'cloud_user_settings', ['email'], 'idx_cloud_user_settings_email_unique');
    
    // ── Cloud folder state (expanded/collapsed folders) ──────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_folder_state (
        email       TEXT    PRIMARY KEY,
        folder_json TEXT    NOT NULL DEFAULT '[]',
        updated_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS cloud_sync_logs (
        id       BIGSERIAL PRIMARY KEY,
        email    TEXT    NOT NULL,
        ts       INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        level    TEXT    NOT NULL DEFAULT 'INFO',
        root_key TEXT    NOT NULL DEFAULT '',
        action   TEXT    NOT NULL DEFAULT '',
        path     TEXT    NOT NULL DEFAULT '',
        message  TEXT    NOT NULL DEFAULT '',
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_sync_logs_email_ts ON cloud_sync_logs(email, ts DESC)");

    $db->exec("CREATE TABLE IF NOT EXISTS site_cookie_consents (
        consent_key  TEXT NOT NULL DEFAULT 'site_cookies',
        device_id    TEXT NOT NULL,
        email        TEXT NOT NULL DEFAULT '',
        status       TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','accepted','declined')),
        asked_at     INTEGER,
        responded_at INTEGER,
        created_at   INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        updated_at   INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        PRIMARY KEY (consent_key, device_id)
    )");
    app_db_ensure_unique_index($db, 'site_cookie_consents', ['consent_key', 'device_id'], 'idx_site_cookie_consents_key_device_unique');
    $db->exec("CREATE INDEX IF NOT EXISTS idx_site_cookie_consents_email ON site_cookie_consents(email)");

    ensure_cloud_encryption_consents_schema($db);
    
    // Seed default quota settings for existing users
    $db->exec("INSERT INTO cloud_user_settings (email, quota_mb, session_limit_mb)
               SELECT email, 500, 0 FROM users ON CONFLICT (email) DO NOTHING");

    // ── One-Time PINs ────────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS one_time_pins (
        id          BIGSERIAL PRIMARY KEY,
        pin         TEXT    NOT NULL UNIQUE,
        email       TEXT    NOT NULL,
        expires     INTEGER NOT NULL,
        created_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_otp_pin ON one_time_pins(pin)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_otp_email ON one_time_pins(email)");

    // ── IP Lockouts (brute-force protection for OTP and login) ───────────────
    $db->exec("CREATE TABLE IF NOT EXISTS ip_lockouts (
        ip           TEXT    NOT NULL,
        lock_type    TEXT    NOT NULL,
        attempts     INTEGER NOT NULL DEFAULT 0,
        locked_until INTEGER,
        last_attempt INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        PRIMARY KEY (ip, lock_type)
    )");
    app_db_ensure_unique_index($db, 'ip_lockouts', ['ip', 'lock_type'], 'idx_ip_lockouts_ip_type_unique');
    $db->exec("CREATE TABLE IF NOT EXISTS client_auth_tokens (
        token_hash   TEXT PRIMARY KEY,
        email        TEXT NOT NULL,
        device_id    TEXT NOT NULL DEFAULT '',
        device_name  TEXT NOT NULL DEFAULT '',
        platform     TEXT NOT NULL DEFAULT '',
        created_at   INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        last_used_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        expires_at   INTEGER NOT NULL,
        revoked      INTEGER NOT NULL DEFAULT 0,
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_tokens_email ON client_auth_tokens(email)");

    $db->exec("CREATE TABLE IF NOT EXISTS trusted_devices (
        id               BIGSERIAL PRIMARY KEY,
        email            TEXT NOT NULL,
        fingerprint      TEXT NOT NULL,
        ip               TEXT NOT NULL DEFAULT '',
        device_name      TEXT NOT NULL DEFAULT '',
        device_token_hash TEXT DEFAULT NULL,
        added_at         INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        last_seen        INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        UNIQUE(email, fingerprint),
        UNIQUE(device_token_hash),
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    app_db_ensure_unique_index($db, 'trusted_devices', ['email', 'fingerprint'], 'idx_trusted_devices_email_fingerprint_unique');
    $db->exec("CREATE INDEX IF NOT EXISTS idx_trusted_devices_email ON trusted_devices(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_trusted_devices_fingerprint ON trusted_devices(fingerprint)");

    // ── Telegram bot authenticated sessions ─────────────────────────────────
    ensureTelegramBotSchema($db);
    ensureOAuthConnectionsSchema($db);

    // ── Quick Commands for SSH Terminal ──────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS quick_commands (
        id          BIGSERIAL PRIMARY KEY,
        email       TEXT NOT NULL,
        name        TEXT NOT NULL DEFAULT '',
        command     TEXT NOT NULL DEFAULT '',
        directory   TEXT NOT NULL DEFAULT '',
        created_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        updated_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_quick_commands_email ON quick_commands(email)");

    // ── Support Chat ──────────────────────────────────────────────────────────
    ensureSupportChatSchema($db);
    ensureNotificationsSchema($db);

    // ── Monitoring (grouped monitor_* tables) ───────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS monitor_system_snapshots (
        id                         BIGSERIAL PRIMARY KEY,
        created_at                 INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        cpu_percent                REAL NOT NULL DEFAULT 0,
        memory_percent             REAL NOT NULL DEFAULT 0,
        swap_percent               REAL NOT NULL DEFAULT 0,
        disk_io_percent            REAL NOT NULL DEFAULT 0,
        disk_read_bytes_per_sec    INTEGER NOT NULL DEFAULT 0,
        disk_write_bytes_per_sec   INTEGER NOT NULL DEFAULT 0,
        network_recv_bytes_per_sec INTEGER NOT NULL DEFAULT 0,
        network_sent_bytes_per_sec INTEGER NOT NULL DEFAULT 0,
        uptime_seconds             INTEGER,
        status                     TEXT NOT NULL DEFAULT 'ok',
        context_json               TEXT NOT NULL DEFAULT '{}'
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_system_snapshots_created ON monitor_system_snapshots(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_system_snapshots_status ON monitor_system_snapshots(status, created_at)");

    $db->exec("CREATE TABLE IF NOT EXISTS monitor_disk_snapshots (
        id            BIGSERIAL PRIMARY KEY,
        snapshot_id   INTEGER NOT NULL,
        drive_letter  TEXT NOT NULL,
        total_bytes   INTEGER NOT NULL DEFAULT 0,
        free_bytes    INTEGER NOT NULL DEFAULT 0,
        used_bytes    INTEGER NOT NULL DEFAULT 0,
        used_percent  REAL NOT NULL DEFAULT 0,
        FOREIGN KEY (snapshot_id) REFERENCES monitor_system_snapshots(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_disk_snapshots_snapshot ON monitor_disk_snapshots(snapshot_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_disk_snapshots_drive_created ON monitor_disk_snapshots(drive_letter, snapshot_id)");

    $db->exec("CREATE TABLE IF NOT EXISTS monitor_app_events (
        id            BIGSERIAL PRIMARY KEY,
        created_at    INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        level         TEXT NOT NULL DEFAULT 'info' CHECK(level IN ('debug','info','warning','error','critical')),
        app_id        TEXT NOT NULL DEFAULT '',
        event_type    TEXT NOT NULL,
        user_email    TEXT,
        ip_address    TEXT NOT NULL DEFAULT '',
        user_agent    TEXT NOT NULL DEFAULT '',
        message       TEXT NOT NULL DEFAULT '',
        context_json  TEXT NOT NULL DEFAULT '{}',
        FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE SET NULL ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_app_events_created ON monitor_app_events(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_app_events_level ON monitor_app_events(level, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_app_events_app ON monitor_app_events(app_id, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_app_events_user ON monitor_app_events(user_email, created_at)");

    $db->exec("CREATE TABLE IF NOT EXISTS monitor_http_requests (
        id             BIGSERIAL PRIMARY KEY,
        created_at     INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        method         TEXT NOT NULL DEFAULT '',
        path           TEXT NOT NULL DEFAULT '',
        app_id         TEXT NOT NULL DEFAULT '',
        user_email     TEXT,
        ip_address     TEXT NOT NULL DEFAULT '',
        status_code    INTEGER NOT NULL DEFAULT 200,
        duration_ms    INTEGER NOT NULL DEFAULT 0,
        response_bytes INTEGER NOT NULL DEFAULT 0,
        error_message  TEXT NOT NULL DEFAULT '',
        context_json   TEXT NOT NULL DEFAULT '{}',
        FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE SET NULL ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_http_requests_created ON monitor_http_requests(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_http_requests_status ON monitor_http_requests(status_code, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_http_requests_path ON monitor_http_requests(path, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_http_requests_user ON monitor_http_requests(user_email, created_at)");

    $db->exec("CREATE TABLE IF NOT EXISTS monitor_errors (
        id            BIGSERIAL PRIMARY KEY,
        created_at    INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        level         TEXT NOT NULL DEFAULT 'error' CHECK(level IN ('warning','error','critical')),
        app_id        TEXT NOT NULL DEFAULT '',
        user_email    TEXT,
        file          TEXT NOT NULL DEFAULT '',
        line          INTEGER,
        message       TEXT NOT NULL,
        trace_hash    TEXT NOT NULL DEFAULT '',
        context_json  TEXT NOT NULL DEFAULT '{}',
        resolved      INTEGER NOT NULL DEFAULT 0,
        resolved_at   INTEGER,
        FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE SET NULL ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_errors_created ON monitor_errors(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_errors_level ON monitor_errors(level, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_errors_resolved ON monitor_errors(resolved, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_errors_trace ON monitor_errors(trace_hash, created_at)");

    $db->exec("CREATE TABLE IF NOT EXISTS monitor_alert_rules (
        id              BIGSERIAL PRIMARY KEY,
        name            TEXT NOT NULL UNIQUE,
        metric          TEXT NOT NULL,
        operator        TEXT NOT NULL CHECK(operator IN ('>','>=','<','<=','=','!=')),
        threshold       REAL NOT NULL,
        duration_sec    INTEGER NOT NULL DEFAULT 300,
        severity        TEXT NOT NULL DEFAULT 'warning' CHECK(severity IN ('info','warning','critical')),
        enabled         INTEGER NOT NULL DEFAULT 1,
        notify_channels TEXT NOT NULL DEFAULT '[]',
        created_at      INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        updated_at      INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
    )");
    app_db_ensure_unique_index($db, 'monitor_alert_rules', ['name'], 'idx_monitor_alert_rules_name_unique');
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_alert_rules_metric ON monitor_alert_rules(metric, enabled)");

    $db->exec("CREATE TABLE IF NOT EXISTS monitor_alerts (
        id            BIGSERIAL PRIMARY KEY,
        rule_id       INTEGER,
        opened_at     INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        closed_at     INTEGER,
        severity      TEXT NOT NULL DEFAULT 'warning' CHECK(severity IN ('info','warning','critical')),
        status        TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open','closed')),
        title         TEXT NOT NULL,
        message       TEXT NOT NULL DEFAULT '',
        value         REAL,
        context_json  TEXT NOT NULL DEFAULT '{}',
        FOREIGN KEY (rule_id) REFERENCES monitor_alert_rules(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_alerts_status ON monitor_alerts(status, opened_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_alerts_rule_status ON monitor_alerts(rule_id, status)");

    $db->exec("CREATE TABLE IF NOT EXISTS monitor_notifications (
        id            BIGSERIAL PRIMARY KEY,
        alert_id      INTEGER NOT NULL,
        created_at    INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        channel       TEXT NOT NULL,
        recipient     TEXT NOT NULL DEFAULT '',
        status        TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','sent','failed','skipped')),
        error_message TEXT NOT NULL DEFAULT '',
        sent_at       INTEGER,
        FOREIGN KEY (alert_id) REFERENCES monitor_alerts(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_notifications_alert ON monitor_notifications(alert_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_notifications_status ON monitor_notifications(status, created_at)");

    $db->exec("CREATE TABLE IF NOT EXISTS monitor_metric_hourly (
        metric_hour INTEGER NOT NULL,
        metric      TEXT NOT NULL,
        app_id      TEXT NOT NULL DEFAULT '',
        min_value   REAL NOT NULL DEFAULT 0,
        avg_value   REAL NOT NULL DEFAULT 0,
        max_value   REAL NOT NULL DEFAULT 0,
        sample_count INTEGER NOT NULL DEFAULT 0,
        updated_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        PRIMARY KEY (metric_hour, metric, app_id)
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_monitor_metric_hourly_metric ON monitor_metric_hourly(metric, metric_hour)");

    $defaultRules = [
        ['High CPU usage', 'system.cpu_percent', '>=', 90, 300, 'critical'],
        ['High memory usage', 'system.memory_percent', '>=', 85, 300, 'warning'],
        ['High swap usage', 'system.swap_percent', '>=', 80, 300, 'warning'],
        ['High disk IO', 'system.disk_io_percent', '>=', 90, 300, 'warning'],
        ['Low disk space', 'disk.used_percent', '>=', 90, 60, 'critical'],
        ['Frequent application errors', 'errors.count_5m', '>=', 10, 0, 'critical'],
        ['Frequent HTTP server errors', 'http.5xx_count_5m', '>=', 3, 0, 'critical'],
    ];
    $ruleStmt = $db->prepare("INSERT INTO monitor_alert_rules
        (name, metric, operator, threshold, duration_sec, severity, notify_channels)
        VALUES(:name, :metric, :operator, :threshold, :duration_sec, :severity, '[]')");
    foreach ($defaultRules as $rule) {
        if ((int)$db->querySingle("SELECT COUNT(*) FROM monitor_alert_rules WHERE name = '" . AppDatabase::escapeString($rule[0]) . "'") > 0) {
            continue;
        }
        $ruleStmt->bindValue(':name', $rule[0], PDO::PARAM_STR);
        $ruleStmt->bindValue(':metric', $rule[1], PDO::PARAM_STR);
        $ruleStmt->bindValue(':operator', $rule[2], PDO::PARAM_STR);
        $ruleStmt->bindValue(':threshold', $rule[3], PDO::PARAM_STR);
        $ruleStmt->bindValue(':duration_sec', $rule[4], PDO::PARAM_INT);
        $ruleStmt->bindValue(':severity', $rule[5], PDO::PARAM_STR);
        $ruleStmt->execute();
    }

    $cloudSettingsV2 = $db->querySingle("SELECT value FROM app_meta WHERE key='cloud_user_settings_v2'");
    if (!$cloudSettingsV2) {
        $cols = [];
        $res = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='cloud_user_settings'");
        while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
            $cols[] = $row['name'] ?? '';
        }
        if (!in_array('sync_local_path', $cols, true)) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN sync_local_path TEXT NOT NULL DEFAULT ''");
        if (!in_array('sync_interval_sec', $cols, true)) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN sync_interval_sec INTEGER NOT NULL DEFAULT 300");
        if (!in_array('sync_mode', $cols, true)) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN sync_mode TEXT NOT NULL DEFAULT 'mtime_size'");
        if (!in_array('sync_enabled', $cols, true)) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN sync_enabled INTEGER NOT NULL DEFAULT 0");
        if (!in_array('last_sync_at', $cols, true)) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN last_sync_at INTEGER NOT NULL DEFAULT 0");
        monitorRetentionSetMeta($db, 'cloud_user_settings_v2', '1');
    }

    $db->exec("CREATE TABLE IF NOT EXISTS smart_app_logs (
        signature   TEXT PRIMARY KEY NOT NULL,
        app_id      TEXT NOT NULL DEFAULT '',
        level       TEXT NOT NULL DEFAULT 'error',
        script      TEXT NOT NULL DEFAULT '',
        source_file TEXT NOT NULL DEFAULT '',
        source_line INTEGER,
        message     TEXT NOT NULL,
        hit_count   INTEGER NOT NULL DEFAULT 1,
        first_seen  INTEGER NOT NULL,
        last_seen   INTEGER NOT NULL
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_smart_app_logs_app_last ON smart_app_logs(app_id, last_seen DESC)");

    $trustedDevicesMigDone = $db->querySingle("SELECT value FROM app_meta WHERE key='trusted_devices_v2_migrated'");
    if (!$trustedDevicesMigDone) {
        $result = $db->query("SELECT email, trusted_devices FROM users WHERE trusted_devices IS NOT NULL AND trusted_devices != '' AND trusted_devices != '[]'");
        if ($result) {
            $upsertTrusted = $db->prepare("INSERT INTO trusted_devices(email, fingerprint, ip, device_name, device_token_hash, added_at, last_seen)
                VALUES(:email, :fingerprint, :ip, :device_name, :device_token_hash, :added_at, :last_seen)
                ON CONFLICT(email, fingerprint) DO UPDATE SET
                    ip = excluded.ip,
                    device_name = CASE WHEN excluded.device_name != '' THEN excluded.device_name ELSE trusted_devices.device_name END,
                    device_token_hash = CASE WHEN excluded.device_token_hash != '' THEN excluded.device_token_hash ELSE trusted_devices.device_token_hash END,
                    last_seen = MAX(trusted_devices.last_seen, excluded.last_seen)");
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $email = (string)($row['email'] ?? '');
                $trustedDevices = json_decode((string)($row['trusted_devices'] ?? '[]'), true);
                if ($email === '' || !is_array($trustedDevices)) continue;
                foreach ($trustedDevices as $device) {
                    if (!is_array($device)) continue;
                    $fingerprint = trim((string)($device['fingerprint'] ?? ''));
                    if ($fingerprint === '') continue;
                    $encryptedToken = trim((string)($device['device_token'] ?? ''));
                    $deviceTokenHash = $encryptedToken !== '' ? hash('sha256', $encryptedToken) : null;
                    $addedAt = (int)($device['added_at'] ?? time());
                    $lastSeen = (int)($device['last_seen'] ?? $addedAt);
                    $upsertTrusted->bindValue(':email', $email, PDO::PARAM_STR);
                    $upsertTrusted->bindValue(':fingerprint', $fingerprint, PDO::PARAM_STR);
                    $upsertTrusted->bindValue(':ip', (string)($device['ip'] ?? ''), PDO::PARAM_STR);
                    $upsertTrusted->bindValue(':device_name', (string)($device['device_name'] ?? ''), PDO::PARAM_STR);
                    if ($deviceTokenHash !== null) {
                        $upsertTrusted->bindValue(':device_token_hash', $deviceTokenHash, PDO::PARAM_STR);
                    } else {
                        $upsertTrusted->bindValue(':device_token_hash', null, PDO::PARAM_NULL);
                    }
                    $upsertTrusted->bindValue(':added_at', $addedAt, PDO::PARAM_INT);
                    $upsertTrusted->bindValue(':last_seen', $lastSeen, PDO::PARAM_INT);
                    $upsertTrusted->execute();
                }
            }
        }
        monitorRetentionSetMeta($db, 'trusted_devices_v2_migrated', '1');
    }

}

// ─────────────────────────────────────────────────────────────────────────────
// USERS
// ─────────────────────────────────────────────────────────────────────────────
function loadUsers(): array {
    $db = getDB();
    $result = $db->query("SELECT * FROM users");
    $users = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $email = $row['email'];
        $row['verified'] = (bool)$row['verified'];
        $users[$email] = $row;
    }
    if (!isset($users['root@root'])) {
        $rootPw = appPasswordHash('root');
        upsertUser('root@root', [
            'email'      => 'root@root',
            'name'       => 'Root Administrator',
            'password'   => $rootPw,
            'photo'      => 'default_avatar.png',
            'role'       => 2,
            'verified'   => true,
            'language'   => 'en',
            'theme'      => 'light',
            'color'      => '#007bff',
            'icon_sort_order' => 'alpha',
            'icon_style' => 'animated',
            'must_change_password' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $users['root@root'] = getUserByEmail('root@root') ?? [];
    } elseif (empty($users['root@root']['must_change_password']) && appPasswordVerify('root', (string)($users['root@root']['password'] ?? ''))) {
        $users['root@root']['must_change_password'] = 1;
        upsertUser('root@root', $users['root@root']);
    }
    $users['root@root']['role']     = 2;
    $users['root@root']['verified'] = true;
    return $users;
}

function saveUsers(array $users): void {
    $db = getDB();
    
    // Protect root@root role
    if (isset($users['root@root'])) {
        $users['root@root']['role'] = 2;
        $users['root@root']['verified'] = true;
    }
    
    foreach ($users as $email => $u) {
        // Check if user exists
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $checkStmt->bindValue(':email', $email);
        $exists = (int)$checkStmt->execute()->fetch(PDO::FETCH_NUM)[0] > 0;
        
        if ($exists) {
            // Use UPDATE for existing users
            $stmt = $db->prepare("UPDATE users SET 
                name = :name,
                password = :password,
                photo = :photo,
                role = :role,
                verified = :verified,
                language = :language,
                theme = :theme,
                color = :color,
                icon_sort_order = :icon_sort_order,
                icon_style = :icon_style,
                wallpaper = :wallpaper,
                created_at = :created_at,
                must_change_password = :must_change_password
                WHERE email = :email");
        } else {
            // Use INSERT for new users
            $stmt = $db->prepare("INSERT INTO users 
                (email, name, password, photo, role, verified, language, theme, color, icon_sort_order, icon_style, wallpaper, created_at, must_change_password)
                VALUES(:email, :name, :password, :photo, :role, :verified, :language, :theme, :color, :icon_sort_order, :icon_style, :wallpaper, :created_at, :must_change_password)");
        }
        
        $stmt->bindValue(':email',      $email);
        $stmt->bindValue(':name',       $u['name']       ?? '');
        $stmt->bindValue(':password',   $u['password']   ?? '');
        $stmt->bindValue(':photo',      $u['photo']      ?? 'default_avatar.png');
        $stmt->bindValue(':role',       (int)($u['role'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':verified',   (int)(bool)($u['verified'] ?? false), PDO::PARAM_INT);
        $stmt->bindValue(':language',   $u['language']   ?? 'en');
        $stmt->bindValue(':theme',      $u['theme']      ?? 'light');
        $stmt->bindValue(':color',      $u['color']      ?? '#007bff');
        $stmt->bindValue(':icon_sort_order', $u['icon_sort_order'] ?? 'alpha');
        $stmt->bindValue(':icon_style', $u['icon_style'] ?? 'animated');
        $stmt->bindValue(':wallpaper',  $u['wallpaper']  ?? 'default');
        $stmt->bindValue(':created_at', $u['created_at'] ?? date('Y-m-d H:i:s'));
        $stmt->bindValue(':must_change_password', (int)($u['must_change_password'] ?? 0), PDO::PARAM_INT);
        $stmt->execute();
    }
}

function userNeedsPasswordSet(?array $user): bool {
    if (!$user) return false;
    $hash = trim((string)($user['password'] ?? ''));
    if ($hash === '') return true;
    $info = password_get_info($hash);
    return (($info['algoName'] ?? 'unknown') === 'unknown');
}

function getUserByEmail(string $email): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email=:e LIMIT 1");
    $stmt->bindValue(':e', $email);
    $row  = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['verified'] = (bool)$row['verified'];
    return $row;
}

function upsertUser(string $email, array $fields): void {
    $db      = getDB();
    $current = getUserByEmail($email) ?? [];
    $merged  = array_merge([
        'name'       => '',
        'password'   => '',
        'photo'      => 'default_avatar.png',
        'role'       => 0,
        'verified'   => false,
        'language'   => 'en',
        'theme'      => 'light',
        'color'      => '#007bff',
        'icon_sort_order' => 'alpha',
        'icon_style' => 'animated',
        'wallpaper' => 'default',
        'must_change_password' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ], $current, $fields);
    $stmt = $db->prepare("INSERT INTO users
        (email,name,password,photo,role,verified,language,theme,color,icon_sort_order,icon_style,wallpaper,created_at,must_change_password)
        VALUES(:email,:name,:password,:photo,:role,:verified,:language,:theme,:color,:icon_sort_order,:icon_style,:wallpaper,:created_at,:must_change_password)
        ON CONFLICT (email) DO UPDATE SET
            name=excluded.name,
            password=excluded.password,
            photo=excluded.photo,
            role=excluded.role,
            verified=excluded.verified,
            language=excluded.language,
            theme=excluded.theme,
            color=excluded.color,
            icon_sort_order=excluded.icon_sort_order,
            icon_style=excluded.icon_style,
            wallpaper=excluded.wallpaper,
            created_at=excluded.created_at,
            must_change_password=excluded.must_change_password");
    $stmt->bindValue(':email',      $email);
    $stmt->bindValue(':name',       $merged['name']);
    $stmt->bindValue(':password',   $merged['password']);
    $stmt->bindValue(':photo',      $merged['photo']);
    $stmt->bindValue(':role',       (int)$merged['role'],              PDO::PARAM_INT);
    $stmt->bindValue(':verified',   (int)(bool)$merged['verified'],    PDO::PARAM_INT);
    $stmt->bindValue(':language',   $merged['language']);
    $stmt->bindValue(':theme',      $merged['theme']);
    $stmt->bindValue(':color',      $merged['color']);
    $stmt->bindValue(':icon_sort_order', $merged['icon_sort_order']);
    $stmt->bindValue(':icon_style', $merged['icon_style'] ?? 'animated');
    $stmt->bindValue(':wallpaper',  $merged['wallpaper'] ?? 'default');
    $stmt->bindValue(':created_at', $merged['created_at']);
    $stmt->bindValue(':must_change_password', (int)($merged['must_change_password'] ?? 0), PDO::PARAM_INT);
    $stmt->execute();
    oauthConsumePendingLink($email);
    if (!empty($merged['verified']) || $email === 'root@root') {
        if (!function_exists('oauth_provision_user_services')) {
            $oauthFile = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/project/oauth.php';
            if (is_readable($oauthFile)) require_once $oauthFile;
        }
        if (function_exists('oauth_provision_user_services')) {
            try {
                oauth_provision_user_services($email, $merged);
            } catch (Throwable $e) {
                error_log('oauth_provision_user_services failed: ' . $e->getMessage());
            }
        }
    }
}

function deleteUser(string $email): void {
    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM users WHERE email=:e");
    $stmt->bindValue(':e', $email);
    $stmt->execute();
}

// ─────────────────────────────────────────────────────────────────────────────
// PENDING USERS
// ─────────────────────────────────────────────────────────────────────────────
function loadPendingUsers(): array {
    $db     = getDB();
    $result = $db->query("SELECT * FROM pending_users");
    $out    = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $out[$row['email']] = $row;
    }
    return $out;
}

function savePendingUsers(array $pending): void {
    $db   = getDB();
    // Replace entire set: delete all then re-insert
    $db->exec("DELETE FROM pending_users");
    $stmt = $db->prepare("INSERT INTO pending_users
        (email,name,password,photo,role,language,theme,color,created_at,reg_method)
        VALUES(:email,:name,:password,:photo,:role,:language,:theme,:color,:created_at,:reg_method)");
    foreach ($pending as $email => $u) {
        $stmt->bindValue(':email',      $email);
        $stmt->bindValue(':name',       $u['name']       ?? '');
        $stmt->bindValue(':password',   $u['password']   ?? '');
        $stmt->bindValue(':photo',      $u['photo']      ?? 'default_avatar.png');
        $stmt->bindValue(':role',       (int)($u['role'] ?? 0),  PDO::PARAM_INT);
        $stmt->bindValue(':language',   $u['language']   ?? 'en');
        $stmt->bindValue(':theme',      $u['theme']      ?? 'light');
        $stmt->bindValue(':color',      $u['color']      ?? '#007bff');
        $stmt->bindValue(':created_at', $u['created_at'] ?? date('Y-m-d H:i:s'));
        $stmt->bindValue(':reg_method', $u['reg_method'] ?? 'Email');
        $stmt->execute();
    }
}

function getPendingUser(string $email): ?array {
    $email = normalizeAuthEmail($email);
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM pending_users WHERE email=:e LIMIT 1");
    $stmt->bindValue(':e', $email);
    $row  = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function deletePendingUser(string $email): void {
    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM pending_users WHERE email=:e");
    $stmt->bindValue(':e', $email);
    $stmt->execute();
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTIVE SESSIONS
// ─────────────────────────────────────────────────────────────────────────────
function loadActiveSessions(): array {
    if ($redis = app_redis()) {
        $out = [];
        $it = null;
        $prefix = app_redis_key('active_session:');
        while (($keys = $redis->scan($it, $prefix . '*', 100)) !== false) {
            foreach ($keys as $key) {
                $raw = $redis->get((string)$key);
                $row = $raw ? json_decode((string)$raw, true) : null;
                if (!is_array($row)) continue;
                $sid = (string)($row['session_id'] ?? substr((string)$key, strlen($prefix)));
                $out[$sid] = $row;
            }
            if ($it === 0) break;
        }
        return $out;
    }
    $db          = getDB();
    $authTimeout = 8 * 3600;
    $cutoff      = time() - $authTimeout;
    // Delete stale sessions first — avoids loading entire table
    try {
        $db->exec("DELETE FROM active_sessions WHERE last_activity < $cutoff");
    } catch (Exception $e) {
        // Ignore lock errors on cleanup; stale sessions will be filtered below
    }
    $result = $db->query("SELECT * FROM active_sessions");
    $out    = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $row['user_data'] = json_decode($row['user_data'], true) ?? [];
        $out[$row['session_id']] = $row;
    }
    return $out;
}

function saveActiveSessions(array $sessions): void {
    if ($redis = app_redis()) {
        foreach ($sessions as $sid => $s) {
            if (!is_array($s)) continue;
            $email = (string)($s['email'] ?? '');
            if ($email === '') continue;
            $s['session_id'] = (string)$sid;
            $ttl = activeSessionTtlSeconds($email);
            $redis->setex(app_redis_key('active_session:' . (string)$sid), $ttl, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $redis->sAdd(app_redis_key('active_sessions_by_email:' . strtolower($email)), (string)$sid);
            $redis->expire(app_redis_key('active_sessions_by_email:' . strtolower($email)), $ttl);
        }
        return;
    }
    $db = getDB();
    // Full replace: clear then write — only called from legacy paths
    $db->exec("DELETE FROM active_sessions");
    $stmt = $db->prepare("INSERT INTO active_sessions
        (session_id,email,user_data,login_time,last_activity,ip_address,user_agent,device,browser)
        VALUES(:sid,:email,:ud,:lt,:la,:ip,:ua,:dev,:br)");
    foreach ($sessions as $sid => $s) {
        $stmt->bindValue(':sid',   $sid);
        $stmt->bindValue(':email', $s['email']);
        $stmt->bindValue(':ud',    json_encode($s['user_data'] ?? []));
        $stmt->bindValue(':lt',    (int)($s['login_time']    ?? time()), PDO::PARAM_INT);
        $stmt->bindValue(':la',    (int)($s['last_activity'] ?? time()), PDO::PARAM_INT);
        $stmt->bindValue(':ip',    $s['ip_address'] ?? '');
        $stmt->bindValue(':ua',    $s['user_agent']  ?? '');
        $stmt->bindValue(':dev',   $s['device']      ?? '');
        $stmt->bindValue(':br',    $s['browser']     ?? '');
        $stmt->execute();
    }
}

if (!function_exists('detectDeviceAndBrowser')) {
    function detectDeviceAndBrowser($userAgent): array {
        $device = 'unknown_device';
        $browser = 'unknown_browser';

        if (preg_match('/android/i', $userAgent)) {
            $device = 'android';
        } elseif (preg_match('/linux/i', $userAgent) && !preg_match('/android/i', $userAgent)) {
            $device = 'linux';
        } elseif (preg_match('/windows|win32|win64/i', $userAgent)) {
            $device = 'windows';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $device = 'ios';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $device = 'macos';
        }

        if (preg_match('/chrome/i', $userAgent) && !preg_match('/edg/i', $userAgent)) {
            $browser = 'chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $browser = 'firefox';
        } elseif (preg_match('/safari/i', $userAgent) && !preg_match('/chrome/i', $userAgent)) {
            $browser = 'safari';
        } elseif (preg_match('/edg/i', $userAgent)) {
            $browser = 'edge';
        } elseif (preg_match('/opera|opr/i', $userAgent)) {
            $browser = 'opera';
        }

        return ['device' => $device, 'browser' => $browser];
    }
}

if (!function_exists('detectDeviceAndBrowser')) {
    function detectDeviceAndBrowser($userAgent): array {
        $device = 'unknown_device';
        $browser = 'unknown_browser';

        if (preg_match('/android/i', $userAgent)) {
            $device = 'android';
        } elseif (preg_match('/linux/i', $userAgent) && !preg_match('/android/i', $userAgent)) {
            $device = 'linux';
        } elseif (preg_match('/windows|win32|win64/i', $userAgent)) {
            $device = 'windows';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $device = 'ios';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $device = 'macos';
        }

        if (preg_match('/chrome/i', $userAgent) && !preg_match('/edg/i', $userAgent)) {
            $browser = 'chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $browser = 'firefox';
        } elseif (preg_match('/safari/i', $userAgent) && !preg_match('/chrome/i', $userAgent)) {
            $browser = 'safari';
        } elseif (preg_match('/edg/i', $userAgent)) {
            $browser = 'edge';
        } elseif (preg_match('/opera|opr/i', $userAgent)) {
            $browser = 'opera';
        }

        return ['device' => $device, 'browser' => $browser];
    }
}

function addUserToActiveSessions(string $email, string $sessionId, array $userData): void {
    $user   = getUserByEmail($email);
    $actual = $user ?? $userData;
    if ($email === 'root@root') { $actual['role'] = 2; $actual['verified'] = true; }
    $ua        = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $detection = detectDeviceAndBrowser($ua);
    $ip        = $_SERVER['HTTP_X_FORWARDED_FOR']
              ?? $_SERVER['HTTP_X_REAL_IP']
              ?? $_SERVER['REMOTE_ADDR']
              ?? 'Unknown';
    $now = time();
    $row = [
        'session_id' => $sessionId,
        'email' => $email,
        'user_data' => $actual,
        'login_time' => $now,
        'last_activity' => $now,
        'ip_address' => $ip,
        'user_agent' => $ua,
        'device' => $detection['device'],
        'browser' => $detection['browser'],
    ];
    if ($redis = app_redis()) {
        $ttl = activeSessionTtlSeconds($email);
        $redis->setex(app_redis_key('active_session:' . $sessionId), $ttl, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $redis->sAdd(app_redis_key('active_sessions_by_email:' . strtolower($email)), $sessionId);
        $redis->expire(app_redis_key('active_sessions_by_email:' . strtolower($email)), $ttl);
        return;
    }
    $db = getDB();
    $checkStmt = $db->prepare("SELECT session_id FROM active_sessions WHERE session_id = :sid");
    $checkStmt->bindValue(':sid', $sessionId);
    $exists = $checkStmt->execute()->fetch();
    if ($exists) {
        $stmt = $db->prepare("UPDATE active_sessions
                              SET email = :email, user_data = :ud, login_time = :lt, 
                                  last_activity = :la, ip_address = :ip, user_agent = :ua, 
                                  device = :dev, browser = :br 
                              WHERE session_id = :sid");
    } else {
        $stmt = $db->prepare("INSERT INTO active_sessions
            (session_id,email,user_data,login_time,last_activity,ip_address,user_agent,device,browser)
            VALUES(:sid,:email,:ud,:lt,:la,:ip,:ua,:dev,:br)");
    }
    $stmt->bindValue(':sid',   $sessionId);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':ud',    json_encode($actual));
    $stmt->bindValue(':lt',    $now, PDO::PARAM_INT);
    $stmt->bindValue(':la',    $now, PDO::PARAM_INT);
    $stmt->bindValue(':ip',    $ip);
    $stmt->bindValue(':ua',    $ua);
    $stmt->bindValue(':dev',   $detection['device']);
    $stmt->bindValue(':br',    $detection['browser']);
    $stmt->execute();
}

/** Update last_activity without reading the whole table */
function updateUserActivity(string $sessionId): ?array {
    if ($redis = app_redis()) {
        $key = app_redis_key('active_session:' . $sessionId);
        $raw = $redis->get($key);
        if (!$raw) return null;
        $row = json_decode((string)$raw, true);
        if (!is_array($row)) return null;
        $email = (string)($row['email'] ?? '');
        $freshUser = getUserByEmail($email) ?: [];
        if ($email === 'root@root') {
            $freshUser['role'] = 2;
            $freshUser['verified'] = true;
        }
        $freshUser['email'] = $email;
        $row['user_data'] = $freshUser;
        $row['last_activity'] = time();
        $redis->setex($key, activeSessionTtlSeconds($email), json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $freshUser;
    }
    $db = getDB();
    // Reload user_data from users table to keep it fresh
    $stmt = $db->prepare("SELECT email FROM active_sessions WHERE session_id=:sid LIMIT 1");
    $stmt->bindValue(':sid', $sessionId);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $email = $row['email'];
    $user  = getUserByEmail($email);
    $freshUser = $user ?: [];
    if ($email === 'root@root') {
        $freshUser['role'] = 2;
        $freshUser['verified'] = true;
    }
    $freshUser['email'] = $email;
    $ud = json_encode($freshUser);
    $now  = time();
    $stmt = $db->prepare("UPDATE active_sessions SET last_activity=:la, user_data=:ud WHERE session_id=:sid");
    $stmt->bindValue(':la',  $now, PDO::PARAM_INT);
    $stmt->bindValue(':ud',  $ud);
    $stmt->bindValue(':sid', $sessionId);
    $stmt->execute();
    return $freshUser;
}

/** Remove session(s) for a user — optionally by specific session_id */
function removeUserFromActiveSessions(string $email, ?string $sessionId = null): void {
    if ($redis = app_redis()) {
        if ($sessionId !== null) {
            $redis->del(app_redis_key('active_session:' . $sessionId));
            if ($email !== '') $redis->sRem(app_redis_key('active_sessions_by_email:' . strtolower($email)), $sessionId);
            return;
        }
        $setKey = app_redis_key('active_sessions_by_email:' . strtolower($email));
        foreach ($redis->sMembers($setKey) ?: [] as $sid) {
            $redis->del(app_redis_key('active_session:' . (string)$sid));
        }
        $redis->del($setKey);
        return;
    }
    $db = getDB();
    if ($sessionId !== null) {
        $stmt = $db->prepare("DELETE FROM active_sessions WHERE session_id=:sid");
        $stmt->bindValue(':sid', $sessionId);
    } else {
        $stmt = $db->prepare("DELETE FROM active_sessions WHERE email=:e");
        $stmt->bindValue(':e', $email);
    }
    $stmt->execute();
}

function getUserActiveSessions(string $email): array {
    if ($redis = app_redis()) {
        $out = [];
        $setKey = app_redis_key('active_sessions_by_email:' . strtolower($email));
        foreach ($redis->sMembers($setKey) ?: [] as $sid) {
            $raw = $redis->get(app_redis_key('active_session:' . (string)$sid));
            if (!$raw) {
                $redis->sRem($setKey, $sid);
                continue;
            }
            $row = json_decode((string)$raw, true);
            if (!is_array($row)) continue;
            $out[(string)$sid] = $row;
        }
        return $out;
    }
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM active_sessions WHERE email=:e");
    $stmt->bindValue(':e', $email);
    $res  = $stmt->execute();
    $out  = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $row['user_data'] = json_decode($row['user_data'], true) ?? [];
        $out[$row['session_id']] = $row;
    }
    return $out;
}

/**
 * Delete expired sessions only for one user.
 * Returns number of deleted rows.
 */
function cleanupExpiredUserSessions(string $email, int $authTimeout = 12 * 60 * 60): int {
    if ($redis = app_redis()) {
        $deleted = 0;
        $setKey = app_redis_key('active_sessions_by_email:' . strtolower($email));
        foreach ($redis->sMembers($setKey) ?: [] as $sid) {
            if (!$redis->exists(app_redis_key('active_session:' . (string)$sid))) {
                $redis->sRem($setKey, $sid);
                $deleted++;
            }
        }
        return $deleted;
    }
    $db = getDB();
    $cutoff = time() - $authTimeout;
    $stmt = $db->prepare("DELETE FROM active_sessions WHERE email = :email AND last_activity < :cutoff");
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':cutoff', $cutoff, PDO::PARAM_INT);
    $stmt->execute();
    return $db->changes();
}

function activeSessionTtlSeconds(string $email): int {
    $fp = getDeviceFingerprint();
    if ($email !== '' && $fp !== '' && isTrustedDevice($email, $fp)) {
        return 365 * 24 * 3600;
    }
    return 12 * 3600;
}

function restoreSessionViaTrustedDevice(?string $preferredEmail = null): ?array {
    $hintEmail = $preferredEmail;
    if ($hintEmail === null || $hintEmail === '') {
        $hint = parseTrustedDeviceHintCookie((string)($_COOKIE['trusted_device_hint'] ?? ''));
        $hintEmail = (string)($hint['email'] ?? '');
    }
    foreach (getTrustedDeviceCookieCandidates($hintEmail) as $candidate) {
        $candidateToken = (string)($candidate['token'] ?? '');
        if ($candidateToken === '') continue;
        $rotated = verifyAndRotateTrustedDeviceCookie($candidateToken);
        if ($rotated === null) continue;
        $autoEmail = (string)($rotated['email'] ?? '');
        if ($autoEmail === '') continue;
        $users = loadUsers();
        if (!isset($users[$autoEmail])) {
            clearTrustedDeviceCookieForEmail($autoEmail);
            forgetTrustedDeviceBrowserAccount($autoEmail);
            if (!empty($candidate['legacy'])) clearTrustedDeviceCookie();
            continue;
        }
        return [
            'email' => $autoEmail,
            'user' => $users[$autoEmail],
            'new_token' => (string)($rotated['new_token'] ?? ''),
            'legacy_cookie' => !empty($candidate['legacy']),
        ];
    }
    return null;
}

function applyTrustedDeviceSessionRestore(array $restore): void {
    $email = (string)($restore['email'] ?? '');
    if ($email === '' || !is_array($restore['user'] ?? null)) return;
    if (session_status() !== PHP_SESSION_ACTIVE) app_session_start();
    session_regenerate_id(true);
    appClearPendingPasswordFlow();
    $_SESSION['user'] = $restore['user'];
    $_SESSION['user']['email'] = $email;
    $_SESSION['auth_time'] = time();
    setLastActiveUser($email);
    cleanupExpiredUserSessions($email);
    addUserToActiveSessions($email, session_id(), $_SESSION['user']);
    $newToken = (string)($restore['new_token'] ?? '');
    if ($newToken !== '') setTrustedDeviceCookieForEmail($email, $newToken);
    rememberTrustedDeviceBrowserAccount($email);
    setTrustedDeviceHintCookie($email, getDeviceFingerprint());
    if (!empty($restore['legacy_cookie'])) clearTrustedDeviceCookie();
    clearTrustedDeviceLogoutSuppression();
}

function ensureAuthenticatedSession(): bool {
    if (hasTrustedDeviceLogoutSuppression()) return false;
    if (isset($_SESSION['user']['email'], $_SESSION['auth_time'])) {
        if (getUserFromActiveSessions(session_id()) !== null) return true;
        $restore = restoreSessionViaTrustedDevice((string)$_SESSION['user']['email']);
        if ($restore === null || (string)($restore['email'] ?? '') !== (string)$_SESSION['user']['email']) return false;
        applyTrustedDeviceSessionRestore($restore);
        return getUserFromActiveSessions(session_id()) !== null;
    }
    $restore = restoreSessionViaTrustedDevice(null);
    if ($restore === null) return false;
    applyTrustedDeviceSessionRestore($restore);
    return getUserFromActiveSessions(session_id()) !== null;
}

/** Fetch a single session row — fast single-row lookup */
function getUserFromActiveSessions(string $sessionId): ?array {
    if ($redis = app_redis()) {
        $raw = $redis->get(app_redis_key('active_session:' . $sessionId));
        if ($raw) {
            $row = json_decode((string)$raw, true);
            if (is_array($row)) return $row;
        }
        // Fallback to DB if Redis doesn't have the key
    }
    $db          = getDB();
    $authTimeout = 8 * 3600;
    $stmt        = $db->prepare("SELECT * FROM active_sessions WHERE session_id=:sid LIMIT 1");
    $stmt->bindValue(':sid', $sessionId);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if (time() - $row['last_activity'] > $authTimeout) {
        try { $db->exec("DELETE FROM active_sessions WHERE session_id='" . AppDatabase::escapeString($sessionId) . "'"); } catch(Exception $e) {}
        return null;
    }
    $row['user_data'] = json_decode($row['user_data'], true) ?? [];
    
    // Sync back to Redis if available
    if ($redis && isset($row['email'])) {
        $ttl = activeSessionTtlSeconds($row['email']);
        try {
            $redis->setex(app_redis_key('active_session:' . $sessionId), $ttl, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {}
    }
    
    return $row;
}

// ─────────────────────────────────────────────────────────────────────────────
// VISIT STATISTICS  — paginated / range queries
// ─────────────────────────────────────────────────────────────────────────────

/** Increment today's counter for an app (single-row touch) */
function incrementVisitStat(string $appId): void {
    $db   = getDB();
    $today = date('Y-m-d');
    $stmt  = $db->prepare("INSERT INTO visit_stats(app_id,stat_date,visits) VALUES(:a,:d,1)
                            ON CONFLICT(app_id,stat_date) DO UPDATE SET visits=visit_stats.visits+1");
    $stmt->bindValue(':a', $appId);
    $stmt->bindValue(':d', $today);
    $stmt->execute();
}

/** Load stats for a date range — returns ['appId'=>['YYYY-MM-DD'=>visits,...],...] */
function loadVisitStatsRange(?string $dateFrom = null, ?string $dateTo = null): array {
    $db  = getDB();
    $sql = "SELECT app_id, stat_date, visits FROM visit_stats WHERE 1=1 AND app_id IS NOT NULL AND app_id != ''";
    if ($dateFrom) $sql .= " AND stat_date >= '" . AppDatabase::escapeString($dateFrom) . "'";
    if ($dateTo)   $sql .= " AND stat_date <= '" . AppDatabase::escapeString($dateTo)   . "'";
    $sql .= " ORDER BY stat_date ASC";
    $res = $db->query($sql);
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[$row['app_id']][$row['stat_date']] = (int)$row['visits'];
    }
    return $out;
}

/** Full stats — all time (used for admin panel) */
function loadVisitStats(): array {
    return loadVisitStatsRange();
}

function saveVisitStats(array $stats): void {
    $db = getDB();
    $db->exec("DELETE FROM visit_stats");
    $stmt = $db->prepare("INSERT INTO visit_stats(app_id,stat_date,visits) VALUES(:a,:d,:v)");
    foreach ($stats as $appId => $dates) {
        if (!is_array($dates)) continue;
        foreach ($dates as $date => $visits) {
            $stmt->bindValue(':a', $appId);
            $stmt->bindValue(':d', (string)$date);
            $stmt->bindValue(':v', (int)$visits, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
}

/** Get total visits per app (for sidebar sorting) — single aggregation query */
function getTotalVisitsPerApp(): array {
    $db  = getDB();
    $res = $db->query("SELECT app_id, SUM(visits) as total FROM visit_stats GROUP BY app_id");
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[$row['app_id']] = (int)$row['total'];
    }
    return $out;
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED FILES
// ─────────────────────────────────────────────────────────────────────────────
/**
 * @param string|null $userEmail      Filter by owner; ignored when $loadAllForAdmin is true
 * @param bool        $loadAllForAdmin Only for role=admin — loads entire table; never pass true from unauthenticated code
 */
function loadSharedFiles(?string $userEmail = null, bool $loadAllForAdmin = false): array {
    $db = getDB();
    if ($loadAllForAdmin) {
        $result = $db->query("SELECT * FROM shared_files ORDER BY created_at DESC");
    } elseif ($userEmail !== null && $userEmail !== '') {
        $stmt = $db->prepare("SELECT * FROM shared_files WHERE user_email = :email ORDER BY created_at DESC");
        $stmt->bindValue(':email', $userEmail);
        $result = $stmt->execute();
    } else {
        return [];
    }
    
    $out = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $out[$row['share_id']] = $row;
    }
    return $out;
}

// Replace saveSharedFiles function with:
function saveSharedFiles(array $files): void {
    $db = getDB();
    $db->exec("DELETE FROM shared_files");
    $stmt = $db->prepare("INSERT INTO shared_files(share_id, file_name, file_path, user_email, created_at, expires, expires_option, password_hash, view_only, disable_download, disable_copy, max_views, view_count)
                          VALUES(:id, :fn, :fp, :ue, :ca, :ex, :eo, :ph, :vo, :dd, :dc, :mv, :vc)");
    foreach ($files as $id => $f) {
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':fn', $f['file_name']  ?? '');
        $stmt->bindValue(':fp', $f['file_path']  ?? '');
        $stmt->bindValue(':ue', $f['user_email'] ?? '');
        $stmt->bindValue(':ca', $f['created_at'] ?? date('Y-m-d H:i:s'));
        $stmt->bindValue(':ex', $f['expires']    ?? null);
        $stmt->bindValue(':eo', $f['expires_option'] ?? 'never');
        $stmt->bindValue(':ph', $f['password_hash'] ?? '');
        $stmt->bindValue(':vo', (int)($f['view_only'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':dd', (int)($f['disable_download'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':dc', (int)($f['disable_copy'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':mv', (int)($f['max_views'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':vc', (int)($f['view_count'] ?? 0), PDO::PARAM_INT);
        $stmt->execute();
    }
}

// Add function to get shared file by ID with user verification:
function getSharedFileById(string $shareId, ?string $userEmail = null): ?array {
    $db = getDB();
    if ($userEmail !== null) {
        $stmt = $db->prepare("SELECT * FROM shared_files WHERE share_id = :id AND user_email = :email LIMIT 1");
        $stmt->bindValue(':email', $userEmail);
    } else {
        $stmt = $db->prepare("SELECT * FROM shared_files WHERE share_id = :id LIMIT 1");
    }
    $stmt->bindValue(':id', $shareId);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function deleteSharedFile(string $shareId): bool {
    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM shared_files WHERE share_id=:id");
    $stmt->bindValue(':id', $shareId);
    $stmt->execute();
    return $db->changes() > 0;
}

function deleteAllSharedFiles(): void {
    getDB()->exec("DELETE FROM shared_files");
}

function deleteSharedFilesBatch(array $ids): int {
    $db      = getDB();
    $escaped = array_map([AppDatabase::class, 'escapeString'], $ids);
    $in      = "'" . implode("','", $escaped) . "'";
    $db->exec("DELETE FROM shared_files WHERE share_id IN ($in)");
    return $db->changes();
}

function updateSharedFileExpiration(string $shareId, ?int $expires, string $expiresOption = 'never'): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE shared_files SET expires = :expires, expires_option = :expires_option WHERE share_id = :id");
    if ($expires === null) $stmt->bindValue(':expires', null, PDO::PARAM_NULL);
    else $stmt->bindValue(':expires', $expires, PDO::PARAM_INT);
    $stmt->bindValue(':expires_option', $expiresOption ?: 'never', PDO::PARAM_STR);
    $stmt->bindValue(':id', $shareId, PDO::PARAM_STR);
    $stmt->execute();
    return $db->changes() > 0;
}

// ─────────────────────────────────────────────────────────────────────────────
// RESET CODES
// ─────────────────────────────────────────────────────────────────────────────
function loadResetCodes(): array {
    $db  = getDB();
    $res = $db->query("SELECT * FROM reset_codes WHERE expires > " . time());
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[$row['email']] = ['code' => $row['code'], 'expires' => (int)$row['expires']];
    }
    return $out;
}

function saveResetCodes(array $codes): void {
    $db = getDB();
    $db->exec("DELETE FROM reset_codes");
    $stmt = $db->prepare("INSERT INTO reset_codes(email,code,expires) VALUES(:e,:c,:ex)");
    foreach ($codes as $email => $data) {
        $stmt->bindValue(':e',  $email);
        $stmt->bindValue(':c',  $data['code']);
        $stmt->bindValue(':ex', (int)$data['expires'], PDO::PARAM_INT);
        $stmt->execute();
    }
}

/** Cryptographically strong password-reset token (not a short numeric PIN) */
function generateResetCode(): string {
    return bin2hex(random_bytes(16));
}

// ─────────────────────────────────────────────────────────────────────────────
// VERIFICATION TOKENS
// ─────────────────────────────────────────────────────────────────────────────
function normalizeAuthEmail(string $email): string {
    return strtolower(trim($email));
}
function sanitizeVerificationToken(?string $token): string {
    $token = strtolower(trim((string)$token));
    if ($token === '') return '';
    if (preg_match('/^[a-f0-9]{64}$/', $token)) return $token;
    if (preg_match('/([a-f0-9]{64})/', $token, $m)) return $m[1];
    return $token;
}
function ensureVerificationTokensSchema(AppDatabase $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        if (app_db_is_pgsql()) {
            $pkCol = (string)$db->querySingle("SELECT kcu.column_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                  ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
                WHERE tc.table_schema = 'public' AND tc.table_name = 'verification_tokens'
                  AND tc.constraint_type = 'PRIMARY KEY'
                LIMIT 1");
            if ($pkCol === 'token') {
                $db->exec('CREATE INDEX IF NOT EXISTS idx_verification_tokens_email ON verification_tokens(email)');
                return;
            }
            $db->exec('CREATE TABLE verification_tokens_v2 (
                token TEXT PRIMARY KEY,
                email TEXT NOT NULL,
                expires BIGINT NOT NULL
            )');
            $db->exec('INSERT INTO verification_tokens_v2 (token, email, expires)
                SELECT token, email, expires FROM verification_tokens
                ON CONFLICT (token) DO NOTHING');
            $db->exec('DROP TABLE verification_tokens');
            $db->exec('ALTER TABLE verification_tokens_v2 RENAME TO verification_tokens');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_verification_tokens_email ON verification_tokens(email)');
            return;
        }
        $pkCol = null;
        $cols = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='verification_tokens'");
        while ($row = $cols->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['pk'])) $pkCol = (string)$row['name'];
        }
        if ($pkCol === 'token') return;
        $db->exec('CREATE TABLE verification_tokens_v2 (
            token TEXT PRIMARY KEY,
            email TEXT NOT NULL,
            expires INTEGER NOT NULL
        )');
        $db->exec('INSERT INTO verification_tokens_v2 (token, email, expires)
            SELECT token, email, expires FROM verification_tokens');
        $db->exec('DROP TABLE verification_tokens');
        $db->exec('ALTER TABLE verification_tokens_v2 RENAME TO verification_tokens');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_verification_tokens_email ON verification_tokens(email)');
    } catch (Throwable $e) {
        error_log('[DB] ensureVerificationTokensSchema: ' . $e->getMessage());
    }
}

function loadVerificationTokens(): array {
    ensureVerificationTokensSchema(getDB());
    $db  = getDB();
    $res = $db->query("SELECT * FROM verification_tokens");
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $email = (string)$row['email'];
        if (!isset($out[$email]) || (int)$row['expires'] > (int)$out[$email]['expires']) {
            $out[$email] = ['token' => $row['token'], 'expires' => (int)$row['expires']];
        }
    }
    return $out;
}

function saveVerificationTokens(array $tokens): void {
    ensureVerificationTokensSchema(getDB());
    $db = getDB();
    $db->exec("DELETE FROM verification_tokens");
    $stmt = $db->prepare("INSERT INTO verification_tokens(token,email,expires) VALUES(:t,:e,:ex)");
    foreach ($tokens as $email => $data) {
        $stmt->bindValue(':t',  $data['token']);
        $stmt->bindValue(':e',  normalizeAuthEmail($email));
        $stmt->bindValue(':ex', (int)$data['expires'], PDO::PARAM_INT);
        $stmt->execute();
    }
}

function cleanupExpiredVerificationTokens(): void {
    ensureVerificationTokensSchema(getDB());
    $db = getDB();
    $db->exec('DELETE FROM verification_tokens WHERE expires <= ' . time());
}

function saveVerificationTokenForEmail(string $email, string $token, int $expires): void {
    ensureVerificationTokensSchema(getDB());
    cleanupExpiredVerificationTokens();
    $email = normalizeAuthEmail($email);
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO verification_tokens(token,email,expires) VALUES(:t,:e,:ex)
        ON CONFLICT(token) DO UPDATE SET email=excluded.email, expires=excluded.expires");
    $stmt->bindValue(':t', $token);
    $stmt->bindValue(':e', $email);
    $stmt->bindValue(':ex', $expires, PDO::PARAM_INT);
    $stmt->execute();
}

function findVerificationTokenRow(string $token): ?array {
    $token = sanitizeVerificationToken($token);
    if ($token === '') return null;
    $db = getDB();
    $stmt = $db->prepare('SELECT email, token, expires FROM verification_tokens WHERE token = :t LIMIT 1');
    $stmt->bindValue(':t', $token);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function findLatestVerificationTokenForEmail(string $email): ?array {
    ensureVerificationTokensSchema(getDB());
    $email = normalizeAuthEmail($email);
    $db = getDB();
    $stmt = $db->prepare('SELECT email, token, expires FROM verification_tokens WHERE email = :e AND expires > :now ORDER BY expires DESC LIMIT 1');
    $stmt->bindValue(':e', $email);
    $stmt->bindValue(':now', time(), PDO::PARAM_INT);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// ─────────────────────────────────────────────────────────────────────────────
// CONFIG (auth, SMTP, Telegram, etc.)
// ─────────────────────────────────────────────────────────────────────────────
function getConfig(string $key, string $default = ''): string {
    $db   = getDB();
    $stmt = $db->prepare("SELECT value FROM config WHERE key=:k LIMIT 1");
    $stmt->bindValue(':k', $key);
    $row  = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : $default;
}

function setConfig(string $key, string $value): void {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO config(key,value) VALUES(:k,:v) ON CONFLICT (key) DO UPDATE SET value=excluded.value");
    $stmt->bindValue(':k', $key);
    $stmt->bindValue(':v', $value);
    $stmt->execute();
}

function loadAuthConfig(): array {
    $keys = ['google_client_id','google_client_secret','github_client_id','github_client_secret','smtp_host','smtp_port','smtp_username','smtp_password'];
    $out  = array_fill_keys($keys, '');
    $db = getDB();
    $quoted = implode(',', array_map(static fn($k) => "'" . AppDatabase::escapeString($k) . "'", $keys));
    $res = $db->query("SELECT key,value FROM config WHERE key IN ($quoted)");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[(string)$row['key']] = (string)$row['value'];
    }
    $out['smtp_port'] = (int)($out['smtp_port'] ?: 587);
    return $out;
}

function saveAuthConfig(array $cfg): void {
    foreach (['google_client_id','google_client_secret','github_client_id','github_client_secret','smtp_host','smtp_port','smtp_username','smtp_password'] as $k) {
        if (isset($cfg[$k])) setConfig($k, (string)$cfg[$k]);
    }
}

function getOAuthConnectionsForUser(string $email): array {
    ensureOAuthConnectionsSchema(getDB());
    $db = getDB();
    $stmt = $db->prepare("SELECT provider, provider_user_id, provider_email, provider_name, provider_photo, linked_at FROM oauth_connections WHERE email = :e ORDER BY provider");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $res = $stmt->execute();
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[(string)$row['provider']] = $row;
    }
    return $out;
}

function hasOAuthConnection(string $email, string $provider): bool {
    $connections = getOAuthConnectionsForUser($email);
    return isset($connections[$provider]);
}

function findUserEmailByOAuthProvider(string $provider, string $providerUserId): ?string {
    ensureOAuthConnectionsSchema(getDB());
    $db = getDB();
    $stmt = $db->prepare("SELECT email FROM oauth_connections WHERE provider = :p AND provider_user_id = :id LIMIT 1");
    $stmt->bindValue(':p', $provider, PDO::PARAM_STR);
    $stmt->bindValue(':id', $providerUserId, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ? (string)$row['email'] : null;
}

function saveOAuthPendingLink(string $email, array $profile): void {
    ensureOAuthConnectionsSchema(getDB());
    $db = getDB();
    $provider = (string)($profile['provider'] ?? '');
    $providerUserId = (string)($profile['provider_user_id'] ?? '');
    if ($provider === '' || $providerUserId === '') {
        return;
    }
    $del = $db->prepare("DELETE FROM oauth_pending_links WHERE email = :e");
    $del->bindValue(':e', $email, PDO::PARAM_STR);
    $del->execute();
    $stmt = $db->prepare("INSERT INTO oauth_pending_links (email, provider, provider_user_id, provider_email, provider_name, provider_photo, created_at)
        VALUES (:e, :p, :pid, :pe, :pn, :pp, :ca)");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':p', $provider, PDO::PARAM_STR);
    $stmt->bindValue(':pid', $providerUserId, PDO::PARAM_STR);
    $stmt->bindValue(':pe', (string)($profile['email'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':pn', (string)($profile['name'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':pp', (string)($profile['photo'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':ca', time(), PDO::PARAM_INT);
    $stmt->execute();
}

function oauthConsumePendingLink(string $email): void {
    ensureOAuthConnectionsSchema(getDB());
    $db = getDB();
    $stmt = $db->prepare("SELECT provider, provider_user_id, provider_email, provider_name, provider_photo FROM oauth_pending_links WHERE email = :e LIMIT 1");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }
    $del = $db->prepare("DELETE FROM oauth_pending_links WHERE email = :e");
    $del->bindValue(':e', $email, PDO::PARAM_STR);
    $del->execute();
    linkOAuthConnection($email, [
        'provider' => (string)$row['provider'],
        'provider_user_id' => (string)$row['provider_user_id'],
        'email' => (string)$row['provider_email'],
        'name' => (string)$row['provider_name'],
        'photo' => (string)$row['provider_photo'],
    ], false);
}

function linkOAuthConnection(string $email, array $profile, bool $allowPending = true): void {
    ensureOAuthConnectionsSchema(getDB());
    if ($allowPending && getUserByEmail($email) === null) {
        saveOAuthPendingLink($email, $profile);
        return;
    }
    $db = getDB();
    $provider = (string)$profile['provider'];
    $providerUserId = (string)$profile['provider_user_id'];
    $del = $db->prepare("DELETE FROM oauth_connections WHERE (email = :e AND provider = :p) OR (provider = :p AND provider_user_id = :pid)");
    $del->bindValue(':e', $email, PDO::PARAM_STR);
    $del->bindValue(':p', $provider, PDO::PARAM_STR);
    $del->bindValue(':pid', $providerUserId, PDO::PARAM_STR);
    $del->execute();
    $stmt = $db->prepare("INSERT INTO oauth_connections (email, provider, provider_user_id, provider_email, provider_name, provider_photo, linked_at)
        VALUES (:e, :p, :pid, :pe, :pn, :pp, :la)");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':p', $provider, PDO::PARAM_STR);
    $stmt->bindValue(':pid', $providerUserId, PDO::PARAM_STR);
    $stmt->bindValue(':pe', (string)($profile['email'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':pn', (string)($profile['name'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':pp', (string)($profile['photo'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':la', time(), PDO::PARAM_INT);
    $stmt->execute();
}

function unlinkOAuthConnection(string $email, string $provider): bool {
    ensureOAuthConnectionsSchema(getDB());
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM oauth_connections WHERE email = :e AND provider = :p");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':p', $provider, PDO::PARAM_STR);
    $stmt->execute();
    return $db->changes() > 0;
}

function loadOmnirouteOAuthConfig(): array {
    return [
        'google_client_id' => getConfig('omniroute_google_client_id', ''),
        'google_client_secret' => getConfig('omniroute_google_client_secret', ''),
    ];
}

function saveOmnirouteOAuthConfig(array $cfg): void {
    foreach (['google_client_id', 'google_client_secret'] as $k) {
        if (!array_key_exists($k, $cfg)) {
            continue;
        }
        $configKey = $k === 'google_client_id'
            ? 'omniroute_google_client_id'
            : 'omniroute_google_client_secret';
        setConfig($configKey, trim((string)$cfg[$k]));
    }
}

function syncOmnirouteOAuthEnv(?array $cfg = null): array {
    $cfg = $cfg ?: loadOmnirouteOAuthConfig();
    $envFile = '/opt/docker/omniroute/omniroute_data/server.env';
    $envDir = dirname($envFile);
    if (!is_dir($envDir)) {
        return ['success' => false, 'message' => 'OmniRoute data directory not found: ' . $envDir];
    }

    $existing = file_exists($envFile) ? (string)file_get_contents($envFile) : '';
    $lines = preg_split("/\\r\\n|\\n|\\r/", $existing);
    if ($lines === false) {
        $lines = [];
    }

    $managedKeys = [
        'GEMINI_CLI_OAUTH_CLIENT_ID' => trim((string)($cfg['google_client_id'] ?? '')),
        'GEMINI_CLI_OAUTH_CLIENT_SECRET' => trim((string)($cfg['google_client_secret'] ?? '')),
    ];
    $updated = [];
    $seen = [];

    foreach ($lines as $line) {
        if ($line === '') {
            $updated[] = $line;
            continue;
        }
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
            $key = $m[1];
            if (array_key_exists($key, $managedKeys)) {
                $seen[$key] = true;
                $value = $managedKeys[$key];
                if ($value !== '') {
                    $updated[] = $key . '=' . $value;
                }
                continue;
            }
        }
        $updated[] = $line;
    }

    foreach ($managedKeys as $key => $value) {
        if ($value === '' || isset($seen[$key])) {
            continue;
        }
        $updated[] = $key . '=' . $value;
    }

    $payload = rtrim(implode("\n", $updated)) . "\n";
    if ($existing !== $payload && !writeOmnirouteEnvFile($envFile, $payload)) {
        return ['success' => false, 'message' => 'Failed to update OmniRoute env file: ' . $envFile];
    }

    $restart = restartOmnirouteContainer();
    if (!$restart['success']) {
        return $restart;
    }

    return ['success' => true, 'message' => 'OmniRoute OAuth settings saved and container restarted'];
}

function writeOmnirouteEnvFile(string $envFile, string $payload): bool {
    if (@file_put_contents($envFile, $payload) !== false) {
        return true;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'omniroute-env-');
    if ($tmp === false) {
        return false;
    }

    $ok = false;
    try {
        if (@file_put_contents($tmp, $payload) === false) {
            return false;
        }
        $commands = [
            'sudo -n /usr/local/bin/omniroute-env-apply ' . escapeshellarg($tmp) . ' 2>&1',
            'sudo -n cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg($envFile) . ' 2>&1',
            'cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg($envFile) . ' 2>&1',
        ];
        foreach ($commands as $command) {
            $output = [];
            $exitCode = 1;
            @exec($command, $output, $exitCode);
            if ($exitCode === 0) {
                $ok = true;
                break;
            }
        }
    } finally {
        @unlink($tmp);
    }

    return $ok;
}

function restartOmnirouteContainer(): array {
    $commands = [
        'sudo -n /usr/local/bin/omniroute-restart 2>&1',
        'sudo -n docker restart omniroute 2>&1',
        'docker restart omniroute 2>&1',
        'sudo -n docker compose -f /opt/docker/omniroute/docker-compose.yml up -d omniroute 2>&1',
        'docker compose -f /opt/docker/omniroute/docker-compose.yml up -d omniroute 2>&1',
        'sudo -n docker-compose -f /opt/docker/omniroute/docker-compose.yml up -d omniroute 2>&1',
        'docker-compose -f /opt/docker/omniroute/docker-compose.yml up -d omniroute 2>&1',
    ];

    $output = [];
    $exitCode = 1;
    foreach ($commands as $command) {
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);
        if ($exitCode === 0) {
            return ['success' => true, 'message' => 'OmniRoute container restarted'];
        }
    }

    $message = trim(implode("\n", $output));
    if ($message === '') {
        $message = 'Unable to restart OmniRoute container automatically';
    }
    return ['success' => false, 'message' => $message];
}

function syncOnlyofficeJwtSecretConfig(?AppDatabase $db = null): ?string {
    $env = getenv('ONLYOFFICE_JWT_SECRET');
    if (!is_string($env)) {
        return null;
    }
    $env = trim($env);
    if ($env === '') {
        return null;
    }
    $db = $db ?: getDB();
    $stmt = $db->prepare("SELECT value FROM config WHERE key=:k LIMIT 1");
    $stmt->bindValue(':k', 'onlyoffice_jwt_secret');
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    $current = $row['value'] ?? '';
    if ($current !== $env) {
        $upsert = $db->prepare('INSERT INTO config(key,value) VALUES(:k,:v) ON CONFLICT (key) DO UPDATE SET value=excluded.value');
        $upsert->bindValue(':k', 'onlyoffice_jwt_secret');
        $upsert->bindValue(':v', $env);
        $upsert->execute();
    }
    return $env;
}

/** ONLYOFFICE Document Server JWT signing — use env ONLYOFFICE_JWT_SECRET and mirror it to DB when present */
function getOnlyofficeJwtSecret(): string {
    $env = syncOnlyofficeJwtSecretConfig();
    if (is_string($env) && $env !== '') {
        return $env;
    }
    return getConfig('onlyoffice_jwt_secret');
}

function loadTgBotConfig(): array {
    return [
        'bot_token' => getConfig('tg_bot_token', ''),
        'webapp_base_url' => trim(getConfig('tg_webapp_base_url', '')),
    ];
}

function saveTgBotConfig(array $cfg): bool {
    setConfig('tg_bot_token', $cfg['bot_token'] ?? '');
    if (array_key_exists('webapp_base_url', $cfg)) {
        setConfig('tg_webapp_base_url', trim((string)$cfg['webapp_base_url']));
    }
    return true;
}
function loadDockerConfig(): array {
    $domain = trim(getConfig('docker_domain', 'docker.serverkherson.xyz'));
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = rtrim($domain, '/');
    $username = decryptDockerCredential(getConfig('docker_username', ''));
    $password = decryptDockerCredential(getConfig('docker_password', ''));
    return [
        'host' => rtrim(getConfig('docker_host', 'https://docker.serverkherson.xyz'), '/'),
        'domain' => $domain !== '' ? $domain : 'docker.serverkherson.xyz',
        'token' => getConfig('docker_token', ''),
        'username' => $username ?? '',
        'password' => $password ?? ''
    ];
}
function saveDockerConfig(array $cfg): bool {
    $host = rtrim((string)($cfg['host'] ?? 'https://docker.serverkherson.xyz'), '/');
    $domain = trim((string)($cfg['domain'] ?? 'docker.serverkherson.xyz'));
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = rtrim($domain, '/');
    setConfig('docker_host', $host);
    setConfig('docker_domain', $domain !== '' ? $domain : 'docker.serverkherson.xyz');
    setConfig('docker_token', trim((string)($cfg['token'] ?? '')));
    $username = trim((string)($cfg['username'] ?? ''));
    $password = trim((string)($cfg['password'] ?? ''));
    setConfig('docker_username', encryptDockerCredential($username));
    setConfig('docker_password', encryptDockerCredential($password));
    return true;
}
function dockerCredentialKey(): string {
    $key = getenv('DOCKER_CONFIG_ENCRYPTION_KEY');
    if (is_string($key) && $key !== '') return $key;
    return getOnlyofficeJwtSecret();
}
function encryptDockerCredential(string $plain): string {
    if ($plain === '') return '';
    $key = hash('sha256', dockerCredentialKey(), true);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return '';
    return base64_encode($iv . $cipher);
}
function decryptDockerCredential(string $cipherText): ?string {
    if ($cipherText === '') return '';
    $raw = base64_decode($cipherText, true);
    if ($raw === false || strlen($raw) < 17) return null;
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $key = hash('sha256', dockerCredentialKey(), true);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? null : $plain;
}
function getUserBlockedApps(string $email): array {
    return [];
}

// ─────────────────────────────────────────────────────────────────────────────
// APP ACCESS LEVELS (global min role per app; default 2 if no row)
// ─────────────────────────────────────────────────────────────────────────────
function invalidateAppAccessLevelsCache(): void {
    if ($redis = app_redis()) {
        $redis->del(app_redis_key('app_access_levels'));
    }
}

function loadAppAccessLevels(): array {
    if ($redis = app_redis()) {
        $raw = $redis->get(app_redis_key('app_access_levels'));
        if (is_string($raw) && $raw !== '') {
            $cached = json_decode($raw, true);
            if (is_array($cached)) return $cached;
        }
    }
    $db  = getDB();
    $res = $db->query("SELECT app_id, min_role FROM app_access_levels");
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[$row['app_id']] = (int)$row['min_role'];
    }
    if ($redis = app_redis()) {
        $redis->setex(app_redis_key('app_access_levels'), 3600, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    return $out;
}

function getAppMinRole(string $appId): int {
    $levels = loadAppAccessLevels();
    if (!isset($levels[$appId])) return 2;
    return max(0, min(2, (int)$levels[$appId]));
}

function userMeetsAppMinRole(int $userRole, string $appId): bool {
    return $userRole >= getAppMinRole($appId);
}

function saveAppAccessLevelsFromMap(array $levels): void {
    $db = getDB();
    $db->exec("DELETE FROM app_access_levels");
    $stmt = $db->prepare("INSERT INTO app_access_levels(app_id, min_role) VALUES(:a,:r)");
    foreach ($levels as $appId => $lvl) {
        $lvl = max(0, min(2, (int)$lvl));
        if ($lvl === 2) {
            continue;
        }
        $stmt->bindValue(':a', (string)$appId);
        $stmt->bindValue(':r', $lvl, PDO::PARAM_INT);
        $stmt->execute();
    }
    invalidateAppAccessLevelsCache();
}
function loadAppAccessSecurityConfig(): array {
    $countries = json_decode(getConfig('app_access_blocked_countries', '[]'), true);
    $hostCountry = appAccessHostCountry();
    return [
        'blocked_countries' => is_array($countries) ? array_values(array_filter(array_unique(array_map('strtoupper', array_filter(array_map('strval', $countries)))), static fn($code) => $code !== $hostCountry)) : [],
        'block_vpn' => getConfig('app_access_block_vpn', '0') === '1',
    ];
}
function saveAppAccessSecurityConfig(array $cfg): void {
    $countries = $cfg['blocked_countries'] ?? [];
    if (!is_array($countries)) $countries = [];
    $hostCountry = appAccessHostCountry();
    $clean = [];
    foreach ($countries as $code) {
        $code = strtoupper(trim((string)$code));
        if (preg_match('/^[A-Z]{2}$/', $code) && $code !== $hostCountry) $clean[] = $code;
    }
    setConfig('app_access_blocked_countries', json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_UNICODE));
    setConfig('app_access_block_vpn', !empty($cfg['block_vpn']) ? '1' : '0');
}
function appAccessLookupIpMeta(string $ip): array {
    if ($ip === '' || $ip === '0.0.0.0' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return [];
    }
    $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode,proxy,hosting,mobile,query';
    $ctx = stream_context_create(['http' => ['timeout' => 1.5, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) && ($data['status'] ?? '') === 'success' ? $data : [];
}
function appAccessHostCountry(): string {
    $forced = strtoupper(trim((string)getenv('APP_HOST_COUNTRY')));
    if (preg_match('/^[A-Z]{2}$/', $forced)) return $forced;
    $cache = json_decode(getConfig('app_access_host_country_cache', '{}'), true);
    if (is_array($cache) && (int)($cache['expires'] ?? 0) > time()) {
        $country = strtoupper((string)($cache['country'] ?? ''));
        if (preg_match('/^[A-Z]{2}$/', $country)) return $country;
    }
    $ip = (string)($_SERVER['SERVER_ADDR'] ?? '');
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        $resolved = $host !== '' ? gethostbyname($host) : '';
        if (filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) $ip = $resolved;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $ctx = stream_context_create(['http' => ['timeout' => 1.5, 'ignore_errors' => true]]);
        $raw = @file_get_contents('https://api.ipify.org', false, $ctx);
        if (is_string($raw) && filter_var(trim($raw), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) $ip = trim($raw);
    }
    $meta = appAccessLookupIpMeta($ip);
    $country = strtoupper((string)($meta['countryCode'] ?? ''));
    if (!preg_match('/^[A-Z]{2}$/', $country)) $country = '';
    setConfig('app_access_host_country_cache', json_encode(['country' => $country, 'expires' => time() + 86400], JSON_UNESCAPED_UNICODE));
    return $country;
}
function appAccessClientMeta(): array {
    $ip = getClientIp();
    $country = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
    $meta = ['ip' => $ip, 'country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : '', 'vpn' => false];
    $lookup = appAccessLookupIpMeta($ip);
    if ($meta['country'] === '' && !empty($lookup['countryCode'])) {
        $meta['country'] = strtoupper((string)$lookup['countryCode']);
    }
    $meta['vpn'] = !empty($lookup['proxy']) || !empty($lookup['hosting']);
    return $meta;
}
function appAccessRequestBlocked(): bool {
    $cfg = loadAppAccessSecurityConfig();
    $meta = appAccessClientMeta();
    if ($meta['country'] !== '' && in_array($meta['country'], $cfg['blocked_countries'], true)) return true;
    return $cfg['block_vpn'] && $meta['vpn'];
}

// ─────────────────────────────────────────────────────────────────────────────
// USER FOLDER PATHS CONFIG
// ─────────────────────────────────────────────────────────────────────────────
function discoverUserFoldersConfig(): array {
    $appsRoot = __DIR__ . DIRECTORY_SEPARATOR . 'apps';
    $discovered = [];
    if (!is_dir($appsRoot)) {
        return ['apps' => $discovered];
    }

    $preferredRoots = [
        'cloud' => ['storage'],
        'spends' => ['users', 'backups'],
        'steps' => ['users', 'users_local_backup_20260508'],
    ];
    $genericRootNames = ['users', 'storage', 'backups', 'profiles', 'user_data', 'userdata'];

    $appDirs = glob($appsRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
    sort($appDirs, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($appDirs as $appDir) {
        $appKey = basename($appDir);
        $candidates = [];

        foreach (($preferredRoots[$appKey] ?? []) as $rootName) {
            $candidatePath = $appDir . DIRECTORY_SEPARATOR . $rootName;
            $candidates[$rootName] = $candidatePath;
        }

        $childDirs = glob($appDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        foreach ($childDirs as $childDir) {
            $childName = basename($childDir);
            $normalized = strtolower($childName);
            if (in_array($normalized, $genericRootNames, true) || preg_match('/^users(?:[_-].+)?$/i', $childName)) {
                $candidates[$childName] = $childDir;
            }
        }

        if ($appKey === 'steps' && empty($candidates['users'])) {
            $candidates['users'] = $appDir . DIRECTORY_SEPARATOR . 'users';
        }
        if ($appKey === 'cloud' && empty($candidates['storage'])) {
            $candidates['storage'] = $appDir . DIRECTORY_SEPARATOR . 'storage';
        }

        $normalizedCandidates = [];
        foreach ($candidates as $rootName => $candidatePath) {
            $resolved = realpath($candidatePath);
            $normalizedPath = $resolved !== false ? $resolved : $candidatePath;
            $normalizedPath = str_replace('\\', '/', $normalizedPath);
            $normalizedPath = rtrim($normalizedPath, '/');
            if ($normalizedPath === '') {
                continue;
            }
            $normalizedCandidates[$rootName] = $normalizedPath;
        }

        if (isset($normalizedCandidates['users'])) {
            $discovered[$appKey] = $normalizedCandidates['users'];
            unset($normalizedCandidates['users']);
        } elseif (isset($normalizedCandidates['storage'])) {
            $discovered[$appKey] = $normalizedCandidates['storage'];
            unset($normalizedCandidates['storage']);
        }

        foreach ($normalizedCandidates as $rootName => $normalizedPath) {
            $suffix = preg_replace('/[^a-z0-9]+/i', '_', strtolower($rootName));
            $suffix = trim($suffix, '_');
            $entryKey = $suffix === '' ? $appKey : ($appKey . '_' . $suffix);
            $dedupe = $entryKey;
            $n = 2;
            while (isset($discovered[$dedupe])) {
                $dedupe = $entryKey . '_' . $n;
                $n++;
            }
            $discovered[$dedupe] = $normalizedPath;
        }
    }

    ksort($discovered, SORT_NATURAL | SORT_FLAG_CASE);
    return ['apps' => $discovered];
}

function syncUserFoldersConfig(): array {
    $db = getDB();
    $desired = discoverUserFoldersConfig();
    $existing = [];
    $res = $db->query("SELECT app_key, path FROM user_folder_paths");
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $existing[(string)$row['app_key']] = (string)$row['path'];
    }
    ksort($existing, SORT_NATURAL | SORT_FLAG_CASE);
    if ($existing !== $desired['apps']) {
        $db->exec("DELETE FROM user_folder_paths");
        $stmt = $db->prepare("INSERT INTO user_folder_paths(app_key,path) VALUES(:k,:p)");
        foreach ($desired['apps'] as $appKey => $path) {
            $stmt->bindValue(':k', (string)$appKey, PDO::PARAM_STR);
            $stmt->bindValue(':p', (string)$path, PDO::PARAM_STR);
            $stmt->execute();
        }
    }
    return $desired;
}

function loadUserFoldersConfig(): array {
    return syncUserFoldersConfig();
}

function saveUserFoldersConfig(array $config): bool {
    syncUserFoldersConfig();
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// LAST ACTIVE USER  (replaces last_active_user.json)
// ─────────────────────────────────────────────────────────────────────────────
function getLastActiveUser(): ?string {
    $db  = getDB();
    $row = $db->querySingle("SELECT value FROM app_meta WHERE key='last_active_user'");
    return $row ?: null;
}

function setLastActiveUser(string $email): void {
    $db   = getDB();
    monitorRetentionSetMeta($db, 'last_active_user', $email);
}

// =============================================================================
// ONE-TIME PINs
// =============================================================================

/**
 * Generates a unique 6-digit PIN not matching existing ones in DB.
 */
function generateUniqueOTP(): string {
    $db       = getDB();
    $attempts = 0;
    do {
        $pin  = sprintf('%06d', random_int(0, 999999));
        $stmt = $db->prepare("SELECT COUNT(*) FROM one_time_pins WHERE pin=:p");
        $stmt->bindValue(':p', $pin);
        $exists = (int)$stmt->execute()->fetch(PDO::FETCH_NUM)[0] > 0;
        $attempts++;
        if ($attempts > 200) break; // protection against infinite loop
    } while ($exists);
    return $pin;
}

/**
 * Creates a new OTP for a user.
 * First deletes all previous OTPs of this user and expired ones of others.
 *
 * @param string $email   Owner's email
 * @param int    $ttlSec  Time to live in seconds (default 300 = 5 min)
 * @return string Generated 6-digit PIN
 */
function createOTP(string $email, int $ttlSec = 300): string {
    $db = getDB();

    // Delete old OTPs of this user
    $del = $db->prepare("DELETE FROM one_time_pins WHERE email=:e");
    $del->bindValue(':e', $email);
    $del->execute();

    // Background cleanup of expired codes for all users
    $db->exec("DELETE FROM one_time_pins WHERE expires < " . time());

    $pin     = generateUniqueOTP();
    $expires = time() + $ttlSec;

    $stmt = $db->prepare("INSERT INTO one_time_pins (pin, email, expires) VALUES (:p, :e, :ex)");
    $stmt->bindValue(':p',  $pin);
    $stmt->bindValue(':e',  $email);
    $stmt->bindValue(':ex', $expires, PDO::PARAM_INT);
    $stmt->execute();

    return $pin;
}

/**
 * Verifies OTP and deletes it on success (one-time use).
 * Returns owner's email on success or null on error/expiry.
 */
function verifyAndConsumeOTP(string $pin): ?string {
    $db   = getDB();
    $now  = time();
    $stmt = $db->prepare("SELECT email, expires FROM one_time_pins WHERE pin=:p LIMIT 1");
    $stmt->bindValue(':p', $pin);
    $row  = $stmt->execute()->fetch(PDO::FETCH_ASSOC);

    if (!$row) return null;

    if ((int)$row['expires'] < $now) {
        // Expired — delete
        $del = $db->prepare("DELETE FROM one_time_pins WHERE pin=:p");
        $del->bindValue(':p', $pin);
        $del->execute();
        return null;
    }

    // Success — delete (one-time use)
    $del = $db->prepare("DELETE FROM one_time_pins WHERE pin=:p");
    $del->bindValue(':p', $pin);
    $del->execute();

    return $row['email'];
}

// =============================================================================
// IP LOCKOUTS  (brute-force protection)
// =============================================================================

/**
 * Returns lockout record for IP + type.
 * lock_type: 'otp' | 'login'
 */
function getLockout(string $ip, string $lockType): array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM ip_lockouts WHERE ip=:ip AND lock_type=:t LIMIT 1");
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':t',  $lockType);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ?: [
        'ip'           => $ip,
        'lock_type'    => $lockType,
        'attempts'     => 0,
        'locked_until' => null,
        'last_attempt' => 0,
    ];
}

/**
 * Records a failed login attempt.
 *
 * Limits:
 *   otp   — 3 attempts → lockout 180 sec (3 min)
 *   login — 10 attempts → lockout 600 sec (10 min)
 */
function recordFailedAttempt(string $ip, string $lockType): void {
    $db      = getDB();
    $lockout = getLockout($ip, $lockType);
    $now     = time();

    // If old lockout has already expired — reset counter
    if ($lockout['locked_until'] && (int)$lockout['locked_until'] < $now) {
        $lockout['attempts']     = 0;
        $lockout['locked_until'] = null;
    }

    $newAttempts = (int)$lockout['attempts'] + 1;

    if ($lockType === 'otp') {
        $maxAttempts = 3;
        $lockSec     = 180;
    } else {
        $maxAttempts = 10;
        $lockSec     = 600;
    }

    $lockedUntil = ($newAttempts >= $maxAttempts) ? ($now + $lockSec) : null;

    $stmt = $db->prepare(
        "INSERT INTO ip_lockouts (ip, lock_type, attempts, locked_until, last_attempt)
         VALUES (:ip, :t, :a, :lu, :la)
         ON CONFLICT(ip, lock_type) DO UPDATE SET
             attempts     = excluded.attempts,
             locked_until = excluded.locked_until,
             last_attempt = excluded.last_attempt"
    );
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':t',  $lockType);
    $stmt->bindValue(':a',  $newAttempts, PDO::PARAM_INT);
    $stmt->bindValue(':lu', $lockedUntil, $lockedUntil !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':la', $now, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * Resets failed attempts counter after successful login.
 */
function resetFailedAttempts(string $ip, string $lockType): void {
    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM ip_lockouts WHERE ip=:ip AND lock_type=:t");
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':t',  $lockType);
    $stmt->execute();
}

/**
 * Returns seconds remaining until lockout expires, or 0 if not locked out.
 */
function getIpLockoutSeconds(string $ip, string $lockType): int {
    $lockout = getLockout($ip, $lockType);
    if (!$lockout['locked_until']) return 0;
    $remaining = (int)$lockout['locked_until'] - time();
    return max(0, $remaining);
}

// ── Trusted Devices ─────────────────────────────────────────────────────────

/**
 * Returns the current client IP (respects X-Forwarded-For).
 */
function getClientIp(): string {
    $ip = monitorClientIp();
    return $ip !== '' ? $ip : '0.0.0.0';
}
function normalizeTrustedDeviceId(string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    if (!preg_match('/^[a-f0-9]{32,128}$/i', $value)) return '';
    return strtolower($value);
}
function getConsentDeviceId(): string {
    $headerDeviceId = normalizeTrustedDeviceId((string)($_SERVER['HTTP_X_DEVICE_ID'] ?? ''));
    if ($headerDeviceId !== '') return $headerDeviceId;
    $cookieDeviceId = normalizeTrustedDeviceId((string)($_COOKIE['ap_device_id'] ?? ''));
    if ($cookieDeviceId !== '') return $cookieDeviceId;
    return '';
}
function getDeviceFingerprint(): string {
    $cookieDeviceId = normalizeTrustedDeviceId((string)($_COOKIE['ap_device_id'] ?? ''));
    if ($cookieDeviceId !== '') {
        return hash('sha256', 'device:' . $cookieDeviceId);
    }
    $headerDeviceId = normalizeTrustedDeviceId((string)($_SERVER['HTTP_X_DEVICE_ID'] ?? ''));
    if ($headerDeviceId !== '') {
        return hash('sha256', 'device:' . $headerDeviceId);
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    return hash('sha256', 'legacy:' . $ua . '|' . $lang);
}

/**
 * Returns the user's trusted devices from the dedicated table.
 */
function getUserTrustedDevices(string $email): array {
    if ($redis = app_redis()) {
        $devices = [];
        $setKey = app_redis_key('trusted_devices_by_email:' . strtolower($email));
        foreach ($redis->sMembers($setKey) ?: [] as $fingerprint) {
            $raw = $redis->get(app_redis_key('trusted_device:' . strtolower($email) . ':' . (string)$fingerprint));
            if (!$raw) {
                $redis->sRem($setKey, $fingerprint);
                continue;
            }
            $row = json_decode((string)$raw, true);
            if (is_array($row)) $devices[] = $row;
        }
        usort($devices, fn($a, $b) => (int)($b['last_seen'] ?? 0) <=> (int)($a['last_seen'] ?? 0));
        return $devices;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT fingerprint, ip, device_name, device_token_hash, added_at, last_seen
        FROM trusted_devices
        WHERE email = :e
        ORDER BY last_seen DESC, id DESC");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $result = $stmt->execute();
    $devices = [];
    while ($result && ($row = $result->fetch(PDO::FETCH_ASSOC))) {
        $devices[] = [
            'fingerprint' => (string)($row['fingerprint'] ?? ''),
            'ip' => (string)($row['ip'] ?? ''),
            'device_name' => (string)($row['device_name'] ?? ''),
            'added_at' => (int)($row['added_at'] ?? 0),
            'last_seen' => (int)($row['last_seen'] ?? 0),
            'device_token' => '',
        ];
    }
    return $devices;
}

/**
 * Legacy compatibility helper. Rebuilds the dedicated table from the provided list.
 */
function setUserTrustedDevices(string $email, array $devices): void {
    if ($redis = app_redis()) {
        $setKey = app_redis_key('trusted_devices_by_email:' . strtolower($email));
        foreach ($redis->sMembers($setKey) ?: [] as $fingerprint) {
            $redis->del(app_redis_key('trusted_device:' . strtolower($email) . ':' . (string)$fingerprint));
        }
        $redis->del($setKey);
        foreach ($devices as $device) {
            if (!is_array($device)) continue;
            $fingerprint = trim((string)($device['fingerprint'] ?? ''));
            if ($fingerprint === '') continue;
            $device['email'] = $email;
            $redis->set(app_redis_key('trusted_device:' . strtolower($email) . ':' . $fingerprint), json_encode($device, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $redis->sAdd($setKey, $fingerprint);
        }
        return;
    }
    $db = getDB();
    $delete = $db->prepare("DELETE FROM trusted_devices WHERE email = :e");
    $delete->bindValue(':e', $email, PDO::PARAM_STR);
    $delete->execute();

    $insert = $db->prepare("INSERT INTO trusted_devices(email, fingerprint, ip, device_name, device_token_hash, added_at, last_seen)
        VALUES(:email, :fingerprint, :ip, :device_name, :device_token_hash, :added_at, :last_seen)");
    foreach ($devices as $device) {
        if (!is_array($device)) continue;
        $fingerprint = trim((string)($device['fingerprint'] ?? ''));
        if ($fingerprint === '') continue;
        $deviceToken = trim((string)($device['device_token'] ?? ''));
        $insert->bindValue(':email', $email, PDO::PARAM_STR);
        $insert->bindValue(':fingerprint', $fingerprint, PDO::PARAM_STR);
        $insert->bindValue(':ip', (string)($device['ip'] ?? ''), PDO::PARAM_STR);
        $insert->bindValue(':device_name', (string)($device['device_name'] ?? ''), PDO::PARAM_STR);
        if ($deviceToken !== '') {
            $insert->bindValue(':device_token_hash', hash('sha256', $deviceToken), PDO::PARAM_STR);
        } else {
            $insert->bindValue(':device_token_hash', null, PDO::PARAM_NULL);
        }
        $insert->bindValue(':added_at', (int)($device['added_at'] ?? time()), PDO::PARAM_INT);
        $insert->bindValue(':last_seen', (int)($device['last_seen'] ?? time()), PDO::PARAM_INT);
        $insert->execute();
    }
}

function isTrustedDevice(string $email, string $fingerprint): bool {
    if ($fingerprint === '') return false;
    if ($redis = app_redis()) {
        return (bool)$redis->exists(app_redis_key('trusted_device:' . strtolower($email) . ':' . $fingerprint));
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT 1 FROM trusted_devices WHERE email = :e AND fingerprint = :f LIMIT 1");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':f', $fingerprint, PDO::PARAM_STR);
    return (bool)$stmt->execute()->fetch(PDO::FETCH_NUM);
}

function trustedDeviceExistsForHint(string $email, string $fingerprint): bool {
    return $email !== '' && $fingerprint !== '' && isTrustedDevice($email, $fingerprint);
}

/**
 * Encrypts data using AES-256-CBC with a derived key.
 */
function encryptTrustedDeviceToken(string $data): string {
    $key = defined('TRUSTED_DEVICE_ENCRYPTION_KEY') ? TRUSTED_DEVICE_ENCRYPTION_KEY : (getenv('TRUSTED_DEVICE_ENCRYPTION_KEY') ?: 'default-dev-key-change-in-production');
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', hash('sha256', $key, true), 0, $iv);
    if ($encrypted === false) {
        throw new RuntimeException('Encryption failed');
    }
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypts data using AES-256-CBC with a derived key.
 */
function decryptTrustedDeviceToken(string $encryptedData): ?string {
    $key = defined('TRUSTED_DEVICE_ENCRYPTION_KEY') ? TRUSTED_DEVICE_ENCRYPTION_KEY : (getenv('TRUSTED_DEVICE_ENCRYPTION_KEY') ?: 'default-dev-key-change-in-production');
    $raw = base64_decode($encryptedData, true);
    if ($raw === false) return null;
    if (strlen($raw) < 16) return null;
    $iv = substr($raw, 0, 16);
    $encrypted = substr($raw, 16);
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', hash('sha256', $key, true), 0, $iv);
    return $decrypted !== false ? $decrypted : null;
}

function addTrustedDevice(string $email, string $ip, string $deviceName = '', string $fingerprint = ''): array {
    $deviceToken = bin2hex(random_bytes(32));
    $encryptedToken = encryptTrustedDeviceToken($deviceToken);
    if ($redis = app_redis()) {
        $now = time();
        if ($fingerprint === '') $fingerprint = hash('sha256', $email . '|' . $ip . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $now);
        $row = [
            'email' => $email,
            'fingerprint' => $fingerprint,
            'ip' => $ip,
            'device_name' => $deviceName,
            'device_token_hash' => hash('sha256', $encryptedToken),
            'device_token' => $encryptedToken,
            'added_at' => $now,
            'last_seen' => $now,
        ];
        $redis->set(app_redis_key('trusted_device:' . strtolower($email) . ':' . $fingerprint), json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $redis->sAdd(app_redis_key('trusted_devices_by_email:' . strtolower($email)), $fingerprint);
        return ['token' => $deviceToken, 'encrypted_token' => $encryptedToken];
    }
    $db = getDB();
    ensureTrustedDevicesSchema($db);
    $now = time();
    $stmt = $db->prepare("INSERT INTO trusted_devices(email, fingerprint, ip, device_name, device_token_hash, added_at, last_seen)
        VALUES(:email, :fingerprint, :ip, :device_name, :device_token_hash, :added_at, :last_seen)
        ON CONFLICT(email, fingerprint) DO UPDATE SET
            ip = excluded.ip,
            device_name = CASE WHEN excluded.device_name != '' THEN excluded.device_name ELSE trusted_devices.device_name END,
            device_token_hash = excluded.device_token_hash,
            last_seen = excluded.last_seen");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':fingerprint', $fingerprint, PDO::PARAM_STR);
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindValue(':device_name', $deviceName, PDO::PARAM_STR);
    $stmt->bindValue(':device_token_hash', hash('sha256', $encryptedToken), PDO::PARAM_STR);
    $stmt->bindValue(':added_at', $now, PDO::PARAM_INT);
    $stmt->bindValue(':last_seen', $now, PDO::PARAM_INT);
    $stmt->execute();
    return ['token' => $deviceToken, 'encrypted_token' => $encryptedToken];
}

function removeTrustedDevice(string $email, string $fingerprint): void {
    if ($redis = app_redis()) {
        $redis->del(app_redis_key('trusted_device:' . strtolower($email) . ':' . $fingerprint));
        $redis->sRem(app_redis_key('trusted_devices_by_email:' . strtolower($email)), $fingerprint);
        removeTrustedDeviceCookies($email, $fingerprint);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM trusted_devices WHERE email = :e AND fingerprint = :f");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':f', $fingerprint, PDO::PARAM_STR);
    $stmt->execute();
    removeTrustedDeviceCookies($email, $fingerprint);
}

/**
 * Clears all trusted devices for a user.
 */
function clearTrustedDevices(string $email): void {
    if ($redis = app_redis()) {
        $setKey = app_redis_key('trusted_devices_by_email:' . strtolower($email));
        foreach ($redis->sMembers($setKey) ?: [] as $fingerprint) {
            $redis->del(app_redis_key('trusted_device:' . strtolower($email) . ':' . (string)$fingerprint));
            removeTrustedDeviceCookies($email, (string)$fingerprint);
        }
        $redis->del($setKey);
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM trusted_devices WHERE email = :e");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->execute();
}

// ── Trusted Devices Schema Helpers ──────────────────────────────────────────

function ensureTrustedDevicesSchema(AppDatabase $db): void {
    if (!app_db_is_pgsql()) return;
    try {
        $db->exec("DELETE FROM trusted_devices WHERE id NOT IN (
            SELECT MAX(id) FROM trusted_devices GROUP BY email, fingerprint
        )");
    } catch (Throwable $e) {}
    try {
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_trusted_devices_email_fp ON trusted_devices(email, fingerprint)");
    } catch (Throwable $e) {}
}

// ── Trusted Device Persistent Cookies ───────────────────────────────────────

function ensureTrustedDeviceCookiesSchema(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS trusted_device_cookies (
        cookie_hash  TEXT    PRIMARY KEY,
        email        TEXT    NOT NULL,
        ip           TEXT    NOT NULL DEFAULT '',
        fingerprint  TEXT    NOT NULL DEFAULT '',
        device_name  TEXT    NOT NULL DEFAULT '',
        created_at   INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        last_used_at INTEGER NOT NULL DEFAULT 0,
        expires_at   INTEGER NOT NULL
    )");
    try { $db->exec("ALTER TABLE trusted_device_cookies ADD COLUMN fingerprint TEXT NOT NULL DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE trusted_device_cookies ADD COLUMN last_used_at INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try {
        if (app_db_is_pgsql()) {
            $db->exec("DELETE FROM trusted_device_cookies t
                USING (
                    SELECT email, fingerprint, MAX(created_at) AS max_created
                    FROM trusted_device_cookies
                    GROUP BY email, fingerprint
                ) keep
                WHERE t.email = keep.email AND t.fingerprint = keep.fingerprint AND t.created_at < keep.max_created");
        } else {
            $db->exec("DELETE FROM trusted_device_cookies
                WHERE rowid NOT IN (
                    SELECT MAX(rowid)
                    FROM trusted_device_cookies
                    GROUP BY email, fingerprint
                )");
        }
    } catch (Exception $e) {}
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_trusted_device_cookies_email_fp ON trusted_device_cookies(email, fingerprint)");
}
function createTrustedDeviceCookie(string $email, string $fingerprint, string $deviceName = ''): string {
    if ($redis = app_redis()) {
        $rawCookie = bin2hex(random_bytes(32));
        $cookieHash = hash('sha256', $rawCookie);
        $now = time();
        $expiresAt = $now + (365 * 24 * 3600);
        $row = ['cookie_hash' => $cookieHash, 'email' => $email, 'ip' => getClientIp(), 'fingerprint' => $fingerprint, 'device_name' => $deviceName, 'created_at' => $now, 'last_used_at' => $now, 'expires_at' => $expiresAt];
        $oldHash = (string)$redis->get(app_redis_key('trusted_cookie_by_email_fp:' . strtolower($email) . ':' . $fingerprint));
        if ($oldHash !== '') $redis->del(app_redis_key('trusted_cookie:' . $oldHash));
        $redis->setex(app_redis_key('trusted_cookie:' . $cookieHash), 365 * 24 * 3600, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $redis->setex(app_redis_key('trusted_cookie_by_email_fp:' . strtolower($email) . ':' . $fingerprint), 365 * 24 * 3600, $cookieHash);
        return $rawCookie;
    }
    $db = getDB();
    ensureTrustedDeviceCookiesSchema($db);
    $rawCookie = bin2hex(random_bytes(32));
    $cookieHash = hash('sha256', $rawCookie);
    $now = time();
    $expiresAt = $now + (365 * 24 * 3600);
    $ip = getClientIp();
    $stmt = $db->prepare("INSERT INTO trusted_device_cookies(cookie_hash,email,ip,fingerprint,device_name,created_at,last_used_at,expires_at)
        VALUES(:h,:e,:i,:f,:n,:c,:u,:x)
        ON CONFLICT(email, fingerprint) DO UPDATE SET
            cookie_hash = excluded.cookie_hash,
            ip = excluded.ip,
            device_name = CASE WHEN excluded.device_name != '' THEN excluded.device_name ELSE trusted_device_cookies.device_name END,
            last_used_at = excluded.last_used_at,
            expires_at = excluded.expires_at");
    $stmt->bindValue(':h', $cookieHash, PDO::PARAM_STR);
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':i', $ip, PDO::PARAM_STR);
    $stmt->bindValue(':f', $fingerprint, PDO::PARAM_STR);
    $stmt->bindValue(':n', $deviceName, PDO::PARAM_STR);
    $stmt->bindValue(':c', $now, PDO::PARAM_INT);
    $stmt->bindValue(':u', $now, PDO::PARAM_INT);
    $stmt->bindValue(':x', $expiresAt, PDO::PARAM_INT);
    $stmt->execute();
    return $rawCookie;
}

function verifyAndRotateTrustedDeviceCookie(string $rawCookie): ?array {
    if ($redis = app_redis()) {
        $cookieHash = hash('sha256', $rawCookie);
        $graceRaw = $redis->get(app_redis_key('trusted_cookie_grace:' . $cookieHash));
        if (is_string($graceRaw) && $graceRaw !== '') {
            $grace = json_decode($graceRaw, true);
            if (is_array($grace) && (string)($grace['email'] ?? '') !== '' && (string)($grace['new_token'] ?? '') !== '') {
                return ['email' => (string)$grace['email'], 'new_token' => (string)$grace['new_token']];
            }
        }
        $raw = $redis->get(app_redis_key('trusted_cookie:' . $cookieHash));
        if (!$raw) return null;
        $row = json_decode((string)$raw, true);
        if (!is_array($row) || (int)($row['expires_at'] ?? 0) < time()) {
            $redis->del(app_redis_key('trusted_cookie:' . $cookieHash));
            return null;
        }
        $email = (string)($row['email'] ?? '');
        $fingerprint = (string)($row['fingerprint'] ?? '');
        $currentFingerprint = getDeviceFingerprint();
        if ($currentFingerprint !== '' && $currentFingerprint !== $fingerprint) {
            $deviceRaw = $redis->get(app_redis_key('trusted_device:' . strtolower($email) . ':' . $fingerprint));
            if ($deviceRaw) {
                $device = json_decode((string)$deviceRaw, true);
                if (is_array($device)) {
                    $device['fingerprint'] = $currentFingerprint;
                    $device['last_seen'] = time();
                    $redis->set(app_redis_key('trusted_device:' . strtolower($email) . ':' . $currentFingerprint), json_encode($device, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $redis->sAdd(app_redis_key('trusted_devices_by_email:' . strtolower($email)), $currentFingerprint);
                    $redis->del(app_redis_key('trusted_device:' . strtolower($email) . ':' . $fingerprint));
                    $redis->sRem(app_redis_key('trusted_devices_by_email:' . strtolower($email)), $fingerprint);
                }
            }
            $fingerprint = $currentFingerprint;
        }
        if ($email === '' || $fingerprint === '' || !isTrustedDevice($email, $fingerprint)) {
            $redis->del(app_redis_key('trusted_cookie:' . $cookieHash));
            return null;
        }
        $newRawCookie = bin2hex(random_bytes(32));
        $newHash = hash('sha256', $newRawCookie);
        $now = time();
        $row['cookie_hash'] = $newHash;
        $row['fingerprint'] = $fingerprint;
        $row['last_used_at'] = $now;
        $row['expires_at'] = $now + (365 * 24 * 3600);
        $redis->expire(app_redis_key('trusted_cookie:' . $cookieHash), 120);
        $redis->setex(app_redis_key('trusted_cookie_grace:' . $cookieHash), 120, json_encode(['email' => $email, 'new_token' => $newRawCookie], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $redis->setex(app_redis_key('trusted_cookie:' . $newHash), 365 * 24 * 3600, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $redis->setex(app_redis_key('trusted_cookie_by_email_fp:' . strtolower($email) . ':' . $fingerprint), 365 * 24 * 3600, $newHash);
        $deviceRaw = $redis->get(app_redis_key('trusted_device:' . strtolower($email) . ':' . $fingerprint));
        $device = $deviceRaw ? json_decode((string)$deviceRaw, true) : null;
        if (is_array($device)) {
            $device['last_seen'] = $now;
            $device['ip'] = getClientIp();
            $redis->set(app_redis_key('trusted_device:' . strtolower($email) . ':' . $fingerprint), json_encode($device, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return ['email' => $email, 'new_token' => $newRawCookie];
    }
    $db = getDB();
    ensureTrustedDeviceCookiesSchema($db);
    $cookieHash = hash('sha256', $rawCookie);
    $now = time();
    $db->exec("DELETE FROM trusted_device_cookies WHERE expires_at < $now");
    $stmt = $db->prepare("SELECT * FROM trusted_device_cookies WHERE cookie_hash = :h AND expires_at > :t LIMIT 1");
    $stmt->bindValue(':h', $cookieHash, PDO::PARAM_STR);
    $stmt->bindValue(':t', $now, PDO::PARAM_INT);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $email = (string)($row['email'] ?? '');
    $fingerprint = (string)($row['fingerprint'] ?? '');
    $currentFingerprint = getDeviceFingerprint();
    if ($currentFingerprint !== '' && $currentFingerprint !== $fingerprint) {
        $migrateTrusted = $db->prepare("INSERT INTO trusted_devices(email, fingerprint, ip, device_name, device_token_hash, added_at, last_seen)
            SELECT email, :new_fingerprint, ip, device_name, device_token_hash, added_at, :now
            FROM trusted_devices
            WHERE email = :email AND fingerprint = :old_fingerprint
            ON CONFLICT(email, fingerprint) DO UPDATE SET
                ip = excluded.ip,
                device_name = CASE WHEN excluded.device_name != '' THEN excluded.device_name ELSE trusted_devices.device_name END,
                last_seen = excluded.last_seen");
        $migrateTrusted->bindValue(':new_fingerprint', $currentFingerprint, PDO::PARAM_STR);
        $migrateTrusted->bindValue(':now', $now, PDO::PARAM_INT);
        $migrateTrusted->bindValue(':email', $email, PDO::PARAM_STR);
        $migrateTrusted->bindValue(':old_fingerprint', $fingerprint, PDO::PARAM_STR);
        $migrateTrusted->execute();
        $deleteOldTrusted = $db->prepare("DELETE FROM trusted_devices WHERE email = :email AND fingerprint = :old_fingerprint");
        $deleteOldTrusted->bindValue(':email', $email, PDO::PARAM_STR);
        $deleteOldTrusted->bindValue(':old_fingerprint', $fingerprint, PDO::PARAM_STR);
        $deleteOldTrusted->execute();
        $fingerprint = $currentFingerprint;
    }
    if ($email === '' || $fingerprint === '' || !isTrustedDevice($email, $fingerprint)) {
        $del = $db->prepare("DELETE FROM trusted_device_cookies WHERE cookie_hash = :h");
        $del->bindValue(':h', $cookieHash, PDO::PARAM_STR);
        $del->execute();
        return null;
    }
    $newRawCookie = bin2hex(random_bytes(32));
    $newHash = hash('sha256', $newRawCookie);
    $newExpires = $now + (365 * 24 * 3600);
    $upd = $db->prepare("UPDATE trusted_device_cookies SET cookie_hash = :nh, fingerprint = :f, last_used_at = :u, expires_at = :x WHERE cookie_hash = :oh");
    $upd->bindValue(':nh', $newHash, PDO::PARAM_STR);
    $upd->bindValue(':f', $fingerprint, PDO::PARAM_STR);
    $upd->bindValue(':u', $now, PDO::PARAM_INT);
    $upd->bindValue(':x', $newExpires, PDO::PARAM_INT);
    $upd->bindValue(':oh', $cookieHash, PDO::PARAM_STR);
    $upd->execute();
    $touch = $db->prepare("UPDATE trusted_devices SET last_seen = :t, ip = :ip WHERE email = :e AND fingerprint = :f");
    $touch->bindValue(':t', $now, PDO::PARAM_INT);
    $touch->bindValue(':ip', getClientIp(), PDO::PARAM_STR);
    $touch->bindValue(':e', $email, PDO::PARAM_STR);
    $touch->bindValue(':f', $fingerprint, PDO::PARAM_STR);
    $touch->execute();
    return ['email' => $email, 'new_token' => $newRawCookie];
}

function removeTrustedDeviceCookies(string $email, string $fingerprint): void {
    if ($redis = app_redis()) {
        $mapKey = app_redis_key('trusted_cookie_by_email_fp:' . strtolower($email) . ':' . $fingerprint);
        $cookieHash = (string)$redis->get($mapKey);
        if ($cookieHash !== '') $redis->del(app_redis_key('trusted_cookie:' . $cookieHash));
        $redis->del($mapKey);
        return;
    }
    $db = getDB();
    ensureTrustedDeviceCookiesSchema($db);
    $stmt = $db->prepare("DELETE FROM trusted_device_cookies WHERE email = :e AND fingerprint = :f");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':f', $fingerprint, PDO::PARAM_STR);
    $stmt->execute();
}

function trustedDeviceCookieIsSecure(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}

function trustedDeviceCookieName(string $email): string {
    $normalized = strtolower(trim($email));
    return 'trusted_device_' . substr(hash('sha256', $normalized), 0, 16);
}

function getTrustedDeviceCookieForEmail(string $email): string {
    if ($email === '') return '';
    return (string)($_COOKIE[trustedDeviceCookieName($email)] ?? '');
}

function setTrustedDeviceCookieForEmail(string $email, string $rawToken): void {
    if ($email === '' || $rawToken === '') return;
    $cookieName = trustedDeviceCookieName($email);
    $expires = time() + (365 * 24 * 3600);
    setcookie($cookieName, $rawToken, [
        'expires' => $expires,
        'path' => '/',
        'secure' => trustedDeviceCookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    $_COOKIE[$cookieName] = $rawToken;
}

function clearTrustedDeviceCookieForEmail(string $email): void {
    if ($email === '') return;
    $cookieName = trustedDeviceCookieName($email);
    setcookie($cookieName, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => trustedDeviceCookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    unset($_COOKIE[$cookieName]);
}

function buildTrustedDeviceManifestCookie(array $emails, string $activeEmail = ''): string {
    $normalizedEmails = [];
    foreach ($emails as $email) {
        $email = trim((string)$email);
        if ($email === '' || isset($normalizedEmails[$email])) continue;
        $normalizedEmails[$email] = true;
    }
    $emailList = array_keys($normalizedEmails);
    if ($activeEmail === '' || !in_array($activeEmail, $emailList, true)) {
        $activeEmail = $emailList[0] ?? '';
    }
    $payload = [
        'emails' => $emailList,
        'active_email' => $activeEmail,
        'exp' => time() + (365 * 24 * 3600),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $sig = hash_hmac('sha256', $json ?: '', trustedDeviceHintSecret());
    return rtrim(strtr(base64_encode(($json ?: '{}') . '.' . $sig), '+/', '-_'), '=');
}

function parseTrustedDeviceManifestCookie(string $cookie): array {
    if ($cookie === '') return ['emails' => [], 'active_email' => ''];
    $normalized = strtr($cookie, '-_', '+/');
    $padLen = strlen($normalized) % 4;
    if ($padLen > 0) {
        $normalized .= str_repeat('=', 4 - $padLen);
    }
    $raw = base64_decode($normalized, true);
    if ($raw === false || !str_contains($raw, '.')) return ['emails' => [], 'active_email' => ''];
    [$json, $sig] = explode('.', $raw, 2);
    $expected = hash_hmac('sha256', $json, trustedDeviceHintSecret());
    if (!hash_equals($expected, $sig)) return ['emails' => [], 'active_email' => ''];
    $payload = json_decode($json, true);
    if (!is_array($payload)) return ['emails' => [], 'active_email' => ''];
    $exp = (int)($payload['exp'] ?? 0);
    if ($exp < time()) return ['emails' => [], 'active_email' => ''];
    $emails = [];
    foreach ((array)($payload['emails'] ?? []) as $email) {
        $email = trim((string)$email);
        if ($email === '' || in_array($email, $emails, true)) continue;
        $emails[] = $email;
    }
    $activeEmail = trim((string)($payload['active_email'] ?? ''));
    if ($activeEmail !== '' && !in_array($activeEmail, $emails, true)) {
        $activeEmail = '';
    }
    return ['emails' => $emails, 'active_email' => $activeEmail];
}

function setTrustedDeviceManifestCookie(array $emails, string $activeEmail = ''): void {
    $emails = array_values(array_filter(array_map(static fn($email) => trim((string)$email), $emails), static fn($email) => $email !== ''));
    if (empty($emails)) {
        clearTrustedDeviceManifestCookie();
        return;
    }
    $cookieValue = buildTrustedDeviceManifestCookie($emails, $activeEmail);
    setcookie('trusted_device_manifest', $cookieValue, [
        'expires' => time() + (365 * 24 * 3600),
        'path' => '/',
        'secure' => trustedDeviceCookieIsSecure(),
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    $_COOKIE['trusted_device_manifest'] = $cookieValue;
}

function clearTrustedDeviceManifestCookie(): void {
    setcookie('trusted_device_manifest', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => trustedDeviceCookieIsSecure(),
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    unset($_COOKIE['trusted_device_manifest']);
}

function buildTrustedDeviceLogoutStateCookie(): string {
    $payload = [
        'suppress' => true,
        'iat' => time(),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $sig = hash_hmac('sha256', $json ?: '', trustedDeviceHintSecret());
    return rtrim(strtr(base64_encode(($json ?: '{}') . '.' . $sig), '+/', '-_'), '=');
}

function hasTrustedDeviceLogoutSuppression(): bool {
    $cookie = (string)($_COOKIE['trusted_device_logout_state'] ?? '');
    if ($cookie === '') return false;
    $normalized = strtr($cookie, '-_', '+/');
    $padLen = strlen($normalized) % 4;
    if ($padLen > 0) {
        $normalized .= str_repeat('=', 4 - $padLen);
    }
    $raw = base64_decode($normalized, true);
    if ($raw === false || !str_contains($raw, '.')) return false;
    [$json, $sig] = explode('.', $raw, 2);
    $expected = hash_hmac('sha256', $json, trustedDeviceHintSecret());
    if (!hash_equals($expected, $sig)) return false;
    $payload = json_decode($json, true);
    return is_array($payload) && !empty($payload['suppress']);
}

function setTrustedDeviceLogoutSuppression(): void {
    $cookieValue = buildTrustedDeviceLogoutStateCookie();
    setcookie('trusted_device_logout_state', $cookieValue, [
        'path' => '/',
        'secure' => trustedDeviceCookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    $_COOKIE['trusted_device_logout_state'] = $cookieValue;
}

function clearTrustedDeviceLogoutSuppression(): void {
    setcookie('trusted_device_logout_state', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => trustedDeviceCookieIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    unset($_COOKIE['trusted_device_logout_state']);
}

function rememberTrustedDeviceBrowserAccount(string $email): void {
    if ($email === '') return;
    $manifest = parseTrustedDeviceManifestCookie((string)($_COOKIE['trusted_device_manifest'] ?? ''));
    $emails = array_values(array_filter($manifest['emails'] ?? [], static fn($existingEmail) => $existingEmail !== $email));
    array_unshift($emails, $email);
    setTrustedDeviceManifestCookie($emails, $email);
}

function forgetTrustedDeviceBrowserAccount(string $email): void {
    if ($email === '') return;
    $manifest = parseTrustedDeviceManifestCookie((string)($_COOKIE['trusted_device_manifest'] ?? ''));
    $emails = array_values(array_filter($manifest['emails'] ?? [], static fn($existingEmail) => $existingEmail !== $email));
    setTrustedDeviceManifestCookie($emails, $manifest['active_email'] ?? '');
}

function getTrustedDeviceCookieCandidates(string $preferredEmail = ''): array {
    $manifest = parseTrustedDeviceManifestCookie((string)($_COOKIE['trusted_device_manifest'] ?? ''));
    $orderedEmails = [];
    $pushEmail = static function (string $email) use (&$orderedEmails): void {
        $email = trim($email);
        if ($email === '' || in_array($email, $orderedEmails, true)) return;
        $orderedEmails[] = $email;
    };
    $pushEmail($preferredEmail);
    $pushEmail((string)($manifest['active_email'] ?? ''));
    foreach ((array)($manifest['emails'] ?? []) as $email) {
        $pushEmail((string)$email);
    }

    $candidates = [];
    $seenCookieNames = [];
    foreach ($orderedEmails as $email) {
        if ($email === '') continue;
        $cookieName = trustedDeviceCookieName($email);
        $token = (string)($_COOKIE[$cookieName] ?? '');
        if ($token === '' || isset($seenCookieNames[$cookieName])) continue;
        $seenCookieNames[$cookieName] = true;
        $candidates[] = ['email' => $email, 'cookie_name' => $cookieName, 'token' => $token, 'legacy' => false];
    }
    foreach ($_COOKIE as $cookieName => $token) {
        if (strncmp((string)$cookieName, 'trusted_device_', 15) !== 0) continue;
        if (in_array((string)$cookieName, ['trusted_device_hint', 'trusted_device_manifest'], true)) continue;
        if (isset($seenCookieNames[$cookieName])) continue;
        $seenCookieNames[$cookieName] = true;
        $candidates[] = ['email' => '', 'cookie_name' => (string)$cookieName, 'token' => (string)$token, 'legacy' => false];
    }
    $legacyToken = (string)($_COOKIE['trusted_device'] ?? '');
    if ($legacyToken !== '') {
        $candidates[] = ['email' => '', 'cookie_name' => 'trusted_device', 'token' => $legacyToken, 'legacy' => true];
    }
    return $candidates;
}

/**
 * Sets the trusted device browser cookie.
 */
function setTrustedDeviceCookie(string $rawToken): void {
    $expires = time() + (365 * 24 * 3600);
    $secure = trustedDeviceCookieIsSecure();
    setcookie('trusted_device', $rawToken, [
        'expires' => $expires,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    $_COOKIE['trusted_device'] = $rawToken;
}

/**
 * Clears the trusted device browser cookie.
 */
function clearTrustedDeviceCookie(): void {
    $secure = trustedDeviceCookieIsSecure();
    setcookie('trusted_device', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    unset($_COOKIE['trusted_device']);
}

function trustedDeviceHintSecret(): string {
    $env = trim((string)(getenv('TRUSTED_DEVICE_HINT_SECRET') ?: ''));
    if ($env !== '') return $env;
    return hash('sha256', (string)(getenv('TRUSTED_DEVICE_ENCRYPTION_KEY') ?: 'default-dev-key-change-in-production'));
}

function buildTrustedDeviceHintCookie(string $email, string $fingerprint): string {
    $payload = [
        'email' => $email,
        'fingerprint' => $fingerprint,
        'exp' => time() + (365 * 24 * 3600),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $sig = hash_hmac('sha256', $json ?: '', trustedDeviceHintSecret());
    return rtrim(strtr(base64_encode(($json ?: '{}') . '.' . $sig), '+/', '-_'), '=');
}

function parseTrustedDeviceHintCookie(string $cookie): ?array {
    $normalized = strtr($cookie, '-_', '+/');
    $padLen = strlen($normalized) % 4;
    if ($padLen > 0) {
        $normalized .= str_repeat('=', 4 - $padLen);
    }
    $raw = base64_decode($normalized, true);
    if ($raw === false || !str_contains($raw, '.')) return null;
    [$json, $sig] = explode('.', $raw, 2);
    $expected = hash_hmac('sha256', $json, trustedDeviceHintSecret());
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode($json, true);
    if (!is_array($payload)) return null;
    $email = trim((string)($payload['email'] ?? ''));
    $fingerprint = trim((string)($payload['fingerprint'] ?? ''));
    $exp = (int)($payload['exp'] ?? 0);
    if ($email === '' || $fingerprint === '' || $exp < time()) return null;
    return ['email' => $email, 'fingerprint' => $fingerprint];
}

function setTrustedDeviceHintCookie(string $email, string $fingerprint): void {
    $secure = trustedDeviceCookieIsSecure();
    $cookieValue = buildTrustedDeviceHintCookie($email, $fingerprint);
    setcookie('trusted_device_hint', $cookieValue, [
        'expires' => time() + (365 * 24 * 3600),
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    $_COOKIE['trusted_device_hint'] = $cookieValue;
}

function clearTrustedDeviceHintCookie(): void {
    $secure = trustedDeviceCookieIsSecure();
    setcookie('trusted_device_hint', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    unset($_COOKIE['trusted_device_hint']);
}

function getCookieConsentRecord(string $deviceId, string $consentKey = 'site_cookies'): ?array {
    if ($deviceId === '') return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM site_cookie_consents WHERE consent_key = :k AND device_id = :d LIMIT 1");
    $stmt->bindValue(':k', $consentKey, PDO::PARAM_STR);
    $stmt->bindValue(':d', $deviceId, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function markCookieConsentPrompted(string $deviceId, string $email = '', string $consentKey = 'site_cookies'): void {
    if ($deviceId === '') return;
    $db = getDB();
    $now = time();
    $stmt = $db->prepare("INSERT INTO site_cookie_consents(consent_key, device_id, email, status, asked_at, created_at, updated_at)
        VALUES(:k, :d, :e, 'pending', :t, :t, :t)
        ON CONFLICT(consent_key, device_id) DO UPDATE SET
            email = CASE WHEN excluded.email != '' THEN excluded.email ELSE site_cookie_consents.email END,
            asked_at = COALESCE(site_cookie_consents.asked_at, excluded.asked_at),
            updated_at = excluded.updated_at");
    $stmt->bindValue(':k', $consentKey, PDO::PARAM_STR);
    $stmt->bindValue(':d', $deviceId, PDO::PARAM_STR);
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':t', $now, PDO::PARAM_INT);
    $stmt->execute();
}

function saveCookieConsentDecision(string $deviceId, string $status, string $email = '', string $consentKey = 'site_cookies'): void {
    if ($deviceId === '') return;
    if (!in_array($status, ['accepted', 'declined'], true)) {
        throw new InvalidArgumentException('Invalid cookie consent status');
    }
    $db = getDB();
    $now = time();
    $stmt = $db->prepare("INSERT INTO site_cookie_consents(consent_key, device_id, email, status, asked_at, responded_at, created_at, updated_at)
        VALUES(:k, :d, :e, :s, :t, :t, :t, :t)
        ON CONFLICT(consent_key, device_id) DO UPDATE SET
            email = CASE WHEN excluded.email != '' THEN excluded.email ELSE site_cookie_consents.email END,
            status = excluded.status,
            asked_at = COALESCE(site_cookie_consents.asked_at, excluded.asked_at),
            responded_at = excluded.responded_at,
            updated_at = excluded.updated_at");
    $stmt->bindValue(':k', $consentKey, PDO::PARAM_STR);
    $stmt->bindValue(':d', $deviceId, PDO::PARAM_STR);
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':s', $status, PDO::PARAM_STR);
    $stmt->bindValue(':t', $now, PDO::PARAM_INT);
    $stmt->execute();
}

function getCloudEncryptionConsent(string $email): ?array {
    if ($email === '') return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM cloud_encryption_consents WHERE email = :e LIMIT 1");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function saveCloudEncryptionConsent(string $email): void {
    if ($email === '') return;
    $db = getDB();
    $now = time();
    $stmt = $db->prepare("INSERT INTO cloud_encryption_consents(email, status, consented_at, updated_at)
        VALUES(:e, 'accepted', :t, :t)
        ON CONFLICT(email) DO UPDATE SET
            status = 'accepted',
            updated_at = excluded.updated_at");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':t', $now, PDO::PARAM_INT);
    $stmt->execute();
}

// ─────────────────────────────────────────────────────────────────────────────
// MONITORING HELPERS
// ─────────────────────────────────────────────────────────────────────────────

function monitorJson(array $context): string {
    $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '{}';
}

function monitorClientIp(): string {
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $trustedProxies = array_filter(array_map('trim', explode(',', (string)getenv('APP_TRUSTED_PROXIES'))));
    $remoteIsTrusted = $remote !== '' && in_array($remote, $trustedProxies, true);
    if ($remoteIsTrusted) {
        $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
            return substr($cf, 0, 128);
        }
        $xff = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($xff !== '') {
            $parts = array_map('trim', explode(',', $xff));
            $first = $parts[0] ?? '';
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return substr($first, 0, 128);
            }
        }
    }
    return substr($remote, 0, 128);
}

function monitorNullableUserEmail(?string $email): ?string {
    $email = trim((string)$email);
    if ($email === '') {
        return null;
    }
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        return $row ? $email : null;
    } catch (Exception $e) {
        return null;
    }
}

function monitorCurrentUserEmail(): ?string {
    if (isset($_SESSION['user']['email'])) {
        return monitorNullableUserEmail((string)$_SESSION['user']['email']);
    }
    if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
        try {
            $session = getUserFromActiveSessions(session_id());
            return monitorNullableUserEmail((string)($session['email'] ?? ''));
        } catch (Exception $e) {
            return null;
        }
    }
    return null;
}

function monitorCompare(float $value, string $operator, float $threshold): bool {
    switch ($operator) {
        case '>': return $value > $threshold;
        case '>=': return $value >= $threshold;
        case '<': return $value < $threshold;
        case '<=': return $value <= $threshold;
        case '=': return abs($value - $threshold) < 0.00001;
        case '!=': return abs($value - $threshold) >= 0.00001;
    }
    return false;
}

function monitorOpenAlert(AppDatabase $db, array $rule, float $value, array $context = []): void {
    $ruleId = (int)($rule['id'] ?? 0);
    if ($ruleId <= 0) {
        return;
    }
    $exists = $db->prepare("SELECT id FROM monitor_alerts WHERE rule_id = :rule_id AND status = 'open' LIMIT 1");
    $exists->bindValue(':rule_id', $ruleId, PDO::PARAM_INT);
    if ($exists->execute()->fetch(PDO::FETCH_ASSOC)) {
        return;
    }

    $title = (string)($rule['name'] ?? 'Monitoring alert');
    $message = sprintf(
        '%s: %.2f %s %.2f',
        (string)($rule['metric'] ?? 'metric'),
        $value,
        (string)($rule['operator'] ?? '>='),
        (float)($rule['threshold'] ?? 0)
    );
    $stmt = $db->prepare("INSERT INTO monitor_alerts
        (rule_id, severity, status, title, message, value, context_json)
        VALUES(:rule_id, :severity, 'open', :title, :message, :value, :context_json)");
    $stmt->bindValue(':rule_id', $ruleId, PDO::PARAM_INT);
    $stmt->bindValue(':severity', (string)($rule['severity'] ?? 'warning'), PDO::PARAM_STR);
    $stmt->bindValue(':title', $title, PDO::PARAM_STR);
    $stmt->bindValue(':message', $message, PDO::PARAM_STR);
    $stmt->bindValue(':value', $value, PDO::PARAM_STR);
    $stmt->bindValue(':context_json', monitorJson($context), PDO::PARAM_STR);
    $stmt->execute();
}

function monitorCloseAlert(AppDatabase $db, int $ruleId): void {
    if ($ruleId <= 0) {
        return;
    }
    $stmt = $db->prepare("UPDATE monitor_alerts
        SET status = 'closed', closed_at = :closed_at
        WHERE rule_id = :rule_id AND status = 'open'");
    $stmt->bindValue(':closed_at', time(), PDO::PARAM_INT);
    $stmt->bindValue(':rule_id', $ruleId, PDO::PARAM_INT);
    $stmt->execute();
}

function monitorRuleSustained(AppDatabase $db, array $rule, float $currentValue): bool {
    $metric = (string)($rule['metric'] ?? '');
    $operator = (string)($rule['operator'] ?? '>=');
    $threshold = (float)($rule['threshold'] ?? 0);
    $duration = max(0, (int)($rule['duration_sec'] ?? 0));
    if ($duration <= 0) {
        return monitorCompare($currentValue, $operator, $threshold);
    }

    $columnMap = [
        'system.cpu_percent' => 'cpu_percent',
        'system.memory_percent' => 'memory_percent',
        'system.swap_percent' => 'swap_percent',
        'system.disk_io_percent' => 'disk_io_percent',
    ];
    if (!isset($columnMap[$metric])) {
        return monitorCompare($currentValue, $operator, $threshold);
    }

    $since = time() - $duration;
    $column = $columnMap[$metric];
    $res = $db->query("SELECT $column AS value FROM monitor_system_snapshots WHERE created_at >= $since ORDER BY created_at ASC");
    $count = 0;
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $count++;
        if (!monitorCompare((float)$row['value'], $operator, $threshold)) {
            return false;
        }
    }
    return $count >= max(2, (int)floor($duration / 90));
}

function monitorEvaluateAlerts(array $metricValues, array $context = []): void {
    try {
        $db = getDB();
        $res = $db->query("SELECT * FROM monitor_alert_rules WHERE enabled = 1");
        while ($rule = $res->fetch(PDO::FETCH_ASSOC)) {
            $metric = (string)($rule['metric'] ?? '');
            if (!array_key_exists($metric, $metricValues)) {
                continue;
            }
            $value = (float)$metricValues[$metric];
            $breached = monitorRuleSustained($db, $rule, $value);
            if ($breached) {
                monitorOpenAlert($db, $rule, $value, $context + ['metric' => $metric]);
            } else {
                monitorCloseAlert($db, (int)$rule['id']);
            }
        }
    } catch (Exception $e) {
        error_log('monitorEvaluateAlerts failed: ' . $e->getMessage());
    }
}

function monitorRecordSystemSnapshot(array $metrics, int $minIntervalSec = 60): ?int {
    try {
        $db = getDB();
        $now = time();
        $last = $db->querySingle('SELECT id FROM monitor_system_snapshots ORDER BY created_at DESC LIMIT 1', true);
        if (is_array($last) && isset($last['id'])) {
            $lastTime = (int)$db->querySingle('SELECT created_at FROM monitor_system_snapshots WHERE id = ' . (int)$last['id']);
            if ($minIntervalSec > 0 && ($now - $lastTime) < $minIntervalSec) {
                return (int)$last['id'];
            }
        }

        $networkRecv = 0;
        $networkSent = 0;
        foreach (($metrics['network_data'] ?? []) as $interface) {
            if (!is_array($interface)) {
                continue;
            }
            $networkRecv += (int)($interface['bytes_recv_rate'] ?? $interface['bytes_recv_rate_per_sec'] ?? 0);
            $networkSent += (int)($interface['bytes_sent_rate'] ?? $interface['bytes_sent_rate_per_sec'] ?? 0);
        }

        $uptime = $metrics['uptime'] ?? null;
        $uptimeSeconds = null;
        if (is_array($uptime)) {
            $uptimeSeconds = ((int)($uptime['days'] ?? 0) * 86400) + ((int)($uptime['hours'] ?? 0) * 3600) + ((int)($uptime['minutes'] ?? 0) * 60);
        } elseif (is_numeric($uptime)) {
            $uptimeSeconds = (int)$uptime;
        }

        $stmt = $db->prepare("INSERT INTO monitor_system_snapshots
            (created_at, cpu_percent, memory_percent, swap_percent, disk_io_percent,
             disk_read_bytes_per_sec, disk_write_bytes_per_sec, network_recv_bytes_per_sec,
             network_sent_bytes_per_sec, uptime_seconds, status, context_json)
            VALUES(:created_at, :cpu, :memory, :swap, :disk_io, :disk_read, :disk_write,
                   :network_recv, :network_sent, :uptime, :status, :context_json)");
        $stmt->bindValue(':created_at', $now, PDO::PARAM_INT);
        $stmt->bindValue(':cpu', (float)($metrics['cpu_usage'] ?? 0), PDO::PARAM_STR);
        $stmt->bindValue(':memory', (float)($metrics['memory_usage'] ?? 0), PDO::PARAM_STR);
        $stmt->bindValue(':swap', (float)($metrics['swap_usage'] ?? 0), PDO::PARAM_STR);
        $stmt->bindValue(':disk_io', (float)($metrics['disk_usage'] ?? 0), PDO::PARAM_STR);
        $stmt->bindValue(':disk_read', (int)($metrics['disk_read_bytes_per_sec'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':disk_write', (int)($metrics['disk_write_bytes_per_sec'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':network_recv', $networkRecv, PDO::PARAM_INT);
        $stmt->bindValue(':network_sent', $networkSent, PDO::PARAM_INT);
        if ($uptimeSeconds === null) {
            $stmt->bindValue(':uptime', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':uptime', $uptimeSeconds, PDO::PARAM_INT);
        }
        $stmt->bindValue(':status', (string)($metrics['status'] ?? 'ok'), PDO::PARAM_STR);
        $context = is_array($metrics['context'] ?? null) ? $metrics['context'] : [];
        if (!empty($metrics['network_data']) && is_array($metrics['network_data'])) {
            $context['network_data'] = $metrics['network_data'];
        }
        if (!empty($metrics['smart']) && is_array($metrics['smart'])) {
            $context['smart'] = $metrics['smart'];
        }
        $stmt->bindValue(':context_json', monitorJson($context), PDO::PARAM_STR);
        $stmt->execute();
        $snapshotId = (int)$db->lastInsertRowID();

        $diskStmt = $db->prepare("INSERT INTO monitor_disk_snapshots
            (snapshot_id, drive_letter, total_bytes, free_bytes, used_bytes, used_percent)
            VALUES(:snapshot_id, :drive_letter, :total_bytes, :free_bytes, :used_bytes, :used_percent)");
        foreach (($metrics['drives'] ?? []) as $drive) {
            if (!is_array($drive)) {
                continue;
            }
            $total = (int)($drive['total_space'] ?? 0);
            $free = (int)($drive['free_space'] ?? 0);
            $used = max(0, $total - $free);
            $diskStmt->bindValue(':snapshot_id', $snapshotId, PDO::PARAM_INT);
            $diskStmt->bindValue(':drive_letter', substr((string)($drive['letter'] ?? ''), 0, 32), PDO::PARAM_STR);
            $diskStmt->bindValue(':total_bytes', $total, PDO::PARAM_INT);
            $diskStmt->bindValue(':free_bytes', $free, PDO::PARAM_INT);
            $diskStmt->bindValue(':used_bytes', $used, PDO::PARAM_INT);
            $diskStmt->bindValue(':used_percent', (float)($drive['usage_percent'] ?? 0), PDO::PARAM_STR);
            $diskStmt->execute();
        }

        $metricValues = [
            'system.cpu_percent' => (float)($metrics['cpu_usage'] ?? 0),
            'system.memory_percent' => (float)($metrics['memory_usage'] ?? 0),
            'system.swap_percent' => (float)($metrics['swap_usage'] ?? 0),
            'system.disk_io_percent' => (float)($metrics['disk_usage'] ?? 0),
        ];
        $maxDisk = 0.0;
        foreach (($metrics['drives'] ?? []) as $drive) {
            if (is_array($drive)) {
                $maxDisk = max($maxDisk, (float)($drive['usage_percent'] ?? 0));
            }
        }
        $metricValues['disk.used_percent'] = $maxDisk;
        monitorEvaluateAlerts($metricValues, ['snapshot_id' => $snapshotId]);
        monitorRetentionCleanup();

        return $snapshotId;
    } catch (Exception $e) {
        error_log('monitorRecordSystemSnapshot failed: ' . $e->getMessage());
        return null;
    }
}

function monitorRecordAppEvent(string $eventType, string $message = '', array $context = [], string $level = 'info', string $appId = '', ?string $userEmail = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO monitor_app_events
            (level, app_id, event_type, user_email, ip_address, user_agent, message, context_json)
            VALUES(:level, :app_id, :event_type, :user_email, :ip_address, :user_agent, :message, :context_json)");
        $stmt->bindValue(':level', in_array($level, ['debug','info','warning','error','critical'], true) ? $level : 'info', PDO::PARAM_STR);
        $stmt->bindValue(':app_id', substr($appId, 0, 128), PDO::PARAM_STR);
        $stmt->bindValue(':event_type', substr($eventType, 0, 160), PDO::PARAM_STR);
        $email = monitorNullableUserEmail($userEmail) ?? monitorCurrentUserEmail();
        $stmt->bindValue(':user_email', $email, $email === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', monitorClientIp(), PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512), PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':context_json', monitorJson($context), PDO::PARAM_STR);
        $stmt->execute();
    } catch (Exception $e) {
        error_log('monitorRecordAppEvent failed: ' . $e->getMessage());
    }
}

function monitorRecordHttpRequest(array $data): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO monitor_http_requests
            (method, path, app_id, user_email, ip_address, status_code, duration_ms, response_bytes, error_message, context_json)
            VALUES(:method, :path, :app_id, :user_email, :ip_address, :status_code, :duration_ms, :response_bytes, :error_message, :context_json)");
        $stmt->bindValue(':method', substr((string)($data['method'] ?? ''), 0, 16), PDO::PARAM_STR);
        $stmt->bindValue(':path', substr((string)($data['path'] ?? ''), 0, 1024), PDO::PARAM_STR);
        $stmt->bindValue(':app_id', substr((string)($data['app_id'] ?? ''), 0, 128), PDO::PARAM_STR);
        $email = monitorNullableUserEmail($data['user_email'] ?? null);
        $stmt->bindValue(':user_email', $email, $email === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', substr((string)($data['ip_address'] ?? monitorClientIp()), 0, 128), PDO::PARAM_STR);
        $stmt->bindValue(':status_code', (int)($data['status_code'] ?? 200), PDO::PARAM_INT);
        $stmt->bindValue(':duration_ms', (int)($data['duration_ms'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':response_bytes', (int)($data['response_bytes'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':error_message', substr((string)($data['error_message'] ?? ''), 0, 2048), PDO::PARAM_STR);
        $stmt->bindValue(':context_json', monitorJson($data['context'] ?? []), PDO::PARAM_STR);
        $stmt->execute();

        if (getenv('APP_MONITOR_SYNC_ALERTS') === '1') {
            $since = time() - 300;
            $serverErrors = (int)$db->querySingle("SELECT COUNT(*) FROM monitor_http_requests WHERE created_at >= $since AND status_code >= 500");
            monitorEvaluateAlerts(['http.5xx_count_5m' => $serverErrors], ['window_sec' => 300]);
        }
    } catch (Exception $e) {
        error_log('monitorRecordHttpRequest failed: ' . $e->getMessage());
    }
}

function monitorRecordError(string $message, string $level = 'error', string $file = '', ?int $line = null, array $context = [], string $appId = '', ?string $userEmail = null): void {
    try {
        $db = getDB();
        $level = in_array($level, ['warning','error','critical'], true) ? $level : 'error';
        $traceHash = hash('sha256', $level . '|' . $file . '|' . (string)$line . '|' . $message);
        $stmt = $db->prepare("INSERT INTO monitor_errors
            (level, app_id, user_email, file, line, message, trace_hash, context_json)
            VALUES(:level, :app_id, :user_email, :file, :line, :message, :trace_hash, :context_json)");
        $stmt->bindValue(':level', $level, PDO::PARAM_STR);
        $stmt->bindValue(':app_id', substr($appId, 0, 128), PDO::PARAM_STR);
        $email = monitorNullableUserEmail($userEmail) ?? monitorCurrentUserEmail();
        $stmt->bindValue(':user_email', $email, $email === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':file', substr($file, 0, 1024), PDO::PARAM_STR);
        if ($line === null) {
            $stmt->bindValue(':line', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':line', $line, PDO::PARAM_INT);
        }
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':trace_hash', $traceHash, PDO::PARAM_STR);
        $stmt->bindValue(':context_json', monitorJson($context), PDO::PARAM_STR);
        $stmt->execute();

        if (getenv('APP_MONITOR_SYNC_ALERTS') === '1') {
            $since = time() - 300;
            $errors = (int)$db->querySingle("SELECT COUNT(*) FROM monitor_errors WHERE created_at >= $since AND level IN ('error','critical')");
            monitorEvaluateAlerts(['errors.count_5m' => $errors], ['window_sec' => 300]);
        }
    } catch (Exception $e) {
        error_log('monitorRecordError failed: ' . $e->getMessage());
    }
}

function monitorRetentionTableExists(AppDatabase $db, string $table): bool {
    try {
        return (bool)$db->querySingle("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='" . AppDatabase::escapeString($table) . "' LIMIT 1");
    } catch (Exception $e) {
        return false;
    }
}

function monitorRetentionExec(AppDatabase $db, string $sql): void {
    try {
        $db->exec($sql);
    } catch (Exception $e) {
    }
}

function monitorRetentionSetMeta(AppDatabase $db, string $key, string $value): void {
    $stmt = $db->prepare(
        'INSERT INTO app_meta(key, value) VALUES(:key, :value)
         ON CONFLICT (key) DO UPDATE SET value = excluded.value'
    );
    $stmt->bindValue(':key', $key, PDO::PARAM_STR);
    $stmt->bindValue(':value', $value, PDO::PARAM_STR);
    $stmt->execute();
}

function monitorRetentionCleanup(int $rawDays = 30, int $retentionDays = 30, int $eventDays = 30): void {
    try {
        $db = getDB();
        $now = time();
        $lastCleanup = (int)$db->querySingle("SELECT value FROM app_meta WHERE key = 'monitor_retention_last_cleanup'");
        if (($now - $lastCleanup) < 3600) {
            return;
        }

        $rawCutoff = $now - ($rawDays * 86400);
        $retentionCutoff = $now - ($retentionDays * 86400);
        $eventCutoff = $now - ($eventDays * 86400);

        if ($retentionDays > $rawDays) {
            $metrics = [
                'cpu_percent' => 'system.cpu_percent',
                'memory_percent' => 'system.memory_percent',
                'swap_percent' => 'system.swap_percent',
                'disk_io_percent' => 'system.disk_io_percent',
                'network_recv_bytes_per_sec' => 'system.network_recv_bytes_per_sec',
                'network_sent_bytes_per_sec' => 'system.network_sent_bytes_per_sec',
            ];
            foreach ($metrics as $column => $metricName) {
                $sql = "SELECT (created_at / 3600) * 3600 AS metric_hour,
                               MIN($column) AS min_value,
                               AVG($column) AS avg_value,
                               MAX($column) AS max_value,
                               COUNT(*) AS sample_count
                        FROM monitor_system_snapshots
                        WHERE created_at < $rawCutoff
                        GROUP BY metric_hour";
                $res = $db->query($sql);
                $insertStmt = $db->prepare("INSERT INTO monitor_metric_hourly
                    (metric_hour, metric, app_id, min_value, avg_value, max_value, sample_count, updated_at)
                    VALUES(:metric_hour, :metric, '', :min_value, :avg_value, :max_value, :sample_count, :updated_at)");
                $updateStmt = $db->prepare("UPDATE monitor_metric_hourly SET
                    min_value = :min_value,
                    avg_value = :avg_value,
                    max_value = :max_value,
                    sample_count = :sample_count,
                    updated_at = :updated_at
                    WHERE metric_hour = :metric_hour AND metric = :metric AND app_id = ''");
                while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                    $metricHour = (int)$row['metric_hour'];
                    $existing = $db->querySingle("SELECT min_value, avg_value, max_value, sample_count FROM monitor_metric_hourly WHERE metric_hour = $metricHour AND metric = '" . AppDatabase::escapeString($metricName) . "' AND app_id = '' LIMIT 1", true);
                    $sampleCount = (int)$row['sample_count'];
                    $minValue = (float)$row['min_value'];
                    $avgValue = (float)$row['avg_value'];
                    $maxValue = (float)$row['max_value'];
                    $stmt = $insertStmt;
                    if (is_array($existing)) {
                        $existingSamples = (int)($existing['sample_count'] ?? 0);
                        $totalSamples = max(1, $existingSamples + $sampleCount);
                        $minValue = min((float)($existing['min_value'] ?? $minValue), $minValue);
                        $maxValue = max((float)($existing['max_value'] ?? $maxValue), $maxValue);
                        $avgValue = (((float)($existing['avg_value'] ?? 0) * $existingSamples) + ($avgValue * $sampleCount)) / $totalSamples;
                        $sampleCount = $totalSamples;
                        $stmt = $updateStmt;
                    }
                    $stmt->bindValue(':metric_hour', $metricHour, PDO::PARAM_INT);
                    $stmt->bindValue(':metric', $metricName, PDO::PARAM_STR);
                    $stmt->bindValue(':min_value', $minValue, PDO::PARAM_STR);
                    $stmt->bindValue(':avg_value', $avgValue, PDO::PARAM_STR);
                    $stmt->bindValue(':max_value', $maxValue, PDO::PARAM_STR);
                    $stmt->bindValue(':sample_count', $sampleCount, PDO::PARAM_INT);
                    $stmt->bindValue(':updated_at', $now, PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        }

        monitorRetentionExec($db, "DELETE FROM monitor_disk_snapshots WHERE snapshot_id IN (SELECT id FROM monitor_system_snapshots WHERE created_at < $rawCutoff)");
        monitorRetentionExec($db, "DELETE FROM monitor_system_snapshots WHERE created_at < $rawCutoff");
        monitorRetentionExec($db, "DELETE FROM monitor_metric_hourly WHERE metric_hour < $retentionCutoff");
        monitorRetentionExec($db, "DELETE FROM monitor_app_events WHERE created_at < $eventCutoff");
        monitorRetentionExec($db, "DELETE FROM monitor_http_requests WHERE created_at < $eventCutoff");
        monitorRetentionExec($db, "DELETE FROM monitor_errors WHERE created_at < $eventCutoff AND resolved = 1");
        monitorRetentionExec($db, "DELETE FROM monitor_uptime_results WHERE checked_at < $retentionCutoff");
        monitorRetentionExec($db, "DELETE FROM monitor_uptime_logs WHERE started_at < $retentionCutoff");
        monitorRetentionExec($db, "DELETE FROM monitor_docker_snapshots WHERE created_at < $retentionCutoff");
        if (monitorRetentionTableExists($db, 'monitor_smart_snapshots')) {
            monitorRetentionExec($db, "DELETE FROM monitor_smart_snapshots WHERE created_at < $retentionCutoff");
        }
        monitorRetentionSetMeta($db, 'monitor_retention_last_cleanup', (string)$now);
    } catch (Exception $e) {
        error_log('monitorRetentionCleanup failed: ' . $e->getMessage());
    }
}
