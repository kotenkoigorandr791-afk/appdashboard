<?php
/**
 * Docker Manager — Engine API transport and container/stack primitives.
 */
declare(strict_types=1);

function callDockerAPI($endpoint, $method = 'GET', $data = null, $timeout = 120, $retryAuth = true) {
    return docker_call((string)$endpoint, (string)$method, $data, (int)$timeout);
}
function splitDockerImageRef($image) {
    $image = preg_replace('/@sha256:[a-f0-9]+$/i', '', (string)$image);
    $p = strrpos($image, ':');
    if ($p === false) {
        return [$image, 'latest'];
    }
    $suffix = substr($image, $p + 1);
    if (strpos($suffix, '/') !== false) {
        return [$image, 'latest'];
    }
    return [substr($image, 0, $p), $suffix];
}
function dockerErrorFromResult(array $result) {
    if (is_array($result['data'] ?? null) && isset($result['data']['message'])) {
        return (string)$result['data']['message'];
    }
    if (is_string($result['data'] ?? null) && $result['data'] !== '') {
        return (string)$result['data'];
    }
    return 'HTTP ' . (int)($result['code'] ?? 0);
}
function dockerActionMessage(array $result, array $okCodes, $successKey, array $vars = []) {
    if (in_array((int)($result['code'] ?? 0), $okCodes, true)) {
        return tf($successKey, $vars);
    }
    return tf('docker_action_error', ['error' => dockerErrorFromResult($result)]);
}
function validStackIdParam($id) {
    return is_string($id) && preg_match('/^\d+$/', $id);
}
function dockerStackById($id) {
    return null;
}
function dockerStackFileContent($id) {
    return [null, t('docker_stack_not_found')];
}
function dockerUpdateStackFile(array $stack, $content, $prune = false, $pullImage = false) {
    return ['code' => 400, 'data' => ['message' => t('docker_stack_not_found')]];
}
function dockerRedirect($message) {
    header('Location: ' . dockerUrl(['message' => $message, 'action' => null, 'id' => null, 'image' => null, 'view' => null]));
    exit;
}
function dockerImageRefFromImage(array $image) {
    if (!empty($image['RepoTags']) && is_array($image['RepoTags'])) {
        foreach ($image['RepoTags'] as $tag) {
            if (is_string($tag) && $tag !== '' && $tag !== '<none>:<none>') {
                return $tag;
            }
        }
    }
    return (string)($image['Id'] ?? '');
}
function stripNulls($v) {
    if (!is_array($v)) {
        return $v;
    }
    foreach ($v as $k => $x) {
        if ($x === null) {
            unset($v[$k]);
        } elseif (is_array($x)) {
            $v[$k] = stripNulls($x);
        }
    }
    return $v;
}
function buildNetworkingConfigFromInspect(array $inspect) {
    $networks = $inspect['NetworkSettings']['Networks'] ?? [];
    if ($networks === []) {
        return [];
    }
    $endpoints = [];
    foreach ($networks as $name => $conf) {
        if (!is_array($conf)) {
            continue;
        }
        $ep = [];
        if (!empty($conf['Aliases'])) {
            $ep['Aliases'] = $conf['Aliases'];
        }
        if (!empty($conf['IPAMConfig'])) {
            $ep['IPAMConfig'] = $conf['IPAMConfig'];
        }
        if (!empty($conf['Links'])) {
            $ep['Links'] = $conf['Links'];
        }
        if (!empty($conf['DriverOpts'])) {
            $ep['DriverOpts'] = $conf['DriverOpts'];
        }
        $endpoints[$name] = $ep;
    }
    return ['EndpointsConfig' => $endpoints];
}
function buildContainerCreateBody(array $inspect) {
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
        'StopTimeout' => isset($cfg['StopTimeout']) ? $cfg['StopTimeout'] : null,
        'Shell' => $cfg['Shell'] ?? null,
        'Healthcheck' => $cfg['Healthcheck'] ?? null,
        'HostConfig' => $hc,
    ];
    $nw = buildNetworkingConfigFromInspect($inspect);
    if ($nw !== []) {
        $body['NetworkingConfig'] = $nw;
    }
    return stripNulls($body);
}
function dockerContainerInspect($id) {
    return callDockerAPI(dockerApi('containers/' . rawurlencode($id) . '/json'));
}
function dockerPullImage($imageRef) {
    list($from, $tag) = splitDockerImageRef($imageRef);
    $q = http_build_query(['fromImage' => $from, 'tag' => $tag], '', '&', PHP_QUERY_RFC3986);
    return callDockerAPI(dockerApi('images/create?' . $q), 'POST', null, 600);
}
function dockerFindHostContainer() {
    global $containers;
    $list = is_array($containers['data'] ?? null) ? $containers['data'] : [];
    foreach ($list as $c) {
        $labels = $c['Labels'] ?? [];
        $name = str_replace('/', '', $c['Names'][0] ?? '');
        if (!empty($labels['io.docker.server'])) return $c['Id'];
        if (stripos($name, 'docker') !== false) return $c['Id'];
    }
    foreach ($list as $c) {
        if (($c['State'] ?? '') === 'running') return $c['Id'];
    }
    return null;
}

function decodeDockerStream($raw) {
    $output = '';
    $offset = 0;
    $len = strlen($raw);
    while ($offset + 8 <= $len) {
        $hdr = substr($raw, $offset, 8);
        $frameLen = unpack('N', substr($hdr, 4, 4))[1];
        $offset += 8;
        $frameLen = min($frameLen, $len - $offset);
        if ($frameLen > 0) $output .= substr($raw, $offset, $frameLen);
        $offset += $frameLen;
    }
    return trim($output);
}

function dockerExecReadOutput($containerId, $command, $timeout = 30) {
    $createResult = callDockerAPI(
        dockerApi('containers/' . rawurlencode($containerId) . '/exec'),
        'POST',
        ['Cmd' => ['sh', '-c', $command], 'AttachStdout' => true, 'AttachStderr' => true, 'Tty' => false],
        $timeout
    );
    if ($createResult['code'] !== 201 || !is_array($createResult['data']) || empty($createResult['data']['Id'])) return null;
    $execId = $createResult['data']['Id'];
    $startResult = callDockerAPI(dockerApi('exec/' . rawurlencode($execId) . '/start'), 'POST', ['Detach' => false, 'Tty' => false], $timeout);
    $raw = is_string($startResult['raw'] ?? null) ? $startResult['raw'] : '';
    if (($startResult['code'] ?? 0) !== 200 || $raw === '') return null;
    return decodeDockerStream($raw);
}

function detectComposeFile($projectName) {
    $hostContainerId = dockerFindHostContainer();
    if ($hostContainerId === null) return [null, t('docker_compose_no_access')];
    $escaped = escapeshellarg($projectName);
    $cmd = 'docker compose -p ' . $escaped . ' config 2>&1; docker-compose -p ' . $escaped . ' config 2>&1';
    $output = dockerExecReadOutput($hostContainerId, $cmd);
    if ($output === null || trim($output) === '') return [null, t('docker_compose_not_found')];
    if (preg_match('/command not found|not a docker command|no such command|is not a docker command/i', $output)) return [null, t('docker_compose_not_found') . ' (CLI)'];
    return [$output, null];
}

function dockerRecreateContainer($id, $imageOverride = null) {
    $insp = dockerContainerInspect($id);
    if ($insp['code'] !== 200 || !is_array($insp['data'])) {
        return 'Error: inspect failed: ' . (is_array($insp['data']) && isset($insp['data']['message']) ? $insp['data']['message'] : 'HTTP ' . $insp['code']);
    }
    $inspect = $insp['data'];
    $name = ltrim($inspect['Name'] ?? '', '/');
    $body = buildContainerCreateBody($inspect);
    if ($imageOverride !== null && $imageOverride !== '') {
        $body['Image'] = $imageOverride;
    }
    $state = $inspect['State']['Status'] ?? '';
    $shouldStart = in_array($state, ['running', 'restarting', 'paused'], true);
    if ($state === 'running' || $state === 'paused' || $state === 'restarting') {
        $st = callDockerAPI(dockerApi('containers/' . rawurlencode($id) . '/stop'), 'POST', null, 120);
        if ($st['code'] !== 204 && $st['code'] !== 304) {
            return 'Error: stop failed: ' . (is_array($st['data']) && isset($st['data']['message']) ? $st['data']['message'] : 'HTTP ' . $st['code']);
        }
    }
    $rm = callDockerAPI(dockerApi('containers/' . rawurlencode($id) . '?force=true'), 'DELETE', null, 120);
    if ($rm['code'] !== 204) {
        return 'Error: remove failed: ' . (is_array($rm['data']) && isset($rm['data']['message']) ? $rm['data']['message'] : 'HTTP ' . $rm['code']);
    }
    $cr = callDockerAPI(dockerApi('containers/create?name=' . rawurlencode($name)), 'POST', $body, 120);
    if ($cr['code'] !== 201 || !is_array($cr['data']) || empty($cr['data']['Id'])) {
        return 'Error: create failed: ' . (is_array($cr['data']) && isset($cr['data']['message']) ? $cr['data']['message'] : 'HTTP ' . $cr['code']);
    }
    $newId = $cr['data']['Id'];
    if ($shouldStart) {
        $st2 = callDockerAPI(dockerApi('containers/' . rawurlencode($newId) . '/start'), 'POST', null, 120);
        if ($st2['code'] !== 204 && $st2['code'] !== 304) {
            return 'Error: start after recreate failed: ' . (is_array($st2['data']) && isset($st2['data']['message']) ? $st2['data']['message'] : 'HTTP ' . $st2['code']);
        }
        return 'Container recreated and started';
    }
    return 'Container recreated (was stopped)';
}
function getDockerEndpointId() {
    return 1;
}
function dockerApi($suffix) {
    return ltrim($suffix, '/');
}
function mainSiteUrl() {
    global $displayDomain;
    return 'https://' . ltrim($displayDomain, '/');
}

function dockerDecodeLogStream($raw) {
    if (!is_string($raw) || $raw === '') {
        return '';
    }
    $out = '';
    $n = strlen($raw);
    $i = 0;
    while ($i + 8 <= $n) {
        $size = (ord($raw[$i + 4]) << 24) | (ord($raw[$i + 5]) << 16) | (ord($raw[$i + 6]) << 8) | ord($raw[$i + 7]);
        if ($size < 0 || $i + 8 + $size > $n) {
            break;
        }
        $out .= substr($raw, $i + 8, $size);
        $i += 8 + $size;
    }
    if ($out === '' && $raw !== '') {
        return $raw;
    }
    return $out;
}
