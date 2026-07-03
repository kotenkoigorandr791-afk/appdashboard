<?php
ini_set('display_errors', (string)getenv('APP_DEBUG') === '1' ? '1' : '0');
ini_set('display_startup_errors', (string)getenv('APP_DEBUG') === '1' ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ============================================
// НАСТРОЙКИ ONLYOFFICE
// ============================================
$DOC_SERV_URL = 'https://document.serverkherson.xyz'; 
$JWT_SECRET = 'uZbUAlqEAONtLYmVrZVNjuqOefiQZR';
$VIEWER_USER = 'zip-viewer';

// ============================================
// МУЛЬТИЯЗЫЧНОСТЬ
// ============================================

$languages = cloud_load_translation_map(__DIR__);


$lang = isset($_GET['lang']) && array_key_exists($_GET['lang'], $languages) ? $_GET['lang'] : 'ru';
$theme = isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light']) ? $_GET['theme'] : 'dark';
$folderColor = isset($_GET['folderColor']) ? $_GET['folderColor'] : '#f1c40f';
$archiveParam = isset($_GET['archive']) ? $_GET['archive'] : '';
$pathParam = isset($_GET['path']) ? $_GET['path'] : '';
$user = isset($_GET['user']) ? $_GET['user'] : 'default';
$root = isset($_GET['root']) ? $_GET['root'] : 'main';
$parentArchive = isset($_GET['parent']) ? urldecode($_GET['parent']) : null;

$currentPath = '';
if (isset($_GET['path'])) {
    $pathValue = $_GET['path'];
    // Игнорируем path, если он указывает на сам архив (содержит расширение .zip или абсолютный путь)
    $isArchivePath = (stripos($pathValue, '.zip') !== false) ||
                     (preg_match('/^[a-zA-Z]:[\\\\\/]/', $pathValue)) ||
                     (strpos($pathValue, '\\') !== false);

    if (!$isArchivePath) {
        $currentPath = ltrim($pathValue, '/');
        $currentPath = rtrim($currentPath, '/');
        if ($currentPath !== '') {
            $currentPath .= '/';
        }
    }
}

$viewFile = isset($_GET['view']) ? $_GET['view'] : null;
$officeCallback = isset($_GET['office_callback']) ? true : false;
$officeDownload = isset($_GET['office_download']) ? true : false;
$officeView = isset($_GET['office_view']) ? $_GET['office_view'] : null;
$downloadFromZip = isset($_GET['download_from_zip']) ? true : false;

$i18n = $languages[$lang];

$archivePath = urldecode($archiveParam);
require_once dirname(__DIR__, 4) . '/includes/cloud_groups.php';
function app_path($relativePath = '') {
    $baseDir = CLOUD_APP_ROOT;
    if ($relativePath === '') return $baseDir;
    return $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
}
function resolve_user_file_path($user, $relativePath, $root = 'main') {
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') return null;
    $safeUser = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', (string)$user);
    $normalizedWeb = ltrim(str_replace('\\', '/', $relativePath), '/');
    $storagePrefix = 'storage/' . $safeUser . '/';
    if (str_starts_with($normalizedWeb, $storagePrefix)) $normalizedWeb = substr($normalizedWeb, strlen($storagePrefix));
    if (str_starts_with((string)$root, 'group:')) {
        try {
            $appDataPath = cloud_resolve_file_path((string)$user, (string)$root, $relativePath);
            if (is_file($appDataPath)) return $appDataPath;
        } catch (Throwable $e) {
        }
    }
    try {
        $appDataPath = appdata_user_full_path('cloud', (string)$user, (string)$root, $normalizedWeb, false);
        if (is_file($appDataPath)) return $appDataPath;
    } catch (Throwable $e) {
    }
    $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    $rootMap = ['archive' => '.archive', 'temp' => '.temp_files', 'documents' => '.documents', 'photos' => '.photos', 'pdf' => '.pdf'];
    $rootPrefix = ($root !== 'main' && isset($rootMap[$root])) ? $rootMap[$root] : '';
    $candidates = [];
    if ($rootPrefix !== '' && strpos($normalized, $rootPrefix . DIRECTORY_SEPARATOR) !== 0) $candidates[] = $rootPrefix . DIRECTORY_SEPARATOR . $normalized;
    if (preg_match('/^(?:[a-zA-Z]:[\\\\\\/]|\/)/', $relativePath)) $candidates[] = $relativePath;
    $candidates[] = $normalized;
    $candidates[] = app_path($normalized);
    $candidates[] = app_path('../' . $normalized);
    $roots = [app_path('storage/' . $user), app_path('../storage/' . $user), app_path('temp'), app_path('temp_convert'), app_path('transcription_uploads'), app_path('tts_output')];
    foreach ($roots as $rootBase) $candidates[] = rtrim($rootBase, '/\\') . DIRECTORY_SEPARATOR . $normalized;
    if ($rootPrefix !== '') foreach ($roots as $rootBase) $candidates[] = rtrim($rootBase, '/\\') . DIRECTORY_SEPARATOR . $rootPrefix . DIRECTORY_SEPARATOR . $normalized;
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $resolved = realpath($candidate);
            if ($resolved !== false) return $resolved;
        }
    }
    return null;
}
if ((!$archivePath || !is_file($archivePath)) && $pathParam !== '') {
    $resolvedArchive = resolve_user_file_path($user, urldecode($pathParam), $root);
    if ($resolvedArchive) $archivePath = $resolvedArchive;
}
$archiveName = basename($archivePath);

// Сохраняем информацию о родительском архиве для навигации
$archiveChain = [];
if ($parentArchive) {
    $archiveChain = json_decode($parentArchive, true) ?: [];
}
$archiveChain[] = ['path' => $archivePath, 'name' => $archiveName, 'internal_path' => $currentPath];

$zip = null;
$files = [];
$allFiles = [];
$audioFiles = [];
$pdfFiles = [];
$officeFiles = [];
$zipFiles = [];
$error = '';

// ============================================
// ОБРАБОТКА ONLYOFFICE CALLBACK
// ============================================
if ($officeCallback && isset($_GET['file']) && isset($_GET['user'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $callbackFile = urldecode($_GET['file']);
    $callbackUser = $_GET['user'];
    $body = json_decode(file_get_contents('php://input'), true);
    
    echo json_encode(['error' => 0, 'status' => 1]);
    exit;
}

// ============================================
// ОБРАБОТКА СКАЧИВАНИЯ ФАЙЛА ДЛЯ ONLYOFFICE
// ============================================
if ($officeDownload && isset($_GET['file']) && isset($_GET['user'])) {
    $downloadFile = urldecode($_GET['file']);
    $downloadUser = $_GET['user'];
    
    if (!empty($archivePath) && file_exists($archivePath)) {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) === true) {
            $stream = $zip->getStream($downloadFile);
            if ($stream) {
                $data = stream_get_contents($stream);
                fclose($stream);
                $zip->close();
                
                $filename = basename($downloadFile);
                $filesize = strlen($data);
                
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . $filesize);
                header('Cache-Control: private');
                header('Pragma: private');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                
                echo $data;
                exit;
            }
            $zip->close();
        }
    }
    
    header('HTTP/1.0 404 Not Found');
    echo 'File not found';
    exit;
}

// ============================================
// СКАЧИВАНИЕ ФАЙЛА ИЗ АРХИВА БЕЗ РАСПАКОВКИ
// ============================================
if ($downloadFromZip && isset($_GET['file']) && !empty($archivePath) && file_exists($archivePath)) {
    $downloadFile = urldecode($_GET['file']);
    $zip = new ZipArchive();
    if ($zip->open($archivePath) === true) {
        $stream = $zip->getStream($downloadFile);
        if ($stream) {
            $data = stream_get_contents($stream);
            fclose($stream);
            $zip->close();
            $filename = basename($downloadFile);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($data));
            header('Cache-Control: private');
            header('Pragma: private');
            echo $data;
            exit;
        }
        $zip->close();
    }
    header('HTTP/1.0 404 Not Found');
    echo 'File not found';
    exit;
}

// ============================================
// ОБРАБОТКА ПРОСМОТРА ЧЕРЕЗ ONLYOFFICE
// ============================================
if ($officeView && !$error) {
    $officeFilePath = urldecode($officeView);
    
    $zip = new ZipArchive();
    if ($zip->open($archivePath) === true) {
        $fileExists = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ($zip->getNameIndex($i) === $officeFilePath) {
                $fileExists = true;
                break;
            }
        }
        $zip->close();
    }
    
    if (!$fileExists) {
        die('File not found in archive');
    }
    
    $ext = strtolower(pathinfo($officeFilePath, PATHINFO_EXTENSION));
    $docTypes = [
        'doc' => 'word', 'docx' => 'word', 'odt' => 'word', 'rtf' => 'word', 'txt' => 'word',
        'xls' => 'cell', 'xlsx' => 'cell', 'ods' => 'cell', 'csv' => 'cell',
        'ppt' => 'slide', 'pptx' => 'slide', 'odp' => 'slide'
    ];
    
    if (!isset($docTypes[$ext])) {
        die('Unsupported file type for ONLYOFFICE viewing');
    }
    
    $documentType = $docTypes[$ext];
    
    function jwtEncode($payload, $secret) {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $h = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', "$h.$p", $secret, true);
        $s = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        return "$h.$p.$s";
    }
    
    $docUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] 
              . '?office_download=1&file=' . urlencode($officeFilePath) . '&user=' . $VIEWER_USER . '&archive=' . urlencode($archivePath);
    
    $cbUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] 
             . '?office_callback=1&file=' . urlencode($officeFilePath) . '&user=' . $VIEWER_USER . '&archive=' . urlencode($archivePath);
    
    $fileKey = md5($officeFilePath . filemtime($archivePath));
    
    $docConfig = [
        'documentType' => $documentType,
        'type' => 'desktop',
        'document' => [
            'fileType' => $ext,
            'key' => $fileKey,
            'title' => basename($officeFilePath),
            'url' => $docUrl,
            'permissions' => [
                'edit' => false,
                'download' => true,
                'print' => true,
                'review' => false,
                'comment' => false,
                'copy' => true
            ]
        ],
        'editorConfig' => [
            'mode' => 'view',
            'callbackUrl' => $cbUrl,
            'lang' => $lang,
            'user' => [
                'id' => 'zip_viewer_' . md5($VIEWER_USER),
                'name' => $VIEWER_USER,
            ],
            'customization' => [
                'goback' => [
                    'url' => 'javascript:window.parent.postMessage({type: "closeOnlyOfficeEditor"}, "*")'
                ],
                'compactToolbar' => true,
                'toolbarNoTabs' => true,
                'reviewDisplay' => 'original',
                'commentAuthorOnly' => false,
                'hideRightMenu' => false,
                'hideRulers' => false,
                'about' => true,
                'chat' => false,
                'help' => true,
                'header' => false,
                'plugins' => true,
                'feedback' => true,
                'comments' => false,
                'uiTheme' => ($theme === 'light') ? 'theme-light' : 'theme-dark',
                'logo' => [
                    'image' => '',
                    'imageEmbedded' => '',
                    'url' => ''
                ]
            ],
        ],
    ];
    
    $docConfig['token'] = jwtEncode($docConfig, $JWT_SECRET);
    
    $uiTheme = ($theme === 'light') ? 'theme-light' : 'theme-dark';
    
    $currentTheme = [
        'theme' => $uiTheme,
        'backgroundColor' => ($theme === 'light') ? '#f3f3f3' : '#1e1e1e'
    ];
    require __DIR__ . '/../views/office.php';
    exit;
}

// ============================================
// ОСНОВНАЯ ЛОГИКА РАБОТЫ С АРХИВОМ
// ============================================

if (empty($archivePath)) {
    $error = 'No archive specified';
} elseif (!file_exists($archivePath)) {
    $error = $i18n['archive_not_found'] . ' ' . htmlspecialchars($archiveName);
} else {
    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        $error = $i18n['cannot_open'] . ' ' . $zip->getStatusString();
        $zip = null;
    }
}

if ($zip && !$error) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $allFiles[] = [
            'name' => $name,
            'is_dir' => str_ends_with($name, '/'),
            'stat' => $zip->statIndex($i)
        ];
    }
    
    $seen = [];
    
    foreach ($allFiles as $file) {
        $name = $file['name'];
        
        if (!str_starts_with($name, $currentPath)) {
            continue;
        }
        
        $relPath = substr($name, strlen($currentPath));
        
        if ($relPath === '' || $relPath === '/') {
            continue;
        }
        
        $parts = explode('/', $relPath, 2);
        $itemName = $parts[0];
        
        if (isset($parts[1]) && $parts[1] !== '') {
            continue;
        }
        
        $isDirectory = $file['is_dir'] || (isset($parts[1]) && $parts[1] === '');
        
        if ($isDirectory && in_array($itemName, $seen)) {
            continue;
        }
        
        $seen[] = $itemName;
        
        $fullPath = $currentPath . $itemName . ($isDirectory ? '/' : '');
        
        if ($isDirectory) {
            $files[] = [
                'type' => 'folder',
                'name' => $itemName,
                'path' => $fullPath,
                'size' => '-',
                'date' => isset($file['stat']['mtime']) ? date('Y-m-d H:i:s', $file['stat']['mtime']) : '-'
            ];
        } else {
            $files[] = [
                'type' => 'file',
                'name' => $itemName,
                'path' => $fullPath,
                'size' => formatBytes($file['stat']['size']),
                'date' => isset($file['stat']['mtime']) ? date('Y-m-d H:i:s', $file['stat']['mtime']) : '-'
            ];
            
            $ext = strtolower(pathinfo($itemName, PATHINFO_EXTENSION));
            if (in_array($ext, ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a'])) {
                $audioFiles[] = [
                    'name' => $itemName,
                    'path' => $fullPath
                ];
            }
            if (in_array($ext, ['pdf'])) {
                $pdfFiles[] = [
                    'name' => $itemName,
                    'path' => $fullPath
                ];
            }
            if (in_array($ext, ['doc', 'docx', 'odt', 'rtf', 'txt', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp'])) {
                $officeFiles[] = [
                    'name' => $itemName,
                    'path' => $fullPath
                ];
            }
            if (in_array($ext, ['zip'])) {
                $zipFiles[] = [
                    'name' => $itemName,
                    'path' => $fullPath
                ];
            }
        }
    }

    usort($files, function ($a, $b) {
        if ($a['type'] === 'folder' && $b['type'] !== 'folder') return -1;
        if ($a['type'] !== 'folder' && $b['type'] === 'folder') return 1;
        return strnatcasecmp($a['name'], $b['name']);
    });
    
    $zip->close();
}

function formatBytes($bytes, $precision = 2) {
    if ($bytes === '-' || $bytes === 0) return '-';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function buildUrl($params = []) {
    global $lang, $theme, $folderColor, $archivePath, $archiveChain;
    
    $defaultParams = [
        'archive' => urlencode($archivePath),
        'lang' => $lang,
        'theme' => $theme,
        'folderColor' => $folderColor
    ];
    
    // Добавляем цепочку архивов для навигации
    if (count($archiveChain) > 1) {
        // Убираем текущий архив из цепочки для parent
        $parentChain = array_slice($archiveChain, 0, -1);
        $defaultParams['parent'] = urlencode(json_encode($parentChain));
    }
    
    $params = array_merge($defaultParams, $params);
    
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        }
    }
    
    if (isset($params['path'])) {
        $params['path'] = ltrim($params['path'], '/');
        if ($params['path'] === '') {
            unset($params['path']);
        }
    }
    
    return '?' . http_build_query($params);
}

function getParentPath($path) {
    $path = rtrim($path, '/');
    if (empty($path)) {
        return '';
    }
    
    $lastSlash = strrpos($path, '/');
    if ($lastSlash === false) {
        return '';
    }
    
    return substr($path, 0, $lastSlash + 1);
}

function getBreadcrumbs($path) {
    $path = rtrim($path, '/');
    if (empty($path)) {
        return [];
    }
    
    $parts = explode('/', $path);
    $breadcrumbs = [];
    $currentPath = '';
    
    foreach ($parts as $part) {
        if ($part === '') continue;
        $currentPath .= $part . '/';
        $breadcrumbs[] = [
            'name' => $part,
            'path' => $currentPath
        ];
    }
    
    return $breadcrumbs;
}

// Функция для проверки, является ли файл текстовым
function isTextFile($data, $ext) {
    // Сначала проверяем расширение
    $textExtensions = ['txt', 'md', 'log', 'json', 'csv', 'php', 'html', 'htm', 'css', 
                       'js', 'jsx', 'ts', 'tsx', 'xml', 'yml', 'yaml', 'ini', 'sql',
                       'py', 'java', 'cpp', 'c', 'h', 'hpp', 'cs', 'go', 'rb', 'rs',
                       'swift', 'kt', 'scala', 'pl', 'sh', 'bash', 'ps1', 'dockerfile',
                       'vue', 'svelte', 'scss', 'sass', 'less', 'styl', 'conf', 'cfg'];
    
    if (in_array($ext, $textExtensions)) {
        return true;
    }
    
    // Если расширение не в списке, проверяем содержимое на наличие нулевых байтов
    // Текстовые файлы обычно не содержат нулевых байтов
    $sample = substr($data, 0, 1024); // Проверяем первые 1024 байта
    return strpos($sample, "\0") === false && mb_check_encoding($sample, 'UTF-8');
}

$previewContent = '';
$previewType = '';
$monacoLanguage = 'plaintext';
$mimeType = '';
$pdfData = '';

if ($viewFile && $zip && !$error) {
    $zip = new ZipArchive();
    if ($zip->open($archivePath) === true) {
        $ext = strtolower(pathinfo($viewFile, PATHINFO_EXTENSION));
        $officeExts = ['doc', 'docx', 'odt', 'rtf', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp'];
        
        if (in_array($ext, $officeExts)) {
            $zip->close();
            header('Location: ' . buildUrl(['office_view' => $viewFile, 'view' => null]));
            exit;
        }
        
        if ($ext === 'zip') {
            $zip->close();
            
            // Извлекаем вложенный архив во временный файл
            $tempDir = sys_get_temp_dir();
            $tempZipFile = $tempDir . '/' . uniqid('nested_') . '.zip';
            
            $innerZip = new ZipArchive();
            if ($innerZip->open($archivePath) === true) {
                $stream = $innerZip->getStream($viewFile);
                if ($stream) {
                    $data = stream_get_contents($stream);
                    fclose($stream);
                    file_put_contents($tempZipFile, $data);

                    // Перенаправляем на просмотр вложенного архива БЕЗ параметра path (корень архива)
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?archive=' . urlencode($tempZipFile) .
                           '&parent=' . urlencode(json_encode($archiveChain)) .
                           '&lang=' . $lang . '&theme=' . $theme . '&folderColor=' . urlencode($folderColor));
                    exit;
                }
                $innerZip->close();
            }
        }
        
        $stream = $zip->getStream($viewFile);
        if ($stream) {
            $data = stream_get_contents($stream);
            fclose($stream);

            if (in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'])) {
                $previewType = 'video';
                $previewContent = base64_encode($data);
                $mimeType = match ($ext) {
                    'mp4' => 'video/mp4',
                    'webm' => 'video/webm',
                    'ogg' => 'video/ogg',
                    'mov' => 'video/quicktime',
                    'avi' => 'video/x-msvideo',
                    'mkv' => 'video/x-matroska',
                    default => 'video/mp4'
                };
            }
            elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a'])) {
                $previewType = 'audio';
                $previewContent = base64_encode($data);
                $mimeType = match ($ext) {
                    'mp3' => 'audio/mpeg',
                    'wav' => 'audio/wav',
                    'ogg' => 'audio/ogg',
                    'flac' => 'audio/flac',
                    'aac' => 'audio/aac',
                    'm4a' => 'audio/mp4',
                    default => 'audio/mpeg'
                };
            }
            elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico'])) {
                $previewType = 'image';
                $mimeExt = $ext === 'jpg' ? 'jpeg' : $ext;
                $previewContent = 'data:image/' . $mimeExt . ';base64,' . base64_encode($data);
            }
            elseif (in_array($ext, ['pdf'])) {
                $previewType = 'pdf';
                $pdfData = base64_encode($data);
            }
            elseif (isTextFile($data, $ext)) {
                // Все текстовые файлы открываем в Monaco
                $previewType = 'monaco';
                $previewContent = $data;
                $monacoLanguage = match ($ext) {
                    'md' => 'markdown',
                    'json' => 'json',
                    'php' => 'php',
                    'html', 'htm' => 'html',
                    'css' => 'css',
                    'js', 'jsx' => 'javascript',
                    'ts', 'tsx' => 'typescript',
                    'xml' => 'xml',
                    'yml', 'yaml' => 'yaml',
                    'ini', 'conf', 'cfg' => 'ini',
                    'sql' => 'sql',
                    'py' => 'python',
                    'java' => 'java',
                    'cpp', 'c', 'h', 'hpp' => 'cpp',
                    'cs' => 'csharp',
                    'go' => 'go',
                    'rb' => 'ruby',
                    'rs' => 'rust',
                    'swift' => 'swift',
                    'kt' => 'kotlin',
                    'scala' => 'scala',
                    'pl' => 'perl',
                    'sh', 'bash' => 'shell',
                    'ps1' => 'powershell',
                    'dockerfile' => 'dockerfile',
                    'vue' => 'vue',
                    'svelte' => 'svelte',
                    'scss', 'sass' => 'scss',
                    'less' => 'less',
                    'styl' => 'stylus',
                    'log' => 'log',
                    'csv' => 'csv',
                    'txt' => 'plaintext',
                    default => 'plaintext'
                };
            }
            else {
                $previewType = 'text';
                $previewContent = $i18n['format_not_supported'] . ' ' . formatBytes(strlen($data));
            }
        } else {
            $previewType = 'text';
            $previewContent = $i18n['cannot_read'];
        }
        $zip->close();
    }
}

$breadcrumbs = getBreadcrumbs($currentPath);

require __DIR__ . '/../views/main.php';
