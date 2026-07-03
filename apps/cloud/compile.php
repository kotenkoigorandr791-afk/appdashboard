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
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    $redirect = $mergeStderr ? ' 2>&1' : '';
    $cmd = 'bash -c ' . escapeshellarg('cd ' . escapeshellarg($workingDir) . ' && ' . $command . $redirect);

    $process = proc_open($cmd, $descriptorspec, $pipes, $workingDir, null);

    if (!is_resource($process)) {
        return ['success' => false, 'output' => '', 'error' => 'Failed to execute command'];
    }

    if ($input !== null && $input !== '') {
        fwrite($pipes[0], $input . "\n");
    }
    fclose($pipes[0]);

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $output = '';
    $startTime = microtime(true);
    $exitCode = -1;
    $timedOut = false;

    while (true) {
        $elapsed = microtime(true) - $startTime;
        if ($elapsed > $timeout) {
            $timedOut = true;
            break;
        }
        $status = proc_get_status($process);
        $chunk = fread($pipes[1], 8192);
        if ($chunk !== false && $chunk !== '') {
            $output .= $chunk;
        }
        if (!$status['running']) {
            $exitCode = (int)$status['exitcode'];
            while (($chunk = fread($pipes[1], 8192)) !== false && $chunk !== '') {
                $output .= $chunk;
            }
            break;
        }
        usleep(50000);
    }

    if ($timedOut) {
        proc_terminate($process);
        usleep(200000);
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $error = $timedOut ? 'Process timed out after ' . $timeout . ' seconds' : '';

    return [
        'success' => !$timedOut && $exitCode === 0,
        'output' => trim($output),
        'error' => trim($error),
        'exitCode' => $exitCode
    ];
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
                if (file_put_contents($tempFilePath, $fileContent) === false) {
                    return ['success' => false, 'output' => '', 'error' => 'Failed to write file to temp directory'];
                }
                
                $compileResult = executeCommand('"' . JAVAC_PATH . '" "' . $fileName . '"', TEMP_DIR);
                if (!$compileResult['success']) {
                    return $compileResult;
                }

                $runResult = executeCommand('"' . JAVA_PATH . '" "' . $baseName . '"', TEMP_DIR, $input);
                return $runResult;

            case 'c':
            case 'cpp':
                return compileCppOnline($fileContent, $input);

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
                    $runResult = executeCommand('"' . PYTHON_PATH . '" "' . $absolutePath . '"', $workingDir, $input);
                    
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

function compileCppOnline($code, $input = null) {
    $api_base = 'https://api.paiza.io';
    $create_endpoint = $api_base . '/runners/create';
    $get_status_endpoint = $api_base . '/runners/get_status';
    $get_details_endpoint = $api_base . '/runners/get_details';
    $api_key = 'guest';

    $post_data = [
        'source_code' => $code,
        'language' => 'cpp',
        'input' => $input ?? '',
        'api_key' => $api_key,
        'longpoll' => 'true',
        'longpoll_timeout' => '30'
    ];

    $ch = curl_init($create_endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $create_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || !$create_response) {
        return [
            'success' => false,
            'output' => '',
            'error' => "Failed to create runner: HTTP $http_code" . ($curl_error ? " - $curl_error" : "")
        ];
    }

    $create_data = json_decode($create_response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'output' => '',
            'error' => "Invalid JSON response from API: " . json_last_error_msg()
        ];
    }

    if (isset($create_data['error'])) {
        return [
            'success' => false,
            'output' => '',
            'error' => "API Error: " . $create_data['error']
        ];
    }

    $runner_id = $create_data['id'] ?? null;
    if (!$runner_id) {
        return [
            'success' => false,
            'output' => '',
            'error' => "Failed to get runner ID from response"
        ];
    }

    // Если API поддерживает longpoll, результат может быть сразу готов
    if (isset($create_data['stdout']) || isset($create_data['stderr']) || isset($create_data['build_stderr'])) {
        return formatPaizaResult($create_data);
    }

    $attempts = 0;
    $max_attempts = 30;
    $status = $create_data['status'] ?? 'running';

    while ($status === 'running' && $attempts < $max_attempts) {
        sleep(1);
        $status_url = $get_status_endpoint . '?id=' . urlencode($runner_id) . '&api_key=' . $api_key;
        
        $ch = curl_init($status_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $status_response = curl_exec($ch);
        $status_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status_http !== 200 || !$status_response) {
            break;
        }

        $status_data = json_decode($status_response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $status = $status_data['status'] ?? 'unknown';
        }
        $attempts++;

        if ($status === 'completed') {
            break;
        }
    }

    if ($attempts >= $max_attempts && $status !== 'completed') {
        return [
            'success' => false,
            'output' => '',
            'error' => 'Execution timeout (30 seconds)'
        ];
    }

    $details_url = $get_details_endpoint . '?id=' . urlencode($runner_id) . '&api_key=' . $api_key;
    
    $ch = curl_init($details_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $details_response = curl_exec($ch);
    $details_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($details_http !== 200 || !$details_response) {
        return [
            'success' => false,
            'output' => '',
            'error' => "Failed to get details: HTTP $details_http"
        ];
    }

    $result = json_decode($details_response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'output' => '',
            'error' => "Invalid JSON in details response: " . json_last_error_msg()
        ];
    }

    return formatPaizaResult($result);
}

function formatPaizaResult($result) {
    $output = $result['stdout'] ?? '';
    $build_error = $result['build_stderr'] ?? '';
    $runtime_error = $result['stderr'] ?? '';
    $build_result = $result['build_result'] ?? $result['result'] ?? 'unknown';
    
    $has_output = !empty($output);
    $has_error = !empty($build_error) || !empty($runtime_error);
    $success = ($build_result === 'success') || ($has_output && !$has_error);

    $terminalOutput = "";
    
    if (!empty($output)) {
        $terminalOutput .= "OUTPUT:\n" . trim($output) . "\n\n";
    }
    
    if (!empty($build_error)) {
        $terminalOutput .= "COMPILATION ERRORS:\n" . trim($build_error) . "\n\n";
    }
    
    if (!empty($runtime_error)) {
        $terminalOutput .= "RUNTIME ERRORS:\n" . trim($runtime_error) . "\n\n";
    }
    
    if (empty($output) && empty($build_error) && empty($runtime_error)) {
        $terminalOutput .= "Program executed successfully (no output)\n\n";
    }
    
    $terminalOutput .= "Status: " . $build_result . "\n";
    $terminalOutput .= "Execution Time: " . ($result['time'] ?? 'unknown') . "s\n";
    $terminalOutput .= "Memory: " . ($result['memory'] ?? 'unknown') . " bytes";

    return [
        'success' => $success,
        'output' => $terminalOutput,
        'error' => ''
    ];
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
