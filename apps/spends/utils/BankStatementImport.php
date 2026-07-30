<?php
declare(strict_types=1);
final class BankStatementImport
{
    public const MAX_BYTES = 15728640;
    private static function normHeader(string $h): string
    {
        $h = mb_strtolower(trim($h), 'UTF-8');
        $h = preg_replace('/\s+/u', ' ', $h) ?? $h;
        return $h;
    }
    public static function detectEncoding(string $raw): string
    {
        if (preg_match('//u', $raw)) {
            return 'UTF-8';
        }
        return 'Windows-1250';
    }
    public static function toUtf8(string $s, string $enc): string
    {
        if ($enc === 'UTF-8') {
            return $s;
        }
        $t = @iconv($enc, 'UTF-8//IGNORE', $s);
        return $t !== false ? $t : $s;
    }
    public static function parseAmountCell(string $cell): ?array
    {
        $cell = trim(preg_replace('/\p{Z}+/u', '', $cell) ?? '');
        if ($cell === '') {
            return null;
        }
        if (!preg_match('/^(-?)\s*([\d\s]+[,.]?\d*)\s*(PLN|EUR|USD|UAH|BYN|GBP)?/iu', $cell, $m)) {
            return null;
        }
        $neg = $m[1] === '-';
        $num = str_replace([' ', ','], ['', '.'], $m[2]);
        if ($num === '' || $num === '.') {
            return null;
        }
        $v = (float) $num;
        if ($neg) {
            $v = -abs($v);
        }
        $cur = isset($m[3]) ? strtoupper($m[3]) : '';
        return ['value' => $v, 'currency' => $cur];
    }
    public static function parseDateCell(string $cell): ?string
    {
        $cell = trim($cell);
        if ($cell === '') {
            return null;
        }
        if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $cell, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $cell, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/(\d{2})-(\d{2})-(\d{4})/', $cell, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        return null;
    }
    private static function scoreDateCol(array $rows, int $col): float
    {
        $ok = 0;
        $n = min(count($rows), 120);
        for ($i = 0; $i < $n; $i++) {
            $c = $rows[$i][$col] ?? '';
            if (self::parseDateCell($c) !== null) {
                $ok++;
            }
        }
        return $n > 0 ? $ok / $n : 0;
    }
    private static function scoreAmountCol(array $rows, int $col): float
    {
        $ok = 0;
        $n = min(count($rows), 120);
        for ($i = 0; $i < $n; $i++) {
            $c = $rows[$i][$col] ?? '';
            if (self::parseAmountCell($c) !== null) {
                $ok++;
            }
        }
        return $n > 0 ? $ok / $n : 0;
    }
    private static function scoreTextCol(array $rows, int $col): float
    {
        $s = 0;
        $n = min(count($rows), 80);
        for ($i = 0; $i < $n; $i++) {
            $c = trim((string) ($rows[$i][$col] ?? ''));
            if (strlen($c) >= 3 && !preg_match('/^-?[\d\s.,]+$/', $c)) {
                $s++;
            }
        }
        return $n > 0 ? $s / $n : 0;
    }
    public static function guessDelimiter(string $firstLine): string
    {
        $sc = substr_count($firstLine, ';');
        $cc = substr_count($firstLine, ',');
        return $sc >= $cc ? ';' : ',';
    }
    public static function guessColumns(array $headers, array $sampleRows): array
    {
        $nh = [];
        foreach ($headers as $i => $h) {
            $nh[$i] = self::normHeader((string) $h);
        }
        $dateIdx = null;
        $bestD = 0.0;
        foreach ($nh as $i => $hn) {
            if (preg_match('/data.*operac|transaction.*date|buchung|date.*operation/i', $hn)) {
                $sc = self::scoreDateCol($sampleRows, $i);
                if ($sc > $bestD) {
                    $bestD = $sc;
                    $dateIdx = $i;
                }
            }
        }
        if ($dateIdx === null) {
            foreach ($nh as $i => $_) {
                $sc = self::scoreDateCol($sampleRows, $i);
                if ($sc > $bestD) {
                    $bestD = $sc;
                    $dateIdx = $i;
                }
            }
        }
        $dateIdxWal = null;
        $bestWal = 0.0;
        foreach ($nh as $i => $hn) {
            if (!preg_match('/data\s*waluty|value\s*date/i', $hn)) {
                continue;
            }
            if (preg_match('/pierwot|original|zwrotu|przelewu\s*pierw/i', $hn)) {
                continue;
            }
            $sc = self::scoreDateCol($sampleRows, $i);
            if ($sc > $bestWal) {
                $bestWal = $sc;
                $dateIdxWal = $i;
            }
        }
        if ($dateIdxWal !== null && $bestWal < 0.35) {
            $dateIdxWal = null;
        }
        $dateIdxKsi = null;
        $bestK = 0.0;
        foreach ($headers as $i => $rawH) {
            $raw = mb_strtolower(trim((string) $rawH), 'UTF-8');
            if (preg_match('/data\s*operac/i', $raw)) {
                continue;
            }
            if (!preg_match('/ksi|booking|buchungstag|buchungsdatum|datum\s*ksiegowania|data\s*zaksięgowania/u', $raw)) {
                continue;
            }
            $sc = self::scoreDateCol($sampleRows, (int) $i);
            if ($sc > $bestK) {
                $bestK = $sc;
                $dateIdxKsi = (int) $i;
            }
        }
        if ($dateIdxKsi !== null && $bestK < 0.2) {
            $dateIdxKsi = null;
        }
        $amountIdx = null;
        $bestA = 0.0;
        foreach ($nh as $i => $hn) {
            if (!preg_match('/kwota|amount|betrag|importe/i', $hn)) {
                continue;
            }
            if (preg_match('/saldo|balance|vat|pierwotn|kurs|waluty\s*kup/i', $hn)) {
                continue;
            }
            $sc = self::scoreAmountCol($sampleRows, $i);
            if ($sc > $bestA) {
                $bestA = $sc;
                $amountIdx = $i;
            }
        }
        if ($amountIdx === null) {
            foreach ($nh as $i => $_) {
                $sc = self::scoreAmountCol($sampleRows, $i);
                if ($sc > $bestA) {
                    $bestA = $sc;
                    $amountIdx = $i;
                }
            }
        }
        $merchIdx = null;
        $bestM = 0.0;
        foreach ($headers as $i => $rawH) {
            $raw = (string) $rawH;
            if (stripos($raw, 'miejsce') !== false && stripos($raw, 'transak') !== false) {
                $merchIdx = (int) $i;
                $bestM = 1.0;
                break;
            }
        }
        if ($merchIdx === null) {
            foreach ($nh as $i => $hn) {
                if (preg_match('/merchant|payee|empf|beguenst|description|beschreibung/i', $hn) && !preg_match('/^opis$/i', trim((string) $headers[$i]))) {
                    $sc = self::scoreTextCol($sampleRows, $i);
                    if ($sc > $bestM) {
                        $bestM = $sc;
                        $merchIdx = $i;
                    }
                }
            }
        }
        if ($merchIdx === null) {
            foreach ($nh as $i => $_) {
                $sc = self::scoreTextCol($sampleRows, $i);
                if ($sc > $bestM) {
                    $bestM = $sc;
                    $merchIdx = $i;
                }
            }
        }
        $catIdx = null;
        foreach ($nh as $i => $hn) {
            if (preg_match('/kategoria|category|typ/i', $hn)) {
                $catIdx = $i;
                break;
            }
        }
        $nadIdx = null;
        $odbIdx = null;
        foreach ($nh as $i => $hn) {
            if ($hn === 'nadawca' || preg_match('/^nadawca$/i', trim((string) $headers[$i]))) {
                $nadIdx = $i;
            }
            if ($hn === 'odbiorca' || (preg_match('/^odbiorca$/i', trim((string) $headers[$i])) && strlen($hn) < 30)) {
                $odbIdx = $i;
            }
        }
        $wwwIdx = null;
        foreach ($nh as $i => $hn) {
            if (preg_match('/www|url|sklep.*www/i', $hn)) {
                $wwwIdx = $i;
                break;
            }
        }
        $descIdx = null;
        foreach ($nh as $i => $hn) {
            if (preg_match('/opis.*szczeg|detail/i', $hn)) {
                $descIdx = $i;
                break;
            }
        }
        return [
            'date' => $dateIdx,
            'date_booking' => $dateIdxKsi,
            'date_fallback' => $dateIdxWal,
            'amount' => $amountIdx,
            'merchant' => $merchIdx,
            'category' => $catIdx,
            'nadawca' => $nadIdx,
            'odbiorca' => $odbIdx,
            'www' => $wwwIdx,
            'opis' => $descIdx,
            'confidence' => round(($bestD + $bestA + min($bestM, 1.0)) / 3, 3),
        ];
    }
    public static function merchantForRow(array $row, array $map): string
    {
        $mi = $map['merchant'];
        if ($mi !== null && trim((string) ($row[$mi] ?? '')) !== '') {
            return trim((string) $row[$mi]);
        }
        $wi = $map['www'];
        if ($wi !== null && trim((string) ($row[$wi] ?? '')) !== '') {
            return trim((string) $row[$wi]);
        }
        $ni = $map['nadawca'];
        $oi = $map['odbiorca'];
        $amt = $map['amount'];
        $parsed = $amt !== null ? self::parseAmountCell((string) ($row[$amt] ?? '')) : null;
        $v = $parsed['value'] ?? 0.0;
        if ($v > 0) {
            if ($ni !== null && trim((string) ($row[$ni] ?? '')) !== '') {
                return trim((string) $row[$ni]);
            }
            if ($oi !== null && trim((string) ($row[$oi] ?? '')) !== '') {
                return trim((string) $row[$oi]);
            }
        } else {
            if ($oi !== null && trim((string) ($row[$oi] ?? '')) !== '') {
                return trim((string) $row[$oi]);
            }
            if ($ni !== null && trim((string) ($row[$ni] ?? '')) !== '') {
                return trim((string) $row[$ni]);
            }
        }
        $di = $map['opis'];
        if ($di !== null && trim((string) ($row[$di] ?? '')) !== '') {
            return trim((string) $row[$di]);
        }
        $ci = $map['category'];
        if ($ci !== null) {
            return trim((string) ($row[$ci] ?? '')) ?: '—';
        }
        return '—';
    }
    public static function rowDate(array $row, array $map): ?string
    {
        foreach (['date', 'date_booking', 'date_fallback'] as $k) {
            $di = $map[$k] ?? null;
            if ($di === null) {
                continue;
            }
            $d = self::parseDateCell((string) ($row[$di] ?? ''));
            if ($d !== null) {
                return $d;
            }
        }
        return null;
    }
    public static function normalizeStoreKey(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return $s;
    }
    public static function bestStoreMatch(string $label, array $storeNames): string
    {
        $nk = self::normalizeStoreKey($label);
        foreach ($storeNames as $sn) {
            if (self::normalizeStoreKey($sn) === $nk) {
                return $sn;
            }
        }
        $best = $label;
        $bestPct = 0.0;
        foreach ($storeNames as $sn) {
            $pct = 0.0;
            similar_text($nk, self::normalizeStoreKey($sn), $pct);
            if ($pct > $bestPct) {
                $bestPct = $pct;
                $best = $sn;
            }
        }
        return $bestPct >= 40.0 ? $best : $label;
    }
    public static function readCsv(string $path): array
    {
        $raw = file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        if ($raw === false) {
            throw new RuntimeException('read_failed');
        }
        if (strlen($raw) > self::MAX_BYTES) {
            throw new RuntimeException('file_too_large');
        }
        $enc = self::detectEncoding($raw);
        $text = self::toUtf8($raw, $enc);
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        if ($lines === [] || $lines[0] === '') {
            throw new RuntimeException('empty');
        }
        $del = self::guessDelimiter($lines[0]);
        $headers = str_getcsv($lines[0], $del, '"', '\\');
        $rows = [];
        for ($i = 1, $L = count($lines); $i < $L; $i++) {
            if (trim($lines[$i]) === '') {
                continue;
            }
            $rows[] = str_getcsv($lines[$i], $del, '"', '\\');
        }
        return ['delimiter' => $del, 'headers' => $headers, 'rows' => $rows];
    }
    public static function buildTransactions(array $parsed, array $storeNames, string $baseCurrency): array
    {
        $headers = $parsed['headers'];
        $rows = $parsed['rows'];
        $sample = array_slice($rows, 0, min(150, count($rows)));
        $map = self::guessColumns($headers, $sample);
        $out = [];
        foreach ($rows as $row) {
            if (count($row) < 3) {
                continue;
            }
            $date = self::rowDate($row, $map);
            if ($date === null) {
                continue;
            }
            $ai = $map['amount'];
            if ($ai === null) {
                continue;
            }
            $parsedAmt = self::parseAmountCell((string) ($row[$ai] ?? ''));
            if ($parsedAmt === null) {
                continue;
            }
            $val = $parsedAmt['value'];
            if (abs($val) < 0.0001) {
                continue;
            }
            $merchRaw = self::merchantForRow($row, $map);
            $cat = $map['category'] !== null ? trim((string) ($row[$map['category']] ?? '')) : '';
            $mappedStore = self::bestStoreMatch($merchRaw, $storeNames);
            $isIncome = $val > 0;
            $amt = abs($val);
            $sig = sha1($date . '|' . self::normalizeStoreKey($mappedStore) . '|' . number_format($amt, 2, '.', '') . '|' . ($isIncome ? 'i' : 'e'));
            $gkey = sha1(($isIncome ? 'in:' : 'ex:') . self::normalizeStoreKey($merchRaw) . '|' . self::normalizeStoreKey($cat));
            $out[] = [
                'date' => $date,
                'income' => $isIncome,
                'amount' => $amt,
                'merchant_raw' => $merchRaw,
                'category' => $cat,
                'store_default' => $mappedStore,
                'group_key' => $gkey,
                'group_label' => $merchRaw . ($cat !== '' ? ' · ' . $cat : ''),
                'signature' => $sig,
            ];
        }
        return ['map' => $map, 'headers' => $headers, 'transactions' => $out];
    }
    public static function markDuplicates(AppDatabase $udb, array &$tx): int
    {
        $dup = 0;
        foreach ($tx as &$t) {
            $t['duplicate'] = false;
            if ($t['income']) {
                continue;
            }
            $st = $t['store_default'];
            $chk = $udb->prepare('SELECT 1 FROM expenses WHERE date=:d AND store=:s AND ABS(ROUND(amount,2)-:a)<0.02 LIMIT 1');
            $chk->bindValue(':d', $t['date']);
            $chk->bindValue(':s', $st);
            $chk->bindValue(':a', $t['amount'], PDO::PARAM_STR);
            if ($chk->execute()->fetch()) {
                $t['duplicate'] = true;
                $dup++;
            }
        }
        unset($t);
        return $dup;
    }
    public static function multisetFromDbExpenses(AppDatabase $udb, string $date): array
    {
        $st = $udb->prepare('SELECT store, ROUND(amount,2) AS a FROM expenses WHERE date=:d AND ABS(amount)>0.0001');
        $st->bindValue(':d', $date);
        $r = $st->execute();
        $list = [];
        while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
            $list[] = self::normalizeStoreKey((string) $row['store']) . '|' . number_format((float) $row['a'], 2, '.', '');
        }
        sort($list);
        return $list;
    }
    public static function multisetFromImport(array $tx, string $date, array $storeMap): array
    {
        $list = [];
        foreach ($tx as $t) {
            if ($t['date'] !== $date || $t['income'] || !empty($t['duplicate'])) {
                continue;
            }
            $st = $storeMap[$t['group_key']] ?? $t['store_default'];
            $list[] = self::normalizeStoreKey($st) . '|' . number_format($t['amount'], 2, '.', '');
        }
        sort($list);
        return $list;
    }
    public static function multisetFromImportIncome(array $tx, string $date): float
    {
        $s = 0.0;
        foreach ($tx as $t) {
            if ($t['date'] === $date && $t['income']) {
                $s += $t['amount'];
            }
        }
        return round($s, 2);
    }
    public static function analyze(AppDatabase $udb, string $path, array $storeNames, string $baseCurrency): array
    {
        $parsed = self::readCsv($path);
        $built = self::buildTransactions($parsed, $storeNames, $baseCurrency);
        $tx = $built['transactions'];
        $byDateDb = [];
        $stAll = $udb->query("SELECT DISTINCT date FROM expenses WHERE ABS(amount)>0.0001");
        while ($row = $stAll->fetch(PDO::FETCH_ASSOC)) {
            $byDateDb[(string) $row['date']] = true;
        }
        $dup = self::markDuplicates($udb, $tx);
        $conflictDates = [];
        $datesSeen = [];
        foreach ($tx as $t) {
            $datesSeen[$t['date']] = true;
        }
        foreach (array_keys($datesSeen) as $d) {
            $hasDb = isset($byDateDb[$d]);
            if (!$hasDb) {
                continue;
            }
            $dbm = self::multisetFromDbExpenses($udb, $d);
            $emptyMap = [];
            $imm = self::multisetFromImport($tx, $d, $emptyMap);
            if ($imm === []) {
                continue;
            }
            if ($dbm !== $imm) {
                $conflictDates[$d] = true;
            }
        }
        $groups = [];
        foreach ($tx as $t) {
            if ($t['duplicate']) {
                continue;
            }
            $gk = $t['group_key'];
            if (!isset($groups[$gk])) {
                $groups[$gk] = [
                    'key' => $gk,
                    'label' => $t['group_label'],
                    'income' => $t['income'],
                    'count' => 0,
                    'total' => 0.0,
                    'dates' => [],
                ];
            }
            $groups[$gk]['count']++;
            $groups[$gk]['total'] += $t['income'] ? $t['amount'] : $t['amount'];
            if (!in_array($t['date'], $groups[$gk]['dates'], true) && count($groups[$gk]['dates']) < 40) {
                $groups[$gk]['dates'][] = $t['date'];
            }
        }
        foreach ($groups as &$g) {
            sort($g['dates']);
            $g['total'] = round($g['total'], 2);
        }
        unset($g);
        return [
            'status' => 'success',
            'delimiter' => $parsed['delimiter'],
            'column_guess' => $built['map'],
            'confidence' => $built['map']['confidence'] ?? 0,
            'groups' => array_values($groups),
            'duplicate_count' => $dup,
            'conflict_dates' => array_keys($conflictDates),
            'row_count' => count($tx),
        ];
    }
    public static function ensureStore(AppDatabase $udb, string $name): void
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
    public static function apply(AppDatabase $udb, array $tx, array $excludeGroupKeys, array $storeMap, string $conflictMode, array $conflictDates): array
    {
        $conflictSet = [];
        foreach ($conflictDates as $d) {
            $conflictSet[(string) $d] = true;
        }
        $exclude = [];
        foreach ($excludeGroupKeys as $k) {
            $exclude[(string) $k] = true;
        }
        $filtered = [];
        foreach ($tx as $t) {
            if (!empty($t['duplicate'])) {
                continue;
            }
            if (isset($exclude[$t['group_key']])) {
                continue;
            }
            $filtered[] = $t;
        }
        $aggExp = [];
        $aggInc = [];
        foreach ($filtered as $t) {
            $d = $t['date'];
            if ($t['income']) {
                $aggInc[$d] = ($aggInc[$d] ?? 0.0) + $t['amount'];
                continue;
            }
            $store = trim((string) ($storeMap[$t['group_key']] ?? $t['store_default']));
            if ($store === '') {
                $store = $t['merchant_raw'];
            }
            if (!isset($aggExp[$d])) {
                $aggExp[$d] = [];
            }
            $aggExp[$d][$store] = ($aggExp[$d][$store] ?? 0.0) + $t['amount'];
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
                    self::ensureStore($udb, $store);
                    if (isset($replaceDays[$d])) {
                        $ins = $udb->prepare("INSERT INTO expenses (date,store,amount,entry_source,ocr_raw_store) VALUES (:d,:s,:a,'manual',NULL) ON CONFLICT(date,store) DO NOTHING");
                        $ins->bindValue(':d', $d);
                        $ins->bindValue(':s', $store);
                        $ins->bindValue(':a', $amt, PDO::PARAM_STR);
                        $ins->execute();
                    } else {
                        $ins = $udb->prepare("INSERT INTO expenses (date,store,amount,entry_source,ocr_raw_store) VALUES (:d,:s,:a,'manual',NULL) ON CONFLICT(date,store) DO NOTHING ON CONFLICT(date,store) DO UPDATE SET amount=ROUND(amount+excluded.amount,2),entry_source='manual',ocr_raw_store=NULL");
                        $ins->bindValue(':d', $d);
                        $ins->bindValue(':s', $store);
                        $ins->bindValue(':a', $amt, PDO::PARAM_STR);
                        $ins->execute();
                    }
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
                    $ins->bindValue(':d', $d);
                    $ins->bindValue(':a', $sum, PDO::PARAM_STR);
                    $ins->execute();
                } else {
                    $ins = $udb->prepare('INSERT INTO incomes(date,amount) VALUES(:d,:a) ON CONFLICT(date) DO NOTHING ON CONFLICT(date) DO UPDATE SET amount=ROUND(amount+excluded.amount,2)');
                    $ins->bindValue(':d', $d);
                    $ins->bindValue(':a', $sum, PDO::PARAM_STR);
                    $ins->execute();
                }
            }
            $udb->exec('COMMIT');
        } catch (Throwable $e) {
            $udb->exec('ROLLBACK');
            throw $e;
        }
        return ['status' => 'success', 'applied' => true];
    }
}
