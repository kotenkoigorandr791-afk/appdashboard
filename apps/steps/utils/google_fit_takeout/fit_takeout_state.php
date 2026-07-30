<?php
declare(strict_types=1);
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}

function fit_takeout_ensure_schema(AppDatabase $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS fit_takeout_job_state (id INTEGER PRIMARY KEY CHECK (id = 1), job_id TEXT NOT NULL DEFAULT \'\', state_json TEXT NOT NULL DEFAULT \'{}\', updated_at INTEGER NOT NULL DEFAULT 0)');
    $db->exec('INSERT INTO fit_takeout_job_state (id, job_id, state_json, updated_at) VALUES (1, \'\', \'{}\', 0) ON CONFLICT (id) DO NOTHING');
}
function fit_takeout_save(AppDatabase $db, string $jobId, array $state): void {
    if (!isset($state['job_id']) || (string)$state['job_id'] === '') {
        $state['job_id'] = $jobId;
    }
    $j = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $t = time();
    $stmt = $db->prepare('INSERT INTO fit_takeout_job_state (id, job_id, state_json, updated_at) VALUES (1, :jid, :js, :ts) ON CONFLICT(id) DO UPDATE SET job_id = :jid2, state_json = :js2, updated_at = :ts2');
    $stmt->bindValue(':jid', $jobId, PDO::PARAM_STR);
    $stmt->bindValue(':js', $j, PDO::PARAM_STR);
    $stmt->bindValue(':ts', $t, PDO::PARAM_INT);
    $stmt->bindValue(':jid2', $jobId, PDO::PARAM_STR);
    $stmt->bindValue(':js2', $j, PDO::PARAM_STR);
    $stmt->bindValue(':ts2', $t, PDO::PARAM_INT);
    $stmt->execute();
}
function fit_takeout_load(AppDatabase $db): array {
    $res = $db->query('SELECT job_id, state_json, updated_at FROM fit_takeout_job_state WHERE id = 1');
    $row = $res ? $res->fetch(PDO::FETCH_ASSOC) : false;
    if (!$row) {
        return ['job_id' => '', 'state' => [], 'updated_at' => 0];
    }
    $st = json_decode((string)$row['state_json'], true);
    if (!is_array($st)) {
        $st = [];
    }
    return ['job_id' => (string)($row['job_id'] ?? ''), 'state' => $st, 'updated_at' => (int)($row['updated_at'] ?? 0)];
}
function fit_takeout_reset(AppDatabase $db): void {
    fit_takeout_save($db, '', ['status' => 'idle', 'progress' => 0.0, 'message' => '', 'imported' => 0, 'total_days' => 0, 'job_id' => '']);
}
