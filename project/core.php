<?php
require_once APP_ROOT . '/storage_helper.php';

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
    $cacheDir = appdata_uploads_base_dir() . '/avatar_cache';
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
    if ($isRoot) {
        $eH = my_htmlspecialchars($email);
        $nH = my_htmlspecialchars($name);
        $eJ = addslashes($email);
        $nJ = addslashes($name);
        $menuLabel = my_htmlspecialchars($translations['user_actions'] ?? 'User actions');
        $resetLabel = my_htmlspecialchars($translations['reset_user_password'] ?? 'Reset password');
        return '<div class="ap-user-actions-menu">'
            . '<button type="button" class="ap-user-menu-btn" title="' . $menuLabel . '" aria-label="' . $menuLabel . '" aria-haspopup="true" aria-expanded="false" data-email="' . $eH . '" data-name="' . $nH . '">'
            . '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>'
            . '</button>'
            . '<div class="ap-user-menu-dropdown" role="menu">'
            . '<button type="button" class="ap-user-menu-item" role="menuitem" onclick="apCloseUserMenus();apOpenResetPassword(\'' . $eJ . '\',\'' . $nJ . '\')">' . $resetLabel . '</button>'
            . '</div></div>';
    }
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
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        last_used_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        expires_at INTEGER NOT NULL,
        revoked INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_tokens_email ON client_auth_tokens(email)");
}
function createClientAuthToken(AppDatabase $db, string $email, string $deviceId, string $deviceName, string $platform): array {
    ensureClientAuthTokensSchema($db);
    $purge = $db->prepare('DELETE FROM client_auth_tokens WHERE expires_at < :t');
    $purge->bindValue(':t', time(), PDO::PARAM_INT);
    $purge->execute();
    if ($deviceId !== '' && $deviceId !== 'mcp_external') {
        $del = $db->prepare('DELETE FROM client_auth_tokens WHERE email = :e AND device_id = :d');
        $del->bindValue(':e', $email, PDO::PARAM_STR);
        $del->bindValue(':d', $deviceId, PDO::PARAM_STR);
        $del->execute();
    }
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $now = time();
    $expiresAt = $now + (180 * 24 * 3600);
    $stmt = $db->prepare("INSERT INTO client_auth_tokens(token_hash,email,device_id,device_name,platform,created_at,last_used_at,expires_at,revoked) VALUES(:h,:e,:d,:n,:p,:c,:u,:x,0)");
    $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':d', $deviceId, PDO::PARAM_STR);
    $stmt->bindValue(':n', $deviceName, PDO::PARAM_STR);
    $stmt->bindValue(':p', $platform, PDO::PARAM_STR);
    $stmt->bindValue(':c', $now, PDO::PARAM_INT);
    $stmt->bindValue(':u', $now, PDO::PARAM_INT);
    $stmt->bindValue(':x', $expiresAt, PDO::PARAM_INT);
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
        created_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        approved_at INTEGER,
        expires_at INTEGER NOT NULL,
        claimed_at INTEGER
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_client_auth_browser_requests_status ON client_auth_browser_requests(status, expires_at)");
}
function purgeClientBrowserAuthRequests(AppDatabase $db): void {
    ensureClientBrowserAuthSchema($db);
    $stmt = $db->prepare("DELETE FROM client_auth_browser_requests WHERE expires_at < :now OR (status = 'claimed' AND claimed_at IS NOT NULL AND claimed_at < :old)");
    $stmt->bindValue(':now', time(), PDO::PARAM_INT);
    $stmt->bindValue(':old', time() - 86400, PDO::PARAM_INT);
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
    $stmt->bindValue(':r', $requestId, PDO::PARAM_STR);
    $stmt->bindValue(':p', hash('sha256', $pollToken), PDO::PARAM_STR);
    $stmt->bindValue(':b', hash('sha256', $browserToken), PDO::PARAM_STR);
    $stmt->bindValue(':c', $callbackUrl, PDO::PARAM_STR);
    $stmt->bindValue(':d', $deviceId, PDO::PARAM_STR);
    $stmt->bindValue(':n', $deviceName, PDO::PARAM_STR);
    $stmt->bindValue(':pl', $platform, PDO::PARAM_STR);
    $stmt->bindValue(':x', $expiresAt, PDO::PARAM_INT);
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
    $stmt->bindValue(':r', $requestId, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
function clientBrowserAuthMatches(string $plain, string $hash): bool {
    return $plain !== '' && $hash !== '' && hash_equals($hash, hash('sha256', $plain));
}
function clientAuthSessionOk(): bool {
    return ensureAuthenticatedSession();
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
    appClearPendingPasswordFlow();
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
function scheduleTrustedDevicePromptIfNeeded(string $email): void {
    $fp = getDeviceFingerprint();
    $isTrusted = isTrustedDevice($email, $fp);
    if ($isTrusted && getTrustedDeviceCookieForEmail($email) !== '') {
        rememberTrustedDeviceBrowserAccount($email);
        setTrustedDeviceHintCookie($email, $fp);
    }
    if (!$isTrusted) {
        $_SESSION['new_device_login'] = true;
        $_SESSION['new_device_login_at'] = time() + random_int(30, 180);
    } else {
        unset($_SESSION['new_device_login'], $_SESSION['new_device_login_at']);
    }
}
function consumeTrustedDevicePromptDelayMs(): ?int {
    if (empty($_SESSION['new_device_login']) || !isset($_SESSION['user']['email'])) return null;
    $email = (string)$_SESSION['user']['email'];
    if (isTrustedDevice($email, getDeviceFingerprint())) {
        unset($_SESSION['new_device_login'], $_SESSION['new_device_login_at']);
        return null;
    }
    $showAt = (int)($_SESSION['new_device_login_at'] ?? 0);
    unset($_SESSION['new_device_login'], $_SESSION['new_device_login_at']);
    return max(0, $showAt - time()) * 1000;
}

$trustedDeviceRestoreNotice = null;
$trustedDeviceHint = parseTrustedDeviceHintCookie((string)($_COOKIE['trusted_device_hint'] ?? ''));

function oauthLoginRedirectUri(): string {
    $base = rtrim(trim((string)getConfig('tg_webapp_base_url', 'https://serverkherson.xyz')), '/');
    if ($base !== '' && preg_match('#^https?://#i', $base)) {
        return $base . '/login/oauth/callback';
    }
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
            if(!$trustedDeviceAutoLoginSuppressed && ensureAuthenticatedSession()){
                $needsAuth=false;
            } else {
                removeUserFromActiveSessions($_SESSION['user']['email'] ?? '',session_id());
                session_destroy();
                $needsAuth=true;
            }
        }
    }elseif(!isset($_SESSION['user'])){
        if(!$trustedDeviceAutoLoginSuppressed && ensureAuthenticatedSession()){
            $needsAuth=false;
        } else {
            if ($trustedDeviceHint && trustedDeviceExistsForHint((string)$trustedDeviceHint['email'], (string)$trustedDeviceHint['fingerprint'])) {
                $trustedDeviceRestoreNotice = 'Saved session on this device could not be restored. Sign in again to renew it.';
            } else {
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
    $stmt->bindValue(':u', $userEmail, PDO::PARAM_STR);
    $stmt->bindValue(':a', $appId, PDO::PARAM_STR);
    $stmt->bindValue(':s', $severity, PDO::PARAM_STR);
    $stmt->bindValue(':t', $title, PDO::PARAM_STR);
    $stmt->bindValue(':m', $message, PDO::PARAM_STR);
    $stmt->bindValue(':e', $eventType, PDO::PARAM_STR);
    $stmt->bindValue(':c', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
    $stmt->bindValue(':n', time(), PDO::PARAM_INT);
    $stmt->execute();
    if (in_array($severity, ['warning','error','critical'], true)) {
        $telegramEnabled = 1;
        if ($userEmail !== '') {
            $set = $db->prepare("SELECT telegram_enabled FROM app_notification_settings WHERE email = :u");
            $set->bindValue(':u', $userEmail, PDO::PARAM_STR);
            $setRow = $set->execute()->fetch(PDO::FETCH_ASSOC);
            $telegramEnabled = (int)($setRow['telegram_enabled'] ?? 1);
        }
        if ($telegramEnabled) {
        $label = $appId !== '' ? '[' . $appId . '] ' : '';
        @sendTelegramMessage(my_htmlspecialchars($label . $title . ($message !== '' ? "\n" . $message : '')));
        }
    }
    return (int)$db->lastInsertRowID();
}
function apNotificationAppMinRole(string $appId, array $appAccessLevels): int {
    $appId = trim($appId);
    if ($appId === '') return 1;
    if (array_key_exists($appId, $appAccessLevels)) {
        return max(0, min(2, (int)$appAccessLevels[$appId]));
    }
    return 2;
}
function apNotificationVisibleTo(array $row, string $userEmail, int $userRole, array $appAccessLevels): bool {
    $owner = (string)($row['user_email'] ?? '');
    $appId = trim((string)($row['app_id'] ?? ''));
    $eventType = trim((string)($row['event_type'] ?? ''));
    if ($userRole >= 2) return true;
    if ($eventType === 'registration_pending' || $appId === 'registration') return false;
    if (in_array($appId, ['server','system','logs','docker','backup'], true) || str_starts_with($eventType, 'server_')) {
        return $userRole >= 1;
    }
    if ($owner !== '') {
        return strcasecmp($owner, $userEmail) === 0;
    }
    if ($userRole < 1) return false;
    if ($appId === '') return true;
    return $userRole >= apNotificationAppMinRole($appId, $appAccessLevels);
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
        $email = apNormalizeAuthEmail((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $deviceId = trim((string)($input['device_id'] ?? ''));
        $deviceName = trim((string)($input['device_name'] ?? ''));
        $platform = trim((string)($input['platform'] ?? ''));
        if ($email === '' || $password === '') json_out(['ok' => false, 'error' => 'missing_credentials'], 400);
        $users = loadUsers();
        if (!isset($users[$email]) || !appPasswordVerify($password, $users[$email]['password'])) json_out(['ok' => false, 'error' => 'invalid_credentials'], 401);
        if (appPasswordNeedsRehash((string)$users[$email]['password'])) {
            $users[$email]['password'] = appPasswordHash($password);
            upsertUser($email, $users[$email]);
        }
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
        $stmt->bindValue(':h', hash('sha256', $token), PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_out(['ok' => false, 'error' => 'invalid_token'], 401);
        if ((int)$row['expires_at'] < time()) json_out(['ok' => false, 'error' => 'token_expired'], 401);
        if ($deviceId !== '' && (string)$row['device_id'] !== '' && $deviceId !== (string)$row['device_id']) json_out(['ok' => false, 'error' => 'device_mismatch'], 403);
        $users = loadUsers();
        $email = (string)($row['email'] ?? '');
        if (!loginUserSession($email, $users)) json_out(['ok' => false, 'error' => 'user_not_found'], 404);
        $upd = $db->prepare("UPDATE client_auth_tokens SET last_used_at = :u WHERE token_hash = :h");
        $upd->bindValue(':u', time(), PDO::PARAM_INT);
        $upd->bindValue(':h', hash('sha256', $token), PDO::PARAM_STR);
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
        $upd->bindValue(':t', time(), PDO::PARAM_INT);
        $upd->bindValue(':r', $requestId, PDO::PARAM_STR);
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
        $upd->bindValue(':e', $email, PDO::PARAM_STR);
        $upd->bindValue(':t', $tokenData['token'], PDO::PARAM_STR);
        $upd->bindValue(':a', time(), PDO::PARAM_INT);
        $upd->bindValue(':r', $requestId, PDO::PARAM_STR);
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
            $stmt->bindValue(':h', hash('sha256', $token), PDO::PARAM_STR);
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
    $userRole = (int)($_SESSION['user']['role'] ?? 0);
    $appAccessLevels = loadAppAccessLevels();
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $action = (string)($input['action'] ?? $_GET['action'] ?? '');
    if (PHP_SESSION_ACTIVE === session_status()) {
        session_write_close();
    }
    if ($action === 'list') {
        $limit = max(1, min(100, (int)($_GET['limit'] ?? $input['limit'] ?? 30)));
        $stmt = $db->prepare("SELECT id,user_email,app_id,severity,title,message,event_type,is_read,created_at FROM app_notifications WHERE user_email = '' OR user_email = :u ORDER BY created_at DESC, id DESC LIMIT :l");
        $stmt->bindValue(':u', $userEmail, PDO::PARAM_STR);
        $stmt->bindValue(':l', $userRole >= 2 ? $limit : max($limit * 20, 500), PDO::PARAM_INT);
        $res = $stmt->execute();
        $items = [];
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            if (!apNotificationVisibleTo($row, $userEmail, $userRole, $appAccessLevels)) continue;
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
            if (count($items) >= $limit) break;
        }
        $cnt = $db->prepare("SELECT id,user_email,app_id,event_type FROM app_notifications WHERE (user_email = '' OR user_email = :u) AND is_read = 0");
        $cnt->bindValue(':u', $userEmail, PDO::PARAM_STR);
        $cntRes = $cnt->execute();
        $unread = 0;
        while ($row = $cntRes->fetch(PDO::FETCH_ASSOC)) {
            if (apNotificationVisibleTo($row, $userEmail, $userRole, $appAccessLevels)) $unread++;
        }
        json_out(['ok' => true, 'items' => $items, 'unread' => $unread]);
    }
    if ($action === 'mark_read') {
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
        if ($id > 0) {
            $check = $db->prepare("SELECT id,user_email,app_id,event_type FROM app_notifications WHERE id = :id AND (user_email = '' OR user_email = :u) LIMIT 1");
            $check->bindValue(':id', $id, PDO::PARAM_INT);
            $check->bindValue(':u', $userEmail, PDO::PARAM_STR);
            $row = $check->execute()->fetch(PDO::FETCH_ASSOC);
            if (!$row || !apNotificationVisibleTo($row, $userEmail, $userRole, $appAccessLevels)) {
                json_out(['ok' => true]);
            }
            $stmt = $db->prepare("UPDATE app_notifications SET is_read = 1, read_at = :t WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':t', time(), PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("SELECT id,user_email,app_id,event_type FROM app_notifications WHERE (user_email = '' OR user_email = :u) AND is_read = 0");
            $stmt->bindValue(':u', $userEmail, PDO::PARAM_STR);
            $res = $stmt->execute();
            $ids = [];
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                if (apNotificationVisibleTo($row, $userEmail, $userRole, $appAccessLevels)) $ids[] = (int)$row['id'];
            }
            if ($ids) {
                $upd = $db->prepare("UPDATE app_notifications SET is_read = 1, read_at = :t WHERE id = :id");
                foreach ($ids as $visibleId) {
                    $upd->bindValue(':id', $visibleId, PDO::PARAM_INT);
                    $upd->bindValue(':t', time(), PDO::PARAM_INT);
                    $upd->execute();
                }
            }
        }
        json_out(['ok' => true]);
    }
    if ($action === 'settings') {
        $stmt = $db->prepare("SELECT browser_enabled, telegram_enabled FROM app_notification_settings WHERE email = :u");
        $stmt->bindValue(':u', $userEmail, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        json_out(['ok' => true, 'settings' => ['browser_enabled' => (int)($row['browser_enabled'] ?? 0), 'telegram_enabled' => (int)($row['telegram_enabled'] ?? 1)]]);
    }
    if ($action === 'save_settings') {
        $browser = !empty($input['browser_enabled']) ? 1 : 0;
        $telegram = array_key_exists('telegram_enabled', $input) ? (!empty($input['telegram_enabled']) ? 1 : 0) : 1;
        $stmt = $db->prepare("INSERT INTO app_notification_settings(email,browser_enabled,telegram_enabled,updated_at) VALUES(:u,:b,:g,:t) ON CONFLICT(email) DO UPDATE SET browser_enabled = excluded.browser_enabled, telegram_enabled = excluded.telegram_enabled, updated_at = excluded.updated_at");
        $stmt->bindValue(':u', $userEmail, PDO::PARAM_STR);
        $stmt->bindValue(':b', $browser, PDO::PARAM_INT);
        $stmt->bindValue(':g', $telegram, PDO::PARAM_INT);
        $stmt->bindValue(':t', time(), PDO::PARAM_INT);
        $stmt->execute();
        json_out(['ok' => true]);
    }
    if ($action === 'metric') {
        $appId = trim((string)($input['app_id'] ?? ''));
        $metric = trim((string)($input['metric'] ?? ''));
        if ($metric === '') json_out(['ok' => false, 'error' => 'missing_metric'], 400);
        $stmt = $db->prepare("INSERT INTO app_metrics(app_id, metric, value, unit, user_email, context_json, created_at) VALUES(:a,:m,:v,:unit,:u,:c,:t)");
        $stmt->bindValue(':a', $appId, PDO::PARAM_STR);
        $stmt->bindValue(':m', $metric, PDO::PARAM_STR);
        $stmt->bindValue(':v', (float)($input['value'] ?? 0), PDO::PARAM_STR);
        $stmt->bindValue(':unit', trim((string)($input['unit'] ?? '')), PDO::PARAM_STR);
        $stmt->bindValue(':u', $userEmail, PDO::PARAM_STR);
        $stmt->bindValue(':c', json_encode(is_array($input['context'] ?? null) ? $input['context'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        $stmt->bindValue(':t', time(), PDO::PARAM_INT);
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

// ── Onboarding API ───────────────────────────────────────────────────────────
if (isset($_GET['api']) && $_GET['api'] === 'onboarding') { try {
    $sessionOk = isset($_SESSION['user']['email'], $_SESSION['auth_time']) && getUserFromActiveSessions(session_id()) !== null;
    if (!$sessionOk) json_out(['ok' => false, 'error' => 'not_authenticated'], 401);
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $action = (string)($input['action'] ?? $_GET['action'] ?? '');
    if ($action === 'done') {
        if (isset($_SESSION['user'])) $_SESSION['user']['onboarding_done'] = true;
        json_out(['ok' => true]);
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
            $stmt->bindValue(':me', $userEmail, PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            json_out(['ok' => true, 'count' => (int)($row['c'] ?? 0)]);
        }
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM support_chat WHERE user_email = :u AND is_read = 0 AND sender_email != :u2');
        $stmt->bindValue(':u', $userEmail, PDO::PARAM_STR);
        $stmt->bindValue(':u2', $userEmail, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
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
            $stmt->bindValue(':email', $chatUserEmail, PDO::PARAM_STR);
            $result = $stmt->execute();
            $messages = [];
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $messages[] = $mapRow($row);
            }
            json_out(['ok' => true, 'messages' => $messages, 'mode' => 'full', 'has_more_older' => false, 'min_id' => null, 'max_id' => null]);
        }

        $sinceId = (int)($_GET['since_id'] ?? 0);
        if ($sinceId > 0) {
            $stmt = $db->prepare('SELECT id, user_email, sender_email, message, media_path, media_type, is_deleted, is_edited, is_read, created_at, edited_at
                FROM support_chat WHERE user_email = :email AND id > :sid ORDER BY created_at ASC LIMIT 100');
            $stmt->bindValue(':email', $chatUserEmail, PDO::PARAM_STR);
            $stmt->bindValue(':sid', $sinceId, PDO::PARAM_INT);
            $result = $stmt->execute();
            $messages = [];
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $messages[] = $mapRow($row);
            }
            json_out(['ok' => true, 'messages' => $messages, 'mode' => 'delta']);
        }

        $chunk = min(100, max(10, (int)($_GET['limit'] ?? 30)));
        $beforeId = (int)($_GET['before_id'] ?? 0);

        if ($beforeId > 0) {
            $stmt = $db->prepare('SELECT id, user_email, sender_email, message, media_path, media_type, is_deleted, is_edited, is_read, created_at, edited_at
                FROM support_chat WHERE user_email = :email AND id < :bid ORDER BY created_at DESC LIMIT :lim');
            $stmt->bindValue(':email', $chatUserEmail, PDO::PARAM_STR);
            $stmt->bindValue(':bid', $beforeId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $chunk, PDO::PARAM_INT);
            $result = $stmt->execute();
            $rows = [];
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
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
                $cstmt->bindValue(':e', $chatUserEmail, PDO::PARAM_STR);
                $cstmt->bindValue(':mid', $minId, PDO::PARAM_INT);
                $crow = $cstmt->execute()->fetch(PDO::FETCH_ASSOC);
                $hasMore = $crow && (int)$crow['c'] > 0;
            }
            json_out(['ok' => true, 'messages' => $messages, 'mode' => 'older', 'has_more_older' => $hasMore, 'min_id' => $minId]);
        }

        $stmt = $db->prepare('SELECT id, user_email, sender_email, message, media_path, media_type, is_deleted, is_edited, is_read, created_at, edited_at
            FROM support_chat WHERE user_email = :email ORDER BY created_at DESC LIMIT :lim');
        $stmt->bindValue(':email', $chatUserEmail, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $chunk, PDO::PARAM_INT);
        $result = $stmt->execute();
        $rows = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
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
            $cstmt->bindValue(':e', $chatUserEmail, PDO::PARAM_STR);
            $cstmt->bindValue(':mid', $minId, PDO::PARAM_INT);
            $crow = $cstmt->execute()->fetch(PDO::FETCH_ASSOC);
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
        $stmt->bindValue(':user_email', $chatUserEmail, PDO::PARAM_STR);
        $stmt->bindValue(':sender_email', $userEmail, PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':media_path', $mediaPath, $mediaPath ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':media_type', $mediaType, $mediaType ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':created_at', time(), PDO::PARAM_INT);
        $stmt->execute();

        $messageId = $db->lastInsertRowID();

        if (!$isAdmin) {
            $checkStmt = $db->prepare('SELECT COUNT(*) AS cnt FROM support_chat WHERE user_email = :email AND sender_email = :email');
            $checkStmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
            $checkResult = $checkStmt->execute()->fetch(PDO::FETCH_ASSOC);

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
        $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);

        if (!$row) json_out(['ok' => false, 'error' => 'message_not_found'], 404);
        if (!$isAdmin && $row['sender_email'] !== $userEmail) {
            json_out(['ok' => false, 'error' => 'permission_denied'], 403);
        }

        // Mark as deleted
        $stmt = $db->prepare("UPDATE support_chat SET is_deleted = 1 WHERE id = :id");
        $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
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
        $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);

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
        $stmt->bindValue(':msg', $newMessage, PDO::PARAM_STR);
        $stmt->bindValue(':edited', time(), PDO::PARAM_INT);
        $stmt->bindValue(':mp', $mediaPath, $mediaPath ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':mt', $bindType, $bindType ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
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
        $stmt->bindValue(':user_email', $chatUserEmail, PDO::PARAM_STR);
        $stmt->bindValue(':current_user', $userEmail, PDO::PARAM_STR);
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
        $stmt->bindValue(':path', $filePath, PDO::PARAM_STR);
        $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
        $stmt->bindValue(':email2', $userEmail, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);

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
                            (SELECT COALESCE(name, '') FROM users WHERE email = sc.user_email LIMIT 1) as user_name,
                            (SELECT photo FROM users WHERE email = sc.user_email LIMIT 1) as user_photo
                            FROM support_chat sc
                            ) chat_rows ORDER BY CASE WHEN last_message_time IS NULL THEN 1 ELSE 0 END ASC, last_message_time DESC");
        if (!$stmt) json_out(['ok' => false, 'error' => 'db_query_failed'], 500);

        $chats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $chats[] = [
                'user_email' => $row['user_email'],
                'user_name' => (string)($row['user_name'] ?? ''),
                'last_message_time' => (int)$row['last_message_time'],
                'message_count' => (int)$row['message_count'],
                'user_role' => (int)($row['user_role'] ?? 0),
                'user_photo' => app_avatar_src($row['user_photo'] ?? null),
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
        $stmt->bindValue(':email', $chatUserEmail, PDO::PARAM_STR);
        $stmt->execute();

        json_out(['ok' => true]);
    }

    // Check if user exists (admin only)
    if ($action === 'check_user') {
        if (!$isAdmin) json_out(['ok' => false, 'error' => 'permission_denied'], 403);

        $email = $_GET['email'] ?? '';
        if ($email === '') json_out(['ok' => false, 'error' => 'missing_email'], 400);

        $stmt = $db->prepare("SELECT role FROM users WHERE email = :email LIMIT 1");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);

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
            $result = $db->query("SELECT email, name, role, photo FROM users WHERE role < 2 ORDER BY lower(email) LIMIT 40");
        } else {
            $esc = static function (string $s): string {
                return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
            };
            $term = '%' . $esc($q) . '%';
            $stmt = $db->prepare("SELECT email, name, role, photo FROM users WHERE role < 2 AND (email LIKE :t ESCAPE '\\' OR COALESCE(name, '') LIKE :t ESCAPE '\\') ORDER BY lower(email) LIMIT 40");
            $stmt->bindValue(':t', $term, PDO::PARAM_STR);
            $result = $stmt->execute();
        }
        $users = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $users[] = [
                'email' => $row['email'],
                'name' => $row['name'] ?? '',
                'role' => (int)($row['role'] ?? 0),
                'photo' => app_avatar_src($row['photo'] ?? null),
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
$isAuthConfigPage = in_array($requestPathForConfig, ['/login', '/register', '/one-time-pin'], true) || (isset($_GET['code']) && strpos($requestPathForConfig, '/login') !== false);

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
    if (!$themeFromUrl) $config['theme'] = $defaultConfig['theme'];
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
    'ai_models_label'=>'Models (one per line)',
    'ai_default_model_label'=>'Default model',
    'ap_settings_subtitle'=>'Configure email, Telegram bot, OnlyOffice, Docker, Backup, and AI Assistant integrations.',
    'docker_manager_subtitle'=>'Manage Docker containers, images, and services.'];
$defaultTranslations['keyboard_shortcuts'] = 'Keyboard Shortcuts';
$defaultTranslations['guide_reopen'] = 'Watch guide';
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
$defaultTranslations['reset_to_default'] = 'Reset to default';
$defaultTranslations['photo_reset_to_default'] = 'Photo reset to default successfully';
$defaultTranslations['photo_too_large_or_empty'] = 'Photo too large or empty';
$defaultTranslations['unsupported_image_type'] = 'Unsupported image type';
$defaultTranslations['photo_upload_failed'] = 'Failed to upload photo';
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
$defaultTranslations['news_writing_prompt'] = 'News writing prompt';
$defaultTranslations['refresh_token'] = 'Refresh token';
$defaultTranslations['copy'] = 'Copy';
$defaultTranslations['created'] = 'Created';
$defaultTranslations['expires'] = 'Expires';
$defaultTranslations['revoke'] = 'Revoke';
$defaultTranslations['no_tokens'] = 'No tokens';
$defaultTranslations['mcp_setup_title'] = 'MCP for AI agents';
$defaultTranslations['mcp_setup_subtitle'] = 'Connect Cursor, Claude Desktop, VS Code, OpenCode, or Agora to this AppDashboard.';
$defaultTranslations['mcp_setup_req'] = 'Requires Node.js 18+ on your PC.';
$defaultTranslations['mcp_setup_copy'] = 'Copy';
$defaultTranslations['mcp_setup_copied'] = 'Copied';
$defaultTranslations['mcp_setup_step1'] = '1. Install Node.js 18+ if needed';
$defaultTranslations['mcp_setup_step2'] = '2. Copy the command for your agent and run it in a terminal';
$defaultTranslations['mcp_setup_step3'] = '3. Restart the agent, then open the browser auth link when prompted';
$defaultTranslations['mcp_setup_not_ready'] = 'Package is not built on this server yet. Ask an admin to redeploy main.';
$defaultTranslations['mcp_setup_not_built'] = 'Not built';
$defaultTranslations['mcp_setup_download'] = 'Download package';
$defaultTranslations['mcp_setup_token_title'] = 'HTTP token for external apps';
$defaultTranslations['mcp_setup_token_hint'] = 'Use this Bearer token with the Streamable HTTP endpoint.';
$defaultTranslations['mcp_setup_token_name'] = 'Token name';
$defaultTranslations['mcp_setup_token_default_name'] = 'Agora';
$defaultTranslations['mcp_setup_token_generate'] = 'Generate Agora token';
$defaultTranslations['mcp_setup_token_failed'] = 'Token generation failed';
$defaultTranslations['mcp_setup_token_generated'] = 'MCP token generated';
$defaultTranslations['mcp_setup_token_revoked'] = 'MCP token revoked';
$defaultTranslations['mcp_setup_token_error'] = 'MCP token error';
$defaultTranslations['mcp_setup_endpoint'] = 'Streamable HTTP endpoint';
$defaultTranslations['mcp_api_tokens'] = 'API Tokens for external apps (MCP)';
$defaultTranslations['mcp_token_copy_now'] = 'Copy this token now. For security it will not be shown again.';
$defaultTranslations['mcp_token_device'] = 'Device / Service name';
$defaultTranslations['mcp_token_name'] = 'Token name';
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
$defaultTranslations['weather_wind_chart_title'] = 'Wind speed';
$defaultTranslations['weather_wind_chart_btn'] = 'Show wind chart';
$defaultTranslations['weather_wind_chart_back'] = 'Back to forecast';
$defaultTranslations['weather_wind_speed_unit'] = 'km/h';
$defaultTranslations['weather_settings'] = 'Settings';
$defaultTranslations['weather_use_current_location'] = 'Use current location';
$defaultTranslations['weather_use_selected_city'] = 'Use selected city';
$defaultTranslations['weather_select_country'] = 'Select country';
$defaultTranslations['weather_select_region'] = 'Select region';
$defaultTranslations['weather_select_city'] = 'Select city';
$defaultTranslations['weather_search'] = 'Search';
$defaultTranslations['weather_no_results'] = 'No results found';
$defaultTranslations['weather_location_settings'] = 'Location settings';
$defaultTranslations['weather_save'] = 'Save';
$defaultTranslations['weather_cancel'] = 'Cancel';
$defaultTranslations['weather_select_country_first'] = 'Select country first';
$defaultTranslations['weather_change_country'] = 'Change country';
$defaultTranslations['weather_change_city'] = 'Change city';
$language = ap_i18n_lang_exists($config['language']) ? $config['language'] : 'en';
$translations = ap_merge_i18n($defaultTranslations, $language);

require 'vendor/autoload.php';
require_once APP_ROOT . '/ssh_terminal_handler.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function apSmtpConfigured(?array $cfg = null): bool {
    $cfg = $cfg ?? loadAuthConfig();
    return trim((string)($cfg['smtp_host'] ?? '')) !== ''
        && trim((string)($cfg['smtp_username'] ?? '')) !== ''
        && trim((string)($cfg['smtp_password'] ?? '')) !== '';
}
function apEmailIconPath(): ?string {
    $root = defined('APP_ROOT') ? APP_ROOT : getcwd();
    foreach ([$root . '/home/icon.png', $root . '/icon.png', $root . '/project/views/icon.png'] as $path) {
        if (is_file($path)) return $path;
    }
    return null;
}
function configurePhpMailerSmtp(PHPMailer $mail, array $authConfig): void {
    $port = (int)($authConfig['smtp_port'] ?? 587);
    $mail->isSMTP();
    $mail->Host = trim((string)$authConfig['smtp_host']);
    $mail->SMTPAuth = true;
    $mail->Username = trim((string)$authConfig['smtp_username']);
    $mail->Password = (string)$authConfig['smtp_password'];
    $mail->Port = $port > 0 ? $port : 587;
    if ($mail->Port === 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 20;
}
function sendEmailWithLogo($to, $subject, $body, ?array $authConfigOverride = null) {
    $to = trim((string)$to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('[Mail] Invalid recipient: ' . $to);
        return false;
    }
    $authConfig = $authConfigOverride ?? loadAuthConfig();
    if (!apSmtpConfigured($authConfig)) {
        error_log('[Mail] SMTP is not configured');
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        configurePhpMailerSmtp($mail, $authConfig);
        $from = trim((string)$authConfig['smtp_username']);
        $mail->setFrom($from, 'Server Kherson');
        $mail->addReplyTo($from, 'Server Kherson');
        $mail->addAddress($to);
        $mail->XMailer = ' ';
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
        $iconPath = apEmailIconPath();
        if ($iconPath) $mail->addEmbeddedImage($iconPath, 'logo');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)), ENT_QUOTES, 'UTF-8'));
        $mail->send();
        error_log('[Mail] Sent "' . $subject . '" to ' . $to);
        return true;
    } catch (Exception $e) {
        error_log('[Mail] Failed to send "' . $subject . '" to ' . $to . ': ' . $mail->ErrorInfo);
        return false;
    }
}
function sendTestEmail(string $to, ?array $authConfigOverride = null): bool {
    $body = buildAuthEmailBody(
        'SMTP test',
        'SMTP test message',
        'This is a test email from Server Kherson. If you received it, outgoing mail is configured correctly.',
        "<div style='text-align:center;padding:18px;border-radius:16px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-size:15px;font-weight:700;'>SMTP test successful</div>",
        'Sent at ' . date('Y-m-d H:i:s T'),
        'You can ignore this message.'
    );
    return sendEmailWithLogo($to, 'Server Kherson SMTP test', $body, $authConfigOverride);
}
function apEmailPublicBaseUrl(): string {
    return 'https://serverkherson.xyz';
}
function apEmailLogoHtml(): string {
    if(apEmailIconPath())return "<img src='cid:logo' alt='Server Kherson' style='width:58px;height:58px;border-radius:18px;display:block;margin:0 auto 14px;box-shadow:0 12px 30px rgba(59,130,246,.25);'>";
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
// Function to determine the actual theme for display
function getActualDisplayTheme($configTheme) {
    if($configTheme === 'auto') {
                // Determine the browser theme via JavaScript (will be set in a cookie)
                // Use light by default until JS sets the cookie
        return isset($_COOKIE['browser_theme']) ? $_COOKIE['browser_theme'] : 'light';
    }
    return $configTheme;
}

// Function to get the theme for links (always a concrete theme, not auto)
function getThemeForLinks($configTheme) {
    if($configTheme === 'auto') {
                // Always pass the actual theme for links
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
    $mainBlock="<div style='text-align:center;margin:4px 0 22px;'><a href='{$safeVerificationLink}' style='display:inline-block;padding:15px 28px;border-radius:999px;background:linear-gradient(135deg,#2563eb,#38bdf8);color:#fff;text-decoration:none;font-size:16px;font-weight:800;box-shadow:0 14px 28px rgba(37,99,235,.24);'>Open verification page</a></div><div style='padding:15px 16px;border-radius:16px;background:#f8fbff;border:1px solid #dbeafe;color:#475569;font-size:13px;line-height:1.6;'><strong style='display:block;color:#334155;margin-bottom:6px;'>Important</strong>Open the link above, then click the Confirm button on the page to activate your account.</div><div style='padding:15px 16px;border-radius:16px;background:#f8fbff;border:1px solid #dbeafe;color:#475569;font-size:13px;line-height:1.6;word-break:break-all;margin-top:12px;'><strong style='display:block;color:#334155;margin-bottom:6px;'>Button not working?</strong><a href='{$safeVerificationLink}' style='color:#2563eb;text-decoration:none;'>{$safeVerificationLink}</a></div>";
    $body=buildAuthEmailBody('Account confirmation','Confirm your Server Kherson account','Thanks for joining Server Kherson. Open the link below and click Confirm on the page to activate your account.',$mainBlock,'This confirmation link is valid for 24 hours.','If you did not create this account, you can safely ignore this email.');
    return sendEmailWithLogo($to,'Server Kherson — confirm your email',$body);
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
function apNormalizeAuthEmail(string $email): string {
    return normalizeAuthEmail($email);
}
function apSanitizeVerificationToken(?string $token): string {
    return sanitizeVerificationToken($token);
}
function apRenderVerificationPage(string $title, string $message, ?string $email = null, bool $showResend = false, ?string $token = null, ?string $confirmLabel = null): void {
    global $language, $config, $translations;
    $primaryColor = my_htmlspecialchars((string)($config['color'] ?? '#2563eb'));
    $safeTitle = my_htmlspecialchars($title);
    $safeMessage = my_htmlspecialchars($message);
    $safeEmail = $email !== null ? my_htmlspecialchars($email) : '';
    $safeToken = $token !== null ? my_htmlspecialchars($token) : '';
    $safeConfirm = my_htmlspecialchars($confirmLabel ?? ($translations['confirm_label'] ?? 'Confirm'));
    $resendLabel = my_htmlspecialchars($translations['resend_verification_link'] ?? 'Send verification link');
    $loginLabel = my_htmlspecialchars($translations['login'] ?? 'Login');
    ?>
    <!doctype html>
    <html lang="<?php echo my_htmlspecialchars($language); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow,noarchive">
        <title><?php echo $safeTitle; ?></title>
        <link rel="icon" type="image/png" href="/icon.png">
        <style>
            :root{color-scheme:light dark;--accent:<?php echo $primaryColor; ?>;--bg:light-dark(#f3f5f7,#0f1115);--card:light-dark(rgba(255,255,255,.82),rgba(24,26,32,.82));--line:light-dark(rgba(20,24,31,.08),rgba(255,255,255,.08));--text:light-dark(#1f2329,#f3f5f7);--muted:light-dark(#5a6472,#a8b0bc);--shadow:0 24px 64px rgba(0,0,0,.18)}
            *{box-sizing:border-box}
            body{margin:0;min-height:100vh;font:15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at top,light-dark(rgba(255,255,255,.94),rgba(255,255,255,.05)),transparent 40%),linear-gradient(180deg,light-dark(#eef1f4,#0b0d10),var(--bg));color:var(--text);display:flex;align-items:center;justify-content:center;padding:24px}
            .card{width:min(620px,100%);background:var(--card);border:1px solid var(--line);border-radius:24px;padding:32px;box-shadow:var(--shadow);backdrop-filter:blur(18px);text-align:center}
            h1{font-size:28px;line-height:1.15;margin:0 0 10px}
            p{color:var(--muted);font-size:15px;line-height:1.6;margin:0 0 22px}
            .email,.field{margin:0 0 16px;padding:14px 16px;border-radius:16px;background:light-dark(rgba(255,255,255,.55),rgba(255,255,255,.04));border:1px solid var(--line);font-weight:700;word-break:break-all}
            .field{font-weight:400;text-align:left}
            .field label{display:block;font-size:13px;font-weight:600;margin-bottom:8px;color:var(--muted)}
            .field input{width:100%;padding:12px 14px;border-radius:12px;border:1px solid var(--line);background:transparent;color:var(--text);font:inherit}
            button,.link-btn{border:none;border-radius:14px;padding:14px 18px;font:inherit;font-weight:700;cursor:pointer;background:var(--accent);color:#fff;width:100%;text-decoration:none;display:inline-block}
            .link-btn.secondary{margin-top:12px;background:transparent;color:var(--text);border:1px solid var(--line)}
        </style>
    </head>
    <body>
        <div class="card">
            <h1><?php echo $safeTitle; ?></h1>
            <p><?php echo $safeMessage; ?></p>
            <?php if($email !== null && $email !== ''): ?><div class="email"><?php echo $safeEmail; ?></div><?php endif; ?>
            <?php if($token !== null && $token !== ''): ?>
            <form method="post" action="/verify">
                <input type="hidden" name="token" value="<?php echo $safeToken; ?>">
                <button type="submit" name="confirm_email_verification" value="1"><?php echo $safeConfirm; ?></button>
            </form>
            <?php elseif($showResend): ?>
            <form method="post" action="/register">
                <div class="field">
                    <label for="verify-resend-email">Email</label>
                    <input type="email" id="verify-resend-email" name="email" value="<?php echo $safeEmail; ?>" required>
                </div>
                <button type="submit" name="resend_verification" value="1"><?php echo $resendLabel; ?></button>
            </form>
            <?php endif; ?>
            <a class="link-btn secondary" href="/login"><?php echo $loginLabel; ?></a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
function generateVerificationToken(){return bin2hex(random_bytes(32));}
function findValidVerificationEmail(string $token): ?string {
    $row = findVerificationTokenRow($token);
    if (!$row || (int)$row['expires'] <= time()) return null;
    return (string)$row['email'];
}
function deleteVerificationToken(string $email): void {
    $db = getDB();
    $st = $db->prepare('DELETE FROM verification_tokens WHERE email=:e');
    $st->bindValue(':e', $email);
    $st->execute();
}
function completeEmailVerification(string $email): bool {
    $email = apNormalizeAuthEmail($email);
    if ($email === '' || $email === 'root@root') return false;
    $name = '';
    $pendingRow = getPendingUser($email);
    if ($pendingRow) {
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
        $name = $userData['name'] ?? '';
    } else {
        $user = getUserByEmail($email);
        if (!$user || !empty($user['verified'])) return false;
        $user['verified'] = true;
        upsertUser($email, $user);
        $name = $user['name'] ?? '';
    }
    deleteVerificationToken($email);
    $freshUser = getUserByEmail($email);
    if (!$freshUser || empty($freshUser['verified'])) return false;
    $_SESSION['user'] = $freshUser;
    $_SESSION['user']['email'] = $email;
    $_SESSION['auth_time'] = time();
    cleanupExpiredUserSessions($email);
    addUserToActiveSessions($email, session_id(), $_SESSION['user']);
    scheduleTrustedDevicePromptIfNeeded($email);
    notifyTelegramEmailVerified($email, $name);
    return true;
}

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
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
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
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
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
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
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
    $purge->bindValue(':n', time(), PDO::PARAM_INT);
    $purge->bindValue(':e', $email, PDO::PARAM_STR);
    $purge->execute();
    $token = strtoupper(bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)));
    $stmt = $db->prepare("INSERT INTO tg_bot_link_tokens(token_hash,email,created_by,created_at,expires_at) VALUES(:h,:e,:c,:n,:x)");
    $stmt->bindValue(':h', hash('sha256', $token), PDO::PARAM_STR);
    $stmt->bindValue(':e', $email, PDO::PARAM_STR);
    $stmt->bindValue(':c', $createdBy, PDO::PARAM_STR);
    $stmt->bindValue(':n', time(), PDO::PARAM_INT);
    $stmt->bindValue(':x', time() + 900, PDO::PARAM_INT);
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
    $newsDataDir = rtrim((string)(getenv('APP_NEWS_DATA_DIR') ?: appdata_data_dir() . '/news'), '/');
    $newsFile = $newsDataDir . '/news.json';
    $layoutFile = $newsDataDir . '/news_layout.json';
    $uploadsDir = $newsDataDir . '/uploads/';
    if (!is_dir($newsDataDir)) @mkdir($newsDataDir, 0775, true);
    if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);
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
    function newsPhotoUrl(string $photo): string {
        $photo = trim($photo);
        if (preg_match('#^uploads/news/([^/]+)$#', $photo, $m)) {
            return '/main?api=news&action=photo&file=' . rawurlencode($m[1]);
        }
        return $photo;
    }
    function newsTableColumns(AppDatabase $db, string $table): array {
        $res = $db->query("SELECT column_name AS name FROM information_schema.columns WHERE table_schema='public' AND table_name='" . str_replace("'", "''", $table) . "'");
        $cols = [];
        while ($res && ($row = $res->fetch(PDO::FETCH_ASSOC))) {
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
            $stmt->bindValue(':id', trim((string)($item['id'] ?? '')) ?: bin2hex(random_bytes(8)), PDO::PARAM_STR);
            $stmt->bindValue(':title', trim((string)$item['title']), PDO::PARAM_STR);
            $stmt->bindValue(':body', (string)($item['body'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':photo', trim((string)($item['photo'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':published', !empty($item['published']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':created_at', (int)($item['created_at'] ?? time()), PDO::PARAM_INT);
            $stmt->bindValue(':updated_at', isset($item['updated_at']) ? (int)$item['updated_at'] : null, isset($item['updated_at']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':author', (string)($item['author'] ?? ''), PDO::PARAM_STR);
            $stmt->execute();
        }
    }
    function newsLoadAllDb(AppDatabase $db, bool $canEdit, int $limit = 0, int $offset = 0): array {
        $sql = "SELECT id, title, body, photo, published, created_at, updated_at, author FROM app_news";
        if (!$canEdit) $sql .= " WHERE published = 1";
        $sql .= " ORDER BY created_at DESC";
        if ($limit > 0) $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $res = $db->query($sql);
        $items = [];
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $row['published'] = newsBool($row['published'] ?? 0);
            $row['created_at'] = (int)($row['created_at'] ?? 0);
            $row['updated_at'] = isset($row['updated_at']) ? (int)$row['updated_at'] : null;
            $row['photo'] = newsPhotoUrl((string)($row['photo'] ?? ''));
            $items[] = $row;
        }
        return $items;
    }
    function newsValidateWritingToken(string $token): bool {
        $token = trim($token);
        if ($token === '') return false;
        $tokenFile = appdata_data_dir() . '/news_writing_token.json';
        if (!is_file($tokenFile)) return false;
        $tokenData = json_decode((string)file_get_contents($tokenFile), true) ?: [];
        $storedToken = (string)($tokenData['token'] ?? '');
        if ($storedToken === '' || !hash_equals($storedToken, $token)) return false;
        $now = time();
        $createdAt = (int)($tokenData['created_at'] ?? 0);
        $lastUsedAt = (int)($tokenData['last_used_at'] ?? 0);
        if (($now - $createdAt) >= 3600 && ($now - $lastUsedAt) >= 300) return false;
        $tokenData['last_used_at'] = $now;
        file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT), LOCK_EX);
        return true;
    }
    newsEnsureDb($db, $newsFile);

    // Support action from query string, POST, or JSON body
$rawInput = (string)file_get_contents('php://input');
$jsonInput = $rawInput !== '' ? json_decode($rawInput, true) : null;
$action = (string)($_GET['action'] ?? $_POST['action'] ?? (is_array($jsonInput) ? ($jsonInput['action'] ?? '') : ''));
    $authHeader = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $bearerToken = preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m) ? trim($m[1]) : '';
    $apiToken = $bearerToken ?: trim((string)($_SERVER['HTTP_X_API_TOKEN'] ?? (is_array($jsonInput) ? ($jsonInput['api_token'] ?? $jsonInput['token'] ?? '') : '')));
    $newsTokenAuth = newsValidateWritingToken($apiToken);
    header('Content-Type: application/json; charset=utf-8');

    if ($action === 'list') {
        $listLimit = max(1, min(200, (int)($_GET['limit'] ?? 20)));
        $listOffset = max(0, (int)($_GET['offset'] ?? 0));
        $items = newsLoadAllDb($db, $canEdit, $listLimit, $listOffset);
        $total = (int)$db->querySingle("SELECT COUNT(*) FROM app_news" . ($canEdit ? '' : " WHERE published = 1"));
        $layout = is_file($layoutFile) ? (json_decode(file_get_contents($layoutFile), true) ?: []) : [];
        echo json_encode(['ok' => true, 'items' => $items, 'can_edit' => $canEdit, 'layout' => $layout, 'total' => $total, 'has_more' => ($listOffset + count($items)) < $total]);
        exit;
    }

    if ($action === 'photo') {
        $filename = basename((string)($_GET['file'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/i', $filename)) {
            http_response_code(404);
            exit;
        }
        $path = $uploadsDir . $filename;
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        $mime = apSecDetectMimeFromFile($path);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) $mime = 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($path);
        exit;
    }

    if ($action === 'ai_generate') {
        $input = is_array($jsonInput) ? $jsonInput : json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) $input = [];
        if (!$canEdit && !$newsTokenAuth) { echo json_encode(['ok' => false, 'error' => 'forbidden']); exit; }
        if (!$newsTokenAuth) apSecRequireSameOrigin();
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
        $systemMsg .= 'Given a topic or prompt, improve or rewrite the article. Respond with valid JSON only: {"title":"...","body":"...","created_at":<optional publication date as a unix timestamp in seconds>}. The body should be written in Markdown format. Use headings, lists, bold, italic, code blocks, and other Markdown syntax as appropriate for the content.';
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
                echo json_encode(['ok' => true, 'title' => (string)$parsed['title'], 'body' => (string)$parsed['body'], 'created_at' => isset($parsed['created_at']) ? (int)$parsed['created_at'] : 0]);
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
        file_put_contents($layoutFile, json_encode($clean, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'save') {
        if (!$canEdit && !$newsTokenAuth) { echo json_encode(['ok' => false, 'error' => 'forbidden']); exit; }
        if (!$newsTokenAuth) apSecRequireSameOrigin();
        $input = is_array($jsonInput) ? $jsonInput : json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;
        $id = trim((string)($input['id'] ?? ''));
        $title = trim((string)($input['title'] ?? ''));
        $body = trim((string)($input['body'] ?? ''));
        $published = !empty($input['published']);
        $photo = trim((string)($input['photo'] ?? ''));
        $hasCreatedAt = array_key_exists('created_at', $input);
        $createdAt = (int)($input['created_at'] ?? 0);
        if ($title === '') { echo json_encode(['ok' => false, 'error' => 'title_required']); exit; }
        $now = time();
        if ($id === '') {
            $id = bin2hex(random_bytes(8));
            $stmt = $db->prepare("INSERT INTO app_news (id, title, body, photo, published, created_at, author) VALUES (:id, :title, :body, :photo, :published, :created_at, :author)");
            $stmt->bindValue(':id', $id, PDO::PARAM_STR);
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':body', $body, PDO::PARAM_STR);
            $stmt->bindValue(':photo', $photo, PDO::PARAM_STR);
            $stmt->bindValue(':published', $published ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':created_at', $createdAt > 0 ? $createdAt : $now, PDO::PARAM_INT);
            $stmt->bindValue(':author', $userEmail !== '' ? $userEmail : 'external-ai', PDO::PARAM_STR);
            $stmt->execute();
        } else {
            $createdAtSql = $hasCreatedAt && $createdAt > 0 ? ", created_at = :created_at" : "";
            $stmt = $db->prepare("UPDATE app_news SET title = :title, body = :body, photo = :photo, published = :published, updated_at = :updated_at{$createdAtSql} WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_STR);
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':body', $body, PDO::PARAM_STR);
            $stmt->bindValue(':photo', $photo, PDO::PARAM_STR);
            $stmt->bindValue(':published', $published ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':updated_at', $now, PDO::PARAM_INT);
            if ($hasCreatedAt && $createdAt > 0) $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_INT);
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
        $stmt->bindValue(':id', $id, PDO::PARAM_STR);
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
        $file = $_FILES['photo'];
        $mime = apSecDetectMimeFromFile($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
            echo json_encode(['ok' => false, 'error' => 'invalid_type']); exit;
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
        $filename = apSecRandomFilename($ext);
        move_uploaded_file($file['tmp_name'], $uploadsDir . $filename);
        echo json_encode(['ok' => true, 'url' => '/main?api=news&action=photo&file=' . rawurlencode($filename)]);
        exit;
    }

    if ($action === 'get_writing_prompt') {
        if (!$canEdit) { echo json_encode(['ok' => false, 'error' => 'forbidden']); exit; }

        $tokenDir = appdata_data_dir();
        if (!is_dir($tokenDir)) {
            @mkdir($tokenDir, 0775, true);
        }
        $tokenFile = $tokenDir . '/news_writing_token.json';

        $now = time();
        $tokenData = [];
        if (is_file($tokenFile)) {
            $tokenData = json_decode((string)file_get_contents($tokenFile), true) ?: [];
        }

        $token = (string)($tokenData['token'] ?? '');
        $createdAt = (int)($tokenData['created_at'] ?? 0);
        $lastUsedAt = (int)($tokenData['last_used_at'] ?? 0);

        // Token rotation: valid for 1 hour from creation, extended 5 min after last use
        $tokenExpired = ($now - $createdAt) >= 3600;
        $graceExpired = ($now - $lastUsedAt) >= 300;
        $needsNewToken = $token === '' || ($tokenExpired && $graceExpired);

        if ($needsNewToken) {
            $token = bin2hex(random_bytes(32));
            $createdAt = $now;
            $lastUsedAt = $now;
            file_put_contents($tokenFile, json_encode([
                'token' => $token,
                'created_at' => $createdAt,
                'last_used_at' => $lastUsedAt,
            ], JSON_PRETTY_PRINT), LOCK_EX);
        } else {
            // Update last used time
            $lastUsedAt = $now;
            file_put_contents($tokenFile, json_encode([
                'token' => $token,
                'created_at' => $createdAt,
                'last_used_at' => $lastUsedAt,
            ], JSON_PRETTY_PRINT), LOCK_EX);
        }

        // Build API URL from current request
        $scheme = 'http';
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $cfVisitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $forwardedProto === 'https'
            || str_contains($cfVisitor, '"scheme":"https"')) {
            $scheme = 'https';
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $apiUrl = $scheme . '://' . $host . '/main?api=news&action=save';

        $tokenExpiresIn = max(0, 3600 - ($now - $createdAt));
        $graceExpiresIn = max(0, 300 - ($now - $lastUsedAt));
        $effectiveExpires = max($tokenExpiresIn, $graceExpiresIn);

        // ── Load recent published articles for context ──
        $previousArticles = '';
        $newsItems = [];
        try {
            $newsItems = newsLoadAllDb($db, $canEdit, 5);
            if (!empty($newsItems)) {
                // Take the 5 most recent published articles
                $recentNews = $newsItems;
                $lines = [];
                foreach ($recentNews as $ni) {
                    $pub = !empty($ni['published']) ? 'Published' : 'Draft';
                    $date = !empty($ni['created_at']) ? date('Y-m-d', (int)$ni['created_at']) : 'unknown date';
                    $title = (string)($ni['title'] ?? 'Untitled');
                    $bodyPreview = (string)($ni['body'] ?? '');
                    $bodyPreview = mb_substr(str_replace(["\r", "\n"], ' ', $bodyPreview), 0, 150);
                    $lines[] = "- [$pub] \"{$title}\" ({$date}) - {$bodyPreview}...";
                }
                $previousArticles = "\n## Recently Published Articles\nReview these previous articles to avoid repeating the same topics:\n" . implode("\n", $lines) . "\n";
            }
        } catch (Throwable $e) {
            // Ignore errors when loading previous articles
            $previousArticles = '';
        }

        $prompt = <<<PROMPT
You are a professional news writer for an app dashboard project. Your task is to create a news article based on recent git changes in the repository.

## News Writing Rules
- Write ONLY in English
- Do NOT use emojis in the title
- You MAY use emojis in the article body text (use them sparingly and appropriately)
- Article length: 100-600 words
- Use content in moderation — don't exaggerate or oversell features
- The news is for regular end-users — do NOT include any code details, technical implementation specifics, or internal architecture references
- Only describe visible changes and user-facing features that regular users would see or benefit from
- Use Markdown formatting for the body (headings, lists, bold, italic as appropriate)
{$previousArticles}
## How to Get the Changes
Run the following command in the server project directory to get recent git changes:
```bash
git log --oneline -20 --no-merges
```
Then for each relevant commit, get the full diff with:
```bash
git show <commit-hash> --stat
```
Focus on user-facing changes only. Ignore internal refactoring, dependency updates, or configuration changes that don't affect the user experience.

## Publish the Article
After you write the article, publish it by sending the finished title and Markdown body to the API.

Use this curl request shape:
```bash
curl -sS -X POST '{$apiUrl}' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer {$token}' \
  --data-binary @- <<'JSON'
{
  "title": "Your News Title Here",
  "body": "Your markdown article body here...",
  "published": true,
  "created_at": 1710000000
}
JSON
```

The request body must be valid JSON:
```json
{"title": "Your News Title Here", "body": "Your markdown article body here...", "published": true, "created_at": 1710000000}
```

## Publication Date
Set the publication date by adding "created_at" to the request body as a Unix timestamp in seconds (e.g. "created_at": 1710000000). Omit it to use the current date/time. You can backdate an article with a past timestamp or schedule one for a future date. To change the date of an existing article, include its "id" in the request together with the new "created_at".

The API response is JSON. A successful response looks like:
```json
{"ok": true, "id": "article_id"}
```

## API Access
- API URL: {$apiUrl}
- API Token: {$token}
- Token expires in: {$effectiveExpires} seconds
PROMPT;

        echo json_encode([
            'ok' => true,
            'prompt' => $prompt,
            'token' => $token,
            'token_expires_in' => $effectiveExpires,
            'api_url' => $apiUrl,
            'previous_articles' => !empty($newsItems) ? array_map(function($ni) {
                return [
                    'id' => $ni['id'] ?? '',
                    'title' => $ni['title'] ?? '',
                    'published' => !empty($ni['published']),
                    'created_at' => $ni['created_at'] ?? 0,
                ];
            }, array_slice($newsItems ?? [], 0, 5)) : [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    json_out(['ok' => false, 'error' => 'unknown_action'], 400);
}
