<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
header('Content-Type: text/html; charset=UTF-8');
require_once 'translations.php';

$lang = isset($_GET['lang']) && isset($translations[$_GET['lang']]) ? $_GET['lang'] : 'en';
$themeRaw = isset($_GET['theme']) ? strtolower($_GET['theme']) : 'light';
$theme = $themeRaw === 'dark' ? 'dark' : 'light';
$color = isset($_GET['color']) ? htmlspecialchars($_GET['color']) : '#85b503';
$t = $translations[$lang];

$labels = [
    'en' => [
        'title' => 'Monitoring Charts',
        'back' => 'Back',
        'period' => 'Period',
        'period_1h' => '1h',
        'period_6h' => '6h',
        'period_24h' => '24h',
        'period_7d' => '7d',
        'period_30d' => '30d',
        'disk' => 'Disk Usage',
        'hourly' => 'Application Metrics',
        'system' => 'System Metrics',
        'disk_prefix' => 'Disk',
        'fullscreen' => 'Fullscreen',
        'disk_axis' => 'Used, %',
        'hourly_axis' => 'Average value',
        'system_axis' => 'Percent / KB/s',
        'loading' => 'Loading charts...',
        'empty' => 'No historical data available',
        'error' => 'Error loading statistics',
    ],
    'ru' => [
        'title' => 'Графики мониторинга',
        'back' => 'Назад',
        'period' => 'Период',
        'period_1h' => '1ч',
        'period_6h' => '6ч',
        'period_24h' => '24ч',
        'period_7d' => '7д',
        'period_30d' => '30д',
        'disk' => 'Использование дисков',
        'hourly' => 'Метрики приложений',
        'system' => 'Системные метрики',
        'disk_prefix' => 'Диск',
        'fullscreen' => 'На весь экран',
        'disk_axis' => 'Использовано, %',
        'hourly_axis' => 'Среднее значение',
        'system_axis' => 'Проценты / KB/s',
        'loading' => 'Загрузка графиков...',
        'empty' => 'Исторических данных нет',
        'error' => 'Ошибка загрузки статистики',
    ],
    'uk' => [
        'title' => 'Графіки моніторингу',
        'back' => 'Назад',
        'period' => 'Період',
        'period_1h' => '1г',
        'period_6h' => '6г',
        'period_24h' => '24г',
        'period_7d' => '7д',
        'period_30d' => '30д',
        'disk' => 'Використання дисків',
        'hourly' => 'Метрики застосунків',
        'system' => 'Системні метрики',
        'disk_prefix' => 'Диск',
        'fullscreen' => 'На весь екран',
        'disk_axis' => 'Використано, %',
        'hourly_axis' => 'Середнє значення',
        'system_axis' => 'Відсотки / KB/s',
        'loading' => 'Завантаження графіків...',
        'empty' => 'Історичних даних немає',
        'error' => 'Помилка завантаження статистики',
    ],
    'be' => [
        'title' => 'Графікі маніторынгу',
        'back' => 'Назад',
        'period' => 'Перыяд',
        'period_1h' => '1г',
        'period_6h' => '6г',
        'period_24h' => '24г',
        'period_7d' => '7д',
        'period_30d' => '30д',
        'disk' => 'Выкарыстанне дыскаў',
        'hourly' => 'Метрыкі праграм',
        'system' => 'Сістэмныя метрыкі',
        'disk_prefix' => 'Дыск',
        'fullscreen' => 'На ўвесь экран',
        'disk_axis' => 'Выкарыстана, %',
        'hourly_axis' => 'Сярэдняе значэнне',
        'system_axis' => 'Працэнты / KB/s',
        'loading' => 'Загрузка графікаў...',
        'empty' => 'Гістарычных даных няма',
        'error' => 'Памылка загрузкі статыстыкі',
    ],
];
$l = $labels[$lang] ?? $labels['en'];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($l['title']) ?></title>
<link rel="stylesheet" href="style.css?v=7">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root { --primary-color: <?= $color ?>; --error-color: #ff4444; --warning-color: #ff8800; }
body { min-height: 100vh; }
.stats-page-header {
    display: grid;
    grid-template-columns: 1fr minmax(0, auto) 1fr;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}
.stats-page-header h1 {
    text-align: center;
    font-size: 1.8rem;
    line-height: 1.2;
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.stats-page-actions { display: flex; align-items: center; gap: 10px; justify-self: end; }
.stats-page-left { justify-self: start; }
.back-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    border: 1px solid color-mix(in srgb, var(--border-color) 70%, var(--primary-color) 30%);
    color: var(--text-color);
    text-decoration: none;
    background: color-mix(in srgb, var(--card-bg) 88%, #111 12%);
    transition: all .2s ease;
}
.back-link:hover { border-color: var(--primary-color); color: var(--primary-color); transform: translateY(-1px); }
.period-select {
    min-width: 96px;
    border: 1px solid color-mix(in srgb, var(--border-color) 70%, var(--primary-color) 30%);
    background: color-mix(in srgb, var(--card-bg) 90%, #111 10%);
    color: var(--text-color);
    border-radius: 10px;
    padding: 9px 12px;
    font-weight: 600;
}
.chart-panel {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 18px;
    margin-bottom: 22px;
    box-shadow: 0 2px 12px rgba(0,0,0,.1);
}
.disk-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}
.pair-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}
.chart-panel h2 {
    margin-bottom: 14px;
    color: var(--primary-color);
    font-size: 1.1rem;
}
.chart-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.chart-head h2 { margin: 0; }
.chart-fullscreen-btn {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    border: 1px solid color-mix(in srgb, var(--border-color) 68%, var(--primary-color) 32%);
    background: color-mix(in srgb, var(--card-bg) 88%, #111 12%);
    color: var(--text-color);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.chart-fullscreen-btn:hover { color: var(--primary-color); border-color: var(--primary-color); }
.charts-stack { margin-bottom: 24px; }
.chart-wrap {
    position: relative;
    width: 100%;
    height: 320px;
}
.chart-wrap canvas { image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges; }
.chart-panel.panel-expanded {
    position: fixed;
    inset: 0;
    z-index: 9999;
    margin: 0;
    border-radius: 0;
    overflow: auto;
    display: flex;
    flex-direction: column;
    padding: 20px;
}
.chart-panel.panel-expanded .chart-wrap {
    flex: 1;
    height: auto;
    min-height: 0;
}
.stats-status {
    padding: 12px 14px;
    margin-bottom: 18px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--card-bg);
    color: var(--text-secondary);
}
.stats-status.error {
    border-color: var(--error-color);
    color: var(--error-color);
}
@media (max-width: 768px) {
    .stats-page-header { gap: 10px; }
    .stats-page-header h1 { font-size: 1.3rem; }
    .chart-wrap { height: 300px; }
    .disk-grid { grid-template-columns: 1fr; }
    .pair-grid { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
    .disk-grid { grid-template-columns: 1fr; }
    .pair-grid { grid-template-columns: 1fr; }
    .chart-wrap { height: 260px; }
}
</style>
</head>
<body class="<?= $theme ?>">
<canvas id="constellation-bg"></canvas>
<div class="container">
    <div class="stats-page-header">
        <div class="stats-page-left"></div>
        <h1><?= htmlspecialchars($l['title']) ?></h1>
        <div class="stats-page-actions">
            <label for="periodSelect"><?= htmlspecialchars($l['period']) ?></label>
            <select id="periodSelect" class="period-select">
                <option value="1h"><?= htmlspecialchars($l['period_1h']) ?></option>
                <option value="6h"><?= htmlspecialchars($l['period_6h']) ?></option>
                <option value="24h" selected><?= htmlspecialchars($l['period_24h']) ?></option>
                <option value="7d"><?= htmlspecialchars($l['period_7d']) ?></option>
                <option value="30d"><?= htmlspecialchars($l['period_30d']) ?></option>
            </select>
            <a class="back-link" href="index.php?lang=<?= urlencode($lang) ?>&theme=<?= urlencode($theme) ?>&color=<?= urlencode($color) ?>" aria-label="<?= htmlspecialchars($l['back']) ?>" title="<?= htmlspecialchars($l['back']) ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/><path d="M9 12h12"/></svg>
            </a>
        </div>
    </div>

    <div id="statsStatus" class="stats-status"><?= htmlspecialchars($l['loading']) ?></div>

    <section>
        <h2><?= htmlspecialchars($l['disk']) ?></h2>
        <div id="diskCharts" class="charts-stack"></div>
    </section>

    <section>
        <h2><?= htmlspecialchars($l['hourly']) ?></h2>
        <div id="hourlyCharts" class="charts-stack"></div>
    </section>

    <section>
        <h2><?= htmlspecialchars($l['system']) ?></h2>
        <div id="systemCharts" class="charts-stack"></div>
    </section>
</div>

<script>
const themeClass = '<?= $theme ?>';
const primaryColor = '<?= $color ?>';
const labels = <?= json_encode($l, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const metricNames = {
    cpu_percent: themeClass === 'dark' ? 'CPU %' : 'CPU %',
    memory_percent: themeClass === 'dark' ? 'Memory %' : 'Memory %',
    swap_percent: themeClass === 'dark' ? 'Swap %' : 'Swap %',
    disk_io_percent: 'Disk I/O %',
    network_recv_kbps: 'Network RX KB/s',
    network_sent_kbps: 'Network TX KB/s'
};
const palette = [
    primaryColor, '#2196f3', '#ff9800', '#9c27b0', '#00a676',
    '#f44336', '#607d8b', '#795548', '#009688', '#e91e63'
];
const textColor = themeClass === 'dark' ? '#f0f0f0' : '#333';
const gridColor = themeClass === 'dark' ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.1)';
Chart.defaults.devicePixelRatio = Math.min(2, window.devicePixelRatio || 1);
Chart.defaults.font.family = 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif';
let selectedPeriod = '24h';
const panelCharts = new Map();

function chartOptions(yTitle) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
            x: {
                ticks: { color: textColor, maxTicksLimit: 10, maxRotation: 45, minRotation: 30 },
                grid: { color: gridColor }
            },
            y: {
                beginAtZero: true,
                title: { display: true, text: yTitle, color: textColor },
                ticks: { color: textColor },
                grid: { color: gridColor }
            }
        },
        plugins: {
            decimation: { enabled: true, algorithm: 'lttb', samples: 200 },
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

function collectLabels(groups) {
    const seen = new Set();
    Object.values(groups).forEach(points => {
        points.forEach(point => seen.add(point.x));
    });
    return Array.from(seen);
}

function buildGroupedDatasets(groups, allLabels) {
    return Object.entries(groups).map(([name, points], index) => {
        const byLabel = new Map(points.map(point => [point.x, point.y]));
        return {
            label: name,
            data: allLabels.map(label => byLabel.has(label) ? byLabel.get(label) : null),
            borderColor: palette[index % palette.length],
            backgroundColor: palette[index % palette.length] + '22',
            borderWidth: 2,
            tension: .5,
            pointRadius: 1.5,
            spanGaps: true
        };
    });
}

function makeSingleDatasetChart(canvas, name, points, yTitle, index) {
    const color = palette[index % palette.length];
    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: points.map(point => point.x),
            datasets: [{
                label: name,
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
        options: chartOptions(yTitle)
    });
}

function getGridClass(containerId, itemCount) {
    if (containerId === 'diskCharts') return itemCount <= 4 ? 'pair-grid' : 'disk-grid';
    return 'pair-grid';
}

function renderChartGroup(containerId, groups, yTitle) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    const entries = Object.entries(groups).filter(([, points]) => points && points.length > 0);
    container.className = 'charts-stack ' + getGridClass(containerId, entries.length);
    entries.forEach(([name, points], index) => {
        if (!points || points.length === 0) return;
        const panel = document.createElement('article');
        panel.className = 'chart-panel';
        const head = document.createElement('div');
        head.className = 'chart-head';
        const title = document.createElement('h2');
        title.textContent = prettyName(containerId, name);
        const fullBtn = document.createElement('button');
        fullBtn.className = 'chart-fullscreen-btn';
        fullBtn.type = 'button';
        fullBtn.title = labels.fullscreen;
        fullBtn.setAttribute('aria-label', labels.fullscreen);
        fullBtn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 3H3v5"/><path d="M16 3h5v5"/><path d="M21 16v5h-5"/><path d="M3 16v5h5"/></svg>';
        fullBtn.onclick = () => togglePanelFullscreen(panel);
        const wrap = document.createElement('div');
        wrap.className = 'chart-wrap';
        const canvas = document.createElement('canvas');
        wrap.appendChild(canvas);
        head.appendChild(title);
        head.appendChild(fullBtn);
        panel.appendChild(head);
        panel.appendChild(wrap);
        container.appendChild(panel);
        const chart = makeSingleDatasetChart(canvas, prettyName(containerId, name), points, yTitle, index);
        panelCharts.set(panel, chart);
    });
}
function togglePanelFullscreen(panel) {
    const isExpanded = panel.classList.toggle('panel-expanded');
    const btn = panel.querySelector('.chart-fullscreen-btn');
    if (btn) {
        btn.innerHTML = isExpanded
            ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 3v5H3"/><path d="M16 3v5h5"/><path d="M21 16h-5v5"/><path d="M3 16h5v5"/></svg>'
            : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 3H3v5"/><path d="M16 3h5v5"/><path d="M21 16v5h-5"/><path d="M3 16v5h5"/></svg>';
    }
    const chart = panelCharts.get(panel);
    if (chart) setTimeout(() => chart.resize(), 50);
}
function prettyName(containerId, name) {
    if (containerId === 'diskCharts') return `${labels.disk_prefix} ${String(name).replace(':','').toUpperCase()}:`;
    if (containerId === 'systemCharts' && metricNames[name]) return metricNames[name];
    return String(name).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function systemGroups(system) {
    return {
        cpu_percent: system.cpu_percent || [],
        memory_percent: system.memory_percent || [],
        swap_percent: system.swap_percent || [],
        disk_io_percent: system.disk_io_percent || [],
        network_recv_kbps: system.network_recv_kbps || [],
        network_sent_kbps: system.network_sent_kbps || []
    };
}

function loadStats() {
fetch('index.php?monitor_stats=1&period=' + encodeURIComponent(selectedPeriod) + '&t=' + Date.now(), { headers: { 'Cache-Control': 'no-cache' }, cache: 'no-store', credentials: 'same-origin' })
    .then(response => {
        if (!response.ok) throw new Error(response.status + ' ' + response.statusText);
        return response.json();
    })
    .then(data => {
        if (!data.ok) throw new Error(data.error || labels.error);
        const hasDisk = data.disk && Object.keys(data.disk).length > 0;
        const hasHourly = data.hourly && Object.keys(data.hourly).length > 0;
        const hasSystem = data.system && Object.values(data.system).some(points => points.length > 0);

        if (!hasDisk && !hasHourly && !hasSystem) {
            document.getElementById('statsStatus').textContent = labels.empty;
            return;
        }

        document.getElementById('statsStatus').style.display = 'none';
        renderChartGroup('diskCharts', data.disk || {}, labels.disk_axis);
        renderChartGroup('hourlyCharts', data.hourly || {}, labels.hourly_axis);
        renderChartGroup('systemCharts', systemGroups(data.system || {}), labels.system_axis);
    })
    .catch(error => {
        const status = document.getElementById('statsStatus');
        status.classList.add('error');
        status.textContent = labels.error + ': ' + error.message;
    });
}
document.getElementById('periodSelect').addEventListener('change', e => {
    selectedPeriod = e.target.value;
    document.getElementById('statsStatus').classList.remove('error');
    document.getElementById('statsStatus').style.display = 'block';
    document.getElementById('statsStatus').textContent = labels.loading;
    loadStats();
});
loadStats();
</script>

<script>
(function() {
    const canvas = document.getElementById('constellation-bg');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const primaryColor = '<?= $color ?>';
    let stars = [];
    function getColorWithOpacity(opacity) {
        let r = 133, g = 181, b = 3;
        if (primaryColor.startsWith('#')) {
            const hex = primaryColor.slice(1);
            if (hex.length === 3) {
                r = parseInt(hex[0] + hex[0], 16);
                g = parseInt(hex[1] + hex[1], 16);
                b = parseInt(hex[2] + hex[2], 16);
            } else if (hex.length >= 6) {
                r = parseInt(hex.substring(0, 2), 16);
                g = parseInt(hex.substring(2, 4), 16);
                b = parseInt(hex.substring(4, 6), 16);
            }
        }
        return `rgba(${r}, ${g}, ${b}, ${opacity})`;
    }
    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        stars = Array.from({ length: 80 }, () => ({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            vx: (Math.random() - .5) * .12,
            vy: (Math.random() - .5) * .12
        }));
    }
    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.lineWidth = .5;
        for (let i = 0; i < stars.length; i++) {
            for (let j = i + 1; j < stars.length; j++) {
                const dx = stars[i].x - stars[j].x;
                const dy = stars[i].y - stars[j].y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 130) {
                    ctx.strokeStyle = getColorWithOpacity(.18 * (1 - dist / 130));
                    ctx.beginPath();
                    ctx.moveTo(stars[i].x, stars[i].y);
                    ctx.lineTo(stars[j].x, stars[j].y);
                    ctx.stroke();
                }
            }
        }
        stars.forEach(star => {
            star.x += star.vx;
            star.y += star.vy;
            if (star.x < 0) star.x = canvas.width;
            if (star.x > canvas.width) star.x = 0;
            if (star.y < 0) star.y = canvas.height;
            if (star.y > canvas.height) star.y = 0;
            ctx.fillStyle = getColorWithOpacity(.35);
            ctx.beginPath();
            ctx.arc(star.x, star.y, 1.4, 0, Math.PI * 2);
            ctx.fill();
        });
        requestAnimationFrame(draw);
    }
    window.addEventListener('resize', resize);
    resize();
    draw();
})();
</script>
</body>
</html>
