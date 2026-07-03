# Viewer Application - Detailed Documentation

**Path:** `apps/viewer/`  
**Total Lines of Code:** ~1,700  
**Main Entry:** `index.php` (1,683 lines)

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Core Components](#core-components)
4. [Features](#features)
5. [API Reference](#api-reference)
6. [Frontend Architecture](#frontend-architecture)
7. [Dashboard Integration](#dashboard-integration)

---

## Overview

The Viewer application is a **network device monitoring and viewing** system with device discovery, status monitoring, view history tracking, and dashboard integration.

### Key Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 1 |
| Total JSON Files | 3 |
| Languages Supported | 4 (EN, UK, RU, BE) |
| Main File Size | 1,683 lines |
| Dashboard Token | Yes |

---

## File Structure

```
apps/viewer/
├── index.php                          # Device viewer interface (1,683 lines)
├── name.json                          # App name translations
├── config.json                        # Application configuration
├── history.json                       # View history tracking
├── dashboard_token.txt                # Dashboard authentication token
├── icon.png                           # Application icon
└── Thumbs.db                          # Windows thumbnail cache
```

---

## Core Components

### 1. Main Interface (`index.php`)

**Purpose:** Network device viewer with status monitoring and dashboard integration.

**Key Functions:**

```php
// Database connection (shared)
function getDB(): ?SQLite3 {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_FILE);
        $db->enableExceptions(true);
        $db->busyTimeout(3000);
    }
    return $db;
}

// User authentication
function getUserRole(): int {
    $db = getDB();
    if (!$db) return 0;
    
    $session_id = session_id();
    try {
        $sql = "SELECT u.role
                FROM active_sessions s
                JOIN users u ON s.email = u.email
                WHERE s.session_id = :session_id";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':session_id', $session_id, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row && isset($row['role'])) {
            return (int)$row['role'];
        }
        
        if (isset($_COOKIE['PHPSESSID'])) {
            $cookie_session = $_COOKIE['PHPSESSID'];
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':session_id', $cookie_session, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['role'])) {
                return (int)$row['role'];
            }
        }
    } catch (Exception $e) {
        error_log("Database query failed: " . $e->getMessage());
    }
    
    return 0;
}

function get_current_user_from_sessions(): string {
    $db = getDB();
    if (!$db) return 'default';
    
    $session_id = session_id();
    
    if ($session_id) {
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
            $stmt->bindValue(':session_id', $session_id, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['email'])) {
                $email = $row['email'];
                $username = explode('@', $email)[0] ?? 'user';
                $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
                return $username ?: 'default';
            }
        } catch (Exception $e) {
            error_log("Database query failed: " . $e->getMessage());
        }
    }
    
    if (isset($_COOKIE['PHPSESSID'])) {
        $cookie_session = $_COOKIE['PHPSESSID'];
        try {
            $stmt = $db->prepare('SELECT email FROM active_sessions WHERE session_id = :session_id');
            $stmt->bindValue(':session_id', $cookie_session, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row && isset($row['email'])) {
                $email = $row['email'];
                $username = explode('@', $email)[0] ?? 'user';
                $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $username);
                return $username ?: 'default';
            }
        } catch (Exception $e) {
            error_log("Database query failed: " . $e->getMessage());
        }
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $username = 'user_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ip);
    return $username;
}

// Token management
function getOrCreateToken(): string {
    $tokenFile = 'dashboard_token.txt';
    
    if (!file_exists($tokenFile)) {
        $token = bin2hex(random_bytes(16));
        file_put_contents($tokenFile, $token);
        return $token;
    }
    
    return trim(file_get_contents($tokenFile));
}

function validateToken(string $token): bool {
    $validToken = getOrCreateToken();
    return hash_equals($validToken, $token);
}

// Device operations
function loadDevices(): array {
    $configFile = 'config.json';
    
    if (!file_exists($configFile)) {
        return [];
    }
    
    return json_decode(file_get_contents($configFile), true);
}

function saveDevices(array $devices): bool {
    $configFile = 'config.json';
    
    return file_put_contents(
        $configFile,
        json_encode($devices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function addDevice(array $device): bool {
    $devices = loadDevices();
    
    $device['id'] = uniqid('device_');
    $device['created_at'] = date('c');
    $device['updated_at'] = date('c');
    $device['status'] = 'unknown';
    $device['last_seen'] = null;
    
    $devices[] = $device;
    
    return saveDevices($devices);
}

function updateDevice(string $id, array $data): bool {
    $devices = loadDevices();
    
    foreach ($devices as &$device) {
        if ($device['id'] === $id) {
            $device = array_merge($device, $data);
            $device['updated_at'] = date('c');
            break;
        }
    }
    
    return saveDevices($devices);
}

function deleteDevice(string $id): bool {
    $devices = loadDevices();
    
    $devices = array_filter($devices, fn($d) => $d['id'] !== $id);
    
    return saveDevices(array_values($devices));
}

// Device status checking
function checkDeviceStatus(array $device): array {
    $type = $device['type'] ?? 'http';
    $host = $device['host'] ?? '';
    $port = $device['port'] ?? 80;
    $path = $device['path'] ?? '/';
    $timeout = $device['timeout'] ?? 5;
    
    $result = [
        'status' => 'offline',
        'response_time' => null,
        'status_code' => null,
        'message' => '',
        'checked_at' => date('c')
    ];
    
    if (empty($host)) {
        $result['message'] = 'No host specified';
        return $result;
    }
    
    $startTime = microtime(true);
    
    switch ($type) {
        case 'http':
        case 'https':
            $url = "{$type}://{$host}:{$port}{$path}";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_NOBODY => true,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            if (curl_exec($ch) !== false) {
                $result['status'] = 'online';
                $result['status_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            } else {
                $result['message'] = curl_error($ch);
            }
            
            curl_close($ch);
            break;
        
        case 'ping':
            $command = (PHP_OS === 'WINNT') ? 'ping -n 1' : 'ping -c 1';
            $output = shell_exec("{$command} " . escapeshellarg($host) . ' 2>&1');
            
            if (strpos($output, '1 packets received') !== false || 
                strpos($output, '1 packets received') !== false ||
                preg_match('/\d+% packet loss/', $output) && !preg_match('/100% packet loss/', $output)) {
                $result['status'] = 'online';
            } else {
                $result['message'] = 'Host unreachable';
            }
            break;
        
        case 'tcp':
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if ($socket !== false) {
                $result['status'] = 'online';
                fclose($socket);
            } else {
                $result['message'] = $errstr;
            }
            break;
    }
    
    $endTime = microtime(true);
    $result['response_time'] = round(($endTime - $startTime) * 1000, 2);
    
    return $result;
}

function checkAllDevices(): array {
    $devices = loadDevices();
    $results = [];
    
    foreach ($devices as &$device) {
        $status = checkDeviceStatus($device);
        $device['status'] = $status['status'];
        $device['last_seen'] = $status['status'] === 'online' ? date('c') : ($device['last_seen'] ?? null);
        $device['response_time'] = $status['response_time'];
        $device['status_message'] = $status['message'];
        
        $results[] = [
            'device_id' => $device['id'],
            ...$status
        ];
    }
    
    saveDevices($devices);
    
    return $results;
}

// History operations
function loadHistory(): array {
    $historyFile = 'history.json';
    
    if (!file_exists($historyFile)) {
        return [];
    }
    
    return json_decode(file_get_contents($historyFile), true);
}

function addToHistory(string $deviceId, string $deviceName): void {
    $history = loadHistory();
    
    // Add to beginning
    array_unshift($history, [
        'device_id' => $deviceId,
        'device_name' => $deviceName,
        'viewed_at' => date('c')
    ]);
    
    // Keep last 100 entries
    $history = array_slice($history, 0, 100);
    
    file_put_contents(
        'history.json',
        json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function getStatistics(): array {
    $devices = loadDevices();
    
    $total = count($devices);
    $online = count(array_filter($devices, fn($d) => $d['status'] === 'online'));
    $offline = count(array_filter($devices, fn($d) => $d['status'] === 'offline'));
    $unknown = $total - $online - $offline;
    
    $avgResponseTime = 0;
    $onlineDevices = array_filter($devices, fn($d) => isset($d['response_time']));
    if (count($onlineDevices) > 0) {
        $avgResponseTime = array_sum(array_column($onlineDevices, 'response_time')) / count($onlineDevices);
    }
    
    return [
        'total' => $total,
        'online' => $online,
        'offline' => $offline,
        'unknown' => $unknown,
        'average_response_time' => round($avgResponseTime, 2)
    ];
}
```

**URL Parameters:**
```php
$lang   = $_GET['lang']   ?? 'en';
$theme  = $_GET['theme']  ?? 'light';
$color  = $_GET['color']  ?? '#85b503';
```

---

## Features

### 1. Device Management

**Device Types:**
- HTTP/HTTPS endpoints
- TCP ports
- Ping (ICMP)
- Custom services

**Device Properties:**
- Name
- Host/IP address
- Port
- Path (for HTTP)
- Check interval
- Timeout
- Tags/Categories
- Notes

### 2. Status Monitoring

**Status Checks:**
- HTTP status code
- Response time
- TCP connection test
- Ping test
- SSL certificate expiry (optional)

**Status Indicators:**
- 🟢 Online
- 🔴 Offline
- 🟡 Unknown
- ⚪ Not checked

### 3. History Tracking

**Features:**
- View history (last 100 viewed devices)
- Timestamp for each view
- Quick access to recently viewed devices
- Auto-cleanup of old entries

### 4. Dashboard Integration

**Token Authentication:**
- Secure token for dashboard access
- Token stored in `dashboard_token.txt`
- Token validation for API access

**Dashboard View:**
- Summary statistics
- Device status overview
- Response time charts
- Alert notifications

### 5. Statistics

**Metrics:**
- Total devices
- Online count
- Offline count
- Unknown status count
- Average response time
- Uptime percentage

---

## API Reference

### Device Endpoints

#### Get All Devices
```http
GET /index.php?action=get_devices

Response:
{
    "success": true,
    "devices": [
        {
            "id": "device_001",
            "name": "Web Server",
            "type": "http",
            "host": "example.com",
            "port": 443,
            "path": "/",
            "status": "online",
            "response_time": 45.2,
            "last_seen": "2024-01-15T10:30:00Z",
            "created_at": "2024-01-01T00:00:00Z"
        }
    ]
}
```

#### Get Single Device
```http
GET /index.php?action=get_device&id=device_001

Response:
{
    "success": true,
    "device": { ... }
}
```

#### Add Device
```http
POST /index.php?action=add_device
Content-Type: application/json

{
    "name": "New Device",
    "type": "http",
    "host": "newserver.com",
    "port": 80,
    "path": "/health",
    "timeout": 5
}
```

#### Update Device
```http
POST /index.php?action=update_device
Content-Type: application/json

{
    "id": "device_001",
    "name": "Updated Name",
    "timeout": 10
}
```

#### Delete Device
```http
POST /index.php?action=delete_device
Content-Type: application/json

{
    "id": "device_001"
}
```

---

### Status Check Endpoints

#### Check All Devices
```http
POST /index.php?action=check_all

Response:
{
    "success": true,
    "results": [
        {
            "device_id": "device_001",
            "status": "online",
            "response_time": 45.2,
            "status_code": 200,
            "message": "",
            "checked_at": "2024-01-15T10:30:00Z"
        }
    ]
}
```

#### Check Single Device
```http
POST /index.php?action=check_device
Content-Type: application/json

{
    "id": "device_001"
}
```

---

### Statistics Endpoints

#### Get Statistics
```http
GET /index.php?action=statistics

Response:
{
    "success": true,
    "statistics": {
        "total": 10,
        "online": 8,
        "offline": 1,
        "unknown": 1,
        "average_response_time": 52.3
    }
}
```

---

### History Endpoints

#### Get History
```http
GET /index.php?action=history

Response:
{
    "success": true,
    "history": [
        {
            "device_id": "device_001",
            "device_name": "Web Server",
            "viewed_at": "2024-01-15T10:30:00Z"
        }
    ]
}
```

#### Clear History
```http
POST /index.php?action=clear_history
```

---

### Dashboard Endpoints

#### Dashboard Data (Token Auth)
```http
GET /index.php?action=dashboard
X-Dashboard-Token: {token}

Response:
{
    "success": true,
    "dashboard": {
        "statistics": { ... },
        "devices": [ ... ],
        "alerts": [ ... ]
    }
}
```

#### Validate Token
```http
POST /index.php?action=validate_token
Content-Type: application/json

{
    "token": "abc123..."
}

Response:
{
    "success": true,
    "valid": true
}
```

#### Generate New Token
```http
POST /index.php?action=generate_token

Response:
{
    "success": true,
    "token": "new_token_here"
}
```

---

## Frontend Architecture

### Main JavaScript Modules

```javascript
// Device Manager
class DeviceManager {
    constructor() {
        this.devices = [];
        this.statistics = {};
        this.refreshInterval = 30000; // 30 seconds
    }
    
    async loadDevices() {
        const response = await fetch('?action=get_devices');
        const data = await response.json();
        this.devices = data.devices;
        this.render();
    }
    
    async addDevice(device) {
        const response = await fetch('?action=add_device', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(device)
        });
        
        const data = await response.json();
        if (data.success) {
            await this.loadDevices();
        }
    }
    
    async updateDevice(id, data) {
        const response = await fetch('?action=update_device', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, ...data })
        });
        
        const data = await response.json();
        if (data.success) {
            await this.loadDevices();
        }
    }
    
    async deleteDevice(id) {
        if (!confirm('Are you sure you want to delete this device?')) return;
        
        const response = await fetch('?action=delete_device', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const data = await response.json();
        if (data.success) {
            await this.loadDevices();
        }
    }
    
    async checkAll() {
        const response = await fetch('?action=check_all', {
            method: 'POST'
        });
        
        const data = await response.json();
        if (data.success) {
            await this.loadDevices();
            await this.loadStatistics();
        }
    }
    
    render() {
        this.renderDeviceList();
        this.renderStatistics();
    }
    
    renderDeviceList() {
        const container = document.getElementById('devices-list');
        
        container.innerHTML = this.devices.map(device => `
            <div class="device-card status-${device.status}" data-id="${device.id}">
                <div class="device-header">
                    <h3>${device.name}</h3>
                    <span class="status-badge ${device.status}">${device.status}</span>
                </div>
                <div class="device-info">
                    <div>Type: ${device.type}</div>
                    <div>Host: ${device.host}:${device.port}</div>
                    ${device.path ? `<div>Path: ${device.path}</div>` : ''}
                    ${device.response_time ? `<div>Response: ${device.response_time}ms</div>` : ''}
                    <div>Last seen: ${device.last_seen ? new Date(device.last_seen).toLocaleString() : 'Never'}</div>
                </div>
                <div class="device-actions">
                    <button onclick="deviceManager.viewDevice('${device.id}')">View</button>
                    <button onclick="deviceManager.editDevice('${device.id}')">Edit</button>
                    <button onclick="deviceManager.deleteDevice('${device.id}')">Delete</button>
                </div>
            </div>
        `).join('');
    }
    
    renderStatistics() {
        const online = this.devices.filter(d => d.status === 'online').length;
        const offline = this.devices.filter(d => d.status === 'offline').length;
        const total = this.devices.length;
        
        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-online').textContent = online;
        document.getElementById('stat-offline').textContent = offline;
    }
    
    viewDevice(id) {
        const device = this.devices.find(d => d.id === id);
        if (device) {
            // Record in history
            fetch('?action=add_history', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    device_id: id,
                    device_name: device.name
                })
            });
            
            // Show device details
            this.showDeviceModal(device);
        }
    }
    
    showDeviceModal(device) {
        const modal = document.getElementById('device-modal');
        document.getElementById('modal-device-name').textContent = device.name;
        document.getElementById('modal-device-type').textContent = device.type;
        document.getElementById('modal-device-host').textContent = `${device.host}:${device.port}`;
        document.getElementById('modal-device-status').textContent = device.status;
        document.getElementById('modal-device-response').textContent = device.response_time ? `${device.response_time}ms` : 'N/A';
        
        modal.classList.add('active');
    }
    
    editDevice(id) {
        const device = this.devices.find(d => d.id === id);
        if (device) {
            // Show edit form
            document.getElementById('edit-device-id').value = device.id;
            document.getElementById('edit-device-name').value = device.name;
            document.getElementById('edit-device-type').value = device.type;
            document.getElementById('edit-device-host').value = device.host;
            document.getElementById('edit-device-port').value = device.port;
            
            document.getElementById('edit-modal').classList.add('active');
        }
    }
    
    async loadStatistics() {
        const response = await fetch('?action=statistics');
        const data = await response.json();
        this.statistics = data.statistics;
    }
}

// Initialize
const deviceManager = new DeviceManager();

document.addEventListener('DOMContentLoaded', () => {
    deviceManager.loadDevices();
    
    // Auto-refresh every 30 seconds
    setInterval(() => {
        deviceManager.checkAll();
    }, deviceManager.refreshInterval);
});
```

---

## Dashboard Integration

### Dashboard Widget Example

```javascript
// Dashboard widget that uses viewer API
class ViewerDashboardWidget {
    constructor(token) {
        this.token = token;
        this.apiBase = '/apps/viewer/index.php';
    }
    
    async loadDashboard() {
        const response = await fetch(`${this.apiBase}?action=dashboard`, {
            headers: {
                'X-Dashboard-Token': this.token
            }
        });
        
        const data = await response.json();
        if (data.success) {
            this.render(data.dashboard);
        }
    }
    
    render(dashboard) {
        const { statistics, devices } = dashboard;
        
        document.getElementById('viewer-total').textContent = statistics.total;
        document.getElementById('viewer-online').textContent = statistics.online;
        document.getElementById('viewer-offline').textContent = statistics.offline;
        
        // Render mini device list
        const container = document.getElementById('viewer-devices');
        container.innerHTML = devices.slice(0, 5).map(device => `
            <div class="mini-device">
                <span class="status-dot ${device.status}"></span>
                <span>${device.name}</span>
                <span>${device.response_time}ms</span>
            </div>
        `).join('');
    }
}
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Devices not loading | Config file missing | Create config.json |
| Status check fails | Timeout too short | Increase timeout value |
| Dashboard auth fails | Token mismatch | Regenerate token |
| History not saving | Permission denied | Check file permissions |

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
