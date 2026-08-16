<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../../lib/redis.php';

function viewerSessionIds(): array {
    return array_values(array_unique(array_filter([session_id() ?: '', (string)($_COOKIE['PHPSESSID'] ?? '')])));
}

function viewerActiveSession(string $sessionId): ?array {
    if ($sessionId === '') return null;
    if ($redis = app_redis()) {
        $raw = $redis->get(app_redis_key('active_session:' . $sessionId));
        $row = $raw ? json_decode((string)$raw, true) : null;
        if (is_array($row)) return $row;
    }
    $db = getDB();
    if (!$db) return null;
    try {
        $stmt = $db->prepare('SELECT * FROM active_sessions WHERE session_id = :session_id LIMIT 1');
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['user_data'] = json_decode((string)($row['user_data'] ?? ''), true) ?: [];
            return $row;
        }
    } catch (Exception $e) {
        error_log('Database query failed: ' . $e->getMessage());
    }
    return null;
}

function viewerCurrentActiveSession(): ?array {
    foreach (viewerSessionIds() as $sessionId) {
        $row = viewerActiveSession($sessionId);
        if ($row) return $row;
    }
    return null;
}

function viewerFindEmail(AppDatabase $db, string $sessionId): ?string {
    if ($sessionId === '') return null;
    $session = viewerActiveSession($sessionId);
    if ($session && !empty($session['email'])) return (string)$session['email'];
    try {
        $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['email'])) return $row['email'];
    } catch (Exception $e) {
        error_log('Database query failed: ' . $e->getMessage());
    }
    return null;
}

function viewerFetchRoleBySession(AppDatabase $db, string $sessionId): ?int {
    if ($sessionId === '') return null;
    $session = viewerActiveSession($sessionId);
    if ($session && isset($session['user_data']['role'])) return (int)$session['user_data']['role'];
    try {
        $sql = "SELECT u.role FROM active_sessions s JOIN users u ON s.email = u.email WHERE s.session_id = :session_id";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['role'])) return (int)$row['role'];
    } catch (Exception $e) {
        error_log('Database query failed: ' . $e->getMessage());
    }
    return null;
}

function getUserRole() {
    $db = getDB();
    if (!$db) return 0;
    foreach (viewerSessionIds() as $sessionId) {
        $role = viewerFetchRoleBySession($db, $sessionId);
        if ($role !== null) return $role;
    }
    return 0;
}

function get_current_user_from_sessions(): string {
    $db = getDB();
    if (!$db) return 'default';
    $email = null;
    foreach (viewerSessionIds() as $sessionId) {
        $email = viewerFindEmail($db, $sessionId);
        if ($email) break;
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
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['role'])) return (int)$row['role'];
    } catch (Exception $e) {
        error_log('Failed to get user role: ' . $e->getMessage());
    }
    return 0;
}
