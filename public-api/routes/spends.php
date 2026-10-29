<?php
$parts = public_api_parse_path();
$sub = strtolower((string)($parts[1] ?? ''));
if ($sub === 'utils' && str_starts_with((string)($parts[2] ?? ''), 'expense_ocr_app')) {
    require APP_ROOT . '/apps/spends/utils/expense_ocr_app.php';
    exit;
}
// JSON/multipart OCR API for the mobile app: /api/spends/ocr/{start|status|resolve|set_dates|cancel|finalize|stores|active_job}
if ($sub === 'ocr') {
    $_GET['api'] = strtolower((string)($parts[2] ?? ''));
    $_GET['job_id'] = (string)($_GET['job_id'] ?? '');
    require APP_ROOT . '/apps/spends/utils/expense_ocr_app.php';
    exit;
}
require_once APP_ROOT . '/apps/spends/core/db.php';
require_once APP_ROOT . '/apps/spends/core/user_db.php';
require_once APP_ROOT . '/apps/spends/core/auth.php';
require_once APP_ROOT . '/apps/spends/core/subscriptions.php';
require_once APP_ROOT . '/apps/spends/core/goals.php';
require_once APP_ROOT . '/apps/spends/core/tags.php';
require_once APP_ROOT . '/apps/spends/core/locale.php';
require_once APP_ROOT . '/apps/spends/core/user_state.php';
require_once APP_ROOT . '/apps/spends/core/mobile_sync.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($sub === 'version' && $method === 'GET') {
    $metaFile = APP_ROOT . '/downloads/spends-mobile.json';
    if (!is_file($metaFile)) public_api_json(['status' => 'error', 'message' => 'no release available'], 503);
    $meta = json_decode((string)file_get_contents($metaFile), true);
    if (!is_array($meta) || empty($meta['version'])) public_api_json(['status' => 'error', 'message' => 'invalid release metadata'], 500);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode([
        'status' => 'success',
        'available' => true,
        'version_name' => (string)$meta['version'],
        'version_code' => (int)($meta['version_code'] ?? 0),
        'hash' => (string)($meta['hash'] ?? ''),
        'size_bytes' => (int)($meta['size_bytes'] ?? 0),
        'built_at' => (int)($meta['built_at'] ?? 0),
        'apk_path' => '/api/spends/apk',
    ], JSON_UNESCAPED_UNICODE));
}
if ($sub === 'apk' && $method === 'GET') {
    $apkFile = APP_ROOT . '/downloads/spends-mobile.apk';
    if (!is_file($apkFile)) public_api_json(['status' => 'error', 'message' => 'no apk available'], 503);
    $metaFile = APP_ROOT . '/downloads/spends-mobile.json';
    $version = 'unknown';
    if (is_file($metaFile)) {
        $meta = json_decode((string)file_get_contents($metaFile), true);
        if (is_array($meta) && !empty($meta['version'])) $version = (string)$meta['version'];
    }
    header('Content-Type: application/vnd.android.package-archive; charset=binary');
    header('Content-Disposition: attachment; filename="Spends-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$version) . '.apk"');
    header('Content-Length: ' . (string)filesize($apkFile));
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    readfile($apkFile);
    exit;
}
if ($sub === 'profile') {
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(spends_mobile_profile(), JSON_UNESCAPED_UNICODE));
}
if ($sub === 'sync' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(spends_mobile_pull($udb, $userSettings), JSON_UNESCAPED_UNICODE));
}
if ($sub === 'sync' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    exit(json_encode(spends_mobile_push($udb, is_array($payload) ? $payload : []), JSON_UNESCAPED_UNICODE));
}

require APP_ROOT . '/apps/spends/controllers/Handlers.php';
