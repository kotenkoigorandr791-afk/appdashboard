<?php
// transcription_editor.php
$audioPath = $_GET['path'] ?? '';
$user = $_GET['user'] ?? 'default';
$root = $_GET['root'] ?? 'main';
$lang = $_GET['lang'] ?? 'en'; // Interface language
$transcriptionLang = $_GET['transcription_lang'] ?? 'pl'; // Transcription language (default Polish)
$theme = $_GET['theme'] ?? 'light';
$color = $_GET['color'] ?? '%2385b503';
$transcriptionApiUrl = cloud_url('transcription_api.php');
$apiUrl = cloud_url('api.php');
require_once dirname(__DIR__, 4) . '/includes/cloud_groups.php';
require_once dirname(__DIR__, 4) . '/includes/session.php';
require_once dirname(__DIR__, 4) . '/includes/auth_gate.php';
if (session_status() === PHP_SESSION_NONE) {
    cloud_session_start();
}
cloud_require_session_auth();

if (!$audioPath) {
    die('No audio file specified');
}
function get_transcription_editor_access_role(): int {
    if (session_status() === PHP_SESSION_NONE) {
        cloud_session_start();
    }
    $email = (string)($_SESSION['user']['email'] ?? $_SESSION['email'] ?? '');
    if ($email === '') return 0;
    try {
        require_once dirname(CLOUD_APP_ROOT, 2) . '/lib/app_db.php';
        $db = app_main_db();
        $stmt = $db->prepare('SELECT role FROM users WHERE email = :email LIMIT 1');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result ? $result->fetch(PDO::FETCH_ASSOC) : false;
        return $row && isset($row['role']) ? (int)$row['role'] : 0;
    } catch (Throwable $e) {
        return 0;
    }
}
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
    if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $relativePath)) return null;
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
    foreach ($roots as $root) $candidates[] = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $normalized;
    if ($rootPrefix !== '') foreach ($roots as $root) $candidates[] = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $rootPrefix . DIRECTORY_SEPARATOR . $normalized;
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $resolved = realpath($candidate);
            if ($resolved !== false) return $resolved;
        }
    }
    return null;
}

// Translation dictionary
$translations = cloud_load_translation_map(__DIR__);


// Get translations for current interface language
$t = $translations[$lang] ?? $translations['en'];

// Function to format file size
function formatFileSize($bytes) {
    global $t;
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' ' . $t['mb'];
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' ' . $t['kb'];
    } else {
        return $bytes . ' ' . $t['bytes'];
    }
}

$rootMap = ['archive' => '.archive', 'temp' => '.temp_files', 'documents' => '.documents', 'photos' => '.photos', 'pdf' => '.pdf'];
$apiAudioPath = ($root !== 'main' && isset($rootMap[$root]) && strpos(ltrim($audioPath, '/\\'), $rootMap[$root] . '/') !== 0) ? $rootMap[$root] . '/' . ltrim($audioPath, '/\\') : $audioPath;
$sourcePath = resolve_user_file_path($user, $audioPath, $root) ?: '';
$accessRole = get_transcription_editor_access_role();
$canViewDebugLog = ($accessRole === 2);
$audioMimeType = 'audio/mpeg'; // Default MP3
if ($sourcePath) {
    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    if ($extension === 'm4a' || $extension === 'mp4') $audioMimeType = 'audio/mp4';
    elseif ($extension === 'wav') $audioMimeType = 'audio/wav';
    elseif ($extension === 'ogg' || $extension === 'oga' || $extension === 'opus') $audioMimeType = 'audio/ogg';
    elseif ($extension === 'webm' || $extension === 'weba') $audioMimeType = 'audio/webm';
    elseif ($extension === 'flac') $audioMimeType = 'audio/flac';
    elseif ($extension === 'aac') $audioMimeType = 'audio/aac';
}
$audioStreamUrl = $apiAudioPath !== '' ? ($apiUrl . '?action=raw&path=' . rawurlencode($apiAudioPath) . '&root=' . rawurlencode($root)) : '';
$accentColor = ltrim(urldecode((string)$color), '#%');
require __DIR__ . '/../views/page.php';
