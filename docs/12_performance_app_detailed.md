# Performance Application - Detailed Documentation

**Path:** `apps/performance/`  
**Total Lines of Code:** ~2,400  
**Main Entry:** `index.php` (1,848 lines)  
**Monitor API:** `server_monitor.php` (547 lines)

---

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Core Components](#core-components)
4. [API Reference](#api-reference)
5. [Metrics Collected](#metrics-collected)
6. [Frontend Architecture](#frontend-architecture)
7. [Caching System](#caching-system)

---

## Overview

The Performance application is a **real-time server monitoring dashboard** that displays CPU, memory, disk, and network metrics. It provides visual graphs and statistics for system health monitoring.

### Key Statistics

| Metric | Value |
|--------|-------|
| Total PHP Files | 3 |
| Total JSON Files | 2 |
| Languages Supported | 4 (EN, UK, RU, BE) |
| Cache TTL | 5 seconds (default) |
| Metrics Endpoints | 6 |

---

## File Structure

```
apps/performance/
├── index.php                          # Dashboard UI (1,848 lines)
├── server_monitor.php                 # Metrics API endpoint (547 lines)
├── translations.php                   # UI translations (PHP array)
├── name.json                          # App name translations
├── style.css                          # Dashboard styles
├── icon.png                           # Application icon
├── Thumbs.db                          # Windows thumbnail cache
├── modules_cache.json                 # Apache modules cache
│
└── cache/                             # Cached metrics data
    ├── {hash}.cache                   # Metric cache files
    └── modules_cache.json             # Apache modules cache copy
```

---

## Core Components

### 1. Dashboard UI (`index.php`)

**Purpose:** Real-time monitoring dashboard with charts and metrics display.

**Key Functions:**

```php
// Database connection
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
function get_current_user_from_sessions(): string
function getUserRoleByEmail(string $email): int
function isUserRoot(): bool

// Translations
function t(string $key, string $lang = 'en'): string
```

**URL Parameters:**
```php
$lang   = $_GET['lang']   ?? 'en';
$theme  = $_GET['theme']  ?? 'light';
$color  = $_GET['color']  ?? '#85b503';
```

---

### 2. Server Monitor API (`server_monitor.php`)

**Purpose:** Collects and returns system metrics.

**Key Functions:**

```php
/**
 * Get CPU load percentage using wmic command
 */
function getCpuUsage(): float {
    $output = @shell_exec('wmic cpu get loadpercentage 2>&1');
    if ($output) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            $line = trim($line);
            if (is_numeric($line)) {
                return (float)$line;
            }
        }
    }
    return 0.0;
}

/**
 * Get RAM usage percentage
 */
function getMemoryUsage(): float {
    $output = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value 2>&1');
    if ($output) {
        $total = 0;
        $free = 0;
        
        if (preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $matches)) {
            $total = (float)$matches[1];
        }
        if (preg_match('/FreePhysicalMemory=(\d+)/', $output, $matches)) {
            $free = (float)$matches[1];
        }
        
        if ($total > 0) {
            return round((($total - $free) / $total) * 100, 1);
        }
    }
    return 0.0;
}

/**
 * Get Virtual Memory (swap) usage percentage
 */
function getSwapUsage(): float {
    $output = @shell_exec('systeminfo | find "Virtual Memory" 2>&1');
    if ($output) {
        $lines = explode("\n", $output);
        $maxSize = 0;
        $inUse = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, 'Max Size:') !== false) {
                if (preg_match('/Max Size:\s*([\d\s,]+)\s*MB/i', $line, $matches)) {
                    $maxSize = (float)str_replace([' ', ','], '', $matches[1]);
                }
            }
            if (strpos($line, 'In Use:') !== false) {
                if (preg_match('/In Use:\s*([\d\s,]+)\s*MB/i', $line, $matches)) {
                    $inUse = (float)str_replace([' ', ','], '', $matches[1]);
                }
            }
        }
        
        if ($maxSize > 0) {
            return round(($inUse / $maxSize) * 100, 1);
        }
    }
    return 0.0;
}

/**
 * Get drive information
 */
function getDrives(): array {
    $drives = [];
    $output = @shell_exec('wmic logicaldisk where drivetype=3 get DeviceID,Size,FreeSpace /format:csv 2>&1');
    
    if ($output) {
        $lines = explode("\n", trim($output));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !preg_match('/^Node,/', $line)) {
                $parts = explode(',', $line);
                if (count($parts) >= 4) {
                    $letter = str_replace(['"', ':'], '', $parts[1]);
                    $freeSpace = (float)$parts[2];
                    $totalSpace = (float)$parts[3];
                    
                    if ($totalSpace > 0) {
                        $usedSpace = $totalSpace - $freeSpace;
                        $usagePercent = round(($usedSpace / $totalSpace) * 100, 1);
                        
                        $drives[] = [
                            'letter' => $letter,
                            'type' => 'Local Disk',
                            'total_space' => $totalSpace,
                            'free_space' => $freeSpace,
                            'usage_percent' => $usagePercent
                        ];
                    }
                }
            }
        }
    }
    
    return $drives;
}

/**
 * Get network interface statistics
 */
function getNetworkStats(): array {
    $output = @shell_exec('wmic path Win32_PerfRawData_Tcpip_NetworkInterface get Name,BytesTotalPersec,BytesReceivedPersec,BytesSentPersec /format:csv 2>&1');
    
    $interfaces = [];
    if ($output) {
        $lines = explode("\n", trim($output));
        
        foreach ($lines as $line) {
            if (strpos($line, 'Microsoft') !== false || strpos($line, 'ISATAP') !== false) {
                continue;
            }
            
            $parts = explode(',', $line);
            if (count($parts) >= 4) {
                $interfaces[] = [
                    'name' => trim($parts[0]),
                    'bytes_total_per_sec' => (float)$parts[1],
                    'bytes_received_per_sec' => (float)$parts[2],
                    'bytes_sent_per_sec' => (float)$parts[3]
                ];
            }
        }
    }
    
    return $interfaces;
}

/**
 * Get system uptime
 */
function getUptime(): array {
    $output = @shell_exec('wmic os get lastbootuptime /value 2>&1');
    
    if (preg_match('/LastBootUpTime=(\d+)/', $output, $matches)) {
        $bootTime = $matches[1];
        $bootDateTime = DateTime::createFromFormat('YmdHis.u', $bootTime);
        $now = new DateTime();
        $uptime = $now->diff($bootDateTime);
        
        return [
            'boot_time' => $bootDateTime->format('Y-m-d H:i:s'),
            'days' => $uptime->days,
            'hours' => $uptime->h,
            'minutes' => $uptime->i,
            'seconds' => $uptime->s
        ];
    }
    
    return ['error' => 'Could not determine uptime'];
}

/**
 * Get Apache modules
 */
function getApacheModules(): array {
    $cacheFile = 'modules_cache.json';
    
    // Check cache (5 minutes)
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    $output = @shell_exec('httpd -M 2>&1');
    if (!$output) {
        $output = @shell_exec('apache2ctl -M 2>&1');
    }
    
    $modules = [];
    if ($output) {
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (preg_match('/(\w+)_module \(shared\)/', $line, $matches)) {
                $modules[] = $matches[1];
            }
        }
    }
    
    // Cache results
    file_put_contents($cacheFile, json_encode($modules));
    
    return $modules;
}
```

---

## API Reference

### Metrics Endpoints

All endpoints return JSON and support caching.

#### CPU Usage
```http
GET /server_monitor.php?action=cpu

Response:
{
    "success": true,
    "data": {
        "usage": 45.2,
        "cores": 8,
        "model": "Intel(R) Core(TM) i7-9700K CPU @ 3.60GHz"
    },
    "cached": false,
    "timestamp": "2024-01-15T10:30:00Z"
}
```

#### Memory Usage
```http
GET /server_monitor.php?action=mem

Response:
{
    "success": true,
    "data": {
        "usage_percent": 62.5,
        "total_mb": 16384,
        "used_mb": 10240,
        "free_mb": 6144
    }
}
```

#### Swap Memory
```http
GET /server_monitor.php?action=memswap

Response:
{
    "success": true,
    "data": {
        "usage_percent": 15.3,
        "total_mb": 8192,
        "used_mb": 1253,
        "available_mb": 6939
    }
}
```

#### Disk I/O
```http
GET /server_monitor.php?action=diskio

Response:
{
    "success": true,
    "data": {
        "drives": [
            {
                "letter": "C",
                "type": "Local Disk",
                "total_gb": 500,
                "free_gb": 200,
                "used_gb": 300,
                "usage_percent": 60.0,
                "read_mb_sec": 50.5,
                "write_mb_sec": 25.3
            }
        ]
    }
}
```

#### Network Statistics
```http
GET /server_monitor.php?action=network

Response:
{
    "success": true,
    "data": {
        "interfaces": [
            {
                "name": "Ethernet",
                "bytes_received_per_sec": 1048576,
                "bytes_sent_per_sec": 524288,
                "total_mbps": 12.5
            }
        ]
    }
}
```

#### System Uptime
```http
GET /server_monitor.php?action=uptime

Response:
{
    "success": true,
    "data": {
        "boot_time": "2024-01-01 08:00:00",
        "days": 14,
        "hours": 2,
        "minutes": 30,
        "seconds": 0,
        "uptime_string": "14 days, 2 hours, 30 minutes"
    }
}
```

#### Apache Modules
```http
GET /server_monitor.php?action=modules

Response:
{
    "success": true,
    "data": {
        "modules": [
            "rewrite",
            "ssl",
            "php",
            "proxy",
            "headers",
            "expires"
        ]
    },
    "cached": true
}
```

---

## Metrics Collected

### CPU Metrics
| Metric | Description | Unit |
|--------|-------------|------|
| usage | Current CPU load | % |
| cores | Number of CPU cores | count |
| model | CPU model name | string |

### Memory Metrics
| Metric | Description | Unit |
|--------|-------------|------|
| usage_percent | Memory usage percentage | % |
| total_mb | Total physical memory | MB |
| used_mb | Used memory | MB |
| free_mb | Free memory | MB |

### Swap Metrics
| Metric | Description | Unit |
|--------|-------------|------|
| usage_percent | Swap usage percentage | % |
| total_mb | Total swap space | MB |
| used_mb | Used swap | MB |
| available_mb | Available swap | MB |

### Disk Metrics
| Metric | Description | Unit |
|--------|-------------|------|
| letter | Drive letter | string |
| type | Drive type | string |
| total_gb | Total capacity | GB |
| free_gb | Free space | GB |
| used_gb | Used space | GB |
| usage_percent | Usage percentage | % |

### Network Metrics
| Metric | Description | Unit |
|--------|-------------|------|
| name | Interface name | string |
| bytes_received_per_sec | Incoming traffic | B/s |
| bytes_sent_per_sec | Outgoing traffic | B/s |
| total_mbps | Total bandwidth | Mbps |

### Uptime Metrics
| Metric | Description | Unit |
|--------|-------------|------|
| boot_time | System boot time | datetime |
| days | Days since boot | days |
| hours | Hours component | hours |
| minutes | Minutes component | minutes |
| seconds | Seconds component | seconds |

---

## Frontend Architecture

### Dashboard JavaScript

```javascript
// Performance Monitor
class PerformanceMonitor {
    constructor() {
        this.updateInterval = 5000; // 5 seconds
        this.charts = {};
        this.data = {
            cpu: [],
            memory: [],
            network: []
        };
        
        this.initCharts();
        this.startMonitoring();
    }
    
    initCharts() {
        // CPU Chart
        const cpuCtx = document.getElementById('cpu-chart').getContext('2d');
        this.charts.cpu = new Chart(cpuCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'CPU Usage (%)',
                    data: [],
                    borderColor: '#4a90e2',
                    backgroundColor: 'rgba(74, 144, 226, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                animation: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
        
        // Memory Chart
        const memCtx = document.getElementById('memory-chart').getContext('2d');
        this.charts.memory = new Chart(memCtx, {
            type: 'doughnut',
            data: {
                labels: ['Used', 'Free'],
                datasets: [{
                    data: [0, 100],
                    backgroundColor: ['#4a90e2', '#e0e0e0']
                }]
            }
        });
    }
    
    async startMonitoring() {
        await this.updateAll();
        setInterval(() => this.updateAll(), this.updateInterval);
    }
    
    async updateAll() {
        await Promise.all([
            this.updateCPU(),
            this.updateMemory(),
            this.updateNetwork(),
            this.updateUptime()
        ]);
    }
    
    async updateCPU() {
        const response = await fetch('server_monitor.php?action=cpu');
        const data = await response.json();
        
        if (data.success) {
            const cpuData = data.data;
            
            // Update gauge
            document.getElementById('cpu-usage').textContent = cpuData.usage.toFixed(1) + '%';
            
            // Update chart
            const now = new Date().toLocaleTimeString();
            this.charts.cpu.data.labels.push(now);
            this.charts.cpu.data.datasets[0].data.push(cpuData.usage);
            
            // Keep last 60 data points
            if (this.charts.cpu.data.labels.length > 60) {
                this.charts.cpu.data.labels.shift();
                this.charts.cpu.data.datasets[0].data.shift();
            }
            
            this.charts.cpu.update();
        }
    }
    
    async updateMemory() {
        const response = await fetch('server_monitor.php?action=mem');
        const data = await response.json();
        
        if (data.success) {
            const memData = data.data;
            
            document.getElementById('memory-usage').textContent = memData.usage_percent.toFixed(1) + '%';
            document.getElementById('memory-total').textContent = this.formatBytes(memData.total_mb * 1024 * 1024);
            document.getElementById('memory-used').textContent = this.formatBytes(memData.used_mb * 1024 * 1024);
            document.getElementById('memory-free').textContent = this.formatBytes(memData.free_mb * 1024 * 1024);
            
            this.charts.memory.data.datasets[0].data = [memData.used_mb, memData.free_mb];
            this.charts.memory.update();
        }
    }
    
    async updateNetwork() {
        const response = await fetch('server_monitor.php?action=network');
        const data = await response.json();
        
        if (data.success) {
            const netData = data.data;
            
            let totalRecv = 0;
            let totalSent = 0;
            
            netData.interfaces.forEach(iface => {
                totalRecv += iface.bytes_received_per_sec;
                totalSent += iface.bytes_sent_per_sec;
            });
            
            document.getElementById('network-in').textContent = this.formatBytes(totalRecv) + '/s';
            document.getElementById('network-out').textContent = this.formatBytes(totalSent) + '/s';
        }
    }
    
    async updateUptime() {
        const response = await fetch('server_monitor.php?action=uptime');
        const data = await response.json();
        
        if (data.success) {
            const uptime = data.data;
            document.getElementById('uptime').textContent = uptime.uptime_string;
            document.getElementById('boot-time').textContent = uptime.boot_time;
        }
    }
    
    formatBytes(bytes) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let unitIndex = 0;
        let value = bytes;
        
        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex++;
        }
        
        return value.toFixed(1) + ' ' + units[unitIndex];
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    new PerformanceMonitor();
});
```

---

## Caching System

### Cache Functions

```php
/**
 * Get cached data or execute callback and cache result
 */
function getCachedData($key, $callback, $ttl = 5) {
    global $cache_dir;
    
    $cache_file = $cache_dir . md5($key) . '.cache';
    
    // Check if cache exists and is fresh
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $ttl) {
        $data = file_get_contents($cache_file);
        if ($data !== false) {
            $cached = unserialize($data);
            if ($cached !== false) {
                return $cached;
            }
        }
    }
    
    // Execute callback and cache result
    $data = $callback();
    file_put_contents($cache_file, serialize($data));
    
    return $data;
}

// Usage example
$cpuUsage = getCachedData('cpu_usage', 'getCpuUsage', 5);
$memoryUsage = getCachedData('memory_usage', 'getMemoryUsage', 5);
```

### Cache Configuration

| Setting | Value | Description |
|---------|-------|-------------|
| Default TTL | 5 seconds | Metrics cache duration |
| Modules TTL | 300 seconds | Apache modules cache |
| Cache Directory | `cache/` | Cache file storage |

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Metrics not loading | Shell commands blocked | Enable shell_exec in php.ini |
| Charts not updating | JavaScript error | Check browser console |
| Cache not working | Permission denied | Check `cache/` folder permissions |
| Network stats zero | No active interfaces | Verify network interface names |

### Debug Mode

```php
// Enable in server_monitor.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-02  
**Maintained By:** ServerKherson Development Team
