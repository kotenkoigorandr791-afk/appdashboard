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
            color-scheme: <?php echo $theme === 'dark' ? 'dark' : 'light'; ?>;
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
        #guide-expand-modal .modal-close { position: static; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; }
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
                "view view";
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
        .chart-nav { flex-shrink: 0; align-self: center; font-size: 17px; background: transparent; border: 1px solid var(--border-color); border-radius: var(--radius-sm); width: 36px; height: 60px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--muted-text); }
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
            position: absolute;
            top: 16px;
            right: 16px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 28px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        #info-modal .modal-close:hover {
            background: color-mix(in srgb, var(--primary-color) 10%, transparent);
            color: var(--primary-color);
        }

        #stats-modal .stats-content {
            width: calc(100vw - 24px);
            overflow-y: auto;
            overflow-x: hidden;
        }
        @media (min-width: 769px) {
            #stats-modal .stats-card {
                grid-column: span 2;
                justify-self: center;
                width: 100%;
                max-width: calc(100vw * 2 / 3);
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
        .map-photo-marker { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #f59e0b; color: #111; border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,.4); }
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
            position: absolute;
            top: 20px;
            right: 20px;
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--muted-text, #a0a0a0);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .mobile-app-content .modal-close:hover {
            background-color: color-mix(in srgb, var(--surface-solid) 65%, transparent);
            color: var(--text-color);
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
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--surface-solid) 72%, transparent);
    color: var(--muted-text);
    transition: transform 0.22s ease, background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
#alert-modal:not(#guide-expand-modal) .alert-close:hover {
    transform: rotate(90deg);
    background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-solid));
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 36%, var(--border-color));
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
    top: 16px;
    right: 16px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--surface-solid) 72%, transparent);
    color: var(--muted-text);
    transition: transform 0.22s ease, background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
#video-confirm-modal .modal-close:hover,
#video-confirm-gaps-modal .modal-close:hover {
    transform: rotate(90deg);
    background: color-mix(in srgb, var(--primary-color) 12%, var(--surface-solid));
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 36%, var(--border-color));
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
.map-stage .leaflet-control-scale-line { background: rgba(11,17,23,0.6); color: #cbd5e1; border-color: rgba(255,255,255,0.4); border-radius: 6px; }
.map-stage .leaflet-control-attribution { background: rgba(11,17,23,0.55) !important; color: #94a3b8 !important; font-size: 9px !important; border-radius: 6px; }
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
@media (max-width: 1024px) {
    .map-workspace { grid-template-columns: minmax(0, 1fr) 286px; height: min(76vh, 720px); }
}
@media (max-width: 820px) {
    .map-workspace { grid-template-columns: 1fr; height: auto; min-height: 0; }
    .map-stage { min-height: 58vh; }
    .map-sidebar { order: 2; }
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
</style>
<?php require_once dirname(__DIR__, 3) . '/lib/sentry_bootstrap.php'; app_sentry_browser_script('steps'); ?>
</head>
