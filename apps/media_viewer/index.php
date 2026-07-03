<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
session_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/controllers/AlbumController.php';

$lang = $_GET['lang'] ?? 'en';
$theme = $_GET['theme'] ?? 'light';
$color = $_GET['color'] ?? '#59b300';
$app = $_GET['app'] ?? 'test2';

$currentUser = get_current_user_from_sessions();
$userRole = $currentUser !== 'Guest' ? getCurrentUserRole() : 0;
$currentUserPhoto = $currentUser !== 'Guest' ? getCurrentUserPhoto() : '';

$notificationDismissed = isNotificationDismissed(session_id() ?: ($_COOKIE['PHPSESSID'] ?? ''));

$translations = loadTranslations($lang);

$roleText = $translations['guest'] ?? 'Guest';
if ($userRole == 1) {
    $roleText = $translations['manager'] ?? 'Manager';
} elseif ($userRole >= 2) {
    $roleText = $translations['admin'] ?? 'Administrator';
}

handleAlbumActions($userRole, $app, $lang, $theme, $color);

$albums = getGalleryAlbums();
$hiddenAlbums = getGalleryHiddenAlbums();
$stats = getGalleryStats();
$mvBgFile = $theme === 'dark' ? 'background_dark.jpg' : 'background_light.jpg';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Photo Album Manager</title>
    <link rel="preload" as="image" href="<?php echo htmlspecialchars($mvBgFile, ENT_QUOTES, 'UTF-8'); ?>" fetchpriority="high">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include __DIR__ . '/views/styles.php'; ?>
</head>
<body>
<div id="mv-app-bg" aria-hidden="true"></div>
<script>
(function(){var s=<?php echo json_encode($mvBgFile); ?>,el=document.getElementById('mv-app-bg'),im=new Image();im.decoding='async';im.fetchPriority='high';im.onload=function(){el.style.backgroundImage='url('+JSON.stringify(s)+')';el.classList.add('ready')};im.src=s})();
</script>
<?php
include __DIR__ . '/views/header.php';
include __DIR__ . '/views/grid.php';
include __DIR__ . '/views/modals.php';
include __DIR__ . '/views/scripts.php';
?>
</body>
</html>
