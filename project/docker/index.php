<?php
// project/docker/index.php — entry point for the Docker Manager app.
// All logic lives next to this file (i18n.php, backend.php, views.php, ...).

$dockerAppRoot = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) {
    define('APP_ROOT', $dockerAppRoot);
}
if (getcwd() !== APP_ROOT) {
    @chdir(APP_ROOT);
}

require_once $dockerAppRoot . '/lib/app_session.php';
app_session_start();
require_once $dockerAppRoot . '/db.php';
require_once $dockerAppRoot . '/lib/admin_security.php';
require_once $dockerAppRoot . '/lib/embed_context.php';
require_once $dockerAppRoot . '/lib/docker_client.php';
require_once $dockerAppRoot . '/smart_log.php';
smart_log_bootstrap('dashboard');
adminPanelRequireGate();
session_write_close();

$dockerCfg = loadDockerConfig();
$supportedLangs = ['en', 'ru', 'uk', 'be', 'pl', 'cs'];
$lang = in_array($_GET['lang'] ?? '', $supportedLangs, true) ? $_GET['lang'] : 'en';
$theme = ($_GET['theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $_GET['color'] ?? '') ? $_GET['color'] : '#007bff';
$GLOBALS['theme'] = $theme;
$GLOBALS['accent'] = $accent;
$GLOBALS['lang'] = $lang;
$embed = app_is_embedded_request();
$GLOBALS['embed'] = $embed;
$configuredDomain = trim((string)($dockerCfg['domain'] ?? 'localhost'));
$displayDomain = trim((string)($_GET['docker_domain'] ?? $configuredDomain));
if ($displayDomain === '') {
    $displayDomain = 'localhost:9000';
}
$jwtToken = trim((string)($dockerCfg['token'] ?? ''));

// Load Docker i18n on top of shared translations (project/i18n/<lang>.json)
require_once __DIR__ . '/i18n.php';
$i18n = docker_load_i18n($lang);

// Delegate to the backend module (handles all views/actions and renders the page)
require_once __DIR__ . '/backend.php';
