<?php
require_once __DIR__ . '/../../../lib/redis.php';

function monitor_current_session_ids(): array {
    $ids = [];
    $sid = session_id();
    if ($sid !== '') {
        $ids[] = $sid;
    }
    $cookieSid = (string)($_COOKIE['PHPSESSID'] ?? '');
    if ($cookieSid !== '') {
        $ids[] = $cookieSid;
    }
    return array_values(array_unique($ids));
}

function monitor_get_active_session(string $sessionId): ?array {
    if ($sessionId === '') {
        return null;
    }
    if (function_exists('app_redis') && ($redis = app_redis())) {
        $raw = $redis->get(app_redis_key('active_session:' . $sessionId));
        $row = $raw ? json_decode((string)$raw, true) : null;
        if (is_array($row)) {
            return $row;
        }
    }
    $db = getDB();
    if (!$db) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM active_sessions WHERE session_id = :session_id LIMIT 1');
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['user_data'] = json_decode((string)($row['user_data'] ?? ''), true) ?: [];
        return $row;
    } catch (Exception $e) {
        error_log("Database query failed: " . $e->getMessage());
        return null;
    }
}

function monitor_current_active_session(): ?array {
    foreach (monitor_current_session_ids() as $sessionId) {
        $row = monitor_get_active_session($sessionId);
        if ($row) {
            return $row;
        }
    }
    return null;
}

function monitor_current_user_email(): ?string {
    $row = monitor_current_active_session();
    $email = is_array($row) ? (string)($row['email'] ?? '') : '';
    return $email !== '' ? $email : null;
}

function monitor_current_user_role(?string $email = null): int {
    $row = monitor_current_active_session();
    $sessionRole = $row['user_data']['role'] ?? null;
    if ($sessionRole !== null) {
        return (int)$sessionRole;
    }
    $email = $email ?: (is_array($row) ? (string)($row['email'] ?? '') : '');
    if ($email === 'root@root') {
        return 2;
    }
    return getUserRoleByEmail($email);
}

function get_current_user_from_sessions(): string {
    $email = monitor_current_user_email();
    if (!$email) return 'Guest';
    $username = explode('@', $email)[0] ?? 'user';
    $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
    return $username ?: 'Guest';
}

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
        error_log("Failed to get user role: " . $e->getMessage());
    }
    
    return 0;
}

function isUserRoot(): bool {
    return monitor_current_user_role() >= 2;
}
