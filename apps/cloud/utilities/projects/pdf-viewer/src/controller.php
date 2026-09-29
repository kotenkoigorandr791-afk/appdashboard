<?php
$theme = $_GET['theme'] ?? 'dark';
$color = $_GET['color'] ?? '%2359b300';
$color = urldecode($color);
$color = ltrim($color, '#');
$token = $_GET['token'] ?? '';
$lang = $_GET['lang'] ?? 'ru';
require_once dirname(__DIR__, 4) . '/includes/session.php';
require_once dirname(__DIR__, 4) . '/includes/auth_gate.php';
cloud_session_start();
cloud_require_session_auth();

// Translations
$translations = cloud_load_translation_map(__DIR__);


$t = $translations[$lang] ?? $translations['en'];
require __DIR__ . '/../views/page.php';
