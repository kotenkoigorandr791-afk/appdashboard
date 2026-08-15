<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
// api.php - Simple REST endpoints for users and files 
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/session.php';
require_once dirname(__DIR__, 2) . '/storage_helper.php';
require_once __DIR__ . '/includes/cloud_groups.php';
require_once __DIR__ . '/includes/cloud_group_chat.php';
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpPresentation\IOFactory as PresentationIOFactory;
function cloud_public_scheme(): string {
    $forwarded = (string)($_SERVER['HTTP_FORWARDED'] ?? '');
    if ($forwarded !== '' && preg_match('/(?:^|[;,]\s*)proto=(https?)/i', $forwarded, $m)) {
        return strtolower($m[1]);
    }
    $xfp = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
    if ($xfp === 'https' || $xfp === 'http') return $xfp;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') return 'https';
    if (strtolower((string)($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '')) === 'on') return 'https';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
    if (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') return 'https';
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') return 'https';
    return 'http';
}
function cloud_public_host(): string {
    $forwarded = (string)($_SERVER['HTTP_FORWARDED'] ?? '');
    if ($forwarded !== '' && preg_match('/(?:^|[;,]\s*)host=([^;,]+)/i', $forwarded, $m)) {
        return trim($m[1], "\" \t");
    }
    $xfh = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0] ?? '');
    if ($xfh !== '') return $xfh;
    return (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
}
function cloud_public_origin(): string {
    return cloud_public_scheme() . '://' . cloud_public_host();
}
if (PHP_SAPI === 'cli' && isset($argv[1])) {
    $_POST['action'] = (string)$argv[1];
    if (isset($argv[2])) $_POST['job_id'] = (string)$argv[2];
    if (isset($argv[3])) $_POST['worker_token'] = (string)$argv[3];
}
if (!defined('CLOUD_SYNC_WS_BOOT') && !defined('CLOUD_WEBDAV_BOOT') && PHP_SAPI !== 'cli') {
cloud_session_start();
$__cloudOrigin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$__cloudAllowedOrigins = array_filter(array_map('trim', explode(',', (string)getenv('APP_CORS_ORIGINS'))));
$__cloudHost = (string)($_SERVER['HTTP_HOST'] ?? '');
if ($__cloudHost !== '') {
    $__cloudAllowedOrigins[] = cloud_public_origin();
}
$__cloudAllowedOrigins = array_values(array_unique($__cloudAllowedOrigins));
if ($__cloudOrigin !== '' && in_array($__cloudOrigin, $__cloudAllowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $__cloudOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Client-Auth, X-CSRF-Token, X-Device-Id');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

const STORAGE_DIR = __DIR__ . '/storage';
const MAX_UPLOAD_BYTES = 500 * 1024 * 1024; // 500MB per request hard cap
$blockedExtensions = ['php','php3','php4','php5','php7','php8','phtml','phar','phps','pht','phtm','inc','cgi','pl','py','rb','exe','sh','bat','cmd','com','dll','jsp','asp','aspx','war','htaccess','htpasswd'];

const DB_FILE = '/opt/AppDashboard/data/db.env';

function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            require_once dirname(__DIR__, 2) . '/lib/app_db.php';
            $db = app_main_db();
            ensureCloudSyncLogsSchema($db);
            ensureCloudGroupsSchema($db);
        } catch (Exception $e) {
            cloud_safe_error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $db;
}

function normalize_cloud_device_id(string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    if (!preg_match('/^[a-f0-9]{32,128}$/i', $value)) return '';
    return strtolower($value);
}

function get_cloud_device_id(): string {
    $header = normalize_cloud_device_id((string)($_SERVER['HTTP_X_DEVICE_ID'] ?? ''));
    if ($header !== '') return $header;
    $cookie = normalize_cloud_device_id((string)($_COOKIE['ap_device_id'] ?? ''));
    if ($cookie !== '') return $cookie;
    return '';
}

function ensure_cloud_encryption_consents_schema_sqlite(AppDatabase $db): void {
    $db->exec("SELECT 1");
    $hasTable = (bool)$db->querySingle("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='cloud_encryption_consents' LIMIT 1");
    $hasDeviceId = false;
    if ($hasTable) {
        $cols = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='cloud_encryption_consents'");
        while ($cols && ($row = $cols->fetch(PDO::FETCH_ASSOC))) {
            if (($row['name'] ?? '') === 'device_id') $hasDeviceId = true;
        }
    }
    if ($hasTable && $hasDeviceId) {
        $db->exec('BEGIN');
        try {
            $db->exec("CREATE TABLE _cec_mig (
                email         TEXT NOT NULL PRIMARY KEY,
                status        TEXT NOT NULL DEFAULT 'accepted' CHECK(status IN ('accepted')),
                consented_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
                updated_at    INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
                FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
            )");
            $db->exec("INSERT INTO _cec_mig (email, status, consented_at, updated_at)
                SELECT c.email, 'accepted', MIN(c.consented_at), MAX(c.updated_at)
                FROM cloud_encryption_consents c
                INNER JOIN users u ON u.email = c.email
                WHERE c.status = 'accepted'
                GROUP BY c.email");
            $db->exec("DROP TABLE cloud_encryption_consents");
            $db->exec("ALTER TABLE _cec_mig RENAME TO cloud_encryption_consents");
            $db->exec('COMMIT');
        } catch (Exception $e) {
            $db->exec('ROLLBACK');
            throw $e;
        }
    }
    if (!(bool)$db->querySingle("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='cloud_encryption_consents' LIMIT 1")) {
        $db->exec("CREATE TABLE cloud_encryption_consents (
            email         TEXT NOT NULL PRIMARY KEY,
            status        TEXT NOT NULL DEFAULT 'accepted' CHECK(status IN ('accepted')),
            consented_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            updated_at    INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
        )");
    }
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_encryption_consents_email ON cloud_encryption_consents(email)");
}

function ensure_cloud_encryption_consents_table(): void {
    $db = getDB();
    if (!$db) return;
    try {
        ensure_cloud_encryption_consents_schema_sqlite($db);
    } catch (Exception $e) {
        cloud_safe_error_log('ensure_cloud_encryption_consents_table: ' . $e->getMessage());
    }
}

// api.php - add after constants

// Active upload sessions storage
const UPLOAD_SESSIONS_DIR = __DIR__ . '/upload_sessions';
@mkdir(UPLOAD_SESSIONS_DIR, 0775, true);

function upload_sessions_dir() {
    static $dir = null;
    if ($dir !== null) return $dir;
    if (is_dir(UPLOAD_SESSIONS_DIR) && is_writable(UPLOAD_SESSIONS_DIR)) {
        return $dir = UPLOAD_SESSIONS_DIR;
    }
    $fallback = rtrim(sys_get_temp_dir(), '/\\') . '/appdashboard_cloud_upload_sessions_' . substr(sha1(__DIR__), 0, 12);
    @mkdir($fallback, 0775, true);
    return $dir = $fallback;
}

// Function to create upload session bound to path
function create_upload_session($target_path, $user) {
    $upload_id = bin2hex(random_bytes(16));
    $session_file = upload_sessions_dir() . '/' . $upload_id . '.json';
    
    $session_data = [
        'id' => $upload_id,
        'user' => $user,
        'target_path' => $target_path, // Full destination path
        'base_path' => dirname($target_path), // Destination folder
        'filename' => basename($target_path),
        'created' => time(),
        'last_activity' => time(),
        'active' => true
    ];
    
    if (@file_put_contents($session_file, json_encode($session_data, JSON_PRETTY_PRINT)) === false) {
        throw new RuntimeException('Cannot create upload session');
    }
    return $upload_id;
}

// Function to get upload session
function get_upload_session($upload_id) {
    $session_file = upload_sessions_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $upload_id) . '.json';
    if (file_exists($session_file)) {
        return json_decode(file_get_contents($session_file), true);
    }
    return null;
}

// Function to update session
function update_upload_session($upload_id) {
    $session_file = upload_sessions_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $upload_id) . '.json';
    if (file_exists($session_file)) {
        $data = json_decode(file_get_contents($session_file), true);
        $data['last_activity'] = time();
        @file_put_contents($session_file, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// Function to delete session
function delete_upload_session($upload_id) {
    $session_file = upload_sessions_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $upload_id) . '.json';
    @unlink($session_file);
}

// Cleanup old sessions (can call at startup)
function cleanup_old_upload_sessions() {
    $files = glob(upload_sessions_dir() . '/*.json');
    $now = time();
    foreach ($files as $file) {
        if ($now - filemtime($file) > 86400) { // 24 hours
            @unlink($file);
        }
    }
}
cleanup_old_upload_sessions();

// api.php - replace the existing function

function ensure_client_auth_tokens_table(): void {
    $db = getDB();
    if (!$db) return;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS client_auth_tokens (
            token_hash TEXT PRIMARY KEY,
            email TEXT NOT NULL,
            device_id TEXT NOT NULL DEFAULT '',
            device_name TEXT NOT NULL DEFAULT '',
            platform TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            last_used_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            expires_at INTEGER NOT NULL,
            revoked INTEGER NOT NULL DEFAULT 0
        )");
    } catch (Exception $e) {
        cloud_safe_error_log('ensure_client_auth_tokens_table: ' . $e->getMessage());
    }
}
function get_email_from_client_auth_header(): ?string {
    static $resolved = false;
    static $result = null;
    if ($resolved) {
        return $result;
    }
    $resolved = true;
    $raw = isset($_SERVER['HTTP_X_CLIENT_AUTH']) ? trim((string)$_SERVER['HTTP_X_CLIENT_AUTH']) : '';
    if ($raw === '') {
        return null;
    }
    $db = getDB();
    if (!$db) {
        return $result;
    }
    try {
        ensure_client_auth_tokens_table();
        $stmt = $db->prepare('SELECT email FROM client_auth_tokens WHERE token_hash = :h AND revoked = 0 AND expires_at > :now LIMIT 1');
        $stmt->bindValue(':h', hash('sha256', $raw), PDO::PARAM_STR);
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['email'])) {
            $upd = $db->prepare('UPDATE client_auth_tokens SET last_used_at = :t WHERE token_hash = :h');
            $upd->bindValue(':t', time(), PDO::PARAM_INT);
            $upd->bindValue(':h', hash('sha256', $raw), PDO::PARAM_STR);
            $upd->execute();
            $result = (string)$row['email'];
        }
    } catch (Exception $e) {
        cloud_safe_error_log('get_email_from_client_auth_header: ' . $e->getMessage());
    }
    return $result;
}

function validate_client_token_for_email(string $email, string $token): bool {
    $email = trim($email);
    $token = trim($token);
    if ($email === '' || $token === '') return false;
    $db = getDB();
    if (!$db) return false;
    try {
        ensure_client_auth_tokens_table();
        $tokenHash = hash('sha256', $token);
        $stmt = $db->prepare('SELECT email FROM client_auth_tokens WHERE email = :e AND token_hash = :h AND revoked = 0 AND expires_at > :now LIMIT 1');
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['email'])) return false;
        $upd = $db->prepare('UPDATE client_auth_tokens SET last_used_at = :t WHERE token_hash = :h');
        $upd->bindValue(':t', time(), PDO::PARAM_INT);
        $upd->bindValue(':h', $tokenHash, PDO::PARAM_STR);
        $upd->execute();
        return true;
    } catch (Exception $e) {
        cloud_safe_error_log('validate_client_token_for_email: ' . $e->getMessage());
        return false;
    }
}

// ------------------------------------------------------------------
//  Client Token Management - Generate/Revoke tokens for WebDAV clients
// ------------------------------------------------------------------
function generate_client_token(string $email, string $deviceId = '', string $deviceName = '', string $platform = '', int $expiresInDays = 365): array {
    $db = getDB();
    if (!$db) return ['ok' => false, 'error' => 'database unavailable'];
    
    try {
        ensure_client_auth_tokens_table();
        
        $token = 'dav_' . rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
        $tokenHash = hash('sha256', $token);
        $expiresAt = time() + ($expiresInDays * 86400);
        
        // Store token hash in database
        $stmt = $db->prepare('INSERT INTO client_auth_tokens (token_hash, email, device_id, device_name, platform, expires_at) VALUES (:h, :e, :d, :n, :p, :exp)');
        $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $stmt->bindValue(':d', $deviceId, PDO::PARAM_STR);
        $stmt->bindValue(':n', $deviceName, PDO::PARAM_STR);
        $stmt->bindValue(':p', $platform, PDO::PARAM_STR);
        $stmt->bindValue(':exp', $expiresAt, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'ok' => true,
            'token' => $token,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'expires_in_days' => $expiresInDays
        ];
    } catch (Exception $e) {
        cloud_safe_error_log('generate_client_token: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function revoke_all_client_tokens(string $email): array {
    $db = getDB();
    if (!$db) return ['ok' => false, 'error' => 'database unavailable'];
    
    try {
        ensure_client_auth_tokens_table();
        $stmt = $db->prepare('UPDATE client_auth_tokens SET revoked = 1 WHERE email = :e AND revoked = 0');
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        return ['ok' => true, 'message' => 'All tokens revoked'];
    } catch (Exception $e) {
        cloud_safe_error_log('revoke_all_client_tokens: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function revoke_client_token(string $email, string $tokenHash): array {
    $db = getDB();
    if (!$db) return ['ok' => false, 'error' => 'database unavailable'];
    if ($tokenHash === '') return ['ok' => false, 'error' => 'token hash required'];

    try {
        ensure_client_auth_tokens_table();
        $stmt = $db->prepare('UPDATE client_auth_tokens SET revoked = 1 WHERE email = :e AND token_hash = :h AND revoked = 0');
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
        $stmt->execute();

        if ($db->changes() < 1) {
            return ['ok' => false, 'error' => 'token not found'];
        }

        return ['ok' => true, 'message' => 'Token revoked'];
    } catch (Exception $e) {
        cloud_safe_error_log('revoke_client_token: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function get_active_tokens(string $email): array {
    $db = getDB();
    if (!$db) return ['ok' => false, 'error' => 'database unavailable'];
    
    try {
        ensure_client_auth_tokens_table();
        $stmt = $db->prepare('SELECT token_hash, device_id, device_name, platform, created_at, last_used_at, expires_at FROM client_auth_tokens WHERE email = :e AND revoked = 0 AND expires_at > :now ORDER BY created_at DESC');
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $result = $stmt->execute();
        
        $tokens = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $tokens[] = [
                'token_hash' => $row['token_hash'],
                'device_id' => $row['device_id'],
                'device_name' => $row['device_name'],
                'platform' => $row['platform'],
                'created_at' => $row['created_at'],
                'last_used_at' => $row['last_used_at'],
                'expires_at' => $row['expires_at']
            ];
        }
        
        return ['ok' => true, 'tokens' => $tokens];
    } catch (Exception $e) {
        cloud_safe_error_log('get_active_tokens: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function email_to_cloud_username(string $email): string {
    $username = str_replace(['@', '.'], '_', $email);
    $username = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username);
    return $username ?: 'guest';
}
// ------------------------------------------------------------------
//  Returns the username derived from the active PHP session.
//  If the session is not found in the DB → returns 'guest'.
//  Tries session_id() first, then PHPSESSID cookie as fallback
//  (needed when auth and api.php run under different session configs).
//  NO IP fallback. Unknown session = guest.
//  Desktop client: X-Client-Auth bearer token (same DB as index.php client_auth).
// ------------------------------------------------------------------
function get_current_user_from_sessions(): string {
    $ca = get_email_from_client_auth_header();
    if ($ca !== null && $ca !== '') {
        return email_to_cloud_username($ca);
    }
    $sessionEmail = $_SESSION['user']['email'] ?? $_SESSION['email'] ?? null;
    if (is_string($sessionEmail) && $sessionEmail !== '') {
        return email_to_cloud_username($sessionEmail);
    }
    $db = getDB();
    if (!$db) return 'guest';

    $tryIds = [];
    $session_id = session_id();
    if ($session_id) $tryIds[] = $session_id;
    if (isset($_COOKIE['PHPSESSID']) && $_COOKIE['PHPSESSID'] !== $session_id) {
        $tryIds[] = $_COOKIE['PHPSESSID'];
    }

    foreach ($tryIds as $sid) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid');
            $stmt->bindValue(':sid', $sid, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row    = $result->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['email'])) {
                $username = str_replace(['@', '.'], '_', $row['email']) ?? 'guest';
                $username = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username);
                return $username ?: 'guest';
            }
        } catch (Exception $e) {
            cloud_safe_error_log("get_current_user_from_sessions failed: " . $e->getMessage());
        }
    }

    return 'guest'; // No IP fallback
}

// ------------------------------------------------------------------
//  Returns the email of the currently authenticated user, or null.
//  Single source of truth — used by quota, folder state, share, etc.
//
//  IMPORTANT: api.php runs session_start() at the top, so session_id()
//  should return the browser's PHPSESSID. If it doesn't match anything
//  in active_sessions, the auth system may be using a different session
//  name or path — in that case we fall back to reading the cookie directly.
// ------------------------------------------------------------------
function get_session_email(): ?string {
    $ca = get_email_from_client_auth_header();
    if ($ca !== null && $ca !== '') {
        return $ca;
    }
    $sessionEmail = $_SESSION['user']['email'] ?? $_SESSION['email'] ?? null;
    if (is_string($sessionEmail) && $sessionEmail !== '') {
        return strtolower(trim($sessionEmail));
    }
    $db = getDB();
    if (!$db) return null;

    // Primary: use the session PHP already started
    $session_id = session_id();
    if ($session_id) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid');
            $stmt->bindValue(':sid', $session_id, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row    = $result->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['email'])) {
                return $row['email'];
            }
        } catch (Exception $e) {
            cloud_safe_error_log("get_session_email (session_id) failed: " . $e->getMessage());
        }
    }

    // Fallback: the auth system may store the session under PHPSESSID cookie
    // directly (e.g. when api.php and auth live under different session configs).
    if (isset($_COOKIE['PHPSESSID'])) {
        $cookie_sid = $_COOKIE['PHPSESSID'];
        if ($cookie_sid && $cookie_sid !== $session_id) {
            try {
                $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid');
                $stmt->bindValue(':sid', $cookie_sid, PDO::PARAM_STR);
                $result = $stmt->execute();
                $row    = $result->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['email'])) {
                    return $row['email'];
                }
            } catch (Exception $e) {
                cloud_safe_error_log("get_session_email (cookie) failed: " . $e->getMessage());
            }
        }
    }

    return null;
}
// Helper function to get user role by email
function getUserRoleByEmail($email): int {
    $db = getDB();
    if (!$db || !$email) {
        return 0;
    }
    
    try {
        $stmt = $db->prepare('SELECT role FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        
        if ($row && isset($row['role'])) {
            return (int)$row['role'];
        }
    } catch (Exception $e) {
        cloud_safe_error_log("Failed to get user role: " . $e->getMessage());
    }
    
    return 0;
}
function getUserPhotoByEmail(?string $email): string {
    if (!$email) return 'default_avatar.png';
    $db = getDB();
    if (!$db) return 'default_avatar.png';
    try {
        $stmt = $db->prepare('SELECT photo FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['photo'])) return (string)$row['photo'];
    } catch (Exception $e) {
        cloud_safe_error_log("Failed to get user photo: " . $e->getMessage());
    }
    return 'default_avatar.png';
}
function resolve_user_photo_url(string $photo): string {
    $photo = trim($photo);
    if ($photo === '' || $photo === 'default_avatar.png') return '/default_avatar.png';
    if (filter_var($photo, FILTER_VALIDATE_URL)) return $photo;
    if (str_starts_with($photo, '/')) return $photo;
    return '/' . ltrim($photo, '/');
}
// ------------------------------------------------------------------
//  Returns true only when the authenticated session belongs to a
//  role=2 (root) user. Tries session_id() first, then PHPSESSID cookie.
// ------------------------------------------------------------------
function isUserRoot(): bool {
    $db = getDB();
    if (!$db) return false;

    $tryIds = [];
    $session_id = session_id();
    if ($session_id) $tryIds[] = $session_id;
    if (isset($_COOKIE['PHPSESSID']) && $_COOKIE['PHPSESSID'] !== $session_id) {
        $tryIds[] = $_COOKIE['PHPSESSID'];
    }

    $sql = "SELECT u.role
            FROM   active_sessions s
            JOIN   users u ON s.email = u.email
            WHERE  s.session_id = :sid";

    foreach ($tryIds as $sid) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':sid', $sid, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row    = $result->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['role'])) {
                return (int)$row['role'] === 2;
            }
        } catch (Exception $e) {
            cloud_safe_error_log("isUserRoot query failed: " . $e->getMessage());
        }
    }

    return false;
}

require_once __DIR__ . '/includes/ai_config_store.php';

function loadAIConfig(): array
{
    return cloud_ai_load_config();
}

function saveAIConfig(array $newConfig): array
{
    if (!isUserRoot()) {
        return ['error' => 'Access denied. Only root users can change AI configuration.', 'code' => 403];
    }
    return cloud_ai_save_config($newConfig);
}

function json_out($data, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}


function safe_path($path) {
    // Allow paths with dots (e.g. .archive, .temp_files)
    // but prevent path traversal via ..
    
    // Normalize the path: remove multiple slashes
    $path = preg_replace('#/+#', '/', $path);
    
    // Split the path into components and handle ..
    $parts = explode('/', $path);
    $result = [];
    
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            // Skip empty and current directory entries
            continue;
        } else if ($part === '..') {
            // Block escaping the base directory
            // Simply ignore .. for safety
            continue;
        } else {
            // Allow all other paths (including .archive, .temp_files, etc.)
            $result[] = $part;
        }
    }
    
    // Rebuild the path
    $path = implode('/', $result);
    
    // Strip leading and trailing slashes
    $path = trim($path, '/');
    
    return $path;
}
function cloud_is_valid_root_key(string $root): bool
{
    if (in_array($root, ['main', 'archive', 'temp', 'documents', 'photos', 'pdf', 'trash', 'clientsync'], true)) {
        return true;
    }
    return cloud_parse_group_id($root) !== null;
}
function get_requested_root_key(): string {
    $raw = $_GET['root'] ?? $_POST['root'] ?? null;
    if ($raw !== null && $raw !== '') {
        $root = strtolower(trim((string)$raw));
        if (cloud_is_valid_root_key($root)) {
            $_SESSION['cloud_root_context'] = $root;
            return $root;
        }
    }
    $saved = strtolower((string)($_SESSION['cloud_root_context'] ?? 'main'));
    return cloud_is_valid_root_key($saved) ? $saved : 'main';
}
function get_root_folder_name(string $rootKey): string {
    static $map = ['archive' => '.archive', 'temp' => '.temp_files', 'documents' => '.documents', 'photos' => '.photos', 'pdf' => '.pdf', 'trash' => '.trash', 'clientsync' => '.clientsync'];
    return $map[$rootKey] ?? '';
}
function ensure_special_roots(string $base): void {
    foreach (['archive', 'temp', 'documents', 'photos', 'pdf', 'trash', 'clientsync'] as $rk) {
        $dir = $base . '/' . get_root_folder_name($rk);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
    }
}
function is_documents_root(?string $rootKey = null): bool {
    return ($rootKey ?? get_requested_root_key()) === 'documents';
}
function remap_clientsync_path(string &$rootKey, string &$path): void {
    if ($rootKey !== 'main') return;
    if ($path === 'clientsync') { $rootKey = 'clientsync'; $path = ''; return; }
    if (str_starts_with($path, 'clientsync/')) { $rootKey = 'clientsync'; $path = substr($path, strlen('clientsync/')); }
}
function should_hide_clientsync_virtual_entry(string $name, bool $isDir): bool {
    if (!$isDir && $name === '.directory') return true;
    if ($isDir) {
        $normalized = strtolower(str_replace(['.', '-', '_', ' '], '', $name));
        if ($normalized === 'trash1000') return true;
    }
    return false;
}
function should_hide_clientsync_virtual_path(string $relPath, bool $isDir): bool {
    $parts = explode('/', str_replace('\\', '/', trim($relPath, '/')));
    foreach ($parts as $index => $part) {
        if ($part === '') continue;
        $isLast = $index === array_key_last($parts);
        if (should_hide_clientsync_virtual_entry($part, $isLast ? $isDir : true)) return true;
    }
    return false;
}
function get_documents_secret(string $user): string {
    $salt = hash('sha256', __FILE__ . '::cloud-docs-v1');
    return hash('sha256', $salt . '|' . $user, true);
}
function encrypt_documents_content(string $plain, string $user): string {
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = random_bytes($ivLen);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', get_documents_secret($user), OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return $plain;
    return "CLOUDDOC1" . $iv . $cipher;
}
function decrypt_documents_content(string $raw, string $user): string {
    if (!str_starts_with($raw, "CLOUDDOC1")) return $raw;
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $body = substr($raw, 9);
    $iv = substr($body, 0, $ivLen);
    $cipher = substr($body, $ivLen);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', get_documents_secret($user), OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}
function ensureCloudSyncLogsSchema($db): void {
    if (!$db) return;
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_sync_logs (
        id BIGSERIAL PRIMARY KEY,
        email TEXT NOT NULL,
        ts INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        level TEXT NOT NULL DEFAULT 'INFO',
        root_key TEXT NOT NULL DEFAULT '',
        action TEXT NOT NULL DEFAULT '',
        path TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL DEFAULT '',
        FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_sync_logs_email_ts ON cloud_sync_logs(email, ts DESC)");
    cloud_sync_logs_cleanup_legacy_files($db);
}
function cloud_sync_logs_cleanup_legacy_files(AppDatabase $db): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $flag = (string)($db->querySingle("SELECT value FROM app_meta WHERE key = 'cloud_sync_logs_legacy_files_migrated' LIMIT 1") ?? '');
    if ($flag === '1') return;
    $base = appdata_app_base_dir('cloud');
    if (!is_dir($base)) {
        $db->exec("INSERT INTO app_meta(key, value) VALUES('cloud_sync_logs_legacy_files_migrated', '1') ON CONFLICT (key) DO UPDATE SET value=excluded.value");
        return;
    }
    foreach (scandir($base) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $logFile = $base . '/' . $entry . '/.sync_roots.log';
        if (!is_file($logFile)) continue;
        $email = resolve_cloud_user_email_from_legacy_name($db, $entry);
        if ($email) {
            $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $row = json_decode((string)$line, true);
                if (!is_array($row)) continue;
                cloud_sync_logs_insert($db, $email, (int)($row['ts'] ?? time()), strtoupper((string)($row['level'] ?? 'INFO')), (string)($row['root'] ?? ''), (string)($row['action'] ?? ''), (string)($row['path'] ?? ''), (string)($row['message'] ?? ''));
            }
        }
        @unlink($logFile);
    }
    $db->exec("INSERT INTO app_meta(key, value) VALUES('cloud_sync_logs_legacy_files_migrated', '1') ON CONFLICT (key) DO UPDATE SET value=excluded.value");
}
function cloud_sync_logs_insert(AppDatabase $db, string $email, int $ts, string $level, string $rootKey, string $actionName, string $path, string $message): void {
    $stmt = $db->prepare("INSERT INTO cloud_sync_logs (email, ts, level, root_key, action, path, message) VALUES (:e, :ts, :l, :r, :a, :p, :m)");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':ts', $ts, PDO::PARAM_INT);
    $stmt->bindValue(':l', $level, PDO::PARAM_STR);
    $stmt->bindValue(':r', $rootKey, PDO::PARAM_STR);
    $stmt->bindValue(':a', $actionName, PDO::PARAM_STR);
    $stmt->bindValue(':p', $path, PDO::PARAM_STR);
    $stmt->bindValue(':m', $message, PDO::PARAM_STR);
    $stmt->execute();
}
function cloud_sync_logs_prune(AppDatabase $db, string $email, int $max = 500): void {
    $stmt = $db->prepare("DELETE FROM cloud_sync_logs WHERE email = :e AND id NOT IN (SELECT id FROM cloud_sync_logs WHERE email = :e2 ORDER BY ts DESC, id DESC LIMIT :lim)");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':e2', $email, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $max, PDO::PARAM_INT);
    $stmt->execute();
}
function cloud_sync_logs_migrate_legacy(AppDatabase $db, string $email, string $user): void {
    $chk = $db->prepare("SELECT COUNT(*) AS c FROM cloud_sync_logs WHERE email = :e");
    $chk->bindValue(':e', $email, PDO::PARAM_STR);
    if ((int)($chk->execute()->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) > 0) return;
    ensureCloudUserSettingsSchema($db);
    $stmt = $db->prepare("SELECT sync_log_json FROM cloud_user_settings WHERE email = :e LIMIT 1");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    $logs = ($row && !empty($row['sync_log_json'])) ? (json_decode((string)$row['sync_log_json'], true) ?: []) : [];
    foreach ($logs as $entry) {
        if (!is_array($entry)) continue;
        cloud_sync_logs_insert($db, $email, (int)($entry['ts'] ?? time()), strtoupper((string)($entry['level'] ?? 'INFO')), (string)($entry['root'] ?? ''), (string)($entry['action'] ?? ''), (string)($entry['path'] ?? ''), (string)($entry['message'] ?? ''));
    }
    if ($logs) {
        $clr = $db->prepare("UPDATE cloud_user_settings SET sync_log_json = '[]' WHERE email = :e");
        $clr->bindValue(':e', $email, PDO::PARAM_STR);
        $clr->execute();
    }
    $logFile = user_root($user, 'main') . '/.sync_roots.log';
    if (!is_file($logFile)) return;
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $entry = json_decode((string)$line, true);
        if (!is_array($entry)) continue;
        cloud_sync_logs_insert($db, $email, (int)($entry['ts'] ?? time()), strtoupper((string)($entry['level'] ?? 'INFO')), (string)($entry['root'] ?? ''), (string)($entry['action'] ?? ''), (string)($entry['path'] ?? ''), (string)($entry['message'] ?? ''));
    }
    @unlink($logFile);
}
function append_sync_log(string $email, string $rootKey, string $actionName, string $path, string $level = 'INFO', string $message = '', array $busExtra = []): void {
    if ($email !== '' && is_file(__DIR__ . '/includes/cloud_sync_bus.php')) {
        require_once __DIR__ . '/includes/cloud_sync_bus.php';
        require_once __DIR__ . '/includes/cloud_sync_engine.php';
        cloud_sync_publish_router_event($email, $rootKey, $actionName, $path, $busExtra);
    }
    if ($rootKey === 'main') return;
    $db = getDB();
    if (!$db) return;
    ensureCloudSyncLogsSchema($db);
    cloud_sync_logs_insert($db, $email, time(), strtoupper($level), $rootKey, $actionName, $path, $message);
    cloud_sync_logs_prune($db, $email);
}

function user_root(string $user, ?string $rootKey = null): string {
  $rootKey = $rootKey ?? 'main';
  $groupId = cloud_parse_group_id($rootKey);
  if ($groupId) {
    try {
      return cloud_group_resolve_storage($groupId)['base'];
    } catch (Throwable $e) {
      return appdata_user_root_dir('cloud', $user, 'main', true) . '/.cloud_groups/_denied';
    }
  }
  return appdata_user_root_dir('cloud', $user, $rootKey, true);
}
function cloud_group_maybe_log(string $action, string $path): void
{
  $groupId = cloud_parse_group_id(get_requested_root_key());
  if (!$groupId) return;
  cloud_group_log_activity($groupId, $path, $action);
}

function trash_meta_file(string $user): string {
    return user_root($user, 'trash') . '/.trash_meta.json';
}

function load_trash_metadata(string $user): array {
    $file = trash_meta_file($user);
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function save_trash_metadata(string $user, array $meta): void {
    @file_put_contents(trash_meta_file($user), json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function trash_item_id(): string {
    try {
        return date('YmdHis') . '_' . bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        return date('YmdHis') . '_' . substr(sha1(uniqid('', true)), 0, 12);
    }
}

function remove_path_recursive(string $target): void {
    if (!file_exists($target)) return;
    if (is_dir($target)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($target);
        return;
    }
    @unlink($target);
}

function move_path_to_trash(string $user, string $sourceRootKey, string $sourcePath): array {
    if ($sourceRootKey === 'trash') {
        throw new RuntimeException('Item is already in trash');
    }
    $sourcePath = safe_path($sourcePath);
    if ($sourcePath === '') {
        throw new RuntimeException('path required');
    }

    $sourceBase = user_root($user, $sourceRootKey);
    $sourceFull = $sourceBase . '/' . $sourcePath;
    if (!file_exists($sourceFull)) {
        throw new RuntimeException('not found');
    }

    $trashBase = user_root($user, 'trash');
    $id = trash_item_id();
    $trashFull = $trashBase . '/' . $id;
    if (!@rename($sourceFull, $trashFull)) {
        throw new RuntimeException('move to trash failed');
    }

    $meta = load_trash_metadata($user);
    $meta[$id] = [
        'id' => $id,
        'name' => basename($sourcePath),
        'original_path' => $sourcePath,
        'original_root' => $sourceRootKey,
        'type' => is_dir($trashFull) ? 'dir' : 'file',
        'deleted_at' => time(),
    ];
    save_trash_metadata($user, $meta);

    return $meta[$id];
}

function dir_size(string $dir): int {
  $size = 0;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $f) $size += $f->getSize();
  return $size;
}

function is_text_mime(string $mime): bool {
  return str_starts_with($mime, 'text/') || in_array($mime, ['application/json','application/xml','application/javascript','application/x-sh','application/x-httpd-php', 'text/x-matlab']);
}
// Word to HTML conversion with dark theme support
function wordToHtml($filePath) {
    // Check file size (over 50MB)
    $fileSize = filesize($filePath);
    $maxSize = 10 * 1024 * 1024; // 50MB
    
    if ($fileSize > $maxSize) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    background: #1a1a1a; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    min-height: 100vh; 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                }
                .error-card { 
                    background: #2a2a2a; 
                    border-radius: 16px; 
                    padding: 48px; 
                    text-align: center; 
                    max-width: 400px; 
                    width: 90%; 
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
                    border: 1px solid #3a3a3a; 
                }
                .error-icon { 
                    width: 120px; 
                    height: 120px; 
                    margin: 0 auto 32px; 
                }
                h2 { 
                    color: #ffffff; 
                    font-size: 24px; 
                    font-weight: 500; 
                    margin-bottom: 16px; 
                }
                p { 
                    color: #9a9a9a; 
                    font-size: 16px; 
                    line-height: 1.5; 
                    margin-bottom: 32px; 
                }
                .file-size { 
                    background: #3a3a3a; 
                    padding: 8px 16px; 
                    border-radius: 20px; 
                    display: inline-block; 
                    color: #ff6b6b; 
                    font-family: monospace; 
                    font-size: 14px; 
                    margin-top: 10px; 
                }
                .back-btn { 
                    background: #3a3a3a; 
                    border: none; 
                    color: #ffffff; 
                    font-size: 16px; 
                    font-weight: 500; 
                    padding: 12px 32px; 
                    border-radius: 8px; 
                    cursor: pointer; 
                    transition: all 0.2s; 
                    display: inline-flex; 
                    align-items: center; 
                    gap: 8px; 
                }
                .back-btn:hover { 
                    background: #4a4a4a; 
                }
                .back-btn svg { 
                    width: 20px; 
                    height: 20px; 
                    fill: none; 
                    stroke: #ffffff; 
                    stroke-width: 2; 
                }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="error-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ff6b6b" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <circle cx="12" cy="16" r="1"/>
                    </svg>
                </div>
                <h2>File too large</h2>
                <p>The document exceeds maximum preview size</p>
                <div class="file-size">' . round($fileSize / (1024 * 1024), 1) . ' MB / 50 MB</div>
                <button class="back-btn" onclick="window.close()">
                    <svg viewBox="0 0 24 24">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Close
                </button>
            </div>
        </body>
        </html>';
    }
    
    try {
        $phpWord = WordIOFactory::load($filePath);
        
        $html = '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Document preview</title>
            <style>
                /* Styles for scrollbars */
                ::-webkit-scrollbar {
                    width: 12px;
                    height: 12px;
                }
                ::-webkit-scrollbar-track {
                    background: #2a2a2a;
                    border-radius: 6px;
                }
                ::-webkit-scrollbar-thumb {
                    background: #555;
                    border-radius: 6px;
                    border: 2px solid #2a2a2a;
                }
                ::-webkit-scrollbar-thumb:hover {
                    background: #666;
                }
                
                /* For Firefox */
                * {
                    scrollbar-width: thin;
                    scrollbar-color: #555 #2a2a2a;
                }
                
                /* Base dark theme */
                body {
                    background: #1a1a1a !important;
                    color: #ffffff !important;
                    margin: 0;
                    padding: 40px;
                    font-family: "Times New Roman", Times, serif;
                    line-height: 1.5;
                    min-height: 100vh;
                    box-sizing: border-box;
                }
                
                .word-container {
                    max-width: 21cm;
                    min-height: 29.7cm;
                    margin: 0 auto;
                    padding: 2.5cm;
                    background: #2a2a2a;
                    box-shadow: 0 0 20px rgba(0,0,0,0.3);
                    border: 1px solid #444;
                    box-sizing: border-box;
                    position: relative;
                }
                
                /* Base styles for all text - white by default */
                .word-container * {
                    color: #ffffff !important;
                }
                
                /* Styles for Word sections */
                .WordSection1, .WordSection2, .WordSection3 {
                    width: 100% !important;
                    max-width: 100% !important;
                    background: #2a2a2a !important;
                }
                
                /* Headings */
                h1, h2, h3, h4, h5, h6 {
                    color: #ffffff !important;
                    margin-top: 1.2em !important;
                    margin-bottom: 0.6em !important;
                    font-weight: bold !important;
                    page-break-after: avoid !important;
                }
                
                h1 { font-size: 18pt !important; }
                h2 { font-size: 16pt !important; }
                h3 { font-size: 14pt !important; }
                h4 { font-size: 12pt !important; }
                h5 { font-size: 11pt !important; }
                h6 { font-size: 10pt !important; }
                
                /* Paragraphs */
                p {
                    margin: 0.8em 0 !important;
                    text-align: left !important;
                    line-height: 1.5 !important;
                    color: #ffffff !important;
                    font-size: 11pt !important;
                }
                
                /* Lists */
                ul, ol {
                    margin: 0.8em 0 !important;
                    padding-left: 2.5em !important;
                    color: #ffffff !important;
                }
                
                li {
                    margin: 0.3em 0 !important;
                    color: #ffffff !important;
                    font-size: 11pt !important;
                }
                
                /* TABLES - ENHANCED STYLES */
                table {
                    width: 100% !important;
                    max-width: 100% !important;
                    border-collapse: collapse !important;
                    margin: 1em 0 !important;
                    background: #2a2a2a !important;
                    border: 2px solid #555 !important;
                    table-layout: auto !important;
                }
                
                td, th {
                    border: 1px solid #666 !important;
                    padding: 8px 12px !important;
                    text-align: left !important;
                    vertical-align: top !important;
                    background: #2a2a2a !important;
                    color: #ffffff !important;
                    font-size: 10pt !important;
                    min-width: 50px !important;
                    word-wrap: break-word !important;
                    overflow-wrap: break-word !important;
                }
                
                th {
                    background: #3a3a3a !important;
                    font-weight: bold !important;
                    color: #ffffff !important;
                    border-bottom: 2px solid #777 !important;
                }
                
                /* Word-specific table styles */
                .MsoNormalTable {
                    width: 100% !important;
                    border-collapse: collapse !important;
                    margin: 1em 0 !important;
                }
                
                /* Links */
                a {
                    color: #4dabf7 !important;
                    text-decoration: underline !important;
                }
                
                a:hover {
                    color: #74c0fc !important;
                }
                
                /* Images */
                img {
                    max-width: 100% !important;
                    height: auto !important;
                    display: block !important;
                    margin: 1em auto !important;
                    border: 1px solid #555 !important;
                    background: #1a1a1a !important;
                    padding: 4px;
                    box-sizing: border-box;
                }
                
                /* Code blocks and quotes */
                blockquote {
                    margin: 1em 2em !important;
                    padding: 1em !important;
                    border-left: 4px solid #555 !important;
                    background: #2a2a2a !important;
                    color: #cccccc !important;
                    font-style: italic !important;
                }
                
                pre, code {
                    background: #1a1a1a !important;
                    color: #e0e0e0 !important;
                    padding: 8px !important;
                    border-radius: 4px !important;
                    border: 1px solid #444 !important;
                    font-family: "Courier New", monospace !important;
                    font-size: 10pt !important;
                }
                
                /* Alignment */
                .align-center { text-align: center !important; }
                .align-right { text-align: right !important; }
                .align-left { text-align: left !important; }
                .align-justify { text-align: justify !important; }
                
                /* Word-specific styles */
                .MsoNormal {
                    margin: 0.8em 0 !important;
                    line-height: 1.5 !important;
                    color: #ffffff !important;
                }
                
                .MsoTitle {
                    font-size: 20pt !important;
                    font-weight: bold !important;
                    text-align: center !important;
                    margin: 2em 0 !important;
                    color: #ffffff !important;
                }
                
                .MsoHeader, .MsoFooter {
                    font-size: 9pt !important;
                    color: #999 !important;
                }
                
                /* Subscript and superscript marks */
                sup, sub {
                    font-size: 0.8em !important;
                    line-height: 0 !important;
                    color: #ffffff !important;
                }
                
                sup { vertical-align: super !important; }
                sub { vertical-align: sub !important; }
                
                /* Text highlight */
                mark {
                    background-color: #ffeb3b !important;
                    color: #000 !important;
                    padding: 0.1em 0.2em !important;
                }
                
                /* Lines */
                hr {
                    border: none !important;
                    border-top: 1px solid #555 !important;
                    margin: 2em 0 !important;
                }
                
                /* Responsiveness */
                @media (max-width: 768px) {
                    body {
                        padding: 20px !important;
                    }
                    
                    .word-container {
                        padding: 1.5cm !important;
                        margin: 0 !important;
                        box-shadow: none !important;
                        border: none !important;
                    }
                    
                    table {
                        font-size: 9pt !important;
                    }
                    
                    td, th {
                        padding: 6px 8px !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="word-container">';
        
        // Use a custom HTML Writer
        $htmlWriter = WordIOFactory::createWriter($phpWord, 'HTML');
        $htmlWriter->setUseDiskCaching(true);
        
        ob_start();
        $htmlWriter->save('php://output');
        $wordHtml = ob_get_clean();
        
        // Process HTML for better rendering
        $wordHtml = processWordHtml($wordHtml);
        
        $html .= $wordHtml;
        $html .= '</div></body></html>';
        
        return $html;
        
    } catch (Exception $e) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    background: #1a1a1a; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    min-height: 100vh; 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                }
                .error-card { 
                    background: #2a2a2a; 
                    border-radius: 16px; 
                    padding: 48px; 
                    text-align: center; 
                    max-width: 400px; 
                    width: 90%; 
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
                    border: 1px solid #3a3a3a; 
                }
                .error-icon { 
                    width: 120px; 
                    height: 120px; 
                    margin: 0 auto 32px; 
                }
                h2 { 
                    color: #ffffff; 
                    font-size: 24px; 
                    font-weight: 500; 
                    margin-bottom: 16px; 
                }
                p { 
                    color: #9a9a9a; 
                    font-size: 16px; 
                    line-height: 1.5; 
                    margin-bottom: 32px; 
                }
                .back-btn { 
                    background: #3a3a3a; 
                    border: none; 
                    color: #ffffff; 
                    font-size: 16px; 
                    font-weight: 500; 
                    padding: 12px 32px; 
                    border-radius: 8px; 
                    cursor: pointer; 
                    transition: all 0.2s; 
                    display: inline-flex; 
                    align-items: center; 
                    gap: 8px; 
                }
                .back-btn:hover { 
                    background: #4a4a4a; 
                }
                .back-btn svg { 
                    width: 20px; 
                    height: 20px; 
                    fill: none; 
                    stroke: #ffffff; 
                    stroke-width: 2; 
                }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="error-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ff6b6b" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <circle cx="12" cy="16" r="1"/>
                    </svg>
                </div>
                <h2>Cannot open document</h2>
                <p>The file may be corrupted or in an unsupported format</p>
                <button class="back-btn" onclick="window.close()">
                    <svg viewBox="0 0 24 24">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Close
                </button>
            </div>
        </body>
        </html>';
    }
}

// Improved Word HTML processing function
function processWordHtml($html) {
    // Remove unnecessary tags
    $html = preg_replace('/<(!DOCTYPE|xml|meta|link).*?>/i', '', $html);
    
    // Fix encoding
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    
    // Process styles for dark theme
    $html = processWordStylesForDarkTheme($html);
    
    // Process tables
    $html = processWordTables($html);
    
    // Process images
    $html = processWordImages($html);
    
    // Process lists
    $html = processWordLists($html);
    
    return $html;
}

// Style processing for dark theme
function processWordStylesForDarkTheme($html) {
    // Keep colored text, but change black text to white
    $html = preg_replace_callback('/style="[^"]*color:\s*([^;"]+)[^"]*"/i', function($matches) {
        $color = strtolower(trim($matches[1]));
        
        // If the color is black or very dark - change to white
        if ($color === '#000000' || $color === 'black' || $color === '#000' || 
            $color === 'rgb(0,0,0)' || $color === 'windowtext') {
            return 'style="color: #ffffff !important;"';
        }
        
        // If the color is close to black - also change to white
        if (preg_match('/^#([0-9a-f]{6})$/i', $color, $hexMatches)) {
            $hex = $hexMatches[1];
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            
            // If very dark color (less than 20% brightness)
            if ($r < 51 && $g < 51 && $b < 51) {
                return 'style="color: #ffffff !important;"';
            }
        }
        
        // Keep the original color for other colors
        return 'style="color: ' . $matches[1] . ' !important;"';
    }, $html);
    
    // Process background - make light backgrounds dark
    $html = preg_replace_callback('/style="[^"]*background-color:\s*([^;"]+)[^"]*"/i', function($matches) {
        $bgColor = strtolower(trim($matches[1]));
        
        // If the background is white or very light - change to dark
        if ($bgColor === '#ffffff' || $bgColor === 'white' || $bgColor === '#fff' || 
            $bgColor === 'rgb(255,255,255)' || $bgColor === 'transparent') {
            return 'style="background-color: #2a2a2a !important;"';
        }
        
        // If the background is close to white - also change to dark
        if (preg_match('/^#([0-9a-f]{6})$/i', $bgColor, $hexMatches)) {
            $hex = $hexMatches[1];
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            
            // If very light background (more than 80% brightness)
            if ($r > 204 && $g > 204 && $b > 204) {
                return 'style="background-color: #2a2a2a !important;"';
            }
        }
        
        // Keep the original color for other backgrounds
        return 'style="background-color: ' . $matches[1] . ' !important;"';
    }, $html);
    
    // Process other styles
    $styles = [
        // Alignment
        '/style="[^"]*text-align:\s*center[^"]*"/i' => 'class="align-center"',
        '/style="[^"]*text-align:\s*right[^"]*"/i' => 'class="align-right"',
        '/style="[^"]*text-align:\s*left[^"]*"/i' => 'class="align-left"',
        '/style="[^"]*text-align:\s*justify[^"]*"/i' => 'class="align-justify"',
        
        // Bold text
        '/style="[^"]*font-weight:\s*(bold|700|800|900)[^"]*"/i' => 'style="font-weight: bold !important;"',
        
        // Italic
        '/style="[^"]*font-style:\s*italic[^"]*"/i' => 'style="font-style: italic !important;"',
        
        // Underline
        '/style="[^"]*text-decoration:\s*underline[^"]*"/i' => 'style="text-decoration: underline !important;"',
        
        // Font size
        '/style="[^"]*font-size:\s*(\d+\.?\d*)pt[^"]*"/i' => 'style="font-size: $1pt !important;"',
        '/style="[^"]*font-size:\s*(\d+\.?\d*)px[^"]*"/i' => 'style="font-size: $1px !important;"',
    ];
    
    foreach ($styles as $pattern => $replacement) {
        $html = preg_replace($pattern, $replacement, $html);
    }
    
    return $html;
}

// Improved table processing
function processWordTables($html) {
    // Make sure all tables have proper styles
    $html = preg_replace_callback('/<table[^>]*>/i', function($matches) {
        $table = $matches[0];
        
        // Add border and cellspacing if missing
        if (strpos($table, 'border=') === false) {
            $table = str_replace('<table', '<table border="1"', $table);
        }
        
        if (strpos($table, 'cellspacing=') === false) {
            $table = str_replace('<table', '<table cellspacing="0"', $table);
        }
        
        if (strpos($table, 'cellpadding=') === false) {
            $table = str_replace('<table', '<table cellpadding="5"', $table);
        }
        
        // Add class if missing
        if (strpos($table, 'class=') === false) {
            $table = str_replace('<table', '<table class="word-table"', $table);
        }
        
        return $table;
    }, $html);
    
    // Process table cells
    $html = preg_replace_callback('/<(td|th)[^>]*>/i', function($matches) {
        $cell = $matches[0];
        $tag = $matches[1];
        
        // Add styles for cells
        if (strpos($cell, 'style=') === false) {
            $cell = str_replace('<' . $tag, '<' . $tag . ' style="border: 1px solid #666; padding: 8px; background: #2a2a2a; color: #ffffff;"', $cell);
        }
        
        return $cell;
    }, $html);
    
    return $html;
}

// Image processing (leave unchanged)
function processWordImages($html) {
    $html = preg_replace_callback('/<img[^>]+>/i', function($matches) {
        $img = $matches[0];
        
        if (strpos($img, 'class=') === false) {
            $img = str_replace('<img', '<img class="word-image"', $img);
        }
        
        if (strpos($img, 'alt=') === false) {
            $img = str_replace('<img', '<img alt="Image"', $img);
        }
        
        return $img;
    }, $html);
    
    return $html;
}

// List processing (leave unchanged)
function processWordLists($html) {
    $html = preg_replace_callback('/<(ul|ol)[^>]*>/i', function($matches) {
        $list = $matches[0];
        
        if (strpos($list, 'class=') === false) {
            $list = str_replace('<' . $matches[1], '<' . $matches[1] . ' class="word-list"', $list);
        }
        
        return $list;
    }, $html);
    
    return $html;
}

function excelToHtml($filePath) {
    // Check file size (over 50MB)
    $fileSize = filesize($filePath);
    $maxSize = 1 * 1024 * 1024; // 50MB
    
    if ($fileSize > $maxSize) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    background: #989898ff; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    min-height: 100vh; 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                }
                .error-card { 
                    background: #2a2a2a; 
                    border-radius: 16px; 
                    padding: 48px; 
                    text-align: center; 
                    max-width: 400px; 
                    width: 90%; 
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
                    border: 1px solid #3a3a3a; 
                }
                .error-icon { 
                    width: 120px; 
                    height: 120px; 
                    margin: 0 auto 32px; 
                }
                h2 { 
                    color: #ffffff; 
                    font-size: 24px; 
                    font-weight: 500; 
                    margin-bottom: 16px; 
                }
                p { 
                    color: #9a9a9a; 
                    font-size: 16px; 
                    line-height: 1.5; 
                    margin-bottom: 32px; 
                }
                .file-size { 
                    background: #3a3a3a; 
                    padding: 8px 16px; 
                    border-radius: 20px; 
                    display: inline-block; 
                    color: #ff6b6b; 
                    font-family: monospace; 
                    font-size: 14px; 
                    margin-top: 10px; 
                }
                .back-btn { 
                    background: #3a3a3a; 
                    border: none; 
                    color: #ffffff; 
                    font-size: 16px; 
                    font-weight: 500; 
                    padding: 12px 32px; 
                    border-radius: 8px; 
                    cursor: pointer; 
                    transition: all 0.2s; 
                    display: inline-flex; 
                    align-items: center; 
                    gap: 8px; 
                }
                .back-btn:hover { 
                    background: #4a4a4a; 
                }
                .back-btn svg { 
                    width: 20px; 
                    height: 20px; 
                    fill: none; 
                    stroke: #ffffff; 
                    stroke-width: 2; 
                }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="error-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ff6b6b" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <circle cx="12" cy="16" r="1"/>
                    </svg>
                </div>
                <h2>File too large</h2>
                <p>The spreadsheet exceeds maximum preview size</p>
                <div class="file-size">' . round($fileSize / (1024 * 1024), 1) . ' MB / 1 MB</div>
                <button class="back-btn" onclick="window.close()">
                    <svg viewBox="0 0 24 24">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Close
                </button>
            </div>
        </body>
        </html>';
    }
    
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheetNames = $spreadsheet->getSheetNames();
        $sheetIndex = isset($_GET['sheet']) ? intval($_GET['sheet']) : 0;

        if (!isset($sheetNames[$sheetIndex])) {
            $sheetIndex = 0;
        }

        $currentPath = $_GET['path'] ?? '';

        $html = '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Spreadsheet preview</title>
            <style>
                /* Styles for scrollbars */
                ::-webkit-scrollbar {
                    width: 12px;
                    height: 12px;
                }
                ::-webkit-scrollbar-track {
                    background: #989898ff;
                    border-radius: 6px;
                }
                ::-webkit-scrollbar-thumb {
                    background: #555;
                    border-radius: 6px;
                    border: 2px solid #989898ff;
                }
                ::-webkit-scrollbar-thumb:hover {
                    background: #666;
                }
                
                /* For Firefox */
                * {
                    scrollbar-width: thin;
                    scrollbar-color: #555 #989898ff;
                }
                
                body, html {
                    margin: 0;
                    padding: 0;
                    font-family: Arial, sans-serif;
                    background: #989898ff;
                    color: #ffffff;
                    height: 100%;
                    overflow: hidden;
                }
                
                .container {
                    padding: 20px;
                    height: 100vh;
                    display: flex;
                    flex-direction: column;
                    background: #989898ff;
                }
                
                .header {
                    background: #989898ff;
                    padding: 15px 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    border: 1px solid #444;
                }
                
                .header h4 {
                    margin: 0;
                    color: #ffffff;
                    font-size: 18px;
                }
                
                .sheet-nav {
                    margin-bottom: 20px;
                }
                .sheet-nav .nav-link {
                    border: 1px solid #444;
                    margin: 0 5px 5px 0;
                    display: inline-block;
                    padding: 8px 16px;
                    text-decoration: none;
                    border-radius: 4px;
                    color: #ffffff;
                    background: #989898ff;
                    transition: all 0.2s;
                }
                .sheet-nav .nav-link:hover {
                    background: #3a3a3a;
                    border-color: #555;
                }
                .sheet-nav .nav-link.active {
                    background: #0d6efd;
                    color: white;
                    border-color: #0d6efd;
                }
                
                .table-container {
                    flex: 1;
                    border: 1px solid #444;
                    border-radius: 8px;
                    overflow: auto;
                    background: #989898ff;
                }
                
                table {
                    border-collapse: collapse;
                    table-layout: fixed;
                    background: #989898ff;
                    width: auto;
                }
                
                td {
                    padding: 4px 8px;
                    vertical-align: middle;
                    border: 1px solid #444;
                    overflow: hidden;
                    position: relative;
                    background: #989898ff;
                    color: #ffffff;
                    font-size: 11px;
                    line-height: 1.2;
                    min-width: 50px;
                    max-width: 300px;
                    white-space: nowrap;
                }
                
                /* Styles for unfilled cells */
                td.default-bg {
                    background: #ffffffff !important;
                }
                
                .content-wrapper {
                    display: block;
                    width: 100%;
                    height: 100%;
                }
                
                /* Styles for rotated text */
                .vertical-text {
                    writing-mode: vertical-rl;
                    transform: rotate(180deg);
                    white-space: nowrap;
                }
                
                .rotated-text {
                    display: inline-block;
                    transform-origin: center;
                    white-space: nowrap;
                }
                
                /* Styles for text wrapping */
                .wrapped-text {
                    white-space: normal !important;
                    word-wrap: break-word;
                    word-break: break-word;
                }
            </style>
        </head>
        <body>
            <div class="container">
                
                
                <nav class="sheet-nav">';

        // Sheet navigation
        foreach ($sheetNames as $index => $name) {
            $activeClass = ($index === $sheetIndex) ? 'active' : '';
            $html .= '<a href="?action=view_excel&path=' . urlencode($currentPath) . '&sheet=' . $index . '" class="nav-link ' . $activeClass . '">' . htmlspecialchars($name) . '</a>';
        }

        $html .= '</nav>
                <div class="table-container">';

        $sheet = $spreadsheet->getSheet($sheetIndex);
        
        // Merged cell map - FIXED LOGIC
        $mergedCells = $sheet->getMergeCells();
        $mergedCellMap = [];
        
        foreach ($mergedCells as $range) {
            try {
                // Safe retrieval of range boundaries
                $rangeBoundaries = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($range);
                
                if (is_array($rangeBoundaries) && count($rangeBoundaries) === 2) {
                    $startCol = $rangeBoundaries[0][0]; // Start column (number)
                    $startRow = $rangeBoundaries[0][1]; // Start row (number)
                    $endCol = $rangeBoundaries[1][0];   // End column (number)
                    $endRow = $rangeBoundaries[1][1];   // End row (number)
                    
                    // Compute merge dimensions
                    $colspan = ($endCol - $startCol) + 1;
                    $rowspan = ($endRow - $startRow) + 1;
                    
                    // Fill the merged cell map
                    for ($row = $startRow; $row <= $endRow; $row++) {
                        for ($col = $startCol; $col <= $endCol; $col++) {
                            if ($row == $startRow && $col == $startCol) {
                                // Main merge cell
                                $mergedCellMap[$row][$col] = [
                                    'colspan' => $colspan, 
                                    'rowspan' => $rowspan
                                ];
                            } else {
                                // Secondary merge cells (should be hidden)
                                $mergedCellMap[$row][$col] = ['merged' => true];
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Skip problematic ranges
                continue;
            }
        }

        // Helper functions
        if (!function_exists('excel_argbToHex')) {
            function excel_argbToHex($argb) {
                if (empty($argb) || strlen($argb) < 6 || $argb === '00000000') {
                    return null;
                }
                return '#' . substr($argb, -6);
            }
        }

        if (!function_exists('excel_borderStyleToCss')) {
            function excel_borderStyleToCss($borderStyle) {
                if ($borderStyle === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE) {
                    return 'none';
                }
                return 'solid'; // Simplify for reliability
            }
        }
        
        if (!function_exists('excel_getBorderWidth')) {
            function excel_getBorderWidth($borderStyle) {
                if ($borderStyle === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK) {
                    return '2px';
                }
                if ($borderStyle === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM) {
                    return '1.5px';
                }
                return '1px';
            }
        }

        $html .= '<table>';
        
        // Columns - SAFE SIZE RETRIEVAL
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        $highestRow = $sheet->getHighestRow();
        
        $html .= '<colgroup>';
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            try {
                $colString = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $columnDimension = $sheet->getColumnDimension($colString);
                $width = $columnDimension->getWidth();
                
                if ($width > 0 && $width !== -1) {
                    $html .= '<col style="width: ' . ($width * 7.5) . 'px;">';
                } else {
                    $html .= '<col style="width: 64px;">';
                }
            } catch (Exception $e) {
                $html .= '<col style="width: 64px;">';
            }
        }
        $html .= '</colgroup>';

        // Rows - SAFE PROCESSING
        for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
            try {
                $rowDimension = $sheet->getRowDimension($rowIndex);
                $rowHeight = $rowDimension->getRowHeight();
                
                $html .= '<tr' . ($rowHeight > 0 && $rowHeight !== -1 ? ' style="height: ' . $rowHeight . 'pt;"' : '') . '>';
                
                for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                    // Skip merged cells
                    if (isset($mergedCellMap[$rowIndex][$colIndex]['merged'])) {
                        continue;
                    }

                    try {
                        $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
                        $cell = $sheet->getCell($cellCoordinate);
                        $style = $sheet->getStyle($cellCoordinate);
                        
                        $cellCss = '';
                        $wrapperCss = '';
                        $wrapperClass = 'content-wrapper';
                        $cellClass = 'default-bg';

                        // Fill
                        $fill = $style->getFill();
                        if ($fill->getFillType() === \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID) {
                            $fillColor = $fill->getStartColor()->getARGB();
                            if ($hexColor = excel_argbToHex($fillColor)) {
                                $cellCss .= 'background-color: ' . $hexColor . ';';
                                $cellClass = ''; // Remove the default class if there is a fill
                            }
                        }

                        // Font
                        $font = $style->getFont();
                        $fontColor = $font->getColor()->getARGB();
                        if ($hexFontColor = excel_argbToHex($fontColor)) {
                            $cellCss .= 'color: ' . $hexFontColor . ';';
                        }
                        
                        $fontName = $font->getName();
                        if (!empty($fontName)) {
                            $cellCss .= 'font-family: \'' . $fontName . '\', sans-serif;';
                        }
                        
                        $fontSize = $font->getSize();
                        if ($fontSize > 0) {
                            $cellCss .= 'font-size: ' . $fontSize . 'pt;';
                        }
                        
                        if ($font->getBold()) {
                            $cellCss .= 'font-weight: bold;';
                        }
                        if ($font->getItalic()) {
                            $cellCss .= 'font-style: italic;';
                        }
                        if ($font->getUnderline() !== \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_NONE) {
                            $cellCss .= 'text-decoration: underline;';
                        }
                        if ($font->getStrikethrough()) {
                            $cellCss .= 'text-decoration: line-through;';
                        }

                        // Borders - SIMPLIFIED PROCESSING
                        $borders = $style->getBorders();
                        foreach (['top', 'right', 'bottom', 'left'] as $side) {
                            $border = $borders->{'get' . ucfirst($side)}();
                            $borderStyle = $border->getBorderStyle();
                            
                            if ($borderStyle !== \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE) {
                                $borderColor = excel_argbToHex($border->getColor()->getARGB()) ?? '#555';
                                $cellCss .= 'border-' . $side . ': ' . excel_getBorderWidth($borderStyle) . ' ' . 
                                           excel_borderStyleToCss($borderStyle) . ' ' . $borderColor . ';';
                            }
                        }

                        // Alignment
                        $alignment = $style->getAlignment();
                        $horizontal = $alignment->getHorizontal();
                        if ($horizontal && $horizontal !== 'general') {
                            $cellCss .= 'text-align: ' . strtolower($horizontal) . ';';
                        }
                        
                        $vertical = $alignment->getVertical();
                        if ($vertical) {
                            $cellCss .= 'vertical-align: ' . strtolower($vertical) . ';';
                        }

                        // Text wrapping
                        if ($alignment->getWrapText()) {
                            $wrapperClass .= ' wrapped-text';
                            $cellCss = str_replace('white-space: nowrap;', '', $cellCss);
                        }

                        // Text rotation
                        $rotation = $alignment->getTextRotation();
                        if ($rotation !== null && $rotation !== 0) {
                            if ($rotation == 255) {
                                $wrapperClass .= ' vertical-text';
                            } else {
                                $wrapperClass .= ' rotated-text';
                                $wrapperCss .= 'transform: rotate(-' . $rotation . 'deg);';
                            }
                        }
                        
                        // Merge attributes
                        $extraAttrs = '';
                        if (isset($mergedCellMap[$rowIndex][$colIndex])) {
                            $mergeData = $mergedCellMap[$rowIndex][$colIndex];
                            $extraAttrs .= ' colspan="' . $mergeData['colspan'] . '" rowspan="' . $mergeData['rowspan'] . '"';
                        }

                        // Cell value
                        $value = $cell->getCalculatedValue(); // Use the calculated value for formulas
                        $displayValue = '';
                        
                        if ($value !== null) {
                            if (is_numeric($value) && !is_string($value)) {
                                $displayValue = (string)$value;
                            } else {
                                $displayValue = (string)$value;
                            }
                        }
                        
                        $html .= '<td class="' . $cellClass . '" ' . $extraAttrs . ' style="' . $cellCss . '" title="' . htmlspecialchars($displayValue) . '">';
                        $html .= '<div class="' . $wrapperClass . '" style="' . $wrapperCss . '">' . htmlspecialchars($displayValue) . '</div>';
                        $html .= '</td>';
                        
                    } catch (Exception $cellException) {
                        // Skip problematic cells
                        $html .= '<td class="default-bg" title="Cell error">#ERROR</td>';
                    }
                }
                $html .= '</tr>';
                
            } catch (Exception $rowException) {
                // Skip problematic rows
                continue;
            }
        }
        
        $html .= '</table></div></div></body></html>';

        return $html;

    } catch (Exception $e) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    background: #989898ff; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    min-height: 100vh; 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                }
                .error-card { 
                    background: #2a2a2a; 
                    border-radius: 16px; 
                    padding: 48px; 
                    text-align: center; 
                    max-width: 400px; 
                    width: 90%; 
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
                    border: 1px solid #3a3a3a; 
                }
                .error-icon { 
                    width: 120px; 
                    height: 120px; 
                    margin: 0 auto 32px; 
                }
                h2 { 
                    color: #ffffff; 
                    font-size: 24px; 
                    font-weight: 500; 
                    margin-bottom: 16px; 
                }
                p { 
                    color: #9a9a9a; 
                    font-size: 16px; 
                    line-height: 1.5; 
                    margin-bottom: 32px; 
                }
                .back-btn { 
                    background: #3a3a3a; 
                    border: none; 
                    color: #ffffff; 
                    font-size: 16px; 
                    font-weight: 500; 
                    padding: 12px 32px; 
                    border-radius: 8px; 
                    cursor: pointer; 
                    transition: all 0.2s; 
                    display: inline-flex; 
                    align-items: center; 
                    gap: 8px; 
                }
                .back-btn:hover { 
                    background: #4a4a4a; 
                }
                .back-btn svg { 
                    width: 20px; 
                    height: 20px; 
                    fill: none; 
                    stroke: #ffffff; 
                    stroke-width: 2; 
                }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="error-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ff6b6b" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <circle cx="12" cy="16" r="1"/>
                    </svg>
                </div>
                <h2>Cannot open spreadsheet</h2>
                <p>The file may be corrupted or in an unsupported format</p>
                <button class="back-btn" onclick="window.close()">
                    <svg viewBox="0 0 24 24">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Close
                </button>
            </div>
        </body>
        </html>';
    }
}

function getFileProperties($fullPath, $relativePath) {
    $stat = stat($fullPath);
    $fileInfo = pathinfo($fullPath);
    $mimeType = mime_content_type($fullPath);
    
    $properties = [
        'type' => $mimeType,
        'size' => $stat['size'],
        'created' => date('c', $stat['ctime']),
        'modified' => date('c', $stat['mtime']),
        'accessed' => date('c', $stat['atime']),
        'attributes' => getFileAttributes($fullPath)
    ];
    
    // Get additional properties based on file type
    $ext = strtolower($fileInfo['extension'] ?? '');
    
    if (strpos($mimeType, 'image/') === 0) {
        $properties['details'] = getImageProperties($fullPath);
    } elseif (strpos($mimeType, 'audio/') === 0) {
        $properties['details'] = getAudioProperties($fullPath);
    } elseif (strpos($mimeType, 'video/') === 0) {
        $properties['details'] = getVideoProperties($fullPath);
    } elseif (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'rtf'])) {
        $properties['details'] = getDocumentProperties($fullPath);
    }
    
    // Get security info (Windows)
    $properties['security'] = getSecurityProperties($fullPath);
    
    // //cloud_safe_error_log($properties);
    
    return $properties;
}

function getFileAttributes($path) {
    $attributes = [
        'readOnly' => !is_writable($path),
        'hidden' => substr(basename($path), 0, 1) === '.',
        'system' => false,
        'archive' => true
    ];
    
    // On Windows, system calls can retrieve exact attributes
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = [];
        exec('attrib "' . $path . '"', $output);
        if (!empty($output)) {
            $attribs = $output[0];
            $attributes['readOnly'] = strpos($attribs, 'R') !== false;
            $attributes['hidden'] = strpos($attribs, 'H') !== false;
            $attributes['system'] = strpos($attribs, 'S') !== false;
            $attributes['archive'] = strpos($attribs, 'A') !== false;
        }
    }
    
    return $attributes;
}
function getImageProperties($path) {
    $info = [];
    
    if (!file_exists($path)) {
        //cloud_safe_error_log("Error: File does not exist: $path");
        return $info;
    }
    if (!is_readable($path)) {
        //cloud_safe_error_log("Error: File is not readable: $path");
        return $info;
    }
    
    $exiftoolPath = __DIR__ . '/exiftool/exiftool' . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? '.exe' : '');
    
    if (file_exists($exiftoolPath) && is_executable($exiftoolPath)) {
        //cloud_safe_error_log("ExifTool found and executable");
        try {
            $libPath = __DIR__ . '/exiftool_files/lib';
            $env = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 
                'set PERL5LIB=' . escapeshellarg($libPath) . ' && ' : 
                'PERL5LIB=' . escapeshellarg($libPath) . ' ';
            $command = $env . escapeshellcmd($exiftoolPath) . ' -json -a -G -s -all ' . escapeshellarg($path) . ' 2>&1';
            //cloud_safe_error_log("Executing ExifTool command: $command");
            
            $output = shell_exec($command);
            //cloud_safe_error_log("ExifTool raw output: " . ($output !== null ? substr($output, 0, 1000) : 'null'));
            
            if ($output !== null && $output !== '') {
                $exifData = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($exifData[0])) {
                    //cloud_safe_error_log("ExifTool JSON decoded successfully, tags found: " . count($exifData[0]));
                    $tags = $exifData[0];
                    
                    // Extract key properties for all images
                    $info['width'] = $tags['PNG:ImageWidth'] ?? $tags['File:ImageWidth'] ?? null;
                    $info['height'] = $tags['PNG:ImageHeight'] ?? $tags['File:ImageHeight'] ?? null;
                    $info['bitDepth'] = $tags['PNG:BitDepth'] ?? null;
                    $info['colorType'] = $tags['PNG:ColorType'] ?? null;
                    $info['compression'] = $tags['PNG:Compression'] ?? null;
                    $info['filter'] = $tags['PNG:Filter'] ?? null;
                    $info['interlace'] = $tags['PNG:Interlace'] ?? null;
                    $info['srgbRendering'] = $tags['PNG:SRGBRendering'] ?? null;
                    $info['gamma'] = $tags['PNG:Gamma'] ?? null;
                    $info['resolution'] = [
                        'x' => $tags['PNG:PixelsPerUnitX'] ?? null,
                        'y' => $tags['PNG:PixelsPerUnitY'] ?? null,
                        'unit' => $tags['PNG:PixelUnits'] ?? null
                    ];
                    
                    // EXIF data (for JPEG/TIFF)
                    if (isset($tags['EXIF:FocalLength'])) $info['focalLength'] = $tags['EXIF:FocalLength'];
                    if (isset($tags['EXIF:Model'])) $info['cameraModel'] = $tags['EXIF:Model'];
                    if (isset($tags['EXIF:FNumber'])) $info['aperture'] = $tags['EXIF:FNumber'];
                    if (isset($tags['EXIF:ISOSpeedRatings'])) $info['iso'] = $tags['EXIF:ISOSpeedRatings'];
                    if (isset($tags['EXIF:ExposureTime'])) $info['exposure'] = $tags['EXIF:ExposureTime'];
                    
                    // Full tag dump
                    $info['allExifTags'] = $tags;
                    $info['exifOnly'] = array_filter($tags, fn($key) => strpos($key, 'EXIF:') === 0, ARRAY_FILTER_USE_KEY);
                    //cloud_safe_error_log("EXIF tags extracted: " . count($info['exifOnly']));
                    
                    return $info;
                } else {
                    //cloud_safe_error_log("ExifTool JSON parse error for $path: " . json_last_error_msg());
                }
            } else {
                //cloud_safe_error_log("ExifTool returned empty output for $path");
            }
        } catch (Exception $e) {
            //cloud_safe_error_log("ExifTool execution error for $path: " . $e->getMessage());
        }
    } else {
        //cloud_safe_error_log("ExifTool not found or not executable at: $exiftoolPath");
    }
    
    //cloud_safe_error_log("Falling back to exif_read_data for $path");
    $supportedFormats = ['jpg', 'jpeg', 'tiff', 'png'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    //cloud_safe_error_log("File extension: $ext");
    
    if (function_exists('exif_read_data') && in_array($ext, $supportedFormats)) {
        try {
            //cloud_safe_error_log("Attempting exif_read_data for $path");
            $exif = @exif_read_data($path, 'EXIF', true);
            if ($exif !== false) {
                //cloud_safe_error_log("exif_read_data successful, processing EXIF data");
                if (isset($exif['COMPUTED']['Width']) && isset($exif['COMPUTED']['Height'])) {
                    $info['width'] = $exif['COMPUTED']['Width'];
                    $info['height'] = $exif['COMPUTED']['Height'];
                    //cloud_safe_error_log("Extracted width: {$info['width']}, height: {$info['height']}");
                }
                if (isset($exif['EXIF']['XResolution']) && isset($exif['EXIF']['YResolution'])) {
                    $info['resolution'] = [
                        'horizontal' => $exif['EXIF']['XResolution'],
                        'vertical' => $exif['EXIF']['YResolution']
                    ];
                    //cloud_safe_error_log("Extracted resolution: horizontal={$info['resolution']['horizontal']}, vertical={$info['resolution']['vertical']}");
                }
                if (isset($exif['COMPUTED']['BitsPerSample'])) {
                    $info['bitDepth'] = $exif['COMPUTED']['BitsPerSample'];
                    //cloud_safe_error_log("Extracted bitDepth: {$info['bitDepth']}");
                } elseif (isset($exif['EXIF']['BitsPerSample'])) {
                    $info['bitDepth'] = $exif['EXIF']['BitsPerSample'];
                    //cloud_safe_error_log("Extracted bitDepth (EXIF): {$info['bitDepth']}");
                }
                if (isset($exif['EXIF']['Model'])) {
                    $info['cameraModel'] = $exif['EXIF']['Model'];
                    //cloud_safe_error_log("Extracted cameraModel: {$info['cameraModel']}");
                }
                if (isset($exif['EXIF']['ExposureTime'])) {
                    $info['exposure'] = $exif['EXIF']['ExposureTime'];
                    //cloud_safe_error_log("Extracted exposure: {$info['exposure']}");
                }
                if (isset($exif['EXIF']['FocalLength'])) {
                    $focalLength = $exif['EXIF']['FocalLength'];
                    if (strpos($focalLength, '/') !== false) {
                        list($numerator, $denominator) = explode('/', $focalLength);
                        $info['focalLength'] = $numerator / ($denominator ?: 1);
                        $info['focalLength'] = round($info['focalLength'], 2) . ' mm';
                    } else {
                        $info['focalLength'] = (float)$focalLength . ' mm';
                    }
                    //cloud_safe_error_log("Extracted focalLength: {$info['focalLength']}");
                }
                if (isset($exif['EXIF']['FNumber'])) {
                    $fNumber = $exif['EXIF']['FNumber'];
                    if (strpos($fNumber, '/') !== false) {
                        list($numerator, $denominator) = explode('/', $fNumber);
                        $info['aperture'] = 'f/' . round($numerator / ($denominator ?: 1), 1);
                    } else {
                        $info['aperture'] = 'f/' . (float)$fNumber;
                    }
                    //cloud_safe_error_log("Extracted aperture: {$info['aperture']}");
                }
                if (isset($exif['EXIF']['ISOSpeedRatings'])) {
                    $info['iso'] = $exif['EXIF']['ISOSpeedRatings'];
                    //cloud_safe_error_log("Extracted iso: {$info['iso']}");
                }
            } else {
                //cloud_safe_error_log("EXIF data not available for file: $path");
            }
        } catch (Exception $e) {
            //cloud_safe_error_log("Fallback EXIF error for $path: " . $e->getMessage());
        }
    } else {
        //cloud_safe_error_log("exif_read_data not available or unsupported format: $ext");
    }
    
    // Fallback via getimagesize
    //cloud_safe_error_log("Checking getimagesize for $path");
    if (empty($info['width']) && ($imageSize = @getimagesize($path))) {
        $info['width'] = $imageSize[0];
        $info['height'] = $imageSize[1];
        if (isset($imageSize['channels'])) {
            $info['bitDepth'] = $imageSize['bits'] * $imageSize['channels'];
        }
        //cloud_safe_error_log("getimagesize extracted: width={$info['width']}, height={$info['height']}" . (isset($info['bitDepth']) ? ", bitDepth={$info['bitDepth']}" : ""));
    } else {
        //cloud_safe_error_log("getimagesize failed or width already set for $path");
    }
    
    //cloud_safe_error_log("Returning image properties: " . json_encode($info));
    return $info;
}








function getAudioProperties($path) {
    $info = [];
    
    // Use getID3 or a similar library to get audio metadata
    // Simplified implementation here
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    // For MP3 files, try to get basic info
    if ($ext === 'mp3' && function_exists('mp3_info')) {
        // MP3 info retrieval implementation
    }
    
    return $info;
}

function getVideoProperties($path) {
    $info = [];
    
    // Use ffmpeg or similar tools to get video metadata
    // Simplified implementation here
    
    return $info;
}

function getDocumentProperties($path) {
    $info = [];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    // For text files
    if (in_array($ext, ['txt', 'md', 'rtf'])) {
        $content = file_get_contents($path);
        $info['lines'] = substr_count($content, "\n") + 1;
        $info['words'] = str_word_count($content);
        $info['characters'] = strlen($content);
    }
    
    return $info;
}

function getSecurityProperties($path) {
    $security = [];
    
    if (function_exists('posix_getpwuid')) {
        $stat = stat($path);
        $owner = posix_getpwuid($stat['uid']);
        $group = posix_getgrgid($stat['gid']);
        $security['owner'] = $owner['name'] ?? 'Unknown';
        $security['group'] = $group['name'] ?? 'Unknown';
        $security['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
    } else {
        // On Windows
        $security['owner'] = get_current_user();
        $security['permissions'] = 'N/A';
    }
    
    return $security;
}

// File conversion function
function convertFile($inputFile, $outputFile, $targetFormat) {
    $inputFormat = strtolower(pathinfo($inputFile, PATHINFO_EXTENSION));
    
    // Supported conversions
    $supportedConversions = [
        'docx' => ['pdf', 'txt', 'odt', 'html'],
        'doc' => ['pdf', 'txt', 'odt', 'html'],
        'pdf' => ['docx', 'txt', 'html'],
        'odt' => ['pdf', 'docx', 'txt', 'html'],
        'jpg' => ['png', 'webp', 'bmp', 'gif'],
        'jpeg' => ['png', 'webp', 'bmp', 'gif'],
        'png' => ['jpg', 'webp', 'bmp', 'gif'],
        'webp' => ['jpg', 'png', 'bmp', 'gif'],
        'bmp' => ['jpg', 'png', 'webp', 'gif'],
        'gif' => ['jpg', 'png', 'webp', 'bmp']
    ];
    
    // Check conversion support
    if (!isset($supportedConversions[$inputFormat]) || 
        !in_array($targetFormat, $supportedConversions[$inputFormat])) {
        throw new Exception("Conversion from .$inputFormat to .$targetFormat is not supported");
    }
    
    // Determine file type
    $isDocument = in_array($inputFormat, ['docx', 'doc', 'pdf', 'odt', 'txt', 'html']);
    $isImage = in_array($inputFormat, ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif']);
    
    if ($isDocument) {
        return convertDocument($inputFile, $outputFile, $inputFormat, $targetFormat);
    } elseif ($isImage) {
        return convertImage($inputFile, $outputFile, $inputFormat, $targetFormat);
    } else {
        throw new Exception("Unsupported file type: .$inputFormat");
    }
}

function convertDocument($inputFile, $outputFile, $from, $to) {
    // Check if LibreOffice is available
    $libreofficeCmd = '';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows paths
        $possiblePaths = [
            '"C:\Program Files\LibreOffice\program\soffice.exe"',
            '"C:\Program Files (x86)\LibreOffice\program\soffice.exe"'
        ];
        
        foreach ($possiblePaths as $cmd) {
            if (file_exists(str_replace(['"', "'"], '', $cmd))) {
                $libreofficeCmd = $cmd;
                break;
            }
        }
    } else {
        // Linux/Mac
        $libreofficeCmd = 'libreoffice';
    }
    
    if (!$libreofficeCmd) {
        throw new Exception("LibreOffice not found. Please install LibreOffice for document conversion.");
    }
    
    $outputDir = dirname($outputFile);
    $tempDir = __DIR__ . '/temp_convert';
    
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }
    
    $command = $libreofficeCmd . " --headless --convert-to " . escapeshellarg($to) . 
              " --outdir " . escapeshellarg($tempDir) . " " . 
              escapeshellarg($inputFile) . " 2>&1";
    
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0) {
        // Look for the converted file
        $originalFilename = pathinfo($inputFile, PATHINFO_FILENAME);
        $convertedFile = $tempDir . '/' . $originalFilename . '.' . $to;
        
        if (file_exists($convertedFile)) {
            // Move to the target location
            if (!rename($convertedFile, $outputFile)) {
                throw new Exception("Failed to move converted file");
            }
            
            // Clean up temporary files
            $tempFiles = glob($tempDir . '/*');
            foreach ($tempFiles as $tempFile) {
                if (is_file($tempFile)) {
                    @unlink($tempFile);
                }
            }
            
            return true;
        }
    }
    
    throw new Exception("Document conversion failed. Error code: $returnCode");
}

function convertImage($inputFile, $outputFile, $from, $to) {
    // Check if GD is available
    if (!extension_loaded('gd')) {
        throw new Exception("GD extension is required for image conversion");
    }
    
    $image = null;
    
    // Load the image depending on format
    switch (strtolower($from)) {
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($inputFile);
            break;
        case 'png':
            $image = imagecreatefrompng($inputFile);
            // Preserve transparency for PNG
            imagealphablending($image, false);
            imagesavealpha($image, true);
            break;
        case 'webp':
            $image = imagecreatefromwebp($inputFile);
            break;
        case 'bmp':
            if (function_exists('imagecreatefrombmp')) {
                $image = imagecreatefrombmp($inputFile);
            } else {
                throw new Exception("BMP conversion not supported");
            }
            break;
        case 'gif':
            $image = imagecreatefromgif($inputFile);
            break;
    }
    
    if (!$image) {
        throw new Exception("Failed to load $from image");
    }
    
    $success = false;
    
    // Save in the required format
    switch (strtolower($to)) {
        case 'jpg':
        case 'jpeg':
            $success = imagejpeg($image, $outputFile, 90);
            break;
        case 'png':
            $success = imagepng($image, $outputFile, 9);
            break;
        case 'webp':
            $success = imagewebp($image, $outputFile, 90);
            break;
        case 'bmp':
            if (function_exists('imagebmp')) {
                $success = imagebmp($image, $outputFile);
            } else {
                imagedestroy($image);
                throw new Exception("BMP conversion not supported");
            }
            break;
        case 'gif':
            $success = imagegif($image, $outputFile);
            break;
    }
    
    imagedestroy($image);
    
    if (!$success) {
        throw new Exception("Failed to save $to image");
    }
    
    return true;
}
// Presentation to HTML conversion
function presentationToHtml($filePath) {
    // Check file size (over 50MB)
    $fileSize = filesize($filePath);
    $maxSize = 10 * 1024 * 1024; // 50MB
    
    if ($fileSize > $maxSize) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    background: #1a1a1a; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    min-height: 100vh; 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                }
                .error-card { 
                    background: #2a2a2a; 
                    border-radius: 16px; 
                    padding: 48px; 
                    text-align: center; 
                    max-width: 400px; 
                    width: 90%; 
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
                    border: 1px solid #3a3a3a; 
                }
                .error-icon { 
                    width: 120px; 
                    height: 120px; 
                    margin: 0 auto 32px; 
                }
                h2 { 
                    color: #ffffff; 
                    font-size: 24px; 
                    font-weight: 500; 
                    margin-bottom: 16px; 
                }
                p { 
                    color: #9a9a9a; 
                    font-size: 16px; 
                    line-height: 1.5; 
                    margin-bottom: 32px; 
                }
                .file-size { 
                    background: #3a3a3a; 
                    padding: 8px 16px; 
                    border-radius: 20px; 
                    display: inline-block; 
                    color: #ff6b6b; 
                    font-family: monospace; 
                    font-size: 14px; 
                    margin-top: 10px; 
                }
                .back-btn { 
                    background: #3a3a3a; 
                    border: none; 
                    color: #ffffff; 
                    font-size: 16px; 
                    font-weight: 500; 
                    padding: 12px 32px; 
                    border-radius: 8px; 
                    cursor: pointer; 
                    transition: all 0.2s; 
                    display: inline-flex; 
                    align-items: center; 
                    gap: 8px; 
                }
                .back-btn:hover { 
                    background: #4a4a4a; 
                }
                .back-btn svg { 
                    width: 20px; 
                    height: 20px; 
                    fill: none; 
                    stroke: #ffffff; 
                    stroke-width: 2; 
                }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="error-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ff6b6b" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <circle cx="12" cy="16" r="1"/>
                    </svg>
                </div>
                <h2>File too large</h2>
                <p>The presentation exceeds maximum preview size</p>
                <div class="file-size">' . round($fileSize / (1024 * 1024), 1) . ' MB / 10 MB</div>
                <button class="back-btn" onclick="window.close()">
                    <svg viewBox="0 0 24 24">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Close
                </button>
            </div>
        </body>
        </html>';
    }
    
    try {
        $presentation = PresentationIOFactory::load($filePath);
        $html = '<div style="font-family: Arial, sans-serif; max-width: 100%; margin: 0 auto;">';
        $slideIndex = 1;
        
        foreach ($presentation->getAllSlides() as $slide) {
            $html .= '<div style="border: 1px solid var(--border); padding: 20px; margin-bottom: 20px; background: var(--panel); border-radius: 8px;">';
            $html .= '<h3 style="color: var(--accent); margin-top: 0;">Slide ' . $slideIndex++ . '</h3>';
            
            foreach ($slide->getShapeCollection() as $shape) {
                if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                    $text = '';
                    foreach ($shape->getParagraphs() as $paragraph) {
                        foreach ($paragraph->getRichTextElements() as $textElement) {
                            if ($textElement instanceof \PhpOffice\PhpPresentation\Shape\RichText\TextElement) {
                                $font = $textElement->getFont();
                                $style = 'font-size: ' . ($font->getSize() ? $font->getSize() . 'pt' : '12pt') . ';';
                                $style .= ' color: ' . ($font->getColor() ? '#' . $font->getColor()->getRGB() : 'var(--fg)') . ';';
                                if ($font->isBold()) $style .= ' font-weight: bold;';
                                if ($font->isItalic()) $style .= ' font-style: italic;';
                                $text .= '<span style="' . $style . '">' . htmlspecialchars($textElement->getText()) . '</span>';
                            }
                        }
                        $text .= '<br>';
                    }
                    $html .= '<div style="margin-bottom: 10px;">' . $text . '</div>';
                } elseif ($shape instanceof \PhpOffice\PhpPresentation\Shape\Drawing\File) {
                    try {
                        $imageContent = file_get_contents($shape->getPath());
                        $base64 = base64_encode($imageContent);
                        $mime = mime_content_type($shape->getPath());
                        $html .= '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Image" style="max-width: 100%; height: auto; margin: 10px 0;">';
                    } catch (Exception $e) {
                        $html .= '<p>Failed to load image</p>';
                    }
                }
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    } catch (Exception $e) {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    background: #1a1a1a; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    min-height: 100vh; 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                }
                .error-card { 
                    background: #2a2a2a; 
                    border-radius: 16px; 
                    padding: 48px; 
                    text-align: center; 
                    max-width: 400px; 
                    width: 90%; 
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
                    border: 1px solid #3a3a3a; 
                }
                .error-icon { 
                    width: 120px; 
                    height: 120px; 
                    margin: 0 auto 32px; 
                }
                h2 { 
                    color: #ffffff; 
                    font-size: 24px; 
                    font-weight: 500; 
                    margin-bottom: 16px; 
                }
                p { 
                    color: #9a9a9a; 
                    font-size: 16px; 
                    line-height: 1.5; 
                    margin-bottom: 32px; 
                }
                .back-btn { 
                    background: #3a3a3a; 
                    border: none; 
                    color: #ffffff; 
                    font-size: 16px; 
                    font-weight: 500; 
                    padding: 12px 32px; 
                    border-radius: 8px; 
                    cursor: pointer; 
                    transition: all 0.2s; 
                    display: inline-flex; 
                    align-items: center; 
                    gap: 8px; 
                }
                .back-btn:hover { 
                    background: #4a4a4a; 
                }
                .back-btn svg { 
                    width: 20px; 
                    height: 20px; 
                    fill: none; 
                    stroke: #ffffff; 
                    stroke-width: 2; 
                }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="error-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ff6b6b" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <circle cx="12" cy="16" r="1"/>
                    </svg>
                </div>
                <h2>Cannot open presentation</h2>
                <p>The file may be corrupted or in an unsupported format</p>
                <button class="back-btn" onclick="window.close()">
                    <svg viewBox="0 0 24 24">
                        <line x1="19" y1="12" x2="5" y2="12"/>
                        <polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Close
                </button>
            </div>
        </body>
        </html>';
    }
}
$action = $_GET['action'] ?? $_POST['action'] ?? '';







// ------------------------------------------------------------------
// ------------------------------------------------------------------
//  Quota helpers — read from cloud_user_settings (keyed by email).
//  $username is the full email with @ and . replaced by _ (e.g. user_gmail_com).
// ------------------------------------------------------------------
function getUserQuota(string $username): int {
    $db = getDB();
    if (!$db) return 500;
    $email = get_session_email();
    if ($email) {
        try {
            $stmt = $db->prepare('SELECT quota_mb FROM cloud_user_settings WHERE email = :e LIMIT 1');
            $stmt->bindValue(':e', $email, PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            if ($row) return max(1, (int)$row['quota_mb']);
        } catch (Exception $e) {
            cloud_safe_error_log("getUserQuota by email failed: " . $e->getMessage());
        }
    }
    try {
        $stmt = $db->prepare('SELECT quota_mb FROM cloud_user_settings WHERE email LIKE :p LIMIT 1');
        $stmt->bindValue(':p', $username, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        return $row ? max(1, (int)$row['quota_mb']) : 500;
    } catch (Exception $e) {
        cloud_safe_error_log("getUserQuota fallback failed: " . $e->getMessage());
        return 500;
    }
}

function getSessionLimit(string $username): int {
    $db = getDB();
    if (!$db) return 0;
    $email = get_session_email();
    if ($email) {
        try {
            $stmt = $db->prepare('SELECT session_limit_mb FROM cloud_user_settings WHERE email = :e LIMIT 1');
            $stmt->bindValue(':e', $email, PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            if ($row) return max(0, (int)$row['session_limit_mb']);
        } catch (Exception $e) {
            cloud_safe_error_log("getSessionLimit by email failed: " . $e->getMessage());
        }
    }
    try {
        $stmt = $db->prepare('SELECT session_limit_mb FROM cloud_user_settings WHERE email LIKE :p LIMIT 1');
        $stmt->bindValue(':p', $username, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        return $row ? max(0, (int)$row['session_limit_mb']) : 0;
    } catch (Exception $e) {
        cloud_safe_error_log("getSessionLimit fallback failed: " . $e->getMessage());
        return 0;
    }
}

function ensureCloudUserSettingsSchema($db): void {
    if (!$db) return;
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_user_settings (
        email TEXT PRIMARY KEY,
        quota_mb INTEGER NOT NULL DEFAULT 500,
        session_limit_mb INTEGER NOT NULL DEFAULT 0,
        hide_lang_switcher INTEGER NOT NULL DEFAULT 0,
        sync_local_path TEXT NOT NULL DEFAULT '',
        sync_interval_sec INTEGER NOT NULL DEFAULT 300,
        sync_mode TEXT NOT NULL DEFAULT 'mtime_size_hash',
        sync_enabled INTEGER NOT NULL DEFAULT 0,
        last_sync_at INTEGER NOT NULL DEFAULT 0,
        sync_log_json TEXT NOT NULL DEFAULT '[]',
        updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
    )");
    $hasHideLangSwitcher = false;
    $hasSyncLocalPath = false;
    $hasSyncIntervalSec = false;
    $hasSyncMode = false;
    $hasSyncEnabled = false;
    $hasLastSyncAt = false;
    $hasSyncLogJson = false;
    $cols = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='cloud_user_settings'");
    while ($cols && ($col = $cols->fetch(PDO::FETCH_ASSOC))) {
        if (($col['name'] ?? '') === 'hide_lang_switcher') $hasHideLangSwitcher = true;
        if (($col['name'] ?? '') === 'sync_local_path') $hasSyncLocalPath = true;
        if (($col['name'] ?? '') === 'sync_interval_sec') $hasSyncIntervalSec = true;
        if (($col['name'] ?? '') === 'sync_mode') $hasSyncMode = true;
        if (($col['name'] ?? '') === 'sync_enabled') $hasSyncEnabled = true;
        if (($col['name'] ?? '') === 'last_sync_at') $hasLastSyncAt = true;
        if (($col['name'] ?? '') === 'sync_log_json') $hasSyncLogJson = true;
    }
    if (!$hasHideLangSwitcher) {
        $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN hide_lang_switcher INTEGER NOT NULL DEFAULT 0");
    }
    if (!$hasSyncLocalPath) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN sync_local_path TEXT NOT NULL DEFAULT ''");
    if (!$hasSyncIntervalSec) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN sync_interval_sec INTEGER NOT NULL DEFAULT 300");
    if (!$hasSyncMode) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN sync_mode TEXT NOT NULL DEFAULT 'mtime_size_hash'");
    if (!$hasSyncEnabled) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN sync_enabled INTEGER NOT NULL DEFAULT 0");
    if (!$hasLastSyncAt) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN last_sync_at INTEGER NOT NULL DEFAULT 0");
    if (!$hasSyncLogJson) $db->exec("ALTER TABLE cloud_user_settings ADD COLUMN sync_log_json TEXT NOT NULL DEFAULT '[]'");
}

function resolve_cloud_user_email_from_legacy_name(AppDatabase $db, string $name): ?string {
    $name = trim($name);
    if ($name === '' || $name === 'default' || $name === 'guest') return null;
    if (strpos($name, '@') !== false) return $name;
    $res = $db->query('SELECT email FROM users');
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $email = (string)($row['email'] ?? '');
        if ($email !== '' && email_to_cloud_username($email) === $name) return $email;
    }
    return null;
}

function migrate_legacy_users_json(): void {
    $legacyFile = __DIR__ . '/data/users.json';
    if (!is_file($legacyFile)) {
        $dataDir = __DIR__ . '/data';
        if (is_dir($dataDir)) {
            $left = array_diff(scandir($dataDir) ?: [], ['.', '..']);
            if (!$left) @rmdir($dataDir);
        }
        return;
    }
    $raw = @file_get_contents($legacyFile);
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if (is_array($data)) {
        $db = getDB();
        if ($db) {
            try {
                ensureCloudUserSettingsSchema($db);
                $users = is_array($data['users'] ?? null) ? $data['users'] : [];
                $stmt = $db->prepare("INSERT INTO cloud_user_settings (email, quota_mb, session_limit_mb, hide_lang_switcher, updated_at)
                    VALUES (:e, :q, :l, 0, extract(epoch from now())::bigint)
                    ON CONFLICT(email) DO UPDATE SET
                        quota_mb = excluded.quota_mb,
                        session_limit_mb = excluded.session_limit_mb,
                        updated_at = excluded.updated_at");
                foreach ($users as $u) {
                    if (!is_array($u)) continue;
                    $email = resolve_cloud_user_email_from_legacy_name($db, (string)($u['name'] ?? ''));
                    if (!$email) continue;
                    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
                    $stmt->bindValue(':q', max(1, (int)($u['quota_mb'] ?? 500)), PDO::PARAM_INT);
                    $stmt->bindValue(':l', max(0, (int)($u['session_limit_mb'] ?? 0)), PDO::PARAM_INT);
                    $stmt->execute();
                }
            } catch (Exception $e) {
                cloud_safe_error_log('migrate_legacy_users_json: ' . $e->getMessage());
            }
        }
    }
    @unlink($legacyFile);
    $dataDir = dirname($legacyFile);
    if (is_dir($dataDir)) {
        $left = array_diff(scandir($dataDir) ?: [], ['.', '..']);
        if (!$left) @rmdir($dataDir);
    }
}

migrate_legacy_users_json();
























// ------------------------------------------------------------------
//  Client Token Management Actions
// ------------------------------------------------------------------

















// Get the current user's role

if (!function_exists('cloud_text_revision')) {
  function cloud_text_revision(string $content): string {
    return sha1($content);
  }
}
if (!function_exists('cloud_merge_text_three_way')) {
  function cloud_merge_text_three_way(string $base, string $current, string $incoming): array {
    if ($current === $incoming) return ['content' => $current, 'merged' => false];
    if ($current === $base) return ['content' => $incoming, 'merged' => false];
    if ($incoming === $base) return ['content' => $current, 'merged' => false];
    $baseLines = preg_split("/\r\n|\n|\r/", $base);
    $currentLines = preg_split("/\r\n|\n|\r/", $current);
    $incomingLines = preg_split("/\r\n|\n|\r/", $incoming);
    $prefix = 0;
    $maxPrefix = min(count($baseLines), count($currentLines), count($incomingLines));
    while ($prefix < $maxPrefix && $baseLines[$prefix] === $currentLines[$prefix] && $baseLines[$prefix] === $incomingLines[$prefix]) $prefix++;
    $baseEnd = count($baseLines) - 1;
    $currentEnd = count($currentLines) - 1;
    $incomingEnd = count($incomingLines) - 1;
    while ($baseEnd >= $prefix && $currentEnd >= $prefix && $incomingEnd >= $prefix && $baseLines[$baseEnd] === $currentLines[$currentEnd] && $baseLines[$baseEnd] === $incomingLines[$incomingEnd]) {
      $baseEnd--;
      $currentEnd--;
      $incomingEnd--;
    }
    $head = array_slice($baseLines, 0, $prefix);
    $tail = $baseEnd < count($baseLines) - 1 ? array_slice($baseLines, $baseEnd + 1) : [];
    $baseMiddle = $baseEnd >= $prefix ? array_slice($baseLines, $prefix, $baseEnd - $prefix + 1) : [];
    $currentMiddle = $currentEnd >= $prefix ? array_slice($currentLines, $prefix, $currentEnd - $prefix + 1) : [];
    $incomingMiddle = $incomingEnd >= $prefix ? array_slice($incomingLines, $prefix, $incomingEnd - $prefix + 1) : [];
    if ($currentMiddle === $baseMiddle) $middle = $incomingMiddle;
    else if ($incomingMiddle === $baseMiddle) $middle = $currentMiddle;
    else if ($currentMiddle === $incomingMiddle) $middle = $currentMiddle;
    else {
      $middle = $currentMiddle;
      if (!empty($middle) && !empty($incomingMiddle) && end($middle) !== '') $middle[] = '';
      foreach ($incomingMiddle as $line) $middle[] = $line;
    }
    return ['content' => implode("\n", array_merge($head, $middle, $tail)), 'merged' => true];
  }
}


// === NEW ONLYOFFICE ENDPOINT: CLEAN FILE DELIVERY ===



























// Metadata endpoint - EXIF extraction via exiftool


















// Get AI configuration


// Save AI configuration (root only)


// ------------------------------------------------------------------
//  DEBUG: call ?action=debug_session to see what session api.php sees
//  Remove this endpoint after confirming folder state works.
// ------------------------------------------------------------------


// ------------------------------------------------------------------
// ------------------------------------------------------------------
//  Full file tree cache helpers
// ------------------------------------------------------------------

/**
 * Recursively scan all files and folders under $dir,
 * returning a flat array of items (same shape as the 'list' action).
 * $base is the user root (used to compute relative paths).
 */
function scan_full_tree(string $dir, string $base): array {
    $items = [];
    if (!is_dir($dir)) return $items;
    foreach (scandir($dir) as $it) {
        if ($it === '.' || $it === '..') continue;
        $full = $dir . '/' . $it;
        // rel path relative to user root
        $rel  = ltrim(substr($full, strlen($base)), '/');
        if (cloud_is_hidden_main_tree_path($rel)) continue;
        if (cloud_is_hidden_group_tree_path($rel)) continue;
        $isDir = is_dir($full);
        $items[] = [
            'name'  => $it,
            'path'  => $rel,
            'type'  => $isDir ? 'dir' : 'file',
            'size'  => $isDir ? null : filesize($full),
            'mtime' => filemtime($full),
        ];
        if ($isDir) {
            $children = scan_full_tree($full, $base);
            foreach ($children as $child) {
                $items[] = $child;
            }
        }
    }
    return $items;
}

/**
 * Ensure the cloud_folder_state table has the tree_json column (added later).
 */
function ensure_file_tree_table(AppDatabase $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS cloud_folder_state (
            email       TEXT    PRIMARY KEY,
            folder_json TEXT    NOT NULL DEFAULT '[]',
            tree_json   TEXT    NOT NULL DEFAULT '',
            tree_valid  INTEGER NOT NULL DEFAULT 0,
            updated_at  INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )
    ");
    // Add tree_json / tree_valid columns if upgrading from old schema
    try { $db->exec("ALTER TABLE cloud_folder_state ADD COLUMN folder_json TEXT NOT NULL DEFAULT '[]'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE cloud_folder_state ADD COLUMN tree_json TEXT NOT NULL DEFAULT ''"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE cloud_folder_state ADD COLUMN tree_valid INTEGER NOT NULL DEFAULT 0"); } catch (Exception $e) {}
}

/**
 * Mark the cached tree as invalid for a user (call after any write operation).
 * Accepts either email or username — we try both.
 */
function invalidate_file_tree(string $email): void {
    $db = getDB();
    if (!$db) return;
    try {
        ensure_file_tree_table($db);
        $stmt = $db->prepare("
            UPDATE cloud_folder_state
            SET tree_valid = 0, tree_json = '', updated_at = extract(epoch from now())::bigint
            WHERE email = :e
        ");
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Exception $e) {
        cloud_safe_error_log("invalidate_file_tree failed: " . $e->getMessage());
    }
}
function patch_file_tree_cache_rename(string $email, string $oldPath, string $newPath, string $newName): void {
    $db = getDB();
    if (!$db) return;
    try {
        ensure_file_tree_table($db);
        $stmt = $db->prepare("SELECT tree_json, tree_valid FROM cloud_folder_state WHERE email = :e LIMIT 1");
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)($row['tree_valid'] ?? 0) !== 1 || empty($row['tree_json'])) return;
        $items = json_decode($row['tree_json'], true);
        if (!is_array($items)) return;
        $changed = false;
        foreach ($items as &$item) {
            $itemPath = (string)($item['path'] ?? '');
            if ($itemPath === $oldPath) {
                $item['path'] = $newPath;
                $item['name'] = $newName;
                $changed = true;
                continue;
            }
            if (strpos($itemPath, $oldPath . '/') === 0) {
                $item['path'] = $newPath . '/' . substr($itemPath, strlen($oldPath) + 1);
                $changed = true;
            }
        }
        unset($item);
        if (!$changed) return;
        $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $update = $db->prepare("UPDATE cloud_folder_state SET tree_json = :j, tree_valid = 1, updated_at = extract(epoch from now())::bigint WHERE email = :e");
        $update->bindValue(':e', $email, PDO::PARAM_STR);
        $update->bindValue(':j', $json, PDO::PARAM_STR);
        $update->execute();
    } catch (Exception $e) {
        cloud_safe_error_log("patch_file_tree_cache_rename failed: " . $e->getMessage());
    }
}

/**
 * Scan the disk and save the full tree to DB, marking it valid.
 */
function rebuild_file_tree(string $email, string $user): void {
    $db = getDB();
    if (!$db) return;
    $base  = user_root($user);
    $items = scan_full_tree($base, $base);
    $json  = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    try {
        ensure_file_tree_table($db);
        // Upsert
        $check = $db->prepare("SELECT COUNT(*) as cnt FROM cloud_folder_state WHERE email = :e");
        $check->bindValue(':e', $email, PDO::PARAM_STR);
        $cnt = $check->execute()->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
        if ($cnt == 0) {
            $s = $db->prepare("INSERT INTO cloud_folder_state (email, folder_json, tree_json, tree_valid, updated_at) VALUES (:e, '[]', :j, 1, extract(epoch from now())::bigint)");
        } else {
            $s = $db->prepare("UPDATE cloud_folder_state SET tree_json = :j, tree_valid = 1, updated_at = extract(epoch from now())::bigint WHERE email = :e");
        }
        $s->bindValue(':e', $email, PDO::PARAM_STR);
        $s->bindValue(':j', $json, PDO::PARAM_STR);
        $s->execute();
    } catch (Exception $e) {
        cloud_safe_error_log("rebuild_file_tree failed: " . $e->getMessage());
    }
}

// ------------------------------------------------------------------
//  Storage usage cache helpers (folder-level treemap data)
// ------------------------------------------------------------------
function ensure_storage_usage_table(AppDatabase $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS cloud_storage_usage_stats (
            email            TEXT PRIMARY KEY,
            stats_json       TEXT    NOT NULL DEFAULT '[]',
            root_total_bytes INTEGER NOT NULL DEFAULT 0,
            updated_at       INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )
    ");
}

/**
 * Recursively calculates folder sizes and metadata.
 * Returns [sizeBytes, filesCount, subfoldersCount, foldersArray].
 */
function scan_folder_usage_recursive(string $full, string $base, int $depth = 0): array {
    $sizeBytes = 0;
    $filesCount = 0;
    $subfoldersCount = 0;
    $folders = [];

    if (!is_dir($full)) {
        return [0, 0, 0, []];
    }

    $entries = @scandir($full);
    if (!is_array($entries)) {
        return [0, 0, 0, []];
    }

    $childrenPreview = [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $childFull = $full . '/' . $entry;

        if (count($childrenPreview) < 8) {
            $childrenPreview[] = $entry;
        }

        if (is_dir($childFull)) {
            [$childSize, $childFiles, $childSubfolders, $childFolders] = scan_folder_usage_recursive($childFull, $base, $depth + 1);
            $sizeBytes += $childSize;
            $filesCount += $childFiles;
            $subfoldersCount += 1 + $childSubfolders;
            foreach ($childFolders as $folderInfo) {
                $folders[] = $folderInfo;
            }
        } elseif (is_file($childFull)) {
            $fSize = @filesize($childFull);
            if ($fSize !== false) {
                $sizeBytes += (int)$fSize;
            }
            $filesCount++;
        }
    }

    $rel = ltrim(substr($full, strlen($base)), '/');
    $path = $rel === '' ? '/' : $rel;
    $name = $rel === '' ? '/' : basename($full);

    $folders[] = [
        'path' => $path,
        'name' => $name,
        'size_bytes' => $sizeBytes,
        'files_count' => $filesCount,
        'subfolders_count' => $subfoldersCount,
        'children_preview' => $childrenPreview,
        'depth' => $depth
    ];

    return [$sizeBytes, $filesCount, $subfoldersCount, $folders];
}

function build_storage_usage_data(string $user): array {
    $base = user_root($user);
    [$rootSize, $rootFiles, $rootSubfolders, $folders] = scan_folder_usage_recursive($base, $base, 0);

    usort($folders, function ($a, $b) {
        return ((int)$b['size_bytes']) <=> ((int)$a['size_bytes']);
    });

    // Keep payload bounded for UI performance.
    if (count($folders) > 1500) {
        $folders = array_slice($folders, 0, 1500);
    }

    return [
        'root_total_bytes' => (int)$rootSize,
        'root_files_count' => (int)$rootFiles,
        'root_subfolders_count' => (int)$rootSubfolders,
        'folders' => $folders
    ];
}

function save_storage_usage_stats(string $email, string $user): array {
    $db = getDB();
    if (!$db) {
        return ['ok' => false, 'error' => 'db unavailable'];
    }

    try {
        ensure_storage_usage_table($db);
        $data = build_storage_usage_data($user);
        $json = json_encode($data['folders'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) $json = '[]';

        $check = $db->prepare("SELECT COUNT(*) as cnt FROM cloud_storage_usage_stats WHERE email = :e");
        $check->bindValue(':e', $email, PDO::PARAM_STR);
        $cnt = (int)($check->execute()->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        if ($cnt === 0) {
            $stmt = $db->prepare("
                INSERT INTO cloud_storage_usage_stats (email, stats_json, root_total_bytes, updated_at)
                VALUES (:e, :j, :r, extract(epoch from now())::bigint)
            ");
        } else {
            $stmt = $db->prepare("
                UPDATE cloud_storage_usage_stats
                SET stats_json = :j, root_total_bytes = :r, updated_at = extract(epoch from now())::bigint
                WHERE email = :e
            ");
        }

        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $stmt->bindValue(':j', $json, PDO::PARAM_STR);
        $stmt->bindValue(':r', (int)$data['root_total_bytes'], PDO::PARAM_INT);
        $stmt->execute();

        $fresh = $db->prepare("
            SELECT stats_json, root_total_bytes, updated_at
            FROM cloud_storage_usage_stats
            WHERE email = :e
        ");
        $fresh->bindValue(':e', $email, PDO::PARAM_STR);
        $row = $fresh->execute()->fetch(PDO::FETCH_ASSOC);

        $folders = $row ? (json_decode($row['stats_json'], true) ?? []) : [];
        $folders = array_values(array_filter($folders, function ($f) {
            return isset($f['path']) && $f['path'] !== '/';
        }));

        return [
            'ok' => true,
            'from_cache' => false,
            'updated_at' => (int)($row['updated_at'] ?? time()),
            'root_total_bytes' => (int)($row['root_total_bytes'] ?? 0),
            'folders' => $folders
        ];
    } catch (Exception $e) {
        cloud_safe_error_log("save_storage_usage_stats failed: " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ------------------------------------------------------------------
//  get_file_tree — returns full flat tree from DB cache (or rescans disk)
// ------------------------------------------------------------------


// ------------------------------------------------------------------
//  invalidate_file_tree action — called by JS after write operations
// ------------------------------------------------------------------


// ------------------------------------------------------------------
//  Storage usage endpoints
//  - get_storage_usage: read cached folder usage stats from DB
//  - refresh_storage_usage: force disk scan and update DB cache
// ------------------------------------------------------------------




// ------------------------------------------------------------------
//  Folder state endpoints — persist expanded tree per user in DB
// ------------------------------------------------------------------






// json_out(['error'=>'unknown action'], 400);

// ------------------------------------------------------------------
//  patch_file_tree — incremental JSON cache update without disk scan
//  ops = JSON array of { op:'add'|'remove'|'rename'|'move', ... }
// ------------------------------------------------------------------
if (!defined('CLOUD_SYNC_WS_BOOT') && basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'api.php') {
    require_once __DIR__ . '/api/actions/router.php';
}
