<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_analytics.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_analytics', 60, 60);
$pdo = app_main_db()->getPdo();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $body = public_api_body();
        $action = strtolower(trim((string)($body['action'] ?? 'recompute')));
        if ($action === 'recompute') {
            StepsGeolocationAnalytics::invalidate($pdo, $owner);
            public_api_json(['ok' => true, 'queued' => true, 'formula_version' => StepsGeolocationAnalytics::VERSION]);
        }
        if ($action === 'refresh') {
            public_api_json(StepsGeolocationAnalytics::summary($pdo, $owner, [
                'from' => $body['from'] ?? null, 'to' => $body['to'] ?? null,
                'timezone' => $body['tz'] ?? 'UTC', 'compare' => !empty($body['compare']), 'refresh' => true,
            ]));
        }
        public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
    }
    public_api_json(StepsGeolocationAnalytics::summary($pdo, $owner, [
        'from' => $_GET['from'] ?? null, 'to' => $_GET['to'] ?? null,
        'timezone' => $_GET['tz'] ?? 'UTC', 'compare' => !empty($_GET['compare']),
        'refresh' => !empty($_GET['refresh']),
    ]));
} catch (InvalidArgumentException $e) {
    public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
} catch (Throwable $e) {
    error_log('geolocation analytics api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'analytics_failed'], 500);
}
