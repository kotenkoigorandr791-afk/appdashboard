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
if (!file_exists($userDir)) mkdir($userDir, 0755, true);

$udb = getUserSQLite($currentUserEmail);

// Load settings from user DB
$userSettings = [
    'monthly_budget' => (float)dbGetSetting($udb, 'monthly_budget', 100),
    'currency'       => dbGetSetting($udb, 'currency', 'USD'),
    'show_incomes'   => (bool)(int)dbGetSetting($udb, 'show_incomes', 1),
    'stores'         => dbGetStores($udb),
];

$tableSettings = [
    'sort_by'      => dbGetTableSetting($udb, 'sort_by',       'date_asc'),
    'rows_per_page'=> (int)dbGetTableSetting($udb, 'rows_per_page', 25),
    'start_date'   => dbGetTableSetting($udb, 'start_date',    ''),
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
    if (!file_exists($userDir)) mkdir($userDir, 0755, true);
    $db = getDB();
    if ($db) {
        $sid = session_id();
        foreach ([$sid, $_COOKIE['PHPSESSID'] ?? null] as $s) {
            if (!$s) continue;
            try {
                $st = $db->prepare('SELECT user_data FROM active_sessions WHERE session_id = :s');
                $st->bindValue(':s', $s, SQLITE3_TEXT);
                $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
                if ($r && isset($r['user_data'])) {
                    $ud = json_decode($r['user_data'], true);
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
                }
            } catch (Exception $e) {}
        }
        if ($userEmail !== 'default_user') {
            try {
                $st = $db->prepare('SELECT photo FROM users WHERE email = :e');
                $st->bindValue(':e', $userEmail, SQLITE3_TEXT);
                $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
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
        $st->bindValue(':e', $currentUserEmail, SQLITE3_TEXT);
        $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
        if ($r && !empty($r['name'])) $displayName = $r['name'];
    } catch (Exception $e) {}
}

$visibleStores = array_filter($userSettings['stores'], fn($s) => $s['visible'] ?? true);

function getUserBackupDir($userEmail) {
    $safe = getSafeFolderName($userEmail);
    $dir  = __DIR__ . '/../backups/' . $safe;
    if (!file_exists($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function exportExpensesToCSV($udb, $stores) {
    $storeNames = array_map(fn($s) => $s['name'], $stores);
    $lines = [];
    $lines[] = implode(',', array_map(fn($s) => '"'.$s.'"', array_merge(['Date'], $storeNames)));
    $mm = $udb->query("SELECT MIN(date) AS lo, MAX(date) AS hi FROM expenses")->fetchArray(SQLITE3_ASSOC);
    if (empty($mm['lo'])) return implode("\n", $lines);
    foreach (listCalendarDaysInclusive($mm['lo'], $mm['hi']) as $d) {
        $cells = [$d];
        foreach ($storeNames as $sn) {
            $st = $udb->prepare('SELECT amount FROM expenses WHERE date=:d AND store=:s');
            $st->bindValue(':d', $d); $st->bindValue(':s', $sn);
            $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
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
