<?php

function oauthRequestPath(?string $requestUri = null): string {
    $path = parse_url($requestUri ?? ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    if (str_starts_with($path, '/project/login/oauth/')) {
        $path = '/login/oauth/' . substr($path, strlen('/project/login/oauth/'));
    }
    return rtrim($path, '/') ?: '/';
}

function oauthIsOAuthPath(string $path): bool {
    return $path === '/login/oauth/start' || $path === '/login/oauth/callback';
}

function oauthSupportedProviders(): array {
    return ['google', 'github'];
}

function oauthGitHubClientIdValid(string $clientId): bool {
    $clientId = trim($clientId);
    if ($clientId === '') {
        return false;
    }
    return (bool)preg_match('/^(Iv1\\.|Iv23[a-zA-Z0-9_]+|Ov23[a-zA-Z0-9_]+)/', $clientId);
}

function oauthNormalizeProvider(string $provider): string {
    $provider = strtolower(trim($provider));
    if (!in_array($provider, oauthSupportedProviders(), true)) {
        throw new InvalidArgumentException('Unsupported OAuth provider');
    }
    return $provider;
}

function oauthStateTtl(): int {
    return 3600;
}

function oauthSetCsrfCookie(string $csrf): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    setcookie('oauth_csrf', $csrf, [
        'expires' => time() + oauthStateTtl(),
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function oauthClearCsrfCookie(): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    setcookie('oauth_csrf', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function oauthCreateState(string $provider, string $mode = 'login'): string {
    oauthNormalizeProvider($provider);
    if (!in_array($mode, ['login', 'link'], true)) {
        throw new InvalidArgumentException('Invalid OAuth mode');
    }
    $csrf = bin2hex(random_bytes(16));
    $createdAt = time();
    $_SESSION['oauth_state_' . $csrf] = [
        'provider' => $provider,
        'mode' => $mode,
        'created_at' => $createdAt,
    ];
    oauthSetCsrfCookie($csrf);
    return base64_encode(json_encode([
        'csrf' => $csrf,
        'provider' => $provider,
        'mode' => $mode,
        'ts' => $createdAt,
    ], JSON_UNESCAPED_SLASHES));
}

function oauthConsumeState(string $stateRaw): ?array {
    $payload = json_decode(base64_decode($stateRaw, true) ?: '', true);
    if (!is_array($payload) || empty($payload['csrf'])) {
        return null;
    }
    $ttl = oauthStateTtl();
    $key = 'oauth_state_' . (string)$payload['csrf'];
    $stored = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);
    if (is_array($stored) && (time() - (int)($stored['created_at'] ?? 0)) <= $ttl) {
        oauthClearCsrfCookie();
        return $stored;
    }
    $cookieCsrf = (string)($_COOKIE['oauth_csrf'] ?? '');
    $createdAt = (int)($payload['ts'] ?? 0);
    if ($cookieCsrf !== ''
        && hash_equals($cookieCsrf, (string)$payload['csrf'])
        && !empty($payload['provider'])
        && $createdAt > 0
        && (time() - $createdAt) <= $ttl) {
        oauthClearCsrfCookie();
        return [
            'provider' => (string)$payload['provider'],
            'mode' => (string)($payload['mode'] ?? 'login'),
            'created_at' => $createdAt,
        ];
    }
    return null;
}

function oauthStartUrl(string $provider, string $mode = 'login'): string {
    global $authConfig;
    $provider = oauthNormalizeProvider($provider);
    $redirectUri = oauthLoginRedirectUri();
    $state = oauthCreateState($provider, $mode);
    if ($provider === 'google') {
        $clientId = trim((string)($authConfig['google_client_id'] ?? ''));
        if ($clientId === '') {
            throw new RuntimeException('Google OAuth is not configured');
        }
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];
        return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    }
    $clientId = trim((string)($authConfig['github_client_id'] ?? ''));
    if ($clientId === '') {
        throw new RuntimeException('GitHub OAuth is not configured');
    }
    if (!oauthGitHubClientIdValid($clientId)) {
        global $translations;
        throw new RuntimeException($translations['github_client_id_invalid'] ?? 'Invalid GitHub Client ID. Use Client ID from GitHub App (Iv23...) or OAuth App (Iv1.xxxxx), not the numeric App ID.');
    }
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'state' => $state,
        'allow_signup' => 'true',
    ];
    if (str_starts_with($clientId, 'Iv1.')) {
        $params['scope'] = 'read:user user:email';
    }
    return 'https://github.com/login/oauth/authorize?' . http_build_query($params);
}

function oauthHttpRequest(string $method, string $url, array $headers = [], ?string $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    if ($response === false) {
        throw new RuntimeException('OAuth request failed: ' . $error);
    }
    return ['status' => $status, 'body' => (string)$response];
}

function oauthGitHubAuthHeaders(string $accessToken): array {
    return [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $accessToken,
        'User-Agent: AppDashboard-OAuth',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
}

function oauthPickGitHubEmail(array $emails): string {
    if ($emails === [] || isset($emails['message'])) {
        return '';
    }
    $primaryVerified = '';
    $verified = '';
    $primary = '';
    $any = '';
    foreach ($emails as $entry) {
        if (!is_array($entry) || empty($entry['email'])) {
            continue;
        }
        $addr = trim((string)$entry['email']);
        if ($addr === '') {
            continue;
        }
        $any = $any ?: $addr;
        if (!empty($entry['primary'])) {
            $primary = $addr;
            if (!empty($entry['verified'])) {
                $primaryVerified = $addr;
            }
        }
        if (!empty($entry['verified'])) {
            $verified = $verified ?: $addr;
        }
    }
    return $primaryVerified ?: $verified ?: $primary ?: $any;
}

function oauthGitHubNoreplyCandidates(array $userData): array {
    $id = trim((string)($userData['id'] ?? ''));
    $login = trim((string)($userData['login'] ?? ''));
    $candidates = [];
    if ($id !== '' && $login !== '') {
        $candidates[] = $id . '+' . $login . '@users.noreply.github.com';
    }
    if ($login !== '') {
        $candidates[] = $login . '@users.noreply.github.com';
    }
    return $candidates;
}

function oauthResolveGitHubEmail(array $userData, array $authHeaders): string {
    global $translations;
    $email = trim((string)($userData['email'] ?? ''));
    if ($email !== '') {
        return $email;
    }
    $emailsResponse = oauthHttpRequest('GET', 'https://api.github.com/user/emails', $authHeaders);
    $emails = json_decode($emailsResponse['body'], true);
    $email = is_array($emails) ? oauthPickGitHubEmail($emails) : '';
    if ($email !== '') {
        return $email;
    }
    if ($emailsResponse['status'] === 403 || $emailsResponse['status'] === 401) {
        error_log('GitHub /user/emails HTTP ' . $emailsResponse['status'] . ' — enable Email read permission on the GitHub App');
    } elseif ($email === '' && $emailsResponse['status'] !== 200) {
        error_log('GitHub /user/emails HTTP ' . $emailsResponse['status'] . ': ' . substr((string)$emailsResponse['body'], 0, 200));
    }
    foreach (oauthGitHubNoreplyCandidates($userData) as $candidate) {
        if ($candidate !== '') {
            return $candidate;
        }
    }
    throw new RuntimeException($translations['github_email_unavailable'] ?? 'GitHub did not provide an email address. Enable Email read permission on the GitHub App or make a verified email public in GitHub profile settings.');
}

function oauthFetchGoogleProfile(string $code): array {
    global $authConfig;
    require_once APP_ROOT . '/vendor/autoload.php';
    $client = new Google_Client();
    $client->setClientId($authConfig['google_client_id']);
    $client->setClientSecret($authConfig['google_client_secret']);
    $client->setRedirectUri(oauthLoginRedirectUri());
    $token = $client->fetchAccessTokenWithAuthCode($code);
    if (isset($token['error'])) {
        throw new RuntimeException($token['error_description'] ?? $token['error']);
    }
    $client->setAccessToken($token);
    $oauth2 = new Google_Service_Oauth2($client);
    $userInfo = $oauth2->userinfo->get();
    $email = trim((string)$userInfo->getEmail());
    if ($email === '') {
        throw new RuntimeException('Email not provided by Google');
    }
    return [
        'provider' => 'google',
        'provider_user_id' => (string)$userInfo->getId(),
        'email' => $email,
        'name' => trim((string)$userInfo->getName()) ?: explode('@', $email)[0],
        'photo' => trim((string)$userInfo->getPicture()),
    ];
}

function oauthFetchGitHubProfile(string $code): array {
    global $authConfig;
    $clientId = trim((string)($authConfig['github_client_id'] ?? ''));
    $clientSecret = trim((string)($authConfig['github_client_secret'] ?? ''));
    if ($clientId === '' || $clientSecret === '') {
        throw new RuntimeException('GitHub OAuth is not configured');
    }
    $tokenResponse = oauthHttpRequest(
        'POST',
        'https://github.com/login/oauth/access_token',
        [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: AppDashboard-OAuth',
        ],
        http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => oauthLoginRedirectUri(),
        ])
    );
    $tokenData = json_decode($tokenResponse['body'], true);
    if (!is_array($tokenData) || empty($tokenData['access_token'])) {
        throw new RuntimeException($tokenData['error_description'] ?? $tokenData['error'] ?? 'GitHub token exchange failed');
    }
    $accessToken = (string)$tokenData['access_token'];
    $authHeaders = oauthGitHubAuthHeaders($accessToken);
    $userResponse = oauthHttpRequest('GET', 'https://api.github.com/user', $authHeaders);
    $userData = json_decode($userResponse['body'], true);
    if (!is_array($userData) || empty($userData['id'])) {
        throw new RuntimeException('GitHub user profile unavailable');
    }
    $email = oauthResolveGitHubEmail($userData, $authHeaders);
    return [
        'provider' => 'github',
        'provider_user_id' => (string)$userData['id'],
        'email' => $email,
        'name' => trim((string)($userData['name'] ?? $userData['login'] ?? '')) ?: explode('@', $email)[0],
        'photo' => trim((string)($userData['avatar_url'] ?? '')),
    ];
}

function oauthFetchProfile(string $provider, string $code): array {
    $provider = oauthNormalizeProvider($provider);
    if ($provider === 'google') {
        return oauthFetchGoogleProfile($code);
    }
    return oauthFetchGitHubProfile($code);
}

function oauthCompleteLogin(string $email, array $users): bool {
    if (adminIsAdminUser($users[$email] ?? null) && adminIpAllowlist() !== [] && !adminIpAllowed()) {
        return false;
    }
    resetFailedAttempts(getClientIp(), 'login');
    clearTrustedDeviceLogoutSuppression();
    adminEstablishSession($email, $users[$email]);
    scheduleTrustedDevicePromptIfNeeded($email);
    if (adminIsAdminUser($users[$email] ?? null)) {
        adminAuditLog('admin_oauth_login', $email, 'ok');
    }
    return true;
}

function oauthHandleLoginFlow(array $profile): void {
    global $translations;
    $users = loadUsers();
    $email = $profile['email'];
    $linkedEmail = findUserEmailByOAuthProvider($profile['provider'], $profile['provider_user_id']);
    if ($linkedEmail !== null) {
        $email = $linkedEmail;
    }
    linkOAuthConnection($email, $profile);
    if (isset($users[$email]) && !empty($users[$email]['verified']) && !userNeedsPasswordSet($users[$email]) && empty($users[$email]['must_change_password'])) {
        if (!oauthCompleteLogin($email, $users)) {
            header('Location: /login?error=admin_ip_forbidden');
            exit;
        }
        header('Location: ' . takePostLoginRedirect('/main'));
        exit;
    }
    $isNewUser = !isset($users[$email]);
    $needsPasswordOnly = isset($users[$email]) && !empty($users[$email]['verified']) && (userNeedsPasswordSet($users[$email]) || !empty($users[$email]['must_change_password']));
    $_SESSION['pending_oauth_data'] = [
        'email' => $email,
        'name' => $profile['name'] ?: ($users[$email]['name'] ?? explode('@', $email)[0]),
        'photo' => $profile['photo'] ?: ($users[$email]['photo'] ?? 'default_avatar.png'),
        'provider' => $profile['provider'],
        'provider_user_id' => $profile['provider_user_id'],
        'reg_method' => $profile['provider'] === 'github' ? 'GitHub' : 'Google',
    ];
    unset($_SESSION['pending_google_data']);
    if ($needsPasswordOnly) {
        $_SESSION['pending_set_password_only'] = true;
    } else {
        unset($_SESSION['pending_set_password_only']);
    }
    $redirectTarget = '/register?step=set_password';
    if ($isNewUser) {
        $redirectTarget .= '&new_oauth_user=1&provider=' . urlencode($profile['provider']);
    } elseif ($needsPasswordOnly) {
        $redirectTarget .= '&password_required=1';
    }
    header('Location: ' . $redirectTarget);
    exit;
}

function oauthHandleLinkFlow(array $profile): void {
    global $profileSuccess, $profileError, $translations;
    if (!isset($_SESSION['user']['email'])) {
        throw new RuntimeException($translations['login'] ?? 'Not authenticated');
    }
    $currentEmail = (string)$_SESSION['user']['email'];
    $existingEmail = findUserEmailByOAuthProvider($profile['provider'], $profile['provider_user_id']);
    if ($existingEmail !== null && $existingEmail !== $currentEmail) {
        throw new RuntimeException($translations['oauth_already_linked_other'] ?? 'This account is already linked to another user');
    }
    if (hasOAuthConnection($currentEmail, $profile['provider'])) {
        throw new RuntimeException($translations['oauth_already_linked'] ?? 'This provider is already linked');
    }
    linkOAuthConnection($currentEmail, $profile);
    $profileSuccess = $translations['oauth_linked_success'] ?? 'Account linked successfully';
    header('Location: /main?profile=1&oauth_linked=' . urlencode($profile['provider']));
    exit;
}

function oauthHandleAdminGateFlow(array $profile): void {
    global $translations;
    if (!isset($_SESSION['user']['email']) || !isset($_SESSION['user']['role']) || (int)$_SESSION['user']['role'] !== 2) {
        throw new RuntimeException($translations['login'] ?? 'Not authenticated');
    }
    if (!adminIpAllowed()) {
        throw new RuntimeException($translations['admin_ip_forbidden'] ?? 'Administrator access is not allowed from this IP.');
    }
    $currentEmail = (string)$_SESSION['user']['email'];
    $existingEmail = findUserEmailByOAuthProvider($profile['provider'], $profile['provider_user_id']);
    if ($existingEmail !== null && $existingEmail !== $currentEmail) {
        throw new RuntimeException($translations['oauth_already_linked_other'] ?? 'This account is already linked to another user');
    }
    if ($existingEmail === null && strcasecmp((string)$profile['email'], $currentEmail) !== 0) {
        throw new RuntimeException($translations['invalid_credentials'] ?? 'Invalid credentials');
    }
    linkOAuthConnection($currentEmail, $profile);
    adminPanelIssueGate($currentEmail);
    adminAuditLog('admin_gate_oauth_login', $currentEmail, 'ok');
    header('Location: /main#adminpanel/users');
    exit;
}

function oauthHandleCallback(string $provider, string $code, ?string $stateRaw, string $modeFallback = 'login'): void {
    global $authError, $translations;
    $mode = $modeFallback;
    if ($stateRaw !== null && $stateRaw !== '') {
        $state = oauthConsumeState($stateRaw);
        if ($state === null) {
            throw new RuntimeException($translations['oauth_state_invalid'] ?? 'OAuth session expired. Please try again.');
        }
        $stateProvider = (string)($state['provider'] ?? '');
        if ($provider !== '' && $stateProvider !== '' && $provider !== $stateProvider) {
            throw new RuntimeException($translations['oauth_state_invalid'] ?? 'OAuth session expired. Please try again.');
        }
        if ($stateProvider !== '') {
            $provider = $stateProvider;
        }
        $mode = (string)($state['mode'] ?? 'login');
    }
    if ($provider === '') {
        throw new RuntimeException($translations['oauth_state_invalid'] ?? 'OAuth session expired. Please try again.');
    }
    $provider = oauthNormalizeProvider($provider);
    $profile = oauthFetchProfile($provider, $code);
    if ($mode === 'link') {
        oauthHandleLinkFlow($profile);
        return;
    }
    if ($mode === 'admin_gate') {
        oauthHandleAdminGateFlow($profile);
        return;
    }
    oauthHandleLoginFlow($profile);
}

function oauthHandleLegacyGoogleCallback(string $code): void {
    oauthHandleCallback('google', $code, null, 'login');
}

function oauthProviderConfigured(string $provider): bool {
    global $authConfig;
    $provider = oauthNormalizeProvider($provider);
    if ($provider === 'google') {
        return trim((string)($authConfig['google_client_id'] ?? '')) !== ''
            && trim((string)($authConfig['google_client_secret'] ?? '')) !== '';
    }
    $clientId = trim((string)($authConfig['github_client_id'] ?? ''));
    $clientSecret = trim((string)($authConfig['github_client_secret'] ?? ''));
    return $clientId !== '' && $clientSecret !== '' && oauthGitHubClientIdValid($clientId);
}

function oauthProviderLabel(string $provider): string {
    return $provider === 'github' ? 'GitHub' : 'Google';
}
