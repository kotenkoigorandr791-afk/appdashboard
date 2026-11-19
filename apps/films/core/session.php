<?php
// Load the active session row for a PHP session id.
// Sessions are stored in Redis by addUserToActiveSessions() whenever Redis is
// available; the SQL active_sessions table is only a fallback. Looking at the
// DB alone made every user resolve to user_<IP> on deployments with Redis.
function films_active_session_row(string $sessionId): ?array {
    if ($sessionId === '') {
        return null;
    }

    if (!function_exists('app_redis')) {
        require_once dirname(__DIR__, 3) . '/lib/redis.php';
    }
    $redis = app_redis();
    if ($redis) {
        try {
            $raw = $redis->get(app_redis_key('active_session:' . $sessionId));
            $row = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (is_array($row)) {
                return $row;
            }
        } catch (Throwable $e) {
            error_log('Redis session lookup failed: ' . $e->getMessage());
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

// Role and photo for an email, from the users table.
function films_user_role_and_photo(string $email): array {
    $db = getDB();
    if (!$db || $email === '') {
        return ['role' => 0, 'photo' => ''];
    }
    try {
        $stmt = $db->prepare('SELECT role, photo FROM users WHERE email = :email LIMIT 1');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return ['role' => (int)($row['role'] ?? 0), 'photo' => (string)($row['photo'] ?? '')];
        }
    } catch (Exception $e) {
        error_log("Failed to get user role: " . $e->getMessage());
    }
    return ['role' => 0, 'photo' => ''];
}

function get_current_user_from_sessions(): string {
    $data = get_current_user_data();
    return $data['username'] !== '' ? $data['username'] : 'default';
}

// Returns user role by email from users table
function getUserRoleByEmail(string $email): int {
    if ($email === 'root@root') {
        return 2;
    }
    return films_user_role_and_photo($email)['role'];
}

// Returns true if current user has role = 2 (root/admin)
function isUserRoot(): bool {
    return (get_current_user_data()['role'] ?? 0) >= 2;
}

// Returns username and role from the active session - compatibility with rest of app code
function get_current_user_data(): array {
    $session_ids = [];
    $session_id = session_id();
    if ($session_id !== '') {
        $session_ids[] = $session_id;
    }
    if (!empty($_COOKIE['PHPSESSID'])) {
        $session_ids[] = (string)$_COOKIE['PHPSESSID'];
    }
    $session_ids = array_values(array_unique($session_ids));

    foreach ($session_ids as $sid) {
        $row = films_active_session_row($sid);
        if ($row === null) {
            continue;
        }

        $userData = is_array($row['user_data'] ?? null) ? $row['user_data'] : [];
        $email = (string)($row['email'] ?? ($userData['email'] ?? ''));
        if ($email === '') {
            continue;
        }

        $username = explode('@', $email)[0] ?? 'user';
        $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
        $username = $username ?: 'user';

        if (array_key_exists('role', $userData)) {
            $role = (int)$userData['role'];
            $photo = (string)($userData['photo'] ?? '');
        } else {
            $user = films_user_role_and_photo($email);
            $role = $user['role'];
            $photo = $user['photo'];
        }
        if ($email === 'root@root') {
            $role = 2;
        }

        return [
            'username' => $username,
            'role'     => $role,
            'photo'    => resolve_user_photo_url($photo !== '' ? $photo : 'default_avatar.png'),
        ];
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return [
        'username' => 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip),
        'role'     => 0,
        'photo'    => '/default_avatar.png',
    ];
}
