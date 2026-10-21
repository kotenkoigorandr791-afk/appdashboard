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

function latex_get_user_storage_root(string $user, string $rootKey): string {
    return appdata_user_root_dir('cloud', $user, $rootKey, false);
}

function latex_path_is_within(string $path, string $root): bool {
    $pathReal = realpath($path);
    $rootReal = realpath($root);
    if ($pathReal === false || $rootReal === false) return false;
    if ($pathReal === $rootReal) return true;
    return str_starts_with($pathReal, $rootReal . DIRECTORY_SEPARATOR);
}

function latex_build_texinputs(string $compileDir, string $userRootDir): string {
    $parts = ['.'];
    $compileReal = realpath($compileDir);
    $rootReal = realpath($userRootDir);
    if ($compileReal !== false) $parts[] = str_replace('\\', '/', $compileReal);
    if ($rootReal !== false) $parts[] = str_replace('\\', '/', $rootReal) . '//';
    return implode(':', $parts) . ':';
}

function latex_shell_env(string $cacheDir, ?string $texInputs = null): string {
    $cacheArg = escapeshellarg($cacheDir);
    $homeArg = escapeshellarg($cacheDir);
    $path = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
    $env = 'export PATH=' . escapeshellarg($path) . " HOME=$homeArg TEXMFVAR=$cacheArg TEXMFCACHE=$cacheArg";
    if ($texInputs !== null && $texInputs !== '') {
        $env .= ' TEXINPUTS=' . escapeshellarg($texInputs);
        $env .= ' BIBINPUTS=' . escapeshellarg($texInputs);
    }
    return $env;
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

function latex_insert_after_documentclass(string $latex, string $insert): string {
    if (preg_match('/(\\\\documentclass(?:\[[^\]]*\])?\{[^}]+\}\s*\r?\n)/', $latex, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($latex, 0, $pos) . $insert . substr($latex, $pos);
    }
    return $insert . $latex;
}

function latex_ensure_cache_dir(string $dir): string {
    $cacheDir = $dir . DIRECTORY_SEPARATOR . '.texmf-var';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    return $cacheDir;
}

function latex_prepare_source_for_lualatex(string $latex): string {
    $latex = latex_escape_texttt($latex);
    $latex = preg_replace('/\\usepackage(\[[^\]]*\])?\{caption\}\s*/i', '', $latex) ?? $latex;
    $latex = preg_replace('/(\\usepackage\[)[^\]]*\bpolish\b([^\]]*\]\{babel\})/i', '$1english$2', $latex) ?? $latex;
    if (preg_match('/\\\\usepackage(\[[^\]]*\])?\{fontspec\}/', $latex)) return $latex;
    $latex = preg_replace('/^\s*\\\\usepackage(\[[^\]]*\])?\{inputenc\}\s*\r?\n/m', '', $latex);
    $latex = preg_replace('/^\s*\\\\usepackage(\[[^\]]*\])?\{fontenc\}\s*\r?\n/m', '', $latex);
    if (!preg_match('/\\\\usepackage(\[([^\]]*)\])?\{babel\}/', $latex, $babelMatch)) return $latex;
    $cyrillicLangs = ['russian', 'ukrainian', 'belarusian'];
    $defaultLang = 'english';
    $needsCyrillic = false;
    if (!empty($babelMatch[2])) {
        foreach (explode(',', $babelMatch[2]) as $opt) {
            $opt = trim($opt);
            if (in_array($opt, $cyrillicLangs, true)) {
                $needsCyrillic = true;
                $defaultLang = $opt;
                break;
            }
        }
    }
    if (!$needsCyrillic) return $latex;
    $latex = preg_replace('/^\s*\\\\usepackage(\[[^\]]*\])?\{babel\}\s*\r?\n/m', '', $latex);
    $preamble = "\\usepackage{fontspec}\n\\usepackage{polyglossia}\n\\setdefaultlanguage{" . $defaultLang . "}\n\\setmainfont{DejaVu Serif}\n\\newfontfamily\\cyrillicfont{DejaVu Serif}\n";
    return latex_insert_after_documentclass($latex, $preamble);
}

function latex_needs_lualatex(string $latex): bool {
    return (bool)preg_match('/\\\\usepackage(?:\[[^\]]*\])?\{(?:fontspec|polyglossia|luaotfload|unicode-math)\}/', $latex);
}

function latex_prepare_source_for_pdflatex(string $latex): string {
    $latex = latex_escape_texttt($latex);
    $latex = preg_replace('/\\usepackage(\[[^\]]*\])?\{caption\}\s*/i', '', $latex) ?? $latex;
    $latex = preg_replace('/(\\usepackage\[)[^\]]*\bpolish\b([^\]]*\]\{babel\})/i', '$1english$2', $latex) ?? $latex;
    $latex = latex_replace_unicode_text_symbols($latex);
    if (!latex_needs_lualatex($latex)
        && preg_match('/\\\\usepackage(?:\[[^\]]*\])?\{babel\}/', $latex)
        && preg_match('/[А-Яа-яЁёІіЇїЄєЎў]/u', $latex)
    ) {
        if (!preg_match('/\\\\usepackage\[utf8\]\{inputenc\}/', $latex)) {
            $latex = latex_insert_after_documentclass($latex, "\\usepackage[utf8]{inputenc}\n");
        }
        if (!preg_match('/\\\\usepackage\[T2A\]\{fontenc\}/', $latex)) {
            $latex = latex_insert_after_documentclass($latex, "\\usepackage[T2A]{fontenc}\n");
        }
    }
    return $latex;
}

function latex_escape_texttt(string $latex): string {
    return preg_replace_callback('/\\\\texttt\{([^{}]*)\}/u', static function (array $m): string {
        $body = preg_replace('/(?<!\\\\)([_#%&])/', '\\\\$1', $m[1]);
        return '\\texttt{' . $body . '}';
    }, $latex) ?? $latex;
}

function latex_replace_unicode_text_symbols(string $latex): string {
    return strtr($latex, [
        '→' => '$\\to$',
        '←' => '$\\leftarrow$',
        '⇒' => '$\\Rightarrow$',
        '≤' => '$\\le$',
        '≥' => '$\\ge$',
        '≠' => '$\\ne$',
    ]);
}

function latex_run_compile(string $texfilePath, string $dir, ?string $texInputs = null, bool $needsLua = false): array {
    $dir = rtrim($dir, DIRECTORY_SEPARATOR);
    if ($dir === '' || !is_dir($dir)) {
        return ['output' => ['Invalid compile directory.'], 'return_code' => 1, 'engine' => 'invalid-dir'];
    }
    $texBasename = basename($texfilePath);
    $dirArg = escapeshellarg($dir);
    $texArg = escapeshellarg($texBasename);
    $cacheDir = latex_ensure_cache_dir($dir);
    $env = latex_shell_env($cacheDir, $texInputs);
    $latexmkPath = '/usr/bin/latexmk';
    $lualatexPath = '/usr/bin/lualatex';
    $pdflatexPath = '/usr/bin/pdflatex';
    $output = [];
    $ret = 1;
    if (!$needsLua && is_executable($pdflatexPath)) {
        $cmd = "$env && cd $dirArg && $pdflatexPath -interaction=nonstopmode -halt-on-error -file-line-error $texArg 2>&1";
        exec($cmd, $output, $ret);
        return ['output' => $output, 'return_code' => $ret, 'engine' => 'pdflatex'];
    }
    if ($needsLua && is_executable($latexmkPath)) {
        $lualatexRule = escapeshellarg('$lualatex="/usr/bin/lualatex %O %S";');
        $cmd = "$env && cd $dirArg && $latexmkPath -e $lualatexRule -lualatex -interaction=nonstopmode -file-line-error $texArg 2>&1";
        exec($cmd, $output, $ret);
        return ['output' => $output, 'return_code' => $ret, 'engine' => 'latexmk-lualatex'];
    }
    if ($needsLua && !is_executable($lualatexPath)) {
        return ['output' => ['LuaLaTeX is required for this document but is not available.'], 'return_code' => 127, 'engine' => 'missing'];
    }
    if (!$needsLua && !is_executable($pdflatexPath)) {
        return ['output' => ['pdfLaTeX is required for this document but is not available.'], 'return_code' => 127, 'engine' => 'missing'];
    }
    $cmd = "$env && cd $dirArg && $lualatexPath -interaction=nonstopmode -file-line-error $texArg 2>&1";
    for ($i = 0; $i < 2; $i++) {
        $output = [];
        exec($cmd, $output, $ret);
        if ($ret !== 0) break;
    }
    return ['output' => $output, 'return_code' => $ret, 'engine' => 'lualatex'];
}

function latex_cleanup_compile_files(string $dir, string $baseName, bool $includePdf = false): void {
    $tempExtensions = ['.tex', '.aux', '.log', '.out', '.toc', '.lof', '.lot', '.nav', '.snm', '.vrb', '.bbl', '.blg', '.bcf', '.run.xml', '.synctex.gz', '.synctex', '.fls', '.fdb_latexmk', '.idx', '.ilg', '.ind', '.xdv'];
    if ($includePdf) $tempExtensions[] = '.pdf';
    foreach ($tempExtensions as $ext) {
        $tempFile = $dir . DIRECTORY_SEPARATOR . $baseName . $ext;
        if (file_exists($tempFile) && is_file($tempFile)) @unlink($tempFile);
    }
    foreach ([$dir . DIRECTORY_SEPARATOR . $baseName . '-blx.bib', $dir . DIRECTORY_SEPARATOR . 'texput.log'] as $extraFile) {
        if (file_exists($extraFile) && is_file($extraFile)) @unlink($extraFile);
    }
    $cacheDir = $dir . DIRECTORY_SEPARATOR . '.texmf-var';
    if (is_dir($cacheDir)) {
        $files = @scandir($cacheDir);
        if (is_array($files)) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $path = $cacheDir . DIRECTORY_SEPARATOR . $file;
                if (is_file($path)) @unlink($path);
            }
        }
        @rmdir($cacheDir);
    }
    $latexmkPath = '/usr/bin/latexmk';
    if (is_executable($latexmkPath)) {
        $dirArg = escapeshellarg($dir);
        $texArg = escapeshellarg($baseName . '.tex');
        $env = latex_shell_env($dir . DIRECTORY_SEPARATOR . '.texmf-var', null);
        @exec("$env && cd $dirArg && $latexmkPath -c $texArg 2>/dev/null");
    }
}

function latex_remove_compile_dir(string $dir): void {
    if ($dir === '' || !is_dir($dir)) return;
    $items = @scandir($dir);
    if (!is_array($items)) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) latex_remove_compile_dir($path);
        else @unlink($path);
    }
    @rmdir($dir);
}

function latex_respond_compile_error(string $errorText, bool $savePdf): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $errorText]);
    exit;
}

if (isset($_POST['latex'])) {
    $latex = $_POST['latex'];
    $savePdf = isset($_POST['save_pdf']) && $filePath;
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $compileId = uniqid();
    $userRootDir = latex_get_user_storage_root($currentUser, $root);
    $compileInUserDir = false;
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'latex_editor_' . md5(session_id() . $compileId);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $baseName = 'input_' . $compileId;
    $texfilePath = $dir . DIRECTORY_SEPARATOR . $baseName . '.tex';
    $pdffilePath = $dir . DIRECTORY_SEPARATOR . $baseName . '.pdf';
    $logfilePath = $dir . DIRECTORY_SEPARATOR . $baseName . '.log';
    $texInputs = latex_build_texinputs($dir, $userRootDir);
    $needsLua = latex_needs_lualatex($latex);
    file_put_contents($texfilePath, $needsLua ? latex_prepare_source_for_lualatex($latex) : latex_prepare_source_for_pdflatex($latex));
    $compileResult = latex_run_compile($texfilePath, $dir, $texInputs, $needsLua);
    $output = $compileResult['output'];
    $ret = $compileResult['return_code'];
    $logContent = file_exists($logfilePath) ? file_get_contents($logfilePath) : '';
    $compileOk = $ret === 0 && file_exists($pdffilePath) && filesize($pdffilePath) > 0;
    if (!$compileOk) {
        $errorText = latex_extract_errors($logContent, $output, $dir, $baseName);
        latex_cleanup_compile_files($dir, $baseName, true);
        if (!$compileInUserDir) latex_remove_compile_dir($dir);
        latex_respond_compile_error($errorText, $savePdf);
    }
    $pdfContent = file_get_contents($pdffilePath);
    latex_cleanup_compile_files($dir, $baseName, true);
    if (!$compileInUserDir) latex_remove_compile_dir($dir);
    if ($savePdf) {
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
            echo json_encode(['success' => true, 'pdf_saved' => true, 'pdf_path' => dirname($filePath) . "/" . $pdfFilename, 'pdf_name' => $pdfFilename, 'size' => filesize($pdfSavePath), 'message' => 'PDF saved successfully']);
        } else {
            if (is_file($pdfSavePath)) @unlink($pdfSavePath);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to save PDF file']);
        }
        exit;
    }
    header('Content-Type: application/pdf');
    echo $pdfContent;
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
