<?php
// ─── AJAX / POST handlers ──────────────────────────────────────────────────────
if (isset($_GET['get_expense_meta'])) {
    $date = trim($_GET['date'] ?? '');
    $store = trim($_GET['store'] ?? '');
    if ($date === '' || $store === '') exit(json_encode(['status'=>'error','message'=>'Invalid data']));
    $st = $udb->prepare("SELECT entry_source, ocr_raw_store FROM expenses WHERE date=:d AND store=:s LIMIT 1");
    $st->bindValue(':d', $date);
    $st->bindValue(':s', $store);
    $row = $st->execute()->fetchArray(SQLITE3_ASSOC) ?: [];
    $source = strtolower((string)($row['entry_source'] ?? 'manual'));
    $ocrRaw = (string)($row['ocr_raw_store'] ?? '');
    exit(json_encode(['status'=>'success','meta'=>['entry_source'=>$source,'ocr_raw_store'=>$ocrRaw]]));
}
if (isset($_GET['get_ocr_stats'])) {
    $storeFilter = trim($_GET['store'] ?? '__all__');
    $year = (int)($_GET['year'] ?? 0);
    $yearWhere = $year > 0 ? " AND date LIKE '" . SQLite3::escapeString((string)$year) . "-%'" : '';
    $stores = [];
    $sr = $udb->query("SELECT DISTINCT store FROM expenses ORDER BY store ASC");
    while ($row = $sr->fetchArray(SQLITE3_ASSOC)) if (!empty($row['store'])) $stores[] = $row['store'];
    $daily = [];
    $monthly = [];
    if ($storeFilter === '__all__') {
        $dr = $udb->query("SELECT date,store,ROUND(SUM(amount),2) AS amount FROM expenses WHERE amount>0 $yearWhere GROUP BY date,store ORDER BY date ASC,store ASC");
        while ($row = $dr->fetchArray(SQLITE3_ASSOC)) $daily[] = $row;
        $mr = $udb->query("SELECT substr(date,1,7) AS ym,store,ROUND(SUM(amount),2) AS amount_sum,COUNT(DISTINCT date) AS intensity FROM expenses WHERE amount>0 $yearWhere GROUP BY ym,store ORDER BY ym ASC,store ASC");
        while ($row = $mr->fetchArray(SQLITE3_ASSOC)) $monthly[] = $row;
    } else {
        $st = $udb->prepare("SELECT date,store,ROUND(SUM(amount),2) AS amount FROM expenses WHERE store=:s AND amount>0 $yearWhere GROUP BY date,store ORDER BY date ASC");
        $st->bindValue(':s', $storeFilter);
        $dr = $st->execute();
        while ($row = $dr->fetchArray(SQLITE3_ASSOC)) $daily[] = $row;
        $st2 = $udb->prepare("SELECT substr(date,1,7) AS ym,store,ROUND(SUM(amount),2) AS amount_sum,COUNT(DISTINCT date) AS intensity FROM expenses WHERE store=:s AND amount>0 $yearWhere GROUP BY ym,store ORDER BY ym ASC");
        $st2->bindValue(':s', $storeFilter);
        $mr = $st2->execute();
        while ($row = $mr->fetchArray(SQLITE3_ASSOC)) $monthly[] = $row;
    }
    exit(json_encode(['status'=>'success','stores'=>$stores,'daily'=>$daily,'monthly'=>$monthly]));
}
if (isset($_GET['get_available_years'])) {
    $years = [];
    $res = $udb->query("SELECT DISTINCT y FROM (SELECT substr(date,1,4) AS y FROM expenses WHERE amount>0 UNION SELECT substr(date,1,4) AS y FROM incomes WHERE amount>0) ORDER BY y ASC");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $y = (int)($row['y'] ?? 0);
        if ($y > 0) $years[] = $y;
    }
    exit(json_encode(['status'=>'success','years'=>$years]));
}

// Exchange Rates API
if (isset($_GET['get_exchange_rates'])) {
    $base = $_GET['base'] ?? 'USD';
    try {
        $rates = [];
        // Try multiple sources
        $url = "https://api.exchangerate-api.com/v4/latest/$base";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['rates'])) {
                $rates = $data['rates'];
            }
        }
        
        // Fallback to NBU for UAH
        if (empty($rates)) {
            $nbu = file_get_contents('https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json');
            if ($nbu) {
                $nbuRates = json_decode($nbu, true);
                foreach ($nbuRates as $r) {
                    if ($r['cc'] === 'USD') $rates['UAH'] = $r['rate'];
                    if ($r['cc'] === 'EUR') $rates['EUR'] = $r['rate'];
                }
                $rates['USD'] = 1;
            }
        }
        
        exit(json_encode([
            'status' => 'success',
            'base' => $base,
            'rates' => $rates,
            'timestamp' => time()
        ]));
    } catch (Exception $e) {
        exit(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
    }
}

// ─── Expenses page data (AJAX for table pagination) ───────────────────────────
if (isset($_GET['get_expenses_page'])) {
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $search    = trim($_GET['search'] ?? '');
    $perPage   = max(1, (int)($_GET['per_page'] ?? 25));
    $sort      = $_GET['sort'] ?? 'date_asc';
    $startDate = trim($_GET['start_date'] ?? '');
    $sortDir   = $sort === 'date_desc' ? 'DESC' : 'ASC';
    $where  = '1=1';
    $params = [];
    if ($startDate) {
        $where .= " AND date >= '" . SQLite3::escapeString($startDate) . "'";
    }
    if ($search !== '') {
        $where .= " AND (date LIKE :s OR store LIKE :s2 OR CAST(amount AS TEXT) LIKE :s3)";
        $params[':s']  = "%$search%";
        $params[':s2'] = "%$search%";
        $params[':s3'] = "%$search%";
    }
    $minMaxSt = $udb->prepare("SELECT MIN(date) AS lo, MAX(date) AS hi FROM expenses WHERE $where");
    foreach ($params as $k => $v) {
        $minMaxSt->bindValue($k, $v);
    }
    $minMax = $minMaxSt->execute()->fetchArray(SQLITE3_ASSOC);
    if (empty($minMax['lo'])) {
        exit(json_encode(['status' => 'success', 'rows' => [], 'dates' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1]));
    }
    $rangeStart = $startDate !== '' ? $startDate : $minMax['lo'];
    $rangeEnd   = $minMax['hi'];
    if (strcmp($rangeStart, $rangeEnd) > 0) {
        exit(json_encode(['status' => 'success', 'rows' => [], 'dates' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1]));
    }
    $allDays = listCalendarDaysInclusive($rangeStart, $rangeEnd);
    if ($sort === 'date_desc') {
        $allDays = array_reverse($allDays);
    }
    $cnt = count($allDays);
    $totalPages = max(1, (int)ceil($cnt / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $pageDays = array_slice($allDays, $offset, $perPage);
    $rows = [];
    $incomes = [];
    if (!empty($pageDays)) {
        $inDates = implode(',', array_map(fn($d) => "'" . SQLite3::escapeString($d) . "'", $pageDays));
        $dataRes = $udb->query("SELECT date, store, amount FROM expenses WHERE date IN ($inDates) ORDER BY date $sortDir, store");
        while ($r = $dataRes->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $r;
        }
        $incRes = $udb->query("SELECT date, amount FROM incomes WHERE date IN ($inDates) ORDER BY date $sortDir");
        while ($r = $incRes->fetchArray(SQLITE3_ASSOC)) $incomes[] = $r;
    }
    exit(json_encode([
        'status'     => 'success',
        'rows'       => $rows,
        'incomes'    => $incomes,
        'dates'      => $pageDays,
        'total'      => $cnt,
        'page'       => $page,
        'totalPages' => $totalPages,
    ]));
}

// ─── Year data for chart (AJAX) ────────────────────────────────────────────────
if (isset($_GET['year_data'])) {
    $year = (int)($_GET['year_data']);
    if ($year < 2000 || $year > 2100) exit(json_encode(['status'=>'error','message'=>'Invalid year']));

    $yearStoreNames = array_map(fn($s) => $s['name'], $userSettings['stores']);
    $yearDates = listCalendarDaysInclusive(sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year));

    $yearExpenses = [];
    $yearIncomes = [];
    if (!empty($yearStoreNames)) {
        $inList = implode(',', array_map(fn($s)=>"'".SQLite3::escapeString($s)."'", $yearStoreNames));
        $res = $udb->query("SELECT date, store, amount FROM expenses WHERE store IN ($inList) AND date LIKE '$year-%' ORDER BY date ASC");
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $yearExpenses[$row['date']][$row['store']] = (float)$row['amount'];
        }
    }
    $resInc = $udb->query("SELECT date, amount FROM incomes WHERE date LIKE '$year-%' ORDER BY date ASC");
    while ($row = $resInc->fetchArray(SQLITE3_ASSOC)) $yearIncomes[$row['date']] = (float)$row['amount'];

    exit(json_encode([
        'status'   => 'success',
        'year'     => $year,
        'dates'    => $yearDates,
        'expenses' => $yearExpenses,
        'incomes'  => $yearIncomes,
    ]));
}

if (isset($_POST['add_date_ajax'])) {
    $date = $_POST['date'] ?? '';
    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date)) {
        $st = $udb->prepare('SELECT COUNT(*) FROM expenses WHERE date=:d');
        $st->bindValue(':d', $date);
        $exists = (int)$st->execute()->fetchArray(SQLITE3_NUM)[0] > 0;
        if ($exists) exit(json_encode(['status'=>'error','message'=>'Date already exists']));

        $stores = dbGetStores($udb);
        $udb->exec('BEGIN');
        foreach ($stores as $s) {
            $ist = $udb->prepare('INSERT OR IGNORE INTO expenses (date,store,amount) VALUES (:d,:s,0)');
            $ist->bindValue(':d', $date);
            $ist->bindValue(':s', $s['name']);
            $ist->execute();
        }
        $inc = $udb->prepare('INSERT OR IGNORE INTO incomes (date,amount) VALUES (:d,0)');
        $inc->bindValue(':d', $date);
        $inc->execute();
        $udb->exec('COMMIT');
        exit(json_encode(['status'=>'success','message'=>'Date added successfully','date'=>$date]));
    }
    exit(json_encode(['status'=>'error','message'=>'Invalid date format']));
}

if (isset($_POST['add_edit_store'])) {
    $storeId   = $_POST['store_id']   ?? (string)time();
    $storeName = trim($_POST['store_name'] ?? '');
    if (empty($storeName)) exit(json_encode(['status'=>'error','message'=>'Store name is required']));

    $existing = $udb->prepare('SELECT id, name FROM stores WHERE id=:id');
    $existing->bindValue(':id', $storeId);
    $row = $existing->execute()->fetchArray(SQLITE3_ASSOC);

    if ($row) {
        $oldName = $row['name'];
        $st = $udb->prepare('UPDATE stores SET name=:n WHERE id=:id');
        $st->bindValue(':n', $storeName);
        $st->bindValue(':id', $storeId);
        $st->execute();
        if ($oldName !== $storeName) {
            $st2 = $udb->prepare('UPDATE expenses SET store=:n WHERE store=:o');
            $st2->bindValue(':n', $storeName);
            $st2->bindValue(':o', $oldName);
            $st2->execute();
            $safe = getSafeFolderName($currentUser);
            $uDir = APPS_DATA_DIR . $safe;
            $oldFile = $uDir . '/store_' . preg_replace('/[^a-zA-Z0-9]/', '_', $oldName) . '.jpg';
            $newFile = $uDir . '/store_' . preg_replace('/[^a-zA-Z0-9]/', '_', $storeName) . '.jpg';
            if (file_exists($oldFile)) rename($oldFile, $newFile);
        }
    } else {
        $maxOrd = (int)$udb->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM stores')->fetchArray(SQLITE3_NUM)[0];
        $st = $udb->prepare('INSERT OR IGNORE INTO stores (id,name,visible,sort_order) VALUES (:id,:n,1,:o)');
        $st->bindValue(':id', $storeId);
        $st->bindValue(':n',  $storeName);
        $st->bindValue(':o',  $maxOrd, SQLITE3_INTEGER);
        $st->execute();
        $dRes = $udb->query("SELECT DISTINCT date FROM expenses");
        $udb->exec('BEGIN');
        while ($dRow = $dRes->fetchArray(SQLITE3_ASSOC)) {
            $ist = $udb->prepare('INSERT OR IGNORE INTO expenses (date,store,amount) VALUES (:d,:s,0)');
            $ist->bindValue(':d', $dRow['date']);
            $ist->bindValue(':s', $storeName);
            $ist->execute();
        }
        $udb->exec('COMMIT');
    }

    if (isset($_FILES['store_photo']) && $_FILES['store_photo']['error'] === UPLOAD_ERR_OK) {
        $imageInfo = getimagesize($_FILES['store_photo']['tmp_name']);
        if ($imageInfo !== false) {
            $image = imagecreatefromstring(file_get_contents($_FILES['store_photo']['tmp_name']));
            $size  = min(imagesx($image), imagesy($image));
            $newImg= imagecreatetruecolor($size, $size);
            imagefill($newImg, 0, 0, imagecolorallocate($newImg, 255, 255, 255));
            imagecopyresampled($newImg, $image, 0, 0, (imagesx($image)-$size)/2, (imagesy($image)-$size)/2, $size, $size, $size, $size);
            $fn   = preg_replace('/[^a-zA-Z0-9]/', '_', $storeName);
            $safe = getSafeFolderName($currentUser);
            $uDir = APPS_DATA_DIR . $safe;
            if (!file_exists($uDir)) mkdir($uDir, 0755, true);
            imagejpeg($newImg, "$uDir/store_$fn.jpg", 90);
            imagedestroy($image); imagedestroy($newImg);
        }
    }
    $sr = $udb->prepare('SELECT id, name, visible FROM stores WHERE id = :id');
    $sr->bindValue(':id', $storeId);
    $stRow = $sr->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$stRow) {
        exit(json_encode(['status' => 'success']));
    }
    exit(json_encode([
        'status'  => 'success',
        'store'   => [
            'id'      => (string) $stRow['id'],
            'name'    => $stRow['name'],
            'visible' => (bool) (int) $stRow['visible'],
        ],
    ]));
}

if (isset($_POST['delete_store'])) {
    $storeId   = $_POST['store_id']   ?? '';
    $storeName = $_POST['store_name'] ?? '';
    $st = $udb->prepare('DELETE FROM stores WHERE id=:id');
    $st->bindValue(':id', $storeId);
    $st->execute();
    $st2 = $udb->prepare('DELETE FROM expenses WHERE store=:s');
    $st2->bindValue(':s', $storeName);
    $st2->execute();
    $fn   = preg_replace('/[^a-zA-Z0-9]/', '_', $storeName);
    $safe = getSafeFolderName($currentUser);
    $uDir = APPS_DATA_DIR . $safe;
    $photo= "$uDir/store_$fn.jpg";
    if (file_exists($photo)) unlink($photo);
    exit(json_encode(['status'=>'success','message'=>'Store deleted successfully']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings'])) {
    $budget   = filter_var($_POST['monthly_budget'] ?? 100, FILTER_VALIDATE_FLOAT) ?: 100;
    $currency = $_POST['currency'] ?? 'USD';
    dbSetSetting($udb, 'monthly_budget', $budget);
    dbSetSetting($udb, 'currency', $currency);
    dbSetSetting($udb, 'show_incomes', isset($_POST['show_incomes']) ? '1' : '0');
    exit(json_encode(['status'=>'success']));
}

if (isset($_POST['update_expense'])) {
    $date   = $_POST['date']   ?? '';
    $store  = $_POST['store']  ?? '';
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($date && $store && $amount !== false) {
        $st = $udb->prepare("INSERT INTO expenses (date,store,amount,entry_source,ocr_raw_store) VALUES (:d,:s,:a,'manual',NULL) ON CONFLICT(date,store) DO UPDATE SET amount=excluded.amount,entry_source='manual',ocr_raw_store=NULL");
        $st->bindValue(':d', $date);
        $st->bindValue(':s', $store);
        $st->bindValue(':a', $amount, SQLITE3_FLOAT);
        $st->execute();
        exit(json_encode(['status'=>'success']));
    }
    exit(json_encode(['status'=>'error','message'=>'Invalid data']));
}
if (isset($_POST['update_income'])) {
    $date = $_POST['date'] ?? '';
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($date && $amount !== false) {
        $st = $udb->prepare("INSERT INTO incomes(date,amount) VALUES(:d,:a) ON CONFLICT(date) DO UPDATE SET amount=excluded.amount");
        $st->bindValue(':d', $date);
        $st->bindValue(':a', $amount, SQLITE3_FLOAT);
        $st->execute();
        exit(json_encode(['status'=>'success']));
    }
    exit(json_encode(['status'=>'error','message'=>'Invalid income data']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_csv_analyze'])) {
    header('Content-Type: application/json; charset=UTF-8');
    if (empty($_FILES['bank_csv_file']) || (int) ($_FILES['bank_csv_file']['error'] ?? 0) !== UPLOAD_ERR_OK) {
        exit(json_encode(['status' => 'error', 'message' => 'upload']));
    }
    require_once __DIR__ . '/../utils/BankStatementImport.php';
    $sz = (int) ($_FILES['bank_csv_file']['size'] ?? 0);
    if ($sz <= 0 || $sz > BankStatementImport::MAX_BYTES) {
        exit(json_encode(['status' => 'error', 'message' => 'file_too_large']));
    }
    $tmpDir = $userDir . '/bank_import';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }
    $token = bin2hex(random_bytes(16));
    $dest = $tmpDir . '/' . $token . '.csv';
    if (!move_uploaded_file($_FILES['bank_csv_file']['tmp_name'], $dest)) {
        exit(json_encode(['status' => 'error', 'message' => 'move']));
    }
    if (!isset($_SESSION['bank_csv_sessions']) || !is_array($_SESSION['bank_csv_sessions'])) {
        $_SESSION['bank_csv_sessions'] = [];
    }
    $cut = time() - 7200;
    foreach ($_SESSION['bank_csv_sessions'] as $tk => $meta) {
        if (($meta['ts'] ?? 0) < $cut && !empty($meta['path']) && is_file($meta['path'])) {
            @unlink($meta['path']);
            unset($_SESSION['bank_csv_sessions'][$tk]);
        }
    }
    try {
        $storeNames = array_map(static fn ($s) => (string) ($s['name'] ?? ''), $userSettings['stores'] ?? []);
        $base = (string) ($userSettings['currency'] ?? 'USD');
        $res = BankStatementImport::analyze($udb, $dest, $storeNames, $base);
        $_SESSION['bank_csv_sessions'][$token] = ['path' => $dest, 'user' => $currentUser, 'ts' => time()];
        $res['token'] = $token;
        exit(json_encode($res));
    } catch (Throwable $e) {
        @unlink($dest);
        exit(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_csv_apply'])) {
    header('Content-Type: application/json; charset=UTF-8');
    require_once __DIR__ . '/../utils/BankStatementImport.php';
    $payload = json_decode((string) ($_POST['bank_csv_apply'] ?? ''), true);
    if (!is_array($payload)) {
        exit(json_encode(['status' => 'error', 'message' => 'bad_json']));
    }
    $token = preg_replace('/[^a-f0-9]/', '', (string) ($payload['token'] ?? ''));
    $exclude = $payload['exclude_group_keys'] ?? [];
    $storeMap = $payload['store_map'] ?? [];
    $conflictMode = ($payload['conflict_mode'] ?? 'skip') === 'replace' ? 'replace' : 'skip';
    $conflictDates = $payload['conflict_dates'] ?? [];
    if ($token === '' || strlen($token) < 16) {
        exit(json_encode(['status' => 'error', 'message' => 'bad_token']));
    }
    if (!isset($_SESSION['bank_csv_sessions'][$token])) {
        exit(json_encode(['status' => 'error', 'message' => 'session']));
    }
    $meta = $_SESSION['bank_csv_sessions'][$token];
    if (($meta['user'] ?? '') !== $currentUser || empty($meta['path']) || !is_file($meta['path'])) {
        exit(json_encode(['status' => 'error', 'message' => 'session']));
    }
    if (!is_array($exclude)) {
        $exclude = [];
    }
    if (!is_array($storeMap)) {
        $storeMap = [];
    }
    if (!is_array($conflictDates)) {
        $conflictDates = [];
    }
    $path = (string) $meta['path'];
    try {
        $storeNames = array_map(static fn ($s) => (string) ($s['name'] ?? ''), $userSettings['stores'] ?? []);
        $base = (string) ($userSettings['currency'] ?? 'USD');
        $parsed = BankStatementImport::readCsv($path);
        $built = BankStatementImport::buildTransactions($parsed, $storeNames, $base);
        $tx = $built['transactions'];
        BankStatementImport::markDuplicates($udb, $tx);
        BankStatementImport::apply($udb, $tx, $exclude, $storeMap, $conflictMode, $conflictDates);
        @unlink($path);
        unset($_SESSION['bank_csv_sessions'][$token]);
        exit(json_encode(['status' => 'success']));
    } catch (Throwable $e) {
        exit(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
    }
}

if (isset($_POST['save_table_settings'])) {
    $sortBy     = $_POST['sort_by']       ?? 'date_asc';
    $rowsPerPage= (int)($_POST['rows_per_page'] ?? 25);
    $startDate  = $_POST['start_date']    ?? '';
    dbSetTableSetting($udb, 'sort_by',       $sortBy);
    dbSetTableSetting($udb, 'rows_per_page', $rowsPerPage);
    dbSetTableSetting($udb, 'start_date',    $startDate);

    $visible = $_POST['store_visible'] ?? [];
    $hidden  = $_POST['store_hidden']  ?? [];
    foreach ($visible as $sid) {
        $st = $udb->prepare('UPDATE stores SET visible=1 WHERE id=:id');
        $st->bindValue(':id', $sid); $st->execute();
    }
    foreach ($hidden as $sid) {
        $st = $udb->prepare('UPDATE stores SET visible=0 WHERE id=:id');
        $st->bindValue(':id', $sid); $st->execute();
    }
    exit(json_encode(['status'=>'success']));
}

if (isset($_POST['bulk_delete_data'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $mode = $_POST['bd_mode'] ?? '';
    if ($mode === 'all') {
        if (trim((string)($_POST['bd_confirm_all'] ?? '')) !== 'DELETE') {
            exit(json_encode(['status' => 'error', 'message' => 'confirm_delete']));
        }
        $udb->exec('DELETE FROM expenses');
        $udb->exec('DELETE FROM incomes');
        exit(json_encode(['status' => 'success']));
    }
    if ($mode === 'range') {
        $df = trim((string)($_POST['bd_date_from'] ?? ''));
        $dt = trim((string)($_POST['bd_date_to'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $df) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
            exit(json_encode(['status' => 'error', 'message' => 'invalid_dates']));
        }
        if (strcmp($df, $dt) > 0) {
            $tmp = $df;
            $df = $dt;
            $dt = $tmp;
        }
        $scope = ($_POST['bd_scope'] ?? 'expenses') === 'both' ? 'both' : 'expenses';
        $storeId = trim((string)($_POST['bd_store_id'] ?? '__all__'));
        $allowed = [];
        foreach ($userSettings['stores'] ?? [] as $s) {
            $allowed[(string)($s['id'] ?? '')] = (string)($s['name'] ?? '');
        }
        $udb->exec('BEGIN');
        if ($storeId === '' || $storeId === '__all__') {
            $st = $udb->prepare('DELETE FROM expenses WHERE date >= :a AND date <= :b');
            $st->bindValue(':a', $df);
            $st->bindValue(':b', $dt);
            $st->execute();
            if ($scope === 'both') {
                $sti = $udb->prepare('DELETE FROM incomes WHERE date >= :a AND date <= :b');
                $sti->bindValue(':a', $df);
                $sti->bindValue(':b', $dt);
                $sti->execute();
            }
        } else {
            if (!isset($allowed[$storeId])) {
                $udb->exec('ROLLBACK');
                exit(json_encode(['status' => 'error', 'message' => 'invalid_store']));
            }
            $sn = $allowed[$storeId];
            $st = $udb->prepare('DELETE FROM expenses WHERE date >= :a AND date <= :b AND store = :s');
            $st->bindValue(':a', $df);
            $st->bindValue(':b', $dt);
            $st->bindValue(':s', $sn);
            $st->execute();
        }
        $udb->exec('COMMIT');
        exit(json_encode(['status' => 'success']));
    }
    exit(json_encode(['status' => 'error', 'message' => 'invalid_mode']));
}

if (isset($_POST['backup_action'])) {
    $action        = $_POST['backup_action'];
    $userBackupDir = getUserBackupDir($currentUser);
    $allStores     = dbGetStores($udb);

    if ($action === 'organize') {
        $csv = exportExpensesToCSV($udb, $allStores);
        $_SESSION['organized_data'] = $csv;
        exit(json_encode(['status'=>'success','message'=>'Data organized successfully']));
    }
    elseif ($action === 'save' && isset($_POST['backup_name'])) {
        $name       = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['backup_name']);
        $backupPath = "$userBackupDir/$name.csv";
        if (isset($_SESSION['organized_data'])) {
            file_put_contents($backupPath, $_SESSION['organized_data']);
            unset($_SESSION['organized_data']);
            exit(json_encode(['status'=>'success','message'=>'Backup saved successfully']));
        }
        exit(json_encode(['status'=>'error','message'=>'No organized data to save']));
    }
    elseif ($action === 'restore' && isset($_POST['backup_file'])) {
        $backupFile = basename($_POST['backup_file']);
        $backupPath = "$userBackupDir/$backupFile";
        if (!file_exists($backupPath)) exit(json_encode(['status'=>'error','message'=>'Backup file not found']));

        $fh = fopen($backupPath, 'r');
        $headers = fgetcsv($fh);
        if (!$headers) { fclose($fh); exit(json_encode(['status'=>'error','message'=>'Invalid CSV'])); }
        $csvStores = array_slice($headers, 1);

        foreach ($csvStores as $sn) {
            $chk = $udb->prepare('SELECT id FROM stores WHERE name=:n');
            $chk->bindValue(':n', $sn);
            if (!$chk->execute()->fetchArray(SQLITE3_ASSOC)) {
                $maxOrd = (int)$udb->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM stores')->fetchArray(SQLITE3_NUM)[0];
                $ist = $udb->prepare('INSERT OR IGNORE INTO stores (id,name,visible,sort_order) VALUES (:id,:n,1,:o)');
                $ist->bindValue(':id', (string)time().rand(100,999));
                $ist->bindValue(':n', $sn);
                $ist->bindValue(':o', $maxOrd, SQLITE3_INTEGER);
                $ist->execute();
            }
        }

        $udb->exec('BEGIN');
        while ($row = fgetcsv($fh)) {
            if (empty($row[0])) continue;
            $date = $row[0];
            if (preg_match('/^(\d{2})-(\d{2})-(\d{2})$/', $date, $m)) $date = '20'.$m[3].'-'.$m[2].'-'.$m[1];
            elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) $date = $m[3].'-'.$m[2].'-'.$m[1];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) continue;
            for ($i = 1; $i < count($row); $i++) {
                $sn  = $csvStores[$i-1] ?? '';
                $amt = is_numeric($row[$i]) ? (float)$row[$i] : 0;
                if (!$sn) continue;
                $st = $udb->prepare('INSERT OR REPLACE INTO expenses (date,store,amount) VALUES (:d,:s,:a)');
                $st->bindValue(':d', $date); $st->bindValue(':s', $sn); $st->bindValue(':a', $amt, SQLITE3_FLOAT);
                $st->execute();
            }
        }
        $udb->exec('COMMIT');
        fclose($fh);
        exit(json_encode(['status'=>'success','message'=>'Backup restored successfully']));
    }
    elseif ($action === 'delete' && isset($_POST['backup_file'])) {
        $backupPath = "$userBackupDir/" . basename($_POST['backup_file']);
        if (file_exists($backupPath)) { unlink($backupPath); exit(json_encode(['status'=>'success','message'=>'Backup deleted successfully'])); }
        exit(json_encode(['status'=>'error','message'=>'Backup file not found']));
    }
    elseif ($action === 'download' && isset($_POST['backup_file'])) {
        $backupPath = "$userBackupDir/" . basename($_POST['backup_file']);
        if (file_exists($backupPath)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
            header('Content-Length: ' . filesize($backupPath));
            readfile($backupPath); exit;
        }
        exit(json_encode(['status'=>'error','message'=>'Backup file not found']));
    }
    elseif ($action === 'upload' && isset($_FILES['backup_file'])) {
        $uploaded = $_FILES['backup_file'];
        if ($uploaded['error'] !== UPLOAD_ERR_OK) exit(json_encode(['status'=>'error','message'=>'File upload error']));
        $fn   = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($uploaded['name']));
        $ftype= mime_content_type($uploaded['tmp_name']);
        if ($ftype === 'text/csv' || $ftype === 'text/plain' || str_contains($fn, '.csv')) {
            if (move_uploaded_file($uploaded['tmp_name'], "$userBackupDir/$fn"))
                exit(json_encode(['status'=>'success','message'=>'Backup uploaded successfully']));
            exit(json_encode(['status'=>'error','message'=>'Failed to move uploaded file']));
        }
        exit(json_encode(['status'=>'error','message'=>'Only CSV files are allowed']));
    }
    exit(json_encode(['status'=>'error','message'=>'Invalid backup action']));
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exit(json_encode(['status'=>'error','message'=>'Invalid request']));
}

if (isset($_GET['download_backup'])) {
    $filename  = $_GET['download_backup'];
    $backupDir = getUserBackupDir($currentUser);
    $filePath  = $backupDir . '/' . basename($filename);
    if (file_exists($filePath)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath); exit;
    }
    http_response_code(404); echo 'File not found'; exit;
}

// ─── Load data for display ──────────────────────────────────────────────────────
$storeNames = array_map(fn($s) => $s['name'], $userSettings['stores']);
$visibleStoreNames = array_map(fn($s) => $s['name'], $visibleStores);

$today = date('Y-m-d');

// Auto-create missing dates from last entry to today
$lastDateRes = $udb->query("SELECT MAX(date) as last_date FROM expenses");
$lastDateRow = $lastDateRes->fetchArray(SQLITE3_ASSOC);
$lastDate = $lastDateRow ? $lastDateRow['last_date'] : null;

if ($lastDate && $lastDate < $today) {
    // Create all missing dates from lastDate+1 to today
    $currentDate = date('Y-m-d', strtotime($lastDate . ' +1 day'));
    $endDate = $today;
    
    $udb->exec('BEGIN');
    while ($currentDate <= $endDate) {
        // Skip if date already exists
        $checkStmt = $udb->prepare('SELECT COUNT(*) as cnt FROM expenses WHERE date = :d');
        $checkStmt->bindValue(':d', $currentDate);
        $exists = (int)$checkStmt->execute()->fetchArray(SQLITE3_NUM)[0] > 0;
        
        if (!$exists) {
            foreach ($storeNames as $sn) {
                $st = $udb->prepare('INSERT OR IGNORE INTO expenses (date,store,amount) VALUES (:d,:s,0)');
                $st->bindValue(':d', $currentDate);
                $st->bindValue(':s', $sn);
                $st->execute();
            }
            $stInc = $udb->prepare('INSERT OR IGNORE INTO incomes (date,amount) VALUES (:d,0)');
            $stInc->bindValue(':d', $currentDate);
            $stInc->execute();
        }
        
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    }
    $udb->exec('COMMIT');
}

// Ensure today exists
foreach ($storeNames as $sn) {
    $st = $udb->prepare('INSERT OR IGNORE INTO expenses (date,store,amount) VALUES (:d,:s,0)');
    $st->bindValue(':d', $today); $st->bindValue(':s', $sn);
    $st->execute();
}
$udb->exec("INSERT OR IGNORE INTO incomes(date,amount) SELECT DISTINCT date,0 FROM expenses");
$stIncToday = $udb->prepare('INSERT OR IGNORE INTO incomes (date,amount) VALUES (:d,0)');
$stIncToday->bindValue(':d', $today);
$stIncToday->execute();

$startDateFilter = $tableSettings['start_date'] ? "AND date >= '" . SQLite3::escapeString($tableSettings['start_date']) . "'" : '';
$startDateForTable = trim($tableSettings['start_date'] ?? '');

$rowsPerPage = $tableSettings['rows_per_page'];
$sortDir = $tableSettings['sort_by'] === 'date_desc' ? 'DESC' : 'ASC';
$mmTable = $udb->query("SELECT MIN(date) AS lo, MAX(date) AS hi FROM expenses WHERE 1=1 $startDateFilter")->fetchArray(SQLITE3_ASSOC);
$firstPageRows = [];
$totalRows = 0;
if (!empty($mmTable['lo'])) {
    $rangeStart = $startDateForTable !== '' ? $startDateForTable : $mmTable['lo'];
    $rangeEnd = $mmTable['hi'];
    if (strcmp($rangeStart, $rangeEnd) <= 0) {
        $allTableDays = listCalendarDaysInclusive($rangeStart, $rangeEnd);
        if ($tableSettings['sort_by'] === 'date_desc') {
            $allTableDays = array_reverse($allTableDays);
        }
        $totalRows = count($allTableDays);
        $pageDaysInit = array_slice($allTableDays, 0, $rowsPerPage);
        foreach ($pageDaysInit as $d) {
            $rowData = [];
            foreach ($visibleStoreNames as $sn) {
                $st = $udb->prepare('SELECT amount FROM expenses WHERE date=:d AND store=:s');
                $st->bindValue(':d', $d);
                $st->bindValue(':s', $sn);
                $amt = $st->execute()->fetchArray(SQLITE3_NUM);
                $rowData[$sn] = $amt ? (float)$amt[0] : 0;
            }
            $firstPageRows[$d] = $rowData;
        }
    }
}

$currentYear = (int)date('Y');
$allDates = listCalendarDaysInclusive($currentYear . '-01-01', $currentYear . '-12-31');

$allStoresExpenses = [];
$allIncomes = [];
if (!empty($storeNames)) {
    $inListAll = implode(',', array_map(fn($s) => "'" . SQLite3::escapeString($s) . "'", $storeNames));
    $aRes = $udb->query("SELECT date, store, amount FROM expenses WHERE store IN ($inListAll) AND date LIKE '$currentYear-%' ORDER BY date ASC");
    while ($row = $aRes->fetchArray(SQLITE3_ASSOC)) {
        $allStoresExpenses[$row['date']][$row['store']] = (float)$row['amount'];
    }
}
$incResAll = $udb->query("SELECT date, amount FROM incomes WHERE date LIKE '$currentYear-%' ORDER BY date ASC");
while ($row = $incResAll->fetchArray(SQLITE3_ASSOC)) $allIncomes[$row['date']] = (float)$row['amount'];

