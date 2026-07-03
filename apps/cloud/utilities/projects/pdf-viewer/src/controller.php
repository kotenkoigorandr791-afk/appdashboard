<?php
$theme = $_GET['theme'] ?? 'dark';
$color = $_GET['color'] ?? '%2359b300';
$color = urldecode($color);
$color = ltrim($color, '#');
$token = $_GET['token'] ?? '';
$lang = $_GET['lang'] ?? 'ru';

// Translations
$translations = cloud_load_translation_map(__DIR__);


$t = $translations[$lang] ?? $translations['en'];
require __DIR__ . '/../views/page.php';
