#!/usr/bin/env php
<?php
declare(strict_types=1);
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}
require APP_ROOT . '/project/bin/oauth_configure_services.php';
exit(oauth_configure_services());
