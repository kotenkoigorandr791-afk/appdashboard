<?php
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
        $systemLimit = max(180, min(4000, (int)ceil($periodSeconds / 10)));
        $hourlyLimit = max(48, min(2000, (int)ceil($periodSeconds / 3600) + 24));
        $diskLimit = max(240, min(8000, $systemLimit * 6));

        $disk = [];
        $stmt = $db->prepare("SELECT * FROM (
            SELECT s.created_at, d.drive_letter, d.used_percent, d.free_bytes, d.total_bytes
            FROM monitor_disk_snapshots d
            JOIN monitor_system_snapshots s ON s.id = d.snapshot_id
            WHERE s.created_at >= :since
            ORDER BY s.created_at DESC, d.drive_letter ASC
            LIMIT :limit
        ) recent ORDER BY created_at ASC, drive_letter ASC");
        $stmt->bindValue(':since', $since, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $diskLimit, PDO::PARAM_INT);
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

        $hourly = [];
        $stmt = $db->prepare("SELECT * FROM (
            SELECT metric_hour, metric, app_id, min_value, avg_value, max_value, sample_count
            FROM monitor_metric_hourly
            WHERE metric_hour >= :since
            ORDER BY metric_hour DESC, metric ASC, app_id ASC
            LIMIT :limit
        ) recent ORDER BY metric_hour ASC, metric ASC, app_id ASC");
        $stmt->bindValue(':since', $since, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $hourlyLimit, PDO::PARAM_INT);
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
        $stmt = $db->prepare("SELECT * FROM (
            SELECT created_at, cpu_percent, memory_percent, swap_percent, disk_io_percent,
                   network_recv_bytes_per_sec, network_sent_bytes_per_sec
            FROM monitor_system_snapshots
            WHERE created_at >= :since
            ORDER BY created_at DESC
            LIMIT :limit
        ) recent ORDER BY created_at ASC");
        $stmt->bindValue(':since', $since, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $systemLimit, PDO::PARAM_INT);
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

        monitor_json_response([
            'ok' => true,
            'generated_at' => $now,
            'period' => $period,
            'disk' => $disk,
            'hourly' => $hourly,
            'system' => $system,
        ]);
    } catch (Exception $e) {
        monitor_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}
