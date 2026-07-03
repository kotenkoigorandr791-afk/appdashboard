<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/../smart_log_boot.php')){require_once $__d.'/../smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
if(session_status()===PHP_SESSION_NONE){session_start();}
define('API_KEY','VlPMGl445dR0JkzdMN6a');
define('APPS_DATA_DIR',__DIR__.'/../user_data/');
require_once __DIR__ . '/db.php';
