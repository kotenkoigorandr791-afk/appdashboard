<?php
declare(strict_types=1);

require_once __DIR__ . '/ai_code_security.php';
require_once __DIR__ . '/cloud_sandbox.php';

function cloud_ai_text_slice(string $text, int $start, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($text, $start, $length) : substr($text, $start, $length);
}

function cloud_ai_latex_needs_lualatex(string $latex): bool
{
    return (bool)preg_match('/\\\\usepackage(?:\[[^\]]*\])?\{(?:fontspec|polyglossia|luaotfload|unicode-math)\}/', $latex);
}

function cloud_ai_prepare_latex_for_lualatex(string $latex): string
{
    return cloud_ai_latex_escape_texttt($latex);
}

function cloud_ai_prepare_latex_for_pdflatex(string $latex): string
{
    $latex = cloud_ai_latex_escape_texttt($latex);
    $latex = cloud_ai_latex_replace_unicode_text_symbols($latex);
    if (!preg_match('/\\\\usepackage(?:\[[^\]]*\])?\{fontspec|polyglossia|luaotfload|unicode-math\}/', $latex)
        && preg_match('/\\\\usepackage(?:\[[^\]]*\])?\{babel\}/', $latex)
        && preg_match('/[А-Яа-яЁёІіЇїЄєЎў]/u', $latex)
    ) {
        if (!preg_match('/\\\\usepackage\[utf8\]\{inputenc\}/', $latex)) {
            $latex = cloud_ai_latex_insert_after_documentclass($latex, "\\usepackage[utf8]{inputenc}\n");
        }
        if (!preg_match('/\\\\usepackage\[T2A\]\{fontenc\}/', $latex)) {
            $latex = cloud_ai_latex_insert_after_documentclass($latex, "\\usepackage[T2A]{fontenc}\n");
        }
    }
    return $latex;
}

function cloud_ai_latex_escape_texttt(string $latex): string
{
    return preg_replace_callback('/\\\\texttt\{([^{}]*)\}/u', static function (array $m): string {
        $body = preg_replace('/(?<!\\\\)([_#%&])/', '\\\\$1', $m[1]);
        return '\\texttt{' . $body . '}';
    }, $latex) ?? $latex;
}

function cloud_ai_latex_replace_unicode_text_symbols(string $latex): string
{
    return strtr($latex, [
        '→' => '$\\to$',
        '←' => '$\\leftarrow$',
        '⇒' => '$\\Rightarrow$',
        '≤' => '$\\le$',
        '≥' => '$\\ge$',
        '≠' => '$\\ne$',
    ]);
}

function cloud_ai_latex_insert_after_documentclass(string $latex, string $insert): string
{
    if (preg_match('/(\\\\documentclass(?:\[[^\]]*\])?\{[^}]+\}\s*\r?\n)/', $latex, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($latex, 0, $pos) . $insert . substr($latex, $pos);
    }
    return $insert . $latex;
}

function cloud_ai_sanitize_latex_output(string $text, string $dir, string $base): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    foreach (array_filter([$dir, str_replace('\\', '/', $dir), rtrim($dir, DIRECTORY_SEPARATOR)]) as $path) {
        $text = str_replace($path . DIRECTORY_SEPARATOR, '', $text);
        $text = str_replace($path . '/', '', $text);
        $text = str_replace($path, '', $text);
    }
    $text = preg_replace('#/(?:tmp|var|home|mnt|media|run|dev/shm)(?:/[^\s:)\]"\']*)+#', '[server-temp-file]', $text);
    $text = preg_replace('#\b' . preg_quote($base, '#') . '\b#', 'document', $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    return trim($text);
}

function cloud_ai_latex_exec(string $cmd, string $dir, int &$code): array
{
    try {
        $result = cloud_sandbox_exec($dir, $cmd, null, 90, true);
        $code = (int)($result['exitCode'] ?? 1);
        $out = (string)($result['output'] ?? '');
        return $out === '' ? [] : preg_split('/\r\n|\r|\n/', $out);
    } catch (Throwable $e) {
        $code = 1;
        return [$e->getMessage()];
    }
}

function cloud_ai_compile_latex(string $latex): array
{
    $latexmk = '/usr/bin/latexmk';
    $lualatex = '/usr/bin/lualatex';
    $pdflatex = '/usr/bin/pdflatex';
    if (!is_executable($lualatex) && !is_executable($pdflatex)) {
        return ['ok' => false, 'success' => false, 'error' => 'LaTeX compiler not available', 'output' => ''];
    }
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cloud_ai_latex_' . bin2hex(random_bytes(6));
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'success' => false, 'error' => 'Cannot create temp directory', 'output' => ''];
    }
    $cacheDir = $dir . DIRECTORY_SEPARATOR . '.texmf-var';
    @mkdir($cacheDir, 0775, true);
    $base = 'input_' . uniqid('', true);
    $tex = $dir . DIRECTORY_SEPARATOR . $base . '.tex';
    $pdf = $dir . DIRECTORY_SEPARATOR . $base . '.pdf';
    $log = $dir . DIRECTORY_SEPARATOR . $base . '.log';
    $needsLua = cloud_ai_latex_needs_lualatex($latex);
    $source = $needsLua ? cloud_ai_prepare_latex_for_lualatex($latex) : cloud_ai_prepare_latex_for_pdflatex($latex);
    file_put_contents($tex, $source);
    $output = [];
    $code = 1;
    $env = 'HOME=' . escapeshellarg($cacheDir) . ' TEXMFVAR=' . escapeshellarg($cacheDir) . ' TEXMFCACHE=' . escapeshellarg($cacheDir);
    if (!$needsLua && is_executable($pdflatex)) {
        $cmd = $env . ' ' . escapeshellarg($pdflatex) . ' -interaction=nonstopmode -halt-on-error -file-line-error -output-directory=' . escapeshellarg($dir) . ' ' . escapeshellarg($tex);
        $output = cloud_ai_latex_exec($cmd, $dir, $code);
    } elseif ($needsLua && is_executable($latexmk) && is_executable($lualatex)) {
        $rule = escapeshellarg('$lualatex="/usr/bin/lualatex %O %S";');
        $cmd = $env . ' ' . escapeshellarg($latexmk) . ' -e ' . $rule . ' -lualatex -interaction=nonstopmode -halt-on-error -file-line-error -output-directory=' . escapeshellarg($dir) . ' ' . escapeshellarg($tex);
        $output = cloud_ai_latex_exec($cmd, $dir, $code);
    } elseif ($needsLua && is_executable($lualatex)) {
        $cmd = $env . ' ' . escapeshellarg($lualatex) . ' -interaction=nonstopmode -halt-on-error -file-line-error -output-directory=' . escapeshellarg($dir) . ' ' . escapeshellarg($tex);
        $output = cloud_ai_latex_exec($cmd, $dir, $code);
    } elseif (is_executable($pdflatex)) {
        $cmd = escapeshellarg($pdflatex) . ' -interaction=nonstopmode -halt-on-error -output-directory=' . escapeshellarg($dir) . ' ' . escapeshellarg($tex);
        $output = cloud_ai_latex_exec($cmd, $dir, $code);
    } else {
        $output = [$needsLua ? 'LuaLaTeX is required for this document but is not available.' : 'pdfLaTeX is required for this document but is not available.'];
        $code = 127;
    }
    $logText = is_file($log) ? (string)file_get_contents($log) : implode("\n", $output);
    $success = $code === 0 && is_file($pdf) && filesize($pdf) > 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
    $errLines = [];
    if (!$success && $logText !== '') {
        foreach (preg_split('/\r\n|\r|\n/', $logText) as $line) {
            if (preg_match('/^! |^l\.[0-9]+|Error|Fatal|Undefined/', $line)) {
                $errLines[] = $line;
            }
        }
    }
    $summary = $success
        ? 'LaTeX compiled successfully.'
        : (implode("\n", array_slice($errLines, 0, 40)) ?: implode("\n", array_slice($output, -40)));
    $summary = cloud_ai_sanitize_latex_output($summary, $dir, $base);
    $cleanOutput = cloud_ai_sanitize_latex_output($logText !== '' ? $logText : implode("\n", $output), $dir, $base);
    return [
        'ok' => true,
        'success' => $success,
        'kind' => 'latex',
        'output' => cloud_ai_text_slice($cleanOutput, 0, 120000),
        'error' => $success ? '' : cloud_ai_text_slice($summary, 0, 16000),
    ];
}

function cloud_ai_compile_latex_to_pdf_file(string $latex, string $pdfTarget): array
{
    $latexmk = '/usr/bin/latexmk';
    $lualatex = '/usr/bin/lualatex';
    $pdflatex = '/usr/bin/pdflatex';
    if (!is_executable($lualatex) && !is_executable($pdflatex)) {
        return ['ok' => false, 'success' => false, 'error' => 'LaTeX compiler not available', 'output' => ''];
    }
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cloud_ai_latex_' . bin2hex(random_bytes(6));
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'success' => false, 'error' => 'Cannot create temp directory', 'output' => ''];
    }
    $cacheDir = $dir . DIRECTORY_SEPARATOR . '.texmf-var';
    @mkdir($cacheDir, 0775, true);
    $base = 'input_' . uniqid('', true);
    $tex = $dir . DIRECTORY_SEPARATOR . $base . '.tex';
    $pdf = $dir . DIRECTORY_SEPARATOR . $base . '.pdf';
    $log = $dir . DIRECTORY_SEPARATOR . $base . '.log';
    $needsLua = cloud_ai_latex_needs_lualatex($latex);
    $source = $needsLua ? cloud_ai_prepare_latex_for_lualatex($latex) : cloud_ai_prepare_latex_for_pdflatex($latex);
    file_put_contents($tex, $source);
    $output = [];
    $code = 1;
    $env = 'HOME=' . escapeshellarg($cacheDir) . ' TEXMFVAR=' . escapeshellarg($cacheDir) . ' TEXMFCACHE=' . escapeshellarg($cacheDir);
    if (!$needsLua && is_executable($pdflatex)) {
        $cmd = $env . ' ' . escapeshellarg($pdflatex) . ' -interaction=nonstopmode -halt-on-error -file-line-error -output-directory=' . escapeshellarg($dir) . ' ' . escapeshellarg($tex);
        $output = cloud_ai_latex_exec($cmd, $dir, $code);
    } elseif ($needsLua && is_executable($latexmk) && is_executable($lualatex)) {
        $rule = escapeshellarg('$lualatex="/usr/bin/lualatex %O %S";');
        $cmd = $env . ' ' . escapeshellarg($latexmk) . ' -e ' . $rule . ' -lualatex -interaction=nonstopmode -halt-on-error -file-line-error -output-directory=' . escapeshellarg($dir) . ' ' . escapeshellarg($tex);
        $output = cloud_ai_latex_exec($cmd, $dir, $code);
    } elseif ($needsLua && is_executable($lualatex)) {
        $cmd = $env . ' ' . escapeshellarg($lualatex) . ' -interaction=nonstopmode -halt-on-error -file-line-error -output-directory=' . escapeshellarg($dir) . ' ' . escapeshellarg($tex);
        $output = cloud_ai_latex_exec($cmd, $dir, $code);
    } else {
        $output = [$needsLua ? 'LuaLaTeX is required for this document but is not available.' : 'pdfLaTeX is required for this document but is not available.'];
        $code = 127;
    }
    $logText = is_file($log) ? (string)file_get_contents($log) : implode("\n", $output);
    $success = $code === 0 && is_file($pdf) && filesize($pdf) > 0;
    if ($success) {
        $targetDir = dirname($pdfTarget);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $success = false;
            $logText .= "\nFailed to create PDF output directory.";
        } elseif (!copy($pdf, $pdfTarget)) {
            $success = false;
            $logText .= "\nFailed to save PDF file.";
        }
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
    $errLines = [];
    if (!$success && $logText !== '') {
        foreach (preg_split('/\r\n|\r|\n/', $logText) as $line) {
            if (preg_match('/^! |^l\.[0-9]+|Error|Fatal|Undefined|Failed/', $line)) {
                $errLines[] = $line;
            }
        }
    }
    $summary = $success
        ? 'LaTeX PDF saved.'
        : (implode("\n", array_slice($errLines, 0, 40)) ?: implode("\n", array_slice($output, -40)));
    $summary = cloud_ai_sanitize_latex_output($summary, $dir, $base);
    $cleanOutput = cloud_ai_sanitize_latex_output($logText !== '' ? $logText : implode("\n", $output), $dir, $base);
    return [
        'ok' => true,
        'success' => $success,
        'kind' => 'latex',
        'output' => cloud_ai_text_slice($cleanOutput, 0, 120000),
        'error' => $success ? '' : cloud_ai_text_slice($summary, 0, 16000),
        'pdf_size' => $success && is_file($pdfTarget) ? (int)filesize($pdfTarget) : 0,
    ];
}

function cloud_ai_compile_code(string $language, string $fileContent, string $filePath, string $rootKey, ?string $resolvedPath = null): array
{
    if (!defined('CLOUD_COMPILE_AS_LIB')) {
        define('CLOUD_COMPILE_AS_LIB', true);
    }
    try {
        require_once __DIR__ . '/../compile.php';
    } catch (Throwable $e) {
        return ['ok' => false, 'success' => false, 'error' => 'Compiler module failed to load: ' . $e->getMessage(), 'output' => ''];
    }
    $lang = strtolower($language);
    $supported = ['java', 'c', 'cpp', 'py', 'm'];
    if (!in_array($lang, $supported, true)) {
        return ['ok' => false, 'success' => false, 'error' => "Unsupported language: $language", 'output' => ''];
    }
    try {
        $resolved = $resolvedPath ?: resolve_cloud_compile_path($filePath, $rootKey);
        $result = compileAndRun($lang, $fileContent, basename($filePath), null, $resolved);
    } catch (Throwable $e) {
        return ['ok' => false, 'success' => false, 'error' => 'Compilation failed: ' . $e->getMessage(), 'output' => ''];
    }
    $out = trim((string)($result['output'] ?? ''));
    $err = trim((string)($result['error'] ?? ''));
    $combined = trim($out . ($err !== '' ? "\n" . $err : ''));
    return [
        'ok' => true,
        'success' => !empty($result['success']),
        'kind' => $lang,
        'output' => cloud_ai_text_slice($combined, 0, 120000),
        'error' => empty($result['success']) ? cloud_ai_text_slice($combined !== '' ? $combined : 'Compilation failed', 0, 16000) : '',
        'fileChanges' => $result['fileChanges'] ?? null,
    ];
}

function cloud_ai_compile_check(string $path, ?string $content, string $rootKey): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($content === null) {
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            return ['ok' => false, 'error' => $resolved['error'] ?? 'File not found'];
        }
        $read = cloud_ai_read_target_content($resolved['target'], $resolved['user'], $resolved['root']);
        if (!($read['ok'] ?? false) || !empty($read['binary'])) {
            return ['ok' => false, 'error' => 'Cannot read file as text'];
        }
        $content = (string)$read['content'];
    }
    if (in_array($ext, ['tex', 'latex'], true)) {
        return cloud_ai_compile_latex($content);
    }
    $langMap = ['py' => 'py', 'cpp' => 'cpp', 'c' => 'c', 'java' => 'java', 'm' => 'm'];
    if (!isset($langMap[$ext])) {
        return ['ok' => false, 'error' => 'Unsupported file type for compile: .' . $ext];
    }
    $sec = cloud_ai_scan_code_security($content, $ext);
    if (!empty($sec['blocked'])) {
        return [
            'ok' => true,
            'success' => false,
            'blocked_security' => true,
            'violations' => $sec['violations'],
            'error' => $sec['error'],
        ];
    }
    $resolvedPath = null;
    if (function_exists('cloud_ai_resolve_user_path_for_write')) {
        $resolved = cloud_ai_resolve_user_path_for_write($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) return ['ok' => false, 'error' => $resolved['error'] ?? 'Cannot resolve file path'];
        $resolvedPath = (string)$resolved['target'];
    }
    return cloud_ai_compile_code($langMap[$ext], $content, $path, $rootKey !== '' ? $rootKey : 'main', $resolvedPath);
}
