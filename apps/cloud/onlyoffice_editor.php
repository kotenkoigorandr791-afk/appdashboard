<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/includes/session.php';
require_once dirname(__DIR__, 2) . '/storage_helper.php';
require_once __DIR__ . '/includes/cloud_groups.php';
cloud_session_start();

$DOC_SERV_URL = 'https://document.serverkherson.xyz';
$JWT_SECRET = trim(getOnlyofficeJwtSecret());

$filePath = isset($_GET['path']) ? $_GET['path'] : '';
$user = isset($_GET['user']) ? $_GET['user'] : 'default';
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$theme = isset($_GET['theme']) ? $_GET['theme'] : 'dark';
$color = isset($_GET['color']) ? (string)$_GET['color'] : '#85b503';
if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $color)) $color = '#85b503';
if ($color[0] !== '#') $color = '#' . $color;
$rootKey = isset($_GET['root']) ? (string)$_GET['root'] : (string)($_SESSION['cloud_root_context'] ?? 'main');
$validRootKeys = ['main', 'archive', 'temp', 'documents', 'photos', 'pdf', 'trash', 'clientsync'];
if (!in_array($rootKey, $validRootKeys, true) && !cloud_parse_group_id($rootKey)) $rootKey = 'main';
function cloud_onlyoffice_error(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    $safe = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>ONLYOFFICE</title></head><body><p>{$safe}</p></body></html>";
    exit;
}
function require_cloud_onlyoffice_group_access(string $rootKey): void {
    $groupId = cloud_parse_group_id($rootKey);
    if (!$groupId) return;
    $email = cloud_path_session_email();
    if (!$email) {
        cloud_onlyoffice_error('Not authenticated', 401);
    }
    $db = cloud_path_db();
    if (!$db || !cloud_group_member_access($db, $groupId, $email)) {
        cloud_onlyoffice_error('Group access denied', 403);
    }
}
function resolve_cloud_user_path_or_fail(string $user, string $rootKey, string $path): string {
    try {
        return cloud_resolve_onlyoffice_download_path($user, $rootKey, $path);
    } catch (Throwable $e) {
        cloud_onlyoffice_error('Invalid path', 400);
    }
}
function cloud_onlyoffice_find_group_root_for_file(string $user, string $path): ?string {
    $email = cloud_path_session_email();
    if (!$email || !function_exists('cloud_group_list_for_email')) return null;
    foreach (cloud_group_list_for_email($email) as $group) {
        $gid = (string)($group['id'] ?? '');
        if ($gid === '') continue;
        $candidateRoot = cloud_group_root_key($gid);
        try {
            $candidate = cloud_resolve_onlyoffice_download_path($user, $candidateRoot, $path);
            if (is_file($candidate)) return $candidateRoot;
        } catch (Throwable $e) {
        }
    }
    return null;
}
function download_remote_content(string $url): string|false {
    $data = @file_get_contents($url);
    if ($data !== false) return $data;
    if (!function_exists('curl_init')) return false;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $data = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data === false || $code >= 400) return false;
    return $data;
}
function cloud_onlyoffice_safe_name(string $path, string $fallback = 'file'): string {
    if (function_exists('cloud_safe_path_label')) {
        return cloud_safe_path_label($path, $fallback);
    }
    $base = basename(str_replace('\\', '/', $path));
    return $base !== '' ? $base : $fallback;
}
function cloud_onlyoffice_public_scheme(): string {
    $forwarded = (string)($_SERVER['HTTP_FORWARDED'] ?? '');
    if ($forwarded !== '' && preg_match('/(?:^|[;,]\s*)proto=(https?)/i', $forwarded, $m)) {
        return strtolower($m[1]);
    }
    $xfp = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
    if ($xfp === 'https' || $xfp === 'http') return $xfp;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') return 'https';
    if (strtolower((string)($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '')) === 'on') return 'https';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
    if (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') return 'https';
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') return 'https';
    return 'https';
}
function cloud_onlyoffice_public_host(): string {
    $forwarded = (string)($_SERVER['HTTP_FORWARDED'] ?? '');
    if ($forwarded !== '' && preg_match('/(?:^|[;,]\s*)host=([^;,]+)/i', $forwarded, $m)) {
        return trim($m[1], "\" \t");
    }
    $xfh = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0] ?? '');
    if ($xfh !== '') return $xfh;
    return (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
}
function cloud_onlyoffice_public_origin(): string {
    return cloud_onlyoffice_public_scheme() . '://' . cloud_onlyoffice_public_host();
}
function cloud_onlyoffice_user_photo_raw(?string $email): string {
    if (!$email) return 'default_avatar.png';
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT photo FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['photo'])) return (string)$row['photo'];
    } catch (Throwable $e) {
    }
    return 'default_avatar.png';
}
function cloud_onlyoffice_user_image_url(?string $email): string {
    $photo = trim(cloud_onlyoffice_user_photo_raw($email));
    $origin = cloud_onlyoffice_public_origin();
    if ($photo === '' || $photo === 'default_avatar.png') {
        return $origin . '/default_avatar.png';
    }
    if (filter_var($photo, FILTER_VALIDATE_URL)) {
        if (preg_match('#^https://lh3\.googleusercontent\.com/#i', $photo)) {
            return $origin . '/main?api=avatar_proxy&u=' . rawurlencode($photo);
        }
        return $photo;
    }
    if (str_starts_with($photo, '/')) return $origin . $photo;
    return $origin . '/' . ltrim($photo, '/');
}

// ---------- FILE DOWNLOAD HANDLING ----------
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $requestedPath = isset($_GET['path']) ? (string)$_GET['path'] : '';
    $requestedUser = isset($_GET['user']) ? (string)$_GET['user'] : 'default';
    $requestedRootKey = isset($_GET['root']) ? (string)$_GET['root'] : 'main';
    $requestedFullPath = '';
    try {
        $requestedFullPath = cloud_resolve_onlyoffice_download_path($requestedUser, $requestedRootKey, $requestedPath);
    } catch (Throwable $e) {
        $requestedFullPath = '';
    }

    $isDownloadDocumentsRoot = ($requestedRootKey === 'documents');

    if (is_file($requestedFullPath)) {
        $filename = basename(str_replace('\\', '/', $requestedPath));
        if ($filename === '') $filename = 'document';
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Cache-Control: private');
        header('Pragma: private');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

        if ($isDownloadDocumentsRoot) {
            $encryptedContent = (string)file_get_contents($requestedFullPath);
            $content = decrypt_documents_content($encryptedContent, $requestedUser);
            header('Content-Length: ' . strlen($content));
            echo $content;
        } else {
            header('Content-Length: ' . filesize($requestedFullPath));
            readfile($requestedFullPath);
        }
        exit;
    }

    header('HTTP/1.0 404 Not Found');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not found';
    exit;
}

// ---------- ONLYOFFICE CALLBACK HANDLING ----------
if (isset($_GET['callback'])) {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true);

    if (!empty($body['status']) && in_array((int)$body['status'], [2, 6], true) && !empty($body['url'])) {
        $fullPath = cloud_resolve_onlyoffice_download_path($user, $rootKey, $filePath);
        $newContent = download_remote_content((string)$body['url']);
        if ($newContent !== false) {
            if ($rootKey === 'documents') {
                $newContent = encrypt_documents_content($newContent, $user);
            }
            $parent = dirname($fullPath);
            if (!is_dir($parent)) @mkdir($parent, 0775, true);
            $saved = file_put_contents($fullPath, $newContent);
            echo json_encode($saved === false
                ? ['error' => 1, 'status' => 0, 'message' => 'Failed to save file']
                : ['error' => 0, 'status' => 1]);
        } else {
            echo json_encode(['error' => 1, 'status' => 0, 'message' => 'Failed to download file']);
        }
    } else {
        echo json_encode(['error' => 0, 'status' => 1]);
    }
    exit;
}

// Check that path is provided
if (empty($filePath)) {
    cloud_onlyoffice_error('File path is required', 400);
}
require_cloud_onlyoffice_group_access($rootKey);

// Build full file path accounting for hidden root folders
$fullPath = resolve_cloud_user_path_or_fail($user, $rootKey, $filePath);
if (!file_exists($fullPath) && $rootKey === 'main') {
    $groupRoot = cloud_onlyoffice_find_group_root_for_file($user, $filePath);
    if ($groupRoot) {
        $rootKey = $groupRoot;
        require_cloud_onlyoffice_group_access($rootKey);
        $fullPath = resolve_cloud_user_path_or_fail($user, $rootKey, $filePath);
    }
}

// Check file exists
if (!file_exists($fullPath)) {
    cloud_onlyoffice_error('File not found: ' . cloud_onlyoffice_safe_name($filePath), 404);
}

// Determine document type
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$docTypes = [
    'doc' => 'word', 'docx' => 'word', 'odt' => 'word', 'rtf' => 'word', 'txt' => 'word',
    'xls' => 'cell', 'xlsx' => 'cell', 'ods' => 'cell', 'csv' => 'cell',
    'ppt' => 'slide', 'pptx' => 'slide', 'odp' => 'slide'
];

if (!isset($docTypes[$ext])) {
    cloud_onlyoffice_error('Unsupported file type for ONLYOFFICE editing', 400);
}

$documentType = $docTypes[$ext];

// Check for encrypted .documents folder
$isDocumentsRoot = ($rootKey === 'documents');
$tempFilePath = null;

// Function to encrypt .documents content
function encrypt_documents_content(string $plain, string $user): string {
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = random_bytes($ivLen);
    $salt = hash('sha256', __FILE__ . '::cloud-docs-v1');
    $key = hash('sha256', $salt . '|' . $user, true);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return $plain;
    return "CLOUDDOC1" . $iv . $cipher;
}

// Function to decrypt .documents content
function decrypt_documents_content(string $raw, string $user): string {
    if (!str_starts_with($raw, "CLOUDDOC1")) return $raw;
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $body = substr($raw, 9);
    $iv = substr($body, 0, $ivLen);
    $cipher = substr($body, $ivLen);
    $salt = hash('sha256', __FILE__ . '::cloud-docs-v1');
    $key = hash('sha256', $salt . '|' . $user, true);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

// ---------- JWT AND CONFIG ----------
function jwtBase64Url(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}
function jwtEncode($payload, $secret) {
    $headerJson = json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($headerJson) || $headerJson === '' || !is_string($payloadJson) || $payloadJson === '') return '';
    $h = jwtBase64Url($headerJson);
    $p = jwtBase64Url($payloadJson);
    $s = jwtBase64Url(hash_hmac('sha256', $h . '.' . $p, (string)$secret, true));
    return $h . '.' . $p . '.' . $s;
}

// Get base URL with HTTPS
$host = $_SERVER['HTTP_HOST'];
$scriptName = $_SERVER['PHP_SELF'];

// Build URL with forced HTTPS
$baseUrl = "https://$host$scriptName";

// Create download URL for file (with rootKey for decryption)
$docDownloadUser = $user;
$docDownloadRoot = $rootKey;
$docDownloadPath = $filePath;
$groupDocParams = cloud_group_document_download_params($rootKey, $filePath);
if ($groupDocParams) {
    $docDownloadUser = $groupDocParams['user'];
    $docDownloadRoot = $groupDocParams['root'];
    $docDownloadPath = $groupDocParams['path'];
}

$docUrl = $baseUrl . '?action=download&path=' . urlencode($docDownloadPath) . '&user=' . urlencode($docDownloadUser) . '&root=' . urlencode($docDownloadRoot);

$cbUrl = $baseUrl . '?callback=1&path=' . urlencode($docDownloadPath) . '&user=' . urlencode($docDownloadUser) . '&root=' . urlencode($docDownloadRoot);

$fileMtime = @filemtime($fullPath);
$fileMtime = $fileMtime !== false ? (int)$fileMtime : 0;
$docKey = md5($fullPath . '|' . $fileMtime);

$editorEmail = cloud_path_session_email();
$editorUserId = $editorEmail ? ('user_' . md5($editorEmail)) : ('user_' . md5($user));
$editorUserName = $editorEmail ?: $user;
$editorUserImage = cloud_onlyoffice_user_image_url($editorEmail);

$editorMode = 'edit';
$groupId = cloud_parse_group_id($rootKey);
if ($groupId) {
    $db = cloud_path_db();
    if ($db && $editorEmail) {
        $access = cloud_group_member_access($db, $groupId, $editorEmail);
        if ($access && ($access['permission'] ?? '') === 'read') $editorMode = 'view';
    }
}

$docConfig = [
    'documentType' => $documentType,
    'type' => 'desktop',
    'document' => [
        'fileType' => $ext,
        'key' => $docKey,
        'title' => basename($filePath),
        'url' => $docUrl,
        'permissions' => [
            'chat' => false,
            'edit' => $editorMode === 'edit',
        ],
    ],
    'editorConfig' => [
        'mode' => $editorMode,
        'callbackUrl' => $cbUrl,
        'lang' => $lang,
        'coEditing' => [
            'mode' => 'fast',
            'change' => true,
        ],
        'plugins' => [
            'autostart' => [
                'asc.{9DC93CDB-B576-4F0C-B55E-FCC9C48DD007}'
            ],
            'pluginsData' => [
                '{9DC93CDB-B576-4F0C-B55E-FCC9C48DD007}/config.json'
            ],
        ],
        'user' => [
            'id' => $editorUserId,
            'name' => $editorUserName,
            'image' => $editorUserImage,
        ],
        'customization' => [
            'goback' => [
                'url' => 'javascript:window.parent.postMessage({type: "closeOnlyOfficeEditor"}, "*")'
            ],
            'uiTheme' => ($theme === 'light') ? 'theme-light' : 'theme-dark',
            'accentColor' => $color,
            'autosave' => true,
            'plugins' => true,
            'mobile' => [
                'forceView' => false
            ],
            'review' => [
                'showReviewChanges' => false
            ],
            'theme' => [
                'id' => $theme,
                'name' => $theme === 'light' ? 'Light' : 'Dark'
            ]
        ]
    ],
];

// Add JWT token
$docConfig['token'] = jwtEncode($docConfig, $JWT_SECRET);
if ($docConfig['token'] === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Failed to generate ONLYOFFICE security token');
}

// Theme settings for CSS
$bgColor = ($theme === 'light') ? '#ffffff' : '#1a1a1a';
$textColor = ($theme === 'light') ? '#333333' : '#f0f0f0';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ONLYOFFICE Editor - <?php echo htmlspecialchars(basename($filePath)); ?></title>
    <script src="<?php echo $DOC_SERV_URL; ?>/web-apps/apps/api/documents/api.js"></script>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow: hidden;
            background-color: <?php echo $bgColor; ?>;
            color: <?php echo $textColor; ?>;
            transition: background-color 0.3s ease;
        }
        #editor {
            width: 100%;
            height: 100vh;
        }
        .onlyoffice-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: <?php echo $textColor; ?>;
        }
        .spinner {
            border: 3px solid <?php echo ($theme === 'light') ? '#f3f3f3' : '#333333'; ?>;
            border-top: 3px solid <?php echo $color; ?>;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 0 auto 15px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .error-message {
            max-width: 400px;
            padding: 20px;
            border-radius: 8px;
            background-color: <?php echo ($theme === 'light') ? '#fff3f3' : '#2d1a1a'; ?>;
            border: 1px solid <?php echo ($theme === 'light') ? '#ffcdcd' : '#662222'; ?>;
        }
        .retry-button {
            background-color: <?php echo $color; ?>;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 15px;
            transition: opacity 0.2s;
        }
        .retry-button:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div id="editor"></div>
    
    <script>
        // Function to handle API load errors
        function handleApiError() {
            document.getElementById('editor').innerHTML = `
                <div class="onlyoffice-loading">
                    <div class="error-message">
                        <h3>Error Loading Editor</h3>
                        <p>Failed to load ONLYOFFICE API. Please check your connection.</p>
                        <p style="font-size: 12px; opacity: 0.7;">API URL: <?php echo $DOC_SERV_URL; ?></p>
                        <button class="retry-button" onclick="window.location.reload()">Retry</button>
                    </div>
                </div>
            `;
            
            window.parent.postMessage({
                type: 'onlyOfficeLoaded',
                success: false,
                error: 'API failed to load'
            }, '*');
        }

        // Handle messages from parent window
        window.addEventListener('message', function(event) {
            if (event.data.type === 'saveOnlyOfficeDocument') {

                // Can add forced save logic here
            }
        });

        // Editor initialization
        function initEditor() {
            const config = <?php echo json_encode($docConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            try {
                const editor = new DocsAPI.DocEditor('editor', config);
                window.onlyofficeEditor = editor;
                window.parent.postMessage({
                    type: 'onlyOfficeLoaded',
                    success: true,
                    theme: '<?php echo $theme; ?>'
                }, '*');
            } catch (error) {
                console.error('Failed to load ONLYOFFICE editor:', error);
                document.getElementById('editor').innerHTML = `
                    <div class="onlyoffice-loading">
                        <div class="error-message">
                            <h3>Error Loading Editor</h3>
                            <p>${error.message || 'Unknown error occurred'}</p>
                            <button class="retry-button" onclick="window.location.reload()">Retry</button>
                        </div>
                    </div>
                `;
                window.parent.postMessage({
                    type: 'onlyOfficeLoaded',
                    success: false,
                    error: error.message
                }, '*');
            }
        }

        // Set timeout for API loading
        const apiTimeout = setTimeout(handleApiError, 10000); // 10 second timeout

        // Load editor after API loads
        if (typeof DocsAPI !== 'undefined' && DocsAPI.DocEditor) {
            clearTimeout(apiTimeout);
            initEditor();
        } else {
            document.getElementById('editor').innerHTML = `
                <div class="onlyoffice-loading">
                    <div class="spinner"></div>
                    <p>Loading editor...</p>
                    <p style="font-size: 12px; opacity: 0.7;">Connecting to <?php echo $DOC_SERV_URL; ?></p>
                </div>
            `;
            
            // Check API availability
            let checkCount = 0;
            let checkInterval = setInterval(() => {
                checkCount++;
                
                if (typeof DocsAPI !== 'undefined' && DocsAPI.DocEditor) {
                    clearInterval(checkInterval);
                    clearTimeout(apiTimeout);
                    initEditor();
                } else if (checkCount > 40) { // 20 seconds max (40 * 500ms)
                    clearInterval(checkInterval);
                    clearTimeout(apiTimeout);
                    handleApiError();
                }
            }, 500);
        }

        // Add global error handler
        window.addEventListener('error', function(event) {
            console.error('Global error:', event.error);
            if (event.error && event.error.message && event.error.message.includes('DocsAPI')) {
                clearTimeout(apiTimeout);
                handleApiError();
            }
        });
    </script>
</body>
</html>
