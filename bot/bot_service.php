<?php
require_once dirname(__DIR__) . '/db.php';
function botBackupCryptKeyBinary(): string {
    $env = getenv('BACKUP_DASHBOARD_CRYPT_KEY');
    if ($env !== false && $env !== '') return hash('sha256', $env, true);
    return hash('sha256', getConfig('backup_dashboard_data_key', ''), true);
}
function botBackupDecryptPayload(string $json): ?string {
    $w = json_decode($json, true);
    if (!is_array($w) || !isset($w['iv'], $w['tag']) || !array_key_exists('ct', $w)) return null;
    $iv = base64_decode((string)$w['iv'], true);
    $ct = base64_decode((string)$w['ct'], true);
    $tag = base64_decode((string)$w['tag'], true);
    if ($iv === false || $ct === false || $tag === false) return null;
    $pt = openssl_decrypt($ct, 'aes-256-gcm', botBackupCryptKeyBinary(), OPENSSL_RAW_DATA, $iv, $tag, '');
    return $pt === false ? null : $pt;
}
function botResticStatus(): array {
    require_once dirname(__DIR__) . '/project/backup/i18n.php';
    backup_boot_i18n('en');
    require_once dirname(__DIR__) . '/project/backup/api.php';
    $cfg = resticLoadConfig();
    $profiles = [];
    foreach ((array)($cfg['backups'] ?? []) as $profile) {
        if (!is_array($profile)) continue;
        $id = (string)($profile['id'] ?? '');
        $repo = resticRepoConfig($cfg, $id);
        $item = [
            'id' => $id,
            'name' => (string)($profile['name'] ?? 'Unnamed'),
            'repository' => (string)($repo['repository'] ?? ''),
            'sources' => array_values((array)($profile['sources'] ?? [])),
            'enabled' => !empty($profile['enabled']),
            'schedule' => (array)($profile['schedule'] ?? []),
            'snapshots' => 0,
            'last_snapshot' => '',
            'error' => '',
        ];
        if ((string)($repo['repository'] ?? '') !== '' && (string)($repo['password'] ?? '') !== '') {
            try {
                $snaps = resticSnapshots($repo);
                $item['snapshots'] = count($snaps);
                $item['last_snapshot'] = (string)(($snaps[0]['time'] ?? '') ?: '');
            } catch (Throwable $e) {
                $item['error'] = $e->getMessage();
            }
        }
        $profiles[] = $item;
    }
    $state = resticLoadScheduleState();
    $jobs = [];
    foreach (resticJobs(10) as $job) {
        if (!is_array($job)) continue;
        $jobs[] = [
            'id' => (string)($job['id'] ?? ''),
            'type' => (string)($job['type'] ?? ''),
            'status' => (string)($job['status'] ?? ''),
            'started_at' => backupSafeInt($job['started_at'] ?? 0),
            'finished_at' => backupSafeInt($job['finished_at'] ?? 0),
        ];
    }
    return [
        'engine' => 'restic',
        'configured' => (string)($cfg['repository'] ?? '') !== '' || $profiles !== [],
        'repository' => (string)($cfg['repository'] ?? ''),
        'profiles' => $profiles,
        'jobs' => $jobs,
        'schedule_state' => $state,
        'restic_version' => resticVersion(),
    ];
}
$cmd = (string)($argv[1] ?? '');
if ($cmd === 'backup-credentials') {
    echo json_encode(['engine' => 'restic', 'host' => 'restic://local', 'token' => ''], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
if ($cmd === 'backup-status') {
    echo json_encode(botResticStatus(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
if ($cmd === 'docker-config') {
    echo json_encode(loadDockerConfig(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
fwrite(STDERR, "Unknown command\n");
exit(2);
