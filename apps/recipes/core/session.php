<?php
function getUserRoleByEmail($email): int
{
    $db = getDB();
    if (!$db || !$email) {
        return 0;
    }
    try {
        $stmt = $db->prepare('SELECT role FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['role'])) {
            return (int)$row['role'];
        }
    } catch (Throwable $e) {
    }
    return 0;
}
