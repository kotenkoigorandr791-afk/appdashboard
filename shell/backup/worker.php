<?php
/**
 * Backup Manager — restic job worker.
 *
 * Usage: php project/backup/worker.php <job-id>
 *
 * Runs one queued job, mirrors restic output into the job log, and updates the
 * job JSON so the web UI can poll progress. The repository password only lives
 * in the job file for the duration of the run and is removed afterwards.
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) define('APP_ROOT', $root);
require_once $root . '/db.php';
require_once __DIR__ . '/i18n.php';
backup_boot_i18n('en');
require_once __DIR__ . '/api.php';

$jobId = isset($argv[1]) ? (string)$argv[1] : '';
if (!preg_match('/^[a-f0-9]{16}$/', $jobId)) {
    fwrite(STDERR, "Invalid job id\n");
    exit(2);
}

$job = resticJobRead($jobId);
if ($job === []) {
    fwrite(STDERR, "Job not found: $jobId\n");
    exit(2);
}

$finish = static function (array $job, string $status, string $error = '') use ($jobId): void {
    $job['status'] = $status;
    $job['finished_at'] = time();
    $job['error'] = $error;
    $job['restic_pid'] = 0;
    $job['worker_pid'] = 0;
    unset($job['params']['password'], $job['password'], $job['_last_write']);
    resticJobWrite($job);
    resticJobAppendLog($jobId, $status . ($error !== '' ? ': ' . $error : ''));
};

if (!empty($job['cancel_requested'])) {
    $finish($job, 'cancelled');
    exit(0);
}

$job['status'] = 'running';
$job['started_at'] = time();
$job['worker_pid'] = getmypid();
resticJobWrite($job);
resticJobAppendLog($jobId, 'running ' . (string)($job['type'] ?? ''));

$finished = false;
register_shutdown_function(static function () use (&$job, &$finished, $finish): void {
    if ($finished) return;
    $err = error_get_last();
    $message = is_array($err) && isset($err['message']) ? (string)$err['message'] : 'Worker terminated unexpectedly';
    $finish($job, 'error', $message);
});

try {
    $run = resticRunJob($job);
    $job = $run['job'];
    $result = $run['result'];
    $code = (int)($result['code'] ?? 0);

    // Restic may need a fallback for older versions without `repair index`.
    if ($code !== 0 && ($job['type'] ?? '') === 'rebuild_index') {
        $errText = (string)($result['stderr'] ?? '');
        if (stripos($errText, 'unknown command') !== false || stripos($errText, 'repair') !== false) {
            resticJobAppendLog($jobId, 'falling back to rebuild-index');
            $fallback = resticRun(['rebuild-index'], resticRepoEnv($job['params']), 0, static function (string $line) use (&$job): void {
                resticJobAppendLog((string)$job['id'], $line, false);
            });
            $result = $fallback;
            $code = (int)($fallback['code'] ?? 0);
        }
    }

    $job = resticJobRead($jobId) ?: $job;
    if (!empty($job['cancel_requested']) || $code === 143 || $code === -15) {
        $finish($job, 'cancelled');
    } elseif ($code === 0) {
        $job['progress']['percent'] = 100;
        $finish($job, 'success');
    } else {
        $finish($job, 'error', resticErrorFromResult($result));
    }
    $finished = true;
} catch (Throwable $e) {
    $finish($job, 'error', $e->getMessage());
    $finished = true;
}
