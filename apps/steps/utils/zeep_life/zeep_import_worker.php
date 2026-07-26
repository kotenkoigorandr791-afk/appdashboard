<?php
declare(strict_types=1);
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
require_once __DIR__ . '/../import_worker_common.php';
function zeep_parse_activity_csv(string $path, array &$daySteps): void {
    foreach (['UTF-8', 'UTF-8', 'CP1251', 'ISO-8859-1'] as $enc) {
        $n0 = count($daySteps);
        $fh = @fopen($path, 'rb');
        if (!$fh) continue;
        $hdr = fgetcsv($fh);
        if (!is_array($hdr) || count($hdr) < 2) {
            fclose($fh);
            continue;
        }
        $hlow = array_map(static fn($x) => steps_worker_norm_key((string)$x), $hdr);
        $idxD = 0;
        $idxS = 1;
        foreach ($hlow as $i => $h) if ($h === 'date' || str_starts_with($h, 'date')) $idxD = $i;
        foreach ($hlow as $i => $h) if ($h === 'steps' || (str_starts_with($h, 'step') && !str_contains($h, 'distance'))) $idxS = $i;
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) <= max($idxD, $idxS)) continue;
            $ds = trim((string)$row[$idxD]);
            $raw = preg_replace('/\D/', '', (string)$row[$idxS]);
            if ($raw === '') continue;
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ds, $m)) continue;
            $step = (int)$raw;
            if ($step >= 0) $daySteps[$m[1] . '-' . $m[2] . '-' . $m[3]] = $step;
        }
        fclose($fh);
        if (count($daySteps) > $n0) return;
        unset($enc);
    }
}
function zeep_find_activity_csvs(string $root): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $fn = $file->getFilename();
        $low = strtolower($fn);
        $rel = strtoupper(str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)));
        if (str_starts_with($low, 'activity') && str_ends_with($low, '.csv') && !str_contains($low, 'minute') && !str_contains($low, 'stage') && str_contains($rel, 'ACTIVITY')) $out[] = $file->getPathname();
    }
    return $out;
}
function zeep_run_job(string $jobDir): void {
    $jdir = realpath($jobDir) ?: $jobDir;
    $cfg = steps_worker_json_read($jdir . '/job_config.json');
    $jid = basename($jdir);
    $table = 'zeep_job_state';
    $write = static fn(array $s) => steps_worker_write_state($jdir, $cfg, $jid, $table, $s);
    $zip = $jdir . '/zeep.zip';
    if (!steps_worker_connect_db($cfg)) {
        $write(['status' => 'error', 'message' => 'no user db', 'progress' => 0, 'imported' => 0, 'job_id' => $jid]);
        steps_import_log('error', 'zeep worker: no user db job=' . $jid . ' email=' . steps_worker_cfg_email($cfg), __FILE__, __LINE__);
        return;
    }
    if (!is_file($zip)) {
        $write(['status' => 'error', 'message' => 'no zip', 'progress' => 0, 'imported' => 0, 'job_id' => $jid]);
        return;
    }
    if (steps_worker_cancelled($jdir)) {
        $write(['status' => 'cancelled', 'message' => 'cancelled', 'progress' => 0, 'imported' => 0, 'total_days' => 0, 'job_id' => $jid]);
        steps_worker_delete_tree($jdir);
        return;
    }
    $write(['status' => 'running', 'progress' => 0.5, 'message' => 'queued', 'imported' => 0, 'total_days' => 0, 'job_id' => $jid]);
    $mode = strtolower(trim((string)($cfg['conflict_mode'] ?? 'ask')));
    if (!in_array($mode, ['ask', 'keep', 'overwrite', 'new_only', 'preview'], true)) $mode = 'ask';
    $snap = $jdir . '/parsed_day_steps.json';
    $daySteps = steps_worker_load_snapshot($snap, true);
    if (!$daySteps) {
        $ext = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'steps_zeep_' . $jid;
        if (is_dir($ext)) steps_worker_delete_tree($ext);
        @mkdir($ext, 0777, true);
        @chmod($jdir, 0777);
        @chmod($ext, 0777);
        $write(['status' => 'running', 'progress' => 2.0, 'message' => 'unzip', 'imported' => 0, 'total_days' => 0, 'job_id' => $jid]);
        $err = steps_worker_extract_zip($zip, $ext, trim((string)($cfg['archive_password'] ?? '')), $jdir . '/worker.log');
        if ($err !== '') {
            $write(['status' => 'error', 'message' => $err, 'progress' => 0, 'imported' => 0, 'job_id' => $jid]);
            steps_import_log('error', 'zeep worker: extract failed job=' . $jid . ' err=' . $err, __FILE__, __LINE__);
            return;
        }
        $write(['status' => 'running', 'progress' => 8.0, 'message' => 'parse', 'imported' => 0, 'total_days' => 0, 'job_id' => $jid]);
        $csvs = zeep_find_activity_csvs($ext);
        $nfiles = max(count($csvs), 1);
        foreach ($csvs as $i => $path) {
            if (steps_worker_cancelled($jdir)) {
                $write(['status' => 'cancelled', 'message' => 'cancelled', 'progress' => 0, 'imported' => 0, 'total_days' => count($daySteps), 'job_id' => $jid]);
                steps_worker_delete_tree($jdir);
                return;
            }
            zeep_parse_activity_csv($path, $daySteps);
            $write(['status' => 'running', 'progress' => min(88.0, 8.0 + 80.0 * ($i + 1) / $nfiles), 'message' => 'file ' . ($i + 1) . '/' . $nfiles, 'imported' => 0, 'total_days' => count($daySteps), 'job_id' => $jid]);
        }
        if ($daySteps) steps_worker_save_snapshot($snap, $daySteps);
        if (is_dir($ext)) steps_worker_delete_tree($ext);
    }
    if (!$daySteps) {
        $write(['status' => 'error', 'message' => 'no step data in archive', 'progress' => 0, 'imported' => 0, 'total_days' => 0, 'job_id' => $jid]);
        steps_import_log('error', 'zeep worker: no step data job=' . $jid, __FILE__, __LINE__);
        return;
    }
    if ($mode === 'preview') {
        $write(['status' => 'awaiting_confirm', 'message' => 'preview', 'progress' => 90.0, 'imported' => 0, 'total_days' => count($daySteps), 'job_id' => $jid]);
        return;
    }
    if ($mode === 'ask') {
        $conf = steps_worker_conflicts($cfg, $daySteps);
        if ($conf > 0) {
            $write(['status' => 'awaiting_conflict', 'message' => 'conflict_prompt', 'progress' => 92.0, 'imported' => 0, 'total_days' => count($daySteps), 'conflict_days' => $conf, 'job_id' => $jid]);
            return;
        }
        $mode = 'overwrite';
    }
    $nimp = steps_worker_import($cfg, $daySteps, $mode, true);
    $write(['status' => 'done', 'message' => 'ok', 'progress' => 100.0, 'imported' => $nimp, 'total_days' => count($daySteps), 'job_id' => $jid]);
    @unlink($zip);
}
if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') === '') {
        fwrite(STDERR, "usage: zeep_import_worker.php <job_dir>\n");
        exit(2);
    }
    try {
        zeep_run_job($argv[1]);
    } catch (Throwable $e) {
        $jdir = realpath($argv[1]) ?: $argv[1];
        steps_worker_write_state($jdir, steps_worker_json_read($jdir . '/job_config.json'), basename($jdir), 'zeep_job_state', ['status' => 'error', 'message' => substr($e->getMessage(), 0, 500), 'progress' => 0, 'imported' => 0, 'job_id' => basename($jdir)]);
        throw $e;
    }
}
