<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (getcwd() !== APP_ROOT) {
    chdir(APP_ROOT);
}

$__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__pathLower = strtolower($__path);
if ($__pathLower === '/webdav' || str_starts_with($__pathLower, '/webdav/') || $__pathLower === '/apps/cloud/webdav.php' || str_starts_with($__pathLower, '/apps/cloud/webdav.php/')) {
    $_SERVER['SCRIPT_NAME'] = str_starts_with($__pathLower, '/webdav') ? '/webdav' : '/apps/cloud/webdav.php';
    $_SERVER['PHP_SELF'] = $__path;
    require APP_ROOT . '/apps/cloud/webdav.php';
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
    if (str_starts_with($__pathLower, '/home/') || str_starts_with($__pathLower, '/preview/')) {
        $__relative = ltrim(rawurldecode($__path), '/');
        $__target = realpath(APP_ROOT . '/' . $__relative);
        $__root = realpath(APP_ROOT);
        if ($__target && $__root && str_starts_with($__target, $__root . DIRECTORY_SEPARATOR) && is_file($__target)) {
            $__ext = strtolower(pathinfo($__target, PATHINFO_EXTENSION));
            $__types = [
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                'gif' => 'image/gif',
                'ico' => 'image/x-icon',
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
require __DIR__ . '/routes.php';
require __DIR__ . '/post_handlers.php';
require __DIR__ . '/runtime.php';
require __DIR__ . '/views/main.php';
