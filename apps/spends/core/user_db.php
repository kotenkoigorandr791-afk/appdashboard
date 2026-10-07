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
