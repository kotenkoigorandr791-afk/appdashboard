<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_saved_views.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_saved_views', 120, 60);
$pdo = app_main_db()->getPdo();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $body = public_api_body();
        $action = strtolower(trim((string)($body['action'] ?? '')));
        if ($action === 'create') public_api_json(['ok' => true, 'view' => StepsGeolocationSavedViews::create($pdo, $owner, $body)]);
        if ($action === 'update') public_api_json(['ok' => true, 'view' => StepsGeolocationSavedViews::update($pdo, $owner, (string)($body['id'] ?? ''), $body)]);
        if ($action === 'delete') { StepsGeolocationSavedViews::delete($pdo, $owner, (string)($body['id'] ?? '')); public_api_json(['ok' => true]); }
        if ($action === 'duplicate') public_api_json(['ok' => true, 'view' => StepsGeolocationSavedViews::duplicate($pdo, $owner, (string)($body['id'] ?? ''))]);
        if ($action === 'pin') {
            $view = StepsGeolocationSavedViews::get($pdo, $owner, (string)($body['id'] ?? ''));
            public_api_json(['ok' => true, 'view' => StepsGeolocationSavedViews::update($pdo, $owner, $view['id'], ['is_pinned' => !$view['is_pinned']])]);
        }
        if ($action === 'default') {
            $view = StepsGeolocationSavedViews::get($pdo, $owner, (string)($body['id'] ?? ''));
            public_api_json(['ok' => true, 'view' => StepsGeolocationSavedViews::update($pdo, $owner, $view['id'], ['is_default' => !$view['is_default']])]);
        }
        if ($action === 'reorder') {
            $view = StepsGeolocationSavedViews::get($pdo, $owner, (string)($body['id'] ?? ''));
            public_api_json(['ok' => true, 'view' => StepsGeolocationSavedViews::update($pdo, $owner, $view['id'], ['sort_order' => (int)($body['sort_order'] ?? 0)])]);
        }
        if ($action === 'import') public_api_json(['ok' => true, 'view' => StepsGeolocationSavedViews::importDefinition($pdo, $owner, $body)]);
        public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
    }

    $id = trim((string)($_GET['id'] ?? ''));
    if ($id !== '') {
        if (!empty($_GET['export'])) public_api_json(['ok' => true, 'definition' => StepsGeolocationSavedViews::exportDefinition($pdo, $owner, $id)]);
        if (!empty($_GET['apply'])) public_api_json(['ok' => true] + StepsGeolocationSavedViews::apply($pdo, $owner, $id, (string)($_GET['tz'] ?? 'UTC')));
        public_api_json(['ok' => true, 'view' => StepsGeolocationSavedViews::get($pdo, $owner, $id)]);
    }
    public_api_json(['ok' => true] + StepsGeolocationSavedViews::list($pdo, $owner));
} catch (StepsGeolocationSavedViewError $e) {
    public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
} catch (Throwable $e) {
    error_log('geolocation saved views api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'saved_view_failed'], 500);
}
