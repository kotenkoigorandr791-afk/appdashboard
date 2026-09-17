<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('kanboard');
require_once __DIR__ . '/integration/bootstrap.php';

kanboard_ensure_database();
kanboard_require_login_or_redirect();

use Kanboard\Core\Controller\Runner;

try {
    require __DIR__ . '/app/common.php';
    $container['router']->dispatch();
    $runner = new Runner($container);
    $runner->execute();
} catch (Throwable $e) {
    if (function_exists('app_sentry_capture_exception')) {
        app_sentry_capture_exception($e);
    }
    echo htmlspecialchars('Internal Error: ' . $e->getMessage(), ENT_QUOTES, 'UTF-8', false);
}
