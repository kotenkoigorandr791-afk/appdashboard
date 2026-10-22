<?php
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('smart_home');
require __DIR__ . '/core/app.php';
include __DIR__ . '/views/main.php';
include __DIR__ . '/views/scripts.php';
?>
</body>
</html>
