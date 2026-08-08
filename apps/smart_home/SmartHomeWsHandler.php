<?php

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class SmartHomeWsHandler implements MessageComponentInterface
{
    private string $appDir;
    private \SplObjectStorage $clients;
    private bool $startupReconcileDone = false;
    private float $lastReconcileAt = 0.0;

    public function __construct(string $appDir)
    {
        $this->appDir = rtrim($appDir, '/');
        
        $this->clients = new \SplObjectStorage();
    }

    private function db(): AppDatabase
    {
        require_once dirname($this->appDir, 2) . '/lib/app_db.php';
        $db = app_main_db();
        $db->enableExceptions(true);
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

    private function shouldApplyDefinition(array $device): bool
    {
        return ((string)($device['type'] ?? '')) === 'pending';
    }

    private function monitorPurge(AppDatabase $db): void
    {
        try {
            $db->exec("DELETE FROM smarthome_monitor_logs WHERE created_at::timestamptz < CURRENT_TIMESTAMP - interval '30 days'");
        } catch (\Throwable $e) {
            error_log('WS monitorPurge logs: ' . $e->getMessage());
        }
    }

    private function errorPurge(AppDatabase $db): void
    {
        try {
            $db->exec("DELETE FROM smarthome_errors WHERE created_at::timestamptz < CURRENT_TIMESTAMP - interval '30 days'");
        } catch (\Throwable $e) {
            error_log('WS monitorPurge errors: ' . $e->getMessage());
        }
    }

    private function monitorLog(string $email, ?string $deviceId, string $eventType, array $data): void
    {
        try {
            $db = $this->db();
            $this->monitorPurge($db);
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
            $db = $this->db();
            $this->errorPurge($db);
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
        $this->clients->attach($conn, [
            'role' => null,
            'user_email' => null,
            'device_id' => null,
            'device_token' => null,
            'connected_at' => microtime(true),
        ]);
    }

    public function onMessage(ConnectionInterface $conn, $msg): void
    {
        if (!$this->clients->contains($conn)) {
            return;
        }
        $state = $this->clients[$conn];
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
        if (!$this->clients->contains($conn)) {
            return;
        }
        $state = $this->clients[$conn];
        if (($state['role'] ?? null) === 'device' && !empty($state['user_email']) && !empty($state['device_id'])) {
            $this->setDeviceTunnelStatus((string)$state['user_email'], (string)$state['device_id'], false);
        }
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->sendJson($conn, ['type' => 'error', 'message' => $e->getMessage()]);
        $conn->close();
    }

    public function tick(): void
    {
        try {
            $db = $this->db();
            $db->exec('DELETE FROM smarthome_ws_tokens WHERE expires_at < ' . time());
            $db->exec('DELETE FROM smarthome_control_queue WHERE expires_at < ' . microtime(true));
            $this->processControlQueue();
            $this->heartbeatTick();
            if (!$this->startupReconcileDone) {
                $this->startupReconcileDone = true;
                $this->reconcileTunnelStatus(true);
            } else {
                $this->reconcileTunnelStatus(false);
            }
        } catch (\Throwable $e) {
            error_log('WS tick: ' . $e->getMessage());
        }
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

    private function handleAuth(ConnectionInterface $conn, array $payload): void
    {
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
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Unknown device token']);
            return;
        }
        $userEmail = (string)($row['user_email'] ?? '');
        $deviceId = (string)($row['device_id'] ?? '');
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
        $duplicates = [];
        foreach ($this->clients as $other) {
            if ($other === $conn) continue;
            $os = $this->clients[$other];
            if (($os['role'] ?? null) === 'device'
                && (string)($os['user_email'] ?? '') === $userEmail
                && (string)($os['device_id'] ?? '') === $deviceId) {
                $duplicates[] = $other;
            }
        }
        foreach ($duplicates as $dup) {
            try { $dup->close(); } catch (\Throwable $e) {}
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
        $device = $this->setDeviceTunnelStatus($userEmail, $deviceId, true, $device);
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
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Invalid dashboard token']);
            return;
        }
        $state = $this->clients[$conn];
        $state['role'] = 'dashboard';
        $state['user_email'] = (string)($meta['user_email'] ?? '');
        $state['device_id'] = (string)($meta['device_id'] ?? '');
        $this->clients[$conn] = $state;
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
                    $param['value'] = (string)$payload['telemetry'][$param['id']];
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
        $this->persistDevice($device);
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
        foreach ($this->clients as $client) {
            $clientState = $this->clients[$client];
            if (($clientState['role'] ?? null) === 'device'
                && ($clientState['user_email'] ?? null) === $state['user_email']
                && ($clientState['device_id'] ?? null) === $state['device_id']) {
                $this->sendJson($client, [
                    'type' => 'command',
                    'control_id' => $controlId,
                    'value' => $payload['value'] ?? null,
                    'controls' => $this->controlsMap($device),
                ]);
                $matched++;
            }
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
        $matched = 0;
        foreach ($this->clients as $client) {
            $clientState = $this->clients[$client];
            if (($clientState['role'] ?? null) === 'device'
                && ($clientState['user_email'] ?? null) === $state['user_email']
                && ($clientState['device_id'] ?? null) === $state['device_id']) {
                $this->sendJson($client, $payload);
                $matched++;
            }
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
        $this->broadcastToDashboards((string)$state['user_email'], (string)$state['device_id'], $payload);
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
            $db = $this->db();
            $stmt = $db->prepare('SELECT * FROM smarthome_devices WHERE id = :id AND user_email = :em');
            $stmt->bindValue(':id', $deviceId, PDO::PARAM_STR);
            $stmt->bindValue(':em', $userEmail, PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->rowToArray($row) : null;
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
        foreach ($this->clients as $client) {
            $state = $this->clients[$client];
            if (($state['role'] ?? null) !== 'device' || empty($state['device_id'])) continue;
            $keys[(string)$state['user_email'] . "\0" . (string)$state['device_id']] = true;
        }
        return $keys;
    }

    private function setDeviceTunnelStatus(string $userEmail, string $deviceId, bool $online, ?array $device = null): array
    {
        if ($device === null) {
            $device = $this->loadDevice($userEmail, $deviceId);
        }
        if ($device === null) {
            return [];
        }
        $device = $this->applyTunnelStatusToDevice($device, $online);
        $this->persistDevice($device);
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
        $connected = $this->connectedDeviceKeys();
        try {
            $db = $this->db();
            $res = $db->query("SELECT id, user_email, status, params FROM smarthome_devices");
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $userEmail = (string)($row['user_email'] ?? '');
                $deviceId = (string)($row['id'] ?? '');
                if ($userEmail === '' || $deviceId === '') continue;
                $params = json_decode($row['params'] ?: '[]', true) ?: [];
                if (!$this->deviceHasWsOnline($params)) continue;
                $key = $userEmail . "\0" . $deviceId;
                if (isset($connected[$key])) continue;
                $this->setDeviceTunnelStatus($userEmail, $deviceId, false);
            }
        } catch (\Throwable $e) {
            error_log('WS reconcileTunnelStatus: ' . $e->getMessage());
        }
    }

    private function persistDevice(array $device): void
    {
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
            $stmt->bindValue(':sd', $device['server_domain'] ?? 'serverkherson.xyz', PDO::PARAM_STR);
            $stmt->bindValue(':ad', $device['ap_domain'] ?? 'serverkherson.xyz', PDO::PARAM_STR);
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

    private function pendingControlIds(string $deviceId): array
    {
        $ids = [];
        $now = microtime(true);
        $db = $this->db();
        $stmt = $db->prepare('SELECT control_id FROM smarthome_control_queue WHERE device_id = :did AND expires_at > :now');
        $stmt->bindValue(':did', $deviceId, PDO::PARAM_STR);
        $stmt->bindValue(':now', $now, PDO::PARAM_STR);
        $res = $stmt->execute();
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $ids[] = (string)($row['control_id'] ?? '');
        }
        return $ids;
    }

    private function broadcastToDashboards(string $userEmail, string $deviceId, array $payload): void
    {
        foreach ($this->clients as $client) {
            $state = $this->clients[$client];
            if (($state['role'] ?? null) !== 'dashboard') {
                continue;
            }
            if (($state['user_email'] ?? null) !== $userEmail || ($state['device_id'] ?? null) !== $deviceId) {
                continue;
            }
            $this->sendJson($client, $payload);
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
        $db = $this->db();
        $result['updated_at'] = microtime(true);
        $ins = $db->prepare('INSERT INTO smarthome_control_results (id, result_json, updated_at) VALUES (:id, :rj, :ua)');
        $ins->bindValue(':id', $requestId, PDO::PARAM_STR);
        $ins->bindValue(':rj', json_encode($result, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $ins->bindValue(':ua', microtime(true), PDO::PARAM_STR);
        $ins->execute();
    }

    private function processControlQueue(): void
    {
        try {
            $db = $this->db();
        $now = microtime(true);
        $items = [];
        $res = $db->query('SELECT * FROM smarthome_control_queue');
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $row;
        }
        unset($res);

        foreach ($items as $item) {
            $requestId = (string)$item['id'];
            $item['value'] = json_decode($item['value_json'] ?? 'null', true);
            $item['expires_at'] = (float)$item['expires_at'];
            $userEmail = (string)($item['user_email'] ?? '');
            $deviceId = (string)($item['device_id'] ?? '');
            $controlId = trim((string)($item['control_id'] ?? ''));
            $visibleDevices = [];
            foreach ($this->clients as $client) {
                $state = $this->clients[$client];
                if (($state['role'] ?? null) === 'device') {
                    $visibleDevices[] = [
                        'user_email' => (string)($state['user_email'] ?? ''),
                        'device_id' => (string)($state['device_id'] ?? ''),
                    ];
                }
            }
            if (($item['expires_at'] ?? 0) < $now) {
                $device = $this->loadDevice($userEmail, $deviceId);
                $this->logError($userEmail, $deviceId, (string)($device['name'] ?? ''), 'control_queue', 'Device control timeout', [
                    'control_id' => $controlId,
                    'request_id' => $requestId,
                ]);
                $this->storeControlResult((string)$requestId, [
                    'status' => 'error',
                    'message' => 'Device control timeout',
                    'visible_devices' => $visibleDevices,
                ]);
                $d = $db->prepare('DELETE FROM smarthome_control_queue WHERE id = :id');
                $d->bindValue(':id', $requestId, PDO::PARAM_STR);
                $d->execute();
                continue;
            }

            if ($userEmail === '' || $deviceId === '' || $controlId === '') {
                $this->storeControlResult((string)$requestId, [
                    'status' => 'error',
                    'message' => 'Invalid control request',
                ]);
                $d = $db->prepare('DELETE FROM smarthome_control_queue WHERE id = :id');
                $d->bindValue(':id', $requestId, PDO::PARAM_STR);
                $d->execute();
                continue;
            }

            $deviceConn = null;
            foreach ($this->clients as $client) {
                $state = $this->clients[$client];
                if (($state['role'] ?? null) === 'device'
                    && ($state['user_email'] ?? '') === $userEmail
                    && ($state['device_id'] ?? '') === $deviceId) {
                    $deviceConn = $client;
                    break;
                }
            }
            if ($deviceConn === null) {
                continue;
            }

            $device = $this->loadDevice($userEmail, $deviceId);
            if ($device === null) {
                $this->storeControlResult((string)$requestId, [
                    'status' => 'error',
                    'message' => 'Device record not found',
                ]);
                $d = $db->prepare('DELETE FROM smarthome_control_queue WHERE id = :id');
                $d->bindValue(':id', $requestId, PDO::PARAM_STR);
                $d->execute();
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
                $this->storeControlResult((string)$requestId, [
                    'status' => 'error',
                    'message' => 'Unknown control',
                ]);
                $d = $db->prepare('DELETE FROM smarthome_control_queue WHERE id = :id');
                $d->bindValue(':id', $requestId, PDO::PARAM_STR);
                $d->execute();
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
            $this->storeControlResult((string)$requestId, [
                'status' => 'ok',
                'device' => $device,
            ]);
            $d = $db->prepare('DELETE FROM smarthome_control_queue WHERE id = :id');
            $d->bindValue(':id', $requestId, PDO::PARAM_STR);
            $d->execute();
        }
        } catch (\Throwable $e) {
            error_log('WS processControlQueue: ' . $e->getMessage());
        }
    }
}
