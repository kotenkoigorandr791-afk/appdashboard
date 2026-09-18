<?php if($isAdmin): ?>
<!-- ====================== ADMIN PANEL ====================== -->
<style>
/* ══════════════════════════════════════════════════════════
   ADMIN PANEL — uses CSS variables from main app theme
   ══════════════════════════════════════════════════════════ */
#admin-panel-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 90000;
    background: var(--bg-color);
    color: var(--text-color);
    font-family: 'Courier New', Consolas, monospace;
    overflow: hidden;
    box-sizing: border-box;
}
#admin-panel-overlay.active { display: flex; flex-direction: column; }
#admin-panel-overlay,
#ap-topbar,
#ap-sidebar,
#ap-content {
    transition: background-color .35s ease, border-color .35s ease, color .35s ease;
}
:root {
    --ap-omniroute-edge-bg: <?php echo $themeForAppLink === 'dark' ? '#0b0d10' : '#f8fafc'; ?>;
    --ap-omniroute-edge-border: <?php echo $themeForAppLink === 'dark' ? '#1b2330' : '#dbe3ed'; ?>;
    --ap-omniroute-edge-text: <?php echo $themeForAppLink === 'dark' ? '#e5edf7' : '#111827'; ?>;
    --ap-omniroute-edge-hover: <?php echo $themeForAppLink === 'dark' ? '#121823' : '#eef3f8'; ?>;
    --ap-omniroute-edge-active: <?php echo $themeForAppLink === 'dark' ? '#172033' : '#e6edf5'; ?>;
    --ap-omniroute-edge-active-border: <?php echo $themeForAppLink === 'dark' ? '#2b3b52' : '#c7d3e0'; ?>;
}
#admin-panel-overlay.ap-edge-omniroute {
    --ap-edge-bg: var(--ap-omniroute-edge-bg, #0b0d10);
    --ap-edge-border: var(--ap-omniroute-edge-border, #1b2330);
    --ap-edge-text: var(--ap-omniroute-edge-text, #e5edf7);
    background: var(--ap-edge-bg);
    color: var(--ap-edge-text);
}
#admin-panel-overlay.ap-edge-omniroute #ap-topbar,
#admin-panel-overlay.ap-edge-omniroute #ap-sidebar,
#admin-panel-overlay.ap-edge-omniroute #ap-content {
    background: var(--ap-edge-bg) !important;
    border-color: var(--ap-edge-border) !important;
    color: var(--ap-edge-text) !important;
}
#admin-panel-overlay.ap-edge-omniroute .ap-nav-btn {
    color: var(--ap-edge-text);
}
#admin-panel-overlay.ap-edge-omniroute .ap-nav-btn:hover {
    background: var(--ap-omniroute-edge-hover, #121823);
}
#admin-panel-overlay.ap-edge-omniroute .ap-nav-btn.active {
    background: var(--ap-omniroute-edge-active, #172033);
    border-color: var(--ap-omniroute-edge-active-border, #2b3b52);
}
#ap-gate-login{
    display:none;
    width:min(400px,92vw);
    position:relative;
    overflow:hidden;
    border-radius:20px;
    border:1px solid color-mix(in srgb,var(--border-color) 80%,transparent);
    background:
        radial-gradient(120% 90% at 0% 0%, color-mix(in srgb,var(--primary-color) 28%,transparent) 0%, transparent 55%),
        radial-gradient(90% 70% at 100% 100%, color-mix(in srgb,var(--primary-color) 16%,transparent) 0%, transparent 50%),
        color-mix(in srgb,var(--modal-bg, rgba(18,24,32,.92)) 92%, var(--bg-color));
    box-shadow: var(--glass-shadow, 0 24px 60px rgba(0,0,0,.35)), inset 0 1px 0 rgba(255,255,255,.06);
    backdrop-filter: blur(18px) saturate(1.35);
    -webkit-backdrop-filter: blur(18px) saturate(1.35);
    padding:28px 26px 22px;
    box-sizing:border-box;
    text-align:left;
    font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
    animation:ap-gate-in .32s cubic-bezier(.2,.8,.2,1);
}
/* Glass/glow layer like auth cards */
#ap-gate-login::before{
    content:'';
    position:absolute;
    inset:-1px;
    border-radius:22px;
    padding:1px;
    background:linear-gradient(135deg,transparent 0%,color-mix(in srgb,var(--primary-color) 70%,transparent) 25%,transparent 50%,rgba(34,211,238,0.50) 75%,transparent 100%);
    background-size:300% 300%;
    -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);
    -webkit-mask-composite:xor;
    mask-composite:exclude;
    animation:ap-gate-border-rotate 6s linear infinite;
    pointer-events:none;
    opacity:.85;
}
#ap-gate-login::after{
    content:'';
    position:absolute;
    top:-50%;
    left:-50%;
    width:200%;
    height:200%;
    background:radial-gradient(circle at 30% 20%,rgba(255,255,255,0.05),transparent 55%);
    pointer-events:none;
    animation:ap-gate-shimmer 8s ease-in-out infinite;
}
@keyframes ap-gate-border-rotate{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes ap-gate-shimmer{0%,100%{transform:translate(0,0) rotate(0deg)}50%{transform:translate(10%,5%) rotate(5deg)}}
@keyframes ap-gate-in{
    from{opacity:0;transform:translateY(14px) scale(.98)}
    to{opacity:1;transform:none}
}
#ap-gate-login .ap-gate-badge{
    display:inline-flex;align-items:center;gap:8px;
    font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
    color:color-mix(in srgb,var(--primary-color) 85%,var(--text-color));
    margin-bottom:14px;
}
#ap-gate-login .ap-gate-badge svg{width:14px;height:14px;flex-shrink:0}
#ap-gate-login .ap-gate-title{
    font-size:22px;font-weight:750;letter-spacing:-.02em;line-height:1.2;
    margin:0 0 8px;color:var(--text-color);
}
#ap-gate-login .ap-gate-sub{
    font-size:13.5px;line-height:1.45;opacity:.72;margin:0 0 20px;color:var(--text-color);
}
#ap-gate-login .ap-gate-label{
    display:block;font-size:12px;font-weight:600;opacity:.7;margin:0 0 6px;
}
#ap-gate-password{
    width:100%;box-sizing:border-box;margin:0 0 14px;padding:12px 14px;
    border-radius:12px;border:1px solid var(--border-color);
    background:color-mix(in srgb,var(--bg-color) 88%,transparent);
    color:var(--text-color);font-size:14px;outline:none;
    transition:border-color .2s, box-shadow .2s;
}
#ap-gate-password:focus{
    border-color:color-mix(in srgb,var(--primary-color) 70%,var(--border-color));
    box-shadow:0 0 0 3px color-mix(in srgb,var(--primary-color) 22%,transparent);
}
#ap-gate-submit{
    width:100%;padding:12px 14px;border:none;border-radius:12px;
    background:linear-gradient(180deg, color-mix(in srgb,var(--primary-color) 88%,white), var(--primary-color));
    color:#fff;font-weight:700;font-size:14px;cursor:pointer;
    box-shadow:0 10px 24px color-mix(in srgb,var(--primary-color) 35%,transparent);
    transition:transform .15s ease, filter .15s ease, opacity .15s;
}
#ap-gate-submit:hover{filter:brightness(1.05);transform:translateY(-1px)}
#ap-gate-submit:active{transform:none}
#ap-gate-submit:disabled{opacity:.55;cursor:wait;transform:none}
#ap-gate-error{
    display:none;margin-top:12px;padding:10px 12px;border-radius:10px;
    font-size:13px;line-height:1.35;
    background:color-mix(in srgb,#ef4444 14%,transparent);
    border:1px solid color-mix(in srgb,#ef4444 35%,transparent);
    color:#fca5a5;
}
body.light-theme #ap-gate-error, html:not(.dark) #ap-gate-error{color:#b91c1c}
#ap-gate-cancel{
    display:block;width:100%;margin-top:12px;padding:10px;
    background:none;border:none;color:var(--text-color);opacity:.65;
    cursor:pointer;font-size:13px;font-weight:600;
}
#ap-gate-cancel:hover{opacity:1;color:var(--primary-color)}
#ap-gate-login .ap-gate-meta{
    margin-top:14px;padding-top:12px;border-top:1px solid color-mix(in srgb,var(--border-color) 80%,transparent);
    font-size:11px;opacity:.55;line-height:1.4;
}

/* Top Bar */
#ap-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg2);
    border-bottom: 1px solid var(--border-color);
    padding: 0 16px;
    height: 60px;
    flex-shrink: 0;
    box-sizing: border-box;
}
#ap-topbar .ap-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 1px;
    color: var(--text-color);
    text-transform: uppercase;
}
#ap-topbar .ap-brand svg { color: var(--primary-color); }
#ap-topbar .ap-user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
#ap-topbar .ap-user-info img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid var(--border-color);
    object-fit: cover;
}
#ap-topbar .ap-user-name { font-size: 13px; color: var(--text-color); opacity: .7; }
#ap-topbar .ap-user-role {
    font-size: 11px;
    background: color-mix(in srgb, var(--primary-color) 15%, transparent);
    color: var(--primary-color);
    border: 1px solid color-mix(in srgb, var(--primary-color) 40%, transparent);
    border-radius: 12px;
    padding: 2px 10px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
/* Close button — matches .std-topbar-close */
.ap-close-btn-style,
    #ap-close-btn {
        width:40px;height:40px;display:flex;align-items:center;justify-content:center;background:none;border:1.5px solid var(--border-color);border-radius:50%;color:var(--text-secondary);font-size:24px;cursor:pointer;transition:background 0.15s,color 0.15s;flex-shrink:0;padding:0;line-height:1;text-align:center;box-sizing:border-box;margin-left:12px;}
.ap-close-btn-style:hover,
#ap-close-btn:hover {
    background: var(--danger, #ef4444);
    color: #fff;
    border-color: var(--danger, #ef4444);
}

/* Body */
#ap-body { display: flex; flex: 1; min-height: 0; overflow: hidden; }
.ap-app-access-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:8px;max-height:min(62vh,520px);overflow-y:auto;padding:4px 2px;}
.ap-app-access-tile{display:flex;flex-direction:column;align-items:center;gap:4px;padding:8px 6px;border:1px solid var(--border-color);border-radius:8px;background:var(--group-block-bg);text-align:center;}
.ap-app-access-icon{width:40px;height:40px;object-fit:contain;border-radius:6px;}
.ap-app-access-icon-svg{display:block;width:40px;height:40px;background:radial-gradient(circle at var(--g-hx,34%) var(--g-hy,30%),color-mix(in srgb,var(--primary-color) 22%,white) 0%,transparent 62%),linear-gradient(145deg,color-mix(in srgb,var(--primary-color) 38%,white) 0%,color-mix(in srgb,var(--primary-color) 78%,black) 100%);-webkit-mask-image:var(--icon-src);mask-image:var(--icon-src);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain;}
.ap-app-access-title{font-size:10px;line-height:1.2;font-weight:600;max-width:100%;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;}
.ap-app-access-level{width:100%;font-size:10px;padding:2px 4px;border-radius:4px;border:1px solid var(--border-color);background:var(--bg-color);color:var(--text-color);}
.ap-world-map{position:relative;height:360px;border:1px solid var(--border-color);border-radius:8px;background:color-mix(in srgb,var(--group-block-bg) 86%,var(--primary-color) 14%);overflow:hidden;margin-bottom:10px;touch-action:none;overscroll-behavior:contain;}
.ap-world-map canvas,.ap-world-map svg{display:block;}
.ap-toggle-row{display:flex;align-items:center;gap:8px;font-size:12px;margin:8px 0;}

/* Left Sidebar */
#ap-sidebar {
    width: 60px;
    background: var(--sidebar-bg);
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 0;
    gap: 4px;
    flex-shrink: 0;
    overflow: visible;
}
.ap-nav-btn {
    width: 44px;
    height: 44px;
    border: none;
    background: transparent;
    color: var(--text-color);
    opacity: .6;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all .2s;
    flex-shrink: 0;
}
.ap-nav-btn:hover { background: var(--group-block-bg); opacity: 1; }
.ap-nav-btn:focus-visible { outline: 2px solid var(--primary-color); outline-offset: 2px; }
.ap-nav-btn.active {
    background: color-mix(in srgb, var(--primary-color) 18%, transparent);
    color: var(--primary-color);
    opacity: 1;
    border: 1px solid color-mix(in srgb, var(--primary-color) 35%, transparent);
}
.ap-nav-btn .ap-tooltip {
    position: fixed;
    left: 68px;
    top: 0;
    background: var(--modal-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    pointer-events: none;
    display: none;
    z-index: 99999;
    font-family: sans-serif;
    box-shadow: 0 4px 12px rgba(0,0,0,.2);
}
.ap-nav-btn:hover .ap-tooltip { display: none; }
.ap-sidebar-divider {
    width: 32px;
    height: 1px;
    background: var(--border-color);
    margin: 2px 0;
    flex-shrink: 0;
}

/* Content Area */
#ap-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 14px 18px;
    background: var(--bg-color);
    min-width: 0;
    min-height: 0;
}
#ap-content:has(#ap-docker-manager.active),
#ap-content:has(#ap-backup.active),
#ap-content:has(#ap-docs.active),
#ap-content:has(#ap-db.active),
#ap-content:has(#ap-performance.active),
#ap-content:has(#ap-omniroute.active) {
    padding: 0;
}
#ap-content:has(#ap-ssh.active) {
    padding: 4px 8px !important;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}
#ap-content:has(#ap-backup.active),
#ap-content:has(#ap-omniroute.active) {
    overflow: hidden;
}
#ap-content::-webkit-scrollbar { width: 6px; }
#ap-content::-webkit-scrollbar-track { background: transparent; }
#ap-content::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 3px; }

.ap-panel { display: none; position: relative; }
.ap-panel.active { display: block; }
#ap-docker-manager.active,
#ap-backup.active,
#ap-docs.active,
#ap-db.active,
#ap-performance.active,
#ap-omniroute.active,
#ap-ssh.active { display: flex; position: relative; flex: 1; min-height: 0; height: 100%; width: 100%; }
#ap-ssh.ap-panel.active {
    flex: 1;
    flex-direction: column;
    min-height: 0;
    height: 100%;
    width: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
}
.ap-ssh-layout {
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr);
    flex: 1;
    min-height: 0;
    height: 100%;
    width: 100%;
    overflow: hidden;
    gap: 6px;
    box-sizing: border-box;
}
.ap-ssh-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-shrink: 0;
    padding: 0 0 4px;
    border-bottom: 1px solid var(--border-color);
}
#ap-ssh .ap-ssh-topbar .ap-breadcrumb { display: none; }
#ap-ssh .ap-ssh-topbar .ap-page-title { margin: 0; font-size: 14px; line-height: 1.2; }
#ap-ssh-quick-commands-bar {
    display: flex;
    gap: 6px;
    padding: 5px 8px;
    background: var(--group-block-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    flex-wrap: nowrap;
    align-items: center;
    flex-shrink: 0;
    overflow-x: auto;
    overflow-y: hidden;
}
@font-face {
    font-family: "Symbols Nerd Font Mono";
    src: url("https://cdn.jsdelivr.net/gh/ryanoasis/nerd-fonts@v3.3.0/patched-fonts/NerdFontsSymbolsOnly/SymbolsNerdFontMono-Regular.ttf") format("truetype");
    font-style: normal;
    font-weight: 400;
    font-display: swap;
}
.ap-ssh-terminal-shell {
    min-height: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
    background: var(--bg-color);
    box-sizing: border-box;
}
#ap-ssh-terminal {
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    box-sizing: border-box;
    padding: 0 0 1px;
}
#ap-ssh-terminal .xterm {
    font-family: "DejaVu Sans Mono", "Liberation Mono", Consolas, "Courier New", "Symbols Nerd Font Mono", "Symbols Nerd Font", "MesloLGS NF", "Hack Nerd Font", "JetBrainsMono Nerd Font", "FiraCode Nerd Font", "CaskaydiaCove Nerd Font", monospace;
}
#ap-ssh-terminal .xterm-viewport {
    overflow-y: auto !important;
}




/* Typography */
.ap-page-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-color);
    margin-bottom: 4px;
    font-family: sans-serif;
}
.ap-page-subtitle {
    font-size: 13px;
    color: var(--text-color);
    opacity: .5;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 14px;
    font-family: sans-serif;
}
.ap-breadcrumb {
    font-size: 12px;
    color: var(--text-color);
    opacity: .4;
    margin-bottom: 14px;
    letter-spacing: 0.5px;
    font-family: sans-serif;
}
.ap-breadcrumb span { color: var(--primary-color); opacity: 1; }

/* Card */
.ap-card {
    background: var(--modal-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px 24px;
    margin-bottom: 20px;
}
.ap-card-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-color);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: sans-serif;
    text-transform: uppercase;
    letter-spacing: .5px;
    opacity: .85;
}
.ap-card-title svg { color: var(--primary-color); }
.ap-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
}
.ap-stat-tile {
    background: var(--group-block-bg);
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    text-align: center;
}
.ap-stat-tile .ap-stat-label {
    font-size: 11px;
    opacity: .7;
}
.ap-stat-tile .ap-stat-value {
    font-size: 22px;
    font-weight: 700;
    margin-top: 2px;
}

@media (max-width: 768px) {
    .ap-stat-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
    }
    .ap-stat-tile {
        padding: 8px 4px;
    }
    .ap-stat-tile .ap-stat-value {
        font-size: 17px;
    }
    .ap-stat-tile .ap-stat-label {
        font-size: 10px;
    }
}
#ap-stats-range-controls { flex-wrap: wrap; }
#ap-stats-range-controls > * { white-space: nowrap; flex-shrink: 0; }
#ap-stats-range-controls input[type="date"] { flex-shrink: 1; }
@media (min-width: 1200px) {
    #ap-stats-range-controls { flex-wrap: nowrap; }
}

/* Settings tabs */
.ap-settings-tabs {
    display: flex;
    align-items: stretch;
    gap: 0;
    width: 100%;
    max-width: calc(100% - 40px);
    margin: 18px auto 20px;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow-x: auto;
    overflow-y: hidden;
    flex-wrap: nowrap;
    scrollbar-width: thin;
}
.ap-settings-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    flex: 1 1 0;
    min-width: 72px;
    max-width: 168px;
    padding: 9px 12px;
    border: 0;
    border-right: 1px solid var(--border-color);
    border-radius: 0;
    background: transparent;
    color: var(--text-color) !important;
    font-size: 12px;
    font-weight: 600;
    font-family: sans-serif;
    white-space: nowrap;
    cursor: pointer;
    opacity: .72;
}
.ap-settings-tab span {
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
}
.ap-settings-tab:last-child { border-right: 0; }
.ap-settings-tab:hover {
    background: color-mix(in srgb, var(--primary-color) 9%, transparent);
    opacity: 1;
}
.ap-settings-tab.active {
    background: color-mix(in srgb, var(--primary-color) 16%, var(--modal-bg));
    color: var(--primary-color) !important;
    opacity: 1;
    box-shadow: inset 0 -2px 0 var(--primary-color);
}
.ap-settings-tab svg {
    flex: 0 0 auto;
}
.ap-settings-card {
    display: none;
    width: 100%;
    max-width: 720px;
    margin-left: auto;
    margin-right: auto;
    margin-bottom: 0;
    text-align: left;
}
.ap-settings-card.active {
    display: block;
}
#ap-settings {
    padding-bottom: 0;
}

/* Fields */
.ap-field { margin-bottom: 14px; }
.ap-field label {
    display: block;
    font-size: 11px;
    color: var(--text-color);
    opacity: .55;
    margin-bottom: 5px;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    font-family: sans-serif;
}
.ap-field input[type="text"],
.ap-field input[type="password"],
.ap-field input[type="number"],
.ap-field input[type="email"],
.ap-field textarea {
    width: 100%;
    max-width: 520px;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 12px;
    color: var(--text-color);
    font-size: 13px;
    font-family: 'Courier New', monospace;
    box-sizing: border-box;
    transition: border-color .2s;
}
#ap-settings .ap-field {
    width: 100%;
    max-width: 520px;
}
#ap-settings .ap-field input[type="text"],
#ap-settings .ap-field input[type="password"],
#ap-settings .ap-field input[type="number"],
#ap-settings .ap-field input[type="email"],
#ap-settings .ap-field textarea,
#ap-settings .ap-pwd-wrap,
#ap-settings .ap-recipients-box {
    width: 100%;
    max-width: 520px !important;
}
.ap-field input:focus, .ap-field textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent);
}
.ap-copy-email{
    display:inline-flex;
    align-items:center;
    max-width:100%;
    font-family:'Courier New',monospace;
    font-size:11px;
    opacity:.7;
    white-space:nowrap;
    cursor:pointer;
    border-radius:6px;
    padding:2px 4px;
    transition:background .18s ease, opacity .18s ease, color .18s ease;
}
.ap-copy-email:hover,
.ap-copy-email:focus-visible{
    opacity:1;
    background:color-mix(in srgb,var(--primary-color) 12%,transparent);
    color:var(--primary-color);
    outline:none;
}
.ap-hint {
    font-size: 12px;
    color: var(--text-color);
    opacity: .4;
    margin-top: 4px;
    font-family: sans-serif;
}
/* Password field wrapper */
.ap-pwd-wrap {
    position: relative;
    max-width: 520px;
}
.ap-pwd-wrap input { padding-right: 52px; max-width: 100%; }
.ap-pwd-eye {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    width: 34px;
    height: 28px;
    background: var(--modal-bg);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    cursor: pointer;
    color: var(--text-color) !important;
    opacity: .88;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    box-shadow: 0 0 0 2px var(--bg-color);
}
.ap-pwd-eye:hover {
    opacity: 1;
    color: var(--primary-color) !important;
    border-color: var(--primary-color);
}

/* Buttons */
.ap-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 6px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    font-family: sans-serif;
}
.ap-btn-primary { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
.ap-btn-primary:hover { opacity: .85; }
.ap-btn-secondary { background: transparent; color: var(--primary-color); border-color: var(--border-color); }
.ap-btn-secondary:hover { background: var(--group-block-bg); border-color: var(--primary-color); }
.ap-btn-danger { background: transparent; color: #f85149; border-color: var(--border-color); }
.ap-btn-danger:hover { background: color-mix(in srgb,#f85149 12%,transparent); border-color: #f85149; }
.ap-btn-success { background: transparent; color: #3fb950; border-color: var(--border-color); }
.ap-btn-success:hover { background: color-mix(in srgb,#3fb950 12%,transparent); border-color: #3fb950; }
.ap-btn-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }

/* Status messages */
.ap-status {
    margin-top: 12px;
    padding: 10px 14px;
    border-radius: 6px;
    font-size: 13px;
    display: none;
    font-family: sans-serif;
}
.ap-status.success { background: color-mix(in srgb,#3fb950 12%,transparent); border: 1px solid #3fb950; color: #3fb950; display: block; }
.ap-status.error   { background: color-mix(in srgb,#f85149 12%,transparent); border: 1px solid #f85149; color: #f85149; display: block; }
.ap-status.info    { background: color-mix(in srgb,var(--primary-color) 12%,transparent); border: 1px solid var(--primary-color); color: var(--primary-color); display: block; }

/* Users Table */
.ap-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ap-table th {
    background: var(--group-block-bg);
    color: var(--text-color);
    opacity: 1;
    text-align: left;
    padding: 9px 12px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border-color);
    font-family: sans-serif;
    white-space: nowrap;
}
.ap-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-color);
    vertical-align: middle;
    font-family: sans-serif;
}
.ap-table tr:hover td { background: var(--group-block-bg); }
.ap-users-table-scroll {
    overflow-x: scroll;
    overflow-y: visible;
    max-width: 100%;
    margin-bottom: 0;
    scrollbar-width: thin;
    scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
}
.ap-users-table-scroll::-webkit-scrollbar { height: 10px; }
.ap-users-table-scroll::-webkit-scrollbar-track { background: var(--scrollbar-track); border-radius: 5px; }
.ap-users-table-scroll::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 5px; }
.ap-users-autosave-hint { margin-top: 12px; padding-top: 2px; }
.ap-user-actions-menu { position: relative; display: inline-flex; }
.ap-user-menu-btn {
    width: 32px; height: 32px; border: 1px solid var(--border-color); border-radius: 6px;
    background: var(--bg-color); color: var(--text-color); cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; padding: 0;
}
.ap-user-menu-btn:hover { background: var(--group-block-bg); border-color: var(--primary-color); }
.ap-user-menu-dropdown {
    display: none; position: absolute; right: 0; top: calc(100% + 4px); min-width: 190px;
    background: var(--modal-bg); border: 1px solid var(--border-color); border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,.18); z-index: 200; padding: 4px 0;
}
.ap-user-actions-menu.open .ap-user-menu-dropdown { display: block; }
.ap-user-menu-item {
    display: block; width: 100%; text-align: left; padding: 8px 14px; border: none; background: none;
    color: var(--text-color); font-size: 13px; cursor: pointer; font-family: sans-serif;
}
.ap-user-menu-item:hover { background: var(--group-block-bg); }
.ap-user-menu-item-danger { color: #f85149; }
.ap-verified-yes { color: #3fb950; font-size: 12px; }
.ap-verified-no  { color: #f85149; font-size: 12px; }
.ap-role-select {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--text-color);
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 12px;
    cursor: pointer;
    font-family: sans-serif;
}
.ap-role-select:focus { outline: none; border-color: var(--primary-color); }
.ap-role-select:disabled { opacity: .45; cursor: not-allowed; }
.ap-search-bar {
    width: 100%;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 12px;
    color: var(--text-color);
    font-size: 14px;
    font-family: sans-serif;
    box-sizing: border-box;
    margin-bottom: 14px;
    transition: border-color .2s;
}
.ap-search-bar:focus { outline: none; border-color: var(--primary-color); }

/* Recipients box */
.ap-recipients-box {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 10px 14px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    color: var(--text-color);
    opacity: .8;
    margin-top: 6px;
    line-height: 2;
}

/* Folder entries — constrained layout */
.ap-folder-entry {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    align-items: stretch;
    flex-wrap: nowrap;
    min-width: 0;
}
.ap-folder-entry select {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-color);
    padding: 7px 8px;
    font-size: 13px;
    width: 140px;
    flex-shrink: 0;
    min-width: 0;
    font-family: sans-serif;
}
.ap-folder-entry input[type="text"] {
    flex: 1;
    min-width: 0;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 7px 10px;
    color: var(--text-color);
    font-size: 13px;
    font-family: 'Courier New', monospace;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ap-folder-entry input:focus, .ap-folder-entry select:focus { outline: none; border-color: var(--primary-color); }
.ap-remove-row-btn {
    background: none;
    border: 1px solid var(--border-color);
    color: #f85149;
    border-radius: 4px;
    width: 28px;
    height: 28px;
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
    flex-shrink: 0;
    align-self: center;
    transition: all .2s;
    padding: 0;
}
.ap-remove-row-btn:hover { background: color-mix(in srgb,#f85149 15%,transparent); border-color: #f85149; }

/* JSON Files Panel */
.ap-json-table { width: 100%; border-collapse: collapse; font-size: 13px; font-family: sans-serif; }
.ap-json-table th {
    background: var(--group-block-bg);
    color: var(--text-color);
    text-align: left;
    padding: 8px 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-bottom: 1px solid var(--border-color);
}
.ap-json-table td {
    padding: 9px 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-color);
    vertical-align: middle;
}
.ap-json-table tr:hover td { background: var(--group-block-bg); cursor: pointer; }
.ap-json-table tr.ap-json-active td { background: color-mix(in srgb,var(--primary-color) 12%,transparent); }
.ap-json-fname { font-family: 'Courier New', monospace; font-size: 13px; color: var(--primary-color); }

/* Monaco editor container */
#ap-monaco-container {
    display: none;
    flex-direction: column;
    gap: 0;
    margin-top: 16px;
}
#ap-monaco-container.active { display: flex; }
#ap-monaco-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--group-block-bg);
    border: 1px solid var(--border-color);
    border-bottom: none;
    border-radius: 6px 6px 0 0;
    padding: 8px 14px;
    flex-wrap: wrap;
    gap: 8px;
}
#ap-monaco-filename {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    color: var(--primary-color);
    font-weight: 700;
}
#ap-monaco-mode-btns { display: flex; gap: 6px; }
.ap-mode-btn {
    padding: 4px 12px;
    border-radius: 4px;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--text-color);
    font-size: 12px;
    cursor: pointer;
    font-family: sans-serif;
    transition: all .2s;
    opacity: .7;
}
.ap-mode-btn.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); opacity: 1; }
.ap-mode-btn:hover { opacity: 1; }
#ap-monaco-editor-wrap {
    border: 1px solid var(--border-color);
    border-radius: 0 0 6px 6px;
    height: 420px;
    overflow: hidden;
}
#ap-json-status { margin-top: 8px; }

/* Stats in admin panel */
#ap-stats-hot-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--border-color);
    border-radius: 6px;
}
#ap-stats-hot-table { height: calc(100vh - 320px); min-height: 400px; width: 100%; }
#ap-stats-analysis,
#ap-stats-analysis * {
    box-sizing: border-box;
}
.ap-analysis-grid {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.ap-analysis-box {
    min-width: 0;
    max-width: 100%;
    background: var(--group-block-bg);
    border-radius: 8px;
    padding: 14px;
    border: 1px solid var(--border-color);
}
@media (max-width: 768px) {
    #ap-stats-analysis {
        padding: 14px !important;
        overflow: hidden;
    }
    .ap-analysis-grid {
        grid-template-columns: minmax(0,1fr);
    }
    #ap-pie-canvas {
        max-width: 100%;
    }
}

/* Handsontable light theme (default) */
#ap-stats-hot-table .handsontable th,
#ap-stats-hot-table .handsontable td,
#ap-stats-hot-table .handsontable [role="rowgroup"] {
    background-color: var(--bg-color) !important;
    color: var(--text-color) !important;
    border-color: var(--border-color) !important;
}
#ap-stats-hot-table .handsontable th {
    background-color: var(--sidebar-bg) !important;
    font-weight: 600;
}
#ap-stats-hot-table .handsontable tr:hover td {
    background-color: var(--group-block-bg) !important;
}
#ap-stats-hot-table .handsontable .htCore thead,
#ap-stats-hot-table .handsontable .htCore thead tr,
#ap-stats-hot-table .handsontable .htCore thead tr:first-child th,
#ap-stats-hot-table .handsontable.ht_clone_top,
#ap-stats-hot-table .handsontable.ht_clone_top .wtHolder,
#ap-stats-hot-table .handsontable.ht_clone_top .wtHider,
#ap-stats-hot-table .handsontable.ht_clone_top .wtSpreader,
#ap-stats-hot-table .handsontable.ht_clone_top_left_corner,
#ap-stats-hot-table .handsontable.ht_clone_top_left_corner .wtHolder,
#ap-stats-hot-table .handsontable.ht_clone_top_left_corner .wtHider,
#ap-stats-hot-table .handsontable.ht_clone_top_left_corner .wtSpreader,
#ap-stats-hot-table .handsontable .ht_clone_top,
#ap-stats-hot-table .handsontable .ht_clone_top .wtHolder,
#ap-stats-hot-table .handsontable .ht_clone_top .wtHider,
#ap-stats-hot-table .handsontable .ht_clone_top .wtSpreader,
#ap-stats-hot-table .handsontable .ht_clone_top_left_corner,
#ap-stats-hot-table .handsontable .ht_clone_top_left_corner .wtHolder,
#ap-stats-hot-table .handsontable .ht_clone_top_left_corner .wtHider,
#ap-stats-hot-table .handsontable .ht_clone_top_left_corner .wtSpreader,
#ap-stats-hot-table .handsontable [role="rowgroup"],
#ap-stats-hot-table .handsontable [role="rowgroup"] tr,
#ap-stats-hot-table .handsontable [role="rowgroup"] th {
    background-color: var(--table-header-bg) !important;
    color: var(--text-color) !important;
}
#ap-stats-hot-table .handsontable.ht_clone_top .htCore,
#ap-stats-hot-table .handsontable.ht_clone_top_left_corner .htCore,
#ap-stats-hot-table .handsontable .ht_clone_top .htCore,
#ap-stats-hot-table .handsontable .ht_clone_top_left_corner .htCore {
    background-color: var(--table-header-bg) !important;
}
/* Custom selection color — uses CSS variable set by JS */
#ap-stats-hot-table .handsontable td.area,
#ap-stats-hot-table .handsontable td.current {
    background-color: var(--hot-selection-bg, rgba(0,123,255,0.15)) !important;
}
#ap-stats-hot-table .handsontable .wtBorder.current,
#ap-stats-hot-table .handsontable .wtBorder.area {
    background-color: var(--primary-color) !important;
}
/* Context menu theme */
#ap-stats-hot-table .htContextMenu .htCore td {
    background-color: var(--modal-bg) !important;
    color: var(--text-color) !important;
    border-color: var(--border-color) !important;
}
#ap-stats-hot-table .htContextMenu .htCore td:hover {
    background-color: var(--group-block-bg) !important;
}
/* Range preset button active state */
.ap-chart-range-btn.active {
    background: var(--primary-color) !important;
    color: #fff !important;
    border-color: var(--primary-color) !important;
}

/* Admin button in settings */
.admin-panel-btn {
    background: color-mix(in srgb, var(--primary-color) 18%, transparent) !important;
    border: 1px solid color-mix(in srgb, var(--primary-color) 45%, transparent) !important;
    color: var(--primary-color) !important;
}
.admin-panel-btn:hover {
    background: color-mix(in srgb, var(--primary-color) 30%, transparent) !important;
    border-color: var(--primary-color) !important;
}
/* Vertical scrolling for the admin panel sidebar */
#ap-sidebar {
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    direction: rtl; /* Change direction to place the scrollbar on the left */
}

/* Compensate content - restore normal text direction */
#ap-sidebar > * {
    direction: ltr;
}

/* Scrollbar styling for WebKit browsers (Chrome, Safari, Edge) */
#ap-sidebar::-webkit-scrollbar {
    width: 2px; /* Even thinner - 2px instead of 4px */
}

/* Completely remove the background (track) */
#ap-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

/* Style only the thumb */
#ap-sidebar::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb, rgba(128, 128, 128, 0.4));
    border-radius: 1px;
}

#ap-sidebar::-webkit-scrollbar-thumb:hover {
    background: var(--scrollbar-thumb-hover, rgba(128, 128, 128, 0.7));
}

/* Hide the scrollbar by default, show only on hover */
#ap-sidebar {
    scrollbar-width: thin;
    scrollbar-color: transparent transparent;
}

#ap-sidebar:hover {
    scrollbar-color: var(--scrollbar-thumb, rgba(128, 128, 128, 0.4)) transparent;
}

#ap-sidebar::-webkit-scrollbar {
    width: 0px;
    transition: width 0.2s ease;
}

#ap-sidebar:hover::-webkit-scrollbar {
    width: 2px;
}
#ap-backup.ap-panel.active {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    width: 100%;
    margin: 0;
    padding: 0;
}
#ap-backup .ap-breadcrumb,
#ap-backup .ap-page-title,
#ap-backup .ap-page-subtitle {
    display: none;
}

/* iframe container - full available area */
#ap-backup > div {
    flex: 1;
    min-height: 0;
    width: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
    border: none;
}
#ap-backup iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    border-radius: 0;
}
#ap-content:has(#ap-backup.active) {
    padding: 0 !important;
}

/* Alternative option - directly for #ap-backup.active */
#ap-backup.active {
    margin: 0 !important;
    padding: 0 !important;
}

/* Also remove padding from the parent container */
#ap-backup.active + *,
#ap-backup.active {
    padding: 0 !important;
}

/* Remove padding from all parent elements when backup is active */
#ap-backup.active,
#ap-backup.active ~ *,
#ap-content:has(#ap-backup.active) {
    padding: 0 !important;
}

/* Documentation panel styles */
#ap-docs.ap-panel.active {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    width: 100%;
    margin: 0;
    padding: 0;
}
#ap-docs .ap-breadcrumb,
#ap-docs .ap-page-title,
#ap-docs .ap-page-subtitle {
    display: none;
}
#ap-docs > div {
    flex: 1;
    min-height: 0;
    width: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
    border: none;
}
#ap-docs iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    border-radius: 0;
}
#ap-content:has(#ap-docs.active) {
    padding: 0 !important;
}
#ap-docs.active {
    margin: 0 !important;
    padding: 0 !important;
}
#ap-docs.active + *,
#ap-docs.active {
    padding: 0 !important;
}
#ap-docs.active,
#ap-docs.active ~ *,
#ap-content:has(#ap-docs.active) {
    padding: 0 !important;
}

/* Database panel – same embedded styles */
#ap-db.ap-panel.active {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    width: 100%;
    margin: 0;
    padding: 0;
}
#ap-db .ap-breadcrumb,
#ap-db .ap-page-title,
#ap-db .ap-page-subtitle {
    display: none;
}
#ap-db > div {
    flex: 1;
    min-height: 0;
    width: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
    border: none;
}
#ap-db iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    border-radius: 0;
}
#ap-content:has(#ap-db.active) {
    padding: 0 !important;
}
#ap-db.active {
    margin: 0 !important;
    padding: 0 !important;
}
#ap-db.active + *,
#ap-db.active {
    padding: 0 !important;
}
#ap-db.active,
#ap-db.active ~ *,
#ap-content:has(#ap-db.active) {
    padding: 0 !important;
}

#ap-performance.ap-panel.active {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    width: 100%;
    margin: 0;
    padding: 0;
}
#ap-performance > div {
    flex: 1;
    min-height: 0;
    width: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
    border: none;
}
#ap-performance iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    border-radius: 0;
    background: var(--bg-color);
}
#ap-content:has(#ap-performance.active) {
    padding: 0 !important;
    overflow: hidden;
}
#ap-performance.active {
    margin: 0 !important;
    padding: 0 !important;
}

/* OmniRoute panel – same embedded styles */
#ap-omniroute.ap-panel.active {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
}
#ap-omniroute .ap-breadcrumb,
#ap-omniroute .ap-page-title,
#ap-omniroute .ap-page-subtitle {
    display: none;
}
#ap-omniroute > div {
    flex: 1;
    min-height: 0;
    width: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
    border: none;
}
#ap-omniroute iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    border-radius: 0;
    background: #0b0d10;
}
#ap-content:has(#ap-omniroute.active) {
    padding: 0 !important;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}
#ap-omniroute.active {
    margin: 0 !important;
    padding: 0 !important;
}
#ap-omniroute.active + *,
#ap-omniroute.active {
    padding: 0 !important;
}
#ap-omniroute.active,
#ap-omniroute.active ~ *,
#ap-content:has(#ap-omniroute.active) {
    padding: 0 !important;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}
</style>

<div id="admin-panel-overlay">
    <div id="ap-loading-skeleton" style="display:flex;flex-direction:column;height:100%;align-items:center;justify-content:center;gap:16px;">
        <svg id="ap-loading-spinner" width="40" height="40" viewBox="0 0 24 24" fill="currentColor" style="color:var(--primary-color);animation:_ap_spin 1s linear infinite;flex-shrink:0;"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46A7.93 7.93 0 0020 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74A7.93 7.93 0 004 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
        <span id="ap-loading-msg" style="font-size:14px;opacity:.6;font-family:sans-serif;"><?php echo my_htmlspecialchars($translations['admin_panel_loading']??'Loading Admin Panel...'); ?></span>
        <div id="ap-gate-login">
            <div class="ap-gate-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <?php echo my_htmlspecialchars($translations['admin'] ?? 'Admin'); ?>
            </div>
            <h2 class="ap-gate-title"><?php echo my_htmlspecialchars($translations['ap_gate_title'] ?? 'Unlock Admin Panel'); ?></h2>
            <p class="ap-gate-sub"><?php echo my_htmlspecialchars($translations['ap_gate_subtitle'] ?? 'Use the same password as your account. A separate admin token will be issued for this session.'); ?></p>
            <label class="ap-gate-label" for="ap-gate-password"><?php echo my_htmlspecialchars($translations['password'] ?? 'Password'); ?></label>
            <input type="password" id="ap-gate-password" autocomplete="current-password" placeholder="••••••••">
            <button type="button" id="ap-gate-submit"><?php echo my_htmlspecialchars($translations['ap_gate_unlock'] ?? 'Unlock'); ?></button>
            <div id="ap-gate-error"></div>
            <button type="button" id="ap-gate-cancel"><?php echo my_htmlspecialchars($translations['close'] ?? 'Close'); ?></button>
            <div class="ap-gate-meta"><?php echo my_htmlspecialchars($translations['ap_gate_meta'] ?? 'Separate from your main session · expires after inactivity'); ?></div>
        </div>
    </div>
    <div id="ap-dynamic-root" style="display:none;flex:1;min-height:0;overflow:hidden;"></div>
</div>
<style>@keyframes _ap_spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}</style>

<script>
// ═══════════════════ ADMIN PANEL – lazy loader ═══════════════════
<?php if($isAdmin): ?>
(function(){
'use strict';
var overlay   = document.getElementById('admin-panel-overlay');
if(!overlay) return;
var openBtn   = document.getElementById('open-admin-panel-btn');
var settingsM = document.getElementById('settings-modal');
var dynRoot   = document.getElementById('ap-dynamic-root');
var skeleton  = document.getElementById('ap-loading-skeleton');
var apLoaded  = false;
var apPanelInited = {};
var apHotInst = null;
var apStatsData = {};
var apMonacoEditor = null;
var apCurrentFile  = null;
var apEditMode     = false;
var apJsonAllFiles = [];
var apJsonSortAsc  = true;
var monacoInjected = false;
// Shared loader is defined in project/views/partials/monaco_loader.php

var panelHash={'ap-users':'usermanagement','ap-email':'emailsetting','ap-apps-access':'appaccess','ap-telegram':'telegram','ap-stats':'stats','ap-ssh':'sshterminal','ap-jsonfiles':'jsonfiles','ap-backup':'backup','ap-docs':'documentation','ap-db':'database','ap-omniroute':'omniroute','ap-performance':'performance','ap-docker':'docker','ap-docker-manager':'dockermanager'};
var hashPanel={'usermanagement':'ap-users','emailsetting':'ap-email','appaccess':'ap-apps-access','telegram':'ap-telegram','stats':'ap-stats','sshterminal':'ap-ssh','jsonfiles':'ap-jsonfiles','backup':'ap-backup','documentation':'ap-docs','database':'ap-db','omniroute':'ap-omniroute','performance':'ap-performance','docker':'ap-docker','dockermanager':'ap-docker-manager','security':'ap-security'};
var OMNIROUTE_URL='/omniroute-bridge?next=/admin-omniroute/home';
function currentResolvedTheme(){return typeof resolveTheme==='function'?resolveTheme(getSelectedThemeValue()):<?php echo json_encode($themeForAppLink); ?>;}
function injectOmniRouteTheme(){
    var frame=document.getElementById('ap-omniroute-frame');
    if(!frame) return;
    try{
        var doc=frame.contentDocument||frame.contentWindow.document;
        if(!doc) return;
        var style=doc.getElementById('ap-omniroute-theme-style');
        if(!style){style=doc.createElement('style');style.id='ap-omniroute-theme-style';(doc.head||doc.documentElement).appendChild(style);}
        if(currentResolvedTheme()==='light'){
            style.textContent='html{color-scheme:light!important}body,[class*="sidebar" i],[class*="side-bar" i],[class*="sidenav" i],aside,nav{background:#f8fafc!important;color:#111827!important;border-color:#dbe3ed!important}[class*="sidebar" i] *,[class*="side-bar" i] *,[class*="sidenav" i] *,aside *,nav *{border-color:#dbe3ed!important}';
        }else{
            style.textContent='';
        }
    }catch(e){}
}
function loadOmniRouteIframe(){
    var frame=document.getElementById('ap-omniroute-frame');
    if(!frame) return;
    if(!frame.dataset.themeHooked){frame.dataset.themeHooked='1';frame.addEventListener('load',injectOmniRouteTheme);}
    var target=frame.getAttribute('data-src')||OMNIROUTE_URL;
    if(frame.dataset.loaded===target) return;
    frame.dataset.loaded=target;
    frame.src=target;
    injectOmniRouteTheme();
}

var T=<?php echo json_encode([
    'saving'        =>$translations['ap_saving']??'Saving...',
    'role_changed'  =>$translations['role_changed']??'Roles saved',
    'email_saved'   =>$translations['email_settings_saved']??'Email settings saved',
    'folder_saved'  =>$translations['folder_config_saved']??'Paths saved',
    'tg_saved'      =>$translations['tg_saved']??'Saved',
    'docker_saved'  =>$translations['docker_saved']??'Docker settings saved',
    'onlyoffice_saved'=>$translations['onlyoffice_saved']??'OnlyOffice token saved',
    'ai_config_saved'=>$translations['ai_config_saved']??'AI configuration saved',
    'apiKeysMatch'=>$translations['apiKeysMatch']??'API keys match',
    'apiKeysDoNotMatch'=>$translations['apiKeysDoNotMatch']??'API keys do not match',
    'apiKeysRequired'=>$translations['apiKeysRequired']??'Both API key fields are required when changing the key',
    'invalidApiKeyFormat'=>$translations['invalidApiKeyFormat']??'Invalid API key format',
    'backup_saved'  =>$translations['backup_saved']??'Backup credentials saved',
    'backup_deleted'=>$translations['backup_deleted']??'Backup credentials deleted',
    'tg_test_ok'    =>$translations['tg_test_ok']??'Test sent OK',
    'tg_test_fail'  =>$translations['tg_test_fail']??'Test failed',
    'stats_saved'   =>$translations['save_changes']??'Stats saved',
    'json_saved'    =>$translations['ap_json_saved']??'File saved',
    'json_invalid'  =>$translations['ap_json_invalid']??'Invalid JSON structure',
    'json_confirm'  =>$translations['ap_json_confirm']??'Save changes to this file?',
    'refresh_users' =>$translations['refresh_users']??'Users list refreshed',
    'folder_ph'     =>$translations['folder_config_app_placeholder']??'— Select app —',
    'error'         =>'Error',
    'fm_new_folder' =>$translations['fm_new_folder']??'New Folder',
    'fm_new_file'   =>$translations['fm_new_file']??'New File',
    'fm_rename'     =>$translations['fm_rename']??'Rename',
    'fm_delete'     =>$translations['fm_delete']??'Delete',
    'fm_saving'     =>$translations['fm_saving']??'Saving…',
    'fm_saved'      =>$translations['fm_saved']??'Saved',
    'fm_loading'    =>$translations['fm_loading']??'Loading…',
    'fm_empty'      =>$translations['fm_empty']??'Empty folder',
    'fm_binary'     =>$translations['fm_binary']??'Binary file — cannot be edited as text.',
    'fm_confirm_del'=>$translations['fm_delete_confirm']??'Are you sure you want to delete',
    'fm_confirm_save'=>$translations['fm_confirm_save']??'Save changes to this file?',
    'fm_folder_name'=>$translations['fm_folder_name']??'Folder name',
    'fm_file_name'  =>$translations['fm_file_name']??'File name',
    'fm_new_name'   =>$translations['fm_new_name']??'New name',
    'fm_view'       =>$translations['fm_view']??'View',
    'fm_edit'       =>$translations['fm_edit']??'Edit',
    'fm_no_items'   =>$translations['fm_no_items']??'No items found',
    'fm_root'       =>$translations['fm_root']??'Root directory',
    'fm_upload'     =>$translations['fm_upload']??'Upload',
    'fm_download'   =>$translations['fm_download']??'Download',
    'fm_copy_clipboard'=>$translations['fm_copy_clipboard']??'Copy to clipboard',
    'session_refreshed'  =>$translations['fm_session_refreshed']??'Sessions list updated',
    'folder_created'     =>$translations['fm_folder_created']??'Folder created',
    'file_created'       =>$translations['fm_file_created']??'File created',
    'copied'             =>$translations['fm_copied']??'Copied to clipboard',
    'renamed'            =>$translations['fm_renamed']??'Renamed',
    'fm_deleted'         =>$translations['fm_deleted']??'Deleted',
    'network_error'      =>$translations['fm_network_error']??'Network error',
    'error_deleting'     =>$translations['fm_error_deleting']??'Error deleting files',
    'error_del_user'     =>$translations['fm_error_deleting_user']??'Error deleting user',
    'password_reset_admin_success'=>$translations['password_reset_admin_success']??'Password reset successfully',
    'copy_password'      =>$translations['copy_password']??'Copy password',
    'password_copied'    =>$translations['password_copied']??'Password copied to clipboard',
    'ssh_saved'          =>$translations['ssh_profile_saved']??'SSH profile saved',
    'ssh_connect_first'  =>$translations['ssh_save_first']??'Save SSH profile first',
    'ssh_qc_edit'        =>$translations['ssh_qc_edit']??'Edit',
    'ssh_qc_delete'      =>$translations['ssh_qc_delete']??'Delete',
    'ssh_qc_edit_title'  =>$translations['ssh_qc_edit_title']??'Edit Command',
    'ssh_qc_update'      =>$translations['ssh_qc_update']??'Update Command',
    'ssh_qc_add_title'   =>$translations['ssh_qc_add_title']??'Add New Command',
    'ssh_qc_save'        =>$translations['ssh_qc_save']??'Save Command',
    'ssh_running'        =>$translations['ssh_running_command']??'Running command...',
    'ssh_ready'          =>$translations['ssh_terminal_ready']??'Terminal ready',
    'ssh_loading_profile'=>$translations['ssh_loading_profile']??'Loading saved SSH profile...',
    'ssh_profile_loaded' =>$translations['ssh_profile_loaded']??'Profile loaded',
    'ssh_no_saved_profile'=>$translations['ssh_no_saved_profile']??'No saved SSH profile',
    'ssh_no_saved_profile_yet'=>$translations['ssh_no_saved_profile_yet']??'No saved SSH profile yet.',
    'ssh_key_ready'=>$translations['ssh_key_ready']??'SSH key found. Only key authentication is used.',
    'ssh_key_only_hint'=>$translations['ssh_key_only_hint']??'Password login is disabled. Place id_ed25519 or id_rsa in data/.ssh/',
    'ssh_saving_testing' =>$translations['ssh_saving_testing']??'Saving & testing SSH connection...',
    'ssh_connected_via'  =>$translations['ssh_connected_via']??'SSH connected via',
    'ssh_not_connected'  =>$translations['ssh_not_connected']??'SSH not connected:',
    'ssh_unknown_error'  =>$translations['ssh_unknown_error']??'unknown error',
    'ssh_test_failed'    =>$translations['ssh_test_failed']??'SSH test failed:',
    'ssh_load_failed'    =>$translations['ssh_load_failed']??'Load failed:',
    'ssh_decrypting'     =>$translations['ssh_decrypting_password']??'Decrypting password...',
    'ssh_no_saved_password_found'=>$translations['ssh_no_saved_password_found']??'No saved password found',
    'ssh_password_revealed'=>$translations['ssh_password_revealed']??'Password revealed',
    'ssh_decrypt_failed' =>$translations['ssh_decrypt_failed']??'Decrypt failed',
    'ssh_revealed'       =>$translations['ssh_revealed']??'Revealed.',
    'app_access_saved'   =>$translations['app_access_saved']??'App access saved',
    'delete_this_command'=>$translations['delete_this_command']??'Delete this command?',
    'browser_no_audio_recording'=>$translations['browser_no_audio_recording']??'Your browser does not support audio recording',
    'unknown_error'=>$translations['unknown_error']??'Unknown error',
    'failed_to_load_file'=>$translations['failed_to_load_file']??'Failed to load file:',
    'recording_release_to_send'=>$translations['recording_release_to_send']??'Recording... Release to send',
    'support_chat_msg_empty'=>$translations['support_chat_msg_empty']??'Message cannot be empty',
    'support_chat_file_too_large'=>$translations['support_chat_file_too_large']??'File size must not exceed 20MB',
    'network_error'=>$translations['network_error']??$translations['fm_network_error']??'Network error',
]); ?>;

var apFolderMap=<?php $fm=[];foreach($apps as $dn=>$dd){$fm[$dd['folder']]=$dn;}echo json_encode($fm); ?>;

/* ── helpers ── */
function esc(s){var d=document.createElement('div');d.appendChild(document.createTextNode(String(s||'')));return d.innerHTML;}
function escA(s){return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function getTheme(){var s=document.querySelector('select[name="theme"]');var v=s?s.value:'light';if(v==='auto')return window.matchMedia('(prefers-color-scheme: dark)').matches?'vs-dark':'vs';return v==='dark'?'vs-dark':'vs';}
function setStatus(el,msg,type){if(!el)return;el.className='ap-status '+type;el.textContent=msg;el.style.display='block';if(el._t)clearTimeout(el._t);el._t=setTimeout(function(){el.className='ap-status';el.style.display='none';el.textContent='';},10000);}
function setHash(id){history.replaceState(null,'','#adminpanel/'+(panelHash[id]||id));}
function clearHash(){history.replaceState(null,'',location.pathname+location.search);}
function apLoadStyleOnce(href){
    if(document.querySelector('link[href="'+href+'"]')) return;
    var l=document.createElement('link');
    l.rel='stylesheet';
    l.href=href;
    document.head.appendChild(l);
}
function apLoadScriptOnce(src,test){
    if(test&&test()) return Promise.resolve();
    if(window._apScriptPromises&&window._apScriptPromises[src]) return window._apScriptPromises[src];
    window._apScriptPromises=window._apScriptPromises||{};
    window._apScriptPromises[src]=new Promise(function(resolve,reject){
        var existing=document.querySelector('script[src="'+src+'"]');
        var s=existing||document.createElement('script');
        s.addEventListener('load',function(){s.dataset.apLoaded='1';resolve();},{once:true});
        s.addEventListener('error',function(){reject(new Error('Failed to load '+src));},{once:true});
        if(existing&&existing.dataset.apLoaded==='1') setTimeout(resolve,0);
        else if(existing&&existing.readyState==='complete') setTimeout(resolve,0);
        else if(!existing){s.src=src;s.async=false;document.head.appendChild(s);}
    }).then(function(){
        if(test&&!test()) throw new Error('Library unavailable after load '+src);
    });
    return window._apScriptPromises[src];
}
function apEnsureStatsDeps(){
    if(window.Handsontable&&window.Chart) return Promise.resolve();
    if(window._apStatsDepsPromise) return window._apStatsDepsPromise;
    apLoadStyleOnce('https://cdn.jsdelivr.net/npm/handsontable@14.1.0/dist/handsontable.full.min.css');
    if(typeof require==='function'){
        window._apStatsDepsPromise=new Promise(function(resolve,reject){
            try{
                require.config({paths:{
                    Handsontable:'https://cdn.jsdelivr.net/npm/handsontable@14.1.0/dist/handsontable.full.min',
                    chartjs:'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min'
                }});
                require(['Handsontable','chartjs'],function(hot,chart){
                    window.Handsontable=window.Handsontable||(hot&&hot.default?hot.default:hot);
                    window.Chart=window.Chart||(chart&&chart.Chart?chart.Chart:(chart&&chart.default?chart.default:chart));
                    if(!window.Handsontable||!window.Chart) reject(new Error('Statistics libraries unavailable after load'));
                    else resolve();
                },reject);
            }catch(e){reject(e);}
        });
        return window._apStatsDepsPromise;
    }
    window._apStatsDepsPromise=Promise.all([
        apLoadScriptOnce('https://cdn.jsdelivr.net/npm/handsontable@14.1.0/dist/handsontable.full.min.js'),
        apLoadScriptOnce('https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js')
    ]).then(function(){
        if(!window.Handsontable||!window.Chart) throw new Error('Statistics libraries unavailable after load');
    });
    return window._apStatsDepsPromise;
}

/* ── Monaco lazy loader ── */
function ensureMonaco(cb){
    window.apLoadMonaco().then(cb).catch(function(e){console.error('Failed to load Monaco:',e);});
}

/* ── Panel switch ── */
function openPanel(id){
    if(!dynRoot)return;
    overlay.classList.toggle('ap-edge-omniroute',id==='ap-omniroute');
    dynRoot.querySelectorAll('.ap-panel').forEach(function(p){p.classList.remove('active');});
    dynRoot.querySelectorAll('.ap-nav-btn').forEach(function(b){b.classList.remove('active');});
    var p=dynRoot.querySelector('#'+id); if(p) p.classList.add('active');
    var nb=dynRoot.querySelector('[data-panel="'+id+'"]'); if(nb) nb.classList.add('active');
    setHash(id);
    if(id==='ap-stats'&&!apPanelInited['ap-stats']){apPanelInited['ap-stats']=true;requestAnimationFrame(function(){requestAnimationFrame(function(){apEnsureStatsDeps().then(function(){apPopulateYearSelector();initApStats();initApChartRangePicker();renderApStatsChart();}).catch(function(e){var st=dynRoot&&dynRoot.querySelector('#ap-stats-status');setStatus(st,e.message||T.error,'error');console.error('apStats init error:',e);});});});}
    else if(id==='ap-stats'&&apHotInst){requestAnimationFrame(function(){requestAnimationFrame(function(){apHotInst.render();initApChartRangePicker();renderApStatsChart();});});}
    if(id==='ap-jsonfiles'){ensureMonaco(function(){});if(!apPanelInited['ap-jsonfiles']){apPanelInited['ap-jsonfiles']=true;if(typeof window._apJsonLoadList==='function'){window._apJsonLoadList();}else{setTimeout(function(){if(typeof window._apJsonLoadList==='function')window._apJsonLoadList();},300);}}else{if(typeof window._apJsonLoadList==='function') window._apJsonLoadList();}}
    if(id==='ap-ssh'){
        requestAnimationFrame(function(){
            requestAnimationFrame(function(){
                var te = dynRoot && dynRoot.querySelector('#ap-ssh-terminal');
                if(te && typeof te._apSshFit === 'function') te._apSshFit();
                setTimeout(function(){
                    if(te && typeof te._apSshFit === 'function') te._apSshFit();
                }, 200);
            });
        });
    }
    if(id==='ap-omniroute') loadOmniRouteIframe();
}

/* ── Open/close ── */
function apCsrfToken(){
    var meta=document.querySelector('meta[name="csrf-token"]');
    var fromMeta=meta&&meta.getAttribute('content');
    if(fromMeta) return fromMeta;
    if(window.__AP_CSRF__) return String(window.__AP_CSRF__);
    return '';
}
function apRememberCsrf(token){
    if(!token) return;
    if(typeof window.__AP_REMEMBER_CSRF__==='function'){ window.__AP_REMEMBER_CSRF__(token); return; }
    window.__AP_CSRF__=String(token);
    var meta=document.querySelector('meta[name="csrf-token"]');
    if(meta) meta.setAttribute('content', String(token));
}
function apRefreshCsrf(){
    return fetch('/csrf',{method:'GET',credentials:'same-origin',cache:'no-store'})
        .then(function(r){ return r.json().catch(function(){ return null; }); })
        .then(function(d){ if(d&&d.csrf) apRememberCsrf(d.csrf); return d; })
        .catch(function(){ return null; });
}
function apAdminFd(fields){
    var fd=new FormData();
    Object.keys(fields||{}).forEach(function(k){ fd.append(k, fields[k]); });
    var t=apCsrfToken();
    if(t){ fd.append('_csrf', t); }
    return fd;
}
function apAdminFetch(fields, retried){
    var t=apCsrfToken();
    var headers={};
    if(t){ headers['X-CSRF-TOKEN']=t; }
    return fetch('/main',{
        method:'POST',
        body:apAdminFd(fields),
        credentials:'same-origin',
        headers:headers
    }).then(function(r){
        return r.json().then(function(d){
            if(d&&d.csrf) apRememberCsrf(d.csrf);
            if(d&&d.error==='csrf_forbidden'&&!retried){
                return apRefreshCsrf().then(function(){ return apAdminFetch(fields, true); });
            }
            return d;
        }).catch(function(){
            return {success:false,error:'invalid_response',status:r.status};
        });
    });
}
function apGateShow(msg){
    apLoaded=false;
    if(dynRoot) dynRoot.style.display='none';
    if(skeleton) skeleton.style.display='flex';
    var spin=document.getElementById('ap-loading-spinner'); if(spin) spin.style.display='none';
    var msgEl=document.getElementById('ap-loading-msg'); if(msgEl) msgEl.style.display='none';
    var gate=document.getElementById('ap-gate-login'); if(gate) gate.style.display='block';
    var err=document.getElementById('ap-gate-error');
    if(err){ err.style.display=msg?'block':'none'; err.textContent=msg||''; }
    var pwd=document.getElementById('ap-gate-password'); if(pwd){ pwd.value=''; setTimeout(function(){pwd.focus();},50); }
}
function apGateHide(){
    var gate=document.getElementById('ap-gate-login'); if(gate) gate.style.display='none';
    var spin=document.getElementById('ap-loading-spinner'); if(spin) spin.style.display='';
    var msgEl=document.getElementById('ap-loading-msg'); if(msgEl){ msgEl.style.display=''; msgEl.textContent=<?php echo json_encode($translations['admin_panel_loading']??'Loading Admin Panel...'); ?>; }
}
function apGateStatus(){
    return apAdminFetch({admin_panel_status:'1',ajax:'1'});
}
function apGateLogin(password){
    return apAdminFetch({admin_panel_login:'1',ajax:'1',password:password||''});
}
function openAdminPanel(target){
    if(typeof window.hideSettingsTooltips==='function') window.hideSettingsTooltips();
    if(settingsM) settingsM.style.display='none';
    overlay.classList.add('active');
    document.body.style.overflow='hidden';
    window._apPendingPanel = target||'ap-users';
    skeleton.style.display='flex'; dynRoot.style.display='none';
    apGateHide();
    apRefreshCsrf().then(function(){ return apGateStatus(); }).then(function(d){
        if(d&&d.csrf) apRememberCsrf(d.csrf);
        if(d&&d.authenticated){
            if(apLoaded){
                if(skeleton) skeleton.style.display='none';
                if(dynRoot) dynRoot.style.cssText='display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;';
                openPanel(window._apPendingPanel);
                return;
            }
            loadPanel(window._apPendingPanel); return;
        }
        apLoaded=false;
        if(d&&d.is_admin===false){ apShowUnauthorizedMsg(); return; }
        if(d&&d.error==='csrf_forbidden'){
            apGateShow(<?php echo json_encode($translations['csrf_forbidden'] ?? 'Security check failed. Refresh the page and try again.'); ?>);
            return;
        }
        apGateShow('');
    }).catch(function(){ apLoaded=false; apGateShow(''); });
}
function closeAdminPanel(){
    overlay.classList.remove('ap-edge-omniroute');
    overlay.classList.remove('active');
    document.body.style.overflow='';
    clearHash();
}

/* ── AJAX load ── */
const AP_UNAUTH='__ap_unauthenticated__';
const AP_GATE='__ap_gate_required__';
function apIsUnauthorizedText(t){
    t=String(t||'');
    return /id="auth-modal"|id="auth-form"/i.test(t)
        || /(not authorized|unauthorized|not authenticated|session[^<]{0,40}(expired|invalid)|не авториз|истекла|сессия[^<]{0,40}истекл)/i.test(t);
}
function apParseAdminError(t){
    try{
        var j=JSON.parse(t);
        if(j&&(j.admin_auth_required||j.error==='admin_auth_required')) return AP_GATE;
        if(j&&(j.error==='csrf_forbidden'||j.error==='cross_origin_forbidden')) return j.error;
    }catch(e){}
    if(apIsUnauthorizedText(t)) return AP_UNAUTH;
    return '';
}
function apShowUnauthorizedMsg(){
    apGateHide();
    if(skeleton){
        var sp=document.getElementById('ap-loading-spinner')||skeleton.querySelector('svg');
        if(sp) sp.style.display='none';
    }
    var msgEl=document.getElementById('ap-loading-msg');
    if(msgEl){
        msgEl.style.display='';
        msgEl.style.opacity='1';
        msgEl.innerHTML='Session expired. Please <a href="/login" style="color:var(--primary-color);text-decoration:underline;">log in again</a>.';
    }
}
function loadPanel(target, year){
    apGateHide();
    if(skeleton) skeleton.style.display='flex';
    var spin=document.getElementById('ap-loading-spinner'); if(spin) spin.style.display='';
    var fields={get_admin_panel:'1',ajax:'1'};
    if(year) fields.stats_year=year;
    var msgEl=document.getElementById('ap-loading-msg');
    if(msgEl){ msgEl.style.display=''; msgEl.textContent=<?php echo json_encode($translations['admin_panel_loading']??'Loading Admin Panel...'); ?>; }
    var t=apCsrfToken();
    var headers={}; if(t) headers['X-CSRF-TOKEN']=t;
    fetch('/main',{method:'POST',body:apAdminFd(fields),credentials:'same-origin',headers:headers})
        .then(function(r){
            return r.text().then(function(t){
                var code=apParseAdminError(t);
                if(r.status===401||code===AP_GATE) throw new Error(AP_GATE);
                if(code==='csrf_forbidden') throw new Error('csrf_forbidden');
                if(r.status===401||r.status===403||code===AP_UNAUTH) throw new Error(AP_UNAUTH);
                if(!r.ok) throw new Error('HTTP '+r.status+': '+t.substring(0,300));
                var jsonStart = t.indexOf('{');
                if(jsonStart > 0){ t = t.substring(jsonStart); }
                try{ return JSON.parse(t); }
                catch(e){ throw new Error('Invalid JSON. Server returned: '+t.substring(0,300)); }
            });
        })
        .then(function(d){
            if(d&&d.csrf) apRememberCsrf(d.csrf);
            if(!d||!d.success){
                if(d&&(d.admin_auth_required||d.error==='admin_auth_required')) {apGateShow('');return;}
                if(d&&d.error==='csrf_forbidden'){ apGateShow(<?php echo json_encode($translations['csrf_forbidden'] ?? 'Security check failed. Refresh the page and try again.'); ?>); return; }
                if(d&&!d.success&&/(not authorized|unauthorized|not authenticated|не авториз)/i.test(d.error||'')) {apShowUnauthorizedMsg();return;}
                if(msgEl)msgEl.textContent='Error: '+(d&&d.error?d.error:'Unknown error');return;
            }
            apLoaded=true;
            try{apStatsData=JSON.parse(d.stats||'{}');}catch(e){apStatsData={};}
            if(d.available_years && Array.isArray(d.available_years)){
                apAvailableYears = d.available_years;
            }
            dynRoot.innerHTML=d.html;
            skeleton.style.display='none';
            dynRoot.style.cssText='display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;';
            apEnsureStatsDeps().catch(function(e){console.error('[AdminPanel] stats dependencies:',e);});
            openPanel(target||'ap-users');
            wireEvents();
        })
        .catch(function(err){
            if(err&&err.message===AP_GATE){apGateShow('');}
            else if(err&&err.message==='csrf_forbidden'){apGateShow(<?php echo json_encode($translations['csrf_forbidden'] ?? 'Security check failed. Refresh the page and try again.'); ?>);}
            else if(err&&err.message===AP_UNAUTH){apShowUnauthorizedMsg();}
            else if(msgEl){msgEl.style.display=''; msgEl.textContent='Error: '+(err.message||'Unknown error');}
            console.error('[AdminPanel]',err);
        });
}

/* Reload stats data for a specific year */
function reloadAdminStats(year){
    if(!year) year = apCurrentYear || new Date().getFullYear().toString();
    var fd=new FormData();
    fd.append('get_admin_panel','1');
    fd.append('ajax','1');
    fd.append('stats_year', year);
    var msgEl=document.getElementById('ap-loading-msg');
    fetch('/main',{method:'POST',body:fd})
        .then(function(r){
            if(!r.ok) throw new Error('HTTP '+r.status);
            return r.json();
        })
        .then(function(d){
            if(!d||!d.success) return;
            try{apStatsData=JSON.parse(d.stats||'{}');}catch(e){apStatsData={};}
            // Update available years if provided
            if(d.available_years && Array.isArray(d.available_years)){
                apAvailableYears = d.available_years;
                // Re-populate year selector with updated years
                apPopulateYearSelector();
            }
            // Switch to year mode and deactivate day buttons
            apChartRange = 'year';
            if(dynRoot){
                dynRoot.querySelectorAll('.ap-chart-range-btn').forEach(function(b){
                    b.classList.remove('active');
                });
            }
            apFilterByYear();
            apEnsureStatsDeps().then(function(){
                initApStats();
                renderApStatsChart();
                if(apAnalysisVisible) renderApAnalysis();
            }).catch(function(e){console.error('[AdminPanel]',e);});
        })
        .catch(function(err){console.error('[AdminPanel]',err);});
}

/* ── SSH panel ── */
function wireSshPanel(){
    var terminalEl = dynRoot.querySelector('#ap-ssh-terminal');
    var terminalShellEl = dynRoot.querySelector('.ap-ssh-terminal-shell');
    var settingsBtn = dynRoot.querySelector('#ap-ssh-settings-btn');
    var modalEl = dynRoot.querySelector('#ap-ssh-settings-modal');
    var closeBtn = dynRoot.querySelector('#ap-ssh-settings-close');
    if(!terminalEl || !settingsBtn || !modalEl || !closeBtn){
        return;
    }

    var hostEl = dynRoot.querySelector('#ap-ssh-host');
    var userEl = dynRoot.querySelector('#ap-ssh-user');
    var portEl = dynRoot.querySelector('#ap-ssh-port');
    var keyPathEl = dynRoot.querySelector('#ap-ssh-key-path');
    var keyPassEl = dynRoot.querySelector('#ap-ssh-key-passphrase');
    var keyHintEl = dynRoot.querySelector('#ap-ssh-key-hint');

    var testBtn = dynRoot.querySelector('#ap-ssh-test-profile');
    var saveBtn = dynRoot.querySelector('#ap-ssh-save-profile');
    var modalStatusEl = dynRoot.querySelector('#ap-ssh-modal-status');
    var connectOutEl = dynRoot.querySelector('#ap-ssh-modal-connect-output');
    if(!hostEl || !userEl || !portEl || !keyPathEl || !keyPassEl || !testBtn || !saveBtn || !modalStatusEl || !connectOutEl){
        return;
    }

    function parseApiJson(response){
        return response.text().then(function(raw){
            var txt = String(raw || '').trim();
            var first = txt.indexOf('{');
            if(first > 0){ txt = txt.slice(first); }
            try{
                return JSON.parse(txt);
            }catch(err){
                if(txt.indexOf('cf-') !== -1 || txt.indexOf('cloudflare') !== -1 || txt.indexOf('challenge') !== -1){
                    throw new Error('Request blocked by Cloudflare. Try disabling Cloudflare protection for this endpoint.');
                }
                throw new Error('Invalid JSON response: ' + txt.slice(0, 200));
            }
        });
    }

    function setPreText(el, text){
        if(!el) return;
        el.textContent = String(text || '');
        try{ el.scrollTop = el.scrollHeight; }catch(_){}
    }

        /** NBSP / narrow NBSP → normal space (fixes 'broken' prompts on copy-paste) */
    function apSshNormalizeSpaces(s){
        return String(s || '').replace(/\u00a0/g,' ').replace(/\u202f/g,' ').replace(/\u2007/g,' ');
    }

        /** Remove helper lines like libs: … if they ever end up in the output */
    function apSshStripLibsLines(text){
        return String(text || '').split(/\r?\n/).filter(function(line){
            return !/^\s*libs\s*:/i.test(apSshNormalizeSpaces(line).trim());
        }).join('\n');
    }

        /** Simple ANSI highlighting for typical cmd output (dir), JSON and 'key=value' */
    function apSshAnsiHighlightLine(line){
        var raw = apSshNormalizeSpaces(line);
        var t = raw.trim();
        if(t === '') return raw;

        var DIM = '\x1b[90m';
        var GRN = '\x1b[32m';
        var CYA = '\x1b[36m';
        var YEL = '\x1b[33m';
        var MAG = '\x1b[35m';
        var RST = '\x1b[0m';

        if(/^\s*Volume in drive/i.test(t) || /^\s*Volume Serial Number is/i.test(t)){
            return GRN + raw + RST;
        }
        if(/^\s*Directory of\b/i.test(t)){
            return CYA + raw + RST;
        }
        if(/^\s*\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}/.test(t) && /<DIR>/i.test(t)){
            return raw.replace(/^(\s*\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}\s+)/, DIM + '$1' + RST)
                .replace(/<DIR>/gi, CYA + '<DIR>' + RST);
        }
        if(/^\s*\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}\s+/.test(t) && !/<DIR>/i.test(t)){
            return raw.replace(/^(\s*\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}\s+)/, DIM + '$1' + RST)
                .replace(/(\d[\d,\s]*\s*bytes?|\d[\d,\s]*\s*File\(s\)|\d[\d,\s]*\s*Dir\(s\))/gi, YEL + '$1' + RST);
        }
        if(/^\s*[\{\[]/.test(t) || /^\s*"[^"]+"\s*:/.test(t)){
            return raw.replace(/("(?:\\.|[^"\\])*")/g, YEL + '$1' + RST)
                .replace(/\b(true|false|null)\b/g, MAG + '$1' + RST);
        }
        if(/^\s*[A-Za-z_][\w.-]*\s*=/.test(t)){
            return raw.replace(/^(\s*[A-Za-z_][\w.-]*)(\s*=)/, GRN + '$1' + RST + '$2');
        }
        return raw;
    }

    var apSshWs = null;
    var apSshWsReady = false;
    var apSshWsToken = null;
    var AP_SSH_BASE_FONT_SIZE = 13;
    var AP_SSH_MIN_FONT_SIZE = 9;
    var AP_SSH_LINE_HEIGHT = 1.38;
    var AP_SSH_FONT_FAMILY = '"DejaVu Sans Mono", "Liberation Mono", Consolas, "Courier New", "Symbols Nerd Font Mono", "Symbols Nerd Font", "MesloLGS NF", "Hack Nerd Font", "JetBrainsMono Nerd Font", "FiraCode Nerd Font", "CaskaydiaCove Nerd Font", monospace';

    function apSshTermSize(term){
        return {
            cols: Math.max(1, Math.floor((term && term.cols) || 80)),
            rows: Math.max(1, Math.floor((term && term.rows) || 24))
        };
    }

    function apSshCellMetrics(term){
        try{
            var d = term._core._renderService.dimensions.css;
            return { cw: d.cell.width || 0, ch: d.cell.height || 0 };
        }catch(_){ return null; }
    }

    function apSshClampTermToContainer(term){
        if(!term || !terminalEl) return;
        var rect = terminalEl.getBoundingClientRect();
        var maxBottom = rect.bottom - 2;
        var guard = 0;
        while(guard++ < 80 && term.rows > 1){
            var xt = terminalEl.querySelector('.xterm');
            if(!xt || xt.getBoundingClientRect().bottom <= maxBottom) break;
            term.resize(term.cols, term.rows - 1);
        }
    }

    function apSshSyncTerminalHeight(){
        if(!terminalShellEl || !terminalEl) return;
        var h = terminalShellEl.clientHeight;
        if(h > 0){
            terminalEl.style.height = h + 'px';
            terminalEl.style.maxHeight = h + 'px';
        }
    }

    function apSshFitTermDimensions(term, fitAddon){
        if(!term || !terminalEl) return;
        apSshSyncTerminalHeight();
        var w = terminalEl.clientWidth;
        var h = terminalEl.clientHeight;
        if(w < 4 || h < 4) return;
        if(fitAddon && fitAddon.fit) fitAddon.fit();
        var m = apSshCellMetrics(term);
        if(m && m.ch > 0 && m.cw > 0){
            var maxRows = Math.max(1, Math.floor((h - 2) / m.ch));
            var maxCols = Math.max(1, Math.floor(w / m.cw));
            if(term.rows > maxRows || term.cols > maxCols){
                term.resize(maxCols, maxRows);
            }
        }
        apSshClampTermToContainer(term);
    }

    function apSshFitFontSize(term, fitAddon){
        if(!term || !terminalEl) return;
        for(var fs = AP_SSH_BASE_FONT_SIZE; fs >= AP_SSH_MIN_FONT_SIZE; fs--){
            term.options.fontSize = fs;
            apSshFitTermDimensions(term, fitAddon);
            var xt = terminalEl.querySelector('.xterm');
            var ok = !xt || xt.getBoundingClientRect().bottom <= terminalEl.getBoundingClientRect().bottom + 0.5;
            if(ok || fs === AP_SSH_MIN_FONT_SIZE) break;
        }
    }

    function initTerminal(){
        if(terminalEl._apTerm){ return; }

        function ensureXterm(cb){
            if(window._apXtermReady){
                cb();
                return;
            }
            if(window._apXtermPromise){
                window._apXtermPromise.then(function(){ cb(); });
                return;
            }

            window._apXtermPromise = new Promise(function(resolve){
                function apLoadStyle(href){
                    if(document.querySelector('link[href="'+href+'"]')) return;
                    var l = document.createElement('link');
                    l.rel = 'stylesheet';
                    l.href = href;
                    document.head.appendChild(l);
                }
                function apLoadScript(src){
                    return new Promise(function(res, rej){
                        var s = document.createElement('script');
                        s.src = src;
                        s.async = false;
                        s.onload = function(){
                            setTimeout(res, 50);
                        };
                        s.onerror = function(){ rej(new Error('Failed to load: ' + src)); };
                        document.head.appendChild(s);
                    });
                }

                var oldDefine = window.define;
                if(oldDefine && oldDefine.amd) {
                    window.define = undefined;
                }

                apLoadStyle('https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css');
                apLoadScript('https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js')
                    .then(function(){
                        return apLoadScript('https://cdn.jsdelivr.net/npm/@xterm/addon-fit@0.10.0/lib/addon-fit.js');
                    })
                    .then(function(){
                        if(oldDefine) {
                            window.define = oldDefine;
                        }
                        window._apXtermReady = !!(window.Terminal);
                        resolve();
                    })
                    .catch(function(err){
                        if(oldDefine) {
                            window.define = oldDefine;
                        }
                        console.error('[SSH Terminal]', err);
                        resolve();
                    });
            });

            window._apXtermPromise.then(function(){ cb(); });
        }

        ensureXterm(function(){
            var pc = getComputedStyle(document.documentElement);
            var bg = pc.getPropertyValue('--bg-color').trim() || '#fff';
            var tc = pc.getPropertyValue('--text-color').trim() || '#000';
            var border = pc.getPropertyValue('--border-color').trim() || '#ddd';
            var primary = pc.getPropertyValue('--primary-color').trim() || '#007bff';

            terminalEl.style.background = bg;

            // Create terminal
            var term = new window.Terminal({
                cursorBlink: true,
                fontFamily: AP_SSH_FONT_FAMILY,
                fontSize: AP_SSH_BASE_FONT_SIZE,
                lineHeight: AP_SSH_LINE_HEIGHT,
                scrollback: 1200,
                rescaleOverlappingGlyphs: true,
                tabStopWidth: 4,
                theme: { background: bg, foreground: tc, cursor: primary }
            });

            terminalEl.innerHTML = '';
            term.open(terminalEl);
            if(document.fonts && document.fonts.load){
                document.fonts.load('12px "Symbols Nerd Font Mono"').then(function(){
                    try{
                        term.refresh(0, Math.max(0, (term.rows || 1) - 1));
                    }catch(_){}
                    apSshFitTerm();
                }).catch(function(){});
            }

            // Fit addon (resize without extra scrollbars on the wrapper)
            var fitAddon = null;
            try{
                var FitCtor = (window.FitAddon && window.FitAddon.FitAddon) || window.FitAddon || null;
                if(FitCtor){
                    fitAddon = new FitCtor();
                    term.loadAddon(fitAddon);
                }
            }catch(e){ console.error('[SSH] FitAddon error:', e); }

            terminalEl._apTerm = term;
            terminalEl._apFit = fitAddon;

            function apSshFitTerm(){
                try{
                    apSshFitFontSize(term, fitAddon);
                }catch(_){}
                apSshWsResize();
            }
            terminalEl._apSshFit = apSshFitTerm;
            terminalEl._apSshUpdatePrompt = apSshFitTerm;
            setTimeout(apSshFitTerm, 50);
            setTimeout(apSshFitTerm, 300);
            function apSshScheduleFit(){
                if(terminalEl._apFitRaf) cancelAnimationFrame(terminalEl._apFitRaf);
                terminalEl._apFitRaf = requestAnimationFrame(function(){
                    terminalEl._apFitRaf = 0;
                    if(sshPanelEl && sshPanelEl.classList.contains('active')) apSshFitTerm();
                });
            }
            if(typeof ResizeObserver !== 'undefined'){
                if(terminalEl._apResizeObs) try{ terminalEl._apResizeObs.disconnect(); }catch(_){}
                terminalEl._apResizeObs = new ResizeObserver(apSshScheduleFit);
                if(terminalShellEl) terminalEl._apResizeObs.observe(terminalShellEl);
                if(terminalEl) terminalEl._apResizeObs.observe(terminalEl);
                var sshLayoutEl = dynRoot.querySelector('.ap-ssh-layout');
                if(sshLayoutEl) terminalEl._apResizeObs.observe(sshLayoutEl);
                var apContentEl = dynRoot.querySelector('#ap-content');
                if(apContentEl) terminalEl._apResizeObs.observe(apContentEl);
            }
            if(!terminalEl._apWinResizeBound){
                terminalEl._apWinResizeBound = true;
                window.addEventListener('resize', apSshScheduleFit);
            }
            terminalEl._apSshShowPrompt = function(){ term.focus(); };

            function apSshWsSend(obj){
                if(apSshWs && apSshWs.readyState === 1){
                    apSshWs.send(JSON.stringify(obj));
                }
            }
            function apSshWsResize(){
                if(!apSshWsReady) return;
                var sz = apSshTermSize(term);
                apSshWsSend({type:'resize', cols: sz.cols, rows: sz.rows});
            }
            function apSshWsDisconnect(){
                apSshWsReady = false;
                if(apSshWs){
                    try{ apSshWs.close(); }catch(_){}
                    apSshWs = null;
                }
            }
            function apSshWsConnect(){
                apSshWsDisconnect();
                apSshWsReady = false;
                term.clear();
                term.writeln('\x1b[90mConnecting to SSH shell...\x1b[0m');
                var fd = new FormData();
                fd.append('ssh_terminal_ws_token', '1');
                fd.append('ajax', '1');
                fetch('/main', {method:'POST', body:fd})
                    .then(parseApiJson)
                    .then(function(d){
                        if(!d || !d.success || !d.token || !d.ws_url){
                            term.writeln('\x1b[31m' + ((d && d.error) ? d.error : 'WebSocket token failed') + '\x1b[0m');
                            term.writeln('\x1b[90mOpen settings (gear) and save SSH profile first.\x1b[0m');
                            return;
                        }
                        apSshWsToken = d.token;
                        apSshWs = new WebSocket(d.ws_url);
                        apSshWs.onopen = function(){
                            apSshFitTerm();
                            var sz = apSshTermSize(term);
                            apSshWsSend({type:'auth', token: apSshWsToken, cols: sz.cols, rows: sz.rows});
                        };
                        apSshWs.onmessage = function(ev){
                            var msg;
                            try{ msg = JSON.parse(ev.data); }catch(_){ return; }
                            if(!msg || !msg.type) return;
                            if(msg.type === 'output' && msg.data){
                                term.write(msg.data);
                            }else if(msg.type === 'connected'){
                                apSshWsReady = true;
                                term.focus();
                            }else if(msg.type === 'error'){
                                term.writeln('\r\n\x1b[31m' + (msg.message || 'SSH error') + '\x1b[0m');
                            }
                        };
                        apSshWs.onclose = function(){
                            apSshWsReady = false;
                            term.writeln('\r\n\x1b[90mSSH session closed.\x1b[0m');
                        };
                        apSshWs.onerror = function(){
                            term.writeln('\r\n\x1b[31mWebSocket connection failed. Is the SSH WS service running?\x1b[0m');
                        };
                    })
                    .catch(function(err){
                        term.writeln('\x1b[31m' + ((err && err.message) ? err.message : 'network error') + '\x1b[0m');
                    });
            }
            terminalEl._apSshReconnect = apSshWsConnect;
            terminalEl._apSshSendInput = function(text){
                if(apSshWsReady) apSshWsSend({type:'input', data: String(text || '')});
            };

            term.onResize(function(){ apSshWsResize(); });
            term.onData(function(data){
                if(apSshWsReady) apSshWsSend({type:'input', data: data});
            });

            term.writeln(apSshNormalizeSpaces('Interactive SSH terminal (PTY). Configure profile in settings if needed.'));
            apSshWsConnect();

            function apSshCopyTextToClipboard(text){
                if(typeof text !== 'string' || !text.length) return;
                function fallbackCopy(){
                    try{
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'fixed';
                        ta.style.left = '-9999px';
                        ta.style.top = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                    }catch(_){}
                }
                if(navigator.clipboard && typeof navigator.clipboard.writeText === 'function'){
                    navigator.clipboard.writeText(text).catch(fallbackCopy);
                }else{
                    fallbackCopy();
                }
            }
            if(typeof term.attachCustomKeyEventHandler === 'function'){
                term.attachCustomKeyEventHandler(function(e){
                    if(e.type !== 'keydown') return true;
                    var ctrl = e.ctrlKey === true;
                    var shift = e.shiftKey === true;
                    var key = e.key || '';
                    if(ctrl && (key === 'c' || key === 'C')){
                        var sel = '';
                        try{ sel = (typeof term.getSelection === 'function') ? term.getSelection() : ''; }catch(_){}
                        if(sel && sel.length){
                            e.preventDefault();
                            apSshCopyTextToClipboard(sel);
                            try{ if(typeof term.clearSelection === 'function') term.clearSelection(); }catch(_){}
                            return false;
                        }
                    }
                    if(ctrl && shift && (key === 'c' || key === 'C')){
                        var sel2 = '';
                        try{ sel2 = (typeof term.getSelection === 'function') ? term.getSelection() : ''; }catch(_){}
                        if(sel2 && sel2.length){
                            e.preventDefault();
                            apSshCopyTextToClipboard(sel2);
                            return false;
                        }
                    }
                    return true;
                });
            }
            terminalEl.addEventListener('contextmenu', function(e){
                if(!term) return;
                e.preventDefault();
                var sel = '';
                try{ sel = (typeof term.getSelection === 'function') ? term.getSelection() : ''; }catch(_){}
                if(sel && sel.trim()){
                    apSshCopyTextToClipboard(sel);
                    return;
                }
                if(!navigator.clipboard || !navigator.clipboard.readText) return;
                navigator.clipboard.readText().then(function(text){
                    if(apSshWsReady && text) apSshWsSend({type:'input', data: text});
                }).catch(function(){});
            });
        });
    }

    function loadProfileIntoModal(){
        setPreText(connectOutEl, '');
        setStatus(modalStatusEl, T.ssh_loading_profile || 'Loading saved SSH profile...', 'info');

        var fd = new FormData();
        fd.append('ssh_terminal_get_profile', '1');
        fd.append('ajax', '1');
        fetch('/main', {method:'POST', body:fd})
            .then(parseApiJson)
            .then(function(d){
                if(d && d.success){
                    hostEl.value = d.host || '';
                    userEl.value = d.user || '';
                    portEl.value = d.port || 22;
                    keyPathEl.value = d.key_path || d.default_key_path || '';
                    if(keyHintEl){
                        keyHintEl.textContent = keyPathEl.value
                            ? (T.ssh_key_ready || 'SSH key found. Only key authentication is used.')
                            : (T.ssh_key_only_hint || 'Password login is disabled. Place id_ed25519 or id_rsa in data/.ssh/');
                    }
                    if(terminalEl && terminalEl._apSshUpdatePrompt){
                        terminalEl._apSshUpdatePrompt();
                    }
                    setStatus(modalStatusEl, T.ssh_profile_loaded || 'Profile loaded', 'success');
                }else{
                    if(d && d.default_key_path){
                        keyPathEl.value = d.default_key_path;
                    }
                    setStatus(modalStatusEl, (d && d.error) ? d.error : (T.ssh_no_saved_profile || 'No saved SSH profile'), 'error');
                    if(keyHintEl){
                        keyHintEl.textContent = T.ssh_key_only_hint || 'Password login is disabled. Place id_ed25519 or id_rsa in data/.ssh/';
                    }
                }
            })
            .catch(function(err){
                setStatus(modalStatusEl, (T.ssh_load_failed || 'Load failed:') + ' ' + (err && err.message ? err.message : (T.network_error || 'network error')), 'error');
            });
    }

    function runInitFromModal(){
        setStatus(modalStatusEl, T.ssh_saving_testing || 'Saving & testing SSH connection...', 'info');
        setPreText(connectOutEl, '');

        var fd = new FormData();
        fd.append('ssh_terminal_init', '1');
        fd.append('ajax', '1');
        fd.append('ssh_host', (hostEl && hostEl.value ? hostEl.value : '').trim());
        fd.append('ssh_user', (userEl && userEl.value ? userEl.value : '').trim());
        fd.append('ssh_port', (portEl && portEl.value ? portEl.value : '22').toString().trim());
        fd.append('ssh_key_path', (keyPathEl && keyPathEl.value ? keyPathEl.value : '').trim());

        if(keyPassEl && keyPassEl.value && String(keyPassEl.value).trim() !== ''){
            fd.append('ssh_key_passphrase', keyPassEl.value);
        }

        fetch('/main', {method:'POST', body:fd})
            .then(parseApiJson)
            .then(function(d){
                var ok = !!(d && d.success && d.connected);
                setStatus(modalStatusEl,
                    ok
                        ? ((T.ssh_connected_via || 'SSH connected via') + ' ' + (d.backend || 'phpseclib'))
                        : ((T.ssh_not_connected || 'SSH not connected:') + ' ' + ((d && (d.connect_error || d.error)) ? (d.connect_error || d.error) : (T.ssh_unknown_error || 'unknown error'))),
                    ok ? 'success' : 'error'
                );
                if(d && d.connect_output){
                    setPreText(connectOutEl, d.connect_output);
                }else if(d && (d.connect_error || d.error)){
                    setPreText(connectOutEl, (d.connect_error || d.error));
                }else{
                    setPreText(connectOutEl, '');
                }

                // Echo into terminal for better UX
                var term = terminalEl && terminalEl._apTerm ? terminalEl._apTerm : null;
                if(term){
                    var msg = ok
                        ? ((T.ssh_connected_via || 'SSH connected via') + ' ' + (d.backend || 'phpseclib'))
                        : ((T.ssh_not_connected || 'SSH not connected:') + ' ' + ((d && (d.connect_error || d.error)) ? (d.connect_error || d.error) : (T.ssh_unknown_error || 'unknown error')));
                    term.writeln((ok ? '\x1b[32m' : '\x1b[31m') + msg + '\x1b[0m');
                    if(d && d.connect_output){
                        apSshStripLibsLines(apSshNormalizeSpaces(String(d.connect_output))).replace(/\r\n/g,'\n').split('\n').forEach(function(line){
                            term.writeln(apSshAnsiHighlightLine(line));
                        });
                    }
                }
                if(ok && terminalEl && typeof terminalEl._apSshReconnect === 'function'){
                    terminalEl._apSshReconnect();
                }else if(terminalEl && terminalEl._apSshUpdatePrompt){
                    terminalEl._apSshUpdatePrompt();
                }
            })
            .catch(function(err){
                setStatus(modalStatusEl, (T.ssh_test_failed || 'SSH test failed:') + ' ' + (err && err.message ? err.message : (T.network_error || 'network error')), 'error');
                setPreText(connectOutEl, err && err.message ? err.message : (T.network_error || 'network error'));
            });
    }

    settingsBtn.addEventListener('click', function(){
        modalEl.style.display = 'flex';
        loadProfileIntoModal();
    });
    closeBtn.addEventListener('click', function(){
        modalEl.style.display = 'none';
    });
    modalEl.addEventListener('click', function(e){
        if(e.target === modalEl){
            modalEl.style.display = 'none';
        }
    });

    // ── Quick Commands ──
    var qcManageBtn = dynRoot.querySelector('#ap-ssh-quick-commands-manage-btn');
    var qcModal = dynRoot.querySelector('#ap-ssh-quick-commands-modal');
    var qcCloseBtn = dynRoot.querySelector('#ap-ssh-quick-commands-close');
    var qcList = dynRoot.querySelector('#ap-ssh-qc-list');
    var qcNameInput = dynRoot.querySelector('#ap-ssh-qc-name');
    var qcCommandInput = dynRoot.querySelector('#ap-ssh-qc-command');
    var qcDirectoryInput = dynRoot.querySelector('#ap-ssh-qc-directory');
    var qcSaveBtn = dynRoot.querySelector('#ap-ssh-qc-save-btn');
    var qcCancelBtn = dynRoot.querySelector('#ap-ssh-qc-cancel-btn');
    var qcFormTitle = dynRoot.querySelector('#ap-ssh-qc-form-title');
    var qcEditIdInput = dynRoot.querySelector('#ap-ssh-qc-edit-id');
    var qcBar = dynRoot.querySelector('#ap-ssh-quick-commands-bar');

    if(qcBar && qcManageBtn && qcModal && qcCloseBtn && qcList){
        var apQuickCommands = [];

        function loadQuickCommands(){
            var fd = new FormData();
            fd.append('quick_command_action', 'list');
            fd.append('ajax', '1');
            fetch('/main', {method: 'POST', body: fd})
                .then(parseApiJson)
                .then(function(d){
                    if(!d || !d.success){
                        return;
                    }
                    apQuickCommands = d.commands || [];
                    renderQuickCommandsBar();
                    if(qcModal.style.display === 'flex'){
                        renderQuickCommandsList(apQuickCommands);
                    }
                })
                .catch(function(err){
                    console.error('[QuickCommands] Error loading:', err);
                });
        }

        function renderQuickCommandsBar(){
            // Clear existing buttons except the manage button
            var existingBtns = qcBar.querySelectorAll('.ap-ssh-qc-bar-btn');
            existingBtns.forEach(function(btn){ btn.remove(); });

            apQuickCommands.forEach(function(cmd){
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ap-ssh-qc-bar-btn';
                btn.setAttribute('data-cmd-command', cmd.command);
                btn.setAttribute('data-cmd-directory', cmd.directory || '');
                btn.style.cssText = 'padding:5px 10px;background:#4CAF50;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:500;white-space:nowrap;transition:background .15s;';
                btn.textContent = cmd.name;
                btn.title = cmd.command + (cmd.directory ? ' (in ' + cmd.directory + ')' : '');

                btn.addEventListener('mouseenter', function(){ this.style.background = '#45a049'; });
                btn.addEventListener('mouseleave', function(){ this.style.background = '#4CAF50'; });

                btn.addEventListener('click', function(){
                    var command = this.getAttribute('data-cmd-command');
                    var directory = this.getAttribute('data-cmd-directory');
                    executeQuickCommand(command, directory);
                });

                // Insert before manage button
                qcBar.insertBefore(btn, qcManageBtn);
            });
            if(terminalEl && typeof terminalEl._apSshFit === 'function'){
                requestAnimationFrame(function(){ terminalEl._apSshFit(); });
            }
        }

        function renderQuickCommandsList(commands){
            if(commands.length === 0){
                qcList.innerHTML = '<div style="text-align:center;padding:30px;opacity:.6;">No commands saved yet. Add your first command below.</div>';
                return;
            }
            qcList.innerHTML = '';
            commands.forEach(function(cmd){
                var item = document.createElement('div');
                item.style.cssText = 'background:var(--group-block-bg);padding:14px;border-radius:8px;border:1px solid var(--border-color);';
                item.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">' +
                    '<div style="flex:1;min-width:0;">' +
                        '<div style="font-weight:600;margin-bottom:6px;">' + escapeHtml(cmd.name) + '</div>' +
                        '<div style="font-family:monospace;font-size:12px;opacity:.8;margin-bottom:4px;word-break:break-all;">' + escapeHtml(cmd.command) + '</div>' +
                        (cmd.directory ? '<div style="font-size:11px;opacity:.6;">Dir: ' + escapeHtml(cmd.directory) + '</div>' : '') +
                    '</div>' +
                    '<div style="display:flex;gap:6px;flex-shrink:0;">' +
                        '<button type="button" class="ap-ssh-qc-edit-btn" data-cmd-id="' + cmd.id + '" style="padding:6px 10px;background:var(--primary-color);color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;" title="' + T.ssh_qc_edit + '">✎ ' + T.ssh_qc_edit + '</button>' +
                        '<button type="button" class="ap-ssh-qc-delete-btn" data-cmd-id="' + cmd.id + '" style="padding:6px 10px;background:#f44;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;" title="' + T.ssh_qc_delete + '">✕ ' + T.ssh_qc_delete + '</button>' +
                    '</div>' +
                '</div>';
                qcList.appendChild(item);
            });

            // Wire edit buttons
            qcList.querySelectorAll('.ap-ssh-qc-edit-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var cmdId = this.getAttribute('data-cmd-id');
                    editQuickCommand(cmdId);
                });
            });

            // Wire delete buttons
            qcList.querySelectorAll('.ap-ssh-qc-delete-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var cmdId = this.getAttribute('data-cmd-id');
                    if(typeof fmConfirm==='function'){fmConfirm(T.delete_this_command,function(){deleteQuickCommand(cmdId);});}
                    else if(confirm(T.delete_this_command)){deleteQuickCommand(cmdId);}
                });
            });
        }

        function escapeHtml(text){
            var div = document.createElement('div');
            div.textContent = String(text || '');
            return div.innerHTML;
        }

        function executeQuickCommand(command, directory){
            if(!terminalEl._apTerm){
                fmAlert('Terminal is not initialized yet. Please wait...');
                return;
            }
            var fullCommand = directory ? ('cd "' + String(directory).replace(/"/g,'') + '" && ' + command) : command;
            if(typeof terminalEl._apSshSendInput === 'function'){
                terminalEl._apSshSendInput(fullCommand + '\r');
            }else{
                fmAlert('SSH shell is not connected. Save SSH profile in settings.');
            }
        }

        function editQuickCommand(cmdId){
            var cmd = apQuickCommands.find(function(c){ return c.id == cmdId; });
            if(!cmd){
                fmAlert('Command not found');
                return;
            }
            qcNameInput.value = cmd.name;
            qcCommandInput.value = cmd.command;
            qcDirectoryInput.value = cmd.directory || '';
            qcEditIdInput.value = cmdId;
            qcFormTitle.textContent = T.ssh_qc_edit_title;
            qcSaveBtn.textContent = T.ssh_qc_update;
            qcCancelBtn.style.display = 'inline-block';
            qcNameInput.focus();
        }

        function deleteQuickCommand(cmdId){
            var fd = new FormData();
            fd.append('quick_command_action', 'delete');
            fd.append('id', cmdId);
            fd.append('ajax', '1');
            fetch('/main', {method: 'POST', body: fd})
                .then(parseApiJson)
                .then(function(d){
                    if(d && d.success){
                        loadQuickCommands();
                    } else {
                        fmAlert('Error: ' + (d && d.error ? d.error : 'Unknown'));
                    }
                })
                .catch(function(err){
                    fmAlert('Error deleting command: ' + err.message);
                });
        }

        function resetQuickForm(){
            qcNameInput.value = '';
            qcCommandInput.value = '';
            qcDirectoryInput.value = '';
            qcEditIdInput.value = '';
            qcFormTitle.textContent = T.ssh_qc_add_title;
            qcSaveBtn.textContent = T.ssh_qc_save;
            qcCancelBtn.style.display = 'none';
        }

        // Open management modal
        qcManageBtn.addEventListener('click', function(){
            qcModal.style.display = 'flex';
            loadQuickCommands();
            resetQuickForm();
        });

        // Close modal
        qcCloseBtn.addEventListener('click', function(){
            qcModal.style.display = 'none';
        });

        qcModal.addEventListener('click', function(e){
            if(e.target === qcModal){
                qcModal.style.display = 'none';
            }
        });

        // Save button
        qcSaveBtn.addEventListener('click', function(){
            var name = qcNameInput.value.trim();
            var command = qcCommandInput.value.trim();
            var directory = qcDirectoryInput.value.trim();
            var editId = qcEditIdInput.value;

            if(!name || !command){
                fmAlert('Name and Command are required');
                return;
            }

            var fd = new FormData();
            if(editId){
                fd.append('quick_command_action', 'update');
                fd.append('id', editId);
            } else {
                fd.append('quick_command_action', 'create');
            }
            fd.append('name', name);
            fd.append('command', command);
            fd.append('directory', directory);
            fd.append('ajax', '1');

            fetch('/main', {method: 'POST', body: fd})
                .then(parseApiJson)
                .then(function(d){
                    if(d && d.success){
                        resetQuickForm();
                        loadQuickCommands();
                    } else {
                        fmAlert('Error: ' + (d && d.error ? d.error : 'Unknown'));
                    }
                })
                .catch(function(err){
                    fmAlert('Error saving command: ' + err.message);
                });
        });

        // Cancel button
        qcCancelBtn.addEventListener('click', function(){
            resetQuickForm();
        });

        // Initial load
        loadQuickCommands();
    }

    testBtn.addEventListener('click', function(){
        runInitFromModal();
    });
    saveBtn.addEventListener('click', function(){
        runInitFromModal();
    });

    // Init terminal only when SSH panel becomes visible
    var sshPanelEl = dynRoot.querySelector('#ap-ssh');
    function ensureTerminalVisible(){
        if(sshPanelEl && sshPanelEl.classList.contains('active')){
            initTerminal();
            setTimeout(function(){
                if(terminalEl && typeof terminalEl._apSshFit === 'function') terminalEl._apSshFit();
            }, 80);
        }
    }
    ensureTerminalVisible();
    var sshNavBtn = dynRoot.querySelector('[data-panel="ap-ssh"]');
    if(sshNavBtn){
        sshNavBtn.addEventListener('click', function(){
            setTimeout(function(){ ensureTerminalVisible(); }, 50);
        });
    }
}

/* ── Wire all events after HTML injected ── */
function wireEvents(){
    /* nav */
    dynRoot.querySelectorAll('.ap-nav-btn').forEach(function(b){b.addEventListener('click',function(ev){
        var pid=this.dataset.panel;
        openPanel(pid);
        if(pid==='ap-security') apSecurityRefresh();
    });});
    window.apEnsureStepup=function(done){
        var fd=new FormData();fd.append('admin_security_status','1');fd.append('ajax','1');
        return fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            if(d&&d.stepup_valid){ if(typeof done==='function') done(); return true; }
            openPanel('ap-security');
            var box=dynRoot.querySelector('#ap-sec-stepup-box'); if(box) box.style.display='block';
            var st=dynRoot.querySelector('#ap-sec-status'); if(st) st.textContent='Confirm identity to continue.';
            return false;
        }).catch(function(){return false;});
    };
    function apSecPost(action, extra){
        var fd=new FormData();fd.append(action,'1');fd.append('ajax','1');
        if(extra) Object.keys(extra).forEach(function(k){fd.append(k,extra[k]);});
        return fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();});
    }
    function apSecurityRefresh(){
        apSecPost('admin_security_status').then(function(d){
            var el=dynRoot.querySelector('#ap-sec-totp-status');
            if(el) el.textContent=(d.totp_enabled?'TOTP enabled':'TOTP not enabled')+(d.stepup_valid?' · step-up active':' · step-up required for sensitive actions')+(d.totp_required?' · TOTP required':'');
            var ta=dynRoot.querySelector('#ap-sec-ip-allowlist'); if(ta) ta.value=d.ip_allowlist||'';
        });
    }
    var setupBtn=dynRoot.querySelector('#ap-sec-totp-setup');
    if(setupBtn) setupBtn.addEventListener('click',function(){
        apSecPost('admin_totp_setup').then(function(d){
            var box=dynRoot.querySelector('#ap-sec-totp-setup-box'); if(box) box.style.display='block';
            var sec=dynRoot.querySelector('#ap-sec-totp-secret'); if(sec) sec.textContent=d.secret||'';
        });
    });
    var confBtn=dynRoot.querySelector('#ap-sec-totp-confirm');
    if(confBtn) confBtn.addEventListener('click',function(){
        var code=(dynRoot.querySelector('#ap-sec-totp-code')||{}).value||'';
        apSecPost('admin_totp_confirm',{code:code}).then(function(d){
            var st=dynRoot.querySelector('#ap-sec-status');
            if(st) st.textContent=d.success?'TOTP enabled':'Error: '+(d.error||'failed');
            if(d.success) apSecurityRefresh();
        });
    });
    var stepBtn=dynRoot.querySelector('#ap-sec-stepup');
    if(stepBtn) stepBtn.addEventListener('click',function(){
        var box=dynRoot.querySelector('#ap-sec-stepup-box'); if(box) box.style.display='block';
    });
    var stepGo=dynRoot.querySelector('#ap-sec-stepup-go');
    if(stepGo) stepGo.addEventListener('click',function(){
        var password=(dynRoot.querySelector('#ap-sec-stepup-password')||{}).value||'';
        var code=(dynRoot.querySelector('#ap-sec-stepup-code')||{}).value||'';
        apSecPost('admin_stepup',{password:password,code:code}).then(function(d){
            var st=dynRoot.querySelector('#ap-sec-status');
            if(st) st.textContent=d.success?'Unlocked':'Error: '+(d.error||'failed');
            if(d.success) apSecurityRefresh();
        });
    });
    var disBtn=dynRoot.querySelector('#ap-sec-totp-disable');
    if(disBtn) disBtn.addEventListener('click',function(){
        apSecPost('admin_totp_disable').then(function(d){
            var st=dynRoot.querySelector('#ap-sec-status');
            if(st) st.textContent=d.success?'TOTP disabled':'Error: '+(d.error||'failed');
            if(d.success) apSecurityRefresh();
        });
    });
    var ipSave=dynRoot.querySelector('#ap-sec-ip-save');
    if(ipSave) ipSave.addEventListener('click',function(){
        var allowlist=(dynRoot.querySelector('#ap-sec-ip-allowlist')||{}).value||'';
        apSecPost('save_admin_ip_allowlist',{allowlist:allowlist}).then(function(d){
            var st=dynRoot.querySelector('#ap-sec-status');
            if(st) st.textContent=d.success?'Allowlist saved':'Error: '+(d.error||'failed');
        });
    });
    var _apFetch=window.fetch;
    if(!_apFetch._apStepupWrapped){
        window.fetch=function(i,n){
            return _apFetch.call(this,i,n).then(function(r){
                if(r.status!==403) return r;
                return r.clone().json().then(function(d){
                    if(d&&(d.admin_auth_required||d.error==='admin_auth_required')){
                        apLoaded=false;
                        if(typeof apGateShow==='function') apGateShow('');
                    } else if(d&&(d.stepup_required||d.error==='stepup_required')){
                        if(typeof window.apEnsureStepup==='function') window.apEnsureStepup();
                    }
                    return r;
                }).catch(function(){return r;});
            });
        };
        window.fetch._apStepupWrapped=true;
    }
    (function(){
        var apSidebar = dynRoot.querySelector('#ap-sidebar');
        var activeNavTooltipBtn = null;
        function positionNavTooltip(btn){
            if(!btn) return;
            var tooltip = btn.querySelector('.ap-tooltip');
            if(!tooltip) return;
            var rect = btn.getBoundingClientRect();
            tooltip.style.display = 'block';
            tooltip.style.left = (rect.right + 12) + 'px';
            tooltip.style.top = (rect.top + (rect.height / 2) - (tooltip.offsetHeight / 2)) + 'px';
        }
        function hideNavTooltip(btn){
            var tooltip = btn ? btn.querySelector('.ap-tooltip') : null;
            if(tooltip) tooltip.style.display = 'none';
            if(activeNavTooltipBtn === btn) activeNavTooltipBtn = null;
        }
        dynRoot.querySelectorAll('.ap-nav-btn').forEach(function(btn){
            btn.addEventListener('mouseenter', function(){
                activeNavTooltipBtn = btn;
                positionNavTooltip(btn);
            });
            btn.addEventListener('mousemove', function(){
                if(activeNavTooltipBtn === btn) positionNavTooltip(btn);
            });
            btn.addEventListener('mouseleave', function(){
                hideNavTooltip(btn);
            });
            btn.addEventListener('focus', function(){
                activeNavTooltipBtn = btn;
                positionNavTooltip(btn);
            });
            btn.addEventListener('blur', function(){
                hideNavTooltip(btn);
            });
        });
        if(apSidebar){
            apSidebar.addEventListener('scroll', function(){
                if(activeNavTooltipBtn) positionNavTooltip(activeNavTooltipBtn);
            }, { passive: true });
        }
        window.addEventListener('resize', function(){
            if(activeNavTooltipBtn) positionNavTooltip(activeNavTooltipBtn);
        });
    })();
    /* admin panel nav keyboard: arrow up/down to switch tabs */
    (function(){
        var navBtns = dynRoot.querySelectorAll('.ap-nav-btn');
        if(!navBtns.length) return;
        document.addEventListener('keydown', function(e){
            var overlay = document.getElementById('admin-panel-overlay');
            if(!overlay || !overlay.classList.contains('active')) return;
            if(e.code !== 'ArrowUp' && e.code !== 'ArrowDown') return;
            var target = e.target;
            if(target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.contentEditable === 'true')) return;
            e.preventDefault();
            var btns = Array.from(dynRoot.querySelectorAll('.ap-nav-btn'));
            var focused = btns.indexOf(document.activeElement);
            if(focused < 0) focused = btns.findIndex(function(b){ return b.classList.contains('active'); });
            if(focused < 0) focused = 0;
            var next = e.code === 'ArrowDown' ? (focused + 1) % btns.length : (focused - 1 + btns.length) % btns.length;
            btns[next].focus();
            btns[next].click();
        });
    })();
    var cb=dynRoot.querySelector('#ap-close-btn'); if(cb) cb.addEventListener('click',closeAdminPanel);

    /* Analysis mode & year selector */
    apPopulateYearSelector();
    apWireAnalysisButton();

    /* pwd eyes (skip for SSH decrypt-eye handled inside SSH panel) */
    dynRoot.querySelectorAll('.ap-pwd-eye').forEach(function(b){b.addEventListener('click',function(){
        if(this.dataset.sshDecrypt==='1'){ return; }
        var i=document.getElementById(this.dataset.target);
        if(i)i.type=i.type==='password'?'text':'password';
    });});
    /* settings tabs */
    (function(){
        var tabs = dynRoot.querySelectorAll('.ap-settings-tab');
        var cards = dynRoot.querySelectorAll('.ap-settings-card');
        if(!tabs.length || !cards.length) return;
        function showSettingsSection(name){
            tabs.forEach(function(tab){
                var active = tab.dataset.settingsSection === name;
                tab.classList.toggle('active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            cards.forEach(function(card){
                card.classList.toggle('active', card.dataset.settingsCard === name);
            });
        }
        tabs.forEach(function(tab){
            tab.addEventListener('click', function(){
                showSettingsSection(this.dataset.settingsSection || 'email');
            });
        });
        var activeTab = dynRoot.querySelector('.ap-settings-tab.active');
        showSettingsSection(activeTab && activeTab.dataset ? (activeTab.dataset.settingsSection || 'email') : 'email');
    })();
    /* users search */
    var uSearch=dynRoot.querySelector('#ap-users-search'), uTable=dynRoot.querySelector('#ap-users-table');
    if(uSearch&&uTable) uSearch.addEventListener('input',function(){var q=this.value.toLowerCase();uTable.querySelectorAll('tbody tr').forEach(function(r){var e=(r.dataset.email||'').toLowerCase(),n=(r.dataset.name||'').toLowerCase();r.style.display=(e.includes(q)||n.includes(q))?'':'none';});});
    /* users field change (event delegation) */
    var uTbl=dynRoot.querySelector('#ap-users-table');
    if(uTbl){
        uTbl.addEventListener('change',function(e){var el=e.target;if(el.classList.contains('ap-user-field')&&el.dataset.email&&el.dataset.field)apSaveField(el.dataset.email,el.dataset.field,el.value);});
        uTbl.addEventListener('click',function(e){var btn=e.target.closest('.ap-verified-toggle');if(!btn)return;var nv=btn.dataset.value==='1'?'0':'1';btn.dataset.value=nv;var sp=btn.querySelector('span');if(sp){sp.className=nv==='1'?'ap-verified-yes':'ap-verified-no';sp.textContent=nv==='1'?'\u2713':'\u2717';}apSaveField(btn.dataset.email,'verified',nv);});
        function apCopyUserEmail(text){
            if(!text) return;
            var done=function(){
                if(typeof showTempNotification!=='undefined'){
                    showTempNotification('Email copied: ' + text,'success');
                }
            };
            if(navigator.clipboard && navigator.clipboard.writeText){
                navigator.clipboard.writeText(text).then(done).catch(function(){
                    try{
                        var ta=document.createElement('textarea');
                        ta.value=text;
                        ta.style.position='fixed';
                        ta.style.opacity='0';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                        done();
                    }catch(_){}
                });
            }else{
                try{
                    var ta=document.createElement('textarea');
                    ta.value=text;
                    ta.style.position='fixed';
                    ta.style.opacity='0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    done();
                }catch(_){}
            }
        }
        uTbl.addEventListener('click',function(e){
            var emailEl=e.target.closest('.ap-copy-email');
            if(!emailEl) return;
            e.preventDefault();
            apCopyUserEmail(emailEl.dataset.copyEmail||emailEl.textContent||'');
        });
        uTbl.addEventListener('keydown',function(e){
            var emailEl=e.target.closest('.ap-copy-email');
            if(!emailEl) return;
            if(e.key!=='Enter' && e.key!==' ') return;
            e.preventDefault();
            apCopyUserEmail(emailEl.dataset.copyEmail||emailEl.textContent||'');
        });
    }
    /* refresh users */
    var rfBtn=dynRoot.querySelector('#ap-refresh-users-btn'); if(rfBtn) rfBtn.addEventListener('click',window.refreshUsersList);

    /* ── Auto-poll: check for new users every 20 seconds ── */
    if(!window._apUserPollTimer){
        // Build initial snapshot of email list from current table
        function getApUserEmails(){
            if(!dynRoot) return [];
            return Array.from(dynRoot.querySelectorAll('#ap-users-table tbody tr[data-email]'))
                .map(function(r){return r.dataset.email;}).sort();
        }
        window._apUserSnapshot = getApUserEmails().join('|');
        window._apUserPollTimer = setInterval(function(){
            // Only poll when admin panel is open and users tab is active
            var overlay=document.getElementById('admin-panel-overlay');
            if(!overlay||!overlay.classList.contains('active')) return;
            var usersPanel=dynRoot&&dynRoot.querySelector('#ap-users.active');
            if(!usersPanel) return;
            // Silent AJAX check
            var fd=new FormData(); fd.append('get_users_table','1'); fd.append('ajax','1');
            fetch('/main',{method:'POST',body:fd})
                .then(function(r){return r.json();})
                .then(function(d){
                    if(!d.success) return;
                    // Parse email list from returned HTML
                    var tmp=document.createElement('tbody');
                    tmp.innerHTML=d.rows;
                    var newEmails=Array.from(tmp.querySelectorAll('tr[data-email]'))
                        .map(function(r){return r.dataset.email;}).sort().join('|');
                    if(newEmails !== window._apUserSnapshot){
                        window._apUserSnapshot = newEmails;
                        var tbody=dynRoot&&dynRoot.querySelector('#ap-users-table tbody');
                        if(tbody) tbody.innerHTML=d.rows;
                        apUpdateUserAnalytics();
                        if(typeof showTempNotification!=='undefined')
                            showTempNotification(<?php echo json_encode($translations['refresh_users']??'Users list updated'); ?>,'info');
                    }
                    // else: no change, do nothing silently
                }).catch(function(){/* silent fail */});
        }, 20000);
    }
    /* Update snapshot after every manual refresh */
    var _origRefresh=window.refreshUsersList;
    window.refreshUsersList=function(){
        _origRefresh();
        setTimeout(function(){
            if(dynRoot){
                window._apUserSnapshot=Array.from(dynRoot.querySelectorAll('#ap-users-table tbody tr[data-email]'))
                    .map(function(r){return r.dataset.email;}).sort().join('|');
            }
        }, 1000);
    };
    /* email */
    var seBtn=dynRoot.querySelector('#ap-save-email'), teBtn=dynRoot.querySelector('#ap-test-email'), eStatus=dynRoot.querySelector('#ap-email-status');
    function apEmailFormData(extra){
        var fd=new FormData();
        fd.set('google_client_id',    (dynRoot.querySelector('#ap-gcid')||{value:''}).value);
        fd.set('google_client_secret',(dynRoot.querySelector('#ap-gcsecret')||{value:''}).value);
        fd.set('github_client_id',    (dynRoot.querySelector('#ap-ghcid')||{value:''}).value);
        fd.set('github_client_secret',(dynRoot.querySelector('#ap-ghcsecret')||{value:''}).value);
        fd.set('smtp_host',           (dynRoot.querySelector('#ap-smtp-host')||{value:''}).value);
        fd.set('smtp_port',           (dynRoot.querySelector('#ap-smtp-port')||{value:587}).value);
        fd.set('smtp_username',       (dynRoot.querySelector('#ap-smtp-user')||{value:''}).value);
        fd.set('smtp_password',       (dynRoot.querySelector('#ap-smtp-pass')||{value:''}).value);
        fd.set('smtp_test_to',        (dynRoot.querySelector('#ap-smtp-test-to')||{value:''}).value);
        if(extra){Object.keys(extra).forEach(function(k){fd.set(k,extra[k]);});}
        fd.append('ajax','1');
        return fd;
    }
    if(seBtn) seBtn.addEventListener('click',function(){
        setStatus(eStatus,T.saving,'info');
        var fd=apEmailFormData({'save_email_settings':'1'});
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){setStatus(eStatus,d.success?('+ '+T.email_saved):('x '+(d.error||T.error)),d.success?'success':'error');if(d.success&&typeof showTempNotification!=='undefined')showTempNotification(T.email_saved,'success');}).catch(function(){setStatus(eStatus,'x '+T.error,'error');});
    });
    if(teBtn) teBtn.addEventListener('click',function(){
        setStatus(eStatus,'...','info');
        var fd=apEmailFormData({'test_smtp':'1'});
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){setStatus(eStatus,d.success?('+ '+(d.message||T.smtp_test_ok||'Test email sent')):('x '+(d.error||T.smtp_test_fail||T.error)),d.success?'success':'error');}).catch(function(){setStatus(eStatus,'x '+T.error,'error');});
    });
    /* telegram */
    var tgTok=dynRoot.querySelector('#ap-tg-token'), tgSt=dynRoot.querySelector('#ap-tg-status');
    function tgReq(a){var fd=new FormData();fd.append(a,'1');fd.append('ajax','1');fd.append('tg_bot_token',tgTok?tgTok.value.trim():'');var tw=dynRoot.querySelector('#ap-tg-webapp-url');fd.append('tg_webapp_base_url',tw?tw.value.trim():'');return fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).catch(function(){return{success:false,error:'Network error'};});}
    var tgSv=dynRoot.querySelector('#ap-tg-save'),tgTs=dynRoot.querySelector('#ap-tg-test'),tgGn=dynRoot.querySelector('#ap-tg-link-generate');
    if(tgSv) tgSv.addEventListener('click',function(){setStatus(tgSt,T.saving,'info');tgReq('save_tg_bot_config').then(function(d){setStatus(tgSt,d.success?('+ '+T.tg_saved):('x '+(d.error||T.error)),d.success?'success':'error');});});
    if(tgTs) tgTs.addEventListener('click',function(){setStatus(tgSt,'...','info');tgReq('test_tg_bot').then(function(d){setStatus(tgSt,d.success?('+ '+T.tg_test_ok):('x '+(d.error||T.tg_test_fail)),d.success?'success':'error');});});
    if(tgGn) tgGn.addEventListener('click',function(){setStatus(tgSt,T.saving,'info');var fd=new FormData();fd.append('generate_tg_link_token','1');fd.append('ajax','1');var sel=dynRoot.querySelector('#ap-tg-link-email');fd.append('tg_link_email',sel?sel.value:'');fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){var out=dynRoot.querySelector('#ap-tg-link-token');if(d.success&&out)out.value=d.token;setStatus(tgSt,d.success?('+ '+(d.token||'')):('x '+(d.error||T.error)),d.success?'success':'error');}).catch(function(){setStatus(tgSt,'x Network error','error');});});
    var dkSt=dynRoot.querySelector('#ap-docker-status'),dkSv=dynRoot.querySelector('#ap-save-docker'),dkFrame=dynRoot.querySelector('#ap-docker-frame');
    if(dkSv) dkSv.addEventListener('click',function(){
        setStatus(dkSt,T.saving,'info');
        var fd=new FormData();
        fd.append('save_docker_config','1');
        fd.append('ajax','1');
        fd.append('docker_token',(dynRoot.querySelector('#ap-docker-token')||{value:''}).value);
        fd.append('docker_username',(dynRoot.querySelector('#ap-docker-username')||{value:''}).value);
        fd.append('docker_password',(dynRoot.querySelector('#ap-docker-password')||{value:''}).value);
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            setStatus(dkSt,d.success?('+ '+T.docker_saved):('x '+(d.error||T.error)),d.success?'success':'error');
            if(d.success&&dkFrame){
                var u=new URL(dkFrame.src,window.location.origin);
                u.searchParams.set('docker_domain','localhost');
                u.searchParams.set('lang',<?php echo json_encode($language); ?>);
                u.searchParams.set('theme',<?php echo json_encode($themeForAppLink); ?>);
                u.searchParams.set('color',<?php echo json_encode($config['color'] ?? '#007bff'); ?>);
                dkFrame.src=u.toString();
            }
        }).catch(function(){setStatus(dkSt,'x '+T.error,'error');});
    });
    var orSt=dynRoot.querySelector('#ap-omniroute-status'),orSv=dynRoot.querySelector('#ap-save-omniroute');
    if(orSv) orSv.addEventListener('click',function(){
        setStatus(orSt,T.saving,'info');
        var fd=new FormData();
        fd.append('save_omniroute_oauth_config','1');
        fd.append('ajax','1');
        fd.append('omniroute_google_client_id',(dynRoot.querySelector('#ap-omniroute-gcid')||{value:''}).value.trim());
        fd.append('omniroute_google_client_secret',(dynRoot.querySelector('#ap-omniroute-gcsecret')||{value:''}).value.trim());
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            var ok=!!d.success, msg=d.message||d.error||T.error;
            setStatus(orSt,(ok?'+ ':'x ')+msg,ok?'success':'error');
            if(ok&&typeof showTempNotification!=='undefined')showTempNotification(msg,'success');
        }).catch(function(){setStatus(orSt,'x '+T.error,'error');});
    });
    var ooSt=dynRoot.querySelector('#ap-onlyoffice-status'),ooSv=dynRoot.querySelector('#ap-save-onlyoffice');
    if(ooSv) ooSv.addEventListener('click',function(){
        setStatus(ooSt,T.saving,'info');
        var fd=new FormData();
        fd.append('save_onlyoffice_config','1');
        fd.append('ajax','1');
        fd.append('onlyoffice_token',(dynRoot.querySelector('#ap-onlyoffice-token')||{value:''}).value.trim());
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            setStatus(ooSt,d.success?('+ '+T.onlyoffice_saved):('x '+(d.error||T.error)),d.success?'success':'error');
            if(d.success&&typeof showTempNotification!=='undefined')showTempNotification(T.onlyoffice_saved,'success');
        }).catch(function(){setStatus(ooSt,'x '+T.error,'error');});
    });
    var bkHost=dynRoot.querySelector('#ap-backup-host'),bkToken=dynRoot.querySelector('#ap-backup-token'),bkSt=dynRoot.querySelector('#ap-backup-creds-status'),bkSave=dynRoot.querySelector('#ap-save-backup-creds'),bkDel=dynRoot.querySelector('#ap-delete-backup-creds'),bkFrame=dynRoot.querySelector('#ap-backup-frame');
    function normalizeBackupTokenAdmin(v){var s=String(v||'').trim().replace(/^\uFEFF/,'');while(/^bearer\s+/i.test(s))s=s.replace(/^bearer\s+/i,'').trim();return s;}
    function reloadBackupFrame(){if(!bkFrame)return;try{var u=new URL(bkFrame.src,window.location.origin);u.searchParams.set('_ts',Date.now().toString());bkFrame.src=u.toString();}catch(e){bkFrame.src=bkFrame.src;}}
    function loadBackupCreds(){
        if(!bkToken)return;
        setStatus(bkSt,'Loading...','info');
        fetch('/backup_dashboard.php?config=load',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
            if(bkHost)bkHost.value=d.host||'http://127.0.0.1:8200';
            if(bkToken)bkToken.value=normalizeBackupTokenAdmin(d.token||'');
            setStatus(bkSt,d.token?'Loaded':'Not configured',d.token?'success':'info');
        }).catch(function(){setStatus(bkSt,'x '+T.error,'error');});
    }
    loadBackupCreds();
    var aiSt=dynRoot.querySelector('#ap-ai-config-status'),aiSave=dynRoot.querySelector('#ap-save-ai-config'),aiNewKey=dynRoot.querySelector('#ap-ai-new-key'),aiConfirmKey=dynRoot.querySelector('#ap-ai-confirm-key'),aiMatch=dynRoot.querySelector('#ap-ai-key-match');
    function checkAiKeyMatch(){
        if(!aiMatch||!aiNewKey||!aiConfirmKey)return;
        var nk=aiNewKey.value.trim(),ck=aiConfirmKey.value.trim();
        if(!nk&&!ck){aiMatch.style.display='none';return;}
        aiMatch.style.display='block';
        if(nk===ck){aiMatch.textContent='✓ '+(T.apiKeysMatch||'API keys match');aiMatch.style.color='#28a745';}
        else{aiMatch.textContent='✗ '+(T.apiKeysDoNotMatch||'API keys do not match');aiMatch.style.color='#dc3545';}
    }
    if(aiNewKey)aiNewKey.addEventListener('input',checkAiKeyMatch);
    if(aiConfirmKey)aiConfirmKey.addEventListener('input',checkAiKeyMatch);
    if(aiSave)aiSave.addEventListener('click',function(){
        var endpointEl=dynRoot.querySelector('#ap-ai-endpoint'),modelsEl=dynRoot.querySelector('#ap-ai-models'),defaultEl=dynRoot.querySelector('#ap-ai-default-model'),currentEl=dynRoot.querySelector('#ap-ai-current-key');
        var newKey=aiNewKey?aiNewKey.value.trim():'',confirmKey=aiConfirmKey?aiConfirmKey.value.trim():'';
        var hasKey=!!(newKey||confirmKey);
        if(hasKey){
            if(!newKey||!confirmKey){setStatus(aiSt,'x '+(T.apiKeysRequired||'Both fields required'),'error');return;}
            if(newKey!==confirmKey){setStatus(aiSt,'x '+(T.apiKeysDoNotMatch||'Keys do not match'),'error');return;}
            if(!/^(sk-|io-v2-)/.test(newKey)){setStatus(aiSt,'x '+(T.invalidApiKeyFormat||'Invalid key format'),'error');return;}
        }
        setStatus(aiSt,T.saving,'info');
        var fd=new FormData();
        fd.append('save_ai_config','1');fd.append('ajax','1');
        var searchEngineEl=dynRoot.querySelector('#ap-search-engine-url');
        fd.append('search_engine_url',searchEngineEl?searchEngineEl.value.trim():'');
        fd.append('api_endpoint',endpointEl?endpointEl.value.trim():'');
        fd.append('models',modelsEl?modelsEl.value.trim():'');
        fd.append('default_model',defaultEl?defaultEl.value.trim():'');
        if(hasKey)fd.append('api_key',newKey);
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            setStatus(aiSt,d.success?('+ '+(d.message||T.ai_config_saved)+(d.has_key===false?' · API key missing':'')):('x '+(d.error||T.error)),d.success&&d.has_key!==false?'success':'error');
            if(d.success){
                if(typeof showTempNotification!=='undefined')showTempNotification(d.message||T.ai_config_saved,'success');
                if(d.config){
                    if(endpointEl&&d.config.api_endpoint)endpointEl.value=d.config.api_endpoint;
                    if(modelsEl&&Array.isArray(d.config.models))modelsEl.value=d.config.models.join('\n');
                    if(defaultEl&&d.config.default_model)defaultEl.value=d.config.default_model;
                    if(currentEl&&d.config.api_key){currentEl.value=d.config.api_key;currentEl.type='password';}
                }
                if(aiNewKey)aiNewKey.value='';
                if(aiConfirmKey)aiConfirmKey.value='';
                if(aiMatch)aiMatch.style.display='none';
            }
        }).catch(function(){setStatus(aiSt,'x '+T.error,'error');});
    });
    if(bkSave)bkSave.addEventListener('click',function(){
        var host=(bkHost?bkHost.value:'').trim()||'http://127.0.0.1:8200';
        var token=normalizeBackupTokenAdmin(bkToken?bkToken.value:'');
        if(!/^https?:\/\//i.test(host)){setStatus(bkSt,'x Invalid Duplicati URL','error');return;}
        if(!token){setStatus(bkSt,'x Token is required','error');return;}
        setStatus(bkSt,T.saving,'info');
        fetch('/backup_dashboard.php?config=save',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({host:host,token:token})}).then(function(r){
            return r.json().then(function(d){d._ok=r.ok;return d;}).catch(function(){return{_ok:r.ok,error:'Save failed'};});
        }).then(function(d){
            setStatus(bkSt,d._ok?('+ '+T.backup_saved):('x '+(d.error||T.error)),d._ok?'success':'error');
            if(d._ok){if(typeof showTempNotification!=='undefined')showTempNotification(T.backup_saved,'success');reloadBackupFrame();}
        }).catch(function(){setStatus(bkSt,'x '+T.error,'error');});
    });
    if(bkDel)bkDel.addEventListener('click',function(){
        var _bkMsg=(T.backup_deleted||'Delete backup credentials')+'?';
        if(typeof fmConfirm==='function'){fmConfirm(_bkMsg,function(){doBkDel();});return;}
        if(!confirm(_bkMsg))return;
        doBkDel();
    });
    function doBkDel(){
        setStatus(bkSt,T.saving,'info');
        fetch('/backup_dashboard.php?config=delete',{method:'POST',credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
            if(bkHost)bkHost.value='';
            if(bkToken)bkToken.value='';
            setStatus(bkSt,d.ok?('+ '+T.backup_deleted):('x '+(d.error||T.error)),d.ok?'success':'error');
            if(d.ok)reloadBackupFrame();
        }).catch(function(){setStatus(bkSt,'x '+T.error,'error');});
    }
    var accGrid=dynRoot.querySelector('#ap-app-access-grid'),accSort=dynRoot.querySelector('#ap-app-access-sort'),accSav=dynRoot.querySelector('#ap-save-app-access'),accSt=dynRoot.querySelector('#ap-app-access-status');
    function apSortAccessTiles(){if(!accGrid||!accSort)return;var tiles=Array.from(accGrid.querySelectorAll('.ap-app-access-tile')),m=accSort.value;tiles.sort(function(a,b){if(m==='name')return(a.dataset.name||'').localeCompare(b.dataset.name||'');if(m==='role')return parseInt(a.dataset.role,10)-parseInt(b.dataset.role,10);return parseInt(b.dataset.visits,10)-parseInt(a.dataset.visits,10);});tiles.forEach(function(t){accGrid.appendChild(t);});}
    if(accSort) accSort.addEventListener('change',apSortAccessTiles);
    if(accGrid) accGrid.addEventListener('change',function(e){if(e.target&&e.target.classList.contains('ap-app-access-level')){var p=e.target.closest('.ap-app-access-tile');if(p)p.dataset.role=e.target.value;}});
    var worldMap=dynRoot.querySelector('#ap-world-map'),blockedCountries=[],vpnBlock=dynRoot.querySelector('#ap-block-vpn-traffic');
    function apLoadAmChartsMaps(){if(window._apAmChartsMapsPromise)return window._apAmChartsMapsPromise;var urls=['https://cdn.amcharts.com/lib/5/index.js','https://cdn.amcharts.com/lib/5/map.js','https://cdn.amcharts.com/lib/5/geodata/worldLow.js','https://cdn.amcharts.com/lib/5/themes/Animated.js'];window._apAmChartsMapsPromise=urls.reduce(function(p,u){return p.then(function(){return new Promise(function(res,rej){if(document.querySelector('script[src="'+u+'"]')){res();return;}var s=document.createElement('script');s.src=u;s.async=false;s.onload=res;s.onerror=function(){rej(new Error('Failed to load: '+u));};document.head.appendChild(s);});});},Promise.resolve());return window._apAmChartsMapsPromise;}
    function renderWorldMap(){if(!worldMap)return;var countries={},hostCountry=(worldMap.dataset.hostCountry||'').toUpperCase();try{countries=JSON.parse(worldMap.dataset.countries||'{}')||{};}catch(e){}try{blockedCountries=JSON.parse(worldMap.dataset.selected||'[]')||[];}catch(e){blockedCountries=[];}blockedCountries=blockedCountries.filter(function(c){return c!==hostCountry;});worldMap.innerHTML='';apLoadAmChartsMaps().then(function(){if(!window.am5||!window.am5map||!window.am5geodata_worldLow)return;if(worldMap._apMapRoot)worldMap._apMapRoot.dispose();var root=am5.Root.new(worldMap);worldMap._apMapRoot=root;root.setThemes([am5themes_Animated.new(root)]);var chart=root.container.children.push(am5map.MapChart.new(root,{panX:'translateX',panY:'translateY',wheelX:'zoom',wheelY:'zoom',pinchZoom:true,maxZoomLevel:32,maxPanOut:.15,projection:am5map.geoMercator()}));var series=chart.series.push(am5map.MapPolygonSeries.new(root,{geoJSON:am5geodata_worldLow,exclude:['AQ']}));var normal=am5.color(0x64748b),active=am5.color(0xef4444),host=am5.color(0x94a3b8),hover=am5.color(0x3b82f6),stroke=am5.color(0xffffff);function apCountryCode(target){return target.dataItem&&target.dataItem.dataContext&&target.dataItem.dataContext.id;}function apply(target){var code=apCountryCode(target);target.set('fill',code===hostCountry?host:(blockedCountries.indexOf(code)!==-1?active:normal));target.set('fillOpacity',code===hostCountry ? .45 : 1);}series.mapPolygons.template.setAll({tooltipText:'{name}',interactive:true,fill:normal,stroke:stroke,strokeWidth:.5});series.mapPolygons.template.adapters.add('tooltipText',function(text,target){var code=apCountryCode(target),label=countries[code]||text;return code===hostCountry?label+' (hosting)':label;});series.mapPolygons.template.events.on('click',function(ev){var target=ev.target,code=apCountryCode(target);if(!code||code===hostCountry)return;var i=blockedCountries.indexOf(code);if(i===-1)blockedCountries.push(code);else blockedCountries.splice(i,1);apply(target);});series.mapPolygons.template.events.on('pointerover',function(ev){var code=apCountryCode(ev.target);ev.target.set('fill',code===hostCountry?host:(blockedCountries.indexOf(code)!==-1?active:hover));});series.mapPolygons.template.events.on('pointerout',function(ev){apply(ev.target);});series.events.on('datavalidated',function(){series.mapPolygons.each(apply);chart.goHome(0);});chart.set('zoomControl',am5map.ZoomControl.new(root,{}));}).catch(function(){worldMap.textContent='Map library failed to load';});}
    renderWorldMap();
    if(accSav) accSav.addEventListener('click',function(){setStatus(accSt,T.saving,'info');var lv={};if(accGrid) accGrid.querySelectorAll('.ap-app-access-level').forEach(function(s){lv[s.getAttribute('data-folder')]=parseInt(s.value,10);});var fd=new FormData();fd.append('save_app_access_levels','1');fd.append('ajax','1');fd.append('levels_json',JSON.stringify(lv));fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(!d.success)throw new Error(d.error||T.error);var fd2=new FormData();fd2.append('save_app_access_security','1');fd2.append('ajax','1');fd2.append('countries_json',JSON.stringify(blockedCountries));fd2.append('block_vpn',vpnBlock&&vpnBlock.checked?'1':'0');return fetch('/main',{method:'POST',body:fd2}).then(function(r){return r.json();});}).then(function(d){setStatus(accSt,d.success?('+ '+T.app_access_saved):('x '+(d.error||T.error)),d.success?'success':'error');if(d.success&&typeof showTempNotification!=='undefined')showTempNotification(T.app_access_saved,'success');}).catch(function(e){setStatus(accSt,'x '+(e&&e.message?e.message:T.error),'error');});});
    /* stats save button */
    var ssBtn=dynRoot.querySelector('#ap-save-stats'), sSt=dynRoot.querySelector('#ap-stats-status');
    if(ssBtn&&!ssBtn._h){ssBtn._h=true;ssBtn.addEventListener('click',function(){setStatus(sSt,T.saving,'info');var fd=new FormData();fd.append('save_stats_data','1');fd.append('stats_data',JSON.stringify(apStatsData));fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){setStatus(sSt,d.success?('\u2713 '+T.stats_saved):('\u2717 '+(d.error||T.error)),d.success?'success':'error');if(d.success&&typeof showTempNotification!=='undefined')showTempNotification(T.stats_saved,'success');}).catch(function(){setStatus(sSt,T.error,'error');});});}
    var rcBtn=dynRoot.querySelector('#ap-refresh-chart');
    if(rcBtn&&!rcBtn._h){rcBtn._h=true;rcBtn.addEventListener('click',function(){initApChartRangePicker();renderApStatsChart();});}
    /* SSH panel */
    wireSshPanel();
    /* JSON panel */
    wireJsonPanel();
}

/* ── Users ── */
function apSaveField(email,field,value){
    var st=dynRoot&&dynRoot.querySelector('#ap-roles-status');setStatus(st,T.saving,'info');
    var fd=new FormData();fd.append('save_user_roles','1');fd.append('ajax','1');
    if(field==='role')     fd.append('user_roles['+email+']',value);
    if(field==='language') fd.append('user_languages['+email+']',value);
    if(field==='theme')    fd.append('user_themes['+email+']',value);
    if(field==='color')    fd.append('user_colors['+email+']',value);
    if(field==='verified') fd.append('user_verified['+email+']',value);
    fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
        setStatus(st,d.message||(d.success?'\u2713 Saved':T.error),d.success?'success':'error');
        if(field==='role'||field==='verified') apUpdateUserAnalytics();
    }).catch(function(){setStatus(st,T.error+': network','error');});
}

function apUpdateUserAnalytics(){
    if(!dynRoot)return;
    var rows=dynRoot.querySelectorAll('#ap-users-table tbody tr[data-email]');
    var total=rows.length,verified=0,admins=0,managers=0,guests=0,pending=0;
    rows.forEach(function(r){
        var pend=r.querySelector('span[style*="Pending"]')||r.querySelector('span[style*="#ff9800"]');
        if(pend){pending++;return;}
        var vBtn=r.querySelector('.ap-verified-toggle');
        if(vBtn&&vBtn.dataset.value==='1')verified++;
        var roleSel=r.querySelector('select[data-field="role"]');
        if(roleSel){
            var v=parseInt(roleSel.value)||0;
            if(v===2)admins++;else if(v===1)managers++;else guests++;
        }else{
            var lockSpan=r.querySelector('span[style*="font-weight:700"]');
            if(lockSpan)admins++;else guests++;
        }
    });
    var s=function(id,v){var e=dynRoot.querySelector(id);if(e)e.textContent=v;};
    s('#ap-analytics-total',total);s('#ap-analytics-verified',verified);
    s('#ap-analytics-admins',admins);s('#ap-analytics-managers',managers);
    s('#ap-analytics-guests',guests);s('#ap-analytics-pending',pending);
}

window.refreshUsersList=function(){
    var btn=dynRoot&&dynRoot.querySelector('#ap-refresh-users-btn');
    var tbody=dynRoot&&dynRoot.querySelector('#ap-users-table tbody');
    if(btn){btn.disabled=true;btn.style.opacity='0.6';}
    var fd=new FormData();fd.append('get_users_table','1');fd.append('ajax','1');
    fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
        if(d.success&&tbody)tbody.innerHTML=d.rows;
        if(btn){btn.disabled=false;btn.style.opacity='1';}
        apUpdateUserAnalytics();
        if(typeof showTempNotification!=='undefined')showTempNotification(T.refresh_users,'success');
    }).catch(function(){if(btn){btn.disabled=false;btn.style.opacity='1';}});
};

window.apOpenDeleteUser=function(email,name){
    var dm=document.getElementById('delete-user-modal');if(!dm)return;
    var t=document.getElementById('delete-user-title'),ed=document.getElementById('delete-user-email-display'),te=document.getElementById('delete-user-target-email');
    var dob=document.getElementById('do-delete-user-btn'),dfc=document.getElementById('delete-files-checkbox'),dss=document.getElementById('delete-slider-section'),cs=document.getElementById('confirm-slider');
    var sm=document.getElementById('delete-user-scanning'),fl=document.getElementById('delete-user-files-loaded');
    var ps=document.getElementById('delete-user-scan-progress'),pb=document.getElementById('scan-progress-bar');
    var pp=document.getElementById('scan-progress-percent'),pf=document.getElementById('scan-progress-files');
    var tr=document.getElementById('scan-time-remaining'),sp=document.getElementById('scan-speed');

    if(t)t.textContent='Delete: '+name;if(ed)ed.textContent=email;if(te)te.value=email;
    if(dob)dob.disabled=true;if(dfc)dfc.checked=false;if(dss)dss.style.display='none';if(cs)cs.value=0;
    if(sm)sm.style.display='none';
    if(ps)ps.style.display='block';
    if(fl)fl.style.display='none';
    dm.style.display='flex';

    // Start progress animation
    var startTime=Date.now();
    var totalEstimate=3000;
    var progressInterval=setInterval(function(){
        var elapsed=Date.now()-startTime;
        var progress=Math.min((elapsed/totalEstimate)*100,95);

        if(pb)pb.style.width=progress+'%';
        if(pp)pp.textContent=Math.round(progress)+'%';

        var remaining=Math.max(0,Math.ceil((totalEstimate-elapsed)/1000));
        if(tr){
            if(remaining>0){
                tr.textContent='<?php echo my_htmlspecialchars($translations['time_remaining'] ?? 'Time remaining'); ?>: '+remaining+'s';
            }else{
                tr.textContent='<?php echo my_htmlspecialchars($translations['almost_done'] ?? 'Almost done...'); ?>';
            }
        }

        var scannedCount=Math.floor((progress/100)*100);
        if(pf)pf.textContent='<?php echo my_htmlspecialchars($translations['scanning_progress'] ?? 'Scanning'); ?>... '+scannedCount+' files';

        if(sp){
            var filesPerSec=(scannedCount/(elapsed/1000)).toFixed(0);
            sp.textContent='<?php echo my_htmlspecialchars($translations['scan_speed'] ?? 'Speed'); ?>: ~'+filesPerSec+' files/sec';
        }
    },100);

    var fd=new FormData();fd.append('scan_user_files','1');fd.append('ajax','1');fd.append('user_email',email);
    fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
        clearInterval(progressInterval);

        if(pb)pb.style.width='100%';
        if(pp)pp.textContent='100%';
        if(tr)tr.textContent='<?php echo my_htmlspecialchars($translations['complete'] ?? 'Complete'); ?>!';

        setTimeout(function(){
            if(sm)sm.style.display='none';
            if(ps)ps.style.display='none';
            if(fl)fl.style.display='block';
            var c=document.getElementById('delete-user-file-count'),s=document.getElementById('delete-user-total-size'),l=document.getElementById('delete-user-files-list');
            if(c)c.textContent=(data.files||[]).length+' file(s)';if(s)s.textContent=data.total_size_human;
            if(l)l.innerHTML=(data.files||[]).slice(0,20).map(function(f){return '<div style="font-size:12px;opacity:.6;padding:1px 0;">'+esc(f.name)+' ('+esc(f.app)+')</div>';}).join('');
            if(dob)dob.disabled=false;
        },300);
    }).catch(function(){
        clearInterval(progressInterval);
        if(sm)sm.style.display='none';
        if(ps)ps.style.display='none';
        if(fl)fl.style.display='block';
        if(dob)dob.disabled=false;
    });
};

window.apCloseUserMenus=function(){
    document.querySelectorAll('.ap-user-actions-menu.open').forEach(function(menu){menu.classList.remove('open');});
};
document.addEventListener('click',function(e){
    if(!e.target.closest('.ap-user-actions-menu')) window.apCloseUserMenus();
});
if(dynRoot){
    dynRoot.addEventListener('click',function(e){
        var btn=e.target.closest('.ap-user-menu-btn');
        if(!btn) return;
        e.stopPropagation();
        var menu=btn.closest('.ap-user-actions-menu');
        if(!menu) return;
        var wasOpen=menu.classList.contains('open');
        window.apCloseUserMenus();
        if(!wasOpen) menu.classList.add('open');
    });
}

function apGenerateSecurePassword(length){
    if(typeof window.generateSecurePassword==='function') return window.generateSecurePassword(length||12);
    var letters='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz', digits='23456789', all=letters+digits;
    var minLen=Math.max(<?php echo PASSWORD_MIN_LENGTH; ?>, length||12), buf=new Uint32Array(minLen);
    crypto.getRandomValues(buf);
    var chars=[letters[buf[0]%letters.length], digits[buf[1]%digits.length]];
    for(var i=2;i<minLen;i++) chars.push(all[buf[i]%all.length]);
    for(var j=chars.length-1;j>0;j--){var k=buf[j]%(j+1), t=chars[j]; chars[j]=chars[k]; chars[k]=t;}
    return chars.join('');
}
function apResetPwdSlider(val){
    var fill=document.getElementById('reset-pwd-slider-fill');
    var label=document.getElementById('reset-pwd-slider-label');
    var btn=document.getElementById('do-reset-user-password-btn');
    if(fill) fill.style.width=val+'%';
    if(label){
        if(val>=98){ label.textContent='✓'; label.style.color='var(--primary-color)'; }
        else { label.textContent='➜'; label.style.color=''; }
    }
    if(btn) btn.disabled=val<98;
}
window.apOpenResetPassword=function(email,name){
    var modal=document.getElementById('reset-user-password-modal');
    if(!modal) return;
    var title=document.getElementById('reset-user-password-title');
    var emailDisplay=document.getElementById('reset-user-password-email-display');
    var target=document.getElementById('reset-user-password-target-email');
    var pwdInput=document.getElementById('reset-user-password-value');
    var formSection=document.getElementById('reset-password-form-section');
    var successSection=document.getElementById('reset-password-success');
    var slider=document.getElementById('reset-pwd-confirm-slider');
    var submitBtn=document.getElementById('do-reset-user-password-btn');
    if(title) title.textContent=(<?php echo json_encode($translations['reset_user_password'] ?? 'Reset password'); ?>)+': '+name;
    if(emailDisplay) emailDisplay.textContent=email;
    if(target) target.value=email;
    if(formSection) formSection.style.display='block';
    if(successSection) successSection.style.display='none';
    if(pwdInput) pwdInput.value=apGenerateSecurePassword(12);
    if(slider){ slider.value=0; apResetPwdSlider(0); }
    if(submitBtn) submitBtn.disabled=true;
    modal.style.display='flex';
};
(function(){
    var modal=document.getElementById('reset-user-password-modal');
    if(!modal) return;
    var closeBtn=document.getElementById('close-reset-user-password');
    var regenBtn=document.getElementById('regen-reset-user-password');
    var pwdInput=document.getElementById('reset-user-password-value');
    var slider=document.getElementById('reset-pwd-confirm-slider');
    var submitBtn=document.getElementById('do-reset-user-password-btn');
    var copyBtn=document.getElementById('copy-reset-user-password');
    var successValue=document.getElementById('reset-password-success-value');
    if(closeBtn) closeBtn.addEventListener('click',function(){ modal.style.display='none'; });
    modal.addEventListener('click',function(e){ if(e.target===modal) modal.style.display='none'; });
    if(regenBtn) regenBtn.addEventListener('click',function(){ if(pwdInput) pwdInput.value=apGenerateSecurePassword(12); });
    if(slider){
        slider.addEventListener('input',function(){ apResetPwdSlider(parseInt(this.value,10)||0); });
        slider.addEventListener('change',function(){
            if(parseInt(this.value,10)<98){ this.value=0; apResetPwdSlider(0); }
        });
    }
    if(submitBtn) submitBtn.addEventListener('click',function(){
        var email=(document.getElementById('reset-user-password-target-email')||{}).value||'';
        var password=pwdInput?pwdInput.value:'';
        if(!email||!password||submitBtn.disabled) return;
        submitBtn.disabled=true;
        var fd=new FormData();
        fd.append('reset_user_password','1');
        fd.append('ajax','1');
        fd.append('user_email',email);
        fd.append('new_password',password);
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            if(d.success){
                var formSection=document.getElementById('reset-password-form-section');
                var successSection=document.getElementById('reset-password-success');
                var successMsg=document.getElementById('reset-password-success-msg');
                if(formSection) formSection.style.display='none';
                if(successSection) successSection.style.display='block';
                if(successMsg) successMsg.textContent=d.message||T.password_reset_admin_success;
                if(successValue) successValue.value=d.password||password;
                if(typeof showTempNotification!=='undefined') showTempNotification(d.message||T.password_reset_admin_success,'success');
            }else{
                submitBtn.disabled=false;
                if(typeof showTempNotification!=='undefined') showTempNotification(d.message||T.error,'error');
            }
        }).catch(function(){
            submitBtn.disabled=false;
            if(typeof showTempNotification!=='undefined') showTempNotification(T.network_error||'Network error','error');
        });
    });
    if(copyBtn) copyBtn.addEventListener('click',function(){
        var text=successValue?successValue.value:'';
        if(!text) return;
        var done=function(){ if(typeof showTempNotification!=='undefined') showTempNotification(T.password_copied,'success'); };
        if(navigator.clipboard&&navigator.clipboard.writeText){
            navigator.clipboard.writeText(text).then(done).catch(function(){
                try{var ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);done();}catch(_){}
            });
        }else{
            try{var ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);done();}catch(_){}
        }
    });
})();

/* ── Handsontable stats ── */
function initApStats(){
    var container=dynRoot&&dynRoot.querySelector('#ap-stats-hot-table');
    if(!container)return;
    if(typeof window.Handsontable==='undefined'){
        apEnsureStatsDeps().then(initApStats).catch(function(e){var st=dynRoot&&dynRoot.querySelector('#ap-stats-status');setStatus(st,e.message||T.error,'error');});
        return;
    }
    if(apHotInst){apHotInst.destroy();apHotInst=null;}
    var allD=new Set();Object.keys(apStatsData).forEach(function(id){Object.keys(apStatsData[id]||{}).forEach(function(d){allD.add(d);});});
    var sdates=Array.from(allD).sort(),appIds=Object.keys(apStatsData).filter(function(id){return id!==null&&id!==''&&id!==undefined;});
    var data=sdates.map(function(date){var row=[date];appIds.forEach(function(id){row.push((apStatsData[id]&&apStatsData[id][date])?apStatsData[id][date]:0);});return row;});
    if(!data.length)data.push([''].concat(appIds.map(function(){return 0;})));
    var lastRow=-1,lastCol=-1;
    // Apply primary color as CSS variable for selection highlight
    var apPrimaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#007bff';
    var apSelBg = apPrimaryColor + '25'; // ~15% opacity
    container.style.setProperty('--hot-selection-bg', apSelBg);

    // Theme: set table wrapper bg so rows inherit correctly
    var apIsDark = getComputedStyle(document.documentElement).getPropertyValue('--bg-color').trim() === '#0f1419';

    apHotInst=new window.Handsontable(container,{
        data:data,colHeaders:['Date'].concat(appIds.map(function(id){return id||'';})),rowHeaders:true,height:'100%',width:'100%',
        licenseKey:'non-commercial-and-evaluation',contextMenu:true,stretchH:'all',
        themeName: apIsDark ? 'ht-theme-main-dark-auto' : 'ht-theme-main',
        afterSelectionEnd:function(r1,c1,r2,c2){lastRow=Math.min(r1,r2);lastCol=Math.min(c1,c2);},
        afterChange:function(changes,source){
            if(source!=='edit'||!changes)return;
            changes.forEach(function(ch){
                var row=ch[0],col=ch[1],ov=ch[2],nv=ch[3];
                if(col===0){if(ov&&nv&&ov!==nv){appIds.forEach(function(id){if(apStatsData[id]&&apStatsData[id][ov]!==undefined){apStatsData[id][nv]=apStatsData[id][ov];delete apStatsData[id][ov];}});if(data[row])data[row][0]=nv;}return;}
                var date=data[row]?data[row][0]:null,appId=appIds[col-1];if(!appId||!date)return;
                if(!apStatsData[appId])apStatsData[appId]={};
                if(nv===0||nv===''||nv===null){delete apStatsData[appId][date];if(!Object.keys(apStatsData[appId]).length)delete apStatsData[appId];}
                else apStatsData[appId][date]=parseInt(nv)||0;
            });
        }
    });
    var drBtn=dynRoot&&dynRoot.querySelector('#ap-del-stats-row'),dcBtn=dynRoot&&dynRoot.querySelector('#ap-del-stats-col');
    if(drBtn&&!drBtn._h){drBtn._h=true;drBtn.addEventListener('click',function(){if(!apHotInst)return;var sel=apHotInst.getSelected(),ri=(sel&&sel.length)?sel[0][0]:lastRow;if(ri<0)return;var dv=apHotInst.getDataAtCell(ri,0);if(dv)Object.keys(apStatsData).forEach(function(id){if(apStatsData[id]&&apStatsData[id][dv]!==undefined)delete apStatsData[id][dv];});apHotInst.alter('remove_row',ri);renderApStatsChart();});}
    if(dcBtn&&!dcBtn._h){dcBtn._h=true;dcBtn.addEventListener('click',function(){if(!apHotInst)return;var sel=apHotInst.getSelected(),ci=(sel&&sel.length&&sel[0][1]>=1)?sel[0][1]:lastCol;if(ci<1)return;var ai=appIds[ci-1];if(ai)delete apStatsData[ai];apHotInst.alter('remove_col',ci);renderApStatsChart();});}
    // Inject dynamic style for HOT selection color (uses current primary color)
    (function(){
        var pc = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#007bff';
        var styleId = 'ap-hot-sel-style';
        var existing = document.getElementById(styleId);
        if(existing) existing.remove();
        var st = document.createElement('style');
        st.id = styleId;
        // Selection highlight
        st.textContent = [
            '#ap-stats-hot-table .handsontable td.area{background-color:'+pc+'22!important;}',
            '#ap-stats-hot-table .handsontable td.current{background-color:'+pc+'33!important;}',
            '#ap-stats-hot-table .handsontable .wtBorder.current{background-color:'+pc+'!important;}',
            '#ap-stats-hot-table .handsontable .wtBorder.area{background-color:'+pc+'!important;}',
            '#ap-stats-hot-table .handsontable .htFillingHandle{background-color:'+pc+'!important;}',
        ].join('');
        document.head.appendChild(st);
    })();
    // Initial chart render
    setTimeout(renderApStatsChart, 100);
}

/* ── Admin Stats Chart ── */
var apStatsChart = null;
var apChartRange = 'year'; // current quick-range preset (default to year-based)
var apChartDateFrom = '';  // custom from date (YYYY-MM-DD)
var apChartDateTo   = '';  // custom to date (YYYY-MM-DD)

function getApThemeColors(){
    var isDark = document.documentElement.style.getPropertyValue('--bg-color')==='#0f1419'
        || window.getComputedStyle(document.documentElement).getPropertyValue('--bg-color').trim()==='#0f1419';
    return {
        text:  isDark ? 'rgba(220,220,220,0.85)' : 'rgba(50,50,50,0.8)',
        grid:  isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)',
        bg:    isDark ? '#333' : '#fff'
    };
}

function apGetFilteredDates(){
    var dataToUse = apGetFilteredData();
    var allDates=new Set();
    Object.keys(dataToUse).filter(function(id){return id!==null&&id!==''&&id!==undefined;}).forEach(function(id){Object.keys(dataToUse[id]||{}).forEach(function(d){allDates.add(d);});});
    var sorted=Array.from(allDates).sort();
    if(!sorted.length) return sorted;

    // When year is selected (not day-based range), show all dates in filtered data
    if(apChartRange === 'year' || apChartRange === 'custom'){
        // For 'year' mode, just return all dates from filtered data
        // For 'custom' mode, apply custom date range from inputs
        if(apChartRange === 'custom'){
            var fromEl = dynRoot&&dynRoot.querySelector('#ap-chart-date-from');
            var toEl   = dynRoot&&dynRoot.querySelector('#ap-chart-date-to');
            var from   = fromEl ? fromEl.value : '';
            var to     = toEl   ? toEl.value   : '';
            if(from) sorted = sorted.filter(function(dt){ return dt >= from; });
            if(to)   sorted = sorted.filter(function(dt){ return dt <= to;   });
        }
        return sorted;
    }

    // Apply quick-range preset (7d, 30d, 90d)
    if(apChartRange !== 'all'){
        var days = parseInt(apChartRange)||30;
        var cutoff = sorted[sorted.length-1]; // last date in data
        // subtract N days from the last date
        var d = new Date(cutoff); d.setDate(d.getDate() - days + 1);
        var fromStr = d.toISOString().split('T')[0];
        sorted = sorted.filter(function(dt){ return dt >= fromStr; });
        return sorted;
    }

    return sorted;
}

function initApChartRangePicker(){
    // Wire range preset buttons
    if(dynRoot){
        dynRoot.querySelectorAll('.ap-chart-range-btn').forEach(function(btn){
            if(btn._apR) return; btn._apR=true;
            btn.addEventListener('click',function(){
                dynRoot.querySelectorAll('.ap-chart-range-btn').forEach(function(b){b.classList.remove('active');});
                this.classList.add('active');
                apChartRange = this.dataset.range;
                // Clear custom date inputs when using preset
                if(apChartRange !== 'custom'){
                    var f=dynRoot.querySelector('#ap-chart-date-from'),t=dynRoot.querySelector('#ap-chart-date-to');
                    // Don't clear values — user may switch back
                }
                renderApStatsChart();
            });
        });
        // Wire date inputs — switch to custom range when either is changed
        var fromEl = dynRoot.querySelector('#ap-chart-date-from');
        var toEl   = dynRoot.querySelector('#ap-chart-date-to');
        function onDateChange(){
            apChartRange = 'custom';
            dynRoot.querySelectorAll('.ap-chart-range-btn').forEach(function(b){b.classList.remove('active');});
            renderApStatsChart();
        }
        if(fromEl && !fromEl._apR){ fromEl._apR=true; fromEl.addEventListener('change', onDateChange); }
        if(toEl   && !toEl._apR)  { toEl._apR=true;   toEl.addEventListener('change',   onDateChange); }
        // Pre-fill date inputs with min/max dates from filtered data
        var dataToUse = apGetFilteredData();
        var allDates=new Set();
        Object.keys(dataToUse).forEach(function(id){Object.keys(dataToUse[id]||{}).forEach(function(d){allDates.add(d);});});
        var sorted=Array.from(allDates).sort();
        if(sorted.length && fromEl && !fromEl.value) fromEl.value = sorted[0];
        if(sorted.length && toEl   && !toEl.value)   toEl.value   = sorted[sorted.length-1];

        // Initialize: year mode is default, no day buttons active
        if(apChartRange === 'year'){
            dynRoot.querySelectorAll('.ap-chart-range-btn').forEach(function(b){b.classList.remove('active');});
        }
    }
}

function renderApStatsChart(){
    var canvas = dynRoot&&dynRoot.querySelector('#ap-stats-canvas');
    if(!canvas) return;
    if(typeof window.Chart === 'undefined'){
        apEnsureStatsDeps().then(renderApStatsChart).catch(function(e){var st=dynRoot&&dynRoot.querySelector('#ap-stats-status');setStatus(st,e.message||T.error,'error');});
        return;
    }
    // Use filtered data if year is selected
    var dataToUse = apGetFilteredData();
    var filteredDates = apGetFilteredDates();
    var appIds = Object.keys(dataToUse).filter(function(id){return id!==null&&id!==''&&id!==undefined;});
    var colors = ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40','#E74C3C','#2ECC71','#1ABC9C','#F39C12'];
    var datasets = appIds.map(function(id,i){
        return {
            label: id||'',
            data:  filteredDates.map(function(d){ return (dataToUse[id]&&dataToUse[id][d])?dataToUse[id][d]:0; }),
            borderColor: colors[i%colors.length],
            backgroundColor: colors[i%colors.length]+'30',
            fill: false, tension: 0.35, pointRadius: filteredDates.length < 60 ? 3 : 1,
            borderWidth: 2
        };
    }).filter(function(ds){ return ds.data.some(function(v){ return v>0; }); });

    var tc = getApThemeColors();
    var chartCfg = {
        type: 'line',
        data: { labels: filteredDates, datasets: datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 250 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: true, position: 'top', labels: { color: tc.text, boxWidth: 10, font: { size: 10 } } },
                title:  { display: false },
                tooltip: { enabled: true }
            },
            scales: {
                x: { ticks: { color: tc.text, maxRotation: 45, font: { size: 9 }, maxTicksLimit: 12 }, grid: { color: tc.grid } },
                y: { beginAtZero: true, ticks: { color: tc.text, font: { size: 9 }, maxTicksLimit: 6 }, grid: { color: tc.grid } }
            }
        }
    };
    if(apStatsChart){ apStatsChart.destroy(); apStatsChart=null; }
    apStatsChart = new window.Chart(canvas, chartCfg);
    // Hook table edits to re-render
    if(apHotInst && !apHotInst._chartHooked){
        apHotInst._chartHooked = true;
        apHotInst.addHook('afterChange', function(changes,source){ if(source==='edit') setTimeout(renderApStatsChart,200); });
    }
    // Init range picker controls (idempotent)
    initApChartRangePicker();
}

/* ── Analysis Mode & Year Selector ── */
var apAnalysisVisible = false;
var apCurrentYear = '';
var apPieChart = null;
var apAvailableYears = []; // Store available years from backend

function apPopulateYearSelector(){
    var sel = dynRoot && dynRoot.querySelector('#ap-chart-year-select');
    if(!sel) return;

    // Use available years from backend (not from stats data)
    var years = apAvailableYears.slice().sort().reverse();
    var currentYear = new Date().getFullYear().toString();

    // Clear and add options - NO "All" option, only specific years
    sel.innerHTML = '';

    years.forEach(function(y){
        var opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        if(y === currentYear) opt.selected = true;
        sel.appendChild(opt);
    });

    // Set default to current year if available, otherwise most recent year
    if(years.length > 0){
        // Preserve currently selected year if it's still available, otherwise use current year or most recent
        if(!apCurrentYear || !years.includes(apCurrentYear)){
            apCurrentYear = years.includes(currentYear) ? currentYear : years[0];
        }
        sel.value = apCurrentYear;
        // Set year mode as default and deactivate day buttons
        apChartRange = 'year';
        if(dynRoot){
            dynRoot.querySelectorAll('.ap-chart-range-btn').forEach(function(b){
                b.classList.remove('active');
            });
        }
        // Auto-filter on load
        apFilterByYear();
    }

    // Wire change event
    if(!sel._apYearWire){
        sel._apYearWire = true;
        sel.addEventListener('change', function(){
            apCurrentYear = this.value;
            // Switch to year mode and deactivate day range buttons
            apChartRange = 'year';
            if(dynRoot){
                dynRoot.querySelectorAll('.ap-chart-range-btn').forEach(function(b){
                    b.classList.remove('active');
                });
            }
            // Reload data from backend for selected year
            reloadAdminStats(apCurrentYear);
        });
    }
}

function apFilterByYear(){
    if(!apCurrentYear) return;
    // Filter apStatsData to only show selected year
    // We'll create a filtered view without modifying original data
    window.apStatsFiltered = {};
    Object.keys(apStatsData).forEach(function(id){
        window.apStatsFiltered[id] = {};
        Object.keys(apStatsData[id]||{}).forEach(function(d){
            if(d.startsWith(apCurrentYear)){
                window.apStatsFiltered[id][d] = apStatsData[id][d];
            }
        });
    });
}

function apGetFilteredData(){
    // Always return filtered data if year is selected
    if(apCurrentYear && window.apStatsFiltered && Object.keys(window.apStatsFiltered).length > 0){
        return window.apStatsFiltered;
    }
    // Fallback to all data if no year selected (shouldn't happen)
    return apStatsData;
}

function apToggleAnalysis(){
    var analysisEl = dynRoot && dynRoot.querySelector('#ap-stats-analysis');
    var toggleBtn = dynRoot && dynRoot.querySelector('#ap-toggle-analysis');
    if(!analysisEl || !toggleBtn) return;

    apAnalysisVisible = !apAnalysisVisible;
    analysisEl.style.display = apAnalysisVisible ? '' : 'none';

    // Update button text with SVG icon
    var svgIcon = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px;"><path d="M11 13H7"/><path d="M19 9h-4"/><path d="M3 3v16a2 2 0 0 0 2 2h16"/><rect x="15" y="5" width="4" height="12" rx="1"/><rect x="7" y="8" width="4" height="9" rx="1"/></svg>';
    toggleBtn.innerHTML = apAnalysisVisible ?
        svgIcon + (T.stats_hide_analysis || 'Hide Analysis') :
        svgIcon + (T.stats_show_analysis || 'Show Analysis');

    if(apAnalysisVisible){
        apFilterByYear();
        renderApAnalysis();
    }
}

function renderApAnalysis(){
    var data = apGetFilteredData();
    var summaryEl = dynRoot && dynRoot.querySelector('#ap-analysis-summary');
    if(!summaryEl) return;

    // Calculate totals per app
    var appTotals = {};
    var allDates = new Set();
    var dailyTotals = {};

    Object.keys(data).forEach(function(id){
        appTotals[id] = 0;
        Object.keys(data[id]||{}).forEach(function(d){
            appTotals[id] += data[id][d] || 0;
            allDates.add(d);
            dailyTotals[d] = (dailyTotals[d] || 0) + (data[id][d] || 0);
        });
    });

    var totalVisits = Object.values(appTotals).reduce(function(a,b){ return a+b; }, 0);
    var uniqueDays = allDates.size;
    var avgDaily = uniqueDays > 0 ? Math.round(totalVisits / uniqueDays) : 0;

    // Find peak day
    var peakDay = null;
    var peakDayVisits = 0;
    Object.keys(dailyTotals).forEach(function(d){
        if(dailyTotals[d] > peakDayVisits){
            peakDayVisits = dailyTotals[d];
            peakDay = d;
        }
    });

    // Summary cards
    summaryEl.innerHTML = '' +
        '<div style="background:var(--group-block-bg);border-radius:8px;padding:14px;border:1px solid var(--border-color);text-align:center;">' +
            '<div style="font-size:11px;opacity:.6;margin-bottom:4px;">' + (T.stats_total_visits || 'Total Visits') + '</div>' +
            '<div style="font-size:24px;font-weight:700;">' + totalVisits.toLocaleString() + '</div>' +
        '</div>' +
        '<div style="background:var(--group-block-bg);border-radius:8px;padding:14px;border:1px solid var(--border-color);text-align:center;">' +
            '<div style="font-size:11px;opacity:.6;margin-bottom:4px;">' + (T.stats_avg_daily_visits || 'Avg Daily Visits') + '</div>' +
            '<div style="font-size:24px;font-weight:700;">' + avgDaily.toLocaleString() + '</div>' +
        '</div>' +
        '<div style="background:var(--group-block-bg);border-radius:8px;padding:14px;border:1px solid var(--border-color);text-align:center;">' +
            '<div style="font-size:11px;opacity:.6;margin-bottom:4px;">' + (T.stats_peak_day || 'Peak Day') + '</div>' +
            '<div style="font-size:18px;font-weight:700;">' + (peakDay || 'N/A') + '</div>' +
            '<div style="font-size:11px;opacity:.6;">' + (peakDayVisits || 0).toLocaleString() + ' visits</div>' +
        '</div>' +
        '<div style="background:var(--group-block-bg);border-radius:8px;padding:14px;border:1px solid var(--border-color);text-align:center;">' +
            '<div style="font-size:11px;opacity:.6;margin-bottom:4px;">Active Days</div>' +
            '<div style="font-size:24px;font-weight:700;">' + uniqueDays.toLocaleString() + '</div>' +
        '</div>';

    // Render pie chart
    renderApPieChart(appTotals, totalVisits);

    // Render popular apps
    renderApPopularApps(appTotals, totalVisits);

    // Render peak days
    renderApPeakDays(dailyTotals);
}

function renderApPieChart(appTotals, totalVisits){
    var canvas = dynRoot && dynRoot.querySelector('#ap-pie-canvas');
    if(!canvas) return;

    if(typeof window.Chart === 'undefined'){
        apEnsureStatsDeps().then(function(){renderApPieChart(appTotals,totalVisits);}).catch(function(e){console.error('[AdminPanel]',e);});
        return;
    }

    var sorted = Object.entries(appTotals)
        .filter(function(e){ return e[1] > 0; })
        .sort(function(a,b){ return b[1] - a[1]; });

    // Group small apps into "Other"
    var threshold = totalVisits * 0.02; // 2% threshold
    var mainApps = [];
    var otherCount = 0;

    sorted.forEach(function(entry){
        if(entry[1] >= threshold || mainApps.length < 6){
            mainApps.push(entry);
        } else {
            otherCount += entry[1];
        }
    });

    if(otherCount > 0) mainApps.push(['Other', otherCount]);

    var labels = mainApps.map(function(e){ return e[0]; });
    var values = mainApps.map(function(e){ return e[1]; });
    var colors = ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40','#E74C3C','#2ECC71','#1ABC9C','#F39C12'];

    if(apPieChart){ apPieChart.destroy(); apPieChart = null; }

    var tc = getApThemeColors();
    apPieChart = new window.Chart(canvas, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors.slice(0, labels.length),
                borderWidth: 2,
                borderColor: tc.bg
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: tc.text, boxWidth: 12, font: { size: 11 } }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx){
                            var val = ctx.parsed;
                            var pct = totalVisits > 0 ? ((val / totalVisits) * 100).toFixed(1) : 0;
                            return ctx.label + ': ' + val.toLocaleString() + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
}

function renderApPopularApps(appTotals, totalVisits){
    var container = dynRoot && dynRoot.querySelector('#ap-popular-apps');
    if(!container) return;

    var sorted = Object.entries(appTotals)
        .filter(function(e){ return e[1] > 0; })
        .sort(function(a,b){ return b[1] - a[1]; });

    var top5 = sorted.slice(0, 5);
    var bottom5 = sorted.slice(-5).reverse();

    var colors = ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF'];

    var html = '<div style="margin-bottom:12px;">';
    html += '<div style="font-size:12px;font-weight:600;margin-bottom:6px;color:#2ecc71;">✓ Top 5</div>';

    if(top5.length === 0){
        html += '<div style="font-size:11px;opacity:.5;">' + (T.stats_no_data || 'No data available') + '</div>';
    } else {
        top5.forEach(function(entry, i){
            var pct = totalVisits > 0 ? ((entry[1] / totalVisits) * 100).toFixed(1) : 0;
            html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">' +
                '<span style="width:18px;height:18px;border-radius:50%;background:' + colors[i % colors.length] + ';flex-shrink:0;"></span>' +
                '<span style="flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + entry[0] + '</span>' +
                '<span style="font-size:11px;font-weight:600;">' + entry[1].toLocaleString() + '</span>' +
                '<span style="font-size:10px;opacity:.6;">' + pct + '%</span>' +
            '</div>';
        });
    }

    html += '</div>';
    html += '<div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border-color);">';
    html += '<div style="font-size:12px;font-weight:600;margin-bottom:6px;color:#e74c3c;">✗ Least Popular</div>';

    if(bottom5.length === 0){
        html += '<div style="font-size:11px;opacity:.5;">' + (T.stats_no_data || 'No data available') + '</div>';
    } else {
        bottom5.forEach(function(entry, i){
            var pct = totalVisits > 0 ? ((entry[1] / totalVisits) * 100).toFixed(1) : 0;
            html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">' +
                '<span style="width:18px;height:18px;border-radius:50%;background:#95a5a6;flex-shrink:0;"></span>' +
                '<span style="flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + entry[0] + '</span>' +
                '<span style="font-size:11px;font-weight:600;">' + entry[1].toLocaleString() + '</span>' +
                '<span style="font-size:10px;opacity:.6;">' + pct + '%</span>' +
            '</div>';
        });
    }

    html += '</div>';
    container.innerHTML = html;
}

function renderApPeakDays(dailyTotals){
    var container = dynRoot && dynRoot.querySelector('#ap-peak-days');
    if(!container) return;

    var sorted = Object.entries(dailyTotals)
        .sort(function(a,b){ return b[1] - a[1]; })
        .slice(0, 10);

    if(sorted.length === 0){
        container.innerHTML = '<div style="font-size:11px;opacity:.5;">' + (T.stats_no_data || 'No data available') + '</div>';
        return;
    }

    var maxVisits = sorted[0][1];

    var html = '';
    sorted.forEach(function(entry, i){
        var date = entry[0];
        var visits = entry[1];
        var pct = maxVisits > 0 ? ((visits / maxVisits) * 100) : 0;
        var dayName = new Date(date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });

        html += '<div style="margin-bottom:8px;">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">' +
                '<span style="font-size:12px;font-weight:500;">' + dayName + '</span>' +
                '<span style="font-size:11px;font-weight:600;">' + visits.toLocaleString() + '</span>' +
            '</div>' +
            '<div style="height:6px;background:var(--border-color);border-radius:3px;overflow:hidden;">' +
                '<div style="height:100%;width:' + pct + '%;background:linear-gradient(90deg, var(--primary-color), color-mix(in srgb, var(--primary-color) 70%, #fff));border-radius:3px;"></div>' +
            '</div>' +
        '</div>';
    });

    container.innerHTML = html;
}

/* Wire analysis toggle button */
function apWireAnalysisButton(){
    var btn = dynRoot && dynRoot.querySelector('#ap-toggle-analysis');
    if(btn && !btn._apAnalysisWire){
        btn._apAnalysisWire = true;
        btn.addEventListener('click', apToggleAnalysis);
    }
}

/* ── File Manager panel ── */
function fmAlert(msg){
    var md=document.getElementById('fm-alert-modal');
    var msgEl=document.getElementById('fm-alert-msg');
    var ok=document.getElementById('fm-alert-ok');
    if(!md||!msgEl||!ok){
        if(typeof console!=='undefined'&&console.error)console.error(msg);
        return;
    }
    if(typeof md._fmAlertCleanup==='function'){md._fmAlertCleanup();}
    msgEl.textContent=msg==null?'':String(msg);
    md.style.display='flex';
    md.classList.add('fm-alert-open');
    md.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';
    function cleanup(){
        md.style.display='none';
        md.classList.remove('fm-alert-open');
        md.setAttribute('aria-hidden','true');
        document.body.style.overflow='';
        document.removeEventListener('keydown',onEsc);
        md.removeEventListener('click',onBackdrop);
        ok.removeEventListener('click',onOk);
        md._fmAlertCleanup=null;
    }
    function onEsc(e){if(e.key==='Escape'){e.preventDefault();cleanup();}}
    function onBackdrop(e){if(e.target===md)cleanup();}
    function onOk(){cleanup();}
    md._fmAlertCleanup=cleanup;
    document.addEventListener('keydown',onEsc,true);
    md.addEventListener('click',onBackdrop);
    ok.addEventListener('click',onOk);
    requestAnimationFrame(function(){try{ok.focus();}catch(er){}});
}
function fmConfirm(msg,onOk){ const md=document.getElementById('fm-confirm-modal'); if(!md){if(confirm(msg))onOk();return;} document.getElementById('fm-confirm-msg').textContent=msg; md.style.display='flex'; const ok=document.getElementById('fm-confirm-ok'),can=document.getElementById('fm-confirm-cancel'); function cleanup(){md.style.display='none';ok.onclick=null;can.onclick=null;} ok.onclick=function(){cleanup();onOk();}; can.onclick=cleanup; }
function fmMediaOpen(url, name){ const md=document.getElementById('fm-media-modal'); const mc=document.getElementById('fm-media-content'); const mn=document.getElementById('fm-media-name'); if(!md||!mc) return; const ext=(name||url).split('.').pop().toLowerCase(); mc.innerHTML=''; if(['jpg','jpeg','png','gif','webp','bmp','svg','ico'].includes(ext)){ const img=document.createElement('img'); img.src=url; img.style.cssText='max-width:90vw;max-height:82vh;border-radius:6px;box-shadow:0 4px 24px rgba(0,0,0,.5);'; mc.appendChild(img); } else if(['mp4','webm','ogg','mov'].includes(ext)){ const vid=document.createElement('video'); vid.src=url; vid.controls=true; vid.autoplay=true; vid.style.cssText='max-width:90vw;max-height:82vh;border-radius:6px;'; mc.appendChild(vid); } else if(['mp3','wav','ogg','flac','aac'].includes(ext)){ const aud=document.createElement('audio'); aud.src=url; aud.controls=true; aud.autoplay=true; mc.appendChild(aud); } else { mc.innerHTML='<div style="color:#fff;font-size:14px;">Preview not available</div>'; } if(mn) mn.textContent=name||''; md.style.display='flex'; }
document.addEventListener('DOMContentLoaded',function(){ const mc=document.getElementById('fm-media-close'); if(mc) mc.addEventListener('click',function(){ const md=document.getElementById('fm-media-modal'); if(md){md.style.display='none'; const v=md.querySelector('video,audio'); if(v)v.pause();} }); const mm=document.getElementById('fm-media-modal'); if(mm) mm.addEventListener('click',function(e){ if(e.target===mm){mm.style.display='none'; const v=mm.querySelector('video,audio'); if(v)v.pause();} }); });

function wireJsonPanel(){
    /* State */
    var fmCurrentPath = '/';
    var fmHistory = ['/'];
    var fmHistoryIdx = 0;
    var fmAllEntries = [];
    var fmSortKey = 'name';
    var fmSortAsc = true;
    var fmCurrentFilePath = null;
    var fmMonacoEditor = null;
    var fmEditMode = false;
    var fmDialogCallback = null;
    var fmTouchModifiers = {ctrl:false, shift:false, alt:false};
    var IMAGE_EXT  = ['jpg','jpeg','png','gif','webp','bmp','svg','ico'];
    var VIDEO_EXT  = ['mp4','webm','ogg','mov','avi','mkv'];
    var AUDIO_EXT  = ['mp3','wav','ogg','flac','aac','m4a'];
    var BINARY_EXT = IMAGE_EXT.concat(VIDEO_EXT).concat(AUDIO_EXT).concat(['pdf','zip','gz','tar','exe','dll','bin']);
    var DB_EXT     = ['db','sqlite','sqlite3','db3'];

    /* DOM refs */
    var listView   = dynRoot.querySelector('#fm-list-view');
    var editorView = dynRoot.querySelector('#fm-editor-view');
    var tbody      = dynRoot.querySelector('#fm-tbody');
    var breadcrumb = dynRoot.querySelector('#fm-breadcrumb');
    var statusBar  = dynRoot.querySelector('#fm-status-bar');
    var btnBack    = dynRoot.querySelector('#fm-btn-back');
    var btnHome    = dynRoot.querySelector('#fm-btn-home');
    var btnRefresh = dynRoot.querySelector('#fm-btn-refresh');
    var btnMkdir   = dynRoot.querySelector('#fm-btn-mkdir');
    var btnMkfile  = dynRoot.querySelector('#fm-btn-mkfile');
    var btnUpload  = dynRoot.querySelector('#fm-btn-upload');
    var uploadInput= dynRoot.querySelector('#fm-upload-input');
    var searchInput= dynRoot.querySelector('#fm-search');
    var thEls      = dynRoot.querySelectorAll('#fm-table thead th[data-sort]');
    var edBack     = dynRoot.querySelector('#fm-editor-back');
    var edFilename = dynRoot.querySelector('#fm-editor-filename');
    var edSave     = dynRoot.querySelector('#fm-editor-save');
    var edCopy     = dynRoot.querySelector('#fm-editor-copy');
    var edDownload = dynRoot.querySelector('#fm-editor-download');
    var edModeV    = dynRoot.querySelector('#fm-mode-view');
    var edModeE    = dynRoot.querySelector('#fm-mode-edit');
    var edStatus   = dynRoot.querySelector('#fm-editor-status');
    var touchBar   = dynRoot.querySelector('#fm-touch-bar');
    var dlg        = dynRoot.querySelector('#fm-dialog');
    var dlgTitle   = dynRoot.querySelector('#fm-dialog-title');
    var dlgInput   = dynRoot.querySelector('#fm-dialog-input');
    var dlgOk      = dynRoot.querySelector('#fm-dialog-ok');
    var dlgCancel  = dynRoot.querySelector('#fm-dialog-cancel');
    var dlgError   = dynRoot.querySelector('#fm-dialog-error');
    /* ── Touch detection ── */
    var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    if(isTouch && touchBar) touchBar.style.display='flex';

    /* Touch modifier key simulation */
    if(touchBar){
        touchBar.querySelectorAll('.fm-touch-key').forEach(function(btn){
            btn.addEventListener('click', function(){
                var key = this.dataset.key;
                if(key==='ctrl'||key==='shift'||key==='alt'){
                    fmTouchModifiers[key] = !fmTouchModifiers[key];
                    this.style.background = fmTouchModifiers[key] ? 'var(--primary-color)' : 'var(--bg-color)';
                    this.style.color = fmTouchModifiers[key] ? '#fff' : 'var(--text-color)';
                } else if(key==='esc'){
                    if(fmMonacoEditor) fmMonacoEditor.trigger('keyboard','editor.action.cancelSelection',[]);
                    closeFmDialog();
                } else if(key==='tab' && fmMonacoEditor){
                    fmMonacoEditor.trigger('keyboard','type',{text:'\t'});
                } else if(key==='home' && fmMonacoEditor){
                    fmMonacoEditor.trigger('keyboard','cursorHome',[]);
                } else if(key==='end' && fmMonacoEditor){
                    fmMonacoEditor.trigger('keyboard','cursorEnd',[]);
                } else if(key==='selall' && fmMonacoEditor){
                    fmMonacoEditor.trigger('keyboard','editor.action.selectAll',[]);
                }
            });
        });
    }

    /* ── helpers ── */
    function showList(){ if(listView)listView.style.display=''; if(editorView)editorView.style.display='none'; }
    function showEditor(){ if(listView)listView.style.display='none'; if(editorView)editorView.style.display=''; }

    function extToLang(ext){
        var m={js:'javascript',ts:'typescript',json:'json',php:'php',html:'html',htm:'html',
               css:'css',scss:'scss',less:'less',xml:'xml',yaml:'yaml',yml:'yaml',
               md:'markdown',sh:'shell',bash:'shell',py:'python',rb:'ruby',java:'java',
               c:'c',cpp:'cpp',cs:'csharp',go:'go',rs:'rust',sql:'sql',txt:'plaintext',
               ini:'ini',env:'ini',toml:'ini',conf:'ini',log:'shell',htaccess:'apache'};
        return m[ext]||'plaintext';
    }

    function extIcon(type, ext, isSymlink){
        if(isSymlink) return '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="color:#4ecdc4;flex-shrink:0;margin-right:7px;vertical-align:middle;"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>';
        if(type==='dir') return '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="color:#f0a500;flex-shrink:0;margin-right:7px;vertical-align:middle;"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>';
        // SQLite db icon
        if(DB_EXT.includes(ext)) return '<svg width="14" height="14" viewBox="0 0 24 24" fill="#5bc0de" style="flex-shrink:0;margin-right:7px;vertical-align:middle;"><path d="M12 3C7.58 3 4 4.79 4 7v10c0 2.21 3.59 4 8 4s8-1.79 8-4V7c0-2.21-3.58-4-8-4zm0 2c3.87 0 6 1.47 6 2s-2.13 2-6 2-6-1.47-6-2 2.13-2 6-2zM6 9.7c1.47.82 3.57 1.3 6 1.3s4.53-.48 6-1.3V11c0 .53-2.13 2-6 2s-6-1.47-6-2V9.7zm0 4c1.47.82 3.57 1.3 6 1.3s4.53-.48 6-1.3V15c0 .53-2.13 2-6 2s-6-1.47-6-2v-1.3zm0 4c1.47.82 3.57 1.3 6 1.3s4.53-.48 6-1.3V19c0 .53-2.13 2-6 2s-6-1.47-6-2v-1.3z"/></svg>';
        var colorMap={json:'#f0db4f',js:'#f7df1e',ts:'#3178c6',php:'#8892bf',html:'#e34c26',
                      css:'#264de4',scss:'#cc6699',py:'#3572a5',sql:'#e38c00',
                      md:'#083fa1',sh:'#4eaa25',xml:'#e34c26',txt:'#aaa',log:'#888',
                      conf:'#aaa',ini:'#aaa',env:'#aaa',
                      jpg:'#e67e22',jpeg:'#e67e22',png:'#2ecc71',gif:'#9b59b6',svg:'#2ecc71',
                      mp4:'#e74c3c',mp3:'#1abc9c',pdf:'#c0392b',zip:'#95a5a6'};
        var col = colorMap[ext]||'var(--primary-color)';
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="'+col+'" style="flex-shrink:0;margin-right:7px;vertical-align:middle;"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13z"/></svg>';
    }

    /* ── sort & filter ── */
    function sortedFiltered(){
        var q=(searchInput?searchInput.value:'').toLowerCase().trim();
        var list=fmAllEntries.slice();
        if(q) list=list.filter(function(e){return e.name.toLowerCase().indexOf(q)!==-1;});
        list.sort(function(a,b){
            if(a.type!==b.type) return a.type==='dir'?-1:1;
            var av=a[fmSortKey]||'',bv=b[fmSortKey]||'';
            if(fmSortKey==='size'){av=+av||0;bv=+bv||0;return fmSortAsc?av-bv:bv-av;}
            var r=String(av).localeCompare(String(bv),undefined,{sensitivity:'base'});
            return fmSortAsc?r:-r;
        });
        return list;
    }

    /* ── render table ── */
    function renderEntries(){
        if(!tbody) return;
        var list=sortedFiltered();
        if(!list.length){tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;opacity:.4;font-size:13px;font-family:sans-serif;">'+(T.fm_no_items||'No items found')+'</td></tr>';if(statusBar)statusBar.textContent='';return;}
        var tdBase='padding:7px 10px;border-bottom:1px solid var(--border-color);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
        var rows='';
        list.forEach(function(e){
            var isDir=e.type==='dir',isLink=e.is_symlink||false;
            var isMedia=IMAGE_EXT.includes(e.ext)||VIDEO_EXT.includes(e.ext)||AUDIO_EXT.includes(e.ext);
            var nameCell='<div style="display:flex;align-items:center;">'+extIcon(e.type,e.ext,isLink)
                +'<span style="font-family:\'Courier New\',monospace;font-size:12px;color:'+(isDir?'var(--text-color)':'var(--primary-color)')+';">'+esc(e.name)+(isLink?' <span style="opacity:.5;font-size:10px;">[link]</span>':'')+'</span></div>';
            var sizeCell=isDir?'<span class="fm-dir-size" data-path="'+escA(e.path)+'" style="opacity:.35;font-size:11px;cursor:pointer;" title="Click to calculate">\u2014</span>':esc(e.size_h||'0 B');
            var renameBtn = '<button type="button" class="ap-btn ap-btn-secondary fm-act-rename" data-path="'+escA(e.path)+'" data-name="'+escA(e.name)+'" style="padding:2px 7px;font-size:11px;" title="Rename"><svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button>';
            var dlBtn = isDir
                ? '<span style="opacity:.35;font-size:11px;">\u2014</span>'
                : '<button type="button" class="ap-btn ap-btn-secondary fm-act-dl" data-path="'+escA(e.path)+'" data-name="'+escA(e.name)+'" style="padding:2px 7px;font-size:11px;" title="Download"><svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M5 20h14v-2H5v2zm7-18l-5 5h3v4h4v-4h3l-5-5z"/></svg></button>';
            var deleteBtn = '<button type="button" class="ap-btn ap-btn-danger fm-act-delete" data-path="'+escA(e.path)+'" data-name="'+escA(e.name)+'" style="padding:2px 7px;font-size:11px;" title="Delete">\uD83D\uDDD1</button>';
            rows+='<tr class="fm-row" data-path="'+escA(e.path)+'" data-type="'+e.type+'" data-ext="'+escA(e.ext)+'" data-name="'+escA(e.name)+'" data-media="'+isMedia+'" style="cursor:pointer;">'
                +'<td style="'+tdBase+'padding-left:12px;">'+nameCell+'</td>'
                +'<td style="'+tdBase+'font-size:12px;opacity:.55;">'+sizeCell+'</td>'
                +'<td style="'+tdBase+'font-size:11px;opacity:.45;font-family:\'Courier New\',monospace;">'+esc(e.mtime||'')+'</td>'
                +'<td style="'+tdBase+'padding:5px 6px;">'+renameBtn+'</td>'
                +'<td style="'+tdBase+'padding:5px 6px;">'+dlBtn+'</td>'
                +'<td style="'+tdBase+'padding:5px 6px;">'+deleteBtn+'</td>'
                +'</tr>';
        });
        tbody.innerHTML=rows;

        tbody.querySelectorAll('tr.fm-row').forEach(function(row){
            row.addEventListener('mouseenter',function(){
                this.querySelectorAll('td').forEach(function(td){td.style.background='color-mix(in srgb, var(--primary-color) 16%, transparent)';});
            });
            row.addEventListener('mouseleave',function(){
                this.querySelectorAll('td').forEach(function(td){td.style.background='';});
            });
            row.addEventListener('click',function(e){
                if(e.target.closest('button,.fm-dir-size')) return;
                if(row.dataset.type==='dir'){ navigateTo(row.dataset.path); }
                else if(row.dataset.media==='true'){ fmMediaOpen(buildFmServeUrl(row.dataset.path, false), row.dataset.name); }
                else { openFileEditor(row.dataset.path, row.dataset.ext); } // 'file' and 'symlink' both open editor
            });
        });
        tbody.querySelectorAll('.fm-dir-size').forEach(function(el){
            el.addEventListener('click',function(e){
                e.stopPropagation(); var p=this.dataset.path,self=this; self.textContent='\u2026';
                var fd=new FormData(); fd.append('fm_dir_size','1'); fd.append('ajax','1'); fd.append('path',p);
                fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){self.textContent=d.success?(d.size_h+' ('+d.files+'f)'):'?'; self.style.cursor='default';});
            });
        });
        tbody.querySelectorAll('.fm-act-rename').forEach(function(btn){
            btn.addEventListener('click',function(e){
                e.stopPropagation();
                var p=this.dataset.path,n=this.dataset.name;
                showFmDialog(T.fm_rename||'Rename',n,T.fm_new_name||'New name',function(v){doRename(p,v);});
            });
        });
        tbody.querySelectorAll('.fm-act-dl').forEach(function(btn){
            btn.addEventListener('click',function(e){ e.stopPropagation(); doDownload(this.dataset.path, this.dataset.name); });
        });
        tbody.querySelectorAll('.fm-act-delete').forEach(function(btn){
            btn.addEventListener('click',function(e){
                e.stopPropagation(); var p=this.dataset.path,n=this.dataset.name;
                fmConfirm((T.fm_confirm_del||'Are you sure you want to delete')+' "'+n+'"?',function(){doDelete(p);});
            });
        });
        if(statusBar) statusBar.textContent=list.length+' item'+(list.length===1?'':'s');
    }

    /* ── breadcrumb ── */
    function renderBreadcrumb(crumbs){
        if(!breadcrumb) return;
        if(!crumbs||!crumbs.length){breadcrumb.textContent='/';return;}
        var html='';
        crumbs.forEach(function(c,i){
            if(i>0) html+=' <span style="opacity:.4;">/</span> ';
            if(i<crumbs.length-1) html+='<a href="#" style="color:var(--primary-color);text-decoration:none;" data-path="'+escA(c.path)+'">'+esc(c.name)+'</a>';
            else html+='<span>'+esc(c.name)+'</span>';
        });
        breadcrumb.innerHTML=html;
        breadcrumb.querySelectorAll('a[data-path]').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();navigateTo(this.dataset.path);});});
    }

    /* ── navigation ── */
    function navigateTo(path){
        fmHistory=fmHistory.slice(0,fmHistoryIdx+1);
        if(fmHistory[fmHistoryIdx]!==path){fmHistory.push(path);fmHistoryIdx=fmHistory.length-1;}
        loadDir(path);
    }
    function loadDir(path){
        fmCurrentPath=path||'/';
        if(tbody) tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;opacity:.4;font-size:13px;font-family:sans-serif;">'+(T.fm_loading||'Loading\u2026')+'</td></tr>';
        var fd=new FormData(); fd.append('fm_list_dir','1'); fd.append('ajax','1'); fd.append('path',fmCurrentPath);
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            if(!d.success){if(tbody)tbody.innerHTML='<tr><td colspan="6" style="padding:20px;color:#f85149;font-family:sans-serif;">'+(d.error||'Error')+'</td></tr>';return;}
            fmCurrentPath=d.path; fmAllEntries=d.entries||[];
            renderBreadcrumb(d.breadcrumb||[]); renderEntries();
            if(btnBack) btnBack.disabled=fmHistoryIdx<=0;
        }).catch(function(){if(tbody)tbody.innerHTML='<tr><td colspan="6" style="padding:20px;color:#f85149;font-family:sans-serif;">Network error</td></tr>';});
    }

    /* ── file editor ── */
    function openFileEditor(path, ext){
        fmCurrentFilePath=path;
        if(edFilename) edFilename.textContent=path;
        if(edStatus){edStatus.className='ap-status';edStatus.style.display='none';}
        showEditor();
        setFmEditorMode('view','');
        var fd=new FormData(); fd.append('fm_get_file','1'); fd.append('ajax','1'); fd.append('path',path);
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            if(!d.success){setStatus(edStatus,'x '+(d.error||'Error'),'error');return;}
            if(d.is_binary){setStatus(edStatus,(T.fm_binary||'Binary file.'),'error');setFmEditorMode('view','/* Binary / non-text file */');return;}
            setFmEditorMode('view',d.content,d.ext||ext||'txt');
        }).catch(function(){setStatus(edStatus,'x Network error','error');});
    }

    function setFmEditorMode(mode,content,ext){
        fmEditMode=(mode==='edit');
        if(edModeV) edModeV.classList.toggle('active',!fmEditMode);
        if(edModeE) edModeE.classList.toggle('active',fmEditMode);
        if(edSave) edSave.style.display=fmEditMode?'inline-flex':'none';
        var edEl=dynRoot.querySelector('#fm-monaco-editor');
        var theme=getTheme();
        var lang=ext?extToLang(ext):(fmCurrentFilePath?extToLang(fmCurrentFilePath.split('.').pop().toLowerCase()):'plaintext');
        function build(ic){
            if(!edEl) return;
            if(fmMonacoEditor){
                if(ic!==undefined) fmMonacoEditor.setValue(ic);
                fmMonacoEditor.updateOptions({readOnly:!fmEditMode});
                monaco.editor.setTheme(theme);
                try{monaco.editor.setModelLanguage(fmMonacoEditor.getModel(),lang);}catch(e){}
            } else {
                fmMonacoEditor = monaco.editor.create(edEl, {
                    value: ic || '',
                    language: lang,
                    theme: theme,
                    readOnly: !fmEditMode,
                    minimap: {
                        enabled: true,
                        showSlider: 'always', // Always show the thumb on the minimap
                        size: 'fit' // Minimap size
                    },
                    scrollbar: {
                        vertical: 'hidden', // Hide the vertical scrollbar
                        horizontal: 'auto', // Horizontal can be kept or also hidden
                        verticalScrollbarSize: 0, // Set size 0 for full hiding
                        handleMouseWheel: true // Allow mouse wheel scrolling
                    },
                    fontSize: 13,
                    lineNumbers: 'on',
                    scrollBeyondLastLine: true, // Allow scrolling past the last line
                    scrollBeyondLastColumn: 3, // Extra horizontal scrolling (optional)
                    automaticLayout: true,
                    wordWrap: 'off',
                    tabSize: 2,
                    formatOnPaste: true,
                    // Better touch support
                    multiCursorModifier: 'ctrlCmd',
                    selectionClipboard: false
                });
                // Touch: make text selectable like a normal textarea
                if(isTouch){
                    var edContainer=edEl.querySelector('.monaco-editor');
                    if(edContainer){edContainer.style.webkitUserSelect='text';edContainer.style.userSelect='text';}
                }
            }
        }
        if(typeof monaco!=='undefined') build(content);
        else ensureMonaco(function(){build(content);});
    }

    new MutationObserver(function(){if(fmMonacoEditor&&typeof monaco!=='undefined')monaco.editor.setTheme(getTheme());}).observe(document.documentElement,{attributes:true,attributeFilter:['style']});

    /* ── Copy to clipboard ── */
    if(edCopy) edCopy.addEventListener('click',function(){
        var raw=fmMonacoEditor?fmMonacoEditor.getValue():'';
        if(navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(raw).then(function(){
                if(typeof showTempNotification!=='undefined') showTempNotification(T.copied,'success');
            }).catch(function(){fmAlert('Copy failed');});
        } else {
            var ta=document.createElement('textarea'); ta.value=raw; ta.style.position='fixed'; ta.style.opacity='0';
            document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
            if(typeof showTempNotification!=='undefined') showTempNotification(T.copied,'success');
        }
    });

    /* ── Download (streaming via hidden anchor) ── */
    function buildFmServeUrl(path, asDownload){
        var base = '/index.php?fm_serve=1&path='+encodeURIComponent(path||'');
        if(asDownload) base += '&download=1';
        return base;
    }
    function doDownload(path, name){
        var url=buildFmServeUrl(path, true);
        window.location.assign(url);
    }
    if(edDownload) edDownload.addEventListener('click',function(){
        if(fmCurrentFilePath) doDownload(fmCurrentFilePath, fmCurrentFilePath.split('/').pop());
    });

    /* ── Upload (chunked) ── */
    var CHUNK_SIZE = 512 * 1024; // 512 KB chunks
    function uploadFile(file){
        var totalChunks=Math.max(1,Math.ceil(file.size/CHUNK_SIZE));
        var prog=dynRoot.querySelector('#fm-upload-progress');
        var bar=dynRoot.querySelector('#fm-upload-bar');
        var lbl=dynRoot.querySelector('#fm-upload-label');
        if(prog) prog.style.display='';
        function sendChunk(i){
            if(lbl) lbl.textContent='Uploading '+file.name+' ('+(i+1)+'/'+totalChunks+')…';
            if(bar) bar.style.width=Math.round((i/totalChunks)*100)+'%';
            var start=i*CHUNK_SIZE, end=Math.min(start+CHUNK_SIZE,file.size);
            var chunk=file.slice(start,end);
            var fd=new FormData();
            fd.append('fm_upload','1'); fd.append('ajax','1');
            fd.append('path',fmCurrentPath); fd.append('filename',file.name);
            fd.append('chunk_index',i); fd.append('total_chunks',totalChunks);
            fd.append('chunk',chunk,'chunk');
            fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
                if(!d.success){if(prog)prog.style.display='none'; fmAlert(d.error||'Upload error'); return;}
                if(d.done){
                    if(bar) bar.style.width='100%';
                    setTimeout(function(){if(prog)prog.style.display='none';if(bar)bar.style.width='0%';},800);
                    loadDir(fmCurrentPath);
                    if(typeof showTempNotification!=='undefined') showTempNotification(file.name+' — '+T.fm_saved,'success');
                } else { sendChunk(i+1); }
            }).catch(function(){if(prog)prog.style.display='none'; fmAlert('Upload network error');});
        }
        sendChunk(0);
    }
    if(btnUpload) btnUpload.addEventListener('click',function(){if(uploadInput)uploadInput.click();});
    if(uploadInput) uploadInput.addEventListener('change',function(){
        var files=this.files; if(!files||!files.length) return;
        Array.from(files).forEach(function(f){ uploadFile(f); });
        uploadInput.value='';
    });
    // Drag & drop upload
    if(listView){
        listView.addEventListener('dragover',function(e){e.preventDefault();listView.style.outline='2px dashed var(--primary-color)';});
        listView.addEventListener('dragleave',function(){listView.style.outline='';});
        listView.addEventListener('drop',function(e){e.preventDefault();listView.style.outline='';var files=e.dataTransfer.files;if(files)Array.from(files).forEach(uploadFile);});
    }

    /* ── dialog ── */
    function showFmDialog(title,value,placeholder,callback){
        fmDialogCallback=callback;
        if(dlgTitle) dlgTitle.textContent=title;
        if(dlgInput){dlgInput.value=value||'';dlgInput.placeholder=placeholder||'';}
        if(dlgError){dlgError.textContent='';dlgError.style.display='none';}
        if(dlg){dlg.style.display='flex';setTimeout(function(){if(dlgInput){dlgInput.focus();dlgInput.select();}},50);}
    }
    function closeFmDialog(){if(dlg)dlg.style.display='none';fmDialogCallback=null;}
    if(dlgCancel) dlgCancel.addEventListener('click',closeFmDialog);
    if(dlgOk) dlgOk.addEventListener('click',function(){
        var v=(dlgInput?dlgInput.value:'').trim();
        if(!v){if(dlgError){dlgError.textContent='Name is required';dlgError.style.display='block';}return;}
        if(fmDialogCallback) fmDialogCallback(v);
        closeFmDialog();
    });
    if(dlgInput) dlgInput.addEventListener('keydown',function(e){if(e.key==='Enter')dlgOk&&dlgOk.click();if(e.key==='Escape')closeFmDialog();});
    if(dlg) dlg.addEventListener('click',function(e){if(e.target===dlg)closeFmDialog();});

    /* ── CRUD ── */
    function doMkdir(name){var fd=new FormData();fd.append('fm_mkdir','1');fd.append('ajax','1');fd.append('path',fmCurrentPath);fd.append('name',name);fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(d.success){loadDir(fmCurrentPath);if(typeof showTempNotification!=='undefined')showTempNotification(T.folder_created,'success');}else{fmAlert(d.error||'Error');}});}
    function doMkfile(name){var fd=new FormData();fd.append('fm_mkfile','1');fd.append('ajax','1');fd.append('path',fmCurrentPath);fd.append('name',name);fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(d.success){loadDir(fmCurrentPath);if(typeof showTempNotification!=='undefined')showTempNotification(T.file_created,'success');}else{fmAlert(d.error||'Error');}});}
    function doDelete(path){var fd=new FormData();fd.append('fm_delete','1');fd.append('ajax','1');fd.append('path',path);fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(d.success){loadDir(fmCurrentPath);if(typeof showTempNotification!=='undefined')showTempNotification(T.fm_deleted||'Deleted','success');}else{fmAlert(d.error||T.error_deleting||'Cannot delete');}}).catch(function(){fmAlert(T.network_error||'Network error');});}
    function doRename(path,newName){var fd=new FormData();fd.append('fm_rename','1');fd.append('ajax','1');fd.append('path',path);fd.append('new_name',newName);fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(d.success){loadDir(fmCurrentPath);if(typeof showTempNotification!=='undefined')showTempNotification(T.renamed,'success');}else{fmAlert(d.error||'Cannot rename');}});}
    function doSaveFile(){
        if(!fmCurrentFilePath){setStatus(edStatus,'No file selected','error');return;}
        var raw=fmMonacoEditor?fmMonacoEditor.getValue():'';
        fmConfirm((T.fm_confirm_save||'Save changes?')+'\n\n'+fmCurrentFilePath,function(){
            setStatus(edStatus,T.fm_saving||'Saving\u2026','info');
            var fd=new FormData();fd.append('fm_save_file','1');fd.append('ajax','1');fd.append('path',fmCurrentFilePath);fd.append('content',raw);
            fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
                setStatus(edStatus,d.success?('+ '+(T.fm_saved||'Saved')):('x '+(d.error||T.error)),d.success?'success':'error');
                if(d.success){setFmEditorMode('view');if(typeof showTempNotification!=='undefined')showTempNotification(T.fm_saved||'Saved','success');}
            }).catch(function(){setStatus(edStatus,'x Network error','error');});
        });
    }

    /* ── toolbar ── */
    if(btnBack) btnBack.addEventListener('click',function(){if(fmHistoryIdx>0){fmHistoryIdx--;loadDir(fmHistory[fmHistoryIdx]);}});
    if(btnHome) btnHome.addEventListener('click',function(){navigateTo('/');});
    if(btnRefresh) btnRefresh.addEventListener('click',function(){loadDir(fmCurrentPath);});
    if(btnMkdir) btnMkdir.addEventListener('click',function(){showFmDialog(T.fm_new_folder||'New Folder','',T.fm_folder_name||'Folder name',doMkdir);});
    if(btnMkfile) btnMkfile.addEventListener('click',function(){showFmDialog(T.fm_new_file||'New File','',T.fm_file_name||'File name',doMkfile);});
    if(searchInput) searchInput.addEventListener('input',function(){renderEntries();});
    if(thEls) thEls.forEach(function(th){th.addEventListener('click',function(){var sk=this.dataset.sort;if(fmSortKey===sk){fmSortAsc=!fmSortAsc;}else{fmSortKey=sk;fmSortAsc=true;}renderEntries();});});
    if(edBack) edBack.addEventListener('click',function(){showList();fmCurrentFilePath=null;if(edStatus){edStatus.className='ap-status';edStatus.style.display='none';}});
    if(edModeV) edModeV.addEventListener('click',function(){setFmEditorMode('view');});
    if(edModeE) edModeE.addEventListener('click',function(){setFmEditorMode('edit');});
    if(edSave)  edSave.addEventListener('click',doSaveFile);

    window._apJsonLoadList=function(){loadDir(fmCurrentPath);};
    if(dynRoot&&dynRoot.querySelector('#ap-jsonfiles.active')){loadDir('/');}
}
/* ── Boot ── */
if(openBtn) openBtn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();openAdminPanel('ap-users');});
var initH=location.hash.match(/^#adminpanel\/(.+)$/);
if(initH) openAdminPanel(hashPanel[initH[1]]||'ap-users');
(function(){
    var submit=document.getElementById('ap-gate-submit');
    var cancel=document.getElementById('ap-gate-cancel');
    var pwd=document.getElementById('ap-gate-password');
    function doGateLogin(){
        var pass=pwd?pwd.value:'';
        if(!pass){ apGateShow(<?php echo json_encode($translations['password_required'] ?? 'Password required'); ?>); return; }
        submit.disabled=true;
        apRefreshCsrf().then(function(){ return apGateLogin(pass); }).then(function(d){
            submit.disabled=false;
            if(d&&d.csrf) apRememberCsrf(d.csrf);
            if(d&&d.success){ apGateHide(); loadPanel(window._apPendingPanel||'ap-users'); return; }
            var msg;
            if(d&&d.error==='invalid_credentials') msg=<?php echo json_encode($translations['invalid_credentials'] ?? 'Invalid credentials'); ?>;
            else if(d&&d.error==='csrf_forbidden') msg=<?php echo json_encode($translations['csrf_forbidden'] ?? 'Security check failed. Refresh the page and try again.'); ?>;
            else if(d&&d.error==='admin_ip_forbidden') msg=<?php echo json_encode($translations['admin_ip_forbidden'] ?? 'Administrator access is not allowed from this IP.'); ?>;
            else msg=(d&&d.error)?d.error:'Login failed';
            apGateShow(msg);
        }).catch(function(){ submit.disabled=false; apGateShow('Network error'); });
    }
    if(submit) submit.addEventListener('click',doGateLogin);
    if(pwd) pwd.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); doGateLogin(); } });
    if(cancel) cancel.addEventListener('click',function(){ closeAdminPanel(); });
})();

})();
<?php endif; ?>
</script>

<?php endif; ?>
