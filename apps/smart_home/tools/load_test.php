<?php
if (php_sapi_name() !== 'cli') exit(1);
chdir(dirname(__DIR__, 3));
require_once 'lib/app_db.php';
require_once 'apps/smart_home/core/perf.php';
require_once 'vendor/autoload.php';
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

function lt_email(int $n): string { return "load{$n}@loadtest"; }
function lt_dev(int $n, int $k): string { return "ld{$n}_{$k}"; }
function lt_db(): AppDatabase { $db = app_main_db(); $db->enableExceptions(true); return $db; }

function lt_seed(int $users, int $per): void {
    $db = lt_db();
    lt_clean();
    $controls = json_encode([['id' => 'power', 'type' => 'switch', 'label' => 'Power', 'value' => false], ['id' => 'mode', 'type' => 'switch', 'label' => 'Mode', 'value' => false]]);
    $params = json_encode([['id' => 'temp', 'label' => 'Temp', 'value' => '0'], ['id' => 'ws', 'label' => 'Tunnel', 'value' => 'offline']]);
    $db->exec("INSERT INTO smarthome_devices (id,user_email,name,type,status,controls,params,transport,mqtt_topic,device_token) SELECT 'ld'||u||'_'||k,'load'||u||'@loadtest','Load '||u||'/'||k,'generic_sensor','offline','$controls','$params',CASE WHEN k=2 THEN 'mqtt' ELSE 'ws' END,CASE WHEN k=2 THEN 'home/ld'||u||'_'||k ELSE '' END,'ldtok'||u||'_'||k FROM generate_series(0,$users-1) u, generate_series(0,$per-1) k");
    $db->exec("INSERT INTO smarthome_token_index (token,user_email,device_id) SELECT 'ldtok'||u||'_'||k,'load'||u||'@loadtest','ld'||u||'_'||k FROM generate_series(0,$users-1) u, generate_series(0,$per-1) k");
    $db->exec("INSERT INTO smarthome_ws_tokens (token,user_email,device_id,expires_at) SELECT 'ldsess'||u,'load'||u||'@loadtest','ld'||u||'_0',extract(epoch from now())::int+7200 FROM generate_series(0,$users-1) u");
    $db->exec("INSERT INTO smarthome_settings (user_email) SELECT 'load'||u||'@loadtest' FROM generate_series(0,$users-1) u ON CONFLICT DO NOTHING");
    echo "seeded users=$users devices=" . ($users * $per) . "\n";
}
function lt_clean(): void {
    $db = lt_db();
    foreach (["DELETE FROM smarthome_devices WHERE user_email LIKE 'load%@loadtest'", "DELETE FROM smarthome_token_index WHERE user_email LIKE 'load%@loadtest'", "DELETE FROM smarthome_ws_tokens WHERE user_email LIKE 'load%@loadtest'", "DELETE FROM smarthome_settings WHERE user_email LIKE 'load%@loadtest'", "DELETE FROM smarthome_params_history WHERE device_id LIKE 'ld%\\_%'", "DELETE FROM smarthome_monitor_logs WHERE user_email LIKE 'load%@loadtest'", "DELETE FROM smarthome_errors WHERE user_email LIKE 'load%@loadtest'"] as $q) $db->exec($q);
    $r = shp_redis();
    if ($r) {
        $it = null;
        while ($keys = $r->scan($it, shp_key('hot:ld*'), 1000)) { $r->del($keys); }
        for ($i = 0; $i < SHP_HOT_SHARDS; $i++) $r->del(shp_key('hot:dirty:' . $i));
        $r->del(shp_key('hist:buf'), shp_key('cq:all'));
    }
}
function lt_ws_connect(string $token, string $role, int $port = 8080) {
    $s = @stream_socket_client("tcp://127.0.0.1:$port", $e, $es, 5);
    if (!$s) return null;
    stream_set_timeout($s, 5);
    $key = base64_encode(random_bytes(16));
    fwrite($s, "GET /smart-home-ws HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n");
    $h = '';
    while (!str_contains($h, "\r\n\r\n")) { $c = fread($s, 1); if ($c === '' || $c === false) return null; $h .= $c; }
    if (!str_contains($h, ' 101 ')) return null;
    stream_set_blocking($s, false);
    return $s;
}
function lt_frame(string $text, int $op = 1): string {
    $len = strlen($text);
    $hdr = chr(0x80 | $op);
    if ($len < 126) $hdr .= chr(0x80 | $len); elseif ($len < 65536) $hdr .= chr(0x80 | 126) . pack('n', $len); else $hdr .= chr(0x80 | 127) . pack('J', $len);
    $mask = random_bytes(4);
    $masked = '';
    for ($i = 0; $i < $len; $i++) $masked .= $text[$i] ^ $mask[$i % 4];
    return $hdr . $mask . $masked;
}
function lt_parse(string &$buf): array {
    $out = [];
    while (strlen($buf) >= 2) {
        $b1 = ord($buf[1]) & 0x7f;
        $off = 2;
        if ($b1 === 126) { if (strlen($buf) < 4) break; $len = unpack('n', substr($buf, 2, 2))[1]; $off = 4; }
        elseif ($b1 === 127) { if (strlen($buf) < 10) break; $len = unpack('J', substr($buf, 2, 8))[1]; $off = 10; }
        else $len = $b1;
        if (strlen($buf) < $off + $len) break;
        $op = ord($buf[0]) & 0x0f;
        $out[] = [$op, substr($buf, $off, $len)];
        $buf = substr($buf, $off + $len);
    }
    return $out;
}
function lt_worker(int $from, int $to, float $startAt, int $dur, string $outFile): void {
    $conns = [];
    $stats = ['dev_ok' => 0, 'dev_fail' => 0, 'dash_ok' => 0, 'dash_fail' => 0, 'state_sent' => 0, 'dash_updates' => 0, 'cmd_recv' => 0, 'ctl_sent' => 0, 'closed' => 0, 'errors' => 0];
    $lat = [];
    for ($n = $from; $n < $to; $n++) {
        for ($k = 0; $k < 2; $k++) {
            $s = lt_ws_connect('', 'device');
            if (!$s) { $stats['dev_fail']++; continue; }
            fwrite($s, lt_frame(json_encode(['type' => 'auth', 'role' => 'device', 'device_token' => "ldtok{$n}_{$k}"])));
            $conns[(int)$s] = ['s' => $s, 'role' => 'device', 'n' => $n, 'k' => $k, 'buf' => '', 'power' => false, 'temp' => 20];
        }
        $s = lt_ws_connect('', 'dashboard');
        if (!$s) { $stats['dash_fail']++; continue; }
        fwrite($s, lt_frame(json_encode(['type' => 'auth', 'role' => 'dashboard', 'session_token' => "ldsess{$n}"])));
        $conns[(int)$s] = ['s' => $s, 'role' => 'dashboard', 'n' => $n, 'buf' => ''];
    }
    $sentAt = [];
    $nextSend = 0.0;
    $nextCtl = 0.0;
    $nextPing = 0.0;
    $end = $startAt + $dur;
    while (microtime(true) < $end + 2) {
        $read = array_map(fn($c) => $c['s'], $conns);
        if ($read) {
            $w = $e = null;
            if (@stream_select($read, $w, $e, 0, 50000)) {
                foreach ($read as $s) {
                    $id = (int)$s;
                    $d = fread($s, 65536);
                    if ($d === '' || $d === false) { if (feof($s)) { $stats['closed']++; unset($conns[$id]); fclose($s); } continue; }
                    $conns[$id]['buf'] .= $d;
                    foreach (lt_parse($conns[$id]['buf']) as [$op, $msg]) {
                        if ($op === 9) { fwrite($s, lt_frame($msg, 10)); continue; }
                        if ($op !== 1) continue;
                        $m = json_decode($msg, true);
                        $t = $m['type'] ?? '';
                        $c = &$conns[$id];
                        if ($t === 'connected') { if ($c['role'] === 'device') $stats['dev_ok']++; else $stats['dash_ok']++; }
                        elseif ($t === 'error') $stats['errors']++;
                        elseif ($t === 'state_update' && $c['role'] === 'dashboard') $stats['dash_updates']++;
                        elseif ($t === 'command' && $c['role'] === 'device') {
                            $stats['cmd_recv']++;
                            if (($m['control_id'] ?? '') === 'power') $c['power'] = (bool)($m['value'] ?? false);
                            $key = $c['n'];
                            if (($m['control_id'] ?? '') === 'mode' && $c['k'] === 0 && isset($sentAt[$key])) { $lat[] = microtime(true) - $sentAt[$key]; unset($sentAt[$key]); }
                        }
                        unset($c);
                    }
                }
            }
        }
        $now = microtime(true);
        if ($now >= $nextPing) {
            $nextPing = $now + 20.0;
            foreach ($conns as $c) fwrite($c['s'], lt_frame(json_encode(['type' => 'ping'])));
        }
        if ($now < $startAt) continue;
        if ($now >= $nextSend && $now < $end) {
            $nextSend = $now + 1.0;
            foreach ($conns as $id => $c) {
                if ($c['role'] !== 'device') continue;
                if (mt_rand(1, 10) === 1) $conns[$id]['temp'] = mt_rand(150, 300) / 10;
                fwrite($c['s'], lt_frame(json_encode(['type' => 'state', 'telemetry' => ['temp' => $conns[$id]['temp']], 'controls' => ['power' => $c['power']]])));
                $stats['state_sent']++;
            }
        }
        if ($now >= $nextCtl && $now < $end) {
            $nextCtl = $now + 2.0;
            foreach ($conns as $id => $c) {
                if ($c['role'] !== 'dashboard' || mt_rand(1, 10) !== 1) continue;
                $sentAt[$c['n']] = microtime(true);
                fwrite($c['s'], lt_frame(json_encode(['type' => 'control', 'control_id' => 'mode', 'value' => (bool)mt_rand(0, 1)])));
                $stats['ctl_sent']++;
            }
        }
    }
    sort($lat);
    $stats['lat_n'] = count($lat);
    $stats['lat_p50'] = $lat ? $lat[(int)(count($lat) * 0.5)] : null;
    $stats['lat_p95'] = $lat ? $lat[(int)(count($lat) * 0.95)] : null;
    $stats['lat_max'] = $lat ? end($lat) : null;
    $stats['alive_end'] = count($conns);
    file_put_contents($outFile, json_encode($stats));
}
function lt_pgstat(AppDatabase $db): array {
    $r = $db->query("SELECT xact_commit, tup_inserted, tup_updated, tup_deleted, tup_returned, blks_read FROM pg_stat_database WHERE datname = current_database()");
    return array_map('floatval', $r->fetch(PDO::FETCH_ASSOC));
}
function lt_run(int $users, int $dur): void {
    $db = lt_db();
    $r = shp_redis();
    $perWorker = 120;
    $startAt = microtime(true) + max(40, $users / 12);
    $procs = [];
    for ($from = 0; $from < $users; $from += $perWorker) {
        $to = min($users, $from + $perWorker);
        $out = "/tmp/lt_out_$from.json";
        @unlink($out);
        $procs[] = [proc_open([PHP_BINARY, __FILE__, 'worker', (string)$from, (string)$to, (string)$startAt, (string)$dur, $out], [1 => ['file', '/tmp/lt_w.log', 'a'], 2 => ['file', '/tmp/lt_w.log', 'a']], $pipes), $out];
    }
    echo "workers=" . count($procs) . " start in " . round($startAt - microtime(true)) . "s\n";
    $mq = $db->query("SELECT password FROM smarthome_mqtt_auth WHERE username='appdashboard_backend'")->fetch(PDO::FETCH_ASSOC);
    $mqtt = new MqttClient('127.0.0.1', 1883, 'loadtest-pub', MqttClient::MQTT_3_1);
    $mqtt->connect((new ConnectionSettings)->setKeepAliveInterval(60)->setUsername('appdashboard_backend')->setPassword((string)$mq['password']), true);
    while (microtime(true) < $startAt) { $mqtt->loopOnce(microtime(true), false, 0); usleep(100000); }
    $before = lt_pgstat($db);
    $cBefore = $r->hGetAll(shp_key('m:counters')) ?: [];
    $t0 = microtime(true);
    $mqttSent = 0;
    $ctlQueued = 0;
    $peak = [];
    $next = 0.0;
    $nextQ = 0.0;
    $nextSample = 0.0;
    $temps = [];
    while (microtime(true) < $t0 + $dur) {
        $now = microtime(true);
        if ($now >= $next) {
            $next = $now + 1.0;
            for ($n = 0; $n < $users; $n++) {
                if (!isset($temps[$n]) || mt_rand(1, 10) === 1) $temps[$n] = mt_rand(150, 300) / 10;
                $mqtt->publish('home/' . lt_dev($n, 2) . '/state', json_encode(['telemetry' => ['temp' => $temps[$n]], 'controls' => ['power' => false]]), 0);
                $mqttSent++;
                if ($n % 100 === 0) $mqtt->loopOnce(microtime(true), false, 0);
            }
        }
        if ($now >= $nextQ) {
            $nextQ = $now + 0.25;
            for ($i = 0; $i < 5; $i++) {
                $n = mt_rand(0, $users - 1);
                shp_cq_add($db, bin2hex(random_bytes(6)), lt_email($n), lt_dev($n, mt_rand(0, 1)), 'power', json_encode((bool)mt_rand(0, 1)), $now, $now + 120);
                $ctlQueued++;
            }
        }
        if ($now >= $nextSample) {
            $nextSample = $now + 3.0;
            $s = shp_metrics_snapshot($db);
            $peak['queue_len'] = max($peak['queue_len'] ?? 0, $s['queue_len']);
            $peak['hot_dirty'] = max($peak['hot_dirty'] ?? 0, $s['hot_dirty']);
            $peak['hist_buffer'] = max($peak['hist_buffer'] ?? 0, $s['hist_buffer']);
            foreach (($s['ws_procs'] ?? []) as $p => $st) {
                $peak["ws$p.tick_max_ms"] = max($peak["ws$p.tick_max_ms"] ?? 0, (int)$st['tick_max_ms']);
                $peak["ws$p.inbox"] = max($peak["ws$p.inbox"] ?? 0, (int)$st['inbox']);
                $peak["ws$p.clients"] = (int)$st['clients'];
            }
        }
        $mqtt->loopOnce(microtime(true), false, 0);
        usleep(20000);
    }
    $elapsed = microtime(true) - $t0;
    $after = lt_pgstat($db);
    sleep(4);
    $cAfter = $r->hGetAll(shp_key('m:counters')) ?: [];
    $mqtt->disconnect();
    $agg = [];
    foreach ($procs as [$p, $out]) {
        proc_close($p);
        $st = is_file($out) ? json_decode(file_get_contents($out), true) : null;
        if (!$st) { $agg['workers_failed'] = ($agg['workers_failed'] ?? 0) + 1; continue; }
        foreach ($st as $k => $v) {
            if (str_starts_with($k, 'lat_')) { $agg[$k] = ($k === 'lat_n') ? ($agg[$k] ?? 0) + $v : max($agg[$k] ?? 0, (float)$v); continue; }
            $agg[$k] = ($agg[$k] ?? 0) + $v;
        }
    }
    echo "== window " . round($elapsed, 1) . "s ==\n";
    echo "client: " . json_encode($agg) . "\n";
    echo "mqtt published: $mqttSent (" . round($mqttSent / $elapsed) . "/s), control queued: $ctlQueued\n";
    $delta = fn($k) => (int)($cAfter[$k] ?? 0) - (int)($cBefore[$k] ?? 0);
    echo "redis counters delta: mqtt_msgs=" . $delta('mqtt_msgs') . " hot_flushed_rows=" . $delta('hot_flushed_rows') . " hist_flushed_rows=" . $delta('hist_flushed_rows') . " control_delivered=" . $delta('control_delivered') . " control_timeouts=" . $delta('control_timeouts') . " ws_inbox_pushed=" . $delta('ws_inbox_pushed') . "\n";
    $wsMsgs = (int)($agg['state_sent'] ?? 0);
    $totalMsgs = $wsMsgs + $mqttSent;
    echo "incoming msgs/s: " . round($totalMsgs / $elapsed) . " (ws " . round($wsMsgs / $elapsed) . ", mqtt " . round($mqttSent / $elapsed) . ")\n";
    foreach (['xact_commit', 'tup_inserted', 'tup_updated'] as $k) echo "pg $k/s: " . round(($after[$k] - $before[$k]) / $elapsed, 1) . "\n";
    echo "peaks: " . json_encode($peak) . "\n";
    $q = $db->query("SELECT status, count(*) c FROM smarthome_devices WHERE user_email LIKE 'load%@loadtest' GROUP BY status");
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) echo "devices {$row['status']}: {$row['c']}\n";
}

$cmd = $argv[1] ?? '';
if ($cmd === 'seed') lt_seed((int)($argv[2] ?? 100), (int)($argv[3] ?? 3));
elseif ($cmd === 'run') lt_run((int)($argv[2] ?? 100), (int)($argv[3] ?? 30));
elseif ($cmd === 'worker') lt_worker((int)$argv[2], (int)$argv[3], (float)$argv[4], (int)$argv[5], $argv[6]);
elseif ($cmd === 'clean') { lt_clean(); echo "cleaned\n"; }
else echo "usage: seed U D | run U SEC | clean\n";
