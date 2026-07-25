<?php

function my_htmlspecialchars($string, $flags = ENT_QUOTES | ENT_SUBSTITUTE, $encoding = 'UTF-8', $double_encode = true) {
    return htmlspecialchars($string ?? '', $flags, $encoding, $double_encode);
}
function app_avatar_src($photo): string {
    $src = trim((string)($photo ?: 'default_avatar.png'));
    if (preg_match('#^https://lh3\.googleusercontent\.com/#i', $src)) {
        return '/main?api=avatar_proxy&u=' . rawurlencode($src);
    }
    return $src;
}
function ap_sanitize_hex_color(string $c, string $fallback = '#007bff'): string {
    $c = trim($c);
    if ($c !== '' && ($c[0] ?? '') !== '#') {
        $c = '#' . $c;
    }
    if (preg_match('/^#([0-9A-Fa-f]{6})$/', $c, $m)) {
        return '#' . strtolower($m[1]);
    }
    if (preg_match('/^#([0-9A-Fa-f]{3})$/', $c, $m)) {
        $h = $m[1];
        return '#' . strtolower($h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2]);
    }
    return $fallback;
}
function ap_normalize_pref_theme(string $theme, string $fallback = 'light'): string {
    return in_array($theme, ['light', 'dark', 'auto'], true) ? $theme : $fallback;
}
function ap_normalize_pref_language(string $lang, string $fallback = 'en'): string {
    $lang = preg_replace('/[^a-z0-9_-]/i', '', strtolower($lang));
    if ($lang === 'ua') $lang = 'uk';
    if ($lang === 'by') $lang = 'be';
    return in_array($lang, ['en', 'ru', 'uk', 'be', 'pl', 'cs'], true) ? $lang : $fallback;
}
function ap_pref_cookie_secure(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}
function ap_read_pref_cookies(array $fallback): array {
    return [
        'language' => ap_normalize_pref_language((string)($_COOKIE['sk_language'] ?? ''), (string)($fallback['language'] ?? 'en')),
        'theme' => ap_normalize_pref_theme((string)($_COOKIE['sk_theme'] ?? ''), (string)($fallback['theme'] ?? 'light')),
        'color' => ap_sanitize_hex_color((string)($_COOKIE['sk_color'] ?? ''), (string)($fallback['color'] ?? '#007bff')),
    ];
}
function ap_write_pref_cookies(array $config): void {
    if (headers_sent()) return;
    $opts = [
        'expires' => time() + 365 * 86400,
        'path' => '/',
        'secure' => ap_pref_cookie_secure(),
        'httponly' => false,
        'samesite' => 'Lax',
    ];
    setcookie('sk_language', ap_normalize_pref_language((string)($config['language'] ?? 'en')), $opts);
    setcookie('sk_theme', ap_normalize_pref_theme((string)($config['theme'] ?? 'light')), $opts);
    setcookie('sk_color', ap_sanitize_hex_color((string)($config['color'] ?? '#007bff')), $opts);
}
function json_out($data, $status = 200) {
    if (PHP_SESSION_ACTIVE === session_status()) {
        session_write_close();
    }
    if (!headers_sent()) {
        $responseStatus = ($status === 401 && isset($_GET['api'])) ? 200 : $status;
        http_response_code($responseStatus);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if (isset($_GET['api']) && $_GET['api'] === 'avatar_proxy') {
    $url = trim((string)($_GET['u'] ?? ''));
    if (!preg_match('#^https://lh3\.googleusercontent\.com/#i', $url)) {
        http_response_code(400);
        exit;
    }
    $cacheDir = APP_ROOT . '/uploads/avatar_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $cacheFile = $cacheDir . '/' . hash('sha256', $url) . '.bin';
    $contentTypeFile = $cacheFile . '.type';
    if (!is_file($cacheFile) || filemtime($cacheFile) < time() - 604800) {
        $body = false;
        $code = 0;
        $type = 'image/jpeg';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_USERAGENT => 'AppDashboard/1.0',
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 8, 'follow_location' => 1, 'user_agent' => 'AppDashboard/1.0']]);
            $body = @file_get_contents($url, false, $ctx);
            $code = is_string($body) && $body !== '' ? 200 : 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (stripos($headerLine, 'Content-Type:') === 0) {
                        $type = trim(substr($headerLine, 13));
                        break;
                    }
                }
            }
        }
        if ($code === 200 && is_string($body) && $body !== '') {
            @file_put_contents($cacheFile, $body, LOCK_EX);
            @file_put_contents($contentTypeFile, preg_match('#^image/#i', $type) ? $type : 'image/jpeg', LOCK_EX);
            header('Content-Type: ' . (preg_match('#^image/#i', $type) ? $type : 'image/jpeg'));
            header('Cache-Control: public, max-age=604800, immutable');
            echo $body;
            exit;
        }
    }
    if (is_file($cacheFile)) {
        header('Content-Type: ' . (is_file($contentTypeFile) ? trim((string)file_get_contents($contentTypeFile)) : 'image/jpeg'));
        header('Cache-Control: public, max-age=604800, immutable');
        readfile($cacheFile);
        exit;
    }
    header('Location: /default_avatar.png', true, 302);
    exit;
}
const PASSWORD_MIN_LENGTH = 6;
function validatePasswordPolicy(string $password): ?string {
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) return 'password_too_short';
    if (!preg_match('/\p{L}/u', $password)) return 'password_needs_letter';
    if (!preg_match('/\d/', $password)) return 'password_needs_digit';
    return null;
}
function generateSecurePassword(int $length = 12): string {
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $all = $letters . $digits;
    $length = max(PASSWORD_MIN_LENGTH, $length);
    $chars = [$letters[random_int(0, strlen($letters) - 1)], $digits[random_int(0, strlen($digits) - 1)]];
    for ($i = 2; $i < $length; $i++) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}
function renderApUserActionsCell(string $email, string $name, bool $isRoot, bool $isSelf, bool $isPending, array $translations): string {
    if ($isRoot) return '<span style="opacity:.35;font-size:12px;">root</span>';
    if ($isSelf) return '<span style="color:var(--primary-color);font-size:12px;">' . my_htmlspecialchars($translations['current_session'] ?? 'You') . '</span>';
    $eH = my_htmlspecialchars($email);
    $nH = my_htmlspecialchars($name);
    $eJ = addslashes($email);
    $nJ = addslashes($name);
    $menuLabel = my_htmlspecialchars($translations['user_actions'] ?? 'User actions');
    $deleteLabel = my_htmlspecialchars($translations['delete_user'] ?? 'Delete user');
    $resetLabel = my_htmlspecialchars($translations['reset_user_password'] ?? 'Reset password');
    $resetItem = $isPending ? '' : '<button type="button" class="ap-user-menu-item" role="menuitem" onclick="apCloseUserMenus();apOpenResetPassword(\'' . $eJ . '\',\'' . $nJ . '\')">' . $resetLabel . '</button>';
    return '<div class="ap-user-actions-menu">'
        . '<button type="button" class="ap-user-menu-btn" title="' . $menuLabel . '" aria-label="' . $menuLabel . '" aria-haspopup="true" aria-expanded="false" data-email="' . $eH . '" data-name="' . $nH . '">'
        . '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>'
        . '</button>'
        . '<div class="ap-user-menu-dropdown" role="menu">'
        . $resetItem
        . '<button type="button" class="ap-user-menu-item ap-user-menu-item-danger" role="menuitem" onclick="apCloseUserMenus();apOpenDeleteUser(\'' . $eJ . '\',\'' . $nJ . '\')">' . $deleteLabel . '</button>'
        . '</div></div>';
}
function ensureClientAuthTokensSchema(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS client_auth_tokens (
        token_hash TEXT PRIMARY KEY,
        email TEXT NOT NULL,
        device_id TEXT NOT NULL DEFAULT '',
        device_name TEXT NOT NULL DEFAULT '',
        platform TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        last_used_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        expires_at INTEGER NOT NULL,
        revoked INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_tokens_email ON client_auth_tokens(email)");
}
function createClientAuthToken(AppDatabase $db, string $email, string $deviceId, string $deviceName, string $platform): array {
    ensureClientAuthTokensSchema($db);
    $purge = $db->prepare('DELETE FROM client_auth_tokens WHERE expires_at < :t');
    $purge->bindValue(':t', time(), SQLITE3_INTEGER);
    $purge->execute();
    if ($deviceId !== '') {
        $del = $db->prepare('DELETE FROM client_auth_tokens WHERE email = :e AND device_id = :d');
        $del->bindValue(':e', $email, SQLITE3_TEXT);
        $del->bindValue(':d', $deviceId, SQLITE3_TEXT);
        $del->execute();
    }
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $now = time();
    $expiresAt = $now + (180 * 24 * 3600);
    $stmt = $db->prepare("INSERT OR REPLACE INTO client_auth_tokens(token_hash,email,device_id,device_name,platform,created_at,last_used_at,expires_at,revoked) VALUES(:h,:e,:d,:n,:p,:c,:u,:x,0)");
    $stmt->bindValue(':h', $tokenHash, SQLITE3_TEXT);
    $stmt->bindValue(':e', $email, SQLITE3_TEXT);
    $stmt->bindValue(':d', $deviceId, SQLITE3_TEXT);
    $stmt->bindValue(':n', $deviceName, SQLITE3_TEXT);
    $stmt->bindValue(':p', $platform, SQLITE3_TEXT);
    $stmt->bindValue(':c', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':u', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':x', $expiresAt, SQLITE3_INTEGER);
    $stmt->execute();
    return ['token' => $rawToken, 'expires_at' => $expiresAt];
}
function ensureClientBrowserAuthSchema(AppDatabase $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS client_auth_browser_requests (
        request_id TEXT PRIMARY KEY,
        poll_token_hash TEXT NOT NULL,
        browser_token_hash TEXT NOT NULL,
        callback_url TEXT NOT NULL DEFAULT '',
        device_id TEXT NOT NULL DEFAULT '',
        device_name TEXT NOT NULL DEFAULT '',
        platform TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending',
        email TEXT NOT NULL DEFAULT '',
        issued_token TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        approved_at INTEGER,
        expires_at INTEGER NOT NULL,
        claimed_at INTEGER
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_browser_requests_status ON client_auth_browser_requests(status, expires_at)");
}
function purgeClientBrowserAuthRequests(AppDatabase $db): void {
    ensureClientBrowserAuthSchema($db);
    $stmt = $db->prepare("DELETE FROM client_auth_browser_requests WHERE expires_at < :now OR (status = 'claimed' AND claimed_at IS NOT NULL AND claimed_at < :old)");
    $stmt->bindValue(':now', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':old', time() - 86400, SQLITE3_INTEGER);
    $stmt->execute();
}
function normalizeClientAuthCallbackUrl(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    $parts = parse_url($url);
    if (!is_array($parts)) return '';
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $port = (int)($parts['port'] ?? 0);
    if ($scheme !== 'http') return '';
    if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) return '';
    if ($port < 1 || $port > 65535) return '';
    return $url;
}
function createClientBrowserAuthRequest(AppDatabase $db, string $deviceId, string $deviceName, string $platform, string $callbackUrl): array {
    purgeClientBrowserAuthRequests($db);
    $requestId = bin2hex(random_bytes(16));
    $pollToken = bin2hex(random_bytes(32));
    $browserToken = bin2hex(random_bytes(32));
    $expiresAt = time() + 900;
    $stmt = $db->prepare("INSERT INTO client_auth_browser_requests(request_id,poll_token_hash,browser_token_hash,callback_url,device_id,device_name,platform,status,expires_at) VALUES(:r,:p,:b,:c,:d,:n,:pl,'pending',:x)");
    $stmt->bindValue(':r', $requestId, SQLITE3_TEXT);
    $stmt->bindValue(':p', hash('sha256', $pollToken), SQLITE3_TEXT);
    $stmt->bindValue(':b', hash('sha256', $browserToken), SQLITE3_TEXT);
    $stmt->bindValue(':c', $callbackUrl, SQLITE3_TEXT);
    $stmt->bindValue(':d', $deviceId, SQLITE3_TEXT);
    $stmt->bindValue(':n', $deviceName, SQLITE3_TEXT);
    $stmt->bindValue(':pl', $platform, SQLITE3_TEXT);
    $stmt->bindValue(':x', $expiresAt, SQLITE3_INTEGER);
    $stmt->execute();
    return [
        'request_id' => $requestId,
        'poll_token' => $pollToken,
        'browser_token' => $browserToken,
        'expires_at' => $expiresAt,
    ];
}
function getClientBrowserAuthRequest(AppDatabase $db, string $requestId): ?array {
    ensureClientBrowserAuthSchema($db);
    $stmt = $db->prepare("SELECT * FROM client_auth_browser_requests WHERE request_id = :r LIMIT 1");
    $stmt->bindValue(':r', $requestId, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}
function clientBrowserAuthMatches(string $plain, string $hash): bool {
    return $plain !== '' && $hash !== '' && hash_equals($hash, hash('sha256', $plain));
}
function clientAuthSessionOk(): bool {
    return isset($_SESSION['user']['email'], $_SESSION['auth_time']) && getUserFromActiveSessions(session_id()) !== null;
}
function setPostLoginRedirect(string $target): void {
    if ($target !== '' && str_starts_with($target, '/')) {
        $_SESSION['post_login_redirect'] = $target;
    }
}
function takePostLoginRedirect(string $fallback = '/main'): string {
    $target = isset($_SESSION['post_login_redirect']) ? (string)$_SESSION['post_login_redirect'] : '';
    unset($_SESSION['post_login_redirect']);
    if ($target !== '' && str_starts_with($target, '/')) {
        return $target;
    }
    return $fallback;
}
function loginUserSession(string $email, array $users): bool {
    if (!isset($users[$email])) return false;
    $_SESSION['user'] = $users[$email];
    $_SESSION['user']['email'] = $email;
    $_SESSION['auth_time'] = time();
    setLastActiveUser($email);
    cleanupExpiredUserSessions($email);
    addUserToActiveSessions($email, session_id(), $_SESSION['user']);
    if (!headers_sent()) {
        $__secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        setcookie('sk_user_has_account', '1', [
            'expires' => time() + 365 * 86400,
            'path' => '/',
            'secure' => $__secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        ap_write_pref_cookies($users[$email]);
    }
    return true;
}

$trustedDeviceRestoreNotice = null;
$trustedDeviceHint = parseTrustedDeviceHintCookie((string)($_COOKIE['trusted_device_hint'] ?? ''));

function oauthLoginRedirectUri(): string {
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $cfVisitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
    $scheme = 'http';
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $forwardedProto === 'https'
        || str_contains($cfVisitor, '"scheme":"https"')) {
        $scheme = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'serverkherson.xyz';
    return $scheme . '://' . $host . '/login/oauth/callback';
}

$PUBLIC_APPS_WITHOUT_AUTH=['test','demo','landing','info','public','shared_file'];
$app=$_GET['app']??'';
if(in_array($app,$PUBLIC_APPS_WITHOUT_AUTH)){
    $needsAuth=false;
    $showLogin=false;
}else{
    $authTimeout=12*60*60;
    $oauthRedirectUri=oauthLoginRedirectUri();
    $googleRedirectUri=$oauthRedirectUri;
    $needsAuth=false;
    $trustedDeviceAutoLoginSuppressed = hasTrustedDeviceLogoutSuppression();
    if(isset($_SESSION['user'])&&isset($_SESSION['auth_time'])){
        $timeSinceAuth=time()-$_SESSION['auth_time'];
        $currentSession=getUserFromActiveSessions(session_id());
        if($timeSinceAuth>$authTimeout||!$currentSession){
            $userEmail = $_SESSION['user']['email'] ?? '';
            $trustedCookie = $userEmail !== '' ? getTrustedDeviceCookieForEmail($userEmail) : '';
            $rotatedTrustedCookie = $trustedCookie !== '' ? verifyAndRotateTrustedDeviceCookie($trustedCookie) : null;
            $isTrusted = $userEmail !== '' && $rotatedTrustedCookie !== null && (string)($rotatedTrustedCookie['email'] ?? '') === $userEmail;
            if($isTrusted){
                $users = loadUsers();
                if(isset($users[$userEmail])){
                    session_regenerate_id(true);
                    $_SESSION['user'] = $users[$userEmail];
                    $_SESSION['user']['email'] = $userEmail;
                    $_SESSION['auth_time'] = time();
                    setLastActiveUser($userEmail);
                    cleanupExpiredUserSessions($userEmail);
                    addUserToActiveSessions($userEmail, session_id(), $_SESSION['user']);
                    setTrustedDeviceCookieForEmail($userEmail, $rotatedTrustedCookie['new_token']);
                    rememberTrustedDeviceBrowserAccount($userEmail);
                    setTrustedDeviceHintCookie($userEmail, getDeviceFingerprint());
                } else {
                    removeUserFromActiveSessions($userEmail,session_id());
                    session_destroy();
                    $needsAuth=true;
                }
            } else {
                removeUserFromActiveSessions($_SESSION['user']['email'] ?? '',session_id());
                session_destroy();
                $needsAuth=true;
            }
        }
    }elseif(!isset($_SESSION['user'])){
        if(!$trustedDeviceAutoLoginSuppressed){
            $trustedCookieCandidates = getTrustedDeviceCookieCandidates((string)($trustedDeviceHint['email'] ?? ''));
            $autoLoginRestored = false;
            foreach($trustedCookieCandidates as $candidate){
                $candidateToken = (string)($candidate['token'] ?? '');
                if($candidateToken === '') continue;
                $rotated = verifyAndRotateTrustedDeviceCookie($candidateToken);
                if($rotated !== null){
                    $autoEmail = (string)($rotated['email'] ?? '');
                    $users = loadUsers();
                    if(isset($users[$autoEmail])){
                        session_regenerate_id(true);
                        $_SESSION['user'] = $users[$autoEmail];
                        $_SESSION['user']['email'] = $autoEmail;
                        $_SESSION['auth_time'] = time();
                        setLastActiveUser($autoEmail);
                        cleanupExpiredUserSessions($autoEmail);
                        addUserToActiveSessions($autoEmail, session_id(), $_SESSION['user']);
                        setTrustedDeviceCookieForEmail($autoEmail, $rotated['new_token']);
                        rememberTrustedDeviceBrowserAccount($autoEmail);
                        setTrustedDeviceHintCookie($autoEmail, getDeviceFingerprint());
                        if(!empty($candidate['legacy'])){
                            clearTrustedDeviceCookie();
                        }
                        $autoLoginRestored = true;
                        break;
                    } else {
                        clearTrustedDeviceCookieForEmail($autoEmail);
                        forgetTrustedDeviceBrowserAccount($autoEmail);
                        if(!empty($candidate['legacy'])){
                            clearTrustedDeviceCookie();
                        }
                    }
                } else {
                    $candidateEmail = trim((string)($candidate['email'] ?? ''));
                    $cookieName = trim((string)($candidate['cookie_name'] ?? ''));
                    if(!empty($candidate['legacy']) || $cookieName === 'trusted_device'){
                        clearTrustedDeviceCookie();
                    } else {
                        if($candidateEmail !== ''){
                            clearTrustedDeviceCookieForEmail($candidateEmail);
                            forgetTrustedDeviceBrowserAccount($candidateEmail);
                        } elseif($cookieName !== ''){
                            setcookie($cookieName, '', [
                                'expires' => time() - 3600,
                                'path' => '/',
                                'secure' => trustedDeviceCookieIsSecure(),
                                'httponly' => true,
                                'samesite' => 'Strict'
                            ]);
                            unset($_COOKIE[$cookieName]);
                        }
                    }
                }
            }
            if(!$autoLoginRestored){
                if ($trustedDeviceHint && trustedDeviceExistsForHint((string)$trustedDeviceHint['email'], (string)$trustedDeviceHint['fingerprint'])) {
                    $trustedDeviceRestoreNotice = 'Saved session on this device could not be restored. Sign in again to renew it.';
                } else {
                    clearTrustedDeviceHintCookie();
                }
                $needsAuth=true;
            } else {
                $needsAuth=false;
            }
        } else {
            if ($trustedDeviceHint && trustedDeviceExistsForHint((string)$trustedDeviceHint['email'], (string)$trustedDeviceHint['fingerprint'])) {
                $needsAuth = true;
            } elseif ($trustedDeviceHint) {
                clearTrustedDeviceHintCookie();
            }
            $needsAuth=true;
        }
    }
}

// Auth config loaded from SQLite via db.php
$authConfig=loadAuthConfig();

function apCreateNotification(AppDatabase $db, string $userEmail, string $appId, string $severity, string $title, string $message = '', string $eventType = '', array $context = []): int {
    ensureNotificationsSchema($db);
    $severity = in_array($severity, ['info','success','warning','error','critical'], true) ? $severity : 'info';
    $stmt = $db->prepare("INSERT INTO app_notifications(user_email, app_id, severity, title, message, event_type, context_json, is_read, created_at) VALUES(:u,:a,:s,:t,:m,:e,:c,0,:n)");
    $stmt->bindValue(':u', $userEmail, SQLITE3_TEXT);
    $stmt->bindValue(':a', $appId, SQLITE3_TEXT);
    $stmt->bindValue(':s', $severity, SQLITE3_TEXT);
    $stmt->bindValue(':t', $title, SQLITE3_TEXT);
    $stmt->bindValue(':m', $message, SQLITE3_TEXT);
    $stmt->bindValue(':e', $eventType, SQLITE3_TEXT);
    $stmt->bindValue(':c', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $stmt->bindValue(':n', time(), SQLITE3_INTEGER);
    $stmt->execute();
    if (in_array($severity, ['warning','error','critical'], true)) {
        $telegramEnabled = 1;
        if ($userEmail !== '') {
            $set = $db->prepare("SELECT telegram_enabled FROM app_notification_settings WHERE email = :u");
            $set->bindValue(':u', $userEmail, SQLITE3_TEXT);
            $setRow = $set->execute()->fetchArray(SQLITE3_ASSOC);
            $telegramEnabled = (int)($setRow['telegram_enabled'] ?? 1);
        }
        if ($telegramEnabled) {
        $label = $appId !== '' ? '[' . $appId . '] ' : '';
        @sendTelegramMessage(my_htmlspecialchars($label . $title . ($message !== '' ? "\n" . $message : '')));
        }
    }
    return (int)$db->lastInsertRowID();
}

// ── AI Config API ─────────────────────────────────────────────────────────
if (isset($_GET['api']) && $_GET['api'] === 'cloud_ai_config') {
    require_once APP_ROOT . '/apps/cloud/includes/ai_config_store.php';
    $config = cloud_ai_load_config();
    echo json_encode(['ok' => true, 'config' => $config]);
    exit;
}

if (isset($_GET['api']) && $_GET['api'] === 'privacy') {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $action = (string)($input['action'] ?? $_GET['action'] ?? '');
    $deviceId = getConsentDeviceId();
    $email = (string)($_SESSION['user']['email'] ?? '');
    if (PHP_SESSION_ACTIVE === session_status()) {
        session_write_close();
    }

    if ($action === 'cookie_consent_status') {
        if ($deviceId === '') json_out(['ok' => false, 'error' => 'missing_device_id'], 400);
        $record = getCookieConsentRecord($deviceId);
        json_out([
            'ok' => true,
            'device_id' => $deviceId,
            'has_record' => $record !== null,
            'status' => (string)($record['status'] ?? ''),
            'should_prompt' => $record === null,
        ]);
    }
    if ($action === 'cookie_consent_prompted') {
        if ($deviceId === '') json_out(['ok' => false, 'error' => 'missing_device_id'], 400);
        markCookieConsentPrompted($deviceId, $email);
        json_out(['ok' => true]);
    }
    if ($action === 'save_cookie_consent') {
        if ($deviceId === '') json_out(['ok' => false, 'error' => 'missing_device_id'], 400);
        $status = trim((string)($input['status'] ?? ''));
        if (!in_array($status, ['accepted', 'declined'], true)) {
            json_out(['ok' => false, 'error' => 'invalid_status'], 400);
        }
        saveCookieConsentDecision($deviceId, $status, $email);
        json_out(['ok' => true, 'status' => $status]);
    }
    json_out(['ok' => false, 'error' => 'unknown_action'], 400);
}
if (isset($_GET['api']) && $_GET['api'] === 'client_auth') {
    $db = getDB();
    ensureSupportChatSchema($db);
    if (!$db) json_out(['ok' => false, 'error' => 'db_unavailable'], 500);
    ensureClientAuthTokensSchema($db);
    ensureClientBrowserAuthSchema($db);
    purgeClientBrowserAuthRequests($db);
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $action = (string)($input['action'] ?? $_GET['action'] ?? '');
    if ($action === 'status') {
        $sessionOk = isset($_SESSION['user']['email'], $_SESSION['auth_time']) && getUserFromActiveSessions(session_id()) !== null;
        json_out(['ok' => true, 'authenticated' => $sessionOk, 'requires_login' => !$sessionOk, 'redirect' => '/main']);
    }
    if ($action === 'login') {
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $deviceId = trim((string)($input['device_id'] ?? ''));
        $deviceName = trim((string)($input['device_name'] ?? ''));
        $platform = trim((string)($input['platform'] ?? ''));
        if ($email === '' || $password === '') json_out(['ok' => false, 'error' => 'missing_credentials'], 400);
        $users = loadUsers();
        if (!isset($users[$email]) || !password_verify($password, $users[$email]['password'])) json_out(['ok' => false, 'error' => 'invalid_credentials'], 401);
        if ($email !== 'root@root' && empty($users[$email]['verified'])) json_out(['ok' => false, 'error' => 'email_not_verified'], 403);
        if (!loginUserSession($email, $users)) json_out(['ok' => false, 'error' => 'login_failed'], 500);
        $tokenData = createClientAuthToken($db, $email, $deviceId, $deviceName, $platform);
        json_out(['ok' => true, 'redirect' => '/main', 'token' => $tokenData['token'], 'expires_at' => $tokenData['expires_at'], 'user' => ['email' => $email, 'name' => $users[$email]['name'] ?? '']]);
    }
    if ($action === 'token_login') {
        $token = trim((string)($input['token'] ?? ''));
        $deviceId = trim((string)($input['device_id'] ?? ''));
        if ($token === '') json_out(['ok' => false, 'error' => 'missing_token'], 400);
        $stmt = $db->prepare("SELECT * FROM client_auth_tokens WHERE token_hash = :h AND revoked = 0 LIMIT 1");
        $stmt->bindValue(':h', hash('sha256', $token), SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) json_out(['ok' => false, 'error' => 'invalid_token'], 401);
        if ((int)$row['expires_at'] < time()) json_out(['ok' => false, 'error' => 'token_expired'], 401);
        if ($deviceId !== '' && (string)$row['device_id'] !== '' && $deviceId !== (string)$row['device_id']) json_out(['ok' => false, 'error' => 'device_mismatch'], 403);
        $users = loadUsers();
        $email = (string)($row['email'] ?? '');
        if (!loginUserSession($email, $users)) json_out(['ok' => false, 'error' => 'user_not_found'], 404);
        $upd = $db->prepare("UPDATE client_auth_tokens SET last_used_at = :u WHERE token_hash = :h");
        $upd->bindValue(':u', time(), SQLITE3_INTEGER);
        $upd->bindValue(':h', hash('sha256', $token), SQLITE3_TEXT);
        $upd->execute();
        json_out(['ok' => true, 'redirect' => '/main', 'user' => ['email' => $email, 'name' => $users[$email]['name'] ?? '']]);
    }
    if ($action === 'issue_from_session') {
        $sessionOk = clientAuthSessionOk();
        if (!$sessionOk) json_out(['ok' => false, 'error' => 'not_authenticated'], 401);
        $email = (string)$_SESSION['user']['email'];
        $users = loadUsers();
        if (!isset($users[$email])) json_out(['ok' => false, 'error' => 'user_not_found'], 404);
        $deviceId = trim((string)($input['device_id'] ?? ''));
        $deviceName = trim((string)($input['device_name'] ?? ''));
        $platform = trim((string)($input['platform'] ?? ''));
        $tokenData = createClientAuthToken($db, $email, $deviceId, $deviceName, $platform);
        json_out(['ok' => true, 'token' => $tokenData['token'], 'expires_at' => $tokenData['expires_at'], 'redirect' => '/main', 'user' => ['email' => $email, 'name' => $users[$email]['name'] ?? '']]);
    }
    if ($action === 'create_browser_login') {
        $deviceId = trim((string)($input['device_id'] ?? ''));
        $deviceName = trim((string)($input['device_name'] ?? ''));
        $platform = trim((string)($input['platform'] ?? ''));
        $callbackUrl = normalizeClientAuthCallbackUrl((string)($input['callback_url'] ?? ''));
        $requestData = createClientBrowserAuthRequest($db, $deviceId, $deviceName, $platform, $callbackUrl);
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $cfVisitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
        $scheme = 'http';
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $forwardedProto === 'https'
            || str_contains($cfVisitor, '"scheme":"https"')) {
            $scheme = 'https';
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'serverkherson.xyz';
        $browserUrl = $scheme . '://' . $host . '/client-auth?request_id=' . urlencode($requestData['request_id']) . '&browser_token=' . urlencode($requestData['browser_token']);
        json_out([
            'ok' => true,
            'request_id' => $requestData['request_id'],
            'poll_token' => $requestData['poll_token'],
            'browser_url' => $browserUrl,
            'expires_at' => $requestData['expires_at'],
        ]);
    }
    if ($action === 'poll_browser_login') {
        $requestId = trim((string)($input['request_id'] ?? $_GET['request_id'] ?? ''));
        $pollToken = trim((string)($input['poll_token'] ?? $_GET['poll_token'] ?? ''));
        if ($requestId === '' || $pollToken === '') json_out(['ok' => false, 'error' => 'missing_request'], 400);
        $row = getClientBrowserAuthRequest($db, $requestId);
        if (!$row || !clientBrowserAuthMatches($pollToken, (string)($row['poll_token_hash'] ?? ''))) {
            json_out(['ok' => false, 'error' => 'request_not_found'], 404);
        }
        if ((int)($row['expires_at'] ?? 0) < time()) {
            json_out(['ok' => true, 'status' => 'expired']);
        }
        json_out([
            'ok' => true,
            'status' => (string)($row['status'] ?? 'pending'),
            'approved' => (string)($row['status'] ?? '') === 'approved',
            'claimed' => (string)($row['status'] ?? '') === 'claimed',
        ]);
    }
    if ($action === 'claim_browser_login') {
        $requestId = trim((string)($input['request_id'] ?? ''));
        $pollToken = trim((string)($input['poll_token'] ?? ''));
        $deviceId = trim((string)($input['device_id'] ?? ''));
        if ($requestId === '' || $pollToken === '') json_out(['ok' => false, 'error' => 'missing_request'], 400);
        $row = getClientBrowserAuthRequest($db, $requestId);
        if (!$row || !clientBrowserAuthMatches($pollToken, (string)($row['poll_token_hash'] ?? ''))) {
            json_out(['ok' => false, 'error' => 'request_not_found'], 404);
        }
        if ((int)($row['expires_at'] ?? 0) < time()) json_out(['ok' => false, 'error' => 'request_expired'], 410);
        if ($deviceId !== '' && (string)($row['device_id'] ?? '') !== '' && $deviceId !== (string)$row['device_id']) {
            json_out(['ok' => false, 'error' => 'device_mismatch'], 403);
        }
        $status = (string)($row['status'] ?? '');
        if ($status === 'pending') json_out(['ok' => true, 'status' => 'pending']);
        if ($status === 'claimed') json_out(['ok' => false, 'error' => 'already_claimed'], 409);
        if ($status !== 'approved') json_out(['ok' => false, 'error' => 'request_invalid'], 409);
        $email = (string)($row['email'] ?? '');
        $users = loadUsers();
        if ($email === '' || !isset($users[$email])) json_out(['ok' => false, 'error' => 'user_not_found'], 404);
        $token = (string)($row['issued_token'] ?? '');
        if ($token === '') json_out(['ok' => false, 'error' => 'token_missing'], 409);
        $upd = $db->prepare("UPDATE client_auth_browser_requests SET status = 'claimed', claimed_at = :t, issued_token = '' WHERE request_id = :r");
        $upd->bindValue(':t', time(), SQLITE3_INTEGER);
        $upd->bindValue(':r', $requestId, SQLITE3_TEXT);
        $upd->execute();
        json_out([
            'ok' => true,
            'status' => 'approved',
            'token' => $token,
            'redirect' => '/main',
            'user' => ['email' => $email, 'name' => $users[$email]['name'] ?? ''],
        ]);
    }
    if ($action === 'approve_browser_login') {
        if (!clientAuthSessionOk()) json_out(['ok' => false, 'error' => 'not_authenticated'], 401);
        $requestId = trim((string)($input['request_id'] ?? ''));
        $browserToken = trim((string)($input['browser_token'] ?? ''));
        if ($requestId === '' || $browserToken === '') json_out(['ok' => false, 'error' => 'missing_request'], 400);
        $row = getClientBrowserAuthRequest($db, $requestId);
        if (!$row || !clientBrowserAuthMatches($browserToken, (string)($row['browser_token_hash'] ?? ''))) {
            json_out(['ok' => false, 'error' => 'request_not_found'], 404);
        }
        if ((int)($row['expires_at'] ?? 0) < time()) json_out(['ok' => false, 'error' => 'request_expired'], 410);
        $status = (string)($row['status'] ?? '');
        if ($status === 'claimed') json_out(['ok' => false, 'error' => 'already_claimed'], 409);
        if ($status !== 'pending' && $status !== 'approved') json_out(['ok' => false, 'error' => 'request_invalid'], 409);
        $email = (string)$_SESSION['user']['email'];
        $users = loadUsers();
        if (!isset($users[$email])) json_out(['ok' => false, 'error' => 'user_not_found'], 404);
        $tokenData = createClientAuthToken($db, $email, (string)($row['device_id'] ?? ''), (string)($row['device_name'] ?? ''), (string)($row['platform'] ?? ''));
        $upd = $db->prepare("UPDATE client_auth_browser_requests SET status = 'approved', email = :e, issued_token = :t, approved_at = :a WHERE request_id = :r");
        $upd->bindValue(':e', $email, SQLITE3_TEXT);
        $upd->bindValue(':t', $tokenData['token'], SQLITE3_TEXT);
        $upd->bindValue(':a', time(), SQLITE3_INTEGER);
        $upd->bindValue(':r', $requestId, SQLITE3_TEXT);
        $upd->execute();
        $redirectUrl = '';
        $callbackUrl = trim((string)($row['callback_url'] ?? ''));
        if ($callbackUrl !== '') {
            $sep = str_contains($callbackUrl, '?') ? '&' : '?';
            $redirectUrl = $callbackUrl . $sep . http_build_query(['request_id' => $requestId, 'status' => 'approved']);
        }
        json_out(['ok' => true, 'status' => 'approved', 'redirect_url' => $redirectUrl]);
    }
    if ($action === 'logout_device') {
        $token = trim((string)($input['token'] ?? ''));
        if ($token !== '') {
            $stmt = $db->prepare("UPDATE client_auth_tokens SET revoked = 1 WHERE token_hash = :h");
            $stmt->bindValue(':h', hash('sha256', $token), SQLITE3_TEXT);
            $stmt->execute();
        }
        json_out(['ok' => true]);
    }
    // ── Trusted Devices API ──
    if ($action === 'add_trusted_device') {
        $sessionOk = isset($_SESSION['user']['email'], $_SESSION['auth_time']) && getUserFromActiveSessions(session_id()) !== null;
        if (!$sessionOk) json_out(['ok' => false, 'error' => 'not_authenticated'], 401);
        $email = (string)$_SESSION['user']['email'];
        $ip = getClientIp();
        $fp = getDeviceFingerprint();
        $deviceName = trim((string)($input['device_name'] ?? ''));
        if ($deviceName === '') {
            $session = getUserFromActiveSessions(session_id());
            $deviceName = ($session['device'] ?? '') . ' / ' . ($session['browser'] ?? '');
        }
        $tokenData = addTrustedDevice($email, $ip, $deviceName, $fp);
        $rawCookieToken = createTrustedDeviceCookie($email, $fp, $deviceName);
        setTrustedDeviceCookieForEmail($email, $rawCookieToken);
        rememberTrustedDeviceBrowserAccount($email);
        setTrustedDeviceHintCookie($email, $fp);
        $encryptedTokenForStorage = $tokenData['encrypted_token'];
        json_out([
            'ok' => true,
            'ip' => $ip,
            'device_name' => $deviceName,
            'device_token' => $tokenData['token'],
            'encrypted_token' => $encryptedTokenForStorage
        ]);
    }
    if ($action === 'remove_trusted_device') {
        $sessionOk = isset($_SESSION['user']['email'], $_SESSION['auth_time']) && getUserFromActiveSessions(session_id()) !== null;
        if (!$sessionOk) json_out(['ok' => false, 'error' => 'not_authenticated'], 401);
        $email = (string)$_SESSION['user']['email'];
        $fp = trim((string)($input['fingerprint'] ?? ''));
        $ip = trim((string)($input['ip'] ?? ''));
        $token = trim((string)($input['token'] ?? ''));
        if ($fp === '' && $ip === '' && $token === '') {
            json_out(['ok' => false, 'error' => 'missing_identifier'], 400);
        }
        if ($token !== '' && $fp === '') {
            $decryptedToken = decryptTrustedDeviceToken($token);
            if ($decryptedToken !== null) {
                $devices = getUserTrustedDevices($email);
                foreach ($devices as $d) {
                    if (isset($d['device_token'])) {
                        $storedToken = decryptTrustedDeviceToken($d['device_token']);
                        if ($storedToken === $decryptedToken) {
                            $fp = $d['fingerprint'] ?? '';
                            break;
                        }
                    }
                }
            }
        }
        if ($fp === '' && $ip !== '') {
            $devices = getUserTrustedDevices($email);
            foreach ($devices as $d) {
                if (($d['ip'] ?? '') === $ip) {
                    $fp = $d['fingerprint'] ?? '';
                    break;
                }
            }
        }
        if ($fp === '') {
            json_out(['ok' => false, 'error' => 'device_not_found'], 404);
        }
        removeTrustedDevice($email, $fp);
        if ($fp === getDeviceFingerprint()) {
            clearTrustedDeviceCookieForEmail($email);
            forgetTrustedDeviceBrowserAccount($email);
            $manifest = parseTrustedDeviceManifestCookie((string)($_COOKIE['trusted_device_manifest'] ?? ''));
            $nextActiveEmail = trim((string)($manifest['active_email'] ?? ''));
            if ($nextActiveEmail !== '') {
                setTrustedDeviceHintCookie($nextActiveEmail, getDeviceFingerprint());
            } else {
                clearTrustedDeviceHintCookie();
            }
        }
        json_out(['ok' => true]);
    }
    if ($action === 'list_trusted_devices') {
        $sessionOk = isset($_SESSION['user']['email'], $_SESSION['auth_time']) && getUserFromActiveSessions(session_id()) !== null;
        if (!$sessionOk) json_out(['ok' => false, 'error' => 'not_authenticated'], 401);
        $email = (string)$_SESSION['user']['email'];
        $devices = getUserTrustedDevices($email);
        $currentIp = getClientIp();
        $currentFp = getDeviceFingerprint();
        json_out(['ok' => true, 'devices' => $devices, 'current_ip' => $currentIp, 'current_fingerprint' => $currentFp, 'is_current_trusted' => isTrustedDevice($email, $currentFp)]);
    }
    json_out(['ok' => false, 'error' => 'unknown_action'], 400);
}

if (isset($_GET['api']) && $_GET['api'] === 'notifications') { try {
    $sessionOk = isset($_SESSION['user']['email'], $_SESSION['auth_time']) && getUserFromActiveSessions(session_id()) !== null;
    if (!$sessionOk) json_out(['ok' => false, 'error' => 'not_authenticated'], 401);
    $db = getDB();
    ensureNotificationsSchema($db);
    $userEmail = (string)$_SESSION['user']['email'];
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $action = (string)($input['action'] ?? $_GET['action'] ?? '');
    if (PHP_SESSION_ACTIVE === session_status()) {
        session_write_close();
    }
    if ($action === 'list') {
        $limit = max(1, min(100, (int)($_GET['limit'] ?? $input['limit'] ?? 30)));
        $stmt = $db->prepare("SELECT id,user_email,app_id,severity,title,message,event_type,is_read,created_at FROM app_notifications WHERE user_email = '' OR user_email = :u ORDER BY created_at DESC, id DESC LIMIT :l");
        $stmt->bindValue(':u', $userEmail, SQLITE3_TEXT);
        $stmt->bindValue(':l', $limit, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $items = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $items[] = [
                'id' => (int)$row['id'],
                'app_id' => (string)$row['app_id'],
                'severity' => (string)$row['severity'],
                'title' => (string)$row['title'],
                'message' => (string)$row['message'],
                'event_type' => (string)$row['event_type'],
                'is_read' => (int)$row['is_read'],
                'created_at' => (int)$row['created_at'],
            ];
        }
        $cnt = $db->prepare("SELECT COUNT(*) AS c FROM app_notifications WHERE (user_email = '' OR user_email = :u) AND is_read = 0");
        $cnt->bindValue(':u', $userEmail, SQLITE3_TEXT);
        $countRow = $cnt->execute()->fetchArray(SQLITE3_ASSOC);
        json_out(['ok' => true, 'items' => $items, 'unread' => (int)($countRow['c'] ?? 0)]);
    }
    if ($action === 'mark_read') {
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE app_notifications SET is_read = 1, read_at = :t WHERE id = :id AND (user_email = '' OR user_email = :u)");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':u', $userEmail, SQLITE3_TEXT);
            $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("UPDATE app_notifications SET is_read = 1, read_at = :t WHERE (user_email = '' OR user_email = :u) AND is_read = 0");
            $stmt->bindValue(':u', $userEmail, SQLITE3_TEXT);
            $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
            $stmt->execute();
        }
        json_out(['ok' => true]);
    }
    if ($action === 'settings') {
        $stmt = $db->prepare("SELECT browser_enabled, telegram_enabled FROM app_notification_settings WHERE email = :u");
        $stmt->bindValue(':u', $userEmail, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        json_out(['ok' => true, 'settings' => ['browser_enabled' => (int)($row['browser_enabled'] ?? 0), 'telegram_enabled' => (int)($row['telegram_enabled'] ?? 1)]]);
    }
    if ($action === 'save_settings') {
        $browser = !empty($input['browser_enabled']) ? 1 : 0;
        $telegram = array_key_exists('telegram_enabled', $input) ? (!empty($input['telegram_enabled']) ? 1 : 0) : 1;
        $stmt = $db->prepare("INSERT INTO app_notification_settings(email,browser_enabled,telegram_enabled,updated_at) VALUES(:u,:b,:g,:t) ON CONFLICT(email) DO UPDATE SET browser_enabled = excluded.browser_enabled, telegram_enabled = excluded.telegram_enabled, updated_at = excluded.updated_at");
        $stmt->bindValue(':u', $userEmail, SQLITE3_TEXT);
        $stmt->bindValue(':b', $browser, SQLITE3_INTEGER);
        $stmt->bindValue(':g', $telegram, SQLITE3_INTEGER);
        $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
        $stmt->execute();
        json_out(['ok' => true]);
    }
    if ($action === 'metric') {
        $appId = trim((string)($input['app_id'] ?? ''));
        $metric = trim((string)($input['metric'] ?? ''));
        if ($metric === '') json_out(['ok' => false, 'error' => 'missing_metric'], 400);
        $stmt = $db->prepare("INSERT INTO app_metrics(app_id, metric, value, unit, user_email, context_json, created_at) VALUES(:a,:m,:v,:unit,:u,:c,:t)");
        $stmt->bindValue(':a', $appId, SQLITE3_TEXT);
        $stmt->bindValue(':m', $metric, SQLITE3_TEXT);
        $stmt->bindValue(':v', (float)($input['value'] ?? 0), SQLITE3_FLOAT);
        $stmt->bindValue(':unit', trim((string)($input['unit'] ?? '')), SQLITE3_TEXT);
        $stmt->bindValue(':u', $userEmail, SQLITE3_TEXT);
        $stmt->bindValue(':c', json_encode(is_array($input['context'] ?? null) ? $input['context'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
        $stmt->execute();
        json_out(['ok' => true]);
    }
    if ($action === 'emit' || $action === 'worker_finished') {
        $appId = trim((string)($input['app_id'] ?? ''));
        $eventType = $action === 'worker_finished' ? 'worker_finished' : trim((string)($input['event_type'] ?? ''));
        $severity = $action === 'worker_finished' ? 'warning' : trim((string)($input['severity'] ?? 'info'));
        $title = trim((string)($input['title'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));
        if ($title === '') $title = $eventType === 'worker_finished' ? 'Worker finished' : 'Notification';
        $id = apCreateNotification($db, $userEmail, $appId, $severity, $title, $message, $eventType, is_array($input['context'] ?? null) ? $input['context'] : []);
        json_out(['ok' => true, 'id' => $id]);
    }
    json_out(['ok' => false, 'error' => 'unknown_action'], 400);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}}

// ── Support Chat API ─────────────────────────────────────────────────────────
if (isset($_GET['api']) && $_GET['api'] === 'support_chat') { try {
    $sessionOk = isset($_SESSION['user']['email'], $_SESSION['auth_time']) && getUserFromActiveSessions(session_id()) !== null;
    if (!$sessionOk) json_out(['ok' => false, 'error' => 'not_authenticated'], 401);

    $db = getDB();
    $userEmail = (string)$_SESSION['user']['email'];
    $userRole = (int)($_SESSION['user']['role'] ?? 0);
    $isAdmin = $userRole >= 2;
    if (PHP_SESSION_ACTIVE === session_status()) {
        session_write_close();
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'get_unread_count') {
        if ($isAdmin) {
            $stmt = $db->prepare('SELECT COUNT(*) AS c FROM support_chat WHERE is_read = 0 AND sender_email != :me');
            $stmt->bindValue(':me', $userEmail, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            json_out(['ok' => true, 'count' => (int)($row['c'] ?? 0)]);
        }
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM support_chat WHERE user_email = :u AND is_read = 0 AND sender_email != :u2');
        $stmt->bindValue(':u', $userEmail, SQLITE3_TEXT);
        $stmt->bindValue(':u2', $userEmail, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        json_out(['ok' => true, 'count' => (int)($row['c'] ?? 0)]);
    }

    // Get messages (chunked: initial latest N, before_id=older, since_id=newer for poll, full=1 up to 500)
    if ($action === 'get_messages') {
        $chatUserEmail = $isAdmin ? ($_GET['user_email'] ?? $_POST['user_email'] ?? '') : $userEmail;
        if ($chatUserEmail === '') json_out(['ok' => false, 'error' => 'missing_user_email'], 400);

        $mapRow = static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'user_email' => $row['user_email'],
                'sender_email' => $row['sender_email'],
                'message' => $row['message'],
                'media_path' => $row['media_path'],
                'media_type' => $row['media_type'],
                'is_deleted' => (bool)$row['is_deleted'],
                'is_edited' => (bool)$row['is_edited'],
                'is_read' => (bool)$row['is_read'],
                'created_at' => (int)$row['created_at'],
                'edited_at' => $row['edited_at'] ? (int)$row['edited_at'] : null,
            ];
        };

        if ((int)($_GET['full'] ?? 0) === 1) {
            $stmt = $db->prepare('SELECT id, user_email, sender_email, message, media_path, media_type, is_deleted, is_edited, is_read, created_at, edited_at
                FROM support_chat WHERE user_email = :email ORDER BY created_at ASC LIMIT 500');
            $stmt->bindValue(':email', $chatUserEmail, SQLITE3_TEXT);
            $result = $stmt->execute();
            $messages = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $messages[] = $mapRow($row);
            }
            json_out(['ok' => true, 'messages' => $messages, 'mode' => 'full', 'has_more_older' => false, 'min_id' => null, 'max_id' => null]);
        }

        $sinceId = (int)($_GET['since_id'] ?? 0);
        if ($sinceId > 0) {
            $stmt = $db->prepare('SELECT id, user_email, sender_email, message, media_path, media_type, is_deleted, is_edited, is_read, created_at, edited_at
                FROM support_chat WHERE user_email = :email AND id > :sid ORDER BY created_at ASC LIMIT 100');
            $stmt->bindValue(':email', $chatUserEmail, SQLITE3_TEXT);
            $stmt->bindValue(':sid', $sinceId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $messages = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $messages[] = $mapRow($row);
            }
            json_out(['ok' => true, 'messages' => $messages, 'mode' => 'delta']);
        }

        $chunk = min(100, max(10, (int)($_GET['limit'] ?? 30)));
        $beforeId = (int)($_GET['before_id'] ?? 0);

        if ($beforeId > 0) {
            $stmt = $db->prepare('SELECT id, user_email, sender_email, message, media_path, media_type, is_deleted, is_edited, is_read, created_at, edited_at
                FROM support_chat WHERE user_email = :email AND id < :bid ORDER BY created_at DESC LIMIT :lim');
            $stmt->bindValue(':email', $chatUserEmail, SQLITE3_TEXT);
            $stmt->bindValue(':bid', $beforeId, SQLITE3_INTEGER);
            $stmt->bindValue(':lim', $chunk, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $rows = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $mapRow($row);
            }
            $messages = array_reverse($rows);
            $minId = null;
            foreach ($messages as $m) {
                $minId = $minId === null ? $m['id'] : min($minId, $m['id']);
            }
            $hasMore = false;
            if ($minId !== null) {
                $cstmt = $db->prepare('SELECT COUNT(*) AS c FROM support_chat WHERE user_email = :e AND id < :mid');
                $cstmt->bindValue(':e', $chatUserEmail, SQLITE3_TEXT);
                $cstmt->bindValue(':mid', $minId, SQLITE3_INTEGER);
                $crow = $cstmt->execute()->fetchArray(SQLITE3_ASSOC);
                $hasMore = $crow && (int)$crow['c'] > 0;
            }
            json_out(['ok' => true, 'messages' => $messages, 'mode' => 'older', 'has_more_older' => $hasMore, 'min_id' => $minId]);
        }

        $stmt = $db->prepare('SELECT id, user_email, sender_email, message, media_path, media_type, is_deleted, is_edited, is_read, created_at, edited_at
            FROM support_chat WHERE user_email = :email ORDER BY created_at DESC LIMIT :lim');
        $stmt->bindValue(':email', $chatUserEmail, SQLITE3_TEXT);
        $stmt->bindValue(':lim', $chunk, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $mapRow($row);
        }
        $messages = array_reverse($rows);
        $minId = null;
        $maxId = null;
        foreach ($messages as $m) {
            $minId = $minId === null ? $m['id'] : min($minId, $m['id']);
            $maxId = $maxId === null ? $m['id'] : max($maxId, $m['id']);
        }
        $hasMore = false;
        if ($minId !== null) {
            $cstmt = $db->prepare('SELECT COUNT(*) AS c FROM support_chat WHERE user_email = :e AND id < :mid');
            $cstmt->bindValue(':e', $chatUserEmail, SQLITE3_TEXT);
            $cstmt->bindValue(':mid', $minId, SQLITE3_INTEGER);
            $crow = $cstmt->execute()->fetchArray(SQLITE3_ASSOC);
            $hasMore = $crow && (int)$crow['c'] > 0;
        }
        json_out(['ok' => true, 'messages' => $messages, 'mode' => 'initial', 'has_more_older' => $hasMore, 'min_id' => $minId, 'max_id' => $maxId]);
    }

    // Send message
    if ($action === 'send_message') {
        $message = trim((string)($_POST['message'] ?? ''));
        $chatUserEmail = $isAdmin ? ($_POST['user_email'] ?? '') : $userEmail;

        if ($chatUserEmail === '') json_out(['ok' => false, 'error' => 'missing_user_email'], 400);
        if ($message === '' && empty($_FILES['media'])) json_out(['ok' => false, 'error' => 'empty_message'], 400);

        $mediaPath = null;
        $mediaType = null;

        if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $stored = apSecStoreSupportChatMedia($_FILES['media']);
            if (isset($stored['error'])) json_out(['ok' => false, 'error' => $stored['error']], 400);
            $mediaPath = $stored['path'];
            $mediaType = $stored['type'];
        }

        $stmt = $db->prepare("INSERT INTO support_chat (user_email, sender_email, message, media_path, media_type, created_at)
                              VALUES (:user_email, :sender_email, :message, :media_path, :media_type, :created_at)");
        $stmt->bindValue(':user_email', $chatUserEmail, SQLITE3_TEXT);
        $stmt->bindValue(':sender_email', $userEmail, SQLITE3_TEXT);
        $stmt->bindValue(':message', $message, SQLITE3_TEXT);
        $stmt->bindValue(':media_path', $mediaPath, $mediaPath ? SQLITE3_TEXT : SQLITE3_NULL);
        $stmt->bindValue(':media_type', $mediaType, $mediaType ? SQLITE3_TEXT : SQLITE3_NULL);
        $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
        $stmt->execute();

        $messageId = $db->lastInsertRowID();

        if (!$isAdmin) {
            $checkStmt = $db->prepare('SELECT COUNT(*) AS cnt FROM support_chat WHERE user_email = :email AND sender_email = :email');
            $checkStmt->bindValue(':email', $userEmail, SQLITE3_TEXT);
            $checkResult = $checkStmt->execute()->fetchArray(SQLITE3_ASSOC);

            if ($checkResult && (int)$checkResult['cnt'] <= 1) {
                $preview = trim($message);
                if ($preview === '' && $mediaPath) {
                    $preview = '[media]';
                }
                $preview = preg_replace('/\s+/u', ' ', $preview);
                $previewShort = function_exists('mb_substr') ? mb_substr($preview, 0, 140) : substr($preview, 0, 140);
                if ($preview !== '' && strlen($preview) > strlen($previewShort)) {
                    $previewShort .= '…';
                }
                $tgBody = '🔔 <b>New support message</b>' . "\n" . '<code>' . htmlspecialchars($userEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
                if ($previewShort !== '') {
                    $tgBody .= "\n\n" . htmlspecialchars($previewShort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
                sendTelegramMessage($tgBody, getTelegramDomain());
            }
        }

        json_out(['ok' => true, 'message_id' => $messageId]);
    }

    // Delete message
    if ($action === 'delete_message') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        $messageId = (int)($input['message_id'] ?? 0);
        if ($messageId <= 0) json_out(['ok' => false, 'error' => 'invalid_message_id'], 400);

        // Check if user has permission to delete
        $stmt = $db->prepare("SELECT sender_email FROM support_chat WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $messageId, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$row) json_out(['ok' => false, 'error' => 'message_not_found'], 404);
        if (!$isAdmin && $row['sender_email'] !== $userEmail) {
            json_out(['ok' => false, 'error' => 'permission_denied'], 403);
        }

        // Mark as deleted
        $stmt = $db->prepare("UPDATE support_chat SET is_deleted = 1 WHERE id = :id");
        $stmt->bindValue(':id', $messageId, SQLITE3_INTEGER);
        $stmt->execute();

        json_out(['ok' => true]);
    }

    // Edit message
    if ($action === 'edit_message') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        $newMessage = trim((string)($_POST['message'] ?? ''));

        if ($messageId <= 0) json_out(['ok' => false, 'error' => 'invalid_message_id'], 400);

        // Check if user has permission to edit
        $stmt = $db->prepare('SELECT sender_email, media_path, media_type FROM support_chat WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $messageId, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$row) json_out(['ok' => false, 'error' => 'message_not_found'], 404);
        if ($row['sender_email'] !== $userEmail) {
            json_out(['ok' => false, 'error' => 'permission_denied'], 403);
        }

        $hadMedia = !empty($row['media_path']);
        if ($newMessage === '' && empty($_FILES['media']) && !$hadMedia) {
            json_out(['ok' => false, 'error' => 'empty_message'], 400);
        }

        $mediaPath = $row['media_path'];
        $existingType = trim((string)($row['media_type'] ?? ''));
        $mediaType = $existingType !== '' ? $existingType : null;

        if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $stored = apSecStoreSupportChatMedia($_FILES['media']);
            if (isset($stored['error'])) json_out(['ok' => false, 'error' => $stored['error']], 400);
            if ($mediaPath && file_exists(APP_ROOT . '/' . $mediaPath)) {
                @unlink(APP_ROOT . '/' . $mediaPath);
            }
            $mediaPath = $stored['path'];
            $mediaType = $stored['type'];
        }

        $bindType = $mediaType;
        if ($bindType === null && $mediaPath) {
            $ext = strtolower(pathinfo((string)$mediaPath, PATHINFO_EXTENSION));
            $bindType = in_array($ext, ['mp4', 'webm', 'mov', 'ogg'], true) ? 'video' : 'image';
        }

        // Update message
        $stmt = $db->prepare('UPDATE support_chat SET message = :msg, is_edited = 1, edited_at = :edited, media_path = :mp, media_type = :mt WHERE id = :id');
        $stmt->bindValue(':msg', $newMessage, SQLITE3_TEXT);
        $stmt->bindValue(':edited', time(), SQLITE3_INTEGER);
        $stmt->bindValue(':mp', $mediaPath, $mediaPath ? SQLITE3_TEXT : SQLITE3_NULL);
        $stmt->bindValue(':mt', $bindType, $bindType ? SQLITE3_TEXT : SQLITE3_NULL);
        $stmt->bindValue(':id', $messageId, SQLITE3_INTEGER);
        $stmt->execute();

        json_out(['ok' => true]);
    }

    // Mark messages as read
    if ($action === 'mark_read') {
        $chatUserEmail = $isAdmin ? ($_GET['user_email'] ?? $_POST['user_email'] ?? '') : $userEmail;
        if ($chatUserEmail === '') json_out(['ok' => false, 'error' => 'missing_user_email'], 400);

        // Mark all messages in this chat as read (except own messages)
        $stmt = $db->prepare("UPDATE support_chat SET is_read = 1
                              WHERE user_email = :user_email
                              AND sender_email != :current_user
                              AND is_read = 0");
        $stmt->bindValue(':user_email', $chatUserEmail, SQLITE3_TEXT);
        $stmt->bindValue(':current_user', $userEmail, SQLITE3_TEXT);
        $stmt->execute();

        json_out(['ok' => true]);
    }

    // Read text file content
    if ($action === 'read_file') {
        $filePath = $_GET['file'] ?? '';
        if ($filePath === '') json_out(['ok' => false, 'error' => 'missing_file'], 400);

        // Security: only allow files from support_chat directory
        if (strpos($filePath, 'uploads/support_chat/') !== 0) {
            json_out(['ok' => false, 'error' => 'invalid_path'], 403);
        }

        $fullPath = APP_ROOT . '/' . $filePath;
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            json_out(['ok' => false, 'error' => 'file_not_found'], 404);
        }

        // Check if user has access to this file (file must be in a message they can see)
        $fileName = basename($filePath);
        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM support_chat WHERE media_path = :path AND (user_email = :email OR sender_email = :email2)');
        $stmt->bindValue(':path', $filePath, SQLITE3_TEXT);
        $stmt->bindValue(':email', $userEmail, SQLITE3_TEXT);
        $stmt->bindValue(':email2', $userEmail, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$isAdmin && (!$row || (int)$row['cnt'] === 0)) {
            json_out(['ok' => false, 'error' => 'access_denied'], 403);
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            json_out(['ok' => false, 'error' => 'read_failed'], 500);
        }

        json_out(['ok' => true, 'content' => $content]);
    }

    // Get chat list (admin only)
    if ($action === 'get_chat_list') {
        if (!$isAdmin) json_out(['ok' => false, 'error' => 'permission_denied'], 403);

        $stmt = $db->query("SELECT * FROM (SELECT DISTINCT sc.user_email,
                            (SELECT MAX(COALESCE(edited_at, created_at)) FROM support_chat t WHERE t.user_email = sc.user_email AND COALESCE(t.is_deleted,0)=0) AS last_message_time,
                            (SELECT COUNT(*) FROM support_chat t2 WHERE t2.user_email = sc.user_email AND COALESCE(t2.is_deleted,0)=0) AS message_count,
                            (SELECT role FROM users WHERE email = sc.user_email LIMIT 1) as user_role,
                            (SELECT COALESCE(name, '') FROM users WHERE email = sc.user_email LIMIT 1) as user_name
                            FROM support_chat sc
                            ) chat_rows ORDER BY CASE WHEN last_message_time IS NULL THEN 1 ELSE 0 END ASC, last_message_time DESC");
        if (!$stmt) json_out(['ok' => false, 'error' => 'db_query_failed'], 500);

        $chats = [];
        while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
            $chats[] = [
                'user_email' => $row['user_email'],
                'user_name' => (string)($row['user_name'] ?? ''),
                'last_message_time' => (int)$row['last_message_time'],
                'message_count' => (int)$row['message_count'],
                'user_role' => (int)($row['user_role'] ?? 0),
            ];
        }

        json_out(['ok' => true, 'chats' => $chats]);
    }

    // Delete entire chat (admin only)
    if ($action === 'delete_chat') {
        if (!$isAdmin) json_out(['ok' => false, 'error' => 'permission_denied'], 403);

        $chatUserEmail = $_POST['user_email'] ?? '';
        if ($chatUserEmail === '') json_out(['ok' => false, 'error' => 'missing_user_email'], 400);

        // Delete all messages for this user
        $stmt = $db->prepare("DELETE FROM support_chat WHERE user_email = :email");
        $stmt->bindValue(':email', $chatUserEmail, SQLITE3_TEXT);
        $stmt->execute();

        json_out(['ok' => true]);
    }

    // Check if user exists (admin only)
    if ($action === 'check_user') {
        if (!$isAdmin) json_out(['ok' => false, 'error' => 'permission_denied'], 403);

        $email = $_GET['email'] ?? '';
        if ($email === '') json_out(['ok' => false, 'error' => 'missing_email'], 400);

        $stmt = $db->prepare("SELECT role FROM users WHERE email = :email LIMIT 1");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($row) {
            $role = (int)$row['role'];
            json_out(['ok' => true, 'exists' => true, 'is_admin' => $role >= 2]);
        } else {
            json_out(['ok' => true, 'exists' => false, 'is_admin' => false]);
        }
    }

    if ($action === 'search_users') {
        if (!$isAdmin) {
            json_out(['ok' => false, 'error' => 'permission_denied'], 403);
        }
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            $result = $db->query("SELECT email, name, role FROM users WHERE role < 2 ORDER BY lower(email) LIMIT 40");
        } else {
            $esc = static function (string $s): string {
                return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
            };
            $term = '%' . $esc($q) . '%';
            $stmt = $db->prepare("SELECT email, name, role FROM users WHERE role < 2 AND (email LIKE :t ESCAPE '\\' OR COALESCE(name, '') LIKE :t ESCAPE '\\') ORDER BY lower(email) LIMIT 40");
            $stmt->bindValue(':t', $term, SQLITE3_TEXT);
            $result = $stmt->execute();
        }
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = [
                'email' => $row['email'],
                'name' => $row['name'] ?? '',
                'role' => (int)($row['role'] ?? 0),
            ];
        }
        json_out(['ok' => true, 'users' => $users]);
    }

    json_out(['ok' => false, 'error' => 'unknown_action'], 400);
} catch (\Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
}

require_once __DIR__ . '/i18n_helpers.php';

/** Per-language JSON in i18n/{lang}.json — only the active locale is loaded per request. */
function ap_load_i18n_lang(string $lang): array {
    static $cache = [];
    $lang = ap_normalize_i18n_lang($lang);
    if (array_key_exists($lang, $cache)) {
        return $cache[$lang];
    }
    $safe = preg_replace('/[^a-z0-9_-]/i', '', $lang);
    if ($safe === '') {
        return $cache[$lang] = [];
    }
    $path = APP_ROOT . '/i18n/' . $safe . '.json';
    if (!is_readable($path)) {
        return $cache[$lang] = [];
    }
    $data = json_decode((string)file_get_contents($path), true);
    return $cache[$lang] = is_array($data) ? $data : [];
}
function ap_i18n_lang_exists(string $lang): bool {
    $lang = ap_normalize_i18n_lang($lang);
    $safe = preg_replace('/[^a-z0-9_-]/i', '', $lang);
    if ($safe === '') {
        return false;
    }
    return is_readable(APP_ROOT . '/i18n/' . $safe . '.json');
}
function ap_merge_i18n(array $defaultTranslations, string $language): array {
    return array_map(static fn($v) => $v ?? '', array_merge($defaultTranslations, ap_load_i18n_lang($language)));
}

require_once __DIR__ . '/wallpapers.php';
$defaultConfig=['language'=>'en','theme'=>'light','color'=>'#007bff','icon_sort_order'=>'alpha','icon_style'=>'animated','wallpaper'=>'default'];
$config=$defaultConfig;
$allowedWallpapers=ap_allowed_wallpapers();

$themeFromUrl = $_GET['theme'] ?? null;
$langFromUrl = $_GET['lang'] ?? null;
$colorFromUrl = $_GET['color'] ?? null;
$requestPathForConfig = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isAuthConfigPage = in_array($requestPathForConfig, ['/login', '/register'], true) || (isset($_GET['code']) && strpos($requestPathForConfig, '/login') !== false);

$cookieConfig = ap_read_pref_cookies($defaultConfig);
if($isAuthConfigPage&&!isset($_SESSION['user']['email'])){
    $config = array_merge($config, $cookieConfig, ['wallpaper' => 'default']);
}

$_lastActiveEmail = $isAuthConfigPage ? null : getLastActiveUser();
if($_lastActiveEmail){
    $users=loadUsers();
    if(isset($users[$_lastActiveEmail])){
        $userData=$users[$_lastActiveEmail];
        $config=['language'=>$userData['language']??$defaultConfig['language'],'theme'=>$userData['theme']??$defaultConfig['theme'],'color'=>$userData['color']??$defaultConfig['color'],'icon_sort_order'=>$userData['icon_sort_order']??$defaultConfig['icon_sort_order'],'icon_style'=>$userData['icon_style']??$defaultConfig['icon_style'],'wallpaper'=>$userData['wallpaper']??$defaultConfig['wallpaper']];
    }
}

if(isset($_SESSION['user']['email'])){
    $userEmail=$_SESSION['user']['email'];
    if($isAuthConfigPage){
        $config=['language'=>$_SESSION['user']['language']??$defaultConfig['language'],'theme'=>$_SESSION['user']['theme']??$defaultConfig['theme'],'color'=>$_SESSION['user']['color']??$defaultConfig['color'],'icon_sort_order'=>$_SESSION['user']['icon_sort_order']??$defaultConfig['icon_sort_order'],'icon_style'=>$_SESSION['user']['icon_style']??$defaultConfig['icon_style'],'wallpaper'=>'default'];
    }else{
        $users=loadUsers();
        if(isset($users[$userEmail])){
            $config=['language'=>$users[$userEmail]['language']??$defaultConfig['language'],'theme'=>$users[$userEmail]['theme']??$defaultConfig['theme'],'color'=>$users[$userEmail]['color']??$defaultConfig['color'],'icon_sort_order'=>$users[$userEmail]['icon_sort_order']??$defaultConfig['icon_sort_order'],'icon_style'=>$users[$userEmail]['icon_style']??$defaultConfig['icon_style'],'wallpaper'=>$users[$userEmail]['wallpaper']??$defaultConfig['wallpaper']];
            setLastActiveUser($userEmail);
            ap_write_pref_cookies($config);
        }
    }
}

$savedTheme = $config['theme'] ?? $defaultConfig['theme'];
$savedLanguage = $config['language'] ?? $defaultConfig['language'];
$savedColor = $config['color'] ?? $defaultConfig['color'];
$savedIconSortOrder = $config['icon_sort_order'] ?? $defaultConfig['icon_sort_order'];
$savedIconStyle = in_array(($config['icon_style'] ?? ''), ['monochrome','multicolor','animated'], true) ? $config['icon_style'] : 'animated';
$config['icon_style'] = $savedIconStyle;
$savedWallpaper = in_array(($config['wallpaper'] ?? ''), $allowedWallpapers, true) ? $config['wallpaper'] : 'default';
$config['wallpaper'] = $savedWallpaper;

if ($themeFromUrl) $config['theme'] = ap_normalize_pref_theme((string)$themeFromUrl, (string)$config['theme']);
if ($langFromUrl) $config['language'] = ap_normalize_pref_language((string)$langFromUrl, (string)$config['language']);
if ($colorFromUrl) $config['color'] = $colorFromUrl;
$config['color'] = ap_sanitize_hex_color((string)($config['color'] ?? '#007bff'));
if($isAuthConfigPage){
    $browserTheme = (string)($_COOKIE['browser_theme'] ?? '');
    if (!$themeFromUrl) $config['theme'] = in_array($browserTheme, ['light', 'dark'], true) ? $browserTheme : 'light';
    $config['wallpaper'] = 'default';
    $savedWallpaper = 'default';
}

$defaultTranslations=['search_placeholder'=>'Search apps...','settings_title'=>'Settings','language'=>'Language','theme'=>'Theme','theme_light'=>'Light','theme_dark'=>'Dark','theme_auto'=>'Device','icon_sort'=>'Icon order','icon_sort_alpha'=>'Alphabetical','icon_sort_popular'=>'By popularity','icon_sort_recommended'=>'Recommended','icon_style'=>'Icon style','icon_style_monochrome'=>'Monochrome','icon_style_multicolor'=>'Multicolor','icon_style_animated'=>'Animated','color'=>'Element Color','wallpaper'=>'Background','wallpaper_default'=>'Theme gradient','wallpaper_choose'=>'Choose background','wallpaper_photo_mountains'=>'Mountains','wallpaper_photo_forest'=>'Forest','wallpaper_photo_lake'=>'Lake','wallpaper_photo_ocean'=>'Ocean','wallpaper_photo_desert'=>'Desert','wallpaper_photo_city_day'=>'City','wallpaper_photo_city_night'=>'City at night','wallpaper_photo_tropical'=>'Tropical beach','wallpaper_photo_valley'=>'Green valley','wallpaper_photo_coast'=>'Coastline','wallpaper_photo_autumn'=>'Autumn','wallpaper_photo_snow'=>'Snow peaks','wallpaper_photo_bridge'=>'Golden bridge','save'=>'Save','open_app_tooltip'=>'Open','app_not_found'=>'App not found','welcome_message'=>'Welcome to the Shell. Select an app from the menu.','home'=>'Home','home_tooltip'=>'Return to main page','stats_graph'=>'View Visit Statistics','week_view'=>'Week','month_view'=>'Month','year_view'=>'Year','year'=>'Year','month'=>'Month','all_time_view'=>'All Time','edit_stats'=>'Edit Stats Data','date_label'=>'Date','visits_label'=>'Visits','bot_settings'=>'Bot Settings','domain_settings'=>'Domain Settings','save_domain'=>'Save Domain','domain_saved'=>'Domain saved successfully','domain_save_error'=>'Error saving domain','login'=>'Login','logout'=>'Logout','email'=>'Email','password'=>'Password','confirm_password'=>'Confirm Password','forgot_password'=>'Forgot Password?','reset_password'=>'Reset Password','send_reset_code'=>'Send Reset Code','reset_code'=>'Reset Code','new_password'=>'New Password','register'=>'Register','already_have_account'=>'Already have an account?','dont_have_account'=>'Don\'t have an account?','invalid_credentials'=>'Invalid email or password','password_mismatch'=>'Passwords do not match','user_exists'=>'User already exists','registration_success'=>'Registration successful','reset_code_sent'=>'Reset code sent to your email','invalid_reset_code'=>'Invalid reset code','password_reset_success'=>'Password reset successful','welcome_user'=>'Welcome, ','change_password'=>'Change Password','current_password'=>'Current Password','change_photo'=>'Change Photo','upload_photo'=>'Upload Photo','profile_settings'=>'Profile Settings','password_changed'=>'Password changed successfully','wrong_current_password'=>'Wrong current password','verify_password'=>'Verify','current_password_verified'=>'Current password verified','generate_password'=>'Generate password','photo_updated'=>'Photo updated successfully','user_profile'=>'User Profile','close'=>'Close','email_settings'=>'Email Settings','google_client_id'=>'Google Client ID','google_client_secret'=>'Google Client Secret','smtp_host'=>'SMTP Host','smtp_port'=>'SMTP Port','smtp_username'=>'SMTP username','smtp_password'=>'SMTP Password','user_management'=>'User Management','users_list'=>'Users List','delete_user'=>'Delete User','user_deleted'=>'User deleted successfully','delete_user_confirm'=>'Are you sure you want to delete this user?','email_settings_saved'=>'Email settings saved successfully','email_settings_error'=>'Error saving email settings','search_users'=>'Search users...','user_role'=>'Role','guest'=>'Guest','manager'=>'Manager','admin'=>'Admin','change_role'=>'Change Role','role_changed'=>'Role changed successfully','verification_code'=>'Verification Code','send_verification_code'=>'Send Verification Code','verify_email'=>'Verify Email','email_verified'=>'Email verified successfully','invalid_verification_code'=>'Invalid verification code','email_not_verified'=>'Email not verified. Please check your email for verification code.','resend_verification'=>'Resend Verification','save_roles'=>'Save Roles','privacy_policy'=>'Privacy Policy','terms_of_service'=>'Terms of Service','shared_files_management'=>'Shared Files Management','file_name'=>'File Name','file_path'=>'File Path','share_id'=>'Share ID','delete_file'=>'Delete File','delete_share_confirm'=>'Are you sure you want to delete this shared file?','share_deleted'=>'Shared file deleted successfully','no_shared_files'=>'No shared files available','delete_all_files'=>'Delete All Files','delete_all_confirm'=>'Are you sure you want to delete ALL shared files?','all_files_deleted'=>'All shared files deleted successfully','refresh_files'=>'Refresh Files List','refresh'=>'Refresh','session_management'=>'Session Management','active_sessions'=>'Active Sessions','session_id'=>'Session ID','login_time'=>'Login Time','last_activity'=>'Last Activity','ip_address'=>'IP Address','terminate_session'=>'Terminate Session','terminate_all_sessions'=>'Terminate All Sessions','session_terminated'=>'Session terminated successfully','all_sessions_terminated'=>'All sessions terminated successfully','no_active_sessions'=>'No active sessions','app_access_control'=>'Application Access Control','block_apps'=>'Block Applications','blocked_apps'=>'Blocked Applications','save_blocked_apps'=>'Save Blocked Apps','blocked_apps_saved'=>'Blocked apps saved successfully','select_apps_to_block'=>'Select applications to block for this user','all_apps_allowed'=>'All applications are allowed','current_session'=>'Current Session','qr_code'=>'QR Code','qr_code_title'=>'Scan to Access Server','current_domain'=>'Current Domain','copy_link'=>'Copy Link','previous_month'=>'Previous Month','next_month'=>'Next Month','previous_year'=>'Previous Year','next_year'=>'Next Year','view_terms'=>'View Terms','view_privacy'=>'View Privacy','device'=>'Device','browser'=>'Browser','windows'=>'Windows','linux'=>'Linux','android'=>'Android','ios'=>'iOS','macos'=>'macOS','chrome'=>'Chrome','firefox'=>'Firefox','safari'=>'Safari','edge'=>'Edge','opera'=>'Opera','unknown_device'=>'Unknown Device','unknown_browser'=>'Unknown Browser','google_data'=>'Google Account Data','set_password'=>'Set Password','confirm_set_password'=>'Confirm Password','password_required'=>'Your account has no password. Please set a password to continue.','google_user_not_found'=>'This Google account is not registered yet. Please set a password to complete registration.','verify_now'=>'Verify Now','verification_link_sent'=>'Verification link sent to your email','click_link_to_verify'=>'Click the link in the email to verify your account','verify_account'=>'Verify Account','verification_failed'=>'Verification failed','account_verified'=>'Account verified successfully','back_to_home'=>'Back to Home','search_files'=>'Search files...','clear_search'=>'Clear search','save_changes'=>'Save Changes','cancel'=>'Cancel','edit_stats_title'=>'Edit Statistics Data','app_name'=>'App Name','date'=>'Date','value'=>'Value','change_photo_button'=>'Change Photo','valid_for_minutes'=>'Valid for 15 minutes','sent_at'=>'Sent at','copyright'=>'All rights reserved','delete_row'=>'Delete Row','delete_column'=>'Delete Column','delete_filtered_files'=>'Delete Filtered Files','delete_filtered_confirm'=>'Are you sure you want to delete the filtered files?','email_not_verified_title'=>'Email Not Verified','email_not_verified_desc'=>'Your email address has not been verified. Click the button below to receive a new verification link.','resend_verification_link'=>'Send Verification Link','back_to_login'=>'Back to Login','refresh_users'=>'Users list refreshed','admin_panel_loading'=>'Loading Admin Panel...','name'=>'Name','ap_autosave'=>'Changes are saved automatically on edit','ap_users_subtitle'=>'View, edit roles, and delete registered users.','ap_email_subtitle'=>'Google OAuth credentials and SMTP relay settings for outgoing mail.','ap_stats_subtitle'=>'Edit visit statistics data. Dates in rows, apps in columns.','ap_json_files'=>'JSON Files','ap_json_subtitle'=>'Browse and edit JSON configuration files in the project directory.','ap_json_loading'=>'Loadingâ¦','ap_json_back'=>'Back to list','ap_view_mode'=>'View','ap_edit_mode'=>'Edit','ap_json_saved'=>'File saved','ap_json_invalid'=>'Invalid JSON structure','ap_json_confirm'=>'Save changes to this file?','ap_sort_by'=>'Sort:','ap_sort_name'=>'Name','ap_sort_size'=>'Size','ap_sort_modified'=>'Modified','ap_saving'=>'Saving...','folder_config_title'=>'App Folder Paths','folder_config_desc'=>'Map each app to the base directory where user folders are stored.','folder_config_add'=>'Add Application','folder_config_saved'=>'Paths saved','folder_config_app_placeholder'=>'â Select app â','folder_config_path_placeholder'=>'/var/www/app/users','folder_config_key_info'=>'App keys are stored as folder names (language-independent).','tg_bot_desc'=>'Configure the Telegram bot token, mini app public URL, and view authorized chat IDs.','tg_bot_token'=>'Bot Token','tg_bot_token_hint'=>'Get your token from @BotFather. Do not URL-encode it.','tg_recipients'=>'Authorized Chat IDs','tg_recipients_hint'=>'IDs are recorded when users sign in to the Telegram bot (database).','tg_no_chats'=>'No sessions yet. Chat IDs appear after users log in to the bot.','tg_saved'=>'Saved','tg_test'=>'Test','tg_test_ok'=>'Test sent OK','tg_test_fail'=>'Test failed','admin_panel_title'=>'Admin Panel','deleting'=>'Deleting...','files'=>'files','scanning_files'=>'Scanning user files...','user_files'=>'User files','no_user_files'=>'No user files found in configured paths','time_remaining'=>'Time remaining','almost_done'=>'Almost done...','scanning_progress'=>'Scanning','scan_speed'=>'Speed','complete'=>'Complete','delete_user_files_also'=>'Also delete all user files listed above','verified'=>'Verified','pending_verification'=>'Pending verification','passwords_match'=>'â Passwords match','password_too_short'=>'Password must be at least 6 characters','password_needs_letter'=>'Password must contain at least one letter','password_needs_digit'=>'Password must contain at least one digit',
    'ap_db_title'=>'Database Manager','ap_db_subtitle'=>'Browse and manage SQLite tables.',
    'ap_col_status'=>'Status','ap_col_date'=>'Created',
    'ap_tg_hint'=>'Chat IDs are recorded when users authenticate in the Telegram bot.',
    'tg_public_base_url'=>'Public site URL',
    'tg_public_base_url_hint'=>'HTTPS origin without a trailing slash. Saved in the database table config (key tg_webapp_base_url). Used for the Telegram mini app button.',
    'ap_folder_key_info'=>'App folder keys are language-independent.',
    'ap_stats_del_col'=>'Delete Column','ap_stats_save'=>'Save Changes',
    'ap_stats_del_row'=>'Delete Row',
    'stats_analysis_mode'=>'Analysis Mode','stats_popular_apps'=>'Most Popular Apps','stats_unpopular_apps'=>'Least Popular Apps','stats_peak_load_days'=>'Peak Load Days','stats_load_distribution'=>'App Load Distribution','stats_no_data'=>'No data available','stats_total_visits'=>'Total Visits','stats_avg_daily_visits'=>'Average Daily Visits','stats_peak_day'=>'Peak Day','stats_popularity_score'=>'Popularity Score','stats_visits_share'=>'Visits Share','stats_trend'=>'Trend','stats_analysis_description'=>'Insights based on usage patterns for the selected year','stats_show_analysis'=>'Show Analysis','stats_hide_analysis'=>'Hide Analysis','show_year_view'=>'Show Year',
    'ap_json_name_col'=>'File','ap_json_size_col'=>'Size','ap_json_modified_col'=>'Modified',
    'ap_db_browse'=>'Browse','ap_db_insert'=>'Insert Row','ap_db_refresh'=>'Refresh',
    'ap_db_search'=>'Search columns…','ap_db_no_rows'=>'No rows','ap_db_loading'=>'Loading…',
    'ap_db_per_page'=>'/ page','ap_db_schema'=>'Schema','ap_db_add_user'=>'Add User',
    'ap_db_sql'=>'SQL Console','ap_db_stats'=>'Stats','ap_db_vacuum'=>'VACUUM',
    'ap_db_export'=>'Export Report',
    'fm_title'=>'File Manager','fm_subtitle'=>'Browse, edit, create and delete files in all project directories.',
    'fm_new_folder'=>'New Folder','fm_new_file'=>'New File','fm_rename'=>'Rename','fm_delete'=>'Delete',
    'fm_open'=>'Open','fm_back'=>'Back','fm_refresh'=>'Refresh','fm_home'=>'Root',
    'fm_folder_name'=>'Folder name','fm_file_name'=>'File name','fm_new_name'=>'New name',
    'fm_confirm_delete'=>'Delete this item?','fm_saving'=>'Saving…','fm_saved'=>'Saved',
    'fm_loading'=>'Loading…','fm_empty'=>'Empty folder','fm_binary'=>'Binary file — cannot be edited as text.',
    'fm_size'=>'Size','fm_modified'=>'Modified','fm_type'=>'Type','fm_name'=>'Name',
    'fm_confirm_save'=>'Save changes to this file?','fm_view'=>'View','fm_edit'=>'Edit',
    'fm_search'=>'Search in folder…','fm_create'=>'Create','fm_cancel'=>'Cancel',
    'fm_file'=>'File','fm_dir'=>'Folder','fm_no_items'=>'No items found',
    'fm_delete_confirm'=>'Are you sure you want to delete',
    'fm_root'=>'Root directory',
    'fm_upload'=>'Upload',
    'fm_download'=>'Download',
    'fm_copy_clipboard'=>'Copy to clipboard',
    'fm_touch_keys'=>'Touch keys',
    'fm_session_refreshed'=>'Sessions list updated',
    'fm_folder_created'=>'Folder created',
    'fm_file_created'=>'File created',
    'fm_copied'=>'Copied to clipboard',
    'fm_file_uploaded'=>'File uploaded',
    'fm_renamed'=>'Renamed',
    'fm_deleted'=>'Deleted',
    'fm_network_error'=>'Network error',
    'fm_error_deleting'=>'Error deleting files',
    'fm_error_deleting_user'=>'Error deleting user',
    'otp_page_title'=>'One-Time PIN Login',
    'otp_subtitle'=>'Enter the 6-digit one-time code provided to you.',
    'otp_warning_attempts'=>'⚠ You have 3 attempts. After 3 wrong codes your IP will be blocked for 3 minutes.',
    'otp_submit'=>'Sign In with PIN',
    'otp_enter_6_digits'=>'Please enter all 6 digits',
    'otp_invalid'=>'Invalid PIN. %d attempts remaining.',
    'otp_ip_locked'=>'Too many failed attempts. Try again in %d seconds.',
    'otp_generate_btn'=>'Generate One-Time PIN',
    'otp_gen_title'=>'Generate One-Time PIN',
    'otp_gen_subtitle'=>'Share this code to allow one-time login into your account.',
    'otp_ttl_label'=>'Valid for:',
    'otp_ttl_1min'=>'1 minute',
    'otp_ttl_3min'=>'3 minutes',
    'otp_ttl_5min'=>'5 minutes (default)',
    'otp_ttl_10min'=>'10 minutes',
    'otp_warning_3attempts'=>'Recipient has only 3 attempts to enter the code. After 3 failures, their IP is blocked for 3 minutes.',
    'otp_generate'=>'Generate PIN',
    'otp_your_pin'=>'Your one-time PIN:',
    'otp_expires_in'=>'Expires in',
    'otp_minutes'=>'min',
    'otp_link_hint'=>'Send the recipient this link:',
    'login_ip_locked'=>'Too many failed login attempts. Try again in %d seconds.',
    'backup'=>'Backup',
    'database'=>'Database',
    'ssh_terminal'=>'SSH Terminal',
    'ssh_profile'=>'SSH Profile',
    'ssh_host'=>'Host',
    'ssh_user'=>'User',
    'ssh_port'=>'Port',
    'ssh_backend'=>'Backend',
    'ssh_key_path'=>'SSH private key',
    'ssh_key_ready'=>'SSH key found. Only key authentication is used.',
    'ssh_key_only_hint'=>'Password login is disabled. Place id_ed25519 or id_rsa in data/.ssh/',
    'ssh_key_path_optional'=>'SSH Key path (optional)',
    'ssh_key_passphrase_optional'=>'Key passphrase (optional)',
    'ssh_password_optional'=>'Password (phpseclib, optional)',
    'ssh_test_connection'=>'Test Connection',
    'ssh_save_profile'=>'Save SSH Profile',
    'ssh_connection_test_output'=>'Connection test output',
    'show_hide_password'=>'Show/hide password',
    'optional'=>'optional',
    'ssh_profile_saved'=>'SSH profile saved',
    'ssh_save_first'=>'Save SSH profile first',
    'ssh_running_command'=>'Running command...',
    'ssh_terminal_ready'=>'Terminal ready',
    'ssh_loading_profile'=>'Loading saved SSH profile...',
    'ssh_profile_loaded'=>'Profile loaded',
    'ssh_no_saved_profile'=>'No saved SSH profile',
    'ssh_no_saved_profile_yet'=>'No saved SSH profile yet.',
    'ssh_saved_click_eye'=>'Saved. Click the eye to reveal.',
    'ssh_no_saved_password'=>'No saved password.',
    'ssh_saving_testing'=>'Saving & testing SSH connection...',
    'ssh_connected_via'=>'SSH connected via',
    'ssh_not_connected'=>'SSH not connected:',
    'ssh_unknown_error'=>'unknown error',
    'ssh_test_failed'=>'SSH test failed:',
    'ssh_load_failed'=>'Load failed:',
    'ssh_decrypting_password'=>'Decrypting password...',
    'ssh_no_saved_password_found'=>'No saved password found',
    'ssh_password_revealed'=>'Password revealed',
    'ssh_decrypt_failed'=>'Decrypt failed',
    'ssh_revealed'=>'Revealed.',
    'omniroute_title'=>'OmniRoute AI Router',
    'omniroute_open_button'=>'Open OmniRoute',
    'omniroute_open_hint'=>'OmniRoute server forbids embedding (CSP frame-ancestors). It opens in a separate browser window — exactly like a regular new tab.',
    'omniroute_popup_blocked'=>'Browser blocked the popup. Allow popups for this site to open OmniRoute.',
    'docker_manager_title'=>'Docker Manager',
    'onlyoffice_title'=>'OnlyOffice',
    'onlyoffice_token_label'=>'JWT Token',
    'onlyoffice_token_hint'=>'Stored in the config table with key onlyoffice_jwt_secret.',
    'onlyoffice_saved'=>'OnlyOffice token saved',
    'ai_assistant_settings'=>'AI Assistant',
    'changeApiKey'=>'Change API Key',
    'apiEndpoint'=>'API Endpoint',
    'currentApiKey'=>'Current API Key',
    'newApiKey'=>'New API Key',
    'confirmNewApiKey'=>'Confirm New API Key',
    'apiKeyChangeWarning'=>'Changing the API key will affect all AI assistant functionality. Make sure you have the correct key.',
    'apiKeysMatch'=>'API keys match',
    'apiKeysDoNotMatch'=>'API keys do not match',
    'apiKeysRequired'=>'Both API key fields are required when changing the key',
    'invalidApiKeyFormat'=>'Invalid API key format. Key should start with sk- or io-v2-',
    'ai_config_saved'=>'AI configuration saved',
    'github_pull_settings'=>'Pull через GitHub',
    'github_update'=>'Обновить',
    'github_refresh'=>'Обновить список',
    'github_pull_ok'=>'Успешно обновлено',
    'github_pull_up_to_date'=>'Уже актуальная версия',
    'github_no_changes'=>'Нет ожидающих изменений',
    'github_pull_confirm'=>'Подтянуть изменения из main и перезапустить контейнер?',
    'ai_models_label'=>'Models (one per line)',
    'ai_default_model_label'=>'Default model',
    'ap_settings_subtitle'=>'Configure email, Telegram bot, OnlyOffice, Docker, Backup, AI Assistant, and Pull via GitHub integrations.',
    'docker_manager_subtitle'=>'Manage Docker containers, images, and services.'];
$defaultTranslations['keyboard_shortcuts'] = 'Keyboard Shortcuts';
$defaultTranslations['keyboard_shortcuts_desc'] = 'Hotkeys available on the home screen with app tiles.';
$defaultTranslations['keyboard_shortcuts_hint'] = 'View hotkeys';
$defaultTranslations['keyboard_shortcuts_open'] = 'Open shortcuts help';
$defaultTranslations['keyboard_shortcuts_key'] = 'Key';
$defaultTranslations['keyboard_shortcuts_action'] = 'Action';
$defaultTranslations['shortcut_admin_panel'] = 'Open Admin Panel';
$defaultTranslations['shortcut_sessions'] = 'Open Session Management';
$defaultTranslations['shortcut_stats'] = 'Open Statistics';
$defaultTranslations['shortcut_shared_files'] = 'Open Shared Files';
$defaultTranslations['shortcut_qr'] = 'Open QR Code';
$defaultTranslations['shortcut_profile'] = 'Open Profile';
$defaultTranslations['shortcut_search'] = 'Focus app search';
$defaultTranslations['shortcut_shortcuts'] = 'Open keyboard shortcuts';
$defaultTranslations['shortcut_support_chat'] = 'Open Support Chat';
$defaultTranslations['ap_geo_access_title'] = 'Country and VPN restrictions';
$defaultTranslations['ap_block_vpn_traffic'] = 'Block VPN/proxy/hosting traffic';
$defaultTranslations['ap_geo_access_hint'] = 'Requests from selected countries are blocked when opening apps. VPN detection uses client IP metadata when available.';
$defaultTranslations['shortcut_arrows'] = 'Move between app tiles';
$defaultTranslations['shortcut_enter'] = 'Open selected app';
$defaultTranslations['account_delete_warning'] = 'To completely delete your account data, please contact support.';
$defaultTranslations['share_expiration'] = 'Expiration';
$defaultTranslations['share_expiration_never'] = 'Never';
$defaultTranslations['share_expiration_1h'] = '1 hour';
$defaultTranslations['share_expiration_1d'] = '1 day';
$defaultTranslations['share_expiration_1w'] = '1 week';
$defaultTranslations['share_expiration_1m'] = '1 month';
$defaultTranslations['share_expiration_3m'] = '3 months';
$defaultTranslations['share_expiration_6m'] = 'Half a year';
$defaultTranslations['share_expiration_1y'] = '1 year';
$defaultTranslations['shell_application'] = 'Shell Application';
$defaultTranslations['news_portal_title'] = 'News Portal';
$defaultTranslations['news_articles'] = 'Articles';
$defaultTranslations['news_title_label'] = 'Title';
$defaultTranslations['news_title_placeholder'] = 'Article title...';
$defaultTranslations['news_body_label'] = 'Content';
$defaultTranslations['news_body_placeholder'] = 'Article text...';
$defaultTranslations['news_save_button'] = 'Save article';
$defaultTranslations['news_title_required'] = 'Title is required';
$defaultTranslations['news_ai_generate'] = 'Generate with AI';
$defaultTranslations['news_ai_prompt_ph'] = 'Describe what you want: topic, tone, length...';
$defaultTranslations['news_ai_fills'] = 'Fills title + content';
$defaultTranslations['news_photo_label'] = 'Photo';
$defaultTranslations['news_photo_placeholder'] = 'Photo URL or path...';
$defaultTranslations['news_published_label'] = 'Published';
$defaultTranslations['news_draft_label'] = 'Draft';
$defaultTranslations['news_created_on'] = 'Created on';
$defaultTranslations['news_updated_on'] = 'Updated on';
$defaultTranslations['news_delete_confirm'] = 'Are you sure you want to delete this article?';
$defaultTranslations['news_article_deleted'] = 'Article deleted successfully';
$defaultTranslations['news_article_saved'] = 'Article saved successfully';
$defaultTranslations['news_empty_title'] = 'No articles yet';
$defaultTranslations['news_empty_body'] = 'Click "Add Article" to create your first article.';
$defaultTranslations['news_valid_until_label'] = 'Valid until';
$defaultTranslations['news_valid_until_hint'] = 'Leave empty for no expiration';
$defaultTranslations['news_layout_label'] = 'Layout';
$defaultTranslations['generate'] = 'Generate';
$defaultTranslations['select_model_placeholder'] = '-- Select Model --';
$defaultTranslations['news_editor_tab'] = 'Editor';
$defaultTranslations['news_settings_tab'] = 'Settings';
$defaultTranslations['news_draft'] = 'Draft';
$defaultTranslations['news_published'] = 'Published';
$defaultTranslations['news_add_button'] = 'Add Article';
$defaultTranslations['weather_perm_denied'] = 'Please allow location access';
$defaultTranslations['weather_loading'] = 'Loading weather...';
$defaultTranslations['weather_denied'] = 'Location access denied. Enable it to see local weather.';
$defaultTranslations['weather_denied_msg'] = 'Please allow location access in your browser settings to see local weather';
$defaultTranslations['weather_allow_location'] = 'Allow location';
$defaultTranslations['weather_no_geo'] = 'Geolocation not supported';
$defaultTranslations['weather_timeout'] = 'Weather request timed out';
$defaultTranslations['weather_no_data'] = 'No weather data';
$defaultTranslations['weather_fetch_error'] = 'Failed to load weather';
$defaultTranslations['weather_unknown'] = 'Unknown location';
$defaultTranslations['weather_settings_hint'] = 'Go to browser site settings and allow location';
$defaultTranslations['weather_forecast_title'] = '7-day forecast';
$defaultTranslations['weather_chart_title'] = 'Temperature trend';
$defaultTranslations['weather_high'] = 'High';
$defaultTranslations['weather_low'] = 'Low';
$defaultTranslations['weather_today'] = 'Today';
$defaultTranslations['weather_tomorrow'] = 'Tomorrow';
$defaultTranslations['weather_wind'] = 'Wind';
$defaultTranslations['weather_precip'] = 'Precipitation';
$defaultTranslations['weather_precip_mm'] = '%s mm';
$defaultTranslations['weather_precip_none'] = 'No rain';
$defaultTranslations['weather_loading_forecast'] = 'Loading forecast...';
$defaultTranslations['weather_no_forecast'] = 'No forecast data available';
$defaultTranslations['weather_fetch_forecast_error'] = 'Failed to load forecast';
$defaultTranslations['weather_click_forecast'] = 'Click to see 7-day forecast';
$defaultTranslations['weather_desc_clear'] = 'Clear';
$defaultTranslations['weather_desc_mainly_clear'] = 'Mainly clear';
$defaultTranslations['weather_desc_partly_cloudy'] = 'Partly cloudy';
$defaultTranslations['weather_desc_overcast'] = 'Overcast';
$defaultTranslations['weather_desc_fog'] = 'Fog';
$defaultTranslations['weather_desc_drizzle'] = 'Drizzle';
$defaultTranslations['weather_desc_rain'] = 'Rain';
$defaultTranslations['weather_desc_snow'] = 'Snow';
$defaultTranslations['weather_desc_showers'] = 'Rain showers';
$defaultTranslations['weather_desc_thunderstorm'] = 'Thunderstorm';
$defaultTranslations['weather_desc_cloudy'] = 'Cloudy';
$defaultTranslations['weather_weekday_0'] = 'Sunday';
$defaultTranslations['weather_weekday_1'] = 'Monday';
$defaultTranslations['weather_weekday_2'] = 'Tuesday';
$defaultTranslations['weather_weekday_3'] = 'Wednesday';
$defaultTranslations['weather_weekday_4'] = 'Thursday';
$defaultTranslations['weather_weekday_5'] = 'Friday';
$defaultTranslations['weather_weekday_6'] = 'Saturday';
$language = ap_i18n_lang_exists($config['language']) ? $config['language'] : 'en';
$translations = ap_merge_i18n($defaultTranslations, $language);

require 'vendor/autoload.php';
require_once APP_ROOT . '/ssh_terminal_handler.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmailWithLogo($to, $subject, $body) {
    global $authConfig;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host=$authConfig['smtp_host'];
        $mail->SMTPAuth=true;
        $mail->Username=$authConfig['smtp_username'];
        $mail->Password=$authConfig['smtp_password'];
        $mail->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port=$authConfig['smtp_port'];
        $mail->CharSet='UTF-8';
        $mail->setFrom($authConfig['smtp_username'],'Server Kherson');
        $mail->addAddress($to);
        if(file_exists('icon.png'))$mail->addEmbeddedImage('icon.png','logo');
        $mail->isHTML(true);
        $mail->Subject=$subject;
        $mail->Body=$body;
        $mail->AltBody=trim(html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$body)),ENT_QUOTES,'UTF-8'));
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
function apEmailPublicBaseUrl(): string {
    return 'https://serverkherson.xyz';
}
function apEmailLogoHtml(): string {
    if(file_exists('icon.png'))return "<img src='cid:logo' alt='Server Kherson' style='width:58px;height:58px;border-radius:18px;display:block;margin:0 auto 14px;box-shadow:0 12px 30px rgba(59,130,246,.25);'>";
    return "<div style='width:58px;height:58px;border-radius:18px;margin:0 auto 14px;background:linear-gradient(135deg,#2563eb,#7dd3fc);color:#fff;font:700 20px Arial,sans-serif;line-height:58px;text-align:center;box-shadow:0 12px 30px rgba(59,130,246,.25);'>SK</div>";
}
function buildAuthEmailBody(string $eyebrow,string $title,string $message,string $mainBlock,string $validityText,string $safetyText): string {
    $year=date('Y');
    $sentTime=date('Y-m-d H:i:s T');
    $termsLink=apEmailPublicBaseUrl().'/terms';
    $safeTermsLink=my_htmlspecialchars($termsLink);
    $safeEyebrow=my_htmlspecialchars($eyebrow);
    $safeTitle=my_htmlspecialchars($title);
    $safeMessage=my_htmlspecialchars($message);
    $safeValidityText=my_htmlspecialchars($validityText);
    $safeSafetyText=my_htmlspecialchars($safetyText);
    $safeSentTime=my_htmlspecialchars($sentTime);
    $logoHtml=apEmailLogoHtml();
    return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title>{$safeTitle}</title>
</head>
<body style='margin:0;padding:0;background:#eef4ff;color:#162033;font-family:Arial,Helvetica,sans-serif;'>
<table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='background:radial-gradient(circle at top left,#dbeafe 0,#eef4ff 32%,#f8fbff 100%);padding:34px 14px;'>
<tr>
<td align='center'>
<table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='max-width:620px;border-radius:28px;overflow:hidden;background:#ffffff;box-shadow:0 22px 70px rgba(30,64,175,.16);'>
<tr>
<td style='padding:34px 30px 28px;text-align:center;background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 48%,#f8fafc 100%);border-bottom:1px solid #dbeafe;'>
{$logoHtml}
<div style='color:#3b82f6;text-transform:uppercase;letter-spacing:.12em;font-size:12px;font-weight:700;margin-bottom:10px;'>{$safeEyebrow}</div>
<h1 style='margin:0;color:#0f172a;font-size:28px;line-height:1.25;font-weight:800;'>{$safeTitle}</h1>
<p style='margin:14px auto 0;max-width:470px;color:#475569;font-size:16px;line-height:1.65;'>{$safeMessage}</p>
</td>
</tr>
<tr>
<td style='padding:32px 30px;background:#fff;'>
{$mainBlock}
<div style='margin-top:24px;padding:16px 18px;border-radius:18px;background:#f8fbff;border:1px solid #dbeafe;color:#334155;font-size:14px;line-height:1.6;'>
<strong style='color:#1d4ed8;'>Security note:</strong> {$safeValidityText}
</div>
<p style='margin:20px 0 0;color:#64748b;font-size:14px;line-height:1.6;'>{$safeSafetyText}</p>
<p style='margin:12px 0 0;color:#94a3b8;font-size:12px;line-height:1.5;'>Sent at {$safeSentTime}</p>
</td>
</tr>
<tr>
<td style='padding:24px 30px;text-align:center;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;line-height:1.7;'>
<p style='margin:0 0 8px;'>By using Server Kherson, you agree to our <a href='{$safeTermsLink}' style='color:#2563eb;text-decoration:none;font-weight:700;'>Terms of Service</a>.</p>
<p style='margin:0;'>&copy; {$year} Server Kherson. All rights reserved.</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>";
}
// Функция для определения фактической темы для отображения
function getActualDisplayTheme($configTheme) {
    if($configTheme === 'auto') {
        // Определяем тему браузера через JavaScript (будет установлена в куки)
        // По умолчанию используем светлую, пока JS не установит куку
        return isset($_COOKIE['browser_theme']) ? $_COOKIE['browser_theme'] : 'light';
    }
    return $configTheme;
}

// Функция для получения темы для ссылок (всегда конкретная тема, не auto)
function getThemeForLinks($configTheme) {
    if($configTheme === 'auto') {
        // Для ссылок всегда передаем фактическую тему
        return getActualDisplayTheme($configTheme);
    }
    return $configTheme;
}
function sendPasswordResetEmail($to, $resetCode) {
    $safeResetCode=my_htmlspecialchars((string)$resetCode);
    $mainBlock="<div style='margin:0 auto;padding:24px;border-radius:22px;background:linear-gradient(135deg,#eff6ff,#f8fbff);border:1px solid #bfdbfe;text-align:center;'><div style='color:#64748b;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px;'>Your reset code</div><div style='font-size:38px;line-height:1;letter-spacing:.22em;font-weight:800;color:#1d4ed8;font-family:Arial,Helvetica,sans-serif;'>{$safeResetCode}</div></div>";
    $body=buildAuthEmailBody('Password reset','Reset your Server Kherson password','Use this one-time code to finish resetting your password.',$mainBlock,'This code is valid for 15 minutes. Never share it with anyone.','If you did not request a password reset, you can safely ignore this email and your password will stay unchanged.');
    return sendEmailWithLogo($to,'Reset your Server Kherson password',$body);
}
function sendVerificationEmail($to, $verificationToken) {
    $verificationLink=apEmailPublicBaseUrl().'/verify?token='.urlencode($verificationToken);
    $safeVerificationLink=my_htmlspecialchars($verificationLink);
    $mainBlock="<div style='text-align:center;margin:4px 0 22px;'><a href='{$safeVerificationLink}' style='display:inline-block;padding:15px 28px;border-radius:999px;background:linear-gradient(135deg,#2563eb,#38bdf8);color:#fff;text-decoration:none;font-size:16px;font-weight:800;box-shadow:0 14px 28px rgba(37,99,235,.24);'>Confirm account</a></div><div style='padding:15px 16px;border-radius:16px;background:#f8fbff;border:1px solid #dbeafe;color:#475569;font-size:13px;line-height:1.6;word-break:break-all;'><strong style='display:block;color:#334155;margin-bottom:6px;'>Button not working?</strong><a href='{$safeVerificationLink}' style='color:#2563eb;text-decoration:none;'>{$safeVerificationLink}</a></div>";
    $body=buildAuthEmailBody('Account confirmation','Confirm your Server Kherson account','Thanks for joining Server Kherson. Please confirm this email address to activate your account.',$mainBlock,'This confirmation link is valid for 24 hours.','If you did not create this account, you can safely ignore this email.');
    return sendEmailWithLogo($to,'Confirm your Server Kherson account',$body);
}
if (!function_exists('detectDeviceAndBrowser')) {
    function detectDeviceAndBrowser($userAgent){
        $device='unknown_device';
        $browser='unknown_browser';
        
        if(preg_match('/android/i',$userAgent)){
            $device='android';
        }
        elseif(preg_match('/linux/i',$userAgent) && !preg_match('/android/i',$userAgent)){
            $device='linux';
        }
        elseif(preg_match('/windows|win32|win64/i',$userAgent)){
            $device='windows';
        }
        elseif(preg_match('/iphone|ipad|ipod/i',$userAgent)){
            $device='ios';
        }
        elseif(preg_match('/macintosh|mac os x/i',$userAgent)){
            $device='macos';
        }
        
        if(preg_match('/chrome/i',$userAgent)&&!preg_match('/edg/i',$userAgent)){$browser='chrome';}
        elseif(preg_match('/firefox/i',$userAgent)){$browser='firefox';}
        elseif(preg_match('/safari/i',$userAgent)&&!preg_match('/chrome/i',$userAgent)){$browser='safari';}
        elseif(preg_match('/edg/i',$userAgent)){$browser='edge';}
        elseif(preg_match('/opera|opr/i',$userAgent)){$browser='opera';}
        return['device'=>$device,'browser'=>$browser];
    }
}

// loadActiveSessions() provided by db.php


// saveActiveSessions() provided by db.php


// addUserToActiveSessions() provided by db.php


// updateUserActivity() provided by db.php


// removeUserFromActiveSessions() provided by db.php


// getUserActiveSessions() provided by db.php


// getUserFromActiveSessions() provided by db.php


function getDeviceTheme(){return 'light';}

$actualTheme = getActualDisplayTheme($config['theme']);
$themeForAppLink = getThemeForLinks($config['theme']);

// loadAppAccessLevels(), getAppMinRole(), userMeetsAppMinRole(), saveAppAccessLevelsFromMap() in db.php


// generateResetCode() — see db.php (cryptographically secure)
function generateVerificationToken(){return bin2hex(random_bytes(32));}

// loadUsers() provided by db.php


// saveUsers() provided by db.php


// loadPendingUsers() provided by db.php


// savePendingUsers() provided by db.php


// loadUserFoldersConfig() provided by db.php


// saveUserFoldersConfig() provided by db.php


// loadTgBotConfig() provided by db.php


// saveTgBotConfig() provided by db.php


function removeBOM($text){
    if(substr($text, 0, 3) === "\xEF\xBB\xBF") return substr($text, 3);
    return $text;
}

function loadAuthorizedChats(){
    try {
        $db = getDB();
        ensureTelegramBotSchema($db);
        $res = $db->query("SELECT chat_id FROM tg_bot_sessions");
        $ids = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $id = trim((string)($row['chat_id'] ?? ''));
            if ($id !== '') $ids[] = $id;
        }
        return $ids;
    } catch (Exception $e) {
        error_log('[TG] loadAuthorizedChats DB error: ' . $e->getMessage());
        return [];
    }
}
function loadAuthorizedTelegramSessions(): array {
    try {
        $db = getDB();
        ensureTelegramBotSchema($db);
        $res = $db->query("SELECT s.chat_id,s.email,s.authorized_at,s.telegram_user_id,u.name,u.role FROM tg_bot_sessions s LEFT JOIN users u ON u.email=s.email ORDER BY s.authorized_at DESC");
        $rows = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}
function loadAuthorizedAdminChats(): array {
    try {
        $db = getDB();
        ensureTelegramBotSchema($db);
        $res = $db->query("SELECT s.chat_id FROM tg_bot_sessions s INNER JOIN users u ON u.email=s.email WHERE u.role >= 2");
        $ids = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $id = trim((string)($row['chat_id'] ?? ''));
            if ($id !== '') $ids[] = $id;
        }
        return $ids;
    } catch (Throwable $e) {
        return [];
    }
}
function createTelegramLinkToken(string $email, string $createdBy): string {
    $db = getDB();
    ensureTelegramBotSchema($db);
    $purge = $db->prepare("DELETE FROM tg_bot_link_tokens WHERE expires_at < :n OR email = :e");
    $purge->bindValue(':n', time(), SQLITE3_INTEGER);
    $purge->bindValue(':e', $email, SQLITE3_TEXT);
    $purge->execute();
    $token = strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)));
    $stmt = $db->prepare("INSERT INTO tg_bot_link_tokens(token_hash,email,created_by,created_at,expires_at) VALUES(:h,:e,:c,:n,:x)");
    $stmt->bindValue(':h', hash('sha256', $token), SQLITE3_TEXT);
    $stmt->bindValue(':e', $email, SQLITE3_TEXT);
    $stmt->bindValue(':c', $createdBy, SQLITE3_TEXT);
    $stmt->bindValue(':n', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':x', time() + 900, SQLITE3_INTEGER);
    $stmt->execute();
    return $token;
}

function sendTelegramMessage($text, $domain = null){
    $cfg = loadTgBotConfig();
    if(empty($cfg['bot_token'])){
        error_log('[TG] sendTelegramMessage: bot_token is empty');
        return false;
    }
    $chatIds = loadAuthorizedAdminChats();
    if(empty($chatIds)){
        error_log('[TG] sendTelegramMessage: no authorized chat IDs found');
        return false;
    }
    // Do NOT urlencode the token — it must be passed verbatim in the URL path
    $token = trim($cfg['bot_token']);
    $url = 'https://api.telegram.org/bot'.$token.'/sendMessage';
    $anySuccess = false;
    foreach($chatIds as $chatId){
        $data = [
            'chat_id'                  => (string)$chatId,
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true
        ];
        if($domain){
            $data['reply_markup'] = buildTelegramInlineKeyboard($domain);
        }
        $ch = curl_init($url);
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_TIMEOUT,15);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if($result === false){
            error_log('[TG] curl error for chatId='.$chatId.': '.$curlError);
        } else {
            $decoded = json_decode($result, true);
            if($httpCode === 200 && isset($decoded['ok']) && $decoded['ok']){
                $anySuccess = true;
            } else {
                error_log('[TG] API error for chatId='.$chatId.': HTTP '.$httpCode.' response='.$result);
            }
        }
    }
    return $anySuccess;
}

function getTelegramDomain(){
    // Always return HTTPS domain
    return 'https://'.$_SERVER['HTTP_HOST'];
}

function buildTelegramInlineKeyboard($domain){
    return json_encode([
        'inline_keyboard' => [[
            ['text' => '🌐 Open '.parse_url($domain, PHP_URL_HOST), 'url' => $domain]
        ]]
    ]);
}

function sendTelegramPhoto($photoUrl, $caption){
    $cfg = loadTgBotConfig();
    if(empty($cfg['bot_token'])) return false;
    $chatIds = loadAuthorizedAdminChats();
    if(empty($chatIds)) return false;
    $token = trim($cfg['bot_token']);
    $domain = getTelegramDomain();
    $replyMarkup = buildTelegramInlineKeyboard($domain);

    // Try to download the photo and send as binary, fallback to URL
    $photoData = null;
    if($photoUrl && strpos($photoUrl, 'http') === 0){
        $ch = curl_init($photoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $photoData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($photoData === false || $httpCode !== 200) $photoData = null;
    } elseif($photoUrl && file_exists($photoUrl)){
        $photoData = file_get_contents($photoUrl);
    }

    $anySuccess = false;
    foreach($chatIds as $chatId){
        $url = 'https://api.telegram.org/bot'.$token.'/sendPhoto';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if($photoData !== null){
            $tmpFile = tempnam(sys_get_temp_dir(), 'tgphoto_');
            file_put_contents($tmpFile, $photoData);
            $postFields = [
                'chat_id'      => (string)$chatId,
                'photo'        => new CURLFile($tmpFile, 'image/jpeg', 'photo.jpg'),
                'caption'      => $caption,
                'parse_mode'   => 'HTML',
                'reply_markup' => $replyMarkup
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        } else {
            // Fallback: send by URL (use multipart so reply_markup works)
            $postFields = [
                'chat_id'      => (string)$chatId,
                'photo'        => $photoUrl ?: '',
                'caption'      => $caption,
                'parse_mode'   => 'HTML',
                'reply_markup' => $replyMarkup
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if(isset($tmpFile) && file_exists($tmpFile)) @unlink($tmpFile);
        $tmpFile = null;

        if($httpCode === 200){
            $anySuccess = true;
        } else {
            error_log('[TG] sendPhoto failed for chatId='.$chatId.': HTTP '.$httpCode.' resp='.$result);
        }
    }
    return $anySuccess;
}

function notifyTelegramNewUser($email, $name, $photo, $method){
    $domain = getTelegramDomain();
    $dt = date('Y-m-d H:i:s');
    $caption = "<b>🆕 New user registered</b>\n\n"
             . "👤 Name: ".my_htmlspecialchars($name)."\n"
             . "📧 Email: ".my_htmlspecialchars($email)."\n"
             . "🔑 Method: ".$method."\n"
             . "📅 Date: ".$dt;

    $hasPhoto = ($photo && $photo !== 'default_avatar.png');
    if($hasPhoto){
        $sent = sendTelegramPhoto($photo, $caption);
        if(!$sent){
            // Fallback to text message with button
            sendTelegramMessage($caption, $domain);
        }
    } else {
        sendTelegramMessage($caption, $domain);
    }
}

function notifyTelegramEmailVerified($email, $name){
    $domain = getTelegramDomain();
    $dt = date('Y-m-d H:i:s');
    $text = "<b>✅ Email verified</b>\n\n"
          . "👤 Name: ".my_htmlspecialchars($name)."\n"
          . "📧 Email: ".my_htmlspecialchars($email)."\n"
          . "📅 Date: ".$dt;
    sendTelegramMessage($text, $domain);
}

/**
 * JSON from the browser (hidden field client_context) — screen, timezone, optional geolocation, etc.
 */
function parseJsonClientContext(?string $raw): array {
    if ($raw === null || $raw === '') {
        return [];
    }
    if (strlen($raw) > 65536) {
        $raw = substr($raw, 0, 65536);
    }
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

/**
 * Request metadata available on the server (no passwords).
 */
function collectServerSecurityMeta(): array {
    $keys = [
        'HTTP_USER_AGENT', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_ENCODING', 'HTTP_ACCEPT',
        'HTTP_REFERER', 'HTTP_ORIGIN',
        'HTTP_SEC_CH_UA', 'HTTP_SEC_CH_UA_MOBILE', 'HTTP_SEC_CH_UA_PLATFORM',
        'HTTP_SEC_FETCH_SITE', 'HTTP_SEC_FETCH_MODE', 'HTTP_SEC_FETCH_USER', 'HTTP_SEC_FETCH_DEST',
        'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_CF_IPCOUNTRY',
        'HTTP_TRUE_CLIENT_IP', 'HTTP_FORWARDED', 'HTTP_DNT',
        'REQUEST_URI', 'REQUEST_METHOD', 'QUERY_STRING', 'HTTP_HOST', 'REMOTE_ADDR', 'SERVER_PROTOCOL',
    ];
    $out = [];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $out[$k] = $_SERVER[$k];
        }
    }
    return $out;
}

/**
 * Notify admins about failed authentication (password / OTP / Google OAuth).
 * $payload may include: attempted_email, client_ip, forwarded_for, cf_country, attempts, attempts_left,
 * lockout_sec, already_locked, oauth_error, client_context (array), server_meta (array).
 */
function notifyTelegramFailedAuth(string $event, array $payload): void {
    $labels = [
        'login_password' => '🔐 Failed password login',
        'otp_pin'        => '🔢 Failed one-time PIN',
        'google_oauth'   => '🔴 Google OAuth failed',
    ];
    $title = $labels[$event] ?? '⚠️ Auth failure';
    $dt    = date('Y-m-d H:i:s T');

    $lines   = [];
    $lines[] = '<b>' . $title . '</b>';
    $lines[] = '<b>Time:</b> ' . my_htmlspecialchars($dt);

    if (!empty($payload['already_locked'])) {
        $lines[] = '<b>Note:</b> attempt while IP already locked (no new counter increment).';
    }
    if (!empty($payload['attempted_email'])) {
        $lines[] = '<b>Attempted email:</b> ' . my_htmlspecialchars((string) $payload['attempted_email']);
    }
    if (!empty($payload['oauth_error'])) {
        $lines[] = '<b>OAuth error:</b> ' . my_htmlspecialchars(mb_substr((string) $payload['oauth_error'], 0, 600));
    }

    $lines[] = '<b>Effective IP:</b> ' . my_htmlspecialchars((string) ($payload['client_ip'] ?? 'unknown'));
    if (!empty($payload['forwarded_for'])) {
        $lines[] = '<b>X-Forwarded-For (raw):</b> ' . my_htmlspecialchars((string) $payload['forwarded_for']);
    }
    if (!empty($payload['cf_country'])) {
        $lines[] = '<b>CF-IPCountry:</b> ' . my_htmlspecialchars((string) $payload['cf_country']);
    }

    $lines[] = '<b>Failed attempts (counter):</b> ' . (int) ($payload['attempts'] ?? 0)
        . ' · <b>Remaining before lockout:</b> ' . (int) ($payload['attempts_left'] ?? 0);
    $lines[] = '<b>Lockout seconds left:</b> ' . (int) ($payload['lockout_sec'] ?? 0);

    $srv = $payload['server_meta'] ?? [];
    if (is_array($srv) && $srv !== []) {
        $enc = json_encode($srv, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($enc !== false) {
            if (strlen($enc) > 2200) {
                $enc = substr($enc, 0, 2197) . '…';
            }
            $lines[] = '<b>Server / proxy headers:</b>';
            $lines[] = '<pre>' . my_htmlspecialchars($enc) . '</pre>';
        }
    }

    $cc = $payload['client_context'] ?? [];
    if (is_array($cc) && $cc !== []) {
        $enc = json_encode($cc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($enc !== false) {
            if (strlen($enc) > 2400) {
                $enc = substr($enc, 0, 2397) . '…';
            }
            $lines[] = '<b>Browser-reported context</b> (timezone, screen, optional GPS if allowed):';
            $lines[] = '<pre>' . my_htmlspecialchars($enc) . '</pre>';
        }
    } else {
        $lines[] = '<i>No client_context (non-JS client or empty field).</i>';
    }

    $text = implode("\n", $lines);
    if (strlen($text) > 4000) {
        $text = substr($text, 0, 3997) . '…';
    }
    @sendTelegramMessage($text, getTelegramDomain());
}

/**
 * Common fields for notifyTelegramFailedAuth (call after recordFailedAttempt when counter should reflect DB).
 */
function failedAuthNotifyPayload(string $clientIp, string $lockType): array {
    $lockout = getLockout($clientIp, $lockType);
    $maxAttempts = $lockType === 'otp' ? 3 : 10;
    return [
        'client_ip'      => $clientIp,
        'forwarded_for'  => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        'cf_country'     => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '',
        'attempts'       => (int) $lockout['attempts'],
        'attempts_left'  => max(0, $maxAttempts - (int) $lockout['attempts']),
        'lockout_sec'    => getIpLockoutSeconds($clientIp, $lockType),
        'client_context' => parseJsonClientContext($_POST['client_context'] ?? ''),
        'server_meta'    => collectServerSecurityMeta(),
    ];
}

/**
 * Given a user's data (email, name), generate possible folder names using the same patterns
 * as the examples: kotenkoigorandr791@gmail.com => kotenkoigorandr791 | kotenkoigorandr791_gmail_com | Ihor_Kotenko
 */
function getUserFolderVariants($email, $name){
    $variants = [];
    // Pattern 1: part before @ in email  (e.g. kotenkoigorandr791)
    $emailLocal = explode('@', $email)[0];
    $variants[] = $emailLocal;
    // Pattern 2: email with @ and . replaced by _  (e.g. kotenkoigorandr791_gmail_com)
    $emailNoDomain = str_replace(['@', '.'], '_', $email);
    $variants[] = $emailNoDomain;
    // Pattern 3: Name with spaces replaced by _  (e.g. Ihor_Kotenko)
    if($name){
        $variants[] = str_replace(' ', '_', $name);
        // lowercase variant
        $variants[] = strtolower(str_replace(' ', '_', $name));
        // uppercase variant
        $variants[] = strtoupper(str_replace(' ', '_', $name));
    }
    // Also add email with dots replaced by underscore
    $emailDotToUnderscore = str_replace('.', '_', $email);
    $variants[] = $emailDotToUnderscore;
    // Remove duplicates
    return array_unique(array_filter($variants));
}

function collectUserStorageTargets($email, $name, $appPaths){
    $userVariants = getUserFolderVariants($email, $name);
    $targets = [];
    foreach($appPaths as $appName => $basePath){
        $basePath = trim((string)$basePath);
        if($basePath === '') continue;
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        foreach($userVariants as $variant){
            $candidate = $basePath . '/' . $variant;
            $resolved = realpath($candidate);
            if($resolved === false || !is_dir($resolved)) continue;
            $normalized = str_replace('\\', '/', $resolved);
            $targets[$normalized] = [
                'app' => (string)$appName,
                'folder' => $normalized,
            ];
        }
    }
    return array_values($targets);
}

/**
 * Scan app folder paths for user folders and return list of files with sizes.
 */
function scanUserFiles($email, $name, $appPaths){
    $result = [];
    foreach(collectUserStorageTargets($email, $name, $appPaths) as $target){
        foreach(scanFilesRecursive($target['folder']) as $file){
            $size = @filesize($file);
            $result[] = [
                'app' => $target['app'],
                'folder' => $target['folder'],
                'path' => $file,
                'size' => $size !== false ? (int)$size : 0,
                'name' => basename($file)
            ];
        }
    }
    return $result;
}

function scanFilesRecursive($dir){
    $result = [];
    if(!is_dir($dir)) return $result;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach($items as $item){
        if($item->isFile()){
            $result[] = $item->getPathname();
        }
    }
    return $result;
}

/**
 * Count total files and folders for a user (for progress estimation)
 */
function countUserFiles($email, $name, $appPaths){
    $totalCount = 0;
    $totalFolders = 0;
    foreach(collectUserStorageTargets($email, $name, $appPaths) as $target){
        $totalFolders++;
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target['folder'], RecursiveDirectoryIterator::SKIP_DOTS));
        foreach($items as $item){
            if($item->isFile()){
                $totalCount++;
            }
        }
    }
    return ['files' => $totalCount, 'folders' => $totalFolders];
}

/**
 * Scan user files progressively with progress updates
 */
function scanUserFilesProgressive($email, $name, $appPaths, $progressCallback = null){
    $result = [];
    $totalFolders = 0;
    $totalFilesCount = 0;
    $targets = collectUserStorageTargets($email, $name, $appPaths);
    
    // First, count total files and folders for progress calculation
    foreach($targets as $target){
        $totalFolders++;
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target['folder'], RecursiveDirectoryIterator::SKIP_DOTS));
        foreach($items as $item){
            if($item->isFile()){
                $totalFilesCount++;
            }
        }
    }
    
    $scannedFiles = 0;
    foreach($targets as $target){
        $files = scanFilesRecursive($target['folder']);
        foreach($files as $file){
            $size = @filesize($file);
            $result[] = [
                'app' => $target['app'],
                'folder' => $target['folder'],
                'path' => $file,
                'size' => $size !== false ? (int)$size : 0,
                'name' => basename($file)
            ];
            $scannedFiles++;
        }
        
        // Report progress if callback provided
        if($progressCallback && $totalFilesCount > 0){
            $progressPercent = ($scannedFiles / $totalFilesCount) * 100;
            $totalSize = array_sum(array_column($result, 'size'));
            $progressCallback($progressPercent, $result, $totalSize);
        }
    }
    return [
        'files' => $result,
        'total_files' => count($result),
        'total_folders' => $totalFolders,
        'total_size' => array_sum(array_column($result, 'size'))
    ];
}

function formatBytes($bytes){
    if($bytes === 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $exp = (int)floor(log($bytes, 1024));
    $exp = min($exp, count($units)-1);
    return round($bytes / pow(1024, $exp), 2) . ' ' . $units[$exp];
}

/**
 * Delete all files in user folders identified for a given user
 */
function deleteUserFolders($email, $name, $appPaths){
    $deleted = [];
    foreach(collectUserStorageTargets($email, $name, $appPaths) as $target){
        if(is_dir($target['folder'])){
            deleteDirectory($target['folder']);
            $deleted[] = $target['folder'];
        }
    }
    return $deleted;
}

function deleteDirectory($dir){
    if(!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach($items as $item){
        if($item->isDir()){
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}

// loadResetCodes() provided by db.php

// saveResetCodes() provided by db.php

// loadVerificationTokens() provided by db.php

// saveVerificationTokens() provided by db.php

// ── News Portal API ───────────────────────────────────────────────────────────
if (isset($_GET['api']) && $_GET['api'] === 'news') {
    $newsFile = APP_ROOT . '/data/news.json';
    $uploadsDir = APP_ROOT . '/uploads/news/';
    $db = getDB();
    $userRole = (int)($_SESSION['user']['role'] ?? -1);
    $userEmail = (string)($_SESSION['user']['email'] ?? '');
    $canEdit = isset($_SESSION['user']) && $userRole >= 1;

    function newsLoadAll($f) {
        if (!is_file($f)) return [];
        $d = json_decode(file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }
    function newsSave($f, $items) {
        file_put_contents($f, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    function newsBool($value): bool {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
    function newsTableColumns(AppDatabase $db, string $table): array {
        $res = $db->query("PRAGMA table_info($table)");
        $cols = [];
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            if (isset($row['name'])) $cols[] = (string)$row['name'];
        }
        return $cols;
    }
    function newsEnsureColumn(AppDatabase $db, array &$cols, string $table, string $column, string $definition): void {
        if (in_array($column, $cols, true)) return;
        try {
            $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            $cols[] = $column;
        } catch (Throwable $e) {}
    }
    function newsEnsureDb(AppDatabase $db, string $newsFile): void {
        $db->exec("CREATE TABLE IF NOT EXISTS app_news (
            id TEXT PRIMARY KEY,
            title TEXT NOT NULL,
            body TEXT NOT NULL DEFAULT '',
            photo TEXT NOT NULL DEFAULT '',
            published INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            updated_at INTEGER,
            author TEXT NOT NULL DEFAULT ''
        )");
        $cols = newsTableColumns($db, 'app_news');
        newsEnsureColumn($db, $cols, 'app_news', 'id', "TEXT NOT NULL DEFAULT ''");
        newsEnsureColumn($db, $cols, 'app_news', 'title', "TEXT NOT NULL DEFAULT ''");
        newsEnsureColumn($db, $cols, 'app_news', 'body', "TEXT NOT NULL DEFAULT ''");
        newsEnsureColumn($db, $cols, 'app_news', 'photo', "TEXT NOT NULL DEFAULT ''");
        newsEnsureColumn($db, $cols, 'app_news', 'published', "INTEGER NOT NULL DEFAULT 0");
        newsEnsureColumn($db, $cols, 'app_news', 'created_at', "INTEGER NOT NULL DEFAULT 0");
        newsEnsureColumn($db, $cols, 'app_news', 'updated_at', "INTEGER");
        newsEnsureColumn($db, $cols, 'app_news', 'author', "TEXT NOT NULL DEFAULT ''");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_app_news_created_at ON app_news(created_at)");
        if ((int)$db->querySingle("SELECT COUNT(*) FROM app_news") > 0 || !is_file($newsFile)) return;
        $items = json_decode((string)file_get_contents($newsFile), true);
        if (!is_array($items)) return;
        foreach ($items as $item) {
            if (!is_array($item) || trim((string)($item['title'] ?? '')) === '') continue;
            $stmt = $db->prepare("INSERT INTO app_news (id, title, body, photo, published, created_at, updated_at, author) VALUES (:id, :title, :body, :photo, :published, :created_at, :updated_at, :author)");
            $stmt->bindValue(':id', trim((string)($item['id'] ?? '')) ?: bin2hex(random_bytes(8)), SQLITE3_TEXT);
            $stmt->bindValue(':title', trim((string)$item['title']), SQLITE3_TEXT);
            $stmt->bindValue(':body', (string)($item['body'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':photo', trim((string)($item['photo'] ?? '')), SQLITE3_TEXT);
            $stmt->bindValue(':published', !empty($item['published']) ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(':created_at', (int)($item['created_at'] ?? time()), SQLITE3_INTEGER);
            $stmt->bindValue(':updated_at', isset($item['updated_at']) ? (int)$item['updated_at'] : null, isset($item['updated_at']) ? SQLITE3_INTEGER : SQLITE3_NULL);
            $stmt->bindValue(':author', (string)($item['author'] ?? ''), SQLITE3_TEXT);
            $stmt->execute();
        }
    }
    function newsLoadAllDb(AppDatabase $db, bool $canEdit): array {
        $sql = "SELECT id, title, body, photo, published, created_at, updated_at, author FROM app_news";
        if (!$canEdit) $sql .= " WHERE published = 1";
        $sql .= " ORDER BY created_at DESC";
        $res = $db->query($sql);
        $items = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $row['published'] = newsBool($row['published'] ?? 0);
            $row['created_at'] = (int)($row['created_at'] ?? 0);
            $row['updated_at'] = isset($row['updated_at']) ? (int)$row['updated_at'] : null;
            $items[] = $row;
        }
        return $items;
    }
    newsEnsureDb($db, $newsFile);

    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
    header('Content-Type: application/json; charset=utf-8');

    if ($action === 'list') {
        $items = newsLoadAllDb($db, $canEdit);
        $layoutFile = APP_ROOT . '/data/news_layout.json';
        $layout = is_file($layoutFile) ? (json_decode(file_get_contents($layoutFile), true) ?: []) : [];
        echo json_encode(['ok' => true, 'items' => $items, 'can_edit' => $canEdit, 'layout' => $layout]);
        exit;
    }

    if ($action === 'ai_generate') {
        if (!$canEdit) { echo json_encode(['ok' => false, 'error' => 'forbidden']); exit; }
        apSecRequireSameOrigin();
        $input = json_decode((string)file_get_contents('php://input'), true);
        $prompt = trim((string)($input['prompt'] ?? ''));
        if ($prompt === '') { echo json_encode(['ok' => false, 'error' => 'prompt_required']); exit; }
        try {
            require_once APP_ROOT . '/apps/cloud/includes/ai_config_store.php';
            require_once APP_ROOT . '/apps/cloud/includes/ai_proxy.php';
            $aiCfg = cloud_ai_load_config();
            if (empty($aiCfg['api_key']) || empty($aiCfg['api_endpoint'])) {
                echo json_encode(['ok' => false, 'error' => 'ai_not_configured']); exit;
            }
            $currentTitle = trim((string)($input['current_title'] ?? ''));
        $currentBody = trim((string)($input['current_body'] ?? ''));
        
        $customSystemPrompt = trim((string)($input['system_prompt'] ?? ''));
        $systemMsg = $customSystemPrompt !== '' ? $customSystemPrompt . ' ' : 'You are a news article writer. ';
        if ($currentTitle || $currentBody) {
            $systemMsg .= 'The user is editing an existing article. Current title: "' . $currentTitle . '". Current content: "' . $currentBody . '". ';
        }
        $systemMsg .= 'Given a topic or prompt, improve or rewrite the article. Respond with valid JSON only: {"title":"...","body":"..."}. The body should be written in Markdown format. Use headings, lists, bold, italic, code blocks, and other Markdown syntax as appropriate for the content.';
            $selectedModel = trim((string)($input['model'] ?? ''));
            $model = cloud_ai_normalize_model_id(
                in_array($selectedModel, $aiCfg['models']) ? $selectedModel : 
                (string)($aiCfg['default_model'] ?: ($aiCfg['models'][0] ?? ''))
            );
            
            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemMsg],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 800,
                'temperature' => 0.7,
                'stream' => false
            ];
            if ($payload['model'] === '') {
                echo json_encode(['ok' => false, 'error' => 'ai_model_required']); exit;
            }
            $result = cloud_ai_proxy_json($aiCfg, $aiCfg['api_endpoint'], $payload, 'POST');
            $raw = $result['body'];
            if (!$result['ok']) {
                $err = json_decode($raw, true);
                $message = $result['error'] ?: (string)($err['error']['message'] ?? $err['message'] ?? 'ai_request_failed');
                echo json_encode(['ok' => false, 'error' => $message]); exit;
            }
            $resp = json_decode($raw, true);
            $text = trim((string)($resp['choices'][0]['message']['content'] ?? ''));
            $jsonStart = strpos($text, '{');
            $jsonEnd = strrpos($text, '}');
            if ($jsonStart !== false && $jsonEnd !== false) {
                $text = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
            }
            $parsed = json_decode($text, true);
            if (isset($parsed['title'], $parsed['body'])) {
                echo json_encode(['ok' => true, 'title' => (string)$parsed['title'], 'body' => (string)$parsed['body']]);
            } else {
                echo json_encode(['ok' => true, 'title' => '', 'body' => $text]);
            }
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'ai_error']);
        }
        exit;
    }

    if ($action === 'save_layout') {
        if (!$canEdit) { echo json_encode(['ok' => false, 'error' => 'forbidden']); exit; }
        apSecRequireSameOrigin();
        $input = json_decode((string)file_get_contents('php://input'), true);
        $layout = $input['layout'] ?? [];
        if (!is_array($layout)) $layout = [];
        $clean = [];
        foreach ($layout as $id => $pos) {
            if (is_string($id) && is_array($pos)) {
                $clean[preg_replace('/[^a-f0-9]/i', '', $id)] = ['x' => (int)($pos['x'] ?? 0), 'y' => (int)($pos['y'] ?? 0)];
            }
        }
        $layoutFile = APP_ROOT . '/data/news_layout.json';
        file_put_contents($layoutFile, json_encode($clean, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'save') {
        if (!$canEdit) { echo json_encode(['ok' => false, 'error' => 'forbidden']); exit; }
        apSecRequireSameOrigin();
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;
        $id = trim((string)($input['id'] ?? ''));
        $title = trim((string)($input['title'] ?? ''));
        $body = trim((string)($input['body'] ?? ''));
        $published = !empty($input['published']);
        $photo = trim((string)($input['photo'] ?? ''));
        if ($title === '') { echo json_encode(['ok' => false, 'error' => 'title_required']); exit; }
        $now = time();
        if ($id === '') {
            $id = bin2hex(random_bytes(8));
            $stmt = $db->prepare("INSERT INTO app_news (id, title, body, photo, published, created_at, author) VALUES (:id, :title, :body, :photo, :published, :created_at, :author)");
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':body', $body, SQLITE3_TEXT);
            $stmt->bindValue(':photo', $photo, SQLITE3_TEXT);
            $stmt->bindValue(':published', $published ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(':created_at', $now, SQLITE3_INTEGER);
            $stmt->bindValue(':author', $userEmail, SQLITE3_TEXT);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("UPDATE app_news SET title = :title, body = :body, photo = :photo, published = :published, updated_at = :updated_at WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':body', $body, SQLITE3_TEXT);
            $stmt->bindValue(':photo', $photo, SQLITE3_TEXT);
            $stmt->bindValue(':published', $published ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(':updated_at', $now, SQLITE3_INTEGER);
            $stmt->execute();
        }
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    if ($action === 'delete') {
        if (!$canEdit) { echo json_encode(['ok' => false, 'error' => 'forbidden']); exit; }
        apSecRequireSameOrigin();
        $input = json_decode((string)file_get_contents('php://input'), true);
        $id = trim((string)($input['id'] ?? ''));
        $stmt = $db->prepare("DELETE FROM app_news WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->execute();
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'upload_photo') {
        if (!$canEdit) { echo json_encode(['ok' => false, 'error' => 'forbidden']); exit; }
        apSecRequireSameOrigin();
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'upload_error']); exit;
        }
        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
        $file = $_FILES['photo'];
        $mime = apSecDetectMimeFromFile($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
            echo json_encode(['ok' => false, 'error' => 'invalid_type']); exit;
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
        $filename = apSecRandomFilename($ext);
        move_uploaded_file($file['tmp_name'], $uploadsDir . $filename);
        echo json_encode(['ok' => true, 'url' => 'uploads/news/' . $filename]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'unknown_action']);
    exit;
}
