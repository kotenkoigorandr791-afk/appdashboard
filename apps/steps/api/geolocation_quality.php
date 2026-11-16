<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_quality.php';
require_once dirname(__DIR__) . '/core/geolocation_corrections.php';
require_once dirname(__DIR__) . '/core/geolocation_matching.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_quality', 60, 60);
$pdo = app_main_db()->getPdo();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $body = public_api_body();
    $action = strtolower(trim((string)($body['action'] ?? '')));
    try {
        if ($action === 'reprocess') {
            $queued = StepsGeolocationQuality::enqueue($pdo, $owner);
            public_api_json(['ok' => true, 'queued' => $queued]);
        }
        if ($action === 'match') {
            $queued = StepsGeolocationMatching::enqueue($pdo, $owner);
            public_api_json(['ok' => true, 'queued' => $queued]);
        }
        if ($action === 'settings') {
            public_api_json(['ok' => true, 'settings' => StepsGeolocationMatching::saveSettings($pdo, $owner, $body)]);
        }
        if ($action === 'correct') {
            public_api_json(['ok' => true, 'correction' => StepsGeolocationCorrections::create($pdo, $owner, $body)]);
        }
        if ($action === 'undo') {
            public_api_json(['ok' => true, 'correction' => StepsGeolocationCorrections::revoke($pdo, $owner, (string)($body['id'] ?? ''))]);
        }
        public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
    } catch (StepsGeolocationCorrectionError $e) {
        public_api_json(['ok' => false, 'error' => $e->errorCode], $e->status);
    } catch (Throwable $e) {
        error_log('geolocation quality action failed: ' . $e->getMessage());
        public_api_json(['ok' => false, 'error' => 'quality_action_failed'], 500);
    }
}

$states = ['pending', 'accepted', 'suspect', 'flagged', 'rejected', 'excluded_by_user', 'private_redacted', 'not_enough_evidence'];
$counts = array_fill_keys($states, 0);
$stmt = $pdo->prepare('SELECT quality_state, COUNT(*) AS c FROM geo_location_points WHERE user_email = :owner GROUP BY quality_state');
$stmt->execute([':owner' => $owner]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $state = (string)$row['quality_state'];
    if (array_key_exists($state, $counts)) $counts[$state] = (int)$row['c'];
}

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$filterFrom = $from !== '' ? strtotime($from) : false;
$filterTo = $to !== '' ? strtotime($to) : false;
$issues = [];
$issueSql = "SELECT id, source_at, accuracy_m, quality_state, quality_reason, quality_score, quality_severity, quality_version, quality_details, correction_json
               FROM geo_location_points
              WHERE user_email = :owner AND quality_state NOT IN ('accepted','pending')";
$issueParams = [':owner' => $owner];
if ($filterFrom !== false && $filterTo !== false && $filterTo > $filterFrom) {
    if ($filterTo - $filterFrom > 366 * 86400) public_api_json(['ok' => false, 'error' => 'range_too_large'], 400);
    $issueSql .= ' AND source_at BETWEEN :from AND :to';
    $issueParams[':from'] = gmdate('c', $filterFrom);
    $issueParams[':to'] = gmdate('c', $filterTo);
}
$issueSql .= ' ORDER BY source_at DESC LIMIT 100';
$issueStmt = $pdo->prepare($issueSql);
$issueStmt->execute($issueParams);
foreach ($issueStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $row['quality_score'] = $row['quality_score'] !== null ? (float)$row['quality_score'] : null;
    $row['accuracy_m'] = $row['accuracy_m'] !== null ? (float)$row['accuracy_m'] : null;
    $row['quality_details'] = json_decode((string)$row['quality_details'], true) ?: [];
    $row['reasons'] = StepsGeolocationQuality::reasonsFromDetails($row['quality_details']);
    unset($row['quality_details']);
    $issues[] = $row;
}

$runs = [];
$runStmt = $pdo->prepare('SELECT id, kind, state, processor_version, details_json, input_from, input_to, created_at, updated_at FROM geo_processor_runs WHERE user_email = :owner AND kind IN (:quality, :matching) ORDER BY created_at DESC LIMIT 12');
$runStmt->execute([':owner' => $owner, ':quality' => 'quality', ':matching' => 'matching']);
foreach ($runStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $row['details_json'] = json_decode((string)$row['details_json'], true) ?: [];
    $runs[] = $row;
}

public_api_json([
    'ok' => true,
    'counts' => $counts,
    'issues' => $issues,
    'corrections' => StepsGeolocationCorrections::listForOwner($pdo, $owner),
    'settings' => StepsGeolocationMatching::settings($pdo, $owner),
    'matching' => StepsGeolocationMatching::summary($pdo, $owner),
    'thresholds' => StepsGeolocationQuality::thresholds(),
    'processor_version' => StepsGeolocationQuality::PROCESSOR_VERSION,
    'matcher_version' => StepsGeolocationMapMatcher::VERSION,
    'effects' => StepsGeolocationCorrections::EFFECTS,
    'runs' => $runs,
]);
