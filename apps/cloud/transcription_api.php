<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');
require_once dirname(__DIR__, 2) . '/storage_helper.php';
require_once __DIR__ . '/includes/cloud_groups.php';
require_once __DIR__ . '/includes/transcription_jobs.php';
require_once __DIR__ . '/includes/ai_speech.php';

function app_path($relativePath = '') {
    $baseDir = realpath(__DIR__);
    if ($baseDir === false) $baseDir = __DIR__;
    if ($relativePath === '') return $baseDir;
    return $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
}

// PHP CLI binary used to start the background transcription worker. PHP_BINARY
// points at php-fpm when this endpoint runs inside FPM, so the CLI has to be
// looked up separately.
function transcription_php_bin(): string {
    foreach (['/usr/local/bin/php', '/usr/bin/php'] as $candidate) {
        if (is_executable($candidate)) return $candidate;
    }
    $which = trim((string)@shell_exec('which php 2>/dev/null'));
    return $which !== '' ? $which : 'php';
}

function transcription_split_root_path(string $relativePath): array {
    $path = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
    $map = ['.archive' => 'archive', '.temp_files' => 'temp', '.documents' => 'documents', '.photos' => 'photos', '.pdf' => 'pdf', '.trash' => 'trash'];
    foreach ($map as $prefix => $rootKey) {
        if ($path === $prefix) return [$rootKey, ''];
        if (str_starts_with($path, $prefix . '/')) return [$rootKey, substr($path, strlen($prefix) + 1)];
    }
    return ['main', $path];
}
function resolve_user_file_path($user, $relativePath, $root = 'main') {
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') return null;
    $root = trim((string)$root);
    if ($root === '') $root = 'main';
    if (function_exists('cloud_resolve_file_path')) {
        try {
            $cloudPath = cloud_resolve_file_path((string)$user, $root, $relativePath);
            if (is_file($cloudPath) || is_dir($cloudPath)) return realpath($cloudPath) ?: $cloudPath;
        } catch (Throwable $e) {
        }
    }
    if (function_exists('appdata_user_full_path')) {
        [$rootKey, $rootRelativePath] = $root === 'main' ? transcription_split_root_path($relativePath) : [$root, $relativePath];
        try {
            $appDataPath = appdata_user_full_path('cloud', (string)$user, $rootKey, $rootRelativePath, false);
            if (is_file($appDataPath) || is_dir($appDataPath)) return realpath($appDataPath) ?: $appDataPath;
        } catch (Throwable $e) {
        }
    }
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

function transcription_safe_message(string $message): string {
    $message = preg_replace('/\b[A-Za-z]:[\\\\\\/][^\s"\'<>)]*/', '[hidden-path]', $message);
    return preg_replace('/(^|[\s(])\/(?:[^\/\s"\'<>)]*\/)*[^\/\s"\'<>)]*/', '$1[hidden-path]', $message);
}

function get_session_email() {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/includes/session.php';
        cloud_session_start();
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
            updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )");

        $stmt = $db->prepare("UPDATE cloud_folder_state SET tree_valid = 0, tree_json = '', updated_at = extract(epoch from now())::bigint WHERE email = ?");
        $stmt->bindValue(1, $email, PDO::PARAM_STR);
        $stmt->execute();
        $db->close();
    } catch (Exception $e) {
        cloud_safe_error_log("invalidate_file_tree failed: " . $e->getMessage());
    }
}

function transcription_worker_dir(string $progressId, bool $create = false): string {
    $uploadDir = app_path('transcription_uploads');
    $dir = $uploadDir . DIRECTORY_SEPARATOR . $progressId;
    if ($create) {
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
    }
    return $dir;
}

function transcription_start_worker(string $progressId, string $filePath, string $language): bool {
    $worker = app_path('transcription_worker.php');
    if (!is_file($worker)) return false;

    $jobDir = transcription_worker_dir($progressId, true);
    $fallbackDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'cloud_transcription_uploads' . DIRECTORY_SEPARATOR . $progressId;
    if (!is_dir($jobDir) || !is_writable($jobDir)) {
        if (!is_dir($fallbackDir)) @mkdir($fallbackDir, 0777, true);
        $jobDir = $fallbackDir;
    }
    $logFile = $jobDir . DIRECTORY_SEPARATOR . 'worker.log';

    $command = escapeshellarg(transcription_php_bin())
        . ' ' . escapeshellarg($worker)
        . ' ' . escapeshellarg($progressId)
        . ' ' . escapeshellarg($filePath)
        . ' ' . escapeshellarg($language);

    if (substr(php_uname(), 0, 7) === 'Windows') {
        $runCommand = 'start "" /B cmd /C ' . escapeshellarg($command . ' > ' . escapeshellarg($logFile) . ' 2>&1');
        @pclose(@popen($runCommand, 'r'));
        return true;
    }

    // nohup keeps the worker alive after the HTTP request that started it ends.
    $line = 'nohup ' . $command . ' > ' . escapeshellarg($logFile) . ' 2>&1 < /dev/null &';
    @exec($line, $output, $returnCode);
    return $returnCode === 0;
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
    $progressId = preg_replace('/^progress_/', '', (string)$progressId);
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
        $data = transcription_jobs_get($progressId);
        if (($data['found'] ?? false) !== true) {
            if ($lastProgress === -1) {
                echo "data: " . json_encode(['error' => 'Progress session not found']) . "\n\n";
                flush();
                break;
            }
        } else {
            $progress = (int)$data['percentage'];
            $message = transcription_safe_message((string)$data['message']);
            $data['message'] = $message;
            if (isset($data['error'])) $data['error'] = transcription_safe_message((string)$data['error']);
            $isHeartbeatTick = (time() - $lastSentTime) >= 5;
            if ($lastProgress === -1 || $progress !== $lastProgress || $isHeartbeatTick) {
                echo "data: " . json_encode($data) . "\n\n";
                flush();
                $lastProgress = $progress;
                $lastSentTime = time();
                if ($progress >= 100 || ($data['status'] ?? '') === 'stopped' || ($data['status'] ?? '') === 'error' || stripos($message, 'Error:') !== false) break;
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

if (transcription_jobs_db() === null) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
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
    $root = $_POST['root'] ?? 'main';
    if (!$audioPath) {
        echo json_encode(['success' => false, 'error' => 'No audio path provided']);
        exit;
    }

    // The AI hub must be configured: the transcription is done by its saved
    // speech model, there is no local fallback any more.
    $aiConfig = cloud_ai_load_config();
    if (trim((string)($aiConfig['api_key'] ?? '')) === '') {
        echo json_encode([
            'success' => false,
            'error' => 'AI hub API key is not configured (Admin Panel → Settings → AI Assistant)',
        ]);
        exit;
    }

    $sourcePath = resolve_user_file_path($user, $audioPath, $root);
    if (!$sourcePath || !file_exists($sourcePath)) {
        echo json_encode(['success' => false, 'error' => 'Source audio file not found']);
        exit;
    }
    if (!is_file($sourcePath)) {
        echo json_encode(['success' => false, 'error' => 'Source audio path is not a file']);
        exit;
    }
    if (!is_readable($sourcePath)) @chmod($sourcePath, 0644);
    if (!is_readable($sourcePath)) {
        echo json_encode(['success' => false, 'error' => 'Source audio file is not readable']);
        exit;
    }

    $progressId = uniqid();
    $job = transcription_jobs_create($progressId, (string)$user, (string)$audioPath, (string)$language);
    if (!$job) {
        echo json_encode(['success' => false, 'error' => 'Failed to create transcription job']);
        exit;
    }

    if (!transcription_start_worker($progressId, $sourcePath, (string)$language)) {
        transcription_jobs_update($progressId, 0, 'Error: Failed to start transcription worker', '', 'error', 'Failed to start transcription worker');
        echo json_encode(['success' => false, 'error' => 'Failed to start transcription process']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Transcription started',
        'transcription_file' => 'db:' . $progressId,
        'progress_id' => $progressId,
    ]);
    exit;
}
if ($action === 'get_progress') {
    $progressId = preg_replace('/^progress_/', '', ($_POST['progress_id'] ?? ''));
    $data = transcription_jobs_get((string)$progressId);
    if (($data['found'] ?? false) !== true) {
        echo json_encode(['progress' => 0,'message' => '','transcription' => '','isProcessing' => false,'isActive' => false,'timestamp' => 0,'stale_seconds' => 0]);
        exit;
    }
    echo json_encode([
        'progress' => (int)$data['progress'],
        'message' => transcription_safe_message((string)$data['message']),
        'transcription' => (string)$data['transcription'],
        'isProcessing' => (bool)$data['isProcessing'],
        'isActive' => (bool)$data['isActive'],
        'timestamp' => (int)$data['timestamp'],
        'stale_seconds' => (int)$data['stale_seconds'],
        'status' => (string)$data['status'],
        'error' => transcription_safe_message((string)($data['error'] ?? '')),
    ]);
    exit;
}
if ($action === 'find_active_progress') {
    $user = $_POST['user'] ?? 'default';
    echo json_encode(transcription_jobs_find_active((string)$user));
    exit;
}
if ($action === 'stream_progress') {
    handle_sse_stream();
    exit;
}
if ($action === 'save_transcription_to_server') {
    $audioPath = $_POST['audio_path'] ?? '';
    $user = $_POST['user'] ?? 'default';
    $root = $_POST['root'] ?? 'main';
    $transcription = $_POST['transcription'] ?? '';
    if (!$audioPath || !$transcription) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters: audio_path and transcription required']);
        exit;
    }
    // Resolve the source audio file to find the target directory
    $sourcePath = resolve_user_file_path($user, $audioPath, $root);
    if (!$sourcePath || !file_exists($sourcePath)) {
        echo json_encode(['success' => false, 'error' => 'Source audio file not found']);
        exit;
    }
    $targetDir = dirname($sourcePath);
    // Create output filename based on audio filename
    $audioBasename = pathinfo($sourcePath, PATHINFO_FILENAME);
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $audioBasename . '.txt';
    if (file_put_contents($targetPath, $transcription) !== false) {
        if (str_starts_with((string)$root, 'group:')) {
            $rootRelativeAudioPath = appdata_normalize_relpath($audioPath);
        } else {
            [$targetRootKey, $rootRelativeAudioPath] = (string)$root === 'main' ? transcription_split_root_path($audioPath) : [(string)$root, appdata_root_relative_path((string)$root, $audioPath)];
        }
        $audioDir = dirname($rootRelativeAudioPath);
        $relativePath = $audioDir === '.' || $audioDir === ''
            ? basename($targetPath)
            : str_replace(DIRECTORY_SEPARATOR, '/', trim($audioDir, '/\\') . DIRECTORY_SEPARATOR . basename($targetPath));
        $email = get_session_email();
        if ($email) {
            invalidate_file_tree($email);
        }
        echo json_encode(['success' => true, 'saved_path' => $relativePath, 'message' => 'Transcription saved successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save transcription file']);
    }
    exit;
}
if ($action === 'stop_transcription') {
    $progressId = preg_replace('/^progress_/', '', (string)($_POST['progress_id'] ?? ''));
    if (!$progressId) {
        echo json_encode(['success' => false, 'error' => 'No progress_id provided']);
        exit;
    }
    transcription_jobs_stop($progressId);
    // The worker notices the stopped status on its next progress tick; killing it
    // by its own progress id additionally releases the HTTP request immediately.
    if (PHP_OS_FAMILY !== 'Windows') {
        @exec('pkill -f ' . escapeshellarg('transcription_worker.php ' . $progressId) . ' 2>/dev/null');
    }
    echo json_encode(['success' => true, 'message' => 'Transcription stopped']);
    exit;
}
if ($action === 'delete_temp_file') {
    $progressId = preg_replace('/^progress_/', '', (string)($_POST['progress_id'] ?? ''));
    if (!$progressId) {
        echo json_encode(['success' => false, 'error' => 'No progress_id provided']);
        exit;
    }
    $uploadJobDir = transcription_worker_dir($progressId);
    $fallbackJobDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'cloud_transcription_uploads' . DIRECTORY_SEPARATOR . $progressId;

    $deleted = false;
    foreach ([$uploadJobDir, $fallbackJobDir] as $jobDir) {
        if (!is_dir($jobDir)) continue;
        // Delete all files in the directory
        $files = glob($jobDir . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        // Remove the directory
        $deleted = @rmdir($jobDir) || $deleted;
    }

    echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Temporary file deleted' : 'No temporary file found']);
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid action']);
