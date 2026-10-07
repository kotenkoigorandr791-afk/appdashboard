<?php
// Photo/role (uses get_current_user_email from auth_common)
function getUserPhoto(string $email): ?string {
    $db = getDB();
    if (!$db) return null;
    try {
        $stmt = $db->prepare('SELECT photo FROM users WHERE email = :e');
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['photo'])) return $row['photo'];
    } catch (Exception $e) { }
    return null;
}

function logError(string $msg): void { error_log("[StepsApp] $msg"); }

function steps_appdata_dir(string $subdir = ''): ?string {
    $helper = dirname(__DIR__, 3) . '/storage_helper.php';
    if (!is_file($helper)) return null;
    require_once $helper;
    try {
        $base = appdata_app_base_dir('steps');
    } catch (Throwable $e) {
        return null;
    }
    $dir = $subdir === '' ? rtrim($base, '/') : (rtrim($base, '/') . '/' . $subdir);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @chmod($dir, 0775);
    return is_dir($dir) ? $dir : null;
}

function checkDirectory(string $dir): bool {
    if (is_link($dir) && !file_exists($dir)) {
        @unlink($dir);
    }
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        $fallback = steps_appdata_dir('');
        if ($fallback && is_writable($fallback)) {
            logError("Failed to create directory $dir; using appdata $fallback");
            return true;
        }
        logError("Failed to create directory $dir");
        return false;
    }
    if (!is_writable($dir)) {
        @chmod($dir, 0775);
    }
    if (!is_writable($dir)) {
        logError("Directory not writable: $dir");
        return false;
    }
    return true;
}
