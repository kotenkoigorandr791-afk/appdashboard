<?php
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/includes/cloud_sync_protocol.php';
require_once __DIR__ . '/includes/cloud_sync_engine.php';
header('Content-Type: application/json; charset=utf-8');
$email = get_email_from_client_auth_header();
if ($email === null) {
    $email = get_session_email();
}
if (!$email) {
    http_response_code(401);
    echo cloud_sync_encode(['ok' => false, 'error' => 'not authenticated']);
    exit;
}
$scheme = 'http';
if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
) {
    $scheme = 'https';
}
if (isset($_SERVER['HTTP_CF_VISITOR'])) {
    $cf = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
    if (is_array($cf) && (($cf['scheme'] ?? '') === 'https')) {
        $scheme = 'https';
    }
}
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
echo cloud_sync_encode([
    'ok' => true,
    'protocol' => 'webdav',
    'transport' => 'webdav',
    'webdav_url' => ($scheme === 'https' ? 'webdavs://' : 'webdav://') . $host . '/webdav/',
    'username' => $email,
    'default_root' => 'main',
    'message' => 'Use WebDAV with your account email as username and a generated DAV token as password.',
]);
