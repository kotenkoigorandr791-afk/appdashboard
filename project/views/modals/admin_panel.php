<?php if($isAdmin): ?>
<!-- ====================== ADMIN PANEL ====================== -->
<link rel="stylesheet" href="/project/backup/assets/backup.css">
<script src="/project/backup/assets/backup.js" defer></script>
<link rel="stylesheet" href="/project/performance/style.css?v=<?= is_file(dirname(__DIR__, 2) . '/performance/style.css') ? filemtime(dirname(__DIR__, 2) . '/performance/style.css') : '9' ?>">
<script src="/project/assets/docker/panel.js?v=<?= (int)@filemtime(dirname(__DIR__, 2) . '/assets/docker/panel.js') ?>" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    width:min(480px,92vw);
    position:relative;
    z-index:1;
    overflow:hidden;
    border-radius:26px;
    border:1px solid rgba(17,255,0,0.28);
    background:
        radial-gradient(120% 90% at 0% 0%, rgba(17,255,0,0.10) 0%, transparent 55%),
        radial-gradient(90% 70% at 100% 100%, rgba(59,130,246,0.10) 0%, transparent 50%),
        linear-gradient(165deg, rgba(14,22,34,0.92) 0%, rgba(10,16,26,0.96) 100%);
    box-shadow:0 30px 80px rgba(0,0,0,0.75),0 0 0 1px rgba(17,255,0,0.06) inset,0 0 80px -20px rgba(17,255,0,0.35),0 0 120px -40px rgba(59,130,246,0.35);
    backdrop-filter:blur(28px) saturate(180%);
    -webkit-backdrop-filter:blur(28px) saturate(180%);
    padding:36px 34px 28px;
    box-sizing:border-box;
    text-align:left;
    font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
    animation:ap-gate-in .5s cubic-bezier(.2,.8,.2,1);
}
#ap-loading-skeleton{position:relative}
#ap-loading-skeleton::before{
    content:'';position:absolute;inset:0;z-index:0;pointer-events:none;
    background-image:
        linear-gradient(rgba(96,165,250,0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(96,165,250,0.06) 1px, transparent 1px);
    background-size:60px 60px;
    animation:ap-gate-grid-drift 35s linear infinite;
    mask-image:radial-gradient(ellipse at center, black 25%, transparent 85%);
    -webkit-mask-image:radial-gradient(ellipse at center, black 25%, transparent 85%);
}
@keyframes ap-gate-grid-drift{from{background-position:0 0}to{background-position:60px 60px}}
/* Glass/glow layer like auth cards */
#ap-gate-login::before{
    content:'';
    position:absolute;
    inset:-1px;
    z-index:0;
    border-radius:27px;
    padding:1px;
    background:linear-gradient(135deg,transparent 0%,rgba(17,255,0,0.85) 20%,rgba(34,211,238,0.5) 40%,transparent 55%,rgba(59,130,246,0.6) 75%,transparent 100%);
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
    z-index:0;
    background:radial-gradient(circle at 30% 20%,rgba(255,255,255,0.05),transparent 55%);
    pointer-events:none;
    animation:ap-gate-shimmer 8s ease-in-out infinite;
}
#ap-gate-login > *{
    position:relative;
    z-index:1;
}
@keyframes ap-gate-border-rotate{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes ap-gate-shimmer{0%,100%{transform:translate(0,0) rotate(0deg)}50%{transform:translate(10%,5%) rotate(5deg)}}
@keyframes ap-gate-in{
    from{opacity:0;transform:translateY(14px) scale(.98)}
    to{opacity:1;transform:none}
}
@keyframes _ap_spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
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
    font-size:13.5px;line-height:1.45;opacity:.82;margin:0 0 20px;color:var(--text-color);
}
#ap-gate-login .ap-gate-label{
    display:block;font-size:12px;font-weight:600;opacity:.82;margin:0 0 6px;
}
#ap-gate-password{
    width:100%;box-sizing:border-box;margin:0;padding:12px 14px;
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
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    width:100%;padding:12px 18px;border:1px solid transparent;border-radius:12px;
    background:linear-gradient(180deg, color-mix(in srgb,var(--primary-color) 88%,white), var(--primary-color));
    color:#fff!important;
    font-family:inherit;font-weight:700;font-size:14px;letter-spacing:.01em;cursor:pointer;
    box-shadow:0 10px 24px color-mix(in srgb,var(--primary-color) 35%,transparent),
               inset 0 1px 0 rgba(255,255,255,.35);
    transition:transform .15s ease, filter .15s ease, box-shadow .15s ease, opacity .15s;
}
#ap-gate-submit:hover{filter:brightness(1.06);transform:translateY(-1px);
    box-shadow:0 14px 30px color-mix(in srgb,var(--primary-color) 45%,transparent),
               inset 0 1px 0 rgba(255,255,255,.35)}
#ap-gate-submit:active{transform:translateY(0);filter:brightness(.97);
    box-shadow:0 6px 14px color-mix(in srgb,var(--primary-color) 32%,transparent)}
#ap-gate-submit:focus-visible{outline:2px solid var(--primary-color);outline-offset:2px}
#ap-gate-submit:disabled{opacity:.55;cursor:wait;transform:none;filter:none}
#ap-gate-error{
    display:none;margin-top:12px;padding:10px 12px;border-radius:10px;
    font-size:13px;line-height:1.35;
    background:color-mix(in srgb,#ef4444 14%,transparent);
    border:1px solid color-mix(in srgb,#ef4444 35%,transparent);
    color:#fca5a5;
}
body.light-theme #ap-gate-error, html:not(.dark) #ap-gate-error{color:#b91c1c}
#ap-gate-cancel{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    width:100%;margin-top:10px;padding:11px 18px;
    background:color-mix(in srgb,var(--text-color) 6%,transparent);
    border:1px solid color-mix(in srgb,var(--text-color) 18%,transparent);
    border-radius:12px;
    color:color-mix(in srgb,var(--text-color) 88%,transparent)!important;
    font-family:inherit;cursor:pointer;font-size:13.5px;font-weight:600;
    box-shadow:0 1px 2px rgba(0,0,0,.06);
    transition:background .18s ease,border-color .18s ease,color .18s ease,transform .18s ease,box-shadow .18s ease;
}
#ap-gate-cancel:hover{
    background:color-mix(in srgb,var(--text-color) 11%,transparent);
    border-color:color-mix(in srgb,var(--text-color) 30%,transparent);
    color:var(--primary-color)!important;
    transform:translateY(-1px);
    box-shadow:0 6px 16px rgba(0,0,0,.10);
}
#ap-gate-cancel:active{transform:translateY(0);box-shadow:0 1px 2px rgba(0,0,0,.06)}
#ap-gate-cancel:focus-visible{outline:2px solid var(--primary-color);outline-offset:2px}
body.light-theme #ap-gate-cancel{
    background:rgba(15,23,42,0.04);
    border-color:rgba(15,23,42,0.12);
    box-shadow:0 1px 2px rgba(15,23,42,0.06);
}
body.light-theme #ap-gate-cancel:hover{background:rgba(15,23,42,0.08);border-color:rgba(15,23,42,0.22)}
#ap-gate-login .ap-gate-meta{
    margin-top:14px;padding-top:12px;border-top:1px solid color-mix(in srgb,var(--border-color) 80%,transparent);
    font-size:11px;opacity:.68;line-height:1.4;
}
.ap-gate-password-wrap{position:relative;margin-bottom:14px}
.ap-gate-password-wrap #ap-gate-password{padding-right:52px}
#ap-gate-password-toggle{position:absolute;right:7px;top:50%;transform:translateY(-50%);width:38px;height:38px;padding:0;border:none;border-radius:10px;background:none;color:color-mix(in srgb,var(--text-color) 72%,transparent)!important;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:0}
#ap-gate-password-toggle svg{width:18px;height:18px;display:block;margin:0;overflow:visible}
#ap-gate-password-toggle:hover{opacity:.75}
#ap-gate-password-toggle.active{color:var(--text-color)!important}
#ap-gate-password-toggle .ap-eye-slash{display:none;position:absolute;left:50%;top:50%;transform:translate(-50%,-50%)}
#ap-gate-password-toggle.active .ap-eye-slash{display:block}
#ap-gate-password-toggle .ap-eye-slash line{stroke:currentColor;stroke-width:2;stroke-linecap:round}
.ap-gate-oauth{display:flex;align-items:center;justify-content:center;gap:18px;margin:18px 0 6px}
.ap-gate-oauth .oauth-provider-btn{display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:14px;border:1px solid var(--border-color);background:transparent;text-decoration:none;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
.ap-gate-oauth .oauth-provider-btn:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(0,0,0,.18)}
.ap-gate-oauth .oauth-provider-btn svg{width:24px;height:24px;display:block}

/* Light theme overrides for the admin gate modal */
body.light-theme #ap-gate-login{
    border-color:rgba(17,150,0,0.30);
    background:
        radial-gradient(120% 90% at 0% 0%, rgba(34,197,94,0.08) 0%, transparent 55%),
        radial-gradient(90% 70% at 100% 100%, rgba(59,130,246,0.08) 0%, transparent 50%),
        linear-gradient(165deg, rgba(255,255,255,0.94) 0%, rgba(241,245,250,0.97) 100%);
    box-shadow:0 24px 64px rgba(15,23,42,0.16),0 0 0 1px rgba(17,150,0,0.05) inset,0 0 60px -24px rgba(17,150,0,0.25),0 0 80px -40px rgba(59,130,246,0.30);
    backdrop-filter:blur(20px) saturate(160%);
    -webkit-backdrop-filter:blur(20px) saturate(160%);
}
body.light-theme #ap-gate-login::before{
    background:linear-gradient(135deg,transparent 0%,rgba(22,163,74,0.65) 20%,rgba(14,165,233,0.40) 40%,transparent 55%,rgba(59,130,246,0.45) 75%,transparent 100%);
    background-size:300% 300%;
    opacity:.7;
}
body.light-theme #ap-gate-login::after{
    background:radial-gradient(circle at 30% 20%,rgba(255,255,255,0.22),transparent 55%);
}
body.light-theme #ap-loading-skeleton::before{
    background-image:
        linear-gradient(rgba(37,99,235,0.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(37,99,235,0.07) 1px, transparent 1px);
}
body.light-theme #ap-gate-password{background:rgba(255,255,255,0.85)}
body.light-theme #ap-gate-password-toggle{color:rgba(15,23,42,0.65)!important}
body.light-theme #ap-gate-password-toggle.active{color:#0f172a!important}
body.light-theme .ap-gate-oauth .oauth-provider-btn{box-shadow:0 4px 14px rgba(15,23,42,0.08)}
body.light-theme .ap-gate-oauth .oauth-provider-btn:hover{box-shadow:0 10px 24px rgba(15,23,42,0.16)}

/* Top Bar */
#ap-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 0 16px;
    background: var(--bg2);
    border-bottom: 1px solid var(--border-color);
    height: 60px;
    min-height: 60px;
    box-sizing: border-box;
}
@media (max-width: 768px) {
    #ap-topbar {
        padding: 0 6px;
        gap: 4px;
        height: auto;
        min-height: 52px;
        padding-top: 6px;
        padding-bottom: 6px;
    }
    #ap-topbar .ap-brand {
        flex: 1 1 auto;
        min-width: 0;
        max-width: calc(100% - 120px);
    }
    #ap-topbar .ap-user-info {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 0 0 auto;
    }
}
#ap-topbar .ap-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 1px;
    color: var(--text-color);
    text-transform: uppercase;
    min-width: 0;
}
#ap-topbar .ap-brand-title {
    display: block;
    line-height: 1.15;
    white-space: nowrap;
}
@media (max-width: 768px) {
    #ap-topbar .ap-brand {
        font-size: 11px;
        gap: 4px;
        letter-spacing: 0.3px;
    }
    #ap-topbar .ap-brand svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }
    #ap-topbar .ap-brand-title {
        white-space: normal;
        overflow-wrap: break-word;
        word-break: normal;
        hyphens: auto;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        overflow: hidden;
        max-width: 100%;
    }
    #ap-topbar {
        padding-left: 6px;
        padding-right: 6px;
    }
    #ap-topbar .ap-user-info {
        gap: 2px;
    }
    #ap-topbar .ap-user-info img {
        margin-left: auto;
        flex-shrink: 0;
    }
    #ap-topbar .ap-user-name {
        font-size: 9px;
        max-width: 72px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    #ap-topbar .ap-user-role {
        font-size: 7px;
        padding: 1px 3px;
        border-radius: 5px;
        white-space: nowrap;
    }
    #ap-close-btn {
        width: 26px;
        height: 26px;
        font-size: 16px;
        margin-left: 2px;
        flex-shrink: 0;
    }
}
#ap-topbar .ap-brand svg { color: var(--primary-color); flex-shrink: 0; }
#ap-topbar .ap-user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
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
    text-align: center;
    width: fit-content;
    align-self: center;
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
#ap-sidebar .ap-session-timer {
    font-size: 10px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.3px;
    color: var(--text-secondary);
    background: var(--group-block-bg, rgba(128,128,128,.12));
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 2px 4px;
    min-width: 44px;
    text-align: center;
    white-space: nowrap;
    box-sizing: border-box;
    margin-bottom: 8px;
    flex-shrink: 0;
}
#ap-sidebar .ap-session-timer.ap-session-timer-warn { color: var(--danger, #ef4444); border-color: var(--danger, #ef4444); }
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
    /* Opaque so the tooltip text stays readable over any panel content. */
    background: color-mix(in srgb, var(--primary-color) 10%, var(--bg-color));
    color: var(--text-color);
    border: 1px solid color-mix(in srgb, var(--border-color) 70%, var(--text-color));
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    pointer-events: none;
    display: none;
    z-index: 99999;
    font-family: sans-serif;
    box-shadow: 0 6px 18px rgba(0,0,0,.35);
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
#ap-backup > #bdp-app { overflow-x: hidden !important; overflow-y: auto !important; }
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
#ap-ssh .ap-ssh-topbar .ap-breadcrumb { display: block; margin-bottom: 6px; }
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
    opacity: .7;
    margin: 0 0 14px;
    letter-spacing: 0.5px;
    font-family: sans-serif;
    flex: 0 0 auto;
}
.ap-breadcrumb span { color: var(--primary-color); opacity: 1; }
.ap-breadcrumb-home {
    color: inherit;
    text-decoration: none;
    cursor: pointer;
    opacity: .85;
}
.ap-breadcrumb-home:hover { color: var(--primary-color); opacity: 1; text-decoration: underline; }
.ap-embed-frame {
    flex: 1;
    min-height: 0;
    width: 100%;
    position: relative;
    overflow: hidden;
}
.ap-embed-frame iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
}

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
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.ap-stat-tile .ap-stat-label {
    font-size: 11px;
    opacity: .7;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 24px;
    line-height: 1.2;
}
.ap-stat-tile .ap-stat-value {
    font-size: 22px;
    font-weight: 700;
    margin-top: 2px;
}

@media (max-width: 768px) {
    .ap-stat-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 4px;
    }
    .ap-stat-tile {
        padding: 6px 3px;
    }
    .ap-stat-tile .ap-stat-value {
        font-size: 15px;
    }
    .ap-stat-tile .ap-stat-label {
        font-size: 9px;
    }
}
#ap-stats-range-controls { flex-wrap: wrap; }
#ap-stats-range-controls > * { white-space: nowrap; flex-shrink: 0; }
#ap-stats-range-controls input[type="date"] { flex-shrink: 1; }
@media (min-width: 1200px) {
    #ap-stats-range-controls { flex-wrap: nowrap; }
}

/* Fix date picker calendar icon visibility in dark theme */
#ap-stats-range-controls input[type="date"]::-webkit-calendar-picker-indicator,
#npo-edit-date::-webkit-calendar-picker-indicator,
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1);
    opacity: 0.7;
    cursor: pointer;
}
#ap-stats-range-controls input[type="date"]::-webkit-calendar-picker-indicator:hover,
#npo-edit-date::-webkit-calendar-picker-indicator:hover,
input[type="date"]::-webkit-calendar-picker-indicator:hover {
    opacity: 1;
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
    min-width: 0;
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
@media (max-width: 900px) {
    .ap-settings-tab { min-width: 72px; }
}
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
.ap-pwd-wrap input,
#ap-settings .ap-pwd-wrap input { padding-right: 44px; max-width: 100%; }
.ap-pwd-eye {
    position: absolute;
    right: 4px;
    top: 0;
    bottom: 0;
    margin: auto 0;
    width: 30px;
    height: 100%;
    max-height: 30px;
    background: transparent;
    border: 0;
    border-radius: 6px;
    box-shadow: none;
    cursor: pointer;
    color: var(--text-color) !important;
    opacity: .6;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    line-height: 0;
}
.ap-pwd-eye svg { display: block; width: 16px; height: 16px; grid-area: 1 / 1; transition: opacity .2s ease, transform .2s ease; }
.ap-pwd-eye { transition: opacity .2s ease, color .2s ease; display: grid; place-items: center; }
.ap-pwd-eye .ap-eye-off { opacity: 0; transform: scale(.6); }
.ap-pwd-eye.showing .ap-eye-open { opacity: 0; transform: scale(.6); }
.ap-pwd-eye.showing .ap-eye-off { opacity: 1; transform: scale(1); }
.ap-pwd-eye:hover {
    opacity: 1;
    color: var(--primary-color) !important;
}
.ap-pwd-eye:focus-visible { outline: 2px solid var(--primary-color); outline-offset: 1px; }

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
    background: #161d27; border: 1px solid var(--border-color); border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,.35); z-index: 200; padding: 4px 0;
}
body.light-theme .ap-user-menu-dropdown { background: #f7fafd; }
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
.ap-users-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;}
.ap-search-wrap{position:relative;flex:1 1 220px;min-width:180px;}
.ap-search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);opacity:.55;pointer-events:none;}
.ap-search-bar {
    width: 100%;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 12px 8px 36px;
    color: var(--text-color);
    font-size: 14px;
    font-family: sans-serif;
    box-sizing: border-box;
    margin-bottom: 0;
    transition: border-color .2s;
}
.ap-search-bar:focus { outline: none; border-color: var(--primary-color); }
.ap-users-sort{display:inline-flex;align-items:center;gap:4px;flex-wrap:wrap;}
.ap-sort-btn,.ap-sort-dir{border:1px solid var(--border-color);background:var(--bg-color);color:var(--text-color);border-radius:6px;padding:6px 10px;font-size:12px;cursor:pointer;font-family:sans-serif;}
.ap-sort-btn.active{border-color:var(--primary-color);background:color-mix(in srgb,var(--primary-color) 18%,transparent);font-weight:600;}
.ap-users-pager{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:12px;}
.ap-users-pager .ap-btn{padding:5px 10px;font-size:12px;min-width:36px;}

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

/* Stats in admin panel */
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
@media (max-width: 768px) {
    #ap-settings .ap-field,
    #ap-settings .ap-field input[type="text"],
    #ap-settings .ap-field input[type="password"],
    #ap-settings .ap-field input[type="number"],
    #ap-settings .ap-field input[type="email"],
    #ap-settings .ap-field textarea,
    #ap-settings .ap-pwd-wrap,
    #ap-settings .ap-recipients-box {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
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
#ap-backup.ap-panel.active,
#ap-docs.ap-panel.active,
#ap-db.ap-panel.active,
#ap-performance.ap-panel.active,
#ap-docker-manager.ap-panel.active {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    width: 100%;
    margin: 0;
    padding: 12px 16px 0;
    box-sizing: border-box;
}
#ap-backup > #bdp-app,
#ap-docs > #doc-app,
#ap-performance > #perf-app,
#ap-db > .ap-embed-frame,
#ap-docker-manager > #ap-docker-root {
    flex: 1;
    min-height: 0;
    width: 100%;
    overflow: hidden;
}
#ap-content:has(#ap-backup.active),
#ap-content:has(#ap-docs.active),
#ap-content:has(#ap-db.active),
#ap-content:has(#ap-performance.active),
#ap-content:has(#ap-docker-manager.active) {
    padding: 0 !important;
}

#ap-backup .ap-breadcrumb,
#ap-docs .ap-breadcrumb,
#ap-db .ap-breadcrumb,
#ap-performance .ap-breadcrumb,
#ap-docker-manager .ap-breadcrumb {
    display: block !important;
    margin: 0 0 10px;
    padding: 0 2px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}
#ap-backup.ap-panel.active,
#ap-docs.ap-panel.active,
#ap-db.ap-panel.active,
#ap-performance.ap-panel.active,
#ap-docker-manager.ap-panel.active {
    padding: 12px 0 0 !important;
    overflow: hidden;
}
#ap-backup .ap-breadcrumb,
#ap-docs .ap-breadcrumb,
#ap-db .ap-breadcrumb,
#ap-performance .ap-breadcrumb,
#ap-docker-manager .ap-breadcrumb {
    padding: 0 16px 10px !important;
    border-bottom: 1px solid var(--border-color);
}

/* OmniRoute panel – same embedded styles (no breadcrumb) */
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
            <div class="ap-gate-password-wrap">
                <input type="password" id="ap-gate-password" autocomplete="current-password" placeholder="••••••••">
                <button type="button" id="ap-gate-password-toggle" aria-pressed="false" title="<?php echo my_htmlspecialchars($translations['show_password'] ?? 'Show password'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['show_password'] ?? 'Show password'); ?>">
                    <svg class="ap-eye-slash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="3" y1="3" x2="21" y2="21"></line></svg>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <button type="button" id="ap-gate-submit"><?php echo my_htmlspecialchars($translations['ap_gate_unlock'] ?? 'Unlock'); ?></button>
            <div class="ap-gate-oauth">
                <?php if(oauthProviderConfigured('github')): ?>
                <a href="/login/oauth/start?provider=github&mode=admin_gate" class="oauth-provider-btn oauth-github" title="GitHub" aria-label="GitHub">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-1.35-3.42-3.3-4.365-3.3-4.365-.825-.705 0-.705 0-.705 1.125 0 1.815 1.215 1.815 1.215 1.02 1.725 2.685 1.23 3.345.945.105-.75.435-1.23.795-1.515-2.79-.315-5.73-1.395-5.73-6.195 0-1.365.495-2.475 1.305-3.345-.135-.315-.57-1.575.135-3.285 0 0 1.065-.33 3.495 1.275a12.06 12.06 0 0 1 3.15-.42c1.065 0 2.145.15 3.15.42 2.43-1.605 3.495-1.275 3.495-1.275.705 1.71.27 2.97.135 3.285.81.87 1.305 1.98 1.305 3.345 0 4.815-2.955 5.865-5.745 6.195.45.39.855 1.155.855 2.325 0 1.68-.015 3.03-.015 3.435 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
                </a>
                <?php endif; ?>
                <?php if(oauthProviderConfigured('google')): ?>
                <a href="/login/oauth/start?provider=google&mode=admin_gate" class="oauth-provider-btn oauth-google" title="Google" aria-label="Google">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                </a>
                <?php endif; ?>
            </div>
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
var apStatsData = {};
var apStorageUsage = null;

var panelHash={'ap-users':'usermanagement','ap-settings':'ap-settings','ap-email':'emailsetting','ap-apps-access':'appaccess','ap-telegram':'telegram','ap-stats':'stats','ap-ssh':'sshterminal','ap-backup':'backup','ap-docs':'documentation','ap-db':'database','ap-omniroute':'omniroute','ap-performance':'performance','ap-docker':'docker','ap-docker-manager':'dockermanager'};
var hashPanel={'usermanagement':'ap-users','ap-settings':'ap-settings','emailsetting':'ap-email','appaccess':'ap-apps-access','telegram':'ap-telegram','stats':'ap-stats','sshterminal':'ap-ssh','backup':'ap-backup','documentation':'ap-docs','database':'ap-db','omniroute':'ap-omniroute','performance':'ap-performance','docker':'ap-docker','dockermanager':'ap-docker-manager'};
var OMNIROUTE_URL='/omniroute-bridge?next=/admin-omniroute/home';
function currentResolvedTheme(){return typeof resolveTheme==='function'?resolveTheme(getSelectedThemeValue()):<?php echo json_encode($themeForAppLink, JSON_HEX_TAG); ?>;}
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
function ensureOmniRouteSw(frame){
    if(!navigator.serviceWorker) return;
    if(frame.dataset.swStarted!=='1'){
        frame.dataset.swStarted='1';
        navigator.serviceWorker.register('/admin-omniroute/embed-sw.js?v=2',{scope:'/admin-omniroute/'}).catch(function(){});
    }
    if(frame.dataset.swReload==='1') return;
    navigator.serviceWorker.ready.then(function(){
        if(frame.dataset.swReload==='1') return;
        frame.dataset.swReload='1';
        try{
            if(!frame.contentWindow||frame.contentWindow.navigator.serviceWorker.controller) return;
            frame.contentWindow.location.reload();
        }catch(e){}
    }).catch(function(){});
}
function loadOmniRouteIframe(){
    var frame=document.getElementById('ap-omniroute-frame');
    if(!frame) return;
    if(!frame.dataset.themeHooked){frame.dataset.themeHooked='1';frame.addEventListener('load',function(){injectOmniRouteTheme();ensureOmniRouteSw(frame);});}
    var target=frame.getAttribute('data-src')||OMNIROUTE_URL;
    if(frame.dataset.loaded===target) return;
    frame.dataset.loaded=target;
    frame.src=target;
    injectOmniRouteTheme();
}

var T=<?php echo json_encode([
    'saving'        =>$translations['ap_saving']??'Saving...',
    'storage_usage_title'  =>$translations['ap_storage_usage_title']??'Disk Usage by User',
    'storage_usage_updated'=>$translations['ap_storage_usage_updated']??'Updated',
    'storage_usage_queued' =>$translations['ap_storage_usage_queued']??'Refresh queued',
    'storage_usage_empty'  =>$translations['ap_storage_usage_empty']??'No storage usage data collected yet.',
    'storage_usage_total'  =>$translations['ap_storage_usage_total']??'Total',
    'role_changed'  =>$translations['role_changed']??'Roles saved',
    'email_saved'   =>$translations['email_settings_saved']??'Email settings saved',
    'folder_saved'  =>$translations['folder_config_saved']??'Paths saved',
    'tg_saved'      =>$translations['tg_saved']??'Saved',
    'docker_saved'  =>$translations['docker_saved']??'Docker settings saved',
    'onlyoffice_saved'=>$translations['onlyoffice_saved']??'OnlyOffice token saved',
    'ai_config_saved'=>$translations['ai_config_saved']??'AI configuration saved',
    'ap_sort_asc' =>$translations['ap_sort_asc']??'Ascending',
    'ap_sort_desc'=>$translations['ap_sort_desc']??'Descending',
    'apiKeysMatch'=>$translations['apiKeysMatch']??'API keys match',
    'apiKeysDoNotMatch'=>$translations['apiKeysDoNotMatch']??'API keys do not match',
    'apiKeysRequired'=>$translations['apiKeysRequired']??'Both API key fields are required when changing the key',
    'invalidApiKeyFormat'=>$translations['invalidApiKeyFormat']??'Invalid API key format',
    'backup_saved'  =>$translations['backup_saved']??'Backup credentials saved',
    'backup_deleted'=>$translations['backup_deleted']??'Backup credentials deleted',
    'tg_test_ok'    =>$translations['tg_test_ok']??'Test sent OK',
    'tg_test_fail'  =>$translations['tg_test_fail']??'Test failed',
    'refresh_users' =>$translations['refresh_users']??'Users list refreshed',
    'folder_ph'     =>$translations['folder_config_app_placeholder']??'— Select app —',
    'error'         =>'Error',
    'invalid_response'=>$translations['invalid_response']??'Invalid server response',
    'network_error' =>$translations['network_error']??'Network error',
    'unknown_error' =>'Unknown error',
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
], JSON_HEX_TAG); ?>;

var apFolderMap=<?php $fm=[];foreach($apps as $dn=>$dd){$fm[$dd['folder']]=$dn;}echo json_encode($fm, JSON_HEX_TAG); ?>;

/* ── helpers ── */
function esc(s){var d=document.createElement('div');d.appendChild(document.createTextNode(String(s||'')));return d.innerHTML;}
function setStatus(el,msg,type){if(!el)return;el.className='ap-status '+type;el.textContent=msg;el.style.display='block';if(el._t)clearTimeout(el._t);el._t=setTimeout(function(){el.className='ap-status';el.style.display='none';el.textContent='';},10000);}
function setHash(id){history.replaceState(null,'','#adminpanel/'+(panelHash[id]||id));}
function clearHash(){history.replaceState(null,'',location.pathname+location.search);}
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
    if(window.Chart) return Promise.resolve();
    if(window._apStatsDepsPromise) return window._apStatsDepsPromise;
    if(typeof require==='function'){
        window._apStatsDepsPromise=new Promise(function(resolve,reject){
            try{
                require.config({paths:{
                    chartjs:'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min'
                }});
                require(['chartjs'],function(chart){
                    window.Chart=window.Chart||(chart&&chart.Chart?chart.Chart:(chart&&chart.default?chart.default:chart));
                    if(!window.Chart) reject(new Error('Statistics libraries unavailable after load'));
                    else resolve();
                },reject);
            }catch(e){reject(e);}
        });
        return window._apStatsDepsPromise;
    }
    window._apStatsDepsPromise=apLoadScriptOnce('https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js')
        .then(function(){
            if(!window.Chart) throw new Error('Statistics libraries unavailable after load');
        });
    return window._apStatsDepsPromise;
}

/* ── Panel switch ── */
function apSessionStartTimer(expiresAt){
    expiresAt=(parseInt(expiresAt,10)||0)*1000;
    if(!expiresAt) return;
    var el=function(){ return document.getElementById('ap-session-timer'); };
    if(window._apSessionTimerId){ clearInterval(window._apSessionTimerId); window._apSessionTimerId=null; }
    function fmt(sec){
        if(sec<=0) return '0:00';
        var h=Math.floor(sec/3600), m=Math.floor((sec%3600)/60), s=sec%60;
        if(h>0){
            return h+':'+String(m).padStart(2,'0');
        }
        return String(m)+':'+String(s).padStart(2,'0');
    }
    function tick(){
        var left=Math.floor((expiresAt-Date.now())/1000);
        var node=el();
        if(!node){ clearInterval(window._apSessionTimerId); window._apSessionTimerId=null; return; }
        if(left<=0){ node.textContent='0:00'; node.classList.add('ap-session-timer-warn'); clearInterval(window._apSessionTimerId); window._apSessionTimerId=null; return; }
        node.textContent=fmt(left);
        node.classList.toggle('ap-session-timer-warn', left<300);
    }
    tick();
    window._apSessionTimerId=setInterval(tick,1000);
}
function openPanel(id){
    if(!dynRoot)return;
    overlay.classList.toggle('ap-edge-omniroute',id==='ap-omniroute');
    dynRoot.querySelectorAll('.ap-panel').forEach(function(p){p.classList.remove('active');});
    dynRoot.querySelectorAll('.ap-nav-btn').forEach(function(b){b.classList.remove('active');});
    var p=dynRoot.querySelector('#'+id); if(p) p.classList.add('active');
    var nb=dynRoot.querySelector('[data-panel="'+id+'"]'); if(nb) nb.classList.add('active');
    setHash(id);
    if(id==='ap-stats'&&!apPanelInited['ap-stats']){apPanelInited['ap-stats']=true;requestAnimationFrame(function(){requestAnimationFrame(function(){apEnsureStatsDeps().then(function(){apPopulateYearSelector();initApChartRangePicker();renderApStatsChart();renderApStorageUsage();}).catch(function(e){var st=dynRoot&&dynRoot.querySelector('#ap-storage-usage-status');setStatus(st,e.message||T.error,'error');console.error('apStats init error:',e);});});});}
    else if(id==='ap-stats'){requestAnimationFrame(function(){requestAnimationFrame(function(){initApChartRangePicker();renderApStatsChart();renderApStorageUsage();});});}
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
    if(id==='ap-apps-access'){requestAnimationFrame(function(){requestAnimationFrame(function(){if(typeof window._apRenderWorldMap==='function'){var f=window._apRenderWorldMap;window._apRenderWorldMap=null;f();}});});}
    if(id==='ap-backup'&&window.BackupPanel){
        if(!apPanelInited['ap-backup']){
            apPanelInited['ap-backup']=true;
            try{window.BackupPanel.init(dynRoot);}catch(e){console.error('[AdminPanel] BackupPanel.init:',e);}
        }else if(typeof window.BackupPanel.refresh==='function'){
            try{window.BackupPanel.refresh();}catch(e){}
        }
    }
    if(id==='ap-performance'&&window.PerformancePanel){
        if(!apPanelInited['ap-performance']){
            apPanelInited['ap-performance']=true;
            try{window.PerformancePanel.init();}catch(e){console.error('[AdminPanel] PerformancePanel.init:',e);}
        }else if(typeof window.PerformancePanel.refresh==='function'){
            try{window.PerformancePanel.refresh();}catch(e){}
        }
    }
    if(id==='ap-docker-manager'&&window.DockerPanel){
        var dr=document.getElementById('ap-docker-root');
        if(!apPanelInited['ap-docker-manager']||!window.DockerPanel.mounted()){
            apPanelInited['ap-docker-manager']=true;
            try{window.DockerPanel.init(dr);}catch(e){console.error('[AdminPanel] DockerPanel.init:',e);}
        }
    }
    if(id==='ap-docs'&&window.DocsPanel){
        if(!apPanelInited['ap-docs']){
            apPanelInited['ap-docs']=true;
            try{window.DocsPanel.init();}catch(e){console.error('[AdminPanel] DocsPanel.init:',e);}
        }else if(typeof window.DocsPanel.refresh==='function'){
            try{window.DocsPanel.refresh();}catch(e){}
        }
    }
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
function apTranslateError(code){
    if(!code) return T.unknown_error;
    var s=String(code);
    var map={'invalid_response':'invalid_response','invalid_rsponse':'invalid_response','network_error':'network_error','timeout':'network_error'};
    var key=map[s];
    if(key&&T[key]) return T[key];
    return s;
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
window.apGateShow=apGateShow;
function apGateHide(){
    var gate=document.getElementById('ap-gate-login'); if(gate) gate.style.display='none';
    var spin=document.getElementById('ap-loading-spinner'); if(spin) spin.style.display='';
    var msgEl=document.getElementById('ap-loading-msg'); if(msgEl){ msgEl.style.display=''; msgEl.textContent=<?php echo json_encode($translations['admin_panel_loading']??'Loading Admin Panel...', JSON_HEX_TAG); ?>; }
}
function apGateStatus(){
    return apAdminFetch({admin_panel_status:'1',ajax:'1'});
}
// Keep the admin session alive (and refresh the countdown) while the panel is open,
// so the gate does not pop up in the middle of active work.
if(!window._apKeepAliveId){
    window._apKeepAliveId=setInterval(function(){
        var ov=document.getElementById('admin-panel-overlay');
        if(!ov||!ov.classList.contains('active'))return;
        apGateStatus().then(function(d){
            if(d&&d.authenticated&&d.expires_at) apSessionStartTimer(d.expires_at);
        }).catch(function(){});
    },60000);
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
            if(d.expires_at){ apSessionStartTimer(d.expires_at); }
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
            apGateShow(<?php echo json_encode($translations['csrf_forbidden'] ?? 'Security check failed. Refresh the page and try again.', JSON_HEX_TAG); ?>);
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
    if(msgEl){ msgEl.style.display=''; msgEl.textContent=<?php echo json_encode($translations['admin_panel_loading']??'Loading Admin Panel...', JSON_HEX_TAG); ?>; }
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
                if(d&&d.error==='csrf_forbidden'){ apGateShow(<?php echo json_encode($translations['csrf_forbidden'] ?? 'Security check failed. Refresh the page and try again.', JSON_HEX_TAG); ?>); return; }
                if(d&&!d.success&&/(not authorized|unauthorized|not authenticated|не авториз)/i.test(d.error||'')) {apShowUnauthorizedMsg();return;}
                if(msgEl)msgEl.textContent='Error: '+apTranslateError(d&&d.error);return;
            }
            apLoaded=true;
            try{apStatsData=JSON.parse(d.stats||'{}');}catch(e){apStatsData={};}
            try{apStorageUsage=JSON.parse(d.storage_usage||'null');}catch(e){apStorageUsage=null;}
            if(d.session_expires_at){ apSessionStartTimer(d.session_expires_at); }
            if(d.available_years && Array.isArray(d.available_years)){
                apAvailableYears = d.available_years;
            }
            dynRoot.innerHTML=d.html;
            Array.prototype.slice.call(dynRoot.querySelectorAll('script')).forEach(function(oldScript){
                var s=document.createElement('script');
                if(oldScript.src){ s.src=oldScript.src; s.async=false; }
                else { s.textContent=oldScript.textContent; }
                oldScript.parentNode.replaceChild(s, oldScript);
            });
            skeleton.style.display='none';
            dynRoot.style.cssText='display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;';
            apPanelInited={};
            apEnsureStatsDeps().catch(function(e){console.error('[AdminPanel] stats dependencies:',e);});
            openPanel(target||'ap-users');
            wireEvents();
        })
        .catch(function(err){
            var expected=false;
            if(err&&err.message===AP_GATE){apGateShow('');expected=true;}
            else if(err&&err.message==='csrf_forbidden'){apGateShow(<?php echo json_encode($translations['csrf_forbidden'] ?? 'Security check failed. Refresh the page and try again.', JSON_HEX_TAG); ?>);expected=true;}
            else if(err&&err.message===AP_UNAUTH){apShowUnauthorizedMsg();expected=true;}
            else if(msgEl){msgEl.style.display=''; msgEl.textContent='Error: '+(err.message||'Unknown error');}
            // An expired/absent admin session is a normal outcome of the gate,
            // not an application error worth reporting.
            if(expected) console.warn('[AdminPanel]',err&&err.message);
            else console.error('[AdminPanel]',err);
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
            try{apStorageUsage=JSON.parse(d.storage_usage||'null');}catch(e){apStorageUsage=null;}
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
    var rotateKeyBtn = dynRoot.querySelector('#ap-ssh-rotate-key-btn');
    if(!terminalEl){
        return;
    }

    // Auto-connect on panel open
    function autoConnect(){
        var fd = new FormData();
        fd.append('ssh_terminal_init', '1');
        fd.append('ajax', '1');
        fetch('/main', {method:'POST', body:fd})
            .then(parseApiJson)
            .then(function(d){
                if(d && d.success && d.connected){
                    if(d.ws_token && terminalEl){
                        terminalEl._apSshPreauthToken = d.ws_token;
                        terminalEl._apSshPreauthUrl = d.ws_url || '';
                    }
                    if(terminalEl && typeof terminalEl._apSshReconnect === 'function'){
                        terminalEl._apSshReconnect();
                    }
                }else{
                    var term = terminalEl._apTerm;
                    var msg = ((d && (d.connect_error || d.error)) ? (d.connect_error || d.error) : 'SSH not connected');
                    if(term){
                        term.writeln('\x1b[31m' + msg + '\x1b[0m');
                    }else{
                        terminalEl.textContent = '[Error] ' + msg;
                    }
                }
            })
            .catch(function(err){
                var msg = (err && err.message) ? err.message : 'network error';
                var term = terminalEl._apTerm;
                if(term){
                    term.writeln('\x1b[31m' + msg + '\x1b[0m');
                }else{
                    terminalEl.textContent = '[Error] ' + msg;
                }
            });
    }

    if(rotateKeyBtn){
        // Rotate SSH key
        rotateKeyBtn.addEventListener('click', function(){
            if(rotateKeyBtn.disabled) return;
            rotateKeyBtn.disabled = true;
            var originalText = rotateKeyBtn.innerHTML;
            rotateKeyBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;animation:_ap_spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="0.75"></path></svg> Rotating...';

            var fd = new FormData();
            fd.append('ssh_rotate_key', '1');
            fd.append('ajax', '1');
            fetch('/main', {method:'POST', body:fd})
                .then(parseApiJson)
                .then(function(d){
                    rotateKeyBtn.disabled = false;
                    rotateKeyBtn.innerHTML = originalText;
                    if(d && d.success){
                        terminalEl.textContent = '[Info] ' + (d.message || 'SSH key rotated successfully');
                        if(d.public_key){
                            terminalEl.textContent += '\n\nNew public key:\n' + d.public_key + '\n\nAdd this to ~/.ssh/authorized_keys on the host machine.';
                        }
                    }else{
                        terminalEl.textContent = '[Error] ' + (d && d.error ? d.error : 'Failed to rotate key');
                    }
                })
                .catch(function(err){
                    rotateKeyBtn.disabled = false;
                    rotateKeyBtn.innerHTML = originalText;
                    terminalEl.textContent = '[Error] ' + (err && err.message ? err.message : 'network error');
                });
        });
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

/** NBSP / narrow NBSP → normal space (fixes 'broken' prompts on copy-paste) */
function apSshNormalizeSpaces(s){
    return String(s || '').replace(/\u00a0/g,' ').replace(/\u202f/g,' ').replace(/\u2007/g,' ');
}

/** Remove helper lines like libs: … if they ever end up in the output */
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

                // ssh_terminal_init already issued a token for this session.
                var preToken = terminalEl._apSshPreauthToken;
                var preUrl = terminalEl._apSshPreauthUrl;
                if(preToken && preUrl){
                    terminalEl._apSshPreauthToken = '';
                    terminalEl._apSshPreauthUrl = '';
                    apSshWsOpen(preToken, preUrl);
                    return;
                }

                var fd = new FormData();
                fd.append('ssh_terminal_ws_token', '1');
                fd.append('ajax', '1');
                fetch('/main', {method:'POST', body:fd})
                    .then(parseApiJson)
                    .then(function(d){
                        if(!d || !d.success || !d.token || !d.ws_url){
                            term.writeln('\x1b[31m' + ((d && d.error) ? d.error : 'WebSocket token failed') + '\x1b[0m');
                            return;
                        }
                        apSshWsOpen(d.token, d.ws_url);
                    })
                    .catch(function(err){
                        term.writeln('\x1b[31m' + ((err && err.message) ? err.message : 'network error') + '\x1b[0m');
                    });
            }

            function apSshWsOpen(token, url){
                apSshWsToken = token;
                apSshWs = new WebSocket(url);
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
            }
            terminalEl._apSshReconnect = apSshWsConnect;
            terminalEl._apSshSendInput = function(text){
                if(apSshWsReady) apSshWsSend({type:'input', data: String(text || '')});
            };

            term.onResize(function(){ apSshWsResize(); });
            term.onData(function(data){
                if(apSshWsReady) apSshWsSend({type:'input', data: data});
            });

            term.writeln(apSshNormalizeSpaces('Interactive SSH terminal (PTY).'));
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
                        fmAlert('Error: ' + apTranslateError(d && d.error));
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
                        fmAlert('Error: ' + apTranslateError(d && d.error));
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
    autoConnect();
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
    });});
    dynRoot.addEventListener('click', function(ev){
        var home = ev.target && ev.target.closest ? ev.target.closest('[data-ap-home]') : null;
        if(!home || !dynRoot.contains(home)) return;
        ev.preventDefault();
        openPanel('ap-users');
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
        if(i){
            i.type=i.type==='password'?'text':'password';
            this.classList.toggle('showing', i.type==='text');
        }
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
    /* users search + sort + pagination */
    (function(){
        var uSearch=dynRoot.querySelector('#ap-users-search');
        var uTable=dynRoot.querySelector('#ap-users-table');
        if(!uTable) return;
        var tbody=uTable.querySelector('tbody');
        var state={q:'',sort:'name',dir:'asc',page:1,pageSize:25};
        function rows(){return Array.prototype.slice.call(tbody?tbody.querySelectorAll('tr[data-email]'):[]);}
        function match(r){
            if(!state.q) return true;
            var e=(r.dataset.email||'').toLowerCase(), n=(r.dataset.name||'').toLowerCase();
            return e.indexOf(state.q)!==-1 || n.indexOf(state.q)!==-1;
        }
        function sortVal(r){
            if(state.sort==='role') return parseInt(r.dataset.role||'0',10)||0;
            if(state.sort==='created') return r.dataset.created||'';
            return (r.dataset.name||'').toLowerCase();
        }
        function applyUsersView(){
            if(!tbody) return;
            var list=rows().filter(match);
            list.sort(function(a,b){
                var av=sortVal(a), bv=sortVal(b);
                if(av<bv) return state.dir==='asc'?-1:1;
                if(av>bv) return state.dir==='asc'?1:-1;
                return 0;
            });
            var total=list.length;
            var pages=Math.max(1, Math.ceil(total/state.pageSize)||1);
            if(state.page>pages) state.page=pages;
            var start=(state.page-1)*state.pageSize;
            var end=start+state.pageSize;
            rows().forEach(function(r){r.style.display='none';});
            list.forEach(function(r,i){
                tbody.appendChild(r);
                r.style.display=(i>=start && i<end)?'':'none';
            });
            var info=dynRoot.querySelector('#ap-users-page-info');
            var prev=dynRoot.querySelector('#ap-users-prev');
            var next=dynRoot.querySelector('#ap-users-next');
            if(info) info.textContent=(total? (start+1) : 0)+'–'+Math.min(end,total)+' / '+total;
            if(prev) prev.disabled=state.page<=1;
            if(next) next.disabled=state.page>=pages;
        }
        window.apApplyUsersView=applyUsersView;
        if(uSearch) uSearch.addEventListener('input',function(){state.q=(this.value||'').toLowerCase().trim();state.page=1;applyUsersView();});
        dynRoot.querySelectorAll('.ap-sort-btn').forEach(function(btn){
            btn.addEventListener('click',function(){
                dynRoot.querySelectorAll('.ap-sort-btn').forEach(function(b){b.classList.remove('active');});
                btn.classList.add('active');
                state.sort=btn.dataset.sort||'name';
                state.page=1;
                applyUsersView();
            });
        });
        var dirBtn=dynRoot.querySelector('#ap-users-sort-dir');
        if(dirBtn) dirBtn.addEventListener('click',function(){
            state.dir=state.dir==='asc'?'desc':'asc';
            dirBtn.dataset.dir=state.dir;
            dirBtn.textContent=state.dir==='asc'?'↑':'↓';
            dirBtn.title=(state.dir==='asc')?(T.ap_sort_asc||'Ascending'):(T.ap_sort_desc||'Descending');
            applyUsersView();
        });
        var prev=dynRoot.querySelector('#ap-users-prev');
        var next=dynRoot.querySelector('#ap-users-next');
        var sizeSel=dynRoot.querySelector('#ap-users-page-size');
        if(prev) prev.addEventListener('click',function(){if(state.page>1){state.page--;applyUsersView();}});
        if(next) next.addEventListener('click',function(){state.page++;applyUsersView();});
        if(sizeSel) sizeSel.addEventListener('change',function(){state.pageSize=parseInt(this.value,10)||25;state.page=1;applyUsersView();});
        applyUsersView();
    })();
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
                        if(typeof window.apApplyUsersView==='function') window.apApplyUsersView();
                        if(typeof showTempNotification!=='undefined')
                            showTempNotification(<?php echo json_encode($translations['refresh_users']??'Users list updated', JSON_HEX_TAG); ?>,'info');
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
        fd.set('turnstile_site_key',  (dynRoot.querySelector('#ap-turnstile-site')||{value:''}).value);
        fd.set('turnstile_secret_key',(dynRoot.querySelector('#ap-turnstile-secret')||{value:''}).value);
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
    var aiSt=dynRoot.querySelector('#ap-ai-config-status'),aiSave=dynRoot.querySelector('#ap-save-ai-config');
    if(aiSave)aiSave.addEventListener('click',function(){
        var endpointEl=dynRoot.querySelector('#ap-ai-endpoint'),modelsEl=dynRoot.querySelector('#ap-ai-models'),defaultEl=dynRoot.querySelector('#ap-ai-default-model'),currentEl=dynRoot.querySelector('#ap-ai-current-key'),speechModelsEl=dynRoot.querySelector('#ap-ai-speech-models'),speechModelEl=dynRoot.querySelector('#ap-ai-speech-model');
        var keyValue=currentEl?(currentEl.value||'').trim():'';
        var originalKey=currentEl?((typeof currentEl.dataset.original!=='undefined')?currentEl.dataset.original:(currentEl.value||'')):'';
        var hasKey=keyValue!==originalKey;
        if(hasKey){
            if(!keyValue){setStatus(aiSt,'x '+(T.apiKeysRequired||'API key is required'),'error');return;}
            if(!/^(sk-|io-v2-)/.test(keyValue)){setStatus(aiSt,'x '+(T.invalidApiKeyFormat||'Invalid key format'),'error');return;}
        }
        setStatus(aiSt,T.saving,'info');
        var fd=new FormData();
        fd.append('save_ai_config','1');fd.append('ajax','1');
        var searchEngineEl=dynRoot.querySelector('#ap-search-engine-url');
        fd.append('search_engine_url',searchEngineEl?searchEngineEl.value.trim():'');
        fd.append('api_endpoint',endpointEl?endpointEl.value.trim():'');
        fd.append('models',modelsEl?modelsEl.value.trim():'');
        fd.append('default_model',defaultEl?defaultEl.value.trim():'');
        fd.append('speech_models',speechModelsEl?speechModelsEl.value.trim():'');
        fd.append('speech_model',speechModelEl?speechModelEl.value.trim():'');
        if(hasKey)fd.append('api_key',keyValue);
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            setStatus(aiSt,d.success?('+ '+(d.message||T.ai_config_saved)+(d.has_key===false?' · API key missing':'')):('x '+(d.error||T.error)),d.success&&d.has_key!==false?'success':'error');
            if(d.success){
                if(typeof showTempNotification!=='undefined')showTempNotification(d.message||T.ai_config_saved,'success');
                if(d.config){
                    if(endpointEl&&d.config.api_endpoint)endpointEl.value=d.config.api_endpoint;
                    if(modelsEl&&Array.isArray(d.config.models))modelsEl.value=d.config.models.join('\n');
                    if(speechModelsEl&&Array.isArray(d.config.speech_models))speechModelsEl.value=d.config.speech_models.join('\n');
                    if(typeof apSyncModelSelect==='function'){
                        apSyncModelSelect(defaultEl, modelsEl?modelsEl.value:'', d.config.default_model||'');
                        apSyncModelSelect(speechModelEl, speechModelsEl?speechModelsEl.value:'', d.config.speech_model||'');
                    } else {
                        if(defaultEl&&d.config.default_model)defaultEl.value=d.config.default_model;
                        if(speechModelEl&&d.config.speech_model)speechModelEl.value=d.config.speech_model;
                    }
                    if(currentEl&&d.config.api_key){currentEl.value=d.config.api_key;currentEl.dataset.original=d.config.api_key;currentEl.type='password';}
                }
                if(currentEl){
                    if(!d.config||!d.config.api_key){currentEl.dataset.original=currentEl.value||'';}
                    currentEl.type='password';
                    var eyeBtn=dynRoot.querySelector('.ap-pwd-eye[data-target="ap-ai-current-key"]');
                    if(eyeBtn) eyeBtn.classList.remove('showing');
                }
            }
        }).catch(function(){setStatus(aiSt,'x '+T.error,'error');});
    });
    function apParseModelLines(text){
        return String(text||'').split(/\r?\n/).map(function(s){return s.trim();}).filter(Boolean);
    }
    function apSyncModelSelect(selectEl, modelsText, current){
        if(!selectEl)return;
        var models=apParseModelLines(modelsText);
        var cur=current||selectEl.value||'';
        if(cur&&models.indexOf(cur)===-1)models.unshift(cur);
        selectEl.innerHTML='';
        if(!models.length){
            var o=document.createElement('option');
            o.value='';o.textContent=T.ai_models_label||'Add models above';
            selectEl.appendChild(o);
            return;
        }
        models.forEach(function(m){
            var o=document.createElement('option');
            o.value=m;o.textContent=m;
            if(m===cur)o.selected=true;
            selectEl.appendChild(o);
        });
    }
    var modelsElLive=dynRoot.querySelector('#ap-ai-models'),defaultElLive=dynRoot.querySelector('#ap-ai-default-model');
    var speechModelsElLive=dynRoot.querySelector('#ap-ai-speech-models'),speechModelElLive=dynRoot.querySelector('#ap-ai-speech-model');
    if(modelsElLive){
        modelsElLive.addEventListener('input',function(){
            apSyncModelSelect(defaultElLive, modelsElLive.value, defaultElLive?defaultElLive.value:'');
        });
    }
    if(speechModelsElLive){
        speechModelsElLive.addEventListener('input',function(){
            apSyncModelSelect(speechModelElLive, speechModelsElLive.value, speechModelElLive?speechModelElLive.value:'');
        });
    }
    // After successful save, refresh selects from returned config
    var aiTest=dynRoot.querySelector('#ap-test-ai-models');
    if(aiTest)aiTest.addEventListener('click',function(){
        var modelsEl=dynRoot.querySelector('#ap-ai-models');
        setStatus(aiSt,(T.testing||'Testing...'),'info');
        aiTest.disabled=true;
        var fd=new FormData();
        fd.append('test_ai_models','1');fd.append('ajax','1');
        fd.append('models',modelsEl?modelsEl.value.trim():'');
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
            var msg=d.message||(d.success?'OK':(d.error||T.error));
            setStatus(aiSt,(d.success?'+ ':'x ')+msg,d.success?'success':'error');
            if(typeof showTempNotification!=='undefined')showTempNotification(msg,d.success?'success':'error');
            if(Array.isArray(d.results)){
                console.log('AI model test results', d.results);
            }
        }).catch(function(){
            setStatus(aiSt,'x '+T.error,'error');
            if(typeof showTempNotification!=='undefined')showTempNotification(T.error||'Error','error');
        }).then(function(){aiTest.disabled=false;});
    });
    var accGrid=dynRoot.querySelector('#ap-app-access-grid'),accSort=dynRoot.querySelector('#ap-app-access-sort'),accSav=dynRoot.querySelector('#ap-save-app-access'),accSt=dynRoot.querySelector('#ap-app-access-status');
    function apSortAccessTiles(){if(!accGrid||!accSort)return;var tiles=Array.from(accGrid.querySelectorAll('.ap-app-access-tile')),m=accSort.value;tiles.sort(function(a,b){if(m==='name')return(a.dataset.name||'').localeCompare(b.dataset.name||'');if(m==='role')return parseInt(a.dataset.role,10)-parseInt(b.dataset.role,10);return parseInt(b.dataset.visits,10)-parseInt(a.dataset.visits,10);});tiles.forEach(function(t){accGrid.appendChild(t);});}
    if(accSort) accSort.addEventListener('change',apSortAccessTiles);
    if(accGrid) accGrid.addEventListener('change',function(e){if(e.target&&e.target.classList.contains('ap-app-access-level')){var p=e.target.closest('.ap-app-access-tile');if(p)p.dataset.role=e.target.value;}});
    var worldMap=dynRoot.querySelector('#ap-world-map'),blockedCountries=[],vpnBlock=dynRoot.querySelector('#ap-block-vpn-traffic');
    function apLoadAmChartsMaps(){if(window._apAmChartsMapsPromise)return window._apAmChartsMapsPromise;var urls=['https://cdn.amcharts.com/lib/5/index.js','https://cdn.amcharts.com/lib/5/map.js','https://cdn.amcharts.com/lib/5/geodata/worldLow.js','https://cdn.amcharts.com/lib/5/themes/Animated.js'];window._apAmChartsMapsPromise=urls.reduce(function(p,u){return p.then(function(){return new Promise(function(res,rej){if(document.querySelector('script[src="'+u+'"]')){res();return;}var s=document.createElement('script');s.src=u;s.async=false;s.onload=res;s.onerror=function(){rej(new Error('Failed to load: '+u));};document.head.appendChild(s);});});},Promise.resolve());return window._apAmChartsMapsPromise;}
    function renderWorldMap(){if(!worldMap||!document.body.contains(worldMap))return;if(!worldMap.offsetWidth){window._apRenderWorldMap=renderWorldMap;return;}var countries={},hostCountry=(worldMap.dataset.hostCountry||'').toUpperCase();try{countries=JSON.parse(worldMap.dataset.countries||'{}')||{};}catch(e){}try{blockedCountries=JSON.parse(worldMap.dataset.selected||'[]')||[];}catch(e){blockedCountries=[];}blockedCountries=blockedCountries.filter(function(c){return c!==hostCountry;});worldMap.innerHTML='';apLoadAmChartsMaps().then(function(){if(!window.am5||!window.am5map||!window.am5geodata_worldLow)return;if(worldMap._apMapRoot)worldMap._apMapRoot.dispose();var root=am5.Root.new(worldMap);worldMap._apMapRoot=root;root.setThemes([am5themes_Animated.new(root)]);var chart=root.container.children.push(am5map.MapChart.new(root,{panX:'translateX',panY:'translateY',wheelY:'zoom',pinchZoom:true,maxZoomLevel:8,maxPanOut:0.1,homeGeoPoint:{latitude:20,longitude:10},homeZoomLevel:1,projection:am5map.geoMercator()}));var series=chart.series.push(am5map.MapPolygonSeries.new(root,{geoJSON:am5geodata_worldLow,exclude:['AQ']}));var normal=am5.color(0x64748b),active=am5.color(0xef4444),host=am5.color(0x94a3b8),hover=am5.color(0x3b82f6),stroke=am5.color(0xffffff);function apCountryCode(target){return target.dataItem&&target.dataItem.dataContext&&target.dataItem.dataContext.id;}function apply(target){var code=apCountryCode(target);target.set('fill',code===hostCountry?host:(blockedCountries.indexOf(code)!==-1?active:normal));target.set('fillOpacity',code===hostCountry ? .45 : 1);}series.mapPolygons.template.setAll({tooltipText:'{name}',interactive:true,fill:normal,stroke:stroke,strokeWidth:.5});series.mapPolygons.template.adapters.add('tooltipText',function(text,target){var code=apCountryCode(target),label=countries[code]||text;return code===hostCountry?label+' (hosting)':label;});series.mapPolygons.template.events.on('click',function(ev){var target=ev.target,code=apCountryCode(target);if(!code||code===hostCountry)return;var i=blockedCountries.indexOf(code);if(i===-1)blockedCountries.push(code);else blockedCountries.splice(i,1);apply(target);});series.mapPolygons.template.events.on('pointerover',function(ev){var code=apCountryCode(ev.target);ev.target.set('fill',code===hostCountry?host:(blockedCountries.indexOf(code)!==-1?active:hover));});series.mapPolygons.template.events.on('pointerout',function(ev){apply(ev.target);});series.events.on('datavalidated',function(){series.mapPolygons.each(apply);chart.goHome(0);});chart.set('zoomControl',am5map.ZoomControl.new(root,{}));chart.goHome(0);}).catch(function(){worldMap.textContent='Map library failed to load';});}
    renderWorldMap();
    if(accSav) accSav.addEventListener('click',function(){setStatus(accSt,T.saving,'info');var lv={};if(accGrid) accGrid.querySelectorAll('.ap-app-access-level').forEach(function(s){lv[s.getAttribute('data-folder')]=parseInt(s.value,10);});var fd=new FormData();fd.append('save_app_access_levels','1');fd.append('ajax','1');fd.append('levels_json',JSON.stringify(lv));fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(!d.success)throw new Error(d.error||T.error);var fd2=new FormData();fd2.append('save_app_access_security','1');fd2.append('ajax','1');fd2.append('countries_json',JSON.stringify(blockedCountries));fd2.append('block_vpn',vpnBlock&&vpnBlock.checked?'1':'0');return fetch('/main',{method:'POST',body:fd2}).then(function(r){return r.json();});}).then(function(d){setStatus(accSt,d.success?('+ '+T.app_access_saved):('x '+(d.error||T.error)),d.success?'success':'error');if(d.success&&typeof showTempNotification!=='undefined')showTempNotification(T.app_access_saved,'success');}).catch(function(e){setStatus(accSt,'x '+(e&&e.message?e.message:T.error),'error');});});
    /* storage usage refresh */
    var suBtn=dynRoot.querySelector('#ap-storage-usage-refresh'), suSt=dynRoot.querySelector('#ap-storage-usage-status');
    if(suBtn&&!suBtn._h){suBtn._h=true;suBtn.addEventListener('click',function(){suBtn.disabled=true;var fd=new FormData();fd.append('refresh_storage_usage','1');fd.append('ajax','1');fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){if(!d.success)throw new Error(d.error||T.error);setStatus(suSt,'\u2713 '+(d.message||T.storage_usage_queued),'success');}).catch(function(e){setStatus(suSt,'\u2717 '+(e&&e.message?e.message:T.error),'error');}).then(function(){setTimeout(function(){suBtn.disabled=false;},4000);});});}
    var rcBtn=dynRoot.querySelector('#ap-refresh-chart');
    if(rcBtn&&!rcBtn._h){rcBtn._h=true;rcBtn.addEventListener('click',function(){initApChartRangePicker();renderApStatsChart();});}
    /* SSH panel */
    wireSshPanel();
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
    if(typeof window.__AP_CSRF__==='string'&&window.__AP_CSRF__) fd.append('_csrf',window.__AP_CSRF__);
    fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
        if(d.success&&tbody)tbody.innerHTML=d.rows;
        if(btn){btn.disabled=false;btn.style.opacity='1';}
        apUpdateUserAnalytics();
        if(typeof window.apApplyUsersView==='function') window.apApplyUsersView();
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
    if(typeof window.__AP_CSRF__==='string'&&window.__AP_CSRF__) fd.append('_csrf',window.__AP_CSRF__);
    fetch('/main',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(data){
        clearInterval(progressInterval);
        if(pb)pb.style.width='100%';
        if(pp)pp.textContent='100%';
        if(tr)tr.textContent='<?php echo my_htmlspecialchars($translations['complete'] ?? 'Complete'); ?>!';
        setTimeout(function(){
            if(sm)sm.style.display='none';
            if(ps)ps.style.display='none';
            if(fl)fl.style.display='block';
            var c=document.getElementById('delete-user-file-count'),s=document.getElementById('delete-user-total-size'),l=document.getElementById('delete-user-files-list');
            var files=(data&&data.files)||[];
            if(c)c.textContent=files.length+(data&&data.truncated?'+':'')+' file(s)';
            if(s)s.textContent=(data&&data.total_size_human)||'0 B';
            if(l){
                if(!data||data.success===false){
                    l.innerHTML='<div class="no-files-msg">'+esc((data&&(data.message||data.error))||'Scan failed')+'</div>';
                }else if(!files.length){
                    l.innerHTML='<div class="no-files-msg"><?php echo my_htmlspecialchars($translations['no_user_files'] ?? 'No user files found in configured paths'); ?></div>';
                }else{
                    l.innerHTML=files.slice(0,20).map(function(f){return '<div style="font-size:12px;opacity:.6;padding:1px 0;">'+esc(f.name)+' ('+esc(f.app)+')</div>';}).join('');
                }
            }
            if(dob)dob.disabled=false;
        },300);
    }).catch(function(err){
        clearInterval(progressInterval);
        if(sm)sm.style.display='none';
        if(ps)ps.style.display='none';
        if(fl)fl.style.display='block';
        var l=document.getElementById('delete-user-files-list');
        if(l) l.innerHTML='<div class="no-files-msg">'+esc((err&&err.message)||'Scan failed')+'</div>';
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
    if(title) title.textContent=(<?php echo json_encode($translations['reset_user_password'] ?? 'Reset password', JSON_HEX_TAG); ?>)+': '+name;
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
        apEnsureStatsDeps().then(renderApStatsChart).catch(function(e){var st=dynRoot&&dynRoot.querySelector('#ap-storage-usage-status');setStatus(st,e.message||T.error,'error');});
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
    // Init range picker controls (idempotent)
    initApChartRangePicker();
}

/* ── Per-user storage usage (reads the collector cache only) ── */
var apStorageUsageChart = null;
var AP_STORAGE_LABELS = {cloud:<?php echo json_encode($translations['ap_storage_usage_app_cloud']??'Cloud', JSON_HEX_TAG); ?>, immich:<?php echo json_encode($translations['ap_storage_usage_app_immich']??'Immich', JSON_HEX_TAG); ?>, gitea:<?php echo json_encode($translations['ap_storage_usage_app_gitea']??'Gitea', JSON_HEX_TAG); ?>, dawarich:<?php echo json_encode($translations['ap_storage_usage_app_dawarich']??'Dawarich', JSON_HEX_TAG); ?>};
function apFormatBytes(bytes){
    bytes = Number(bytes)||0;
    if(bytes<=0) return '0 B';
    var u=['B','KB','MB','GB','TB','PB'], e=Math.min(Math.floor(Math.log(bytes)/Math.log(1024)),u.length-1);
    return (bytes/Math.pow(1024,e)).toFixed(e>1?2:0)+' '+u[e];
}
function apFormatAgo(ts){
    ts = Number(ts)||0;
    if(!ts) return '';
    var s=Math.max(1,Math.floor((Date.now()/1000-ts)/60));
    if(s<60) return s+' min';
    if(s<1440) return Math.floor(s/60)+' h';
    return Math.floor(s/1440)+' d';
}
function renderApStorageUsage(){
    var canvas = dynRoot&&dynRoot.querySelector('#ap-storage-usage-canvas');
    var empty  = dynRoot&&dynRoot.querySelector('#ap-storage-usage-empty');
    var meta   = dynRoot&&dynRoot.querySelector('#ap-storage-usage-meta');
    var srcBox = dynRoot&&dynRoot.querySelector('#ap-storage-usage-sources');
    if(!canvas) return;
    var snap = apStorageUsage || {sources:{},users:{},rows:{}};
    var users = snap.users||{}, rows = snap.rows||{}, sources = snap.sources||{};
    var appKeys = Object.keys(rows).sort();
    var totals = {};
    Object.keys(users).forEach(function(mail){ totals[mail]=0; });
    appKeys.forEach(function(k){ Object.keys(rows[k]).forEach(function(mail){ totals[mail]=(totals[mail]||0)+rows[k][mail].bytes; }); });
    var ordered = Object.keys(totals).filter(function(m){ return totals[m]>0; }).sort(function(a,b){ return totals[b]-totals[a]; });
    var hasData = ordered.length>0;
    if(apStorageUsageChart){ apStorageUsageChart.destroy(); apStorageUsageChart=null; }
    if(empty) empty.style.display = hasData ? 'none' : 'block';
    if(canvas.parentNode) canvas.parentNode.style.display = hasData ? '' : 'none';
    if(meta){
        var newest = Object.keys(sources).reduce(function(acc,k){ var m=Number(sources[k]&&sources[k].measured_at)||0; return m>acc?m:acc; },0);
        meta.textContent = newest ? (T.storage_usage_updated+' '+apFormatAgo(newest)) : '';
    }
    if(srcBox){
        srcBox.innerHTML = '';
        var known = {cloud:1,immich:1,gitea:1,dawarich:1};
        Object.keys(known).forEach(function(k){
            var st = sources[k] || {};
            var color = st.status==='ok' ? '#2ECC71' : (st.status==='unavailable' ? '#F39C12' : '#E74C3C');
            var span = document.createElement('span');
            span.style.display='inline-flex';
            span.style.alignItems='center';
            span.style.gap='5px';
            span.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:'+color+';display:inline-block;"></span>';
            var txt = document.createElement('span');
            txt.textContent = (AP_STORAGE_LABELS[k]||k) + ' — ' + (st.status==='ok' ? apFormatAgo(st.measured_at) : (st.status||'pending'));
            if(st.message){ txt.title = String(st.message).substring(0,300); }
            span.appendChild(txt);
            srcBox.appendChild(span);
        });
    }
    if(!hasData) return;
    if(typeof window.Chart === 'undefined') return;
    var colors = ['#36A2EB','#FFCE56','#9966FF','#4BC0C0','#FF9F40','#2ECC71','#E74C3C','#1ABC9C'];
    var datasets = appKeys.map(function(k,i){
        return {
            label: AP_STORAGE_LABELS[k]||k,
            data: ordered.map(function(m){ return (rows[k][m]?rows[k][m].bytes:0); }),
            backgroundColor: colors[i%colors.length]+'cc',
            borderColor: colors[i%colors.length],
            borderWidth: 1,
            borderRadius: 3,
            maxBarThickness: 46
        };
    });
    var tc = getApThemeColors();
    apStorageUsageChart = new window.Chart(canvas, {
        type: 'bar',
        data: { labels: ordered.map(function(m){ var n = users[m]||''; return n ? n : m; }), datasets: datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 250 },
            plugins: {
                legend: { display: true, position: 'top', labels: { color: tc.text, boxWidth: 10, font: { size: 10 } } },
                tooltip: {
                    callbacks: {
                        title: function(items){ var m = ordered[items[0].dataIndex]; return (users[m]||m); },
                        label: function(ctx){ return ctx.dataset.label+': '+apFormatBytes(ctx.parsed.y); },
                        footer: function(items){
                            var sum = items.reduce(function(acc,it){ return acc+(it.parsed.y||0); },0);
                            return T.storage_usage_total+': '+apFormatBytes(sum);
                        }
                    }
                }
            },
            scales: {
                x: { stacked: true, ticks: { color: tc.text, font: { size: 9 }, maxRotation: 45, maxTicksLimit: 20 }, grid: { color: tc.grid } },
                y: { stacked: true, beginAtZero: true, ticks: { color: tc.text, font: { size: 9 }, maxTicksLimit: 6, callback: function(v){ return apFormatBytes(v); } }, grid: { color: tc.grid } }
            }
        }
    });
    if(canvas.setAttribute) canvas.setAttribute('title', ordered.map(function(m){
        return (users[m]||m)+' ('+apFormatBytes(totals[m])+')';
    }).join('\n'));
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

/* ── Dialog helpers ── */
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

/* ── Boot ── */
if(openBtn) openBtn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();openAdminPanel('ap-users');});
var initH=location.hash.match(/^#adminpanel\/(.+)$/);
if(initH) openAdminPanel(hashPanel[initH[1]]||'ap-users');
(function(){
    var submit=document.getElementById('ap-gate-submit');
    var cancel=document.getElementById('ap-gate-cancel');
    var pwd=document.getElementById('ap-gate-password');
    var toggle=document.getElementById('ap-gate-password-toggle');
    function doGateLogin(){
        var pass=pwd?pwd.value:'';
        if(!pass){ apGateShow(<?php echo json_encode($translations['password_required'] ?? 'Password required', JSON_HEX_TAG); ?>); return; }
        submit.disabled=true;
        apRefreshCsrf().then(function(){ return apGateLogin(pass); }).then(function(d){
            submit.disabled=false;
            if(d&&d.csrf) apRememberCsrf(d.csrf);
            if(d&&d.success){ apGateHide(); loadPanel(window._apPendingPanel||'ap-users'); return; }
            var msg;
            if(d&&d.error==='invalid_credentials') msg=<?php echo json_encode($translations['invalid_credentials'] ?? 'Invalid credentials', JSON_HEX_TAG); ?>;
            else if(d&&d.error==='csrf_forbidden') msg=<?php echo json_encode($translations['csrf_forbidden'] ?? 'Security check failed. Refresh the page and try again.', JSON_HEX_TAG); ?>;
            else if(d&&d.error==='admin_ip_forbidden') msg=<?php echo json_encode($translations['admin_ip_forbidden'] ?? 'Administrator access is not allowed from this IP.', JSON_HEX_TAG); ?>;
            else msg=(d&&d.error)?d.error:'Login failed';
            apGateShow(msg);
        }).catch(function(){ submit.disabled=false; apGateShow('Network error'); });
    }
    if(submit) submit.addEventListener('click',doGateLogin);
    if(toggle&&pwd) toggle.addEventListener('click',function(){
        var show=pwd.type!=='text';
        pwd.type=show?'text':'password';
        toggle.classList.toggle('active',show);
        var label=show?<?php echo json_encode($translations['hide_password'] ?? 'Hide password', JSON_HEX_TAG); ?>:<?php echo json_encode($translations['show_password'] ?? 'Show password', JSON_HEX_TAG); ?>;
        toggle.title=label;
        toggle.setAttribute('aria-label',label);
        toggle.setAttribute('aria-pressed',show?'true':'false');
        pwd.focus();
    });
    if(pwd) pwd.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); doGateLogin(); } });
    if(cancel) cancel.addEventListener('click',function(){ closeAdminPanel(); });
})();

})();
<?php endif; ?>
</script>

<?php endif; ?>
