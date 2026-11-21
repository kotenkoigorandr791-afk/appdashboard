<?php
require_once SHAREPOINT_ROOT . '/views/password.php';
require_once SHAREPOINT_ROOT . '/views/error.php';

$token = (string)($_GET['share_token'] ?? '');
$repo = new ShareRepository();
$share = $repo->findByToken($token);

if (!$share) {
    echo sp_render_status_page('not_found', null, 404);
    exit;
}
if (!$share->isPasswordProtected()) {
    header('Location: ' . SHAREPOINT_PUBLIC_PREFIX . '/' . rawurlencode($share->token));
    exit;
}
if ($share->isRevoked() || $share->isExpired()) {
    echo sp_render_status_page($share->isRevoked() ? 'revoked' : 'expired', $share, $share->isRevoked() ? 403 : 410);
    exit;
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    echo sp_render_password_page($share);
    exit;
}

if (!sp_verify_csrf((string)($_POST['_csrf'] ?? ''))) {
    echo sp_render_password_page($share, sp_t('invalid_request'));
    exit;
}

$ip = ShareAccessPolicy::clientIp();
if (ShareTokenService::isRateLimited($share->token_hash, $ip)) {
    echo sp_render_status_page('too_many_attempts', $share, 429);
    exit;
}

$password = (string)($_POST['password'] ?? '');
if ($password !== '' && password_verify($password, (string)$share->password_hash)) {
    ShareTokenService::clearAttempts($share->token_hash, $ip);
    ShareAccessPolicy::markUnlocked($share);
    header('Location: ' . SHAREPOINT_PUBLIC_PREFIX . '/' . rawurlencode($share->token));
    exit;
}

ShareTokenService::recordFailedAttempt($share->token_hash, $ip);
echo sp_render_password_page($share, sp_t('wrong_password'));
