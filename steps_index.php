<?php
require_once __DIR__ . '/smart_log.php';
smart_log_bootstrap('steps');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ╨С╤Г╤Д╨╡╤А╨╕╨╖╨░╤Ж╨╕╤П тАФ ╤З╤В╨╛╨▒╤Л POST-╨╛╨▒╤А╨░╨▒╨╛╤В╤З╨╕╨║╨╕ ╨╝╨╛╨│╨╗╨╕ ╤Б╨┤╨╡╨╗╨░╤В╤М exit() ╤Б JSON ╨┤╨╛ ╨▓╤Л╨▓╨╛╨┤╨░ HTML
ob_start();
require_once __DIR__ . '/apps/steps/auth_common.php';
require_once __DIR__ . '/apps/steps/core/db_helpers.php';
require_once __DIR__ . '/video_job_sqlite.php';
require_once __DIR__ . '/utils/google_fit_takeout/fit_takeout_state.php';
// тФАтФАтФА ╨Ю╨▒╤Й╨░╤П ╨▒╨░╨╖╨░ ╨┤╨░╨╜╨╜╤Л╤Е (╨░╨▓╤В╨╛╤А╨╕╨╖╨░╤Ж╨╕╤П, ╤Б╨╡╤Б╤Б╨╕╨╕) тФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФА
const UPLOAD_SESSIONS_DIR = __DIR__ . '/upload_sessions';
const TABLE_PAGE_SIZE     = 50; // ╤Б╤В╤А╨╛╨║ ╨╜╨░ ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Г ╨▓ excel-╤В╨░╨▒╨╗╨╕╤Ж╨╡

@mkdir(UPLOAD_SESSIONS_DIR, 0775, true);

// ─── Пользовательская база данных — apps/steps/core/db_helpers.php ───

// ─── Утилиты дат ───
// dd-mm-yy тЖТ YYYY-MM-DD
function csvDateToISO(string $raw): ?string {
    $parts = explode('-', trim($raw));
    if (count($parts) !== 3) return null;
    [$d, $m, $y] = $parts;
    if (!is_numeric($d) || !is_numeric($m) || !is_numeric($y)) return null;
    $fullYear = (int)$y < 70 ? "20$y" : "19$y";
    return "$fullYear-$m-$d";
}

// YYYY-MM-DD тЖТ dd-mm-yy
function isoToCSVDate(string $iso): string {
    return (new DateTime($iso))->format('d-m-y');
}

// тФАтФАтФА ╨Э╨░╤Б╤В╤А╨╛╨╣╨║╨╕ тФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФА
$defaultSettings = [
    'goal_steps'    => 10000,
    'height'        => 170,
    'weight'        => 70,
    'stride_length' => 70,
    'birth_date'    => '1994-01-01',
];

function loadUserSettings(AppDatabase $db, array $defaults): array {
    $settings = $defaults;
    try {
        $result = $db->query('SELECT key, value FROM settings');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
    } catch (Exception $e) {
        error_log("Load settings failed: " . $e->getMessage());
    }
    $settings['goal_steps']    = (int)($settings['goal_steps']    ?? $defaults['goal_steps']);
    $settings['height']        = (int)($settings['height']        ?? $defaults['height']);
    $settings['weight']        = (int)($settings['weight']        ?? $defaults['weight']);
    $settings['stride_length'] = (int)($settings['stride_length'] ?? $defaults['stride_length']);
    $settings['birth_date']    = $settings['birth_date'] ?? $defaults['birth_date'];
    try {
        $bd = new DateTime($settings['birth_date']);
        $settings['age'] = (new DateTime())->diff($bd)->y;
    } catch (Exception $e) {
        $settings['age'] = 30;
    }
    return $settings;
}

function saveUserSetting(AppDatabase $db, string $key, string $value): void {
    $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)');
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':v', $value, SQLITE3_TEXT);
    $stmt->execute();
}

// тФАтФАтФА ╨и╨░╨│╨╕ тАФ ╨▒╨░╨╖╨╛╨▓╤Л╨╡ ╨╛╨┐╨╡╤А╨░╤Ж╨╕╨╕ тФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФА
function upsertStep(AppDatabase $db, string $date, int $steps): void {
    $stmt = $db->prepare('INSERT OR REPLACE INTO steps (date, steps) VALUES (:d, :s)');
    $stmt->bindValue(':d', $date, SQLITE3_TEXT);
    $stmt->bindValue(':s', $steps, SQLITE3_INTEGER);
    $stmt->execute();
}
function normalizeStoredDate(string $raw): ?string {
    $s = trim($raw);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        if (!checkdate($mo, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{2})$/', $s, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $yy = (int)$m[3];
        $y = $yy < 70 ? (2000 + $yy) : (1900 + $yy);
        if (!checkdate($mo, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $s, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $y = (int)$m[3];
        if (!checkdate($mo, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    return null;
}
function normalizeStepsDatesTable(AppDatabase $db): void {
    $rows = [];
    try {
        $res = $db->query('SELECT date, steps FROM steps');
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            $iso = normalizeStoredDate((string)($r['date'] ?? ''));
            if (!$iso) continue;
            $steps = (int)($r['steps'] ?? 0);
            if (!isset($rows[$iso]) || $steps > $rows[$iso]) $rows[$iso] = $steps;
        }
    } catch (Exception $e) {
        return;
    }
    try {
        $db->exec('BEGIN IMMEDIATE TRANSACTION');
        $db->exec('DELETE FROM steps');
        $st = $db->prepare('INSERT INTO steps (date, steps) VALUES (:d, :s)');
        foreach ($rows as $d => $s) {
            $st->bindValue(':d', $d, SQLITE3_TEXT);
            $st->bindValue(':s', $s, SQLITE3_INTEGER);
            $st->execute();
        }
        $db->exec('COMMIT');
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
    }
}

// ╨Ч╨░╨│╤А╤Г╨╖╨║╨░ ╨в╨Ю╨Ы╨м╨Ъ╨Ю ╨╜╤Г╨╢╨╜╨╛╨│╨╛ ╨┤╨╕╨░╨┐╨░╨╖╨╛╨╜╨░ ╨┤╨░╤В (╨┤╨╗╤П ╨│╤А╨░╤Д╨╕╨║╨░/╨║╨░╨╗╨╡╨╜╨┤╨░╤А╤П)
function loadStepsRange(AppDatabase $db, string $from, string $to): array {
    $steps = [];
    try {
        $stmt = $db->prepare(
            'SELECT date, steps FROM steps WHERE date >= :from AND date <= :to ORDER BY date ASC'
        );
        $stmt->bindValue(':from', $from, SQLITE3_TEXT);
        $stmt->bindValue(':to',   $to,   SQLITE3_TEXT);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $steps[$row['date']] = ['date' => $row['date'], 'steps' => (int)$row['steps']];
        }
    } catch (Exception $e) {
        error_log("loadStepsRange failed: " . $e->getMessage());
    }
    return $steps;
}

// ╨Ь╨╕╨╜╨╕╨╝╨░╨╗╤М╨╜╨░╤П ╨╕ ╨╝╨░╨║╤Б╨╕╨╝╨░╨╗╤М╨╜╨░╤П ╨┤╨░╤В╨░ ╨▓ ╨▒╨░╨╖╨╡
function getStepsDateBounds(AppDatabase $db): array {
    try {
        $minDate = $db->querySingle('SELECT MIN(date) FROM steps');
        $maxDate = $db->querySingle('SELECT MAX(date) FROM steps');
        return [$minDate ?: null, $maxDate ?: null];
    } catch (Exception $e) {
        return [null, null];
    }
}

// ╨Ч╨░╨│╤А╤Г╨╖╨║╨░ ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Л ╤В╨░╨▒╨╗╨╕╤Ж╤Л ╤Б SQL-╤Д╨╕╨╗╤М╤В╤А╨╛╨╝
function loadStepsPage(AppDatabase $db, int $page, int $pageSize, string $filterOp, ?int $filterVal, ?int $filterValTo, ?string $dateFrom, ?string $dateTo): array {
    $offset = ($page - 1) * $pageSize;

    // ╨б╤В╤А╨╛╨╕╨╝ WHERE
    [$where, $params] = buildStepsWhere($filterOp, $filterVal, $filterValTo, $dateFrom, $dateTo);

    try {
        // ╨Ю╨▒╤Й╨╡╨╡ ╨║╨╛╨╗-╨▓╨╛
        $countSql = "SELECT COUNT(*) FROM steps" . ($where ? " WHERE $where" : '');
        $countStmt = $db->prepare($countSql);
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v[0], $v[1]);
        $total = (int)$countStmt->execute()->fetchArray(SQLITE3_NUM)[0];

        // ╨б╤В╤А╨░╨╜╨╕╤Ж╨░
        $sql = "SELECT date, steps FROM steps" . ($where ? " WHERE $where" : '') . " ORDER BY date DESC LIMIT :lim OFFSET :off";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v[0], $v[1]);
        $stmt->bindValue(':lim', $pageSize, SQLITE3_INTEGER);
        $stmt->bindValue(':off', $offset,   SQLITE3_INTEGER);
        $result = $stmt->execute();

        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = ['date' => $row['date'], 'steps' => (int)$row['steps']];
        }

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    } catch (Exception $e) {
        error_log("loadStepsPage failed: " . $e->getMessage());
        return ['rows' => [], 'total' => 0, 'page' => $page, 'page_size' => $pageSize];
    }
}

function buildStepsWhere(string $op, ?int $val, ?int $valTo, ?string $dateFrom, ?string $dateTo): array {
    $parts = [];
    $params = [];
    $safeDateFrom = (is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) ? $dateFrom : null;
    $safeDateTo = (is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) ? $dateTo : null;
    if ($safeDateFrom !== null) {
        $parts[] = 'date >= :df';
        $params[':df'] = [$safeDateFrom, SQLITE3_TEXT];
    }
    if ($safeDateTo !== null) {
        $parts[] = 'date <= :dt';
        $params[':dt'] = [$safeDateTo, SQLITE3_TEXT];
    }
    if ($val === null) return [implode(' AND ', $parts), $params];
    switch ($op) {
        case '=':  $w = 'steps = :v1';                   $params[':v1'] = [$val, SQLITE3_INTEGER]; break;
        case '>':  $w = 'steps > :v1';                   $params[':v1'] = [$val, SQLITE3_INTEGER]; break;
        case '<':  $w = 'steps < :v1';                   $params[':v1'] = [$val, SQLITE3_INTEGER]; break;
        case '>=': $w = 'steps >= :v1';                  $params[':v1'] = [$val, SQLITE3_INTEGER]; break;
        case '<=': $w = 'steps <= :v1';                  $params[':v1'] = [$val, SQLITE3_INTEGER]; break;
        case 'range':
            if ($valTo !== null) {
                $w = 'steps >= :v1 AND steps <= :v2';
                $params[':v1'] = [$val, SQLITE3_INTEGER];
                $params[':v2'] = [$valTo, SQLITE3_INTEGER];
            } else {
                $w = 'steps >= :v1';
                $params[':v1'] = [$val, SQLITE3_INTEGER];
            }
            break;
        default: return [implode(' AND ', $parts), $params];
    }
    $parts[] = $w;
    return [implode(' AND ', $parts), $params];
}

// ╨н╨║╤Б╨┐╨╛╤А╤В ╨▓╤Б╨╡╤Е ╤И╨░╨│╨╛╨▓ ╨▓ CSV (╨┤╨╗╤П ╨▒╤Н╨║╨░╨┐╨╛╨▓)
function exportStepsToCSV(AppDatabase $db): string {
    $out = '';
    try {
        $result = $db->query('SELECT date, steps FROM steps ORDER BY date ASC');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $out .= isoToCSVDate($row['date']) . ',' . $row['steps'] . "\n";
        }
    } catch (Exception $e) {
        error_log("exportStepsToCSV failed: " . $e->getMessage());
    }
    return $out;
}

// ╨Ш╨╝╨┐╨╛╤А╤В CSV-╨║╨╛╨╜╤В╨╡╨╜╤В╨░ ╨▓ ╨▒╨░╨╖╤Г
function importStepsFromCSVContent(AppDatabase $db, string $csvContent, bool $replace = true): array {
    $imported = [];
    $lines = explode("\n", $csvContent);
    $db->exec('BEGIN TRANSACTION');
    try {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $row = str_getcsv($line);
            if (count($row) < 2) continue;
            $rawDate = trim($row[0]);
            if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $rawDate)) continue;
            $isoDate = csvDateToISO($rawDate);
            if (!$isoDate) continue;
            $steps = (int)$row[1];
            $sql   = $replace
                ? 'INSERT OR REPLACE INTO steps (date, steps) VALUES (:d, :s)'
                : 'INSERT OR IGNORE INTO steps  (date, steps) VALUES (:d, :s)';
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':d', $isoDate, SQLITE3_TEXT);
            $stmt->bindValue(':s', $steps,   SQLITE3_INTEGER);
            $stmt->execute();
            $imported[$isoDate] = $steps;
        }
        $db->exec('COMMIT');
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        error_log("importStepsFromCSVContent failed: " . $e->getMessage());
    }
    return $imported;
}

function steps_delete_tree(string $dir): bool {
    if (!is_dir($dir)) {
        return true;
    }
    $dir = realpath($dir);
    if ($dir === false) {
        return false;
    }
    foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
        $p = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($p)) {
            steps_delete_tree($p);
        } else {
            @unlink($p);
        }
    }
    return @rmdir($dir);
}

// тФАтФАтФА ╨д╨╛╤В╨╛/╤А╨╛╨╗╤М (╨╕╤Б╨┐╨╛╨╗╤М╨╖╤Г╨╡╤В╤Б╤П get_current_user_email ╨╕╨╖ auth_common) тФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФА
function getUserPhoto(string $email): ?string {
    $db = getDB();
    if (!$db) return null;
    try {
        $stmt = $db->prepare('SELECT photo FROM users WHERE email = :e');
        $stmt->bindValue(':e', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row && !empty($row['photo'])) return $row['photo'];
    } catch (Exception $e) { }
    return null;
}

function getUserRoleByEmail(string $email): int {
    $db = getDB();
    if (!$db) return 0;
    try {
        $stmt = $db->prepare('SELECT role FROM users WHERE email = :e');
        $stmt->bindValue(':e', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['role'])) return (int)$row['role'];
    } catch (Exception $e) { }
    return 0;
}

function isUserRoot(): bool {
    $db = getDB();
    if (!$db) return false;
    $ids = array_unique(array_filter([session_id(), $_COOKIE['PHPSESSID'] ?? '']));
    foreach ($ids as $sid) {
        try {
            $stmt = $db->prepare(
                'SELECT u.role FROM active_sessions s JOIN users u ON s.email = u.email WHERE s.session_id = :sid'
            );
            $stmt->bindValue(':sid', $sid, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['role'])) return $row['role'] === 2;
        } catch (Exception $e) { }
    }
    return false;
}

function logError(string $msg): void { error_log("[StepsApp] $msg"); }

function checkDirectory(string $dir): void {
    if (!file_exists($dir) && !mkdir($dir, 0755, true)) {
        logError("Failed to create directory $dir");
        header('Content-Type: application/json');
        exit(json_encode(['status' => 'error', 'message' => 'Failed to create directory']));
    }
    if (!is_writable($dir)) {
        logError("Directory not writable: $dir");
        header('Content-Type: application/json');
        exit(json_encode(['status' => 'error', 'message' => 'Directory is not writable']));
    }
}

// тФАтФАтФА ╨Ш╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П тФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФА
require_once __DIR__ . '/project/i18n_helpers.php';
$language     = ap_normalize_i18n_lang($_GET['lang'] ?? 'en');
$theme        = $_GET['theme'] ?? 'light';
$color        = $_GET['color'] ?? '#007bff';
$translations = ap_load_i18n_from_dir(__DIR__ . '/apps/steps', $language);

$userEmail       = get_current_user_email();
$safeUserName    = get_current_user_folder();
$currentUser     = $safeUserName;
$userName        = $userEmail ? explode('@', $userEmail)[0] : 'default_user';
$userPhotoFromDB = $userEmail ? getUserPhoto($userEmail) : null;

$usersDir = __DIR__ . '/users';
$userDir  = "$usersDir/$safeUserName";
checkDirectory($usersDir);
checkDirectory($userDir);

// ╨д╨╛╤В╨╛ ╨┐╤А╨╛╤Д╨╕╨╗╤П
$profilePhoto = 'default_profile.jpg';
if (!empty($userPhotoFromDB)) {
    $photoPath = "$userDir/profile.jpg";
    if (!file_exists($photoPath) || filemtime($photoPath) < time() - 3600) {
        if (filter_var($userPhotoFromDB, FILTER_VALIDATE_URL)) {
            $c = @file_get_contents($userPhotoFromDB);
            if ($c !== false) file_put_contents($photoPath, $c);
        } elseif (file_exists($userPhotoFromDB)) {
            @copy($userPhotoFromDB, $photoPath);
        }
    }
    if (file_exists($photoPath) && filesize($photoPath) > 0) {
        $profilePhoto = "users/$safeUserName/profile.jpg";
    }
}

// ╨Ю╤В╨║╤А╤Л╨▓╨░╨╡╨╝ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤М╤Б╨║╤Г╤О ╨С╨Ф
$userDb = getUserDB($userDir);
normalizeStepsDatesTable($userDb);

// ╨Ч╨░╨│╤А╤Г╨╢╨░╨╡╨╝ ╨╜╨░╤Б╤В╤А╨╛╨╣╨║╨╕
$userSettings = loadUserSettings($userDb, $defaultSettings);

// тФАтФАтФА ╨Ш╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨╕╤А╤Г╨╡╨╝ ╤И╨░╨│╨╕: ╤В╨╛╨╗╤М╨║╨╛ ╨┐╨╛╤Б╨╗╨╡╨┤╨╜╨╕╨╡ 12 ╨╝╨╡╤Б╤П╤Ж╨╡╨▓ ╨┤╨╗╤П ╨╜╨░╤З╨░╨╗╤М╨╜╨╛╨│╨╛ ╤А╨╡╨╜╨┤╨╡╤А╨░ тФА
$today        = (new DateTime())->format('Y-m-d');
$yearAgo      = (new DateTime())->modify('-12 months')->format('Y-m-d');

// ╨Х╤Б╨╗╨╕ ╨С╨Ф ╨┐╤Г╤Б╤В╨░ тАФ ╨╖╨░╨┐╨╛╨╗╨╜╤П╨╡╨╝ ╨╝╨╡╤Б╤П╤Ж╨╡╨╝ ╨╜╤Г╨╗╨╡╨╣
$totalCount = (int)$userDb->querySingle('SELECT COUNT(*) FROM steps');
if ($totalCount === 0) {
    $sd = (new DateTime())->modify('-1 month');
    $ed = new DateTime();
    $userDb->exec('BEGIN TRANSACTION');
    while ($sd <= $ed) {
        upsertStep($userDb, $sd->format('Y-m-d'), 0);
        $sd->modify('+1 day');
    }
    $userDb->exec('COMMIT');
}

// ╨г╨▒╨╡╨┤╨╕╨╝╤Б╤П, ╤З╤В╨╛ ╤Б╨╡╨│╨╛╨┤╨╜╤П╤И╨╜╨╕╨╣ ╨┤╨╡╨╜╤М ╨╡╤Б╤В╤М ╨▓ ╨▒╨░╨╖╨╡
$userDb->exec("INSERT OR IGNORE INTO steps (date, steps) VALUES ('$today', 0)");

// ╨Ю╨┐╤А╨╡╨┤╨╡╨╗╤П╨╡╨╝ ╤Б╨░╨╝╤Г╤О ╤А╨░╨╜╨╜╤О╤О ╨┤╨░╤В╤Г
[$earliestInDB, $latestInDB] = getStepsDateBounds($userDb);
$earliestDate = $earliestInDB ?? $today;

// ╨Ч╨░╨│╤А╤Г╨╢╨░╨╡╨╝ ╨▓╨╡╤Б╤М ╨┤╨╛╤Б╤В╤Г╨┐╨╜╤Л╨╣ ╨┤╨╕╨░╨┐╨░╨╖╨╛╨╜ ╨┤╨░╤В ╨┤╨╗╤П JS
$jsRangeFrom = $earliestInDB ?? $today;
$jsRangeTo = ($latestInDB && strcmp($latestInDB, $today) > 0) ? $latestInDB : $today;
$stepsDataRaw = loadStepsRange($userDb, $jsRangeFrom, $jsRangeTo);

// ╨Ч╨░╨┐╨╛╨╗╨╜╤П╨╡╨╝ ╨┐╤А╨╛╨┐╤Г╤Б╨║╨╕ ╨╜╤Г╨╗╤П╨╝╨╕ ╨▓ ╨╖╨░╨│╤А╤Г╨╢╨╡╨╜╨╜╨╛╨╝ ╨┤╨╕╨░╨┐╨░╨╖╨╛╨╜╨╡
$cd = new DateTime($jsRangeFrom);
$ed2 = new DateTime($jsRangeTo);
$toInsert = [];
while ($cd <= $ed2) {
    $ds = $cd->format('Y-m-d');
    if (!isset($stepsDataRaw[$ds])) $toInsert[] = $ds;
    $cd->modify('+1 day');
}
if ($toInsert) {
    $userDb->exec('BEGIN TRANSACTION');
    foreach ($toInsert as $ds) {
        upsertStep($userDb, $ds, 0);
        $stepsDataRaw[$ds] = ['date' => $ds, 'steps' => 0];
    }
    $userDb->exec('COMMIT');
}

$stepsData = array_values($stepsDataRaw);
usort($stepsData, fn($a, $b) => strcmp($a['date'], $b['date']));

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['video_bootstrap']) && (string)$_GET['video_bootstrap'] === '1' && (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')) {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean();
    $jobStaleSec = 20 * 60;
    $now = time();
    $vj = video_job_load($userDb);
    $st = $vj['state'];
    $status = (string)($st['status'] ?? '');
    $mt = (int)$vj['updated_at'];
    $jidRow = (string)$vj['job_id'];
    if ($status === 'running' && $mt > 0 && ($now - $mt) <= $jobStaleSec && strlen($jidRow) >= 8) {
        exit(json_encode(['ok' => true, 'mode' => 'running', 'jobId' => $jidRow, 'state' => $st]));
    }
    $pf = $userDir . '/pending_video_confirm.json';
    if (is_file($pf) && is_readable($pf)) {
        $pdata = json_decode((string)file_get_contents($pf), true);
        if (is_array($pdata) && !empty($pdata['results']) && is_array($pdata['results'])) {
            exit(json_encode([
                'ok' => true,
                'mode' => 'pending',
                'job_id' => (string)($pdata['job_id'] ?? ''),
                'output_basename' => (string)($pdata['output_basename'] ?? ''),
                'results' => $pdata['results'],
            ]));
        }
    }
    if ($status === 'done' && !empty($st['results']) && is_array($st['results']) && strlen($jidRow) >= 8) {
        exit(json_encode([
            'ok' => true,
            'mode' => 'pending',
            'job_id' => $jidRow,
            'output_basename' => (string)($st['output_basename'] ?? ''),
            'results' => $st['results'],
        ]));
    }
    exit(json_encode(['ok' => true, 'mode' => 'idle']));
}

// тФАтФАтФА POST-╨╛╨▒╤А╨░╨▒╨╛╤В╤З╨╕╨║╨╕ тФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФА
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    ob_clean();

    // ╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╨╜╨░╤Б╤В╤А╨╛╨╡╨║
    if (isset($_POST['settings'])) {
        $goalSteps = filter_var(str_replace(',', '.', $_POST['goal_steps']), FILTER_VALIDATE_FLOAT);
        if ($goalSteps === false || $goalSteps < 0)
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_goal_steps'] ?? 'Invalid goal steps']));

        try {
            $userDb->exec('BEGIN TRANSACTION');
            saveUserSetting($userDb, 'goal_steps',    (string)(int)($goalSteps * 1000));
            saveUserSetting($userDb, 'height',        (string)(filter_var($_POST['height'],        FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 170));
            saveUserSetting($userDb, 'weight',        (string)(filter_var($_POST['weight'],        FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 70));
            saveUserSetting($userDb, 'stride_length', (string)(filter_var($_POST['stride_length'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 70));
            saveUserSetting($userDb, 'birth_date',    $_POST['birth_date'] ?? '1994-01-01');
            $userDb->exec('COMMIT');
        } catch (Exception $e) {
            $userDb->exec('ROLLBACK');
            exit(json_encode(['status' => 'error', 'message' => $translations['failed_save_settings'] ?? 'Failed to save settings']));
        }
        exit(json_encode(['status' => 'success']));
    }

    // ╨Ф╨╛╨▒╨░╨▓╨╗╨╡╨╜╨╕╨╡ ╤И╨░╨│╨╛╨▓
    if (isset($_POST['add_steps'])) {
        $date  = $_POST['date'] ?? '';
        $stepsRaw = preg_replace('/\D+/', '', (string)($_POST['steps'] ?? ''));
        $steps = $stepsRaw === '' ? false : filter_var($stepsRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($date && $steps !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                upsertStep($userDb, $date, $steps);
                exit(json_encode(['status' => 'success']));
            } catch (Exception $e) {
                exit(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
            }
        }
        exit(json_encode(['status' => 'error', 'message' => $translations['invalid_date_steps'] ?? 'Invalid date or steps']));
    }

    if (isset($_POST['import_video_batch'])) {
        $raw = $_POST['rows'] ?? '';
        $rows = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($rows)) {
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_request'] ?? 'Invalid request']));
        }
        $imported = [];
        $ok = false;
        $lastErr = '';
        for ($attempt = 0; $attempt < 8; $attempt++) {
            try {
                $imported = [];
                $userDb->exec('BEGIN IMMEDIATE TRANSACTION');
                foreach ($rows as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $d = trim((string)($r['date'] ?? ''));
                    $sRaw = preg_replace('/\D+/', '', (string)($r['steps'] ?? ''));
                    $s = $sRaw === '' ? false : filter_var($sRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                    if ($s === false || !preg_match('/^\d{2}-\d{2}-\d{2}$/', $d)) {
                        continue;
                    }
                    $iso = csvDateToISO($d);
                    if (!$iso) {
                        continue;
                    }
                    upsertStep($userDb, $iso, (int)$s);
                    $imported[$iso] = (int)$s;
                }
                $userDb->exec('COMMIT');
                $ok = true;
                break;
            } catch (Exception $e) {
                $userDb->exec('ROLLBACK');
                $lastErr = $e->getMessage();
                if (stripos($lastErr, 'locked') === false && stripos($lastErr, 'busy') === false) {
                    break;
                }
                usleep(50000 * ($attempt + 1));
            }
        }
        if (!$ok) {
            exit(json_encode(['status' => 'error', 'message' => $lastErr !== '' ? $lastErr : ($translations['failed_save'] ?? 'Failed to save')]));
        }
        if (count($imported) > 0 && is_file($userDir . '/pending_video_confirm.json')) {
            @unlink($userDir . '/pending_video_confirm.json');
            try {
                video_job_reset($userDb);
            } catch (Exception $e) {
            }
        }
        exit(json_encode(['status' => 'success', 'new_steps' => $imported]));
    }

    if (isset($_POST['cleanup_video_temp'])) {
        $jobId = preg_replace('/[^a-f0-9]/i', '', (string)($_POST['job_id'] ?? ''));
        $bn    = basename((string)($_POST['output_basename'] ?? ''));
        $jBase = realpath($userDir . '/video_jobs');
        if ($jBase && strlen($jobId) >= 8) {
            $jPath = $userDir . '/video_jobs/' . $jobId;
            if (is_dir($jPath)) {
                $rj = realpath($jPath);
                if ($rj && strncmp($rj, $jBase, strlen($jBase)) === 0) {
                    steps_delete_tree($jPath);
                }
            }
        }
        $rBase = realpath($userDir . '/results');
        if ($rBase && $bn !== '' && preg_match('/^steps_data_[A-Za-z0-9_.-]+\.txt$/', $bn)) {
            $fp = $userDir . '/results/' . $bn;
            if (is_file($fp)) {
                $rf = realpath($fp);
                if ($rf && strncmp($rf, $rBase, strlen($rBase)) === 0) {
                    @unlink($rf);
                }
            }
        }
        $px = $userDir . '/pending_video_confirm.json';
        if (is_file($px)) {
            @unlink($px);
        }
        try {
            video_job_reset($userDb);
        } catch (Exception $e) {
        }
        exit(json_encode(['status' => 'success']));
    }

    // ╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╤Б╤В╤А╨╛╨║╨╕ ╨╕╨╖ ╤В╨░╨▒╨╗╨╕╤Ж╤Л (╨┤╨░╤В╨░ dd-mm-yy)
    if (isset($_POST['save_table_data'])) {
        $date  = $_POST['date']  ?? '';
        $steps = preg_replace('/\D+/', '', (string)($_POST['steps'] ?? ''));
        if (empty($date) || $steps === '' || !ctype_digit($steps))
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_data'] ?? 'Invalid data']));
        if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $date))
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_date_format'] ?? 'Invalid date format']));
        $isoDate = csvDateToISO($date);
        if (!$isoDate)
            exit(json_encode(['status' => 'error', 'message' => $translations['invalid_date_format'] ?? 'Invalid date format']));
        try {
            upsertStep($userDb, $isoDate, (int)$steps);
            exit(json_encode(['status' => 'success', 'message' => $translations['data_saved'] ?? 'Data saved']));
        } catch (Exception $e) {
            exit(json_encode(['status' => 'error', 'message' => $translations['failed_save'] ?? 'Failed to save']));
        }
    }

    // тФАтФА ╨в╨░╨▒╨╗╨╕╤Ж╨░: ╨┐╨╛╨╗╤Г╤З╨╡╨╜╨╕╨╡ ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Л ╤Б ╤Д╨╕╨╗╤М╤В╤А╨╛╨╝ (╤З╨╡╤А╨╡╨╖ SQL) тФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФА
    if (isset($_POST['get_table_page'])) {
        $page       = max(1, (int)($_POST['page']      ?? 1));
        $filterOp   = $_POST['filter_op']              ?? '';
        $filterVal  = isset($_POST['filter_val'])  && $_POST['filter_val']  !== '' ? (int)$_POST['filter_val']  : null;
        $filterValTo= isset($_POST['filter_val_to']) && $_POST['filter_val_to'] !== '' ? (int)$_POST['filter_val_to'] : null;
        $dateFrom   = isset($_POST['date_from']) && $_POST['date_from'] !== '' ? (string)$_POST['date_from'] : null;
        $dateTo     = isset($_POST['date_to']) && $_POST['date_to'] !== '' ? (string)$_POST['date_to'] : null;

        $result = loadStepsPage($userDb, $page, TABLE_PAGE_SIZE, $filterOp, $filterVal, $filterValTo, $dateFrom, $dateTo);
        exit(json_encode(['status' => 'success'] + $result));
    }

    // ╨б╨╛╨╖╨┤╨░╨╜╨╕╨╡ ╨▒╤Н╨║╨░╨┐╨░
    if (isset($_POST['create_backup'])) {
        checkDirectory($userDir);
        $backupDir = "$userDir/backups";
        checkDirectory($backupDir);
        $csvContent = exportStepsToCSV($userDb);
        if (empty($csvContent))
            exit(json_encode(['status' => 'error', 'message' => $translations['steps_file_not_found'] ?? 'No steps data']));
        $ts   = date('Y-m-d_H-i-s');
        $name = "Steps_backup_$ts.csv";
        if (file_put_contents("$backupDir/$name", $csvContent) !== false) {
            $files = glob("$backupDir/Steps_backup_*.csv");
            if (count($files) > 10) {
                usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
                for ($i = 0; $i < count($files) - 10; $i++) @unlink($files[$i]);
            }
            exit(json_encode(['status' => 'success', 'message' => $translations['backup_created'] ?? 'Backup created', 'backup_file' => $name]));
        }
        exit(json_encode(['status' => 'error', 'message' => $translations['failed_create_backup'] ?? 'Failed to create backup']));
    }

    // ╨б╨┐╨╕╤Б╨╛╨║ ╨▒╤Н╨║╨░╨┐╨╛╨▓
    if (isset($_POST['get_backups_list'])) {
        $backupDir = "$userDir/backups";
        $backups   = [];
        if (is_dir($backupDir)) {
            foreach (glob("$backupDir/Steps_backup_*.csv") ?: [] as $f) {
                $backups[] = ['name' => basename($f), 'size' => filesize($f), 'date' => date('Y-m-d H:i:s', filemtime($f))];
            }
            usort($backups, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
        }
        exit(json_encode(['status' => 'success', 'backups' => $backups]));
    }

    // ╨Т╨╛╤Б╤Б╤В╨░╨╜╨╛╨▓╨╗╨╡╨╜╨╕╨╡ ╨╕╨╖ ╨▒╤Н╨║╨░╨┐╨░
    if (isset($_POST['restore_backup'])) {
        $bname     = $_POST['backup_file'] ?? '';
        $backupDir = "$userDir/backups";
        if (empty($bname) || !file_exists("$backupDir/$bname"))
            exit(json_encode(['status' => 'error', 'message' => $translations['backup_file_not_found'] ?? 'Backup not found']));
        // ╨Р╨▓╤В╨╛╨▒╤Н╨║╨░╨┐ ╨┐╨╡╤А╨╡╨┤ ╨▓╨╛╤Б╤Б╤В╨░╨╜╨╛╨▓╨╗╨╡╨╜╨╕╨╡╨╝
        $autoCSV = exportStepsToCSV($userDb);
        if ($autoCSV) file_put_contents("$backupDir/Before_restore_" . date('Y-m-d_H-i-s') . ".csv", $autoCSV);
        $content = file_get_contents("$backupDir/$bname");
        if ($content === false)
            exit(json_encode(['status' => 'error', 'message' => $translations['failed_restore_backup'] ?? 'Failed to restore']));
        try {
            $userDb->exec('DELETE FROM steps');
            importStepsFromCSVContent($userDb, $content, true);
            exit(json_encode(['status' => 'success', 'message' => $translations['backup_restored'] ?? 'Restored']));
        } catch (Exception $e) {
            exit(json_encode(['status' => 'error', 'message' => $translations['failed_restore_backup'] ?? 'Failed to restore']));
        }
    }

    // ╨г╨┤╨░╨╗╨╡╨╜╨╕╨╡ ╨▒╤Н╨║╨░╨┐╨░
    if (isset($_POST['delete_backup'])) {
        $bname     = $_POST['backup_file'] ?? '';
        $backupDir = "$userDir/backups";
        if (empty($bname) || !file_exists("$backupDir/$bname"))
            exit(json_encode(['status' => 'error', 'message' => $translations['backup_file_not_found'] ?? 'Backup not found']));
        exit(json_encode(unlink("$backupDir/$bname")
            ? ['status' => 'success', 'message' => $translations['backup_deleted'] ?? 'Deleted']
            : ['status' => 'error',   'message' => $translations['failed_delete_backup'] ?? 'Failed to delete']));
    }

    // ╨б╨║╨░╤З╨╕╨▓╨░╨╜╨╕╨╡ ╤В╨╡╨║╤Г╤Й╨╕╤Е ╨┤╨░╨╜╨╜╤Л╤Е ╨║╨░╨║ CSV
    if (isset($_POST['download_backup'])) {
        $csv = exportStepsToCSV($userDb);
        if ($csv) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="Steps_backup_' . date('Y-m-d') . '.csv"');
            ob_clean();
            echo $csv;
            exit;
        }
        exit(json_encode(['status' => 'error', 'message' => $translations['file_not_found'] ?? 'No data']));
    }

    exit(json_encode(['status' => 'error', 'message' => $translations['invalid_request'] ?? 'Invalid request']));
}

// тФАтФАтФА HTML-╨▓╤Л╨▓╨╛╨┤ тФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФА
ob_clean();
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($translations['steps_counter_title']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <style>
        :root { 
            --bg-color: <?php echo $theme === 'dark' ? '#333' : '#fff'; ?>; 
            --text-color: <?php echo $theme === 'dark' ? '#fff' : '#000'; ?>; 
            --primary-color: <?php echo htmlspecialchars($color); ?>; 
            --modal-bg: <?php echo $theme === 'dark' ? '#444' : '#fff'; ?>; 
            --border-color: <?php echo $theme === 'dark' ? '#666' : '#ccc'; ?>; 
            --group-block-bg: <?php echo $theme === 'dark' ? '#555' : '#e9e9e9'; ?>; 
            --chart-color: <?php echo $theme === 'dark' ? '#fff' : '#000'; ?>; 
            --scrollbar-thumb: <?php echo $theme === 'dark' ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.3)'; ?>; 
        }
        
        /* ╨б╨║╤А╤Л╨▓╨░╨╡╨╝ ╤Б╨║╤А╨╛╨╗╨╗╨▒╨░╤А ╨┐╤А╨╕ ╨╛╤В╨║╤А╤Л╤В╨╛╨╝ ╨╝╨╛╨┤╨░╨╗╤М╨╜╨╛╨╝ ╨╛╨║╨╜╨╡ */
        body.modal-open {
            overflow: hidden !important;
            position: fixed;
            width: 100%;
            height: 100%;
        }
        
        *::-webkit-scrollbar { width: 10px; height: 10px; } 
        *::-webkit-scrollbar-track { background: transparent; } 
        *::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 5px; } 
        *::-webkit-scrollbar-thumb:hover { background: var(--primary-color); } 
        * { scrollbar-width: thin; scrollbar-color: var(--scrollbar-thumb) transparent; }
        
        #alert-modal { z-index: 10030 !important; }
        #global-image-viewer {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2vh 2vw;
            background: rgba(0,0,0,0.88);
            z-index: 10050;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        #global-image-viewer.open { display: flex; opacity: 1; }
        #global-image-viewer img {
            max-width: 95vw;
            max-height: 95vh;
            width: auto;
            height: auto;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 24px 54px rgba(0,0,0,0.45);
            cursor: zoom-out;
        }
        #global-image-viewer-close {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 40px;
            height: 40px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.35);
            background: rgba(0,0,0,0.5);
            color: #fff;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
        }
        #add-steps-modal #add-panel-video { overflow-x: hidden; box-sizing: border-box; }
        #add-steps-modal #add-panel-video .video-analysis-content {
            width: 100%;
            max-width: min(1100px, calc(100vw - 32px));
            min-width: 0;
            height: 92vh;
            max-height: 900px;
            margin: 2vh auto;
            display: flex;
            flex-direction: column;
            padding: 0;
            box-sizing: border-box;
            overflow: hidden;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.35);
        }
        #add-steps-modal #add-panel-video .va-form-box, #add-steps-modal #add-panel-video .va-grid, #add-steps-modal #add-panel-video .video-inline-scroll { min-width: 0; }
        #add-steps-modal #add-panel-video .va-head {
            padding: 18px 22px 14px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, rgba(0,0,0,0.06) 0%, transparent 60%);
        }
        #add-steps-modal #add-panel-video .va-head h2 { margin: 0 40px 6px 0; font-size: 1.35rem; font-weight: 600; }
        #add-steps-modal #add-panel-video .va-sub { margin: 0; font-size: 13px; opacity: 0.85; line-height: 1.4; }
        .va-steps { display: flex; align-items: center; justify-content: center; gap: 0; margin: 0 0 14px; user-select: none; }
        .va-step-dot { width: 34px; height: 34px; border-radius: 50%; border: 2px solid var(--border-color); background: var(--modal-bg); color: var(--text-color); font-weight: 700; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; }
        .va-step-dot.is-active { border-color: var(--primary-color); background: var(--primary-color); color: #fff; }
        .va-step-dot.is-done { border-color: var(--primary-color); color: var(--primary-color); background: rgba(0,123,255,0.08); }
        .va-step-dot[disabled] { opacity: 0.45; cursor: not-allowed; }
        .va-step-line { width: min(140px, 16vw); height: 2px; background: var(--border-color); margin: 0 10px; }
        .va-step-line.is-done { background: var(--primary-color); }
        #add-steps-modal #add-panel-video .video-inline-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            text-align: left;
            padding: 16px 18px 20px;
        }
        #add-steps-modal #add-panel-video .va-grid { display: flex; flex-direction: column; gap: 14px; }
        #add-steps-modal #add-panel-video .va-segment {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 16px;
            background: var(--group-block-bg);
        }
        #add-steps-modal #add-panel-video .va-segment.is-locked { opacity: 0.55; pointer-events: none; }
        #add-steps-modal #add-panel-video .va-segment-h {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            opacity: 0.9;
            margin: 0 0 12px;
            color: var(--primary-color);
        }
        #add-steps-modal #add-panel-video .va-segment-h i { font-size: 14px; opacity: 0.9; }
        #add-steps-modal #add-panel-video .va-field label { display: block; margin: 8px 0 4px; font-size: 13px; font-weight: 500; }
        #add-steps-modal #add-panel-video .va-field:first-child label { margin-top: 0; }
        #add-steps-modal #add-panel-video .va-field input[type="file"],
        #add-steps-modal #add-panel-video .va-field input[type="text"],
        #add-steps-modal #add-panel-video .va-field select {
            width: 100%;
            max-width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--modal-bg);
            color: var(--text-color);
            box-sizing: border-box;
        }
        #add-steps-modal #add-panel-video .video-btn-row { margin-top: 4px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        #add-steps-modal #add-panel-video .video-btn-row .excel-btn { padding: 10px 18px; border-radius: 8px; }
        #add-steps-modal #add-panel-video #va-job-panel.va-segment { background: var(--modal-bg); }
        #add-steps-modal #add-panel-video #va-progress-bar { height: 10px; background: rgba(0,0,0,0.12); border-radius: 6px; overflow: hidden; border: none; margin-top: 8px; }
        #add-steps-modal #add-panel-video #va-progress-fill { height: 100%; width: 0%; background: var(--primary-color); transition: width 0.25s ease; border-radius: 6px; }
        #add-steps-modal #add-panel-video #va-msg { margin: 6px 0 0; font-size: 14px; line-height: 1.45; }
        #add-steps-modal #add-panel-video #va-meta { font-size: 12px; margin: 8px 0 0; opacity: 0.85; }
        #va-file-load-wrap { display: none; margin-top: 10px; }
        #va-file-load-label { font-size: 12px; margin-bottom: 4px; opacity: 0.9; }
        #va-file-load-track { position: relative; height: 8px; background: rgba(0,0,0,0.1); border-radius: 4px; overflow: hidden; border: 1px solid var(--border-color); }
        #va-file-load-fill { height: 100%; width: 0%; background: var(--primary-color); border-radius: 4px; transition: width 0.12s ease; }
        #va-file-load-wrap.va-file-load-indet #va-file-load-fill { width: 100% !important; transition: none; animation: vaFileLoadP 0.9s ease-in-out infinite; }
        @keyframes vaFileLoadP { 0%, 100% { opacity: 0.35; } 50% { opacity: 1; } }
        #va-crop-btn:disabled, #va-start:disabled { opacity: 0.45; pointer-events: none; cursor: not-allowed; }
        #video-crop-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 10002; display: none; align-items: center; justify-content: center; overflow: hidden; overflow-x: hidden; }
        #video-crop-modal .video-crop-inner { background: var(--modal-bg); padding: 16px; border-radius: 8px; max-width: min(96vw, 100%); max-height: 88vh; overflow: hidden; overflow-x: hidden; box-sizing: border-box; min-width: 0; }
        #video-crop-img { max-width: 100%; max-height: 70vh; display: block; }
        #video-crop-modal .cropper-view-box { outline: none !important; border: 3px solid #fff !important; box-shadow: 0 0 0 1px #000, 0 0 0 4px rgba(255,255,255,0.95) !important; }
        #video-crop-modal .cropper-face { background: transparent !important; opacity: 0 !important; }
        #video-crop-modal .cropper-line { background-color: transparent !important; opacity: 0 !important; }
        #video-crop-modal .cropper-point { background-color: #fff !important; border: 2px solid #000 !important; opacity: 1 !important; }
        #video-crop-modal .cropper-dashed { display: none !important; }
        #video-crop-modal .cropper-center { display: none !important; }
        
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background: var(--bg-color); color: var(--text-color); transition: all 0.3s ease; }
        
        .app-steps { background: var(--bg-color); padding: 20px; border-radius: 8px; text-align: center; max-width: 80%; margin: auto; position: relative; }
        
        .view-selector { margin-bottom: 20px; display: flex; justify-content: center; background: rgba(0,0,0,0.1); border-radius: 20px; padding: 5px; }
        .view-btn { background: var(--primary-color); color: #fff; border: none; padding: 10px 20px; margin: 0 2px; cursor: pointer; border-radius: 16px; } 
        .view-btn.active, .view-btn:hover { opacity: 0.8; }
        
        .chart-container { position: relative; margin-bottom: 20px; display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 10px; width: 100%; max-width: 820px; margin-left: auto; margin-right: auto; box-sizing: border-box; }
        .chart-nav { flex-shrink: 0; align-self: center; font-size: 24px; background: var(--modal-bg); border: 1px solid var(--border-color); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .chart-nav:hover { background: var(--primary-color); color: #fff; }
        .chart-main { position: relative; flex: 1 1 auto; min-width: 0; max-width: 720px; width: 100%; padding-top: 40px; }
        .chart-label { position: absolute; top: 4px; left: 50%; transform: translateX(-50%); font-size: 15px; font-weight: 700; text-align: center; pointer-events: none;
            padding: 6px 14px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--modal-bg);
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
            max-width: calc(100% - 20px);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chart-wrapper { position: relative; width: 100%; max-width: 720px; margin: 0 auto; }
        #steps-chart { width: 100%; height: 460px; max-width: 720px; display: block; }
        
        .calendar-container { margin-top: 20px; width: 650px; margin-left: auto; margin-right: auto; }
        .calendar-header select { padding: 5px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--modal-bg); color: var(--text-color); margin-bottom: 15px;}
        .calendar-table { width: 100%; border-collapse: collapse; outline: none; } 
        .calendar-table th, .calendar-table td { pointer-events: none; outline: none; border: 1px solid var(--border-color); padding: 10px; text-align: center; height: 40px; pointer-events: none; }
        .calendar-table th { color: rgba(196, 196, 0, 1); font-weight: bold; } 
        .calendar-table td { position: relative; width: 14.28%; } 
        .calendar-table td[data-date] { cursor: pointer; pointer-events: auto; } 
        .calendar-table td.selected { background: var(--primary-color); color: #fff; }
        .calendar-table td.calendar-day-other-month { color: #9a9a9a; }
        .calendar-table td.calendar-day-other-month.selected { background: var(--primary-color); color: #fff; }
        
        .goal-icon { width: 20px; height: 20px; position: absolute; right: 2px; top: 50%; transform: translateY(-50%); }
        .goal-summary { margin-top: 20px; padding: 10px; background: var(--group-block-bg); border-radius: 8px; border: 1px solid var(--border-color); }
        
        /* ╨Ъ╨╜╨╛╨┐╨║╨╕ ╨╝╨╡╨╜╤О ╤Б ╨╛╨┤╨╕╨╜╨░╨║╨╛╨▓╤Л╨╝╨╕ ╨╕╨║╨╛╨╜╨║╨░╨╝╨╕ */
        #info-button, #settings-button, #add-steps-button, #excel-button { 
            position: absolute; 
            top: 5px; 
            background: none; 
            border: none; 
            cursor: pointer; 
            font-size: 24px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            border-radius: 50%;
            transition: background-color 0.2s;
        }
        #info-button:hover, #settings-button:hover, #add-steps-button:hover, #excel-button:hover { 
            background: rgba(0, 123, 255, 0.1); 
        }
        #info-button { right: 60px; }
        #settings-button { right: 110px; }
        #add-steps-button { right: 210px; }
        #excel-button { right: 160px; }
        
        /* ╨Ъ╤А╤Г╨│╨╗╨╛╨╡ ╤Д╨╛╤В╨╛ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤П тАФ ╨║╤А╨░╨╣╨╜╨╡╨╡ ╨┐╤А╨░╨▓╨╛╨╡ */
        #user-button {
            position: absolute;
            top: 5px;
            right: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            padding: 0;
            overflow: hidden;
            border: 2px solid var(--primary-color);
            background: transparent;
            cursor: default;
        }
        
        #user-button img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        /* ╨Ь╨╛╨┤╨░╨╗╤М╨╜╤Л╨╡ ╨╛╨║╨╜╨░ ╨┤╨╗╤П ╨╝╨╛╨▒╨╕╨╗╤М╨╜╤Л╤Е */
        #info-modal, #settings-modal, #add-steps-modal, #user-modal, #add-user-modal, #video-confirm-modal, #video-confirm-gaps-modal, #excel-modal, #excel-hotkeys-modal, #backups-modal, #alert-modal { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.8); 
            display: none; 
            justify-content: center; 
            align-items: center; 
            z-index: 10000; 
            overflow: hidden;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        #excel-hotkeys-modal { z-index: 10020; }
        #excel-modal {
            padding: 12px;
            align-items: stretch;
            justify-content: center;
        }
        .video-confirm-inner {
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        #video-confirm-table-wrap {
            min-height: 0;
        }
        #video-confirm-gaps-modal { z-index: 10025; background: rgba(0,0,0,0.55); }
        
        .modal-close { 
            position: absolute; 
            top: 15px; 
            right: 15px; 
            background: none; 
            border: 2px solid red; 
            border-radius: 4px; 
            color: red; 
            font-size: 18px; 
            cursor: pointer; 
            padding: 2px 8px; 
            z-index: 10001;
        }
        
        .settings-content, .add-steps-content, .user-content, .add-user-content, .video-analysis-content, .video-confirm-inner, .video-confirm-gaps-inner, .excel-content, .backups-content, .alert-content, .info-content { 
            background: var(--modal-bg); 
            padding: 20px; 
            border-radius: 8px; 
            width: min(90vw, 720px);
            max-width: 100%;
            max-height: 90vh; 
            overflow: hidden;
            overflow-x: hidden;
            position: relative;
            margin: 20px;
            box-sizing: border-box;
            min-width: 0;
        }
        #info-modal .info-content {
            overflow-y: auto;
            overflow-x: hidden;
        }
        #settings-modal .settings-content {
            overflow: hidden;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            width: min(90vw, 520px);
        }
        #settings-modal .settings-content > h2 {
            flex-shrink: 0;
            margin-top: 0;
        }
        .settings-tab-bar {
            display: flex;
            flex-shrink: 0;
            gap: 4px;
            padding: 5px;
            margin-bottom: 14px;
            background: var(--group-block-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
        }
        .settings-tab {
            flex: 1;
            margin: 0;
            padding: 11px 14px;
            border: none;
            border-radius: 9px;
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
        }
        .settings-tab:hover {
            background: rgba(0,0,0,0.07);
        }
        .settings-tab.active {
            background: var(--modal-bg);
            color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }
        .settings-tab-panel {
            display: none;
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1 1 auto;
            min-height: min(58vh, 520px);
            max-height: min(58vh, 520px);
            padding-right: 2px;
        }
        .settings-tab-panel.active {
            display: block;
        }
        #settings-panel-chart .settings-chart-type-intro {
            text-align: left;
            font-size: 14px;
            line-height: 1.45;
            margin: 0 0 14px;
            opacity: 0.92;
        }
        .settings-chart-type-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .settings-chart-type-group .chart-type-btn {
            flex: 1;
            min-width: 130px;
            justify-content: center;
            margin: 0;
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 14px;
        }
        #add-steps-modal .add-steps-content {
            overflow: hidden;
        }
        #add-steps-modal .add-steps-merged {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            width: min(96vw, 980px);
            max-height: min(92vh, 920px);
            padding: 24px;
        }
        #add-steps-modal .add-steps-main-title {
            margin: 0 0 10px 0;
            font-size: 1.35rem;
            text-align: center;
        }
        .add-tab-bar {
            display: flex;
            flex-shrink: 0;
            gap: 4px;
            padding: 5px;
            margin-bottom: 14px;
            background: var(--group-block-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        .add-tab {
            flex: 1;
            margin: 0;
            padding: 12px 10px;
            border: none;
            border-radius: 9px;
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .add-tab:hover { background: rgba(0,0,0,0.07); }
        .add-tab.active {
            background: var(--modal-bg);
            color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }
        .add-tab-panel {
            display: none;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: min(62vh, 560px);
            max-height: min(72vh, 700px);
            padding-right: 2px;
        }
        .add-tab-panel.active { display: block; }
        #add-panel-manual { text-align: center; align-items: center; }
        #add-panel-takeout .video-analysis-content {
            padding: 2px;
            max-width: min(980px, calc(100% - 16px));
            margin: 0 auto;
        }
        #add-panel-takeout .va-head {
            text-align: center;
            margin-bottom: 10px;
        }
        #add-panel-takeout .va-head h2 {
            margin: 0 0 6px 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        #add-panel-takeout .va-sub {
            margin: 0 0 10px 0;
            font-size: 13px;
            opacity: 0.9;
            line-height: 1.45;
        }
        #add-panel-takeout .takeout-hint {
            margin-bottom: 12px;
        }
        #add-panel-takeout .video-inline-scroll {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        #add-panel-takeout .va-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
            align-items: center;
            width: 100%;
            max-width: 100%;
        }
        #add-panel-takeout .va-segment {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--group-block-bg);
            padding: 12px;
            width: min(100%, 860px);
        }
        #add-panel-takeout .va-field {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #add-panel-takeout .va-field label {
            display: block;
            margin: 8px 0 6px;
            font-size: 13px;
            font-weight: 500;
        }
        #add-panel-takeout .va-field input[type="file"] {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--modal-bg);
            color: var(--text-color);
        }
        #add-panel-takeout .video-btn-row {
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
        }
        #add-panel-takeout .video-btn-row .excel-btn {
            padding: 10px 18px;
            border-radius: 8px;
        }
        #add-panel-takeout #ft-progress-bar {
            height: 10px;
            background: rgba(0,0,0,0.12);
            border-radius: 6px;
            overflow: hidden;
            margin-top: 8px;
        }
        #add-panel-takeout #ft-progress-fill {
            height: 100%;
            width: 0%;
            background: var(--primary-color);
            border-radius: 6px;
            transition: width 0.25s ease;
        }
        #add-panel-takeout #ft-msg {
            margin: 6px 0 0;
            font-size: 14px;
            line-height: 1.45;
            text-align: center;
        }
        #add-panel-takeout #ft-meta {
            font-size: 12px;
            opacity: 0.85;
            margin: 8px 0 0;
            text-align: center;
        }
        #add-panel-takeout .takeout-guide-segment {
            width: 100%;
            max-width: 100%;
            padding: 0;
            overflow: hidden;
            background: var(--modal-bg);
        }
        #add-panel-takeout .takeout-guide-head {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: space-between;
            padding: 12px 14px;
            background: linear-gradient(130deg, color-mix(in srgb, var(--primary-color) 18%, transparent), transparent 70%);
        }
        #add-panel-takeout .takeout-guide-toggle {
            margin-left: auto;
            border: 0;
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            padding: 2px 4px;
        }
        #add-panel-takeout .takeout-guide-body {
            border-top: 1px solid var(--border-color);
        }
        #add-panel-takeout .takeout-guide-segment.is-collapsed .takeout-guide-body {
            display: none;
        }
        #add-panel-takeout .takeout-guide-segment.is-collapsed .takeout-guide-toggle i {
            transform: rotate(-90deg);
        }
        #add-panel-takeout .takeout-guide-toggle i {
            transition: transform 0.2s ease;
        }
        #add-panel-takeout #takeout-guide-frame {
            width: 100%;
            height: min(62vh, 660px);
            border: 0;
            display: block;
            background: var(--modal-bg);
        }
        .worker-stop-btn {
            background: #dc3545 !important;
            color: #fff !important;
        }
        #excel-modal .excel-content {
            display: flex;
            flex-direction: column;
            width: calc(100vw - 24px);
            max-width: calc(100vw - 24px);
            height: calc(100vh - 24px);
            max-height: calc(100vh - 24px);
            margin: 0;
            padding: 50px 48px 14px 16px;
            overflow: hidden;
            min-height: 0;
        }
        #excel-modal .excel-content > .modal-close {
            top: 12px;
            right: 12px;
            z-index: 3;
        }
        .excel-modal-head {
            display: grid;
            grid-template-columns: 48px minmax(0, 1fr) 48px;
            align-items: center;
            justify-items: stretch;
            flex-shrink: 0;
            margin-bottom: 10px;
            column-gap: 8px;
        }
        .excel-modal-head .excel-head-side-left {
            justify-self: start;
        }
        .excel-modal-head h2 {
            margin: 0;
            font-size: 1.25rem;
            text-align: center;
            justify-self: center;
            max-width: 100%;
        }
        .excel-modal-head .excel-head-spacer {
            width: 42px;
            height: 42px;
            justify-self: end;
            visibility: hidden;
            pointer-events: none;
        }
        #excel-hotkeys-open {
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            padding: 0;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            background: var(--group-block-bg);
            color: var(--primary-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: background 0.15s ease, color 0.15s ease;
        }
        #excel-hotkeys-open:hover {
            background: var(--primary-color);
            color: #fff;
        }
        #excel-modal .excel-content > .excel-search-container,
        #excel-modal .excel-content > .excel-actions {
            flex-shrink: 0;
        }
        #excel-modal .excel-search-container {
            margin-bottom: 8px;
        }
        #excel-modal .excel-table-stack {
            flex: 1 1 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin: 8px 0;
        }
        #excel-modal .excel-table-stack .excel-table-container {
            flex: 1 1 0;
            min-height: 80px;
            max-height: none;
            margin: 0;
            overflow: auto;
        }
        #excel-modal .excel-table-pagination-bar {
            flex-shrink: 0;
            background: var(--modal-bg);
            border: 1px solid var(--border-color);
            border-top: none;
            box-sizing: border-box;
        }
        #excel-modal .excel-table-pagination-bar .table-pagination {
            padding: 10px 0 6px;
            margin: 0;
        }
        #excel-table-container .excel-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        #excel-table-container .excel-table th,
        #excel-table-container .excel-table td {
            border: none;
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }
        #excel-table-container .excel-table th:first-child,
        #excel-table-container .excel-table td:first-child {
            border-left: 1px solid var(--border-color);
        }
        #excel-table-container .excel-table thead tr:first-child th {
            border-top: 1px solid var(--border-color);
        }
        #excel-modal .excel-actions {
            flex-wrap: nowrap;
            overflow-x: auto;
            margin-top: 0;
            gap: 8px;
            padding: 4px 0;
            -webkit-overflow-scrolling: touch;
        }
        #excel-modal .excel-actions .excel-btn {
            flex: 0 0 auto;
            white-space: nowrap;
            padding: 9px 14px;
            font-size: 13px;
        }
        #excel-hotkeys-modal .excel-hotkeys-inner {
            background: var(--modal-bg);
            padding: 20px;
            border-radius: 8px;
            width: min(90vw, 520px);
            max-width: 100%;
            max-height: min(85vh, 560px);
            overflow-y: auto;
            overflow-x: hidden;
            position: relative;
            margin: 16px;
            box-sizing: border-box;
        }
        #excel-hotkeys-modal .excel-hotkeys-inner .modal-close {
            z-index: 2;
        }
        #excel-hotkeys-modal .excel-hotkeys-inner h2 {
            margin-top: 0;
            padding-right: 36px;
        }
        
        /* Excel ╤В╨░╨▒╨╗╨╕╤Ж╨░ */
        .excel-table-container {
            width: 100%;
            max-width: 100%;
            max-height: 60vh;
            overflow-x: hidden;
            overflow-y: auto;
            margin: 20px 0;
            border: 1px solid var(--border-color);
            box-sizing: border-box;
            min-width: 0;
        }
        
        .excel-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .excel-table th, .excel-table td {
            border: 1px solid var(--border-color);
            padding: 8px;
            text-align: center;
            min-width: 100px;
        }
        
        .excel-table thead th {
            background: var(--primary-color);
            color: white;
            position: sticky;
            top: 0;
            z-index: 5;
            box-shadow: 0 2px 4px rgba(0,0,0,0.18);
        }
        
        .excel-table td {
            background: var(--modal-bg);
        }
        
        .excel-table td.selected {
            background: rgba(0, 123, 255, 0.3);
        }
        
        .excel-table td.editing {
            background: rgba(255, 255, 0, 0.3);
        }
        
        .excel-table tr.found {
            background: rgba(255, 255, 0, 0.3) !important;
        }
        
        .excel-table tr.highlighted {
            background: rgba(0, 123, 255, 0.2) !important;
        }
        
        .excel-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .excel-btn {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .excel-btn:hover {
            opacity: 0.8;
        }
        
        .excel-btn.delete {
            background: #dc3545;
        }
        
        .excel-btn.download {
            background: #17a2b8;
        }
        
        .excel-btn.restore {
            background: #28a745;
        }
        
        .excel-btn.manage-backups {
            background: #6c757d;
        }
        
        .excel-btn.create-backup {
            background: #17a2b8;
        }
        
        /* Excel search */
        .excel-search-container {
            background: var(--group-block-bg);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 15px;
        }
        
        .hotkey {
            display: inline-block;
            padding: 2px 6px;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
            font-family: monospace;
            font-size: 11px;
            margin: 0 2px;
        }
        
        .excel-hotkeys-info {
            line-height: 1.6;
            margin-bottom: 15px;
            padding: 10px;
            background: var(--group-block-bg);
            border-radius: 4px;
            font-size: 12px;
        }
        
        .add-steps-content, .user-content, .add-user-content { display: flex; flex-direction: column; align-items: center; }
        .settings-content input[type="text"], .add-user-content input[type="text"], .settings-content input[type="date"] { 
            padding: 10px; 
            border: 1px solid var(--border-color); 
            border-radius: 4px; 
            background: var(--modal-bg); 
            color: var(--text-color); 
        }
        
        /* ╨и╨╕╤А╨╡ ╨┐╨╛╨╗╨╡ ╨┤╨░╤В╤Л ╤А╨╛╨╢╨┤╨╡╨╜╨╕╤П */
        .settings-content input[name="birth_date"] {
            width: 150px;
        }
        
        .add-steps-content input[type="date"], .add-steps-content input[type="text"] { 
            padding: 10px; 
            border: 1px solid var(--border-color); 
            border-radius: 4px; 
            background: var(--modal-bg); 
            color: var(--text-color); 
        }
        
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; }
        
        .settings-tile { display: flex; flex-direction: column; align-items: center; background: var(--group-block-bg); padding: 15px; margin-bottom: 10px; border-radius: 8px; border: 1px solid var(--border-color); }
        .settings-tile hr { border: none; border-top: 1px solid var(--primary-color); width: 100%; margin: 10px 0; }
        .tile-title { font-weight: bold; margin-bottom: 10px; }
        .tile-content { display: grid; grid-template-columns: 80px 120px 70px; align-items: center; justify-items: center; gap: 10px; }
        .tile-content span:last-child { justify-self: end; }
        .save-button-container { background: var(--group-block-bg); border-radius: 8px; padding: 5px; margin-top: 20px; }
        .save-button { background: var(--primary-color); color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; }
        
        .data-type-selector { display: flex; justify-content: center; margin-bottom: 20px; }
        .data-type-btn { 
            width: 80px; 
            height: 80px; 
            border-radius: 50%; 
            margin: 0 10px; 
            border: 2px solid var(--border-color); 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: var(--modal-bg);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        } 
        .data-type-btn:hover {
            border-color: var(--primary-color);
            background: rgba(0,123,255,0.08);
            box-shadow: 0 8px 18px rgba(0,123,255,0.2);
            transform: translateY(-2px);
        }
        .data-type-btn.active { border-color: var(--primary-color); }
        .data-type-btn img { max-width: 70%; max-height: 70%; transition: transform 0.18s ease, filter 0.18s ease; }
        .data-type-btn:hover img { transform: scale(1.06); filter: brightness(1.07); }
        
        .chart-type-btn { background: var(--primary-color); color: #fff; border: none; padding: 8px; margin: 5px 0; cursor: pointer; border-radius: 4px; font-size: 14px; display: flex; align-items: center; } 
        .chart-type-btn.active, .chart-type-btn:hover { opacity: 0.9; }
        .chart-type-btn span { margin-left: 5px; }
        
        .notification { 
            background: var(--modal-bg); 
            padding: 15px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.3); 
            width: min(380px, calc(100vw - 40px)); 
            max-width: min(380px, calc(100vw - 40px));
            min-width: 0;
            box-sizing: border-box; 
            max-height: 220px; 
            overflow-y: auto; 
            border-left: 4px solid var(--primary-color); 
            word-wrap: break-word; 
            overflow-wrap: break-word; 
        }
        .notification.video-related-notif { border-left-color: #e6a200; }
        
        .notification-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); }
        .notification-title { font-weight: bold; font-size: 16px; color: var(--primary-color); }
        .notification-content { font-size: 14px; line-height: 1.4; }
        .notification-item { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin: 5px 0; flex-wrap: nowrap; font-size: 13px; }
        .notification-item > span:first-child { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notification-value { font-weight: bold; flex-shrink: 0; white-space: nowrap; text-align: right; }
        .close-notification { background: none; border: none; color: #999; font-size: 18px; cursor: pointer; } 
        .close-notification:hover { color: red; }
        
        .adjacent-dates { width: 100%; margin: 10px 0; }
        .adjacent-date { display: flex; justify-content: space-between; padding: 5px; background: var(--group-block-bg); border-radius: 4px; margin: 5px 0; opacity: 0.7; cursor: pointer; } 
        .adjacent-date:hover { opacity: 1; background: var(--primary-color); color: #fff; }
        .current-date { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: var(--modal-bg); border: 1px solid var(--primary-color); border-radius: 4px; margin: 10px auto; width: 50%; }
        .current-date input[type="date"], .current-date input[type="text"] { width: 45%; font-size: 16px; }
        
        #notifications { position: fixed; bottom: 20px; right: 20px; z-index: 10000; display: flex; flex-direction: column-reverse; align-items: flex-end; gap: 10px; max-width: min(100vw - 32px, 100%); box-sizing: border-box; }
        #worker-progress-widget { position: fixed; left: 20px; bottom: 20px; z-index: 10000; display: none; flex-direction: column; gap: 8px; align-items: flex-start; }
        .worker-progress-item { display: inline-flex; align-items: center; gap: 8px; padding: 8px; border-radius: 8px; background: var(--modal-bg); border: 1px solid var(--border-color); box-shadow: 0 3px 14px rgba(0,0,0,0.18); font-size: 13px; line-height: 1.2; width: fit-content; }
        .worker-progress-item i { width: 14px; text-align: center; color: var(--primary-color); }
        .worker-progress-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        /* Alert modal */
        .alert-content {
            max-width: 400px;
            text-align: center;
        }
        
        .alert-message {
            margin: 20px 0;
            font-size: 16px;
        }
        
        .alert-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .alert-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            min-width: 80px;
        }
        
        .alert-btn.ok {
            background: var(--primary-color);
            color: white;
        }
        
        .alert-btn.cancel {
            background: var(--group-block-bg);
            color: var(--text-color);
        }
        
        .alert-btn.extra {
            background: #ffc107;
            color: #212529;
        }
        
        /* Info modal */
        .info-content {
            max-width: 800px;
        }
        
        .info-section {
            margin: 20px 0;
            padding: 15px;
            background: var(--group-block-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .info-section h3 {
            color: var(--primary-color);
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-section ul {
            padding-left: 20px;
        }
        
        .info-section li {
            margin: 8px 0;
        }
        
        .hotkey {
            display: inline-block;
            padding: 2px 6px;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            margin: 0 3px;
        }
        
        /* Backups modal */
        .backups-list {
            max-height: 400px;
            overflow-y: auto;
            margin: 20px 0;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .backup-item:last-child {
            border-bottom: none;
        }
        
        .backup-info {
            flex-grow: 1;
        }
        
        .backup-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .backup-details {
            font-size: 12px;
            color: #666;
        }
        
        .backup-actions {
            display: flex;
            gap: 10px;
        }
        
        .backup-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .backup-btn.restore {
            background: #28a745;
            color: white;
        }
        
        .backup-btn.delete {
            background: #dc3545;
            color: white;
        }
        
        /* ╨Я╨░╨│╨╕╨╜╨░╤Ж╨╕╤П ╤В╨░╨▒╨╗╨╕╤Ж╤Л */
        .table-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 0 4px;
            flex-wrap: wrap;
        }
        .page-btn {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--modal-bg);
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
        }
        .page-btn:disabled { opacity: 0.35; cursor: default; }
        .page-btn:not(:disabled):hover { background: var(--primary-color); color: #fff; }
        .page-info { font-size: 13px; color: var(--text-color); }

        /* ╨Ь╨╛╨▒╨╕╨╗╤М╨╜╨░╤П ╨░╨┤╨░╨┐╤В╨░╤Ж╨╕╤П */
        @media only screen and (max-width: 768px) {
            body.modal-open {
                padding: 0 !important;
            }
            
            .app-steps { 
                max-width: 100%; 
                padding: 10px; 
                margin: 0; 
            }
            
            .app-steps h1 { 
                font-size: 1.2em; 
                margin-top: 5px; 
                margin-bottom: 5px; 
                margin-right: 200px; 
                text-align: left; 
            }
            
            #info-button, #settings-button, #add-steps-button, #excel-button { 
                top: 5px; 
                font-size: 20px;
                width: 35px;
                height: 35px;
            }
            #info-button { right: 60px; }
            #settings-button { right: 105px; }
            #add-steps-button { right: 195px; }
            #excel-button { right: 150px; }
            
            /* ╨Ъ╤А╤Г╨│╨╗╨╛╨╡ ╤Д╨╛╤В╨╛ ╨┐╨╛╨╗╤М╨╖╨╛╨▓╨░╤В╨╡╨╗╤П ╨┤╨╗╤П ╨╝╨╛╨▒╨╕╨╗╤М╨╜╤Л╤Е */
            #user-button {
                width: 35px;
                height: 35px;
                right: 10px;
                top: 5px;
            }
            
            .goal-icon { width: 12px; height: 12px; right: 1px; left: auto; top: 50%; transform: translateY(-50%); }
            
            .chart-wrapper { width: 100%; height: 280px; position: relative; overflow: hidden; box-sizing: border-box; }
            .chart-container { max-width: 100%; padding: 0 8px; box-sizing: border-box; flex-direction: row; align-items: center; gap: 6px; }
            .chart-main { max-width: none; }
            #steps-chart { width: 100% !important; height: 100% !important; }
            .chart-nav { width: 32px; height: 32px; font-size: 15px; }
            
            .calendar-container { width: 100%; padding: 0 10px; box-sizing: border-box; }
            .calendar-table { font-size: 12px; }
            .calendar-table th, .calendar-table td { padding: 5px; height: 30px; }
            
            .settings-content, .add-steps-content, .user-content, .add-user-content, .video-analysis-content, .video-confirm-inner, .video-confirm-gaps-inner, .excel-content, .backups-content, .alert-content, .info-content { 
                width: 95% !important; 
                max-width: 95% !important; 
                padding: 15px; 
                margin: 10px;
                max-height: 85vh;
                overflow: hidden;
                overflow-x: hidden;
            }
            #add-steps-modal .add-steps-merged {
                width: 96% !important;
                max-width: 96% !important;
                max-height: 88vh !important;
                padding: 16px !important;
            }
            .add-tab {
                font-size: 13px;
                padding: 10px 8px;
            }
            .add-tab-panel {
                min-height: min(58vh, 520px);
                max-height: min(68vh, 640px);
            }
            
            .settings-content input[name="birth_date"] {
                width: 120px;
            }
            
            .excel-table-container {
                max-height: 50vh;
            }
            #excel-modal .excel-content {
                width: calc(100vw - 16px) !important;
                max-width: calc(100vw - 16px) !important;
                height: calc(100vh - 16px) !important;
                max-height: calc(100vh - 16px) !important;
                margin: 0 !important;
                padding: 46px 42px 10px 10px !important;
            }
            .excel-modal-head {
                grid-template-columns: 44px minmax(0, 1fr) 44px;
                column-gap: 4px;
            }
            .excel-modal-head .excel-head-spacer {
                width: 38px;
                height: 38px;
            }
            #excel-modal {
                padding: 8px;
            }
            #excel-modal .excel-table-stack {
                flex: 1 1 0;
                min-height: 0;
                margin: 6px 0;
            }
            #excel-modal .excel-table-stack .excel-table-container {
                max-height: none;
                flex: 1 1 0;
                min-height: 60px;
            }
            #excel-modal .excel-actions {
                flex-direction: row;
                flex-wrap: nowrap;
            }
            #excel-modal .excel-actions .excel-btn {
                width: auto;
            }
            #info-modal .info-content {
                overflow-y: auto;
            }
            #settings-modal .settings-content {
                width: 95% !important;
                max-width: 95% !important;
            }
            .settings-tab-panel {
                min-height: min(52vh, 420px);
                max-height: min(52vh, 420px);
            }
            
            .excel-table th, .excel-table td {
                padding: 4px;
                font-size: 12px;
                min-width: 80px;
            }
            
            .excel-search-container {
                padding: 10px;
            }
            
            .excel-hotkeys-info {
                font-size: 10px;
            }
            
            .hotkey {
                padding: 1px 4px;
                font-size: 10px;
            }
            
            .excel-actions {
                flex-direction: column;
            }
            
            .excel-btn {
                width: 100%;
                justify-content: center;
            }
            
            .backup-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .backup-actions {
                width: 100%;
                justify-content: flex-end;
                margin-top: 10px;
            }
            
            #add-panel-video .video-analysis-content { max-height: 80vh; }
            #video-crop-modal .cropper-view-box { border-width: 4px !important; box-shadow: 0 0 0 2px #000, 0 0 0 6px #fff !important; }
            #video-crop-modal .cropper-point {
                width: 22px !important;
                height: 22px !important;
                opacity: 1 !important;
                background-color: #fff !important;
                border: 3px solid #000 !important;
                box-sizing: border-box !important;
            }
            #video-crop-modal .cropper-point.point-e {
                margin-top: -11px !important;
                right: -11px !important;
            }
            #video-crop-modal .cropper-point.point-n {
                margin-left: -11px !important;
                top: -11px !important;
            }
            #video-crop-modal .cropper-point.point-ne {
                right: -11px !important;
                top: -11px !important;
            }
            #video-crop-modal .cropper-point.point-nw {
                left: -11px !important;
                top: -11px !important;
            }
            #video-crop-modal .cropper-point.point-s {
                margin-left: -11px !important;
                bottom: -11px !important;
            }
            #video-crop-modal .cropper-point.point-se {
                bottom: -11px !important;
                right: -11px !important;
            }
            #video-crop-modal .cropper-point.point-sw {
                bottom: -11px !important;
                left: -11px !important;
            }
            #video-crop-modal .cropper-point.point-w {
                margin-top: -11px !important;
                left: -11px !important;
            }
            #video-crop-modal .cropper-line.line-e {
                width: 20px !important;
                right: -10px !important;
            }
            #video-crop-modal .cropper-line.line-n {
                height: 20px !important;
                top: -10px !important;
            }
            #video-crop-modal .cropper-line.line-w {
                width: 20px !important;
                left: -10px !important;
            }
            #video-crop-modal .cropper-line.line-s {
                height: 20px !important;
                bottom: -10px !important;
            }
            #notifications { bottom: 10px; right: 10px; }
            .notification { width: 100%; max-width: min(360px, calc(100vw - 24px)); max-height: 180px; }
            #worker-progress-widget { position: static; left: auto; bottom: auto; width: auto; margin: 12px 0 18px; }
            
            .data-type-btn {
                width: 60px;
                height: 60px;
                margin: 0 5px;
            }
            
            .tile-content {
                grid-template-columns: 30px 1fr 50px;
            }
            
            .settings-content input[type="text"], 
            .settings-content input[type="date"],
            .add-user-content input[type="text"] {
                width: 100%;
            }
            
            .current-date input[type="date"], 
            .current-date input[type="text"] {
                width: 48%;
            }
        }
    </style>
</head>
<body>
    <div class="app-steps">
        <button id="info-button" title="<?php echo htmlspecialchars($translations['app_info'] ?? 'App Information'); ?>">
            <i class="fas fa-info-circle"></i>
        </button>
        <button id="settings-button" title="<?php echo htmlspecialchars($translations['settings_tooltip']); ?>">
            <i class="fas fa-cog"></i>
        </button>
        <button id="add-steps-button" title="<?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps_tooltip']); ?>">
            <i class="fas fa-plus"></i>
        </button>
        <button id="excel-button" title="<?php echo htmlspecialchars($translations['excel_view'] ?? 'Excel View'); ?>">
            <i class="fas fa-table"></i>
        </button>
        <div id="user-button" title="<?php echo htmlspecialchars($userName); ?>">
            <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="User">
        </div>
        
        <h1><?php echo htmlspecialchars($translations['steps_counter_title']); ?></h1>
        
        <div class="view-selector">
            <button class="view-btn active" data-view="year"><?php echo htmlspecialchars($translations['year']); ?></button>
            <button class="view-btn" data-view="month"><?php echo htmlspecialchars($translations['month']); ?></button>
            <button class="view-btn" data-view="week"><?php echo htmlspecialchars($translations['week']); ?></button>
        </div>
        
        <div class="data-type-selector">
    <button class="data-type-btn active" data-type="steps">
        <img src="steps.png" alt="<?php echo htmlspecialchars($translations['steps']); ?>">
    </button>
    <button class="data-type-btn" data-type="distance">
        <img src="distance.png" alt="<?php echo htmlspecialchars($translations['distance_km']); ?>">
    </button>
    <button class="data-type-btn" data-type="calories">
        <img src="kkal.png" alt="<?php echo htmlspecialchars($translations['calories']); ?>">
    </button>
</div>
        
        <div class="chart-container">
            <button type="button" class="chart-nav chart-nav-left" id="chart-nav-left" style="display:none;" aria-label="<?php echo htmlspecialchars($translations['chart_prev'] ?? 'Previous'); ?>">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="chart-main">
                <div class="chart-label" id="chart-label"></div>
                <div class="chart-wrapper">
                    <canvas id="steps-chart"></canvas>
                </div>
            </div>
            <button type="button" class="chart-nav chart-nav-right" id="chart-nav-right" style="display:none;" aria-label="<?php echo htmlspecialchars($translations['chart_next'] ?? 'Next'); ?>">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="calendar-container">
            <div class="calendar-header">
                <select id="month-selector"></select>
            </div>
            <div id="calendar"></div>
            <div class="goal-summary" id="goal-summary"></div>
        </div>
        
        <div id="notifications">
            <div id="stats-notification" class="notification" style="display:none;">
                <div class="notification-header">
                    <div class="notification-title">
                        <i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($translations['max_steps']); ?>
                    </div>
                    <button class="close-notification">├Ч</button>
                </div>
                <div class="notification-content" id="stats-content"></div>
            </div>
            <div id="goal-notification" class="notification" style="display:none;">
                <div class="notification-header">
                    <div class="notification-title">
                        <i class="fas fa-trophy"></i> <?php echo htmlspecialchars($translations['weekly_goal']); ?>
                    </div>
                    <button class="close-notification">├Ч</button>
                </div>
                <div class="notification-content" id="goal-content"></div>
            </div>
            <div id="video-recognition-notification" class="notification video-related-notif" style="display:none;">
                <div class="notification-header">
                    <div class="notification-title" id="video-recognition-notif-title">
                        <i class="fas fa-film"></i> <?php echo htmlspecialchars($translations['video_notif_title'] ?? 'Video analysis'); ?>
                    </div>
                    <button type="button" class="close-notification" id="video-recognition-notif-close" title="<?php echo htmlspecialchars($translations['video_notif_snooze_hint'] ?? 'Hide for 12 hours'); ?>" aria-label="<?php echo htmlspecialchars($translations['video_notif_snooze_hint'] ?? 'Close'); ?>">├Ч</button>
                </div>
                <div class="notification-content" id="video-recognition-notif-body"><?php echo htmlspecialchars($translations['video_notif_body'] ?? ''); ?></div>
                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end;">
                    <button type="button" class="excel-btn" id="video-recognition-notif-open" style="background: var(--primary-color); color: #fff; padding: 8px 14px; font-size: 13px;"><?php echo htmlspecialchars($translations['video_notif_open'] ?? 'Open'); ?></button>
                </div>
            </div>
        </div>
        <div id="worker-progress-widget"></div>
        
        <!-- Info Modal -->
        <div id="info-modal" style="display:none;">
            <div class="info-content">
                <button class="modal-close">├Ч</button>
                <h2><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($translations['app_info'] ?? 'Application Information'); ?></h2>
                
                <div class="info-section">
                    <h3><i class="fas fa-walking"></i> <?php echo htmlspecialchars($translations['main_features'] ?? 'Main Features'); ?></h3>
                    <ul>
                        <li><?php echo htmlspecialchars($translations['feature_steps_tracking'] ?? 'Track your daily steps with detailed statistics'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_visualization'] ?? 'Visualize data with interactive charts (bar and line charts)'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_calendar_view'] ?? 'Calendar view with goal achievement indicators'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_multiple_views'] ?? 'Multiple view modes: Year, Month, and Week'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_data_types'] ?? 'Different data types: Steps, Distance, and Calories'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_excel_editor'] ?? 'Excel-like table editor for managing your data'); ?></li>
                        <li><?php echo htmlspecialchars($translations['feature_backup_system'] ?? 'Automatic backup system with restore functionality'); ?></li>
                    </ul>
                </div>
                
                <div class="info-section">
                    <h3><i class="fas fa-keyboard"></i> <?php echo htmlspecialchars($translations['hotkeys'] ?? 'Hotkeys'); ?></h3>
                    <ul>
                        <li><?php echo htmlspecialchars($translations['hotkey_enter'] ?? 'Press Enter to save edited cell'); ?></li>
                        <li><?php echo htmlspecialchars($translations['hotkey_escape'] ?? 'Press Escape to cancel editing'); ?></li>
                        <li><?php echo htmlspecialchars($translations['hotkey_delete'] ?? 'Press Delete to clear selected cells'); ?></li>
                        <li><?php echo htmlspecialchars($translations['hotkey_ctrl_a'] ?? 'Ctrl+A to select all cells in Excel view'); ?></li>
                        <li><?php echo htmlspecialchars($translations['hotkey_ctrl_s'] ?? 'Ctrl+S to save changes in Excel view'); ?></li>
                        <li><?php echo htmlspecialchars($translations['hotkey_shift_select'] ?? 'Hold Shift to select multiple cells'); ?></li>
                    </ul>
                </div>
                
                <div class="info-section">
                    <h3><i class="fas fa-table"></i> <?php echo htmlspecialchars($translations['excel_features'] ?? 'Excel View Features'); ?></h3>
                    <ul>
                        <li><?php echo htmlspecialchars($translations['excel_edit'] ?? 'Double-click any cell to edit its value'); ?></li>
                        <li><?php echo htmlspecialchars($translations['excel_select'] ?? 'Click and drag to select multiple cells'); ?></li>
                        <li><?php echo htmlspecialchars($translations['excel_manage'] ?? 'Manage backups directly from the Excel view'); ?></li>
                        <li><?php echo htmlspecialchars($translations['excel_restore'] ?? 'Restore data from any backup point'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Settings Modal -->
        <div id="settings-modal" style="display:none;">
            <div class="settings-content">
                <button class="modal-close">├Ч</button>
                <h2><i class="fas fa-cog"></i> <?php echo htmlspecialchars($translations['settings_title']); ?></h2>
                <div class="settings-tab-bar" role="tablist">
                    <button type="button" class="settings-tab active" data-settings-tab="profile" role="tab" aria-selected="true">
                        <i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($translations['settings_tab_profile'] ?? $translations['settings_title']); ?>
                    </button>
                    <button type="button" class="settings-tab" data-settings-tab="chart" role="tab" aria-selected="false">
                        <i class="fas fa-chart-area"></i> <?php echo htmlspecialchars($translations['settings_tab_chart'] ?? 'Chart'); ?>
                    </button>
                </div>
                <div id="settings-panel-profile" class="settings-tab-panel active" role="tabpanel">
                    <form id="settings-form" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="settings" value="1">
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['goal_steps']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-walking"></i></span>
                                <input type="text" name="goal_steps" value="<?php echo $userSettings['goal_steps'] / 1000; ?>" pattern="[0-9.,]*" inputmode="decimal">
                                <span><?php echo htmlspecialchars($translations['thousands'] ?? '╤В╤Л╤Б.'); ?></span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['height']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-ruler-vertical"></i></span>
                                <input type="text" name="height" value="<?php echo $userSettings['height']; ?>" pattern="\d*" inputmode="numeric">
                                <span>cm</span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['weight']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-weight"></i></span>
                                <input type="text" name="weight" value="<?php echo $userSettings['weight']; ?>" pattern="\d*" inputmode="numeric">
                                <span>kg</span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['stride_length']); ?></div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-ruler"></i></span>
                                <input type="text" name="stride_length" value="<?php echo $userSettings['stride_length']; ?>" pattern="\d*" inputmode="numeric">
                                <span>cm</span>
                            </div>
                        </div>
                        <div class="settings-tile">
                            <div class="tile-title"><?php echo htmlspecialchars($translations['age']); ?> (<?php echo $userSettings['age']; ?>)</div>
                            <hr>
                            <div class="tile-content">
                                <span class="tile-icon"><i class="fas fa-birthday-cake"></i></span>
                                <input type="date" name="birth_date" value="<?php echo $userSettings['birth_date']; ?>">
                                <span></span>
                            </div>
                        </div>
                        <div class="save-button-container">
                            <button type="submit" class="save-button">
                                <i class="fas fa-save"></i> <?php echo htmlspecialchars($translations['save']); ?>
                            </button>
                        </div>
                    </form>
                </div>
                <div id="settings-panel-chart" class="settings-tab-panel" role="tabpanel">
                    <p class="settings-chart-type-intro"><?php echo htmlspecialchars($translations['settings_chart_type_hint'] ?? 'Choose how the main chart is drawn.'); ?></p>
                    <div class="settings-chart-type-group">
                        <button type="button" class="chart-type-btn active" data-type="bar">
                            <i class="fas fa-chart-bar"></i>
                            <span><?php echo htmlspecialchars($translations['bar_chart'] ?? 'Bar Chart'); ?></span>
                        </button>
                        <button type="button" class="chart-type-btn" data-type="line">
                            <i class="fas fa-chart-line"></i>
                            <span><?php echo htmlspecialchars($translations['linear_chart'] ?? 'Linear Chart'); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="add-steps-modal" style="display:none;">
            <div class="add-steps-content add-steps-merged">
                <button class="modal-close" type="button" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">├Ч</button>
                <h2 class="add-steps-main-title"><i class="fas fa-plus"></i> <?php echo htmlspecialchars($translations['add_action_tooltip'] ?? $translations['add_steps']); ?></h2>
                <div class="add-tab-bar" role="tablist">
                    <button type="button" class="add-tab active" data-add-tab="manual" role="tab" aria-selected="true">
                        <i class="fas fa-keyboard"></i> <?php echo htmlspecialchars($translations['add_tab_manual'] ?? 'Manual'); ?>
                    </button>
                    <button type="button" class="add-tab" data-add-tab="video" role="tab" aria-selected="false">
                        <i class="fas fa-video"></i> <?php echo htmlspecialchars($translations['add_tab_video'] ?? 'Video'); ?>
                    </button>
                    <button type="button" class="add-tab" data-add-tab="takeout" role="tab" aria-selected="false">
                        <i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($translations['add_tab_fit'] ?? 'Takeout'); ?>
                    </button>
                </div>
                <div id="add-panel-manual" class="add-tab-panel active" role="tabpanel">
                    <div class="adjacent-dates" id="prev-dates"></div>
                    <div class="current-date">
                        <input type="date" id="selected-date-input">
                        <input type="text" id="steps-input" inputmode="numeric" placeholder="<?php echo htmlspecialchars($translations['add_steps']); ?>" autocomplete="off">
                    </div>
                    <div class="adjacent-dates" id="next-dates"></div>
                    <div class="save-button-container">
                        <button id="add-steps-submit" class="save-button">
                            <i class="fas fa-save"></i> <?php echo htmlspecialchars($translations['add_steps']); ?>
                        </button>
                    </div>
                    <form id="add-steps-form" method="post" style="display:none;">
                        <input type="hidden" name="date" id="selected-date">
                        <input type="hidden" name="steps" id="selected-steps">
                        <input type="hidden" name="add_steps" value="1">
                    </form>
                </div>
                <div id="add-panel-video" class="add-tab-panel" role="tabpanel">
                    <div class="video-analysis-content">
                        <div class="va-head">
                            <h2><i class="fas fa-film"></i> <?php echo htmlspecialchars($translations['video_analysis'] ?? 'Video Analysis'); ?></h2>
                            <p class="va-sub"><?php echo htmlspecialchars($translations['video_analysis_hint'] ?? ''); ?></p>
                        </div>
                        <div class="video-inline-scroll">
                            <div class="va-grid" id="va-form-box">
                                <div class="va-steps" id="va-steps">
                                    <button type="button" class="va-step-dot is-active" data-step-target="1">1</button>
                                    <span class="va-step-line"></span>
                                    <button type="button" class="va-step-dot" data-step-target="2" disabled>2</button>
                                    <span class="va-step-line"></span>
                                    <button type="button" class="va-step-dot" data-step-target="3" disabled>3</button>
                                </div>
                                <section class="va-segment va-segment-hidable" data-step-section="1" aria-label="<?php echo htmlspecialchars($translations['video_section_file'] ?? ''); ?>">
                                    <div class="va-segment-h"><i class="fas fa-file-video"></i> <?php echo htmlspecialchars($translations['video_section_file'] ?? 'Source'); ?></div>
                                    <div class="va-field">
                                        <label for="va-file"><?php echo htmlspecialchars($translations['video_file_label'] ?? 'Video file'); ?></label>
                                        <input type="file" id="va-file" accept="video/*">
                                        <div id="va-file-load-wrap" aria-hidden="true">
                                            <p id="va-file-load-label"></p>
                                            <div id="va-file-load-track"><div id="va-file-load-fill"></div></div>
                                        </div>
                                    </div>
                                </section>
                                <section class="va-segment va-segment-hidable" data-step-section="2" style="display:none;" aria-label="<?php echo htmlspecialchars($translations['video_section_options'] ?? ''); ?>">
                                    <div class="va-segment-h"><i class="fas fa-language"></i> <?php echo htmlspecialchars($translations['video_section_options'] ?? 'Recognition'); ?></div>
                                    <div class="va-field">
                                        <label for="va-lang"><?php echo htmlspecialchars($translations['video_ocr_lang'] ?? 'OCR language'); ?></label>
                                        <select id="va-lang">
                                            <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="ru" <?php echo $language === 'ru' ? 'selected' : ''; ?>>╨а╤Г╤Б╤Б╨║╨╕╨╣</option>
                                            <option value="uk" <?php echo $language === 'uk' ? 'selected' : ''; ?>>╨г╨║╤А╨░╤Ч╨╜╤Б╤М╨║╨░</option>
                                            <option value="be" <?php echo $language === 'be' ? 'selected' : ''; ?>>╨С╨╡╨╗╨░╤А╤Г╤Б╨║╨░╤П</option>
                                        </select>
                                    </div>
                                    <div class="va-field">
                                        <label for="va-year-select"><?php echo htmlspecialchars($translations['video_year_label'] ?? 'Year'); ?></label>
                                        <select id="va-year-select">
                                            <option value="-">-</option>
                                            <?php for ($yy = (int)date('Y'); $yy >= 2000; $yy--): ?>
                                                <option value="<?php echo $yy; ?>"><?php echo $yy; ?></option>
                                            <?php endfor; ?>
                                            <option value="manual"><?php echo htmlspecialchars($translations['manual'] ?? 'Manual'); ?></option>
                                        </select>
                                        <input type="text" id="va-year-manual" placeholder="<?php echo htmlspecialchars($translations['video_year_label'] ?? 'Year'); ?>" autocomplete="off" inputmode="numeric" style="display:none;margin-top:8px;">
                                    </div>
                                </section>
                                <section class="va-segment va-segment-hidable" data-step-section="3" style="display:none;" aria-label="<?php echo htmlspecialchars($translations['video_section_actions'] ?? ''); ?>">
                                    <div class="va-segment-h"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($translations['video_section_actions'] ?? 'Actions'); ?></div>
                                    <div class="video-btn-row">
                                        <button type="button" class="excel-btn" id="va-crop-btn" style="display:none;" disabled><?php echo htmlspecialchars($translations['video_crop_btn'] ?? 'Crop frame'); ?></button>
                                        <button type="button" class="excel-btn" id="va-start" style="background:var(--primary-color);color:#fff;" disabled><?php echo htmlspecialchars($translations['video_start_btn'] ?? 'Start analysis'); ?></button>
                                    </div>
                                    <input type="hidden" id="va-crop-x" value="">
                                    <input type="hidden" id="va-crop-y" value="">
                                    <input type="hidden" id="va-crop-w" value="">
                                    <input type="hidden" id="va-crop-h" value="">
                                </section>
                                <section class="va-segment" id="va-job-panel" style="display:none;" aria-live="polite" aria-label="<?php echo htmlspecialchars($translations['video_section_progress'] ?? ''); ?>">
                                    <p id="va-remote-hint" style="display:none;font-size:13px;opacity:0.92;line-height:1.4;margin:0 0 10px;padding:8px 10px;border-radius:6px;border:1px solid var(--border-color);background:rgba(0,123,255,0.08);text-align:left;"><?php echo htmlspecialchars($translations['video_remote_hint'] ?? 'Analysis is running on the server. You can close this app on another device; progress updates here.'); ?></p>
                                    <div class="va-segment-h"><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($translations['video_section_progress'] ?? 'Status'); ?></div>
                                    <p id="va-msg">тАФ</p>
                                    <div id="va-progress-bar"><div id="va-progress-fill"></div></div>
                                    <p id="va-meta"></p>
                                    <div class="video-btn-row"><button type="button" class="excel-btn worker-stop-btn" id="va-stop"><?php echo htmlspecialchars($translations['stop'] ?? '╨б╤В╨╛╨┐'); ?></button></div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="add-panel-takeout" class="add-tab-panel" role="tabpanel">
                    <div class="video-analysis-content">
                        <div class="va-head">
                            <h2><i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($translations['add_tab_fit'] ?? 'Takeout'); ?></h2>
                            <p class="va-sub takeout-hint"><?php echo htmlspecialchars($translations['takeout_hint'] ?? ''); ?></p>
                        </div>
                        <div class="video-inline-scroll">
                            <div class="va-grid">
                                <div class="va-steps" id="ft-steps">
                                    <button type="button" class="va-step-dot is-active" data-step-target="1">1</button>
                                    <span class="va-step-line"></span>
                                    <button type="button" class="va-step-dot" data-step-target="2" disabled>2</button>
                                    <span class="va-step-line"></span>
                                    <button type="button" class="va-step-dot" data-step-target="3" disabled>3</button>
                                </div>
                                <section class="va-segment" data-step-section="1">
                                    <div class="va-segment-h"><i class="fas fa-file-zipper"></i> <?php echo htmlspecialchars($translations['takeout_zip_section'] ?? 'ZIP archive'); ?></div>
                                    <div class="va-field">
                                        <label for="ft-zip"><?php echo htmlspecialchars($translations['takeout_zip_file_label'] ?? 'Archive file'); ?> (.zip)</label>
                                        <input type="file" id="ft-zip" accept=".zip,application/zip">
                                    </div>
                                </section>
                                <section class="va-segment" data-step-section="2" style="display:none;">
                                    <div class="va-segment-h"><i class="fas fa-language"></i> <?php echo htmlspecialchars($translations['video_section_options'] ?? 'Recognition'); ?></div>
                                    <div class="va-field">
                                        <label for="ft-lang"><?php echo htmlspecialchars($translations['video_ocr_lang'] ?? 'OCR language'); ?></label>
                                        <select id="ft-lang">
                                            <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="ru" <?php echo $language === 'ru' ? 'selected' : ''; ?>>╨а╤Г╤Б╤Б╨║╨╕╨╣</option>
                                            <option value="uk" <?php echo $language === 'uk' ? 'selected' : ''; ?>>╨г╨║╤А╨░╤Ч╨╜╤Б╤М╨║╨░</option>
                                            <option value="be" <?php echo $language === 'be' ? 'selected' : ''; ?>>╨С╨╡╨╗╨░╤А╤Г╤Б╨║╨░╤П</option>
                                        </select>
                                    </div>
                                    <div class="va-field">
                                        <label for="ft-year"><?php echo htmlspecialchars($translations['video_year_label'] ?? 'Year'); ?></label>
                                        <input type="text" id="ft-year" placeholder="-" value="-" autocomplete="off">
                                    </div>
                                </section>
                                <section class="va-segment" data-step-section="3" style="display:none;">
                                    <div class="va-segment-h"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($translations['video_section_actions'] ?? 'Actions'); ?></div>
                                    <div class="video-btn-row">
                                        <button type="button" class="excel-btn" id="ft-start" style="background:var(--primary-color);color:#fff;" disabled><?php echo htmlspecialchars($translations['takeout_start'] ?? 'Import'); ?></button>
                                    </div>
                                </section>
                                <section class="va-segment" id="ft-job-panel" style="display:none;" aria-live="polite">
                                    <div class="va-segment-h"><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($translations['video_section_progress'] ?? 'Status'); ?></div>
                                    <p id="ft-msg">тАФ</p>
                                    <div id="ft-progress-bar"><div id="ft-progress-fill"></div></div>
                                    <p id="ft-meta"></p>
                                    <div class="video-btn-row"><button type="button" class="excel-btn worker-stop-btn" id="ft-stop"><?php echo htmlspecialchars($translations['stop'] ?? '╨б╤В╨╛╨┐'); ?></button></div>
                                </section>
                                <section class="va-segment takeout-guide-segment is-collapsed" id="takeout-guide-segment">
                                    <div class="takeout-guide-head">
                                        <span><i class="fas fa-map-signs"></i> <?php echo htmlspecialchars($translations['takeout_guide_title'] ?? 'How to create a Google Takeout archive'); ?></span>
                                        <button type="button" class="takeout-guide-toggle" id="takeout-guide-toggle" aria-expanded="false" title="<?php echo htmlspecialchars($translations['show'] ?? 'Show'); ?>"><i class="fas fa-chevron-down"></i></button>
                                    </div>
                                    <div class="takeout-guide-body">
                                        <iframe
                                            id="takeout-guide-frame"
                                            loading="lazy"
                                            title="<?php echo htmlspecialchars($translations['takeout_guide_iframe_title'] ?? 'Takeout instruction'); ?>"
                                            src="instruction/takeout_guide.html?lang=<?php echo urlencode($language); ?>&theme=<?php echo urlencode($theme); ?>&color=<?php echo urlencode($color); ?>"
                                        ></iframe>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="video-crop-modal" style="display:none;">
            <div class="video-crop-inner" style="text-align:center;">
                <h3 style="margin-top:0;"><?php echo htmlspecialchars($translations['video_crop_btn'] ?? 'Crop frame'); ?></h3>
                <div style="max-height:70vh;overflow:hidden;"><img id="video-crop-img" src="" alt=""></div>
                <div style="margin-top:18px;">
                    <button type="button" class="excel-btn" id="va-crop-ok" style="background:var(--primary-color);color:#fff;">OK</button>
                    <button type="button" class="excel-btn" id="va-crop-cancel" style="background:#6c757d;color:#fff;"><?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Excel Modal -->
        <div id="excel-modal" style="display:none;">
            <div class="excel-content">
                <button class="modal-close">├Ч</button>
                <div class="excel-modal-head">
                    <button type="button" id="excel-hotkeys-open" class="excel-head-side-left" title="<?php echo htmlspecialchars($translations['hotkeys'] ?? 'Hotkeys'); ?>" aria-label="<?php echo htmlspecialchars($translations['hotkeys'] ?? 'Hotkeys'); ?>"><i class="fas fa-info-circle"></i></button>
                    <h2><i class="fas fa-table"></i> <?php echo htmlspecialchars($translations['excel_view'] ?? 'Excel View'); ?></h2>
                    <span class="excel-head-spacer" aria-hidden="true"></span>
                </div>
                <div class="excel-search-container">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="search-steps" placeholder="<?php echo htmlspecialchars($translations['search_steps'] ?? 'Search steps...'); ?>" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--modal-bg); color: var(--text-color); width: 150px;">
                            <select id="search-operator" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--modal-bg); color: var(--text-color);">
                                <option value="="><?php echo htmlspecialchars($translations['equals'] ?? '='); ?></option>
                                <option value=">">></option>
                                <option value="<"><</option>
                                <option value=">=">>=</option>
                                <option value="<="><=</option>
                                <option value="range"><?php echo htmlspecialchars($translations['range'] ?? 'Range'); ?></option>
                            </select>
                            <input type="text" id="search-steps-to" placeholder="<?php echo htmlspecialchars($translations['to'] ?? 'to'); ?>" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--modal-bg); color: var(--text-color); width: 150px; display: none;">
                            <input type="date" id="search-date-from" title="<?php echo htmlspecialchars($translations['from'] ?? 'From'); ?>" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--modal-bg); color: var(--text-color);">
                            <input type="date" id="search-date-to" title="<?php echo htmlspecialchars($translations['to'] ?? 'To'); ?>" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--modal-bg); color: var(--text-color);">
                            <button id="search-btn" class="excel-btn" style="padding: 8px 15px;">
                                <i class="fas fa-search"></i> <?php echo htmlspecialchars($translations['search'] ?? 'Search'); ?>
                            </button>
                            <button id="clear-search" class="excel-btn" style="padding: 8px 15px; background: #6c757d;">
                                <i class="fas fa-times"></i> <?php echo htmlspecialchars($translations['clear'] ?? 'Clear'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="excel-table-stack">
                    <div class="excel-table-container" id="excel-table-container">
                    </div>
                    <div class="excel-table-pagination-bar" id="excel-table-pagination"></div>
                </div>
                <div class="excel-actions">
                    <button class="excel-btn delete" id="delete-selected">
                        <i class="fas fa-trash"></i> <?php echo htmlspecialchars($translations['delete_selected'] ?? 'Delete Selected'); ?>
                    </button>
                    <button class="excel-btn create-backup" id="create-backup" style="background: #17a2b8;">
                        <i class="fas fa-plus-circle"></i> <?php echo htmlspecialchars($translations['create_backup'] ?? 'Create Backup'); ?>
                    </button>
                    <button class="excel-btn restore" id="restore-backup">
                        <i class="fas fa-history"></i> <?php echo htmlspecialchars($translations['restore_backup'] ?? 'Restore from Backup'); ?>
                    </button>
                    <button class="excel-btn manage-backups" id="manage-backups">
                        <i class="fas fa-database"></i> <?php echo htmlspecialchars($translations['manage_backups'] ?? 'Manage Backups'); ?>
                    </button>
                    <button class="excel-btn download" id="download-backup">
                        <i class="fas fa-download"></i> <?php echo htmlspecialchars($translations['download_backup'] ?? 'Download Backup'); ?>
                    </button>
                </div>
            </div>
        </div>
        <div id="excel-hotkeys-modal" style="display:none;">
            <div class="excel-hotkeys-inner">
                <button class="modal-close">├Ч</button>
                <h2><i class="fas fa-keyboard"></i> <?php echo htmlspecialchars($translations['hotkeys'] ?? 'Hotkeys'); ?></h2>
                <div class="excel-hotkeys-info">
                    <strong><?php echo htmlspecialchars($translations['navigation'] ?? 'Navigation:'); ?></strong>
                    <span class="hotkey">тЖСтЖУтЖРтЖТ</span> - <?php echo htmlspecialchars($translations['move_cells'] ?? 'Move between cells'); ?>
                    <span class="hotkey">Enter</span> - <?php echo htmlspecialchars($translations['save_edit'] ?? 'Save edit'); ?>
                    <span class="hotkey">Esc</span> - <?php echo htmlspecialchars($translations['cancel_edit'] ?? 'Cancel edit'); ?>
                    <span class="hotkey">Ctrl+A</span> - <?php echo htmlspecialchars($translations['select_all'] ?? 'Select all'); ?>
                    <span class="hotkey">Ctrl+S</span> - <?php echo htmlspecialchars($translations['save_changes'] ?? 'Save changes'); ?>
                    <span class="hotkey">Delete</span> - <?php echo htmlspecialchars($translations['clear_selected'] ?? 'Clear selected'); ?>
                </div>
            </div>
        </div>
        
        <!-- Backups Modal -->
        <div id="backups-modal" style="display:none;">
            <div class="backups-content">
                <button class="modal-close">├Ч</button>
                <h2><i class="fas fa-database"></i> <?php echo htmlspecialchars($translations['manage_backups'] ?? 'Manage Backups'); ?></h2>
                
                <div class="backups-list" id="backups-list">
                    <!-- Backups will be loaded here -->
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <button id="refresh-backups" class="excel-btn">
                        <i class="fas fa-sync"></i> <?php echo htmlspecialchars($translations['refresh_backups'] ?? 'Refresh List'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Alert Modal -->
        <div id="alert-modal" style="display:none;">
            <div class="alert-content">
                <button class="modal-close">├Ч</button>
                <div class="alert-message" id="alert-message"></div>
                <div class="alert-buttons">
                    <button class="alert-btn ok" id="alert-ok">OK</button>
                    <button class="alert-btn extra" id="alert-extra" style="display:none;"></button>
                    <button class="alert-btn cancel" id="alert-cancel" style="display:none;"><?php echo htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
                </div>
            </div>
        </div>
        <div id="video-confirm-modal" style="display:none;">
            <div class="video-confirm-inner excel-content">
                <button class="modal-close" type="button" id="video-confirm-close-x">├Ч</button>
                <div style="display:flex;align-items:center;justify-content:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:4px;max-width:100%;padding-right:48px;box-sizing:border-box;">
                    <button type="button" id="video-confirm-gaps-btn" class="excel-btn" style="display:none;flex-shrink:0;padding:8px 12px;background:#ffc107;color:#212529;align-self:flex-start;" title="<?php echo htmlspecialchars($translations['video_gaps_title'] ?? 'Missing days in range'); ?>" aria-label="<?php echo htmlspecialchars($translations['video_gaps_title'] ?? 'Gaps'); ?>"><i class="fas fa-exclamation-triangle"></i></button>
                    <h2 id="video-confirm-title" style="margin:0;flex:1;min-width:0;"><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($translations['video_confirm_title'] ?? 'Recognized steps'); ?></h2>
                </div>
                <p id="video-confirm-sub" style="text-align:left;font-size:14px;opacity:0.9;margin-top:0;"></p>
                <div id="video-confirm-chart-wrap" style="width:100%;height:280px;max-width:100%;margin:8px 0 12px;display:none;position:relative;">
                    <canvas id="video-confirm-chart"></canvas>
                </div>
                <div class="excel-table-container" id="video-confirm-table-wrap" style="max-height:45vh;overflow:auto;"></div>
                <div style="margin-top:16px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                    <button type="button" class="excel-btn" id="video-confirm-import" style="background:var(--primary-color);color:#fff;"><?php echo htmlspecialchars($translations['video_add_to_app'] ?? 'Add to app'); ?></button>
                    <button type="button" class="excel-btn" id="video-confirm-skip" style="background:#6c757d;color:#fff;"><?php echo htmlspecialchars($translations['video_discard'] ?? 'Do not add'); ?></button>
                </div>
            </div>
        </div>
        <div id="video-confirm-gaps-modal" style="display:none;">
            <div class="video-confirm-gaps-inner excel-content" style="max-width:520px;">
                <button class="modal-close" type="button" id="video-confirm-gaps-close-x">├Ч</button>
                <h2 style="margin-top:0;"><i class="fas fa-calendar-day"></i> <span id="video-confirm-gaps-heading"><?php echo htmlspecialchars($translations['video_gaps_title'] ?? 'Missing days'); ?></span></h2>
                <p id="video-confirm-gaps-sub" style="text-align:left;font-size:14px;opacity:0.9;"><?php echo htmlspecialchars($translations['video_gaps_sub'] ?? 'Days between the first and last recognized date with no data. Enter steps, then apply.'); ?></p>
                <div id="video-confirm-gaps-body" class="excel-table-container" style="max-height:50vh;overflow:auto;"></div>
                <div style="margin-top:16px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                    <button type="button" class="excel-btn" id="video-confirm-gaps-apply" style="background:var(--primary-color);color:#fff;"><?php echo htmlspecialchars($translations['video_gaps_apply'] ?? 'Add to list'); ?></button>
                </div>
            </div>
        </div>
        <div id="global-image-viewer" aria-hidden="true">
            <button type="button" id="global-image-viewer-close" aria-label="<?php echo htmlspecialchars($translations['close'] ?? 'Close'); ?>">├Ч</button>
            <img id="global-image-viewer-img" src="" alt="">
        </div>
    </div>
    
    <script>
        let stepsData = <?php echo json_encode($stepsData); ?>, 
            userSettings = <?php echo json_encode($userSettings); ?>, 
            currentUser = '<?php echo htmlspecialchars($currentUser); ?>', 
            translations = <?php echo json_encode($translations); ?>;
            
        const localeMap = { 'en': 'en-EN', 'ru': 'ru-RU', 'uk': 'uk-UA', 'be': 'be-BY' }, 
              currentLocale = localeMap['<?php echo $language; ?>'] || 'en-EN';
              
        const chartTypeStorageKey = () => 'stepsChartType_' + String(currentUser || 'u').replace(/[^a-z0-9_\-]/gi, '_');
        let currentView = 'year', currentDataType = 'steps', currentDate = new Date(), chart = null;
        let currentChartType = 'bar';
        try { const _ct = localStorage.getItem(chartTypeStorageKey()); if (_ct === 'bar' || _ct === 'line') currentChartType = _ct; } catch (e) {}
        let selectedCells = new Set();
        let editingCell = null;
        let currentRow = 0;
        let currentCol = 0;
        let isEditing = false;
        
        Chart.register(ChartZoom);
        
        // ╨Т╤Б╨┐╨╛╨╝╨╛╨│╨░╤В╨╡╨╗╤М╨╜╤Л╨╡ ╤Д╤Г╨╜╨║╤Ж╨╕╨╕
        const formatNumber = num => num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const formatLocalYMD = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        const localDateFromYMD = s => { const p = String(s).split('-'); if (p.length !== 3) return new Date(NaN); const y = +p[0], m = +p[1], da = +p[2]; if (!y || !m || !da) return new Date(NaN); return new Date(y, m - 1, da); };
        const parseStepsRaw = s => String(s).replace(/\s/g, '');
        const setStepsInputValue = n => { const el = document.getElementById('steps-input'); if (el) { const v = Number(n) || 0; el.value = v > 0 ? formatNumber(v) : ''; } };
        
        const formatDate = (date, mode = 'day', lang = currentLocale) => 
            mode === 'month' ? date.toLocaleDateString(lang, { month: 'long' }) : 
            mode === 'day' ? date.toLocaleDateString(lang, { day: '2-digit', month: 'short' }) : 
            date.toLocaleDateString(lang, { year: 'numeric' });
        const mondayWeekOffset = d => (d.getDay() + 6) % 7;
        const getChartLabel = (view, date, lang = currentLocale) => {
    if (view === 'year') {
        return date.toLocaleDateString(lang, { year: 'numeric' });
    } else if (view === 'month') {
        return date.toLocaleDateString(lang, { month: 'long', year: 'numeric' });
    } else if (view === 'week') {
        const weekStart = new Date(date);
        weekStart.setDate(date.getDate() - mondayWeekOffset(date));
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekStart.getDate() + 6);
        return `${formatDate(weekStart, 'day', lang)} - ${formatDate(weekEnd, 'day', lang)}`;
    }
    return '';
};
        
        const getAvailableMonths = (lang = currentLocale) => 
            Array.from(new Set(stepsData.map(entry => { const x = localDateFromYMD(entry.date); return x.getFullYear() + '-' + (x.getMonth() + 1); })))
                .sort()
                .map(month => ({ 
                    value: month, 
                    label: new Date(month + '-01').toLocaleDateString(lang, { month: 'long', year: 'numeric' }) 
                }));
        
        const convertData = (data, dataType) => 
            dataType === 'steps' ? data : 
            dataType === 'distance' ? data.map(steps => (steps * userSettings.stride_length) / 100000) : 
            data.map(steps => steps * 0.04 * (userSettings.weight / 70));
        
        // ╨д╤Г╨╜╨║╤Ж╨╕╤П ╨┤╨╗╤П ╨┐╨╛╨║╨░╨╖╨░ ╨░╨╗╨╡╤А╤В╨░
        function showAlert(message, isConfirm = false, options = {}) {
            return new Promise((resolve) => {
                const modal = document.getElementById('alert-modal');
                const messageEl = document.getElementById('alert-message');
                const okBtn = document.getElementById('alert-ok');
                const extraBtn = document.getElementById('alert-extra');
                const cancelBtn = document.getElementById('alert-cancel');
                const okText = options.okText || 'OK';
                const cancelText = options.cancelText || (translations.cancel || 'Cancel');
                const extraText = options.extraText || '';
                messageEl.textContent = message;
                okBtn.textContent = okText;
                cancelBtn.textContent = cancelText;
                extraBtn.textContent = extraText;
                cancelBtn.style.display = isConfirm ? 'block' : 'none';
                extraBtn.style.display = isConfirm && extraText ? 'block' : 'none';
                
                const openModal = () => {
                    document.body.classList.add('modal-open');
                    modal.style.display = 'flex';
                    setTimeout(() => okBtn.focus(), 0);
                };
                
                const closeModal = (result) => {
                    modal.removeEventListener('keydown', handleKeyDown, true);
                    document.body.classList.remove('modal-open');
                    modal.style.display = 'none';
                    resolve(result);
                };
                const handleKeyDown = (e) => {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        e.stopPropagation();
                        closeModal(false);
                        return;
                    }
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        e.stopPropagation();
                        if (isConfirm) closeModal(true);
                    }
                };
                
                okBtn.onclick = () => closeModal(true);
                cancelBtn.onclick = () => closeModal(false);
                extraBtn.onclick = () => closeModal('extra');
                
                modal.querySelector('.modal-close').onclick = () => closeModal(false);
                modal.addEventListener('keydown', handleKeyDown, true);
                
                openModal();
            });
        }
        
        // ╨Ю╨▒╤А╨░╨▒╨╛╤В╨║╨░ ╨╝╨╛╨┤╨░╨╗╤М╨╜╤Л╤Е ╨╛╨║╨╛╨╜
        function syncChartTypeButtons() {
            document.querySelectorAll('.chart-type-btn').forEach(b => b.classList.toggle('active', b.getAttribute('data-type') === currentChartType));
        }
        function resetSettingsModalTabs() {
            document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.remove('active'));
            const t0 = document.querySelector('.settings-tab[data-settings-tab="profile"]'), p0 = document.getElementById('settings-panel-profile');
            if (t0) t0.classList.add('active');
            if (p0) p0.classList.add('active');
        }
        function resetAddModalTabs() {
            document.querySelectorAll('.add-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.add-tab-panel').forEach(p => p.classList.remove('active'));
            const t0 = document.querySelector('.add-tab[data-add-tab="manual"]'), p0 = document.getElementById('add-panel-manual');
            if (t0) t0.classList.add('active');
            if (p0) p0.classList.add('active');
        }
        function switchAddTab(id) {
            document.querySelectorAll('.add-tab').forEach(t => t.classList.toggle('active', t.getAttribute('data-add-tab') === id));
            document.querySelectorAll('.add-tab-panel').forEach(p => p.classList.toggle('active', p.id === 'add-panel-' + id));
            if (id === 'video' && typeof vaResumeFromAccountState === 'function') vaResumeFromAccountState();
            if (id === 'takeout' && typeof ftResumeFromAccountState === 'function') ftResumeFromAccountState();
        }
        function openModal(modalId, opts) {
            opts = opts || {};
            const modal = document.getElementById(modalId);
            if (modal) {
                document.body.classList.add('modal-open');
                modal.style.display = 'flex';
                if (modalId === 'settings-modal') {
                    resetSettingsModalTabs();
                    syncChartTypeButtons();
                }
                if (modalId === 'add-steps-modal') {
                    if (opts.addTab) switchAddTab(opts.addTab);
                    else resetAddModalTabs();
                }
                if (modalId === 'video-confirm-modal' && !document.hidden) {
                    const vn = document.getElementById('video-recognition-notification');
                    if (vn) vn.style.display = 'none';
                }
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal(modalId);
                    }
                });
            }
        }
        
        function refreshBodyModalLock() {
            const ids = ['info-modal','settings-modal','add-steps-modal','user-modal','add-user-modal','video-confirm-modal','video-confirm-gaps-modal','excel-modal','excel-hotkeys-modal','backups-modal','alert-modal','video-crop-modal'];
            const any = ids.some(id => { const m = document.getElementById(id); return m && window.getComputedStyle(m).display === 'flex'; });
            if (any) document.body.classList.add('modal-open');
            else document.body.classList.remove('modal-open');
        }
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                if (modalId === 'add-steps-modal') {
                    if (typeof vaStopPoll === 'function') vaStopPoll();
                    if (typeof vaResetVideoFileUi === 'function') vaResetVideoFileUi();
                    if (typeof ftStopPoll === 'function') ftStopPoll();
                }
                if (modalId === 'video-confirm-modal') {
                    if (typeof destroyVideoConfirmChart === 'function') destroyVideoConfirmChart();
                    if (typeof closeVideoGapsModal === 'function') closeVideoGapsModal();
                    if (typeof syncVideoNotifications === 'function') setTimeout(function() { syncVideoNotifications(); }, 0);
                }
                refreshBodyModalLock();
            }
        }
        
        // ╨д╤Г╨╜╨║╤Ж╨╕╨╕ ╨┤╨╗╤П ╤А╨░╨▒╨╛╤В╤Л ╤Б ╨│╤А╨░╤Д╨╕╨║╨╛╨╝
        function prepareChartData(view, selectedDate, dataType, lang = currentLocale) {
    let labels = [], data = [], dates = [];
    
    if (view === 'year') {
        const year = selectedDate.getFullYear();
        const yearData = {};
        
        // ╨Ш╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨╕╤А╤Г╨╡╨╝ ╨▓╤Б╨╡ ╨╝╨╡╤Б╤П╤Ж╤Л ╨│╨╛╨┤╨░
        for (let month = 0; month < 12; month++) {
            const yearMonth = `${year}-${month + 1}`;
            yearData[yearMonth] = 0;
            yearData[`${yearMonth}_date`] = `${year}-${String(month + 1).padStart(2, '0')}-01`;
        }
        
        // ╨б╤Г╨╝╨╝╨╕╤А╤Г╨╡╨╝ ╤И╨░╨│╨╕ ╨┐╨╛ ╨╝╨╡╤Б╤П╤Ж╨░╨╝ ╨▓╤Л╨▒╤А╨░╨╜╨╜╨╛╨│╨╛ ╨│╨╛╨┤╨░
        stepsData.forEach(entry => { 
            const date = localDateFromYMD(entry.date);
            if (date.getFullYear() === year) {
                const yearMonth = `${year}-${date.getMonth() + 1}`;
                yearData[yearMonth] = (yearData[yearMonth] || 0) + entry.steps;
            }
        });
        
        // ╨б╨╛╤А╤В╨╕╤А╤Г╨╡╨╝ ╨╝╨╡╤Б╤П╤Ж╤Л
        const sortedMonths = Object.keys(yearData).filter(key => !key.includes('_date')).sort((a, b) => {
            const aMonth = parseInt(a.split('-')[1]);
            const bMonth = parseInt(b.split('-')[1]);
            return aMonth - bMonth;
        });
        
        labels = sortedMonths.map(ym => {
            const [year, month] = ym.split('-');
            return new Date(year, month - 1).toLocaleDateString(lang, { month: 'short' });
        });
        
        data = sortedMonths.map(ym => yearData[ym]);
        dates = sortedMonths.map(ym => yearData[`${ym}_date`]);
        
    } else if (view === 'month') {
        const monthData = stepsData.filter(entry => {
            const entryDate = localDateFromYMD(entry.date);
            return entryDate.getMonth() === selectedDate.getMonth() && 
                entryDate.getFullYear() === selectedDate.getFullYear();
        });
        labels = monthData.map(entry => formatDate(localDateFromYMD(entry.date), 'day', lang));
        data = monthData.map(entry => entry.steps);
        dates = monthData.map(entry => entry.date);
        
    } else if (view === 'week') {
        const weekStart = new Date(selectedDate);
        weekStart.setDate(selectedDate.getDate() - mondayWeekOffset(selectedDate));
        const weekData = [];
        for (let i = 0; i < 7; i++) {
            const currentDay = new Date(weekStart);
            currentDay.setDate(weekStart.getDate() + i);
            const dateStr = formatLocalYMD(currentDay);
            const stepsEntry = stepsData.find(entry => entry.date === dateStr);
            weekData.push({
                date: dateStr,
                steps: stepsEntry ? stepsEntry.steps : 0
            });
        }
        
        labels = weekData.map(entry => formatDate(localDateFromYMD(entry.date), 'day', lang));
        data = weekData.map(entry => entry.steps);
        dates = weekData.map(entry => entry.date);
    }
    
    return { labels, data: convertData(data, dataType), dates };
}
        
        function renderChart(view, selectedDate, dataType, chartType, lang = currentLocale) {
            if (chart) chart.destroy();
            const chartData = prepareChartData(view, selectedDate, dataType, lang);
            if (!chartData.data.length) return;
            
            document.getElementById('chart-label').textContent = getChartLabel(view, selectedDate, lang);
            document.getElementById('chart-nav-left').style.display = 'block';
            document.getElementById('chart-nav-right').style.display = 'block';
            
            const unitLabel = dataType === 'steps' ? translations.steps : dataType === 'distance' ? translations.distance_km : translations.calories;
            const isDarkTheme = '<?php echo $theme === 'dark' ? 'true' : 'false'; ?>' === 'true';
            
            if (!Chart.registry.plugins.get('annotation')) {
                Chart.register(ChartAnnotation);
            }
            const goalSteps = userSettings.goal_steps;
            const dataMax = chartData.data.length ? Math.max.apply(null, chartData.data) : 0;
            const yMaxWhenBelowGoal = view !== 'year' && dataType === 'steps' && dataMax < goalSteps
                ? Math.round(goalSteps * 1.2)
                : undefined;
            chart = new Chart(document.getElementById('steps-chart').getContext('2d'), {
                type: chartType,
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: unitLabel,
                        data: chartData.data,
                        backgroundColor: chartType === 'bar' ? 'rgba(0,123,255,0.5)' : 'transparent',
                        borderColor: 'rgba(0,123,255,1)',
                        borderWidth: chartType === 'bar' ? 1 : 2,
                        fill: chartType === 'line',
                        tension: chartType === 'line' ? 0.4 : 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: 10 },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ...(yMaxWhenBelowGoal != null ? { max: yMaxWhenBelowGoal } : {}),
                            ticks: { 
                                color: isDarkTheme ? '#fff' : '#000',
                                callback: (v) => (dataType === 'steps' ? formatNumber(v) : String(v))
                            },
                            title: { 
                                display: true, 
                                text: unitLabel, 
                                color: isDarkTheme ? '#fff' : '#000' 
                            }
                        },
                        x: {
                            ticks: { color: isDarkTheme ? '#fff' : '#000' },
                            title: { 
                                display: true, 
                                text: translations.time || 'Time', 
                                color: isDarkTheme ? '#fff' : '#000' 
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { 
                            callbacks: { 
                                label: context => `${unitLabel}: ${formatNumber(Math.round(context.raw))}` 
                            } 
                        },
                        zoom: { 
                            pan: { enabled: true, mode: 'x' }, 
                            zoom: { 
                                wheel: { enabled: true }, 
                                pinch: { enabled: true }, 
                                mode: 'x' 
                            } 
                        },
                        ...(view !== 'year' ? {
                            annotation: {
                                annotations: {
                                    goalLine: {
                                        type: 'line',
                                        yMin: userSettings.goal_steps,
                                        yMax: userSettings.goal_steps,
                                        borderColor: 'rgba(194, 0, 42, 0.76)',
                                        borderWidth: 2,
                                        borderDash: [6, 6],
                                        label: {
                                            display: true,
                                            content: (translations.goal_label || 'Goal: ') + formatNumber(userSettings.goal_steps),
                                            position: 'end',
                                            yAdjust: -20,
                                            backgroundColor: 'rgba(167, 0, 36, 0.55)',
                                            font: {
                                                size: 12
                                            }
                                        }
                                    }
                                }
                            }
                        } : {})
                    }
                }
            });
        }
        
        let statsNotifRevealTimer = null, goalNotifRevealTimer = null, statsNotifRevealDone = false, goalNotifRevealDone = false;
        function panelNotifUserKey(kind) {
            return 'stepsPanelN_' + kind + '_' + String(currentUser || 'u').replace(/[^a-z0-9_\-]/gi, '_');
        }
        function getPanelNotifSnoozeUntil(kind) {
            try { return parseInt(localStorage.getItem(panelNotifUserKey(kind)) || '0', 10); } catch (e) { return 0; }
        }
        function snoozePanelNotif12h(kind) {
            try { localStorage.setItem(panelNotifUserKey(kind), String(Date.now() + 12 * 60 * 60 * 1000)); } catch (e) {}
        }
        function cancelStatsNotifTimer() {
            if (statsNotifRevealTimer) { clearTimeout(statsNotifRevealTimer); statsNotifRevealTimer = null; }
        }
        function cancelGoalNotifTimer() {
            if (goalNotifRevealTimer) { clearTimeout(goalNotifRevealTimer); goalNotifRevealTimer = null; }
        }
        function ensureStatsNotifScheduled() {
            const el = document.getElementById('stats-notification');
            if (!el) return;
            if (Date.now() < getPanelNotifSnoozeUntil('stats')) return;
            if (statsNotifRevealDone || statsNotifRevealTimer !== null) return;
            statsNotifRevealTimer = setTimeout(function() {
                statsNotifRevealTimer = null;
                statsNotifRevealDone = true;
                if (Date.now() >= getPanelNotifSnoozeUntil('stats')) el.style.display = 'block';
            }, Math.floor(Math.random() * 60000));
        }
        function ensureGoalNotifScheduled() {
            const el = document.getElementById('goal-notification');
            if (!el) return;
            if (Date.now() < getPanelNotifSnoozeUntil('goal')) return;
            if (goalNotifRevealDone || goalNotifRevealTimer !== null) return;
            goalNotifRevealTimer = setTimeout(function() {
                goalNotifRevealTimer = null;
                goalNotifRevealDone = true;
                if (Date.now() >= getPanelNotifSnoozeUntil('goal')) el.style.display = 'block';
            }, Math.floor(Math.random() * 60000));
        }
        function updateStatsNotification() {
            const steps = stepsData.map(entry => entry.steps), 
                  maxSteps = Math.max(...steps), 
                  maxDistance = (maxSteps * userSettings.stride_length) / 100000;
            const monthData = stepsData.filter(entry => 
                localDateFromYMD(entry.date).getMonth() === currentDate.getMonth() && 
                localDateFromYMD(entry.date).getFullYear() === currentDate.getFullYear()
            );
            const avgSteps = monthData.length ? monthData.reduce((sum, entry) => sum + entry.steps, 0) / monthData.length : 0;
            const el = document.getElementById('stats-notification');
            document.getElementById('stats-content').innerHTML = `
                <div class="notification-item">
                    <span>${translations.max_steps}</span>
                    <span class="notification-value">${formatNumber(maxSteps)}</span>
                </div>
                <div class="notification-item">
                    <span>${translations.max_distance}</span>
                    <span class="notification-value">${maxDistance.toFixed(2)} ${translations.distance_km}</span>
                </div>
                <div class="notification-item">
                    <span>${translations.avg_steps}</span>
                    <span class="notification-value">${formatNumber(Math.round(avgSteps))}</span>
                </div>
                <div class="notification-item">
                    <span>${translations.avg_distance}</span>
                    <span class="notification-value">${(avgSteps * userSettings.stride_length / 100000).toFixed(2)} ${translations.distance_km}</span>
                </div>`;
            if (!el) return;
            if (Date.now() < getPanelNotifSnoozeUntil('stats')) {
                cancelStatsNotifTimer();
                statsNotifRevealDone = false;
                el.style.display = 'none';
                return;
            }
            ensureStatsNotifScheduled();
            if (statsNotifRevealDone) el.style.display = 'block';
        }
        function updateGoalNotification() {
            const today = formatLocalYMD(new Date()), 
                  todayData = stepsData.find(entry => entry.date === today) || { steps: 0 };
            const remainingSteps = Math.max(0, userSettings.goal_steps - todayData.steps);
            const weekStart = new Date();
            weekStart.setDate(weekStart.getDate() - mondayWeekOffset(weekStart));
            const weekData = Array.from({length: 7}, (_, i) => { 
                const date = new Date(weekStart); 
                date.setDate(weekStart.getDate() + i); 
                return (stepsData.find(entry => entry.date === formatLocalYMD(date)) || { steps: 0 }).steps; 
            });
            const weeklyTotal = weekData.reduce((sum, steps) => sum + steps, 0), 
                  remainingWeeklySteps = Math.max(0, userSettings.goal_steps * 7 - weeklyTotal);
            const el = document.getElementById('goal-notification');
            document.getElementById('goal-content').innerHTML = `
                <div class="notification-item">
                    <span>${translations.today_steps}</span>
                    <span class="notification-value">${formatNumber(todayData.steps)}</span>
                </div>
                <div class="notification-item">
                    <span>${translations.remaining_steps}</span>
                    <span class="notification-value">${formatNumber(remainingSteps)}</span>
                </div>
                <div class="notification-item">
                    <span>${translations.this_week}</span>
                    <span class="notification-value">${formatNumber(weeklyTotal)}</span>
                </div>
                <div class="notification-item">
                    <span>${translations.remaining_weekly_steps}</span>
                    <span class="notification-value">${formatNumber(remainingWeeklySteps)}</span>
                </div>`;
            if (!el) return;
            if (Date.now() < getPanelNotifSnoozeUntil('goal')) {
                cancelGoalNotifTimer();
                goalNotifRevealDone = false;
                el.style.display = 'none';
                return;
            }
            ensureGoalNotifScheduled();
            if (goalNotifRevealDone) el.style.display = 'block';
        }
        
        // ╨д╤Г╨╜╨║╤Ж╨╕╨╕ ╨┤╨╗╤П ╨║╨░╨╗╨╡╨╜╨┤╨░╤А╤П
        function updateCalendar(lang = currentLocale) {
            const monthSelector = document.getElementById('month-selector');
            monthSelector.innerHTML = getAvailableMonths(lang)
                .map(month => `<option value="${month.value}" ${currentDate.getMonth() + 1 === parseInt(month.value.split('-')[1]) && currentDate.getFullYear() === parseInt(month.value.split('-')[0]) ? 'selected' : ''}>${month.label}</option>`)
                .join('');
            renderCalendar(monthSelector.value ? new Date(monthSelector.value + '-01') : currentDate, lang);
        }
        
        function renderCalendar(date, lang = currentLocale) {
            const year = date.getFullYear(), 
                  month = date.getMonth(), 
                  firstDay = new Date(year, month, 1), 
                  lastDay = new Date(year, month + 1, 0),
                  daysInMonth = lastDay.getDate(),
                  weekStartOffset = mondayWeekOffset(firstDay),
                  numRows = Math.ceil((weekStartOffset + daysInMonth) / 7),
                  totalCells = numRows * 7;
            let calendarHTML = `<table class="calendar-table"><tr>${(translations.days_of_week || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']).map(day => `<th>${day}</th>`).join('')}</tr>`;
            for (let idx = 0; idx < totalCells; idx++) {
                if (idx % 7 === 0) calendarHTML += '<tr>';
                const dayDate = new Date(year, month, 1 - weekStartOffset + idx),
                      inMonth = dayDate.getMonth() === month && dayDate.getFullYear() === year,
                      domDay = dayDate.getDate(),
                      dateStr = formatLocalYMD(dayDate), 
                      steps = (stepsData.find(entry => entry.date === dateStr) || { steps: 0 }).steps,
                      tipRaw = (translations.steps || 'Steps') + ': ' + formatNumber(steps),
                      tipAttr = tipRaw.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/'/g, '&#39;'),
                      sel = dayDate.toDateString() === new Date().toDateString() ? 'selected' : '',
                      oth = inMonth ? '' : ' calendar-day-other-month';
                calendarHTML += `<td class="${sel}${oth}" data-date="${dateStr}" title="${tipAttr}" tabindex="-1">
    <span class="day-number">${domDay}</span>
    ${steps >= userSettings.goal_steps ? '<img src="goal.png" alt="Goal" class="goal-icon">' : ''}
</td>`;
                if (idx % 7 === 6) calendarHTML += '</tr>';
            }
            calendarHTML += '</table>';
            
            document.getElementById('calendar').innerHTML = calendarHTML;
            
            const monthSteps = stepsData.filter(entry => 
                localDateFromYMD(entry.date).getFullYear() === year && 
                localDateFromYMD(entry.date).getMonth() === month
            );
            
            const totalSteps = monthSteps.reduce((sum, entry) => sum + entry.steps, 0);
            const goalDays = monthSteps.filter(entry => entry.steps >= userSettings.goal_steps).length;
            
            document.getElementById('goal-summary').innerHTML = `
                <div>${translations.total_steps}: ${formatNumber(totalSteps)}</div>
                <div>${translations.goal_days}: ${goalDays}</div>`;
        }
        
        // ╨д╤Г╨╜╨║╤Ж╨╕╨╕ ╨┤╨╗╤П ╨╝╨╛╨┤╨░╨╗╤М╨╜╨╛╨│╨╛ ╨╛╨║╨╜╨░ ╨┤╨╛╨▒╨░╨▓╨╗╨╡╨╜╨╕╤П ╤И╨░╨│╨╛╨▓
        function applyManualDate(date, lang = currentLocale, direction = 'none') {
            document.getElementById('selected-date-input').value = date;
            setStepsInputValue((stepsData.find(entry => entry.date === date) || { steps: 0 }).steps);
            updateAdjacentDates(date, lang, direction);
        }
        function handleDateWheelScroll(e) {
            e.preventDefault();
            const dateInput = document.getElementById('selected-date-input'), 
                  cur = localDateFromYMD(dateInput.value);
            if (isNaN(cur.getTime())) return;
            const direction = e.deltaY > 0 ? 'next' : 'prev';
            cur.setDate(cur.getDate() + (direction === 'next' ? 1 : -1));
            const newDateStr = formatLocalYMD(cur);
            applyManualDate(newDateStr, currentLocale, direction);
        }
        function updateAdjacentDates(date, lang = currentLocale, direction = 'none') {
            const dateObj = localDateFromYMD(date), 
                  prevDates = [], 
                  nextDates = [];
            if (isNaN(dateObj.getTime())) return;
            for (let i = 1; i <= 3; i++) {
                const prevDate = new Date(dateObj); 
                prevDate.setDate(dateObj.getDate() - i);
                
                const nextDate = new Date(dateObj); 
                nextDate.setDate(dateObj.getDate() + i);
                const prevStr = formatLocalYMD(prevDate), nextStr = formatLocalYMD(nextDate);
                prevDates.push({ 
                    date: prevStr, 
                    steps: (stepsData.find(entry => entry.date === prevStr) || { steps: 0 }).steps 
                });
                
                nextDates.push({ 
                    date: nextStr, 
                    steps: (stepsData.find(entry => entry.date === nextStr) || { steps: 0 }).steps 
                });
            }
            
            document.getElementById('prev-dates').innerHTML = prevDates.reverse()
                .map(item => `<div class="adjacent-date" data-date="${item.date}">
                    <span>${localDateFromYMD(item.date).toLocaleDateString(lang)}</span>
                    <span>${formatNumber(item.steps)}</span>
                </div>`)
                .join('');
                
            document.getElementById('next-dates').innerHTML = nextDates
                .map(item => `<div class="adjacent-date" data-date="${item.date}">
                    <span>${localDateFromYMD(item.date).toLocaleDateString(lang)}</span>
                    <span>${formatNumber(item.steps)}</span>
                </div>`)
                .join('');
                
            document.querySelectorAll('.adjacent-date').forEach(el => {
                el.addEventListener('click', () => {
                    const newDate = el.getAttribute('data-date');
                    const curDate = document.getElementById('selected-date-input').value;
                    const direction = newDate > curDate ? 'next' : 'prev';
                    applyManualDate(newDate, lang, direction);
                });
            });
        }
        
        async function saveSteps() {
            const date = document.getElementById('selected-date-input').value;
            const steps = parseStepsRaw(document.getElementById('steps-input').value);
            if (!date || !/^\d*$/.test(steps)) {
                await showAlert(translations.invalid_date_steps || 'Invalid date or steps');
                return;
            }
            document.getElementById('selected-date').value = date;
            document.getElementById('selected-steps').value = steps;
            
            try {
                const response = await fetch('', { 
                    method: 'POST', 
                    body: new FormData(document.getElementById('add-steps-form')) 
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    stepsData = stepsData.filter(entry => entry.date !== date);
                    stepsData.push({ date, steps: parseInt(steps) });
                    stepsData.sort((a, b) => new Date(a.date) - new Date(b.date));
                    
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                    updateCalendar(currentLocale);
                    updateStatsNotification();
                    updateGoalNotification();
                    setStepsInputValue(0);
                    const nextDateObj = localDateFromYMD(date);
                    nextDateObj.setDate(nextDateObj.getDate() + 1);
                    const newDateStr = formatLocalYMD(nextDateObj);
                    applyManualDate(newDateStr, currentLocale, 'next');
                    document.getElementById('steps-input').focus();
                } else {
                    await showAlert(`${translations.invalid_request || 'Error'}: ${data.message}`);
                }
            } catch (err) {
                await showAlert(`${translations.invalid_request || 'Error'}: ${err.message}`);
            }
        }
        
        // ╨д╤Г╨╜╨║╤Ж╨╕╨╕ ╨┤╨╗╤П Excel ╨╝╨╛╨┤╨░╨╗╤М╨╜╨╛╨│╨╛ ╨╛╨║╨╜╨░
        // тФАтФА ╨в╨░╨▒╨╗╨╕╤Ж╨░ ╤Б ╨┐╨░╨│╨╕╨╜╨░╤Ж╨╕╨╡╨╣ ╨╕ SQL-╤Д╨╕╨╗╤М╤В╤А╨╛╨╝ тФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФАтФА
        let tablePage = 1;
        let tableTotal = 0;
        let tablePageSize = 50;
        let tableFilterOp = '';
        let tableFilterVal = '';
        let tableFilterValTo = '';
        let tableDateFrom = '';
        let tableDateTo = '';

        async function renderExcelTable(page = 1, filterOp = tableFilterOp, filterVal = tableFilterVal, filterValTo = tableFilterValTo, dateFrom = tableDateFrom, dateTo = tableDateTo) {
            tablePage      = page;
            tableFilterOp  = filterOp;
            tableFilterVal = filterVal;
            tableFilterValTo = filterValTo;
            tableDateFrom = dateFrom;
            tableDateTo = dateTo;

            const container = document.getElementById('excel-table-container');
            const pagBar = document.getElementById('excel-table-pagination');
            if (!container) return;
            container.innerHTML = `<div style="text-align:center;padding:20px;">${translations.loading || 'Loading...'}</div>`;
            if (pagBar) pagBar.innerHTML = '';

            try {
                const fd = new FormData();
                fd.append('get_table_page', '1');
                fd.append('page', page);
                if (filterOp && filterVal !== '') {
                    fd.append('filter_op',  filterOp);
                    fd.append('filter_val', filterVal);
                    if (filterOp === 'range' && filterValTo !== '') fd.append('filter_val_to', filterValTo);
                }
                if (dateFrom) fd.append('date_from', dateFrom);
                if (dateTo) fd.append('date_to', dateTo);
                const res  = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status !== 'success') {
                    container.innerHTML = `<p style="color:red">${data.message}</p>`;
                    if (pagBar) pagBar.innerHTML = '';
                    return;
                }

                tableTotal    = data.total;
                tablePageSize = data.page_size;
                const rows    = data.rows;
                const totalPages = Math.ceil(tableTotal / tablePageSize);

                let html = `<table class="excel-table" id="excel-table">
                    <thead><tr>
                        <th>${translations.date || 'Date'}</th>
                        <th>${translations.steps || 'Steps'}</th>
                    </tr></thead><tbody>`;

                rows.forEach((entry, rowIndex) => {
                    const d  = localDateFromYMD(entry.date);
                    const dd = String(d.getDate()).padStart(2,'0');
                    const mm = String(d.getMonth()+1).padStart(2,'0');
                    const yy = String(d.getFullYear()).slice(-2);
                    const fmtDate = `${dd}-${mm}-${yy}`;
                    html += `<tr data-row="${rowIndex}">
                        <td data-row="${rowIndex}" data-col="0" contenteditable="false">${fmtDate}</td>
                        <td data-row="${rowIndex}" data-col="1" contenteditable="false">${formatNumber(entry.steps)}</td>
                    </tr>`;
                });

                html += `</tbody></table>`;
                const pagHtml = `<div class="table-pagination">
                    <button class="page-btn" onclick="renderExcelTable(1)" ${page<=1?'disabled':''}>┬л</button>
                    <button class="page-btn" onclick="renderExcelTable(${page-1})" ${page<=1?'disabled':''}>тА╣</button>
                    <span class="page-info">${translations.page||'Page'} ${page} / ${totalPages} &nbsp; (${tableTotal} ${translations.records||'records'})</span>
                    <button class="page-btn" onclick="renderExcelTable(${page+1})" ${page>=totalPages?'disabled':''}>тА║</button>
                    <button class="page-btn" onclick="renderExcelTable(${totalPages})" ${page>=totalPages?'disabled':''}>┬╗</button>
                </div>`;
                container.innerHTML = html;
                if (pagBar) pagBar.innerHTML = pagHtml;

                const table = document.getElementById('excel-table');
                if (table) { setupExcelTableEvents(table); setupTableNavigation(table); }
            } catch(err) {
                container.innerHTML = `<p style="color:red">${err.message}</p>`;
                if (pagBar) pagBar.innerHTML = '';
            }
        }
        
        function setupExcelTableEvents(table) {
            let isSelecting = false;
            let startCell = null;
            
            table.addEventListener('mousedown', (e) => {
                const cell = e.target.closest('td');
                if (!cell || cell.closest('thead')) return;
                
                const row = parseInt(cell.getAttribute('data-row'));
                const col = parseInt(cell.getAttribute('data-col'));
                currentRow = row;
                currentCol = col;
                
                if (e.shiftKey && startCell) {
                    // ╨Т╤Л╨┤╨╡╨╗╨╡╨╜╨╕╨╡ ╨┤╨╕╨░╨┐╨░╨╖╨╛╨╜╨░
                    selectCellRange(startCell, cell);
                } else {
                    // ╨Ю╨┤╨╕╨╜╨╛╤З╨╜╨╛╨╡ ╨▓╤Л╨┤╨╡╨╗╨╡╨╜╨╕╨╡
                    clearSelection();
                    toggleCellSelection(cell);
                    startCell = cell;
                }
                
                isSelecting = true;
                e.preventDefault();
            });
            
            table.addEventListener('mouseover', (e) => {
                if (!isSelecting) return;
                
                const cell = e.target.closest('td');
                if (cell && startCell) {
                    selectCellRange(startCell, cell);
                }
            });
            
            // ╨Р╨▓╤В╨╛╨╝╨░╤В╨╕╤З╨╡╤Б╨║╨╛╨╡ ╤Б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╨┐╤А╨╕ ╨┐╨╛╤В╨╡╤А╨╡ ╤Д╨╛╨║╤Г╤Б╨░
            table.addEventListener('focusout', (e) => {
                if (editingCell) {
                    finishEditing(true);
                }
            });
            
            // ╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╨┐╤А╨╕ ╨╜╨░╨╢╨░╤В╨╕╨╕ Tab
            table.addEventListener('keydown', (e) => {
                if (e.key === 'Tab' && editingCell) {
                    e.preventDefault();
                    finishEditing(true);
                    
                    // ╨Я╨╡╤А╨╡╤Е╨╛╨┤ ╨║ ╤Б╨╗╨╡╨┤╤Г╤О╤Й╨╡╨╣ ╤П╤З╨╡╨╣╨║╨╡
                    const nextCell = getCell(currentRow, currentCol + 1);
                    if (nextCell) {
                        startEditing(nextCell);
                    }
                }
            });

            document.addEventListener('mouseup', () => {
                isSelecting = false;
            });
            
            // ╨Ф╨▓╨╛╨╣╨╜╨╛╨╣ ╨║╨╗╨╕╨║ ╨┤╨╗╤П ╤А╨╡╨┤╨░╨║╤В╨╕╤А╨╛╨▓╨░╨╜╨╕╤П
            table.addEventListener('dblclick', (e) => {
                const cell = e.target.closest('td');
                if (!cell || cell.closest('thead')) return;
                
                if (editingCell) {
                    finishEditing();
                }
                
                startEditing(cell);
            });
        }
        
        // ╨д╤Г╨╜╨║╤Ж╨╕╤П ╨┤╨╗╤П ╨╜╨░╨▓╨╕╨│╨░╤Ж╨╕╨╕ ╨┐╨╛ ╤В╨░╨▒╨╗╨╕╤Ж╨╡ ╤Б ╨┐╨╛╨╝╨╛╤Й╤М╤О ╤Б╤В╤А╨╡╨╗╨╛╨║
        function setupTableNavigation(table) {
            function getCell(row, col) {
                return table.querySelector(`td[data-row="${row}"][data-col="${col}"]`);
            }
            
            function moveSelection(newRow, newCol) {
                const rows = table.querySelectorAll('tbody tr');
                const maxRow = rows.length - 1;
                const maxCol = 1; // 0 - ╨┤╨░╤В╨░, 1 - ╤И╨░╨│╨╕
                
                // ╨Ю╨│╤А╨░╨╜╨╕╤З╨╕╨▓╨░╨╡╨╝ ╨┤╨╕╨░╨┐╨░╨╖╨╛╨╜
                newRow = Math.max(0, Math.min(newRow, maxRow));
                newCol = Math.max(0, Math.min(newCol, maxCol));
                
                // ╨б╨╜╨╕╨╝╨░╨╡╨╝ ╨▓╤Л╨┤╨╡╨╗╨╡╨╜╨╕╨╡ ╤Б╨╛ ╨▓╤Б╨╡╤Е ╤П╤З╨╡╨╡╨║
                clearSelection();
                
                // ╨Т╤Л╨┤╨╡╨╗╤П╨╡╨╝ ╨╜╨╛╨▓╤Г╤О ╤П╤З╨╡╨╣╨║╤Г
                const newCell = getCell(newRow, newCol);
                if (newCell) {
                    toggleCellSelection(newCell);
                    currentRow = newRow;
                    currentCol = newCol;
                    
                    // ╨Я╤А╨╛╨║╤А╤Г╤З╨╕╨▓╨░╨╡╨╝ ╨║ ╤П╤З╨╡╨╣╨║╨╡ ╨╡╤Б╨╗╨╕ ╨╛╨╜╨░ ╨╜╨╡ ╨▓╨╕╨┤╨╜╨░
                    newCell.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            
            // ╨Ю╨▒╤А╨░╨▒╨╛╤В╨║╨░ ╨│╨╛╤А╤П╤З╨╕╤Е ╨║╨╗╨░╨▓╨╕╤И
            document.addEventListener('keydown', (e) => {
                // ╨Х╤Б╨╗╨╕ ╨╝╤Л ╨▓ ╤А╨╡╨╢╨╕╨╝╨╡ ╤А╨╡╨┤╨░╨║╤В╨╕╤А╨╛╨▓╨░╨╜╨╕╤П, ╨╜╨╡ ╨╛╨▒╤А╨░╨▒╨░╤В╤Л╨▓╨░╨╡╨╝ ╤Б╤В╤А╨╡╨╗╨║╨╕
                if (editingCell || e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') {
                    return;
                }
                
                const modal = document.getElementById('excel-modal');
                if (!modal || modal.style.display !== 'flex') {
                    return;
                }
                
                switch (e.key) {
                    case 'ArrowUp':
                        e.preventDefault();
                        moveSelection(currentRow - 1, currentCol);
                        break;
                    case 'ArrowDown':
                        e.preventDefault();
                        moveSelection(currentRow + 1, currentCol);
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        moveSelection(currentRow, currentCol - 1);
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        moveSelection(currentRow, currentCol + 1);
                        break;
                    case 'Enter':
                        if (!editingCell) {
                            const currentCell = getCell(currentRow, currentCol);
                            if (currentCell) {
                                if (editingCell) {
                                    finishEditing();
                                }
                                startEditing(currentCell);
                            }
                        } else {
                            finishEditing(true);
                        }
                        break;
                    case 'Escape':
                        if (editingCell) {
                            finishEditing(false);
                        }
                        break;
                    case 'Delete':
                        if (selectedCells.size > 0) {
                            deleteSelectedCells();
                        }
                        break;
                    case 'a':
                        if (e.ctrlKey) {
                            e.preventDefault();
                            selectAllCells();
                        }
                        break;
                    case 's':
                        if (e.ctrlKey) {
                            e.preventDefault();
                            updateDataFromTable();
                        }
                        break;
                }
            });
        }
        
        function selectAllCells() {
            const table = document.getElementById('excel-table');
            if (!table) return;
            
            clearSelection();
            
            const cells = table.querySelectorAll('td');
            cells.forEach(cell => {
                cell.classList.add('selected');
                selectedCells.add(cell);
            });
        }
        
        function toggleCellSelection(cell) {
            if (!cell) return;
            
            if (selectedCells.has(cell)) {
                cell.classList.remove('selected');
                selectedCells.delete(cell);
            } else {
                cell.classList.add('selected');
                selectedCells.add(cell);
            }
        }
        
        function selectCellRange(startCell, endCell) {
            if (!startCell || !endCell) return;
            
            clearSelection();
            
            const startRow = parseInt(startCell.getAttribute('data-row'));
            const startCol = parseInt(startCell.getAttribute('data-col'));
            const endRow = parseInt(endCell.getAttribute('data-row'));
            const endCol = parseInt(endCell.getAttribute('data-col'));
            
            const minRow = Math.min(startRow, endRow);
            const maxRow = Math.max(startRow, endRow);
            const minCol = Math.min(startCol, endCol);
            const maxCol = Math.max(startCol, endCol);
            
            const table = document.getElementById('excel-table');
            if (!table) return;
            
            for (let row = minRow; row <= maxRow; row++) {
                for (let col = minCol; col <= maxCol; col++) {
                    const cell = table.querySelector(`td[data-row="${row}"][data-col="${col}"]`);
                    if (cell) {
                        cell.classList.add('selected');
                        selectedCells.add(cell);
                    }
                }
            }
        }
        
        function clearSelection() {
            selectedCells.forEach(cell => {
                cell.classList.remove('selected');
            });
            selectedCells.clear();
        }
        
        function startEditing(cell) {
            if (!cell) return;
            
            // ╨Я╤А╨╛╨▓╨╡╤А╤П╨╡╨╝, ╨╜╨╡ ╤П╨▓╨╗╤П╨╡╤В╤Б╤П ╨╗╨╕ ╤П╤З╨╡╨╣╨║╨░ ╨┤╨░╤В╨╛╨╣ (╨║╨╛╨╗╨╛╨╜╨║╨░ 0)
            const col = parseInt(cell.getAttribute('data-col'));
            if (col === 0) {
                // ╨н╤В╨╛ ╤П╤З╨╡╨╣╨║╨░ ╤Б ╨┤╨░╤В╨╛╨╣ - ╨╖╨░╨┐╤А╨╡╤Й╨░╨╡╨╝ ╤А╨╡╨┤╨░╨║╤В╨╕╤А╨╛╨▓╨░╨╜╨╕╨╡
                showAlert(translations.date_not_editable || 'Date cannot be edited');
                return;
            }
            
            // ╨Ч╨░╨▓╨╡╤А╤И╨░╨╡╨╝ ╨┐╤А╨╡╨┤╤Л╨┤╤Г╤Й╨╡╨╡ ╤А╨╡╨┤╨░╨║╤В╨╕╤А╨╛╨▓╨░╨╜╨╕╨╡, ╨╡╤Б╨╗╨╕ ╨╡╤Б╤В╤М
            if (editingCell) {
                finishEditing(false);
            }
            
            editingCell = cell;
            isEditing = true;
            cell.classList.add('editing');
            cell.setAttribute('contenteditable', 'true');
            
            const displayVal = cell.textContent.trim();
            if (col === 1) {
                const r = parseInt(parseStepsRaw(displayVal), 10) || 0;
                cell.setAttribute('data-original', String(r));
                cell.textContent = String(r);
            } else {
                cell.setAttribute('data-original', displayVal);
            }
            // ╨д╨╛╨║╤Г╤Б╨╕╤А╤Г╨╡╨╝╤Б╤П ╨╕ ╨▓╤Л╨┤╨╡╨╗╤П╨╡╨╝ ╤В╨╡╨║╤Б╤В
            cell.focus();
            
            // ╨Т╤Л╨┤╨╡╨╗╤П╨╡╨╝ ╤В╨╡╨║╤Б╤В
            const range = document.createRange();
            range.selectNodeContents(cell);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
        
        async function updateDataFromTable() {
            // ╨б╨╛╤Е╤А╨░╨╜╤П╨╡╨╝ ╤В╨╛╨╗╤М╨║╨╛ ╤А╨╡╨┤╨░╨║╤В╨╕╤А╤Г╨╡╨╝╤Г╤О ╤П╤З╨╡╨╣╨║╤Г
            if (!editingCell) return false;
            
            const row = parseInt(editingCell.getAttribute('data-row'));
            const col = parseInt(editingCell.getAttribute('data-col'));
            
            // ╨Я╨╛╨╗╤Г╤З╨░╨╡╨╝ ╨▓╤Б╤О ╤Б╤В╤А╨╛╨║╤Г
            const table = document.getElementById('excel-table');
            if (!table) return false;
            
            const rowElement = table.querySelector(`tr[data-row="${row}"]`);
            if (!rowElement) return false;
            
            const cells = rowElement.querySelectorAll('td');
            if (cells.length < 2) return false;
            
            const dateCell = cells[0];
            const stepsCell = cells[1];
            
            const dateStr = dateCell.textContent.trim();
            const newSteps = parseInt(parseStepsRaw(stepsCell.textContent), 10) || 0;
            const originalSteps = parseInt(parseStepsRaw(editingCell.getAttribute('data-original') || '0'), 10) || 0;
            
            // ╨Я╤А╨╛╨▓╨╡╤А╤П╨╡╨╝, ╨╕╨╖╨╝╨╡╨╜╨╕╨╗╨╛╤Б╤М ╨╗╨╕ ╨╖╨╜╨░╤З╨╡╨╜╨╕╨╡
            if (newSteps === originalSteps) {
                console.log('No changes detected');
                return true; // ╨Э╨╕╤З╨╡╨│╨╛ ╨╜╨╡ ╨╕╨╖╨╝╨╡╨╜╨╕╨╗╨╛╤Б╤М, ╨╜╨╛ ╤Н╤В╨╛ ╨╜╨╡ ╨╛╤И╨╕╨▒╨║╨░
            }
            return await persistTableRow(dateStr, newSteps, row);
        }
        async function persistTableRow(dateStr, newSteps, row) {
            if (!/^\d{2}-\d{2}-\d{2}$/.test(dateStr)) {
                await showAlert(translations.invalid_date_format || 'Invalid date format. Use dd-mm-yy format');
                return false;
            }
            if (isNaN(newSteps) || newSteps < 0) {
                await showAlert(translations.invalid_steps || 'Invalid steps value');
                return false;
            }
            try {
                const formData = new FormData();
                formData.append('save_table_data', '1');
                formData.append('date', dateStr);
                formData.append('steps', newSteps);
                formData.append('row_index', row);
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.status !== 'success') {
                    await showAlert(`${translations.save_error || 'Error saving data'}: ${data.message}`);
                    return false;
                }
                updateLocalData(dateStr, newSteps, row);
                return true;
            } catch (err) {
                await showAlert(`${translations.save_error || 'Error saving data'}: ${err.message}`);
                return false;
            }
        }
        
        function updateLocalData(date, steps, rowIndex) {
            // ╨Я╤А╨╡╨╛╨▒╤А╨░╨╖╤Г╨╡╨╝ ╨┤╨░╤В╤Г ╨╕╨╖ dd-mm-yy ╨▓ yyyy-mm-dd ╨┤╨╗╤П ╨┐╨╛╨╕╤Б╨║╨░ ╨▓ stepsData
            const dateParts = date.split('-');
            const day = dateParts[0];
            const month = dateParts[1];
            const year = dateParts[2];
            
            // ╨Ю╨┐╤А╨╡╨┤╨╡╨╗╤П╨╡╨╝ ╨┐╨╛╨╗╨╜╤Л╨╣ ╨│╨╛╨┤
            const fullYear = parseInt(year) < 70 ? `20${year}` : `19${year}`;
            const searchDate = `${fullYear}-${month}-${day}`;
            
            console.log('Updating local data:', { date, searchDate, steps }); // ╨Ю╤В╨╗╨░╨┤╨║╨░
            
            // ╨Э╨░╤Е╨╛╨┤╨╕╨╝ ╨╕ ╨╛╨▒╨╜╨╛╨▓╨╗╤П╨╡╨╝ ╨╖╨░╨┐╨╕╤Б╤М ╨▓ stepsData
            let found = false;
            for (let i = 0; i < stepsData.length; i++) {
                if (stepsData[i].date === searchDate) {
                    console.log('Found existing entry:', stepsData[i]); // ╨Ю╤В╨╗╨░╨┤╨║╨░
                    stepsData[i].steps = steps;
                    found = true;
                    break;
                }
            }
            
            // ╨Х╤Б╨╗╨╕ ╨╜╨╡ ╨╜╨░╤И╨╗╨╕, ╨┤╨╛╨▒╨░╨▓╨╗╤П╨╡╨╝ ╨╜╨╛╨▓╤Г╤О ╨╖╨░╨┐╨╕╤Б╤М
            if (!found) {
                console.log('Adding new entry:', { date: searchDate, steps }); // ╨Ю╤В╨╗╨░╨┤╨║╨░
                stepsData.push({ date: searchDate, steps: steps });
                stepsData.sort((a, b) => new Date(a.date) - new Date(b.date));
            }
            
            // ╨Ю╨▒╨╜╨╛╨▓╨╗╤П╨╡╨╝ ╨│╤А╨░╤Д╨╕╨║ ╨╕ ╨║╨░╨╗╨╡╨╜╨┤╨░╤А╤М
            renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
            updateCalendar(currentLocale);
            updateStatsNotification();
            updateGoalNotification();

            setTimeout(() => {
                const table = document.getElementById('excel-table');
                if (table) {
                    const cell = table.querySelector(`td[data-row="${rowIndex}"][data-col="1"]`);
                    if (cell) {
                        cell.textContent = formatNumber(steps);
                    }
                }
            }, 0);
        }
        
        function finishEditing(save = true) {
    if (!editingCell) return;

    const originalValue = editingCell.getAttribute('data-original');
    const newValue = editingCell.textContent.trim();

    editingCell.classList.remove('editing');
    editingCell.setAttribute('contenteditable', 'false');
    const cellToUpdate = editingCell;
    editingCell = null;
    isEditing = false;

    const colx = parseInt(cellToUpdate.getAttribute('data-col'), 10);
    if (!save) {
        if (colx === 1) {
            cellToUpdate.textContent = formatNumber(parseInt(originalValue || '0', 10) || 0);
        } else {
            cellToUpdate.textContent = originalValue;
        }
        cellToUpdate.removeAttribute('data-original');
    } else if (originalValue !== newValue) {
        const finalNewValue = newValue;
        cellToUpdate.textContent = finalNewValue;
        cellToUpdate.removeAttribute('data-original');
        (async () => {
            editingCell = cellToUpdate;
            editingCell.setAttribute('data-original', originalValue);
            const success = await updateDataFromTable();
            if (!success) {
                if (colx === 1) {
                    cellToUpdate.textContent = formatNumber(parseInt(originalValue || '0', 10) || 0);
                } else {
                    cellToUpdate.textContent = originalValue;
                }
            }
            cellToUpdate.removeAttribute('data-original');
            editingCell = null;
        })();
    } else {
        if (colx === 1) {
            cellToUpdate.textContent = formatNumber(parseInt(newValue, 10) || 0);
        }
        cellToUpdate.removeAttribute('data-original');
    }
}
        
        async function deleteSelectedCells() {
            if (selectedCells.size === 0) return;
            
            const confirm = await showAlert(
                translations.confirm_delete_cells || `Delete ${selectedCells.size} selected cell(s)?`,
                true
            );
            
            if (!confirm) return;
            const targets = Array.from(selectedCells).filter(cell => parseInt(cell.getAttribute('data-col'), 10) === 1);
            if (targets.length === 0) return;
            for (const cell of targets) {
                const row = parseInt(cell.getAttribute('data-row'), 10);
                if (Number.isNaN(row)) continue;
                const rowEl = cell.closest('tr');
                if (!rowEl) continue;
                const dateCell = rowEl.querySelector('td[data-col="0"]');
                if (!dateCell) continue;
                const dateStr = dateCell.textContent.trim();
                const ok = await persistTableRow(dateStr, 0, row);
                if (!ok) return;
                cell.textContent = '0';
            }
            clearSelection();
            await showAlert(translations.cells_cleared || 'Cells cleared successfully');
        }
        
        // ╨д╤Г╨╜╨║╤Ж╨╕╤П ╨┤╨╗╤П ╤Б╨╛╨╖╨┤╨░╨╜╨╕╤П ╨╜╨╛╨▓╨╛╨│╨╛ ╨▒╤Н╨║╨░╨┐╨░
        async function createBackup() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'create_backup=1'
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    await showAlert(data.message || translations.backup_created || 'Backup created successfully');
                    await loadBackups(); // ╨Ю╨▒╨╜╨╛╨▓╨╗╤П╨╡╨╝ ╤Б╨┐╨╕╤Б╨╛╨║ ╨▒╤Н╨║╨░╨┐╨╛╨▓
                } else {
                    await showAlert(`Error: ${data.message}`);
                }
            } catch (err) {
                await showAlert(`Error creating backup: ${err.message}`);
            }
        }
        
        // ╨Я╨╛╨╕╤Б╨║ тАФ SQL-╤Д╨╕╨╗╤М╤В╤А ╤З╨╡╤А╨╡╨╖ ╤Б╨╡╤А╨▓╨╡╤А, ╨┐╨╡╤А╨▓╨░╤П ╤Б╤В╤А╨░╨╜╨╕╤Ж╨░
        async function searchInTable() {
            const val   = document.getElementById('search-steps').value.trim();
            const op    = document.getElementById('search-operator').value;
            const valTo = document.getElementById('search-steps-to').value.trim();
            const dateFrom = document.getElementById('search-date-from').value;
            const dateTo = document.getElementById('search-date-to').value;

            if (!val && op !== 'range' && !dateFrom && !dateTo) {
                await renderExcelTable(1, '', '', '', '', '');
                return;
            }
            await renderExcelTable(1, op, val, valTo, dateFrom, dateTo);
        }
        
        async function loadBackups() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'get_backups_list=1'
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    renderBackupsList(data.backups);
                } else {
                    await showAlert(`Error: ${data.message}`);
                }
            } catch (err) {
                await showAlert(`Error: ${err.message}`);
            }
        }
        
        function renderBackupsList(backups) {
            const container = document.getElementById('backups-list');
            if (!container) return;
            
            if (!backups || backups.length === 0) {
                container.innerHTML = `<div style="text-align: center; padding: 20px; color: #666;">${translations.no_backups_found || 'No backups found'}</div>`;
                return;
            }
            
            let html = '';
            
            backups.forEach((backup, index) => {
                const fileSize = (backup.size / 1024).toFixed(2);
                html += `
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name">${backup.name}</div>
                            <div class="backup-details">
                                ${backup.date} | ${fileSize} KB
                            </div>
                        </div>
                        <div class="backup-actions">
                            <button class="backup-btn restore" data-file="${backup.name}">
                                <i class="fas fa-history"></i> ${translations.restore || 'Restore'}
                            </button>
                            <button class="backup-btn delete" data-file="${backup.name}">
                                <i class="fas fa-trash"></i> ${translations.delete || 'Delete'}
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            
            // ╨Ф╨╛╨▒╨░╨▓╨╗╤П╨╡╨╝ ╨╛╨▒╤А╨░╨▒╨╛╤В╤З╨╕╨║╨╕ ╨┤╨╗╤П ╨║╨╜╨╛╨┐╨╛╨║
            container.querySelectorAll('.backup-btn.restore').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const fileName = btn.getAttribute('data-file');
                    await restoreBackup(fileName);
                });
            });
            
            container.querySelectorAll('.backup-btn.delete').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const fileName = btn.getAttribute('data-file');
                    await deleteBackup(fileName);
                });
            });
        }
        
        async function restoreBackup(fileName) {
            const confirm = await showAlert(
                `${translations.confirm_restore_backup || 'Restore backup'} "${fileName}"?`,
                true
            );
            
            if (!confirm) return;
            
            try {
                const formData = new FormData();
                formData.append('restore_backup', '1');
                formData.append('backup_file', fileName);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    await showAlert(translations.backup_restored || 'Backup restored successfully');
                    
                    // ╨Я╨╡╤А╨╡╨╖╨░╨│╤А╤Г╨╢╨░╨╡╨╝ ╤Б╤В╤А╨░╨╜╨╕╤Ж╤Г ╨┤╨╗╤П ╨╛╨▒╨╜╨╛╨▓╨╗╨╡╨╜╨╕╤П ╨┤╨░╨╜╨╜╤Л╤Е
                    location.reload();
                } else {
                    await showAlert(`Error: ${data.message}`);
                }
            } catch (err) {
                await showAlert(`Error: ${err.message}`);
            }
        }
        
        async function deleteBackup(fileName) {
            const confirm = await showAlert(
                `${translations.confirm_delete_backup || 'Delete backup'} "${fileName}"?`,
                true
            );
            
            if (!confirm) return;
            
            try {
                const formData = new FormData();
                formData.append('delete_backup', '1');
                formData.append('backup_file', fileName);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    await showAlert(translations.backup_deleted || 'Backup deleted successfully');
                    await loadBackups(); // ╨Ю╨▒╨╜╨╛╨▓╨╗╤П╨╡╨╝ ╤Б╨┐╨╕╤Б╨╛╨║
                } else {
                    await showAlert(`Error: ${data.message}`);
                }
            } catch (err) {
                await showAlert(`Error: ${err.message}`);
            }
        }
        
        async function downloadBackup() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'download_backup=1'
                });
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Steps_backup_${formatLocalYMD(new Date())}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    await showAlert(translations.backup_downloaded || 'Backup downloaded successfully');
                } else {
                    const data = await response.json();
                    await showAlert(`Error: ${data.message}`);
                }
            } catch (err) {
                await showAlert(`Error: ${err.message}`);
            }
        }
        
        let pendingVideoCleanup = { jobId: '', outputBasename: '' };
        let pendingVideoRows = [];
        let videoPollTimer = null, videoLastJobId = null, videoCropper = null;
        let fitPollTimer = null, fitLastJobId = null;
        let vaUploadToken = '';
        let globalImageViewerOpen = false;
        let workerWidgetTimer = null;
        const workerProgressState = { video: null, takeout: null };
        const VA_API = 'utils/analize.php';
        const FTO_API = 'utils/google_fit_takeout/takeout_api.php';
        function closeGlobalImageViewer() {
            const wrap = document.getElementById('global-image-viewer');
            const img = document.getElementById('global-image-viewer-img');
            if (!wrap || !img) return;
            wrap.classList.remove('open');
            wrap.setAttribute('aria-hidden', 'true');
            img.src = '';
            img.alt = '';
            globalImageViewerOpen = false;
        }
        function openGlobalImageViewer(src, alt) {
            const wrap = document.getElementById('global-image-viewer');
            const img = document.getElementById('global-image-viewer-img');
            if (!wrap || !img || !src) return;
            img.src = src;
            img.alt = alt || '';
            wrap.classList.add('open');
            wrap.setAttribute('aria-hidden', 'false');
            globalImageViewerOpen = true;
        }
        function workerNormalizePct(v) {
            const n = Number(v);
            if (!isFinite(n)) return 0;
            return Math.max(0, Math.min(100, Math.round(n)));
        }
        function workerSetState(kind, state) {
            if (!Object.prototype.hasOwnProperty.call(workerProgressState, kind)) return;
            workerProgressState[kind] = state || null;
            workerRenderProgressWidget();
        }
        function workerRenderProgressWidget() {
            const box = document.getElementById('worker-progress-widget');
            if (!box) return;
            const items = [];
            if (workerProgressState.video && workerProgressState.video.status === 'running') {
                items.push({ icon: 'fas fa-film', pct: workerNormalizePct(workerProgressState.video.progress) });
            }
            if (workerProgressState.takeout && workerProgressState.takeout.status === 'running') {
                items.push({ icon: 'fas fa-file-zipper', pct: workerNormalizePct(workerProgressState.takeout.progress) });
            }
            if (!items.length) {
                box.style.display = 'none';
                box.innerHTML = '';
                return;
            }
            box.innerHTML = items.map(function(it) {
                return '<div class="worker-progress-item"><i class="' + it.icon + '"></i><span class="worker-progress-text">' + String(it.pct) + '%</span></div>';
            }).join('');
            box.style.display = 'flex';
        }
        async function workerRefreshStates() {
            const reqVideo = vaFetchBootstrap();
            const reqTakeout = fetch(FTO_API + '?action=active_job', { credentials: 'same-origin' }).then(function(r) { return r.json(); }).catch(function() { return null; });
            const res = await Promise.all([reqVideo, reqTakeout]);
            const vd = res[0];
            const td = res[1];
            workerSetState('video', vd && vd.ok && vd.mode === 'running' && vd.state ? vd.state : null);
            workerSetState('takeout', td && td.ok && td.active && td.active.state ? td.active.state : null);
        }
        function workerStartRefreshLoop() {
            if (workerWidgetTimer) clearInterval(workerWidgetTimer);
            workerRefreshStates();
            workerWidgetTimer = setInterval(workerRefreshStates, 2500);
        }
        function vaBaseQuery() {
            return 'lang=' + encodeURIComponent('<?php echo $language; ?>') + '&theme=' + encodeURIComponent('<?php echo $theme; ?>') + '&color=' + encodeURIComponent('<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>');
        }
        function vaGetUrl(action, extra) {
            let s = VA_API + '?' + vaBaseQuery() + '&action=' + encodeURIComponent(action);
            if (extra) for (const k in extra) s += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(extra[k]);
            return s;
        }
        let vaWizardStep = 1;
        let vaWizardLocked = false;
        let ftWizardStep = 1;
        let ftWizardLocked = false;
        function setWizardUi(rootId, currentStep, maxReached, locked) {
            const root = document.getElementById(rootId);
            if (!root) return;
            const dots = root.querySelectorAll('.va-step-dot');
            const lines = root.querySelectorAll('.va-step-line');
            dots.forEach((dot, idx) => {
                const step = idx + 1;
                dot.classList.toggle('is-active', step === currentStep);
                dot.classList.toggle('is-done', step < currentStep);
                dot.disabled = !!locked || step > maxReached;
            });
            lines.forEach((line, idx) => { line.classList.toggle('is-done', idx + 1 < currentStep); });
        }
        function setWizardSections(panelId, currentStep, locked) {
            const panel = document.getElementById(panelId);
            if (!panel) return;
            panel.querySelectorAll('[data-step-section]').forEach((section) => {
                const step = parseInt(section.getAttribute('data-step-section') || '0', 10);
                const active = step === currentStep;
                section.style.display = active ? '' : 'none';
                section.classList.toggle('is-locked', !!locked);
                section.querySelectorAll('input,select,button,textarea').forEach((el) => { el.disabled = !!locked || !active; });
            });
        }
        function vaUpdateWizardUi() {
            const hasFile = !!(document.getElementById('va-file') && document.getElementById('va-file').files && document.getElementById('va-file').files[0]);
            const maxStep = hasFile && vaIsYearValidForStep3() ? 3 : (hasFile ? 2 : 1);
            if (!hasFile) vaWizardStep = 1;
            else if (vaWizardStep < 2) vaWizardStep = 2;
            if (vaWizardStep > maxStep) vaWizardStep = maxStep;
            setWizardUi('va-steps', vaWizardStep, maxStep, vaWizardLocked);
            setWizardSections('add-panel-video', vaWizardStep, vaWizardLocked);
            const canAct = hasFile && vaWizardStep === 3 && !vaWizardLocked && vaFileReady;
            const st = document.getElementById('va-start');
            const cb = document.getElementById('va-crop-btn');
            if (st) st.disabled = !canAct;
            if (cb) cb.disabled = !canAct;
        }
        function ftUpdateWizardUi() {
            const hasFile = !!(document.getElementById('ft-zip') && document.getElementById('ft-zip').files && document.getElementById('ft-zip').files[0]);
            const maxStep = hasFile ? 3 : 1;
            if (!hasFile) ftWizardStep = 1;
            else if (ftWizardStep < 2) ftWizardStep = 2;
            if (ftWizardStep > maxStep) ftWizardStep = maxStep;
            setWizardUi('ft-steps', ftWizardStep, maxStep, ftWizardLocked);
            setWizardSections('add-panel-takeout', ftWizardStep, ftWizardLocked);
            const st = document.getElementById('ft-start');
            if (st) st.disabled = !(hasFile && ftWizardStep === 3 && !ftWizardLocked);
        }
        function vaGetYearValue() {
            const sel = document.getElementById('va-year-select');
            if (!sel) return '-';
            if (sel.value === 'manual') {
                const inp = document.getElementById('va-year-manual');
                return ((inp && inp.value) ? String(inp.value) : '').trim();
            }
            return String(sel.value || '-').trim();
        }
        function vaIsYearValidForStep3() {
            const yRaw = vaGetYearValue();
            if (yRaw === '' || yRaw === '-') return true;
            if (!/^\d{4}$/.test(yRaw)) return false;
            const y = parseInt(yRaw, 10);
            return !isNaN(y) && y >= 2000 && y <= 2100;
        }
        function vaShowJobPanel(on) { const p = document.getElementById('va-job-panel'); if (p) p.style.display = on ? 'block' : 'none'; }
        function vaSetBar(pct) { const f = document.getElementById('va-progress-fill'); if (f) f.style.width = Math.min(100, Math.max(0, pct)) + '%'; }
        function vaStopPoll() { if (videoPollTimer) { clearInterval(videoPollTimer); videoPollTimer = null; } }
        let vaFileReady = false;
        function vaSetFileActionEnabled(on) {
            const st = document.getElementById('va-start');
            const cb = document.getElementById('va-crop-btn');
            if (st) st.disabled = !on;
            if (cb) cb.disabled = !on;
            vaUpdateWizardUi();
        }
        function vaFileLoadSetVisible(visible) {
            const w = document.getElementById('va-file-load-wrap');
            if (w) w.style.display = visible ? 'block' : 'none';
        }
        function vaFileLoadSetIndet(ind) {
            const w = document.getElementById('va-file-load-wrap');
            if (w) w.classList.toggle('va-file-load-indet', !!ind);
        }
        function vaFileLoadSetPct(m) {
            const f = document.getElementById('va-file-load-fill');
            if (f) f.style.width = Math.min(100, Math.max(0, 100 * m)) + '%';
        }
        function vaFileLoadSetLabel(t) { const el = document.getElementById('va-file-load-label'); if (el) el.textContent = t || ''; }
        function vaFileLoadHide() {
            vaFileLoadSetVisible(false);
            vaFileLoadSetIndet(false);
            vaFileLoadSetPct(0);
            vaFileLoadSetLabel('');
        }
        function vaFormatEtaSec(sec) {
            const s0 = Math.max(0, parseFloat(String(sec)) || 0);
            const s = Math.round(s0);
            if (s < 60) return (translations.video_eta_s || '~{n} s').replace(/\{n\}/g, String(s));
            if (s < 3600) {
                const m = Math.floor(s / 60);
                const rem = s - m * 60;
                if (rem < 1) return (translations.video_eta_m || '~{m} min').replace(/\{m\}/g, String(m));
                return (translations.video_eta_ms || '~{m} min {s} s').replace(/\{m\}/g, String(m)).replace(/\{s\}/g, String(rem));
            }
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            if (m < 1) return (translations.video_eta_h || '~{h} h').replace(/\{h\}/g, String(h));
            return (translations.video_eta_hm || '~{h} h {m} min').replace(/\{h\}/g, String(h)).replace(/\{m\}/g, String(m));
        }
        async function vaReadLocalVideoFile(f) {
            if (!f || !f.stream) {
                return;
            }
            const r = f.stream().getReader();
            const tot = f.size || 1;
            let load = 0;
            for (;;) {
                const x = await r.read();
                if (x.done) break;
                load += (x.value && x.value.byteLength) ? x.value.byteLength : 0;
                vaFileLoadSetPct(load / tot);
            }
            vaFileLoadSetPct(1);
        }
        function vaXhrForm(url, formData, isBlob) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.responseType = isBlob ? 'blob' : 'text';
                xhr.upload.addEventListener('progress', (e) => {
                    vaFileLoadSetVisible(true);
                    if (e.lengthComputable) { vaFileLoadSetIndet(false); vaFileLoadSetPct(e.loaded / e.total); } else { vaFileLoadSetIndet(true); }
                });
                xhr.addEventListener('load', () => {
                    if (xhr.status < 200 || xhr.status >= 300) { reject(new Error('http' + xhr.status)); return; }
                    if (isBlob) { resolve(xhr.response); } else {
                        try { resolve(JSON.parse(xhr.responseText)); } catch (err) { reject(err); }
                    }
                });
                xhr.addEventListener('error', () => { reject(new Error('net')); });
                xhr.send(formData);
            });
        }
        async function streamUploadFileInChunks(apiUrl, file, kind, onProgress) {
            const chunkSize = 512 * 1024;
            const totalChunks = Math.max(1, Math.ceil((file.size || 0) / chunkSize));
            const uploadId = (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : (Date.now().toString(16) + Math.random().toString(16).slice(2)));
            for (let i = 0; i < totalChunks; i++) {
                const start = i * chunkSize;
                const end = Math.min(file.size, start + chunkSize);
                const part = file.slice(start, end);
                const fd = new FormData();
                fd.append('action', 'upload_chunk');
                fd.append('file_kind', kind);
                fd.append('upload_id', uploadId);
                fd.append('chunk_index', String(i));
                fd.append('chunk_total', String(totalChunks));
                fd.append('file_size', String(file.size || 0));
                fd.append('file_name', file.name || '');
                fd.append('chunk', part, (file.name || 'chunk') + '.part');
                let d = null;
                try {
                    d = await fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then((r) => r.json());
                } catch (e) {
                    d = null;
                }
                if (!d || !d.ok) throw new Error((d && d.error) ? d.error : 'upload_chunk_failed');
                if (typeof onProgress === 'function') onProgress(Math.min(1, (i + 1) / totalChunks), i + 1, totalChunks);
            }
            return uploadId;
        }
        function vaResetVideoFileUi() {
            vaFileReady = false;
            vaUploadToken = '';
            vaFileLoadHide();
            const inp = document.getElementById('va-file');
            if (inp) inp.value = '';
            const ys = document.getElementById('va-year-select');
            if (ys) ys.value = '-';
            const ym = document.getElementById('va-year-manual');
            if (ym) { ym.value = ''; ym.style.display = 'none'; }
            const cb = document.getElementById('va-crop-btn');
            if (cb) { cb.style.display = 'none'; cb.disabled = true; }
            vaSetFileActionEnabled(false);
            ['va-crop-x', 'va-crop-y', 'va-crop-w', 'va-crop-h'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            if (typeof vaSetRemoteJobMode === 'function') vaSetRemoteJobMode(false);
            vaWizardStep = 1;
            vaWizardLocked = false;
            vaUpdateWizardUi();
        }
        function vaTranslateDetail(msg) {
            if (msg == null || msg === '') return '';
            const s = String(msg);
            if (s === 'ffmpeg failed') return translations.video_err_ffmpeg || s;
            if (s === 'no frames') return translations.video_err_noframes || s;
            if (s === 'python not started') return translations.video_err_worker || s;
            if (s === 'stale_job') return translations.video_err_stale_job || s;
            if (/^import:/i.test(s)) return (translations.video_err_import || '') + s.replace(/^import:\s*/i, '').trim();
            return s;
        }
        function vaTranslateStateMessage(st) {
            if (!st) return 'тАФ';
            const raw = st.message;
            if (raw == null || raw === '') {
                if (st.status === 'running') return translations.video_status_preparing || 'тАж';
                return st.status || 'тАФ';
            }
            const s = String(raw);
            if (s === 'preparing') return translations.video_status_preparing || s;
            if (s === 'python') return translations.video_status_python || s;
            if (s === 'ffmpeg') return translations.video_status_ffmpeg || s;
            if (s === 'ok') return translations.video_status_ok || s;
            let x = s.match(/^OCR (\d+) frames$/i);
            if (x) return (translations.video_status_ocr_n || s).replace(/\{n\}/g, x[1]);
            x = s.match(/^frame (\d+)\/(\d+)$/i);
            if (x) return (translations.video_status_frame_ab || s).replace(/\{a\}/g, x[1]).replace(/\{b\}/g, x[2]);
            if (s === 'ffmpeg failed') return translations.video_err_ffmpeg || s;
            if (s === 'no frames') return translations.video_err_noframes || s;
            if (/^import:/i.test(s)) return (translations.video_err_import || '') + s.replace(/^import:\s*/i, '');
            x = s.match(/^gap r\d+ (\d+)\/(\d+)/);
            if (x) return (translations.video_status_gap || s).replace(/\{a\}/g, x[1]).replace(/\{b\}/g, x[2]);
            if (s === 'merge') return translations.video_status_merge || s;
            return s;
        }
        function vaMapProcessError(code) {
            if (!code) return translations.video_error || '';
            const map = { year: 'video_err_year', move: 'video_err_upload', mkdir: 'video_err_dir', worker: 'video_err_worker' };
            const k = map[code];
            return k && translations[k] ? translations[k] : (translations.video_error || code);
        }
        function vaOnJobState(st) {
            if (!st) return;
            workerSetState('video', st);
            const m = document.getElementById('va-msg');
            if (m) m.textContent = vaTranslateStateMessage(st);
            vaSetBar(st.progress != null && !isNaN(st.progress) ? st.progress : 0);
            const meta = document.getElementById('va-meta');
            if (meta) {
                let t = '';
                if (st.processed != null && st.total) {
                    t = (translations.video_meta_frames || '{cur} / {tot}').replace(/\{cur\}/g, String(st.processed)).replace(/\{tot\}/g, String(st.total));
                }
                if (st.eta_sec != null && st.eta_sec !== '' && !isNaN(st.eta_sec)) {
                    t += (t ? ' ┬╖ ' : '') + vaFormatEtaSec(st.eta_sec);
                }
                meta.textContent = t;
            }
            if (st.status === 'done') {
                vaStopPoll();
                workerSetState('video', null);
                vaShowJobPanel(false);
                vaWizardLocked = false;
                vaUpdateWizardUi();
                showVideoResultsConfirm({ jobId: videoLastJobId, outputBasename: st.output_basename, results: st.results || [] });
                videoLastJobId = null;
                setTimeout(function() { if (typeof syncVideoNotifications === 'function') syncVideoNotifications(); }, 500);
            } else if (st.status === 'error') {
                vaStopPoll();
                workerSetState('video', null);
                vaShowJobPanel(true);
                vaWizardLocked = false;
                vaUpdateWizardUi();
                videoLastJobId = null;
                const detail = vaTranslateDetail(st.message);
                showAlert((translations.video_job_err || 'Error') + (detail ? ': ' + detail : ''));
            } else if (st.status === 'cancelled') {
                vaStopPoll();
                workerSetState('video', null);
                videoLastJobId = null;
                vaShowJobPanel(false);
                vaWizardLocked = false;
                vaUpdateWizardUi();
            }
        }
        function vaStartPoll(jid) {
            videoLastJobId = jid;
            vaStopPoll();
            vaShowJobPanel(true);
            vaWizardLocked = true;
            vaUpdateWizardUi();
            const one = () => {
                fetch(vaGetUrl('job_status', { job: jid }), { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => { if (d && d.ok) vaOnJobState(d.state); })
                    .catch(function() {});
            };
            one();
            videoPollTimer = setInterval(one, 400);
        }
        function ftStopPoll() { if (fitPollTimer) { clearInterval(fitPollTimer); fitPollTimer = null; } }
        function ftSetBar(pct) { const f = document.getElementById('ft-progress-fill'); if (f) f.style.width = Math.min(100, Math.max(0, pct)) + '%'; }
        function ftTranslateStateMessage(st) {
            if (!st) return 'тАФ';
            const raw = st.message;
            if (raw == null || raw === '') {
                if (st.status === 'running') return translations.takeout_queued || 'тАж';
                return st.status || 'тАФ';
            }
            const s = String(raw);
            if (s === 'unzip') return translations.takeout_unzip || s;
            if (s === 'parse') return translations.takeout_parse || s;
            if (s === 'queued') return translations.takeout_queued || s;
            if (s === 'conflict_prompt') return translations.takeout_conflict_prompt || s;
            if (s === 'apply') return translations.takeout_apply || s;
            const x = s.match(/^file (\d+)\/(\d+)$/i);
            if (x) return (translations.takeout_file_n || s).replace(/\{a\}/g, x[1]).replace(/\{b\}/g, x[2]);
            if (s === 'no step data in archive') return translations.takeout_err_none || s;
            if (s === 'stale_job') return translations.video_err_stale_job || s;
            return s;
        }
        async function ftResolveConflict(mode, jid) {
            const fd = new FormData();
            fd.append('action', 'resolve_conflict');
            fd.append('job', jid);
            fd.append('mode', mode);
            let d = null;
            try {
                d = await fetch(FTO_API, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
            } catch (e) {
                d = null;
            }
            if (!d || !d.ok) {
                await showAlert((translations.takeout_err_worker || 'Error') + (d && d.error ? ' ' + d.error : ''));
                return;
            }
            ftStartPoll(jid);
        }
        function ftOnState(st) {
            if (!st) return;
            workerSetState('takeout', st);
            const m = document.getElementById('ft-msg');
            if (m) m.textContent = ftTranslateStateMessage(st);
            ftSetBar(st.progress != null && !isNaN(st.progress) ? st.progress : 0);
            const meta = document.getElementById('ft-meta');
            if (meta) {
                const n = st.total_days != null && st.total_days > 0 ? st.total_days : 0;
                meta.textContent = n ? (translations.takeout_meta_days || '').replace(/\{n\}/g, String(n)) : '';
            }
            if (st.status === 'done') {
                ftStopPoll();
                workerSetState('takeout', null);
                ftWizardLocked = false;
                ftUpdateWizardUi();
                fitLastJobId = null;
                location.reload();
            } else if (st.status === 'awaiting_conflict') {
                ftStopPoll();
                const n = parseInt(st.conflict_days, 10) || 0;
                const q = (translations.takeout_conflict_question || 'Conflicts found for {n} days. Overwrite existing values?').replace(/\{n\}/g, String(n));
                showAlert(q, true, {
                    okText: translations.takeout_overwrite || 'Overwrite',
                    cancelText: translations.cancel || 'Cancel',
                    extraText: translations.takeout_add_new_only || 'Add only new values'
                }).then(async (choice) => {
                    if (choice === true) {
                        await ftResolveConflict('overwrite', st.job_id || fitLastJobId);
                        return;
                    }
                    if (choice === 'extra') {
                        await ftResolveConflict('new_only', st.job_id || fitLastJobId);
                        return;
                    }
                    await ftStopJob();
                });
            } else if (st.status === 'error') {
                ftStopPoll();
                workerSetState('takeout', null);
                ftWizardLocked = false;
                ftUpdateWizardUi();
                fitLastJobId = null;
                let det = String(st.message || '');
                if (det === 'stale_job') det = translations.video_err_stale_job || det;
                else if (det === 'no step data in archive') det = translations.takeout_err_none || det;
                showAlert((translations.video_job_err || 'Error') + (det ? ': ' + det : ''));
            } else if (st.status === 'cancelled') {
                ftStopPoll();
                workerSetState('takeout', null);
                ftWizardLocked = false;
                ftUpdateWizardUi();
                fitLastJobId = null;
                const p = document.getElementById('ft-job-panel');
                if (p) p.style.display = 'none';
            }
        }
        function ftStartPoll(jid) {
            fitLastJobId = jid;
            ftStopPoll();
            ftWizardLocked = true;
            ftUpdateWizardUi();
            const p = document.getElementById('ft-job-panel');
            if (p) p.style.display = 'block';
            const one = () => {
                fetch(FTO_API + '?action=job_status&job=' + encodeURIComponent(jid), { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => { if (d && d.ok) ftOnState(d.state); })
                    .catch(function() {});
            };
            one();
            fitPollTimer = setInterval(one, 400);
        }
        async function ftResumeFromAccountState() {
            let d = null;
            try {
                d = await fetch(FTO_API + '?action=active_job', { credentials: 'same-origin' }).then(r => r.json());
            } catch (e) {
                d = null;
            }
            const p = document.getElementById('ft-job-panel');
            if (d && d.ok && d.active && d.active.job_id) {
                fitLastJobId = d.active.job_id;
                if (p) p.style.display = 'block';
                ftWizardLocked = true;
                ftUpdateWizardUi();
                if (d.active.state) ftOnState(d.active.state);
                ftStartPoll(d.active.job_id);
                return true;
            }
            if (p) p.style.display = 'none';
            ftStopPoll();
            ftWizardLocked = false;
            ftUpdateWizardUi();
            fitLastJobId = null;
            return false;
        }
        function vaSetRemoteJobMode(on) {
            const hint = document.getElementById('va-remote-hint');
            if (hint) hint.style.display = on ? 'block' : 'none';
            document.querySelectorAll('.va-segment-hidable').forEach((el) => { el.style.display = on ? 'none' : ''; });
        }
        function videoEscapeHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
        }
        function videoDmyToSortKey(d) {
            const p = String(d).trim().split('-');
            if (p.length !== 3) return '';
            const yy = parseInt(p[2], 10);
            if (isNaN(yy)) return '';
            const cent = yy < 70 ? '20' : '19';
            return cent + p[2].padStart(2, '0') + '-' + p[1].padStart(2, '0') + '-' + p[0].padStart(2, '0');
        }
        function videoIsoToDmy(iso) {
            const p = String(iso).split('-');
            if (p.length !== 3) return '';
            return p[2] + '-' + p[1] + '-' + p[0].slice(2);
        }
        function sortPendingVideoRows() {
            pendingVideoRows.sort((a, b) => videoDmyToSortKey(a.date).localeCompare(videoDmyToSortKey(b.date)));
        }
        function renderVideoConfirmTableBody() {
            const wrap = document.getElementById('video-confirm-table-wrap');
            if (!wrap) return;
            sortPendingVideoRows();
            if (!pendingVideoRows.length) {
                wrap.innerHTML = '<p style="text-align:center;">' + (translations.video_no_data || 'No data') + '</p>';
                updateVideoConfirmGapsButton();
                return;
            }
            const dh = translations['date'] || 'Date';
            const thh = translations.video_col_time || 'Time';
            const sh = translations['steps'] || 'Steps';
            let h = '<table class="excel-table" id="video-confirm-table"><tr><th>' + videoEscapeHtml(dh) + '</th><th>' + videoEscapeHtml(thh) + '</th><th>' + videoEscapeHtml(sh) + '</th></tr>';
            pendingVideoRows.forEach((r) => {
                h += '<tr><td><input class="vcr-inp vcr-date" type="text" inputmode="numeric" value="' + videoEscapeHtml(r.date) + '" maxlength="8" style="min-width:7em;max-width:9em;box-sizing:border-box;padding:4px 6px;border:1px solid var(--border-color);border-radius:4px;background:var(--modal-bg);color:var(--text-color);"></td><td>' + videoEscapeHtml(r.t || 'тАФ') + '</td><td><input class="vcr-inp vcr-steps" type="text" inputmode="numeric" value="' + videoEscapeHtml(formatNumber(parseInt(r.steps, 10) || 0)) + '" style="min-width:5em;max-width:8em;box-sizing:border-box;padding:4px 6px;border:1px solid var(--border-color);border-radius:4px;background:var(--modal-bg);color:var(--text-color);"></td></tr>';
            });
            h += '</table>';
            wrap.innerHTML = h;
            updateVideoConfirmGapsButton();
        }
        function syncVideoConfirmFromInputs() {
            const wrap = document.getElementById('video-confirm-table-wrap');
            if (!wrap) return;
            const table = wrap.querySelector('table.excel-table');
            if (!table) return;
            const trs = table.querySelectorAll('tr');
            const byDate = new Map();
            for (let i = 1; i < trs.length; i++) {
                const tds = trs[i].querySelectorAll('td');
                if (tds.length < 3) continue;
                const di = tds[0].querySelector('.vcr-date');
                const si = tds[2].querySelector('.vcr-steps');
                if (!di || !si) continue;
                const d = (di.value || '').trim();
                const s = parseInt(parseStepsRaw(si.value), 10);
                if (!/^\d{2}-\d{2}-\d{2}$/.test(d) || isNaN(s) || s < 0) continue;
                const tc = (tds[1].textContent || '').trim();
                const t = tc === 'тАФ' ? '' : tc;
                byDate.set(d, { date: d, steps: s, t: t });
            }
            pendingVideoRows = Array.from(byDate.values());
            sortPendingVideoRows();
        }
        function refreshVideoConfirmView() {
            renderVideoConfirmTableBody();
            requestAnimationFrame(function() { renderVideoConfirmChart(pendingVideoRows); });
        }
        function updateVideoConfirmGapsButton() {
            const b = document.getElementById('video-confirm-gaps-btn');
            if (!b) return;
            b.style.display = pendingVideoRows.length ? 'inline-flex' : 'none';
        }
        function computeVideoGaps() {
            if (pendingVideoRows.length < 1) return [];
            sortPendingVideoRows();
            const first = pendingVideoRows[0];
            const last = pendingVideoRows[pendingVideoRows.length - 1];
            const iso0 = videoDmyToSortKey(first.date);
            const iso1 = videoDmyToSortKey(last.date);
            if (!iso0 || !iso1) return [];
            const have = new Set();
            pendingVideoRows.forEach((r) => {
                const k = videoDmyToSortKey(r.date);
                if (k) have.add(k);
            });
            const out = [];
            const cur = new Date(iso0 + 'T12:00:00');
            const end = new Date(iso1 + 'T12:00:00');
            while (cur <= end) {
                const iso = cur.toISOString().slice(0, 10);
                if (!have.has(iso)) {
                    out.push({ iso: iso, dmy: videoIsoToDmy(iso) });
                }
                cur.setDate(cur.getDate() + 1);
            }
            return out;
        }
        function openVideoGapsModal() {
            const gaps = computeVideoGaps();
            const body = document.getElementById('video-confirm-gaps-body');
            const lab = translations['date'] || 'Date';
            const sLab = translations['steps'] || 'Steps';
            if (!gaps.length) {
                body.innerHTML = '<p style="text-align:center;opacity:0.9;">' + videoEscapeHtml(translations.video_gaps_empty || 'No gaps') + '</p>';
            } else {
                let h = '<table class="excel-table"><tr><th>' + videoEscapeHtml(lab) + '</th><th>' + videoEscapeHtml(sLab) + '</th></tr>';
                gaps.forEach((g) => {
                    h += '<tr><td>' + videoEscapeHtml(g.dmy) + '</td><td><input type="text" class="vcr-gap-step" data-dmy="' + videoEscapeHtml(g.dmy) + '" inputmode="numeric" placeholder="" style="min-width:5em;max-width:8em;box-sizing:border-box;padding:4px 6px;border:1px solid var(--border-color);border-radius:4px;background:var(--modal-bg);color:var(--text-color);"></td></tr>';
                });
                h += '</table>';
                body.innerHTML = h;
            }
            const gm = document.getElementById('video-confirm-gaps-modal');
            if (gm) gm.style.display = 'flex';
        }
        function closeVideoGapsModal() {
            const gm = document.getElementById('video-confirm-gaps-modal');
            if (gm) gm.style.display = 'none';
        }
        let videoConfirmChart = null;
        function destroyVideoConfirmChart() {
            if (videoConfirmChart) {
                videoConfirmChart.destroy();
                videoConfirmChart = null;
            }
        }
        function renderVideoConfirmChart(rows) {
            destroyVideoConfirmChart();
            const cwrap = document.getElementById('video-confirm-chart-wrap');
            const canvas = document.getElementById('video-confirm-chart');
            if (!cwrap || !canvas || !rows || !rows.length) {
                if (cwrap) cwrap.style.display = 'none';
                return;
            }
            cwrap.style.display = 'block';
            const sorted = [...rows].sort((a, b) => videoDmyToSortKey(a.date).localeCompare(videoDmyToSortKey(b.date)));
            const labels = sorted.map(r => r.date);
            const data = sorted.map(r => r.steps);
            const isDarkTheme = '<?php echo $theme === 'dark' ? 'true' : 'false'; ?>' === 'true';
            const dlab = translations['date'] || 'Date';
            const slab = translations['steps'] || 'Steps';
            videoConfirmChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: { labels, datasets: [{ label: slab, data, backgroundColor: 'rgba(0,123,255,0.5)', borderColor: 'rgba(0,123,255,1)', borderWidth: 1 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: 10 },
                    scales: {
                        y: { beginAtZero: true, ticks: { color: isDarkTheme ? '#fff' : '#000' }, title: { display: true, text: slab, color: isDarkTheme ? '#fff' : '#000' } },
                        x: { ticks: { color: isDarkTheme ? '#fff' : '#000' }, title: { display: true, text: dlab, color: isDarkTheme ? '#fff' : '#000' } }
                    },
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => `${slab}: ${formatNumber(Math.round(c.raw))}` } } }
                }
            });
        }
        async function vaFetchBootstrap() {
            const u = new URL(window.location.href);
            u.searchParams.set('video_bootstrap', '1');
            try {
                return await fetch(u.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json());
            } catch (e) {
                return null;
            }
        }
        async function vaResumeFromAccountState() {
            const d = await vaFetchBootstrap();
            if (d && d.ok && d.mode === 'running' && d.jobId && d.state) {
                vaSetRemoteJobMode(true);
                videoLastJobId = d.jobId;
                vaOnJobState(d.state);
                vaStartPoll(d.jobId);
                return true;
            }
            vaSetRemoteJobMode(false);
            if (d && d.ok && d.mode === 'pending' && d.results && d.results.length) {
                showVideoResultsConfirm({ jobId: d.job_id, outputBasename: d.output_basename, results: d.results });
                return true;
            }
            vaShowJobPanel(false);
            return false;
        }
        async function vaOpenVideoModal() {
            openModal('add-steps-modal', { addTab: 'video' });
        }

        async function runVideoCleanup() {
            const fd = new FormData();
            fd.append('cleanup_video_temp', '1');
            fd.append('job_id', pendingVideoCleanup.jobId || '');
            fd.append('output_basename', pendingVideoCleanup.outputBasename || '');
            try {
                await fetch('', { method: 'POST', body: fd });
            } catch (e) {}
            pendingVideoCleanup = { jobId: '', outputBasename: '' };
            pendingVideoRows = [];
        }
        async function vaStopJob() {
            if (!videoLastJobId) return;
            const ok = await showAlert('╨Э╨╡╨╛╨▒╤А╨░╨▒╨╛╤В╨░╨╜╨╜╤Л╨╡ ╨┤╨░╨╜╨╜╤Л╨╡ ╨▒╤Г╨┤╤Г╤В ╤Г╨┤╨░╨╗╨╡╨╜╤Л. ╨Ю╤Б╤В╨░╨╜╨╛╨▓╨╕╤В╤М ╨▓╨╛╤А╨║╨╡╤А?', true);
            if (!ok) return;
            const fd = new FormData();
            fd.append('action', 'stop_job');
            fd.append('job', videoLastJobId);
            try {
                await fetch(VA_API + '?' + vaBaseQuery(), { method: 'POST', body: fd, credentials: 'same-origin' });
            } catch (e) {}
            vaStopPoll();
            workerSetState('video', null);
            videoLastJobId = null;
            vaShowJobPanel(false);
            vaWizardLocked = false;
            vaUpdateWizardUi();
            pendingVideoCleanup = { jobId: '', outputBasename: '' };
            pendingVideoRows = [];
            const pb = document.getElementById('va-progress-fill');
            if (pb) pb.style.width = '0%';
        }
        async function ftStopJob() {
            if (!fitLastJobId) return;
            const ok = await showAlert('╨Э╨╡╨╛╨▒╤А╨░╨▒╨╛╤В╨░╨╜╨╜╤Л╨╡ ╨┤╨░╨╜╨╜╤Л╨╡ ╨▒╤Г╨┤╤Г╤В ╤Г╨┤╨░╨╗╨╡╨╜╤Л. ╨Ю╤Б╤В╨░╨╜╨╛╨▓╨╕╤В╤М ╨▓╨╛╤А╨║╨╡╤А?', true);
            if (!ok) return;
            const fd = new FormData();
            fd.append('action', 'stop_job');
            fd.append('job', fitLastJobId);
            try {
                await fetch(FTO_API, { method: 'POST', body: fd, credentials: 'same-origin' });
            } catch (e) {}
            ftStopPoll();
            workerSetState('takeout', null);
            fitLastJobId = null;
            ftWizardLocked = false;
            ftUpdateWizardUi();
            const p = document.getElementById('ft-job-panel');
            if (p) p.style.display = 'none';
            const pb = document.getElementById('ft-progress-fill');
            if (pb) pb.style.width = '0%';
        }

        function showVideoResultsConfirm(payload) {
            pendingVideoCleanup = { jobId: payload.jobId || '', outputBasename: payload.outputBasename || '' };
            const raw = payload.results && payload.results.length ? payload.results : [];
            pendingVideoRows = [];
            raw.forEach(r => {
                const d = (r.dmy && /^\d{2}-\d{2}-\d{2}$/.test(r.dmy)) ? r.dmy : '';
                if (!d) return;
                const s = parseInt(r.steps, 10);
                if (isNaN(s) || s < 0) return;
                pendingVideoRows.push({ date: d, steps: s, t: (r.t_display != null && r.t_display !== '') ? String(r.t_display) : '' });
            });
            document.getElementById('video-confirm-sub').textContent = translations.video_confirm_sub || '';
            renderVideoConfirmTableBody();
            closeModal('add-steps-modal');
            openModal('video-confirm-modal');
            requestAnimationFrame(function() { renderVideoConfirmChart(pendingVideoRows); });
        }

        function videoNotifUserKey() {
            return 'stepsVNotifZ_' + String(currentUser || 'u').replace(/[^a-z0-9_\-]/gi, '_');
        }
        function snoozeVideoNotifications12h() {
            try { localStorage.setItem(videoNotifUserKey(), String(Date.now() + 12 * 60 * 60 * 1000)); } catch (e) {}
            const n = document.getElementById('video-recognition-notification');
            if (n) n.style.display = 'none';
        }
        async function syncVideoNotifications() {
            const n = document.getElementById('video-recognition-notification');
            if (!n) return;
            let until = 0;
            try { until = parseInt(localStorage.getItem(videoNotifUserKey()) || '0', 10); } catch (e) {}
            if (Date.now() < until) { n.style.display = 'none'; return; }
            const vcm = document.getElementById('video-confirm-modal');
            if (vcm && window.getComputedStyle(vcm).display === 'flex' && !document.hidden) { n.style.display = 'none'; return; }
            const u = new URL(window.location.href);
            u.searchParams.set('video_bootstrap', '1');
            let d;
            try {
                d = await fetch(u.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json());
            } catch (e) { return; }
            if (d && d.ok && d.mode === 'pending' && d.results && d.results.length) {
                const th = document.getElementById('video-recognition-notif-title');
                const bd = document.getElementById('video-recognition-notif-body');
                if (th) th.innerHTML = '<i class="fas fa-film"></i> ' + (translations.video_notif_title || 'Video');
                if (bd) bd.textContent = translations.video_notif_body || '';
                n.style.display = 'block';
            } else {
                n.style.display = 'none';
            }
        }

        // ╨Ш╨╜╨╕╤Ж╨╕╨░╨╗╨╕╨╖╨░╤Ж╨╕╤П ╨┐╤А╨╕╨╗╨╛╨╢╨╡╨╜╨╕╤П
        function initApp() {
            renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
            updateCalendar(currentLocale);
            updateStatsNotification();
            updateGoalNotification();
            syncVideoNotifications();
            setInterval(function() { syncVideoNotifications(); }, 45000);
            document.addEventListener('visibilitychange', function() { if (!document.hidden) syncVideoNotifications(); });
            workerStartRefreshLoop();
            document.addEventListener('visibilitychange', function() { if (!document.hidden) workerRefreshStates(); });
            
            // ╨Ю╨▒╤А╨░╨▒╨╛╤В╤З╨╕╨║╨╕ ╨┤╨╗╤П ╨║╨╜╨╛╨┐╨║╨╕ ╨╕╨╜╤Д╨╛╤А╨╝╨░╤Ж╨╕╨╕
            document.getElementById('info-button').addEventListener('click', () => {
                openModal('info-modal');
            });
            
            // ╨Ю╨▒╤А╨░╨▒╨╛╤В╤З╨╕╨║╨╕ ╨┤╨╗╤П ╨║╨╜╨╛╨┐╨╛╨║ ╨┐╤А╨╡╨┤╤Б╤В╨░╨▓╨╗╨╡╨╜╨╕╤П
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentView = btn.getAttribute('data-view');
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                });
            });
            
            // ╨Ю╨▒╤А╨░╨▒╨╛╤В╤З╨╕╨║╨╕ ╨┤╨╗╤П ╤В╨╕╨┐╨░ ╨┤╨░╨╜╨╜╤Л╤Е
            document.querySelectorAll('.data-type-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.data-type-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentDataType = btn.getAttribute('data-type');
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                });
            });
            
            // ╨Ю╨▒╤А╨░╨▒╨╛╤В╤З╨╕╨║╨╕ ╨┤╨╗╤П ╤В╨╕╨┐╨░ ╨│╤А╨░╤Д╨╕╨║╨░
            document.querySelectorAll('.chart-type-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.chart-type-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentChartType = btn.getAttribute('data-type');
                    try { localStorage.setItem(chartTypeStorageKey(), currentChartType); } catch (e) {}
                    renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                });
            });
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    const id = tab.getAttribute('data-settings-tab');
                    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.settings-tab-panel').forEach(p => p.classList.remove('active'));
                    tab.classList.add('active');
                    const panel = document.getElementById('settings-panel-' + id);
                    if (panel) panel.classList.add('active');
                    if (id === 'chart') syncChartTypeButtons();
                });
            });
            
            // ╨Э╨░╨▓╨╕╨│╨░╤Ж╨╕╤П ╨┐╨╛ ╨│╤А╨░╤Д╨╕╨║╤Г
            document.getElementById('chart-nav-left').addEventListener('click', () => { 
                if (currentView === 'month') {
                    currentDate.setMonth(currentDate.getMonth() - 1);
                } else if (currentView === 'week') {
                    currentDate.setDate(currentDate.getDate() - 7);
                } else if (currentView === 'year') {
                    currentDate.setFullYear(currentDate.getFullYear() - 1);
                }
                renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                if (currentView === 'month') updateCalendar(currentLocale);
            });

            document.getElementById('chart-nav-right').addEventListener('click', () => { 
                if (currentView === 'month') {
                    currentDate.setMonth(currentDate.getMonth() + 1);
                } else if (currentView === 'week') {
                    currentDate.setDate(currentDate.getDate() + 7);
                } else if (currentView === 'year') {
                    currentDate.setFullYear(currentDate.getFullYear() + 1);
                }
                renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                if (currentView === 'month') updateCalendar(currentLocale);
            });
            
            // ╨Т╤Л╨▒╨╛╤А ╨╝╨╡╤Б╤П╤Ж╨░
            document.getElementById('month-selector').addEventListener('change', () => { 
                const [year, month] = document.getElementById('month-selector').value.split('-'); 
                currentDate = new Date(year, month - 1); 
                renderCalendar(currentDate, currentLocale); 
                renderChart('month', currentDate, currentDataType, currentChartType, currentLocale); 
            });
            
            // ╨Ъ╨╜╨╛╨┐╨║╨░ ╨╜╨░╤Б╤В╤А╨╛╨╡╨║
            document.getElementById('settings-button').addEventListener('click', () => {
                openModal('settings-modal');
            });
            
            // ╨Ъ╨╜╨╛╨┐╨║╨░ ╨┤╨╛╨▒╨░╨▓╨╗╨╡╨╜╨╕╤П ╤И╨░╨│╨╛╨▓
            document.getElementById('add-steps-button').addEventListener('click', () => { 
                const today = formatLocalYMD(new Date()); 
                document.getElementById('selected-date-input').value = today; 
                setStepsInputValue((stepsData.find(entry => entry.date === today) || { steps: 0 }).steps); 
                updateAdjacentDates(today, currentLocale); 
                openModal('add-steps-modal');
                setTimeout(() => document.getElementById('steps-input').focus(), 100); 
            });
            
            // ╨Ъ╨╜╨╛╨┐╨║╨░ Excel
            document.getElementById('excel-button').addEventListener('click', () => {
                document.getElementById('search-steps').value = '';
                document.getElementById('search-steps-to').value = '';
                document.getElementById('search-date-from').value = '';
                document.getElementById('search-date-to').value = '';
                renderExcelTable(1, '', '', '', '', '');
                openModal('excel-modal');
            });
            document.getElementById('excel-hotkeys-open')?.addEventListener('click', () => { openModal('excel-hotkeys-modal'); });
            
            // ╨Ъ╨╜╨╛╨┐╨║╨░ ╤Б╨╛╨╖╨┤╨░╨╜╨╕╤П ╨▒╤Н╨║╨░╨┐╨░
            document.getElementById('create-backup').addEventListener('click', createBackup);
            
            document.getElementById('video-recognition-notif-open')?.addEventListener('click', function() { vaOpenVideoModal(); });
            document.getElementById('global-image-viewer-close')?.addEventListener('click', function() { closeGlobalImageViewer(); });
            document.getElementById('global-image-viewer')?.addEventListener('click', function(e) {
                if (e.target && (e.target.id === 'global-image-viewer' || e.target.id === 'global-image-viewer-img')) closeGlobalImageViewer();
            });
            window.addEventListener('message', function(event) {
                if (!event || !event.data || typeof event.data !== 'object') return;
                if (event.data.type === 'takeout-guide-image-open') openGlobalImageViewer(event.data.src || '', event.data.alt || '');
                else if (event.data.type === 'takeout-guide-image-close') closeGlobalImageViewer();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && globalImageViewerOpen) closeGlobalImageViewer();
            });
            document.querySelectorAll('.add-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    const id = tab.getAttribute('data-add-tab');
                    if (id) switchAddTab(id);
                });
            });
            document.getElementById('ft-zip')?.addEventListener('change', function() {
                const f = this.files && this.files[0];
                if (f && ftWizardStep < 2) ftWizardStep = 2;
                ftUpdateWizardUi();
            });
            document.getElementById('ft-lang')?.addEventListener('change', function() {
                if (document.getElementById('ft-zip')?.files?.[0]) { ftWizardStep = 3; ftUpdateWizardUi(); }
            });
            document.getElementById('ft-year')?.addEventListener('input', function() {
                if (document.getElementById('ft-zip')?.files?.[0]) { ftWizardStep = 3; ftUpdateWizardUi(); }
            });
            document.getElementById('ft-start')?.addEventListener('click', async function() {
                if (ftWizardStep < 3) return;
                const inp = document.getElementById('ft-zip');
                const f = inp && inp.files && inp.files[0];
                if (!f) { await showAlert(translations.takeout_err_zip || 'Zip'); return; }
                const msg = document.getElementById('ft-msg');
                if (msg) msg.textContent = translations.video_file_uploading || 'Uploading...';
                let takeoutToken = '';
                try {
                    takeoutToken = await streamUploadFileInChunks(FTO_API, f, 'takeout', function(progress) {
                        ftSetBar((progress || 0) * 100);
                        if (msg) msg.textContent = (translations.video_file_uploading || 'Uploading...') + ' ' + String(Math.round((progress || 0) * 100)) + '%';
                    });
                } catch (e) {
                    if (msg) msg.textContent = 'тАФ';
                    ftSetBar(0);
                    await showAlert((translations.takeout_err_worker || '') + ' ' + ((e && e.message) ? e.message : 'upload'));
                    return;
                }
                const fd = new FormData();
                fd.append('action', 'upload_takeout');
                fd.append('takeout_token', takeoutToken);
                let d;
                try {
                    d = await fetch(FTO_API, { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
                } catch (e) { d = null; }
                if (!d || !d.ok) { await showAlert((translations.takeout_err_worker || '') + (d && d.error ? ' ' + d.error : '')); return; }
                ftStartPoll(d.job_id);
            });
            document.getElementById('ft-stop')?.addEventListener('click', async function() { await ftStopJob(); });
            const vaFile = document.getElementById('va-file');
            if (vaFile) vaFile.addEventListener('change', async () => {
                const f = vaFile.files && vaFile.files[0];
                const cb = document.getElementById('va-crop-btn');
                vaUploadToken = '';
                vaFileReady = false;
                vaSetFileActionEnabled(false);
                ['va-crop-x', 'va-crop-y', 'va-crop-w', 'va-crop-h'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
                if (!f) {
                    vaFileLoadHide();
                    if (cb) cb.style.display = 'none';
                    vaWizardStep = 1;
                    vaUpdateWizardUi();
                    return;
                }
                if (cb) cb.style.display = 'inline-block';
                vaWizardStep = 2;
                vaUpdateWizardUi();
                vaFileLoadSetVisible(true);
                vaFileLoadSetIndet(false);
                vaFileLoadSetPct(0);
                vaFileLoadSetLabel(translations.video_file_uploading || 'тАж');
                try {
                    vaUploadToken = await streamUploadFileInChunks(VA_API + '?' + vaBaseQuery(), f, 'video', function(progress) {
                        vaFileLoadSetIndet(false);
                        vaFileLoadSetPct(progress || 0);
                    });
                } catch (e) {
                    vaFileLoadHide();
                    await showAlert((translations.video_err_upload || 'Upload error') + ((e && e.message) ? ': ' + e.message : ''));
                    if (cb) cb.style.display = 'none';
                    vaWizardStep = 1;
                    vaUpdateWizardUi();
                    return;
                }
                vaFileLoadHide();
                vaFileReady = true;
                vaSetFileActionEnabled(true);
            });
            document.getElementById('va-lang')?.addEventListener('change', function() {
                if (document.getElementById('va-file')?.files?.[0] && vaIsYearValidForStep3()) vaWizardStep = 3;
                else if (document.getElementById('va-file')?.files?.[0]) vaWizardStep = 2;
                vaUpdateWizardUi();
            });
            document.getElementById('va-year-select')?.addEventListener('change', function() {
                const manual = document.getElementById('va-year-manual');
                if (manual) manual.style.display = this.value === 'manual' ? '' : 'none';
                if (document.getElementById('va-file')?.files?.[0] && vaIsYearValidForStep3()) vaWizardStep = 3;
                else if (document.getElementById('va-file')?.files?.[0]) vaWizardStep = 2;
                vaUpdateWizardUi();
            });
            document.getElementById('va-year-manual')?.addEventListener('input', function() {
                if (document.getElementById('va-file')?.files?.[0] && vaIsYearValidForStep3()) vaWizardStep = 3;
                else if (document.getElementById('va-file')?.files?.[0]) vaWizardStep = 2;
                vaUpdateWizardUi();
            });
            document.getElementById('va-crop-btn')?.addEventListener('click', async () => {
                if (!vaFileReady) return;
                const fi = document.getElementById('va-file').files[0];
                if (!fi || !vaUploadToken) return;
                const fd = new FormData();
                fd.append('video_token', vaUploadToken);
                fd.append('action', 'extract_frame');
                const y = vaGetYearValue();
                fd.append('current_year', (y === '' || y === '-') ? '-' : y);
                let blob;
                try {
                    vaFileLoadSetVisible(true);
                    vaFileLoadSetLabel(translations.video_file_uploading || 'тАж');
                    vaFileLoadSetPct(0);
                    vaFileLoadSetIndet(true);
                    blob = await vaXhrForm(VA_API + '?' + vaBaseQuery(), fd, true);
                } catch (e) { vaFileLoadHide(); await showAlert(translations.video_frame_error || 'Frame'); return; }
                vaFileLoadHide();
                const ct = (blob && blob.type) ? blob.type : '';
                if (ct.indexOf('image') < 0) { await showAlert(translations.video_frame_error || 'Frame'); return; }
                const img = document.getElementById('video-crop-img');
                if (img && img._vaBlobUrl) try { URL.revokeObjectURL(img._vaBlobUrl); } catch (e) {}
                img._vaBlobUrl = URL.createObjectURL(blob);
                img.src = img._vaBlobUrl;
                const cm = document.getElementById('video-crop-modal');
                if (cm) cm.style.display = 'flex';
                if (videoCropper) { videoCropper.destroy(); videoCropper = null; }
                videoCropper = new Cropper(img, { viewMode: 1, autoCropArea: 0.5 });
            });
            document.getElementById('va-crop-ok')?.addEventListener('click', () => {
                if (videoCropper) {
                    const d = videoCropper.getData(true);
                    document.getElementById('va-crop-x').value = String(d.x);
                    document.getElementById('va-crop-y').value = String(d.y);
                    document.getElementById('va-crop-w').value = String(d.width);
                    document.getElementById('va-crop-h').value = String(d.height);
                    videoCropper.destroy();
                    videoCropper = null;
                }
                const cm = document.getElementById('video-crop-modal');
                if (cm) cm.style.display = 'none';
                vaWizardStep = 3;
                vaUpdateWizardUi();
            });
            document.getElementById('va-crop-cancel')?.addEventListener('click', () => {
                if (videoCropper) { videoCropper.destroy(); videoCropper = null; }
                const cm = document.getElementById('video-crop-modal');
                if (cm) cm.style.display = 'none';
            });
            document.getElementById('va-start')?.addEventListener('click', async () => {
                vaSetRemoteJobMode(false);
                if (vaWizardStep < 3) return;
                if (!vaFileReady) return;
                const fi = document.getElementById('va-file').files[0];
                if (!fi || !vaUploadToken) return;
                const yRaw = vaGetYearValue();
                let yPost = yRaw;
                if (yRaw === '' || yRaw === '-') yPost = '-';
                else {
                    const y = parseInt(yRaw, 10);
                    if (isNaN(y) || y < 2000 || y > 2100) { await showAlert(translations.year_error || 'Year'); return; }
                    yPost = String(y);
                }
                const fd = new FormData();
                fd.append('video_token', vaUploadToken);
                fd.append('action', 'process_video');
                fd.append('current_year', yPost);
                fd.append('language', document.getElementById('va-lang').value);
                const cx = document.getElementById('va-crop-x').value;
                if (cx) {
                    fd.append('crop_x', cx);
                    fd.append('crop_y', document.getElementById('va-crop-y').value);
                    fd.append('crop_width', document.getElementById('va-crop-w').value);
                    fd.append('crop_height', document.getElementById('va-crop-h').value);
                }
                let d;
                try {
                    vaFileLoadSetVisible(true);
                    vaFileLoadSetLabel(translations.video_file_uploading || 'тАж');
                    vaFileLoadSetPct(0);
                    vaFileLoadSetIndet(true);
                    d = await vaXhrForm(VA_API + '?' + vaBaseQuery(), fd, false);
                } catch (e) { vaFileLoadHide(); await showAlert(translations.video_error || 'Error'); return; }
                vaFileLoadHide();
                if (!d || !d.ok) { await showAlert(vaMapProcessError(d && d.error)); return; }
                vaStartPoll(d.job_id);
            });
            document.getElementById('va-stop')?.addEventListener('click', async function() { await vaStopJob(); });
            document.querySelectorAll('#va-steps .va-step-dot').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (vaWizardLocked) return;
                    const s = parseInt(btn.getAttribute('data-step-target') || '1', 10);
                    if (isNaN(s)) return;
                    vaWizardStep = s;
                    vaUpdateWizardUi();
                });
            });
            document.querySelectorAll('#ft-steps .va-step-dot').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (ftWizardLocked) return;
                    const s = parseInt(btn.getAttribute('data-step-target') || '1', 10);
                    if (isNaN(s)) return;
                    ftWizardStep = s;
                    ftUpdateWizardUi();
                });
            });
            vaUpdateWizardUi();
            ftUpdateWizardUi();
            document.getElementById('video-confirm-import').addEventListener('click', async () => {
                syncVideoConfirmFromInputs();
                if (!pendingVideoRows.length) {
                    closeModal('video-confirm-modal');
                    return;
                }
                const fd = new FormData();
                fd.append('import_video_batch', '1');
                fd.append('rows', JSON.stringify(pendingVideoRows));
                let data = null;
                try {
                    const response = await fetch('', { method: 'POST', body: fd, credentials: 'same-origin' });
                    data = await response.json();
                    if (data && data.status === 'success' && data.new_steps) {
                        Object.entries(data.new_steps).forEach(([date, steps]) => {
                            stepsData = stepsData.filter(entry => entry.date !== date);
                            stepsData.push({ date, steps: parseInt(steps, 10) });
                        });
                        stepsData.sort((a, b) => new Date(a.date) - new Date(b.date));
                        renderChart(currentView, currentDate, currentDataType, currentChartType, currentLocale);
                        if (currentView === 'month') updateCalendar(currentLocale);
                        updateStatsNotification();
                        updateGoalNotification();
                    }
                } catch (e) {}
                if (!data || data.status !== 'success') {
                    await showAlert((translations.video_job_err || 'Error') + (data && data.message ? ': ' + data.message : ''));
                    return;
                }
                if (!data.new_steps || !Object.keys(data.new_steps).length) {
                    await showAlert(translations.video_no_data || 'No data');
                    return;
                }
                if (data && data.status === 'success' && data.new_steps && Object.keys(data.new_steps).length) {
                    await runVideoCleanup();
                }
                closeModal('video-confirm-modal');
            });
            document.getElementById('video-confirm-skip').addEventListener('click', async () => { await runVideoCleanup(); closeModal('video-confirm-modal'); });
            document.getElementById('video-confirm-close-x').addEventListener('click', () => { closeModal('video-confirm-modal'); });
            const vctw = document.getElementById('video-confirm-table-wrap');
            if (vctw) {
                vctw.addEventListener('change', (e) => { if (e.target && e.target.classList && e.target.classList.contains('vcr-inp')) { syncVideoConfirmFromInputs(); refreshVideoConfirmView(); } });
                vctw.addEventListener('focusin', (e) => { if (e.target && e.target.classList && e.target.classList.contains('vcr-steps')) e.target.value = parseStepsRaw(e.target.value); });
                vctw.addEventListener('blur', (e) => { if (e.target && e.target.classList && e.target.classList.contains('vcr-inp')) { if (e.target.classList.contains('vcr-steps')) { const t = e.target.value.replace(/\D/g, ''); e.target.value = t ? formatNumber(parseInt(t, 10) || 0) : ''; } syncVideoConfirmFromInputs(); refreshVideoConfirmView(); } }, true);
            }
            const vgBody = document.getElementById('video-confirm-gaps-body');
            if (vgBody) {
                vgBody.addEventListener('focusin', (e) => { if (e.target && e.target.classList && e.target.classList.contains('vcr-gap-step')) e.target.value = parseStepsRaw(e.target.value); });
                vgBody.addEventListener('focusout', (e) => { if (e.target && e.target.classList && e.target.classList.contains('vcr-gap-step')) { const t = e.target.value.replace(/\D/g, ''); e.target.value = t ? formatNumber(parseInt(t, 10) || 0) : ''; } }, true);
            }
            document.getElementById('video-confirm-gaps-btn')?.addEventListener('click', () => { openVideoGapsModal(); });
            document.getElementById('video-confirm-gaps-apply')?.addEventListener('click', () => {
                const byDmy = new Map();
                pendingVideoRows.forEach((r) => { byDmy.set(r.date, { date: r.date, steps: r.steps, t: r.t || '' }); });
                document.querySelectorAll('.vcr-gap-step').forEach((inp) => {
                    const dmy = inp.getAttribute('data-dmy');
                    const v = parseInt(parseStepsRaw(inp.value), 10);
                    if (!dmy || isNaN(v) || v < 0) return;
                    const prev = byDmy.get(dmy);
                    byDmy.set(dmy, { date: dmy, steps: v, t: prev ? prev.t : '' });
                });
                pendingVideoRows = Array.from(byDmy.values());
                sortPendingVideoRows();
                closeVideoGapsModal();
                refreshVideoConfirmView();
            });
            document.getElementById('video-confirm-gaps-close-x')?.addEventListener('click', () => { closeVideoGapsModal(); });
            const vgm = document.getElementById('video-confirm-gaps-modal');
            if (vgm) vgm.addEventListener('click', (e) => { if (e.target === vgm) closeVideoGapsModal(); });
            const takeoutGuideSegment = document.getElementById('takeout-guide-segment');
            const takeoutGuideToggle = document.getElementById('takeout-guide-toggle');
            if (takeoutGuideSegment && takeoutGuideToggle) {
                takeoutGuideToggle.addEventListener('click', () => {
                    const collapsed = takeoutGuideSegment.classList.toggle('is-collapsed');
                    takeoutGuideToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                });
            }
            // ╨Ъ╨╜╨╛╨┐╨║╨░ ╨▓╨╛╤Б╤Б╤В╨░╨╜╨╛╨▓╨╗╨╡╨╜╨╕╤П ╨╕╨╖ ╨▒╨╡╨║╨░╨┐╨░
            document.getElementById('restore-backup').addEventListener('click', async () => {
                await loadBackups();
                openModal('backups-modal');
            });
            
            // ╨Ъ╨╜╨╛╨┐╨║╨░ ╤Г╨┐╤А╨░╨▓╨╗╨╡╨╜╨╕╤П ╨▒╨╡╨║╨░╨┐╨░╨╝╨╕
            document.getElementById('manage-backups').addEventListener('click', async () => {
                await loadBackups();
                openModal('backups-modal');
            });
            
            // ╨Ъ╨╜╨╛╨┐╨║╨░ ╨╛╨▒╨╜╨╛╨▓╨╗╨╡╨╜╨╕╤П ╤Б╨┐╨╕╤Б╨║╨░ ╨▒╨╡╨║╨░╨┐╨╛╨▓
            document.getElementById('refresh-backups')?.addEventListener('click', async () => {
                await loadBackups();
            });
            
            // ╨Ъ╨╜╨╛╨┐╨║╨░ ╤Б╨║╨░╤З╨╕╨▓╨░╨╜╨╕╤П ╨▒╨╡╨║╨░╨┐╨░
            document.getElementById('download-backup').addEventListener('click', downloadBackup);
            
            // ╨Ъ╨╜╨╛╨┐╨║╨░ ╨┐╨╛╨╕╤Б╨║╨░
            document.getElementById('search-btn').addEventListener('click', searchInTable);
            
            // ╨Ю╤З╨╕╤Б╤В╨║╨░ ╨┐╨╛╨╕╤Б╨║╨░
            document.getElementById('clear-search').addEventListener('click', () => {
                document.getElementById('search-steps').value = '';
                document.getElementById('search-steps-to').value = '';
                document.getElementById('search-date-from').value = '';
                document.getElementById('search-date-to').value = '';
                renderExcelTable(1, '', '', '', '', '');
            });
            
            // ╨Ш╨╖╨╝╨╡╨╜╨╡╨╜╨╕╨╡ ╨╛╨┐╨╡╤А╨░╤В╨╛╤А╨░ ╨┐╨╛╨╕╤Б╨║╨░
            document.getElementById('search-operator').addEventListener('change', function() {
                const rangeInput = document.getElementById('search-steps-to');
                if (this.value === 'range') {
                    rangeInput.style.display = 'inline-block';
                } else {
                    rangeInput.style.display = 'none';
                    rangeInput.value = '';
                }
            });
            
            // Enter ╨┤╨╗╤П ╨┐╨╛╨╕╤Б╨║╨░
            document.getElementById('search-steps').addEventListener('keypress', e => { 
                if (e.key === 'Enter') searchInTable(); 
            });
            
            document.getElementById('search-steps-to').addEventListener('keypress', e => { 
                if (e.key === 'Enter') searchInTable(); 
            });
            document.getElementById('search-date-from').addEventListener('change', searchInTable);
            document.getElementById('search-date-to').addEventListener('change', searchInTable);
            
            // ╨Ч╨░╨║╤А╤Л╤В╨╕╨╡ ╨╝╨╛╨┤╨░╨╗╤М╨╜╤Л╤Е ╨╛╨║╨╛╨╜
            document.querySelectorAll('.modal-close').forEach(btn => {
                btn.addEventListener('click', () => {
                    const modal = btn.closest('[id$="-modal"]');
                    if (modal) {
                        closeModal(modal.id);
                    }
                });
            });
            
            document.querySelectorAll('.close-notification').forEach(btn => {
                btn.addEventListener('click', () => {
                    const notification = btn.closest('.notification');
                    if (!notification) return;
                    if (notification.id === 'video-recognition-notification') {
                        snoozeVideoNotifications12h();
                        return;
                    }
                    if (notification.id === 'stats-notification') {
                        cancelStatsNotifTimer();
                        statsNotifRevealDone = false;
                        snoozePanelNotif12h('stats');
                    } else if (notification.id === 'goal-notification') {
                        cancelGoalNotifTimer();
                        goalNotifRevealDone = false;
                        snoozePanelNotif12h('goal');
                    }
                    notification.style.display = 'none';
                });
            });
            
            // ╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╨╜╨░╤Б╤В╤А╨╛╨╡╨║
            document.getElementById('settings-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                try {
                    const response = await fetch('', { method: 'POST', body: new FormData(e.target) });
                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        closeModal('settings-modal');
                        location.reload();
                    } else {
                        await showAlert(`${translations.failed_save_settings || 'Error saving settings'}: ${data.message}`);
                    }
                } catch (err) {
                    await showAlert(`${translations.failed_save_settings || 'Error saving settings'}: ${err.message}`);
                }
            });
            
            // ╨б╨╛╤Е╤А╨░╨╜╨╡╨╜╨╕╨╡ ╤И╨░╨│╨╛╨▓
            document.getElementById('add-steps-submit').addEventListener('click', saveSteps);
            const stepsInpEl = document.getElementById('steps-input');
            stepsInpEl.addEventListener('focus', function() { this.value = parseStepsRaw(this.value); });
            stepsInpEl.addEventListener('blur', function() { const t = this.value.replace(/\D/g, ''); this.value = t ? formatNumber(parseInt(t, 10) || 0) : ''; });
            stepsInpEl.addEventListener('keypress', e => { if (e.key === 'Enter') saveSteps(); });
            
            // ╨Я╤А╨╛╨║╤А╤Г╤В╨║╨░ ╨┤╨░╤В╤Л
            document.getElementById('selected-date-input').addEventListener('wheel', handleDateWheelScroll);
            
            // ╨Я╤А╨╡╨┤╨╛╤В╨▓╤А╨░╤Й╨╡╨╜╨╕╨╡ ╨┐╤А╨╛╨║╤А╤Г╤В╨║╨╕ ╨▓ ╤Б╨╛╤Б╨╡╨┤╨╜╨╕╤Е ╨┤╨░╤В╨░╤Е
            document.querySelectorAll('.adjacent-dates').forEach(container => {
                container.addEventListener('wheel', e => e.preventDefault());
            });
            
            // ╨Ъ╨╗╨╕╨║ ╨┐╨╛ ╨║╨░╨╗╨╡╨╜╨┤╨░╤А╤О
            document.getElementById('calendar').addEventListener('click', e => {
                const cell = e.target.closest('td[data-date]');
                if (cell) { 
                    const date = cell.getAttribute('data-date'); 
                    applyManualDate(date, currentLocale);
                    openModal('add-steps-modal');
                }
            });
            
            // ╨Ю╨▒╤А╨░╨▒╨╛╤В╤З╨╕╨║╨╕ ╨┤╨╗╤П Excel ╨╝╨╛╨┤╨░╨╗╤М╨╜╨╛╨│╨╛ ╨╛╨║╨╜╨░
            document.getElementById('delete-selected').addEventListener('click', deleteSelectedCells);
        }
        
        // ╨Ч╨░╨┐╤Г╤Б╨║ ╨┐╤А╨╕╨╗╨╛╨╢╨╡╨╜╨╕╤П
        initApp();
    </script>
</body>
