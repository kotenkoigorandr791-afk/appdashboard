<?php
require_once __DIR__ . '/../core/monitor_metrics.php';
// Historical monitoring data for the sidebar statistics page.
if (isset($_GET['monitor_stats'])) {
    if ($userRole < 1) {
        monitor_json_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    try {
        $period = isset($_GET['period']) ? strtolower((string)$_GET['period']) : '24h';
        monitor_json_response(monitorFetchStatsPayload($period));
    } catch (Exception $e) {
        monitor_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if (isset($_POST['cleanup_smart_device'])) {
    if ($userRole < 2) {
        monitor_json_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    $devicePath = trim((string)($_POST['device_path'] ?? ''));
    $result = monitorCleanupSmartDevice($devicePath);
    if (!empty($result['ok'])) {
        monitor_cache_bust();
    }
    monitor_json_response($result);
}

if (isset($_POST['cleanup_metric_data'])) {
    if ($userRole < 2) {
        monitor_json_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    $metricType = trim((string)($_POST['metric_type'] ?? ''));
    $result = monitorCleanupMetricData($metricType);
    if (!empty($result['ok'])) {
        monitor_cache_bust();
    }
    monitor_json_response($result);
}

if (isset($_POST['cleanup_chart_data'])) {
    if ($userRole < 2) {
        monitor_json_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    $chartGroup = trim((string)($_POST['chart_group'] ?? ''));
    $chartKey = trim((string)($_POST['chart_key'] ?? ''));
    $result = monitorCleanupChartData($chartGroup, $chartKey);
    if (!empty($result['ok'])) {
        monitor_cache_bust();
    }
    monitor_json_response($result);
}

if (isset($_POST['cleanup_time_period'])) {
    if ($userRole < 2) {
        monitor_json_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    $startTime = (int)($_POST['start_time'] ?? 0);
    $endTime = (int)($_POST['end_time'] ?? 0);
    $result = monitorCleanupByTimePeriod($startTime, $endTime);
    if (!empty($result['ok'])) {
        monitor_cache_bust();
    }
    monitor_json_response($result);
}
