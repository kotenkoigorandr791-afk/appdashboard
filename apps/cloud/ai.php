<?php 
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
// ai.php - AI Assistant endpoint
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

require_once __DIR__ . '/includes/ai_config_store.php';
require_once __DIR__ . '/includes/ai_proxy.php';
$config = cloud_ai_load_config();
if (empty($config['api_key']) || empty($config['api_endpoint'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid AI configuration']);
    exit;
}

const AI_CHAT_TABLE = 'cloud_ai_assistant_chats';
const AI_CHAT_OLD_TABLE = 'ai_assistant_chats';
const AI_USER_SETTINGS_TABLE = 'cloud_ai_assistant_user_settings';
const AI_USER_SETTINGS_OLD_TABLE = 'ai_assistant_user_settings';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

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
        $_POST['meta_json'] ?? ''
    ));
    exit;
} elseif ($action === 'ai_chat_load') {
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 5;
    echo json_encode(aiChatLoad($offset, $limit));
    exit;
} elseif ($action === 'ai_chat_clear') {
    echo json_encode(aiChatClear());
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
        CURLOPT_TIMEOUT => 180
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

// Функция для получения текущего пользователя из главного API
// ai.php - замените существующую функцию

function getCurrentUserFromMainAPI() {
    // Read from SQLite database (current session storage)
    require_once __DIR__ . '/../../lib/app_db.php';
    
    try {
        $db = app_main_db();
        
        $session_id = session_id();
        
        // Try to get email from active_sessions table using current session
        if ($session_id) {
            $stmt = aiPrepare($db, "SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1");
            $stmt->bindValue(':sid', $session_id, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
            
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
            $stmt->bindValue(':sid', $cookie_session, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
            
            if ($row && !empty($row['email'])) {
                $email = $row['email'];
                $username = explode('@', $email)[0] ?? 'user';
                $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
                return $username ?: 'default';
            }
        }
        
        $db->close();
    } catch (Exception $e) {
        cloud_safe_error_log('getCurrentUserFromMainAPI error: ' . $e->getMessage());
    }

    // Fallback: use IP address
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $username = 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);

    return $username;
}

function getCurrentUserEmailFromMainAPI() {
    // Read from SQLite database (current session storage)
    require_once __DIR__ . '/../../lib/app_db.php';
    
    try {
        $db = app_main_db();
        
        $session_id = session_id();
        
        // Try to get email from active_sessions table using current session
        if ($session_id) {
            $stmt = aiPrepare($db, "SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1");
            $stmt->bindValue(':sid', $session_id, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
            
            if ($row && !empty($row['email'])) {
                return (string)$row['email'];
            }
        }
        
        // Fallback: try PHP session cookie
        if (isset($_COOKIE['PHPSESSID'])) {
            $cookie_session = $_COOKIE['PHPSESSID'];
            $stmt = aiPrepare($db, "SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1");
            $stmt->bindValue(':sid', $cookie_session, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
            
            if ($row && !empty($row['email'])) {
                return (string)$row['email'];
            }
        }
        
        $db->close();
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
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $db->close();
        return $row && (int)($row['role'] ?? 0) === 2;
    } catch (Exception $e) {
        return false;
    }
}

function aiChatAppend($question, $answer, $model = '', $fileName = '', $timeSeconds = '', $tokens = '', $speed = '', $metaJson = '') {
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

    $email = getCurrentUserEmailFromMainAPI();
    if ($email === '') {
        // Fallback to legacy safe user id (still store in shared table)
        $safe = getCurrentUserFromMainAPI();
        $email = $safe . '@local';
    }

    try {
        $db = getAiDb();
        if (!$db) return ['success' => false, 'error' => 'Database unavailable'];

        ensureAiChatTable($db);

        $stmt = aiPrepare($db, "
            INSERT INTO " . AI_CHAT_TABLE . " (email, question, answer, model, file_name, time_seconds, tokens, speed, meta_json, created_at)
            VALUES (:email, :q, :a, :m, :f, :t, :tok, :s, :meta, strftime('%s','now'))
        ");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':q', $question, SQLITE3_TEXT);
        $stmt->bindValue(':a', $answer, SQLITE3_TEXT);
        $stmt->bindValue(':m', $model, SQLITE3_TEXT);
        $stmt->bindValue(':f', $fileName, SQLITE3_TEXT);
        $stmt->bindValue(':meta', $metaJson, SQLITE3_TEXT);
        if ($timeVal === null) $stmt->bindValue(':t', null, SQLITE3_NULL); else $stmt->bindValue(':t', $timeVal, SQLITE3_FLOAT);
        if ($tokensVal === null) $stmt->bindValue(':tok', null, SQLITE3_NULL); else $stmt->bindValue(':tok', $tokensVal, SQLITE3_INTEGER);
        if ($speedVal === null) $stmt->bindValue(':s', null, SQLITE3_NULL); else $stmt->bindValue(':s', $speedVal, SQLITE3_FLOAT);
        $stmt->execute();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function aiChatLoad($offset, $limit) {
    $offset = max(0, (int)$offset);
    $limit = (int)$limit;
    if ($limit <= 0) $limit = 5;
    if ($limit > 50) $limit = 50;

    $email = getCurrentUserEmailFromMainAPI();
    if ($email === '') {
        $safe = getCurrentUserFromMainAPI();
        $email = $safe . '@local';
    }

    try {
        $db = getAiDb();
        if (!$db) return ['success' => false, 'error' => 'Database unavailable', 'items' => [], 'has_more' => false];

        ensureAiChatTable($db);

        $stmt = aiPrepare($db, "
            SELECT id, question, answer, model, file_name, time_seconds, tokens, speed, meta_json, created_at
            FROM " . AI_CHAT_TABLE . "
            WHERE email = :email
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $rows = [];
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $rows[] = $row;
        }

        $countStmt = aiPrepare($db, "SELECT COUNT(*) AS cnt FROM " . AI_CHAT_TABLE . " WHERE email = :email");
        $countStmt->bindValue(':email', $email, SQLITE3_TEXT);
        $countRes = $countStmt->execute();
        $cntRow = $countRes ? $countRes->fetchArray(SQLITE3_ASSOC) : null;
        $total = (int)($cntRow['cnt'] ?? 0);

        return [
            'success' => true,
            'items' => $rows,
            'has_more' => ($offset + count($rows)) < $total
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'items' => [], 'has_more' => false];
    }
}

function aiChatClear() {
    $email = getCurrentUserEmailFromMainAPI();
    if ($email === '') {
        $safe = getCurrentUserFromMainAPI();
        $email = $safe . '@local';
    }

    try {
        $db = getAiDb();
        if (!$db) return ['success' => false, 'error' => 'Database unavailable'];

        ensureAiChatTable($db);

        $stmt = aiPrepare($db, "DELETE FROM " . AI_CHAT_TABLE . " WHERE email = :email");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->execute();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
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
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
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
        $sel->bindValue(':email', $email, SQLITE3_TEXT);
        $res = $sel->execute();
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
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
                VALUES (:email, :json, strftime('%s','now'), :auto_confirm)
                ON CONFLICT(email) DO UPDATE SET
                    settings_json = excluded.settings_json,
                    updated_at = excluded.updated_at,
                    auto_confirm_agent_edits = excluded.auto_confirm_agent_edits
            ")
            : aiPrepare($db, "
                INSERT INTO " . AI_USER_SETTINGS_TABLE . " (email, settings_json, updated_at)
                VALUES (:email, :json, strftime('%s','now'))
                ON CONFLICT(email) DO UPDATE SET
                    settings_json = excluded.settings_json,
                    updated_at = excluded.updated_at
            ");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':json', $payload, SQLITE3_TEXT);
        if ($hasAutoConfirmColumn) $stmt->bindValue(':auto_confirm', $autoVal, SQLITE3_INTEGER);
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
        
        $db->exec("PRAGMA journal_mode = WAL");
        $db->exec("PRAGMA foreign_keys = ON");
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
    if (!$stmt) throw new Exception('DB prepare failed: ' . $db->lastErrorMsg());
    return $stmt;
}

function ensureAiChatTable($db) {
    if (!$db) return;
    migrateAiTableIfNeeded($db, AI_CHAT_OLD_TABLE, AI_CHAT_TABLE);

    // Create table if missing (one shared table across all users; filtered by email)
    $db->exec("
        CREATE TABLE IF NOT EXISTS " . AI_CHAT_TABLE . " (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            email      TEXT    NOT NULL,
            question   TEXT    NOT NULL,
            answer     TEXT    NOT NULL,
            model      TEXT    NOT NULL DEFAULT '',
            file_name  TEXT    NOT NULL DEFAULT '',
            time_seconds REAL   NULL,
            tokens       INTEGER NULL,
            speed        REAL   NULL,
            created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
        )
    ");
    $db->exec("DROP INDEX IF EXISTS idx_ai_assistant_chats_email_created");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_ai_assistant_chats_email_created ON " . AI_CHAT_TABLE . "(email, created_at DESC)");

    // Backfill schema if table existed before columns were added
    try {
        $cols = [];
        $res = $db->query("PRAGMA table_info(" . AI_CHAT_TABLE . ")");
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            if (isset($row['name'])) $cols[$row['name']] = true;
        }
        if (!isset($cols['time_seconds'])) $db->exec("ALTER TABLE " . AI_CHAT_TABLE . " ADD COLUMN time_seconds REAL NULL");
        if (!isset($cols['tokens'])) $db->exec("ALTER TABLE " . AI_CHAT_TABLE . " ADD COLUMN tokens INTEGER NULL");
        if (!isset($cols['speed'])) $db->exec("ALTER TABLE " . AI_CHAT_TABLE . " ADD COLUMN speed REAL NULL");
        if (!isset($cols['meta_json'])) $db->exec("ALTER TABLE " . AI_CHAT_TABLE . " ADD COLUMN meta_json TEXT NOT NULL DEFAULT ''");
    } catch (Exception $e) {
        // ignore
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
        $res = $db->query("PRAGMA table_info(" . AI_USER_SETTINGS_TABLE . ")");
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) {
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
    $stmt = aiPrepare($db, "SELECT name FROM sqlite_master WHERE type='table' AND name = :name LIMIT 1");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $res = $stmt->execute();
    return (bool)($res && $res->fetchArray(SQLITE3_ASSOC));
}
function aiTableHasColumn($db, $table, $column) {
    if (!$db || !$table || !$column) return false;
    try {
        $res = $db->query("PRAGMA table_info(" . $table . ")");
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
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
    // Получаем текущего пользователя из главного API
    $user = getCurrentUserFromMainAPI();
    
    // Sanitize username
    $safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '', $user);
    if (empty($safeUser)) $safeUser = 'default';
    
    // Save in data directory
    return __DIR__ . '/data/' . $safeUser . '_ai_chat_history.json';
}

echo json_encode(['error' => 'Unknown action']);
?>
