<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
require_once dirname(__DIR__, 3) . '/lib/app_session.php';
app_session_start();

// Configuration
define('BIN_PATH', __DIR__ . '/bin/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('FRAMES_DIR', __DIR__ . '/frames/');
define('CHUNK_SESSIONS_DIR', dirname(__DIR__) . '/upload_sessions');
define('VIDEO_JOB_RUNNING_STALE_SEC', 86400);

// URL parameters
$allowedPostTesseract = ['eng', 'rus', 'ukr', 'bel'];
$lang = 'en';
if (isset($_POST['language']) && is_string($_POST['language'])) {
    $pLang = trim($_POST['language']);
    if (in_array($pLang, $allowedPostTesseract, true)) {
        $inverseTessUi = ['eng' => 'en', 'rus' => 'ru', 'ukr' => 'uk', 'bel' => 'be'];
        $lang = $inverseTessUi[$pLang] ?? 'en';
    } elseif (in_array($pLang, ['en', 'ru', 'uk', 'be'], true)) {
        $lang = $pLang;
    }
} elseif (!isset($_POST['language']) && isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ru', 'uk', 'be'], true)) {
    $lang = $_GET['lang'];
}
$theme = isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark']) ? $_GET['theme'] : 'light';
$color = isset($_GET['color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $_GET['color']) ? $_GET['color'] : '#007bff';

$tesseractLangMap = [
    'en' => 'eng',
    'ru' => 'rus',
    'uk' => 'ukr',
    'be' => 'bel'
];
$tessSelForForms = (($tesseractLangMap[$lang] ?? 'eng'));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['language']) && is_string($_POST['language']) && in_array(trim($_POST['language']), $allowedPostTesseract, true)) {
    $tessSelForForms = trim($_POST['language']);
}

// Translations
$translations = [
    'en' => [
        'title' => 'Enhanced Video Step Counter',
        'select_language' => 'Select Language:',
        'select_video' => '📁 Select video file:',
        'enter_year' => '📅 Enter current year (or - for current year):',
        'start_analysis' => '🚀 Start Analysis',
        'results' => '✅ Found Data:',
        'date' => 'Date',
        'steps' => 'Steps',
        'frame' => 'Frame',
        'download' => '📥 Download Report',
        'no_data' => '⚠ No data found. Check results/ folder.',
        'no_file' => 'Report not found. Check results/ folder.',
        'year_error' => 'Please enter a valid year (2000-2100) or "-".',
        'video_error' => 'Please select a video file.',
        'save_edited' => '💾 Save Edited Values',
        'processing' => '⏳ Processing...',
        'save_success' => '✅ Values saved successfully.',
        'save_error' => '❌ Error saving values.',
        'manage_results' => '📋 Manage Results',
        'results_table' => 'Results Files',
        'file_name' => 'File Name',
        'file_date' => 'Creation Date',
        'actions' => 'Actions',
        'view' => 'View',
        'delete' => 'Delete',
        'close' => 'Close',
        'progress_label' => 'Progress',
        'eta' => 'ETA',
        'job_err' => 'Processing error',
        'job_start' => 'Starting background job',
        'iframe_result_hint' => 'The results are shown in the main window. You can add them to your steps or discard.',
    ],
    'ru' => [
        'title' => '🎥 Улучшенный анализатор данных о шагах',
        'select_language' => 'Выберите язык:',
        'select_video' => '📁 Выберите видео файл:',
        'enter_year' => '📅 Введите текущий год (или - для текущего года):',
        'start_analysis' => '🚀 Начать анализ',
        'results' => '✅ Найденные данные:',
        'date' => 'Дата',
        'steps' => 'Шаги',
        'frame' => 'Кадр',
        'download' => '📥 Скачать отчет',
        'no_data' => '⚠ Данные не найдены. Проверьте папку results/.',
        'no_file' => 'Отчет не найден. Проверьте папку results/.',
        'year_error' => 'Пожалуйста, введите корректный год (2000-2100) или "-".',
        'video_error' => 'Пожалуйста, выберите видео файл.',
        'save_edited' => '💾 Сохранить исправленные значения',
        'processing' => '⏳ Обработка...',
        'save_success' => '✅ Значения успешно сохранены.',
        'save_error' => '❌ Ошибка при сохранении значений.',
        'manage_results' => '📋 Управление результатами',
        'results_table' => 'Файлы результатов',
        'file_name' => 'Имя файла',
        'file_date' => 'Дата создания',
        'actions' => 'Действия',
        'view' => 'Просмотреть',
        'delete' => 'Удалить',
        'close' => 'Закрыть',
        'progress_label' => 'Прогресс',
        'eta' => 'Осталось, с',
        'job_err' => 'Ошибка обработки',
        'job_start' => 'Запуск в фоне',
        'iframe_result_hint' => 'Результаты откроются в основном окне. Вы сможете добавить шаги в базу или отклонить.',
    ],
    'uk' => [
        'title' => '🎥 Поліпшений аналізатор кроків із відео',
        'select_language' => 'Виберіть мову:',
        'select_video' => '📁 Виберіть відеофайл:',
        'enter_year' => '📅 Введіть поточний рік (або - для поточного року):',
        'start_analysis' => '🚀 Почати аналіз',
        'results' => '✅ Знайдені дані:',
        'date' => 'Дата',
        'steps' => 'Кроки',
        'frame' => 'Кадр',
        'download' => '📥 Завантажити звіт',
        'no_data' => '⚠ Дані не знайдено. Перевірте папку results/.',
        'no_file' => 'Звіт не знайдено. Перевірте папку results/.',
        'year_error' => 'Будь ласка, введіть правильний рік (2000-2100) або "-".',
        'video_error' => 'Будь ласка, виберіть відеофайл.',
        'save_edited' => '💾 Зберегти відредаговані значення',
        'processing' => '⏳ Обробка...',
        'save_success' => '✅ Значення успішно збережено.',
        'save_error' => '❌ Помилка при збереженні значень.',
        'manage_results' => '📋 Керування результатами',
        'results_table' => 'Файли результатів',
        'file_name' => 'Ім\'я файлу',
        'file_date' => 'Дата створення',
        'actions' => 'Дії',
        'view' => 'Переглянути',
        'delete' => 'Видалити',
        'close' => 'Закрити'
    ],
    'be' => [
        'title' => '🎥 Пашыраны аналізатар дадзеных пра крокі',
        'select_language' => 'Выберыце мову:',
        'select_video' => '📁 Выберыце відэафайл:',
        'enter_year' => '📅 Увядзіце бягучае года (або - для бягучага года):',
        'start_analysis' => '🚀 Пачаць аналіз',
        'results' => '✅ Знойдзеныя дадзеныя:',
        'date' => 'Дата',
        'steps' => 'Крокі',
        'frame' => 'Кадр',
        'download' => '📥 Спампаваць звіт',
        'no_data' => '⚠ Дадзеныя не знойдзены. Праверце папку results/.',
        'no_file' => 'Звіт не знойдзены. Праверце папку results/.',
        'year_error' => 'Калі ласка, увядзіце правільны год (2000-2100) або "-".',
        'video_error' => 'Калі ласка, выберыце відэафайл.',
        'save_edited' => '💾 Захаваць выпраўленыя значэнні',
        'processing' => '⏳ Абробка...',
        'save_success' => '✅ Значэнні паспяхова захаваны.',
        'save_error' => '❌ Памылка пры захаванні значэнняў.',
        'manage_results' => '📋 Кіраванне вынікамі',
        'results_table' => 'Файлы вынікаў',
        'file_name' => 'Назва файла',
        'file_date' => 'Дата стварэння',
        'actions' => 'Дзеянні',
        'view' => 'Прагледзець',
        'delete' => 'Выдаліць',
        'close' => 'Зачыніць'
    ]
];
foreach (['uk' => ['progress_label' => 'Прогрес', 'eta' => 'Залишилось, с', 'job_err' => 'Помилка обробки', 'job_start' => 'Фоновий запуск', 'iframe_result_hint' => 'Результати в головному вікні. Можна додати кроки або відхилити.'], 'be' => ['progress_label' => 'Прагрэс', 'eta' => 'Засталося, с', 'job_err' => 'Памылка', 'job_start' => 'Фонавы запуск', 'iframe_result_hint' => 'Вынікі ў асноўным акне. Можаце дадаць крокі або адхіліць.']] as $lk => $extra) {
    foreach ($extra as $k => $v) {
        $translations[$lk][$k] = $v;
    }
}
$trans = $translations[$lang];
require_once __DIR__ . '/../auth_common.php';
require_once __DIR__ . '/../../../lib/app_db.php';
$safeName = get_current_user_folder();
if (!defined('RESULTS_DIR')) {
    define('USER_DIR', __DIR__ . '/../users/' . $safeName);
    define('RESULTS_DIR', USER_DIR . '/results/');
    define('JOBS_DIR', USER_DIR . '/video_jobs/');
}
$folders = [UPLOAD_DIR, FRAMES_DIR, CHUNK_SESSIONS_DIR, dirname(__DIR__) . '/users', USER_DIR, RESULTS_DIR, JOBS_DIR];
foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }
}
require_once __DIR__ . '/../video_job_sqlite.php';
function steps_notify_video_job_once(?AppDatabase $db, string $jobId, array $state, ?string $statePath = null): array {
    $status = (string)($state['status'] ?? '');
    if (!in_array($status, ['done','error'], true) || !empty($state['notification_sent'])) return $state;
    $email = get_current_user_email() ?: '';
    $title = $status === 'done' ? 'Steps video import finished' : 'Steps video import failed';
    $message = $status === 'done' ? ('Processed: ' . (int)($state['processed'] ?? 0) . ' / ' . (int)($state['total'] ?? 0)) : (string)($state['message'] ?? 'worker_error');
    app_notify($email, 'steps', $status === 'done' ? 'success' : 'error', $title, $message, 'worker_finished', ['job_id' => $jobId, 'kind' => 'video']);
    app_metric($email, 'steps', 'video_job_finished', 1, 'job', ['job_id' => $jobId, 'status' => $status]);
    $state['notification_sent'] = 1;
    if ($db) {
        try { video_job_save($db, $jobId, $state); } catch (Throwable $e) {}
    } elseif ($statePath) {
        @file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    return $state;
}
function analize_find_tesseract(): string {
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    if ((\PHP_OS_FAMILY ?? '') === 'Windows') {
        $exe = BIN_PATH . 'tesseract.exe';
        if (is_file($exe)) {
            return $memo = ((realpath($exe) ?: $exe));
        }
        return $memo = 'tesseract.exe';
    }
    $tev = getenv('TESSERACT_BINARY');
    foreach ([false !== $tev && $tev !== '' ? (string)$tev : '', '/usr/bin/tesseract', '/usr/local/bin/tesseract'] as $p) {
        if ($p !== '' && is_file($p) && is_readable($p)) {
            return $memo = (($rp = realpath($p)) ? $rp : $p);
        }
    }
    $fromShell = trim((string)@shell_exec('PATH=/usr/local/bin:/usr/bin:/snap/bin:' . (getenv('PATH') ?: '') . ' command -v tesseract 2>/dev/null'));
    if ($fromShell !== '' && $fromShell !== 'tesseract.exe') {
        return $memo = $fromShell;
    }
    return $memo = 'tesseract';
}
function analize_find_tessdata(): string {
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    $tp = getenv('TESSDATA_PREFIX');
    $tdCandidates = ['/usr/share/tesseract-ocr/5/tessdata', '/usr/share/tesseract-ocr/4/tessdata', '/usr/share/tesseract-ocr/tessdata', '/usr/share/tessdata'];
    if ($tp !== false && $tp !== '') {
        array_unshift($tdCandidates, rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim((string)$tp, "/\\")), DIRECTORY_SEPARATOR));
    }
    foreach ($tdCandidates as $td) {
        if ($td !== '' && is_dir($td) && is_file($td . DIRECTORY_SEPARATOR . 'eng.traineddata')) {
            return $memo = (($rp = realpath($td)) ? $rp : $td);
        }
    }
    $bundled = BIN_PATH . 'tessdata';
    if (is_dir($bundled) && is_file($bundled . DIRECTORY_SEPARATOR . 'eng.traineddata')) {
        return $memo = ((realpath($bundled) ?: $bundled));
    }
    return $memo = '/usr/share/tesseract-ocr/5/tessdata';
}
function analize_find_ffmpeg(): string {
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    foreach (['FFMPEG_BINARY', 'FFMPEG_PATH'] as $ek) {
        $v = getenv($ek);
        if ($v !== false && $v !== '' && is_string($v) && is_file($v)) {
            return $memo = ((realpath($v) ?: $v));
        }
    }
    if (PHP_OS_FAMILY === 'Windows') {
        $exe = BIN_PATH . 'ffmpeg.exe';
        if (is_file($exe)) {
            return $memo = ((realpath($exe) ?: $exe));
        }
    }
    foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/snap/bin/ffmpeg'] as $abs) {
        if (is_file($abs) && (PHP_OS_FAMILY === 'Windows' || @is_executable($abs))) {
            return $memo = $abs;
        }
    }
    $extra = getenv('PATH');
    $fromShell = trim((string)@shell_exec('PATH=/usr/local/bin:/usr/bin:/snap/bin:/bin:/sbin' . (($extra !== false && $extra !== '') ? ':' . $extra : '') . ' command -v ffmpeg 2>/dev/null'));
    if ($fromShell !== '' && is_file($fromShell)) {
        return $memo = $fromShell;
    }
    return $memo = 'ffmpeg';
}
function analize_extract_sample_frame(string $videoPath, ?string &$debugOut = null): ?string {
    $debugOut = '';
    if (!is_file($videoPath) || !is_readable($videoPath)) {
        $debugOut = 'video not readable';
        return null;
    }
    $frFrames = FRAMES_DIR;
    if ((!is_dir($frFrames) && !@mkdir($frFrames, 0777, true)) || (!is_writable($frFrames))) {
        $debugOut = 'frames dir not writable: ' . $frFrames;
        return null;
    }
    $ffBin = analize_find_ffmpeg();
    $ffExec = (strpos($ffBin, '/') === 0 || strpos($ffBin, '\\') !== false || preg_match('/^[a-z]:[\\\\\\/]/i', $ffBin)) ? escapeshellarg($ffBin) : escapeshellcmd($ffBin);
    $savedPath = getenv('PATH');
    $usePathBoost = ($ffBin === 'ffmpeg');
    if ($usePathBoost) {
        putenv('PATH=/usr/local/bin:/usr/bin:/bin:/sbin' . (($savedPath !== false && $savedPath !== '') ? ':' . $savedPath : ''));
    }
    $vp = escapeshellarg($videoPath);
    $bestLog = '';
    $patterns = [];
    foreach ([0.0, 0.25, 1.5, 3.0] as $seek) {
        $pre = $seek > 0 ? (' -ss ' . (string)$seek) : '';
        $patterns[] = $pre . ' -i ' . $vp . ' -sn -dn -threads 1 -frames:v 1 ';
        $patterns[] = $pre . ' -i ' . $vp . ' -map 0:v:0 -sn -dn -threads 1 -frames:v 1 ';
    }
    $patterns[] = ' -i ' . $vp . ' -sn -dn -vf scale=iw:-2 -frames:v 1 ';
    $patterns[] = ' -probesize 50M -analyzeduration 100M -i ' . $vp . ' -sn -dn -frames:v 1 ';
    $out = $frFrames . DIRECTORY_SEPARATOR . 'sample_' . uniqid('', true) . '.png';
    $outArg = escapeshellarg($out);
    $lastErr = '';
    $rc = 1;
    $restorePath = function () use (&$usePathBoost, &$savedPath): void {
        if (!$usePathBoost) {
            return;
        }
        if ($savedPath !== false) {
            putenv('PATH=' . $savedPath);
        } else {
            putenv('PATH');
        }
    };
    foreach ($patterns as $mid) {
        if (is_file($out)) {
            @unlink($out);
        }
        $cmd = $ffExec . ' -y -nostdin -hide_banner -loglevel error' . $mid . $outArg . ' 2>&1';
        $lines = [];
        $rc = 1;
        exec($cmd, $lines, $rc);
        $lastErr = implode("\n", $lines);
        if ($bestLog === '' && $lastErr !== '') {
            $bestLog = $lastErr;
        }
        if ($rc === 0 && is_file($out) && (int)@filesize($out) > 32) {
            $restorePath();
            $debugOut = 'ok bin=' . $ffBin;
            return $out;
        }
    }
    $restorePath();
    if (is_file($out)) {
        @unlink($out);
    }
    $tail = $lastErr !== '' ? $lastErr : $bestLog;
    $tailCut = function_exists('mb_substr') ? mb_substr($tail, 0, 1800, 'UTF-8') : substr($tail, 0, 1800);
    $debugOut = 'ffmpeg bin=' . $ffBin . ' PATH_restored exec rc=' . (string)$rc . ' ffmpeg log: ' . $tailCut;
    return null;
}
function analize_spawn_worker(string $jobPath): bool {
    @file_put_contents((realpath($jobPath) ?: $jobPath) . DIRECTORY_SEPARATOR . 'worker.log', '[' . date('c') . "] video worker is not available\n", FILE_APPEND);
    return false;
}
function analize_chunk_session_paths(string $uploadId): array {
    $safe = preg_replace('/[^a-z0-9]/i', '', strtolower($uploadId));
    return [
        CHUNK_SESSIONS_DIR . DIRECTORY_SEPARATOR . 'video_' . $safe . '.bin',
        CHUNK_SESSIONS_DIR . DIRECTORY_SEPARATOR . 'video_' . $safe . '.json',
    ];
}
function analize_chunk_read_meta(string $metaPath): array {
    $raw = @file_get_contents($metaPath);
    $meta = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($meta) ? $meta : [];
}
function analize_chunk_write_meta(string $metaPath, array $meta): bool {
    return @file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}
function analize_chunk_promote_to_job(string $uploadId, string $targetPath): bool {
    [$dataPath, $metaPath] = analize_chunk_session_paths($uploadId);
    if (!is_file($dataPath)) {
        return false;
    }
    if (@rename($dataPath, $targetPath)) {
        @unlink($metaPath);
        return true;
    }
    if (@copy($dataPath, $targetPath)) {
        @unlink($dataPath);
        @unlink($metaPath);
        return true;
    }
    return false;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['action'] ?? '') === 'upload_chunk') {
    $uploadId = preg_replace('/[^a-z0-9]/i', '', (string)($_POST['upload_id'] ?? ''));
    $chunkIndex = (int)($_POST['chunk_index'] ?? -1);
    $chunkTotal = (int)($_POST['chunk_total'] ?? 0);
    $fileSize = (int)($_POST['file_size'] ?? 0);
    $fileName = (string)($_POST['file_name'] ?? '');
    $kind = (string)($_POST['file_kind'] ?? 'video');
    if ($kind !== 'video' || strlen($uploadId) < 12 || $chunkIndex < 0 || $chunkTotal <= 0 || !isset($_FILES['chunk'])) {
        echo json_encode(['ok' => false, 'error' => 'chunk_params']);
        exit;
    }
    if ((int)($_FILES['chunk']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'chunk_upload', 'file_err' => (int)($_FILES['chunk']['error'] ?? 0)]);
        exit;
    }
    [$dataPath, $metaPath] = analize_chunk_session_paths($uploadId);
    $meta = analize_chunk_read_meta($metaPath);
    $next = (int)($meta['next_index'] ?? 0);
    if ($chunkIndex === 0) {
        @unlink($dataPath);
        $meta = [];
        $next = 0;
    }
    if ($chunkIndex !== $next) {
        echo json_encode(['ok' => false, 'error' => 'chunk_order', 'next_index' => $next]);
        exit;
    }
    $tmp = (string)($_FILES['chunk']['tmp_name'] ?? '');
    $in = @fopen($tmp, 'rb');
    $out = @fopen($dataPath, 'ab');
    if (!$in || !$out) {
        if (is_resource($in)) fclose($in);
        if (is_resource($out)) fclose($out);
        echo json_encode(['ok' => false, 'error' => 'chunk_io']);
        exit;
    }
    stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);
    $received = (int)@filesize($dataPath);
    $meta = [
        'next_index' => $chunkIndex + 1,
        'chunk_total' => $chunkTotal,
        'file_size' => $fileSize > 0 ? $fileSize : $received,
        'file_name' => $fileName,
        'updated_at' => time(),
    ];
    if (!analize_chunk_write_meta($metaPath, $meta)) {
        echo json_encode(['ok' => false, 'error' => 'chunk_meta']);
        exit;
    }
    $done = ($chunkIndex + 1) >= $chunkTotal;
    echo json_encode(['ok' => true, 'done' => $done, 'upload_id' => $uploadId, 'received' => $received]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'job_status' && isset($_GET['job'])) {
    header('Content-Type: application/json; charset=utf-8');
    $jid = preg_replace('/[^a-f0-9]/i', '', (string)$_GET['job']);
    if (strlen($jid) < 8) {
        echo json_encode(['ok' => false, 'error' => 'invalid job']);
        exit;
    }
    $fsStatePath = JOBS_DIR . $jid . DIRECTORY_SEPARATOR . 'state.json';
    $fromFs = function () use ($fsStatePath, $jid) {
        if (!is_file($fsStatePath) || !is_readable($fsStatePath)) {
            return null;
        }
        $st = json_decode((string)file_get_contents($fsStatePath), true);
        if (!is_array($st)) {
            return null;
        }
        if ((string)($st['job_id'] ?? $jid) !== $jid) {
            return null;
        }
        $mt = @filemtime($fsStatePath) ?: 0;
        if (($st['status'] ?? '') === 'running' && $mt > 0 && (time() - $mt) > VIDEO_JOB_RUNNING_STALE_SEC) {
            $st = array_merge($st, ['status' => 'error', 'message' => 'stale_job']);
        }
        return $st;
    };
    $vdb = video_job_db_connect(USER_DIR);
    if ($vdb) {
        $vj = video_job_load($vdb);
        if (($vj['job_id'] ?? '') === $jid) {
            $st = $vj['state'];
            $mt = (int)($vj['updated_at'] ?? 0);
            if (($st['status'] ?? '') === 'running' && $mt > 0 && (time() - $mt) > VIDEO_JOB_RUNNING_STALE_SEC) {
                $st = array_merge($st, ['status' => 'error', 'message' => 'stale_job']);
            }
            $st = steps_notify_video_job_once($vdb, $jid, $st);
            echo json_encode(['ok' => true, 'state' => $st], JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0));
            exit;
        }
        $fsSt = $fromFs();
        if ($fsSt !== null) {
            $fsSt = steps_notify_video_job_once(null, $jid, $fsSt, $fsStatePath);
            echo json_encode(['ok' => true, 'state' => $fsSt], JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0));
            exit;
        }
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }
    $fsStOnly = $fromFs();
    if ($fsStOnly !== null) {
        $fsStOnly = steps_notify_video_job_once(null, $jid, $fsStOnly, $fsStatePath);
        echo json_encode(['ok' => true, 'state' => $fsStOnly], JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0));
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'not found']);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'active_job' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $best = null;
    $vdb = video_job_db_connect(USER_DIR);
    if ($vdb) {
        $vj = video_job_load($vdb);
        $st = $vj['state'];
        $m = (int)($vj['updated_at'] ?? 0);
        if (is_array($st) && ($st['status'] ?? '') === 'running' && $m > 0 && (time() - $m) <= VIDEO_JOB_RUNNING_STALE_SEC && strlen((string)($vj['job_id'] ?? '')) >= 8) {
            $best = ['jobId' => $vj['job_id'], 'state' => $st];
        }
    }
    if (!$best && is_dir(JOBS_DIR)) {
        $bestM = 0;
        foreach (glob(JOBS_DIR . '*/state.json', GLOB_NOSORT) ?: [] as $sf) {
            $st = @json_decode((string)@file_get_contents($sf), true);
            if (!is_array($st) || ($st['status'] ?? '') !== 'running') {
                continue;
            }
            $m = @filemtime($sf) ?: 0;
            if ($m <= 0 || (time() - $m) > VIDEO_JOB_RUNNING_STALE_SEC) {
                continue;
            }
            if ($m > $bestM) {
                $bestM = $m;
                $id = basename(dirname($sf));
                $best = ['jobId' => $id, 'state' => $st];
            }
        }
    }
    echo json_encode(['ok' => true, 'active' => $best]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'stop_job') {
    header('Content-Type: application/json; charset=utf-8');
    $jid = preg_replace('/[^a-f0-9]/i', '', (string)($_POST['job'] ?? ''));
    if (strlen($jid) < 8) {
        echo json_encode(['ok' => false, 'error' => 'invalid job']);
        exit;
    }
    $jobPath = JOBS_DIR . $jid;
    if (is_dir($jobPath)) {
        @file_put_contents($jobPath . DIRECTORY_SEPARATOR . 'cancel.flag', '1');
    }
    $vdb = video_job_db_connect(USER_DIR);
    if ($vdb) {
        $st = ['status' => 'cancelled', 'progress' => 0.0, 'message' => 'cancelled', 'processed' => 0, 'total' => 0, 'results' => null, 'job_id' => $jid];
        try {
            video_job_save($vdb, $jid, $st);
        } catch (Exception $e) {
        }
        try {
            video_job_reset($vdb);
        } catch (Exception $e) {
        }
    }
    $pf = USER_DIR . DIRECTORY_SEPARATOR . 'pending_video_confirm.json';
    if (is_file($pf)) {
        @unlink($pf);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'extract_frame' && (isset($_FILES['video']) || isset($_POST['video_token']))) {
    $extForTemp = '.mp4';
    if (isset($_POST['video_token'])) {
        $t0 = preg_replace('/[^a-z0-9]/i', '', (string)$_POST['video_token']);
        if (strlen($t0) >= 12) {
            [, $metaPath0] = analize_chunk_session_paths($t0);
            $m0 = analize_chunk_read_meta($metaPath0);
            $bn0 = (string)($m0['file_name'] ?? '');
            $e0 = pathinfo($bn0, PATHINFO_EXTENSION);
            if ($e0 !== '') {
                $extForTemp = '.' . preg_replace('/[^a-z0-9]/i', '', $e0);
                if (strlen($extForTemp) < 4) {
                    $extForTemp = '.mp4';
                }
            }
        }
    }
    $videoFile = UPLOAD_DIR . 'temp_' . uniqid('', true) . $extForTemp;
    $hasVideo = false;
    if (isset($_POST['video_token'])) {
        $token = preg_replace('/[^a-z0-9]/i', '', (string)$_POST['video_token']);
        if (strlen($token) >= 12) {
            [$dataPath] = analize_chunk_session_paths($token);
            $hasVideo = is_file($dataPath) && @copy($dataPath, $videoFile);
        }
    }
    if (!$hasVideo && isset($_FILES['video']) && (int)($_FILES['video']['error'] ?? 1) === UPLOAD_ERR_OK) {
        $hasVideo = move_uploaded_file((string)($_FILES['video']['tmp_name'] ?? ''), $videoFile);
    }
    if (!$hasVideo) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'video_source'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ffDbg = '';
    $sampleFrame = analize_extract_sample_frame($videoFile, $ffDbg);
    if ($sampleFrame) {
        header('Content-Type: image/png');
        readfile($sampleFrame);
        @unlink($sampleFrame);
        @unlink($videoFile);
        exit;
    }
    @unlink($videoFile);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'frame_extract', 'detail' => $ffDbg, 'ffmpeg' => analize_find_ffmpeg()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_year'], $_POST['action']) && $_POST['action'] === 'process_video' && (isset($_FILES['video']) || isset($_POST['video_token']))) {
    @ini_set('max_execution_time', 120);
    $allowedTess = ['eng', 'rus', 'ukr', 'bel'];
    $selectedOcrLang = 'eng';
    if (isset($_POST['ocr_lang']) && in_array($_POST['ocr_lang'], $allowedTess, true)) {
        $selectedOcrLang = (string)$_POST['ocr_lang'];
    } elseif (isset($_POST['language'])) {
        $p = (string)$_POST['language'];
        if (in_array($p, $allowedTess, true)) {
            $selectedOcrLang = $p;
        } elseif (in_array($p, ['en', 'ru', 'uk', 'be'], true)) {
            $selectedOcrLang = $tesseractLangMap[$p] ?? 'eng';
            $lang = $p;
        }
    }
    $year = ($_POST['current_year'] === '-' || $_POST['current_year'] === '') ? (int)date('Y') : (int)$_POST['current_year'];
    if ($year < 2000 || $year > 2100) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'year']);
        exit;
    }
    $vdb = video_job_db_connect(USER_DIR);
    if ($vdb) {
        $vjPrev = video_job_load($vdb);
        $prevSt = $vjPrev['state'] ?? [];
        if (($prevSt['status'] ?? '') === 'running') {
            $mtPrev = (int)($vjPrev['updated_at'] ?? 0);
            if ($mtPrev > 0 && (time() - $mtPrev) <= VIDEO_JOB_RUNNING_STALE_SEC) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'busy']);
                exit;
            }
        }
    }
    video_jobs_disk_cleanup(USER_DIR);
    try {
        $jobId = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $jobId = bin2hex(substr(hash('sha256', uniqid((string)mt_rand(), true), true), 0, 8));
    }
    $jobPath = JOBS_DIR . $jobId;
    if (!@mkdir($jobPath, 0777, true) && !is_dir($jobPath)) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'mkdir']);
        exit;
    }
    $token = preg_replace('/[^a-z0-9]/i', '', (string)($_POST['video_token'] ?? ''));
    $videoName = isset($_FILES['video']) ? (string)($_FILES['video']['name'] ?? '') : '';
    if ($videoName === '' && $token !== '') {
        [, $metaPath] = analize_chunk_session_paths($token);
        $meta = analize_chunk_read_meta($metaPath);
        $videoName = (string)($meta['file_name'] ?? '');
    }
    $ext = pathinfo($videoName, PATHINFO_EXTENSION);
    $ext = $ext ? ('.' . preg_replace('/[^a-z0-9]/i', '', $ext)) : '.mp4';
    $inFile = $jobPath . '/input' . $ext;
    $moved = false;
    if ($token !== '') {
        $moved = analize_chunk_promote_to_job($token, $inFile);
    } elseif (isset($_FILES['video'])) {
        $moved = move_uploaded_file($_FILES['video']['tmp_name'], $inFile);
    }
    if (!$moved) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'move']);
        exit;
    }
    $crop = null;
    if (isset($_POST['crop_x'], $_POST['crop_y'], $_POST['crop_width'], $_POST['crop_height']) && (int)$_POST['crop_width'] > 0 && (int)$_POST['crop_height'] > 0) {
        $crop = [
            'x' => (int)$_POST['crop_x'],
            'y' => (int)$_POST['crop_y'],
            'width' => (int)$_POST['crop_width'],
            'height' => (int)$_POST['crop_height'],
        ];
    }
    $ff = analize_find_ffmpeg();
    $te = analize_find_tesseract();
    $td = analize_find_tessdata();
    $email = get_current_user_email() ?: ('folder_' . basename(USER_DIR));
    $cfg = [
        'ocr_lang' => $selectedOcrLang,
        'current_year' => $year,
        'crop' => $crop,
        'fps' => 4.0,
        'fps_refine' => 5.0,
        'ffmpeg' => $ff,
        'tesseract_exe' => $te,
        'tessdata_dir' => $td,
        'user_results_dir' => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, realpath(RESULTS_DIR) ?: RESULTS_DIR),
        'user_email' => $email,
    ];
    file_put_contents($jobPath . '/job_config.json', json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $state0 = [
        'status' => 'running',
        'progress' => 0.0,
        'message' => 'preparing',
        'job_id' => $jobId,
    ];
    if ($vdb) {
        try {
            video_job_save($vdb, $jobId, $state0);
        } catch (Exception $e) {
            @file_put_contents($jobPath . '/state.json', json_encode($state0));
        }
    } else {
        @file_put_contents($jobPath . '/state.json', json_encode($state0));
    }
    if (!analize_spawn_worker($jobPath)) {
        $state0['status'] = 'error';
        $state0['message'] = 'worker not available';
        if ($vdb) {
            try {
                video_job_save($vdb, $jobId, $state0);
            } catch (Exception $e) {
                @file_put_contents($jobPath . '/state.json', json_encode($state0));
            }
        } else {
            @file_put_contents($jobPath . '/state.json', json_encode($state0));
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'worker', 'job_id' => $jobId]);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'job_id' => $jobId]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_edited') {
    $response = ['status' => 'error', 'message' => $trans['save_error']];
    if (isset($_POST['edited_dates']) && isset($_POST['edited_steps']) && is_array($_POST['edited_dates']) && is_array($_POST['edited_steps']) && isset($_POST['output_file'])) {
        $filePath = trim((string)$_POST['output_file']);
        $rd = realpath(RESULTS_DIR);
        $rfn = is_file($filePath) ? realpath($filePath) : false;
        if ($rd && $rfn && is_writable($rfn) && strncmp($rfn, $rd, strlen($rd)) === 0) {
            $filePath = $rfn;
            $content = '';
            $dates = $_POST['edited_dates'];
            $steps = $_POST['edited_steps'];
            $maxCount = max(count($dates), count($steps));
            for ($i = 0; $i < $maxCount; $i++) {
                $date = isset($dates[$i]) ? trim($dates[$i]) : '';
                $step = isset($steps[$i]) ? preg_replace('/\D+/', '', trim($steps[$i])) : '';
                $content .= "$date,$step\n";
            }
            if (file_put_contents($filePath, $content) !== false) {
                $response = ['status' => 'success', 'message' => $trans['save_success']];
            } else {
                error_log("Не удалось записать в файл: $filePath");
            }
        } else {
            error_log("Файл не найден или не доступен для записи: $filePath");
        }
    } else {
        error_log("Отсутствуют данные POST: " . print_r($_POST, true));
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    $response = ['status' => 'error', 'message' => $trans['save_error']];
    if (isset($_POST['filename'])) {
        $filePath = RESULTS_DIR . basename((string)$_POST['filename']);
        $rd = realpath(RESULTS_DIR);
        $rfn = is_file($filePath) ? realpath($filePath) : false;
        if ($rd && $rfn && strncmp($rfn, $rd, strlen($rd)) === 0 && unlink($rfn)) {
            $response = ['status' => 'success', 'message' => $trans['save_success']];
        } else {
            error_log("Не удалось удалить файл: $filePath");
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

function cleanupOldFiles($dir, $hours = 1) {
    if (!is_dir($dir)) return;
    
    $files = glob($dir . '*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > ($hours * 3600)) {
            unlink($file);
        }
    }
}

cleanupOldFiles(UPLOAD_DIR, 1);
cleanupOldFiles(FRAMES_DIR, 1);
cleanupOldFiles(RESULTS_DIR, 24);
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $trans['title'] ?></title>
    <style>
        :root {
            --button-color: <?= $color ?>;
            --border-color: <?= $color ?>;
            --background-color: <?= $theme === 'dark' ? '#1a1a1a' : '#f5f5f5' ?>;
            --text-color: <?= $theme === 'dark' ? '#ffffff' : '#000000' ?>;
            --container-background: <?= $theme === 'dark' ? '#2c2c2c' : '#ffffff' ?>;
            --scrollbar-track: <?= $theme === 'dark' ? '#333333' : '#f1f1f1' ?>;
            --scrollbar-thumb: <?= $theme === 'dark' ? '#555555' : '#888888' ?>;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: var(--background-color);
            color: var(--text-color);
            transition: all 0.3s;
            transform: scale(0.95);
            transform-origin: center;
        }
        /* Dark theme scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--scrollbar-track);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: <?= $theme === 'dark' ? '#777777' : '#666666' ?>;
        }
        /* Firefox scrollbar styling */
        * {
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
        }
        #app {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 1000px;
            width: 100%;
            background: var(--container-background);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 2px solid var(--border-color);
            overflow: auto;
        }
        .upload-form {
            margin-bottom: 30px;
            padding: 30px;
            border: 3px dashed var(--border-color);
            border-radius: 10px;
            text-align: center;
            background: <?= $theme === 'dark' ? '#333333' : '#fafafa' ?>;
        }
        .upload-form:hover {
            border-color: <?= $theme === 'dark' ? '#4CAF50' : '#4CAF50' ?>;
            background: <?= $theme === 'dark' ? '#2f2f2f' : '#f0fff0' ?>;
        }
        .results { margin-top: 30px; }
        .download-btn, .manage-btn { 
            display: inline-block; 
            padding: 12px 24px; 
            background: var(--button-color);
            color: <?= $theme === 'dark' ? '#ffffff' : '#ffffff' ?>;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 15px;
            font-weight: bold;
            transition: background 0.3s;
            cursor: pointer;
        }
        .download-btn:hover, .manage-btn:hover { 
            background: <?= $theme === 'dark' ? 'darken(var(--button-color), 10%)' : 'darken(var(--button-color), 10%)' ?>; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border: 1px solid var(--border-color); 
        }
        th { 
            background: <?= $theme === 'dark' ? '#444444' : '#f8f9fa' ?>; 
            font-weight: bold; 
        }
        tr:nth-child(even) { 
            background: <?= $theme === 'dark' ? '#3a3a3a' : '#f8f9fa' ?>; 
        }
        select, input[type="file"], input[type="number"], input[type="text"] { 
            padding: 10px; 
            border: 1px solid var(--border-color); 
            border-radius: 5px; 
            width: 80%; 
            max-width: 400px; 
            margin: 10px 0;
            background: <?= $theme === 'dark' ? '#2c2c2c' : '#ffffff' ?>;
            color: var(--text-color);
        }
        button { 
            padding: 12px 24px; 
            background: var(--button-color);
            color: <?= $theme === 'dark' ? '#ffffff' : '#ffffff' ?>;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 15px;
        }
        button:hover { 
            background: <?= $theme === 'dark' ? 'darken(var(--button-color), 10%)' : 'darken(var(--button-color), 10%)' ?>; 
        }
        .spinner { 
            display: none; 
            width: 40px; 
            height: 40px; 
            border: 4px solid <?= $theme === 'dark' ? '#555555' : '#f3f3f3' ?>;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 1000;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        .modal-content { 
            background: <?= $theme === 'dark' ? '#2c2c2c' : '#ffffff' ?>;
            padding: 20px;
            border-radius: 10px;
            max-width: min(90vw, 700px);
            max-height: 85vh;
            width: 100%;
            min-width: 0;
            overflow: hidden;
            overflow-x: hidden;
            text-align: center;
            border: 2px solid var(--border-color);
            box-sizing: border-box;
        }
        .modal-table-wrap, #fileViewModal .modal-content > pre { max-height: 50vh; overflow-x: hidden; overflow-y: auto; margin: 10px 0; box-sizing: border-box; width: 100%; min-width: 0; }
        #fileViewModal .modal-content > pre { text-align: left; }
        .crop-btn { 
            margin: 10px; 
            background: #28a745; 
        }
        .crop-btn:hover { 
            background: #218838; 
        }
        .error { 
            color: #ff4444; 
            margin: 10px 0; 
        }
        .success { 
            color: #28a745; 
            margin: 10px 0; 
        }
        .action-btn { 
            padding: 8px 16px; 
            margin: 0 5px; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        .view-btn { 
            background: #28a745; 
            color: white; 
            border: none; 
        }
        .delete-btn { 
            background: #dc3545; 
            color: white; 
            border: none; 
        }
        .view-btn:hover { 
            background: #218838; 
        }
        .delete-btn:hover { 
            background: #c82333; 
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="container">
            <h1><?= $trans['title'] ?></h1>
            <p><?= $trans['select_language'] ?><br><?= $trans['select_video'] ?> <br> <?= $trans['enter_year'] ?></p>
            
            <div class="upload-form">
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <select name="language" id="languageSelect" onchange="this.form.submit()">
                        <option value="eng" <?= $tessSelForForms === 'eng' ? 'selected' : '' ?>>English</option>
                        <option value="rus" <?= $tessSelForForms === 'rus' ? 'selected' : '' ?>>Русский</option>
                        <option value="ukr" <?= $tessSelForForms === 'ukr' ? 'selected' : '' ?>>Українська</option>
                        <option value="bel" <?= $tessSelForForms === 'bel' ? 'selected' : '' ?>>Беларуская</option>
                    </select>
                    <br><br>
                    <input type="file" name="video" accept="video/*" required id="videoInput" title="<?= $trans['select_video'] ?>">
                    <input type="text" name="current_year" placeholder="2024 or -" title="<?= $trans['enter_year'] ?>">
                    <br><br>
                    <button type="submit" id="submitBtn" title="<?= $trans['start_analysis'] ?>"><?= $trans['start_analysis'] ?></button>
                    <div class="spinner" id="spinner"></div>
                </form>
            </div>

            <!-- Moved Manage Results button here to make it always visible -->
            <button class="manage-btn" id="manageResultsBtn" title="<?= $trans['manage_results'] ?>"><?= $trans['manage_results'] ?></button>

            <div class="modal" id="resultsModal">
                <div class="modal-content">
                    <h3><?= $trans['results_table'] ?></h3>
                    <div class="modal-table-wrap">
                    <table id="resultsTable">
                        <tr>
                            <th><?= $trans['file_name'] ?></th>
                            <th><?= $trans['file_date'] ?></th>
                            <th><?= $trans['actions'] ?></th>
                        </tr>
                        <?php
                        $resultFiles = glob(RESULTS_DIR . 'steps_data_*.txt');
                        foreach ($resultFiles as $file) {
                            $filename = basename($file);
                            $fileDate = date('Y-m-d H:i:s', filemtime($file));
                            echo "<tr>
                                    <td>$filename</td>
                                    <td>$fileDate</td>
                                    <td>
                                        <button class='action-btn view-btn' onclick='viewFile(\"$filename\")'>{$trans['view']}</button>
                                        <button class='action-btn delete-btn' onclick='deleteFile(\"$filename\")'>{$trans['delete']}</button>
                                    </td>
                                </tr>";
                        }
                        ?>
                    </table>
                    </div>
                    <button class="crop-btn" id="closeResultsModal" title="<?= $trans['close'] ?>"><?= $trans['close'] ?></button>
                </div>
            </div>

            <div class="modal" id="fileViewModal">
                <div class="modal-content">
                    <h3><?= $trans['results_table'] ?></h3>
                    <pre id="fileContent"></pre>
                    <button class="crop-btn" id="closeFileViewModal" title="<?= $trans['close'] ?>"><?= $trans['close'] ?></button>
                </div>
            </div>

            <div id="jobPanel" class="results" style="display:none; margin-top: 16px;">
                <h3 id="jobTitle"><?= htmlspecialchars($trans['progress_label'] ?? 'Progress') ?></h3>
                <p id="jobMsg" style="margin:4px 0;">—</p>
                <div style="background: <?= $theme === 'dark' ? '#444' : '#e0e0e0' ?>; border-radius: 6px; height: 24px; overflow: hidden; margin: 8px 0; border:1px solid var(--border-color);">
                    <div id="jobBar" style="height: 100%; width: 0%; background: var(--button-color); transition: width 0.2s;"></div>
                </div>
                <p id="jobMeta" style="font-size: 14px; margin: 0; opacity:0.9;"></p>
            </div>
            <div id="resultsArea" class="results" style="display:none; margin-top: 16px;">
                <h3 id="resTitle"><?= $trans['results'] ?></h3>
                <form method="post" id="editForm">
                    <input type="hidden" name="output_file" id="outputFileInp" value="">
                    <div class="modal-table-wrap">
                    <table id="resultsDataTable">
                        <tr>
                            <th><?= $trans['date'] ?></th>
                            <th><?= $trans['steps'] ?></th>
                            <th><?= $trans['frame'] ?></th>
                        </tr>
                    </table>
                    </div>
                    <input type="hidden" name="action" value="save_edited">
                    <button type="submit" id="saveBtn" title="<?= $trans['save_edited'] ?>"><?= $trans['save_edited'] ?></button>
                </form>
                <a href="#" class="download-btn" id="downloadLink" style="display:none" download title="<?= $trans['download'] ?>"><?= $trans['download'] ?></a>
                <p id="saveMessage" class="success" style="display: none;"></p>
            </div>
            <p id="noDataP" class="error" style="display:none;"><?= $trans['no_data'] ?></p>
            <p id="iframeDoneHint" class="success" style="display:none;margin-top:10px;"><?= htmlspecialchars($trans['iframe_result_hint'] ?? '', ENT_QUOTES) ?></p>
        </div>
    </div>

    <script>
        const DL_BASE = '../users/<?= htmlspecialchars($safeName, ENT_QUOTES) ?>/results/';
        function formatStepsDisp(n) { var x = parseInt(n, 10); if (isNaN(x) || x < 0) return ''; return String(x).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
        function parseStepsRaw(s) { return String(s).replace(/\s/g, ''); }
        function escAttr(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;'); }
        const isIframe = window.self !== window.top;
        if (isIframe) {
            const style = document.createElement('style');
            style.textContent = `
                body { margin: 0; padding: 0; }
                #app { min-height: 0; }
                .container { margin: 0; padding: 10px; border-radius: 0; box-shadow: none; width: 100%; height: 700px; position: absolute; top: 0; left: 0; }
                .modal {
                    position: absolute !important;
                    top: 20px !important;
                    left: 0 !important;
                    width: 100% !important;
                    height: 450px !important;
                    z-index: 1000;
                    overflow: hidden;
                    overflow-x: hidden;
                }
                .modal-content {
                    max-width: 100% !important;
                    max-height: 100% !important;
                    margin: 0;
                    border-radius: 0;
                    padding: 10px;
                    overflow: hidden;
                    overflow-x: hidden;
                }
                .modal-table-wrap { max-height: 45vh; }
            `;
            document.head.appendChild(style);
        }

        const uploadForm = document.getElementById('uploadForm');
        const videoInput = document.getElementById('videoInput');
        const submitBtn = document.getElementById('submitBtn');
        const spinner = document.getElementById('spinner');
        const editForm = document.getElementById('editForm');
        const saveBtn = document.getElementById('saveBtn');
        const saveMessage = document.getElementById('saveMessage');
        const manageResultsBtn = document.getElementById('manageResultsBtn');
        const resultsModal = document.getElementById('resultsModal');
        const closeResultsModal = document.getElementById('closeResultsModal');
        const fileViewModal = document.getElementById('fileViewModal');
        const closeFileViewModal = document.getElementById('closeFileViewModal');

        let pollT = null;
        let lastJobId = null;
        function qs() { const p = new URLSearchParams(window.location.search); return p; }
        function uAct(action, extra) { const p = qs(); p.set('action', action); if (extra) for (const k in extra) p.set(k, extra[k]); return '?' + p.toString(); }
        function showJob(on) { document.getElementById('jobPanel').style.display = on ? 'block' : 'none'; }
        function setBar(pct) { const b = document.getElementById('jobBar'); b.style.width = Math.min(100, Math.max(0, pct)) + '%'; }
        function showResults(st) {
            const host = document.getElementById('resultsArea');
            const tb = document.getElementById('resultsDataTable');
            while (tb.rows.length > 1) tb.deleteRow(1);
            const rows = st.results && st.results.length ? st.results : [];
            if (rows.length === 0) { host.style.display = 'none'; document.getElementById('noDataP').style.display = 'block'; return; }
            document.getElementById('noDataP').style.display = 'none';
            host.style.display = 'block';
            document.getElementById('outputFileInp').value = st.output_file || '';
            rows.forEach(function(r) {
                const fd = (r.dmy && r.dmy.length) ? r.dmy : (r.date || '');
                const tr = tb.insertRow();
                tr.innerHTML = '<td><input type="text" name="edited_dates[]" required value="' + escAttr(fd) + '"></td><td><input type="text" class="edit-steps-inp" name="edited_steps[]" required value="' + escAttr(formatStepsDisp(r.steps)) + '"></td><td>' + (r.frame || '') + '</td>';
            });
            const dl = document.getElementById('downloadLink');
            if (st.output_basename) { dl.href = DL_BASE + st.output_basename; dl.style.display = 'inline-block'; } else { dl.style.display = 'none'; }
        }
        function onState(st) {
            if (!st) return;
            document.getElementById('jobMsg').textContent = st.message || st.status;
            setBar(st.progress != null ? st.progress : 0);
            let m = document.getElementById('jobMeta');
            m.textContent = (st.processed != null && st.total) ? (st.processed + '/' + st.total) : '';
            if (st.eta_sec) m.textContent += (m.textContent ? ' · ' : '') + '<?= htmlspecialchars($trans['eta'] ?? 'ETA', ENT_QUOTES) ?> ' + st.eta_sec + 's';
            if (st.status === 'done') {
                showJob(false);
                if (pollT) { clearInterval(pollT); pollT = null; }
                if (window.self !== window.top) {
                    try {
                        window.parent.postMessage({ type: 'stepsVideoAnalysisDone', payload: { jobId: lastJobId, outputBasename: st.output_basename, output_file: st.output_file, results: Array.isArray(st.results) ? st.results : [], ocr_preview: Array.isArray(st.ocr_preview) ? st.ocr_preview : [] } }, '*');
                    } catch (e) {}
                    document.getElementById('resultsArea').style.display = 'none';
                    document.getElementById('noDataP').style.display = 'none';
                    const h = document.getElementById('iframeDoneHint');
                    if (h) { h.style.display = 'block'; }
                } else {
                    showResults(st);
                }
            }
            if (st.status === 'error') { showJob(true); if (pollT) { clearInterval(pollT); pollT = null; } alert('<?= htmlspecialchars($trans['job_err'] ?? 'Error', ENT_QUOTES) ?>: ' + (st.message || '')); }
        }
        function startPoll(jid) {
            lastJobId = jid;
            if (pollT) clearInterval(pollT);
            const hi = document.getElementById('iframeDoneHint');
            if (hi) hi.style.display = 'none';
            showJob(true);
            document.getElementById('resultsArea').style.display = 'none';
            function one() { fetch(uAct('job_status', { job: jid })).then(function(r) { return r.json(); }).then(function(d) { if (d.ok) onState(d.state); }).catch(function() {}); }
            one();
            pollT = setInterval(one, 1200);
        }
        uploadForm.addEventListener('submit', function(e) {
            const yearInput = document.querySelector('input[name="current_year"]');
            if (yearInput.value !== '-' && (yearInput.value < 2000 || yearInput.value > 2100 || isNaN(yearInput.value))) {
                e.preventDefault();
                alert('<?= $trans['year_error'] ?>');
                return;
            }
            if (!videoInput.files.length) {
                e.preventDefault();
                alert('<?= $trans['video_error'] ?>');
                return;
            }
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.textContent = '<?= $trans['processing'] ?>';
            spinner.style.display = 'block';
            const fd = new FormData(uploadForm);
            fd.set('action', 'process_video');
            fetch(document.location, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    spinner.style.display = 'none';
                    submitBtn.disabled = false;
                    submitBtn.textContent = '<?= $trans['start_analysis'] ?>';
                    if (!d.ok) { alert(d.error || 'fail'); return; }
                    startPoll(d.job_id);
                })
                .catch(function() { spinner.style.display = 'none'; submitBtn.disabled = false; submitBtn.textContent = '<?= $trans['start_analysis'] ?>'; alert('network'); });
        });
        fetch(uAct('active_job'), { method: 'GET' })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.ok && d.active && d.active.state && d.active.state.status === 'running') { startPoll(d.active.jobId); } });

        editForm.addEventListener('focusin', function(e) { if (e.target.classList && e.target.classList.contains('edit-steps-inp')) e.target.value = parseStepsRaw(e.target.value); });
        editForm.addEventListener('focusout', function(e) { if (e.target.classList && e.target.classList.contains('edit-steps-inp')) { var t = e.target.value.replace(/\D/g, ''); e.target.value = t ? formatStepsDisp(t) : ''; } });
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            editForm.querySelectorAll('.edit-steps-inp').forEach(function(inp) { inp.value = parseStepsRaw(inp.value); });
            const formData = new FormData(this);
            editForm.querySelectorAll('.edit-steps-inp').forEach(function(inp) { inp.value = inp.value ? formatStepsDisp(inp.value) : ''; });
            saveBtn.disabled = true;
            saveBtn.textContent = '<?= $trans['processing'] ?>';

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                saveBtn.disabled = false;
                saveBtn.textContent = '<?= $trans['save_edited'] ?>';
                saveMessage.textContent = data.message;
                saveMessage.style.display = data.status === 'success' ? 'block' : 'none';
                if (data.status === 'error') {
                    alert(data.message);
                }
                setTimeout(() => {
                    saveMessage.style.display = 'none';
                }, 3000);
            })
            .catch(error => {
                saveBtn.disabled = false;
                saveBtn.textContent = '<?= $trans['save_edited'] ?>';
                alert('<?= $trans['save_error'] ?>');
            });
        });

        manageResultsBtn.addEventListener('click', function() {
            resultsModal.style.display = 'flex';
        });

        closeResultsModal.addEventListener('click', function() {
            resultsModal.style.display = 'none';
        });

        closeFileViewModal.addEventListener('click', function() {
            fileViewModal.style.display = 'none';
        });

        function viewFile(filename) {
            fetch(DL_BASE + filename, {
                method: 'GET'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Файл не найден');
                }
                return response.text();
            })
            .then(data => {
                document.getElementById('fileContent').textContent = data;
                fileViewModal.style.display = 'flex';
            })
            .catch(error => {
                alert('Ошибка загрузки содержимого файла: ' + error.message);
            });
        }

        function deleteFile(filename) {
            if (confirm('Вы уверены, что хотите удалить ' + filename + '?')) {
                const formData = new FormData();
                formData.append('action', 'delete_file');
                formData.append('filename', filename);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    alert('Ошибка удаления файла');
                });
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (resultsModal.style.display === 'flex') {
                    resultsModal.style.display = 'none';
                }
                if (fileViewModal.style.display === 'flex') {
                    fileViewModal.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>
