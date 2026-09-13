<?php
declare(strict_types=1);

function hc_volume_dir(string $userDir, bool $ensure = true): string {
    $dir = rtrim($userDir, '/') . '/volume';
    if ($ensure && !is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

function hc_health_data_dir(string $userDir, bool $ensure = true): string {
    $dir = hc_volume_dir($userDir, $ensure) . '/health_connect';
    if ($ensure && !is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

function hc_health_raw_dir(string $userDir, bool $ensure = true): string {
    $dir = hc_health_data_dir($userDir, $ensure) . '/raw';
    if ($ensure && !is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

function hc_health_days_dir(string $userDir, bool $ensure = true): string {
    $dir = hc_health_data_dir($userDir, $ensure) . '/days';
    if ($ensure && !is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

function hc_safe_date_from_time_value(mixed $v): ?string {
    if (!is_string($v) || trim($v) === '') return null;
    $v = trim($v);
    // Expect ISO-8601. We only need the date part in the record's own timezone.
    try {
        $dt = new DateTime($v);
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        // Fallback: if it starts with YYYY-MM-DD
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $m)) return $m[1];
        return null;
    }
}

function hc_extract_record_date(array $record): ?string {
    // Sleep sessions in mobile payload don't provide a top-level start_time.
    // Use the earliest stage start day so the chart matches the day the user
    // actually started sleeping (not the day it ended).
    if (array_key_exists('session_end_time', $record) && array_key_exists('stages', $record) && is_array($record['stages']) && !empty($record['stages'])) {
        $stageDates = [];
        foreach ($record['stages'] as $stage) {
            if (!is_array($stage)) continue;
            $d = hc_safe_date_from_time_value($stage['start_time'] ?? ($stage['time'] ?? null));
            if ($d !== null) $stageDates[] = $d;
        }
        if ($stageDates) {
            sort($stageDates);
            return (string)$stageDates[0];
        }
    }
    foreach (['start_time', 'time', 'end_time', 'session_end_time', 'measurement_time'] as $k) {
        if (array_key_exists($k, $record)) {
            $d = hc_safe_date_from_time_value($record[$k]);
            if ($d !== null) return $d;
        }
    }
    return null;
}

function hc_payload_normalize_root(array $payload): array {
    $ts = $payload['timestamp'] ?? null;
    $appVersion = $payload['app_version'] ?? null;
    $out = [
        'timestamp' => is_string($ts) ? $ts : (string)time(),
        'app_version' => is_string($appVersion) ? $appVersion : '',
    ];

    foreach ($payload as $k => $v) {
        if (!is_string($k)) continue;
        if ($k === 'timestamp' || $k === 'app_version') continue;
        $out[$k] = is_array($v) ? $v : [];
    }
    return $out;
}

function hc_payload_extract_steps_daily(array $payload): array {
    // Output: [YYYY-MM-DD => totalCount]
    $stepsArr = $payload['steps'] ?? [];
    if (!is_array($stepsArr) || empty($stepsArr)) return [];
    $out = [];
    foreach ($stepsArr as $rec) {
        if (!is_array($rec)) continue;
        $date = is_string($rec['date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$rec['date']) ? (string)$rec['date'] : hc_safe_date_from_time_value($rec['start_time'] ?? ($rec['end_time'] ?? null));
        if ($date === null) continue;
        $cnt = (int)($rec['count'] ?? 0);
        if ($cnt < 0) $cnt = 0;
        $out[$date] = ($out[$date] ?? 0) + $cnt;
    }
    return $out;
}

function hc_json_atomic_write(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $tmp = $path . '.tmp_' . bin2hex(random_bytes(6));
    @file_put_contents($tmp, $content, LOCK_EX);
    @rename($tmp, $path);
}

/**
 * Schema for the compact per-day metrics we keep. Only the data needed for
 * charts/summaries is stored; the raw Health Connect response is never written
 * to disk. sum = accumulate a daily total, series = keep min/max/avg/last.
 */
function hc_metric_type_config(): array {
    return [
        'steps'           => ['kind' => 'sum',    'fields' => ['count'],                          'total' => 'total',        'unit' => ''],
        'distance'        => ['kind' => 'sum',    'fields' => ['meters', 'distance_meters', 'distance'], 'total' => 'total_meters', 'unit' => 'meters'],
        'active_calories' => ['kind' => 'sum',    'fields' => ['calories'],                       'total' => 'total',        'unit' => 'kcal'],
        'total_calories'  => ['kind' => 'sum',    'fields' => ['calories'],                       'total' => 'total',        'unit' => 'kcal'],
        'sleep'           => ['kind' => 'sum',    'fields' => ['duration_seconds', 'duration', 'duration_millis'], 'total' => 'total_seconds', 'unit' => 'seconds', 'scales' => ['duration_millis' => 0.001]],
        // Mobile payload sometimes sends aggregate HR as { time, avg, min, max } (without bpm).
        // Keep series aggregation but allow using whichever numeric field is present per record.
        'heart_rate'      => ['kind' => 'series', 'fields' => ['avg', 'bpm', 'value', 'heart_rate'], 'unit' => 'bpm'],
        'weight'          => ['kind' => 'series', 'fields' => ['kilograms', 'weight_kg', 'weight', 'value'], 'unit' => 'kg'],
        'blood_pressure'  => ['kind' => 'series', 'fields' => ['systolic'],                       'unit' => 'mmHg'],
        'oxygen_spo2'     => ['kind' => 'series', 'fields' => ['percentage', 'value'],            'unit' => '%'],
    ];
}

function hc_metric_record_value(array $rec, array $cfg): ?float {
    $scales = $cfg['scales'] ?? [];
    foreach ($cfg['fields'] as $f) {
        if (array_key_exists($f, $rec) && is_numeric($rec[$f])) {
            $v = (float)$rec[$f];
            if (isset($scales[$f])) $v *= (float)$scales[$f];
            return $v;
        }
    }
    return null;
}

function hc_aggregate_records_by_type(string $type, array $records): array {
    $cfg = hc_metric_type_config()[$type] ?? null;
    if ($cfg === null) return [];
    $agg = ['samples' => 0];
    $sumSeries = 0.0;
    foreach ($records as $rec) {
        if (!is_array($rec)) continue;
        $agg['samples']++;
        $v = hc_metric_record_value($rec, $cfg);
        if ($v === null) continue;
        if ($cfg['kind'] === 'sum') {
            $agg[$cfg['total']] = ($agg[$cfg['total']] ?? 0) + $v;
        } else {
            $agg['min'] = isset($agg['min']) ? min($agg['min'], $v) : $v;
            $agg['max'] = isset($agg['max']) ? max($agg['max'], $v) : $v;
            $agg['last'] = $v;
            $sumSeries += $v;
        }
    }
    if ($cfg['kind'] === 'series' && $agg['samples'] > 0 && isset($agg['min'])) {
        $agg['avg'] = $sumSeries / $agg['samples'];
    }
    if (!empty($cfg['unit'])) $agg['unit'] = $cfg['unit'];
    if (isset($agg['total']) && (float)$agg['total'] == (int)$agg['total']) {
        $agg['total'] = (int)$agg['total'];
    }
    return $agg;
}

function hc_aggregate_payload_metrics(array $payload): array {
    // Output: [YYYY-MM-DD => [type => compact metric]]
    $normalized = hc_payload_normalize_root($payload);
    $schema = hc_metric_type_config();
    $byDay = [];
    foreach ($normalized as $k => $arr) {
        if ($k === 'timestamp' || $k === 'app_version') continue;
        if (!is_array($arr) || !isset($schema[$k])) continue;
        foreach ($arr as $rec) {
            if (!is_array($rec)) continue;
            $d = hc_extract_record_date($rec);
            if ($d === null) continue;
            $byDay[$d][$k][] = $rec;
        }
    }
    $out = [];
    foreach ($byDay as $d => $types) {
        foreach ($types as $t => $recs) {
            $agg = hc_aggregate_records_by_type((string)$t, $recs);
            if ($agg) $out[$d][$t] = $agg;
        }
    }
    return $out;
}

function hc_metrics_from_legacy_day(array $day): array {
    // Migrate old-format day files (full record arrays under 'types').
    $metrics = [];
    $types = $day['types'] ?? null;
    if (is_array($types)) {
        foreach ($types as $t => $recs) {
            if (!is_array($recs)) continue;
            $agg = hc_aggregate_records_by_type((string)$t, $recs);
            if ($agg) $metrics[$t] = $agg;
        }
    }
    if (isset($day['steps_daily_total'])) {
        if (isset($metrics['steps'])) {
            $metrics['steps']['total'] = (int)$day['steps_daily_total'];
        } else {
            $metrics['steps'] = ['samples' => 0, 'total' => (int)$day['steps_daily_total']];
        }
    }
    return $metrics;
}

function hc_read_day_metrics(string $userDir, string $date): array {
    $dir = hc_health_days_dir($userDir, false);
    $path = $dir . '/' . $date . '.json';
    if (!is_file($path)) return [];
    $day = json_decode((string)file_get_contents($path), true);
    if (!is_array($day)) return [];
    if (isset($day['metrics']) && is_array($day['metrics'])) return $day['metrics'];
    $metrics = hc_metrics_from_legacy_day($day);
    if ($metrics) {
        $day['metrics'] = $metrics;
        unset($day['types']);
        hc_json_atomic_write($path, json_encode($day, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    return $metrics;
}

function hc_read_metrics_range(string $userDir, string $from, string $to): array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$to)) {
        return [];
    }
    if (strcmp($from, $to) > 0) {
        [$from, $to] = [$to, $from];
    }
    if (strcmp($to, $from) > 0 && (int)floor(((int)strtotime($to) - (int)strtotime($from)) / 86400) > 1000) {
        return [];
    }
    $out = [];
    $cd = new DateTime($from);
    $end = new DateTime($to);
    while ($cd <= $end) {
        $ds = $cd->format('Y-m-d');
        $m = hc_read_day_metrics($userDir, $ds);
        if ($m) $out[] = ['date' => $ds, 'metrics' => $m];
        $cd->modify('+1 day');
    }
    return $out;
}

function hc_store_payload_json(string $userDir, array $payload): array {
    $dirRaw = hc_health_raw_dir($userDir, false);
    $dirDays = hc_health_days_dir($userDir, true);

    $stepsDaily = hc_payload_extract_steps_daily($payload);
    $perDay = hc_aggregate_payload_metrics($payload);

    $days = array_unique(array_merge(array_keys($stepsDaily), array_keys($perDay)));
    $storedDays = [];
    foreach ($days as $d) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d)) continue;
        $metrics = hc_read_day_metrics($userDir, $d);
        foreach (($perDay[$d] ?? []) as $type => $agg) {
            $metrics[$type] = $agg;
        }
        if (isset($stepsDaily[$d])) {
            $metrics['steps'] = array_merge($metrics['steps'] ?? ['samples' => 0], ['total' => (int)$stepsDaily[$d]]);
        }
        $obj = ['date' => $d, 'metrics' => $metrics];
        if (isset($stepsDaily[$d])) $obj['steps_daily_total'] = (int)$stepsDaily[$d];
        hc_json_atomic_write($dirDays . '/' . $d . '.json', json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $storedDays[] = $d;
    }

    // Legacy full-response files are no longer produced; clean them up.
    if (is_dir($dirRaw)) {
        foreach (glob($dirRaw . '/payload_*.json') ?: [] as $legacyRaw) {
            @unlink($legacyRaw);
        }
    }

    return [
        'steps_daily' => $stepsDaily,
        'stored_days' => $storedDays,
    ];
}
