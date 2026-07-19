<?php
function get_current_user_from_sessions(): string {
    $db = getDB();
    if (!$db) {
        return 'default';
    }
    $session_id = session_id();
    if ($session_id) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
            $stmt->bindValue(':session_id', $session_id, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['email'])) {
                $email = $row['email'];
                // Заменяем @ и . на _
                $username = str_replace(['@', '.'], '_', $email);
                // Дополнительная очистка на всякий случай
                $username = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username);
                return $username ?: 'default';
            }
        } catch (Exception $e) {
            error_log("Database query failed: " . $e->getMessage());
        }
    }
    if (isset($_COOKIE['PHPSESSID'])) {
        $cookie_session = $_COOKIE['PHPSESSID'];
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
            $stmt->bindValue(':session_id', $cookie_session, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['email'])) {
                $email = $row['email'];
                // Заменяем @ и . на _
                $username = str_replace(['@', '.'], '_', $email);
                // Дополнительная очистка на всякий случай
                $username = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username);
                return $username ?: 'default';
            }
        } catch (Exception $e) {
            error_log("Database query failed: " . $e->getMessage());
        }
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $username = 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
    return $username;
}

/**
 * Получение роли пользователя по email из БД
 * @return int 0 = обычный пользователь, 1 = администратор, 2 = root
 */

function getUserRoleByEmail($email): int {
    $db = getDB();
    if (!$db || !$email) {
        return 0;
    }
    try {
        $stmt = $db->prepare('SELECT role FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['role'])) {
            return (int)$row['role'];
        }
    } catch (Exception $e) {
        error_log("Failed to get user role: " . $e->getMessage());
    }
    return 0;
}

/**
 * Проверка, является ли текущий пользователь root (role = 2)
 */

function isUserRoot(): bool {
    $db = getDB();
    if (!$db) {
        return false;
    }
    $session_id = session_id();
    try {
        $sql = "SELECT u.role
                FROM active_sessions s
                JOIN users u ON s.email = u.email
                WHERE s.session_id = :session_id";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':session_id', $session_id, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['role'])) {
            return $row['role'] === 2;
        }
        if (isset($_COOKIE['PHPSESSID'])) {
            $cookie_session = $_COOKIE['PHPSESSID'];
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':session_id', $cookie_session, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['role'])) {
                return $row['role'] === 2;
            }
        }
    } catch (Exception $e) {
        error_log("Database query failed: " . $e->getMessage());
    }
    return false;
}

/**
 * Получение email текущего пользователя из БД по сессии
 */

function getCurrentUserEmail(): ?string {
    $db = getDB();
    if (!$db) {
        return null;
    }
    $session_id = session_id();
    foreach ([$session_id, $_COOKIE['PHPSESSID'] ?? null] as $sid) {
        if (!$sid) continue;
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
            $stmt->bindValue(':session_id', $sid, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['email'])) {
                return $row['email'];
            }
        } catch (Exception $e) {
            error_log("Database query failed: " . $e->getMessage());
        }
    }
    return null;
}

// Get current user (safe username derived from email)
$currentUser = get_current_user_from_sessions();

// Get current user email and role from DB
$currentUserEmail = getCurrentUserEmail();
$currentUserRole = getUserRoleByEmail($currentUserEmail);
$currentUserIsRoot = ($currentUserRole === 2);

// Create safe username for directory
$safeUsername = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $currentUser);

// User-specific directories and files
$USER_DIR = rtrim(APPS_DATA_DIR, '/') . '/' . $safeUsername . '/';
$UPLOAD_DIR = $USER_DIR . 'uploads/';
$ROUTES_FILE = $USER_DIR . 'routes.csv';
$FAVORITES_FILE = $USER_DIR . 'favorites.json';

// Normalize paths to resolve any .. or . components
$USER_DIR = realpath($USER_DIR) ?: $USER_DIR;
$UPLOAD_DIR = realpath($UPLOAD_DIR) ?: $UPLOAD_DIR;
$ROUTES_FILE = realpath($ROUTES_FILE) ?: $ROUTES_FILE;
$FAVORITES_FILE = realpath($FAVORITES_FILE) ?: $FAVORITES_FILE;

// Create user directory structure
@mkdir(APPS_DATA_DIR, 0755, true);
@mkdir($USER_DIR, 0755, true);
@mkdir($UPLOAD_DIR, 0755, true);

// Create files only if directories exist
if (is_dir($USER_DIR)) {
    if (!file_exists($ROUTES_FILE)) {
        file_put_contents($ROUTES_FILE, '');
    }
    if (!file_exists($FAVORITES_FILE)) {
        file_put_contents($FAVORITES_FILE, json_encode([]));
    }
}

// Initialize location sharing database on page load
