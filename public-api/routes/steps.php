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
if (!$userDb) public_api_json(['status' => 'error', 'message' => 'steps db unavailable'], 500);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
header('Content-Type: application/json; charset=utf-8');
function steps_conflicts_path(string $userDir): string {
    $dir = rtrim($userDir, '/') . '/volume/health_connect';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir . '/conflicts.json';
}
function steps_conflicts_read(string $userDir): array {
    $path = steps_conflicts_path($userDir);
    if (!is_file($path)) return [];
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}
function steps_conflicts_write(string $userDir, array $conflicts): void {
    hc_json_atomic_write(steps_conflicts_path($userDir), json_encode(array_values($conflicts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function steps_load_table_page(AppDatabase $db, array $payload): array {
    $page = max(1, (int)($payload['page'] ?? 1));
    $filterOp = (string)($payload['filter_op'] ?? '');
    $filterVal = isset($payload['filter_val']) && $payload['filter_val'] !== '' ? (int)$payload['filter_val'] : null;
    $filterValTo = isset($payload['filter_val_to']) && $payload['filter_val_to'] !== '' ? (int)$payload['filter_val_to'] : null;
    $dateFrom = isset($payload['date_from']) && $payload['date_from'] !== '' ? (string)$payload['date_from'] : null;
    $dateTo = isset($payload['date_to']) && $payload['date_to'] !== '' ? (string)$payload['date_to'] : null;
    return loadStepsPage($db, $page, 50, $filterOp, $filterVal, $filterValTo, $dateFrom, $dateTo);
}
function steps_list_backups(string $userDir): array {
    $backupDir = "$userDir/backups";
    $backups = [];
    if (is_dir($backupDir)) {
        foreach (glob("$backupDir/Steps_backup_*.csv") ?: [] as $f) {
            $backups[] = ['name' => basename($f), 'size' => filesize($f), 'date' => date('Y-m-d H:i:s', filemtime($f))];
        }
        usort($backups, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
    }
    return $backups;
}
function steps_create_backup(AppDatabase $db, string $userDir): array {
    checkDirectory($userDir);
    $backupDir = "$userDir/backups";
    checkDirectory($backupDir);
    $csvContent = exportStepsToCSV($db);
    if (empty($csvContent)) return ['status' => 'error', 'message' => 'No steps data'];
    $name = 'Steps_backup_' . date('Y-m-d_H-i-s') . '.csv';
    if (file_put_contents("$backupDir/$name", $csvContent) === false) {
        return ['status' => 'error', 'message' => 'Failed to create backup'];
    }
    $files = glob("$backupDir/Steps_backup_*.csv");
    if (count($files) > 10) {
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
        for ($i = 0; $i < count($files) - 10; $i++) @unlink($files[$i]);
    }
    return ['status' => 'success', 'message' => 'Backup created', 'backup_file' => $name];
}
function steps_conflict_public(array $conflict): array {
    return [
        'id' => (string)($conflict['id'] ?? ''),
        'status' => (string)($conflict['status'] ?? 'pending'),
        'created_at' => (int)($conflict['created_at'] ?? 0),
        'days' => $conflict['days'] ?? [],
        'resolution_url' => (string)($conflict['resolution_url'] ?? ''),
    ];
}
function steps_detect_conflicts(AppDatabase $db, array $stepsDaily): array {
    $dates = array_values(array_filter(array_keys($stepsDaily), static fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d)));
    if (!$dates) return [];
    sort($dates);
    $existing = loadStepsRange($db, $dates[0], $dates[count($dates) - 1]);
    $out = [];
    foreach ($dates as $date) {
        $server = (int)($existing[$date]['steps'] ?? 0);
        $phone = (int)$stepsDaily[$date];
        if ($server > 0 && $phone > 0 && $server !== $phone) $out[] = ['date' => $date, 'server' => $server, 'phone' => $phone];
    }
    return $out;
}
function steps_filter_payload_days(array $payload, array $skipDates): array {
    if (!$skipDates) return $payload;
    $skip = array_fill_keys($skipDates, true);
    foreach ($payload as $key => $value) {
        if (!is_array($value)) continue;
        $payload[$key] = array_values(array_filter($value, static function ($row) use ($skip) {
            if (!is_array($row)) return true;
            $date = hc_extract_record_date($row);
            return $date === null || empty($skip[$date]);
        }));
    }
    return $payload;
}
function steps_create_conflict(string $userDir, array $payload, array $days): array {
    $id = sha1(json_encode($days) . '|' . json_encode($payload) . '|' . microtime(true));
    $conflict = ['id' => $id, 'status' => 'pending', 'created_at' => time(), 'days' => $days, 'payload' => $payload, 'resolution_url' => '/api/steps/conflicts/' . $id];
    $conflicts = array_values(array_filter(steps_conflicts_read($userDir), static fn($c) => ($c['status'] ?? '') === 'pending'));
    $conflicts[] = $conflict;
    steps_conflicts_write($userDir, $conflicts);
    return $conflict;
}
function steps_apply_payload(AppDatabase $db, string $userDir, array $payload, string $action, array $conflictDays): array {
    $storePayload = steps_filter_payload_days($payload, $action === 'skip' ? array_column($conflictDays, 'date') : []);
    $stored = hc_store_payload_json($userDir, $storePayload);
    $stepsDaily = $stored['steps_daily'] ?? [];
    if ($action === 'add') {
        foreach ($conflictDays as $day) {
            $date = (string)$day['date'];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
            $stepsDaily[$date] = (int)$day['server'] + (int)$day['phone'];
            $metrics = hc_read_day_metrics($userDir, $date);
            $metrics['steps'] = array_merge($metrics['steps'] ?? ['samples' => 0], ['total' => $stepsDaily[$date]]);
            $obj = ['date' => $date, 'metrics' => $metrics, 'steps_daily_total' => $stepsDaily[$date]];
            hc_json_atomic_write(hc_health_days_dir($userDir) . '/' . $date . '.json', json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $stored['steps_daily'] = $stepsDaily;
    }
    $batch = [];
    foreach ($stepsDaily as $d => $c) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d)) $batch[] = ['date' => $d, 'steps' => (int)$c];
    }
    if ($batch) upsertStepsBatch($db, $batch);
    return ['stored' => $stored, 'steps_upserted' => count($batch)];
}
if ($method === 'GET') {
    if (strtolower((string)$sub) === 'version') {
        $metaFile = APP_ROOT . '/downloads/stepmonitor.json';
        if (!is_file($metaFile)) public_api_json(['status' => 'error', 'message' => 'no release available'], 503);
        $meta = json_decode((string)file_get_contents($metaFile), true);
        if (!is_array($meta) || empty($meta['version'])) public_api_json(['status' => 'error', 'message' => 'invalid release metadata'], 500);
        exit(json_encode([
            'status' => 'success',
            'available' => true,
            'version_name' => (string)$meta['version'],
            'version_code' => (int)($meta['version_code'] ?? 0),
            'hash' => (string)($meta['hash'] ?? ''),
            'size_bytes' => (int)($meta['size_bytes'] ?? 0),
            'built_at' => (int)($meta['built_at'] ?? 0),
            'apk_path' => '/api/steps/apk',
        ], JSON_UNESCAPED_UNICODE));
    }
    if (strtolower((string)$sub) === 'apk') {
        $apkFile = APP_ROOT . '/downloads/stepmonitor.apk';
        if (!is_file($apkFile)) public_api_json(['status' => 'error', 'message' => 'no apk available'], 503);
        $metaFile = APP_ROOT . '/downloads/stepmonitor.json';
        $version = 'unknown';
        if (is_file($metaFile)) {
            $meta = json_decode((string)file_get_contents($metaFile), true);
            if (is_array($meta) && !empty($meta['version'])) $version = (string)$meta['version'];
        }
        header('Content-Type: application/vnd.android.package-archive; charset=binary');
        header('Content-Disposition: attachment; filename="stepmonitor-' . $version . '.apk"');
        header('Content-Length: ' . (string)filesize($apkFile));
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        readfile($apkFile);
        exit;
    }
    if (strtolower((string)$sub) === 'profile') {
        $email = get_current_user_email();
        $profile = ['email' => $email ?: '', 'name' => $email ?: 'StepMonitor', 'photo' => ''];
        if ($email && function_exists('app_main_db')) {
            try {
                $mainDb = app_main_db();
                $stmt = $mainDb->prepare('SELECT name, photo FROM users WHERE lower(email) = :email LIMIT 1');
                $stmt->bindValue(':email', strtolower($email), PDO::PARAM_STR);
                $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $profile['name'] = (string)($row['name'] ?: $profile['name']);
                    $profile['photo'] = function_exists('app_avatar_src') ? app_avatar_src($row['photo'] ?? '') : (string)($row['photo'] ?? '');
                }
            } catch (Throwable $ignored) {
            }
        }
        exit(json_encode(['status' => 'success', 'profile' => $profile], JSON_UNESCAPED_UNICODE));
    }
    if (strtolower((string)$sub) === 'conflicts') {
        $pending = array_values(array_filter(steps_conflicts_read($userDir), static fn($c) => ($c['status'] ?? '') === 'pending'));
        exit(json_encode(['status' => 'success', 'conflicts' => array_map('steps_conflict_public', $pending)], JSON_UNESCAPED_UNICODE));
    }
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
    if (strcmp($from, $to) > 0) [$from, $to] = [$to, $from];
    if (isset($_GET['metrics'])) {
        $metrics = hc_read_metrics_range($userDir, $from, $to);
        // Index by date for fast lookup
        $metricsIndex = [];
        foreach ($metrics as $entry) { $metricsIndex[(string)$entry['date']] = $entry; }
        // Load SQLite steps for the full range to fill gaps in metric files
        $sqliteRows = loadStepsRange($userDb, $from, $to);
        $combined = [];
        $cd = new DateTime($from);
        $end = new DateTime($to);
        while ($cd <= $end) {
            $ds = $cd->format('Y-m-d');
            $sqliteSteps = (int)($sqliteRows[$ds]['steps'] ?? 0);
            if (isset($metricsIndex[$ds])) {
                $entry = $metricsIndex[$ds];
                // Prefer SQLite daily total for steps consistency with web chart
                if ($sqliteSteps > 0 && empty($entry['metrics']['steps']['total'])) {
                    $entry['metrics']['steps'] = array_merge($entry['metrics']['steps'] ?? ['samples' => 0], ['total' => $sqliteSteps]);
                }
                $combined[] = $entry;
            } elseif ($sqliteSteps > 0) {
                $combined[] = ['date' => $ds, 'metrics' => ['steps' => ['total' => $sqliteSteps, 'samples' => 0]]];
            }
            $cd->modify('+1 day');
        }
        exit(json_encode(['status' => 'success', 'from' => $from, 'to' => $to, 'metrics' => $combined, 'count' => count($combined)], JSON_UNESCAPED_UNICODE));
    }
    $rows = array_values(loadStepsRange($userDb, $from, $to));
    usort($rows, static fn($a, $b) => strcmp((string)$a['date'], (string)$b['date']));
    exit(json_encode(['status' => 'success', 'from' => $from, 'to' => $to, 'steps' => $rows, 'count' => count($rows)], JSON_UNESCAPED_UNICODE));
}
if ($method === 'POST') {
    $contentLen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLen > 0 && $contentLen > 8 * 1024 * 1024) public_api_json(['status' => 'error', 'message' => 'payload too large'], 413);
    $subLower = strtolower((string)$sub);
    if (!in_array($subLower, ['', 'ingest', 'webhook', 'sync', 'conflicts'], true)) public_api_json(['status' => 'error', 'message' => 'unknown_post_path'], 404);
    // Accept both JSON (Health Connect app) and form-encoded (web UI / MCP) bodies.
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    $isFormPost = str_contains($contentType, 'application/x-www-form-urlencoded');
    $payload = $isFormPost ? public_api_form_body() : public_api_body();
    if (!is_array($payload)) public_api_json(['status' => 'error', 'message' => 'invalid_json'], 400);
    // Form-encoded table page / backups actions (mirror apps/steps PostHandlers) for MCP clients.
    if ($isFormPost) {
        if (isset($payload['get_table_page'])) {
            $result = steps_load_table_page($userDb, $payload);
            exit(json_encode(['status' => 'success'] + $result, JSON_UNESCAPED_UNICODE));
        }
        if (isset($payload['get_backups_list'])) {
            exit(json_encode(['status' => 'success', 'backups' => steps_list_backups($userDir)], JSON_UNESCAPED_UNICODE));
        }
        if (isset($payload['create_backup'])) {
            exit(json_encode(steps_create_backup($userDb, $userDir), JSON_UNESCAPED_UNICODE));
        }
    }
    if ($subLower === 'conflicts') {
        $id = (string)($parts[2] ?? ($payload['id'] ?? ''));
        $action = (string)($payload['action'] ?? '');
        if ($id === '' || !in_array($action, ['skip', 'replace', 'add'], true)) public_api_json(['status' => 'error', 'message' => 'invalid_conflict_resolution'], 400);
        $conflicts = steps_conflicts_read($userDir);
        foreach ($conflicts as $idx => $conflict) {
            if (($conflict['id'] ?? '') !== $id) continue;
            if (($conflict['status'] ?? '') !== 'pending') exit(json_encode(['status' => 'success', 'applied' => false, 'message' => 'already_resolved'], JSON_UNESCAPED_UNICODE));
            $result = steps_apply_payload($userDb, $userDir, $conflict['payload'] ?? [], $action, $conflict['days'] ?? []);
            $conflicts[$idx]['status'] = 'resolved';
            $conflicts[$idx]['resolved_at'] = time();
            $conflicts[$idx]['resolution'] = $action;
            steps_conflicts_write($userDir, $conflicts);
            exit(json_encode(['status' => 'success', 'applied' => true, 'resolution' => $action, 'result' => $result], JSON_UNESCAPED_UNICODE));
        }
        public_api_json(['status' => 'error', 'message' => 'conflict_not_found'], 404);
    }
    if (empty($payload['timestamp']) || empty($payload['app_version'])) public_api_json(['status' => 'error', 'message' => 'missing timestamp/app_version'], 400);
    $stepsDaily = hc_payload_extract_steps_daily($payload);
    $conflictDays = steps_detect_conflicts($userDb, $stepsDaily);
    if ($conflictDays) {
        $conflict = steps_create_conflict($userDir, $payload, $conflictDays);
        http_response_code(409);
        exit(json_encode(['status' => 'conflict', 'conflict' => steps_conflict_public($conflict)], JSON_UNESCAPED_UNICODE));
    }
    $result = steps_apply_payload($userDb, $userDir, $payload, 'replace', []);
    exit(json_encode(['status' => 'success', 'stored' => ['stored_days' => $result['stored']['stored_days'] ?? []], 'steps_upserted' => $result['steps_upserted'] ?? 0], JSON_UNESCAPED_UNICODE));
}
public_api_json(['status' => 'error', 'message' => 'method_not_allowed'], 405);
