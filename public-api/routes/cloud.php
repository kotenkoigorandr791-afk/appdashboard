<?php
$parts = public_api_parse_path();
if (!isset($_GET['action']) && isset($parts[1]) && !str_ends_with((string)$parts[1], '.php')) {
    $_GET['action'] = $parts[1];
}
$direct = (string)($parts[1] ?? '');
$allowedDirect = [
    'compile.php',
    'format_code.php',
    'de_format_code.php',
    'remove_comments.php',
    'transcription_api.php',
    'translate_api_simple.php',
    'tts_api.php',
    'ai.php',
];
if (in_array($direct, $allowedDirect, true)) {
    if (!empty($_SESSION['user']['email'])) {
        $_SESSION['email'] = (string)$_SESSION['user']['email'];
    }
    require APP_ROOT . '/apps/cloud/' . $direct;
    exit;
}
require APP_ROOT . '/apps/cloud/api.php';
