<?php
/**
 * Owner-only privacy controls (task 30): policy, counts, correction,
 * range deletion and account purge. No public link is ever created.
 */
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_privacy.php';
require_once dirname(__DIR__) . '/core/geolocation_exports.php';

public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner((string)$owner);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST') {
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($origin !== '' && strcasecmp((string)parse_url($origin, PHP_URL_HOST), (string)$host) !== 0) {
        public_api_json(['ok' => false, 'error' => 'invalid_request', 'reason' => 'forbidden_origin'], 403);
    }
    public_api_rate_limit('geolocation_privacy_write', 30, 60);
} else {
    public_api_rate_limit('geolocation_privacy_read', 120, 60);
}

if ($method === 'GET') {
    $audit = [];
    try {
        $stmt = $pdo->prepare("SELECT event_type, context_json, created_at FROM geo_audit_events WHERE user_email = :owner AND event_type NOT LIKE 'access.read' ORDER BY created_at DESC LIMIT 25");
        $stmt->execute([':owner' => $owner]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $audit[] = [
                'event_type' => (string)$row['event_type'],
                'context' => StepsGeolocationSecurity::redactContext(json_decode((string)$row['context_json'], true) ?: []),
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        $audit = [];
    }
    public_api_json([
        'ok' => true,
        'policy' => StepsGeolocationPrivacy::policy($pdo, $owner),
        'counts' => StepsGeolocationPrivacy::counts($pdo, $owner),
        'audit' => $audit,
        'limits' => [
            'summary_precision_decimals' => ['min' => 0, 'max' => 4],
            'retention_days' => ['min' => 0, 'max' => 3650],
            'audit_retention_days' => ['min' => 30, 'max' => 3650],
            'delete_range_days' => StepsGeolocationPrivacy::MAX_RANGE_DAYS,
            'purge_confirmation' => StepsGeolocationPrivacy::PURGE_CONFIRMATION,
        ],
    ]);
}

if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);

$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? '')));
try {
    if ($action === 'policy') {
        $policy = StepsGeolocationPrivacy::savePolicy($pdo, $owner, $body);
        public_api_json(['ok' => true, 'policy' => $policy]);
    }
    if ($action === 'correct_point') {
        $point = StepsGeolocationPrivacy::correctPoint(
            $pdo,
            $owner,
            trim((string)($body['point_id'] ?? $body['id'] ?? '')),
            $body['latitude'] ?? null,
            $body['longitude'] ?? null,
            isset($body['observed_at']) ? (string)$body['observed_at'] : null
        );
        public_api_json(['ok' => true, 'point' => $point]);
    }
    if ($action === 'delete_range') {
        $counts = StepsGeolocationPrivacy::deleteRange($pdo, $owner, $body['from'] ?? null, $body['to'] ?? null);
        public_api_json(['ok' => true, 'deleted' => $counts]);
    }
    if ($action === 'purge') {
        $confirmation = (string)($body['confirmation'] ?? $body['confirm'] ?? '');
        if (!empty($body['async'])) {
            $id = StepsGeolocationPrivacy::enqueuePurge($pdo, $owner);
            public_api_json(['ok' => true, 'queued' => true, 'request_id' => $id], 202);
        }
        $counts = StepsGeolocationPrivacy::purge($pdo, $owner, $owner, $confirmation);
        public_api_json(['ok' => true, 'purged' => $counts]);
    }
    public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
} catch (StepsGeolocationPrivacyError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
} catch (StepsGeolocationSecurityError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
} catch (InvalidArgumentException $e) {
    public_api_json(['ok' => false, 'error' => 'invalid_request'], 400);
} catch (Throwable $e) {
    error_log('geolocation privacy: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'internal_error'], 500);
}
