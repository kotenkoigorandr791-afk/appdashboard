<?php
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
