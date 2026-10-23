<?php

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

require_once __DIR__ . '/core/perf.php';

class SmartHomeWsHandler implements MessageComponentInterface
{
    private string $appDir;
    private \SplObjectStorage $clients;
    private bool $startupReconcileDone = false;
    private float $lastReconcileAt = 0.0;
    private bool $ddlDone = false;
    private int $port = 8766;
    private array $cfgCache = [];
    private array $deviceConns = [];
    private array $dashCount = [];
    private float $lastBeatAt = 0.0;
    private float $lastTouchAt = 0.0;
    private float $tickMax = 0.0;
    private array $prevBoots = [];
    private bool $isLeader = false;
    private array $persistSig = [];
    private float $lastQueuePollAt = 0.0;
    private float $lastTokenPurgeAt = 0.0;

    public function __construct(string $appDir, int $port = 8766)
    {
        $this->port = $port;
        $this->appDir = rtrim($appDir, '/');
        
        $this->clients = new \SplObjectStorage();
    }

    private ?AppDatabase $dbc = null;
    private float $dbAt = 0.0;

    private function db(): AppDatabase
    {
        if ($this->dbc && (microtime(true) - $this->dbAt) < 30.0) {
            return $this->dbc;
        }
        require_once dirname($this->appDir, 2) . '/lib/app_db.php';
        $db = app_main_db();
        $db->enableExceptions(true);
        $this->dbc = $db;
        $this->dbAt = microtime(true);
        if (!$this->ddlDone) {
            $this->ddlDone = true;
            if (!shp_schema_ready()) {
            $db->exec("CREATE TABLE IF NOT EXISTS smarthome_ws_tokens (
                token TEXT PRIMARY KEY, user_email TEXT NOT NULL, device_id TEXT NOT NULL,
                expires_at INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP)
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS smarthome_control_queue (
                id TEXT PRIMARY KEY, user_email TEXT NOT NULL, device_id TEXT NOT NULL,
                control_id TEXT NOT NULL, value_json TEXT NOT NULL, created_at REAL NOT NULL, expires_at REAL NOT NULL
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS smarthome_control_results (
                id TEXT PRIMARY KEY, result_json TEXT NOT NULL, updated_at REAL NOT NULL
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS smarthome_monitor_logs (
                id BIGSERIAL PRIMARY KEY,
                user_email TEXT NOT NULL,
                device_id TEXT,
                event_type TEXT NOT NULL,
                event_data TEXT NOT NULL DEFAULT '{}',
                created_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP)
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS smarthome_errors (
                id BIGSERIAL PRIMARY KEY,
                user_email TEXT NOT NULL DEFAULT '',
                device_id TEXT NOT NULL DEFAULT '',
                device_name TEXT NOT NULL DEFAULT '',
                source TEXT NOT NULL DEFAULT '',
                message TEXT NOT NULL,
                context_json TEXT NOT NULL DEFAULT '{}',
                created_at TEXT NOT NULL DEFAULT (CURRENT_TIMESTAMP)
            )");
            }
        }
        return $db;
    }

    private function rowToArray(array $row): array
    {
        $row['controls'] = json_decode($row['controls'] ?? '[]', true) ?: [];
        $row['params'] = json_decode($row['params'] ?? '[]', true) ?: [];
        $row['files'] = json_decode($row['files'] ?? '[]', true) ?: [];
        $row['displayed_params'] = json_decode($row['displayed_params'] ?? '[]', true) ?: [];
        if (!empty($row['displayed_params']) && !empty($row['params'])) {
            $paramById = [];
            foreach ($row['params'] as $param) {
                $pid = (string)($param['id'] ?? '');
                if ($pid !== '') {
                    $paramById[$pid] = $param;
                }
            }
            foreach ($row['displayed_params'] as &$displayed) {
                $pid = (string)($displayed['id'] ?? '');
                if ($pid !== '' && isset($paramById[$pid])) {
                    $displayed = array_merge($displayed, $paramById[$pid]);
                }
            }
            unset($displayed);
        }
        return $this->sanitizeDevice($row);
    }

    private function sanitizeDevice(array $device): array
    {
        if (($device['type'] ?? '') !== 'smart_torch') {
            return $device;
        }
        $controls = [];
        foreach (($device['controls'] ?? []) as $control) {
            $id = (string)($control['id'] ?? '');
            if ($id === 'power_save') {
                continue;
            }
            if ($id === 'relay_1') {
                $control['label'] = 'Main lamp';
            } elseif ($id === 'relay_2') {
                $control['label'] = 'Small lamp';
            }
            $controls[] = $control;
        }
        $device['controls'] = $controls;
        return $device;
    }

    private function applyDeviceDefinition(array $device, array $definition): array
    {
        $sectionLabels = [];
        if (!empty($definition['sections']) && is_array($definition['sections'])) {
            foreach ($definition['sections'] as $section) {
                if (!is_array($section)) {
                    continue;
                }
                $sid = trim((string)($section['id'] ?? ''));
                if ($sid === '') {
                    continue;
                }
                $sectionLabels[$sid] = trim((string)($section['label'] ?? $sid));
            }
        }
        if (($definition['type'] ?? '') !== '') {
            $device['type'] = (string)$definition['type'];
        }
        $iconLocked = !empty($device['icon_locked']);
        if (!$iconLocked) {
            if (($definition['icon'] ?? '') !== '') {
                $device['icon'] = (string)$definition['icon'];
            } elseif (($definition['type'] ?? '') !== '' && ($device['type'] ?? '') !== 'pending') {
                $tpl = $this->templateForType((string)$device['type']);
                if ($tpl && !empty($tpl['icon'])) {
                    $device['icon'] = (string)$tpl['icon'];
                }
            }
        }
        if (!empty($definition['controls']) && is_array($definition['controls'])) {
            $existingValues = [];
            foreach (($device['controls'] ?? []) as $control) {
                $id = (string)($control['id'] ?? '');
                if ($id !== '') {
                    $existingValues[$id] = $control['value'] ?? null;
                }
            }
            $controls = [];
            foreach ($definition['controls'] as $control) {
                if (!is_array($control)) {
                    continue;
                }
                $id = trim((string)($control['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                if (!array_key_exists('value', $control) && array_key_exists($id, $existingValues)) {
                    $control['value'] = $existingValues[$id];
                }
                $sid = trim((string)($control['section'] ?? ''));
                if ($sid !== '' && isset($sectionLabels[$sid])) {
                    $control['section_label'] = $sectionLabels[$sid];
                }
                $controls[] = $control;
            }
            $device['controls'] = $controls;
        }
        if (!empty($definition['params']) && is_array($definition['params'])) {
            $existingValues = [];
            foreach (($device['params'] ?? []) as $param) {
                $id = (string)($param['id'] ?? '');
                if ($id !== '') {
                    $existingValues[$id] = $param['value'] ?? null;
                }
            }
            $params = [];
            foreach ($definition['params'] as $param) {
                if (!is_array($param)) {
                    continue;
                }
                $id = trim((string)($param['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                if (!array_key_exists('value', $param) && array_key_exists($id, $existingValues)) {
                    $param['value'] = $existingValues[$id];
                }
                $sid = trim((string)($param['section'] ?? ''));
                if ($sid !== '' && isset($sectionLabels[$sid])) {
                    $param['section_label'] = $sectionLabels[$sid];
                }
                $params[] = $param;
            }
            $device['params'] = $params;
        }
        if (!empty($definition['files']) && is_array($definition['files'])) {
            $device['files'] = array_values(array_filter($definition['files'], 'is_array'));
        }
        return $this->sanitizeDevice($device);
    }

    private function templateForType(string $type): ?array
    {
        static $templates = [
            'generic_sensor' => ['icon' => 'sensor'],
            'smart_torch' => ['icon' => 'torch'],
            'smart_lamp' => ['icon' => 'lamp'],
            'smart_socket' => ['icon' => 'smart-plug'],
            'sonoff_relay' => ['icon' => 'light-bulb'],
            'pending' => ['icon' => 'smart-device'],
        ];
        return $templates[$type] ?? null;
    }

    private function monitorPurge(AppDatabase $db): void
    {
        shp_purge_if_due($db);
    }

    private function errorPurge(AppDatabase $db): void
    {
        shp_purge_if_due($db);
    }

    private function monitorLog(string $email, ?string $deviceId, string $eventType, array $data): void
    {
        try {
            if (!shp_rate_ok('ml:' . shp_user_hash($email), 300)) {
                return;
            }
            $db = $this->db();
            if ($deviceId && empty($data['device_name'])) {
                $stmt = $db->prepare('SELECT name FROM smarthome_devices WHERE id = :id LIMIT 1');
                $stmt->bindValue(':id', $deviceId, PDO::PARAM_STR);
                $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $data['device_name'] = (string)($row['name'] ?? '');
                }
            }
            $stmt = $db->prepare('INSERT INTO smarthome_monitor_logs (user_email, device_id, event_type, event_data) VALUES (:em, :did, :et, :ed)');
            $stmt->bindValue(':em', $email, PDO::PARAM_STR);
            $stmt->bindValue(':did', $deviceId ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':et', $eventType, PDO::PARAM_STR);
            $stmt->bindValue(':ed', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
            $stmt->execute();
        } catch (\Throwable $e) {
            error_log('WS monitorLog: ' . $e->getMessage());
        }
    }

    private function logError(string $email, ?string $deviceId, string $deviceName, string $source, string $message, array $context = []): void
    {
        try {
            if (!shp_event_dedupe('err', $email, (string)$deviceId, $source, $message) || !shp_rate_ok('err:' . shp_user_hash($email), 120)) {
                return;
            }
            $message = shp_clip($message, 2000);
            $db = $this->db();
            if ($deviceName === '' && $deviceId) {
                $stmt = $db->prepare('SELECT name FROM smarthome_devices WHERE id = :id LIMIT 1');
                $stmt->bindValue(':id', $deviceId, PDO::PARAM_STR);
                $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $deviceName = (string)($row['name'] ?? '');
                }
            }
            $stmt = $db->prepare('INSERT INTO smarthome_errors (user_email, device_id, device_name, source, message, context_json) VALUES (:em, :did, :dn, :src, :msg, :ctx)');
            $stmt->bindValue(':em', $email, PDO::PARAM_STR);
            $stmt->bindValue(':did', $deviceId ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':dn', $deviceName, PDO::PARAM_STR);
            $stmt->bindValue(':src', $source, PDO::PARAM_STR);
            $stmt->bindValue(':msg', $message, PDO::PARAM_STR);
            $stmt->bindValue(':ctx', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
            $stmt->execute();
        } catch (\Throwable $e) {
            error_log('WS logError: ' . $e->getMessage());
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->offsetSet($conn, [
            'role' => null,
            'user_email' => null,
            'device_id' => null,
            'device_token' => null,
            'connected_at' => microtime(true),
            'ip' => $this->clientIp($conn),
            'bucket' => 100.0,
            'bucket_at' => microtime(true),
            'dropped' => 0,
        ]);
    }

    private function clientIp(ConnectionInterface $conn): string
    {
        try {
            $h = $conn->httpRequest->getHeader('X-Real-IP');
            if ($h) return (string)$h[0];
        } catch (\Throwable $e) {}
        return (string)($conn->remoteAddress ?? '');
    }

    private function allowMessage(ConnectionInterface $conn, array &$state, int $len): bool
    {
        $role = $state['role'] ?? null;
        $maxLen = $role === 'dashboard' ? 65536 : 1048576;
        if ($len > $maxLen) {
            shp_metric('ws_oversize');
            try { $conn->close(); } catch (\Throwable $e) {}
            return false;
        }
        $now = microtime(true);
        $rate = $role === 'dashboard' ? 20.0 : 100.0;
        $burst = $role === 'dashboard' ? 60.0 : 300.0;
        $state['bucket'] = min($burst, ($state['bucket'] ?? $burst) + ($now - ($state['bucket_at'] ?? $now)) * $rate);
        $state['bucket_at'] = $now;
        if ($state['bucket'] < 1.0) {
            $state['dropped'] = ($state['dropped'] ?? 0) + 1;
            shp_metric('ws_rate_dropped');
            if ($state['dropped'] > 1000) {
                try { $conn->close(); } catch (\Throwable $e) {}
            }
            return false;
        }
        $state['bucket'] -= 1.0;
        return true;
    }

    public function onMessage(ConnectionInterface $conn, $msg): void
    {
        if (!$this->clients->offsetExists($conn)) {
            return;
        }
        $state = $this->clients[$conn];
        if (!$this->allowMessage($conn, $state, strlen((string)$msg))) {
            $this->clients[$conn] = $state;
            return;
        }
        $state['last_recv_at'] = microtime(true);
        $this->clients[$conn] = $state;
        $payload = json_decode((string)$msg, true);
        if (!is_array($payload)) {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Invalid payload']);
            return;
        }
        $type = (string)($payload['type'] ?? '');
        if ($type === 'ping') {
            $this->sendJson($conn, ['type' => 'pong', 'ts' => microtime(true)]);
            return;
        }
        if ($type === 'pong') {
            return;
        }
        if ($type === 'auth') {
            $this->handleAuth($conn, $payload);
            return;
        }

        if (($state['role'] ?? null) === 'device') {
            if ($type === 'state') {
                $this->handleDeviceState($conn, $payload);
            } elseif (in_array($type, ['route_list_result', 'route_list_end', 'route_file_begin', 'route_file_chunk', 'route_file_end', 'route_file_error', 'route_action_result'], true)) {
                $this->forwardDeviceRouteMessage($conn, $payload);
            }
            return;
        }
        if (($state['role'] ?? null) === 'dashboard') {
            if ($type === 'control') {
                $this->handleDashboardControl($conn, $payload);
            } elseif (in_array($type, ['route_list', 'route_file', 'route_delete', 'route_rename'], true)) {
                $this->handleDashboardRouteRequest($conn, $payload);
            }
            return;
        }
        $this->sendJson($conn, ['type' => 'error', 'message' => 'Unauthenticated']);
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if (!$this->clients->offsetExists($conn)) {
            return;
        }
        $state = $this->clients[$conn];
        $u = (string)($state['user_email'] ?? '');
        $d = (string)($state['device_id'] ?? '');
        if (($state['role'] ?? null) === 'device' && $u !== '' && $d !== '') {
            $key = $u . "\0" . $d;
            $mine = ($this->deviceConns[$key] ?? null) === $conn;
            if ($mine) unset($this->deviceConns[$key]);
            $owner = shp_ws_dev_get($u, $d);
            if ($mine && ($owner === null || $owner === $this->port)) {
                shp_ws_dev_del($u, $d, $this->port);
                $this->setDeviceTunnelStatus($u, $d, false);
            }
        }
        if (($state['role'] ?? null) === 'dashboard' && $u !== '') {
            $key = $u . "\0" . $d;
            $this->dashCount[$key] = max(0, ($this->dashCount[$key] ?? 1) - 1);
            if ($this->dashCount[$key] === 0) {
                unset($this->dashCount[$key]);
                shp_ws_dash_remove($u, $d, $this->port);
            }
        }
        $this->clients->offsetUnset($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->sendJson($conn, ['type' => 'error', 'message' => $e->getMessage()]);
        $conn->close();
    }

    public function tick(): void
    {
        $t0 = microtime(true);
        try {
            $db = $this->db();
            shp_purge_if_due($db);
            shp_flush_all($db);
            $this->processControlQueue();
            $this->heartbeatTick();
            $this->beatTick();
            if ($this->isLeader) {
                if (!$this->startupReconcileDone) {
                    $this->startupReconcileDone = true;
                    $this->reconcileTunnelStatus(true);
                } else {
                    $this->reconcileTunnelStatus(false);
                }
            }
        } catch (\Throwable $e) {
            error_log('WS tick: ' . $e->getMessage());
        }
        $this->tickMax = max($this->tickMax, microtime(true) - $t0);
    }

    private float $lastHeartbeatAt = 0;

    private function heartbeatTick(): void
    {
        $now = microtime(true);
        if ($now - $this->lastHeartbeatAt < 5.0) return;
        $this->lastHeartbeatAt = $now;
        $stale = [];
        foreach ($this->clients as $client) {
            $state = $this->clients[$client];
            $lastRecv = (float)($state['last_recv_at'] ?? $state['connected_at'] ?? $now);
            $lastPing = (float)($state['last_ping_at'] ?? 0);
            if (($now - $lastRecv) > 90.0) {
                $stale[] = $client;
                continue;
            }
            if (($now - $lastRecv) > 30.0 && ($now - $lastPing) > 25.0) {
                $this->sendJson($client, ['type' => 'ping', 'ts' => $now]);
                $state['last_ping_at'] = $now;
                $this->clients[$client] = $state;
            }
        }
        foreach ($stale as $c) {
            $cs = $this->clients[$c] ?? null;
            if ($cs && ($cs['role'] ?? null) === 'device' && !empty($cs['device_id'])) {
                $this->logError((string)($cs['user_email'] ?? ''), (string)$cs['device_id'], '', 'tunnel', 'Connection timed out (no traffic in 90s), closing', []);
                $this->setDeviceTunnelStatus((string)($cs['user_email'] ?? ''), (string)$cs['device_id'], false);
            }
            try { $c->close(); } catch (\Throwable $e) {}
        }
    }

    private function authFailed(ConnectionInterface $conn): void
    {
        $ip = (string)($this->clients[$conn]['ip'] ?? '');
        if ($ip !== '' && $ip !== '127.0.0.1' && $ip !== '::1' && !shp_rate_ok('wsauthfail:' . $ip, 20)) {
            shp_metric('ws_auth_blocked');
            try { $conn->close(); } catch (\Throwable $e) {}
        }
    }

    private function authAllowed(ConnectionInterface $conn): bool
    {
        $ip = (string)($this->clients[$conn]['ip'] ?? '');
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1' || shp_rate_ok('wsauth:' . $ip, 600)) return true;
        $this->sendJson($conn, ['type' => 'error', 'message' => 'Too many auth attempts']);
        try { $conn->close(); } catch (\Throwable $e) {}
        return false;
    }

    private function handleAuth(ConnectionInterface $conn, array $payload): void
    {
        if (!$this->authAllowed($conn)) {
            return;
        }
        $role = (string)($payload['role'] ?? '');
        if ($role === 'device') {
            $this->authDevice($conn, $payload);
            return;
        }
        if ($role === 'dashboard') {
            $this->authDashboard($conn, $payload);
            return;
        }
        $this->sendJson($conn, ['type' => 'error', 'message' => 'Unknown role']);
    }

    private function authDevice(ConnectionInterface $conn, array $payload): void
    {
        $deviceToken = trim((string)($payload['device_token'] ?? ''));
        if ($deviceToken === '') {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Missing device token']);
            return;
        }
        $db = $this->db();
        $stmt = $db->prepare('SELECT * FROM smarthome_token_index WHERE token = :tok');
        $stmt->bindValue(':tok', $deviceToken, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->authFailed($conn);
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Unknown device token']);
            return;
        }
        $userEmail = (string)($row['user_email'] ?? '');
        $deviceId = (string)($row['device_id'] ?? '');
        unset($this->cfgCache[$deviceId]);
        shp_hot_drop($deviceId);
        $device = $this->loadDevice($userEmail, $deviceId);
        if ($device === null) {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Device record not found']);
            return;
        }
        $state = $this->clients[$conn];
        $state['role'] = 'device';
        $state['user_email'] = $userEmail;
        $state['device_id'] = $deviceId;
        $state['device_token'] = $deviceToken;
        $state['last_recv_at'] = microtime(true);
        $state['last_ping_at'] = 0.0;
        $this->clients[$conn] = $state;
        $key = $userEmail . "\0" . $deviceId;
        $dup = $this->deviceConns[$key] ?? null;
        if ($dup && $dup !== $conn) {
            unset($this->deviceConns[$key]);
            try { $dup->close(); } catch (\Throwable $e) {}
        }
        $this->deviceConns[$key] = $conn;
        $prevOwner = shp_ws_dev_set($userEmail, $deviceId, $this->port);
        if ($prevOwner !== null && $prevOwner !== $this->port) {
            shp_ws_push($prevOwner, ['kind' => 'close_device', 'user' => $userEmail, 'device' => $deviceId]);
        }
        if (!empty($payload['definition']) && is_array($payload['definition'])) {
            $device = $this->applyDeviceDefinition($device, $payload['definition']);
        }
        if (!empty($payload['name'])) {
            $device['name'] = trim((string)$payload['name']);
        }
        if (!empty($payload['local_url'])) {
            $device['local_url'] = trim((string)$payload['local_url']);
        }
        $device = $this->setDeviceTunnelStatus($userEmail, $deviceId, true, $device, true);
        $this->sendJson($conn, array_merge([
            'type' => 'connected',
            'device_id' => $deviceId,
        ], $this->controlsMap($device)));
        $this->broadcastToDashboards($userEmail, $deviceId, [
            'type' => 'state_update',
            'device' => $device,
        ]);
    }

    private function authDashboard(ConnectionInterface $conn, array $payload): void
    {
        $sessionToken = trim((string)($payload['session_token'] ?? ''));
        if ($sessionToken === '') {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Missing session token']);
            return;
        }
        $db = $this->db();
        $stmt = $db->prepare('SELECT user_email, device_id, expires_at FROM smarthome_ws_tokens WHERE token = :tok');
        $stmt->bindValue(':tok', $sessionToken, PDO::PARAM_STR);
        $meta = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (!$meta || (int)($meta['expires_at'] ?? 0) < time()) {
            $this->authFailed($conn);
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Invalid dashboard token']);
            return;
        }
        $state = $this->clients[$conn];
        $state['role'] = 'dashboard';
        $state['user_email'] = (string)($meta['user_email'] ?? '');
        $state['device_id'] = (string)($meta['device_id'] ?? '');
        $this->clients[$conn] = $state;
        if (!shp_rate_ok('wsdash:' . $state['user_email'], 120)) {
            $state['role'] = null;
            $this->clients[$conn] = $state;
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Too many dashboard connections']);
            try { $conn->close(); } catch (\Throwable $e) {}
            return;
        }
        $dk = $state['user_email'] . "\0" . $state['device_id'];
        $this->dashCount[$dk] = ($this->dashCount[$dk] ?? 0) + 1;
        shp_ws_dash_add($state['user_email'], $state['device_id'], $this->port);
        $del = $db->prepare('DELETE FROM smarthome_ws_tokens WHERE token = :tok');
        $del->bindValue(':tok', $sessionToken, PDO::PARAM_STR);
        $del->execute();

        $device = $this->loadDevice((string)$state['user_email'], (string)$state['device_id']);
        $this->sendJson($conn, [
            'type' => 'connected',
            'device' => $device,
            'controls' => $device ? $this->controlsMap($device) : new \stdClass(),
        ]);
    }

    private function handleDeviceState(ConnectionInterface $conn, array $payload): void
    {
        $state = $this->clients[$conn];
        $device = $this->loadDevice((string)$state['user_email'], (string)$state['device_id']);
        if ($device === null) {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Device record not found']);
            return;
        }
        if (!empty($payload['definition']) && is_array($payload['definition'])) {
            $device = $this->applyDeviceDefinition($device, $payload['definition']);
        }
        if (!empty($payload['local_url'])) {
            $device['local_url'] = trim((string)$payload['local_url']);
        }
        if (!empty($payload['controls']) && is_array($payload['controls'])) {
            $pending = $this->pendingControlIds((string)$state['device_id']);
            $userEmail = (string)$state['user_email'];
            $deviceId = (string)$state['device_id'];
            foreach ($device['controls'] as &$control) {
                $cid = (string)($control['id'] ?? '');
                if ($cid === '' || in_array($cid, $pending, true)) continue;
                if ($this->isControlPending($userEmail, $deviceId, $cid)) continue;
                if (!array_key_exists($cid, $payload['controls'])) continue;
                $control['value'] = $payload['controls'][$cid];
            }
            unset($control);
        }
        if (!empty($payload['telemetry']) && is_array($payload['telemetry'])) {
            foreach ($device['params'] as &$param) {
                if ((string)($param['id'] ?? '') === 'ws') continue;
                if (array_key_exists($param['id'], $payload['telemetry'])) {
                    $param['value'] = $this->normalizeParamValue($param, $payload['telemetry'][$param['id']]);
                }
            }
            unset($param);
        }
        if (!empty($payload['events']) && is_array($payload['events'])) {
            foreach ($payload['events'] as $event) {
                if (!is_array($event)) {
                    continue;
                }
                $this->monitorLog((string)$state['user_email'], (string)$state['device_id'], 'device_toggle', [
                    'device_name' => (string)($device['name'] ?? ''),
                    'source' => (string)($event['source'] ?? 'device'),
                    'control_id' => (string)($event['control_id'] ?? ''),
                    'control_label' => (string)($event['control_label'] ?? ''),
                    'value' => (bool)($event['value'] ?? false),
                    'uptime_ms' => (int)($event['uptime_ms'] ?? 0),
                    'local_url' => (string)($payload['local_url'] ?? ''),
                    'device_name' => (string)($device['name'] ?? ''),
                ]);
            }
        }
        $device = $this->applyTunnelStatusToDevice($device, true);
        $this->persistDevice($device, !empty($payload['definition']) && is_array($payload['definition']));
        $this->broadcastToDashboards((string)$state['user_email'], (string)$state['device_id'], [
            'type' => 'state_update',
            'device' => $device,
        ]);
    }

    private function handleDashboardControl(ConnectionInterface $conn, array $payload): void
    {
        $state = $this->clients[$conn];
        $device = $this->loadDevice((string)$state['user_email'], (string)$state['device_id']);
        if ($device === null) {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Device record not found']);
            return;
        }
        $controlId = trim((string)($payload['control_id'] ?? ''));
        if ($controlId === '') {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Missing control id']);
            return;
        }
        foreach ($device['controls'] as &$control) {
            if (($control['id'] ?? '') !== $controlId) {
                continue;
            }
            if (($control['type'] ?? '') === 'switch') {
                $control['value'] = (bool)($payload['value'] ?? false);
            } elseif (($control['type'] ?? '') === 'range') {
                $control['value'] = (int)($payload['value'] ?? 0);
            } else {
                $control['value'] = (string)($payload['value'] ?? '');
            }
        }
        unset($control);
        $this->persistDevice($device);

        $matched = 0;
        $cmd = [
            'type' => 'command',
            'control_id' => $controlId,
            'value' => $payload['value'] ?? null,
            'controls' => $this->controlsMap($device),
        ];
        $local = $this->localDevice((string)$state['user_email'], (string)$state['device_id']);
        if ($local) {
            $this->sendJson($local, $cmd);
            $matched++;
        } elseif ($this->forwardToDevice((string)$state['user_email'], (string)$state['device_id'], $cmd, [$controlId, $payload['value'] ?? null])) {
            $matched++;
        }

        if ($matched > 0) {
            $this->markControlPending((string)$state['user_email'], (string)$state['device_id'], $controlId, $payload['value'] ?? null);
        }

        $this->broadcastToDashboards((string)$state['user_email'], (string)$state['device_id'], [
            'type' => 'state_update',
            'device' => $device,
        ]);
        $this->sendJson($conn, [
            'type' => 'ack',
            'device' => $device,
            'matched' => $matched,
        ]);
    }

    private function handleDashboardRouteRequest(ConnectionInterface $conn, array $payload): void
    {
        $state = $this->clients[$conn];
        $requestId = trim((string)($payload['request_id'] ?? ''));
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(8));
            $payload['request_id'] = $requestId;
        }
        if ($this->routeTunnelDisabled((string)$state['user_email'], (string)$state['device_id'])) {
            $this->sendJson($conn, [
                'type' => 'route_error',
                'request_id' => $requestId,
                'ok' => false,
                'error' => 'route_maps_disabled',
            ]);
            return;
        }
        $matched = 0;
        $local = $this->localDevice((string)$state['user_email'], (string)$state['device_id']);
        if ($local) {
            $this->sendJson($local, $payload);
            $matched++;
        } elseif ($this->forwardToDevice((string)$state['user_email'], (string)$state['device_id'], $payload)) {
            $matched++;
        }
        if ($matched < 1) {
            $this->sendJson($conn, [
                'type' => 'route_error',
                'request_id' => $requestId,
                'ok' => false,
                'error' => 'device_tunnel_not_connected',
            ]);
        }
    }

    private function forwardDeviceRouteMessage(ConnectionInterface $conn, array $payload): void
    {
        $state = $this->clients[$conn];
        if ($this->routeTunnelDisabled((string)$state['user_email'], (string)$state['device_id'])) {
            return;
        }
        $this->broadcastToDashboards((string)$state['user_email'], (string)$state['device_id'], $payload);
    }

    private function routeTunnelDisabled(string $userEmail, string $deviceId): bool
    {
        $device = $this->loadDevice($userEmail, $deviceId);
        return trim((string)($device['name'] ?? '')) === 'Bike Doc Station';
    }

    private array $controlPending = [];

    private function markControlPending(string $userEmail, string $deviceId, string $controlId, mixed $value): void
    {
        $key = $userEmail . '|' . $deviceId . '|' . $controlId;
        $this->controlPending[$key] = [
            'value' => $value,
            'until' => microtime(true) + 5.0,
        ];
    }

    private function isControlPending(string $userEmail, string $deviceId, string $controlId): bool
    {
        $key = $userEmail . '|' . $deviceId . '|' . $controlId;
        $entry = $this->controlPending[$key] ?? null;
        if (!$entry) return false;
        if ($entry['until'] < microtime(true)) {
            unset($this->controlPending[$key]);
            return false;
        }
        return true;
    }

    private function loadDevice(string $userEmail, string $deviceId): ?array
    {
        try {
            $hot = shp_hot_get($deviceId);
            $cached = $this->cfgCache[$deviceId] ?? null;
            if ($hot && $cached && (microtime(true) - $cached[1]) < 300.0 && ($cached[0]['user_email'] ?? '') === $userEmail) {
                $dev = $cached[0];
                $dev['status'] = $hot['status'];
                if ($hot['local_url'] !== '') $dev['local_url'] = $hot['local_url'];
                $dev['controls'] = $hot['controls'];
                $dev['params'] = $hot['params'];
                return $dev;
            }
            $db = $this->db();
            $stmt = $db->prepare('SELECT * FROM smarthome_devices WHERE id = :id AND user_email = :em');
            $stmt->bindValue(':id', $deviceId, PDO::PARAM_STR);
            $stmt->bindValue(':em', $userEmail, PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            $dev = $this->rowToArray($row);
            if (count($this->cfgCache) > 20000) $this->cfgCache = [];
            $this->cfgCache[$deviceId] = [$dev, microtime(true)];
            return $dev;
        } catch (\Throwable $e) {
            error_log('WS loadDevice: ' . $e->getMessage());
            return null;
        }
    }

    private function applyTunnelStatusToDevice(array $device, bool $online): array
    {
        $value = $online ? 'online' : 'offline';
        $device['status'] = $value;
        $found = false;
        foreach ($device['params'] as &$param) {
            if ((string)($param['id'] ?? '') === 'ws') {
                $param['value'] = $value;
                $found = true;
            }
        }
        unset($param);
        if (!$found) {
            $device['params'][] = ['id' => 'ws', 'label' => 'Tunnel', 'value' => $value];
        }
        return $device;
    }

    private function deviceHasWsOnline(array $params): bool
    {
        foreach ($params as $param) {
            if ((string)($param['id'] ?? '') !== 'ws') continue;
            $value = strtolower(trim((string)($param['value'] ?? '')));
            return $value === 'online' || $value === '1' || $value === 'true';
        }
        return false;
    }

    /** @return array<string, true> */
    private function connectedDeviceKeys(): array
    {
        $keys = [];
        foreach ($this->deviceConns as $key => $_) $keys[$key] = true;
        return $keys;
    }

    private function setDeviceTunnelStatus(string $userEmail, string $deviceId, bool $online, ?array $device = null, bool $config = false): array
    {
        if ($device === null) {
            $device = $this->loadDevice($userEmail, $deviceId);
        }
        if ($device === null) {
            return [];
        }
        $device = $this->applyTunnelStatusToDevice($device, $online);
        $this->persistDevice($device, $config);
        $this->broadcastToDashboards($userEmail, $deviceId, [
            'type' => 'state_update',
            'device' => $device,
        ]);
        return $device;
    }

    private function reconcileTunnelStatus(bool $force): void
    {
        $now = microtime(true);
        if (!$force && ($now - $this->lastReconcileAt) < 30.0) {
            return;
        }
        $this->lastReconcileAt = $now;
        try {
            $db = $this->db();
            $res = $db->query("SELECT id, user_email, params FROM smarthome_devices WHERE params LIKE '%online%'");
            $cand = [];
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $userEmail = (string)($row['user_email'] ?? '');
                $deviceId = (string)($row['id'] ?? '');
                if ($userEmail === '' || $deviceId === '') continue;
                $params = json_decode($row['params'] ?: '[]', true) ?: [];
                if (!$this->deviceHasWsOnline($params)) continue;
                $cand[] = [$userEmail, $deviceId];
            }
            $local = $this->connectedDeviceKeys();
            foreach (array_chunk($cand, 1000) as $chunk) {
                $exists = shp_ws_dev_connected_many($chunk);
                foreach ($chunk as [$u, $d]) {
                    $key = $u . "\0" . $d;
                    if (isset($local[$key]) || !empty($exists[$key])) continue;
                    $this->setDeviceTunnelStatus($u, $d, false);
                }
            }
        } catch (\Throwable $e) {
            error_log('WS reconcileTunnelStatus: ' . $e->getMessage());
        }
    }

    private function persistDevice(array $device, bool $config = false): void
    {
        $id = (string)($device['id'] ?? '');
        if (!$config && shp_hot_put($id, ['status' => $device['status'] ?? 'unknown', 'local_url' => $device['local_url'] ?? '', 'controls' => $device['controls'] ?? [], 'params' => $device['params'] ?? []]) !== null) {
            return;
        }
        if ($config) {
            $this->cfgCache[$id] = [$device, microtime(true)];
            shp_hot_drop($id);
        }
        $sig = md5(json_encode([$device['name'] ?? '', $device['room'] ?? '', $device['type'] ?? '', $device['status'] ?? '', $device['local_url'] ?? '', $device['server_domain'] ?? '', $device['ap_domain'] ?? '', $device['mqtt_topic'] ?? '', $device['device_token'] ?? '', $device['notes'] ?? '', $device['icon'] ?? '', $device['controls'] ?? [], $device['params'] ?? [], $device['files'] ?? []]));
        $now = microtime(true);
        $prev = $this->persistSig[$id] ?? null;
        if ($prev && $prev[0] === $sig && ($now - $prev[1]) < 30.0) {
            return;
        }
        $this->persistSig[$id] = [$sig, $now];
        try {
            $db = $this->db();
            $stmt = $db->prepare("UPDATE smarthome_devices SET
                name = :name,
                room = :room,
                type = :type,
                status = :status,
                local_url = :local,
                server_domain = :sd,
                ap_domain = :ad,
                mqtt_topic = :mqtt,
                device_token = :tok,
                notes = :notes,
                icon = :icon,
                controls = :ctrls,
                params = :params,
                files = :files,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id");
            $stmt->bindValue(':name', $device['name'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':room', $device['room'] ?? 'Home', PDO::PARAM_STR);
            $stmt->bindValue(':type', $device['type'] ?? 'generic_sensor', PDO::PARAM_STR);
            $stmt->bindValue(':status', $device['status'] ?? 'unknown', PDO::PARAM_STR);
            $stmt->bindValue(':local', $device['local_url'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':sd', $device['server_domain'] ?? (function_exists('app_base_domain') ? app_base_domain() : 'localhost'), PDO::PARAM_STR);
            $stmt->bindValue(':ad', $device['ap_domain'] ?? (function_exists('app_base_domain') ? app_base_domain() : 'localhost'), PDO::PARAM_STR);
            $stmt->bindValue(':mqtt', $device['mqtt_topic'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':tok', $device['device_token'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':notes', $device['notes'] ?? '', PDO::PARAM_STR);
            $stmt->bindValue(':icon', $device['icon'] ?? 'smart-device', PDO::PARAM_STR);
            $stmt->bindValue(':ctrls', json_encode($device['controls'] ?? [], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmt->bindValue(':params', json_encode($device['params'] ?? [], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmt->bindValue(':files', json_encode($device['files'] ?? [], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmt->bindValue(':id', $device['id'] ?? '', PDO::PARAM_STR);
            $stmt->execute();
        } catch (\Throwable $e) {
            error_log('WS persistDevice: ' . $e->getMessage());
        }
    }

    private function controlsMap(array $device): array
    {
        $controls = [];
        foreach (($device['controls'] ?? []) as $control) {
            $controls[$control['id']] = $control['value'] ?? null;
        }
        return $controls;
    }

    private function normalizeParamValue(array $param, mixed $value): mixed
    {
        if (($param['type'] ?? '') === 'chart') {
            if (is_array($value)) {
                $out = [];
                foreach ($value as $v) {
                    if (is_numeric($v)) {
                        $out[] = (float)$v;
                    }
                }
                return $out;
            }
            $decoded = json_decode((string)$value, true);
            return is_array($decoded) ? $this->normalizeParamValue($param, $decoded) : [];
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string)$value;
    }

    private function pendingControlIds(string $deviceId): array
    {
        return shp_cq_pending($this->db(), $deviceId, microtime(true));
    }

    private function broadcastToDashboards(string $userEmail, string $deviceId, array $payload): void
    {
        $this->deliverToLocalDashboards($userEmail, $deviceId, $payload);
        if (count(shp_ws_live_ports()) < 2) return;
        foreach (shp_ws_dash_ports($userEmail, $deviceId) as $port) {
            if ($port !== $this->port) shp_ws_push($port, ['kind' => 'dash', 'user' => $userEmail, 'device' => $deviceId, 'payload' => $payload]);
        }
    }

    private function deliverToLocalDashboards(string $userEmail, string $deviceId, array $payload): void
    {
        if (empty($this->dashCount[$userEmail . "\0" . $deviceId])) return;
        foreach ($this->clients as $client) {
            $state = $this->clients[$client];
            if (($state['role'] ?? null) !== 'dashboard') continue;
            if (($state['user_email'] ?? null) !== $userEmail || ($state['device_id'] ?? null) !== $deviceId) continue;
            $this->sendJson($client, $payload);
        }
    }

    private function localDevice(string $userEmail, string $deviceId): ?ConnectionInterface
    {
        return $this->deviceConns[$userEmail . "\0" . $deviceId] ?? null;
    }

    private function forwardToDevice(string $userEmail, string $deviceId, array $payload, ?array $mark = null): bool
    {
        $owner = shp_ws_dev_get($userEmail, $deviceId);
        if ($owner === null || $owner === $this->port || !in_array($owner, shp_ws_live_ports(), true)) return false;
        shp_ws_push($owner, ['kind' => 'to_device', 'user' => $userEmail, 'device' => $deviceId, 'payload' => $payload, 'mark' => $mark]);
        return true;
    }

    public function drainInbox(): void
    {
        foreach (shp_ws_drain($this->port) as $m) {
            $kind = (string)($m['kind'] ?? '');
            $u = (string)($m['user'] ?? '');
            $d = (string)($m['device'] ?? '');
            if ($kind === 'dash') {
                $this->deliverToLocalDashboards($u, $d, (array)($m['payload'] ?? []));
            } elseif ($kind === 'to_device') {
                $c = $this->localDevice($u, $d);
                if ($c) {
                    $this->sendJson($c, (array)($m['payload'] ?? []));
                    if (!empty($m['mark'])) $this->markControlPending($u, $d, (string)$m['mark'][0], $m['mark'][1] ?? null);
                }
            } elseif ($kind === 'close_device') {
                $c = $this->localDevice($u, $d);
                if ($c) {
                    unset($this->deviceConns[$u . "\0" . $d]);
                    try { $c->close(); } catch (\Throwable $e) {}
                }
            }
        }
    }

    private function beatTick(): void
    {
        $now = microtime(true);
        if ($now - $this->lastBeatAt >= 2.0) {
            $this->lastBeatAt = $now;
            $dash = 0;
            foreach ($this->dashCount as $n) $dash += $n;
            shp_ws_proc_beat($this->port, ['clients' => count($this->clients), 'devices' => count($this->deviceConns), 'dashboards' => $dash, 'tick_max_ms' => (int)($this->tickMax * 1000), 'mem_mb' => (int)(memory_get_usage(true) / 1048576)]);
            $this->tickMax = 0.0;
            $this->isLeader = shp_ws_is_leader($this->port);
            $boots = shp_ws_live_boots();
            if ($this->prevBoots && $boots !== $this->prevBoots) {
                foreach ($this->prevBoots as $p => $b) {
                    if (($boots[$p] ?? null) !== $b) {
                        $this->lastReconcileAt = 0.0;
                        break;
                    }
                }
            }
            $this->prevBoots = $boots;
        }
        if ($now - $this->lastTouchAt >= 10.0) {
            $this->lastTouchAt = $now;
            $pairs = [];
            foreach ($this->deviceConns as $key => $_) $pairs[] = explode("\0", $key, 2);
            foreach (array_chunk($pairs, 1000) as $chunk) shp_ws_dev_touch($chunk, $this->port);
        }
    }

    private function sendJson(ConnectionInterface $conn, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json !== false) {
            $conn->send($json);
        }
    }

    private function storeControlResult(string $requestId, array $result): void
    {
        $result['updated_at'] = microtime(true);
        shp_cr_put($requestId, $result);
    }

    private function processControlQueue(): void
    {
        $now = microtime(true);
        if (!shp_redis() && ($now - $this->lastQueuePollAt) < 1.0) {
            return;
        }
        $this->lastQueuePollAt = $now;
        try {
            $db = $this->db();
            $items = shp_cq_list($db, $now);
            if (!$items) {
                return;
            }
            $isLeader = $this->isLeader;
            foreach ($items as $item) {
                $requestId = (string)$item['id'];
                $item['value'] = json_decode($item['value_json'] ?? 'null', true);
                $item['expires_at'] = (float)$item['expires_at'];
                $userEmail = (string)($item['user_email'] ?? '');
                $deviceId = (string)($item['device_id'] ?? '');
                $controlId = trim((string)($item['control_id'] ?? ''));
                $deviceConn = $this->localDevice($userEmail, $deviceId);
                if ($deviceConn === null) {
                    $owner = shp_ws_dev_get($userEmail, $deviceId);
                    if ($owner !== null && $owner !== $this->port && in_array($owner, shp_ws_live_ports(), true)) {
                        continue;
                    }
                    if (!$isLeader) {
                        continue;
                    }
                }
                if ($item['expires_at'] < $now) {
                    $device = $this->loadDevice($userEmail, $deviceId);
                    $this->logError($userEmail, $deviceId, (string)($device['name'] ?? ''), 'control_queue', 'Device control timeout', [
                        'control_id' => $controlId,
                        'request_id' => $requestId,
                    ]);
                    $this->storeControlResult($requestId, ['status' => 'error', 'message' => 'Device control timeout']);
                    shp_cq_del($db, $requestId);
                    shp_metric('control_timeouts');
                    continue;
                }
                if ($userEmail === '' || $deviceId === '' || $controlId === '') {
                    $this->storeControlResult($requestId, ['status' => 'error', 'message' => 'Invalid control request']);
                    shp_cq_del($db, $requestId);
                    continue;
                }
                if ($deviceConn === null) {
                    continue;
                }
                $device = $this->loadDevice($userEmail, $deviceId);
                if ($device === null) {
                    $this->storeControlResult($requestId, ['status' => 'error', 'message' => 'Device record not found']);
                    shp_cq_del($db, $requestId);
                    continue;
                }
                $matched = false;
                foreach ($device['controls'] as &$control) {
                    if (($control['id'] ?? '') !== $controlId) {
                        continue;
                    }
                    $matched = true;
                    if (($control['type'] ?? '') === 'switch') {
                        $control['value'] = (bool)($item['value'] ?? false);
                    } elseif (($control['type'] ?? '') === 'range') {
                        $control['value'] = (int)($item['value'] ?? 0);
                    } else {
                        $control['value'] = (string)($item['value'] ?? '');
                    }
                }
                unset($control);
                if (!$matched) {
                    $this->storeControlResult($requestId, ['status' => 'error', 'message' => 'Unknown control']);
                    shp_cq_del($db, $requestId);
                    continue;
                }
                $this->persistDevice($device);
                $this->sendJson($deviceConn, [
                    'type' => 'command',
                    'control_id' => $controlId,
                    'value' => $item['value'] ?? null,
                    'controls' => $this->controlsMap($device),
                ]);
                $this->broadcastToDashboards($userEmail, $deviceId, [
                    'type' => 'state_update',
                    'device' => $device,
                ]);
                $this->storeControlResult($requestId, ['status' => 'ok', 'device' => $device]);
                shp_cq_del($db, $requestId);
                shp_metric('control_delivered');
            }
        } catch (\Throwable $e) {
            error_log('WS processControlQueue: ' . $e->getMessage());
        }
    }
}
