<?php
/**
 * Docker Manager — shared helpers: URL building, value formatting, HTML bits.
 */
declare(strict_types=1);

function dockerUrl(array $params = []) {
    $query = $_GET;
    unset($query['message']);
    foreach ($params as $k => $v) {
        if ($v === null) unset($query[$k]); else $query[$k] = $v;
    }
    return '/project/docker/?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

/**
 * Docker reports some counters as unsigned 64-bit values that overflow PHP's
 * int (pids limit is 2^64 on cgroup v2). Clamp instead of casting blindly.
 */
function dockerSafeInt($value): int {
    if (is_int($value)) return $value;
    if (!is_numeric($value)) return 0;
    $float = (float)$value;
    if (!is_finite($float) || $float > PHP_INT_MAX || $float < PHP_INT_MIN) return PHP_INT_MAX;
    return (int)$float;
}
function formatBytesValue($bytes) {
    $bytes = (float)$bytes;
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return rtrim(rtrim(number_format($bytes, $i === 0 ? 0 : 2, '.', ''), '0'), '.') . ' ' . $units[$i];
}
function clampPercent($value) {
    $value = is_numeric($value) ? (float)$value : 0.0;
    return max(0, min(100, $value));
}
function statBarHtml($label, $valueText, $percent, $class = '') {
    $p = clampPercent($percent);
    return '<div class="metric ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"><div class="metric-head"><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span><strong>' . htmlspecialchars($valueText, ENT_QUOTES, 'UTF-8') . '</strong></div><div class="metric-bar"><span style="width:' . htmlspecialchars((string)$p, ENT_QUOTES, 'UTF-8') . '%"></span></div></div>';
}

function dockerIsFragment(): bool {
    return (string)($_GET['fragment'] ?? '') === '1';
}
function dockerAssetLinks(): string {
    $out = '';
    foreach (['manager', 'views'] as $name) {
        $url = '/project/assets/docker/' . $name . '.css?v=' . @filemtime(__DIR__ . '/../assets/docker/' . $name . '.css');
        $out .= '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
    }
    return $out;
}
function dockerAppAttrs(): string {
    global $theme, $accent;
    $a = htmlspecialchars((string)$accent, ENT_QUOTES, 'UTF-8');
    $muted = $theme === 'light' ? 'rgba(37,99,235,0.1)' : sprintf('rgba(%d,%d,%d,0.14)', hexdec(substr($accent, 1, 2)), hexdec(substr($accent, 3, 2)), hexdec(substr($accent, 5, 2)));
    return 'id="docker-app" data-theme="' . htmlspecialchars((string)$theme, ENT_QUOTES, 'UTF-8') . '" style="--accent:' . $a . ';--accent-muted:' . $muted . '"';
}
function dockerOutput(string $html, string $title, bool $wrapApp): void {
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    if (dockerIsFragment()) {
        echo $html;
        return;
    }
    $body = $wrapApp ? '<div ' . dockerAppAttrs() . '>' . $html . '</div>' : $html;
    echo '<!DOCTYPE html><html lang="' . htmlspecialchars($GLOBALS['lang'], ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><style>html,body{margin:0;height:100%}#docker-app{height:100vh}</style>' . dockerAssetLinks() . '</head><body>' . $body . '</body></html>';
}
