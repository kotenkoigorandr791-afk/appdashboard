<?php
require_once __DIR__ . '/db.php';

function viewerFindEmail(AppDatabase $db, string $sessionId): ?string {
    if ($sessionId === '') return null;
    try {
        $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['email'])) return $row['email'];
    } catch (Exception $e) {
        error_log('Database query failed: ' . $e->getMessage());
    }
    return null;
}

function viewerFetchRoleBySession(AppDatabase $db, string $sessionId): ?int {
    if ($sessionId === '') return null;
    try {
        $sql = "SELECT u.role FROM active_sessions s JOIN users u ON s.email = u.email WHERE s.session_id = :session_id";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['role'])) return (int)$row['role'];
    } catch (Exception $e) {
        error_log('Database query failed: ' . $e->getMessage());
    }
    return null;
}

function getUserRole() {
    $db = getDB();
    if (!$db) return 0;
    $role = viewerFetchRoleBySession($db, session_id() ?: '');
    if ($role === null && isset($_COOKIE['PHPSESSID'])) {
        $role = viewerFetchRoleBySession($db, $_COOKIE['PHPSESSID']);
    }
    return $role ?? 0;
}

function get_current_user_from_sessions(): string {
    $db = getDB();
    if (!$db) return 'default';
    $email = viewerFindEmail($db, session_id() ?: '');
    if (!$email && isset($_COOKIE['PHPSESSID'])) {
        $email = viewerFindEmail($db, $_COOKIE['PHPSESSID']);
    }
    if ($email) {
        $username = explode('@', $email)[0] ?? 'user';
        $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
        return $username ?: 'default';
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
}

function getUserRoleByEmail($email): int {
    $db = getDB();
    if (!$db || !$email) return 0;
    try {
        $stmt = $db->prepare('SELECT role FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['role'])) return (int)$row['role'];
    } catch (Exception $e) {
        error_log('Failed to get user role: ' . $e->getMessage());
    }
    return 0;
}
