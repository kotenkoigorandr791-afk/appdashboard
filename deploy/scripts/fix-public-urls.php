#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * Fix loopback OAuth issuer / public URLs after domain refactor.
 * Derives the public base URL from existing service URLs when BASE_DOMAIN is wrong.
 */
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/lib/app_domains.php';
require_once APP_ROOT . '/db.php';

function ap_url_host(string $url): string {
    return strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
}
function ap_is_loopback_host(string $host): bool {
    return $host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || str_ends_with($host, '.localhost');
}
function ap_base_from_service_host(string $host): string {
    if ($host === '' || ap_is_loopback_host($host)) {
        return '';
    }
    foreach (['git.', 'photo.', 'dawarich.', 'docker.', 'search.', 'ai.', 'document.'] as $prefix) {
        if (str_starts_with($host, $prefix)) {
            return substr($host, strlen($prefix));
        }
    }
    return $host;
}

$scheme = app_public_scheme();
$wantHost = '';
$want = rtrim(app_public_url(''), '/');
if (ap_is_loopback_host(ap_url_host($want))) {
    $want = '';
}
if ($want !== '') {
    $wantHost = ap_url_host($want);
}
if ($wantHost === '') {
    foreach (['gitea_url', 'immich_url', 'dawarich_url', 'docker_host', 'tg_webapp_base_url', 'app_public_base_url'] as $key) {
        $base = ap_base_from_service_host(ap_url_host(trim((string)getConfig($key, ''))));
        if ($base !== '') {
            $wantHost = $base;
            break;
        }
    }
}
if ($wantHost === '' || ap_is_loopback_host($wantHost)) {
    fwrite(STDERR, "fix-public-urls: no non-loopback base URL available\n");
    exit(0);
}
$want = 'https://' . $wantHost;
$updates = [
    'app_public_base_url' => $want,
    'tg_webapp_base_url' => $want,
    'search_engine_url' => 'https://search.' . $wantHost,
    'gitea_url' => 'https://git.' . $wantHost,
    'immich_url' => 'https://photo.' . $wantHost,
    'dawarich_url' => 'https://dawarich.' . $wantHost,
    'docker_host' => 'https://docker.' . $wantHost,
    'docker_domain' => 'docker.' . $wantHost,
];
foreach ($updates as $key => $value) {
    $cur = trim((string)getConfig($key, ''));
    $curHost = ap_url_host($cur);
    $needsFix = ($cur === '' || ap_is_loopback_host($curHost) || str_starts_with($cur, 'http://'));
    if ($needsFix) {
        setConfig($key, $value);
        echo "set {$key}={$value}\n";
    }
}
