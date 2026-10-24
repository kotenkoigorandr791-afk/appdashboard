<?php
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('recipes');
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
require_once __DIR__ . '/../../lib/app_session.php';
app_session_start();

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/schema.php';
require_once __DIR__ . '/core/image.php';
require_once __DIR__ . '/models/Recipe.php';
require_once __DIR__ . '/core/session.php';
require __DIR__ . '/core/init.php';
require __DIR__ . '/controllers/get_handlers.php';
require_once __DIR__ . '/core/translate.php';
require __DIR__ . '/controllers/post_handlers.php';
require __DIR__ . '/core/post_init.php';
?>

<?php include __DIR__ . '/views/head.php'; ?>
<?php include __DIR__ . '/views/body.php'; ?>
<?php include __DIR__ . '/views/scripts.php'; ?>
</body>
</html>
