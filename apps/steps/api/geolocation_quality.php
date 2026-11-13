<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_quality.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_quality', 60, 60);
$pdo = app_main_db()->getPdo();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $body = public_api_body();
    if (strtolower(trim((string)($body['action'] ?? ''))) !== 'reprocess') {
        public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
    }
    $queued = StepsGeolocationQuality::enqueue($pdo, $owner);
    public_api_json(['ok' => true, 'queued' => $queued]);
}

$counts = ['pending' => 0, 'accepted' => 0, 'flagged' => 0, 'rejected' => 0];
$stmt = $pdo->prepare('SELECT quality_state, COUNT(*) AS c FROM geo_location_points WHERE user_email = :owner GROUP BY quality_state');
$stmt->execute([':owner' => $owner]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $state = (string)$row['quality_state'];
    if (array_key_exists($state, $counts)) $counts[$state] = (int)$row['c'];
}

$runs = [];
$runStmt = $pdo->prepare('SELECT id, state, processor_version, details_json, input_from, input_to, created_at, updated_at FROM geo_processor_runs WHERE user_email = :owner AND kind = :kind ORDER BY created_at DESC LIMIT 10');
$runStmt->execute([':owner' => $owner, ':kind' => 'quality']);
foreach ($runStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $row['details_json'] = json_decode((string)$row['details_json'], true) ?: [];
    $runs[] = $row;
}

public_api_json([
    'ok' => true,
    'counts' => $counts,
    'thresholds' => StepsGeolocationQuality::thresholds(),
    'processor_version' => StepsGeolocationQuality::PROCESSOR_VERSION,
    'runs' => $runs,
]);
