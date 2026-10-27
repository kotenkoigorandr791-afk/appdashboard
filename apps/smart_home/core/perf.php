<?php
require_once dirname(__DIR__, 3) . '/lib/redis.php';
const SHP_SCHEMA_VERSION = 'v3';
function shp_redis(): ?Redis {
    try { return app_redis(); } catch (Throwable $e) { return null; }
}
function shp_key(string $k): string {
    return app_redis_key('smarthome:' . $k);
}
function shp_throttle(string $key, int $ttl): bool {
    $r = shp_redis();
    if ($r) {
        try { return (bool)$r->set(shp_key('th:' . $key), '1', ['nx', 'ex' => max(1, $ttl)]); } catch (Throwable $e) {}
    }
    static $local = [];
    $now = microtime(true);
    if (isset($local[$key]) && $local[$key] > $now) return false;
    $local[$key] = $now + $ttl;
    return true;
}
function shp_lock(string $name, int $ttl): ?string {
    $r = shp_redis();
    if (!$r) return null;
    try {
        $tok = bin2hex(random_bytes(6));
        return $r->set(shp_key('lock:' . $name), $tok, ['nx', 'ex' => $ttl]) ? $tok : null;
    } catch (Throwable $e) { return null; }
}
function shp_unlock(string $name, ?string $tok): void {
    $r = shp_redis();
    if (!$r || $tok === null) return;
    try { if ($r->get(shp_key('lock:' . $name)) === $tok) $r->del(shp_key('lock:' . $name)); } catch (Throwable $e) {}
}
function shp_cache_get(string $key): ?string {
    $r = shp_redis();
    if (!$r) return null;
    try { $v = $r->get(shp_key('c:' . $key)); return is_string($v) ? $v : null; } catch (Throwable $e) { return null; }
}
function shp_cache_set(string $key, string $value, int $ttl): void {
    $r = shp_redis();
    if (!$r) return;
    try { $r->setex(shp_key('c:' . $key), $ttl, $value); } catch (Throwable $e) {}
}
function shp_cache_del(string ...$keys): void {
    $r = shp_redis();
    if (!$r) return;
    try { foreach ($keys as $k) $r->del(shp_key('c:' . $k)); } catch (Throwable $e) {}
}
function shp_user_hash(string $email): string {
    return md5(strtolower($email));
}
function shp_bust_user(string $email): void {
    $h = shp_user_hash($email);
    shp_cache_del('state:' . $h, 'stats:' . $h . ':24');
}
function shp_flag_set(string $name, int $ttl = 60): void {
    $r = shp_redis();
    if (!$r) return;
    try { $r->setex(shp_key('f:' . $name), $ttl, '1'); } catch (Throwable $e) {}
}
function shp_flag_take(string $name): bool {
    $r = shp_redis();
    if (!$r) return false;
    try { return (int)$r->del(shp_key('f:' . $name)) > 0; } catch (Throwable $e) { return false; }
}
function shp_rate_ok(string $bucket, int $limit, int $window = 60): bool {
    $r = shp_redis();
    if (!$r) return true;
    try {
        $k = shp_key('rl:' . $bucket . ':' . intdiv(time(), $window));
        $n = (int)$r->incr($k);
        if ($n === 1) $r->expire($k, $window + 1);
        return $n <= $limit;
    } catch (Throwable $e) { return true; }
}
function shp_history_should_write(string $deviceId, string $paramId, string $value, int $minInterval = 300): bool {
    $r = shp_redis();
    if (!$r) return true;
    try {
        $k = shp_key('h:' . md5($deviceId . "\0" . $paramId));
        $prev = $r->get($k);
        $sig = md5($value);
        if ($prev === $sig) return false;
        $tk = $k . ':t';
        if (!$r->set($tk, '1', ['nx', 'ex' => 20])) return false;
        $r->setex($k, $minInterval, $sig);
        return true;
    } catch (Throwable $e) { return true; }
}
function shp_event_dedupe(string $kind, string ...$parts): bool {
    return shp_throttle('ev:' . $kind . ':' . md5(implode("\0", $parts)), 60);
}
function shp_clip(string $v, int $max = 1000): string {
    return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
}
function shp_schema_flag(): string {
    return sys_get_temp_dir() . '/smarthome_schema_' . SHP_SCHEMA_VERSION;
}
function shp_schema_ready(): bool {
    return is_file(shp_schema_flag());
}
function shp_schema_mark(): void {
    @file_put_contents(shp_schema_flag(), (string)time());
}
function shp_migrate(AppDatabase $db): void {
    $cols = ['smarthome_devices' => 'updated_at', 'smarthome_monitor_logs' => 'created_at', 'smarthome_errors' => 'created_at', 'smarthome_params_history' => 'recorded_at'];
    foreach ($cols as $t => $c) {
        $res = $db->query("SELECT data_type AS dt FROM information_schema.columns WHERE table_schema='public' AND table_name='" . $t . "' AND column_name='" . $c . "'");
        $row = $res->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['dt'] ?? '') !== 'text') continue;
        $db->exec("ALTER TABLE $t ALTER COLUMN $c DROP DEFAULT");
        $db->exec("ALTER TABLE $t ALTER COLUMN $c TYPE timestamptz USING COALESCE(NULLIF($c,'')::timestamptz, CURRENT_TIMESTAMP)");
        $db->exec("ALTER TABLE $t ALTER COLUMN $c SET DEFAULT CURRENT_TIMESTAMP");
    }
    foreach ([
        'sh_dev_user' => 'smarthome_devices(user_email)',
        'sh_dev_group' => 'smarthome_devices(group_id)',
        'sh_dev_status_upd' => 'smarthome_devices(status, updated_at)',
        'sh_ml_user_ts' => 'smarthome_monitor_logs(user_email, created_at DESC)',
        'sh_ml_ts' => 'smarthome_monitor_logs(created_at)',
        'sh_err_user_ts' => 'smarthome_errors(user_email, created_at DESC)',
        'sh_err_ts' => 'smarthome_errors(created_at)',
        'sh_ph_dev_ts' => 'smarthome_params_history(device_id, recorded_at DESC)',
        'sh_ph_ts' => 'smarthome_params_history(recorded_at)',
        'sh_cq_dev' => 'smarthome_control_queue(device_id, expires_at)',
        'sh_cq_exp' => 'smarthome_control_queue(expires_at)',
        'sh_cr_upd' => 'smarthome_control_results(updated_at)',
        'sh_ti_dev' => 'smarthome_token_index(device_id)',
        'sh_gm_member' => 'smarthome_group_members(member_email)',
        'sh_grp_owner' => 'smarthome_groups(user_email)',
        'sh_wt_user' => 'smarthome_ws_tokens(user_email)',
        'sh_wt_exp' => 'smarthome_ws_tokens(expires_at)',
    ] as $name => $def) {
        $db->exec("CREATE INDEX IF NOT EXISTS $name ON $def");
    }
}
function shp_purge_if_due(AppDatabase $db): void {
    if (!shp_throttle('purge', 30)) return;
    try {
        $batch = 20000;
        foreach ([
            ['smarthome_monitor_logs', 'created_at', '30 days'],
            ['smarthome_errors', 'created_at', '30 days'],
            ['smarthome_params_history', 'recorded_at', '14 days'],
        ] as [$t, $c, $age]) {
            $db->exec("DELETE FROM $t WHERE id IN (SELECT id FROM $t WHERE $c < CURRENT_TIMESTAMP - interval '$age' ORDER BY $c LIMIT $batch)");
        }
        $db->exec('DELETE FROM smarthome_control_results WHERE updated_at < ' . (microtime(true) - 3600));
        $db->exec('DELETE FROM smarthome_control_queue WHERE expires_at < ' . (microtime(true) - 60));
        $db->exec('DELETE FROM smarthome_ws_tokens WHERE expires_at < ' . time());
    } catch (Throwable $e) {
        error_log('shp_purge: ' . $e->getMessage());
    }
}
function shp_metric(string $name, int $by = 1): void {
    $r = shp_redis();
    if (!$r) return;
    try { $r->hIncrBy(shp_key('m:counters'), $name, $by); } catch (Throwable $e) {}
}
const SHP_HOT_SHARDS = 4;
function shp_hot_dirty_key(string $id): string {
    return shp_key('hot:dirty:' . (crc32($id) % SHP_HOT_SHARDS));
}
function shp_hot_key(string $id): string {
    return shp_key('hot:' . $id);
}
function shp_hot_get(string $id, bool $requireFresh = true): ?array {
    $r = shp_redis();
    if (!$r) return null;
    try { $h = $r->hGetAll(shp_hot_key($id)); } catch (Throwable $e) { return null; }
    if (!$h || empty($h['ts'])) return null;
    if ($requireFresh && (microtime(true) - (float)$h['ts']) > 180) return null;
    return [
        'status' => (string)($h['status'] ?? 'unknown'),
        'local_url' => (string)($h['local_url'] ?? ''),
        'controls' => json_decode((string)($h['controls'] ?? '[]'), true) ?: [],
        'params' => json_decode((string)($h['params'] ?? '[]'), true) ?: [],
        'ts' => (float)$h['ts'],
    ];
}
function shp_hot_overlay(array $row): array {
    $id = (string)($row['id'] ?? '');
    if ($id === '') return $row;
    $hot = shp_hot_get($id);
    if (!$hot) return $row;
    $row['status'] = $hot['status'];
    if ($hot['local_url'] !== '') $row['local_url'] = $hot['local_url'];
    $row['controls'] = json_encode($hot['controls'], JSON_UNESCAPED_UNICODE);
    $row['params'] = json_encode($hot['params'], JSON_UNESCAPED_UNICODE);
    $row['updated_at'] = gmdate('Y-m-d H:i:sP', (int)$hot['ts']);
    return $row;
}
function shp_hot_put(string $id, array $state): ?bool {
    $r = shp_redis();
    if (!$r || $id === '') return null;
    try {
        $c = json_encode($state['controls'] ?? [], JSON_UNESCAPED_UNICODE);
        $p = json_encode($state['params'] ?? [], JSON_UNESCAPED_UNICODE);
        $st = (string)($state['status'] ?? 'unknown');
        $lu = (string)($state['local_url'] ?? '');
        $sig = md5($c . "\0" . $p . "\0" . $st . "\0" . $lu);
        $k = shp_hot_key($id);
        if ($r->exists(shp_key('hotblk:' . $id))) return false;
        $prev = $r->hMGet($k, ['sig', 'dirty_at']);
        $now = microtime(true);
        $dirty = ($prev['sig'] ?? false) !== $sig || ($now - (float)($prev['dirty_at'] ?: 0)) >= 30.0;
        $f = ['controls' => $c, 'params' => $p, 'status' => $st, 'local_url' => $lu, 'sig' => $sig, 'ts' => $now];
        if ($dirty) $f['dirty_at'] = $now;
        $pipe = $r->multi(Redis::PIPELINE);
        $pipe->hMSet($k, $f);
        $pipe->expire($k, 900);
        if ($dirty) $pipe->sAdd(shp_hot_dirty_key($id), $id);
        $pipe->exec();
        return $dirty;
    } catch (Throwable $e) { return null; }
}
function shp_hot_drop(string ...$ids): void {
    $r = shp_redis();
    if (!$r) return;
    try { foreach ($ids as $id) { $r->del(shp_hot_key($id)); $r->sRem(shp_hot_dirty_key($id), $id); $r->setex(shp_key('hotblk:' . $id), 2, '1'); } } catch (Throwable $e) {}
}
function shp_flush_hot(AppDatabase $db, int $max = 500): int {
    $r = shp_redis();
    if (!$r) return 0;
    $done = 0;
    $shards = range(0, SHP_HOT_SHARDS - 1);
    shuffle($shards);
    foreach ($shards as $sh) {
        if (!shp_throttle('flush_hot_iv:' . $sh, 1)) continue;
        $lock = shp_lock('flush_hot:' . $sh, 20);
        if ($lock === null) continue;
        $ids = [];
        try {
            $dk = shp_key('hot:dirty:' . $sh);
            $ids = $r->sPop($dk, $max);
            if (!is_array($ids) || !$ids) continue;
            $rows = [];
            foreach ($ids as $id) {
                $h = $r->hGetAll(shp_hot_key((string)$id));
                if ($h && !empty($h['ts'])) $rows[(string)$id] = [(string)$id, (string)$h['status'], (string)$h['local_url'], (string)$h['controls'], (string)$h['params'], (string)$h['ts']];
            }
            foreach (array_chunk(array_values($rows), 100) as $chunk) {
                $vals = [];
                foreach ($chunk as $i => $_) $vals[] = "(:i$i,:s$i,:u$i,:c$i,:p$i,CAST(:t$i AS double precision))";
                $stmt = $db->prepare("UPDATE smarthome_devices d SET status=v.status, local_url=CASE WHEN v.local_url='' THEN d.local_url ELSE v.local_url END, controls=v.controls, params=v.params, updated_at=to_timestamp(v.ts) FROM (VALUES " . implode(',', $vals) . ") AS v(id,status,local_url,controls,params,ts) WHERE d.id=v.id");
                foreach ($chunk as $i => $row) {
                    foreach (['i', 's', 'u', 'c', 'p', 't'] as $n => $pfx) $stmt->bindValue(":$pfx$i", $row[$n], PDO::PARAM_STR);
                }
                $stmt->execute();
                foreach ($chunk as $row) unset($rows[$row[0]]);
                $done += count($chunk);
            }
            shp_metric('hot_flushed_rows', $done);
        } catch (Throwable $e) {
            error_log('shp_flush_hot: ' . $e->getMessage());
            try {
                $left = isset($rows) ? array_keys($rows) : $ids;
                if ($left) $r->sAdd(shp_key('hot:dirty:' . $sh), ...$left);
            } catch (Throwable $e2) {}
        } finally {
            shp_unlock('flush_hot:' . $sh, $lock);
            unset($rows);
        }
    }
    return $done;
}
function shp_hist_push(string $deviceId, string $paramId, string $label, string $value): bool {
    $r = shp_redis();
    if (!$r) return false;
    try {
        $r->rPush(shp_key('hist:buf'), json_encode([$deviceId, $paramId, $label, $value, microtime(true)], JSON_UNESCAPED_UNICODE));
        if (random_int(1, 500) === 1) $r->lTrim(shp_key('hist:buf'), -200000, -1);
        return true;
    } catch (Throwable $e) { return false; }
}
function shp_flush_history(AppDatabase $db, int $max = 2000): int {
    $r = shp_redis();
    if (!$r || !shp_throttle('flush_hist_iv', 1)) return 0;
    $lock = shp_lock('flush_hist', 20);
    if ($lock === null) return 0;
    $done = 0;
    $items = [];
    try {
        $k = shp_key('hist:buf');
        $items = $r->lRange($k, 0, $max - 1);
        if (!$items) return 0;
        $r->lTrim($k, count($items), -1);
        $pos = 0;
        foreach (array_chunk($items, 500) as $chunk) {
            $rows = [];
            foreach ($chunk as $raw) {
                $a = json_decode((string)$raw, true);
                if (is_array($a) && count($a) === 5) $rows[] = $a;
            }
            if ($rows) {
                $vals = [];
                foreach ($rows as $i => $_) $vals[] = "(:d$i,:p$i,:l$i,:v$i,to_timestamp(CAST(:t$i AS double precision)))";
                $stmt = $db->prepare('INSERT INTO smarthome_params_history (device_id,param_id,param_label,param_value,recorded_at) VALUES ' . implode(',', $vals));
                foreach ($rows as $i => $a) {
                    $stmt->bindValue(":d$i", (string)$a[0], PDO::PARAM_STR);
                    $stmt->bindValue(":p$i", (string)$a[1], PDO::PARAM_STR);
                    $stmt->bindValue(":l$i", (string)$a[2], PDO::PARAM_STR);
                    $stmt->bindValue(":v$i", (string)$a[3], PDO::PARAM_STR);
                    $stmt->bindValue(":t$i", (string)$a[4], PDO::PARAM_STR);
                }
                $stmt->execute();
                $done += count($rows);
            }
            $pos += count($chunk);
        }
        shp_metric('hist_flushed_rows', $done);
    } catch (Throwable $e) {
        error_log('shp_flush_history: ' . $e->getMessage());
        try {
            $rest = array_slice($items, $pos ?? 0);
            if ($rest) $r->lPush(shp_key('hist:buf'), ...array_reverse($rest));
        } catch (Throwable $e2) {}
    } finally {
        shp_unlock('flush_hist', $lock);
    }
    return $done;
}
function shp_flush_all(AppDatabase $db): void {
    shp_flush_hot($db);
    shp_flush_history($db);
}
function shp_cq_add(AppDatabase $db, string $id, string $user, string $device, string $control, string $valueJson, float $created, float $expires): void {
    $r = shp_redis();
    if ($r) {
        try {
            $item = ['id' => $id, 'user_email' => $user, 'device_id' => $device, 'control_id' => $control, 'value_json' => $valueJson, 'created_at' => $created, 'expires_at' => $expires];
            $pipe = $r->multi(Redis::PIPELINE);
            $pipe->setex(shp_key('cq:i:' . $id), 240, json_encode($item, JSON_UNESCAPED_UNICODE));
            $pipe->zAdd(shp_key('cq:all'), $expires, $id);
            $pipe->zAdd(shp_key('cq:d:' . $device), $expires, $id);
            $pipe->expire(shp_key('cq:d:' . $device), 300);
            $pipe->exec();
            return;
        } catch (Throwable $e) {}
    }
    $stmt = $db->prepare('INSERT INTO smarthome_control_queue (id, user_email, device_id, control_id, value_json, created_at, expires_at) VALUES (:id, :em, :did, :cid, :val, :ca, :exp)');
    foreach ([':id' => $id, ':em' => $user, ':did' => $device, ':cid' => $control, ':val' => $valueJson, ':ca' => $created, ':exp' => $expires] as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->execute();
}
function shp_cq_list(AppDatabase $db, float $now, int $limit = 200): array {
    $r = shp_redis();
    if ($r) {
        try {
            $r->zRemRangeByScore(shp_key('cq:all'), '-inf', (string)($now - 60));
            $ids = $r->zRangeByScore(shp_key('cq:all'), (string)($now - 30), '+inf', ['limit' => [0, $limit]]);
            if (!$ids) return [];
            $raw = $r->mGet(array_map(fn($i) => shp_key('cq:i:' . $i), $ids));
            $rows = [];
            foreach ($ids as $n => $id) {
                $a = isset($raw[$n]) && is_string($raw[$n]) ? json_decode($raw[$n], true) : null;
                if (is_array($a)) $rows[] = $a; else $r->zRem(shp_key('cq:all'), $id);
            }
            return $rows;
        } catch (Throwable $e) {}
    }
    $res = $db->query('SELECT * FROM smarthome_control_queue WHERE expires_at > ' . ($now - 30) . ' ORDER BY created_at LIMIT ' . $limit);
    $rows = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) $rows[] = $row;
    return $rows;
}
function shp_cq_del(AppDatabase $db, string $id): void {
    $r = shp_redis();
    if ($r) {
        try {
            $raw = $r->get(shp_key('cq:i:' . $id));
            $a = is_string($raw) ? json_decode($raw, true) : null;
            $pipe = $r->multi(Redis::PIPELINE);
            $pipe->del(shp_key('cq:i:' . $id));
            $pipe->zRem(shp_key('cq:all'), $id);
            if (is_array($a)) $pipe->zRem(shp_key('cq:d:' . ($a['device_id'] ?? '')), $id);
            $pipe->exec();
            return;
        } catch (Throwable $e) {}
    }
    $d = $db->prepare('DELETE FROM smarthome_control_queue WHERE id = :id');
    $d->bindValue(':id', $id, PDO::PARAM_STR);
    $d->execute();
}
function shp_cq_pending(AppDatabase $db, string $deviceId, float $now): array {
    $r = shp_redis();
    if ($r) {
        try {
            $ids = $r->zRangeByScore(shp_key('cq:d:' . $deviceId), (string)$now, '+inf');
            if (!$ids) return [];
            $raw = $r->mGet(array_map(fn($i) => shp_key('cq:i:' . $i), $ids));
            $out = [];
            foreach ($raw as $s) {
                $a = is_string($s) ? json_decode($s, true) : null;
                if (is_array($a)) $out[] = (string)($a['control_id'] ?? '');
            }
            return $out;
        } catch (Throwable $e) {}
    }
    $stmt = $db->prepare('SELECT control_id FROM smarthome_control_queue WHERE device_id = :did AND expires_at > :now');
    $stmt->bindValue(':did', $deviceId, PDO::PARAM_STR);
    $stmt->bindValue(':now', $now, PDO::PARAM_STR);
    $res = $stmt->execute();
    $out = [];
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) $out[] = (string)($row['control_id'] ?? '');
    return $out;
}
function shp_cq_clear_device(AppDatabase $db, string $deviceId): void {
    $r = shp_redis();
    if ($r) {
        try {
            $ids = $r->zRange(shp_key('cq:d:' . $deviceId), 0, -1);
            $pipe = $r->multi(Redis::PIPELINE);
            foreach ($ids as $id) { $pipe->del(shp_key('cq:i:' . $id)); $pipe->zRem(shp_key('cq:all'), $id); }
            $pipe->del(shp_key('cq:d:' . $deviceId));
            $pipe->exec();
            return;
        } catch (Throwable $e) {}
    }
    $stmt = $db->prepare('DELETE FROM smarthome_control_queue WHERE device_id = :did');
    $stmt->bindValue(':did', $deviceId, PDO::PARAM_STR);
    $stmt->execute();
}
function shp_cr_put(string $id, array $result): void {
    $r = shp_redis();
    if (!$r) return;
    try { $r->setex(shp_key('cr:' . $id), 3600, json_encode($result, JSON_UNESCAPED_UNICODE)); } catch (Throwable $e) {}
}
function shp_ws_ports(): array {
    $raw = (string)(getenv('SMART_HOME_WS_PORTS') ?: getenv('SMART_HOME_WS_PORT') ?: '8766');
    $ports = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
    return $ports ?: [8766];
}
function shp_ws_dev_key(string $user, string $device): string {
    return shp_key('ws:dev:' . md5($user . "\0" . $device));
}
function shp_ws_boot(): string {
    static $boot = null;
    return $boot ??= bin2hex(random_bytes(4));
}
function shp_ws_dev_val(int $port): string {
    return $port . '|' . shp_ws_boot();
}
function shp_ws_dev_set(string $user, string $device, int $port): ?int {
    $r = shp_redis();
    if (!$r) return null;
    try {
        $prev = $r->getSet(shp_ws_dev_key($user, $device), shp_ws_dev_val($port));
        $r->expire(shp_ws_dev_key($user, $device), 40);
        return $prev === false ? null : (int)$prev;
    } catch (Throwable $e) { return null; }
}
function shp_ws_dev_get(string $user, string $device): ?int {
    $r = shp_redis();
    if (!$r) return null;
    try { $v = $r->get(shp_ws_dev_key($user, $device)); return $v === false ? null : (int)$v; } catch (Throwable $e) { return null; }
}
function shp_ws_dev_del(string $user, string $device, int $port): void {
    $r = shp_redis();
    if (!$r) return;
    try { if ((string)$r->get(shp_ws_dev_key($user, $device)) === shp_ws_dev_val($port)) $r->del(shp_ws_dev_key($user, $device)); } catch (Throwable $e) {}
}
function shp_ws_dev_touch(array $pairs, int $port): void {
    $r = shp_redis();
    if (!$r || !$pairs) return;
    try {
        $pipe = $r->multi(Redis::PIPELINE);
        foreach ($pairs as [$u, $d]) { $pipe->set(shp_ws_dev_key($u, $d), shp_ws_dev_val($port), ['ex' => 40]); }
        $pipe->exec();
    } catch (Throwable $e) {}
}
function shp_ws_dev_connected_many(array $pairs): array {
    $r = shp_redis();
    $out = [];
    if (!$r || !$pairs) return $out;
    try {
        $live = shp_ws_live_boots();
        $vals = $r->mGet(array_map(fn($p) => shp_ws_dev_key($p[0], $p[1]), $pairs));
        foreach ($pairs as $i => [$u, $d]) {
            $v = $vals[$i] ?? false;
            $ok = false;
            if (is_string($v) && str_contains($v, '|')) {
                [$port, $boot] = explode('|', $v, 2);
                $ok = ($live[(int)$port] ?? null) === $boot;
            }
            $out[$u . "\0" . $d] = $ok;
        }
    } catch (Throwable $e) {}
    return $out;
}
function shp_ws_live_boots(): array {
    $r = shp_redis();
    $out = [];
    if (!$r) return $out;
    try {
        foreach (shp_ws_live_ports() as $p) $out[$p] = (string)$r->hGet(shp_key('ws:stat:' . $p), 'boot');
    } catch (Throwable $e) {}
    return $out;
}
function shp_ws_proc_beat(int $port, array $stats): void {
    $r = shp_redis();
    if (!$r) return;
    try {
        $r->zAdd(shp_key('ws:procs'), time(), (string)$port);
        $r->hMSet(shp_key('ws:stat:' . $port), $stats + ['ts' => time(), 'boot' => shp_ws_boot()]);
        $r->expire(shp_key('ws:stat:' . $port), 30);
    } catch (Throwable $e) {}
}
function shp_ws_live_ports(): array {
    static $cache = [0, []];
    if ($cache[0] > microtime(true)) return $cache[1];
    $r = shp_redis();
    $ports = [];
    if ($r) {
        try { $ports = array_map('intval', $r->zRangeByScore(shp_key('ws:procs'), (string)(time() - 10), '+inf')); } catch (Throwable $e) {}
    }
    $cache = [microtime(true) + 1.0, $ports];
    return $ports;
}
function shp_ws_is_leader(int $port): bool {
    $r = shp_redis();
    if (!$r) return true;
    try {
        $k = shp_key('ws:leader');
        $me = shp_ws_dev_val($port);
        if ($r->set($k, $me, ['nx', 'ex' => 6])) return true;
        if ($r->get($k) === $me) { $r->expire($k, 6); return true; }
        return false;
    } catch (Throwable $e) { return true; }
}
function shp_ws_leader_port(): ?int {
    $p = shp_ws_live_ports();
    return $p ? min($p) : null;
}
function shp_ws_push(int $port, array $msg): void {
    $r = shp_redis();
    if (!$r) return;
    try {
        $k = shp_key('ws:in:' . $port);
        $r->rPush($k, json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        $r->expire($k, 60);
        if (random_int(1, 200) === 1) $r->lTrim($k, -5000, -1);
        shp_metric('ws_inbox_pushed');
    } catch (Throwable $e) {}
}
function shp_ws_drain(int $port, int $max = 300): array {
    $r = shp_redis();
    if (!$r) return [];
    try {
        $k = shp_key('ws:in:' . $port);
        $pipe = $r->multi(Redis::PIPELINE);
        $pipe->lRange($k, 0, $max - 1);
        $pipe->lTrim($k, $max, -1);
        $res = $pipe->exec();
        $out = [];
        foreach (($res[0] ?? []) as $raw) {
            $a = json_decode((string)$raw, true);
            if (is_array($a)) $out[] = $a;
        }
        return $out;
    } catch (Throwable $e) { return []; }
}
function shp_ws_dash_key(string $user, string $device): string {
    return shp_key('ws:dash:' . md5($user . "\0" . $device));
}
function shp_ws_dash_add(string $user, string $device, int $port): void {
    $r = shp_redis();
    if (!$r) return;
    try { $r->sAdd(shp_ws_dash_key($user, $device), (string)$port); $r->expire(shp_ws_dash_key($user, $device), 7200); } catch (Throwable $e) {}
}
function shp_ws_dash_remove(string $user, string $device, int $port): void {
    $r = shp_redis();
    if (!$r) return;
    try { $r->sRem(shp_ws_dash_key($user, $device), (string)$port); } catch (Throwable $e) {}
}
function shp_ws_dash_ports(string $user, string $device): array {
    $r = shp_redis();
    if (!$r) return [];
    try { return array_map('intval', $r->sMembers(shp_ws_dash_key($user, $device))); } catch (Throwable $e) { return []; }
}
function shp_ws_port_for_device(string $user, string $device, int $default): int {
    $p = shp_ws_dev_get($user, $device);
    return $p ?: $default;
}
function shp_metrics_snapshot(AppDatabase $db): array {
    $r = shp_redis();
    $out = ['redis' => (bool)$r, 'ts' => time()];
    if ($r) {
        try {
            $out['counters'] = $r->hGetAll(shp_key('m:counters')) ?: [];
            $out['queue_len'] = (int)$r->zCard(shp_key('cq:all'));
            $out['hot_dirty'] = 0;
            for ($i = 0; $i < SHP_HOT_SHARDS; $i++) $out['hot_dirty'] += (int)$r->sCard(shp_key('hot:dirty:' . $i));
            $out['hist_buffer'] = (int)$r->lLen(shp_key('hist:buf'));
            $procs = [];
            foreach (shp_ws_live_ports() as $p) {
                $s = $r->hGetAll(shp_key('ws:stat:' . $p)) ?: [];
                $s['inbox'] = (int)$r->lLen(shp_key('ws:in:' . $p));
                $procs[$p] = $s;
            }
            $out['ws_procs'] = $procs;
        } catch (Throwable $e) {}
    }
    $q = $db->query("SELECT relname, n_live_tup FROM pg_stat_user_tables WHERE relname LIKE 'smarthome_%'");
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) $out['tables'][$row['relname']] = (int)$row['n_live_tup'];
    return $out;
}
