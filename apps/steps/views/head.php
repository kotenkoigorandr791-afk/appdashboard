<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($translations['steps_counter_title']); ?></title>
    <?php
    $bgFull = $theme === 'dark' ? 'background_dark.jpg' : 'background_light.jpg';
    $nameConfig = json_decode((string) @file_get_contents(__DIR__ . '/../name.json'), true);
    $menuButton = is_array($nameConfig['menu_button'] ?? null) ? $nameConfig['menu_button'] : [];
    $menuButtonLeft = (int) ($menuButton['left'] ?? 16);
    $menuButtonTop = (int) ($menuButton['top'] ?? 16);
    $menuButtonWidth = (int) ($menuButton['width'] ?? 40);
    $menuButtonHeight = (int) ($menuButton['height'] ?? 40);
    ?>
    <link rel="preload" as="image" href="<?php echo htmlspecialchars($bgFull); ?>" fetchpriority="high">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <style>
        :root {
            --bg-color: <?php echo $theme === 'dark' ? '#111416' : '#f4f7f2'; ?>;
            --text-color: <?php echo $theme === 'dark' ? '#f4f7f2' : '#161b18'; ?>;
            --primary-color: <?php echo htmlspecialchars($color); ?>;
            --modal-bg: <?php echo $theme === 'dark' ? 'rgba(24, 28, 29, 0.92)' : 'rgba(255, 255, 250, 0.86)'; ?>;
            --border-color: <?php echo $theme === 'dark' ? 'rgba(244, 247, 242, 0.14)' : 'rgba(22, 27, 24, 0.12)'; ?>;
            --group-block-bg: <?php echo $theme === 'dark' ? 'rgba(244, 247, 242, 0.07)' : 'rgba(255, 255, 250, 0.66)'; ?>;
            --chart-color: <?php echo $theme === 'dark' ? '#f4f7f2' : '#161b18'; ?>;
            --scrollbar-thumb: <?php echo $theme === 'dark' ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.3)'; ?>;
            --muted-text: <?php echo $theme === 'dark' ? 'rgba(244, 247, 242, 0.68)' : 'rgba(22, 27, 24, 0.62)'; ?>;
            --surface-solid: <?php echo $theme === 'dark' ? '#181c1d' : '#fffffa'; ?>;
            --surface-rail: <?php echo $theme === 'dark' ? 'rgba(12, 15, 15, 0.74)' : 'rgba(232, 239, 224, 0.82)'; ?>;
            --shadow-soft: 0 26px 70px rgba(0,0,0,<?php echo $theme === 'dark' ? '0.34' : '0.16'; ?>);
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
        .settings-content, .add-steps-content, .user-content, .add-user-content, .video-analysis-content, .video-confirm-inner, .video-confirm-gaps-inner, .excel-content, .backups-content, .alert-content, .info-content, .stats-content,
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
        #add-steps-modal #add-panel-video .video-analysis-content {
            width: 100%;
            max-width: min(1100px, calc(100vw - 32px));
            min-width: 0;
            height: auto;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            padding: 0;
            box-sizing: border-box;
            overflow-x: hidden;
            overflow-y: visible;
        }
        #add-steps-modal #add-panel-video .va-head {
            padding: 18px 22px 14px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, rgba(0,0,0,0.06) 0%, transparent 60%);
            text-align: center;
        }
        #add-steps-modal #add-panel-video .video-inline-scroll {
            flex: 0 1 auto;
            overflow: visible;
            overflow-x: hidden;
            text-align: left;
            padding: 16px 18px 18px;
            display: flex;
            justify-content: center;
            width: 100%;
        }
        #add-steps-modal #add-panel-video .va-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
            align-items: center;
            width: 100%;
            max-width: 100%;
        }
        #add-steps-modal #add-panel-video .va-segment {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 16px;
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
        #add-steps-modal #add-panel-video .takeout-guide-segment {
            width: min(100%, 860px);
            max-width: 100%;
            padding: 0;
            overflow: hidden;
            background: var(--modal-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-sizing: border-box;
        }
        #add-steps-modal #add-panel-video .takeout-guide-head {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: space-between;
            padding: 12px 14px;
            background: linear-gradient(130deg, color-mix(in srgb, var(--primary-color) 18%, transparent), transparent 70%);
            font-size: 14px;
            line-height: 1.4;
        }
        #add-steps-modal #add-panel-video .takeout-guide-toggle {
            margin-left: auto;
            border: 0;
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            padding: 2px 4px;
            flex-shrink: 0;
        }
        #add-steps-modal #add-panel-video .takeout-guide-body {
            border-top: 1px solid var(--border-color);
            overflow: hidden;
        }
        #add-steps-modal #add-panel-video #zeep-guide-frame {
            width: 100%;
            height: min(66.6vh, 1100px);
            min-height: 360px;
            border: 0;
            display: block;
            background: var(--modal-bg);
        }
        #add-steps-modal #add-panel-video .takeout-guide-segment.is-collapsed .takeout-guide-body {
            display: none;
        }
        #add-steps-modal #add-panel-video .takeout-guide-segment.is-collapsed .takeout-guide-toggle i {
            transform: rotate(-90deg);
        }
        #add-steps-modal #add-panel-video .takeout-guide-toggle i {
            transition: transform 0.2s ease;
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
        .takeout-guide-actions { display: flex; align-items: center; gap: 6px; margin-left: auto; flex-shrink: 0; }
        .takeout-guide-expand { border: 0; background: transparent; color: var(--text-color); cursor: pointer; font-size: 13px; line-height: 1; padding: 3px 5px; opacity: 0.75; border-radius: 6px; transition: all 0.2s ease; }
        .takeout-guide-expand:hover { opacity: 1; color: var(--primary-color); background: rgba(127,127,127,0.12); }
        #guide-expand-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); display: none; justify-content: center; align-items: stretch; z-index: 10035; overflow: hidden; box-sizing: border-box; padding: 12px; }
        #guide-expand-modal .guide-expand-content { background: var(--modal-bg); width: calc(100vw - 24px); height: calc(100vh - 24px); max-width: calc(100vw - 24px); max-height: calc(100vh - 24px); border-radius: var(--radius-lg); position: relative; display: flex; flex-direction: column; padding: clamp(14px, 2vw, 22px); box-sizing: border-box; box-shadow: 0 24px 60px rgba(0,0,0,0.5); min-width: 0; }
        #guide-expand-modal .guide-expand-title { display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 600; margin: 0 0 12px; color: var(--text-color); padding-right: 40px; }
        #guide-expand-modal .guide-expand-title i { color: var(--primary-color); }
        #guide-expand-modal .guide-expand-frame-wrap { flex: 1; min-height: 0; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
        #guide-expand-modal #guide-expand-frame { width: 100%; height: 100%; border: 0; display: block; background: var(--modal-bg); }
        #guide-expand-modal .modal-close { top: 12px; right: 12px; }
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
        #add-steps-modal #add-panel-video .video-btn-row .excel-btn { padding: 10px 18px; border-radius: 8px; }
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

        body { margin: 0; padding: 0; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--bg-color); color: var(--text-color); transition: color 0.3s ease; position: relative; min-height: 100vh; }

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
            max-width: 1320px;
            width: 100%;
            margin: 0 auto;
            position: relative;
            box-sizing: border-box;
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
            box-sizing: border-box;
            display: grid;
            grid-template-columns: minmax(300px, 0.82fr) minmax(0, 1.42fr);
            grid-template-areas:
                "view chart"
                "types chart"
                "calendar chart"
                "goal chart";
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
        .chart-container { grid-area: chart; position: relative; margin-bottom: 0; display: flex; flex-direction: row; align-items: stretch; justify-content: center; gap: 10px; width: 100%; max-width: none; min-height: 430px; margin-left: auto; margin-right: auto; box-sizing: border-box; padding: clamp(10px, 1.6vw, 16px); border-radius: calc(var(--radius-lg) - 4px); background: var(--surface-solid); border: 1px solid rgba(127,127,127,0.1); }
        .chart-nav { flex-shrink: 0; align-self: center; font-size: 17px; background: transparent; border: 1px solid var(--border-color); border-radius: var(--radius-sm); width: 36px; height: 60px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--muted-text); }
        .chart-nav:hover { background: var(--primary-color); color: #fff; }
        .chart-nav:disabled { opacity: 0.3; cursor: not-allowed; background: var(--group-block-bg); color: var(--text-color); }
        .chart-nav:disabled:hover { background: var(--group-block-bg); color: var(--text-color); }
        .chart-main { position: relative; flex: 1 1 auto; min-width: 0; max-width: none; width: 100%; padding-top: 46px; display: flex; flex-direction: column; }
        .chart-label {
            position: absolute;
            top: 0;
            left: 0;
            transform: none;
            font-size: clamp(20px, 2.2vw, 34px);
            font-weight: 800;
            text-align: left;
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
        .chart-wrapper { position: relative; width: 100%; max-width: none; margin: 0 auto; flex: 1 1 auto; min-height: 340px; }
        #steps-chart { width: 100%; height: 100%; min-height: 340px; max-width: none; display: block; }
        .calendar-container { grid-area: calendar; margin-top: 0; width: 100%; margin-left: auto; margin-right: auto; min-width: 0; }
        .calendar-header { display: flex; gap: 8px; justify-content: stretch; }
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
        .calendar-table { width: 100%; border-collapse: separate; border-spacing: 4px; outline: none; table-layout: fixed; }
        .calendar-table th, .calendar-table td { pointer-events: none; outline: none; border: 0; padding: 0; text-align: center; height: 42px; pointer-events: none; }
        .calendar-table th { color: var(--muted-text); font-weight: 800; font-size: 11px; text-transform: uppercase; }
        .calendar-table td { position: relative; width: 14.28%; border-radius: var(--radius-sm); background: rgba(127,127,127,0.07); font-weight: 700; transition: background 0.16s ease, color 0.16s ease, box-shadow 0.16s ease; }
        .calendar-table td[data-date] { cursor: pointer; pointer-events: auto; }
        .calendar-table td[data-date]:hover { background: color-mix(in srgb, var(--primary-color) 18%, transparent); }
        .calendar-table td.selected { background: var(--primary-color); color: #fff; box-shadow: inset 0 -3px 0 rgba(0,0,0,0.16); }
        .calendar-table td.calendar-day-other-month { color: var(--muted-text); opacity: 0.52; }
        .calendar-table td.calendar-day-other-month.selected { background: var(--primary-color); color: #fff; }

        .goal-icon { width: 15px; height: 15px; position: absolute; right: 3px; bottom: 3px; top: auto; transform: none; cursor: pointer; }
        .goal-summary { grid-area: goal; margin-top: 0; padding: 12px; background: var(--surface-solid); border-radius: var(--radius-md); border: 1px solid var(--border-color); display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; text-align: left; }
        .goal-summary-item { min-width: 0; }
        .goal-summary-label { display: block; color: var(--muted-text); font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .goal-summary-value { display: block; margin-top: 4px; font-size: 20px; font-weight: 850; color: var(--text-color); }

        #info-button, #settings-button, #add-steps-button, #excel-button, #stats-button {
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
        #info-button:hover, #settings-button:hover, #add-steps-button:hover, #excel-button:hover, #stats-button:hover, #stats-button.active {
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
        }

        /* Modal windows for mobile */
        #info-modal, #stats-modal, #settings-modal, #add-steps-modal, #user-modal, #add-user-modal, #video-confirm-modal, #video-confirm-gaps-modal, #excel-modal, #excel-hotkeys-modal, #backups-modal, #alert-modal {
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
            padding: 12px;
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

        .settings-content, .add-steps-content, .user-content, .add-user-content, .video-analysis-content, .video-confirm-inner, .video-confirm-gaps-inner, .excel-content, .backups-content, .alert-content, .info-content, .stats-content {
            background: var(--modal-bg);
            padding: clamp(14px, 2vw, 22px);
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
            overflow-y: auto;
            overflow-x: hidden;
        }
        #stats-modal .stats-content {
            width: calc(100vw - 24px);
            overflow-y: auto;
            overflow-x: hidden;
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
            gap: 4px;
            padding: 5px;
            margin-bottom: 14px;
            background: var(--group-block-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
        }
        .settings-tab {
            flex: 1;
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
        #add-panel-takeout .video-analysis-content {
            padding: 2px;
            max-width: min(1100px, calc(100vw - 32px));
            height: auto;
            max-height: none;
            margin: 0 auto;
        }
        #add-panel-takeout .va-head {
            text-align: center;
            margin-bottom: 10px;
        }
        #add-panel-takeout .va-head h2 {
            margin: 0 0 6px 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        #add-panel-takeout .va-sub {
            margin: 0 0 10px 0;
            font-size: 13px;
            opacity: 0.9;
            line-height: 1.45;
        }
        #add-panel-takeout .takeout-hint {
            margin-bottom: 12px;
        }
        #add-panel-takeout .video-inline-scroll {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        #add-panel-takeout .va-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
            align-items: center;
            width: 100%;
            max-width: 100%;
        }
        #add-panel-takeout .va-segment {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--group-block-bg);
            padding: 12px 8px;
            width: min(100%, 860px);
        }
        #add-panel-takeout .va-field {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #add-panel-takeout .va-field label {
            display: block;
            margin: 8px 0 6px;
            font-size: 13px;
            font-weight: 500;
        }
        #add-panel-takeout .va-field input[type="file"] {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--modal-bg);
            color: var(--text-color);
        }
        #add-panel-takeout .video-btn-row {
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
        }
        #add-panel-takeout .video-btn-row .excel-btn {
            padding: 10px 18px;
            border-radius: 8px;
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
        #add-panel-takeout .takeout-guide-segment {
            width: 100%;
            max-width: 100%;
            padding: 0;
            overflow: hidden;
            background: var(--modal-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
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
        #add-panel-video .takeout-guide-segment {
            width: 100%;
            max-width: 100%;
            padding: 0;
            overflow: hidden;
            background: var(--modal-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
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
        .tile-content { display: grid; grid-template-columns: 80px 120px 70px; align-items: center; justify-items: center; gap: 10px; }
        .tile-content span:last-child { justify-self: end; }
        .save-button-container { background: var(--group-block-bg); border-radius: var(--radius-md); padding: 5px; margin-top: 20px; }
        .save-button { background: var(--primary-color); color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: var(--radius-sm); font-weight: 800; }

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
        .data-type-btn img { max-width: 62%; max-height: 62%; transition: transform 0.18s ease, filter 0.18s ease; cursor: pointer; }
        .data-type-btn:hover img { transform: scale(1.06); filter: brightness(1.07); }

        .chart-type-btn { background: var(--primary-color); color: #fff; border: none; padding: 8px; margin: 5px 0; cursor: pointer; border-radius: 4px; font-size: 14px; display: flex; align-items: center; }
        .chart-type-btn.active, .chart-type-btn:hover { opacity: 0.9; }
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

        .notification-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); }
        .notification-title { font-weight: bold; font-size: 16px; color: var(--primary-color); }
        .notification-content { font-size: 14px; line-height: 1.4; }
        .notification-item { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin: 5px 0; flex-wrap: nowrap; font-size: 13px; }
        .notification-item > span:first-child { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notification-value { font-weight: bold; flex-shrink: 0; white-space: nowrap; text-align: right; }
        .close-notification { background: none; border: none; color: #999; font-size: 18px; cursor: pointer; }
        .close-notification:hover { color: red; }

        .adjacent-dates { width: 100%; margin: 10px 0; }
        .adjacent-date { display: flex; justify-content: space-between; padding: 5px; background: var(--group-block-bg); border-radius: 4px; margin: 5px 0; opacity: 0.7; cursor: pointer; }
        .adjacent-date:hover { opacity: 1; background: var(--primary-color); color: #fff; }
        .current-date { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px; background: var(--modal-bg); border: 1px solid var(--primary-color); border-radius: 4px; margin: 10px auto; width: min(100%, 420px); box-sizing: border-box; }
        .current-date input[type="date"], .current-date input[type="text"] { flex: 1 1 0; min-width: 0; width: auto; font-size: 16px; box-sizing: border-box; }
        .current-date input[type="date"] { flex-basis: 150px; min-width: 145px; }

        #notifications { position: fixed; bottom: 20px; right: 20px; z-index: 10000; display: flex; flex-direction: column-reverse; align-items: flex-end; gap: 10px; max-width: min(100vw - 32px, 100%); box-sizing: border-box; }
        #worker-progress-widget { position: fixed; left: 20px; bottom: 20px; z-index: 10000; display: none; flex-direction: column; gap: 8px; align-items: flex-start; }
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
        }

        .info-section {
            margin: 20px 0;
            padding: 15px;
            background: var(--group-block-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .info-section h3 {
            color: var(--primary-color);
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-section ul {
            padding-left: 20px;
        }

        .info-section li {
            margin: 8px 0;
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

            .app-steps {
                max-width: 100%;
                padding: calc(var(--topbar-height) + 10px) 8px 10px;
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

            #info-button, #settings-button, #add-steps-button, #excel-button, #stats-button {
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
            }
            .app-main {
                grid-template-columns: 1fr;
                grid-template-areas:
                    "view"
                    "types"
                    "chart"
                    "calendar"
                    "goal";
                gap: 10px;
                padding: 10px;
                border-radius: 14px;
            }
            .goal-icon { width: 12px; height: 12px; right: 2px; left: auto; bottom: 2px; top: auto; transform: none; }

            .chart-wrapper { width: 100%; height: 240px; min-height: 240px; position: relative; overflow: hidden; box-sizing: border-box; }
            .chart-container { max-width: 100%; min-height: 310px; padding: 8px; box-sizing: border-box; flex-direction: row; align-items: stretch; gap: 5px; }
            .chart-main { max-width: none; padding-top: 38px; }
            .chart-label { font-size: 20px; }
            #steps-chart { width: 100% !important; height: 100% !important; min-height: 240px; }
            .chart-nav { width: 28px; height: 52px; font-size: 14px; }

            .calendar-container { width: 100%; padding: 0 10px; box-sizing: border-box; }
            .calendar-table { font-size: 12px; }
            .calendar-table th, .calendar-table td { padding: 0; height: 34px; }
            .goal-summary { grid-template-columns: 1fr 1fr; padding: 12px; }
            .goal-summary-value { font-size: 18px; }

            .settings-content, .add-steps-content, .user-content, .add-user-content, .video-analysis-content, .video-confirm-inner, .video-confirm-gaps-inner, .excel-content, .backups-content, .alert-content, .info-content, .stats-content {
                width: calc(100vw - 16px) !important;
                max-width: calc(100vw - 16px) !important;
                height: calc(100vh - 16px) !important;
                max-height: calc(100vh - 16px) !important;
                padding: 14px;
                margin: 0;
                overflow: auto;
                overflow-x: hidden;
            }
            #add-steps-modal .add-steps-merged {
                width: calc(100vw - 16px) !important;
                max-width: calc(100vw - 16px) !important;
                height: calc(100vh - 16px) !important;
                max-height: calc(100vh - 16px) !important;
                padding: 14px !important;
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
            #excel-modal .excel-content {
                width: calc(100vw - 16px) !important;
                max-width: calc(100vw - 16px) !important;
                height: calc(100vh - 16px) !important;
                max-height: calc(100vh - 16px) !important;
                margin: 0 !important;
                padding: 46px 42px 10px 10px !important;
            }
            .excel-modal-head {
                grid-template-columns: 44px minmax(0, 1fr) 44px;
                column-gap: 4px;
            }
            .excel-modal-head .excel-head-spacer {
                width: 38px;
                height: 38px;
            }
            #excel-modal {
                padding: 8px;
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
            #info-modal .info-content {
                overflow-y: auto;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .stats-chart-wrap {
                height: 300px;
            }
            #settings-modal .settings-content {
                width: calc(100vw - 16px) !important;
                max-width: calc(100vw - 16px) !important;
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
                margin: 0 5px;
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
    </style>
</head>
