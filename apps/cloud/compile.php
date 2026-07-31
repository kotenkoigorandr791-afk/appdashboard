<?php

// Очищаем буфер вывода в самом начале
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
// compile.php — компиляция и выполнение кода (только для авторизованных пользователей с валидной сессией)
if (!defined('CLOUD_COMPILE_AS_LIB')) {
header('Content-Type: application/json; charset=utf-8');
}

// Проверяем, не был ли уже отправлен вывод
if (headers_sent()) {
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

// Конфигурация - абсолютные пути к компиляторам
define('GCC_PATH', 'gcc');
define('GPP_PATH', 'g++');
define('JAVAC_PATH', 'javac');
define('JAVA_PATH', 'java');
define('PYTHON_PATH', 'python3');
define('RUNMAT_PATH', '/usr/local/bin/runmat');

const MAX_EXECUTION_TIME = 30;
const MATLAB_EXECUTION_TIME = 120;
const TEMP_DIR = __DIR__ . '/temp';
const STORAGE_DIR = __DIR__ . '/storage';

// Создаем временную папку если не существует
if (!is_dir(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0775, true);
}

// Создаем storage папку если не существует
if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0775, true);
}

// Вспомогательные функции для отслеживания изменений файлов
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
    
    // Новые файлы
    foreach ($after as $file => $info) {
        if (!isset($before[$file])) {
            $changes[] = [
                'action' => 'created',
                'file' => $file,
                'path' => str_replace('\\', '/', $relativeBase . $file)
            ];
        }
    }
    
    // Удаленные файлы
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
                $workingDir = $originalPath !== null ? dirname((string)$originalPath) : TEMP_DIR;
                if (!is_dir($workingDir) && !mkdir($workingDir, 0775, true)) {
                    return ['success' => false, 'output' => '', 'error' => 'Failed to create directory for ' . compile_safe_name((string)$originalPath)];
                }
                $beforeFiles = getDirectorySnapshotWithFolders($workingDir);
                $tempFilePath = $workingDir . '/' . $fileName;
                if (file_put_contents($tempFilePath, $fileContent) === false) {
                    return ['success' => false, 'output' => '', 'error' => 'Failed to write file to temp directory'];
                }
                $compiler = $language === 'c' ? GCC_PATH : GPP_PATH;
                $standard = $language === 'c' ? '-std=c17' : '-std=c++20';
                $program = '.cloud_compile_program_' . bin2hex(random_bytes(4));
                $compileResult = executeCommand(escapeshellarg($compiler) . ' ' . $standard . ' -O2 -pipe ' . escapeshellarg($fileName) . ' -o ' . escapeshellarg($program), $workingDir);
                if (!$compileResult['success']) {
                    return $compileResult;
                }
                $runResult = executeCommand('./' . escapeshellarg($program), $workingDir, $input);
                if ($originalPath !== null) {
                    @unlink($workingDir . '/' . $program);
                    $runResult['fileChanges'] = detectFileAndFolderChanges($beforeFiles, getDirectorySnapshotWithFolders($workingDir), $workingDir);
                }
                return $runResult;

            case 'py':
                // Для Python используем путь из запроса
                if ($originalPath !== null) {
                    $absolutePath = (string)$originalPath;
                                    
                    $workingDir = dirname($absolutePath);
                    
                    // Проверяем существование директории
                    if (!is_dir($workingDir)) {
                        // Пытаемся создать директорию
                        if (!mkdir($workingDir, 0775, true)) {
                            return ['success' => false, 'output' => '', 'error' => 'Failed to create directory for ' . compile_safe_name($absolutePath)];
                        }
                    }
                    
                    // Проверяем права на запись
                    if (!is_writable($workingDir)) {
                        return ['success' => false, 'output' => '', 'error' => 'Target directory is not writable for ' . compile_safe_name($absolutePath)];
                    }
                    
                    // Сохраняем состояние ДО выполнения (включая папки)
                    $beforeFiles = getDirectorySnapshotWithFolders($workingDir);
                    
                    // Записываем содержимое в файл
                    if (file_put_contents($absolutePath, $fileContent) === false) {
                        return ['success' => false, 'output' => '', 'error' => 'Failed to write Python file: ' . compile_safe_name($absolutePath)];
                    }
                    
                    // Проверяем, что файл создался
                    if (!file_exists($absolutePath)) {
                        return ['success' => false, 'output' => '', 'error' => 'Python file not found after write: ' . compile_safe_name($absolutePath)];
                    }
                    
                    // Проверяем, что файл не пустой
                    if (filesize($absolutePath) === 0) {
                        return ['success' => false, 'output' => '', 'error' => 'Python file is empty after write: ' . compile_safe_name($absolutePath)];
                    }
                    
                    // Запускаем Python файл
                    $runResult = executeCommand(escapeshellarg(PYTHON_PATH) . ' ' . escapeshellarg(basename($absolutePath)), $workingDir, $input);
                    
                    // Сохраняем состояние ПОСЛЕ выполнения (включая папки)
                    $afterFiles = getDirectorySnapshotWithFolders($workingDir);
                    
                    // Добавляем информацию о файлах и папках к результату
                    $fileChanges = detectFileAndFolderChanges($beforeFiles, $afterFiles, $workingDir);
                    if (!empty($fileChanges)) {
                        $runResult['fileChanges'] = $fileChanges;
                    }
                    
                    // Добавляем информацию о файле для отладки
                    if (!empty($runResult['error'])) {
                        $runResult['error'] = "File: " . basename($absolutePath) . "\n" . $runResult['error'];
                    }
                    
                    return $runResult;
                } else {
                    // Если путь не указан, используем временную папку
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

// Новая функция для получения снимка директории с учетом папок
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

// Новая функция для обнаружения изменений файлов и папок
function detectFileAndFolderChanges($before, $after, $dir) {
    $changes = [];
    $relativeBase = str_replace(STORAGE_DIR . '/', '', $dir . '/');
    
    // Новые элементы (файлы и папки)
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
    
    // Удаленные элементы
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
    
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
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

// Основная логика обработки запроса
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
        
        // Для Python проверяем существование полного пути
        if ($language === 'py') {
            $fullPath = resolve_cloud_compile_path($filePath, (string)$rootKey);
            $dirPath = dirname($fullPath);
            
            if (!is_dir($dirPath)) {
                // Пытаемся создать директорию
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

        // Очищаем буфер и выводим JSON
        ob_clean();
        
        $response = [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error']
        ];
        
        // Добавляем информацию об изменениях файлов
        if (isset($result['fileChanges']) && !empty($result['fileChanges'])) {
            $response['fileChanges'] = $result['fileChanges'];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('Invalid action or request method');
    }
} catch (Exception $e) {
    // Очищаем буфер и выводим ошибку в JSON
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => compile_safe_message($e->getMessage())
    ], JSON_UNESCAPED_UNICODE);
}

// Завершаем буферизацию
ob_end_flush();
}
?>
