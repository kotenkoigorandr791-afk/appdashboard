<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['video_bootstrap']) && (string)$_GET['video_bootstrap'] === '1' && (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')) {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean();
    $jobStaleSec = 86400;
    $now = time();
    $vj = video_job_load($userDb);
    $st = $vj['state'];
    $status = (string)($st['status'] ?? '');
    $mt = (int)$vj['updated_at'];
    $jidRow = (string)$vj['job_id'];
    $jf = JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0);
    if ($status === 'running' && $mt > 0 && ($now - $mt) <= $jobStaleSec && strlen($jidRow) >= 8) {
        exit(json_encode(['ok' => true, 'mode' => 'running', 'jobId' => $jidRow, 'state' => $st], $jf));
    }
    $pf = $userDir . '/pending_video_confirm.json';
    if (is_file($pf) && is_readable($pf)) {
        $pdata = json_decode((string)file_get_contents($pf), true);
        if (is_array($pdata) && isset($pdata['results']) && is_array($pdata['results'])) {
            $op = (isset($pdata['ocr_preview']) && is_array($pdata['ocr_preview'])) ? $pdata['ocr_preview'] : [];
            exit(json_encode([
                'ok' => true,
                'mode' => 'pending',
                'job_id' => (string)($pdata['job_id'] ?? ''),
                'output_basename' => (string)($pdata['output_basename'] ?? ''),
                'results' => $pdata['results'],
                'ocr_preview' => $op,
            ], $jf));
        }
    }
    if ($status === 'done' && isset($st['results']) && is_array($st['results']) && strlen($jidRow) >= 8) {
        $op2 = (isset($st['ocr_preview']) && is_array($st['ocr_preview'])) ? $st['ocr_preview'] : [];
        exit(json_encode([
            'ok' => true,
            'mode' => 'pending',
            'job_id' => $jidRow,
            'output_basename' => (string)($st['output_basename'] ?? ''),
            'results' => $st['results'],
            'ocr_preview' => $op2,
        ], $jf));
    }
    exit(json_encode(['ok' => true, 'mode' => 'idle']));
}

