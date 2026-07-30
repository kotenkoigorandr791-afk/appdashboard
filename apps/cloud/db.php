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
        return $stmt->execute([$email, max(1, $quota_mb), $email]);
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
        $stmt = $this->pdo->prepare("INSERT INTO app_meta (key, value) VALUES ('last_active_user', ?) ON CONFLICT (key) DO UPDATE SET value=excluded.value");
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
