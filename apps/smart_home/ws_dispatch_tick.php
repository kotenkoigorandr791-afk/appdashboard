<?php
if (PHP_SAPI !== 'cli') {
    exit(0);
}
require dirname(__DIR__, 2) . '/webssh/vendor/autoload.php';
require __DIR__ . '/SmartHomeWsHandler.php';
$h = new SmartHomeWsHandler(__DIR__);
$h->tick();
