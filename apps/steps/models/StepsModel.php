<?php
require_once __DIR__ . '/../core/store.php';
// === Steps — basic operations ===
function upsertStep(AppDatabase $db, string $date, int $steps): void {
    $stmt = $db->prepare('INSERT INTO steps_days (user_email, date, steps) VALUES (:u, :d, :s) ON CONFLICT (user_email, date) DO UPDATE SET steps=excluded.steps');
    steps_bind_user($stmt);
    $stmt->bindValue(':d', $date, PDO::PARAM_STR);
    $stmt->bindValue(':s', $steps, PDO::PARAM_INT);
    $stmt->execute();
}
function upsertStepsBatch(AppDatabase $db, array $items): void {
    $pdo = $db->getPdo();
    $pdo->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO steps_days (user_email, date, steps) VALUES (:u, :d, :s) ON CONFLICT (user_email, date) DO UPDATE SET steps=excluded.steps');
        steps_bind_user($stmt);
        foreach ($items as $item) {
            $stmt->bindValue(':d', $item['date'], PDO::PARAM_STR);
            $stmt->bindValue(':s', (int)$item['steps'], PDO::PARAM_INT);
            $stmt->execute();
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
function normalizeStoredDate(string $raw): ?string {
    $s = trim($raw);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        if (!checkdate($mo, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{2})$/', $s, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $yy = (int)$m[3];
        $y = $yy < 70 ? (2000 + $yy) : (1900 + $yy);
        if (!checkdate($mo, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $s, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $y = (int)$m[3];
        if (!checkdate($mo, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    return null;
}
// Load ONLY needed date range (for chart/calendar)
function loadStepsRange(AppDatabase $db, string $from, string $to): array {
    $steps = [];
    try {
        $stmt = $db->prepare(
            'SELECT date, steps FROM steps_days WHERE user_email = :u AND date >= :from AND date <= :to ORDER BY date ASC'
        );
        steps_bind_user($stmt);
        $stmt->bindValue(':from', $from, PDO::PARAM_STR);
        $stmt->bindValue(':to',   $to,   PDO::PARAM_STR);
        $result = $stmt->execute();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $steps[$row['date']] = ['date' => $row['date'], 'steps' => (int)$row['steps']];
        }
    } catch (Exception $e) {
        error_log("loadStepsRange failed: " . $e->getMessage());
    }
    return $steps;
}

// Min and max date in DB
function getStepsDateBounds(AppDatabase $db): array {
    try {
        $stmt = $db->prepare('SELECT MIN(date), MAX(date) FROM steps_days WHERE user_email = :u');
        steps_bind_user($stmt);
        $r = $stmt->execute()->fetch(PDO::FETCH_NUM);
        return [($r[0] ?? null) ?: null, ($r[1] ?? null) ?: null];
    } catch (Exception $e) {
        return [null, null];
    }
}

// Load table page with SQL filter
function loadStepsPage(AppDatabase $db, int $page, int $pageSize, string $filterOp, ?int $filterVal, ?int $filterValTo, ?string $dateFrom, ?string $dateTo): array {
    $offset = ($page - 1) * $pageSize;

    // Build WHERE
    [$where, $params] = buildStepsWhere($filterOp, $filterVal, $filterValTo, $dateFrom, $dateTo);

    try {
        // Total count
        $countSql = "SELECT COUNT(*) FROM steps_days WHERE $where";
        $countStmt = $db->prepare($countSql);
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v[0], $v[1]);
        $total = (int)$countStmt->execute()->fetch(PDO::FETCH_NUM)[0];

        // Page
        $sql = "SELECT date, steps FROM steps_days WHERE $where ORDER BY date DESC LIMIT :lim OFFSET :off";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v[0], $v[1]);
        $stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
        $result = $stmt->execute();

        $rows = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = ['date' => $row['date'], 'steps' => (int)$row['steps']];
        }

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    } catch (Exception $e) {
        error_log("loadStepsPage failed: " . $e->getMessage());
        return ['rows' => [], 'total' => 0, 'page' => $page, 'page_size' => $pageSize];
    }
}

function buildStepsWhere(string $op, ?int $val, ?int $valTo, ?string $dateFrom, ?string $dateTo): array {
    $parts = ['user_email = :u'];
    $params = [':u' => [steps_user(), PDO::PARAM_STR]];
    $safeDateFrom = (is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) ? $dateFrom : null;
    $safeDateTo = (is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) ? $dateTo : null;
    if ($safeDateFrom !== null) {
        $parts[] = 'date >= :df';
        $params[':df'] = [$safeDateFrom, PDO::PARAM_STR];
    }
    if ($safeDateTo !== null) {
        $parts[] = 'date <= :dt';
        $params[':dt'] = [$safeDateTo, PDO::PARAM_STR];
    }
    if ($val === null) return [implode(' AND ', $parts), $params];
    switch ($op) {
        case '=':  $w = 'steps = :v1';                   $params[':v1'] = [$val, PDO::PARAM_INT]; break;
        case '>':  $w = 'steps > :v1';                   $params[':v1'] = [$val, PDO::PARAM_INT]; break;
        case '<':  $w = 'steps < :v1';                   $params[':v1'] = [$val, PDO::PARAM_INT]; break;
        case '>=': $w = 'steps >= :v1';                  $params[':v1'] = [$val, PDO::PARAM_INT]; break;
        case '<=': $w = 'steps <= :v1';                  $params[':v1'] = [$val, PDO::PARAM_INT]; break;
        case 'range':
            if ($valTo !== null) {
                $w = 'steps >= :v1 AND steps <= :v2';
                $params[':v1'] = [$val, PDO::PARAM_INT];
                $params[':v2'] = [$valTo, PDO::PARAM_INT];
            } else {
                $w = 'steps >= :v1';
                $params[':v1'] = [$val, PDO::PARAM_INT];
            }
            break;
        default: return [implode(' AND ', $parts), $params];
    }
    $parts[] = $w;
    return [implode(' AND ', $parts), $params];
}

// Export all steps to CSV (for backup)
function exportStepsToCSV(AppDatabase $db): string {
    $out = '';
    try {
        $stmt = $db->prepare('SELECT date, steps FROM steps_days WHERE user_email = :u ORDER BY date ASC');
        steps_bind_user($stmt);
        $result = $stmt->execute();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $out .= isoToCSVDate($row['date']) . ',' . $row['steps'] . "\n";
        }
    } catch (Exception $e) {
        error_log("exportStepsToCSV failed: " . $e->getMessage());
    }
    return $out;
}

// Import CSV content into DB
function importStepsFromCSVContent(AppDatabase $db, string $csvContent, bool $replace = true, bool $ownTx = true): array {
    $imported = [];
    $lines = explode("\n", $csvContent);
    $sql = 'INSERT INTO steps_days (user_email, date, steps) VALUES (:u, :d, :s) ON CONFLICT (user_email, date) DO ' . ($replace ? 'UPDATE SET steps=excluded.steps' : 'NOTHING');
    $stmt = $db->prepare($sql);
    steps_bind_user($stmt);
    if ($ownTx) $db->exec('BEGIN');
    try {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $row = str_getcsv($line);
            if (count($row) < 2) continue;
            $rawDate = trim($row[0]);
            if (!preg_match('/^\d{2}-\d{2}-\d{2}$/', $rawDate)) continue;
            $isoDate = csvDateToISO($rawDate);
            if (!$isoDate) continue;
            $steps = (int)$row[1];
            $stmt->bindValue(':d', $isoDate, PDO::PARAM_STR);
            $stmt->bindValue(':s', $steps,   PDO::PARAM_INT);
            $stmt->execute();
            $imported[$isoDate] = $steps;
        }
        if ($ownTx) $db->exec('COMMIT');
    } catch (Exception $e) {
        if ($ownTx) $db->exec('ROLLBACK');
        error_log("importStepsFromCSVContent failed: " . $e->getMessage());
        if (!$ownTx) throw $e;
    }
    return $imported;
}

function steps_fill_range(array $byDate, string $from, string $to): array {
    $out = [];
    $cd = new DateTime($from);
    $end = new DateTime($to);
    while ($cd <= $end) {
        $ds = $cd->format('Y-m-d');
        $out[] = $byDate[$ds] ?? ['date' => $ds, 'steps' => 0];
        $cd->modify('+1 day');
    }
    return $out;
}
function steps_delete_all(AppDatabase $db): void {
    $stmt = $db->prepare('DELETE FROM steps_days WHERE user_email = :u');
    steps_bind_user($stmt);
    $stmt->execute();
}
function steps_replace_all_from_csv(AppDatabase $db, string $csv): array {
    $db->exec('BEGIN');
    try {
        steps_delete_all($db);
        $r = importStepsFromCSVContent($db, $csv, true, false);
        $db->exec('COMMIT');
        return $r;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}
function steps_version(AppDatabase $db): string {
    $stmt = $db->prepare("SELECT COUNT(*) AS count, COALESCE(MAX(date), '') AS max_date, COALESCE(SUM(steps), 0) AS total_steps FROM steps_days WHERE user_email = :u");
    steps_bind_user($stmt);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return (string)($row['count'] ?? 0) . '|' . (string)($row['max_date'] ?? '') . '|' . (string)($row['total_steps'] ?? 0);
}
function steps_delete_tree(string $dir): bool {
    if (!is_dir($dir)) {
        return true;
    }
    $dir = realpath($dir);
    if ($dir === false) {
        return false;
    }
    foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
        $p = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($p)) {
            steps_delete_tree($p);
        } else {
            @unlink($p);
        }
    }
    return @rmdir($dir);
}


