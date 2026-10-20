<?php
// Legacy entrypoint: old links used ?sharedFile=<id>. The identifier is now the
// public SharePoint token, so redirect to the canonical /s/{token} URL.
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
