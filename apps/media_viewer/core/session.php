<?php
require_once __DIR__ . '/db.php';

function mvFindEmailBySession(AppDatabase $db, string $sessionId): ?string {
    if ($sessionId === '') return null;
    try {
        $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['email'])) {
            return $row['email'];
        }
    } catch (Exception $e) {
        error_log("Database query failed: " . $e->getMessage());
    }
    return null;
}

function get_current_user_from_sessions(): string {
    $db = getDB();
    if (!$db) return 'Guest';
    $email = mvFindEmailBySession($db, session_id() ?: '');
    if (!$email && isset($_COOKIE['PHPSESSID'])) {
        $email = mvFindEmailBySession($db, $_COOKIE['PHPSESSID']);
    }
    if ($email) {
        $username = explode('@', $email)[0] ?? 'user';
        $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
        return $username ?: 'Guest';
    }
    return 'Guest';
}

function getUserRoleByEmail($email): int {
    $db = getDB();
    if (!$db || !$email) return 0;
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

function isUserRoot(): bool {
    $db = getDB();
    if (!$db) return false;
    $sessionId = session_id();
    try {
        $sql = "SELECT u.role FROM active_sessions s JOIN users u ON s.email = u.email WHERE s.session_id = :session_id";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['role'])) {
            return (int)$row['role'] === 2;
        }
        if (isset($_COOKIE['PHPSESSID'])) {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':session_id', $_COOKIE['PHPSESSID'], SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['role'])) {
                return (int)$row['role'] === 2;
            }
        }
    } catch (Exception $e) {
        error_log("Database query failed: " . $e->getMessage());
    }
    return false;
}

function getCurrentUserPhoto(): string {
    $db = getDB();
    if (!$db) return '';
    $email = mvFindEmailBySession($db, session_id() ?: '');
    if (!$email && isset($_COOKIE['PHPSESSID'])) {
        $email = mvFindEmailBySession($db, $_COOKIE['PHPSESSID']);
    }
    if (!$email) return '';
    try {
        $stmt = $db->prepare('SELECT photo FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['photo']) && $row['photo'] !== 'default_avatar.png' && $row['photo'] !== '') {
            $photo = $row['photo'];
            // Local file path — serve through proxy
            if (strpos($photo, 'http://') !== 0 && strpos($photo, 'https://') !== 0) {
                // Verify the file exists before serving through proxy
                $basePath = realpath(__DIR__ . '/../../..');
                $fullPath = realpath($basePath . '/' . ltrim($photo, '/'));
                if ($fullPath && strncmp($fullPath, $basePath, strlen($basePath)) === 0 && is_file($fullPath)) {
                    return 'serve_avatar.php?path=' . rawurlencode(ltrim($photo, '/'));
                }
                return '';  // file doesn't exist — fall back to SVG icon
            }
            return $photo;
        }
    } catch (Exception $e) {
        error_log("Failed to get user photo: " . $e->getMessage());
    }
    return '';
}

function getCurrentUserRole(): int {
    $db = getDB();
    if (!$db) return 0;
    $email = mvFindEmailBySession($db, session_id() ?: '');
    if (!$email && isset($_COOKIE['PHPSESSID'])) {
        $email = mvFindEmailBySession($db, $_COOKIE['PHPSESSID']);
    }
    return $email ? getUserRoleByEmail($email) : 0;
}
