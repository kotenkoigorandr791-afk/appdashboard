<?php
$mcpPath = preg_replace('#^/mcp/?#', '', $path) ?? '';
$mcpPath = trim((string)$mcpPath, '/');
$downloadsDir = dirname(__DIR__) . '/downloads';
$metaFile = $downloadsDir . '/appdashboard-mcp.json';
$tgzFile = $downloadsDir . '/appdashboard-mcp.tgz';

$forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$cfVisitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
$scheme = 'http';
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $forwardedProto === 'https'
    || str_contains($cfVisitor, '"scheme":"https"')
    || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')) {
    $scheme = 'https';
}
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$baseUrl = $scheme . '://' . $host;
$meta = is_file($metaFile) ? json_decode((string)file_get_contents($metaFile), true) : null;
if (!is_array($meta)) $meta = [];
$version = (string)($meta['version'] ?? '1.0.0');
$ready = is_file($tgzFile) && filesize($tgzFile) > 0;
$cacheKey = $ready ? substr((string)($meta['sha256'] ?? ''), 0, 12) : $version;
$packageUrl = $baseUrl . '/mcp/download?v=' . rawurlencode($cacheKey);

if ($mcpPath === '' || $mcpPath === 'info') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $agents = ['cursor', 'claude', 'vscode', 'opencode'];
    $commands = [];
    $removeCommands = [];
    foreach ($agents as $agent) {
        $commands[$agent] = 'npx -y ' . $packageUrl . ' setup --agent ' . $agent . ' --url ' . $baseUrl . ' --package ' . $packageUrl;
        $removeCommands[$agent] = 'npx -y ' . $packageUrl . ' setup --agent ' . $agent . ' --remove';
    }
    echo json_encode([
        'ok' => true,
        'ready' => $ready,
        'name' => 'appdashboard-mcp',
        'version' => $version,
        'base_url' => $baseUrl,
        'package_url' => $packageUrl,
        'size' => $ready ? filesize($tgzFile) : 0,
        'sha256' => $meta['sha256'] ?? null,
        'built_at' => $meta['built_at'] ?? null,
        'requirement' => 'Node.js 18+',
        'commands' => $commands,
        'remove_commands' => $removeCommands,
        'mcp_entry' => [
            'command' => 'npx',
            'args' => ['-y', $packageUrl],
            'env' => ['APPDASHBOARD_URL' => $baseUrl],
        ],
        'opencode_mcp_entry' => [
            'enabled' => true,
            'type' => 'local',
            'command' => ['npx', '-y', $packageUrl],
            'environment' => ['APPDASHBOARD_URL' => $baseUrl],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($mcpPath === 'appdashboard-mcp.tgz' || $mcpPath === 'download') {
    if (!$ready) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'package_not_built']);
        exit;
    }
    header('Content-Type: application/gzip');
    header('Content-Length: ' . filesize($tgzFile));
    header('Content-Disposition: attachment; filename="appdashboard-mcp-' . preg_replace('/[^0-9.]+/', '', $version) . '.tgz"');
    header('Cache-Control: public, max-age=300');
    readfile($tgzFile);
    exit;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'not_found']);
