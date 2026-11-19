<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_search.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_search', 180, 60);
$pdo = app_main_db()->getPdo();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

try {
    $opts = [
        'q' => (string)($_GET['q'] ?? $_GET['query'] ?? ''),
        'types' => $_GET['types'] ?? $_GET['type'] ?? null,
        'from' => isset($_GET['from']) ? (string)$_GET['from'] : null,
        'to' => isset($_GET['to']) ? (string)$_GET['to'] : null,
        'timezone' => (string)($_GET['tz'] ?? 'UTC'),
        'limit' => (int)($_GET['limit'] ?? 20),
        'cursor' => (string)($_GET['cursor'] ?? ''),
        'sort' => (string)($_GET['sort'] ?? 'relevance'),
    ];
    if (strtolower(trim((string)($_GET['mode'] ?? ''))) === 'suggest') {
        public_api_json(['ok' => true] + StepsGeolocationSearch::suggest($pdo, $owner, $opts));
    }
    $result = StepsGeolocationSearch::search($pdo, $owner, $opts);
    public_api_json(['ok' => true] + $result);
} catch (InvalidArgumentException $e) {
    public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
} catch (Throwable $e) {
    error_log('geolocation search api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'search_failed'], 500);
}
