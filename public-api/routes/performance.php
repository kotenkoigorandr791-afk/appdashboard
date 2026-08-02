<?php
if (($parts[1] ?? '') === 'live' || isset($_GET['endpoint'])) {
    require APP_ROOT . '/project/performance/server_monitor.php';
    exit;
}
require_once APP_ROOT . '/project/performance/core/db.php';
require_once APP_ROOT . '/project/performance/core/session.php';
require_once APP_ROOT . '/project/performance/core/helpers.php';
require_once APP_ROOT . '/project/performance/controllers/MonitorController.php';
