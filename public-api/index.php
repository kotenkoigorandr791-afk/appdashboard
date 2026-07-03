<?php
define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_API', true);
require_once APP_ROOT . '/lib/public_api.php';
public_api_bootstrap();
$parts = public_api_parse_path();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($parts === [] || ($parts[0] ?? '') === 'index.php') {
    public_api_json(['ok' => true, 'api' => 'public-api/v1', 'auth' => 'Authorization: Bearer <token>, X-Device-Id: <hex>']);
}
$section = $parts[0] ?? '';
if ($section === 'auth') {
    require __DIR__ . '/routes/auth.php';
    exit;
}
if ($section === 'apps' && ($parts[1] ?? '') === '' && $method === 'GET') {
    require __DIR__ . '/routes/apps.php';
    exit;
}
$app = $section;
$allowed = ['cloud','films','map','media_viewer','performance','recipes','smart_home','spends','steps','tft-generator','viewer'];
if (!in_array($app, $allowed, true)) {
    public_api_json(['ok' => false, 'error' => 'unknown_app'], 404);
}
public_api_auth_user(true);
public_api_check_app($app);
$routeFile = __DIR__ . '/routes/' . $app . '.php';
if (!is_file($routeFile)) {
    public_api_json(['ok' => false, 'error' => 'app_not_implemented'], 501);
}
require $routeFile;
