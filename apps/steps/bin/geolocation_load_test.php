#!/usr/bin/env php
<?php
/**
 * Repeatable geolocation read load test (task 31/32).
 *
 * Seeds a deterministic history for one API key owner, then drives the v1
 * points endpoint through PHP curl_multi (no external load tool required).
 *
 *   php apps/steps/bin/geolocation_load_test.php --seed=50000 --concurrency=32 --duration=30
 *   php apps/steps/bin/geolocation_load_test.php --owner=qa-load@example.test --concurrency=64 --duration=60
 *
 * Results are printed as one JSON line. Each request uses a slightly different
 * viewport, so the 20 s map/read cache cannot inflate throughput.
 */
require_once dirname(__DIR__, 3) . '/lib/app_db.php';
require_once dirname(__DIR__) . '/core/geolocation_schema.php';
require_once dirname(__DIR__) . '/core/geolocation_api.php';
require_once dirname(__DIR__) . '/core/geolocation_domain.php';

$opts = getopt('', ['seed::', 'users::', 'concurrency::', 'duration::', 'owner::', 'base::', 'limit::', 'bboxes::']);
$seed = max(0, (int)($opts['seed'] ?? 0));
$concurrency = max(1, min(256, (int)($opts['concurrency'] ?? 16)));
$duration = max(1, min(600, (int)($opts['duration'] ?? 15)));
$users = max(1, min(64, (int)($opts['users'] ?? 1)));
$ownerBase = StepsGeolocationDomain::normalizeOwner((string)($opts['owner'] ?? 'qa-load@example.test'));
[$ownerLocal, $ownerDomain] = array_pad(explode('@', $ownerBase, 2), 2, 'example.test');
$owners = [];
for ($i = 0; $i < $users; $i++) {
    $owners[] = $users === 1 ? $ownerBase : $ownerLocal . '+load' . $i . '@' . $ownerDomain;
}
$base = rtrim((string)($opts['base'] ?? 'http://127.0.0.1:8080'), '/');
$limit = max(1, min(StepsGeolocationApi::MAX_LIMIT, (int)($opts['limit'] ?? 50)));
$bboxes = max(0, min(1000, (int)($opts['bboxes'] ?? 0)));
$db = app_main_db();
$pdo = $db->getPdo();
steps_geolocation_migrate($db);

$perOwner = max(1, (int)ceil($seed / $users));
foreach ($owners as $owner) {
    $hex = md5($owner);
    $device = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
    $pdo->prepare("INSERT INTO geo_devices(id,user_email,name,platform,source_type,status) VALUES(:id,:owner,'QA load device','api','api','active') ON CONFLICT (user_email,id) DO UPDATE SET status = 'active'")
        ->execute([':id' => $device, ':owner' => $owner]);
    if ($seed <= 0) continue;
    $current = (int)$pdo->query('SELECT COUNT(*) FROM geo_location_points WHERE user_email = ' . $pdo->quote($owner))->fetchColumn();
    $need = max(0, $perOwner - $current);
    for ($offset = 0; $offset < $need; $offset += 5000) {
        $count = min(5000, $need - $offset);
        $sql = "INSERT INTO geo_location_points(id,user_email,device_id,client_event_id,source_type,source_at,position,accuracy_m,quality_state)
                SELECT gen_random_uuid(), :owner, :device, gen_random_uuid(), 'api',
                       now() - interval '30 days' + ((:offset + g) % 30000) * interval '1 minute',
                       ST_SetSRID(ST_MakePoint(30.45 + ((:offset2 + g) % 1000) * 0.0009, 50.40 + ((:offset3 + g) % 1000) * 0.0006), 4326),
                       5 + (g % 20), 'accepted'
                  FROM generate_series(1, :count) g";
        $pdo->prepare($sql)->execute([
            ':owner' => $owner, ':device' => $device, ':offset' => $offset,
            ':offset2' => $offset, ':offset3' => $offset, ':count' => $count,
        ]);
    }
}
$issued = [];
$keys = [];
foreach ($owners as $owner) {
    $pdo->prepare("DELETE FROM geo_api_keys WHERE user_email = :owner AND name = 'load-test'")->execute([':owner' => $owner]);
    $key = StepsGeolocationApi::issueKey($pdo, $owner, 'load-test', ['read:points']);
    $issued[] = $key;
    $keys[] = (string)$key['secret'];
}
$from = gmdate('c', time() - 30 * 86400);
$to = gmdate('c');
$urlBase = $base . '/apps/steps/api/v1.php?path=points&limit=' . $limit . '&from=' . rawurlencode($from) . '&to=' . rawurlencode($to) . '&bbox=';

$mh = curl_multi_init();
$active = [];
$latencies = [];
$errors = [];
$requests = 0;
$handles = 0;
$runStarted = microtime(true);
$deadline = $runStarted + $duration;

$newHandle = function () use ($urlBase, $keys, $bboxes, &$handles): CurlHandle {
    $i = $bboxes > 0 ? random_int(0, $bboxes - 1) : random_int(0, 700);
    $bbox = implode(',', [30.45 + $i * 0.0009, 50.40 + $i * 0.0006, 30.55 + $i * 0.0009, 50.50 + $i * 0.0006]);
    $ch = curl_init($urlBase . rawurlencode($bbox));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $keys[$handles++ % count($keys)], 'Accept: application/json'],
    ]);
    return $ch;
};
$finish = function (CurlHandle $ch) use (&$active, &$latencies, &$errors, $mh): void {
    $id = (int)$ch;
    $body = (string)curl_multi_getcontent($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $started = $active[$id]['start'] ?? microtime(true);
    $latencies[] = (microtime(true) - $started) * 1000;
    $decoded = json_decode($body, true);
    if ($code !== 200 || !is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
        $error = is_array($decoded) && isset($decoded['error']) ? (string)$decoded['error'] : 'http_' . $code;
        $errors[$error] = ($errors[$error] ?? 0) + 1;
    }
    unset($active[$id]);
    curl_multi_remove_handle($mh, $ch);
};

for ($i = 0; $i < $concurrency; $i++) {
    $ch = $newHandle();
    curl_multi_add_handle($mh, $ch);
    $active[(int)$ch] = ['handle' => $ch, 'start' => microtime(true)];
    $requests++;
}
while ($active !== []) {
    do {
        $status = curl_multi_exec($mh, $running);
    } while ($status === CURLM_CALL_MULTI_PERFORM);
    while ($info = curl_multi_info_read($mh)) {
        $finish($info['handle']);
        if (microtime(true) < $deadline) {
            $ch = $newHandle();
            curl_multi_add_handle($mh, $ch);
            $active[(int)$ch] = ['handle' => $ch, 'start' => microtime(true)];
            $requests++;
        }
    }
    if ($active !== [] && $running > 0) curl_multi_select($mh, 0.05);
}
curl_multi_close($mh);

sort($latencies);
$pct = static function (array $values, float $p): float {
    if ($values === []) return 0;
    $idx = (int)min(count($values) - 1, max(0, ceil($p / 100 * count($values)) - 1));
    return round($values[$idx], 2);
};
$elapsed = max(0.001, microtime(true) - $runStarted);
foreach ($issued as $keyRow) {
    $pdo->prepare('UPDATE geo_api_keys SET revoked_at = now() WHERE id = :id')->execute([':id' => $keyRow['id']]);
}
$ownerList = implode(',', array_map([$pdo, 'quote'], $owners));
echo json_encode([
    'users' => $users,
    'seeded_points' => (int)$pdo->query('SELECT COUNT(*) FROM geo_location_points WHERE user_email IN (' . $ownerList . ')')->fetchColumn(),
    'concurrency' => $concurrency,
    'elapsed_s' => round($elapsed, 2),
    'requests' => count($latencies),
    'rps' => round(count($latencies) / $elapsed, 2),
    'p50_ms' => $pct($latencies, 50),
    'p95_ms' => $pct($latencies, 95),
    'p99_ms' => $pct($latencies, 99),
    'max_ms' => $pct($latencies, 100),
    'errors' => $errors,
], JSON_UNESCAPED_SLASHES) . "\n";
