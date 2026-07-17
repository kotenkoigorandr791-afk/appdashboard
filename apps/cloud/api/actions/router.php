<?php
$action = $_GET['action'] ?? $_POST['action'] ?? '';
require_once __DIR__ . '/ai_agent.php';
require_once __DIR__ . '/cloud_groups.php';
require_once __DIR__ . '/cloud_group_chat.php';
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

if ($action === 'get_encryption_notice_status') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensure_cloud_encryption_consents_table();
    $stmt = $db->prepare("SELECT status, consented_at FROM cloud_encryption_consents WHERE email = :e LIMIT 1");
    $stmt->bindValue(':e', $email, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
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
    $stmt->bindValue(':e', $email, SQLITE3_TEXT);
    $stmt->bindValue(':t', $now, SQLITE3_INTEGER);
    $stmt->execute();
    json_out(['ok' => true, 'status' => 'accepted']);
}

if ($action === 'get_state') {
    $current_user = get_current_user_from_sessions();
    $email = get_session_email();
    $display_email = $email ?: $current_user;

    // Квота — ищем по email напрямую (без JOIN users.name, которого не существует)
    $user_quota    = 500;
    $session_limit = 0;
    $hide_lang_switcher = 0;
    $sync_local_path = '';
    $sync_interval_sec = 300;
    $sync_mode = 'mtime_size';
    $sync_enabled = 0;
    $last_sync_at = 0;
    if ($email) {
        $db_s = getDB();
        if ($db_s) {
            try {
                ensureCloudUserSettingsSchema($db_s);
                $sq = $db_s->prepare('SELECT quota_mb, session_limit_mb, hide_lang_switcher, sync_local_path, sync_interval_sec, sync_mode, sync_enabled, last_sync_at FROM cloud_user_settings WHERE email = :e');
                $sq->bindValue(':e', $email, SQLITE3_TEXT);
                $rq = $sq->execute()->fetchArray(SQLITE3_ASSOC);
                if ($rq) {
                    $user_quota    = max(1, (int)$rq['quota_mb']);
                    $session_limit = max(0, (int)$rq['session_limit_mb']);
                    $hide_lang_switcher = ((int)($rq['hide_lang_switcher'] ?? 0)) ? 1 : 0;
                    $sync_local_path = (string)($rq['sync_local_path'] ?? '');
                    $sync_interval_sec = max(60, (int)($rq['sync_interval_sec'] ?? 300));
                    $sync_mode = (string)($rq['sync_mode'] ?? 'mtime_size');
                    $sync_enabled = ((int)($rq['sync_enabled'] ?? 0)) ? 1 : 0;
                    $last_sync_at = max(0, (int)($rq['last_sync_at'] ?? 0));
                }
            } catch (Exception $e) {
                cloud_safe_error_log("get_state quota lookup failed: " . $e->getMessage());
            }
        }
    }

    $root = user_root($current_user);
    $used_bytes = is_dir($root) ? dir_size($root) : 0;
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
  $user = get_current_user_from_sessions();;
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
  json_out(['ok' => true]);
}

if ($action === 'move_to_trash') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
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
  $targetRoot = in_array(($item['original_root'] ?? 'main'), ['main', 'archive', 'temp', 'documents', 'photos', 'pdf', 'clientsync'], true)
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
  $trashBase = user_root($user, 'trash');
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
  if ($email) append_sync_log($email, 'trash', 'empty_trash', '', 'WARNING', 'Trash emptied');
  json_out(['ok' => true, 'removed' => $removed]);
}

if ($action === 'share_file') {
    $user = get_current_user_from_sessions();
    if ($user === 'guest') json_out(['error' => 'not authenticated'], 401);

    $base = user_root($user, get_requested_root_key());
    $path = safe_path((string)($_POST['path'] ?? ''));

    if ($path === '') {
        json_out(['error' => 'path required'], 400);
    }

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

    // Нормализуем путь
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
                $columnsResult = $db->query("PRAGMA table_info(shared_files)");
            } catch (Exception $e) {
                $columnsResult = null;
            }
            while ($columnsResult && ($column = $columnsResult->fetchArray(SQLITE3_ASSOC))) {
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

        // Очищаем просроченные ссылки
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

        // Проверяем, есть ли уже активная ссылка для этого файла/папки от этого пользователя
        $stmt = $db->prepare('SELECT share_id FROM shared_files WHERE file_path = :file_path AND user_email = :user_email');
        $stmt->bindValue(':file_path', $normalizedPath, SQLITE3_TEXT);
        $stmt->bindValue(':user_email', $userEmail, SQLITE3_TEXT);
        $result = $stmt->execute();
        $existing = $result->fetchArray(SQLITE3_ASSOC);

        if ($existing && isset($existing['share_id'])) {
            $upd = $db->prepare('UPDATE shared_files SET is_folder = :is_folder, expires = :expires, expires_option = :expires_option, password_hash = :password_hash, view_only = :view_only, disable_download = :disable_download, disable_copy = :disable_copy, max_views = :max_views WHERE share_id = :share_id');
            $upd->bindValue(':is_folder', $isFolder, SQLITE3_INTEGER);
            if ($expires === null) $upd->bindValue(':expires', null, SQLITE3_NULL);
            else $upd->bindValue(':expires', $expires, SQLITE3_INTEGER);
            $upd->bindValue(':expires_option', $expiresOption, SQLITE3_TEXT);
            $upd->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
            $upd->bindValue(':view_only', $viewOnly, SQLITE3_INTEGER);
            $upd->bindValue(':disable_download', $disableDownload, SQLITE3_INTEGER);
            $upd->bindValue(':disable_copy', $disableCopy, SQLITE3_INTEGER);
            $upd->bindValue(':max_views', $maxViews, SQLITE3_INTEGER);
            $upd->bindValue(':share_id', $existing['share_id'], SQLITE3_TEXT);
            $upd->execute();
            json_out(['key' => $existing['share_id'], 'expires' => $expires, 'expires_option' => $expiresOption, 'view_only' => $viewOnly, 'disable_download' => $disableDownload, 'disable_copy' => $disableCopy, 'max_views' => $maxViews]);
            return;
        }

        // Генерируем уникальный ID
        do {
            $shareId = bin2hex(random_bytes(12));
            $stmt = $db->prepare('SELECT share_id FROM shared_files WHERE share_id = :share_id');
            $stmt->bindValue(':share_id', $shareId, SQLITE3_TEXT);
            $result = $stmt->execute();
            $exists = $result->fetchArray(SQLITE3_ASSOC);
        } while ($exists);

        // Создаем новую запись
        $fileName = basename($fullPath);
        $createdAtExpr = (function_exists('app_db_is_pgsql') && app_db_is_pgsql()) ? 'NOW()' : 'datetime("now", "localtime")';
        $stmt = $db->prepare('
            INSERT INTO shared_files (share_id, file_name, file_path, is_folder, user_email, created_at, expires, expires_option, password_hash, view_only, disable_download, disable_copy, max_views, view_count)
            VALUES (:share_id, :file_name, :file_path, :is_folder, :user_email, ' . $createdAtExpr . ', :expires, :expires_option, :password_hash, :view_only, :disable_download, :disable_copy, :max_views, 0)
        ');

        $stmt->bindValue(':share_id', $shareId, SQLITE3_TEXT);
        $stmt->bindValue(':file_name', $fileName, SQLITE3_TEXT);
        $stmt->bindValue(':file_path', $normalizedPath, SQLITE3_TEXT);
        $stmt->bindValue(':is_folder', $isFolder, SQLITE3_INTEGER);
        $stmt->bindValue(':user_email', $userEmail, SQLITE3_TEXT);
        if ($expires === null) $stmt->bindValue(':expires', null, SQLITE3_NULL);
        else $stmt->bindValue(':expires', $expires, SQLITE3_INTEGER);
        $stmt->bindValue(':expires_option', $expiresOption, SQLITE3_TEXT);
        $stmt->bindValue(':password_hash', $passwordHash, SQLITE3_TEXT);
        $stmt->bindValue(':view_only', $viewOnly, SQLITE3_INTEGER);
        $stmt->bindValue(':disable_download', $disableDownload, SQLITE3_INTEGER);
        $stmt->bindValue(':disable_copy', $disableCopy, SQLITE3_INTEGER);
        $stmt->bindValue(':max_views', $maxViews, SQLITE3_INTEGER);

        if ($stmt->execute()) {
            json_out(['key' => $shareId, 'expires' => $expires, 'expires_option' => $expiresOption, 'view_only' => $viewOnly, 'disable_download' => $disableDownload, 'disable_copy' => $disableCopy, 'max_views' => $maxViews]);
        } else {
            json_out(['error' => 'Failed to create share record'], 500);
        }

    } catch (Exception $e) {
        cloud_safe_error_log("Share file error: " . $e->getMessage());
        json_out(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

if ($action === 'set_quota') {
    $email = get_session_email();
    if (!$email) json_out(['error' => 'not authenticated'], 401);
    $quota_mb = max(1, (int)($_POST['quota_mb'] ?? 0));
    $db = getDB();
    if (!$db) json_out(['error' => 'db unavailable'], 500);
    try {
        ensureCloudUserSettingsSchema($db);
        // Надёжный upsert: сначала INSERT OR IGNORE, потом UPDATE
        $ins = $db->prepare("INSERT OR IGNORE INTO cloud_user_settings (email, quota_mb, session_limit_mb, hide_lang_switcher, updated_at) VALUES (:e, :q, 0, 0, strftime('%s','now'))");
        $ins->bindValue(':e', $email, SQLITE3_TEXT);
        $ins->bindValue(':q', $quota_mb, SQLITE3_INTEGER);
        $ins->execute();
        $upd = $db->prepare("UPDATE cloud_user_settings SET quota_mb = :q, updated_at = strftime('%s','now') WHERE email = :e");
        $upd->bindValue(':e', $email, SQLITE3_TEXT);
        $upd->bindValue(':q', $quota_mb, SQLITE3_INTEGER);
        $upd->execute();
        json_out(['ok' => true, 'quota_mb' => $quota_mb]);
    } catch (Exception $e) {
        json_out(['error' => $e->getMessage()], 500);
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
        $ins = $db->prepare("INSERT OR IGNORE INTO cloud_user_settings (email, quota_mb, session_limit_mb, hide_lang_switcher, updated_at) VALUES (:e, 500, :l, 0, strftime('%s','now'))");
        $ins->bindValue(':e', $email, SQLITE3_TEXT);
        $ins->bindValue(':l', $limit_mb, SQLITE3_INTEGER);
        $ins->execute();
        $upd = $db->prepare("UPDATE cloud_user_settings SET session_limit_mb = :l, updated_at = strftime('%s','now') WHERE email = :e");
        $upd->bindValue(':e', $email, SQLITE3_TEXT);
        $upd->bindValue(':l', $limit_mb, SQLITE3_INTEGER);
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
        $ins = $db->prepare("INSERT OR IGNORE INTO cloud_user_settings (email, quota_mb, session_limit_mb, hide_lang_switcher, updated_at) VALUES (:e, 500, 0, :h, strftime('%s','now'))");
        $ins->bindValue(':e', $email, SQLITE3_TEXT);
        $ins->bindValue(':h', $hide, SQLITE3_INTEGER);
        $ins->execute();
        $upd = $db->prepare("UPDATE cloud_user_settings SET hide_lang_switcher = :h, updated_at = strftime('%s','now') WHERE email = :e");
        $upd->bindValue(':e', $email, SQLITE3_TEXT);
        $upd->bindValue(':h', $hide, SQLITE3_INTEGER);
        $upd->execute();
        json_out(['ok' => true, 'hide_lang_switcher' => $hide]);
    } catch (Exception $e) {
        json_out(['error' => $e->getMessage()], 500);
    }
}

if ($action === 'get_sync_log') {
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated'], 401);
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));
    $user = get_current_user_from_sessions();
    $db = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable'], 500);
    ensureCloudSyncLogsSchema($db);
    cloud_sync_logs_migrate_legacy($db, $email, $user);
    $stmt = $db->prepare("SELECT ts, level, root_key AS root, action, path, message FROM cloud_sync_logs WHERE email = :e ORDER BY ts DESC, id DESC LIMIT :lim");
    $stmt->bindValue(':e', $email, SQLITE3_TEXT);
    $stmt->bindValue(':lim', $limit, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $items = [];
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) $items[] = $row;
    json_out(['ok' => true, 'items' => $items]);
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
        $result['webdav_url'] = cloud_public_origin() . '/webdav/';
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
  $user = get_current_user_from_sessions();;
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  $old_path = safe_path((string)($_POST['old_path'] ?? ''));
  $new_name = trim((string)($_POST['new_name'] ?? ''));
  if ($old_path==='' || $new_name==='') json_out(['error'=>'bad params'], 400);
  remap_clientsync_path($rootKey, $old_path);
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
  $user = get_current_user_from_sessions();;
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  $base = user_root($user, $rootKey);
  $path = safe_path((string)($_POST['path'] ?? ''));
  
  
  if ($path==='') json_out(['error'=>'path required'], 400);
  $full = $base . '/' . $path;
  

  
  if (file_exists($full)) {
    json_out(['error'=>'exists'], 409);
  }
  
  // Создаем директорию, если ее нет
  $dir = dirname($full);
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
  json_out(['ok'=>true]);
}

if ($action === 'view_word') {
    $user = get_current_user_from_sessions();;
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
    $user = get_current_user_from_sessions();;
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
    $user = get_current_user_from_sessions();;
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
    
    // Дополнительно получаем email для отладки
    $db = getDB();
    $email = null;
    if ($db) {
        $session_id = session_id();
        if ($session_id) {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
            $stmt->bindValue(':session_id', $session_id, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
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
  $user = get_current_user_from_sessions();;
  $path = safe_path((string)($_POST['path'] ?? ''));
  $content = (string)($_POST['content'] ?? '');
  $baseRevision = trim((string)($_POST['base_revision'] ?? ''));
  $baseContent = (string)($_POST['base_content'] ?? '');

  if ($path==='') {
    json_out(['error'=>'path required'], 400);
  }

  // Определяем rootKey из пути
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
  
  // Если путь начинается с одного из скрытых каталогов, определяем rootKey автоматически
  foreach ($rootMap as $dirName => $key) {
      if (strpos($path, $dirName . '/') === 0 || $path === $dirName) {
          $rootKey = $key;
          // Убираем имя корневого каталога из пути
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
  
  // Убираем проверку на текстовый файл для пустых файлов
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
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: *');
    
    $user = $_GET['user'] ?? get_current_user_from_sessions();
    $path = safe_path((string)($_GET['path'] ?? ''));
    
    if ($path === '') {
        http_response_code(400);
        exit('Path required');
    }
    
    $base = user_root($user, get_requested_root_key());
    $target = $base . '/' . $path;
    
    if (!file_exists($target)) {
        http_response_code(404);
        exit('File not found');
    }
    
    $mime = mime_content_type($target) ?: 'application/octet-stream';
    
    // Важно: очищаем вывод, чтобы не было JSON-ошибок
    @ob_end_clean();
    
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . rawurlencode(basename($target)) . '"');
    header('Content-Length: ' . filesize($target));
    header('Cache-Control: no-cache');
    header('X-Content-Type-Options: nosniff');
    
    readfile($target);
    exit;
}

if ($action === 'list') {
  $user = get_current_user_from_sessions();;
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
  remap_clientsync_path($rootKey, $path);
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
    if ($rootKey === 'clientsync' && should_hide_clientsync_virtual_entry($it, $isDir)) continue;
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
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  remap_clientsync_path($rootKey, $path);
  $rootFolderPrefix = get_root_folder_name($rootKey);
  
  // If path starts with a different root folder, use that root instead
  $specialRoots = ['archive' => '.archive', 'temp' => '.temp_files', 'documents' => '.documents', 'photos' => '.photos', 'pdf' => '.pdf', 'trash' => '.trash', 'clientsync' => '.clientsync'];
  foreach ($specialRoots as $rk => $prefix) {
      if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
          $rootKey = $rk;
          break;
      }
  }
  
  $base = user_root($user, $rootKey);
  $dir = $base . '/' . $path;

  if (is_dir($dir)) {
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
    // Проверяем, есть ли ID сессии
    $upload_id = $_POST['upload_id'] ?? '';
    $target_path = safe_path((string)($_POST['path'] ?? ''));
    
    // Определяем пользователя и путь
    $user = null;
    $final_target_path = $target_path;
    
    if ($upload_id) {
        // Восстанавливаем сессию загрузки
        $session = get_upload_session($upload_id);
        if ($session && $session['active']) {
            $user = $session['user'];
            $final_target_path = $session['target_path'];
            update_upload_session($upload_id);
        }
    }
    
    // Если нет сессии или она не валидна, создаем новую
    if (!$user) {
        $user = get_current_user_from_sessions();
        
        // Если это инициализация новой загрузки
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
    
    // Если все еще нет пользователя - ошибка
    if (!$user) {
        json_out(['error' => 'Could not determine user'], 400);
    }
    
    $rootKey = get_requested_root_key();
    remap_clientsync_path($rootKey, $final_target_path);
    session_write_close();
    $base = user_root($user, $rootKey);
    $targetDir = $base . '/' . dirname($final_target_path);
    $fileName  = basename($final_target_path);
    $mtimeFromClientRaw = (int)($_POST['mtime'] ?? 0);
    $mtimeFromClientTime = $mtimeFromClientRaw > 20000000000 ? ((float)$mtimeFromClientRaw / 1000.0) : (float)$mtimeFromClientRaw;

    // Создаем целевую директорию если её нет
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

    // === Quota check — read from DB by session email ===
    $root = user_root($user);
    $used = dir_size($root);

    $quotaBytes        = 500 * 1024 * 1024; // default 500MB
    $sessionLimitBytes = 0;
    if ($upload_email) {
        $db_uq = getDB();
        if ($db_uq) {
            try {
                $sq = $db_uq->prepare('SELECT quota_mb, session_limit_mb FROM cloud_user_settings WHERE email = :e');
                $sq->bindValue(':e', $upload_email, SQLITE3_TEXT);
                $rq = $sq->execute()->fetchArray(SQLITE3_ASSOC);
                if ($rq) {
                    $quotaBytes        = max(1, (int)$rq['quota_mb']) * 1024 * 1024;
                    $sessionLimitBytes = max(0, (int)$rq['session_limit_mb']) * 1024 * 1024;
                }
            } catch (Exception $e) {
                cloud_safe_error_log("upload quota check failed: " . $e->getMessage());
            }
        }
    } else {
        $quotaBytes        = getUserQuota($user) * 1024 * 1024;
        $sessionLimitBytes = getSessionLimit($user) * 1024 * 1024;
    }

    $remaining = $quotaBytes - $used;
    if ($sessionLimitBytes > 0 && $sessionLimitBytes < $remaining) {
        $remaining = $sessionLimitBytes;
    }
    
    // Проверяем квоту с учетом уже загруженных частей
    $existing_size = 0;
    $dest = $targetDir . '/' . $fileName;
    $tmpPlainDest = $dest . '.plainpart';
    if (file_exists($dest)) {
        $existing_size = filesize($dest);
    }
    
    if ($file['size'] + $existing_size > $remaining) {
        json_out(['error'=>'quota exceeded'], 413);
    }

    // Поддержка дозагрузки (chunk upload)
    if (isset($_POST['chunk'])) {
        $chunk = (int)$_POST['chunk'];
        $chunks = (int)$_POST['chunks'];
        
        // Открываем или создаем файл
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
            
            // Если это последний чанк, удаляем сессию
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

            // Для upload не инвалидируем кеш дерева:
            // структура будет обновлена инкрементально через patch_file_tree.
            $emailLog = get_session_email();
            if ($chunk == $chunks - 1 && $emailLog) append_sync_log($emailLog, $rootKey, 'upload', $final_target_path, 'INFO', 'Upload completed');
            json_out(['ok'=>true, 'name'=>$fileName, 'chunk'=>$chunk, 'completed'=>($chunk == $chunks - 1)]);
        } else {
            json_out(['error'=>'failed to write chunk'], 500);
        }
    } else {
        // Обычная загрузка
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
        
        // Удаляем сессию после завершения
        if ($upload_id) {
            delete_upload_session($upload_id);
        }

        // Для upload не инвалидируем кеш дерева:
        // структура будет обновлена инкрементально через patch_file_tree.
        $emailLog = get_session_email();
        if ($emailLog) append_sync_log($emailLog, $rootKey, 'upload', $final_target_path, 'INFO', 'Upload completed');
        json_out(['ok'=>true, 'name'=>$fileName]);
    }
}

if ($action === 'delete') {
  $user = get_current_user_from_sessions();;
  $rootKey = get_requested_root_key();
  cloud_group_require_write($rootKey);
  $path = safe_path((string)($_POST['path'] ?? ''));
  if ($path==='') json_out(['error'=>'path required'], 400);
  remap_clientsync_path($rootKey, $path);
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
  remap_clientsync_path($rootKey, $path);
  $base = user_root($user, $rootKey);
  $target = $base . '/' . $path;

  // Release session lock before long-running file transfer
  session_write_close();

  if (!is_file($target)) {
    http_response_code(404);
    echo 'File not found';
    exit;
  }

  // Проверяем, является ли файл временным архивом (начинается с selected_)
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

  // Поддержка докачки/возобновления через HTTP Range.
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
    
    // Удаляем временный архив после успешного скачивания
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
  
  // Используем X-Sendfile если доступно (более эффективно)
  if (function_exists('apache_get_modules') && in_array('mod_xsendfile', apache_get_modules())) {
    header('X-Sendfile: ' . $target);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
    exit;
  }
  
  // Стандартный потоковый метод
  header('Content-Type: ' . $mime);
  header('Content-Length: ' . $size);
  header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
  header('Content-Transfer-Encoding: binary');
  
  // Очищаем буферы
  while (ob_get_level()) {
    ob_end_clean();
  }
  
  // Используем readfile с буферизацией
  readfile($target);
  exit;
}

if ($action === 'open') {
  $user = get_current_user_from_sessions();;
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
  
  // Если файл пустой или mime type application/x-empty, считаем его текстовым
  if ($mime === 'application/x-empty' || filesize($target) === 0) {
    $mime = 'text/plain';
  }
  
  // Определяем тип файла для preview
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
    // Для больших файлов используем потоковое скачивание
    if (filesize($target) > 50 * 1024 * 1024) { // Если файл больше 50MB
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
    
    // Собираем информацию о содержимом папки
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
        
        // Сохраняем первые 100 элементов для preview (опционально)
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
        'items_preview' => $items // опционально
    ];
    
    json_out($properties);
}

if ($action === 'view_pdf') {
  $user = get_current_user_from_sessions();
  $rootKey = get_requested_root_key();
  $base = user_root($user, $rootKey);
  $path = safe_path((string)($_GET['path'] ?? ''));
  $target = $base . '/' . $path;

  if (!file_exists($target)) {
    http_response_code(404); echo 'File not found'; exit;
  }

  // Handle encrypted files in .documents folder
  if (is_documents_root($rootKey)) {
    $encryptedContent = file_get_contents($target);
    $decryptedContent = decrypt_documents_content($encryptedContent, $user);
    $target = tempnam(sys_get_temp_dir(), 'pdf_view_') . '.pdf';
    file_put_contents($target, $decryptedContent);
  }

  $mime = mime_content_type($target);
  if ($mime !== 'application/pdf') {
    http_response_code(400); 
    if (is_documents_root($rootKey) && file_exists($target)) {
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
        $stmt->bindValue(':u', $email, SQLITE3_TEXT);
        $stmt->bindValue(':s', $severity, SQLITE3_TEXT);
        $stmt->bindValue(':t', $title, SQLITE3_TEXT);
        $stmt->bindValue(':m', $message, SQLITE3_TEXT);
        $stmt->bindValue(':c', json_encode(['job_id' => $jobId, 'root' => (string)($payload['root'] ?? ''), 'type' => (string)($payload['type'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $stmt->bindValue(':n', time(), SQLITE3_INTEGER);
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
function cloud_archive_safe_extract_path(string $base, string $name): ?string {
    $name = str_replace('\\', '/', $name);
    $name = ltrim($name, '/');
    if ($name === '' || preg_match('#(^|/)\.\.(/|$)#', $name)) return null;
    $target = $base . '/' . $name;
    $dir = dirname($target);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $realBase = realpath($base);
    $realDir = realpath($dir);
    if (!$realBase || !$realDir || strpos($realDir, $realBase) !== 0) return null;
    return $target;
}
function cloud_archive_command_path(string $command): string {
    $fallbacks = [
        'zip' => ['/usr/bin/zip', '/bin/zip'],
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
    if (in_array($format, ['zip', '7z', 'tar', 'tar.gz', 'tgz'], true)) return $format === 'tgz' ? 'tar.gz' : $format;
    if (str_ends_with($name, '.tar.gz') || str_ends_with($name, '.tgz')) return 'tar.gz';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, ['zip', '7z', 'tar'], true) ? $ext : 'zip';
}
function cloud_archive_format_extension(string $format): string {
    return $format === 'tar.gz' ? 'tar.gz' : $format;
}
function cloud_archive_password_supported(string $format): bool {
    return in_array($format, ['zip', '7z'], true);
}
function cloud_archive_sanitize_name(string $name, string $format): string {
    $ext = cloud_archive_format_extension($format);
    $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', trim($name));
    if ($name === '') $name = 'selected_' . date('Y-m-d_H-i-s') . '.' . $ext;
    $lower = strtolower($name);
    if ($format === 'tar.gz') {
        if (!str_ends_with($lower, '.tar.gz') && !str_ends_with($lower, '.tgz')) $name .= '.tar.gz';
    } elseif (!str_ends_with($lower, '.' . $ext)) {
        $name .= '.' . $ext;
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
    $before = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS));
    $beforeCount = iterator_count($before);
    if (in_array($format, ['zip', '7z'], true)) {
        if (!cloud_archive_command_exists('7z')) throw new RuntimeException('7z command not available');
        $cmd = [cloud_archive_command_path('7z'), 'x', '-y', '-o' . $extractPath];
        if ($password !== '') $cmd[] = '-p' . $password;
        $cmd[] = $archivePath;
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
    if (in_array($format, ['zip', '7z'], true)) {
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
        if (!$temporary && file_exists($archive_path)) {
            $path_info = pathinfo($archive_name);
            $archive_name = $path_info['filename'] . '_' . time() . '.' . $path_info['extension'];
            $archive_path = $base . '/' . $archive_name;
        }
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
    
    // Получаем список элементов для архивации
    $items = $_POST['items'] ?? '';
    $format = cloud_archive_normalize_format((string)($_POST['format'] ?? ''), (string)($_POST['archive_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $archive_name = $_POST['archive_name'] ?? 'selected_' . date('Y-m-d_H-i-s') . '.' . cloud_archive_format_extension($format);
    
    // Декодируем JSON, если items приходит как строка JSON
    if (is_string($items)) {
        $items = json_decode($items, true);
    }
    
    // Проверяем, что items это массив и не пустой
    if (!is_array($items) || empty($items)) {
        json_out(['error' => 'No items selected'], 400);
    }
    if ((string)($_POST['async'] ?? '') === '1') {
        cloud_archive_start_job('create_archive', ['user' => $user, 'email' => get_session_email(), 'root' => $rootKey, 'items' => $items, 'archive_name' => $archive_name, 'format' => $format, 'password' => $password, 'temporary' => (string)($_POST['temporary'] ?? '') === '1', 'type' => 'create_archive'], 'cloud_archive_create_selected_job');
    }
    $archive_name = cloud_archive_sanitize_name((string)$archive_name, $format);
    $archive_path = $base . '/' . $archive_name;
    if (file_exists($archive_path)) {
        $path_info = pathinfo($archive_name);
        $archive_name = $path_info['filename'] . '_' . time() . '.' . $path_info['extension'];
        $archive_path = $base . '/' . $archive_name;
    }
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
    
    // Санитайзим имя архива
    $archive_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $archive_name);
    if (!str_ends_with(strtolower($archive_name), '.zip')) {
        $archive_name .= '.zip';
    }
    
    // Путь для сохранения архива (в корне пользователя)
    $archive_path = $base . '/' . $archive_name;
    
    // Проверяем, не существует ли уже архив с таким именем
    if (file_exists($archive_path)) {
        // Добавляем timestamp к имени
        $path_info = pathinfo($archive_name);
        $archive_name = $path_info['filename'] . '_' . time() . '.' . $path_info['extension'];
        $archive_path = $base . '/' . $archive_name;
    }
    
    // Проверяем, доступен ли класс ZipArchive
    if (!class_exists('ZipArchive')) {
        json_out(['error' => 'Zip extension not available'], 500);
    }
    
    // Создаем ZIP архив
    $zip = new ZipArchive();
    if ($zip->open($archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        json_out(['error' => 'Failed to create archive'], 500);
    }
    
    $added_count = 0;
    $errors = [];
    
    foreach ($items as $item) {
        $item = safe_path($item);
        if (empty($item)) continue;
        
        $full_path = $base . '/' . $item;
        
        if (!file_exists($full_path)) {
            $errors[] = "File not found: $item";
            continue;
        }
        
        if (is_dir($full_path)) {
            // Добавляем папку рекурсивно
            try {
                $directory = new RecursiveDirectoryIterator(
                    $full_path,
                    RecursiveDirectoryIterator::SKIP_DOTS
                );
                
                $iterator = new RecursiveIteratorIterator(
                    $directory,
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $file) {
                    /** @var SplFileInfo $file */
                    $file_path = $file->getPathname();
                    
                    // Получаем относительный путь от корня архивируемой папки
                    $relative_path = $item . '/' . substr($file_path, strlen($full_path) + 1);
                    $relative_path = str_replace('\\', '/', $relative_path);
                    
                    if ($file->isDir()) {
                        // Создаем пустую директорию в архиве
                        if (!empty($relative_path)) {
                            $zip->addEmptyDir($relative_path);
                        }
                    } else {
                        // Добавляем файл
                        if ($zip->addFile($file_path, $relative_path)) {
                            $added_count++;
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Error adding folder $item: " . $e->getMessage();
            }
        } else {
            // Добавляем файл
            if ($zip->addFile($full_path, $item)) {
                $added_count++;
            } else {
                $errors[] = "Failed to add file: $item";
            }
        }
    }
    
    // Закрываем архив
    $zip->close();
    
    // Проверяем результат
    if (file_exists($archive_path) && filesize($archive_path) > 0) {
        $emailArch = get_session_email();
        // Only invalidate cache for main root, not for hidden roots
        if ($emailArch && $rootKey === 'main') invalidate_file_tree($emailArch);
        if ($emailArch) append_sync_log($emailArch, $rootKey, 'create_archive', $archive_name, 'INFO', 'Archive created');
        json_out([
            'success' => true,
            'archive_path' => $archive_name,
            'message' => 'Archive created successfully',
            'items_count' => $added_count,
            'size' => filesize($archive_path),
            'errors' => empty($errors) ? null : $errors
        ]);
    } else {
        // Удаляем пустой архив если он создался
        if (file_exists($archive_path) && filesize($archive_path) == 0) {
            @unlink($archive_path);
        }
        json_out([
            'error' => 'Failed to create archive',
            'details' => empty($errors) ? 'Unknown error' : implode("\n", $errors)
        ], 500);
    }
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

  // Проверяем, что это изображение
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
        // Парсим различные форматы GPS
        if (preg_match('/(\d+)\s+deg\s+(\d+)\s*\'\s+([\d.]+)\s*"/', $lat, $latMatch) &&
            preg_match('/(\d+)\s+deg\s+(\d+)\s*\'\s+([\d.]+)\s*"/', $lon, $lonMatch)) {
          $latDecimal = ($latMatch[1] + $latMatch[2]/60 + $latMatch[3]/3600) * ($latRef === 'S' ? -1 : 1);
          $lonDecimal = ($lonMatch[1] + $lonMatch[2]/60 + $lonMatch[3]/3600) * ($lonRef === 'W' ? -1 : 1);
          $result['gps'] = ['lat' => $latDecimal, 'lon' => $lonDecimal];
        } elseif (is_numeric($lat) && is_numeric($lon)) {
          // Уже в десятичных градусах
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
    
    // Определяем MIME тип
    $mime = mime_content_type($target) ?: 'application/octet-stream';
    
    // Для видео проверяем, что это видео файл (опционально)
    if ($action === 'raw_video' && !str_starts_with($mime, 'video/')) {
        // Если запросили видео, но это не видео - все равно отдаем, но с правильным заголовком
        // Можно раскомментировать проверку если нужно строго проверять
        // http_response_code(415);
        // echo 'Not a video file';
        // exit;
    }
    
    // Получаем размер файла
    $size = filesize($target);
    
    // Поддержка диапазонов для перемотки
    if (isset($_SERVER['HTTP_RANGE'])) {
        // Парсим заголовок Range
        if (preg_match('/bytes=\h*(\d+)-(\d*)/i', $_SERVER['HTTP_RANGE'], $matches)) {
            $start = intval($matches[1]);
            $end = isset($matches[2]) && strlen($matches[2]) ? intval($matches[2]) : $size - 1;
            
            // Валидация
            if ($start >= $size || $start > $end) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
            
            $length = $end - $start + 1;
            
            // Устанавливаем заголовки для частичного контента
            http_response_code(206);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . $length);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Accept-Ranges: bytes');
            header('Cache-Control: no-cache, must-revalidate');
            header('X-Content-Type-Options: nosniff');
            
            // Открываем файл и отдаем только запрошенную часть
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
        // Обычный запрос - отдаем весь файл
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        
        // Для больших файлов используем потоковую передачу
        if ($size > 10 * 1024 * 1024) { // больше 10MB
            $fp = fopen($target, 'rb');
            $chunkSize = 8192;
            
            while (!feof($fp) && connection_status() === CONNECTION_NORMAL) {
                echo fread($fp, $chunkSize);
                flush();
            }
            
            fclose($fp);
        } else {
            // Маленькие файлы отдаем целиком
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
    
    // Пробуем получить длительность через getID3 или другие методы
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
    
    // Проверяем, что это ZIP файл
    $extension = strtolower(pathinfo($full_archive_path, PATHINFO_EXTENSION));
    if ($extension !== 'zip') {
        json_out(['error' => 'Not a ZIP file'], 400);
    }
    
    // Проверяем, доступен ли класс ZipArchive
    if (!class_exists('ZipArchive')) {
        json_out(['error' => 'Zip extension not available'], 500);
    }
    
    // Определяем путь для извлечения
    if ($extract_to === '') {
        // Извлекаем в ту же папку, где находится архив
        $extract_full_path = dirname($full_archive_path);
    } else {
        $extract_full_path = $base . '/' . $extract_to;
        
        // Создаем папку для извлечения если не существует
        if (!is_dir($extract_full_path)) {
            @mkdir($extract_full_path, 0775, true);
        }
    }
    
    // Проверяем, что папка для извлечения существует и доступна для записи
    if (!is_dir($extract_full_path) || !is_writable($extract_full_path)) {
        json_out(['error' => 'Extraction directory not writable'], 500);
    }
    
    // Создаем объект ZipArchive
    $zip = new ZipArchive();
    $result = $zip->open($full_archive_path);
    
    if ($result === TRUE) {
        // Извлекаем архив
        if ($zip->extractTo($extract_full_path)) {
            $zip->close();
            
            // Получаем список извлеченных файлов для информации
            $extracted_files = [];
            $archive = new ZipArchive();
            if ($archive->open($full_archive_path) === TRUE) {
                for ($i = 0; $i < $archive->numFiles; $i++) {
                    $filename = $archive->getNameIndex($i);
                    if ($filename && !str_ends_with($filename, '/')) { // Исключаем папки
                        $extracted_files[] = $filename;
                    }
                }
                $archive->close();
            }

            $emailEx = get_session_email();
            // Only invalidate cache for main root, not for hidden roots
            if ($emailEx && $rootKey === 'main') invalidate_file_tree($emailEx);
            if ($emailEx) append_sync_log($emailEx, $rootKey, 'extract_archive', $archive_path, 'INFO', 'Archive extracted');
            json_out([
                'success' => true,
                'message' => 'Archive extracted successfully',
                'extracted_to' => $extract_to === '' ? dirname($archive_path) : $extract_to,
                'extracted_files_count' => count($extracted_files),
                'extracted_files' => $extracted_files
            ]);
        } else {
            $zip->close();
            json_out(['error' => 'Extraction failed'], 500);
        }
    } else {
        json_out(['error' => 'Cannot open archive file'], 500);
    }
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
    $archive_name = cloud_archive_sanitize_name((string)($_POST['archive_name'] ?? ($folder_name . '_' . date('Y-m-d_H-i-s'))), $format);
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
    
    // Проверяем, доступен ли класс ZipArchive
    if (!class_exists('ZipArchive')) {
        json_out(['error' => 'Zip extension not available'], 500);
    }
    
    // Создаем имя архива
    $folder_name = basename($folder_path);
    $archive_name = $folder_name . '_' . date('Y-m-d_H-i-s') . '.zip';
    $archive_path = dirname($full_path) . '/' . $archive_name;
    
    // Проверяем, не существует ли уже архив с таким именем
    if (file_exists($archive_path)) {
        json_out(['error' => 'Archive already exists'], 409);
    }
    
    // Создаем ZIP архив
    $zip = new ZipArchive();
    if ($zip->open($archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        
        // Создаем рекурсивный итератор для обхода всех файлов и папок
        $directory = new RecursiveDirectoryIterator(
            $full_path,
            RecursiveDirectoryIterator::SKIP_DOTS
        );
        
        $iterator = new RecursiveIteratorIterator(
            $directory,
            RecursiveIteratorIterator::SELF_FIRST // Важно: сначала папки, потом файлы
        );
        
        $added_count = 0;
        
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $item_path = $item->getPathname();
            
            // Получаем относительный путь от корня архивируемой папки
            // Исправлено: используем getPathname() вместо getRealPath() и правильно вычисляем относительный путь
            $relative_path = substr($item_path, strlen($full_path) + 1);
            
            // Нормализуем разделители путей для ZIP (всегда используем /)
            $relative_path = str_replace('\\', '/', $relative_path);
            
            if ($item->isDir()) {
                // Создаем пустую директорию в архиве
                if (!empty($relative_path)) { // Не создаем пустую строку для корня
                    $zip->addEmptyDir($relative_path);
                }
            } else {
                // Добавляем файл с относительным путем
                if ($zip->addFile($item_path, $relative_path)) {
                    $added_count++;
                }
            }
        }
        
        // Закрываем архив
        $zip->close();
        
        // Проверяем результат
        if (file_exists($archive_path) && filesize($archive_path) > 0) {
            $emailArch2 = get_session_email();
            // Only invalidate cache for main root, not for hidden roots
            if ($emailArch2 && $rootKey === 'main') invalidate_file_tree($emailArch2);
            if ($emailArch2) append_sync_log($emailArch2, $rootKey, 'archive_folder', $folder_path, 'INFO', 'Folder archived');
            json_out([
                'success' => true,
                'archive_path' => dirname($folder_path) . '/' . $archive_name,
                'archive_name' => $archive_name,
                'message' => 'Archive created successfully',
                'size' => filesize($archive_path),
                'files_count' => $added_count
            ]);
        } else {
            json_out(['error' => 'Failed to create archive file'], 500);
        }
    } else {
        json_out(['error' => 'Failed to create archive'], 500);
    }
}

if ($action === 'convert_file') {
    $user = get_current_user_from_sessions();;
    $rootKey = get_requested_root_key();
    $base = user_root($user, $rootKey);
    $source_path = safe_path((string)($_POST['source_path'] ?? ''));
    $target_format = (string)($_POST['target_format'] ?? '');
    $output_path = safe_path((string)($_POST['output_path'] ?? ''));
    $delete_original = (bool)($_POST['delete_original'] ?? false);
    
    $delete_original = filter_var($_POST['delete_original'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($source_path === '' || $target_format === '') {
        json_out(['error' => 'source_path and target_format required'], 400);
    }
    
    $source_full = $base . '/' . $source_path;
    
    if (!file_exists($source_full)) {
        json_out(['error' => 'source file not found'], 404);
    }
    
    // Определяем выходной путь
    $source_dir = dirname($source_path);
    $source_filename = pathinfo($source_path, PATHINFO_FILENAME);
    
    if ($output_path === '') {
        $output_path = $source_dir . '/' . $source_filename . '.' . $target_format;
    } else {
        // Если указан путь к папке, добавляем имя файла
        if (strpos($output_path, '.') === false) {
            $output_path = rtrim($output_path, '/') . '/' . $source_filename . '.' . $target_format;
        }
    }
    
    $output_full = $base . '/' . $output_path;
    
    // Проверяем, не существует ли уже целевой файл
    if (file_exists($output_full)) {
        json_out(['error' => 'target file already exists'], 409);
    }
    
    // Создаем директорию для выходного файла если нужно
    $output_dir = dirname($output_full);
    if (!is_dir($output_dir)) {
        @mkdir($output_dir, 0775, true);
    }
    
    try {
    $result = convertFile($source_full, $output_full, $target_format);
    
    if ($result && file_exists($output_full)) {
        // Удаляем оригинальный файл ТОЛЬКО если явно указано
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
    if (!is_file($fontPath)) return false;
    $input = tempnam(sys_get_temp_dir(), 'cloud_md_pdf_');
    if ($input === false) return false;
    file_put_contents($input, $markdown);
    $py = <<<'PY'
import re, sys
from PIL import Image, ImageDraw, ImageFont
src, out, font_path = sys.argv[1], sys.argv[2], sys.argv[3]
markdown = open(src, 'r', encoding='utf-8', errors='replace').read().replace('\r\n', '\n').replace('\r', '\n')
bold_path = font_path.replace('DejaVuSans.ttf', 'DejaVuSans-Bold.ttf')
mono_path = font_path.replace('DejaVuSans.ttf', 'DejaVuSansMono.ttf')
font = ImageFont.truetype(font_path, 28)
bold = ImageFont.truetype(bold_path, 28)
mono = ImageFont.truetype(mono_path, 24)
fonts = {
    'p': font,
    'bold': bold,
    'h1': ImageFont.truetype(bold_path, 46),
    'h2': ImageFont.truetype(bold_path, 38),
    'h3': ImageFont.truetype(bold_path, 32),
    'code': mono,
}
width, height = 1240, 1754
margin = 90
max_w = width - margin * 2
pages = []
img = Image.new('RGB', (width, height), 'white')
draw = ImageDraw.Draw(img)
y = margin
def text_width(s, f):
    return draw.textlength(s, font=f)
def strip_inline(s):
    s = re.sub(r'!\[([^\]]*)\]\([^)]+\)', r'\1', s)
    s = re.sub(r'\[([^\]]+)\]\([^)]+\)', r'\1', s)
    s = re.sub(r'`([^`]*)`', r'\1', s)
    s = re.sub(r'(\*\*|__)(.*?)\1', r'\2', s)
    s = re.sub(r'(\*|_)(.*?)\1', r'\2', s)
    s = re.sub(r'~~(.*?)~~', r'\1', s)
    return s
def wrap_line(line, f, max_width=max_w):
    line = strip_inline(line).strip()
    if not line:
        return ['']
    words = line.split()
    out = []
    cur = ''
    for word in words:
        cand = word if not cur else cur + ' ' + word
        if text_width(cand, f) <= max_width:
            cur = cand
            continue
        if cur:
            out.append(cur)
        cur = word
        while cur and text_width(cur, f) > max_width:
            lo, hi = 1, len(cur)
            while lo < hi:
                mid = (lo + hi + 1) // 2
                if text_width(cur[:mid], f) <= max_width:
                    lo = mid
                else:
                    hi = mid - 1
            out.append(cur[:lo])
            cur = cur[lo:]
    if cur:
        out.append(cur)
    return out
def new_page():
    global img, draw, y
    pages.append(img)
    img = Image.new('RGB', (width, height), 'white')
    draw = ImageDraw.Draw(img)
    y = margin
def ensure(h):
    if y + h > height - margin:
        new_page()
def draw_lines(lines, f, x=margin, fill=(20, 20, 20), line_h=None, before=0, after=12):
    global y
    if line_h is None:
        line_h = int(f.size * 1.45)
    if before:
        ensure(before)
        y += before
    for line in lines:
        ensure(line_h)
        draw.text((x, y), line, fill=fill, font=f)
        y += line_h
    y += after
def parse_table_row(row):
    row = row.strip()
    if row.startswith('|'):
        row = row[1:]
    if row.endswith('|'):
        row = row[:-1]
    return [strip_inline(c.strip()) for c in row.split('|')]
def is_table_sep(line):
    return bool(re.match(r'^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$', line))
def draw_table(rows):
    global y
    if not rows:
        return
    cols = max(len(r) for r in rows)
    col_w = max_w / cols
    pad = 10
    for ri, row in enumerate(rows):
        f = fonts['bold'] if ri == 0 else fonts['p']
        wrapped = [wrap_line(row[ci] if ci < len(row) else '', f, int(col_w - pad * 2)) for ci in range(cols)]
        row_h = max(44, max(len(c) for c in wrapped) * int(f.size * 1.35) + pad * 2)
        ensure(row_h)
        if ri == 0:
            draw.rectangle([margin, y, margin + max_w, y + row_h], fill=(238, 240, 243))
        for ci, cell_lines in enumerate(wrapped):
            x0 = int(margin + ci * col_w)
            x1 = int(margin + (ci + 1) * col_w)
            draw.rectangle([x0, y, x1, y + row_h], outline=(150, 150, 150), width=2)
            ty = y + pad
            for line in cell_lines:
                draw.text((x0 + pad, ty), line, font=f, fill=(20, 20, 20))
                ty += int(f.size * 1.35)
        y += row_h
    y += 18
def flush_paragraph(buf):
    if buf:
        draw_lines(wrap_line(' '.join(buf), fonts['p']), fonts['p'], after=14)
lines = markdown.split('\n')
i = 0
para = []
in_code = False
code_lines = []
while i < len(lines):
    raw = lines[i]
    if raw.strip().startswith('```'):
        flush_paragraph(para)
        para = []
        if in_code:
            for code in code_lines or ['']:
                draw_lines(wrap_line(code, fonts['code'], max_w - 24), fonts['code'], x=margin + 12, fill=(45, 45, 45), line_h=34, after=0)
            y += 14
            code_lines = []
            in_code = False
        else:
            in_code = True
        i += 1
        continue
    if in_code:
        code_lines.append(raw)
        i += 1
        continue
    if raw.strip() == '':
        flush_paragraph(para)
        para = []
        y += 8
        i += 1
        continue
    if re.match(r'^\s{0,3}(-{3,}|\*{3,}|_{3,})\s*$', raw):
        flush_paragraph(para)
        para = []
        ensure(24)
        draw.line([margin, y + 10, margin + max_w, y + 10], fill=(180, 180, 180), width=2)
        y += 28
        i += 1
        continue
    if i + 1 < len(lines) and '|' in raw and is_table_sep(lines[i + 1]):
        flush_paragraph(para)
        para = []
        table = [parse_table_row(raw)]
        i += 2
        while i < len(lines) and '|' in lines[i] and lines[i].strip():
            table.append(parse_table_row(lines[i]))
            i += 1
        draw_table(table)
        continue
    m = re.match(r'^\s{0,3}(#{1,6})\s+(.+?)\s*#*\s*$', raw)
    if m:
        flush_paragraph(para)
        para = []
        level = len(m.group(1))
        f = fonts['h1'] if level == 1 else fonts['h2'] if level == 2 else fonts['h3']
        draw_lines(wrap_line(m.group(2), f), f, before=8 if y > margin else 0, after=16)
        i += 1
        continue
    m = re.match(r'^(\s*)([-*+])\s+(.+)$', raw)
    if m:
        flush_paragraph(para)
        para = []
        indent = min(90, len(m.group(1)) * 12)
        wrapped = wrap_line('• ' + m.group(3), fonts['p'], max_w - indent)
        draw_lines(wrapped, fonts['p'], x=margin + indent, after=2)
        i += 1
        continue
    m = re.match(r'^(\s*)(\d+)[.)]\s+(.+)$', raw)
    if m:
        flush_paragraph(para)
        para = []
        indent = min(90, len(m.group(1)) * 12)
        wrapped = wrap_line(m.group(2) + '. ' + m.group(3), fonts['p'], max_w - indent)
        draw_lines(wrapped, fonts['p'], x=margin + indent, after=2)
        i += 1
        continue
    m = re.match(r'^\s{0,3}>\s?(.*)$', raw)
    if m:
        flush_paragraph(para)
        para = []
        wrapped = wrap_line(m.group(1), fonts['p'], max_w - 30)
        draw.line([margin, y, margin, y + max(40, len(wrapped) * 40)], fill=(160, 160, 160), width=4)
        draw_lines(wrapped, fonts['p'], x=margin + 22, fill=(70, 70, 70), after=10)
        i += 1
        continue
    para.append(raw.strip())
    i += 1
flush_paragraph(para)
if in_code and code_lines:
    for code in code_lines:
        draw_lines(wrap_line(code, fonts['code'], max_w - 24), fonts['code'], x=margin + 12, fill=(45, 45, 45), line_h=34, after=0)
if not pages or y > margin:
    pages.append(img)
pages[0].save(out, 'PDF', resolution=150, save_all=True, append_images=pages[1:])
PY;
    $cmd = 'python3 -c ' . escapeshellarg($py) . ' ' . escapeshellarg($input) . ' ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($fontPath);
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

    // Определяем rootKey из пути
    $rootKey = get_requested_root_key();
    $rootMap = [
        '.archive' => 'archive',
        '.temp_files' => 'temp',
        '.documents' => 'documents',
        '.photos' => 'photos',
        '.pdf' => 'pdf',
        '.trash' => 'trash'
    ];
    
    // Если путь начинается с одного из скрытых каталогов, определяем rootKey автоматически
    foreach ($rootMap as $dirName => $key) {
        if (strpos($path, $dirName . '/') === 0 || $path === $dirName) {
            $rootKey = $key;
            // Убираем имя корневого каталога из пути
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
        // Проверим, может быть pandoc создает файл, но возвращает ошибку
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
    $config = loadAIConfig();
    if (isUserRoot()) {
        json_out($config);
    } else {
        json_out([
            'api_endpoint' => $config['api_endpoint'],
            'default_model' => $config['default_model'],
            'models' => $config['models'] ?? [],
            'has_key' => !empty($config['api_key'])
        ]);
    }
}

if ($action === 'save_ai_config') {
    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(['error' => 'Method not allowed'], 405);
    }
    
    // Получаем данные из POST или из тела запроса
    $input = $_POST;
    
    // Если данные пришли как JSON в теле запроса
    if (empty($input) && $jsonInput = file_get_contents('php://input')) {
        $input = json_decode($jsonInput, true);
    }
    
    if (!is_array($input)) {
        json_out(['error' => 'Invalid input'], 400);
    }
    
    // Сохраняем конфигурацию
    $result = saveAIConfig($input);
    
    if (isset($result['error'])) {
        json_out(['error' => $result['error']], $result['code'] ?? 400);
    }
    
    json_out($result);
}

if ($action === 'debug_session') {
    $db = getDB();
    $session_id  = session_id();
    $cookie_sid  = $_COOKIE['PHPSESSID'] ?? null;
    $email       = get_session_email();

    $db_row = null;
    if ($db && $cookie_sid) {
        $stmt = $db->prepare('SELECT session_id, email FROM active_sessions WHERE session_id = :sid');
        $stmt->bindValue(':sid', $cookie_sid, SQLITE3_TEXT);
        $db_row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
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
    $email = get_session_email();
    if (!$email) json_out(['ok' => false, 'error' => 'not authenticated']);

    $user = get_current_user_from_sessions();
    $db   = getDB();
    if (!$db) json_out(['ok' => false, 'error' => 'db unavailable']);

    try {
        ensure_file_tree_table($db);
        $stmt = $db->prepare("SELECT tree_json, tree_valid FROM cloud_folder_state WHERE email = :e");
        $stmt->bindValue(':e', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($row && $row['tree_valid'] == 1 && !empty($row['tree_json'])) {
            $items = json_decode($row['tree_json'], true) ?? [];
            $items = cloud_dedupe_tree_items($items);
            json_out(['ok' => true, 'items' => $items, 'from_cache' => true]);
        } else {
            // Scan disk and cache
            rebuild_file_tree($email, $user);
            // Re-read
            $stmt2 = $db->prepare("SELECT tree_json FROM cloud_folder_state WHERE email = :e");
            $stmt2->bindValue(':e', $email, SQLITE3_TEXT);
            $row2 = $stmt2->execute()->fetchArray(SQLITE3_ASSOC);
            $items = $row2 ? (json_decode($row2['tree_json'], true) ?? []) : [];
            $items = cloud_dedupe_tree_items($items);
            json_out(['ok' => true, 'items' => $items, 'from_cache' => false]);
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
        $stmt->bindValue(':e', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

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
            $db->exec("ALTER TABLE cloud_ai_assistant_chats ADD COLUMN IF NOT EXISTS meta_json TEXT NOT NULL DEFAULT ''");
        } catch (Throwable $e) {
        }
    }
}

if ($action === 'ai_chat_append') {
    $email = get_session_email();
    if (!$email) json_out(['success' => false, 'error' => 'not authenticated'], 401);
    $db = getDB();
    if (!$db) json_out(['success' => false, 'error' => 'db unavailable'], 500);
    cloud_ai_ensure_chat_tables($db);
    $stmt = $db->prepare("INSERT INTO cloud_ai_assistant_chats
        (email, question, answer, model, file_name, time_seconds, tokens, speed, meta_json, created_at)
        VALUES (:email, :question, :answer, :model, :file_name, :time_seconds, :tokens, :speed, :meta_json, extract(epoch from now())::bigint)");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':question', (string)($_POST['question'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':answer', (string)($_POST['answer'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':model', (string)($_POST['model'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':file_name', (string)($_POST['file_name'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':time_seconds', is_numeric($_POST['time_seconds'] ?? null) ? (float)$_POST['time_seconds'] : null, SQLITE3_FLOAT);
    $stmt->bindValue(':tokens', is_numeric($_POST['tokens'] ?? null) ? (int)$_POST['tokens'] : null, SQLITE3_INTEGER);
    $stmt->bindValue(':speed', is_numeric($_POST['speed'] ?? null) ? (float)$_POST['speed'] : null, SQLITE3_FLOAT);
    $stmt->bindValue(':meta_json', (string)($_POST['meta_json'] ?? ''), SQLITE3_TEXT);
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
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':limit', $limit + 1, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $items = [];
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
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
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
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
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
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
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':settings_json', $json, SQLITE3_TEXT);
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
        
        // ВАЖНО: Проверяем, есть ли запись для пользователя
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM cloud_folder_state WHERE email = :e");
        $stmt->bindValue(':e', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row['cnt'] == 0) {
            // Создаем запись для пользователя, если её нет
            $insert = $db->prepare("
                INSERT INTO cloud_folder_state (email, folder_json, updated_at)
                VALUES (:e, '[]', strftime('%s','now'))
            ");
            $insert->bindValue(':e', $email, SQLITE3_TEXT);
            $insert->execute();
            json_out(['paths' => []]);
            exit;
        }
        
        $stmt = $db->prepare('SELECT folder_json FROM cloud_folder_state WHERE email = :e');
        $stmt->bindValue(':e', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $paths = $row ? (json_decode($row['folder_json'], true) ?? []) : [];
        json_out(['paths' => $paths]);
    } catch (Exception $e) {
        cloud_safe_error_log("get_folder_state failed: " . $e->getMessage());
        json_out(['paths' => []]);
    }
}

if ($action === 'set_folder_state') {
    $email = get_session_email();
    cloud_safe_error_log("[cloud] set_folder_state: session_id=" . session_id() . " cookie=" . ($_COOKIE['PHPSESSID'] ?? 'none') . " email=" . ($email ?? 'null'));
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
        // Сначала убеждаемся, что запись существует
        $check = $db->prepare("SELECT COUNT(*) as cnt FROM cloud_folder_state WHERE email = :e");
        $check->bindValue(':e', $email, SQLITE3_TEXT);
        $result = $check->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row['cnt'] == 0) {
            // Создаем запись, если её нет
            $insert = $db->prepare("
                INSERT INTO cloud_folder_state (email, folder_json, updated_at)
                VALUES (:e, :j, strftime('%s','now'))
            ");
            $insert->bindValue(':e', $email, SQLITE3_TEXT);
            $insert->bindValue(':j', $json, SQLITE3_TEXT);
            $insert->execute();
        } else {
            // Обновляем существующую
            $update = $db->prepare("
                UPDATE cloud_folder_state 
                SET folder_json = :j, updated_at = strftime('%s','now')
                WHERE email = :e
            ");
            $update->bindValue(':e', $email, SQLITE3_TEXT);
            $update->bindValue(':j', $json, SQLITE3_TEXT);
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
        $stmt->bindValue(':e', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        // If cache is missing/invalid, build it once from disk,
        // then apply patch ops (add/remove/rename) without full UI refresh.
        if (!$row || $row['tree_valid'] != 1 || empty($row['tree_json'])) {
            $user = get_current_user_from_sessions();
            if ($user) {
                rebuild_file_tree($email, $user);
                $stmtReload = $db->prepare("SELECT tree_json, tree_valid FROM cloud_folder_state WHERE email = :e");
                $stmtReload->bindValue(':e', $email, SQLITE3_TEXT);
                $row = $stmtReload->execute()->fetchArray(SQLITE3_ASSOC);
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

        $check = $db->prepare("SELECT COUNT(*) as cnt FROM cloud_folder_state WHERE email = :e");
        $check->bindValue(':e', $email, SQLITE3_TEXT);
        $cnt = $check->execute()->fetchArray(SQLITE3_ASSOC)['cnt'] ?? 0;
        if ($cnt == 0) {
            $s = $db->prepare("INSERT INTO cloud_folder_state (email, folder_json, tree_json, tree_valid, updated_at) VALUES (:e, '[]', :j, 1, strftime('%s','now'))");
        } else {
            $s = $db->prepare("UPDATE cloud_folder_state SET tree_json = :j, tree_valid = 1, updated_at = strftime('%s','now') WHERE email = :e");
        }
        $s->bindValue(':e', $email, SQLITE3_TEXT);
        $s->bindValue(':j', $json, SQLITE3_TEXT);
        $s->execute();

        json_out(['ok' => true, 'count' => count($items)]);
    } catch (Exception $e) {
        cloud_safe_error_log("patch_file_tree failed: " . $e->getMessage());
        json_out(['ok' => false, 'error' => $e->getMessage()]);
    }
}
json_out(['error'=>'unknown action'], 400);
