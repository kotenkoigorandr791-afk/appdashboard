<?php
if (($parts[1] ?? '') === 'live' || isset($_GET['endpoint'])) {
    require APP_ROOT . '/apps/performance/server_monitor.php';
    exit;
}
require_once APP_ROOT . '/apps/performance/core/db.php';
require_once APP_ROOT . '/apps/performance/core/session.php';
require_once APP_ROOT . '/apps/performance/core/helpers.php';
require_once APP_ROOT . '/apps/performance/controllers/MonitorController.php';
