<?php
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    sp_json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$token = (string)($_GET['share_token'] ?? '');
$repo = new ShareRepository();
$share = $repo->findByToken($token);
if (!$share) {
    sp_json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$payload = sp_request_body();
$csrf = (string)($_SERVER['HTTP_X_SHAREPOINT_CSRF'] ?? ($payload['_csrf'] ?? ''));
if (!sp_verify_csrf($csrf)) {
    sp_json_response(['ok' => false, 'error' => 'invalid_request'], 403);
}

$action = strtolower(trim((string)($payload['action'] ?? '')));
if ($action === '') {
    sp_json_response(['ok' => false, 'error' => 'invalid_request'], 400);
}

$required = 'interact';
$handler = ResourceResolver::handler($share);
if ($handler instanceof AppHandlerInterface && in_array($action, $handler->getEditableActions(), true)) {
    $required = 'edit';
}

$access = ShareAccessPolicy::check($share, $required);
if (!$access->isAllowed()) {
    sp_json_response(['ok' => false, 'error' => $access->reason], AccessResult::httpStatus($access->reason));
}
if (!$handler || !$access->resource) {
    sp_json_response(['ok' => false, 'error' => 'resource_unavailable'], 404);
}

$allowed = $handler instanceof AppHandlerInterface ? array_merge($handler->getPublicEndpoints(), $handler->getEditableActions()) : [];
if (!in_array($action, $allowed, true)) {
    sp_json_response(['ok' => false, 'error' => 'unsupported_action'], 400);
}

try {
    $result = $handler->handleAction($share, $access->resource, $action, $payload);
    $result['ok'] = $result['ok'] ?? true;
    sp_json_response($result);
} catch (Throwable $e) {
    error_log('sharepoint action: ' . $e->getMessage());
    sp_json_response(['ok' => false, 'error' => 'action_failed'], 500);
}
