<?php
require_once __DIR__ . '/../core/monitor_metrics.php';
// Historical monitoring data for the sidebar statistics page.
if (isset($_GET['monitor_stats'])) {
    if ($userRole < 1) {
        monitor_json_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    $db = getDB();
    if (!$db) {
        monitor_json_response(['ok' => false, 'error' => 'db_unavailable'], 500);
    }

    try {
        $db->busyTimeout(5000);
        $db->exec('SELECT 1');
        $now = time();
        $period = isset($_GET['period']) ? strtolower((string)$_GET['period']) : '24h';
        $periodSecondsMap = [
            '1h' => 3600,
            '6h' => 6 * 3600,
            '24h' => 24 * 3600,
            '7d' => 7 * 24 * 3600,
            '30d' => 30 * 24 * 3600,
        ];
        $periodSeconds = $periodSecondsMap[$period] ?? (24 * 3600);
        $since = $now - $periodSeconds;
        $bucketSeconds = max(60, (int)ceil(($periodSeconds / 240) / 60) * 60);
        $bucketExpr = "(created_at / $bucketSeconds) * $bucketSeconds";
        $diskBucketExpr = "(s.created_at / $bucketSeconds) * $bucketSeconds";
        $smoothWindow = $periodSeconds >= 604800 ? 5 : ($periodSeconds >= 86400 ? 3 : 1);
        $smoothPoints = static function (array $points) use ($smoothWindow): array {
            if ($smoothWindow <= 1 || count($points) < 3) return $points;
            $half = intdiv($smoothWindow, 2);
            $count = count($points);
            for ($i = 0; $i < $count; $i++) {
                $sum = 0.0;
                $samples = 0;
                for ($j = max(0, $i - $half); $j <= min($count - 1, $i + $half); $j++) {
                    $sum += (float)$points[$j]['y'];
                    $samples++;
                }
                $points[$i]['y'] = round($sum / max(1, $samples), 2);
            }
            return $points;
        };

        $disk = [];
        $stmt = $db->prepare("
            SELECT $diskBucketExpr AS created_at, d.drive_letter, AVG(d.used_percent) AS used_percent,
                   AVG(d.free_bytes) AS free_bytes, AVG(d.total_bytes) AS total_bytes
            FROM monitor_disk_snapshots d
            JOIN monitor_system_snapshots s ON s.id = d.snapshot_id
            WHERE s.created_at >= :since
            GROUP BY $diskBucketExpr, d.drive_letter
            ORDER BY created_at ASC, d.drive_letter ASC");
        $stmt->bindValue(':since', $since, PDO::PARAM_INT);
        $res = $stmt->execute();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $drive = (string)($row['drive_letter'] ?? '');
            if ($drive === '') {
                continue;
            }
            if (!isset($disk[$drive])) {
                $disk[$drive] = [];
            }
            $disk[$drive][] = [
                'x' => monitor_format_time_label((int)$row['created_at']),
                'y' => round((float)$row['used_percent'], 2),
                'free_bytes' => (int)$row['free_bytes'],
                'total_bytes' => (int)$row['total_bytes'],
            ];
        }
        foreach ($disk as $drive => $points) {
            $disk[$drive] = $smoothPoints($points);
        }

        $hourly = [];
        $stmt = $db->prepare("
            SELECT metric_hour, metric, app_id, min_value, avg_value, max_value, sample_count
            FROM monitor_metric_hourly
            WHERE metric_hour >= :since
            ORDER BY metric_hour ASC, metric ASC, app_id ASC");
        $stmt->bindValue(':since', $since, PDO::PARAM_INT);
        $res = $stmt->execute();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $metric = (string)($row['metric'] ?? '');
            if ($metric === '') {
                continue;
            }
            if (!isset($hourly[$metric])) {
                $hourly[$metric] = [];
            }
            $hourly[$metric][] = [
                'x' => monitor_format_time_label((int)$row['metric_hour']),
                'y' => round((float)$row['avg_value'], 2),
                'min' => round((float)$row['min_value'], 2),
                'max' => round((float)$row['max_value'], 2),
                'samples' => (int)$row['sample_count'],
            ];
        }

        $system = [
            'cpu_percent' => [],
            'memory_percent' => [],
            'swap_percent' => [],
            'disk_io_percent' => [],
            'network_recv_kbps' => [],
            'network_sent_kbps' => [],
        ];
        $stmt = $db->prepare("
            SELECT $bucketExpr AS created_at,
                   AVG(cpu_percent) AS cpu_percent,
                   AVG(memory_percent) AS memory_percent,
                   AVG(swap_percent) AS swap_percent,
                   AVG(disk_io_percent) AS disk_io_percent,
                   AVG(network_recv_bytes_per_sec) AS network_recv_bytes_per_sec,
                   AVG(network_sent_bytes_per_sec) AS network_sent_bytes_per_sec
            FROM monitor_system_snapshots
            WHERE created_at >= :since
            GROUP BY $bucketExpr
            ORDER BY created_at ASC");
        $stmt->bindValue(':since', $since, PDO::PARAM_INT);
        $res = $stmt->execute();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $label = monitor_format_time_label((int)$row['created_at']);
            $system['cpu_percent'][] = ['x' => $label, 'y' => round((float)$row['cpu_percent'], 2)];
            $system['memory_percent'][] = ['x' => $label, 'y' => round((float)$row['memory_percent'], 2)];
            $system['swap_percent'][] = ['x' => $label, 'y' => round((float)$row['swap_percent'], 2)];
            $system['disk_io_percent'][] = ['x' => $label, 'y' => round((float)$row['disk_io_percent'], 2)];
            $system['network_recv_kbps'][] = ['x' => $label, 'y' => round(((float)$row['network_recv_bytes_per_sec']) / 1024, 2)];
            $system['network_sent_kbps'][] = ['x' => $label, 'y' => round(((float)$row['network_sent_bytes_per_sec']) / 1024, 2)];
        }
        foreach ($system as $metric => $points) {
            $system[$metric] = $smoothPoints($points);
        }

        monitor_json_response([
            'ok' => true,
            'generated_at' => $now,
            'period' => $period,
            'bucket_seconds' => $bucketSeconds,
            'disk' => $disk,
            'hourly' => $hourly,
            'system' => $system,
        ]);
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
    monitor_json_response($result);
}

if (isset($_POST['cleanup_metric_data'])) {
    if ($userRole < 2) {
        monitor_json_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    $metricType = trim((string)($_POST['metric_type'] ?? ''));
    $result = monitorCleanupMetricData($metricType);
    monitor_json_response($result);
}

if (isset($_POST['cleanup_chart_data'])) {
    if ($userRole < 2) {
        monitor_json_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    $chartGroup = trim((string)($_POST['chart_group'] ?? ''));
    $chartKey = trim((string)($_POST['chart_key'] ?? ''));
    $result = monitorCleanupChartData($chartGroup, $chartKey);
    monitor_json_response($result);
}

if (isset($_POST['cleanup_time_period'])) {
    if ($userRole < 2) {
        monitor_json_response(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    $startTime = (int)($_POST['start_time'] ?? 0);
    $endTime = (int)($_POST['end_time'] ?? 0);
    $result = monitorCleanupByTimePeriod($startTime, $endTime);
    monitor_json_response($result);
}
