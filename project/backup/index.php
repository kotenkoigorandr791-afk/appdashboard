<?php
/**
 * project/backup/index.php — entry point for the Backup Manager.
 *
 * Handles both the JSON API (?api=...) and the standalone page. The Admin
 * Panel embeds the same panel markup directly (no iframe).
 */
declare(strict_types=1);

$backupAppRoot = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) {
    define('APP_ROOT', $backupAppRoot);
}
if (getcwd() !== APP_ROOT) {
    @chdir(APP_ROOT);
}

require_once $backupAppRoot . '/project/bootstrap.php';
require_once $backupAppRoot . '/lib/embed_context.php';
smart_log_bootstrap('dashboard');

$backupIsApi = isset($_GET['api']) && $_GET['api'] !== '';
$activeSession = getUserFromActiveSessions(session_id());
if (!isset($_SESSION['user']) && is_array($activeSession) && isset($activeSession['user_data'])) {
    $_SESSION['user'] = $activeSession['user_data'];
    $_SESSION['auth_time'] = $activeSession['last_activity'] ?? time();
}
if ($activeSession === null && !isset($_SESSION['user'])) {
    if ($backupIsApi) {
        if (!headers_sent()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    if (!headers_sent()) {
        http_response_code(401);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access denied</title></head><body><p>You must be logged in.</p><p><a href="/">Home</a></p></body></html>';
    exit;
}

adminPanelRequireGate();
// bootstrap.php normally provides apSecCsrfToken(); keep a local fallback so a
// partially bootstrapped request cannot die with "undefined function".
if (function_exists('apSecCsrfToken')) {
    $csrf = apSecCsrfToken();
} else {
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf = (string)$_SESSION['_csrf_token'];
}

require_once __DIR__ . '/i18n.php';

$supportedLangs = backup_supported_langs();
$lang = in_array($_GET['lang'] ?? '', $supportedLangs, true) ? (string)$_GET['lang'] : 'en';
$theme = ($_GET['theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_GET['color'] ?? '')) ? (string)$_GET['color'] : '#007bff';
$GLOBALS['theme'] = $theme;
$GLOBALS['accent'] = $accent;
$GLOBALS['lang'] = $lang;
$GLOBALS['embed'] = app_is_embedded_request();
$GLOBALS['backup_csrf'] = $csrf;
session_write_close();

$bkpLang = backup_boot_i18n($lang);
require_once __DIR__ . '/backend.php';
