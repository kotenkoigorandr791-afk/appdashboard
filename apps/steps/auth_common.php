<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
if (defined('STEPS_AUTH_COMMON_LOADED')) {
    return;
}
define('STEPS_AUTH_COMMON_LOADED', 1);
require_once __DIR__ . '/../../lib/app_db.php';
if (!function_exists('getDB')) {
    function getDB(): ?AppDatabase {
        static $db = null;
        if ($db === null) {
            try {
                $db = app_main_db();
            } catch (Exception $e) {
                error_log('Auth DB connection failed: ' . $e->getMessage());
                return null;
            }
        }
        return $db;
    }
}
function get_current_user_email(): ?string {
    $sessionEmail = $_SESSION['user']['email'] ?? $_SESSION['email'] ?? null;
    if (is_string($sessionEmail) && $sessionEmail !== '') return $sessionEmail;
    $db = getDB();
    if (!$db) {
        return null;
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
            error_log('get_current_user_email: ' . $e->getMessage());
        }
    }
    return null;
}
function emailToFolderName(string $email): string {
    $n = str_replace(['@', '.'], '_', $email);
    $n = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $n);
    return $n !== '' ? $n : 'default_user';
}
function get_current_user_folder(): string {
    $email = get_current_user_email();
    if ($email) {
        return emailToFolderName($email);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
}
