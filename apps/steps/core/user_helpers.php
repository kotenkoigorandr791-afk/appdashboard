<?php
// ─── Фото/роль (используется get_current_user_email из auth_common) ───────────
function getUserPhoto(string $email): ?string {
    $db = getDB();
    if (!$db) return null;
    try {
        $stmt = $db->prepare('SELECT photo FROM users WHERE email = :e');
        $stmt->bindValue(':e', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row && !empty($row['photo'])) return $row['photo'];
    } catch (Exception $e) { }
    return null;
}

function getUserRoleByEmail(string $email): int {
    $db = getDB();
    if (!$db) return 0;
    try {
        $stmt = $db->prepare('SELECT role FROM users WHERE email = :e');
        $stmt->bindValue(':e', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['role'])) return (int)$row['role'];
    } catch (Exception $e) { }
    return 0;
}

function isUserRoot(): bool {
    $db = getDB();
    if (!$db) return false;
    $ids = array_unique(array_filter([session_id(), $_COOKIE['PHPSESSID'] ?? '']));
    foreach ($ids as $sid) {
        try {
            $stmt = $db->prepare(
                'SELECT u.role FROM active_sessions s JOIN users u ON s.email = u.email WHERE s.session_id = :sid'
            );
            $stmt->bindValue(':sid', $sid, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['role'])) return $row['role'] === 2;
        } catch (Exception $e) { }
    }
    return false;
}

function logError(string $msg): void { error_log("[StepsApp] $msg"); }

function checkDirectory(string $dir): void {
    if (is_link($dir) && !file_exists($dir)) {
        @unlink($dir);
    }
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        logError("Failed to create directory $dir");
        header('Content-Type: application/json');
        exit(json_encode(['status' => 'error', 'message' => 'Failed to create directory']));
    }
    if (!is_writable($dir)) {
        logError("Directory not writable: $dir");
        header('Content-Type: application/json');
        exit(json_encode(['status' => 'error', 'message' => 'Directory is not writable']));
    }
}
