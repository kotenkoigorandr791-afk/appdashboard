<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<style>
:root{--accent-solid:#<?php echo $color; ?>;--accent-soft:color-mix(in srgb,var(--accent-solid) 24%,transparent);--accent-soft-strong:color-mix(in srgb,var(--accent-solid) 34%,transparent);--accent-border:color-mix(in srgb,var(--accent-solid) 62%,transparent);--accent-text:color-mix(in srgb,var(--accent-solid) 90%,white 10%);--accent:var(--accent-solid);<?php if ($theme === 'dark'): ?>--bg:#404040;--panel:#2d2d2d;--fg:#ffffff;--muted:#aaaaaa;--border:#4a4a4a;<?php else: ?>--bg:#f5f5f5;--panel:#ffffff;--fg:#333333;--muted:#666666;--border:#cccccc;<?php endif; ?>--md-primary:<?php echo $theme === 'light' ? '#6750A4' : '#D0BCFF'; ?>;--md-secondary-container:<?php echo $theme === 'light' ? '#E8DEF8' : '#4A4458'; ?>;--md-surface:<?php echo $theme === 'light' ? '#FEF7FF' : '#2B2930'; ?>;}
*{margin:0;padding:0;box-sizing:border-box;}
body{height:100vh;overflow:hidden;background:<?php echo $theme === 'light' ? '#f0f0f0' : '#404040'; ?>;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
#toolbar{background:<?php echo $theme === 'light' ? '#e0e0e0' : '#2d2d2d'; ?>;padding:10px 8px;display:flex;flex-direction:column;gap:10px;position:fixed;top:0;left:0;bottom:0;z-index:100;box-shadow:1px 0 4px rgba(0,0,0,0.15);overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;scrollbar-width:thin;-ms-overflow-style:auto;justify-content:flex-start;align-items:stretch;min-height:100vh;min-height:100dvh;height:100vh;height:100dvh;width:clamp(72px,7.4vw,92px);max-width:clamp(72px,7.4vw,92px);flex-wrap:nowrap;border-radius:0;scrollbar-color:<?php echo $theme === 'light' ? '#999 #ccc' : '#fff #404040'; ?>;}
#toolbar::-webkit-scrollbar{width:3px;height:3px;}
#toolbar::-webkit-scrollbar-track{background:transparent;}
#toolbar::-webkit-scrollbar-thumb{background:<?php echo $theme === 'light' ? '#999' : '#555'; ?>;border-radius:2px;opacity:0;transition:opacity 0.2s ease;}
#toolbar:hover::-webkit-scrollbar-thumb{opacity:1;}
button{background:0;border:0;border-radius:4px;padding:6px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;min-width:32px;position:relative;z-index: auto;}
button:hover{background:<?php echo $theme === 'light' ? '#c0c0c0' : '#4a4a4a'; ?>;z-index: 10000;}
button.active{background:<?php echo $theme === 'light' ? '#a0a0a0' : '#4a4a4a'; ?>;}
button.active svg{stroke:<?php echo $theme === 'light' ? '#333' : '#<?php echo $color; ?>'; ?>;}
button:not(.active) svg{stroke:<?php echo $theme === 'light' ? '#333' : '#<?php echo $color; ?>'; ?>;}
button[data-tooltip]{position:relative;}


svg{width:18px;height:18px;stroke:<?php echo $theme === 'light' ? '#333' : '#fff'; ?>;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;}
/* Page info pagination */
.page-info-pagination{display:inline-flex;align-items:center;gap:4px;background:<?php echo $theme === 'light' ? 'color-mix(in srgb,var(--panel) 96%,transparent)' : 'color-mix(in srgb,var(--panel) 12%,transparent)'; ?>;border-radius:20px;padding:4px 8px;min-width:56px;color:<?php echo $theme === 'light' ? '#6b7280' : '#fff'; ?>;}
.page-input{background:transparent;border:none;outline:none;padding:0;margin:0;font-family:inherit;font-size:inherit;font-weight:inherit;color:inherit;letter-spacing:inherit;width:1.2ch;text-align:center;caret-color:#3b82f6;-moz-appearance:textfield;appearance:textfield;}
.page-input::-webkit-outer-spin-button,.page-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}
.page-input:hover{background-color:rgba(0,0,0,0.04);border-radius:4px;}
.page-input:focus{background-color:#f9fafb;border-radius:4px;box-shadow:0 0 0 2px rgba(59,130,246,0.2);}
.page-total{user-select:none;color:<?php echo $theme === 'light' ? '#6b7280' : '#d7d7d7'; ?>;font-size:11px;}
.color-selector{width:36px;height:36px;border-radius:50%;border:2px solid currentColor;background:radial-gradient(circle at 30% 30%,#ff6b6b,#ee5a5a);box-shadow:0 2px 8px rgba(255,107,107,0.3);cursor:pointer;transition:transform .15s ease,box-shadow .15s ease;outline:none;padding:0;}
.color-selector:hover{transform:scale(1.05);box-shadow:0 4px 12px rgba(255,107,107,0.4);}
.color-selector:active{transform:scale(0.98);}

.zoom-wrapper{width:100%;background-color:<?php echo $theme === 'light' ? '#f0f2f5' : '#2a2a2a'; ?>;padding:10px 16px;border-radius:50px;box-sizing:border-box;display:flex;align-items:center;box-shadow:inset 0 2px 4px rgba(0,0,0,0.04);}
.zoom-slider{-webkit-appearance:none;appearance:none;width:100%;height:6px;background:#d1d5db;border-radius:10px;outline:none;cursor:pointer;}
.zoom-slider::-webkit-slider-runnable-track{height:6px;border-radius:10px;background:linear-gradient(to right,#4a90e2 0%,#4a90e2 var(--val,50%),#d1d5db var(--val,50%),#d1d5db 100%);}
.zoom-slider::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;width:20px;height:20px;background:#fff;border:2px solid #4a90e2;border-radius:50%;margin-top:-7px;box-shadow:0 2px 6px rgba(74,144,226,0.4);transition:transform .15s;}
.zoom-slider::-webkit-slider-thumb:hover{transform:scale(1.15);}
.zoom-slider::-moz-range-track{height:6px;border-radius:10px;background:#d1d5db;}
.zoom-slider::-moz-range-progress{height:6px;border-radius:10px;background:#4a90e2;}
.zoom-slider::-moz-range-thumb{width:20px;height:20px;background:#fff;border:2px solid #4a90e2;border-radius:50%;box-shadow:0 2px 6px rgba(74,144,226,0.4);cursor:grab;}
.zoom-select-container{position:relative;display:inline-flex;align-items:center;min-width:100%;margin:0;}
select.zoom-select{appearance:none;-webkit-appearance:none;-moz-appearance:none;background:<?php echo $theme === 'light' ? '#f5f5f5' : '#404040'; ?>;border:1px solid <?php echo $theme === 'light' ? '#ccc' : '#4a4a4a'; ?>;border-radius:4px;color:#fff !important;font-size:11px;font-weight:600;padding:5px 22px 5px 6px;cursor:pointer;width:100%;outline:none;text-align-last:center;}
select option{color:#333 !important;background:#fff;}
select.zoom-select:hover{background:<?php echo $theme === 'light' ? '#e8e8e8' : '#4a4a4a'; ?>;border-color:#<?php echo $color; ?>;}
select.zoom-select:focus{border-color:#<?php echo $color; ?>;box-shadow:0 0 0 2px #<?php echo $color; ?>33;}
.zoom-select-container .ui-select ~ .zoom-select-arrow{display:none!important;}
.zoom-select-arrow{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:14px;height:14px;pointer-events:none;stroke:<?php echo $theme === 'light' ? '#666' : '#aaa'; ?>;stroke-width:2;fill:none;}
.zoom-select-container:hover .zoom-select-arrow{stroke:#<?php echo $color; ?>;}
#viewer-container{position:fixed;top:0;left:clamp(72px,7.4vw,92px);right:0;bottom:0;overflow:auto;background:<?php echo $theme === 'light' ? '#e0e0e0' : '#404040'; ?>;-webkit-overflow-scrolling:touch;scrollbar-color:<?php echo $theme === 'light' ? '#999 #ccc' : '#fff #404040'; ?>;scrollbar-width:thin;z-index:1;touch-action:pan-x pan-y;}
#viewer-container::-webkit-scrollbar{width:8px;height:8px;}
#viewer-container::-webkit-scrollbar-track{background:<?php echo $theme === 'light' ? '#ccc' : '#404040'; ?>;}
#viewer-container::-webkit-scrollbar-thumb{background:<?php echo $theme === 'light' ? '#999' : '#fff'; ?>;border-radius:4px;}
#viewer-container::-webkit-scrollbar-thumb:hover{background:<?php echo $theme === 'light' ? '#666' : '#e0e0e0'; ?>;}
.pdf-viewer{position:relative;margin:0 auto;padding:10px 0;display:flex;flex-direction:column;align-items:center;z-index:1;}
.pdf-viewer.is-zooming{will-change:transform;backface-visibility:hidden;-webkit-font-smoothing:antialiased;}
.page-wrapper{position:relative;margin:0 auto 10px auto;box-shadow:0 2px 8px rgba(0,0,0,0.15);background:#fff;}
.canvas-container{position:relative;width:100%;height:100%;}
.canvas-container canvas{position:absolute;top:0;left:0;width:100% !important;height:100% !important;display:block;}
.canvas-container .pdf-canvas{z-index:1;}
.canvas-container .draw-canvas{z-index:2;pointer-events:none;}
.canvas-container .draw-canvas.active{pointer-events:auto;cursor:crosshair;}
.canvas-container .text-layer{z-index:3;position:absolute;top:0;left:0;width:100%;height:100%;overflow:hidden;line-height:1;text-size-adjust:none;-webkit-text-size-adjust:none;forced-color-adjust:none;transform-origin:0 0;pointer-events:auto;cursor:text;user-select:text;-webkit-user-select:text;-moz-user-select:text;-ms-user-select:text;-webkit-touch-callout:default;}
.canvas-container .text-layer span,.canvas-container .text-layer br{position:absolute;color:transparent;white-space:pre;cursor:text;transform-origin:0% 0%;}
.canvas-container .text-layer ::selection{background:rgba(0,120,255,0.35);}
.canvas-container .text-layer ::-moz-selection{background:rgba(0,120,255,0.35);}
.canvas-container .text-layer.no-select{pointer-events:none;user-select:none;-webkit-user-select:none;}
.toolbar-group{display:flex;flex-direction:column;align-items:stretch;gap:6px;padding:0 0 10px;border-right:0;border-bottom:1px solid <?php echo $theme === 'light' ? '#ccc' : '#4a4a4a'; ?>;flex-shrink:0;}
.toolbar-group:last-child{border-bottom:0;padding-bottom:0;}
.toolbar-row{display:flex;align-items:center;justify-content:center;gap:6px;}
.toolbar-label{color:<?php echo $theme === 'light' ? '#555' : '#d7d7d7'; ?>;font-size:11px;font-weight:700;letter-spacing:0.03em;text-transform:uppercase;text-align:center;}
#progress-container{position:fixed;top:0;left:clamp(72px,7.4vw,92px);right:0;height:2px;background:#1a1a1a;z-index:1000;overflow:hidden;}
#progress-bar{height:100%;background:#<?php echo $color; ?>;width:0%;transition:width 0.3s ease;box-shadow:0 0 3px #<?php echo $color; ?>;}
#progress-text{position:fixed;top:50%;left:50%;transform:translate(-50%, -50%);color:<?php echo $theme === 'light' ? '#333' : '#fff'; ?>;font-size:36px;font-weight:300;font-style:italic;opacity:0.8;z-index:1001;pointer-events:none;text-shadow:0 2px 4px rgba(0,0,0,0.2);transition:opacity 0.3s ease;background:<?php echo $theme === 'light' ? 'rgba(255,255,255,0.65)' : 'rgba(0,0,0,0.5)'; ?>;padding:8px 20px;border-radius:10px;}
#password-dialog{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:2000;backdrop-filter:blur(5px);}
.password-dialog-content{background:<?php echo $theme === 'light' ? '#fff' : '#2d2d2d'; ?>;padding:24px;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.3);max-width:320px;width:90%;color:<?php echo $theme === 'light' ? '#333' : '#fff'; ?>;}
.password-dialog-content h3{margin-bottom:16px;font-weight:400;font-size:18px;}
.password-dialog-content input{width:100%;padding:10px 12px;background:<?php echo $theme === 'light' ? '#f5f5f5' : '#404040'; ?>;border:1px solid <?php echo $theme === 'light' ? '#ccc' : '#4a4a4a'; ?>;color:<?php echo $theme === 'light' ? '#333' : '#fff'; ?>;border-radius:4px;font-size:16px;margin-bottom:16px;}
.password-dialog-content input:focus{outline:none;border-color:#<?php echo $color; ?>;}
.password-dialog-buttons{display:flex;gap:8px;justify-content:flex-end;}
.password-dialog-buttons button{padding:8px 16px;border-radius:4px;border:none;font-size:14px;cursor:pointer;transition:all 0.2s;min-width:80px;}
.password-dialog-buttons button:hover{opacity:0.9;}
#password-submit{background:var(--accent-soft);border:1px solid var(--accent-border);color:var(--accent-text);}
#password-submit:hover{background:var(--accent-soft-strong);}
#password-cancel{background:<?php echo $theme === 'light' ? '#e0e0e0' : '#4a4a4a'; ?>;color:<?php echo $theme === 'light' ? '#333' : '#fff'; ?>;}
#password-error{color:#ff4444;margin-top:8px;font-size:14px;display:none;}
.draw-controls{display:flex;flex-direction:column;align-items:stretch;gap:8px;margin-left:0;background:<?php echo $theme === 'light' ? '#FEF7FF' : '#1D1B20'; ?>;padding:8px;border-radius:12px;}
/* Material 3 color swatch */
.draw-color-picker{width:26px;height:26px;border-radius:50%;border:2px solid var(--md-primary);cursor:pointer;padding:0;background:var(--md-surface);align-self:center;flex:0 0 auto;box-sizing:border-box;box-shadow:0 1px 3px rgba(0,0,0,.24);transition:transform .15s ease,box-shadow .15s ease;-webkit-appearance:none;appearance:none;}
.draw-color-picker:hover{transform:scale(1.1);box-shadow:0 3px 8px color-mix(in srgb,var(--md-primary) 35%,transparent);}
.draw-color-picker:active{transform:scale(.96);}
.draw-color-picker::-webkit-color-swatch-wrapper{padding:2px;}
.draw-color-picker::-webkit-color-swatch{border:none;border-radius:50%;}
.draw-color-picker::-moz-color-swatch{border:none;border-radius:50%;}
/* Material 3 range slider */
.draw-size-slider{-webkit-appearance:none;appearance:none;width:100%;height:8px;margin:0;border-radius:8px;outline:none;cursor:pointer;background:linear-gradient(to right,var(--md-primary) 0%,var(--md-primary) var(--draw-pct,15%),var(--md-secondary-container) var(--draw-pct,15%),var(--md-secondary-container) 100%);transition:background-color .2s ease;}
.draw-size-slider::-webkit-slider-runnable-track{height:8px;border-radius:8px;background:transparent;}
.draw-size-slider::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;width:18px;height:18px;margin-top:-5px;border-radius:50%;background:#fff;border:2px solid var(--md-primary);box-shadow:0 2px 4px rgba(0,0,0,.2);transition:transform .15s ease,box-shadow .15s ease;}
.draw-size-slider:hover::-webkit-slider-thumb{transform:scale(1.1);box-shadow:0 4px 8px color-mix(in srgb,var(--md-primary) 30%,transparent);}
.draw-size-slider:active::-webkit-slider-thumb{transform:scale(1.2);}
.draw-size-slider::-moz-range-track{height:8px;border-radius:8px;background:transparent;}
.draw-size-slider::-moz-range-progress{height:8px;border-radius:8px;background:var(--md-primary);}
.draw-size-slider::-moz-range-thumb{width:18px;height:18px;border-radius:50%;background:#fff;border:2px solid var(--md-primary);box-shadow:0 2px 4px rgba(0,0,0,.2);transition:transform .15s ease,box-shadow .15s ease;}
.draw-size-slider:hover::-moz-range-thumb{transform:scale(1.1);}
@media (min-width:769px){
#toolbar{padding:clamp(4px,0.7vh,8px) clamp(4px,0.5vw,6px);gap:clamp(6px,0.8vh,10px)}
.toolbar-group{background:color-mix(in srgb,<?php echo $theme === 'light' ? '#ffffff' : '#1f1f1f'; ?> 82%,transparent);border:1px solid <?php echo $theme === 'light' ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.08)'; ?>;border-radius:clamp(12px,1.5vh,16px);padding:clamp(6px,0.9vh,10px) clamp(4px,0.5vw,6px) clamp(8px,1.1vh,12px);box-shadow:0 10px 24px rgba(0,0,0,0.12);gap:clamp(4px,0.7vh,6px)}
.toolbar-group:last-child{padding-bottom:clamp(8px,1.1vh,12px)}
#toolbar button,#toolbar #page-info,#toolbar .zoom-select-container,#toolbar .toolbar-label{background:color-mix(in srgb,<?php echo $theme === 'light' ? '#f7f7f7' : '#2c2c2c'; ?> 88%,transparent);border:1px solid <?php echo $theme === 'light' ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.08)'; ?>;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.08)}
#toolbar button{min-height:clamp(28px,3.8vh,36px);padding:clamp(4px,0.55vh,6px);min-width:0}
#toolbar svg{width:clamp(14px,1.9vh,18px);height:clamp(14px,1.9vh,18px)}
#toolbar #page-info{padding:clamp(5px,0.7vh,8px) clamp(4px,0.5vw,6px);font-size:clamp(11px,1.45vh,13px)}
#toolbar #page-input{width:clamp(22px,2.8vh,28px);font-size:clamp(10px,1.35vh,12px);padding:clamp(2px,0.25vh,3px) 2px}
#toolbar .toolbar-label{padding:clamp(4px,0.55vh,6px) clamp(5px,0.65vw,8px);font-size:clamp(9px,1.2vh,11px)}
#toolbar .zoom-select-container{padding:2px}
#toolbar select.zoom-select{font-size:clamp(9px,1.15vh,11px);padding:clamp(4px,0.5vh,5px) 20px clamp(4px,0.5vh,5px) 4px}
#toolbar .zoom-select-arrow{right:4px;width:clamp(11px,1.5vh,14px);height:clamp(11px,1.5vh,14px)}
#toolbar select.zoom-select{background:transparent;border:0;box-shadow:none}
#toolbar .toolbar-row{padding:0 clamp(2px,0.3vw,4px);gap:clamp(4px,0.55vw,8px)}
}
@media (max-width:768px){
#toolbar{padding:4px;min-height:44px;justify-content:flex-start;flex-direction:row;top:0;left:0;right:0;bottom:auto;width:100%;max-width:none;height:auto;box-shadow:0 1px 4px rgba(0,0,0,0.15);overflow-x:auto;overflow-y:hidden;align-items:center;}
button{padding:8px;min-width:40px;z-index: auto;}
svg{width:20px;height:20px;}
#page-input{width:45px;padding:6px;font-size:14px;}
#page-info{flex-direction:row;gap:2px;margin:0 2px;font-size:14px;}
.zoom-select-container{min-width:75px;}
select.zoom-select{font-size:12px;padding:6px 24px 6px 8px;}
#progress-text{font-size:28px;}
#viewer-container{top:44px;left:0;}
#progress-container{top:44px;left:0;}
.toolbar-group{flex-direction:row;align-items:center;padding:0 2px;border-bottom:0;border-right:1px solid <?php echo $theme === 'light' ? '#ccc' : '#4a4a4a'; ?>;}
.toolbar-group:last-child{border-right:0;}
.draw-controls{flex-direction:row;align-items:center;}
.toolbar-row{gap:4px;}
.toolbar-label{font-size:10px;}
button[data-tooltip]:hover::after{font-size:11px;padding:3px 6px;margin-top:4px;}
button[data-tooltip]:hover::before{margin-top:-4px;}
}
@media (max-width:480px){
.toolbar-group{padding:0 2px;}
.zoom-select-container{min-width:65px;}
select.zoom-select{padding:5px 22px 5px 8px;font-size:13px;}
svg{width:18px;height:18px;}
button{padding:6px;min-width:36px;}
}
/* Single-frame zoom control on desktop: the outer .zoom-select-container card is
   the only visual frame, the custom button fills it without its own border. */
@media (min-width:769px){
#toolbar .zoom-select-container{padding:0;}
#toolbar .zoom-select-container .ui-select{display:flex;width:100%;max-width:none;height:100%;border-radius:inherit;}
#toolbar .zoom-select-container .ui-select-btn{width:100%;height:auto;min-width:0;border:0;border-radius:inherit!important;background:transparent;box-shadow:none;padding-inline:2px;gap:2px;}
#toolbar .zoom-select-container .ui-select-chevron{width:10px;height:10px;flex:0 0 10px;}
#toolbar .zoom-select-container .ui-select-btn:hover,
#toolbar .zoom-select-container .ui-select-btn:focus-visible{background:color-mix(in srgb,var(--accent) 14%,transparent);}
#toolbar .zoom-select-container .ui-select-label{flex:1;text-align:center;font-size:10px;font-weight:600;letter-spacing:0;}
}
</style>
<style><?php readfile(dirname(__DIR__, 2) . '/shared/assets/editor-ui.css'); ?></style>
</head>
<body data-theme="<?php echo $theme; ?>" class="theme-<?php echo $theme; ?>">
<div id="progress-container">
<div id="progress-bar"></div>
</div>
<div id="progress-text">0%</div>
<div id="password-dialog" style="display: none;">
<div class="password-dialog-content">
<h3><?php echo $t['password_title']; ?></h3>
<input type="password" id="password-input" placeholder="<?php echo $t['password_placeholder']; ?>" autocomplete="off">
<div id="password-error"><?php echo $t['password_error']; ?></div>
<div class="password-dialog-buttons">
<button id="password-cancel"><?php echo $t['cancel']; ?></button>
<button id="password-submit"><?php echo $t['ok']; ?></button>
</div>
</div>
</div>
<div id="toolbar">
<div class="toolbar-group">
<div class="page-info-pagination">
                <input type="text" id="page-input" class="page-input" value="1" title="<?php echo $t['page']; ?>" inputmode="numeric" pattern="[0-9]*" aria-label="Страница" oninput="this.style.width = Math.max(1.2, this.value.length * 0.8) + 'ch';">
                <span class="page-total">/<span id="page-count">1</span></span>
              </div>
<div class="toolbar-row">
<button id="prev" data-tooltip="<?php echo $t['prev_page']; ?>"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
<button id="next" data-tooltip="<?php echo $t['next_page']; ?>"><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></button>
</div>
</div>
<div class="toolbar-group">
<div class="toolbar-label">Zoom</div>

<div class="zoom-select-container">
<select id="zoom-select" class="zoom-select" data-ui-menu-min-width="72">
<option value="0.25">25%</option>
<option value="0.5">50%</option>
<option value="0.75">75%</option>
<option value="1" selected>100%</option>
<option value="1.25">125%</option>
<option value="1.5">150%</option>
<option value="1.75">175%</option>
<option value="2">200%</option>
<option value="2.25">225%</option>
<option value="2.5">250%</option>
</select>
<svg class="zoom-select-arrow" viewBox="0 0 24 24">
<polyline points="6 9 12 15 18 9"/>
</svg>
</div>
<div class="toolbar-row">
<button id="zoom-out" data-tooltip="<?php echo $t['zoom_out']; ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg></button>
<button id="zoom-in" data-tooltip="<?php echo $t['zoom_in']; ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></button>
</div>
<button id="fit" data-tooltip="<?php echo $t['fit_width']; ?>">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="m18 8 4 4-4 4"/>
<path d="M2 12h20"/>
<path d="m6 8-4 4 4 4"/>
</svg>
</button>
</div>
<div class="toolbar-group">
<div class="toolbar-row">
<button id="rotate-left" data-tooltip="<?php echo $t['rotate_left']; ?>">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
<path d="M3 3v5h5"/>
</svg>
</button>
<button id="rotate-right" data-tooltip="<?php echo $t['rotate_right']; ?>">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
<path d="M21 3v5h-5"/>
</svg>
</button>
</div>
</div>
<div class="toolbar-group">
<button id="draw-toggle" data-tooltip="<?php echo $t['draw']; ?>">
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<path d="m11 10 3 3"/>
<path d="M6.5 21A3.5 3.5 0 1 0 3 17.5a2.62 2.62 0 0 1-.708 1.792A1 1 0 0 0 3 21z"/>
<path d="M9.969 17.031 21.378 5.624a1 1 0 0 0-3.002-3.002L6.967 14.031"/>
</svg>
</button>
<div class="draw-controls" id="draw-controls" style="display:none;">
<input type="color" id="draw-color" value="#ff0000" class="draw-color-picker" title="<?php echo $t['draw_color']; ?>">
<input type="range" id="draw-size" min="1" max="20" value="3" class="draw-size-slider" title="<?php echo $t['draw_size']; ?>">
<button id="draw-undo" data-tooltip="<?php echo $t['undo']; ?>">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M3 7v6h6"/>
<path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/>
</svg>
</button>
<button id="draw-clear" data-tooltip="<?php echo $t['clear_drawings']; ?>">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M3 6h18"/>
<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
<line x1="10" y1="11" x2="10" y2="17"/>
<line x1="14" y1="11" x2="14" y2="17"/>
</svg>
</button>
</div>
</div>
</div>
<div id="viewer-container">
<div id="viewer" class="pdf-viewer"></div>
</div>
<script><?php echo file_get_contents(dirname(__DIR__, 4) . '/assets/file-load-progress.js'); ?></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function() {
<?php require __DIR__ . '/../public/assets/js/page-script1.js.php'; ?>

{
    const currentPageInput = document.getElementById('page-input');
    currentPageInput?.addEventListener('input', function() {
        this.style.width = Math.max(1.2, this.value.length * 0.8) + 'ch';
    });
}

})();
</script>

<script><?php readfile(dirname(__DIR__, 2) . '/shared/assets/editor-ui.js'); ?></script>
</body>
</html>
