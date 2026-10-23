<?php
/**
 * AppDashboard version info helper.
 *
 * Reads version metadata from the image environment (APP_VERSION, APP_GIT_COMMIT,
 * APP_BUILD_DATE baked by the Docker build) and falls back to a cheap, subprocess
 * free read of the git repository that is bind-mounted into the container.
 *
 * Deliberately no `git` subprocess calls here: on Docker Desktop bind mounts
 * `git status` may block for minutes and wedge PHP-FPM workers.
 */

function appdashboard_version_info(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $envVersion = trim((string)getenv('APP_VERSION'));
    if ($envVersion !== '' && $envVersion !== 'dev') {
        $envCommit = trim((string)getenv('APP_GIT_COMMIT'));
        $envBuild = trim((string)getenv('APP_BUILD_DATE'));
        $cached = [
            'label' => $envVersion,
            'short' => $envCommit !== '' ? substr($envCommit, 0, 7) : '',
            'full' => $envCommit,
            'branch' => '',
            'updated_at' => '',
            'updated_at_human' => '',
            'build_date' => $envBuild,
            'build_date_human' => appdashboard_version_format_date($envBuild),
            'source' => 'env',
        ];
        return $cached;
    }

    $git = appdashboard_version_read_git(dirname(__DIR__));
    $fileVersion = appdashboard_version_read_file(dirname(__DIR__));

    if ($fileVersion === '' && $git === null) {
        $cached = [
            'label' => '',
            'short' => '',
            'full' => '',
            'branch' => '',
            'updated_at' => '',
            'updated_at_human' => '',
            'build_date' => '',
            'build_date_human' => '',
            'source' => 'unknown',
        ];
        return $cached;
    }

    $label = $fileVersion;
    if ($label === '' && $git !== null) {
        $label = $git['short'];
    }

    $cached = [
        'label' => $label,
        'short' => $git['short'] ?? '',
        'full' => $git['full'] ?? '',
        'branch' => $git['branch'] ?? '',
        'updated_at' => $git['updated_at'] ?? '',
        'updated_at_human' => appdashboard_version_format_date($git['updated_at'] ?? ''),
        'build_date' => '',
        'build_date_human' => '',
        'source' => $fileVersion !== '' ? 'version' : 'git',
    ];
    return $cached;
}

/**
 * Version declared in the repository VERSION file (e.g. "1.0.0").
 * The Docker build replaces it with major.minor.<commit count> for releases.
 */
function appdashboard_version_read_file(string $repoRoot): string {
    $file = $repoRoot . '/VERSION';
    if (!is_file($file)) {
        return '';
    }
    $value = trim((string)@file_get_contents($file));
    return preg_match('/^\d+\.\d+(\.\d+)?$/', $value) ? $value : '';
}

/**
 * Resolve HEAD/branch/update time by reading .git files only.
 */
function appdashboard_version_read_git(string $repoRoot): ?array {
    $gitDir = $repoRoot . '/.git';
    if (is_file($gitDir)) {
        // Worktree / submodule style ".git" file: "gitdir: /path/to/gitdir".
        $content = @file_get_contents($gitDir);
        if ($content === false || !preg_match('/^gitdir:\s*(.+)$/m', $content, $m)) {
            return null;
        }
        $gitDir = trim($m[1]);
        if ($gitDir !== '' && $gitDir[0] !== '/') {
            $gitDir = $repoRoot . '/' . $gitDir;
        }
    }
    if (!is_dir($gitDir)) {
        return null;
    }

    $head = @file_get_contents($gitDir . '/HEAD');
    if ($head === false) {
        return null;
    }
    $head = trim($head);

    $full = '';
    $branch = '';
    if (preg_match('/^ref:\s*(.+)$/', $head, $m)) {
        $ref = trim($m[1]);
        $branch = basename($ref);
        $refFile = $gitDir . '/' . $ref;
        if (is_file($refFile)) {
            $full = trim((string)@file_get_contents($refFile));
        }
        if ($full === '') {
            $packed = $gitDir . '/packed-refs';
            if (is_file($packed)) {
                $packedData = @file_get_contents($packed);
                if ($packedData !== false && preg_match('/^([0-9a-f]{40})\s+' . preg_quote($ref, '/') . '$/m', $packedData, $pm)) {
                    $full = $pm[1];
                }
            }
        }
    } elseif (preg_match('/^[0-9a-f]{40}$/', $head)) {
        $full = $head;
    }

    if ($full === '') {
        return null;
    }

    // The reflog mtime tracks the last fetch/reset/checkout, i.e. when the code
    // running in the container was updated.
    $updatedAt = '';
    $logs = $gitDir . '/logs/HEAD';
    $ts = is_file($logs) ? @filemtime($logs) : false;
    if ($ts === false) {
        $ts = @filemtime($gitDir . '/HEAD');
    }
    if ($ts !== false) {
        $updatedAt = gmdate('Y-m-d', $ts);
    }

    return [
        'full' => $full,
        'short' => substr($full, 0, 7),
        'branch' => $branch,
        'updated_at' => $updatedAt,
    ];
}

function appdashboard_version_format_date(string $value): string {
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts === false ? '' : date('d.m.Y', $ts);
}
