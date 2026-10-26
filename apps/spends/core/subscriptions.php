<?php
// ─── Recurring expenses (subscriptions) ───────────────────────────────────────
// A subscription is a rule, not a stored list of dates: every time the app runs
// it materialises the charges that became due since the last run, so a long
// pause in usage still results in all expected expenses.

function spendsSubscriptionYmd(?string $value): string {
    $value = trim((string)$value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : '';
}

/**
 * Due charge dates for one subscription, strictly after `last_run` and not
 * after min(end_date, today).
 *
 * Monthly charges keep the requested day of month and clamp it to the last day
 * of shorter months (31 → 30/28/29), then return to the requested day in the
 * months that have it.
 */
function spendsSubscriptionDueDates(array $sub, string $today): array {
    $start = spendsSubscriptionYmd($sub['start_date'] ?? '');
    if ($start === '') return [];
    $end   = spendsSubscriptionYmd($sub['end_date'] ?? '');
    $until = ($end !== '' && strcmp($end, $today) < 0) ? $end : $today;
    if (strcmp($until, $start) < 0) return [];
    $lastRun = spendsSubscriptionYmd($sub['last_run'] ?? '');
    $dates = [];

    if (($sub['frequency'] ?? 'monthly') === 'monthly') {
        $day = (int)($sub['day_of_month'] ?? 1);
        $day = max(1, min(31, $day));
        $cursor = new DateTime(substr($start, 0, 7) . '-01');
        $stop   = new DateTime(substr($until, 0, 7) . '-01');
        $guard  = 0;
        while ($cursor <= $stop && $guard < 600) {
            $year  = (int)$cursor->format('Y');
            $month = (int)$cursor->format('n');
            $daysInMonth = (int)$cursor->format('t');
            $ymd = sprintf('%04d-%02d-%02d', $year, $month, min($day, $daysInMonth));
            if (strcmp($ymd, $start) >= 0 && strcmp($ymd, $until) <= 0 && ($lastRun === '' || strcmp($ymd, $lastRun) > 0)) {
                $dates[] = $ymd;
            }
            $cursor->modify('+1 month');
            $guard++;
        }
        return $dates;
    }

    $step = (int)($sub['interval_days'] ?? 0);
    $step = max(1, min(3650, $step));
    $startObj = new DateTime($start);
    $cursor = clone $startObj;
    if ($lastRun !== '') {
        $elapsed = (int)$startObj->diff(new DateTime($lastRun))->days;
        $skip = intdiv($elapsed, $step) + 1;
        if ($skip > 0) $cursor->modify('+' . ($skip * $step) . ' days');
    }
    $untilObj = new DateTime($until);
    $guard = 0;
    while ($cursor <= $untilObj && $guard < 2000) {
        $dates[] = $cursor->format('Y-m-d');
        $cursor->modify('+' . $step . ' days');
        $guard++;
    }
    return $dates;
}

/** Materialise every due charge and remember how far each rule has been applied. */
function spendsApplySubscriptions($udb): int {
    $res = $udb->query('SELECT id, store, amount, frequency, day_of_month, interval_days, start_date, end_date, last_run, created_at FROM subscriptions ORDER BY created_at ASC');
    $subs = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) $subs[] = $row;
    if (!$subs) return 0;

    $today = date('Y-m-d');
    $insert = $udb->prepare("INSERT INTO expenses (date, store, amount, entry_source, ocr_raw_store) VALUES (:d, :s, :a, 'subscription', NULL) ON CONFLICT (user_id, date, store) DO NOTHING");
    $advance = $udb->prepare('UPDATE subscriptions SET last_run = :l WHERE id = :id');
    $added = 0;
    foreach ($subs as $sub) {
        $dates = spendsSubscriptionDueDates($sub, $today);
        if (!$dates) continue;
        foreach ($dates as $date) {
            $insert->bindValue(':d', $date);
            $insert->bindValue(':s', (string)$sub['store']);
            $insert->bindValue(':a', (float)$sub['amount'], PDO::PARAM_STR);
            $insert->execute();
            $added++;
        }
        $advance->bindValue(':l', (string)end($dates));
        $advance->bindValue(':id', (string)$sub['id']);
        $advance->execute();
    }
    return $added;
}

/** Run the scheduler at most once per day per user. */
function spendsSubscriptionsSync($udb): void {
    $today = date('Y-m-d');
    if ((string)dbGetSetting($udb, 'subscriptions_synced', '') === $today) return;
    try {
        spendsApplySubscriptions($udb);
        dbSetSetting($udb, 'subscriptions_synced', $today);
    } catch (Throwable $e) {
        error_log('spends subscriptions sync: ' . $e->getMessage());
    }
}

function spendsSubscriptionList($udb): array {
    $res = $udb->query('SELECT id, store, amount, frequency, day_of_month, interval_days, start_date, end_date, last_run, created_at FROM subscriptions ORDER BY created_at ASC');
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[] = [
            'id'            => (string)$row['id'],
            'store'         => (string)$row['store'],
            'amount'        => (float)$row['amount'],
            'frequency'     => (string)($row['frequency'] ?? 'monthly'),
            'day_of_month'  => (int)($row['day_of_month'] ?? 0),
            'interval_days' => (int)($row['interval_days'] ?? 0),
            'start_date'    => (string)$row['start_date'],
            'end_date'      => (string)($row['end_date'] ?? ''),
            'last_run'      => (string)($row['last_run'] ?? ''),
        ];
    }
    return $out;
}
