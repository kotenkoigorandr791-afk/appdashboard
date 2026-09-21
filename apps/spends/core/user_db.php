<?php
require_once __DIR__ . '/../../../lib/user_schema_pg.php';
const SPENDS_STORE_CATEGORIES = ['food','groceries','cafes','clothes','shoes','home','apartment','utilities','transport','car','travel','health','pharmacy','beauty','children','pets','build','education','sports','electronics','subscriptions','entertainment','gifts','services','finance','taxes','insurance','charity','other'];
function spendsStoreCategories(): array {
    return SPENDS_STORE_CATEGORIES;
}
function spendsNormalizeStoreCategory($category): string {
    $category = (string)$category;
    return in_array($category, SPENDS_STORE_CATEGORIES, true) ? $category : 'other';
}
function getUserDBPath($userEmail) {
    $safe = getSafeFolderName($userEmail);
    $dir  = APPS_DATA_DIR . $safe;
    if (!file_exists($dir)) mkdir($dir, SPENDS_RUNTIME_DIR_MODE, true);
    @chmod($dir, SPENDS_RUNTIME_DIR_MODE);
    return $dir . '/data.sqlite3';
}
function getUserSQLite($userEmail) {
    static $cache = [];
    if ($userEmail === '' || $userEmail === 'default_user') $userEmail = 'guest@local';
    if (isset($cache[$userEmail])) return $cache[$userEmail];
    $udb = app_user_db_connect('spends', $userEmail);
    app_spends_migrate_sqlite_file($userEmail, $udb);
    $cache[$userEmail] = $udb;
    return $udb;
}
function app_spends_migrate_sqlite_file(string $email, AppDatabase $udb): void {
    $path = getUserDBPath($email);
    if (!is_file($path)) return;
    $cnt = (int)$udb->querySingle('SELECT COUNT(*) FROM expenses');
    if ($cnt > 0) return;
    try {
        $sqlite = new SQLite3($path);
        $sqlite->enableExceptions(true);
        foreach (['settings', 'stores', 'expenses', 'incomes', 'ocr_entries', 'table_settings'] as $table) {
            $res = @$sqlite->query("SELECT * FROM \"$table\"");
            if (!$res) continue;
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                if ($table === 'settings') dbSetSetting($udb, (string)$row['key'], (string)$row['value']);
                elseif ($table === 'table_settings') dbSetTableSetting($udb, (string)$row['key'], (string)$row['value']);
                elseif ($table === 'stores') {
                    $st = $udb->prepare('INSERT INTO stores (id,name,visible,sort_order,category) VALUES (:id,:n,:v,:o,:c) ON CONFLICT (id) DO UPDATE SET name=EXCLUDED.name, visible=EXCLUDED.visible, sort_order=EXCLUDED.sort_order, category=EXCLUDED.category');
                    $st->bindValue(':id', (string)$row['id'], PDO::PARAM_STR);
                    $st->bindValue(':n', (string)$row['name'], PDO::PARAM_STR);
                    $st->bindValue(':v', (int)($row['visible'] ?? 1), PDO::PARAM_INT);
                    $st->bindValue(':o', (int)($row['sort_order'] ?? 0), PDO::PARAM_INT);
                    $st->bindValue(':c', spendsNormalizeStoreCategory($row['category'] ?? 'other'), PDO::PARAM_STR);
                    $st->execute();
                } elseif ($table === 'expenses') {
                    $st = $udb->prepare('INSERT INTO expenses (date,store,amount,entry_source,ocr_raw_store) VALUES (:d,:s,:a,:es,:or) ON CONFLICT (date,store) DO UPDATE SET amount=EXCLUDED.amount');
                    $st->bindValue(':d', (string)$row['date'], PDO::PARAM_STR);
                    $st->bindValue(':s', (string)$row['store'], PDO::PARAM_STR);
                    $st->bindValue(':a', (float)($row['amount'] ?? 0), PDO::PARAM_STR);
                    $st->bindValue(':es', (string)($row['entry_source'] ?? 'manual'), PDO::PARAM_STR);
                    $st->bindValue(':or', $row['ocr_raw_store'] ?? null, PDO::PARAM_NULL);
                    $st->execute();
                } elseif ($table === 'incomes') {
                    $st = $udb->prepare('INSERT INTO incomes (date,amount) VALUES (:d,:a) ON CONFLICT (date) DO UPDATE SET amount=EXCLUDED.amount');
                    $st->bindValue(':d', (string)$row['date'], PDO::PARAM_STR);
                    $st->bindValue(':a', (float)($row['amount'] ?? 0), PDO::PARAM_STR);
                    $st->execute();
                }
            }
        }
        $sqlite->close();
    } catch (Throwable $e) {
        error_log('spends sqlite migrate ' . $email . ': ' . $e->getMessage());
    }
}
function migrateExistingFiles($userEmail, $udb) {
    $safe = getSafeFolderName($userEmail);
    $userDir = APPS_DATA_DIR . $safe;
    $settingsFile = $userDir . '/user_settings.json';
    if (file_exists($settingsFile)) {
        $js = json_decode(file_get_contents($settingsFile), true) ?: [];
        dbSetSetting($udb, 'monthly_budget', $js['monthly_budget'] ?? 100);
        dbSetSetting($udb, 'currency', $js['currency'] ?? 'USD');
        if (!empty($js['stores']) && is_array($js['stores'])) {
            $ord = 0;
            foreach ($js['stores'] as $s) {
                if (!is_array($s) || empty($s['name'])) continue;
                $sid = $s['id'] ?? (string)time() . rand(100,999);
                $vis = isset($s['visible']) ? (int)(bool)$s['visible'] : 1;
                $stmt = $udb->prepare('INSERT INTO stores (id, name, visible, sort_order, category) VALUES (:id,:n,:v,:o,:c) ON CONFLICT (id) DO NOTHING');
                $stmt->bindValue(':id', $sid);
                $stmt->bindValue(':n',  $s['name']);
                $stmt->bindValue(':v',  $vis, PDO::PARAM_INT);
                $stmt->bindValue(':o',  $ord++, PDO::PARAM_INT);
                $stmt->bindValue(':c',  spendsNormalizeStoreCategory($s['category'] ?? 'other'), PDO::PARAM_STR);
                $stmt->execute();
            }
        }
    } else {
        dbSetSetting($udb, 'monthly_budget', 100);
        dbSetSetting($udb, 'currency', 'USD');
    }
    $gsFile = null;
    if (function_exists('spends_appdata_subdir')) {
        $gsDir = spends_appdata_subdir('');
        if ($gsDir) $gsFile = rtrim($gsDir, '/') . '/global_settings.json';
    }
    if ($gsFile === null || !is_file($gsFile)) {
        $gsLink = __DIR__ . '/../global_settings.json';
        $gsFile = is_file($gsLink) ? $gsLink : null;
    }
    if ($gsFile && file_exists($gsFile)) {
        $gs = json_decode(file_get_contents($gsFile), true) ?: [];
        $ts = $gs['table_settings'][$safe] ?? $gs['table_settings'][$userEmail] ?? [];
        if (!empty($ts)) {
            dbSetTableSetting($udb, 'sort_by',      $ts['sort_by']      ?? 'date_asc');
            dbSetTableSetting($udb, 'months_window', $ts['months_window'] ?? 1);
            dbSetTableSetting($udb, 'start_date',   $ts['start_date']   ?? '');
        }
    }
    $csvFile = $userDir . '/Spends.csv';
    if (file_exists($csvFile)) {
        $fh = fopen($csvFile, 'r');
        $headers = fgetcsv($fh);
        if ($headers) {
            $csvStores = array_slice($headers, 1);
            $udb->exec('BEGIN');
            while ($row = fgetcsv($fh)) {
                if (empty($row[0])) continue;
                $date = $row[0];
                if (preg_match('/^(\d{2})-(\d{2})-(\d{2})$/', $date, $m)) $date = '20' . $m[3] . '-' . $m[2] . '-' . $m[1];
                elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) $date = $m[3] . '-' . $m[2] . '-' . $m[1];
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) continue;
                for ($i = 1; $i < count($row); $i++) {
                    $store  = $csvStores[$i-1] ?? '';
                    $amount = is_numeric($row[$i]) ? (float)$row[$i] : 0;
                    if ($store === '') continue;
                    $st = $udb->prepare('INSERT INTO expenses (date, store, amount) VALUES (:d,:s,:a) ON CONFLICT (date,store) DO UPDATE SET amount=EXCLUDED.amount');
                    $st->bindValue(':d', $date);
                    $st->bindValue(':s', $store);
                    $st->bindValue(':a', $amount, PDO::PARAM_STR);
                    $st->execute();
                }
            }
            $udb->exec('COMMIT');
        }
        fclose($fh);
    }
}
function dbSetSetting($udb, $key, $value) {
    $st = $udb->prepare('INSERT INTO settings (key, value) VALUES (:k,:v) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value');
    $st->bindValue(':k', $key);
    $st->bindValue(':v', (string)$value);
    $st->execute();
}
function dbGetSetting($udb, $key, $default = null) {
    $st = $udb->prepare('SELECT value FROM settings WHERE key = :k');
    $st->bindValue(':k', $key);
    $r = $st->execute()->fetch(PDO::FETCH_ASSOC);
    return $r ? $r['value'] : $default;
}
function dbSetTableSetting($udb, $key, $value) {
    $st = $udb->prepare('INSERT INTO table_settings (key, value) VALUES (:k,:v) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value');
    $st->bindValue(':k', $key);
    $st->bindValue(':v', (string)$value);
    $st->execute();
}
function dbGetTableSetting($udb, $key, $default = null) {
    $st = $udb->prepare('SELECT value FROM table_settings WHERE key = :k');
    $st->bindValue(':k', $key);
    $r = $st->execute()->fetch(PDO::FETCH_ASSOC);
    return $r ? $r['value'] : $default;
}
function dbGetStores($udb) {
    $res  = $udb->query("SELECT id, name, visible, sort_order, COALESCE(category,'other') AS category FROM stores ORDER BY sort_order ASC, id ASC");
    $list = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $row['visible'] = (bool)(int)$row['visible'];
        $row['category'] = spendsNormalizeStoreCategory($row['category'] ?? 'other');
        $list[] = $row;
    }
    return $list;
}
