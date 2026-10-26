<?php
$nameConfig = json_decode(file_get_contents(__DIR__ . '/../name.json'), true) ?: [];
$menuButton = $nameConfig['menu_button'] ?? [];
$menuSafeWidth = (int)($menuButton['left'] ?? 16) + (int)($menuButton['width'] ?? 40) + 10;
$bgFull = $theme === 'dark' ? '../steps/background_dark.jpg' : '../steps/background_light.jpg';
$tableModalTitle = ['be'=>'Табліца','cs'=>'Tabulka','en'=>'Table','pl'=>'Tabela','ru'=>'Таблица','uk'=>'Таблиця'][$language] ?? 'Table';

/**
 * WCAG-safe derivatives of the user's brand colour.
 *   --primary-fill  solid background for primary controls; deepened until
 *                   white text reaches 4.5:1 so buttons stay readable for
 *                   every brand colour.
 *   --primary-ink   text colour to place ON --primary-fill.
 *   --primary-text  brand colour usable AS text on the theme surface
 *                   (tints included), pushed toward black/white until it
 *                   reaches 4.5:1.
 */
$spendsRgb = static function (string $hex): array {
    $h = ltrim(trim($hex), '#');
    if (strlen($h) === 3) $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
    if (strlen($h) !== 6 || !ctype_xdigit($h)) $h = '007bff';
    return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
};
$spendsLum = static function (array $c): float {
    $lin = static function ($v) {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * $lin($c[0]) + 0.7152 * $lin($c[1]) + 0.0722 * $lin($c[2]);
};
$spendsRatio = static function (array $a, array $b) use ($spendsLum): float {
    $la = $spendsLum($a);
    $lb = $spendsLum($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
};
$spendsMix = static function (array $c, array $to, float $t): array {
    return [$c[0] + ($to[0] - $c[0]) * $t, $c[1] + ($to[1] - $c[1]) * $t, $c[2] + ($to[2] - $c[2]) * $t];
};
$spendsHex = static fn (array $c): string => sprintf('#%02x%02x%02x', (int)round($c[0]), (int)round($c[1]), (int)round($c[2]));

$spendsBase    = $spendsRgb($primaryColor);
$spendsBlack   = [0, 0, 0];
$spendsWhite   = [255, 255, 255];
$spendsSurface = $theme === 'dark' ? [17, 20, 22] : [244, 247, 242];

$primaryFill = $spendsHex($spendsBase);
for ($i = 1; $i <= 20; $i++) {
    $candidate = $spendsMix($spendsBase, $spendsBlack, $i * 0.05);
    $primaryFill = $spendsHex($candidate);
    if ($spendsRatio($spendsWhite, $candidate) >= 4.5) break;
}
$primaryInk  = $spendsRatio($spendsWhite, $spendsRgb($primaryFill)) >= 4.5 ? '#ffffff' : '#101410';
$primaryText = $spendsHex($spendsBase);
$spendsPush  = $theme === 'dark' ? $spendsWhite : $spendsBlack;
for ($i = 1; $i <= 20 && $spendsRatio($spendsRgb($primaryText), $spendsSurface) < 4.5; $i++) {
    $primaryText = $spendsHex($spendsMix($spendsBase, $spendsPush, $i * 0.05));
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language) ?>" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <?= spendsCsrfScript() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($translations['expense_tracker_title']) ?></title>
    <link rel="preload" as="image" href="<?= htmlspecialchars($bgFull) ?>" fetchpriority="high">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <style>
        :root {
            --bg-color: <?= $theme==='dark'?'#111416':'#f4f7f2' ?>;
            --text-color: <?= $theme==='dark'?'#f4f7f2':'#161b18' ?>;
            --primary-color: <?= htmlspecialchars($primaryColor) ?>;
            --modal-bg: <?= $theme==='dark'?'rgba(24,28,29,.92)':'rgba(255,255,250,.86)' ?>;
            --border-color: <?= $theme==='dark'?'rgba(244,247,242,.14)':'rgba(22,27,24,.12)' ?>;
            --group-block-bg: <?= $theme==='dark'?'rgba(244,247,242,.07)':'rgba(255,255,250,.66)' ?>;
            --primary-color-rgb: <?= implode(', ', sscanf($primaryColor, "#%02x%02x%02x")) ?>;
            --primary-fill: <?= $primaryFill ?>;
            --primary-ink: <?= $primaryInk ?>;
            --primary-text: <?= $primaryText ?>;
            --success-text: <?= $theme === 'dark' ? '#4ade80' : '#166534' ?>;
            --danger-text: <?= $theme === 'dark' ? '#f87171' : '#b91c1c' ?>;
            --warning-text: <?= $theme === 'dark' ? '#fbbf24' : '#8a6100' ?>;
            --danger-color: #dc3545; --danger-hover: #c82333;
            --scrollbar-track: transparent;
            --scrollbar-thumb: <?= $theme==='dark'?'rgba(255,255,255,.3)':'rgba(0,0,0,.3)' ?>;
            --scrollbar-thumb-hover: var(--primary-color);
            --muted-text: <?= $theme==='dark'?'rgba(244,247,242,.68)':'rgba(22,27,24,.62)' ?>;
            --surface-solid: <?= $theme==='dark'?'#181c1d':'#fffffa' ?>;
            --surface-rail: <?= $theme==='dark'?'rgba(12,15,15,.74)':'rgba(232,239,224,.82)' ?>;
            --shadow-soft: 0 26px 70px rgba(0,0,0,<?= $theme==='dark'?'.34':'.16' ?>);
            --radius-lg: 18px;
            --radius-md: 12px;
            --radius-sm: 7px;
            --glass-blur: 20px;
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
        
        button:focus-visible, select:focus-visible, input:focus-visible, [tabindex]:focus-visible { outline:3px solid color-mix(in srgb,var(--primary-color) 64%,#fff); outline-offset:2px; }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { transition-duration:.01ms!important; animation-duration:.01ms!important; scroll-behavior:auto!important; } }
        body { margin:0; padding:0; font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; background:var(--bg-color); color:var(--text-color); overflow-x:hidden; min-height:100vh; }
        body.modal-open { overflow:hidden!important; }
        body.modal-open #menu-button, body.modal-open [id$="-menu-button"], body.modal-open .menu-button, body.modal-open .menu-toggle.floating, body.modal-open .mobile-menu-button { opacity:0!important; pointer-events:none!important; visibility:hidden!important; }
        #bg-layer { position:fixed; inset:0; z-index:-3; pointer-events:none; background:center/cover no-repeat; opacity:0; transition:opacity .25s ease; contain:strict; filter:saturate(.82) contrast(.94); }
        #bg-layer::after { content:""; position:fixed; inset:0; background:<?= $theme==='dark'?'linear-gradient(135deg,rgba(17,20,22,.94),rgba(17,20,22,.82) 45%,rgba(47,143,131,.22))':'linear-gradient(135deg,rgba(244,247,242,.94),rgba(244,247,242,.78) 45%,rgba(217,151,76,.2))' ?>; }
        #bg-layer.ready { opacity:1; }
        .app-expenses { max-width:1440px; width:100%; margin:0 auto; position:relative; box-sizing:border-box; padding:calc(var(--top-bar-height) + 16px) clamp(12px,2.4vw,28px) 22px; }
        .header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; position:fixed; top:0; left:0; right:0; z-index:900; min-height:var(--top-bar-height); margin:0; padding:10px clamp(12px,2vw,24px) 10px calc(var(--menu-safe-width) + 10px); box-sizing:border-box; background:<?= $theme==='dark'?'rgba(17,20,22,.78)':'rgba(244,247,242,.78)' ?>; border-bottom:1px solid var(--border-color); backdrop-filter:blur(var(--glass-blur)) saturate(1.1); -webkit-backdrop-filter:blur(var(--glass-blur)) saturate(1.1); }
        .header h2 { margin:0; white-space:nowrap; font-size:1.5em; line-height:1.2; font-weight:760; letter-spacing:0; min-width:0; overflow:hidden; text-overflow:ellipsis; flex:1 1 auto; }
        .header-buttons { display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end; }
        .header-btn { background:var(--group-block-bg); border:1px solid var(--border-color); cursor:pointer; font-size:20px; color:var(--text-color); padding:0; border-radius:var(--radius-sm); transition:background .2s,color .2s,transform .2s,border-color .2s; display:flex; align-items:center; justify-content:center; overflow:visible; width:40px; height:40px; backdrop-filter:blur(var(--glass-blur)); -webkit-backdrop-filter:blur(var(--glass-blur)); }
        .header-btn:hover { background:var(--primary-color); color:#fff; border-color:var(--primary-color); transform:translateY(-1px); }
        .add-menu-wrap { position:relative; display:inline-block; }
        .import-dropdown { display:none; position:absolute; top:100%; left:0; margin-top:4px; min-width:min(96vw,320px); background:var(--surface-solid); border:1px solid var(--border-color); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.22); z-index:3000; padding:6px 0; }
        .import-dropdown button { display:block; width:100%; text-align:left; padding:10px 14px; border:none; background:transparent; color:var(--text-color); cursor:pointer; font-size:14px; }
        .import-dropdown button:hover { background:rgba(var(--primary-color-rgb),.18); }

        /* ── Manual expense modal: same chrome as the table modal, a bit smaller ── */
        @media (min-width:769px){
            #manual-add-modal .modal-content { width:min(1120px,92vw); max-width:92vw; max-height:min(84vh,820px); display:flex; flex-direction:column; overflow:hidden; }
        }
        #manual-add-modal .modal-body { display:flex; flex-direction:column; gap:14px; }
        .manual-steps { display:flex; align-items:center; justify-content:center; gap:6px; flex-wrap:nowrap; width:100%; max-width:1100px; margin:0 auto; }
        .manual-step { display:inline-flex; align-items:center; gap:9px; height:42px; padding:0 14px 0 7px; border-radius:999px; border:1px solid var(--border-color); background:var(--group-block-bg); color:var(--muted-text); font-family:inherit; font-size:13px; font-weight:700; white-space:nowrap; cursor:pointer; transition:background .15s,border-color .15s,color .15s; }
        .manual-step:not(:disabled):hover { background:rgba(var(--primary-color-rgb),.14); border-color:rgba(var(--primary-color-rgb),.5); color:var(--text-color); }
        .manual-step:disabled { opacity:.55; cursor:default; }
        .manual-step-node { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; flex:0 0 28px; border-radius:50%; border:1px solid var(--border-color); background:var(--surface-solid); color:var(--muted-text); font-size:12px; font-weight:800; transition:background .15s,border-color .15s,color .15s; }
        .manual-step-label { overflow:hidden; text-overflow:ellipsis; }
        .manual-step.active { border-color:var(--primary-color); background:rgba(var(--primary-color-rgb),.14); color:var(--text-color); }
        .manual-step.active .manual-step-node { background:var(--primary-color); border-color:var(--primary-color); color:#fff; }
        .manual-step.done { color:var(--primary-color); border-color:color-mix(in srgb,var(--primary-color) 42%,var(--border-color)); }
        .manual-step.done .manual-step-node { background:rgba(var(--primary-color-rgb),.18); border-color:rgba(var(--primary-color-rgb),.55); color:var(--primary-color); }
        .manual-step-arrow { display:flex; align-items:center; justify-content:center; flex:0 0 18px; color:var(--muted-text); opacity:.5; }
        .manual-step-arrow svg { display:block; width:14px; height:14px; }
        .manual-panel { display:none; flex:1 1 auto; min-height:0; flex-direction:column; gap:12px; width:100%; max-width:1100px; margin:0 auto; }
        .manual-panel.active { display:flex; }
        .manual-store-search { position:relative; display:flex; }
        .manual-search-icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); width:18px; height:18px; color:var(--muted-text); pointer-events:none; }
        #manual-add-modal .manual-store-search input { flex:1 1 auto; width:100%; box-sizing:border-box; margin:0; padding:12px 14px 12px 42px; font-family:inherit; font-size:15px; background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); border-radius:10px; }
        #manual-add-modal .manual-store-search input:focus { outline:none; border-color:var(--primary-color); background:var(--group-block-bg); box-shadow:0 0 0 3px rgba(var(--primary-color-rgb),.22); }
        .manual-store-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(132px,1fr)); gap:10px; overflow-y:auto; overflow-x:hidden; padding-bottom:4px; flex:1 1 auto; min-height:0; max-height:min(52vh,460px); align-content:start; }
        .manual-store-btn { display:flex; align-items:center; gap:10px; padding:10px 12px; min-height:58px; border-radius:12px; border:1px solid var(--border-color); background:var(--group-block-bg); color:var(--text-color); font-family:inherit; font-size:14px; font-weight:600; text-align:left; cursor:pointer; transition:background .15s,border-color .15s; }
        .manual-store-btn:hover { background:rgba(var(--primary-color-rgb),.14); border-color:rgba(var(--primary-color-rgb),.5); }
        .manual-store-btn.selected { background:rgba(var(--primary-color-rgb),.16); border-color:var(--primary-color); color:var(--text-color); box-shadow:inset 0 0 0 1px rgba(var(--primary-color-rgb),.45); }
        .manual-store-btn.selected .manual-store-initials { background:rgba(var(--primary-color-rgb),.24); }
        .manual-store-thumb { width:34px; height:34px; border-radius:50%; object-fit:cover; flex:0 0 34px; background:var(--surface-solid); }
        .manual-store-initials { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:50%; flex:0 0 34px; background:rgba(var(--primary-color-rgb),.18); color:var(--primary-color); font-size:13px; font-weight:800; }
        .manual-store-name { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .manual-empty { grid-column:1/-1; padding:18px; text-align:center; color:var(--muted-text); font-size:14px; }
        .manual-date-quick { display:grid; grid-template-columns:repeat(auto-fit,minmax(104px,1fr)); gap:8px; }
        .manual-date-chip { padding:10px 6px; border-radius:12px; border:1px solid var(--border-color); background:var(--group-block-bg); color:var(--text-color); font-family:inherit; font-size:13px; font-weight:700; text-align:center; cursor:pointer; transition:background .15s,border-color .15s; }
        .manual-date-chip:hover { background:rgba(var(--primary-color-rgb),.14); border-color:rgba(var(--primary-color-rgb),.5); }
        .manual-date-chip.selected { background:rgba(var(--primary-color-rgb),.16); border-color:var(--primary-color); color:var(--text-color); box-shadow:inset 0 0 0 1px rgba(var(--primary-color-rgb),.45); }
        .manual-date-chip small { display:block; margin-top:3px; font-size:11px; font-weight:500; opacity:.82; }
        .manual-date-chip.selected small { color:var(--primary-color); opacity:1; }

        /* Always-visible custom calendar (step 2) */
        .manual-calendar { width:100%; max-width:480px; margin:0 auto; padding:10px 12px 12px; border:1px solid var(--border-color); border-radius:12px; background:var(--group-block-bg); box-sizing:border-box; }
        .manual-cal-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px; }
        .manual-cal-jump { display:flex; align-items:center; justify-content:center; gap:6px; flex:1 1 auto; min-width:0; }
        #manual-add-modal .manual-cal-select { flex:0 1 auto; width:auto; min-width:0; max-width:100%; margin:0; padding:8px 26px 8px 10px; appearance:none; -webkit-appearance:none; background-color:var(--surface-solid); background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>"); background-repeat:no-repeat; background-position:right 8px center; background-size:10px 10px; border:1px solid var(--border-color); border-radius:8px; color:var(--text-color); font-family:inherit; font-size:13px; font-weight:700; cursor:pointer; text-transform:capitalize; }
        #manual-add-modal .manual-cal-select:focus { outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(var(--primary-color-rgb),.22); }
        #manual-add-modal .manual-cal-select-year { flex:0 0 86px; }
        .manual-cal-nav { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; padding:0; border-radius:10px; border:1px solid var(--border-color); background:var(--surface-solid); color:var(--text-color); cursor:pointer; }
        .manual-cal-nav:hover { background:var(--primary-color); color:#fff; border-color:var(--primary-color); }
        .manual-cal-nav svg { width:16px; height:16px; }
        .manual-cal-weekdays { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:4px; margin-bottom:4px; }
        .manual-cal-weekdays span { padding:4px 0; text-align:center; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--muted-text); }
        .manual-cal-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:4px; }
        .manual-cal-day { display:flex; align-items:center; justify-content:center; height:38px; padding:0; border-radius:9px; border:1px solid transparent; background:transparent; color:var(--text-color); font-family:inherit; font-size:13px; font-weight:600; cursor:pointer; transition:background .15s,border-color .15s,color .15s; }
        .manual-cal-day:hover { background:rgba(var(--primary-color-rgb),.14); border-color:rgba(var(--primary-color-rgb),.4); }
        .manual-cal-day.today { border-color:rgba(var(--primary-color-rgb),.55); }
        .manual-cal-day.selected { background:rgba(var(--primary-color-rgb),.18); border-color:var(--primary-color); color:var(--primary-color); font-weight:800; }
        .manual-cal-blank { height:38px; }
        .manual-store-avatar { display:inline-flex; align-items:center; flex:0 0 auto; }
        .manual-summary { display:flex; align-items:center; gap:12px 20px; flex-wrap:wrap; padding:12px 14px; border-radius:12px; border:1px solid var(--border-color); background:var(--group-block-bg); font-size:14px; }
        .manual-summary-avatar .manual-store-thumb, .manual-summary-avatar .manual-store-initials { width:42px; height:42px; flex:0 0 42px; font-size:15px; }
        .manual-summary-item { display:flex; flex-direction:column; gap:2px; min-width:0; }
        .manual-summary-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--muted-text); }
        .manual-summary-item strong { font-size:15px; font-weight:800; overflow:hidden; text-overflow:ellipsis; }
        .manual-amount-wrap { display:flex; flex-direction:column; gap:6px; }
        .manual-amount-wrap > label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--muted-text); }
        #manual-add-modal .manual-amount-field { display:flex; align-items:center; gap:12px; padding:6px 8px 6px 16px; border-radius:12px; border:2px solid var(--border-color); background:var(--surface-solid); }
        #manual-add-modal .manual-amount-field:focus-within { border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(var(--primary-color-rgb),.22); }
        #manual-add-modal .manual-amount-field input { flex:1 1 auto; min-width:0; width:100%; margin:0; padding:8px 0; border:0; border-radius:0; background:transparent; color:var(--text-color); font-family:inherit; font-size:26px; font-weight:800; line-height:1.2; box-shadow:none; outline:none; }
        #manual-add-modal .manual-amount-field input:focus { outline:none; border:0; background:transparent; }
        /* Custom currency dropdown: the native <select> popup inherits system colors and is unreadable on dark */
        .manual-currency { position:relative; flex:0 0 auto; }
        #manual-add-modal .manual-currency-btn { display:inline-flex; align-items:center; justify-content:space-between; gap:8px; min-width:88px; margin:0; padding:9px 10px 9px 12px; border:1px solid var(--border-color); border-radius:10px; background:var(--group-block-bg); color:var(--text-color); font-family:inherit; font-size:13px; font-weight:800; letter-spacing:.02em; cursor:pointer; }
        #manual-add-modal .manual-currency-btn:hover { background:rgba(var(--primary-color-rgb),.14); border-color:rgba(var(--primary-color-rgb),.5); }
        #manual-add-modal .manual-currency-btn[aria-expanded="true"] { border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(var(--primary-color-rgb),.22); }
        .manual-currency-chevron { width:12px; height:12px; flex:0 0 12px; opacity:.7; }
        .manual-currency-menu { position:absolute; right:0; bottom:calc(100% + 6px); z-index:30; display:none; flex-direction:column; gap:2px; min-width:132px; padding:6px; border:1px solid var(--border-color); border-radius:12px; background:var(--surface-solid); box-shadow:0 16px 36px rgba(0,0,0,.34); }
        .manual-currency-menu.open { display:flex; }
        .manual-currency-opt { display:flex; align-items:center; justify-content:space-between; gap:10px; width:100%; padding:9px 10px; border:0; border-radius:8px; background:transparent; color:var(--text-color); font-family:inherit; font-size:13px; font-weight:700; text-align:left; cursor:pointer; }
        .manual-currency-opt:hover { background:rgba(var(--primary-color-rgb),.16); }
        .manual-currency-opt[aria-selected="true"] { background:rgba(var(--primary-color-rgb),.18); color:var(--primary-color); }
        .manual-currency-check { width:14px; height:14px; flex:0 0 14px; }
        #manual-add-modal .modal-content { color-scheme: <?= $theme === 'dark' ? 'dark' : 'light' ?>; }
        .manual-amount-hint { margin:0; min-height:16px; font-size:12px; font-weight:600; color:var(--muted-text); }

        /* Recurring expense (subscription) block on the amount step */
        .manual-sub { padding:12px 14px; border-radius:12px; border:1px solid var(--border-color); background:var(--group-block-bg); }
        .manual-sub-switch { display:flex; align-items:center; gap:12px; font-size:14px; font-weight:700; color:var(--text-color); cursor:pointer; }
        .manual-sub-switch-text { min-width:0; }
        .manual-sub-body { display:flex; flex-direction:column; gap:12px; margin-top:12px; }
        .manual-sub-body[hidden] { display:none; }
        .manual-sub-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; align-items:end; }
        .manual-sub-field { display:flex; flex-direction:column; gap:6px; min-width:0; }
        .manual-sub-field > label, .manual-sub-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--muted-text); }
        #manual-add-modal .manual-sub-field input[type="number"], #manual-add-modal .manual-sub-field input[type="date"] { width:100%; box-sizing:border-box; margin:0; padding:10px 12px; background:var(--surface-solid); color:var(--text-color); border:1px solid var(--border-color); border-radius:10px; font-family:inherit; font-size:14px; }
        #manual-add-modal .manual-sub-field input[type="number"]:focus, #manual-add-modal .manual-sub-field input[type="date"]:focus { outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(var(--primary-color-rgb),.22); }
        .manual-sub-period { position:relative; }
        #manual-add-modal .manual-sub-period-btn { display:flex; align-items:center; justify-content:space-between; gap:8px; width:100%; margin:0; padding:10px 12px; background:var(--surface-solid); color:var(--text-color); border:1px solid var(--border-color); border-radius:10px; font-family:inherit; font-size:14px; font-weight:700; text-align:left; cursor:pointer; }
        #manual-add-modal .manual-sub-period-btn:hover { border-color:rgba(var(--primary-color-rgb),.5); }
        #manual-add-modal .manual-sub-period-btn[aria-expanded="true"] { border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(var(--primary-color-rgb),.22); }
        .manual-sub-period-menu { position:absolute; left:0; right:0; bottom:calc(100% + 6px); z-index:40; display:none; flex-direction:column; gap:2px; padding:6px; border:1px solid var(--border-color); border-radius:12px; background:var(--surface-solid); box-shadow:0 16px 36px rgba(0,0,0,.34); }
        .manual-sub-period-menu.open { display:flex; }
        .manual-sub-period-opt { display:flex; align-items:center; justify-content:space-between; gap:10px; width:100%; padding:9px 10px; border:0; border-radius:8px; background:transparent; color:var(--text-color); font-family:inherit; font-size:13px; font-weight:700; text-align:left; cursor:pointer; }
        .manual-sub-period-opt:hover { background:rgba(var(--primary-color-rgb),.16); }
        .manual-sub-period-opt[aria-selected="true"] { background:rgba(var(--primary-color-rgb),.18); color:var(--primary-color); }
        .manual-sub-count { display:flex; align-items:center; gap:8px; }
        .manual-sub-count > span { flex:0 0 auto; font-size:13px; font-weight:700; color:var(--muted-text); }
        .manual-sub-end { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .manual-sub-endless { display:inline-flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--text-color); cursor:pointer; white-space:nowrap; }
        #manual-add-modal .manual-sub-endless input { width:auto; margin:0; padding:0; accent-color:var(--primary-color); }
        #manual-add-modal .manual-sub-end input[type="date"] { flex:1 1 160px; min-width:0; }
        .manual-sub-hint { margin:0; min-height:15px; font-size:12px; line-height:1.4; color:var(--muted-text); }
        .manual-sub-active { display:flex; flex-direction:column; gap:8px; }
        .manual-sub-active-title { font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--muted-text); }
        .manual-sub-list { display:flex; flex-direction:column; gap:8px; }
        .manual-sub-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:10px; border:1px solid var(--border-color); background:var(--surface-solid); font-size:13px; }
        .manual-sub-item-main { flex:1 1 auto; min-width:0; display:flex; flex-direction:column; gap:2px; }
        .manual-sub-item strong { font-size:13px; font-weight:800; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .manual-sub-item span { font-size:12px; color:var(--muted-text); }
        .manual-sub-item-del { flex:0 0 auto; padding:6px 10px; border-radius:8px; border:1px solid var(--border-color); background:transparent; color:var(--danger-color); font-family:inherit; font-size:12px; font-weight:700; cursor:pointer; }
        .manual-sub-item-del:hover { background:rgba(220,53,69,.14); }
        .manual-sub-empty { font-size:12px; color:var(--muted-text); }

        .table-modal-actions { display:flex; justify-content:flex-end; gap:8px; margin:0 0 10px; flex-shrink:0; }
        .table-modal-actions .add-date-btn { padding:8px 14px; border-radius:10px; font-weight:700; }

        .manual-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:4px; }
        .manual-actions button { flex:1 1 140px; padding:12px 18px; border-radius:10px; font-family:inherit; font-size:14px; font-weight:700; }
        @media (max-width:768px){
            .manual-store-grid { grid-template-columns:repeat(auto-fill,minmax(148px,1fr)); }
            .manual-actions button { flex:1 1 100%; }
            #manual-add-modal .manual-amount-field input { font-size:24px; }
            .manual-steps { gap:4px; }
            .manual-step { flex:1 1 0; min-width:0; justify-content:center; padding:0 6px 0 4px; gap:6px; height:40px; font-size:12px; }
            .manual-step-node { width:24px; height:24px; flex:0 0 24px; font-size:11px; }
            .manual-step-arrow { flex:0 0 12px; }
            .manual-step-arrow svg { width:12px; height:12px; }
            .manual-calendar { padding:8px 8px 10px; }
            .manual-cal-day, .manual-cal-blank { height:34px; }
        }
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
        .data-table-container { overflow:hidden; margin:10px 0; max-width:100%; max-height:calc(100vh - var(--top-bar-height) - 20px); }
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
        .data-table td { background:transparent; }
        .data-table td input { width:100%; border:none; text-align:center; background:transparent; color:var(--text-color); box-sizing:border-box; }
        .data-table td input:focus { outline:1px solid var(--primary-color); background:var(--group-block-bg); }
        .data-table td:first-child { white-space:nowrap!important; min-width:100px!important; max-width:120px!important; }
        .data-table .store-header { display:flex; align-items:center; justify-content:center; gap:5px; }
        .data-table tbody tr:hover { background-color:transparent!important; color:var(--primary-color); }
        .data-table tbody tr.current-date-row { background-color:transparent!important; color:var(--primary-color); border-left:3px solid var(--primary-color); }
        .data-table tbody tr.highlight-row { background-color:transparent; color:var(--primary-color); }
        .data-table th.highlight-column { background-color:rgba(var(--primary-color-rgb),0.1); }
        .data-table td.highlight-column { background-color:transparent; color:var(--primary-color); }
        .data-table td.highlight-date { background-color:transparent!important; color:var(--primary-color)!important; font-weight:bold; }
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
        .store-item { display:flex; align-items:center; gap:10px; margin:5px 0; padding:10px; background:var(--group-block-bg); border-radius:var(--radius-sm); }
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
        #ocr-analytics-modal .chart-nav-bottom { margin:0 0 8px!important; min-height:42px; align-items:center; }
        #ocr-analytics-modal .chart-year-framed { margin-bottom:0!important; display:flex; align-items:center; justify-content:center; height:40px; }
        #ocr-analytics-modal .form-row { margin-bottom:8px; }
        #ocr-analytics-modal .rate-chart-container { flex:1 1 auto; min-height:0; margin:0; padding:8px 10px; overflow:hidden; display:flex; }
        #ocr-analytics-modal #ocr-analytics-chart { width:100%!important; height:100%!important; max-height:none!important; }
        /* Exchange Rates Modal */
        .rates-spinner { width:36px; height:36px; border:4px solid var(--border-color); border-top-color:var(--primary-color); border-radius:50%; animation:rates-spin .8s linear infinite; margin:0 auto; }
        @keyframes rates-spin { to { transform:rotate(360deg); } }
        /* Exchange rates modal (redesigned) */
        .xr-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; background:rgba(var(--primary-color-rgb),0.12); color:var(--primary-color); font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
        .xr-dot { width:6px; height:6px; border-radius:50%; background:var(--primary-color); animation:xr-blink 1.6s infinite; }
        @keyframes xr-blink { 50% { opacity:.25; } }
        .xr-base-tabs { display:flex; gap:6px; padding:14px 0 0; overflow-x:auto; scrollbar-width:none; }
        #exchange-rates-modal .modal-body { padding:0 clamp(16px,2.2vw,24px) clamp(12px,1.6vw,16px); }
        .xr-base-tabs::-webkit-scrollbar { display:none; }
        .xr-base-tab { flex-shrink:0; padding:8px 16px; border-radius:12px; background:var(--group-block-bg); border:1px solid transparent; color:var(--muted-text); font-size:13px; font-weight:600; cursor:pointer; transition:all .2s; font-family:inherit; }
        .xr-base-tab:hover { background:rgba(var(--primary-color-rgb),0.12); color:var(--text-color); }
        .xr-base-tab.active { background:linear-gradient(135deg,var(--primary-color),color-mix(in srgb,var(--primary-color) 70%,#fff)); color:#fff; box-shadow:0 8px 20px -6px rgba(var(--primary-color-rgb),0.6); }
        .xr-rows { padding:12px 0 16px; }
        .xr-row { display:grid; grid-template-columns:36px 1fr auto auto auto; align-items:center; gap:14px; padding:12px 10px; border-radius:16px; cursor:pointer; transition:background .18s; }
        .xr-row:hover { background:rgba(var(--primary-color-rgb),0.06); }
        .xr-row.active { background:rgba(var(--primary-color-rgb),0.1); }
        .xr-flag { width:36px; height:36px; border-radius:50%; background:var(--group-block-bg); display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:800; color:var(--primary-color); }
        .xr-info strong { display:block; color:var(--text-color); font-size:14px; font-weight:600; }
        .xr-info span { font-size:11px; color:var(--muted-text); font-weight:500; }
        .xr-rate { text-align:right; font-size:16px; font-weight:700; color:var(--text-color); font-variant-numeric:tabular-nums; letter-spacing:-0.02em; white-space:nowrap; }
        .xr-rate small { font-size:10px; color:var(--muted-text); margin-left:3px; font-weight:500; }
        .xr-change { min-width:64px; padding:5px 10px; border-radius:10px; font-size:11px; font-weight:700; text-align:center; font-variant-numeric:tabular-nums; }
        .xr-change.up { background:rgba(46,203,113,0.12); color:#2ecb71; }
        .xr-change.down { background:rgba(255,86,86,0.12); color:#ff5656; }
        .xr-change.flat { background:var(--group-block-bg); color:var(--muted-text); }
        .xr-spark { width:60px; height:24px; }
        .xr-spark path { fill:none; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
        .xr-foot { padding:12px 0; border-top:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; font-size:11px; color:var(--muted-text); flex-wrap:wrap; gap:6px; }
        .xr-foot i { color:var(--primary-color); margin-right:5px; }
        @media (max-width:480px){ .xr-row { grid-template-columns:32px 1fr auto; gap:10px; } .xr-spark { display:none; } .xr-change { min-width:56px; padding:4px 8px; } }
        .currency-select { padding:8px 12px; background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); border-radius:6px; margin:10px 0; width:100%; max-width:100%; min-width:0; box-sizing:border-box; }
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
        .chart-container { margin:0; justify-content:center; max-width:none; min-height:340px; padding:clamp(12px,2vw,18px); border-radius:var(--radius-lg); background:var(--surface-solid); border:1px solid rgba(127,127,127,.1); box-shadow:0 14px 40px rgba(0,0,0,.1); }
        .chart-nav-btn, .chart-toggle-btn { background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); padding:9px 14px; border-radius:var(--radius-sm); font-size:15px; font-weight:700; }
        .chart-nav-btn:hover, .chart-toggle-btn:hover { background:var(--primary-color); color:#fff; border-color:var(--primary-color); }
        .chart-controls-row {
            display:grid;
            grid-template-columns: 1fr auto 1fr;
            align-items:center;
            gap:12px;
            margin:12px 0;
            flex-wrap:wrap;
        }
        .currency-selector { display:flex; justify-content:flex-start; gap:6px; flex-wrap:wrap; }
        .currency-btn { background:var(--group-block-bg); color:var(--muted-text); border:1px solid var(--border-color); padding:9px 12px; border-radius:var(--radius-sm); font-weight:800; flex: 1 1 auto; min-width: 0; padding-inline: 4px; }
        .currency-btn:hover, .currency-btn.active { background:var(--primary-color); color:white; border-color:var(--primary-color); }
        .divider { height:1px; background:var(--border-color); margin:18px 0; }
        .controls-container { display:flex; justify-content:center; align-items:center; margin:12px 0; padding:0; border:0; background:transparent; box-shadow:none; }
        .chart-toggle-btn { justify-self: end; }
        .currency-selector { justify-self: start; }
        .table-toolbar .search-container { position:relative; display:flex; align-items:center; width:min(44vw,260px); min-width:220px; height:40px; }
        .table-toolbar .search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; display:flex; align-items:center; justify-content:center; color:var(--muted-text); pointer-events:none; z-index:1; }
        .table-toolbar .search-box { width:100%; height:40px; margin:0; padding:0 12px 0 38px; background:var(--surface-solid); border-radius:var(--radius-sm); line-height:40px; }
        .add-date-btn { padding:10px 16px; border-radius:var(--radius-sm); font-weight:800; }
        .data-table-container { overflow:hidden; margin:12px 0; max-width:100%; max-height:calc(100vh - var(--top-bar-height) - 40px); border:1px solid var(--border-color); border-radius:var(--radius-lg); background:var(--modal-bg); box-shadow:var(--shadow-soft); backdrop-filter:blur(var(--glass-blur)) saturate(1.08); -webkit-backdrop-filter:blur(var(--glass-blur)) saturate(1.08); }
        .data-table-scroll { overflow:auto; height:100%; min-height:0; }
        .data-table { border-collapse:separate; border-spacing:0; }
        .data-table th, .data-table td { border:0; border-bottom:1px solid var(--border-color); border-left:1px solid var(--border-color); padding:8px 10px; min-width:92px; }
        .data-table th { background:color-mix(in srgb,var(--primary-color) 86%,#111); font-size:12px; font-weight:800; }
        .data-table td { background:transparent; }
        .data-table td input:focus { outline:2px solid color-mix(in srgb,var(--primary-color) 64%,#fff); background:var(--surface-solid); border-radius:var(--radius-sm); }
        .settings-menu-bar { position:relative; display:table; width:min(360px,100%); margin:0 auto 16px; border:none; background:transparent; padding:0; }
        .settings-menu-btn { position:relative; width:33.333%; display:table-cell; text-align:center; color:#949494; background:transparent; border:none; text-decoration:none; font-family:inherit; font-weight:bold; padding:10px 14px; cursor:pointer; transition:0.2s ease color; box-shadow:none; }
        .settings-menu-btn:before, .settings-menu-btn:after { content:""; position:absolute; border-radius:50%; transform:scale(0); transition:0.2s ease transform; pointer-events:none; }
        .settings-menu-btn:before { top:0; left:12px; width:6px; height:6px; }
        .settings-menu-btn:after { top:5px; left:20px; width:4px; height:4px; }
        .settings-menu-btn:nth-child(1):before { background-color:yellow; }
        .settings-menu-btn:nth-child(1):after { background-color:red; }
        .settings-menu-btn:nth-child(2):before { background-color:#00e2ff; }
        .settings-menu-btn:nth-child(2):after { background-color:#89ff00; }
        .settings-menu-btn:nth-child(3):before { background-color:purple; }
        .settings-menu-btn:nth-child(3):after { background-color:palevioletred; }
        .settings-menu-bar #indicator { position:absolute; left:5%; bottom:0; width:30px; height:3px; background-color:#fff; border-radius:5px; transition:0.2s ease left, 0.2s ease background; pointer-events:none; }
        .settings-menu-btn:hover { color:#fff; background:transparent; }
        .settings-menu-btn:hover:before, .settings-menu-btn:hover:after { transform:scale(1); }
        .settings-menu-btn.active { color:#fff; background:transparent; }
        .settings-menu-btn.active:before, .settings-menu-btn.active:after { transform:scale(1); }
        .settings-menu-btn:nth-child(1):hover ~ #indicator, .settings-menu-bar:not(:has(.settings-menu-btn:hover)) .settings-menu-btn:nth-child(1).active ~ #indicator { left:5%; background:linear-gradient(130deg, yellow, red); }
        .settings-menu-btn:nth-child(2):hover ~ #indicator, .settings-menu-bar:not(:has(.settings-menu-btn:hover)) .settings-menu-btn:nth-child(2).active ~ #indicator { left:38%; background:linear-gradient(130deg, #00e2ff, #89ff00); }
        .settings-menu-btn:nth-child(3):hover ~ #indicator, .settings-menu-bar:not(:has(.settings-menu-btn:hover)) .settings-menu-btn:nth-child(3).active ~ #indicator { left:72%; background:linear-gradient(130deg, purple, palevioletred); }
        .modal-content { padding:64px clamp(16px,2.2vw,24px) clamp(16px,2.2vw,24px); border-radius:var(--radius-lg); border:1px solid var(--border-color); box-shadow:var(--shadow-soft); backdrop-filter:blur(var(--glass-blur)) saturate(1.08); -webkit-backdrop-filter:blur(var(--glass-blur)) saturate(1.08); background:linear-gradient(120deg,var(--surface-rail),var(--modal-bg)) top/100% 52px no-repeat,linear-gradient(180deg,var(--modal-bg),var(--group-block-bg)); }
        .modal-content::before { content:""; position:absolute; top:0; left:-1px; right:-1px; height:52px; border-bottom:1px solid var(--border-color); background:linear-gradient(120deg,var(--surface-rail),var(--modal-bg)); border-radius:var(--radius-lg) var(--radius-lg) 0 0; pointer-events:none; z-index:2; }
        .modal-content{ scrollbar-gutter:stable both-edges; -webkit-scrollbar-gutter:stable both-edges; }
        .modal-content{ overflow-x:hidden; overflow-y:scroll; }
        .modal-content > h2:first-of-type, .modal-content > h3:first-of-type { position:absolute; top:15px; left:clamp(16px,2.2vw,24px); right:66px; z-index:1; margin:0!important; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; font-size:18px; line-height:1.2; font-weight:800; color:var(--text-color)!important; text-align:left!important; }
        .modal-close { position:absolute!important; top:26px!important; right:12px!important; transform:translateY(-50%)!important; z-index:3; color:var(--text-color); width:36px; height:36px; border-radius:var(--radius-sm); background:var(--group-block-bg); border:1px solid var(--border-color); box-shadow:none; display:inline-flex; align-items:center; justify-content:center; line-height:1; }
        .modal-close:hover { background:var(--primary-color); color:#fff; filter:none; border-color:var(--primary-color); }
        .modal:not(#import-modal):not(#bank-csv-import-modal) .modal-content { padding-top:64px!important; }
        #bank-csv-import-modal .modal-content { padding:64px 12px 14px!important; }
        .modal { position:fixed; display:none; inset:0; width:100%; height:100%; background:rgba(10,14,18,.48); -webkit-backdrop-filter:blur(14px); backdrop-filter:blur(14px); z-index:10000; padding:16px; box-sizing:border-box; overflow-y:auto; overflow-x:hidden; align-items:center; justify-content:center; }
        .modal-content { width:min(920px,100%); max-width:calc(100vw - 32px); max-height:calc(100dvh - 32px); height:auto; margin:auto; border:1px solid var(--border-color); border-radius:18px; box-shadow:var(--shadow-soft); background:linear-gradient(120deg,var(--surface-rail),var(--modal-bg)) top/100% 52px no-repeat,color-mix(in srgb,var(--modal-bg) 92%,var(--surface-solid)); padding:64px 22px 22px; overflow:auto; box-sizing:border-box; }
        .modal-close { top:26px!important; right:12px!important; transform:translateY(-50%)!important; width:36px; height:36px; border-radius:10px; background:color-mix(in srgb,var(--surface-solid) 72%,transparent); border:1px solid var(--border-color); color:var(--muted-text); }
        .modal-close:hover { background:color-mix(in srgb,var(--primary-color) 12%,var(--surface-solid)); color:var(--primary-color); border-color:color-mix(in srgb,var(--primary-color) 36%,var(--border-color)); }
        #import-modal, #bank-csv-import-modal { background:rgba(10,14,18,.48); padding:16px!important; align-items:center; justify-content:center; }
        #import-modal .modal-content, #bank-csv-import-modal .modal-content { width:min(1120px,calc(100vw - 32px))!important; height:calc(100dvh - 32px)!important; max-width:calc(100vw - 32px)!important; max-height:calc(100dvh - 32px)!important; border-radius:18px!important; padding:0!important; overflow:hidden; }
        #bank-csv-import-modal .modal-content { padding:64px 12px 14px!important; }
        #import-modal .modal-content::before, #bank-csv-import-modal .modal-content::before { border-radius:18px 18px 0 0; }
        /* Import (OCR wizard) needs an opaque top bar; also prevent scrollbar overlap */
        #import-modal .modal-content::before{
            content:""!important;
            height:52px!important;
            background:var(--surface-solid)!important;
            opacity:1!important;
            border-bottom:1px solid var(--border-color)!important;
            pointer-events:none;
            z-index:20;
            left:-1px!important;
            right:-1px!important;
        }
        #import-modal .ocr-shell{
            z-index:1;
        }
        #import-modal .ocr-shell-close{
            position:absolute!important;
            top:26px!important; /* 52px bar height / 2 */
            right:12px!important;
            transform:translateY(-50%)!important;
            z-index:100!important;
        }
        #import-modal .ocrw-shell{
            scrollbar-gutter:stable both-edges;
            -webkit-scrollbar-gutter:stable both-edges;
            position:relative;
            z-index:1;
            padding-top:68px!important;
        }
        .table-modal .modal-content { width:min(1320px,96vw); max-width:96vw; max-height:min(90vh,900px); display:flex; flex-direction:column; overflow:hidden; }
        .table-toolbar { display:flex; justify-content:flex-end; margin:0 0 12px; flex-shrink:0; }
        .table-modal .data-table-container { flex:1 1 auto; min-height:0; max-height:none; margin:0; box-shadow:none; overflow:hidden; display:flex; flex-direction:column; }
        .table-modal .data-table-scroll { flex:1 1 0%; min-height:0; height:0; max-height:none; overflow:auto; }
        .table-modal .data-table th { position:sticky; top:0; z-index:10; background-color:color-mix(in srgb,var(--primary-color) 86%,#111); }
        /* Compact rows (PC + mobile) */
        .table-modal .data-table th, .table-modal .data-table td { padding:3px 6px; min-width:64px; }
        .table-modal .data-table th { padding:5px 6px; font-size:11.5px; line-height:1.2; }
        .table-modal .data-table td { line-height:1.2; }
        .table-modal .data-table td input { height:26px; margin:0; box-sizing:border-box; padding:2px 4px; font-size:13.5px; line-height:1.2; }
        .table-modal .data-table .store-icon { width:16px; height:16px; }
        /* Focused column header must stay opaque so values do not show through */
        .table-modal .data-table th.highlight-column { background-color:color-mix(in srgb,var(--primary-color) 68%,#fff); }
        /* Today's row (all stores) is visibly highlighted */
        .table-modal .data-table tbody tr.current-date-row td { background-color:rgba(var(--primary-color-rgb),.16)!important; color:var(--text-color)!important; }
        .table-modal .data-table tbody tr.current-date-row td:first-child { color:var(--primary-color)!important; font-weight:800; }
        .table-modal .data-table tbody tr.current-date-row td.highlight-column { background-color:rgba(var(--primary-color-rgb),.3)!important; }
        /* Year/month calendar popover next to the "Today" button */
        .month-pagination { position:relative; }
        .table-cal-pop { position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%); z-index:1500; display:none; flex-direction:column; gap:10px; padding:12px; background:var(--modal-bg); border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:0 14px 36px rgba(0,0,0,.3); min-width:264px; backdrop-filter:blur(var(--glass-blur)); -webkit-backdrop-filter:blur(var(--glass-blur)); }
        .table-cal-pop.open { display:flex; }
        .table-cal-pop .table-cal-title { font-size:12px; font-weight:800; color:var(--muted-text); text-transform:uppercase; letter-spacing:.04em; }
        .table-cal-grid { display:grid; grid-template-columns:96px minmax(0,1fr); gap:10px; }
        .table-cal-field { display:flex; flex-direction:column; gap:5px; min-width:0; }
        .table-cal-field > span { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:var(--muted-text); }
        .table-cal-pop select { flex:1 1 auto; min-width:0; margin:0; width:100%; padding:9px 30px 9px 10px; color:var(--text-color); background-color:var(--surface-solid); background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>"); background-repeat:no-repeat; background-position:right 10px center; background-size:12px 12px; border:1px solid var(--border-color); border-radius:var(--radius-sm); font-family:inherit; font-size:14px; font-weight:700; line-height:1.2; -webkit-appearance:none; appearance:none; -moz-appearance:none; cursor:pointer; transition:border-color .15s ease, box-shadow .15s ease; }
        .table-cal-pop select:hover { border-color:rgba(var(--primary-color-rgb),.55); }
        .table-cal-pop select:focus { outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(var(--primary-color-rgb),.22); }
        /* Calendar trigger styled exactly like the neighbouring month buttons */
        .month-pagination button.table-cal-trigger { display:inline-flex; align-items:center; justify-content:center; line-height:1; padding:8px 10px; }
        .month-pagination button.table-cal-trigger svg { display:block; width:15px; height:15px; }
        @media (min-width:769px){ .table-modal .modal-body { padding-top:8px; padding-bottom:8px; } }
        .notification { border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:0 18px 42px rgba(0,0,0,.2); }
        #pagination { margin:14px 0 0; }
        #pagination button { padding:8px 12px; border-radius:var(--radius-sm); font-weight:700; }
        .month-pagination { display:flex; align-items:center; justify-content:center; gap:6px; margin:14px 0 0; flex-wrap:wrap; }
        .month-pagination button { background:var(--group-block-bg); color:var(--text-color); border:1px solid var(--border-color); padding:8px 12px; border-radius:var(--radius-sm); font-weight:700; cursor:pointer; transition:all .15s ease; }
        .month-pagination button:hover:not(:disabled) { background:rgba(var(--primary-color-rgb),.15); border-color:rgba(var(--primary-color-rgb),.4); }
        .month-pagination button.active { background:var(--primary-color); color:#fff; border-color:var(--primary-color); }
        .month-pagination button:disabled { opacity:.35; cursor:default; }
        .month-pagination-label { font-size:13px; font-weight:700; color:var(--text-color); padding:0 6px; white-space:nowrap; }
        .chart-year-framed { padding:8px 16px; border:1px solid var(--border-color); border-radius:var(--radius-md); font-weight:800; color:var(--text-color); margin-bottom:12px; background:var(--modal-bg); box-shadow:0 12px 30px rgba(0,0,0,.1); backdrop-filter:blur(var(--glass-blur)); -webkit-backdrop-filter:blur(var(--glass-blur)); }
        .rate-chart-container { border:1px solid var(--border-color); border-radius:var(--radius-md); }

        /* ── Share-to-chat modal (chart-chat-modal) ───────────────────────────── */
        .ccm-step { display:flex; flex-direction:column; gap:12px; min-height:0; }
        .ccm-label { font-size:13px; font-weight:700; color:var(--text-color); }
        .ccm-input { width:100%; box-sizing:border-box; padding:11px 14px; border:1px solid var(--border-color); border-radius:10px; background:var(--group-block-bg); color:var(--text-color); font-family:inherit; font-size:14px; resize:vertical; }
        .ccm-input:focus { outline:2px solid color-mix(in srgb,var(--primary-color) 64%,#fff); }
        /* Fixed height so the dialog cannot reflow on every keystroke. */
        .ccm-results { display:flex; flex-direction:column; gap:6px; flex:0 0 auto; height:240px; max-height:240px; overflow-y:auto; overflow-x:hidden; }
        .ccm-results > .ccm-empty { flex:1 1 auto; display:flex; align-items:center; justify-content:center; text-align:center; }
        .ccm-user-row { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:12px; border:1px solid var(--border-color); background:transparent; cursor:pointer; text-align:left; font-family:inherit; flex:0 0 auto; transition:background .18s,border-color .18s; }
        .ccm-user-row:hover { background:rgba(var(--primary-color-rgb),0.08); border-color:rgba(var(--primary-color-rgb),0.4); }
        .ccm-user-row img, .ccm-user-avatar { width:32px; height:32px; border-radius:50%; object-fit:cover; background:var(--group-block-bg); display:flex; align-items:center; justify-content:center; font-weight:800; color:var(--primary-color); font-size:13px; }
        .ccm-user-info strong { display:block; font-size:14px; color:var(--text-color); }
        .ccm-user-info span { font-size:12px; color:var(--muted-text); }
        .ccm-empty { font-size:13px; color:var(--muted-text); text-align:center; padding:12px; }
        .ccm-recipient { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:14px; background:var(--group-block-bg); border:1px solid var(--border-color); }
        .ccm-change-user { margin-left:auto; padding:7px 14px; border-radius:10px; border:1px solid var(--border-color); background:transparent; color:var(--primary-color); font-family:inherit; font-size:13px; font-weight:600; cursor:pointer; }
        .ccm-change-user:hover { background:rgba(var(--primary-color-rgb),0.1); }
        .ccm-preview { max-width:100%; max-height:180px; border-radius:12px; border:1px solid var(--border-color); align-self:center; }
        @media (max-width:480px){ .share-actions { flex-direction:column; } .share-act-btn { width:100%; } .csl-actions { flex-direction:column; } .csl-actions .share-act-btn { width:100%; } }

@media (max-width:768px) {
            :root { --top-bar-height: 64px; }
            .ocr-shell .ocrw-shell { padding-top:74px!important; }
            .header { justify-content:flex-end; align-content:center; }
            .header h2 { display:none; }
            .header-buttons { gap:4px; justify-content:flex-end; width:100%; flex-shrink:0; flex-wrap:wrap; overflow:visible; padding-bottom:2px; }
            .header-btn { padding:4px; }
            .chart-container { max-width:100%; }
            .modal { padding:0!important; align-items:stretch!important; justify-content:stretch!important; }
            .modal .modal-content { width:100vw!important; height:100vh!important; max-width:none!important; max-height:none!important; border-radius:0!important; margin:0!important; padding:64px 12px 12px!important; box-sizing:border-box; }
        .modal .modal-content::before { border-radius:0!important; left:-1px!important; right:-1px!important; }
            .ocr-shell-close { top:12px!important; right:12px!important; }

            .chart-controls-row {
                display:flex;
                flex-direction: row !important;
                flex-wrap: wrap !important;
                gap: 10px !important;
                align-items: center !important;
                justify-content: flex-start !important;
                overflow-x: hidden !important;
            }
            .currency-btn { flex: 1 1 auto; min-width: 0; padding-inline: 4px; }
            .chart-toggle-btn { justify-self: auto !important; margin-left: 0 !important; }
            .currency-selector { justify-self: auto !important; }
            .currency-selector {
                flex-wrap: wrap !important;
                gap: 6px !important;
                overflow-x: hidden !important;
            }
            .controls-container { justify-content:center; flex-wrap:wrap; }
            .table-toolbar { justify-content:stretch; }
            .table-toolbar .search-container, .table-toolbar .search-box { width:100%; }
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
                background-color: transparent !important;
            }
            .data-table tbody tr.current-date-row td:first-child {
                background-color: transparent !important;
                color: var(--primary-color) !important;
            }
            .data-table tbody tr:hover td:first-child {
                background-color: transparent !important;
                color: var(--primary-color) !important;
            }
            .data-table td.highlight-date {
                background-color: transparent !important;
                color: var(--primary-color) !important;
                z-index: 6 !important;
            }

            /* Make store header row sticky at top */
            .table-modal .data-table thead {
                position: sticky !important;
                top: 0 !important;
                z-index: 10 !important;
            }

            /* Share modal - fix chart overflow on mobile */
            /* Share action buttons (styled like other modal buttons) */
        .share-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-top:16px; }
        .share-act-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 18px; border-radius:12px; border:1px solid var(--border-color); background:var(--group-block-bg); color:var(--text-color); font-family:inherit; font-size:14px; font-weight:600; cursor:pointer; transition:background .2s,color .2s,transform .2s,border-color .2s,box-shadow .2s; }
        .share-act-btn svg { flex:0 0 auto; }
        .share-act-btn:hover { background:var(--primary-color); border-color:var(--primary-color); color:#fff; transform:translateY(-1px); box-shadow:0 6px 16px -6px rgba(var(--primary-color-rgb),.6); }
        .share-act-btn:disabled { opacity:.55; cursor:default; transform:none!important; box-shadow:none!important; }
        .share-act-btn-primary { background:var(--primary-color); border-color:var(--primary-color); color:#fff; width:100%; }
        .share-act-btn-primary:hover { filter:brightness(1.08); background:var(--primary-color); color:#fff; }
        .csl-body { display:flex; flex-direction:column; gap:14px; }
        .csl-link-box { background:var(--group-block-bg); border:1px solid var(--border-color); border-radius:10px; padding:12px; word-break:break-all; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12px; }
        .csl-link-box a { color:var(--primary-color); text-decoration:none; }
        .csl-qr { display:grid; place-items:center; min-height:120px; }
        .csl-qr .csl-qr-wrap { padding:14px; border-radius:18px; background:#fff; }
        .csl-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; }
        .csl-note { margin:0; font-size:12px; color:var(--muted-text); text-align:center; }
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
            #ocr-analytics-modal .modal-content { padding:64px 10px 10px!important; width:100vw!important; height:100vh!important; max-width:none!important; border-radius:0!important; margin:0!important; }
            #ocr-analytics-modal h3 { font-size:18px; margin:0!important; }
            #ocr-analytics-modal .chart-nav-bottom { gap:8px; margin:0 0 6px!important; }
            #ocr-analytics-modal .chart-year-framed { min-width:92px; height:36px; padding:4px 10px; }
            #ocr-analytics-modal .currency-select { margin:6px 0; width:100%; max-width:100%; min-width:0; box-sizing:border-box; }
            #ocr-analytics-modal .rate-chart-container { padding:6px; }

            #settings-modal .modal-content { padding:64px 15px 15px!important; }
            #settings-modal .store-visibility-table {
                font-size: 13px !important;
            }
            #settings-modal .store-visibility-table th,
            #settings-modal .store-visibility-table td {
                padding: 6px 4px !important;
            }
            #backup-modal .modal-content { display:flex; flex-direction:column; justify-content:space-between; }
            #backup-modal .form-row, #backup-modal h4 { margin:0; background:var(--group-block-bg); border:1px solid var(--border-color); }
            #backup-modal .form-row { border-radius:var(--radius-md); padding:14px; flex:0 0 auto; }
            #backup-modal h4 { border-radius:var(--radius-md) var(--radius-md) 0 0; border-bottom:none; padding:12px 14px 6px; flex:0 0 auto; }
            #backup-modal h4 + .form-row, #backup-modal h4 + #backup-list { border-radius:0 0 var(--radius-md) var(--radius-md); border-top:none; }
            #backup-modal .form-row + h4 { margin-top:12px; }
            #backup-modal #backup-list { flex:1; min-height:0; max-height:none!important; margin:0; background:var(--group-block-bg); border:1px solid var(--border-color); border-radius:0 0 var(--radius-md) var(--radius-md); padding:8px; overflow-y:auto; }
        }
        .modal .modal-content{display:flex!important;flex-direction:column!important;padding:0!important;overflow:hidden!important;background:color-mix(in srgb,var(--modal-bg) 92%,var(--surface-solid))!important;scrollbar-gutter:auto!important;-webkit-scrollbar-gutter:auto!important;}
        .modal:not(#import-modal):not(#bank-csv-import-modal) .modal-content,#bank-csv-import-modal .modal-content,#ocr-analytics-modal .modal-content,#settings-modal .modal-content,#share-modal .share-modal-content{padding:0!important;}
        .modal .modal-content::before{content:none!important;}
        .modal-topbar{flex:0 0 52px;min-height:52px;align-self:stretch;display:flex;align-items:center;gap:12px;padding:0 12px 0 clamp(16px,2.2vw,24px);border-bottom:1px solid var(--border-color);background:linear-gradient(120deg,var(--surface-rail),var(--modal-bg));border-radius:inherit;border-bottom-left-radius:0;border-bottom-right-radius:0;box-sizing:border-box;}
        .modal-topbar-title{position:static!important;inset:auto!important;transform:none!important;flex:1 1 auto;min-width:0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:18px!important;line-height:1.2!important;font-weight:800;color:var(--text-color)!important;text-align:left!important;margin:0!important;}
        .modal-topbar .modal-close{position:static!important;transform:none!important;flex:0 0 36px;top:auto!important;right:auto!important;margin:0!important;}
        .modal-body{flex:1 1 auto;min-height:0;width:100%;overflow:auto;padding:clamp(16px,2.2vw,24px);box-sizing:border-box;}
        #import-modal .modal-body{display:flex;flex-direction:column;padding:0;overflow:hidden;}
        #import-modal .ocrw-shell{flex:1 1 auto;min-height:0;padding-top:0!important;}
        #bank-csv-import-modal .modal-body{display:flex;flex-direction:column;padding:12px 12px 14px;}
        .table-modal .modal-body{display:flex;flex-direction:column;}
        #ocr-analytics-modal .modal-body{padding:10px;}
        #settings-modal .modal-body{padding:15px;}
        @media (max-width:768px){.modal-body{padding:12px;}.modal-topbar{border-radius:0;}#ocr-analytics-modal .modal-content,#settings-modal .modal-content{padding:0!important;}}
        *{scrollbar-width:thin;scrollbar-color:var(--scrollbar-thumb) var(--scrollbar-track);}
        *::-webkit-scrollbar{width:10px;height:10px;}
        *::-webkit-scrollbar-track{background:var(--scrollbar-track);}
        *::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb);border-radius:8px;}
        *::-webkit-scrollbar-thumb:hover{background:var(--scrollbar-thumb-hover);}
        .financial-summary-bar{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:12px 0 16px;}
        .financial-summary-bar>div,.analytics-summary-grid>section{background:var(--group-block-bg);border:1px solid var(--border-color);border-radius:8px;padding:12px;color:var(--text-color);}
        .financial-summary-bar span{display:block;font-size:12px;opacity:.75;margin-bottom:4px;}
        .financial-summary-bar strong{display:block;font-size:18px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .analytics-summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:10px 0;flex:0 0 auto;}
        .analytics-summary-grid h4{margin:0 0 8px;font-size:14px;}
        .analytics-summary-table{width:100%;border-collapse:collapse;font-size:13px;}
        .analytics-summary-table th,.analytics-summary-table td{padding:6px;border-bottom:1px solid var(--border-color);text-align:right;}
        .analytics-summary-table th:first-child,.analytics-summary-table td:first-child{text-align:left;}
        #financial-income{color:#16a34a;}
        #financial-expenses{color:#dc2626;}
        @media (min-width:769px){.chart-controls-row{display:flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;flex-wrap:nowrap!important;}.chart-controls-nav{display:flex;gap:8px;flex:0 0 auto;}.chart-controls-row .currency-selector{flex:1 1 auto;min-width:0;flex-wrap:nowrap!important;}.chart-controls-row .chart-toggle-btn{flex:0 0 auto;white-space:nowrap;}.chart-controls-row .chart-year{display:none;}}
        .settings-menu-bar{display:flex!important;width:100%!important;max-width:520px!important;margin:0 auto 16px!important;padding:4px!important;gap:4px!important;background:var(--surface-solid)!important;border:1px solid var(--border-color)!important;border-radius:8px!important;box-shadow:none!important;overflow:visible!important;}
        .settings-menu-btn{display:inline-flex!important;align-items:center!important;justify-content:center!important;flex:1 1 0!important;width:auto!important;padding:9px 12px!important;border:1px solid transparent!important;border-radius:6px!important;background:transparent!important;color:var(--muted-text)!important;font-weight:700!important;transition:background .15s,border-color .15s,color .15s!important;}
        .settings-menu-btn:hover,.settings-menu-btn.active{background:var(--group-block-bg)!important;border-color:var(--border-color)!important;color:var(--text-color)!important;}
        .settings-menu-btn:before,.settings-menu-btn:after,.settings-menu-bar #indicator{display:none!important;}
        #store-category-input{position:absolute!important;width:1px!important;height:1px!important;opacity:0!important;pointer-events:none!important;}
        .store-category-row{align-items:flex-start;}.store-category-picker{display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:8px;width:100%;}
        .store-category-option{display:flex;align-items:center;gap:8px;min-width:0;padding:9px 10px;border:1px solid var(--border-color);border-radius:6px;background:var(--group-block-bg);color:#dca90a;font-weight:700;cursor:pointer;text-align:left;}.store-category-option[data-used="1"]{color:#16a34a;}.store-category-option.active{outline:2px solid color-mix(in srgb,var(--primary-color) 70%,#fff);background:color-mix(in srgb,var(--primary-color) 12%,var(--group-block-bg));}.store-category-option svg{width:18px;height:18px;flex:0 0 18px;}
        .store-photo-crop-card{display:flex;flex-direction:column;align-items:center;width:fit-content;max-width:100%;border:2px solid var(--border-color);border-radius:20px;overflow:hidden;background:var(--modal-bg);box-shadow:0 8px 22px rgba(0,0,0,.08);margin:10px 0;}.store-photo-frame{width:220px;height:180px;max-width:100%;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--group-block-bg) 84%,transparent);overflow:hidden;}.store-photo-frame img{width:100%;height:100%;object-fit:contain;display:block;}.store-photo-crop-btn{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 14px;background:var(--modal-bg);color:var(--text-color);border:0;border-top:1px solid var(--border-color);font-weight:700;cursor:pointer;}.store-photo-crop-btn:hover{background:var(--group-block-bg);}.store-photo-crop-btn svg{width:16px;height:16px;}
        .crop-modal-content{width:min(840px,calc(100vw - 32px))!important;height:min(760px,calc(100dvh - 32px))!important;}.cropper-container{flex:1 1 auto!important;min-height:360px!important;max-height:none!important;margin:0!important;background:color-mix(in srgb,var(--group-block-bg) 85%,#000);border:1px solid var(--border-color);border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden;}.cropper-container img{display:block;max-width:100%;max-height:100%;}.crop-save-btn{margin-top:10px;align-self:flex-end;}.analytics-summary-table td.expense-gradient-cell{font-weight:800;background:none;}
        #crop-modal{z-index:30000!important;}
        #crop-modal .modal-content{z-index:30001!important;}
        @media (min-width:769px){.chart-controls-row{display:grid!important;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)!important;align-items:center!important;gap:10px!important;}.chart-controls-row .currency-selector{grid-column:1!important;justify-self:start!important;display:flex!important;flex-wrap:nowrap!important;min-width:0!important;}.chart-controls-row .chart-controls-nav{grid-column:2!important;justify-self:center!important;display:flex!important;gap:8px!important;}.chart-controls-row .chart-controls-actions{grid-column:3!important;justify-self:end!important;display:flex!important;gap:10px!important;align-items:center!important;min-width:0!important;}.chart-controls-row .chart-toggle-btn{white-space:nowrap!important;}.chart-controls-row .chart-year{display:none!important;}}
        @media (orientation:portrait) and (min-width:769px) and (max-width:1024px){.chart-controls-row{grid-template-columns:auto minmax(0,1fr) auto!important;}.chart-controls-row .chart-controls-nav{justify-self:center!important;}}
        @media(max-width:768px){.analytics-summary-grid{grid-template-columns:1fr;}.financial-summary-bar{grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;}.financial-summary-bar>div{padding:8px;}.financial-summary-bar span{font-size:10px;margin-bottom:2px;}.financial-summary-bar strong{font-size:14px;}}
    </style>
    <script>
    function syncModalBodyState(){document.body.classList.toggle('modal-open',!!document.querySelector('.modal[style*="display: flex"]'));}
    function showModal(id){if(typeof setupModalChrome==='function')setupModalChrome();var el=document.getElementById(id);if(el){el.style.display='flex';syncModalBodyState();}}
    function closeModal(id){var el=document.getElementById(id);if(el){el.style.display='none';syncModalBodyState();}}
    function showOCRAnalyticsModal(){showModal('ocr-analytics-modal');if(typeof updateYearButtons==='function')updateYearButtons();if(typeof loadOCRAnalytics==='function')loadOCRAnalytics();}
    </script>
    <?php require_once dirname(__DIR__, 3) . '/lib/sentry_bootstrap.php'; app_sentry_browser_script('spends'); ?>
</head>
