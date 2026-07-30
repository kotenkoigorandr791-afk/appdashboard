<?php
// ─── Инициализация ────────────────────────────────────────────────────────────
require_once dirname(__DIR__, 3) . '/project/i18n_helpers.php';
$language     = ap_normalize_i18n_lang($_GET['lang'] ?? 'en');
$theme        = $_GET['theme'] ?? 'light';
$color        = $_GET['color'] ?? '#007bff';
$translations = ap_load_i18n_from_dir(__DIR__ . '/..', $language);
$GLOBALS['_t_i18n'] = $translations;
$stepsVideoOcrTess = ['en' => 'eng', 'ru' => 'rus', 'uk' => 'ukr', 'be' => 'bel', 'pl' => 'pol', 'cs' => 'ces'][$language] ?? 'eng';

$userEmail       = get_current_user_email();
$safeUserName    = get_current_user_folder();
$currentUser     = $safeUserName;
$userName        = $userEmail ? explode('@', $userEmail)[0] : 'default_user';
$userPhotoFromDB = $userEmail ? getUserPhoto($userEmail) : null;

$usersDir = __DIR__ . '/../users';
$userDir  = "$usersDir/$safeUserName";
checkDirectory($usersDir);
checkDirectory($userDir);

$profilePhoto = 'default_profile.jpg';
if (!empty($userPhotoFromDB)) {
    $photoSrc = trim((string)$userPhotoFromDB);
    if (filter_var($photoSrc, FILTER_VALIDATE_URL)) {
        $profilePhoto = $photoSrc;
    } else {
        $rootDir = dirname(__DIR__, 3);
        $relPhoto = ltrim($photoSrc, '/');
        $rootPhotoPath = $rootDir . '/' . $relPhoto;
        if ($relPhoto !== '' && is_file($rootPhotoPath)) {
            $profilePhoto = '/' . $relPhoto . '?v=' . (int)@filemtime($rootPhotoPath);
        } elseif ($photoSrc !== '' && is_file($photoSrc)) {
            $profilePhoto = $photoSrc;
        } else {
            $photoPath = "$userDir/profile.jpg";
            if (is_file($photoPath) && filesize($photoPath) > 0) {
                $profilePhoto = "users/$safeUserName/profile.jpg?v=" . (int)@filemtime($photoPath);
            }
        }
    }
}

// Открываем пользовательскую БД
$userDb = getUserDB($userDir);
if (!$userDb) {
    throw new RuntimeException('Steps database unavailable');
}
normalizeStepsDatesTable($userDb);

// Загружаем настройки
$userSettings = loadUserSettings($userDb, $defaultSettings);

// ─── Инициализируем шаги: только последние 12 месяцев для начального рендера ─
$today        = (new DateTime())->format('Y-m-d');
$yearAgo      = (new DateTime())->modify('-12 months')->format('Y-m-d');

// Если БД пуста — заполняем месяцем нулей
$totalCount = (int)$userDb->querySingle('SELECT COUNT(*) FROM steps');
if ($totalCount === 0) {
    $sd = (new DateTime())->modify('-1 month');
    $ed = new DateTime();
    $userDb->exec('BEGIN');
    while ($sd <= $ed) {
        upsertStep($userDb, $sd->format('Y-m-d'), 0);
        $sd->modify('+1 day');
    }
    $userDb->exec('COMMIT');
}

// Убедимся, что сегодняшний день есть в базе
$userDb->exec("INSERT INTO steps (date, steps) VALUES ('$today', 0) ON CONFLICT (date) DO NOTHING");

// Определяем самую раннюю дату
[$earliestInDB, $latestInDB] = getStepsDateBounds($userDb);
$earliestDate = $earliestInDB ?? $today;

// Загружаем весь доступный диапазон дат для JS
$jsRangeFrom = $earliestInDB ?? $today;
$jsRangeTo = ($latestInDB && strcmp($latestInDB, $today) > 0) ? $latestInDB : $today;
error_log("Loading steps range: from=$jsRangeFrom, to=$jsRangeTo, earliest=$earliestInDB, latest=$latestInDB, today=$today");
$stepsDataRaw = loadStepsRange($userDb, $jsRangeFrom, $jsRangeTo);
error_log("Loaded steps count: " . count($stepsDataRaw));

// Заполняем пропуски нулями в загруженном диапазоне
$cd = new DateTime($jsRangeFrom);
$ed2 = new DateTime($jsRangeTo);
$toInsert = [];
while ($cd <= $ed2) {
    $ds = $cd->format('Y-m-d');
    if (!isset($stepsDataRaw[$ds])) $toInsert[] = $ds;
    $cd->modify('+1 day');
}
if ($toInsert) {
    $userDb->exec('BEGIN');
    foreach ($toInsert as $ds) {
        upsertStep($userDb, $ds, 0);
        $stepsDataRaw[$ds] = ['date' => $ds, 'steps' => 0];
    }
    $userDb->exec('COMMIT');
}

$stepsData = array_values($stepsDataRaw);
usort($stepsData, fn($a, $b) => strcmp($a['date'], $b['date']));
error_log("Final stepsData count: " . count($stepsData) . ", first 3: " . json_encode(array_slice($stepsData, 0, 3)));
