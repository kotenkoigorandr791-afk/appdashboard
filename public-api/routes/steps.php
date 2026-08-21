<?php
require_once APP_ROOT . '/apps/steps/core/bootstrap.php';
require_once APP_ROOT . '/apps/steps/core/db_helpers.php';
require_once APP_ROOT . '/apps/steps/core/date_utils.php';
require_once APP_ROOT . '/apps/steps/core/settings.php';
require_once APP_ROOT . '/apps/steps/models/StepsModel.php';
require_once APP_ROOT . '/apps/steps/core/user_helpers.php';
require APP_ROOT . '/apps/steps/core/init.php';
require APP_ROOT . '/apps/steps/controllers/VideoBootstrap.php';
require APP_ROOT . '/apps/steps/controllers/PostHandlers.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    if (isset($_GET['settings']) || isset($_GET['get_settings'])) {
        exit(json_encode(['status' => 'success', 'settings' => $userSettings], JSON_UNESCAPED_UNICODE));
    }
    $from = trim((string)($_GET['from'] ?? ''));
    $to = trim((string)($_GET['to'] ?? ''));
    $date = trim((string)($_GET['date'] ?? ''));
    if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        if ($from === '') $from = $date;
        if ($to === '') $to = $date;
    }
    if ($from === '' || $to === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        exit(json_encode(['status' => 'error', 'message' => 'from and to (YYYY-MM-DD) required'], JSON_UNESCAPED_UNICODE));
    }
    if (strcmp($from, $to) > 0) {
        [$from, $to] = [$to, $from];
    }
    $raw = loadStepsRange($userDb, $from, $to);
    $rows = array_values($raw);
    usort($rows, static fn($a, $b) => strcmp((string)$a['date'], (string)$b['date']));
    exit(json_encode([
        'status' => 'success',
        'from' => $from,
        'to' => $to,
        'steps' => $rows,
        'count' => count($rows),
    ], JSON_UNESCAPED_UNICODE));
}
