<?php
require_once APP_ROOT . '/lib/duplicati_client.php';
public_api_auth_user(true);
$parts = public_api_parse_path();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$body = public_api_body();
$resource = $parts[1] ?? '';
$id = isset($parts[2]) ? rawurldecode((string)$parts[2]) : '';

$backupAbsPath = static function (string $raw): ?string {
    $s = trim($raw);
    if ($s === '' || str_contains($s, "\0")) return null;
    if (preg_match('/^file:/i', $s)) {
        $u = preg_replace('/^file:\\/\\//i', '', $s);
        $u = preg_replace('/^localhost\\/?/i', '', $u);
        $u = preg_replace('/^\\/+/', '/', $u);
        $decoded = rawurldecode($u);
        return ($decoded !== '' && isset($decoded[0]) && $decoded[0] === '/') ? $decoded : null;
    }
    return (isset($s[0]) && $s[0] === '/') ? rawurldecode($s) : null;
};

if ($resource === 'status' && $method === 'GET') {
    public_api_require_role(1);
    $creds = dupBackupLoadDecrypted();
    if ($creds['host'] === '' || $creds['token'] === '') {
        public_api_json(['ok' => false, 'error' => 'duplicati_not_configured'], 503);
    }
    $backups = duplicati_call('Backups');
    $state = duplicati_call('ServerState');
    public_api_json([
        'ok' => true,
        'configured' => true,
        'host' => $creds['host'],
        'backups' => $backups['data'] ?? null,
        'backups_http' => $backups['code'],
        'server_state' => $state['data'] ?? null,
        'server_state_http' => $state['code'],
    ]);
}

if ($resource === 'run' && $id !== '' && $method === 'POST') {
    public_api_require_role(2);
    $r = duplicati_call('backup/' . rawurlencode($id) . '/run', 'POST');
    if ((int)$r['code'] >= 400) {
        $r = duplicati_call('Backups/' . rawurlencode($id) . '/Run', 'POST');
    }
    if ((int)$r['code'] >= 400) {
        public_api_json(['ok' => false, 'error' => is_array($r['data']) ? ($r['data']['error'] ?? 'run_failed') : 'run_failed', 'http' => $r['code'], 'data' => $r['data']], (int)$r['code'] >= 400 ? (int)$r['code'] : 502);
    }
    public_api_json(['ok' => true, 'id' => $id, 'data' => $r['data']]);
}

if ($resource === 'logs' && $id !== '' && $method === 'GET') {
    public_api_require_role(2);
    $r = duplicati_call('backup/' . rawurlencode($id) . '/log');
    if ((int)$r['code'] >= 400) {
        $r = duplicati_call('Backups/' . rawurlencode($id) . '/Log');
    }
    if ((int)$r['code'] >= 400) {
        $r2 = duplicati_call('backup/' . rawurlencode($id) . '/logs');
        if ((int)$r2['code'] >= 400) {
            $r2 = duplicati_call('Backups/' . rawurlencode($id) . '/Logs');
        }
        if ((int)$r2['code'] >= 400) {
            public_api_json(['ok' => false, 'error' => 'logs_failed', 'http' => $r2['code'], 'data' => $r2['data']], (int)$r2['code'] >= 400 ? (int)$r2['code'] : 502);
        }
        public_api_json(['ok' => true, 'id' => $id, 'logs' => $r2['data']]);
    }
    public_api_json(['ok' => true, 'id' => $id, 'logs' => $r['data']]);
}

if ($resource === 'disk' && $method === 'GET') {
    public_api_require_role(1);
    $path = (string)($_GET['path'] ?? $body['path'] ?? '');
    if ($path === '') public_api_json(['ok' => false, 'error' => 'path_required'], 400);
    $normalizedPath = realpath($path);
    if ($normalizedPath === false) $normalizedPath = $path;
    $driveRoot = '/';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && preg_match('/^([a-zA-Z]):/', $normalizedPath, $m)) {
        $driveRoot = $m[1] . ':\\';
    }
    $total = @disk_total_space($driveRoot);
    $free = @disk_free_space($driveRoot);
    if ($total === false || $free === false) {
        public_api_json(['ok' => false, 'error' => 'disk_space_unavailable', 'path' => $driveRoot], 400);
    }
    $used = $total - $free;
    public_api_json([
        'ok' => true,
        'path' => $driveRoot,
        'requested' => $path,
        'total' => $total,
        'free' => $free,
        'used' => $used,
        'percentUsed' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
    ]);
}

if ($resource === 'host_fs' && $method === 'GET') {
    public_api_require_role(2);
    $pathIn = (string)($_GET['path'] ?? '');
    $onlyFolders = (string)($_GET['onlyFolders'] ?? 'true') !== 'false';
    $showHidden = (string)($_GET['showHidden'] ?? 'false') === 'true';
    $local = $backupAbsPath($pathIn);
    if ($local === null) public_api_json(['ok' => false, 'error' => 'invalid_path'], 400);
    $real = realpath($local);
    if ($real === false || !is_dir($real)) public_api_json(['ok' => false, 'error' => 'not_a_directory', 'path' => $local], 400);
    $dh = @opendir($real);
    if ($dh === false) public_api_json(['ok' => false, 'error' => 'cannot_read_directory'], 500);
    $out = [];
    while (($name = readdir($dh)) !== false) {
        if ($name === '.' || $name === '..') continue;
        if (!$showHidden && isset($name[0]) && $name[0] === '.') continue;
        $full = $real . DIRECTORY_SEPARATOR . $name;
        $isDir = is_dir($full);
        if ($onlyFolders && !$isDir) continue;
        $mtime = @filemtime($full);
        $out[] = [
            'path' => $full,
            'name' => $name,
            'leaf' => !$isDir,
            'mtime' => ($mtime !== false) ? gmdate('Y-m-d\TH:i:s\Z', (int)$mtime) : null,
        ];
    }
    closedir($dh);
    usort($out, static function ($a, $b) {
        if ($a['leaf'] !== $b['leaf']) return $a['leaf'] ? 1 : -1;
        return strcasecmp(basename($a['path']), basename($b['path']));
    });
    public_api_json(['ok' => true, 'path' => $real, 'entries' => $out]);
}

if ($resource === 'host_fs' && $id === 'mkdir' && $method === 'POST') {
    public_api_require_role(2);
    $parentRaw = (string)($body['parent'] ?? '');
    $name = trim((string)($body['name'] ?? ''));
    $parentLocal = $backupAbsPath($parentRaw);
    if ($parentLocal === null || $name === '' || preg_match('/[\/\\\\]/', $name) || $name === '.' || $name === '..') {
        public_api_json(['ok' => false, 'error' => 'invalid_parent_or_name'], 400);
    }
    $parentReal = realpath($parentLocal);
    if ($parentReal === false || !is_dir($parentReal)) public_api_json(['ok' => false, 'error' => 'parent_not_found'], 400);
    $newPath = $parentReal . DIRECTORY_SEPARATOR . $name;
    if (file_exists($newPath)) public_api_json(['ok' => false, 'error' => 'already_exists'], 409);
    if (!@mkdir($newPath, 0775)) public_api_json(['ok' => false, 'error' => 'mkdir_failed'], 500);
    public_api_json(['ok' => true, 'path' => $newPath]);
}

if ($resource === 'path_rw_test' && $method === 'POST') {
    public_api_require_role(2);
    $pathIn = (string)($body['path'] ?? '');
    $local = $backupAbsPath($pathIn);
    if ($local === null) public_api_json(['ok' => false, 'error' => 'invalid_path'], 400);
    $real = realpath($local);
    if ($real === false || !is_dir($real)) public_api_json(['ok' => false, 'error' => 'directory_not_found', 'path' => $local], 400);
    $tmpBase = $real . DIRECTORY_SEPARATOR . '.backup_api_rw_' . bin2hex(random_bytes(8)) . '.tmp';
    $payload = 'backup_api_rw_' . bin2hex(random_bytes(8));
    if (@file_put_contents($tmpBase, $payload, LOCK_EX) === false) {
        public_api_json(['ok' => false, 'write' => false, 'read' => false, 'path' => $real, 'error' => 'write_denied'], 403);
    }
    $got = @file_get_contents($tmpBase);
    @unlink($tmpBase);
    $readOk = ($got === $payload);
    public_api_json(['ok' => $readOk, 'write' => true, 'read' => $readOk, 'path' => $real]);
}

public_api_json(['ok' => false, 'error' => 'unknown_backup_action'], 404);
