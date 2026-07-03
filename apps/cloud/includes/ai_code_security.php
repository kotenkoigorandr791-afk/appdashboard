<?php
declare(strict_types=1);

function cloud_ai_code_security_lang(string $ext): ?string
{
    $map = ['py' => 'py', 'c' => 'c', 'cpp' => 'cpp', 'cc' => 'cpp', 'cxx' => 'cpp', 'java' => 'java', 'h' => 'c', 'hpp' => 'cpp'];
    return $map[strtolower($ext)] ?? null;
}

function cloud_ai_code_security_patterns(string $lang): array
{
    if ($lang === 'py') {
        return [
            '/\bos\.system\s*\(/i' => 'os.system',
            '/\bsubprocess\b/i' => 'subprocess',
            '/\b__import__\s*\(/i' => '__import__',
            '/\beval\s*\(/i' => 'eval',
            '/\bexec\s*\(/i' => 'exec',
            '/\bcompile\s*\(/i' => 'compile (dynamic)',
            '/\bshutil\.rmtree\s*\(/i' => 'shutil.rmtree',
            '/\bos\.remove\s*\(/i' => 'os.remove',
            '/\bos\.unlink\s*\(/i' => 'os.unlink',
            '/\bos\.kill\s*\(/i' => 'os.kill',
            '/\bos\.killpg\s*\(/i' => 'os.killpg',
            '/\bsignal\.(SIGKILL|SIGTERM|SIGABRT)\b/i' => 'signal kill',
            '/\bctypes\b/i' => 'ctypes',
            '/\bsocket\.socket\s*\(/i' => 'raw socket',
            '/\bpopen\s*\(/i' => 'popen',
        ];
    }
    if ($lang === 'c' || $lang === 'cpp') {
        return [
            '/\bsystem\s*\(/i' => 'system()',
            '/\bpopen\s*\(/i' => 'popen()',
            '/\bexec[lv][pe]?\s*\(/i' => 'exec*()',
            '/\bfork\s*\(/i' => 'fork()',
            '/\bremove\s*\(/i' => 'remove()',
            '/\bunlink\s*\(/i' => 'unlink()',
            '/\bkill\s*\(/i' => 'kill()',
            '/\bchmod\s*\(/i' => 'chmod()',
            '/\bchown\s*\(/i' => 'chown()',
        ];
    }
    if ($lang === 'java') {
        return [
            '/Runtime\.getRuntime\s*\(\s*\)\s*\.exec/i' => 'Runtime.exec',
            '/\bProcessBuilder\b/i' => 'ProcessBuilder',
            '/\bSystem\.exit\s*\(/i' => 'System.exit',
        ];
    }
    return [];
}

function cloud_ai_scan_code_security(string $content, string $ext): array
{
    $lang = cloud_ai_code_security_lang($ext);
    if ($lang === null) {
        return ['ok' => true, 'blocked' => false, 'violations' => []];
    }
    $patterns = cloud_ai_code_security_patterns($lang);
    $violations = [];
    $lines = explode("\n", str_replace("\r\n", "\n", $content));
    foreach ($lines as $num => $line) {
        foreach ($patterns as $re => $label) {
            if (preg_match($re, $line)) {
                $violations[] = ['line' => $num + 1, 'pattern' => $label, 'text' => mb_substr(trim($line), 0, 200)];
            }
        }
    }
    if ($violations !== []) {
        return [
            'ok' => true,
            'blocked' => true,
            'violations' => $violations,
            'error' => 'Blocked: code contains disallowed operations for server safety (process control, shell, or destructive filesystem access). Remove them before compile.',
        ];
    }
    return ['ok' => true, 'blocked' => false, 'violations' => []];
}
