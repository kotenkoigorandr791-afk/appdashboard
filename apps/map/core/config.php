<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/../smart_log_boot.php')){require_once $__d.'/../smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
if(session_status()===PHP_SESSION_NONE){session_start();}
define('API_KEY','VlPMGl445dR0JkzdMN6a');
$mapHelper = dirname(__DIR__, 3) . '/storage_helper.php';
$mapData = null;
if (is_file($mapHelper)) {
    require_once $mapHelper;
    try { $mapData = rtrim(appdata_app_base_dir('map'), '/') . '/user_data/'; } catch (Throwable $e) { $mapData = null; }
}
if ($mapData === null) {
    $mapLink = __DIR__ . '/../user_data';
    $mapData = (is_link($mapLink) && realpath($mapLink)) ? (rtrim(realpath($mapLink), '/') . '/') : (__DIR__ . '/../user_data/');
}
define('APPS_DATA_DIR', $mapData);
if (!is_dir(APPS_DATA_DIR)) @mkdir(APPS_DATA_DIR, 0755, true);
$mapUserLink = __DIR__ . '/../user_data';
if (!file_exists($mapUserLink) && is_dir(APPS_DATA_DIR)) @symlink(rtrim(APPS_DATA_DIR, '/'), $mapUserLink);
require_once __DIR__ . '/db.php';
