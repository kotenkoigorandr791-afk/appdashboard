<?php
/**
 * Public API — restic backup integration.
 *
 * GET  /backup/status          repository status, snapshots, stats, jobs
 * POST /backup/run             start a backup job
 * GET  /backup/logs[/<job>]    recent jobs or a single job log
 * GET  /backup/disk?path=...   disk space
 * GET  /backup/host_fs?path=…  browse the host filesystem
 * POST /backup/host_fs/mkdir   create a host folder
 * POST /backup/path_rw_test    verify read/write access
 */
require_once APP_ROOT . '/project/backup/i18n.php';
backup_boot_i18n('en');
require_once APP_ROOT . '/project/backup/api.php';

public_api_auth_user(true);
$parts = public_api_parse_path();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$body = public_api_body();
$resource = $parts[1] ?? '';
$id = isset($parts[2]) ? rawurldecode((string)$parts[2]) : '';

if ($resource === 'status' && $method === 'GET') {
    public_api_require_role(1);
    $cfg = resticLoadConfig();
    $configured = (string)$cfg['repository'] !== '' && (string)$cfg['password'] !== '';
    $repoOk = false;
    $repoError = '';
    $snapshots = [];
    $stats = null;
    if ($configured) {
        $test = resticTestRepository($cfg);
        $repoOk = !empty($test['ok']);
        $repoError = (string)($test['error'] ?? '');
        if ($repoOk) {
            try { $snapshots = resticSnapshots($cfg); } catch (Throwable $e) { $repoError = $e->getMessage(); }
            try { $stats = resticStats($cfg); } catch (Throwable $e) {}
        }
    }
    public_api_json([
        'ok' => true,
        'configured' => $configured,
        'repo_ok' => $repoOk,
        'repo_error' => $repoError,
        'config' => resticPublicConfig($cfg),
        'snapshots' => $snapshots,
        'stats' => $stats,
        'jobs' => resticJobs(10),
        'schedule_state' => resticLoadScheduleState(),
    ]);
}

if ($resource === 'run' && $id === '' && $method === 'POST') {
    public_api_require_role(2);
    $cfg = resticLoadConfig();
    try {
        resticRequireConfigured($cfg);
        if (empty($cfg['sources'])) throw new RuntimeException('no_sources');
        $job = resticJobSpawn('backup', ['sources' => $cfg['sources'], 'trigger' => 'api']);
        adminAuditLog('backup_run', (string)$cfg['repository'], 'ok', ['source' => 'public_api']);
        public_api_json(['ok' => true, 'job' => $job], 202);
    } catch (Throwable $e) {
        public_api_json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

if ($resource === 'logs' && $method === 'GET') {
    public_api_require_role(2);
    if ($id === '') {
        public_api_json(['ok' => true, 'jobs' => resticJobs(30)]);
    }
    $job = resticJobRead($id);
    if ($job === []) public_api_json(['ok' => false, 'error' => 'job_not_found'], 404);
    public_api_json(['ok' => true, 'job' => resticJobPublic($job), 'logs' => resticJobTail($id)]);
}

if ($resource === 'disk' && $method === 'GET') {
    public_api_require_role(1);
    $path = (string)($_GET['path'] ?? $body['path'] ?? resticHostRoot());
    try {
        public_api_json(['ok' => true] + resticDiskSpace($path));
    } catch (Throwable $e) {
        public_api_json(['ok' => false, 'error' => $e->getMessage(), 'path' => $path], 400);
    }
}

if ($resource === 'host_fs' && $id === '' && $method === 'GET') {
    public_api_require_role(2);
    $path = (string)($_GET['path'] ?? resticHostRoot());
    $onlyFolders = (string)($_GET['onlyFolders'] ?? 'true') !== 'false';
    $showHidden = (string)($_GET['showHidden'] ?? 'false') === 'true';
    try {
        public_api_json(['ok' => true] + resticHostBrowse($path, $onlyFolders, $showHidden));
    } catch (Throwable $e) {
        public_api_json(['ok' => false, 'error' => $e->getMessage(), 'path' => $path], 400);
    }
}

if ($resource === 'host_fs' && $id === 'mkdir' && $method === 'POST') {
    public_api_require_role(2);
    try {
        public_api_json(['ok' => true] + resticHostMkdir((string)($body['parent'] ?? ''), (string)($body['name'] ?? '')));
    } catch (Throwable $e) {
        public_api_json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

if ($resource === 'path_rw_test' && $method === 'POST') {
    public_api_require_role(2);
    try {
        public_api_json(['ok' => true] + resticPathRwTest((string)($body['path'] ?? '')));
    } catch (Throwable $e) {
        public_api_json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

public_api_json(['ok' => false, 'error' => 'unknown_backup_action'], 404);
