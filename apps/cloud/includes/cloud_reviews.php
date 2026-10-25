<?php
declare(strict_types=1);

function cloud_reviews_ensure_schema($db): void
{
    $make = static function () use ($db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS cloud_file_reviews (
            id BIGSERIAL PRIMARY KEY,
            scope_key TEXT NOT NULL,
            file_path TEXT NOT NULL,
            author_email TEXT NOT NULL,
            author_name TEXT NOT NULL DEFAULT '',
            start_line INTEGER NOT NULL DEFAULT 1,
            end_line INTEGER NOT NULL DEFAULT 1,
            quote TEXT NOT NULL DEFAULT '',
            comment TEXT NOT NULL DEFAULT '',
            suggestion TEXT NULL,
            status TEXT NOT NULL DEFAULT 'open',
            resolved_by TEXT NOT NULL DEFAULT '',
            resolved_at BIGINT NULL,
            created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_file_reviews_file ON cloud_file_reviews(scope_key, file_path, created_at)");
    };
    if (function_exists('cloud_schema_once')) cloud_schema_once('cloud_reviews_schema', $make);
    else $make();
}

function cloud_reviews_run($db, string $sql, array $params)
{
    $st = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : ($v === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
    }
    return $st->execute();
}

function cloud_reviews_scope(string $email, string $rootKey): string
{
    return cloud_is_group_root($rootKey) ? $rootKey : $email . '|' . $rootKey;
}

function cloud_reviews_row(array $r): array
{
    return [
        'id' => (int)$r['id'],
        'author' => (string)$r['author_name'] ?: (string)$r['author_email'],
        'mine' => !empty($r['mine']),
        'start_line' => (int)$r['start_line'],
        'end_line' => (int)$r['end_line'],
        'quote' => (string)$r['quote'],
        'comment' => (string)$r['comment'],
        'suggestion' => $r['suggestion'] === null ? null : (string)$r['suggestion'],
        'status' => (string)$r['status'],
        'resolved_by' => (string)$r['resolved_by'],
        'created_at' => (int)$r['created_at'],
    ];
}

function cloud_reviews_handle(string $action): void
{
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    cloud_reviews_ensure_schema($db);
    $user = get_current_user_from_sessions();
    $rootKey = get_requested_root_key();
    $path = safe_path((string)($_POST['path'] ?? $_GET['path'] ?? ''));
    if ($path === '') json_out(['ok' => false, 'error' => 'path required'], 400);
    if (!file_exists(user_root($user, $rootKey) . '/' . $path)) json_out(['ok' => false, 'error' => 'not found'], 404);
    $scope = cloud_reviews_scope($email, $rootKey);
    if ($action === 'review_list') {
        $res = cloud_reviews_run($db, "SELECT *, CASE WHEN author_email = :e THEN 1 ELSE 0 END AS mine FROM cloud_file_reviews WHERE scope_key = :s AND file_path = :p ORDER BY start_line, id", [':e' => $email, ':s' => $scope, ':p' => $path]);
        $items = [];
        while (($row = $res->fetch(PDO::FETCH_ASSOC)) !== null) $items[] = cloud_reviews_row($row);
        json_out(['ok' => true, 'items' => $items]);
    }
    if ($action === 'review_add') {
        $comment = trim((string)($_POST['comment'] ?? ''));
        $suggestion = isset($_POST['suggestion']) && $_POST['suggestion'] !== '' ? (string)$_POST['suggestion'] : null;
        if ($comment === '' && $suggestion === null) json_out(['ok' => false, 'error' => 'empty review'], 400);
        $start = max(1, (int)($_POST['start_line'] ?? 1));
        $end = max($start, (int)($_POST['end_line'] ?? $start));
        $res = cloud_reviews_run($db, "INSERT INTO cloud_file_reviews (scope_key, file_path, author_email, author_name, start_line, end_line, quote, comment, suggestion) VALUES (:s, :p, :e, :n, :a, :b, :q, :c, :g) RETURNING id", [':s' => $scope, ':p' => $path, ':e' => $email, ':n' => $user, ':a' => $start, ':b' => $end, ':q' => mb_substr((string)($_POST['quote'] ?? ''), 0, 4000), ':c' => mb_substr($comment, 0, 4000), ':g' => $suggestion]);
        json_out(['ok' => true, 'id' => (int)$res->fetchColumn()]);
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'review_resolve') {
        $status = ($_POST['status'] ?? '') === 'rejected' ? 'rejected' : 'accepted';
        cloud_reviews_run($db, "UPDATE cloud_file_reviews SET status = :st, resolved_by = :e, resolved_at = extract(epoch from now())::bigint WHERE id = :id AND scope_key = :s AND file_path = :p", [':st' => $status, ':e' => $user, ':id' => $id, ':s' => $scope, ':p' => $path]);
        json_out(['ok' => true, 'status' => $status]);
    }
    if ($action === 'review_delete') {
        cloud_reviews_run($db, "DELETE FROM cloud_file_reviews WHERE id = :id AND scope_key = :s AND file_path = :p AND author_email = :e", [':id' => $id, ':s' => $scope, ':p' => $path, ':e' => $email]);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false, 'error' => 'unknown action'], 400);
}
