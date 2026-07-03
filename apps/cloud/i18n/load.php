<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$lang = preg_replace('/[^a-z]{2}/', '', strtolower((string)($_GET['lang'] ?? 'en')));
if ($lang === '') {
    $lang = 'en';
}
$dir = __DIR__ . '/' . $lang;
$merged = [];
if (is_dir($dir)) {
    $files = glob($dir . '/*.json') ?: [];
    sort($files);
    foreach ($files as $file) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data)) {
            $merged = array_merge($merged, $data);
        }
    }
} else {
    $legacy = __DIR__ . '/' . $lang . '.json';
    if (is_file($legacy)) {
        $data = json_decode((string)file_get_contents($legacy), true);
        if (is_array($data)) {
            $merged = $data;
        }
    }
}
echo json_encode($merged, JSON_UNESCAPED_UNICODE);
