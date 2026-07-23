<?php
$nameConfig = json_decode(file_get_contents(__DIR__ . '/../name.json'), true) ?: [];
$menuButton = $nameConfig['menu_button'] ?? [];
$menuSafeWidth = (int)($menuButton['left'] ?? 16) + (int)($menuButton['width'] ?? 40) + 10;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($translations['expense_tracker_title']) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <style>
        :root {
            --bg-color: <?= $theme==='dark'?'#1a1a1a':'#fff' ?>;
            --text-color: <?= $theme==='dark'?'#fff':'#000' ?>;
            --primary-color: <?= htmlspecialchars($primaryColor) ?>;
            --modal-bg: <?= $theme==='dark'?'#2a2a2a':'#fff' ?>;
            --border-color: <?= $theme==='dark'?'#444':'#ccc' ?>;
            --group-block-bg: <?= $theme==='dark'?'#333':'#e9e9e9' ?>;
            --primary-color-rgb: <?= implode(', ', sscanf($primaryColor, "#%02x%02x%02x")) ?>;
            --danger-color: #dc3545; --danger-hover: #c82333;
            --scrollbar-track: <?= $theme==='dark'?'#1f1f1f':'#f1f4f8' ?>;
            --scrollbar-thumb: <?= $theme==='dark'?'#555':'#8a97ab' ?>;
            --scrollbar-thumb-hover: <?= $theme==='dark'?'#6b6b6b':'#6f8098' ?>;
            --top-bar-height: 64px;
            --menu-safe-width: <?= $menuSafeWidth ?>px;
        }
        
        /* Custom scrollbar styling */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        ::-webkit-scrollbar-track {
            background: var(--scrollbar-track);
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--scrollbar-thumb-hover);
        }
        
        /* Firefox scrollbar */
        * {
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
        }
        
        body { margin:0; padding:0; font-family:Arial; background:var(--bg-color); color:var(--text-color); overflow-x:hidden; }
        body.modal-open { overflow:hidden!important; }
        .app-expenses { max-width:100%; margin:auto; position:relative; box-sizing:border-box; padding:calc(var(--top-bar-height) + 10px) 10px 10px; }
        .header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; position:fixed; top:0; left:0; right:0; z-index:900; min-height:var(--top-bar-height); margin:0; padding:10px 10px 10px calc(var(--menu-safe-width) + 10px); box-sizing:border-box; background:var(--bg-color); border-bottom:1px solid var(--border-color); box-shadow:0 2px 8px rgba(0,0,0,.08); }
        .header h2 { margin:0; white-space:nowrap; font-size:1.2rem; min-width:0; overflow:hidden; text-overflow:ellipsis; flex:1 1 auto; }
        .header-buttons { display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end; }
        .header-btn { background:none; border:none; cursor:pointer; font-size:20px; color:var(--text-color); padding:6px; border-radius:4px; transition:background 0.2s; display:flex; align-items:center; justify-content:center; overflow:visible; }
        .header-btn:hover { background:rgba(var(--primary-color-rgb),0.2); }
        .add-menu-wrap { position:relative; display:inline-block; }
        .import-dropdown { display:none; position:absolute; top:100%; left:0; margin-top:4px; min-width:min(96vw,320px); background:var(--modal-bg); border:1px solid var(--border-color); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.22); z-index:3000; padding:6px 0; }
        .import-dropdown button { display:block; width:100%; text-align:left; padding:10px 14px; border:none; background:transparent; color:var(--text-color); cursor:pointer; font-size:14px; }
        .import-dropdown button:hover { background:rgba(var(--primary-color-rgb),.18); }
        #bank-csv-import-modal { background:rgba(0,0,0,0.75); padding:0!important; box-sizing:border-box; align-items:stretch; justify-content:stretch; inset:0; }
        #bank-csv-import-modal .modal-content { width:100vw!important; height:100vh!important; max-width:none!important; max-height:none!important; border-radius:0!important; padding:10px 12px 14px!important; box-sizing:border-box; display:flex; flex-direction:column; overflow:hidden; }
        #bank-csv-import-modal #bank-csv-scroll { flex:1; min-height:0; overflow:auto; padding:0 8px 8px 0; box-sizing:border-box; }
        .bank-csv-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; table-layout:fixed; color:var(--text-color); }
        .bank-csv-table th, .bank-csv-table td { border-bottom:1px solid var(--border-color); padding:10px 8px; text-align:left; vertical-align:middle; word-break:break-word; box-sizing:border-box; }
        .bank-csv-table th { background:rgba(var(--primary-color-rgb),.22); font-weight:600; font-size:12px; }
        .bank-csv-th-num { text-align:right!important; }
        .bank-csv-col-inc { width:56px; }
        .bank-csv-col-dates { width:min(32vw,220px); }
        .bank-csv-col-store { width:min(28vw,200px); }
        .bank-csv-col-amt { width:104px; }
        .bank-csv-td-inc { text-align:center!important; padding-left:10px!important; }
        input.bank-csv-inc[type=checkbox] { appearance:none; -webkit-appearance:none; width:28px; height:28px; margin:0; border:2px solid var(--border-color); border-radius:8px; background:var(--group-block-bg); cursor:pointer; vertical-align:middle; position:relative; transition:background .15s,border-color .15s,box-shadow .15s; box-sizing:border-box; }
        input.bank-csv-inc[type=checkbox]:hover { border-color:rgba(var(--primary-color-rgb),.65); box-shadow:0 0 0 2px rgba(var(--primary-color-rgb),.2); }
        input.bank-csv-inc[type=checkbox]:checked { background:var(--primary-color); border-color:var(--primary-color); }
        input.bank-csv-inc[type=checkbox]:checked::after { content:''; position:absolute; left:9px; top:5px; width:7px; height:12px; border:solid #fff; border-width:0 2.5px 2.5px 0; transform:rotate(45deg); }
        .bank-csv-td-label strong { display:block; font-size:13px; }
        .bank-csv-inc-tag { font-size:11px; font-weight:500; opacity:.88; }
        .bank-csv-td-dates { font-size:11px; opacity:.92; line-height:1.35; }
        .bank-csv-n { font-weight:600; opacity:1; display:block; margin-bottom:2px; }
        .bank-csv-dates { opacity:.9; }
        .bank-csv-td-sel .bank-csv-sel { width:100%; max-width:100%; padding:8px; box-sizing:border-box; background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); border-radius:6px; }
        .bank-csv-td-amt { text-align:right!important; font-variant-numeric:tabular-nums; font-weight:700; white-space:nowrap; padding-right:12px!important; }
        @media(max-width:640px){ .bank-csv-table { font-size:12px; } .bank-csv-table th, .bank-csv-table td { padding:8px 5px; } .bank-csv-col-dates { width:36vw; } }
        .bulk-delete-intro { font-size:13px; opacity:.92; margin:0 0 10px; line-height:1.45; }
        .bulk-delete-hint { font-size:12px; opacity:.85; margin:8px 0 12px; line-height:1.4; }
        .bulk-delete-danger { color:var(--danger-color); font-weight:600; margin:0 0 10px; line-height:1.4; }
        #settings-section-delete .confirm-btn { margin-top:12px; }
        #settings-modal .modal-content { width:min(1100px,96vw); max-width:min(1100px,96vw); min-height:480px; }
        #settings-modal .store-list { max-width:100%; }
        .chart-container { margin:20px 0; display:flex; align-items:center; justify-content:space-between; flex-wrap:nowrap; max-width:80%; margin-left:auto; margin-right:auto; }
        .chart-nav-btn, .chart-toggle-btn { background:rgba(200,200,200,0.3); color:var(--text-color); border:none; padding:8px 12px; cursor:pointer; flex:0 0 auto; border-radius:4px; font-size:16px; transition:background 0.2s; }
        .chart-nav-btn:hover, .chart-toggle-btn:hover { background:rgba(200,200,200,0.5); }
        .chart-nav-btn:disabled { opacity:.35; cursor:not-allowed; }
        #expenses-chart { max-width:100%; height:300px; flex:1 1 auto; }
        .chart-year { text-align:center; margin-top:10px; color:var(--text-color); position:absolute; left:50%; transform:translateX(-50%); }
        .divider { height:2px; background:repeating-linear-gradient(90deg,#ffd700,#ffd700 4px,transparent 4px,transparent 8px); margin:20px 0; }
        .controls-container { display:flex; justify-content:space-between; align-items:center; margin:10px 0; }
        .search-container { position:relative; display:inline-block; }
        .search-box { padding:5px 5px 5px 30px; width:200px; box-sizing:border-box; background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); border-radius:8px; }
        .search-box:focus { outline:1px solid var(--primary-color); background:var(--modal-bg); }
        .search-icon { position:absolute; left:8px; top:50%; transform:translateY(-50%); color:#666; pointer-events:none; }
        .add-date-btn { background:var(--primary-color); color:white; border:none; padding:5px 10px; cursor:pointer; border-radius:4px; transition:opacity 0.2s; }
        .add-date-btn:hover { opacity:0.9; }
        .data-table-container { overflow-x:auto; margin:10px 0; max-width:100%; max-height:calc(100vh - var(--top-bar-height) - 20px); overflow-y:auto; }
        .data-table { width:100%; border-collapse:collapse; table-layout:auto; }
        .data-table th, .data-table td { border:1px solid var(--border-color); padding:5px; text-align:center; min-width:80px; box-sizing:border-box; }
        .data-table th.income-separator, .data-table td.income-separator { border-left:3px solid rgba(var(--primary-color-rgb),.45)!important; }
        .data-table th.income-separator { background:var(--primary-color)!important; }
        .data-table td.income-separator { background:transparent; }
        .income-switch-row { display:flex; align-items:center; gap:12px; font-weight:600; }
        .income-switch { position:relative; width:52px; height:30px; display:inline-block; flex:0 0 auto; }
        .income-switch input { opacity:0; width:0; height:0; position:absolute; }
        .income-slider { position:absolute; inset:0; cursor:pointer; background:rgba(148,163,184,.45); border-radius:999px; transition:.25s ease; border:1px solid rgba(255,255,255,.18); }
        .income-slider:before { content:''; position:absolute; width:24px; height:24px; left:3px; top:2px; background:#fff; border-radius:999px; box-shadow:0 3px 8px rgba(0,0,0,.25); transition:.25s ease; }
        .income-switch input:checked + .income-slider { background:linear-gradient(135deg,rgba(var(--primary-color-rgb),.95),rgba(34,197,94,.95)); }
        .income-switch input:checked + .income-slider:before { transform:translateX(21px); }
        .data-table th { background:var(--primary-color); color:white; position:sticky; top:0; z-index:10; box-shadow:0 2px 2px -1px rgba(0,0,0,0.1); }
        .data-table td input { width:100%; border:none; text-align:center; background:transparent; color:var(--text-color); box-sizing:border-box; }
        .data-table td input:focus { outline:1px solid var(--primary-color); background:var(--group-block-bg); }
        .data-table td:first-child { white-space:nowrap!important; min-width:100px!important; max-width:120px!important; }
        .data-table .store-header { display:flex; align-items:center; justify-content:center; gap:5px; }
        .data-table tbody tr:hover { background-color:rgba(var(--primary-color-rgb),0.1)!important; }
        .data-table tbody tr.current-date-row { background-color:rgba(var(--primary-color-rgb),0.15)!important; border-left:3px solid var(--primary-color); }
        .data-table tbody tr.highlight-row { background-color:rgba(var(--primary-color-rgb),0.05); }
        .data-table th.highlight-column, .data-table td.highlight-column { background-color:rgba(var(--primary-color-rgb),0.1); }
        .data-table td.highlight-date { background-color:var(--primary-color)!important; color:white!important; font-weight:bold; }
        .store-icon, .user-icon { width:30px; height:30px; border-radius:50%; object-fit:cover; }
        .profile-avatar-btn { border:1px solid var(--primary-color); border-radius:50%; padding:2px; }
        .settings-menu-bar { display:flex; flex-wrap:wrap; justify-content:center; gap:0; border:1px solid var(--border-color); border-radius:10px; overflow:hidden; margin:0 auto 14px; width:fit-content; max-width:100%; }
        .settings-menu-btn { background:var(--group-block-bg); color:var(--text-color); border:none; border-right:1px solid var(--border-color); padding:8px 14px; cursor:pointer; font-size:14px; }
        .settings-menu-btn:last-child { border-right:none; }
        .settings-menu-btn.active { background:var(--primary-color); color:#fff; }
        .settings-section { display:none; }
        .settings-section.active { display:block; }
        .income-header.hidden, .income-separator.hidden { display:none!important; }
        .modal { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.62); backdrop-filter:blur(8px); display:none; justify-content:center; align-items:center; z-index:1000; padding:14px; box-sizing:border-box; }
        #import-modal { background:rgba(0,0,0,0.75); padding:0!important; box-sizing:border-box; align-items:stretch; justify-content:stretch; inset:0; }
        #import-modal .modal-content { width:100vw!important; height:100vh!important; max-width:none!important; max-height:none!important; border-radius:0!important; padding:0!important; box-sizing:border-box; overflow:hidden; }
        #import-modal .modal-close:not(.ocr-shell-close) { top:50%!important; right:14px!important; transform:translateY(-50%)!important; width:40px; height:40px; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; padding:0!important; }
        .ocr-shell { position:relative; display:flex; flex-direction:column; height:100%; background:linear-gradient(180deg,var(--modal-bg),var(--group-block-bg)); }
        .ocr-shell .ocrw-shell { padding-top:68px!important; }
        .ocr-shell-close { position:absolute!important; top:12px!important; right:12px!important; transform:none!important; z-index:30; flex:0 0 auto; color:#fff!important; background:linear-gradient(135deg,rgba(var(--primary-color-rgb),.9),rgba(var(--primary-color-rgb),.65))!important; border:1px solid rgba(255,255,255,.35)!important; width:42px; height:42px; border-radius:12px; box-shadow:0 10px 24px rgba(0,0,0,.28); backdrop-filter:blur(8px); }
        .ocr-shell-close:hover { filter:brightness(1.08); }
        .ocr-shell-close:active { transform:translateY(1px)!important; }
        .modal-content { background:linear-gradient(180deg,var(--modal-bg),var(--group-block-bg)); padding:20px; border-radius:20px; max-width:90%; max-height:90vh; overflow-y:auto; color:var(--text-color); position:relative; border:1px solid rgba(255,255,255,.12); box-shadow:0 24px 70px rgba(0,0,0,.28); }
        .modal-close { position:absolute; top:12px; right:12px; border:none; font-size:22px; cursor:pointer; color:#fff; width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,rgba(var(--primary-color-rgb),.9),rgba(var(--primary-color-rgb),.65)); border:1px solid rgba(255,255,255,.35); box-shadow:0 10px 24px rgba(0,0,0,.28); }
        .modal-close:hover { filter:brightness(1.08); }
        .store-list { margin:10px 0; max-width:600px; }
        .store-item { display:flex; align-items:center; gap:10px; margin:5px 0; padding:10px; background:var(--group-block-bg); border-radius:4px; }
        .store-item input, .modal-content input, .modal-content select { flex:1; padding:8px; margin:5px 0; background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); border-radius:4px; }
        .store-item input:focus, .modal-content input:focus, .modal-content select:focus { outline:1px solid var(--primary-color); background:var(--modal-bg); }
        .add-store-btn, .edit-store-btn { background:var(--primary-color); color:white; border:none; padding:8px 12px; cursor:pointer; margin:5px 0; border-radius:4px; transition:opacity 0.2s; }
        .add-store-btn:hover, .edit-store-btn:hover { opacity:0.9; }
        .delete-store-btn { background:var(--danger-color); color:white; border:none; padding:8px 12px; cursor:pointer; margin:5px 0; border-radius:4px; display:inline-flex; align-items:center; gap:5px; transition:background .2s; }
        .delete-store-btn:hover { background:var(--danger-hover); }
        .delete-store-btn svg { width:16px; height:16px; fill:currentColor; }
        .cropper-container { max-width:100%; max-height:300px; margin:10px 0; overflow:hidden; }
        .cropper-container img { max-width:100%; max-height:300px; }
        .date-picker-container { display:flex; flex-direction:column; gap:10px; }
        .date-scroll { display:flex; gap:10px; justify-content:center; }
        .date-scroll select { width:100px; }
        .form-row { display:flex; flex-direction:column; margin-bottom:10px; }
        .form-row label { margin-bottom:5px; font-weight:bold; }
        .notification-container { position:fixed; bottom:20px; right:20px; z-index:2000; }
        .amount-currency-popover { position:fixed; z-index:2100; background:var(--group-block-bg); border:1px solid var(--border-color); border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.18); padding:4px 6px; font-size:11px; color:var(--text-color); max-width:min(100vw - 16px,200px); }
        .amount-currency-popover-title { font-weight:600; margin-bottom:4px; opacity:.9; font-size:11px; line-height:1.2; }
        .amount-currency-popover-btns { display:flex; flex-wrap:wrap; gap:4px; }
        .amount-currency-popover-btns button { padding:3px 7px; border:1px solid var(--border-color); border-radius:4px; background:var(--bg-color); color:var(--text-color); cursor:pointer; font-weight:600; font-size:11px; line-height:1.2; min-width:0; }
        .amount-currency-popover-btns button:hover { background:rgba(var(--primary-color-rgb),.2); border-color:rgba(var(--primary-color-rgb),.5); }
        .notification { background:var(--modal-bg); color:var(--text-color); padding:15px; margin:10px 0; border-radius:5px; box-shadow:0 2px 10px rgba(0,0,0,.2); display:flex; justify-content:space-between; align-items:center; min-width:300px; }
        .notification.error { border-left:4px solid #ff4757; }
        .notification.success { border-left:4px solid #2ed573; }
        .notification.warning { border-left:4px solid #ffa502; }
        .notification-close { background:none; border:none; font-size:18px; cursor:pointer; color:var(--text-color); }
        .backup-item { display:flex; align-items:center; padding:10px; border-bottom:1px solid var(--border-color); }
        .action-btn { background:none; border:none; cursor:pointer; color:var(--primary-color); padding:5px; border-radius:4px; transition:background 0.2s; }
        .action-btn:hover { background:rgba(var(--primary-color-rgb),.1); }
        .download-btn,.view-btn,.restore-btn { background:none; border:none; cursor:pointer; color:var(--primary-color); padding:5px; border-radius:4px; margin:2px; }
        .delete-btn { background:none; border:none; cursor:pointer; color:var(--danger-color); padding:5px; border-radius:4px; margin:2px; }
        .number-input { display:flex; align-items:center; gap:5px; }
        .number-input button { background:var(--primary-color); color:white; border:none; padding:5px 10px; cursor:pointer; border-radius:4px; transition:opacity 0.2s; }
        .number-input button:hover { opacity:0.9; }
        .date-spinner { width:60px; text-align:center; padding:5px; background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); border-radius:4px; }
        .scroll-hint { color:#888; font-size:12px; text-align:center; }
        .currency-btn { background:rgba(200,200,200,0.3); color:var(--text-color); border:none; padding:5px 10px; cursor:pointer; border-radius:4px; transition:all 0.2s; }
        .currency-btn.active { background:var(--primary-color); color:white; }
        #pagination { display:flex; gap:5px; justify-content:center; flex-wrap:wrap; margin:10px 0; }
        #pagination button { background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); padding:5px 10px; cursor:pointer; border-radius:4px; transition:all 0.2s; }
        #pagination button.active { background:var(--primary-color); color:white; border-color:var(--primary-color); }
        #pagination button:hover:not(:disabled) { background:rgba(var(--primary-color-rgb),0.2); }
        #pagination button:disabled { opacity:.5; cursor:default; }
        .store-visibility-table { width:100%; border-collapse:collapse; margin:10px 0; }
        .store-visibility-table th, .store-visibility-table td { padding:8px; border:1px solid var(--border-color); text-align:left; }
        .store-visibility-table th { background:var(--primary-color); color:white; }
        .visibility-toggle { background:none; border:none; cursor:pointer; color:var(--primary-color); padding:5px; border-radius:4px; transition:background 0.2s; }
        .visibility-toggle:hover { background:rgba(var(--primary-color-rgb),0.2); }
        .confirm-modal .modal-content { max-width:400px; }
        .button-group { display:flex; gap:10px; margin-top:15px; }
        .confirm-btn { background:var(--danger-color); color:white; border:none; padding:10px 20px; cursor:pointer; border-radius:4px; transition:opacity 0.2s; }
        .confirm-btn:hover { opacity:0.9; }
        .cancel-btn { background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); padding:10px 20px; cursor:pointer; border-radius:4px; transition:background 0.2s; }
        .cancel-btn:hover { background:rgba(var(--primary-color-rgb),0.1); }
        .action-primary-btn { background:var(--primary-color); color:white; border:none; padding:10px 20px; cursor:pointer; border-radius:4px; transition:opacity 0.2s; }
        .action-primary-btn:hover { opacity:0.9; }
        .restore-confirm-icon { display:flex; justify-content:center; margin-bottom:6px; color:var(--primary-color); }
        .restore-confirm-text { text-align:center; margin:0 0 4px; line-height:1.45; color:var(--text-color); }
        .chart-header { text-align:center; position:relative; }
        .chart-year-framed { display:inline-block; padding:5px 15px; border:2px solid var(--primary-color); border-radius:20px; font-weight:bold; color:var(--primary-color); margin-bottom:10px; }
        .chart-nav-bottom { display:flex; justify-content:center; gap:10px; margin-top:10px; align-items:center; }
        #ocr-analytics-modal { padding:0!important; align-items:stretch; justify-content:stretch; inset:0; }
        #ocr-analytics-modal .modal-content { width:90vw!important; height:80vh!important; max-width:1200px!important; max-height:none!important; border-radius:12px!important; margin:auto!important; overflow:hidden!important; display:flex; flex-direction:column; padding:12px 14px; box-sizing:border-box; }
        #ocr-analytics-modal h3 { margin:0 48px 8px!important; text-align:center; }
        #ocr-analytics-modal .chart-nav-bottom { margin:0 0 8px!important; min-height:42px; align-items:center; }
        #ocr-analytics-modal .chart-year-framed { margin-bottom:0!important; display:flex; align-items:center; justify-content:center; height:40px; }
        #ocr-analytics-modal .form-row { margin-bottom:8px; }
        #ocr-analytics-modal .rate-chart-container { flex:1 1 auto; min-height:0; margin:0; padding:8px 10px; overflow:hidden; display:flex; }
        #ocr-analytics-modal #ocr-analytics-chart { width:100%!important; height:100%!important; max-height:none!important; }
        /* Exchange Rates Modal */
        .rates-spinner { width:36px; height:36px; border:4px solid var(--border-color); border-top-color:var(--primary-color); border-radius:50%; animation:rates-spin .8s linear infinite; margin:0 auto; }
        @keyframes rates-spin { to { transform:rotate(360deg); } }
        .rates-table { width:100%; border-collapse:collapse; margin-top:15px; }
        .rates-table th, .rates-table td { padding:12px; text-align:left; border-bottom:1px solid var(--border-color); }
        .rates-table th { background:var(--primary-color); color:white; font-weight:500; }
        .rates-table tr:hover { background:rgba(var(--primary-color-rgb),0.05); }
        .rate-positive { color:#2ed573; }
        .rate-negative { color:#ff4757; }
        .currency-select { padding:8px 12px; background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); border-radius:6px; margin:10px 0; }
        .rate-chart-container { margin-top:20px; padding:15px; background:var(--group-block-bg); border-radius:8px; }

        /* Share modal styles */
        .share-modal-content { width:90%; max-width:600px; }
        .share-chart-container { 
            text-align:center; 
            margin:20px 0; 
            background:white; 
            padding:20px; 
            border-radius:8px; 
            border:2px solid var(--border-color);
            overflow:hidden;
        }
        .share-chart-container canvas {
            max-width:100%;
            height:auto !important;
        }
        .share-actions {
            display:flex; 
            justify-content:center; 
            gap:20px; 
            margin-top:20px;
            flex-wrap:wrap;
        }

        @media (max-width:768px) {
            :root { --top-bar-height: 64px; }
            .ocr-shell .ocrw-shell { padding-top:74px!important; }
            .header { justify-content:flex-end; align-content:center; }
            .header h2 { display:none; }
            .header-buttons { gap:5px; justify-content:center; width:100%; }
            .header-btn { padding:4px; }
            .chart-container { max-width:100%; }
            .modal-content { max-width:96%; padding:12px; }
            .ocr-shell-close { top:12px!important; right:12px!important; }
            /* Fix currency buttons and toggle button spacing on mobile */
            div[style*="justify-content:space-between"] {
                flex-direction: column !important;
                gap: 12px !important;
                align-items: center !important;
            }
            div[style*="justify-content:center"][style*="gap:10px"] {
                flex-wrap: wrap !important;
                margin-bottom: 8px !important;
            }
            .chart-toggle-btn {
                margin-top: 0 !important;
                align-self: center !important;
            }

            /* Make date column sticky on mobile when scrolling horizontally */
            .data-table th:first-child,
            .data-table td:first-child {
                position: sticky !important;
                left: 0 !important;
                z-index: 5 !important;
                background-color: var(--bg-color) !important;
                box-shadow: 2px 0 4px rgba(0,0,0,0.1) !important;
            }
            .data-table th:first-child {
                z-index: 15 !important;
                background-color: var(--primary-color) !important;
            }
            .data-table td:first-child {
                background-color: var(--bg-color) !important;
            }
            .data-table tbody tr.current-date-row td:first-child {
                background-color: rgba(var(--primary-color-rgb),0.15) !important;
            }
            .data-table tbody tr:hover td:first-child {
                background-color: rgba(var(--primary-color-rgb),0.1) !important;
            }
            .data-table td.highlight-date {
                background-color: var(--primary-color) !important;
                color: white !important;
                z-index: 6 !important;
            }

            /* Make store header row sticky at top */
            .data-table thead {
                position: sticky !important;
                top: 0 !important;
                z-index: 10 !important;
            }

            /* Share modal - fix chart overflow on mobile */
            #share-modal .share-modal-content {
                width: 96% !important;
                max-width: 96% !important;
                padding: 12px !important;
            }
            #share-modal .share-chart-container {
                margin: 10px 0 !important;
                padding: 10px !important;
                overflow: hidden !important;
            }
            #share-modal .share-chart-container canvas {
                max-width: 100% !important;
                height: auto !important;
            }
            #share-modal .share-actions {
                flex-wrap: wrap !important;
                gap: 10px !important;
                margin-top: 15px !important;
            }
            #share-modal .action-btn {
                padding: 8px 15px !important;
                font-size: 14px !important;
                flex: 1 1 auto !important;
                min-width: 120px !important;
            }
            #ocr-analytics-modal .modal-content { padding:10px!important; width:100vw!important; height:100vh!important; max-width:none!important; border-radius:0!important; margin:0!important; }
            #ocr-analytics-modal h3 { font-size:18px; margin:0 42px 6px!important; }
            #ocr-analytics-modal .chart-nav-bottom { gap:8px; margin:0 0 6px!important; }
            #ocr-analytics-modal .chart-year-framed { min-width:92px; height:36px; padding:4px 10px; }
            #ocr-analytics-modal .currency-select { margin:6px 0; }
            #ocr-analytics-modal .rate-chart-container { padding:6px; }

            #settings-modal .modal-content {
                width: 98% !important;
                max-width: 98% !important;
                padding: 15px !important;
            }
            #settings-modal .store-visibility-table {
                font-size: 13px !important;
            }
            #settings-modal .store-visibility-table th,
            #settings-modal .store-visibility-table td {
                padding: 6px 4px !important;
            }
        }
        *{scrollbar-width:thin;scrollbar-color:var(--scrollbar-thumb) var(--scrollbar-track);}
        *::-webkit-scrollbar{width:10px;height:10px;}
        *::-webkit-scrollbar-track{background:var(--scrollbar-track);}
        *::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb);border-radius:8px;}
        *::-webkit-scrollbar-thumb:hover{background:var(--scrollbar-thumb-hover);}
    </style>
</head>
