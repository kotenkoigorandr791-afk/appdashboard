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

function cloud_ai_latex_sidecar(string $latex): array
{
    if (!getenv('PY_SIDECAR_URL')) {
        return ['pdf' => '', 'output' => 'LaTeX compile sidecar is not configured.'];
    }
    require_once dirname(__DIR__, 3) . '/lib/py_sidecar.php';
    $lua = cloud_ai_latex_needs_lualatex($latex);
    $res = py_sidecar('latex', ['latex' => $lua ? cloud_ai_prepare_latex_for_lualatex($latex) : cloud_ai_prepare_latex_for_pdflatex($latex), 'engine' => $lua ? 'lualatex' : 'pdflatex'], null, null, 300);
    $body = is_array($res['body']) ? $res['body'] : [];
    $ok = !empty($body['success']) && !empty($body['pdf']);
    return ['pdf' => $ok ? (string)base64_decode((string)$body['pdf']) : '', 'output' => (string)($body['output'] ?? '') !== '' ? (string)$body['output'] : (string)$res['error']];
}

function cloud_ai_latex_result(array $run, string $okMessage, array $extra = []): array
{
    $success = $run['pdf'] !== '';
    $errLines = [];
    if (!$success) {
        foreach (preg_split('/\r\n|\r|\n/', $run['output']) as $line) {
            if (preg_match('/^! |^l\.[0-9]+|Error|Fatal|Undefined|Failed/', $line)) $errLines[] = $line;
        }
    }
    $summary = $success ? $okMessage : (implode("\n", array_slice($errLines, 0, 40)) ?: implode("\n", array_slice(preg_split('/\r\n|\r|\n/', $run['output']), -40)));
    return [
        'ok' => true,
        'success' => $success,
        'kind' => 'latex',
        'output' => cloud_ai_text_slice($run['output'], 0, 120000),
        'error' => $success ? '' : cloud_ai_text_slice($summary, 0, 16000),
    ] + $extra;
}

function cloud_ai_compile_latex(string $latex): array
{
    return cloud_ai_latex_result(cloud_ai_latex_sidecar($latex), 'LaTeX compiled successfully.');
}

function cloud_ai_compile_latex_to_pdf_file(string $latex, string $pdfTarget): array
{
    $run = cloud_ai_latex_sidecar($latex);
    $size = 0;
    if ($run['pdf'] !== '') {
        $targetDir = dirname($pdfTarget);
        if ((!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) || file_put_contents($pdfTarget, $run['pdf']) === false) {
            $run = ['pdf' => '', 'output' => $run['output'] . "\nFailed to save PDF file."];
        } else {
            $size = (int)filesize($pdfTarget);
        }
    }
    return cloud_ai_latex_result($run, 'LaTeX PDF saved.', ['pdf_size' => $size]);
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
