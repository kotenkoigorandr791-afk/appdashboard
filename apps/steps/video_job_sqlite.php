<?php
declare(strict_types=1);
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
require_once __DIR__ . '/core/store.php';

function video_job_save(AppDatabase $db, string $jobId, array $state): void {
    steps_job_save($db, 'video', $jobId, $state);
}
function video_job_load(AppDatabase $db): array {
    return steps_job_load($db, 'video');
}
function video_job_reset(AppDatabase $db): void {
    video_job_save($db, '', ['status' => 'idle', 'progress' => 0.0, 'message' => '', 'processed' => 0, 'total' => 0, 'results' => null, 'job_id' => '']);
}
function video_job_db_connect(string $userDir): ?AppDatabase {
    require_once __DIR__ . '/core/db_helpers.php';
    return getUserDB($userDir);
}
function video_jobs_disk_cleanup(string $userDir): void {
    @unlink($userDir . DIRECTORY_SEPARATOR . 'pending_video_confirm.json');
}
