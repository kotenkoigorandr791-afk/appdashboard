<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/views/header.php';
require __DIR__ . '/views/password.php';
require __DIR__ . '/views/error.php';
require __DIR__ . '/controllers/ShareController.php';

$controller = new ShareController();
$controller->handle();
