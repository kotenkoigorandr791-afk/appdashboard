<?php
/**
 * Backup Manager — request handlers (JSON API + standalone page).
 */
declare(strict_types=1);

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/api.php';

function backupApiGuard(): void {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') return;
    if (function_exists('apSecRequireSameOrigin')) {
        apSecRequireSameOrigin();
    }
    if (function_exists('apSecCsrfCheck')) {
        $expected = $_SESSION['_csrf_token'] ?? '';
        if (is_string($expected) && $expected !== '' && !apSecCsrfCheck()) {
            backupJsonResponse(['ok' => false, 'error' => 'csrf_forbidden'], 403);
        }
    }
}

function backupApiConfigFromInput(): array {
    $input = backupRequestData();
    $cfg = resticLoadConfig();
    $profileId = trim((string)($input['profile'] ?? $_GET['profile'] ?? ''));
    $repo = resticRepoConfig($cfg, $profileId);
    if (isset($input['repository']) || array_key_exists('password', $input) || isset($input['host'])) {
        $repo['repository'] = trim((string)($input['repository'] ?? $repo['repository'] ?? ''));
        if (!resticValidRepository($repo['repository'])) {
            throw new InvalidArgumentException('Invalid repository path or URL');
        }
        $password = (string)($input['password'] ?? '');
        if ($password !== '') $repo['password'] = $password;
        $host = trim((string)($input['host'] ?? ''));
        if ($host !== '' && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $host)) $repo['host'] = $host;
    }
    return $repo;
}

/** Which backup's repository an operation targets (explicit id or the default). */
function backupActiveProfileId(array $cfg, array $input = []): string {
    $id = trim((string)($input['profile'] ?? $_GET['profile'] ?? $_POST['profile'] ?? ''));
    if ($id !== '' && resticFindProfile($cfg, $id) !== null) return $id;
    return resticActiveProfileId($cfg);
}

function backupHandleApi(string $api): void {
    backupApiGuard();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $input = backupRequestData();

    try {
        switch ($api) {
            case 'bootstrap':
                $cfg = resticLoadConfig();
                $activeId = backupActiveProfileId($cfg, $input);
                $repoCfg = resticRepoConfig($cfg, $activeId);
                $snapshots = [];
                $snapErr = '';
                if ((string)($repoCfg['repository'] ?? '') !== '' && (string)($repoCfg['password'] ?? '') !== '') {
                    try { $snapshots = resticSnapshots($repoCfg); }
                    catch (Throwable $e) { $snapErr = $e->getMessage(); }
                }
                $state = resticLoadScheduleState();
                backupJsonResponse([
                    'ok' => true,
                    'config' => resticPublicConfig($cfg),
                    'active_profile' => $activeId,
                    'snapshots' => $snapshots,
                    'snapshots_error' => $snapErr,
                    'jobs' => resticJobs(10),
                    'schedule_state' => $state,
                ]);
                return;

            case 'config':
                if ($method === 'GET') {
                    backupJsonResponse(['ok' => true, 'config' => resticPublicConfig(resticLoadConfig()), 'schedule_state' => resticLoadScheduleState()]);
                }
                $cfg = resticSaveConfig($input);
                adminAuditLog('backup_config_save', (string)$cfg['repository'], 'ok');
                backupJsonResponse(['ok' => true, 'config' => resticPublicConfig($cfg), 'message' => bt('backup_config_saved')]);
                return;

            case 'config_delete':
                resticClearConfig();
                adminAuditLog('backup_config_delete', '', 'ok');
                backupJsonResponse(['ok' => true, 'message' => bt('backup_delete_config_done')]);
                return;

            case 'test':
                $cfg = backupApiConfigFromInput();
                $res = resticTestRepository($cfg);
                backupJsonResponse($res, $res['ok'] ? 200 : 400);
                return;

            case 'init':
                $cfg = backupApiConfigFromInput();
                resticInitRepository($cfg);
                adminAuditLog('backup_repo_init', (string)($cfg['repository'] ?? ''), 'ok');
                backupJsonResponse(['ok' => true, 'message' => bt('backup_repo_initialized')]);
                return;

            case 'snapshots':
                $cfg = resticLoadConfig();
                backupJsonResponse(['ok' => true, 'snapshots' => resticSnapshots(resticRepoConfig($cfg, backupActiveProfileId($cfg, $input)))]);
                return;

            case 'stats':
                $cfg = resticLoadConfig();
                backupJsonResponse(['ok' => true, 'stats' => resticStats(resticRepoConfig($cfg, backupActiveProfileId($cfg, $input)))]);
                return;

            case 'ls':
                $snapshot = (string)($_GET['snapshot'] ?? '');
                $path = (string)($_GET['path'] ?? '');
                $cfg = resticLoadConfig();
                backupJsonResponse(['ok' => true] + resticList(resticRepoConfig($cfg, backupActiveProfileId($cfg, $input)), $snapshot, $path));
                return;

            case 'backups':
                backupJsonResponse(['ok' => true, 'backups' => resticProfilesPublic(resticLoadConfig()['backups'] ?? [])]);
                return;

            case 'backup_create':
                $profile = resticCreateProfile($input);
                adminAuditLog('backup_profile_create', (string)$profile['id'], 'ok');
                backupJsonResponse(['ok' => true, 'backup' => $profile, 'backups' => resticProfilesPublic(resticLoadConfig()['backups'] ?? []), 'message' => bt('backup_created')]);
                return;

            case 'backup_update':
                $profileId = trim((string)($input['id'] ?? ''));
                if ($profileId === '') throw new InvalidArgumentException('backup_not_found');
                $profile = resticUpdateProfile($profileId, $input);
                adminAuditLog('backup_profile_update', $profileId, 'ok');
                backupJsonResponse(['ok' => true, 'backup' => $profile, 'backups' => resticProfilesPublic(resticLoadConfig()['backups'] ?? []), 'message' => bt('backup_config_saved')]);
                return;

            case 'backup_delete':
                $profileId = trim((string)($input['id'] ?? $_GET['id'] ?? ''));
                if ($profileId === '') throw new InvalidArgumentException('backup_not_found');
                resticDeleteProfile($profileId);
                adminAuditLog('backup_profile_delete', $profileId, 'ok');
                backupJsonResponse(['ok' => true, 'backups' => resticProfilesPublic(resticLoadConfig()['backups'] ?? []), 'message' => bt('backup_deleted')]);
                return;

            case 'backup':
                $cfg = resticLoadConfig();
                $profileId = trim((string)($input['profile'] ?? $_GET['profile'] ?? ''));
                $spawnParams = ['trigger' => 'manual'];
                if ($profileId !== '') {
                    $profile = resticFindProfile($cfg, $profileId);
                    if ($profile === null) throw new InvalidArgumentException('backup_not_found');
                    if (empty($profile['sources'])) throw new RuntimeException(bt('backup_no_sources'));
                    // Check that this backup has a configured repository/password.
                    $profileRepo = resticRepoConfig($cfg, $profileId);
                    resticRequireConfigured($profileRepo);
                    $spawnParams += [
                        'profile' => (string)$profile['id'],
                        'profile_name' => (string)$profile['name'],
                        'sources' => $profile['sources'],
                        'excludes' => $profile['excludes'],
                        'retention' => $profile['retention'],
                    ];
                } elseif (!empty($cfg['backups'])) {
                    // With the named list in place every run belongs to a backup.
                    throw new RuntimeException(bt('backup_select_backup'));
                } elseif (empty($cfg['sources'])) {
                    throw new RuntimeException(bt('backup_no_sources'));
                } else {
                    // Legacy single-repo mode: validate global config.
                    resticRequireConfigured($cfg);
                }
                $job = resticJobSpawn('backup', $spawnParams);
                $runRepo = resticRepoConfig($cfg, (string)($spawnParams['profile'] ?? ''));
                adminAuditLog('backup_run', (string)($runRepo['repository'] ?? ''), 'ok');
                backupJsonResponse(['ok' => true, 'job' => $job, 'message' => bt('backup_run_started')], 202);
                return;

            case 'restore':
                $snapshot = trim((string)($input['snapshot'] ?? ''));
                if (!preg_match('/^[a-f0-9]{4,64}$/i', $snapshot)) throw new InvalidArgumentException('Invalid snapshot id');
                $target = trim((string)($input['target'] ?? ''));
                if ($target === '' || $target[0] !== '/' || str_contains($target, "\0")) {
                    throw new InvalidArgumentException(bt('backup_target_required'));
                }
                $job = resticJobSpawn('restore', [
                    'snapshot' => $snapshot,
                    'target' => $target,
                    'include' => trim((string)($input['include'] ?? '')),
                    'profile' => backupActiveProfileId(resticLoadConfig(), $input),
                ]);
                adminAuditLog('backup_restore', $snapshot, 'ok', ['target' => $target]);
                backupJsonResponse(['ok' => true, 'job' => $job, 'message' => bt('backup_restore_started')], 202);
                return;

            case 'forget':
                $forgetCfg = resticLoadConfig();
                $params = ['profile' => backupActiveProfileId($forgetCfg, $input)];
                if (is_array($input['retention'] ?? null)) {
                    $params['retention'] = $input['retention'];
                }
                $forgetProfile = trim((string)($input['profile'] ?? ''));
                if ($forgetProfile !== '') {
                    $foundProfile = resticFindProfile($forgetCfg, $forgetProfile);
                    if ($foundProfile === null) throw new InvalidArgumentException('backup_not_found');
                    $params['profile'] = (string)$foundProfile['id'];
                    $params['retention'] = $foundProfile['retention'];
                }
                $job = resticJobSpawn('forget', $params);
                adminAuditLog('backup_forget', '', 'ok');
                backupJsonResponse(['ok' => true, 'job' => $job, 'message' => bt('backup_job_queued')], 202);
                return;

            case 'prune':
                backupJsonResponse(['ok' => true, 'job' => resticJobSpawn('prune', ['profile' => backupActiveProfileId(resticLoadConfig(), $input)]), 'message' => bt('backup_job_queued')], 202);
                return;

            case 'check':
                $job = resticJobSpawn('check', ['read_data' => !empty($input['read_data']), 'profile' => backupActiveProfileId(resticLoadConfig(), $input)]);
                backupJsonResponse(['ok' => true, 'job' => $job, 'message' => bt('backup_job_queued')], 202);
                return;

            case 'unlock':
                backupJsonResponse(['ok' => true, 'job' => resticJobSpawn('unlock', ['profile' => backupActiveProfileId(resticLoadConfig(), $input)]), 'message' => bt('backup_job_queued')], 202);
                return;

            case 'rebuild_index':
                backupJsonResponse(['ok' => true, 'job' => resticJobSpawn('rebuild_index', ['profile' => backupActiveProfileId(resticLoadConfig(), $input)]), 'message' => bt('backup_job_queued')], 202);
                return;

            case 'delete_snapshot':
                $snapshot = trim((string)($input['snapshot'] ?? ''));
                if (!preg_match('/^[a-f0-9]{4,64}$/i', $snapshot)) throw new InvalidArgumentException('Invalid snapshot id');
                $job = resticJobSpawn('delete_snapshot', ['snapshot' => $snapshot, 'profile' => backupActiveProfileId(resticLoadConfig(), $input)]);
                adminAuditLog('backup_snapshot_delete', $snapshot, 'ok');
                backupJsonResponse(['ok' => true, 'job' => $job, 'message' => bt('backup_job_queued')], 202);
                return;

            case 'jobs':
                backupJsonResponse(['ok' => true, 'jobs' => resticJobs(30)]);
                return;

            case 'job':
                $id = (string)($_GET['id'] ?? '');
                $job = resticJobRead($id);
                if ($job === []) backupJsonResponse(['ok' => false, 'error' => 'Job not found'], 404);
                backupJsonResponse(['ok' => true, 'job' => resticJobPublic($job), 'log' => resticJobTail($id)]);
                return;

            case 'job_cancel':
                $id = (string)($input['id'] ?? $_GET['id'] ?? '');
                backupJsonResponse(['ok' => true, 'job' => resticJobCancel($id), 'message' => bt('backup_job_cancel_requested')]);
                return;

            case 'disk':
                backupJsonResponse(['ok' => true] + resticDiskSpace((string)($_GET['path'] ?? resticHostRoot())));
                return;

            case 'path_rw_test':
                backupJsonResponse(['ok' => true] + resticPathRwTest((string)($input['path'] ?? $_GET['path'] ?? '')));
                return;

            case 'host_fs':
                $path = (string)($_GET['path'] ?? resticHostRoot());
                $onlyFolders = (string)($_GET['onlyFolders'] ?? 'true') !== 'false';
                $showHidden = (string)($_GET['showHidden'] ?? 'false') === 'true';
                backupJsonResponse(['ok' => true] + resticHostBrowse($path, $onlyFolders, $showHidden));
                return;

            case 'host_fs_mkdir':
                backupJsonResponse(['ok' => true] + resticHostMkdir((string)($input['parent'] ?? ''), (string)($input['name'] ?? '')));
                return;

            case 'host_ips':
                backupJsonResponse(['ok' => true, 'ips' => resticHostIps(), 'root' => resticHostRoot()]);
                return;

            case 'status':
                $cfg = resticLoadConfig();
                $activeId = backupActiveProfileId($cfg, $input);
                $statusRepo = resticRepoConfig($cfg, $activeId);
                $configured = (string)($statusRepo['repository'] ?? '') !== '';
                $repoOk = false;
                $repoError = '';
                if ($configured && (string)($statusRepo['password'] ?? '') !== '') {
                    $test = resticTestRepository($statusRepo);
                    $repoOk = (bool)$test['ok'];
                    $repoError = (string)($test['error'] ?? '');
                }
                backupJsonResponse([
                    'ok' => true,
                    'active_profile' => $activeId,
                    'configured' => $configured,
                    'repo_ok' => $repoOk,
                    'repo_error' => $repoError,
                    'config' => resticPublicConfig($cfg),
                    'schedule_state' => resticLoadScheduleState(),
                    'jobs' => resticJobs(5),
                ]);
                return;

            default:
                backupJsonResponse(['ok' => false, 'error' => 'Unknown API action'], 404);
        }
    } catch (InvalidArgumentException $e) {
        backupJsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        backupJsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

$backupApi = isset($_GET['api']) ? (string)$_GET['api'] : '';
if ($backupApi !== '') {
    backupHandleApi($backupApi);
    exit;
}

// --- Standalone page -------------------------------------------------------
$bkpLang = backup_boot_i18n($_GET['lang'] ?? 'en');
require_once __DIR__ . '/views.php';
ob_start();
require dirname(__DIR__) . '/views/modals/backup_manager.php';
$backupPanelHtml = (string)ob_get_clean();
backupViewShell(bt('backup_title'), $backupPanelHtml, $bkpLang);
