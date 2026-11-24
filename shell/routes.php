<?php

require_once __DIR__ . '/core.php';

$requestUri = $_SERVER['REQUEST_URI'];
$path = oauthRequestPath($requestUri);

// Public SharePoint links are standalone pages: never let them fall through to
// the login/registration guards when routes.php is loaded by a legacy entrypoint.
require_once __DIR__ . '/sharepoint/dispatch.php';
if (sharepoint_dispatch_path($path)) {
    exit;
}

if ($path === '/csrf' && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
    while (ob_get_level() > 1) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => true, 'csrf' => apSecCsrfToken()]);
    exit;
}

if ($path === '/login/oauth/start' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    try {
        $provider = (string)($_GET['provider'] ?? '');
        $mode = (string)($_GET['mode'] ?? 'login');
        $rememberMe = $mode === 'login' && !empty($_GET['remember_me']);
        if ($rememberMe) {
            $_SESSION['oauth_remember_me'] = true;
        } elseif ($mode === 'login') {
            unset($_SESSION['oauth_remember_me']);
        }
        if (($mode === 'link' || $mode === 'admin_gate') && !isset($_SESSION['user']['email'])) {
            header('Location: /login');
            exit;
        }
        if ($mode === 'admin_gate' && (!isset($_SESSION['user']['role']) || (int)$_SESSION['user']['role'] !== 2)) {
            header('Location: /main');
            exit;
        }
        header('Location: ' . oauthStartUrl($provider, $mode, $rememberMe));
        exit;
    } catch (Throwable $e) {
        error_log('OAuth start error: ' . $e->getMessage());
        $failMode = (string)($_GET['mode'] ?? 'login');
        $target = $failMode === 'admin_gate'
            ? '/main?oauth_error=' . urlencode($e->getMessage()) . '#adminpanel/users'
            : ($failMode === 'link'
            ? '/main?profile=1&oauth_error=' . urlencode($e->getMessage())
            : '/login?oauth_error=' . urlencode($e->getMessage()));
        header('Location: ' . $target);
        exit;
    }
}

if (in_array($path, ['/.well-known/openid-configuration', '/.well-known/jwks.json', '/authorize', '/token', '/userinfo', '/oauth-bridge', '/omniroute-bridge', '/login/oauth/authorize', '/login/oauth/access_token', '/api/v1/user', '/api/v1/user/emails'], true)) {
    oauth_dispatch($path);
    exit;
}

if ($path === '/mcp' || $path === '/mcp/' || str_starts_with($path, '/mcp/')) {
    require __DIR__ . '/mcp_download.php';
    exit;
}

if ($path === '/oauth/approve') {
    $clientId = trim((string)($_GET['client_id'] ?? ''));
    $redirectUri = trim((string)($_GET['redirect_uri'] ?? ''));
    $responseType = trim((string)($_GET['response_type'] ?? 'code'));
    $scope = trim((string)($_GET['scope'] ?? 'mcp'));
    $codeChallenge = trim((string)($_GET['code_challenge'] ?? ''));
    $codeChallengeMethod = trim((string)($_GET['code_challenge_method'] ?? 'S256'));
    $state = trim((string)($_GET['state'] ?? ''));
    $oaParamsOk = $responseType === 'code'
        && $clientId !== ''
        && $redirectUri !== ''
        && $codeChallenge !== ''
        && in_array($codeChallengeMethod, ['S256', 'plain'], true)
        && strlen($codeChallenge) >= 43 && strlen($codeChallenge) <= 128
        && preg_match('/^[A-Za-z0-9._~-]+$/', $codeChallenge) === 1;
    if (!$oaParamsOk) {
        http_response_code(400);
        echo '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;padding:40px;"><h1>Invalid OAuth request</h1><p>Missing or malformed authorization parameters. Return to the application and start authorization again.</p></body></html>';
        exit;
    }
    $oaScheme = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string)($_SERVER['SERVER_PORT'] ?? '') === '443') $oaScheme = 'https';
    $oaHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $oaBaseUrl = $oaScheme . '://' . $oaHost;
    $oaLookupJson = @file_get_contents($oaBaseUrl . '/public-api/oauth/client?id=' . urlencode($clientId) . '&redirect_uri=' . urlencode($redirectUri));
    $oaLookup = is_string($oaLookupJson) ? json_decode($oaLookupJson, true) : null;
    $oaClientValid = is_array($oaLookup) && !empty($oaLookup['found']) && $oaLookup['redirect_ok'] === true;
    $oaClientName = is_array($oaLookup) && !empty($oaLookup['client_name']) ? my_htmlspecialchars((string)$oaLookup['client_name']) : 'opencode';
    if (!$oaClientValid) {
        http_response_code(400);
        echo '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;padding:40px;"><h1>Authorization denied</h1><p>The requesting application is unknown or its redirect URI is not allowed.</p></body></html>';
        exit;
    }
    if (!clientAuthSessionOk()) {
        setPostLoginRedirect($requestUri);
        header('Location: /login');
        exit;
    }
    $oaUserEmail = my_htmlspecialchars((string)($_SESSION['user']['email'] ?? ''));
    $oaRoleLabel = ($_SESSION['user']['role'] ?? 0) == 2 ? 'Admin' : (($_SESSION['user']['role'] ?? 0) == 1 ? 'Manager' : 'Guest');
    $oaPrimaryColor = my_htmlspecialchars((string)($config['color'] ?? '#2563eb'));
    $oaRedirectSafe = my_htmlspecialchars($redirectUri);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Authorize Application</title>
        <link rel="icon" type="image/x-icon" href="/shell/views/icon.ico?v=3">
        <link rel="icon" type="image/png" href="/icon.png?v=3">
        <link rel="shortcut icon" type="image/png" href="/icon.png?v=3">
        <style>
            :root{
                color-scheme: light dark;
                --accent: <?php echo $oaPrimaryColor; ?>;
                --bg: light-dark(#f3f5f7,#0f1115);
                --card: light-dark(rgba(255,255,255,.82),rgba(24,26,32,.82));
                --line: light-dark(rgba(20,24,31,.08),rgba(255,255,255,.08));
                --text: light-dark(#1f2329,#f3f5f7);
                --muted: light-dark(#5a6472,#a8b0bc);
                --shadow: 0 24px 64px rgba(0,0,0,.18);
            }
            *{box-sizing:border-box}
            body{
                margin:0;min-height:100vh;font:15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
                background:linear-gradient(180deg, light-dark(#eef1f4,#0b0d10), var(--bg));
                color:var(--text);display:flex;align-items:center;justify-content:center;padding:24px;
            }
            .card{
                width:min(620px,100%);background:var(--card);border:1px solid var(--line);border-radius:24px;padding:32px;
                box-shadow:var(--shadow);backdrop-filter:blur(18px);
            }
            .pill{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:light-dark(rgba(255,255,255,.55),rgba(255,255,255,.05));color:var(--muted);font-size:12px;letter-spacing:.1em;text-transform:uppercase}
            h1{font-size:30px;line-height:1.1;margin:14px 0 10px;word-break:break-word}
            p{color:var(--muted);font-size:15px;line-height:1.6;margin:0}
            .meta{margin:22px 0;padding:18px;border-radius:18px;background:light-dark(rgba(255,255,255,.55),rgba(255,255,255,.04));border:1px solid var(--line)}
            .meta-row{display:flex;justify-content:space-between;gap:16px;padding:8px 0;word-break:break-word}
            .meta-row span:first-child{color:var(--muted)}
            .meta-row strong{text-align:right}
            .scope{margin-top:10px;padding-top:10px;border-top:1px dashed var(--line);color:var(--muted);font-size:13px}
            button{border:1px solid var(--line);border-radius:14px;padding:14px 18px;font:inherit;font-weight:700;cursor:pointer;background:light-dark(#23272f,#e5e9ef);color:light-dark(#fff,#111318);width:100%}
            button[disabled]{opacity:.6;cursor:wait}
            .secondary{margin-top:12px;background:transparent;color:var(--text)}
            .status{margin-top:16px;min-height:24px;color:var(--muted)}
            .ok{color:#2e7d32}.err{color:#c62828}
        </style>
    </head>
    <body>
        <div class="card">
            <div class="pill">AppDashboard</div>
            <h1>Authorize <?php echo $oaClientName; ?></h1>
            <p>The application will be able to act on your behalf using your current account permissions.</p>
            <div class="meta">
                <div class="meta-row"><span>Signed in as</span><strong><?php echo $oaUserEmail; ?> (<?php echo $oaRoleLabel; ?>)</strong></div>
                <div class="meta-row"><span>Application</span><strong><?php echo $oaClientName; ?></strong></div>
                <div class="meta-row"><span>Redirect URI</span><strong><?php echo $oaRedirectSafe; ?></strong></div>
                <div class="meta-row"><span>Scope</span><strong><?php echo my_htmlspecialchars($scope); ?></strong></div>
                <div class="scope">You will be able to revoke this access at any time by changing your password or restoring all sessions.</div>
            </div>
            <button id="approveBtn" type="button">Authorize application</button>
            <button class="secondary" id="denyBtn" type="button">Deny</button>
            <div id="status" class="status"></div>
        </div>
        <script>
        (function () {
            const params = {
                client_id: <?php echo json_encode($clientId, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
                redirect_uri: <?php echo json_encode($redirectUri, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
                code_challenge: <?php echo json_encode($codeChallenge, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
                code_challenge_method: <?php echo json_encode($codeChallengeMethod, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
                state: <?php echo json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>
            };
            const statusEl = document.getElementById('status');
            const approveBtn = document.getElementById('approveBtn');
            const redirectWithError = () => {
                const sep = params.redirect_uri.includes('?') ? '&' : '?';
                window.location.href = params.redirect_uri + sep + 'error=access_denied&state=' + encodeURIComponent(params.state);
            };
            document.getElementById('denyBtn').addEventListener('click', () => {
                approveBtn.disabled = true;
                redirectWithError();
            });
            approveBtn.addEventListener('click', async () => {
                approveBtn.disabled = true;
                statusEl.textContent = 'Authorizing, please wait...';
                try {
                    const res = await fetch('/public-api/oauth/approve', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(params)
                    });
                    const data = await res.json();
                    if (!data.ok) throw new Error(data.error || 'authorization_failed');
                    const sep = data.redirect_uri.includes('?') ? '&' : '?';
                    window.location.href = data.redirect_uri + sep + 'code=' + encodeURIComponent(data.code) + '&state=' + encodeURIComponent(data.state || '');
                } catch (err) {
                    statusEl.textContent = 'Failed to authorize: ' + err.message;
                    statusEl.className = 'status err';
                    approveBtn.disabled = false;
                }
            });
        })();
        </script>
    </body>
    </html>
    <?php
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
    $mobileAuth = $validRequest && in_array((string)($row['platform'] ?? ''), ['nexsteps', 'nexspends', 'nexgitea'], true);
    $deviceName = $validRequest ? my_htmlspecialchars((string)($row['device_name'] ?: 'Desktop app')) : 'Desktop app';
    $platformName = $validRequest ? my_htmlspecialchars((string)($row['platform'] ?: 'desktop')) : 'desktop';
    $userEmail = my_htmlspecialchars((string)($_SESSION['user']['email'] ?? ''));
    $primaryColor = my_htmlspecialchars(trim((string)($config['color'] ?? '')) !== '' ? (string)$config['color'] : '#2563eb');

    // This screen can open on anyone's phone, so language and theme follow the
    // browser instead of the signed-in user's saved preferences.
    $caSupported = ['en', 'ru', 'uk', 'be', 'pl', 'cs'];
    $caLang = 'en';
    foreach (explode(',', strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''))) as $caPart) {
        $caTag = strtolower(trim(explode(';', $caPart)[0]));
        if ($caTag === '') continue;
        $caPrimary = preg_split('/[-_]/', $caTag)[0] ?? '';
        if ($caPrimary === 'ua') $caPrimary = 'uk';
        if ($caPrimary === 'by') $caPrimary = 'be';
        if (in_array($caPrimary, $caSupported, true)) {
            $caLang = $caPrimary;
            break;
        }
    }
    $caAll = require __DIR__ . '/client_auth_i18n.php';
    $ca = $caAll[$caLang] ?? $caAll['en'];
    $caE = static function (string $key) use ($ca): string {
        return my_htmlspecialchars((string)($ca[$key] ?? ''));
    };
    $caJs = json_encode($ca, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <!doctype html>
    <html lang="<?php echo $caLang; ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="light dark">
        <title><?php echo $caE('title'); ?></title>
        <link rel="icon" type="image/x-icon" href="/shell/views/icon.ico?v=3">
        <link rel="icon" type="image/png" href="/icon.png?v=3">
        <link rel="shortcut icon" type="image/png" href="/icon.png?v=3">
        <style>
            :root{
                color-scheme: light dark;
                --accent: <?php echo $primaryColor; ?>;
                --bg: #eef1f5;
                --card: #ffffff;
                --card-soft: #f5f7fa;
                --line: rgba(16,24,40,.10);
                --text: #171b21;
                --muted: #5c6673;
                --ok: #1d7a3d;
                --err: #c02626;
                --shadow: 0 20px 50px rgba(16,24,40,.13);
                --ring: rgba(37,99,235,.45);
            }
            @media (prefers-color-scheme: dark){
                :root{
                    --bg: #0b0e12;
                    --card: #161a21;
                    --card-soft: #1d222b;
                    --line: rgba(255,255,255,.10);
                    --text: #f1f4f8;
                    --muted: #a7b0bd;
                    --ok: #7fe0a0;
                    --err: #ff9d9d;
                    --shadow: 0 20px 50px rgba(0,0,0,.55);
                    --ring: rgba(125,175,255,.5);
                }
            }
            *{box-sizing:border-box}
            body{
                margin:0;
                min-height:100vh;
                min-height:100dvh;
                display:flex;
                flex-direction:column;
                align-items:center;
                justify-content:flex-start;
                gap:14px;
                padding:clamp(14px,4vw,28px);
                font:15px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
                background:var(--bg);
                color:var(--text);
                -webkit-text-size-adjust:100%;
            }
            .card{
                width:100%;
                max-width:560px;
                margin-block:auto;
                padding:clamp(18px,5vw,30px);
                border:1px solid var(--line);
                border-radius:22px;
                background:var(--card);
                box-shadow:var(--shadow);
            }
            .pill{
                display:inline-flex;align-items:center;gap:8px;padding:6px 11px;border-radius:999px;
                background:var(--card-soft);border:1px solid var(--line);color:var(--muted);
                font-size:11.5px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
            }
            h1{
                margin:14px 0 8px;
                font-size:clamp(22px,6.4vw,32px);
                line-height:1.15;
                letter-spacing:-.01em;
                overflow-wrap:anywhere;
            }
            p{margin:0;color:var(--muted);font-size:15px;line-height:1.6;overflow-wrap:anywhere}
            .meta{
                margin:20px 0 18px;padding:6px 16px;border-radius:16px;
                background:var(--card-soft);border:1px solid var(--line);
            }
            .meta-row{display:flex;flex-wrap:wrap;align-items:baseline;gap:2px 12px;padding:10px 0;border-bottom:1px solid var(--line)}
            .meta-row:last-child{border-bottom:0}
            .meta-row span{color:var(--muted);font-size:14px;white-space:nowrap}
            .meta-row strong{
                margin-left:auto;min-width:0;max-width:100%;
                text-align:right;overflow-wrap:anywhere;
                font-size:14px;line-height:1.4;
            }
            /* Narrow phones: give long account/device values a little more room */
            @media (max-width:400px){
                .meta{padding:6px 12px}
                .meta-row strong{font-size:13.5px}
            }
            .btn{
                display:flex;align-items:center;justify-content:center;gap:8px;
                width:100%;margin:0;padding:14px 18px;
                border:1px solid transparent;border-radius:14px;
                font:inherit;font-weight:700;line-height:1.25;text-align:center;
                cursor:pointer;transition:filter .15s ease,transform .12s ease,background .15s ease,border-color .15s ease;
            }
            .btn-primary{background:var(--accent);color:#fff;box-shadow:0 10px 22px rgba(16,24,40,.18)}
            .btn-primary:hover{filter:brightness(1.07)}
            .btn-primary:active{transform:translateY(1px);box-shadow:none}
            .btn-secondary{margin-top:10px;background:transparent;color:var(--text);border-color:var(--line)}
            .btn-secondary:hover{background:var(--card-soft)}
            .btn:focus-visible{outline:3px solid var(--ring);outline-offset:2px}
            .btn[disabled]{opacity:.6;cursor:wait;filter:none;transform:none;box-shadow:none}
            .status{margin-top:14px;min-height:22px;color:var(--muted);font-size:14px;overflow-wrap:anywhere}
            .status.ok{color:var(--ok)}
            .status.err{color:var(--err)}
            .legal{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px;padding-top:18px;border-top:1px solid var(--line)}
            .legal a{
                flex:1 1 130px;
                display:inline-flex;align-items:center;justify-content:center;gap:8px;
                padding:11px 14px;border-radius:12px;
                border:1px solid var(--line);background:var(--card-soft);
                color:var(--text);font-size:13.5px;font-weight:600;text-decoration:none;
                transition:color .15s ease,border-color .15s ease,background .15s ease;
            }
            .legal a:hover{color:var(--accent);border-color:var(--accent)}
            .legal a:focus-visible{outline:3px solid var(--ring);outline-offset:2px}
            .legal svg{width:16px;height:16px;flex:none;opacity:.85}
            .toast{
                position:fixed;left:14px;right:14px;bottom:calc(14px + env(safe-area-inset-bottom));
                margin:0 auto;max-width:360px;padding:13px 16px;border-radius:14px;
                background:var(--card);border:1px solid var(--line);color:var(--text);
                box-shadow:var(--shadow);font-size:14px;
                transform:translateY(18px);opacity:0;pointer-events:none;transition:.22s ease;
            }
            .toast.show{transform:none;opacity:1}
        </style>
    </head>
    <body>
        <div class="card">
            <div class="pill"><?php echo $mobileAuth ? 'Nexorbit' : 'NexOrbit Desktop'; ?></div>
            <?php if (!$validRequest): ?>
                <h1><?php echo $caE('invalid_title'); ?></h1>
                <p><?php echo $caE('invalid_desc'); ?></p>
            <?php else: ?>
                <h1><?php echo $caE($mobileAuth ? 'mobile_title' : 'desktop_title'); ?></h1>
                <p><?php echo $caE($mobileAuth ? 'mobile_desc' : 'desktop_desc'); ?></p>
                <div class="meta">
                    <div class="meta-row"><span><?php echo $caE('account'); ?></span><strong><?php echo $userEmail; ?></strong></div>
                    <div class="meta-row"><span><?php echo $caE('device'); ?></span><strong><?php echo $deviceName; ?></strong></div>
                    <div class="meta-row"><span><?php echo $caE('platform'); ?></span><strong><?php echo $platformName; ?></strong></div>
                </div>
                <button id="approveBtn" class="btn btn-primary" type="button"><?php echo $caE('approve'); ?></button>
                <button class="btn btn-secondary" type="button" onclick="window.close()"><?php echo $caE('close'); ?></button>
                <div id="status" class="status" role="status" aria-live="polite"></div>
                <script>
                const T = <?php echo $caJs; ?>;
                (function syncLegalLinks(){
                    const lang = document.documentElement.lang || 'en';
                    const dark = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
                    const apply = () => {
                        const theme = dark && dark.matches ? 'dark' : 'light';
                        document.querySelectorAll('a[data-legal]').forEach((a) => {
                            a.href = a.dataset.legal + '?lang=' + encodeURIComponent(lang) + '&theme=' + theme;
                        });
                    };
                    apply();
                    if (dark && dark.addEventListener) dark.addEventListener('change', apply);
                })();
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
                    statusEl.textContent = T.approving;
                    showToast(statusEl.textContent);
                    try {
                        const res = await fetch('/main?api=client_auth&action=approve_browser_login', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                request_id: <?php echo json_encode($requestId, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
                                browser_token: <?php echo json_encode($browserToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>
                            })
                        });
                        const data = await res.json();
                        if (!data.ok) {
                            throw new Error(data.error || 'authorization_failed');
                        }
                        statusEl.textContent = T.confirmed;
                        statusEl.className = 'status ok';
                        showToast(statusEl.textContent);
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        }
                    } catch (err) {
                        statusEl.textContent = T.failed + err.message;
                        statusEl.className = 'status err';
                        showToast(statusEl.textContent);
                        approveBtn.disabled = false;
                    }
                });
                </script>
            <?php endif; ?>
            <nav class="legal" aria-label="<?php echo $caE('legal_label'); ?>">
                <a data-legal="/privacy" href="/privacy?lang=<?php echo $caLang; ?>" target="_blank" rel="noopener noreferrer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>
                    <span><?php echo $caE('privacy'); ?></span>
                </a>
                <a data-legal="/terms" href="/terms?lang=<?php echo $caLang; ?>" target="_blank" rel="noopener noreferrer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>
                    <span><?php echo $caE('terms'); ?></span>
                </a>
            </nav>
        </div>
        <div id="toast" class="toast" role="status" aria-live="polite"></div>
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
        include APP_ROOT . '/shell/privacy.html';
        exit;
    }elseif($path==='/terms'){
        include APP_ROOT . '/shell/terms.html';
        exit;
    }
}

if($path==='/database'){
    if($needsAuth){
        header('Location: /login');
        exit;
    }
    $sessionIsAdminDb = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == 2;
    if(!$sessionIsAdminDb || !adminHasCapability('database') || !adminIpAllowed() || !adminPanelGateValid()){
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403 Forbidden</title></head><body style="font-family:sans-serif;text-align:center;padding:60px"><h1>403 Forbidden</h1><p>Unlock Admin Panel first, then open Database.</p><a href="/main">Back</a></body></html>';
        exit;
    }
    adminAuditLog('database_access', '/database', 'ok');
    
    $_GET['theme'] = $_GET['theme'] ?? $config['theme'];
    $_GET['lang'] = $_GET['lang'] ?? $language;
    $_GET['color'] = $_GET['color'] ?? $config['color'];
    include APP_ROOT . '/shell/db_admin.php';
    exit;
}

if($path==='/verify'){
    $token = apSanitizeVerificationToken(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? ''));
    if(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['confirm_email_verification']) && $token !== ''){
        $email = findValidVerificationEmail($token);
        if($email && completeEmailVerification($email)){
            header('Location: ' . takePostLoginRedirect('/main'));
            exit;
        }
        error_log('[Verify] POST failed for token ' . substr($token, 0, 12) . '...');
        apRenderVerificationPage(
            $translations['verification_failed'] ?? 'Verification failed',
            $translations['verification_link_expired_desc'] ?? 'This verification link is invalid, expired, or has already been used. Request a new link below.',
            null,
            true
        );
    }
    if($token !== ''){
        $tokenRow = findVerificationTokenRow($token);
        if($tokenRow){
            $email = apNormalizeAuthEmail((string)$tokenRow['email']);
            if((int)$tokenRow['expires'] <= time()){
                apRenderVerificationPage(
                    $translations['verification_link_expired'] ?? 'Verification link expired',
                    $translations['verification_link_expired_desc'] ?? 'This verification link has expired. Request a new link below.',
                    $email,
                    true
                );
            }
            $pendingRow = getPendingUser($email);
            $existingUser = getUserByEmail($email);
            if($pendingRow || ($existingUser && empty($existingUser['verified']))){
                apRenderVerificationPage(
                    $translations['verify_account'] ?? 'Verify Account',
                    $translations['email_verify_confirm_desc'] ?? 'Click the button below to confirm this email address and activate your account.',
                    $email,
                    false,
                    $token,
                    $translations['confirm_label'] ?? 'Confirm'
                );
            }
            apRenderVerificationPage(
                $translations['email_verified'] ?? 'Email verified',
                $translations['verification_already_done'] ?? 'This email address is already verified. You can sign in now.',
                $email,
                false
            );
        }
        error_log('[Verify] token not found: ' . substr($token, 0, 12) . '...');
        apRenderVerificationPage(
            $translations['verification_failed'] ?? 'Verification failed',
            $translations['verification_link_expired_desc'] ?? 'This verification link is invalid or outdated. If you requested a new email, use the latest link. Otherwise request a new link below.',
            null,
            true
        );
    }
    header('Location: /login');
    exit;
}

if(isset($_GET['sharedFile'])){
    $token = (string)($_GET['sharedFile'] ?? '');
    $token = preg_replace('/[^A-Za-z0-9_-]/', '', $token);
    if ($token === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Shared link not found';
        exit;
    }
    $query = [];
    foreach (['theme', 'lang', 'color'] as $key) {
        if (!empty($_GET[$key])) {
            $query[$key] = (string)$_GET[$key];
        }
    }
    $target = '/s/' . rawurlencode($token) . ($query ? '?' . http_build_query($query) : '');
    header('Location: ' . $target, true, 302);
    exit;
}

if($path==='/one-time-pin'){
    $needsAuth=false;
    $showLogin=false;
    $showOtpPage=true;
    $pageTitle=$translations['otp_page_title']??'One-Time PIN Login';
}

if($path==='/login'||$path==='/register'||$path==='/login/oauth/callback'||$path==='/login/oauth/start'||(isset($_GET['code'])&&strpos($path,'/login')!==false)){
    $sessionOkEarly=(isset($_SESSION['user']['email'],$_SESSION['auth_time'])&&getUserFromActiveSessions(session_id())!==null)||ensureAuthenticatedSession();
    $isAuthPost=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='POST';
    $skipEarlyRedirect=$isAuthPost;
    if(!$skipEarlyRedirect && $path==='/register' && (
        (!empty($_GET['password_required']) && (($_GET['step']??'')==='set_password'))
        || !empty($_SESSION['pending_set_password_only'])
        || !empty($_SESSION['pending_oauth_data'])
        || !empty($_SESSION['pending_google_data'])
    )){
        $skipEarlyRedirect=true;
    }
    if($sessionOkEarly&&!isset($_GET['code'])&&!$skipEarlyRedirect){
        $rq=[];if($themeFromUrl)$rq['theme']=$themeFromUrl;if($langFromUrl)$rq['lang']=$langFromUrl;if($colorFromUrl)$rq['color']=$colorFromUrl;
        header('Location: ' . takePostLoginRedirect('/main'.($rq?'?'.http_build_query($rq):'')));exit;
    }
    $needsAuth=true;
    $showLogin=true;
    if(!$isAuthPost){
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
    }
}elseif($path==='/main'){
    if(isset($_SESSION['user'])&&!$needsAuth){
        $showLogin=false;
    }else{
        $loginParams=[];if($themeFromUrl)$loginParams['theme']=$themeFromUrl;if($langFromUrl)$loginParams['lang']=$langFromUrl;if($colorFromUrl)$loginParams['color']=$colorFromUrl;
        header('Location: /login' . ($loginParams ? '?' . http_build_query($loginParams) : ''));
        exit;
    }
}elseif($path==='/apps/lopaka/'||$path==='/apps/lopaka'){
    if(isset($_SESSION['user'])&&!$needsAuth){
        $showLogin=false;
    }else{
        $loginParams=[];if($themeFromUrl)$loginParams['theme']=$themeFromUrl;if($langFromUrl)$loginParams['lang']=$langFromUrl;if($colorFromUrl)$loginParams['color']=$colorFromUrl;
        header('Location: /login' . ($loginParams ? '?' . http_build_query($loginParams) : ''));
        exit;
    }
}elseif(str_starts_with($path, '/apps/') && str_ends_with($path, '.php') && !str_contains($path, 'webdav.php')){
    if(isset($_SESSION['user'])&&!$needsAuth){
        $showLogin=false;
    }else{
        $loginParams=[];if($themeFromUrl)$loginParams['theme']=$themeFromUrl;if($langFromUrl)$loginParams['lang']=$langFromUrl;if($colorFromUrl)$loginParams['color']=$colorFromUrl;
        header('Location: /login' . ($loginParams ? '?' . http_build_query($loginParams) : ''));
        exit;
    }
}elseif($path==='/'||$path===''){
    // Залогиненным промо-виджеты на home не показываем (флаг читается в home.js).
    $__skHomeLoggedIn = isset($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['email']);
    echo '<script>window.__skUserLoggedIn = ' . ($__skHomeLoggedIn ? 'true' : 'false') . ';</script>';
    $__homeContent = file_get_contents(__DIR__ . '/views/home.html');
    if ($__homeContent !== false) {
        $__appVersion = htmlspecialchars(getenv('APP_VERSION') ?: '1.0.7', ENT_QUOTES, 'UTF-8');
        $__homeContent = str_replace('id="navVersion">v1.0.7<', 'id="navVersion">v' . $__appVersion . '<', $__homeContent);
        echo $__homeContent;
    }
    exit;
}else{
    $showLogin=false;
}
