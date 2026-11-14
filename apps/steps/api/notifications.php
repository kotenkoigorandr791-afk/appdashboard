<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_notifications.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_notifications', 60, 60);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET') {
        public_api_json([
            'ok' => true,
            'preferences' => StepsGeolocationNotifications::preferences($pdo, $owner),
            'events' => StepsGeolocationNotifications::EVENTS,
            'webhooks' => StepsGeolocationNotifications::webhooks($pdo, $owner),
            'deliveries' => StepsGeolocationNotifications::deliveries($pdo, $owner),
        ]);
    }
    if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
    $body = public_api_body();
    $action = strtolower(trim((string)($body['action'] ?? '')));

    if ($action === 'save_preferences') {
        public_api_json(['ok' => true, 'preferences' => StepsGeolocationNotifications::savePreferences($pdo, $owner, $body)]);
    }
    if ($action === 'create_webhook') {
        $saved = StepsGeolocationNotifications::saveWebhook($pdo, $owner, $body);
        public_api_json(['ok' => true, 'webhook' => $saved['webhook'], 'secret' => $saved['secret']], 201);
    }
    if ($action === 'delete_webhook') {
        $id = trim((string)($body['id'] ?? ''));
        if (!StepsGeolocationDomain::isUuid($id)) public_api_json(['ok' => false, 'error' => 'invalid_id'], 400);
        StepsGeolocationNotifications::deleteWebhook($pdo, $owner, $id);
        public_api_json(['ok' => true, 'deleted' => true]);
    }
    if ($action === 'test_webhook') {
        StepsGeolocationNotifications::enqueue($pdo, $owner, 'webhook_test', 'Steps webhook test', 'This is a test event from Steps.', ['test' => true], 'info', 'webhook_test:' . time());
        public_api_json(['ok' => true, 'queued' => true]);
    }
    public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
} catch (InvalidArgumentException $e) {
    public_api_json(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('geolocation notifications api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'notifications_update_failed'], 500);
}
