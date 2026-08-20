<?php
public_api_auth_user(true);
$parts = public_api_parse_path();
$action = $parts[1] ?? '';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($action === 'monitor' && $method === 'GET') {
    public_api_require_role(2);
    require_once APP_ROOT . '/project/performance/core/monitor_metrics.php';
    public_api_json([
        'ok' => true,
        'metrics' => [
            'cpu' => function_exists('getCpuUsage') ? getCpuUsage() : null,
            'memory' => function_exists('getMemoryUsage') ? getMemoryUsage() : null,
            'swap' => function_exists('getSwapUsage') ? getSwapUsage() : null,
            'drives' => function_exists('getDrives') ? getDrives() : null,
            'disk_io' => function_exists('getDiskIO') ? getDiskIO() : null,
            'network' => function_exists('getNetworkData') ? getNetworkData() : null,
            'uptime' => function_exists('getUptime') ? getUptime() : null,
        ],
    ]);
}
if (($action === 'live' || isset($_GET['endpoint']))) {
    require APP_ROOT . '/project/performance/server_monitor.php';
    exit;
}
require_once APP_ROOT . '/project/performance/core/db.php';
require_once APP_ROOT . '/project/performance/core/session.php';
require_once APP_ROOT . '/project/performance/core/helpers.php';
require_once APP_ROOT . '/project/performance/controllers/MonitorController.php';
