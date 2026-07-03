<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/models/UserModel.php';
require_once __DIR__ . '/models/LocationModel.php';
require_once __DIR__ . '/models/RouteModel.php';
require_once __DIR__ . '/controllers/LocationController.php';
require_once __DIR__ . '/controllers/RouteController.php';
require_once __DIR__ . '/views/routes.php';
require_once __DIR__ . '/views/forms.php';
require_once __DIR__ . '/views/map.php';
require_once __DIR__ . '/views/api_docs.php';
$currentUser = get_current_user_from_sessions();
$currentUserEmail = getCurrentUserEmail();
$currentUserRole = getUserRoleByEmail($currentUserEmail);
$safeUsername = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $currentUser);
$USER_DIR = APPS_DATA_DIR . $safeUsername . '/';
$UPLOAD_DIR = $USER_DIR . 'uploads/';
$ROUTES_FILE = $USER_DIR . 'routes.csv';
$FAVORITES_FILE = $USER_DIR . 'favorites.json';
if (!file_exists(APPS_DATA_DIR)) mkdir(APPS_DATA_DIR, 0755, true);
if (!file_exists($USER_DIR)) mkdir($USER_DIR, 0755, true);
if (!file_exists($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);
if (!file_exists($ROUTES_FILE)) file_put_contents($ROUTES_FILE, '');
if (!file_exists($FAVORITES_FILE)) file_put_contents($FAVORITES_FILE, json_encode([]));
initLocationSharingDB();
$existingToken = null;
$userEmail = getCurrentUserEmail();
if ($userEmail) {
    try {
        $locDb = initLocationSharingDB();
        if ($locDb) {
            $stmt = $locDb->prepare("SELECT token, expires_at FROM map_location_tokens WHERE user_email = :email AND is_active = 1 AND expires_at > CURRENT_TIMESTAMP ORDER BY created_at DESC LIMIT 1");
            $stmt->bindValue(':email', $userEmail, SQLITE3_TEXT);
            $result = $stmt->execute();
            $existingToken = $result->fetchArray(SQLITE3_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Failed to get existing token: " . $e->getMessage());
    }
}
if (handleLocationAPI()) exit;
if (!isset($_SESSION['settings'][$safeUsername])) $_SESSION['settings'][$safeUsername] = ['items_per_page' => 6, 'current_page' => 1, 'view_mode' => 'all', 'display_mode' => 'grid'];
require_once dirname(__DIR__, 2) . '/project/i18n_helpers.php';
$languages = ap_load_i18n_all_langs(__DIR__);
$lang = isset($_GET['lang']) && ap_i18n_lang_exists_in_dir(__DIR__, $_GET['lang']) ? ap_normalize_i18n_lang($_GET['lang']) : 'en';
$theme = isset($_GET['theme']) ? $_GET['theme'] : 'light';
$color = isset($_GET['color']) ? $_GET['color'] : '#78a300';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : $_SESSION['settings'][$safeUsername]['current_page'];
$view_mode = $_GET['view_mode'] ?? $_SESSION['settings'][$safeUsername]['view_mode'];
$display_mode = $_GET['display_mode'] ?? $_SESSION['settings'][$safeUsername]['display_mode'];
$download = $_GET['download'] ?? '';
$_SESSION['settings'][$safeUsername]['current_page'] = $page;
$_SESSION['settings'][$safeUsername]['view_mode'] = $view_mode;
$_SESSION['settings'][$safeUsername]['display_mode'] = $display_mode;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $_SESSION['settings'][$safeUsername]['items_per_page'] = (int)$_POST['items_per_page'];
    header("Location: ?lang=$lang&theme=$theme&color=" . urlencode($color) . "&page=1&view_mode=$view_mode&display_mode=$display_mode");
    exit;
}
$lang_array = $languages[$lang];
handleRoutePostActions($lang, $theme, $color, $view_mode, $display_mode);
handleRouteAjaxActions();
if ($download && $id) downloadRoute($id, $download);
$routes = getRoutes();
$pagination = getPaginatedRoutes($routes, $page, $_SESSION['settings'][$safeUsername]['items_per_page'], $view_mode);
echo resolveRouteContent($action, $display_mode, $pagination, $id);
