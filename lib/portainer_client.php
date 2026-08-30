<?php
function portainer_host_candidates(array $cfg): array {
    $candidates = [];
    $host = trim((string)($cfg['host'] ?? ''));
    $domain = trim((string)($cfg['domain'] ?? ''));
    $machine = trim((string)php_uname('n'));
    foreach ([$host, $domain !== '' ? 'http://' . $domain : '', $machine !== '' ? 'http://' . $machine . ':9000' : '', 'http://localhost:9000', 'http://127.0.0.1:9000'] as $candidate) {
        $candidate = rtrim(trim($candidate), '/');
        if ($candidate === '') continue;
        if (!preg_match('#^https?://#i', $candidate)) $candidate = 'http://' . $candidate;
        if (!preg_match('#:\d+$#', preg_replace('#^https?://#i', '', $candidate))) $candidate .= ':9000';
        $candidates[] = $candidate;
    }
    return array_values(array_unique($candidates));
}
function portainer_can_auth(string $baseUrl, string $username, string $password): bool {
    if ($username === '' || $password === '') return false;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($baseUrl, '/') . '/api/auth',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['username' => $username, 'password' => $password], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) return false;
    $decoded = json_decode((string)$response, true);
    return is_array($decoded) && trim((string)($decoded['jwt'] ?? '')) !== '';
}
function portainer_can_status(string $baseUrl): bool {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($baseUrl, '/') . '/api/status',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return $httpCode === 200;
}
function portainer_resolve_url(array $cfg): string {
    $username = trim((string)($cfg['username'] ?? ''));
    $password = trim((string)($cfg['password'] ?? ''));
    $fallback = 'http://localhost:9000';
    foreach (portainer_host_candidates($cfg) as $candidate) {
        if (portainer_can_auth($candidate, $username, $password)) return $candidate;
    }
    foreach (portainer_host_candidates($cfg) as $candidate) {
        if (portainer_can_status($candidate)) return $candidate;
    }
    return $fallback;
}
function portainer_auth(string $url, array &$cfg): ?string {
    $username = trim((string)($cfg['username'] ?? ''));
    $password = trim((string)($cfg['password'] ?? ''));
    if ($username === '' || $password === '') return null;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($url, '/') . '/api/auth',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['username' => $username, 'password' => $password], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) return null;
    $decoded = json_decode((string)$response, true);
    $jwt = is_array($decoded) ? trim((string)($decoded['jwt'] ?? '')) : '';
    if ($jwt === '') return null;
    $cfg['token'] = $jwt;
    if (function_exists('saveDockerConfig')) saveDockerConfig($cfg);
    return $jwt;
}
function portainer_bootstrap(): array {
    $cfg = loadDockerConfig();
    $url = portainer_resolve_url($cfg);
    $token = trim((string)($cfg['token'] ?? ''));
    if ($token === '') {
        $token = (string)(portainer_auth($url, $cfg) ?? '');
    }
    return ['url' => $url, 'token' => $token, 'cfg' => $cfg];
}
function portainer_call(string $path, string $method = 'GET', $data = null, int $timeout = 120, bool $retryAuth = true): array {
    static $ctx = null;
    if ($ctx === null) $ctx = portainer_bootstrap();
    $url = rtrim($ctx['url'], '/') . '/api/' . ltrim($path, '/');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $ctx['token'],
            'Content-Type: application/json',
        ],
    ]);
    if ($data !== null && $data !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    if ($retryAuth && ($httpCode === 401 || $httpCode === 403)) {
        $new = portainer_auth($ctx['url'], $ctx['cfg']);
        if ($new !== null) {
            $ctx['token'] = $new;
            return portainer_call($path, $method, $data, $timeout, false);
        }
    }
    if ($httpCode === 0) {
        return ['code' => 0, 'data' => ['message' => 'portainer_unreachable', 'url' => $ctx['url'], 'curl_error' => $curlErr], 'raw' => ''];
    }
    $decoded = json_decode((string)$response, true);
    return ['code' => $httpCode, 'data' => $decoded !== null ? $decoded : $response, 'raw' => $response];
}
function portainer_endpoint_id(): int {
    static $id = null;
    if ($id !== null) return $id;
    $r = portainer_call('endpoints');
    if ($r['code'] === 200 && is_array($r['data'])) {
        foreach ($r['data'] as $ep) {
            if (isset($ep['Type'], $ep['Id']) && (int)$ep['Type'] === 1) {
                $id = (int)$ep['Id'];
                return $id;
            }
        }
        if (!empty($r['data'][0]['Id'])) {
            $id = (int)$r['data'][0]['Id'];
            return $id;
        }
    }
    $id = 1;
    return $id;
}
function portainer_docker(string $suffix): array {
    return portainer_call('endpoints/' . portainer_endpoint_id() . '/docker/' . ltrim($suffix, '/'));
}
function portainer_docker_method(string $suffix, string $method = 'GET', $data = null, int $timeout = 120): array {
    return portainer_call('endpoints/' . portainer_endpoint_id() . '/docker/' . ltrim($suffix, '/'), $method, $data, $timeout);
}
function portainer_split_image(string $image): array {
    $image = preg_replace('/@sha256:[a-f0-9]+$/i', '', $image) ?? $image;
    $p = strrpos($image, ':');
    if ($p === false) return [$image, 'latest'];
    $suffix = substr($image, $p + 1);
    if (strpos($suffix, '/') !== false) return [$image, 'latest'];
    return [substr($image, 0, $p), $suffix];
}
function portainer_strip_nulls($v) {
    if (!is_array($v)) return $v;
    foreach ($v as $k => $x) {
        if ($x === null) unset($v[$k]);
        elseif (is_array($x)) $v[$k] = portainer_strip_nulls($x);
    }
    return $v;
}
function portainer_build_create_body(array $inspect): array {
    $cfg = $inspect['Config'] ?? [];
    $hc = $inspect['HostConfig'] ?? [];
    $body = [
        'Hostname' => $cfg['Hostname'] ?? '',
        'Domainname' => $cfg['Domainname'] ?? '',
        'User' => $cfg['User'] ?? '',
        'AttachStdin' => false,
        'AttachStdout' => true,
        'AttachStderr' => true,
        'Tty' => $cfg['Tty'] ?? false,
        'OpenStdin' => $cfg['OpenStdin'] ?? false,
        'StdinOnce' => false,
        'Env' => $cfg['Env'] ?? [],
        'Cmd' => $cfg['Cmd'] ?? null,
        'Image' => $cfg['Image'] ?? '',
        'Volumes' => $cfg['Volumes'] ?? null,
        'WorkingDir' => $cfg['WorkingDir'] ?? '',
        'Entrypoint' => $cfg['Entrypoint'] ?? null,
        'Labels' => $cfg['Labels'] ?? [],
        'StopSignal' => $cfg['StopSignal'] ?? null,
        'StopTimeout' => $cfg['StopTimeout'] ?? null,
        'Shell' => $cfg['Shell'] ?? null,
        'Healthcheck' => $cfg['Healthcheck'] ?? null,
        'HostConfig' => $hc,
    ];
    $networks = $inspect['NetworkSettings']['Networks'] ?? [];
    if (is_array($networks) && $networks !== []) {
        $endpoints = [];
        foreach ($networks as $name => $conf) {
            if (!is_array($conf)) continue;
            $ep = [];
            foreach (['Aliases', 'IPAMConfig', 'Links', 'DriverOpts'] as $k) {
                if (!empty($conf[$k])) $ep[$k] = $conf[$k];
            }
            $endpoints[$name] = $ep;
        }
        if ($endpoints !== []) $body['NetworkingConfig'] = ['EndpointsConfig' => $endpoints];
    }
    return portainer_strip_nulls($body);
}
function portainer_err(array $result): string {
    if (is_array($result['data'] ?? null) && isset($result['data']['message'])) return (string)$result['data']['message'];
    if (is_string($result['data'] ?? null) && $result['data'] !== '') return (string)$result['data'];
    return 'HTTP ' . (int)($result['code'] ?? 0);
}
