<?php
$action = $_GET['action'] ?? $_POST['action'] ?? '';
require_once __DIR__ . '/ai_agent.php';
require_once __DIR__ . '/cloud_groups.php';
require_once __DIR__ . '/cloud_group_chat.php';
require_once dirname(__DIR__, 2) . '/includes/cloud_storage_requests.php';
require_once dirname(__DIR__, 2) . '/includes/ai_config_store.php';
require_once dirname(__DIR__, 2) . '/includes/ai_proxy.php';
require_once dirname(__DIR__, 2) . '/includes/cloud_hardening.php';
if (!in_array($action, cloud_public_api_actions(), true)) {
    cloud_require_api_auth_unless_public($action);
    cloud_require_cookie_csrf();
    $rlKey = (string)(get_session_email() ?: ($_SERVER['REMOTE_ADDR'] ?? 'anon'));
    cloud_require_rate_limit($rlKey, $action === 'upload' ? 'upload' : 'api');
}
if (!function_exists('cloud_dedupe_tree_items')) {
    function cloud_dedupe_tree_items(array $items): array {
        $deduped = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $path = trim(str_replace('\\', '/', (string)($item['path'] ?? '')), '/');
            if ($path === '') continue;
            $item['path'] = preg_replace('#/+#', '/', $path);
            $deduped[$item['path']] = $item;
        }
        return array_values($deduped);
    }
}
if (!function_exists('cloud_validate_file_name')) {
    function cloud_validate_file_name(string $name): ?string {
        if (trim($name) === '') return 'empty name';
        if ($name !== basename($name) || preg_match('/[\\\\\/:*?"<>|\x00-\x1F]/', $name)) return 'invalid name';
        if ($name === '.' || $name === '..') return 'invalid name';
        if (preg_match('/[. ]$/', $name)) return 'invalid name';
        if (preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\..*)?$/i', $name)) return 'reserved name';
        return null;
    }
    function cloud_validate_relative_path_names(string $path): ?string {
        foreach (explode('/', trim(str_replace('\\', '/', $path), '/')) as $part) {
            if ($part === '') continue;
            $error = cloud_validate_file_name($part);
            if ($error !== null) return $error;
        }
        return null;
    }
}

if ($action === 'get_encryption_notice_status') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensure_cloud_encryption_consents_table();
    $stmt = $db->prepare("SELECT status, consented_at FROM cloud_encryption_consents WHERE email = :e LIMIT 1");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    json_out([
        'ok' => true,
        'status' => (string)($row['status'] ?? ''),
        'consented_at' => isset($row['consented_at']) ? (int)$row['consented_at'] : null,
        'should_prompt' => !$row || (string)($row['status'] ?? '') !== 'accepted',
    ]);
}

if ($action === 'set_encryption_notice_status') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensure_cloud_encryption_consents_table();
    $now = time();
    $stmt = $db->prepare("INSERT INTO cloud_encryption_consents(email, status, consented_at, updated_at)
        VALUES(:e, 'accepted', :t, :t)
        ON CONFLICT(email) DO UPDATE SET
            status = 'accepted',
            updated_at = excluded.updated_at");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':t', $now, PDO::PARAM_INT);
    $stmt->execute();
    json_out(['ok' => true, 'status' => 'accepted']);
}

if ($action === 'get_state') {
    $current_user = cloud_api_user();
    $email = cloud_api_email();
    $display_email = $email;
    cloud_session_close_early();

    $user_quota    = cloud_storage_default_quota_mb();
    $session_limit = 0;
    $hide_lang_switcher = 0;
    $sync_local_path = '';
    $sync_interval_sec = 300;
    $sync_mode = 'mtime_size';
    $sync_enabled = 0;
    $last_sync_at = 0;
    $trash_retention_days = 7;
    $db_s = getDB();
    if ($db_s) {
        try {
            ensureCloudUserSettingsSchema($db_s);
            $sq = $db_s->prepare('SELECT quota_mb, session_limit_mb, hide_lang_switcher, sync_local_path, sync_interval_sec, sync_mode, sync_enabled, last_sync_at, trash_retention_days FROM cloud_user_settings WHERE email = :e');
            $sq->bindValue(':e', $email, PDO::PARAM_STR);
            $rq = $sq->execute()->fetch(PDO::FETCH_ASSOC);
            if ($rq) {
                $user_quota    = max(1, (int)$rq['quota_mb']);
                $session_limit = max(0, (int)$rq['session_limit_mb']);
                $hide_lang_switcher = ((int)($rq['hide_lang_switcher'] ?? 0)) ? 1 : 0;
                $sync_local_path = (string)($rq['sync_local_path'] ?? '');
                $sync_interval_sec = max(60, (int)($rq['sync_interval_sec'] ?? 300));
                $sync_mode = (string)($rq['sync_mode'] ?? 'mtime_size');
                if ($sync_mode === 'mtime_size_hash') $sync_mode = 'mtime_size';
                $sync_enabled = ((int)($rq['sync_enabled'] ?? 0)) ? 1 : 0;
                $last_sync_at = max(0, (int)($rq['last_sync_at'] ?? 0));
                $trash_retention_days = cloud_normalize_trash_retention_days((int)($rq['trash_retention_days'] ?? 7));
            }
        } catch (Exception $e) {
            cloud_safe_error_log("get_state quota lookup failed: " . $e->getMessage());
        }
    }

    $used_bytes = cloud_used_bytes_cached($email, $current_user, false, 300);
    $user_photo = resolve_user_photo_url(getUserPhotoByEmail($email));

    $users = [[
        'name'             => $current_user,
        'email'            => $display_email,
        'photo'            => $user_photo,
        'quota_mb'         => $user_quota,
        'session_limit_mb' => $session_limit,
        'hide_lang_switcher' => $hide_lang_switcher,
        'sync_local_path' => $sync_local_path,
        'sync_interval_sec' => $sync_interval_sec,
        'sync_mode' => $sync_mode,
        'sync_enabled' => $sync_enabled,
        'last_sync_at' => $last_sync_at,
        'trash_retention_days' => $trash_retention_days,
        'used_bytes'       => $used_bytes
    ]];

    json_out([
        'active' => $current_user,
        'active_email' => $display_email,
        'photo' => $user_photo,
        'users' => $users
    ]);
}

if ($action === 'get_properties') {
  $user = get_current_user_from_sessions();
  $base = user_root($user, get_requested_root_key());
  $path = safe_path((string)($_GET['path'] ?? ''));
  $fullPath = $base . '/' . $path;
  
  if (!file_exists($fullPath)) {
    json_out(['error' => 'File not found'], 404);
  }
  
  $properties = getFileProperties($fullPath, $path);
  json_out($properties);
}

if ($action === 'move') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  $base = user_root($user, $rootKey);
  $source_path = safe_path((string)($_POST['source_path'] ?? ''));
  $target_dir  = safe_path((string)($_POST['target_dir'] ?? ''));
  $raw_target_root = strtolower(trim((string)($_POST['target_root'] ?? '')));
  $target_root_key = cloud_is_valid_root_key($raw_target_root) ? $raw_target_root : $rootKey;
  cloud_group_require_write($target_root_key);
  $target_base = user_root($user, $target_root_key);

  if ($source_path === '') {
    json_out(['error' => 'source_path required'], 400);
  }

  if (strpos($source_path, 'object') !== false || strpos($source_path, '[') !== false) {
    json_out(['error' => 'invalid source_path'], 400);
  }

  $source_full = $base . '/' . $source_path;
  $target_full = $target_dir === ''
    ? $target_base . '/' . basename($source_path)
    : $target_base . '/' . $target_dir . '/' . basename($source_path);

  if (!file_exists($source_full)) {
    json_out(['error' => 'source not found'], 404);
  }

  if (file_exists($target_full)) {
    json_out(['error' => 'target already exists'], 409);
  }

  $target_dir_full = $target_dir === '' ? $target_base : $target_base . '/' . $target_dir;
  if (!is_dir($target_dir_full)) {
    json_out(['error' => 'target directory not found'], 404);
  }

  if (!rename($source_full, $target_full)) {
    json_out(['error' => 'move failed'], 500);
  }

  $email = get_session_email();
  if ($email && $rootKey === 'main') invalidate_file_tree($email);
  if ($email && $target_root_key === 'main' && $target_root_key !== $rootKey) invalidate_file_tree($email);
  if ($email) append_sync_log($email, $rootKey, 'move', $source_path, 'INFO', 'Path moved');
  cloud_group_maybe_log('move', $source_path);
  if ($target_root_key !== $rootKey) {
    $target_group_id = cloud_parse_group_id($target_root_key);
    if ($target_group_id) cloud_group_log_activity($target_group_id, ($target_dir === '' ? basename($source_path) : $target_dir . '/' . basename($source_path)), 'create', $email);
  }
  json_out(['ok' => true]);
}

if ($action === 'copy') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  $base = user_root($user, $rootKey);
  $source_path = safe_path((string)($_POST['source_path'] ?? ''));
  $target_dir  = safe_path((string)($_POST['target_dir'] ?? ''));
  $raw_target_root = strtolower(trim((string)($_POST['target_root'] ?? '')));
  $target_root_key = cloud_is_valid_root_key($raw_target_root) ? $raw_target_root : $rootKey;
  cloud_group_require_write($target_root_key);
  $target_base = user_root($user, $target_root_key);

  if ($source_path === '') {
    json_out(['error' => 'source_path required'], 400);
  }

  if (strpos($source_path, 'object') !== false || strpos($source_path, '[') !== false) {
    json_out(['error' => 'invalid source_path'], 400);
  }

  $source_full = $base . '/' . $source_path;
  $target_full = $target_dir === ''
    ? $target_base . '/' . basename($source_path)
    : $target_base . '/' . $target_dir . '/' . basename($source_path);

  if (!file_exists($source_full)) {
    json_out(['error' => 'source not found'], 404);
  }

  if (file_exists($target_full)) {
    json_out(['error' => 'target already exists'], 409);
  }

  $target_dir_full = $target_dir === '' ? $target_base : $target_base . '/' . $target_dir;
  if (!is_dir($target_dir_full)) {
    json_out(['error' => 'target directory not found'], 404);
  }

  if ($target_dir !== '' && strpos($target_dir, $source_path . '/') === 0) {
    json_out(['error' => 'cannot copy into itself'], 400);
  }

  $copyRecursive = static function (string $src, string $dst) use (&$copyRecursive): bool {
    if (is_file($src)) {
      $parent = dirname($dst);
      if (!is_dir($parent) && !mkdir($parent, 0755, true)) return false;
      return copy($src, $dst);
    }
    if (!is_dir($src)) return false;
    if (!is_dir($dst) && !mkdir($dst, 0755, true)) return false;
    $entries = scandir($src);
    if ($entries === false) return false;
    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') continue;
      if (!$copyRecursive($src . '/' . $entry, $dst . '/' . $entry)) return false;
    }
    return true;
  };

  if (!$copyRecursive($source_full, $target_full)) {
    json_out(['error' => 'copy failed'], 500);
  }

  $email = get_session_email();
  if ($email && ($rootKey === 'main' || $target_root_key === 'main')) invalidate_file_tree($email);
  if ($email) append_sync_log($email, $rootKey, 'copy', $source_path, 'INFO', 'Path copied');
  $target_group_id = cloud_parse_group_id($target_root_key);
  if ($target_group_id) cloud_group_log_activity($target_group_id, ($target_dir === '' ? basename($source_path) : $target_dir . '/' . basename($source_path)), 'create', $email);
  json_out(['ok' => true]);
}

if ($action === 'move_to_trash') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  $path = safe_path((string)($_POST['path'] ?? ''));
  if ($path === '') json_out(['error' => 'path required'], 400);

  try {
    $meta = move_path_to_trash($user, $rootKey, $path);
  } catch (RuntimeException $e) {
    $message = $e->getMessage();
    $status = $message === 'not found' ? 404 : ($message === 'Item is already in trash' ? 409 : 500);
    json_out(['error' => $message], $status);
  }

  $email = get_session_email();
  if ($email && $rootKey === 'main') invalidate_file_tree($email);
  if ($email) append_sync_log($email, $rootKey, 'trash', $path, 'WARNING', 'Path moved to trash');
  cloud_group_maybe_log('delete', $path);
  json_out(['ok' => true, 'item' => $meta]);
}

if ($action === 'restore_from_trash') {
  $user = get_current_user_from_sessions();
  $trashBase = user_root($user, 'trash');
  $id = safe_path((string)($_POST['path'] ?? ''));
  if ($id === '') json_out(['error' => 'path required'], 400);

  $meta = load_trash_metadata($user);
  if (!isset($meta[$id])) json_out(['error' => 'not found'], 404);

  $item = $meta[$id];
  $targetRoot = in_array(($item['original_root'] ?? 'main'), ['main', 'archive', 'temp', 'documents', 'photos', 'pdf'], true)
      ? $item['original_root']
      : 'main';
  $targetPath = safe_path((string)($item['original_path'] ?? ''));
  if ($targetPath === '') json_out(['error' => 'invalid original path'], 500);

  $sourceFull = $trashBase . '/' . $id;
  if (!file_exists($sourceFull)) {
    unset($meta[$id]);
    save_trash_metadata($user, $meta);
    json_out(['error' => 'not found'], 404);
  }

  $targetBase = user_root($user, $targetRoot);
  $targetFull = $targetBase . '/' . $targetPath;
  if (file_exists($targetFull)) json_out(['error' => 'restore target exists'], 409);

  $targetDir = dirname($targetFull);
  if (!is_dir($targetDir)) @mkdir($targetDir, 0775, true);

  if (!@rename($sourceFull, $targetFull)) {
    json_out(['error' => 'restore failed'], 500);
  }

  unset($meta[$id]);
  save_trash_metadata($user, $meta);

  $email = get_session_email();
  if ($email && $targetRoot === 'main') invalidate_file_tree($email);
  if ($email) append_sync_log($email, $targetRoot, 'restore_from_trash', $targetPath, 'INFO', 'Path restored from trash');
  json_out(['ok' => true, 'restored_path' => $targetPath, 'restored_root' => $targetRoot]);
}

if ($action === 'empty_trash') {
  $user = get_current_user_from_sessions();
  if ($user === 'guest') json_out(['error' => 'not authenticated'], 401);
  $trashBase = user_root($user, 'trash');
  if (!is_dir($trashBase)) {
    @mkdir($trashBase, 0775, true);
  }
  $meta = load_trash_metadata($user);
  $removed = 0;

  foreach ($meta as $id => $item) {
    $target = $trashBase . '/' . $id;
    if (file_exists($target)) {
      remove_path_recursive($target);
      $removed++;
    }
  }

  foreach (scandir($trashBase) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..' || $entry === '.trash_meta.json') continue;
    remove_path_recursive($trashBase . '/' . $entry);
    $removed++;
  }

  save_trash_metadata($user, []);

  $email = get_session_email();
  if ($email) {
    invalidate_file_tree($email);
    append_sync_log($email, 'trash', 'empty_trash', '', 'WARNING', 'Trash emptied');
  }
  json_out(['ok' => true, 'removed' => $removed]);
}

if ($action === 'set_trash_retention') {
  $user = get_current_user_from_sessions();
  if ($user === 'guest') json_out(['error' => 'not authenticated'], 401);
  $email = get_session_email();
  if (!$email) json_out(['error' => 'not authenticated'], 401);
  $days = cloud_normalize_trash_retention_days((int)($_POST['days'] ?? $_GET['days'] ?? 7));
  $days = cloud_set_trash_retention_days($email, $days);
  json_out(['ok' => true, 'trash_retention_days' => $days, 'options' => cloud_trash_retention_options()]);
}

if ($action === 'share_file' || $action === 'share_folder') {
    require_once dirname(__DIR__, 4) . '/project/sharepoint/bridge_legacy.php';
    $user = get_current_user_from_sessions();
    if ($user === 'guest') json_out(['error' => 'not authenticated'], 401);

    $requestedRootKey = get_requested_root_key();
    $rawPath = safe_path((string)($_POST['path'] ?? ''));

    if ($rawPath === '') {
        json_out(['error' => 'path required'], 400);
    }

    [$rootKey, $path] = cloud_resolve_rooted_target_path($rawPath, $requestedRootKey);
    $base = user_root($user, $rootKey);
    $fullPath = $base . '/' . $path;
    if (!file_exists($fullPath)) {
        json_out(['error' => 'file not found'], 404);
    }

    $db = getDB();
    if (!$db) {
        json_out(['error' => 'Database connection failed'], 500);
    }

    // Get email directly from session — no fallback by name or @local
    $userEmail = get_session_email();
    if (!$userEmail) {
        json_out(['error' => 'not authenticated'], 401);
    }

    // Normalize the path
    $absolutePath   = realpath($fullPath);
    $normalizedPath = str_replace('\\', '/', $absolutePath);
    $isFolder = is_dir($fullPath) ? 1 : 0;

    try {
        $ensureSharedFilesColumns = static function(AppDatabase $db): void {
            $isPg = function_exists('app_db_is_pgsql') && app_db_is_pgsql();
            if ($isPg) {
                $safeExec = static function(string $sql) use ($db): void {
                    try { $db->exec($sql); } catch (Exception $e) {}
                };
                $safeExec("ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS is_folder INTEGER NOT NULL DEFAULT 0");
                $safeExec("ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS user_email TEXT NOT NULL DEFAULT ''");
                $safeExec("ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS expires BIGINT DEFAULT NULL");
                $safeExec("ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS expires_option TEXT NOT NULL DEFAULT 'never'");
                $safeExec("ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS password_hash TEXT NOT NULL DEFAULT ''");
                $safeExec("ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS view_only INTEGER NOT NULL DEFAULT 0");
                $safeExec("ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS disable_download INTEGER NOT NULL DEFAULT 0");
                $safeExec("ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS disable_copy INTEGER NOT NULL DEFAULT 0");
                $safeExec("ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS max_views INTEGER NOT NULL DEFAULT 0");
                $safeExec("ALTER TABLE shared_files ADD COLUMN IF NOT EXISTS view_count INTEGER NOT NULL DEFAULT 0");
                return;
            }
            $cols = [];
            $columnsResult = null;
            try {
                $columnsResult = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='shared_files'");
            } catch (Exception $e) {
                $columnsResult = null;
            }
            while ($columnsResult && ($column = $columnsResult->fetch(PDO::FETCH_ASSOC))) {
                $cols[] = (string)($column['name'] ?? '');
            }
            $safeExec = static function(string $sql) use ($db): void {
                try { $db->exec($sql); } catch (Exception $e) {}
            };
            if (!in_array('is_folder', $cols, true)) $safeExec("ALTER TABLE shared_files ADD COLUMN is_folder INTEGER NOT NULL DEFAULT 0");
            if (!in_array('user_email', $cols, true)) $safeExec("ALTER TABLE shared_files ADD COLUMN user_email TEXT NOT NULL DEFAULT ''");
            if (!in_array('expires', $cols, true)) $safeExec("ALTER TABLE shared_files ADD COLUMN expires INTEGER DEFAULT NULL");
            if (!in_array('expires_option', $cols, true)) $safeExec("ALTER TABLE shared_files ADD COLUMN expires_option TEXT NOT NULL DEFAULT 'never'");
            if (!in_array('password_hash', $cols, true)) $safeExec("ALTER TABLE shared_files ADD COLUMN password_hash TEXT NOT NULL DEFAULT ''");
            if (!in_array('view_only', $cols, true)) $safeExec("ALTER TABLE shared_files ADD COLUMN view_only INTEGER NOT NULL DEFAULT 0");
            if (!in_array('disable_download', $cols, true)) $safeExec("ALTER TABLE shared_files ADD COLUMN disable_download INTEGER NOT NULL DEFAULT 0");
            if (!in_array('disable_copy', $cols, true)) $safeExec("ALTER TABLE shared_files ADD COLUMN disable_copy INTEGER NOT NULL DEFAULT 0");
            if (!in_array('max_views', $cols, true)) $safeExec("ALTER TABLE shared_files ADD COLUMN max_views INTEGER NOT NULL DEFAULT 0");
            if (!in_array('view_count', $cols, true)) $safeExec("ALTER TABLE shared_files ADD COLUMN view_count INTEGER NOT NULL DEFAULT 0");
        };
        $resolveExpiry = static function(string $option): array {
            $map = [
                'never' => null,
                '1h' => 3600,
                '1d' => 86400,
                '1w' => 7 * 86400,
                '1m' => 30 * 86400,
                '3m' => 90 * 86400,
                '6m' => 180 * 86400,
                '1y' => 365 * 86400,
            ];
            $normalized = array_key_exists($option, $map) ? $option : 'never';
            $ttl = $map[$normalized];
            return [$normalized, $ttl === null ? null : (time() + $ttl)];
        };
        $ensureSharedFilesColumns($db);

        // Clean up expired links
        $db->exec("DELETE FROM shared_files WHERE expires IS NOT NULL AND expires < " . time());
        [$expiresOption, $expires] = $resolveExpiry((string)($_POST['expires_option'] ?? 'never'));
        $plainPassword = trim((string)($_POST['password'] ?? ''));
        $passwordHash = $plainPassword !== '' ? password_hash($plainPassword, PASSWORD_DEFAULT) : '';
        $viewOnly = (int)((int)($_POST['view_only'] ?? 0) ? 1 : 0);
        $disableDownload = (int)((int)($_POST['disable_download'] ?? 0) ? 1 : 0);
        $disableCopy = (int)((int)($_POST['disable_copy'] ?? 0) ? 1 : 0);
        $maxViews = max(0, (int)($_POST['max_views'] ?? 0));
        if ($viewOnly) {
            $disableDownload = 1;
            $disableCopy = 1;
        }

        // Check if an active link already exists for this file/folder from this user
        $stmt = $db->prepare('SELECT share_id, expires, expires_option, password_hash, view_only, disable_download, disable_copy, max_views FROM shared_files WHERE file_path = :file_path AND user_email = :user_email');
        $stmt->bindValue(':file_path', $normalizedPath, PDO::PARAM_STR);
        $stmt->bindValue(':user_email', $userEmail, PDO::PARAM_STR);
        $result = $stmt->execute();
        $existing = $result->fetch(PDO::FETCH_ASSOC);

        if ($existing && isset($existing['share_id'])) {
            $existingExpires = ($existing['expires'] ?? null) === null ? null : (int)$existing['expires'];
            if (!empty($_POST['reuse_existing'])) {
                json_out(['key' => $existing['share_id'], 'link' => sharepoint_public_link((string)$existing['share_id']), 'expires' => $existingExpires, 'expires_option' => (string)$existing['expires_option'], 'view_only' => (int)$existing['view_only'], 'disable_download' => (int)$existing['disable_download'], 'disable_copy' => (int)$existing['disable_copy'], 'max_views' => (int)$existing['max_views'], 'has_password' => (string)$existing['password_hash'] !== '']);
                return;
            }
            if ($plainPassword === '' && !empty($_POST['keep_password'])) {
                $passwordHash = (string)$existing['password_hash'];
            }
            if ($expiresOption === (string)$existing['expires_option']) {
                $expires = $existingExpires;
            }
            $upd = $db->prepare('UPDATE shared_files SET is_folder = :is_folder, expires = :expires, expires_option = :expires_option, password_hash = :password_hash, view_only = :view_only, disable_download = :disable_download, disable_copy = :disable_copy, max_views = :max_views WHERE share_id = :share_id');
            $upd->bindValue(':is_folder', $isFolder, PDO::PARAM_INT);
            if ($expires === null) $upd->bindValue(':expires', null, PDO::PARAM_NULL);
            else $upd->bindValue(':expires', $expires, PDO::PARAM_INT);
            $upd->bindValue(':expires_option', $expiresOption, PDO::PARAM_STR);
            $upd->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
            $upd->bindValue(':view_only', $viewOnly, PDO::PARAM_INT);
            $upd->bindValue(':disable_download', $disableDownload, PDO::PARAM_INT);
            $upd->bindValue(':disable_copy', $disableCopy, PDO::PARAM_INT);
            $upd->bindValue(':max_views', $maxViews, PDO::PARAM_INT);
            $upd->bindValue(':share_id', $existing['share_id'], PDO::PARAM_STR);
            $upd->execute();
            sharepoint_mirror_legacy_cloud($db, (string)$existing['share_id'], basename($fullPath), $normalizedPath, (bool)$isFolder, $userEmail, $expires, $expiresOption, $passwordHash, !$disableDownload, $maxViews);
            json_out(['key' => $existing['share_id'], 'link' => sharepoint_public_link((string)$existing['share_id']), 'expires' => $expires, 'expires_option' => $expiresOption, 'view_only' => $viewOnly, 'disable_download' => $disableDownload, 'disable_copy' => $disableCopy, 'max_views' => $maxViews, 'has_password' => $passwordHash !== '']);
            return;
        }

        // Generate a unique ID
        do {
            $shareId = bin2hex(random_bytes(12));
            $stmt = $db->prepare('SELECT share_id FROM shared_files WHERE share_id = :share_id');
            $stmt->bindValue(':share_id', $shareId, PDO::PARAM_STR);
            $result = $stmt->execute();
            $exists = $result->fetch(PDO::FETCH_ASSOC);
        } while ($exists);

        // Create a new record
        $fileName = basename($fullPath);
        $createdAtExpr = (function_exists('app_db_is_pgsql') && app_db_is_pgsql()) ? 'NOW()' : 'datetime("now", "localtime")';
        $stmt = $db->prepare('
            INSERT INTO shared_files (share_id, file_name, file_path, is_folder, user_email, created_at, expires, expires_option, password_hash, view_only, disable_download, disable_copy, max_views, view_count)
            VALUES (:share_id, :file_name, :file_path, :is_folder, :user_email, ' . $createdAtExpr . ', :expires, :expires_option, :password_hash, :view_only, :disable_download, :disable_copy, :max_views, 0)
        ');

        $stmt->bindValue(':share_id', $shareId, PDO::PARAM_STR);
        $stmt->bindValue(':file_name', $fileName, PDO::PARAM_STR);
        $stmt->bindValue(':file_path', $normalizedPath, PDO::PARAM_STR);
        $stmt->bindValue(':is_folder', $isFolder, PDO::PARAM_INT);
        $stmt->bindValue(':user_email', $userEmail, PDO::PARAM_STR);
        if ($expires === null) $stmt->bindValue(':expires', null, PDO::PARAM_NULL);
        else $stmt->bindValue(':expires', $expires, PDO::PARAM_INT);
        $stmt->bindValue(':expires_option', $expiresOption, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->bindValue(':view_only', $viewOnly, PDO::PARAM_INT);
        $stmt->bindValue(':disable_download', $disableDownload, PDO::PARAM_INT);
        $stmt->bindValue(':disable_copy', $disableCopy, PDO::PARAM_INT);
        $stmt->bindValue(':max_views', $maxViews, PDO::PARAM_INT);

        if ($stmt->execute()) {
            sharepoint_mirror_legacy_cloud($db, (string)$shareId, basename($fullPath), $normalizedPath, (bool)$isFolder, $userEmail, $expires, $expiresOption, $passwordHash, !$disableDownload, $maxViews);
            json_out(['key' => $shareId, 'link' => sharepoint_public_link((string)$shareId), 'expires' => $expires, 'expires_option' => $expiresOption, 'view_only' => $viewOnly, 'disable_download' => $disableDownload, 'disable_copy' => $disableCopy, 'max_views' => $maxViews, 'has_password' => $passwordHash !== '']);
        } else {
            json_out(['error' => 'Failed to create share record'], 500);
        }

    } catch (Exception $e) {
        cloud_safe_error_log("Share file error: " . $e->getMessage());
        json_out(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

if ($action === 'internal_share_metadata') {
    $user = get_current_user_from_sessions();
    if ($user === 'guest') json_out(['error' => 'not authenticated'], 401);
    $requestedRootKey = get_requested_root_key();
    $rawPath = safe_path((string)($_GET['path'] ?? $_POST['path'] ?? ''));
    if ($rawPath === '') json_out(['error' => 'path required'], 400);
    [$rootKey, $path] = cloud_resolve_rooted_target_path($rawPath, $requestedRootKey);
    $base = user_root($user, $rootKey);
    $target = $base . '/' . $path;
    if (!is_file($target)) json_out(['error' => 'file not found'], 404);
    $name = basename($target);
    $size = (int)(@filesize($target) ?: 0);
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    $mime = (string)(@mime_content_type($target) ?: 'application/octet-stream');
    $previewType = 'file';
    $preview = '';
    $textExts = ['txt','md','json','xml','csv','log','html','css','js','ts','tsx','jsx','php','py','java','c','cpp','h','hpp','go','rs','sql','yaml','yml','sh','ini','conf','config'];
    if (($size <= 65536 && (is_text_mime($mime) || in_array($ext, $textExts, true))) || $size === 0) {
        $previewType = 'text';
        if (is_documents_root($rootKey)) {
            $rawContent = decrypt_documents_content((string)@file_get_contents($target), $user);
        } else {
            $rawContent = (string)@file_get_contents($target, false, null, 0, 1200);
        }
        $preview = substr($rawContent, 0, 500);
        if (!@preg_match('//u', $preview)) {
            $preview = '';
        }
    } elseif ($size <= 262144 && preg_match('#^image/(png|jpe?g|gif|webp|svg\+xml)$#i', $mime)) {
        $previewType = 'image';
        $preview = 'data:' . $mime . ';base64,' . base64_encode((string)@file_get_contents($target));
    }
    json_out(['ok' => true, 'name' => $name, 'size' => $size, 'ext' => $ext, 'mime' => $mime, 'preview_type' => $previewType, 'preview' => $preview]);
}

if ($action === 'import_internal_share') {
    $user = get_current_user_from_sessions();
    $email = get_session_email();
    if ($user === 'guest' || !$email) json_out(['error' => 'not authenticated'], 401);
    $shareId = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_POST['share_id'] ?? $_GET['share_id'] ?? '')));
    if ($shareId === '') json_out(['error' => 'share_id required'], 400);
    $db = getDB();
    if (!$db) json_out(['error' => 'Database connection failed'], 500);
    $stmt = $db->prepare('SELECT share_id, file_name, file_path, is_folder, expires FROM shared_files WHERE share_id = :share_id LIMIT 1');
    $stmt->bindValue(':share_id', $shareId, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(['error' => 'share not found'], 404);
    if (!empty($row['expires']) && (int)$row['expires'] < time()) json_out(['error' => 'share expired'], 410);
    if ((int)($row['is_folder'] ?? 0) === 1) json_out(['error' => 'folders are not supported'], 400);
    $source = (string)($row['file_path'] ?? '');
    if ($source === '' || !is_file($source)) json_out(['error' => 'file not found'], 404);
    $safeName = basename((string)($row['file_name'] ?? basename($source)));
    if (($nameError = cloud_validate_file_name($safeName)) !== null) json_out(['error' => $nameError], 400);
    $targetDirRel = 'incoming';
    $targetBase = user_root($user, 'temp');
    $targetDir = $targetBase . '/' . $targetDirRel;
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) json_out(['error' => 'create failed'], 500);
    $nameBase = pathinfo($safeName, PATHINFO_FILENAME);
    $ext = pathinfo($safeName, PATHINFO_EXTENSION);
    $candidate = $safeName;
    for ($i = 1; file_exists($targetDir . '/' . $candidate); $i++) {
        $candidate = $nameBase . '-' . $i . ($ext !== '' ? '.' . $ext : '');
    }
    $target = $targetDir . '/' . $candidate;
    if (!@copy($source, $target)) json_out(['error' => 'copy failed'], 500);
    append_sync_log($email, 'temp', 'import_internal_share', $targetDirRel . '/' . $candidate, 'INFO', 'Imported chat attachment');
    json_out(['ok' => true, 'root' => 'temp', 'path' => $targetDirRel . '/' . $candidate, 'name' => $candidate]);
}

if ($action === 'set_quota') {
    $email = get_session_email();
    if (!$email) json_out(['error' => 'not authenticated'], 401);
    // Users may no longer change their own storage quota — only admins can.
    if (getUserRoleByEmail($email) < 2) json_out(['error' => 'forbidden'], 403);
    $quota_mb = max(1, (int)($_POST['quota_mb'] ?? 0));
    $db = getDB();
    if (!$db) json_out(['error' => 'db unavailable'], 500);
    try {
        ensureCloudUserSettingsSchema($db);
        // Reliable upsert: INSERT first, then UPDATE
        $ins = $db->prepare("INSERT INTO cloud_user_settings (email, quota_mb, session_limit_mb, hide_lang_switcher, updated_at) VALUES (:e, :q, 0, 0, extract(epoch from now())::bigint) ON CONFLICT (email) DO NOTHING");
        $ins->bindValue(':e', $email, PDO::PARAM_STR);
        $ins->bindValue(':q', $quota_mb, PDO::PARAM_INT);
        $ins->execute();
        $upd = $db->prepare("UPDATE cloud_user_settings SET quota_mb = :q, updated_at = extract(epoch from now())::bigint WHERE email = :e");
        $upd->bindValue(':e', $email, PDO::PARAM_STR);
        $upd->bindValue(':q', $quota_mb, PDO::PARAM_INT);
        $upd->execute();
        json_out(['ok' => true, 'quota_mb' => $quota_mb]);
    } catch (Exception $e) {
        json_out(['error' => $e->getMessage()], 500);
    }
}

// ------------------------------------------------------------------
//  Storage quota requests (2-step admin approval flow)
// ------------------------------------------------------------------
if (!function_exists('cloud_storage_api_admin_email')) {
    function cloud_storage_api_admin_email(): string {
        $email = get_session_email();
        if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
        if (getUserRoleByEmail($email) < 2) json_out(['ok' => false, 'error' => 'forbidden'], 403);
        return $email;
    }
    function cloud_storage_api_used_bytes(string $email): int {
        $username = email_to_cloud_username(strtolower(trim($email)));
        return cloud_used_bytes_cached($email, $username, false, 300);
    }
}

// A regular user asks for more storage; every admin gets a notification.
if ($action === 'request_storage') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    try {
        ensureCloudUserSettingsSchema($db);
        $quota = cloud_storage_user_quota_mb($db, $email);
        $used = cloud_storage_api_used_bytes($email);
        $note = (string)($_POST['note'] ?? '');
        $res = cloud_storage_request_create($db, $email, $quota, $used, $note);
        if (empty($res['ok'])) json_out(['ok' => false, 'error' => $res['error'] ?? 'failed'], 400);
        json_out(['ok' => true, 'already_pending' => !empty($res['already_pending']), 'request' => $res['request']]);
    } catch (Exception $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// Current user's storage status + pending request (drives the settings button).
if ($action === 'get_storage_request') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    try {
        ensureCloudUserSettingsSchema($db);
        json_out([
            'ok' => true,
            'quota_mb' => cloud_storage_user_quota_mb($db, $email),
            'used_bytes' => cloud_storage_api_used_bytes($email),
            'pending' => cloud_storage_pending_request($db, $email),
        ]);
    } catch (Exception $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// Admin mini panel: every user with quota/usage + all pending requests.
// Admin mini panel: paginated + searchable user list (built for thousands of
// users — only the current page is resolved, usage comes from the cached stats
// table instead of walking every home directory).
if ($action === 'admin_storage_overview') {
    cloud_storage_api_admin_email();
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $page = max(1, (int)($_GET['page'] ?? $_POST['page'] ?? 1));
    $perPage = min(100, max(5, (int)($_GET['per_page'] ?? $_POST['per_page'] ?? 25)));
    $q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
    try {
        ensureCloudUserSettingsSchema($db);
        $like = '%' . strtolower($q) . '%';
        $where = $q !== '' ? "WHERE LOWER(email) LIKE :like OR LOWER(COALESCE(name,'')) LIKE :like" : '';

        $cntStmt = $db->prepare('SELECT COUNT(*) AS c FROM users ' . $where);
        if ($q !== '') $cntStmt->bindValue(':like', $like, PDO::PARAM_STR);
        $total = (int)($cntStmt->execute()->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;

        $listStmt = $db->prepare('SELECT email, name, role FROM users ' . $where . ' ORDER BY LOWER(email) LIMIT :lim OFFSET :off');
        if ($q !== '') $listStmt->bindValue(':like', $like, PDO::PARAM_STR);
        $listStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
        $res = $listStmt->execute();
        $pageRows = [];
        $emails = [];
        while ($res && ($u = $res->fetch(PDO::FETCH_ASSOC))) {
            $pageRows[] = $u;
            $emails[] = strtolower(trim((string)($u['email'] ?? '')));
        }
        $quotaMap = cloud_storage_quota_map($db, $emails);
        $usageMap = cloud_storage_usage_map($db, $emails);
        $pendingMap = cloud_storage_pending_map($db, $emails);

        $users = [];
        foreach ($pageRows as $u) {
            $uEmail = strtolower(trim((string)($u['email'] ?? '')));
            if ($uEmail === '') continue;
            $users[] = [
                'email' => $uEmail,
                'name' => (string)($u['name'] ?? ''),
                'role' => (int)($u['role'] ?? 0),
                'quota_mb' => max(1, (int)($quotaMap[$uEmail] ?? cloud_storage_default_quota_mb())),
                'used_bytes' => (int)($usageMap[$uEmail] ?? 0),
                'pending_request' => $pendingMap[$uEmail] ?? null,
            ];
        }
        json_out([
            'ok' => true,
            'users' => $users,
            'requests' => cloud_storage_list_requests($db, 'pending', 100),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'query' => $q,
        ]);
    } catch (Exception $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// Admin analytics: top-10 users by used space plus the aggregated "others".
if ($action === 'admin_storage_analytics') {
    cloud_storage_api_admin_email();
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    try {
        cloud_storage_ensure_usage_table($db);
        try { $db->exec('CREATE INDEX IF NOT EXISTS idx_cloud_storage_usage_bytes ON cloud_storage_usage_stats(root_total_bytes DESC)'); } catch (Throwable $e) {}

        $totalUsers = (int)($db->query('SELECT COUNT(*) AS c FROM users')->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        $agg = $db->query('SELECT COUNT(*) AS c, COALESCE(SUM(root_total_bytes),0) AS s FROM cloud_storage_usage_stats WHERE root_total_bytes > 0')->fetch(PDO::FETCH_ASSOC);
        $usersWithUsage = (int)($agg['c'] ?? 0);
        $totalUsed = (int)($agg['s'] ?? 0);

        $top = [];
        $topSum = 0;
        $res = $db->query('SELECT LOWER(email) AS email, root_total_bytes AS bytes FROM cloud_storage_usage_stats WHERE root_total_bytes > 0 ORDER BY root_total_bytes DESC, LOWER(email) ASC LIMIT 10');
        while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
            $bytes = max(0, (int)$row['bytes']);
            $topSum += $bytes;
            $top[] = ['email' => (string)$row['email'], 'used_bytes' => $bytes];
        }
        $quotaTotal = (int)($db->query('SELECT COALESCE(SUM(quota_mb),0) AS s FROM cloud_user_settings')->fetch(PDO::FETCH_ASSOC)['s'] ?? 0);

        json_out([
            'ok' => true,
            'total_users' => $totalUsers,
            'users_with_usage' => $usersWithUsage,
            'total_used_bytes' => $totalUsed,
            'quota_total_mb' => $quotaTotal,
            'top' => $top,
            'others' => [
                'count' => max(0, $usersWithUsage - count($top)),
                'used_bytes' => max(0, $totalUsed - $topSum),
            ],
            'measured' => true,
        ]);
    } catch (Exception $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// Admin sets an arbitrary user's quota from the mini panel.
if ($action === 'admin_set_user_quota') {
    cloud_storage_api_admin_email();
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $target = strtolower(trim((string)($_POST['email'] ?? '')));
    $quotaMb = max(1, (int)($_POST['quota_mb'] ?? 0));
    if ($target === '') json_out(['ok' => false, 'error' => 'missing_email'], 400);
    try {
        ensureCloudUserSettingsSchema($db);
        cloud_storage_set_user_quota($db, $target, $quotaMb);
        json_out(['ok' => true, 'email' => $target, 'quota_mb' => $quotaMb]);
    } catch (Exception $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// Admin opens a chat with the requester — fetch their pending request.
if ($action === 'admin_get_storage_request') {
    cloud_storage_api_admin_email();
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $target = strtolower(trim((string)($_GET['email'] ?? $_POST['email'] ?? '')));
    if ($target === '') json_out(['ok' => false, 'error' => 'missing_email'], 400);
    try {
        ensureCloudUserSettingsSchema($db);
        json_out([
            'ok' => true,
            'email' => $target,
            'quota_mb' => cloud_storage_user_quota_mb($db, $target),
            'used_bytes' => cloud_storage_api_used_bytes($target),
            'pending' => cloud_storage_pending_request($db, $target),
        ]);
    } catch (Exception $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// Admin approves (with a new quota) or rejects the request.
if ($action === 'admin_resolve_storage_request') {
    $adminEmail = cloud_storage_api_admin_email();
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    $id = (int)($_POST['request_id'] ?? 0);
    $decision = (string)($_POST['decision'] ?? '');
    $quotaMb = max(1, (int)($_POST['quota_mb'] ?? 0));
    $reason = (string)($_POST['reason'] ?? '');
    if ($id <= 0) json_out(['ok' => false, 'error' => 'missing_request_id'], 400);
    if (!in_array($decision, ['approve', 'reject'], true)) json_out(['ok' => false, 'error' => 'invalid_decision'], 400);
    if ($decision === 'approve' && (int)($_POST['quota_mb'] ?? 0) < 1) json_out(['ok' => false, 'error' => 'invalid_quota'], 400);
    try {
        $res = cloud_storage_request_resolve($db, $id, $decision === 'approve', $quotaMb, $adminEmail, $reason);
        if (empty($res['ok'])) json_out(['ok' => false, 'error' => $res['error'] ?? 'failed'], 400);
        json_out($res);
    } catch (Exception $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'set_session_limit') {
    $email = get_session_email();
    if (!$email) json_out(['error' => 'not authenticated'], 401);
    $limit_mb = max(0, (int)($_POST['session_limit_mb'] ?? 0));
    $db = getDB();
    if (!$db) json_out(['error' => 'db unavailable'], 500);
    try {
        ensureCloudUserSettingsSchema($db);
        $ins = $db->prepare("INSERT INTO cloud_user_settings (email, quota_mb, session_limit_mb, hide_lang_switcher, updated_at) VALUES (:e, " . cloud_storage_default_quota_mb() . ", :l, 0, extract(epoch from now())::bigint) ON CONFLICT (email) DO NOTHING");
        $ins->bindValue(':e', $email, PDO::PARAM_STR);
        $ins->bindValue(':l', $limit_mb, PDO::PARAM_INT);
        $ins->execute();
        $upd = $db->prepare("UPDATE cloud_user_settings SET session_limit_mb = :l, updated_at = extract(epoch from now())::bigint WHERE email = :e");
        $upd->bindValue(':e', $email, PDO::PARAM_STR);
        $upd->bindValue(':l', $limit_mb, PDO::PARAM_INT);
        $upd->execute();
        json_out(['ok' => true, 'session_limit_mb' => $limit_mb]);
    } catch (Exception $e) {
        json_out(['error' => $e->getMessage()], 500);
    }
}

if ($action === 'set_hide_lang_switcher') {
    $email = get_session_email();
    if (!$email) json_out(['error' => 'not authenticated'], 401);
    $hide = ((int)($_POST['hide_lang_switcher'] ?? 0)) ? 1 : 0;
    $db = getDB();
    if (!$db) json_out(['error' => 'db unavailable'], 500);
    try {
        ensureCloudUserSettingsSchema($db);
        $ins = $db->prepare("INSERT INTO cloud_user_settings (email, quota_mb, session_limit_mb, hide_lang_switcher, updated_at) VALUES (:e, " . cloud_storage_default_quota_mb() . ", 0, :h, extract(epoch from now())::bigint) ON CONFLICT (email) DO NOTHING");
        $ins->bindValue(':e', $email, PDO::PARAM_STR);
        $ins->bindValue(':h', $hide, PDO::PARAM_INT);
        $ins->execute();
        $upd = $db->prepare("UPDATE cloud_user_settings SET hide_lang_switcher = :h, updated_at = extract(epoch from now())::bigint WHERE email = :e");
        $upd->bindValue(':e', $email, PDO::PARAM_STR);
        $upd->bindValue(':h', $hide, PDO::PARAM_INT);
        $upd->execute();
        json_out(['ok' => true, 'hide_lang_switcher' => $hide]);
    } catch (Exception $e) {
        json_out(['error' => $e->getMessage()], 500);
    }
}

if ($action === 'get_sync_log') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $user = get_current_user_from_sessions();
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensureCloudSyncLogsSchema($db);
    cloud_sync_logs_migrate_legacy($db, $email, $user);
    $stmt = $db->prepare("SELECT ts, level, root_key AS root, action, path, message FROM cloud_sync_logs WHERE email = :e ORDER BY ts DESC, id DESC LIMIT :lim OFFSET :off");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit + 1, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $res = $stmt->execute();
    $items = [];
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) $items[] = $row;
    $hasMore = count($items) > $limit;
    if ($hasMore) array_pop($items);
    json_out(['ok' => true, 'items' => $items, 'has_more' => $hasMore]);
}

if ($action === 'set_sync_profile') {
    json_out(['ok' => false, 'error' => 'forbidden'], 403);
}

if ($action === 'sync_manifest') {
    json_out([
        'ok' => false,
        'deprecated' => true,
        'error' => 'use_webdav',
        'bootstrap' => 'sync_bootstrap.php',
        'webdav_path' => '/webdav/',
        'protocol' => 'webdav',
    ], 410);
}

if ($action === 'client_token_generate') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    
    $deviceId = $_POST['device_id'] ?? '';
    $deviceName = $_POST['device_name'] ?? '';
    $platform = $_POST['platform'] ?? '';
    $expiresInDays = (int)($_POST['expires_in_days'] ?? 365);
    
    $result = generate_client_token($email, $deviceId, $deviceName, $platform, $expiresInDays);
    if (!empty($result['ok'])) {
        $result['username'] = $email;
                $result['webdav_url'] = (cloud_public_scheme() === 'https' ? 'webdavs://' : 'webdav://') . cloud_public_host() . '/webdav/';
    }
    json_out($result);
}

if ($action === 'client_token_revoke_all') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    
    $result = revoke_all_client_tokens($email);
    json_out($result);
}

if ($action === 'client_token_revoke') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);

    $tokenHash = trim((string)($_POST['token_hash'] ?? ''));
    $result = revoke_client_token($email, $tokenHash);
    json_out($result);
}

if ($action === 'client_token_list') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    
    $result = get_active_tokens($email);
    json_out($result);
}

if ($action === 'sync_manifest_client') {
    json_out([
        'ok' => false,
        'deprecated' => true,
        'error' => 'use_webdav',
        'bootstrap' => 'sync_bootstrap.php',
        'webdav_path' => '/webdav/',
        'protocol' => 'webdav',
    ], 410);
}

if ($action === 'rename') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  $old_path = safe_path((string)($_POST['old_path'] ?? ''));
  $new_name = trim((string)($_POST['new_name'] ?? ''));
  if ($old_path==='' || $new_name==='') json_out(['error'=>'bad params'], 400);
  if (($nameError = cloud_validate_file_name($new_name)) !== null) json_out(['error'=>$nameError], 400);
  // clientsync root removed
  $base = user_root($user, $rootKey);
  $old_dir = dirname($old_path);
  $new_path = ($old_dir === '.' || $old_dir === '') ? $new_name : ($old_dir . '/' . $new_name);
  $old_full = $base . '/' . $old_path;
  $new_full = $base . '/' . $new_path;
  if (!file_exists($old_full)) json_out(['error'=>'not found'], 404);
  if (file_exists($new_full)) json_out(['error'=>'exists'], 409);
  if (!rename($old_full, $new_full)) json_out(['error'=>'rename failed'], 500);
  $email = get_session_email();
  if ($email && $rootKey === 'main') {
    patch_file_tree_cache_rename($email, $old_path, $new_path, $new_name);
  }
  if ($email) append_sync_log($email, $rootKey, 'rename', $new_path, 'INFO', 'Path renamed', ['from' => $old_path]);
  cloud_group_maybe_log('rename', $new_path);
  json_out(['ok'=>true]);
}

function cloud_write_stored_zip(string $full, array $files): bool {
  $local = '';
  $central = '';
  $offset = 0;
  $dosTime = 0;
  $dosDate = 0x0021;
  foreach ($files as $name => $data) {
    $name = str_replace('\\', '/', (string)$name);
    $data = (string)$data;
    $crc = crc32($data);
    $size = strlen($data);
    $nameLen = strlen($name);
    $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0) . $name;
    $local .= $localHeader . $data;
    $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset) . $name;
    $offset += strlen($localHeader) + $size;
  }
  $centralOffset = strlen($local);
  $centralSize = strlen($central);
  $count = count($files);
  $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $centralSize, $centralOffset, 0);
  return file_put_contents($full, $local . $central . $end) !== false;
}

function cloud_create_initial_file(string $full, string $ext): bool {
  $ext = strtolower($ext);
  if ($ext === 'docx') {
    return cloud_write_stored_zip($full, [
      '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
      '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
      'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p/><w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr></w:body></w:document>',
    ]);
  }
  if ($ext === 'xlsx') {
    return cloud_write_stored_zip($full, [
      '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
      '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
      'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>',
      'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
      'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData/></worksheet>',
      'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>',
    ]);
  }
  if ($ext === 'pptx') {
    return cloud_write_stored_zip($full, [
      '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/></Types>',
      '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>',
      'ppt/presentation.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:sldIdLst><p:sldId id="256" r:id="rId1"/></p:sldIdLst><p:sldSz cx="9144000" cy="6858000" type="screen4x3"/></p:presentation>',
      'ppt/_rels/presentation.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/></Relationships>',
      'ppt/slides/slide1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr></p:spTree></p:cSld></p:sld>',
    ]);
  }
  if ($ext === 'rtf') {
    return file_put_contents($full, "{\\rtf1\\ansi\\deff0\n}\n") !== false;
  }
  return touch($full);
}

if ($action === 'create_file') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  $base = user_root($user, $rootKey);
  $path = safe_path((string)($_POST['path'] ?? ''));
  
  
  if ($path==='') json_out(['error'=>'path required'], 400);
  if (($nameError = cloud_validate_relative_path_names($path)) !== null) json_out(['error'=>$nameError], 400);
  $full = $base . '/' . $path;
  

  
  if (file_exists($full)) {
    json_out(['error'=>'exists'], 409);
  }
  
  // Create the directory if it does not exist
  $dir = dirname($full);
  if (file_exists($dir) && !is_dir($dir)) {
    json_out(['error'=>'parent is not directory'], 409);
  }
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if (!cloud_create_initial_file($full, $ext)) {
    json_out(['error'=>'create failed'], 500);
  }

  $email = get_session_email();
  // Only invalidate cache for main root, not for hidden roots
  if ($email && $rootKey === 'main') invalidate_file_tree($email);
  if ($email) append_sync_log($email, $rootKey, 'create_file', $path, 'INFO', 'File created');
  cloud_group_maybe_log('create', $path);
  json_out(['ok'=>true]);
}

function cloud_video_jobs_dir(): string {
    $dir = upload_sessions_dir() . '/video_jobs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}
function cloud_video_tmp_dir(): string {
    $base = is_dir('/opt/ApplicationData') && is_writable('/opt/ApplicationData') ? '/opt/ApplicationData' : upload_sessions_dir();
    $dir = $base . '/cloud_video_tmp';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}
function cloud_video_job_file(string $jobId): string {
    return cloud_video_jobs_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $jobId) . '.json';
}
function cloud_video_write_job(string $jobId, array $data): void {
    $file = cloud_video_job_file($jobId);
    $current = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $data['updated_at'] = time();
    if (@file_put_contents($file, json_encode(array_merge($current, $data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
        throw new RuntimeException('Video job storage is not writable');
    }
}
function cloud_video_log(string $jobId, string $message, array $context = []): void {
    $line = json_encode(['time' => date('c'), 'job' => $jobId, 'message' => $message, 'context' => $context], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @file_put_contents(cloud_video_tmp_dir() . '/video_generation.log', $line . PHP_EOL, FILE_APPEND);
}
function cloud_video_hyperframes_env(): array {
    $home = cloud_video_tmp_dir() . '/.hyperframes-home';
    $cache = cloud_video_tmp_dir() . '/.hyperframes-cache';
    if (!is_dir($home)) @mkdir($home, 0775, true);
    if (!is_dir($cache)) @mkdir($cache, 0775, true);
    return ['HOME=' . $home, 'XDG_CACHE_HOME=' . $cache, 'npm_config_cache=' . $cache . '/npm', 'HYPERFRAMES_TELEMETRY_DISABLED=1', 'HF_DE_PARALLEL_ROUTER=false'];
}
function cloud_video_command_path(string $command): string {
    if ($command === 'php') {
        foreach (['/usr/local/bin/php', PHP_BINARY, '/usr/bin/php'] as $path) {
            if (is_string($path) && is_file($path) && is_executable($path)) return $path;
        }
    }
    if ($command === 'hyperframes') {
        foreach (['/usr/local/bin/hyperframes', '/usr/bin/hyperframes'] as $path) {
            if (is_file($path) && is_executable($path)) return $path;
        }
    }
    foreach (['/usr/bin/' . $command, '/usr/local/bin/' . $command, '/bin/' . $command] as $path) {
        if (is_file($path) && is_executable($path)) return $path;
    }
    $out = [];
    $code = 1;
    @exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null', $out, $code);
    return ($code === 0 && !empty($out[0])) ? (string)$out[0] : '';
}
function cloud_video_node_major(): int {
    $node = cloud_video_command_path('node');
    if ($node === '') return 0;
    $out = [];
    $code = 1;
    @exec(escapeshellarg($node) . ' --version 2>/dev/null', $out, $code);
    if ($code !== 0 || empty($out[0]) || !preg_match('/v(\d+)/', (string)$out[0], $m)) return 0;
    return (int)$m[1];
}
function cloud_video_normalize_size(int $width, int $height): array {
    $width = max(320, min(1280, $width));
    $height = max(320, min(1280, $height));
    $maxPixels = 921600;
    if ($width * $height > $maxPixels) {
        $scale = sqrt($maxPixels / ($width * $height));
        $width = max(320, (int)(floor(($width * $scale) / 2) * 2));
        $height = max(320, (int)(floor(($height * $scale) / 2) * 2));
    }
    return [$width, $height];
}
function cloud_video_extract_json(string $content): ?array {
    $content = trim($content);
    if ($content === '') return null;
    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $content, $m)) {
        $content = trim($m[1]);
    }
    $decoded = json_decode($content, true);
    if (is_array($decoded)) return $decoded;
    $start = strpos($content, '{');
    $end = strrpos($content, '}');
    if ($start === false || $end === false || $end <= $start) return null;
    $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
    return is_array($decoded) ? $decoded : null;
}
function cloud_video_prompt_composition(array $payload): array {
    $prompt = trim((string)($payload['prompt'] ?? 'Generated video'));
    $title = preg_replace('/\s+/', ' ', mb_substr($prompt, 0, 72));
    $duration = max(3, min(60, (int)($payload['duration'] ?? 15)));
    $parts = preg_split('/[.!?\n]+/u', $prompt) ?: [];
    $parts = array_values(array_filter(array_map(static fn($part) => trim((string)$part), $parts)));
    if (!$parts) $parts = [$title, 'A clear visual story', 'Ready to share'];
    $sceneCount = min(5, max(3, count($parts)));
    $sceneDuration = max(1, (int)floor($duration / $sceneCount));
    $scenes = [];
    for ($i = 0; $i < $sceneCount; $i++) {
        $text = $parts[$i] ?? ($i === 0 ? $title : 'Generated video');
        $scenes[] = ['type' => $i === 0 ? 'title' : ($i === $sceneCount - 1 ? 'outro' : 'text'), 'text' => mb_substr($text, 0, 120), 'duration' => $sceneDuration];
    }
    $scenes[$sceneCount - 1]['duration'] += max(0, $duration - ($sceneDuration * $sceneCount));
    return cloud_video_validate_composition(['title' => $title ?: 'Generated Video', 'duration' => $duration, 'width' => $payload['width'], 'height' => $payload['height'], 'fps' => $payload['fps'], 'scenes' => $scenes], $payload);
}
function cloud_video_completion(array $payload): array {
    $config = cloud_ai_load_config();
    if (trim((string)$config['api_key']) === '') throw new RuntimeException('AI API key is not configured');
    $model = (string)$config['default_model'];
    if (!empty($payload['model']) && in_array((string)$payload['model'], (array)($config['models'] ?? []), true)) {
        $model = (string)$payload['model'];
    }
    $prompt = (string)$payload['prompt'];
    $duration = (int)$payload['duration'];
    [$width, $height] = cloud_video_normalize_size((int)$payload['width'], (int)$payload['height']);
    $fps = (int)$payload['fps'];
    $request = [
        'model' => cloud_ai_normalize_model_id($model),
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.2,
        'messages' => [
            ['role' => 'system', 'content' => 'You generate deterministic HyperFrames-style video composition JSON. Return one JSON object only. No markdown. Schema: {"title":"short filename safe title","duration":number,"width":number,"height":number,"fps":number,"style":{"background":"css gradient","accent":"css color","textColor":"css color","mood":"premium|technical|playful|cinematic"},"scenes":[{"type":"title|interior|device|data|robot|assistant|security|energy|environment|book|food|travel|nature|product|outro","visual":"home|books|food|travel|nature|product|education|technology|abstract","text":"short on-screen headline matching the user prompt","props":["2-4 short visual props from the prompt"],"elements":[{"kind":"book|page|shelf|lamp|cup|window|curtain|light|panel|robot|thermostat|energy|plate|fruit|spice|sea|sun|table|leaf|product|card|orb","label":"short object label from the prompt","x":number,"y":number,"w":number,"h":number,"rotate":number,"color":"css color","animation":"float|drift|reveal|slide|zoom|pulse|turn|sway|none"}],"duration":number,"tone":"calm|bold|premium|technical","layout":"center|left|right|split","motion":"rise|slide|zoom|wipe|scale"}]}. The model must design the scene itself: choose visual, props, and every element from the user prompt. The model may only select element animation names from the fixed animation enum; do not invent animation names. x,y,w,h are percentages of the frame. Do not use generic widgets, code panels, app screens, browser windows, API panels, dashboards, spinning badges, or background grids unless the user explicitly asks for them. For books use shelves/pages/covers/lamps/cups. For smart home use windows/curtains/lights/thermostat/energy/robot. Do not create subtitles or watermarks. Scene durations must add up to requested duration.'],
            ['role' => 'user', 'content' => json_encode(['prompt' => $prompt, 'duration' => $duration, 'width' => $width, 'height' => $height, 'fps' => $fps, 'assets' => array_values($payload['assets'] ?? []), 'requirements' => ['HyperFrames-compatible HTML/CSS composition will be generated from this JSON', 'scene text and all elements must match the requested topic', 'each scene must include 3-6 custom elements', 'valid JSON only']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ],
    ];
    $result = cloud_ai_proxy_json($config, $config['api_endpoint'], $request, 'POST');
    if (!$result['ok']) throw new RuntimeException($result['error'] ?: ('AI request failed: HTTP ' . $result['http']));
    $decoded = json_decode($result['body'], true);
    $content = $decoded['choices'][0]['message']['content'] ?? '';
    $composition = cloud_video_extract_json((string)$content);
    if (isset($composition['composition']) && is_array($composition['composition'])) $composition = $composition['composition'];
    if (!is_array($composition)) return cloud_video_prompt_composition($payload);
    return cloud_video_validate_composition($composition, $payload);
}
function cloud_video_validate_composition(array $composition, array $payload): array {
    $duration = max(3, min((int)$payload['duration'], (int)($composition['duration'] ?? $payload['duration'])));
    [$payloadWidth, $payloadHeight] = cloud_video_normalize_size((int)$payload['width'], (int)$payload['height']);
    [$width, $height] = cloud_video_normalize_size(max(320, min($payloadWidth, (int)($composition['width'] ?? $payloadWidth))), max(320, min($payloadHeight, (int)($composition['height'] ?? $payloadHeight))));
    $fps = max(12, min((int)$payload['fps'], (int)($composition['fps'] ?? $payload['fps'])));
    $title = trim((string)($composition['title'] ?? 'Generated Video'));
    $style = is_array($composition['style'] ?? null) ? $composition['style'] : [];
    $style = [
        'background' => mb_substr((string)($style['background'] ?? 'linear-gradient(135deg,#111827,#0f766e)'), 0, 120),
        'accent' => mb_substr((string)($style['accent'] ?? '#38bdf8'), 0, 40),
        'textColor' => mb_substr((string)($style['textColor'] ?? '#ffffff'), 0, 40),
    ];
    $scenes = [];
    foreach (($composition['scenes'] ?? []) as $scene) {
        if (!is_array($scene)) continue;
        $type = (string)($scene['type'] ?? 'text');
        if (!in_array($type, ['title', 'text', 'image', 'interior', 'device', 'data', 'robot', 'assistant', 'security', 'energy', 'environment', 'dashboard', 'book', 'food', 'travel', 'nature', 'product', 'outro'], true)) $type = 'text';
        $visual = (string)($scene['visual'] ?? ($type === 'book' ? 'books' : ($type === 'interior' ? 'home' : 'abstract')));
        if (!in_array($visual, ['home', 'books', 'food', 'travel', 'nature', 'product', 'education', 'technology', 'abstract'], true)) $visual = 'abstract';
        if ($type === 'book') $visual = 'books';
        if (in_array($type, ['interior', 'device', 'robot', 'assistant', 'security', 'energy'], true)) $visual = 'home';
        $text = mb_substr(trim((string)($scene['text'] ?? $title)), 0, 160);
        if ($text === '') $text = $title;
        $props = [];
        foreach ((array)($scene['props'] ?? []) as $prop) {
            $prop = mb_substr(trim((string)$prop), 0, 40);
            if ($prop !== '') $props[] = $prop;
            if (count($props) >= 4) break;
        }
        if (!$props) $props = [$text];
        $elements = [];
        $allowedKinds = ['book', 'page', 'shelf', 'lamp', 'cup', 'window', 'curtain', 'light', 'panel', 'robot', 'thermostat', 'energy', 'plate', 'fruit', 'spice', 'sea', 'sun', 'table', 'leaf', 'product', 'card', 'orb'];
        $allowedAnimations = ['float', 'drift', 'reveal', 'slide', 'zoom', 'pulse', 'turn', 'sway', 'none'];
        foreach ((array)($scene['elements'] ?? []) as $element) {
            if (!is_array($element)) continue;
            $kind = (string)($element['kind'] ?? 'card');
            if (!in_array($kind, $allowedKinds, true)) $kind = 'card';
            $label = mb_substr(trim((string)($element['label'] ?? '')), 0, 42);
            if ($label === '') $label = $props[count($elements) % count($props)] ?? $text;
            $x = max(2, min(82, (float)($element['x'] ?? (8 + count($elements) * 18))));
            $y = max(4, min(82, (float)($element['y'] ?? (16 + count($elements) * 12))));
            $w = max(10, min(44, (float)($element['w'] ?? 22)));
            $h = max(6, min(34, (float)($element['h'] ?? 16)));
            $rotate = max(-28, min(28, (float)($element['rotate'] ?? 0)));
            $color = mb_substr((string)($element['color'] ?? $style['accent']), 0, 40);
            $animation = (string)($element['animation'] ?? 'float');
            if (!in_array($animation, $allowedAnimations, true)) $animation = 'float';
            $elements[] = compact('kind', 'label', 'x', 'y', 'w', 'h', 'rotate', 'color', 'animation');
            if (count($elements) >= 6) break;
        }
        if (!$elements) {
            foreach (array_slice($props, 0, 4) as $i => $prop) {
                $kind = $visual === 'books' ? ['book', 'page', 'shelf', 'lamp'][$i % 4] : ($visual === 'home' ? ['window', 'curtain', 'light', 'robot'][$i % 4] : 'card');
                $label = $prop;
                $x = 8 + $i * 19;
                $y = 18 + ($i % 2) * 28;
                $w = 20;
                $h = 16;
                $rotate = ($i - 1) * 5;
                $color = $style['accent'];
                $animationMap = ['book' => 'turn', 'page' => 'sway', 'shelf' => 'reveal', 'lamp' => 'pulse', 'cup' => 'float', 'window' => 'reveal', 'curtain' => 'sway', 'light' => 'pulse', 'robot' => 'slide'];
                $animation = $animationMap[$kind] ?? 'float';
                $elements[] = compact('kind', 'label', 'x', 'y', 'w', 'h', 'rotate', 'color', 'animation');
            }
        }
        $layout = (string)($scene['layout'] ?? 'center');
        if (!in_array($layout, ['center', 'left', 'right', 'split'], true)) $layout = 'center';
        $motion = (string)($scene['motion'] ?? 'rise');
        if (!in_array($motion, ['rise', 'slide', 'zoom', 'wipe', 'scale'], true)) $motion = 'rise';
        $scenes[] = ['type' => $type, 'visual' => $visual, 'text' => $text, 'props' => $props, 'elements' => $elements, 'duration' => max(1, min(12, (int)($scene['duration'] ?? 3))), 'tone' => mb_substr((string)($scene['tone'] ?? 'bold'), 0, 24), 'layout' => $layout, 'motion' => $motion];
        if (count($scenes) >= 12) break;
    }
    if (!$scenes) $scenes[] = ['type' => 'title', 'text' => $title, 'duration' => $duration];
    $sum = array_sum(array_map(static fn($scene) => (int)$scene['duration'], $scenes));
    if ($sum !== $duration && $sum > 0) {
        $scale = $duration / $sum;
        $used = 0;
        foreach ($scenes as $i => $scene) {
            $scenes[$i]['duration'] = max(1, (int)round((int)$scene['duration'] * $scale));
            $used += $scenes[$i]['duration'];
        }
        $scenes[count($scenes) - 1]['duration'] += $duration - $used;
        if ($scenes[count($scenes) - 1]['duration'] < 1) $scenes[count($scenes) - 1]['duration'] = 1;
    }
    return ['title' => mb_substr($title ?: 'Generated Video', 0, 80), 'duration' => $duration, 'width' => $width, 'height' => $height, 'fps' => $fps, 'style' => $style, 'scenes' => $scenes];
}
function cloud_video_unique_path(string $base, string $folder, string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._ -]+/', '-', $name) ?: 'generated-video';
    $name = trim($name, ". \t\r\n");
    if ($name === '') $name = 'generated-video';
    $name = mb_substr($name, 0, 80);
    $dir = $folder === '' ? $base : $base . '/' . $folder;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $path = ($folder === '' ? '' : $folder . '/') . $name . '.mp4';
    $i = 2;
    while (file_exists($base . '/' . $path)) {
        $path = ($folder === '' ? '' : $folder . '/') . $name . '-' . $i . '.mp4';
        $i++;
    }
    return $path;
}
function cloud_video_delete_dir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}
function cloud_video_html(array $composition): string {
    $width = (int)$composition['width'];
    $height = (int)$composition['height'];
    $duration = (int)$composition['duration'];
    $scenes = '';
    $timeline = 'const tl=gsap.timeline({paused:true});tl.set(".scene",{autoAlpha:0});';
    $start = 0;
    $style = is_array($composition['style'] ?? null) ? $composition['style'] : [];
    $background = htmlspecialchars((string)($style['background'] ?? 'linear-gradient(135deg,#111827,#0f766e)'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $accent = htmlspecialchars((string)($style['accent'] ?? '#38bdf8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $textColor = htmlspecialchars((string)($style['textColor'] ?? '#ffffff'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    foreach ($composition['scenes'] as $index => $scene) {
        $sceneDuration = max(1, (int)($scene['duration'] ?? 3));
        $text = htmlspecialchars((string)($scene['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $kicker = htmlspecialchars(strtoupper((string)($scene['type'] ?? 'scene')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $type = htmlspecialchars((string)($scene['type'] ?? 'text'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $visualType = htmlspecialchars((string)($scene['visual'] ?? 'abstract'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $layout = htmlspecialchars((string)($scene['layout'] ?? 'center'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $motion = htmlspecialchars((string)($scene['motion'] ?? 'rise'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $elementsHtml = '';
        foreach ((array)($scene['elements'] ?? []) as $element) {
            if (!is_array($element)) continue;
            $kind = htmlspecialchars((string)($element['kind'] ?? 'card'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $label = htmlspecialchars((string)($element['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $color = htmlspecialchars((string)($element['color'] ?? $accent), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $x = (float)($element['x'] ?? 10);
            $y = (float)($element['y'] ?? 10);
            $w = (float)($element['w'] ?? 20);
            $h = (float)($element['h'] ?? 14);
            $rotate = (float)($element['rotate'] ?? 0);
            $animation = htmlspecialchars((string)($element['animation'] ?? 'float'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $elementsHtml .= '<div class="el el-' . $kind . ' anim-' . $animation . '" data-anim="' . $animation . '" style="--x:' . $x . ';--y:' . $y . ';--w:' . $w . ';--h:' . $h . ';--r:' . $rotate . 'deg;--c:' . $color . '"><span>' . $label . '</span></div>';
        }
        $visual = '<div class="visual visual-' . $visualType . ' scene-kind-' . $type . '">' . $elementsHtml . '</div>';
        $card = '<div class="scene-card"><span>' . $kicker . '</span><h1>' . $text . '</h1><i></i></div>';
        $scenes .= '<section class="clip scene scene-' . ($index % 4) . ' layout-' . $layout . ' motion-' . $motion . '" data-start="' . $start . '" data-duration="' . $sceneDuration . '" data-track-index="' . ($index + 1) . '">' . $visual . $card . '</section>';
        $selector = '.scene:nth-of-type(' . ($index + 1) . ')';
        $timeline .= 'tl.set("' . $selector . '",{autoAlpha:1},' . $start . ');';
        $timeline .= 'tl.fromTo("' . $selector . ' .scene-card",{autoAlpha:0,y:90,scale:.96},{autoAlpha:1,y:0,scale:1,duration:.7,ease:"power3.out"},' . $start . ');';
        $timeline .= 'tl.fromTo("' . $selector . ' h1",{autoAlpha:0,y:35,scale:.94},{autoAlpha:1,y:0,scale:1,duration:.8,ease:"power3.out"},' . ($start + 0.12) . ');';
        $timeline .= 'tl.fromTo("' . $selector . ' .el",{autoAlpha:0,y:42,scale:.82},{autoAlpha:1,y:0,scale:1,duration:' . min(1.1, max(.65, $sceneDuration / 3)) . ',stagger:.08,ease:"power3.out"},' . $start . ');';
        $timeline .= 'tl.to("' . $selector . ' .anim-float",{y:"-=26",duration:' . $sceneDuration . ',yoyo:true,repeat:1,ease:"sine.inOut"},' . ($start + 0.2) . ');';
        $timeline .= 'tl.to("' . $selector . ' .anim-drift",{x:"+=42",y:"-=18",duration:' . $sceneDuration . ',ease:"none"},' . $start . ');';
        $timeline .= 'tl.fromTo("' . $selector . ' .anim-slide",{x:"-=80"},{x:"+=34",duration:' . $sceneDuration . ',ease:"power1.inOut"},' . $start . ');';
        $timeline .= 'tl.fromTo("' . $selector . ' .anim-zoom",{scale:.72},{scale:1.08,duration:' . $sceneDuration . ',ease:"power2.out"},' . $start . ');';
        $timeline .= 'tl.to("' . $selector . ' .anim-pulse",{scale:1.12,filter:"brightness(1.22)",duration:.75,yoyo:true,repeat:' . max(1, $sceneDuration) . ',ease:"sine.inOut"},' . $start . ');';
        $timeline .= 'tl.to("' . $selector . ' .anim-turn",{rotation:"+=18",duration:' . $sceneDuration . ',yoyo:true,repeat:1,ease:"sine.inOut"},' . $start . ');';
        $timeline .= 'tl.to("' . $selector . ' .anim-sway",{rotation:"+=10",x:"+=12",duration:1.2,yoyo:true,repeat:' . max(1, $sceneDuration) . ',ease:"sine.inOut"},' . $start . ');';
        $timeline .= 'tl.fromTo("' . $selector . ' .anim-reveal",{clipPath:"inset(0 100% 0 0)"},{clipPath:"inset(0 0% 0 0)",duration:1,ease:"power2.out"},' . $start . ');';
        $timeline .= 'tl.to("' . $selector . ' .scene-card",{autoAlpha:0,y:-50,scale:1.03,duration:.45,ease:"power2.in"},' . max($start, $start + $sceneDuration - 0.55) . ');';
        $timeline .= 'tl.set("' . $selector . '",{autoAlpha:0},' . ($start + $sceneDuration) . ');';
        $start += $sceneDuration;
        if ($start >= $duration) break;
    }
    $timeline .= 'tl.set(".scene:last-of-type",{autoAlpha:1},' . max(0, $duration - 0.1) . ');window.__timelines=window.__timelines||{};window.__timelines["cloud-video"]=tl;';
    return '<!doctype html><html><head><meta charset="utf-8"><script src="https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js"></script><style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#050510;color:' . $textColor . ';font-family:Inter,Arial,sans-serif}#stage{position:relative;width:' . $width . 'px;height:' . $height . 'px;background:' . $background . ';isolation:isolate;overflow:hidden;perspective:1200px}#stage:before{content:"";position:absolute;inset:-12%;background:radial-gradient(circle at 22% 18%,color-mix(in srgb,' . $accent . ' 34%,transparent),transparent 28%),radial-gradient(circle at 76% 72%,rgba(255,255,255,.16),transparent 24%);animation:bgDrift 11s ease-in-out infinite alternate;z-index:-1}.scene{position:absolute;inset:0;display:grid;place-items:center;padding:7%;box-sizing:border-box;overflow:hidden;visibility:hidden;opacity:0}.scene-card{max-width:86%;display:grid;gap:16px;justify-items:center;text-align:center;position:relative;z-index:5}.scene-card i{display:block;width:min(26vw,150px);height:5px;border-radius:999px;background:' . $accent . ';box-shadow:0 0 34px ' . $accent . '}.scene span{font-size:min(2vw,22px);font-weight:800;letter-spacing:.1em;color:' . $accent . '}.scene h1{font-size:min(6.4vw,70px);line-height:1.05;text-align:center;margin:0;text-wrap:balance;text-shadow:0 18px 60px rgba(0,0,0,.55)}.visual{position:absolute;inset:4%;transform-style:preserve-3d}.el{position:absolute;left:calc(var(--x)*1%);top:calc(var(--y)*1%);width:calc(var(--w)*1%);height:calc(var(--h)*1%);min-width:64px;min-height:42px;max-width:46%;display:grid;place-items:center;padding:10px;box-sizing:border-box;border:1px solid color-mix(in srgb,var(--c) 46%,rgba(255,255,255,.28));background:linear-gradient(135deg,color-mix(in srgb,var(--c) 34%,rgba(255,255,255,.18)),rgba(255,255,255,.08));box-shadow:0 26px 70px rgba(0,0,0,.34),0 0 32px color-mix(in srgb,var(--c) 28%,transparent);transform:rotate(var(--r));overflow:hidden}.el:before{content:"";position:absolute;inset:9%;border-radius:inherit;background:radial-gradient(circle at 30% 20%,rgba(255,255,255,.45),transparent 36%);opacity:.5}.el span{position:relative;z-index:1;font-size:min(2vw,18px);line-height:1.15;text-align:center;text-transform:none;letter-spacing:0;color:' . $textColor . ';overflow-wrap:anywhere}.el-book,.el-page{border-radius:4px 14px 14px 4px}.el-book:after,.el-page:after{content:"";position:absolute;left:18%;top:0;bottom:0;width:2px;background:rgba(255,255,255,.34)}.el-shelf{border-radius:6px;height:max(calc(var(--h)*1%),64px)}.el-lamp,.el-light,.el-sun,.el-orb{border-radius:999px}.el-lamp:after,.el-light:after,.el-sun:after{content:"";position:absolute;inset:-20%;border-radius:999px;background:radial-gradient(circle,color-mix(in srgb,var(--c) 46%,transparent),transparent 62%);filter:blur(12px);z-index:-1}.el-cup,.el-plate,.el-fruit,.el-spice{border-radius:999px 999px 18px 18px}.el-window{border-radius:20px;background:linear-gradient(90deg,transparent 48%,rgba(255,255,255,.35) 49% 51%,transparent 52%),linear-gradient(0deg,transparent 48%,rgba(255,255,255,.28) 49% 51%,transparent 52%),linear-gradient(135deg,rgba(186,230,253,.32),rgba(255,255,255,.08))}.el-curtain{border-radius:30px 8px 30px 8px}.el-robot{border-radius:999px}.el-robot:after{content:"";position:absolute;width:26%;height:26%;border-radius:999px;background:' . $textColor . ';box-shadow:28px 0 0 ' . $textColor . ';top:28%;left:24%;opacity:.8}.el-table,.el-card,.el-panel,.el-product{border-radius:16px}.el-sea{border-radius:999px 999px 18px 18px;background:linear-gradient(180deg,rgba(125,211,252,.42),rgba(14,165,233,.18))}.el-leaf{border-radius:999px 0 999px 0}.layout-left .scene-card{justify-items:start;text-align:left;margin-right:auto}.layout-right .scene-card{justify-items:end;text-align:right;margin-left:auto}.layout-split .scene-card{justify-items:start;text-align:left;max-width:62%;margin-right:auto}@keyframes bgDrift{from{transform:translate3d(-2%,-2%,0) scale(1)}to{transform:translate3d(2%,3%,0) scale(1.05)}}</style></head><body><div id="stage" data-composition-id="cloud-video" data-start="0" data-duration="' . $duration . '" data-fps="' . (int)($composition['fps'] ?? 24) . '" data-width="' . $width . '" data-height="' . $height . '">' . $scenes . '</div><script>' . $timeline . '</script></body></html>';
}
function cloud_video_render_with_hyperframes(string $jobId, array $composition, string $output): bool {
    $hyperframes = cloud_video_command_path('hyperframes');
    if ($hyperframes === '') throw new RuntimeException('HyperFrames is not installed in the appdashboard container');
    if (cloud_video_node_major() < 22) throw new RuntimeException('Node.js 22+ is required inside the appdashboard container for HyperFrames rendering');
    $lock = fopen(cloud_video_tmp_dir() . '/video_generation.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another video is rendering. Wait until it finishes and start again.');
    }
    $work = cloud_video_tmp_dir() . '/' . $jobId . '_hyperframes';
    if (!is_dir($work)) @mkdir($work, 0775, true);
    file_put_contents($work . '/index.html', cloud_video_html($composition));
    $renderedOutput = $work . '/out.mp4';
    $fps = max(12, min(60, (int)($composition['fps'] ?? 24)));
    $cmd = array_merge(['env'], cloud_video_hyperframes_env(), [$hyperframes, 'render', '--no-browser-gpu', '--workers=1', '--fps=' . $fps, '-o', $renderedOutput]);
    cloud_video_log($jobId, 'hyperframes_start', ['cmd' => $cmd, 'work' => $work, 'node_major' => cloud_video_node_major()]);
    $pipes = [];
    $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $work);
    if (!is_resource($proc)) {
        cloud_video_log($jobId, 'hyperframes_proc_open_failed');
        cloud_video_delete_dir($work);
        flock($lock, LOCK_UN);
        return false;
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $lastProgress = 55;
    $lastWrite = time();
    $started = time();
    $lastFrame = 0;
    $totalFrames = 1;
    while (true) {
        $stdoutChunk = (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        if ($stdoutChunk !== '') $stdout = substr($stdout . $stdoutChunk, -12000);
        if (preg_match_all('/Streaming frame\s+(\d+)\/(\d+)/', $stdoutChunk, $m, PREG_SET_ORDER)) {
            $last = end($m);
            $lastFrame = max($lastFrame, (int)$last[1]);
            $totalFrames = max($totalFrames, (int)$last[2]);
            $progress = min(88, 55 + (int)floor(($lastFrame / $totalFrames) * 33));
            if ($progress > $lastProgress || time() - $lastWrite >= 10) {
                $lastProgress = $progress;
                $lastWrite = time();
                cloud_video_write_job($jobId, ['status' => 'rendering', 'progress' => $progress]);
            }
        } elseif (str_contains($stdoutChunk, 'Assembling final video') && $lastProgress < 90) {
            $lastProgress = 90;
            $lastWrite = time();
            cloud_video_write_job($jobId, ['status' => 'rendering', 'progress' => 90]);
        } elseif (time() - $lastWrite >= 10) {
            $lastWrite = time();
            cloud_video_write_job($jobId, ['status' => 'rendering', 'progress' => $lastProgress]);
        }
        $status = proc_get_status($proc);
        if (!$status['running']) break;
        if (time() - $started > 1200) {
            proc_terminate($proc);
            sleep(1);
            $status = proc_get_status($proc);
            if ($status['running']) proc_terminate($proc, 9);
            cloud_video_log($jobId, 'hyperframes_timeout', ['stdout' => substr($stdout, -4000), 'stderr' => substr($stderr, -4000)]);
            cloud_video_delete_dir($work);
            flock($lock, LOCK_UN);
            throw new RuntimeException('Video rendering timed out');
        }
        usleep(250000);
    }
    $stdout .= (string)stream_get_contents($pipes[1]);
    $stderr .= (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) {
        cloud_video_log($jobId, 'hyperframes_failed', ['code' => $code, 'stdout' => substr($stdout, -4000), 'stderr' => substr($stderr, -4000)]);
        cloud_video_delete_dir($work);
        flock($lock, LOCK_UN);
        throw new RuntimeException(trim($stderr) ?: 'HyperFrames render failed');
    }
    if (!is_file($renderedOutput) || !@rename($renderedOutput, $output)) {
        cloud_video_log($jobId, 'hyperframes_missing_output', ['stdout' => substr($stdout, -4000), 'stderr' => substr($stderr, -4000)]);
        cloud_video_delete_dir($work);
        flock($lock, LOCK_UN);
        throw new RuntimeException('HyperFrames did not produce an MP4 file');
    }
    cloud_video_log($jobId, 'hyperframes_done', ['output' => $output, 'size' => filesize($output)]);
    cloud_video_delete_dir($work);
    flock($lock, LOCK_UN);
    return is_file($output) && filesize($output) > 0;
}
function cloud_video_worker_run(string $jobId, string $token): void {
    $file = cloud_video_job_file($jobId);
    $job = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    cloud_video_log($jobId, 'worker_started', ['pid' => getmypid(), 'php' => PHP_BINARY]);
    if (($job['worker_token'] ?? '') !== $token) {
        cloud_video_log($jobId, 'worker_token_mismatch');
        return;
    }
    $payload = $job['payload'] ?? [];
    try {
        cloud_video_write_job($jobId, ['status' => 'preparing', 'progress' => 10]);
        $user = (string)$payload['user'];
        $rootKey = (string)$payload['root'];
        $base = user_root($user, $rootKey);
        $folder = safe_path((string)$payload['folder']);
        if ($folder !== '' && !is_dir($base . '/' . $folder)) throw new RuntimeException('Folder not found');
        cloud_video_write_job($jobId, ['status' => 'generating_composition', 'progress' => 30]);
        $composition = cloud_video_completion($payload);
        cloud_video_write_job($jobId, ['status' => 'rendering', 'progress' => 55, 'composition' => $composition]);
        $tmp = cloud_video_tmp_dir() . '/' . $jobId . '.mp4';
        cloud_video_render_with_hyperframes($jobId, $composition, $tmp);
        cloud_video_write_job($jobId, ['status' => 'uploading', 'progress' => 90]);
        $targetPath = cloud_video_unique_path($base, $folder, (string)$composition['title']);
        if (!@rename($tmp, $base . '/' . $targetPath)) throw new RuntimeException('Failed to save video');
        $email = (string)($payload['email'] ?? '');
        if ($email !== '' && $rootKey === 'main') invalidate_file_tree($email);
        if ($email !== '') append_sync_log($email, $rootKey, 'create_video', $targetPath, 'INFO', 'Video created');
        cloud_video_write_job($jobId, ['status' => 'completed', 'progress' => 100, 'fileId' => $targetPath, 'file_path' => $targetPath, 'file_name' => basename($targetPath), 'size' => filesize($base . '/' . $targetPath), 'payload' => [], 'worker_token' => '']);
    } catch (Throwable $e) {
        foreach (glob(cloud_video_tmp_dir() . '/' . $jobId . '*') ?: [] as $tmp) if (is_file($tmp)) @unlink($tmp);
        cloud_video_log($jobId, 'worker_failed', ['error' => $e->getMessage()]);
        cloud_video_write_job($jobId, ['status' => 'failed', 'progress' => 100, 'error' => $e->getMessage(), 'payload' => [], 'worker_token' => '']);
    }
}
if ($action === 'video_generation_runtime') {
    $hyperframes = cloud_video_command_path('hyperframes');
    json_out([
        'hyperframes' => $hyperframes,
        'hyperframes_exists' => $hyperframes !== '',
        'node_major' => cloud_video_node_major(),
        'php_binary' => PHP_BINARY,
        'sapi' => PHP_SAPI,
        'path' => getenv('PATH') ?: '',
    ]);
}
if ($action === 'video_generation_options') {
    $email = get_session_email();
    $role = getUserRoleByEmail($email);
    $canChooseModel = isUserRoot() || $role >= 1;
    $config = cloud_ai_load_config();
    json_out([
        'can_choose_model' => $canChooseModel,
        'default_model' => $canChooseModel ? (string)$config['default_model'] : '',
        'models' => $canChooseModel ? array_values((array)($config['models'] ?? [])) : [],
    ]);
}
if ($action === 'video_generation') {
    $user = get_current_user_from_sessions();
    $email = get_session_email();
    $rootKey = get_requested_root_key();
    cloud_group_require_write($rootKey);
    $folder = safe_path((string)($_POST['folderId'] ?? $_POST['folder'] ?? ''));
    $base = user_root($user, $rootKey);
    if ($folder !== '' && !is_dir($base . '/' . $folder)) json_out(['error' => 'Folder not found'], 404);
    $prompt = trim((string)($_POST['prompt'] ?? ''));
    if ($prompt === '') json_out(['error' => 'prompt required'], 400);
    $duration = max(3, min(20, (int)($_POST['duration'] ?? 12)));
    [$width, $height] = cloud_video_normalize_size((int)($_POST['width'] ?? 720), (int)($_POST['height'] ?? 1280));
    $fps = max(12, min(24, (int)($_POST['fps'] ?? 24)));
    $model = '';
    $emailRole = getUserRoleByEmail($email);
    if (isUserRoot() || $emailRole >= 1) {
        $config = cloud_ai_load_config();
        $requestedModel = trim((string)($_POST['model'] ?? ''));
        if ($requestedModel !== '' && in_array($requestedModel, (array)($config['models'] ?? []), true)) $model = $requestedModel;
    }
    $assetIds = json_decode((string)($_POST['assetIds'] ?? '[]'), true);
    $assets = [];
    foreach (is_array($assetIds) ? $assetIds : [] as $asset) {
        $assetPath = safe_path((string)$asset);
        if ($assetPath !== '' && is_file($base . '/' . $assetPath)) $assets[] = $assetPath;
        if (count($assets) >= 12) break;
    }
    $jobId = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(16));
    cloud_video_write_job($jobId, ['id' => $jobId, 'status' => 'queued', 'progress' => 0, 'created_at' => time(), 'payload' => compact('user', 'email', 'rootKey', 'folder', 'prompt', 'duration', 'width', 'height', 'fps', 'assets', 'model') + ['root' => $rootKey], 'worker_token' => $token]);
    $php = cloud_video_command_path('php');
    $log = cloud_video_tmp_dir() . '/video_generation_worker.log';
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/api.php') . ' video_generation_worker ' . escapeshellarg($jobId) . ' ' . escapeshellarg($token) . ' >> ' . escapeshellarg($log) . ' 2>&1 &';
    cloud_video_log($jobId, 'worker_spawn', ['cmd' => $cmd, 'php' => $php, 'sapi_binary' => PHP_BINARY]);
    @exec($cmd);
    json_out(['jobId' => $jobId, 'status' => 'queued']);
}
if ($action === 'video_generation_status') {
    $jobId = preg_replace('/[^a-f0-9]/', '', (string)($_GET['jobId'] ?? $_GET['job_id'] ?? $_POST['jobId'] ?? $_POST['job_id'] ?? ''));
    $file = cloud_video_job_file($jobId);
    if ($jobId === '' || !is_file($file)) json_out(['error' => 'job not found'], 404);
    $job = json_decode((string)file_get_contents($file), true) ?: [];
    unset($job['worker_token'], $job['payload']);
    json_out($job);
}
if ($action === 'video_generation_worker' && PHP_SAPI === 'cli') {
    cloud_video_worker_run(preg_replace('/[^a-f0-9]/', '', (string)($_POST['job_id'] ?? '')), preg_replace('/[^a-f0-9]/', '', (string)($_POST['worker_token'] ?? '')));
    exit;
}

if ($action === 'view_word') {
    $user = get_current_user_from_sessions();
    $rootKey = get_requested_root_key();
    $base = user_root($user, $rootKey);
    $path = safe_path((string)($_GET['path'] ?? ''));
    $target = $base . '/' . $path;

     if (!file_exists($target)) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #1a1a1a; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
            .error-card { background: #2a2a2a; border-radius: 16px; padding: 48px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 40px rgba(0,0,0,0.3); border: 1px solid #3a3a3a; }
            .error-icon { width: 120px; height: 120px; margin: 0 auto 32px; fill: none; stroke: #ff6b6b; stroke-width: 2; }
            .error-icon svg { width: 100%; height: 100%; }
            h2 { color: #ffffff; font-size: 24px; font-weight: 500; margin-bottom: 16px; }
            p { color: #9a9a9a; font-size: 16px; line-height: 1.5; margin-bottom: 32px; }
            .back-btn { background: #3a3a3a; border: none; color: #ffffff; font-size: 16px; font-weight: 500; padding: 12px 32px; border-radius: 8px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
            .back-btn:hover { background: #4a4a4a; }
            .back-btn svg { width: 20px; height: 20px; fill: none; stroke: #ffffff; stroke-width: 2; }
        </style></head><body>
        <div class="error-card">
            <div class="error-icon">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <circle cx="12" cy="16" r="1"/>
                </svg>
            </div>
            <h2>File not found</h2>
            <p>The document could not be opened. It may have been moved or deleted.</p>
            <button class="back-btn" onclick="window.close()">
                <svg viewBox="0 0 24 24">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                Close
            </button>
        </div>
        </body></html>';
        exit;
    }

    // Handle encrypted files in .documents folder
    $tempFile = null;
    if (is_documents_root($rootKey)) {
        $encryptedContent = file_get_contents($target);
        $decryptedContent = decrypt_documents_content($encryptedContent, $user);
        $tempFile = tempnam(sys_get_temp_dir(), 'word_view_') . '.' . pathinfo($path, PATHINFO_EXTENSION);
        file_put_contents($tempFile, $decryptedContent);
        $target = $tempFile;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Просмотр Word</title>';
    echo '<style>body,html{margin:0;padding:0;height:100%;overflow:auto;background:var(--bg);color:var(--fg);}</style>';
    echo '</head><body>';
    echo wordToHtml($target);
    echo '</body></html>';
    
    // Clean up temp file
    if ($tempFile && file_exists($tempFile)) {
        @unlink($tempFile);
    }
    exit;
}

if ($action === 'view_excel') {
    $user = get_current_user_from_sessions();
    $rootKey = get_requested_root_key();
    $base = user_root($user, $rootKey);
    $path = safe_path((string)($_GET['path'] ?? ''));
    $target = $base . '/' . $path;

     if (!file_exists($target)) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #1a1a1a; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
            .error-card { background: #2a2a2a; border-radius: 16px; padding: 48px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 40px rgba(0,0,0,0.3); border: 1px solid #3a3a3a; }
            .error-icon { width: 120px; height: 120px; margin: 0 auto 32px; fill: none; stroke: #ff6b6b; stroke-width: 2; }
            .error-icon svg { width: 100%; height: 100%; }
            h2 { color: #ffffff; font-size: 24px; font-weight: 500; margin-bottom: 16px; }
            p { color: #9a9a9a; font-size: 16px; line-height: 1.5; margin-bottom: 32px; }
            .back-btn { background: #3a3a3a; border: none; color: #ffffff; font-size: 16px; font-weight: 500; padding: 12px 32px; border-radius: 8px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
            .back-btn:hover { background: #4a4a4a; }
            .back-btn svg { width: 20px; height: 20px; fill: none; stroke: #ffffff; stroke-width: 2; }
        </style></head><body>
        <div class="error-card">
            <div class="error-icon">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <circle cx="12" cy="16" r="1"/>
                </svg>
            </div>
            <h2>File not found</h2>
            <p>The document could not be opened. It may have been moved or deleted.</p>
            <button class="back-btn" onclick="window.close()">
                <svg viewBox="0 0 24 24">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                Close
            </button>
        </div>
        </body></html>';
        exit;
    }

    // Handle encrypted files in .documents folder
    $tempFile = null;
    if (is_documents_root($rootKey)) {
        $encryptedContent = file_get_contents($target);
        $decryptedContent = decrypt_documents_content($encryptedContent, $user);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_view_') . '.' . pathinfo($path, PATHINFO_EXTENSION);
        file_put_contents($tempFile, $decryptedContent);
        $target = $tempFile;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo excelToHtml($target);
    
    // Clean up temp file
    if ($tempFile && file_exists($tempFile)) {
        @unlink($tempFile);
    }
    exit;
}

if ($action === 'view_presentation') {
    $user = get_current_user_from_sessions();
    $rootKey = get_requested_root_key();
    $base = user_root($user, $rootKey);
    $path = safe_path((string)($_GET['path'] ?? ''));
    $target = $base . '/' . $path;

     if (!file_exists($target)) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #1a1a1a; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
            .error-card { background: #2a2a2a; border-radius: 16px; padding: 48px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 20px 40px rgba(0,0,0,0.3); border: 1px solid #3a3a3a; }
            .error-icon { width: 120px; height: 120px; margin: 0 auto 32px; fill: none; stroke: #ff6b6b; stroke-width: 2; }
            .error-icon svg { width: 100%; height: 100%; }
            h2 { color: #ffffff; font-size: 24px; font-weight: 500; margin-bottom: 16px; }
            p { color: #9a9a9a; font-size: 16px; line-height: 1.5; margin-bottom: 32px; }
            .back-btn { background: #3a3a3a; border: none; color: #ffffff; font-size: 16px; font-weight: 500; padding: 12px 32px; border-radius: 8px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
            .back-btn:hover { background: #4a4a4a; }
            .back-btn svg { width: 20px; height: 20px; fill: none; stroke: #ffffff; stroke-width: 2; }
        </style></head><body>
        <div class="error-card">
            <div class="error-icon">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <circle cx="12" cy="16" r="1"/>
                </svg>
            </div>
            <h2>File not found</h2>
            <p>The document could not be opened. It may have been moved or deleted.</p>
            <button class="back-btn" onclick="window.close()">
                <svg viewBox="0 0 24 24">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                Close
            </button>
        </div>
        </body></html>';
        exit;
    }

    // Handle encrypted files in .documents folder
    $tempFile = null;
    if (is_documents_root($rootKey)) {
        $encryptedContent = file_get_contents($target);
        $decryptedContent = decrypt_documents_content($encryptedContent, $user);
        $tempFile = tempnam(sys_get_temp_dir(), 'presentation_view_') . '.' . pathinfo($path, PATHINFO_EXTENSION);
        file_put_contents($tempFile, $decryptedContent);
        $target = $tempFile;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Просмотр презентации</title>';
    echo '<style>body,html{margin:0;padding:0;height:100%;overflow:auto;background:var(--bg);color:var(--fg);}</style>';
    echo '</head><body>';
    echo presentationToHtml($target);
    echo '</body></html>';
    
    // Clean up temp file
    if ($tempFile && file_exists($tempFile)) {
        @unlink($tempFile);
    }
    exit;
}

if ($action === 'get_user_role') {
    $user = get_current_user_from_sessions();
    $is_root = isUserRoot();
    
    // Additionally get the email for debugging
    $db = getDB();
    $email = null;
    if ($db) {
        $session_id = session_id();
        if ($session_id) {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
            $stmt->bindValue(':session_id', $session_id, PDO::PARAM_STR);
            $result = $stmt->execute();
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $email = $row['email'];
            }
        }
    }
    
    json_out([
        'role' => $is_root ? 2 : 0,
        'user' => $user,
        'email' => $email,
        'is_root' => $is_root
    ]);
}

if ($action === 'save_file') {
  $user = get_current_user_from_sessions();
  $path = safe_path((string)($_POST['path'] ?? ''));
  $content = (string)($_POST['content'] ?? '');
  $baseRevision = trim((string)($_POST['base_revision'] ?? ''));
  $baseContent = (string)($_POST['base_content'] ?? '');

  if ($path==='') {
    json_out(['error'=>'path required'], 400);
  }

  // Determine rootKey from the path
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  $rootMap = [
      '.archive' => 'archive',
      '.temp_files' => 'temp',
      '.documents' => 'documents',
      '.photos' => 'photos',
      '.pdf' => 'pdf',
      '.trash' => 'trash'
  ];
  
  // If the path starts with one of the hidden directories, determine rootKey automatically
  foreach ($rootMap as $dirName => $key) {
      if (strpos($path, $dirName . '/') === 0 || $path === $dirName) {
          $rootKey = $key;
          // Strip the root directory name from the path
          $path = substr($path, strlen($dirName) + 1);
          break;
      }
  }
  
  $base = user_root($user, $rootKey);

  if ($path==='') {
    json_out(['error'=>'path required'], 400);
  }

  $full = $base . '/' . $path;
  
  if (!file_exists($full)) {
    json_out(['error'=>'not found'], 404);
  }
  
  // Remove the text file check for empty files
  $mime = is_documents_root($rootKey) ? 'text/plain' : mime_content_type($full);
  if ($mime === 'application/x-empty') {
  } else if (!is_text_mime($mime)) {
    json_out(['error'=>'not text file'], 400);
  }
  $currentContent = is_documents_root($rootKey) ? decrypt_documents_content((string)file_get_contents($full), $user) : (string)file_get_contents($full);
  $currentRevision = cloud_text_revision($currentContent);
  $finalContent = $content;
  $wasMerged = false;
  if ($baseRevision !== '' && $baseRevision !== $currentRevision) {
    $merged = cloud_merge_text_three_way($baseContent, $currentContent, $content);
    $finalContent = $merged['content'];
    $wasMerged = (bool)$merged['merged'];
  }
  $payload = is_documents_root($rootKey) ? encrypt_documents_content($finalContent, $user) : $finalContent;
  $result = file_put_contents($full, $payload);
  
  if ($result === false) {
    json_out(['error'=>'save failed'], 500);
  }

  // No tree structure change on save (file already exists), skip invalidation
  $email = get_session_email();
  if ($email) append_sync_log($email, $rootKey, 'save_file', $path, 'INFO', 'File updated');
  cloud_group_maybe_log('edit', $path);
  json_out(['ok'=>true, 'revision'=>cloud_text_revision($finalContent), 'content'=>$finalContent, 'merged'=>$wasMerged]);
}

if ($action === 'office_download') {
    $user = cloud_api_user();
    cloud_session_close_early();
    $path = safe_path((string)($_GET['path'] ?? ''));
    if ($path === '') {
        http_response_code(400);
        exit('Path required');
    }
    $base = user_root($user, get_requested_root_key());
    $target = $base . '/' . $path;
    $realBase = realpath($base);
    $realTarget = realpath($target);
    if ($realBase === false || $realTarget === false || strpos($realTarget, $realBase . DIRECTORY_SEPARATOR) !== 0 && $realTarget !== $realBase) {
        http_response_code(404);
        exit('File not found');
    }
    if (!is_file($realTarget)) {
        http_response_code(404);
        exit('File not found');
    }
    $mime = mime_content_type($realTarget) ?: 'application/octet-stream';
    @ob_end_clean();
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode(basename($realTarget)) . '"');
    header('Content-Length: ' . filesize($realTarget));
    header('Cache-Control: no-cache');
    header('X-Content-Type-Options: nosniff');
    readfile($realTarget);
    exit;
}

if ($action === 'list') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  if ($rootKey === 'trash') {
    $trashBase = user_root($user, 'trash');
    $meta = load_trash_metadata($user);
    $items = [];
    foreach ($meta as $id => $info) {
      $full = $trashBase . '/' . $id;
      if (!file_exists($full)) continue;
      $items[] = [
        'name' => (string)($info['name'] ?? $id),
        'path' => $id,
        'type' => is_dir($full) ? 'dir' : 'file',
        'size' => is_dir($full) ? null : @filesize($full),
        'mtime' => @filemtime($full) ?: 0,
        'deleted_at' => (int)($info['deleted_at'] ?? 0),
        'original_path' => (string)($info['original_path'] ?? ''),
        'original_root' => (string)($info['original_root'] ?? 'main'),
      ];
    }
    usort($items, static function($a, $b) {
      return ($b['deleted_at'] ?? 0) <=> ($a['deleted_at'] ?? 0);
    });
    json_out(['items' => $items]);
  }
  $path = safe_path((string)($_GET['path'] ?? ''));
  // clientsync root removed
  $base = user_root($user, $rootKey);
  $dir = $base . '/' . $path;
  $dir = rtrim($dir, '/');
  
  
  if (!is_dir($dir)) {
    json_out(['error'=>'not found'], 404);
  }
  
  $items = [];
  foreach (scandir($dir) as $it) {
    if ($it === '.' || $it === '..') continue;
    if ($rootKey === 'main' && str_starts_with($it, '.')) continue;
    if (preg_match('/\.zip\.[A-Za-z0-9]{6,}$/', $it)) continue;
    $full = $dir . '/' . $it;
    $isDir = is_dir($full);

    $rel = $path ? $path . '/' . $it : $it;
    if (cloud_is_group_root($rootKey) && cloud_is_hidden_group_tree_path($rel)) continue;
    $items[] = [
      'name' => $it,
      'path' => $rel,
      'type' => $isDir ? 'dir' : 'file',
      'size' => $isDir ? null : filesize($full),
      'mtime' => filemtime($full)
    ];
  }
  
  
  usort($items, function($a,$b){
    if ($a['type'] !== $b['type']) return $a['type']==='dir' ? -1 : 1;
    return strcasecmp($a['name'], $b['name']);
  });
  json_out(['items'=>$items]);
}

if ($action === 'mkdir') {
  $user = get_current_user_from_sessions();
  if ($user === 'guest') json_out(['error' => 'not authenticated'], 401);
  
  $path = safe_path((string)($_POST['path'] ?? ''));
  if ($path==='') json_out(['error'=>'path required'], 400);
  if (($nameError = cloud_validate_relative_path_names($path)) !== null) json_out(['error'=>$nameError], 400);
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  // clientsync root removed
  $rootFolderPrefix = get_root_folder_name($rootKey);
  
  // If path starts with a different root folder, use that root instead
  $specialRoots = ['archive' => '.archive', 'temp' => '.temp_files', 'documents' => '.documents', 'photos' => '.photos', 'pdf' => '.pdf', 'trash' => '.trash'];
  foreach ($specialRoots as $rk => $prefix) {
      if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
          $rootKey = $rk;
          break;
      }
  }
  
  $base = user_root($user, $rootKey);
  $dir = $base . '/' . $path;

  if (file_exists($dir)) {
    json_out(['error'=>'exists'], 409);
  }

  if (!@mkdir($dir, 0775, true)) {
    json_out(['error'=>'create failed'], 500);
  }

  $email = get_session_email();
  // Only invalidate cache for main root, not for hidden roots
  if ($email && $rootKey === 'main') invalidate_file_tree($email);
  if ($email) append_sync_log($email, $rootKey, 'mkdir', $path, 'INFO', 'Directory created');
  cloud_group_maybe_log('create', $path);
  json_out(['ok'=>true]);
}

if ($action === 'upload') {
    cloud_group_require_write(get_requested_root_key());
    // Check if a session ID exists
    $upload_id = $_POST['upload_id'] ?? '';
    $target_path = safe_path((string)($_POST['path'] ?? ''));
    
    // Determine the user and path
    $user = null;
    $final_target_path = $target_path;
    
    if ($upload_id) {
        // Restore the upload session
        $session = get_upload_session($upload_id);
        if ($session && $session['active']) {
            $user = $session['user'];
            $final_target_path = $session['target_path'];
            update_upload_session($upload_id);
        }
    }
    
    // If there is no session or it is invalid, create a new one
    if (!$user) {
        $user = get_current_user_from_sessions();
        
        // If this initializes a new upload
        if (isset($_POST['init_upload']) && $_POST['init_upload'] === 'true') {
            try {
                $upload_id = create_upload_session($target_path, $user);
            } catch (Throwable $e) {
                json_out(['error' => $e->getMessage()], 500);
            }
            json_out(['success' => true, 'upload_id' => $upload_id]);
            exit;
        }
    }
    
    // If there is still no user - error
    if (!$user) {
        json_out(['error' => 'Could not determine user'], 400);
    }
    
    $rootKey = get_requested_root_key();
    // clientsync root removed
    session_write_close();
    $base = user_root($user, $rootKey);
    $targetDir = $base . '/' . dirname($final_target_path);
    $fileName  = basename($final_target_path);
    if (($nameError = cloud_validate_relative_path_names($final_target_path)) !== null) json_out(['error'=>$nameError], 400);
    $mtimeFromClientRaw = (int)($_POST['mtime'] ?? 0);
    $mtimeFromClientTime = $mtimeFromClientRaw > 20000000000 ? ((float)$mtimeFromClientRaw / 1000.0) : (float)$mtimeFromClientRaw;

    // Create the target directory if it does not exist
    if (file_exists($targetDir) && !is_dir($targetDir)) {
        json_out(['error'=>'target is not directory'], 409);
    }
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            json_out(['error'=>'Cannot create directory'], 500);
        }
    }

    if (!isset($_FILES['file'])) json_out(['error'=>'no file'], 400);
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) json_out(['error'=>'upload error ' . $file['error']], 400);
    if ($file['size'] > MAX_UPLOAD_BYTES) json_out(['error'=>'too large'], 413);

    $upload_email = get_session_email();
    $upload_role = getUserRoleByEmail($upload_email);
    if ($upload_role < 2) {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext && in_array($ext, $blockedExtensions, true)) json_out(['error'=>'blocked extension'], 400);
        foreach (array_filter(array_map('strtolower', explode('.', (string)$fileName))) as $__part) {
            if (in_array($__part, $blockedExtensions, true)) json_out(['error'=>'blocked extension'], 400);
        }
    }

    if (!$upload_email || $user === 'guest' || $user !== cloud_api_user()) {
        json_out(['error' => 'not authenticated'], 401);
    }
    $chunkIndex = (int)($_POST['chunk'] ?? $_POST['chunk_index'] ?? 0);
    $isLastChunk = !empty($_POST['last']) || !empty($_POST['is_last']) || (isset($_POST['chunks']) && $chunkIndex + 1 >= (int)$_POST['chunks']);
    $needQuotaScan = ($chunkIndex <= 0) || $isLastChunk;
    $used = cloud_used_bytes_cached($upload_email, $user, false, $needQuotaScan ? 60 : 300);

    $quotaBytes        = cloud_storage_default_quota_mb() * 1024 * 1024;
    $sessionLimitBytes = 0;
    $db_uq = getDB();
    if ($db_uq) {
        try {
            $sq = $db_uq->prepare('SELECT quota_mb, session_limit_mb FROM cloud_user_settings WHERE email = :e');
            $sq->bindValue(':e', $upload_email, PDO::PARAM_STR);
            $rq = $sq->execute()->fetch(PDO::FETCH_ASSOC);
            if ($rq) {
                $quotaBytes        = max(1, (int)$rq['quota_mb']) * 1024 * 1024;
                $sessionLimitBytes = max(0, (int)$rq['session_limit_mb']) * 1024 * 1024;
            }
        } catch (Exception $e) {
            cloud_safe_error_log("upload quota check failed: " . $e->getMessage());
        }
    }

    $remaining = $quotaBytes - $used;
    if ($sessionLimitBytes > 0 && $sessionLimitBytes < $remaining) {
        $remaining = $sessionLimitBytes;
    }
    
    // Check quota considering already uploaded chunks
    $existing_size = 0;
    $dest = $targetDir . '/' . $fileName;
    $tmpPlainDest = $dest . '.plainpart';
    if (file_exists($dest)) {
        $existing_size = filesize($dest);
    }
    
    if ($file['size'] + $existing_size > $remaining) {
        json_out(['error'=>'quota exceeded'], 413);
    }

    // Chunk upload support
    if (isset($_POST['chunk'])) {
        $chunk = (int)$_POST['chunk'];
        $chunks = (int)$_POST['chunks'];
        
        // Open or create the file
        $chunkDest = is_documents_root($rootKey) ? $tmpPlainDest : $dest;
        $out = fopen($chunkDest, $chunk == 0 ? 'wb' : 'ab');
        if ($out) {
            $in = fopen($file['tmp_name'], 'rb');
            if ($in) {
                while ($buff = fread($in, 4096)) {
                    fwrite($out, $buff);
                }
                fclose($in);
            }
            fclose($out);
            
            @unlink($file['tmp_name']);
            
            // If this is the last chunk, delete the session
            if ($chunk == $chunks - 1 && is_documents_root($rootKey)) {
                $plain = (string)file_get_contents($tmpPlainDest);
                file_put_contents($dest, encrypt_documents_content($plain, $user));
                @unlink($tmpPlainDest);
            }
            if ($chunk == $chunks - 1 && $upload_id) {
                delete_upload_session($upload_id);
            }
            if ($chunk == $chunks - 1 && $mtimeFromClientTime > 0) {
                @touch($dest, $mtimeFromClientTime);
            }

            // For upload, do not invalidate the tree cache:
            // the structure will be updated incrementally via patch_file_tree.
            $emailLog = get_session_email();
            if ($chunk == $chunks - 1 && $emailLog) {
                append_sync_log($emailLog, $rootKey, 'upload', $final_target_path, 'INFO', 'Upload completed');
                cloud_adjust_usage_cache($emailLog, (int)$file['size']);
            }
            if ($chunk == $chunks - 1) cloud_group_maybe_log('create', $final_target_path);
            json_out(['ok'=>true, 'name'=>$fileName, 'chunk'=>$chunk, 'completed'=>($chunk == $chunks - 1)]);
        } else {
            json_out(['error'=>'failed to write chunk'], 500);
        }
    } else {
        // Regular upload
        if (file_exists($dest) && !isset($_POST['overwrite'])) {
            json_out(['error'=>'file already exists'], 409);
        }
        
        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            json_out(['error'=>'save failed'], 500);
        }
        if (is_documents_root($rootKey)) {
            $plain = (string)file_get_contents($dest);
            file_put_contents($dest, encrypt_documents_content($plain, $user));
        }
        if ($mtimeFromClientTime > 0) {
            @touch($dest, $mtimeFromClientTime);
        }
        
        // Delete the session after completion
        if ($upload_id) {
            delete_upload_session($upload_id);
        }

        // For upload, do not invalidate the tree cache:
        // the structure will be updated incrementally via patch_file_tree.
        $emailLog = get_session_email();
        if ($emailLog) {
            append_sync_log($emailLog, $rootKey, 'upload', $final_target_path, 'INFO', 'Upload completed');
            cloud_adjust_usage_cache($emailLog, (int)@filesize($dest));
        }
        cloud_group_maybe_log('create', $final_target_path);
        json_out(['ok'=>true, 'name'=>$fileName]);
    }
}

if ($action === 'delete') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  $path = safe_path((string)($_POST['path'] ?? ''));
  if ($path==='') json_out(['error'=>'path required'], 400);
  // clientsync root removed
  $base = user_root($user, $rootKey);
  $target = $base . '/' . $path;
  if (!file_exists($target)) json_out(['error'=>'not found'], 404);
  $wasDir = is_dir($target);
  remove_path_recursive($target);
  if ($rootKey === 'trash') {
    $meta = load_trash_metadata($user);
    if (isset($meta[$path])) {
      unset($meta[$path]);
      save_trash_metadata($user, $meta);
    }
  }
  $email = get_session_email();
  // Only invalidate cache for main root, not for hidden roots
  if ($email && $rootKey === 'main') invalidate_file_tree($email);
  if ($email) append_sync_log($email, $rootKey, 'delete', $path, 'WARNING', 'Path deleted', ['type' => $wasDir ? 'dir' : 'file']);
  cloud_group_maybe_log('delete', $path);
  json_out(['ok'=>true]);
}

if ($action === 'download') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  $path = safe_path((string)($_GET['path'] ?? ''));
  // clientsync root removed
  $base = user_root($user, $rootKey);
  $target = $base . '/' . $path;

  // Release session lock before long-running file transfer
  session_write_close();

  if (!is_file($target)) {
    http_response_code(404);
    echo 'File not found';
    exit;
  }

  // Check if the file is a temporary archive (starts with selected_)
  $isTempArchive = (strpos(basename($target), 'selected_') === 0 && str_ends_with(strtolower($target), '.zip'));

  if (is_documents_root($rootKey)) {
    $plain = decrypt_documents_content((string)file_get_contents($target), $user);
    $name = basename($target);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($plain) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
    header('Content-Length: ' . strlen($plain));
    echo $plain;
    exit;
  }
  $name = basename($target);
  $mime = mime_content_type($target) ?: 'application/octet-stream';

  $size = filesize($target);
  $start = 0;
  $end = $size > 0 ? ($size - 1) : 0;
  $partial = false;

  // Resume support via HTTP Range.
  if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=\h*(\d+)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
    $start = (int)$m[1];
    $end = (isset($m[2]) && strlen($m[2]) > 0) ? (int)$m[2] : $end;

    if ($start >= $size || $start > $end) {
      http_response_code(416);
      header('Content-Range: bytes */' . $size);
      exit;
    }
    $partial = true;
  }

  $length = max(0, $end - $start + 1);

  header('Content-Type: ' . $mime);
  header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
  header('Content-Transfer-Encoding: binary');
  header('Accept-Ranges: bytes');
  header('Cache-Control: no-cache, no-store, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');
  if ($partial) {
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
  }
  header('Content-Length: ' . $length);

  while (ob_get_level()) {
    ob_end_clean();
  }

  $chunkSize = 1024 * 1024;

  try {
    if (!is_readable($target)) {
      throw new Exception('File not readable');
    }

    $file = fopen($target, 'rb');
    if (!$file) {
      throw new Exception('Cannot open file for reading');
    }

    if ($start > 0) {
      fseek($file, $start);
    }

    $bytesLeft = $length;
    while ($bytesLeft > 0 && !feof($file)) {
      if (connection_aborted()) {
        break;
      }

      $toRead = ($bytesLeft > $chunkSize) ? $chunkSize : $bytesLeft;
      $chunk = fread($file, $toRead);
      if ($chunk === false) {
        break;
      }
      echo $chunk;
      flush();
      $bytesLeft -= strlen($chunk);
    }

    fclose($file);
    
    // Delete the temporary archive after a successful download
    if ($isTempArchive && file_exists($target)) {
      @unlink($target);
    }
  } catch (Exception $e) {
    cloud_safe_error_log('Download error: ' . $e->getMessage());
    http_response_code(500);
  }

  exit;
}

if ($action === 'download_stream') {
  $user = get_current_user_from_sessions();
  if ($user === 'guest') json_out(['error' => 'not authenticated'], 401);
  $base = user_root($user, get_requested_root_key());
  $path = safe_path((string)($_GET['path'] ?? ''));
  $target = $base . '/' . $path;
  
  // Release session lock before long-running file transfer
  session_write_close();
  
  if (!is_file($target)) { 
    http_response_code(404); 
    echo 'File not found'; 
    exit; 
  }
  
  $name = basename($target);
  $size = filesize($target);
  $mime = mime_content_type($target) ?: 'application/octet-stream';
  
  // Use X-Sendfile if available (more efficient)
  if (function_exists('apache_get_modules') && in_array('mod_xsendfile', apache_get_modules())) {
    header('X-Sendfile: ' . $target);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
    exit;
  }
  
  // Standard streaming method
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . $size);
  header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
  header('Content-Transfer-Encoding: binary');
  
  // Flush buffers
  while (ob_get_level()) {
    ob_end_clean();
  }
  
  // Use readfile with buffering
  readfile($target);
  exit;
}

if ($action === 'open') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  $base = user_root($user, $rootKey);
  $path = safe_path((string)($_GET['path'] ?? ''));
  $target = $base . '/' . $path;
  
  if (!file_exists($target)) { 
    http_response_code(404); echo 'Not found'; exit; 
  }
  
  $rawContent = is_documents_root($rootKey) ? decrypt_documents_content((string)file_get_contents($target), $user) : '';
  if (is_documents_root($rootKey)) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($rawContent) ?: 'application/octet-stream';
  } else {
    $mime = mime_content_type($target) ?: 'application/octet-stream';
  }
  
  // If the file is empty or has mime type application/x-empty, treat it as text
  if ($mime === 'application/x-empty' || filesize($target) === 0) {
    $mime = 'text/plain';
  }
  
  // Determine the file type for preview
  $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
  $officeExts = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
  $isPdf = $ext === 'pdf';
  $isVideo = in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv']);
  $isAudio = in_array($ext, ['mp3', 'wav', 'ogg', 'm4a', 'flac']);
  $is3DModel = in_array($ext, ['obj', 'stl']);
  $isWord = in_array($ext, ['doc', 'docx']);
  $isExcel = in_array($ext, ['xls', 'xlsx']);
  $isPresentation = in_array($ext, ['ppt', 'pptx']);
  $isOffice = $isWord || $isExcel || $isPresentation;
  
   if ($isVideo) {
    json_out([
      'binary' => true,
      'preview' => true,
      'previewType' => 'video',
      'mime' => $mime,
      'name' => basename($target)
    ]);
  } else if ($isAudio) {
    json_out([
      'binary' => true,
      'preview' => true,
      'previewType' => 'audio',
      'mime' => $mime,
      'name' => basename($target)
    ]);
  } else if ($is3DModel) {
    json_out([
      'binary' => true,
      'preview' => true,
      'previewType' => '3d',
      'mime' => $mime,
      'name' => basename($target)
    ]);
  } else if ($isPdf) {
  json_out([
    'binary' => true, 
    'preview' => true,
    'previewType' => 'pdf',
    'mime' => $mime,
    'name' => basename($target),
    'previewUrl' => "api.php?action=view_pdf&path=".rawurlencode($path)."&root=".rawurlencode($rootKey)
  ]);
} else if ($isWord) {
    json_out([
      'binary' => true, 
      'preview' => true,
      'previewType' => 'word',
      'mime' => $mime,
      'name' => basename($target)
    ]);
  } else if ($isExcel) {
    json_out([
      'binary' => true, 
      'preview' => true,
      'previewType' => 'excel',
      'mime' => $mime,
      'name' => basename($target)
    ]);
  } else if ($isPresentation) {
    json_out([
      'binary' => true, 
      'preview' => true,
      'previewType' => 'presentation',
      'mime' => $mime,
      'name' => basename($target)
    ]);
  } else if (!is_text_mime($mime)) {
    if (is_documents_root($rootKey)) {
      json_out(['binary'=>true, 'openUrl'=>"api.php?action=raw&path=".rawurlencode($path)."&root=".rawurlencode($rootKey), 'mime'=>$mime]);
    }
    // For large files, use streaming download
    if (filesize($target) > 50 * 1024 * 1024) { // If the file is larger than 50MB
        $downloadUrl = "api.php?action=download_stream&path=".rawurlencode($path)."&root=".rawurlencode($rootKey);
    } else {
        $downloadUrl = "api.php?action=raw&path=".rawurlencode($path)."&root=".rawurlencode($rootKey);
    }
    json_out(['binary'=>true, 'openUrl'=>$downloadUrl, 'mime'=>$mime]);
}
  
  $content = is_documents_root($rootKey) ? $rawContent : file_get_contents($target);
  json_out(['binary'=>false, 'content'=>$content, 'ext'=>$ext, 'mime'=>$mime, 'name'=>basename($target), 'revision'=>cloud_text_revision($content)]);
}

if ($action === 'get_folder_properties') {
    $user = get_current_user_from_sessions();
    $base = user_root($user, get_requested_root_key());
    $path = safe_path((string)($_GET['path'] ?? ''));
    $fullPath = $base . '/' . $path;
    
    if (!file_exists($fullPath)) {
        json_out(['error' => 'Folder not found'], 404);
    }
    
    if (!is_dir($fullPath)) {
        json_out(['error' => 'Path is not a directory'], 400);
    }
    
    // Gather info about the folder contents
    $totalSize = 0;
    $fileCount = 0;
    $folderCount = 0;
    $items = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $totalSize += $item->getSize();
            $fileCount++;
        } elseif ($item->isDir()) {
            $folderCount++;
        }
        
        // Keep the first 100 items for preview (optional)
        if (count($items) < 100) {
            $items[] = [
                'name' => $item->getFilename(),
                'path' => str_replace($base . '/', '', $item->getPathname()),
                'type' => $item->isDir() ? 'dir' : 'file',
                'size' => $item->isFile() ? $item->getSize() : null,
                'modified' => $item->getMTime()
            ];
        }
    }
    
    $stat = stat($fullPath);
    
    $properties = [
        'type' => 'directory',
        'size' => $totalSize,
        'items' => $fileCount + $folderCount,
        'files' => $fileCount,
        'folders' => $folderCount,
        'created' => date('c', $stat['ctime']),
        'modified' => date('c', $stat['mtime']),
        'accessed' => date('c', $stat['atime']),
        'attributes' => getFileAttributes($fullPath),
        'items_preview' => $items // optional
    ];
    
    json_out($properties);
}

if ($action === 'view_pdf') {
  cloud_session_start();
  $user = get_current_user_from_sessions();
  if ($user === 'guest' || $user === '') {
    http_response_code(401); echo 'Unauthorized'; exit;
  }
  $rootKey = get_requested_root_key();
  $base = user_root($user, $rootKey);
  $path = safe_path((string)($_GET['path'] ?? ''));
  if ($path === '') {
    http_response_code(400); echo 'Path required'; exit;
  }
  $target = $base . '/' . $path;

  if (!file_exists($target)) {
    http_response_code(404); echo 'File not found'; exit;
  }

  // Handle encrypted files in .documents folder
  if (is_documents_root($rootKey)) {
    $encryptedContent = @file_get_contents($target);
    if ($encryptedContent !== false) {
      $decryptedContent = decrypt_documents_content($encryptedContent, $user);
      $target = tempnam(sys_get_temp_dir(), 'pdf_view_') . '.pdf';
      if ($decryptedContent !== false) {
        file_put_contents($target, $decryptedContent);
      }
    }
  }

  $mime = @mime_content_type($target);
  if ($mime !== 'application/pdf') {
    http_response_code(400); 
    if (is_documents_root($rootKey)) {
        @unlink($target);
    }
    echo 'Not a PDF file'; 
    exit;
  }

  $token = bin2hex(random_bytes(16));
  // Store both target path and root key in session
  $_SESSION['pdf_tokens'][$token] = [
    'path' => $target,
    'root' => $rootKey,
    'user' => $user,
    'is_temp' => is_documents_root($rootKey)
  ];

  // Include the template with parameters
  $_GET['token'] = $token;
  include dirname(__DIR__, 2) . '/utilities/projects/pdf-viewer/public/index.php';
  exit;
}

if ($action === 'pdf_to_images') {
    $user = get_current_user_from_sessions();
    $rootKey = get_requested_root_key();
    $base = user_root($user, $rootKey);
    $path = safe_path((string)(($_GET['path'] ?? $_POST['path'] ?? '')));
    if ($path === '') json_out(['ok' => false, 'error' => 'path required'], 400);
    $target = $base . '/' . $path;
    if (!is_file($target)) json_out(['ok' => false, 'error' => 'file_not_found'], 404);
    $mime = (string)@mime_content_type($target);
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    if ($mime !== 'application/pdf' && $ext !== 'pdf') json_out(['ok' => false, 'error' => 'not_a_pdf'], 400);
    $pdftoppm = trim((string)@shell_exec('command -v pdftoppm 2>/dev/null'));
    if ($pdftoppm === '') json_out(['ok' => false, 'error' => 'pdftoppm_unavailable'], 501);
    $stem = pathinfo($path, PATHINFO_FILENAME);
    $dirRel = trim(str_replace('\\', '/', dirname($path)), '/.');
    $outDirRel = ($dirRel === '' ? '' : ($dirRel . '/')) . $stem . '_pages';
    $outDir = $base . '/' . $outDirRel;
    if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) json_out(['ok' => false, 'error' => 'mkdir_failed'], 500);
    $prefix = $outDir . '/page';
    $cmd = escapeshellarg($pdftoppm) . ' -png -r 72 ' . escapeshellarg($target) . ' ' . escapeshellarg($prefix);
    $output = [];
    $code = 0;
    @exec($cmd . ' 2>&1', $output, $code);
    if ($code !== 0) json_out(['ok' => false, 'error' => 'pdftoppm_failed', 'detail' => implode("\n", $output)], 500);
    $images = [];
    foreach (glob($prefix . '-*.png') ?: [] as $img) {
        $images[] = $outDirRel . '/' . basename($img);
    }
    sort($images, SORT_NATURAL);
    if ($images === []) json_out(['ok' => false, 'error' => 'no_images_produced'], 500);
    $email = get_session_email();
    if ($email && $rootKey === 'main') invalidate_file_tree($email);
    json_out(['ok' => true, 'images' => $images, 'count' => count($images), 'folder' => $outDirRel]);
}

function cloud_archive_jobs_dir(): string {
    $dir = upload_sessions_dir() . '/archive_jobs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}
function cloud_archive_tmp_dir(): string {
    $dir = upload_sessions_dir() . '/archive_tmp';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}
function cloud_archive_job_file(string $jobId): string {
    return cloud_archive_jobs_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $jobId) . '.json';
}
function cloud_archive_write_job(string $jobId, array $data): void {
    $file = cloud_archive_job_file($jobId);
    $current = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $data['updated_at'] = time();
    if (@file_put_contents($file, json_encode(array_merge($current, $data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
        throw new RuntimeException('Archive job storage is not writable');
    }
}
function cloud_archive_start_job(string $type, array $payload, callable $runner): void {
    $jobId = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(16));
    cloud_archive_write_job($jobId, ['id' => $jobId, 'type' => $type, 'status' => 'queued', 'progress' => 0, 'created_at' => time(), 'payload' => $payload, 'worker_token' => $token]);
    $php = cloud_archive_command_path('php') ?: PHP_BINARY;
    $command = escapeshellarg($php) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/api.php') . ' archive_worker_run ' . escapeshellarg($jobId) . ' ' . escapeshellarg($token) . ' > /dev/null 2>&1 &';
    @exec($command);
    json_out(['success' => true, 'async' => true, 'job_id' => $jobId]);
}
function cloud_archive_error_job(string $jobId, string $message): void {
    cloud_archive_write_job($jobId, ['status' => 'error', 'progress' => 100, 'error' => $message, 'payload' => [], 'worker_token' => '']);
}
function cloud_archive_notify_job(string $jobId, array $payload, string $severity, string $title, string $message): void {
    $email = (string)($payload['email'] ?? '');
    if ($email === '' || !function_exists('ensureNotificationsSchema')) return;
    try {
        $db = getDB();
        ensureNotificationsSchema($db);
        $stmt = $db->prepare("INSERT INTO app_notifications(user_email,app_id,severity,title,message,event_type,context_json,is_read,created_at) VALUES(:u,'cloud',:s,:t,:m,'worker_finished',:c,0,:n)");
        $stmt->bindValue(':u', $email, PDO::PARAM_STR);
        $stmt->bindValue(':s', $severity, PDO::PARAM_STR);
        $stmt->bindValue(':t', $title, PDO::PARAM_STR);
        $stmt->bindValue(':m', $message, PDO::PARAM_STR);
        $stmt->bindValue(':c', json_encode(['job_id' => $jobId, 'root' => (string)($payload['root'] ?? ''), 'type' => (string)($payload['type'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        $stmt->bindValue(':n', time(), PDO::PARAM_INT);
        $stmt->execute();
    } catch (Throwable $e) {}
}
function cloud_archive_job_cancelled(string $jobId): bool {
    $file = cloud_archive_job_file($jobId);
    $job = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    return !empty($job['cancel_requested']);
}
function cloud_archive_cancelled_job(string $jobId, ?string $path = null): void {
    if ($path && is_file($path)) @unlink($path);
    foreach (glob(cloud_archive_tmp_dir() . '/' . $jobId . '*') ?: [] as $tmp) {
        if (is_file($tmp)) @unlink($tmp);
    }
    cloud_archive_write_job($jobId, ['status' => 'cancelled', 'progress' => 100, 'payload' => [], 'worker_token' => '']);
}
function cloud_archive_command_path(string $command): string {
    $fallbacks = [
        'zip' => ['/usr/bin/zip', '/bin/zip'],
        'unzip' => ['/usr/bin/unzip', '/bin/unzip'],
        '7z' => ['/usr/bin/7z', '/usr/bin/7za', '/bin/7z', '/bin/7za'],
        'tar' => ['/usr/bin/tar', '/bin/tar'],
        'php' => ['/usr/bin/php', '/usr/local/bin/php', PHP_BINARY],
    ];
    foreach ($fallbacks[$command] ?? [] as $path) {
        if (is_string($path) && $path !== '' && is_file($path) && is_executable($path)) return $path;
    }
    $out = [];
    $code = 1;
    @exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null', $out, $code);
    return ($code === 0 && !empty($out[0])) ? (string)$out[0] : '';
}
function cloud_archive_command_exists(string $command): bool {
    return cloud_archive_command_path($command) !== '';
}
function cloud_archive_run(array $command, ?string $cwd = null): array {
    $pipes = [];
    $proc = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd ?: null);
    if (!is_resource($proc)) return [1, '', 'Failed to start archive command'];
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return [$code, (string)$stdout, (string)$stderr];
}
function cloud_archive_normalize_format(string $format, string $name = ''): string {
    $format = strtolower(trim($format));
    $name = strtolower(trim($name));
    if (in_array($format, ['zip', '7z', 'rar', 'tar', 'tar.gz', 'tgz'], true)) return $format === 'tgz' ? 'tar.gz' : $format;
    if (str_ends_with($name, '.tar.gz') || str_ends_with($name, '.tgz')) return 'tar.gz';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, ['zip', '7z', 'rar', 'tar'], true) ? $ext : 'zip';
}
function cloud_archive_format_extension(string $format): string {
    return $format === 'tar.gz' ? 'tar.gz' : $format;
}
function cloud_archive_password_supported(string $format): bool {
    return in_array($format, ['zip', '7z'], true);
}
function cloud_archive_is_encrypted_message(string $message): bool {
    return preg_match('/encrypted|password|wrong password|can not open encrypted/i', $message) === 1;
}
function cloud_archive_requires_password(string $archivePath, string $format): bool {
    if (!in_array($format, ['zip', '7z', 'rar'], true)) return false;
    if ($format === 'zip' && cloud_archive_command_exists('unzip')) {
        $cmd = [cloud_archive_command_path('unzip'), '-Z', '-v', $archivePath];
        [$code, $stdout, $stderr] = cloud_archive_run($cmd);
        if ($code !== 0) return cloud_archive_is_encrypted_message($stderr . "\n" . $stdout);
        return stripos($stdout, 'file security status:                           encrypted') !== false;
    }
    if (!cloud_archive_command_exists('7z')) return false;
    $cmd = [cloud_archive_command_path('7z'), 'l', '-slt', $archivePath];
    [$code, $stdout, $stderr] = cloud_archive_run($cmd);
    if ($code !== 0) return cloud_archive_is_encrypted_message($stderr . "\n" . $stdout);
    foreach (preg_split('/\R/', $stdout) as $line) {
        if (preg_match('/^Encrypted = \+/', $line)) return true;
    }
    return false;
}
function cloud_archive_sanitize_name(string $name, string $format): string {
    $ext = cloud_archive_format_extension($format);
    $name = trim($name);
    if ($name === '') $name = 'selected_' . date('Y-m-d_H-i-s') . '.' . $ext;
    $lower = strtolower($name);
    if ($format === 'tar.gz') {
        if (!str_ends_with($lower, '.tar.gz') && !str_ends_with($lower, '.tgz')) $name .= '.tar.gz';
    } elseif (!str_ends_with($lower, '.' . $ext)) {
        $name .= '.' . $ext;
    }
    if (($nameError = cloud_validate_file_name($name)) !== null) {
        throw new RuntimeException($nameError);
    }
    return $name;
}
function cloud_archive_relative_arg(string $path): string {
    return './' . ltrim(str_replace('\\', '/', $path), '/');
}
function cloud_archive_zip_add_path(ZipArchive $zip, string $base, string $relative, string $password = ''): void {
    $source = $base . '/' . $relative;
    $local = ltrim(str_replace('\\', '/', $relative), '/');
    if (is_dir($source)) {
        $zip->addEmptyDir($local);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $filePath = $file->getPathname();
            $entry = $local . '/' . ltrim(str_replace('\\', '/', substr($filePath, strlen($source))), '/');
            $zip->addFile($filePath, $entry);
            if ($password !== '') $zip->setEncryptionName($entry, ZipArchive::EM_AES_256);
        }
        return;
    }
    $zip->addFile($source, $local);
    if ($password !== '') $zip->setEncryptionName($local, ZipArchive::EM_AES_256);
}
function cloud_archive_create_zip_php(string $base, array $items, string $tmpPath, string $password = ''): bool {
    if (!class_exists('ZipArchive')) return false;
    if ($password !== '' && (!defined('ZipArchive::EM_AES_256') || !method_exists('ZipArchive', 'setEncryptionName'))) return false;
    $zip = new ZipArchive();
    if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
    if ($password !== '') $zip->setPassword($password);
    foreach ($items as $item) {
        $relative = safe_path((string)$item);
        if ($relative === '' || !file_exists($base . '/' . $relative)) continue;
        cloud_archive_zip_add_path($zip, $base, $relative, $password);
    }
    return $zip->close() && is_file($tmpPath) && filesize($tmpPath) > 0;
}
function cloud_archive_create_file(string $base, array $items, string $tmpPath, string $format, string $password = ''): int {
    $paths = [];
    foreach ($items as $item) {
        $item = safe_path((string)$item);
        if ($item === '' || !file_exists($base . '/' . $item)) continue;
        $paths[] = cloud_archive_relative_arg($item);
    }
    if (!$paths) throw new RuntimeException('No existing items selected');
    if ($password !== '' && !cloud_archive_password_supported($format)) throw new RuntimeException('Password is supported only for ZIP and 7Z archives');
    if ($format === 'zip') {
        if (cloud_archive_create_zip_php($base, $items, $tmpPath, $password)) return count($paths);
        if (!cloud_archive_command_exists('zip')) throw new RuntimeException('zip command not available');
        $cmd = [cloud_archive_command_path('zip'), '-r', '-q'];
        if ($password !== '') array_push($cmd, '-P', $password);
        array_push($cmd, $tmpPath, '--', ...$paths);
    } elseif ($format === '7z') {
        if (!cloud_archive_command_exists('7z')) throw new RuntimeException('7z command not available');
        $cmd = [cloud_archive_command_path('7z'), 'a', '-y', '-t7z'];
        if ($password !== '') array_push($cmd, '-p' . $password, '-mhe=on');
        array_push($cmd, $tmpPath, ...$paths);
    } elseif ($format === 'tar') {
        if (!cloud_archive_command_exists('tar')) throw new RuntimeException('tar command not available');
        $cmd = [cloud_archive_command_path('tar'), '-cf', $tmpPath, '--', ...$paths];
    } elseif ($format === 'tar.gz') {
        if (!cloud_archive_command_exists('tar')) throw new RuntimeException('tar command not available');
        $cmd = [cloud_archive_command_path('tar'), '-czf', $tmpPath, '--', ...$paths];
    } else {
        throw new RuntimeException('Unsupported archive format');
    }
    [$code, $stdout, $stderr] = cloud_archive_run($cmd, $base);
    if ($code !== 0 || !is_file($tmpPath) || filesize($tmpPath) <= 0) {
        @unlink($tmpPath);
        throw new RuntimeException(trim($stderr ?: $stdout) ?: 'Failed to create archive');
    }
    return count($paths);
}
function cloud_archive_extract_file(string $archivePath, string $extractPath, string $format, string $password = ''): int {
    if (!is_dir($extractPath)) @mkdir($extractPath, 0775, true);
    if (!is_dir($extractPath) || !is_writable($extractPath)) throw new RuntimeException('Extraction directory not writable');
    foreach (cloud_archive_list_paths($archivePath, $format, $password) as $entry) {
        $entry = str_replace('\\', '/', $entry);
        if ($entry === '' || str_starts_with($entry, '/') || preg_match('#(^|/)\.\.(/|$)#', $entry)) {
            throw new RuntimeException('Archive contains unsafe paths');
        }
    }
    if ($format === 'zip' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) === true) {
            if ($password !== '') $zip->setPassword($password);
            $beforeCount = iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS)));
            if (!$zip->extractTo($extractPath)) {
                $zip->close();
                throw new RuntimeException('Failed to extract archive');
            }
            $zip->close();
            $afterCount = iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS)));
            return max(0, $afterCount - $beforeCount);
        }
    }
    $before = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS));
    $beforeCount = iterator_count($before);
    if (in_array($format, ['zip', '7z', 'rar'], true)) {
        if ($format === 'zip' && cloud_archive_command_exists('unzip')) {
            $cmd = [cloud_archive_command_path('unzip'), '-o'];
            if ($password !== '') array_push($cmd, '-P', $password);
            array_push($cmd, $archivePath, '-d', $extractPath);
        } else {
            if (!cloud_archive_command_exists('7z')) throw new RuntimeException('7z command not available');
            $cmd = [cloud_archive_command_path('7z'), 'x', '-y', '-o' . $extractPath];
            if ($password !== '') $cmd[] = '-p' . $password;
            $cmd[] = $archivePath;
        }
    } elseif ($format === 'tar') {
        if (!cloud_archive_command_exists('tar')) throw new RuntimeException('tar command not available');
        $cmd = [cloud_archive_command_path('tar'), '-xf', $archivePath, '-C', $extractPath];
    } elseif ($format === 'tar.gz') {
        if (!cloud_archive_command_exists('tar')) throw new RuntimeException('tar command not available');
        $cmd = [cloud_archive_command_path('tar'), '-xzf', $archivePath, '-C', $extractPath];
    } else {
        throw new RuntimeException('Unsupported archive format');
    }
    [$code, $stdout, $stderr] = cloud_archive_run($cmd);
    if ($code !== 0) throw new RuntimeException(trim($stderr ?: $stdout) ?: 'Failed to extract archive');
    $after = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS));
    return max(0, iterator_count($after) - $beforeCount);
}
function cloud_archive_list_paths(string $archivePath, string $format, string $password = ''): array {
    if (in_array($format, ['zip', '7z', 'rar'], true)) {
        if ($format === 'zip' && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($archivePath) === true) {
                if ($password !== '') $zip->setPassword($password);
                $paths = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if ($name !== false) $paths[] = $name;
                }
                $zip->close();
                return $paths;
            }
        }
        if ($format === 'zip' && cloud_archive_command_exists('unzip')) {
            $cmd = [cloud_archive_command_path('unzip'), '-Z1'];
            if ($password !== '') array_push($cmd, '-P', $password);
            $cmd[] = $archivePath;
            [$code, $stdout, $stderr] = cloud_archive_run($cmd);
            if ($code !== 0) throw new RuntimeException(trim($stderr ?: $stdout) ?: 'Cannot list archive');
            return array_values(array_filter(preg_split('/\R/', $stdout), static fn($path) => $path !== ''));
        }
        if (!cloud_archive_command_exists('7z')) throw new RuntimeException('7z command not available');
        $cmd = [cloud_archive_command_path('7z'), 'l', '-slt'];
        if ($password !== '') $cmd[] = '-p' . $password;
        $cmd[] = $archivePath;
        [$code, $stdout, $stderr] = cloud_archive_run($cmd);
        if ($code !== 0) throw new RuntimeException(trim($stderr ?: $stdout) ?: 'Cannot list archive');
        $paths = [];
        foreach (preg_split('/\R/', $stdout) as $line) {
            if (str_starts_with($line, 'Path = ')) {
                $path = substr($line, 7);
                if ($path !== $archivePath) $paths[] = $path;
            }
        }
        return $paths;
    }
    if ($format === 'tar') $cmd = [cloud_archive_command_path('tar'), '-tf', $archivePath];
    elseif ($format === 'tar.gz') $cmd = [cloud_archive_command_path('tar'), '-tzf', $archivePath];
    else throw new RuntimeException('Unsupported archive format');
    [$code, $stdout, $stderr] = cloud_archive_run($cmd);
    if ($code !== 0) throw new RuntimeException(trim($stderr ?: $stdout) ?: 'Cannot list archive');
    return array_values(array_filter(preg_split('/\R/', $stdout)));
}
function cloud_archive_create_selected_job(string $jobId, array $payload): void {
    try {
        $user = $payload['user'];
        $rootKey = $payload['root'];
        $base = user_root($user, $rootKey);
        $items = $payload['items'];
        $temporary = !empty($payload['temporary']);
        $format = cloud_archive_normalize_format((string)($payload['format'] ?? ''), (string)($payload['archive_name'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $archive_name = cloud_archive_sanitize_name((string)$payload['archive_name'], $format);
        $archive_path = cloud_archive_tmp_dir() . '/' . $jobId . '_download.' . cloud_archive_format_extension($format);
        if (!$temporary) {
            $archive_path = $base . '/' . $archive_name;
        }
        if (!$temporary && file_exists($archive_path)) throw new RuntimeException('exists');
        $total = max(1, count($items));
        cloud_archive_write_job($jobId, ['status' => 'running', 'progress' => 20, 'processed' => 0, 'total' => $total]);
        $tmp_path = cloud_archive_tmp_dir() . '/' . $jobId . '.' . cloud_archive_format_extension($format);
        $added = cloud_archive_create_file($base, $items, $tmp_path, $format, $password);
        cloud_archive_write_job($jobId, ['status' => 'running', 'progress' => 99, 'processed' => $total, 'total' => $total]);
        if (cloud_archive_job_cancelled($jobId)) {
            cloud_archive_cancelled_job($jobId, $tmp_path);
            return;
        }
        if (!@rename($tmp_path, $archive_path)) throw new RuntimeException('Failed to move archive into place');
        if (!file_exists($archive_path) || filesize($archive_path) <= 0) throw new RuntimeException('Failed to create archive');
        $email = get_session_email();
        if (!$temporary && $email && $rootKey === 'main') invalidate_file_tree($email);
        if (!$temporary && $email) append_sync_log($email, $rootKey, 'create_archive', $archive_name, 'INFO', 'Archive created');
        $done = ['status' => 'done', 'progress' => 100, 'archive_name' => $archive_name, 'items_count' => $added, 'size' => filesize($archive_path), 'payload' => [], 'worker_token' => ''];
        if ($temporary) {
            $downloadToken = bin2hex(random_bytes(16));
            $done['temporary'] = true;
            $done['temp_file'] = basename($archive_path);
            $done['download_token'] = $downloadToken;
            $done['download_url'] = 'api.php?action=archive_job_download&job_id=' . rawurlencode($jobId) . '&token=' . rawurlencode($downloadToken);
        } else {
            $done['archive_path'] = $archive_name;
        }
        cloud_archive_write_job($jobId, $done);
        cloud_archive_notify_job($jobId, $payload, 'success', 'Cloud archive finished', $archive_name);
    } catch (Throwable $e) {
        cloud_archive_error_job($jobId, $e->getMessage());
        cloud_archive_notify_job($jobId, $payload, 'error', 'Cloud archive failed', $e->getMessage());
    }
}
function cloud_archive_folder_job(string $jobId, array $payload): void {
    try {
        $user = $payload['user'];
        $rootKey = $payload['root'];
        $base = user_root($user, $rootKey);
        $folder_path = safe_path((string)$payload['folder_path']);
        $full_path = $base . '/' . $folder_path;
        if (!is_dir($full_path)) throw new RuntimeException('Folder not found');
        $format = cloud_archive_normalize_format((string)($payload['format'] ?? ''), (string)($payload['archive_name'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $folder_name = basename($folder_path);
        $archive_name = cloud_archive_sanitize_name((string)($payload['archive_name'] ?? ($folder_name . '_' . date('Y-m-d_H-i-s'))), $format);
        $archive_path = dirname($full_path) . '/' . $archive_name;
        if (file_exists($archive_path)) throw new RuntimeException('Archive already exists');
        $total = 1;
        $tmp_path = cloud_archive_tmp_dir() . '/' . $jobId . '.' . cloud_archive_format_extension($format);
        cloud_archive_write_job($jobId, ['status' => 'running', 'progress' => 20, 'processed' => 0, 'total' => $total]);
        $parent = dirname($folder_path);
        $folderItem = ($parent === '.' || $parent === '') ? basename($folder_path) : $folder_path;
        $added = cloud_archive_create_file($base, [$folderItem], $tmp_path, $format, $password);
        cloud_archive_write_job($jobId, ['status' => 'running', 'progress' => 99, 'processed' => $total, 'total' => $total]);
        if (cloud_archive_job_cancelled($jobId)) {
            cloud_archive_cancelled_job($jobId, $tmp_path);
            return;
        }
        if (!@rename($tmp_path, $archive_path)) throw new RuntimeException('Failed to move archive into place');
        if (!file_exists($archive_path) || filesize($archive_path) <= 0) throw new RuntimeException('Failed to create archive file');
        $email = get_session_email();
        if ($email && $rootKey === 'main') invalidate_file_tree($email);
        if ($email) append_sync_log($email, $rootKey, 'archive_folder', $folder_path, 'INFO', 'Folder archived');
        $result_path = trim(dirname($folder_path) . '/' . $archive_name, './');
        cloud_archive_write_job($jobId, ['status' => 'done', 'progress' => 100, 'archive_path' => $result_path, 'archive_name' => $archive_name, 'size' => filesize($archive_path), 'files_count' => $added, 'payload' => [], 'worker_token' => '']);
        cloud_archive_notify_job($jobId, $payload, 'success', 'Cloud folder archive finished', $result_path);
    } catch (Throwable $e) {
        cloud_archive_error_job($jobId, $e->getMessage());
        cloud_archive_notify_job($jobId, $payload, 'error', 'Cloud folder archive failed', $e->getMessage());
    }
}
function cloud_archive_extract_job(string $jobId, array $payload): void {
    try {
        $user = $payload['user'];
        $rootKey = $payload['root'];
        $base = user_root($user, $rootKey);
        $archive_path = safe_path((string)$payload['archive_path']);
        $extract_to = safe_path((string)$payload['extract_to']);
        $full_archive_path = $base . '/' . $archive_path;
        if (!is_file($full_archive_path)) throw new RuntimeException('Archive not found');
        $format = cloud_archive_normalize_format((string)($payload['format'] ?? ''), $archive_path);
        $password = (string)($payload['password'] ?? '');
        $extract_full_path = $extract_to === '' ? dirname($full_archive_path) : $base . '/' . $extract_to;
        cloud_archive_write_job($jobId, ['status' => 'running', 'progress' => 20, 'processed' => 0, 'total' => 1]);
        $extracted = cloud_archive_extract_file($full_archive_path, $extract_full_path, $format, $password);
        $email = get_session_email();
        if ($email && $rootKey === 'main') invalidate_file_tree($email);
        if ($email) append_sync_log($email, $rootKey, 'extract_archive', $archive_path, 'INFO', 'Archive extracted');
        $extractResult = $extract_to === '' ? dirname($archive_path) : $extract_to;
        cloud_archive_write_job($jobId, ['status' => 'done', 'progress' => 100, 'extracted_to' => $extractResult, 'extracted_files_count' => $extracted, 'payload' => [], 'worker_token' => '']);
        cloud_archive_notify_job($jobId, $payload, 'success', 'Cloud extraction finished', $extractResult);
    } catch (Throwable $e) {
        cloud_archive_error_job($jobId, $e->getMessage());
        cloud_archive_notify_job($jobId, $payload, 'error', 'Cloud extraction failed', $e->getMessage());
    }
}
if ($action === 'archive_job_status') {
    $jobId = preg_replace('/[^a-f0-9]/', '', (string)($_GET['job_id'] ?? $_POST['job_id'] ?? ''));
    $file = cloud_archive_job_file($jobId);
    if ($jobId === '' || !is_file($file)) json_out(['error' => 'Job not found'], 404);
    $job = json_decode((string)file_get_contents($file), true) ?: ['error' => 'Invalid job'];
    unset($job['payload'], $job['worker_token'], $job['temp_file'], $job['download_token']);
    json_out($job);
}
if ($action === 'archive_job_download') {
    $jobId = preg_replace('/[^a-f0-9]/', '', (string)($_GET['job_id'] ?? ''));
    $token = (string)($_GET['token'] ?? '');
    $file = cloud_archive_job_file($jobId);
    $job = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    if ($jobId === '' || !$job || !hash_equals((string)($job['download_token'] ?? ''), $token)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    $tempFile = basename((string)($job['temp_file'] ?? ''));
    $target = cloud_archive_tmp_dir() . '/' . $tempFile;
    if ($tempFile === '' || !is_file($target)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    session_write_close();
    $name = (string)($job['archive_name'] ?? basename($target));
    $mime = mime_content_type($target) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
    header('Content-Length: ' . filesize($target));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($target);
    @unlink($target);
    @unlink($file);
    exit;
}
if ($action === 'archive_job_cancel') {
    $jobId = preg_replace('/[^a-f0-9]/', '', (string)($_POST['job_id'] ?? ''));
    $file = cloud_archive_job_file($jobId);
    if ($jobId === '' || !is_file($file)) json_out(['error' => 'Job not found'], 404);
    cloud_archive_write_job($jobId, ['cancel_requested' => true]);
    json_out(['success' => true]);
}
if ($action === 'archive_password_required') {
    $user = get_current_user_from_sessions();
    $rootKey = get_requested_root_key();
    $base = user_root($user, $rootKey);
    $archive_path = safe_path((string)($_GET['archive_path'] ?? $_POST['archive_path'] ?? ''));
    $format = cloud_archive_normalize_format((string)($_GET['format'] ?? $_POST['format'] ?? ''), $archive_path);
    if ($archive_path === '') json_out(['error' => 'archive_path is required'], 400);
    $full_archive_path = $base . '/' . $archive_path;
    if (!is_file($full_archive_path)) json_out(['error' => 'Archive not found'], 404);
    json_out(['success' => true, 'password_required' => cloud_archive_requires_password($full_archive_path, $format)]);
}
if ($action === 'archive_worker_run') {
    $jobId = preg_replace('/[^a-f0-9]/', '', (string)($_POST['job_id'] ?? ''));
    $token = (string)($_POST['worker_token'] ?? '');
    $file = cloud_archive_job_file($jobId);
    $job = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    if ($jobId === '' || !$job || !hash_equals((string)($job['worker_token'] ?? ''), $token)) exit;
    cloud_archive_write_job($jobId, ['status' => 'running', 'progress' => max(1, (int)($job['progress'] ?? 0))]);
    $payload = $job['payload'] ?? [];
    if (($job['type'] ?? '') === 'create_archive') cloud_archive_create_selected_job($jobId, $payload);
    elseif (($job['type'] ?? '') === 'archive_folder') cloud_archive_folder_job($jobId, $payload);
    elseif (($job['type'] ?? '') === 'extract_archive') cloud_archive_extract_job($jobId, $payload);
    exit;
}
if ($action === 'create_archive') {
    $user = get_current_user_from_sessions();
    $rootKey = get_requested_root_key();
    $base = user_root($user, $rootKey);
    
    // Get the list of items to archive
    $items = $_POST['items'] ?? '';
    $format = cloud_archive_normalize_format((string)($_POST['format'] ?? ''), (string)($_POST['archive_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $archive_name = $_POST['archive_name'] ?? 'selected_' . date('Y-m-d_H-i-s') . '.' . cloud_archive_format_extension($format);
    
    // Decode JSON if items arrives as a JSON string
    if (is_string($items)) {
        $items = json_decode($items, true);
    }
    
    // Check that items is an array and not empty
    if (!is_array($items) || empty($items)) {
        json_out(['error' => 'No items selected'], 400);
    }
    try {
        $archive_name = cloud_archive_sanitize_name((string)$archive_name, $format);
    } catch (Throwable $e) {
        json_out(['error' => $e->getMessage()], 400);
    }
    if ((string)($_POST['async'] ?? '') === '1') {
        cloud_archive_start_job('create_archive', ['user' => $user, 'email' => get_session_email(), 'root' => $rootKey, 'items' => $items, 'archive_name' => $archive_name, 'format' => $format, 'password' => $password, 'temporary' => (string)($_POST['temporary'] ?? '') === '1', 'type' => 'create_archive'], 'cloud_archive_create_selected_job');
    }
    $archive_path = $base . '/' . $archive_name;
    if (file_exists($archive_path)) json_out(['error' => 'exists'], 409);
    $tmp_path = cloud_archive_tmp_dir() . '/' . bin2hex(random_bytes(8)) . '.' . cloud_archive_format_extension($format);
    try {
        $added_count = cloud_archive_create_file($base, $items, $tmp_path, $format, $password);
        if (!@rename($tmp_path, $archive_path)) throw new RuntimeException('Failed to move archive into place');
    } catch (Throwable $e) {
        @unlink($tmp_path);
        json_out(['error' => $e->getMessage()], 500);
    }
    $emailArch = get_session_email();
    if ($emailArch && $rootKey === 'main') invalidate_file_tree($emailArch);
    if ($emailArch) append_sync_log($emailArch, $rootKey, 'create_archive', $archive_name, 'INFO', 'Archive created');
    json_out([
        'success' => true,
        'archive_path' => $archive_name,
        'message' => 'Archive created successfully',
        'items_count' => $added_count,
        'size' => filesize($archive_path),
        'errors' => null
    ]);
}

if ($action === 'pdf_stream') {
  $token = $_GET['token'] ?? '';
  $tokenData = $_SESSION['pdf_tokens'][$token] ?? null;

  if (!$tokenData) {
    http_response_code(404); exit;
  }

  // Handle both old format (string) and new format (array)
  if (is_string($tokenData)) {
    $target = $tokenData;
    $rootKey = get_requested_root_key();
    $user = get_current_user_from_sessions();
    $isTemp = false;
  } else {
    $target = $tokenData['path'] ?? '';
    $rootKey = $tokenData['root'] ?? get_requested_root_key();
    $user = $tokenData['user'] ?? get_current_user_from_sessions();
    $isTemp = $tokenData['is_temp'] ?? false;
  }

  if (!$target) {
    http_response_code(404); exit;
  }

  // Check if file exists
  if (!file_exists($target)) {
    http_response_code(404); exit;
  }

  // If this is a temp file from encrypted documents, serve it directly
  if ($isTemp) {
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($target));
    header('Content-Disposition: inline; filename="' . rawurlencode(basename($target)) . '"');
    readfile($target);
    // Clean up temp file after serving
    @unlink($target);
    // Remove from session to prevent reuse
    unset($_SESSION['pdf_tokens'][$token]);
    exit;
  }

  // Handle encrypted documents root (legacy - for non-temp encrypted files)
  if (is_documents_root($rootKey)) {
    $plain = decrypt_documents_content((string)file_get_contents($target), $user);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($plain));
    header('Content-Disposition: inline; filename="' . rawurlencode(basename($target)) . '"');
    echo $plain;
    exit;
  }

  header('Content-Type: application/pdf');
  header('Content-Length: ' . filesize($target));
  header('Content-Disposition: inline; filename="' . rawurlencode(basename($target)) . '"');
  readfile($target);
  exit;
}

if ($action === 'raw') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  $base = user_root($user, $rootKey);
  $path = safe_path((string)($_GET['path'] ?? ''));
  $target = $base . '/' . $path;
  
  // Release session lock before file transfer
  session_write_close();
  
  if (!file_exists($target)) { http_response_code(404); echo 'Not found'; exit; }
  if (is_documents_root($rootKey)) {
    $plain = decrypt_documents_content((string)file_get_contents($target), $user);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($plain) ?: 'application/octet-stream';
    $inline = (str_starts_with($mime,'image/') || str_starts_with($mime,'text/') || $mime === 'application/pdf');
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode(basename($target)) . '"');
    echo $plain;
    exit;
  }
  $mime = mime_content_type($target) ?: 'application/octet-stream';
  $inline = (str_starts_with($mime,'image/') || str_starts_with($mime,'text/'));
  header('Content-Type: ' . $mime);
  header('X-Content-Type-Options: nosniff');
  header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode(basename($target)) . '"');
  readfile($target);
  exit;
}

if ($action === 'metadata') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  $base = user_root($user, $rootKey);
  $path = safe_path((string)($_GET['path'] ?? ''));
  $target = $base . '/' . $path;

  if (!file_exists($target)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
    exit;
  }

  // Check that this is an image
  $mime = mime_content_type($target) ?: '';
  if (!str_starts_with($mime, 'image/')) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
  }

  $output = [];
  exec('exiftool -json -GPSLatitude -GPSLongitude -GPSLatitudeRef -GPSLongitudeRef ' . escapeshellarg($target) . ' 2>/dev/null', $output);
  
  $result = [];
  if (!empty($output)) {
    $exif = json_decode(implode("\n", $output), true);
    if (!empty($exif[0])) {
      $lat = $exif[0]['GPSLatitude'] ?? null;
      $lon = $exif[0]['GPSLongitude'] ?? null;
      $latRef = $exif[0]['GPSLatitudeRef'] ?? 'N';
      $lonRef = $exif[0]['GPSLongitudeRef'] ?? 'E';
      
      if ($lat !== null && $lon !== null) {
        // Parse various GPS formats
        if (preg_match('/(\d+)\s+deg\s+(\d+)\s*\'\s+([\d.]+)\s*"/', $lat, $latMatch) &&
            preg_match('/(\d+)\s+deg\s+(\d+)\s*\'\s+([\d.]+)\s*"/', $lon, $lonMatch)) {
          $latDecimal = ($latMatch[1] + $latMatch[2]/60 + $latMatch[3]/3600) * ($latRef === 'S' ? -1 : 1);
          $lonDecimal = ($lonMatch[1] + $lonMatch[2]/60 + $lonMatch[3]/3600) * ($lonRef === 'W' ? -1 : 1);
          $result['gps'] = ['lat' => $latDecimal, 'lon' => $lonDecimal];
        } elseif (is_numeric($lat) && is_numeric($lon)) {
          // Already in decimal degrees
          $result['gps'] = ['lat' => (float)$lat * ($latRef === 'S' ? -1 : 1), 'lon' => (float)$lon * ($lonRef === 'W' ? -1 : 1)];
        }
      }
    }
  }

  header('Content-Type: application/json');
  echo json_encode($result);
  exit;
}

elseif ($action === 'raw_audio' || $action === 'raw_video') {
    $user = get_current_user_from_sessions();
    $base = user_root($user, get_requested_root_key());
    $path = safe_path((string)($_GET['path'] ?? ''));
    $target = $base . '/' . $path;
    
    // Release session lock before long-running streaming
    session_write_close();
    
    if (!file_exists($target)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    
    // Determine the MIME type
    $mime = mime_content_type($target) ?: 'application/octet-stream';
    
    // For video, check that it is a video file (optional)
    if ($action === 'raw_video' && !str_starts_with($mime, 'video/')) {
        // If video was requested but it is not video - still serve it with the correct header
        // You can uncomment the check if strict validation is needed
        // http_response_code(415);
        // echo 'Not a video file';
        // exit;
    }
    
    // Get the file size
    $size = filesize($target);
    
    // Range support for seeking
    if (isset($_SERVER['HTTP_RANGE'])) {
        // Parse the Range header
        if (preg_match('/bytes=\h*(\d+)-(\d*)/i', $_SERVER['HTTP_RANGE'], $matches)) {
            $start = intval($matches[1]);
            $end = isset($matches[2]) && strlen($matches[2]) ? intval($matches[2]) : $size - 1;
            
            // Validation
            if ($start >= $size || $start > $end) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
            
            $length = $end - $start + 1;
            
            // Set headers for partial content
            http_response_code(206);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . $length);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Accept-Ranges: bytes');
            header('Cache-Control: no-cache, must-revalidate');
            header('X-Content-Type-Options: nosniff');
            
            // Open the file and serve only the requested part
            $fp = fopen($target, 'rb');
            fseek($fp, $start);
            
            $chunkSize = 8192; // 8KB chunks
            $bytesSent = 0;
            
            while (!feof($fp) && $bytesSent < $length && connection_status() === CONNECTION_NORMAL) {
                $remaining = $length - $bytesSent;
                $readSize = $remaining < $chunkSize ? $remaining : $chunkSize;
                
                $buffer = fread($fp, $readSize);
                if ($buffer === false) break;
                
                echo $buffer;
                flush();
                $bytesSent += strlen($buffer);
            }
            
            fclose($fp);
        } else {
            http_response_code(400);
            exit;
        }
    } else {
        // Regular request - serve the whole file
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        
        // For large files, use streaming
        if ($size > 10 * 1024 * 1024) { // larger than 10MB
            $fp = fopen($target, 'rb');
            $chunkSize = 8192;
            
            while (!feof($fp) && connection_status() === CONNECTION_NORMAL) {
                echo fread($fp, $chunkSize);
                flush();
            }
            
            fclose($fp);
        } else {
            // Serve small files in full
            readfile($target);
        }
    }
    exit;
}

elseif ($action === 'audio_info') {
    $user = get_current_user_from_sessions();
    $base = user_root($user, get_requested_root_key());
    $path = safe_path((string)($_GET['path'] ?? ''));
    $target = $base . '/' . $path;
    
    if (!file_exists($target)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    
    $result = [
        'size' => filesize($target),
        'duration' => 0,
        'bitrate' => 0,
        'format' => pathinfo($target, PATHINFO_EXTENSION)
    ];
    
    // Try to get the duration via getID3 or other methods
    if (function_exists('shell_exec') && is_executable('/usr/bin/ffprobe')) {
        try {
            $cmd = escapeshellcmd("/usr/bin/ffprobe -v quiet -print_format json -show_format -show_streams " . escapeshellarg($target));
            $output = shell_exec($cmd);
            if ($output) {
                $info = json_decode($output, true);
                if (isset($info['format']['duration'])) {
                    $result['duration'] = (float)$info['format']['duration'];
                }
                if (isset($info['format']['bit_rate'])) {
                    $result['bitrate'] = (int)$info['format']['bit_rate'];
                }
            }
        } catch (Exception $e) {
            // ignore
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

elseif ($action === 'file_info') {
    $user = get_current_user_from_sessions();
    $base = user_root($user, get_requested_root_key());
    $path = safe_path((string)($_GET['path'] ?? ''));
    $target = $base . '/' . $path;
    
    if (!file_exists($target)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    
    $result = [
        'size' => filesize($target),
        'modified' => filemtime($target),
        'created' => filectime($target),
        'mime' => mime_content_type($target) ?: 'application/octet-stream'
    ];
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

if ($action === 'extract_archive') {
    $user = get_current_user_from_sessions();
    $rootKey = get_requested_root_key();
    $base = user_root($user, $rootKey);
    $archive_path = safe_path((string)($_POST['archive_path'] ?? ''));
    $extract_to = safe_path((string)($_POST['extract_to'] ?? ''));
    $format = cloud_archive_normalize_format((string)($_POST['format'] ?? ''), $archive_path);
    $password = (string)($_POST['password'] ?? '');
    
    if ($archive_path === '') {
        json_out(['error' => 'archive_path is required'], 400);
    }
    if ((string)($_POST['async'] ?? '') === '1') {
        cloud_archive_start_job('extract_archive', ['user' => $user, 'email' => get_session_email(), 'root' => $rootKey, 'archive_path' => $archive_path, 'extract_to' => $extract_to, 'format' => $format, 'password' => $password, 'type' => 'extract_archive'], 'cloud_archive_extract_job');
    }
    
    $full_archive_path = $base . '/' . $archive_path;
    
    if (!file_exists($full_archive_path)) {
        json_out(['error' => 'Archive not found'], 404);
    }
    $extract_full_path = $extract_to === '' ? dirname($full_archive_path) : $base . '/' . $extract_to;
    try {
        $extracted_count = cloud_archive_extract_file($full_archive_path, $extract_full_path, $format, $password);
    } catch (Throwable $e) {
        json_out(['error' => $e->getMessage()], 500);
    }
    $emailEx = get_session_email();
    if ($emailEx && $rootKey === 'main') invalidate_file_tree($emailEx);
    if ($emailEx) append_sync_log($emailEx, $rootKey, 'extract_archive', $archive_path, 'INFO', 'Archive extracted');
    json_out([
        'success' => true,
        'message' => 'Archive extracted successfully',
        'extracted_to' => $extract_to === '' ? dirname($archive_path) : $extract_to,
        'extracted_files_count' => $extracted_count
    ]);
}

if ($action === 'archive_folder') {
    $user = get_current_user_from_sessions();
    $rootKey = get_requested_root_key();
    $base = user_root($user, $rootKey);
    $folder_path = safe_path((string)($_POST['folder_path'] ?? ''));
    $format = cloud_archive_normalize_format((string)($_POST['format'] ?? ''), (string)($_POST['archive_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    
    if ($folder_path === '') {
        json_out(['error' => 'folder_path is required'], 400);
    }
    if ((string)($_POST['async'] ?? '') === '1') {
        cloud_archive_start_job('archive_folder', ['user' => $user, 'email' => get_session_email(), 'root' => $rootKey, 'folder_path' => $folder_path, 'format' => $format, 'password' => $password, 'archive_name' => (string)($_POST['archive_name'] ?? ''), 'type' => 'archive_folder'], 'cloud_archive_folder_job');
    }
    
    $full_path = $base . '/' . $folder_path;
    
    if (!is_dir($full_path)) {
        json_out(['error' => 'Folder not found'], 404);
    }
    $folder_name = basename($folder_path);
    try {
        $archive_name = cloud_archive_sanitize_name((string)($_POST['archive_name'] ?? ($folder_name . '_' . date('Y-m-d_H-i-s'))), $format);
    } catch (Throwable $e) {
        json_out(['error' => $e->getMessage()], 400);
    }
    $archive_path = dirname($full_path) . '/' . $archive_name;
    if (file_exists($archive_path)) json_out(['error' => 'Archive already exists'], 409);
    $tmp_path = cloud_archive_tmp_dir() . '/' . bin2hex(random_bytes(8)) . '.' . cloud_archive_format_extension($format);
    try {
        $added_count = cloud_archive_create_file($base, [$folder_path], $tmp_path, $format, $password);
        if (!@rename($tmp_path, $archive_path)) throw new RuntimeException('Failed to move archive into place');
    } catch (Throwable $e) {
        @unlink($tmp_path);
        json_out(['error' => $e->getMessage()], 500);
    }
    $emailArch2 = get_session_email();
    if ($emailArch2 && $rootKey === 'main') invalidate_file_tree($emailArch2);
    if ($emailArch2) append_sync_log($emailArch2, $rootKey, 'archive_folder', $folder_path, 'INFO', 'Folder archived');
    $result_path = trim(dirname($folder_path) . '/' . $archive_name, './');
    json_out([
        'success' => true,
        'archive_path' => $result_path,
        'archive_name' => $archive_name,
        'message' => 'Folder archived successfully',
        'files_count' => $added_count,
        'size' => filesize($archive_path)
    ]);
}

if ($action === 'convert_file') {
    $user = get_current_user_from_sessions();
    $rootKey = get_requested_root_key();
    $base = user_root($user, $rootKey);
    $source_path = safe_path((string)($_POST['source_path'] ?? ''));
    $target_format = (string)($_POST['target_format'] ?? '');
    $output_path = safe_path((string)($_POST['output_path'] ?? ''));
    $delete_original = filter_var($_POST['delete_original'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($source_path === '' || $target_format === '') {
        json_out(['error' => 'source_path and target_format required'], 400);
    }
    if (!preg_match('/^[a-z0-9]{1,16}$/i', $target_format)) {
        json_out(['error' => 'invalid target format'], 400);
    }
    
    $source_full = $base . '/' . $source_path;
    
    if (!file_exists($source_full)) {
        json_out(['error' => 'source file not found'], 404);
    }
    
    // Determine the output path
    $source_dir = dirname($source_path);
    if ($source_dir === '.' || $source_dir === '') {
        $source_dir = '';
    }
    $source_filename = pathinfo($source_path, PATHINFO_FILENAME);
    
    if ($output_path === '') {
        $output_path = ($source_dir === '' ? '' : ($source_dir . '/')) . $source_filename . '.' . $target_format;
    } else {
        // If a folder path is given, append the file name
        if (strpos($output_path, '.') === false) {
            $output_path = rtrim($output_path, '/') . '/' . $source_filename . '.' . $target_format;
        }
    }
    $output_path = ltrim(str_replace('\\', '/', $output_path), '/');
    if (($nameError = cloud_validate_relative_path_names($output_path)) !== null) json_out(['error'=>$nameError], 400);
    
    $output_full = $base . '/' . $output_path;
    
    // Check if the target file already exists
    if (file_exists($output_full)) {
        json_out(['error' => 'target file already exists'], 409);
    }
    
    // Create the output file directory if needed
    $output_dir = dirname($output_full);
    if (file_exists($output_dir) && !is_dir($output_dir)) {
        json_out(['error' => 'target is not directory'], 409);
    }
    if (!is_dir($output_dir)) {
        @mkdir($output_dir, 0775, true);
    }
    
    try {
    $result = convertFile($source_full, $output_full, $target_format);
    
    if ($result && file_exists($output_full)) {
        // Delete the original file ONLY if explicitly requested
        if ($delete_original === true) {
    if (file_exists($source_full)) {
        if (!@unlink($source_full)) {
            //cloud_safe_error_log("Failed to delete original file: " . $source_full);
        }
    }
}
        $emailConv = get_session_email();
        // Only invalidate cache for main root, not for hidden roots
        if ($emailConv && $rootKey === 'main') invalidate_file_tree($emailConv);
        json_out([
            'ok' => true,
            'output_path' => $output_path,
            'message' => 'File converted successfully',
            'original_deleted' => $delete_original
        ]);
    } else {
        json_out(['error' => 'Conversion failed'], 500);
    }
} catch (Exception $e) {
    json_out(['error' => $e->getMessage()], 500);
}
}

function cloud_pdf_utf16_hex(string $text): string {
    $utf16 = "\xFE\xFF" . iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    return strtoupper(bin2hex($utf16));
}

function cloud_pdf_chars(string $text): array {
    if (preg_match_all('/./us', $text, $m)) return $m[0];
    return str_split($text);
}

function cloud_pdf_wrap_text(string $text, int $limit): array {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            $out[] = '';
            continue;
        }
        $words = preg_split('/\s+/u', $line) ?: [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (count(cloud_pdf_chars($candidate)) <= $limit) {
                $current = $candidate;
                continue;
            }
            if ($current !== '') $out[] = $current;
            $chars = cloud_pdf_chars($word);
            while (count($chars) > $limit) {
                $out[] = implode('', array_splice($chars, 0, $limit));
            }
            $current = implode('', $chars);
        }
        if ($current !== '') $out[] = $current;
    }
    return $out;
}

function cloud_markdown_to_pdf_text(string $markdown): string {
    $text = str_replace(["\r\n", "\r"], "\n", $markdown);
    $text = preg_replace('/```[\s\S]*?```/', '', $text);
    $text = preg_replace('/`([^`]*)`/', '$1', $text);
    $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/u', '$1', $text);
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $text);
    $text = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $text);
    $text = preg_replace('/^\s{0,3}>\s?/m', '', $text);
    $text = preg_replace('/^\s*[-*+]\s+/m', '- ', $text);
    $text = preg_replace('/^\s*\d+\.\s+/m', '', $text);
    $text = preg_replace('/[*_~]{1,3}([^*_~]+)[*_~]{1,3}/u', '$1', $text);
    $text = preg_replace('/^\s*\|?[-:\s|]+\|?\s*$/m', '', $text);
    return trim($text);
}

function cloud_generate_simple_markdown_pdf(string $markdown, string $pdfPath): bool {
    if (cloud_generate_markdown_pdf_image($markdown, $pdfPath)) return true;
    $fontPath = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    if (!is_file($fontPath)) return false;
    $font = file_get_contents($fontPath);
    if ($font === false) return false;
    $lines = cloud_pdf_wrap_text(cloud_markdown_to_pdf_text($markdown), 86);
    if (!$lines) $lines = [''];
    $perPage = 48;
    $pages = array_chunk($lines, $perPage);
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $fontObj = 3 + count($pages) * 2;
    $descObj = $fontObj + 1;
    $fontDescriptorObj = $fontObj + 2;
    $fileObj = $fontObj + 3;
    $pageRefs = [];
    foreach ($pages as $i => $pageLines) {
        $pageObj = 3 + $i * 2;
        $contentObj = $pageObj + 1;
        $pageRefs[] = $pageObj . ' 0 R';
        $stream = "BT\n/F1 11 Tf\n50 790 Td\n14 TL\n";
        foreach ($pageLines as $line) {
            $stream .= '<' . cloud_pdf_utf16_hex($line) . "> Tj\nT*\n";
        }
        $stream .= "ET\n";
        $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontObj . ' 0 R >> >> /Contents ' . $contentObj . ' 0 R >>';
        $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pages) . ' >>';
    $objects[$fontObj] = '<< /Type /Font /Subtype /Type0 /BaseFont /DejaVuSans /Encoding /Identity-H /DescendantFonts [' . $descObj . ' 0 R] >>';
    $objects[$descObj] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DejaVuSans /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor ' . $fontDescriptorObj . ' 0 R /DW 600 /CIDToGIDMap /Identity >>';
    $objects[$fontDescriptorObj] = '<< /Type /FontDescriptor /FontName /DejaVuSans /Flags 4 /FontBBox [-1021 -463 1794 1233] /ItalicAngle 0 /Ascent 928 /Descent -236 /CapHeight 928 /StemV 80 /FontFile2 ' . $fileObj . ' 0 R >>';
    $objects[$fileObj] = "<< /Length " . strlen($font) . " /Length1 " . strlen($font) . " >>\nstream\n" . $font . "\nendstream";
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $max = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return file_put_contents($pdfPath, $pdf) !== false && is_file($pdfPath) && filesize($pdfPath) > 0;
}

function cloud_generate_markdown_pdf_image(string $markdown, string $pdfPath): bool {
    $fontPath = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    $input = tempnam(sys_get_temp_dir(), 'cloud_md_pdf_');
    if ($input === false) return false;
    file_put_contents($input, $markdown);
    $helper = dirname(__DIR__, 4) . '/lib/py_sidecar.php';
    if (is_file($helper)) {
        require_once $helper;
    }
    if (function_exists('py_sidecar_enabled') && py_sidecar_enabled()) {
        $res = py_sidecar_binary('process-pdf', ['font_path' => $fontPath], $input, 'markdown', 120);
        @unlink($input);
        if (!$res['ok'] || $res['raw'] === '') {
            return false;
        }
        return file_put_contents($pdfPath, $res['raw']) !== false && is_file($pdfPath) && filesize($pdfPath) > 0;
    }
    if (!is_file($fontPath)) {
        @unlink($input);
        return false;
    }
    $script = dirname(__DIR__, 4) . '/deploy/python-sidecar/process_pdf.py';
    if (!is_file($script)) {
        @unlink($input);
        return false;
    }
    $cmd = 'python3 ' . escapeshellarg($script) . ' ' . escapeshellarg($input) . ' ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($fontPath);
    $output = [];
    $code = 1;
    exec($cmd . ' 2>&1', $output, $code);
    @unlink($input);
    return $code === 0 && is_file($pdfPath) && filesize($pdfPath) > 0;
}

if ($action === 'export_pdf') {
    // Ensure clean JSON output - clear any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }
    error_reporting(0);
    ini_set('display_errors', 0);

    $user = get_current_user_from_sessions();
    $path = safe_path((string)($_POST['path'] ?? ''));

    if ($path === '') {
        json_out(['error' => 'path required'], 400);
    }

    // Determine rootKey from the path
    $rootKey = get_requested_root_key();
    $rootMap = [
        '.archive' => 'archive',
        '.temp_files' => 'temp',
        '.documents' => 'documents',
        '.photos' => 'photos',
        '.pdf' => 'pdf',
        '.trash' => 'trash'
    ];
    
    // If the path starts with one of the hidden directories, determine rootKey automatically
    foreach ($rootMap as $dirName => $key) {
        if (strpos($path, $dirName . '/') === 0 || $path === $dirName) {
            $rootKey = $key;
            // Strip the root directory name from the path
            $path = substr($path, strlen($dirName) + 1);
            break;
        }
    }
    
    $base = user_root($user, $rootKey);

    if ($path === '') {
        json_out(['error' => 'path required'], 400);
    }

    $fullPath = $base . '/' . $path;

    if (!file_exists($fullPath)) {
        json_out(['error' => 'file not found'], 404);
    }

    if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'md') {
        json_out(['error' => 'only markdown files supported'], 400);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $pdfFilename = pathinfo($fullPath, PATHINFO_FILENAME) . '.pdf';
    $pdfPath = dirname($fullPath) . '/' . $pdfFilename;

    // Clear any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }

    $returnCode = -1;
    $output = [];
    $errorMessage = '';

    $pandocPath = is_executable('/usr/bin/pandoc') ? '/usr/bin/pandoc' : '';
    if ($pandocPath !== '') {
        $command = $pandocPath . ' ' . escapeshellarg($fullPath) . ' -o ' . escapeshellarg($pdfPath) . ' --pdf-engine=/usr/bin/xelatex -V mainfont="Liberation Serif" -V papersize=a4';
        exec($command . ' 2>&1', $output, $returnCode);
    }

    if ($pandocPath !== '' && ($returnCode !== 0 || !file_exists($pdfPath))) {
        $output = [];
        $command = $pandocPath . ' ' . escapeshellarg($fullPath) . ' -o ' . escapeshellarg($pdfPath) . ' --pdf-engine=/usr/bin/xelatex -V mainfont="Liberation Serif" -V papersize=a4 --variable=CJKmainfont:"Noto Sans CJK SC"';
        exec($command . ' 2>&1', $output, $returnCode);
    }

    if (($pandocPath === '' || $returnCode !== 0 || !file_exists($pdfPath)) && is_readable($fullPath)) {
        $fallbackOk = cloud_generate_simple_markdown_pdf((string)file_get_contents($fullPath), $pdfPath);
        if ($fallbackOk) {
            $returnCode = 0;
            $output = [];
        }
    }

    // Ensure clean output buffer before sending JSON response
    while (ob_get_level()) {
        ob_end_clean();
    }

    if ($returnCode === 0 && file_exists($pdfPath)) {
        $relativePdfPath = str_replace($base . '/', '', $pdfPath);
        $emailPdf = get_session_email();
        // Only invalidate cache for main root, not for hidden roots
        if ($emailPdf && $rootKey === 'main') invalidate_file_tree($emailPdf);
        json_out([
            'success' => true,
            'pdf_path' => $relativePdfPath,
            'pdf_name' => $pdfFilename,
            'message' => 'PDF generated successfully',
            'size' => filesize($pdfPath)
        ]);
    } else {
        // Check whether pandoc creates the file but returns an error
        if (file_exists($pdfPath)) {
            $relativePdfPath = str_replace($base . '/', '', $pdfPath);
            $emailPdf = get_session_email();
            // Only invalidate cache for main root, not for hidden roots
            if ($emailPdf && $rootKey === 'main') invalidate_file_tree($emailPdf);
            json_out([
                'success' => true,
                'pdf_path' => $relativePdfPath,
                'pdf_name' => $pdfFilename,
                'message' => 'PDF generated (with warnings)',
                'size' => filesize($pdfPath),
                'warning' => 'Process returned code ' . $returnCode
            ]);
        } else {
            $errorMessage = $pandocPath === '' ? 'PDF export failed: pandoc is not installed and fallback generation failed' : 'Pandoc failed with return code ' . $returnCode;
            if (!empty($output)) {
                $errorMessage .= ': ' . implode(' | ', array_slice($output, 0, 5));
            }
            json_out([
                'success' => false,
                'error' => $errorMessage,
                'details' => [
                    'return_code' => $returnCode,
                    'output' => array_slice($output, 0, 10)
                ]
            ]);
        }
    }
}

if ($action === 'get_ai_config') {
    cloud_api_email();
    $config = loadAIConfig();
    if (isUserRoot()) {
        json_out($config);
    } else {
        json_out([
            'default_model' => $config['default_model'] ?? '',
            'models' => $config['models'] ?? [],
            'has_key' => !empty($config['api_key']),
        ]);
    }
}

if ($action === 'save_ai_config') {
    // Check the request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(['error' => 'Method not allowed'], 405);
    }
    
    // Get data from POST or from the request body
    $input = $_POST;
    
    // If the data arrived as JSON in the request body
    if (empty($input) && $jsonInput = file_get_contents('php://input')) {
        $input = json_decode($jsonInput, true);
    }
    
    if (!is_array($input)) {
        json_out(['error' => 'Invalid input'], 400);
    }
    
    // Save the configuration
    $result = saveAIConfig($input);
    
    if (isset($result['error'])) {
        json_out(['error' => $result['error']], $result['code'] ?? 400);
    }
    
    json_out($result);
}

if ($action === 'debug_session') {
    if (!isUserRoot()) json_out(['error' => 'forbidden'], 403);
    $db = getDB();
    $session_id  = session_id();
    $cookie_sid  = $_COOKIE['PHPSESSID'] ?? null;
    $email       = get_session_email();
    $db_row = null;
    if ($db && $cookie_sid) {
        $stmt = $db->prepare('SELECT session_id, email FROM active_sessions WHERE session_id = :sid');
        $stmt->bindValue(':sid', $cookie_sid, PDO::PARAM_STR);
        $db_row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    }
    json_out([
        'php_session_id'   => $session_id,
        'cookie_PHPSESSID' => $cookie_sid,
        'match'            => ($session_id === $cookie_sid),
        'resolved_email'   => $email,
        'db_row_for_cookie'=> $db_row,
        'all_cookies'      => array_keys($_COOKIE),
    ]);
}

if ($action === 'get_file_tree') {
    $email = cloud_api_email();
    $user = cloud_api_user();
    cloud_session_close_early();
    $db   = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable']);

    try {
        ensure_file_tree_table($db);
        $clientHash = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['hash'] ?? $_POST['hash'] ?? '')));
        if ($clientHash !== '') {
            $hashStmt = $db->prepare("SELECT tree_hash AS h FROM cloud_folder_state WHERE email = :e AND tree_valid = 1 AND tree_hash <> '' LIMIT 1");
            $hashStmt->bindValue(':e', $email, PDO::PARAM_STR);
            $hashRow = $hashStmt->execute()->fetch(PDO::FETCH_ASSOC);
            if ($hashRow && (string)$hashRow['h'] === $clientHash) {
                json_out(['ok' => true, 'unchanged' => true, 'hash' => $clientHash]);
            }
        }
        $stmt = $db->prepare("SELECT tree_json, tree_hash, tree_valid FROM cloud_folder_state WHERE email = :e");
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['tree_valid'] == 1 && !empty($row['tree_json'])) {
            $treeHash = (string)($row['tree_hash'] ?? '');
            if ($treeHash === '') $treeHash = md5((string)$row['tree_json']);
            if ($clientHash !== '' && $clientHash === $treeHash) {
                json_out(['ok' => true, 'unchanged' => true, 'hash' => $treeHash]);
            }
            $items = json_decode($row['tree_json'], true) ?? [];
            $items = cloud_dedupe_tree_items($items);
            json_out(['ok' => true, 'items' => $items, 'from_cache' => true, 'hash' => $treeHash]);
        } else {
            rebuild_file_tree($email, $user);
            $stmt2 = $db->prepare("SELECT tree_json, tree_hash FROM cloud_folder_state WHERE email = :e");
            $stmt2->bindValue(':e', $email, PDO::PARAM_STR);
            $row2 = $stmt2->execute()->fetch(PDO::FETCH_ASSOC);
            $items = $row2 ? (json_decode($row2['tree_json'], true) ?? []) : [];
            $items = cloud_dedupe_tree_items($items);
            $outHash = $row2 ? ((string)($row2['tree_hash'] ?? '') ?: md5((string)$row2['tree_json'])) : '';
            json_out(['ok' => true, 'items' => $items, 'from_cache' => false, 'hash' => $outHash]);
        }
    } catch (Exception $e) {
        cloud_safe_error_log("get_file_tree failed: " . $e->getMessage());
        json_out(['ok' => false, 'error' => $e->getMessage()]);
    }
}

if ($action === 'invalidate_file_tree') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated']);
    invalidate_file_tree($email);
    json_out(['ok' => true]);
}

if ($action === 'get_storage_usage') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);

    $user = get_current_user_from_sessions();
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);

    try {
        ensure_storage_usage_table($db);
        $stmt = $db->prepare("
            SELECT stats_json, root_total_bytes, updated_at
            FROM cloud_storage_usage_stats
            WHERE email = :e
        ");
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['stats_json'])) {
            $folders = json_decode($row['stats_json'], true) ?? [];
            $folders = array_values(array_filter($folders, function ($f) {
                return isset($f['path']) && $f['path'] !== '/';
            }));
            json_out([
                'ok' => true,
                'from_cache' => true,
                'updated_at' => (int)($row['updated_at'] ?? time()),
                'root_total_bytes' => (int)($row['root_total_bytes'] ?? 0),
                'folders' => $folders
            ]);
        }

        $fresh = save_storage_usage_stats($email, $user);
        if (!$fresh['ok']) {
            json_out(['ok' => false, 'error' => $fresh['error'] ?? 'failed to build storage usage'], 500);
        }
        json_out($fresh);
    } catch (Exception $e) {
        cloud_safe_error_log("get_storage_usage failed: " . $e->getMessage());
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'refresh_storage_usage') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $user = get_current_user_from_sessions();
    $fresh = save_storage_usage_stats($email, $user);
    if (!$fresh['ok']) {
        json_out(['ok' => false, 'error' => $fresh['error'] ?? 'scan failed'], 500);
    }
    json_out($fresh);
}

if (!function_exists('cloud_ai_ensure_chat_tables')) {
    function cloud_ai_ensure_chat_tables(AppDatabase $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS cloud_ai_assistant_chats (
            id BIGSERIAL PRIMARY KEY,
            email TEXT NOT NULL,
            chat_id TEXT NOT NULL DEFAULT '',
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            model TEXT NOT NULL DEFAULT '',
            file_name TEXT NOT NULL DEFAULT '',
            time_seconds REAL NULL,
            tokens INTEGER NULL,
            speed REAL NULL,
            meta_json TEXT NOT NULL DEFAULT '',
            created_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint)
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_ai_assistant_chats_email_created ON cloud_ai_assistant_chats(email, created_at DESC)");
        $db->exec("CREATE TABLE IF NOT EXISTS cloud_ai_assistant_user_settings (
            email TEXT PRIMARY KEY,
            settings_json TEXT NOT NULL DEFAULT '{}',
            updated_at BIGINT NOT NULL DEFAULT (extract(epoch from now())::bigint),
            auto_confirm_agent_edits INTEGER NOT NULL DEFAULT 0
        )");
        try {
            $db->exec("ALTER TABLE cloud_ai_assistant_chats ADD COLUMN IF NOT EXISTS chat_id TEXT NOT NULL DEFAULT ''");
            $db->exec("ALTER TABLE cloud_ai_assistant_chats ADD COLUMN IF NOT EXISTS meta_json TEXT NOT NULL DEFAULT ''");
        } catch (Throwable $e) {
        }
        $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_ai_assistant_chats_email_chat_created ON cloud_ai_assistant_chats(email, chat_id, created_at DESC)");
    }
}

if ($action === 'ai_chat_append') {
    $email = get_session_email();
    if (!$email) json_out(['success' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['success' => false, 'error' => 'db unavailable'], 500);
    cloud_ai_ensure_chat_tables($db);
    $stmt = $db->prepare("INSERT INTO cloud_ai_assistant_chats
        (email, chat_id, question, answer, model, file_name, time_seconds, tokens, speed, meta_json, created_at)
        VALUES (:email, :chat_id, :question, :answer, :model, :file_name, :time_seconds, :tokens, :speed, :meta_json, extract(epoch from now())::bigint)");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':chat_id', (string)($_POST['chat_id'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':question', (string)($_POST['question'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':answer', (string)($_POST['answer'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':model', (string)($_POST['model'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':file_name', (string)($_POST['file_name'] ?? ''), PDO::PARAM_STR);
    $stmt->bindValue(':time_seconds', is_numeric($_POST['time_seconds'] ?? null) ? (float)$_POST['time_seconds'] : null, PDO::PARAM_STR);
    $stmt->bindValue(':tokens', is_numeric($_POST['tokens'] ?? null) ? (int)$_POST['tokens'] : null, PDO::PARAM_INT);
    $stmt->bindValue(':speed', is_numeric($_POST['speed'] ?? null) ? (float)$_POST['speed'] : null, PDO::PARAM_STR);
    $stmt->bindValue(':meta_json', (string)($_POST['meta_json'] ?? ''), PDO::PARAM_STR);
    $ok = $stmt->execute();
    json_out(['success' => (bool)$ok, 'id' => method_exists($db, 'lastInsertRowID') ? $db->lastInsertRowID() : null]);
}

if ($action === 'ai_chat_load') {
    $email = get_session_email();
    if (!$email) json_out(['success' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['success' => false, 'error' => 'db unavailable'], 500);
    cloud_ai_ensure_chat_tables($db);
    $limit = max(1, min(50, (int)($_POST['limit'] ?? 5)));
    $offset = max(0, (int)($_POST['offset'] ?? 0));
    $stmt = $db->prepare("SELECT id, question, answer, model, file_name, time_seconds, tokens, speed, meta_json, created_at
        FROM cloud_ai_assistant_chats
        WHERE email = :email
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $res = $stmt->execute();
    $items = [];
    while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
        $items[] = $row;
    }
    $hasMore = count($items) > $limit;
    if ($hasMore) $items = array_slice($items, 0, $limit);
    json_out(['success' => true, 'items' => $items, 'has_more' => $hasMore]);
}

if ($action === 'ai_chat_clear') {
    $email = get_session_email();
    if (!$email) json_out(['success' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['success' => false, 'error' => 'db unavailable'], 500);
    cloud_ai_ensure_chat_tables($db);
    $stmt = $db->prepare("DELETE FROM cloud_ai_assistant_chats WHERE email = :email");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    json_out(['success' => true]);
}

if ($action === 'ai_user_settings_get') {
    $email = get_session_email();
    if (!$email) json_out(['success' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['success' => false, 'error' => 'db unavailable'], 500);
    cloud_ai_ensure_chat_tables($db);
    $stmt = $db->prepare("SELECT settings_json FROM cloud_ai_assistant_user_settings WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    $settings = $row ? (json_decode((string)$row['settings_json'], true) ?: []) : [];
    json_out(['success' => true, 'settings' => $settings]);
}

if ($action === 'ai_user_settings_set') {
    $email = get_session_email();
    if (!$email) json_out(['success' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['success' => false, 'error' => 'db unavailable'], 500);
    cloud_ai_ensure_chat_tables($db);
    $settings = [
        'selected_model' => (string)($_POST['selected_model'] ?? ''),
        'selected_role' => (string)($_POST['selected_role'] ?? ''),
        'chat_mode' => (string)($_POST['chat_mode'] ?? 'chat'),
        'remember_previous' => filter_var($_POST['remember_previous'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'notify_on_complete' => filter_var($_POST['notify_on_complete'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ];
    $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = $db->prepare("INSERT INTO cloud_ai_assistant_user_settings (email, settings_json, updated_at)
        VALUES (:email, :settings_json, extract(epoch from now())::bigint)
        ON CONFLICT(email) DO UPDATE SET settings_json = excluded.settings_json, updated_at = excluded.updated_at");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':settings_json', $json, PDO::PARAM_STR);
    $stmt->execute();
    json_out(['success' => true, 'settings' => $settings]);
}

if ($action === 'get_folder_state') {
    $email = get_session_email();
    if (!$email) json_out(['paths' => []]);

    $db = getDB();
    if (!$db) json_out(['paths' => []]);

    try {
        ensure_file_tree_table($db);
        
        // IMPORTANT: Check if a record exists for the user
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM cloud_folder_state WHERE email = :e");
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        
        if ($row['cnt'] == 0) {
            // Create a record for the user if it does not exist
            $insert = $db->prepare("
                INSERT INTO cloud_folder_state (email, folder_json, updated_at)
                VALUES (:e, '[]', extract(epoch from now())::bigint)
            ");
            $insert->bindValue(':e', $email, PDO::PARAM_STR);
            $insert->execute();
            json_out(['paths' => []]);
            exit;
        }
        
        $stmt = $db->prepare('SELECT folder_json FROM cloud_folder_state WHERE email = :e');
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        $paths = $row ? (json_decode($row['folder_json'], true) ?? []) : [];
        json_out(['paths' => $paths]);
    } catch (Exception $e) {
        cloud_safe_error_log("get_folder_state failed: " . $e->getMessage());
        json_out(['paths' => []]);
    }
}

if ($action === 'set_folder_state') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated']);

    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable']);

    $rawPaths = $_POST['paths'] ?? '';
    $paths    = json_decode($rawPaths, true);
    if (!is_array($paths)) $paths = [];

    $paths = array_values(array_filter(array_map('strval', $paths)));
    $json  = json_encode($paths, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        ensure_file_tree_table($db);
        // First make sure the record exists
        $check = $db->prepare("SELECT COUNT(*) as cnt FROM cloud_folder_state WHERE email = :e");
        $check->bindValue(':e', $email, PDO::PARAM_STR);
        $result = $check->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        
        if ($row['cnt'] == 0) {
            // Create the record if it does not exist
            $insert = $db->prepare("
                INSERT INTO cloud_folder_state (email, folder_json, updated_at)
                VALUES (:e, :j, extract(epoch from now())::bigint)
            ");
            $insert->bindValue(':e', $email, PDO::PARAM_STR);
            $insert->bindValue(':j', $json, PDO::PARAM_STR);
            $insert->execute();
        } else {
            // Update the existing one
            $update = $db->prepare("
                UPDATE cloud_folder_state 
                SET folder_json = :j, updated_at = extract(epoch from now())::bigint
                WHERE email = :e
            ");
            $update->bindValue(':e', $email, PDO::PARAM_STR);
            $update->bindValue(':j', $json, PDO::PARAM_STR);
            $update->execute();
        }
        
        json_out(['ok' => true]);
    } catch (Exception $e) {
        cloud_safe_error_log("set_folder_state failed: " . $e->getMessage());
        json_out(['ok' => false, 'error' => $e->getMessage()]);
    }
}

if ($action === 'patch_file_tree') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated']);

    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable']);

    $raw = $_POST['ops'] ?? '';
    $ops = json_decode($raw, true);
    if (!is_array($ops) || count($ops) === 0) json_out(['ok' => true]);

    try {
        ensure_file_tree_table($db);

        $stmt = $db->prepare("SELECT tree_json, tree_valid FROM cloud_folder_state WHERE email = :e");
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);

        // If cache is missing/invalid, build it once from disk,
        // then apply patch ops (add/remove/rename) without full UI refresh.
        if (!$row || $row['tree_valid'] != 1 || empty($row['tree_json'])) {
            $user = get_current_user_from_sessions();
            if ($user) {
                rebuild_file_tree($email, $user);
                $stmtReload = $db->prepare("SELECT tree_json, tree_valid FROM cloud_folder_state WHERE email = :e");
                $stmtReload->bindValue(':e', $email, PDO::PARAM_STR);
                $row = $stmtReload->execute()->fetch(PDO::FETCH_ASSOC);
            }
        }

        if (!$row || $row['tree_valid'] != 1) {
            json_out(['ok' => false, 'error' => 'tree cache unavailable']);
        }

        $items = cloud_dedupe_tree_items(json_decode($row['tree_json'], true) ?? []);

        foreach ($ops as $op) {
            $opType = $op['op'] ?? '';
            if ($opType === 'add' && isset($op['item'])) {
                $newItem = $op['item'];
                if (cloud_is_hidden_main_tree_path((string)($newItem['path'] ?? ''))) continue;
                $exists = false;
                foreach ($items as $it) { if ($it['path'] === $newItem['path']) { $exists = true; break; } }
                if (!$exists) $items[] = $newItem;

            } elseif ($opType === 'remove' && isset($op['path'])) {
                $removePath = $op['path'];
                $items = array_values(array_filter($items, function($item) use ($removePath) {
                    return $item['path'] !== $removePath
                        && strpos($item['path'], $removePath . '/') !== 0;
                }));

            } elseif (($opType === 'rename' || $opType === 'move') && isset($op['old_path'], $op['new_path'])) {
                $oldPath = $op['old_path'];
                $newPath = $op['new_path'];
                $newName = $op['new_name'] ?? basename($newPath);
                $items = array_map(function($item) use ($oldPath, $newPath, $newName) {
                    if ($item['path'] === $oldPath) {
                        $item['path'] = $newPath;
                        $item['name'] = $newName;
                    } elseif (strpos($item['path'], $oldPath . '/') === 0) {
                        $item['path'] = $newPath . '/' . substr($item['path'], strlen($oldPath) + 1);
                    }
                    return $item;
                }, $items);
            }
        }

        $items = cloud_dedupe_tree_items($items);
        $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = md5((string)$json);

        $check = $db->prepare("SELECT COUNT(*) as cnt FROM cloud_folder_state WHERE email = :e");
        $check->bindValue(':e', $email, PDO::PARAM_STR);
        $cnt = $check->execute()->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
        if ($cnt == 0) {
            $s = $db->prepare("INSERT INTO cloud_folder_state (email, folder_json, tree_json, tree_hash, tree_valid, updated_at) VALUES (:e, '[]', :j, :h, 1, extract(epoch from now())::bigint)");
        } else {
            $s = $db->prepare("UPDATE cloud_folder_state SET tree_json = :j, tree_hash = :h, tree_valid = 1, updated_at = extract(epoch from now())::bigint WHERE email = :e");
        }
        $s->bindValue(':e', $email, PDO::PARAM_STR);
        $s->bindValue(':j', $json, PDO::PARAM_STR);
        $s->bindValue(':h', $hash, PDO::PARAM_STR);
        $s->execute();

        json_out(['ok' => true, 'count' => count($items), 'hash' => $hash]);
    } catch (Exception $e) {
        cloud_safe_error_log("patch_file_tree failed: " . $e->getMessage());
        json_out(['ok' => false, 'error' => $e->getMessage()]);
    }
}

if ($action === 'monaco_collab_ticket') {
    $email = cloud_api_email();
    $ticket = bin2hex(random_bytes(16));
    $dir = sys_get_temp_dir() . '/cloud_monaco_tickets';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $payload = json_encode(['email' => $email, 'exp' => time() + 3600], JSON_UNESCAPED_UNICODE);
    @file_put_contents($dir . '/' . $ticket . '.json', $payload);
    json_out(['ok' => true, 'ticket' => $ticket, 'expires_in' => 3600]);
}

if ($action === 'search_tree') {
    $email = cloud_api_email();
    $user = cloud_api_user();
    cloud_session_close_early();
    $q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
    if ($q === '' || mb_strlen($q) < 2) json_out(['ok' => true, 'items' => []]);
    $limit = min(500, max(1, (int)($_GET['limit'] ?? $_POST['limit'] ?? 200)));
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensure_file_tree_table($db);
    $stmt = $db->prepare("SELECT tree_json, tree_valid FROM cloud_folder_state WHERE email = :e LIMIT 1");
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)($row['tree_valid'] ?? 0) !== 1 || empty($row['tree_json'])) {
        rebuild_file_tree($email, $user);
        $stmt = $db->prepare("SELECT tree_json, tree_valid FROM cloud_folder_state WHERE email = :e LIMIT 1");
        $stmt->bindValue(':e', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    }
    $items = $row ? (json_decode((string)$row['tree_json'], true) ?? []) : [];
    $needle = mb_strtolower($q);
    $out = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $name = mb_strtolower((string)($item['name'] ?? ''));
        $path = mb_strtolower((string)($item['path'] ?? ''));
        if ($name === '' && $path === '') continue;
        if (mb_strpos($name, $needle) !== false || mb_strpos($path, $needle) !== false) {
            $out[] = $item;
            if (count($out) >= $limit) break;
        }
    }
    json_out(['ok' => true, 'items' => $out, 'count' => count($out)]);
}

json_out(['error'=>'unknown action'], 400);
