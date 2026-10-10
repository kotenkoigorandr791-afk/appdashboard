<?php
declare(strict_types=1);
// Shared storage-quota request helpers.
//
// Lifecycle:
//   1. A user whose space ran out asks an admin for more storage
//      (cloud_storage_request_create). A single broadcast notification is
//      created for every admin (user_email = '').
//   2. Any admin resolves it either from the cloud "Manage storage" panel or
//      straight from the support chat (cloud_storage_request_resolve).
//   3. Resolving the request deletes the broadcast notification (so it
//      disappears once the first admin reviews it) and notifies the requester
//      about the outcome.

if (!defined('CLOUD_DEFAULT_QUOTA_MB')) {
    define('CLOUD_DEFAULT_QUOTA_MB', 1024); // 1 GB for every new user
}

if (!class_exists('AppDatabase')) {
    $__lib = dirname(__DIR__, 3) . '/lib/app_db.php';
    if (is_file($__lib)) require_once $__lib;
}

function cloud_storage_default_quota_mb(): int
{
    return (int)CLOUD_DEFAULT_QUOTA_MB;
}

function cloud_storage_requests_ensure($db): void
{
    if (!$db) return;
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_storage_requests (
        id BIGSERIAL PRIMARY KEY,
        email TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        note TEXT NOT NULL DEFAULT '',
        current_quota_mb INTEGER NOT NULL DEFAULT 0,
        used_bytes BIGINT NOT NULL DEFAULT 0,
        granted_quota_mb INTEGER NOT NULL DEFAULT 0,
        reject_reason TEXT NOT NULL DEFAULT '',
        notification_id BIGINT NOT NULL DEFAULT 0,
        created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
        resolved_at BIGINT,
        resolved_by TEXT NOT NULL DEFAULT ''
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_storage_requests_status ON cloud_storage_requests(status, created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_storage_requests_email ON cloud_storage_requests(email, status)");
    if (function_exists('app_notifications_ensure')) app_notifications_ensure($db);
}

function cloud_storage_map_request(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'email' => strtolower(trim((string)($row['email'] ?? ''))),
        'status' => (string)($row['status'] ?? 'pending'),
        'note' => (string)($row['note'] ?? ''),
        'current_quota_mb' => (int)($row['current_quota_mb'] ?? 0),
        'used_bytes' => (int)($row['used_bytes'] ?? 0),
        'granted_quota_mb' => (int)($row['granted_quota_mb'] ?? 0),
        'reject_reason' => (string)($row['reject_reason'] ?? ''),
        'created_at' => (int)($row['created_at'] ?? 0),
        'resolved_at' => isset($row['resolved_at']) ? (int)$row['resolved_at'] : null,
        'resolved_by' => strtolower(trim((string)($row['resolved_by'] ?? ''))),
    ];
}

function cloud_storage_ensure_usage_table($db): void
{
    if (!$db) return;
    if (function_exists('ensure_storage_usage_table')) {
        ensure_storage_usage_table($db);
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_storage_usage_stats (
        email TEXT PRIMARY KEY,
        stats_json TEXT NOT NULL DEFAULT '[]',
        root_total_bytes INTEGER NOT NULL DEFAULT 0,
        updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
    )");
}

/** Batch quota lookup for a set of emails (lowercased keys). */
function cloud_storage_quota_map($db, array $emails): array
{
    $map = [];
    if (!$db || !$emails) return $map;
    foreach (array_chunk(array_values(array_unique($emails)), 200) as $chunk) {
        $ph = [];
        foreach ($chunk as $i => $e) $ph[] = ':e' . $i;
        $stmt = $db->prepare('SELECT LOWER(email) AS e, quota_mb AS q FROM cloud_user_settings WHERE LOWER(email) IN (' . implode(',', $ph) . ')');
        foreach ($chunk as $i => $e) $stmt->bindValue(':e' . $i, strtolower((string)$e), PDO::PARAM_STR);
        $res = $stmt->execute();
        while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
            $map[(string)$row['e']] = max(1, (int)$row['q']);
        }
    }
    return $map;
}

/** Batch usage lookup from the cached stats table (lowercased keys). */
function cloud_storage_usage_map($db, array $emails): array
{
    $map = [];
    if (!$db || !$emails) return $map;
    cloud_storage_ensure_usage_table($db);
    foreach (array_chunk(array_values(array_unique($emails)), 200) as $chunk) {
        $ph = [];
        foreach ($chunk as $i => $e) $ph[] = ':e' . $i;
        $stmt = $db->prepare('SELECT LOWER(email) AS e, root_total_bytes AS b FROM cloud_storage_usage_stats WHERE LOWER(email) IN (' . implode(',', $ph) . ')');
        foreach ($chunk as $i => $e) $stmt->bindValue(':e' . $i, strtolower((string)$e), PDO::PARAM_STR);
        $res = $stmt->execute();
        while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
            $map[(string)$row['e']] = max(0, (int)$row['b']);
        }
    }
    return $map;
}

/** Batch pending-request lookup for a set of emails (latest per email). */
function cloud_storage_pending_map($db, array $emails): array
{
    $map = [];
    if (!$db || !$emails) return $map;
    cloud_storage_requests_ensure($db);
    foreach (array_chunk(array_values(array_unique($emails)), 200) as $chunk) {
        $ph = [];
        foreach ($chunk as $i => $e) $ph[] = ':e' . $i;
        $stmt = $db->prepare("SELECT * FROM cloud_storage_requests WHERE status = 'pending' AND LOWER(email) IN (" . implode(',', $ph) . ") ORDER BY created_at DESC, id DESC");
        foreach ($chunk as $i => $e) $stmt->bindValue(':e' . $i, strtolower((string)$e), PDO::PARAM_STR);
        $res = $stmt->execute();
        while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
            $mapped = cloud_storage_map_request($row);
            if (!isset($map[$mapped['email']])) $map[$mapped['email']] = $mapped;
        }
    }
    return $map;
}

function cloud_storage_user_quota_mb($db, string $email): int
{
    $email = strtolower(trim($email));
    if ($db && $email !== '') {
        $stmt = $db->prepare('SELECT quota_mb FROM cloud_user_settings WHERE email = :e LIMIT 1');
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if ($row) return max(1, (int)$row['quota_mb']);
    }
    return cloud_storage_default_quota_mb();
}

function cloud_storage_pending_request($db, string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '' || !$db) return null;
    cloud_storage_requests_ensure($db);
    $stmt = $db->prepare("SELECT * FROM cloud_storage_requests WHERE email = :e AND status = 'pending' ORDER BY created_at DESC, id DESC LIMIT 1");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ? cloud_storage_map_request($row) : null;
}

function cloud_storage_request_by_id($db, int $id): ?array
{
    if (!$db || $id <= 0) return null;
    cloud_storage_requests_ensure($db);
    $stmt = $db->prepare('SELECT * FROM cloud_storage_requests WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ? cloud_storage_map_request($row) : null;
}

function cloud_storage_list_requests($db, string $status = 'pending', int $limit = 100): array
{
    if (!$db) return [];
    cloud_storage_requests_ensure($db);
    $out = [];
    if ($status === 'all') {
        $stmt = $db->prepare('SELECT * FROM cloud_storage_requests ORDER BY created_at DESC, id DESC LIMIT :lim');
    } else {
        $stmt = $db->prepare('SELECT * FROM cloud_storage_requests WHERE status = :s ORDER BY created_at DESC, id DESC LIMIT :lim');
        $stmt->bindValue(':s', $status, PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', max(1, min(500, $limit)), PDO::PARAM_INT);
    $res = $stmt->execute();
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $out[] = cloud_storage_map_request($row);
    }
    return $out;
}

function cloud_storage_insert_notification($db, string $email, string $severity, string $title, string $message, string $eventType, array $context): int
{
    if (!$db) return 0;
    if (function_exists('app_notifications_ensure')) app_notifications_ensure($db);
    $stmt = $db->prepare("INSERT INTO app_notifications(user_email, app_id, severity, title, message, event_type, context_json, is_read, created_at)
        VALUES(:u, 'cloud', :s, :t, :m, :e, :c, 0, :n)");
    $stmt->bindValue(':u', $email, PDO::PARAM_STR);
    $stmt->bindValue(':s', in_array($severity, ['info', 'success', 'warning', 'error', 'critical'], true) ? $severity : 'info', PDO::PARAM_STR);
    $stmt->bindValue(':t', $title, PDO::PARAM_STR);
    $stmt->bindValue(':m', $message, PDO::PARAM_STR);
    $stmt->bindValue(':e', $eventType, PDO::PARAM_STR);
    $stmt->bindValue(':c', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
    $stmt->bindValue(':n', time(), PDO::PARAM_INT);
    $stmt->execute();
    return (int)$db->lastInsertRowID();
}

/**
 * Creates a pending request and broadcasts an admin notification.
 * Returns ['ok' => bool, 'already_pending' => bool, 'request' => array].
 */
function cloud_storage_request_create($db, string $email, int $currentQuotaMb, int $usedBytes, string $note = ''): array
{
    $email = strtolower(trim($email));
    if ($email === '') return ['ok' => false, 'error' => 'not authenticated'];
    if (!$db) return ['ok' => false, 'error' => 'db unavailable'];
    cloud_storage_requests_ensure($db);

    $existing = cloud_storage_pending_request($db, $email);
    if ($existing) {
        return ['ok' => true, 'already_pending' => true, 'request' => $existing];
    }

    $now = time();
    $stmt = $db->prepare("INSERT INTO cloud_storage_requests(email, status, note, current_quota_mb, used_bytes, created_at)
        VALUES(:e, 'pending', :n, :q, :u, :t)");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':n', mb_substr(trim($note), 0, 500), PDO::PARAM_STR);
    $stmt->bindValue(':q', max(0, $currentQuotaMb), PDO::PARAM_INT);
    $stmt->bindValue(':u', max(0, $usedBytes), PDO::PARAM_INT);
    $stmt->bindValue(':t', $now, PDO::PARAM_INT);
    $stmt->execute();
    $id = (int)$db->lastInsertRowID();

    // One broadcast notification (user_email = '') visible to every admin.
    $notificationId = cloud_storage_insert_notification(
        $db,
        '',
        'warning',
        'Storage increase request',
        $email,
        'cloud_storage_request',
        [
            'request_id' => $id,
            'email' => $email,
            'current_quota_mb' => max(0, $currentQuotaMb),
            'used_bytes' => max(0, $usedBytes),
            'note' => mb_substr(trim($note), 0, 500),
        ]
    );
    $upd = $db->prepare('UPDATE cloud_storage_requests SET notification_id = :nid WHERE id = :id');
    $upd->bindValue(':nid', $notificationId, PDO::PARAM_INT);
    $upd->bindValue(':id', $id, PDO::PARAM_INT);
    $upd->execute();

    return ['ok' => true, 'already_pending' => false, 'request' => cloud_storage_request_by_id($db, $id)];
}

/** Upserts the cloud quota for a user. */
function cloud_storage_set_user_quota($db, string $email, int $quotaMb): void
{
    $email = strtolower(trim($email));
    $quotaMb = max(1, $quotaMb);
    if (!$db || $email === '') return;
    $ins = $db->prepare("INSERT INTO cloud_user_settings (email, quota_mb, session_limit_mb, hide_lang_switcher, updated_at)
        VALUES (:e, :q, 0, 0, extract(epoch from now())::bigint) ON CONFLICT (email) DO NOTHING");
    $ins->bindValue(':e', $email, PDO::PARAM_STR);
    $ins->bindValue(':q', $quotaMb, PDO::PARAM_INT);
    $ins->execute();
    $upd = $db->prepare("UPDATE cloud_user_settings SET quota_mb = :q, updated_at = extract(epoch from now())::bigint WHERE email = :e");
    $upd->bindValue(':e', $email, PDO::PARAM_STR);
    $upd->bindValue(':q', $quotaMb, PDO::PARAM_INT);
    $upd->execute();
}

/**
 * Approves or rejects a pending request.
 * The admin broadcast notification is removed as soon as the first admin
 * resolves it, and the requester receives an outcome notification.
 */
function cloud_storage_request_resolve($db, int $id, bool $approve, int $quotaMb, string $adminEmail, string $reason = ''): array
{
    if (!$db) return ['ok' => false, 'error' => 'db unavailable'];
    cloud_storage_requests_ensure($db);
    $request = cloud_storage_request_by_id($db, $id);
    if (!$request) return ['ok' => false, 'error' => 'request_not_found'];
    if ($request['status'] !== 'pending') return ['ok' => false, 'error' => 'already_resolved'];

    $adminEmail = strtolower(trim($adminEmail));
    $now = time();
    $status = $approve ? 'approved' : 'rejected';
    $granted = $approve ? max(1, $quotaMb) : 0;

    $stmt = $db->prepare("UPDATE cloud_storage_requests
        SET status = :s, granted_quota_mb = :g, reject_reason = :r, resolved_at = :t, resolved_by = :a
        WHERE id = :id AND status = 'pending'");
    $stmt->bindValue(':s', $status, PDO::PARAM_STR);
    $stmt->bindValue(':g', $granted, PDO::PARAM_INT);
    $stmt->bindValue(':r', mb_substr(trim($reason), 0, 500), PDO::PARAM_STR);
    $stmt->bindValue(':t', $now, PDO::PARAM_INT);
    $stmt->bindValue(':a', $adminEmail, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($approve) {
        cloud_storage_set_user_quota($db, $request['email'], $granted);
    }

    // Remove the admin broadcast notification(s) for this request so it
    // disappears after the first admin reviews it.
    if ((int)($request['notification_id'] ?? 0) > 0) {
        $del = $db->prepare('DELETE FROM app_notifications WHERE id = :id');
        $del->bindValue(':id', (int)$request['notification_id'], PDO::PARAM_INT);
        $del->execute();
    }
    $del2 = $db->prepare("DELETE FROM app_notifications WHERE event_type = 'cloud_storage_request' AND context_json LIKE :p");
    $del2->bindValue(':p', '%"request_id":' . $id . '%', PDO::PARAM_STR);
    $del2->execute();

    // Notify the requester about the outcome.
    if ($approve) {
        cloud_storage_insert_notification(
            $db,
            $request['email'],
            'success',
            'Storage increase approved',
            $granted . ' MB',
            'cloud_storage_request_resolved',
            ['request_id' => $id, 'status' => 'approved', 'granted_quota_mb' => $granted, 'resolved_by' => $adminEmail]
        );
    } else {
        cloud_storage_insert_notification(
            $db,
            $request['email'],
            'warning',
            'Storage increase rejected',
            mb_substr(trim($reason), 0, 500),
            'cloud_storage_request_resolved',
            ['request_id' => $id, 'status' => 'rejected', 'reason' => mb_substr(trim($reason), 0, 500), 'resolved_by' => $adminEmail]
        );
    }

    return ['ok' => true, 'request_id' => $id, 'status' => $status, 'granted_quota_mb' => $granted];
}
