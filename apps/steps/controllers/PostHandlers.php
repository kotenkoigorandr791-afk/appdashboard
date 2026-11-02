<?php
require_once __DIR__ . '/../core/file_index.php';
function steps_safe_backup_name($name): ?string {
    $n = basename((string)$name);
    return preg_match('/^(Steps_backup|Before_restore|Before_external_import)_[0-9_-]+\.csv$/', $n) ? $n : null;
}
// === POST handlers ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    ob_clean();

    // Save settings
    if (isset($_POST['settings'])) {
        $goalSteps = filter_var(str_replace(',', '.', $_POST['goal_steps']), FILTER_VALIDATE_FLOAT);
        if ($goalSteps === false || $goalSteps < 0)
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_goal_steps'] ?? 'Invalid goal steps']));

        try {
            $userDb->exec('BEGIN');
            saveUserSetting($userDb, 'goal_steps',    (string)(int)($goalSteps * 1000));
            saveUserSetting($userDb, 'height',        (string)(filter_var($_POST['height'],        FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 170));
            saveUserSetting($userDb, 'weight',        (string)(filter_var($_POST['weight'],        FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 70));
            saveUserSetting($userDb, 'stride_length', (string)(filter_var($_POST['stride_length'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 70));
            saveUserSetting($userDb, 'birth_date',    $_POST['birth_date'] ?? '1994-01-01');
            $userDb->exec('COMMIT');
        } catch (Exception $e) {
            $userDb->exec('ROLLBACK');
            exit(json_encode(['status' => 'error', 'message' => $translations['failed_save_settings'] ?? 'Failed to save settings']));
        }
        exit(json_encode(['status' => 'success']));
    }

    // Add steps
    if (isset($_POST['add_steps'])) {
        $date  = $_POST['date'] ?? '';
        $stepsRaw = preg_replace('/\D+/', '', (string)($_POST['steps'] ?? ''));
        $steps = $stepsRaw === '' ? false : filter_var($stepsRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($date && $steps !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                upsertStep($userDb, $date, $steps);
                exit(json_encode(['status' => 'success']));
            } catch (Exception $e) {
                exit(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
            }
        }
        exit(json_encode(['status' => 'error', 'message' => $translations['invalid_date_steps'] ?? 'Invalid date or steps']));
    }

    if (isset($_POST['import_video_batch'])) {
        $raw = $_POST['rows'] ?? '';
        $rows = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($rows)) {
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_request'] ?? 'Invalid request']));
        }
        $imported = [];
        $ok = false;
        $lastErr = '';
        for ($attempt = 0; $attempt < 8; $attempt++) {
            try {
                $imported = [];
                $userDb->exec('BEGIN');
                foreach ($rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $d = trim((string)($r['date'] ?? ''));
                    $sRaw = preg_replace('/\D+/', '', (string)($r['steps'] ?? ''));
                    $s = $sRaw === '' ? false : filter_var($sRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                    if ($s === false || !preg_match('/^\d{2}-\d{2}-\d{2}$/', $d)) {
                        continue;
                    }
                    $iso = csvDateToISO($d);
                    if (!$iso) {
                        continue;
                    }
                    upsertStep($userDb, $iso, (int)$s);
                    $imported[$iso] = (int)$s;
                }
                $userDb->exec('COMMIT');
                $ok = true;
                break;
            } catch (Exception $e) {
                $userDb->exec('ROLLBACK');
                $lastErr = $e->getMessage();
                if (stripos($lastErr, 'locked') === false && stripos($lastErr, 'busy') === false) {
                    break;
                }
                usleep(50000 * ($attempt + 1));
            }
        }
        if (!$ok) {
            exit(json_encode(['status' => 'error', 'message' => $lastErr !== '' ? $lastErr : ($translations['failed_save'] ?? 'Failed to save')]));
        }
        if (count($imported) > 0 && is_file($userDir . '/pending_video_confirm.json')) {
            @unlink($userDir . '/pending_video_confirm.json');
            try {
                video_job_reset($userDb);
            } catch (Exception $e) {
            }
        }
        exit(json_encode(['status' => 'success', 'new_steps' => $imported]));
    }

    if (isset($_POST['cleanup_video_temp'])) {
        $jobId = preg_replace('/[^a-f0-9]/i', '', (string)($_POST['job_id'] ?? ''));
        $bn    = basename((string)($_POST['output_basename'] ?? ''));
        $jBase = realpath($userDir . '/video_jobs');
        if ($jBase && strlen($jobId) >= 8) {
            $jPath = $userDir . '/video_jobs/' . $jobId;
            if (is_dir($jPath)) {
                $rj = realpath($jPath);
                if ($rj && strncmp($rj, $jBase, strlen($jBase)) === 0) {
                    steps_delete_tree($jPath);
                }
            }
        }
        $rBase = realpath($userDir . '/results');
        if ($rBase && $bn !== '' && preg_match('/^steps_data_[A-Za-z0-9_.-]+\.txt$/', $bn)) {
            $fp = $userDir . '/results/' . $bn;
            if (is_file($fp)) {
                $rf = realpath($fp);
                if ($rf && strncmp($rf, $rBase, strlen($rBase)) === 0) {
                    @unlink($rf);
                }
            }
        }
        $px = $userDir . '/pending_video_confirm.json';
        if (is_file($px)) {
            @unlink($px);
        }
        try {
            video_job_reset($userDb);
        } catch (Exception $e) {
        }
        exit(json_encode(['status' => 'success']));
    }

    // Save table row (date in dd-mm-yy)
    if (isset($_POST['save_table_data'])) {
        $date  = $_POST['date']  ?? '';
        $steps = preg_replace('/\D+/', '', (string)($_POST['steps'] ?? ''));
        if (empty($date) || $steps === '' || !ctype_digit($steps))
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_data'] ?? 'Invalid data']));
        if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $date))
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_date_format'] ?? 'Invalid date format']));
        $isoDate = csvDateToISO($date);
        if (!$isoDate)
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_date_format'] ?? 'Invalid date format']));
        try {
            upsertStep($userDb, $isoDate, (int)$steps);
            exit(json_encode(['status' => 'success', 'message' => $translations['data_saved'] ?? 'Data saved']));
        } catch (Exception $e) {
            exit(json_encode(['status' => 'error', 'message' => $translations['failed_save'] ?? 'Failed to save']));
        }
    }

    // Batch save table rows (bulk delete/zero)
    if (isset($_POST['save_table_data_batch'])) {
        $raw = $_POST['items'] ?? '[]';
        $items = json_decode($raw, true);
        if (!is_array($items) || empty($items))
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_data'] ?? 'Invalid data']));
        $batch = [];
        foreach ($items as $item) {
            $date  = $item['date'] ?? '';
            $steps = preg_replace('/\D+/', '', (string)($item['steps'] ?? ''));
            if (empty($date) || $steps === '' || !ctype_digit($steps))
                continue;
            if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $date))
                continue;
            $isoDate = csvDateToISO($date);
            if (!$isoDate)
                continue;
            $batch[] = ['date' => $isoDate, 'steps' => (int)$steps];
        }
        if (empty($batch))
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_data'] ?? 'Invalid data']));
        try {
            upsertStepsBatch($userDb, $batch);
            exit(json_encode(['status' => 'success', 'message' => $translations['data_saved'] ?? 'Data saved', 'count' => count($batch)]));
        } catch (Exception $e) {
            exit(json_encode(['status' => 'error', 'message' => $translations['failed_save'] ?? 'Failed to save']));
        }
    }

    // Table: get page with filter (via SQL)
    if (isset($_POST['get_table_page'])) {
        $page       = max(1, (int)($_POST['page']      ?? 1));
        $filterOp   = $_POST['filter_op']              ?? '';
        $filterVal  = isset($_POST['filter_val'])  && $_POST['filter_val']  !== '' ? (int)$_POST['filter_val']  : null;
        $filterValTo= isset($_POST['filter_val_to']) && $_POST['filter_val_to'] !== '' ? (int)$_POST['filter_val_to'] : null;
        $dateFrom   = isset($_POST['date_from']) && $_POST['date_from'] !== '' ? (string)$_POST['date_from'] : null;
        $dateTo     = isset($_POST['date_to']) && $_POST['date_to'] !== '' ? (string)$_POST['date_to'] : null;

        $result = loadStepsPage($userDb, $page, TABLE_PAGE_SIZE, $filterOp, $filterVal, $filterValTo, $dateFrom, $dateTo);
        exit(json_encode(['status' => 'success'] + $result));
    }
    if (isset($_POST['get_steps_updates'])) {
        $version = steps_version($userDb);
        if ((string)($_POST['version'] ?? '') === $version) {
            exit(json_encode(['status' => 'success', 'changed' => false, 'version' => $version]));
        }
        // This endpoint returns the whole history (hundreds of KB) and is polled
        // by every open tab, so the serialized payload is cached per data version.
        // The version covers row count, max date and total steps, which means the
        // cache is keyed by the actual data state and can never serve stale rows.
        $cacheDir = rtrim($userDir, '/') . '/volume';
        checkDirectory($cacheDir);
        $cacheFile = $cacheDir . '/steps_updates_cache.json';
        $cached = null;
        if (is_file($cacheFile)) {
            $cachedRaw = @file_get_contents($cacheFile);
            if ($cachedRaw !== false) $cached = json_decode($cachedRaw, true);
        }
        if (is_array($cached) && (string)($cached['version'] ?? '') === $version && isset($cached['body'])) {
            echo (string)$cached['body'];
            exit;
        }
        $today = (new DateTime())->format('Y-m-d');
        [$earliestInDB, $latestInDB] = getStepsDateBounds($userDb);
        $from = $earliestInDB ?? $today;
        $to = ($latestInDB && strcmp($latestInDB, $today) > 0) ? $latestInDB : $today;
        $rows = steps_fill_range(loadStepsRange($userDb, $from, $to), $from, $to);
        $body = json_encode(['status' => 'success', 'changed' => true, 'version' => $version, 'steps' => $rows]);
        $tmp = $cacheFile . '.tmp' . getmypid();
        if (@file_put_contents($tmp, json_encode(['version' => $version, 'body' => $body])) !== false) {
            @rename($tmp, $cacheFile);
        } else {
            @unlink($tmp);
        }
        echo $body;
        exit;
    }

    // Create backup
    if (isset($_POST['create_backup'])) {
        checkDirectory($userDir);
        $backupDir = "$userDir/backups";
        checkDirectory($backupDir);
        $csvContent = exportStepsToCSV($userDb);
        if (empty($csvContent))
            exit(json_encode(['status' => 'error', 'message' => $translations['steps_file_not_found'] ?? 'No steps data']));
        $ts   = date('Y-m-d_H-i-s');
        $name = "Steps_backup_$ts.csv";
        if (file_put_contents("$backupDir/$name", $csvContent) !== false) {
            steps_record_backup($userDir, $name);
            $files = steps_list_backups($userDir);
            if (count($files) > 10) {
                foreach (array_slice($files, 10) as $stale) {
                    @unlink($stale['path']);
                    steps_forget_backup($userDir, $stale['name']);
                }
            }
            exit(json_encode(['status' => 'success', 'message' => $translations['backup_created'] ?? 'Backup created', 'backup_file' => $name]));
        }
        exit(json_encode(['status' => 'error', 'message' => $translations['failed_create_backup'] ?? 'Failed to create backup']));
    }

    // Backups list
    if (isset($_POST['get_backups_list'])) {
        $backups = [];
        foreach (steps_list_backups($userDir) as $f) {
            $backups[] = ['name' => $f['name'], 'size' => $f['size'], 'date' => date('Y-m-d H:i:s', $f['mtime'])];
        }
        exit(json_encode(['status' => 'success', 'backups' => $backups]));
    }

    // Restore from backup
    if (isset($_POST['restore_backup'])) {
        $bname     = steps_safe_backup_name($_POST['backup_file'] ?? '');
        $backupDir = "$userDir/backups";
        $backupPath = $bname === null ? null : steps_backup_path($userDir, $bname);
        if ($backupPath === null)
            exit(json_encode(['status' => 'error', 'message' => $translations['backup_file_not_found'] ?? 'Backup not found']));
        // Auto-backup before restore
        $autoCSV = exportStepsToCSV($userDb);
        if ($autoCSV) {
            $autoName = 'Before_restore_' . date('Y-m-d_H-i-s') . '.csv';
            if (file_put_contents("$backupDir/$autoName", $autoCSV) !== false) steps_record_backup($userDir, $autoName);
        }
        $content = file_get_contents($backupPath);
        if ($content === false)
            exit(json_encode(['status' => 'error', 'message' => $translations['failed_restore_backup'] ?? 'Failed to restore']));
        try {
            steps_replace_all_from_csv($userDb, $content);
            exit(json_encode(['status' => 'success', 'message' => $translations['backup_restored'] ?? 'Restored']));
        } catch (Exception $e) {
            exit(json_encode(['status' => 'error', 'message' => $translations['failed_restore_backup'] ?? 'Failed to restore']));
        }
    }

    // Delete backup
    if (isset($_POST['delete_backup'])) {
        $bname      = steps_safe_backup_name($_POST['backup_file'] ?? '');
        $backupPath = $bname === null ? null : steps_backup_path($userDir, $bname);
        if ($backupPath === null)
            exit(json_encode(['status' => 'error', 'message' => $translations['backup_file_not_found'] ?? 'Backup not found']));
        $ok = unlink($backupPath);
        if ($ok) steps_forget_backup($userDir, (string)$bname);
        exit(json_encode($ok
            ? ['status' => 'success', 'message' => $translations['backup_deleted'] ?? 'Deleted']
            : ['status' => 'error',   'message' => $translations['failed_delete_backup'] ?? 'Failed to delete']));
    }

    // Download current data as CSV
    if (isset($_POST['download_backup'])) {
        $csv = exportStepsToCSV($userDb);
        if ($csv) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="Steps_backup_' . date('Y-m-d') . '.csv"');
            ob_clean();
            echo $csv;
            exit;
        }
        exit(json_encode(['status' => 'error', 'message' => $translations['file_not_found'] ?? 'No data']));
    }

    if (isset($_POST['import_external_backup'])) {
        $mode = ($_POST['import_mode'] ?? 'merge') === 'replace' ? 'replace' : 'merge';
        if (empty($_FILES['backup_file']['tmp_name']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK)
            exit(json_encode(['status' => 'error', 'message' => $translations['no_file_selected'] ?? 'No file selected']));
        $content = file_get_contents($_FILES['backup_file']['tmp_name']);
        if ($content === false || trim($content) === '')
            exit(json_encode(['status' => 'error', 'message' => $translations['external_backup_fetch_failed'] ?? 'Failed to read file']));
        $autoCSV = exportStepsToCSV($userDb);
        if ($autoCSV) {
            $backupDir = "$userDir/backups";
            checkDirectory($backupDir);
            file_put_contents("$backupDir/Before_external_import_" . date('Y-m-d_H-i-s') . ".csv", $autoCSV);
        }
        $imported = $mode === 'replace' ? steps_replace_all_from_csv($userDb, $content) : importStepsFromCSVContent($userDb, $content, true);
        $msg = sprintf($translations['external_backup_imported'] ?? 'Imported %d records', count($imported));
        exit(json_encode(['status' => 'success', 'message' => $msg, 'count' => count($imported)]));
    }

    exit(json_encode(['status' => 'error', 'message' => $translations['invalid_request'] ?? 'Invalid request']));
}
