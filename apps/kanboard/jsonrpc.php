<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('kanboard-api');
require_once __DIR__ . '/integration/bootstrap.php';

kanboard_ensure_database();

require __DIR__ . '/app/common.php';
echo $container['api']->execute();
