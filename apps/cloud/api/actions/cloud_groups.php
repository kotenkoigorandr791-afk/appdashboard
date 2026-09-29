<?php
require_once __DIR__ . '/../../includes/cloud_groups.php';

if ($action === 'groups_list') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    try {
        $groups = cloud_group_list_for_email($email);
        json_out(['ok' => true, 'groups' => $groups]);
    } catch (Throwable $e) {
        cloud_safe_error_log('groups_list failed: ' . $e->getMessage());
        json_out(['ok' => false, 'error' => 'groups list failed'], 500);
    }
}

if ($action === 'groups_mark_notifications_read') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_POST['group_id'] ?? $_GET['group_id'] ?? '')));
    if ($groupId === '') json_out(['ok' => false, 'error' => 'group_id required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    if (!cloud_group_member_access($db, $groupId, $email)) json_out(['ok' => false, 'error' => 'access denied'], 403);
    $changed = cloud_group_mark_notifications_read($db, $groupId, $email);
    json_out(['ok' => true, 'changed' => $changed]);
}

if ($action === 'groups_create') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $email = strtolower(trim($email));
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensureCloudGroupsSchema($db);
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') json_out(['ok' => false, 'error' => 'name required'], 400);
    if (mb_strlen($name) > 120) json_out(['ok' => false, 'error' => 'name too long'], 400);
    $groupId = cloud_group_generate_id();
    $now = time();
    $stmt = $db->prepare('INSERT INTO cloud_groups (group_id, name, admin_email, created_at) VALUES (:gid, :name, :email, :ts)');
    $stmt->bindValue(':gid', $groupId, PDO::PARAM_STR);
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':ts', $now, PDO::PARAM_INT);
    if (!$stmt->execute()) json_out(['ok' => false, 'error' => 'create failed'], 500);
    $adminUser = cloud_path_username($email);
    $base = appdata_user_root_dir('cloud', $adminUser, 'main', true) . '/' . cloud_group_storage_rel($groupId);
    if (!is_dir($base)) @mkdir($base, 0775, true);
    json_out(['ok' => true, 'group' => [
        'id' => $groupId,
        'name' => $name,
        'admin_email' => $email,
        'permission' => 'read_edit',
        'is_admin' => true,
        'created_at' => $now,
        'root' => cloud_group_root_key($groupId),
    ]]);
}

if ($action === 'groups_members') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_GET['group_id'] ?? $_POST['group_id'] ?? '')));
    if ($groupId === '') json_out(['ok' => false, 'error' => 'group_id required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access || !$access['is_admin']) json_out(['ok' => false, 'error' => 'admin only'], 403);
    $members = [];
    $stmt = $db->prepare('SELECT member_email, permission, joined_at FROM cloud_group_members WHERE group_id = :gid ORDER BY joined_at ASC');
    $stmt->bindValue(':gid', $groupId, PDO::PARAM_STR);
    $res = $stmt->execute();
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $members[] = [
            'email' => (string)$row['member_email'],
            'permission' => (string)$row['permission'],
            'joined_at' => (int)($row['joined_at'] ?? 0),
        ];
    }
    json_out(['ok' => true, 'admin_email' => $access['admin_email'], 'members' => $members]);
}

if ($action === 'groups_add_member') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_POST['group_id'] ?? '')));
    $memberEmail = strtolower(trim((string)($_POST['member_email'] ?? '')));
    $permission = (string)($_POST['permission'] ?? 'read_edit');
    if (!in_array($permission, ['read', 'read_edit'], true)) $permission = 'read';
    if ($groupId === '' || $memberEmail === '') json_out(['ok' => false, 'error' => 'group_id and member_email required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access || !$access['is_admin']) json_out(['ok' => false, 'error' => 'admin only'], 403);
    if ($memberEmail === strtolower($access['admin_email'])) json_out(['ok' => false, 'error' => 'cannot add admin'], 400);
    if (!cloud_group_user_exists($db, $memberEmail)) json_out(['ok' => false, 'error' => 'user not found'], 404);
    $stmt = $db->prepare('INSERT INTO cloud_group_members (group_id, member_email, permission, joined_at)
        VALUES (:gid, :email, :perm, :ts)
        ON CONFLICT(group_id, member_email) DO UPDATE SET permission = excluded.permission');
    $stmt->bindValue(':gid', $groupId, PDO::PARAM_STR);
    $stmt->bindValue(':email', $memberEmail, PDO::PARAM_STR);
    $stmt->bindValue(':perm', $permission, PDO::PARAM_STR);
    $stmt->bindValue(':ts', time(), PDO::PARAM_INT);
    $stmt->execute();
    json_out(['ok' => true]);
}

if ($action === 'groups_update_member') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_POST['group_id'] ?? '')));
    $memberEmail = strtolower(trim((string)($_POST['member_email'] ?? '')));
    $permission = (string)($_POST['permission'] ?? 'read');
    if (!in_array($permission, ['read', 'read_edit'], true)) $permission = 'read';
    if ($groupId === '' || $memberEmail === '') json_out(['ok' => false, 'error' => 'group_id and member_email required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access || !$access['is_admin']) json_out(['ok' => false, 'error' => 'admin only'], 403);
    if ($memberEmail === strtolower($access['admin_email'])) json_out(['ok' => false, 'error' => 'cannot change admin'], 400);
    $stmt = $db->prepare('UPDATE cloud_group_members SET permission = :perm WHERE group_id = :gid AND member_email = :email');
    $stmt->bindValue(':perm', $permission, PDO::PARAM_STR);
    $stmt->bindValue(':gid', $groupId, PDO::PARAM_STR);
    $stmt->bindValue(':email', $memberEmail, PDO::PARAM_STR);
    $stmt->execute();
    json_out(['ok' => true]);
}

if ($action === 'groups_remove_member') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_POST['group_id'] ?? '')));
    $memberEmail = strtolower(trim((string)($_POST['member_email'] ?? '')));
    if ($groupId === '' || $memberEmail === '') json_out(['ok' => false, 'error' => 'group_id and member_email required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access || !$access['is_admin']) json_out(['ok' => false, 'error' => 'admin only'], 403);
    if ($memberEmail === strtolower($access['admin_email'])) json_out(['ok' => false, 'error' => 'cannot remove admin'], 400);
    $stmt = $db->prepare('DELETE FROM cloud_group_members WHERE group_id = :gid AND member_email = :email');
    $stmt->bindValue(':gid', $groupId, PDO::PARAM_STR);
    $stmt->bindValue(':email', $memberEmail, PDO::PARAM_STR);
    $stmt->execute();
    json_out(['ok' => true]);
}

if ($action === 'groups_leave') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_POST['group_id'] ?? '')));
    if ($groupId === '') json_out(['ok' => false, 'error' => 'group_id required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access) json_out(['ok' => false, 'error' => 'not a member'], 404);
    if ($access['is_admin']) json_out(['ok' => false, 'error' => 'admin cannot leave'], 400);
    $stmt = $db->prepare('DELETE FROM cloud_group_members WHERE group_id = :gid AND member_email = :email');
    $stmt->bindValue(':gid', $groupId, PDO::PARAM_STR);
    $stmt->bindValue(':email', strtolower($email), PDO::PARAM_STR);
    $stmt->execute();
    json_out(['ok' => true]);
}

if ($action === 'groups_lookup_user') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_GET['group_id'] ?? '')));
    $target = strtolower(trim((string)($_GET['email'] ?? '')));
    if ($groupId === '') json_out(['ok' => false, 'error' => 'group_id required'], 400);
    if ($target === '' || !filter_var($target, FILTER_VALIDATE_EMAIL)) {
        json_out(['ok' => true, 'found' => false]);
    }
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access || !$access['is_admin']) json_out(['ok' => false, 'error' => 'admin only'], 403);
    $stmt = $db->prepare('SELECT email, photo FROM users WHERE LOWER(email) = :email LIMIT 1');
    $stmt->bindValue(':email', $target, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(['ok' => true, 'found' => false]);
    $userEmail = (string)$row['email'];
    json_out(['ok' => true, 'found' => true, 'user' => [
        'email' => $userEmail,
        'photo_url' => resolve_user_photo_url(getUserPhotoByEmail($userEmail)),
    ]]);
}

if ($action === 'groups_delete') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_POST['group_id'] ?? '')));
    $moveTargetRoot = trim((string)($_POST['move_target_root'] ?? ''));
    $moveTargetPath = safe_path((string)($_POST['move_target_path'] ?? ''));
    if ($groupId === '') json_out(['ok' => false, 'error' => 'group_id required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $group = cloud_group_get_row($db, $groupId);
    if (!$group) json_out(['ok' => false, 'error' => 'not found'], 404);
    if (strtolower((string)$group['admin_email']) !== strtolower($email)) json_out(['ok' => false, 'error' => 'admin only'], 403);
    $adminUser = cloud_path_username((string)$group['admin_email']);
    $srcBase = appdata_user_root_dir('cloud', $adminUser, 'main', false) . '/' . cloud_group_storage_rel($groupId);
    if ($moveTargetRoot !== '') {
        $allowed = ['main', 'archive', 'temp', 'documents', 'photos', 'pdf'];
        if (!in_array($moveTargetRoot, $allowed, true)) json_out(['ok' => false, 'error' => 'invalid target root'], 400);
        $dstBase = user_root($adminUser, $moveTargetRoot);
        $dstDir = $moveTargetPath === '' ? $dstBase : $dstBase . '/' . $moveTargetPath;
        if (!is_dir($dstDir) && !@mkdir($dstDir, 0775, true)) json_out(['ok' => false, 'error' => 'target create failed'], 500);
        $folderName = preg_replace('/[^a-zA-Z0-9_\-\. ]/u', '_', (string)$group['name']) ?: 'group_' . substr($groupId, 0, 8);
        $target = $dstDir . '/' . $folderName;
        $suffix = 1;
        while (file_exists($target)) {
            $target = $dstDir . '/' . $folderName . '_' . $suffix;
            $suffix++;
        }
        if (is_dir($srcBase) && !cloud_group_copy_tree($srcBase, $target)) {
            json_out(['ok' => false, 'error' => 'move files failed'], 500);
        }
    }
    cloud_group_delete_storage($groupId, (string)$group['admin_email']);
    $stmt = $db->prepare('DELETE FROM cloud_groups WHERE group_id = :gid');
    $stmt->bindValue(':gid', $groupId, PDO::PARAM_STR);
    $stmt->execute();
    json_out(['ok' => true]);
}

if ($action === 'groups_history') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_GET['group_id'] ?? '')));
    if ($groupId === '') json_out(['ok' => false, 'error' => 'group_id required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access || !$access['is_admin']) json_out(['ok' => false, 'error' => 'admin only'], 403);
    $limit = min(500, max(1, (int)($_GET['limit'] ?? 200)));
    $stmt = $db->prepare('SELECT file_path, user_email, action, created_at FROM cloud_group_file_history WHERE group_id = :gid ORDER BY created_at DESC LIMIT :lim');
    $stmt->bindValue(':gid', $groupId, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $items = [];
    $res = $stmt->execute();
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $items[] = [
            'file_path' => (string)$row['file_path'],
            'user_email' => (string)$row['user_email'],
            'action' => (string)$row['action'],
            'created_at' => (int)($row['created_at'] ?? 0),
        ];
    }
    json_out(['ok' => true, 'items' => $items]);
}

if ($action === 'groups_log_activity') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_POST['group_id'] ?? '')));
    $filePath = safe_path((string)($_POST['file_path'] ?? ''));
    $act = (string)($_POST['activity'] ?? 'open');
    if ($groupId === '') json_out(['ok' => false, 'error' => 'group_id required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    if (!cloud_group_member_access($db, $groupId, $email)) json_out(['ok' => false, 'error' => 'access denied'], 403);
    cloud_group_log_activity($groupId, $filePath, $act, $email);
    json_out(['ok' => true]);
}

if ($action === 'groups_rename') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $groupId = strtolower(trim((string)($_POST['group_id'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));
    if ($groupId === '' || $name === '') json_out(['ok' => false, 'error' => 'group_id and name required'], 400);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $access = cloud_group_member_access($db, $groupId, $email);
    if (!$access || !$access['is_admin']) json_out(['ok' => false, 'error' => 'admin only'], 403);
    $stmt = $db->prepare('UPDATE cloud_groups SET name = :name WHERE group_id = :gid');
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':gid', $groupId, PDO::PARAM_STR);
    $stmt->execute();
    json_out(['ok' => true, 'name' => $name]);
}
