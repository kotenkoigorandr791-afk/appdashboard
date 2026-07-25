<?php
declare(strict_types=1);
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../auth_common.php';
require_once __DIR__ . '/zeep_state.php';
require_once __DIR__ . '/../../core/db_helpers.php';
require_once __DIR__ . '/../import_worker_common.php';
function zeep_notify_job_once(AppDatabase $db, string $jobId, array $state): array {
    $status = (string)($state['status'] ?? '');
    if (!in_array($status, ['done','error'], true) || !empty($state['notification_sent'])) return $state;
    $email = get_current_user_email() ?: '';
    $title = $status === 'done' ? 'Zeep Life import finished' : 'Zeep Life import failed';
    $message = $status === 'done' ? ('Imported: ' . (int)($state['imported'] ?? 0) . ' days') : (string)($state['message'] ?? 'worker_error');
    app_notify($email, 'steps', $status === 'done' ? 'success' : 'error', $title, $message, 'worker_finished', ['job_id' => $jobId, 'kind' => 'zeep_life']);
    app_metric($email, 'steps', 'zeep_life_finished', 1, 'job', ['job_id' => $jobId, 'status' => $status, 'imported' => (int)($state['imported'] ?? 0)]);
    $state['notification_sent'] = 1;
    try { zeep_job_save($db, $jobId, $state); } catch (Throwable $e) {}
    return $state;
}
$safeName = get_current_user_folder();
define('ZEEP_USER_DIR', __DIR__ . '/../../users/' . $safeName);
define('ZEEP_JOBS_DIR', ZEEP_USER_DIR . '/zeep_jobs/');
define('ZEEP_UPLOAD_SESSIONS_DIR', __DIR__ . '/../../upload_sessions');
if (!is_dir(ZEEP_JOBS_DIR)) {
    @mkdir(ZEEP_JOBS_DIR, 0777, true);
}
if (!is_dir(ZEEP_UPLOAD_SESSIONS_DIR)) {
    @mkdir(ZEEP_UPLOAD_SESSIONS_DIR, 0777, true);
}
@chmod(ZEEP_JOBS_DIR, 0777);
@chmod(ZEEP_UPLOAD_SESSIONS_DIR, 0777);
function zeep_chunk_session_paths(string $uploadId): array {
    $safe = preg_replace('/[^a-z0-9]/i', '', strtolower($uploadId));
    return [
        ZEEP_UPLOAD_SESSIONS_DIR . DIRECTORY_SEPARATOR . 'zeep_' . $safe . '.bin',
        ZEEP_UPLOAD_SESSIONS_DIR . DIRECTORY_SEPARATOR . 'zeep_' . $safe . '.json',
    ];
}
function zeep_chunk_read_meta(string $metaPath): array {
    $raw = @file_get_contents($metaPath);
    $meta = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($meta) ? $meta : [];
}
function zeep_chunk_write_meta(string $metaPath, array $meta): bool {
    return @file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}
function zeep_chunk_move_to_path(string $uploadId, string $targetPath): bool {
    [$dataPath, $metaPath] = zeep_chunk_session_paths($uploadId);
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
function zeep_delete_tree(string $dir): void {
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
            zeep_delete_tree($p);
        } else {
            @unlink($p);
        }
    }
    @rmdir($dir);
}
function zeep_php_cli(): string {
    $candidates = [];
    $phpBindir = defined('PHP_BINDIR') ? PHP_BINDIR : '';
    if ($phpBindir !== '') {
        $candidates[] = $phpBindir . DIRECTORY_SEPARATOR . 'php';
        $candidates[] = $phpBindir . DIRECTORY_SEPARATOR . 'php.exe';
    }
    $candidates[] = '/usr/bin/php';
    $candidates[] = '/usr/local/bin/php';
    $candidates[] = 'php';
    foreach ($candidates as $p) {
        if ($p === '') continue;
        if (strpos($p, '/') !== false || strpos($p, '\\') !== false) {
            if (!is_file($p) || !is_executable($p)) continue;
            $cmd = escapeshellarg($p) . ' -r ' . escapeshellarg('exit(PHP_SAPI==="cli"?0:1);');
        } else {
            $cmd = escapeshellcmd($p) . ' -r ' . escapeshellarg('exit(PHP_SAPI==="cli"?0:1);');
        }
        @exec($cmd, $o, $rc);
        if ((int)$rc === 0) return $p;
    }
    return 'php';
}
function zeep_spawn_worker(string $jobPath): bool {
    $php = zeep_php_cli();
    $script = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'zeep_import_worker.php');
    if ($script === false || !is_file($script)) {
        return false;
    }
    $jobPath = realpath($jobPath) ?: $jobPath;
    $log = $jobPath . DIRECTORY_SEPARATOR . 'worker.log';
    $statePath = $jobPath . DIRECTORY_SEPARATOR . 'state.json';
    @unlink($statePath);
    if (DIRECTORY_SEPARATOR === '\\' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $pys = str_replace('/', '\\', $php);
        $sjs = str_replace('/', '\\', $script);
        $jbs = str_replace('/', '\\', $jobPath);
        $lgs = str_replace('/', '\\', $log);
        $bat = $jobPath . DIRECTORY_SEPARATOR . 'run_zeep_worker.cmd';
        $c = '@echo off' . "\r\n" . '"' . $pys . '" "' . $sjs . '" "' . $jbs . '" 1>>"' . $lgs . '" 2>&1' . "\r\n";
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
    $cmd = sprintf('cd %s && nohup %s %s %s >>%s 2>&1 < /dev/null & echo $!', escapeshellarg($jobPath), escapeshellarg($php), escapeshellarg($script), escapeshellarg($jobPath), escapeshellarg($log));
    $out = [];
    $rc = 1;
    @exec($cmd, $out, $rc);
    $pid = (int)trim((string)($out[0] ?? ''));
    if ((int)$rc !== 0 || $pid <= 0) return false;
    @file_put_contents($jobPath . DIRECTORY_SEPARATOR . 'worker.pid', (string)$pid);
    for ($i = 0; $i < 20; $i++) {
        if (is_file($statePath)) return true;
        if (function_exists('posix_kill') && !@posix_kill($pid, 0)) return is_file($statePath);
        usleep(100000);
    }
    return is_file($statePath);
}
function zeep_run_worker_now(string $jobPath): bool {
    $php = zeep_php_cli();
    $script = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'zeep_import_worker.php');
    if ($script === false || !is_file($script)) {
        return false;
    }
    $jobPath = realpath($jobPath) ?: $jobPath;
    $log = $jobPath . DIRECTORY_SEPARATOR . 'worker.log';
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobPath) . ' >>' . escapeshellarg($log) . ' 2>&1';
    @exec($cmd, $out, $rc);
    return (int)$rc === 0;
}
function zeep_job_cfg(array $jobIdParts): array {
    $email = get_current_user_email() ?: ('folder_' . basename(ZEEP_USER_DIR));
    $userSchema = app_user_schema_id('steps', $email);
    $usql = realpath(ZEEP_USER_DIR . DIRECTORY_SEPARATOR . 'user_data.sqlite3');
    if ($usql === false) {
        $usql = ZEEP_USER_DIR . DIRECTORY_SEPARATOR . 'user_data.sqlite3';
    }
    return array_merge([
        'user_schema' => $userSchema,
        'user_sqlite_path' => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $usql),
        'user_email' => $email,
    ], $jobIdParts);
}
function zeep_load_parsed_rows(string $jobPath): array {
    $snap = $jobPath . '/parsed_day_steps.json';
    if (!is_file($snap)) {
        return [];
    }
    $obj = json_decode((string)@file_get_contents($snap), true);
    if (!is_array($obj)) {
        return [];
    }
    $rows = [];
    foreach ($obj as $d => $s) {
        $ds = (string)$d;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ds)) {
            continue;
        }
        $rows[] = ['date' => $ds, 'steps' => (int)$s];
    }
    usort($rows, static function ($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
    return $rows;
}
function zeep_load_file_state(string $jobPath): ?array {
    $path = $jobPath . DIRECTORY_SEPARATOR . 'state.json';
    if (!is_file($path)) {
        return null;
    }
    $state = json_decode((string)@file_get_contents($path), true);
    return is_array($state) ? $state : null;
}
function zeep_refresh_state_from_file(AppDatabase $db, string $jobId, array $state, int $updatedAt): array {
    $jobPath = ZEEP_JOBS_DIR . $jobId;
    $fileState = zeep_load_file_state($jobPath);
    if (!is_array($fileState)) {
        return $state;
    }
    $fileTime = (int)@filemtime($jobPath . DIRECTORY_SEPARATOR . 'state.json');
    $status = (string)($state['status'] ?? '');
    $fileStatus = (string)($fileState['status'] ?? '');
    if ($fileTime >= $updatedAt || in_array($status, zeep_active_statuses(), true) || $fileStatus !== '') {
        if (!isset($fileState['job_id']) || (string)$fileState['job_id'] === '') {
            $fileState['job_id'] = $jobId;
        }
        try { zeep_job_save($db, $jobId, $fileState); } catch (Throwable $e) {}
        return $fileState;
    }
    return $state;
}
function zeep_apply_parsed_job(AppDatabase $db, string $jobId, string $jobPath, array $state, string $mode): array {
    $daySteps = steps_worker_load_snapshot($jobPath . DIRECTORY_SEPARATOR . 'parsed_day_steps.json', true);
    if (!$daySteps) return ['ok' => false, 'error' => 'no_data'];
    if ($mode === 'ask') {
        $conf = steps_worker_conflicts_app_db($db, $daySteps);
        if ($conf > 0) {
            $next = array_merge($state, ['status' => 'awaiting_conflict', 'message' => 'conflict_prompt', 'progress' => 92.0, 'imported' => 0, 'total_days' => count($daySteps), 'conflict_days' => $conf, 'job_id' => $jobId]);
            zeep_job_save($db, $jobId, $next);
            return ['ok' => true, 'state' => $next];
        }
        $mode = 'overwrite';
    }
    $nimp = steps_worker_import_app_db($db, $daySteps, $mode, true);
    if ($nimp < 1) $nimp = count($daySteps);
    $next = ['status' => 'done', 'message' => 'ok', 'progress' => 100.0, 'imported' => $nimp, 'total_days' => count($daySteps), 'job_id' => $jobId];
    zeep_job_save($db, $jobId, $next);
    return ['ok' => true, 'state' => $next];
}
function zeep_active_statuses(): array {
    return ['running', 'awaiting_confirm', 'awaiting_conflict'];
}
function zeep_norm_password(string $password): string {
    $password = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{2060}]/u', '', $password);
    return trim($password);
}
function zeep_norm_import_date(string $date): string {
    $date = trim($date);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{2}|\d{4})$/', $date, $m)) {
        $year = (int)$m[3];
        if ($year < 100) {
            $year += $year < 70 ? 2000 : 1900;
        }
        return sprintf('%04d-%02d-%02d', $year, (int)$m[2], (int)$m[1]);
    }
    return '';
}
try {
    $db = getUserDB(ZEEP_USER_DIR);
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
    $kind = (string)($_POST['file_kind'] ?? 'zeep');
    if ($kind !== 'zeep' || strlen($uploadId) < 12 || $chunkIndex < 0 || $chunkTotal <= 0 || !isset($_FILES['chunk'])) {
        echo json_encode(['ok' => false, 'error' => 'chunk_params']);
        exit;
    }
    if ((int)($_FILES['chunk']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'chunk_upload', 'file_err' => (int)($_FILES['chunk']['error'] ?? 0)]);
        exit;
    }
    [$dataPath, $metaPath] = zeep_chunk_session_paths($uploadId);
    $meta = zeep_chunk_read_meta($metaPath);
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
    @chmod(dirname($dataPath), 0777);
    $in = @fopen($tmp, 'rb');
    $out = @fopen($dataPath, 'ab');
    if (!$in || !$out) {
        if (is_resource($in)) {
            fclose($in);
        }
        if (is_resource($out)) {
            fclose($out);
        }
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
    if (!zeep_chunk_write_meta($metaPath, $meta)) {
        echo json_encode(['ok' => false, 'error' => 'chunk_meta']);
        exit;
    }
    $done = ($chunkIndex + 1) >= $chunkTotal;
    echo json_encode(['ok' => true, 'done' => $done, 'upload_id' => $uploadId, 'received' => $received]);
    exit;
}
if ($action === 'active_job' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $zt = zeep_job_load($db);
    $st = $zt['state'];
    $mt = (int)($zt['updated_at'] ?? 0);
    $jid = (string)($zt['job_id'] ?? '');
    if (is_array($st) && strlen($jid) >= 8) {
        $st = zeep_refresh_state_from_file($db, $jid, $st, $mt);
        $mt = time();
    }
    $status = is_array($st) ? (string)($st['status'] ?? '') : '';
    if (is_array($st) && in_array($status, zeep_active_statuses(), true) && strlen($jid) >= 8) {
        if ($status === 'running' && $mt > 0 && (time() - $mt) > 1800) {
            $st = array_merge($st, ['status' => 'error', 'message' => 'stale_job']);
        }
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
    $zt = zeep_job_load($db);
    if (($zt['job_id'] ?? '') !== $jid) {
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }
    $st = $zt['state'];
    $mt = (int)($zt['updated_at'] ?? 0);
    if (is_array($st)) {
        $st = zeep_refresh_state_from_file($db, $jid, $st, $mt);
        $mt = time();
    }
    if (is_array($st) && ($st['status'] ?? '') === 'running' && $mt > 0 && (time() - $mt) > 1800) {
        $st = array_merge($st, ['status' => 'error', 'message' => 'stale_job']);
    }
    if (is_array($st)) $st = zeep_notify_job_once($db, $jid, $st);
    echo json_encode(['ok' => true, 'state' => $st]);
    exit;
}
if ($action === 'preview' && isset($_GET['job'])) {
    $jid = preg_replace('/[^a-f0-9]/i', '', (string)$_GET['job']);
    if (strlen($jid) < 8) {
        echo json_encode(['ok' => false, 'error' => 'invalid job']);
        exit;
    }
    $zt = zeep_job_load($db);
    if (($zt['job_id'] ?? '') !== $jid) {
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }
    $st = is_array($zt['state'] ?? null) ? $zt['state'] : [];
    $st = zeep_refresh_state_from_file($db, $jid, $st, (int)($zt['updated_at'] ?? 0));
    $statusNow = (string)($st['status'] ?? '');
    if (!in_array($statusNow, ['awaiting_confirm', 'awaiting_conflict'], true)) {
        echo json_encode(['ok' => false, 'error' => 'state']);
        exit;
    }
    $jobPath = ZEEP_JOBS_DIR . $jid;
    echo json_encode(['ok' => true, 'rows' => zeep_load_parsed_rows($jobPath)]);
    exit;
}
if ($action === 'stop_job' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $jid = preg_replace('/[^a-f0-9]/i', '', (string)($_POST['job'] ?? ''));
    if (strlen($jid) < 8) {
        echo json_encode(['ok' => false, 'error' => 'invalid job']);
        exit;
    }
    $zt = zeep_job_load($db);
    if (($zt['job_id'] ?? '') !== $jid) {
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }
    $jobPath = ZEEP_JOBS_DIR . $jid;
    if (is_dir($jobPath)) {
        @file_put_contents($jobPath . DIRECTORY_SEPARATOR . 'cancel.flag', '1');
    }
    $st = $zt['state'];
    if (!is_array($st)) {
        $st = [];
    }
    try {
        zeep_job_save($db, $jid, array_merge($st, ['status' => 'cancelled', 'message' => 'cancelled', 'job_id' => $jid]));
    } catch (Exception $e) {
    }
    try {
        zeep_job_reset($db);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true]);
    exit;
}
if ($action === 'cancel_zeep' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $jid = preg_replace('/[^a-f0-9]/i', '', (string)($_POST['job'] ?? ''));
    if (strlen($jid) < 8) {
        echo json_encode(['ok' => false, 'error' => 'invalid job']);
        exit;
    }
    $jobPath = ZEEP_JOBS_DIR . $jid;
    if (is_dir($jobPath)) {
        @file_put_contents($jobPath . DIRECTORY_SEPARATOR . 'cancel.flag', '1');
        zeep_delete_tree($jobPath);
    }
    try {
        zeep_job_reset($db);
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
    $zt = zeep_job_load($db);
    if (($zt['job_id'] ?? '') !== $jid) {
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }
    $st = is_array($zt['state'] ?? null) ? $zt['state'] : [];
    $st = zeep_refresh_state_from_file($db, $jid, $st, (int)($zt['updated_at'] ?? 0));
    if (($st['status'] ?? '') === 'done') {
        echo json_encode(['ok' => true, 'job_id' => $jid, 'state' => $st]);
        exit;
    }
    if (($st['status'] ?? '') !== 'awaiting_conflict') {
        echo json_encode(['ok' => false, 'error' => 'state']);
        exit;
    }
    $jobPath = ZEEP_JOBS_DIR . $jid;
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
    $applied = zeep_apply_parsed_job($db, $jid, $jobPath, $st, $mode);
    if (!$applied['ok']) {
        echo json_encode(['ok' => false, 'error' => $applied['error'] ?? 'apply']);
        exit;
    }
    echo json_encode(['ok' => true, 'job_id' => $jid, 'state' => $applied['state']]);
    exit;
}
if ($action === 'confirm_zeep' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $jid = preg_replace('/[^a-f0-9]/i', '', (string)($_POST['job'] ?? ''));
    if (strlen($jid) < 8) {
        echo json_encode(['ok' => false, 'error' => 'invalid job']);
        exit;
    }
    $zt = zeep_job_load($db);
    if (($zt['job_id'] ?? '') !== $jid) {
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }
    $st = is_array($zt['state'] ?? null) ? $zt['state'] : [];
    $st = zeep_refresh_state_from_file($db, $jid, $st, (int)($zt['updated_at'] ?? 0));
    $statusNow = (string)($st['status'] ?? '');
    if (!in_array($statusNow, ['awaiting_confirm', 'awaiting_conflict'], true)) {
        echo json_encode(['ok' => false, 'error' => 'state']);
        exit;
    }
    if ($statusNow === 'awaiting_conflict' && !isset($_POST['rows'])) {
        echo json_encode(['ok' => true, 'job_id' => $jid]);
        exit;
    }
    $rawRows = (string)($_POST['rows'] ?? '');
    $rowsIn = json_decode($rawRows, true);
    if (!is_array($rowsIn)) {
        echo json_encode(['ok' => false, 'error' => 'rows']);
        exit;
    }
    $daySteps = [];
    foreach ($rowsIn as $r) {
        if (!is_array($r)) {
            continue;
        }
        $d = zeep_norm_import_date((string)($r['date'] ?? ($r['dmy'] ?? '')));
        if ($d === '') {
            continue;
        }
        $s = (int)($r['steps'] ?? -1);
        if ($s < 0) {
            continue;
        }
        $daySteps[$d] = $s;
    }
    if (!$daySteps) {
        echo json_encode(['ok' => false, 'error' => 'rows']);
        exit;
    }
    $jobPath = ZEEP_JOBS_DIR . $jid;
    $snap = $jobPath . '/parsed_day_steps.json';
    if (@file_put_contents($snap, json_encode($daySteps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        echo json_encode(['ok' => false, 'error' => 'snapshot']);
        exit;
    }
    $mode = strtolower(trim((string)($_POST['conflict_mode'] ?? 'ask')));
    if (!in_array($mode, ['ask', 'keep', 'overwrite', 'new_only'], true)) {
        $mode = 'ask';
    }
    $cfgPath = $jobPath . '/job_config.json';
    $cfg = is_file($cfgPath) ? json_decode((string)@file_get_contents($cfgPath), true) : [];
    if (!is_array($cfg)) {
        $cfg = [];
    }
    $cfg['conflict_mode'] = $mode;
    if (@file_put_contents($cfgPath, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        echo json_encode(['ok' => false, 'error' => 'config_write']);
        exit;
    }
    $applied = zeep_apply_parsed_job($db, $jid, $jobPath, array_merge($st, ['total_days' => count($daySteps)]), $mode);
    if (!$applied['ok']) {
        echo json_encode(['ok' => false, 'error' => $applied['error'] ?? 'apply']);
        exit;
    }
    echo json_encode(['ok' => true, 'job_id' => $jid, 'state' => $applied['state']]);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $action !== 'upload_zeep') {
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}
if (!isset($_FILES['zeep']) && !isset($_POST['zeep_token'])) {
    echo json_encode(['ok' => false, 'error' => 'upload']);
    exit;
}
$password = zeep_norm_password((string)($_POST['archive_password'] ?? ''));
if ($password === '') {
    echo json_encode(['ok' => false, 'error' => 'password']);
    exit;
}
$token = preg_replace('/[^a-z0-9]/i', '', (string)($_POST['zeep_token'] ?? ''));
$name = isset($_FILES['zeep']) ? (string)($_FILES['zeep']['name'] ?? '') : '';
if ($name === '' && $token !== '') {
    [, $metaPath] = zeep_chunk_session_paths($token);
    $meta = zeep_chunk_read_meta($metaPath);
    $name = (string)($meta['file_name'] ?? '');
}
if (!preg_match('/\.zip$/i', $name)) {
    echo json_encode(['ok' => false, 'error' => 'not_zip']);
    exit;
}
try {
    zeep_job_save($db, '', ['status' => 'idle', 'progress' => 0, 'message' => '', 'imported' => 0, 'total_days' => 0, 'job_id' => '']);
} catch (Exception $e) {
}
$jobId = bin2hex(random_bytes(8));
$jobPath = ZEEP_JOBS_DIR . $jobId;
if (!@mkdir($jobPath, 0777, true) && !is_dir($jobPath)) {
    echo json_encode(['ok' => false, 'error' => 'mkdir']);
    exit;
}
@chmod($jobPath, 0777);
$zipFile = $jobPath . '/zeep.zip';
$moved = false;
$expectedSize = 0;
if ($token !== '') {
    [, $metaPath] = zeep_chunk_session_paths($token);
    $meta = zeep_chunk_read_meta($metaPath);
    $expectedSize = (int)($meta['file_size'] ?? 0);
    $moved = zeep_chunk_move_to_path($token, $zipFile);
} elseif (isset($_FILES['zeep']) && (int)($_FILES['zeep']['error'] ?? 1) === UPLOAD_ERR_OK) {
    $moved = move_uploaded_file($_FILES['zeep']['tmp_name'], $zipFile);
    $expectedSize = (int)($_FILES['zeep']['size'] ?? 0);
}
if (!$moved) {
    echo json_encode(['ok' => false, 'error' => 'move']);
    exit;
}
$actualSize = (int)@filesize($zipFile);
if ($actualSize < 1 || ($expectedSize > 0 && $actualSize !== $expectedSize)) {
    @unlink($zipFile);
    echo json_encode(['ok' => false, 'error' => 'upload']);
    exit;
}
$cfg = zeep_job_cfg([
    'job_id' => $jobId,
    'conflict_mode' => 'preview',
    'archive_password' => $password,
]);
if (@file_put_contents($jobPath . '/job_config.json', json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
    @unlink($zipFile);
    echo json_encode(['ok' => false, 'error' => 'config']);
    exit;
}
$state0 = [
    'status' => 'running', 'progress' => 0.0, 'message' => 'queued', 'imported' => 0, 'total_days' => 0, 'job_id' => $jobId,
];
try {
    zeep_job_save($db, $jobId, $state0);
} catch (Exception $e) {
    @unlink($zipFile);
    echo json_encode(['ok' => false, 'error' => 'db_save']);
    exit;
}
$okW = zeep_spawn_worker($jobPath);
if (!$okW) {
    try {
        zeep_job_save($db, $jobId, array_merge($state0, ['status' => 'error', 'message' => 'worker not started']));
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => false, 'error' => 'worker', 'job_id' => $jobId]);
    exit;
}
echo json_encode(['ok' => true, 'job_id' => $jobId]);
