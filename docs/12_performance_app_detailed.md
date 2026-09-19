# Performance (Server Monitor) Application - Detailed Documentation

**Path:** `project/performance/` (moved from `apps/performance/`)  
**Type:** System Monitoring Tool  
**Description:** Server performance monitoring with real-time metrics dashboard.

---

## Overview

The Performance Monitor is a server monitoring tool that provides real-time metrics about system resources, processes, and services. It is accessed via `/project/performance/` and features a dedicated web interface with live dashboards.

---

## File Structure

```
project/performance/
├── index.php                     # Main UI entry point
├── server_monitor.php            # Metrics API endpoint
├── monitor_stats.php             # Stats endpoint
├── style.css                     # Stylesheet
├── icon.svg                      # App icon
├── name.json                     # Display name
├── description.json              # App description
├── translations.php              # Translation mappings
├── bin/monitor_collector.php     # Background metrics collection daemon
├── controllers/
│   └── MonitorController.php     # Metrics controller
├── core/                         # Core models and services
│   ├── MetricModel.php
│   ├── SystemInfo.php
│   └── AlertManager.php
├── views/                        # View templates
│   ├── dashboard.php
│   ├── cpu.php
│   ├── memory.php
│   ├── disk.php
│   ├── network.php
│   └── processes.php
├── deploy/                       # Deployment configs
└── i18n/                         # Translation files (6 languages)
```

## Configuration Files

| File | Purpose |
|------|---------|
| `server_monitor.php` | Main metrics API |
| `monitor_stats.php` | Stats and history endpoint |
| `translations.php` | Language translations |
| `i18n/` | Translation JSON files (en, uk, ru, be, pl, cs) |

## Key Features

### Real-Time Metrics
- CPU usage percentage (per core and total)
- Memory usage (used, free, cached, buffers)
- Swap usage
- Disk I/O (read/write speeds, throughput)
- Network traffic (in/out, interfaces)
- System uptime

### Process Monitoring
- Running process list with resource usage
- Process tree visualization
- CPU-intensive process detection
- Memory-heavy process detection
- Kill/restart capabilities (admin)

### Dashboard
- Responsive layout with live charts
- Dark/light theme support
- Configurable refresh rate
- Alert thresholds and notifications
- Historical data and trends
- Export data (CSV, JSON)

### Alerts
- Configurable thresholds for CPU, memory, disk
- Email and in-app notifications
- Alert history and acknowledgment
- Maintenance mode

### System Info
- OS version and kernel
- Hardware details (CPU model, cores, RAM)
- Network interfaces and IPs
- Disk partitions and mount points
- Active users and sessions

## API Endpoints

### Metrics
- `GET /performance/cpu` - CPU usage percentage
- `GET /performance/mem` - Memory usage percentage
- `GET /performance/memswap` - Swap memory usage
- `GET /performance/diskio` - Disk I/O statistics
- `GET /performance/network` - Network traffic
- `GET /performance/uptime` - System uptime
- `GET /performance/processes` - Running processes
- `GET /performance/disk` - Disk usage per partition

### Admin
- `POST /performance/alerts/config` - Update alert thresholds
- `GET /performance/alerts/history` - Alert history
- `POST /performance/maintenance` - Toggle maintenance mode

## Data Storage

Metrics stored in SQLite database:
```
project/performance/data/metrics.db
```

Configuration stored in `config.json`.

## Security Features

- Admin authentication required for settings
- IP-based access restrictions
- Alert threshold validation
- Read-only mode for non-admin users

## Performance

- Background metrics collection (every 10 seconds)
- SQLite for efficient querying
- Caching of frequently accessed data
- Web Worker for client-side chart rendering
- Lazy loading for historical data

## Internationalization

Supports all 6 interface languages (en, uk, ru, be, pl, cs).

## Deployment Notes

- Cron job or background daemon for metrics collection
- SQLite write permissions
- `proc` filesystem read access for system metrics
- Redis/caching layer for high-traffic deployments

## Scheduled Tasks

Cron entries (configurable):
```
*/5 * * * * php /home/ihor/apps/kanboard/deploy/collect_historical_stats.php --retain-days=30
*/10 * * * * php /path/to/project/performance/bin/monitor_collector.php
```
