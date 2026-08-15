<?php
declare(strict_types=1);
function cloud_session_start(): void {
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) return;
    $root = dirname(__DIR__, 3);
    require_once $root . '/lib/app_session.php';
    app_session_start();
}
