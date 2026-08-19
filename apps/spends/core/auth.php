<?php
require_once dirname(__DIR__, 3) . '/lib/redis.php';
function spendsSessionIds(): array {
    $ids = [];
    foreach ([session_id(), $_COOKIE['PHPSESSID'] ?? null] as $sid) {
        if (!is_string($sid) || $sid === '' || isset($ids[$sid])) continue;
        $ids[$sid] = $sid;
    }
    return array_values($ids);
}
function spendsActiveSessionRowById(string $sid): ?array {
    if ($sid === '') return null;
    if ($redis = app_redis()) {
        $raw = $redis->get(app_redis_key('active_session:' . $sid));
        if ($raw) {
            $row = json_decode((string)$raw, true);
            if (is_array($row)) return $row;
        }
    }
    $db = getDB();
    if (!$db) return null;
    try {
        $st = $db->prepare('SELECT * FROM active_sessions WHERE session_id = :s LIMIT 1');
        $st->bindValue(':s', $sid, PDO::PARAM_STR);
        $row = $st->execute()->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (isset($row['user_data']) && is_string($row['user_data'])) {
                $decoded = json_decode($row['user_data'], true);
                $row['user_data'] = is_array($decoded) ? $decoded : [];
            }
            return $row;
        }
    } catch (Exception $e) {}
    return null;
}
function spendsActiveSessionRow(): ?array {
    foreach (spendsSessionIds() as $sid) {
        $row = spendsActiveSessionRowById($sid);
        if ($row) return $row;
    }
    return null;
}
function getCurrentUserFromActiveSessions() {
    $sessionEmail = $_SESSION['user']['email'] ?? $_SESSION['email'] ?? null;
    if (is_string($sessionEmail) && $sessionEmail !== '') return $sessionEmail;
    $row = spendsActiveSessionRow();
    if (!empty($row['email']) && is_string($row['email'])) return $row['email'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
}
function getSafeFolderName($email) {
    $n = str_replace(['@', '.'], '_', $email);
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
    $row = spendsActiveSessionRow();
    $sessionRole = $row['user_data']['role'] ?? null;
    if ($sessionRole !== null) return (int)$sessionRole === 2;
    $email = $row['email'] ?? getCurrentUserFromActiveSessions();
    if (is_string($email) && $email !== '') return getUserRoleByEmail($email) === 2;
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
