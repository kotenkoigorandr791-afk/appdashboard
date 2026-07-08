<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (getcwd() !== APP_ROOT) {
    chdir(APP_ROOT);
}

$__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__pathLower = strtolower($__path);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    if ($__path === '/' || $__path === '' || $__pathLower === '/news') {
        header('Content-Type: text/html; charset=UTF-8');
        readfile(APP_ROOT . '/home/home.html');
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
}

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/core.php';
require __DIR__ . '/oauth.php';
require __DIR__ . '/routes.php';
require __DIR__ . '/post_handlers.php';
require __DIR__ . '/runtime.php';
require __DIR__ . '/views/main.php';
