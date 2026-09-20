<?php
declare(strict_types=1);
function appSecurityIsHttps(): bool {
    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $cfVisitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || $forwardedProto === 'https'
        || str_contains($cfVisitor, '"scheme":"https"');
}
function appSecurityIpMatchesCidr(string $ip, string $cidr): bool {
    if (!str_contains($cidr, '/')) return $ip === $cidr;
    [$subnet, $bits] = explode('/', $cidr, 2);
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) return false;
    $bits = max(0, min((int)$bits, strlen($ipBin) * 8));
    $bytes = intdiv($bits, 8);
    $remainder = $bits % 8;
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) return false;
    if ($remainder === 0) return true;
    $mask = (0xff << (8 - $remainder)) & 0xff;
    return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
}
function appSecurityTrustedProxyCidrs(): array {
    $configured = array_filter(array_map('trim', explode(',', (string)getenv('APP_TRUSTED_PROXIES'))));
    return array_values(array_unique(array_merge($configured, ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'])));
}
function appSecurityRemoteIsTrustedProxy(string $remote): bool {
    if ($remote === '' || !filter_var($remote, FILTER_VALIDATE_IP)) return false;
    foreach (appSecurityTrustedProxyCidrs() as $cidr) {
        if (appSecurityIpMatchesCidr($remote, $cidr)) return true;
    }
    return false;
}
function appSecurityFirstForwardedIp(string $value): string {
    foreach (explode(',', $value) as $part) {
        $ip = trim($part);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '';
}
function appSecurityClientIp(): string {
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if (appSecurityRemoteIsTrustedProxy($remote)) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP'] as $header) {
            $ip = trim((string)($_SERVER[$header] ?? ''));
            if (filter_var($ip, FILTER_VALIDATE_IP)) return substr($ip, 0, 128);
        }
        $xff = appSecurityFirstForwardedIp((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($xff !== '') return substr($xff, 0, 128);
    }
    return substr($remote !== '' ? $remote : '0.0.0.0', 0, 128);
}
