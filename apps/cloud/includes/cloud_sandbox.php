<?php
declare(strict_types=1);

function cloud_sandbox_bwrap_path(): string
{
    $path = trim((string)shell_exec('command -v bwrap 2>/dev/null'));
    return $path !== '' && is_executable($path) ? $path : '';
}

function cloud_sandbox_unsafe_allowed(): bool
{
    return getenv('APP_SANDBOX_ALLOW_UNSAFE') === '1';
}

function cloud_sandbox_base_args(string $workDir): array
{
    $bwrap = cloud_sandbox_bwrap_path();
    if ($bwrap === '') {
        throw new RuntimeException('Sandbox runtime is not available');
    }
    $workDir = realpath($workDir) ?: $workDir;
    if (!is_dir($workDir)) {
        throw new RuntimeException('Sandbox workspace not found');
    }
    $args = [
        $bwrap,
        '--die-with-parent',
        '--unshare-user-try',
        '--unshare-pid',
        '--unshare-ipc',
        '--unshare-uts',
        '--new-session',
        '--proc', '/proc',
        '--dev', '/dev',
        '--tmpfs', '/tmp',
        '--tmpfs', '/home',
        '--bind', $workDir, '/workspace',
        '--chdir', '/workspace',
        '--setenv', 'HOME', '/tmp',
        '--setenv', 'TMPDIR', '/tmp',
        '--setenv', 'PATH', '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
    ];
    foreach (['/usr', '/bin', '/sbin', '/lib', '/lib64', '/opt'] as $path) {
        if (file_exists($path)) {
            array_push($args, '--ro-bind', $path, $path);
        }
    }
    foreach (['/etc/alternatives', '/etc/ld.so.cache', '/etc/ld.so.conf', '/etc/ld.so.conf.d', '/etc/java-17-openjdk', '/etc/ssl', '/etc/ca-certificates'] as $path) {
        if (file_exists($path)) {
            array_push($args, '--ro-bind', $path, $path);
        }
    }
    return $args;
}

function cloud_sandbox_shell_command(string $workDir, string $command): string
{
    $args = cloud_sandbox_base_args($workDir);
    array_push($args, '/bin/sh', '-lc', $command);
    return implode(' ', array_map('escapeshellarg', $args));
}

function cloud_sandbox_exec(string $workDir, string $command, ?string $input = null, int $timeout = 30, bool $mergeStderr = true): array
{
    $timeout = max(1, min(180, $timeout));
    $redirect = $mergeStderr ? ' 2>&1' : '';
    if (cloud_sandbox_bwrap_path() === '' && !cloud_sandbox_unsafe_allowed()) {
        return ['success' => false, 'output' => '', 'error' => 'Sandbox runtime is not available', 'exitCode' => 126];
    }
    if (cloud_sandbox_bwrap_path() === '') {
        $result = cloud_sandbox_proc_exec('timeout ' . (int)$timeout . 's /bin/sh -lc ' . escapeshellarg($command . $redirect), $workDir, $input);
        return [
            'success' => ($result['exitCode'] ?? 1) === 0,
            'output' => trim((string)($result['output'] ?? '')),
            'error' => ($result['exitCode'] ?? 1) === 124 ? 'Process timed out after ' . $timeout . ' seconds' : '',
            'exitCode' => (int)($result['exitCode'] ?? 1),
        ];
    }
    $cmd = 'timeout ' . (int)$timeout . 's ' . cloud_sandbox_shell_command($workDir, $command . $redirect);
    $result = cloud_sandbox_proc_exec($cmd, $workDir, $input);
    if (cloud_sandbox_unsafe_allowed() && ($result['exitCode'] ?? 0) !== 0 && str_contains((string)($result['output'] ?? ''), 'bwrap: setting up uid map: Permission denied')) {
        $result = cloud_sandbox_proc_exec('timeout ' . (int)$timeout . 's /bin/sh -lc ' . escapeshellarg($command . $redirect), $workDir, $input);
    }
    return [
        'success' => ($result['exitCode'] ?? 1) === 0,
        'output' => trim((string)($result['output'] ?? '')),
        'error' => ($result['exitCode'] ?? 1) === 124 ? 'Process timed out after ' . $timeout . ' seconds' : '',
        'exitCode' => (int)($result['exitCode'] ?? 1),
    ];
}

function cloud_sandbox_proc_exec(string $cmd, string $workDir, ?string $input): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($cmd, $descriptors, $pipes, $workDir, null);
    if (!is_resource($process)) {
        return ['output' => 'Failed to start sandboxed command', 'exitCode' => -1];
    }
    if ($input !== null && $input !== '') {
        fwrite($pipes[0], $input);
        if (!str_ends_with($input, "\n")) fwrite($pipes[0], "\n");
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    $combined = trim((string)$output . ((string)$stderr !== '' ? "\n" . (string)$stderr : ''));
    return ['output' => $combined, 'exitCode' => $code];
}
