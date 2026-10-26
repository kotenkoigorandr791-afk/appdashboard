<?php
declare(strict_types=1);

function spendsGoalTypes(): array {
    return ['savings', 'expense'];
}

function spendsNormalizeGoalType($type): string {
    $type = strtolower(trim((string)$type));
    return in_array($type, spendsGoalTypes(), true) ? $type : 'savings';
}

function spendsGoalList(AppDatabase $udb, bool $includeArchived = false): array {
    $sql = 'SELECT id, name, description, target_amount, current_amount, currency, target_date, goal_type, is_archived, icon, created_at, updated_at FROM goals';
    if (!$includeArchived) {
        $sql .= ' WHERE COALESCE(is_archived, 0) = 0';
    }
    $sql .= ' ORDER BY is_archived ASC, updated_at DESC, created_at DESC';
    $res = $udb->query($sql);
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[] = spendsGoalRow($row);
    }
    return $out;
}

function spendsGoalRow(array $row): array {
    $target = round((float)($row['target_amount'] ?? 0), 2);
    $current = round((float)($row['current_amount'] ?? 0), 2);
    $pct = $target > 0 ? min(100, round(($current / $target) * 100, 1)) : 0.0;
    return [
        'id' => (string)($row['id'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'target_amount' => $target,
        'current_amount' => $current,
        'currency' => (string)($row['currency'] ?? 'USD'),
        'target_date' => (string)($row['target_date'] ?? ''),
        'goal_type' => spendsNormalizeGoalType($row['goal_type'] ?? 'savings'),
        'is_archived' => (int)($row['is_archived'] ?? 0) === 1,
        'icon' => (string)($row['icon'] ?? ''),
        'progress_percent' => $pct,
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function spendsGoalUpsert(AppDatabase $udb, array $payload): array {
    $id = trim((string)($payload['id'] ?? ''));
    $name = trim((string)($payload['name'] ?? ''));
    if ($name === '') {
        return ['status' => 'error', 'message' => 'empty_name'];
    }
    $now = date('c');
    $isNew = $id === '';
    if ($isNew) {
        $id = (string)(int)(microtime(true) * 1000) . bin2hex(random_bytes(3));
    }
    $target = max(0, round((float)($payload['target_amount'] ?? 0), 2));
    $current = max(0, round((float)($payload['current_amount'] ?? 0), 2));
    $currency = strtoupper(trim((string)($payload['currency'] ?? 'USD')));
    if ($currency === '' || !preg_match('/^[A-Z]{3}$/', $currency)) {
        $currency = 'USD';
    }
    $targetDate = trim((string)($payload['target_date'] ?? ''));
    if ($targetDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
        $targetDate = '';
    }
    $type = spendsNormalizeGoalType($payload['goal_type'] ?? 'savings');
    $desc = trim((string)($payload['description'] ?? ''));
    $icon = trim((string)($payload['icon'] ?? ''));
    $archived = !empty($payload['is_archived']) ? 1 : 0;

    if ($isNew) {
        $st = $udb->prepare('INSERT INTO goals (id, name, description, target_amount, current_amount, currency, target_date, goal_type, is_archived, icon, created_at, updated_at) VALUES (:id,:n,:d,:t,:c,:cur,:td,:gt,:a,:i,:ca,:ua)');
        $st->bindValue(':ca', $now);
    } else {
        $st = $udb->prepare('UPDATE goals SET name=:n, description=:d, target_amount=:t, current_amount=:c, currency=:cur, target_date=:td, goal_type=:gt, is_archived=:a, icon=:i, updated_at=:ua WHERE id=:id');
    }
    $st->bindValue(':id', $id);
    $st->bindValue(':n', $name);
    $st->bindValue(':d', $desc !== '' ? $desc : null, $desc !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $st->bindValue(':t', $target, PDO::PARAM_STR);
    $st->bindValue(':c', $current, PDO::PARAM_STR);
    $st->bindValue(':cur', $currency);
    $st->bindValue(':td', $targetDate !== '' ? $targetDate : null, $targetDate !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $st->bindValue(':gt', $type);
    $st->bindValue(':a', $archived, PDO::PARAM_INT);
    $st->bindValue(':i', $icon !== '' ? $icon : null, $icon !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $st->bindValue(':ua', $now);
    $st->execute();

    $get = $udb->prepare('SELECT id, name, description, target_amount, current_amount, currency, target_date, goal_type, is_archived, icon, created_at, updated_at FROM goals WHERE id = :id LIMIT 1');
    $get->bindValue(':id', $id);
    $row = $get->execute()->fetch(PDO::FETCH_ASSOC);
    return ['status' => 'success', 'goal' => spendsGoalRow($row ?: ['id' => $id, 'name' => $name, 'target_amount' => $target, 'current_amount' => $current, 'currency' => $currency, 'goal_type' => $type, 'created_at' => $now, 'updated_at' => $now])];
}

function spendsGoalContribute(AppDatabase $udb, string $id, float $delta): array {
    $id = trim($id);
    if ($id === '') {
        return ['status' => 'error', 'message' => 'missing_id'];
    }
    $get = $udb->prepare('SELECT id, name, description, target_amount, current_amount, currency, target_date, goal_type, is_archived, icon, created_at, updated_at FROM goals WHERE id = :id LIMIT 1');
    $get->bindValue(':id', $id);
    $row = $get->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['status' => 'error', 'message' => 'not_found'];
    }
    $current = max(0, round((float)($row['current_amount'] ?? 0) + $delta, 2));
    $now = date('c');
    $upd = $udb->prepare('UPDATE goals SET current_amount = :c, updated_at = :u WHERE id = :id');
    $upd->bindValue(':c', $current, PDO::PARAM_STR);
    $upd->bindValue(':u', $now);
    $upd->bindValue(':id', $id);
    $upd->execute();
    $row['current_amount'] = $current;
    $row['updated_at'] = $now;
    return ['status' => 'success', 'goal' => spendsGoalRow($row)];
}

function spendsGoalDelete(AppDatabase $udb, string $id): array {
    $id = trim($id);
    if ($id === '') {
        return ['status' => 'error', 'message' => 'missing_id'];
    }
    $st = $udb->prepare('DELETE FROM goals WHERE id = :id');
    $st->bindValue(':id', $id);
    $st->execute();
    return ['status' => 'success'];
}
