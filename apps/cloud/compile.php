<?php

// Clean output buffer at the very beginning
if (!defined('CLOUD_COMPILE_AS_LIB')) {
if (ob_get_level()) ob_clean();
ob_start();
}

error_reporting(0);
ini_set('display_errors', 0);

if (!defined('CLOUD_COMPILE_AS_LIB')) {
session_start();
}
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'smart_log.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage_helper.php';
require_once __DIR__ . '/includes/cloud_sandbox.php';
require_once __DIR__ . '/includes/ai_code_security.php';
smart_log_bootstrap('cloud');
// compile.php — code compilation and execution (only for authenticated users with a valid session)
if (!defined('CLOUD_COMPILE_AS_LIB')) {
header('Content-Type: application/json; charset=utf-8');
}

if (!defined('CLOUD_COMPILE_AS_LIB') && headers_sent()) {
    die(json_encode(['success' => false, 'error' => 'Headers already sent']));
}

if (!defined('CLOUD_COMPILE_AS_LIB') && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!defined('CLOUD_COMPILE_AS_LIB')) {
if (!isset($_SESSION['user']['email'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Not authenticated']));
}
$sessRow = getUserFromActiveSessions(session_id());
if (!$sessRow || ($sessRow['email'] ?? '') !== $_SESSION['user']['email']) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Invalid session']));
}
}

// Configuration — absolute paths to compilers
if (!defined('GCC_PATH')) define('GCC_PATH', 'gcc');
if (!defined('GPP_PATH')) define('GPP_PATH', 'g++');
if (!defined('JAVAC_PATH')) define('JAVAC_PATH', 'javac');
if (!defined('JAVA_PATH')) define('JAVA_PATH', 'java');
if (!defined('PYTHON_PATH')) define('PYTHON_PATH', 'python3');
if (!defined('RUNMAT_PATH')) define('RUNMAT_PATH', '/usr/local/bin/runmat');

if (!defined('MAX_EXECUTION_TIME')) define('MAX_EXECUTION_TIME', 30);
if (!defined('MATLAB_EXECUTION_TIME')) define('MATLAB_EXECUTION_TIME', 120);
if (!defined('TEMP_DIR')) define('TEMP_DIR', __DIR__ . '/temp');
if (!defined('STORAGE_DIR')) define('STORAGE_DIR', __DIR__ . '/storage');

// Create temp directory if it doesn't exist
if (!is_dir(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0775, true);
}

// Create storage directory if it doesn't exist
if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0775, true);
}

// Helper functions for tracking file changes
function getDirectorySnapshot($dir) {
    $snapshot = [];
    if (!is_dir($dir)) return $snapshot;
    
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $snapshot[basename($file)] = [
                'mtime' => filemtime($file),
                'size' => filesize($file)
            ];
        }
    }
    return $snapshot;
}

function detectFileChanges($before, $after, $dir) {
    $changes = [];
    $relativeBase = str_replace(STORAGE_DIR . '/', '', $dir . '/');
    
    // New files
    foreach ($after as $file => $info) {
        if (!isset($before[$file])) {
            $changes[] = [
                'action' => 'created',
                'file' => $file,
                'path' => str_replace('\\', '/', $relativeBase . $file)
            ];
        }
    }
    
    // Deleted files
    foreach ($before as $file => $info) {
        if (!isset($after[$file])) {
            $changes[] = [
                'action' => 'deleted',
                'file' => $file,
                'path' => str_replace('\\', '/', $relativeBase . $file)
            ];
        }
    }
    
    return $changes;
}

function executeCommand($command, $workingDir, $input = null, $timeout = MAX_EXECUTION_TIME, $mergeStderr = true) {
    try {
        return cloud_sandbox_exec($workingDir, $command, $input, $timeout, $mergeStderr);
    } catch (Throwable $e) {
        return ['success' => false, 'output' => '', 'error' => $e->getMessage(), 'exitCode' => -1];
    }
}

function compileAndRun($language, $fileContent, $fileName, $input = null, $originalPath = null) {
    cleanupTempDir(TEMP_DIR);
    
    if (!is_dir(TEMP_DIR)) {
        mkdir(TEMP_DIR, 0775, true);
    }

    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
    $tempFilePath = TEMP_DIR . '/' . $fileName;

    try {
        switch ($language) {
            case 'java':
                $workingDir = $originalPath !== null ? dirname((string)$originalPath) : TEMP_DIR;
                if (!is_dir($workingDir) && !mkdir($workingDir, 0775, true)) {
                    return ['success' => false, 'output' => '', 'error' => 'Failed to create directory for ' . compile_safe_name((string)$originalPath)];
                }
                $beforeFiles = getDirectorySnapshotWithFolders($workingDir);
                if (preg_match('/\bpublic\s+class\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $fileContent, $m)) {
                    $baseName = $m[1];
                    $fileName = $baseName . '.java';
                }
                $tempFilePath = $workingDir . '/' . $fileName;
                if (file_put_contents($tempFilePath, $fileContent) === false) {
                    return ['success' => false, 'output' => '', 'error' => 'Failed to write file to temp directory'];
                }
                
                $compileResult = executeCommand('"' . JAVAC_PATH . '" "' . $fileName . '"', $workingDir);
                if (!$compileResult['success']) {
                    if ($originalPath !== null && basename((string)$originalPath) !== $fileName) @unlink($tempFilePath);
                    return $compileResult;
                }

                $runResult = executeCommand('"' . JAVA_PATH . '" "' . $baseName . '"', $workingDir, $input);
                if ($originalPath !== null) {
                    if (basename((string)$originalPath) !== $fileName) @unlink($tempFilePath);
                    foreach (glob($workingDir . '/' . $baseName . '*.class') ?: [] as $classFile) @unlink($classFile);
                    $runResult['fileChanges'] = detectFileAndFolderChanges($beforeFiles, getDirectorySnapshotWithFolders($workingDir), $workingDir);
                }
                return $runResult;

            case 'c':
            case 'cpp':
                $sourceDir = TEMP_DIR . '/c_' . bin2hex(random_bytes(4));
                if (!mkdir($sourceDir, 0775, true) && !is_dir($sourceDir)) {
                    return ['success' => false, 'output' => '', 'error' => 'Failed to create compile temp directory'];
                }
                $userDir = $originalPath !== null ? dirname((string)$originalPath) : null;
                $tempRoot = realpath(TEMP_DIR) ?: TEMP_DIR;
                $trackUser = false;
                if ($userDir && is_dir($userDir)) {
                    $userReal = realpath($userDir) ?: $userDir;
                    $trackUser = $userReal !== $tempRoot && !str_starts_with($userReal, rtrim($tempRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
                }
                $beforeFiles = $trackUser ? getDirectorySnapshotWithFolders($userDir) : [];
                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$fileName)) ?: ($language === 'c' ? 'main.c' : 'main.cpp');
                if (!preg_match('/\.(c|cpp|cc|cxx)$/i', $safeName)) {
                    $safeName .= $language === 'c' ? '.c' : '.cpp';
                }
                $tempFilePath = $sourceDir . '/' . $safeName;
                if (file_put_contents($tempFilePath, $fileContent) === false) {
                    return ['success' => false, 'output' => '', 'error' => 'Failed to write file to temp directory'];
                }
                if ($trackUser) {
                    @file_put_contents((string)$originalPath, $fileContent);
                }
                $compiler = $language === 'c' ? GCC_PATH : GPP_PATH;
                $standard = $language === 'c' ? '-std=c17' : '-std=c++20';
                $program = 'program_' . bin2hex(random_bytes(4));
                $compileResult = executeCommand(
                    escapeshellarg($compiler) . ' ' . $standard . ' -O2 -pipe ' . escapeshellarg($safeName) . ' -o ' . escapeshellarg($program),
                    $sourceDir
                );
                if (!$compileResult['success']) {
                    cleanupTempDir($sourceDir);
                    return $compileResult;
                }
                $runResult = executeCommand(escapeshellarg('./' . $program), $sourceDir, $input);
                if ($trackUser) {
                    $changes = detectFileAndFolderChanges($beforeFiles, getDirectorySnapshotWithFolders($userDir), $userDir);
                    if (!empty($changes)) $runResult['fileChanges'] = $changes;
                }
                cleanupTempDir($sourceDir);
                return $runResult;

            case 'py':
                // For Python, use the path from the request
                if ($originalPath !== null) {
                    $absolutePath = (string)$originalPath;
                                    
                    $workingDir = dirname($absolutePath);
                    
                    // Check if directory exists
                    if (!is_dir($workingDir)) {
                        // Try to create the directory
                        if (!mkdir($workingDir, 0775, true)) {
                            return ['success' => false, 'output' => '', 'error' => 'Failed to create directory for ' . compile_safe_name($absolutePath)];
                        }
                    }
                    
                    // Check write permissions
                    if (!is_writable($workingDir)) {
                        return ['success' => false, 'output' => '', 'error' => 'Target directory is not writable for ' . compile_safe_name($absolutePath)];
                    }
                    
                    // Save state BEFORE execution (including folders)
                    $beforeFiles = getDirectorySnapshotWithFolders($workingDir);
                    
                    // Write content to file
                    if (file_put_contents($absolutePath, $fileContent) === false) {
                        return ['success' => false, 'output' => '', 'error' => 'Failed to write Python file: ' . compile_safe_name($absolutePath)];
                    }
                    
                    // Verify the file was created
                    if (!file_exists($absolutePath)) {
                        return ['success' => false, 'output' => '', 'error' => 'Python file not found after write: ' . compile_safe_name($absolutePath)];
                    }
                    
                    // Verify the file is not empty
                    if (filesize($absolutePath) === 0) {
                        return ['success' => false, 'output' => '', 'error' => 'Python file is empty after write: ' . compile_safe_name($absolutePath)];
                    }
                    
                    // Run the Python file
                    $runResult = executeCommand(escapeshellarg(PYTHON_PATH) . ' ' . escapeshellarg(basename($absolutePath)), $workingDir, $input);
                    
                    // Save state AFTER execution (including folders)
                    $afterFiles = getDirectorySnapshotWithFolders($workingDir);
                    
                    // Add file and folder info to the result
                    $fileChanges = detectFileAndFolderChanges($beforeFiles, $afterFiles, $workingDir);
                    if (!empty($fileChanges)) {
                        $runResult['fileChanges'] = $fileChanges;
                    }
                    
                    // Add file info for debugging
                    if (!empty($runResult['error'])) {
                        $runResult['error'] = "File: " . basename($absolutePath) . "\n" . $runResult['error'];
                    }
                    
                    return $runResult;
                } else {
                    // If no path specified, use the temp directory
                    if (file_put_contents($tempFilePath, $fileContent) === false) {
                        return ['success' => false, 'output' => '', 'error' => 'Failed to write file to temp directory'];
                    }
                    
                    $beforeFiles = getDirectorySnapshotWithFolders(TEMP_DIR);
                    $runResult = executeCommand('"' . PYTHON_PATH . '" "' . $tempFilePath . '"', TEMP_DIR, $input);
                    $afterFiles = getDirectorySnapshotWithFolders(TEMP_DIR);
                    
                    $fileChanges = detectFileAndFolderChanges($beforeFiles, $afterFiles, TEMP_DIR);
                    if (!empty($fileChanges)) {
                        $runResult['fileChanges'] = $fileChanges;
                    }
                    
                    return $runResult;
                }
                
            case 'm':  // MATLAB/RunMat
                if (file_put_contents($tempFilePath, $fileContent) === false) {
                    return ['success' => false, 'output' => '', 'error' => 'Failed to write file to temp directory'];
                }
                $matlabEnv = implode(' ', [
                    'XDG_RUNTIME_DIR=/tmp',
                    'DISPLAY=',
                    'LIBGL_ALWAYS_SOFTWARE=1',
                    'GALLIUM_DRIVER=softpipe',
                    'RUST_LOG=wgpu=off,naga=off,wgpu_hal=off,runmat_accelerate=off,runmat=off',
                    'VK_ICD_FILENAMES=/dev/null',
                    'WAYLAND_DISPLAY=',
                ]);
                $runResult = executeCommand(
                    $matlabEnv . ' ' . escapeshellarg(RUNMAT_PATH) . ' ' . escapeshellarg($tempFilePath),
                    TEMP_DIR, $input, MATLAB_EXECUTION_TIME, false
                );
                return $runResult;
                
            default:
                return ['success' => false, 'output' => '', 'error' => "Unsupported language: $language"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'output' => '', 'error' => $e->getMessage()];
    }
}

// New function to get a directory snapshot including subdirectories
function getDirectorySnapshotWithFolders($dir) {
    $snapshot = [];
    if (!is_dir($dir)) return $snapshot;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $item->getPathname());
        $relativePath = str_replace('\\', '/', $relativePath);
        
        if ($item->isDir()) {
            $snapshot[$relativePath] = [
                'type' => 'dir',
                'mtime' => $item->getMTime(),
                'path' => $relativePath
            ];
        } else {
            $snapshot[$relativePath] = [
                'type' => 'file',
                'mtime' => $item->getMTime(),
                'size' => $item->getSize(),
                'path' => $relativePath
            ];
        }
    }
    return $snapshot;
}

// New function to detect file and folder changes
function detectFileAndFolderChanges($before, $after, $dir) {
    $changes = [];
    $relativeBase = str_replace(STORAGE_DIR . '/', '', $dir . '/');
    
    // New items (files and folders)
    foreach ($after as $item => $info) {
        if (!isset($before[$item])) {
            $type = $info['type'] ?? 'file';
            $icon = $type === 'dir' ? 'dir' : 'file';
            $changes[] = [
                'action' => 'created',
                'type' => $type,
                'file' => basename($item),
                'path' => str_replace('\\', '/', $relativeBase . $item),
                'fullPath' => $item,
                'icon' => $icon
            ];
        }
    }
    
    // Deleted items
    foreach ($before as $item => $info) {
        if (!isset($after[$item])) {
            $type = $info['type'] ?? 'file';
            $icon = $type === 'dir' ? 'dir' : 'file';
            $changes[] = [
                'action' => 'deleted',
                'type' => $type,
                'file' => basename($item),
                'path' => str_replace('\\', '/', $relativeBase . $item),
                'fullPath' => $item,
                'icon' => $icon
            ];
        }
    }
    
    return $changes;
}

function cleanupTempDir($dir) {
    if (!is_dir($dir)) return;
    $items = @scandir($dir);
    if (!is_array($items)) return;
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path)) {
            cleanupTempDir($path);
            @rmdir($path);
        } elseif (is_file($path) || is_link($path)) {
            @unlink($path);
        }
    }
    $base = realpath(TEMP_DIR) ?: TEMP_DIR;
    $full = realpath($dir) ?: $dir;
    if ($full !== $base && str_starts_with($full, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        @rmdir($dir);
    }
}
function resolve_cloud_compile_path(string $filePath, string $root = 'main'): string {
    $path = ltrim(str_replace('\\', '/', trim($filePath)), '/');
    if (preg_match('#^storage/([^/]+)/(.+)$#', $path, $m)) {
        $user = $m[1];
        $relative = $m[2];
        return appdata_user_full_path('cloud', $user, $root, $relative, true);
    }
    return __DIR__ . '/' . ltrim($filePath, '/');
}
function compile_safe_name(string $path, string $fallback = 'file'): string {
    if (function_exists('cloud_safe_path_label')) {
        return cloud_safe_path_label($path, $fallback);
    }
    $base = basename(str_replace('\\', '/', $path));
    return $base !== '' ? $base : $fallback;
}
function compile_safe_message(string $message): string {
    if (function_exists('cloud_sanitize_paths_in_text')) {
        return cloud_sanitize_paths_in_text($message);
    }
    return $message;
}

// Main request processing logic
if (!defined('CLOUD_COMPILE_AS_LIB')) {
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'compile_run') {
        $language = $_POST['language'] ?? '';
        $fileContent = $_POST['file_content'] ?? '';
        $filePath = $_POST['file_path'] ?? '';
        $rootKey = $_POST['root'] ?? 'main';
        $programInput = $_POST['program_input'] ?? null;

        if (empty($language) || $fileContent === '' || empty($filePath)) {
            throw new Exception('Missing required parameters');
        }

        $supportedLanguages = ['java', 'c', 'cpp', 'py', 'm'];
        if (!in_array($language, $supportedLanguages)) {
            throw new Exception("Language $language is not supported");
        }
        $securityExt = $language === 'py' ? 'py' : $language;
        if (cloud_ai_code_security_lang($securityExt) !== null) {
            $sec = cloud_ai_scan_code_security($fileContent, $securityExt);
            if (!empty($sec['blocked'])) {
                throw new Exception($sec['error'] ?? 'Unsafe code is blocked');
            }
        }

        $fileName = basename($filePath);
        
        // For Python, check if the full path exists
        if ($language === 'py') {
            $fullPath = resolve_cloud_compile_path($filePath, (string)$rootKey);
            $dirPath = dirname($fullPath);
            
            if (!is_dir($dirPath)) {
                // Try to create the directory
                if (!mkdir($dirPath, 0775, true)) {
                    throw new Exception("Cannot create directory for " . compile_safe_name($filePath));
                }
            }
            
            if (!is_writable($dirPath)) {
                throw new Exception("Target directory is not writable for " . compile_safe_name($filePath));
            }
        }
        
        $resolvedFilePath = resolve_cloud_compile_path($filePath, (string)$rootKey);
        $result = compileAndRun($language, $fileContent, $fileName, $programInput, $resolvedFilePath);

        // Clear buffer and output JSON
        ob_clean();
        
        $response = [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error']
        ];
        
        // Add file change information
        if (isset($result['fileChanges']) && !empty($result['fileChanges'])) {
            $response['fileChanges'] = $result['fileChanges'];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('Invalid action or request method');
    }
} catch (Exception $e) {
    // Clear buffer and output error as JSON
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => compile_safe_message($e->getMessage())
    ], JSON_UNESCAPED_UNICODE);
}

// End output buffering
ob_end_flush();
}
?>
