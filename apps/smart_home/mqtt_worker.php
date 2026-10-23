<?php
if (php_sapi_name() !== 'cli') {
    exit(1);
}
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('smart_home_mqtt');
require_once dirname(__DIR__, 2) . '/lib/app_db.php';
require_once __DIR__ . '/core/perf.php';
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
function shmw_db(): AppDatabase {
    static $cached = null, $at = 0.0;
    if ($cached && (microtime(true) - $at) < 30.0) return $cached;
    $db = app_main_db();
    $db->enableExceptions(true);
    $cached = $db;
    $at = microtime(true);
    static $ddlDone = false;
    if ($ddlDone || shp_schema_ready()) return $db;
    $ddlDone = true;
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_devices (
        id TEXT PRIMARY KEY,
        user_email TEXT NOT NULL,
        name TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'generic_sensor',
        room TEXT NOT NULL DEFAULT 'Living room',
        status TEXT NOT NULL DEFAULT 'unknown',
        local_url TEXT NOT NULL DEFAULT '',
        controls TEXT NOT NULL DEFAULT '[]',
        params TEXT NOT NULL DEFAULT '[]',
        updated_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_control_queue (
        id TEXT PRIMARY KEY,
        user_email TEXT NOT NULL,
        device_id TEXT NOT NULL,
        control_id TEXT NOT NULL,
        value_json TEXT NOT NULL,
        created_at REAL NOT NULL,
        expires_at REAL NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_params_history (
        id BIGSERIAL PRIMARY KEY,
        device_id TEXT NOT NULL,
        param_id TEXT NOT NULL,
        param_label TEXT NOT NULL,
        param_value TEXT NOT NULL,
        recorded_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS smarthome_mqtt_auth (
        username TEXT PRIMARY KEY,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'backend',
        updated_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP)
    )");
    return $db;
}
function shmw_backend_credentials(AppDatabase $db): array {
    $username = 'appdashboard_backend';
    $stmt = $db->prepare('SELECT password FROM smarthome_mqtt_auth WHERE username = :u LIMIT 1');
    $stmt->bindValue(':u', $username, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$row || (string)($row['password'] ?? '') === '') throw new RuntimeException('MQTT backend credentials missing');
    return [$username, (string)$row['password']];
}
function shmw_json_array(?string $json): array {
    $data = json_decode((string)$json, true);
    return is_array($data) ? $data : [];
}
function shmw_normalize_param_value(array $param, mixed $value): mixed {
    if (($param['type'] ?? '') === 'chart') {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $v) {
                if (is_numeric($v)) $out[] = (float)$v;
            }
            return $out;
        }
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? shmw_normalize_param_value($param, $decoded) : [];
    }
    if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE);
    return (string)$value;
}
function shmw_pending_control_ids(AppDatabase $db, string $deviceId): array {
    return shp_cq_pending($db, $deviceId, microtime(true));
}
function shmw_record_history(AppDatabase $db, string $deviceId, array $params, array $telemetry): void {
    $ins = $db->prepare('INSERT INTO smarthome_params_history (device_id, param_id, param_label, param_value, recorded_at) VALUES (:did, :pid, :lbl, :val, CURRENT_TIMESTAMP)');
    foreach ($params as $param) {
        if (!is_array($param)) continue;
        $id = (string)($param['id'] ?? '');
        if ($id === '' || !array_key_exists($id, $telemetry) || is_array($telemetry[$id])) continue;
        if (!shp_history_should_write($deviceId, $id, shp_clip((string)$telemetry[$id], 500))) continue;
        $ins->bindValue(':did', $deviceId, PDO::PARAM_STR);
        $ins->bindValue(':pid', $id, PDO::PARAM_STR);
        $ins->bindValue(':lbl', (string)($param['label'] ?? $id), PDO::PARAM_STR);
        $ins->bindValue(':val', shp_clip((string)$telemetry[$id], 500), PDO::PARAM_STR);
        $ins->execute();
    }
}
function shmw_my_shard(string $deviceId): bool {
    static $n = null, $i = null;
    if ($n === null) {
        $n = max(1, (int)(getenv('SMART_HOME_MQTT_SHARDS') ?: 1));
        $i = (int)(getenv('SMART_HOME_MQTT_SHARD') ?: 0);
    }
    return $n === 1 || (crc32($deviceId) % $n) === $i;
}
function shmw_device_known(AppDatabase $db, string $deviceId): ?array {
    static $cache = [];
    $now = microtime(true);
    if (isset($cache[$deviceId]) && ($now - $cache[$deviceId][0]) < 30.0) return $cache[$deviceId][1];
    $stmt = $db->prepare('SELECT controls, params FROM smarthome_devices WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $deviceId, PDO::PARAM_STR);
    $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (count($cache) > 50000) $cache = [];
    $cache[$deviceId] = [$now, $row ? ['controls' => shmw_json_array($row['controls'] ?? '[]'), 'params' => shmw_json_array($row['params'] ?? '[]')] : null];
    return $cache[$deviceId][1];
}
function shmw_load_base(AppDatabase $db, string $deviceId): ?array {
    $hot = shp_hot_get($deviceId);
    if ($hot) return $hot + ['known' => true];
    $row = shmw_device_known($db, $deviceId);
    if (!$row) return null;
    $stmt = $db->prepare('SELECT controls, params, local_url, status FROM smarthome_devices WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $deviceId, PDO::PARAM_STR);
    $r = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;
    return ['controls' => shmw_json_array($r['controls'] ?? '[]'), 'params' => shmw_json_array($r['params'] ?? '[]'), 'local_url' => (string)($r['local_url'] ?? ''), 'status' => (string)($r['status'] ?? 'unknown')];
}
function shmw_handle_state(string $deviceId, string $message): void {
    if (!shmw_my_shard($deviceId)) return;
    $payload = json_decode($message, true);
    if (!is_array($payload)) return;
    $db = shmw_db();
    $base = shmw_load_base($db, $deviceId);
    if (!$base) return;
    $controls = $base['controls'];
    $params = $base['params'];
    if (!empty($payload['controls']) && is_array($payload['controls'])) {
        $pending = shmw_pending_control_ids($db, $deviceId);
        foreach ($controls as &$control) {
            $id = (string)($control['id'] ?? '');
            if ($id === '' || in_array($id, $pending, true) || !array_key_exists($id, $payload['controls'])) continue;
            $control['value'] = $payload['controls'][$id];
        }
        unset($control);
    }
    if (!empty($payload['telemetry']) && is_array($payload['telemetry'])) {
        foreach ($params as &$param) {
            $id = (string)($param['id'] ?? '');
            if ($id === '' || $id === 'ws' || !array_key_exists($id, $payload['telemetry'])) continue;
            $param['value'] = shmw_normalize_param_value($param, $payload['telemetry'][$id]);
        }
        unset($param);
        foreach ($params as $param) {
            $id = (string)($param['id'] ?? '');
            if ($id === '' || !array_key_exists($id, $payload['telemetry']) || is_array($payload['telemetry'][$id])) continue;
            $val = shp_clip((string)$payload['telemetry'][$id], 500);
            if (shp_history_should_write($deviceId, $id, $val)) shp_hist_push($deviceId, $id, (string)($param['label'] ?? $id), $val);
        }
    }
    $localUrl = trim((string)($payload['local_url'] ?? ''));
    $ok = shp_hot_put($deviceId, ['status' => 'online', 'local_url' => $localUrl !== '' ? $localUrl : (string)($base['local_url'] ?? ''), 'controls' => $controls, 'params' => $params]);
    if ($ok === null) throw new RuntimeException('redis unavailable');
}
function shmw_handle_status(string $deviceId, string $message): void {
    if (!shmw_my_shard($deviceId)) return;
    $status = trim($message);
    if ($status !== 'online' && $status !== 'offline') {
        $payload = json_decode($message, true);
        $status = is_array($payload) ? (string)($payload['status'] ?? '') : '';
    }
    if ($status !== 'online' && $status !== 'offline') return;
    $db = shmw_db();
    $base = shmw_load_base($db, $deviceId);
    if (!$base) return;
    shp_hot_put($deviceId, ['status' => $status, 'local_url' => (string)($base['local_url'] ?? ''), 'controls' => $base['controls'], 'params' => $base['params']]);
}
function shmw_count(): void {
    static $n = 0, $t = 0.0;
    $n++;
    $now = microtime(true);
    if ($now - $t >= 2.0) {
        shp_metric('mqtt_msgs', $n);
        $n = 0;
        $t = $now;
    }
}
while (true) {
    try {
        $db = shmw_db();
        [$username, $password] = shmw_backend_credentials($db);
        $mqtt = new MqttClient('127.0.0.1', 1883, 'appdashboard-smarthome-backend-' . (int)(getenv('SMART_HOME_MQTT_SHARD') ?: 0), MqttClient::MQTT_3_1);
        $settings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setKeepAliveInterval(15)
            ->setConnectTimeout(5)
            ->setSocketTimeout(5)
            ->setReconnectAutomatically(true)
            ->setMaxReconnectAttempts(1000000)
            ->setDelayBetweenReconnectAttempts(1000);
        $mqtt->connect($settings, false);
        $mqtt->registerLoopEventHandler(function (MqttClient $c, float $elapsed) {
            static $last = 0.0;
            $now = microtime(true);
            if ($now - $last < 0.5) return;
            $last = $now;
            shp_flush_all(shmw_db());
            $GLOBALS['shmw_beat'] = $now;
        });
        $mqtt->subscribe('home/+/state', function (string $topic, string $message) {
            $parts = explode('/', $topic);
            if (count($parts) === 3) {
                shmw_count();
                try { shmw_handle_state($parts[1], $message); } catch (Throwable $e) { fwrite(STDERR, 'mqtt_worker state: ' . $e->getMessage() . PHP_EOL); }
            }
        }, 1);
        $mqtt->subscribe('home/+/status', function (string $topic, string $message) {
            $parts = explode('/', $topic);
            if (count($parts) === 3) {
                try { shmw_handle_status($parts[1], $message); } catch (Throwable $e) { fwrite(STDERR, 'mqtt_worker status: ' . $e->getMessage() . PHP_EOL); }
            }
        }, 1);
        $mqtt->loop(true);
        $mqtt->disconnect();
    } catch (Throwable $e) {
        fwrite(STDERR, 'mqtt_worker: ' . $e->getMessage() . PHP_EOL);
        sleep(5);
    }
}
