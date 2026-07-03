<?php
require_once __DIR__ . '/../../../lib/user_schema_pg.php';
require_once __DIR__ . '/../auth_common.php';
require_once __DIR__ . '/../video_job_sqlite.php';
require_once __DIR__ . '/../utils/google_fit_takeout/fit_takeout_state.php';
function getUserDB(string $userDir): ?AppDatabase {
    static $cache = [];
    $email = get_current_user_email() ?: ('folder_' . basename($userDir));
    if (isset($cache[$email])) return $cache[$email];
    try {
        $userDb = app_user_db_connect('steps', $email);
        app_steps_migrate_sqlite_file($email, $userDir, $userDb);
        video_job_ensure_schema($userDb);
        fit_takeout_ensure_schema($userDb);
        require_once __DIR__ . '/../utils/zeep_life/zeep_state.php';
        zeep_job_ensure_schema($userDb);
        $cache[$email] = $userDb;
        return $userDb;
    } catch (Exception $e) {
        error_log('User DB init failed: ' . $e->getMessage());
        return null;
    }
}
function app_steps_migrate_sqlite_file(string $email, string $userDir, AppDatabase $db): void {
    $path = "$userDir/user_data.sqlite3";
    if (!is_file($path)) return;
    $cnt = (int)$db->querySingle('SELECT COUNT(*) FROM steps');
    if ($cnt > 0) return;
    try {
        $sqlite = new SQLite3($path);
        $sqlite->enableExceptions(true);
        $res = $sqlite->query('SELECT date, steps FROM steps');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $st = $db->prepare('INSERT INTO steps (date, steps) VALUES (:d,:s) ON CONFLICT (date) DO UPDATE SET steps=EXCLUDED.steps');
            $st->bindValue(':d', (string)$row['date'], SQLITE3_TEXT);
            $st->bindValue(':s', (int)$row['steps'], SQLITE3_INTEGER);
            $st->execute();
        }
        $res = $sqlite->query('SELECT key, value FROM settings');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $st = $db->prepare('INSERT INTO settings (key, value) VALUES (:k,:v) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value');
            $st->bindValue(':k', (string)$row['key'], SQLITE3_TEXT);
            $st->bindValue(':v', (string)$row['value'], SQLITE3_TEXT);
            $st->execute();
        }
        $sqlite->close();
    } catch (Throwable $e) {
        error_log('steps sqlite migrate ' . $email . ': ' . $e->getMessage());
    }
}
