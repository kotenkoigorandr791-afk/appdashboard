<?php
function getCurrentUserFromActiveSessions() {
    $db = getDB();
    if (!$db) return null;
    $session_id = session_id();
    foreach ([$session_id, $_COOKIE['PHPSESSID'] ?? null] as $sid) {
        if (!$sid) continue;
        try {
            $st = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :s');
            $st->bindValue(':s', $sid, PDO::PARAM_STR);
            $r = $st->execute()->fetch(PDO::FETCH_ASSOC);
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
        $st->bindValue(':e', $email, PDO::PARAM_STR);
        $r = $st->execute()->fetch(PDO::FETCH_ASSOC);
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
            $st->bindValue(':s', $sid, PDO::PARAM_STR);
            $r = $st->execute()->fetch(PDO::FETCH_ASSOC);
            if ($r && isset($r['role'])) return $r['role'] === 2;
        } catch (Exception $e) {}
    }
    return false;
}

define('SPENDS_RUNTIME_DIR_MODE', 02775);
function logError($message) { error_log("[ExpenseApp] $message"); }

function spends_storage_helper_path(): string {
    return dirname(__DIR__, 3) . '/storage_helper.php';
}

function spends_appdata_subdir(string $name): ?string {
    $helper = spends_storage_helper_path();
    if (!is_file($helper)) return null;
    require_once $helper;
    try {
        $base = appdata_app_base_dir('spends');
    } catch (Throwable $e) {
        return null;
    }
    $dir = $name === '' ? rtrim($base, '/') : (rtrim($base, '/') . '/' . $name);
    if (!is_dir($dir)) @mkdir($dir, SPENDS_RUNTIME_DIR_MODE, true);
    @chmod($dir, SPENDS_RUNTIME_DIR_MODE);
    return is_dir($dir) ? $dir : null;
}

function spends_dir_writable(string $dir): bool {
    if ($dir === '' || !is_dir($dir)) return false;
    if (@is_writable($dir)) return true;
    $probe = rtrim($dir, '/') . '/.write_probe_' . getmypid();
    $ok = @file_put_contents($probe, '1') !== false;
    if ($ok) @unlink($probe);
    return $ok;
}

function spends_ensure_web_link(string $linkPath, string $targetPath): void {
    if ($targetPath === '' || !is_dir($targetPath)) return;
    if (is_link($linkPath)) {
        if (@readlink($linkPath) === $targetPath) return;
        @unlink($linkPath);
        @symlink($targetPath, $linkPath);
        return;
    }
    if (file_exists($linkPath)) return;
    @symlink($targetPath, $linkPath);
}

function spends_runtime_dir(string $name): string {
    $legacy = __DIR__ . '/../' . $name;
    $appdata = spends_appdata_subdir($name);
    if ($appdata !== null) {
        spends_ensure_web_link($legacy, $appdata);
        @chmod($appdata, SPENDS_RUNTIME_DIR_MODE);
        return rtrim($appdata, '/') . '/';
    }
    if (is_link($legacy)) {
        $resolved = realpath($legacy) ?: $legacy;
        if (is_dir($resolved)) {
            @chmod($resolved, SPENDS_RUNTIME_DIR_MODE);
            return rtrim($resolved, '/') . '/';
        }
    }
    logError("APPDATA unavailable for spends/$name");
    return rtrim($legacy, '/') . '/';
}

function checkDirectory($dir) {
    if (!file_exists($dir)) {
        if (!@mkdir($dir, SPENDS_RUNTIME_DIR_MODE, true) && !is_dir($dir)) {
            logError("Failed to create directory $dir");
            return false;
        }
    }
    @chmod($dir, SPENDS_RUNTIME_DIR_MODE);
    if (!spends_dir_writable($dir)) {
        logError("Directory $dir is not writable");
        return false;
    }
    return true;
}

if (!defined('APPS_DATA_DIR')) {
    define('APPS_DATA_DIR', spends_runtime_dir('users'));
}
$backupBaseDir = rtrim(spends_runtime_dir('backups'), '/');
checkDirectory(APPS_DATA_DIR);
checkDirectory($backupBaseDir);
