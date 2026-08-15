<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
// save_config.php - Save AI configuration

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Auth check (must be root)
function checkUserRole() {
    $sessions_file = __DIR__ . '/active_sessions.json';
    
    if (!file_exists($sessions_file)) {
        return false;
    }
    
    require_once __DIR__ . '/includes/session.php';
    cloud_session_start();
    $session_id = session_id();
    
    $sessions_data = json_decode(file_get_contents($sessions_file), true);
    
    if ($session_id && isset($sessions_data[$session_id])) {
        $role = $sessions_data[$session_id]['user_data']['role'] ?? 0;
        return $role === 2; // Root only (role = 2)
    }
    
    // Check by cookie
    if (isset($_COOKIE['PHPSESSID'])) {
        $cookie_session = $_COOKIE['PHPSESSID'];
        if (isset($sessions_data[$cookie_session])) {
            $role = $sessions_data[$cookie_session]['user_data']['role'] ?? 0;
            return $role === 2;
        }
    }
    
    return false;
}

if (!checkUserRole()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Only root users can change API key.']);
    exit;
}

// Get data from request
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['api_key'])) {
    http_response_code(400);
    echo json_encode(['error' => 'API key is required']);
    exit;
}

$api_key = trim($input['api_key']);
if (!preg_match('/^(sk-|io-v2-)/', $api_key)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid API key format. Key should start with sk- or io-v2-']);
    exit;
}
require_once __DIR__ . '/includes/ai_config_store.php';
$result = cloud_ai_save_config(['api_key' => $api_key]);
if (isset($result['error'])) {
    http_response_code((int)($result['code'] ?? 400));
    echo json_encode(['error' => $result['error']]);
    exit;
}
echo json_encode(['success' => true, 'message' => 'API key updated successfully']);
