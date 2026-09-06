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
        $date = hc_safe_date_from_time_value($rec['start_time'] ?? ($rec['end_time'] ?? null));
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

function hc_store_payload_json(string $userDir, array $payload): array {
    $dirRaw = hc_health_raw_dir($userDir, true);
    $dirDays = hc_health_days_dir($userDir, true);

    $normalized = hc_payload_normalize_root($payload);

    $ts = $normalized['timestamp'] ?? '';
    $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $fileBase = ($ts !== '' ? str_replace([':', '.'], '-', $ts) : 't_' . time()) . '_' . substr($hash, 0, 12);
    $rawPath = $dirRaw . '/payload_' . $fileBase . '.json';
    hc_json_atomic_write($rawPath, json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // Store per-day payload slices for all keys except timestamp/app_version.
    // Each day file keeps arrays grouped by data-type.
    $stepsDaily = hc_payload_extract_steps_daily($payload);

    $keys = array_keys($normalized);
    $dayFiles = [];
    foreach ($keys as $k) {
        if ($k === 'timestamp' || $k === 'app_version') continue;
        $arr = $normalized[$k];
        if (!is_array($arr) || empty($arr)) continue;
        foreach ($arr as $rec) {
            if (!is_array($rec)) continue;
            $d = hc_extract_record_date($rec);
            if ($d === null) continue;
            $dayPath = $dirDays . '/' . $d . '.json';
            if (!isset($dayFiles[$d])) {
                $dayObj = ['date' => $d, 'types' => []];
                if (is_file($dayPath)) {
                    $prev = json_decode((string)file_get_contents($dayPath), true);
                    if (is_array($prev)) $dayObj = $prev;
                }
                if (!isset($dayObj['types']) || !is_array($dayObj['types'])) $dayObj['types'] = [];
                $dayFiles[$d] = $dayObj;
            }
            $dayFiles[$d]['types'][$k] = $dayFiles[$d]['types'][$k] ?? [];
            $seen = $dayFiles[$d]['types'][$k];
            $matches = array_filter($seen, static fn($x) => is_array($x) && $x === $rec);
            if (!$matches) {
                $dayFiles[$d]['types'][$k][] = $rec;
            }
        }
    }

    // Also ensure steps totals are present for that day.
    foreach ($stepsDaily as $d => $total) {
        $dayPath = $dirDays . '/' . $d . '.json';
        if (!isset($dayFiles[$d])) {
            $dayObj = ['date' => $d, 'types' => []];
            if (is_file($dayPath)) {
                $prev = json_decode((string)file_get_contents($dayPath), true);
                if (is_array($prev)) $dayObj = $prev;
            }
            if (!isset($dayObj['types']) || !is_array($dayObj['types'])) $dayObj['types'] = [];
            $dayFiles[$d] = $dayObj;
        }
        $dayFiles[$d]['steps_daily_total'] = (int)$total;
    }

    foreach ($dayFiles as $d => $obj) {
        $dayPath = $dirDays . '/' . $d . '.json';
        hc_json_atomic_write($dayPath, json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    return [
        'raw_file' => basename($rawPath),
        'steps_daily' => $stepsDaily,
        'stored_days' => array_keys($dayFiles),
    ];
}
