<?php
/**
 * Public base URL a phone can use to reach this dashboard.
 *
 * The pairing modal used to build the endpoint from SERVER_ADDR/SERVER_PORT,
 * which inside Docker are the container IP (172.18.0.x) and the internal nginx
 * port (8080). A phone can reach neither, so every generated link failed on the
 * first try even though the token was valid.
 *
 * Rules, most reliable first:
 *  1. MOBILE_PAIR_BASE_URL (full URL) - explicit admin override.
 *  2. The origin the browser used (X-Forwarded-* first, then Host), unless it
 *     is loopback or a Docker bridge address.
 *  3. MOBILE_PAIR_HOST (host[:port]) when the browser is on loopback, so a
 *     dashboard opened at localhost/127.0.0.1 still yields a LAN address.
 *  4. A LAN address found from the machine hostname (correct on bare metal; in
 *     a container this finds the bridge address and is skipped).
 */

function mobile_pair_request_scheme(): string
{
    $forwarded = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwarded !== '') {
        $forwarded = trim(explode(',', $forwarded)[0]);
        if ($forwarded === 'https' || $forwarded === 'http') return $forwarded;
    }
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return 'https';
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') return 'https';
    return 'http';
}

/** Split "host", "host:port" or "[v6]:port" into [host, port|null]. */
function mobile_pair_split_host(string $value): array
{
    $value = trim($value);
    if ($value === '') return ['', null];
    if (preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $value, $m)) {
        return [$m[1], isset($m[2]) && $m[2] !== '' ? (string)(int)$m[2] : null];
    }
    if (substr_count($value, ':') === 1) {
        [$host, $port] = explode(':', $value, 2);
        if (ctype_digit($port)) return [trim($host), (string)(int)$port];
    }
    return [$value, null];
}

function mobile_pair_host_is_loopback(string $host): bool
{
    $host = strtolower(trim($host, " \t[]"));
    return $host === '' || $host === 'localhost' || $host === '::1'
        || str_starts_with($host, '127.') || $host === '0.0.0.0';
}

/** False for public and LAN addresses; true for Docker bridge ranges used here. */
function mobile_pair_host_is_internal(string $host): bool
{
    if (mobile_pair_host_is_loopback($host)) return true;
    return (bool)preg_match('/^172\.(1[7-9])\./', $host);
}

function mobile_pair_lan_ip(): string
{
    $candidates = [];
    $hostname = gethostname();
    if (is_string($hostname) && $hostname !== '' && function_exists('gethostbynamel')) {
        $candidates = gethostbynamel($hostname) ?: [];
    }
    $candidates[] = (string)($_SERVER['SERVER_ADDR'] ?? '');

    $private = '';
    foreach ($candidates as $ip) {
        $ip = trim((string)$ip);
        if ($ip === '' || mobile_pair_host_is_loopback($ip) || mobile_pair_host_is_internal($ip)) continue;
        if (str_starts_with($ip, '169.254.')) continue;
        if (str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) return $ip;
        if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip) && $private === '') $private = $ip;
    }
    return $private;
}

function mobile_pair_public_base_url(): string
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $override = getenv('MOBILE_PAIR_BASE_URL');
    if (is_string($override) && trim($override) !== '') {
        return $cached = rtrim(trim($override), '/');
    }

    $scheme = mobile_pair_request_scheme();
    $hostHeader = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '');
    if (trim($hostHeader) === '') $hostHeader = (string)($_SERVER['HTTP_HOST'] ?? '');
    $hostHeader = trim(explode(',', $hostHeader)[0]);

    [$host, $port] = mobile_pair_split_host($hostHeader);
    if ($port === null) {
        $forwardedPort = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PORT'] ?? ''))[0]);
        if (ctype_digit($forwardedPort)) $port = (string)(int)$forwardedPort;
    }

    if (mobile_pair_host_is_internal($host)) {
        $pairHost = getenv('MOBILE_PAIR_HOST');
        if (is_string($pairHost) && trim($pairHost) !== '') {
            [$overrideHost, $overridePort] = mobile_pair_split_host($pairHost);
            if ($overrideHost !== '') {
                $host = $overrideHost;
                if ($overridePort !== null) $port = $overridePort;
            }
        } else {
            $lan = mobile_pair_lan_ip();
            if ($lan !== '') {
                $host = $lan;
                // The internal port is meaningless for the phone; the detected
                // LAN address serves on the scheme default unless overridden.
                if ($port !== null && ($port === '80' || $port === '443')) $port = null;
                if ($port !== null && (int)$port >= 8000 && (int)$port <= 8099) $port = null;
            }
        }
    }

    if ($host === '') return $cached = '';

    $portPart = '';
    if ($port !== null && !(($scheme === 'http' && $port === '80') || ($scheme === 'https' && $port === '443'))) {
        $portPart = ':' . $port;
    }
    return $cached = $scheme . '://' . $host . $portPart;
}

/** Pairing endpoint for one public app API (spends|steps). */
function mobile_pair_endpoint(string $app): string
{
    $base = mobile_pair_public_base_url();
    return $base === '' ? '' : $base . '/api/' . $app;
}
