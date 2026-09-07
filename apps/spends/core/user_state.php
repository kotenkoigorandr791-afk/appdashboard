<?php
// ─── Current user setup ────────────────────────────────────────────────────────
$currentUserEmail = getCurrentUserFromActiveSessions();
if (!$currentUserEmail || $currentUserEmail === 'default_user') {
    $currentUserEmail  = 'default_user';
    $currentUserFolder = 'default_user';
} else {
    $currentUserFolder = getSafeFolderName($currentUserEmail);
}
$currentUser = $currentUserEmail;

$userDir = APPS_DATA_DIR . $currentUserFolder;
if (!file_exists($userDir)) mkdir($userDir, SPENDS_RUNTIME_DIR_MODE, true);
@chmod($userDir, SPENDS_RUNTIME_DIR_MODE);

$udb = getUserSQLite($currentUserEmail);

// Load settings from user DB
$userSettings = [
    'monthly_budget' => (float)dbGetSetting($udb, 'monthly_budget', 100),
    'currency'       => dbGetSetting($udb, 'currency', 'USD'),
    'show_incomes'       => (bool)(int)dbGetSetting($udb, 'show_incomes', 1),
    'show_trend'         => (bool)(int)dbGetSetting($udb, 'show_trend', 0),
    'trim_empty_months'  => (bool)(int)dbGetSetting($udb, 'trim_empty_months', 0),
    'stores'             => dbGetStores($udb),
];

$tableSettings = [
    'sort_by'       => dbGetTableSetting($udb, 'sort_by',       'date_asc'),
    'months_window' => (int)dbGetTableSetting($udb, 'months_window', 1),
    'start_date'    => dbGetTableSetting($udb, 'start_date',    ''),
];

// Profile photo & display name
define('DASHBOARD_ROOT', realpath(__DIR__ . '/../../..') ?: (__DIR__ . '/../../..'));
function dashboardPhotoWebPath($photo) {
    $photo = trim((string)$photo);
    if ($photo === '' || $photo === 'default_avatar.png') return '';
    if (filter_var($photo, FILTER_VALIDATE_URL)) return $photo;
    if (str_starts_with($photo, '/')) return $photo;
    $rel = ltrim(str_replace('\\', '/', $photo), '/');
    $abs = DASHBOARD_ROOT . '/' . $rel;
    if (file_exists($abs)) return '/' . $rel;
    return '';
}
function getUserPhoto($userEmail) {
    $safe     = getSafeFolderName($userEmail);
    $userDir  = APPS_DATA_DIR . $safe;
    $photoPath= $userDir . '/profile.jpg';
    if (file_exists($photoPath)) return "users/{$safe}/profile.jpg?t=" . filemtime($photoPath);
    if (!file_exists($userDir)) mkdir($userDir, SPENDS_RUNTIME_DIR_MODE, true);
    @chmod($userDir, SPENDS_RUNTIME_DIR_MODE);
    $db = getDB();
    if ($db) {
        $sessionRow = function_exists('spendsActiveSessionRow') ? spendsActiveSessionRow() : null;
        $ud = is_array($sessionRow['user_data'] ?? null) ? $sessionRow['user_data'] : [];
        if (!empty($ud['photo'])) {
            $web = dashboardPhotoWebPath($ud['photo']);
            if ($web !== '') {
                if (filter_var($web, FILTER_VALIDATE_URL)) {
                    $pd = @file_get_contents($web);
                    if ($pd !== false) { file_put_contents($photoPath, $pd); return "users/{$safe}/profile.jpg?t=" . filemtime($photoPath); }
                } else {
                    $abs = DASHBOARD_ROOT . parse_url($web, PHP_URL_PATH);
                    if ($abs && file_exists($abs) && @copy($abs, $photoPath)) return "users/{$safe}/profile.jpg?t=" . filemtime($photoPath);
                    return $web;
                }
            }
        }
        if ($userEmail !== 'default_user') {
            try {
                $st = $db->prepare('SELECT photo FROM users WHERE email = :e');
                $st->bindValue(':e', $userEmail, PDO::PARAM_STR);
                $r = $st->execute()->fetch(PDO::FETCH_ASSOC);
                if ($r && !empty($r['photo']) && $r['photo'] !== 'default_avatar.png') {
                    $web = dashboardPhotoWebPath($r['photo']);
                    if ($web !== '') {
                        if (filter_var($web, FILTER_VALIDATE_URL)) {
                            $pd = @file_get_contents($web);
                            if ($pd !== false) { file_put_contents($photoPath, $pd); return "users/{$safe}/profile.jpg?t=" . filemtime($photoPath); }
                        } else {
                            $abs = DASHBOARD_ROOT . parse_url($web, PHP_URL_PATH);
                            if ($abs && file_exists($abs) && @copy($abs, $photoPath)) return "users/{$safe}/profile.jpg?t=" . filemtime($photoPath);
                            return $web;
                        }
                    }
                }
            } catch (Exception $e) {}
        }
    }
    return 'default_profile.jpg';
}

$profilePhoto = getUserPhoto($currentUserEmail);
$displayName  = $currentUserEmail;
$db = getDB();
if ($db && $currentUserEmail !== 'default_user') {
    try {
        $st = $db->prepare('SELECT name FROM users WHERE email = :e');
        $st->bindValue(':e', $currentUserEmail, PDO::PARAM_STR);
        $r = $st->execute()->fetch(PDO::FETCH_ASSOC);
        if ($r && !empty($r['name'])) $displayName = $r['name'];
    } catch (Exception $e) {}
}

$visibleStores = array_filter($userSettings['stores'], fn($s) => $s['visible'] ?? true);

function getUserBackupDir($userEmail) {
    global $backupBaseDir;
    $safe = getSafeFolderName($userEmail);
    $base = isset($backupBaseDir) && $backupBaseDir !== '' ? $backupBaseDir : rtrim(spends_runtime_dir('backups'), '/');
    $dir  = rtrim($base, '/') . '/' . $safe;
    if (!file_exists($dir)) mkdir($dir, SPENDS_RUNTIME_DIR_MODE, true);
    @chmod($dir, SPENDS_RUNTIME_DIR_MODE);
    return $dir;
}

function exportExpensesToCSV($udb, $stores) {
    $storeNames = array_map(fn($s) => $s['name'], $stores);
    $lines = [];
    $lines[] = implode(',', array_map(fn($s) => '"'.$s.'"', array_merge(['Date'], $storeNames)));
    $mm = $udb->query("SELECT MIN(date) AS lo, MAX(date) AS hi FROM expenses")->fetch(PDO::FETCH_ASSOC);
    if (empty($mm['lo'])) return implode("\n", $lines);
    foreach (listCalendarDaysInclusive($mm['lo'], $mm['hi']) as $d) {
        $cells = [$d];
        foreach ($storeNames as $sn) {
            $st = $udb->prepare('SELECT amount FROM expenses WHERE date=:d AND store=:s');
            $st->bindValue(':d', $d); $st->bindValue(':s', $sn);
            $r = $st->execute()->fetch(PDO::FETCH_ASSOC);
            $cells[] = $r ? number_format((float)$r['amount'], 2, '.', '') : '0.00';
        }
        $lines[] = implode(',', $cells);
    }
    return implode("\n", $lines);
}

function listCalendarDaysInclusive($startYmd, $endYmd) {
    if ($startYmd === '' || $endYmd === '' || strcmp($startYmd, $endYmd) > 0) return [];
    $out = [];
    $d = new DateTime($startYmd);
    $end = new DateTime($endYmd);
    while ($d <= $end) {
        $out[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
    return $out;
}
