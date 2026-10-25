<?php
$filePath = $_GET['path'] ?? '';
$lang = $_GET['lang'] ?? 'en';
$theme = $_GET['theme'] ?? 'dark';
$color = $_GET['color'] ?? '%2385b503';
$root = $_GET['root'] ?? 'main';
require_once dirname(__DIR__, 4) . '/includes/session.php';
require_once dirname(__DIR__, 4) . '/includes/auth_gate.php';
cloud_session_start();
cloud_require_session_auth();
$currentUser = cloud_session_storage_user();
require_once dirname(__DIR__, 4) . '/includes/cloud_groups.php';
$currentUser = $_SESSION['current_user'] ?? $_GET['user'] ?? 'default';
$templatesFile = CLOUD_APP_ROOT . '/templates.json';
$cloudBaseUrl = cloud_app_base_url();
$assetsBaseUrl = cloud_url('assets');
$templatesUrl = cloud_url('templates.json');
$templatesData = [];
if (file_exists($templatesFile)) {
    $templatesData = json_decode(file_get_contents($templatesFile), true);
}

function resolve_cloud_path_or_fail(string $user, string $root, string $path): string {
    try {
        return cloud_resolve_file_path($user, $root, $path);
    } catch (Throwable $e) {
        http_response_code(400);
        exit('Invalid path');
    }
}
$userStoragePath = resolve_cloud_path_or_fail($currentUser, $root, $filePath);

// Run translation
if (isset($_POST['translate'])) {
    $text = $_POST['text'] ?? '';
    $sourceLang = $_POST['source_lang'] ?? 'auto';
    $targetLang = $_POST['target_lang'] ?? 'ru';

    if (empty($text)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No text to translate']);
        exit;
    }

    // Map language codes to MyMemory API format
    $langMap = [
        'auto' => 'Autodetect',
        'en' => 'en',
        'ru' => 'ru',
        'uk' => 'uk',
        'be' => 'be',
        'de' => 'de',
        'fr' => 'fr',
        'es' => 'es',
        'it' => 'it',
        'pl' => 'pl',
        'zh' => 'zh',
        'ja' => 'ja'
    ];
    $sourceCode = $langMap[$sourceLang] ?? 'Autodetect';
    $targetCode = $langMap[$targetLang] ?? 'ru';
    
    // For autodetect, we use 'Autodetect|target' format
    if ($sourceCode === 'Autodetect') {
        $langPair = 'Autodetect|' . $targetCode;
    } else {
        $langPair = $sourceCode . '|' . $targetCode;
    }
    $url = 'https://api.mymemory.translated.net/get?q=' . urlencode($text) . '&langpair=' . urlencode($langPair);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'user_agent' => 'TranslationApp/1.0',
            'header' => 'Accept: application/json'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to fetch translation']);
        exit;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid JSON response']);
        exit;
    }

    if (isset($data['responseData']['translatedText'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'translated_text' => $data['responseData']['translatedText']
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No translation found']);
    }
    exit;
}

function isTrulyEmptyFile($content) {
    $content = trim($content);
    if ($content === '') return true;
    if (strlen($content) < 10) return true;
    if (preg_match('/^\s+$/', $content)) return true;
    return false;
}

function cleanupOldTempFiles($directory, $maxAgeHours = 1) {
    if (!is_dir($directory)) return;
    $files = scandir($directory);
    $now = time();
    $maxAge = $maxAgeHours * 3600;
    $tempExtensions = ['.aux', '.log', '.out', '.toc', '.lof', '.lot', '.nav', '.snm', '.vrb', '.bbl', '.blg', '.bcf','.run.xml', '.synctex.gz', '.synctex', '.fls','.fdb_latexmk', '.idx', '.ilg', '.ind', '.xdv','-blx.bib'];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $filePath = $directory . DIRECTORY_SEPARATOR . $file;
        if (strpos($file, 'temp_compile_') === 0) {
            $fileTime = filemtime($filePath);
            if ($now - $fileTime > $maxAge) @unlink($filePath);
        }
        foreach ($tempExtensions as $ext) {
            if (strpos($file, $ext) !== false) {
                $fileTime = filemtime($filePath);
                if ($now - $fileTime > $maxAge) @unlink($filePath);
            }
        }
    }
}

$editorContent = '';
$isEmptyFile = true;
if ($filePath && file_exists($userStoragePath)) {
    $editorContent = file_get_contents($userStoragePath);
    $isEmptyFile = isTrulyEmptyFile($editorContent);
}


function latex_sanitize_output(string $text, string $workDir, string $baseName): string {
    if ($text === '') return $text;
    $dirVariants = array_unique(array_filter([
        $workDir,
        str_replace('\\', '/', $workDir),
        rtrim($workDir, DIRECTORY_SEPARATOR),
        rtrim(str_replace('\\', '/', $workDir), '/'),
    ]));
    foreach ($dirVariants as $dir) {
        $text = str_replace($dir . DIRECTORY_SEPARATOR, '', $text);
        $text = str_replace($dir . '/', '', $text);
        $text = str_replace($dir, '', $text);
    }
    $text = preg_replace_callback('#/(?:var|tmp|home|usr|opt|media|mnt|run|dev/shm)(?:/[^\s\)\]"\']*)+#', function ($m) {
        $parts = preg_split('#/#', trim($m[0], '/'));
        return end($parts) ?: '***';
    }, $text);
    $text = preg_replace('#/(?:[^/\s\)\]"\']+/)+([^/\s\)\]"\']+\.(?:tex|log|aux|sty|cls|pdf|bib|fls|fdb_latexmk|synctex\.gz))#', '$1', $text);
    $text = preg_replace('#[A-Za-z]:\\\\[^\s\)\]"\']+#', '***', $text);
    $text = preg_replace('#\b' . preg_quote($baseName, '#') . '\b#', 'document', $text);
    return $text;
}

function latex_is_noise_line(string $line): bool {
    if ($line === '') return true;
    if (preg_match("/^Running '/i", $line)) return true;
    if (preg_match("/^Rule '/i", $line)) return true;
    if (preg_match('/^Latexmk: (Errors, so I did not complete|Log file says|Examining|Getting log file|All targets|Summary of warnings|Sometimes|Nothing to do|Using bibtex)/i', $line)) return true;
    if (preg_match('/^Collected error summary/i', $line)) return true;
    if (preg_match('/^lualatex: (Command for|Run of rule)/i', $line)) return true;
    if (preg_match('/^Refer to .*\.log/i', $line)) return true;
    if (preg_match('/^See the .*\.log/i', $line)) return true;
    if (preg_match('/^Output written on/i', $line)) return true;
    if (preg_match('/^Transcript written on/i', $line)) return true;
    if (preg_match('/^luaotfload \| db :/i', $line)) return true;
    return false;
}

function latex_is_error_line(string $line): bool {
    if (latex_is_noise_line($line)) return false;
    if (preg_match('/^! /', $line)) return true;
    if (preg_match('/^l\.\d+/i', $line)) return true;
    if (preg_match('/:\d+:\s/', $line)) return true;
    if (preg_match('/^! LaTeX Error:/i', $line)) return true;
    if (preg_match('/^LaTeX Error:/i', $line)) return true;
    if (preg_match('/Package .+ Error:/i', $line)) return true;
    if (preg_match('/^Undefined control sequence/i', $line)) return true;
    if (preg_match('/^Emergency stop/i', $line)) return true;
    if (preg_match('/^Missing .* inserted/i', $line)) return true;
    if (preg_match('/not found\.?/i', $line)) return true;
    if (preg_match('/Fatal error/i', $line)) return true;
    if (preg_match('/^Runaway argument/i', $line)) return true;
    if (preg_match('/luaotfload.*FATAL/i', $line)) return true;
    if (preg_match('/no writeable cache path/i', $line)) return true;
    if (preg_match('/^kpathsea:/i', $line)) return true;
    if (preg_match('/lstat\(.*\) failed/i', $line)) return true;
    if (preg_match("/Can't get directory of program name/i", $line)) return true;
    return false;
}

function latex_collect_error_lines(string $text): array {
    $lines = [];
    foreach (explode("\n", $text) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || latex_is_noise_line($trimmed)) continue;
        if (latex_is_error_line($trimmed)) $lines[] = $trimmed;
    }
    return $lines;
}

function latex_extract_errors(string $logContent, array $outputLines, string $workDir, string $baseName): string {
    $lines = latex_collect_error_lines($logContent);
    if (empty($lines)) {
        $lines = latex_collect_error_lines(implode("\n", $outputLines));
    }
    if (empty($lines)) {
        $fallback = [];
        foreach ($outputLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || latex_is_noise_line($trimmed)) continue;
            $fallback[] = $trimmed;
        }
        $lines = array_slice($fallback, -8);
    }
    if (empty($lines)) {
        $lines = ['LaTeX compilation failed. Check document syntax.'];
    }
    $lines = array_values(array_unique($lines));
    return latex_sanitize_output(implode("\n", $lines), $workDir, $baseName);
}

function latex_needs_lualatex(string $latex): bool {
    return (bool)preg_match('/\\\\usepackage(?:\[[^\]]*\])?\{(?:fontspec|polyglossia|luaotfload|unicode-math)\}/', $latex);
}

function latex_respond_compile_error(string $errorText, bool $savePdf): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $errorText]);
    exit;
}

if (isset($_POST['synctex_query'])) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    header('Content-Type: application/json; charset=utf-8');
    if (!getenv('PY_SIDECAR_URL')) { echo json_encode(['ok' => false]); exit; }
    require_once dirname(__DIR__, 6) . '/lib/py_sidecar.php';
    $fields = ['id' => (string)($_POST['synctex_id'] ?? ''), 'mode' => ($_POST['mode'] ?? '') === 'edit' ? 'edit' : 'view'];
    foreach (['line', 'col', 'page'] as $k) if (isset($_POST[$k])) $fields[$k] = (int)$_POST[$k];
    foreach (['x', 'y'] as $k) if (isset($_POST[$k])) $fields[$k] = (float)$_POST[$k];
    $res = py_sidecar('synctex', $fields, null, null, 30);
    echo json_encode(is_array($res['body']) ? $res['body'] : ['ok' => false]);
    exit;
}

if (isset($_POST['synctex_pdf'])) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (!getenv('PY_SIDECAR_URL')) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'sidecar unavailable']);
        exit;
    }
    require_once dirname(__DIR__, 6) . '/lib/py_sidecar.php';
    $sid = (string)($_POST['synctex_id'] ?? '');
    $res = py_sidecar_raw('synctex/pdf', ['id' => $sid], 30);
    if (!$res['ok'] || stripos($res['content_type'], 'pdf') === false) {
        http_response_code($res['code'] >= 400 ? $res['code'] : 404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'pdf unavailable']);
        exit;
    }
    header('Content-Type: application/pdf');
    if (!empty($res['headers']['x-synctex-id'])) header('X-Synctex-Id: ' . $res['headers']['x-synctex-id']);
    echo $res['raw'];
    exit;
}

if (isset($_POST['latex'])) {
    $latex = $_POST['latex'];
    $savePdf = isset($_POST['save_pdf']) && $filePath;
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (!getenv('PY_SIDECAR_URL')) {
        latex_respond_compile_error('LaTeX compile sidecar is not configured.', $savePdf);
    }
    require_once dirname(__DIR__, 6) . '/lib/py_sidecar.php';
    $engine = latex_needs_lualatex($latex) ? 'lualatex' : 'pdflatex';
    if ($savePdf) {
        $sidecar = py_sidecar('latex', ['latex' => $latex, 'engine' => $engine, 'synctex' => '1', 'format' => 'json'], null, null, 300);
        $body = is_array($sidecar['body']) ? $sidecar['body'] : [];
        if (empty($body['success']) || empty($body['pdf'])) {
            latex_respond_compile_error((string)($body['output'] ?? '') !== '' ? (string)$body['output'] : ($sidecar['error'] ?: 'Compilation failed'), true);
        }
        $pdfContent = base64_decode((string)$body['pdf']);
        $pdfFilename = pathinfo($filePath, PATHINFO_FILENAME) . '.pdf';
        $pdfSavePath = dirname($userStoragePath) . "/" . $pdfFilename;
        $pdfDir = dirname($pdfSavePath);
        if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);
        $bytes = file_put_contents($pdfSavePath, $pdfContent);
        clearstatcache(true, $pdfSavePath);
        if ($bytes !== false && is_file($pdfSavePath) && filesize($pdfSavePath) > 0) {
            if (function_exists('get_session_email') && function_exists('invalidate_file_tree') && $root === 'main') {
                $email = get_session_email();
                if ($email) invalidate_file_tree($email);
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'pdf_saved' => true, 'pdf_path' => dirname($filePath) . "/" . $pdfFilename, 'pdf_name' => $pdfFilename, 'size' => filesize($pdfSavePath), 'message' => 'PDF saved successfully', 'synctex_id' => (string)($body['synctex_id'] ?? '')]);
        } else {
            if (is_file($pdfSavePath)) @unlink($pdfSavePath);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to save PDF file']);
        }
        exit;
    }
    $res = py_sidecar_raw('latex', ['latex' => $latex, 'engine' => $engine, 'synctex' => '1', 'format' => 'raw'], 300);
    if (!$res['ok'] || stripos($res['content_type'], 'pdf') === false) {
        $err = $res['error'] ?: 'Compilation failed';
        if ($res['raw'] !== '') {
            $decoded = json_decode($res['raw'], true);
            if (is_array($decoded) && !empty($decoded['output'])) $err = (string)$decoded['output'];
            elseif (is_array($decoded) && empty($decoded['success']) && !empty($decoded['error'])) $err = (string)$decoded['error'];
        }
        latex_respond_compile_error($err, false);
    }
    header('Content-Type: application/pdf');
    if (!empty($res['headers']['x-synctex-id'])) header('X-Synctex-Id: ' . $res['headers']['x-synctex-id']);
    echo $res['raw'];
    exit;
}

if (isset($_POST['save']) && isset($_POST['content']) && $filePath) {
    $content = $_POST['content'];
    $fullPath = $userStoragePath;
    $dir = dirname($fullPath);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $success = file_put_contents($fullPath, $content);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success,'file_path' => $filePath,'file_size' => $success ? filesize($fullPath) : 0,'user' => $currentUser]);
    exit;
}

if (isset($_POST['autosave']) && isset($_POST['content']) && $filePath) {
    $content = $_POST['content'];
    $fullPath = $userStoragePath;
    $dir = dirname($fullPath);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $success = file_put_contents($fullPath, $content);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success,'autosaved' => true,'timestamp' => date('H:i:s')]);
    exit;
}

function isMobileDevice() {
    return preg_match("/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i", $_SERVER['HTTP_USER_AGENT']);
}

function isTablet() {
    return preg_match("/(ipad|android(?!.*mobile)|tablet)/i", $_SERVER['HTTP_USER_AGENT']);
}

$isMobile = isMobileDevice();
$isTablet = isTablet();


$translations = cloud_load_translation_map(__DIR__);


$t = $translations[$lang] ?? $translations['en'];

$cssVariables = [
    'dark' => ['--bg-color' => '#1e1e1e','--panel-bg' => '#252526','--border-color' => '#3c3c3c','--text-color' => '#cccccc','--btn-bg' => '#3a3d41','--scrollbar-bg' => '#2e2e2e','--scrollbar-thumb' => '#555555','--code-bg' => '#1e1e1e'],
    'light' => ['--bg-color' => '#ffffff','--panel-bg' => '#f5f5f5','--border-color' => '#dddddd','--text-color' => '#333333','--btn-bg' => '#e9ecef','--scrollbar-bg' => '#f1f1f1','--scrollbar-thumb' => '#c1c1c1','--code-bg' => '#f8f9fa']
];
$currentTheme = $cssVariables[$theme] ?? $cssVariables['dark'];
$accentColor = urldecode($color);
require __DIR__ . '/../views/page.php';
