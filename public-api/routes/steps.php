<?php
require_once APP_ROOT . '/apps/steps/core/db_helpers.php';
require_once APP_ROOT . '/apps/steps/core/date_utils.php';
require_once APP_ROOT . '/apps/steps/core/settings.php';
require_once APP_ROOT . '/apps/steps/models/StepsModel.php';
require_once APP_ROOT . '/apps/steps/core/user_helpers.php';
require_once APP_ROOT . '/apps/steps/auth_common.php';
require_once APP_ROOT . '/apps/steps/core/health_connect_ingest.php';

$parts = public_api_parse_path();
$sub = $parts[1] ?? '';

$stepsAppDir = APP_ROOT . '/apps/steps';
$usersDir = $stepsAppDir . '/users';
$safeUserName = get_current_user_folder();
$userDir = $usersDir . '/' . $safeUserName;

checkDirectory($usersDir);
checkDirectory($userDir);

$userDb = getUserDB($userDir);
if (!$userDb) {
    public_api_json(['status' => 'error', 'message' => 'steps db unavailable'], 500);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
header('Content-Type: application/json; charset=utf-8');

if ($method === 'GET') {
    if (isset($_GET['settings']) || isset($_GET['get_settings'])) {
        $settings = loadUserSettings($userDb, $defaultSettings);
        exit(json_encode(['status' => 'success', 'settings' => $settings], JSON_UNESCAPED_UNICODE));
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

if ($method === 'POST') {
    $subLower = strtolower($sub);
    $okSub = $subLower === '' || $subLower === 'ingest' || $subLower === 'webhook' || $subLower === 'sync';
    if (!$okSub) {
        public_api_json(['status' => 'error', 'message' => 'unknown_post_path'], 404);
    }
    $payload = public_api_body();
    if (!is_array($payload)) {
        public_api_json(['status' => 'error', 'message' => 'invalid_json'], 400);
    }
    if (empty($payload['timestamp']) || empty($payload['app_version'])) {
        public_api_json(['status' => 'error', 'message' => 'missing timestamp/app_version'], 400);
    }

    $stored = hc_store_payload_json($userDir, $payload);
    $stepsDaily = $stored['steps_daily'] ?? [];

    if (!empty($stepsDaily)) {
        $batch = [];
        foreach ($stepsDaily as $d => $c) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d)) continue;
            $batch[] = ['date' => $d, 'steps' => (int)$c];
        }
        if (!empty($batch)) {
            upsertStepsBatch($userDb, $batch);
        }
    }

    exit(json_encode([
        'status' => 'success',
        'stored' => [
            'raw_file' => $stored['raw_file'] ?? null,
            'stored_days' => $stored['stored_days'] ?? [],
        ],
        'steps_upserted' => count($stepsDaily),
    ], JSON_UNESCAPED_UNICODE));
}

public_api_json(['status' => 'error', 'message' => 'method_not_allowed'], 405);
