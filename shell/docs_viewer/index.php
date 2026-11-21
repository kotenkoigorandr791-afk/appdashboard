<?php
/**
 * Documentation Viewer
 * Browse and read project documentation files with markdown rendering
 */
session_start();
require_once dirname(__DIR__, 2) . '/db.php';
require_once dirname(__DIR__, 2) . '/smart_log.php';
smart_log_bootstrap('dashboard');
require_once dirname(__DIR__, 2) . '/lib/embed_context.php';
function docViewerIsEmbeddedRequest(): bool { return app_is_embedded_request(); }
function docViewerDeny(int $httpCode, string $htmlMsg, string $jsonMsg = ''): void {
    $j = $jsonMsg !== '' ? $jsonMsg : $htmlMsg;
    if (isset($_GET['docs']) || isset($_GET['file'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode);
        echo json_encode(['error' => $j], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    http_response_code($httpCode);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access denied</title></head><body><p>'
        . htmlspecialchars($htmlMsg, ENT_QUOTES, 'UTF-8')
        . '</p><p><a href="/">Home</a></p></body></html>';
    exit;
}
$activeSession = getUserFromActiveSessions(session_id());
$docViewerLoggedIn = $activeSession !== null;
if (!$docViewerLoggedIn) {
    docViewerDeny(401, 'You must be logged in.', 'Unauthorized');
}
if (!isset($_SESSION['user']) && isset($activeSession['user_data'])) {
    $_SESSION['user'] = $activeSession['user_data'];
    $_SESSION['auth_time'] = $activeSession['last_activity'];
}
$docViewerRole = $docViewerLoggedIn ? (int)($_SESSION['user']['role'] ?? 0) : -1;
if ($docViewerRole < 1 && !docViewerIsEmbeddedRequest()) {
    docViewerDeny(403, 'Access denied.', 'Forbidden');
}
$docTheme = isset($_GET['theme']) && preg_match('/^(dark|light)$/', (string)$_GET['theme']) ? $_GET['theme'] : 'dark';
$docLang = isset($_GET['lang']) && preg_match('/^(en|ru|uk|be)$/', (string)$_GET['lang']) ? $_GET['lang'] : 'en';
$docColor = isset($_GET['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$_GET['color']) ? $_GET['color'] : '#007bff';
$lastActiveEmail = getLastActiveUser();
$defaultConfig = ['language' => $docLang, 'theme' => $docTheme, 'color' => $docColor];
$config = $defaultConfig;
if ($lastActiveEmail) {
    $users = loadUsers();
    if (isset($users[$lastActiveEmail])) {
        $userData = $users[$lastActiveEmail];
        $config = [
            'language' => $userData['language'] ?? $defaultConfig['language'],
            'theme' => $userData['theme'] ?? $defaultConfig['theme'],
            'color' => $userData['color'] ?? $defaultConfig['color']
        ];
    }
}
if (isset($_GET['theme'])) $config['theme'] = $docTheme;
if (isset($_GET['lang'])) $config['language'] = $docLang;
if (isset($_GET['color'])) $config['color'] = $docColor;
$language = $config['language'];
$theme = $config['theme'];
$color = $config['color'];
$translations = [];    $i18nPath = dirname(__DIR__) . '/i18n/' . preg_replace('/[^a-z0-9_-]/i', '', $language) . '.json';
if (is_readable($i18nPath)) {
    $translations = json_decode(file_get_contents($i18nPath), true) ?: [];
}
function t($key, $default = '') {
    global $translations;
    return $translations[$key] ?? $default;
}
if (isset($_GET['file'])) {
    $filename = preg_replace('/[^a-z0-9_.-]/i', '', $_GET['file']);
    if ($filename === '') {
        docViewerDeny(400, 'Invalid filename', 'Invalid filename');
    }
    $filePath = dirname(__DIR__, 2) . '/docs/' . $filename;
    if (!is_readable($filePath)) {
        docViewerDeny(404, 'File not found', 'File not found');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'content' => file_get_contents($filePath),
        'filename' => $filename
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once __DIR__ . '/data.php';
$docsFiles = docViewerLoadDocs();
require __DIR__ . '/views/page.php';
