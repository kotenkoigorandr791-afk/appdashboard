<?php
$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
$callerDir = isset($bt[0]['file']) ? dirname($bt[0]['file']) : __DIR__;
require_once __DIR__ . '/smart_log.php';
smart_log_bootstrap_auto($callerDir);
