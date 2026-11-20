<?php
$stepsWorkerLongRunning = ['geolocation_import_run', 'geolocation_export_run', 'geolocation_immich_run', 'geolocation_maintenance_run', 'geolocation_privacy_run', 'geolocation_render_run', 'geolocation_boundaries_import', 'geolocation_migrate'];
$stepsWorkerName = pathinfo((string)($_SERVER['SCRIPT_FILENAME'] ?? ''), PATHINFO_FILENAME);
$stepsWorkerTimeoutMs = in_array($stepsWorkerName, $stepsWorkerLongRunning, true) ? 900000 : 120000;
$GLOBALS['app_db_extra_session_sql'] = [
    'SET statement_timeout = ' . $stepsWorkerTimeoutMs,
    'SET lock_timeout = 10000',
    'SET idle_in_transaction_session_timeout = 120000',
    'SET synchronous_commit = off',
    "SET application_name = 'steps-" . preg_replace('/[^a-z0-9_-]/i', '', $stepsWorkerName) . "'",
];
ini_set('memory_limit', getenv('STEPS_WORKER_MEMORY_LIMIT') ?: '512M');
if (function_exists('proc_nice')) @proc_nice(10);
