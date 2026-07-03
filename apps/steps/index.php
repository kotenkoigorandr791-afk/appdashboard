<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/db_helpers.php';
require_once __DIR__ . '/core/date_utils.php';
require_once __DIR__ . '/core/settings.php';
require_once __DIR__ . '/models/StepsModel.php';
require_once __DIR__ . '/core/user_helpers.php';
require __DIR__ . '/core/init.php';
require __DIR__ . '/controllers/VideoBootstrap.php';
require __DIR__ . '/controllers/PostHandlers.php';

include __DIR__ . '/views/preview.php';
?>
<?php include __DIR__ . '/views/head.php'; ?>
<?php include __DIR__ . '/views/body.php'; ?>
<?php include __DIR__ . '/views/scripts.php'; ?>
