<?php
$fileId = $_GET['sharedFile'] ?? '';
$fileData = null;
$shareBlockedReason = '';
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
if ($fileId) {
    $db = getDB();
    if (!$db) {
        error_log('Database connection failed in share_file_utils');
        http_response_code(500);
        die('<div style="padding:40px;text-align:center;font-family:system-ui;color:#666;">Database error</div>');
    }
    try {
        $db->exec('DELETE FROM shared_files WHERE expires IS NOT NULL AND expires < ' . time());
        $stmt = $db->prepare('SELECT * FROM shared_files WHERE share_id = :share_id');
        $stmt->bindValue(':share_id', $fileId, PDO::PARAM_STR);
        $result = $stmt->execute();
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $resolvedFilePath = resolveSharedFilePath((string) $row['file_path']);
            $isFolderRow = isset($row['is_folder']) && (int) $row['is_folder'] === 1;
            $fileData = [
                'url' => $resolvedFilePath,
                'name' => $row['file_name'],
                'user' => $row['user_email'],
                'owner_user' => email_to_cloud_username((string) $row['user_email']),
                'created' => $row['created_at'],
                'expires' => $row['expires'],
                'is_folder' => $isFolderRow,
                'password_hash' => (string)($row['password_hash'] ?? ''),
                'view_only' => (int)($row['view_only'] ?? 0),
                'disable_download' => (int)($row['disable_download'] ?? 0),
                'disable_copy' => (int)($row['disable_copy'] ?? 0),
                'max_views' => (int)($row['max_views'] ?? 0),
                'view_count' => (int)($row['view_count'] ?? 0),
            ];
            if ($row['expires'] && $row['expires'] < time()) {
                $fileData = null;
                $shareBlockedReason = 'expired';
                error_log("Shared file expired: $fileId");
            }
            if ($fileData && !file_exists($resolvedFilePath)) {
                $fileData = null;
                $shareBlockedReason = 'missing';
                error_log('Shared file not found on disk: ' . $row['file_path']);
            }
            if ($fileData && (int)($row['max_views'] ?? 0) > 0 && (int)($row['view_count'] ?? 0) >= (int)($row['max_views'] ?? 0)) {
                $fileData = null;
                $shareBlockedReason = 'views';
            }
        } else {
            $shareBlockedReason = 'missing';
            error_log("Shared file not found in database: $fileId");
        }
    } catch (Exception $e) {
        error_log('Error getting shared file from database: ' . $e->getMessage());
        $fileData = null;
    }
}
if (!$fileData || !isset($fileData['url'])) {
    http_response_code(404);
    $themeByBrowser = 'light';
    if (isset($_SERVER['HTTP_SEC_CH_PREFERS_COLOR_SCHEME'])) {
        $themeByBrowser = strtolower((string)$_SERVER['HTTP_SEC_CH_PREFERS_COLOR_SCHEME']) === 'dark' ? 'dark' : 'light';
    }
    $bg = $themeByBrowser === 'dark' ? '#060b16' : '#e9f0ff';
    $fg = $themeByBrowser === 'dark' ? '#d7e4ff' : '#12203d';
    $panel = $themeByBrowser === 'dark' ? 'rgba(12,20,36,0.72)' : 'rgba(255,255,255,0.72)';
    $border = $themeByBrowser === 'dark' ? 'rgba(130,152,188,0.28)' : 'rgba(91,116,166,0.25)';
    $title = htmlspecialchars($translations['file_not_found'] ?? 'Shared link unavailable');
    $sub = $shareBlockedReason === 'views' ? 'The view limit for this link has been reached.' : ($shareBlockedReason === 'expired' ? 'This shared link has expired.' : 'The file is not available anymore.');
    die('<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . '</title><style>:root{color-scheme:light dark}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui;background:radial-gradient(1200px 600px at 12% 8%,rgba(80,127,255,.16),transparent),radial-gradient(900px 600px at 100% 90%,rgba(50,185,255,.18),transparent),' . $bg . ';color:' . $fg . ';min-height:100vh;display:flex;align-items:center;justify-content:center;padding:22px}.wrap{max-width:920px;width:100%;display:grid;gap:14px}.box{position:relative;overflow:hidden;padding:28px;border-radius:22px;background:' . $panel . ';backdrop-filter:blur(16px) saturate(1.18);border:1px solid ' . $border . ';box-shadow:0 30px 80px rgba(0,0,0,.32)}.shine{position:absolute;inset:-1px;background:linear-gradient(120deg,rgba(255,255,255,.22),rgba(255,255,255,0) 38%);pointer-events:none}.head{display:flex;gap:14px;align-items:center}.icon{width:62px;height:62px;border-radius:18px;background:linear-gradient(140deg,rgba(79,124,255,.3),rgba(51,177,255,.25));display:flex;align-items:center;justify-content:center;border:1px solid rgba(153,185,255,.45)}h1{margin:0 0 4px;font-size:30px;line-height:1.1}.muted{opacity:.82;font-size:15px;line-height:1.55}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:16px}.card{padding:12px;border-radius:14px;background:rgba(255,255,255,.06);border:1px solid rgba(153,185,255,.22)}.card b{display:block;font-size:12px;opacity:.78;margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em}.btns{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}.cta{display:inline-flex;align-items:center;gap:8px;padding:11px 16px;border-radius:11px;background:linear-gradient(135deg,#4f7cff,#33b1ff);color:#fff;text-decoration:none;font-weight:700}.ghost{display:inline-flex;align-items:center;gap:8px;padding:11px 16px;border-radius:11px;border:1px solid ' . $border . ';background:rgba(255,255,255,.04);color:' . $fg . ';text-decoration:none;font-weight:600}.foot{font-size:12px;opacity:.62;margin-top:8px}</style></head><body><div class="wrap"><div class="box"><div class="shine"></div><div class="head"><div class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg></div><div><h1>' . $title . '</h1><div class="muted">' . htmlspecialchars($sub) . '</div></div></div><div class="grid"><div class="card"><b>Status</b>Link is currently unavailable</div><div class="card"><b>Possible reason</b>' . htmlspecialchars($sub) . '</div><div class="card"><b>Tip</b>Ask owner to create a new link</div></div><div class="btns"><a class="cta" href="/"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18"/><path d="m14 5 7 7-7 7"/></svg>See other service offers</a><a class="ghost" href="javascript:location.reload()"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15.55-6.36L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15.55 6.36L3 16"/></svg>Retry</a></div><div class="foot">AppDashboard share service</div></div></div></body></html>');
}
$shareNeedPassword = $fileData['password_hash'] !== '' && empty($_SESSION['shared_ok_' . $fileId]);
if ($shareNeedPassword && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_password'])) {
    if (password_verify((string)$_POST['share_password'], $fileData['password_hash'])) {
        $_SESSION['shared_ok_' . $fileId] = 1;
        $shareNeedPassword = false;
    }
}
if (!$shareNeedPassword && !isset($_SESSION['shared_viewed_' . $fileId])) {
    $db = getDB();
    if ($db) {
        try {
            $db->exec("UPDATE shared_files SET view_count = view_count + 1 WHERE share_id = '" . AppDatabase::escapeString((string)$fileId) . "'");
            $_SESSION['shared_viewed_' . $fileId] = 1;
            if (($fileData['max_views'] ?? 0) > 0) {
                $fileData['view_count'] = (int)($fileData['view_count'] ?? 0) + 1;
            }
        } catch (Exception $e) {
        }
    }
}
$isFolder = !empty($fileData['is_folder']);
$folderFiles = [];
$currentSubPath = isset($_GET['subpath']) ? (string) $_GET['subpath'] : '';
$currentSubPath = str_replace(['..', '\\'], ['', '/'], $currentSubPath);
if ($isFolder) {
    $folderPath = $fileData['url'];
    if ($currentSubPath !== '') {
        $folderPath = $folderPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentSubPath);
    }
    if (is_dir($folderPath)) {
        $items = scandir($folderPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $folderPath . DIRECTORY_SEPARATOR . $item;
            $folderFiles[] = [
                'name' => $item,
                'path' => $itemPath,
                'is_dir' => is_dir($itemPath),
                'size' => is_file($itemPath) ? filesize($itemPath) : 0,
                'mtime' => filemtime($itemPath),
            ];
        }
        usort($folderFiles, function ($a, $b) {
            if ($a['is_dir'] != $b['is_dir']) {
                return $b['is_dir'] - $a['is_dir'];
            }
            return strcasecmp($a['name'], $b['name']);
        });
    }
}
$selectedFile = null;
if ($isFolder && isset($_GET['file'])) {
    $selectedFileName = basename((string) $_GET['file']);
    $searchPath = $fileData['url'];
    if ($currentSubPath !== '') {
        $searchPath = $searchPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentSubPath);
    }
    $selectedFilePath = $searchPath . DIRECTORY_SEPARATOR . $selectedFileName;
    if (file_exists($selectedFilePath) && is_file($selectedFilePath)) {
        $selectedFile = [
            'name' => $selectedFileName,
            'path' => $selectedFilePath,
            'is_dir' => false,
            'size' => filesize($selectedFilePath),
            'mtime' => filemtime($selectedFilePath),
        ];
    }
}
$fileUrl = $fileData['url'];
$fileName = $fileData['name'] ?? basename($fileUrl);
$sharedOwnerUser = $fileData['owner_user'] ?? email_to_cloud_username((string) ($fileData['user'] ?? ''));
if ($isFolder && $selectedFile) {
    $fileUrl = $selectedFile['path'];
    $fileName = $selectedFile['name'];
}
