<?php
// ─── Шаги — базовые операции ─────────────────────────────────────────────────
function upsertStep(AppDatabase $db, string $date, int $steps): void {
    $stmt = $db->prepare('INSERT INTO steps (date, steps) VALUES (:d, :s) ON CONFLICT (date) DO UPDATE SET steps=excluded.steps');
    $stmt->bindValue(':d', $date, PDO::PARAM_STR);
    $stmt->bindValue(':s', $steps, PDO::PARAM_INT);
    $stmt->execute();
}
function upsertStepsBatch(AppDatabase $db, array $items): void {
    $pdo = $db->getPdo();
    $pdo->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO steps (date, steps) VALUES (:d, :s) ON CONFLICT (date) DO UPDATE SET steps=excluded.steps');
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
function normalizeStepsDatesTable(AppDatabase $db): void {
    $rows = [];
    try {
        $res = $db->query('SELECT date, steps FROM steps');
        while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
            $iso = normalizeStoredDate((string)($r['date'] ?? ''));
            if (!$iso) continue;
            $steps = (int)($r['steps'] ?? 0);
            if (!isset($rows[$iso]) || $steps > $rows[$iso]) $rows[$iso] = $steps;
        }
    } catch (Exception $e) {
        return;
    }
    try {
        $db->exec('BEGIN');
        $db->exec('DELETE FROM steps');
        $st = $db->prepare('INSERT INTO steps (date, steps) VALUES (:d, :s) ON CONFLICT (date) DO UPDATE SET steps=excluded.steps');
        foreach ($rows as $d => $s) {
            $st->bindValue(':d', $d, PDO::PARAM_STR);
            $st->bindValue(':s', $s, PDO::PARAM_INT);
            $st->execute();
        }
        $db->exec('COMMIT');
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
    }
}

// Загрузка ТОЛЬКО нужного диапазона дат (для графика/календаря)
function loadStepsRange(AppDatabase $db, string $from, string $to): array {
    $steps = [];
    try {
        $stmt = $db->prepare(
            'SELECT date, steps FROM steps WHERE date >= :from AND date <= :to ORDER BY date ASC'
        );
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

// Минимальная и максимальная дата в базе
function getStepsDateBounds(AppDatabase $db): array {
    try {
        $minDate = $db->querySingle('SELECT MIN(date) FROM steps');
        $maxDate = $db->querySingle('SELECT MAX(date) FROM steps');
        return [$minDate ?: null, $maxDate ?: null];
    } catch (Exception $e) {
        return [null, null];
    }
}

// Загрузка страницы таблицы с SQL-фильтром
function loadStepsPage(AppDatabase $db, int $page, int $pageSize, string $filterOp, ?int $filterVal, ?int $filterValTo, ?string $dateFrom, ?string $dateTo): array {
    $offset = ($page - 1) * $pageSize;

    // Строим WHERE
    [$where, $params] = buildStepsWhere($filterOp, $filterVal, $filterValTo, $dateFrom, $dateTo);

    try {
        // Общее кол-во
        $countSql = "SELECT COUNT(*) FROM steps" . ($where ? " WHERE $where" : '');
        $countStmt = $db->prepare($countSql);
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v[0], $v[1]);
        $total = (int)$countStmt->execute()->fetch(PDO::FETCH_NUM)[0];

        // Страница
        $sql = "SELECT date, steps FROM steps" . ($where ? " WHERE $where" : '') . " ORDER BY date DESC LIMIT :lim OFFSET :off";
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
    $parts = [];
    $params = [];
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

// Экспорт всех шагов в CSV (для бэкапов)
function exportStepsToCSV(AppDatabase $db): string {
    $out = '';
    try {
        $result = $db->query('SELECT date, steps FROM steps ORDER BY date ASC');
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $out .= isoToCSVDate($row['date']) . ',' . $row['steps'] . "\n";
        }
    } catch (Exception $e) {
        error_log("exportStepsToCSV failed: " . $e->getMessage());
    }
    return $out;
}

// Импорт CSV-контента в базу
function importStepsFromCSVContent(AppDatabase $db, string $csvContent, bool $replace = true): array {
    $imported = [];
    $lines = explode("\n", $csvContent);
    $db->exec('BEGIN');
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
            $sql   = $replace
                ? 'INSERT INTO steps (date, steps) VALUES (:d, :s) ON CONFLICT (date) DO UPDATE SET steps=excluded.steps'
                : 'INSERT INTO steps (date, steps) VALUES (:d, :s) ON CONFLICT (date) DO NOTHING';
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':d', $isoDate, PDO::PARAM_STR);
            $stmt->bindValue(':s', $steps,   PDO::PARAM_INT);
            $stmt->execute();
            $imported[$isoDate] = $steps;
        }
        $db->exec('COMMIT');
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        error_log("importStepsFromCSVContent failed: " . $e->getMessage());
    }
    return $imported;
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

// Получение распределения шагов по годам (для графика)
function getYearlyStepDistribution(AppDatabase $db): array {
    $result = [];
    try {
        $query = 'SELECT strftime("%Y", date) as year, SUM(steps) as total_steps FROM steps GROUP BY year ORDER BY year ASC';
        $stmt = $db->prepare($query);
        $executionResult = $stmt->execute();
        
        while ($row = $executionResult->fetch(PDO::FETCH_ASSOC)) {
            $result[] = [
                'year' => $row['year'],
                'steps' => (int)$row['total_steps']
            ];
        }
    } catch (Exception $e) {
        error_log("getYearlyStepDistribution failed: " . $e->getMessage());
    }
    return $result;
}

// Получение интенсивности шагов по месяцам текущего года (для графика)
function getMonthlyStepIntensityCurrentYear(AppDatabase $db): array {
    $currentYear = getCurrentYear();
    $result = array_fill(0, 12, ['month' => 0, 'steps' => 0]);
    
    // Инициализируем массив с названиями месяцев
    for ($i = 0; $i < 12; $i++) {
        $result[$i]['month'] = $i + 1;
    }
    
    try {
        $query = 'SELECT strftime("%m", date) as month, SUM(steps) as total_steps FROM steps WHERE strftime("%Y", date) = :year GROUP BY month ORDER BY month ASC';
        $stmt = $db->prepare($query);
        $stmt->bindValue(':year', $currentYear, PDO::PARAM_STR);
        $executionResult = $stmt->execute();
        
        while ($row = $executionResult->fetch(PDO::FETCH_ASSOC)) {
            $monthIndex = (int)$row['month'] - 1;
            if ($monthIndex >= 0 && $monthIndex < 12) {
                $result[$monthIndex]['steps'] = (int)$row['total_steps'];
            }
        }
    } catch (Exception $e) {
        error_log("getMonthlyStepIntensityCurrentYear failed: " . $e->getMessage());
    }
    
    return $result;
}

