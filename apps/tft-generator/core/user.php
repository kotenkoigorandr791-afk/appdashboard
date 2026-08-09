<?php
require_once __DIR__ . '/../../../lib/app_db.php';
require_once __DIR__ . '/../../../lib/user_schema_pg.php';
require_once __DIR__ . '/db.php';
if (!defined('USER_DATA_BASE_DIR')) {
    $tftHelper = dirname(__DIR__, 3) . '/storage_helper.php';
    $tftData = null;
    if (is_file($tftHelper)) {
        require_once $tftHelper;
        try {
            $tftData = rtrim(appdata_app_base_dir('tft-generator'), '/') . '/userdata';
        } catch (Throwable $e) {
            $tftData = null;
        }
    }
    if ($tftData === null) {
        $tftLink = __DIR__ . '/../userdata';
        $tftData = (is_link($tftLink) && realpath($tftLink)) ? realpath($tftLink) : $tftLink;
    }
    if (!is_dir($tftData)) @mkdir($tftData, 0775, true);
    define('USER_DATA_BASE_DIR', $tftData);
}
function getCurrentUserEmail(): string {
    $db = getDB();
    if (!$db) {
        return '';
    }
    $ids = array_unique(array_filter([session_id(), $_COOKIE['PHPSESSID'] ?? '']));
    foreach ($ids as $sid) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid');
            $stmt->bindValue(':sid', $sid, PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['email'])) {
                return (string)$row['email'];
            }
        } catch (Exception $e) {
            error_log('getCurrentUserEmail: ' . $e->getMessage());
        }
    }
    return '';
}
function get_current_user_from_sessions(): string {
    $email = getCurrentUserEmail();
    if ($email !== '') {
        return emailToFolderName($email);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
}
function emailToFolderName(string $email): string {
    return preg_replace('/[@.]/', '_', $email);
}
function getUserDB(): ?AppDatabase {
    static $userDb = null;
    if ($userDb !== null) return $userDb;
    $email = getCurrentUserEmail();
    if ($email === '') $email = 'guest@local';
    $folderName = emailToFolderName($email);
    $userDir = USER_DATA_BASE_DIR . '/' . $folderName;
    if (!is_dir($userDir)) mkdir($userDir, 0775, true);
    try {
        $userDb = app_user_db_connect('tft', $email);
        app_tft_migrate_sqlite_file($userDir, $userDb);
    } catch (Exception $e) {
        error_log('User DB init failed: ' . $e->getMessage());
        return null;
    }
    return $userDb;
}
function app_tft_migrate_sqlite_file(string $userDir, AppDatabase $db): void {
    $path = $userDir . '/data.sqlite3';
    if (!is_file($path)) return;
    $cnt = (int)$db->querySingle('SELECT COUNT(*) FROM config');
    if ($cnt > 0) return;
    try {
        $sqlite = new SQLite3($path);
        $sqlite->enableExceptions(true);
        foreach (['config', 'canvas'] as $table) {
            $res = @$sqlite->query("SELECT key, value FROM \"$table\"");
            if (!$res) continue;
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $st = $db->prepare("INSERT INTO \"$table\" (key, value) VALUES (:k,:v) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value");
                $st->bindValue(':k', (string)$row['key'], PDO::PARAM_STR);
                $st->bindValue(':v', (string)$row['value'], PDO::PARAM_STR);
                $st->execute();
            }
        }
        $sqlite->close();
    } catch (Throwable $e) {}
}
