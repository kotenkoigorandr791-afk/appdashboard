<?php
public_api_auth_user(true);
$appsDir = APP_ROOT . '/apps/';
$list = [];
if (is_dir($appsDir)) {
    foreach (glob($appsDir . '*', GLOB_ONLYDIR) as $subdir) {
        $folder = basename($subdir);
        $nameFile = $subdir . '/name.json';
        if (!is_file($nameFile)) continue;
        if (!userMeetsAppMinRole((int)($_SESSION['user']['role'] ?? 0), $folder)) continue;
        $nameData = json_decode((string)file_get_contents($nameFile), true);
        if (!is_array($nameData)) $nameData = [];
        $list[] = ['id' => $folder, 'name' => $nameData];
    }
}
public_api_json(['ok' => true, 'apps' => $list]);
