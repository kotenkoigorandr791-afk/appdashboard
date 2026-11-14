<?php
/**
 * Narrow read-only MCP transport for Steps geolocation (task 29).
 *
 * The MCP server authenticates with the ordinary 64-hex Public API bearer plus
 * X-Device-Id. This endpoint accepts only declared read tools; mutations,
 * exports and sharing are rejected before dispatch. It is not a generic proxy
 * and never exposes SQL or database credentials.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_security.php';
require_once dirname(__DIR__) . '/core/geolocation_mcp.php';

public_api_bootstrap();
$requestId = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if (!preg_match('/^[A-Za-z0-9._:-]{1,80}$/', $requestId)) $requestId = bin2hex(random_bytes(8));
if (!headers_sent()) header('X-Request-Id: ' . $requestId);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed', 'request_id' => $requestId], 405);

try {
    $auth = public_api_auth_user(true);
    public_api_check_app('steps');
    public_api_rate_limit('steps_mcp_read', 120, 60);
    $owner = StepsGeolocationDomain::normalizeOwner((string)$auth['email']);
    $body = public_api_body();
    if ($body === []) $body = is_array($_POST) ? $_POST : [];
    $tool = trim((string)($body['tool'] ?? $_GET['tool'] ?? ''));
    if ($tool === '') public_api_json(['ok' => false, 'error' => 'tool_required', 'request_id' => $requestId], 400);
    $args = $body['arguments'] ?? null;
    if (!is_array($args)) {
        $args = $body;
        unset($args['tool'], $args['arguments']);
    }
    $pdo = app_main_db()->getPdo();
    $result = StepsGeolocationMcp::run($pdo, $owner, $tool, $args, $requestId);
    public_api_json(['ok' => true, 'request_id' => $requestId] + $result);
} catch (StepsGeolocationMcpError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode, 'request_id' => $requestId], $e->status);
} catch (StepsGeolocationSecurityError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode, 'request_id' => $requestId], $e->status);
} catch (StepsGeolocationPermissionsError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode, 'request_id' => $requestId], $e->status);
} catch (Throwable $e) {
    error_log('steps mcp read [' . $requestId . ']: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'internal_error', 'request_id' => $requestId], 500);
}
