<script>
const themeClass = '<?= $theme ?>';
const primaryColor = '<?= $color ?>';
const userRole = <?= $userRole ?>;
const translations = {
    days: <?php $d=[];foreach($translations as $lc=>$ld){$d[$lc]=$ld['days']??['day','days'];}echo json_encode($d,JSON_UNESCAPED_UNICODE); ?>,
    hours: <?php $d=[];foreach($translations as $lc=>$ld){$d[$lc]=$ld['hours']??['hour','hours'];}echo json_encode($d,JSON_UNESCAPED_UNICODE); ?>,
    minutes: <?php $d=[];foreach($translations as $lc=>$ld){$d[$lc]=$ld['minutes']??['minute','minutes'];}echo json_encode($d,JSON_UNESCAPED_UNICODE); ?>,
    ui: <?php
    $jsUiKeys = [
        'loading_from_cache', 'refreshing', 'connection_error', 'connection_unstable',
        'max_retries_reached', 'retry', 'no_data_available', 'access_denied',
        'error_loading_phpinfo', 'unknown_interface', 'download', 'upload',
        'total', 'speed', 'session_break', 'usage', 'network_speed',
        'time', 'loading_ellipsis', 'error_loading_data', 'unknown'
    ];
    $jsUi = [];
    foreach ($translations as $langCode => $langData) {
        $jsUi[$langCode] = [];
        foreach ($jsUiKeys as $key) {
            $jsUi[$langCode][$key] = $langData[$key] ?? '';
        }
    }
    echo json_encode($jsUi, JSON_UNESCAPED_UNICODE);
    ?>
};
const monitorChartsLabels = <?= json_encode([
    'title' => $monitorChartsTitle,
    'system' => $t['system_metrics'] ?? 'System Metrics',
    'disk' => $t['disk_usage'] ?? 'Disk Usage',
    'hourly' => $t['app_metrics'] ?? 'Application Metrics',
    'loading' => $t['loading'] ?? 'Loading',
    'empty' => $t['no_data_available'] ?? 'No data available',
    'delete' => $t['delete'] ?? 'Delete',
    'confirm_delete' => $t['cleanup_device_confirm'] ?? 'Delete all monitoring data for this device?',
    'deleted' => $t['cleanup_success'] ?? 'Data deleted successfully',
    'error' => $t['cleanup_error'] ?? 'Error deleting data',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const STORAGE_KEY = 'server_monitor_chart_data_v3';
const DATA_MAX_AGE = 3 * 60 * 60 * 1000;
const UPDATE_INTERVAL = 120000;
const SECTION_STORAGE_KEY = 'server_monitor_active_section';
const MAX_DATA_POINTS = 180;
const lang = '<?= $lang ?>';

function t(key, params = {}) {
    const dict = translations.ui[lang] || {};
    let str = dict[key];
    if (str === undefined || str === '') {
        console.warn('Translation missing for key: "' + key + '"');
        str = key;
    }
    Object.keys(params).forEach(p => {
        str = str.replaceAll('{' + p + '}', params[p]);
    });
    return str;
}

const serverMonitorUrl = new URL(<?= json_encode($serverMonitorEndpoint, JSON_UNESCAPED_SLASHES) ?>, window.location.origin);
const serverMonitorFallbackUrl = new URL(<?= json_encode($serverMonitorEndpoint, JSON_UNESCAPED_SLASHES) ?>, window.location.origin);

let cpuData = [];
let memoryData = [];
let diskData = [];
let networkData = [];
let swapData = [];
let graphChart = null;
let isFetching = false;
let fetchQueue = [];
let retryCount = 0;
const maxRetries = 3;
let lastNginxSearch = '';
let lastPhpSearch = '';
let lastNginxModules = [];
let lastPhpModules = [];
let lastSessionEnd = null;
let currentSessionStart = null;
let hoverPreviewChart = null;
let hoverPreviewEl = null;
let hoverPreviewCanvas = null;
let hoverPreviewRequestId = 0;
// Latest numeric metrics for reliable caching
let lastKnownMetrics = {
    cpu_usage: 0,
    memory_usage: 0,
    disk_usage: 0,
    swap_usage: 0,
    network_speed: 0,
    uptime: '-'
};
let lastKnownDrives = [];
let lastKnownNetworkInterfaces = [];
let lastKnownSmartData = null;
let lastKnownUptimeMonitoring = null;
let lastKnownDockerContainers = [];
let uptimeChecksById = {};
let expandedDockerChecks = {};
let dockerChartPeriods = {};
let dockerMetricCharts = {};
let uptimeGroupCollapsed = { docker: false, other: false };
let monitorChartsLoaded = false;
let monitorChartsPeriod = '24h';
let monitorCharts = {};

// Auto-zoom state (only for admins)
let autoZoomEnabled = userRole >= 2;
let autoZoomScales = {
    cpu: { min: 0, max: 100 },
    memory: { min: 0, max: 100 },
    disk: { min: 0, max: 100 },
    swap: { min: 0, max: 100 },
    network: { min: 0, max: 100 }
};

function drawCircle(c, pct, col) {
    const ctx = c.getContext('2d'), R = (c.width/2) - 10;
    const centerX = c.width/2;
    const centerY = c.height/2;
    
    ctx.clearRect(0, 0, c.width, c.height);
    
    ctx.beginPath();
    ctx.arc(centerX, centerY, R, 0, 2*Math.PI);
    ctx.strokeStyle = themeClass === 'dark' ? '#444' : '#ddd';
    ctx.lineWidth = 8;
    ctx.stroke();
    
    if (pct > 0) {
        const startAngle = -Math.PI/2;
        const endAngle = startAngle + 2*Math.PI*pct/100;
        
        function interpolateColor(percent) {
            const startColor = { r: 33, g: 150, b: 243 };
            const midColor1 = { r: 76, g: 175, b: 80 };
            const midColor2 = { r: 255, g: 193, b: 7 };
            const midColor3 = { r: 255, g: 152, b: 0 };
            const endColor = { r: 244, g: 67, b: 54 };
            
            let r, g, b;
            
            if (percent <= 0.25) {
                const t = percent / 0.25;
                r = Math.round(startColor.r + (midColor1.r - startColor.r) * t);
                g = Math.round(startColor.g + (midColor1.g - startColor.g) * t);
                b = Math.round(startColor.b + (midColor1.b - startColor.b) * t);
            } else if (percent <= 0.5) {
                const t = (percent - 0.25) / 0.25;
                r = Math.round(midColor1.r + (midColor2.r - midColor1.r) * t);
                g = Math.round(midColor1.g + (midColor2.g - midColor1.g) * t);
                b = Math.round(midColor1.b + (midColor2.b - midColor1.b) * t);
            } else if (percent <= 0.75) {
                const t = (percent - 0.5) / 0.25;
                r = Math.round(midColor2.r + (midColor3.r - midColor2.r) * t);
                g = Math.round(midColor2.g + (midColor3.g - midColor2.g) * t);
                b = Math.round(midColor2.b + (midColor3.b - midColor2.b) * t);
            } else {
                const t = (percent - 0.75) / 0.25;
                r = Math.round(midColor3.r + (endColor.r - midColor3.r) * t);
                g = Math.round(midColor3.g + (endColor.g - midColor3.g) * t);
                b = Math.round(midColor3.b + (endColor.b - midColor3.b) * t);
            }
            
            return `rgb(${r}, ${g}, ${b})`;
        }
        
        const progressColor = interpolateColor(pct / 100);
        
        ctx.beginPath();
        ctx.arc(centerX, centerY, R, startAngle, endAngle);
        ctx.strokeStyle = progressColor;
        ctx.lineWidth = 8;
        ctx.lineCap = 'round';
        ctx.stroke();
    }
}

function loadStoredData() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const data = JSON.parse(stored);
            const now = Date.now();
            
            if (data.timestamp && (now - data.timestamp) > DATA_MAX_AGE) {
                localStorage.removeItem(STORAGE_KEY);
                return null;
            }
            
            cpuData = data.cpuData || [];
            memoryData = data.memoryData || [];
            diskData = data.diskData || [];
            networkData = data.networkData || [];
            swapData = data.swapData || [];
            lastSessionEnd = data.lastSessionEnd || null;
            
            if (cpuData.length > MAX_DATA_POINTS) cpuData = cpuData.slice(-MAX_DATA_POINTS);
            if (memoryData.length > MAX_DATA_POINTS) memoryData = memoryData.slice(-MAX_DATA_POINTS);
            if (diskData.length > MAX_DATA_POINTS) diskData = diskData.slice(-MAX_DATA_POINTS);
            if (networkData.length > MAX_DATA_POINTS) networkData = networkData.slice(-MAX_DATA_POINTS);
            if (swapData.length > MAX_DATA_POINTS) swapData = swapData.slice(-MAX_DATA_POINTS);
            
            return true;
        }
    } catch (e) {
        console.error('Error loading stored data:', e);
    }
    return false;
}

function saveStoredData() {
    try {
        const data = {
            cpuData: cpuData,
            memoryData: memoryData,
            diskData: diskData,
            networkData: networkData,
            swapData: swapData,
            lastSessionEnd: lastSessionEnd,
            lastMetrics: lastKnownMetrics,
            lastNginxModules: lastNginxModules,
            lastPhpModules: lastPhpModules,
            lastNetworkData: lastKnownNetworkInterfaces,
            lastDrivesData: lastKnownDrives,
            lastSmartData: lastKnownSmartData,
            lastUptimeMonitoring: lastKnownUptimeMonitoring,
            lastDockerContainers: lastKnownDockerContainers,
            timestamp: Date.now()
        };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch (e) {
        console.error('Error saving data to localStorage:', e);
    }
}

/**
 * Calculate auto-zoom scale for a given data array
 * Returns { min, max } with padding for better visualization
 */
function calculateAutoZoomScale(data, type) {
    if (!data || data.length === 0) {
        return { min: 0, max: type === 'network' ? 100 : 100 };
    }

    // Filter out break points and get numeric values
    const values = data.filter(item => !item.isBreak).map(item => item.y);

    if (values.length === 0) {
        return { min: 0, max: type === 'network' ? 100 : 100 };
    }

    const minVal = Math.min(...values);
    const maxVal = Math.max(...values);
    const range = maxVal - minVal;

    if (type === 'cpu') {
        const upper = Math.min(100, Math.max(10, Math.ceil((maxVal + Math.max(5, range * 0.25)) / 5) * 5));
        return { min: 0, max: upper };
    }

    // If all values are the same or very close, create a reasonable range around the value
    if (range === 0 || range < 1) {
        const center = maxVal;
        const spread = Math.max(center * 0.35, type === 'network' ? 1024 : 15);
        return {
            min: Math.max(0, center - spread),
            max: Math.ceil(center + spread)
        };
    }

    // Add extra headroom to avoid clipping on smoothed lines
    const paddingPercent = 0.22;
    const padding = range * paddingPercent;
    let newMin = minVal - padding;
    let newMax = maxVal + padding;

    // Clamp to valid range
    newMin = Math.max(0, newMin);

    // Ensure minimum spread so chart doesn't look like a "wall"
    const minSpread = type === 'network' ? 5120 : 20; // At least 5 KB/s or 20%
    if ((newMax - newMin) < minSpread) {
        const center = (newMin + newMax) / 2;
        const halfSpread = minSpread / 2;
        newMin = Math.max(0, center - halfSpread);
        newMax = center + halfSpread;
    }

    // Round to nice numbers for readability
    if (type === 'network') {
        // For network, round to nearest KB
        newMin = Math.max(0, Math.floor(newMin / 1024) * 1024);
        newMax = Math.ceil(newMax / 1024) * 1024;
    } else {
        // For percentage values, round to nearest 5
        newMin = Math.max(0, Math.floor(newMin / 5) * 5);
        newMax = Math.ceil(newMax / 5) * 5;
    }

    // Final guard: keep extra headroom above real max value
    const requiredMax = maxVal + (type === 'network' ? Math.max(range * 0.1, 256) : Math.max(range * 0.1, 2));
    if (newMax < requiredMax) {
        if (type === 'network') {
            newMax = Math.ceil(requiredMax / 1024) * 1024;
        } else {
            newMax = Math.ceil(requiredMax / 5) * 5;
        }
    }

    return { min: newMin, max: newMax };
}

/**
 * Update auto-zoom scales for all chart types based on current data
 */
function updateAutoZoomScales() {
    autoZoomScales.cpu = calculateAutoZoomScale(cpuData, 'cpu');
    autoZoomScales.memory = calculateAutoZoomScale(memoryData, 'memory');
    autoZoomScales.disk = calculateAutoZoomScale(diskData, 'disk');
    autoZoomScales.swap = calculateAutoZoomScale(swapData, 'swap');
    autoZoomScales.network = calculateAutoZoomScale(networkData, 'network');
}

/**
 * Toggle auto-zoom on/off
 */
function toggleAutoZoom() {
    if (userRole < 2) {
        alert(translations.ui[lang].access_denied);
        return;
    }

    autoZoomEnabled = !autoZoomEnabled;
    
    const toggle = document.getElementById('autoZoomToggle');
    if (autoZoomEnabled) {
        toggle.classList.add('active');
        updateAutoZoomScales();
        // Refresh current chart if modal is open
        if (graphChart) {
            const title = document.getElementById('graphTitle').textContent;
            const typeMap = {
                '<?= htmlspecialchars($t['cpu_usage']) ?>': 'cpu',
                '<?= htmlspecialchars($t['memory_usage']) ?>': 'memory',
                '<?= htmlspecialchars($t['disk_usage']) ?>': 'disk',
                '<?= htmlspecialchars($t['swap_usage']) ?>': 'swap',
                '<?= htmlspecialchars($t['network_usage']) ?>': 'network'
            };
            for (const [label, type] of Object.entries(typeMap)) {
                if (title === label) {
                    openGraphModal(type);
                    break;
                }
            }
        }
    } else {
        toggle.classList.remove('active');
        // Refresh current chart with fixed scales
        if (graphChart) {
            const title = document.getElementById('graphTitle').textContent;
            const typeMap = {
                '<?= htmlspecialchars($t['cpu_usage']) ?>': 'cpu',
                '<?= htmlspecialchars($t['memory_usage']) ?>': 'memory',
                '<?= htmlspecialchars($t['disk_usage']) ?>': 'disk',
                '<?= htmlspecialchars($t['swap_usage']) ?>': 'swap',
                '<?= htmlspecialchars($t['network_usage']) ?>': 'network'
            };
            for (const [label, type] of Object.entries(typeMap)) {
                if (title === label) {
                    openGraphModal(type);
                    break;
                }
            }
        }
    }
    
    // Save preference
    try {
        localStorage.setItem('autoZoomEnabled', autoZoomEnabled);
    } catch (e) {}
}

/**
 * Get Y-axis scale configuration based on auto-zoom state
 */
function getAutoZoomScaleConfig(type) {
    if (autoZoomEnabled && autoZoomScales[type]) {
        return {
            min: autoZoomScales[type].min,
            max: autoZoomScales[type].max
        };
    }
    
    // Default fixed scales
    return {
        min: 0,
        max: type === 'network' ? undefined : 100,
        suggestedMin: 0,
        suggestedMax: type === 'network' ? undefined : 100
    };
}

// Helper function for parsing size
function parseB(str) {
    if (!str) return 0;
    const units = { 'B': 1, 'KB': 1024, 'MB': 1048576, 'GB': 1073741824, 'TB': 1099511627776 };
    const match = str.match(/([\d.]+)\s*([KMGT]?B)/i);
    if (match) {
        const value = parseFloat(match[1]);
        const unit = match[2].toUpperCase();
        return value * (units[unit] || 1);
    }
    return 0;
}

// Helper function for parsing network speed
function parseNetworkSpeed(str) {
    if (!str) return 0;
    const units = { 'B/s': 1, 'KB/s': 1024, 'MB/s': 1048576, 'GB/s': 1073741824 };
    const match = str.match(/([\d.]+)\s*([KMGT]?B\/s)/i);
    if (match) {
        const value = parseFloat(match[1]);
        const unit = match[2].toUpperCase();
        return value * (units[unit] || 1);
    }
    return 0;
}

function addSessionBreakIfNeeded() {
    if (lastSessionEnd && currentSessionStart) {
        const breakDuration = currentSessionStart - lastSessionEnd;
        if (breakDuration > 30000) {
            return true;
        }
    }
    return false;
}

function updateConnectionStatus(status, message = '') {
    const statusEl = document.getElementById('connectionStatus');
    const textEl = document.getElementById('statusText');
    const retryEl = document.getElementById('retryCount');
    
    if (status === 'connected') {
        statusEl.style.display = 'none';
        statusEl.className = 'connection-status connected';
        retryCount = 0;
    } else if (status === 'error') {
        statusEl.style.display = 'block';
        statusEl.className = 'connection-status error';
        textEl.textContent = message || translations.ui[lang].connection_error;
        retryEl.textContent = retryCount > 0 ? ` (${translations.ui[lang].retry} ${retryCount}/${maxRetries})` : '';
    } else if (status === 'warning') {
        statusEl.style.display = 'block';
        statusEl.className = 'connection-status warning';
        textEl.textContent = message || translations.ui[lang].connection_unstable;
    }
}

function filterTable(inpId, bodyId) {
    const input = document.getElementById(inpId);
    const searchValue = input.value.toLowerCase();
    const body = document.getElementById(bodyId);
    const rows = body.getElementsByTagName('tr');
    
    if (inpId === 'nginxSearch') lastNginxSearch = searchValue;
    if (inpId === 'phpSearch') lastPhpSearch = searchValue;
    
    for (let row of rows) {
        const firstCell = row.cells[0];
        if (firstCell) {
            const cellText = firstCell.textContent.toLowerCase();
            row.style.display = cellText.includes(searchValue) ? '' : 'none';
        }
    }
}

function restoreTableFilter(inpId, bodyId) {
    const input = document.getElementById(inpId);
    const searchValue = input.value;
    
    if (searchValue) {
        filterTable(inpId, bodyId);
    }
}

async function loadData(forceRefresh = false) {
    if (isFetching) {
        fetchQueue.push(loadData);
        return;
    }
    isFetching = true;
    let timeoutId = null;
    try {
        const controller = new AbortController();
        timeoutId = setTimeout(() => controller.abort(), 60000);
        const url = new URL(serverMonitorUrl.href);
        url.searchParams.set('t', Date.now());
        if (forceRefresh) {
            url.searchParams.set('force_refresh', '1');
        }
        const response = await fetch(url.href, {
            signal: controller.signal,
            headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' },
            credentials: 'same-origin',
            cache: 'no-store'
        });
        if (!response.ok && (response.status === 404 || response.status === 0)) {
            throw new Error('primary_fetch_failed');
        }
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const d = await response.json();
        if (!d || typeof d !== 'object') {
            throw new Error('Invalid response format');
        }
        updateConnectionStatus('connected');
        retryCount = 0;
        handleData(d);
        saveStoredData();
        histDataCache = null;
    } catch (error) {
        console.error('Error loading data:', error);
        retryCount++;
        const shouldUseFallback = error && (error.message === 'primary_fetch_failed' || (error.name === 'TypeError' && retryCount <= 1));
        if (shouldUseFallback) {
            try {
                const fallbackUrl = new URL(serverMonitorFallbackUrl.href);
                fallbackUrl.searchParams.set('t', Date.now());
                if (forceRefresh) {
                    fallbackUrl.searchParams.set('force_refresh', '1');
                }
                const fallbackResponse = await fetch(fallbackUrl.href, { credentials: 'same-origin', cache: 'no-store', headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' } });
                if (fallbackResponse.ok) {
                    const d = await fallbackResponse.json();
                    if (d && typeof d === 'object') {
                        updateConnectionStatus('connected');
                        retryCount = 0;
                        handleData(d);
                        saveStoredData();
                        histDataCache = null;
                        return;
                    }
                }
            } catch (fallbackError) {
                console.error('Fallback fetch failed:', fallbackError);
            }
        }
        const isAbort = error && (error.name === 'AbortError' || String(error).includes('DOMException'));
        const isFetchFailure = error && (error.name === 'TypeError' || String(error.message || '').toLowerCase().includes('failed to fetch'));
        const readableMessage = isAbort ? 'Request timed out (60s), retrying...' : isFetchFailure ? 'Connection failed, retrying...' : (error && error.message ? error.message : 'Unknown error');
        if (retryCount <= maxRetries) {
            updateConnectionStatus('error', readableMessage);
            const statusEl = document.getElementById('connectionStatus');
            if (!document.getElementById('manualRetryBtn')) {
                const retryBtn = document.createElement('button');
                retryBtn.id = 'manualRetryBtn';
                retryBtn.textContent = translations.ui[lang].retry;
                retryBtn.className = 'btn-secondary';
                retryBtn.style.marginLeft = '10px';
                retryBtn.style.padding = '4px 8px';
                retryBtn.style.fontSize = '12px';
                retryBtn.onclick = () => {
                    retryCount = 0;
                    refreshData();
                };
                statusEl.appendChild(retryBtn);
            }
            setTimeout(() => {
                isFetching = false;
                if (fetchQueue.length > 0) {
                    const next = fetchQueue.shift();
                    next();
                } else {
                    refreshData();
                }
            }, 2000 * retryCount);
            return;
        } else {
            updateConnectionStatus('error', translations.ui[lang].max_retries_reached);
            const statusEl = document.getElementById('connectionStatus');
            if (!document.getElementById('manualRetryBtn')) {
                const retryBtn = document.createElement('button');
                retryBtn.id = 'manualRetryBtn';
                retryBtn.textContent = translations.ui[lang].retry;
                retryBtn.className = 'btn-primary';
                retryBtn.style.marginLeft = '10px';
                retryBtn.onclick = () => {
                    retryCount = 0;
                    refreshData();
                };
                statusEl.appendChild(retryBtn);
            }
        }
    } finally {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        isFetching = false;
        if (fetchQueue.length > 0) {
            const next = fetchQueue.shift();
            setTimeout(next, 100);
        }
    }
}

function formatNetworkSpeed(bytesPerSec) {
    if (bytesPerSec === 0) return '0 B/s';
    
    const units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
    let value = bytesPerSec;
    let unitIndex = 0;
    
    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex++;
    }
    
    return value.toFixed(1) + ' ' + units[unitIndex];
}

function handleData(d, isFallback = false) {
    drawCircle(cpuChart, d.cpu_usage || 0);
    drawCircle(memChart, d.memory_usage || 0);
    drawCircle(diskChart, d.disk_usage || 0);
    drawCircle(swapChart, d.swap_usage || 0);
    
    cpuVal.textContent = (d.cpu_usage || 0).toFixed(1) + '%';
    memVal.textContent = (d.memory_usage || 0).toFixed(1) + '%';
    diskVal.textContent = (d.disk_usage || 0).toFixed(1) + '%';
    swapVal.textContent = (d.swap_usage || 0).toFixed(1) + '%';
    
    // Save numeric values for cache
    lastKnownMetrics.cpu_usage = d.cpu_usage || 0;
    lastKnownMetrics.memory_usage = d.memory_usage || 0;
    lastKnownMetrics.disk_usage = d.disk_usage || 0;
    lastKnownMetrics.swap_usage = d.swap_usage || 0;
    
    let totalNetworkSpeed = 0;
    if (d.network_data && Array.isArray(d.network_data)) {
        d.network_data.forEach(net => {
            totalNetworkSpeed += (net.total_rate || 0);
        });
    }
    
    const networkPercent = Math.min((totalNetworkSpeed / 1048576) * 10, 100);
    drawCircle(networkChart, networkPercent);
    networkVal.textContent = formatNetworkSpeed(totalNetworkSpeed);
    
    // Save network speed
    lastKnownMetrics.network_speed = totalNetworkSpeed;
    
    cpuVal.style.color = isFallback ? 'var(--warning-color)' : '';
    memVal.style.color = isFallback ? 'var(--warning-color)' : '';
    diskVal.style.color = isFallback ? 'var(--warning-color)' : '';
    swapVal.style.color = isFallback ? 'var(--warning-color)' : '';
    networkVal.style.color = isFallback ? 'var(--warning-color)' : '';

    displaySmartData(d.smart || null);
    displayUptimeMonitoring(d.uptime_monitoring || null, d.docker_containers || []);
    
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second: '2-digit'});
    const timestamp = now.getTime();
    
    const hasSessionBreak = addSessionBreakIfNeeded();
    
    if (hasSessionBreak) {
        const breakMarker = { x: '— ' + translations.ui[lang].session_break + ' —', y: null, timestamp: lastSessionEnd, isBreak: true };
        cpuData.push(breakMarker);
        memoryData.push(breakMarker);
        diskData.push(breakMarker);
        swapData.push(breakMarker);
        networkData.push(breakMarker);
    }
    
    cpuData.push({ x: timeStr, y: d.cpu_usage || 0, timestamp: timestamp });
    memoryData.push({ x: timeStr, y: d.memory_usage || 0, timestamp: timestamp });
    diskData.push({ x: timeStr, y: d.disk_usage || 0, timestamp: timestamp });
    swapData.push({ x: timeStr, y: d.swap_usage || 0, timestamp: timestamp });
    networkData.push({ x: timeStr, y: totalNetworkSpeed, timestamp: timestamp });
    
    if (cpuData.length > MAX_DATA_POINTS) cpuData.shift();
    if (memoryData.length > MAX_DATA_POINTS) memoryData.shift();
    if (diskData.length > MAX_DATA_POINTS) diskData.shift();
    if (swapData.length > MAX_DATA_POINTS) swapData.shift();
    if (networkData.length > MAX_DATA_POINTS) networkData.shift();

    // Update auto-zoom scales if enabled
    if (autoZoomEnabled) {
        updateAutoZoomScales();
        
        // Refresh chart if modal is open to apply new scales
        if (graphChart) {
            const title = document.getElementById('graphTitle').textContent;
            const typeMap = {
                '<?= htmlspecialchars($t['cpu_usage']) ?>': 'cpu',
                '<?= htmlspecialchars($t['memory_usage']) ?>': 'memory',
                '<?= htmlspecialchars($t['disk_usage']) ?>': 'disk',
                '<?= htmlspecialchars($t['swap_usage']) ?>': 'swap',
                '<?= htmlspecialchars($t['network_usage']) ?>': 'network'
            };
            for (const [label, type] of Object.entries(typeMap)) {
                if (title === label) {
                    openGraphModal(type);
                    break;
                }
            }
        }
    }

    const days = d.uptime?.days || 0;
    const hours = d.uptime?.hours || 0;
    const minutes = d.uptime?.minutes || 0;
    
    let uptimeStr = '';
    if (days > 0) {
        uptimeStr += days + ' ' + translations.days[lang][days === 1 ? 0 : 1] + ', ';
    }
    if (hours > 0 || days > 0) {
        uptimeStr += hours + ' ' + translations.hours[lang][hours === 1 ? 0 : 1] + ', ';
    }
    uptimeStr += minutes + ' ' + translations.minutes[lang][minutes === 1 ? 0 : 1];
    uptimeVal.textContent = uptimeStr || '-';
    lastKnownMetrics.uptime = uptimeVal.textContent;
    
    networkGrid.innerHTML = '';
    if (d.network_data && Array.isArray(d.network_data) && d.network_data.length > 0) {
        lastKnownNetworkInterfaces = d.network_data;
        d.network_data.forEach(net => {
            networkGrid.insertAdjacentHTML('beforeend', `
                <div class="network-card">
                    <h4>${net.name || translations.ui[lang].unknown_interface}</h4>
                    <div class="network-stats">
                        <div class="network-stat">
                            <span class="network-label">${translations.ui[lang].download}:</span>
                            <span class="network-value">${formatNetworkSpeed(net.bytes_recv_rate || 0)}</span>
                        </div>
                        <div class="network-stat">
                            <span class="network-label">${translations.ui[lang].upload}:</span>
                            <span class="network-value">${formatNetworkSpeed(net.bytes_sent_rate || 0)}</span>
                        </div>
                        <div class="network-stat">
                            <span class="network-label">${translations.ui[lang].total}:</span>
                            <span class="network-value">${formatNetworkSpeed(net.total_rate || 0)}</span>
                        </div>
                        <div class="network-stat">
                            <span class="network-label">${translations.ui[lang].speed}:</span>
                            <span class="network-value">${formatNetworkSpeed(net.speed || 0)}</span>
                        </div>
                    </div>
                </div>`);
        });
    } else {
        networkGrid.innerHTML = '<p class="no-data"><?= htmlspecialchars($t['no_network_data']) ?></p>';
    }
    
    drivesGrid.innerHTML = '';
    if (d.drives && Array.isArray(d.drives) && d.drives.length > 0) {
        lastKnownDrives = d.drives;
        d.drives.forEach(dr => {
            drivesGrid.insertAdjacentHTML('beforeend', `
                <div class="drive-card">
                    <div class="drive-header">
                        <h3 class="drive-title">${escapeHtml(dr.letter)}</h3>
                        <span class="drive-type-badge">${escapeHtml(dr.type || '—')}</span>
                    </div>
                    <div class="drive-stats-grid">
                        <div class="drive-stat-box">
                            <span class="drive-stat-label"><?= htmlspecialchars($t['filesystem']) ?></span>
                            <span class="drive-stat-value mono">${escapeHtml(dr.fs_type || '—')}</span>
                        </div>
                        <div class="drive-stat-box">
                            <span class="drive-stat-label"><?= htmlspecialchars($t['source']) ?></span>
                            <span class="drive-stat-value mono">${escapeHtml(dr.source || '—')}</span>
                        </div>
                        <div class="drive-stat-box">
                            <span class="drive-stat-label"><?= htmlspecialchars($t['total_space']) ?></span>
                            <span class="drive-stat-value">${formatB(dr.total_space)}</span>
                        </div>
                        <div class="drive-stat-box">
                            <span class="drive-stat-label"><?= htmlspecialchars($t['free_space']) ?></span>
                            <span class="drive-stat-value">${formatB(dr.free_space)}</span>
                        </div>
                    </div>
                    <div class="progress-bar"><div class="progress" style="width:${dr.usage_percent}%"></div></div>
                    <p class="usage-text"><?= htmlspecialchars($t['used']) ?>: ${dr.usage_percent}%</p>
                </div>`);
        });
    } else {
        drivesGrid.innerHTML = '<p class="no-data"><?= htmlspecialchars($t['no_drives_found']) ?></p>';
    }
    
    if (d.nginx_modules && Array.isArray(d.nginx_modules)) {
        lastNginxModules = d.nginx_modules;
        fillTable('nginxBody', d.nginx_modules, 'name', 'status');
        restoreTableFilter('nginxSearch', 'nginxBody');
    }
    
    if (d.php_modules && Array.isArray(d.php_modules)) {
        lastPhpModules = d.php_modules;
        fillTable('phpBody', d.php_modules, 'name', 'status');
        restoreTableFilter('phpSearch', 'phpBody');
    }
    
    // Save data after processing
    saveStoredData();
}

function formatB(b) {
    if (b === undefined || b === null) return '0 B';
    const k = 1024;
    const s = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = Math.floor(Math.log(b) / Math.log(k));
    i = Math.min(i, s.length - 1);
    return (b / Math.pow(k, i)).toFixed(1) + ' ' + s[i];
}
function formatSmartDate(ts) {
    if (!ts) return '—';
    const d = new Date(ts * 1000);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString();
}

function formatSmartHours(hours) {
    if (hours === undefined || hours === null) return '—';
    const total = Number(hours);
    if (!Number.isFinite(total)) return '—';
    const days = Math.floor(total / 24);
    const remHours = total % 24;
    if (days <= 0) return `${total} h`;
    return `${days} d ${remHours} h`;
}

function formatSmartValue(value, suffix = '') {
    if (value === undefined || value === null || value === '') return '—';
    return `${value}${suffix}`;
}

function smartStatusText(status) {
    switch (status) {
        case 'good': return '<?= htmlspecialchars($t['smart_status_good']) ?>';
        case 'warning': return '<?= htmlspecialchars($t['smart_status_warning']) ?>';
        case 'critical': return '<?= htmlspecialchars($t['smart_status_critical']) ?>';
        default: return '<?= htmlspecialchars($t['smart_status_unknown']) ?>';
    }
}

function buildSmartNotes(smart) {
    const notes = [];
    if (!smart || !smart.available) {
        if (smart?.message) {
            notes.push({ level: 'info', text: `${'<?= htmlspecialchars($t['smart_no_data']) ?>'}: ${escapeHtml(String(smart.message))}` });
        }
        return notes;
    }
    if (smart.health_passed === false) {
        notes.push({ level: 'critical', text: '<?= htmlspecialchars($t['smart_hint_health_fail']) ?>' });
    }
    if ((smart.crc_error_count || 0) > 0) {
        notes.push({ level: 'warning', text: `<?= htmlspecialchars($t['smart_hint_crc']) ?> (${smart.crc_error_count})` });
    }
    if ((smart.error_log_count || 0) > 0) {
        notes.push({ level: 'critical', text: `<?= htmlspecialchars($t['smart_hint_errors_logged']) ?> (${smart.error_log_count})` });
    }
    if ((smart.temperature_c || 0) >= 50) {
        notes.push({ level: 'warning', text: `<?= htmlspecialchars($t['smart_hint_temp_high']) ?> (${smart.temperature_c}°C)` });
    }
    if (smart.life_remaining_percent !== undefined && smart.life_remaining_percent !== null && smart.life_remaining_percent <= 25) {
        notes.push({ level: smart.life_remaining_percent <= 10 ? 'critical' : 'warning', text: `<?= htmlspecialchars($t['smart_hint_wear_high']) ?> (${smart.life_remaining_percent}%)` });
    }
    return notes;
}

function buildSmartDeviceCard(device) {
    const notes = buildSmartNotes(device);
    const tests = Array.isArray(device.self_tests) ? device.self_tests : [];
    const notesHtml = notes.length > 0 ? `<div class="smart-notes">${notes.map(note => `<div class="smart-note ${note.level}">${note.text}</div>`).join('')}</div>` : '';
    const testsHtml = tests.length > 0 ? tests.map(test => `
        <div class="smart-test">
            <div class="smart-test-top">
                <span class="smart-test-type">${escapeHtml(test.type || '<?= htmlspecialchars($t['unknown']) ?>')}</span>
                <span class="smart-test-status">${escapeHtml(test.status || '<?= htmlspecialchars($t['smart_health_unknown']) ?>')}</span>
            </div>
            <div class="smart-test-meta">${formatSmartHours(test.lifetime_hours)}${test.remaining_percent !== null && test.remaining_percent !== undefined ? ` • ${test.remaining_percent}% remaining` : ''}</div>
        </div>`).join('') : `<p class="no-data"><?= htmlspecialchars($t['smart_no_tests']) ?></p>`;

    const cleanupButton = <?= $userRole >= 2 ? 'true' : 'false' ?> ? `<button class="smart-cleanup-btn" type="button" onclick="cleanupSmartDevice('${escapeHtml(device.device || '')}')" title="<?= htmlspecialchars($t['cleanup_device_data'] ?? 'Delete device data') ?>"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>` : '';

    return `
        <div class="smart-card">
            <div class="smart-hero">
                <div class="smart-meta">
                    <h3>${escapeHtml(device.model || '<?= htmlspecialchars($t['smart_primary_disk']) ?>')}</h3>
                    <div class="smart-subtitle">${escapeHtml(device.device || '')}</div>
                    <div class="smart-subtitle"><?= htmlspecialchars($t['smart_serial']) ?>: ${escapeHtml(device.serial || '—')} • <?= htmlspecialchars($t['smart_firmware']) ?>: ${escapeHtml(device.firmware || '—')}</div>
                </div>
                <div class="smart-hero-actions">
                    <span class="smart-badge ${escapeHtml(device.status || 'unknown')}">${smartStatusText(device.status)}</span>
                    ${cleanupButton}
                </div>
            </div>
            <div class="smart-updated"><?= htmlspecialchars($t['smart_last_updated']) ?>: ${formatSmartDate(device.updated_at)}</div>
            <div class="smart-metrics">
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_health']) ?></span><span class="smart-metric-value">${device.health_passed === true ? '<?= htmlspecialchars($t['smart_health_passed']) ?>' : device.health_passed === false ? '<?= htmlspecialchars($t['smart_health_failed']) ?>' : '<?= htmlspecialchars($t['smart_health_unknown']) ?>'}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_wear_used']) ?></span><span class="smart-metric-value">${formatSmartValue(device.wear_used_percent, '%')}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_life_left']) ?></span><span class="smart-metric-value">${formatSmartValue(device.life_remaining_percent, '%')}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_temperature']) ?></span><span class="smart-metric-value">${formatSmartValue(device.temperature_c, '°C')}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_power_on_hours']) ?></span><span class="smart-metric-value">${formatSmartHours(device.power_on_hours)}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_power_cycles']) ?></span><span class="smart-metric-value">${formatSmartValue(device.power_cycle_count)}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_data_written']) ?></span><span class="smart-metric-value">${device.data_written_bytes ? formatB(device.data_written_bytes) : '—'}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_crc_errors']) ?></span><span class="smart-metric-value">${formatSmartValue(device.crc_error_count)}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_error_log']) ?></span><span class="smart-metric-value">${formatSmartValue(device.error_log_count)}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_reallocated']) ?></span><span class="smart-metric-value">${formatSmartValue(device.reallocated_sectors)}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_reserved_blocks']) ?></span><span class="smart-metric-value">${formatSmartValue(device.reserved_block_count)}</span></div>
                <div class="smart-metric"><span class="smart-metric-label"><?= htmlspecialchars($t['smart_uncorrectable']) ?></span><span class="smart-metric-value">${formatSmartValue(device.uncorrectable_error_count)}</span></div>
            </div>
            ${notesHtml}
            <div class="smart-tests" style="margin-top:16px">
                <h4><?= htmlspecialchars($t['smart_last_tests']) ?></h4>
                ${testsHtml}
            </div>
        </div>`;
}

function displaySmartData(smart) {
    const smartGrid = document.getElementById('smartGrid');
    if (!smartGrid) return;
    lastKnownSmartData = smart || null;

    // Determine devices to display
    let devices = [];
    let updatedAt = null;

    if (smart && Array.isArray(smart.devices)) {
        // New format: { devices: [...], updated_at: ... }
        devices = smart.devices.filter(d => d && d.available);
        updatedAt = smart.updated_at || null;
    } else if (smart && smart.available) {
        // Legacy format: single device object
        devices = [smart];
        updatedAt = smart.updated_at || null;
    }

    if (devices.length === 0) {
        const updated = updatedAt ? `<div class="smart-updated"><?= htmlspecialchars($t['smart_last_updated']) ?>: ${formatSmartDate(updatedAt)}</div>` : '';
        smartGrid.innerHTML = `<div class="smart-card"><h3><?= htmlspecialchars($t['smart_primary_disk']) ?></h3><p class="no-data"><?= htmlspecialchars($t['smart_no_data']) ?></p>${updated}</div>`;
        return;
    }

    smartGrid.innerHTML = devices.map(device => buildSmartDeviceCard(device)).join('');
}
// Function to display latest data from localStorage on load
function displayLastKnownData() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const data = JSON.parse(stored);
            const now = Date.now();
            
            // Check data freshness (not older than DATA_MAX_AGE)
            if (data.timestamp && (now - data.timestamp) <= DATA_MAX_AGE) {
                // If cached data exists, display it
                if (data.lastMetrics) {
                    // Update CPU
                    if (data.lastMetrics.cpu_usage !== undefined) {
                        const cpuPercent = data.lastMetrics.cpu_usage;
                        drawCircle(cpuChart, cpuPercent);
                        cpuVal.textContent = cpuPercent.toFixed(1) + '%';
                    }
                    
                    // Update Memory
                    if (data.lastMetrics.memory_usage !== undefined) {
                        const memPercent = data.lastMetrics.memory_usage;
                        drawCircle(memChart, memPercent);
                        memVal.textContent = memPercent.toFixed(1) + '%';
                    }
                    
                    // Update Disk
                    if (data.lastMetrics.disk_usage !== undefined) {
                        const diskPercent = data.lastMetrics.disk_usage;
                        drawCircle(diskChart, diskPercent);
                        diskVal.textContent = diskPercent.toFixed(1) + '%';
                    }
                    
                    // Update Swap
                    if (data.lastMetrics.swap_usage !== undefined) {
                        const swapPercent = data.lastMetrics.swap_usage;
                        drawCircle(swapChart, swapPercent);
                        swapVal.textContent = swapPercent.toFixed(1) + '%';
                    }
                    
                    // Update Network
                    if (data.lastMetrics.network_speed !== undefined) {
                        const networkSpeed = data.lastMetrics.network_speed;
                        const networkPercent = Math.min((networkSpeed / 1048576) * 10, 100);
                        drawCircle(networkChart, networkPercent);
                        networkVal.textContent = formatNetworkSpeed(networkSpeed);
                    }
                    
                    // Update Uptime
                    if (data.lastMetrics.uptime) {
                        uptimeVal.textContent = data.lastMetrics.uptime;
                    }
                    
                    // Indicate that data was loaded from cache
                    const statusEl = document.getElementById('connectionStatus');
                    const textEl = document.getElementById('statusText');
                    statusEl.style.display = 'block';
                    statusEl.className = 'connection-status warning';
                    textEl.textContent = translations.ui[lang].loading_from_cache || 'Loading from cache...';
                    setTimeout(() => {
                        if (statusEl.style.display === 'block' && 
                            statusEl.className === 'connection-status warning' &&
                            textEl.textContent === (translations.ui[lang].loading_from_cache || 'Loading from cache...')) {
                            statusEl.style.display = 'none';
                        }
                    }, 2000);
                }
                
                // Display saved modules if any
                const cachedNginxModules = data.lastNginxModules || data.lastApacheModules;
                if (cachedNginxModules && cachedNginxModules.length > 0) {
                    lastNginxModules = cachedNginxModules;
                    fillTable('nginxBody', cachedNginxModules, 'name', 'status');
                    if (lastNginxSearch) {
                        filterTable('nginxSearch', 'nginxBody');
                    }
                }
                
                if (data.lastPhpModules && data.lastPhpModules.length > 0) {
                    fillTable('phpBody', data.lastPhpModules, 'name', 'status');
                    if (lastPhpSearch) {
                        filterTable('phpSearch', 'phpBody');
                    }
                }
                
                if (data.lastNetworkData && data.lastNetworkData.length > 0) {
                    displayNetworkData(data.lastNetworkData);
                }
                
                if (data.lastDrivesData && data.lastDrivesData.length > 0) {
                    displayDrivesData(data.lastDrivesData);
                }

                if (data.lastSmartData && (data.lastSmartData.available || Array.isArray(data.lastSmartData.devices))) {
                    displaySmartData(data.lastSmartData);
                }

                if (data.lastUptimeMonitoring) {
                    displayUptimeMonitoring(data.lastUptimeMonitoring, data.lastDockerContainers || []);
                }
            }
        }
    } catch (e) {
        console.error('Error loading last known data:', e);
    }
}

// Function to display network data
function displayNetworkData(networkData) {
    const networkGrid = document.getElementById('networkGrid');
    networkGrid.innerHTML = '';
    if (networkData && Array.isArray(networkData) && networkData.length > 0) {
        networkData.forEach(net => {
            networkGrid.insertAdjacentHTML('beforeend', `
                <div class="network-card">
                    <h4>${escapeHtml(net.name || translations.ui[lang].unknown_interface)}</h4>
                    <div class="network-stats">
                        <div class="network-stat">
                            <span class="network-label">${translations.ui[lang].download}:</span>
                            <span class="network-value">${formatNetworkSpeed(net.bytes_recv_rate || 0)}</span>
                        </div>
                        <div class="network-stat">
                            <span class="network-label">${translations.ui[lang].upload}:</span>
                            <span class="network-value">${formatNetworkSpeed(net.bytes_sent_rate || 0)}</span>
                        </div>
                        <div class="network-stat">
                            <span class="network-label">${translations.ui[lang].total}:</span>
                            <span class="network-value">${formatNetworkSpeed(net.total_rate || 0)}</span>
                        </div>
                        <div class="network-stat">
                            <span class="network-label">${translations.ui[lang].speed}:</span>
                            <span class="network-value">${formatNetworkSpeed(net.speed || 0)}</span>
                        </div>
                    </div>
                </div>`);
        });
    } else {
        networkGrid.innerHTML = '<p class="no-data"><?= htmlspecialchars($t['no_network_data']) ?></p>';
    }
}

// Helper function for HTML escaping
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Function to display disk data
function displayDrivesData(drivesData) {
    const drivesGrid = document.getElementById('drivesGrid');
    drivesGrid.innerHTML = '';
    if (drivesData && Array.isArray(drivesData) && drivesData.length > 0) {
        drivesData.forEach(dr => {
            drivesGrid.insertAdjacentHTML('beforeend', `
                <div class="drive-card">
                    <div class="drive-header">
                        <h3 class="drive-title">${escapeHtml(dr.letter)}</h3>
                        <span class="drive-type-badge">${escapeHtml(dr.type || '—')}</span>
                    </div>
                    <div class="drive-stats-grid">
                        <div class="drive-stat-box">
                            <span class="drive-stat-label"><?= htmlspecialchars($t['filesystem']) ?></span>
                            <span class="drive-stat-value mono">${escapeHtml(dr.fs_type || '—')}</span>
                        </div>
                        <div class="drive-stat-box">
                            <span class="drive-stat-label"><?= htmlspecialchars($t['source']) ?></span>
                            <span class="drive-stat-value mono">${escapeHtml(dr.source || '—')}</span>
                        </div>
                        <div class="drive-stat-box">
                            <span class="drive-stat-label"><?= htmlspecialchars($t['total_space']) ?></span>
                            <span class="drive-stat-value">${formatB(dr.total_space)}</span>
                        </div>
                        <div class="drive-stat-box">
                            <span class="drive-stat-label"><?= htmlspecialchars($t['free_space']) ?></span>
                            <span class="drive-stat-value">${formatB(dr.free_space)}</span>
                        </div>
                    </div>
                    <div class="progress-bar"><div class="progress" style="width:${dr.usage_percent}%"></div></div>
                    <p class="usage-text"><?= htmlspecialchars($t['used']) ?>: ${dr.usage_percent}%</p>
                </div>`);
        });
    } else {
        drivesGrid.innerHTML = '<p class="no-data"><?= htmlspecialchars($t['no_drives_found']) ?></p>';
    }
}

function uptimeStatusLabel(status) {
    if (status === 'up') return 'UP';
    if (status === 'down') return 'DOWN';
    return 'UNKNOWN';
}

function formatUptimeDate(ts) {
    if (!ts) return '—';
    const d = new Date(Number(ts) * 1000);
    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString();
}

function displayUptimeMonitoring(payload, dockerContainers) {
    const summaryEl = document.getElementById('uptimeSummary');
    const gridEl = document.getElementById('uptimeChecksGrid');
    if (!summaryEl || !gridEl) return;
    lastKnownUptimeMonitoring = payload || null;
    lastKnownDockerContainers = Array.isArray(dockerContainers) ? dockerContainers : [];
    const dockerById = {};
    const dockerByName = {};
    lastKnownDockerContainers.forEach(container => {
        if (container?.id) dockerById[String(container.id)] = container;
        if (container?.name) dockerByName[String(container.name)] = container;
    });
    const summary = payload?.summary || { up: 0, down: 0, unknown: 0, total: 0 };
    summaryEl.innerHTML = `
        <div class="uptime-summary-card up"><span>UP</span><strong>${summary.up || 0}</strong></div>
        <div class="uptime-summary-card down"><span>DOWN</span><strong>${summary.down || 0}</strong></div>
        <div class="uptime-summary-card unknown"><span>UNKNOWN</span><strong>${summary.unknown || 0}</strong></div>
        <div class="uptime-summary-card"><span>TOTAL</span><strong>${summary.total || 0}</strong></div>`;
    const checks = Array.isArray(payload?.checks) ? payload.checks : [];
    if (checks.length === 0) {
        gridEl.innerHTML = '<p class="no-data">No uptime checks yet</p>';
        return;
    }
    uptimeChecksById = {};
    checks.forEach(check => { uptimeChecksById[Number(check.id)] = check; });
    const dockerChecks = checks.filter(check => String(check.type || '') === 'docker');
    const otherChecks = checks.filter(check => String(check.type || '') !== 'docker');
    const renderCheck = check => {
        const status = check.last_status || 'unknown';
        const history = Array.isArray(check.history) ? check.history : [];
        const bars = history.map(item => `<span class="uptime-history-bar ${escapeHtml(item.status || 'unknown')}" title="${formatUptimeDate(item.checked_at)}"></span>`).join('');
        const canDelete = userRole >= 2 && Number(check.auto_discovered || 0) === 0;
        const isDocker = String(check.type || '') === 'docker';
        const dockerContainer = isDocker ? (dockerById[String(check.docker_container_id || '')] || dockerByName[String(check.docker_container_name || check.target || '')] || null) : null;
        const expanded = Boolean(expandedDockerChecks[Number(check.id)]);
        const dockerDetails = isDocker && dockerContainer ? buildDockerMetricsDetails(check, dockerContainer, expanded) : '';
        return `
            <div class="uptime-check-card ${escapeHtml(status)}">
                <div class="uptime-check-top">
                    <div>
                        <h3>${escapeHtml(check.name || '')}</h3>
                        <div class="uptime-check-target">${escapeHtml(check.target || '')}</div>
                    </div>
                    <span class="uptime-status ${escapeHtml(status)}">${uptimeStatusLabel(status)}</span>
                </div>
                <div class="uptime-check-meta">
                    <span>${escapeHtml(String(check.type || '').toUpperCase())}</span>
                    <span>${check.last_latency_ms ? `${check.last_latency_ms} ms` : '—'}</span>
                    <span>${check.last_http_status ? `HTTP ${check.last_http_status}` : formatUptimeDate(check.last_checked_at)}</span>
                </div>
                <div class="uptime-history">${bars || '<span class="uptime-history-empty">No checks</span>'}</div>
                <div class="uptime-check-message">${escapeHtml(check.last_message || '')}</div>
                <div class="uptime-card-actions">
                    ${isDocker && dockerContainer ? `<button class="btn-secondary icon-button" type="button" onclick="toggleDockerMetrics(${Number(check.id)})">${expanded ? '<svg viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg> Hide metrics' : '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/></svg> Show metrics'}</button>` : ''}
                    ${userRole >= 2 ? `<button class="btn-secondary icon-button" type="button" onclick="editUptimeCheckById(${Number(check.id)})"><svg viewBox="0 0 24 24"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg> Edit</button>${canDelete ? `<button class="btn-secondary icon-button" type="button" onclick="deleteUptimeCheck(${Number(check.id)})"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg> Delete</button>` : ''}` : ''}
                    <button class="btn-secondary icon-button" type="button" onclick="exportCheckAsJson(${Number(check.id)})"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export</button>
                </div>
                ${dockerDetails}
            </div>`;
    };
    const renderGroup = (key, title, groupChecks) => {
        if (groupChecks.length === 0) return '';
        const collapsed = Boolean(uptimeGroupCollapsed[key]);
        const up = groupChecks.filter(check => (check.last_status || 'unknown') === 'up').length;
        const down = groupChecks.filter(check => (check.last_status || 'unknown') === 'down').length;
        return `
            <div class="uptime-group ${collapsed ? 'collapsed' : ''}">
                <button class="uptime-group-header" type="button" onclick="toggleUptimeGroup('${key}')">
                    <span class="uptime-group-arrow">${collapsed ? '›' : '⌄'}</span>
                    <span class="uptime-group-title">${title}</span>
                    <span class="uptime-group-count">${groupChecks.length}</span>
                    <span class="uptime-group-pill up">${up}</span>
                    <span class="uptime-group-pill down">${down}</span>
                </button>
                <div class="uptime-group-body">${collapsed ? '' : groupChecks.map(renderCheck).join('')}</div>
            </div>`;
    };
    gridEl.innerHTML = `
        ${renderGroup('docker', 'Docker', dockerChecks)}
        ${renderGroup('other', 'Other monitors', otherChecks)}`;
    updateToggleAllDockerMetricsButton(dockerChecks);
    setTimeout(renderExpandedDockerCharts, 0);
}

function toggleUptimeGroup(key) {
    uptimeGroupCollapsed[key] = !uptimeGroupCollapsed[key];
    displayUptimeMonitoring(lastKnownUptimeMonitoring, lastKnownDockerContainers);
}

function buildDockerMetricsDetails(check, container, expanded) {
    if (!expanded) return '';
    const stats = container.stats || {};
    const checkId = Number(check.id);
    const period = dockerChartPeriods[checkId] || '24h';
    return `
        <div class="docker-metrics-panel">
            <div class="docker-metrics-layout">
                <div class="drive-stats-grid">
                    <div class="drive-stat-box"><span class="drive-stat-label">Image</span><span class="drive-stat-value">${escapeHtml(container.image || '—')}</span></div>
                    <div class="drive-stat-box"><span class="drive-stat-label">State</span><span class="drive-stat-value">${escapeHtml(container.health || container.state || '—')}</span></div>
                    <div class="drive-stat-box"><span class="drive-stat-label">CPU</span><span class="drive-stat-value">${Number(stats.cpu_percent || 0).toFixed(2)}%</span></div>
                    <div class="drive-stat-box"><span class="drive-stat-label">Memory</span><span class="drive-stat-value">${Number(stats.memory_percent || 0).toFixed(2)}%</span></div>
                    <div class="drive-stat-box"><span class="drive-stat-label">Memory usage</span><span class="drive-stat-value">${escapeHtml(stats.memory || '—')}</span></div>
                    <div class="drive-stat-box"><span class="drive-stat-label">PIDs</span><span class="drive-stat-value">${Number(stats.pids || 0)}</span></div>
                    <div class="drive-stat-box"><span class="drive-stat-label">Network IO</span><span class="drive-stat-value">${escapeHtml(stats.network_io || '—')}</span></div>
                    <div class="drive-stat-box"><span class="drive-stat-label">Block IO</span><span class="drive-stat-value">${escapeHtml(stats.block_io || '—')}</span></div>
                </div>
                <div class="docker-chart-panel">
                    <div class="docker-chart-toolbar">
                        <select onchange="setDockerChartPeriod(${checkId}, this.value)">
                            ${['1h','6h','24h','7d','30d'].map(p => `<option value="${p}" ${p === period ? 'selected' : ''}>${p}</option>`).join('')}
                        </select>
                    </div>
                    <div class="docker-chart-wrap">
                        <canvas id="dockerChart-${checkId}" data-container-id="${escapeHtml(container.id || '')}" data-period="${escapeHtml(period)}"></canvas>
                    </div>
                </div>
            </div>
        </div>`;
}

function toggleDockerMetrics(checkId) {
    expandedDockerChecks[Number(checkId)] = !expandedDockerChecks[Number(checkId)];
    displayUptimeMonitoring(lastKnownUptimeMonitoring, lastKnownDockerContainers);
}

function toggleAllDockerMetrics() {
    const checks = Array.isArray(lastKnownUptimeMonitoring?.checks) ? lastKnownUptimeMonitoring.checks : [];
    const dockerChecks = checks.filter(check => String(check.type || '') === 'docker');
    const allExpanded = dockerChecks.length > 0 && dockerChecks.every(check => expandedDockerChecks[Number(check.id)]);
    dockerChecks.forEach(check => {
        expandedDockerChecks[Number(check.id)] = !allExpanded;
    });
    displayUptimeMonitoring(lastKnownUptimeMonitoring, lastKnownDockerContainers);
}

function updateToggleAllDockerMetricsButton(dockerChecks) {
    const button = document.getElementById('toggleAllDockerMetricsBtn');
    if (!button) return;
    const allExpanded = dockerChecks.length > 0 && dockerChecks.every(check => expandedDockerChecks[Number(check.id)]);
    const label = button.querySelector('span');
    if (label) label.textContent = allExpanded ? 'Collapse all' : 'Expand all';
    button.disabled = dockerChecks.length === 0;
}

function setDockerChartPeriod(checkId, period) {
    dockerChartPeriods[Number(checkId)] = period;
    displayUptimeMonitoring(lastKnownUptimeMonitoring, lastKnownDockerContainers);
}

function renderExpandedDockerCharts() {
    document.querySelectorAll('canvas[id^="dockerChart-"]').forEach(canvas => {
        const checkId = Number(canvas.id.replace('dockerChart-', ''));
        const containerId = canvas.dataset.containerId || '';
        const period = canvas.dataset.period || '24h';
        if (containerId) {
            loadDockerMetricChart(checkId, containerId, period);
        }
    });
}

async function loadDockerMetricChart(checkId, containerId, period) {
    const canvas = document.getElementById(`dockerChart-${checkId}`);
    if (!canvas) return;
    const url = new URL(serverMonitorUrl.href);
    url.searchParams.set('action', 'docker_history');
    url.searchParams.set('container_id', containerId);
    url.searchParams.set('period', period);
    url.searchParams.set('t', Date.now());
    const resp = await fetch(url.href, { credentials: 'same-origin', cache: 'no-store' });
    if (!resp.ok) return;
    const data = await resp.json();
    const points = Array.isArray(data.points) ? data.points : [];
    const labels = points.map(point => formatDockerChartTime(point.created_at, period));
    const textColor = themeClass === 'dark' ? '#f0f0f0' : '#333';
    const gridColor = themeClass === 'dark' ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.1)';
    if (dockerMetricCharts[checkId]) {
        dockerMetricCharts[checkId].destroy();
        delete dockerMetricCharts[checkId];
    }
    dockerMetricCharts[checkId] = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'CPU %',
                    data: points.map(point => Number(point.cpu_percent || 0)),
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76,175,80,.12)',
                    borderWidth: 2,
                    tension: .5,
                    pointRadius: 0,
                    yAxisID: 'percent'
                },
                {
                    label: 'Memory %',
                    data: points.map(point => Number(point.memory_percent || 0)),
                    borderColor: primaryColor,
                    backgroundColor: 'rgba(33,150,243,.1)',
                    borderWidth: 2,
                    tension: .5,
                    pointRadius: 0,
                    yAxisID: 'percent'
                },
                {
                    label: 'PIDs',
                    data: points.map(point => Number(point.pids || 0)),
                    borderColor: '#FF9800',
                    backgroundColor: 'rgba(255,152,0,.1)',
                    borderWidth: 2,
                    tension: .5,
                    pointRadius: 0,
                    yAxisID: 'pids'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { color: textColor } } },
            scales: {
                x: { ticks: { color: textColor, maxTicksLimit: 8 }, grid: { color: gridColor } },
                percent: { type: 'linear', position: 'left', min: 0, suggestedMax: 100, ticks: { color: textColor }, grid: { color: gridColor } },
                pids: { type: 'linear', position: 'right', min: 0, ticks: { color: textColor }, grid: { drawOnChartArea: false } }
            }
        }
    });
}

function formatDockerChartTime(ts, period) {
    const d = new Date(Number(ts) * 1000);
    if (Number.isNaN(d.getTime())) return '';
    if (period === '7d' || period === '30d') {
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

async function runUptimeChecks() {
    const url = new URL(serverMonitorUrl.href);
    url.searchParams.set('action', 'run_uptime_checks');
    url.searchParams.set('t', Date.now());
    const resp = await fetch(url.href, { credentials: 'same-origin', cache: 'no-store' });
    if (resp.ok) {
        const data = await resp.json();
        displayUptimeMonitoring(data, data.docker_containers || lastKnownDockerContainers);
    }
}

function resetUptimeForm() {
    const form = document.getElementById('uptimeCheckForm');
    if (!form) return;
    form.reset();
    form.elements.id.value = '';
    form.elements.enabled.checked = true;
}

function toggleUptimeAddDropdown(e) {
    e.stopPropagation();
    const menu = document.getElementById('uptimeAddDropdownMenu');
    if (!menu) return;
    menu.classList.toggle('open');
}

function closeUptimeAddDropdown() {
    const menu = document.getElementById('uptimeAddDropdownMenu');
    if (menu) menu.classList.remove('open');
}

document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('uptimeAddDropdown');
    if (dropdown && !dropdown.contains(e.target)) closeUptimeAddDropdown();
});

function openUptimeCheckModal() {
    const modal = document.getElementById('uptimeCheckModal');
    if (!modal) return;
    modal.style.display = 'block';
    document.body.classList.add('modal-open');
}

function closeUptimeCheckModal() {
    const modal = document.getElementById('uptimeCheckModal');
    if (!modal) return;
    modal.style.display = 'none';
    resetUptimeForm();
    document.body.classList.remove('modal-open');
}

function editUptimeCheck(check) {
    const form = document.getElementById('uptimeCheckForm');
    if (!form) return;
    form.elements.id.value = check.id || '';
    form.elements.name.value = check.name || '';
    form.elements.type.value = check.type || 'http';
    form.elements.target.value = check.target || '';
    form.elements.method.value = check.method || 'GET';
    form.elements.expected_status.value = check.expected_status || 200;
    form.elements.keyword.value = check.keyword || '';
    form.elements.interval_sec.value = check.interval_sec || 60;
    form.elements.timeout_sec.value = check.timeout_sec || 10;
    form.elements.enabled.checked = Number(check.enabled || 0) === 1;
    document.querySelector('[data-section-target="uptime"]')?.click();
    openUptimeCheckModal();
}

function editUptimeCheckById(id) {
    const check = uptimeChecksById[Number(id)];
    if (check) editUptimeCheck(check);
}

async function saveUptimeCheck(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.enabled = form.elements.enabled.checked ? 1 : 0;
    const url = new URL(serverMonitorUrl.href);
    url.searchParams.set('action', 'save_uptime_check');
    const resp = await fetch(url.href, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    if (resp.ok) {
        closeUptimeCheckModal();
        await runUptimeChecks();
    }
}

async function deleteUptimeCheck(id) {
    const url = new URL(serverMonitorUrl.href);
    url.searchParams.set('action', 'delete_uptime_check');
    url.searchParams.set('id', id);
    const resp = await fetch(url.href, { method: 'POST', credentials: 'same-origin' });
    if (resp.ok) {
        await runUptimeChecks();
    }
}
function exportCheckAsJson(id) {
    const check = uptimeChecksById[Number(id)];
    if (!check) return;
    const exportData = {
        name: check.name || '',
        type: check.type || 'http',
        target: check.target || '',
        method: check.method || 'GET',
        expected_status: Number(check.expected_status) || 200,
        keyword: check.keyword || '',
        interval_sec: Number(check.interval_sec) || 60,
        timeout_sec: Number(check.timeout_sec) || 10,
        enabled: Number(check.enabled || 0) === 1
    };
    const json = JSON.stringify(exportData, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = (check.name || 'uptime-check').replace(/[^a-zA-Z0-9_-]/g, '_') + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function importChecksFromJson() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json,application/json';
    input.multiple = true;
    input.onchange = async (e) => {
        const files = Array.from(e.target.files || []);
        if (files.length === 0) return;
        let imported = 0;
        let errors = 0;
        for (const file of files) {
            try {
                const text = await file.text();
                const data = JSON.parse(text);
                const checks = Array.isArray(data) ? data : [data];
                for (const item of checks) {
                    if (!item.name || !item.target) { errors++; continue; }
                    const payload = {
                        name: item.name || '',
                        type: item.type || 'http',
                        target: item.target || '',
                        method: item.method || 'GET',
                        expected_status: item.expected_status || 200,
                        keyword: item.keyword || '',
                        interval_sec: item.interval_sec || 60,
                        timeout_sec: item.timeout_sec || 10,
                        enabled: item.enabled !== undefined ? (item.enabled ? 1 : 0) : 1
                    };
                    const url = new URL(serverMonitorUrl.href);
                    url.searchParams.set('action', 'save_uptime_check');
                    const resp = await fetch(url.href, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    if (resp.ok) imported++;
                    else errors++;
                }
            } catch (err) {
                errors++;
            }
        }
        if (imported > 0) await runUptimeChecks();
        alert(`Imported: ${imported}, Errors: ${errors}`);
    };
    input.click();
}

function fillTable(id, list, nameKey, statusKey) {
    const body = document.getElementById(id);
    body.innerHTML = '';
    
    if (!Array.isArray(list) || list.length === 0) {
        body.innerHTML = '<tr><td colspan="2">' + translations.ui[lang].no_data_available + '</td></tr>';
        return;
    }
    
    list.forEach(m => {
        const statusClass = m[statusKey] === '<?= $t['loaded'] ?>' ? 'badge' : 'badge badge-not-loaded';
        const dotClass = m[statusKey] === '<?= $t['loaded'] ?>' ? 'module-status-dot module-status-loaded' : 'module-status-dot module-status-not-loaded';
        
        body.insertAdjacentHTML('beforeend', 
            `<tr><td><span class="${dotClass}"></span>${m[nameKey] || ''}</td>
             <td><span class="${statusClass}">${m[statusKey] || translations.ui[lang].unknown}</span></td></tr>`);
    });
}

function openPhpInfo() {
    if (userRole < 2) {
        alert(translations.ui[lang].access_denied);
        return;
    }
    
    const m = document.getElementById('phpinfoModal'), c = document.getElementById('phpinfoContent');
    m.style.display = 'block';
    document.body.classList.add('modal-open');
    c.innerHTML = '<p style="padding:40px;text-align:center">' + translations.ui[lang].loading_ellipsis + '</p>';
    
    fetch('?phpinfo=1&ajax=1').then(r => {
        if (!r.ok) {
            throw new Error(translations.ui[lang].access_denied);
        }
        return r.json();
    }).then(d => {
        c.innerHTML = d.phpinfo || '<p>' + translations.ui[lang].no_data_available + '</p>';
    }).catch(e => {
        c.innerHTML = '<p>' + translations.ui[lang].error_loading_phpinfo + ': ' + e.message + '</p>';
        if (e.message === translations.ui[lang].access_denied) {
            setTimeout(() => {
                alert(translations.ui[lang].access_denied + '. ' + 'You do not have permission to view phpinfo.');
                closePhpInfo();
            }, 100);
        }
    });
}

function closePhpInfo() {
    document.getElementById('phpinfoModal').style.display = 'none';
    document.getElementById('phpinfoContent').innerHTML = '';
    document.body.classList.remove('modal-open');
    document.body.classList.remove('light', 'dark');
    document.body.classList.add(themeClass);
}

function prepareChartData(data, type, label) {
    const filteredData = data.filter(item => !item.isBreak);
    const rawData = filteredData.map(item => item.y);
    const windowSize = rawData.length > 120 ? 5 : (rawData.length > 60 ? 3 : 1);
    const numericData = windowSize > 1 ? rawData.map((value, index) => {
        let sum = 0;
        let count = 0;
        const half = Math.floor(windowSize / 2);
        for (let i = Math.max(0, index - half); i <= Math.min(rawData.length - 1, index + half); i++) {
            sum += Number(rawData[i] || 0);
            count++;
        }
        return count > 0 ? sum / count : value;
    }) : rawData;
    const labels = filteredData.map(item => item.x);
    
    const breakIndices = [];
    data.forEach((item, index) => {
        if (item.isBreak) {
            breakIndices.push(index);
        }
    });
    
    // Create main dataset
    const datasets = [{
        label: type === 'network' ? translations.ui[lang].network_speed : translations.ui[lang].usage,
        data: numericData,
        borderColor: primaryColor,
        backgroundColor: themeClass === 'dark' ? 'rgba(133, 181, 3, 0.2)' : 'rgba(133, 181, 3, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.5,
        pointRadius: 2,
        pointHoverRadius: 5
    }];
    
    // Add vertical lines for session breaks
    breakIndices.forEach(breakIndex => {
        if (breakIndex < filteredData.length) {
            datasets.push({
                type: 'line',
                label: '', // Empty label to hide from legend
                data: (() => {
                    // Create vertical line
                    const breakData = [];
                    for (let i = 0; i < filteredData.length; i++) {
                        if (i === breakIndex) {
                            // Add two points for vertical line
                            breakData.push({ x: filteredData[i].x, y: 0 });
                            breakData.push({ x: filteredData[i].x, y: type === 'network' ? Math.max(...numericData) : 100 });
                            breakData.push({ x: filteredData[i].x, y: null }); // Break
                        }
                    }
                    return breakData;
                })(),
                borderColor: 'rgba(255, 0, 0, 0.5)',
                borderWidth: 1,
                borderDash: [3, 3],
                pointRadius: 0,
                fill: false,
                spanGaps: true,
                showLine: true
            });
        }
    });
    
    return { labels, datasets };
}

let histDataCache = null;
let histDataCacheTime = 0;

async function fetchHistoricalDataIfNeeded() {
    const now = Date.now();
    if (histDataCache && (now - histDataCacheTime) < 300000) return histDataCache;
    try {
        const url = new URL(window.location.href);
        url.search = '?monitor_stats=1&period=24h&t=' + now;
        const resp = await fetch(url.href, { headers: {'Cache-Control': 'no-cache'}, cache: 'no-store', credentials: 'same-origin' });
        if (resp.ok) {
            const data = await resp.json();
            if (data && data.ok) { histDataCache = data; histDataCacheTime = now; }
        }
    } catch (e) {}
    return histDataCache;
}

async function openGraphModal(type) {
    if (autoZoomEnabled) {
        updateAutoZoomScales();
    }

    const m = document.getElementById('graphModal');
    const title = document.getElementById('graphTitle');
    const cleanupBtn = document.getElementById('graphCleanupBtn');
    m.style.display = 'block';
    document.body.classList.add('modal-open');
    if (cleanupBtn) {
        cleanupBtn.onclick = event => {
            event.stopPropagation();
            cleanupMetricData(type);
        };
    }

    const payload = await getGraphPayload(type);
    if (!payload) {
        return;
    }
    const { data, label, yLabel } = payload;
    title.textContent = label;

    if (graphChart) {
        graphChart.destroy();
        graphChart = null;
    }

    setTimeout(() => {
        const canvas = document.getElementById('graphChart');
        const ctx = canvas.getContext('2d');

        const { labels, datasets } = prepareChartData(data, type, label);

        const textColor = themeClass === 'dark' ? '#f0f0f0' : '#333';
        const gridColor = themeClass === 'dark' ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';

        // Get auto-zoom scale config
        let yScaleConfig;
        if (autoZoomEnabled && autoZoomScales[type]) {
            if (type === 'network') {
                // Network scales are in bytes, convert to KB for display
                yScaleConfig = {
                    min: autoZoomScales[type].min / 1024,
                    max: autoZoomScales[type].max / 1024
                };
            } else {
                // CPU, Memory, Disk, Swap are already in percentage (0-100)
                yScaleConfig = {
                    min: autoZoomScales[type].min,
                    max: autoZoomScales[type].max
                };
            }
        } else {
            yScaleConfig = {
                min: 0,
                max: type === 'network' ? undefined : 100,
                suggestedMin: 0,
                suggestedMax: type === 'network' ? undefined : 100
            };
        }
        
        graphChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: translations.ui[lang].time,
                            color: textColor,
                            font: { size: 14, weight: 'bold' }
                        },
                        ticks: { 
                            color: textColor,
                            maxTicksLimit: 12,
                            maxRotation: 45,
                            minRotation: 45,
                            font: { size: 11 }
                        },
                        grid: { 
                            color: gridColor,
                            drawBorder: false
                        }
                    },
                    y: {
                        display: true,
                        beginAtZero: yScaleConfig.min === 0,
                        title: {
                            display: true,
                            text: yLabel,
                            color: textColor,
                            font: { size: 14, weight: 'bold' }
                        },
                        ticks: {
                            color: textColor,
                            callback: function(value) {
                                if (type === 'network') {
                                    return value.toFixed(0) + ' KB/s';
                                }
                                return value.toFixed(0) + '%';
                            },
                            font: { size: 11 }
                        },
                        grid: {
                            color: gridColor,
                            drawBorder: false
                        },
                        min: yScaleConfig.min,
                        max: yScaleConfig.max
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: { 
                            color: textColor,
                            font: { size: 12 },
                            usePointStyle: true,
                            filter: function(item, chart) {
                                // Filter empty labels (vertical lines)
                                return item.text !== '';
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: themeClass === 'dark' ? 'rgba(45, 45, 45, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                        titleColor: textColor,
                        bodyColor: textColor,
                        borderColor: primaryColor,
                        borderWidth: 1,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex > 0) return null; // Skip vertical lines
                                if (type === 'network') {
                                    return `${context.dataset.label}: ${formatNetworkSpeed(context.parsed.y * 1024)}`;
                                }
                                return `${context.dataset.label}: ${context.parsed.y.toFixed(1)}%`;
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                elements: {
                    line: {
                        tension: 0.5
                    }
                },
                animation: {
                    duration: 750
                }
            }
        });
    }, 50);
}

async function getGraphPayload(type) {
    let data, label, yLabel;
    if (type === 'cpu') {
        data = cpuData;
        label = '<?= htmlspecialchars($t['cpu_usage']) ?>';
        yLabel = '<?= htmlspecialchars($t['cpu_usage']) ?>';
    } else if (type === 'memory') {
        data = memoryData;
        label = '<?= htmlspecialchars($t['memory_usage']) ?>';
        yLabel = '<?= htmlspecialchars($t['memory_usage']) ?>';
    } else if (type === 'disk') {
        data = diskData;
        label = '<?= htmlspecialchars($t['disk_usage']) ?>';
        yLabel = '<?= htmlspecialchars($t['disk_usage']) ?>';
    } else if (type === 'swap') {
        data = swapData;
        label = '<?= htmlspecialchars($t['swap_usage']) ?>';
        yLabel = '<?= htmlspecialchars($t['swap_usage']) ?>';
    } else if (type === 'network') {
        data = networkData.map(item => ({ ...item, y: item.y / 1024 }));
        label = '<?= htmlspecialchars($t['network_usage']) ?>';
        yLabel = '<?= htmlspecialchars($t['network_usage']) ?> (KB/s)';
    } else {
        return null;
    }
    const histData = await fetchHistoricalDataIfNeeded();
    if (histData && histData.ok && histData.system) {
        let dbPoints = null;
        if (type === 'cpu' && (histData.system.cpu_percent || []).length > 0) dbPoints = histData.system.cpu_percent.map(p => ({ x: p.x, y: p.y }));
        else if (type === 'memory' && (histData.system.memory_percent || []).length > 0) dbPoints = histData.system.memory_percent.map(p => ({ x: p.x, y: p.y }));
        else if (type === 'disk' && (histData.system.disk_io_percent || []).length > 0) dbPoints = histData.system.disk_io_percent.map(p => ({ x: p.x, y: p.y }));
        else if (type === 'swap' && (histData.system.swap_percent || []).length > 0) dbPoints = histData.system.swap_percent.map(p => ({ x: p.x, y: p.y }));
        else if (type === 'network') {
            const recv = histData.system.network_recv_kbps || [];
            const sent = histData.system.network_sent_kbps || [];
            if (recv.length > 0 || sent.length > 0) {
                const combined = {};
                recv.forEach(p => { combined[p.x] = (combined[p.x] || 0) + p.y; });
                sent.forEach(p => { combined[p.x] = (combined[p.x] || 0) + p.y; });
                dbPoints = Object.entries(combined).map(([x, y]) => ({ x, y }));
            }
        }
        if (dbPoints && dbPoints.length > 0) data = dbPoints;
    }
    return { data, label, yLabel };
}

function monitorChartMetricName(group, key) {
    const names = {
        cpu_percent: 'CPU %',
        memory_percent: 'Memory %',
        swap_percent: 'Swap %',
        disk_io_percent: 'Disk I/O %',
        network_recv_kbps: 'Network RX KB/s',
        network_sent_kbps: 'Network TX KB/s'
    };
    if (group === 'system' && names[key]) return names[key];
    if (group === 'disk') return '<?= htmlspecialchars($t['disk'] ?? 'Disk') ?> ' + String(key).replace(':', '').toUpperCase() + ':';
    return String(key).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function destroyMonitorCharts() {
    Object.values(monitorCharts).forEach(chart => {
        if (chart) chart.destroy();
    });
    monitorCharts = {};
}

function monitorChartOptions() {
    const textColor = themeClass === 'dark' ? '#f0f0f0' : '#333';
    const gridColor = themeClass === 'dark' ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.1)';
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
            x: { ticks: { color: textColor, maxTicksLimit: 10, maxRotation: 45, minRotation: 30 }, grid: { color: gridColor } },
            y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } }
        },
        plugins: {
            legend: { labels: { color: textColor, usePointStyle: true } },
            tooltip: {
                backgroundColor: themeClass === 'dark' ? 'rgba(45,45,45,.96)' : 'rgba(255,255,255,.96)',
                titleColor: textColor,
                bodyColor: textColor,
                borderColor: primaryColor,
                borderWidth: 1
            }
        }
    };
}

function renderMonitorChart(container, group, key, points, index) {
    if (!points || points.length === 0) return;
    const panel = document.createElement('article');
    panel.className = 'monitor-chart-panel';
    const head = document.createElement('div');
    head.className = 'monitor-chart-head';
    const title = document.createElement('h3');
    title.textContent = monitorChartMetricName(group, key);
    head.appendChild(title);
    if (userRole >= 2) {
        const cleanupBtn = document.createElement('button');
        cleanupBtn.className = 'monitor-chart-cleanup';
        cleanupBtn.type = 'button';
        cleanupBtn.title = monitorChartsLabels.delete;
        cleanupBtn.setAttribute('aria-label', monitorChartsLabels.delete);
        cleanupBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>';
        cleanupBtn.onclick = () => cleanupMonitorChartData(group, key);
        head.appendChild(cleanupBtn);
    }
    const wrap = document.createElement('div');
    wrap.className = 'monitor-chart-wrap';
    const canvas = document.createElement('canvas');
    wrap.appendChild(canvas);
    panel.appendChild(head);
    panel.appendChild(wrap);
    container.appendChild(panel);
    const palette = [primaryColor, '#2196f3', '#ff9800', '#9c27b0', '#00a676', '#f44336', '#607d8b'];
    const color = palette[index % palette.length];
    monitorCharts[group + ':' + key] = new Chart(canvas, {
        type: 'line',
        data: {
            labels: points.map(point => point.x),
            datasets: [{
                label: monitorChartMetricName(group, key),
                data: points.map(point => point.y),
                borderColor: color,
                backgroundColor: color + '22',
                borderWidth: 2,
                tension: .5,
                pointRadius: 0,
                pointHoverRadius: 3,
                fill: true
            }]
        },
        options: monitorChartOptions()
    });
}

function systemMonitorGroups(system) {
    return {
        cpu_percent: system.cpu_percent || [],
        memory_percent: system.memory_percent || [],
        swap_percent: system.swap_percent || [],
        disk_io_percent: system.disk_io_percent || [],
        network_recv_kbps: system.network_recv_kbps || [],
        network_sent_kbps: system.network_sent_kbps || []
    };
}

function renderMonitorCharts(data) {
    destroyMonitorCharts();
    const groups = [
        ['monitorSystemCharts', 'system', systemMonitorGroups(data.system || {})],
        ['monitorDiskCharts', 'disk', data.disk || {}],
        ['monitorHourlyCharts', 'hourly', data.hourly || {}]
    ];
    let rendered = 0;
    groups.forEach(([containerId, group, charts]) => {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '';
        Object.entries(charts).filter(([, points]) => points && points.length > 0).forEach(([key, points], index) => {
            renderMonitorChart(container, group, key, points, index);
            rendered++;
        });
    });
    const status = document.getElementById('monitorChartsStatus');
    if (!status) return;
    status.classList.remove('error');
    status.style.display = rendered > 0 ? 'none' : 'block';
    status.textContent = rendered > 0 ? '' : monitorChartsLabels.empty;
}

function loadMonitorCharts() {
    const status = document.getElementById('monitorChartsStatus');
    if (status) {
        status.classList.remove('error');
        status.style.display = 'block';
        status.textContent = monitorChartsLabels.loading;
    }
    fetch('index.php?monitor_stats=1&period=' + encodeURIComponent(monitorChartsPeriod) + '&t=' + Date.now(), { headers: { 'Cache-Control': 'no-cache' }, cache: 'no-store', credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            if (!data.ok) throw new Error(data.error || monitorChartsLabels.error);
            renderMonitorCharts(data);
            monitorChartsLoaded = true;
        })
        .catch(error => {
            if (!status) return;
            status.classList.add('error');
            status.style.display = 'block';
            status.textContent = monitorChartsLabels.error + ': ' + error.message;
        });
}

function cleanupMonitorChartData(group, key) {
    if (!confirm(monitorChartsLabels.confirm_delete)) return;
    const formData = new FormData();
    formData.append('cleanup_chart_data', '1');
    formData.append('chart_group', group);
    formData.append('chart_key', key);
    fetch(window.location.pathname + window.location.search, { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            if (!data.ok) throw new Error(data.error || monitorChartsLabels.error);
            alert(monitorChartsLabels.deleted);
            loadMonitorCharts();
        })
        .catch(error => alert(monitorChartsLabels.error + ': ' + error.message));
}

function createHoverPreview() {
    if (hoverPreviewEl) return;
    hoverPreviewEl = document.createElement('div');
    hoverPreviewEl.className = 'graph-hover-preview';
    hoverPreviewEl.innerHTML = '<div class="graph-hover-preview-title"></div><div class="graph-hover-preview-canvas-wrap"><canvas id="graphHoverPreviewCanvas"></canvas></div>';
    document.body.appendChild(hoverPreviewEl);
    hoverPreviewCanvas = hoverPreviewEl.querySelector('#graphHoverPreviewCanvas');
}

function moveHoverPreview(clientX, clientY) {
    if (!hoverPreviewEl) return;
    const margin = 12;
    const rect = hoverPreviewEl.getBoundingClientRect();
    let left = clientX + 16;
    let top = clientY + 16;
    if (left + rect.width + margin > window.innerWidth) left = clientX - rect.width - 16;
    if (top + rect.height + margin > window.innerHeight) top = clientY - rect.height - 16;
    left = Math.max(margin, left);
    top = Math.max(margin, top);
    hoverPreviewEl.style.left = left + 'px';
    hoverPreviewEl.style.top = top + 'px';
}

function hideHoverPreview() {
    if (hoverPreviewChart) {
        hoverPreviewChart.destroy();
        hoverPreviewChart = null;
    }
    if (hoverPreviewEl) {
        hoverPreviewEl.classList.remove('visible');
    }
}

async function showHoverPreview(type, event) {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches || window.innerWidth <= 768) return;
    createHoverPreview();
    moveHoverPreview(event.clientX, event.clientY);
    hoverPreviewEl.querySelector('.graph-hover-preview-title').textContent = event.currentTarget.closest('.stat-card')?.querySelector('h3')?.textContent || '';
    hoverPreviewEl.classList.add('visible');
    const requestId = ++hoverPreviewRequestId;
    const payload = await getGraphPayload(type);
    if (!payload || requestId !== hoverPreviewRequestId || !hoverPreviewEl.classList.contains('visible')) return;
    const { data } = payload;
    const chartData = prepareChartData(data, type, '');
    const textColor = themeClass === 'dark' ? '#f0f0f0' : '#333';
    const gridColor = themeClass === 'dark' ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.08)';
    let yScaleConfig;
    if (autoZoomEnabled && autoZoomScales[type]) {
        yScaleConfig = type === 'network' ? { min: autoZoomScales[type].min / 1024, max: autoZoomScales[type].max / 1024 } : { min: autoZoomScales[type].min, max: autoZoomScales[type].max };
    } else {
        yScaleConfig = { min: 0, max: type === 'network' ? undefined : 100 };
    }
    if (hoverPreviewChart) hoverPreviewChart.destroy();
    hoverPreviewChart = new Chart(hoverPreviewCanvas.getContext('2d'), {
        type: 'line',
        data: { labels: chartData.labels, datasets: chartData.datasets.map((set, idx) => idx === 0 ? set : { ...set, borderWidth: 0.7 }) },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 220 },
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            elements: { point: { radius: 0 } },
            scales: {
                x: { ticks: { display: false }, grid: { color: gridColor, drawBorder: false } },
                y: { ticks: { color: textColor, maxTicksLimit: 4 }, grid: { color: gridColor, drawBorder: false }, min: yScaleConfig.min, max: yScaleConfig.max }
            }
        }
    });
}

function closeGraphModal() {
    document.getElementById('graphModal').style.display = 'none';
    document.body.classList.remove('modal-open');
    document.body.classList.remove('light', 'dark');
    document.body.classList.add(themeClass);
    if (graphChart) {
        graphChart.destroy();
        graphChart = null;
    }
}

function setActiveMonitorSection(sectionId) {
    const panels = document.querySelectorAll('[data-section-panel]');
    const buttons = document.querySelectorAll('[data-section-target]');
    let resolved = sectionId || 'graphs';
    const known = new Set(Array.from(panels).map(panel => panel.getAttribute('data-section-panel')));
    if (!known.has(resolved)) {
        resolved = 'graphs';
    }
    panels.forEach(panel => {
        panel.classList.toggle('active', panel.getAttribute('data-section-panel') === resolved);
    });
    buttons.forEach(button => {
        const isActive = button.getAttribute('data-section-target') === resolved;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
    try {
        localStorage.setItem(SECTION_STORAGE_KEY, resolved);
    } catch (e) {}
    if (resolved === 'monitorCharts' && !monitorChartsLoaded) {
        loadMonitorCharts();
    }
}

function restoreActiveMonitorSection() {
    let sectionId = 'graphs';
    const params = new URLSearchParams(window.location.search);
    const requestedSection = params.get('section');
    if (requestedSection) {
        setActiveMonitorSection(requestedSection);
        return;
    }
    try {
        const saved = localStorage.getItem(SECTION_STORAGE_KEY);
        if (saved) {
            sectionId = saved;
        }
    } catch (e) {}
    setActiveMonitorSection(sectionId);
}

function cleanupSmartDevice(devicePath) {
    if (!devicePath || !confirm(translations.ui[lang].cleanup_device_confirm || 'Delete all monitoring data for this device?')) {
        return;
    }

    const formData = new FormData();
    formData.append('cleanup_smart_device', '1');
    formData.append('device_path', devicePath);

    fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            alert(translations.ui[lang].cleanup_success || 'Data deleted successfully');
            refreshData();
        } else {
            alert(translations.ui[lang].cleanup_error || 'Error deleting data');
        }
    })
    .catch(error => {
        console.error('Cleanup error:', error);
        alert(translations.ui[lang].cleanup_error || 'Error deleting data');
    });
}

function cleanupMetricData(metricType) {
    const metricNames = {
        'cpu': 'CPU',
        'memory': '<?= htmlspecialchars($t['memory_usage'] ?? 'Memory') ?>',
        'disk': '<?= htmlspecialchars($t['disk_usage'] ?? 'Disk') ?>',
        'network': '<?= htmlspecialchars($t['network_usage'] ?? 'Network') ?>',
        'swap': 'Swap'
    };

    const metricName = metricNames[metricType] || metricType;
    if (!confirm((translations.ui[lang].cleanup_device_confirm || 'Delete all monitoring data for this device?').replace('device', metricName))) {
        return;
    }

    const formData = new FormData();
    formData.append('cleanup_metric_data', '1');
    formData.append('metric_type', metricType);

    fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            alert(translations.ui[lang].cleanup_success || 'Data deleted successfully');
            // Clear local data arrays
            if (metricType === 'cpu') cpuData = [];
            else if (metricType === 'memory') memoryData = [];
            else if (metricType === 'disk') diskData = [];
            else if (metricType === 'network') networkData = [];
            else if (metricType === 'swap') swapData = [];

            saveStoredData();
            refreshData();
        } else {
            alert(translations.ui[lang].cleanup_error || 'Error deleting data');
        }
    })
    .catch(error => {
        console.error('Cleanup error:', error);
        alert(translations.ui[lang].cleanup_error || 'Error deleting data');
    });
}

function openCleanupTimePeriodModal() {
    const modal = document.getElementById('cleanupTimePeriodModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

function closeCleanupTimePeriodModal() {
    const modal = document.getElementById('cleanupTimePeriodModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('cleanupStartTime').value = '';
        document.getElementById('cleanupEndTime').value = '';
    }
}

function submitCleanupTimePeriod(event) {
    event.preventDefault();

    const startTimeStr = document.getElementById('cleanupStartTime').value;
    const endTimeStr = document.getElementById('cleanupEndTime').value;

    if (!startTimeStr || !endTimeStr) {
        alert(translations.ui[lang].select_time_period || 'Select time period');
        return;
    }

    const startTime = Math.floor(new Date(startTimeStr).getTime() / 1000);
    const endTime = Math.floor(new Date(endTimeStr).getTime() / 1000);

    if (startTime >= endTime) {
        alert('Invalid time range');
        return;
    }

    if (!confirm(translations.ui[lang].cleanup_time_confirm || 'Delete all monitoring data for the selected period?')) {
        return;
    }

    const formData = new FormData();
    formData.append('cleanup_time_period', '1');
    formData.append('start_time', startTime);
    formData.append('end_time', endTime);

    fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            alert(translations.ui[lang].cleanup_success || 'Data deleted successfully');
            closeCleanupTimePeriodModal();
            refreshData();
        } else {
            alert(translations.ui[lang].cleanup_error || 'Error deleting data');
        }
    })
    .catch(error => {
        console.error('Cleanup error:', error);
        alert(translations.ui[lang].cleanup_error || 'Error deleting data');
    });
}

function refreshData() {
    // Reset retry counter
    retryCount = 0;

    // Remove manual refresh button if present
    const manualBtn = document.getElementById('manualRetryBtn');
    if (manualBtn) {
        manualBtn.remove();
    }

    // Show refresh indicator
    const statusEl = document.getElementById('connectionStatus');
    const textEl = document.getElementById('statusText');
    statusEl.style.display = 'block';
    statusEl.className = 'connection-status warning';
    textEl.textContent = translations.ui[lang].refreshing || 'Refreshing...';

    // Load data
    loadData(true);
}
function handleBeforeUnload() {
    lastSessionEnd = Date.now();
    saveStoredData();
}

document.getElementById('phpinfoModal').addEventListener('click', e => {
    if (e.target.id === 'phpinfoModal') closePhpInfo();
});

document.getElementById('graphModal').addEventListener('click', e => {
    if (e.target.id === 'graphModal') closeGraphModal();
});

document.addEventListener('DOMContentLoaded', () => {
    cpuChart = document.getElementById('cpuChart');
    memChart = document.getElementById('memChart');
    diskChart = document.getElementById('diskChart');
    swapChart = document.getElementById('swapChart');
    networkChart = document.getElementById('networkChart');

    document.querySelectorAll('[data-section-target]').forEach(button => {
        button.addEventListener('click', () => setActiveMonitorSection(button.getAttribute('data-section-target')));
    });
    const monitorChartsPeriodEl = document.getElementById('monitorChartsPeriod');
    if (monitorChartsPeriodEl) {
        monitorChartsPeriodEl.addEventListener('change', event => {
            monitorChartsPeriod = event.target.value;
            monitorChartsLoaded = false;
            loadMonitorCharts();
        });
    }
    restoreActiveMonitorSection();

    // Load auto-zoom preference from localStorage
    if (userRole >= 2) {
        try {
            const savedAutoZoom = localStorage.getItem('autoZoomEnabled');
            if (savedAutoZoom !== null) {
                autoZoomEnabled = savedAutoZoom === 'true';
            }
        } catch (e) {}
        
        // Update toggle button state
        const toggle = document.getElementById('autoZoomToggle');
        if (toggle) {
            if (autoZoomEnabled) {
                toggle.classList.add('active');
            } else {
                toggle.classList.remove('active');
            }
        }
    } else {
        // Hide toggle for non-admins
        const toggle = document.getElementById('autoZoomToggle');
        if (toggle) {
            toggle.style.display = 'none';
        }
    }

    currentSessionStart = Date.now();
    loadStoredData();
    
    // Display latest known data from cache
    displayLastKnownData();
    
    drawCircle(cpuChart, 0);
    drawCircle(memChart, 0);
    drawCircle(diskChart, 0);
    drawCircle(swapChart, 0);
    drawCircle(networkChart, 0);
    
    loadData();
    
    const intervalId = setInterval(loadData, UPDATE_INTERVAL);

    window.addEventListener('beforeunload', handleBeforeUnload);
    window.addEventListener('pagehide', handleBeforeUnload);

    <?php if ($userRole >= 2): ?>
    window.cleanupSmartDevice = cleanupSmartDevice;
    window.cleanupMetricData = cleanupMetricData;
    window.cleanupMonitorChartData = cleanupMonitorChartData;
    window.deleteUptimeCheck = deleteUptimeCheck;
    window.openCleanupTimePeriodModal = openCleanupTimePeriodModal;
    window.closeCleanupTimePeriodModal = closeCleanupTimePeriodModal;
    window.submitCleanupTimePeriod = submitCleanupTimePeriod;
    <?php endif; ?>
    
    let lastDataCount = {
        cpu: cpuData.length,
        memory: memoryData.length,
        disk: diskData.length,
        network: networkData.length,
        swap: swapData.length
    };
    
    setInterval(() => {
        const changes = {
            cpu: cpuData.length !== lastDataCount.cpu,
            memory: memoryData.length !== lastDataCount.memory,
            disk: diskData.length !== lastDataCount.disk,
            network: networkData.length !== lastDataCount.network,
            swap: swapData.length !== lastDataCount.swap
        };
        
        if (changes.cpu || changes.memory || changes.disk || changes.network || changes.swap) {
            saveStoredData();
            lastDataCount = {
                cpu: cpuData.length,
                memory: memoryData.length,
                disk: diskData.length,
                network: networkData.length,
                swap: swapData.length
            };
        }
    }, 30000);
    const hoverGraphTargets = [
        { id: 'cpuChart', type: 'cpu' },
        { id: 'memChart', type: 'memory' },
        { id: 'diskChart', type: 'disk' },
        { id: 'swapChart', type: 'swap' },
        { id: 'networkChart', type: 'network' }
    ];
    hoverGraphTargets.forEach(({ id, type }) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('mouseenter', e => showHoverPreview(type, e));
        el.addEventListener('mousemove', e => moveHoverPreview(e.clientX, e.clientY));
        el.addEventListener('mouseleave', hideHoverPreview);
    });
    window.addEventListener('scroll', hideHoverPreview, { passive: true });
    window.addEventListener('resize', hideHoverPreview);
});
</script>
