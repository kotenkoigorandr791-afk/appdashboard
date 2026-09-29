<?php
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once dirname(__DIR__, 4) . '/includes/session.php';
require_once dirname(__DIR__, 4) . '/includes/auth_gate.php';
cloud_session_start();
cloud_require_session_auth();
require_once dirname(__DIR__, 4) . '/includes/cloud_groups.php';
$filePath = isset($_GET['path']) ? $_GET['path'] : '';
$user = isset($_GET['user']) ? $_GET['user'] : 'default';
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$theme = isset($_GET['theme']) ? $_GET['theme'] : 'dark';
$color = isset($_GET['color']) ? $_GET['color'] : '#569cd6';
$root = isset($_GET['root']) ? $_GET['root'] : 'main';

function resolve_cloud_path_or_fail(string $user, string $root, string $path): string {
    try {
        return cloud_resolve_file_path($user, $root, $path);
    } catch (Throwable $e) {
        http_response_code(400);
        exit('Invalid path');
    }
}
$fullPath = resolve_cloud_path_or_fail($user, $root, $filePath);

$fileContent = '';
if (file_exists($fullPath)) {
    $fileContent = file_get_contents($fullPath);
}
$fileContentForJS = json_encode($fileContent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$monacoTheme = $theme === 'light' ? 'vs' : 'vs-dark';
$cloudBaseUrl = cloud_app_base_url();
$assetsBaseUrl = cloud_url('assets');
$apiUrl = cloud_url('api.php');

require __DIR__ . '/../views/page.php';
