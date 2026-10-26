<?php
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('spends');
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
require_once dirname(__DIR__, 2) . '/lib/app_session.php';
app_session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/user_db.php';
require_once __DIR__ . '/core/subscriptions.php';
require_once __DIR__ . '/core/goals.php';
require_once __DIR__ . '/core/locale.php';
require_once __DIR__ . '/core/user_state.php';
require __DIR__ . '/controllers/Handlers.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<?php include __DIR__ . '/views/head.php'; ?>
<?php include __DIR__ . '/views/body.php'; ?>
<?php include __DIR__ . '/views/scripts.php'; ?>
</body>
</html>
