<?php
declare(strict_types=1);
require_once __DIR__ . '/../import_worker_common.php';
function fit_nanos_to_day($n): string {
    if (!is_numeric((string)$n)) return '';
    return gmdate('Y-m-d', (int)((float)$n / 1000000000));
}
function fit_parse_obj($obj, array &$daySteps): void {
    if (!is_array($obj)) return;
    $dtp = (string)($obj['dataTypeName'] ?? $obj['Data Source'] ?? $obj['dataType'] ?? '');
    $sdt = strtolower($dtp);
    if (!str_contains($sdt, 'step') && !str_contains($sdt, 'com.google') && !str_contains(strtolower(substr(json_encode($obj), 0, 2000)), 'intval') && !isset($obj['Data Points'], $obj['dataPoints'], $obj['point'])) return;
    $points = null;
    foreach (['Data Points', 'dataPoints', 'point', 'points'] as $k) if (isset($obj[$k]) && is_array($obj[$k])) {
        $points = $obj[$k];
        break;
    }
    if ($points === null) {
        foreach ($obj as $k => $v) if (is_array($v) && isset($v['Data Points']) && is_array($v['Data Points'])) fit_parse_obj($v + ['Data Source' => $dtp], $daySteps);
        return;
    }
    $isDelta = str_contains($sdt, 'delta') && !str_contains($sdt, 'cumulative');
    $isCum = str_contains($sdt, 'cumulative') || (!$isDelta && str_contains($sdt, 'step_count') && str_contains($sdt, 'cumulative'));
    foreach ($points as $p) {
        if (!is_array($p)) continue;
        $day = '';
        foreach (['startTimeNanos', 'startTime', 'rawTimestampNanos'] as $tk) if (isset($p[$tk])) {
            $day = fit_nanos_to_day($p[$tk]);
            break;
        }
        if ($day === '' && isset($p['date']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$p['date'], $m)) $day = $m[1] . '-' . $m[2] . '-' . $m[3];
        $val = 0;
        foreach (['fitValue', 'value', 'values'] as $fk) {
            $fv = $p[$fk] ?? null;
            if (is_array($fv) && array_is_list($fv)) foreach ($fv as $it) {
                $w = is_array($it) ? ($it['value'] ?? null) : null;
                if (is_array($w) && isset($w['intVal'])) $val = max($val, (int)$w['intVal']);
            } elseif (is_array($fv) && isset($fv['intVal'])) $val = (int)$fv['intVal'];
        }
        if (isset($p['intVal'])) $val = (int)$p['intVal'];
        if ($day === '' || $val < 0) continue;
        if ($isCum && !$isDelta) $daySteps[$day] = max($daySteps[$day] ?? 0, $val);
        else $daySteps[$day] = ($daySteps[$day] ?? 0) + $val;
    }
}
function fit_try_parse_json(string $path, array &$daySteps, array &$seen): bool {
    $key = @filesize($path) . substr($path, -80);
    if (isset($seen[$key])) return false;
    $seen[$key] = true;
    $data = json_decode((string)@file_get_contents($path), true);
    if (!is_array($data)) return false;
    $n0 = count($daySteps);
    if (array_is_list($data)) foreach ($data as $it) fit_parse_obj($it, $daySteps);
    else fit_parse_obj($data, $daySteps);
    return count($daySteps) > $n0;
}
function fit_parse_csv(string $path, array &$daySteps): void {
    $fh = @fopen($path, 'rb');
    if (!$fh) return;
    $hdr = fgetcsv($fh);
    if (!is_array($hdr) || count($hdr) < 2) {
        fclose($fh);
        return;
    }
    $hlow = array_map(static fn($x) => steps_worker_norm_key((string)$x), $hdr);
    $idxD = 0;
    $idxS = 1;
    foreach ($hlow as $i => $h) if (in_array($h, ['date', 'day', 'дата', 'day index'], true) || (str_contains($h, 'date') && !str_contains($h, 'end'))) $idxD = $i;
    foreach ($hlow as $i => $h) if ((str_contains($h, 'step') || str_starts_with($h, 'шаг') || str_starts_with($h, 'крок') || str_starts_with($h, 'krok')) && !preg_match('/distance|kcal|кал|серд|heart/u', $h)) $idxS = $i;
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) <= max($idxD, $idxS)) continue;
        $raw = preg_replace('/\D/', '', (string)$row[$idxS]);
        if ($raw === '') continue;
        $ds = trim((string)$row[$idxD]);
        $day = '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ds, $m)) $day = $m[1] . '-' . $m[2] . '-' . $m[3];
        elseif (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})$/', $ds, $m)) {
            $y = (int)$m[3];
            if ($y < 100) $y += $y < 70 ? 2000 : 1900;
            $day = sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[1]);
        }
        if ($day !== '') $daySteps[$day] = ($daySteps[$day] ?? 0) + (int)$raw;
    }
    fclose($fh);
}
function fit_parse_daily_metrics(string $root): array {
    $out = [];
    $base = $root . '/Takeout/Fit/Daily activity metrics';
    if (!is_dir($base)) return $out;
    foreach (scandir($base) ?: [] as $fn) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})\.csv$/i', $fn, $m)) continue;
        $day = $m[1] . '-' . $m[2] . '-' . $m[3];
        $fh = @fopen($base . '/' . $fn, 'rb');
        if (!$fh) continue;
        $hdr = fgetcsv($fh);
        $idx = -1;
        if (is_array($hdr)) foreach ($hdr as $i => $h) {
            $hn = steps_worker_norm_key((string)$h);
            if (in_array($hn, ['step count', 'steps', 'шаги', 'кроки'], true) || (str_contains($hn, 'step') && !str_contains($hn, 'distance'))) {
                $idx = $i;
                break;
            }
        }
        $total = 0;
        while ($idx >= 0 && ($row = fgetcsv($fh)) !== false) if (isset($row[$idx]) && preg_match('/^-?\d+(\.\d+)?$/', str_replace(' ', '', (string)$row[$idx]))) {
            $v = (int)(float)str_replace(' ', '', (string)$row[$idx]);
            if ($v > 0) $total += $v;
        }
        fclose($fh);
        if ($total > 0) $out[$day] = $total;
    }
    return $out;
}
function fit_files(string $root, string $ext): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) if ($file->isFile() && str_ends_with(strtolower($file->getFilename()), $ext)) $out[] = $file->getPathname();
    return $out;
}
function fit_run_job(string $jobDir): void {
    $jdir = realpath($jobDir) ?: $jobDir;
    $cfg = steps_worker_json_read($jdir . '/job_config.json');
    $jid = basename($jdir);
    $write = static fn(array $s) => steps_worker_write_state($jdir, $cfg, $jid, 'fit_takeout_job_state', $s);
    $zip = $jdir . '/takeout.zip';
    $dbPath = trim((string)($cfg['user_sqlite_path'] ?? ''));
    if ($dbPath === '' || !is_file($dbPath)) {
        $write(['status' => 'error', 'message' => 'no user db', 'progress' => 0, 'imported' => 0, 'job_id' => $jid]);
        return;
    }
    if (!is_file($zip)) {
        $write(['status' => 'error', 'message' => 'no zip', 'progress' => 0, 'imported' => 0, 'job_id' => $jid]);
        return;
    }
    $mode = strtolower(trim((string)($cfg['conflict_mode'] ?? 'ask')));
    if (!in_array($mode, ['ask', 'keep', 'overwrite', 'new_only'], true)) $mode = 'ask';
    $snap = $jdir . '/parsed_day_steps.json';
    $daySteps = steps_worker_load_snapshot($snap);
    if (!$daySteps) {
        $ext = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'steps_takeout_' . $jid;
        if (is_dir($ext)) steps_worker_delete_tree($ext);
        @mkdir($ext, 0777, true);
        @chmod($jdir, 0777);
        @chmod($ext, 0777);
        $write(['status' => 'running', 'progress' => 2.0, 'message' => 'unzip', 'imported' => 0, 'total_days' => 0, 'job_id' => $jid]);
        $err = steps_worker_extract_zip($zip, $ext, '', $jdir . '/worker.log');
        if ($err !== '') {
            $write(['status' => 'error', 'message' => $err, 'progress' => 0, 'imported' => 0, 'job_id' => $jid]);
            return;
        }
        $write(['status' => 'running', 'progress' => 8.0, 'message' => 'parse', 'imported' => 0, 'total_days' => 0, 'job_id' => $jid]);
        $daySteps = fit_parse_daily_metrics($ext);
        if (!$daySteps) {
            $jsons = array_values(array_filter(fit_files($ext, '.json'), static fn($p) => str_contains(strtolower($p), 'fit') || str_contains(strtolower(basename($p)), 'd.') || str_contains(strtolower($p), 'step') || str_contains(strtolower($p), 'activity')));
            if (!$jsons) $jsons = fit_files($ext, '.json');
            $nfiles = max(count($jsons), 1);
            $seen = [];
            foreach ($jsons as $i => $path) {
                if (steps_worker_cancelled($jdir)) {
                    $write(['status' => 'cancelled', 'message' => 'cancelled', 'progress' => 0, 'imported' => 0, 'total_days' => count($daySteps), 'job_id' => $jid]);
                    steps_worker_delete_tree($jdir);
                    return;
                }
                fit_try_parse_json($path, $daySteps, $seen);
                $write(['status' => 'running', 'progress' => min(88.0, 8.0 + 80.0 * ($i + 1) / $nfiles), 'message' => 'file ' . ($i + 1) . '/' . $nfiles, 'imported' => 0, 'total_days' => count($daySteps), 'job_id' => $jid]);
            }
            foreach (fit_files($ext, '.csv') as $csv) {
                $low = strtolower(basename($csv));
                if (preg_match('/step|day|d\.|daily|дневн|krok/u', $low)) fit_parse_csv($csv, $daySteps);
            }
            if (!$daySteps) foreach (fit_files($ext, '.csv') as $csv) fit_parse_csv($csv, $daySteps);
        }
        if ($daySteps) steps_worker_save_snapshot($snap, $daySteps);
        if (is_dir($ext)) steps_worker_delete_tree($ext);
    }
    if (!$daySteps) {
        $write(['status' => 'error', 'message' => 'no step data in archive', 'progress' => 0, 'imported' => 0, 'total_days' => 0, 'job_id' => $jid]);
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
    $nimp = steps_worker_import($cfg, $daySteps, $mode);
    if ($nimp < 1) $nimp = count($daySteps);
    $write(['status' => 'done', 'message' => 'ok', 'progress' => 100.0, 'imported' => $nimp, 'total_days' => count($daySteps), 'job_id' => $jid]);
    @unlink($zip);
}
if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') === '') {
        fwrite(STDERR, "usage: takeout_import_worker.php <job_dir>\n");
        exit(2);
    }
    try {
        fit_run_job($argv[1]);
    } catch (Throwable $e) {
        $jdir = realpath($argv[1]) ?: $argv[1];
        steps_worker_write_state($jdir, steps_worker_json_read($jdir . '/job_config.json'), basename($jdir), 'fit_takeout_job_state', ['status' => 'error', 'message' => substr($e->getMessage(), 0, 500), 'progress' => 0, 'imported' => 0, 'job_id' => basename($jdir)]);
        throw $e;
    }
}
