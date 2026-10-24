<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/translations.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/../../lib/admin_security.php';

$lang = isset($_GET['lang']) && isset($translations[$_GET['lang']]) ? $_GET['lang'] : 'en';
$themeRaw = isset($_GET['theme']) ? strtolower($_GET['theme']) : 'light';
$theme = $themeRaw === 'dark' ? 'dark' : 'light';
$color = isset($_GET['color']) ? htmlspecialchars($_GET['color']) : '#85b503';

$t = $translations[$lang];
$GLOBALS['_t_i18n'] = $t;
$monitorChartsTitle = [
    'en' => 'Monitoring Charts',
    'ru' => 'Графики мониторинга',
    'uk' => 'Графіки моніторингу',
    'be' => 'Графікі маніторынгу',
    'pl' => 'Wykresy monitoringu',
    'cs' => 'Grafy monitorování',
][$lang] ?? 'Monitoring Charts';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$perfAppBase = rtrim($scriptDir === '/' ? '' : $scriptDir, '/') ?: '/project/performance';
$serverMonitorEndpoint = $perfAppBase . '/server_monitor.php';

$currentUser = 'Guest';
$userRole = 0;
$userRoleName = $t['role_guest'];

session_start();
$currentUser = get_current_user_from_sessions();
$userEmail = monitor_current_user_email();
$userRole = monitor_current_user_role($userEmail);

adminPanelRequireGate();

if ($userRole >= 2) {
    $userRoleName = $t['role_admin'];
} elseif ($userRole == 1) {
    $userRoleName = $t['role_user'];
} else {
    $userRoleName = $t['role_guest'];
}

session_write_close();

require __DIR__ . '/controllers/MonitorController.php';
?>
<?php include __DIR__ . '/views/head.php'; ?>
<?php include __DIR__ . '/views/body.php'; ?>
<?php include __DIR__ . '/views/scripts_main.php'; ?>
</body>
</html>
