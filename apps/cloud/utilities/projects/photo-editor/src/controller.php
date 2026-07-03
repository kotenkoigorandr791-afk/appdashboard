<?php
error_reporting(E_ALL);
ini_set('display_errors', (string)getenv('APP_DEBUG') === '1' ? '1' : '0');
ini_set('display_startup_errors', (string)getenv('APP_DEBUG') === '1' ? '1' : '0');
ini_set('log_errors', '1');

// Получаем параметры
$currentUser = isset($_GET['user']) ? $_GET['user'] : 'default';
$path = isset($_GET['path']) ? $_GET['path'] : '';
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$theme = isset($_GET['theme']) ? $_GET['theme'] : 'light';
$color = isset($_GET['color']) ? $_GET['color'] : '#85b503';
$color = ltrim(urldecode((string)$color), '#%');
$root = isset($_GET['root']) ? $_GET['root'] : 'main';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 4) . '/includes/cloud_groups.php';
$folder = isset($_GET['folder']) ? $_GET['folder'] : '';
$imagesJson = isset($_GET['images']) ? $_GET['images'] : '[]';
$cloudBaseUrl = cloud_app_base_url();
$apiUrl = cloud_url('api.php');
function cloud_relative_input_path(string $user, string $path): string {
    return cloud_normalize_relative_path($user, $path);
}
function resolve_cloud_photo_path_or_fail(string $user, string $root, string $path): string {
    try {
        return cloud_resolve_file_path($user, $root, $path);
    } catch (Throwable $e) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'error' => 'Invalid path']));
    }
}

// Handle image saving
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['path']) && isset($data['data'])) {
        $savePath = resolve_cloud_photo_path_or_fail($currentUser, $root, (string)$data['path']);
        $imageData = $data['data'];
        
        // Декодируем base64 изображение
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = str_replace(' ', '+', $imageData);
        $decodedData = base64_decode($imageData);
        
        // Сохраняем файл
        $success = file_put_contents($savePath, $decodedData);
        
        if ($success !== false) {
            // Вычисляем относительный путь для основного приложения
            $relativePath = appdata_root_relative_path($root, cloud_relative_input_path($currentUser, (string)$data['path']));
            
            echo json_encode([
                'success' => true,
                'relative_path' => $relativePath,
                'full_path' => $relativePath
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to save file',
                'path' => $savePath
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Missing data'
        ]);
    }
    exit;
}

// Language translations
$translations = cloud_load_translation_map(__DIR__);


// Default to English if language is not supported
$t = isset($translations[$lang]) ? $translations[$lang] : $translations['en'];
$imageApiPath = cloud_relative_input_path($currentUser, $path);
$imageUrl = $path !== '' ? ($apiUrl . '?action=raw&path=' . rawurlencode($imageApiPath) . '&root=' . rawurlencode($root)) : '';
$images = json_decode($imagesJson, true);
if (!is_array($images)) $images = [];
$currentFileName = $path !== '' ? basename($path) : '';
$currentIndex = array_search($currentFileName, $images, true);
if ($currentIndex === false) $currentIndex = 0;
require __DIR__ . '/../views/page.php';
