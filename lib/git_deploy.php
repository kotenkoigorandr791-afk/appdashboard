<?php
declare(strict_types=1);

function gitDeployCodeDir(): string {
    return defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
}

function gitDeployRepoPath(): string {
    return '/data/git/repositories/kotenkoigorandr791/apps-dashboard.git';
}


function gitDeployGitBin(string $codeDir): string {
    return 'git -c safe.directory=' . escapeshellarg($codeDir);
}

function gitDeployRun(string $cmd): string {
    $out = [];
    exec($cmd . ' 2>&1', $out, $code);
    return implode("\n", $out);
}

function gitDeployRepoUrl(): string {
    $url = function_exists('getConfig') ? trim((string)getConfig('git_repo_url')) : '';
    return $url !== '' ? $url : 'https://git.serverkherson.xyz/kotenkoigorandr791/apps-dashboard.git';
}

function gitDeploySyncRemoteRef(): array {
    $codeDir = gitDeployCodeDir();
    $bundleHost = '/tmp/appdashboard-pull-' . getmypid() . '.bundle';
    $bundleGitea = '/data/gitea/tmp/appdashboard-pull.bundle';
    $steps = [];
    $git = gitDeployGitBin($codeDir);
    $steps[] = gitDeployRun('docker exec -u git gitea git -C ' . escapeshellarg(gitDeployRepoPath()) . ' bundle create ' . escapeshellarg($bundleGitea) . ' main');
    $steps[] = gitDeployRun('docker cp gitea:' . escapeshellarg($bundleGitea) . ' ' . escapeshellarg($bundleHost));
    $steps[] = gitDeployRun('cd ' . escapeshellarg($codeDir) . ' && ' . $git . ' fetch --force ' . escapeshellarg($bundleHost) . ' main:refs/remotes/origin/main');
    @unlink($bundleHost);
    $error = '';
    foreach ($steps as $step) {
        if (preg_match('/\b(fatal:|error:)\b/i', $step)) {
            $error = $step;
            break;
        }
    }
    return ['output' => implode("\n", $steps), 'error' => $error];
}

function gitDeployGetStatus(): array {
    $sync = gitDeploySyncRemoteRef();
    if ($sync['error'] !== '') {
        return ['success' => false, 'error' => $sync['error'], 'output' => $sync['output']];
    }
    $codeDir = gitDeployCodeDir();
    $git = gitDeployGitBin($codeDir);
    $behind = (int)trim(gitDeployRun('cd ' . escapeshellarg($codeDir) . ' && ' . $git . ' rev-list --count HEAD..origin/main 2>/dev/null') ?: '0');
    $commits = trim(gitDeployRun('cd ' . escapeshellarg($codeDir) . ' && ' . $git . ' log HEAD..origin/main --oneline 2>/dev/null') ?: '');
    $stat = trim(gitDeployRun('cd ' . escapeshellarg($codeDir) . ' && ' . $git . ' diff HEAD..origin/main --stat 2>/dev/null') ?: '');
    return [
        'success' => true,
        'branch' => trim(gitDeployRun('cd ' . escapeshellarg($codeDir) . ' && ' . $git . ' rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'main'),
        'local_head' => trim(gitDeployRun('cd ' . escapeshellarg($codeDir) . ' && ' . $git . ' rev-parse --short HEAD 2>/dev/null') ?: ''),
        'remote_head' => trim(gitDeployRun('cd ' . escapeshellarg($codeDir) . ' && ' . $git . ' rev-parse --short origin/main 2>/dev/null') ?: ''),
        'behind' => $behind,
        'up_to_date' => $behind === 0,
        'commits' => $commits,
        'stat' => $stat,
        'repo_url' => gitDeployRepoUrl(),
    ];
}

function gitDeployRestartContainer(): array {
    $sock = '/var/run/docker.sock';
    if (!is_readable($sock)) {
        return ['success' => false, 'error' => 'Docker socket not available'];
    }
    $ch = curl_init('http://localhost/containers/appdashboard/restart?t=10');
    curl_setopt_array($ch, [
        CURLOPT_UNIX_SOCKET_PATH => $sock,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        return ['success' => true, 'output' => (string)$body];
    }
    return ['success' => false, 'error' => $err !== '' ? $err : ('HTTP ' . $code . ': ' . (string)$body)];
}

function gitDeployApplyNginxConfig(): array {
    $codeDir = gitDeployCodeDir();
    $src = $codeDir . '/deploy/nginx/appdashboard.conf';
    if (!is_file($src)) {
        return ['success' => false, 'error' => 'Nginx config not found: ' . $src];
    }
    $copyOut = gitDeployRun('docker cp ' . escapeshellarg($src) . ' appdashboard:/etc/nginx/sites-available/appdashboard.conf');
    if (preg_match('/\b(fatal:|error:|Error response)\b/i', $copyOut)) {
        return ['success' => false, 'error' => $copyOut];
    }
    $testOut = gitDeployRun('docker exec appdashboard nginx -t');
    if (!preg_match('/test is successful/i', $testOut)) {
        return ['success' => false, 'error' => $testOut];
    }
    return ['success' => true, 'output' => trim($copyOut . "\n" . $testOut)];
}

function gitDeployPullAndRestart(): array {
    $status = gitDeployGetStatus();
    if (empty($status['success'])) {
        return $status;
    }
    if (!empty($status['up_to_date'])) {
        $nginxOut = gitDeployApplyNginxConfig();
        if (empty($nginxOut['success'])) {
            return ['success' => false, 'error' => $nginxOut['error'] ?? 'Nginx config update failed'];
        }
        $restartOut = gitDeployRestartContainer();
        if (empty($restartOut['success'])) {
            return ['success' => false, 'error' => $restartOut['error'] ?? 'Container restart failed', 'nginx_output' => $nginxOut['output'] ?? ''];
        }
        return [
            'success' => true,
            'message' => 'Already up to date; config reapplied and container restarted',
            'nginx_output' => $nginxOut['output'] ?? '',
            'restarted' => true,
            'up_to_date' => true,
            'local_head' => $status['local_head'] ?? '',
        ];
    }
    $codeDir = gitDeployCodeDir();
    $git = gitDeployGitBin($codeDir);
    $mergeOut = gitDeployRun('cd ' . escapeshellarg($codeDir) . ' && ' . $git . ' merge --ff-only origin/main');
    if (preg_match('/\b(fatal:|error:)\b/i', $mergeOut)) {
        return ['success' => false, 'error' => $mergeOut];
    }
    $nginxOut = gitDeployApplyNginxConfig();
    if (empty($nginxOut['success'])) {
        return ['success' => false, 'error' => $nginxOut['error'] ?? 'Nginx config update failed', 'merge_output' => $mergeOut];
    }
    $restartOut = gitDeployRestartContainer();
    if (empty($restartOut['success'])) {
        return ['success' => false, 'error' => $restartOut['error'] ?? 'Container restart failed', 'merge_output' => $mergeOut, 'nginx_output' => $nginxOut['output'] ?? ''];
    }
    return [
        'success' => true,
        'message' => 'Updated and container restarted',
        'merge_output' => $mergeOut,
        'nginx_output' => $nginxOut['output'] ?? '',
        'restarted' => true,
        'local_head' => trim(gitDeployRun('cd ' . escapeshellarg($codeDir) . ' && ' . gitDeployGitBin($codeDir) . ' rev-parse --short HEAD 2>/dev/null') ?: ''),
    ];
}
