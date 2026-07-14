<?php
declare(strict_types=1);
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../auth_common.php';
require_once __DIR__ . '/fit_takeout_state.php';
$safeName = get_current_user_folder();
define('FIT_USER_DIR', __DIR__ . '/../../users/' . $safeName);
define('FIT_JOBS_DIR', FIT_USER_DIR . '/fit_takeout_jobs/');
define('FIT_UPLOAD_SESSIONS_DIR', __DIR__ . '/../../upload_sessions');
if (!is_dir(FIT_JOBS_DIR)) {
    @mkdir(FIT_JOBS_DIR, 0777, true);
}
if (!is_dir(FIT_UPLOAD_SESSIONS_DIR)) {
    @mkdir(FIT_UPLOAD_SESSIONS_DIR, 0777, true);
}
function fit_chunk_session_paths(string $uploadId): array {
    $safe = preg_replace('/[^a-z0-9]/i', '', strtolower($uploadId));
    return [
        FIT_UPLOAD_SESSIONS_DIR . DIRECTORY_SEPARATOR . 'takeout_' . $safe . '.bin',
        FIT_UPLOAD_SESSIONS_DIR . DIRECTORY_SEPARATOR . 'takeout_' . $safe . '.json',
    ];
}
function fit_chunk_read_meta(string $metaPath): array {
    $raw = @file_get_contents($metaPath);
    $meta = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($meta) ? $meta : [];
}
function fit_chunk_write_meta(string $metaPath, array $meta): bool {
    return @file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}
function fit_chunk_move_to_path(string $uploadId, string $targetPath): bool {
    [$dataPath, $metaPath] = fit_chunk_session_paths($uploadId);
    if (!is_file($dataPath)) {
        return false;
    }
    if (@rename($dataPath, $targetPath)) {
        @unlink($metaPath);
        return true;
    }
    if (@copy($dataPath, $targetPath)) {
        @unlink($dataPath);
        @unlink($metaPath);
        return true;
    }
    return false;
}
function fit_delete_tree(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if (!is_array($items)) {
        @rmdir($dir);
        return;
    }
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') {
            continue;
        }
        $p = $dir . DIRECTORY_SEPARATOR . $it;
        if (is_dir($p) && !is_link($p)) {
            fit_delete_tree($p);
        } else {
            @unlink($p);
        }
    }
    @rmdir($dir);
}
function fit_find_python(): string {
    $candidates = [];
    $interpreterFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'python_interpreter.txt';
    if (is_file($interpreterFile)) {
        $line = trim((string)@file_get_contents($interpreterFile));
        if ($line !== '' && is_file($line)) {
            $candidates[] = $line;
        }
    }
    if (DIRECTORY_SEPARATOR === '\\' && is_file('C:\\Compilers\\Python\\python.exe')) {
        $candidates[] = 'C:\\Compilers\\Python\\python.exe';
    }
    $candidates = array_merge($candidates, [dirname(__DIR__) . '/venv/Scripts/python.exe', dirname(__DIR__) . '/.venv/Scripts/python.exe', 'py', 'python', 'python3']);
    foreach ($candidates as $p) {
        if (strpos($p, '/') !== false || strpos($p, '\\') !== false) {
            if (is_file($p)) {
                return $p;
            }
        } else {
            $o = [];
            @exec(escapeshellcmd($p) . ' -c ' . escapeshellarg('print(1)') . ' 2>nul', $o, $rc);
            if ((int)$rc === 0) {
                return $p;
            }
        }
    }
    return 'python';
}
function fit_spawn_worker(string $jobPath): bool {
    $py = fit_find_python();
    if (strpos($py, ' ') === false) {
        $pyReal = realpath($py);
        if ($pyReal) {
            $py = $pyReal;
        }
    }
    $script = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'takeout_import_worker.py');
    if ($script === false || !is_file($script)) {
        return false;
    }
    $jobPath = realpath($jobPath) ?: $jobPath;
    $log  = $jobPath . DIRECTORY_SEPARATOR . 'worker.log';
    if (DIRECTORY_SEPARATOR === '\\' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $pys = str_replace('/', '\\', $py);
        $sjs = str_replace('/', '\\', $script);
        $jbs = str_replace('/', '\\', $jobPath);
        $lgs = str_replace('/', '\\', $log);
        $bat = $jobPath . DIRECTORY_SEPARATOR . 'run_fit_takeout_worker.cmd';
        $c   = '@echo off' . "\r\n" . '"' . $pys . '" "' . $sjs . '" "' . $jbs . '" 1>>"' . $lgs . '" 2>&1' . "\r\n";
        if (@file_put_contents($bat, $c) === false) {
            return false;
        }
        $bats = str_replace('/', '\\', $bat);
        if (class_exists('COM', false)) {
            try {
                $s = new COM('WScript.Shell');
                $s->Run('cmd /c "' . $bats . '"', 0, false);
                return true;
            } catch (Exception $e) {
            }
        }
        pclose(popen('cmd /c start /B "" "' . $bats . '"', 'r'));
        return true;
    }
    $cmd = sprintf('nohup %s %s %s >/dev/null 2>&1 &', escapeshellarg($py), escapeshellarg($script), escapeshellarg($jobPath));
    exec($cmd);
    return true;
}
require_once __DIR__ . '/../../core/db_helpers.php';
function fit_notify_job_once(AppDatabase $db, string $jobId, array $state): array {
    $status = (string)($state['status'] ?? '');
    if (!in_array($status, ['done','error'], true) || !empty($state['notification_sent'])) return $state;
    $email = get_current_user_email() ?: '';
    $title = $status === 'done' ? 'Google Fit import finished' : 'Google Fit import failed';
    $message = $status === 'done' ? ('Imported: ' . (int)($state['imported'] ?? 0) . ' days') : (string)($state['message'] ?? 'worker_error');
    app_notify($email, 'steps', $status === 'done' ? 'success' : 'error', $title, $message, 'worker_finished', ['job_id' => $jobId, 'kind' => 'google_fit_takeout']);
    app_metric($email, 'steps', 'fit_takeout_finished', 1, 'job', ['job_id' => $jobId, 'status' => $status, 'imported' => (int)($state['imported'] ?? 0)]);
    $state['notification_sent'] = 1;
    try { fit_takeout_save($db, $jobId, $state); } catch (Throwable $e) {}
    return $state;
}
try {
    $db = getUserDB(FIT_USER_DIR);
    if (!$db) {
        echo json_encode(['ok' => false, 'error' => 'db']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'db']);
    exit;
}
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($action === 'upload_chunk' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $uploadId = preg_replace('/[^a-z0-9]/i', '', (string)($_POST['upload_id'] ?? ''));
    $chunkIndex = (int)($_POST['chunk_index'] ?? -1);
    $chunkTotal = (int)($_POST['chunk_total'] ?? 0);
    $fileSize = (int)($_POST['file_size'] ?? 0);
    $fileName = (string)($_POST['file_name'] ?? '');
    $kind = (string)($_POST['file_kind'] ?? 'takeout');
    if ($kind !== 'takeout' || strlen($uploadId) < 12 || $chunkIndex < 0 || $chunkTotal <= 0 || !isset($_FILES['chunk'])) {
        echo json_encode(['ok' => false, 'error' => 'chunk_params']);
        exit;
    }
    if ((int)($_FILES['chunk']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'chunk_upload', 'file_err' => (int)($_FILES['chunk']['error'] ?? 0)]);
        exit;
    }
    [$dataPath, $metaPath] = fit_chunk_session_paths($uploadId);
    $meta = fit_chunk_read_meta($metaPath);
    $next = (int)($meta['next_index'] ?? 0);
    if ($chunkIndex === 0) {
        @unlink($dataPath);
        $meta = [];
        $next = 0;
    }
    if ($chunkIndex !== $next) {
        echo json_encode(['ok' => false, 'error' => 'chunk_order', 'next_index' => $next]);
        exit;
    }
    $tmp = (string)($_FILES['chunk']['tmp_name'] ?? '');
    $in = @fopen($tmp, 'rb');
    $out = @fopen($dataPath, 'ab');
    if (!$in || !$out) {
        if (is_resource($in)) fclose($in);
        if (is_resource($out)) fclose($out);
        echo json_encode(['ok' => false, 'error' => 'chunk_io']);
        exit;
    }
    stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);
    $received = (int)@filesize($dataPath);
    $meta = [
        'next_index' => $chunkIndex + 1,
        'chunk_total' => $chunkTotal,
        'file_size' => $fileSize > 0 ? $fileSize : $received,
        'file_name' => $fileName,
        'updated_at' => time(),
    ];
    if (!fit_chunk_write_meta($metaPath, $meta)) {
        echo json_encode(['ok' => false, 'error' => 'chunk_meta']);
        exit;
    }
    $done = ($chunkIndex + 1) >= $chunkTotal;
    echo json_encode(['ok' => true, 'done' => $done, 'upload_id' => $uploadId, 'received' => $received]);
    exit;
}
if ($action === 'active_job' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $ft = fit_takeout_load($db);
    $st = $ft['state'];
    $mt = (int)($ft['updated_at'] ?? 0);
    $jid = (string)($ft['job_id'] ?? '');
    if (is_array($st) && ($st['status'] ?? '') === 'running' && $mt > 0 && (time() - $mt) <= 1800 && strlen($jid) >= 8) {
        echo json_encode(['ok' => true, 'active' => ['job_id' => $jid, 'state' => $st]]);
        exit;
    }
    echo json_encode(['ok' => true, 'active' => null]);
    exit;
}
if ($action === 'job_status' && isset($_GET['job'])) {
    $jid = preg_replace('/[^a-f0-9]/i', '', (string)$_GET['job']);
    if (strlen($jid) < 8) {
        echo json_encode(['ok' => false, 'error' => 'invalid job']);
        exit;
    }
    $ft = fit_takeout_load($db);
    if (($ft['job_id'] ?? '') !== $jid) {
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }
    $st = $ft['state'];
    $mt = (int)($ft['updated_at'] ?? 0);
    if (($st['status'] ?? '') === 'running' && $mt > 0 && (time() - $mt) > 1800) {
        $st = array_merge($st, ['status' => 'error', 'message' => 'stale_job']);
    }
    $st = fit_notify_job_once($db, $jid, $st);
    echo json_encode(['ok' => true, 'state' => $st]);
    exit;
}
if ($action === 'stop_job' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $jid = preg_replace('/[^a-f0-9]/i', '', (string)($_POST['job'] ?? ''));
    if (strlen($jid) < 8) {
        echo json_encode(['ok' => false, 'error' => 'invalid job']);
        exit;
    }
    $ft = fit_takeout_load($db);
    if (($ft['job_id'] ?? '') !== $jid) {
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }
    $jobPath = FIT_JOBS_DIR . $jid;
    if (is_dir($jobPath)) {
        @file_put_contents($jobPath . DIRECTORY_SEPARATOR . 'cancel.flag', '1');
    }
    $st = $ft['state'];
    if (!is_array($st)) {
        $st = [];
    }
    try {
        fit_takeout_save($db, $jid, array_merge($st, ['status' => 'cancelled', 'message' => 'cancelled', 'job_id' => $jid]));
    } catch (Exception $e) {
    }
    try {
        fit_takeout_reset($db);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true]);
    exit;
}
if ($action === 'resolve_conflict' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $jid = preg_replace('/[^a-f0-9]/i', '', (string)($_POST['job'] ?? ''));
    $mode = strtolower(trim((string)($_POST['mode'] ?? '')));
    if (strlen($jid) < 8 || !in_array($mode, ['keep', 'overwrite', 'new_only'], true)) {
        echo json_encode(['ok' => false, 'error' => 'invalid']);
        exit;
    }
    $ft = fit_takeout_load($db);
    if (($ft['job_id'] ?? '') !== $jid) {
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }
    $st = is_array($ft['state'] ?? null) ? $ft['state'] : [];
    if (($st['status'] ?? '') !== 'awaiting_conflict') {
        echo json_encode(['ok' => false, 'error' => 'state']);
        exit;
    }
    $jobPath = FIT_JOBS_DIR . $jid;
    $cfgPath = $jobPath . '/job_config.json';
    if (!is_file($cfgPath)) {
        echo json_encode(['ok' => false, 'error' => 'config']);
        exit;
    }
    $cfg = json_decode((string)@file_get_contents($cfgPath), true);
    if (!is_array($cfg)) {
        $cfg = [];
    }
    $cfg['conflict_mode'] = $mode;
    if (@file_put_contents($cfgPath, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        echo json_encode(['ok' => false, 'error' => 'config_write']);
        exit;
    }
    try {
        fit_takeout_save($db, $jid, array_merge($st, ['status' => 'running', 'message' => 'apply', 'progress' => 93.0, 'job_id' => $jid]));
    } catch (Exception $e) {
    }
    if (!fit_spawn_worker($jobPath)) {
        echo json_encode(['ok' => false, 'error' => 'worker']);
        exit;
    }
    echo json_encode(['ok' => true, 'job_id' => $jid]);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $action !== 'upload_takeout') {
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}
if (!isset($_FILES['takeout']) && !isset($_POST['takeout_token'])) {
    echo json_encode(['ok' => false, 'error' => 'upload']);
    exit;
}
$token = preg_replace('/[^a-z0-9]/i', '', (string)($_POST['takeout_token'] ?? ''));
$name = isset($_FILES['takeout']) ? (string)($_FILES['takeout']['name'] ?? '') : '';
if ($name === '' && $token !== '') {
    [, $metaPath] = fit_chunk_session_paths($token);
    $meta = fit_chunk_read_meta($metaPath);
    $name = (string)($meta['file_name'] ?? '');
}
if (!preg_match('/\.zip$/i', $name)) {
    echo json_encode(['ok' => false, 'error' => 'not_zip']);
    exit;
}
try {
    fit_takeout_save($db, '', ['status' => 'idle', 'progress' => 0, 'message' => '', 'imported' => 0, 'total_days' => 0, 'job_id' => '']);
} catch (Exception $e) {
}
$jobId = bin2hex(random_bytes(8));
$jobPath = FIT_JOBS_DIR . $jobId;
if (!@mkdir($jobPath, 0777, true) && !is_dir($jobPath)) {
    echo json_encode(['ok' => false, 'error' => 'mkdir']);
    exit;
}
$zipFile = $jobPath . '/takeout.zip';
$moved = false;
if ($token !== '') {
    $moved = fit_chunk_move_to_path($token, $zipFile);
} elseif (isset($_FILES['takeout']) && (int)($_FILES['takeout']['error'] ?? 1) === UPLOAD_ERR_OK) {
    $moved = move_uploaded_file($_FILES['takeout']['tmp_name'], $zipFile);
}
if (!$moved) {
    echo json_encode(['ok' => false, 'error' => 'move']);
    exit;
}
$cfg = ['user_sqlite_path' => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $userSqlite), 'job_id' => $jobId, 'conflict_mode' => 'ask'];
if (@file_put_contents($jobPath . '/job_config.json', json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
    @unlink($zipFile);
    echo json_encode(['ok' => false, 'error' => 'config']);
    exit;
}
$state0 = [
    'status' => 'running', 'progress' => 0.0, 'message' => 'queued', 'imported' => 0, 'total_days' => 0, 'results' => null, 'job_id' => $jobId,
];
try {
    fit_takeout_save($db, $jobId, $state0);
} catch (Exception $e) {
    @unlink($zipFile);
    echo json_encode(['ok' => false, 'error' => 'db_save']);
    exit;
}
$okW = fit_spawn_worker($jobPath);
if (!$okW) {
    try {
        fit_takeout_save($db, $jobId, array_merge($state0, ['status' => 'error', 'message' => 'python not started']));
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => false, 'error' => 'worker', 'job_id' => $jobId]);
    exit;
}
echo json_encode(['ok' => true, 'job_id' => $jobId]);
