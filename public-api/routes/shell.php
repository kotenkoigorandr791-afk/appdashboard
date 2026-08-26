<?php
require_once APP_ROOT . '/storage_helper.php';
$auth = public_api_auth_user(true);
$email = (string)$auth['email'];
$role = public_api_role();
$isAdmin = $role >= 2;
$body = public_api_body();
$action = trim((string)($body['action'] ?? $_GET['action'] ?? ''));
$shellNormalizeChatEmail = static fn($email): string => strtolower(trim((string)$email));
$shellPeerConversationKey = static function ($a, $b) use ($shellNormalizeChatEmail): array {
    $a = $shellNormalizeChatEmail($a);
    $b = $shellNormalizeChatEmail($b);
    return strcmp($a, $b) <= 0 ? [$a, $b] : [$b, $a];
};
$shellIsPeerBlocked = static function (AppDatabase $db, $a, $b) use ($shellNormalizeChatEmail): bool {
    $a = $shellNormalizeChatEmail($a);
    $b = $shellNormalizeChatEmail($b);
    if ($a === '' || $b === '') return true;
    $stmt = $db->prepare('SELECT 1 FROM chat_blocks WHERE (blocker_email = :a AND blocked_email = :b) OR (blocker_email = :b2 AND blocked_email = :a2) LIMIT 1');
    $stmt->bindValue(':a', $a, PDO::PARAM_STR);
    $stmt->bindValue(':b', $b, PDO::PARAM_STR);
    $stmt->bindValue(':b2', $b, PDO::PARAM_STR);
    $stmt->bindValue(':a2', $a, PDO::PARAM_STR);
    return (bool)$stmt->execute()->fetch(PDO::FETCH_ASSOC);
};
$shellAssertPeerVisible = static function (AppDatabase $db, $current, $other) use ($shellNormalizeChatEmail, $shellIsPeerBlocked): array {
    $current = $shellNormalizeChatEmail($current);
    $other = $shellNormalizeChatEmail($other);
    if ($other === '' || $other === $current) public_api_json(['ok' => false, 'error' => 'invalid_peer'], 400);
    $stmt = $db->prepare('SELECT email, name, photo FROM users WHERE lower(email) = :email LIMIT 1');
    $stmt->bindValue(':email', $other, PDO::PARAM_STR);
    $user = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$user) public_api_json(['ok' => false, 'error' => 'user_not_found'], 404);
    if ($shellIsPeerBlocked($db, $current, $user['email'])) public_api_json(['ok' => false, 'error' => 'peer_blocked'], 403);
    return $user;
};
if ($action === '' && isset($body['quick_command_action'])) {
    $qcMap = ['list' => 'list_quick_commands', 'create' => 'create_quick_command', 'update' => 'update_quick_command', 'delete' => 'delete_quick_command'];
    $action = $qcMap[(string)$body['quick_command_action']] ?? '';
}
if ($action === '') public_api_json(['ok' => false, 'error' => 'missing_action'], 400);

if (!function_exists('validatePasswordPolicy')) {
    function validatePasswordPolicy(string $password): ?string {
        if (mb_strlen($password) < 6) return 'password_too_short';
        if (!preg_match('/\p{L}/u', $password)) return 'password_needs_letter';
        if (!preg_match('/\d/', $password)) return 'password_needs_digit';
        return null;
    }
}
if (!function_exists('formatBytes')) {
    function formatBytes($bytes): string {
        $bytes = (float)$bytes;
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($u) - 1) { $bytes /= 1024; $i++; }
        return round($bytes, 2) . ' ' . $u[$i];
    }
}
$shellResolvePath = static function (string $requestedPath, bool $allowRoot = true): ?string {
    $baseDir = realpath(APP_ROOT);
    if ($baseDir === false) return null;
    if ($requestedPath === '' || $requestedPath === '/') {
        return $allowRoot ? $baseDir : null;
    }
    $clean = ltrim(str_replace(['../', '..\\', '..'], '', $requestedPath), '/\\');
    $target = realpath($baseDir . DIRECTORY_SEPARATOR . $clean);
    if (!$target || strpos($target, $baseDir) !== 0) return null;
    return $target;
};
$shellDeleteTree = static function (string $dir) use (&$shellDeleteTree): bool {
    if (!is_dir($dir)) return false;
    $items = @scandir($dir);
    if (!is_array($items)) return false;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full) && !is_link($full)) {
            if (!$shellDeleteTree($full)) return false;
        } else {
            if (!@unlink($full)) return false;
        }
    }
    return @rmdir($dir);
};

if ($action === 'change_password') {
    $current = (string)($body['current_password'] ?? '');
    $new = (string)($body['new_password'] ?? '');
    $confirm = (string)($body['confirm_password'] ?? '');
    $users = loadUsers();
    if (!isset($users[$email]) || !appPasswordVerify($current, (string)($users[$email]['password'] ?? ''))) {
        public_api_json(['ok' => false, 'error' => 'wrong_current_password'], 400);
    }
    if ($new !== $confirm) public_api_json(['ok' => false, 'error' => 'password_mismatch'], 400);
    $policyErr = validatePasswordPolicy($new);
    if ($policyErr !== null) public_api_json(['ok' => false, 'error' => $policyErr], 400);
    $users[$email]['password'] = appPasswordHash($new);
    saveUsers($users);
    public_api_json(['ok' => true]);
}

if ($action === 'change_photo') {
    $b64 = trim((string)($body['photo_base64'] ?? ''));
    if ($b64 === '') public_api_json(['ok' => false, 'error' => 'missing_photo'], 400);
    if (preg_match('#^data:image/[^;]+;base64,#i', $b64)) {
        $b64 = substr($b64, strpos($b64, ',') + 1);
    }
    $bin = base64_decode($b64, true);
    if ($bin === false || $bin === '') public_api_json(['ok' => false, 'error' => 'invalid_photo'], 400);
    if (strlen($bin) > 10 * 1024 * 1024) public_api_json(['ok' => false, 'error' => 'photo_too_large'], 400);
    $uploadDir = appdata_uploads_base_dir() . '/avatars/';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        public_api_json(['ok' => false, 'error' => 'upload_dir_unavailable'], 500);
    }
    $tmp = $uploadDir . 'tmp_' . bin2hex(random_bytes(8)) . '.upload';
    if (@file_put_contents($tmp, $bin) === false) public_api_json(['ok' => false, 'error' => 'photo_upload_failed'], 500);
    $imgInfo = @getimagesize($tmp);
    if (!is_array($imgInfo)) {
        @unlink($tmp);
        public_api_json(['ok' => false, 'error' => 'unsupported_image_type'], 400);
    }
    $users = loadUsers();
    $oldPhoto = (string)($users[$email]['photo'] ?? '');
    $relPath = '';
    $webpName = bin2hex(random_bytes(16)) . '.webp';
    $webpPath = $uploadDir . $webpName;
    $converted = false;
    if (function_exists('imagewebp')) {
        $type = (int)($imgInfo[2] ?? 0);
        $im = null;
        if ($type === IMAGETYPE_JPEG) $im = @imagecreatefromjpeg($tmp);
        elseif ($type === IMAGETYPE_PNG) $im = @imagecreatefrompng($tmp);
        elseif ($type === IMAGETYPE_GIF) $im = @imagecreatefromgif($tmp);
        elseif (defined('IMAGETYPE_WEBP') && $type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) $im = @imagecreatefromwebp($tmp);
        if ($im) {
            if (function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($im);
            if (function_exists('imagealphablending')) @imagealphablending($im, true);
            if (function_exists('imagesavealpha')) @imagesavealpha($im, true);
            $converted = @imagewebp($im, $webpPath, 85);
            imagedestroy($im);
        }
    }
    if ($converted && is_file($webpPath)) {
        @chmod($webpPath, 0644);
        $relPath = 'uploads/avatars/' . $webpName;
        @unlink($tmp);
    } else {
        @unlink($webpPath);
        $jpgName = bin2hex(random_bytes(16)) . '.jpg';
        $jpgPath = $uploadDir . $jpgName;
        if (!@rename($tmp, $jpgPath)) {
            @unlink($tmp);
            public_api_json(['ok' => false, 'error' => 'photo_upload_failed'], 500);
        }
        @chmod($jpgPath, 0644);
        $relPath = 'uploads/avatars/' . $jpgName;
    }
    $users[$email]['photo'] = $relPath;
    saveUsers($users);
    if ($oldPhoto !== '' && str_starts_with($oldPhoto, 'uploads/avatars/') && $oldPhoto !== $relPath) {
        $oldAbs = APP_ROOT . '/' . $oldPhoto;
        if (is_file($oldAbs)) @unlink($oldAbs);
    }
    public_api_json(['ok' => true, 'photo' => $relPath]);
}

if ($action === 'reset_photo') {
    $users = loadUsers();
    if (!isset($users[$email])) public_api_json(['ok' => false, 'error' => 'user_not_found'], 404);
    $oldPhoto = (string)($users[$email]['photo'] ?? '');
    if (str_starts_with($oldPhoto, 'uploads/avatars/')) {
        $oldAbs = APP_ROOT . '/' . $oldPhoto;
        if (is_file($oldAbs)) @unlink($oldAbs);
    }
    $users[$email]['photo'] = 'default_avatar.png';
    saveUsers($users);
    public_api_json(['ok' => true, 'photo' => 'default_avatar.png']);
}

if ($action === 'settings') {
    $users = loadUsers();
    if (!isset($users[$email])) public_api_json(['ok' => false, 'error' => 'user_not_found'], 404);
    $fields = [];
    if (array_key_exists('language', $body)) $fields['language'] = trim((string)$body['language']);
    if (array_key_exists('theme', $body)) $fields['theme'] = trim((string)$body['theme']);
    if (array_key_exists('color', $body)) {
        $color = trim((string)$body['color']);
        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) public_api_json(['ok' => false, 'error' => 'invalid_color'], 400);
        $fields['color'] = $color;
    }
    if ($fields === []) public_api_json(['ok' => false, 'error' => 'no_fields'], 400);
    foreach ($fields as $k => $v) $users[$email][$k] = $v;
    saveUsers($users);
    public_api_json(['ok' => true, 'user' => [
        'language' => $users[$email]['language'] ?? 'en',
        'theme' => $users[$email]['theme'] ?? 'light',
        'color' => $users[$email]['color'] ?? '',
    ]]);
}

if ($action === 'get_shared_files') {
    $files = $isAdmin ? loadSharedFiles(null, true) : loadSharedFiles($email);
    public_api_json(['ok' => true, 'files' => array_values($files)]);
}

if ($action === 'delete_shared_file') {
    $shareId = trim((string)($body['share_id'] ?? ''));
    if ($shareId === '') public_api_json(['ok' => false, 'error' => 'missing_share_id'], 400);
    $file = getSharedFileById($shareId);
    if (!$file || (!$isAdmin && (($file['user_email'] ?? '') !== $email))) {
        public_api_json(['ok' => false, 'error' => 'access_denied'], 403);
    }
    if (!deleteSharedFile($shareId)) public_api_json(['ok' => false, 'error' => 'delete_failed'], 500);
    public_api_json(['ok' => true]);
}

if ($action === 'update_shared_file_expiration') {
    $shareId = trim((string)($body['share_id'] ?? ''));
    $expiresOption = trim((string)($body['expires_option'] ?? 'never'));
    $allowed = [
        'never' => null,
        '1h' => 3600,
        '1d' => 86400,
        '1w' => 7 * 86400,
        '1m' => 30 * 86400,
        '3m' => 90 * 86400,
        '6m' => 180 * 86400,
        '1y' => 365 * 86400,
    ];
    if ($shareId === '' || !array_key_exists($expiresOption, $allowed)) {
        public_api_json(['ok' => false, 'error' => 'invalid_request'], 400);
    }
    $file = getSharedFileById($shareId);
    if (!$file || (!$isAdmin && (($file['user_email'] ?? '') !== $email))) {
        public_api_json(['ok' => false, 'error' => 'access_denied'], 403);
    }
    $expires = $allowed[$expiresOption] === null ? null : (time() + $allowed[$expiresOption]);
    updateSharedFileExpiration($shareId, $expires, $expiresOption);
    public_api_json(['ok' => true, 'expires' => $expires, 'expires_option' => $expiresOption]);
}

if ($action === 'send_support_message' || $action === 'send_messenger_message') {
    $message = trim((string)($body['message'] ?? ''));
    if ($message === '') public_api_json(['ok' => false, 'error' => 'empty_message'], 400);
    $db = getDB();
    if (!$db) public_api_json(['ok' => false, 'error' => 'db_unavailable'], 500);
    ensureSupportChatSchema($db);
    $recipient = $shellNormalizeChatEmail($body['recipient_email'] ?? '');
    $chatId = (string)($body['chat_id'] ?? '');
    if ($recipient === '' && str_starts_with($chatId, 'peer:')) $recipient = $shellNormalizeChatEmail(substr($chatId, 5));
    if ($action === 'send_messenger_message' && $recipient !== '') {
        if ($isAdmin) public_api_json(['ok' => false, 'error' => 'permission_denied'], 403);
        $shellAssertPeerVisible($db, $email, $recipient);
        [$chatUserEmail, $recipientEmail] = $shellPeerConversationKey($email, $recipient);
        $chatType = 'peer';
    } else {
        $chatUserEmail = $isAdmin ? $shellNormalizeChatEmail($body['user_email'] ?? '') : $shellNormalizeChatEmail($email);
        if ($chatUserEmail === '') $chatUserEmail = $shellNormalizeChatEmail($email);
        $recipientEmail = null;
        $chatType = 'support';
    }
    $stmt = $db->prepare('INSERT INTO support_chat (user_email, recipient_email, chat_type, sender_email, message, created_at) VALUES (:u, :r, :t, :s, :m, :c)');
    $stmt->bindValue(':u', $chatUserEmail, PDO::PARAM_STR);
    $stmt->bindValue(':r', $recipientEmail, $recipientEmail ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':t', $chatType, PDO::PARAM_STR);
    $stmt->bindValue(':s', $email, PDO::PARAM_STR);
    $stmt->bindValue(':m', $message, PDO::PARAM_STR);
    $stmt->bindValue(':c', time(), PDO::PARAM_INT);
    $stmt->execute();
    public_api_json(['ok' => true, 'message_id' => $db->lastInsertRowID()]);
}

if ($action === 'get_support_messages' || $action === 'get_messenger_messages') {
    $db = getDB();
    if (!$db) public_api_json(['ok' => false, 'error' => 'db_unavailable'], 500);
    ensureSupportChatSchema($db);
    $recipient = $shellNormalizeChatEmail($body['recipient_email'] ?? $_GET['recipient_email'] ?? '');
    $chatId = (string)($body['chat_id'] ?? $_GET['chat_id'] ?? '');
    if ($recipient === '' && str_starts_with($chatId, 'peer:')) $recipient = $shellNormalizeChatEmail(substr($chatId, 5));
    if ($action === 'get_messenger_messages' && $recipient !== '') {
        if ($isAdmin) public_api_json(['ok' => false, 'error' => 'permission_denied'], 403);
        $shellAssertPeerVisible($db, $email, $recipient);
        [$chatUserEmail, $recipientEmail] = $shellPeerConversationKey($email, $recipient);
        $chatType = 'peer';
    } else {
        $chatUserEmail = $isAdmin ? $shellNormalizeChatEmail($body['user_email'] ?? $_GET['user_email'] ?? '') : $shellNormalizeChatEmail($email);
        if ($chatUserEmail === '') $chatUserEmail = $shellNormalizeChatEmail($email);
        $recipientEmail = null;
        $chatType = 'support';
    }
    $stmt = $db->prepare('SELECT id, user_email, recipient_email, chat_type, sender_email, message, media_path, media_type, is_deleted, is_edited, is_read, created_at, edited_at FROM support_chat WHERE chat_type = :t AND user_email = :e AND ' . ($chatType === 'peer' ? 'recipient_email = :r' : 'recipient_email IS NULL') . ' ORDER BY created_at DESC LIMIT 100');
    $stmt->bindValue(':t', $chatType, PDO::PARAM_STR);
    $stmt->bindValue(':e', $chatUserEmail, PDO::PARAM_STR);
    if ($chatType === 'peer') $stmt->bindValue(':r', $recipientEmail, PDO::PARAM_STR);
    $res = $stmt->execute();
    $rows = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;
    public_api_json(['ok' => true, 'messages' => array_reverse($rows)]);
}

if ($action === 'list_messenger_chats') {
    $db = getDB();
    if (!$db) public_api_json(['ok' => false, 'error' => 'db_unavailable'], 500);
    ensureSupportChatSchema($db);
    if ($isAdmin) {
        public_api_json(['ok' => true, 'chats' => [['chat_id' => 'support', 'chat_type' => 'support', 'user_email' => $shellNormalizeChatEmail($email), 'recipient_email' => null]]]);
    }
    $me = $shellNormalizeChatEmail($email);
    $stmt = $db->prepare("SELECT user_email, recipient_email, MAX(created_at) AS last_message_time, COUNT(*) AS message_count FROM support_chat WHERE chat_type = 'peer' AND (user_email = :me OR recipient_email = :me2) GROUP BY user_email, recipient_email ORDER BY last_message_time DESC");
    $stmt->bindValue(':me', $me, PDO::PARAM_STR);
    $stmt->bindValue(':me2', $me, PDO::PARAM_STR);
    $res = $stmt->execute();
    $chats = [['chat_id' => 'support', 'chat_type' => 'support', 'user_email' => $me, 'recipient_email' => null]];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $row['chat_type'] = 'peer';
        $row['peer_email'] = $shellNormalizeChatEmail($row['user_email']) === $me ? $row['recipient_email'] : $row['user_email'];
        $row['chat_id'] = 'peer:' . $row['peer_email'];
        $chats[] = $row;
    }
    public_api_json(['ok' => true, 'chats' => $chats]);
}

$adminActions = [
    'get_app_access_levels','set_app_access_level',
    'fm_list_dir','fm_get_file','fm_save_file','fm_mkdir','fm_mkfile','fm_delete','fm_rename','fm_dir_size',
    'list_json_files','get_json_file','save_json_file',
    'list_quick_commands','create_quick_command','update_quick_command','delete_quick_command',
    'list_alert_rules','create_alert_rule','delete_alert_rule','list_alerts','get_metrics_hourly',
];
if (!in_array($action, $adminActions, true)) {
    public_api_json(['ok' => false, 'error' => 'unknown_action'], 404);
}
public_api_require_role(2);

if ($action === 'get_app_access_levels') {
    public_api_json(['ok' => true, 'levels' => loadAppAccessLevels()]);
}

if ($action === 'set_app_access_level') {
    $appId = trim((string)($body['app_id'] ?? ''));
    $minRole = max(0, min(2, (int)($body['min_role'] ?? 2)));
    if ($appId === '') public_api_json(['ok' => false, 'error' => 'missing_app_id'], 400);
    $levels = loadAppAccessLevels();
    $levels[$appId] = $minRole;
    saveAppAccessLevelsFromMap($levels);
    public_api_json(['ok' => true, 'app_id' => $appId, 'min_role' => getAppMinRole($appId)]);
}

if ($action === 'fm_list_dir') {
    $requestedPath = (string)($body['path'] ?? '/');
    $baseDir = realpath(APP_ROOT);
    $targetDir = $shellResolvePath($requestedPath);
    if (!$targetDir || !is_dir($targetDir)) $targetDir = $baseDir;
    $relPath = substr($targetDir, strlen($baseDir));
    $relPath = str_replace('\\', '/', $relPath);
    if ($relPath === '') $relPath = '/';
    $entries = [];
    foreach (@scandir($targetDir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $targetDir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($full);
        $isSymlink = is_link($full);
        $ext = $isDir ? '' : strtolower(pathinfo($item, PATHINFO_EXTENSION));
        $realTarget = $isSymlink ? @realpath($full) : null;
        $realExt = ($isSymlink && $realTarget) ? strtolower(pathinfo($realTarget, PATHINFO_EXTENSION)) : $ext;
        $size = ($isDir && !$isSymlink) ? 0 : (file_exists($full) ? filesize($full) : 0);
        $entries[] = [
            'name' => $item,
            'type' => $isSymlink ? 'symlink' : ($isDir ? 'dir' : 'file'),
            'ext' => $realExt,
            'is_symlink' => $isSymlink,
            'size' => $size,
            'size_h' => ($isDir && !$isSymlink) ? '' : formatBytes($size),
            'mtime' => date('Y-m-d H:i', @filemtime($full)),
            'path' => $relPath . ($relPath === '/' ? '' : '/') . $item,
            'target' => $isSymlink ? ($realTarget ? str_replace($baseDir, '', $realTarget) : '?') : null,
        ];
    }
    usort($entries, static function ($a, $b) {
        if ($a['type'] !== $b['type']) return $a['type'] === 'dir' ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });
    $crumbs = [['name' => 'root', 'path' => '/']];
    if ($relPath !== '/') {
        $acc = '';
        foreach (explode('/', trim($relPath, '/')) as $p) {
            $acc .= '/' . $p;
            $crumbs[] = ['name' => $p, 'path' => $acc];
        }
    }
    public_api_json(['ok' => true, 'path' => $relPath, 'entries' => $entries, 'breadcrumb' => $crumbs]);
}

if ($action === 'fm_get_file') {
    $targetFile = $shellResolvePath((string)($body['path'] ?? ''), false);
    if (!$targetFile || !is_file($targetFile)) public_api_json(['ok' => false, 'error' => 'file_not_found'], 404);
    $size = filesize($targetFile);
    if ($size > 2 * 1024 * 1024) public_api_json(['ok' => false, 'error' => 'file_too_large'], 400);
    $content = file_get_contents($targetFile);
    $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $binaryExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp', 'svg', 'pdf', 'zip', 'gz', 'tar', 'exe', 'dll', 'bin'];
    $isBinary = in_array($ext, $binaryExts, true);
    public_api_json(['ok' => true, 'content' => $isBinary ? base64_encode((string)$content) : $content, 'is_binary' => $isBinary, 'ext' => $ext, 'size' => $size, 'size_h' => formatBytes($size)]);
}

if ($action === 'fm_save_file') {
    $targetFile = $shellResolvePath((string)($body['path'] ?? ''), false);
    if (!$targetFile || !is_file($targetFile)) public_api_json(['ok' => false, 'error' => 'file_not_found'], 404);
    $content = (string)($body['content'] ?? '');
    if (file_put_contents($targetFile, $content) === false) public_api_json(['ok' => false, 'error' => 'write_failed'], 500);
    public_api_json(['ok' => true]);
}

if ($action === 'fm_mkdir' || $action === 'fm_mkfile') {
    $rawPath = trim((string)($body['path'] ?? '/'));
    $newName = trim((string)($body['name'] ?? ''));
    if ($newName === '') {
        $newName = basename(str_replace('\\', '/', $rawPath));
        $parentRaw = dirname(str_replace('\\', '/', $rawPath));
        if ($parentRaw === '.' || $parentRaw === '') $parentRaw = '/';
    } else {
        $parentRaw = $rawPath === '' ? '/' : $rawPath;
    }
    $parentDir = $shellResolvePath($parentRaw);
    if (!$parentDir || !is_dir($parentDir)) public_api_json(['ok' => false, 'error' => 'invalid_parent'], 400);
    if ($newName === '' || preg_match('/[<>:"\\/\\\\|?*]/', $newName)) public_api_json(['ok' => false, 'error' => 'invalid_name'], 400);
    $newPath = $parentDir . DIRECTORY_SEPARATOR . $newName;
    if ($action === 'fm_mkdir') {
        if (is_dir($newPath)) public_api_json(['ok' => false, 'error' => 'already_exists'], 409);
        if (!mkdir($newPath, 0755, true)) public_api_json(['ok' => false, 'error' => 'mkdir_failed'], 500);
        public_api_json(['ok' => true, 'path' => $parentRaw . ($parentRaw === '/' ? '' : '/') . $newName]);
    }
    if (file_exists($newPath)) public_api_json(['ok' => false, 'error' => 'already_exists'], 409);
    $content = (string)($body['content'] ?? '');
    if (file_put_contents($newPath, $content) === false) public_api_json(['ok' => false, 'error' => 'create_failed'], 500);
    public_api_json(['ok' => true, 'path' => $parentRaw . ($parentRaw === '/' ? '' : '/') . $newName]);
}

if ($action === 'fm_delete') {
    $baseDir = realpath(APP_ROOT);
    $targetPath = $shellResolvePath((string)($body['path'] ?? ''), false);
    if (!$targetPath || $targetPath === $baseDir) public_api_json(['ok' => false, 'error' => 'access_denied'], 403);
    if (is_file($targetPath)) $ok = @unlink($targetPath);
    elseif (is_dir($targetPath)) {
        if (function_exists('deleteDirectory')) deleteDirectory($targetPath);
        else $shellDeleteTree($targetPath);
        $ok = !is_dir($targetPath);
    } else $ok = false;
    if (!$ok) public_api_json(['ok' => false, 'error' => 'delete_failed'], 500);
    public_api_json(['ok' => true]);
}

if ($action === 'fm_rename') {
    $baseDir = realpath(APP_ROOT);
    $targetPath = $shellResolvePath((string)($body['path'] ?? ''), false);
    $newName = basename((string)($body['new_name'] ?? $body['new_path'] ?? ''));
    if (!$targetPath || $targetPath === $baseDir) public_api_json(['ok' => false, 'error' => 'access_denied'], 403);
    if ($newName === '' || preg_match('/[<>:"\\/\\\\|?*]/', $newName)) public_api_json(['ok' => false, 'error' => 'invalid_name'], 400);
    $newPath = dirname($targetPath) . DIRECTORY_SEPARATOR . $newName;
    if (file_exists($newPath) && $newPath !== $targetPath) public_api_json(['ok' => false, 'error' => 'already_exists'], 409);
    if (!@rename($targetPath, $newPath)) public_api_json(['ok' => false, 'error' => 'rename_failed'], 500);
    public_api_json(['ok' => true]);
}

if ($action === 'fm_dir_size') {
    $targetDir = $shellResolvePath((string)($body['path'] ?? '/'));
    if (!$targetDir || !is_dir($targetDir)) public_api_json(['ok' => false, 'error' => 'not_found'], 404);
    $size = 0;
    $count = 0;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $item) {
            if ($item->isFile()) { $size += $item->getSize(); $count++; }
        }
    } catch (Throwable $e) {}
    public_api_json(['ok' => true, 'size' => $size, 'size_h' => formatBytes($size), 'files' => $count]);
}

if ($action === 'list_json_files') {
    $files = [];
    foreach (glob(APP_ROOT . '/*.json') ?: [] as $filepath) {
        $size = filesize($filepath);
        $files[] = [
            'name' => basename($filepath),
            'size' => $size,
            'size_human' => formatBytes($size),
            'mtime' => date('Y-m-d H:i:s', filemtime($filepath)),
        ];
    }
    usort($files, static fn($a, $b) => strcmp($a['name'], $b['name']));
    public_api_json(['ok' => true, 'files' => $files]);
}

if ($action === 'get_json_file') {
    $rel = ltrim(str_replace(['../', '..\\', '..'], '', (string)($body['path'] ?? $body['filename'] ?? '')), '/\\');
    if ($rel === '' || !str_ends_with(strtolower($rel), '.json')) public_api_json(['ok' => false, 'error' => 'invalid_path'], 400);
    $base = realpath(APP_ROOT);
    $path = $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    $real = realpath($path);
    if (!$real || !$base || strpos($real, $base) !== 0 || !is_file($real)) public_api_json(['ok' => false, 'error' => 'file_not_found'], 404);
    public_api_json(['ok' => true, 'content' => file_get_contents($real), 'path' => substr($real, strlen($base) + 1)]);
}

if ($action === 'save_json_file') {
    $rel = ltrim(str_replace(['../', '..\\', '..'], '', (string)($body['path'] ?? $body['filename'] ?? '')), '/\\');
    $contentRaw = (string)($body['content'] ?? '');
    if ($rel === '' || !str_ends_with(strtolower($rel), '.json')) public_api_json(['ok' => false, 'error' => 'invalid_path'], 400);
    $parsed = json_decode($contentRaw);
    if (json_last_error() !== JSON_ERROR_NONE) public_api_json(['ok' => false, 'error' => 'json_parse_error', 'detail' => json_last_error_msg()], 400);
    $base = realpath(APP_ROOT);
    $path = $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    $parent = dirname($path);
    $parentReal = realpath($parent);
    if (!$base || !$parentReal || strpos($parentReal, $base) !== 0) public_api_json(['ok' => false, 'error' => 'invalid_path'], 400);
    $pretty = json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($path, $pretty) === false) public_api_json(['ok' => false, 'error' => 'write_failed'], 500);
    public_api_json(['ok' => true, 'path' => $rel]);
}

$qcActions = ['list_quick_commands', 'create_quick_command', 'update_quick_command', 'delete_quick_command'];
if (in_array($action, $qcActions, true) || isset($body['quick_command_action'])) {
    $db = getDB();
    if (!$db) public_api_json(['ok' => false, 'error' => 'db_unavailable'], 500);
    $db->exec("CREATE TABLE IF NOT EXISTS quick_commands (
        id BIGSERIAL PRIMARY KEY,
        email TEXT NOT NULL,
        name TEXT NOT NULL DEFAULT '',
        command TEXT NOT NULL DEFAULT '',
        directory TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
    )");
    $qcAction = $action;
    if (isset($body['quick_command_action'])) {
        $map = ['list' => 'list_quick_commands', 'create' => 'create_quick_command', 'update' => 'update_quick_command', 'delete' => 'delete_quick_command'];
        $qcAction = $map[(string)$body['quick_command_action']] ?? $action;
    }
    if ($qcAction === 'list_quick_commands') {
        $stmt = $db->prepare('SELECT id, name, command, directory, created_at, updated_at FROM quick_commands WHERE email = :email ORDER BY name ASC');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $res = $stmt->execute();
        $commands = [];
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) $commands[] = $row;
        public_api_json(['ok' => true, 'commands' => $commands]);
    }
    if ($qcAction === 'create_quick_command') {
        $name = trim((string)($body['name'] ?? ''));
        $command = trim((string)($body['command'] ?? ''));
        $directory = trim((string)($body['directory'] ?? ''));
        if ($name === '' || $command === '') public_api_json(['ok' => false, 'error' => 'missing_fields'], 400);
        $stmt = $db->prepare('INSERT INTO quick_commands (email, name, command, directory) VALUES (:email, :name, :command, :directory)');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':command', $command, PDO::PARAM_STR);
        $stmt->bindValue(':directory', $directory, PDO::PARAM_STR);
        $stmt->execute();
        public_api_json(['ok' => true, 'id' => $db->lastInsertRowID()]);
    }
    if ($qcAction === 'update_quick_command') {
        $id = (int)($body['id'] ?? 0);
        $name = trim((string)($body['name'] ?? ''));
        $command = trim((string)($body['command'] ?? ''));
        $directory = trim((string)($body['directory'] ?? ''));
        if ($id <= 0 || $name === '' || $command === '') public_api_json(['ok' => false, 'error' => 'invalid_data'], 400);
        $stmt = $db->prepare('UPDATE quick_commands SET name = :name, command = :command, directory = :directory, updated_at = extract(epoch from now())::bigint WHERE id = :id AND email = :email');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':command', $command, PDO::PARAM_STR);
        $stmt->bindValue(':directory', $directory, PDO::PARAM_STR);
        $stmt->execute();
        public_api_json(['ok' => true]);
    }
    if ($qcAction === 'delete_quick_command') {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
        $stmt = $db->prepare('DELETE FROM quick_commands WHERE id = :id AND email = :email');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        public_api_json(['ok' => true]);
    }
}

$db = getDB();
if (!$db) public_api_json(['ok' => false, 'error' => 'db_unavailable'], 500);
$db->exec("CREATE TABLE IF NOT EXISTS monitor_alert_rules (
    id BIGSERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    metric TEXT NOT NULL,
    operator TEXT NOT NULL,
    threshold REAL NOT NULL,
    duration_sec INTEGER NOT NULL DEFAULT 300,
    severity TEXT NOT NULL DEFAULT 'warning',
    enabled INTEGER NOT NULL DEFAULT 1,
    notify_channels TEXT NOT NULL DEFAULT '[]',
    created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
    updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
)");
$db->exec("CREATE TABLE IF NOT EXISTS monitor_alerts (
    id BIGSERIAL PRIMARY KEY,
    rule_id INTEGER,
    opened_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
    closed_at INTEGER,
    severity TEXT NOT NULL DEFAULT 'warning',
    status TEXT NOT NULL DEFAULT 'open',
    title TEXT NOT NULL,
    message TEXT NOT NULL DEFAULT '',
    value REAL,
    context_json TEXT NOT NULL DEFAULT '{}'
)");
$db->exec("CREATE TABLE IF NOT EXISTS monitor_metric_hourly (
    metric_hour INTEGER NOT NULL,
    metric TEXT NOT NULL,
    app_id TEXT NOT NULL DEFAULT '',
    min_value REAL NOT NULL DEFAULT 0,
    avg_value REAL NOT NULL DEFAULT 0,
    max_value REAL NOT NULL DEFAULT 0,
    sample_count INTEGER NOT NULL DEFAULT 0,
    updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
    PRIMARY KEY (metric_hour, metric, app_id)
)");

if ($action === 'list_alert_rules') {
    $res = $db->query('SELECT * FROM monitor_alert_rules ORDER BY id ASC');
    $rules = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) $rules[] = $row;
    public_api_json(['ok' => true, 'rules' => $rules]);
}

if ($action === 'create_alert_rule') {
    $name = trim((string)($body['name'] ?? ''));
    $metric = trim((string)($body['metric'] ?? ''));
    $operator = trim((string)($body['operator'] ?? ''));
    $threshold = (float)($body['threshold'] ?? 0);
    if ($name === '' || $metric === '' || $operator === '') public_api_json(['ok' => false, 'error' => 'missing_fields'], 400);
    $stmt = $db->prepare('INSERT INTO monitor_alert_rules (name, metric, operator, threshold, duration_sec, severity, enabled, notify_channels) VALUES (:n, :m, :o, :t, :d, :s, :e, :c)');
    $stmt->bindValue(':n', $name, PDO::PARAM_STR);
    $stmt->bindValue(':m', $metric, PDO::PARAM_STR);
    $stmt->bindValue(':o', $operator, PDO::PARAM_STR);
    $stmt->bindValue(':t', $threshold);
    $stmt->bindValue(':d', (int)($body['duration_sec'] ?? 300), PDO::PARAM_INT);
    $stmt->bindValue(':s', trim((string)($body['severity'] ?? 'warning')), PDO::PARAM_STR);
    $stmt->bindValue(':e', !empty($body['enabled']) || !array_key_exists('enabled', $body) ? 1 : 0, PDO::PARAM_INT);
    $channels = $body['notify_channels'] ?? [];
    $stmt->bindValue(':c', is_string($channels) ? $channels : json_encode($channels), PDO::PARAM_STR);
    $stmt->execute();
    public_api_json(['ok' => true, 'id' => $db->lastInsertRowID()]);
}

if ($action === 'delete_alert_rule') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
    $stmt = $db->prepare('DELETE FROM monitor_alert_rules WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    public_api_json(['ok' => true]);
}

if ($action === 'list_alerts') {
    $res = $db->query('SELECT * FROM monitor_alerts ORDER BY opened_at DESC LIMIT 200');
    $alerts = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) $alerts[] = $row;
    public_api_json(['ok' => true, 'alerts' => $alerts]);
}

if ($action === 'get_metrics_hourly') {
    $since = (int)($body['since'] ?? $_GET['since'] ?? (time() - 7 * 86400));
    $metric = trim((string)($body['metric'] ?? $_GET['metric'] ?? ''));
    if ($metric !== '') {
        $stmt = $db->prepare('SELECT * FROM monitor_metric_hourly WHERE metric_hour >= :s AND metric = :m ORDER BY metric_hour ASC');
        $stmt->bindValue(':s', $since, PDO::PARAM_INT);
        $stmt->bindValue(':m', $metric, PDO::PARAM_STR);
    } else {
        $stmt = $db->prepare('SELECT * FROM monitor_metric_hourly WHERE metric_hour >= :s ORDER BY metric_hour ASC, metric ASC');
        $stmt->bindValue(':s', $since, PDO::PARAM_INT);
    }
    $res = $stmt->execute();
    $rows = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;
    public_api_json(['ok' => true, 'metrics' => $rows]);
}

public_api_json(['ok' => false, 'error' => 'unknown_shell_action'], 404);
