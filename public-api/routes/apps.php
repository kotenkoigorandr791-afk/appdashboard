<?php
public_api_auth_user(true);
$parts = public_api_parse_path();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = $parts[1] ?? '';
$body = public_api_body();
if (($action === '' || $action === 'list') && $method === 'GET') {
    $appsDir = APP_ROOT . '/apps/';
    $list = [];
    if (is_dir($appsDir)) {
        foreach (glob($appsDir . '*', GLOB_ONLYDIR) as $subdir) {
            $folder = basename($subdir);
            $nameFile = $subdir . '/name.json';
            if (!is_file($nameFile)) continue;
            if (!userMeetsAppMinRole(public_api_role(), $folder)) continue;
            $nameData = json_decode((string)file_get_contents($nameFile), true);
            if (!is_array($nameData)) $nameData = [];
            $list[] = ['id' => $folder, 'name' => $nameData, 'min_role' => getAppMinRole($folder)];
        }
    }
    public_api_json(['ok' => true, 'apps' => $list]);
}
if ($action === 'access_levels' && $method === 'GET') {
    public_api_require_role(2);
    $levels = loadAppAccessLevels();
    $appsDir = APP_ROOT . '/apps/';
    $out = [];
    if (is_dir($appsDir)) {
        foreach (glob($appsDir . '*', GLOB_ONLYDIR) as $subdir) {
            $folder = basename($subdir);
            if (!is_file($subdir . '/name.json')) continue;
            $out[$folder] = $levels[$folder] ?? 2;
        }
    }
    public_api_json(['ok' => true, 'levels' => $out]);
}
if ($action === 'access_levels' && $method === 'POST') {
    public_api_require_role(2);
    $appId = trim((string)($body['app_id'] ?? $body['app'] ?? ''));
    $minRole = max(0, min(2, (int)($body['min_role'] ?? $body['level'] ?? 2)));
    if ($appId === '') public_api_json(['ok' => false, 'error' => 'missing_app_id'], 400);
    $levels = loadAppAccessLevels();
    $levels[$appId] = $minRole;
    saveAppAccessLevelsFromMap($levels);
    public_api_json(['ok' => true, 'app_id' => $appId, 'min_role' => getAppMinRole($appId)]);
}
public_api_json(['ok' => false, 'error' => 'unknown_apps_action'], 404);
