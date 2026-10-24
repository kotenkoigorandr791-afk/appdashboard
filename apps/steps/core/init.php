<?php
// Initialization
require_once dirname(__DIR__, 3) . '/project/i18n_helpers.php';
require_once __DIR__ . '/health_connect_ingest.php';
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

$userDb = getUserDB($userDir);
if (!$userDb) {
    throw new RuntimeException('Steps database unavailable');
}
$userSettings = loadUserSettings($userDb, $defaultSettings);
$today = (new DateTime())->format('Y-m-d');
[$earliestInDB, $latestInDB] = getStepsDateBounds($userDb);
$jsRangeFrom = $earliestInDB ?? (new DateTime())->modify('-1 month')->format('Y-m-d');
$jsRangeTo = ($latestInDB && strcmp($latestInDB, $today) > 0) ? $latestInDB : $today;
$stepsDataRaw = loadStepsRange($userDb, $jsRangeFrom, $jsRangeTo);
$stepsData = steps_fill_range($stepsDataRaw, $jsRangeFrom, $jsRangeTo);
$healthMetrics = hc_read_metrics_range($userDir, $jsRangeFrom, $jsRangeTo);
