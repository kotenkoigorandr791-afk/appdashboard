<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($translations['steps_counter_title']); ?></title>
    <?php
    $bgFull = $theme === 'dark' ? 'background_dark.jpg' : 'background_light.jpg';
    $bgBase = $theme === 'dark' ? 'background_dark' : 'background_light';
    $bgSrcset = $bgBase . '-960.jpg 960w, ' . $bgBase . '-1920.jpg 1920w, ' . $bgFull . ' 5001w';
    $nameConfig = json_decode((string) @file_get_contents(__DIR__ . '/../name.json'), true);
    $menuButton = is_array($nameConfig['menu_button'] ?? null) ? $nameConfig['menu_button'] : [];
    $menuButtonLeft = (int) ($menuButton['left'] ?? 16);
    $menuButtonTop = (int) ($menuButton['top'] ?? 16);
    $menuButtonWidth = (int) ($menuButton['width'] ?? 40);
    $menuButtonHeight = (int) ($menuButton['height'] ?? 40);
    ?>
    <link rel="preload" as="image" href="<?php echo htmlspecialchars($bgFull); ?>" imagesrcset="<?php echo htmlspecialchars($bgSrcset); ?>" imagesizes="100vw" fetchpriority="low">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0..1,0&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/apps/steps/assets/md3-redesign.css?v=20261121r">
    <link rel="stylesheet" href="/apps/steps/assets/md3-from-preview.css?v=20261121r">
    <link rel="stylesheet" href="/apps/steps/assets/md3-modals.css?v=20261121r">
    <link rel="stylesheet" href="/apps/steps/assets/md3-exact.css?v=20261121r">
    <script>
    // Chart.js is interaction-only. It is loaded on demand by scripts.php
    // instead of blocking the initial render. Cropper had no call sites.
    window.STEPS_CHART_ASSETS = [
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
        'https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js',
        'https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js'
    ];
    (function(){
        var promise = null;
        function loadScript(src) {
            return new Promise(function(resolve, reject){
                var s = document.createElement('script');
                s.src = src; s.async = true;
                s.onload = resolve; s.onerror = function(){ reject(new Error('asset_failed:' + src)); };
                document.head.appendChild(s);
            });
        }
        window.ensureStepsCharts = function(){
            if (window.Chart && window.ChartZoom && window.ChartAnnotation) return Promise.resolve(window.Chart);
            if (promise) return promise;
            promise = window.STEPS_CHART_ASSETS.reduce(function(chain, src){
                return chain.then(function(){ return loadScript(src); });
            }, Promise.resolve()).then(function(){
                if (window.Chart) {
                    if (window.ChartZoom) window.Chart.register(window.ChartZoom);
                    if (window.ChartAnnotation) window.Chart.register(window.ChartAnnotation);
                }
                return window.Chart;
            }).catch(function(err){ promise = null; throw err; });
            return promise;
        };
    })();
    </script>
    <style>
        :root {
            color-scheme: <?php echo $theme === 'dark' ? 'dark' : 'light'; ?>;
            --bg-color: <?php echo $theme === 'dark' ? '#121314' : '#f4f7f2'; ?>;
            --text-color: <?php echo $theme === 'dark' ? '#E3E5E8' : '#161b18'; ?>;
            --primary-color: <?php echo $theme === 'dark' ? '#3B82F6' : htmlspecialchars($color); ?>;
            --modal-bg: <?php echo $theme === 'dark' ? 'rgba(28, 29, 31, 0.96)' : 'rgba(255, 255, 250, 0.86)'; ?>;
            --border-color: <?php echo $theme === 'dark' ? '#34363B' : 'rgba(22, 27, 24, 0.12)'; ?>;
            --group-block-bg: <?php echo $theme === 'dark' ? '#242528' : 'rgba(255, 255, 250, 0.66)'; ?>;
            --chart-color: <?php echo $theme === 'dark' ? '#E3E5E8' : '#161b18'; ?>;
            --scrollbar-thumb: <?php echo $theme === 'dark' ? 'rgba(159,163,170,0.28)' : 'rgba(0,0,0,0.3)'; ?>;
            --muted-text: <?php echo $theme === 'dark' ? '#9FA3AA' : 'rgba(22, 27, 24, 0.62)'; ?>;
            --surface-solid: <?php echo $theme === 'dark' ? '#1C1D1F' : '#fffffa'; ?>;
            --surface-rail: <?php echo $theme === 'dark' ? '#121314' : 'rgba(232, 239, 224, 0.82)'; ?>;
            --shadow-soft: 0 26px 70px rgba(0,0,0,<?php echo $theme === 'dark' ? '0.34' : '0.16'; ?>);
            --surface-raised: color-mix(in srgb, var(--surface-solid) 92%, var(--primary-color));
            --text-secondary: var(--muted-text);
            --border-subtle: color-mix(in srgb, var(--border-color) 84%, transparent);
            --state-hover: color-mix(in srgb, var(--primary-color) 10%, transparent);
            --state-active: color-mix(in srgb, var(--primary-color) 18%, transparent);
            --focus-ring: color-mix(in srgb, var(--primary-color) 38%, transparent);
            --danger-color: #dc2626;
            --success-color: #15803d;
            --warning-color: #b45309;
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-5: 20px;
            --space-6: 24px;
            --control-height: 40px;
            --transition-fast: 140ms ease;
            --transition-standard: 200ms cubic-bezier(.2,.8,.2,1);
            --radius-lg: 18px;
            --radius-md: 12px;
            --radius-sm: 7px;
            --glass-blur: 20px;
            --menu-button-left: <?php echo $menuButtonLeft; ?>px;
            --menu-button-top: <?php echo $menuButtonTop; ?>px;
            --menu-button-width: <?php echo $menuButtonWidth; ?>px;
            --menu-button-height: <?php echo $menuButtonHeight; ?>px;
            --topbar-height: calc(var(--menu-button-top) + var(--menu-button-height) + 8px);
            --topbar-left-pad: calc(var(--menu-button-left) + var(--menu-button-width) + 8px);
        }
        .view-selector,
        .chart-nav,
        .chart-label,
        .calendar-selector-box,
        .calendar-selector-box select,
        .calendar-header select,
        .goal-summary,
        .settings-content, .add-steps-content, .user-content, .add-user-content, .video-analysis-content, .video-confirm-inner, .video-confirm-gaps-inner, .excel-content, .backups-content, .alert-content, .info-content, .stats-content, .mobile-app-content,
        .settings-tab-bar,
        .settings-tab.active,
        .add-tab-bar,
        .add-tab.active,
        .settings-tile,
        .save-button-container,
        .data-type-btn,
        .notification,
        .worker-progress-item,
        .adjacent-date,
        .current-date,
        .va-step-dot,
        #add-steps-modal #add-panel-video .va-segment,
        #add-steps-modal #add-panel-video .va-field input[type="file"],
        #add-steps-modal #add-panel-video .va-field input[type="text"],
        #add-steps-modal #add-panel-video .va-field select,
        #add-panel-takeout .va-segment,
        #add-panel-takeout .va-field input[type="file"],
        #add-panel-takeout .takeout-guide-segment,
        #add-panel-video .takeout-guide-segment,
        #video-confirm-modal .takeout-guide-segment,
        #video-crop-modal .video-crop-inner,
        .excel-search-container,
        .excel-hotkeys-inner,
        .excel-hotkeys-info,
        #excel-modal .excel-table-pagination-bar,
        .page-btn,
        .info-section,
        .alert-btn.cancel,
        #excel-hotkeys-open,
        .settings-content input[type="text"],
        .settings-content input[type="date"],
        .add-user-content input[type="text"],
        .add-steps-content input[type="date"],
        .add-steps-content input[type="text"] {
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(1.1);
            backdrop-filter: blur(var(--glass-blur)) saturate(1.1);
        }

        /* Hide scrollbar when a modal is open */
        body.modal-open {
            overflow: hidden !important;
            position: fixed;
            width: 100%;
            height: 100%;
        }
        body.modal-open #menu-button,
        body.modal-open .menu-button,
        body.modal-open .menu-toggle.floating,
        body.modal-open .mobile-menu-button {
            opacity: 0 !important;
            pointer-events: none !important;
            visibility: hidden !important;
        }

        *::-webkit-scrollbar { width: 10px; height: 10px; }
        *::-webkit-scrollbar-track { background: transparent; }
        *::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 5px; }
        *::-webkit-scrollbar-thumb:hover { background: var(--primary-color); }
        * { scrollbar-width: thin; scrollbar-color: var(--scrollbar-thumb) transparent; }
        button:focus-visible,
        select:focus-visible,
        input:focus-visible,
        [tabindex]:focus-visible {
            outline: 3px solid color-mix(in srgb, var(--primary-color) 64%, #fff);
            outline-offset: 2px;
        }
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

        #alert-modal { z-index: 10030 !important; }
        #global-image-viewer {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2vh 2vw;
            background: rgba(0,0,0,0.88);
            z-index: 10050;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        #global-image-viewer.open { display: flex; opacity: 1; }
        #global-image-viewer img {
            max-width: 95vw;
            max-height: 95vh;
            width: auto;
            height: auto;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 24px 54px rgba(0,0,0,0.45);
            cursor: zoom-out;
        }
        #global-image-viewer-close {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 40px;
            height: 40px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.35);
            background: rgba(0,0,0,0.5);
            color: #fff;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
        }
        #add-steps-modal #add-panel-video { overflow-x: hidden; overflow-y: visible; box-sizing: border-box; }
        #add-steps-modal #add-panel-video.add-tab-panel {
            min-height: 0 !important;
            max-height: none !important;
            overflow-y: auto !important;
            flex: 0 1 auto;
        }
        #add-steps-modal #add-panel-takeout.add-tab-panel {
            overflow-y: auto !important;
        }
        #add-steps-modal #add-panel-video .video-analysis-content,
        #add-steps-modal #add-panel-video .zip-import-layout {
            width: 100%;
            max-width: 660px;
            min-width: 0;
            height: auto;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 0;
            box-sizing: border-box;
            overflow-x: hidden;
            overflow-y: visible;
            border: none;
            background: transparent;
        }
        #add-steps-modal #add-panel-video .va-head,
        #add-steps-modal #add-panel-video .zip-import-head {
            padding: 4px 0 0;
            border: none;
            border-bottom: none;
            background: transparent;
            text-align: center;
        }
        #add-steps-modal #add-panel-video .video-inline-scroll {
            flex: 0 1 auto;
            overflow: visible;
            overflow-x: hidden;
            text-align: left;
            padding: 0;
            display: flex;
            justify-content: center;
            width: 100%;
            border: none;
            background: transparent;
        }
        #add-steps-modal #add-panel-video .va-grid,
        #add-steps-modal #add-panel-video .zip-import-stack {
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: stretch;
            width: 100%;
            max-width: 100%;
        }
        #add-steps-modal #add-panel-video .va-segment,
        #add-steps-modal #add-panel-video .zip-import-card {
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            background: var(--group-block-bg);
            width: min(100%, 860px);
            box-sizing: border-box;
        }
        #add-steps-modal #add-panel-video .va-field {
            text-align: left;
        }
        #add-steps-modal #add-panel-video .zeep-ready-hint {
            margin: 0 0 12px;
            font-size: 14px;
            line-height: 1.5;
        }
        .va-steps { display: flex; flex-direction: column; align-items: center; gap: 12px; margin: 0 0 14px; user-select: none; width: min(100%, 860px); }
        .va-steps-row { display: flex; align-items: center; justify-content: center; gap: 0; width: 100%; }
        .va-progress { width: 100%; height: 5px; background: var(--border-color); border-radius: 5px; overflow: hidden; opacity: 0.85; }
        .va-progress-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #22c55e, var(--primary-color)); border-radius: 5px; transition: width 0.45s ease; }
        .va-step-dot { width: 34px; height: 34px; border-radius: 50%; border: 2px solid var(--border-color); background: var(--modal-bg); color: var(--text-color); font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; }
        .va-step-dot.is-active { border-color: var(--primary-color); background: var(--primary-color); color: #fff; box-shadow: 0 0 0 4px rgba(59,130,246,0.2); }
        .va-step-dot.is-done { border-color: #22c55e; background: #22c55e; color: #fff; box-shadow: 0 0 0 4px rgba(34,197,94,0.18); }
        .va-step-dot[disabled] { opacity: 0.45; cursor: not-allowed; }
        .va-step-line { width: min(140px, 16vw); height: 2px; background: var(--border-color); margin: 0 10px; transition: background 0.35s ease, box-shadow 0.35s ease; }
        .va-step-line.is-done { background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,0.45); }
        .va-segment.va-section-in-up { animation: vaSectionInUp 0.35s ease both; }
        .va-segment.va-section-in-next { animation: vaSectionInNext 0.38s cubic-bezier(.22,.68,.36,1) both; }
        .va-segment.va-section-in-prev { animation: vaSectionInPrev 0.38s cubic-bezier(.22,.68,.36,1) both; }
        @keyframes vaSectionInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes vaSectionInNext { from { opacity: 0; transform: translateX(28px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes vaSectionInPrev { from { opacity: 0; transform: translateX(-28px); } to { opacity: 1; transform: translateX(0); } }

        .takeout-guide-segment {
            width: min(100%, 860px);
            max-width: 100%;
            padding: 0 !important;
            overflow: hidden;
            background: transparent !important;
            border: none !important;
            box-sizing: border-box;
        }
        .takeout-guide-card-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            background: var(--group-block-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-color);
            cursor: pointer;
            text-align: left;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .takeout-guide-card-btn:hover {
            background: color-mix(in srgb, var(--primary-color) 12%, var(--group-block-bg));
            border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
            transform: translateY(-1px);
        }
        .takeout-guide-card-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: color-mix(in srgb, var(--primary-color) 18%, transparent);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .takeout-guide-card-text {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 auto;
            min-width: 0;
        }
        .takeout-guide-card-text strong {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
        }
        .takeout-guide-card-text span {
            font-size: 12px;
            color: var(--muted-text);
        }
        .takeout-guide-card-arrow {
            color: var(--muted-text);
            font-size: 14px;
            flex-shrink: 0;
            transition: transform 0.2s ease, color 0.2s ease;
        }
        .takeout-guide-card-btn:hover .takeout-guide-card-arrow {
            color: var(--primary-color);
            transform: translateX(3px);
        }

        #guide-expand-modal { position: fixed !important; inset: 0 !important; width: 100vw !important; height: 100vh !important; max-width: 100vw !important; max-height: 100vh !important; background: rgba(0,0,0,0.7) !important; -webkit-backdrop-filter: blur(10px) !important; backdrop-filter: blur(10px) !important; display: none; justify-content: center !important; align-items: stretch !important; z-index: 10040 !important; overflow: hidden !important; box-sizing: border-box !important; padding: 0 !important; margin: 0 !important; border: none !important; }
        #guide-expand-modal .guide-expand-content { background: var(--modal-bg) !important; width: 100vw !important; height: 100vh !important; max-width: 100vw !important; max-height: 100vh !important; border-radius: 0 !important; position: relative !important; display: flex !important; flex-direction: column !important; padding: 0 !important; margin: 0 !important; box-sizing: border-box !important; box-shadow: none !important; min-width: 0 !important; border: none !important; }
        .guide-expand-topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 16px; border-bottom: 1px solid var(--border-color); background: color-mix(in srgb, var(--surface-solid) 92%, transparent); flex-shrink: 0; }
        .guide-expand-back-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--group-block-bg); color: var(--text-color); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .guide-expand-back-btn:hover { background: color-mix(in srgb, var(--primary-color) 15%, var(--group-block-bg)); border-color: var(--primary-color); color: var(--primary-color); }
        #guide-expand-modal .guide-expand-title { display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 600; margin: 0; color: var(--text-color); flex: 1 1 auto; justify-content: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        #guide-expand-modal .guide-expand-title i { color: var(--primary-color); }
        #guide-expand-modal .modal-close { position: static; }
        #guide-expand-modal .guide-expand-frame-wrap { flex: 1 1 auto; min-height: 0; border: none; border-radius: 0; overflow: hidden; }
        #guide-expand-modal #guide-expand-frame { width: 100%; height: 100%; border: 0; display: block; background: var(--modal-bg); }
        #add-steps-modal #add-panel-video .va-head h2 { margin: 0 0 6px; font-size: 1.3rem; font-weight: 600; }
        #add-steps-modal #add-panel-video .va-sub { margin: 0; font-size: 14px; opacity: 0.9; line-height: 1.45; }
        #add-steps-modal #add-panel-video .va-form-box, #add-steps-modal #add-panel-video .va-grid, #add-steps-modal #add-panel-video .video-inline-scroll { min-width: 0; }
        #add-steps-modal #add-panel-video .va-segment.is-locked { opacity: 0.55; pointer-events: none; }
        #add-steps-modal #add-panel-video .va-segment-h {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            opacity: 0.9;
            margin: 0 0 12px;
            color: var(--primary-color);
        }
        #add-steps-modal #add-panel-video .va-segment-h i { font-size: 14px; opacity: 0.9; }
        #add-steps-modal #add-panel-video .va-field label { display: block; margin: 8px 0 4px; font-size: 13px; font-weight: 500; }
        #add-steps-modal #add-panel-video .va-field:first-child label { margin-top: 0; }
        #add-steps-modal #add-panel-video .va-field input[type="file"],
        #add-steps-modal #add-panel-video .va-field input[type="text"],
        #add-steps-modal #add-panel-video .va-field select {
            width: 100%;
            max-width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--modal-bg);
            color: var(--text-color);
            box-sizing: border-box;
        }
        #add-steps-modal #add-panel-video .video-btn-row { margin-top: 4px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        #add-steps-modal #add-panel-video .video-btn-row .excel-btn { padding: 0 20px; height: 40px; border-radius: 12px; }
        #add-steps-modal #add-panel-video #zl-progress-bar { height: 10px; background: rgba(0,0,0,0.12); border-radius: 6px; overflow: hidden; border: none; margin-top: 8px; }
        #add-steps-modal #add-panel-video #zl-progress-fill { height: 100%; width: 0%; background: var(--primary-color); transition: width 0.25s ease; border-radius: 6px; }
        #add-steps-modal #add-panel-video #zl-msg { margin: 6px 0 0; font-size: 14px; line-height: 1.45; }
        #add-steps-modal #add-panel-video #zl-meta { font-size: 12px; margin: 8px 0 0; opacity: 0.85; }
        #add-steps-modal #add-panel-video .va-field input[type="password"],
        #add-steps-modal #add-panel-video .va-field .zl-secret-input {
            width: 100%; max-width: 100%; box-sizing: border-box; padding: 10px 42px 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--modal-bg); color: var(--text-color); font-size: 14px;
        }
        #add-steps-modal #add-panel-video .zl-password-wrap { position: relative; width: 100%; }
        #add-steps-modal #add-panel-video .zl-password-toggle {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            width: 32px; height: 32px; padding: 0; border: none; border-radius: 6px;
            background: transparent; color: var(--text-color); opacity: 0.72; cursor: pointer;
        }
        #add-steps-modal #add-panel-video .zl-password-toggle:hover { opacity: 1; background: rgba(127,127,127,0.12); }
        #add-steps-modal #add-panel-video .zl-password-wrap .zl-secret-input.is-visible,
        #add-steps-modal #add-panel-video .zl-password-wrap .zl-secret-input[type="text"] {
            -webkit-text-security: none;
            text-security: none;
        }
        #add-steps-modal #add-panel-video #zl-start:disabled { opacity: 0.45; pointer-events: none; cursor: not-allowed; }
        #add-steps-modal #add-panel-video #zl-job-panel.va-segment { background: var(--modal-bg); }
        #video-crop-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.42); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); z-index: 10002; display: none; align-items: stretch; justify-content: center; overflow: hidden; overflow-x: hidden; padding: 12px; box-sizing: border-box; }
        #video-crop-modal .video-crop-inner { background: var(--modal-bg); padding: clamp(14px, 2vw, 22px); border-radius: var(--radius-lg); width: calc(100vw - 24px); height: calc(100vh - 24px); max-width: calc(100vw - 24px); max-height: calc(100vh - 24px); overflow: hidden; overflow-x: hidden; box-sizing: border-box; min-width: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        #video-crop-img { max-width: 100%; max-height: calc(100vh - 150px); display: block; }
        #video-crop-modal .cropper-view-box { outline: none !important; border: 3px solid #fff !important; box-shadow: 0 0 0 1px #000, 0 0 0 4px rgba(255,255,255,0.95) !important; }
        #video-crop-modal .cropper-face { background: transparent !important; opacity: 0 !important; }
        #video-crop-modal .cropper-line { background-color: transparent !important; opacity: 0 !important; }
        #video-crop-modal .cropper-point { background-color: #fff !important; border: 2px solid #000 !important; opacity: 1 !important; }
        #video-crop-modal .cropper-dashed { display: none !important; }
        #video-crop-modal .video-crop-actions { display: flex; flex-direction: row; flex-wrap: nowrap; align-items: center; justify-content: center; gap: 14px; margin-top: 18px; }

        body { margin: 0; padding: 0; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--bg-color); color: var(--text-color); transition: color 0.3s ease; position: relative; min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; width: 100%; box-sizing: border-box; }

        #bg-layer {
            position: fixed;
            inset: 0;
            z-index: -3;
            pointer-events: none;
            background: center / cover no-repeat;
            opacity: 0;
            transition: opacity 0.25s ease;
            contain: strict;
            filter: saturate(0.82) contrast(0.94);
        }
        #bg-layer::after {
            content: "";
            position: fixed;
            inset: 0;
            background: <?php echo $theme === 'dark' ? 'linear-gradient(135deg, rgba(17,20,22,0.94), rgba(17,20,22,0.82) 45%, rgba(47,143,131,0.22))' : 'linear-gradient(135deg, rgba(244,247,242,0.94), rgba(244,247,242,0.78) 45%, rgba(217,151,76,0.2))'; ?>;
        }
        #bg-layer.ready { opacity: 1; }

        .app-steps {
            background: transparent;
            padding: calc(var(--topbar-height) + 16px) clamp(12px, 2.4vw, 28px) 22px;
            text-align: center;
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
            position: relative;
            box-sizing: border-box;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - var(--topbar-height));
        }
        .app-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9000;
            min-height: var(--topbar-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px clamp(12px, 2vw, 24px) 10px var(--topbar-left-pad);
            box-sizing: border-box;
            background: <?php echo $theme === 'dark' ? 'rgba(17,20,22,0.78)' : 'rgba(244,247,242,0.78)'; ?>;
            border-bottom: 1px solid var(--border-color);
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(1.1);
            backdrop-filter: blur(var(--glass-blur)) saturate(1.1);
        }
        .app-topbar h1 {
            min-width: 0;
            margin: 0;
            font-size: 1.5em;
            line-height: 1.2;
            font-weight: 760;
            letter-spacing: 0;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .app-topbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .app-main {
            width: 100%;
            flex: 0 1 auto;
            box-sizing: border-box;
            display: grid;
            grid-template-columns: minmax(300px, 0.82fr) minmax(0, 1.42fr);
            grid-template-areas:
                "types chart"
                "calendar chart"
                "goal chart"
                ". view";
            gap: 10px 14px;
            align-items: stretch;
            padding: clamp(10px, 1.6vw, 16px);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: linear-gradient(120deg, var(--surface-rail), var(--modal-bg));
            box-shadow: var(--shadow-soft);
            -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(1.08);
            backdrop-filter: blur(var(--glass-blur)) saturate(1.08);
        }
        .view-selector { grid-area: view; margin-bottom: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); justify-content: center; background: rgba(127,127,127,0.08); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 4px; }
        .view-btn { background: transparent; color: var(--muted-text); border: none; padding: 10px 12px; margin: 0; cursor: pointer; border-radius: var(--radius-sm); font-weight: 700; letter-spacing: 0; }
        .view-btn.active, .view-btn:hover { background: var(--surface-solid); color: var(--text-color); opacity: 1; box-shadow: 0 8px 22px rgba(0,0,0,0.1); }
        .chart-container { grid-area: chart; position: relative; margin-bottom: 0; display: flex; flex-direction: row; align-items: stretch; justify-content: center; gap: 10px; width: 100%; max-width: none; min-height: 0; height: 100%; margin-left: auto; margin-right: auto; box-sizing: border-box; padding: clamp(10px, 1.6vw, 16px); border-radius: calc(var(--radius-lg) - 4px); background: var(--surface-solid); border: 1px solid rgba(127,127,127,0.1); }
        .chart-nav { flex-shrink: 0; align-self: center; font-size: 17px; background: transparent; border: 1px solid var(--border-color); border-radius: var(--radius-sm); width: 36px; height: 60px; min-height: 0; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--muted-text); }
        .chart-nav:hover { background: var(--primary-color); color: #fff; }
        .chart-nav:disabled { opacity: 0.3; cursor: not-allowed; background: var(--group-block-bg); color: var(--text-color); }
        .chart-nav:disabled:hover { background: var(--group-block-bg); color: var(--text-color); }
        .chart-main { position: relative; flex: 1 1 auto; min-width: 0; max-width: none; width: 100%; height: 100%; padding-top: 60px; display: flex; flex-direction: column; box-sizing: border-box; }
        .chart-label {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            transform: none;
            font-size: clamp(20px, 2.2vw, 34px);
            font-weight: 800;
            text-align: center;
            pointer-events: none;
            padding: 0 0 12px;
            border: 1px solid var(--border-color);
            border-width: 0 0 1px;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chart-wrapper { position: relative; width: 100%; max-width: none; margin: 0 auto; flex: 1 1 auto; min-height: 0; height: 100%; box-sizing: border-box; padding-top: 8px; }
        #steps-chart { width: 100%; height: 100%; min-height: 0; max-width: none; display: block; }
        .calendar-container { grid-area: calendar; margin-top: 0; width: 100%; margin-left: auto; margin-right: auto; min-width: 0; }
        .calendar-header { display: flex; gap: 8px; align-items: stretch; justify-content: stretch; }
        /* The chart period arrows live next to the year/month pickers. */
        .calendar-header .chart-nav {
            flex: 0 0 auto;
            align-self: stretch;
            width: 38px;
            height: auto;
            min-height: 0;
            margin-bottom: 10px;
            font-size: 15px;
            border-radius: var(--radius-md);
        }
        .calendar-header select { padding: 5px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--modal-bg); color: var(--text-color); margin-bottom: 15px; }
        #year-selector { width: 90px; }
        #month-selector { width: 160px; }

        /* Stylish box for year/month selects */
        .calendar-selector-box {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: rgba(127,127,127,0.08);
            box-shadow: none;
            box-sizing: border-box;
        }
        .calendar-selector-box select {
            flex: 1 1 0;
            min-width: 0;
            padding: 9px 11px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--surface-solid);
            color: var(--text-color);
            font-size: 14px;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .calendar-selector-box select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0,123,255,0.2);
        }
        .calendar-table { width: 100%; border-collapse: separate; border-spacing: 6px; outline: none; table-layout: fixed; }
        .calendar-table th, .calendar-table td { pointer-events: none; outline: none; border: 0; padding: 0; text-align: center; height: 42px; pointer-events: none; }
        .calendar-table th { color: var(--muted-text); font-weight: 800; font-size: 11px; text-transform: uppercase; }
        .calendar-table td { position: relative; width: 14.28%; border-radius: var(--radius-sm); background: rgba(127,127,127,0.07); font-weight: 700; transition: background 0.16s ease, color 0.16s ease, box-shadow 0.16s ease; }
        .calendar-table td[data-date] { cursor: pointer; pointer-events: auto; }
        .calendar-table td[data-date]:hover { background: color-mix(in srgb, var(--primary-color) 18%, transparent); }
        .calendar-table td.selected { background: var(--primary-color); color: #fff; box-shadow: inset 0 -3px 0 rgba(0,0,0,0.16); }
        .calendar-table td.calendar-day-other-month { color: var(--muted-text); opacity: 0.52; }
        .calendar-table td.calendar-day-other-month.selected { background: var(--primary-color); color: #fff; }

        .goal-icon { width: 15px; height: 15px; position: absolute; right: 3px; bottom: 3px; top: auto; transform: none; cursor: pointer; }
        .goal-summary { grid-area: goal; margin-top: 10px; padding: 12px; background: var(--surface-solid); border-radius: var(--radius-md); border: 1px solid var(--border-color); display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; text-align: left; }
        .goal-summary-item { min-width: 0; }
        .goal-summary-label { display: block; color: var(--muted-text); font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .goal-summary-value { display: block; margin-top: 4px; font-size: 20px; font-weight: 850; color: var(--text-color); }

        #info-button, #settings-button, #add-steps-button, #stats-button, #stepmonitor-apk-button, #sharepoint-share-button {
            position: static;
            background: rgba(127,127,127,0.08);
            border: 1px solid transparent;
            cursor: pointer;
            font-size: 24px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            border-radius: var(--radius-sm);
            transition: background-color 0.2s, border-color 0.2s;
        }
        #info-button:hover, #settings-button:hover, #add-steps-button:hover, #stats-button:hover, #stats-button.active, #stepmonitor-apk-button:hover, #sharepoint-share-button:hover {
            background: color-mix(in srgb, var(--primary-color) 14%, transparent);
            border-color: color-mix(in srgb, var(--primary-color) 34%, transparent);
        }
        #stats-button svg {
            width: 24px;
            height: 24px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        #user-button {
            position: static;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            padding: 0;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid color-mix(in srgb, var(--primary-color) 55%, transparent);
            background: transparent;
            cursor: default;
        }

        #user-button img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius-sm);
            box-sizing: border-box;
            display: block;
            flex-shrink: 0;
        }

        /* Modal windows for mobile */
        #info-modal, #stats-modal, #settings-modal, #add-steps-modal, #user-modal, #add-user-modal, #video-confirm-modal, #video-confirm-gaps-modal, #excel-modal, #excel-hotkeys-modal, #backups-modal, #alert-modal, #mobile-app-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.42);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            overflow: hidden;
            overflow-x: hidden;
            box-sizing: border-box;
            padding: 0;
        }
        #excel-hotkeys-modal { z-index: 10020; }
        #excel-modal {
            padding: 12px;
            align-items: stretch;
            justify-content: center;
        }
        .video-confirm-inner {
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        #video-confirm-table-wrap {
            min-height: 0;
        }
        #video-confirm-gaps-modal { z-index: 10025; background: rgba(0,0,0,0.38); }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(220,53,69,0.08);
            border: 1px solid rgba(220,53,69,0.38);
            border-radius: var(--radius-sm);
            color: #dc3545;
            font-size: 18px;
            cursor: pointer;
            padding: 2px 8px;
            z-index: 10001;
        }

        .settings-content, .add-steps-content, .user-content, .add-user-content, .video-analysis-content, .video-confirm-inner, .video-confirm-gaps-inner, .excel-content, .backups-content, .alert-content, .info-content, .stats-content, .mobile-app-content {
            background: var(--modal-bg);
            padding: 24px;
            border-radius: var(--radius-lg);
            width: calc(100vw - 24px);
            max-width: calc(100vw - 24px);
            height: calc(100vh - 24px);
            max-height: calc(100vh - 24px);
            overflow: auto;
            overflow-x: hidden;
            position: relative;
            margin: 0;
            box-sizing: border-box;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        #info-modal .info-content {
            overflow: hidden;
            overflow-x: hidden;
            padding: 0 !important;
        }
        #info-modal .info-scroll {
            overflow-y: auto;
            overflow-x: hidden;
            border-radius: inherit;
            max-height: inherit;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 20px;
            padding: 22px;
            box-sizing: border-box;
        }
        #info-modal .modal-close {
            position: static;
        }

        #stats-modal .stats-content {
            width: calc(100vw - 24px);
            overflow-y: auto;
            overflow-x: hidden;
        }
        @media (min-width: 769px) {
            #stats-modal .stats-grid > .stats-card {
                grid-column: auto;
                justify-self: stretch;
                width: 100%;
                max-width: none;
            }
            #stats-modal .stats-overview-split > .stats-card {
                grid-column: auto;
                justify-self: stretch;
                width: auto;
                max-width: none;
            }
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .stats-card {
            min-width: 0;
            padding: 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--group-block-bg);
            box-sizing: border-box;
        }
        .stats-card h3 {
            margin: 0 0 12px;
            font-size: 16px;
            color: var(--primary-color);
        }
        .stats-chart-wrap {
            position: relative;
            width: 100%;
            height: 360px;
        }
        #stats-empty {
            margin: 16px 0 0;
            text-align: center;
        }

        /* Unified premium statistics experience ------------------------------- */
        #stats-modal .stats-experience {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: clamp(20px, 3vw, 34px);
            border-radius: calc(var(--radius-lg, 18px) + 6px);
            background:
                radial-gradient(1200px 460px at 8% -12%, color-mix(in srgb, var(--primary-color) 16%, transparent), transparent 58%),
                radial-gradient(760px 420px at 100% 0%, color-mix(in srgb, #a855f7 10%, transparent), transparent 62%),
                var(--bg-color);
        }
        .stats-hero { position: relative; padding-right: 44px; }
        .stats-eyebrow, .stats-card-kicker {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase;
            color: var(--primary-color);
        }
        .stats-hero h2 {
            margin: 7px 0 4px; font-size: clamp(24px, 3.2vw, 34px); line-height: 1.08;
            letter-spacing: -.035em; font-weight: 800;
            background: linear-gradient(120deg, var(--text-color) 18%, color-mix(in srgb, var(--primary-color) 78%, var(--text-color)));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .stats-hero p { margin: 0; max-width: 62ch; color: var(--muted-text); font-size: 13px; line-height: 1.55; }
        .stats-freshness { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; min-height: 20px; }
        .stats-freshness span {
            display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border-radius: 999px;
            font-size: 10px; font-weight: 700; letter-spacing: .03em;
            border: 1px solid color-mix(in srgb, var(--border-color) 78%, transparent);
            background: color-mix(in srgb, var(--group-block-bg) 80%, transparent); color: var(--muted-text);
        }
        .stats-freshness .is-live { color: #15803d; border-color: color-mix(in srgb, #22c55e 36%, transparent); background: color-mix(in srgb, #22c55e 10%, transparent); }
        .stats-freshness .is-waiting { color: var(--primary-color); border-color: color-mix(in srgb, var(--primary-color) 35%, transparent); background: color-mix(in srgb, var(--primary-color) 9%, transparent); }
        .stats-tabbar {
            margin: 16px 0 12px;
            position: sticky; top: -1px; z-index: 3; display: flex; gap: 4px; padding: 4px;
            border: 1px solid color-mix(in srgb, var(--border-color) 84%, transparent); border-radius: 14px;
            background: color-mix(in srgb, var(--group-block-bg) 88%, transparent);
            backdrop-filter: blur(18px); overflow-x: auto; scrollbar-width: none;
        }
        .stats-tabbar::-webkit-scrollbar { display: none; }
        .stats-tab {
            flex: 1 0 auto; display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            min-height: 40px; padding: 8px 12px; border: 0; border-radius: 10px; cursor: pointer;
            background: transparent; color: var(--muted-text); font: inherit; font-size: 12px; font-weight: 650;
            transition: background .18s ease, color .18s ease, transform .18s ease;
        }
        .stats-tab i { font-size: 12px; opacity: .85; }
        .stats-tab:hover { color: var(--text-color); background: color-mix(in srgb, var(--primary-color) 8%, transparent); }
        .stats-tab.is-active { color: #fff; background: linear-gradient(135deg, var(--primary-color), color-mix(in srgb, var(--primary-color) 72%, #7c3aed)); box-shadow: 0 6px 18px color-mix(in srgb, var(--primary-color) 28%, transparent); }
        .stats-panels { min-height: 0; }
        .stats-panel { display: none; animation: statsPanelIn .28s ease both; }
        .stats-panel.is-active { display: block; }
        @keyframes statsPanelIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: none; } }
        .stats-metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
        .stats-metric {
            min-width: 0; padding: 14px 15px; border-radius: 14px;
            border: 1px solid color-mix(in srgb, var(--border-color) 82%, transparent);
            background: linear-gradient(155deg, color-mix(in srgb, var(--group-block-bg) 96%, #fff 4%), var(--group-block-bg));
            box-shadow: 0 1px 0 color-mix(in srgb, #fff 30%, transparent) inset;
        }
        .stats-metric > span { display: block; font-size: 10px; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; color: var(--muted-text); }
        .stats-metric strong { display: block; margin: 7px 0 2px; font-size: clamp(20px, 2.5vw, 26px); line-height: 1.05; letter-spacing: -.04em; font-variant-numeric: tabular-nums; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .stats-metric small { display: block; color: var(--muted-text); font-size: 10px; line-height: 1.4; }
        .stats-overview-split { display: grid; grid-template-columns: 1.05fr .95fr; gap: 12px; }
        .stats-experience .stats-card {
            padding: 16px; border-radius: 14px; border: 1px solid color-mix(in srgb, var(--border-color) 82%, transparent);
            background: color-mix(in srgb, var(--group-block-bg) 92%, transparent); box-shadow: 0 10px 30px rgba(0,0,0,.035);
        }
        .stats-experience .stats-card h3 { display: flex; align-items: center; gap: 7px; margin-bottom: 12px; font-size: 14px; letter-spacing: -.01em; }
        .stats-spotlight { background: linear-gradient(150deg, color-mix(in srgb, var(--primary-color) 11%, var(--group-block-bg)), var(--group-block-bg)) !important; }
        .stats-spotlight > strong { display: block; margin: 8px 0 4px; font-size: clamp(32px, 5vw, 52px); line-height: .95; letter-spacing: -.06em; font-variant-numeric: tabular-nums; }
        .stats-spotlight p { margin: 0; color: var(--muted-text); font-size: 11px; }
        .stats-reach { display: flex; flex-wrap: wrap; gap: 7px; }
        .stats-reach span { display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border-radius: 999px; background: color-mix(in srgb, var(--primary-color) 8%, transparent); border: 1px solid color-mix(in srgb, var(--primary-color) 15%, transparent); font-size: 11px; font-weight: 600; }
        .stats-week-rhythm { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 7px; align-items: end; min-height: 132px; }
        .stats-rhythm-day { display: flex; flex-direction: column; align-items: center; gap: 7px; min-width: 0; }
        .stats-rhythm-bar { width: 100%; max-width: 44px; min-height: 4px; border-radius: 7px 7px 3px 3px; background: linear-gradient(180deg, var(--primary-color), color-mix(in srgb, var(--primary-color) 46%, transparent)); }
        .stats-rhythm-day small { font-size: 9px; color: var(--muted-text); text-transform: uppercase; letter-spacing: .04em; }
        .stats-rhythm-day strong { font-size: 10px; font-variant-numeric: tabular-nums; }
        .stats-achievement-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; margin: 2px 0 12px; }
        .stats-achievement-head h3 { margin: 4px 0 0; font-size: 18px; letter-spacing: -.025em; }
        .stats-achievement-count { font-size: 24px; letter-spacing: -.05em; color: var(--primary-color); }
        .stats-achievement-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 10px; }
        .stats-achievement {
            position: relative; overflow: hidden; min-height: min-content; padding: 15px; border-radius: 15px;
            border: 1px solid color-mix(in srgb, var(--primary-color) 20%, var(--border-color));
            background: linear-gradient(155deg, color-mix(in srgb, var(--primary-color) 12%, var(--group-block-bg)), var(--group-block-bg) 70%);
            box-shadow: 0 9px 26px color-mix(in srgb, var(--primary-color) 9%, transparent);
        }
        .stats-achievement::after { content: ""; position: absolute; width: 110px; height: 110px; right: -42px; top: -52px; border-radius: 50%; background: color-mix(in srgb, var(--primary-color) 15%, transparent); }
        .stats-achievement-icon { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 12px; color: var(--primary-color); background: color-mix(in srgb, var(--primary-color) 12%, transparent); border: 1px solid color-mix(in srgb, var(--primary-color) 18%, transparent); }
        .stats-achievement h4 { margin: 12px 0 3px; font-size: 14px; letter-spacing: -.015em; }
        .stats-achievement p { margin: 0 0 12px; color: var(--muted-text); font-size: 11px; line-height: 1.45; }
        .stats-achievement footer { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-top: 9px; border-top: 1px solid color-mix(in srgb, var(--border-color) 70%, transparent); font-size: 10px; color: var(--muted-text); }
        .stats-achievement-badge { color: var(--primary-color); font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .stats-empty-state { margin: 0; padding: 26px 18px; text-align: center; border: 1px dashed var(--border-color); border-radius: 14px; color: var(--muted-text); }
        .stats-sources { display: flex; flex-direction: column; gap: 10px; }
        .stats-note { margin: 14px 0 0; color: var(--muted-text); font-size: 10px; line-height: 1.5; }
        @media (max-width: 900px) {
            .stats-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .stats-overview-split { grid-template-columns: 1fr; }
            #stats-modal .stats-card { max-width: none !important; }
        }
        @media (max-width: 640px) {
            #stats-modal .stats-experience { padding: 16px; gap: 12px; }
            .stats-tab span { display: none; }
            .stats-tab { min-width: 44px; }
            .stats-tab i { font-size: 14px; }
            .stats-metric { padding: 11px 12px; }
            .stats-metric strong { font-size: 19px; }
            .stats-chart-wrap { height: 280px; }
            .stats-achievement-grid { grid-template-columns: 1fr; }
        }
        @media (prefers-reduced-motion: reduce) {
            .stats-panel { animation: none; }
            .stats-tab { transition: none; }
        }
        #settings-modal .settings-content {
            overflow: hidden;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            width: calc(100vw - 24px);
        }
        #settings-modal .settings-content > h2 {
            flex-shrink: 0;
            margin-top: 0;
        }
        #settings-modal .settings-content > form,
        #settings-panel-profile form {
            width: min(100%, 980px);
            margin: 0 auto;
        }
        .settings-tab-bar {
            display: flex;
            flex-shrink: 0;
            overflow-x: auto;
            gap: 4px;
            padding: 5px;
            margin-bottom: 14px;
            background: var(--group-block-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
        }
        .settings-tab {
            flex: 0 0 auto;
            margin: 0;
            padding: 11px 14px;
            border: none;
            border-radius: 9px;
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
        }
        .settings-tab:hover {
            background: rgba(0,0,0,0.07);
        }
        .settings-tab.active {
            background: var(--modal-bg);
            color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }
        .settings-tab-panel {
            display: none;
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1 1 auto;
            min-height: 0;
            max-height: none;
            padding-right: 2px;
        }
        .settings-tab-panel.active {
            display: block;
        }
        .location-panel { display: flex; flex-direction: column; gap: 16px; }
        .location-section { display: flex; flex-direction: column; gap: 12px; }
        .location-section-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
        .location-section-head h3 { margin: 0; font-size: 15px; display: flex; align-items: center; gap: 8px; }
        .location-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .location-tz { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted-text); }
        .location-tz input { width: 130px; padding: 6px 8px; border-radius: 8px; border: 1px solid rgba(127,127,127,.35); background: rgba(127,127,127,.08); color: inherit; font-size: 12px; }
        .location-status { margin: 0; font-size: 12px; color: var(--muted-text); min-height: 16px; }
        .location-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px; }
        .location-card { padding: 10px 12px; border-radius: 12px; background: rgba(127,127,127,.08); border: 1px solid rgba(127,127,127,.14); display: flex; flex-direction: column; gap: 2px; }
        .location-card span { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted-text); }
        .location-card strong { font-size: 17px; line-height: 1.2; }
        .location-warnings { display: flex; flex-direction: column; gap: 6px; }
        .location-warning { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 10px; background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.35); color: #f59e0b; font-size: 12px; font-weight: 700; }
        .location-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px; }
        .location-detail-block { padding: 10px 12px; border-radius: 12px; background: rgba(127,127,127,.05); border: 1px solid rgba(127,127,127,.12); }
        .location-detail-block h4 { margin: 0 0 6px; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted-text); }
        .location-detail-block ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; font-size: 12px; }
        .location-detail-block li { display: flex; justify-content: space-between; gap: 10px; }
        .location-detail-block li span:last-child { text-align: right; color: var(--muted-text); }
        .location-detail-block p { margin: 0; font-size: 12px; }
        .location-hint { font-size: 11px; color: var(--muted-text); }
        .location-assumptions { margin: 0; padding-left: 18px; font-size: 11px; color: var(--muted-text); display: flex; flex-direction: column; gap: 3px; }
        .location-import-form { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .location-import-form input[type=file] { flex: 1 1 220px; min-width: 180px; font-size: 12px; color: inherit; }
        .location-import-option { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--muted-text); }
        .location-import-history { display: flex; flex-direction: column; gap: 8px; }
        .import-item { padding: 10px 12px; border-radius: 12px; background: rgba(127,127,127,.07); border: 1px solid rgba(127,127,127,.16); display: flex; flex-direction: column; gap: 4px; }
        .import-item-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
        .import-item-head strong { font-size: 12px; overflow-wrap: anywhere; }
        .import-item-meta { font-size: 11px; color: var(--muted-text); }
        .import-state { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; padding: 2px 7px; border-radius: 999px; background: rgba(127,127,127,.14); }
        .import-state.is-ready { background: rgba(34,197,94,.16); color: #22c55e; }
        .import-state.is-failed { background: rgba(239,68,68,.16); color: #ef4444; }
        .import-state.is-processing { background: rgba(59,130,246,.16); color: #60a5fa; }
        .import-item-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .import-item-actions button { font-size: 11px; padding: 4px 8px; }
        .location-export-field { display: inline-flex; flex-direction: column; gap: 3px; font-size: 11px; color: var(--muted-text); }
        .location-export-field select, .location-export-field input { min-width: 130px; font-size: 12px; padding: 5px 7px; }
        .location-export-field[hidden] { display: none; }
        /* Export controls form one aligned grid so fields and the action button
           stay on the same row instead of wrapping raggedly. */
        .location-export-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            align-items: end;
            gap: 10px 12px;
        }
        .location-export-form .location-export-field { min-width: 0; }
        .location-export-form .location-export-field select,
        .location-export-form .location-export-field input { width: 100%; min-width: 0; }
        .location-export-form .location-import-option { grid-column: 1 / -1; }
        .location-export-form > .excel-btn { justify-self: start; align-self: end; white-space: nowrap; }
        /* The location search box is a first-class field, not a bare input. */
        #location-search-q {
            flex: 1 1 260px;
            min-width: 180px;
            height: var(--control-height, 40px);
            padding: 0 var(--space-3, 12px);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm, 7px);
            background: var(--surface-solid);
            color: var(--text-color);
            font-size: 13px;
            line-height: 1.2;
            transition: border-color .18s ease, box-shadow .18s ease;
        }
        #location-search-q::placeholder { color: var(--muted-text); opacity: .85; }
        #location-search-q:hover { border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color)); }
        #location-search-q:focus { outline: 0; border-color: var(--primary-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 24%, transparent); }
        .export-artifact-links { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 11px; }
        .export-artifact-links a { color: var(--primary-color); text-decoration: none; }
        .export-artifact-links a:hover { text-decoration: underline; }
        .family-group { display: flex; flex-direction: column; gap: 8px; padding: 12px; border: 1px solid rgba(127,127,127,.16); border-radius: 12px; background: rgba(127,127,127,.05); }
        .family-member { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px; font-size: 12px; padding: 4px 0; border-top: 1px solid rgba(127,127,127,.1); }
        .family-invite-form { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .family-invite-form input { min-width: 180px; }
        .location-tools-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
        .location-tool-card { display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid rgba(127,127,127,.16); border-radius: 12px; background: rgba(127,127,127,.05); }
        .location-tool-card h4 { margin: 0; font-size: 13px; }
        .places-manager-modal { position: fixed; inset: 0; z-index: 11000; background: rgba(0,0,0,.55); display: flex; align-items: flex-end; justify-content: center; padding: 12px; }
        .places-manager-modal[hidden] { display: none !important; }
        .places-manager-card { width: min(920px, 100%); height: min(86vh, 820px); background: var(--surface, #12141a); border-radius: 18px 18px 12px 12px; border: 1px solid rgba(255,255,255,.08); display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 80px rgba(0,0,0,.45); }
        .places-manager-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 12px; }
        .places-manager-top h3 { margin: 0; font-size: 16px; }
        .places-manager-map { flex: 1; min-height: 240px; background: #0b0d12; }
        .places-manager-bottom { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; padding: 10px 12px 14px; border-top: 1px solid rgba(255,255,255,.08); }
        .location-tool-row { display: flex; gap: 6px; flex-wrap: wrap; }
        .location-tool-row input { min-width: 0; flex: 1 1 90px; font-size: 12px; padding: 5px 7px; }
        .location-tool-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 11px; padding: 4px 0; border-top: 1px solid rgba(127,127,127,.1); }
        .location-tool-item > span { min-width: 0; overflow-wrap: anywhere; }
        .location-consent { display: flex; align-items: center; gap: 8px; font-size: 12px; margin: 2px 0; }
        .notif-events { display: flex; flex-wrap: wrap; gap: 6px 14px; margin: 2px 0; }
        .notif-event { display: flex; align-items: center; gap: 6px; font-size: 12px; }
        .notif-time { display: flex; align-items: center; gap: 6px; font-size: 12px; }
        .notif-time input { font-size: 12px; padding: 4px 6px; }
        .immich-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; }
        .immich-card { display: flex; flex-direction: column; gap: 4px; border: 1px solid rgba(127,127,127,.16); border-radius: 10px; overflow: hidden; padding-bottom: 6px; background: rgba(127,127,127,.05); }
        .immich-card img { width: 100%; height: 105px; object-fit: cover; background: rgba(127,127,127,.12); }
        .immich-card span { font-size: 10px; padding: 0 6px; color: var(--muted-text); }
        .map-photo-marker { background: transparent !important; border: 0 !important; overflow: visible !important; }
        .map-photo-marker-ring { position: relative; display: block; box-sizing: border-box; width: 100%; height: 100%; border-radius: 50%; overflow: hidden; border: 2.5px solid #f59e0b; box-shadow: 0 2px 10px rgba(0,0,0,.45); background: #1f2937; }
        .map-photo-marker-ring.is-loading { background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%); }
        .map-photo-marker-ring .map-photo-fallback { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #111; font-size: 14px; z-index: 1; pointer-events: none; }
        .map-photo-marker-ring img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center; display: block; opacity: 0; border: 0; z-index: 2; }
        .map-photo-marker-ring img.is-ready { opacity: 1; }
        .map-photo-marker-ring.is-ready .map-photo-fallback { display: none; }
        .map-photo-marker .map-photo-count { position: absolute; top: -6px; right: -8px; z-index: 3; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: #111; color: #fff; font-size: 11px; line-height: 18px; text-align: center; border: 1px solid rgba(255,255,255,.55); font-style: normal; font-weight: 700; }
        .leaflet-popup.photo-md3-leaflet .leaflet-popup-content-wrapper { background: transparent; box-shadow: none; padding: 0; border: 0; }
        .leaflet-popup.photo-md3-leaflet .leaflet-popup-content { margin: 0; width: auto !important; }
        .leaflet-popup.photo-md3-leaflet .leaflet-popup-tip { background: rgba(20,22,28,.92); }
        .leaflet-popup.photo-md3-leaflet a.leaflet-popup-close-button { display: none; }
        .photo-md3-pop { width: min(280px, 82vw); background: rgba(18,20,26,.94); border: 1px solid rgba(255,255,255,.1); border-radius: 18px; overflow: hidden; box-shadow: 0 18px 40px rgba(0,0,0,.45); backdrop-filter: blur(10px); }
        .photo-md3-media { position: relative; background: #0b0d12; line-height: 0; min-height: 96px; }
        .photo-md3-media img { width: 100%; height: auto; max-height: min(56vh, 420px); object-fit: contain; object-position: center; display: block; opacity: 0; transition: opacity .25s ease; vertical-align: top; }
        .photo-md3-media img.is-ready { opacity: 1; }
        .photo-md3-skeleton { position: absolute; inset: 0; background: linear-gradient(110deg, #1a1d24 20%, #2a2f3a 40%, #1a1d24 60%); background-size: 200% 100%; animation: photoMd3Shimmer 1.2s linear infinite; }
        .photo-md3-actions { display: flex; gap: 6px; padding: 8px; }
        .photo-md3-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 4px; min-height: 34px; border: 0; border-radius: 12px; background: rgba(255,255,255,.08); color: #f3f4f6; font-size: 12px; cursor: pointer; }
        .photo-md3-btn:hover { background: rgba(255,255,255,.14); }
        .photo-md3-btn.primary { background: #f59e0b; color: #111; font-weight: 600; }
        .photo-md3-info { display: none; padding: 0 10px 10px; color: #d1d5db; font-size: 11px; line-height: 1.45; }
        .photo-md3-info.is-open { display: block; }
        .photo-md3-info div + div { margin-top: 4px; opacity: .85; }
        .photo-md3-lightbox { position: fixed; inset: 0; z-index: 12000; background: rgba(0,0,0,.82); display: flex; align-items: center; justify-content: center; padding: 24px; }
        .photo-md3-lightbox[hidden] { display: none !important; }
        .photo-md3-lightbox-card { width: min(720px, 92vw); max-height: 92vh; background: #111318; border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,.08); box-shadow: 0 24px 80px rgba(0,0,0,.55); display: flex; flex-direction: column; }
        .photo-md3-lightbox-top { display: flex; justify-content: flex-end; gap: 8px; padding: 10px; }
        .photo-md3-lightbox-media { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; background: #000; width: 100%; padding: 0; }
        .photo-md3-lightbox-media img { max-width: 100%; max-height: min(78vh, 860px); width: auto; height: auto; object-fit: contain; }
        .photo-md3-lightbox-meta { padding: 12px 14px 16px; color: #e5e7eb; font-size: 13px; }
        @keyframes photoMd3Shimmer { to { background-position-x: -200%; } }
        .map-place-photo { background: transparent !important; border: 0 !important; }
        .map-place-photo-ring { display: block; width: 100%; height: 100%; border-radius: 50%; overflow: hidden; border: 2.5px solid #0ea5e9; box-shadow: 0 2px 8px rgba(0,0,0,.45); background: #1f2937; }
        .map-live-heading { display: inline-block; transform-origin: 50% 55%; }
        .map-place-photo-ring img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .visit-pop-photo, .visit-editor-photo { width: 72px; height: 72px; border-radius: 50%; overflow: hidden; margin: 0 0 8px; border: 2px solid rgba(255,255,255,.35); box-shadow: 0 2px 10px rgba(0,0,0,.35); }
        .visit-pop-photo img, .visit-editor-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .visit-photo-attr { font-size: 10px; opacity: .7; margin-top: 4px; line-height: 1.3; }
        .visit-row-photo { width: 28px; height: 28px; border-radius: 50%; overflow: hidden; flex: 0 0 28px; border: 2px solid rgba(255,255,255,.25); }
        .visit-row-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .family-token { font-family: monospace; font-size: 11px; overflow-wrap: anywhere; background: rgba(127,127,127,.12); padding: 6px; border-radius: 6px; }
        .import-error { font-size: 11px; color: #ef4444; }
        #settings-panel-chart .settings-chart-type-intro {
            text-align: left;
            font-size: 14px;
            line-height: 1.45;
            margin: 0 0 14px;
            opacity: 0.92;
        }
        .settings-chart-type-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .settings-chart-type-group .chart-type-btn {
            flex: 1;
            min-width: 130px;
            justify-content: center;
            margin: 0;
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 14px;
            border: 1px solid var(--border-color);
        }
        .settings-chart-preview-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 16px;
        }
        @media (max-width: 768px) {
            .settings-chart-preview-grid {
                grid-template-columns: 1fr;
            }
        }
        .settings-chart-preview-item {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .settings-chart-preview-item .chart-type-btn {
            width: 100%;
        }
        .settings-chart-preview-box {
            padding: 12px;
            background: var(--group-block-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            transition: border-color 0.2s ease;
        }
        .settings-chart-preview-item:has(.chart-type-btn.active) .settings-chart-preview-box {
            border-color: var(--primary-color);
        }
        .settings-chart-preview-box canvas {
            width: 100%;
            height: 200px;
            display: block;
        }
        #add-steps-modal .add-steps-content {
            overflow: hidden;
        }
        #add-steps-modal .add-steps-merged {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            width: calc(100vw - 24px);
            height: calc(100vh - 24px);
            max-width: calc(100vw - 24px);
            max-height: calc(100vh - 24px);
            padding: clamp(14px, 2vw, 22px);
            box-sizing: border-box;
            min-width: 0;
        }
        #add-steps-modal .add-steps-main-title {
            margin: 0 0 10px 0;
            font-size: 1.35rem;
            text-align: center;
        }
        .add-tab-bar {
            display: flex;
            flex-shrink: 0;
            gap: 4px;
            padding: 5px;
            margin-bottom: 14px;
            background: var(--group-block-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        .add-tab {
            flex: 1;
            margin: 0;
            padding: 10px 10px;
            border: none;
            border-radius: 9px;
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .add-tab:hover { background: rgba(0,0,0,0.07); }
        .add-tab.active {
            background: var(--modal-bg);
            color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }
        .add-tab-panel {
            display: none !important;
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1 1 0;
            min-height: 0;
            padding-right: 2px;
        }
        .add-tab-panel.active { display: block !important; }
        #add-panel-manual.add-tab-panel.active { display: flex !important; }
        #add-panel-manual { text-align: center; align-items: center; justify-content: center; flex-direction: column; flex: 1 1 0; min-height: 0; overflow-y: auto; overflow-x: hidden; }
        #add-panel-takeout .video-analysis-content,
        #add-panel-takeout.zip-import-layout,
        #add-panel-takeout .zip-import-layout {
            padding: 0;
            max-width: 660px;
            width: 100%;
            height: auto;
            max-height: none;
            margin: 0 auto;
            box-sizing: border-box;
            overflow-x: hidden;
        }
        #add-panel-takeout .va-head {
            text-align: center;
            margin-bottom: 0;
            border: none;
            background: transparent;
        }
        #add-panel-takeout .va-head h2 {
            margin: 0 0 6px 0;
            font-size: 17px;
            font-weight: 700;
        }
        #add-panel-takeout .va-sub {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
            line-height: 1.45;
            text-align: center;
        }
        #add-panel-takeout .takeout-hint {
            margin-bottom: 0;
        }
        #add-panel-takeout .va-grid,
        #add-panel-takeout .zip-import-stack {
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: stretch;
            width: 100%;
            max-width: 100%;
        }
        #add-panel-takeout .va-segment,
        #add-panel-takeout .zip-import-card {
            border: 1px solid var(--border-color);
            border-radius: 16px;
            background: var(--group-block-bg);
            padding: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        #add-panel-takeout .va-field {
            text-align: left;
            display: block;
        }
        #add-panel-takeout .va-field label {
            display: block;
            margin: 0 0 6px;
            font-size: 12px;
            font-weight: 500;
            text-align: left;
        }
        #add-panel-takeout .va-field input[type="file"] {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            height: 44px;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--modal-bg);
            color: var(--text-color);
        }
        #add-panel-takeout .video-btn-row,
        #add-panel-takeout .zip-import-actions {
            margin-top: 4px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
        }
        #add-panel-takeout .video-btn-row .excel-btn {
            padding: 0 20px;
            height: 40px;
            border-radius: 12px;
        }
        #add-panel-takeout #ft-progress-bar {
            height: 10px;
            background: rgba(0,0,0,0.12);
            border-radius: 6px;
            overflow: hidden;
            margin-top: 8px;
        }
        #add-panel-takeout #ft-progress-fill {
            height: 100%;
            width: 0%;
            background: var(--primary-color);
            border-radius: 6px;
            transition: width 0.25s ease;
        }
        #add-panel-takeout #ft-msg {
            margin: 6px 0 0;
            font-size: 14px;
            line-height: 1.45;
            text-align: center;
        }
        #add-panel-takeout #ft-meta {
            font-size: 12px;
            opacity: 0.85;
            margin: 8px 0 0;
            text-align: center;
        }
        #add-panel-takeout .takeout-guide-segment,
        #add-panel-takeout .zip-import-guide {
            width: 100%;
            max-width: 100%;
            padding: 0;
            overflow: hidden;
            background: transparent;
            border: none;
            border-radius: 0;
        }
        #add-panel-takeout .takeout-guide-head {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: space-between;
            padding: 12px 14px;
            background: linear-gradient(130deg, color-mix(in srgb, var(--primary-color) 18%, transparent), transparent 70%);
        }
        #add-panel-takeout .takeout-guide-toggle {
            margin-left: auto;
            border: 0;
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            padding: 2px 4px;
        }
        #add-panel-takeout .takeout-guide-body {
            border-top: 1px solid var(--border-color);
            overflow: hidden;
        }
        #add-panel-takeout #takeout-guide-frame {
            width: 100%;
            height: min(66.6vh, 1100px);
            border: 0;
            display: block;
            background: var(--modal-bg);
        }
        #add-panel-takeout .takeout-guide-segment.is-collapsed .takeout-guide-body {
            display: none;
        }
        #add-panel-takeout .takeout-guide-segment.is-collapsed .takeout-guide-toggle i {
            transform: rotate(-90deg);
        }
        #add-panel-takeout .takeout-guide-toggle i {
            transition: transform 0.2s ease;
        }
        #add-panel-video .takeout-guide-segment,
        #add-panel-video .zip-import-guide {
            width: 100%;
            max-width: 100%;
            padding: 0;
            overflow: hidden;
            background: transparent;
            border: none;
            border-radius: 0;
        }
        #add-panel-video .takeout-guide-head {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: space-between;
            padding: 12px 14px;
            background: linear-gradient(130deg, color-mix(in srgb, var(--primary-color) 18%, transparent), transparent 70%);
        }
        #add-panel-video .takeout-guide-toggle {
            margin-left: auto;
            border: 0;
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            padding: 2px 4px;
        }
        #add-panel-video .takeout-guide-body {
            border-top: 1px solid var(--border-color);
            overflow: hidden;
        }
        #add-panel-video #zeep-guide-frame {
            width: 100%;
            height: min(66.6vh, 1100px);
            min-height: 360px;
            border: 0;
            display: block;
            background: var(--modal-bg);
        }
        #add-panel-video .takeout-guide-segment.is-collapsed .takeout-guide-body {
            display: none;
        }
        #add-panel-video .takeout-guide-segment.is-collapsed .takeout-guide-toggle i {
            transform: rotate(-90deg);
        }
        #add-panel-video .takeout-guide-toggle i {
            transition: transform 0.2s ease;
        }
        #video-confirm-modal .takeout-guide-segment {
            width: 100%;
            max-width: 100%;
            padding: 0;
            overflow: hidden;
            background: var(--modal-bg);
            margin: 0 0 12px;
            box-sizing: border-box;
        }
        #video-confirm-modal .takeout-guide-head {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: space-between;
            padding: 12px 14px;
            background: linear-gradient(130deg, color-mix(in srgb, var(--primary-color) 18%, transparent), transparent 70%);
        }
        #video-confirm-modal .takeout-guide-toggle {
            margin-left: auto;
            border: 0;
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            padding: 2px 4px;
        }
        #video-confirm-modal .takeout-guide-body {
            border-top: 1px solid var(--border-color);
        }
        #video-confirm-modal .takeout-guide-segment.is-collapsed .takeout-guide-body {
            display: none;
        }
        #video-confirm-modal .takeout-guide-segment.is-collapsed .takeout-guide-toggle i {
            transform: rotate(-90deg);
        }
        #video-confirm-modal .takeout-guide-toggle i {
            transition: transform 0.2s ease;
        }
        #video-confirm-modal #video-confirm-phone-guide-frame {
            width: 100%;
            height: min(50vh, 520px);
            border: 0;
            display: block;
            background: var(--modal-bg);
        }
        .worker-stop-btn {
            background: #dc3545 !important;
            color: #fff !important;
        }
        #excel-modal .excel-content {
            display: flex;
            flex-direction: column;
            width: calc(100vw - 24px);
            max-width: calc(100vw - 24px);
            height: calc(100vh - 24px);
            max-height: calc(100vh - 24px);
            margin: 0;
            padding: 50px 42px 14px 16px;
            overflow: hidden;
            min-height: 0;
        }
        #excel-modal .excel-content > .modal-close {
            top: 12px;
            right: 12px;
            z-index: 3;
        }
        .excel-modal-head {
            display: grid;
            grid-template-columns: 48px minmax(0, 1fr) 48px;
            align-items: center;
            justify-items: stretch;
            flex-shrink: 0;
            margin-bottom: 10px;
            column-gap: 8px;
        }
        .excel-modal-head .excel-head-side-left {
            justify-self: start;
        }
        .excel-modal-head h2 {
            margin: 0;
            font-size: 1.25rem;
            text-align: center;
            justify-self: center;
            max-width: 100%;
        }
        .excel-modal-head .excel-head-spacer {
            width: 42px;
            height: 42px;
            justify-self: end;
            visibility: hidden;
            pointer-events: none;
        }
        #excel-hotkeys-open {
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            padding: 0;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            background: var(--group-block-bg);
            color: var(--primary-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: background 0.15s ease, color 0.15s ease;
        }
        #excel-hotkeys-open:hover {
            background: var(--primary-color);
            color: #fff;
        }
        #excel-modal .excel-content > .excel-search-container,
        #excel-modal .excel-content > .excel-actions {
            flex-shrink: 0;
        }
        #excel-modal .excel-search-container {
            margin-bottom: 8px;
        }
        #excel-modal .excel-table-stack {
            flex: 1 1 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin: 8px 0;
        }
        #excel-modal .excel-table-stack .excel-table-container {
            flex: 1 1 0;
            min-height: 80px;
            max-height: none;
            margin: 0;
            overflow: auto;
        }
        #excel-modal .excel-table-pagination-bar {
            flex-shrink: 0;
            background: var(--modal-bg);
            border: 1px solid var(--border-color);
            border-top: none;
            box-sizing: border-box;
        }
        #excel-modal .excel-table-pagination-bar .table-pagination {
            padding: 10px 0 6px;
            margin: 0;
        }
        #excel-table-container .excel-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        #excel-table-container .excel-table th,
        #excel-table-container .excel-table td {
            border: none;
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }
        #excel-table-container .excel-table th:first-child,
        #excel-table-container .excel-table td:first-child {
            border-left: 1px solid var(--border-color);
        }
        #excel-table-container .excel-table thead tr:first-child th {
            border-top: 1px solid var(--border-color);
        }
        #excel-modal .excel-actions {
            flex-wrap: nowrap;
            overflow-x: auto;
            margin-top: 0;
            gap: 8px;
            padding: 4px 0;
            -webkit-overflow-scrolling: touch;
        }
        #excel-modal .excel-actions .excel-btn {
            flex: 0 0 auto;
            white-space: nowrap;
            padding: 9px 14px;
            font-size: 13px;
        }
        #excel-hotkeys-modal .excel-hotkeys-inner {
            background: var(--modal-bg);
            padding: clamp(14px, 2vw, 22px);
            border-radius: var(--radius-lg);
            width: calc(100vw - 24px);
            max-width: calc(100vw - 24px);
            height: calc(100vh - 24px);
            max-height: calc(100vh - 24px);
            overflow-y: auto;
            overflow-x: hidden;
            position: relative;
            margin: 0;
            box-sizing: border-box;
        }
        #excel-hotkeys-modal .excel-hotkeys-inner .modal-close {
            z-index: 2;
        }
        #excel-hotkeys-modal .excel-hotkeys-inner h2 {
            margin-top: 0;
            padding-right: 36px;
        }

        /* Excel table */
        .excel-table-container {
            width: 100%;
            max-width: 100%;
            max-height: 60vh;
            overflow-x: hidden;
            overflow-y: auto;
            margin: 20px 0;
            border: 1px solid var(--border-color);
            box-sizing: border-box;
            min-width: 0;
        }

        .excel-table {
            width: 100%;
            border-collapse: collapse;
        }

        .excel-table th, .excel-table td {
            border: 1px solid var(--border-color);
            padding: 8px;
            text-align: center;
            min-width: 100px;
        }

        .excel-table thead th {
            background: var(--primary-color);
            color: white;
            position: sticky;
            top: 0;
            z-index: 5;
            box-shadow: 0 2px 4px rgba(0,0,0,0.18);
        }

        .excel-table td {
            background: var(--modal-bg);
        }

        .excel-table td.selected {
            background: rgba(0, 123, 255, 0.3);
        }

        .excel-table td.editing {
            background: rgba(255, 255, 0, 0.3);
        }

        .excel-table tr.found {
            background: rgba(255, 255, 0, 0.3) !important;
        }

        .excel-table tr.highlighted {
            background: rgba(0, 123, 255, 0.2) !important;
        }

        .excel-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .excel-btn {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .excel-btn:hover {
            opacity: 0.8;
        }

        .excel-btn.delete {
            background: #dc3545;
        }

        .excel-btn.download {
            background: #17a2b8;
        }

        .excel-btn.restore {
            background: #28a745;
        }

        .excel-btn.manage-backups {
            background: #6c757d;
        }

        .excel-btn.create-backup {
            background: #17a2b8;
        }

        /* Excel search */
        .excel-search-container {
            background: var(--group-block-bg);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 15px;
        }

        .hotkey {
            display: inline-block;
            padding: 2px 6px;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
            font-family: monospace;
            font-size: 11px;
            margin: 0 2px;
        }

        .excel-hotkeys-info {
            line-height: 1.6;
            margin-bottom: 15px;
            padding: 10px;
            background: var(--group-block-bg);
            border-radius: 4px;
            font-size: 12px;
        }

        .add-steps-content, .user-content, .add-user-content { display: flex; flex-direction: column; align-items: center; }
        .settings-content input[type="text"], .add-user-content input[type="text"], .settings-content input[type="date"] {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--modal-bg);
            color: var(--text-color);
        }

        /* Wider date of birth field */
        .settings-content input[name="birth_date"] {
            width: 150px;
        }

        .add-steps-content input[type="date"], .add-steps-content input[type="text"] {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--modal-bg);
            color: var(--text-color);
        }

        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; }

        .settings-tile { display: flex; flex-direction: column; align-items: center; background: var(--group-block-bg); padding: 15px; margin-bottom: 10px; border-radius: var(--radius-md); border: 1px solid var(--border-color); }
        .settings-tile hr { border: none; border-top: 1px solid var(--primary-color); width: 100%; margin: 10px 0; }
        .tile-title { font-weight: bold; margin-bottom: 10px; }
        .tile-content { display: grid; grid-template-columns: 65px minmax(0, 1fr) 55px; align-items: center; justify-items: center; gap: 10px; width: 100%; box-sizing: border-box; }
        .tile-content span:last-child { justify-self: end; }
        #settings-panel-profile .settings-tile input {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            min-width: 0;
        }
        @media (min-width: 760px) {
            #settings-panel-profile form {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 10px;
            }
            #settings-panel-profile .settings-tile {
                margin-bottom: 0;
            }
            .tile-content {
                grid-template-columns: 60px minmax(0, 1fr) 50px;
            }
        }
        .save-button-container { display: none !important; }
        .save-button { display: none !important; }

        .data-type-selector { grid-area: types; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); justify-content: center; gap: 8px; margin-bottom: 0; }
        .data-type-btn {
            width: 100%;
            height: 76px;
            border-radius: var(--radius-md);
            margin: 0;
            border: 1px solid var(--border-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--surface-solid);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        }
        .data-type-btn:hover {
            border-color: var(--primary-color);
            background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-solid));
            box-shadow: 0 12px 26px rgba(0,0,0,0.14);
            transform: translateY(-2px);
        }
        .data-type-btn.active { border-color: var(--primary-color); box-shadow: inset 0 -4px 0 var(--primary-color); }
        .data-type-btn i { font-size: 26px; color: var(--text-color); transition: transform 0.18s ease, color 0.18s ease; }
        .data-type-btn:hover i { transform: scale(1.06); color: var(--primary-color); }

        .chart-type-btn { background: var(--surface-solid); color: var(--text-color); border: 1px solid var(--border-color); padding: 8px; margin: 5px 0; cursor: pointer; border-radius: 4px; font-size: 14px; display: flex; align-items: center; transition: all 0.16s ease; }
        .chart-type-btn.active { background: color-mix(in srgb, var(--primary-color) 20%, var(--surface-solid)); color: var(--primary-color); border-color: color-mix(in srgb, var(--primary-color) 30%, var(--border-color)); }
        .chart-type-btn:hover:not(.active) { border-color: var(--primary-color); color: var(--primary-color); }
        .chart-type-btn span { margin-left: 5px; }

        .notification {
            background: var(--modal-bg);
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            width: min(380px, calc(100vw - 40px));
            max-width: min(380px, calc(100vw - 40px));
            min-width: 0;
            box-sizing: border-box;
            max-height: 220px;
            overflow-y: auto;
            border-left: 4px solid var(--primary-color);
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .notification.video-related-notif { border-left-color: #e6a200; }
        .notification.success { border-left-color: #28a745; }
        .notification.error { border-left-color: #dc3545; }
        .notification.info { border-left-color: var(--primary-color); }

        .notification-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); }
        .notification-title { font-weight: bold; font-size: 16px; color: var(--primary-color); }
        .notification-content { font-size: 14px; line-height: 1.4; }
        .notification-item { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin: 5px 0; flex-wrap: nowrap; font-size: 13px; }
        .notification-item > span:first-child { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notification-value { font-weight: bold; flex-shrink: 0; white-space: nowrap; text-align: right; }
        .close-notification { background: none; border: none; color: #999; font-size: 18px; cursor: pointer; }
        .close-notification:hover { color: red; }

        .adjacent-dates { width: 100%; margin: 10px 0; }
        #add-panel-manual { justify-content: space-between; align-items: stretch; }
        .adjacent-dates { display: flex; flex-direction: column; overflow: visible; margin: 0; }
        #prev-dates { flex: 1 1 0; justify-content: flex-end; }
        #next-dates { flex: 1 1 0; justify-content: flex-start; }
        .adjacent-date { display: flex; justify-content: space-between; padding: 5px; background: var(--group-block-bg); border-radius: 4px; margin: 5px 0; opacity: 0.7; cursor: pointer; }
        .adjacent-date:hover { opacity: 1; background: var(--primary-color); color: #fff; }
        .current-date { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px; background: var(--modal-bg); border: 1px solid var(--primary-color); border-radius: 4px; margin: 10px auto; width: min(100%, 420px); box-sizing: border-box; }
        .current-date input[type="date"], .current-date input[type="text"] { flex: 1 1 0; min-width: 0; width: auto; font-size: 16px; box-sizing: border-box; }
        .current-date input[type="date"] { flex-basis: 150px; min-width: 145px; }

        #notifications { position: fixed; bottom: 20px; right: 20px; z-index: 20000; display: flex; flex-direction: column-reverse; align-items: flex-end; gap: 10px; max-width: min(100vw - 32px, 100%); box-sizing: border-box; }
        #worker-progress-widget { position: fixed; left: 20px; bottom: 20px; z-index: 20000; display: none; flex-direction: column; gap: 8px; align-items: flex-start; }
        .toast-notification { animation: toastIn 0.3s ease-out; }
        .toast-in-success { animation: toastInSuccess 0.5s ease-out; }
        .toast-in-error { animation: toastInError 0.5s ease-out; }
        .toast-in-info { animation: toastInInfo 0.4s ease-out; }
        @keyframes toastIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes toastOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .worker-progress-item { display: inline-flex; align-items: center; gap: 8px; padding: 8px; border-radius: 8px; background: var(--modal-bg); border: 1px solid var(--border-color); box-shadow: 0 3px 14px rgba(0,0,0,0.18); font-size: 13px; line-height: 1.2; width: fit-content; }
        .worker-progress-item i { width: 14px; text-align: center; color: var(--primary-color); }
        .worker-progress-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Alert modal */
        .alert-content {
            max-width: 400px;
            text-align: center;
        }

        .alert-message {
            margin: 20px 0;
            font-size: 16px;
        }

        .alert-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .alert-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            min-width: 80px;
        }

        .alert-btn.ok {
            background: var(--primary-color);
            color: white;
        }

        .alert-btn.cancel {
            background: var(--group-block-bg);
            color: var(--text-color);
        }

        .alert-btn.extra {
            background: #ffc107;
            color: #212529;
        }

/* Info modal */
        .info-content {
            max-width: 800px;
            gap: 20px;
            padding: 24px;
        }

        .info-header {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 16px;
            align-items: center;
            padding: 0 8px;
        }

        .info-header-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: color-mix(in srgb, var(--primary-color) 15%, transparent);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-header-icon svg,
        .info-header-icon i {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .info-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            line-height: 1.2;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
        }

        .info-card {
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
            background: var(--group-block-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        .info-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: color-mix(in srgb, var(--primary-color) 20%, transparent);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-card-icon svg,
        .info-card-icon i {
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .info-card h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card h3 svg {
            color: var(--primary-color);
        }

        .info-card p {
            margin: 2px 0 0;
            color: var(--text-color);
            line-height: 1.5;
            flex-grow: 1;
        }

        .mobile-app-content {
            max-width: 800px;
        }
        .mobile-app-content .modal-close {
            position: static;
        }

        /* Mobile App modal (action + features layout) */
        .mobile-app-content .ma-header {
            margin-bottom: 24px;
            padding-right: 40px;
        }
        .mobile-app-content .ma-header h1 {
            font-size: 24px;
            margin: 0 0 8px 0;
            font-weight: 600;
            letter-spacing: -0.5px;
            color: var(--text-color);
        }
        .mobile-app-content .ma-header p {
            color: var(--muted-text, var(--text-color));
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }
        .mobile-app-content .ma-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 24px;
        }
        .mobile-app-content .ma-action {
            position: relative;
            background: color-mix(in srgb, var(--surface-solid) 55%, transparent);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md, 12px);
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            justify-content: center;
        }
        .mobile-app-content .ma-qr-frame {
            position: relative;
            width: 220px;
            height: 220px;
            background-color: #fff;
            border-radius: var(--radius-sm, 8px);
            margin-bottom: 20px;
            padding: 12px;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .mobile-app-content .ma-qr-placeholder {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            filter: blur(6px);
            pointer-events: none;
        }
        .mobile-app-content .ma-qr-placeholder svg {
            width: 220px;
            height: 220px;
        }
        .mobile-app-content .ma-qr-code {
            position: absolute;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .mobile-app-content .ma-qr-code svg {
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            display: block;
        }
        .mobile-app-content .ma-qr-generate {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border: none;
            border-radius: var(--radius-sm, 8px);
            background: var(--primary-color, #3D8BFF);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
            transition: background-color 0.2s, transform 0.1s;
            z-index: 1;
        }
        .mobile-app-content .ma-qr-generate:hover:not(:disabled) {
            filter: brightness(1.08);
        }
        .mobile-app-content .ma-qr-generate:active {
            transform: translate(-50%, -50%) scale(0.98);
        }
        .mobile-app-content .ma-qr-generate:disabled {
            opacity: 0.6;
            cursor: wait;
        }
        .mobile-app-content .ma-qr-revoke {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin: -8px 0 16px;
            padding: 8px 14px;
            border: 1px solid color-mix(in srgb, #ef4444 45%, var(--border-color));
            border-radius: var(--radius-sm, 8px);
            background: color-mix(in srgb, #ef4444 12%, var(--surface-solid));
            color: #ef4444;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background-color 0.2s, transform 0.1s, filter 0.2s;
        }
        .mobile-app-content .ma-qr-revoke:hover:not(:disabled) {
            background: color-mix(in srgb, #ef4444 18%, var(--surface-solid));
            filter: brightness(1.04);
        }
        .mobile-app-content .ma-qr-revoke:active {
            transform: scale(0.98);
        }
        .mobile-app-content .ma-qr-revoke:disabled {
            opacity: 0.6;
            cursor: wait;
        }
        /* Copy-link/revoke stack, aligned to the QR width like the spends app. */
        .mobile-app-content .ma-qr-actions {
            display: none;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            width: 220px;
            max-width: 100%;
            margin: -8px 0 16px;
        }
        .mobile-app-content .ma-qr-actions.is-visible {
            display: flex;
        }
        .mobile-app-content .ma-qr-actions .ma-qr-revoke {
            margin: 0;
        }
        .mobile-app-content .ma-copy-link-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            padding: 8px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm, 8px);
            background: var(--surface-solid);
            color: var(--primary-color, #3D8BFF);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
        }
        .mobile-app-content .ma-copy-link-btn:hover {
            background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-solid));
            border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
        }
        .mobile-app-content .ma-copy-link-btn.is-copied {
            background: #166534;
            border-color: #166534;
            color: #fff;
        }
        .mobile-app-content .ma-copy-link-btn.is-empty {
            opacity: 0.6;
        }
        .mobile-app-content .ma-qr-hint {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 26px;
            height: 26px;
            border: none;
            border-radius: 50%;
            background: #ffffff;
            color: #7c3aed;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(124, 58, 237, 0.35);
            transition: color 0.2s ease, transform 0.15s ease, background-color 0.2s ease, box-shadow 0.2s ease;
            z-index: 2;
        }
        .mobile-app-content .ma-qr-hint:hover {
            color: #5b21b6;
            background: #f8f5ff;
            transform: scale(1.12);
            box-shadow: 0 4px 16px rgba(124, 58, 237, 0.45), 0 0 0 1px rgba(124, 58, 237, 0.45);
        }
        .mobile-app-content .ma-qr-hint:active {
            transform: scale(0.95);
        }

        /* Copy buttons with animated check */
        .ma-copy-btn {
            position: relative;
            align-self: flex-end;
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: color-mix(in srgb, var(--surface-solid) 70%, transparent);
            color: var(--muted-text, var(--text-color));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.12s ease;
            overflow: hidden;
        }
        .ma-copy-btn:hover {
            color: var(--primary-color, #3D8BFF);
            border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
            background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-solid));
        }
        .ma-copy-btn:active {
            transform: scale(0.92);
        }
        .ma-copy-btn .ma-copy-check {
            position: absolute;
            opacity: 0;
            transform: scale(0.4) rotate(-30deg);
            transition: opacity 0.18s ease, transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .ma-copy-btn .ma-copy-icon {
            transition: opacity 0.15s ease, transform 0.18s ease;
        }
        .ma-copy-btn.is-copied {
            color: #fff;
            background: #22c55e;
            border-color: #22c55e;
        }
        .ma-copy-btn.is-copied .ma-copy-icon {
            opacity: 0;
            transform: scale(0.4);
        }
        .ma-copy-btn.is-copied .ma-copy-check {
            opacity: 1;
            transform: scale(1) rotate(0deg);
        }
        .ma-copy-btn.is-empty {
            animation: maCopyShake 0.4s ease;
        }
        @keyframes maCopyShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            50% { transform: translateX(4px); }
            75% { transform: translateX(-3px); }
        }
        .ma-copy-btn.is-copied::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: radial-gradient(circle, rgba(255,255,255,0.55) 10%, transparent 10.01%) center/10000% 10000% no-repeat;
            animation: maCopyRipple 0.6s ease forwards;
            pointer-events: none;
        }
        @keyframes maCopyRipple {
            from { background-size: 0% 0%; opacity: 1; }
            to { background-size: 220% 220%; opacity: 0; }
        }

        /* Manual pairing modal */
        #manual-pair-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(10,14,18,0.48);
            -webkit-backdrop-filter: blur(14px);
            backdrop-filter: blur(14px);
            z-index: 10030;
            box-sizing: border-box;
            overflow: hidden;
        }
        #manual-pair-modal .manual-pair-content {
            position: relative;
            width: min(560px, 100%);
            max-width: min(560px, calc(100vw - 32px));
            max-height: calc(100dvh - 32px);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            box-shadow: var(--shadow-soft);
            background: color-mix(in srgb, var(--modal-bg) 92%, var(--surface-solid));
            padding: 24px;
            overflow: auto;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        #manual-pair-modal .manual-pair-steps {
            margin: 0 0 18px;
            padding-left: 20px;
            display: grid;
            gap: 8px;
            color: var(--text-color);
            font-size: 13.5px;
            line-height: 1.5;
        }
        #manual-pair-modal .manual-pair-fields {
            display: grid;
            gap: 12px;
        }
        #manual-pair-modal .manual-pair-row {
            display: flex;
            gap: 10px;
            align-items: stretch;
        }
        #manual-pair-modal .manual-pair-field {
            flex: 1;
            min-width: 0;
        }
        #manual-pair-modal .manual-pair-field label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.7;
            margin: 0 0 6px;
            color: var(--text-color);
        }
        #manual-pair-modal .manual-pair-field pre {
            margin: 0;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--group-block-bg);
            color: var(--text-color);
            font: 12.5px/1.45 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            word-break: break-all;
            white-space: pre-wrap;
            min-height: 42px;
            box-sizing: border-box;
        }
        #manual-pair-modal .manual-pair-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
        }
        #manual-pair-modal .manual-pair-regen-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border: none;
            border-radius: 10px;
            background: var(--primary-color, #3D8BFF);
            color: #fff;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            transition: filter 0.18s ease, transform 0.12s ease, opacity 0.18s ease;
        }
        #manual-pair-modal .manual-pair-regen-btn:hover:not(:disabled) {
            filter: brightness(1.08);
        }
        #manual-pair-modal .manual-pair-regen-btn:active:not(:disabled) {
            transform: scale(0.97);
        }
        #manual-pair-modal .manual-pair-regen-btn:disabled {
            opacity: 0.6;
            cursor: wait;
        }
        #manual-pair-modal .manual-pair-regen-btn.is-busy svg {
            animation: maSpin 0.9s linear infinite;
        }
        @keyframes maSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @media (max-width: 640px) {
            #manual-pair-modal .manual-pair-row {
                flex-direction: column;
            }
            #manual-pair-modal .ma-copy-btn {
                align-self: flex-end;
                width: 100%;
                justify-content: center;
            }
        }
        .mobile-app-content .ma-action h3 {
            font-size: 16px;
            margin: 0 0 8px 0;
            color: var(--text-color);
        }
        .mobile-app-content .ma-action > p {
            font-size: 13px;
            color: var(--muted-text, var(--text-color));
            margin: 0 0 24px 0;
            line-height: 1.4;
        }
        .mobile-app-content .ma-download-row {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }
        .mobile-app-content .ma-download,
        .mobile-app-content .ma-play-protect {
            border: none;
            padding: 14px 18px;
            border-radius: var(--radius-sm, 8px);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            box-sizing: border-box;
            justify-content: center;
            text-decoration: none;
            transition: background-color 0.2s, transform 0.1s, filter 0.2s;
        }
        .mobile-app-content .ma-download {
            background-color: var(--ma-accent, #4ade80);
            color: #000;
        }
        .mobile-app-content .ma-download:hover {
            background-color: var(--ma-accent-hover, #22c55e);
        }
        .mobile-app-content .ma-download:active,
        .mobile-app-content .ma-play-protect:active {
            transform: scale(0.98);
        }
        .mobile-app-content .ma-play-protect {
            background: color-mix(in srgb, var(--surface-solid) 70%, transparent);
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }
        .mobile-app-content .ma-play-protect:hover {
            border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
            background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-solid));
            color: var(--primary-color, #3D8BFF);
        }
        .mobile-app-content .ma-meta {
            display: flex;
            gap: 16px;
            margin-top: 16px;
            font-size: 11px;
            color: var(--muted-text, #666);
        }
        .mobile-app-content .ma-features {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 12px;
            min-height: 100%;
        }
        .mobile-app-content .ma-feature {
            display: flex;
            flex: 1 1 0;
            align-items: center;
            gap: 16px;
            padding: 16px;
            box-sizing: border-box;
            background-color: transparent;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md, 12px);
            transition: background-color 0.2s, border-color 0.2s;
        }
        .mobile-app-content .ma-feature:hover {
            background-color: color-mix(in srgb, var(--surface-solid) 55%, transparent);
            border-color: color-mix(in srgb, var(--primary-color) 26%, var(--border-color));
        }
        .mobile-app-content .ma-feature-icon {
            background-color: color-mix(in srgb, var(--ma-accent, #4ade80) 12%, transparent);
            color: var(--ma-accent, #4ade80);
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm, 8px);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .mobile-app-content .ma-feature-text {
            min-width: 0;
            text-align: left;
        }
        .mobile-app-content .ma-feature-text h4 {
            margin: 0 0 4px 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
        }
        .mobile-app-content .ma-feature-text p {
            margin: 0;
            font-size: 12px;
            color: var(--muted-text, var(--text-color));
            line-height: 1.4;
        }
        @media (max-width: 650px) {
            .mobile-app-content .ma-grid {
                grid-template-columns: 1fr;
            }
            .mobile-app-content .ma-features {
                min-height: 0;
            }
            .mobile-app-content .ma-feature {
                flex: 0 0 auto;
            }
        }

        .hotkey {
            display: inline-block;
            padding: 2px 6px;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            margin: 0 3px;
        }

        /* Backups modal */
        .backups-list {
            max-height: 400px;
            overflow-y: auto;
            margin: 20px 0;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .backup-table-wrap {
            padding: 0;
        }

        .backup-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .backup-table th,
        .backup-table td {
            padding: 10px 12px;
            border-top: 1px solid var(--border-color);
            vertical-align: top;
            text-align: left;
            word-break: break-word;
        }

        .backup-table thead th {
            border-top: none;
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--group-block-bg);
            color: var(--muted-text);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .backup-table td.actions-cell {
            width: 1%;
            white-space: nowrap;
        }

        .backup-name {
            font-weight: 800;
        }

        .backup-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            align-items: center;
        }

        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .backup-item:last-child {
            border-bottom: none;
        }

        .backup-info {
            flex-grow: 1;
        }

        .backup-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .backup-details {
            font-size: 12px;
            color: #666;
        }

        .backup-actions {
            display: flex;
            gap: 10px;
        }

        .backup-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .backup-btn.restore {
            background: #28a745;
            color: white;
        }

        .backup-btn.delete {
            background: #dc3545;
            color: white;
        }

        .settings-backup-actions {
            margin: 0 0 14px;
            justify-content: flex-start;
        }

        .settings-backup-import {
            margin-top: 14px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--group-block-bg);
            display: none;
        }
        .settings-backup-import.active {
            display: block;
        }

        .settings-backup-import h3 {
            margin: 0 0 12px;
            font-size: 15px;
        }

        .settings-backup-import-grid {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) auto auto;
            gap: 10px;
            align-items: end;
        }

        .settings-backup-import label {
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
            opacity: 0.8;
        }

        .settings-backup-import input[type="file"] {
            width: 100%;
            box-sizing: border-box;
            padding: 6px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--modal-bg);
            color: var(--text-color);
            font-size: 14px;
        }

        .settings-backup-import-mode {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 4px;
        }
        .settings-backup-import-mode label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            background: var(--surface-solid);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
        }
        .settings-backup-import-mode label:has(input[type="radio"]:checked) {
            border-color: var(--primary-color);
            background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-solid));
        }
        .settings-backup-import-mode label:hover {
            background: color-mix(in srgb, var(--primary-color) 8%, var(--surface-solid));
            border-color: color-mix(in srgb, var(--primary-color) 30%, var(--border-color));
        }
        .settings-backup-import-mode input[type="radio"] {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: var(--primary-color);
            cursor: pointer;
        }

        /* Table pagination */
        .table-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 0 4px;
            flex-wrap: wrap;
        }
        .page-btn {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--modal-bg);
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
        }
        .page-btn:disabled { opacity: 0.35; cursor: default; }
        .page-btn:not(:disabled):hover { background: var(--primary-color); color: #fff; }
        .page-info { font-size: 13px; color: var(--text-color); }

        /* Mobile adaptation */
        @media only screen and (max-width: 768px) {
            body.modal-open {
                padding: 0 !important;
            }
            body.modal-open #menu-button,
            body.modal-open [id$="-menu-button"],
            body.modal-open .menu-button,
            body.modal-open .menu-toggle,
            body.modal-open .mobile-menu-button {
                opacity: 0 !important;
                pointer-events: none !important;
                visibility: hidden !important;
            }

            .app-steps {
                max-width: 100%;
                min-height: 100dvh;
                display: flex;
                flex-direction: column;
                padding: calc(var(--topbar-height) + 10px) 8px 8px;
                margin: 0;
            }

            .app-topbar {
                min-height: var(--topbar-height);
                gap: 8px;
                padding: 6px 10px 6px var(--topbar-left-pad);
            }

            .app-topbar h1 {
                display: none;
            }

            #info-button, #settings-button, #add-steps-button, #stats-button, #sharepoint-share-button, #stepmonitor-apk-button {
                font-size: 20px;
                width: 35px;
                height: 35px;
            }
            #stats-button svg {
                width: 20px;
                height: 20px;
            }

            .app-topbar-actions {
                width: 100%;
                justify-content: flex-end;
                gap: 5px;
            }

            #user-button {
                width: 35px;
                height: 35px;
                padding: 0;
                box-sizing: border-box;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            #user-button img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: var(--radius-sm);
                box-sizing: border-box;
                display: block;
                flex-shrink: 0;
            }
            .app-main {
                flex: 1 1 auto;
                width: 100%;
                min-width: 0;
                box-sizing: border-box;
                grid-template-columns: 1fr;
                grid-template-areas:
                    "types"
                    "chart"
                    "view"
                    "calendar"
                    "goal";
                gap: 10px;
                padding: 10px;
                border-radius: 14px;
            }
            .data-type-selector {
                width: 100%;
                max-width: 100%;
                justify-self: stretch;
                margin-left: 0;
                margin-right: 0;
                grid-template-columns: repeat(6, minmax(0, 1fr));
                gap: 6px;
                box-sizing: border-box;
            }
            .data-type-btn {
                height: 54px;
                border-radius: 10px;
            }
            .data-type-btn svg {
                width: 26px;
                height: 26px;
            }
            .view-selector {
                width: 100%;
                box-sizing: border-box;
            }
            .chart-container {
                width: 100%;
                box-sizing: border-box;
            }
            .calendar-container {
                width: 100%;
                min-width: 0;
                padding: 0;
                box-sizing: border-box;
            }
            .calendar-table {
                width: 100%;
                font-size: 11px;
                table-layout: fixed;
                border-spacing: 2px;
            }
            .calendar-table th, .calendar-table td {
                padding: 0;
                height: 32px;
                box-sizing: border-box;
            }
            .goal-summary {
                width: 100%;
                box-sizing: border-box;
                grid-template-columns: 1fr 1fr;
                padding: 10px 12px;
            }
            .goal-icon { width: 12px; height: 12px; right: 2px; left: auto; bottom: 2px; top: auto; transform: none; }

            .chart-wrapper { width: 100%; height: 240px; min-height: 240px; position: relative; overflow: hidden; box-sizing: border-box; padding-top: 4px; }
            .chart-container { width: 100%; max-width: 100%; min-height: 310px; padding: 8px; box-sizing: border-box; flex-direction: row; align-items: stretch; gap: 5px; }
            .chart-main { width: 100%; min-width: 0; max-width: none; padding-top: 46px; box-sizing: border-box; }
            .chart-label { font-size: 20px; }
            #steps-chart { width: 100% !important; height: 100% !important; min-height: 240px; }
            .chart-nav { width: 28px; height: 52px; font-size: 14px; }
            .calendar-header .chart-nav { width: 32px; font-size: 13px; }

            .settings-content, .add-steps-content, .user-content, .add-user-content, .video-confirm-inner, .video-confirm-gaps-inner, .excel-content, .backups-content, .alert-content, .info-content, .stats-content, .excel-hotkeys-inner, .mobile-app-content {
                width: 100dvw !important;
                max-width: 100dvw !important;
                height: 100dvh !important;
                max-height: 100dvh !important;
                padding: 14px;
                margin: 0;
                border-radius: 0;
                overflow: auto;
                overflow-x: hidden;
            }
            #info-modal:not(#guide-expand-modal), #stats-modal:not(#guide-expand-modal), #settings-modal:not(#guide-expand-modal), #add-steps-modal:not(#guide-expand-modal), #user-modal:not(#guide-expand-modal), #add-user-modal:not(#guide-expand-modal), #video-confirm-modal:not(#guide-expand-modal), #video-confirm-gaps-modal:not(#guide-expand-modal), #alert-modal:not(#guide-expand-modal), #video-crop-modal:not(#guide-expand-modal), #excel-modal:not(#guide-expand-modal), #excel-hotkeys-modal:not(#guide-expand-modal), #backups-modal:not(#guide-expand-modal), #mobile-app-modal:not(#guide-expand-modal) {
                width: 100dvw !important;
                height: 100dvh !important;
                padding: 0 !important;
                align-items: stretch !important;
                justify-content: stretch !important;
            }

            #guide-expand-modal .guide-expand-content,
            #video-crop-modal .video-crop-inner {
                width: 100dvw !important;
                max-width: 100dvw !important;
                height: 100dvh !important;
                max-height: 100dvh !important;
                border-radius: 0 !important;
            }
            #add-steps-modal .add-steps-merged {
                width: 100dvw !important;
                max-width: 100dvw !important;
                height: 100dvh !important;
                max-height: 100dvh !important;
                padding: 14px !important;
                box-sizing: border-box !important;
                min-width: 0 !important;
            }
            #add-steps-modal .add-tab-bar {
                box-sizing: border-box;
                width: 100%;
                min-width: 0;
            }
            #add-steps-modal .add-tab {
                min-width: 0;
                gap: 4px;
            }
            #add-steps-modal #add-panel-video .va-head,
            #add-steps-modal #add-panel-video .video-inline-scroll,
            #add-panel-takeout .va-head,
            #add-panel-takeout .video-inline-scroll {
                padding: 12px 12px 14px;
                box-sizing: border-box;
                min-width: 0;
            }
            #add-steps-modal #add-panel-video .video-analysis-content,
            #add-panel-takeout .video-analysis-content {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                box-sizing: border-box;
            }
            #add-steps-modal #add-panel-video .va-grid,
            #add-panel-takeout .va-grid,
            #add-steps-modal .va-steps,
            #add-panel-takeout .va-steps {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                box-sizing: border-box;
            }
            #add-steps-modal .va-segment,
            #add-panel-takeout .va-segment {
                width: 100%;
                margin: 0;
                box-sizing: border-box;
                padding: 12px;
            }
            #add-steps-modal .va-step-line {
                margin: 0 6px;
            }
            .add-tab {
                font-size: 13px;
                padding: 10px 8px;
            }
            .add-tab-panel {
                flex: 1 1 0;
                min-height: 0;
            }
            #add-steps-modal #add-panel-video.add-tab-panel {
                overflow-y: auto !important;
            }
            #add-steps-modal #add-panel-takeout.add-tab-panel {
                overflow-y: auto !important;
            }

            .settings-content input[name="birth_date"] {
                width: 120px;
            }

            .excel-table-container {
                max-height: 50vh;
            }
            .excel-modal-head {
                grid-template-columns: 44px minmax(0, 1fr) 44px;
                column-gap: 4px;
            }
            .excel-modal-head .excel-head-spacer {
                width: 38px;
                height: 38px;
            }
            #excel-modal .excel-table-stack {
                flex: 1 1 0;
                min-height: 0;
                margin: 6px 0;
            }
            #excel-modal .excel-table-stack .excel-table-container {
                max-height: none;
                flex: 1 1 0;
                min-height: 60px;
            }
            #excel-modal .excel-actions {
                flex-direction: row;
                flex-wrap: nowrap;
            }
            #excel-modal .excel-actions .excel-btn {
                width: auto;
            }
            #info-modal .info-scroll {
                padding: 14px;
            }
            .info-header {
                grid-template-columns: 1fr;
                padding-right: 36px;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .info-card {
                min-height: 0;
            }
            .settings-backup-import-grid {
                grid-template-columns: 1fr;
            }
            .settings-backup-import-mode {
                align-items: flex-start;
                flex-direction: column;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .stats-chart-wrap {
                height: 300px;
            }
            #settings-modal .settings-content {
                width: 100dvw !important;
                max-width: 100dvw !important;
            }
            .settings-tab-panel {
                min-height: 0;
                max-height: none;
            }

            .excel-table th, .excel-table td {
                padding: 4px;
                font-size: 12px;
                min-width: 80px;
            }

            .excel-search-container {
                padding: 10px;
            }

            .excel-hotkeys-info {
                font-size: 10px;
            }

            .hotkey {
                padding: 1px 4px;
                font-size: 10px;
            }

            .excel-actions {
                flex-direction: column;
            }

            .excel-btn {
                width: 100%;
                justify-content: center;
            }

            .backup-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .backup-actions {
                width: 100%;
                justify-content: flex-end;
                margin-top: 10px;
            }

            #add-panel-video .video-analysis-content {
                flex: 1 1 0;
                min-height: 0;
                margin: 0 auto;
            }
            #video-crop-modal .cropper-view-box { border-width: 4px !important; box-shadow: 0 0 0 2px #000, 0 0 0 6px #fff !important; }
            #video-crop-modal .cropper-point {
                width: 22px !important;
                height: 22px !important;
                opacity: 1 !important;
                background-color: #fff !important;
                border: 3px solid #000 !important;
                box-sizing: border-box !important;
            }
            #video-crop-modal .cropper-point.point-e {
                margin-top: -11px !important;
                right: -11px !important;
            }
            #video-crop-modal .cropper-point.point-n {
                margin-left: -11px !important;
                top: -11px !important;
            }
            #video-crop-modal .cropper-point.point-ne {
                right: -11px !important;
                top: -11px !important;
            }
            #video-crop-modal .cropper-point.point-nw {
                left: -11px !important;
                top: -11px !important;
            }
            #video-crop-modal .cropper-point.point-s {
                margin-left: -11px !important;
                bottom: -11px !important;
            }
            #video-crop-modal .cropper-point.point-se {
                bottom: -11px !important;
                right: -11px !important;
            }
            #video-crop-modal .cropper-point.point-sw {
                bottom: -11px !important;
                left: -11px !important;
            }
            #video-crop-modal .cropper-point.point-w {
                margin-top: -11px !important;
                left: -11px !important;
            }
            #video-crop-modal .cropper-line.line-e {
                width: 20px !important;
                right: -10px !important;
            }
            #video-crop-modal .cropper-line.line-n {
                height: 20px !important;
                top: -10px !important;
            }
            #video-crop-modal .cropper-line.line-w {
                width: 20px !important;
                left: -10px !important;
            }
            #video-crop-modal .cropper-line.line-s {
                height: 20px !important;
                bottom: -10px !important;
            }
            #notifications { bottom: 10px; right: 10px; }
            .notification { width: 100%; max-width: min(360px, calc(100vw - 24px)); max-height: 180px; }
            #worker-progress-widget { position: static; left: auto; bottom: auto; width: auto; margin: 12px 0 18px; }

            .data-type-btn {
                width: 60px;
                height: 60px;
                margin: 0;
            }

            .data-type-selector {
                justify-content: center;
                width: min(100%, 360px);
            }

            .backup-table thead {
                display: none;
            }

            .backup-table,
            .backup-table tbody,
            .backup-table tr {
                display: block;
                width: 100%;
            }

            .backup-table tr {
                border-top: 1px solid var(--border-color);
                padding: 8px 0;
            }

            .backup-table td {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                border-top: none;
                padding: 6px 12px;
            }

            .backup-table td::before {
                content: attr(data-label);
                color: var(--muted-text);
                font-size: 12px;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                margin-right: 12px;
            }

            .backup-table td.actions-cell {
                justify-content: flex-end;
            }

            .tile-content {
                grid-template-columns: 30px 1fr 50px;
            }

            .settings-content input[type="text"],
            .settings-content input[type="date"],
            .add-user-content input[type="text"] {
                width: 100%;
            }

            .current-date { width: 100%; }
            .current-date input[type="date"] { min-width: 145px; }
        }
.app-steps button, .excel-btn, .save-button, .alert-btn, .modal-close, .data-type-btn, .view-btn, .chart-type-btn, .settings-tab, .add-tab, .page-btn {
transition: transform 0.16s ease, box-shadow 0.16s ease, background-color 0.16s ease, border-color 0.16s ease, color 0.16s ease, opacity 0.16s ease;
}
.app-steps button.is-pressed, .excel-btn.is-pressed, .save-button.is-pressed, .alert-btn.is-pressed, .modal-close.is-pressed, .data-type-btn.is-pressed, .view-btn.is-pressed, .chart-type-btn.is-pressed, .settings-tab.is-pressed, .add-tab.is-pressed, .page-btn.is-pressed {
transform: scale(0.96);
}
.app-steps button.copied, .excel-btn.copied {
background: #16a34a !important;
border-color: #16a34a !important;
color: #fff !important;
box-shadow: 0 10px 24px rgba(22,163,74,0.26);
}
.data-type-selector {
grid-template-columns: repeat(6, minmax(0, 1fr));
}
@media (min-width: 769px) and (max-width: 1063px) {
.data-type-selector {
grid-template-columns: repeat(3, minmax(0, 1fr));
}
}
@media (max-width: 768px) {
.data-type-selector {
grid-template-columns: repeat(6, minmax(0, 1fr));
}
}
.data-type-btn {
height: clamp(48px, 6.5vh, 72px);
padding: 0;
border-radius: 14px;
background: color-mix(in srgb, var(--surface-solid) 88%, transparent);
}
.data-type-btn svg {
width: clamp(24px, 3.5vh, 36px);
height: clamp(24px, 3.5vh, 36px);
display: block;
filter: drop-shadow(0 8px 14px rgba(0,0,0,0.12));
transition: transform 0.18s ease;
}
.data-type-btn:hover svg, .data-type-btn.active svg {
transform: scale(1.08);
}
#info-modal:not(#guide-expand-modal), #stats-modal:not(#guide-expand-modal), #settings-modal:not(#guide-expand-modal), #add-steps-modal:not(#guide-expand-modal), #user-modal:not(#guide-expand-modal), #add-user-modal:not(#guide-expand-modal), #video-confirm-modal:not(#guide-expand-modal), #video-confirm-gaps-modal:not(#guide-expand-modal), #alert-modal:not(#guide-expand-modal), #video-crop-modal:not(#guide-expand-modal), #excel-modal:not(#guide-expand-modal), #excel-hotkeys-modal:not(#guide-expand-modal), #backups-modal:not(#guide-expand-modal), #mobile-app-modal:not(#guide-expand-modal) {
position: fixed;
inset: 0;
display: none;
align-items: center;
justify-content: center;
padding: 16px;
background: rgba(10,14,18,0.48);
-webkit-backdrop-filter: blur(14px);
backdrop-filter: blur(14px);
z-index: 10000;
box-sizing: border-box;
overflow: hidden;
}
.settings-content, .add-steps-content, .user-content, .add-user-content, .video-analysis-content, .video-confirm-inner, .video-confirm-gaps-inner, .excel-content, .backups-content, .alert-content, .info-content, .stats-content, .excel-hotkeys-inner, .mobile-app-content {
width: min(920px, 100%);
max-width: calc(100vw - 32px);
max-height: calc(100dvh - 32px);
height: auto;
border: 1px solid var(--border-color);
border-radius: 18px;
box-shadow: var(--shadow-soft);
background: color-mix(in srgb, var(--modal-bg) 92%, var(--surface-solid));
padding: 22px;
overflow: auto;
}
.modal-close {
top: 12px;
right: 12px;
width: 38px;
height: 38px;
display: inline-flex;
align-items: center;
justify-content: center;
padding: 0;
border-radius: 12px;
background: color-mix(in srgb, var(--surface-solid) 72%, transparent);
border: 1px solid var(--border-color);
color: var(--muted-text);
line-height: 1;
}
.modal-close:hover {
background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-solid));
color: var(--primary-color);
border-color: color-mix(in srgb, var(--primary-color) 36%, var(--border-color));
}
@media (max-width: 640px) {
#info-modal:not(#guide-expand-modal), #stats-modal:not(#guide-expand-modal), #settings-modal:not(#guide-expand-modal), #add-steps-modal:not(#guide-expand-modal), #user-modal:not(#guide-expand-modal), #add-user-modal:not(#guide-expand-modal), #video-confirm-modal:not(#guide-expand-modal), #video-confirm-gaps-modal:not(#guide-expand-modal), #alert-modal:not(#guide-expand-modal), #video-crop-modal:not(#guide-expand-modal), #excel-modal:not(#guide-expand-modal), #excel-hotkeys-modal:not(#guide-expand-modal), #backups-modal:not(#guide-expand-modal), #mobile-app-modal:not(#guide-expand-modal) {
padding: 0 !important;
align-items: stretch !important;
justify-content: stretch !important;
}
.settings-content, .add-steps-content, .user-content, .add-user-content, .video-confirm-inner, .video-confirm-gaps-inner, .excel-content, .backups-content, .alert-content, .info-content, .stats-content, .excel-hotkeys-inner, .mobile-app-content {
width: 100dvw !important;
max-width: 100dvw !important;
height: 100dvh !important;
max-height: 100dvh !important;
border-radius: 0 !important;
padding: 16px !important;
}
.data-type-selector {
grid-template-columns: repeat(6, minmax(0, 1fr));
width: 100%;
max-width: 100%;
}
.data-type-btn {
height: 48px;
}
.data-type-btn svg {
    width: 24px;
    height: 24px;
    }
}

/* --- Confirm / alert dialog --- */
#alert-modal:not(#guide-expand-modal) {
    padding: 16px !important;
    align-items: center !important;
    justify-content: center !important;
    animation: alertFadeIn 0.25s ease;
}
@keyframes alertFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
#alert-modal:not(#guide-expand-modal) .alert-content {
    width: min(420px, 100%) !important;
    max-width: calc(100vw - 32px) !important;
    height: auto !important;
    max-height: calc(100dvh - 32px) !important;
    padding: 0 !important;
    border-radius: 22px !important;
    overflow: hidden;
    text-align: left;
    display: flex;
    flex-direction: column;
    animation: alertPopIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes alertPopIn {
    from { transform: scale(0.95) translateY(10px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}
#alert-modal:not(#guide-expand-modal) .alert-close {
    position: absolute;
    top: 16px;
    right: 16px;
}
#alert-modal:not(#guide-expand-modal) .alert-header {
    padding: 24px 56px 14px 24px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
#alert-modal:not(#guide-expand-modal) .alert-header.is-empty {
    display: none;
}
#alert-modal:not(#guide-expand-modal) .alert-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1.3;
    color: var(--text-color);
}
#alert-modal:not(#guide-expand-modal) .alert-subtitle {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
    color: var(--muted-text);
}
#alert-modal:not(#guide-expand-modal) .alert-body {
    padding: 0 24px;
}
#alert-modal:not(#guide-expand-modal) .alert-box {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border-radius: 14px;
    padding: 14px 16px;
    border: 1px solid transparent;
    font-size: 14px;
    line-height: 1.5;
    color: var(--text-color);
    text-align: left;
}
#alert-modal:not(#guide-expand-modal) .alert-box.danger {
    border-color: rgba(239, 68, 68, 0.55);
    background: rgba(239, 68, 68, 0.12);
}
#alert-modal:not(#guide-expand-modal) .alert-box.warning {
    border-color: rgba(245, 158, 11, 0.6);
    background: rgba(245, 158, 11, 0.14);
}
#alert-modal:not(#guide-expand-modal) .alert-box.info {
    border-color: color-mix(in srgb, var(--primary-color) 50%, transparent);
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
}
#alert-modal:not(#guide-expand-modal) .alert-icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 1px;
}
#alert-modal:not(#guide-expand-modal) .alert-box.danger .alert-icon { color: #ef4444; }
#alert-modal:not(#guide-expand-modal) .alert-box.warning .alert-icon { color: #f59e0b; }
#alert-modal:not(#guide-expand-modal) .alert-box.info .alert-icon { color: var(--primary-color); }
#alert-modal:not(#guide-expand-modal) .alert-message {
    margin: 0;
    min-width: 0;
}
#alert-modal:not(#guide-expand-modal) .alert-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 20px 24px 24px;
    margin-top: 0;
}
#alert-modal:not(#guide-expand-modal) .alert-btn {
    flex: 1 1 0;
    padding: 12px 18px;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}
#alert-modal:not(#guide-expand-modal) .alert-btn:active { transform: translateY(1px); }
#alert-modal:not(#guide-expand-modal) .alert-btn.ok {
    background: var(--primary-color);
    color: #fff;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--primary-color) 35%, transparent);
}
#alert-modal:not(#guide-expand-modal) .alert-btn.ok:hover { transform: translateY(-1px); filter: brightness(1.05); }
#alert-modal:not(#guide-expand-modal) .alert-btn.ok.danger {
    background: #ef4444;
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);
}
#alert-modal:not(#guide-expand-modal) .alert-btn.ok.danger:hover {
    background: #dc2626;
}
#alert-modal:not(#guide-expand-modal) .alert-btn.ok.warning {
    background: #f59e0b;
    color: #1c1917;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);
}
#alert-modal:not(#guide-expand-modal) .alert-btn.ok.warning:hover {
    background: #fbbf24;
}
#alert-modal:not(#guide-expand-modal) .alert-btn.cancel {
    background: color-mix(in srgb, var(--surface-solid) 78%, transparent);
    border: 1px solid var(--border-color);
    color: var(--text-color);
}
#alert-modal:not(#guide-expand-modal) .alert-btn.cancel:hover {
    background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-solid));
}
#alert-modal:not(#guide-expand-modal) .alert-btn.extra {
    background: color-mix(in srgb, var(--primary-color) 14%, transparent);
    color: var(--primary-color);
}
#alert-modal:not(#guide-expand-modal) .alert-btn.extra:hover {
    background: color-mix(in srgb, var(--primary-color) 24%, transparent);
}

/* --- Video confirm, gaps & sync-conflict windows --- */
#video-confirm-modal:not(#guide-expand-modal), #video-confirm-gaps-modal:not(#guide-expand-modal) {
    padding: 16px !important;
    align-items: center !important;
    justify-content: center !important;
    animation: alertFadeIn 0.25s ease;
}
#video-confirm-modal .video-confirm-inner,
#video-confirm-gaps-modal .video-confirm-gaps-inner {
    width: min(680px, 100%) !important;
    max-width: calc(100vw - 32px) !important;
    height: auto !important;
    max-height: calc(100dvh - 32px) !important;
    padding: 24px !important;
    box-sizing: border-box;
    border-radius: 22px !important;
    animation: alertPopIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
#video-confirm-gaps-modal .video-confirm-gaps-inner {
    width: min(520px, 100%) !important;
}
#video-confirm-modal .modal-close,
#video-confirm-gaps-modal .modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
}
#stepmonitor-conflict-modal .smc-card {
    width: min(480px, 100%);
    max-width: calc(100vw - 32px);
    max-height: calc(100dvh - 32px);
    overflow-y: auto;
    background: color-mix(in srgb, var(--modal-bg) 92%, var(--surface-solid));
    color: var(--text-color);
    border: 1px solid var(--border-color);
    border-radius: 22px;
    box-shadow: var(--shadow-soft);
    padding: 0;
    text-align: left;
    animation: alertPopIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
#stepmonitor-conflict-modal .smc-head {
    padding: 24px 24px 14px;
}
#stepmonitor-conflict-modal .smc-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1.3;
    color: var(--text-color);
}
#stepmonitor-conflict-modal .smc-body {
    padding: 0 24px;
}
#stepmonitor-conflict-modal .smc-box {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border-radius: 14px;
    padding: 14px 16px;
    border: 1px solid rgba(245, 158, 11, 0.6);
    background: rgba(245, 158, 11, 0.14);
    font-size: 14px;
    line-height: 1.5;
    color: var(--text-color);
    text-align: left;
}
#stepmonitor-conflict-modal .smc-icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 1px;
    color: #f59e0b;
}
#stepmonitor-conflict-modal .smc-msg {
    margin: 0;
    min-width: 0;
}
#stepmonitor-conflict-modal .smc-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 20px 24px 24px;
    flex-wrap: wrap;
}
#stepmonitor-conflict-modal .smc-btn {
    flex: 1 1 0;
    padding: 12px 18px;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}
#stepmonitor-conflict-modal .smc-btn:active { transform: translateY(1px); }
#stepmonitor-conflict-modal .smc-btn.cancel {
    background: color-mix(in srgb, var(--surface-solid) 78%, transparent);
    border: 1px solid var(--border-color);
    color: var(--text-color);
}
#stepmonitor-conflict-modal .smc-btn.cancel:hover {
    background: color-mix(in srgb, var(--primary-color) 10%, var(--surface-solid));
}
#stepmonitor-conflict-modal .smc-btn.extra {
    background: color-mix(in srgb, var(--primary-color) 14%, transparent);
    color: var(--primary-color);
}
#stepmonitor-conflict-modal .smc-btn.extra:hover {
    background: color-mix(in srgb, var(--primary-color) 24%, transparent);
}
#stepmonitor-conflict-modal .smc-btn.ok {
    background: var(--primary-color);
    color: #fff;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--primary-color) 35%, transparent);
}
#stepmonitor-conflict-modal .smc-btn.ok:hover { transform: translateY(-1px); filter: brightness(1.05); }
#stepmonitor-conflict-modal .smc-btn.ok.warning {
    background: #f59e0b;
    color: #1c1917;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);
}
#stepmonitor-conflict-modal .smc-btn.ok.warning:hover { background: #fbbf24; }
#stepmonitor-conflict-modal .smc-btn:disabled { opacity: 0.55; cursor: default; }

#manual-pair-modal .manual-pair-content {
    overflow: hidden;
}
#manual-pair-modal .manual-pair-content::after {
    content: "";
    display: block;
    clear: both;
}
#manual-pair-modal .manual-pair-fields {
    overflow-y: auto;
    overflow-x: hidden;
    max-height: min(48dvh, 420px);
    padding-right: 4px;
    margin-right: -4px;
    scrollbar-gutter: stable;
}
#manual-pair-modal .manual-pair-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 42px;
    align-items: end;
}
#manual-pair-modal .manual-pair-field {
    min-width: 0;
}
#manual-pair-modal .ma-copy-btn {
    align-self: end;
}
.mobile-app-content .ma-qr-generate,
#manual-pair-modal .manual-pair-regen-btn {
    background: var(--primary-color, #3D8BFF);
    color: #fff;
    border-color: color-mix(in srgb, var(--primary-color) 80%, #000);
}
.mobile-app-content .ma-download {
    background-color: var(--ma-accent, #4ade80);
    color: #000;
}
@media (max-width: 520px) {
    #manual-pair-modal .manual-pair-content {
        max-height: calc(100dvh - 20px);
        padding: 18px;
        border-radius: 16px;
    }
    #manual-pair-modal .manual-pair-fields {
        max-height: min(44dvh, 360px);
    }
    #manual-pair-modal .manual-pair-row {
        grid-template-columns: minmax(0, 1fr) 40px;
        gap: 8px;
    }
    #manual-pair-modal .ma-copy-btn {
        width: 40px;
        height: 40px;
    }
}

/* --- Main mode switch: steps / map --- */
.mode-switch {
    align-self: center;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin: 0 auto 12px;
    padding: 4px;
    border: 1px solid var(--border-color);
    border-radius: 999px;
    background: var(--group-block-bg);
    box-shadow: 0 12px 32px rgba(0,0,0,0.10);
    -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(1.1);
    backdrop-filter: blur(var(--glass-blur)) saturate(1.1);
}
.mode-switch-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 20px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--muted-text);
    font: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: background-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
}
.mode-switch-btn i { font-size: 15px; }
.mode-switch-btn:hover { color: var(--text-color); }
.mode-switch-btn.active {
    background: var(--surface-solid);
    color: var(--text-color);
    box-shadow: inset 0 -2px 0 var(--primary-color), 0 8px 22px rgba(0,0,0,0.14);
}
.app-main[hidden] { display: none !important; }
.map-view { width: 100%; min-width: 0; box-sizing: border-box; text-align: left; }
.map-view[hidden] { display: none !important; }
.map-workspace {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 14px;
    height: min(72vh, 760px);
    min-height: 520px;
    box-sizing: border-box;
}
.map-stage {
    position: relative;
    min-width: 0;
    overflow: hidden;
    border-radius: 20px;
    border: 1px solid var(--border-color);
    background: radial-gradient(circle at 30% 20%, #16232e, #0b1117 70%);
    box-shadow: 0 24px 60px rgba(0,0,0,0.34), inset 0 0 0 1px rgba(255,255,255,0.04);
}
#ma-map-canvas { position: absolute; inset: 0; width: 100%; height: 100%; background: #0b1117; }
#ma-map-canvas.leaflet-container { font-family: inherit; background: #0b1117; }
.map-tiles { filter: saturate(0.92) contrast(1.04); }
.map-stage .leaflet-tile-pane { animation: mapTilesIn 0.45s ease both; }
@keyframes mapTilesIn { from { opacity: 0; } to { opacity: 1; } }
.map-hud {
    position: absolute;
    z-index: 500;
    top: 14px;
    left: 14px;
    right: 14px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    pointer-events: none;
}
.map-hud h3 {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    border-radius: 999px;
    background: rgba(11,17,23,0.66);
    color: #e6f2ff;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid rgba(255,255,255,0.12);
    box-shadow: 0 10px 30px rgba(0,0,0,0.35);
    -webkit-backdrop-filter: blur(14px);
    backdrop-filter: blur(14px);
}
.map-hud h3 i { color: #38bdf8; }
.map-hud p {
    margin: 0;
    white-space: nowrap;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 8px 12px;
    border-radius: 12px;
    max-width: 100%;
    background: rgba(11,17,23,0.62);
    color: #cfe6ff;
    font-size: 12px;
    line-height: 1.4;
    border: 1px solid rgba(255,255,255,0.1);
    -webkit-backdrop-filter: blur(14px);
    backdrop-filter: blur(14px);
}
.map-hud #ma-map-error { color: #fecaca; border-color: rgba(239,68,68,0.42); }
.map-hud #ma-map-live {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #d7f9e5;
    border-color: rgba(34,197,94,0.36);
}
.map-hud #ma-map-live[hidden] { display: none; }
.map-hud #ma-map-live.is-stale { color: #fde9c8; border-color: rgba(245,158,11,0.4); }
.map-live-pulse {
    width: 8px; height: 8px; border-radius: 50%; flex: 0 0 auto;
    background: #22c55e;
    animation: map-live-pulse 1.9s ease-out infinite;
}
#ma-map-live.is-stale .map-live-pulse { background: #f59e0b; animation: none; }
@keyframes map-live-pulse {
    0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.62); }
    70% { box-shadow: 0 0 0 9px rgba(34,197,94,0); }
    100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
}
.map-live-marker { position: relative; display: flex; align-items: center; justify-content: center; }
.map-live-marker .map-live-ring {
    position: absolute; inset: 0; border-radius: 50%;
    background: rgba(56,189,248,0.25);
}
.map-live-marker.is-live .map-live-ring { animation: map-live-halo 2.1s ease-out infinite; }
.map-live-marker .map-live-dot {
    position: relative; display: flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%;
    background: #0ea5e9; border: 2px solid #fff; color: #fff;
    font-size: 10px; line-height: 1;
    box-shadow: 0 2px 8px rgba(0,0,0,0.45);
}
.map-live-marker .map-live-core { width: 8px; height: 8px; border-radius: 50%; background: #fff; }
@keyframes map-live-halo {
    0% { transform: scale(0.62); opacity: 0.85; }
    70% { transform: scale(1.7); opacity: 0; }
    100% { transform: scale(1.7); opacity: 0; }
}
.map-vignette {
    position: absolute;
    inset: 0;
    z-index: 450;
    pointer-events: none;
    box-shadow: inset 0 0 120px 30px rgba(0,0,0,0.5);
    background: linear-gradient(180deg, rgba(11,17,23,0.28), transparent 22%, transparent 74%, rgba(11,17,23,0.4));
}
.map-sidebar {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
    padding: 16px;
    box-sizing: border-box;
    border-radius: 20px;
    border: 1px solid var(--border-color);
    background: linear-gradient(160deg, var(--surface-solid), color-mix(in srgb, var(--surface-solid) 80%, transparent));
    box-shadow: var(--shadow-soft);
    overflow: auto;
}
.map-sidebar-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.map-sidebar-title { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.map-sidebar-kicker { font-size: 10px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted-text); }
.map-sidebar-title strong { font-size: 15px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.map-reset-btn {
    flex: 0 0 auto;
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    background: var(--group-block-bg);
    color: var(--muted-text);
    cursor: pointer;
    transition: color 0.16s ease, border-color 0.16s ease, background-color 0.16s ease;
}
.map-reset-btn:hover {
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
}
.map-panel { display: flex; flex-direction: column; gap: 8px; padding-top: 12px; border-top: 1px solid var(--border-color); }
.map-panel-title { font-size: 11px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted-text); }
.map-date-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.map-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; font-size: 11px; font-weight: 600; color: var(--muted-text); }
.map-field input,
.map-select {
    width: 100%;
    box-sizing: border-box;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    background: var(--group-block-bg);
    color: var(--text-color);
    font: inherit;
    font-size: 12px;
    font-weight: 600;
}
.map-field input[type="date"] {
    color-scheme: inherit;
}
.map-field input[type="date"]::-webkit-calendar-picker-indicator,
.map-field input[type="date"]::-webkit-inner-spin-button {
    opacity: 0.75;
    cursor: pointer;
}
<?php if ($theme === 'dark'): ?>
.map-field input[type="date"]::-webkit-calendar-picker-indicator,
.add-steps-content input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0.92) brightness(1.6);
}
.map-sidebar option,
.add-steps-content option {
    background: var(--surface-solid);
    color: var(--text-color);
}
<?php endif; ?>
.map-field input:focus,
.map-select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent);
}
.map-layers { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.map-switch {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    padding: 7px 9px;
    border-radius: 10px;
    border: 1px solid transparent;
    background: var(--group-block-bg);
    color: var(--text-color);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.16s ease, background-color 0.16s ease;
}
.map-switch:hover { border-color: color-mix(in srgb, var(--primary-color) 32%, transparent); }
.map-switch input { position: absolute; opacity: 0; pointer-events: none; }
.map-switch-track {
    position: relative;
    flex: 0 0 auto;
    width: 30px;
    height: 17px;
    border-radius: 999px;
    background: rgba(127,127,127,0.32);
    transition: background-color 0.18s ease;
}
.map-switch-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    transition: transform 0.18s ease;
}
.map-switch input:checked + .map-switch-track { background: var(--primary-color); }
.map-switch input:checked + .map-switch-track .map-switch-thumb { transform: translateX(13px); }
.map-switch input:focus-visible + .map-switch-track { box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent); }
.map-switch-label { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.map-slider { display: flex; flex-direction: column; gap: 6px; font-size: 12px; font-weight: 600; }
.map-slider-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.map-slider-head output { font-size: 11px; font-weight: 700; color: var(--primary-color); font-variant-numeric: tabular-nums; }
.map-slider input[type="range"] { -webkit-appearance: none; appearance: none; width: 100%; height: 18px; margin: 0; background: transparent; cursor: pointer; }
.map-slider input[type="range"]::-webkit-slider-runnable-track { height: 5px; border-radius: 999px; background: linear-gradient(90deg, var(--primary-color), color-mix(in srgb, var(--primary-color) 30%, transparent)); }
.map-slider input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; margin-top: -5px; width: 15px; height: 15px; border-radius: 50%; background: #fff; border: 2px solid var(--primary-color); box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
.map-slider input[type="range"]::-moz-range-track { height: 5px; border-radius: 999px; background: color-mix(in srgb, var(--primary-color) 60%, transparent); }
.map-slider input[type="range"]::-moz-range-thumb { width: 13px; height: 13px; border-radius: 50%; background: #fff; border: 2px solid var(--primary-color); box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
.map-apply-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: auto;
    padding: 11px 16px;
    border-radius: 12px;
    border: 1px solid color-mix(in srgb, var(--primary-color) 70%, #000);
    background: var(--primary-color);
    color: #fff;
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 14px 30px color-mix(in srgb, var(--primary-color) 34%, transparent);
    transition: transform 0.16s ease, filter 0.16s ease;
}
.map-apply-btn:hover { filter: brightness(1.06); transform: translateY(-1px); }
.map-stage .leaflet-control-zoom { border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 12px 30px rgba(0,0,0,0.42); }
.map-stage .leaflet-control-zoom a { width: 34px; height: 34px; line-height: 34px; background: rgba(11,17,23,0.78); color: #dbeafe; border-bottom: 1px solid rgba(255,255,255,0.1); }
.map-stage .leaflet-control-zoom a:hover { background: rgba(56,189,248,0.24); color: #fff; }
.map-stage .map-locate-control { margin-bottom: 10px; border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 12px 30px rgba(0,0,0,0.42); }
.map-stage .map-locate-control a.map-locate-btn {
    width: 34px; height: 34px; line-height: 34px; display: flex; align-items: center; justify-content: center;
    background: rgba(11,17,23,0.78); color: #7dd3fc; font-size: 14px;
}
.map-stage .map-locate-control a.map-locate-btn:hover { background: rgba(56,189,248,0.24); color: #fff; }
.map-stage .leaflet-bottom.leaflet-left { margin-bottom: 8px; }
.map-stage .leaflet-bottom .leaflet-control-scale { margin: 0 0 36px 14px; }
.map-stage .leaflet-control-scale-line { background: rgba(11,17,23,0.6); color: #cbd5e1; border-color: rgba(255,255,255,0.4); border-radius: 6px; padding: 2px 7px; }
.map-stage .leaflet-control-attribution { background: rgba(11,17,23,0.55) !important; color: #94a3b8 !important; font-size: 9px !important; border-radius: 6px; margin: 0 0 4px 8px !important; }
.map-stage .leaflet-control-attribution a { color: #7dd3fc !important; }
.map-stage .leaflet-popup-content-wrapper { background: rgba(13,20,27,0.94); color: #e2e8f0; border-radius: 12px; border: 1px solid rgba(148,163,184,0.28); box-shadow: 0 18px 40px rgba(0,0,0,0.5); }
.map-stage .leaflet-popup-content { margin: 10px 12px; font-size: 12px; }
.map-stage .leaflet-popup-tip { background: rgba(13,20,27,0.94); }
.map-stage .leaflet-container a.leaflet-popup-close-button { color: #94a3b8; }
.map-stage .map-dot { transition: fill-opacity 0.16s ease, stroke-width 0.16s ease; }
.map-stage .map-dot:hover { stroke-width: 2.5; }
.map-cluster {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: radial-gradient(circle at 35% 30%, #7dd3fc, #0ea5e9 60%, #0369a1);
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    border: 2px solid rgba(255,255,255,0.75);
    box-shadow: 0 0 0 6px rgba(14,165,233,0.18), 0 10px 24px rgba(0,0,0,0.45);
}
.map-workspace { transition: grid-template-columns 0.25s ease; }
.map-workspace.is-collapsed { grid-template-columns: minmax(0, 1fr) 0; gap: 0; }
.map-workspace.is-collapsed .map-sidebar { display: none; }
.map-sidebar-backdrop { display: none; }
body.map-sidebar-open { overflow: hidden !important; }
.map-sidebar-show {
    position: absolute; top: 12px; right: 12px; z-index: 1000;
    width: 40px; height: 40px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.18);
    background: rgba(11,17,23,0.78); color: #dbeafe; cursor: pointer; font-size: 16px;
}
.map-sidebar-show[hidden] { display: none; }
.map-sidebar-show:hover { background: rgba(56,189,248,0.28); }
@media (max-width: 1024px) {
    .map-workspace { grid-template-columns: minmax(0, 1fr) 286px; height: min(76vh, 720px); }
    .map-workspace.is-collapsed { grid-template-columns: minmax(0, 1fr) 0; }
}
@media (max-width: 820px) {
    .map-workspace { grid-template-columns: 1fr; height: auto; min-height: 0; }
    .map-workspace.is-collapsed { grid-template-columns: 1fr; }
    .map-stage { min-height: 58vh; }
    .map-workspace.is-collapsed .map-stage { min-height: 76vh; }
    /* On phones the controls panel is a modal sheet over the map, not a block
       stacked below it. */
    .map-sidebar {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        top: auto;
        z-index: 1400;
        max-height: min(86vh, 760px);
        padding: 16px 16px calc(16px + env(safe-area-inset-bottom, 0px));
        border-radius: 20px 20px 0 0;
        box-shadow: 0 -24px 60px rgba(0,0,0,0.45);
        overscroll-behavior: contain;
    }
    .map-workspace:not(.is-collapsed) .map-sidebar-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        z-index: 1350;
        background: rgba(8,12,16,0.55);
        -webkit-backdrop-filter: blur(3px);
        backdrop-filter: blur(3px);
    }
    .map-sidebar-head #ma-sidebar-hide i { transform: rotate(90deg); }
    .map-sidebar-show { width: 44px; height: 44px; }
}

/* --- Custom year/month dropdowns --- */
.calendar-selector-box { align-items: stretch; gap: 10px; }
.custom-select { position: relative; flex: 1 1 0; min-width: 0; }
.calendar-selector-box select.custom-select-native {
    position: absolute !important;
    top: 0;
    left: 0;
    width: 1px !important;
    height: 1px !important;
    min-width: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    opacity: 0 !important;
    pointer-events: none !important;
    overflow: hidden;
    clip: rect(0 0 0 0);
}
.custom-select-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    background: var(--surface-solid);
    color: var(--text-color);
    font: inherit;
    font-size: 14px;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
}
.custom-select-trigger:hover { border-color: color-mix(in srgb, var(--primary-color) 45%, var(--border-color)); }
.custom-select-trigger:focus-visible {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent);
}
.custom-select-trigger:disabled { opacity: 0.5; cursor: not-allowed; }
.custom-select-value { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-select-caret { font-size: 11px; color: var(--muted-text); transition: transform 0.2s ease; }
.custom-select.is-open .custom-select-caret { transform: rotate(180deg); }
.custom-select.is-open .custom-select-trigger {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent);
}
.custom-select-menu {
    position: absolute;
    z-index: 70;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    display: none;
    padding: 8px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    background: color-mix(in srgb, var(--surface-solid) 94%, var(--modal-bg));
    box-shadow: var(--shadow-soft);
    max-height: 280px;
    overflow: auto;
    -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(1.1);
    backdrop-filter: blur(var(--glass-blur)) saturate(1.1);
}
.custom-select.is-open .custom-select-menu { display: block; }
.custom-select-header {
    padding: 4px 6px 8px;
    margin-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
    color: var(--muted-text);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.custom-select-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(78px, 1fr)); gap: 6px; }
.custom-select-option {
    padding: 9px 8px;
    border: 1px solid transparent;
    border-radius: 10px;
    background: rgba(127,127,127,0.07);
    color: var(--text-color);
    font: inherit;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    transition: background-color 0.16s ease, color 0.16s ease, border-color 0.16s ease;
}
.custom-select-option:hover {
    background: color-mix(in srgb, var(--primary-color) 14%, transparent);
    border-color: color-mix(in srgb, var(--primary-color) 30%, transparent);
}
.custom-select-option.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: #fff;
    box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 34%, transparent);
}

/* --- Minimalist day steps editor --- */
#day-steps-modal {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(10,14,18,0.42);
    -webkit-backdrop-filter: blur(14px);
    backdrop-filter: blur(14px);
    box-sizing: border-box;
}
#day-steps-modal .day-steps-content {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: min(320px, 100%);
    padding: 22px;
    border: 1px solid var(--border-color);
    border-radius: 20px;
    background: color-mix(in srgb, var(--modal-bg) 92%, var(--surface-solid));
    box-shadow: var(--shadow-soft);
    animation: alertPopIn 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.day-steps-title {
    text-align: center;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted-text);
}
.day-steps-date {
    text-align: center;
    font-size: 15px;
    font-weight: 700;
    color: var(--text-color);
}
#day-steps-input {
    width: 100%;
    box-sizing: border-box;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    background: var(--surface-solid);
    color: var(--text-color);
    font: inherit;
    font-size: 32px;
    font-weight: 800;
    letter-spacing: 0.02em;
    text-align: center;
}
#day-steps-input::placeholder { color: color-mix(in srgb, var(--muted-text) 55%, transparent); font-weight: 800; }
#day-steps-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent);
}
.day-steps-actions { display: flex; gap: 8px; }
.day-steps-actions button {
    flex: 1 1 0;
    padding: 11px 14px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--group-block-bg);
    color: var(--text-color);
    font: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: filter 0.16s ease, transform 0.16s ease;
}
.day-steps-actions button:hover { filter: brightness(1.05); }
.day-steps-actions .day-steps-save { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }

/* --- Map settings tab, day navigation and custom date pickers --- */
#settings-modal .settings-content {
    width: min(780px, calc(100vw - 40px));
    height: auto;
    aspect-ratio: 16 / 10;
    max-height: calc(100dvh - 40px);
    padding: 22px 24px 20px;
    overflow: hidden;
}
#settings-modal .settings-content > h2 { margin-bottom: 14px; }
.settings-tab { padding: 10px 8px; font-size: 13px; gap: 7px; white-space: nowrap; }
.settings-map-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; text-align: left; padding-bottom: 4px; }
.settings-map-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    background: var(--group-block-bg);
}
.settings-map-section h3 { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 14px; font-weight: 800; }
.settings-map-section h3 i { color: var(--primary-color); font-size: 14px; }
.settings-map-hint { margin: -4px 0 0; font-size: 12px; line-height: 1.45; color: var(--muted-text); }
.settings-map-section .map-slider { font-size: 13px; }
.settings-map-actions { display: flex; justify-content: flex-end; margin-top: auto; padding-top: 4px; }
.settings-map-reset {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 14px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--surface-solid);
    color: var(--text-color);
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: color 0.16s ease, border-color 0.16s ease, background-color 0.16s ease;
}
.settings-map-reset:hover {
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
    background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-solid));
}
.map-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.map-day-nav { display: flex; gap: 4px; }
.map-day-btn {
    flex: 0 0 auto;
    width: 26px;
    height: 26px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--group-block-bg);
    color: var(--muted-text);
    font-size: 10px;
    cursor: pointer;
    transition: color 0.16s ease, border-color 0.16s ease, background-color 0.16s ease;
}
.map-day-btn:hover {
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
}
.map-field > span { font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.dp-field { position: relative; display: flex; }
.dp-native {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: 0;
    padding: 0;
    border: 0;
    opacity: 0;
    pointer-events: none;
}
.dp-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--group-block-bg);
    color: var(--text-color);
    font: inherit;
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
    transition: border-color 0.16s ease, box-shadow 0.16s ease;
}
.dp-trigger:hover { border-color: color-mix(in srgb, var(--primary-color) 45%, var(--border-color)); }
.dp-trigger[aria-expanded="true"] {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent);
}
.dp-value { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dp-icon { flex: 0 0 auto; font-size: 12px; color: var(--primary-color); }
.dp-popover {
    position: fixed;
    z-index: 10050;
    box-sizing: border-box;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    background: var(--surface-solid);
    box-shadow: var(--shadow-soft);
}
.dp-popover[hidden] { display: none !important; }
.dp-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 8px; }
.dp-title { font-size: 12px; font-weight: 800; text-transform: capitalize; }
.dp-nav {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--group-block-bg);
    color: var(--text-color);
    font-size: 10px;
    cursor: pointer;
}
.dp-nav:hover { color: var(--primary-color); border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color)); }
.dp-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    margin-bottom: 4px;
    color: var(--muted-text);
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    text-align: center;
}
.dp-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.dp-day {
    aspect-ratio: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid transparent;
    border-radius: 8px;
    background: transparent;
    color: var(--text-color);
    font: inherit;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}
.dp-day:hover { background: color-mix(in srgb, var(--primary-color) 16%, transparent); }
.dp-day.is-today { border-color: color-mix(in srgb, var(--primary-color) 55%, transparent); }
.dp-day.is-selected { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }
.dp-empty { aspect-ratio: 1; }
.dp-foot { display: flex; justify-content: center; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border-color); }
.dp-today {
    padding: 3px 6px;
    border: 0;
    background: transparent;
    color: var(--primary-color);
    font: inherit;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
}
.custom-select-menu { background: var(--surface-solid); }
.custom-select-option { background: color-mix(in srgb, var(--text-color) 6%, var(--surface-solid)); }
@media (max-width: 700px) {
    .settings-map-grid { grid-template-columns: 1fr; }
    #settings-modal .settings-content { border-radius: 0; }
}
@media (max-width: 560px) {
    .settings-tab { font-size: 0; gap: 0; padding: 12px 6px; }
    .settings-tab i { font-size: 15px; }
    .settings-map-section { padding: 14px; }
}
.data-type-icon {
    width: clamp(28px, 4vh, 40px);
    height: clamp(28px, 4vh, 40px);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--metric-color, var(--primary-color));
    color: #fff;
    box-shadow: 0 8px 14px rgba(0, 0, 0, 0.16);
    transition: transform 0.18s ease;
}
.data-type-btn .data-type-icon i,
.data-type-btn:hover .data-type-icon i,
.data-type-btn.active .data-type-icon i {
    font-size: clamp(13px, 1.9vh, 18px);
    line-height: 1;
    color: #fff;
}
.data-type-btn:hover .data-type-icon,
.data-type-btn.active .data-type-icon { transform: scale(1.08); }
.trip-list { display: flex; flex-direction: column; gap: 6px; max-height: 220px; overflow: auto; padding-right: 2px; }
.trip-empty { padding: 6px 2px; font-size: 12px; color: var(--muted-text); }
.trip-item {
    display: grid;
    grid-template-columns: 16px minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
    padding: 7px 8px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--group-block-bg);
}
.trip-item.is-editing { border-color: color-mix(in srgb, var(--primary-color) 55%, var(--border-color)); }
.trip-pick { width: 14px; height: 14px; margin: 0; accent-color: var(--primary-color); cursor: pointer; }
.trip-open {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--text-color);
    font: inherit;
    text-align: left;
    cursor: pointer;
}
.trip-title { font-size: 12px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.trip-meta { font-size: 10px; color: var(--muted-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.trip-actions { display: flex; gap: 2px; }
.trip-action {
    width: 26px;
    height: 26px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid transparent;
    border-radius: 7px;
    background: transparent;
    color: var(--muted-text);
    font-size: 11px;
    cursor: pointer;
}
.trip-action:hover {
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 35%, var(--border-color));
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
}
.trip-editor {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    background: var(--group-block-bg);
}
.trip-editor[hidden] { display: none !important; }
.trip-editor-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.trip-editor-head strong { font-size: 12px; }
.trip-close {
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid var(--border-color);
    border-radius: 7px;
    background: var(--surface-solid);
    color: var(--muted-text);
    font-size: 10px;
    cursor: pointer;
}
.trip-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--muted-text);
}
.trip-field input,
.trip-field textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 7px 8px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--surface-solid);
    color: var(--text-color);
    font: inherit;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0;
    text-transform: none;
}
.trip-field textarea { min-height: 44px; resize: vertical; }
.map-ctx-menu { position: absolute; z-index: 1200; min-width: 200px; display: flex; flex-direction: column; padding: 4px; border-radius: 10px; background: var(--card-bg, #1f2937); border: 1px solid var(--border-color, #374151); box-shadow: 0 8px 24px rgba(0,0,0,.35); }
.map-ctx-menu button { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 0; border-radius: 6px; background: transparent; color: inherit; font: inherit; font-size: 13px; text-align: left; cursor: pointer; }
.map-ctx-menu button:hover, .map-ctx-menu button:focus-visible { background: color-mix(in srgb, var(--primary-color) 18%, transparent); outline: none; }
.map-ctx-head { padding: 4px 10px; font-size: 11px; opacity: .65; font-variant-numeric: tabular-nums; }
.visit-chip { display: inline-block; padding: 1px 8px; border-radius: 999px; color: #fff; font-size: 10px; font-weight: 600; white-space: nowrap; }
.visit-pop-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.visit-modal { position: fixed; inset: 0; z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(0,0,0,.5); }
.visit-modal-box { width: min(440px, 100%); max-height: 90vh; overflow: auto; border-radius: 14px; background: var(--card-bg, #1f2937); color: var(--text-color, inherit); border: 1px solid var(--border-color, #374151); padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.visit-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.visit-modal-body { display: flex; flex-direction: column; gap: 10px; }
.visit-meta, .visit-sub, .visit-msg { font-size: 12px; opacity: .8; }
.visit-msg:empty { display: none; }
.visit-cands { display: flex; flex-direction: column; gap: 6px; }
.visit-cand { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-color, #374151); background: transparent; color: inherit; font: inherit; text-align: left; cursor: pointer; }
.visit-cand span { font-size: 11px; opacity: .75; }
.visit-cand:hover, .visit-cand.is-selected { border-color: var(--primary-color); }
.visit-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.visit-row { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; border: 1px solid var(--border-color, #374151); background: transparent; color: inherit; font: inherit; text-align: left; cursor: pointer; }
.visit-row:hover { border-color: var(--primary-color); }
.visit-row-text { display: flex; flex-direction: column; min-width: 0; }
.visit-row-text strong, .visit-row-text small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.visit-row-text small { font-size: 11px; opacity: .7; }
.visit-dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 auto; }
.trip-field input:focus,
.trip-field textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent);
}
.trip-dates { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.trip-visible { display: flex; align-items: center; gap: 7px; font-size: 12px; color: var(--text-color); }
.trip-visible input { width: 14px; height: 14px; margin: 0; accent-color: var(--primary-color); }
.trip-editor-actions { display: flex; justify-content: space-between; gap: 6px; }
.trip-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 7px 9px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--surface-solid);
    color: var(--text-color);
    font: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}
.trip-btn:hover { color: var(--primary-color); border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color)); }
.trip-btn-primary { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }
.trip-btn-primary:hover { color: #fff; filter: brightness(1.06); }

select.custom-select-native,
#settings-modal select:not(.custom-select-native), #share-location-modal select:not(.custom-select-native) {
    position: absolute !important; top: 0; left: 0; width: 1px !important; height: 1px !important;
    min-width: 0 !important; margin: 0 !important; padding: 0 !important; border: 0 !important;
    opacity: 0 !important; pointer-events: none !important; overflow: hidden;
}
.custom-select-grid { grid-template-columns: 1fr; }
.custom-select-option { width: 100%; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#settings-panel-profile .settings-tile { align-items: stretch; min-width: 0; }
#settings-panel-profile .tile-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; margin-bottom: 0; }
#settings-panel-profile .tile-content { display: flex; flex-wrap: nowrap; align-items: center; gap: 10px; }
#settings-panel-profile .tile-content > * { flex: 0 0 auto; }
#settings-panel-profile .tile-content input,
#settings-panel-profile .tile-content .dp-field { flex: 1 1 auto; min-width: 0; }
#settings-panel-profile .tile-content .tile-unit {
    min-width: 44px;
    height: 32px;
    padding: 0 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: color-mix(in srgb, var(--text-color) 6%, var(--surface-solid));
    border: 1px solid var(--border-color);
    color: var(--muted-text);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-align: center;
    box-sizing: border-box;
}

/* --- Settings: fullscreen, right-hand vertical menu, unified controls --- */
#settings-modal { padding: 0; align-items: stretch; justify-content: stretch; }
#settings-modal .settings-content {
    width: 100vw; max-width: none; height: 100dvh; max-height: none; aspect-ratio: auto;
    margin: 0; border-radius: 0; padding: 20px 28px;
    display: grid; grid-template-columns: minmax(0, 1fr) 232px; grid-template-rows: auto minmax(0, 1fr);
    column-gap: 24px; overflow: hidden; text-align: left;
}
#settings-modal .settings-content > h2 { grid-column: 1 / -1; grid-row: 1; margin: 0 48px 14px 0; }
#settings-modal .settings-content > .modal-close { position: static; }
#close-settings { transition: transform 0.3s ease, background 0.18s ease, color 0.18s ease, border-color 0.18s ease; }
#close-settings:hover { transform: rotate(180deg); }
@media (prefers-reduced-motion: reduce) { #close-settings { transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease; } #close-settings:hover { transform: none; } }
#settings-modal .settings-tab-panel { grid-column: 1; grid-row: 2; min-height: 0; padding: 0 6px 24px 0; }
#settings-modal .settings-tab-bar {
    grid-column: 2; grid-row: 2; order: 0; flex-direction: column; align-self: start;
    margin: 0; padding: 8px; gap: 4px; overflow-y: auto; max-height: 100%;
    border-radius: var(--radius-lg, 16px);
}
#settings-modal .settings-tab {
    flex: 0 0 auto; width: 100%; justify-content: flex-start; padding: 12px 14px; font-size: 14px; gap: 12px; text-align: left;
}
#settings-modal .settings-tab i { width: 18px; text-align: center; }
#settings-modal .settings-map-grid, #settings-modal .location-panel, #settings-modal .settings-chart-preview-grid { max-width: 1100px; }
#settings-modal .settings-field { display: flex; flex-direction: column; gap: 6px; font-size: 12px; font-weight: 700; color: var(--muted-text); margin-bottom: 10px; }
#settings-modal .settings-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 14px; }
#settings-modal .settings-actions-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
#settings-modal .custom-select { flex: 1 1 auto; width: 100%; }
#settings-modal .settings-content input[type="text"],
#settings-modal .settings-content input[type="email"],
#settings-modal .settings-content input[type="password"],
#settings-modal .settings-content input[type="url"],
#settings-modal .settings-content input[type="number"],
#settings-modal .settings-content input[type="date"],
#settings-modal .settings-content input[type="datetime-local"],
#settings-modal .settings-content textarea {
    box-sizing: border-box; width: 100%; min-height: 40px; padding: 9px 12px;
    border: 1px solid var(--border-color); border-radius: var(--radius-md, 12px);
    background: var(--surface-solid); color: var(--text-color); font: inherit; font-size: 14px;
}
#settings-modal .settings-content input:focus-visible,
#settings-modal .settings-content textarea:focus-visible {
    outline: none; border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent);
}
#settings-modal .location-section, #settings-modal .settings-map-section {
    border: 1px solid var(--border-color); border-radius: var(--radius-lg, 16px);
    background: var(--surface-solid); padding: 16px;
}
#settings-modal .flow-step {
    display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; margin-right: 6px;
    border-radius: 50%; background: var(--primary-color); color: #fff; font-size: 12px; font-weight: 800;
}
#settings-modal .render-progress { height: 8px; border-radius: 99px; background: var(--border-color); overflow: hidden; margin-bottom: 8px; }
#settings-modal .render-progress span { display: block; height: 100%; width: 0; background: var(--primary-color); transition: width 0.3s ease; }
/* Render wizard: a compact road map, one stage at a time. */
#settings-modal .render-stepper {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 14px;
    padding: 6px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md, 12px);
    background: color-mix(in srgb, var(--primary-color) 5%, var(--group-block-bg));
}
#settings-modal .render-step {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid transparent;
    border-radius: var(--radius-sm, 7px);
    background: transparent;
    color: var(--muted-text);
    font-size: 11px;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
    transition: background .16s ease, color .16s ease, border-color .16s ease;
}
#settings-modal .render-step:hover { background: color-mix(in srgb, var(--primary-color) 9%, transparent); color: var(--text-color); }
#settings-modal .render-step.is-active {
    background: var(--surface-solid);
    border-color: color-mix(in srgb, var(--primary-color) 34%, var(--border-color));
    color: var(--text-color);
    box-shadow: 0 4px 14px color-mix(in srgb, var(--primary-color) 12%, transparent);
}
#settings-modal .render-step-index {
    display: inline-grid;
    place-items: center;
    flex: 0 0 auto;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: color-mix(in srgb, var(--muted-text) 18%, transparent);
    color: var(--text-color);
    font-size: 11px;
    font-weight: 800;
}
#settings-modal .render-step.is-active .render-step-index { background: var(--primary-color); color: #fff; }
#settings-modal .render-step-label { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#settings-modal .render-step-panel { display: none; }
#settings-modal .render-step-panel.is-active { display: flex; }
#settings-modal .render-step-nav { display: flex; gap: 8px; justify-content: flex-end; margin-top: 4px; }
#settings-modal .render-step-nav .render-back { margin-right: auto; }
@media (max-width: 640px) {
    #settings-modal .render-step-label { display: none; }
    #settings-modal .render-step { justify-content: center; }
}
#settings-modal [hidden] { display: none !important; }
@media (max-width: 820px) {
    #settings-modal .settings-content { padding: 14px 12px; grid-template-columns: minmax(0, 1fr) 56px; column-gap: 10px; }
    #settings-modal .settings-tab { justify-content: center; padding: 12px 0; font-size: 0; gap: 0; }
    #settings-modal .settings-tab span { display: none; }
    #settings-modal .settings-tab i { font-size: 17px; width: auto; }
    #settings-modal .settings-tab-bar { padding: 5px; }
    #settings-modal .settings-map-grid, #settings-modal .settings-grid-2 { grid-template-columns: 1fr; }
}
/* --- Share chooser, location share modal, render cards, points list, area tools --- */
.app-topbar-actions { position: relative; }
.share-chooser {
    position: absolute; top: calc(100% + 8px); right: 0; z-index: 10010; min-width: 220px; padding: 6px;
    border: 1px solid var(--border-color); border-radius: var(--radius-lg, 16px);
    background: var(--surface-solid); box-shadow: var(--shadow-soft); display: flex; flex-direction: column; gap: 4px;
}
.share-chooser[hidden] { display: none; }
.share-chooser button {
    display: flex; align-items: center; gap: 10px; width: 100%; height: auto; padding: 11px 12px; border: none; border-radius: 10px;
    background: transparent; color: var(--text-color); font: inherit; font-size: 14px; text-align: left; cursor: pointer;
}
.share-chooser button:hover { background: color-mix(in srgb, var(--primary-color) 14%, transparent); color: var(--primary-color); }
#share-location-modal {
    position: fixed; inset: 0; z-index: 10000; display: none; align-items: center; justify-content: center; padding: 16px;
    background: rgba(0,0,0,0.42); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); box-sizing: border-box;
}
#share-location-modal .share-location-content {
    position: relative; width: min(760px, 100%); max-height: 100%; overflow: auto; padding: 22px; box-sizing: border-box;
    border: 1px solid var(--border-color); border-radius: var(--radius-lg, 16px); background: var(--modal-bg); text-align: left;
}
#share-location-modal .modal-close { position: static; }
#share-location-modal .custom-select { width: 100%; flex: 1 1 auto; }
#share-location-modal input[type="text"], #share-location-modal input[type="email"], #share-location-modal input[type="number"],
#share-location-modal input[type="date"], #share-location-modal input[type="datetime-local"] {
    box-sizing: border-box; width: 100%; min-height: 40px; padding: 9px 12px; margin-bottom: 8px; border: 1px solid var(--border-color);
    border-radius: var(--radius-md, 12px); background: var(--surface-solid); color: var(--text-color); font: inherit; font-size: 14px;
}
.render-cards { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
.render-card { display: flex; flex-direction: column; gap: 8px; padding: 12px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-md, 12px); background: var(--group-block-bg); }
.render-card-head { display: flex; align-items: center; gap: 10px; min-width: 0; }
.render-card-head strong { flex: 1 1 auto; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.render-badge { padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 800; text-transform: uppercase; background: var(--border-color); color: var(--muted-text); }
.render-card.is-ready .render-badge { background: color-mix(in srgb, #22c55e 22%, transparent); color: #22c55e; }
.render-card.is-processing .render-badge, .render-card.is-pending .render-badge { background: color-mix(in srgb, var(--primary-color) 22%, transparent); color: var(--primary-color); }
.render-card.is-failed .render-badge, .render-card.is-cancelled .render-badge { background: color-mix(in srgb, #ef4444 22%, transparent); color: #ef4444; }
.render-card-meta { font-size: 12px; color: var(--muted-text); }
.render-card-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.render-card-actions .excel-btn { text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.render-card-actions .render-delete { margin-left: auto; color: #dc2626; border-color: color-mix(in srgb, #dc2626 40%, transparent); }
.render-card-actions .render-delete:hover { background: color-mix(in srgb, #dc2626 12%, transparent); }
.render-preview { margin: 4px 0 8px; border: 1px solid var(--border-color); border-radius: var(--radius-md, 12px); overflow: hidden; background: #000; }
.render-preview[hidden] { display: none; }
.render-preview-media { display: block; width: 100%; max-height: 60vh; object-fit: contain; border: 0; background: #000; }
iframe.render-preview-media { height: 60vh; }
        input[type="checkbox"]:not(.map-switch input):not(.dp-native) { -webkit-appearance: none; appearance: none; position: relative; display: inline-grid; place-content: center; flex: 0 0 auto; width: 18px !important; height: 18px !important; min-height: 0 !important; margin: 0; padding: 0 !important; border: 1.5px solid color-mix(in srgb, var(--text-color) 35%, var(--border-color)); border-radius: 5px; background: transparent; cursor: pointer; transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease, transform .12s ease; }
        input[type="checkbox"]:not(.map-switch input):not(.dp-native)::after { content: ''; width: 5px; height: 10px; margin-top: -2px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg) scale(0); transform-origin: center; transition: transform .2s cubic-bezier(.2, .9, .3, 1.5); }
        input[type="checkbox"]:not(.map-switch input):not(.dp-native):hover { border-color: var(--primary-color); }
        input[type="checkbox"]:not(.map-switch input):not(.dp-native):active { transform: scale(.9); }
        input[type="checkbox"]:not(.map-switch input):not(.dp-native):checked { background: var(--primary-color); border-color: var(--primary-color); }
        input[type="checkbox"]:not(.map-switch input):not(.dp-native):checked::after { transform: rotate(45deg) scale(1); }
        input[type="checkbox"]:not(.map-switch input):not(.dp-native):focus-visible { outline: none; box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 28%, transparent); }
        input[type="checkbox"]:not(.map-switch input):not(.dp-native):disabled { opacity: .45; cursor: not-allowed; }
        .pager { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 8px 0; }
        .pager-pages { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
        .pager-page { min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid var(--border-color); border-radius: 8px; background: transparent; color: var(--text-color); cursor: pointer; transition: background-color .15s, border-color .15s; }
        .pager-page:hover { border-color: var(--primary-color); }
        .pager-page.is-current { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }
        .pager-gap { opacity: .6; padding: 0 2px; }
        .pager select { width: auto; height: 32px; }
        .place-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
        .place-chip { padding: 5px 12px; border: 1px solid var(--border-color); border-radius: 999px; background: transparent; color: var(--text-color); font-size: 12px; cursor: pointer; text-transform: capitalize; transition: background-color .15s, border-color .15s; }
        .place-chip:hover { border-color: var(--primary-color); }
        .place-chip.is-active { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }
        .picker-overlay { position: fixed; inset: 0; z-index: 100001; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,.6); padding: 12px; }
        .picker-box { width: min(920px, 100%); max-height: 100%; display: flex; flex-direction: column; border: 1px solid var(--border-color); border-radius: 14px; background: var(--card-bg, var(--bg-color)); color: var(--text-color); overflow: hidden; }
        .picker-head, .picker-foot { display: flex; align-items: center; gap: 10px; padding: 10px 14px; }
        .picker-head { justify-content: space-between; }
        .picker-foot .picker-coords { flex: 1; font-variant-numeric: tabular-nums; font-size: 13px; }
        .picker-x { border: 0; background: transparent; color: inherit; font-size: 18px; cursor: pointer; }
        .picker-map { height: min(64vh, 560px); width: 100%; }
        .picker-tip { background: rgba(0,0,0,.7); border: 0; color: #fff; font-size: 11px; box-shadow: none; }
        .dp-time { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 8px; }
        .dp-time input { width: 56px !important; height: 32px; text-align: center; padding: 0 !important; }
        .trip-item.is-focused { border-color: #f43f5e; background: color-mix(in srgb, #f43f5e 12%, transparent); }
        .trip-item:has(.trip-pick:checked):not(.is-focused) { border-color: #facc15; }        .speed-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; margin-top: 10px; font-size: 12px; opacity: .9; }
        .speed-legend[hidden] { display: none; }
        .speed-legend span { width: 18px; height: 8px; border-radius: 4px; }
        .speed-legend em { font-style: normal; margin-right: 6px; }
        .rplayer { position: relative; background: #000; outline: none; }
        .rplayer-video { display: block; width: 100%; max-height: 60vh; object-fit: contain; background: #000; cursor: pointer; }
        .rplayer:fullscreen .rplayer-video { max-height: 100vh; height: 100%; }
        .rplayer-big { position: absolute; inset: 0 0 44px 0; margin: auto; width: 64px; height: 64px; border-radius: 50%; border: 0; background: rgba(0,0,0,.55); color: #fff; font-size: 24px; cursor: pointer; }
        .rplayer-big[hidden] { display: none; }
        .rplayer.is-loading::after { content: ''; position: absolute; top: calc(50% - 22px); left: calc(50% - 16px); width: 32px; height: 32px; border: 3px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: rplayer-spin .8s linear infinite; pointer-events: none; }
        @keyframes rplayer-spin { to { transform: rotate(360deg); } }
        .rplayer-bar { display: flex; align-items: center; gap: 8px; padding: 6px 10px; background: linear-gradient(transparent, rgba(0,0,0,.85)); color: #fff; }
        .rplayer-btn { border: 0; background: transparent; color: #fff; min-width: 28px; height: 28px; cursor: pointer; font-size: 14px; border-radius: 6px; }
        .rplayer-btn:hover { background: rgba(255,255,255,.15); }
        .rplayer-track { position: relative; flex: 1; height: 6px; border-radius: 3px; background: rgba(255,255,255,.2); cursor: pointer; touch-action: none; }
        .rplayer-buf, .rplayer-pos { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 3px; width: 0; }
        .rplayer-buf { background: rgba(255,255,255,.35); }
        .rplayer-pos { background: var(--accent-color, #60a5fa); }
        .rplayer-knob { position: absolute; top: 50%; left: 0; width: 12px; height: 12px; margin: -6px 0 0 -6px; border-radius: 50%; background: #fff; }
        .rplayer-time { font-size: 12px; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .settings-field input[type="color"] { width: 100%; height: 38px; padding: 2px 4px; cursor: pointer; }
        .settings-field input[type="range"] { width: 100%; }
/* The point list grows with its content; the settings panel scrolls instead of
   trapping the list in its own scrollbar. */
.points-list { display: flex; flex-direction: column; gap: 4px; max-height: none; overflow: visible; }
.points-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid var(--border-color); border-radius: 10px; font-size: 13px; cursor: pointer; }
.points-row:hover { background: color-mix(in srgb, var(--primary-color) 8%, transparent); }
.points-row input { flex: 0 0 auto; width: auto; min-height: 0; }
.points-row span { flex: 1 1 auto; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.points-row em { flex: 0 0 auto; font-style: normal; color: var(--muted-text); font-size: 12px; }
.map-area-tools { display: flex; align-items: center; gap: 8px; margin-top: 8px; pointer-events: auto; }
.map-area-tools [hidden] { display: none; }
/* Icon-only buttons in the map panel stay square, not stretched rectangles. */
.map-area-tools .trip-btn { width: 40px; padding: 0; }
/* Tolerance picker sits between the smooth and delete buttons, so it stays
   compact enough for the same single row on a phone. */
.map-area-tolerance { display: inline-flex; align-items: center; }
.map-area-tolerance select { width: auto; min-width: 72px; height: 40px; padding: 0 6px; font-size: 12px; border-radius: 10px; }
#ma-area-toggle.active { background: #ef4444; color: #fff; border-color: #ef4444; }
@media (max-width: 820px) {
    .points-row { flex-wrap: wrap; }
    .points-row em { flex-basis: 100%; }
    /* Touch-sized but still square icon buttons. */
    .map-day-btn { width: 34px; height: 34px; font-size: 12px; }
    .map-area-tools .trip-btn { width: 44px; }
    .map-area-tolerance select { height: 44px; }
}

/* --- Unified modal top bars and fullscreen settings --- */
.modal-content.has-modal-topbar {
    display: flex !important;
    flex-direction: column !important;
    align-items: stretch !important;
    padding: 0 !important;
    overflow: hidden !important;
}
.modal-topbar {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 58px;
    padding: 10px 16px;
    padding-top: max(10px, env(safe-area-inset-top));
    border-bottom: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--surface-solid) 94%, var(--modal-bg));
    position: relative;
    z-index: 4;
    box-sizing: border-box;
}
.modal-topbar-title {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 !important;
    padding: 0 !important;
    font-size: 18px;
    font-weight: 700;
    line-height: 1.25;
    color: var(--text-color);
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.modal-topbar-title i,
.modal-topbar-title svg {
    flex: 0 0 auto;
    color: var(--primary-color);
}
.modal-topbar .modal-close {
    position: static !important;
    inset: auto !important;
    flex: 0 0 auto;
    margin: 0 0 0 auto !important;
    z-index: 5;
}
.modal-body {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
    padding: 20px 22px;
    overflow: auto;
    overflow-x: hidden;
    box-sizing: border-box;
}
.modal-body > :first-child { margin-top: 0; }
.modal-body > :last-child { margin-bottom: 0; }

/* Settings must occupy the whole viewport, not a centered card. */
#settings-modal {
    padding: 0 !important;
    align-items: stretch !important;
    justify-content: stretch !important;
}
#settings-modal .settings-content,
#settings-modal .settings-content.has-modal-topbar {
    width: 100dvw !important;
    max-width: 100dvw !important;
    height: 100dvh !important;
    max-height: 100dvh !important;
    margin: 0 !important;
    border-radius: 0 !important;
    aspect-ratio: auto !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
}
#settings-modal .settings-content > .modal-body {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 232px;
    grid-template-rows: minmax(0, 1fr);
    column-gap: 24px;
    padding: 18px 28px 24px;
    overflow: hidden;
}
#settings-modal .settings-tab-panel {
    grid-column: 1;
    grid-row: 1;
    min-height: 0;
    padding: 0 6px 24px 0;
    overflow-y: auto;
}
#settings-modal .settings-tab-bar {
    grid-column: 2;
    grid-row: 1;
    align-self: start;
    max-height: 100%;
    overflow-y: auto;
}
#settings-modal .modal-topbar { padding-left: 28px; padding-right: 28px; }

/* The info modal has its own scroll area below the shared top bar. */
#info-modal .info-content { padding: 0 !important; }
#info-modal .modal-body {
    display: flex;
    flex-direction: column;
    padding: 0;
    overflow: hidden;
}
#info-modal .info-scroll {
    flex: 1 1 auto;
    max-height: none;
    overflow-y: auto;
}

#alert-modal .modal-body { padding-top: 18px; }
#alert-modal .alert-header { padding-top: 0 !important; }
#video-crop-modal .modal-body { padding: 16px 18px 18px; }

@media (max-width: 820px) {
    #settings-modal .settings-content > .modal-body {
        grid-template-columns: minmax(0, 1fr) 56px;
        column-gap: 10px;
        padding: 14px 12px 18px;
    }
    #settings-modal .settings-tab-bar { padding: 5px; }
    #settings-modal .modal-topbar { padding-left: 14px; padding-right: 12px; }
}
@media (max-width: 640px) {
    .modal-topbar {
        min-height: 54px;
        padding: 8px 12px;
        padding-top: max(8px, env(safe-area-inset-top));
    }
    .modal-topbar-title { font-size: 16px; }
    .modal-body { padding: 16px; }
    #settings-modal .settings-content > .modal-body { padding: 12px; }
}
.app-steps,
.app-steps button,
.app-steps input,
.app-steps select,
.app-steps textarea { font-family: inherit; }
.app-steps button,
.app-steps input,
.app-steps select,
.app-steps textarea { transition: border-color var(--transition-fast), background-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast), transform var(--transition-fast); }
/* Icon-only controls own their geometry (pills and squares); the generic rule
   only applies to text buttons. */
.app-steps button:not(.maplibregl-ctrl-icon):not(.leaflet-control-zoom-in):not(.leaflet-control-zoom-out):not(.map-locate-btn):not(.mode-switch-btn):not(.map-day-btn):not(.map-sidebar-show):not(.map-reset-btn):not(.trip-close):not(.chart-nav) { min-height: var(--control-height); border-radius: var(--radius-sm); }
.app-steps button:disabled { cursor: not-allowed; opacity: .52; }
.app-steps button:not(:disabled):active { transform: translateY(1px); }
.app-steps .app-icon { display: inline-block; width: 1em; font-family: "Font Awesome 6 Free"; font-size: 18px; font-style: normal; font-weight: 900; line-height: 1; text-align: center; }
.app-steps .app-icon--sm { font-size: 14px; }
.app-topbar-actions > button > i { width: 20px; font-size: 20px !important; line-height: 1; text-align: center; }
.app-steps input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="color"]),
.app-steps select,
.app-steps textarea { box-sizing: border-box; border: 1px solid var(--border-color); border-radius: var(--radius-sm); background: var(--surface-solid); color: var(--text-color); }
.app-steps input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="color"]),
.app-steps select { min-height: var(--control-height); padding: 0 var(--space-3); }
.app-steps textarea { min-height: 96px; padding: var(--space-3); resize: vertical; }
.app-steps input:focus-visible,
.app-steps select:focus-visible,
.app-steps textarea:focus-visible { border-color: var(--primary-color); box-shadow: 0 0 0 3px var(--focus-ring); outline: 0; }
.modal-close,
.app-close-button,
#global-image-viewer-close,
.trip-close,
.picker-x {
  width: 40px !important;
  height: 40px !important;
  min-width: 40px !important;
  min-height: 40px !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 0 !important;
  border: 1px solid #34363B !important;
  border-radius: 12px !important;
  background: #242528 !important;
  color: #9FA3AA !important;
  line-height: 1 !important;
  font-size: 0 !important;
  flex-shrink: 0 !important;
  cursor: pointer !important;
  transition: background-color 200ms cubic-bezier(0.2, 0, 0, 1), border-color 200ms cubic-bezier(0.2, 0, 0, 1), color 200ms cubic-bezier(0.2, 0, 0, 1), transform 200ms cubic-bezier(0.2, 0, 0, 1) !important;
}
.app-close-button > i,
.modal-close > i,
#global-image-viewer-close > i,
.trip-close > i,
.picker-x > i,
.app-close-button > .material-symbols-rounded,
.modal-close > .material-symbols-rounded,
#global-image-viewer-close > .material-symbols-rounded,
.trip-close > .material-symbols-rounded,
.picker-x > .material-symbols-rounded {
  display: block !important;
  width: 20px !important;
  height: 20px !important;
  font-size: 20px !important;
  line-height: 20px !important;
  text-align: center !important;
  transition: transform 200ms cubic-bezier(0.2, 0, 0, 1) !important;
}
.modal-close:hover,
.app-close-button:hover,
#global-image-viewer-close:hover,
.trip-close:hover,
.picker-x:hover {
  background: #32353B !important;
  border-color: #4B4F58 !important;
  color: #fff !important;
}
.modal-close:hover > i,
.modal-close:hover > .material-symbols-rounded,
.app-close-button:hover > i,
.app-close-button:hover > .material-symbols-rounded,
#global-image-viewer-close:hover > i,
#global-image-viewer-close:hover > .material-symbols-rounded,
.trip-close:hover > i,
.trip-close:hover > .material-symbols-rounded,
.picker-x:hover > i,
.picker-x:hover > .material-symbols-rounded {
  transform: rotate(90deg) !important;
}
.modal-close:active,
.app-close-button:active,
#global-image-viewer-close:active,
.trip-close:active,
.picker-x:active {
  transform: scale(0.9) !important;
}
.modal-close:focus-visible,
.app-close-button:focus-visible,
#global-image-viewer-close:focus-visible,
.trip-close:focus-visible,
.picker-x:focus-visible {
  outline: none !important;
  box-shadow: 0 0 0 2px rgba(76, 154, 255, 0.5) !important;
}
body[data-theme="light"] .modal-close,
body[data-theme="light"] .app-close-button,
body[data-theme="light"] #global-image-viewer-close,
body[data-theme="light"] .trip-close,
body[data-theme="light"] .picker-x,
body.light .modal-close,
body.light .app-close-button,
body.light #global-image-viewer-close,
body.light .trip-close,
body.light .picker-x {
  background: #F1F5F9 !important;
  border-color: #E2E8F0 !important;
  color: #475569 !important;
}
body[data-theme="light"] .modal-close:hover,
body[data-theme="light"] .app-close-button:hover,
body[data-theme="light"] #global-image-viewer-close:hover,
body[data-theme="light"] .trip-close:hover,
body[data-theme="light"] .picker-x:hover,
body.light .modal-close:hover,
body.light .app-close-button:hover,
body.light #global-image-viewer-close:hover,
body.light .trip-close:hover,
body.light .picker-x:hover {
  background: #E2E8F0 !important;
  border-color: #CBD5E1 !important;
  color: #0F172A !important;
}
.app-steps table:not(.calendar-table) { border-collapse: separate; border-spacing: 0; width: 100%; }
.app-steps th { color: var(--text-secondary); font-size: 12px; font-weight: 700; letter-spacing: .02em; text-align: left; }
.app-steps th,
.app-steps td { padding: var(--space-3); border-bottom: 1px solid var(--border-subtle); vertical-align: middle; }
.app-steps tr:last-child td { border-bottom: 0; }
.app-steps .calendar-table { border-collapse: separate; border-spacing: 6px; }
.app-steps .calendar-table th,
.app-steps .calendar-table td { padding: 0; border-bottom: 0; }
#stats-modal .stats-content.has-modal-topbar { gap: 0; padding: 0 !important; overflow: hidden; }
#stats-modal .stats-content > .modal-body { padding: var(--space-5); }
#stats-modal .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-4); }
#stats-modal .stats-grid > .stats-card { grid-column: auto; justify-self: stretch; max-width: none; }
.stats-tab { --stats-icon: var(--primary-color); }
.stats-tab--overview { --stats-icon: #3b82f6; }
.stats-tab--activity { --stats-icon: #16a34a; }
.stats-tab--movement { --stats-icon: #f97316; }
.stats-tab--achievements { --stats-icon: #ca8a04; }
.stats-tab--quality { --stats-icon: #7c3aed; }
.stats-tab > i { color: var(--stats-icon); opacity: 1; }
.stats-tab.is-active > i { color: #fff; }
.stats-card-title { display: flex; align-items: center; gap: var(--space-2); }
.stats-card-title > i { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: var(--radius-sm); font-size: 14px; }
.stats-card-title--steps > i { color: #2563eb; background: color-mix(in srgb, #2563eb 14%, transparent); }
.stats-card-title--calendar > i { color: #16a34a; background: color-mix(in srgb, #16a34a 14%, transparent); }
.stats-achievement { --achievement-color: var(--primary-color); border-color: color-mix(in srgb, var(--achievement-color) 24%, var(--border-color)); background: linear-gradient(155deg, color-mix(in srgb, var(--achievement-color) 12%, var(--group-block-bg)), var(--group-block-bg) 70%); box-shadow: 0 9px 26px color-mix(in srgb, var(--achievement-color) 10%, transparent); }
.stats-achievement::after { background: color-mix(in srgb, var(--achievement-color) 16%, transparent); }
.stats-achievement-icon { color: var(--achievement-color); background: color-mix(in srgb, var(--achievement-color) 14%, transparent); border-color: color-mix(in srgb, var(--achievement-color) 22%, transparent); }
.stats-achievement-badge { color: var(--achievement-color); }
.stats-achievement--coverage { --achievement-color: #16a34a; }
.stats-achievement--distance { --achievement-color: #2563eb; }
.stats-achievement--places { --achievement-color: #d97706; }
.stats-achievement--cities { --achievement-color: #7c3aed; }
.stats-achievement--regions { --achievement-color: #0891b2; }
.stats-achievement--countries { --achievement-color: #db2777; }
.stats-achievement--steps { --achievement-color: #0d9488; }
.stats-achievement--trail { --achievement-color: #ea580c; }
/* Bridge between the steps half and the location half, shown in settings. */
.achievement-bridge { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; margin: 12px 0 0; }
.achievement-bridge[hidden] { display: none; }
.achievement-bridge-item { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border-radius: var(--radius-sm); background: var(--group-block-bg); border: 1px solid var(--border-color); }
.achievement-bridge-item span { color: var(--muted-text); font-size: 10px; letter-spacing: .04em; text-transform: uppercase; }
.achievement-bridge-item strong { font-size: 15px; }
/* Metric rows sit flush under the tab bar; panel gap owns vertical rhythm. */
.stats-experience .stats-metric-grid { margin: 0; }
/* Tab icons get a contrasting chip so they never blend into the surface. */
.stats-tab > i {
    display: inline-grid;
    place-items: center;
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--stats-icon) 14%, var(--surface-solid));
    border: 1px solid color-mix(in srgb, var(--stats-icon) 34%, transparent);
    color: var(--stats-icon);
    font-size: 12px;
    opacity: 1;
}
.stats-tab.is-active > i {
    color: #fff;
    background: rgba(255, 255, 255, 0.22);
    border-color: rgba(255, 255, 255, 0.46);
}
/* Sources/devices read as a real table with clear separators. */
.stats-source-table { width: 100%; border-collapse: collapse; font-size: 12px; text-align: left; }
.stats-source-table caption {
    text-align: left;
    padding: 0 0 6px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--muted-text);
}
.stats-source-table th,
.stats-source-table td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--border-subtle);
    vertical-align: middle;
}
.stats-source-table th {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--muted-text);
    background: color-mix(in srgb, var(--primary-color) 6%, transparent);
    border-bottom: 1px solid color-mix(in srgb, var(--primary-color) 26%, var(--border-color));
}
.stats-source-table tbody tr:nth-child(even) td { background: color-mix(in srgb, var(--primary-color) 4%, transparent); }
.stats-source-table tbody tr:last-child td { border-bottom: 0; }
.stats-source-table td.stats-source-name { font-weight: 700; color: var(--text-color); }
.stats-source-table td.stats-source-count { font-variant-numeric: tabular-nums; text-align: right; }
.stats-source-table .stats-source-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
}
.stats-source-table .stats-source-status.is-paused { background: rgba(217, 119, 6, 0.14); color: #b45309; }
.stats-source-table .stats-source-status.is-revoked { background: rgba(220, 38, 38, 0.14); color: #dc2626; }
.stats-source-empty { margin: 0; padding: 12px; color: var(--muted-text); font-size: 12px; }
@media (max-width: 640px) {
    #stats-modal .stats-content > .modal-body { padding: var(--space-4); }
    #stats-modal .stats-grid { grid-template-columns: 1fr; gap: var(--space-3); }
}
@media (max-width: 640px) {
    .app-steps button:not(.maplibregl-ctrl-icon):not(.leaflet-control-zoom-in):not(.leaflet-control-zoom-out):not(.mode-switch-btn):not(.map-day-btn):not(.map-sidebar-show):not(.map-reset-btn):not(.trip-close):not(.chart-nav) { min-height: 44px; }
    .app-steps th,
    .app-steps td { padding: var(--space-2); }
}
/* Desktop: the dashboard owns the viewport, so the page itself never scrolls.
   The calendar shrinks to fit, and only that column can scroll as a fallback. */
@media (min-width: 900px) and (pointer: fine) {
    html, body { height: 100%; }
    body { overflow: hidden; }
    .app-steps {
        height: 100dvh;
        min-height: 0;
        max-height: 100dvh;
        overflow: hidden;
        padding-bottom: 12px;
    }
    .app-main {
        flex: 1 1 auto;
        min-height: 0;
        grid-template-rows: auto minmax(0, 1fr) auto auto;
    }
    .chart-container,
    .chart-wrapper,
    #steps-chart { min-height: 0; }
    .calendar-container {
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        scrollbar-width: thin;
    }
    .calendar-container #calendar { flex: 0 1 auto; min-height: 0; }
}
@media (min-width: 900px) and (pointer: fine) and (max-height: 860px) {
    .calendar-table th,
    .calendar-table td { height: 34px; }
    .calendar-selector-box { margin-bottom: 6px; padding: 5px; }
    .goal-summary { padding: 8px 10px; }
}
@media (min-width: 900px) and (pointer: fine) and (max-height: 720px) {
    .calendar-table th,
    .calendar-table td { height: 28px; }
    .app-main { gap: 6px 10px; padding: 10px; }
    .view-btn { padding: 7px 10px; }
}
/* ModalCloseButton — final lock so no modal-specific rules can drift size/shape */
body.md3-steps .modal-close,
body.md3-steps .app-close-button,
body.md3-steps #global-image-viewer-close,
body.md3-steps .trip-close,
body.md3-steps .picker-x {
    width: 40px !important;
    height: 40px !important;
    min-width: 40px !important;
    min-height: 40px !important;
    padding: 0 !important;
    border-radius: 12px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0 !important;
    flex-shrink: 0 !important;
    background: #242528 !important;
    border: 1px solid #34363B !important;
    color: #9FA3AA !important;
    transition: background-color 200ms cubic-bezier(0.2, 0, 0, 1), border-color 200ms cubic-bezier(0.2, 0, 0, 1), color 200ms cubic-bezier(0.2, 0, 0, 1), transform 200ms cubic-bezier(0.2, 0, 0, 1) !important;
}
body.md3-steps .md3-modal-head .modal-close,
body.md3-steps .md3-modal-head .app-close-button,
body.md3-steps .modal-topbar .modal-close,
body.md3-steps #guide-expand-modal .modal-close,
body.md3-steps #info-modal .modal-close,
body.md3-steps #mobile-app-modal .modal-close,
body.md3-steps #settings-modal .modal-close {
    position: static !important;
    inset: auto !important;
    margin: 0 !important;
}
body.md3-steps .modal-close:hover,
body.md3-steps .app-close-button:hover,
body.md3-steps #global-image-viewer-close:hover,
body.md3-steps .trip-close:hover,
body.md3-steps .picker-x:hover {
    background: #32353B !important;
    border-color: #4B4F58 !important;
    color: #fff !important;
}
body.md3-steps .modal-close:hover > i,
body.md3-steps .modal-close:hover > .material-symbols-rounded,
body.md3-steps .app-close-button:hover > i,
body.md3-steps .app-close-button:hover > .material-symbols-rounded,
body.md3-steps #global-image-viewer-close:hover > i,
body.md3-steps #global-image-viewer-close:hover > .material-symbols-rounded,
body.md3-steps .trip-close:hover > i,
body.md3-steps .trip-close:hover > .material-symbols-rounded,
body.md3-steps .picker-x:hover > i,
body.md3-steps .picker-x:hover > .material-symbols-rounded {
    transform: rotate(90deg) !important;
}
body.md3-steps .modal-close:active,
body.md3-steps .app-close-button:active,
body.md3-steps #global-image-viewer-close:active,
body.md3-steps .trip-close:active,
body.md3-steps .picker-x:active {
    transform: scale(0.9) !important;
}
body.md3-steps[data-theme="light"] .modal-close,
body.md3-steps[data-theme="light"] .app-close-button,
body.md3-steps[data-theme="light"] #global-image-viewer-close,
body.md3-steps[data-theme="light"] .trip-close,
body.md3-steps[data-theme="light"] .picker-x,
body.md3-steps.light .modal-close,
body.md3-steps.light .app-close-button,
body.md3-steps.light #global-image-viewer-close,
body.md3-steps.light .trip-close,
body.md3-steps.light .picker-x {
    background: #F1F5F9 !important;
    border-color: #E2E8F0 !important;
    color: #475569 !important;
}
body.md3-steps[data-theme="light"] .modal-close:hover,
body.md3-steps[data-theme="light"] .app-close-button:hover,
body.md3-steps[data-theme="light"] #global-image-viewer-close:hover,
body.md3-steps[data-theme="light"] .trip-close:hover,
body.md3-steps[data-theme="light"] .picker-x:hover,
body.md3-steps.light .modal-close:hover,
body.md3-steps.light .app-close-button:hover,
body.md3-steps.light #global-image-viewer-close:hover,
body.md3-steps.light .trip-close:hover,
body.md3-steps.light .picker-x:hover {
    background: #E2E8F0 !important;
    border-color: #CBD5E1 !important;
    color: #0F172A !important;
}

        /* --- Mobile/tablet top bar fit (last-loaded, overrides shell + app rules) --- */
        @media (max-width: 900px) {
            body.md3-steps .app-steps.md3 .app-brand { min-width: 0; flex: 0 1 auto; overflow: hidden; }
            body.md3-steps .app-steps.md3 .app-brand-text { min-width: 0; overflow: hidden; }
            body.md3-steps .app-steps.md3 .app-brand-text h1 { overflow: hidden; text-overflow: ellipsis; }
            body.md3-steps .app-steps.md3 .mode-switch span.mode-label { display: none; }
            body.md3-steps .app-steps.md3 .mode-switch { flex: 0 0 auto; }
            body.md3-steps .app-steps.md3 .mode-switch-btn { flex-shrink: 0; padding: 0 12px; }
            body.md3-steps .app-steps.md3 .app-topbar-actions { width: auto; flex: 0 1 auto; min-width: 0; justify-content: flex-end; margin-left: auto; }
        }
        @media (max-width: 640px) {
            body.md3-steps .app-steps.md3 { --topbar-height: 109px; } /* two-row bar, keeps .app-steps padding in sync */
            body.md3-steps .app-steps.md3 .app-topbar { height: auto; min-height: 0; padding-top: 8px; padding-bottom: 8px; }
            body.md3-steps .app-steps.md3 .app-topbar-inner { height: auto; flex-wrap: wrap; row-gap: 8px; column-gap: 8px; }
            body.md3-steps .app-steps.md3 .app-brand { order: 0; }
            body.md3-steps .app-steps.md3 .app-topbar-actions { order: 1; gap: 4px; }
            body.md3-steps .app-steps.md3 .mode-switch { order: 2; flex: 1 0 100%; height: 38px; margin: 0; padding: 3px; justify-content: center; }
            body.md3-steps .app-steps.md3 .mode-switch-btn { flex: 1 1 0; justify-content: center; padding: 0 10px; }
            body.md3-steps .app-steps.md3 .app-topbar-actions > #sharepoint-share-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #add-steps-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #stepmonitor-apk-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #stats-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #settings-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #info-button,
            body.md3-steps .app-steps.md3 #user-button,
            body.md3-steps .app-steps.md3 .topbar-icon-btn { width: 34px; height: 34px; min-width: 34px; min-height: 34px; font-size: 19px; }
        }
        @media (max-width: 480px) {
            body.md3-steps .app-steps.md3 { --topbar-height: 97px; }
            body.md3-steps .app-steps.md3 .app-brand-mark { width: 32px; height: 32px; }
            body.md3-steps .app-steps.md3 .app-topbar-actions { gap: 3px; }
            body.md3-steps .app-steps.md3 .app-topbar-actions > #sharepoint-share-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #add-steps-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #stepmonitor-apk-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #stats-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #settings-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #info-button,
            body.md3-steps .app-steps.md3 #user-button,
            body.md3-steps .app-steps.md3 .topbar-icon-btn { width: 30px; height: 30px; min-width: 30px; min-height: 30px; font-size: 17px; }
        }
        @media (max-width: 340px) {
            body.md3-steps .app-steps.md3 { --topbar-height: 89px; }
            body.md3-steps .app-steps.md3 .app-brand { display: none; }
            body.md3-steps .app-steps.md3 .app-topbar-actions { gap: 2px; }
            body.md3-steps .app-steps.md3 .app-topbar-actions > #sharepoint-share-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #add-steps-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #stepmonitor-apk-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #stats-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #settings-button,
            body.md3-steps .app-steps.md3 .app-topbar-actions > #info-button,
            body.md3-steps .app-steps.md3 #user-button,
            body.md3-steps .app-steps.md3 .topbar-icon-btn { width: 28px; height: 28px; min-width: 28px; min-height: 28px; font-size: 16px; }
        }
        /* Add-steps tab bar must not widen the modal at phone widths */
        @media (max-width: 900px) {
            body.md3-steps #add-steps-modal .add-tab-bar { align-self: stretch; width: auto; max-width: 100%; min-width: 0; box-sizing: border-box; }
            body.md3-steps #add-steps-modal .add-tab { min-width: 0; }
        }
</style>
<?php require_once dirname(__DIR__, 3) . '/lib/sentry_bootstrap.php'; app_sentry_browser_script('steps'); ?>
</head>
