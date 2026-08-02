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
            $stmt->bindValue(':session_id', $session_id, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['email'])) {
                $username = explode('@', $row['email'])[0] ?? 'user';
                $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
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
            $stmt->bindValue(':session_id', $cookie_session, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['email'])) {
                $username = explode('@', $row['email'])[0] ?? 'user';
                $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
                return $username ?: 'default';
            }
        } catch (Exception $e) {
            error_log("Database query failed: " . $e->getMessage());
        }
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
}

// Returns user role by email from users table
function getUserRoleByEmail(string $email): int {
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

// Returns true if current user has role = 2 (root/admin)
function isUserRoot(): bool {
    $db = getDB();
    if (!$db) {
        return false;
    }
    $session_id = session_id();
    $sql = "SELECT u.role
            FROM active_sessions s
            JOIN users u ON s.email = u.email
            WHERE s.session_id = :session_id";
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':session_id', $session_id, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['role'])) {
            return $row['role'] === 2;
        }

        if (isset($_COOKIE['PHPSESSID'])) {
            $cookie_session = $_COOKIE['PHPSESSID'];
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':session_id', $cookie_session, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['role'])) {
                return $row['role'] === 2;
            }
        }
    } catch (Exception $e) {
        error_log("Database query failed: " . $e->getMessage());
    }
    return false;
}

// Returns username and role from DB - compatibility with rest of app code
function get_current_user_data(): array {
    $db = getDB();
    $default = ['username' => 'default', 'role' => 0, 'photo' => '/default_avatar.png'];
    if (!$db) {
        return $default;
    }

    $session_id = session_id();
    $sql = "SELECT s.email, u.role, u.photo
            FROM active_sessions s
            JOIN users u ON s.email = u.email
            WHERE s.session_id = :session_id";

    $trySession = function(string $sid) use ($db, $sql): ?array {
        try {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':session_id', $sid, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['email'])) {
                $username = explode('@', $row['email'])[0] ?? 'user';
                $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
                return [
                    'username' => $username ?: 'default',
                    'role'     => (int)($row['role'] ?? 0),
                    'photo'    => resolve_user_photo_url($row['photo'] ?? 'default_avatar.png'),
                ];
            }
        } catch (Exception $e) {
            error_log("Database query failed: " . $e->getMessage());
        }
        return null;
    };

    if ($session_id) {
        $data = $trySession($session_id);
        if ($data !== null) return $data;
    }

    if (isset($_COOKIE['PHPSESSID'])) {
        $data = $trySession($_COOKIE['PHPSESSID']);
        if ($data !== null) return $data;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return [
        'username' => 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip),
        'role'     => 0,
        'photo'    => '/default_avatar.png',
    ];
}
