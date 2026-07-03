<?php
function renderMainPage($pagination) {
    global $lang, $theme, $color, $lang_array, $view_mode, $display_mode, $currentUser, $safeUsername, $page, $currentUserRole, $existingToken;
    $processed = isset($_GET['processed']) ? (int)$_GET['processed'] : 0;
    $favorites = getFavorites();
    $itemsPerPage = isset($_SESSION['settings'][$safeUsername]['items_per_page']) ? $_SESSION['settings'][$safeUsername]['items_per_page'] : 6;
    
    ob_start(); ?>
    <!DOCTYPE html>
    <html lang="<?= $lang ?>" data-theme="<?= $theme ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $lang_array['app_title'] ?></title>
        <link href="leaflet/leaflet.css" rel="stylesheet">
        <style data-page-style="route-page">
            :root { --primary-color: <?= $color ?>; --bg-color: <?= $theme === 'dark' ? '#1a1a1a' : '#ffffff' ?>; --text-color: <?= $theme === 'dark' ? '#ffffff' : '#000000' ?>; --card-bg: <?= $theme === 'dark' ? '#2d2d2d' : '#f5f5f5' ?>; --scrollbar-bg: <?= $theme === 'dark' ? '#2d2d2d' : '#f5f5f5' ?>; --scrollbar-thumb: <?= $theme === 'dark' ? '#555' : '#ccc' ?>; --radius-sm: 14px; --radius-md: 20px; --radius-lg: 28px; --glass-bg: <?= $theme === 'dark' ? 'rgba(255,255,255,0.08)' : 'rgba(255,255,255,0.58)' ?>; --glass-border: <?= $theme === 'dark' ? 'rgba(255,255,255,0.16)' : 'rgba(0,0,0,0.1)' ?>; }
            ::-webkit-scrollbar { width: 12px; height: 12px; }
            ::-webkit-scrollbar-track { background: var(--scrollbar-bg); border-radius: 6px; }
            ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 6px; border: 2px solid var(--scrollbar-bg); }
            ::-webkit-scrollbar-thumb:hover { background: var(--primary-color); }
            * { scrollbar-width: thin; scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-bg); }
            .osm-dark-tiles { filter: invert(1) hue-rotate(180deg) brightness(0.95) contrast(0.9); }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); transition: all 0.3s ease; overflow-x: hidden; }
            header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.25rem 2rem; background-color: var(--card-bg); margin: 12px 45px 2rem; position: relative; border-radius: var(--radius-lg); }
            .header-left { display: none; }
            .header-left h1 { margin: 0; font-size: 1.5rem; }
            .user-info { font-size: 0.9rem; color: var(--primary-color); background: var(--bg-color); padding: 0.3rem 0.8rem; border-radius: 20px; }
            .view-mode { display: flex; gap: 0.5rem; }
            .view-mode a { 
                text-decoration: none; 
                color: var(--text-color); 
                padding: 0.5rem 1rem; /* Увеличил горизонтальные отступы */
                border-radius: 4px; 
                font-size: 0.9rem;
                display: inline-flex; /* Изменено с block на inline-flex */
                align-items: center; /* Центрирование по вертикали */
                justify-content: center; /* Центрирование по горизонтали */
                min-width: 80px; /* Минимальная ширина для одинакового размера */
                text-align: center;
                line-height: 1.2; /* Для лучшего вертикального выравнивания */
            }
            .view-mode a.active { background-color: var(--primary-color); color: white; }
            .display-mode { display: flex; gap: 0.5rem; margin-left: 1rem; }
            .display-mode a {
                text-decoration: none;
                color: var(--text-color);
                padding: 0.5rem 1rem; /* Увеличил горизонтальный padding */
                border-radius: 4px;
                font-size: 0.9rem;
                display: flex;
                align-items: center; /* Центрирование по вертикали */
                justify-content: center; /* Центрирование по горизонтали */
                gap: 0.3rem;
                text-align: center;
                min-width: 80px; /* Минимальная ширина для одинакового размера */
            }
            .display-mode a.active { background-color: var(--primary-color); color: white; }
            .display-mode svg { width: 20px; height: 20px; stroke: currentColor; fill: none; }
            .controls { display: flex; gap: 1rem; align-items: center; width: 100%; }
            .search-container { display: flex; align-items: center; background: var(--bg-color); border-radius: var(--radius-md); padding: 0.85rem 1rem; border: 1px solid var(--glass-border); gap: 0.75rem; flex: 1; min-width: 0; box-shadow: inset 0 1px 0 rgba(255,255,255,0.12); }
            .search-container input { border: none; background: transparent; color: var(--text-color); outline: none; padding: 0; flex: 1; font-size: 1rem; }
            .search-icon { width: 16px; height: 16px; fill: #666; }
            .btn { padding: 0.5rem; border: none; border-radius: 999px; background-color: var(--primary-color); color: white; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: opacity 0.2s ease; width: 44px; height: 44px; }
            .btn:hover { opacity: 0.9; }
            .btn-icon { width: 20px; height: 20px; fill: currentColor; }
            .menu-toggle { flex-shrink: 0; background: var(--glass-bg); border: 1px solid var(--glass-border); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); }
            .menu-toggle img { width: 22px; height: 22px; display: block; }
            .side-menu-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.28); opacity: 0; pointer-events: none; transition: opacity 0.25s ease; z-index: 1200; }
            .side-menu { position: fixed; top: 0; right: 0; width: min(360px, 88vw); height: 100vh; background: <?= $theme === 'dark' ? 'linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(24,24,24,0.92) 18%, rgba(24,24,24,0.95) 100%)' : 'linear-gradient(180deg, rgba(255,255,255,0.92) 0%, rgba(248,248,248,0.86) 22%, rgba(243,243,243,0.92) 100%)' ?>; backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border-left: 1px solid var(--glass-border); box-shadow: -10px 0 40px rgba(0,0,0,0.18); transform: translateX(100%); transition: transform 0.28s ease; z-index: 1300; padding: calc(1.25rem + 45px) 1.25rem 1.25rem calc(1.25rem + 45px); display: flex; flex-direction: column; gap: 1rem; overflow-y: auto; overflow-x: hidden; }
            .side-menu.open { transform: translateX(0); }
            .side-menu-overlay.open { opacity: 1; pointer-events: auto; }
            body.menu-open { overflow: hidden; }
            .side-menu-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
            .side-menu-title { font-size: 1rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; opacity: 0.75; }
            .side-menu-close { background: transparent; border: 1px solid var(--glass-border); color: var(--text-color); width: 42px; height: 42px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
            .side-menu-close svg { width: 18px; height: 18px; stroke: currentColor; }
            .side-menu-section { display: flex; flex-direction: column; gap: 0.75rem; }
            .side-menu-label { font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.55; padding-left: 0.2rem; }
            .side-menu-buttons { display: flex; flex-direction: column; gap: 0.75rem; }
            .menu-switch { display: flex; flex-direction: column; gap: 0.55rem; }
            .menu-switch-title { font-size: 0.78rem; letter-spacing: 0.06em; text-transform: uppercase; opacity: 0.6; padding-left: 0.2rem; }
            .menu-switch-track { position: relative; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 4px; border-radius: 999px; background: <?= $theme === 'dark' ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)' ?>; border: 1px solid <?= $color ?>33; backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); box-shadow: inset 0 1px 0 rgba(255,255,255,0.06); overflow: hidden; }
            .menu-switch-indicator { position: absolute; top: 4px; left: 4px; width: calc(50% - 4px); height: calc(100% - 8px); border-radius: 999px; background: var(--primary-color); box-shadow: 0 10px 24px <?= $color ?>33; transition: transform 0.24s cubic-bezier(0.4, 0, 0.2, 1), background 0.24s ease, box-shadow 0.24s ease; z-index: 1; }
            .menu-switch.is-right .menu-switch-indicator { transform: translateX(100%); }
            .menu-switch-option { min-height: 48px; padding: 0.8rem 0.9rem; border-radius: 999px; border: none; color: <?= $theme === 'dark' ? 'rgba(255,255,255,0.72)' : 'rgba(0,0,0,0.62)' ?>; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; text-align: center; font-weight: 600; font-size: 0.92rem; background: transparent; position: relative; z-index: 2; transition: color 0.2s ease, transform 0.16s ease; }
            .menu-switch-option:hover { transform: translateY(-1px); }
            .menu-switch-option.active { color: <?= $theme === 'dark' ? '#081018' : '#ffffff' ?>; }
            .menu-switch-option:active { transform: scale(0.98); }
            .menu-action { width: 100%; min-height: 54px; padding: 0.9rem 1rem; border-radius: 22px; border: 1px solid var(--glass-border); background: var(--glass-bg); color: var(--text-color); text-decoration: none; display: inline-flex; align-items: center; gap: 0.85rem; backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease; box-shadow: inset 0 1px 0 rgba(255,255,255,0.08); }
            .menu-action:hover { transform: translateX(-3px); border-color: <?= $color ?>80; box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
            .menu-action.active { background: <?= $theme === 'dark' ? 'rgba(255,255,255,0.14)' : 'rgba(255,255,255,0.74)' ?>; border-color: <?= $color ?>99; color: var(--primary-color); }
            .menu-action.disabled { opacity: 0.45; pointer-events: none; }
            .menu-action svg { width: 20px; height: 20px; flex-shrink: 0; stroke: currentColor; fill: none; }
            .menu-action .fill-icon { fill: currentColor; stroke: none; }
            .menu-action span { font-size: 0.98rem; font-weight: 600; }
            .routes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem; padding: 1rem; }
            .route-card { background-color: var(--card-bg); border-radius: var(--radius-lg); overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: box-shadow 0.2s ease; position: relative; cursor: pointer; }
            .route-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,0.18); }
            .card-cb-wrap { position: absolute; top: 0.6rem; left: 0.6rem; z-index: 20; width: 26px; height: 26px; display: none; align-items: center; justify-content: center; }
            .routes-grid.select-mode .card-cb-wrap { display: flex; }
            .card-cb-box { width: 22px; height: 22px; border: 2.5px solid rgba(255,255,255,0.85); border-radius: 6px; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; transition: background 0.15s, border-color 0.15s; flex-shrink: 0; }
            .route-card.card-selected .card-cb-box { background: var(--primary-color); border-color: var(--primary-color); }
            .card-cb-box::after { content: ''; width: 5px; height: 9px; border: 2.5px solid white; border-top: none; border-left: none; transform: rotate(45deg) translate(-1px, -1px); display: none; }
            .route-card.card-selected .card-cb-box::after { display: block; }
            .route-card.card-selected { outline: 2.5px solid var(--primary-color); outline-offset: -2px; }
            .routes-grid.select-mode .route-card { cursor: default; }
            .select-bar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1500; background: var(--card-bg); border-top: 1px solid var(--glass-border); padding: 0.85rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; transform: translateY(100%); transition: transform 0.25s cubic-bezier(0.4,0,0.2,1); box-shadow: 0 -4px 20px rgba(0,0,0,0.15); }
            .select-bar.visible { transform: translateY(0); }
            .select-bar-left { display: flex; align-items: center; gap: 0.75rem; }
            .select-bar-count { font-weight: 700; font-size: 1rem; }
            .select-bar-clear { background: none; border: none; cursor: pointer; color: var(--text-color); opacity: 0.6; font-size: 0.88rem; padding: 0.3rem 0; }
            .select-bar-clear:hover { opacity: 1; }
            .select-bar .btn-del { background: #dc2626; color: white; border: none; padding: 0.6rem 1.1rem; border-radius: 999px; cursor: pointer; font-size: 0.9rem; font-weight: 700; }
            .select-bar .btn-del:disabled { opacity: 0.5; cursor: not-allowed; }
            .select-mode-btn { background: none; border: 1.5px solid var(--glass-border); border-radius: 999px; padding: 0.35rem 0.85rem; cursor: pointer; font-size: 0.85rem; color: var(--text-color); display: flex; align-items: center; gap: 0.4rem; transition: background 0.15s; white-space: nowrap; }
            .select-mode-btn:hover { background: var(--glass-border); }
            .select-mode-btn.active { background: var(--primary-color); color: white; border-color: var(--primary-color); }
            .select-mode-btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; flex-shrink: 0; }
            .route-card::after { content: '<?= $lang_array['right_click_tip'] ?>'; position: absolute; top: 0; left: 0; right: 0; background: rgba(0,0,0,0.8); color: white; padding: 0.5rem; font-size: 0.8rem; text-align: center; opacity: 0; transition: opacity 0.2s ease; pointer-events: none; }
            .route-card:hover::after { opacity: 1; }
            .route-preview { height: 200px; width: 100%; background-color: #ddd; position: relative; }
            .route-info { padding: 1rem; }
            .route-info h3 { margin-bottom: 0.5rem; font-size: 1.1rem; }
            .route-meta { display: flex; justify-content: space-between; font-size: 0.8rem; color: #666; margin-bottom: 1rem; }
            .route-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
            .download-btn { padding: 0.75rem 1.15rem; background: var(--primary-color); color: white; text-decoration: none; border-radius: 999px; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; border: none; cursor: pointer; }
            .view-icon { width: 16px; height: 16px; fill: currentColor; }
            .favorite-btn { padding: 0.5rem; background: none; border: none; cursor: pointer; }
            .favorite-btn.favorited svg { fill: #ffd700; }
            .favorite-btn svg { width: 20px; height: 20px; fill: #ccc; }
            .favorite-btn:hover svg { fill: #ffd700; }
            .stats-btn { padding: 0.5rem; background: none; border: none; cursor: pointer; color: var(--text-color); opacity: 0.55; transition: opacity 0.18s, color 0.18s; }
            .stats-btn:hover { opacity: 1; color: var(--primary-color); }
            .stats-btn svg { width: 20px; height: 20px; stroke: currentColor; fill: none; display: block; }
            .stats-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; }
            .stats-overlay.open { display: flex; }
            .stats-popup { background: var(--card-bg); border-radius: var(--radius-lg); box-shadow: 0 24px 60px rgba(0,0,0,0.28); max-width: 480px; width: 100%; max-height: 90vh; overflow-y: auto; position: relative; animation: statsPop 0.22s cubic-bezier(0.34,1.56,0.64,1); }
            @keyframes statsPop { from { opacity:0; transform:scale(0.88) translateY(12px); } to { opacity:1; transform:none; } }
            .stats-popup-header { padding: 1.1rem 1.25rem 0.85rem; border-bottom: 1px solid var(--glass-border); display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; }
            .stats-popup-title { font-size: 1.05rem; font-weight: 700; line-height: 1.3; }
            .stats-popup-close { background: transparent; border: none; color: var(--text-color); opacity: 0.55; cursor: pointer; padding: 0.2rem; flex-shrink: 0; }
            .stats-popup-close:hover { opacity: 1; }
            .stats-popup-close svg { width: 20px; height: 20px; stroke: currentColor; fill: none; display: block; }
            .stats-body { padding: 1rem 1.25rem 1.25rem; display: flex; flex-direction: column; gap: 0.5rem; }
            .stats-group-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.5; padding-top: 0.6rem; }
            .stats-row { display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem; padding: 0.3rem 0; border-bottom: 1px solid var(--glass-border); }
            .stats-row:last-child { border-bottom: none; }
            .stats-label { font-size: 0.88rem; opacity: 0.7; white-space: nowrap; }
            .stats-value { font-size: 0.92rem; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; }
            .pagination { display: flex; justify-content: center; gap: 0.5rem; padding: 1rem; }
            /* Стили для кнопок пагинации */
            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 15px;
                margin: 30px 0;
                flex-wrap: wrap;
            }

            .pagination-btn {
                padding: 10px 24px;
                background: <?php echo $theme === 'dark' ? '#444' : '#f0f0f0'; ?>;
                border: 2px solid <?php echo $theme === 'dark' ? '#555' : '#ddd'; ?>;
                border-radius: 12px;
                color: <?php echo $theme === 'dark' ? '#fff' : '#333'; ?>;
                text-decoration: none;
                font-weight: 600;
                transition: all var(--transition-speed);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 100px;
            }

            .pagination-btn:hover:not(.disabled) {
                background: var(--primary-color);
                border-color: var(--primary-color);
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px <?php echo $color; ?>40;
            }

            .pagination-btn.disabled {
                opacity: 0.5;
                cursor: not-allowed;
                pointer-events: none;
                background: <?php echo $theme === 'dark' ? '#333' : '#e0e0e0'; ?>;
                border-color: <?php echo $theme === 'dark' ? '#444' : '#ccc'; ?>;
            }

            .pagination span {
                padding: 10px 20px;
                background: <?php echo $theme === 'dark' ? '#333' : '#fff'; ?>;
                border-radius: 12px;
                color: <?php echo $theme === 'dark' ? '#fff' : '#333'; ?>;
                font-weight: 600;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .modal { display: none; position: fixed; inset: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.58); z-index: 1500; overflow-y: auto; }
            body.modal-open { overflow: hidden; }
            .modal-content { 
                background: var(--card-bg); 
                margin: 0; 
                padding: 2rem 2rem 2.5rem; 
                border-radius: 0; 
                max-width: none; 
                width: 100%; 
                min-height: 100vh; 
                max-height: none; 
                overflow-y: visible;
                position: relative;
            }
            @media (min-width: 640px) {
                .modal-content { max-width: 660px; margin: 2.5rem auto; min-height: auto; border-radius: var(--radius-lg); padding: 2.5rem 2.5rem 3rem; }
            }
            .modal-content h2 { margin-bottom: 1rem; }
            .modal-content form { display: flex; flex-direction: column; gap: 1rem; }
            .modal-content .btn { width: auto; min-width: 140px; }
            .modal-content .user-info-modal { background: var(--bg-color); padding: 0.75rem; border-radius: var(--radius-sm); margin-bottom: 1rem; text-align: center; color: var(--primary-color); font-weight: bold; }
            .modal-content code { border-radius: 16px; }
            #location-sharing-modal .modal-content { max-width: none !important; margin: 0 !important; min-height: 100vh; border-radius: 0; }
            #location-sharing-modal #location-map { height: min(55vh, 520px) !important; }
            .modal-close { position: absolute; top: 1.25rem; right: 1.25rem; width: 46px; height: 46px; min-width: 46px; padding: 0; border-radius: 999px; border: 2px solid rgba(220, 38, 38, 0.72); background: rgba(220, 38, 38, 0.08); color: #dc2626; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.14s ease, background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease; box-shadow: inset 0 1px 0 rgba(255,255,255,0.1); }
            .modal-close:hover { background: rgba(220, 38, 38, 0.14); border-color: rgba(220, 38, 38, 0.92); box-shadow: 0 10px 24px rgba(220, 38, 38, 0.16); }
            .modal-close:active { transform: scale(0.9) rotate(8deg); background: rgba(220, 38, 38, 0.2); }
            .modal-close svg { width: 18px; height: 18px; stroke: currentColor; }
            .settings-zone { border: 1px solid var(--glass-border); border-radius: var(--radius-sm); margin-top: 1.75rem; overflow: hidden; }
            .settings-zone-header { padding: 0.8rem 1.1rem; background: var(--glass-bg); border-bottom: 1px solid var(--glass-border); font-weight: 700; font-size: 0.88rem; letter-spacing: 0.04em; text-transform: uppercase; display: flex; align-items: center; gap: 0.5rem; opacity: 0.85; }
            .settings-zone-header svg { width: 15px; height: 15px; stroke: currentColor; fill: none; flex-shrink: 0; }
            .settings-zone-body { padding: 1.1rem; display: flex; flex-direction: column; gap: 0.85rem; }
            .btn-danger { background: rgba(220,38,38,0.07); border: 1px solid rgba(220,38,38,0.35); color: #dc2626; }
            .btn-danger:hover { background: rgba(220,38,38,0.14); border-color: rgba(220,38,38,0.65); }
            .download-menu { position: relative; display: inline-block; }
            .download-options { display: none; position: absolute; bottom: 100%; left: 0; background: var(--card-bg); border-radius: var(--radius-sm); box-shadow: 0 2px 10px rgba(0,0,0,0.2); padding: 0.5rem; min-width: 100px; z-index: 100; }
            .download-options a { display: block; padding: 0.5rem; color: var(--text-color); text-decoration: none; font-size: 0.9rem; border-radius: 12px; }
            .download-options a:hover { background: var(--primary-color); color: white; }
            .download-menu:hover .download-options { display: block; }
            .btn:disabled, 
            .btn.disabled {
                opacity: 0.5;
                cursor: not-allowed;
                pointer-events: none;
                background-color: #8c8c8c2d; /* Опционально: измените цвет фона */
            }
            .btn.disabled:hover {
                opacity: 0.5;
                transform: none;
                box-shadow: none;
            }
            
            @media (max-width: 768px) { 
                header { margin: 12px 45px 1.5rem; padding: 1rem; }
                .controls { gap: 0.75rem; }
                .search-container { width: 100%; }
                .side-menu { width: min(340px, 92vw); }
                .routes-grid { grid-template-columns: 1fr; gap: 1rem; padding: 0.5rem; }
                .pagination {
                    gap: 10px;
                }
                
                .pagination-btn {
                    padding: 8px 16px;
                    min-width: 80px;
                    font-size: 14px;
                }
                
                .pagination span {
                    padding: 8px 16px;
                    font-size: 14px;
                }
                .modal-content { padding: 1.5rem; }
            }
            @media (max-width: 480px) { 
                header { margin: 12px 45px 1rem; padding: 0.75rem; }
                .controls { gap: 0.5rem; }
                .btn { width: 36px; height: 36px; }
                .modal-content { padding: 1rem; }
                .modal-close { top: 1rem; right: 1rem; width: 42px; height: 42px; min-width: 42px; }
                .menu-action { min-height: 50px; border-radius: 18px; }
            }
            [data-theme="dark"] { --bg-color: #1a1a1a; --text-color: #ffffff; --card-bg: #2d2d2d; --scrollbar-bg: #2d2d2d; --scrollbar-thumb: #555; }
            [data-theme="light"] { --bg-color: #ffffff; --text-color: #000000; --card-bg: #f5f5f5; --scrollbar-bg: #f5f5f5; --scrollbar-thumb: #ccc; }
            /* Стили для полей ввода в модальном окне */
            .modal-content input[type="number"],
            .modal-content input[type="text"],
            .modal-content select {
                width: 100%;
                padding: 0.75rem;
                border: 2px solid var(--border-color, <?= $theme === 'dark' ? '#444' : '#ddd' ?>);
                border-radius: 18px;
                background-color: var(--bg-color);
                color: var(--text-color);
                font-size: 1rem;
                transition: all 0.3s ease;
                box-sizing: border-box;
            }

            .modal-content input[type="number"]:focus,
            .modal-content input[type="text"]:focus,
            .modal-content select:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px <?= $color ?>20;
            }

            /* Специфические стили для спиннера (стрелочек) */
            .modal-content input[type="number"] {
                -moz-appearance: textfield; /* Убирает стандартные стрелки в Firefox */
                appearance: textfield;
            }

            .modal-content input[type="number"]::-webkit-inner-spin-button,
            .modal-content input[type="number"]::-webkit-outer-spin-button {
                opacity: 0.8;
                height: 24px;
                background: var(--card-bg);
                border-left: 1px solid var(--border-color, <?= $theme === 'dark' ? '#444' : '#ddd' ?>);
                cursor: pointer;
            }

            .modal-content input[type="number"]::-webkit-inner-spin-button:hover,
            .modal-content input[type="number"]::-webkit-outer-spin-button:hover {
                background: var(--primary-color);
                opacity: 1;
            }

            /* Стили для группы полей ввода */
            .modal-content .form-group {
                margin-bottom: 1.5rem;
            }

            .modal-content .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: var(--text-color);
                font-size: 0.95rem;
            }

            /* Стили для кнопок в модальном окне */
            .modal-content .form-group .btn {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
                border: none;
                border-radius: 999px;
                background-color: var(--primary-color);
                color: white;
                cursor: pointer;
                transition: opacity 0.2s ease;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 100px;
                font-weight: 500;
            }

            .modal-content .form-group .btn:hover {
                opacity: 0.9;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px <?= $color ?>40;
            }

            .modal-content .form-group .btn:active {
                transform: translateY(0);
            }

            /* Modern Notification Styles */
            .notification-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
            }
            
            .notification {
                pointer-events: auto;
                background: var(--card-bg);
                border-radius: 12px;
                padding: 16px 20px;
                min-width: 320px;
                max-width: 450px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.3);
                display: flex;
                align-items: flex-start;
                gap: 12px;
                animation: slideInRight 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border-left: 4px solid var(--primary-color);
                transition: all 0.3s ease;
            }
            
            .notification:hover {
                transform: translateX(-4px);
                box-shadow: 0 12px 32px rgba(0,0,0,0.4);
            }
            
            .notification.notification-success {
                border-left-color: #10b981;
            }
            
            .notification.notification-error {
                border-left-color: #ef4444;
            }
            
            .notification.notification-warning {
                border-left-color: #f59e0b;
            }
            
            .notification.notification-info {
                border-left-color: #3b82f6;
            }
            
            .notification-icon {
                flex-shrink: 0;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 14px;
            }
            
            .notification-success .notification-icon {
                background: #10b98120;
                color: #10b981;
            }
            
            .notification-error .notification-icon {
                background: #ef444420;
                color: #ef4444;
            }
            
            .notification-content {
                flex: 1;
                min-width: 0;
            }
            
            .notification-title {
                font-weight: 600;
                font-size: 0.95rem;
                margin-bottom: 4px;
                color: var(--text-color);
            }
            
            .notification-message {
                font-size: 0.85rem;
                color: var(--text-color);
                opacity: 0.8;
                line-height: 1.4;
            }
            
            .notification-close {
                flex-shrink: 0;
                background: none;
                border: none;
                color: var(--text-color);
                opacity: 0.5;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .notification-close:hover {
                opacity: 1;
                background: rgba(0,0,0,0.1);
            }
            
            .notification.removing {
                animation: slideOutRight 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                opacity: 0;
                transform: translateX(100%);
            }
            
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100%);
                }
            }
            
            @media (max-width: 768px) {
                .notification-container {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                }
                
                .notification {
                    min-width: auto;
                    max-width: none;
                }
            }
        </style>
    </head>
    <body>
        <header>
            <div class="controls">
                <div class="search-container">
                    <svg class="search-icon" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="search-input" placeholder="<?= $lang_array['search_placeholder'] ?>">
                </div>
                <button class="select-mode-btn" id="select-mode-btn" type="button">
                    <svg viewBox="0 0 24 24" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <span id="select-mode-label"><?= $lang_array['select_mode'] ?></span>
                </button>
                <button class="btn menu-toggle" id="menu-toggle" type="button" aria-label="Open menu">
                    <img src="menu.svg" alt="Menu">
                </button>
            </div>
        </header>
        <div class="select-bar" id="select-bar">
            <div class="select-bar-left">
                <span class="select-bar-count" id="select-bar-count"></span>
                <button class="select-bar-clear" id="select-bar-clear"><?= $lang_array['clear_selection'] ?></button>
            </div>
            <button class="btn-del" id="select-bar-delete"><?= $lang_array['delete_selected'] ?></button>
        </div>
        <?php $hasRoutes = !empty(getRoutes()); ?>
        <div class="side-menu-overlay" id="side-menu-overlay"></div>
        <aside class="side-menu" id="side-menu" aria-hidden="true">
            <div class="side-menu-header">
                <div class="side-menu-title"><?= $lang_array['app_title'] ?></div>
                <button class="side-menu-close" id="side-menu-close" type="button" aria-label="Close menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="side-menu-section">
                <div class="side-menu-label">Navigation</div>
                <div class="side-menu-buttons">
                    <div class="menu-switch <?= $view_mode === 'favorites' ? 'is-right' : '' ?>">
                        <div class="menu-switch-title"><?= $lang_array['favorites'] ?></div>
                        <div class="menu-switch-track">
                            <div class="menu-switch-indicator"></div>
                            <a href="?page=1&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=all&display_mode=<?= $display_mode ?>" data-partial-nav="1" class="menu-switch-option <?= $view_mode === 'all' ? 'active' : '' ?>"><?= $lang_array['all_routes'] ?></a>
                            <a href="?page=1&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=favorites&display_mode=<?= $display_mode ?>" data-partial-nav="1" class="menu-switch-option <?= $view_mode === 'favorites' ? 'active' : '' ?>"><?= $lang_array['favorites'] ?></a>
                        </div>
                    </div>
                    <div class="menu-switch <?= $display_mode === 'table' ? 'is-right' : '' ?>">
                        <div class="menu-switch-title"><?= $lang_array['display_mode'] ?? 'Display' ?></div>
                        <div class="menu-switch-track">
                            <div class="menu-switch-indicator"></div>
                            <a href="?page=<?= $page ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=grid" data-partial-nav="1" class="menu-switch-option <?= $display_mode === 'grid' ? 'active' : '' ?>"><?= $lang_array['grid_view'] ?></a>
                            <a href="?page=<?= $page ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=table" data-partial-nav="1" class="menu-switch-option <?= $display_mode === 'table' ? 'active' : '' ?>"><?= $lang_array['table_view'] ?></a>
                        </div>
                    </div>
                    <?php if ($hasRoutes): ?>
                        <a href="?action=view_all_map&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="menu-action">
                            <svg viewBox="0 0 24 24" stroke-width="2"><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"></path><path d="M15 5.764v15"></path><path d="M9 3.236v15"></path></svg>
                            <span><?= $lang_array['view_all_map'] ?></span>
                        </a>
                    <?php else: ?>
                        <span class="menu-action disabled">
                            <svg viewBox="0 0 24 24" stroke-width="2"><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"></path><path d="M15 5.764v15"></path><path d="M9 3.236v15"></path></svg>
                            <span><?= $lang_array['view_all_map'] ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="side-menu-section">
                <div class="side-menu-label">Actions</div>
                <div class="side-menu-buttons">
                    <a href="?action=add&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="menu-action">
                        <svg viewBox="0 0 24 24" stroke-width="2"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>
                        <span><?= $lang_array['add_route'] ?></span>
                    </a>
                    <a href="?action=add_folder&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="menu-action">
                        <svg viewBox="0 0 24 24" stroke-width="2"><path d="M3 7h5l2 2h11v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><path d="M3 7a2 2 0 0 1 2-2h4"></path></svg>
                        <span><?= $lang_array['add_folder'] ?></span>
                    </a>
                    <button class="menu-action" id="location-sharing-btn" type="button">
                        <svg viewBox="0 0 24 24" stroke-width="2"><line x1="2" x2="5" y1="12" y2="12"></line><line x1="19" x2="22" y1="12" y2="12"></line><line x1="12" x2="12" y1="2" y2="5"></line><line x1="12" x2="12" y1="19" y2="22"></line><circle cx="12" cy="12" r="7"></circle><circle cx="12" cy="12" r="3"></circle></svg>
                        <span><?= $lang_array['my_location'] ?></span>
                    </button>
                    <button class="menu-action" id="settings-btn" type="button">
                        <svg viewBox="0 0 24 24" class="fill-icon"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"></path></svg>
                        <span><?= $lang_array['settings'] ?></span>
                    </button>
                    <a href="?action=api_docs&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="menu-action">
                        <svg viewBox="0 0 24 24" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        <span><?= $lang_array['api_docs'] ?></span>
                    </a>
                </div>
            </div>
        </aside>
        <main>
            <?php if ($processed > 0): ?>
                <p style="text-align: center; color: var(--primary-color);"><?= $processed ?> <?= $lang_array['add_route'] ?>(s) processed</p>
            <?php endif; ?>
            <div class="routes-grid">
                <?php foreach ($pagination['routes'] as $route): ?>
                    <div class="route-card" data-route-id="<?= $route['id'] ?>" data-view-url="?action=view&id=<?= $route['id'] ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>">
                        <div class="card-cb-wrap"><div class="card-cb-box"></div></div>
                        <div class="route-preview" id="map-<?= $route['id'] ?>"></div>
                        <div class="route-info">
                            <h3><?= htmlspecialchars($route['name']) ?></h3>
                            <div class="route-meta">
                                <span><?= $lang_array['file_size'] ?>: <?= formatFileSize($route['size']) ?></span>
                                <span><?= $lang_array['file_date'] ?>: <?= formatDate($route['date']) ?></span>
                            </div>
                            <div class="route-actions">
                                <div class="download-menu">
                                    <button class="download-btn"><svg class="view-icon" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg> <?= $lang_array['download'] ?></button>
                                    <div class="download-options">
                                        <a href="?download=kml&id=<?= $route['id'] ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>"><?= $lang_array['kml_format'] ?></a>
                                        <a href="?download=gpx&id=<?= $route['id'] ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>"><?= $lang_array['gpx_format'] ?></a>
                                        <a href="?download=csv&id=<?= $route['id'] ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>"><?= $lang_array['csv_format'] ?></a>
                                        <a href="?download=json&id=<?= $route['id'] ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>"><?= $lang_array['json_format'] ?></a>
                                    </div>
                                </div>
                                <button class="stats-btn" data-route-id="<?= $route['id'] ?>" title="<?= $lang_array['stats'] ?>"><svg viewBox="0 0 24 24" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg></button>
                                <button class="favorite-btn <?= in_array($route['id'], $favorites) ? 'favorited' : '' ?>" data-route-id="<?= $route['id'] ?>" title="<?= in_array($route['id'], $favorites) ? $lang_array['unmark_favorite'] : $lang_array['mark_favorite'] ?>"><svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="pagination">
                <a href="?page=<?= max(1, $pagination['current_page'] - 1) ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" data-partial-nav="1" class="pagination-btn <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>"><?= $lang_array['previous'] ?></a>
                <span><?= $lang_array['page'] ?> <?= $pagination['current_page'] ?> <?= $lang_array['of'] ?> <?= $pagination['total_pages'] ?></span>
                <a href="?page=<?= min($pagination['total_pages'], $pagination['current_page'] + 1) ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" data-partial-nav="1" class="pagination-btn <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>"><?= $lang_array['next'] ?></a>
            </div>
            <div class="stats-overlay" id="stats-overlay">
                <div class="stats-popup" id="stats-popup">
                    <div class="stats-popup-header">
                        <div class="stats-popup-title" id="stats-popup-title"></div>
                        <button class="stats-popup-close" id="stats-popup-close" aria-label="Close"><svg viewBox="0 0 24 24" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
                    </div>
                    <div class="stats-body" id="stats-body"></div>
                </div>
            </div>
            <div id="settings-modal" class="modal">
                <div class="modal-content">
                    <button type="button" class="modal-close" id="close-settings-modal" aria-label="Close modal">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <h2><?= $lang_array['settings'] ?></h2>
                    <div class="user-info-modal"><?= $lang_array['user_info'] ?><?= htmlspecialchars($currentUser) ?> <span style="font-size:0.8em;opacity:0.75;font-weight:normal;">(<?= htmlspecialchars([0=>'User', 1=>'Admin', 2=>'Root'][$currentUserRole] ?? 'User') ?>)</span></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        <div class="form-group">
                            <label for="items_per_page"><?= $lang_array['items_per_page'] ?>:</label>
                            <input type="number" id="items_per_page" name="items_per_page" value="<?= $itemsPerPage ?>" min="1" max="50">
                        </div>
                        <div class="form-group" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button type="submit" class="btn"><?= $lang_array['save'] ?></button>
                        </div>
                    </form>
                    
                    <div class="settings-zone">
                        <div class="settings-zone-header">
                            <svg viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="7"></circle><circle cx="12" cy="12" r="3"></circle><line x1="12" y1="2" x2="12" y2="5"></line><line x1="12" y1="19" x2="12" y2="22"></line><line x1="2" y1="12" x2="5" y2="12"></line><line x1="19" y1="12" x2="22" y2="12"></line></svg>
                            <?= $lang_array['location_sharing'] ?>
                        </div>
                        <div class="settings-zone-body">
                            <div id="settings-token-display" style="display: none;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;"><?= $lang_array['your_token'] ?></label>
                                <code id="settings-token-code" style="display: block; padding: 0.75rem 1rem; background: var(--bg-color); font-size: 0.8rem; word-break: break-all; border-radius: 16px; margin-bottom: 0.5rem;"></code>
                                <p style="font-size: 0.8rem; opacity: 0.7; margin-bottom: 0.75rem;"><?= $lang_array['token_expires'] ?>: <span id="settings-token-expires"></span></p>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <button class="btn" id="settings-copy-token-btn" style="min-width: auto; padding: 0.5rem 0.75rem; font-size: 0.85rem;"><?= $lang_array['copy_token'] ?></button>
                                    <button class="btn btn-danger" id="settings-revoke-token-btn" style="min-width: auto; padding: 0.5rem 0.75rem; font-size: 0.85rem;"><?= $lang_array['revoke_token'] ?></button>
                                </div>
                            </div>
                            <button class="btn" id="settings-generate-token-btn" style="width: 100%;"><?= $lang_array['generate_token'] ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="route-action-modal" class="modal">
                <div class="modal-content" style="max-width: 520px;">
                    <button type="button" class="modal-close" id="close-route-action-modal" aria-label="Close modal">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <h2><?= $lang_array['actions'] ?></h2>
                    <form id="route-action-form" style="display:flex;flex-direction:column;gap:0.9rem;">
                        <div class="form-group">
                            <label for="route-action-select"><?= $lang_array['actions'] ?></label>
                            <select id="route-action-select">
                                <option value="rename"><?= $lang_array['rename'] ?></option>
                                <option value="delete"><?= $lang_array['delete'] ?></option>
                            </select>
                        </div>
                        <div class="form-group" id="route-rename-group">
                            <label for="route-rename-input"><?= $lang_array['enter_name'] ?></label>
                            <input type="text" id="route-rename-input" maxlength="255" autocomplete="off">
                        </div>
                        <div class="form-group" style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:flex-end;">
                            <button type="button" class="btn" id="route-action-cancel-btn"><?= $lang_array['cancel'] ?></button>
                            <button type="submit" class="btn" id="route-action-submit-btn"><?= $lang_array['save'] ?></button>
                        </div>
                    </form>
                </div>
            </div>
            <div id="confirm-action-modal" class="modal">
                <div class="modal-content" style="max-width: 460px;">
                    <button type="button" class="modal-close" id="close-confirm-action-modal" aria-label="Close modal">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <h2><?= $lang_array['delete'] ?></h2>
                    <p id="confirm-action-message" style="margin:0.5rem 0 1.15rem 0;line-height:1.45;"><?= $lang_array['confirm_delete'] ?></p>
                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:flex-end;">
                        <button type="button" class="btn" id="confirm-action-cancel-btn"><?= $lang_array['cancel'] ?></button>
                        <button type="button" class="btn btn-danger" id="confirm-action-ok-btn"><?= $lang_array['delete'] ?></button>
                    </div>
                </div>
            </div>
            <div id="location-sharing-modal" class="modal">
                <div class="modal-content">
                    <button type="button" class="modal-close" id="close-location-modal" aria-label="Close modal">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <h2><?= $lang_array['location_sharing'] ?></h2>
                    <div class="user-info-modal"><?= $lang_array['your_token'] ?>: <?= $lang_array['settings'] ?></div>
                    <div id="location-section">
                        <h3><?= $lang_array['last_coordinates'] ?></h3>
                        <div id="location-map" style="width: 100%; height: 300px; border-radius: 24px; margin-bottom: 1rem; display: none;"></div>
                        <div id="location-data" style="display: none;">
                            <div style="border:1px solid var(--glass-border);border-radius:16px;overflow:hidden;background:var(--card-bg);">
                                <table style="width:100%;border-collapse:collapse;">
                                    <tbody>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['latitude'] ?></td><td id="loc-lat" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;font-variant-numeric:tabular-nums;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['longitude'] ?></td><td id="loc-lon" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;font-variant-numeric:tabular-nums;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['accuracy'] ?></td><td id="loc-accuracy" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['device'] ?></td><td id="loc-device" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['speed'] ?></td><td id="loc-speed" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['altitude'] ?></td><td id="loc-altitude" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['satellites'] ?></td><td id="loc-satellites" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;"><?= $lang_array['timestamp'] ?></td><td id="loc-timestamp" style="padding:0.7rem 0.9rem;text-align:right;"></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div id="loc-telemetry-wrap" style="display: none; margin-top: 1rem;">
                                <p><strong><?= $lang_array['telemetry'] ?>:</strong></p>
                                <div id="loc-telemetry" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.5rem;"></div>
                            </div>
                            <div id="loc-history-wrap" style="display: none; margin-top: 1rem;">
                                <p><strong><?= $lang_array['previous_messages'] ?>:</strong></p>
                                <div id="loc-history-nav" style="display:flex;justify-content:center;align-items:center;gap:0.5rem;margin:0.75rem auto 0.95rem;padding:0.7rem 0.9rem;flex-wrap:wrap;background:var(--card-bg);border:1px solid var(--glass-border);border-radius:14px;max-width:max-content;">
                                    <button type="button" class="btn" id="loc-history-prev" style="min-width: auto; padding: 0.45rem 0.8rem;"><?= $lang_array['previous'] ?></button>
                                    <span id="loc-history-page" style="opacity: 0.75;">1 / 1</span>
                                    <button type="button" class="btn" id="loc-history-next" style="min-width: auto; padding: 0.45rem 0.8rem;"><?= $lang_array['next'] ?></button>
                                    <button type="button" class="btn" id="loc-history-latest" style="min-width: auto; padding: 0.45rem 0.8rem;"><?= $lang_array['last_coordinates'] ?></button>
                                </div>
                            </div>
                        </div>
                        <div id="location-photos" style="display: none; margin-top: 1rem;">
                            <h4><?= $lang_array['location_photos'] ?></h4>
                            <div id="photos-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.5rem; margin-top: 0.5rem;"></div>
                        </div>
                        <p id="no-location-msg" style="text-align: center; opacity: 0.6; padding: 2rem;"><?= $lang_array['no_location_data'] ?></p>
                    </div>
                    
                    <div class="form-group" style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem;">
                        <button type="button" class="btn" id="open-settings-from-location"><?= $lang_array['settings'] ?></button>
                    </div>
                </div>
            </div>
        </main>
        <div id="notification-container" class="notification-container"></div>
        <script src="leaflet/leaflet.js"></script>
        <script>
            window.loadRouteViewPartial = window.loadRouteViewPartial || async function(url, pushState = true) {
                const menuWasOpen = document.body.classList.contains('menu-open') || !!document.querySelector('#side-menu.open');
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'fetch'
                    }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const html = await response.text();
                const parser = new DOMParser();
                const nextDoc = parser.parseFromString(html, 'text/html');
                const nextStyle = nextDoc.querySelector('style[data-page-style="route-page"]');
                const currentStyle = document.querySelector('style[data-page-style="route-page"]');
                if (nextStyle && currentStyle) {
                    currentStyle.textContent = nextStyle.textContent;
                }

                document.title = nextDoc.title || document.title;
                document.body.innerHTML = nextDoc.body.innerHTML;

                if (menuWasOpen) {
                    document.body.classList.add('menu-open');
                    const sideMenu = document.getElementById('side-menu');
                    const sideMenuOverlay = document.getElementById('side-menu-overlay');
                    if (sideMenu) {
                        sideMenu.classList.add('open');
                        sideMenu.setAttribute('aria-hidden', 'false');
                    }
                    if (sideMenuOverlay) {
                        sideMenuOverlay.classList.add('open');
                    }
                    sessionStorage.setItem('keepSideMenuOpen', '1');
                }

                if (pushState) {
                    window.history.pushState({}, '', url);
                }

                document.dispatchEvent(new Event('DOMContentLoaded'));
            };

            window.bindPartialNavigation = window.bindPartialNavigation || function() {
                if (window.__mapPartialNavBound) return;
                window.__mapPartialNavBound = true;

                document.addEventListener('click', async (event) => {
                    const link = event.target.closest('a[data-partial-nav="1"]');
                    if (!link || link.classList.contains('disabled')) return;
                    event.preventDefault();
                    try {
                        await window.loadRouteViewPartial(link.href, true);
                    } catch (error) {
                        console.error('Partial navigation failed:', error);
                        window.location.href = link.href;
                    }
                });

                window.addEventListener('popstate', () => {
                    window.location.reload();
                });
            };

            window.bindPartialNavigation();

            const langTexts = <?= json_encode($lang_array) ?>;
            const routes = <?= json_encode($pagination['routes']) ?>;
            const existingToken = <?= json_encode($existingToken ?: null) ?>;
            
            document.addEventListener('DOMContentLoaded', () => {
                const menuToggle = document.getElementById('menu-toggle');
                const sideMenu = document.getElementById('side-menu');
                const sideMenuOverlay = document.getElementById('side-menu-overlay');
                const sideMenuClose = document.getElementById('side-menu-close');

                const openSideMenu = () => {
                    document.body.classList.add('menu-open');
                    sideMenu.classList.add('open');
                    sideMenuOverlay.classList.add('open');
                    sideMenu.setAttribute('aria-hidden', 'false');
                };

                const closeSideMenu = () => {
                    document.body.classList.remove('menu-open');
                    sideMenu.classList.remove('open');
                    sideMenuOverlay.classList.remove('open');
                    sideMenu.setAttribute('aria-hidden', 'true');
                    sessionStorage.removeItem('keepSideMenuOpen');
                };

                menuToggle.addEventListener('click', openSideMenu);
                sideMenuClose.addEventListener('click', closeSideMenu);
                sideMenuOverlay.addEventListener('click', closeSideMenu);
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        closeSideMenu();
                    }
                });

                if (sessionStorage.getItem('keepSideMenuOpen') === '1') {
                    openSideMenu();
                }

                sideMenu.querySelectorAll('.menu-switch-option').forEach(element => {
                    element.addEventListener('click', () => {
                        sessionStorage.setItem('keepSideMenuOpen', '1');
                    });
                });

                sideMenu.querySelectorAll('a, button').forEach(element => {
                    if (element.id !== 'side-menu-close') {
                        element.addEventListener('click', () => {
                            if (!element.classList.contains('disabled') && !element.classList.contains('menu-switch-option')) {
                                closeSideMenu();
                            }
                        });
                    }
                });

                routes.forEach(route => {
                    if (route.points && route.points.length > 0) {
                        const mapContainer = document.getElementById(`map-${route.id}`);
                        if (!mapContainer || mapContainer.dataset.mapReady === '1') return;
                        const map = L.map(mapContainer, { attributionControl: false, zoomControl: false, dragging: false, doubleClickZoom: false, boxZoom: false, scrollWheelZoom: false }).setView(route.points[0], 14);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'<?= $theme === 'dark' ? ", { className: 'osm-dark-tiles' }" : '' ?>).addTo(map);
                        L.polyline(route.points, { color: '<?= $color ?>', weight: 4, opacity: 0.9, smoothFactor: 1 }).addTo(map);
                        L.marker(route.points[0], { icon: L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [15, 25], iconAnchor: [7, 25], popupAnchor: [1, -24], shadowSize: [25, 25] }) }).addTo(map);
                        L.marker(route.points[route.points.length - 1], { icon: L.icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [15, 25], iconAnchor: [7, 25], popupAnchor: [1, -24], shadowSize: [25, 25] }) }).addTo(map);
                        map.fitBounds(L.polyline(route.points).getBounds());
                        mapContainer.dataset.mapReady = '1';
                    }
                });

                // ─── Cross-page selection ────────────────────────────────────
                const SEL_KEY = 'selectedRoutes';
                const SELMODE_KEY = 'selectModeActive';
                const langSel = {
                    select: <?= json_encode($lang_array['select_mode']) ?>,
                    exit:   <?= json_encode($lang_array['exit_select']) ?>,
                    count:  <?= json_encode($lang_array['selected_count']) ?>,
                    confirmMany: <?= json_encode($lang_array['confirm_delete_many']) ?>
                };

                function getSelected() {
                    try { return JSON.parse(sessionStorage.getItem(SEL_KEY) || '[]'); } catch(e) { return []; }
                }
                function setSelected(arr) { sessionStorage.setItem(SEL_KEY, JSON.stringify(arr)); }
                function clearSelected() { sessionStorage.removeItem(SEL_KEY); }
                function isSelectMode() { return sessionStorage.getItem(SELMODE_KEY) === '1'; }
                function setSelectMode(on) { on ? sessionStorage.setItem(SELMODE_KEY, '1') : sessionStorage.removeItem(SELMODE_KEY); }

                const grid = document.querySelector('.routes-grid');
                const selectBtn = document.getElementById('select-mode-btn');
                const selectLabel = document.getElementById('select-mode-label');
                const selBar = document.getElementById('select-bar');
                const selCount = document.getElementById('select-bar-count');
                const selClear = document.getElementById('select-bar-clear');
                const selDelete = document.getElementById('select-bar-delete');

                function syncUI() {
                    const sel = getSelected();
                    const n = sel.length;
                    selCount.textContent = n + ' ' + langSel.count;
                    selBar.classList.toggle('visible', n > 0);
                    selDelete.disabled = n === 0;
                    document.querySelectorAll('.route-card').forEach(card => {
                        const id = card.dataset.routeId;
                        card.classList.toggle('card-selected', sel.includes(id));
                    });
                    const mode = isSelectMode();
                    selectBtn.classList.toggle('active', mode);
                    selectLabel.textContent = mode ? langSel.exit : langSel.select;
                    if (grid) grid.classList.toggle('select-mode', mode);
                }

                function toggleCard(card) {
                    const id = card.dataset.routeId;
                    let sel = getSelected();
                    const idx = sel.indexOf(id);
                    if (idx === -1) sel.push(id); else sel.splice(idx, 1);
                    setSelected(sel);
                    syncUI();
                }

                if (selectBtn) {
                    selectBtn.addEventListener('click', () => {
                        const nowOn = !isSelectMode();
                        setSelectMode(nowOn);
                        if (!nowOn) clearSelected();
                        syncUI();
                    });
                }

                if (selClear) selClear.addEventListener('click', () => { clearSelected(); syncUI(); });

                if (selDelete) selDelete.addEventListener('click', async () => {
                    const sel = getSelected();
                    if (!sel.length) return;
                    const msg = langSel.confirmMany.replace('{n}', sel.length);
                    if (!confirm(msg)) return;
                    selDelete.disabled = true;
                    for (const id of sel) {
                        await fetch(`/map/api/route/${id}`, { method: 'DELETE' }).catch(() => {});
                        deleteRoute(id);
                    }
                    clearSelected();
                    setSelectMode(false);
                    location.reload();
                });

                const routeActionModal=document.getElementById('route-action-modal');
                const routeActionForm=document.getElementById('route-action-form');
                const routeActionSelect=document.getElementById('route-action-select');
                const routeRenameGroup=document.getElementById('route-rename-group');
                const routeRenameInput=document.getElementById('route-rename-input');
                const confirmActionModal=document.getElementById('confirm-action-modal');
                const confirmActionMessage=document.getElementById('confirm-action-message');
                let pendingRouteActionId='';
                let pendingConfirmResolve=null;
                function toggleRenameField(){
                    const isRename=routeActionSelect.value==='rename';
                    routeRenameGroup.style.display=isRename?'':'none';
                    routeRenameInput.required=isRename;
                    if(isRename)routeRenameInput.focus();
                }
                function openRouteActionModal(routeId){
                    pendingRouteActionId=routeId;
                    routeActionSelect.value='rename';
                    routeRenameInput.value='';
                    toggleRenameField();
                    openModal('route-action-modal');
                }
                function closeRouteActionModal(){
                    closeModal('route-action-modal');
                }
                function askDeleteConfirm(message){
                    confirmActionMessage.textContent=message||langTexts.confirm_delete;
                    openModal('confirm-action-modal');
                    return new Promise(resolve=>{pendingConfirmResolve=resolve;});
                }
                function resolveDeleteConfirm(result){
                    closeModal('confirm-action-modal');
                    if(pendingConfirmResolve){pendingConfirmResolve(result);pendingConfirmResolve=null;}
                }
                routeActionSelect.addEventListener('change',toggleRenameField);
                document.getElementById('close-route-action-modal').addEventListener('click',closeRouteActionModal);
                document.getElementById('route-action-cancel-btn').addEventListener('click',closeRouteActionModal);
                routeActionForm.addEventListener('submit',async(e)=>{
                    e.preventDefault();
                    if(!pendingRouteActionId)return;
                    if(routeActionSelect.value==='delete'){
                        closeRouteActionModal();
                        const ok=await askDeleteConfirm(langTexts.confirm_delete);
                        if(ok)deleteRoute(pendingRouteActionId);
                        return;
                    }
                    const newName=routeRenameInput.value.trim();
                    if(!newName)return;
                    closeRouteActionModal();
                    renameRoute(pendingRouteActionId,newName);
                });
                document.getElementById('close-confirm-action-modal').addEventListener('click',()=>resolveDeleteConfirm(false));
                document.getElementById('confirm-action-cancel-btn').addEventListener('click',()=>resolveDeleteConfirm(false));
                document.getElementById('confirm-action-ok-btn').addEventListener('click',()=>resolveDeleteConfirm(true));
                confirmActionModal.addEventListener('click',(e)=>{if(e.target===confirmActionModal)resolveDeleteConfirm(false);});
                routeActionModal.addEventListener('click',(e)=>{if(e.target===routeActionModal)closeRouteActionModal();});
                document.addEventListener('keydown',(e)=>{
                    if(e.key!=='Escape')return;
                    if(routeActionModal.style.display==='block')closeRouteActionModal();
                    if(confirmActionModal.style.display==='block')resolveDeleteConfirm(false);
                });
                syncUI();
                // ─────────────────────────────────────────────────────────────

                document.querySelectorAll('.route-card').forEach(card => {
                    card.addEventListener('click', (e) => {
                        if (isSelectMode()) {
                            if (!e.target.closest('.favorite-btn, .stats-btn')) toggleCard(card);
                            return;
                        }
                        if (!e.target.closest('.download-menu, .favorite-btn, .stats-btn')) {
                            window.location.href = card.dataset.viewUrl;
                        }
                    });
                    card.addEventListener('contextmenu', (e) => {
                        if (isSelectMode()) return;
                        e.preventDefault();
                        openRouteActionModal(card.dataset.routeId);
                    });
                });

                document.querySelectorAll('.favorite-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const routeId = btn.dataset.routeId;
                        toggleFavorite(routeId, btn);
                    });
                });

                (function() {
                    function haversine(lat1, lon1, lat2, lon2) {
                        const R = 6371, toRad = x => x * Math.PI / 180;
                        const dLat = toRad(lat2 - lat1), dLon = toRad(lon2 - lon1);
                        const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;
                        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                    }
                    function fmtDist(km) { return km < 1 ? (km*1000).toFixed(0)+' m' : km.toFixed(2)+' km'; }
                    function fmtCoord(v) { return v.toFixed(5); }
                    function computeStats(pts) {
                        const n = pts.length;
                        let dist = 0, minLat = pts[0][0], maxLat = pts[0][0], minLon = pts[0][1], maxLon = pts[0][1], sumLat = 0, sumLon = 0;
                        for (let i = 0; i < n; i++) {
                            sumLat += pts[i][0]; sumLon += pts[i][1];
                            if (pts[i][0] < minLat) minLat = pts[i][0]; if (pts[i][0] > maxLat) maxLat = pts[i][0];
                            if (pts[i][1] < minLon) minLon = pts[i][1]; if (pts[i][1] > maxLon) maxLon = pts[i][1];
                            if (i > 0) dist += haversine(pts[i-1][0], pts[i-1][1], pts[i][0], pts[i][1]);
                        }
                        return { n, dist, straight: haversine(pts[0][0], pts[0][1], pts[n-1][0], pts[n-1][1]),
                            avgStep: n > 1 ? dist/(n-1)*1000 : 0,
                            minLat, maxLat, minLon, maxLon,
                            centerLat: sumLat/n, centerLon: sumLon/n,
                            spanNS: haversine(minLat, minLon, maxLat, minLon),
                            spanEW: haversine(minLat, minLon, minLat, maxLon) };
                    }
                    function row(label, value) { return `<div class="stats-row"><span class="stats-label">${label}</span><span class="stats-value">${value}</span></div>`; }
                    function group(label) { return `<div class="stats-group-label">${label}</div>`; }
                    const overlay = document.getElementById('stats-overlay');
                    const popup = document.getElementById('stats-popup');
                    const titleEl = document.getElementById('stats-popup-title');
                    const bodyEl = document.getElementById('stats-body');
                    function openStats(routeId) {
                        const route = routes.find(r => r.id == routeId);
                        if (!route || !route.points || !route.points.length) return;
                        const s = computeStats(route.points);
                        titleEl.textContent = route.name;
                        const fileSizeStr = route.size < 1024 ? route.size+' B' : route.size < 1048576 ? (route.size/1024).toFixed(1)+' KB' : (route.size/1048576).toFixed(2)+' MB';
                        const dateStr = new Date(route.date * 1000).toLocaleDateString();
                        bodyEl.innerHTML =
                            group('<?= $lang_array['file_size'] ?> / <?= $lang_array['file_date'] ?>') +
                            row('<?= $lang_array['file_size'] ?>', fileSizeStr) +
                            row('<?= $lang_array['file_date'] ?>', dateStr) +
                            row('<?= $lang_array['points_count'] ?>', s.n.toLocaleString()) +
                            group('<?= $lang_array['total_distance'] ?>') +
                            row('<?= $lang_array['total_distance'] ?>', fmtDist(s.dist)) +
                            row('<?= $lang_array['straight_distance'] ?>', fmtDist(s.straight)) +
                            row('<?= $lang_array['avg_step'] ?>', s.avgStep.toFixed(1)+' m') +
                            group('<?= $lang_array['coverage'] ?>') +
                            row('<?= $lang_array['coverage'] ?>', fmtDist(s.spanNS)+' × '+fmtDist(s.spanEW)) +
                            row('<?= $lang_array['start_point'] ?>', fmtCoord(route.points[0][0])+', '+fmtCoord(route.points[0][1])) +
                            row('<?= $lang_array['end_point'] ?>', fmtCoord(route.points[route.points.length-1][0])+', '+fmtCoord(route.points[route.points.length-1][1])) +
                            row('<?= $lang_array['center_point'] ?>', fmtCoord(s.centerLat)+', '+fmtCoord(s.centerLon));
                        overlay.classList.add('open');
                        document.body.classList.add('modal-open');
                    }
                    function closeStats() {
                        overlay.classList.remove('open');
                        document.body.classList.remove('modal-open');
                    }
                    document.querySelectorAll('.stats-btn').forEach(btn => {
                        btn.addEventListener('click', e => { e.stopPropagation(); openStats(btn.dataset.routeId); });
                    });
                    document.getElementById('stats-popup-close').addEventListener('click', closeStats);
                    overlay.addEventListener('click', e => { if (e.target === overlay) closeStats(); });
                    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeStats(); });
                })();

                window.openModal = window.openModal || function(id) {
                    document.getElementById(id).style.display = 'block';
                    document.body.classList.add('modal-open');
                };
                window.closeModal = window.closeModal || function(id) {
                    document.getElementById(id).style.display = 'none';
                    if (!Array.from(document.querySelectorAll('.modal')).some(m => m.style.display === 'block')) {
                        document.body.classList.remove('modal-open');
                    }
                };

                document.getElementById('settings-btn').addEventListener('click', () => {
                    closeSideMenu();
                    openModal('settings-modal');
                });

                document.getElementById('close-settings-modal').addEventListener('click', () => {
                    closeModal('settings-modal');
                });

                document.getElementById('search-input').addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase();
                    document.querySelectorAll('.route-card').forEach(card => {
                        const routeName = card.querySelector('h3').textContent.toLowerCase();
                        card.style.display = routeName.includes(searchTerm) ? 'block' : 'none';
                    });
                });
                
                // Settings modal token generation
                document.getElementById('settings-generate-token-btn').addEventListener('click', () => {
                    fetch('/map/api/token', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('settings-token-display').style.display = 'block';
                            document.getElementById('settings-token-code').textContent = data.token;
                            document.getElementById('settings-token-expires').textContent = new Date(data.expires_at).toLocaleString();
                            document.getElementById('settings-generate-token-btn').style.display = 'none';
                            showNotification(langTexts.token_generated || 'Token generated successfully!', 'success');
                        } else {
                            showNotification(data.error || langTexts.token_generate_failed || 'Failed to generate token', 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Error generating token:', err);
                        showNotification(langTexts.token_generate_error || 'Error generating token', 'error');
                    });
                });
                
                document.getElementById('settings-copy-token-btn').addEventListener('click', () => {
                    const token = document.getElementById('settings-token-code').textContent;
                    copyToClipboard(token);
                });

                document.getElementById('settings-revoke-token-btn').addEventListener('click', () => {
                    const token = document.getElementById('settings-token-code').textContent;
                    fetch(`/map/api/token/${token}`, { method: 'DELETE' })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('settings-token-display').style.display = 'none';
                            document.getElementById('settings-generate-token-btn').style.display = '';
                            showNotification(langTexts.token_revoked || 'Token revoked', 'success');
                        }
                    });
                });
                
                // Show existing token if available
                if (existingToken) {
                    document.getElementById('settings-token-display').style.display = 'block';
                    document.getElementById('settings-token-code').textContent = existingToken.token;
                    document.getElementById('settings-token-expires').textContent = new Date(existingToken.expires_at).toLocaleString();
                    document.getElementById('settings-generate-token-btn').style.display = 'none';
                }
            });

            function toggleFavorite(routeId, btn) {
                fetch('?ajax=toggle_favorite', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${routeId}` })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            btn.classList.toggle('favorited');
                            btn.title = btn.classList.contains('favorited') ? langTexts.unmark_favorite : langTexts.mark_favorite;
                        }
                    });
            }

            function renameRoute(routeId, newName) {
                fetch('?ajax=rename', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${routeId}&name=${encodeURIComponent(newName)}` })
                    .then(response => response.json())
                    .then(data => { if (data.success) location.reload(); });
            }

            function deleteRoute(routeId) {
                fetch('?ajax=delete', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${routeId}` })
                    .then(response => response.json())
                    .then(data => { if (data.success) location.reload(); });
            }
            
            // Clipboard helper with fallback
            function copyToClipboard(text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(() => {
                        showNotification(langTexts.token_copied, 'success');
                    }).catch(() => {
                        fallbackCopyText(text);
                    });
                } else {
                    fallbackCopyText(text);
                }
            }
            
            function fallbackCopyText(text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.left = '-999999px';
                textarea.style.top = '-999999px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                try {
                    document.execCommand('copy');
                    showNotification(langTexts.token_copied, 'success');
                } catch (err) {
                    showNotification('Failed to copy token', 'error');
                }
                document.body.removeChild(textarea);
            }
            
            // Notification system
            function showNotification(message, type = 'info', title = null) {
                const container = document.getElementById('notification-container');
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                
                const icons = {
                    success: '✓',
                    error: '✕',
                    warning: '⚠',
                    info: 'ℹ'
                };
                
                const titles = {
                    success: langTexts.success || 'Success',
                    error: langTexts.error || 'Error',
                    warning: langTexts.warning || 'Warning',
                    info: langTexts.info || 'Info'
                };
                
                notification.innerHTML = `
                    <div class="notification-icon">${icons[type] || icons.info}</div>
                    <div class="notification-content">
                        <div class="notification-title">${title || titles[type] || titles.info}</div>
                        <div class="notification-message">${message}</div>
                    </div>
                    <button class="notification-close" onclick="this.parentElement.classList.add('removing'); setTimeout(() => this.parentElement.remove(), 300);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                `;
                
                container.appendChild(notification);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.classList.add('removing');
                        setTimeout(() => notification.remove(), 300);
                    }
                }, 5000);
            }
            
            // Location Sharing functionality
            let locationMap = null;
            let locationMarker = null;
            let locationHistory = [];
            let locationHistoryIndex = 0;
            let telemetrySeries = {};
            let telemetryChartPopover = null;
            let telemetryChartPinned = false;

            function formatTelemetryValue(value) {
                if (value === null || value === undefined || value === '') return langTexts.na || 'N/A';
                if (typeof value === 'number') return Number.isInteger(value) ? String(value) : value.toFixed(2);
                if (typeof value === 'boolean') return value ? (langTexts.yes || 'Yes') : (langTexts.no || 'No');
                if (typeof value === 'object') return JSON.stringify(value);
                return String(value);
            }

            function formatTelemetryLabel(key) {
                return String(key).replace(/_/g, ' ').replace(/\b\w/g, m => m.toUpperCase());
            }
            function extractNumericValue(value) {
                if (typeof value === 'number' && Number.isFinite(value)) return value;
                if (typeof value !== 'string') return null;
                const normalized = value.replace(',', '.');
                const match = normalized.match(/-?\d+(\.\d+)?/);
                if (!match) return null;
                const n = Number(match[0]);
                return Number.isFinite(n) ? n : null;
            }
            function buildTelemetrySeries(locations) {
                telemetrySeries = {};
                if (!Array.isArray(locations)) return;
                locations.forEach(entry => {
                    const telemetry = (entry && entry.telemetry && typeof entry.telemetry === 'object') ? entry.telemetry : {};
                    Object.entries(telemetry).forEach(([key, val]) => {
                        const n = extractNumericValue(val);
                        if (n === null) return;
                        if (!telemetrySeries[key]) telemetrySeries[key] = [];
                        telemetrySeries[key].push(n);
                    });
                });
            }
            function createSparklineSvg(values) {
                if (!Array.isArray(values) || values.length < 2) return '';
                const w = 220;
                const h = 80;
                const p = 8;
                const min = Math.min(...values);
                const max = Math.max(...values);
                const range = max - min || 1;
                const points = values.map((v, i) => {
                    const x = p + (i * (w - p * 2)) / (values.length - 1);
                    const y = h - p - ((v - min) / range) * (h - p * 2);
                    return `${x.toFixed(2)},${y.toFixed(2)}`;
                }).join(' ');
                return `<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" style="display:block"><polyline points="${points}" fill="none" stroke="var(--primary-color)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="${points.split(' ').at(-1).split(',')[0]}" cy="${points.split(' ').at(-1).split(',')[1]}" r="3.2" fill="var(--primary-color)"/></svg>`;
            }
            function formatChartNumber(value) {
                const fixed = Number(value).toFixed(2);
                if (fixed.endsWith('00')) return Number(fixed).toFixed(1);
                if (fixed.endsWith('0')) return fixed.slice(0, -1);
                return fixed;
            }
            function ensureTelemetryChartPopover() {
                if (telemetryChartPopover) return telemetryChartPopover;
                telemetryChartPopover = document.createElement('div');
                telemetryChartPopover.style.position = 'fixed';
                telemetryChartPopover.style.zIndex = '1800';
                telemetryChartPopover.style.display = 'none';
                telemetryChartPopover.style.minWidth = '240px';
                telemetryChartPopover.style.maxWidth = '280px';
                telemetryChartPopover.style.padding = '0.65rem 0.7rem';
                telemetryChartPopover.style.borderRadius = '12px';
                telemetryChartPopover.style.background = 'var(--card-bg)';
                telemetryChartPopover.style.border = '1px solid var(--glass-border)';
                telemetryChartPopover.style.boxShadow = '0 12px 30px rgba(0,0,0,0.22)';
                telemetryChartPopover.style.pointerEvents = 'none';
                document.body.appendChild(telemetryChartPopover);
                document.addEventListener('click', e => {
                    if (!telemetryChartPinned) return;
                    if (!telemetryChartPopover.contains(e.target) && !e.target.closest('[data-telemetry-key]')) {
                        telemetryChartPinned = false;
                        telemetryChartPopover.style.display = 'none';
                    }
                });
                return telemetryChartPopover;
            }
            function showTelemetryChart(target, key, pin = false) {
                const values = telemetrySeries[key];
                if (!values || values.length < 2) return;
                const box = ensureTelemetryChartPopover();
                telemetryChartPinned = !!pin;
                box.style.pointerEvents = telemetryChartPinned ? 'auto' : 'none';
                const avg = values.reduce((sum, v) => sum + v, 0) / values.length;
                box.innerHTML = `<div style="font-size:0.78rem;opacity:0.82;margin-bottom:0.35rem;">${formatTelemetryLabel(key)}</div>${createSparklineSvg(values)}<div style="display:flex;justify-content:space-between;font-size:0.73rem;opacity:0.75;margin-top:0.2rem;"><span>${langTexts.min || 'min'} ${formatChartNumber(Math.min(...values))}</span><span>${langTexts.avg || 'avg'} ${formatChartNumber(avg)}</span><span>${langTexts.max || 'max'} ${formatChartNumber(Math.max(...values))}</span></div>`;
                const rect = target.getBoundingClientRect();
                const top = Math.max(10, rect.top - 110);
                const left = Math.max(10, Math.min(window.innerWidth - 290, rect.left + rect.width / 2 - 130));
                box.style.top = `${top}px`;
                box.style.left = `${left}px`;
                box.style.display = 'block';
            }
            function hideTelemetryChart() {
                const box = ensureTelemetryChartPopover();
                if (telemetryChartPinned) return;
                box.style.display = 'none';
            }
            function bindTelemetryChartEvents(card, key) {
                card.dataset.telemetryKey = key;
                card.style.cursor = 'pointer';
                card.addEventListener('mouseenter', () => showTelemetryChart(card, key, false));
                card.addEventListener('mouseleave', hideTelemetryChart);
                card.addEventListener('click', e => {
                    e.stopPropagation();
                    showTelemetryChart(card, key, true);
                });
                card.addEventListener('touchstart', e => {
                    e.stopPropagation();
                    showTelemetryChart(card, key, true);
                }, { passive: true });
            }
            function renderTelemetryDetails(loc) {
                const telemetryWrap = document.getElementById('loc-telemetry-wrap');
                const telemetryBox = document.getElementById('loc-telemetry');
                telemetryBox.innerHTML = '';
                const telemetry = (loc && loc.telemetry && typeof loc.telemetry === 'object') ? { ...loc.telemetry } : {};
                ['latitude', 'longitude', 'accuracy', 'message', 'device_name', 'device_type', 'speed', 'altitude', 'satellites'].forEach(key => delete telemetry[key]);
                const entries = Object.entries(telemetry);
                if (!entries.length) {
                    telemetryWrap.style.display = 'none';
                    return;
                }
                entries.forEach(([key, value]) => {
                    const card = document.createElement('div');
                    card.style.padding = '0.7rem 0.8rem';
                    card.style.borderRadius = '14px';
                    card.style.background = 'linear-gradient(135deg, var(--bg-color), color-mix(in srgb, var(--bg-color) 78%, var(--primary-color) 22%))';
                    card.style.border = '1px solid color-mix(in srgb, var(--primary-color) 45%, transparent)';
                    card.innerHTML = `<span style="display:block;font-size:0.74rem;opacity:0.75;letter-spacing:0.04em;text-transform:uppercase;">${formatTelemetryLabel(key)}</span><span style="display:block;font-size:0.98rem;font-weight:600;margin-top:0.18rem;">${formatTelemetryValue(value)}</span>`;
                    if ((telemetrySeries[key] || []).length > 1 && extractNumericValue(value) !== null) bindTelemetryChartEvents(card, key);
                    telemetryBox.appendChild(card);
                });
                telemetryWrap.style.display = 'block';
            }
            function applyLocationEntry(loc) {
                document.getElementById('loc-lat').textContent = loc.latitude.toFixed(6);
                document.getElementById('loc-lon').textContent = loc.longitude.toFixed(6);
                document.getElementById('loc-accuracy').textContent = loc.accuracy ? `${loc.accuracy.toFixed(0)}m` : 'N/A';
                document.getElementById('loc-device').textContent = [loc.device_name, loc.device_type].filter(Boolean).join(' / ') || 'N/A';
                document.getElementById('loc-speed').textContent = loc.speed !== null && loc.speed !== undefined ? `${Number(loc.speed).toFixed(1)} km/h` : 'N/A';
                document.getElementById('loc-altitude').textContent = loc.altitude !== null && loc.altitude !== undefined ? `${Number(loc.altitude).toFixed(1)} m` : 'N/A';
                document.getElementById('loc-satellites').textContent = loc.satellites ?? 'N/A';
                document.getElementById('loc-timestamp').textContent = new Date(loc.timestamp).toLocaleString();
                renderTelemetryDetails(loc);
            }
            function renderLocationPhoto(loc) {
                if (loc.photo_path) {
                    document.getElementById('location-photos').style.display = 'block';
                    const photosGrid = document.getElementById('photos-grid');
                    photosGrid.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = loc.photo_path;
                    img.style.width = '100%';
                    img.style.height = '150px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '4px';
                    img.style.cursor = 'pointer';
                    img.onclick = () => window.open(loc.photo_path, '_blank');
                    photosGrid.appendChild(img);
                } else {
                    document.getElementById('location-photos').style.display = 'none';
                }
            }
            function updateMapLocation(loc) {
                if (!locationMap) return;
                locationMap.setView([loc.latitude, loc.longitude], 15);
                if (locationMarker) locationMap.removeLayer(locationMarker);
                locationMarker = L.marker([loc.latitude, loc.longitude]).addTo(locationMap);
            }
            function updateLocationHistoryCard() {
                const historyWrap = document.getElementById('loc-history-wrap');
                const prevBtn = document.getElementById('loc-history-prev');
                const nextBtn = document.getElementById('loc-history-next');
                const latestBtn = document.getElementById('loc-history-latest');
                const pageLabel = document.getElementById('loc-history-page');
                if (!locationHistory.length) {
                    historyWrap.style.display = 'none';
                    return;
                }
                const currentEntry = locationHistory[locationHistoryIndex];
                applyLocationEntry(currentEntry);
                renderLocationPhoto(currentEntry);
                updateMapLocation(currentEntry);
                pageLabel.textContent = `${locationHistoryIndex + 1} / ${locationHistory.length}`;
                prevBtn.disabled = locationHistoryIndex >= locationHistory.length - 1;
                nextBtn.disabled = locationHistoryIndex <= 0;
                latestBtn.disabled = locationHistoryIndex === 0;
                historyWrap.style.display = 'block';
            }
            function renderLocationHistory(locations) {
                const historyWrap = document.getElementById('loc-history-wrap');
                const prevBtn = document.getElementById('loc-history-prev');
                const nextBtn = document.getElementById('loc-history-next');
                const latestBtn = document.getElementById('loc-history-latest');
                locationHistory = Array.isArray(locations) ? locations : [];
                buildTelemetrySeries(locationHistory);
                locationHistoryIndex = 0;
                prevBtn.onclick = () => {
                    if (locationHistoryIndex < locationHistory.length - 1) {
                        locationHistoryIndex += 1;
                        updateLocationHistoryCard();
                    }
                };
                nextBtn.onclick = () => {
                    if (locationHistoryIndex > 0) {
                        locationHistoryIndex -= 1;
                        updateLocationHistoryCard();
                    }
                };
                latestBtn.onclick = () => {
                    locationHistoryIndex = 0;
                    updateLocationHistoryCard();
                };
                if (!locationHistory.length) {
                    historyWrap.style.display = 'none';
                    return;
                }
                updateLocationHistoryCard();
            }
            
            document.getElementById('location-sharing-btn').addEventListener('click', () => {
                openModal('location-sharing-modal');
                loadLocationData();
            });
            
            document.getElementById('open-settings-from-location').addEventListener('click', () => {
                document.getElementById('location-sharing-modal').style.display = 'none';
                openModal('settings-modal');
            });
            
            document.getElementById('close-location-modal').addEventListener('click', () => {
                closeModal('location-sharing-modal');
            });
            
            function loadLocationData() {
                // First, get tokens
                fetch('/map/api/tokens')
                .then(response => response.json())
                .then(tokenData => {
                    if (tokenData.success && tokenData.tokens.length > 0) {
                        const token = tokenData.tokens[0].token;
                        
                        // Get location data
                        return fetch(`/map/api/location?token=${token}&limit=50`);
                    } else {
                        document.getElementById('no-location-msg').style.display = 'block';
                        document.getElementById('location-map').style.display = 'none';
                        document.getElementById('location-data').style.display = 'none';
                        document.getElementById('location-photos').style.display = 'none';
                        document.getElementById('loc-history-wrap').style.display = 'none';
                        return null;
                    }
                })
                .then(response => response ? response.json() : null)
                .then(data => {
                    if (data && data.success && data.locations.length > 0) {
                        const loc = data.locations[0];
                        document.getElementById('no-location-msg').style.display = 'none';
                        document.getElementById('location-data').style.display = 'block';
                        document.getElementById('location-map').style.display = 'block';
                        if (locationMap) {
                            locationMap.remove();
                        }
                        const locationMapEl = document.getElementById('location-map');
                        if (locationMapEl) {
                            locationMap = L.map(locationMapEl).setView([loc.latitude, loc.longitude], 15);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'<?= $theme === 'dark' ? ", { className: 'osm-dark-tiles' }" : '' ?>).addTo(locationMap);
                        }
                        renderLocationHistory(data.locations);
                    } else if (data) {
                        document.getElementById('no-location-msg').style.display = 'block';
                        document.getElementById('location-map').style.display = 'none';
                        document.getElementById('location-data').style.display = 'none';
                        document.getElementById('location-photos').style.display = 'none';
                        document.getElementById('loc-telemetry-wrap').style.display = 'none';
                        document.getElementById('loc-history-wrap').style.display = 'none';
                    }
                })
                .catch(err => {
                    console.error('Error loading location data:', err);
                    document.getElementById('loc-telemetry-wrap').style.display = 'none';
                    document.getElementById('loc-history-wrap').style.display = 'none';
                });
            }
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function renderTablePage($pagination) {
    global $lang, $theme, $color, $lang_array, $view_mode, $display_mode, $currentUser, $safeUsername, $page, $currentUserRole, $existingToken;
    $processed = isset($_GET['processed']) ? (int)$_GET['processed'] : 0;
    $favorites = getFavorites();
    $itemsPerPage = isset($_SESSION['settings'][$safeUsername]['items_per_page']) ? $_SESSION['settings'][$safeUsername]['items_per_page'] : 6;
    
    ob_start(); ?>
    <!DOCTYPE html>
    <html lang="<?= $lang ?>" data-theme="<?= $theme ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $lang_array['app_title'] ?> - <?= $lang_array['table_view'] ?></title>
        <style data-page-style="route-page">
            :root { --primary-color: <?= $color ?>; --bg-color: <?= $theme === 'dark' ? '#1a1a1a' : '#ffffff' ?>; --text-color: <?= $theme === 'dark' ? '#ffffff' : '#000000' ?>; --card-bg: <?= $theme === 'dark' ? '#2d2d2d' : '#f5f5f5' ?>; --scrollbar-bg: <?= $theme === 'dark' ? '#2d2d2d' : '#f5f5f5' ?>; --scrollbar-thumb: <?= $theme === 'dark' ? '#555' : '#ccc' ?>; --border-color: <?= $theme === 'dark' ? '#444' : '#ddd' ?>; --hover-color: <?= $theme === 'dark' ? '#3a3a3a' : '#eaeaea' ?>; --radius-sm: 14px; --radius-md: 20px; --radius-lg: 28px; --glass-bg: <?= $theme === 'dark' ? 'rgba(255,255,255,0.08)' : 'rgba(255,255,255,0.58)' ?>; --glass-border: <?= $theme === 'dark' ? 'rgba(255,255,255,0.16)' : 'rgba(0,0,0,0.1)' ?>; }
            ::-webkit-scrollbar { width: 12px; height: 12px; }
            ::-webkit-scrollbar-track { background: var(--scrollbar-bg); border-radius: 6px; }
            ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 6px; border: 2px solid var(--scrollbar-bg); }
            ::-webkit-scrollbar-thumb:hover { background: var(--primary-color); }
            * { scrollbar-width: thin; scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-bg); }
            .osm-dark-tiles { filter: invert(1) hue-rotate(180deg) brightness(0.95) contrast(0.9); }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); transition: all 0.3s ease; overflow-x: hidden; }
            header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.25rem 2rem; background-color: var(--card-bg); margin: 12px 45px 2rem; position: relative; border-radius: var(--radius-lg); }
            .header-left { display: none; }
            .header-left h1 { margin: 0; font-size: 1.5rem; }
            .user-info { font-size: 0.9rem; color: var(--primary-color); background: var(--bg-color); padding: 0.3rem 0.8rem; border-radius: 20px; }
            .view-mode { display: flex; gap: 0.5rem; }
            .view-mode a { 
                text-decoration: none; 
                color: var(--text-color); 
                padding: 0.5rem 1rem; /* Увеличил горизонтальные отступы */
                border-radius: 4px; 
                font-size: 0.9rem;
                display: inline-flex; /* Изменено с block на inline-flex */
                align-items: center; /* Центрирование по вертикали */
                justify-content: center; /* Центрирование по горизонтали */
                min-width: 80px; /* Минимальная ширина для одинакового размера */
                text-align: center;
                line-height: 1.2; /* Для лучшего вертикального выравнивания */
            }
            .view-mode a.active { background-color: var(--primary-color); color: white; }
            .display-mode { display: flex; gap: 0.5rem; margin-left: 1rem; }
            .display-mode a {
                text-decoration: none;
                color: var(--text-color);
                padding: 0.5rem 1rem; /* Увеличил горизонтальный padding */
                border-radius: 4px;
                font-size: 0.9rem;
                display: flex;
                align-items: center; /* Центрирование по вертикали */
                justify-content: center; /* Центрирование по горизонтали */
                gap: 0.3rem;
                text-align: center;
                min-width: 80px; /* Минимальная ширина для одинакового размера */
            }
            .display-mode a.active { background-color: var(--primary-color); color: white; }
            .display-mode svg { width: 20px; height: 20px; stroke: currentColor; fill: none; }
            .controls { display: flex; gap: 1rem; align-items: center; width: 100%; }
            .search-container { 
                display: flex;
                align-items: center; 
                background: var(--bg-color); 
                border-radius: var(--radius-md); 
                padding: 0.85rem 1rem; 
                border: 1px solid var(--glass-border); 
                gap: 0.5rem; 
                flex: 1;
                max-width: none;
                min-width: 0;
            }
            .btn:disabled, 
            .btn.disabled {
                opacity: 0.5;
                cursor: not-allowed;
                pointer-events: none;
                background-color: #8c8c8c2d; /* Опционально: измените цвет фона */
            }
            .btn.disabled:hover {
                opacity: 0.5;
                transform: none;
                box-shadow: none;
            }
            .search-container input { border: none; background: transparent; color: var(--text-color); outline: none; padding: 0; flex: 1; font-size: 1rem; }
            .search-icon { width: 16px; height: 16px; fill: #666; }
            .btn { padding: 0.5rem; border: none; border-radius: 999px; background-color: var(--primary-color); color: white; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: opacity 0.2s ease; min-width: 44px; height: 44px; }
            .btn:hover { opacity: 0.9; }
            .btn-icon { width: 20px; height: 20px; fill: currentColor; }
            .menu-toggle { flex-shrink: 0; background: var(--glass-bg); border: 1px solid var(--glass-border); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); }
            .menu-toggle img { width: 22px; height: 22px; display: block; }
            .side-menu-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.28); opacity: 0; pointer-events: none; transition: opacity 0.25s ease; z-index: 1200; }
            .side-menu { position: fixed; top: 0; right: 0; width: min(360px, 88vw); height: 100vh; background: <?= $theme === 'dark' ? 'linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(24,24,24,0.92) 18%, rgba(24,24,24,0.95) 100%)' : 'linear-gradient(180deg, rgba(255,255,255,0.92) 0%, rgba(248,248,248,0.86) 22%, rgba(243,243,243,0.92) 100%)' ?>; backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border-left: 1px solid var(--glass-border); box-shadow: -10px 0 40px rgba(0,0,0,0.18); transform: translateX(100%); transition: transform 0.28s ease; z-index: 1300; padding: calc(1.25rem + 45px) 1.25rem 1.25rem calc(1.25rem + 45px); display: flex; flex-direction: column; gap: 1rem; overflow-y: auto; overflow-x: hidden; }
            .side-menu.open { transform: translateX(0); }
            .side-menu-overlay.open { opacity: 1; pointer-events: auto; }
            body.menu-open { overflow: hidden; }
            .side-menu-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
            .side-menu-title { font-size: 1rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; opacity: 0.75; }
            .side-menu-close { background: transparent; border: 1px solid var(--glass-border); color: var(--text-color); width: 42px; height: 42px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
            .side-menu-close svg { width: 18px; height: 18px; stroke: currentColor; }
            .side-menu-section { display: flex; flex-direction: column; gap: 0.75rem; }
            .side-menu-label { font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.55; padding-left: 0.2rem; }
            .side-menu-buttons { display: flex; flex-direction: column; gap: 0.75rem; }
            .menu-switch { display: flex; flex-direction: column; gap: 0.55rem; }
            .menu-switch-title { font-size: 0.78rem; letter-spacing: 0.06em; text-transform: uppercase; opacity: 0.6; padding-left: 0.2rem; }
            .menu-switch-track { position: relative; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 4px; border-radius: 999px; background: <?= $theme === 'dark' ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)' ?>; border: 1px solid <?= $color ?>33; backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); box-shadow: inset 0 1px 0 rgba(255,255,255,0.06); overflow: hidden; }
            .menu-switch-indicator { position: absolute; top: 4px; left: 4px; width: calc(50% - 4px); height: calc(100% - 8px); border-radius: 999px; background: var(--primary-color); box-shadow: 0 10px 24px <?= $color ?>33; transition: transform 0.24s cubic-bezier(0.4, 0, 0.2, 1), background 0.24s ease, box-shadow 0.24s ease; z-index: 1; }
            .menu-switch.is-right .menu-switch-indicator { transform: translateX(100%); }
            .menu-switch-option { min-height: 48px; padding: 0.8rem 0.9rem; border-radius: 999px; border: none; color: <?= $theme === 'dark' ? 'rgba(255,255,255,0.72)' : 'rgba(0,0,0,0.62)' ?>; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; text-align: center; font-weight: 600; font-size: 0.92rem; background: transparent; position: relative; z-index: 2; transition: color 0.2s ease, transform 0.16s ease; }
            .menu-switch-option:hover { transform: translateY(-1px); }
            .menu-switch-option.active { color: <?= $theme === 'dark' ? '#081018' : '#ffffff' ?>; }
            .menu-switch-option:active { transform: scale(0.98); }
            .menu-action { width: 100%; min-height: 54px; padding: 0.9rem 1rem; border-radius: 22px; border: 1px solid var(--glass-border); background: var(--glass-bg); color: var(--text-color); text-decoration: none; display: inline-flex; align-items: center; gap: 0.85rem; backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease; box-shadow: inset 0 1px 0 rgba(255,255,255,0.08); }
            .menu-action:hover { transform: translateX(-3px); border-color: <?= $color ?>80; box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
            .menu-action.active { background: <?= $theme === 'dark' ? 'rgba(255,255,255,0.14)' : 'rgba(255,255,255,0.74)' ?>; border-color: <?= $color ?>99; color: var(--primary-color); }
            .menu-action.disabled { opacity: 0.45; pointer-events: none; }
            .menu-action svg { width: 20px; height: 20px; flex-shrink: 0; stroke: currentColor; fill: none; }
            .menu-action .fill-icon { fill: currentColor; stroke: none; }
            .menu-action span { font-size: 0.98rem; font-weight: 600; }
            .table-container { padding: 1rem; overflow-x: auto; }
            table { width: 100%; border-collapse: collapse; background-color: var(--card-bg); border-radius: var(--radius-lg); overflow: hidden; }
            th { background-color: var(--primary-color); color: white; padding: 1rem; text-align: left; font-weight: bold; }
            td { padding: 1rem; border-bottom: 1px solid var(--border-color); }
            tr:hover { background-color: var(--hover-color); }
            .selected-row { background-color: var(--hover-color); outline: 2px solid var(--primary-color); }
            .action-btn { background: none; border: none; cursor: pointer; padding: 0.5rem; color: var(--text-color); display: inline-flex; align-items: center; justify-content: center; }
            .action-btn svg { width: 20px; height: 20px; stroke: currentColor; fill: none; }
            .action-btn:hover { color: var(--primary-color); }
            .favorite-btn { color: <?= $color ?>; }
            .favorite-btn.favorited svg { fill: #ffd700; stroke: #ffd700; }
            .stats-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; }
            .stats-overlay.open { display: flex; }
            .stats-popup { background: var(--card-bg); border-radius: var(--radius-lg); box-shadow: 0 24px 60px rgba(0,0,0,0.28); max-width: 480px; width: 100%; max-height: 90vh; overflow-y: auto; position: relative; animation: statsPop 0.22s cubic-bezier(0.34,1.56,0.64,1); }
            @keyframes statsPop { from { opacity:0; transform:scale(0.88) translateY(12px); } to { opacity:1; transform:none; } }
            .stats-popup-header { padding: 1.1rem 1.25rem 0.85rem; border-bottom: 1px solid var(--glass-border); display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; }
            .stats-popup-title { font-size: 1.05rem; font-weight: 700; line-height: 1.3; }
            .stats-popup-close { background: transparent; border: none; color: var(--text-color); opacity: 0.55; cursor: pointer; padding: 0.2rem; flex-shrink: 0; }
            .stats-popup-close:hover { opacity: 1; }
            .stats-popup-close svg { width: 20px; height: 20px; stroke: currentColor; fill: none; display: block; }
            .stats-body { padding: 1rem 1.25rem 1.25rem; display: flex; flex-direction: column; gap: 0.5rem; }
            .stats-group-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.5; padding-top: 0.6rem; }
            .stats-row { display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem; padding: 0.3rem 0; border-bottom: 1px solid var(--glass-border); }
            .stats-row:last-child { border-bottom: none; }
            .stats-label { font-size: 0.88rem; opacity: 0.7; white-space: nowrap; }
            .stats-value { font-size: 0.92rem; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; }
            .select-checkbox { width: 20px; height: 20px; cursor: pointer; }
            .download-menu { position: relative; display: inline-block; }
            .download-options { display: none; position: absolute; bottom: 100%; left: 0; background: var(--card-bg); border-radius: var(--radius-sm); box-shadow: 0 2px 10px rgba(0,0,0,0.2); padding: 0.5rem; min-width: 100px; z-index: 100; }
            .download-options a { display: block; padding: 0.5rem; color: var(--text-color); text-decoration: none; font-size: 0.9rem; border-radius: 12px; }
            .download-options a:hover { background: var(--primary-color); color: white; }
            .download-menu:hover .download-options { display: none;}
            .bulk-actions { display: flex; gap: 1rem; margin-bottom: 1rem; padding: 0 1rem; }
            .pagination { display: flex; justify-content: center; gap: 0.5rem; padding: 1rem; }
            /* Стили для кнопок пагинации */
            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 15px;
                margin: 30px 0;
                flex-wrap: wrap;
            }

            .pagination-btn {
                padding: 10px 24px;
                background: <?php echo $theme === 'dark' ? '#444' : '#f0f0f0'; ?>;
                border: 2px solid <?php echo $theme === 'dark' ? '#555' : '#ddd'; ?>;
                border-radius: 12px;
                color: <?php echo $theme === 'dark' ? '#fff' : '#333'; ?>;
                text-decoration: none;
                font-weight: 600;
                transition: all var(--transition-speed);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 100px;
            }

            .pagination-btn:hover:not(.disabled) {
                background: var(--primary-color);
                border-color: var(--primary-color);
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px <?php echo $color; ?>40;
            }

            .pagination-btn.disabled {
                opacity: 0.5;
                cursor: not-allowed;
                pointer-events: none;
                background: <?php echo $theme === 'dark' ? '#333' : '#e0e0e0'; ?>;
                border-color: <?php echo $theme === 'dark' ? '#444' : '#ccc'; ?>;
            }

            .pagination span {
                padding: 10px 20px;
                background: <?php echo $theme === 'dark' ? '#333' : '#fff'; ?>;
                border-radius: 12px;
                color: <?php echo $theme === 'dark' ? '#fff' : '#333'; ?>;
                font-weight: 600;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .modal { display: none; position: fixed; inset: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.58); z-index: 1500; overflow-y: auto; }
            body.modal-open { overflow: hidden; }
            .modal-content { 
                background: var(--card-bg); 
                margin: 0; 
                padding: 2rem 2rem 2.5rem; 
                border-radius: 0; 
                max-width: none; 
                width: 100%; 
                min-height: 100vh; 
                max-height: none; 
                overflow-y: visible;
                position: relative;
            }
            @media (min-width: 640px) {
                .modal-content { max-width: 660px; margin: 2.5rem auto; min-height: auto; border-radius: var(--radius-lg); padding: 2.5rem 2.5rem 3rem; }
            }
            .modal-content h2 { margin-bottom: 1rem; }
            .modal-content form { display: flex; flex-direction: column; gap: 1rem; }
            .modal-content .btn { width: auto; min-width: 140px; }
            .modal-content .user-info-modal { background: var(--bg-color); padding: 0.75rem; border-radius: var(--radius-sm); margin-bottom: 1rem; text-align: center; color: var(--primary-color); font-weight: bold; }
            .modal-content code { border-radius: 16px; }
            #location-sharing-modal .modal-content { max-width: none !important; margin: 0 !important; min-height: 100vh; border-radius: 0; }
            #location-sharing-modal #location-map { height: min(55vh, 520px) !important; }
            .settings-zone { border: 1px solid var(--glass-border); border-radius: var(--radius-sm); margin-top: 1.75rem; overflow: hidden; }
            .settings-zone-header { padding: 0.8rem 1.1rem; background: var(--glass-bg); border-bottom: 1px solid var(--glass-border); font-weight: 700; font-size: 0.88rem; letter-spacing: 0.04em; text-transform: uppercase; display: flex; align-items: center; gap: 0.5rem; opacity: 0.85; }
            .settings-zone-header svg { width: 15px; height: 15px; stroke: currentColor; fill: none; flex-shrink: 0; }
            .settings-zone-body { padding: 1.1rem; display: flex; flex-direction: column; gap: 0.85rem; }
            .btn-danger { background: rgba(220,38,38,0.07); border: 1px solid rgba(220,38,38,0.35); color: #dc2626; }
            .btn-danger:hover { background: rgba(220,38,38,0.14); border-color: rgba(220,38,38,0.65); }
            .modal-close { position: absolute; top: 1.25rem; right: 1.25rem; width: 46px; height: 46px; min-width: 46px; padding: 0; border-radius: 999px; border: 2px solid rgba(220, 38, 38, 0.72); background: rgba(220, 38, 38, 0.08); color: #dc2626; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.14s ease, background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease; box-shadow: inset 0 1px 0 rgba(255,255,255,0.1); }
            .modal-close:hover { background: rgba(220, 38, 38, 0.14); border-color: rgba(220, 38, 38, 0.92); box-shadow: 0 10px 24px rgba(220, 38, 38, 0.16); }
            .modal-close:active { transform: scale(0.9) rotate(8deg); background: rgba(220, 38, 38, 0.2); }
            .modal-close svg { width: 18px; height: 18px; stroke: currentColor; }
            @media (max-width: 768px) { 
                header { margin: 12px 45px 1.5rem; padding: 1rem; }
                .controls { gap: 0.75rem; }
                .search-container { width: 100%; }
                .side-menu { width: min(340px, 92vw); }
                table { font-size: 0.9rem; }
                th, td { padding: 0.75rem; }
                .bulk-actions { flex-wrap: wrap; }
                .pagination {
                    gap: 10px;
                }
                
                .pagination-btn {
                    padding: 8px 16px;
                    min-width: 80px;
                    font-size: 14px;
                }
                
                .pagination span {
                    padding: 8px 16px;
                    font-size: 14px;
                }
            }
            @media (max-width: 480px) {
                header { margin: 12px 45px 1rem; padding: 0.75rem; }
                .btn { min-width: 40px; height: 40px; }
                .modal-close { top: 1rem; right: 1rem; width: 42px; height: 42px; min-width: 42px; }
                .menu-action { min-height: 50px; border-radius: 18px; }
            }
            [data-theme="dark"] { --bg-color: #1a1a1a; --text-color: #ffffff; --card-bg: #2d2d2d; --scrollbar-bg: #2d2d2d; --scrollbar-thumb: #555; --border-color: #444; --hover-color: #3a3a3a; }
            [data-theme="light"] { --bg-color: #ffffff; --text-color: #000000; --card-bg: #f5f5f5; --scrollbar-bg: #f5f5f5; --scrollbar-thumb: #ccc; --border-color: #ddd; --hover-color: #eaeaea; }
                    /* Стили для полей ввода в модальном окне */
            .modal-content input[type="number"],
            .modal-content input[type="text"],
            .modal-content select {
                width: 100%;
                padding: 0.75rem;
                border: 2px solid var(--border-color, <?= $theme === 'dark' ? '#444' : '#ddd' ?>);
                border-radius: 18px;
                background-color: var(--bg-color);
                color: var(--text-color);
                font-size: 1rem;
                transition: all 0.3s ease;
                box-sizing: border-box;
            }

            .modal-content input[type="number"]:focus,
            .modal-content input[type="text"]:focus,
            .modal-content select:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px <?= $color ?>20;
            }

            /* Специфические стили для спиннера (стрелочек) */
            .modal-content input[type="number"] {
                -moz-appearance: textfield; /* Убирает стандартные стрелки в Firefox */
                appearance: textfield;
            }

            .modal-content input[type="number"]::-webkit-inner-spin-button,
            .modal-content input[type="number"]::-webkit-outer-spin-button {
                opacity: 0.8;
                height: 24px;
                background: var(--card-bg);
                border-left: 1px solid var(--border-color, <?= $theme === 'dark' ? '#444' : '#ddd' ?>);
                cursor: pointer;
            }

            .modal-content input[type="number"]::-webkit-inner-spin-button:hover,
            .modal-content input[type="number"]::-webkit-outer-spin-button:hover {
                background: var(--primary-color);
                opacity: 1;
            }

            /* Стили для группы полей ввода */
            .modal-content .form-group {
                margin-bottom: 1.5rem;
            }

            .modal-content .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: var(--text-color);
                font-size: 0.95rem;
            }

            /* Стили для кнопок в модальном окне */
            .modal-content .form-group .btn {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
                border: none;
                border-radius: 999px;
                background-color: var(--primary-color);
                color: white;
                cursor: pointer;
                transition: opacity 0.2s ease;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 100px;
                font-weight: 500;
            }

            .modal-content .form-group .btn:hover {
                opacity: 0.9;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px <?= $color ?>40;
            }

            .modal-content .form-group .btn:active {
                transform: translateY(0);
            }

            /* Modern Notification Styles */
            .notification-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
            }
            
            .notification {
                pointer-events: auto;
                background: var(--card-bg);
                border-radius: 12px;
                padding: 16px 20px;
                min-width: 320px;
                max-width: 450px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.3);
                display: flex;
                align-items: flex-start;
                gap: 12px;
                animation: slideInRight 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border-left: 4px solid var(--primary-color);
                transition: all 0.3s ease;
            }
            
            .notification:hover {
                transform: translateX(-4px);
                box-shadow: 0 12px 32px rgba(0,0,0,0.4);
            }
            
            .notification.notification-success {
                border-left-color: #10b981;
            }
            
            .notification.notification-error {
                border-left-color: #ef4444;
            }
            
            .notification.notification-warning {
                border-left-color: #f59e0b;
            }
            
            .notification.notification-info {
                border-left-color: #3b82f6;
            }
            
            .notification-icon {
                flex-shrink: 0;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 14px;
            }
            
            .notification-success .notification-icon {
                background: #10b98120;
                color: #10b981;
            }
            
            .notification-error .notification-icon {
                background: #ef444420;
                color: #ef4444;
            }
            
            .notification-content {
                flex: 1;
                min-width: 0;
            }
            
            .notification-title {
                font-weight: 600;
                font-size: 0.95rem;
                margin-bottom: 4px;
                color: var(--text-color);
            }
            
            .notification-message {
                font-size: 0.85rem;
                color: var(--text-color);
                opacity: 0.8;
                line-height: 1.4;
            }
            
            .notification-close {
                flex-shrink: 0;
                background: none;
                border: none;
                color: var(--text-color);
                opacity: 0.5;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .notification-close:hover {
                opacity: 1;
                background: rgba(0,0,0,0.1);
            }
            
            .notification.removing {
                animation: slideOutRight 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                opacity: 0;
                transform: translateX(100%);
            }
            
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100%);
                }
            }
            
            @media (max-width: 768px) {
                .notification-container {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                }
                
                .notification {
                    min-width: auto;
                    max-width: none;
                }
            }
        </style>
    </head>
    <body>
        <header>
            <div class="controls">
                <div class="search-container">
                    <svg class="search-icon" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="search-input" placeholder="<?= $lang_array['search_placeholder'] ?>">
                </div>
                <button class="btn menu-toggle" id="menu-toggle" type="button" aria-label="Open menu">
                    <img src="menu.svg" alt="Menu">
                </button>
            </div>
        </header>
        <?php $hasRoutes = !empty(getRoutes()); ?>
        <div class="side-menu-overlay" id="side-menu-overlay"></div>
        <aside class="side-menu" id="side-menu" aria-hidden="true">
            <div class="side-menu-header">
                <div class="side-menu-title"><?= $lang_array['app_title'] ?></div>
                <button class="side-menu-close" id="side-menu-close" type="button" aria-label="Close menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="side-menu-section">
                <div class="side-menu-label">Navigation</div>
                <div class="side-menu-buttons">
                    <div class="menu-switch <?= $view_mode === 'favorites' ? 'is-right' : '' ?>">
                        <div class="menu-switch-title"><?= $lang_array['favorites'] ?></div>
                        <div class="menu-switch-track">
                            <div class="menu-switch-indicator"></div>
                            <a href="?page=1&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=all&display_mode=<?= $display_mode ?>" data-partial-nav="1" class="menu-switch-option <?= $view_mode === 'all' ? 'active' : '' ?>"><?= $lang_array['all_routes'] ?></a>
                            <a href="?page=1&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=favorites&display_mode=<?= $display_mode ?>" data-partial-nav="1" class="menu-switch-option <?= $view_mode === 'favorites' ? 'active' : '' ?>"><?= $lang_array['favorites'] ?></a>
                        </div>
                    </div>
                    <div class="menu-switch <?= $display_mode === 'table' ? 'is-right' : '' ?>">
                        <div class="menu-switch-title"><?= $lang_array['display_mode'] ?? 'Display' ?></div>
                        <div class="menu-switch-track">
                            <div class="menu-switch-indicator"></div>
                            <a href="?page=<?= $page ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=grid" data-partial-nav="1" class="menu-switch-option <?= $display_mode === 'grid' ? 'active' : '' ?>"><?= $lang_array['grid_view'] ?></a>
                            <a href="?page=<?= $page ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=table" data-partial-nav="1" class="menu-switch-option <?= $display_mode === 'table' ? 'active' : '' ?>"><?= $lang_array['table_view'] ?></a>
                        </div>
                    </div>
                    <?php if ($hasRoutes): ?>
                        <a href="?action=view_all_map&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="menu-action">
                            <svg viewBox="0 0 24 24" stroke-width="2"><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"></path><path d="M15 5.764v15"></path><path d="M9 3.236v15"></path></svg>
                            <span><?= $lang_array['view_all_map'] ?></span>
                        </a>
                    <?php else: ?>
                        <span class="menu-action disabled">
                            <svg viewBox="0 0 24 24" stroke-width="2"><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"></path><path d="M15 5.764v15"></path><path d="M9 3.236v15"></path></svg>
                            <span><?= $lang_array['view_all_map'] ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="side-menu-section">
                <div class="side-menu-label">Actions</div>
                <div class="side-menu-buttons">
                    <a href="?action=add&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="menu-action">
                        <svg viewBox="0 0 24 24" stroke-width="2"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>
                        <span><?= $lang_array['add_route'] ?></span>
                    </a>
                    <a href="?action=add_folder&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="menu-action">
                        <svg viewBox="0 0 24 24" stroke-width="2"><path d="M3 7h5l2 2h11v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><path d="M3 7a2 2 0 0 1 2-2h4"></path></svg>
                        <span><?= $lang_array['add_folder'] ?></span>
                    </a>
                    <button class="menu-action" id="location-sharing-btn" type="button">
                        <svg viewBox="0 0 24 24" stroke-width="2"><line x1="2" x2="5" y1="12" y2="12"></line><line x1="19" x2="22" y1="12" y2="12"></line><line x1="12" x2="12" y1="2" y2="5"></line><line x1="12" x2="12" y1="19" y2="22"></line><circle cx="12" cy="12" r="7"></circle><circle cx="12" cy="12" r="3"></circle></svg>
                        <span><?= $lang_array['my_location'] ?></span>
                    </button>
                    <button class="menu-action" id="settings-btn" type="button">
                        <svg viewBox="0 0 24 24" class="fill-icon"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"></path></svg>
                        <span><?= $lang_array['settings'] ?></span>
                    </button>
                    <a href="?action=api_docs&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="menu-action">
                        <svg viewBox="0 0 24 24" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        <span><?= $lang_array['api_docs'] ?></span>
                    </a>
                </div>
            </div>
        </aside>
        <main>
            <?php if ($processed > 0): ?>
                <p style="text-align: center; color: var(--primary-color);"><?= $processed ?> <?= $lang_array['add_route'] ?>(s) processed</p>
            <?php endif; ?>
            
            <div class="bulk-actions">
                <button class="btn" id="select-all-btn"><?= $lang_array['select_all'] ?></button>
                <button class="btn" id="deselect-all-btn"><?= $lang_array['deselect_all'] ?></button>
                <div class="download-menu">
                    <button class="btn" id="download-selected-btn"><?= $lang_array['download_selected'] ?></button>
                    <div class="download-options">
                        <a href="#" onclick="downloadSelected('csv'); return false;"><?= $lang_array['csv_format'] ?></a>
                        <a href="#" onclick="downloadSelected('json'); return false;"><?= $lang_array['json_format'] ?></a>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table id="routes-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all-checkbox" class="select-checkbox"></th>
                            <th><?= $lang_array['id'] ?></th>
                            <th><?= $lang_array['name'] ?></th>
                            <th><?= $lang_array['file_size'] ?></th>
                            <th><?= $lang_array['file_date'] ?></th>
                            <th><?= $lang_array['points_count'] ?></th>
                            <th><?= $lang_array['favorites'] ?></th>
                            <th><?= $lang_array['actions'] ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagination['routes'] as $route): ?>
                        <tr data-route-id="<?= $route['id'] ?>" data-route-name="<?= htmlspecialchars($route['name']) ?>" data-points='<?= json_encode($route['points']) ?>'>
                            <td>
                                <input type="checkbox" class="select-checkbox row-checkbox" value="<?= $route['id'] ?>">
                            </td>
                            <td><?= htmlspecialchars(substr($route['id'], 0, 8)) ?>...</td>
                            <td><?= htmlspecialchars($route['name']) ?></td>
                            <td><?= formatFileSize($route['size']) ?></td>
                            <td><?= formatDate($route['date']) ?></td>
                            <td><?= count($route['points']) ?></td>
                            <td>
                                <button class="action-btn favorite-btn <?= in_array($route['id'], $favorites) ? 'favorited' : '' ?>" data-route-id="<?= $route['id'] ?>" title="<?= in_array($route['id'], $favorites) ? $lang_array['unmark_favorite'] : $lang_array['mark_favorite'] ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                </button>
                            </td>
                            <td>
                                
                                <button class="action-btn view-row-btn" onclick="window.location.href='?action=view&id=<?= $route['id'] ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>'" title="<?= $lang_array['view'] ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <button class="action-btn stats-btn" data-route-id="<?= $route['id'] ?>" title="<?= $lang_array['stats'] ?>"><svg viewBox="0 0 24 24" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg></button>
                                <div class="download-menu" style="display: inline-block;">
                                    <button class="action-btn" title="<?= $lang_array['download'] ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    </button>
                                    <div class="download-options">
                                        <a href="?download=kml&id=<?= $route['id'] ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>"><?= $lang_array['kml_format'] ?></a>
                                        <a href="?download=gpx&id=<?= $route['id'] ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>"><?= $lang_array['gpx_format'] ?></a>
                                        <a href="?download=csv&id=<?= $route['id'] ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>"><?= $lang_array['csv_format'] ?></a>
                                        <a href="?download=json&id=<?= $route['id'] ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>"><?= $lang_array['json_format'] ?></a>
                                    </div>
                                </div>
                                <button class="action-btn" onclick="if(confirm('<?= $lang_array['confirm_delete'] ?>')) deleteRoute('<?= $route['id'] ?>')" title="<?= $lang_array['delete'] ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <a href="?page=<?= max(1, $pagination['current_page'] - 1) ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" data-partial-nav="1" class="pagination-btn <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>"><?= $lang_array['previous'] ?></a>
                <span><?= $lang_array['page'] ?> <?= $pagination['current_page'] ?> <?= $lang_array['of'] ?> <?= $pagination['total_pages'] ?></span>
                <a href="?page=<?= min($pagination['total_pages'], $pagination['current_page'] + 1) ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" data-partial-nav="1" class="pagination-btn <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>"><?= $lang_array['next'] ?></a>
            </div>

            <div class="stats-overlay" id="stats-overlay">
                <div class="stats-popup" id="stats-popup">
                    <div class="stats-popup-header">
                        <div class="stats-popup-title" id="stats-popup-title"></div>
                        <button class="stats-popup-close" id="stats-popup-close" aria-label="Close"><svg viewBox="0 0 24 24" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
                    </div>
                    <div class="stats-body" id="stats-body"></div>
                </div>
            </div>
            
            <div id="settings-modal" class="modal">
                <div class="modal-content">
                    <button type="button" class="modal-close" id="close-settings-modal" aria-label="Close modal">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <h2><?= $lang_array['settings'] ?></h2>
                    <div class="user-info-modal"><?= $lang_array['user_info'] ?><?= htmlspecialchars($currentUser) ?> <span style="font-size:0.8em;opacity:0.75;font-weight:normal;">(<?= htmlspecialchars([0=>'User', 1=>'Admin', 2=>'Root'][$currentUserRole] ?? 'User') ?>)</span></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        <div class="form-group">
                            <label for="items_per_page"><?= $lang_array['items_per_page'] ?>:</label>
                            <input type="number" id="items_per_page" name="items_per_page" value="<?= $itemsPerPage ?>" min="1" max="50">
                        </div>
                        <div class="form-group" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button type="submit" class="btn"><?= $lang_array['save'] ?></button>
                        </div>
                    </form>
                    
                    <div class="settings-zone">
                        <div class="settings-zone-header">
                            <svg viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="7"></circle><circle cx="12" cy="12" r="3"></circle><line x1="12" y1="2" x2="12" y2="5"></line><line x1="12" y1="19" x2="12" y2="22"></line><line x1="2" y1="12" x2="5" y2="12"></line><line x1="19" y1="12" x2="22" y2="12"></line></svg>
                            <?= $lang_array['location_sharing'] ?>
                        </div>
                        <div class="settings-zone-body">
                            <div id="settings-token-display-table" style="display: none;">
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;"><?= $lang_array['your_token'] ?></label>
                                <code id="settings-token-code-table" style="display: block; padding: 0.75rem 1rem; background: var(--bg-color); font-size: 0.8rem; word-break: break-all; border-radius: 16px; margin-bottom: 0.5rem;"></code>
                                <p style="font-size: 0.8rem; opacity: 0.7; margin-bottom: 0.75rem;"><?= $lang_array['token_expires'] ?>: <span id="settings-token-expires-table"></span></p>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <button class="btn" id="settings-copy-token-btn-table" style="min-width: auto; padding: 0.5rem 0.75rem; font-size: 0.85rem;"><?= $lang_array['copy_token'] ?></button>
                                    <button class="btn btn-danger" id="settings-revoke-token-btn-table" style="min-width: auto; padding: 0.5rem 0.75rem; font-size: 0.85rem;"><?= $lang_array['revoke_token'] ?></button>
                                </div>
                            </div>
                            <button class="btn" id="settings-generate-token-btn-table" style="width: 100%;"><?= $lang_array['generate_token'] ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="route-action-modal" class="modal">
                <div class="modal-content" style="max-width: 520px;">
                    <button type="button" class="modal-close" id="close-route-action-modal" aria-label="Close modal">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <h2><?= $lang_array['actions'] ?></h2>
                    <form id="route-action-form" style="display:flex;flex-direction:column;gap:0.9rem;">
                        <div class="form-group">
                            <label for="route-action-select"><?= $lang_array['actions'] ?></label>
                            <select id="route-action-select">
                                <option value="rename"><?= $lang_array['rename'] ?></option>
                                <option value="delete"><?= $lang_array['delete'] ?></option>
                            </select>
                        </div>
                        <div class="form-group" id="route-rename-group">
                            <label for="route-rename-input"><?= $lang_array['enter_name'] ?></label>
                            <input type="text" id="route-rename-input" maxlength="255" autocomplete="off">
                        </div>
                        <div class="form-group" style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:flex-end;">
                            <button type="button" class="btn" id="route-action-cancel-btn"><?= $lang_array['cancel'] ?></button>
                            <button type="submit" class="btn" id="route-action-submit-btn"><?= $lang_array['save'] ?></button>
                        </div>
                    </form>
                </div>
            </div>
            <div id="confirm-action-modal" class="modal">
                <div class="modal-content" style="max-width: 460px;">
                    <button type="button" class="modal-close" id="close-confirm-action-modal" aria-label="Close modal">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <h2><?= $lang_array['delete'] ?></h2>
                    <p id="confirm-action-message" style="margin:0.5rem 0 1.15rem 0;line-height:1.45;"><?= $lang_array['confirm_delete'] ?></p>
                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:flex-end;">
                        <button type="button" class="btn" id="confirm-action-cancel-btn"><?= $lang_array['cancel'] ?></button>
                        <button type="button" class="btn btn-danger" id="confirm-action-ok-btn"><?= $lang_array['delete'] ?></button>
                    </div>
                </div>
            </div>
            <div id="location-sharing-modal" class="modal">
                <div class="modal-content">
                    <button type="button" class="modal-close" id="close-location-modal" aria-label="Close modal">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <h2><?= $lang_array['location_sharing'] ?></h2>
                    <div class="user-info-modal"><?= $lang_array['your_token'] ?>: <?= $lang_array['settings'] ?></div>
                    <div id="location-section">
                        <h3><?= $lang_array['last_coordinates'] ?></h3>
                        <div id="location-map" style="width: 100%; height: 300px; border-radius: 24px; margin-bottom: 1rem; display: none;"></div>
                        <div id="location-data" style="display: none;">
                            <div style="border:1px solid var(--glass-border);border-radius:16px;overflow:hidden;background:var(--card-bg);">
                                <table style="width:100%;border-collapse:collapse;">
                                    <tbody>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['latitude'] ?></td><td id="loc-lat" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;font-variant-numeric:tabular-nums;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['longitude'] ?></td><td id="loc-lon" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;font-variant-numeric:tabular-nums;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['accuracy'] ?></td><td id="loc-accuracy" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['device'] ?></td><td id="loc-device" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['speed'] ?></td><td id="loc-speed" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['altitude'] ?></td><td id="loc-altitude" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;border-bottom:1px solid var(--glass-border);"><?= $lang_array['satellites'] ?></td><td id="loc-satellites" style="padding:0.7rem 0.9rem;border-bottom:1px solid var(--glass-border);text-align:right;"></td></tr>
                                        <tr><td style="padding:0.7rem 0.9rem;font-weight:600;opacity:0.78;"><?= $lang_array['timestamp'] ?></td><td id="loc-timestamp" style="padding:0.7rem 0.9rem;text-align:right;"></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div id="loc-telemetry-wrap" style="display: none; margin-top: 1rem;">
                                <p><strong><?= $lang_array['telemetry'] ?>:</strong></p>
                                <div id="loc-telemetry" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.5rem;"></div>
                            </div>
                            <div id="loc-history-wrap" style="display: none; margin-top: 1rem;">
                                <p><strong><?= $lang_array['previous_messages'] ?>:</strong></p>
                                <div id="loc-history-nav" style="display:flex;justify-content:center;align-items:center;gap:0.5rem;margin:0.75rem auto 0.95rem;padding:0.7rem 0.9rem;flex-wrap:wrap;background:var(--card-bg);border:1px solid var(--glass-border);border-radius:14px;max-width:max-content;">
                                    <button type="button" class="btn" id="loc-history-prev" style="min-width: auto; padding: 0.45rem 0.8rem;"><?= $lang_array['previous'] ?></button>
                                    <span id="loc-history-page" style="opacity: 0.75;">1 / 1</span>
                                    <button type="button" class="btn" id="loc-history-next" style="min-width: auto; padding: 0.45rem 0.8rem;"><?= $lang_array['next'] ?></button>
                                    <button type="button" class="btn" id="loc-history-latest" style="min-width: auto; padding: 0.45rem 0.8rem;"><?= $lang_array['last_coordinates'] ?></button>
                                </div>
                            </div>
                        </div>
                        <div id="location-photos" style="display: none; margin-top: 1rem;">
                            <h4><?= $lang_array['location_photos'] ?></h4>
                            <div id="photos-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 0.5rem; margin-top: 0.5rem;"></div>
                        </div>
                        <p id="no-location-msg" style="text-align: center; opacity: 0.6; padding: 2rem;"><?= $lang_array['no_location_data'] ?></p>
                    </div>
                    
                    <div class="form-group" style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem;">
                        <button type="button" class="btn" id="open-settings-from-location"><?= $lang_array['settings'] ?></button>
                    </div>
                </div>
            </div>
        </main>
        <div id="notification-container" class="notification-container"></div>

        <script>
            window.loadRouteViewPartial = window.loadRouteViewPartial || async function(url, pushState = true) {
                const menuWasOpen = document.body.classList.contains('menu-open') || !!document.querySelector('#side-menu.open');
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'fetch'
                    }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const html = await response.text();
                const parser = new DOMParser();
                const nextDoc = parser.parseFromString(html, 'text/html');
                const nextStyle = nextDoc.querySelector('style[data-page-style="route-page"]');
                const currentStyle = document.querySelector('style[data-page-style="route-page"]');
                if (nextStyle && currentStyle) {
                    currentStyle.textContent = nextStyle.textContent;
                }

                document.title = nextDoc.title || document.title;
                document.body.innerHTML = nextDoc.body.innerHTML;

                if (menuWasOpen) {
                    document.body.classList.add('menu-open');
                    const sideMenu = document.getElementById('side-menu');
                    const sideMenuOverlay = document.getElementById('side-menu-overlay');
                    if (sideMenu) {
                        sideMenu.classList.add('open');
                        sideMenu.setAttribute('aria-hidden', 'false');
                    }
                    if (sideMenuOverlay) {
                        sideMenuOverlay.classList.add('open');
                    }
                    sessionStorage.setItem('keepSideMenuOpen', '1');
                }

                if (pushState) {
                    window.history.pushState({}, '', url);
                }

                document.dispatchEvent(new Event('DOMContentLoaded'));
            };

            window.bindPartialNavigation = window.bindPartialNavigation || function() {
                if (window.__mapPartialNavBound) return;
                window.__mapPartialNavBound = true;

                document.addEventListener('click', async (event) => {
                    const link = event.target.closest('a[data-partial-nav="1"]');
                    if (!link || link.classList.contains('disabled')) return;
                    event.preventDefault();
                    try {
                        await window.loadRouteViewPartial(link.href, true);
                    } catch (error) {
                        console.error('Partial navigation failed:', error);
                        window.location.href = link.href;
                    }
                });

                window.addEventListener('popstate', () => {
                    window.location.reload();
                });
            };

            window.bindPartialNavigation();

            const langTexts = <?= json_encode($lang_array) ?>;
            const existingToken = <?= json_encode($existingToken ?: null) ?>;

            document.addEventListener('DOMContentLoaded', () => {
                const menuToggle = document.getElementById('menu-toggle');
                const sideMenu = document.getElementById('side-menu');
                const sideMenuOverlay = document.getElementById('side-menu-overlay');
                const sideMenuClose = document.getElementById('side-menu-close');

                const openSideMenu = () => {
                    document.body.classList.add('menu-open');
                    sideMenu.classList.add('open');
                    sideMenuOverlay.classList.add('open');
                    sideMenu.setAttribute('aria-hidden', 'false');
                };

                const closeSideMenu = () => {
                    document.body.classList.remove('menu-open');
                    sideMenu.classList.remove('open');
                    sideMenuOverlay.classList.remove('open');
                    sideMenu.setAttribute('aria-hidden', 'true');
                    sessionStorage.removeItem('keepSideMenuOpen');
                };

                menuToggle.addEventListener('click', openSideMenu);
                sideMenuClose.addEventListener('click', closeSideMenu);
                sideMenuOverlay.addEventListener('click', closeSideMenu);
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        closeSideMenu();
                    }
                });

                if (sessionStorage.getItem('keepSideMenuOpen') === '1') {
                    openSideMenu();
                }

                sideMenu.querySelectorAll('.menu-switch-option').forEach(element => {
                    element.addEventListener('click', () => {
                        sessionStorage.setItem('keepSideMenuOpen', '1');
                    });
                });

                sideMenu.querySelectorAll('a, button').forEach(element => {
                    if (element.id !== 'side-menu-close') {
                        element.addEventListener('click', () => {
                            if (!element.classList.contains('disabled') && !element.classList.contains('menu-switch-option')) {
                                closeSideMenu();
                            }
                        });
                    }
                });

                // Settings modal
                window.openModal = window.openModal || function(id) {
                    document.getElementById(id).style.display = 'block';
                    document.body.classList.add('modal-open');
                };
                window.closeModal = window.closeModal || function(id) {
                    document.getElementById(id).style.display = 'none';
                    if (!Array.from(document.querySelectorAll('.modal')).some(m => m.style.display === 'block')) {
                        document.body.classList.remove('modal-open');
                    }
                };
                const confirmActionModal=document.getElementById('confirm-action-modal');
                const confirmActionMessage=document.getElementById('confirm-action-message');
                let pendingConfirmResolve=null;
                function askDeleteConfirm(message){
                    confirmActionMessage.textContent=message||langTexts.confirm_delete;
                    openModal('confirm-action-modal');
                    return new Promise(resolve=>{pendingConfirmResolve=resolve;});
                }
                function resolveDeleteConfirm(result){
                    closeModal('confirm-action-modal');
                    if(pendingConfirmResolve){pendingConfirmResolve(result);pendingConfirmResolve=null;}
                }
                document.getElementById('close-confirm-action-modal').addEventListener('click',()=>resolveDeleteConfirm(false));
                document.getElementById('confirm-action-cancel-btn').addEventListener('click',()=>resolveDeleteConfirm(false));
                document.getElementById('confirm-action-ok-btn').addEventListener('click',()=>resolveDeleteConfirm(true));
                confirmActionModal.addEventListener('click',(e)=>{if(e.target===confirmActionModal)resolveDeleteConfirm(false);});
                document.addEventListener('keydown',(e)=>{if(e.key==='Escape'&&confirmActionModal.style.display==='block')resolveDeleteConfirm(false);});

                document.getElementById('settings-btn').addEventListener('click', () => {
                    closeSideMenu();
                    openModal('settings-modal');
                });

                document.getElementById('close-settings-modal').addEventListener('click', () => {
                    closeModal('settings-modal');
                });

                // Search functionality
                document.getElementById('search-input').addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase();
                    document.querySelectorAll('#routes-table tbody tr').forEach(row => {
                        const routeName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                        row.style.display = routeName.includes(searchTerm) ? '' : 'none';
                    });
                });

                // Favorite buttons
                document.querySelectorAll('.favorite-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const routeId = btn.dataset.routeId;
                        toggleFavorite(routeId, btn);
                    });
                });

                (function() {
                    function haversine(lat1, lon1, lat2, lon2) {
                        const R = 6371, toRad = x => x * Math.PI / 180;
                        const dLat = toRad(lat2 - lat1), dLon = toRad(lon2 - lon1);
                        const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;
                        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                    }
                    function fmtDist(km) { return km < 1 ? (km*1000).toFixed(0)+' m' : km.toFixed(2)+' km'; }
                    function fmtCoord(v) { return v.toFixed(5); }
                    function computeStats(pts) {
                        const n = pts.length;
                        let dist = 0, minLat = pts[0][0], maxLat = pts[0][0], minLon = pts[0][1], maxLon = pts[0][1], sumLat = 0, sumLon = 0;
                        for (let i = 0; i < n; i++) {
                            sumLat += pts[i][0]; sumLon += pts[i][1];
                            if (pts[i][0] < minLat) minLat = pts[i][0]; if (pts[i][0] > maxLat) maxLat = pts[i][0];
                            if (pts[i][1] < minLon) minLon = pts[i][1]; if (pts[i][1] > maxLon) maxLon = pts[i][1];
                            if (i > 0) dist += haversine(pts[i-1][0], pts[i-1][1], pts[i][0], pts[i][1]);
                        }
                        return { n, dist, straight: haversine(pts[0][0], pts[0][1], pts[n-1][0], pts[n-1][1]),
                            avgStep: n > 1 ? dist/(n-1)*1000 : 0,
                            minLat, maxLat, minLon, maxLon,
                            centerLat: sumLat/n, centerLon: sumLon/n,
                            spanNS: haversine(minLat, minLon, maxLat, minLon),
                            spanEW: haversine(minLat, minLon, minLat, maxLon) };
                    }
                    function row(label, value) { return `<div class="stats-row"><span class="stats-label">${label}</span><span class="stats-value">${value}</span></div>`; }
                    function group(label) { return `<div class="stats-group-label">${label}</div>`; }
                    const overlay = document.getElementById('stats-overlay');
                    const titleEl = document.getElementById('stats-popup-title');
                    const bodyEl = document.getElementById('stats-body');
                    function openStats(routeId) {
                        const route = routes.find(r => r.id == routeId);
                        if (!route || !route.points || !route.points.length) return;
                        const s = computeStats(route.points);
                        titleEl.textContent = route.name;
                        const fileSizeStr = route.size < 1024 ? route.size+' B' : route.size < 1048576 ? (route.size/1024).toFixed(1)+' KB' : (route.size/1048576).toFixed(2)+' MB';
                        const dateStr = new Date(route.date * 1000).toLocaleDateString();
                        bodyEl.innerHTML =
                            group('<?= $lang_array['file_size'] ?> / <?= $lang_array['file_date'] ?>') +
                            row('<?= $lang_array['file_size'] ?>', fileSizeStr) +
                            row('<?= $lang_array['file_date'] ?>', dateStr) +
                            row('<?= $lang_array['points_count'] ?>', s.n.toLocaleString()) +
                            group('<?= $lang_array['total_distance'] ?>') +
                            row('<?= $lang_array['total_distance'] ?>', fmtDist(s.dist)) +
                            row('<?= $lang_array['straight_distance'] ?>', fmtDist(s.straight)) +
                            row('<?= $lang_array['avg_step'] ?>', s.avgStep.toFixed(1)+' m') +
                            group('<?= $lang_array['coverage'] ?>') +
                            row('<?= $lang_array['coverage'] ?>', fmtDist(s.spanNS)+' × '+fmtDist(s.spanEW)) +
                            row('<?= $lang_array['start_point'] ?>', fmtCoord(route.points[0][0])+', '+fmtCoord(route.points[0][1])) +
                            row('<?= $lang_array['end_point'] ?>', fmtCoord(route.points[route.points.length-1][0])+', '+fmtCoord(route.points[route.points.length-1][1])) +
                            row('<?= $lang_array['center_point'] ?>', fmtCoord(s.centerLat)+', '+fmtCoord(s.centerLon));
                        overlay.classList.add('open');
                        document.body.classList.add('modal-open');
                    }
                    function closeStats() {
                        overlay.classList.remove('open');
                        document.body.classList.remove('modal-open');
                    }
                    document.querySelectorAll('.stats-btn').forEach(btn => {
                        btn.addEventListener('click', e => { e.stopPropagation(); openStats(btn.dataset.routeId); });
                    });
                    document.getElementById('stats-popup-close').addEventListener('click', closeStats);
                    overlay.addEventListener('click', e => { if (e.target === overlay) closeStats(); });
                    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeStats(); });
                })();

                // Get references to buttons and checkbox
                const selectAllBtn = document.getElementById('select-all-btn');
                const deselectAllBtn = document.getElementById('deselect-all-btn');
                const selectAllCheckbox = document.getElementById('select-all-checkbox');
                const downloadSelectedBtn = document.getElementById('download-selected-btn');
                const downloadMenu = document.querySelector('.download-menu');

                // Create modal for alerts
                const alertModal = document.createElement('div');
                alertModal.className = 'modal';
                alertModal.id = 'alert-modal';
                alertModal.innerHTML = `
                    <div class="modal-content" style="text-align: center;">
                        <button type="button" class="modal-close" id="alert-close" aria-label="Close modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        <h2 id="alert-title">${langTexts.notification || 'Notification'}</h2>
                        <p id="alert-message"></p>
                    </div>
                `;
                document.body.appendChild(alertModal);

                // Alert function
                window.showAlert = function(message) {
                    document.getElementById('alert-message').textContent = message;
                    openModal('alert-modal');
                };

                document.getElementById('alert-close').addEventListener('click', () => {
                    closeModal('alert-modal');
                });

                // Close modal when clicking outside
                window.addEventListener('click', (e) => {
                    if (e.target.classList.contains('modal')) {
                        e.target.style.display = 'none';
                    }
                });

                // Function to update selection buttons state
                function updateSelectionButtons() {
                    const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
                    const selectedCount = selectedCheckboxes.length;
                    const totalCheckboxes = document.querySelectorAll('.row-checkbox').length;
                    
                    // Update deselect all button state
                    if (deselectAllBtn) {
                        if (selectedCount === 0) {
                            deselectAllBtn.classList.add('disabled');
                            deselectAllBtn.disabled = true;
                        } else {
                            deselectAllBtn.classList.remove('disabled');
                            deselectAllBtn.disabled = false;
                        }
                    }
                    
                    // Update download selected button state
                    if (downloadSelectedBtn) {
                        if (selectedCount === 0) {
                            downloadSelectedBtn.classList.add('disabled');
                            downloadSelectedBtn.disabled = true;
                        } else {
                            downloadSelectedBtn.classList.remove('disabled');
                            downloadSelectedBtn.disabled = false;
                        }
                    }
                    
                    // Update select all checkbox state
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = selectedCount === totalCheckboxes && totalCheckboxes > 0;
                        selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalCheckboxes;
                    }
                }

                // Toggle download menu on click
                if (downloadSelectedBtn) {
                    downloadSelectedBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        
                        // Don't open if button is disabled
                        if (downloadSelectedBtn.disabled) {
                            return;
                        }
                        
                        // Toggle the download options
                        const downloadOptions = document.querySelector('.download-options');
                        if (downloadOptions) {
                            // Close any other open download menus
                            document.querySelectorAll('.download-options').forEach(menu => {
                                if (menu !== downloadOptions) {
                                    menu.style.display = 'none';
                                }
                            });
                            
                            // Toggle current menu
                            if (downloadOptions.style.display === 'block') {
                                downloadOptions.style.display = 'none';
                            } else {
                                downloadOptions.style.display = 'block';
                            }
                        }
                    });
                }

                // Close download menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.download-menu')) {
                        document.querySelectorAll('.download-options').forEach(menu => {
                            menu.style.display = 'none';
                        });
                    }
                });

                // Checkbox selection with state update
                document.querySelectorAll('.row-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', (e) => {
                        const row = checkbox.closest('tr');
                        if (checkbox.checked) {
                            row.classList.add('selected-row');
                        } else {
                            row.classList.remove('selected-row');
                        }
                        updateSelectionButtons();
                    });
                });

                // Select all checkbox
                if (selectAllCheckbox) {
                    selectAllCheckbox.addEventListener('change', (e) => {
                        const checked = e.target.checked;
                        document.querySelectorAll('.row-checkbox').forEach(cb => {
                            cb.checked = checked;
                            const row = cb.closest('tr');
                            if (checked) {
                                row.classList.add('selected-row');
                            } else {
                                row.classList.remove('selected-row');
                            }
                        });
                        updateSelectionButtons();
                    });
                }

                // Select all button
                if (selectAllBtn) {
                    selectAllBtn.addEventListener('click', () => {
                        document.querySelectorAll('.row-checkbox').forEach(cb => {
                            cb.checked = true;
                            cb.closest('tr').classList.add('selected-row');
                        });
                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = true;
                            selectAllCheckbox.indeterminate = false;
                        }
                        updateSelectionButtons();
                    });
                }

                // Deselect all button
                if (deselectAllBtn) {
                    deselectAllBtn.addEventListener('click', () => {
                        document.querySelectorAll('.row-checkbox').forEach(cb => {
                            cb.checked = false;
                            cb.closest('tr').classList.remove('selected-row');
                        });
                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = false;
                        }
                        updateSelectionButtons();
                    });
                }

                // Row click to view route (only if not clicking on checkbox or buttons)
                document.querySelectorAll('#routes-table tbody tr').forEach(row => {
                    row.addEventListener('click', (e) => {
                        if (!e.target.closest('input') && !e.target.closest('button') && !e.target.closest('.download-menu')) {
                            const routeId = row.dataset.routeId;
                            window.location.href = `?action=view&id=${routeId}&lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>`;
                        }
                    });
                });

                // Initial update of selection buttons
                updateSelectionButtons();

                // Settings modal token generation for table view
                document.getElementById('settings-generate-token-btn-table').addEventListener('click', () => {
                    fetch('/map/api/token', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('settings-token-display-table').style.display = 'block';
                            document.getElementById('settings-token-code-table').textContent = data.token;
                            document.getElementById('settings-token-expires-table').textContent = new Date(data.expires_at).toLocaleString();
                            document.getElementById('settings-generate-token-btn-table').style.display = 'none';
                            showNotification(langTexts.token_generated || 'Token generated successfully!', 'success');
                        } else {
                            showNotification(data.error || langTexts.token_generate_failed || 'Failed to generate token', 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Error generating token:', err);
                        showNotification(langTexts.token_generate_error || 'Error generating token', 'error');
                    });
                });

                document.getElementById('settings-copy-token-btn-table').addEventListener('click', () => {
                    const token = document.getElementById('settings-token-code-table').textContent;
                    copyToClipboard(token);
                });

                document.getElementById('settings-revoke-token-btn-table').addEventListener('click', () => {
                    const token = document.getElementById('settings-token-code-table').textContent;
                    fetch(`/map/api/token/${token}`, { method: 'DELETE' })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('settings-token-display-table').style.display = 'none';
                            document.getElementById('settings-generate-token-btn-table').style.display = '';
                            showNotification(langTexts.token_revoked || 'Token revoked', 'success');
                        }
                    });
                });

                // Show existing token if available in table view
                if (typeof existingToken !== 'undefined' && existingToken) {
                    document.getElementById('settings-token-display-table').style.display = 'block';
                    document.getElementById('settings-token-code-table').textContent = existingToken.token;
                    document.getElementById('settings-token-expires-table').textContent = new Date(existingToken.expires_at).toLocaleString();
                    document.getElementById('settings-generate-token-btn-table').style.display = 'none';
                }
            });

            function toggleFavorite(routeId, btn) {
                fetch('?ajax=toggle_favorite', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${routeId}` })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            btn.classList.toggle('favorited');
                            btn.title = btn.classList.contains('favorited') ? langTexts.unmark_favorite : langTexts.mark_favorite;
                        }
                    });
            }

            async function deleteRoute(routeId) {
                const ok = await askDeleteConfirm(langTexts.confirm_delete);
                if (!ok) return;
                fetch('?ajax=delete', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${routeId}` })
                    .then(response => response.json())
                    .then(data => { if (data.success) location.reload(); });
            }

            function downloadSelected(format) {
                const selectedIds = [];
                document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
                    selectedIds.push(cb.value);
                });

                if (selectedIds.length === 0) {
                    showNotification(langTexts.no_routes_selected || 'No routes selected', 'warning');
                    return false;
                }

                fetch('?ajax=download_selected', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ids=${encodeURIComponent(JSON.stringify(selectedIds))}&format=${format}`
                })
                .then(response => {
                    if (response.ok) {
                        return response.blob();
                    }
                    throw new Error('Network response was not ok.');
                })
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `selected_routes.${format}`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);

                    // Close the download menu after selection
                    document.querySelectorAll('.download-options').forEach(menu => {
                        menu.style.display = 'none';
                    });
                })
                .catch(error => console.error('Error downloading selected routes:', error));

                return false;
            }
            
            // Clipboard helper with fallback
            function copyToClipboard(text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(() => {
                        showNotification(langTexts.token_copied, 'success');
                    }).catch(() => {
                        fallbackCopyText(text);
                    });
                } else {
                    fallbackCopyText(text);
                }
            }
            
            function fallbackCopyText(text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.left = '-999999px';
                textarea.style.top = '-999999px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                try {
                    document.execCommand('copy');
                    showNotification(langTexts.token_copied, 'success');
                } catch (err) {
                    showNotification('Failed to copy token', 'error');
                }
                document.body.removeChild(textarea);
            }
            
            // Notification system for table view
            function showNotification(message, type = 'info', title = null) {
                let container = document.getElementById('notification-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'notification-container';
                    container.className = 'notification-container';
                    document.body.appendChild(container);
                }
                
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                
                const icons = {
                    success: '✓',
                    error: '✕',
                    warning: '⚠',
                    info: 'ℹ'
                };
                
                const titles = {
                    success: langTexts.success || 'Success',
                    error: langTexts.error || 'Error',
                    warning: langTexts.warning || 'Warning',
                    info: langTexts.info || 'Info'
                };
                
                notification.innerHTML = `
                    <div class="notification-icon">${icons[type] || icons.info}</div>
                    <div class="notification-content">
                        <div class="notification-title">${title || titles[type] || titles.info}</div>
                        <div class="notification-message">${message}</div>
                    </div>
                    <button class="notification-close" onclick="this.parentElement.classList.add('removing'); setTimeout(() => this.parentElement.remove(), 300);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                `;
                
                container.appendChild(notification);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.classList.add('removing');
                        setTimeout(() => notification.remove(), 300);
                    }
                }, 5000);
            }

            // Location Sharing functionality
            let locationMap = null;
            let locationMarker = null;
            let locationHistory = [];
            let locationHistoryIndex = 0;
            let telemetrySeries = {};
            let telemetryChartPopover = null;
            let telemetryChartPinned = false;

            function formatTelemetryValue(value) {
                if (value === null || value === undefined || value === '') return langTexts.na || 'N/A';
                if (typeof value === 'number') return Number.isInteger(value) ? String(value) : value.toFixed(2);
                if (typeof value === 'boolean') return value ? (langTexts.yes || 'Yes') : (langTexts.no || 'No');
                if (typeof value === 'object') return JSON.stringify(value);
                return String(value);
            }

            function formatTelemetryLabel(key) {
                return String(key).replace(/_/g, ' ').replace(/\b\w/g, m => m.toUpperCase());
            }
            function extractNumericValue(value) {
                if (typeof value === 'number' && Number.isFinite(value)) return value;
                if (typeof value !== 'string') return null;
                const normalized = value.replace(',', '.');
                const match = normalized.match(/-?\d+(\.\d+)?/);
                if (!match) return null;
                const n = Number(match[0]);
                return Number.isFinite(n) ? n : null;
            }
            function buildTelemetrySeries(locations) {
                telemetrySeries = {};
                if (!Array.isArray(locations)) return;
                locations.forEach(entry => {
                    const telemetry = (entry && entry.telemetry && typeof entry.telemetry === 'object') ? entry.telemetry : {};
                    Object.entries(telemetry).forEach(([key, val]) => {
                        const n = extractNumericValue(val);
                        if (n === null) return;
                        if (!telemetrySeries[key]) telemetrySeries[key] = [];
                        telemetrySeries[key].push(n);
                    });
                });
            }
            function createSparklineSvg(values) {
                if (!Array.isArray(values) || values.length < 2) return '';
                const w = 220;
                const h = 80;
                const p = 8;
                const min = Math.min(...values);
                const max = Math.max(...values);
                const range = max - min || 1;
                const points = values.map((v, i) => {
                    const x = p + (i * (w - p * 2)) / (values.length - 1);
                    const y = h - p - ((v - min) / range) * (h - p * 2);
                    return `${x.toFixed(2)},${y.toFixed(2)}`;
                }).join(' ');
                return `<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" style="display:block"><polyline points="${points}" fill="none" stroke="var(--primary-color)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="${points.split(' ').at(-1).split(',')[0]}" cy="${points.split(' ').at(-1).split(',')[1]}" r="3.2" fill="var(--primary-color)"/></svg>`;
            }
            function formatChartNumber(value) {
                const fixed = Number(value).toFixed(2);
                if (fixed.endsWith('00')) return Number(fixed).toFixed(1);
                if (fixed.endsWith('0')) return fixed.slice(0, -1);
                return fixed;
            }
            function ensureTelemetryChartPopover() {
                if (telemetryChartPopover) return telemetryChartPopover;
                telemetryChartPopover = document.createElement('div');
                telemetryChartPopover.style.position = 'fixed';
                telemetryChartPopover.style.zIndex = '1800';
                telemetryChartPopover.style.display = 'none';
                telemetryChartPopover.style.minWidth = '240px';
                telemetryChartPopover.style.maxWidth = '280px';
                telemetryChartPopover.style.padding = '0.65rem 0.7rem';
                telemetryChartPopover.style.borderRadius = '12px';
                telemetryChartPopover.style.background = 'var(--card-bg)';
                telemetryChartPopover.style.border = '1px solid var(--glass-border)';
                telemetryChartPopover.style.boxShadow = '0 12px 30px rgba(0,0,0,0.22)';
                telemetryChartPopover.style.pointerEvents = 'none';
                document.body.appendChild(telemetryChartPopover);
                document.addEventListener('click', e => {
                    if (!telemetryChartPinned) return;
                    if (!telemetryChartPopover.contains(e.target) && !e.target.closest('[data-telemetry-key]')) {
                        telemetryChartPinned = false;
                        telemetryChartPopover.style.display = 'none';
                    }
                });
                return telemetryChartPopover;
            }
            function showTelemetryChart(target, key, pin = false) {
                const values = telemetrySeries[key];
                if (!values || values.length < 2) return;
                const box = ensureTelemetryChartPopover();
                telemetryChartPinned = !!pin;
                box.style.pointerEvents = telemetryChartPinned ? 'auto' : 'none';
                const avg = values.reduce((sum, v) => sum + v, 0) / values.length;
                box.innerHTML = `<div style="font-size:0.78rem;opacity:0.82;margin-bottom:0.35rem;">${formatTelemetryLabel(key)}</div>${createSparklineSvg(values)}<div style="display:flex;justify-content:space-between;font-size:0.73rem;opacity:0.75;margin-top:0.2rem;"><span>${langTexts.min || 'min'} ${formatChartNumber(Math.min(...values))}</span><span>${langTexts.avg || 'avg'} ${formatChartNumber(avg)}</span><span>${langTexts.max || 'max'} ${formatChartNumber(Math.max(...values))}</span></div>`;
                const rect = target.getBoundingClientRect();
                const top = Math.max(10, rect.top - 110);
                const left = Math.max(10, Math.min(window.innerWidth - 290, rect.left + rect.width / 2 - 130));
                box.style.top = `${top}px`;
                box.style.left = `${left}px`;
                box.style.display = 'block';
            }
            function hideTelemetryChart() {
                const box = ensureTelemetryChartPopover();
                if (telemetryChartPinned) return;
                box.style.display = 'none';
            }
            function bindTelemetryChartEvents(card, key) {
                card.dataset.telemetryKey = key;
                card.style.cursor = 'pointer';
                card.addEventListener('mouseenter', () => showTelemetryChart(card, key, false));
                card.addEventListener('mouseleave', hideTelemetryChart);
                card.addEventListener('click', e => {
                    e.stopPropagation();
                    showTelemetryChart(card, key, true);
                });
                card.addEventListener('touchstart', e => {
                    e.stopPropagation();
                    showTelemetryChart(card, key, true);
                }, { passive: true });
            }
            function renderTelemetryDetails(loc) {
                const telemetryWrap = document.getElementById('loc-telemetry-wrap');
                const telemetryBox = document.getElementById('loc-telemetry');
                telemetryBox.innerHTML = '';
                const telemetry = (loc && loc.telemetry && typeof loc.telemetry === 'object') ? { ...loc.telemetry } : {};
                ['latitude', 'longitude', 'accuracy', 'message', 'device_name', 'device_type', 'speed', 'altitude', 'satellites'].forEach(key => delete telemetry[key]);
                const entries = Object.entries(telemetry);
                if (!entries.length) {
                    telemetryWrap.style.display = 'none';
                    return;
                }
                entries.forEach(([key, value]) => {
                    const card = document.createElement('div');
                    card.style.padding = '0.7rem 0.8rem';
                    card.style.borderRadius = '14px';
                    card.style.background = 'linear-gradient(135deg, var(--bg-color), color-mix(in srgb, var(--bg-color) 78%, var(--primary-color) 22%))';
                    card.style.border = '1px solid color-mix(in srgb, var(--primary-color) 45%, transparent)';
                    card.innerHTML = `<span style="display:block;font-size:0.74rem;opacity:0.75;letter-spacing:0.04em;text-transform:uppercase;">${formatTelemetryLabel(key)}</span><span style="display:block;font-size:0.98rem;font-weight:600;margin-top:0.18rem;">${formatTelemetryValue(value)}</span>`;
                    if ((telemetrySeries[key] || []).length > 1 && extractNumericValue(value) !== null) bindTelemetryChartEvents(card, key);
                    telemetryBox.appendChild(card);
                });
                telemetryWrap.style.display = 'block';
            }
            function applyLocationEntry(loc) {
                document.getElementById('loc-lat').textContent = loc.latitude.toFixed(6);
                document.getElementById('loc-lon').textContent = loc.longitude.toFixed(6);
                document.getElementById('loc-accuracy').textContent = loc.accuracy ? `${loc.accuracy.toFixed(0)}m` : 'N/A';
                document.getElementById('loc-device').textContent = [loc.device_name, loc.device_type].filter(Boolean).join(' / ') || 'N/A';
                document.getElementById('loc-speed').textContent = loc.speed !== null && loc.speed !== undefined ? `${Number(loc.speed).toFixed(1)} km/h` : 'N/A';
                document.getElementById('loc-altitude').textContent = loc.altitude !== null && loc.altitude !== undefined ? `${Number(loc.altitude).toFixed(1)} m` : 'N/A';
                document.getElementById('loc-satellites').textContent = loc.satellites ?? 'N/A';
                document.getElementById('loc-timestamp').textContent = new Date(loc.timestamp).toLocaleString();
                renderTelemetryDetails(loc);
            }
            function renderLocationPhoto(loc) {
                if (loc.photo_path) {
                    document.getElementById('location-photos').style.display = 'block';
                    const photosGrid = document.getElementById('photos-grid');
                    photosGrid.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = loc.photo_path;
                    img.style.width = '100%';
                    img.style.height = '150px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '4px';
                    img.style.cursor = 'pointer';
                    img.onclick = () => window.open(loc.photo_path, '_blank');
                    photosGrid.appendChild(img);
                } else {
                    document.getElementById('location-photos').style.display = 'none';
                }
            }
            function updateMapLocation(loc) {
                if (!locationMap) return;
                locationMap.setView([loc.latitude, loc.longitude], 15);
                if (locationMarker) locationMap.removeLayer(locationMarker);
                locationMarker = L.marker([loc.latitude, loc.longitude]).addTo(locationMap);
            }
            function updateLocationHistoryCard() {
                const historyWrap = document.getElementById('loc-history-wrap');
                const prevBtn = document.getElementById('loc-history-prev');
                const nextBtn = document.getElementById('loc-history-next');
                const latestBtn = document.getElementById('loc-history-latest');
                const pageLabel = document.getElementById('loc-history-page');
                if (!locationHistory.length) {
                    historyWrap.style.display = 'none';
                    return;
                }
                const currentEntry = locationHistory[locationHistoryIndex];
                applyLocationEntry(currentEntry);
                renderLocationPhoto(currentEntry);
                updateMapLocation(currentEntry);
                pageLabel.textContent = `${locationHistoryIndex + 1} / ${locationHistory.length}`;
                prevBtn.disabled = locationHistoryIndex >= locationHistory.length - 1;
                nextBtn.disabled = locationHistoryIndex <= 0;
                latestBtn.disabled = locationHistoryIndex === 0;
                historyWrap.style.display = 'block';
            }
            function renderLocationHistory(locations) {
                const historyWrap = document.getElementById('loc-history-wrap');
                const prevBtn = document.getElementById('loc-history-prev');
                const nextBtn = document.getElementById('loc-history-next');
                const latestBtn = document.getElementById('loc-history-latest');
                locationHistory = Array.isArray(locations) ? locations : [];
                buildTelemetrySeries(locationHistory);
                locationHistoryIndex = 0;
                prevBtn.onclick = () => {
                    if (locationHistoryIndex < locationHistory.length - 1) {
                        locationHistoryIndex += 1;
                        updateLocationHistoryCard();
                    }
                };
                nextBtn.onclick = () => {
                    if (locationHistoryIndex > 0) {
                        locationHistoryIndex -= 1;
                        updateLocationHistoryCard();
                    }
                };
                latestBtn.onclick = () => {
                    locationHistoryIndex = 0;
                    updateLocationHistoryCard();
                };
                if (!locationHistory.length) {
                    historyWrap.style.display = 'none';
                    return;
                }
                updateLocationHistoryCard();
            }
            
            document.getElementById('location-sharing-btn').addEventListener('click', () => {
                openModal('location-sharing-modal');
                loadLocationData();
            });
            
            document.getElementById('open-settings-from-location').addEventListener('click', () => {
                document.getElementById('location-sharing-modal').style.display = 'none';
                openModal('settings-modal');
            });
            
            document.getElementById('close-location-modal').addEventListener('click', () => {
                closeModal('location-sharing-modal');
            });
            
            function loadLocationData() {
                fetch('/map/api/tokens')
                .then(response => response.json())
                .then(tokenData => {
                    if (tokenData.success && tokenData.tokens.length > 0) {
                        const token = tokenData.tokens[0].token;
                        return fetch(`/map/api/location?token=${token}&limit=50`);
                    } else {
                        document.getElementById('no-location-msg').style.display = 'block';
                        document.getElementById('location-map').style.display = 'none';
                        document.getElementById('location-data').style.display = 'none';
                        document.getElementById('location-photos').style.display = 'none';
                        document.getElementById('loc-telemetry-wrap').style.display = 'none';
                        document.getElementById('loc-history-wrap').style.display = 'none';
                        return null;
                    }
                })
                .then(response => response ? response.json() : null)
                .then(data => {
                    if (data && data.success && data.locations.length > 0) {
                        const loc = data.locations[0];
                        document.getElementById('no-location-msg').style.display = 'none';
                        document.getElementById('location-map').style.display = 'block';
                        document.getElementById('location-data').style.display = 'block';
                        if (!locationMap) {
                            const locationMapEl = document.getElementById('location-map');
                            if (locationMapEl) {
                                locationMap = L.map(locationMapEl).setView([loc.latitude, loc.longitude], 15);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'<?= $theme === 'dark' ? ", { className: 'osm-dark-tiles' }" : '' ?>).addTo(locationMap);
                            }
                        }
                        renderLocationHistory(data.locations);
                        setTimeout(() => {
                            locationMap.invalidateSize();
                        }, 100);
                    } else if (data) {
                        document.getElementById('no-location-msg').style.display = 'block';
                        document.getElementById('location-map').style.display = 'none';
                        document.getElementById('location-data').style.display = 'none';
                        document.getElementById('location-photos').style.display = 'none';
                        document.getElementById('loc-telemetry-wrap').style.display = 'none';
                        document.getElementById('loc-history-wrap').style.display = 'none';
                    }
                })
                .catch(err => {
                    console.error('Error loading location data:', err);
                    document.getElementById('no-location-msg').textContent = langTexts.location_load_error || 'Error loading location data';
                    document.getElementById('no-location-msg').style.display = 'block';
                    document.getElementById('location-map').style.display = 'none';
                    document.getElementById('location-data').style.display = 'none';
                    document.getElementById('location-photos').style.display = 'none';
                    document.getElementById('loc-telemetry-wrap').style.display = 'none';
                    document.getElementById('loc-history-wrap').style.display = 'none';
                });
            }
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
