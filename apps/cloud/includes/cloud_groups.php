<?php
declare(strict_types=1);

function cloud_is_group_root(?string $rootKey): bool
{
    return is_string($rootKey) && str_starts_with($rootKey, 'group:');
}

function cloud_parse_group_id(?string $rootKey): ?string
{
    if (!cloud_is_group_root($rootKey)) return null;
    $id = substr((string)$rootKey, 6);
    return preg_match('/^[a-f0-9\-]{36}$/i', $id) ? strtolower($id) : null;
}

function cloud_group_root_key(string $groupId): string
{
    return 'group:' . strtolower($groupId);
}

function ensureCloudGroupsSchema($db): void
{
    if (function_exists('cloud_schema_once')) cloud_schema_once(__FUNCTION__, fn() => ensureCloudGroupsSchema_impl($db));
    else ensureCloudGroupsSchema_impl($db);
}
function ensureCloudGroupsSchema_impl($db): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (!$db) return;
    cloud_group_ensure_notifications($db);
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_groups (
        group_id TEXT PRIMARY KEY,
        name TEXT NOT NULL DEFAULT '',
        admin_email TEXT NOT NULL,
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        FOREIGN KEY (admin_email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_group_members (
        group_id TEXT NOT NULL,
        member_email TEXT NOT NULL,
        permission TEXT NOT NULL DEFAULT 'read_edit',
        joined_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        PRIMARY KEY (group_id, member_email),
        FOREIGN KEY (group_id) REFERENCES cloud_groups(group_id) ON DELETE CASCADE,
        FOREIGN KEY (member_email) REFERENCES users(email) ON DELETE CASCADE ON UPDATE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_group_members_email ON cloud_group_members(member_email)");
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_group_file_history (
        id BIGSERIAL PRIMARY KEY,
        group_id TEXT NOT NULL,
        file_path TEXT NOT NULL DEFAULT '',
        user_email TEXT NOT NULL DEFAULT '',
        action TEXT NOT NULL DEFAULT 'open',
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        FOREIGN KEY (group_id) REFERENCES cloud_groups(group_id) ON DELETE CASCADE
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_group_history_group_ts ON cloud_group_file_history(group_id, created_at DESC)");
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_group_notifications_lookup ON app_notifications(user_email, app_id, event_type, is_read, created_at)");
    } catch (Throwable $e) {
    }
}

function cloud_group_generate_id(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function cloud_group_storage_rel(string $groupId): string
{
    return '.cloud_groups/' . strtolower($groupId);
}

function cloud_group_get_row($db, string $groupId): ?array
{
    $stmt = $db->prepare('SELECT group_id, name, admin_email, created_at FROM cloud_groups WHERE group_id = :gid LIMIT 1');
    $stmt->bindValue(':gid', strtolower($groupId), PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function cloud_group_member_access($db, string $groupId, string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '') return null;
    $group = cloud_group_get_row($db, $groupId);
    if (!$group) return null;
    $isAdmin = strtolower((string)$group['admin_email']) === $email;
    if ($isAdmin) {
        return [
            'group_id' => (string)$group['group_id'],
            'name' => (string)$group['name'],
            'admin_email' => (string)$group['admin_email'],
            'permission' => 'read_edit',
            'is_admin' => true,
        ];
    }
    $stmt = $db->prepare('SELECT permission FROM cloud_group_members WHERE group_id = :gid AND LOWER(member_email) = :email LIMIT 1');
    $stmt->bindValue(':gid', strtolower($groupId), PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $member = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$member) return null;
    $perm = (string)($member['permission'] ?? 'read');
    if (!in_array($perm, ['read', 'read_edit'], true)) $perm = 'read';
    return [
        'group_id' => (string)$group['group_id'],
        'name' => (string)$group['name'],
        'admin_email' => (string)$group['admin_email'],
        'permission' => $perm,
        'is_admin' => false,
    ];
}

function cloud_path_db()
{
    if (function_exists('getDB')) return getDB();
    static $db = null;
    if ($db === null) {
        require_once dirname(__DIR__, 3) . '/lib/app_db.php';
        $db = app_main_db();
        if ($db) ensureCloudGroupsSchema($db);
    }
    return $db;
}
function cloud_path_ensure_storage_helper(): void
{
    if (function_exists('appdata_user_root_dir')) return;
    $helper = dirname(__DIR__, 3) . '/storage_helper.php';
    if (is_file($helper)) require_once $helper;
}
function cloud_path_session_email(): ?string
{
    if (function_exists('get_session_email')) {
        $email = get_session_email();
        if (is_string($email) && $email !== '') return strtolower(trim($email));
    }
    if (session_status() === PHP_SESSION_NONE) {
        $session = __DIR__ . '/session.php';
        if (is_readable($session)) {
            require_once $session;
            cloud_session_start();
        } else {
            @session_start();
        }
    }
    $sessionEmail = $_SESSION['user']['email'] ?? $_SESSION['email'] ?? null;
    if (is_string($sessionEmail) && $sessionEmail !== '') return strtolower(trim($sessionEmail));
    $db = cloud_path_db();
    if (!$db) return null;
    $sessionId = session_id();
    $lookup = static function (string $sid) use ($db): ?string {
        if ($sid === '') return null;
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :sid LIMIT 1');
            $stmt->bindValue(':sid', $sid, PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['email'])) return strtolower(trim((string)$row['email']));
        } catch (Throwable $e) {
        }
        return null;
    };
    $email = $lookup($sessionId);
    if ($email) return $email;
    $cookieSid = (string)($_COOKIE['PHPSESSID'] ?? '');
    if ($cookieSid !== '' && $cookieSid !== $sessionId) return $lookup($cookieSid);
    return null;
}
function cloud_path_username(string $email): string
{
    $email = strtolower(trim($email));
    if (function_exists('email_to_cloud_username')) return email_to_cloud_username($email);
    $username = str_replace(['@', '.'], '_', $email);
    $username = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username);
    return $username ?: 'guest';
}
function cloud_normalize_relative_path(string $user, string $path, ?string $rootKey = null): string
{
    $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');
    if (preg_match('#^storage/[^/]+/(.+)$#', $normalized, $m)) return $m[1];
    $safeUser = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $user);
    $storagePrefix = 'storage/' . $safeUser . '/';
    if (str_starts_with($normalized, $storagePrefix)) $normalized = substr($normalized, strlen($storagePrefix));
    if ($rootKey !== null && cloud_parse_group_id($rootKey)) {
        if (preg_match('#^\.cloud_groups/[a-f0-9\-]{36}/(.+)$#i', $normalized, $gm)) return $gm[1];
        if (preg_match('#^\.cloud_groups/[a-f0-9\-]{36}$#i', $normalized)) return '';
    }
    return $normalized;
}
function cloud_is_group_storage_main_path(string $path): bool
{
    $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');
    return (bool)preg_match('#^\.cloud_groups/[a-f0-9\-]{36}(/|$)#i', $normalized);
}
function cloud_group_storage_candidate_paths(string $groupId, string $rel, string $user = ''): array
{
    cloud_path_ensure_storage_helper();
    $paths = [];
    $addBase = static function (?string $base) use (&$paths, $rel): void {
        if (!is_string($base) || $base === '') return;
        $paths[] = $base . ($rel !== '' ? '/' . $rel : '');
    };
    $admin = cloud_group_admin_storage($groupId);
    if ($admin) $addBase($admin['base']);
    if ($user !== '' && $user !== 'default') {
        $safeUser = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $user);
        $base = appdata_user_root_dir('cloud', $safeUser, 'main', false) . '/' . cloud_group_storage_rel($groupId);
        $addBase($base);
    }
    return array_values(array_unique($paths));
}
function cloud_group_first_existing_storage_path(string $groupId, string $rel, string $user = ''): ?string
{
    foreach (cloud_group_storage_candidate_paths($groupId, $rel, $user) as $path) {
        if (file_exists($path)) return $path;
    }
    return null;
}
function cloud_resolve_onlyoffice_download_path(string $user, string $rootKey, string $path): string
{
    cloud_path_ensure_storage_helper();
    $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');
    if ($rootKey === 'main' && cloud_is_group_storage_main_path($normalized)) {
        if (preg_match('#^\.cloud_groups/([a-f0-9\-]{36})(?:/(.*))?$#i', $normalized, $m)) {
            $groupId = strtolower($m[1]);
            $rel = (string)($m[2] ?? '');
            $existing = cloud_group_first_existing_storage_path($groupId, $rel, $user);
            if ($existing) return $existing;
            $admin = cloud_group_admin_storage($groupId);
            if ($admin) {
                return $admin['base'] . ($rel !== '' ? '/' . $rel : '');
            }
        }
        return appdata_user_full_path('cloud', $user, 'main', $normalized, false);
    }
    $groupId = cloud_parse_group_id($rootKey);
    if ($groupId) {
        $rel = cloud_normalize_relative_path($user, $path, $rootKey);
        $existing = cloud_group_first_existing_storage_path($groupId, $rel, $user);
        if ($existing) return $existing;
    }
    try {
        return cloud_resolve_file_path($user, $rootKey, $path);
    } catch (Throwable $e) {
        return cloud_resolve_file_path_public($user, $rootKey, $path);
    }
}
function cloud_group_admin_storage(string $groupId): ?array
{
    cloud_path_ensure_storage_helper();
    $db = cloud_path_db();
    if (!$db) return null;
    ensureCloudGroupsSchema($db);
    $group = cloud_group_get_row($db, $groupId);
    if (!$group) return null;
    $adminUser = cloud_path_username((string)$group['admin_email']);
    $base = appdata_user_root_dir('cloud', $adminUser, 'main', false) . '/' . cloud_group_storage_rel($groupId);
    return ['admin_user' => $adminUser, 'admin_email' => (string)$group['admin_email'], 'base' => $base];
}
function cloud_resolve_file_path(string $user, string $rootKey, string $path): string
{
    cloud_path_ensure_storage_helper();
    $rel = cloud_normalize_relative_path($user, $path, $rootKey);
    $groupId = cloud_parse_group_id($rootKey);
    if ($groupId) {
        $storage = cloud_group_resolve_storage($groupId);
        return $storage['base'] . ($rel !== '' ? '/' . $rel : '');
    }
    $sessionEmail = PHP_SAPI === 'cli' ? null : cloud_path_session_email();
    if ($sessionEmail) $user = cloud_path_username($sessionEmail);
    return appdata_user_full_path('cloud', $user, $rootKey, $rel, false);
}
function cloud_resolve_file_path_public(string $user, string $rootKey, string $path): string
{
    cloud_path_ensure_storage_helper();
    $rel = cloud_normalize_relative_path($user, $path, $rootKey);
    $groupId = cloud_parse_group_id($rootKey);
    if ($groupId) {
        $admin = cloud_group_admin_storage($groupId);
        if (!$admin) throw new RuntimeException('group not found');
        $storageUser = $admin['admin_user'];
        if ($user !== '' && $user !== 'default') {
            $safeUser = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $user);
            if ($safeUser === $admin['admin_user']) $storageUser = $admin['admin_user'];
        }
        $storageRel = cloud_group_storage_rel($groupId);
        $fullRel = $rel !== '' ? $storageRel . '/' . $rel : $storageRel;
        return appdata_user_full_path('cloud', $storageUser, 'main', $fullRel, false);
    }
    return appdata_user_full_path('cloud', $user, $rootKey, $rel, false);
}
function cloud_group_document_download_params(string $rootKey, string $path): ?array
{
    $groupId = cloud_parse_group_id($rootKey);
    if (!$groupId) return null;
    $admin = cloud_group_admin_storage($groupId);
    if (!$admin) return null;
    $rel = cloud_normalize_relative_path($admin['admin_user'], $path, $rootKey);
    return [
        'user' => $admin['admin_user'],
        'root' => cloud_group_root_key($groupId),
        'path' => $rel,
    ];
}
function cloud_main_tree_hidden_prefixes(): array
{
    return ['.archive', '.temp_files', '.documents', '.photos', '.pdf', '.trash', '.cloud_groups'];
}
function cloud_is_hidden_main_tree_path(string $path): bool
{
    foreach (cloud_main_tree_hidden_prefixes() as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) return true;
    }
    return false;
}
function cloud_group_resolve_storage(string $groupId): array
{
    cloud_path_ensure_storage_helper();
    $db = cloud_path_db();
    if (!$db) throw new RuntimeException('db unavailable');
    ensureCloudGroupsSchema($db);
    $email = cloud_path_session_email();
    if (!$email) throw new RuntimeException('not authenticated');
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access) throw new RuntimeException('group access denied');
    $adminUser = cloud_path_username($access['admin_email']);
    $base = appdata_user_root_dir('cloud', $adminUser, 'main', true) . '/' . cloud_group_storage_rel($groupId);
    if (!is_dir($base)) @mkdir($base, 0775, true);
    return [
        'base' => $base,
        'admin_user' => $adminUser,
        'access' => $access,
    ];
}

function cloud_group_require_write(?string $rootKey = null): void
{
    $rootKey = $rootKey ?? get_requested_root_key();
    $groupId = cloud_parse_group_id($rootKey);
    if (!$groupId) return;
    $db = getDB();
    if (!$db) json_out(['error' => 'db unavailable'], 500);
    $email = get_session_email();
    if (!$email) json_out(['error' => 'not authenticated'], 401);
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access) json_out(['error' => 'group access denied'], 403);
    if ($access['permission'] !== 'read_edit') json_out(['error' => 'read only'], 403);
}

function cloud_group_log_activity(string $groupId, string $filePath, string $action, ?string $email = null): void
{
    $db = getDB();
    if (!$db) return;
    ensureCloudGroupsSchema($db);
    $email = $email ?? get_session_email();
    if (!$email) return;
    $allowed = ['open', 'edit', 'create', 'delete', 'move', 'rename'];
    if (!in_array($action, $allowed, true)) $action = 'open';
    $stmt = $db->prepare('INSERT INTO cloud_group_file_history (group_id, file_path, user_email, action, created_at) VALUES (:gid, :path, :email, :action, :ts)');
    $stmt->bindValue(':gid', strtolower($groupId), PDO::PARAM_STR);
    $stmt->bindValue(':path', $filePath, PDO::PARAM_STR);
    $stmt->bindValue(':email', strtolower($email), PDO::PARAM_STR);
    $stmt->bindValue(':action', $action, PDO::PARAM_STR);
    $stmt->bindValue(':ts', time(), PDO::PARAM_INT);
    $stmt->execute();
    if ($action !== 'open') cloud_group_notify_file_change($db, $groupId, $filePath, $action, $email);
}

function cloud_group_notification_title(string $email): string
{
    $db = cloud_path_db();
    $lang = 'en';
    if ($db && $email !== '') {
        try {
            $stmt = $db->prepare('SELECT language FROM users WHERE LOWER(email) = :email LIMIT 1');
            $stmt->bindValue(':email', strtolower($email), PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            $lang = strtolower((string)($row['language'] ?? 'en'));
        } catch (Throwable $e) {
        }
    }
    return str_starts_with($lang, 'ru') ? 'Файлы в группе изменены' : 'Group files changed';
}

function cloud_group_ensure_notifications($db): void
{
    if (!function_exists('app_notifications_ensure')) {
        $lib = dirname(__DIR__, 3) . '/lib/app_db.php';
        if (is_file($lib)) require_once $lib;
    }
    if (function_exists('app_notifications_ensure')) app_notifications_ensure($db);
}

function cloud_group_member_emails($db, string $groupId): array
{
    $group = cloud_group_get_row($db, $groupId);
    if (!$group) return [];
    $emails = [strtolower((string)$group['admin_email']) => true];
    $stmt = $db->prepare('SELECT member_email FROM cloud_group_members WHERE group_id = :gid');
    $stmt->bindValue(':gid', strtolower($groupId), PDO::PARAM_STR);
    $res = $stmt->execute();
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $email = strtolower(trim((string)($row['member_email'] ?? '')));
        if ($email !== '') $emails[$email] = true;
    }
    return array_keys($emails);
}

function cloud_group_find_unread_notification($db, string $email, string $groupId): ?array
{
    $stmt = $db->prepare("SELECT id, context_json FROM app_notifications WHERE user_email = :email AND app_id = 'cloud' AND event_type = 'cloud_group_files_changed' AND is_read = 0 ORDER BY created_at DESC, id DESC");
    $stmt->bindValue(':email', strtolower($email), PDO::PARAM_STR);
    $res = $stmt->execute();
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $ctx = json_decode((string)($row['context_json'] ?? '{}'), true);
        if (is_array($ctx) && strtolower((string)($ctx['group_id'] ?? '')) === strtolower($groupId)) return $row;
    }
    return null;
}

function cloud_group_notify_file_change($db, string $groupId, string $filePath, string $action, string $actorEmail): void
{
    if (!$db) return;
    cloud_group_ensure_notifications($db);
    $actorEmail = strtolower(trim($actorEmail));
    if ($actorEmail === '') return;
    $now = time();
    foreach (cloud_group_member_emails($db, $groupId) as $email) {
        if ($email === $actorEmail) continue;
        $existing = cloud_group_find_unread_notification($db, $email, $groupId);
        $context = ['group_id' => strtolower($groupId), 'last_path' => $filePath, 'last_action' => $action, 'last_actor' => $actorEmail, 'count' => 1];
        if ($existing) {
            $old = json_decode((string)($existing['context_json'] ?? '{}'), true);
            if (is_array($old)) $context['count'] = max(1, (int)($old['count'] ?? 1)) + 1;
            $stmt = $db->prepare('UPDATE app_notifications SET title = :title, message = :message, context_json = :ctx, created_at = :ts, read_at = NULL WHERE id = :id');
            $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare("INSERT INTO app_notifications(user_email, app_id, severity, title, message, event_type, context_json, is_read, created_at) VALUES(:email, 'cloud', 'info', :title, :message, 'cloud_group_files_changed', :ctx, 0, :ts)");
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        }
        $stmt->bindValue(':title', cloud_group_notification_title($email), PDO::PARAM_STR);
        $stmt->bindValue(':message', $context['count'] > 1 ? (string)$context['count'] : '', PDO::PARAM_STR);
        $stmt->bindValue(':ctx', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        $stmt->bindValue(':ts', $now, PDO::PARAM_INT);
        $stmt->execute();
    }
}

function cloud_group_mark_notifications_read($db, string $groupId, string $email): int
{
    cloud_group_ensure_notifications($db);
    $changed = 0;
    while ($row = cloud_group_find_unread_notification($db, $email, $groupId)) {
        $stmt = $db->prepare('UPDATE app_notifications SET is_read = 1, read_at = :ts WHERE id = :id');
        $stmt->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
        $stmt->bindValue(':ts', time(), PDO::PARAM_INT);
        $stmt->execute();
        $changed++;
    }
    return $changed;
}

function cloud_group_list_for_email(string $email): array
{
    $db = getDB();
    if (!$db) return [];
    ensureCloudGroupsSchema($db);
    $email = strtolower(trim($email));
    if ($email === '') return [];
    $groups = [];
    $stmt = $db->prepare('SELECT group_id, name, admin_email, created_at FROM cloud_groups WHERE LOWER(admin_email) = :email ORDER BY LOWER(name)');
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $res = $stmt->execute();
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $groups[] = [
            'id' => (string)$row['group_id'],
            'name' => (string)$row['name'],
            'admin_email' => (string)$row['admin_email'],
            'permission' => 'read_edit',
            'is_admin' => true,
            'created_at' => (int)($row['created_at'] ?? 0),
        ];
    }
    $stmt2 = $db->prepare('SELECT g.group_id, g.name, g.admin_email, g.created_at, m.permission
        FROM cloud_group_members m
        JOIN cloud_groups g ON g.group_id = m.group_id
        WHERE LOWER(m.member_email) = :email
        ORDER BY LOWER(g.name)');
    $stmt2->bindValue(':email', $email, PDO::PARAM_STR);
    $res2 = $stmt2->execute();
    while ($res2 && ($row = $res2->fetch(PDO::FETCH_ASSOC))) {
        $gid = (string)$row['group_id'];
        if (array_filter($groups, static fn($g) => $g['id'] === $gid)) continue;
        $perm = (string)($row['permission'] ?? 'read');
        if (!in_array($perm, ['read', 'read_edit'], true)) $perm = 'read';
        $groups[] = [
            'id' => $gid,
            'name' => (string)$row['name'],
            'admin_email' => (string)$row['admin_email'],
            'permission' => $perm,
            'is_admin' => false,
            'created_at' => (int)($row['created_at'] ?? 0),
        ];
    }
    return $groups;
}

function cloud_group_delete_storage(string $groupId, string $adminEmail): void
{
    $adminUser = cloud_path_username($adminEmail);
    $base = appdata_user_root_dir('cloud', $adminUser, 'main', false) . '/' . cloud_group_storage_rel($groupId);
    if (!is_dir($base)) return;
    cloud_group_rmtree($base);
}

function cloud_group_rmtree(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if (!is_array($items)) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) cloud_group_rmtree($path);
        else @unlink($path);
    }
    @rmdir($dir);
}

function cloud_group_copy_tree(string $src, string $dst): bool
{
    if (!is_dir($src)) return false;
    if (!is_dir($dst) && !@mkdir($dst, 0775, true)) return false;
    $items = scandir($src);
    if (!is_array($items)) return false;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $from = $src . '/' . $item;
        $to = $dst . '/' . $item;
        if (is_dir($from)) {
            if (!cloud_group_copy_tree($from, $to)) return false;
        } elseif (!@copy($from, $to)) {
            return false;
        }
    }
    return true;
}

function cloud_group_user_exists($db, string $email): bool
{
    $stmt = $db->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
    $stmt->bindValue(':email', strtolower(trim($email)), PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return (bool)$row;
}
