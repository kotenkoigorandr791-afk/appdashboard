<?php
$nameData = is_file(__DIR__ . '/../name.json') ? json_decode((string)file_get_contents(__DIR__ . '/../name.json'), true) : [];
if (!is_array($nameData)) {
    $nameData = [];
}
$parseMenuBtn = static function (array $data, string $key, array $fallback): array {
    $btn = isset($data[$key]) && is_array($data[$key]) ? $data[$key] : $fallback;
    $parsed = $fallback;
    foreach (['left', 'top', 'width', 'height'] as $k) {
        if (isset($btn[$k]) && is_numeric($btn[$k])) {
            $parsed[$k] = max(0, (int)$btn[$k]);
        }
    }
    return $parsed;
};
$defaultMenuBtn = ['left' => 16, 'top' => 16, 'width' => 40, 'height' => 40];
$menuBtn = $parseMenuBtn($nameData, 'menu_button', $defaultMenuBtn);
$menuBtnMobile = $parseMenuBtn($nameData, 'menu_button_mobile', $menuBtn);
$menuBtnReserve = $menuBtn['left'] + $menuBtn['width'] + 12;
$menuBtnMobileReserve = $menuBtnMobile['left'] + $menuBtnMobile['width'] + 10;
$menuBtnClearTop = $menuBtn['top'] + $menuBtn['height'] + 12;
$menuBtnMobileClearTop = $menuBtnMobile['top'] + $menuBtnMobile['height'] + 12;
$sideRailWidth = 96;
$sideRailOffset = 28;
$sideRailGap = 16;
?>
<style>
    :root {
        --menu-button-reserve: <?php echo (int)$menuBtnReserve; ?>px;
        --menu-button-mobile-reserve: <?php echo (int)$menuBtnMobileReserve; ?>px;
        --menu-button-clear-top: <?php echo (int)$menuBtnClearTop; ?>px;
        --menu-button-mobile-clear-top: <?php echo (int)$menuBtnMobileClearTop; ?>px;
        --top-bar-offset: 12px;
        --top-bar-height: max(82px, calc(var(--menu-button-clear-top) + 18px));
        --side-rail-width: <?php echo (int)$sideRailWidth; ?>px;
        --side-rail-offset: <?php echo (int)$sideRailOffset; ?>px;
        --side-rail-gap: <?php echo (int)$sideRailGap; ?>px;
        --primary-color: <?php echo $color; ?>;
        --primary-soft: <?php echo $color; ?>18;
        --primary-border: <?php echo $color; ?>33;
        --transition-speed: 0.28s;
        --surface: <?php echo $theme === 'dark' ? 'rgba(30, 30, 32, 0.88)' : 'rgba(255, 255, 255, 0.82)'; ?>;
        --surface-strong: <?php echo $theme === 'dark' ? '#1c1c1e' : '#ffffff'; ?>;
        --surface-muted: <?php echo $theme === 'dark' ? 'rgba(44, 44, 46, 0.92)' : 'rgba(247, 249, 252, 0.95)'; ?>;
        --surface-2: <?php echo $theme === 'dark' ? '#2c2c2e' : '#f5f7fb'; ?>;
        --line: <?php echo $theme === 'dark' ? 'rgba(255,255,255,0.08)' : 'rgba(20,28,45,0.08)'; ?>;
        --text: <?php echo $theme === 'dark' ? '#f2f2f2' : '#172033'; ?>;
        --text-soft: <?php echo $theme === 'dark' ? '#98989e' : '#66758a'; ?>;
        --shadow: <?php echo $theme === 'dark' ? '0 24px 60px rgba(0,0,0,0.38)' : '0 24px 60px rgba(25,42,70,0.14)'; ?>;
        --modal-backdrop: rgba(7, 11, 18, 0.62);
    }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; overflow-x: clip; overflow-y: auto; min-height: 100%; }
    #mv-app-bg {
        position: fixed;
        inset: 0;
        z-index: -1;
        background-color: <?php echo $theme === 'dark' ? '#131313' : '#eef3f9'; ?>;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        opacity: 0;
        transition: opacity 0.25s ease;
    }
    #mv-app-bg.ready { opacity: 1; }
    body {
        font-family: "Avenir Next", "Segoe UI", system-ui, -apple-system, sans-serif;
        margin: 0;
        padding: calc(var(--top-bar-height) + 40px) calc(var(--side-rail-offset) + var(--side-rail-width) + var(--side-rail-gap) + 28px) 28px 28px;
        transition: background var(--transition-speed), color var(--transition-speed);
        background-image:
            radial-gradient(circle at top left, <?php echo $color; ?>20 0, transparent 24%),
            radial-gradient(circle at bottom right, <?php echo adjustBrightness($color, -15); ?>18 0, transparent 28%),
            <?php echo $theme === 'dark' ? 'linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55))' : 'linear-gradient(rgba(255,255,255,0.35), rgba(255,255,255,0.35))'; ?>;
        background-size: auto, auto, auto;
        background-position: 0 0, 0 0, 0 0;
        background-repeat: repeat, repeat, repeat;
        background-color: transparent;
        color: var(--text);
        min-height: 100%;
        display: flex;
        flex-direction: column;
        -webkit-tap-highlight-color: transparent;
        overflow-x: hidden;
    }
    button, input, select, textarea { font: inherit; }
    * {
        scrollbar-width: thin;
        scrollbar-color: <?php echo $theme === 'dark' ? '#48484a #1a1a1a' : '#b8c2cf #eef3f9'; ?>;
    }
    *::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }
    *::-webkit-scrollbar-track {
        background: <?php echo $theme === 'dark' ? '#1a1a1a' : '#eef3f9'; ?>;
    }
    *::-webkit-scrollbar-thumb {
        background: <?php echo $theme === 'dark' ? '#48484a' : '#b8c2cf'; ?>;
        border-radius: 999px;
        border: 2px solid <?php echo $theme === 'dark' ? '#1a1a1a' : '#eef3f9'; ?>;
    }
    *::-webkit-scrollbar-thumb:hover {
        background: <?php echo $theme === 'dark' ? '#636366' : '#9eacbc'; ?>;
    }
    svg { display: block; width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .container { max-width: 1440px; margin: 0 auto; width: 100%; position: relative; display: flex; flex-direction: column; }
    .header {
        display: flex;
        align-items: center;
        margin-bottom: 0;
        padding: 14px 36px 14px max(36px, var(--menu-button-reserve));
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 0 0 28px 28px;
        box-shadow: var(--shadow);
        backdrop-filter: blur(18px);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        min-height: var(--top-bar-height);
        max-height: var(--top-bar-height);
        z-index: 2400;
        overflow: hidden;
    }
    .header::after {
        content: "";
        position: absolute;
        inset: auto -8% -35% auto;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        background: radial-gradient(circle, <?php echo $color; ?>20 0%, transparent 68%);
        pointer-events: none;
    }
    .header-left { display: flex; flex-direction: column; justify-content: space-between; gap: 12px; position: relative; z-index: 1; }
    .app-mark { display: flex; align-items: center; gap: 14px; }
    .app-mark-icon {
        width: 46px;
        height: 46px;
        border-radius: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        background: linear-gradient(145deg, var(--primary-soft), rgba(255,255,255,0));
        border: 1px solid var(--primary-border);
    }
    .app-mark-copy { min-width: 0; }
    .app-kicker {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.14em;
        color: var(--text-soft);
        margin-bottom: 6px;
    }
    .header-left h1 { margin: 0; font-size: clamp(24px, 2.5vw, 34px); line-height: 1.02; }
    .header-description {
        margin: 0;
        max-width: 560px;
        color: var(--text-soft);
        font-size: 14px;
        line-height: 1.45;
    }
    .header-center { display: flex; flex-direction: column; justify-content: center; gap: 14px; position: relative; z-index: 1; }
    .main-summary {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 16px;
        width: 100%;
        margin-bottom: 24px;
        padding: 18px 22px;
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 24px;
        box-shadow: var(--shadow);
        backdrop-filter: blur(18px);
    }
    .header-description { flex: 1 1 240px; }
    .search-bar { flex: 1 1 300px; }
    .header-metrics { display: flex; gap: 10px; flex: 0 1 auto; }
    .search-bar { position: relative; width: 100%; margin: 0; }
    .mobile-menu-button {
        display: none;
        position: fixed;
        top: 20px;
        right: 20px;
        width: 52px;
        height: 52px;
        background: var(--surface);
        border: 1px solid var(--line);
        color: var(--text);
        cursor: pointer;
        padding: 0;
        border-radius: 16px;
        transition: transform var(--transition-speed), background var(--transition-speed), box-shadow var(--transition-speed);
        z-index: 4100;
        box-shadow: var(--shadow);
        backdrop-filter: blur(14px);
    }
    .mobile-menu-button:hover { transform: translateY(-2px); }
    .search-bar input {
        width: 100%;
        height: 52px;
        padding: 0 20px 0 56px;
        border: 1px solid var(--line);
        border-radius: 18px;
        font-size: 16px;
        background: var(--surface-strong);
        color: var(--text);
        transition: border-color var(--transition-speed), box-shadow var(--transition-speed), transform var(--transition-speed);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.02);
    }
    .search-bar input::placeholder { color: var(--text-soft); }
    .search-bar input:focus {
        outline: none;
        border-color: var(--primary-border);
        box-shadow: 0 0 0 5px var(--primary-soft);
        transform: translateY(-1px);
    }
    .search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-soft);
        width: 22px;
        height: 22px;
        pointer-events: none;
    }
    .metric-pill {
        min-width: 122px;
        padding: 10px 14px;
        border-radius: 16px;
        background: var(--surface-muted);
        border: 1px solid var(--line);
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .metric-value { font-size: 18px; font-weight: 700; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
    .metric-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-soft); }
    .button {
        padding: 12px 24px;
        background: linear-gradient(135deg, <?php echo $color; ?>c8 0%, <?php echo $color; ?>c8 35%, <?php echo adjustBrightness($color, -22); ?>c8 100%);
        color: white;
        border: none;
        border-radius: 14px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        transition: transform var(--transition-speed), box-shadow var(--transition-speed), opacity var(--transition-speed);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        box-shadow: 0 12px 24px <?php echo $color; ?>30;
    }
    .button:hover { transform: translateY(-2px); opacity: 0.96; }
    .button:active { transform: translateY(0); }
    .grid { display: grid; gap: 22px; margin: 16px 0 0; width: 100%; grid-template-columns: repeat(var(--grid-columns, 4), minmax(0, 1fr)); }
    .album-card {
        background: var(--surface);
        border-radius: 28px;
        overflow: hidden;
        transition: transform var(--transition-speed), box-shadow var(--transition-speed), border-color var(--transition-speed);
        cursor: pointer;
        box-shadow: 0 16px 34px rgba(12, 24, 44, 0.10);
        position: relative;
        width: 100%;
        aspect-ratio: 0.92;
        border: 1px solid var(--line);
        backdrop-filter: blur(14px);
        display: grid;
        grid-template-rows: minmax(0, 1fr) auto;
    }
    .album-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 26px 52px rgba(12, 24, 44, 0.18);
        border-color: var(--primary-border);
    }
    .album-card::after {
        content: "";
        position: absolute;
        inset: auto 0 0;
        height: 40%;
        background: linear-gradient(180deg, transparent 0%, rgba(10, 14, 20, 0.02) 30%, rgba(10, 14, 20, 0.14) 100%);
        pointer-events: none;
    }
    .album-topbar {
        position: absolute;
        top: 14px;
        left: 14px;
        right: 14px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        z-index: 2;
    }
    .album-chip {
        position: absolute;
        top: 14px;
        right: 14px;
        z-index: 2;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }
    .album-chip svg { width: 14px; height: 14px; }
    .album-chip-warning { background: rgba(255, 152, 0, 0.16); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.25); }
    .album-preview { width: 100%; height: 100%; min-height: 0; object-fit: cover; display: block; }
    .album-info {
        position: relative;
        z-index: 2;
        padding: 14px 16px 16px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 10px;
        background: linear-gradient(180deg, rgba(255,255,255,0.02) 0%, var(--surface) 18%);
    }
    .album-copy { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .album-title {
        font-weight: 700;
        margin: 0;
        color: var(--text);
        line-height: 1.28;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .album-open-indicator {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: var(--primary-color);
        background: var(--primary-soft);
        border: 1px solid var(--primary-border);
    }
    .album-open-indicator svg { width: 16px; height: 16px; }
    .album-stats { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; line-height: 1.25; color: var(--text-soft); min-width: 0; }
    .album-stat { display: inline-flex; align-items: center; gap: 7px; min-width: 0; max-width: 50%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .album-stat span { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
    .album-stat svg { width: 16px; height: 16px; flex-shrink: 0; }
    .modal { display: none; position: fixed; inset: 0; background: var(--modal-backdrop); z-index: 3500; justify-content: center; align-items: center; backdrop-filter: blur(10px); padding: 20px; }
    .modal-content { background: var(--surface-strong); border: 1px solid var(--line); border-radius: 24px; padding: 25px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; overflow-x: hidden; box-shadow: var(--shadow); clip-path: inset(0 round 24px); scrollbar-gutter: stable; }
    .modal-content--wide { max-width: 860px; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-title { font-size: 22px; font-weight: 700; color: var(--text); margin: 0; }
    .close-button { background: none; border: none; font-size: 28px; cursor: pointer; color: #e53935; transition: color var(--transition-speed), transform var(--transition-speed); padding: 5px; line-height: 1; }
    .close-button:hover { color: #b71c1c; transform: scale(1.15); }
    .form-group { margin-bottom: 18px; }
    .form-label { display: block; margin-bottom: 6px; font-weight: 600; color: var(--text); }
    .form-input { width: 100%; padding: 14px; border: 1px solid var(--line); border-radius: 14px; background: var(--surface-2); color: var(--text); font-size: 16px; transition: border-color var(--transition-speed), box-shadow var(--transition-speed); }
    .form-input:focus { outline: none; border-color: var(--primary-color); }
    .preview-upload { border: 2px dashed <?php echo $theme === 'dark' ? '#48484a' : '#ccc'; ?>; border-radius: 12px; padding: 25px; text-align: center; cursor: pointer; margin-top: 10px; transition: all var(--transition-speed); }
    .preview-upload:hover { border-color: var(--primary-color); background: <?php echo $color; ?>10; }
    .preview-image { max-width: 100%; max-height: 150px; margin-top: 10px; border-radius: 8px; display: none; }
    .frame-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: <?php echo $theme === 'dark' ? '#1c1c1e' : '#fff'; ?>; z-index: 6000; }
    .frame-header { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: var(--surface-2); border-bottom: 2px solid var(--primary-color); position: relative; flex-direction: row-reverse; }
    .frame-title { position: absolute; left: 50%; transform: translateX(-50%); color: var(--text); font-weight: 600; max-width: 70%; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .frame-close { background: none; border: none; font-size: 28px; cursor: pointer; color: var(--text); padding: 5px; line-height: 1; }
    .frame-content { width: 100%; height: calc(100% - 60px); border: none; }
    .pagination { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; margin: 30px 0; }
    .page-button { padding: 10px 16px; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; cursor: pointer; color: var(--text); transition: all var(--transition-speed); min-width: 44px; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06); }
    .page-button.active { background: <?php echo $color; ?>c8; color: white; }
    .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
    .setting-item { display: flex; flex-direction: column; }
    .setting-label { margin-bottom: 6px; font-weight: 600; color: var(--text); }
    .setting-input { padding: 10px; border: 1px solid var(--line); border-radius: 10px; background: var(--surface-2); color: var(--text); }
    .stats-container { background: var(--surface-muted); border-radius: 20px; padding: 20px; margin: 25px 0; border: 1px solid var(--line); }
    .icon-button {
        padding: 12px 14px;
        background: var(--surface-muted);
        border: 1px solid var(--line);
        border-radius: 16px;
        cursor: pointer;
        color: var(--text);
        transition: transform var(--transition-speed), border-color var(--transition-speed), background var(--transition-speed), color var(--transition-speed);
        display: flex;
        align-items: center;
        gap: 10px;
        white-space: nowrap;
    }
    .icon-button:hover { background: var(--primary-soft); border-color: var(--primary-border); color: var(--primary-color); transform: translateY(-1px); }
    .icon { width: 18px; height: 18px; flex-shrink: 0; }
    .rail-button .icon { width: 24px; height: 24px; }
    .glyph { width: 22px; height: 22px; margin: 0 auto; }
    .drop-area { border: 2px dashed var(--primary-color); border-radius: 18px; padding: 25px 15px; text-align: center; margin: 15px 0; background: <?php echo $color; ?>08; }
    .drag-text { color: var(--primary-color); font-weight: 600; margin-bottom: 8px; }
    .paste-button { margin-top: 12px; }
    .edit-album-grid {
        display: grid;
        grid-template-columns: minmax(240px, 0.8fr) minmax(320px, 1.2fr);
        gap: 22px;
        align-items: start;
    }
    .crop-panel {
        padding: 14px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface-muted);
    }
    .crop-canvas {
        width: 100%;
        aspect-ratio: 1.52;
        display: block;
        border-radius: 14px;
        background: var(--surface-2);
        border: 1px solid var(--line);
    }
    .crop-controls {
        display: grid;
        gap: 10px;
        margin-top: 12px;
    }
    .crop-control {
        display: grid;
        grid-template-columns: 54px 1fr;
        align-items: center;
        gap: 10px;
        color: var(--text-soft);
        font-size: 13px;
        font-weight: 600;
    }
    .crop-control input { width: 100%; }
    .crop-upload-button {
        width: 100%;
        margin-top: 14px;
    }
    /* ── Admin Panel ── */
    .modal-content--admin {
        max-width: 960px;
        padding: 0;
        max-height: calc(100vh - 40px);
        overflow: hidden;
        border-radius: 28px;
        clip-path: inset(0 round 28px);
        display: flex;
        flex-direction: column;
    }
    .modal-content--admin .modal-header {
        padding: 28px 32px 20px;
        margin: 0;
        border-bottom: 1px solid var(--line);
        flex-shrink: 0;
    }
    .admin-scroll-body {
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-gutter: stable;
    }
    .admin-modal-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff;
        font-size: 16px;
        margin-right: 12px;
        vertical-align: middle;
    }
    .admin-modal-user {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-soft);
        margin-left: 10px;
        padding: 3px 12px;
        border-radius: 999px;
        background: var(--surface-muted);
        border: 1px solid var(--line);
        vertical-align: middle;
    }
    .admin-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        padding: 20px 32px;
        background: var(--surface-muted);
        border-bottom: 1px solid var(--line);
    }
    .admin-stat-card {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        background: var(--surface-strong);
        border: 1px solid var(--line);
        border-radius: 16px;
        transition: transform var(--transition-speed), box-shadow var(--transition-speed);
    }
    .admin-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    }
    .admin-stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .admin-stat-icon--albums { background: var(--primary-soft); color: var(--primary-color); }
    .admin-stat-icon--visible { background: #4caf5020; color: #4CAF50; }
    .admin-stat-icon--hidden { background: #ff980020; color: #FF9800; }
    .admin-stat-icon--views { background: #2196f320; color: #2196F3; }
    .admin-stat-body { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .admin-stat-value {
        font-size: 22px;
        font-weight: 800;
        color: var(--text);
        line-height: 1.1;
    }
    .admin-stat-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-soft);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .admin-section {
        padding: 24px 32px 8px;
    }
    .admin-section:last-of-type {
        padding-bottom: 24px;
    }
    .admin-section-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 16px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .admin-section-title i {
        color: var(--primary-color);
        font-size: 14px;
    }
    .admin-table-wrap {
        border: 1px solid var(--line);
        border-radius: 16px;
        overflow: hidden;
    }
    .admin-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .admin-table thead {
        background: var(--surface-muted);
        border-bottom: 1px solid var(--line);
    }
    .admin-table th {
        padding: 14px 16px;
        font-weight: 700;
        color: var(--text-soft);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        text-align: left;
        white-space: nowrap;
    }
    .admin-th-actions { text-align: right; }
    .admin-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--line);
        color: var(--text);
    }
    .admin-table tbody tr:last-child td {
        border-bottom: none;
    }
    .admin-table tbody tr {
        transition: background var(--transition-speed);
    }
    .admin-table tbody tr:hover {
        background: <?php echo $theme === 'dark' ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.02)'; ?>;
    }
    .admin-row-hidden {
        opacity: 0.65;
    }
    .admin-td-name { max-width: 200px; }
    .admin-album-name {
        font-weight: 600;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .admin-td-views {
        font-weight: 600;
        color: var(--primary-color);
    }
    .admin-td-date {
        color: var(--text-soft);
        font-size: 13px;
    }
    .admin-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }
    .admin-status-badge--visible {
        background: #4caf5018;
        color: #4CAF50;
        border: 1px solid #4caf5030;
    }
    .admin-status-badge--hidden {
        background: #ff980018;
        color: #FF9800;
        border: 1px solid #ff980030;
    }
    .admin-empty {
        text-align: center;
        padding: 40px 20px !important;
        color: var(--text-soft);
    }
    .admin-empty i {
        font-size: 32px;
        display: block;
        margin-bottom: 10px;
        opacity: 0.4;
    }
    .action-buttons {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
    }
    .action-btn {
        background: none;
        border: 1px solid transparent;
        cursor: pointer;
        font-size: 14px;
        padding: 8px;
        border-radius: 10px;
        transition: all var(--transition-speed);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        min-height: 36px;
    }
    .action-btn:hover {
        transform: translateY(-1px);
        border-color: var(--line);
    }
    .edit-btn { color: #4CAF50; }
    .edit-btn:hover { background: #4CAF50; color: white; border-color: #4CAF50; }
    .delete-btn { color: #f44336; }
    .delete-btn:hover { background: #f44336; color: white; border-color: #f44336; }
    .hide-btn { color: #FF9800; }
    .hide-btn:hover { background: #FF9800; color: white; border-color: #FF9800; }
    .admin-actions-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        max-width: 620px;
    }
    .admin-action-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        background: var(--surface-muted);
        border: 1px solid var(--line);
        border-radius: 14px;
        cursor: pointer;
        transition: all var(--transition-speed);
        text-align: left;
        color: var(--text);
        font: inherit;
    }
    .admin-action-card:hover {
        background: var(--surface);
        border-color: var(--primary-border);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    }
    .admin-action-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }
    .admin-action-icon--export {
        background: var(--primary-soft);
        color: var(--primary-color);
    }
    .admin-action-icon--clear {
        background: #ff980020;
        color: #FF9800;
    }
    .admin-action-label {
        font-size: 14px;
        font-weight: 700;
    }
    .admin-action-desc {
        display: none;
        font-size: 12px;
        color: var(--text-soft);
        line-height: 1.4;
    }
    .modal--dialog { z-index: 7000; }
    .dialog-content {
        max-width: 420px;
        text-align: center;
        padding: 28px;
    }
    .dialog-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        margin: 0 auto 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        background: var(--primary-soft);
        border: 1px solid var(--primary-border);
        font-size: 20px;
    }
    .dialog-icon.dialog-icon--danger {
        color: #f44336;
        background: #f4433618;
        border-color: #f4433630;
    }
    .dialog-icon.dialog-icon--success {
        color: #4CAF50;
        background: #4caf5018;
        border-color: #4caf5030;
    }
    .dialog-title { margin-bottom: 8px; }
    .dialog-message {
        margin: 0;
        color: var(--text-soft);
        line-height: 1.5;
    }
    .dialog-actions {
        display: flex;
        gap: 10px;
        margin-top: 22px;
    }
    .dialog-actions .button {
        flex: 1;
        min-width: 0;
    }
    .dialog-cancel {
        background: var(--surface-2);
        color: var(--text);
        border: 1px solid var(--line);
        box-shadow: none;
    }
    .admin-footer {
        padding: 20px 32px 28px;
        border-top: 1px solid var(--line);
    }
    .admin-footer .button {
        width: 100%;
    }
    @media (max-width: 768px) {
        .modal {
            align-items: flex-start;
            overflow-y: auto;
        }
        .modal-content--admin .modal-header {
            padding: 20px 20px 16px;
        }
        .edit-album-grid {
            grid-template-columns: 1fr;
        }
        .admin-stats {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 16px 20px;
        }
        .admin-stat-card {
            padding: 12px;
        }
        .admin-stat-value {
            font-size: 18px;
        }
        .admin-section {
            padding: 16px 20px 4px;
        }
        .admin-section:last-of-type {
            padding-bottom: 16px;
        }
        .admin-actions-grid {
            grid-template-columns: 1fr;
        }
        .admin-table-wrap {
            overflow-x: auto;
            border-radius: 12px;
        }
        .admin-table {
            min-width: 600px;
        }
        .admin-table th,
        .admin-table td {
            padding: 10px 12px;
        }
        .admin-footer {
            padding: 16px 20px 20px;
        }
    }
    @media (max-width: 480px) {
        .admin-stats {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 12px 16px;
        }
        .admin-stat-card {
            padding: 10px;
        }
        .admin-section {
            padding: 12px 16px 4px;
        }
        .admin-section:last-of-type {
            padding-bottom: 12px;
        }
        .admin-footer {
            padding: 12px 16px 16px;
        }
        .admin-modal-user {
            display: none;
        }
    }
    /* ── Download Toast Notification ── */
    .download-toast {
        position: fixed;
        bottom: 32px;
        left: 50%;
        transform: translateX(-50%) translateY(120px);
        width: min(420px, calc(100vw - 40px));
        background: <?php echo $theme === 'dark' ? 'rgba(30, 30, 34, 0.92)' : 'rgba(255, 255, 255, 0.90)'; ?>;
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid var(--line);
        border-radius: 24px;
        padding: 20px 24px 18px;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.24);
        z-index: 5000;
        opacity: 0;
        transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.4s ease;
        pointer-events: none;
    }
    .download-toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
        pointer-events: auto;
    }
    .toast-grip {
        width: 36px;
        height: 4px;
        border-radius: 4px;
        background: var(--line);
        margin: 0 auto 14px;
    }
    .toast-header {
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }
    .toast-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: linear-gradient(135deg, <?php echo $color; ?>c8, <?php echo adjustBrightness($color, -20); ?>c8);
        color: #fff;
        font-size: 20px;
    }
    .toast-header-text {
        flex: 1;
        min-width: 0;
    }
    .toast-header-text h4 {
        margin: 2px 0 4px;
        font-size: 16px;
        font-weight: 700;
        color: var(--text);
        line-height: 1.3;
    }
    .toast-header-text p {
        margin: 0;
        font-size: 13px;
        color: var(--text-soft);
        line-height: 1.4;
    }
    .toast-close {
        background: none;
        border: none;
        color: var(--text-soft);
        font-size: 22px;
        cursor: pointer;
        padding: 0 0 0 8px;
        line-height: 1;
        transition: color var(--transition-speed), transform var(--transition-speed);
        flex-shrink: 0;
    }
    .toast-close:hover {
        color: #e53935;
        transform: scale(1.15);
    }
    .toast-divider {
        height: 1px;
        background: var(--line);
        margin: 16px 0 14px;
    }
    .toast-actions {
        display: flex;
        gap: 10px;
    }
    .toast-btn {
        flex: 1;
        padding: 12px 14px;
        border: none;
        border-radius: 14px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: transform var(--transition-speed), box-shadow var(--transition-speed), background var(--transition-speed);
    }
    .toast-btn:hover {
        transform: translateY(-2px);
    }
    .toast-btn:active {
        transform: translateY(0);
    }
    .toast-btn-primary {
        background: linear-gradient(135deg, <?php echo $color; ?>c8 0%, <?php echo adjustBrightness($color, -22); ?>c8 100%);
        color: white;
        box-shadow: 0 8px 20px <?php echo $color; ?>30;
    }
    .toast-btn-primary:hover {
        box-shadow: 0 12px 28px <?php echo $color; ?>40;
    }
    .toast-btn-secondary {
        background: var(--surface-muted);
        color: var(--text-soft);
        border: 1px solid var(--line);
    }
    .toast-btn-secondary:hover {
        background: var(--surface-2);
        color: var(--text);
    }
    @media (max-width: 480px) {
        .download-toast {
            bottom: 16px;
            padding: 16px 18px 14px;
            border-radius: 20px;
            width: calc(100vw - 32px);
        }
        .toast-header-text h4 { font-size: 15px; }
        .toast-btn { font-size: 13px; padding: 10px 12px; }
    }
    .install-modal { display: none; position: fixed; inset: 0; background: var(--modal-backdrop); z-index: 3000; padding: 20px; overflow-y: auto; backdrop-filter: blur(10px); }
    .install-header { display: flex; justify-content: center; align-items: center; position: relative; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid <?php echo $color; ?>; }
    .install-header .close-button { position: absolute; right: 0; top: 50%; transform: translateY(-50%); }
    .install-header .close-button:hover { transform: translateY(-50%) scale(1.15); }
    .install-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
    .install-card { background: var(--surface); border-radius: 20px; padding: 24px; text-align: center; transition: all var(--transition-speed); border: 1px solid var(--line); backdrop-filter: blur(12px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
    .install-card:hover { border-color: <?php echo $color; ?>; transform: translateY(-5px); cursor: pointer; }
    .install-card-hint {
        margin-top: 10px;
        font-size: 12px;
        color: var(--text-soft);
        opacity: 0.7;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: opacity var(--transition-speed);
    }
    .install-card:hover .install-card-hint { opacity: 1; }
    .install-detail {
        animation: installDetailFadeIn 0.3s ease;
    }
    @keyframes installDetailFadeIn {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .install-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background: var(--surface-muted);
        border: 1px solid var(--line);
        border-radius: 12px;
        color: var(--text);
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all var(--transition-speed);
        margin-bottom: 25px;
    }
    .install-back-btn:hover {
        background: var(--primary-soft);
        border-color: var(--primary-border);
        color: var(--primary-color);
        transform: translateX(-4px);
    }
    .install-detail-content {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 24px;
        padding: 36px;
        backdrop-filter: blur(12px);
        max-width: 640px;
        margin: 0 auto;
    }
    .install-detail-icon {
        font-size: 56px;
        color: <?php echo $color; ?>;
        text-align: center;
        margin-bottom: 16px;
    }
    .install-detail-title {
        font-size: 28px;
        font-weight: 700;
        color: var(--text);
        text-align: center;
        margin: 0 0 6px 0;
    }
    .install-detail-subtitle {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-soft);
        text-align: center;
        margin: 0 0 28px 0;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--line);
    }
    .install-steps {
        margin: 0 0 28px 0;
        padding: 0;
        list-style: none;
        counter-reset: step-counter;
    }
    .install-steps li {
        counter-increment: step-counter;
        padding: 14px 14px 14px 52px;
        position: relative;
        color: var(--text);
        font-size: 15px;
        line-height: 1.5;
        border-bottom: 1px solid var(--line);
        transition: background var(--transition-speed);
    }
    .install-steps li:last-child { border-bottom: none; }
    .install-steps li::before {
        content: counter(step-counter);
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: <?php echo $color; ?>20;
        color: <?php echo $color; ?>;
        font-weight: 700;
        font-size: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .install-steps li:hover {
        background: <?php echo $color; ?>08;
    }
    .install-detail-content .download-btn {
        display: block;
        width: 100%;
        text-align: center;
        padding: 14px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 14px;
        margin-top: 10px;
    }
    .install-icon { font-size: 42px; margin-bottom: 12px; color: <?php echo $color; ?>; }
    .install-title { font-size: 18px; font-weight: 600; margin: 0 0 8px 0; color: var(--text); }
    .install-desc { color: var(--text-soft); margin-bottom: 15px; font-size: 14px; }
    .download-btn { display: inline-block; padding: 10px 20px; background: <?php echo $color; ?>c8; color: white; text-decoration: none; border-radius: 8px; transition: all var(--transition-speed); font-size: 14px; backdrop-filter: blur(4px); }
    .download-btn:hover { background: <?php echo adjustBrightness($color, -10); ?>cc; transform: translateY(-2px); }
    .mobile-menu { display: block; position: fixed; top: var(--top-bar-height); right: 0; width: min(320px, 88vw); height: calc(100% - var(--top-bar-height)); background: var(--surface-strong); z-index: 4000; padding: 20px; box-shadow: -12px 0 30px rgba(0,0,0,0.18); transform: translateX(100%); transition: transform var(--transition-speed); overflow-y: auto; border-left: 1px solid var(--line); }
    .mobile-menu.active { transform: translateX(0); }
    .mobile-menu-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--line); }
    .mobile-menu-title { font-size: 20px; font-weight: 600; color: var(--primary-color); margin: 0; }
    .mobile-menu-close { background: none; border: none; font-size: 28px; color: var(--text); cursor: pointer; padding: 5px; line-height: 1; }
    .mobile-menu-content { display: flex; flex-direction: column; gap: 15px; }
    .side-rail {
        position: fixed;
        top: calc(var(--top-bar-height) + 16px);
        right: 28px;
        width: 96px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        z-index: 2300;
    }
    .rail-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 16px;
        border-radius: 26px;
        background: var(--surface);
        border: 1px solid var(--line);
        box-shadow: var(--shadow);
        backdrop-filter: blur(18px);
    }
    .rail-button {
        width: 100%;
        min-height: 56px;
        justify-content: center;
        border-radius: 18px;
    }
    .desktop-rail .rail-button span:last-child { display: none; }
    .rail-user-button {
        border: 1px solid var(--line);
        background: var(--surface);
        border-radius: 28px;
        padding: 14px 12px;
        color: var(--text);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        box-shadow: var(--shadow);
        backdrop-filter: blur(18px);
    }
    .rail-user-meta { display: flex; flex-direction: column; gap: 2px; align-items: center; min-width: 0; width: 100%; max-width: 100%; }
    .rail-user-name {
        max-width: 72px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .rail-user-role { font-size: 11px; color: var(--text-soft); text-transform: uppercase; letter-spacing: 0.06em; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .user-avatar {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        background: var(--primary-soft);
        border: 1px solid var(--primary-border);
        padding: 10px;
        overflow: hidden;
    }
    .user-avatar-img { width: calc(100% + 20px); height: calc(100% + 20px); margin: -10px; object-fit: cover; border-radius: 14px; }
    .user-summary-card {
        background: var(--surface-muted);
        border: 1px solid var(--line);
        padding: 16px;
        border-radius: 20px;
    }
    .user-summary-top { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .user-summary-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-soft); margin-bottom: 4px; }
    .user-summary-name { font-size: 18px; font-weight: 700; color: var(--text); }
    .user-summary-role { font-size: 14px; color: var(--text-soft); }
    .access-badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-top: 5px; }
    .access-guest { background: #757575; color: white; }
    .access-manager { background: #4CAF50; color: white; }
    .access-admin { background: #F44336; color: white; }
    @media (max-width: 1200px) {
        :root {
            --top-bar-height: max(82px, calc(var(--menu-button-clear-top) + 18px));
        }
        body {
            padding-bottom: 28px;
        }
        .grid { --grid-columns: 2 !important; gap: 18px; }
        .album-card { aspect-ratio: 1; min-height: 0; }
        .album-info { padding: 12px 14px 14px; }
        .album-title { font-size: 15px; }
        .album-stats { flex-direction: column; align-items: flex-start; gap: 4px; font-size: 12px; }
        .album-stat { max-width: 100%; }
        .album-open-indicator { width: 30px; height: 30px; border-radius: 10px; margin-left: 4px; }
    }
    @media (max-width: 768px) {
        :root {
            --top-bar-height: max(78px, calc(var(--menu-button-mobile-clear-top) + 18px));
        }
        body {
            padding: calc(var(--top-bar-height) + 16px) 16px 16px;
        }
        .desktop-rail { display: none; }
        .mobile-menu-button { display: block; }
        .header { padding: 12px 76px 12px max(22px, var(--menu-button-mobile-reserve)); margin-top: 0; }
        .header-description { max-width: none; }
        .main-summary { padding: 14px 16px; gap: 12px; }
        .grid { --grid-columns: 2 !important; gap: 16px; }
        .album-card { aspect-ratio: 1; min-height: 0; }
        .album-title { font-size: 15px; }
        .album-stats { flex-direction: column; align-items: flex-start; gap: 4px; font-size: 12px; }
        .album-stat { max-width: 100%; }
        .modal-content { padding: 20px; margin: 10px; }
        .admin-table { display: block; overflow-x: auto; }
        .install-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        :root {
            --top-bar-height: max(76px, calc(var(--menu-button-mobile-clear-top) + 18px));
        }
        .header { border-radius: 0 0 24px 24px; padding: 10px 76px 10px max(16px, var(--menu-button-mobile-reserve)); }
        .grid { --grid-columns: 2 !important; gap: 12px; }
        .album-card { border-radius: 22px; }
        .album-info { padding: 12px; }
        .album-title { font-size: 14px; -webkit-line-clamp: 1; }
        .album-stats { font-size: 11px; gap: 6px; }
        .album-open-indicator { width: 28px; height: 28px; }
        .modal-content { padding: 15px; }
        .modal-title { margin-left: auto; margin-right: 10px; font-size: 20px; }
        .button, .icon-button { padding: 10px 16px; font-size: 14px; }
        .search-bar input { height: 54px; padding: 0 15px 0 46px; font-size: 14px; }
        .search-icon { left: 12px; }
        .metric-pill { flex-basis: 100%; }
    }
    @media (max-width: 360px) {
        .header-left h1 { font-size: 24px; }
    }
</style>
