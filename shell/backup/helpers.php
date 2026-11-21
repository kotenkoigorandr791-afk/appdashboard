<?php
/**
 * Backup Manager — shared helpers: URLs, request parsing, formatting, HTML.
 */
declare(strict_types=1);

if (!defined('BACKUP_APP_PATH')) {
    define('BACKUP_APP_PATH', '/project/backup/index.php');
}

function backupUrl(array $params = []): string {
    $query = $_GET;
    unset($query['message']);
    foreach ($params as $k => $v) {
        if ($v === null) unset($query[$k]); else $query[$k] = $v;
    }
    $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    return BACKUP_APP_PATH . ($qs !== '' ? '?' . $qs : '');
}

function backupSafeInt($value): int {
    if (is_int($value)) return $value;
    if (!is_numeric($value)) return 0;
    $float = (float)$value;
    if (!is_finite($float) || $float > PHP_INT_MAX || $float < PHP_INT_MIN) return PHP_INT_MAX;
    return (int)$float;
}

if (!function_exists('formatBytesValue')) {
    function formatBytesValue($bytes): string {
        $bytes = (float)$bytes;
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return rtrim(rtrim(number_format($bytes, $i === 0 ? 0 : 2, '.', ''), '0'), '.') . ' ' . $units[$i];
    }
}

if (!function_exists('clampPercent')) {
    function clampPercent($value): float {
        $value = is_numeric($value) ? (float)$value : 0.0;
        return max(0.0, min(100.0, $value));
    }
}

if (!function_exists('statBarHtml')) {
    function statBarHtml($label, $valueText, $percent, $class = ''): string {
        $p = clampPercent($percent);
        return '<div class="metric ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"><div class="metric-head"><span>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><strong>'
            . htmlspecialchars($valueText, ENT_QUOTES, 'UTF-8') . '</strong></div><div class="metric-bar"><span style="width:'
            . htmlspecialchars((string)$p, ENT_QUOTES, 'UTF-8') . '%"></span></div></div>';
    }
}

function backupEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function backupJsonResponse($data, int $code = 200): void {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function backupRequestJson(): array {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function backupRequestData(): array {
    $json = backupRequestJson();
    if ($json !== []) return $json;
    return is_array($_POST) ? $_POST : [];
}

function backupStringList($value, int $maxItems = 200): array {
    if (is_array($value)) {
        $rows = $value;
    } else {
        $rows = preg_split('/\R/u', (string)$value) ?: [];
    }
    $out = [];
    foreach ($rows as $row) {
        if (!is_string($row)) continue;
        $row = trim($row);
        if ($row === '' || str_contains($row, "\0")) continue;
        $out[] = $row;
        if (count($out) >= $maxItems) break;
    }
    return array_values(array_unique($out));
}

function backupClampInt($value, int $min, int $max, int $default = 0): int {
    $v = backupSafeInt($value);
    if ($v < $min) return $min;
    if ($v > $max) return $max;
    return $v;
}

function backupRepoIsLocal(string $repo): bool {
    return $repo !== '' && $repo[0] === '/';
}

function backupFormatDuration(int $seconds): string {
    if ($seconds <= 0) return '—';
    if ($seconds < 60) return $seconds . 's';
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    if ($m < 60) return $m . 'm ' . $s . 's';
    $h = intdiv($m, 60);
    return $h . 'h ' . ($m % 60) . 'm';
}

function backupFormatTime($ts): string {
    $ts = (int)$ts;
    if ($ts <= 0) return '—';
    return gmdate('Y-m-d H:i:s', $ts) . ' UTC';
}
