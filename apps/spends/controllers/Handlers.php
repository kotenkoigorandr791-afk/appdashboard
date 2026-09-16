<?php
// ─── AJAX / POST handlers ──────────────────────────────────────────────────────
$__t = static function (string $key): string {
    global $translations;
    return $translations[$key] ?? $key;
};
if (isset($_GET['get_settings']) || isset($_POST['get_settings'])) {
    exit(json_encode([
        'status' => 'success',
        'monthly_budget' => $userSettings['monthly_budget'] ?? 100,
        'currency' => $userSettings['currency'] ?? 'USD',
        'show_incomes' => (bool)($userSettings['show_incomes'] ?? true),
        'show_trend' => (bool)($userSettings['show_trend'] ?? false),
        'trim_empty_months' => (bool)($userSettings['trim_empty_months'] ?? false),
        'stores' => $userSettings['stores'] ?? [],
    ]));
}
if (isset($_GET['get_expense_meta'])) {
    $date = trim($_GET['date'] ?? '');
    $store = trim($_GET['store'] ?? '');
    if ($date === '' || $store === '') exit(json_encode(['status'=>'error','message'=>$__t('msg_invalid_data')]));
    $st = $udb->prepare("SELECT entry_source, ocr_raw_store FROM expenses WHERE date=:d AND store=:s LIMIT 1");
    $st->bindValue(':d', $date);
    $st->bindValue(':s', $store);
    $row = $st->execute()->fetch(PDO::FETCH_ASSOC) ?: [];
    $source = strtolower((string)($row['entry_source'] ?? 'manual'));
    $ocrRaw = (string)($row['ocr_raw_store'] ?? '');
    exit(json_encode(['status'=>'success','meta'=>['entry_source'=>$source,'ocr_raw_store'=>$ocrRaw]]));
}
if (isset($_GET['get_ocr_stats'])) {
    $storeFilter = trim($_GET['store'] ?? '__all__');
    $year = (int)($_GET['year'] ?? 0);
    $yearWhere = $year > 0 ? " AND date LIKE '" . AppDatabase::escapeString((string)$year) . "-%'" : '';
    $stores = [];
    $sr = $udb->query("SELECT DISTINCT store FROM expenses ORDER BY store ASC");
    while ($row = $sr->fetch(PDO::FETCH_ASSOC)) if (!empty($row['store'])) $stores[] = $row['store'];
    $daily = [];
    $monthly = [];
    if ($storeFilter === '__all__') {
        $dr = $udb->query("SELECT date,store,ROUND(SUM(amount)::numeric,2) AS amount FROM expenses WHERE amount>0 $yearWhere GROUP BY date,store ORDER BY date ASC,store ASC");
        while ($row = $dr->fetch(PDO::FETCH_ASSOC)) $daily[] = $row;
        $mr = $udb->query("SELECT substr(date,1,7) AS ym,store,ROUND(SUM(amount)::numeric,2) AS amount_sum,COUNT(DISTINCT date) AS intensity FROM expenses WHERE amount>0 $yearWhere GROUP BY ym,store ORDER BY ym ASC,store ASC");
        while ($row = $mr->fetch(PDO::FETCH_ASSOC)) $monthly[] = $row;
    } else {
        $st = $udb->prepare("SELECT date,store,ROUND(SUM(amount)::numeric,2) AS amount FROM expenses WHERE store=:s AND amount>0 $yearWhere GROUP BY date,store ORDER BY date ASC");
        $st->bindValue(':s', $storeFilter);
        $dr = $st->execute();
        while ($row = $dr->fetch(PDO::FETCH_ASSOC)) $daily[] = $row;
        $st2 = $udb->prepare("SELECT substr(date,1,7) AS ym,store,ROUND(SUM(amount)::numeric,2) AS amount_sum,COUNT(DISTINCT date) AS intensity FROM expenses WHERE store=:s AND amount>0 $yearWhere GROUP BY ym,store ORDER BY ym ASC");
        $st2->bindValue(':s', $storeFilter);
        $mr = $st2->execute();
        while ($row = $mr->fetch(PDO::FETCH_ASSOC)) $monthly[] = $row;
    }
    exit(json_encode(['status'=>'success','stores'=>$stores,'daily'=>$daily,'monthly'=>$monthly]));
}
if (isset($_GET['get_available_years'])) {
    $years = [];
    $res = $udb->query("SELECT DISTINCT y FROM (SELECT substr(date,1,4) AS y FROM expenses WHERE amount>0 UNION SELECT substr(date,1,4) AS y FROM incomes WHERE amount>0) ORDER BY y ASC");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
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

// ─── Expenses page data (AJAX for table) ──────────────────────────────────────
if (isset($_GET['get_expenses_page'])) {
    $search       = trim($_GET['search'] ?? '');
    $monthsWindow = max(0, (int)($_GET['months_window'] ?? 1));
    $sort         = $_GET['sort'] ?? 'date_asc';
    $startDate    = trim($_GET['start_date'] ?? '');
    $endDate      = trim($_GET['end_date'] ?? '');
    $sortDir      = $sort === 'date_desc' ? 'DESC' : 'ASC';
    if ($startDate && $endDate) {
        $rangeStart = $startDate;
        $rangeEnd   = $endDate;
    } else {
        $today = date('Y-m-d');
        $todayObj = new DateTime($today);
        $startObj = (clone $todayObj)->modify("-{$monthsWindow} months")->modify('first day of this month');
        $endObj   = (clone $todayObj)->modify("+{$monthsWindow} months")->modify('last day of this month');
        $rangeStart = $startObj->format('Y-m-d');
        $rangeEnd   = $endObj->format('Y-m-d');
        if ($startDate && strcmp($startDate, $rangeStart) > 0) {
            $rangeStart = $startDate;
        }
    }
    if (strcmp($rangeStart, $rangeEnd) > 0) {
        exit(json_encode(['status' => 'success', 'rows' => [], 'dates' => [], 'total' => 0, 'totalPages' => 1]));
    }
    $allDays = listCalendarDaysInclusive($rangeStart, $rangeEnd);
    if ($sort === 'date_desc') {
        $allDays = array_reverse($allDays);
    }
    $rows = [];
    $incomes = [];
    if (!empty($allDays)) {
        $inDates = implode(',', array_map(fn($d) => "'" . AppDatabase::escapeString($d) . "'", $allDays));
        $expWhere = "date IN ($inDates)";
        $expParams = [];
        if ($search !== '') {
            $expWhere .= " AND (date LIKE :s OR store LIKE :s2 OR CAST(amount AS TEXT) LIKE :s3)";
            $expParams[':s']  = "%$search%";
            $expParams[':s2'] = "%$search%";
            $expParams[':s3'] = "%$search%";
        }
        $expSt = $udb->prepare("SELECT date, store, amount FROM expenses WHERE $expWhere ORDER BY date $sortDir, store");
        foreach ($expParams as $k => $v) $expSt->bindValue($k, $v);
        $expRes = $expSt->execute();
        while ($r = $expRes->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;

        $incWhere = "date IN ($inDates)";
        $incParams = [];
        if ($search !== '') {
            $incWhere .= " AND (date LIKE :s OR CAST(amount AS TEXT) LIKE :s2)";
            $incParams[':s']  = "%$search%";
            $incParams[':s2'] = "%$search%";
        }
        $incSt = $udb->prepare("SELECT date, amount FROM incomes WHERE $incWhere ORDER BY date $sortDir");
        foreach ($incParams as $k => $v) $incSt->bindValue($k, $v);
        $incRes = $incSt->execute();
        while ($r = $incRes->fetch(PDO::FETCH_ASSOC)) $incomes[] = $r;
        if ($search !== '') {
            $found = [];
            foreach ($rows as $r) $found[$r['date']] = true;
            foreach ($incomes as $r) $found[$r['date']] = true;
            $allDays = array_values(array_filter($allDays, fn($d) => isset($found[$d])));
        }
    }
    exit(json_encode([
        'status'     => 'success',
        'rows'       => $rows,
        'incomes'    => $incomes,
        'dates'      => $allDays,
        'total'      => count($allDays),
        'totalPages' => 1,
    ]));
}

// ─── Year data for chart (AJAX) ────────────────────────────────────────────────
if (isset($_GET['year_data'])) {
    $year = (int)($_GET['year_data']);
    if ($year < 2000 || $year > 2100) exit(json_encode(['status'=>'error','message'=>$__t('msg_invalid_year')]));

    $yearStoreNames = array_map(fn($s) => $s['name'], $userSettings['stores']);
    $yearDates = listCalendarDaysInclusive(sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year));

    $yearExpenses = [];
    $yearIncomes = [];
    if (!empty($yearStoreNames)) {
        $inList = implode(',', array_map(fn($s)=>"'".AppDatabase::escapeString($s)."'", $yearStoreNames));
        $res = $udb->query("SELECT date, store, amount FROM expenses WHERE store IN ($inList) AND date LIKE '$year-%' ORDER BY date ASC");
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $yearExpenses[$row['date']][$row['store']] = (float)$row['amount'];
        }
    }
    $resInc = $udb->query("SELECT date, amount FROM incomes WHERE date LIKE '$year-%' ORDER BY date ASC");
    while ($row = $resInc->fetch(PDO::FETCH_ASSOC)) $yearIncomes[$row['date']] = (float)$row['amount'];

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
        $exists = (int)$st->execute()->fetch(PDO::FETCH_NUM)[0] > 0;
        if ($exists) exit(json_encode(['status'=>'error','message'=>$__t('msg_date_exists')]));

        $stores = dbGetStores($udb);
        $udb->exec('BEGIN');
        foreach ($stores as $s) {
            $ist = $udb->prepare('INSERT INTO expenses (date,store,amount) VALUES (:d,:s,0) ON CONFLICT (date,store) DO NOTHING');
            $ist->bindValue(':d', $date);
            $ist->bindValue(':s', $s['name']);
            $ist->execute();
        }
        $inc = $udb->prepare('INSERT INTO incomes (date,amount) VALUES (:d,0) ON CONFLICT (date) DO NOTHING');
        $inc->bindValue(':d', $date);
        $inc->execute();
        $udb->exec('COMMIT');
        exit(json_encode(['status'=>'success','message'=>$__t('date_added'),'date'=>$date]));
    }
    exit(json_encode(['status'=>'error','message'=>$__t('invalid_date_format')]));
}

if (isset($_POST['add_edit_store'])) {
    $storeId   = $_POST['store_id']   ?? (string)time();
    $storeName = trim($_POST['store_name'] ?? '');
    if (empty($storeName)) exit(json_encode(['status'=>'error','message'=>$__t('store_name_required')]));

    $existing = $udb->prepare('SELECT id, name FROM stores WHERE id=:id');
    $existing->bindValue(':id', $storeId);
    $row = $existing->execute()->fetch(PDO::FETCH_ASSOC);

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
        $maxOrd = (int)$udb->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM stores')->fetch(PDO::FETCH_NUM)[0];
        $st = $udb->prepare('INSERT INTO stores (id,name,visible,sort_order) VALUES (:id,:n,1,:o) ON CONFLICT (id) DO UPDATE SET name=excluded.name, visible=excluded.visible, sort_order=excluded.sort_order');
        $st->bindValue(':id', $storeId);
        $st->bindValue(':n',  $storeName);
        $st->bindValue(':o',  $maxOrd, PDO::PARAM_INT);
        $st->execute();
        $dRes = $udb->query("SELECT DISTINCT date FROM expenses");
        $udb->exec('BEGIN');
        while ($dRow = $dRes->fetch(PDO::FETCH_ASSOC)) {
            $ist = $udb->prepare('INSERT INTO expenses (date,store,amount) VALUES (:d,:s,0) ON CONFLICT (date,store) DO NOTHING');
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
            if (!file_exists($uDir)) mkdir($uDir, SPENDS_RUNTIME_DIR_MODE, true);
            @chmod($uDir, SPENDS_RUNTIME_DIR_MODE);
            imagejpeg($newImg, "$uDir/store_$fn.jpg", 90);
            imagedestroy($image); imagedestroy($newImg);
        }
    }
    $sr = $udb->prepare('SELECT id, name, visible FROM stores WHERE id = :id');
    $sr->bindValue(':id', $storeId);
    $stRow = $sr->execute()->fetch(PDO::FETCH_ASSOC);
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
    exit(json_encode(['status'=>'success','message'=>$__t('delete_store_ok')]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings'])) {
    $budget   = filter_var($_POST['monthly_budget'] ?? 100, FILTER_VALIDATE_FLOAT) ?: 100;
    $currency = $_POST['currency'] ?? 'USD';
    dbSetSetting($udb, 'monthly_budget', $budget);
    dbSetSetting($udb, 'currency', $currency);
    dbSetSetting($udb, 'show_incomes', isset($_POST['show_incomes']) ? '1' : '0');
    dbSetSetting($udb, 'show_trend', isset($_POST['show_trend']) ? '1' : '0');
    dbSetSetting($udb, 'trim_empty_months', isset($_POST['trim_empty_months']) ? '1' : '0');
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
        $st->bindValue(':a', $amount, PDO::PARAM_STR);
        $st->execute();
        exit(json_encode(['status'=>'success']));
    }
    exit(json_encode(['status'=>'error','message'=>$__t('msg_invalid_data')]));
}
if (isset($_POST['update_income'])) {
    $date = $_POST['date'] ?? '';
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($date && $amount !== false) {
        $st = $udb->prepare("INSERT INTO incomes(date,amount) VALUES(:d,:a) ON CONFLICT(date) DO UPDATE SET amount=excluded.amount");
        $st->bindValue(':d', $date);
        $st->bindValue(':a', $amount, PDO::PARAM_STR);
        $st->execute();
        exit(json_encode(['status'=>'success']));
    }
    exit(json_encode(['status'=>'error','message'=>$__t('msg_invalid_data')]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_csv_analyze'])) {
    header('Content-Type: application/json; charset=UTF-8');
    if (empty($_FILES['bank_csv_file']) || (int) ($_FILES['bank_csv_file']['error'] ?? 0) !== UPLOAD_ERR_OK) {
        exit(json_encode(['status' => 'error', 'message' => $__t('upload_error')]));
    }
    require_once __DIR__ . '/../utils/BankStatementParser.php';
    $sz = (int) ($_FILES['bank_csv_file']['size'] ?? 0);
    if ($sz <= 0 || $sz > BankStatementParser::MAX_BYTES) {
        exit(json_encode(['status' => 'error', 'message' => $__t('msg_file_too_large')]));
    }
    $tmpDir = $userDir . '/bank_import';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, SPENDS_RUNTIME_DIR_MODE, true);
    }
    @chmod($tmpDir, SPENDS_RUNTIME_DIR_MODE);
    $token = bin2hex(random_bytes(16));
    $origName = $_FILES['bank_csv_file']['name'] ?? 'statement';
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xml', 'ofx', 'qfx', 'mt940', 'sta', 'pdf'], true)) {
        $ext = 'csv';
    }
    $dest = $tmpDir . '/' . $token . '.' . $ext;
    if (!move_uploaded_file($_FILES['bank_csv_file']['tmp_name'], $dest)) {
        exit(json_encode(['status' => 'error', 'message' => $__t('msg_move_failed')]));
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
        $parsed = BankStatementParser::parse($dest);
        $res = BankStatementParser::toLegacyFormat($parsed, $storeNames, $base);
        $_SESSION['bank_csv_sessions'][$token] = ['path' => $dest, 'user' => $currentUser, 'ts' => time()];
        $res['token'] = $token;
        // Pre-computed DB duplicates for the analysis view.
        $res['duplicate_count'] = bankImportMarkDuplicates($udb, $res['transactions']);
        exit(json_encode($res));
    } catch (Throwable $e) {
        @unlink($dest);
        exit(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
    }
}

/**
 * Flag transactions already present in the DB (same date, store and
 * amount). Expenses only; income rows are never marked as duplicates.
 */
function bankImportMarkDuplicates(AppDatabase $udb, array &$tx): int
{
    $dup = 0;
    foreach ($tx as &$t) {
        $t['duplicate'] = false;
        if (!empty($t['income'])) {
            continue;
        }
        $chk = $udb->prepare('SELECT 1 FROM expenses WHERE date=:d AND store=:s AND ABS(ROUND(amount::numeric,2)-:a)<0.02 LIMIT 1');
        $chk->bindValue(':d', $t['date']);
        $chk->bindValue(':s', $t['store_default']);
        $chk->bindValue(':a', $t['amount'], PDO::PARAM_STR);
        if ($chk->execute()->fetch()) {
            $t['duplicate'] = true;
            $dup++;
        }
    }
    unset($t);
    return $dup;
}

/**
 * Persist the parsed transactions: expenses are aggregated per
 * date+store, income is summed per date. Conflicting dates (days that
 * already have expenses) are skipped or replaced per user choice.
 */
function bankImportApply(AppDatabase $udb, array $tx, array $excludeGroupKeys, array $storeMap, string $conflictMode, array $conflictDates): void
{
    $conflictSet = [];
    foreach ($conflictDates as $d) {
        $conflictSet[(string) $d] = true;
    }
    $exclude = [];
    foreach ($excludeGroupKeys as $k) {
        $exclude[(string) $k] = true;
    }

    $aggExp = [];
    $aggInc = [];
    foreach ($tx as $t) {
        if (!empty($t['duplicate']) || isset($exclude[$t['group_key']])) {
            continue;
        }
        $d = $t['date'];
        if (!empty($t['income'])) {
            $aggInc[$d] = ($aggInc[$d] ?? 0.0) + (float) $t['amount'];
            continue;
        }
        $store = trim((string) ($storeMap[$t['group_key']] ?? $t['store_default']));
        if ($store === '') {
            $store = (string) $t['merchant_raw'];
        }
        $aggExp[$d][$store] = ($aggExp[$d][$store] ?? 0.0) + (float) $t['amount'];
    }

    $replaceDays = [];
    if ($conflictMode === 'replace') {
        foreach ($conflictDates as $d) {
            $replaceDays[(string) $d] = true;
        }
    }

    $udb->exec('BEGIN');
    try {
        foreach (array_keys($replaceDays) as $d) {
            $del = $udb->prepare('DELETE FROM expenses WHERE date=:d');
            $del->bindValue(':d', $d);
            $del->execute();
        }
        foreach ($aggExp as $d => $byStore) {
            if ($conflictMode === 'skip' && isset($conflictSet[$d])) {
                continue;
            }
            foreach ($byStore as $store => $amt) {
                $amt = round((float) $amt, 2);
                if ($amt < 0.0001) {
                    continue;
                }
                bankImportEnsureStore($udb, $store);
                if (isset($replaceDays[$d])) {
                    $ins = $udb->prepare("INSERT INTO expenses (date,store,amount,entry_source,ocr_raw_store) VALUES (:d,:s,:a,'manual',NULL) ON CONFLICT(date,store) DO NOTHING");
                } else {
                    $ins = $udb->prepare("INSERT INTO expenses (date,store,amount,entry_source,ocr_raw_store) VALUES (:d,:s,:a,'manual',NULL) ON CONFLICT(date,store) DO UPDATE SET amount=ROUND((expenses.amount+excluded.amount)::numeric,2),entry_source='manual',ocr_raw_store=NULL");
                }
                $ins->bindValue(':d', $d);
                $ins->bindValue(':s', $store);
                $ins->bindValue(':a', $amt, PDO::PARAM_STR);
                $ins->execute();
            }
        }
        foreach ($aggInc as $d => $sum) {
            if ($conflictMode === 'skip' && isset($conflictSet[$d])) {
                continue;
            }
            $sum = round((float) $sum, 2);
            if ($sum < 0.0001) {
                continue;
            }
            if (isset($replaceDays[$d])) {
                $deli = $udb->prepare('DELETE FROM incomes WHERE date=:d');
                $deli->bindValue(':d', $d);
                $deli->execute();
                $ins = $udb->prepare('INSERT INTO incomes(date,amount) VALUES(:d,:a) ON CONFLICT(date) DO NOTHING');
            } else {
                $ins = $udb->prepare('INSERT INTO incomes(date,amount) VALUES(:d,:a) ON CONFLICT(date) DO UPDATE SET amount=ROUND((incomes.amount+excluded.amount)::numeric,2)');
            }
            $ins->bindValue(':d', $d);
            $ins->bindValue(':a', $sum, PDO::PARAM_STR);
            $ins->execute();
        }
        $udb->exec('COMMIT');
    } catch (Throwable $e) {
        $udb->exec('ROLLBACK');
        throw $e;
    }
}

function bankImportEnsureStore(AppDatabase $udb, string $name): void
{
    $name = trim($name);
    if ($name === '') {
        return;
    }
    $chk = $udb->prepare('SELECT id FROM stores WHERE name=:n LIMIT 1');
    $chk->bindValue(':n', $name);
    if ($chk->execute()->fetch()) {
        return;
    }
    $maxOrd = (int) $udb->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM stores')->fetch(PDO::FETCH_NUM)[0];
    $id = 's_' . bin2hex(random_bytes(8));
    $st = $udb->prepare('INSERT INTO stores (id,name,visible,sort_order) VALUES (:id,:n,1,:o) ON CONFLICT (id) DO NOTHING');
    $st->bindValue(':id', $id);
    $st->bindValue(':n', $name);
    $st->bindValue(':o', $maxOrd, PDO::PARAM_INT);
    $st->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_csv_apply'])) {
    header('Content-Type: application/json; charset=UTF-8');
    require_once __DIR__ . '/../utils/BankStatementParser.php';
    $payload = json_decode((string) ($_POST['bank_csv_apply'] ?? ''), true);
    if (!is_array($payload)) {
        exit(json_encode(['status' => 'error', 'message' => $__t('msg_bad_json')]));
    }
    $token = preg_replace('/[^a-f0-9]/', '', (string) ($payload['token'] ?? ''));
    $exclude = $payload['exclude_group_keys'] ?? [];
    $storeMap = $payload['store_map'] ?? [];
    $conflictMode = ($payload['conflict_mode'] ?? 'skip') === 'replace' ? 'replace' : 'skip';
    $conflictDates = $payload['conflict_dates'] ?? [];
    if ($token === '' || strlen($token) < 16) {
        exit(json_encode(['status' => 'error', 'message' => $__t('msg_bad_token')]));
    }
    $path = null;
    if (isset($_SESSION['bank_csv_sessions'][$token])) {
        $meta = $_SESSION['bank_csv_sessions'][$token];
        if (($meta['user'] ?? '') === $currentUser && !empty($meta['path']) && is_file($meta['path'])) {
            $path = (string) $meta['path'];
        }
    }
    if ($path === null) {
        exit(json_encode(['status' => 'error', 'message' => $__t('msg_session')]));
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
    try {
        $storeNames = array_map(static fn ($s) => (string) ($s['name'] ?? ''), $userSettings['stores'] ?? []);
        $base = (string) ($userSettings['currency'] ?? 'USD');
        $parsed = BankStatementParser::parse($path);
        $legacy = BankStatementParser::toLegacyFormat($parsed, $storeNames, $base);
        $tx = $legacy['transactions'];
        bankImportMarkDuplicates($udb, $tx);
        bankImportApply($udb, $tx, $exclude, $storeMap, $conflictMode, $conflictDates);
        @unlink($path);
        unset($_SESSION['bank_csv_sessions'][$token]);
        exit(json_encode(['status' => 'success']));
    } catch (Throwable $e) {
        exit(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
    }
}

if (isset($_POST['save_table_settings'])) {
    $sortBy      = $_POST['sort_by']       ?? 'date_asc';
    $monthsWindow= max(0, (int)($_POST['months_window'] ?? 1));
    $startDate   = $_POST['start_date']    ?? '';
    dbSetTableSetting($udb, 'sort_by',       $sortBy);
    dbSetTableSetting($udb, 'months_window', $monthsWindow);
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

    if ($action === 'list') {
        $backups = [];
        foreach (glob("$userBackupDir/*.csv") ?: [] as $path) {
            $backups[] = [
                'name' => basename($path),
                'size' => (int)filesize($path),
                'mtime' => (int)filemtime($path),
            ];
        }
        exit(json_encode(['status' => 'success', 'backups' => $backups]));
    }
    elseif ($action === 'organize') {
        $csv = exportExpensesToCSV($udb, $allStores);
        $_SESSION['organized_data'] = $csv;
        exit(json_encode(['status'=>'success','message'=>$__t('msg_organized_ok')]));
    }
    elseif ($action === 'save' && isset($_POST['backup_name'])) {
        $name       = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['backup_name']);
        $backupPath = "$userBackupDir/$name.csv";
        $csv = $_SESSION['organized_data'] ?? null;
        if ($csv === null || $csv === '') {
            $csv = exportExpensesToCSV($udb, $allStores);
        } else {
            unset($_SESSION['organized_data']);
        }
        if ($csv === null || $csv === '') {
            exit(json_encode(['status'=>'error','message'=>$__t('msg_no_organized_data')]));
        }
        file_put_contents($backupPath, $csv);
        exit(json_encode(['status'=>'success','message'=>$__t('msg_backup_saved')]));
    }
    elseif ($action === 'restore' && isset($_POST['backup_file'])) {
        $backupFile = basename($_POST['backup_file']);
        $backupPath = "$userBackupDir/$backupFile";
        if (!file_exists($backupPath)) exit(json_encode(['status'=>'error','message'=>$__t('msg_backup_not_found')]));

        $fh = fopen($backupPath, 'r');
        $headers = fgetcsv($fh);
        if (!$headers) { fclose($fh); exit(json_encode(['status'=>'error','message'=>$__t('msg_invalid_csv')])); }
        $csvStores = array_slice($headers, 1);

        foreach ($csvStores as $sn) {
            $chk = $udb->prepare('SELECT id FROM stores WHERE name=:n');
            $chk->bindValue(':n', $sn);
            if (!$chk->execute()->fetch(PDO::FETCH_ASSOC)) {
                $maxOrd = (int)$udb->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM stores')->fetch(PDO::FETCH_NUM)[0];
                $ist = $udb->prepare('INSERT INTO stores (id,name,visible,sort_order) VALUES (:id,:n,1,:o) ON CONFLICT (id) DO UPDATE SET name=excluded.name, visible=excluded.visible, sort_order=excluded.sort_order');
                $ist->bindValue(':id', (string)time().rand(100,999));
                $ist->bindValue(':n', $sn);
                $ist->bindValue(':o', $maxOrd, PDO::PARAM_INT);
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
                $st = $udb->prepare('INSERT INTO expenses (date,store,amount) VALUES (:d,:s,:a) ON CONFLICT (date,store) DO UPDATE SET amount=excluded.amount');
                $st->bindValue(':d', $date); $st->bindValue(':s', $sn); $st->bindValue(':a', $amt, PDO::PARAM_STR);
                $st->execute();
            }
        }
        $udb->exec('COMMIT');
        fclose($fh);
        exit(json_encode(['status'=>'success','message'=>$__t('backup_restored')]));
    }
    elseif ($action === 'delete' && isset($_POST['backup_file'])) {
        $backupPath = "$userBackupDir/" . basename($_POST['backup_file']);
        if (file_exists($backupPath)) { unlink($backupPath); exit(json_encode(['status'=>'success','message'=>$__t('backup_deleted')])); }
        exit(json_encode(['status'=>'error','message'=>$__t('msg_backup_not_found')]));
    }
    elseif ($action === 'download' && isset($_POST['backup_file'])) {
        $backupPath = "$userBackupDir/" . basename($_POST['backup_file']);
        if (file_exists($backupPath)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
            header('Content-Length: ' . filesize($backupPath));
            readfile($backupPath); exit;
        }
        exit(json_encode(['status'=>'error','message'=>$__t('msg_backup_not_found')]));
    }
    elseif ($action === 'upload' && isset($_FILES['backup_file'])) {
        $uploaded = $_FILES['backup_file'];
        if ($uploaded['error'] !== UPLOAD_ERR_OK) exit(json_encode(['status'=>'error','message'=>$__t('upload_error')]));
        $fn   = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($uploaded['name']));
        $ftype= mime_content_type($uploaded['tmp_name']);
        if ($ftype === 'text/csv' || $ftype === 'text/plain' || str_contains($fn, '.csv')) {
            if (move_uploaded_file($uploaded['tmp_name'], "$userBackupDir/$fn"))
                exit(json_encode(['status'=>'success','message'=>$__t('backup_uploaded')]));
            exit(json_encode(['status'=>'error','message'=>$__t('msg_move_failed')]));
        }
        exit(json_encode(['status'=>'error','message'=>$__t('msg_csv_only')]));
    }
    exit(json_encode(['status'=>'error','message'=>$__t('msg_invalid_backup_action')]));
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exit(json_encode(['status'=>'error','message'=>$__t('msg_invalid_request')]));
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
$lastYearStart = date('Y-m-d', strtotime('-1 year'));

$daysToEnsure = listCalendarDaysInclusive($lastYearStart, $today);
if (!empty($daysToEnsure)) {
    $udb->exec('BEGIN');
    $expenseInsert = $udb->prepare('INSERT INTO expenses (date,store,amount) VALUES (:d,:s,0) ON CONFLICT (date,store) DO NOTHING');
    $incomeInsert = $udb->prepare('INSERT INTO incomes (date,amount) VALUES (:d,0) ON CONFLICT (date) DO NOTHING');
    foreach ($daysToEnsure as $currentDate) {
        foreach ($storeNames as $sn) {
            $expenseInsert->bindValue(':d', $currentDate);
            $expenseInsert->bindValue(':s', $sn);
            $expenseInsert->execute();
        }
        $incomeInsert->bindValue(':d', $currentDate);
        $incomeInsert->execute();
    }
    $udb->exec('COMMIT');
}
$udb->exec("INSERT INTO incomes(date,amount) SELECT DISTINCT date,0 FROM expenses ON CONFLICT (date) DO NOTHING");

$startDateForTable = trim($tableSettings['start_date'] ?? '');

$monthsWindow = $tableSettings['months_window'];
$sortDir = $tableSettings['sort_by'] === 'date_desc' ? 'DESC' : 'ASC';
$nowObj = new DateTime(date('Y-m-d'));
$rangeStartObj = (clone $nowObj)->modify("-{$monthsWindow} months")->modify('first day of this month');
$rangeEndObj   = (clone $nowObj)->modify("+{$monthsWindow} months")->modify('last day of this month');
$rangeStart = $rangeStartObj->format('Y-m-d');
$rangeEnd   = $rangeEndObj->format('Y-m-d');
if ($startDateForTable !== '' && strcmp($startDateForTable, $rangeStart) > 0) {
    $rangeStart = $startDateForTable;
}
$firstPageRows = [];
$totalRows = 0;
if (strcmp($rangeStart, $rangeEnd) <= 0) {
    $allTableDays = listCalendarDaysInclusive($rangeStart, $rangeEnd);
    if ($tableSettings['sort_by'] === 'date_desc') {
        $allTableDays = array_reverse($allTableDays);
    }
    $totalRows = count($allTableDays);
    foreach ($allTableDays as $d) {
        $rowData = [];
        foreach ($visibleStoreNames as $sn) {
            $st = $udb->prepare('SELECT amount FROM expenses WHERE date=:d AND store=:s');
            $st->bindValue(':d', $d);
            $st->bindValue(':s', $sn);
            $amt = $st->execute()->fetch(PDO::FETCH_NUM);
            $rowData[$sn] = $amt ? (float)$amt[0] : 0;
        }
        $firstPageRows[$d] = $rowData;
    }
}

$currentYear = (int)date('Y');
$allDates = listCalendarDaysInclusive($currentYear . '-01-01', $currentYear . '-12-31');

$allStoresExpenses = [];
$allIncomes = [];
if (!empty($storeNames)) {
    $inListAll = implode(',', array_map(fn($s) => "'" . AppDatabase::escapeString($s) . "'", $storeNames));
    $aRes = $udb->query("SELECT date, store, amount FROM expenses WHERE store IN ($inListAll) AND date LIKE '$currentYear-%' ORDER BY date ASC");
    while ($row = $aRes->fetch(PDO::FETCH_ASSOC)) {
        $allStoresExpenses[$row['date']][$row['store']] = (float)$row['amount'];
    }
}
$incResAll = $udb->query("SELECT date, amount FROM incomes WHERE date LIKE '$currentYear-%' ORDER BY date ASC");
while ($row = $incResAll->fetch(PDO::FETCH_ASSOC)) $allIncomes[$row['date']] = (float)$row['amount'];
