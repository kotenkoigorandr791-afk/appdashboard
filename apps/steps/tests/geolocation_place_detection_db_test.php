<?php
/**
 * Overpass client (failover, 429/504, cache, dedupe) and visit resolution,
 * manual corrections and profile storage against PostgreSQL.
 *
 *   php apps/steps/tests/geolocation_place_detection_db_test.php
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_place_resolver.php';

function geo_pdb_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$owner = 'qa-place-detect@example.test';
$db = app_main_db();
$pdo = $db->getPdo();
steps_geolocation_migrate($db);
$lat = 50.0647 + (random_int(0, 9999) / 1_000_000);
$lon = 19.9450 + (random_int(0, 9999) / 1_000_000);
$clean = static function () use ($pdo, $owner): void {
    $pdo->prepare('DELETE FROM geo_visit_candidates WHERE user_email = :o')->execute([':o' => $owner]);
    $pdo->prepare('DELETE FROM geo_visits WHERE user_email = :o')->execute([':o' => $owner]);
    $pdo->prepare('DELETE FROM geo_stops WHERE user_email = :o')->execute([':o' => $owner]);
    $pdo->prepare('DELETE FROM geo_places WHERE user_email = :o')->execute([':o' => $owner]);
    $pdo->prepare('DELETE FROM geo_place_blocks WHERE user_email = :o')->execute([':o' => $owner]);
    $pdo->prepare("DELETE FROM steps_settings WHERE user_email = :o AND key = 'occupation'")->execute([':o' => $owner]);
    $pdo->prepare('DELETE FROM geo_location_points WHERE user_email = :o')->execute([':o' => $owner]);
    $pdo->prepare('DELETE FROM geo_devices WHERE user_email = :o')->execute([':o' => $owner]);
    $pdo->exec("DELETE FROM geo_osm_query_cache WHERE cache_key LIKE 'cooldown:%' OR cache_key LIKE 'q:%'");
    $pdo->exec('DELETE FROM geo_osm_places WHERE osm_id BETWEEN 990000001 AND 990000099');
};
$clean();

$providers = ['https://p1.test/api', 'https://p2.test/api', 'https://p3.test/api'];
$config = ['providers' => $providers, 'search_radius_m' => 100.0, 'timeout_s' => 5, 'max_attempts' => 4, 'backoff_base_ms' => 0, 'provider_cooldown_s' => 60, 'min_interval_ms' => 0, 'cache_ttl_s' => 3600, 'negative_cache_ttl_s' => 60, 'cache_grid_deg' => 0.0005, 'max_elements' => 150];
$sleeps = [];
$sleeper = static function (int $ms) use (&$sleeps): void { $sleeps[] = $ms; };
$payload = static fn(): string => json_encode(['elements' => [
    ['type' => 'node', 'id' => 990000001, 'lat' => $GLOBALS['lat'], 'lon' => $GLOBALS['lon'], 'tags' => ['amenity' => 'cafe', 'name' => 'QA Cafe']],
    ['type' => 'node', 'id' => 990000002, 'lat' => $GLOBALS['lat'] + 0.0002, 'lon' => $GLOBALS['lon'], 'tags' => ['shop' => 'bakery', 'name' => 'QA Bakery']],
]]);
$calls = [];
$script = [];
$transport = static function (string $url, string $query, int $timeout) use (&$calls, &$script): array {
    $calls[] = $url;
    $step = array_shift($script);
    if ($step === null) return [200, $GLOBALS['payload'](), []];
    return $step($url);
};

try {
    $script = [fn() => [502, '', []], fn() => [200, $payload(), []]];
    $osm = new StepsGeolocationOsm($pdo, $config, $transport, $sleeper);
    $first = $osm->lookup($lat, $lon);
    geo_pdb_assert($first['status'] === 'ok' && count($first['elements']) === 2 && $first['provider'] === $providers[1], 'failover to the second provider');
    geo_pdb_assert($calls === [$providers[0], $providers[1]] && $osm->stats['failovers'] === 1, 'one failed provider then success');
    $calls = [];
    $again = $osm->lookup($lat + 0.00001, $lon);
    geo_pdb_assert($again['from_cache'] === true && $calls === [] && count($again['elements']) === 2, 'nearby lookup served from cache without network');
    $row = $pdo->query("SELECT count(*) FROM geo_osm_places WHERE osm_id IN (990000001, 990000002)")->fetchColumn();
    geo_pdb_assert((int)$row === 2, 'OSM objects stored once by type and id');
    $osm2 = new StepsGeolocationOsm($pdo, $config, $transport, $sleeper);
    $osm2->lookup($lat, $lon);
    geo_pdb_assert((int)$pdo->query("SELECT count(*) FROM geo_osm_places WHERE osm_id IN (990000001, 990000002)")->fetchColumn() === 2, 'no duplicate OSM rows');
    $first = $osm->lookup($lat, $lon);
    $cached = $pdo->query("SELECT element_refs FROM geo_osm_query_cache WHERE cache_key LIKE 'q:%' LIMIT 1")->fetchColumn();
    geo_pdb_assert(str_contains((string)$cached, '990000001'), 'query cache stores references');

    $pdo->exec("DELETE FROM geo_osm_query_cache WHERE cache_key LIKE 'q:%' OR cache_key LIKE 'cooldown:%'");
    $calls = [];
    $script = [fn() => [429, '', ['retry-after' => '90']], fn() => [504, '', []], fn() => [200, $payload(), []]];
    $osm3 = new StepsGeolocationOsm($pdo, $config, $transport, $sleeper);
    $res = $osm3->lookup($lat + 0.01, $lon);
    geo_pdb_assert($res['status'] === 'ok' && count($calls) === 3 && count(array_unique($calls)) === 3, '429 and 504 move to other providers');
    geo_pdb_assert($sleeps !== [], 'exponential backoff hook used');
    $cool = (int)$pdo->query("SELECT count(*) FROM geo_osm_query_cache WHERE cache_key LIKE 'cooldown:%' AND expires_at > now()")->fetchColumn();
    geo_pdb_assert($cool === 2, 'failed providers are cooled down');
    $calls = [];
    $script = [];
    $osm3->lookup($lat + 0.02, $lon);
    geo_pdb_assert($calls === [$providers[2]], 'cooled providers are skipped, got ' . implode(',', $calls));

    $pdo->exec("DELETE FROM geo_osm_query_cache WHERE cache_key LIKE 'q:%' OR cache_key LIKE 'cooldown:%'");
    $calls = [];
    $script = array_fill(0, 4, fn() => [504, '', []]);
    $osm4 = new StepsGeolocationOsm($pdo, $config, $transport, $sleeper);
    $down = $osm4->lookup($lat + 0.03, $lon);
    geo_pdb_assert($down['status'] === 'unavailable' && $down['elements'] === [], 'all providers failing is reported');
    $callsAfter = count($calls);
    $osm4->lookup($lat + 0.03, $lon);
    geo_pdb_assert(count($calls) === $callsAfter, 'no immediate retry storm while providers are cooling');

} catch (Throwable $e) {
    $clean();
    throw $e;
}

try {
    $pdo->exec("DELETE FROM geo_osm_query_cache WHERE cache_key LIKE 'q:%' OR cache_key LIKE 'cooldown:%'");
    $second = app_pdo_open(app_db_load_config());
    $key = StepsGeolocationOsm::cacheKey($lat + 0.04, $lon, 100.0, 0.0005);
    $lockId = (int)sprintf('%u', crc32($key)) & 0x7fffffff;
    $hold = $second->prepare('SELECT pg_try_advisory_lock(:ns, :id)');
    $hold->execute([':ns' => 710103, ':id' => $lockId]);
    geo_pdb_assert((bool)$hold->fetchColumn(), 'test lock acquired');
    $calls = [];
    $osm5 = new StepsGeolocationOsm($pdo, $config, $transport, $sleeper);
    $busy = $osm5->lookup($lat + 0.04, $lon);
    geo_pdb_assert($busy['status'] === 'busy' && $calls === [], 'identical concurrent request is deduplicated');
    $second->prepare('SELECT pg_advisory_unlock(:ns, :id)')->execute([':ns' => 710103, ':id' => $lockId]);

    $device = StepsGeolocationPlaces::uuid();
    $pdo->prepare("INSERT INTO geo_devices(id,user_email,name,platform,source_type,status) VALUES(:id,:o,'QA','api','api','active')")->execute([':id' => $device, ':o' => $owner]);
    $started = gmdate('c', time() - 7200);
    $ended = gmdate('c', time() - 5400);
    $pointIns = $pdo->prepare("INSERT INTO geo_location_points(id,user_email,device_id,client_event_id,source_type,source_at,position,accuracy_m,quality_state) VALUES(:id,:o,:d,:e,'api',:at,ST_SetSRID(ST_MakePoint(:lon,:lat),4326),9,'accepted')");
    for ($i = 0; $i < 8; $i++) {
        $pointIns->execute([':id' => StepsGeolocationPlaces::uuid(), ':o' => $owner, ':d' => $device, ':e' => StepsGeolocationPlaces::uuid(), ':at' => gmdate('c', time() - 7200 + $i * 240), ':lon' => $lon, ':lat' => $lat]);
    }
    $stopId = StepsGeolocationPlaces::uuid();
    $pdo->prepare("INSERT INTO geo_stops(id,user_email,started_at,ended_at,position,radius_m,confidence,state) VALUES(:id,:o,:s,:e,ST_SetSRID(ST_MakePoint(:lon,:lat),4326),50,0.9,'ready')")->execute([':id' => $stopId, ':o' => $owner, ':s' => $started, ':e' => $ended, ':lon' => $lon, ':lat' => $lat]);
    $visitId = StepsGeolocationPlaces::uuid();
    $pdo->prepare("INSERT INTO geo_visits(id,user_email,stop_id,arrived_at,departed_at,confidence,state,override_json,detection_status) VALUES(:id,:o,:s,:a,:d,0.9,'ready','{}'::jsonb,'pending')")->execute([':id' => $visitId, ':o' => $owner, ':s' => $stopId, ':a' => $started, ':d' => $ended]);
    $visitRow = static function () use ($pdo, $visitId): array {
        $stmt = $pdo->prepare("SELECT v.id, v.user_email, v.resolve_attempts, v.departed_at, s.started_at, s.ended_at, ST_Y(s.position) AS lat, ST_X(s.position) AS lon, s.radius_m, s.override_json AS stop_override FROM geo_visits v JOIN geo_stops s ON s.id = v.stop_id WHERE v.id = :id");
        $stmt->execute([':id' => $visitId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    };
    $state = static function () use ($pdo, $visitId): array {
        $stmt = $pdo->prepare('SELECT detection_status, detection_method, display_name, category, osm_id, place_id, confidence FROM geo_visits WHERE id = :id');
        $stmt->execute([':id' => $visitId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    };

    $pdo->exec("DELETE FROM geo_osm_query_cache WHERE cache_key LIKE 'q:%' OR cache_key LIKE 'cooldown:%'");
    $script = array_fill(0, 4, fn() => [504, '', []]);
    $osm6 = new StepsGeolocationOsm($pdo, $config, $transport, $sleeper);
    $out = StepsGeolocationPlaceResolver::resolve($pdo, $osm6, $visitRow());
    geo_pdb_assert($out['status'] === 'pending' && $state()['detection_status'] === 'pending', 'unavailable Overpass leaves the visit pending for retry');
    $retry = $pdo->query("SELECT retry_after > now() AS later, resolve_attempts FROM geo_visits WHERE id = '$visitId'")->fetch(PDO::FETCH_ASSOC);
    geo_pdb_assert($retry['later'] && (int)$retry['resolve_attempts'] === 1, 'retry is scheduled with backoff');

    $pdo->exec("DELETE FROM geo_osm_query_cache WHERE cache_key LIKE 'q:%' OR cache_key LIKE 'cooldown:%'");
    $pdo->prepare('UPDATE geo_visits SET retry_after = NULL WHERE id = :id')->execute([':id' => $visitId]);
    StepsGeolocationPlaceResolver::setProfile($pdo, $owner, 'remote_worker');
    geo_pdb_assert(StepsGeolocationPlaceResolver::profile($pdo, $owner) === 'remote_worker', 'profile stored');
    geo_pdb_assert(StepsGeolocationPlaceResolver::setProfile($pdo, $owner, 'bogus') === 'prefer_not_to_say', 'unknown profile normalized');
    StepsGeolocationPlaceResolver::setProfile($pdo, $owner, 'remote_worker');
    $script = [fn() => [200, $payload(), []]];
    $out = StepsGeolocationPlaceResolver::resolve($pdo, new StepsGeolocationOsm($pdo, $config, $transport, $sleeper), $visitRow());
    $s = $state();
    geo_pdb_assert(in_array($out['status'], ['confirmed', 'provisional'], true) && $s['display_name'] === 'QA Cafe' && (int)$s['osm_id'] === 990000001 && $s['detection_method'] === 'osm_overpass', 'visit resolved to the cafe: ' . json_encode($s));
    $cands = StepsGeolocationPlaceResolver::candidates($pdo, $owner, $visitId);
    geo_pdb_assert(count($cands) === 2 && $cands[0]['selected'] === true && $cands[0]['name'] === 'QA Cafe', 'alternatives stored');

    $other = $cands[1];
    $r = StepsGeolocationPlaceResolver::selectCandidate($pdo, $owner, $visitId, $other['id']);
    $s = $state();
    geo_pdb_assert($r['status'] === 'manual' && $s['display_name'] === 'QA Bakery' && $s['detection_status'] === 'manual', 'manual candidate selection');
    $manualStop = $pdo->query("SELECT override_json->>'manual' FROM geo_stops WHERE id = '$stopId'")->fetchColumn();
    geo_pdb_assert($manualStop === 'true', 'corrected stop survives reprocessing');

    StepsGeolocationPlaceResolver::assign($pdo, $owner, $visitId, ['name' => 'Moje biuro', 'category' => 'office', 'tag' => 'work']);
    $s = $state();
    geo_pdb_assert($s['display_name'] === 'Moje biuro' && $s['place_id'] !== null && $s['detection_method'] === 'manual_place', 'assign as Work creates a personal place');
    $work = $pdo->prepare("SELECT count(*) FROM geo_places WHERE user_email = :o AND tag = 'work' AND is_active");
    $work->execute([':o' => $owner]);
    geo_pdb_assert((int)$work->fetchColumn() === 1, 'one Work place');
    StepsGeolocationPlaceResolver::assign($pdo, $owner, $visitId, ['name' => 'Biuro 2', 'tag' => 'work']);
    $work->execute([':o' => $owner]);
    geo_pdb_assert((int)$work->fetchColumn() === 1, 'Work stays a singleton');

    StepsGeolocationPlaceResolver::assign($pdo, $owner, $visitId, ['name' => 'Tylko etykieta', 'category' => 'custom']);
    $s = $state();
    geo_pdb_assert($s['display_name'] === 'Tylko etykieta' && $s['detection_method'] === 'manual_label' || $s['place_id'] !== null, 'custom label');

    StepsGeolocationPlaceResolver::retry($pdo, $owner, $visitId);
    $pdo->prepare('UPDATE geo_visits SET place_id = NULL, override_json = \'{}\'::jsonb WHERE id = :id')->execute([':id' => $visitId]);
    StepsGeolocationPlaceResolver::selectCandidate($pdo, $owner, $visitId, $cands[0]['id']);
    StepsGeolocationPlaceResolver::block($pdo, $owner, $visitId);
    geo_pdb_assert($state()['detection_status'] === 'unidentified' && isset(StepsGeolocationPlaceResolver::blocked($pdo, $owner)['node:990000001']), 'block records the OSM object');
    $ranked = StepsGeolocationPlaceRanking::rank(['lat' => $lat, 'lon' => $lon, 'radius_m' => 50, 'duration_s' => 1800], [['lat' => $lat, 'lon' => $lon, 'accuracy_m' => 9.0]], [['osm_type' => 'node', 'osm_id' => 990000001, 'name' => 'QA Cafe', 'category' => 'amenity', 'subcategory' => 'cafe', 'address' => '', 'lat' => $lat, 'lon' => $lon, 'geometry' => null, 'tags' => ['amenity' => 'cafe'], 'fetched_at' => '']], 'prefer_not_to_say', null, null, StepsGeolocationPlaceResolver::blocked($pdo, $owner));
    geo_pdb_assert($ranked['selected'] === null, 'blocked object never reassigned');
    $hasStatus = (int)$pdo->query("SELECT count(*) FROM information_schema.columns WHERE table_name = 'geo_visits' AND column_name IN ('detection_status','osm_id','display_name')")->fetchColumn();
    geo_pdb_assert($hasStatus === 3, 'migration columns');
    echo "ok\n";
} finally {
    $clean();
}
