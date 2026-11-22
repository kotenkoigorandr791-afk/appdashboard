<?php
require_once dirname(__DIR__, 3) . '/lib/public_api.php';
require_once dirname(__DIR__) . '/auth_common.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';
require_once dirname(__DIR__) . '/core/geolocation_place_resolver.php';
require_once dirname(__DIR__) . '/core/geolocation_observability.php';
public_api_bootstrap();
$owner = get_current_user_email();
if (!$owner) public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
$owner = StepsGeolocationDomain::normalizeOwner($owner);
public_api_rate_limit('geolocation_place_prefs', 60, 60);
$pdo = app_main_db()->getPdo();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    public_api_json([
        'ok' => true,
        'profile' => StepsGeolocationPlaceResolver::profile($pdo, $owner),
        'profiles' => StepsGeolocationPlaceProfiles::ids(),
        'pending' => StepsGeolocationPlaceResolver::pendingCount($pdo, $owner),
        'last_run' => StepsGeolocationPlaceResolver::lastRun($pdo, $owner),
    ]);
}
if ($method !== 'POST') public_api_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
$body = public_api_body();
$action = strtolower(trim((string)($body['action'] ?? 'profile')));
if ($action === 'reprocess' || $action === 'detect') {
    $result = StepsGeolocationPlaceResolver::enqueue($pdo, $owner, true);
    StepsGeolocationObservability::invalidateOwner($owner);
    public_api_json(['ok' => true, 'queued' => $result]);
}
if ($action === 'recalculate') {
    require_once dirname(__DIR__) . '/core/geolocation_stops.php';
    $fromRaw = trim((string)($body['from'] ?? ''));
    $toRaw = trim((string)($body['to'] ?? ''));
    $fromTs = strtotime($fromRaw);
    $toTs = strtotime($toRaw);
    if ($fromTs === false || $toTs === false || $toTs < $fromTs) {
        public_api_json(['ok' => false, 'error' => 'invalid_range'], 400);
    }
    // Cap to 366 days so a mistaken full-history click cannot thrash the worker.
    if (($toTs - $fromTs) > 366 * 86400) {
        public_api_json(['ok' => false, 'error' => 'range_too_large'], 400);
    }
    $fromIso = gmdate('c', $fromTs);
    $toIso = gmdate('c', $toTs);
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "DELETE FROM geo_visits
              WHERE user_email = :owner AND COALESCE(override_json->>'manual', 'false') <> 'true'
                AND arrived_at >= :from AND arrived_at <= :to"
        )->execute([':owner' => $owner, ':from' => $fromIso, ':to' => $toIso]);
        $pdo->prepare(
            "DELETE FROM geo_stops
              WHERE user_email = :owner AND COALESCE(override_json->>'manual', 'false') <> 'true'
                AND started_at >= :from AND started_at <= :to"
        )->execute([':owner' => $owner, ':from' => $fromIso, ':to' => $toIso]);
        $runId = StepsGeolocationStops::uuid();
        $pdo->prepare(
            "INSERT INTO geo_processor_runs(id, user_email, kind, state, processor_version, details_json)
             VALUES(:id, :owner, 'stops', 'pending', :version, CAST(:details AS jsonb))"
        )->execute([
            ':id' => $runId,
            ':owner' => $owner,
            ':version' => StepsGeolocationStops::PROCESSOR_VERSION,
            ':details' => json_encode(['force_from' => $fromIso, 'force_to' => $toIso, 'source' => 'recalculate'], JSON_UNESCAPED_SLASHES),
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        public_api_json(['ok' => false, 'error' => 'recalculate_failed'], 500);
    }
    StepsGeolocationObservability::invalidateOwner($owner);
    public_api_json(['ok' => true, 'queued' => ['run_id' => $runId, 'from' => $fromIso, 'to' => $toIso]]);
}
if ($action === 'profile' || isset($body['profile'])) {
    $profile = strtolower(trim((string)($body['profile'] ?? '')));
    if (!in_array($profile, StepsGeolocationPlaceProfiles::ids(), true)) public_api_json(['ok' => false, 'error' => 'invalid_profile'], 400);
    $saved = StepsGeolocationPlaceResolver::setProfile($pdo, $owner, $profile);
    $pdo->prepare("UPDATE geo_visits SET detection_status = 'pending', retry_after = NULL, resolve_attempts = 0 WHERE user_email = :o AND detection_status IN ('provisional','unidentified') AND detection_method IN ('osm_overpass','osm_unavailable') AND arrived_at > now() - interval '30 days' AND COALESCE(override_json->>'manual','false') <> 'true'")->execute([':o' => $owner]);
    StepsGeolocationPlaceResolver::enqueue($pdo, $owner, false);
    StepsGeolocationObservability::invalidateOwner($owner);
    public_api_json(['ok' => true, 'profile' => $saved, 'pending' => StepsGeolocationPlaceResolver::pendingCount($pdo, $owner)]);
}
public_api_json(['ok' => false, 'error' => 'invalid_action'], 400);
