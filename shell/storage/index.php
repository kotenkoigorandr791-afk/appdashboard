<?php
/**
 * shell/storage/index.php — Storage Manager entry point.
 *
 * Handles the JSON API (?api=...) and the standalone page. The Admin Panel
 * embeds the same panel markup directly.
 */
declare(strict_types=1);

$storageAppRoot = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) define('APP_ROOT', $storageAppRoot);
if (getcwd() !== APP_ROOT) @chdir(APP_ROOT);

require_once $storageAppRoot . '/shell/bootstrap.php';
require_once $storageAppRoot . '/lib/embed_context.php';
require_once $storageAppRoot . '/lib/Storage/bootstrap.php';
smart_log_bootstrap('dashboard');

$storageIsApi = isset($_GET['api']) && $_GET['api'] !== '';
$activeSession = getUserFromActiveSessions(session_id());
if (!isset($_SESSION['user']) && is_array($activeSession) && isset($activeSession['user_data'])) {
    $_SESSION['user'] = $activeSession['user_data'];
    $_SESSION['auth_time'] = $activeSession['last_activity'] ?? time();
}
if ($activeSession === null && !isset($_SESSION['user'])) {
    if ($storageIsApi) {
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
$csrf = function_exists('apSecCsrfToken') ? apSecCsrfToken() : (string)($_SESSION['_csrf_token'] ?? '');
if (!headers_sent()) header('X-CSRF-Token: ' . $csrf);
$GLOBALS['storage_csrf'] = $csrf;
session_write_close();

require_once __DIR__ . '/backend.php';
