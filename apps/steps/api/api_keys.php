<?php
/**
 * API key management for the existing Settings UI (task 28).
 *
 * Session-authenticated only: issuance, rotation and revocation never happen
 * through the key-authenticated REST API itself. The plaintext secret is
 * returned once on create/rotate and never stored.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_api.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_api_keys', 60, 60);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    public_api_json([
        'ok' => true,
        'keys' => StepsGeolocationApi::listKeys($pdo, $owner),
        'scopes' => StepsGeolocationApi::SCOPES,
        'default_scopes' => StepsGeolocationApi::DEFAULT_SCOPES,
        'api_version' => StepsGeolocationApi::VERSION,
    ]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
if ($origin !== '' && strcasecmp((string)parse_url($origin, PHP_URL_HOST), (string)$host) !== 0) {
    public_api_json(['ok' => false, 'error' => 'forbidden_origin'], 403);
}
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? 'create')));
try {
    if ($action === 'create') {
        $key = StepsGeolocationApi::issueKey(
            $pdo,
            $owner,
            (string)($body['name'] ?? ''),
            is_array($body['scopes'] ?? null) ? $body['scopes'] : StepsGeolocationApi::DEFAULT_SCOPES
        );
        public_api_json(['ok' => true, 'key' => $key, 'keys' => StepsGeolocationApi::listKeys($pdo, $owner)], 201);
    }
    if ($action === 'rotate') {
        $key = StepsGeolocationApi::rotateKey($pdo, $owner, trim((string)($body['id'] ?? '')));
        public_api_json(['ok' => true, 'key' => $key, 'keys' => StepsGeolocationApi::listKeys($pdo, $owner)]);
    }
    if ($action === 'revoke') {
        StepsGeolocationApi::revokeKey($pdo, $owner, trim((string)($body['id'] ?? '')));
        public_api_json(['ok' => true, 'keys' => StepsGeolocationApi::listKeys($pdo, $owner)]);
    }
    public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
} catch (StepsGeolocationApiException $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode, 'details' => $e->details], $e->status);
} catch (Throwable $e) {
    error_log('geolocation api keys: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'internal_error'], 500);
}
