<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
// db.php
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            require_once __DIR__ . '/../../lib/db_config.php';
            $this->pdo = app_pdo_connect();
            $this->ensureTablesExist();
        } catch (PDOException $e) {
            cloud_safe_error_log("Database connection failed: " . $e->getMessage());
            $this->pdo = null;
        }
    }

    // ------------------------------------------------------------------
    //  Schema bootstrap
    // ------------------------------------------------------------------
    private function ensureTablesExist() {
        if (!$this->pdo) return;
        if (function_exists('app_db_is_pgsql') && app_db_is_pgsql()) return;

        // active_sessions
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='active_sessions'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE active_sessions (
                    session_id    TEXT    PRIMARY KEY,
                    email         TEXT    NOT NULL,
                    user_data     TEXT    NOT NULL DEFAULT '{}',
                    login_time    INTEGER NOT NULL,
                    last_activity INTEGER NOT NULL,
                    ip_address    TEXT    NOT NULL DEFAULT '',
                    user_agent    TEXT    NOT NULL DEFAULT '',
                    device        TEXT    NOT NULL DEFAULT '',
                    browser       TEXT    NOT NULL DEFAULT '',
                    FOREIGN KEY (email) REFERENCES users(email)
                        ON DELETE CASCADE ON UPDATE CASCADE
                )
            ");
        }

        // users
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE users (
                    email            TEXT    PRIMARY KEY,
                    name             TEXT    NOT NULL DEFAULT '',
                    password         TEXT    NOT NULL DEFAULT '',
                    photo            TEXT    NOT NULL DEFAULT 'default_avatar.png',
                    role             INTEGER NOT NULL DEFAULT 0,
                    verified         INTEGER NOT NULL DEFAULT 0,
                    language         TEXT    NOT NULL DEFAULT 'en',
                    theme            TEXT    NOT NULL DEFAULT 'light',
                    color            TEXT    NOT NULL DEFAULT '#007bff',
                    created_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
                )
            ");
        }

        // app_meta
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='app_meta'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE app_meta (
                    key   TEXT PRIMARY KEY,
                    value TEXT NOT NULL DEFAULT ''
                )
            ");
        }

        // shared_files
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='shared_files'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE shared_files (
                    share_id   TEXT PRIMARY KEY,
                    file_name  TEXT NOT NULL DEFAULT '',
                    file_path  TEXT NOT NULL DEFAULT '',
                    is_folder  INTEGER NOT NULL DEFAULT 0,
                    user_email TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
                    expires    INTEGER DEFAULT NULL,
                    expires_option TEXT NOT NULL DEFAULT 'never',
                    password_hash TEXT NOT NULL DEFAULT '',
                    view_only INTEGER NOT NULL DEFAULT 0,
                    disable_download INTEGER NOT NULL DEFAULT 0,
                    disable_copy INTEGER NOT NULL DEFAULT 0,
                    max_views INTEGER NOT NULL DEFAULT 0,
                    view_count INTEGER NOT NULL DEFAULT 0
                )
            ");
        } else {
            // Добавляем недостающие поля shared_files если их нет
            $hasIsFolder = false;
            $hasUserEmail = false;
            $hasExpires = false;
            $hasExpiresOption = false;
            $hasPasswordHash = false;
            $hasViewOnly = false;
            $hasDisableDownload = false;
            $hasDisableCopy = false;
            $hasMaxViews = false;
            $hasViewCount = false;
            $columnsStmt = $this->pdo->query("PRAGMA table_info(shared_files)");
            while ($columnsStmt && ($column = $columnsStmt->fetch())) {
                $name = (string)($column['name'] ?? '');
                if ($name === 'is_folder') $hasIsFolder = true;
                if ($name === 'user_email') $hasUserEmail = true;
                if ($name === 'expires') $hasExpires = true;
                if ($name === 'expires_option') $hasExpiresOption = true;
                if ($name === 'password_hash') $hasPasswordHash = true;
                if ($name === 'view_only') $hasViewOnly = true;
                if ($name === 'disable_download') $hasDisableDownload = true;
                if ($name === 'disable_copy') $hasDisableCopy = true;
                if ($name === 'max_views') $hasMaxViews = true;
                if ($name === 'view_count') $hasViewCount = true;
            }
            if (!$hasIsFolder) {
                $this->pdo->exec("ALTER TABLE shared_files ADD COLUMN is_folder INTEGER NOT NULL DEFAULT 0");
            }
            if (!$hasUserEmail) {
                $this->pdo->exec("ALTER TABLE shared_files ADD COLUMN user_email TEXT NOT NULL DEFAULT ''");
            }
            if (!$hasExpires) {
                $this->pdo->exec("ALTER TABLE shared_files ADD COLUMN expires INTEGER DEFAULT NULL");
            }
            if (!$hasExpiresOption) {
                $this->pdo->exec("ALTER TABLE shared_files ADD COLUMN expires_option TEXT NOT NULL DEFAULT 'never'");
            }
            if (!$hasPasswordHash) {
                $this->pdo->exec("ALTER TABLE shared_files ADD COLUMN password_hash TEXT NOT NULL DEFAULT ''");
            }
            if (!$hasViewOnly) {
                $this->pdo->exec("ALTER TABLE shared_files ADD COLUMN view_only INTEGER NOT NULL DEFAULT 0");
            }
            if (!$hasDisableDownload) {
                $this->pdo->exec("ALTER TABLE shared_files ADD COLUMN disable_download INTEGER NOT NULL DEFAULT 0");
            }
            if (!$hasDisableCopy) {
                $this->pdo->exec("ALTER TABLE shared_files ADD COLUMN disable_copy INTEGER NOT NULL DEFAULT 0");
            }
            if (!$hasMaxViews) {
                $this->pdo->exec("ALTER TABLE shared_files ADD COLUMN max_views INTEGER NOT NULL DEFAULT 0");
            }
            if (!$hasViewCount) {
                $this->pdo->exec("ALTER TABLE shared_files ADD COLUMN view_count INTEGER NOT NULL DEFAULT 0");
            }
        }

        // cloud_user_settings — quota and session limit per user (cloud app only)
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_user_settings'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE cloud_user_settings (
                    email            TEXT    PRIMARY KEY,
                    quota_mb         INTEGER NOT NULL DEFAULT 500,
                    session_limit_mb INTEGER NOT NULL DEFAULT 0,
                    hide_lang_switcher INTEGER NOT NULL DEFAULT 0,
                    updated_at       INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
                    FOREIGN KEY (email) REFERENCES users(email)
                        ON DELETE CASCADE ON UPDATE CASCADE
                )
            ");
            // Pre-fill from existing users with defaults
            $this->pdo->exec("
                INSERT OR IGNORE INTO cloud_user_settings (email, quota_mb, session_limit_mb)
                SELECT email, 500, 0 FROM users
            ");
        } else {
            // Backward-compatible migration for existing installations.
            $hasHideLangSwitcherColumn = false;
            $columnsStmt = $this->pdo->query("PRAGMA table_info(cloud_user_settings)");
            while ($columnsStmt && ($column = $columnsStmt->fetch())) {
                if (($column['name'] ?? '') === 'hide_lang_switcher') {
                    $hasHideLangSwitcherColumn = true;
                    break;
                }
            }
            if (!$hasHideLangSwitcherColumn) {
                $this->pdo->exec("ALTER TABLE cloud_user_settings ADD COLUMN hide_lang_switcher INTEGER NOT NULL DEFAULT 0");
            }
        }

        // cloud_folder_state  — stores per-user expanded folder paths (cloud app only)
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_folder_state'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE cloud_folder_state (
                    email       TEXT    PRIMARY KEY,
                    folder_json TEXT    NOT NULL DEFAULT '[]',
                    updated_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
                    FOREIGN KEY (email) REFERENCES users(email)
                        ON DELETE CASCADE ON UPDATE CASCADE
                )
            ");
        }


        $stmt = $this->pdo->query("SELECT COUNT(*) as cnt FROM cloud_folder_state");
        $count = $stmt->fetch();
        if ($count['cnt'] == 0) {
            // Заполняем таблицу для всех пользователей
            $this->pdo->exec("
                INSERT OR IGNORE INTO cloud_folder_state (email, folder_json, updated_at)
                SELECT email, '[]', extract(epoch from now())::bigint 
                FROM users 
                WHERE email NOT IN (SELECT email FROM cloud_folder_state)
            ");
        }

        // cloud_storage_usage_stats — cached folder usage treemap data
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_storage_usage_stats'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE cloud_storage_usage_stats (
                    email            TEXT    PRIMARY KEY,
                    stats_json       TEXT    NOT NULL DEFAULT '[]',
                    root_total_bytes INTEGER NOT NULL DEFAULT 0,
                    updated_at       INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
                    FOREIGN KEY (email) REFERENCES users(email)
                        ON DELETE CASCADE ON UPDATE CASCADE
                )
            ");
        }

        // cloud_sync_logs — per-user sync activity log (cloud app only)
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_sync_logs'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE cloud_sync_logs (
                    id       INTEGER PRIMARY KEY AUTOINCREMENT,
                    email    TEXT    NOT NULL,
                    ts       INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
                    level    TEXT    NOT NULL DEFAULT 'INFO',
                    root_key TEXT    NOT NULL DEFAULT '',
                    action   TEXT    NOT NULL DEFAULT '',
                    path     TEXT    NOT NULL DEFAULT '',
                    message  TEXT    NOT NULL DEFAULT '',
                    FOREIGN KEY (email) REFERENCES users(email)
                        ON DELETE CASCADE ON UPDATE CASCADE
                )
            ");
            $this->pdo->exec("CREATE INDEX idx_cloud_sync_logs_email_ts ON cloud_sync_logs(email, ts DESC)");
        }

        // cloud_ai_assistant_chats — stores Q/A pairs for AI assistant (cloud app table)
        $oldAiChats = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ai_assistant_chats'")->fetch();
        $newAiChats = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_ai_assistant_chats'")->fetch();
        if ($oldAiChats && !$newAiChats) {
            $this->pdo->exec("ALTER TABLE ai_assistant_chats RENAME TO cloud_ai_assistant_chats");
        }
        $this->pdo->exec("DROP INDEX IF EXISTS idx_ai_assistant_chats_email_created");
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_ai_assistant_chats'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE cloud_ai_assistant_chats (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    email      TEXT    NOT NULL,
                    question   TEXT    NOT NULL,
                    answer     TEXT    NOT NULL,
                    model      TEXT    NOT NULL DEFAULT '',
                    file_name  TEXT    NOT NULL DEFAULT '',
                    time_seconds REAL   NULL,
                    tokens       INTEGER NULL,
                    speed        REAL   NULL,
                    created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
                    FOREIGN KEY (email) REFERENCES users(email)
                        ON DELETE CASCADE ON UPDATE CASCADE
                )
            ");
        }
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cloud_ai_assistant_chats_email_created ON cloud_ai_assistant_chats(email, created_at DESC)");

        $oldAiSettings = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ai_assistant_user_settings'")->fetch();
        $newAiSettings = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_ai_assistant_user_settings'")->fetch();
        if ($oldAiSettings && !$newAiSettings) {
            $this->pdo->exec("ALTER TABLE ai_assistant_user_settings RENAME TO cloud_ai_assistant_user_settings");
        }
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_ai_assistant_user_settings'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE cloud_ai_assistant_user_settings (
                    email         TEXT    PRIMARY KEY,
                    settings_json TEXT    NOT NULL DEFAULT '{}',
                    updated_at    INTEGER NOT NULL DEFAULT 0,
                    auto_confirm_agent_edits INTEGER NOT NULL DEFAULT 0
                )
            ");
        } else {
            $hasAutoConfirm = false;
            $columnsStmt = $this->pdo->query("PRAGMA table_info(cloud_ai_assistant_user_settings)");
            while ($columnsStmt && ($column = $columnsStmt->fetch())) {
                if (($column['name'] ?? '') === 'auto_confirm_agent_edits') {
                    $hasAutoConfirm = true;
                    break;
                }
            }
            if (!$hasAutoConfirm) {
                $this->pdo->exec("ALTER TABLE cloud_ai_assistant_user_settings ADD COLUMN auto_confirm_agent_edits INTEGER NOT NULL DEFAULT 0");
            }
        }

        // cloud_groups — collaborative editing groups (cloud app only)
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_groups'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE cloud_groups (
                    group_id TEXT PRIMARY KEY,
                    name TEXT NOT NULL DEFAULT '',
                    admin_email TEXT NOT NULL,
                    created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
                    FOREIGN KEY (admin_email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
                )
            ");
        }
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_group_members'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE cloud_group_members (
                    group_id TEXT NOT NULL,
                    member_email TEXT NOT NULL,
                    permission TEXT NOT NULL DEFAULT 'read_edit',
                    joined_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
                    PRIMARY KEY (group_id, member_email),
                    FOREIGN KEY (group_id) REFERENCES cloud_groups(group_id) ON DELETE CASCADE,
                    FOREIGN KEY (member_email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
                )
            ");
        }
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cloud_group_file_history'");
        if (!$stmt->fetch()) {
            $this->pdo->exec("
                CREATE TABLE cloud_group_file_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    group_id TEXT NOT NULL,
                    file_path TEXT NOT NULL DEFAULT '',
                    user_email TEXT NOT NULL DEFAULT '',
                    action TEXT NOT NULL DEFAULT 'open',
                    created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
                    FOREIGN KEY (group_id) REFERENCES cloud_groups(group_id) ON DELETE CASCADE
                )
            ");
            $this->pdo->exec("CREATE INDEX idx_cloud_group_history_group_ts ON cloud_group_file_history(group_id, created_at DESC)");
        }

    }

    // ------------------------------------------------------------------
    //  Singleton
    // ------------------------------------------------------------------
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // ------------------------------------------------------------------
    //  Sessions
    // ------------------------------------------------------------------
    public function getActiveSessions() {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->query("SELECT * FROM active_sessions");
        $sessions = [];
        while ($row = $stmt->fetch()) {
            $sessions[$row['session_id']] = [
                'email'         => $row['email'],
                'user_data'     => json_decode($row['user_data'], true),
                'login_time'    => $row['login_time'],
                'last_activity' => $row['last_activity'],
                'ip_address'    => $row['ip_address'],
                'user_agent'    => $row['user_agent'],
                'device'        => $row['device'],
                'browser'       => $row['browser'],
            ];
        }
        return $sessions;
    }

    public function getSessionById($sessionId) {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->prepare("SELECT * FROM active_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return [
            'email'         => $row['email'],
            'user_data'     => json_decode($row['user_data'], true),
            'login_time'    => $row['login_time'],
            'last_activity' => $row['last_activity'],
            'ip_address'    => $row['ip_address'],
            'user_agent'    => $row['user_agent'],
            'device'        => $row['device'],
            'browser'       => $row['browser'],
        ];
    }

    public function updateSessionActivity($sessionId) {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("UPDATE active_sessions SET last_activity = ? WHERE session_id = ?");
        return $stmt->execute([time(), $sessionId]);
    }

    public function cleanupOldSessions($maxAge = 86400) {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("DELETE FROM active_sessions WHERE last_activity < ?");
        return $stmt->execute([time() - $maxAge]);
    }

    // ------------------------------------------------------------------
    //  Users
    // ------------------------------------------------------------------
    public function getUserRole($email) {
        if (!$this->pdo) return 0;
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ? (int)$row['role'] : 0;
    }

    /**
     * Get storage quota in MB for a user (by email).
     * Reads from cloud_user_settings table.
     */
    public function getUserQuota($email) {
        if (!$this->pdo) return 500;
        $stmt = $this->pdo->prepare("SELECT quota_mb FROM cloud_user_settings WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ? max(1, (int)$row['quota_mb']) : 500;
    }

    /**
     * Get per-session upload limit in MB (0 = no limit).
     * Reads from cloud_user_settings table.
     */
    public function getSessionLimit($email) {
        if (!$this->pdo) return 0;
        $stmt = $this->pdo->prepare("SELECT session_limit_mb FROM cloud_user_settings WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ? max(0, (int)$row['session_limit_mb']) : 0;
    }

    /**
     * Update storage quota for a user (UPSERT into cloud_user_settings).
     */
    public function setUserQuota($email, int $quota_mb) {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("
            INSERT INTO cloud_user_settings (email, quota_mb, session_limit_mb, updated_at)
            VALUES (?, ?, COALESCE((SELECT session_limit_mb FROM cloud_user_settings WHERE email = ?), 0), extract(epoch from now())::bigint)
            ON CONFLICT(email) DO UPDATE SET quota_mb = EXCLUDED.quota_mb, updated_at = EXCLUDED.updated_at
        ");
        return $stmt->execute([max(1, $quota_mb), $email, $email]);
    }

    /**
     * Update session upload limit for a user (UPSERT into cloud_user_settings).
     */
    public function setSessionLimit($email, int $limit_mb) {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("
            INSERT INTO cloud_user_settings (email, quota_mb, session_limit_mb, updated_at)
            VALUES (?, COALESCE((SELECT quota_mb FROM cloud_user_settings WHERE email = ?), 500), ?, extract(epoch from now())::bigint)
            ON CONFLICT(email) DO UPDATE SET session_limit_mb = EXCLUDED.session_limit_mb, updated_at = EXCLUDED.updated_at
        ");
        return $stmt->execute([$email, $email, max(0, $limit_mb)]);
    }

    // ------------------------------------------------------------------
    //  Folder state
    // ------------------------------------------------------------------

    /**
     * Load the list of expanded folder paths for a user.
     * Returns a plain PHP array of path strings.
     */
    public function getFolderState($email) {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare("SELECT folder_json FROM cloud_folder_state WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row) return [];
        $decoded = json_decode($row['folder_json'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Persist the list of expanded folder paths for a user.
     * Uses UPSERT so it works both for first-save and subsequent updates.
     */
    public function setFolderState($email, array $paths) {
        if (!$this->pdo) return false;
        // Sanitize: keep only non-empty strings
        $clean = array_values(array_filter(array_map('strval', $paths)));
        $json  = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt  = $this->pdo->prepare("
            INSERT INTO cloud_folder_state (email, folder_json, updated_at)
            VALUES (?, ?, extract(epoch from now())::bigint)
            ON CONFLICT(email) DO UPDATE SET
                folder_json = EXCLUDED.folder_json,
                updated_at  = EXCLUDED.updated_at
        ");
        return $stmt->execute([$email, $json]);
    }

    /**
     * Get cached storage usage stats for user.
     */
    public function getStorageUsageStats($email) {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->prepare("
            SELECT stats_json, root_total_bytes, updated_at
            FROM cloud_storage_usage_stats
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return [
            'stats' => json_decode($row['stats_json'], true) ?: [],
            'root_total_bytes' => (int)$row['root_total_bytes'],
            'updated_at' => (int)$row['updated_at']
        ];
    }

    /**
     * Save cached storage usage stats for user.
     */
    public function saveStorageUsageStats($email, array $stats, int $rootTotalBytes) {
        if (!$this->pdo) return false;
        $json = json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $this->pdo->prepare("
            INSERT INTO cloud_storage_usage_stats (email, stats_json, root_total_bytes, updated_at)
            VALUES (?, ?, ?, extract(epoch from now())::bigint)
            ON CONFLICT(email) DO UPDATE SET
                stats_json = EXCLUDED.stats_json,
                root_total_bytes = EXCLUDED.root_total_bytes,
                updated_at = EXCLUDED.updated_at
        ");
        return $stmt->execute([$email, $json ?: '[]', max(0, $rootTotalBytes)]);
    }

    /**
     * Clear cached storage usage stats.
     */
    public function clearStorageUsageStats($email) {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("DELETE FROM cloud_storage_usage_stats WHERE email = ?");
        return $stmt->execute([$email]);
    }

    // ------------------------------------------------------------------
    //  App meta
    // ------------------------------------------------------------------
    public function getLastActiveUser() {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->query("SELECT value FROM app_meta WHERE key = 'last_active_user'");
        $row  = $stmt->fetch();
        return $row ? $row['value'] : null;
    }

    public function setLastActiveUser($email) {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("INSERT INTO app_meta (key, value) VALUES ('last_active_user', ?)");
        return $stmt->execute([$email]);
    }

    // ------------------------------------------------------------------
    //  Shared files
    // ------------------------------------------------------------------
    public function getSharedFile($shareId) {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->prepare("SELECT * FROM shared_files WHERE share_id = ?");
        $stmt->execute([$shareId]);
        return $stmt->fetch();
    }

    public function createSharedFile($shareId, $fileName, $filePath) {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("INSERT INTO shared_files (share_id, file_name, file_path) VALUES (?, ?, ?)");
        return $stmt->execute([$shareId, $fileName, $filePath]);
    }

    public function getSharedFilesByPath($filePath) {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare("SELECT * FROM shared_files WHERE file_path = ?");
        $stmt->execute([$filePath]);
        return $stmt->fetchAll();
    }
}
?>
