<?php
// image_viewer.php - Viewer.js с поддержкой листания изображений в папке
$filePath = isset($_GET['path']) ? $_GET['path'] : '';
$theme = isset($_GET['theme']) ? $_GET['theme'] : 'light';
$color = isset($_GET['color']) ? $_GET['color'] : '#85b503';
$color = ltrim(urldecode((string)$color), '#%');
$user = isset($_GET['user']) ? $_GET['user'] : 'default';
$root = isset($_GET['root']) ? $_GET['root'] : 'main';
$folder = isset($_GET['folder']) ? $_GET['folder'] : '';
$imagesJson = isset($_GET['images']) ? $_GET['images'] : '[]';
$apiUrl = cloud_url('api.php');

// Безопасная проверка пути
if (empty($filePath)) {
    http_response_code(400);
    die('No file path provided');
}

$images = json_decode($imagesJson, true);
$currentFileName = basename($filePath);
$currentIndex = array_search($currentFileName, $images);
if ($currentIndex === false) $currentIndex = 0;

// Кэширование: 1 час для изображений
header('Cache-Control: public, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
require __DIR__ . '/../views/page.php';
