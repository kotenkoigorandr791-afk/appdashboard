<?php
declare(strict_types=1);

function spendsTagList(AppDatabase $udb): array {
    $res = $udb->query('SELECT t.id, t.name, t.color, t.created_at, t.updated_at, (SELECT COUNT(*) FROM expense_tags e WHERE e.tag_id = t.id) AS usage_count FROM tags t ORDER BY lower(t.name)');
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[] = ['id' => (string)$row['id'], 'name' => (string)$row['name'], 'color' => (string)($row['color'] ?? ''), 'usage_count' => (int)$row['usage_count'], 'created_at' => (string)$row['created_at'], 'updated_at' => (string)$row['updated_at']];
    }
    return $out;
}

function spendsTagUpsert(AppDatabase $udb, array $payload): array {
    $name = mb_substr(trim((string)($payload['name'] ?? '')), 0, 60);
    if ($name === '') return ['status' => 'error', 'message' => 'empty_name'];
    $color = trim((string)($payload['color'] ?? ''));
    if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '';
    $id = trim((string)($payload['id'] ?? ''));
    $now = date('c');
    $dup = $udb->prepare('SELECT id FROM tags WHERE lower(name) = lower(:n) LIMIT 1');
    $dup->bindValue(':n', $name);
    $found = $dup->execute()->fetch(PDO::FETCH_ASSOC);
    if ($id === '' && $found) $id = (string)$found['id'];
    if ($id !== '' && $found && (string)$found['id'] !== $id) return ['status' => 'error', 'message' => 'duplicate_name'];
    if ($id === '') {
        $id = (string)(int)(microtime(true) * 1000) . bin2hex(random_bytes(3));
        $st = $udb->prepare('INSERT INTO tags (id, name, color, created_at, updated_at) VALUES (:id,:n,:c,:t,:t)');
    } else {
        $st = $udb->prepare('INSERT INTO tags (id, name, color, created_at, updated_at) VALUES (:id,:n,:c,:t,:t) ON CONFLICT (user_id,id) DO UPDATE SET name=excluded.name, color=excluded.color, updated_at=excluded.updated_at');
    }
    $st->bindValue(':id', $id);
    $st->bindValue(':n', $name);
    $st->bindValue(':c', $color !== '' ? $color : null, $color !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $st->bindValue(':t', $now);
    $st->execute();
    return ['status' => 'success', 'tag' => ['id' => $id, 'name' => $name, 'color' => $color]];
}

function spendsTagDelete(AppDatabase $udb, string $id): array {
    $id = trim($id);
    if ($id === '') return ['status' => 'error', 'message' => 'missing_id'];
    foreach (['DELETE FROM expense_tags WHERE tag_id = :id', 'DELETE FROM tags WHERE id = :id'] as $sql) {
        $st = $udb->prepare($sql);
        $st->bindValue(':id', $id);
        $st->execute();
    }
    return ['status' => 'success'];
}

function spendsExpenseTagsSet(AppDatabase $udb, string $date, string $store, array $tagIds): array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || trim($store) === '') return ['status' => 'error', 'message' => 'invalid_data'];
    $udb->exec('BEGIN');
    $del = $udb->prepare('DELETE FROM expense_tags WHERE date = :d AND store = :s');
    $del->bindValue(':d', $date);
    $del->bindValue(':s', $store);
    $del->execute();
    $ins = $udb->prepare('INSERT INTO expense_tags (date, store, tag_id) SELECT :d, :s, id FROM tags WHERE id = :t ON CONFLICT DO NOTHING');
    foreach (array_slice(array_unique(array_map('strval', $tagIds)), 0, 20) as $tid) {
        $ins->bindValue(':d', $date);
        $ins->bindValue(':s', $store);
        $ins->bindValue(':t', $tid);
        $ins->execute();
    }
    $udb->exec('COMMIT');
    return ['status' => 'success'];
}

function spendsExpenseTagsFor(AppDatabase $udb, string $from, string $to): array {
    $st = $udb->prepare('SELECT date, store, tag_id FROM expense_tags WHERE date >= :a AND date <= :b');
    $st->bindValue(':a', $from);
    $st->bindValue(':b', $to);
    $res = $st->execute();
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $out[$row['date'] . "\t" . $row['store']][] = (string)$row['tag_id'];
    }
    return $out;
}
