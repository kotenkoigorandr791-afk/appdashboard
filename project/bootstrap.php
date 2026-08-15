<?php

ob_start();
$__APP_DEBUG = (string)getenv('APP_DEBUG') === '1';
ini_set('display_errors', $__APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', $__APP_DEBUG ? '1' : '0');
ini_set('log_errors', $__APP_DEBUG ? '1' : '0');
if ($__APP_DEBUG) {
    ini_set('error_log', 'php://stderr');
}
error_reporting($__APP_DEBUG ? E_ALL : 0);
require_once APP_ROOT . '/lib/redis.php';
app_redis_configure_php_sessions();
$__cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
if (PHP_SESSION_NONE === session_status()) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $__cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (function_exists('ini_set')) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
    }
}
session_start();
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/bot/tg_mini_auth.php';
if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    tgMiniRestoreSessionFromCookie();
}
require_once APP_ROOT . '/smart_log.php';
if ($__APP_DEBUG) {
    smart_log_bootstrap('dashboard');
}
if (!function_exists('apSecExpectedHosts')) {
    function apSecExpectedHosts(): array {
        $hosts = [];
        $h = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($h !== '') $hosts[] = strtolower($h);
        $sn = (string)($_SERVER['SERVER_NAME'] ?? '');
        if ($sn !== '') $hosts[] = strtolower($sn);
        $extra = (string)getenv('APP_TRUSTED_HOSTS');
        if ($extra !== '') {
            foreach (explode(',', $extra) as $e) {
                $e = strtolower(trim($e));
                if ($e !== '') $hosts[] = $e;
            }
        }
        return array_values(array_unique($hosts));
    }
}
if (!function_exists('apSecSameOrigin')) {
    function apSecSameOrigin(): bool {
        $expected = apSecExpectedHosts();
        if (empty($expected)) return true;
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $candidate = $origin !== '' ? $origin : $referer;
        if ($candidate === '') return false;
        $parts = parse_url($candidate);
        if (!is_array($parts) || empty($parts['host'])) return false;
        return in_array(strtolower($parts['host']), $expected, true);
    }
}
if (!function_exists('apSecRequireSameOrigin')) {
    function apSecRequireSameOrigin(): void {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? "GET"));
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) return;
        if (apSecSameOrigin()) return;
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'success' => false, 'error' => 'cross_origin_forbidden']);
        exit;
    }
}
if (!function_exists('apSecCsrfToken')) {
    function apSecCsrfToken(): string {
        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}
if (!function_exists('apSecCsrfCheck')) {
    function apSecCsrfCheck(): bool {
        $expected = $_SESSION['_csrf_token'] ?? '';
        if (!is_string($expected) || $expected === '') return false;
        $sent = (string)(
            $_POST['_csrf'] ?? $_GET['_csrf'] ??
            $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRF'] ?? ''
        );
        return $sent !== '' && hash_equals($expected, $sent);
    }
}
if (!function_exists('apSecSafeFileExt')) {
    function apSecSafeFileExt(string $original, array $whitelist, string $fallback = 'bin'): string {
        $ext = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? '';
        if ($ext === '' || !in_array($ext, $whitelist, true)) return $fallback;
        $blocked = ['php','php3','php4','php5','php7','php8','phtml','phar','phps','pht','phtm','inc','cgi','pl','py','rb','jsp','asp','aspx','sh','bat','cmd','exe','dll','so','htaccess','htpasswd'];
        return in_array($ext, $blocked, true) ? $fallback : $ext;
    }
}
if (!function_exists('apSecRandomFilename')) {
    function apSecRandomFilename(string $ext): string {
        $ext = preg_replace('/[^a-z0-9]/', '', strtolower($ext)) ?? 'bin';
        if ($ext === '') $ext = 'bin';
        return bin2hex(random_bytes(16)) . '.' . $ext;
    }
}
if (!function_exists('apSecEnsureWritableDir')) {
    function apSecEnsureWritableDir(string $dir): bool {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
        @chmod($dir, 0775);
        return is_dir($dir) && is_writable($dir);
    }
}
if (!function_exists('apSecDetectMimeFromFile')) {
    function apSecDetectMimeFromFile(string $path): string {
        if (!is_file($path)) return '';
        if (function_exists('finfo_open')) {
            $f = @finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $m = (string)@finfo_file($f, $path);
                @finfo_close($f);
                if ($m !== '') return strtolower($m);
            }
        }
        if (function_exists('mime_content_type')) {
            return strtolower((string)@mime_content_type($path));
        }
        return '';
    }
}
if (!function_exists('apSecConvertImageToWebp')) {
    function apSecConvertImageToWebp(string $sourcePath, string $destPath, ?string $mime = null): bool {
        if (!is_file($sourcePath)) return false;
        $mime = $mime !== null && $mime !== '' ? strtolower($mime) : apSecDetectMimeFromFile($sourcePath);
        $convertBin = trim((string)@shell_exec('command -v convert 2>/dev/null'));
        if ($convertBin === '') return false;
        $sourceArg = $sourcePath;
        if ($mime === 'image/gif') {
            $sourceArg .= '[0]';
        }
        $cmd = escapeshellarg($convertBin)
            . ' ' . escapeshellarg($sourceArg)
            . ' -auto-orient -strip -quality 84 '
            . escapeshellarg($destPath)
            . ' 2>/dev/null';
        @exec($cmd, $out, $code);
        return $code === 0 && is_file($destPath) && filesize($destPath) > 0;
    }
}
if (!function_exists('apSecStoreSupportChatMedia')) {
    function apSecStoreSupportChatMedia(array $file): array {
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 20 * 1024 * 1024) return ['error' => 'file_too_large'];
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) return ['error' => 'invalid_upload'];
        $mime = apSecDetectMimeFromFile($tmp);
        $origName = (string)($file['name'] ?? '');
        $origExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $imageMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $videoMimes = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
        $audioMimes = ['audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/webm' => 'weba', 'audio/mp4' => 'm4a'];
        $textMimes = [
            'text/plain' => 'txt', 'text/markdown' => 'md', 'text/x-markdown' => 'md',
            'application/json' => 'json', 'text/xml' => 'xml', 'application/xml' => 'xml',
            'text/csv' => 'csv', 'text/x-log' => 'log', 'text/html' => 'html',
            'text/css' => 'css', 'text/javascript' => 'js', 'application/javascript' => 'js',
            'text/x-python' => 'py', 'text/x-java' => 'java', 'text/x-c' => 'c',
            'text/x-c++' => 'cpp', 'text/x-php' => 'php', 'text/x-ruby' => 'rb',
            'text/x-go' => 'go', 'text/x-rust' => 'rs', 'text/x-sql' => 'sql',
            'application/x-yaml' => 'yaml', 'text/yaml' => 'yaml', 'text/x-yaml' => 'yml'
        ];
        $kind = null; $ext = null;
        if ($mime === 'video/webm' && in_array($origExt, ['weba', 'opus'], true)) { $kind = 'audio'; $ext = 'weba'; }
        elseif (isset($imageMimes[$mime])) { $kind = 'image'; $ext = $imageMimes[$mime]; }
        elseif (isset($videoMimes[$mime])) { $kind = 'video'; $ext = $videoMimes[$mime]; }
        elseif (isset($audioMimes[$mime])) { $kind = 'audio'; $ext = $audioMimes[$mime]; }
        elseif (isset($textMimes[$mime])) { $kind = 'text'; $ext = $textMimes[$mime]; }
        else {
            // Fallback: check by extension for text files
            $textExts = ['txt', 'md', 'json', 'xml', 'csv', 'log', 'html', 'css', 'js', 'py', 'java', 'c', 'cpp', 'php', 'rb', 'go', 'rs', 'sql', 'yaml', 'yml', 'sh', 'bat', 'ini', 'conf', 'config'];
            if (in_array($origExt, $textExts, true)) {
                $kind = 'text';
                $ext = $origExt;
            } else {
                return ['error' => 'unsupported_type'];
            }
        }
        if ($kind === 'image') {
            $info = @getimagesize($tmp);
            $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
            if (!is_array($info) || !in_array((int)($info[2] ?? 0), $allowed, true)) {
                return ['error' => 'unsupported_type'];
            }
        }
        $uploadDir = APP_ROOT . '/uploads/support_chat/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        $newFileName = apSecRandomFilename($ext);
        $destPath = $uploadDir . $newFileName;
        if (!move_uploaded_file($tmp, $destPath)) return ['error' => 'move_failed'];
        @chmod($destPath, 0644);
        return ['path' => 'uploads/support_chat/' . $newFileName, 'type' => $kind];
    }
}

if ($__APP_DEBUG) {
    $__monitorRequestStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
    $__monitorHandledErrors = [];

    set_error_handler(function ($severity, $message, $file, $line) use (&$__monitorHandledErrors) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $level = in_array($severity, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_RECOVERABLE_ERROR], true) ? 'warning' : 'error';
    $hash = hash('sha256', $severity . '|' . $file . '|' . $line . '|' . $message);
    $__monitorHandledErrors[$hash] = true;
    monitorRecordError((string)$message, $level, (string)$file, (int)$line, ['severity' => $severity], (string)($_GET['app'] ?? 'main'));
    return false;
    });

    register_shutdown_function(function () use ($__monitorRequestStart, &$__monitorHandledErrors) {
    $fatal = error_get_last();
    $appId = (string)($_GET['app'] ?? 'main');
    $errorMessage = '';
    $statusCode = http_response_code();
    if ($statusCode < 100) {
        $statusCode = 200;
    }

    if (is_array($fatal) && in_array((int)($fatal['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $errorMessage = (string)($fatal['message'] ?? '');
        $hash = hash('sha256', (string)($fatal['type'] ?? '') . '|' . (string)($fatal['file'] ?? '') . '|' . (string)($fatal['line'] ?? '') . '|' . $errorMessage);
        if (!isset($__monitorHandledErrors[$hash])) {
            monitorRecordError($errorMessage, 'critical', (string)($fatal['file'] ?? ''), isset($fatal['line']) ? (int)$fatal['line'] : null, ['type' => $fatal['type'] ?? null], $appId);
        }
        if ($statusCode < 500) {
            $statusCode = 500;
        }
    }

    $durationMs = (int)round((microtime(true) - (float)$__monitorRequestStart) * 1000);
    if ($statusCode >= 400 || $durationMs >= 1000 || $errorMessage !== '') {
        monitorRecordHttpRequest([
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'path' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'app_id' => $appId,
            'user_email' => $_SESSION['user']['email'] ?? null,
            'ip_address' => monitorClientIp(),
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'response_bytes' => ob_get_length() ?: 0,
            'error_message' => $errorMessage,
            'context' => [
                'script' => $_SERVER['SCRIPT_NAME'] ?? '',
                'query' => $_SERVER['QUERY_STRING'] ?? ''
            ]
        ]);
    }
    });
}

if(isset($_SESSION['user'])&&isset($_SESSION['auth_time'])){
    $currentSession=getUserFromActiveSessions(session_id());
    if(!$currentSession){
        session_destroy();
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }
    $sessionEmail=(string)($_SESSION['user']['email']??'');
    if($sessionEmail!==''){
        $freshUser=getUserByEmail($sessionEmail);
        if($freshUser){
            $_SESSION['user']=$freshUser;
            $_SESSION['user']['email']=$sessionEmail;
            addUserToActiveSessions($sessionEmail,session_id(),$_SESSION['user']);
        }else{
            removeUserFromActiveSessions($sessionEmail,session_id());
            session_destroy();
            header('Location: '.$_SERVER['PHP_SELF']);
            exit;
        }
    }
}
