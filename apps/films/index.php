<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}

session_start();

error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/translations.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/upload_errors.php';
require_once __DIR__ . '/models/MovieModel.php';

$user_data = get_current_user_data();
$current_user = $user_data['username'];
$user_access_level = $user_data['role'];
$user_photo = $user_data['photo'];

define('MOVIES_FILE', 'movies.json');
define('CONFIG_FILE', 'config.json');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('COMMENTS_DIR', 'comments/');
define('CHUNK_SIZE', 1024 * 1024);

if (!file_exists(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0777, true); }
if (!file_exists(COMMENTS_DIR)) { mkdir(COMMENTS_DIR, 0777, true); }

$lang = isset($_GET['lang']) && ap_i18n_lang_exists_in_dir(__DIR__, $_GET['lang']) ? ap_normalize_i18n_lang($_GET['lang']) : 'en';
$theme = isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark']) ? $_GET['theme'] : 'light';
$color = isset($_GET['color']) ? $_GET['color'] : '#3b82f6';
if (substr($color, 0, 1) === '%') { $color = urldecode($color); }

require __DIR__ . '/controllers/StreamController.php';
require __DIR__ . '/controllers/ApiController.php';

$t = $translations[$lang];
$movies = getVisibleMovies();
foreach ($movies as &$movie) {
    $comments = loadComments($movie['id']);
    $userRatings = array_column($comments, 'rating');
    $movie['overall_rating'] = calculateOverallRating($movie['rating'], $userRatings);
}
unset($movie);
?>
<?php include __DIR__ . '/views/head.php'; ?>
<?php include __DIR__ . '/views/header.php'; ?>
<?php include __DIR__ . '/views/nav_script.php'; ?>
<?php include __DIR__ . '/views/modals.php'; ?>
<?php include __DIR__ . '/views/main_scripts.php'; ?>
</body>
</html>
