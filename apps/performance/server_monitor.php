<?php
$__d = __DIR__;
for ($__n = 0; $__n < 16; $__n++) {
    if (is_file($__d . '/smart_log_boot.php')) {
        require_once $__d . '/smart_log_boot.php';
        break;
    }
    $__p = dirname($__d);
    if ($__p === $__d) {
        break;
    }
    $__d = $__p;
}
ini_set('log_errors', 0);
ini_set('display_errors', 0);
set_time_limit(30);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/core/monitor_metrics.php';

session_start();
$_sid = session_id() ?: ($_COOKIE['PHPSESSID'] ?? '');
$_sessionUser = $_sid ? getUserFromActiveSessions($_sid) : null;
$_userRole = (int)(($_sessionUser['user_data']['role'] ?? 0));
if ($_userRole < 1) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
session_write_close();

if (!function_exists('json_encode')) {
    header('Content-Type: text/plain; charset=utf-8');
    die('ERROR: JSON extension is not available. Please enable json extension in php.ini');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$action = isset($_GET['action']) ? strtolower(trim((string)$_GET['action'])) : '';
if ($action !== '') {
    if ($_userRole < 2 && in_array($action, ['save_uptime_check', 'delete_uptime_check'], true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'uptime') {
        monitorEnsureUptimeSchema();
        echo json_encode(['ok' => true] + monitorFetchUptimePayload(), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'run_uptime_checks') {
        $containers = monitorCollectDockerContainers();
        monitorRunUptimeChecks($containers);
        echo json_encode(['ok' => true] + monitorFetchUptimePayload(), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'docker_history') {
        echo json_encode(['ok' => true] + monitorFetchDockerHistory((string)($_GET['container_id'] ?? ''), (string)($_GET['period'] ?? '24h')), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'save_uptime_check') {
        $raw = file_get_contents('php://input');
        $input = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($input)) {
            $input = $_POST;
        }
        echo json_encode(monitorSaveUptimeCheckFromInput($input), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'delete_uptime_check') {
        echo json_encode(monitorDeleteUptimeCheck((int)($_GET['id'] ?? 0)), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$result = monitorFetchDisplayPayloadFromDb();

$requestedEndpoint = isset($_GET['endpoint']) ? strtolower(trim((string)$_GET['endpoint'])) : '';
if ($requestedEndpoint !== '') {
    switch ($requestedEndpoint) {
        case 'cpu':
            echo json_encode(['total' => (float)($result['cpu_usage'] ?? 0)], JSON_UNESCAPED_UNICODE);
            exit;
        case 'mem':
            echo json_encode(['percent' => (float)($result['memory_usage'] ?? 0)], JSON_UNESCAPED_UNICODE);
            exit;
        case 'memswap':
            echo json_encode(['percent' => (float)($result['swap_usage'] ?? 0)], JSON_UNESCAPED_UNICODE);
            exit;
        case 'diskio':
            echo json_encode([[
                'disk_name' => 'aggregate',
                'read_bytes' => 0,
                'write_bytes' => 0,
                'usage_percent' => (float)($result['disk_usage'] ?? 0),
            ]], JSON_UNESCAPED_UNICODE);
            exit;
        case 'network':
            $networkResponse = [];
            foreach (($result['network_data'] ?? []) as $interface) {
                if (!is_array($interface)) {
                    continue;
                }
                $networkResponse[] = [
                    'interface_name' => $interface['name'] ?? 'Unknown',
                    'bytes_sent_rate_per_sec' => (float)($interface['bytes_sent_rate'] ?? 0),
                    'bytes_recv_rate_per_sec' => (float)($interface['bytes_recv_rate'] ?? 0),
                    'bytes_all_rate_per_sec' => (float)($interface['total_rate'] ?? 0),
                    'speed' => (float)($interface['speed'] ?? 0),
                ];
            }
            echo json_encode($networkResponse, JSON_UNESCAPED_UNICODE);
            exit;
        case 'uptime':
            $uptime = $result['uptime'] ?? ['days' => 0, 'hours' => 0, 'minutes' => 0];
            $uptimeStr = sprintf('%d days, %02d:%02d:00', (int)($uptime['days'] ?? 0), (int)($uptime['hours'] ?? 0), (int)($uptime['minutes'] ?? 0));
            echo json_encode($uptimeStr, JSON_UNESCAPED_UNICODE);
            exit;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Unknown endpoint'], JSON_UNESCAPED_UNICODE);
            exit;
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
