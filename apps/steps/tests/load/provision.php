<?php
require_once dirname(__DIR__, 4) . '/lib/app_db.php';
require_once dirname(__DIR__, 2) . '/core/geolocation_schema.php';
require_once dirname(__DIR__, 2) . '/core/geolocation_api.php';
require_once dirname(__DIR__, 2) . '/core/geolocation_domain.php';
$mode = $argv[1] ?? 'provision';
$users = max(1, (int)($argv[2] ?? 10));
$perUser = max(0, (int)($argv[3] ?? 5000));
$db = app_main_db();
$pdo = $db->getPdo();
$like = 'qa-ramp+%@example.test';
if ($mode === 'cleanup') {
    foreach (['geo_location_points', 'geo_devices', 'geo_api_keys'] as $t) {
        $pdo->prepare("DELETE FROM $t WHERE user_email LIKE :l")->execute([':l' => $like]);
    }
    echo json_encode(['cleaned' => true]) . "\n";
    exit(0);
}
steps_geolocation_migrate($db);
$out = [];
for ($i = 0; $i < $users; $i++) {
    $owner = 'qa-ramp+' . $i . '@example.test';
    $device = StepsGeolocationDomain::isUuid($d = substr(md5($owner), 0, 8) . '-' . substr(md5($owner), 8, 4) . '-4' . substr(md5($owner), 13, 3) . '-a' . substr(md5($owner), 17, 3) . '-' . substr(md5($owner), 20, 12)) ? $d : '';
    $pdo->prepare("INSERT INTO geo_devices(id,user_email,name,platform,source_type,status) VALUES(:id,:o,'QA ramp','api','api','active') ON CONFLICT (user_email,id) DO UPDATE SET status='active'")->execute([':id' => $device, ':o' => $owner]);
    $have = (int)$pdo->query('SELECT COUNT(*) FROM geo_location_points WHERE user_email=' . $pdo->quote($owner))->fetchColumn();
    for ($off = 0, $need = max(0, $perUser - $have); $off < $need; $off += 5000) {
        $pdo->prepare("INSERT INTO geo_location_points(id,user_email,device_id,client_event_id,source_type,source_at,position,accuracy_m,quality_state)
            SELECT gen_random_uuid(), :o, :d, gen_random_uuid(), 'api', now() - interval '30 days' + ((:a + g) % 30000) * interval '1 minute',
            ST_SetSRID(ST_MakePoint(30.45 + ((:b + g) % 1000) * 0.0009, 50.40 + ((:c + g) % 1000) * 0.0006), 4326), 5 + (g % 20), 'accepted'
            FROM generate_series(1, :n) g")->execute([':o' => $owner, ':d' => $device, ':a' => $off, ':b' => $off, ':c' => $off, ':n' => min(5000, $need - $off)]);
    }
    $pdo->prepare("DELETE FROM geo_api_keys WHERE user_email=:o")->execute([':o' => $owner]);
    $key = StepsGeolocationApi::issueKey($pdo, $owner, 'ramp', ['read:points', 'read:trips', 'read:places', 'read:statistics', 'write:points']);
    $out[] = ['key' => $key['secret'], 'device' => $device];
}
echo json_encode($out) . "\n";
