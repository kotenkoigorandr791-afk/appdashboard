<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
// tts_api.php - Text to Speech API
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
    $candidates = ['/usr/bin/python3', 'python3', 'python'];
    foreach ($candidates as $bin) {
        if (strpos($bin, '/') !== false && is_executable($bin)) return $bin;
        if (strpos($bin, ':\\') !== false && file_exists($bin)) return $bin;
        $path = trim((string)shell_exec('which ' . escapeshellarg($bin) . ' 2>/dev/null'));
        if ($path !== '') return $path;
    }
    return 'python3';
}
function tts_split_root_path(string $relativePath): array {
    $path = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
    $map = ['.archive' => 'archive', '.temp_files' => 'temp', '.documents' => 'documents', '.photos' => 'photos', '.pdf' => 'pdf', '.trash' => 'trash', '.clientsync' => 'clientsync'];
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
        [$rootKey, $rootRelativePath] = $root === 'main' ? tts_split_root_path($relativePath) : [$root, $relativePath];
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

const TTS_OUTPUT_DIR = __DIR__ . '/tts_output';
@mkdir(TTS_OUTPUT_DIR, 0777, true);
@mkdir(TTS_OUTPUT_DIR . '/.cache', 0777, true);
@chmod(TTS_OUTPUT_DIR, 0777);
@chmod(TTS_OUTPUT_DIR . '/.cache', 0777);

require_once __DIR__ . '/../../lib/app_db.php';
require_once dirname(__DIR__, 2) . '/storage_helper.php';
require_once __DIR__ . '/includes/cloud_groups.php';

if (!function_exists('getDB')) {
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = app_main_db();
            $db->enableExceptions(true);
            
        } catch (Exception $e) {
            cloud_safe_error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $db;
}
}

function get_session_email() {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/includes/session.php';
        cloud_session_start();
    }
    return $_SESSION['email'] ?? $_SESSION['user']['email'] ?? null;
}

function invalidate_file_tree($email) {
    $db = getDB();
    if (!$db || !$email) return;
    try {
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
    } catch (Exception $e) {
        cloud_safe_error_log("invalidate_file_tree failed: " . $e->getMessage());
    }
}

function tts_safe_message(string $message): string {
    $message = preg_replace('/\b[A-Za-z]:[\\\\\\/][^\s"\'<>)]*/', '[hidden-path]', $message);
    return preg_replace('/(^|[\s(])\/(?:[^\/\s"\'<>)]*\/)*[^\/\s"\'<>)]*/', '$1[hidden-path]', $message);
}

function tts_python_preflight(string $pythonPath): array {
    $libPath = realpath(__DIR__ . '/../../lib') ?: (__DIR__ . '/../../lib');
    $code = "import sys\nsys.path.insert(0, sys.argv[1])\nimport gtts\nimport psycopg2\n";
    $output = [];
    $rc = 0;
    exec(escapeshellarg($pythonPath) . ' -c ' . escapeshellarg($code) . ' ' . escapeshellarg($libPath) . ' 2>&1', $output, $rc);
    if ($rc === 0) return [true, ''];
    return [false, tts_safe_message(trim(implode("\n", $output)) ?: 'Python TTS dependencies unavailable')];
}

function ensure_tts_tables() {
    $db = getDB();
    if (!$db) return false;

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS cloud_tts_jobs (
            progress_id TEXT PRIMARY KEY,
            user_name TEXT NOT NULL DEFAULT '',
            text_path TEXT NOT NULL DEFAULT '',
            language TEXT NOT NULL DEFAULT 'en',
            speed REAL NOT NULL DEFAULT 1.0,
            status TEXT NOT NULL DEFAULT 'processing',
            percentage INTEGER NOT NULL DEFAULT 0,
            message TEXT NOT NULL DEFAULT '',
            audio_url TEXT NOT NULL DEFAULT '',
            error_text TEXT NOT NULL DEFAULT '',
            started_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
            updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )");

        $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_tts_jobs_status ON cloud_tts_jobs(status, updated_at DESC)");
        return true;
    } catch (Exception $e) {
        cloud_safe_error_log('ensure_tts_tables: ' . $e->getMessage());
        return false;
    }
}

ensure_tts_tables();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get_progress') {
        $progressId = $_GET['progress_id'] ?? '';
        if (!$progressId) {
            echo json_encode(['success' => false, 'error' => 'No progress_id provided']);
            exit;
        }

        $db = getDB();
        if (!$db) {
            echo json_encode(['success' => false, 'error' => 'Database unavailable']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM cloud_tts_jobs WHERE progress_id = ? LIMIT 1");
        $stmt->bindValue(1, $progressId, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['progress' => 0, 'message' => '', 'status' => 'not_found']);
            exit;
        }

        $audioData = null;
        if (($_GET['include_audio_data'] ?? '') === '1' && $row['status'] === 'completed' && $row['audio_url']) {
            $audioFilename = basename($row['audio_url']);
            $audioPath = TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . $audioFilename;
            if (file_exists($audioPath)) {
                $audioData = base64_encode(file_get_contents($audioPath));
            }
        }

        echo json_encode([
            'progress' => (int)$row['percentage'],
            'message' => tts_safe_message((string)$row['message']),
            'status' => $row['status'],
            'audio_url' => $row['audio_url'],
            'audio_data' => $audioData,
            'error' => tts_safe_message((string)$row['error_text'])
        ]);
        exit;
    }

    if ($action === 'download_audio') {
        $progressId = $_GET['progress_id'] ?? '';
        if (!$progressId) {
            http_response_code(400);
            echo 'No progress_id provided';
            exit;
        }

        $db = getDB();
        if (!$db) {
            http_response_code(500);
            echo 'Database unavailable';
            exit;
        }

        $stmt = $db->prepare("SELECT audio_url FROM cloud_tts_jobs WHERE progress_id = ? AND status = 'completed' LIMIT 1");
        $stmt->bindValue(1, $progressId, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['audio_url']) {
            http_response_code(404);
            echo 'Audio file not found';
            exit;
        }

        $audioFilename = basename($row['audio_url']);
        $audioPath = TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . $audioFilename;

        if (!file_exists($audioPath)) {
            http_response_code(404);
            echo 'Audio file not found on disk';
            exit;
        }

        header('Content-Type: audio/mpeg');
        header('Content-Disposition: attachment; filename="' . $audioFilename . '"');
        header('Content-Length: ' . filesize($audioPath));
        readfile($audioPath);
        exit;
    }

    if ($action === 'cleanup_audio') {
        $progressId = $_GET['progress_id'] ?? '';
        if (!$progressId) {
            echo json_encode(['success' => false, 'error' => 'No progress_id provided']);
            exit;
        }

        $db = getDB();
        if (!$db) {
            echo json_encode(['success' => false, 'error' => 'Database unavailable']);
            exit;
        }

        $stmt = $db->prepare("SELECT audio_url FROM cloud_tts_jobs WHERE progress_id = ? LIMIT 1");
        $stmt->bindValue(1, $progressId, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['audio_url']) {
            $audioFilename = basename($row['audio_url']);
            $audioPath = TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . $audioFilename;

            // Delete audio file
            if (file_exists($audioPath)) {
                @unlink($audioPath);
            }

            // Delete temp text file
            $tempTextFile = TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . $progressId . '.txt';
            if (file_exists($tempTextFile)) {
                @unlink($tempTextFile);
            }

            // Delete log file
            $logFile = TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . $progressId . '.log';
            if (file_exists($logFile)) {
                @unlink($logFile);
            }

            // Delete database record
            $deleteStmt = $db->prepare("DELETE FROM cloud_tts_jobs WHERE progress_id = ?");
            $deleteStmt->bindValue(1, $progressId, PDO::PARAM_STR);
            $deleteStmt->execute();
        }

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'generate_tts') {
    $text = $_POST['text'] ?? '';
    $language = $_POST['language'] ?? 'en';
    $speed = floatval($_POST['speed'] ?? 1.0);
    $textPath = $_POST['text_path'] ?? '';
    $user = $_POST['user'] ?? 'default';

    if (!$text) {
        echo json_encode(['success' => false, 'error' => 'No text provided']);
        exit;
    }

    $pythonPath = python_bin();
    $pythonScript = app_path('tts_generate.py');

    if (!file_exists($pythonScript)) {
        echo json_encode(['success' => false, 'error' => 'TTS script not found']);
        exit;
    }

    [$pythonReady, $pythonError] = tts_python_preflight($pythonPath);
    if (!$pythonReady) {
        echo json_encode(['success' => false, 'error' => $pythonError]);
        exit;
    }

    // Cleanup old temporary files (older than 1 hour)
    $files = glob(TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . '*');
    $now = time();
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > 3600) {
            @unlink($file);
        }
    }

    $progressId = uniqid();
    $outputFilename = $progressId . '.mp3';
    $outputPath = TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . $outputFilename;

    // Save text to temporary file
    $tempTextFile = TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . $progressId . '.txt';
    if (file_put_contents($tempTextFile, $text) === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to write temporary text file']);
        exit;
    }

    // Insert job into database
    $db = getDB();
    if (!$db) {
        echo json_encode(['success' => false, 'error' => 'Database unavailable']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO cloud_tts_jobs (progress_id, user_name, text_path, language, speed, status, percentage, message) VALUES (?, ?, ?, ?, ?, 'processing', 0, 'Starting...')");
    $stmt->bindValue(1, $progressId, PDO::PARAM_STR);
    $stmt->bindValue(2, $user, PDO::PARAM_STR);
    $stmt->bindValue(3, $textPath, PDO::PARAM_STR);
    $stmt->bindValue(4, $language, PDO::PARAM_STR);
    $stmt->bindValue(5, $speed, PDO::PARAM_STR);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Failed to create job record']);
        exit;
    }

    // Use absolute paths for Windows
    $tempTextFileAbs = realpath($tempTextFile);
    $outputPathAbs = realpath(TTS_OUTPUT_DIR) . DIRECTORY_SEPARATOR . $outputFilename;

    $command = escapeshellarg($pythonPath) . ' ' .
               escapeshellarg($pythonScript) . ' ' .
               escapeshellarg($tempTextFileAbs) . ' ' .
               escapeshellarg($outputPathAbs) . ' ' .
               escapeshellarg($language) . ' ' .
               escapeshellarg($speed) . ' ' .
               escapeshellarg($progressId);

    $logFile = TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . $progressId . '.log';
    $cacheDir = TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . '.cache';

    if (substr(php_uname(), 0, 7) === "Windows") {
        $runCommand = 'start "" /B cmd /C ' . escapeshellarg($command . ' > ' . escapeshellarg($logFile) . ' 2>&1');
        pclose(popen($runCommand, "r"));
    } else {
        $env = 'HOME=' . escapeshellarg($cacheDir) . ' XDG_CACHE_HOME=' . escapeshellarg($cacheDir) . ' MPLCONFIGDIR=' . escapeshellarg($cacheDir) . ' PYTHONUNBUFFERED=1 ';
        exec($env . $command . ' > ' . escapeshellarg($logFile) . ' 2>&1 &');
    }

    echo json_encode([
        'success' => true,
        'message' => 'TTS generation started',
        'progress_id' => $progressId
    ]);
    exit;
}

if ($action === 'save_audio_to_server') {
    $progressId = $_POST['progress_id'] ?? '';
    $textPath = $_POST['text_path'] ?? '';
    $user = $_POST['user'] ?? 'default';
    $root = $_POST['root'] ?? 'main';

    if (!$progressId || !$textPath) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        exit;
    }

    $db = getDB();
    if (!$db) {
        echo json_encode(['success' => false, 'error' => 'Database unavailable']);
        exit;
    }

    // Get audio URL from database
    $stmt = $db->prepare("SELECT audio_url FROM cloud_tts_jobs WHERE progress_id = ? AND status = 'completed' LIMIT 1");
    $stmt->bindValue(1, $progressId, PDO::PARAM_STR);
    $result = $stmt->execute();
    $row = $result->fetch(PDO::FETCH_ASSOC);

    if (!$row || !$row['audio_url']) {
        echo json_encode(['success' => false, 'error' => 'Audio file not found in database']);
        exit;
    }

    // Extract filename from audio URL
    $audioFilename = basename($row['audio_url']);
    $sourceAudioPath = TTS_OUTPUT_DIR . DIRECTORY_SEPARATOR . $audioFilename;

    if (!file_exists($sourceAudioPath)) {
        echo json_encode(['success' => false, 'error' => 'Audio file not found on disk']);
        exit;
    }

    // Determine target directory (same as text file)
    $resolvedTextPath = resolve_user_file_path($user, $textPath, $root);
    if (!$resolvedTextPath || !file_exists($resolvedTextPath)) {
        echo json_encode(['success' => false, 'error' => 'Text file not found']);
        exit;
    }
    if (str_starts_with((string)$root, 'group:')) {
        $rootRelativeTextPath = appdata_normalize_relpath($textPath);
    } else {
        [$targetRootKey, $rootRelativeTextPath] = (string)$root === 'main' ? tts_split_root_path($textPath) : [(string)$root, appdata_root_relative_path((string)$root, $textPath)];
    }
    $textDir = dirname($rootRelativeTextPath);
    $textBasename = pathinfo($rootRelativeTextPath, PATHINFO_FILENAME);
    $targetDir = realpath(dirname($resolvedTextPath));

    if (!$targetDir) {
        echo json_encode(['success' => false, 'error' => 'Target directory not found']);
        exit;
    }

    // Create audio filename based on text file name
    $targetFilename = $textBasename . '.mp3';
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $targetFilename;

    // Copy audio file to target location
    if (copy($sourceAudioPath, $targetPath)) {
        $relativePath = $textDir === '.' || $textDir === ''
            ? $targetFilename
            : str_replace(DIRECTORY_SEPARATOR, '/', trim($textDir, '/\\') . DIRECTORY_SEPARATOR . $targetFilename);

        // Invalidate file tree cache so the new file appears in the tree
        $email = get_session_email();
        if ($email) {
            invalidate_file_tree($email);
        }

        echo json_encode([
            'success' => true,
            'saved_path' => $relativePath,
            'message' => 'Audio saved successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to copy audio file']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
