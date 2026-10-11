<?php
require_once __DIR__ . '/../vendor/autoload.php';
use WebProject\DockerApiClient\Client\DockerApiClientWrapper;
use WebProject\DockerApiClient\Service\DockerService;
function docker_socket_path(): string {
    $path = getenv('DOCKER_SOCKET') ?: '/var/run/docker.sock';
    return is_string($path) && $path !== '' ? $path : '/var/run/docker.sock';
}
function docker_api_wrapper(): DockerApiClientWrapper {
    static $wrapper = null;
    if ($wrapper === null) {
        $wrapper = DockerApiClientWrapper::create('http://localhost', docker_socket_path(), 120);
    }
    return $wrapper;
}
function docker_service(): DockerService {
    static $service = null;
    if ($service === null) {
        $service = new DockerService(docker_api_wrapper());
    }
    return $service;
}
function docker_normalize_path(string $path): string {
    $path = ltrim($path, '/');
    if (preg_match('#^endpoints/\d+/docker/#', $path)) {
        $path = preg_replace('#^endpoints/\d+/docker/#', '', $path);
    }
    return '/' . ltrim((string)$path, '/');
}
function docker_call(string $path, string $method = 'GET', $data = null, int $timeout = 120): array {
    $body = '';
    if ($data !== null && $data !== '') {
        $body = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $socket = @stream_socket_client('unix://' . docker_socket_path(), $errno, $errstr, max(1, $timeout));
    if (!is_resource($socket)) {
        return ['code' => 0, 'data' => ['message' => 'docker_unreachable'], 'raw' => ''];
    }
    stream_set_timeout($socket, max(1, $timeout));
    $request = strtoupper($method) . ' ' . docker_normalize_path($path) . " HTTP/1.1\r\nHost: docker\r\nConnection: close\r\n";
    if ($body !== '') {
        $request .= "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n";
    }
    fwrite($socket, $request . "\r\n" . $body);
    $response = stream_get_contents($socket);
    fclose($socket);
    if (!is_string($response) || $response === '') {
        return ['code' => 0, 'data' => ['message' => 'docker_empty_response'], 'raw' => ''];
    }
    $parts = explode("\r\n\r\n", $response, 2);
    $headers = $parts[0] ?? '';
    $raw = $parts[1] ?? '';
    preg_match('#^HTTP/\S+\s+(\d+)#', $headers, $m);
    $code = isset($m[1]) ? (int)$m[1] : 0;
    if (stripos($headers, "Transfer-Encoding: chunked") !== false) {
        $raw = docker_decode_chunked($raw);
    }
    $decoded = json_decode($raw, true);
    return ['code' => $code, 'data' => $decoded !== null ? $decoded : $raw, 'raw' => $raw];
}
function docker_decode_chunked(string $raw): string {
    $out = '';
    $offset = 0;
    $length = strlen($raw);
    while ($offset < $length) {
        $pos = strpos($raw, "\r\n", $offset);
        if ($pos === false) break;
        $size = hexdec(trim(substr($raw, $offset, $pos - $offset)));
        if ($size <= 0) break;
        $offset = $pos + 2;
        $out .= substr($raw, $offset, $size);
        $offset += $size + 2;
    }
    return $out;
}
function docker_api(string $suffix): string {
    return ltrim($suffix, '/');
}
function docker_err(array $result): string {
    if (is_array($result['data'] ?? null) && isset($result['data']['message'])) return (string)$result['data']['message'];
    if (is_string($result['data'] ?? null) && $result['data'] !== '') return (string)$result['data'];
    return 'HTTP ' . (int)($result['code'] ?? 0);
}
function docker_split_image(string $image): array {
    $image = preg_replace('/@sha256:[a-f0-9]+$/i', '', $image) ?? $image;
    $p = strrpos($image, ':');
    if ($p === false) return [$image, 'latest'];
    $suffix = substr($image, $p + 1);
    if (strpos($suffix, '/') !== false) return [$image, 'latest'];
    return [substr($image, 0, $p), $suffix];
}
function docker_strip_nulls($v) {
    if (!is_array($v)) return $v;
    foreach ($v as $k => $x) {
        if ($x === null) unset($v[$k]);
        elseif (is_array($x)) $v[$k] = docker_strip_nulls($x);
    }
    return $v;
}
function docker_build_create_body(array $inspect): array {
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
    return docker_strip_nulls($body);
}
