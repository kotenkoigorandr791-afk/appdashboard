<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
require_once __DIR__ . '/../../lib/admin_security.php';
session_start();
adminPanelRequireGate();
session_write_close();
$query = $_GET;
$query['section'] = 'monitorCharts';
$target = 'index.php?' . http_build_query($query);
header('Location: ' . $target, true, 302);
exit;
