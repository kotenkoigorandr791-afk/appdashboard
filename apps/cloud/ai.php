<?php 
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
// ai.php - AI Assistant endpoint
ini_set('display_errors', '0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/includes/session.php';
cloud_session_start();

require_once __DIR__ . '/includes/ai_config_store.php';
require_once __DIR__ . '/includes/ai_proxy.php';
require_once dirname(__DIR__, 2) . '/storage_helper.php';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if (PHP_SAPI === 'cli' && isset($argv[1])) {
    $action = (string)$argv[1];
    if (isset($argv[2])) $_POST['job_id'] = (string)$argv[2];
    if (isset($argv[3])) $_POST['worker_token'] = (string)$argv[3];
}
$config = in_array($action, ['ai_worker_run', 'ai_run_status', 'ai_run_cancel'], true)
    ? ['api_key' => '', 'api_endpoint' => '', 'default_model' => CLOUD_AI_DEFAULT_MODEL, 'models' => CLOUD_AI_DEFAULT_MODELS]
    : cloud_ai_load_config();
if ((empty($config['api_key']) || empty($config['api_endpoint'])) && !in_array($action, ['ai_run_status', 'ai_run_cancel', 'ai_worker_run'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid AI configuration']);
    exit;
}

const AI_CHAT_TABLE = 'cloud_ai_assistant_chats';
const AI_CHAT_OLD_TABLE = 'ai_assistant_chats';
const AI_CHAT_SESSION_TABLE = 'cloud_ai_assistant_chat_sessions';
const AI_USER_SETTINGS_TABLE = 'cloud_ai_assistant_user_settings';
const AI_USER_SETTINGS_OLD_TABLE = 'ai_assistant_user_settings';

if ($action === 'ai_completions' || $action === 'ai_models') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    $email = getCurrentUserEmailFromMainAPI();
    if (!$email) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'not authenticated']]);
        exit;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if ($action === 'ai_models') {
        cloud_ai_proxy_models($config);
        exit;
    }
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '', true);
    if (!is_array($payload)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'Invalid JSON body']]);
        exit;
    }
    if (!empty($payload['messages']) && is_array($payload['messages'])) {
        require_once __DIR__ . '/includes/ai_context_pack.php';
        $payload['messages'] = cloud_ai_pack_messages_for_budget($payload['messages']);
    }
    cloud_ai_proxy_completions($config, $payload);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Handle chat history operations
// NOTE: legacy per-user JSON history endpoints kept for backward compatibility.
if ($action === 'save_history') { echo json_encode(saveChatHistory($_POST['history'] ?? '[]')); exit; }
if ($action === 'load_history') { echo json_encode(loadChatHistory()); exit; }
if ($action === 'clear_history') { echo json_encode(clearChatHistory()); exit; }

// New DB-backed Q/A chat pagination (fast load)
if ($action === 'ai_chat_append') {
    echo json_encode(aiChatAppend(
        $_POST['question'] ?? '',
        $_POST['answer'] ?? '',
        $_POST['model'] ?? '',
        $_POST['file_name'] ?? '',
        $_POST['time_seconds'] ?? '',
        $_POST['tokens'] ?? '',
        $_POST['speed'] ?? '',
        $_POST['meta_json'] ?? '',
        $_POST['chat_id'] ?? ''
    ));
    exit;
} elseif ($action === 'ai_chat_load') {
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 5;
    echo json_encode(aiChatLoad($offset, $limit, $_POST['chat_id'] ?? ''));
    exit;
} elseif ($action === 'ai_chat_clear') {
    echo json_encode(aiChatDelete($_POST['chat_id'] ?? ''));
    exit;
} elseif ($action === 'ai_chat_sessions') {
    echo json_encode(aiChatSessions());
    exit;
} elseif ($action === 'ai_chat_create') {
    echo json_encode(aiChatCreate($_POST['title'] ?? ''));
    exit;
} elseif ($action === 'ai_chat_delete') {
    echo json_encode(aiChatDelete($_POST['chat_id'] ?? ''));
    exit;
} elseif ($action === 'ai_user_settings_get') {
    echo json_encode(aiUserSettingsGet());
    exit;
} elseif ($action === 'ai_runtime_config') {
    $email = getCurrentUserEmailFromMainAPI();
    if (!$email) {
        echo json_encode(['success' => false, 'error' => 'not authenticated']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'configured' => !empty($config['api_key']),
        'default_model' => $config['default_model'],
        'models' => $config['models'] ?? [],
    ]);
    exit;
} elseif ($action === 'ai_user_settings_set') {
    echo json_encode(aiUserSettingsSet(
        $_POST['selected_model'] ?? '',
        $_POST['selected_role'] ?? '',
        $_POST['chat_mode'] ?? '',
        $_POST['include_file_context'] ?? null,
        $_POST['remember_previous'] ?? null,
        $_POST['has_attached_files'] ?? null,
        $_POST['auto_confirm_agent_edits'] ?? null,
        $_POST['attached_files_count'] ?? null,
        $_POST['mentioned_files_count'] ?? null,
        $_POST['notify_on_complete'] ?? null
    ));
    exit;
} elseif ($action === 'ai_run_start') {
    echo json_encode(aiRunStart($_POST));
    exit;
} elseif ($action === 'ai_run_status') {
    echo json_encode(aiRunStatus($_POST['job_id'] ?? '', $_POST['chat_id'] ?? ''));
    exit;
} elseif ($action === 'ai_run_cancel') {
    echo json_encode(aiRunCancel($_POST['job_id'] ?? '', $_POST['chat_id'] ?? ''));
    exit;
} elseif ($action === 'ai_worker_run') {
    aiWorkerRunCli($_POST['job_id'] ?? '', $_POST['worker_token'] ?? '');
    exit;
}

if ($action === 'chat') {
    $model = $_POST['model'] ?? $config['default_model'];
    $role = $_POST['role'] ?? 'general';
    $message = $_POST['message'] ?? '';
    $fileContent = $_POST['file_content'] ?? '';
    $fileName = $_POST['file_name'] ?? '';
    $additionalFiles = [];
    if (!empty($_POST['additional_files'])) {
        $decoded = json_decode($_POST['additional_files'], true);
        if (is_array($decoded)) {
            $additionalFiles = $decoded;
        }
    }
    $history = isset($_POST['history']) ? json_decode($_POST['history'], true) : [];

    if (empty($message)) {
        echo json_encode(['error' => 'Message is required']);
        exit;
    }

    // Prepare system prompt based on role
    $systemPrompt = getSystemPrompt($role, $fileName, $fileContent, $additionalFiles);
    
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];
    
    // Add history if provided
    if (is_array($history) && !empty($history)) {
        foreach ($history as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
    }
    
    // Add current user message
    $messages[] = ['role' => 'user', 'content' => $message];

    try {
        $response = sendToAI($config, $model, $messages);
        echo json_encode(['response' => $response]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

function getSystemPrompt($role, $fileName, $fileContent, $additionalFiles = []) {
    $basePrompt = "You are a helpful assistant. Provide clear, concise responses without internal monologue or thinking process. ";
    $basePrompt .= "If providing code, format it properly and make it easy to copy. ";
    
    if (!empty($fileName) && !empty($fileContent)) {
        $basePrompt .= "You are analyzing the file: {$fileName}\n\nFile content:\n{$fileContent}\n\n";
    }

    if (is_array($additionalFiles) && !empty($additionalFiles)) {
        $basePrompt .= "Additional referenced files:\n";
        $totalChars = 0;
        $maxChars = 45000;

        foreach ($additionalFiles as $file) {
            if (!is_array($file)) continue;
            $name = $file['name'] ?? '';
            $content = $file['content'] ?? '';
            if ($name === '' || $content === '') continue;

            $remaining = $maxChars - $totalChars;
            if ($remaining <= 0) break;

            if (strlen($content) > $remaining) {
                $content = substr($content, 0, $remaining);
            }

            $basePrompt .= "\n[Referenced file: {$name}]\n{$content}\n";
            $totalChars += strlen($content);
        }

        $basePrompt .= "\n";
    }

    switch ($role) {
        case 'programmer':
            return $basePrompt . "You are an expert programmer. Provide code solutions, debugging help, and programming advice. Focus on practical, working code examples.";
        case 'analyst':
            return $basePrompt . "You are a data analyst. Help analyze data, create reports, and provide insights. Use tables for structured data when appropriate.";
        default:
            return $basePrompt . "You are a general assistant. Help with various tasks and questions.";
    }
}

function sendToAI($config, $model, $messages) {
    $data = [
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => 4000,
        'temperature' => 0.7,
        'stream' => false
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['api_endpoint'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['api_key']
        ],
        CURLOPT_TIMEOUT => CLOUD_AI_BLOCKING_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('CURL error: ' . $error);
    }

    if ($httpCode !== 200) {
        throw new Exception('API error: HTTP ' . $httpCode . ' - ' . $response);
    }

    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    } else {
        throw new Exception('Invalid response format from AI');
    }
}

// Function to get current user from main API
// ai.php - replace existing function

function getCurrentUserFromMainAPI() {
    // Read from SQLite database (current session storage)
    require_once __DIR__ . '/../../lib/app_db.php';
    
    try {
        $db = app_main_db();
        
        $session_id = session_id();
        
        // Try to get email from active_sessions table using current session
        if ($session_id) {
            $stmt = aiPrepare($db, "SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1");
            $stmt->bindValue(':sid', $session_id, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result ? $result->fetch(PDO::FETCH_ASSOC) : null;
            
            if ($row && !empty($row['email'])) {
                $email = $row['email'];
                $username = explode('@', $email)[0] ?? 'user';
                $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
                return $username ?: 'default';
            }
        }
        
        // Fallback: try PHP session cookie
        if (isset($_COOKIE['PHPSESSID'])) {
            $cookie_session = $_COOKIE['PHPSESSID'];
            $stmt = aiPrepare($db, "SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1");
            $stmt->bindValue(':sid', $cookie_session, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result ? $result->fetch(PDO::FETCH_ASSOC) : null;
            
            if ($row && !empty($row['email'])) {
                $email = $row['email'];
                $username = explode('@', $email)[0] ?? 'user';
                $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
                return $username ?: 'default';
            }
        }
        
        if (method_exists($db, 'close')) $db->close();
    } catch (Exception $e) {
        cloud_safe_error_log('getCurrentUserFromMainAPI error: ' . $e->getMessage());
    }

    // Fallback: use IP address
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $username = 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);

    return $username;
}

function getCurrentUserEmailFromMainAPI() {
    // Primary: session data populated by the main auth flow (Redis-backed sessions)
    $sessionEmail = $_SESSION['user']['email'] ?? $_SESSION['email'] ?? null;
    if (is_string($sessionEmail) && $sessionEmail !== '') {
        return strtolower(trim($sessionEmail));
    }

    // Read from SQLite database (current session storage)
    require_once __DIR__ . '/../../lib/app_db.php';
    
    try {
        $db = app_main_db();
        
        $session_id = session_id();
        
        // Try to get email from active_sessions table using current session
        if ($session_id) {
            $stmt = aiPrepare($db, "SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1");
            $stmt->bindValue(':sid', $session_id, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result ? $result->fetch(PDO::FETCH_ASSOC) : null;
            
            if ($row && !empty($row['email'])) {
                return (string)$row['email'];
            }
        }
        
        // Fallback: try PHP session cookie
        if (isset($_COOKIE['PHPSESSID'])) {
            $cookie_session = $_COOKIE['PHPSESSID'];
            $stmt = aiPrepare($db, "SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1");
            $stmt->bindValue(':sid', $cookie_session, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result ? $result->fetch(PDO::FETCH_ASSOC) : null;
            
            if ($row && !empty($row['email'])) {
                return (string)$row['email'];
            }
        }
        
        if (method_exists($db, 'close')) $db->close();
    } catch (Exception $e) {
        cloud_safe_error_log('getCurrentUserEmailFromMainAPI error: ' . $e->getMessage());
    }
    
    return '';
}

function aiIsRootUser(): bool
{
    $email = getCurrentUserEmailFromMainAPI();
    if ($email === '') {
        return false;
    }
    require_once __DIR__ . '/../../lib/app_db.php';
    try {
        $db = app_main_db();
        
        $stmt = aiPrepare($db, 'SELECT role FROM users WHERE email = :email LIMIT 1');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (method_exists($db, 'close')) $db->close();
        return $row && (int)($row['role'] ?? 0) === 2;
    } catch (Exception $e) {
        return false;
    }
}

function aiCurrentEmailForChat(): string {
    $email = getCurrentUserEmailFromMainAPI();
    if ($email === '') {
        $safe = getCurrentUserFromMainAPI();
        $email = $safe . '@local';
    }
    return $email;
}

function aiChatTitleFromQuestion(string $question): string {
    $title = trim(preg_replace('/\s+/u', ' ', $question));
    if ($title === '') return 'New chat';
    if (function_exists('mb_substr')) return mb_substr($title, 0, 60);
    return substr($title, 0, 60);
}

function aiSafeEmailKey(string $email): string {
    $key = strtolower(trim($email));
    $key = preg_replace('/[^a-z0-9_.@-]+/i', '_', $key);
    return trim((string)$key, '._-') ?: 'default';
}

function aiChatSessionsPath(string $email): string {
    return appdata_app_base_dir('cloud') . '/ai_chat_sessions/' . aiSafeEmailKey($email) . '.json';
}

function aiReadChatSessionsJson(string $email): array {
    $path = aiChatSessionsPath($email);
    if (!is_file($path)) return [];
    $data = json_decode((string)@file_get_contents($path), true);
    return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
}

function aiWriteChatSessionsJson(string $email, array $sessions): void {
    $path = aiChatSessionsPath($email);
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Chat session storage is not writable');
    }
    $sessions = array_slice(array_values($sessions), 0, 50);
    $json = json_encode($sessions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($path, $json, LOCK_EX) === false) {
        throw new RuntimeException('Chat session storage is not writable');
    }
}

function aiMigrateChatSessionsToJson($db, string $email): void {
    $path = aiChatSessionsPath($email);
    if (is_file($path)) return;
    $sessions = [];
    try {
        if (aiSqliteTableExists($db, AI_CHAT_SESSION_TABLE)) {
            $stmt = aiPrepare($db, "SELECT id, title, created_at, updated_at FROM " . AI_CHAT_SESSION_TABLE . " WHERE email = :email ORDER BY updated_at DESC, created_at DESC LIMIT 50");
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $res = $stmt->execute();
            while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
                $sessions[] = [
                    'id' => (string)$row['id'],
                    'title' => (string)($row['title'] ?: 'New chat'),
                    'created_at' => (int)($row['created_at'] ?? time()),
                    'updated_at' => (int)($row['updated_at'] ?? time()),
                ];
            }
        }
        if ($sessions === []) {
            $stmt = aiPrepare($db, "SELECT chat_id, MAX(created_at) AS updated_at, MIN(created_at) AS created_at, MIN(question) AS question FROM " . AI_CHAT_TABLE . " WHERE email = :email GROUP BY chat_id ORDER BY updated_at DESC LIMIT 50");
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $res = $stmt->execute();
            while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
                $id = trim((string)($row['chat_id'] ?? ''));
                if ($id === '') {
                    $id = bin2hex(random_bytes(12));
                    $upd = aiPrepare($db, "UPDATE " . AI_CHAT_TABLE . " SET chat_id = :id WHERE email = :email AND (chat_id = '' OR chat_id IS NULL)");
                    $upd->bindValue(':id', $id, PDO::PARAM_STR);
                    $upd->bindValue(':email', $email, PDO::PARAM_STR);
                    $upd->execute();
                }
                $sessions[] = [
                    'id' => $id,
                    'title' => aiChatTitleFromQuestion((string)($row['question'] ?? '')),
                    'created_at' => (int)($row['created_at'] ?? time()),
                    'updated_at' => (int)($row['updated_at'] ?? time()),
                ];
            }
        }
    } catch (Exception $e) {
    }
    if ($sessions === []) {
        $now = time();
        $sessions[] = ['id' => bin2hex(random_bytes(12)), 'title' => 'New chat', 'created_at' => $now, 'updated_at' => $now];
    }
    aiWriteChatSessionsJson($email, $sessions);
}

function aiLoadChatSessions(string $email, $db = null): array {
    if ($db) aiMigrateChatSessionsToJson($db, $email);
    $sessions = aiReadChatSessionsJson($email);
    if ($sessions === []) {
        $now = time();
        $sessions = [['id' => bin2hex(random_bytes(12)), 'title' => 'New chat', 'created_at' => $now, 'updated_at' => $now]];
        aiWriteChatSessionsJson($email, $sessions);
    }
    usort($sessions, static fn($a, $b) => ((int)($b['updated_at'] ?? 0) <=> (int)($a['updated_at'] ?? 0)));
    return array_slice($sessions, 0, 50);
}

function aiEnsureChatSessionJson(string $email, string $chatId, string $question = '', $db = null): string {
    $sessions = aiLoadChatSessions($email, $db);
    foreach ($sessions as $session) {
        if ((string)($session['id'] ?? '') === $chatId && $chatId !== '') return $chatId;
    }
    if ($chatId === '') return (string)($sessions[0]['id'] ?? '');
    $now = time();
    array_unshift($sessions, ['id' => $chatId, 'title' => $question !== '' ? aiChatTitleFromQuestion($question) : 'New chat', 'created_at' => $now, 'updated_at' => $now]);
    aiWriteChatSessionsJson($email, $sessions);
    return $chatId;
}

function aiTouchChatSessionJson(string $email, string $chatId, string $question = ''): void {
    $sessions = aiLoadChatSessions($email);
    $found = false;
    foreach ($sessions as &$session) {
        if ((string)($session['id'] ?? '') !== $chatId) continue;
        $session['updated_at'] = time();
        if (($session['title'] ?? 'New chat') === 'New chat' && $question !== '') $session['title'] = aiChatTitleFromQuestion($question);
        $found = true;
        break;
    }
    unset($session);
    if (!$found) {
        $now = time();
        array_unshift($sessions, ['id' => $chatId, 'title' => $question !== '' ? aiChatTitleFromQuestion($question) : 'New chat', 'created_at' => $now, 'updated_at' => $now]);
    }
    aiWriteChatSessionsJson($email, $sessions);
}

function aiChatAppend($question, $answer, $model = '', $fileName = '', $timeSeconds = '', $tokens = '', $speed = '', $metaJson = '', $chatId = '', $emailOverride = '') {
    $question = is_string($question) ? trim($question) : '';
    $answer = is_string($answer) ? trim($answer) : '';
    $model = is_string($model) ? trim($model) : '';
    $fileName = is_string($fileName) ? trim($fileName) : '';
    $timeSeconds = is_string($timeSeconds) ? trim($timeSeconds) : '';
    $tokens = is_string($tokens) ? trim($tokens) : '';
    $speed = is_string($speed) ? trim($speed) : '';
    $metaJson = is_string($metaJson) ? trim($metaJson) : '';

    $timeVal = ($timeSeconds !== '' && is_numeric($timeSeconds)) ? (float)$timeSeconds : null;
    $tokensVal = ($tokens !== '' && is_numeric($tokens)) ? (int)$tokens : null;
    $speedVal = ($speed !== '' && is_numeric($speed)) ? (float)$speed : null;

    if ($question === '' || $answer === '') {
        return ['success' => false, 'error' => 'Question and answer are required'];
    }

    $email = $emailOverride !== '' ? (string)$emailOverride : aiCurrentEmailForChat();

    try {
        $db = getAiDb();
        if (!$db) return ['success' => false, 'error' => 'Database unavailable'];

        ensureAiChatTable($db);
        $chatId = aiEnsureChatSessionJson($email, (string)$chatId, $question, $db);

        $stmt = aiPrepare($db, "
            INSERT INTO " . AI_CHAT_TABLE . " (email, chat_id, question, answer, model, file_name, time_seconds, tokens, speed, meta_json, created_at)
            VALUES (:email, :chat_id, :q, :a, :m, :f, :t, :tok, :s, :meta, extract(epoch from now())::bigint)
        ");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':chat_id', $chatId, PDO::PARAM_STR);
        $stmt->bindValue(':q', $question, PDO::PARAM_STR);
        $stmt->bindValue(':a', $answer, PDO::PARAM_STR);
        $stmt->bindValue(':m', $model, PDO::PARAM_STR);
        $stmt->bindValue(':f', $fileName, PDO::PARAM_STR);
        $stmt->bindValue(':meta', $metaJson, PDO::PARAM_STR);
        if ($timeVal === null) $stmt->bindValue(':t', null, PDO::PARAM_NULL); else $stmt->bindValue(':t', $timeVal, PDO::PARAM_STR);
        if ($tokensVal === null) $stmt->bindValue(':tok', null, PDO::PARAM_NULL); else $stmt->bindValue(':tok', $tokensVal, PDO::PARAM_INT);
        if ($speedVal === null) $stmt->bindValue(':s', null, PDO::PARAM_NULL); else $stmt->bindValue(':s', $speedVal, PDO::PARAM_STR);
        $stmt->execute();
        aiTouchChatSessionJson($email, $chatId, $question);
        return ['success' => true, 'chat_id' => $chatId];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function aiChatLoad($offset, $limit, $chatId = '') {
    $offset = max(0, (int)$offset);
    $limit = (int)$limit;
    if ($limit <= 0) $limit = 5;
    if ($limit > 50) $limit = 50;

    $email = aiCurrentEmailForChat();

    try {
        $db = getAiDb();
        if (!$db) return ['success' => false, 'error' => 'Database unavailable', 'items' => [], 'has_more' => false];

        ensureAiChatTable($db);
        $chatId = aiEnsureChatSessionJson($email, (string)$chatId, '', $db);

        $stmt = aiPrepare($db, "
            SELECT id, question, answer, model, file_name, time_seconds, tokens, speed, meta_json, created_at
            FROM " . AI_CHAT_TABLE . "
            WHERE email = :email AND chat_id = :chat_id
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':chat_id', $chatId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $res = $stmt->execute();
        $rows = [];
        while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
            $rows[] = $row;
        }

        $countStmt = aiPrepare($db, "SELECT COUNT(*) AS cnt FROM " . AI_CHAT_TABLE . " WHERE email = :email AND chat_id = :chat_id");
        $countStmt->bindValue(':email', $email, PDO::PARAM_STR);
        $countStmt->bindValue(':chat_id', $chatId, PDO::PARAM_STR);
        $countRes = $countStmt->execute();
        $cntRow = $countRes ? $countRes->fetch(PDO::FETCH_ASSOC) : null;
        $total = (int)($cntRow['cnt'] ?? 0);

        return [
            'success' => true,
            'chat_id' => $chatId,
            'items' => $rows,
            'has_more' => ($offset + count($rows)) < $total
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'items' => [], 'has_more' => false];
    }
}

function aiChatSessions() {
    $email = aiCurrentEmailForChat();
    try {
        $db = getAiDb();
        if (!$db) return ['success' => false, 'error' => 'Database unavailable', 'items' => []];
        ensureAiChatTable($db);
        return ['success' => true, 'items' => aiLoadChatSessions($email, $db)];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'items' => []];
    }
}

function aiChatCreate($title = '') {
    $email = aiCurrentEmailForChat();
    try {
        $db = getAiDb();
        if (!$db) return ['success' => false, 'error' => 'Database unavailable'];
        ensureAiChatTable($db);
        aiMigrateChatSessionsToJson($db, $email);
        $id = bin2hex(random_bytes(12));
        $now = time();
        $sessions = aiLoadChatSessions($email);
        array_unshift($sessions, ['id' => $id, 'title' => trim((string)$title) ?: 'New chat', 'created_at' => $now, 'updated_at' => $now]);
        aiWriteChatSessionsJson($email, $sessions);
        return ['success' => true, 'chat_id' => $id];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function aiChatDelete($chatId = '') {
    $email = aiCurrentEmailForChat();
    $chatId = trim((string)$chatId);

    try {
        $db = getAiDb();
        if (!$db) return ['success' => false, 'error' => 'Database unavailable'];

        ensureAiChatTable($db);
        if ($chatId === '') $chatId = aiEnsureChatSessionJson($email, '', '', $db);

        $stmt = aiPrepare($db, "DELETE FROM " . AI_CHAT_TABLE . " WHERE email = :email AND chat_id = :chat_id");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':chat_id', $chatId, PDO::PARAM_STR);
        $stmt->execute();
        $sessions = array_values(array_filter(aiLoadChatSessions($email, $db), static fn($session) => (string)($session['id'] ?? '') !== $chatId));
        if ($sessions === []) {
            $now = time();
            $sessions[] = ['id' => bin2hex(random_bytes(12)), 'title' => 'New chat', 'created_at' => $now, 'updated_at' => $now];
        }
        aiWriteChatSessionsJson($email, $sessions);
        return ['success' => true, 'chat_id' => (string)($sessions[0]['id'] ?? ''), 'items' => $sessions];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function aiWorkerBaseDir(string $email = ''): string {
    $base = appdata_app_base_dir('cloud') . '/ai_worker_jobs';
    if ($email === '') return $base;
    return $base . '/' . aiSafeEmailKey($email);
}

function aiWorkerJobPath(string $email, string $jobId): string {
    $jobId = preg_replace('/[^a-f0-9]/i', '', $jobId);
    return aiWorkerBaseDir($email) . '/' . $jobId . '.json';
}

function aiWorkerReadJob(string $path): array {
    $data = json_decode((string)@file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function aiWorkerWriteJob(string $path, array $job): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Worker storage is not writable');
    }
    $json = json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($path, $json, LOCK_EX) === false) {
        throw new RuntimeException('Worker storage is not writable');
    }
    @chmod($dir, 0700);
    @chmod($path, 0600);
}

function aiWorkerEvent(array &$job, string $type, string $text, array $data = []): void {
    $job['events'][] = ['id' => count($job['events'] ?? []) + 1, 'type' => $type, 'text' => $text, 'data' => $data, 'time' => time()];
    $job['updated_at'] = time();
}

function aiWorkerUsernameFromEmail(string $email): string {
    $username = str_replace(['@', '.'], '_', $email);
    $username = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username);
    return $username ?: 'guest';
}

function aiRunStart(array $input): array {
    $email = aiCurrentEmailForChat();
    $message = trim((string)($input['message'] ?? ''));
    if ($message === '') return ['success' => false, 'error' => 'Message is required'];
    $chatId = trim((string)($input['chat_id'] ?? ''));
    try {
        $db = getAiDb();
        if (!$db) return ['success' => false, 'error' => 'Database unavailable'];
        ensureAiChatTable($db);
        $chatId = aiEnsureChatSessionJson($email, $chatId, $message, $db);
        $jobId = bin2hex(random_bytes(12));
        $token = bin2hex(random_bytes(16));
        $job = [
            'id' => $jobId,
            'token' => $token,
            'email' => $email,
            'user' => aiWorkerUsernameFromEmail($email),
            'chat_id' => $chatId,
            'status' => 'queued',
            'message' => $message,
            'user_facing_message' => (string)($input['user_facing_message'] ?? $message),
            'model' => (string)($input['model'] ?? ''),
            'role' => (string)($input['role'] ?? 'general'),
            'chat_mode' => (string)($input['chat_mode'] ?? 'chat'),
            'root' => (string)($input['root'] ?? 'main'),
            'mentioned_files' => json_decode((string)($input['mentioned_files'] ?? '[]'), true) ?: [],
            'mentioned_roots' => json_decode((string)($input['mentioned_roots'] ?? '{}'), true) ?: [],
            'attachments' => json_decode((string)($input['attachments'] ?? '[]'), true) ?: [],
            'history' => json_decode((string)($input['history'] ?? '[]'), true) ?: [],
            'config' => [
                'api_key' => (string)($GLOBALS['config']['api_key'] ?? ''),
                'api_endpoint' => (string)($GLOBALS['config']['api_endpoint'] ?? ''),
                'default_model' => (string)($GLOBALS['config']['default_model'] ?? ''),
                'models' => $GLOBALS['config']['models'] ?? [],
            ],
            'events' => [],
            'created_at' => time(),
            'updated_at' => time(),
        ];
        aiWorkerEvent($job, 'queued', 'Worker queued');
        aiWorkerWriteJob(aiWorkerJobPath($email, $jobId), $job);
        aiTouchChatSessionJson($email, $chatId, $message);
        $php = PHP_BINARY;
        if (stripos($php, 'php-fpm') !== false || !is_executable($php)) {
            $detected = trim((string)shell_exec('command -v php 2>/dev/null'));
            if ($detected !== '') $php = $detected;
        }
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg(__FILE__) . ' ai_worker_run ' . escapeshellarg($jobId) . ' ' . escapeshellarg($token) . ' > /dev/null 2>&1 &';
        @exec($cmd);
        return ['success' => true, 'job_id' => $jobId, 'chat_id' => $chatId];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function aiRunStatus($jobId, $chatId = ''): array {
    $email = aiCurrentEmailForChat();
    $jobId = preg_replace('/[^a-f0-9]/i', '', (string)$jobId);
    $path = $jobId !== '' ? aiWorkerJobPath($email, $jobId) : '';
    if ($path === '' || !is_file($path)) {
        $dir = aiWorkerBaseDir($email);
        $files = is_dir($dir) ? glob($dir . '/*.json') : [];
        usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach ($files as $file) {
            $job = aiWorkerReadJob($file);
            if ($chatId === '' || (string)($job['chat_id'] ?? '') === (string)$chatId) {
                $path = $file;
                break;
            }
        }
    }
    if ($path === '' || !is_file($path)) return ['success' => true, 'job' => null];
    $job = aiWorkerReadJob($path);
    if (($job['status'] ?? '') === 'done' && empty($job['appended_at']) && (string)($job['email'] ?? '') === $email) {
        $append = aiChatAppend($job['user_facing_message'] ?? $job['message'] ?? '', $job['answer'] ?? '', $job['model'] ?? '', '', '', '', '', aiWorkerMetaJson($job), $job['chat_id'] ?? '', $email);
        if (!empty($append['success'])) {
            $job['appended_at'] = time();
            aiWorkerWriteJob($path, $job);
        }
    }
    unset($job['token']);
    return ['success' => true, 'job' => $job];
}

function aiRunCancel($jobId, $chatId = ''): array {
    $email = aiCurrentEmailForChat();
    $jobId = preg_replace('/[^a-f0-9]/i', '', (string)$jobId);
    if ($jobId === '') return ['success' => false, 'error' => 'Job id is required'];
    $path = aiWorkerJobPath($email, $jobId);
    if (!is_file($path)) return ['success' => true, 'cancelled' => false];
    $job = aiWorkerReadJob($path);
    if ($chatId !== '' && (string)($job['chat_id'] ?? '') !== (string)$chatId) return ['success' => false, 'error' => 'Worker job not found'];
    if (in_array((string)($job['status'] ?? ''), ['done', 'error', 'cancelled'], true)) return ['success' => true, 'cancelled' => (string)($job['status'] ?? '') === 'cancelled'];
    $job['status'] = 'cancelled';
    $job['cancelled_at'] = time();
    aiWorkerEvent($job, 'cancelled', 'Worker cancelled');
    aiWorkerWriteJob($path, $job);
    return ['success' => true, 'cancelled' => true];
}

function aiWorkerCompletion(array $config, string $model, array $messages, int $maxTokens = 6000): string {
    $payload = ['model' => cloud_ai_normalize_model_id($model ?: (string)($config['default_model'] ?? '')), 'messages' => $messages, 'max_tokens' => $maxTokens, 'temperature' => 0.2, 'stream' => false];
    $result = cloud_ai_proxy_json($config, (string)$config['api_endpoint'], $payload, 'POST', PHP_SAPI === 'cli' ? 600 : null);
    if (!$result['ok']) throw new RuntimeException($result['error'] ?: ('AI HTTP ' . $result['http']));
    $data = json_decode((string)$result['body'], true);
    $text = (string)($data['choices'][0]['message']['content'] ?? '');
    if (trim($text) === '') throw new RuntimeException('Empty AI response');
    return $text;
}

function aiWorkerMetaJson(array $job): string {
    return json_encode([
        'v' => 1,
        'type' => 'agent',
        'attachments' => array_values(array_filter((array)($job['attachments'] ?? []), 'is_array')),
        'mentionedFiles' => array_values(array_filter((array)($job['mentioned_files'] ?? []), 'is_string')),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
}

function aiWorkerThrowIfCancelled(string $jobPath): void {
    $job = aiWorkerReadJob($jobPath);
    if (($job['status'] ?? '') === 'cancelled') throw new RuntimeException('Worker cancelled');
}

function aiWorkerRunCli($jobId, $token): void {
    global $config;
    $jobId = preg_replace('/[^a-f0-9]/i', '', (string)$jobId);
    foreach (glob(aiWorkerBaseDir() . '/*/' . $jobId . '.json') ?: [] as $path) {
        $job = aiWorkerReadJob($path);
        if (!hash_equals((string)($job['token'] ?? ''), (string)$token)) continue;
        try {
            $job['status'] = 'running';
            aiWorkerEvent($job, 'running', 'Worker started');
            aiWorkerWriteJob($path, $job);
            $workerConfig = !empty($config['api_key']) ? $config : (is_array($job['config'] ?? null) ? $job['config'] : []);
            $answer = aiWorkerPerformJob($job, $workerConfig, $path);
            $job = aiWorkerReadJob($path);
            $job['status'] = 'done';
            $job['answer'] = $answer;
            aiWorkerEvent($job, 'done', 'Worker finished');
            aiWorkerWriteJob($path, $job);
            if (PHP_SAPI !== 'cli') {
                $append = aiChatAppend($job['user_facing_message'] ?? $job['message'], $answer, $job['model'] ?? '', '', '', '', '', aiWorkerMetaJson($job), $job['chat_id'] ?? '', (string)($job['email'] ?? ''));
                if (!empty($append['success'])) {
                    $job = aiWorkerReadJob($path);
                    $job['appended_at'] = time();
                    aiWorkerWriteJob($path, $job);
                }
            }
        } catch (Throwable $e) {
            $job = aiWorkerReadJob($path);
            if (($job['status'] ?? '') === 'cancelled' || $e->getMessage() === 'Worker cancelled') {
                $job['status'] = 'cancelled';
                aiWorkerWriteJob($path, $job);
                return;
            }
            $job['status'] = 'error';
            $job['error'] = $e->getMessage();
            aiWorkerEvent($job, 'error', $e->getMessage());
            aiWorkerWriteJob($path, $job);
        }
        return;
    }
    if (PHP_SAPI !== 'cli') {
        echo json_encode(['success' => false, 'error' => 'Worker job not found']);
    }
}

function aiWorkerPerformJob(array $job, array $config, string $jobPath): string {
    aiWorkerThrowIfCancelled($jobPath);
    $message = (string)($job['message'] ?? '');
    $messages = [['role' => 'system', 'content' => 'You are a helpful assistant. Provide clear, concise responses. Reply in the same language as the user message.']];
    foreach ((array)($job['history'] ?? []) as $msg) {
        $role = (string)($msg['role'] ?? '');
        $content = (string)($msg['content'] ?? '');
        if (($role === 'user' || $role === 'assistant') && trim($content) !== '') $messages[] = ['role' => $role, 'content' => $content];
    }
    $messages[] = ['role' => 'user', 'content' => $message];
    $answer = aiWorkerCompletion($config, (string)($job['model'] ?? ''), $messages, 4000);
    return $answer;
}

function aiUserSettingsGet() {
    global $config;
    $email = getCurrentUserEmailFromMainAPI();
    if ($email === '') {
        $safe = getCurrentUserFromMainAPI();
        $email = $safe . '@local';
    }
    $defaults = [
        'selected_model' => (string)($config['default_model'] ?? ''),
        'selected_role' => 'general',
        'chat_mode' => 'chat',
        'include_file_context' => true,
        'remember_previous' => false,
        'notify_on_complete' => false,
        'has_attached_files' => false,
        'attached_files_count' => 0,
        'mentioned_files_count' => 0,
        'auto_confirm_agent_edits' => false
    ];
    try {
        $db = getAiDb();
        if (!$db) return ['success' => true, 'settings' => $defaults];
        ensureAiAssistantUserSettingsTable($db);
        $hasAutoConfirmColumn = aiTableHasColumn($db, AI_USER_SETTINGS_TABLE, 'auto_confirm_agent_edits');
        $sql = $hasAutoConfirmColumn
            ? "SELECT settings_json, COALESCE(auto_confirm_agent_edits, 0) AS auto_confirm_agent_edits FROM " . AI_USER_SETTINGS_TABLE . " WHERE email = :email LIMIT 1"
            : "SELECT settings_json, 0 AS auto_confirm_agent_edits FROM " . AI_USER_SETTINGS_TABLE . " WHERE email = :email LIMIT 1";
        $stmt = aiPrepare($db, $sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $res = $stmt->execute();
        $row = $res ? $res->fetch(PDO::FETCH_ASSOC) : null;
        if (!$row || !isset($row['settings_json'])) {
            return [
                'success' => true,
                'settings' => $defaults
            ];
        }
        $decoded = json_decode((string)$row['settings_json'], true);
        if (!is_array($decoded)) {
            return [
                'success' => true,
                'settings' => array_merge($defaults, ['auto_confirm_agent_edits' => !empty($row['auto_confirm_agent_edits'])])
            ];
        }
        return [
            'success' => true,
            'settings' => [
                'selected_model' => isset($decoded['selected_model']) ? (string)$decoded['selected_model'] : $defaults['selected_model'],
                'selected_role' => isset($decoded['selected_role']) ? (string)$decoded['selected_role'] : $defaults['selected_role'],
                'chat_mode' => (isset($decoded['chat_mode']) && in_array((string)$decoded['chat_mode'], ['chat', 'agent'], true)) ? (string)$decoded['chat_mode'] : $defaults['chat_mode'],
                'include_file_context' => isset($decoded['include_file_context']) ? (bool)$decoded['include_file_context'] : $defaults['include_file_context'],
                'remember_previous' => isset($decoded['remember_previous']) ? (bool)$decoded['remember_previous'] : $defaults['remember_previous'],
                'notify_on_complete' => isset($decoded['notify_on_complete']) ? (bool)$decoded['notify_on_complete'] : $defaults['notify_on_complete'],
                'has_attached_files' => isset($decoded['has_attached_files']) ? (bool)$decoded['has_attached_files'] : $defaults['has_attached_files'],
                'attached_files_count' => isset($decoded['attached_files_count']) ? max(0, (int)$decoded['attached_files_count']) : 0,
                'mentioned_files_count' => isset($decoded['mentioned_files_count']) ? max(0, (int)$decoded['mentioned_files_count']) : 0,
                'auto_confirm_agent_edits' => !empty($row['auto_confirm_agent_edits'])
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'settings' => $defaults];
    }
}

function aiUserSettingsSet($selectedModel, $selectedRole, $chatMode, $includeFileContext, $rememberPrevious, $hasAttachedFiles = null, $autoConfirmAgentEdits = null, $attachedFilesCount = null, $mentionedFilesCount = null, $notifyOnComplete = null) {
    $email = getCurrentUserEmailFromMainAPI();
    if ($email === '') {
        $safe = getCurrentUserFromMainAPI();
        $email = $safe . '@local';
    }
    $selectedModel = is_string($selectedModel) ? trim($selectedModel) : '';
    $selectedRole = is_string($selectedRole) ? trim($selectedRole) : '';
    $chatMode = is_string($chatMode) ? trim($chatMode) : '';
    if (!in_array($chatMode, ['chat', 'agent'], true)) $chatMode = '';
    $include = null;
    if ($includeFileContext !== null && $includeFileContext !== '') {
        $raw = strtolower(trim((string)$includeFileContext));
        $include = in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }
    $remember = null;
    if ($rememberPrevious !== null && $rememberPrevious !== '') {
        $raw = strtolower(trim((string)$rememberPrevious));
        $remember = in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }
    $notify = null;
    if ($notifyOnComplete !== null && $notifyOnComplete !== '') {
        $rawN = strtolower(trim((string)$notifyOnComplete));
        $notify = in_array($rawN, ['1', 'true', 'yes', 'on'], true);
    }
    $hasAttached = null;
    if ($hasAttachedFiles !== null && $hasAttachedFiles !== '') {
        $raw = strtolower(trim((string)$hasAttachedFiles));
        $hasAttached = in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }
    $autoConfirm = null;
    if ($autoConfirmAgentEdits !== null && $autoConfirmAgentEdits !== '') {
        $rawA = strtolower(trim((string)$autoConfirmAgentEdits));
        $autoConfirm = in_array($rawA, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }
    $attachedCount = null;
    if ($attachedFilesCount !== null && $attachedFilesCount !== '') {
        $attachedCount = max(0, (int)$attachedFilesCount);
    }
    $mentionedCount = null;
    if ($mentionedFilesCount !== null && $mentionedFilesCount !== '') {
        $mentionedCount = max(0, (int)$mentionedFilesCount);
    }
    try {
        $db = getAiDb();
        if (!$db) return ['success' => false, 'error' => 'Database unavailable'];
        ensureAiAssistantUserSettingsTable($db);
        $existing = [];
        $existingAuto = 0;
        $hasAutoConfirmColumn = aiTableHasColumn($db, AI_USER_SETTINGS_TABLE, 'auto_confirm_agent_edits');
        $selectSql = $hasAutoConfirmColumn
            ? "SELECT settings_json, COALESCE(auto_confirm_agent_edits, 0) AS auto_confirm_agent_edits FROM " . AI_USER_SETTINGS_TABLE . " WHERE email = :email LIMIT 1"
            : "SELECT settings_json, 0 AS auto_confirm_agent_edits FROM " . AI_USER_SETTINGS_TABLE . " WHERE email = :email LIMIT 1";
        $sel = aiPrepare($db, $selectSql);
        $sel->bindValue(':email', $email, PDO::PARAM_STR);
        $res = $sel->execute();
        $row = $res ? $res->fetch(PDO::FETCH_ASSOC) : null;
        if ($row && isset($row['settings_json'])) {
            $decoded = json_decode((string)$row['settings_json'], true);
            if (is_array($decoded)) $existing = $decoded;
        }
        if ($row && isset($row['auto_confirm_agent_edits'])) {
            $existingAuto = (int)$row['auto_confirm_agent_edits'] ? 1 : 0;
        }
        if ($selectedModel !== '') $existing['selected_model'] = $selectedModel;
        if ($selectedRole !== '') $existing['selected_role'] = $selectedRole;
        if ($chatMode !== '') $existing['chat_mode'] = $chatMode;
        if ($include !== null) $existing['include_file_context'] = $include;
        if ($remember !== null) $existing['remember_previous'] = $remember;
        if ($notify !== null) $existing['notify_on_complete'] = $notify;
        if ($hasAttached !== null) $existing['has_attached_files'] = $hasAttached;
        if ($attachedCount !== null) $existing['attached_files_count'] = $attachedCount;
        if ($mentionedCount !== null) $existing['mentioned_files_count'] = $mentionedCount;
        $autoVal = $autoConfirm !== null ? $autoConfirm : $existingAuto;
        $payload = json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) return ['success' => false, 'error' => 'Failed to encode settings'];
        $stmt = $hasAutoConfirmColumn
            ? aiPrepare($db, "
                INSERT INTO " . AI_USER_SETTINGS_TABLE . " (email, settings_json, updated_at, auto_confirm_agent_edits)
                VALUES (:email, :json, extract(epoch from now())::bigint, :auto_confirm)
                ON CONFLICT(email) DO UPDATE SET
                    settings_json = excluded.settings_json,
                    updated_at = excluded.updated_at,
                    auto_confirm_agent_edits = excluded.auto_confirm_agent_edits
            ")
            : aiPrepare($db, "
                INSERT INTO " . AI_USER_SETTINGS_TABLE . " (email, settings_json, updated_at)
                VALUES (:email, :json, extract(epoch from now())::bigint)
                ON CONFLICT(email) DO UPDATE SET
                    settings_json = excluded.settings_json,
                    updated_at = excluded.updated_at
            ");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':json', $payload, PDO::PARAM_STR);
        if ($hasAutoConfirmColumn) $stmt->bindValue(':auto_confirm', $autoVal, PDO::PARAM_INT);
        $stmt->execute();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getAiDb() {
    static $db = null;
    if ($db !== null) return $db;

    require_once __DIR__ . '/../../lib/app_db.php';
    try {
        $db = app_main_db();
        $db->enableExceptions(true);
        
        $db->exec("SELECT 1");
        $db->exec("SELECT 1");
        return $db;
    } catch (Exception $e) {
        cloud_safe_error_log("AI DB connection failed: " . $e->getMessage());
        $db = null;
        return null;
    }
}
function aiPrepare($db, $sql) {
    if (!$db) throw new Exception('Database unavailable');
    $stmt = @$db->prepare($sql);
    if (!$stmt) throw new Exception('DB prepare failed');
    return $stmt;
}

function ensureAiChatTable($db) {
    if (!$db) return;
    migrateAiTableIfNeeded($db, AI_CHAT_OLD_TABLE, AI_CHAT_TABLE);

    // Create table if missing (one shared table across all users; filtered by email)
    $db->exec("
        CREATE TABLE IF NOT EXISTS " . AI_CHAT_TABLE . " (
            id         BIGSERIAL PRIMARY KEY,
            email      TEXT    NOT NULL,
            chat_id    TEXT    NOT NULL DEFAULT '',
            question   TEXT    NOT NULL,
            answer     TEXT    NOT NULL,
            model      TEXT    NOT NULL DEFAULT '',
            file_name  TEXT    NOT NULL DEFAULT '',
            time_seconds REAL   NULL,
            tokens       INTEGER NULL,
            speed        REAL   NULL,
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )
    ");
    $db->exec("DROP INDEX IF EXISTS idx_ai_assistant_chats_email_created");
    try {
        $cols = [];
        $res = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='" . AI_CHAT_TABLE . "'");
        while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
            if (isset($row['name'])) $cols[$row['name']] = true;
        }
        if (!isset($cols['time_seconds'])) $db->exec("ALTER TABLE " . AI_CHAT_TABLE . " ADD COLUMN time_seconds REAL NULL");
        if (!isset($cols['tokens'])) $db->exec("ALTER TABLE " . AI_CHAT_TABLE . " ADD COLUMN tokens INTEGER NULL");
        if (!isset($cols['speed'])) $db->exec("ALTER TABLE " . AI_CHAT_TABLE . " ADD COLUMN speed REAL NULL");
        if (!isset($cols['meta_json'])) $db->exec("ALTER TABLE " . AI_CHAT_TABLE . " ADD COLUMN meta_json TEXT NOT NULL DEFAULT ''");
        if (!isset($cols['chat_id'])) $db->exec("ALTER TABLE " . AI_CHAT_TABLE . " ADD COLUMN chat_id TEXT NOT NULL DEFAULT ''");
    } catch (Exception $e) {
        // ignore
    }
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_ai_assistant_chats_email_created ON " . AI_CHAT_TABLE . "(email, created_at DESC)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_ai_assistant_chats_email_chat_created ON " . AI_CHAT_TABLE . "(email, chat_id, created_at DESC)");
}

function ensureAiChatSessionTable($db) {
    if (!$db) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS " . AI_CHAT_SESSION_TABLE . " (
            id         TEXT    PRIMARY KEY,
            email      TEXT    NOT NULL,
            title      TEXT    NOT NULL DEFAULT 'New chat',
            created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )
    ");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_ai_chat_sessions_email_updated ON " . AI_CHAT_SESSION_TABLE . "(email, updated_at DESC)");
}

function aiCreateChatSession($db, string $email, string $title): string {
    $id = bin2hex(random_bytes(12));
    $stmt = aiPrepare($db, "INSERT INTO " . AI_CHAT_SESSION_TABLE . " (id, email, title, created_at, updated_at) VALUES (:id, :email, :title, extract(epoch from now())::bigint, extract(epoch from now())::bigint)");
    $stmt->bindValue(':id', $id, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':title', $title !== '' ? $title : 'New chat', PDO::PARAM_STR);
    $stmt->execute();
    return $id;
}

function aiEnsureChatSession($db, string $email, string $chatId, string $question = ''): string {
    ensureAiChatSessionTable($db);
    $chatId = trim($chatId);
    if ($chatId !== '') {
        $stmt = aiPrepare($db, "SELECT id FROM " . AI_CHAT_SESSION_TABLE . " WHERE email = :email AND id = :id LIMIT 1");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':id', $chatId, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($row) return $chatId;
    }
    $stmt = aiPrepare($db, "SELECT id FROM " . AI_CHAT_SESSION_TABLE . " WHERE email = :email ORDER BY updated_at DESC, created_at DESC LIMIT 1");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if ($row && $chatId === '') return (string)$row['id'];
    $id = aiCreateChatSession($db, $email, $question !== '' ? aiChatTitleFromQuestion($question) : 'New chat');
    $upd = aiPrepare($db, "UPDATE " . AI_CHAT_TABLE . " SET chat_id = :id WHERE email = :email AND (chat_id = '' OR chat_id IS NULL)");
    $upd->bindValue(':id', $id, PDO::PARAM_STR);
    $upd->bindValue(':email', $email, PDO::PARAM_STR);
    $upd->execute();
    return $id;
}

function aiPruneChatSessions($db, string $email): void {
    $stmt = aiPrepare($db, "SELECT id FROM " . AI_CHAT_SESSION_TABLE . " WHERE email = :email ORDER BY updated_at DESC, created_at DESC LIMIT -1 OFFSET 50");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $res = $stmt->execute();
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $id = (string)$row['id'];
        $delMsg = aiPrepare($db, "DELETE FROM " . AI_CHAT_TABLE . " WHERE email = :email AND chat_id = :id");
        $delMsg->bindValue(':email', $email, PDO::PARAM_STR);
        $delMsg->bindValue(':id', $id, PDO::PARAM_STR);
        $delMsg->execute();
        $del = aiPrepare($db, "DELETE FROM " . AI_CHAT_SESSION_TABLE . " WHERE email = :email AND id = :id");
        $del->bindValue(':email', $email, PDO::PARAM_STR);
        $del->bindValue(':id', $id, PDO::PARAM_STR);
        $del->execute();
    }
}

function aiPruneEmptyChatSessions($db, string $email): void {
    try {
        $sql = "DELETE FROM " . AI_CHAT_SESSION_TABLE . " s
            WHERE s.email = :email
              AND s.title = 'New chat'
              AND NOT EXISTS (SELECT 1 FROM " . AI_CHAT_TABLE . " c WHERE c.email = s.email AND c.chat_id = s.id)
              AND s.id NOT IN (
                  SELECT id FROM " . AI_CHAT_SESSION_TABLE . "
                  WHERE email = :email_keep AND title = 'New chat'
                  ORDER BY updated_at DESC, created_at DESC
                  LIMIT 1
              )";
        $stmt = aiPrepare($db, $sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':email_keep', $email, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Exception $e) {
    }
}

function ensureAiAssistantUserSettingsTable($db) {
    if (!$db) return;
    migrateAiTableIfNeeded($db, AI_USER_SETTINGS_OLD_TABLE, AI_USER_SETTINGS_TABLE);

    $db->exec("
        CREATE TABLE IF NOT EXISTS " . AI_USER_SETTINGS_TABLE . " (
            email         TEXT    NOT NULL PRIMARY KEY,
            settings_json TEXT    NOT NULL DEFAULT '{}',
            updated_at    INTEGER NOT NULL DEFAULT 0,
            auto_confirm_agent_edits INTEGER NOT NULL DEFAULT 0
        )
    ");
    try {
        $cols = [];
        $res = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='" . AI_USER_SETTINGS_TABLE . "'");
        while ($res && ($r = $res->fetch(PDO::FETCH_ASSOC))) {
            if (isset($r['name'])) $cols[$r['name']] = true;
        }
        if (!isset($cols['auto_confirm_agent_edits'])) {
            $db->exec("ALTER TABLE " . AI_USER_SETTINGS_TABLE . " ADD COLUMN auto_confirm_agent_edits INTEGER NOT NULL DEFAULT 0");
        }
    } catch (Exception $e) {
    }
}

function migrateAiTableIfNeeded($db, $oldName, $newName) {
    if (!$db || !aiSqliteTableExists($db, $oldName) || aiSqliteTableExists($db, $newName)) return;
    $db->exec("ALTER TABLE " . $oldName . " RENAME TO " . $newName);
}

function aiSqliteTableExists($db, $name) {
    $stmt = aiPrepare($db, "SELECT table_name AS name FROM information_schema.tables WHERE table_schema='public' AND table_name = :name LIMIT 1");
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $res = $stmt->execute();
    return (bool)($res && $res->fetch(PDO::FETCH_ASSOC));
}
function aiTableHasColumn($db, $table, $column) {
    if (!$db || !$table || !$column) return false;
    try {
        $res = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='" . AppDatabase::escapeString($table) . "'");
        while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
            if (isset($row['name']) && $row['name'] === $column) return true;
        }
    } catch (Exception $e) {
    }
    return false;
}

// Functions for chat history management
function saveChatHistory($history) {
    try {
        $historyFile = getHistoryFilePath();
        
        // Create directory if not exists
        $dir = dirname($historyFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Ensure history is valid JSON
        if (is_string($history)) {
            // Already JSON string
            $result = file_put_contents($historyFile, $history);
        } else {
            $result = file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        if ($result === false) {
            throw new Exception('Failed to write history file');
        }
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function loadChatHistory() {
    try {
        $historyFile = getHistoryFilePath();
        
        if (!file_exists($historyFile)) {
            return ['success' => true, 'history' => []];
        }
        
        $content = file_get_contents($historyFile);
        if ($content === false) {
            throw new Exception('Failed to read history file');
        }
        
        $history = json_decode($content, true);
        
        // Check JSON validity
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to repair or return empty
            return ['success' => true, 'history' => []];
        }
        
        return ['success' => true, 'history' => $history ?: []];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function clearChatHistory() {
    try {
        $historyFile = getHistoryFilePath();
        
        if (file_exists($historyFile)) {
            $result = unlink($historyFile);
            if (!$result) {
                throw new Exception('Failed to delete history file');
            }
        }
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getHistoryFilePath() {
    // Get current user from main API
    $user = getCurrentUserFromMainAPI();
    
    // Sanitize username
    $safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '', $user);
    if (empty($safeUser)) $safeUser = 'default';
    
    // Save in data directory
    return __DIR__ . '/data/' . $safeUser . '_ai_chat_history.json';
}

echo json_encode(['error' => 'Unknown action']);
?>
