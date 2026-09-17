<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('kanboard-api');
require_once __DIR__ . '/integration/bootstrap.php';

kanboard_ensure_database();

try {
    require __DIR__ . '/app/common.php';
    echo $container['api']->execute();
} catch (Throwable $e) {
    if (function_exists('app_sentry_capture_exception')) {
        app_sentry_capture_exception($e);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Internal Error: ' . $e->getMessage();
}
