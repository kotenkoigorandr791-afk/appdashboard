<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
session_start();

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/models/ViewerModel.php';
require_once __DIR__ . '/controllers/ApiController.php';

require_once dirname(__DIR__, 2) . '/project/i18n_helpers.php';

$userRole = getUserRole();

handleViewerApi($userRole);

$lang = ap_normalize_i18n_lang($_GET['lang'] ?? 'en');
$viewerI18n = ap_load_i18n_all_langs(__DIR__);
$theme = isset($_GET['theme']) ? $_GET['theme'] : 'light';
$color = isset($_GET['color']) ? $_GET['color'] : '#4285f4';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">Web Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <?php include __DIR__ . '/views/styles.php'; ?>
</head>
<body>
<?php
include __DIR__ . '/views/main.php';
include __DIR__ . '/views/scripts.php';
?>
</body>
</html>
