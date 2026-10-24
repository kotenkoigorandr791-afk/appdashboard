<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/includes/cloud_endpoint_auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}
$translateEmail = cloud_endpoint_require_email();
cloud_endpoint_rate_limit($translateEmail, 'utility');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['text']) || !isset($input['source_lang'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters: text, source_lang']);
        exit();
    }
    
    $text = trim($input['text']);
    $sourceLang = $input['source_lang'];
    $targetLangs = $input['target_langs'] ?? ['en', 'ru', 'be'];
    if (!is_array($targetLangs)) $targetLangs = ['en'];
    $targetLangs = array_slice(array_values($targetLangs), 0, 5);
    if (empty($text)) {
        http_response_code(400);
        echo json_encode(['error' => 'Text cannot be empty']);
        exit();
    }
    if (mb_strlen($text) > 5000) {
        http_response_code(400);
        echo json_encode(['error' => 'Text too long']);
        exit();
    }
    
    $translations = [];
    $errors = [];
    
    foreach ($targetLangs as $targetLang) {
        if ($sourceLang === $targetLang) {
            $translations[$targetLang] = $text;
            continue;
        }
        
        $langPair = $sourceLang . '|' . $targetLang;
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
            $errors[$targetLang] = 'Failed to fetch translation';
            continue;
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[$targetLang] = 'Invalid JSON response';
            continue;
        }
        
        if (isset($data['responseData']['translatedText'])) {
            $translations[$targetLang] = $data['responseData']['translatedText'];
        } else {
            $errors[$targetLang] = 'No translation found';
        }
        
        // Sleep briefly to avoid rate limiting
        usleep(200000);
    }
    
    echo json_encode([
        'success' => true,
        'source_text' => $text,
        'source_lang' => $sourceLang,
        'translations' => $translations,
        'errors' => $errors
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
}
?>
