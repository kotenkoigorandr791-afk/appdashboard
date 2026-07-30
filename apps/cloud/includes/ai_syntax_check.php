<?php
declare(strict_types=1);

require_once __DIR__ . '/ai_code_security.php';
require_once __DIR__ . '/cloud_sandbox.php';

function cloud_ai_syntax_temp_dir(): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cloud_ai_syntax_' . bin2hex(random_bytes(5));
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create temp directory');
    }
    return $dir;
}

function cloud_ai_syntax_cleanup(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    @rmdir($dir);
}

function cloud_ai_syntax_run(string $cmd, string $cwd, int $timeout = 25): array
{
    try {
        return cloud_sandbox_exec($cwd, $cmd, null, $timeout, true);
    } catch (Throwable $e) {
        return ['success' => false, 'output' => '', 'error' => $e->getMessage(), 'exitCode' => -1];
    }
}

function cloud_ai_syntax_check_python(string $content, string $baseName): array
{
    $dir = cloud_ai_syntax_temp_dir();
    $file = $dir . DIRECTORY_SEPARATOR . $baseName . '.py';
    file_put_contents($file, $content);
    $py = is_executable('/usr/bin/python3') ? '/usr/bin/python3' : 'python3';
    $res = cloud_ai_syntax_run(escapeshellarg($py) . ' -m py_compile ' . escapeshellarg(basename($file)), $dir);
    cloud_ai_syntax_cleanup($dir);
    return [
        'ok' => true,
        'success' => $res['success'],
        'kind' => 'python',
        'output' => mb_substr($res['output'], 0, 16000),
        'error' => $res['success'] ? '' : mb_substr($res['error'] ?: $res['output'] ?: 'Python syntax error', 0, 16000),
    ];
}

function cloud_ai_syntax_check_c(string $content, string $baseName, bool $isCpp): array
{
    $dir = cloud_ai_syntax_temp_dir();
    $ext = $isCpp ? 'cpp' : 'c';
    $file = $dir . DIRECTORY_SEPARATOR . $baseName . '.' . $ext;
    file_put_contents($file, $content);
    $compiler = $isCpp ? (is_executable('/usr/bin/g++') ? '/usr/bin/g++' : 'g++') : (is_executable('/usr/bin/gcc') ? '/usr/bin/gcc' : 'gcc');
    $cmd = escapeshellarg($compiler) . ' -fsyntax-only -x ' . ($isCpp ? 'c++' : 'c') . ' ' . escapeshellarg(basename($file));
    $res = cloud_ai_syntax_run($cmd, $dir);
    cloud_ai_syntax_cleanup($dir);
    return [
        'ok' => true,
        'success' => $res['success'],
        'kind' => $isCpp ? 'cpp' : 'c',
        'output' => mb_substr($res['output'], 0, 16000),
        'error' => $res['success'] ? '' : mb_substr($res['error'] ?: $res['output'] ?: 'Syntax error', 0, 16000),
    ];
}

function cloud_ai_syntax_check_java(string $content, string $fileName): array
{
    $dir = cloud_ai_syntax_temp_dir();
    $safeName = preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.java$/', $fileName) ? $fileName : 'Main.java';
    $file = $dir . DIRECTORY_SEPARATOR . $safeName;
    file_put_contents($file, $content);
    $javac = is_executable('/usr/bin/javac') ? '/usr/bin/javac' : 'javac';
    $res = cloud_ai_syntax_run(escapeshellarg($javac) . ' ' . escapeshellarg($safeName), $dir);
    cloud_ai_syntax_cleanup($dir);
    return [
        'ok' => true,
        'success' => $res['success'],
        'kind' => 'java',
        'output' => mb_substr($res['output'], 0, 16000),
        'error' => $res['success'] ? '' : mb_substr($res['error'] ?: $res['output'] ?: 'Java compile error', 0, 16000),
    ];
}

function cloud_ai_syntax_check_latex(string $content): array
{
    require_once __DIR__ . '/ai_compile_check.php';
    return cloud_ai_compile_latex($content);
}

function cloud_ai_verify_syntax(string $path, ?string $content, string $rootKey): array
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
    $base = preg_replace('/[^A-Za-z0-9_]/', '_', pathinfo($path, PATHINFO_FILENAME)) ?: 'input';
    if (in_array($ext, ['tex', 'latex'], true)) {
        $r = cloud_ai_syntax_check_latex($content);
        return $r + ['syntax_only' => false];
    }
    if ($ext === 'py') {
        $r = cloud_ai_syntax_check_python($content, $base);
        return $r + ['syntax_only' => true];
    }
    if ($ext === 'c') {
        $r = cloud_ai_syntax_check_c($content, $base, false);
        return $r + ['syntax_only' => true];
    }
    if (in_array($ext, ['cpp', 'cc', 'cxx', 'hpp'], true)) {
        $r = cloud_ai_syntax_check_c($content, $base, true);
        return $r + ['syntax_only' => true];
    }
    if ($ext === 'java') {
        $r = cloud_ai_syntax_check_java($content, basename($path));
        return $r + ['syntax_only' => true];
    }
    return ['ok' => false, 'error' => 'Unsupported file type for syntax check: .' . $ext];
}
