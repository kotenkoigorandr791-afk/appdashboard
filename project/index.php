<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (getcwd() !== APP_ROOT) {
    chdir(APP_ROOT);
}

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/core.php';
require __DIR__ . '/oauth.php';
require __DIR__ . '/routes.php';
require __DIR__ . '/post_handlers.php';
require __DIR__ . '/runtime.php';
require __DIR__ . '/views/main.php';
