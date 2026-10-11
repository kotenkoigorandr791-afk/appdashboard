<?php
require_once APP_ROOT . '/lib/docker_client.php';
public_api_auth_user(true);
$parts = public_api_parse_path();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$body = public_api_body();
$resource = $parts[1] ?? '';
$id = isset($parts[2]) ? rawurldecode((string)$parts[2]) : '';
$sub = $parts[3] ?? '';

$okCodes = static function (array $result, array $codes): bool {
    return in_array((int)($result['code'] ?? 0), $codes, true);
};
$fail = static function (array $result, int $fallback = 502): void {
    $code = (int)($result['code'] ?? 0);
    public_api_json(['ok' => false, 'error' => docker_err($result), 'http' => $code, 'data' => $result['data'] ?? null], $code >= 400 ? $code : $fallback);
};

if (!function_exists('docker_api_recreate_container')) {
    function docker_api_recreate_container(string $id): array {
        $insp = docker_call('containers/' . rawurlencode($id) . '/json');
        if ((int)($insp['code'] ?? 0) !== 200 || !is_array($insp['data'])) {
            return ['ok' => false, 'error' => 'inspect_failed', 'detail' => docker_err($insp)];
        }
        $inspect = $insp['data'];
        $name = ltrim((string)($inspect['Name'] ?? ''), '/');
        if ($name === '') return ['ok' => false, 'error' => 'missing_name'];
        $createBody = docker_build_create_body($inspect);
        $state = (string)($inspect['State']['Status'] ?? '');
        $shouldStart = in_array($state, ['running', 'restarting', 'paused'], true);
        if (in_array($state, ['running', 'paused', 'restarting'], true)) {
            $st = docker_call('containers/' . rawurlencode($id) . '/stop', 'POST', null, 120);
            if (!in_array((int)$st['code'], [204, 304], true)) {
                return ['ok' => false, 'error' => 'stop_failed', 'detail' => docker_err($st)];
            }
        }
        $oldName = $name . '_old_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $rn = docker_call('containers/' . rawurlencode($id) . '/rename?name=' . rawurlencode($oldName), 'POST', null, 60);
        if (!in_array((int)$rn['code'], [204, 200], true)) {
            $rm = docker_call('containers/' . rawurlencode($id) . '?force=true', 'DELETE', null, 120);
            if ((int)$rm['code'] !== 204) {
                return ['ok' => false, 'error' => 'rename_failed', 'detail' => docker_err($rn)];
            }
            $oldName = '';
        }
        $cr = docker_call('containers/create?name=' . rawurlencode($name), 'POST', $createBody, 120);
        if ((int)$cr['code'] !== 201 || !is_array($cr['data']) || empty($cr['data']['Id'])) {
            if ($oldName !== '') {
                docker_call('containers/' . rawurlencode($id) . '/rename?name=' . rawurlencode($name), 'POST', null, 60);
            }
            return ['ok' => false, 'error' => 'create_failed', 'detail' => docker_err($cr)];
        }
        $newId = (string)$cr['data']['Id'];
        if ($oldName !== '') {
            docker_call('containers/' . rawurlencode($id) . '?force=true', 'DELETE', null, 120);
        }
        if ($shouldStart) {
            $st2 = docker_call('containers/' . rawurlencode($newId) . '/start', 'POST', null, 120);
            if (!in_array((int)$st2['code'], [204, 304], true)) {
                return ['ok' => false, 'error' => 'start_failed', 'new_id' => $newId, 'detail' => docker_err($st2)];
            }
        }
        return ['ok' => true, 'new_id' => $newId, 'name' => $name, 'started' => $shouldStart];
    }
}

if ($resource === 'containers' && $id === '' && $method === 'GET') {
    public_api_require_role(1);
    $r = docker_call('containers/json?all=1');
    if (!$okCodes($r, [200])) $fail($r);
    public_api_json(['ok' => true, 'containers' => $r['data']]);
}

if ($resource === 'containers' && $id !== '' && $sub === 'stats' && $method === 'GET') {
    public_api_require_role(1);
    $r = docker_call('containers/' . rawurlencode($id) . '/stats?stream=false', 'GET', null, 30);
    if (!$okCodes($r, [200])) $fail($r);
    public_api_json(['ok' => true, 'stats' => $r['data']]);
}

if ($resource === 'containers' && $id !== '' && $sub === 'logs' && $method === 'GET') {
    public_api_require_role(1);
    $tail = max(1, min(5000, (int)($_GET['tail'] ?? 200)));
    $r = docker_call('containers/' . rawurlencode($id) . '/logs?stdout=true&stderr=true&tail=' . $tail, 'GET', null, 90);
    if (!$okCodes($r, [200])) $fail($r);
    $raw = is_string($r['raw'] ?? null) ? $r['raw'] : (is_string($r['data'] ?? null) ? $r['data'] : '');
    public_api_json(['ok' => true, 'logs' => $raw, 'raw' => $raw]);
}

if ($resource === 'containers' && $id !== '' && $sub === 'inspect' && $method === 'GET') {
    public_api_require_role(2);
    $r = docker_call('containers/' . rawurlencode($id) . '/json');
    if (!$okCodes($r, [200])) $fail($r);
    public_api_json(['ok' => true, 'inspect' => $r['data']]);
}

if ($resource === 'containers' && $id !== '' && $sub !== '' && $method === 'POST') {
    public_api_require_role(2);
    $op = strtolower($sub);
    if (in_array($op, ['start', 'stop', 'restart', 'pause', 'unpause', 'kill'], true)) {
        $r = docker_call('containers/' . rawurlencode($id) . '/' . $op, 'POST', null, 120);
        $accept = in_array($op, ['start', 'stop'], true) ? [204, 304] : [204];
        if (!$okCodes($r, $accept)) $fail($r);
        public_api_json(['ok' => true, 'action' => $op, 'id' => $id]);
    }
    if ($op === 'remove') {
        $r = docker_call('containers/' . rawurlencode($id) . '?force=true', 'DELETE', null, 120);
        if (!$okCodes($r, [204])) $fail($r);
        public_api_json(['ok' => true, 'action' => 'remove', 'id' => $id]);
    }
    if ($op === 'pull') {
        $insp = docker_call('containers/' . rawurlencode($id) . '/json');
        if (!$okCodes($insp, [200]) || !is_array($insp['data'])) $fail($insp);
        $img = (string)($insp['data']['Config']['Image'] ?? '');
        if ($img === '') public_api_json(['ok' => false, 'error' => 'image_missing'], 400);
        list($from, $tag) = docker_split_image($img);
        $q = http_build_query(['fromImage' => $from, 'tag' => $tag], '', '&', PHP_QUERY_RFC3986);
        $r = docker_call('images/create?' . $q, 'POST', null, 600);
        if (!$okCodes($r, [200])) $fail($r);
        public_api_json(['ok' => true, 'action' => 'pull', 'image' => $img]);
    }
    if ($op === 'update') {
        $insp = docker_call('containers/' . rawurlencode($id) . '/json');
        if (!$okCodes($insp, [200]) || !is_array($insp['data'])) $fail($insp);
        $img = (string)($insp['data']['Config']['Image'] ?? '');
        if ($img === '') public_api_json(['ok' => false, 'error' => 'image_missing'], 400);
        list($from, $tag) = docker_split_image($img);
        $q = http_build_query(['fromImage' => $from, 'tag' => $tag], '', '&', PHP_QUERY_RFC3986);
        $pr = docker_call('images/create?' . $q, 'POST', null, 600);
        if (!$okCodes($pr, [200])) $fail($pr);
        $raw = (string)($pr['raw'] ?? '');
        if ($raw !== '' && (str_contains($raw, '"error"') || str_contains($raw, '"errorDetail"'))) {
            public_api_json(['ok' => false, 'error' => 'image_pull_failed'], 502);
        }
        $recreate = docker_api_recreate_container($id);
        if (!$recreate['ok']) public_api_json($recreate, 502);
        public_api_json(['ok' => true, 'action' => 'update', 'result' => $recreate]);
    }
    if ($op === 'recreate') {
        $recreate = docker_api_recreate_container($id);
        if (!$recreate['ok']) public_api_json($recreate, 502);
        public_api_json(['ok' => true, 'action' => 'recreate', 'result' => $recreate]);
    }
    public_api_json(['ok' => false, 'error' => 'unknown_container_action'], 404);
}

if ($resource === 'images' && $id === 'pull' && $sub === '' && $method === 'POST') {
    public_api_require_role(2);
    $img = trim((string)($body['image'] ?? ''));
    if ($img === '') public_api_json(['ok' => false, 'error' => 'missing_image'], 400);
    list($from, $tag) = docker_split_image($img);
    $q = http_build_query(['fromImage' => $from, 'tag' => $tag], '', '&', PHP_QUERY_RFC3986);
    $r = docker_call('images/create?' . $q, 'POST', null, 600);
    if (!$okCodes($r, [200])) $fail($r);
    public_api_json(['ok' => true, 'image' => $img]);
}

if ($resource === 'images' && $id !== '' && $id !== 'pull' && $sub === '' && $method === 'DELETE') {
    public_api_require_role(2);
    $r = docker_call('images/' . rawurlencode($id) . '?force=true&noprune=false', 'DELETE', null, 120);
    if (!$okCodes($r, [200, 202, 204])) $fail($r);
    public_api_json(['ok' => true]);
}

if ($resource === 'images' && $id !== '' && $sub === 'inspect' && $method === 'GET') {
    public_api_require_role(2);
    $r = docker_call('images/' . rawurlencode($id) . '/json');
    if (!$okCodes($r, [200])) $fail($r);
    public_api_json(['ok' => true, 'inspect' => $r['data']]);
}

if ($resource === 'volumes' && $id !== '' && $method === 'DELETE') {
    public_api_require_role(2);
    $r = docker_call('volumes/' . rawurlencode($id) . '?force=true', 'DELETE', null, 120);
    if (!$okCodes($r, [204])) $fail($r);
    public_api_json(['ok' => true]);
}

if ($resource === 'stacks' && $id === '' && $method === 'GET') {
    public_api_require_role(1);
    $r = docker_call('containers/json?all=1');
    if (!$okCodes($r, [200])) $fail($r);
    $stacks = [];
    foreach (is_array($r['data'] ?? null) ? $r['data'] : [] as $container) {
        if (!is_array($container)) continue;
        $labels = is_array($container['Labels'] ?? null) ? $container['Labels'] : [];
        $name = trim((string)($labels['com.docker.compose.project'] ?? $labels['com.docker.stack.namespace'] ?? ''));
        if ($name === '') continue;
        if (!isset($stacks[$name])) $stacks[$name] = ['Name' => $name, 'Containers' => 0, 'Type' => 2];
        $stacks[$name]['Containers']++;
    }
    public_api_json(['ok' => true, 'stacks' => array_values($stacks)]);
}

if ($resource === 'stacks' && $id !== '' && $sub === 'compose' && $method === 'GET') {
    public_api_require_role(2);
    public_api_json(['ok' => false, 'error' => 'compose_edit_unavailable'], 404);
}

if ($resource === 'stacks' && $id !== '' && $sub === '' && $method === 'POST') {
    public_api_require_role(2);
    public_api_json(['ok' => false, 'error' => 'compose_edit_unavailable'], 404);
}

public_api_json(['ok' => false, 'error' => 'unknown_docker_action'], 404);
