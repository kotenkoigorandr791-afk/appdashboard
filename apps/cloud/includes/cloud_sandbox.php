<?php
declare(strict_types=1);

function cloud_sandbox_bwrap_path(): string
{
    $path = trim((string)shell_exec('command -v bwrap 2>/dev/null'));
    return $path !== '' && is_executable($path) ? $path : '';
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
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $redirect = $mergeStderr ? ' 2>&1' : '';
    $cmd = 'timeout ' . (int)$timeout . 's ' . cloud_sandbox_shell_command($workDir, $command . $redirect);
    $process = proc_open($cmd, $descriptors, $pipes, $workDir, null);
    if (!is_resource($process)) {
        return ['success' => false, 'output' => '', 'error' => 'Failed to start sandboxed command', 'exitCode' => -1];
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
    return [
        'success' => $code === 0,
        'output' => $combined,
        'error' => $code === 124 ? 'Process timed out after ' . $timeout . ' seconds' : '',
        'exitCode' => $code,
    ];
}
