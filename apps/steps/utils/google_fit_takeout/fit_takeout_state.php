<?php
declare(strict_types=1);
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
require_once __DIR__ . '/../../core/store.php';

function fit_takeout_save(AppDatabase $db, string $jobId, array $state): void {
    steps_job_save($db, 'fit', $jobId, $state);
}
function fit_takeout_load(AppDatabase $db): array {
    return steps_job_load($db, 'fit');
}
function fit_takeout_reset(AppDatabase $db): void {
    fit_takeout_save($db, '', ['status' => 'idle', 'progress' => 0.0, 'message' => '', 'imported' => 0, 'total_days' => 0, 'job_id' => '']);
}
