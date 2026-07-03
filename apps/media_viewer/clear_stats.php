<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
session_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/session.php';

header('Content-Type: application/json');

if (getCurrentUserRole() < 1) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

echo json_encode(['success' => clearGalleryStats()]);
