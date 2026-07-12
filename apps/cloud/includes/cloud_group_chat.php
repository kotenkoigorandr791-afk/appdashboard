<?php
declare(strict_types=1);
require_once __DIR__ . '/cloud_groups.php';
function cloud_group_chat_media_folder(): string
{
    return '.chat_media';
}
function cloud_group_chat_media_rel(string $groupId): string
{
    return cloud_group_storage_rel($groupId) . '/' . cloud_group_chat_media_folder();
}
function cloud_is_hidden_group_tree_path(string $path): bool
{
    $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');
    $folder = cloud_group_chat_media_folder();
    return $normalized === $folder || str_starts_with($normalized, $folder . '/');
}
function ensureCloudGroupChatSchema($db): void
{
    if (!$db) return;
    ensureCloudGroupsSchema($db);
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_group_chat_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id TEXT NOT NULL,
        sender_email TEXT NOT NULL,
        message TEXT NOT NULL DEFAULT '',
        media_file TEXT,
        media_type TEXT,
        is_deleted INTEGER NOT NULL DEFAULT 0,
        is_edited INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        edited_at INTEGER,
        FOREIGN KEY (group_id) REFERENCES cloud_groups(group_id) ON DELETE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_group_chat_group_ts ON cloud_group_chat_messages(group_id, created_at DESC)");
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_group_chat_read (
        group_id TEXT NOT NULL,
        member_email TEXT NOT NULL,
        last_read_id INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (group_id, member_email),
        FOREIGN KEY (group_id) REFERENCES cloud_groups(group_id) ON DELETE CASCADE
    )");
}
function cloud_group_chat_require_access($db, string $groupId, string $email): array
{
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access) throw new RuntimeException('group access denied');
    return $access;
}
function cloud_group_chat_map_row(array $row, string $groupId, string $viewerEmail): array
{
    $mediaFile = (string)($row['media_file'] ?? '');
    $mediaUrl = null;
    if ($mediaFile !== '') {
        $mediaUrl = cloud_group_chat_media_url($groupId, $mediaFile);
    }
    $sender = strtolower((string)($row['sender_email'] ?? ''));
    return [
        'id' => (int)$row['id'],
        'group_id' => (string)$row['group_id'],
        'sender_email' => $sender,
        'message' => (string)($row['message'] ?? ''),
        'media_file' => $mediaFile !== '' ? $mediaFile : null,
        'media_url' => $mediaUrl,
        'media_type' => $row['media_type'] ? (string)$row['media_type'] : null,
        'is_deleted' => (bool)($row['is_deleted'] ?? 0),
        'is_edited' => (bool)($row['is_edited'] ?? 0),
        'created_at' => (int)($row['created_at'] ?? 0),
        'edited_at' => isset($row['edited_at']) && $row['edited_at'] ? (int)$row['edited_at'] : null,
        'is_own' => $sender === strtolower($viewerEmail),
    ];
}
function cloud_group_chat_media_url(string $groupId, string $fileName): string
{
    $base = (string)(($_SERVER['SCRIPT_NAME'] ?? '') !== '' ? dirname((string)$_SERVER['SCRIPT_NAME']) : '');
    if ($base === '/' || $base === '.' || $base === '') $api = '/api.php';
    else $api = rtrim($base, '/') . '/api.php';
    return $api . '?action=group_chat_media&group_id=' . rawurlencode(strtolower($groupId)) . '&file=' . rawurlencode(basename($fileName));
}
function cloud_group_chat_media_dir(string $groupId): ?string
{
    $admin = cloud_group_admin_storage($groupId);
    if (!$admin) return null;
    $dir = $admin['base'] . '/' . cloud_group_chat_media_folder();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return null;
    return $dir;
}
function cloud_group_chat_random_filename(string $ext): string
{
    return bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
}
function cloud_group_chat_detect_mime(string $tmp): string
{
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $mime = finfo_file($f, $tmp);
            finfo_close($f);
            if (is_string($mime) && $mime !== '') return $mime;
        }
    }
    $mime = @mime_content_type($tmp);
    return is_string($mime) ? $mime : 'application/octet-stream';
}
function cloud_group_chat_store_media(string $groupId, array $file): array
{
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 20 * 1024 * 1024) return ['error' => 'file_too_large'];
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return ['error' => 'invalid_upload'];
    $mime = cloud_group_chat_detect_mime($tmp);
    $origName = (string)($file['name'] ?? '');
    $origExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $imageMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $videoMimes = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
    $audioMimes = ['audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/webm' => 'weba', 'audio/mp4' => 'm4a'];
    $kind = null;
    $ext = null;
    if ($mime === 'video/webm' && in_array($origExt, ['weba', 'opus'], true)) { $kind = 'audio'; $ext = 'weba'; }
    elseif (isset($imageMimes[$mime])) { $kind = 'image'; $ext = $imageMimes[$mime]; }
    elseif (isset($videoMimes[$mime])) { $kind = 'video'; $ext = $videoMimes[$mime]; }
    elseif (isset($audioMimes[$mime])) { $kind = 'audio'; $ext = $audioMimes[$mime]; }
    else return ['error' => 'unsupported_type'];
    if ($kind === 'image') {
        $info = @getimagesize($tmp);
        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!is_array($info) || !in_array((int)($info[2] ?? 0), $allowed, true)) return ['error' => 'unsupported_type'];
    }
    $uploadDir = cloud_group_chat_media_dir($groupId);
    if (!$uploadDir) return ['error' => 'storage_failed'];
    $newFileName = cloud_group_chat_random_filename($ext ?? 'bin');
    $destPath = $uploadDir . '/' . $newFileName;
    if (!move_uploaded_file($tmp, $destPath)) return ['error' => 'move_failed'];
    @chmod($destPath, 0644);
    return ['file' => $newFileName, 'type' => $kind];
}
function cloud_group_chat_unread_count($db, string $groupId, string $email): int
{
    $email = strtolower(trim($email));
    $stmt = $db->prepare('SELECT last_read_id FROM cloud_group_chat_read WHERE group_id = :gid AND member_email = :email LIMIT 1');
    $stmt->bindValue(':gid', strtolower($groupId), SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $lastRead = (int)($row['last_read_id'] ?? 0);
    $cstmt = $db->prepare('SELECT COUNT(*) AS c FROM cloud_group_chat_messages WHERE group_id = :gid AND id > :lid AND LOWER(sender_email) != :email AND COALESCE(is_deleted,0)=0');
    $cstmt->bindValue(':gid', strtolower($groupId), SQLITE3_TEXT);
    $cstmt->bindValue(':lid', $lastRead, SQLITE3_INTEGER);
    $cstmt->bindValue(':email', $email, SQLITE3_TEXT);
    $crow = $cstmt->execute()->fetchArray(SQLITE3_ASSOC);
    return (int)($crow['c'] ?? 0);
}
function cloud_group_chat_mark_read($db, string $groupId, string $email, int $lastId): void
{
    $email = strtolower(trim($email));
    $stmt = $db->prepare('INSERT INTO cloud_group_chat_read (group_id, member_email, last_read_id) VALUES (:gid, :email, :lid)
        ON CONFLICT(group_id, member_email) DO UPDATE SET last_read_id = CASE WHEN excluded.last_read_id > cloud_group_chat_read.last_read_id THEN excluded.last_read_id ELSE cloud_group_chat_read.last_read_id END');
    $stmt->bindValue(':gid', strtolower($groupId), SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':lid', max(0, $lastId), SQLITE3_INTEGER);
    $stmt->execute();
}
