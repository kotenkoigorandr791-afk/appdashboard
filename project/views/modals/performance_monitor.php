<?php
/**
 * Performance Monitor panel — inline (no iframe), like backup_manager.
 * Included by project/post_handlers.php inside #ap-performance.
 */
$perfInline = true;
$perfAppBase = '/project/performance';
$_perfBackup = [
    'translations' => $translations ?? null,
    'lang' => $lang ?? null,
    'theme' => $theme ?? null,
    't' => $t ?? null,
    'color' => $color ?? null,
];
require_once dirname(__DIR__, 2) . '/i18n_helpers.php';
$perfTranslationsAll = ap_load_i18n_all_langs(dirname(__DIR__, 2) . '/performance');
$perfLang = $language ?? ($_GET['lang'] ?? 'en');
if (!isset($perfTranslationsAll[$perfLang])) {
    $perfLang = 'en';
}
$lang = $perfLang;
$themeRaw = strtolower((string)($themeForAppLink ?? ($_GET['theme'] ?? 'light')));
$theme = $themeRaw === 'dark' ? 'dark' : 'light';
$rawColor = (string)($config['color'] ?? ($_perfBackup['color'] ?? '#007bff'));
if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $rawColor)) {
    $rawColor = '#007bff';
}
$color = htmlspecialchars($rawColor, ENT_QUOTES, 'UTF-8');
$t = $perfTranslationsAll[$lang] ?? ($perfTranslationsAll['en'] ?? []);
$GLOBALS['_t_i18n'] = $t;
$monitorChartsTitle = [
    'en' => 'Monitoring Charts',
    'ru' => 'Графики мониторинга',
    'uk' => 'Графіки моніторингу',
    'be' => 'Графікі маніторынгу',
    'pl' => 'Wykresy monitoringu',
    'cs' => 'Grafy monitorování',
][$lang] ?? 'Monitoring Charts';
$serverMonitorEndpoint = $perfAppBase . '/server_monitor.php';
$userRole = isset($_SESSION['user']['role']) ? (int)$_SESSION['user']['role'] : 2;
$translations = $perfTranslationsAll;
?>
<div id="perf-app" class="perf-root <?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?> admin-embed" data-perf-inline="1" style="--primary-color: <?php echo $color; ?>">
<?php include dirname(__DIR__, 2) . '/performance/views/body_content.php'; ?>
</div>
<script>window.__PERF_INLINE__=true;window.__PERF_APP_BASE__=<?php echo json_encode($perfAppBase, JSON_UNESCAPED_SLASHES); ?>;</script>
<?php include dirname(__DIR__, 2) . '/performance/views/scripts_main.php'; ?>
<?php
if (is_array($_perfBackup['translations'] ?? null)) {
    $translations = $_perfBackup['translations'];
}
if ($_perfBackup['lang'] !== null) {
    $lang = $_perfBackup['lang'];
}
if ($_perfBackup['theme'] !== null) {
    $theme = $_perfBackup['theme'];
}
if ($_perfBackup['t'] !== null) {
    $t = $_perfBackup['t'];
}
if ($_perfBackup['color'] !== null) {
    $color = $_perfBackup['color'];
}
unset($_perfBackup, $perfTranslationsAll);
?>
