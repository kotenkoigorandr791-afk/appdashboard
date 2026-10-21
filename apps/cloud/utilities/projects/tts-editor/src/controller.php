<?php
// tts_editor.php - Text to Speech Editor
$textPath = $_GET['path'] ?? '';
$user = $_GET['user'] ?? 'default';
$root = $_GET['root'] ?? 'main';
$lang = $_GET['lang'] ?? 'en';
$theme = $_GET['theme'] ?? 'light';
$color = $_GET['color'] ?? '%2385b503';
$ttsApiUrl = cloud_url('tts_api.php');

if (!$textPath) {
    die('No text file specified');
}
require_once dirname(__DIR__, 4) . '/includes/session.php';
require_once dirname(__DIR__, 4) . '/includes/auth_gate.php';
cloud_session_start();
cloud_require_session_auth();
require_once dirname(__DIR__, 4) . '/includes/cloud_groups.php';
function app_path($relativePath = '') {
    $baseDir = CLOUD_APP_ROOT;
    if ($relativePath === '') return $baseDir;
    return $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
}
function resolve_user_file_path($user, $relativePath, $root = 'main') {
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') return null;
    $safeUser = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', (string)$user);
    $normalizedWeb = ltrim(str_replace('\\', '/', $relativePath), '/');
    $storagePrefix = 'storage/' . $safeUser . '/';
    if (str_starts_with($normalizedWeb, $storagePrefix)) $normalizedWeb = substr($normalizedWeb, strlen($storagePrefix));
    if (str_starts_with((string)$root, 'group:')) {
        try {
            $appDataPath = cloud_resolve_file_path((string)$user, (string)$root, $relativePath);
            if (is_file($appDataPath)) return $appDataPath;
        } catch (Throwable $e) {
        }
    }
    try {
        $appDataPath = appdata_user_full_path('cloud', (string)$user, (string)$root, $normalizedWeb, false);
        if (is_file($appDataPath)) return $appDataPath;
    } catch (Throwable $e) {
    }
    $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    $rootMap = ['archive' => '.archive', 'temp' => '.temp_files', 'documents' => '.documents', 'photos' => '.photos', 'pdf' => '.pdf'];
    $rootPrefix = ($root !== 'main' && isset($rootMap[$root])) ? $rootMap[$root] : '';
    $candidates = [];
    if ($rootPrefix !== '' && strpos($normalized, $rootPrefix . DIRECTORY_SEPARATOR) !== 0) $candidates[] = $rootPrefix . DIRECTORY_SEPARATOR . $normalized;
    if (preg_match('/^(?:[a-zA-Z]:[\\\\\\/]|\/)/', $relativePath)) $candidates[] = $relativePath;
    $candidates[] = $normalized;
    $candidates[] = app_path($normalized);
    $candidates[] = app_path('../' . $normalized);
    $roots = [app_path('storage/' . $user), app_path('../storage/' . $user), app_path('temp'), app_path('temp_convert'), app_path('transcription_uploads'), app_path('tts_output')];
    foreach ($roots as $root) $candidates[] = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $normalized;
    if ($rootPrefix !== '') foreach ($roots as $root) $candidates[] = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $rootPrefix . DIRECTORY_SEPARATOR . $normalized;
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $resolved = realpath($candidate);
            if ($resolved !== false) return $resolved;
        }
    }
    return null;
}

// Translation dictionary
$translations = cloud_load_translation_map(__DIR__);


$t = $translations[$lang] ?? $translations['en'];
$rootMap = ['archive' => '.archive', 'temp' => '.temp_files', 'documents' => '.documents', 'photos' => '.photos', 'pdf' => '.pdf'];
$apiTextPath = ($root !== 'main' && isset($rootMap[$root]) && strpos(ltrim($textPath, '/\\'), $rootMap[$root] . '/') !== 0) ? $rootMap[$root] . '/' . ltrim($textPath, '/\\') : $textPath;
$textContent = '';
$sourcePath = resolve_user_file_path($user, $textPath, $root) ?: '';
if ($sourcePath && is_readable($sourcePath)) $textContent = file_get_contents($sourcePath);
require __DIR__ . '/../views/page.php';
