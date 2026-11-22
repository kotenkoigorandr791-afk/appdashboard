<?php
declare(strict_types=1);
$appRoot = dirname(__DIR__, 2);
require_once $appRoot . '/shell/storage_helper.php';
require_once $appRoot . '/lib/Storage/bootstrap.php';

storage_assert_ready();

$pool = app_storage_root_path();
$fail = false;
$apps = ['cloud', 'films', 'spends', 'steps', 'recipes', 'smart_home', 'map', 'media_viewer'];
$users = ['root@root', 'sample_user@example.com'];

foreach ($apps as $app) {
    $base = appdata_app_base_dir($app);
    printf("%-14s %s\n", $app, $base);
    if (!str_starts_with($base, $pool . '/')) $fail = true;
    foreach ($users as $user) {
        $dir = appdata_user_root_dir($app, $user, 'main', false);
        printf("  %-20s %s\n", $user, $dir);
        if (!str_starts_with($dir, $pool . '/')) $fail = true;
    }
}

printf("UPLOADS_BASE_DIR -> %s\n", appdata_uploads_base_dir());
if (!str_starts_with(appdata_uploads_base_dir(), $pool . '/')) $fail = true;

if ($fail) {
    fwrite(STDERR, "FAIL: one or more resolved paths are outside $pool\n");
    exit(1);
}
echo "OK: all resolved paths are under $pool\n";
