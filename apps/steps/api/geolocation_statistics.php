<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_statistics.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_statistics', 120, 60);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST') {
    $body = public_api_body();
    $action = strtolower(trim((string)($body['action'] ?? 'refresh')));
    if (!in_array($action, ['refresh', 'recompute'], true)) public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
    $from = isset($body['from']) ? (string)$body['from'] : null;
    $to = isset($body['to']) ? (string)$body['to'] : null;
    $timezone = StepsGeolocationStatistics::timezone((string)($body['tz'] ?? 'UTC'));
    if ($from !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
    if ($to !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
    if ($action === 'recompute') {
        try {
            $days = StepsGeolocationStatistics::refresh($pdo, $owner, $timezone, $from, $to);
            public_api_json(['ok' => true, 'days' => $days]);
        } catch (InvalidArgumentException $e) {
            public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
        }
    }
    public_api_json(['ok' => true, 'queued' => StepsGeolocationStatistics::enqueue($pdo, $owner, $from, $to)]);
}

$timezone = StepsGeolocationStatistics::timezone((string)($_GET['tz'] ?? 'UTC'));
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['from']) ? (string)$_GET['from'] : null;
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['to']) ? (string)$_GET['to'] : null;
if (($from === null) !== ($to === null)) public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
if ($from !== null && strcmp($from, $to) > 0) public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);

try {
    if ($from === null) {
        $cached = $pdo->prepare('SELECT payload_json, computed_at FROM geo_statistics_cache WHERE user_email = :owner AND timezone = :tz AND processor_version = :version');
        $cached->execute([':owner' => $owner, ':tz' => $timezone, ':version' => StepsGeolocationStatistics::PROCESSOR_VERSION]);
        $row = $cached->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $payload = json_decode((string)$row['payload_json'], true);
            if (is_array($payload)) {
                $payload['cached'] = true;
                $payload['computed_at'] = $row['computed_at'];
                public_api_json(['ok' => true] + $payload);
            }
        }
    }
    $summary = StepsGeolocationStatistics::summary($pdo, $owner, $timezone, $from, $to);
    if ($from === null) {
        $latest = $pdo->prepare('SELECT MAX(source_at) FROM geo_location_points WHERE user_email = :owner');
        $latest->execute([':owner' => $owner]);
        $payload = $summary + ['cached' => false, 'computed_at' => gmdate('c')];
        $upsert = $pdo->prepare(
            "INSERT INTO geo_statistics_cache(user_email, timezone, processor_version, boundary_version, input_to, payload_json, computed_at)
             VALUES(:owner, :tz, :version, :boundary, :input_to, :payload, now())
             ON CONFLICT (user_email, timezone) DO UPDATE SET processor_version = EXCLUDED.processor_version, boundary_version = EXCLUDED.boundary_version, input_to = EXCLUDED.input_to, payload_json = EXCLUDED.payload_json, computed_at = now()"
        );
        $upsert->execute([
            ':owner' => $owner, ':tz' => $timezone, ':version' => StepsGeolocationStatistics::PROCESSOR_VERSION,
            ':boundary' => (string)($summary['boundary']['country_dataset']['version'] ?? ''),
            ':input_to' => $latest->fetchColumn() ?: null,
            ':payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
        public_api_json(['ok' => true] + $payload);
    }
    public_api_json(['ok' => true] + $summary + ['cached' => false]);
} catch (InvalidArgumentException $e) {
    public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
} catch (Throwable $e) {
    error_log('geolocation statistics api: ' . $e->getMessage());
    public_api_json(['ok' => false, 'error' => 'statistics_failed'], 500);
}
