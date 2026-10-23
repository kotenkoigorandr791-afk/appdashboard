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
    $db = app_main_db();
    $db->enableExceptions(true);
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
    $ids = [];
    $stmt = $db->prepare('SELECT control_id FROM smarthome_control_queue WHERE device_id = :id AND expires_at > :now');
    $stmt->bindValue(':id', $deviceId, PDO::PARAM_STR);
    $stmt->bindValue(':now', microtime(true), PDO::PARAM_STR);
    $res = $stmt->execute();
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) $ids[] = (string)($row['control_id'] ?? '');
    return $ids;
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
function shmw_handle_state(string $deviceId, string $message): void {
    $payload = json_decode($message, true);
    if (!is_array($payload)) return;
    $db = shmw_db();
    static $cache = [];
    $now = microtime(true);
    if (isset($cache[$deviceId]) && ($now - $cache[$deviceId][0]) < 2.0) {
        $device = $cache[$deviceId][1];
    } else {
        $stmt = $db->prepare('SELECT * FROM smarthome_devices WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $deviceId, PDO::PARAM_STR);
        $device = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (count($cache) > 20000) $cache = [];
    }
    if (!$device) return;
    $controls = shmw_json_array($device['controls'] ?? '[]');
    $params = shmw_json_array($device['params'] ?? '[]');
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
        shmw_record_history($db, $deviceId, $params, $payload['telemetry']);
    }
    static $sigs = [];
    $localUrl = trim((string)($payload['local_url'] ?? ''));
    $controlsJson = json_encode($controls, JSON_UNESCAPED_UNICODE);
    $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);
    $sig = md5($localUrl . $controlsJson . $paramsJson);
    $device['controls'] = $controlsJson;
    $device['params'] = $paramsJson;
    $cache[$deviceId] = [$now, $device];
    if (isset($sigs[$deviceId]) && $sigs[$deviceId][0] === $sig && ($now - $sigs[$deviceId][1]) < 30.0) return;
    if (count($sigs) > 20000) $sigs = [];
    $sigs[$deviceId] = [$sig, $now];
    $upd = $db->prepare("UPDATE smarthome_devices SET status='online', local_url=COALESCE(NULLIF(:local,''), local_url), controls=:controls, params=:params, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
    $upd->bindValue(':local', trim((string)($payload['local_url'] ?? '')), PDO::PARAM_STR);
    $upd->bindValue(':controls', json_encode($controls, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
    $upd->bindValue(':params', json_encode($params, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
    $upd->bindValue(':id', $deviceId, PDO::PARAM_STR);
    $upd->execute();
}
function shmw_handle_status(string $deviceId, string $message): void {
    $status = trim($message);
    if ($status !== 'online' && $status !== 'offline') {
        $payload = json_decode($message, true);
        $status = is_array($payload) ? (string)($payload['status'] ?? '') : '';
    }
    if ($status !== 'online' && $status !== 'offline') return;
    $db = shmw_db();
    $upd = $db->prepare('UPDATE smarthome_devices SET status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
    $upd->bindValue(':status', $status, PDO::PARAM_STR);
    $upd->bindValue(':id', $deviceId, PDO::PARAM_STR);
    $upd->execute();
}
while (true) {
    try {
        $db = shmw_db();
        [$username, $password] = shmw_backend_credentials($db);
        $mqtt = new MqttClient('127.0.0.1', 1883, 'appdashboard-smarthome-backend', MqttClient::MQTT_3_1);
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
        $mqtt->subscribe('home/+/state', function (string $topic, string $message) {
            $parts = explode('/', $topic);
            if (count($parts) === 3) {
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
