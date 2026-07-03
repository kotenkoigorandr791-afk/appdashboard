<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($t['title']) ?></title>
<link rel="stylesheet" href="style.css?v=7">
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
.graph-modal-content { display: flex; justify-content: center; align-items: center; min-height: 100%; padding: 40px 20px; }
.graph-container { width: 100%; max-width: 900px; height: 500px; background: var(--card-bg); border-radius: 12px; padding: 25px; box-shadow: 0 8px 30px rgba(0,0,0,0.25); display: flex; flex-direction: column; }
.graph-title { text-align: center; margin-bottom: 25px; font-size: 1.8rem; color: var(--text-color); font-weight: 600; }
.graph-canvas-container { flex: 1; position: relative; width: 100%; height: 100%; }
.graph-canvas-container canvas { width: 100% !important; height: 100% !important; }
.user-info { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: var(--card-bg); border-radius: 6px; border: 1px solid var(--border-color); white-space: nowrap; }
.user-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
.user-details { display: flex; flex-direction: column; }
.user-name { font-weight: 600; color: var(--text-color); }
.user-role { font-size: 0.85rem; color: var(--primary-color); }
.stats-grid { justify-content: center; }
.section-strip { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 10px 14px; margin: 18px 0 24px; border: 1px solid color-mix(in srgb, var(--border-color) 82%, var(--primary-color) 18%); border-radius: 14px; background: linear-gradient(180deg, color-mix(in srgb, var(--card-bg) 86%, var(--primary-color) 6%), color-mix(in srgb, var(--card-bg) 96%, transparent)); box-shadow: 0 8px 24px rgba(0,0,0,0.08); overflow-x: auto; scrollbar-width: thin; }
.section-strip-button { appearance: none; border: 1px solid transparent; background: transparent; color: var(--text-color); font-size: 0.95rem; font-weight: 600; padding: 10px 16px; border-radius: 10px; cursor: pointer; white-space: nowrap; transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease; }
.section-strip-button:hover { border-color: color-mix(in srgb, var(--primary-color) 45%, var(--border-color)); background: color-mix(in srgb, var(--primary-color) 10%, transparent); }
.section-strip-button.active { color: #fff; background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 90%, #e7f07a), color-mix(in srgb, var(--primary-color) 72%, #5b7400)); border-color: color-mix(in srgb, var(--primary-color) 70%, #fff); box-shadow: 0 10px 22px color-mix(in srgb, var(--primary-color) 26%, transparent); }
.monitor-section { display: none; animation: monitorSectionFade 0.18s ease; }
.monitor-section.active { display: block; }
@keyframes monitorSectionFade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
.section-cluster { display: grid; gap: 24px; }
@media (max-width: 768px) { 
    .graph-container { padding: 15px; height: 400px; } 
    .graph-modal-content { padding: 20px 10px; } 
    .user-info { flex-direction: row; align-items: center; }
    .header-title h1 { font-size: 1.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-info { justify-content: center; order: 3; margin-top: 10px; }
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

/* Добавьте в существующий блок <style> */
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
.smart-badge { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
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
.side-menu-open {
    overflow: hidden;
}
.side-menu-toggle {
    position: static;
    width: 54px;
    height: 54px;
    border: 1px solid color-mix(in srgb, var(--primary-color) 60%, transparent);
    border-radius: 16px;
    background: linear-gradient(145deg, color-mix(in srgb, var(--primary-color) 85%, #1f9dff), color-mix(in srgb, var(--primary-color) 70%, #6d38ff));
    color: #fff;
    cursor: pointer;
    box-shadow: 0 16px 36px rgba(0,0,0,0.32);
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(8px);
    transition: transform .2s ease, box-shadow .2s ease, filter .2s ease, border-color .2s ease;
}
.mobile-refresh-btn {
    position: static;
    width: 54px;
    height: 54px;
    border-radius: 16px;
    font-size: 20px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.side-menu-toggle:hover {
    filter: brightness(1.05);
    transform: translateY(-2px);
    box-shadow: 0 22px 40px rgba(0,0,0,0.35);
    border-color: color-mix(in srgb, var(--primary-color) 85%, #fff);
}
.side-menu-toggle svg {
    width: 26px;
    height: 26px;
}
.side-menu-backdrop {
    position: fixed;
    inset: 0;
    z-index: 895;
    background: rgba(0,0,0,.45);
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease;
}
.side-menu-backdrop.open {
    opacity: 1;
    pointer-events: auto;
}
.side-menu {
    position: fixed;
    top: 0;
    right: 0;
    z-index: 900;
    width: min(380px, calc(100vw - 24px));
    height: 100vh;
    padding: 24px;
    background: color-mix(in srgb, var(--card-bg) 90%, #0a0a0a 10%);
    border-left: 1px solid color-mix(in srgb, var(--border-color) 70%, var(--primary-color) 30%);
    box-shadow: -18px 0 52px rgba(0,0,0,.32);
    backdrop-filter: blur(16px);
    transform: translateX(105%);
    transition: transform .25s ease;
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.side-menu.open {
    transform: translateX(0);
}
.side-menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}
.side-menu-title {
    font-size: 1.2rem;
    font-weight: 800;
    letter-spacing: .02em;
}
.side-menu-close {
    width: 40px;
    height: 40px;
    border: 1px solid color-mix(in srgb, var(--border-color) 65%, var(--primary-color));
    border-radius: 12px;
    background: color-mix(in srgb, var(--card-bg) 85%, #111 15%);
    color: var(--text-color);
    cursor: pointer;
    font-size: 26px;
    line-height: 1;
    transition: all .2s ease;
}
.side-menu-close:hover {
    color: var(--primary-color);
    border-color: var(--primary-color);
    transform: translateY(-1px);
}
.side-menu .user-info {
    width: 100%;
    padding: 14px;
    border-radius: 8px;
}
.side-menu-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.side-menu-link,
.side-menu .btn-primary,
.side-menu .btn-secondary,
.side-menu .auto-zoom-toggle {
    width: 100%;
    min-height: 44px;
    justify-content: center;
}
.side-menu-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid color-mix(in srgb, var(--border-color) 70%, var(--primary-color) 30%);
    border-radius: 12px;
    background: color-mix(in srgb, var(--card-bg) 88%, #0f0f0f 12%);
    color: var(--text-color);
    text-decoration: none;
    font-weight: 700;
    transition: all .2s ease;
}
.side-menu-link:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-1px);
    box-shadow: 0 8px 22px rgba(0,0,0,.18);
}
.side-menu .btn-primary,
.side-menu .btn-secondary {
    border-radius: 12px;
    min-height: 46px;
    font-weight: 700;
    letter-spacing: .01em;
    transition: all .2s ease;
}
.side-menu .btn-primary {
    background: linear-gradient(145deg, color-mix(in srgb, var(--primary-color) 88%, #45b8ff), color-mix(in srgb, var(--primary-color) 72%, #4a23ff));
    border: 1px solid color-mix(in srgb, var(--primary-color) 70%, #fff);
    box-shadow: 0 10px 24px rgba(0,0,0,.22);
}
.side-menu .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 28px rgba(0,0,0,.26);
}
.side-menu .btn-secondary {
    border: 1px solid color-mix(in srgb, var(--border-color) 65%, var(--primary-color) 35%);
    background: color-mix(in srgb, var(--card-bg) 90%, #111 10%);
}
.side-menu .btn-secondary:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-1px);
}
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
    .side-menu-toggle { width: 48px; height: 48px; border-radius: 14px; }
    .mobile-refresh-btn {
        width: 48px;
        height: 48px;
        border-radius: 14px;
    }
    .section-strip {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        overflow-x: visible;
        padding-left: 10px;
        padding-right: 10px;
        margin-left: 0;
        margin-right: 0;
    }
    .section-strip-button {
        width: 100%;
        padding: 9px 12px;
        min-width: 0;
        text-align: center;
        white-space: normal;
        overflow-wrap: anywhere;
        line-height: 1.2;
    }
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
