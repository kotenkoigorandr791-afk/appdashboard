<?php
$parts = public_api_parse_path();
$sub = strtolower((string)($parts[1] ?? ''));
if ($sub === 'utils' && str_starts_with((string)($parts[2] ?? ''), 'expense_ocr_app')) {
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
