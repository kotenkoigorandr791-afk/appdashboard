<?php
declare(strict_types=1);
function steps_import_log(string $level, string $message, string $sourceFile = '', ?int $sourceLine = null): void {
    static $booted = false;
    if (!$booted) {
        $__d = __DIR__;
        for ($__n = 0; $__n < 16; $__n++) {
            if (is_file($__d . '/smart_log_boot.php')) {
                require_once $__d . '/smart_log_boot.php';
                break;
            }
            $__p = dirname($__d);
            if ($__p === $__d) break;
            $__d = $__p;
        }
        $booted = true;
    }
    if (function_exists('smart_log_write')) {
        smart_log_write('steps', $level, $message, $sourceFile, $sourceLine);
        return;
    }
    error_log('[steps] ' . $message);
}
function steps_worker_cfg_email(array $cfg): string {
    $email = trim((string)($cfg['user_email'] ?? ''));
    if ($email !== '') return $email;
    $sqlitePath = trim((string)($cfg['user_sqlite_path'] ?? ''));
    if ($sqlitePath !== '') {
        $folder = basename(dirname(str_replace('\\', '/', $sqlitePath)));
        if ($folder !== '' && $folder !== 'users') return 'folder_' . $folder;
    }
    return '';
}
function steps_worker_should_sync_file_state(int $fileTime, int $updatedAt, string $pgStatus, string $fileStatus, array $activeStatuses): bool {
    if ($fileTime >= $updatedAt) return true;
    if (!in_array($pgStatus, $activeStatuses, true)) return false;
    if ($fileStatus === '' || $fileStatus === $pgStatus) return false;
    return in_array($fileStatus, ['awaiting_confirm', 'awaiting_conflict', 'done', 'error', 'cancelled'], true);
}
function steps_worker_connect_db(array $cfg): ?AppDatabase {
    $email = steps_worker_cfg_email($cfg);
    if ($email === '') {
        steps_import_log('error', 'steps worker: missing user_email in job config', __FILE__, __LINE__);
        return null;
    }
    try {
        require_once __DIR__ . '/../../../lib/app_db.php';
        require_once __DIR__ . '/../core/store.php';
        require_once __DIR__ . '/../video_job_sqlite.php';
        require_once __DIR__ . '/google_fit_takeout/fit_takeout_state.php';
        require_once __DIR__ . '/zeep_life/zeep_state.php';
        $db = app_main_db();
        steps_user($email);
        steps_store_ensure($db);
        return $db;
    } catch (Throwable $e) {
        steps_import_log('error', 'steps worker db connect failed email=' . $email . ' err=' . $e->getMessage(), __FILE__, __LINE__);
        return null;
    }
}
function steps_worker_save_job_state(array $cfg, string $table, string $jobId, array $state): void {
    $db = steps_worker_connect_db($cfg);
    if (!$db) return;
    if ($table === 'zeep_job_state') {
        zeep_job_save($db, $jobId, $state);
        return;
    }
    if ($table === 'fit_takeout_job_state') {
        fit_takeout_save($db, $jobId, $state);
    }
}
function steps_worker_json_read(string $path): array {
    $raw = @file_get_contents($path);
    $obj = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($obj) ? $obj : [];
}
function steps_worker_cancelled(string $jobDir): bool {
    return is_file($jobDir . DIRECTORY_SEPARATOR . 'cancel.flag');
}
function steps_worker_delete_tree(string $dir): void {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if (!is_array($items)) {
        @rmdir($dir);
        return;
    }
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $it;
        if (is_dir($p) && !is_link($p)) steps_worker_delete_tree($p);
        else @unlink($p);
    }
    @rmdir($dir);
}
function steps_worker_write_state(string $jobDir, array $cfg, string $jobId, string $table, array $state): void {
    if (!isset($state['job_id']) || (string)$state['job_id'] === '') $state['job_id'] = $jobId;
    $payload = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($jobDir . DIRECTORY_SEPARATOR . 'state.json', $payload);
    for ($attempt = 0; $attempt < 30; $attempt++) {
        try {
            steps_worker_save_job_state($cfg, $table, $jobId, $state);
            return;
        } catch (Throwable $e) {
            usleep(50000 * min($attempt + 1, 10));
        }
    }
    steps_import_log('error', 'steps worker: failed to save job state table=' . $table . ' job=' . $jobId . ' status=' . (string)($state['status'] ?? ''), __FILE__, __LINE__);
}
function steps_worker_find_7z(): string {
    static $memo = null;
    if ($memo !== null) return $memo;
    $candidates = [];
    $env7 = getenv('SEVENZIP');
    if ($env7 !== false && $env7 !== '') $candidates[] = (string)$env7;
    if ((PHP_OS_FAMILY ?? '') === 'Windows') {
        foreach ([getenv('ProgramFiles') ?: 'C:\\Program Files', getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)'] as $base) {
            $candidates[] = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '7-Zip' . DIRECTORY_SEPARATOR . '7z.exe';
        }
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . '7z.exe';
    }
    foreach (['/usr/bin/7z', '/usr/local/bin/7z', '/usr/bin/7za', '/usr/local/bin/7za'] as $p) {
        $candidates[] = $p;
    }
    foreach ($candidates as $p) {
        if ($p !== '' && is_file($p) && (((PHP_OS_FAMILY ?? '') === 'Windows') || is_executable($p))) {
            return $memo = (realpath($p) ?: $p);
        }
    }
    $pathEnv = ((PHP_OS_FAMILY ?? '') === 'Windows') ? (getenv('PATH') ?: '') : '/usr/local/bin:/usr/bin:/bin';
    $finder = ((PHP_OS_FAMILY ?? '') === 'Windows')
        ? 'where 7z 2>nul'
        : 'PATH=' . escapeshellarg($pathEnv) . ' command -v 7z 2>/dev/null || PATH=' . escapeshellarg($pathEnv) . ' command -v 7za 2>/dev/null';
    $found = trim((string)shell_exec($finder));
    if ($found !== '') {
        $first = preg_split('/\R/', $found)[0] ?? '';
        if ($first !== '' && is_file($first)) return $memo = (realpath($first) ?: $first);
    }
    return $memo = '';
}
function steps_worker_extract_zip_php(string $zipPath, string $targetDir, string $password, string $logPath): string {
    if (!class_exists('ZipArchive')) return 'zip extension missing';
    $zip = new ZipArchive();
    $open = $zip->open($zipPath);
    if ($open !== true) {
        @file_put_contents($logPath, '[' . date('c') . "] php zip open failed code={$open}\n", FILE_APPEND);
        return 'bad zip';
    }
    if ($password !== '') $zip->setPassword($password);
    @mkdir($targetDir, 0777, true);
    if (!$zip->extractTo($targetDir)) {
        $zip->close();
        @file_put_contents($logPath, '[' . date('c') . "] php zip extract failed\n", FILE_APPEND);
        return 'bad password';
    }
    $zip->close();
    @file_put_contents($logPath, '[' . date('c') . "] php zip extract ok\n", FILE_APPEND);
    return '';
}
function steps_worker_extract_zip(string $zipPath, string $targetDir, string $password = '', string $logPath = ''): string {
    $seven = steps_worker_find_7z();
    $unzip = '';
    foreach (['/usr/bin/unzip', '/usr/local/bin/unzip'] as $p) {
        if (is_file($p) && is_executable($p)) {
            $unzip = $p;
            break;
        }
    }
    if ($unzip === '') $unzip = trim((string)shell_exec('PATH=/usr/local/bin:/usr/bin:/bin command -v unzip 2>/dev/null'));
    $bin = $seven !== '' ? $seven : $unzip;
    if ($logPath === '') $logPath = dirname($targetDir) . DIRECTORY_SEPARATOR . 'worker.log';
    @mkdir($targetDir, 0777, true);
    @chmod($targetDir, 0777);
    @file_put_contents($logPath, '[' . date('c') . '] extract env user=' . (function_exists('posix_geteuid') ? (string)posix_geteuid() : '') . ' cwd=' . getcwd() . ' zip=' . $zipPath . ' target=' . $targetDir . ' seven=' . $seven . "\n", FILE_APPEND);
    if ($seven === '' && $password !== '') {
        $phpErr = steps_worker_extract_zip_php($zipPath, $targetDir, $password, $logPath);
        return $phpErr === '' ? '' : ($phpErr !== '' ? $phpErr : 'zip extractor not installed');
    }
    if ($bin === '') return 'zip extractor not installed';
    $base = basename($bin);
    $out = [];
    $rc = 1;
    if ($seven !== '') {
        $env = 'HOME=/tmp TMPDIR=/tmp ';
        $testCmd = $env . escapeshellarg($seven) . ' t -y -bd ' . ($password !== '' ? '-p' . escapeshellarg($password) . ' ' : '') . escapeshellarg($zipPath);
        @exec($testCmd . ' 2>&1', $out, $rc);
        @file_put_contents($logPath, '[' . date('c') . "] test rc={$rc}\n" . implode("\n", $out) . "\n", FILE_APPEND);
        if ((int)$rc !== 0) {
            $msg = strtolower(implode("\n", $out));
            if (str_contains($msg, 'wrong password') || str_contains($msg, 'password') || str_contains($msg, 'data error')) return 'bad password';
            if (str_contains($msg, 'not archive') || str_contains($msg, 'cannot find zipfile')) return 'bad zip';
            return trim(implode(' ', array_slice($out, -3))) ?: ('archive test failed rc=' . (int)$rc);
        }
        $out = [];
        $cmd = $env . escapeshellarg($seven) . ' x -y -bd ' . ($password !== '' ? '-p' . escapeshellarg($password) . ' ' : '') . '-o' . escapeshellarg($targetDir) . ' ' . escapeshellarg($zipPath);
    } else {
        $cmd = escapeshellarg($bin) . ' -qq ' . ($password !== '' ? '-P ' . escapeshellarg($password) . ' ' : '') . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($targetDir);
    }
    @exec($cmd . ' 2>&1', $out, $rc);
    @file_put_contents($logPath, '[' . date('c') . "] extract rc={$rc}\n" . implode("\n", $out) . "\n", FILE_APPEND);
    if ((int)$rc === 0) return '';
    $msg = strtolower(implode("\n", $out));
    if (str_contains($msg, 'permission denied') || str_contains($msg, 'cannot open output file')) return 'extract permission denied';
    if ($password !== '' && class_exists('ZipArchive')) {
        $phpErr = steps_worker_extract_zip_php($zipPath, $targetDir, $password, $logPath);
        if ($phpErr === '') return '';
        if ($phpErr === 'bad password') return $phpErr;
    }
    if ($seven !== '' && $unzip !== '' && $password === '') {
        $out2 = [];
        $cmd2 = escapeshellarg($unzip) . ' -qq ' . ($password !== '' ? '-P ' . escapeshellarg($password) . ' ' : '') . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($targetDir);
        @exec($cmd2 . ' 2>&1', $out2, $rc2);
        @file_put_contents($logPath, '[' . date('c') . "] unzip fallback rc={$rc2}\n" . implode("\n", $out2) . "\n", FILE_APPEND);
        if ((int)$rc2 === 0) return '';
        $msg .= "\n" . strtolower(implode("\n", $out2));
    }
    if (str_contains($msg, 'wrong password') || str_contains($msg, 'password') || str_contains($msg, 'data error')) return 'bad password';
    if (str_contains($msg, 'not archive') || str_contains($msg, 'cannot find zipfile')) return 'bad zip';
    if ((int)$rc === 81 || str_contains($msg, 'unsupported compression') || str_contains($msg, 'unsupported decryption')) {
        return $password !== '' ? 'bad password' : 'unsupported archive encryption';
    }
    return trim(implode(' ', array_slice($out, -3))) ?: ('extract failed rc=' . (int)$rc);
}
function steps_worker_norm_key(string $s): string {
    return preg_replace('/\s+/', ' ', strtolower(trim($s))) ?? '';
}
function steps_worker_load_snapshot(string $path, bool $allowZero = false): array {
    $obj = steps_worker_json_read($path);
    $out = [];
    foreach ($obj as $k => $v) {
        $ks = (string)$k;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ks)) continue;
        $iv = (int)$v;
        if ($allowZero ? $iv >= 0 : $iv > 0) $out[$ks] = $iv;
    }
    return $out;
}
function steps_worker_save_snapshot(string $path, array $rows): void {
    @file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function steps_worker_conflicts(array $cfg, array $daySteps): int {
    if (!$daySteps) return 0;
    for ($attempt = 0; $attempt < 20; $attempt++) {
        try {
            $db = steps_worker_connect_db($cfg);
            if (!$db) return 0;
            return steps_worker_conflicts_app_db($db, $daySteps);
        } catch (Throwable $e) {
            usleep(50000 * min($attempt + 1, 10));
        }
    }
    return 0;
}
function steps_worker_import(array $cfg, array $daySteps, string $mode, bool $allowZero = false): int {
    if (!$daySteps) return 0;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        try {
            $db = steps_worker_connect_db($cfg);
            if (!$db) return 0;
            return steps_worker_import_app_db($db, $daySteps, $mode, $allowZero);
        } catch (Throwable $e) {
            usleep(50000 * min($attempt + 1, 10));
        }
    }
    return 0;
}
function steps_worker_existing_dates_map(AppDatabase $db, array $days): array {
    if (!$days) return [];
    $days = array_values(array_unique(array_map('strval', $days)));
    sort($days);
    $map = [];
    $chunkSize = 500;
    foreach (array_chunk($days, $chunkSize) as $chunk) {
        $ph = [];
        $params = [];
        foreach ($chunk as $i => $day) {
            $key = ':d' . $i;
            $ph[] = $key;
            $params[$key] = $day;
        }
        $stmt = $db->prepare('SELECT date, steps FROM steps_days WHERE user_email = :u AND date IN (' . implode(',', $ph) . ')');
        steps_bind_user($stmt);
        foreach ($params as $key => $day) $stmt->bindValue($key, $day, PDO::PARAM_STR);
        $res = $stmt->execute();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $map[(string)$row['date']] = (int)$row['steps'];
        }
    }
    return $map;
}
function steps_worker_import_app_db(AppDatabase $db, array $daySteps, string $mode, bool $allowZero = false): int {
    steps_cache_bump();
    if (!$daySteps) return 0;
    ksort($daySteps);
    $existing = steps_worker_existing_dates_map($db, array_keys($daySteps));
    $replaceStmt = $db->prepare('INSERT INTO steps_days (user_email, date, steps) VALUES (:u, :d, :s) ON CONFLICT (user_email, date) DO UPDATE SET steps=excluded.steps');
    $insertStmt = $db->prepare('INSERT INTO steps_days (user_email, date, steps) VALUES (:u, :d, :s) ON CONFLICT (user_email, date) DO NOTHING');
    steps_bind_user($replaceStmt);
    steps_bind_user($insertStmt);
    $n = 0;
    foreach ($daySteps as $d => $s) {
        if ($allowZero ? $s < 0 : $s <= 0) continue;
        $date = (string)$d;
        $steps = (int)$s;
        if ($mode === 'new_only') {
            if (array_key_exists($date, $existing)) continue;
            $insertStmt->bindValue(':d', $date, PDO::PARAM_STR);
            $insertStmt->bindValue(':s', $steps, PDO::PARAM_INT);
            $insertStmt->execute();
            if ($db->changes() > 0) {
                $n++;
                $existing[$date] = $steps;
            }
            continue;
        }
        if ($mode === 'keep' && array_key_exists($date, $existing) && $existing[$date] !== $steps) {
            continue;
        }
        $replaceStmt->bindValue(':d', $date, PDO::PARAM_STR);
        $replaceStmt->bindValue(':s', $steps, PDO::PARAM_INT);
        $replaceStmt->execute();
        if ($db->changes() > 0) $n++;
        $existing[$date] = $steps;
    }
    return $n;
}
function steps_worker_conflicts_app_db(AppDatabase $db, array $daySteps): int {
    if (!$daySteps) return 0;
    $existing = steps_worker_existing_dates_map($db, array_map('strval', array_keys($daySteps)));
    $n = 0;
    foreach ($daySteps as $day => $steps) {
        if (isset($existing[(string)$day]) && $existing[(string)$day] !== (int)$steps) $n++;
    }
    return $n;
}
