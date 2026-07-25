<?php


$requestUri=$_SERVER['REQUEST_URI'];
$path=parse_url($requestUri,PHP_URL_PATH);

if ($path === '/login/oauth/start' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    try {
        $provider = (string)($_GET['provider'] ?? '');
        $mode = (string)($_GET['mode'] ?? 'login');
        if ($mode === 'link' && !isset($_SESSION['user']['email'])) {
            header('Location: /login');
            exit;
        }
        header('Location: ' . oauthStartUrl($provider, $mode));
        exit;
    } catch (Throwable $e) {
        error_log('OAuth start error: ' . $e->getMessage());
        $failMode = (string)($_GET['mode'] ?? 'login');
        $target = $failMode === 'link'
            ? '/main?profile=1&oauth_error=' . urlencode($e->getMessage())
            : '/login?oauth_error=' . urlencode($e->getMessage());
        header('Location: ' . $target);
        exit;
    }
}

if (in_array($path, ['/.well-known/openid-configuration', '/.well-known/jwks.json', '/authorize', '/token', '/userinfo', '/oauth-bridge', '/omniroute-bridge', '/login/oauth/authorize', '/login/oauth/access_token', '/api/v1/user', '/api/v1/user/emails', '/plane-oauth/login/oauth/authorize', '/plane-oauth/login/oauth/access_token', '/plane-oauth/api/v1/user', '/plane-oauth/api/v1/user/emails'], true)) {
    oauth_dispatch($path);
    exit;
}

if ($path === '/client-auth') {
    $requestId = trim((string)($_GET['request_id'] ?? ''));
    $browserToken = trim((string)($_GET['browser_token'] ?? ''));
    if ($requestId === '' || $browserToken === '') {
        http_response_code(400);
        echo '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;padding:40px;"><h1>Invalid request</h1><p>Missing browser authorization parameters.</p></body></html>';
        exit;
    }
    if (!clientAuthSessionOk()) {
        setPostLoginRedirect($requestUri);
        header('Location: /login');
        exit;
    }
    $db = getDB();
    if (!$db) {
        http_response_code(500);
        echo '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;padding:40px;"><h1>Server error</h1><p>Database unavailable.</p></body></html>';
        exit;
    }
    ensureClientBrowserAuthSchema($db);
    purgeClientBrowserAuthRequests($db);
    $row = getClientBrowserAuthRequest($db, $requestId);
    $validRequest = $row && clientBrowserAuthMatches($browserToken, (string)($row['browser_token_hash'] ?? '')) && (int)($row['expires_at'] ?? 0) >= time();
    $deviceName = $validRequest ? my_htmlspecialchars((string)($row['device_name'] ?: 'Desktop app')) : 'Desktop app';
    $platformName = $validRequest ? my_htmlspecialchars((string)($row['platform'] ?: 'desktop')) : 'desktop';
    $userEmail = my_htmlspecialchars((string)($_SESSION['user']['email'] ?? ''));
    $primaryColor = my_htmlspecialchars((string)($config['color'] ?? '#2563eb'));
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Desktop Authorization</title>
        <link rel="icon" type="image/png" href="/icon.png">
        <link rel="shortcut icon" type="image/png" href="/icon.png">
        <style>
            :root{
                color-scheme: light dark;
                --accent: <?php echo $primaryColor; ?>;
                --bg: light-dark(#f3f5f7,#0f1115);
                --card: light-dark(rgba(255,255,255,.82),rgba(24,26,32,.82));
                --line: light-dark(rgba(20,24,31,.08),rgba(255,255,255,.08));
                --text: light-dark(#1f2329,#f3f5f7);
                --muted: light-dark(#5a6472,#a8b0bc);
                --shadow: 0 24px 64px rgba(0,0,0,.18);
            }
            *{box-sizing:border-box}
            body{
                margin:0;
                min-height:100vh;
                font:15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
                background:
                    radial-gradient(circle at top, light-dark(rgba(255,255,255,.94), rgba(255,255,255,.05)), transparent 40%),
                    linear-gradient(180deg, light-dark(#eef1f4,#0b0d10), var(--bg));
                color:var(--text);
                display:flex;
                align-items:center;
                justify-content:center;
                padding:24px;
            }
            .card{
                width:min(620px,100%);
                background:var(--card);
                border:1px solid var(--line);
                border-radius:24px;
                padding:32px;
                box-shadow:var(--shadow);
                backdrop-filter:blur(18px);
            }
            .pill{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:light-dark(rgba(255,255,255,.55),rgba(255,255,255,.05));color:var(--muted);font-size:12px;letter-spacing:.1em;text-transform:uppercase}
            h1{font-size:34px;line-height:1.05;margin:14px 0 10px}
            p{color:var(--muted);font-size:15px;line-height:1.6;margin:0}
            .meta{margin:22px 0;padding:18px;border-radius:18px;background:light-dark(rgba(255,255,255,.55),rgba(255,255,255,.04));border:1px solid var(--line)}
            .meta-row{display:flex;justify-content:space-between;gap:16px;padding:8px 0}
            .meta-row span:first-child{color:var(--muted)}
            button{
                border:1px solid var(--line);
                border-radius:14px;
                padding:14px 18px;
                font:inherit;
                font-weight:700;
                cursor:pointer;
                background:light-dark(#23272f,#e5e9ef);
                color:light-dark(#fff,#111318);
                width:100%
            }
            button[disabled]{opacity:.6;cursor:wait}
            .secondary{margin-top:12px;background:transparent;color:var(--text)}
            .status{margin-top:16px;min-height:24px;color:var(--muted)}
            .ok{color:#2e7d32}.err{color:#c62828}
            .toast{
                position:fixed;right:18px;bottom:18px;min-width:240px;max-width:360px;padding:14px 16px;
                border-radius:14px;background:light-dark(rgba(35,39,47,.96), rgba(236,239,244,.96));color:light-dark(#fff,#111318);
                box-shadow:var(--shadow);transform:translateY(18px);opacity:0;pointer-events:none;transition:.22s ease;
            }
            .toast.show{transform:none;opacity:1}
        </style>
    </head>
    <body>
        <div class="card">
            <div class="pill">ServerKherson Desktop</div>
            <?php if (!$validRequest): ?>
                <h1>Authorization request is invalid or expired</h1>
                <p>Return to the desktop app and start authorization again.</p>
            <?php else: ?>
                <h1>Authorize desktop sync</h1>
                <p>Confirm token generation and cloud synchronization for this device. After confirmation, the browser will return control to the running application.</p>
                <div class="meta">
                    <div class="meta-row"><span>Account</span><strong><?php echo $userEmail; ?></strong></div>
                    <div class="meta-row"><span>Device</span><strong><?php echo $deviceName; ?></strong></div>
                    <div class="meta-row"><span>Platform</span><strong><?php echo $platformName; ?></strong></div>
                </div>
                <button id="approveBtn" type="button">Confirm authorization</button>
                <button class="secondary" type="button" onclick="window.close()">Close</button>
                <div id="status" class="status"></div>
                <script>
                function showToast(text){
                    const toast=document.getElementById('toast');
                    toast.textContent=text;
                    toast.classList.add('show');
                    setTimeout(()=>toast.classList.remove('show'),3200);
                    if('Notification' in window && Notification.permission==='granted'){
                        new Notification('Cloud Sync',{body:text});
                    }
                }
                if('Notification' in window && Notification.permission==='default'){
                    Notification.requestPermission().catch(()=>{});
                }
                const statusEl = document.getElementById('status');
                const approveBtn = document.getElementById('approveBtn');
                approveBtn.addEventListener('click', async () => {
                    approveBtn.disabled = true;
                    statusEl.textContent = 'Approving and sending token to the application...';
                    showToast(statusEl.textContent);
                    try {
                        const res = await fetch('/main?api=client_auth&action=approve_browser_login', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                request_id: <?php echo json_encode($requestId, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
                                browser_token: <?php echo json_encode($browserToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
                            })
                        });
                        const data = await res.json();
                        if (!data.ok) {
                            throw new Error(data.error || 'authorization_failed');
                        }
                        statusEl.textContent = 'Authorization confirmed. Returning to the application...';
                        statusEl.className = 'status ok';
                        showToast(statusEl.textContent);
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        }
                    } catch (err) {
                        statusEl.textContent = 'Failed to confirm authorization: ' + err.message;
                        statusEl.className = 'status err';
                        showToast(statusEl.textContent);
                        approveBtn.disabled = false;
                    }
                });
                </script>
            <?php endif; ?>
        </div>
        <div id="toast" class="toast"></div>
    </body>
    </html>
    <?php
    exit;
}

if($path==='/privacy'||$path==='/terms'){
    $themeParam=$_GET['theme']??$config['theme'];
    $langParam=$_GET['lang']??$config['language'];
    $colorParam=$_GET['color']??$config['color'];
    if($path==='/privacy'){
        include 'privacy.html';
        exit;
    }elseif($path==='/terms'){
        include 'terms.html';
        exit;
    }
}

if($path==='/database'){
    if($needsAuth){
        header('Location: /login');
        exit;
    }
    $sessionIsAdminDb = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == 2;
    if(!$sessionIsAdminDb){
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403 Forbidden</title></head><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>403 Forbidden</h1></body></html>';
        exit;
    }
    
    // Pass URL parameters to db_admin.php via global variables
    $_GET['theme'] = $_GET['theme'] ?? $config['theme'];
    $_GET['lang'] = $_GET['lang'] ?? $language;
    $_GET['color'] = $_GET['color'] ?? $config['color'];
    
    // Include db_admin.php directly (without redirect)
    include 'db_admin.php';
    exit;
}

if($path==='/verify'){
    $token = $_GET['token'] ?? '';
    if($token){
        $pendingUsers = loadPendingUsers();
        $tokens = loadVerificationTokens();
        $found = false;
        foreach($tokens as $email => $tokenData){
            if($tokenData['token'] === $token && $tokenData['expires'] > time()){
                $pendingRow = getPendingUser($email);
                if($pendingRow){
                    // Move from pending_users to users with verified=1
                    $userData = [
                        'name'       => $pendingRow['name'],
                        'password'   => $pendingRow['password'],
                        'photo'      => $pendingRow['photo'] ?? 'default_avatar.png',
                        'role'       => (int)($pendingRow['role'] ?? 0),
                        'verified'   => true,
                        'language'   => $pendingRow['language'] ?? 'en',
                        'theme'      => $pendingRow['theme'] ?? 'light',
                        'color'      => $pendingRow['color'] ?? '#007bff',
                        'created_at' => $pendingRow['created_at'] ?? date('Y-m-d H:i:s'),
                    ];
                    upsertUser($email, $userData);
                    deletePendingUser($email);
                    // Remove the token
                    $dbTok = getDB();
                    $stTok = $dbTok->prepare('DELETE FROM verification_tokens WHERE email=:e');
                    $stTok->bindValue(':e', $email); $stTok->execute();
                    $found = true;
                    
                    $freshUser = getUserByEmail($email);
                    $_SESSION['user'] = $freshUser;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['auth_time'] = time();
                    cleanupExpiredUserSessions($email);
                    addUserToActiveSessions($email, session_id(), $_SESSION['user']);
                    
                    notifyTelegramEmailVerified($email, $userData['name'] ?? '');
                    
                    header('Location: ' . takePostLoginRedirect('/main'));
                    exit;
                }
                break;
            }
        }
        if(!$found){
            $authError = $translations['verification_failed'];
            header('Location: /login?error=verification_failed');
            exit;
        }
    }
    header('Location: /login');
    exit;
}

if(isset($_GET['fm_serve']) && isset($_SESSION['user'])){
    // Serve a file for preview (images/video/audio) — admin only
    $sessionIsAdminServe = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == 2;
    if(!$sessionIsAdminServe){ http_response_code(403); exit; }
    $requestedPath = $_GET['path'] ?? '';
    $baseDir = realpath(APP_ROOT);
    $cleanPath = ltrim(str_replace(['../', '..\\', '..'], '', $requestedPath), '/\\');
    $candidate = $baseDir . DIRECTORY_SEPARATOR . $cleanPath;
    $parentDir = realpath(dirname($candidate));
    $targetFile = $parentDir ? ($parentDir . DIRECTORY_SEPARATOR . basename($candidate)) : false;
    if(!$targetFile || !$parentDir || strpos($parentDir, $baseDir) !== 0 || !is_file($targetFile)){
        http_response_code(404); exit;
    }
    $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $mimeMap = [
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif',
        'webp'=>'image/webp','bmp'=>'image/bmp','svg'=>'image/svg+xml','ico'=>'image/x-icon',
        'mp4'=>'video/mp4','webm'=>'video/webm','ogg'=>'video/ogg','mov'=>'video/quicktime',
        'mp3'=>'audio/mpeg','wav'=>'audio/wav','flac'=>'audio/flac','aac'=>'audio/aac','m4a'=>'audio/mp4',
    ];
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
    while(ob_get_level()>0)ob_end_clean();
    header('Content-Type: '.$mime);
    header('Content-Length: '.filesize($targetFile));
    if(isset($_GET['download'])){ header('Content-Disposition: attachment; filename="'.basename($targetFile).'"'); }
    else { header('Content-Disposition: inline'); }
    header('Cache-Control: no-store');
    readfile($targetFile);
    exit;
}

if(isset($_GET['sharedFile'])){
    $themeParam = $_GET['theme'] ?? $config['theme'];
    $langParam = $_GET['lang'] ?? $config['language'];
    $colorParam = $_GET['color'] ?? $config['color'];
    
    $_GET['theme'] = $themeParam;
    $_GET['lang'] = $langParam;
    $_GET['color'] = $colorParam;
    
    include 'file_viewer.php';
    exit;
}

if($path==='/one-time-pin'){
    $needsAuth=false;
    $showLogin=false;
    $showOtpPage=true;
    $pageTitle=$translations['otp_page_title']??'One-Time PIN Login';
}

if($path==='/login'||$path==='/register'||$path==='/login/oauth/callback'||$path==='/login/oauth/start'||(isset($_GET['code'])&&strpos($path,'/login')!==false)){
    $sessionOkEarly=isset($_SESSION['user']['email'],$_SESSION['auth_time'])&&getUserFromActiveSessions(session_id())!==null;
    if($sessionOkEarly&&!isset($_GET['code'])){
        $rq=[];if($themeFromUrl)$rq['theme']=$themeFromUrl;if($langFromUrl)$rq['lang']=$langFromUrl;if($colorFromUrl)$rq['color']=$colorFromUrl;
        header('Location: ' . takePostLoginRedirect('/main'.($rq?'?'.http_build_query($rq):'')));exit;
    }
    $needsAuth=true;
    $showLogin=true;
    $currentUrl = $_SERVER['REQUEST_URI'];
    $parsedUrl = parse_url($currentUrl);
    $queryParams = [];
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
    }
    $newQueryParams = $queryParams;
    unset($newQueryParams['theme']);
    unset($newQueryParams['lang']);
    unset($newQueryParams['color']);
    if ($themeFromUrl) $newQueryParams['theme'] = $themeFromUrl;
    if ($langFromUrl) $newQueryParams['lang'] = $langFromUrl;
    if ($colorFromUrl) $newQueryParams['color'] = $colorFromUrl;
    $newQueryString = http_build_query($newQueryParams);
    $newPath = $parsedUrl['path'] . ($newQueryString ? '?' . $newQueryString : '');
    if ($currentUrl !== $newPath) {
        header('Location: ' . $newPath);
        exit;
    }
}elseif($path==='/main'){
    if(isset($_SESSION['user'])&&!$needsAuth){
        $showLogin=false;
    }else{
        $loginParams=[];if($themeFromUrl)$loginParams['theme']=$themeFromUrl;if($langFromUrl)$loginParams['lang']=$langFromUrl;if($colorFromUrl)$loginParams['color']=$colorFromUrl;
        header('Location: /login' . ($loginParams ? '?' . http_build_query($loginParams) : ''));
        exit;
    }
}elseif($path==='/'||$path===''){
    include 'home/home.html';
    exit;
}else{
    $showLogin=false;
}
