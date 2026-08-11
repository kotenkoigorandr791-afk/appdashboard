<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (getcwd() !== APP_ROOT) {
    chdir(APP_ROOT);
}

// Handle sharedFile parameter (lightweight entry for public shared-file links).
// Load the same bootstrap chain as the normal flow so routes.php has access
// to db.php helpers (parseTrustedDeviceHintCookie, getConfig, ...), the session,
// and the OAuth helpers (oauthRequestPath, oauthStartUrl, oauth_dispatch).
if (isset($_GET['sharedFile'])) {
    require __DIR__ . '/bootstrap.php';
    require __DIR__ . '/core.php';
    require __DIR__ . '/oauth.php';
    require __DIR__ . '/oauth_login.php';
    require __DIR__ . '/routes.php';
    exit;
}

$__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__pathLower = strtolower($__path);
if ($__pathLower === '/webdav' || str_starts_with($__pathLower, '/webdav/') || $__pathLower === '/apps/cloud/webdav.php' || str_starts_with($__pathLower, '/apps/cloud/webdav.php/')) {
    $_SERVER['SCRIPT_NAME'] = str_starts_with($__pathLower, '/webdav') ? '/webdav' : '/apps/cloud/webdav.php';
    $_SERVER['PHP_SELF'] = $__path;
    require APP_ROOT . '/apps/cloud/webdav.php';
    exit;
}
$__performancePhp = [
    '/project/performance/index.php' => APP_ROOT . '/project/performance/index.php',
    '/project/performance/server_monitor.php' => APP_ROOT . '/project/performance/server_monitor.php',
    '/project/performance/monitor_stats.php' => APP_ROOT . '/project/performance/monitor_stats.php',
];
if (isset($__performancePhp[$__pathLower])) {
    $_SERVER['SCRIPT_NAME'] = $__pathLower;
    $_SERVER['PHP_SELF'] = $__path;
    require $__performancePhp[$__pathLower];
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    if ($__path === '/' || $__path === '' || $__pathLower === '/news') {
        header('Content-Type: text/html; charset=UTF-8');
        $__homeHtml = file_get_contents(APP_ROOT . '/home/home.html');
        if ($__homeHtml !== false) {
            $__cssVersion = is_file(APP_ROOT . '/home/home.css') ? (string)filemtime(APP_ROOT . '/home/home.css') : '1';
            $__jsVersion = is_file(APP_ROOT . '/home/home.js') ? (string)filemtime(APP_ROOT . '/home/home.js') : '1';
            $__homeHtml = str_replace('href="/home/home.css"', 'href="/home/home.css?v=' . $__cssVersion . '"', $__homeHtml);
            $__homeHtml = str_replace('src="/home/home.js"', 'src="/home/home.js?v=' . $__jsVersion . '"', $__homeHtml);
            echo $__homeHtml;
        }
        exit;
    }
    $__homeAssets = [
        '/home/home.css' => ['home/home.css', 'text/css; charset=UTF-8'],
        '/home/home.js' => ['home/home.js', 'application/javascript; charset=UTF-8'],
    ];
    if (isset($__homeAssets[$__path])) {
        [$__file, $__type] = $__homeAssets[$__path];
        $__target = APP_ROOT . '/' . $__file;
        if (is_file($__target)) {
            header('Content-Type: ' . $__type);
            header('Content-Length: ' . filesize($__target));
            header('Cache-Control: public, max-age=3600');
            readfile($__target);
            exit;
        }
    }
    if (str_starts_with($__pathLower, '/home/') || str_starts_with($__pathLower, '/preview/') || str_starts_with($__pathLower, '/project/assets/') || str_starts_with($__pathLower, '/project/performance/') || str_starts_with($__pathLower, '/project/onboarding/') || str_starts_with($__pathLower, '/project/icon')) {
        $__relative = ltrim(rawurldecode($__path), '/');
        $__target = realpath(APP_ROOT . '/' . $__relative);
        $__root = realpath(APP_ROOT);
        if ($__target && $__root && str_starts_with($__target, $__root . DIRECTORY_SEPARATOR) && is_file($__target)) {
            $__ext = strtolower(pathinfo($__target, PATHINFO_EXTENSION));
            if (str_starts_with($__pathLower, '/project/performance/') && !in_array($__ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'js', 'css', 'ttf', 'woff', 'woff2', 'json', 'map'], true)) {
                http_response_code(404);
                exit;
            }
            if (str_starts_with($__pathLower, '/project/onboarding/') && !in_array($__ext, ['js', 'css'], true)) {
                http_response_code(404);
                exit;
            }
            $__types = [
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'gif' => 'image/gif',
                'ico' => 'image/x-icon',
                'js' => 'application/javascript; charset=UTF-8',
                'css' => 'text/css; charset=UTF-8',
                'ttf' => 'font/ttf',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'json' => 'application/json; charset=UTF-8',
                'map' => 'application/json; charset=UTF-8',
            ];
            header('Content-Type: ' . ($__types[$__ext] ?? 'application/octet-stream'));
            header('Content-Length: ' . filesize($__target));
            header('Cache-Control: public, max-age=3600');
            readfile($__target);
            exit;
        }
        http_response_code(404);
        exit;
    }
}

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/core.php';
require __DIR__ . '/oauth.php';
require __DIR__ . '/oauth_login.php';
require __DIR__ . '/routes.php';
require __DIR__ . '/post_handlers.php';
require __DIR__ . '/runtime.php';
require __DIR__ . '/views/main.php';
