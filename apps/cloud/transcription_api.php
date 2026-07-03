<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

function app_path($relativePath = '') {
    $baseDir = realpath(__DIR__);
    if ($baseDir === false) $baseDir = __DIR__;
    if ($relativePath === '') return $baseDir;
    return $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
}
function python_bin() {
    $candidates = ['python3', 'python'];
    foreach ($candidates as $bin) {
        if (strpos($bin, ':\\') !== false && file_exists($bin)) return $bin;
        $path = trim((string)shell_exec('which ' . escapeshellarg($bin) . ' 2>/dev/null'));
        if ($path !== '') return $path;
    }
    return 'python3';
}
function resolve_user_file_path($user, $relativePath) {
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') return null;
    $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    $candidates = [];
    if (preg_match('/^(?:[a-zA-Z]:[\\\\\\/]|\/)/', $relativePath)) $candidates[] = $relativePath;
    $candidates[] = $normalized;
    $candidates[] = app_path($normalized);
    $candidates[] = app_path('../' . $normalized);
    $roots = [
        app_path('storage' . DIRECTORY_SEPARATOR . $user),
        app_path('../storage' . DIRECTORY_SEPARATOR . $user),
        app_path('temp'),
        app_path('temp_convert'),
        app_path('transcription_uploads'),
        app_path('tts_output')
    ];
    foreach ($roots as $root) $candidates[] = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $normalized;
    foreach ($candidates as $candidate) {
        if (is_file($candidate) || is_dir($candidate)) {
            $resolved = realpath($candidate);
            if ($resolved !== false) return $resolved;
        }
    }
    return null;
}
function to_public_relative_path($path) {
    $normalized = str_replace('\\', '/', (string)$path);
    if (preg_match('#/storage/[^/]+/(.+)$#', $normalized, $m)) return ltrim($m[1], '/');
    if (preg_match('#/transcription_uploads/(.+)$#', $normalized, $m)) return ltrim($m[1], '/');
    if (preg_match('#/tts_output/(.+)$#', $normalized, $m)) return ltrim($m[1], '/');
    return basename($normalized);
}

function get_session_email() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['email'] ?? null;
}

function invalidate_file_tree($email) {
    if (!$email) return;

    require_once __DIR__ . '/../../lib/app_db.php';
    try {
        $db = app_main_db();
        $db->enableExceptions(true);
        $db->busyTimeout(3000);

        // Ensure table exists
        $db->exec("CREATE TABLE IF NOT EXISTS cloud_folder_state (
            email TEXT PRIMARY KEY,
            tree_json TEXT NOT NULL DEFAULT '',
            tree_valid INTEGER NOT NULL DEFAULT 0,
            updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
        )");

        $stmt = $db->prepare("UPDATE cloud_folder_state SET tree_valid = 0, tree_json = '', updated_at = strftime('%s','now') WHERE email = ?");
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $stmt->execute();
        $db->close();
    } catch (Exception $e) {
        cloud_safe_error_log("invalidate_file_tree failed: " . $e->getMessage());
    }
}

function bridge_call($action, $payload = []) {
    $python = python_bin();
    $script = app_path('transcription_db_bridge.py');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $b64 = 'b64:' . base64_encode($json);

    // Create temp file for output to preserve UTF-8
    $tempFile = tempnam(sys_get_temp_dir(), 'bridge_');
    $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($action) . ' ' . escapeshellarg($b64) . ' > ' . escapeshellarg($tempFile) . ' 2>&1';

    exec($command, $output, $code);

    // Read output from temp file
    $raw = '';
    if (file_exists($tempFile)) {
        $raw = file_get_contents($tempFile);
        unlink($tempFile);
    }

    $raw = trim($raw);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return $decoded;
    return ['success' => false, 'error' => 'Bridge call failed', 'raw' => $raw, 'code' => $code];
}
function handle_sse_stream() {
    $progressId = $_GET['progress_id'] ?? '';
    if (!$progressId) {
        http_response_code(400);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No progress_id provided']);
        exit;
    }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    header('Access-Control-Allow-Origin: *');
    $lastProgress = -1;
    $lastSentTime = 0;
    $maxTime = 600;
    $startTime = time();
    while ((time() - $startTime) < $maxTime) {
        $data = bridge_call('get_progress', ['progress_id' => $progressId]);
        if (($data['found'] ?? false) !== true) {
            if ($lastProgress === -1) {
                echo "data: " . json_encode(['error' => 'Progress session not found']) . "\n\n";
                flush();
                break;
            }
        } else {
            $progress = (int)$data['percentage'];
            $message = (string)$data['message'];
            $isHeartbeatTick = (time() - $lastSentTime) >= 5;
            if ($lastProgress === -1 || $progress !== $lastProgress || $isHeartbeatTick) {
                echo "data: " . json_encode($data) . "\n\n";
                flush();
                $lastProgress = $progress;
                $lastSentTime = time();
                if ($progress >= 100 || stripos($message, 'Error:') !== false || ($data['status'] ?? '') === 'error') break;
                if ($progress === 0 && ((int)($data['stale_seconds'] ?? 0)) >= 45) {
                    echo "data: " . json_encode(['error' => 'Transcription process appears to be stuck at startup']) . "\n\n";
                    flush();
                    break;
                }
            }
        }
        sleep(1);
    }
    echo "data: " . json_encode(['done' => true]) . "\n\n";
    flush();
    exit;
}
$ensure = bridge_call('ensure_tables', []);
if (($ensure['success'] ?? false) !== true) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'error' => 'Database bridge unavailable', 'debug' => $ensure]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'stream_progress') {
        handle_sse_stream();
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}
$action = $_POST['action'] ?? '';
if ($action === 'start_transcription') {
    $audioPath = $_POST['audio_path'] ?? '';
    $language = $_POST['language'] ?? 'pl';
    $user = $_POST['user'] ?? 'default';
    if (!$audioPath) {
        echo json_encode(['success' => false, 'error' => 'No audio path provided']);
        exit;
    }
    $pythonPath = python_bin();
    $ffmpegPath = trim(shell_exec('which ffmpeg 2>/dev/null') ?: '/usr/bin/ffmpeg');
    $uploadDir = app_path('transcription_uploads');
    $normalizedProgressId = uniqid();
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $uploadJobDir = $uploadDir . DIRECTORY_SEPARATOR . $normalizedProgressId;
    if (!is_dir($uploadJobDir)) mkdir($uploadJobDir, 0777, true);
    $sourcePath = resolve_user_file_path($user, $audioPath);
    if (!$sourcePath || !file_exists($sourcePath)) {
        echo json_encode(['success' => false,'error' => 'Source audio file not found']);
        exit;
    }
    $fileName = uniqid() . '_' . basename($audioPath);
    $filePath = $uploadJobDir . DIRECTORY_SEPARATOR . $fileName;
    if (!copy($sourcePath, $filePath)) {
        echo json_encode(['success' => false,'error' => 'Failed to copy audio file']);
        exit;
    }
    // Verify the copied file exists and is readable
    if (!file_exists($filePath) || !is_readable($filePath)) {
        echo json_encode(['success' => false,'error' => 'Copied file is not accessible: ' . $filePath]);
        exit;
    }
    // Use realpath to get absolute path, keep Windows backslashes
    $filePath = realpath($filePath);
    $ins = bridge_call('start_job', ['progress_id' => $normalizedProgressId, 'user' => $user, 'audio_path' => $audioPath, 'language' => $language]);
    if (($ins['success'] ?? false) !== true) {
        echo json_encode(['success' => false, 'error' => 'Failed to create transcription job']);
        exit;
    }
    $pythonScript = app_path('transcribe.py');
    if (!file_exists($pythonScript)) {
        echo json_encode(['success' => false,'error' => 'Python script not found']);
        exit;
    }
    if (!file_exists($ffmpegPath)) {
        echo json_encode(['success' => false,'error' => 'FFmpeg not found at: ' . $ffmpegPath]);
        exit;
    }
    exec("\"$ffmpegPath\" -version 2>&1", $ffmpegOutput, $ffmpegReturn);
    if ($ffmpegReturn !== 0) {
        echo json_encode(['success' => false,'error' => 'FFmpeg not working: ' . implode("\n", $ffmpegOutput)]);
        exit;
    }
    $command = escapeshellarg($pythonPath) . ' ' . escapeshellarg($pythonScript) . ' ' . escapeshellarg($filePath) . ' ' . escapeshellarg($language) . ' ' . escapeshellarg($normalizedProgressId) . ' ' . escapeshellarg($user) . ' ' . escapeshellarg($audioPath);
    $pythonLogFile = $uploadJobDir . DIRECTORY_SEPARATOR . 'python.log';
    if (substr(php_uname(), 0, 7) === "Windows") {
        $runCommand = 'start "" /B cmd /C ' . escapeshellarg($command . ' > ' . escapeshellarg($pythonLogFile) . ' 2>&1');
        pclose(popen($runCommand, "r"));
        $success = true;
    } else {
        exec($command . ' > ' . escapeshellarg($pythonLogFile) . ' 2>&1 &', $output, $returnCode);
        $success = ($returnCode === 0);
    }
    if ($success) {
        echo json_encode(['success' => true,'message' => 'Transcription started','transcription_file' => 'db:' . $normalizedProgressId,'progress_id' => $normalizedProgressId]);
    } else {
        echo json_encode(['success' => false,'error' => 'Failed to start transcription process']);
    }
    exit;
}
if ($action === 'get_progress') {
    $progressId = preg_replace('/^progress_/', '', ($_POST['progress_id'] ?? ''));
    $data = bridge_call('get_progress', ['progress_id' => $progressId]);
    if (($data['found'] ?? false) !== true) {
        echo json_encode(['progress' => 0,'message' => '','transcription' => '','isProcessing' => false,'isActive' => false,'timestamp' => 0,'stale_seconds' => 0]);
        exit;
    }
    echo json_encode(['progress' => (int)$data['progress'],'message' => (string)$data['message'],'transcription' => (string)$data['transcription'],'isProcessing' => (bool)$data['isProcessing'],'isActive' => (bool)$data['isActive'],'timestamp' => (int)$data['timestamp'],'stale_seconds' => (int)$data['stale_seconds']]);
    exit;
}
if ($action === 'find_active_progress') {
    $user = $_POST['user'] ?? 'default';
    echo json_encode(bridge_call('find_active', ['user' => $user]));
    exit;
}
if ($action === 'stream_progress') {
    handle_sse_stream();
    exit;
}
if ($action === 'save_transcription_to_server') {
    $audioPath = $_POST['audio_path'] ?? '';
    $user = $_POST['user'] ?? 'default';
    $transcription = $_POST['transcription'] ?? '';
    if (!$audioPath || !$transcription) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters: audio_path and transcription required']);
        exit;
    }
    // Resolve the source audio file to find the target directory
    $sourcePath = resolve_user_file_path($user, $audioPath);
    if (!$sourcePath || !file_exists($sourcePath)) {
        echo json_encode(['success' => false, 'error' => 'Source audio file not found']);
        exit;
    }
    $targetDir = dirname($sourcePath);
    // Create output filename based on audio filename
    $audioBasename = pathinfo($sourcePath, PATHINFO_FILENAME);
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $audioBasename . '.txt';
    if (file_put_contents($targetPath, $transcription) !== false) {
        $email = get_session_email();
        if ($email) {
            invalidate_file_tree($email);
        }
        echo json_encode(['success' => true, 'saved_path' => to_public_relative_path($targetPath), 'message' => 'Transcription saved successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save transcription file']);
    }
    exit;
}
if ($action === 'stop_transcription') {
    $progressId = $_POST['progress_id'] ?? '';
    if (!$progressId) {
        echo json_encode(['success' => false, 'error' => 'No progress_id provided']);
        exit;
    }
    // Kill all Python processes related to transcription
    if (substr(php_uname(), 0, 7) === "Windows") {
        exec('taskkill /F /IM python.exe /FI "WINDOWTITLE eq transcribe*" 2>&1', $output, $returnCode);
    } else {
        exec('pkill -f "transcribe.py" 2>&1', $output, $returnCode);
    }
    // Update job status to stopped
    $result = bridge_call('stop_job', ['progress_id' => $progressId]);
    echo json_encode(['success' => true, 'message' => 'Transcription stopped']);
    exit;
}
if ($action === 'delete_temp_file') {
    $progressId = $_POST['progress_id'] ?? '';
    if (!$progressId) {
        echo json_encode(['success' => false, 'error' => 'No progress_id provided']);
        exit;
    }
    $uploadDir = app_path('transcription_uploads');
    $uploadJobDir = $uploadDir . DIRECTORY_SEPARATOR . $progressId;

    $deleted = false;
    if (is_dir($uploadJobDir)) {
        // Delete all files in the directory
        $files = glob($uploadJobDir . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        // Remove the directory
        $deleted = @rmdir($uploadJobDir);
    }

    echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Temporary file deleted' : 'No temporary file found']);
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
