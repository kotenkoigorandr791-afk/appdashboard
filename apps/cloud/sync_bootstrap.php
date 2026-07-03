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
$scheme = 'ws';
if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
) {
    $scheme = 'wss';
}
if (isset($_SERVER['HTTP_CF_VISITOR'])) {
    $cf = json_decode((string)$_SERVER['HTTP_CF_VISITOR'], true);
    if (is_array($cf) && (($cf['scheme'] ?? '') === 'https')) {
        $scheme = 'wss';
    }
}
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$wsPublic = trim((string)(getenv('CLOUD_SYNC_WS_PUBLIC_PATH') ?: CLOUD_SYNC_WS_PATH));
echo cloud_sync_encode([
    'ok' => true,
    'protocol' => CLOUD_SYNC_PROTOCOL_VERSION,
    'transport' => 'websocket',
    'ws_url' => "{$scheme}://{$host}{$wsPublic}",
    'ws_path' => CLOUD_SYNC_WS_PATH,
    'chunk_bytes' => CLOUD_SYNC_CHUNK_BYTES,
    'manifest_page' => CLOUD_SYNC_MANIFEST_PAGE,
    'allowed_roots' => CLOUD_SYNC_ALLOWED_ROOTS,
    'default_root' => 'clientsync',
    'sync_mode' => cloud_sync_user_mode($email),
    'deprecated_http_actions' => ['sync_manifest', 'sync_manifest_client'],
    'message' => 'Use WebSocket sync; HTTP manifest endpoints are deprecated bootstrap-only.',
]);
