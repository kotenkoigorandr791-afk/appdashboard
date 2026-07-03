<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
session_start();

require_once __DIR__ . '/core/db.php';

if (isset($_GET['album_id'])) {
    $albumId = $_GET['album_id'];
    $views = incrementGalleryAlbumViews($albumId);

    echo json_encode(['success' => true, 'views' => $views]);
    exit;
}

echo json_encode(['success' => false]);
?>
