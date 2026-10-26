<?php
/**
 * Mobile companion sync helpers for Spends.
 * Dedup reuses ocr_entries signatures so wallet / OCR / mobile pushes are not double-written.
 */
declare(strict_types=1);

function spends_mobile_signature(string $date, string $store, float $amount, string $raw = ''): string
{
    $normDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    $normStore = mb_strtolower(trim($store));
    $normAmount = number_format(round($amount, 2), 2, '.', '');
    $normRaw = mb_strtolower(trim($raw));
    return hash('sha256', $normDate . '|' . $normStore . '|' . $normAmount . '|' . $normRaw);
}

function spends_mobile_ensure_store(AppDatabase $udb, string $store, string $category = 'other'): array
{
    $store = trim($store);
    if ($store === '') {
        return ['ok' => false, 'message' => 'empty_store'];
    }
    $existing = $udb->prepare('SELECT id, name, category, visible FROM stores WHERE name = :n LIMIT 1');
    $existing->bindValue(':n', $store);
    $row = $existing->execute()->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return [
            'ok' => true,
            'store' => [
                'id' => (string)$row['id'],
                'name' => (string)$row['name'],
                'category' => spendsNormalizeStoreCategory($row['category'] ?? 'other'),
                'visible' => (int)($row['visible'] ?? 1) === 1,
            ],
        ];
    }
    $id = (string)(int)(microtime(true) * 1000) . bin2hex(random_bytes(2));
    $cat = spendsNormalizeStoreCategory($category);
    $ins = $udb->prepare('INSERT INTO stores (id, name, visible, sort_order, category) VALUES (:id, :n, 1, 0, :c) ON CONFLICT (user_id, name) DO NOTHING');
    $ins->bindValue(':id', $id);
    $ins->bindValue(':n', $store);
    $ins->bindValue(':c', $cat);
    $ins->execute();
    $existing->bindValue(':n', $store);
    $row = $existing->execute()->fetch(PDO::FETCH_ASSOC) ?: ['id' => $id, 'name' => $store, 'category' => $cat, 'visible' => 1];
    return [
        'ok' => true,
        'store' => [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'category' => spendsNormalizeStoreCategory($row['category'] ?? 'other'),
            'visible' => (int)($row['visible'] ?? 1) === 1,
        ],
    ];
}

function spends_mobile_ledger_has(AppDatabase $udb, string $signature): bool
{
    $st = $udb->prepare('SELECT 1 FROM ocr_entries WHERE signature = :s LIMIT 1');
    $st->bindValue(':s', $signature);
    return (bool)$st->execute()->fetchColumn();
}

function spends_mobile_ledger_put(
    AppDatabase $udb,
    string $signature,
    string $date,
    string $store,
    float $amount,
    string $raw,
    string $imageName = 'mobile'
): void {
    $st = $udb->prepare('INSERT INTO ocr_entries (signature, date, store, amount, ocr_raw_store, image_name, created_at)
        VALUES (:sig, :d, :s, :a, :r, :i, :c) ON CONFLICT (user_id, signature) DO NOTHING');
    $st->bindValue(':sig', $signature);
    $st->bindValue(':d', $date);
    $st->bindValue(':s', $store);
    $st->bindValue(':a', round($amount, 2), PDO::PARAM_STR);
    if ($raw === '') {
        $st->bindValue(':r', null, PDO::PARAM_NULL);
    } else {
        $st->bindValue(':r', $raw);
    }
    $st->bindValue(':i', $imageName);
    $st->bindValue(':c', date('c'));
    $st->execute();
}

function spends_mobile_pull(AppDatabase $udb, array $userSettings): array
{
    $years = [];
    $yr = $udb->query("SELECT DISTINCT substr(date,1,4) AS y FROM expenses WHERE date <> '' ORDER BY y DESC");
    while ($row = $yr->fetch(PDO::FETCH_ASSOC)) {
        $y = (int)($row['y'] ?? 0);
        if ($y >= 2000 && $y <= 2100) {
            $years[] = $y;
        }
    }
    if ($years === []) {
        $years[] = (int)date('Y');
    }

    $expenses = [];
    $er = $udb->query('SELECT date, store, amount, entry_source, ocr_raw_store FROM expenses WHERE amount > 0 ORDER BY date DESC, store ASC');
    while ($row = $er->fetch(PDO::FETCH_ASSOC)) {
        $date = (string)$row['date'];
        $store = (string)$row['store'];
        $amount = (float)$row['amount'];
        $raw = (string)($row['ocr_raw_store'] ?? '');
        $expenses[] = [
            'date' => $date,
            'store' => $store,
            'amount' => $amount,
            'entry_source' => (string)($row['entry_source'] ?? 'manual'),
            'ocr_raw_store' => $raw,
            'signature' => spends_mobile_signature($date, $store, $amount, $raw),
        ];
    }

    $incomes = [];
    $ir = $udb->query('SELECT date, amount FROM incomes WHERE amount > 0 ORDER BY date DESC');
    while ($row = $ir->fetch(PDO::FETCH_ASSOC)) {
        $date = (string)$row['date'];
        $amount = (float)$row['amount'];
        $incomes[] = [
            'date' => $date,
            'amount' => $amount,
            'signature' => spends_mobile_signature($date, '__income__', $amount),
        ];
    }

    $known = [];
    $lr = $udb->query('SELECT signature FROM ocr_entries');
    while ($row = $lr->fetch(PDO::FETCH_ASSOC)) {
        $known[] = (string)$row['signature'];
    }

    $email = spends_mobile_current_email();

    return [
        'status' => 'success',
        'server_time' => time(),
        'profile' => ['email' => $email, 'app' => 'spends'],
        'settings' => [
            'monthly_budget' => (float)($userSettings['monthly_budget'] ?? 100),
            'currency' => (string)($userSettings['currency'] ?? 'USD'),
            'show_incomes' => (bool)($userSettings['show_incomes'] ?? true),
            'show_trend' => (bool)($userSettings['show_trend'] ?? false),
            'trim_empty_months' => (bool)($userSettings['trim_empty_months'] ?? false),
        ],
        'stores' => $userSettings['stores'] ?? [],
        'store_categories' => $userSettings['store_categories'] ?? spendsStoreCategories(),
        'years' => $years,
        'expenses' => $expenses,
        'incomes' => $incomes,
        'subscriptions' => function_exists('spendsSubscriptionList') ? spendsSubscriptionList($udb) : [],
        'known_signatures' => $known,
    ];
}

function spends_mobile_push(AppDatabase $udb, array $payload): array
{
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    foreach (($payload['stores'] ?? []) as $storeRow) {
        if (!is_array($storeRow)) {
            continue;
        }
        $name = trim((string)($storeRow['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $res = spends_mobile_ensure_store($udb, $name, (string)($storeRow['category'] ?? 'other'));
        if (!$res['ok']) {
            $errors[] = ['store' => $name, 'error' => $res['message'] ?? 'store_failed'];
        }
    }

    foreach (($payload['expenses'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $date = trim((string)($row['date'] ?? ''));
        $store = trim((string)($row['store'] ?? ''));
        $amount = filter_var($row['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $raw = trim((string)($row['raw'] ?? $row['ocr_raw_store'] ?? ''));
        $source = trim((string)($row['source'] ?? 'mobile'));
        $mode = strtolower(trim((string)($row['mode'] ?? 'skip_if_known')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $store === '' || $amount === false) {
            $errors[] = ['expense' => $row, 'error' => 'invalid'];
            continue;
        }
        $amount = round((float)$amount, 2);
        $signature = trim((string)($row['signature'] ?? ''));
        if ($signature === '') {
            $signature = spends_mobile_signature($date, $store, $amount, $raw);
        }
        if ($mode !== 'force' && spends_mobile_ledger_has($udb, $signature)) {
            $skipped++;
            continue;
        }
        spends_mobile_ensure_store($udb, $store, (string)($row['category'] ?? 'other'));
        $exists = $udb->prepare('SELECT amount FROM expenses WHERE date = :d AND store = :s LIMIT 1');
        $exists->bindValue(':d', $date);
        $exists->bindValue(':s', $store);
        $prev = $exists->execute()->fetch(PDO::FETCH_ASSOC);
        if ($amount < 0.005) {
            $del = $udb->prepare('DELETE FROM expenses WHERE date = :d AND store = :s');
            $del->bindValue(':d', $date);
            $del->bindValue(':s', $store);
            $del->execute();
            $updated++;
        } else {
            $entrySource = match ($source) {
                'ocr', 'wallet' => 'ocr',
                'subscription' => 'subscription',
                default => 'manual',
            };
            $st = $udb->prepare("INSERT INTO expenses (date,store,amount,entry_source,ocr_raw_store) VALUES (:d,:s,:a,:e,:r)
                ON CONFLICT (user_id,date,store) DO UPDATE SET amount=excluded.amount, entry_source=excluded.entry_source, ocr_raw_store=COALESCE(NULLIF(excluded.ocr_raw_store,''), expenses.ocr_raw_store)");
            $st->bindValue(':d', $date);
            $st->bindValue(':s', $store);
            $st->bindValue(':a', $amount, PDO::PARAM_STR);
            $st->bindValue(':e', $entrySource);
            if ($raw === '') {
                $st->bindValue(':r', null, PDO::PARAM_NULL);
            } else {
                $st->bindValue(':r', $raw);
            }
            $st->execute();
            $prev ? $updated++ : $created++;
        }
        spends_mobile_ledger_put($udb, $signature, $date, $store, $amount, $raw !== '' ? $raw : $source, 'mobile:' . $source);
    }

    foreach (($payload['incomes'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $date = trim((string)($row['date'] ?? ''));
        $amount = filter_var($row['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $mode = strtolower(trim((string)($row['mode'] ?? 'skip_if_known')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $amount === false) {
            $errors[] = ['income' => $row, 'error' => 'invalid'];
            continue;
        }
        $amount = round((float)$amount, 2);
        $signature = trim((string)($row['signature'] ?? ''));
        if ($signature === '') {
            $signature = spends_mobile_signature($date, '__income__', $amount);
        }
        if ($mode !== 'force' && spends_mobile_ledger_has($udb, $signature)) {
            $skipped++;
            continue;
        }
        if ($amount < 0.005) {
            $del = $udb->prepare('DELETE FROM incomes WHERE date = :d');
            $del->bindValue(':d', $date);
            $del->execute();
        } else {
            $st = $udb->prepare('INSERT INTO incomes(date,amount) VALUES(:d,:a) ON CONFLICT (user_id,date) DO UPDATE SET amount=excluded.amount');
            $st->bindValue(':d', $date);
            $st->bindValue(':a', $amount, PDO::PARAM_STR);
            $st->execute();
        }
        spends_mobile_ledger_put($udb, $signature, $date, '__income__', $amount, 'income', 'mobile:income');
        $updated++;
    }

    foreach (($payload['wallet_events'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $date = trim((string)($row['date'] ?? date('Y-m-d')));
        $store = trim((string)($row['store'] ?? $row['merchant'] ?? ''));
        $amount = filter_var($row['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $raw = trim((string)($row['raw'] ?? $row['title'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $store === '' || $amount === false || $amount <= 0) {
            $errors[] = ['wallet' => $row, 'error' => 'invalid'];
            continue;
        }
        $amount = round((float)$amount, 2);
        $signature = trim((string)($row['signature'] ?? ''));
        if ($signature === '') {
            $signature = spends_mobile_signature($date, $store, $amount, $raw !== '' ? $raw : 'wallet');
        }
        if (spends_mobile_ledger_has($udb, $signature)) {
            $skipped++;
            continue;
        }
        spends_mobile_ensure_store($udb, $store, (string)($row['category'] ?? 'other'));
        $st = $udb->prepare("INSERT INTO expenses (date,store,amount,entry_source,ocr_raw_store) VALUES (:d,:s,:a,'ocr',:r)
            ON CONFLICT (user_id,date,store) DO UPDATE SET amount=ROUND((expenses.amount+excluded.amount)::numeric,2), entry_source='ocr', ocr_raw_store=COALESCE(NULLIF(excluded.ocr_raw_store,''), expenses.ocr_raw_store)");
        $st->bindValue(':d', $date);
        $st->bindValue(':s', $store);
        $st->bindValue(':a', $amount, PDO::PARAM_STR);
        if ($raw === '') {
            $st->bindValue(':r', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':r', $raw);
        }
        $st->execute();
        spends_mobile_ledger_put($udb, $signature, $date, $store, $amount, $raw !== '' ? $raw : 'wallet', 'wallet');
        $created++;
    }

    if (!empty($payload['settings']) && is_array($payload['settings'])) {
        $s = $payload['settings'];
        if (isset($s['monthly_budget'])) {
            dbSetSetting($udb, 'monthly_budget', (string)(float)$s['monthly_budget']);
        }
        if (isset($s['currency'])) {
            $cur = strtoupper(trim((string)$s['currency']));
            if (in_array($cur, ['USD', 'EUR', 'PLN', 'UAH', 'BYN'], true)) {
                dbSetSetting($udb, 'currency', $cur);
            }
        }
        foreach (['show_incomes', 'show_trend', 'trim_empty_months'] as $flag) {
            if (array_key_exists($flag, $s)) {
                dbSetSetting($udb, $flag, !empty($s[$flag]) ? '1' : '0');
            }
        }
    }

    return [
        'status' => 'success',
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'server_time' => time(),
    ];
}

function spends_mobile_current_email(): string
{
    $email = (string)($_SESSION['user']['email'] ?? '');
    if ($email !== '') {
        return $email;
    }
    if (function_exists('getCurrentUserFromActiveSessions')) {
        $fromSession = getCurrentUserFromActiveSessions();
        if (is_string($fromSession) && $fromSession !== '') {
            return $fromSession;
        }
    }
    return '';
}

function spends_mobile_profile(): array
{
    return ['status' => 'success', 'profile' => ['email' => spends_mobile_current_email(), 'app' => 'spends']];
}
