<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($t['title']) ?></title>
<link rel="stylesheet" href="/project/performance/style.css?v=<?= is_file(__DIR__ . '/../style.css') ? filemtime(__DIR__ . '/../style.css') : '8' ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root { --primary-color: <?= $color ?>; --error-color: #ff4444; --warning-color: #ff8800; }
.network-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 15px; }
.network-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.network-card h4 { margin-top: 0; margin-bottom: 10px; color: var(--primary-color); }
.network-stats { display: flex; flex-direction: column; gap: 8px; }
.network-stat { display: flex; justify-content: space-between; align-items: center; }
.network-label { font-weight: 500; color: var(--text-color); }
.network-value { font-family: monospace; color: var(--primary-color); }
.search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; fill: var(--primary-color); pointer-events: none; }
.module-status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; }
.module-status-loaded { background-color: var(--primary-color); }
.module-status-not-loaded { background-color: var(--error-color); }
.graph-modal-content { display: flex; justify-content: center; align-items: center; min-height: 100%; padding: 90px 20px 40px; }
.graph-container { width: 100%; max-width: 900px; height: min(500px, calc(100dvh - 40px)); background: var(--card-bg); border-radius: 12px; padding: 25px; box-shadow: 0 8px 30px rgba(0,0,0,0.25); display: flex; flex-direction: column; }
.graph-title { text-align: center; margin-bottom: 25px; font-size: 1.8rem; color: var(--text-color); font-weight: 600; }
.graph-canvas-container { flex: 1; position: relative; width: 100%; height: 100%; }
.graph-canvas-container canvas { width: 100% !important; height: 100% !important; }
.stats-grid { justify-content: stretch; }
.monitor-section { display: none; animation: monitorSectionFade 0.18s ease; }
.monitor-section.active { display: block; }
@keyframes monitorSectionFade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
@media (max-width: 768px) { 
    .graph-container { padding: 15px; height: min(400px, calc(100dvh - 40px)); }
    .graph-modal-content { padding: 60px 10px 20px; }
}
@media (max-width: 560px) {
    .graph-container { padding: 12px; height: min(360px, calc(100dvh - 40px)); }
    .graph-title { font-size: 1rem; margin-bottom: 14px; padding-right: 88px; overflow-wrap: anywhere; }
    body.admin-embed .graph-container .modal-close { top: 6px; right: 6px; width: 32px; height: 32px; font-size: 22px; line-height: 32px; }
    .graph-cleanup-btn { top: 6px; right: 44px; width: 32px; height: 32px; }
    .graph-cleanup-btn svg { width: 16px; height: 16px; }
}
.connection-status button {
    margin-left: 10px;
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.connection-status button.btn-secondary {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-color);
}

.connection-status button.btn-primary {
    background: var(--primary-color);
    border: none;
    color: white;
}

.connection-status button:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.auto-zoom-toggle {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    user-select: none;
}

.auto-zoom-toggle:hover {
    border-color: var(--primary-color);
}

.auto-zoom-toggle.active {
    background: var(--card-bg);
    border-color: var(--primary-color);
}

.auto-zoom-toggle .toggle-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-color);
}

.auto-zoom-toggle .switch-track {
    position: relative;
    width: 40px;
    height: 22px;
    background: var(--border-color);
    border-radius: 11px;
    transition: background 0.2s ease;
    flex-shrink: 0;
}

.auto-zoom-toggle.active .switch-track {
    background: var(--primary-color);
}

.auto-zoom-toggle .switch-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    transition: transform 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.auto-zoom-toggle.active .switch-thumb {
    transform: translateX(18px);
}

.auto-zoom-toggle .zoom-icon {
    width: 18px;
    height: 18px;
    color: var(--text-color);
    transition: color 0.2s ease;
    flex-shrink: 0;
}

.auto-zoom-toggle.active .zoom-icon {
    color: var(--primary-color);
}

/* Add to the existing <style> block */
.progress-bar {
    background-color: var(--border-color);
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
    height: 20px;
}

.progress {
    background: linear-gradient(90deg, var(--primary-color), #4caf50);
    height: 100%;
    transition: width 0.3s ease;
    border-radius: 10px;
}

.drive-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 0;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.drive-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.drive-card .drive-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.drive-card .drive-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--primary-color);
}

.drive-card .drive-type-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: color-mix(in srgb, var(--primary-color) 18%, transparent);
    color: var(--primary-color);
    border: 1px solid color-mix(in srgb, var(--primary-color) 35%, transparent);
}

.drive-card .drive-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin-bottom: 16px;
}

.drive-card .drive-stat-box {
    padding: 12px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--card-bg) 82%, var(--primary-color) 8%);
    border: 1px solid color-mix(in srgb, var(--border-color) 85%, var(--primary-color) 15%);
    text-align: center;
}

.drive-card .drive-stat-label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.drive-card .drive-stat-value {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-color);
    word-break: break-all;
}

.drive-card .drive-stat-value.mono {
    font-family: 'Courier New', monospace;
    font-size: 0.92rem;
}
.smart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-top: 15px; }
.smart-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
.smart-card h3, .smart-card h4 { margin-top: 0; }
.smart-card h3 { margin-bottom: 10px; color: var(--primary-color); }
.smart-hero { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.smart-meta { display: flex; flex-direction: column; gap: 6px; }
.smart-subtitle { color: var(--text-muted, #777); font-size: 0.92rem; }
.smart-badge { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
.smart-badge.good { background: rgba(56, 142, 60, 0.14); color: #2e7d32; }
.smart-badge.warning { background: rgba(245, 124, 0, 0.16); color: #ef6c00; }
.smart-badge.critical { background: rgba(211, 47, 47, 0.14); color: #c62828; }
.smart-badge.unknown { background: rgba(120, 144, 156, 0.16); color: #546e7a; }
.smart-updated { font-size: 0.85rem; color: var(--text-muted, #777); margin-top: 8px; }
.smart-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 16px; }
.smart-metric { padding: 12px; border-radius: 10px; background: color-mix(in srgb, var(--card-bg) 82%, var(--primary-color) 8%); border: 1px solid color-mix(in srgb, var(--border-color) 85%, var(--primary-color) 15%); }
.smart-metric-label { display: block; font-size: 0.82rem; color: var(--text-muted, #777); margin-bottom: 6px; }
.smart-metric-value { font-size: 1.05rem; font-weight: 700; color: var(--text-color); }
.smart-notes { display: grid; gap: 10px; margin-top: 16px; }
.smart-note { padding: 12px 14px; border-radius: 10px; border-left: 4px solid var(--primary-color); background: color-mix(in srgb, var(--card-bg) 80%, var(--primary-color) 6%); }
.smart-note.warning { border-left-color: #ef6c00; }
.smart-note.critical { border-left-color: #c62828; }
.smart-note.info { border-left-color: #1976d2; }
.smart-tests { display: grid; gap: 10px; margin-top: 12px; }
.smart-test { padding: 12px; border: 1px solid var(--border-color); border-radius: 10px; background: color-mix(in srgb, var(--card-bg) 88%, #000 2%); }
.smart-test-top { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 6px; }
.smart-test-type { font-weight: 700; color: var(--text-color); }
.smart-test-status { font-size: 0.9rem; color: var(--primary-color); }
.smart-test-meta { font-size: 0.84rem; color: var(--text-muted, #777); }
@media (max-width: 768px) { .smart-hero { flex-direction: column; } .smart-metrics { grid-template-columns: 1fr 1fr; } }
.graph-hover-preview {
    position: fixed;
    z-index: 1200;
    width: min(420px, calc(100vw - 24px));
    height: 260px;
    border: 1px solid color-mix(in srgb, var(--border-color) 65%, var(--primary-color) 35%);
    border-radius: 12px;
    background: color-mix(in srgb, var(--card-bg) 92%, #111 8%);
    box-shadow: 0 16px 34px rgba(0,0,0,.28);
    backdrop-filter: blur(8px);
    padding: 10px;
    pointer-events: none;
    opacity: 0;
    transform: translateY(4px);
    transition: opacity .14s ease, transform .14s ease;
}
.graph-hover-preview.visible {
    opacity: 1;
    transform: translateY(0);
}
.graph-hover-preview-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-color);
    margin-bottom: 6px;
}
.graph-hover-preview-canvas-wrap {
    position: relative;
    width: 100%;
    height: calc(100% - 24px);
}
.graph-hover-preview-canvas-wrap canvas {
    width: 100% !important;
    height: 100% !important;
}
@media (max-width: 768px) {
    .network-value {
        min-width: 0;
        overflow-wrap: anywhere;
        text-align: right;
    }
    .smart-metrics {
        grid-template-columns: 1fr;
    }
    .drive-card,
    .smart-card,
    .network-card,
    .stat-card {
        min-width: 0;
        overflow: hidden;
    }
}
</style>
