<?php
function get_current_user_from_sessions(): string {
    $db = getDB();
    if (!$db) {
        return 'Guest';
    }
    
    $session_id = session_id();
    
    if ($session_id) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
            $stmt->bindValue(':session_id', $session_id, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['email'])) {
                $email = $row['email'];
                $username = explode('@', $email)[0] ?? 'user';
                $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
                return $username ?: 'Guest';
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
                $email = $row['email'];
                $username = explode('@', $email)[0] ?? 'user';
                $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
                return $username ?: 'Guest';
            }
        } catch (Exception $e) {
            error_log("Database query failed: " . $e->getMessage());
        }
    }
    
    return 'Guest';
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
