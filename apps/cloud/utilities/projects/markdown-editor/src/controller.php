<?php
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once dirname(__DIR__, 4) . '/includes/cloud_groups.php';
$filePath = isset($_GET['path']) ? $_GET['path'] : '';
$user = isset($_GET['user']) ? $_GET['user'] : 'default';
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$rawTheme = isset($_GET['theme']) ? trim((string)$_GET['theme']) : '';
if ($rawTheme === 'auto') {
    $cookieTheme = $_COOKIE['browser_theme'] ?? '';
    $theme = ($cookieTheme === 'light' || $cookieTheme === 'dark') ? $cookieTheme : 'dark';
} elseif ($rawTheme === 'light') {
    $theme = 'light';
} else {
    $theme = 'dark';
}
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

if (isset($_POST['translate'])) {
    $text = $_POST['text'] ?? '';
    $sourceLang = $_POST['source_lang'] ?? 'auto';
    $targetLang = $_POST['target_lang'] ?? 'ru';
    if (empty($text)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No text to translate']);
        exit;
    }
    // Map language codes to MyMemory API format
    $langMap = [
        'auto' => 'Autodetect',  // MyMemory supports autodetect
        'en' => 'en',
        'ru' => 'ru',
        'uk' => 'uk',
        'be' => 'be',
        'de' => 'de',
        'fr' => 'fr',
        'es' => 'es',
        'it' => 'it',
        'pl' => 'pl',
        'zh' => 'zh',
        'ja' => 'ja'
    ];
    $sourceCode = $langMap[$sourceLang] ?? 'Autodetect';
    $targetCode = $langMap[$targetLang] ?? 'ru';
    
    // For autodetect, we use 'Autodetect|target' format
    if ($sourceCode === 'Autodetect') {
        $langPair = 'Autodetect|' . $targetCode;
    } else {
        $langPair = $sourceCode . '|' . $targetCode;
    }
    $url = 'https://api.mymemory.translated.net/get?q=' . urlencode($text) . '&langpair=' . urlencode($langPair);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'user_agent' => 'TranslationApp/1.0',
            'header' => 'Accept: application/json'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to fetch translation']);
        exit;
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid JSON response']);
        exit;
    }
    if (isset($data['responseData']['translatedText'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'translated_text' => $data['responseData']['translatedText']
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No translation found']);
    }
    exit;
}
require __DIR__ . '/../views/page.php';
