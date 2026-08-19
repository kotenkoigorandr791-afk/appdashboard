<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/ai_content_extract.php';
require_once dirname(__DIR__, 2) . '/includes/ai_agent_prompts.php';
require_once dirname(__DIR__, 2) . '/includes/ai_compile_check.php';
require_once dirname(__DIR__, 2) . '/includes/ai_code_security.php';
require_once dirname(__DIR__, 2) . '/includes/ai_syntax_check.php';
require_once dirname(__DIR__, 2) . '/includes/cloud_sandbox.php';
require_once __DIR__ . '/ai_tools_speech.php';

const CLOUD_AI_FETCH_MAX_BYTES = 20971520;
const CLOUD_AI_FETCH_MAX_CHARS = 120000;
const CLOUD_AI_BINARY_READ_MAX_BYTES = 262144;
const CLOUD_AI_COMMAND_ALLOWLIST = [
    'php', 'node', 'npm', 'npx', 'python', 'python3', 'pytest', 'composer', 'git',
    'rg', 'grep', 'find', 'ls', 'cat', 'head', 'tail', 'wc', 'sed', 'awk', 'cut', 'tr', 'sort', 'uniq', 'diff',
    'sh', 'bash', 'true', 'false', 'test', 'echo', 'printf', 'mkdir', 'cp', 'mv', 'touch', 'chmod',
    'gcc', 'g++', 'cc', 'c++', 'clang', 'clang++', 'make', 'cmake', 'javac', 'java', 'rustc', 'go',
    'dotnet', 'lua', 'perl', 'ruby', 'which', 'basename', 'dirname', 'realpath', 'pwd', 'env', 'base64',
    'md5sum', 'sha256sum', 'stat', 'tee', 'xargs', 'timeout',
];
const CLOUD_AI_TEXT_EXTENSIONS = [
    'txt','md','markdown','json','jsonl','csv','tsv','xml','html','htm','css','scss','sass','less',
    'js','mjs','cjs','ts','tsx','jsx','vue','php','py','rb','go','java','kt','kts','swift','rs',
    'c','h','cc','cpp','cxx','hpp','hh','hxx','ipp','ino','cs','m','mm','sh','bash','zsh','fish',
    'bat','cmd','ps1','psm1','sql','yaml','yml','toml','ini','cfg','conf','env','properties',
    'gradle','dockerfile','makefile','mk','cmake','log','rst','tex','latex','bib','lua','pl','pm',
    'r','dart','scala','sc','clj','cljs','ex','exs','erl','hrl','fs','fsi','fsx','vb','vbs',
    'asm','s','pas','pp','proto','graphql','gql','lock','gitignore','gitattributes','editorconfig'
];

function cloud_ai_normalize_root_path(string &$rootKey, string &$path): void
{
    $map = [
        'clientsync' => 'clientsync',
        '.clientsync' => 'clientsync',
        'documents' => 'documents',
        '.documents' => 'documents',
        'photos' => 'photos',
        '.photos' => 'photos',
        'pdf' => 'pdf',
        '.pdf' => 'pdf',
        'archive' => 'archive',
        '.archive' => 'archive',
        'trash' => 'trash',
        '.trash' => 'trash',
        'temp' => 'temp',
        '.temp_files' => 'temp',
    ];
    $path = ltrim(str_replace('\\', '/', $path), '/');
    foreach ($map as $prefix => $mappedRoot) {
        if ($path === $prefix) {
            $rootKey = $mappedRoot;
            $path = '';
            return;
        }
        if (str_starts_with($path, $prefix . '/')) {
            $rootKey = $mappedRoot;
            $path = substr($path, strlen($prefix) + 1);
            return;
        }
    }
}

function cloud_ai_release_session_lock(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function cloud_ai_path_looks_text(string $target, string $mime = ''): bool
{
    if ($mime !== '' && is_text_mime($mime)) {
        return true;
    }
    if (filesize($target) === 0) {
        return true;
    }
    $name = strtolower(basename($target));
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    if (in_array($ext, CLOUD_AI_TEXT_EXTENSIONS, true) || in_array($name, CLOUD_AI_TEXT_EXTENSIONS, true)) {
        return true;
    }
    $fh = @fopen($target, 'rb');
    if (!$fh) {
        return false;
    }
    $chunk = (string)fread($fh, 8192);
    fclose($fh);
    return $chunk === '' || !str_contains($chunk, "\0");
}

function cloud_ai_resolve_user_file(string $path, ?string $rootKey = null): array
{
    $user = get_current_user_from_sessions();
    if ($user === '') {
        return ['ok' => false, 'error' => 'not authenticated', 'code' => 401];
    }
    $rootKey = $rootKey ?: get_requested_root_key();
    cloud_ai_normalize_root_path($rootKey, $path);
    $base = user_root($user, $rootKey);
    $safePath = safe_path($path);
    $target = $base . '/' . $safePath;
    if (!file_exists($target)) {
        foreach (['main', 'documents', 'clientsync'] as $fallbackRoot) {
            if ($fallbackRoot === $rootKey) {
                continue;
            }
            $fallbackPath = $safePath;
            cloud_ai_normalize_root_path($fallbackRoot, $fallbackPath);
            $fallbackTarget = user_root($user, $fallbackRoot) . '/' . $fallbackPath;
            if (file_exists($fallbackTarget)) {
                return ['ok' => true, 'user' => $user, 'root' => $fallbackRoot, 'path' => $fallbackPath, 'target' => $fallbackTarget];
            }
        }
        return ['ok' => false, 'error' => 'File not found', 'code' => 404];
    }
    return ['ok' => true, 'user' => $user, 'root' => $rootKey, 'path' => $safePath, 'target' => $target];
}

function cloud_ai_resolve_user_path_for_write(string $path, ?string $rootKey = null): array
{
    $user = get_current_user_from_sessions();
    if ($user === '') {
        return ['ok' => false, 'error' => 'not authenticated', 'code' => 401];
    }
    $rootKey = $rootKey ?: get_requested_root_key();
    cloud_ai_normalize_root_path($rootKey, $path);
    $base = user_root($user, $rootKey);
    $safePath = safe_path($path);
    $target = $base . '/' . $safePath;
    if (!file_exists($target)) {
        foreach (['main', 'documents', 'clientsync'] as $fallbackRoot) {
            if ($fallbackRoot === $rootKey) {
                continue;
            }
            $fallbackPath = $safePath;
            cloud_ai_normalize_root_path($fallbackRoot, $fallbackPath);
            $fallbackTarget = user_root($user, $fallbackRoot) . '/' . $fallbackPath;
            if (file_exists($fallbackTarget)) {
                return ['ok' => true, 'user' => $user, 'root' => $fallbackRoot, 'path' => $fallbackPath, 'target' => $fallbackTarget, 'exists' => true];
            }
        }
    }
    return ['ok' => true, 'user' => $user, 'root' => $rootKey, 'path' => $safePath, 'target' => $target, 'exists' => file_exists($target)];
}

function cloud_ai_persist_file_content(array $resolved, string $content): bool
{
    $dir = dirname($resolved['target']);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    if (is_documents_root($resolved['root'])) {
        return file_put_contents($resolved['target'], encrypt_documents_content($content, $resolved['user'])) !== false;
    }
    return file_put_contents($resolved['target'], $content) !== false;
}

function cloud_ai_sync_file_tree_after_write(array $resolved): void
{
    $email = function_exists('get_session_email') ? get_session_email() : null;
    if (!$email || ($resolved['root'] ?? '') !== 'main') {
        return;
    }
    if (!function_exists('invalidate_file_tree')) {
        return;
    }
    invalidate_file_tree($email);
}

function cloud_ai_remove_path_recursive(string $path): bool
{
    if (!file_exists($path)) {
        return true;
    }
    if (is_file($path) || is_link($path)) {
        return @unlink($path);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $ok = $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        if (!$ok) {
            return false;
        }
    }
    return @rmdir($path);
}

function cloud_ai_path_stat(array $resolved): array
{
    $target = $resolved['target'];
    $isDir = is_dir($target);
    return [
        'ok' => true,
        'path' => $resolved['path'],
        'name' => basename($resolved['path']),
        'type' => $isDir ? 'dir' : 'file',
        'exists' => true,
        'size' => $isDir ? null : (@filesize($target) ?: 0),
        'mtime' => @filemtime($target) ?: 0,
        'mime' => $isDir ? 'inode/directory' : (mime_content_type($target) ?: 'application/octet-stream'),
        'is_readable' => is_readable($target),
        'is_writable' => is_writable($target),
    ];
}

function cloud_ai_regex_delimiter(string $pattern, string $flags = ''): string
{
    return '~' . str_replace('~', '\~', $pattern) . '~' . preg_replace('/[^imsuxADSUXJu]/', '', $flags);
}

function cloud_ai_search_by_regex(array $paths, string $pattern, string $flags, string $rootKey, int $maxResults = 80): array
{
    if ($pattern === '') {
        return ['ok' => false, 'error' => 'pattern is required'];
    }
    $regex = cloud_ai_regex_delimiter($pattern, $flags);
    set_error_handler(static function() {});
    $isValid = @preg_match($regex, '');
    restore_error_handler();
    if ($isValid === false) {
        return ['ok' => false, 'error' => 'Invalid regular expression'];
    }
    $matches = [];
    foreach ($paths as $rawPath) {
        $path = trim((string)$rawPath);
        if ($path === '') {
            continue;
        }
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            continue;
        }
        $read = cloud_ai_read_target_content($resolved['target'], $resolved['user'], $resolved['root']);
        if (!($read['ok'] ?? false) || (!empty($read['binary']) && empty($read['extracted']))) {
            continue;
        }
        $lines = explode("\n", str_replace("\r\n", "\n", (string)$read['content']));
        foreach ($lines as $i => $line) {
            if (!preg_match($regex, $line, $m)) {
                continue;
            }
            $matches[] = [
                'path' => $resolved['path'],
                'line' => $i + 1,
                'text' => mb_substr($line, 0, 500),
                'match' => mb_substr((string)($m[0] ?? ''), 0, 200),
            ];
            if (count($matches) >= $maxResults) {
                break 2;
            }
        }
    }
    return ['ok' => true, 'pattern' => $pattern, 'flags' => $flags, 'match_count' => count($matches), 'matches' => $matches];
}

function cloud_ai_set_json_path(array &$data, string $path, mixed $value): void
{
    $parts = array_values(array_filter(explode('.', $path), static fn($p) => $p !== ''));
    if ($parts === []) {
        return;
    }
    $ref =& $data;
    foreach ($parts as $idx => $part) {
        $last = $idx === count($parts) - 1;
        if ($last) {
            $ref[$part] = $value;
            return;
        }
        if (!isset($ref[$part]) || !is_array($ref[$part])) {
            $ref[$part] = [];
        }
        $ref =& $ref[$part];
    }
}

function cloud_ai_remove_json_path(array &$data, string $path): void
{
    $parts = array_values(array_filter(explode('.', $path), static fn($p) => $p !== ''));
    if ($parts === []) {
        return;
    }
    $ref =& $data;
    foreach ($parts as $idx => $part) {
        $last = $idx === count($parts) - 1;
        if (!is_array($ref) || !array_key_exists($part, $ref)) {
            return;
        }
        if ($last) {
            unset($ref[$part]);
            return;
        }
        $ref =& $ref[$part];
    }
}

function cloud_ai_build_diff(string $left, string $right, string $leftLabel, string $rightLabel, int $maxChars = 32000): array
{
    $tmpA = tempnam(sys_get_temp_dir(), 'aidiff_a_');
    $tmpB = tempnam(sys_get_temp_dir(), 'aidiff_b_');
    if ($tmpA === false || $tmpB === false) {
        return ['ok' => false, 'error' => 'Temp file error'];
    }
    file_put_contents($tmpA, $left);
    file_put_contents($tmpB, $right);
    $cmd = sprintf(
        'diff -u --label %s --label %s %s %s 2>&1',
        escapeshellarg($leftLabel),
        escapeshellarg($rightLabel),
        escapeshellarg($tmpA),
        escapeshellarg($tmpB)
    );
    exec($cmd, $output, $code);
    @unlink($tmpA);
    @unlink($tmpB);
    $diff = implode("\n", $output);
    if ($diff === '') {
        $diff = "--- {$leftLabel}\n+++ {$rightLabel}\n";
    }
    if (strlen($diff) > $maxChars) {
        $diff = substr($diff, 0, $maxChars) . "\n[... diff truncated ...]";
    }
    return ['ok' => true, 'changed' => $left !== $right, 'diff' => $diff, 'exit_code' => $code];
}

function cloud_ai_fetch_url(string $url, int $maxBytes = CLOUD_AI_FETCH_MAX_BYTES): array
{
    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['ok' => false, 'error' => 'Only http/https URLs are allowed'];
    }
    $headers = '';
    $body = '';
    $status = 0;
    $contentType = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 CloudIDE-AI-Agent/1.0',
            CURLOPT_HEADER => true,
            CURLOPT_MAXREDIRS => 5,
        ]);
        $raw = curl_exec($ch);
        if (is_string($raw)) {
            $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headers = substr($raw, 0, $headerSize);
            $body = substr($raw, $headerSize, $maxBytes);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        }
        curl_close($ch);
    }
    if ($body === '') {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'header' => "User-Agent: Mozilla/5.0 CloudIDE-AI-Agent/1.0\r\n",
                'follow_location' => 1,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx, 0, $maxBytes);
        if (is_string($raw)) {
            $body = $raw;
            $headersList = $http_response_header ?? [];
            $headers = implode("\n", $headersList);
            foreach ($headersList as $h) {
                if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) {
                    $status = (int)$m[1];
                }
                if (stripos($h, 'Content-Type:') === 0) {
                    $contentType = trim(substr($h, 13));
                }
            }
        }
    }
    if ($body === '' && $headers === '') {
        return ['ok' => false, 'error' => 'Failed to fetch URL'];
    }
    return ['ok' => true, 'status' => $status ?: 200, 'content_type' => $contentType ?: 'application/octet-stream', 'body' => $body, 'headers' => $headers];
}

function cloud_ai_workspace_root(string $user, string $rootKey, string $cwd = ''): string
{
    $base = user_root($user, $rootKey);
    $safeCwd = $cwd === '' ? '' : safe_path($cwd);
    return $safeCwd === '' ? $base : $base . '/' . $safeCwd;
}

function cloud_ai_normalize_command_argv($command): array
{
    if (is_string($command)) {
        $command = trim($command);
        if ($command === '') {
            return [];
        }
        if (preg_match_all('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\']*)\'|(\S+)/', $command, $m, PREG_SET_ORDER)) {
            $parts = [];
            foreach ($m as $row) {
                $parts[] = $row[1] !== '' ? stripcslashes($row[1]) : ($row[2] !== '' ? $row[2] : $row[3]);
            }
            return array_values(array_filter($parts, static fn($p) => $p !== ''));
        }
        return preg_split('/\s+/', $command) ?: [];
    }
    if (!is_array($command)) {
        return [];
    }
    $parts = [];
    foreach ($command as $part) {
        if (is_array($part)) {
            continue;
        }
        $s = trim((string)$part);
        if ($s !== '') {
            $parts[] = $s;
        }
    }
    return $parts;
}

function cloud_ai_command_binary_name(string $binary): string
{
    $binary = trim($binary);
    $binary = preg_replace('#^./+#', '', $binary) ?? $binary;
    if (str_contains($binary, '/')) {
        $binary = basename($binary);
    }
    return strtolower($binary);
}

function cloud_ai_run_command(array|string $command, string $user, string $rootKey, string $cwd = '', int $timeout = 20): array
{
    $command = cloud_ai_normalize_command_argv($command);
    if ($command === []) {
        return ['ok' => false, 'error' => 'command is required', 'allowed' => CLOUD_AI_COMMAND_ALLOWLIST];
    }
    $binary = cloud_ai_command_binary_name((string)($command[0] ?? ''));
    if ($binary === '' || !in_array($binary, CLOUD_AI_COMMAND_ALLOWLIST, true)) {
        return [
            'ok' => false,
            'error' => 'Command is not in allowlist: ' . ($binary !== '' ? $binary : '(empty)') . '. Use compile_and_check/run_script, or an allowed binary.',
            'allowed' => CLOUD_AI_COMMAND_ALLOWLIST,
        ];
    }
    $command[0] = $binary;
    $cwdPath = cloud_ai_workspace_root($user, $rootKey, $cwd);
    if (!is_dir($cwdPath)) {
        return ['ok' => false, 'error' => 'cwd not found'];
    }
    $timeout = max(1, min(60, $timeout));
    $cmd = implode(' ', array_map(static fn($part) => escapeshellarg((string)$part), $command));
    try {
        $result = cloud_sandbox_exec($cwdPath, $cmd, null, $timeout, true);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
    $code = (int)($result['exitCode'] ?? 1);
    $stdout = (string)($result['output'] ?? '');
    if (strlen($stdout) > 32000) {
        $stdout = substr($stdout, 0, 32000) . "\n[... output truncated ...]";
    }
    return ['ok' => true, 'command' => $command, 'cwd' => $cwd, 'exit_code' => $code, 'output' => $stdout, 'success' => $code === 0];
}

function cloud_ai_run_script(string $language, string $script, string $user, string $rootKey, string $cwd = '', int $timeout = 20): array
{
    $language = strtolower(trim($language));
    $extMap = ['python' => 'py', 'python3' => 'py', 'py' => 'py', 'node' => 'js', 'javascript' => 'js', 'js' => 'js', 'php' => 'php', 'sh' => 'sh', 'bash' => 'sh', 'shell' => 'sh'];
    $cmdMap = ['python' => 'python3', 'python3' => 'python3', 'py' => 'python3', 'node' => 'node', 'javascript' => 'node', 'js' => 'node', 'php' => 'php', 'sh' => 'sh', 'bash' => 'sh', 'shell' => 'sh'];
    if (!isset($cmdMap[$language])) {
        return ['ok' => false, 'error' => 'Unsupported script language'];
    }
    if (trim($script) === '') {
        return ['ok' => false, 'error' => 'script is required'];
    }
    $cwdPath = cloud_ai_workspace_root($user, $rootKey, $cwd);
    if (!is_dir($cwdPath)) {
        if ($cwd !== '' && @mkdir($cwdPath, 0775, true)) {
            // created
        }
        if (!is_dir($cwdPath)) {
            return ['ok' => false, 'error' => 'cwd not found'];
        }
    }
    if (!is_writable($cwdPath)) {
        return ['ok' => false, 'error' => 'cwd is not writable'];
    }
    $scriptName = 'ai_tmp_' . bin2hex(random_bytes(6)) . '.' . $extMap[$language];
    $scriptPath = $cwdPath . '/' . $scriptName;
    if (file_put_contents($scriptPath, $script) === false) {
        return ['ok' => false, 'error' => 'Failed to write script'];
    }
    @chmod($scriptPath, 0664);
    try {
        $result = cloud_sandbox_exec($cwdPath, escapeshellarg($cmdMap[$language]) . ' ' . escapeshellarg($scriptName), null, max(1, min(60, $timeout)), true);
    } catch (Throwable $e) {
        @unlink($scriptPath);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
    @unlink($scriptPath);
    $output = (string)($result['output'] ?? '');
    if (strlen($output) > 32000) {
        $output = substr($output, 0, 32000) . "\n[... output truncated ...]";
    }
    $code = (int)($result['exitCode'] ?? 1);
    return [
        'ok' => true,
        'language' => $language,
        'cwd' => $cwd,
        'exit_code' => $code,
        'output' => $output,
        'error' => $code === 0 ? '' : ($output !== '' ? $output : 'Script failed'),
        'success' => $code === 0,
    ];
}

function cloud_ai_binary_content_payload(string $bytes, string $mime, ?int $fullSize = null): array
{
    $size = $fullSize ?? strlen($bytes);
    $chunk = substr($bytes, 0, CLOUD_AI_BINARY_READ_MAX_BYTES);
    return [
        'ok' => true,
        'content' => "Binary file\nMIME: {$mime}\nSize: {$size} bytes\nBase64 first " . strlen($chunk) . " bytes:\n" . base64_encode($chunk),
        'binary' => true,
        'extracted' => true,
        'mime' => $mime,
        'size' => $size,
        'encoding' => 'base64',
        'truncated' => $size > strlen($chunk),
    ];
}

function cloud_ai_read_target_content(string $target, string $user, string $rootKey): array
{
    if (is_documents_root($rootKey)) {
        $raw = decrypt_documents_content((string)file_get_contents($target), $user);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($raw) ?: 'application/octet-stream';
        if ($mime === 'application/x-empty') {
            $mime = 'text/plain';
        }
        if (!is_text_mime($mime)) {
            $tmp = tempnam(sys_get_temp_dir(), 'ai_doc_');
            if ($tmp === false) {
                return ['ok' => false, 'error' => 'Temp file error'];
            }
            $ext = pathinfo($target, PATHINFO_EXTENSION);
            $tmpPath = $tmp . ($ext ? '.' . $ext : '');
            rename($tmp, $tmpPath);
            file_put_contents($tmpPath, $raw);
            $extracted = cloud_ai_extract_document_text($tmpPath);
            @unlink($tmpPath);
            if (!($extracted['ok'] ?? false)) {
                return cloud_ai_binary_content_payload($raw, $mime);
            }
            return ['ok' => true, 'content' => $extracted['content'], 'binary' => false, 'extracted' => true, 'format' => 'markdown'];
        }
        return ['ok' => true, 'content' => $raw, 'binary' => false];
    }

    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    if (cloud_ai_is_extractable_document_ext($ext)) {
        $extracted = cloud_ai_extract_document_text($target);
        if ($extracted['ok'] ?? false) {
            $extracted['binary'] = false;
            $extracted['extracted'] = true;
            $extracted['format'] = 'markdown';
            return $extracted;
        }
        $mime = mime_content_type($target) ?: 'application/octet-stream';
        $fh = @fopen($target, 'rb');
        if (!$fh) {
            return $extracted;
        }
        $chunk = (string)stream_get_contents($fh, CLOUD_AI_BINARY_READ_MAX_BYTES);
        fclose($fh);
        return cloud_ai_binary_content_payload($chunk, $mime, @filesize($target) ?: strlen($chunk));
    }

    $mime = mime_content_type($target) ?: 'application/octet-stream';
    if (!cloud_ai_path_looks_text($target, $mime)) {
        $fh = @fopen($target, 'rb');
        if (!$fh) {
            return ['ok' => false, 'error' => 'File is not readable'];
        }
        $chunk = (string)stream_get_contents($fh, CLOUD_AI_BINARY_READ_MAX_BYTES);
        fclose($fh);
        $size = @filesize($target) ?: strlen($chunk);
        return cloud_ai_binary_content_payload($chunk, $mime, $size);
    }
    $content = (string)file_get_contents($target);
    return ['ok' => true, 'content' => $content, 'binary' => false];
}

function cloud_ai_normalize_text(string $text): string
{
    return str_replace("\r\n", "\n", str_replace("\r", "\n", $text));
}

function cloud_ai_tool_text_content(array $resolved, array $args): array
{
    if (array_key_exists('base_content', $args) && is_string($args['base_content'])) {
        return [
            'ok' => true,
            'content' => cloud_ai_normalize_text($args['base_content']),
            'source' => 'base_content',
        ];
    }
    $read = cloud_ai_read_target_content($resolved['target'], $resolved['user'], $resolved['root']);
    if (!($read['ok'] ?? false)) {
        return $read;
    }
    if (!empty($read['binary'])) {
        return ['ok' => false, 'error' => 'Binary file cannot be edited as text'];
    }
    return [
        'ok' => true,
        'content' => cloud_ai_normalize_text((string)$read['content']),
        'source' => 'disk',
    ];
}

function cloud_ai_search_replace_text(string $content, string $old, string $new): array
{
    $content = cloud_ai_normalize_text($content);
    $old = cloud_ai_normalize_text($old);
    $new = cloud_ai_normalize_text($new);
    if ($old === '') {
        return ['ok' => false, 'error' => 'old_string is required'];
    }
    if (!str_contains($content, $old)) {
        $oldTrim = rtrim($old, " \t");
        if ($oldTrim !== $old && str_contains($content, $oldTrim)) {
            $old = $oldTrim;
            $new = rtrim($new, " \t");
        }
    }
    if (!str_contains($content, $old)) {
        return [
            'ok' => false,
            'error' => 'old_string not found',
            'hint' => 'Copy old_string exactly from read_file "content" (LF line endings). For LaTeX use doubled backslashes in JSON (e.g. \\\\author).',
            'content_length' => strlen($content),
            'old_string_length' => strlen($old),
            'old_string_preview' => mb_substr($old, 0, 160),
        ];
    }
    return [
        'ok' => true,
        'content' => $content,
        'updated' => str_replace($old, $new, $content),
        'replacements' => substr_count($content, $old),
    ];
}

function cloud_ai_read_file_slice(string $target, string $user, string $rootKey, int $offsetLine = 1, int $lineCount = 200, int $maxChars = 32000): array
{
    $read = cloud_ai_read_target_content($target, $user, $rootKey);
    if (!($read['ok'] ?? false)) {
        return $read;
    }
    if (!empty($read['binary']) && empty($read['extracted'])) {
        return ['ok' => false, 'error' => 'Binary file cannot be read as text slice'];
    }
    $full = str_replace("\r\n", "\n", (string)$read['content']);
    $lines = $full === '' ? [] : explode("\n", $full);
    $totalLines = count($lines);
    $offsetLine = max(1, $offsetLine);
    $lineCount = max(1, min(500, $lineCount));
    $startIdx = $offsetLine - 1;
    if ($startIdx >= $totalLines) {
        return [
            'ok' => true,
            'content' => '',
            'total_lines' => $totalLines,
            'total_bytes' => strlen($full),
            'offset_line' => $offsetLine,
            'line_count' => 0,
            'returned_lines' => 0,
            'truncated' => false,
            'hint' => 'Offset is past end of file.',
        ];
    }
    $slice = array_slice($lines, $startIdx, $lineCount);
    $content = implode("\n", $slice);
    $truncatedByLines = ($startIdx + count($slice)) < $totalLines;
    if (strlen($content) > $maxChars) {
        $content = substr($content, 0, $maxChars) . "\n\n[... slice truncated by max_chars ...]";
    }
    $nextLine = $startIdx + count($slice) + 1;
    $hint = $truncatedByLines
        ? "File has {$totalLines} lines. To read more, call read_file with offset_line={$nextLine}."
        : 'End of file reached.';
    return [
        'ok' => true,
        'content' => $content,
        'total_lines' => $totalLines,
        'total_bytes' => strlen($full),
        'offset_line' => $offsetLine,
        'line_count' => count($slice),
        'returned_lines' => count($slice),
        'truncated' => $truncatedByLines || strlen($full) > $maxChars,
        'hint' => $hint,
    ];
}

function cloud_ai_search_in_paths(array $paths, string $query, string $rootKey, int $maxResults = 80): array
{
    if ($query === '') {
        return ['ok' => false, 'error' => 'query is required'];
    }
    $matches = [];
    foreach ($paths as $rawPath) {
        $path = trim((string)$rawPath);
        if ($path === '') {
            continue;
        }
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            continue;
        }
        $read = cloud_ai_read_target_content($resolved['target'], $resolved['user'], $resolved['root']);
        if (!($read['ok'] ?? false) || (!empty($read['binary']) && empty($read['extracted']))) {
            continue;
        }
        $lines = explode("\n", str_replace("\r\n", "\n", (string)$read['content']));
        foreach ($lines as $i => $line) {
            if (!str_contains($line, $query)) {
                continue;
            }
            $matches[] = [
                'path' => $resolved['path'],
                'line' => $i + 1,
                'text' => mb_substr($line, 0, 500),
            ];
            if (count($matches) >= $maxResults) {
                break 2;
            }
        }
    }
    return ['ok' => true, 'query' => $query, 'match_count' => count($matches), 'matches' => $matches];
}

function cloud_ai_decode_search_url(string $url): string
{
    if ($url === '') {
        return '';
    }
    $parts = @parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
    if (!$parts || empty($parts['query'])) {
        return $url;
    }
    parse_str($parts['query'], $query);
    if (!empty($query['uddg'])) {
        return (string)$query['uddg'];
    }
    return $url;
}

function cloud_ai_searxng_base_url(): string
{
    require_once __DIR__ . '/../../../../db.php';
    return rtrim(getConfig('search_engine_url', ''), '/');
}

function cloud_ai_searxng_query(string $baseUrl, array $params, int $timeout = 20): ?array
{
    $url = $baseUrl . '/search?' . http_build_query($params);
    $body = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'AppDashboard/1.0',
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (is_string($resp) && $code >= 200 && $code < 400) {
            $body = $resp;
        }
    }
    if ($body === '') {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => $timeout,
            'header' => "User-Agent: AppDashboard/1.0\r\nAccept: application/json\r\n",
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        if (is_string($resp)) {
            $body = $resp;
        }
    }
    if ($body === '') return null;
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function cloud_ai_web_search(string $query, int $maxResults = 5): array
{
    $query = trim($query);
    if ($query === '') {
        return ['ok' => false, 'error' => 'query is required'];
    }
    $maxResults = max(1, min(10, $maxResults));
    $baseUrl = cloud_ai_searxng_base_url();
    if ($baseUrl === '') {
        return ['ok' => false, 'error' => 'Search engine URL not configured'];
    }
    $data = cloud_ai_searxng_query($baseUrl, [
        'q' => $query,
        'format' => 'json',
        'categories' => 'general',
    ]);
    if ($data === null) {
        return ['ok' => false, 'error' => 'Web search request failed'];
    }
    $results = [];
    foreach (($data['results'] ?? []) as $row) {
        if (!is_array($row)) continue;
        $title = trim((string)($row['title'] ?? ''));
        $link = trim((string)($row['url'] ?? ''));
        $snippet = trim((string)($row['content'] ?? ''));
        if ($title === '' || $link === '') continue;
        $results[] = ['title' => $title, 'url' => $link, 'snippet' => mb_substr($snippet, 0, 300)];
        if (count($results) >= $maxResults) break;
    }
    return ['ok' => true, 'query' => $query, 'count' => count($results), 'results' => $results];
}

function cloud_ai_image_search(string $query, int $maxResults = 8): array
{
    $query = trim($query);
    if ($query === '') {
        return ['ok' => false, 'error' => 'query is required'];
    }
    $maxResults = max(1, min(20, $maxResults));
    $baseUrl = cloud_ai_searxng_base_url();
    if ($baseUrl === '') {
        return ['ok' => false, 'error' => 'Search engine URL not configured'];
    }
    $data = cloud_ai_searxng_query($baseUrl, [
        'q' => $query,
        'format' => 'json',
        'categories' => 'images',
    ]);
    if ($data === null) {
        return ['ok' => false, 'error' => 'Image search request failed'];
    }
    $results = [];
    foreach (($data['results'] ?? []) as $row) {
        if (!is_array($row)) continue;
        $image = trim((string)($row['img_src'] ?? $row['url'] ?? ''));
        $thumb = trim((string)($row['thumbnail_src'] ?? $row['thumbnail'] ?? ''));
        $title = trim((string)($row['title'] ?? ''));
        $source = trim((string)($row['source'] ?? $row['url'] ?? ''));
        if ($image === '' && $thumb === '') continue;
        $results[] = ['title' => $title, 'url' => $image ?: $thumb, 'thumbnail' => $thumb ?: $image, 'source' => $source];
        if (count($results) >= $maxResults) break;
    }
    return ['ok' => true, 'query' => $query, 'count' => count($results), 'results' => $results];
}

if ($action === 'ai_agent_prompts') {
    $openPath = (string)($_GET['open_path'] ?? $_POST['open_path'] ?? '');
    $mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'agent');
    if ($mode === 'chat') {
        json_out([
            'ok' => true,
            'system' => cloud_ai_chat_system_prompt($openPath !== '' ? $openPath : null),
            'context' => cloud_ai_agent_file_context($openPath !== '' ? $openPath : null),
            'open_path' => $openPath,
            'mode' => 'chat',
        ]);
    } else {
        json_out(['ok' => true] + cloud_ai_agent_prompts_payload($openPath !== '' ? $openPath : null));
    }
}

if ($action === 'ai_compile_check') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    $path = (string)($_POST['path'] ?? '');
    $rootKey = (string)($_POST['root'] ?? '');
    $content = isset($_POST['content']) ? (string)$_POST['content'] : null;
    if ($path === '') {
        json_out(['ok' => false, 'error' => 'path is required'], 400);
    }
    cloud_ai_release_session_lock();
    $result = cloud_ai_compile_check($path, $content, $rootKey);
    if (!($result['ok'] ?? true) && isset($result['error']) && !isset($result['success'])) {
        json_out(['ok' => false, 'error' => $result['error']], 400);
    }
    json_out(['ok' => true, 'path' => $path] + $result);
}

if ($action === 'ai_extract_upload') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    if (empty($_FILES['file']) || !is_uploaded_file((string)($_FILES['file']['tmp_name'] ?? ''))) {
        json_out(['ok' => false, 'error' => 'file upload is required'], 400);
    }
    $upload = $_FILES['file'];
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_out(['ok' => false, 'error' => 'Upload failed'], 400);
    }
    $size = (int)($upload['size'] ?? 0);
    if ($size <= 0 || $size > CLOUD_AI_FETCH_MAX_BYTES) {
        json_out(['ok' => false, 'error' => 'File too large'], 400);
    }
    cloud_ai_release_session_lock();
    $name = basename((string)($upload['name'] ?? 'upload'));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!cloud_ai_is_extractable_document_ext($ext)) {
        json_out(['ok' => false, 'error' => 'Unsupported document type'], 400);
    }
    $tmpSrc = (string)$upload['tmp_name'];
    $tmpPath = $tmpSrc;
    if ($ext !== '' && !str_ends_with(strtolower($tmpSrc), '.' . $ext)) {
        $tmpCopy = tempnam(sys_get_temp_dir(), 'ai_up_');
        if ($tmpCopy === false) {
            json_out(['ok' => false, 'error' => 'Temp file error'], 500);
        }
        $tmpPath = $tmpCopy . '.' . $ext;
        if (!@rename($tmpCopy, $tmpPath)) {
            @unlink($tmpCopy);
            json_out(['ok' => false, 'error' => 'Temp file error'], 500);
        }
        if (!@copy($tmpSrc, $tmpPath)) {
            @unlink($tmpPath);
            json_out(['ok' => false, 'error' => 'Failed to stage upload'], 500);
        }
    }
    $extracted = cloud_ai_extract_document_text($tmpPath);
    if ($tmpPath !== $tmpSrc) {
        @unlink($tmpPath);
    }
    if (!($extracted['ok'] ?? false)) {
        json_out(['ok' => false, 'error' => $extracted['error'] ?? 'extract failed'], 400);
    }
    json_out([
        'ok' => true,
        'name' => $name,
        'content' => $extracted['content'],
        'extracted' => true,
        'format' => 'markdown',
    ]);
}

if ($action === 'ai_extract_text') {
    $path = (string)($_GET['path'] ?? $_POST['path'] ?? '');
    $rootKey = (string)($_GET['root'] ?? $_POST['root'] ?? '');
    $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
    if (!($resolved['ok'] ?? false)) {
        json_out(['ok' => false, 'error' => $resolved['error'] ?? 'error'], $resolved['code'] ?? 400);
    }
    cloud_ai_release_session_lock();
    $read = cloud_ai_read_target_content($resolved['target'], $resolved['user'], $resolved['root']);
    if (!($read['ok'] ?? false)) {
        json_out(['ok' => false, 'error' => $read['error'] ?? 'read failed'], 400);
    }
    json_out([
        'ok' => true,
        'path' => $resolved['path'],
        'content' => $read['content'],
        'extracted' => !empty($read['extracted']),
        'format' => !empty($read['extracted']) ? 'markdown' : 'text',
    ]);
}

if ($action === 'ai_agent_tool') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    }
    $tool = (string)($_POST['tool'] ?? '');
    $argsRaw = $_POST['args'] ?? '{}';
    $args = is_array($argsRaw) ? $argsRaw : json_decode((string)$argsRaw, true);
    if (!is_array($args)) {
        json_out(['ok' => false, 'error' => 'Invalid args'], 400);
    }
    $rootKey = (string)($_POST['root'] ?? $args['root'] ?? '');
    cloud_ai_release_session_lock();

    if ($tool === 'read_file') {
        $path = (string)($args['path'] ?? '');
        $offsetLine = (int)($args['offset_line'] ?? 1);
        $lineCount = (int)($args['line_count'] ?? 200);
        $maxChars = (int)($args['max_chars'] ?? 32000);
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        $slice = cloud_ai_read_file_slice($resolved['target'], $resolved['user'], $resolved['root'], $offsetLine, $lineCount, $maxChars);
        if (!($slice['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $slice['error'] ?? 'read failed'], 400);
        }
        json_out(['ok' => true, 'path' => $resolved['path']] + $slice);
    }

    if ($tool === 'search_in_files') {
        $paths = $args['paths'] ?? [];
        if (!is_array($paths)) {
            $paths = [];
        }
        $query = (string)($args['query'] ?? '');
        $maxResults = (int)($args['max_results'] ?? 80);
        if ($paths === []) {
            json_out(['ok' => false, 'error' => 'paths array is required (files to search, e.g. from @mentions)'], 400);
        }
        $search = cloud_ai_search_in_paths($paths, $query, $rootKey, max(1, min(200, $maxResults)));
        if (!($search['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $search['error'] ?? 'search failed'], 400);
        }
        json_out($search);
    }

    if ($tool === 'write_file') {
        $path = (string)($args['path'] ?? '');
        $content = (string)($args['content'] ?? '');
        $persist = !empty($args['persist']);
        $resolved = cloud_ai_resolve_user_path_for_write($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        $before = '';
        if (!empty($resolved['exists'])) {
            $read = cloud_ai_read_target_content($resolved['target'], $resolved['user'], $resolved['root']);
            if (!($read['ok'] ?? false) || !empty($read['binary'])) {
                json_out(['ok' => false, 'error' => 'Cannot overwrite binary file'], 400);
            }
            $before = (string)$read['content'];
        }
        $ext = strtolower(pathinfo($resolved['path'], PATHINFO_EXTENSION));
        if (cloud_ai_code_security_lang($ext) !== null) {
            $sec = cloud_ai_scan_code_security($content, $ext);
            if (!empty($sec['blocked'])) {
                json_out(['ok' => false, 'error' => $sec['error'], 'blocked_security' => true, 'violations' => $sec['violations']], 400);
            }
        }
        if ($persist) {
            if (!cloud_ai_persist_file_content($resolved, $content)) {
                json_out(['ok' => false, 'error' => 'Write failed'], 500);
            }
            cloud_ai_sync_file_tree_after_write($resolved);
            json_out(['ok' => true, 'path' => $resolved['path'], 'root' => $resolved['root'], 'bytes' => strlen($content), 'persisted' => true, 'tree_indexed' => true]);
        }
        json_out([
            'ok' => true,
            'staged' => true,
            'path' => $resolved['path'],
            'root' => $resolved['root'],
            'before' => $before,
            'after' => $content,
            'bytes' => strlen($content),
        ]);
    }

    if ($tool === 'create_file') {
        $path = (string)($args['path'] ?? '');
        $content = (string)($args['content'] ?? '');
        $resolved = cloud_ai_resolve_user_path_for_write($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        if (!empty($resolved['exists'])) {
            json_out(['ok' => false, 'error' => 'File already exists'], 400);
        }
        $ext = strtolower(pathinfo($resolved['path'], PATHINFO_EXTENSION));
        if (cloud_ai_code_security_lang($ext) !== null) {
            $sec = cloud_ai_scan_code_security($content, $ext);
            if (!empty($sec['blocked'])) {
                json_out(['ok' => false, 'error' => $sec['error'], 'blocked_security' => true, 'violations' => $sec['violations']], 400);
            }
        }
        if (!cloud_ai_persist_file_content($resolved, $content)) {
            json_out(['ok' => false, 'error' => 'Create failed'], 500);
        }
        cloud_ai_sync_file_tree_after_write($resolved);
        json_out([
            'ok' => true,
            'path' => $resolved['path'],
            'root' => $resolved['root'],
            'bytes' => strlen($content),
            'persisted' => true,
            'created' => true,
            'tree_indexed' => true,
        ]);
    }

    if ($tool === 'make_directory') {
        $path = (string)($args['path'] ?? '');
        $resolved = cloud_ai_resolve_user_path_for_write($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        if (file_exists($resolved['target'])) {
            json_out(['ok' => false, 'error' => 'Path already exists'], 400);
        }
        if (!@mkdir($resolved['target'], 0775, true)) {
            json_out(['ok' => false, 'error' => 'Directory create failed'], 500);
        }
        cloud_ai_sync_file_tree_after_write($resolved);
        json_out(['ok' => true, 'path' => $resolved['path'], 'root' => $resolved['root'], 'persisted' => true, 'created' => true, 'type' => 'dir']);
    }

    if ($tool === 'copy_file') {
        $sourcePath = (string)($args['source_path'] ?? '');
        $targetPath = (string)($args['target_path'] ?? '');
        $source = cloud_ai_resolve_user_file($sourcePath, $rootKey !== '' ? $rootKey : null);
        if (!($source['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $source['error']], $source['code'] ?? 400);
        }
        if (is_dir($source['target'])) {
            json_out(['ok' => false, 'error' => 'copy_file supports files only'], 400);
        }
        $target = cloud_ai_resolve_user_path_for_write($targetPath, $rootKey !== '' ? $rootKey : null);
        if (!($target['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $target['error']], $target['code'] ?? 400);
        }
        if (file_exists($target['target'])) {
            json_out(['ok' => false, 'error' => 'Target already exists'], 400);
        }
        $dir = dirname($target['target']);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            json_out(['ok' => false, 'error' => 'Failed to create target directory'], 500);
        }
        if (!@copy($source['target'], $target['target'])) {
            json_out(['ok' => false, 'error' => 'Copy failed'], 500);
        }
        cloud_ai_sync_file_tree_after_write($target);
        json_out(['ok' => true, 'path' => $target['path'], 'root' => $target['root'], 'source_path' => $source['path'], 'persisted' => true, 'copied' => true]);
    }

    if ($tool === 'rename_file') {
        $oldPath = (string)($args['old_path'] ?? '');
        $newPath = (string)($args['new_path'] ?? '');
        if ($oldPath === '' || $newPath === '') {
            json_out(['ok' => false, 'error' => 'old_path and new_path are required'], 400);
        }
        $oldResolved = cloud_ai_resolve_user_file($oldPath, $rootKey !== '' ? $rootKey : null);
        if (!($oldResolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $oldResolved['error']], $oldResolved['code'] ?? 400);
        }
        $newResolved = cloud_ai_resolve_user_path_for_write($newPath, $rootKey !== '' ? $rootKey : null);
        if (!($newResolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $newResolved['error']], $newResolved['code'] ?? 400);
        }
        if (file_exists($newResolved['target'])) {
            json_out(['ok' => false, 'error' => 'Target already exists'], 400);
        }
        $dir = dirname($newResolved['target']);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            json_out(['ok' => false, 'error' => 'Failed to create target directory'], 500);
        }
        if (!@rename($oldResolved['target'], $newResolved['target'])) {
            json_out(['ok' => false, 'error' => 'Rename failed'], 500);
        }
        cloud_ai_sync_file_tree_after_write($newResolved);
        json_out(['ok' => true, 'path' => $newResolved['path'], 'root' => $newResolved['root'], 'old_path' => $oldResolved['path'], 'persisted' => true, 'renamed' => true]);
    }

    if ($tool === 'move_path') {
        $oldPath = (string)($args['old_path'] ?? '');
        $newPath = (string)($args['new_path'] ?? '');
        if ($oldPath === '' || $newPath === '') {
            json_out(['ok' => false, 'error' => 'old_path and new_path are required'], 400);
        }
        $oldResolved = cloud_ai_resolve_user_file($oldPath, $rootKey !== '' ? $rootKey : null);
        if (!($oldResolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $oldResolved['error']], $oldResolved['code'] ?? 400);
        }
        $newResolved = cloud_ai_resolve_user_path_for_write($newPath, $rootKey !== '' ? $rootKey : null);
        if (!($newResolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $newResolved['error']], $newResolved['code'] ?? 400);
        }
        if (file_exists($newResolved['target'])) {
            json_out(['ok' => false, 'error' => 'Target already exists'], 400);
        }
        $dir = dirname($newResolved['target']);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            json_out(['ok' => false, 'error' => 'Failed to create target directory'], 500);
        }
        if (!@rename($oldResolved['target'], $newResolved['target'])) {
            json_out(['ok' => false, 'error' => 'Move failed'], 500);
        }
        cloud_ai_sync_file_tree_after_write($newResolved);
        json_out(['ok' => true, 'path' => $newResolved['path'], 'root' => $newResolved['root'], 'old_path' => $oldResolved['path'], 'persisted' => true, 'moved' => true]);
    }

    if ($tool === 'delete_file') {
        $path = (string)($args['path'] ?? '');
        $confirmed = !empty($args['confirmed']);
        if (!$confirmed) {
            json_out(['ok' => false, 'error' => 'Deletion requires user confirmation'], 400);
        }
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        if (is_dir($resolved['target'])) {
            json_out(['ok' => false, 'error' => 'Directory deletion is not supported'], 400);
        }
        if (!@unlink($resolved['target'])) {
            json_out(['ok' => false, 'error' => 'Delete failed'], 500);
        }
        cloud_ai_sync_file_tree_after_write($resolved);
        json_out(['ok' => true, 'deleted_path' => $resolved['path'], 'persisted' => true, 'deleted' => true]);
    }

    if ($tool === 'delete_directory') {
        $path = (string)($args['path'] ?? '');
        $confirmed = !empty($args['confirmed']);
        if (!$confirmed) {
            json_out(['ok' => false, 'error' => 'Deletion requires user confirmation'], 400);
        }
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        if (!is_dir($resolved['target'])) {
            json_out(['ok' => false, 'error' => 'Path is not a directory'], 400);
        }
        if (!cloud_ai_remove_path_recursive($resolved['target'])) {
            json_out(['ok' => false, 'error' => 'Directory delete failed'], 500);
        }
        cloud_ai_sync_file_tree_after_write($resolved);
        json_out(['ok' => true, 'deleted_path' => $resolved['path'], 'persisted' => true, 'deleted' => true, 'type' => 'dir']);
    }

    if ($tool === 'list_directory') {
        $path = (string)($args['path'] ?? '');
        $recursive = !empty($args['recursive']);
        $maxEntries = max(1, min(1000, (int)($args['max_entries'] ?? 200)));
        $user = get_current_user_from_sessions();
        if ($user === '') {
            json_out(['ok' => false, 'error' => 'not authenticated'], 401);
        }
        $rk = $rootKey !== '' ? $rootKey : get_requested_root_key();
        $base = user_root($user, $rk);
        $safePath = $path === '' ? '' : safe_path($path);
        $targetDir = $safePath === '' ? $base : $base . '/' . $safePath;
        if (!is_dir($targetDir)) {
            json_out(['ok' => false, 'error' => 'Directory not found'], 404);
        }
        $entries = [];
        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($targetDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                $rel = ltrim(str_replace($targetDir, '', (string)$item->getPathname()), '/');
                $entries[] = [
                    'name' => $item->getFilename(),
                    'path' => ($safePath !== '' ? $safePath . '/' : '') . str_replace('\\', '/', $rel),
                    'type' => $item->isDir() ? 'dir' : 'file',
                ];
                if (count($entries) >= $maxEntries) {
                    break;
                }
            }
        } else {
            foreach (scandir($targetDir) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $full = $targetDir . '/' . $name;
                $entries[] = [
                    'name' => $name,
                    'path' => ltrim(($safePath !== '' ? $safePath . '/' : '') . $name, '/'),
                    'type' => is_dir($full) ? 'dir' : 'file',
                ];
                if (count($entries) >= $maxEntries) {
                    break;
                }
            }
        }
        json_out(['ok' => true, 'path' => $safePath, 'recursive' => $recursive, 'entries' => $entries, 'count' => count($entries)]);
    }

    if ($tool === 'stat_path') {
        $path = (string)($args['path'] ?? '');
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        json_out(cloud_ai_path_stat($resolved));
    }

    if ($tool === 'web_search') {
        $query = (string)($args['query'] ?? '');
        $maxResults = (int)($args['max_results'] ?? 5);
        $result = cloud_ai_web_search($query, $maxResults);
        if (!($result['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $result['error'] ?? 'Web search failed'], 400);
        }
        json_out($result);
    }

    if ($tool === 'image_search') {
        $query = (string)($args['query'] ?? '');
        $maxResults = (int)($args['max_results'] ?? 8);
        $result = cloud_ai_image_search($query, $maxResults);
        if (!($result['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $result['error'] ?? 'Image search failed'], 400);
        }
        json_out($result);
    }

    if ($tool === 'open_url_fetch') {
        $url = (string)($args['url'] ?? '');
        $maxChars = max(1000, min(CLOUD_AI_FETCH_MAX_CHARS, (int)($args['max_chars'] ?? 30000)));
        $result = cloud_ai_fetch_url($url);
        if (!($result['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $result['error'] ?? 'Fetch failed'], 400);
        }
        $body = (string)($result['body'] ?? '');
        $text = mb_substr($body, 0, $maxChars);
        json_out([
            'ok' => true,
            'url' => $url,
            'status' => $result['status'],
            'content_type' => $result['content_type'],
            'content' => $text,
            'truncated' => strlen($body) > strlen($text),
        ]);
    }

    if ($tool === 'download_file') {
        $url = (string)($args['url'] ?? '');
        $targetPath = (string)($args['target_path'] ?? '');
        $overwrite = !empty($args['overwrite']);
        $resolved = cloud_ai_resolve_user_path_for_write($targetPath, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        if (!$overwrite && file_exists($resolved['target'])) {
            json_out(['ok' => false, 'error' => 'Target already exists'], 400);
        }
        $fetched = cloud_ai_fetch_url($url);
        if (!($fetched['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $fetched['error'] ?? 'Download failed'], 400);
        }
        $dir = dirname($resolved['target']);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            json_out(['ok' => false, 'error' => 'Failed to create target directory'], 500);
        }
        if (file_put_contents($resolved['target'], (string)$fetched['body']) === false) {
            json_out(['ok' => false, 'error' => 'Write failed'], 500);
        }
        cloud_ai_sync_file_tree_after_write($resolved);
        json_out(['ok' => true, 'path' => $resolved['path'], 'root' => $resolved['root'], 'bytes' => strlen((string)$fetched['body']), 'persisted' => true, 'downloaded' => true]);
    }

    if ($tool === 'run_script') {
        $user = get_current_user_from_sessions();
        if ($user === '') {
            json_out(['ok' => false, 'error' => 'not authenticated'], 401);
        }
        $rk = $rootKey !== '' ? $rootKey : get_requested_root_key();
        $result = cloud_ai_run_script(
            (string)($args['language'] ?? ''),
            (string)($args['script'] ?? ''),
            $user,
            $rk,
            (string)($args['cwd'] ?? ''),
            (int)($args['timeout_seconds'] ?? 20)
        );
        if (!($result['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $result['error'] ?? 'Script failed'], 400);
        }
        json_out($result);
    }

    if ($tool === 'search_replace') {
        $path = (string)($args['path'] ?? '');
        $old = (string)($args['old_string'] ?? '');
        $new = (string)($args['new_string'] ?? '');
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        $text = cloud_ai_tool_text_content($resolved, $args);
        if (!($text['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $text['error'] ?? 'read failed'], 400);
        }
        $content = (string)$text['content'];
        $replaced = cloud_ai_search_replace_text($content, $old, $new);
        if (!($replaced['ok'] ?? false)) {
            json_out(['ok' => false] + $replaced, 400);
        }
        $updated = (string)$replaced['updated'];
        $ext = strtolower(pathinfo($resolved['path'], PATHINFO_EXTENSION));
        if (cloud_ai_code_security_lang($ext) !== null) {
            $sec = cloud_ai_scan_code_security($updated, $ext);
            if (!empty($sec['blocked'])) {
                json_out(['ok' => false, 'error' => $sec['error'], 'blocked_security' => true, 'violations' => $sec['violations']], 400);
            }
        }
        $persist = !empty($args['persist']);
        if ($persist) {
            if (!cloud_ai_persist_file_content($resolved, $updated)) {
                json_out(['ok' => false, 'error' => 'Write failed'], 500);
            }
            cloud_ai_sync_file_tree_after_write($resolved);
            json_out([
                'ok' => true,
                'path' => $resolved['path'],
                'replacements' => (int)($replaced['replacements'] ?? 0),
                'content_source' => $text['source'] ?? 'disk',
                'persisted' => true,
                'tree_indexed' => true,
            ]);
        }
        json_out([
            'ok' => true,
            'staged' => true,
            'path' => $resolved['path'],
            'root' => $resolved['root'],
            'before' => $content,
            'after' => $updated,
            'replacements' => (int)($replaced['replacements'] ?? 0),
            'content_source' => $text['source'] ?? 'disk',
        ]);
    }

    if ($tool === 'search_by_regex') {
        $paths = $args['paths'] ?? [];
        if (!is_array($paths)) {
            $paths = [];
        }
        $pattern = (string)($args['pattern'] ?? '');
        $flags = (string)($args['flags'] ?? '');
        $maxResults = (int)($args['max_results'] ?? 80);
        if ($paths === []) {
            json_out(['ok' => false, 'error' => 'paths array is required'], 400);
        }
        $result = cloud_ai_search_by_regex($paths, $pattern, $flags, $rootKey, max(1, min(200, $maxResults)));
        if (!($result['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $result['error'] ?? 'regex search failed'], 400);
        }
        json_out($result);
    }

    if ($tool === 'replace_by_regex') {
        $path = (string)($args['path'] ?? '');
        $pattern = (string)($args['pattern'] ?? '');
        $replacement = (string)($args['replacement'] ?? '');
        $flags = (string)($args['flags'] ?? '');
        if ($pattern === '') {
            json_out(['ok' => false, 'error' => 'pattern is required'], 400);
        }
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        $text = cloud_ai_tool_text_content($resolved, $args);
        if (!($text['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $text['error'] ?? 'read failed'], 400);
        }
        $content = (string)$text['content'];
        $regex = cloud_ai_regex_delimiter($pattern, $flags);
        set_error_handler(static function() {});
        $updated = @preg_replace($regex, $replacement, $content, -1, $count);
        restore_error_handler();
        if ($updated === null) {
            json_out(['ok' => false, 'error' => 'Invalid regular expression'], 400);
        }
        if ((int)$count < 1) {
            json_out([
                'ok' => false,
                'error' => 'Pattern not found',
                'hint' => 'Content uses LF line endings. Copy context from read_file or use staged edits via search_replace.',
                'content_source' => $text['source'] ?? 'disk',
            ], 400);
        }
        $ext = strtolower(pathinfo($resolved['path'], PATHINFO_EXTENSION));
        if (cloud_ai_code_security_lang($ext) !== null) {
            $sec = cloud_ai_scan_code_security($updated, $ext);
            if (!empty($sec['blocked'])) {
                json_out(['ok' => false, 'error' => $sec['error'], 'blocked_security' => true, 'violations' => $sec['violations']], 400);
            }
        }
        json_out([
            'ok' => true,
            'staged' => true,
            'path' => $resolved['path'],
            'root' => $resolved['root'],
            'before' => $content,
            'after' => $updated,
            'replacements' => (int)$count,
            'content_source' => $text['source'] ?? 'disk',
        ]);
    }

    if ($tool === 'diff_file') {
        $path = (string)($args['path'] ?? '');
        $comparePath = (string)($args['compare_path'] ?? '');
        $content = array_key_exists('content', $args) ? (string)$args['content'] : null;
        $maxChars = max(1000, min(64000, (int)($args['max_chars'] ?? 32000)));
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        $read = cloud_ai_read_target_content($resolved['target'], $resolved['user'], $resolved['root']);
        if (!($read['ok'] ?? false) || (!empty($read['binary']) && empty($read['extracted']))) {
            json_out(['ok' => false, 'error' => 'Cannot diff binary file'], 400);
        }
        $left = (string)$read['content'];
        $right = null;
        $rightLabel = 'content';
        if ($comparePath !== '') {
            $other = cloud_ai_resolve_user_file($comparePath, $rootKey !== '' ? $rootKey : null);
            if (!($other['ok'] ?? false)) {
                json_out(['ok' => false, 'error' => $other['error']], $other['code'] ?? 400);
            }
            $otherRead = cloud_ai_read_target_content($other['target'], $other['user'], $other['root']);
            if (!($otherRead['ok'] ?? false) || (!empty($otherRead['binary']) && empty($otherRead['extracted']))) {
                json_out(['ok' => false, 'error' => 'Cannot diff against binary file'], 400);
            }
            $right = (string)$otherRead['content'];
            $rightLabel = $other['path'];
        } elseif ($content !== null) {
            $right = $content;
        } else {
            json_out(['ok' => false, 'error' => 'compare_path or content is required'], 400);
        }
        $diff = cloud_ai_build_diff($left, $right, $resolved['path'], $rightLabel, $maxChars);
        if (!($diff['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $diff['error'] ?? 'Diff failed'], 500);
        }
        json_out(['ok' => true, 'path' => $resolved['path']] + $diff);
    }

    if ($tool === 'json_edit') {
        $path = (string)($args['path'] ?? '');
        $edits = $args['edits'] ?? [];
        if (!is_array($edits) || $edits === []) {
            json_out(['ok' => false, 'error' => 'edits array is required'], 400);
        }
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        $read = cloud_ai_read_target_content($resolved['target'], $resolved['user'], $resolved['root']);
        if (!($read['ok'] ?? false) || !empty($read['binary'])) {
            json_out(['ok' => false, 'error' => 'Cannot edit binary file as JSON'], 400);
        }
        $data = json_decode((string)$read['content'], true);
        if (!is_array($data)) {
            json_out(['ok' => false, 'error' => 'JSON root must be an object or array'], 400);
        }
        foreach ($edits as $edit) {
            if (!is_array($edit)) {
                continue;
            }
            $op = (string)($edit['op'] ?? '');
            $keyPath = (string)($edit['key_path'] ?? '');
            if ($op === 'set') {
                cloud_ai_set_json_path($data, $keyPath, $edit['value'] ?? null);
            } elseif ($op === 'remove') {
                cloud_ai_remove_json_path($data, $keyPath);
            }
        }
        $updated = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($updated === false) {
            json_out(['ok' => false, 'error' => 'JSON encode failed'], 500);
        }
        json_out([
            'ok' => true,
            'staged' => true,
            'path' => $resolved['path'],
            'root' => $resolved['root'],
            'before' => (string)$read['content'],
            'after' => $updated . "\n",
        ]);
    }

    if ($tool === 'csv_preview') {
        $path = (string)($args['path'] ?? '');
        $maxRows = max(1, min(200, (int)($args['max_rows'] ?? 20)));
        $delimiter = array_key_exists('delimiter', $args) ? (string)$args['delimiter'] : '';
        $resolved = cloud_ai_resolve_user_file($path, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        $read = cloud_ai_read_target_content($resolved['target'], $resolved['user'], $resolved['root']);
        if (!($read['ok'] ?? false) || (!empty($read['binary']) && empty($read['extracted']))) {
            json_out(['ok' => false, 'error' => 'Cannot preview binary file as CSV'], 400);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'aicsv_');
        if ($tmp === false) {
            json_out(['ok' => false, 'error' => 'Temp file error'], 500);
        }
        file_put_contents($tmp, (string)$read['content']);
        $fh = fopen($tmp, 'rb');
        @unlink($tmp);
        if (!$fh) {
            json_out(['ok' => false, 'error' => 'Failed to open CSV'], 500);
        }
        $sample = substr((string)$read['content'], 0, 4096);
        $delim = $delimiter !== '' ? $delimiter : ((substr_count($sample, "\t") > substr_count($sample, ',')) ? "\t" : ',');
        $headers = fgetcsv($fh, 0, $delim);
        $rows = [];
        while (($row = fgetcsv($fh, 0, $delim)) !== false && count($rows) < $maxRows) {
            $rows[] = $row;
        }
        fclose($fh);
        json_out(['ok' => true, 'path' => $resolved['path'], 'delimiter' => $delim, 'headers' => $headers ?: [], 'rows' => $rows, 'rows_returned' => count($rows)]);
    }

    if ($tool === 'archive_extract') {
        $archivePath = (string)($args['archive_path'] ?? '');
        $extractTo = (string)($args['extract_to'] ?? '');
        $resolved = cloud_ai_resolve_user_file($archivePath, $rootKey !== '' ? $rootKey : null);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        if (strtolower(pathinfo($resolved['path'], PATHINFO_EXTENSION)) !== 'zip') {
            json_out(['ok' => false, 'error' => 'Only ZIP archives are supported'], 400);
        }
        if (!class_exists('ZipArchive')) {
            json_out(['ok' => false, 'error' => 'Zip extension not available'], 500);
        }
        $extractResolved = $extractTo !== ''
            ? cloud_ai_resolve_user_path_for_write($extractTo, $rootKey !== '' ? $rootKey : null)
            : ['ok' => true, 'path' => dirname($resolved['path']), 'target' => dirname($resolved['target'])];
        if (!($extractResolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $extractResolved['error']], $extractResolved['code'] ?? 400);
        }
        if (!is_dir($extractResolved['target']) && !mkdir($extractResolved['target'], 0775, true) && !is_dir($extractResolved['target'])) {
            json_out(['ok' => false, 'error' => 'Failed to create extract directory'], 500);
        }
        $zip = new ZipArchive();
        if ($zip->open($resolved['target']) !== true) {
            json_out(['ok' => false, 'error' => 'Failed to open archive'], 500);
        }
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name && !str_ends_with($name, '/')) {
                $files[] = $name;
            }
        }
        $ok = $zip->extractTo($extractResolved['target']);
        $zip->close();
        if (!$ok) {
            json_out(['ok' => false, 'error' => 'Archive extract failed'], 500);
        }
        cloud_ai_sync_file_tree_after_write(['root' => $rootKey !== '' ? $rootKey : get_requested_root_key()]);
        json_out(['ok' => true, 'archive_path' => $resolved['path'], 'extracted_to' => $extractResolved['path'], 'files' => $files, 'persisted' => true]);
    }

    if ($tool === 'archive_create') {
        $items = $args['items'] ?? [];
        $archivePath = (string)($args['archive_path'] ?? '');
        if (!is_array($items) || $items === []) {
            json_out(['ok' => false, 'error' => 'items array is required'], 400);
        }
        if ($archivePath === '') {
            json_out(['ok' => false, 'error' => 'archive_path is required'], 400);
        }
        if (!class_exists('ZipArchive')) {
            json_out(['ok' => false, 'error' => 'Zip extension not available'], 500);
        }
        $archiveResolved = cloud_ai_resolve_user_path_for_write($archivePath, $rootKey !== '' ? $rootKey : null);
        if (!($archiveResolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $archiveResolved['error']], $archiveResolved['code'] ?? 400);
        }
        $archiveRealPath = $archiveResolved['path'];
        if (!str_ends_with(strtolower($archiveRealPath), '.zip')) {
            $archiveResolved = cloud_ai_resolve_user_path_for_write($archiveRealPath . '.zip', $rootKey !== '' ? $rootKey : null);
        }
        $dir = dirname($archiveResolved['target']);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            json_out(['ok' => false, 'error' => 'Failed to create archive directory'], 500);
        }
        $zip = new ZipArchive();
        if ($zip->open($archiveResolved['target'], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            json_out(['ok' => false, 'error' => 'Failed to create archive'], 500);
        }
        $added = 0;
        foreach ($items as $itemPath) {
            $itemResolved = cloud_ai_resolve_user_file((string)$itemPath, $rootKey !== '' ? $rootKey : null);
            if (!($itemResolved['ok'] ?? false)) {
                continue;
            }
            if (is_dir($itemResolved['target'])) {
                $baseName = basename($itemResolved['path']);
                $directory = new RecursiveDirectoryIterator($itemResolved['target'], RecursiveDirectoryIterator::SKIP_DOTS);
                $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);
                foreach ($iterator as $file) {
                    $inner = substr($file->getPathname(), strlen($itemResolved['target']) + 1);
                    $zipPath = str_replace('\\', '/', $baseName . '/' . $inner);
                    if ($file->isDir()) {
                        $zip->addEmptyDir($zipPath);
                    } else {
                        if ($zip->addFile($file->getPathname(), $zipPath)) {
                            $added++;
                        }
                    }
                }
            } else {
                if ($zip->addFile($itemResolved['target'], basename($itemResolved['path']))) {
                    $added++;
                }
            }
        }
        $zip->close();
        cloud_ai_sync_file_tree_after_write($archiveResolved);
        json_out(['ok' => true, 'path' => $archiveResolved['path'], 'root' => $archiveResolved['root'], 'archive_path' => $archiveResolved['path'], 'files_added' => $added, 'persisted' => true]);
    }

    if ($tool === 'generate_speech') {
        $text = (string)($args['text'] ?? '');
        $language = (string)($args['language'] ?? 'en');
        $speed = (float)($args['speed'] ?? 1.0);
        $result = cloud_ai_tool_generate_speech($text, $language, $speed);
        if (!($result['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $result['error']], $result['code'] ?? 400);
        }
        json_out($result);
    }

    if ($tool === 'get_speech_progress') {
        $progressId = (string)($args['progress_id'] ?? '');
        $result = cloud_ai_tool_get_speech_progress($progressId);
        if (!($result['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $result['error']], $result['code'] ?? 400);
        }
        json_out($result);
    }

    if ($tool === 'transcribe_audio') {
        $audioPath = (string)($args['audio_path'] ?? '');
        $language = (string)($args['language'] ?? 'pl');
        $result = cloud_ai_tool_transcribe_audio($audioPath, $language);
        if (!($result['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $result['error']], $result['code'] ?? 400);
        }
        json_out($result);
    }

    if ($tool === 'get_transcription_progress') {
        $progressId = (string)($args['progress_id'] ?? '');
        $result = cloud_ai_tool_get_transcription_progress($progressId);
        if (!($result['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $result['error']], $result['code'] ?? 400);
        }
        json_out($result);
    }

    if ($tool === 'export_latex_pdf_file') {
        $path = (string)($args['path'] ?? '');
        if ($path === '') {
            json_out(['ok' => false, 'error' => 'path is required'], 400);
        }
        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['tex', 'latex'], true)) {
            json_out(['ok' => false, 'error' => 'Only .tex/.latex files support PDF export'], 400);
        }
        $rk = $rootKey !== '' ? $rootKey : get_requested_root_key();
        $resolved = cloud_ai_resolve_user_file($path, $rk);
        if (!($resolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $resolved['error']], $resolved['code'] ?? 400);
        }
        if (is_dir($resolved['target'])) {
            json_out(['ok' => false, 'error' => 'Path is a directory'], 400);
        }
        $read = cloud_ai_read_target_content($resolved['target'], $resolved['user'], $resolved['root']);
        if (!($read['ok'] ?? false) || !empty($read['binary'])) {
            json_out(['ok' => false, 'error' => 'Cannot read file as text'], 400);
        }
        cloud_ai_release_session_lock();
        $pdfRel = preg_replace('/\.(?:tex|latex)$/i', '.pdf', $resolved['path']);
        if ($pdfRel === $resolved['path']) {
            $pdfRel = $resolved['path'] . '.pdf';
        }
        $pdfResolved = cloud_ai_resolve_user_path_for_write($pdfRel, $rk);
        if (!($pdfResolved['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $pdfResolved['error']], $pdfResolved['code'] ?? 400);
        }
        $result = cloud_ai_compile_latex_to_pdf_file((string)$read['content'], $pdfResolved['target']);
        if (!($result['success'] ?? false)) {
            json_out(['ok' => true, 'success' => false, 'path' => $resolved['path'], 'pdf_path' => $pdfResolved['path'], 'error' => $result['error'] ?? 'PDF export failed', 'output' => $result['output'] ?? '']);
        }
        cloud_ai_sync_file_tree_after_write($pdfResolved);
        json_out(['ok' => true, 'success' => true, 'path' => $resolved['path'], 'pdf_path' => $pdfResolved['path'], 'pdf_size' => $result['pdf_size'] ?? 0, 'persisted' => true, 'message' => 'PDF saved']);
    }

    if ($tool === 'verify_syntax') {
        $path = (string)($args['path'] ?? '');
        $content = array_key_exists('content', $args) ? (string)$args['content'] : null;
        if ($path === '') {
            json_out(['ok' => false, 'error' => 'path is required'], 400);
        }
        $rk = $rootKey !== '' ? $rootKey : get_requested_root_key();
        $result = cloud_ai_verify_syntax($path, $content, $rk);
        if (isset($result['error']) && !isset($result['success'])) {
            json_out(['ok' => false, 'error' => $result['error']], 400);
        }
        json_out(['ok' => true, 'path' => $path] + $result);
    }

    if ($tool === 'run_command') {
        $command = $args['command'] ?? ($args['cmd'] ?? ($args['argv'] ?? []));
        $cwd = (string)($args['cwd'] ?? '');
        $timeout = (int)($args['timeout_seconds'] ?? 20);
        $user = get_current_user_from_sessions();
        if ($user === '') {
            json_out(['ok' => false, 'error' => 'not authenticated'], 401);
        }
        $rk = $rootKey !== '' ? $rootKey : get_requested_root_key();
        $argv = cloud_ai_normalize_command_argv($command);
        $result = cloud_ai_run_command($argv, $user, $rk, $cwd, $timeout);
        if (!($result['ok'] ?? false)) {
            json_out(['ok' => false, 'error' => $result['error'] ?? 'Command failed', 'allowed' => $result['allowed'] ?? null], 400);
        }
        json_out($result);
    }

    if ($tool === 'compile_and_check') {
        $path = (string)($args['path'] ?? '');
        $content = array_key_exists('content', $args) ? (string)$args['content'] : null;
        if ($path === '') {
            json_out(['ok' => false, 'error' => 'path is required'], 400);
        }
        $rk = $rootKey !== '' ? $rootKey : get_requested_root_key();
        try {
            $result = cloud_ai_compile_check($path, $content, $rk);
        } catch (Throwable $e) {
            json_out(['ok' => false, 'error' => 'compile_and_check failed: ' . $e->getMessage()], 400);
        }
        if (!is_array($result)) {
            json_out(['ok' => false, 'error' => 'compile_and_check returned invalid result'], 400);
        }
        if (isset($result['error']) && !isset($result['success'])) {
            json_out(['ok' => false, 'error' => $result['error']], 400);
        }
        json_out(['ok' => true, 'path' => $path] + $result);
    }

    if ($tool === 'apply_changes') {
        $changes = $args['changes'] ?? [];
        if (!is_array($changes) || $changes === []) {
            json_out(['ok' => false, 'error' => 'changes array is required'], 400);
        }
        $applied = [];
        foreach ($changes as $ch) {
            if (!is_array($ch)) {
                continue;
            }
            $path = (string)($ch['path'] ?? '');
            $content = (string)($ch['content'] ?? '');
            if ($path === '') {
                continue;
            }
            $resolved = cloud_ai_resolve_user_path_for_write($path, $rootKey !== '' ? $rootKey : null);
            if (!($resolved['ok'] ?? false)) {
                json_out(['ok' => false, 'error' => $resolved['error'] ?? 'resolve failed', 'path' => $path], 400);
            }
            if (!cloud_ai_persist_file_content($resolved, $content)) {
                json_out(['ok' => false, 'error' => 'Write failed', 'path' => $path], 500);
            }
            $applied[] = $resolved['path'];
        }
        if ($applied !== [] && isset($resolved)) {
            cloud_ai_sync_file_tree_after_write($resolved);
        }
        json_out(['ok' => true, 'applied' => $applied, 'tree_indexed' => $applied !== []]);
    }

    json_out(['ok' => false, 'error' => 'Unknown tool: ' . $tool], 400);
}
