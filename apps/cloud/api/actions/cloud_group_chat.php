<?php
require_once __DIR__ . '/../../includes/cloud_group_chat.php';
$mapRow = static function (array $row, string $groupId, string $viewerEmail): array {
    return cloud_group_chat_map_row($row, $groupId, $viewerEmail);
};
if ($action === 'group_chat_unread') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensureCloudGroupChatSchema($db);
    $groups = cloud_group_list_for_email($email);
    $total = 0;
    $byGroup = [];
    foreach ($groups as $g) {
        $gid = (string)$g['id'];
        $cnt = cloud_group_chat_unread_count($db, $gid, $email);
        if ($cnt > 0) $byGroup[$gid] = $cnt;
        $total += $cnt;
    }
    json_out(['ok' => true, 'count' => $total, 'by_group' => $byGroup]);
}
if ($action === 'group_chat_messages') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_GET['group_id'] ?? $_POST['group_id'] ?? '')));
    if ($groupId === '') json_out(['ok' => false, 'error' => 'group_id required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensureCloudGroupChatSchema($db);
    try {
        cloud_group_chat_require_access($db, $groupId, $email);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => 'access denied'], 403);
    }
    if ((int)($_GET['full'] ?? 0) === 1) {
        $stmt = $db->prepare('SELECT id, group_id, sender_email, message, media_file, media_type, is_deleted, is_edited, created_at, edited_at
            FROM cloud_group_chat_messages WHERE group_id = :gid ORDER BY created_at ASC LIMIT 500');
        $stmt->bindValue(':gid', $groupId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $messages = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $messages[] = $mapRow($row, $groupId, $email);
        json_out(['ok' => true, 'messages' => $messages, 'mode' => 'full', 'has_more_older' => false]);
    }
    $sinceId = (int)($_GET['since_id'] ?? 0);
    if ($sinceId > 0) {
        $stmt = $db->prepare('SELECT id, group_id, sender_email, message, media_file, media_type, is_deleted, is_edited, created_at, edited_at
            FROM cloud_group_chat_messages WHERE group_id = :gid AND id > :sid ORDER BY created_at ASC LIMIT 100');
        $stmt->bindValue(':gid', $groupId, SQLITE3_TEXT);
        $stmt->bindValue(':sid', $sinceId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $messages = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $messages[] = $mapRow($row, $groupId, $email);
        json_out(['ok' => true, 'messages' => $messages, 'mode' => 'delta']);
    }
    $chunk = min(100, max(10, (int)($_GET['limit'] ?? 30)));
    $beforeId = (int)($_GET['before_id'] ?? 0);
    if ($beforeId > 0) {
        $stmt = $db->prepare('SELECT id, group_id, sender_email, message, media_file, media_type, is_deleted, is_edited, created_at, edited_at
            FROM cloud_group_chat_messages WHERE group_id = :gid AND id < :bid ORDER BY created_at DESC LIMIT :lim');
        $stmt->bindValue(':gid', $groupId, SQLITE3_TEXT);
        $stmt->bindValue(':bid', $beforeId, SQLITE3_INTEGER);
        $stmt->bindValue(':lim', $chunk, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $rows[] = $mapRow($row, $groupId, $email);
        $messages = array_reverse($rows);
        $minId = $messages ? $messages[0]['id'] : null;
        $hasMore = false;
        if ($minId !== null) {
            $cstmt = $db->prepare('SELECT COUNT(*) AS c FROM cloud_group_chat_messages WHERE group_id = :gid AND id < :mid');
            $cstmt->bindValue(':gid', $groupId, SQLITE3_TEXT);
            $cstmt->bindValue(':mid', $minId, SQLITE3_INTEGER);
            $crow = $cstmt->execute()->fetchArray(SQLITE3_ASSOC);
            $hasMore = $crow && (int)$crow['c'] > 0;
        }
        json_out(['ok' => true, 'messages' => $messages, 'mode' => 'older', 'has_more_older' => $hasMore, 'min_id' => $minId]);
    }
    $stmt = $db->prepare('SELECT id, group_id, sender_email, message, media_file, media_type, is_deleted, is_edited, created_at, edited_at
        FROM cloud_group_chat_messages WHERE group_id = :gid ORDER BY created_at DESC LIMIT :lim');
    $stmt->bindValue(':gid', $groupId, SQLITE3_TEXT);
    $stmt->bindValue(':lim', $chunk, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) $rows[] = $mapRow($row, $groupId, $email);
    $messages = array_reverse($rows);
    $minId = null;
    $maxId = null;
    foreach ($messages as $m) {
        $minId = $minId === null ? $m['id'] : min($minId, $m['id']);
        $maxId = $maxId === null ? $m['id'] : max($maxId, $m['id']);
    }
    $hasMore = false;
    if ($minId !== null) {
        $cstmt = $db->prepare('SELECT COUNT(*) AS c FROM cloud_group_chat_messages WHERE group_id = :gid AND id < :mid');
        $cstmt->bindValue(':gid', $groupId, SQLITE3_TEXT);
        $cstmt->bindValue(':mid', $minId, SQLITE3_INTEGER);
        $crow = $cstmt->execute()->fetchArray(SQLITE3_ASSOC);
        $hasMore = $crow && (int)$crow['c'] > 0;
    }
    json_out(['ok' => true, 'messages' => $messages, 'mode' => 'initial', 'has_more_older' => $hasMore, 'min_id' => $minId, 'max_id' => $maxId]);
}
if ($action === 'group_chat_send') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_POST['group_id'] ?? '')));
    $message = trim((string)($_POST['message'] ?? ''));
    if ($groupId === '') json_out(['ok' => false, 'error' => 'group_id required'], 400);
    if ($message === '' && empty($_FILES['media'])) json_out(['ok' => false, 'error' => 'empty_message'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensureCloudGroupChatSchema($db);
    try {
        cloud_group_chat_require_access($db, $groupId, $email);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => 'access denied'], 403);
    }
    $mediaFile = null;
    $mediaType = null;
    if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $stored = cloud_group_chat_store_media($groupId, $_FILES['media']);
        if (isset($stored['error'])) json_out(['ok' => false, 'error' => $stored['error']], 400);
        $mediaFile = $stored['file'];
        $mediaType = $stored['type'];
    }
    $stmt = $db->prepare('INSERT INTO cloud_group_chat_messages (group_id, sender_email, message, media_file, media_type, created_at)
        VALUES (:gid, :sender, :msg, :mf, :mt, :ts)');
    $stmt->bindValue(':gid', $groupId, SQLITE3_TEXT);
    $stmt->bindValue(':sender', strtolower($email), SQLITE3_TEXT);
    $stmt->bindValue(':msg', $message, SQLITE3_TEXT);
    $stmt->bindValue(':mf', $mediaFile, $mediaFile ? SQLITE3_TEXT : SQLITE3_NULL);
    $stmt->bindValue(':mt', $mediaType, $mediaType ? SQLITE3_TEXT : SQLITE3_NULL);
    $stmt->bindValue(':ts', time(), SQLITE3_INTEGER);
    $stmt->execute();
    json_out(['ok' => true, 'message_id' => (int)$db->lastInsertRowID()]);
}
if ($action === 'group_chat_delete') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $messageId = (int)($input['message_id'] ?? 0);
    $groupId = strtolower(trim((string)($input['group_id'] ?? '')));
    if ($messageId <= 0 || $groupId === '') json_out(['ok' => false, 'error' => 'invalid request'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensureCloudGroupChatSchema($db);
    try {
        cloud_group_chat_require_access($db, $groupId, $email);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => 'access denied'], 403);
    }
    $stmt = $db->prepare('SELECT sender_email FROM cloud_group_chat_messages WHERE id = :id AND group_id = :gid LIMIT 1');
    $stmt->bindValue(':id', $messageId, SQLITE3_INTEGER);
    $stmt->bindValue(':gid', $groupId, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) json_out(['ok' => false, 'error' => 'message_not_found'], 404);
    if (strtolower((string)$row['sender_email']) !== strtolower($email)) json_out(['ok' => false, 'error' => 'permission_denied'], 403);
    $stmt = $db->prepare('UPDATE cloud_group_chat_messages SET is_deleted = 1 WHERE id = :id');
    $stmt->bindValue(':id', $messageId, SQLITE3_INTEGER);
    $stmt->execute();
    json_out(['ok' => true]);
}
if ($action === 'group_chat_edit') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $messageId = (int)($_POST['message_id'] ?? 0);
    $groupId = strtolower(trim((string)($_POST['group_id'] ?? '')));
    $newMessage = trim((string)($_POST['message'] ?? ''));
    if ($messageId <= 0 || $groupId === '') json_out(['ok' => false, 'error' => 'invalid request'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensureCloudGroupChatSchema($db);
    try {
        cloud_group_chat_require_access($db, $groupId, $email);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => 'access denied'], 403);
    }
    $stmt = $db->prepare('SELECT sender_email, media_file, media_type FROM cloud_group_chat_messages WHERE id = :id AND group_id = :gid LIMIT 1');
    $stmt->bindValue(':id', $messageId, SQLITE3_INTEGER);
    $stmt->bindValue(':gid', $groupId, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) json_out(['ok' => false, 'error' => 'message_not_found'], 404);
    if (strtolower((string)$row['sender_email']) !== strtolower($email)) json_out(['ok' => false, 'error' => 'permission_denied'], 403);
    $mediaFile = $row['media_file'];
    $mediaType = $row['media_type'];
    if ($newMessage === '' && empty($_FILES['media']) && empty($mediaFile)) json_out(['ok' => false, 'error' => 'empty_message'], 400);
    if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $stored = cloud_group_chat_store_media($groupId, $_FILES['media']);
        if (isset($stored['error'])) json_out(['ok' => false, 'error' => $stored['error']], 400);
        if ($mediaFile) {
            $dir = cloud_group_chat_media_dir($groupId);
            if ($dir) @unlink($dir . '/' . basename((string)$mediaFile));
        }
        $mediaFile = $stored['file'];
        $mediaType = $stored['type'];
    }
    $stmt = $db->prepare('UPDATE cloud_group_chat_messages SET message = :msg, is_edited = 1, edited_at = :edited, media_file = :mf, media_type = :mt WHERE id = :id');
    $stmt->bindValue(':msg', $newMessage, SQLITE3_TEXT);
    $stmt->bindValue(':edited', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':mf', $mediaFile, $mediaFile ? SQLITE3_TEXT : SQLITE3_NULL);
    $stmt->bindValue(':mt', $mediaType, $mediaType ? SQLITE3_TEXT : SQLITE3_NULL);
    $stmt->bindValue(':id', $messageId, SQLITE3_INTEGER);
    $stmt->execute();
    json_out(['ok' => true]);
}
if ($action === 'group_chat_mark_read') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_GET['group_id'] ?? $_POST['group_id'] ?? '')));
    $lastId = (int)($_GET['last_id'] ?? $_POST['last_id'] ?? 0);
    if ($groupId === '') json_out(['ok' => false, 'error' => 'group_id required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensureCloudGroupChatSchema($db);
    try {
        cloud_group_chat_require_access($db, $groupId, $email);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => 'access denied'], 403);
    }
    cloud_group_chat_mark_read($db, $groupId, $email, $lastId);
    json_out(['ok' => true]);
}
if ($action === 'group_chat_media') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_GET['group_id'] ?? '')));
    $file = basename((string)($_GET['file'] ?? ''));
    if ($groupId === '' || $file === '' || !preg_match('/^[a-f0-9]{32}\.[a-z0-9]+$/i', $file)) {
        json_out(['ok' => false, 'error' => 'invalid request'], 400);
    }
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensureCloudGroupChatSchema($db);
    try {
        cloud_group_chat_require_access($db, $groupId, $email);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => 'access denied'], 403);
    }
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM cloud_group_chat_messages WHERE group_id = :gid AND media_file = :f');
    $stmt->bindValue(':gid', $groupId, SQLITE3_TEXT);
    $stmt->bindValue(':f', $file, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row || (int)$row['c'] === 0) json_out(['ok' => false, 'error' => 'not found'], 404);
    $dir = cloud_group_chat_media_dir($groupId);
    if (!$dir) json_out(['ok' => false, 'error' => 'not found'], 404);
    $full = $dir . '/' . $file;
    if (!is_file($full)) json_out(['ok' => false, 'error' => 'not found'], 404);
    $mime = cloud_group_chat_detect_mime($full);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($full));
    header('Cache-Control: private, max-age=3600');
    readfile($full);
    exit;
}
