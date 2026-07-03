<?php
function getCurrentUserFromActiveSessions() {
    $db = getDB();
    if (!$db) return null;
    $session_id = session_id();
    foreach ([$session_id, $_COOKIE['PHPSESSID'] ?? null] as $sid) {
        if (!$sid) continue;
        try {
            $st = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :s');
            $st->bindValue(':s', $sid, SQLITE3_TEXT);
            $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
            if ($r && isset($r['email'])) return $r['email'];
        } catch (Exception $e) {}
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
}

function getSafeFolderName($email) {
    $n = str_replace(['@','.'], '_', $email);
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $n);
}

function getUserRoleByEmail($email) {
    $db = getDB();
    if (!$db || !$email) return 0;
    try {
        $st = $db->prepare('SELECT role FROM users WHERE email = :e');
        $st->bindValue(':e', $email, SQLITE3_TEXT);
        $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
        if ($r && isset($r['role'])) return (int)$r['role'];
    } catch (Exception $e) {}
    return 0;
}

function isUserRoot() {
    $db = getDB();
    if (!$db) return false;
    $session_id = session_id();
    $sql = "SELECT u.role FROM active_sessions s JOIN users u ON s.email = u.email WHERE s.session_id = :s";
    foreach ([$session_id, $_COOKIE['PHPSESSID'] ?? null] as $sid) {
        if (!$sid) continue;
        try {
            $st = $db->prepare($sql);
            $st->bindValue(':s', $sid, SQLITE3_TEXT);
            $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
            if ($r && isset($r['role'])) return $r['role'] === 2;
        } catch (Exception $e) {}
    }
    return false;
}

define('APPS_DATA_DIR', __DIR__ . '/../users/');
function logError($message) { error_log("[ExpenseApp] $message"); }
function checkDirectory($dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) { logError("Failed to create directory $dir"); exit(json_encode(['status'=>'error','message'=>'Failed to create directory'])); }
    }
    if (!is_writable($dir)) { logError("Directory $dir is not writable"); exit(json_encode(['status'=>'error','message'=>'Directory is not writable'])); }
}

$utilsDir   = __DIR__ . '/../utils';
$resultsDir = __DIR__ . '/../utils/results';
checkDirectory($utilsDir);
checkDirectory($resultsDir);
if (!file_exists(APPS_DATA_DIR)) mkdir(APPS_DATA_DIR, 0755, true);
$backupBaseDir = __DIR__ . '/../backups';
checkDirectory($backupBaseDir);
