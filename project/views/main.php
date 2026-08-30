<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
    <title><?php echo my_htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" href="/project/views/icon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php require __DIR__ . '/../mobile_chrome.php'; ?>
    <?php require_once __DIR__ . '/../browser-icons.php'; ?>
    <?php if($path==='/main' && !$showLogin): ?>
    <script src="https://unpkg.com/qrcodejs@1.0.0/qrcode.min.js" defer></script>
    <?php endif; ?>
    <style>
        :root{--bg-color:<?php echo $config['theme']==='dark'?'#0f1419':'#f0f4f8';?>;--text-color:<?php echo $config['theme']==='dark'?'#f4f7fb':'#1a2332';?>;--primary-color:<?php echo my_htmlspecialchars($config['color']);?>;--sidebar-bg:<?php echo $config['theme']==='dark'?'rgba(16,22,30,0.58)':'rgba(240,244,248,0.20)';?>;--group-block-bg:<?php echo $config['theme']==='dark'?'rgba(255,255,255,0.10)':'rgba(255,255,255,0.20)';?>;--app-item-bg:<?php echo $config['theme']==='dark'?'rgba(255,255,255,0.12)':'rgba(255,255,255,0.20)';?>;--icon-bg-color:<?php echo $config['theme']==='dark'?'rgba(18,24,32,0.8)':'rgba(255,255,255,0.20)';?>;--tooltip-bg:<?php echo $config['theme']==='dark'?'rgba(18,22,28,0.82)':'rgba(255,255,255,0.88)';?>;--modal-bg:<?php echo $config['theme']==='dark'?'rgba(18,24,32,0.62)':'rgba(255,255,255,0.68)';?>;--border-color:<?php echo $config['theme']==='dark'?'rgba(255,255,255,0.14)':'rgba(15,30,50,0.14)';?>;--icon-bg-color:<?php echo $config['theme']==='dark'?'rgba(18,24,32,0.8)':'rgba(255,255,255,0.8)';?>;--link-color:<?php echo $config['theme']==='dark'?'#e8eef5':'#1a2332';?>;--link-hover-color:var(--primary-color);--table-header-bg:<?php echo $config['theme']==='dark'?'rgba(255,255,255,0.08)':'rgba(255,255,255,0.78)';?>;--table-row-hover:<?php echo $config['theme']==='dark'?'rgba(255,255,255,0.10)':'rgba(0,0,0,0.05)';?>;--scrollbar-track:<?php echo $config['theme']==='dark'?'rgba(255,255,255,0.04)':'rgba(0,0,0,0.04)';?>;--scrollbar-thumb:<?php echo $config['theme']==='dark'?'rgba(255,255,255,0.22)':'rgba(0,0,0,0.2)';?>;--scrollbar-thumb-hover:<?php echo $config['theme']==='dark'?'rgba(255,255,255,0.35)':'rgba(0,0,0,0.35)';?>;--glass-blur:56px;--glass-sat:180%;--glass-shadow:<?php echo $config['theme']==='dark'?'0 18px 50px rgba(0,0,0,0.45)':'0 18px 50px rgba(30,50,80,0.14)';?>;--casa-radius:20px;--casa-tile-radius:22px;--app-icon-grad-start:<?php echo $config['theme']==='dark'?'color-mix(in srgb,var(--primary-color) 72%,white)':'color-mix(in srgb,var(--primary-color) 84%,black)';?>;--app-icon-grad-end:<?php echo $config['theme']==='dark'?'color-mix(in srgb,var(--primary-color) 88%,black)':'color-mix(in srgb,var(--primary-color) 65%,black)';?>;--shell-menu-button-left:<?php echo (int)($selectedAppMenuButton['left']??20); ?>px;--shell-menu-button-top:<?php echo (int)($selectedAppMenuButton['top']??13); ?>px;--shell-menu-button-width:<?php echo (int)($selectedAppMenuButton['width']??40); ?>px;--shell-menu-button-height:<?php echo (int)($selectedAppMenuButton['height']??40); ?>px;--shell-menu-button-mobile-left:<?php echo (int)($selectedAppMenuButtonMobile['left']??($selectedAppMenuButton['left']??20)); ?>px;--shell-menu-button-mobile-top:<?php echo (int)($selectedAppMenuButtonMobile['top']??($selectedAppMenuButton['top']??13)); ?>px;--shell-menu-button-mobile-width:<?php echo (int)($selectedAppMenuButtonMobile['width']??($selectedAppMenuButton['width']??40)); ?>px;--shell-menu-button-mobile-height:<?php echo (int)($selectedAppMenuButtonMobile['height']??($selectedAppMenuButton['height']??40)); ?>px;}
        /* Global styled scrollbars */
        *{scrollbar-width:thin;scrollbar-color:var(--scrollbar-thumb) var(--scrollbar-track);}
        *::-webkit-scrollbar{width:6px;height:6px;}
        *::-webkit-scrollbar-track{background:var(--scrollbar-track);border-radius:3px;}
        *::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb);border-radius:3px;}
        *::-webkit-scrollbar-thumb:hover{background:var(--scrollbar-thumb-hover);}
        *::-webkit-scrollbar-corner{background:var(--scrollbar-track);}
        /* Auth top bar styles */
        :root{--auth-topbar-h:65px;}
        #auth-top-bar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;backdrop-filter:blur(16px) saturate(1.5);-webkit-backdrop-filter:blur(16px) saturate(1.5);position:fixed;top:0;left:0;right:0;z-index:12000;height:var(--auth-topbar-h);box-sizing:border-box;}
        body.dark-theme #auth-top-bar{background:rgba(12,22,42,0.85);border-bottom:1px solid rgba(255,255,255,0.12);}
        body.light-theme #auth-top-bar{background:rgba(212,232,250,0.92);border-bottom:1px solid rgba(28,66,110,0.16);}
        .auth-top-bar-left{display:flex;align-items:center;}
        .auth-brand-link{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:12px;border:2px solid #7d9cc0;background:transparent;color:#7d9cc0 !important;transition:opacity .2s,transform .2s;}
        body.light-theme .auth-brand-link{border-color:#2f6fc4;color:#2f6fc4 !important;}
        .auth-brand-link:hover{opacity:.9;transform:translateY(-1px);}
        .auth-brand-link svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:2;}
        .auth-top-bar-right{display:flex;align-items:center;gap:12px;}

        /* Reuse theme-toggle and lang-selector styles from home page */
        #auth-top-bar .theme-toggle{display:flex;gap:3px;background:rgba(255,255,255,0.08);border-radius:30px;padding:4px;}
        #auth-top-bar .theme-toggle button{padding:6px 14px;border:none;background:transparent;color:#eaf2fb !important;font-size:12px;font-weight:600;border-radius:26px;cursor:pointer;transition:background .2s,color .2s;}
        #auth-top-bar .theme-toggle button.active{background:rgba(255,255,255,0.22);}
        body.light-theme #auth-top-bar .theme-toggle{background:rgba(30,80,140,0.08);}
        body.light-theme #auth-top-bar .theme-toggle button{color:#16324f !important;}
        body.light-theme #auth-top-bar .theme-toggle button.active{background:rgba(30,80,140,0.18);}

        #auth-top-bar .lang-selector{position:relative;font-size:13px;font-weight:500;}
        #auth-top-bar .lang-selector-btn{display:flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.18);border-radius:30px;color:#eaf2fb !important;cursor:pointer;transition:border-color .2s,box-shadow .2s,background .2s;}
        #auth-top-bar .lang-selector-btn:hover,#auth-top-bar .lang-selector.open .lang-selector-btn{border-color:rgba(255,255,255,0.58);box-shadow:0 0 0 3px rgba(74,144,226,.18);outline:none;}
        body.light-theme #auth-top-bar .lang-selector-btn{background:rgba(255,255,255,0.55);border-color:rgba(30,80,140,0.28);color:#16324f !important;}
        body.light-theme #auth-top-bar .lang-selector-btn:hover,body.light-theme #auth-top-bar .lang-selector.open .lang-selector-btn{border-color:rgba(30,80,140,0.55);}
        #auth-top-bar .lang-selector-label{flex:1;text-align:left;white-space:nowrap;}
        #auth-top-bar .lang-selector-chevron{width:14px;height:14px;flex-shrink:0;transition:transform .2s;stroke:#eaf2fb;opacity:.8;}
        body.light-theme #auth-top-bar .lang-selector-chevron{stroke:#16324f;}
        #auth-top-bar .lang-selector.open .lang-selector-chevron{transform:translateY(-35%) rotate(225deg);}
        #auth-top-bar .lang-selector-menu{position:absolute;top:calc(100% + 6px);right:0;min-width:160px;background:rgba(16,26,45,0.96);border:1px solid rgba(255,255,255,0.08);border-radius:12px;box-shadow:0 18px 45px rgba(0,0,0,.35);padding:6px;opacity:0;transform:translateY(-8px) scale(.98);pointer-events:none;transition:opacity .2s,transform .2s;z-index:200;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);}
        body.light-theme #auth-top-bar .lang-selector-menu{background:#eef6fd;border-color:rgba(30,80,140,0.14);box-shadow:0 18px 45px rgba(28,66,110,.16);}
        #auth-top-bar .lang-selector.open .lang-selector-menu{opacity:1;transform:translateY(0) scale(1);pointer-events:auto;}
        #auth-top-bar .lang-selector-option{display:flex;align-items:center;gap:8px;width:100%;padding:8px 10px;border:none;background:transparent;color:#eaf2fb !important;font-size:13px;text-align:left;border-radius:8px;cursor:pointer;transition:background .15s;}
        #auth-top-bar .lang-selector-option:hover,#auth-top-bar .lang-selector-option.active{background:rgba(255,255,255,.1);}
        body.light-theme #auth-top-bar .lang-selector-option{color:#16324f !important;}
        body.light-theme #auth-top-bar .lang-selector-option:hover,body.light-theme #auth-top-bar .lang-selector-option.active{background:rgba(30,80,140,.08);}
        #auth-top-bar .lang-flag{display:inline-block;width:20px;height:14px;background-size:cover;background-position:center;}
        #auth-top-bar .lang-flag-en{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 60 30'%3E%3CclipPath id='s'%3E%3Cpath d='M0,0 v30 h60 v-30 z'/%3E%3C/clipPath%3E%3CclipPath id='t'%3E%3Cpath d='M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z'/%3E%3C/clipPath%3E%3Cg clip-path='url(%23s)'%3E%3Cpath d='M0,0 v30 h60 v-30 z' fill='%23012169'/%3E%3Cpath d='M0,0 L60,30 M60,0 L0,30' stroke='%23fff' stroke-width='6'/%3E%3Cpath d='M0,0 L60,30 M60,0 L0,30' clip-path='url(%23t)' stroke='%23C8102E' stroke-width='4'/%3E%3Cpath d='M30,0 v30 M0,15 h60' stroke='%23fff' stroke-width='10'/%3E%3Cpath d='M30,0 v30 M0,15 h60' stroke='%23C8102E' stroke-width='6'/%3E%3C/g%3E%3C/svg%3E");}
        #auth-top-bar .lang-flag-uk{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 12'%3E%3Crect fill='%230057b8' width='20' height='6'/%3E%3Crect fill='%23ffd800' y='6' width='20' height='6'/%3E%3C/svg%3E");}
        #auth-top-bar .lang-flag-ru{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 9 6'%3E%3Crect fill='%23fff' width='9' height='6'/%3E%3Crect fill='%230039a6' y='2' width='9' height='2'/%3E%3Crect fill='%23d52b1e' y='4' width='9' height='2'/%3E%3C/svg%3E");}
        #auth-top-bar .lang-flag-be{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 40'%3E%3Crect fill='%23d31145' width='64' height='40'/%3E%3Crect fill='%23009739' y='26.67' width='64' height='13.33'/%3E%3C/svg%3E");}
        #auth-top-bar .lang-flag-pl{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 9 6'%3E%3Crect fill='%23fff' width='9' height='6'/%3E%3Crect fill='%23dc143c' y='3' width='9' height='3'/%3E%3C/svg%3E");}
        #auth-top-bar .lang-flag-cs{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 20'%3E%3Crect fill='%23ffffff' width='30' height='20'/%3E%3Crect fill='%23d7141a' y='10' width='30' height='10'/%3E%3Cpolygon points='0,0 15,10 0,20' fill='%2311457e'/%3E%3C/svg%3E");}
        #auth-top-bar .lang-flag-by{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 40'%3E%3Crect fill='%23d31145' width='64' height='40'/%3E%3Crect fill='%23009739' y='26.67' width='64' height='13.33'/%3E%3C/svg%3E");}

        @media(max-width:480px){:root{--auth-topbar-h:61px;}#auth-top-bar{padding:10px 16px;}}
        #auth-top-bar .lang-selector-menu{right:-20px;}

        <?php if($showLogin): ?>
        #auth-modal{display:flex;position:fixed;top:var(--auth-topbar-h);left:0;width:100%;height:calc(100% - var(--auth-topbar-h));justify-content:center;align-items:safe center;z-index:10050;background:var(--auth-gradient);backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);overflow-y:auto;}
        #auth-modal .gradient-bg{position:absolute;top:0;left:0;width:100%;height:100%;background:var(--auth-gradient);opacity:1;z-index:0;pointer-events:none;}
        #auth-form{background:linear-gradient(180deg, color-mix(in srgb, var(--modal-bg) 90%, white), var(--modal-bg));padding:30px;border-radius:var(--casa-radius);color:var(--text-color);width:calc(100% - 40px);max-width:400px;box-shadow:var(--glass-shadow);margin:20px 0;position:relative;z-index:1;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid color-mix(in srgb, var(--border-color) 50%, white);}
        .auth-tabs{display:flex;margin-bottom:20px;border-bottom:1px solid var(--border-color);background:var(--modal-bg);border-radius:var(--casa-radius) var(--casa-radius) 0 0;}
        .auth-tab{flex:1;padding:12px;text-align:center;background:none;border:none;color:var(--text-color);cursor:pointer;border-bottom:2px solid transparent;}
        .auth-tab.active{border-bottom-color:<?php echo $config['theme']==='dark'?'#6b7280':'#3b82f6';?>;color:<?php echo $config['theme']==='dark'?'#f4f7fb':'#1a2332';?>;}
        .auth-content{display:none;}
        .auth-content.active{display:block;}
        #content,#top-bar,#sidebar,#settings-modal,#profile-modal,#notifications-panel{pointer-events:none!important;}
        #auth-modal,#auth-modal *{pointer-events:auto;}
        #auth-modal .gradient-bg{pointer-events:none!important;}
        <?php if(!$themeFromUrl): ?>
        @media(prefers-color-scheme:dark){
            :root{--bg-color:#0f1419;--text-color:#f4f7fb;--sidebar-bg:rgba(16,22,30,0.58);--group-block-bg:rgba(255,255,255,0.10);--app-item-bg:rgba(255,255,255,0.12);--tooltip-bg:rgba(18,22,28,0.82);--modal-bg:rgba(24,30,38,0.7);--border-color:rgba(255,255,255,0.14);--icon-bg-color:rgba(18,24,32,0.8);--link-color:#e8eef5;--table-header-bg:rgba(255,255,255,0.08);--table-row-hover:rgba(255,255,255,0.10);--scrollbar-track:rgba(255,255,255,0.04);--scrollbar-thumb:rgba(255,255,255,0.22);--scrollbar-thumb-hover:rgba(255,255,255,0.35);--glass-shadow:0 18px 50px rgba(0,0,0,0.45);--app-icon-grad-start:color-mix(in srgb,var(--primary-color) 72%,white);--app-icon-grad-end:color-mix(in srgb,var(--primary-color) 88%,black);--auth-gradient:linear-gradient(135deg, #07111e, #0c2035, #0e2a40);}
        }
        @media(prefers-color-scheme:light){
            :root{--bg-color:#e8eef5;--text-color:#1a2332;--sidebar-bg:rgba(255,255,255,0.62);--group-block-bg:rgba(255,255,255,0.20);--app-item-bg:rgba(255,255,255,0.20);--tooltip-bg:rgba(255,255,255,0.88);--modal-bg:rgba(255,255,255,0.68);--border-color:rgba(15,30,50,0.14);--icon-bg-color:rgba(255,255,255,0.20);--link-color:#1a2332;--table-header-bg:rgba(255,255,255,0.78);--table-row-hover:rgba(0,0,0,0.05);--scrollbar-track:rgba(0,0,0,0.04);--scrollbar-thumb:rgba(0,0,0,0.2);--scrollbar-thumb-hover:rgba(0,0,0,0.35);--glass-shadow:0 18px 50px rgba(30,50,80,0.14);--app-icon-grad-start:color-mix(in srgb,var(--primary-color) 84%,black);--app-icon-grad-end:color-mix(in srgb,var(--primary-color) 65%,black);--auth-gradient:linear-gradient(135deg, #edf4fb, #d5eaf5, #c2dff0);}
        }
        <?php endif; ?>
        :root{--auth-gradient:linear-gradient(135deg, #07111e, #0c2035, #0e2a40);}
        body.light-theme{--auth-gradient:linear-gradient(135deg, #edf4fb, #d5eaf5, #c2dff0);}
        <?php endif; ?>
        .auth-input{width:100%;padding:12px;margin-bottom:15px;border:1px solid var(--border-color);border-radius:12px;background:color-mix(in srgb,var(--modal-bg) 70%,transparent);color:var(--text-color);box-sizing:border-box;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);}
        .auth-button{width:100%;padding:12px;background:linear-gradient(135deg,#3578d0,#4a90e2);color:#fff!important;font-weight:700;border:none;border-radius:50px;cursor:pointer;margin-bottom:10px;}
        body.light-theme .auth-button{background:linear-gradient(135deg,#1a5fa8,#2472c0);}
        .auth-button:hover{opacity:0.9;}
        .auth-link{color:<?php echo $config['theme']==='dark'?'#9ca3af':'#2563eb';?>;cursor:pointer;text-decoration:underline;}
        .auth-error{color:#ff4444;margin-bottom:15px;text-align:center;}
        .auth-success{color:#00C851;margin-bottom:15px;text-align:center;}
        <?php if($showLogin): ?>
        .google-user-info{display:flex;flex-direction:column;align-items:center;margin:20px 0;padding:20px;background:var(--group-block-bg);border-radius:14px;border:1px solid var(--border-color);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);}
        .google-avatar{width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--primary-color);margin-bottom:10px;}
        .google-email{font-weight:bold;margin-bottom:5px;}
        .google-name{color:var(--text-color);opacity:0.8;}
        .oauth-providers-bar{display:flex;align-items:center;justify-content:center;gap:18px;margin:18px 0 10px;}
        .oauth-provider-btn{display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:14px;border:1px solid var(--border-color);background:var(--group-block-bg);text-decoration:none;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease;box-shadow:0 4px 14px rgba(0,0,0,.08);}
        .oauth-provider-btn:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(0,0,0,.14);}
        .oauth-provider-btn svg{width:24px;height:24px;display:block;}
        .oauth-provider-btn.oauth-github{background:transparent;border-color:var(--border-color);}

        .oauth-provider-btn.oauth-google{background:transparent;border-color:var(--border-color);}
        <?php endif; ?>
        .oauth-accounts-list{display:flex;flex-direction:column;gap:8px;margin-top:10px;}
        .oauth-account-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border-color);border-radius:12px;background:var(--group-block-bg);}
        .oauth-account-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
        .oauth-account-icon svg{width:18px;height:18px;}
        .oauth-account-icon.oauth-github{background:#24292f;color:#fff;}
        .oauth-account-icon.oauth-google{background:#fff;border:1px solid rgba(0,0,0,.08);}
        .oauth-account-icon.oauth-email{background:color-mix(in srgb,var(--primary-color) 18%,transparent);color:var(--primary-color);}
        .oauth-account-meta{flex:1;min-width:0;}
        .oauth-account-title{font-weight:600;font-size:13px;line-height:1.2;}
        .oauth-account-sub{font-size:11px;opacity:.65;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;}
        .oauth-account-actions{display:flex;align-items:center;gap:6px;flex-shrink:0;}
        .oauth-status-icon{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;}
        .oauth-status-icon svg{width:14px;height:14px;display:block;}
        .oauth-status-icon.connected{background:color-mix(in srgb,#22c55e 18%,transparent);color:#16a34a;}
        .oauth-status-icon.disconnected{background:color-mix(in srgb,var(--text-color) 8%,transparent);color:color-mix(in srgb,var(--text-color) 42%,transparent);}
        .oauth-action-btn{width:28px;height:28px;border-radius:50%;border:1px solid var(--border-color);background:transparent;color:var(--text-color);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:0;text-decoration:none;flex-shrink:0;transition:background-color .15s ease,border-color .15s ease,color .15s ease;}
        .oauth-action-btn svg{width:14px;height:14px;display:block;}
        .oauth-action-btn:hover{background:color-mix(in srgb,var(--text-color) 8%,transparent);}
        .oauth-action-btn.connect{color:var(--primary-color);border-color:color-mix(in srgb,var(--primary-color) 35%,transparent);}
        .oauth-action-btn.unlink{color:#ef4444;border-color:rgba(239, 68, 68, 0.35);}
        .oauth-action-btn.unlink:hover{background:color-mix(in srgb,#ef4444 12%,transparent);}
        .mcp-token-table{width:100%;border-collapse:collapse;font-size:12px;margin:8px 0 14px;}
        .mcp-token-table th,.mcp-token-table td{border-bottom:1px solid var(--border-color);padding:8px 6px;text-align:left;vertical-align:middle;}
        .mcp-token-table th{font-weight:600;opacity:.8;}
        .mcp-token-alert{width:100%;box-sizing:border-box;background:color-mix(in srgb,#22c55e 16%,transparent);border:1px solid color-mix(in srgb,#22c55e 45%,transparent);border-radius:8px;padding:12px;margin:8px 0 14px;text-align:left;}
        .mcp-token-alert code{display:block;word-break:break-all;margin-top:8px;font-size:12px;}
        .mcp-token-form{display:flex;gap:8px;width:100%;max-width:420px;align-items:center;}
        .mcp-token-form .auth-input{margin:0;}
        .mcp-token-form .profile-action-button{width:auto;margin:0;white-space:nowrap;}
        .mcp-token-revoke{background:#ef4444;color:white;border:none;border-radius:18px;padding:7px 10px;cursor:pointer;}
        .password-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:<?php echo $config['theme']==='dark'?'#6b7280':'#3b82f6';?>;cursor:pointer;padding:5px;border-radius:4px;transition:background-color 0.3s;z-index:5;}
        .password-toggle:hover{background-color:rgba(0,0,0,0.1);}
        .password-toggle:active{background-color:rgba(0,0,0,0.2);}
        .password-input-container{position:relative;margin-bottom:15px;}
        .password-input-container .auth-input{padding-right:40px;margin-bottom:0;}
        .password-input-row{display:flex;align-items:stretch;gap:8px;margin-bottom:15px;}
        .password-input-row .password-input-container{flex:1;margin-bottom:0;}
        .password-generate-btn{flex-shrink:0;width:44px;min-height:44px;align-self:stretch;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--primary-color);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;transition:background-color 0.2s,opacity 0.2s;}
        .password-generate-btn:hover:not(:disabled){background:var(--group-block-bg);}
        .password-generate-btn:disabled{opacity:0.45;cursor:not-allowed;}
        .password-toggle svg{fill:<?php echo $config['theme']==='dark'?'#6b7280':'#3b82f6';?>;}
        .user-profile{display:flex;align-items:center;gap:8px;cursor:pointer;padding:4px 8px;border-radius:20px;transition:background-color 0.3s;position:relative;height:40px;overflow:visible;flex-shrink:0;}
        .user-profile:hover{background-color:var(--group-block-bg);}
        .user-avatar{width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid var(--primary-color);flex-shrink:0;}
        .user-name{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:var(--tooltip-bg);color:white;padding:4px 8px;border-radius:4px;display:none;z-index:10000;margin-bottom:8px;white-space:nowrap;pointer-events:none;box-shadow:0 2px 10px rgba(0,0,0,0.2);}
        .user-profile:hover .user-name{display:block;}
        .user-name::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:var(--tooltip-bg);}
        .logout-button{background:#ff4444;color:white;border:none;padding:10px 15px;border-radius:24px;cursor:pointer;width:100%;margin-top:10px;display:flex;align-items:center;justify-content:center;gap:8px;}
        .change-password-button{background:var(--primary-color);color:white;border:none;padding:12px 20px;border-radius:24px;cursor:pointer;width:60%;max-width:300px;margin:10px auto;display:flex;align-items:center;justify-content:center;gap:10px;font-weight:600;transition:all 0.3s ease;}
        .change-password-button:hover{opacity:0.9;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
        body.light-theme .change-password-button{color:#fff !important;}
        .change-photo-button{background:var(--primary-color);color:white;border:none;padding:12px 20px;border-radius:24px;cursor:pointer;width:60%;max-width:300px;margin:10px auto;display:flex;align-items:center;justify-content:center;gap:10px;font-weight:600;transition:all 0.3s ease;}
        .change-photo-button:hover{opacity:0.9;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
        body.light-theme .change-photo-button{color:#fff !important;}
        #profile-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:10000;}
        #profile-content{background:var(--modal-bg);padding:0;border-radius:var(--casa-radius);color:var(--text-color);width:50%;max-width:500px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;position:relative;margin:2% auto;z-index:10001;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        @media (max-width: 768px) {
            #profile-content { margin: 5% auto; width: 95%; }
            #edit-stats-content, #stats-content, #shared-files-content, #sessions-content, #users-content { margin: 5% auto; }
        }
        #close-profile{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:24px;cursor:pointer;padding:0;z-index:10002;width:40px;height:40px;min-width:40px;min-height:40px;display:inline-flex;align-items:center;justify-content:center;line-height:1;box-sizing:border-box;}
        .profile-section{margin-bottom:25px;padding-bottom:20px;border-bottom:1px solid var(--border-color);display:flex;flex-direction:column;align-items:center;}
        .profile-section:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0;}
        .profile-avatar{width:100px;height:100px;border-radius:50%;object-fit:cover;display:block;margin:0 auto 15px;border:3px solid var(--primary-color);}
        .profile-actions{display:flex;flex-direction:column;align-items:center;gap:10px;}
        .profile-action-button{padding:12px 20px;background:var(--primary-color);color:white;border:none;border-radius:24px;cursor:pointer;text-align:center;font-weight:600;transition:all 0.3s ease;width:60%;max-width:300px;margin:0 auto 10px auto;display:block;}
        .profile-action-button:hover{opacity:0.9;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
        .email-row{display:inline-flex;align-items:center;gap:6px;}
        .copy-email-btn{background:none;border:none;padding:2px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;border-radius:4px;transition:all 0.3s ease;vertical-align:middle;}
        .copy-email-btn svg{width:16px;height:16px;color:color-mix(in srgb,var(--text-color) 50%,var(--primary-color));stroke:currentColor;transition:all 0.3s ease;}
        .copy-email-btn:hover svg{opacity:0.7;}
        .copy-email-btn.copied svg{stroke:#22c55e;animation:copyPulse 0.4s ease;}
        .copy-email-btn.failed svg{stroke:#ef4444;animation:copyShake 0.4s ease;}
        @keyframes copyPulse{0%{transform:scale(1);}50%{transform:scale(1.3);}100%{transform:scale(1);}}
        @keyframes copyShake{0%{transform:translateX(0);}25%{transform:translateX(-3px);}75%{transform:translateX(3px);}100%{transform:translateX(0);}}
        .password-form, .photo-form{display:none;margin-top:15px;padding:15px;background:var(--group-block-bg);border-radius:6px;width:100%;max-width:400px;}
        .password-form.active, .photo-form.active{display:block;}
        .password-change-box{display:none;margin-top:15px;padding:30px;background:var(--group-block-bg);border:1px solid var(--border-color);border-radius:8px;width:100%;max-width:400px;box-sizing:border-box;}
        .password-change-box.active{display:block;}
        .password-change-box .password-form{display:block;margin:0;padding:0;background:transparent;border-radius:0;width:100%;max-width:none;}
        .password-verify-msg{font-size:13px;margin:-6px 0 10px 0;min-height:18px;text-align:left;}
        .password-new-fields input:disabled{opacity:0.55;cursor:not-allowed;}
        body{margin:0;padding:0;font-family:"Segoe UI","SF Pro Display",system-ui,-apple-system,sans-serif;background-color:var(--bg-color);color:var(--text-color);overflow:hidden;background-size:cover;background-position:center;background-attachment:fixed;background-repeat:no-repeat;}
        body.wallpaper-default{background-image:radial-gradient(ellipse at 18% 12%,color-mix(in srgb,var(--primary-color) 28%,transparent) 0%,transparent 52%),radial-gradient(ellipse at 82% 88%,color-mix(in srgb,var(--primary-color) 18%,transparent) 0%,transparent 48%),linear-gradient(160deg,color-mix(in srgb,var(--bg-color) 88%,transparent) 0%,color-mix(in srgb,var(--primary-color) 12%,transparent) 100%);}
        body.wallpaper-custom{background-image:linear-gradient(180deg,rgba(255,255,255,0.06),rgba(248,250,252,0.10));}
        body.wallpaper-custom.theme-dark{background-image:linear-gradient(180deg,rgba(8,12,18,0.08),rgba(8,12,18,0.14));}
        body.wallpaper-custom.theme-light{background-image:linear-gradient(180deg,rgba(255,255,255,0.08),rgba(248,250,252,0.12));}
        html,body{margin:0;padding:0;overflow:hidden;height:100%;}
        @keyframes shellAppBackgroundFade{from{background-color:var(--bg-color);}to{background-color:#05070a;}}
        body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background:
          radial-gradient(ellipse at 12% 18%, color-mix(in srgb,var(--primary-color) 28%,transparent) 0%, transparent 42%),
          radial-gradient(ellipse at 88% 12%, color-mix(in srgb,var(--primary-color) 18%,transparent) 0%, transparent 40%),
          radial-gradient(ellipse at 70% 88%, color-mix(in srgb,var(--primary-color) 16%,transparent) 0%, transparent 45%),
          linear-gradient(160deg, color-mix(in srgb,var(--bg-color) 88%,#000) 0%, var(--bg-color) 45%, color-mix(in srgb,var(--bg-color) 82%,var(--primary-color)) 100%);
        }
        body.shell-app-open{animation:shellAppBackgroundFade 140ms ease-out both;}
        body.shell-app-open::before{animation:shellLayerFadeOut 140ms ease-out both;}
        body.shell-app-open.wallpaper-custom::after{animation:shellLayerFadeOut 140ms ease-out both;}
        @keyframes shellLayerFadeOut{from{opacity:1;}to{opacity:0;}}
        body.wallpaper-custom::before{opacity:0!important;}
        body.wallpaper-custom::after{content:'';position:fixed;inset:-8px;z-index:-1;pointer-events:none;background-image:var(--wallpaper-url);background-size:cover;background-position:center;background-repeat:no-repeat;filter:blur(3px);transform:scale(1.01);opacity:1;}
        #top-bar,#sidebar,#content{z-index:1;}
        #top-bar{position:fixed;top:0;left:0;width:100%;height:54px;background:var(--sidebar-bg);border-bottom:1px solid var(--border-color);display:flex;align-items:center;padding:0 16px 0 20px;box-sizing:border-box;z-index:998;gap:12px;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));box-shadow:0 8px 28px rgba(0,0,0,0.08);}
        body.light-theme #top-bar{background:rgba(240,244,248,0.20);}
        body.light-theme #sidebar{background:rgba(240,244,248,0.20);}
        #top-bar-title{font-weight:bold;font-size:18px;flex:1;min-width:0;text-align:center;position:absolute;left:50%;transform:translateX(-50%);}
        #support-chat-btn{position:relative;top:auto;right:auto;margin-left:auto;flex-shrink:0;width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 28%,transparent) 0%,color-mix(in srgb,var(--primary-color) 14%,transparent) 100%);color:var(--primary-color);border:1px solid color-mix(in srgb,var(--primary-color) 35%,transparent);box-shadow:0 2px 10px color-mix(in srgb,var(--primary-color) 22%,transparent);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.2s,box-shadow 0.2s,background 0.2s;backdrop-filter:blur(8px);}
        #notifications-btn{position:relative;top:auto;right:auto;margin-left:auto;flex-shrink:0;width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 22%,transparent) 0%,color-mix(in srgb,var(--primary-color) 10%,transparent) 100%);color:var(--primary-color);border:1px solid color-mix(in srgb,var(--primary-color) 32%,transparent);box-shadow:0 2px 10px color-mix(in srgb,var(--primary-color) 18%,transparent);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.2s,box-shadow 0.2s,background 0.2s;backdrop-filter:blur(8px);}
        #notifications-btn:hover{transform:scale(1.06);box-shadow:0 4px 14px color-mix(in srgb,var(--primary-color) 26%,transparent);background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 34%,transparent) 0%,color-mix(in srgb,var(--primary-color) 18%,transparent) 100%);}
        #notifications-btn svg{width:22px;height:22px;stroke:currentColor;}
        #notifications-btn + #support-chat-btn{margin-left:0;}
        #support-chat-btn:hover{transform:scale(1.06);box-shadow:0 4px 14px color-mix(in srgb,var(--primary-color) 30%,transparent);background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 40%,transparent) 0%,color-mix(in srgb,var(--primary-color) 22%,transparent) 100%);}
        #support-chat-btn svg{width:22px;height:22px;stroke:currentColor;}
	        #menu-button{position:fixed;top:var(--shell-menu-button-top);left:var(--shell-menu-button-left);cursor:pointer;color:var(--text-color);z-index:1002;padding:0;border:none;outline:none;width:var(--shell-menu-button-width);height:var(--shell-menu-button-height);transition:opacity 0.15s ease,visibility 0.15s ease,transform 0.2s,box-shadow 0.2s,background 0.2s;}
        body.app-modal-open #menu-button{opacity:0;visibility:hidden;pointer-events:none;z-index:0;transition:none;}
	        <?php if(!empty($selectedAppMenuButtonScroll)): ?>#menu-button{position:absolute;}<?php endif; ?>
	        <?php foreach(($selectedAppMenuButtonStyle??[]) as $prop=>$value): ?>#menu-button{<?php echo my_htmlspecialchars((string)$prop); ?>:<?php echo my_htmlspecialchars((string)$value); ?>;}<?php endforeach; ?>
	        #menu-button .menu-button-glyph{display:flex;align-items:center;justify-content:center;line-height:1;}
        <?php if($isHomeDashboard): ?>
        #top-bar{justify-content:flex-start;}
        #top-bar-title{flex:0 0 auto;margin-left:12px;}
        #menu-button{position:static;width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 22%,transparent) 0%,color-mix(in srgb,var(--primary-color) 10%,transparent) 100%);color:var(--primary-color);border:1px solid color-mix(in srgb,var(--primary-color) 32%,transparent);box-shadow:0 2px 10px color-mix(in srgb,var(--primary-color) 18%,transparent);display:flex;align-items:center;justify-content:center;transition:opacity 0.15s ease,visibility 0.15s ease,transform 0.2s,box-shadow 0.2s,background 0.2s;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);transform:none;}
        #home-root-button{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--primary-color);text-decoration:none;background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 28%,transparent) 0%,color-mix(in srgb,var(--primary-color) 14%,transparent) 100%);flex:0 0 auto;border:1px solid color-mix(in srgb,var(--primary-color) 35%,transparent);box-shadow:0 2px 10px color-mix(in srgb,var(--primary-color) 22%,transparent);transition:transform 0.2s,box-shadow 0.2s,background 0.2s;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);}
        body.light-theme #home-root-button{color:var(--text-color) !important;}
        #home-root-button svg{width:22px;height:22px;}
        #home-root-button:hover{transform:scale(1.06);box-shadow:0 4px 14px color-mix(in srgb,var(--primary-color) 30%,transparent);background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 40%,transparent) 0%,color-mix(in srgb,var(--primary-color) 22%,transparent) 100%);}
        #menu-button:hover{transform:scale(1.06);box-shadow:0 4px 14px color-mix(in srgb,var(--primary-color) 26%,transparent);background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 34%,transparent) 0%,color-mix(in srgb,var(--primary-color) 18%,transparent) 100%);}
        #top-bar #support-chat-btn{position:absolute;right:16px;top:5px;margin-left:0;}
        #top-bar #notifications-btn{position:absolute;right:64px;top:5px;margin-left:0;}
        #menu-button .menu-button-glyph{width:auto;height:auto;}
        #menu-button .menu-button-glyph svg{width:22px;height:22px;}
        <?php else: ?>
        #menu-button{width:var(--shell-menu-button-width);height:var(--shell-menu-button-height);display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--sidebar-bg) 36%,transparent);border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,0.1);backdrop-filter:blur(16px) saturate(140%);-webkit-backdrop-filter:blur(16px) saturate(140%);transform:none;}
        #menu-button .menu-button-glyph{width:100%;height:100%;}
        #menu-button .menu-button-glyph svg{width:calc(var(--shell-menu-button-height) * 0.5);height:calc(var(--shell-menu-button-height) * 0.5);}
	        @media (max-width:768px){
            #menu-button{top:var(--shell-menu-button-mobile-top);left:var(--shell-menu-button-mobile-left);width:var(--shell-menu-button-mobile-width);height:var(--shell-menu-button-mobile-height);}
            <?php foreach(($selectedAppMenuButtonMobileStyle??[]) as $prop=>$value): ?>#menu-button{<?php echo my_htmlspecialchars((string)$prop); ?>:<?php echo my_htmlspecialchars((string)$value); ?>;}<?php endforeach; ?>
            #menu-button .menu-button-glyph svg{width:calc(var(--shell-menu-button-mobile-height) * 0.5);height:calc(var(--shell-menu-button-mobile-height) * 0.5);}
        }
        <?php endif; ?>
        #sidebar{position:fixed;top:54px;left:-320px;width:320px;height:calc(100% - 54px);background:var(--sidebar-bg);transition:left 0.3s cubic-bezier(.2,.8,.2,1);padding:20px;box-sizing:border-box;display:flex;flex-direction:column;z-index:999;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);border-left:none;box-shadow:var(--glass-shadow);}
        <?php if(!$isHomeDashboard): ?>
        #sidebar{top:0;height:100%;border-top-right-radius:var(--casa-radius);border-bottom-right-radius:var(--casa-radius);display:flex;flex-direction:column;}
        <?php endif; ?>
        #notifications-panel{display:flex;flex-direction:column;visibility:hidden;position:fixed;top:62px;right:16px;width:min(420px,calc(100vw - 24px));max-height:min(620px,calc(100vh - 78px));background:var(--modal-bg);border:1px solid var(--border-color);border-radius:var(--casa-radius);box-shadow:var(--glass-shadow);z-index:100020;overflow:hidden;color:var(--text-color);opacity:0;pointer-events:none;transform:translateY(-8px) scale(.98);transform-origin:top right;transition:opacity .16s ease,transform .16s ease,visibility 0s linear .16s;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));}
        #notifications-panel.open{visibility:visible;opacity:1;pointer-events:auto;transform:translateY(0) scale(1);transition-delay:0s;}
        .notifications-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border-color);font-weight:700;}
        .notifications-actions{display:flex;gap:8px;align-items:center;}
        .notifications-actions button{border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);border-radius:8px;padding:7px 10px;cursor:pointer;font-size:12px;display:inline-flex;align-items:center;gap:6px;position:relative;overflow:visible;}
        .notifications-actions button svg{width:14px;height:14px;stroke:currentColor;flex-shrink:0;}
        .notifications-actions button:disabled{opacity:.45;cursor:not-allowed;}
        .notifications-actions button.mark-read-done{background:rgba(34,197,94,.15);color:#22c55e;border-color:rgba(34,197,94,.3);}
        @keyframes mark-read-pop{0%{transform:scale(1)}40%{transform:scale(1.08)}100%{transform:scale(1)}}
        .mark-read-pop{animation:mark-read-pop .3s ease}
        .mark-read-confetti{position:absolute;width:6px;height:6px;border-radius:1px;left:var(--sx);top:50%;pointer-events:none;z-index:5;animation:confetti-fly .7s ease-out forwards}
        @keyframes confetti-fly{0%{transform:translate(0,0) rotate(0deg);opacity:1}100%{transform:translate(var(--dx),var(--dy)) rotate(var(--rot));opacity:0}}
        #notifications-list{overflow:auto;padding:4px 0;display:flex;flex-direction:column;}
        .notification-row{display:flex;align-items:flex-start;gap:10px;padding:10px 16px;transition:background .15s;position:relative;}
        .notification-row+.notification-row{border-top:1px solid color-mix(in srgb,var(--border-color) 50%,transparent);}
        .notification-row:hover{background:var(--group-block-bg);}
        .notification-row.unread{background:color-mix(in srgb,var(--primary-color) 6%,transparent);}
        .notification-row.unread:hover{background:color-mix(in srgb,var(--primary-color) 10%,transparent);}
        .notification-row-icon{flex-shrink:0;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-top:2px;}
        .notification-row-icon svg{width:18px;height:18px;}
        .notification-row-icon.severity-info{background:color-mix(in srgb,#3b82f6 14%,transparent);color:#3b82f6;}
        .notification-row-icon.severity-warning{background:color-mix(in srgb,#f59e0b 14%,transparent);color:#f59e0b;}
        .notification-row-icon.severity-error{background:color-mix(in srgb,#ef4444 14%,transparent);color:#ef4444;}
        .notification-row-icon.severity-success{background:color-mix(in srgb,#22c55e 14%,transparent);color:#22c55e;}
        .notification-row-icon.severity-critical{background:color-mix(in srgb,#dc2626 14%,transparent);color:#dc2626;}
        .notification-row-icon.severity-default{background:color-mix(in srgb,var(--primary-color) 14%,transparent);color:var(--primary-color);}
        .notification-row-body{flex:1;min-width:0;}
        .notification-row-title{font-weight:600;font-size:13px;line-height:1.3;margin-bottom:2px;}
        .notification-row-meta{font-size:11px;opacity:.55;margin-bottom:2px;}
        .notification-row-message{font-size:12px;line-height:1.4;opacity:.7;white-space:pre-wrap;}
        .notification-row-unread-dot{width:8px;height:8px;border-radius:50%;background:var(--primary-color);flex-shrink:0;margin-top:6px;}
        .notification-row-copy{background:none;border:none;cursor:pointer;opacity:0.55;padding:0 3px;display:flex;align-items:center;justify-content:center;border-radius:4px;transition:all 0.15s;flex-shrink:0;margin-top:2px;}
        .notification-row-copy svg{width:13px;height:13px;color:var(--text-color);fill:none;stroke:currentColor;stroke-width:2;}
        .notification-row-copy:hover{opacity:1!important;background:rgba(128,128,128,0.15);}
        #sidebar.open{left:0;}
        #app-list-container{margin-top:0;padding-top:16px;max-height:none;flex:1;min-height:0;overflow-y:auto;position:relative;scrollbar-width:none;-ms-overflow-style:none;-webkit-mask-image:linear-gradient(to bottom,transparent 0px,black 20px,black calc(100% - 30px),transparent 100%);mask-image:linear-gradient(to bottom,transparent 0px,black 20px,black calc(100% - 30px),transparent 100%);}
        #app-list-container::-webkit-scrollbar{display:none;}
        #bottom-bar{position:relative;bottom:auto;left:auto;right:auto;display:flex;align-items:center;gap:8px;flex-wrap:nowrap;flex-shrink:0;padding-top:8px;}
        <?php if(!$isHomeDashboard): ?>
        #app-list-container{padding-top:0;max-height:none;flex:1;min-height:0;position:relative;-webkit-mask-image:linear-gradient(to bottom,transparent 0px,black 24px,black calc(100% - 30px),transparent 100%);mask-image:linear-gradient(to bottom,transparent 0px,black 24px,black calc(100% - 30px),transparent 100%);}
        #bottom-bar{position:relative;bottom:auto;left:auto;right:auto;flex-shrink:0;padding-top:8px;}
        <?php endif; ?>
        #app-list-container::before,
        #app-list-container::after{content:"";position:absolute;left:0;right:0;z-index:2;pointer-events:none;backdrop-filter:blur(14px) saturate(150%);-webkit-backdrop-filter:blur(14px) saturate(150%);}
        #app-list-container::before{top:0;height:44px;-webkit-mask-image:linear-gradient(to bottom,black 0%,transparent 100%);mask-image:linear-gradient(to bottom,black 0%,transparent 100%);}
        #app-list-container::after{bottom:0;height:54px;-webkit-mask-image:linear-gradient(to top,black 0%,transparent 100%);mask-image:linear-gradient(to top,black 0%,transparent 100%);}
        @media (min-width:769px){#app-list-container{overflow-y:auto;-webkit-mask-image:none;mask-image:none;}#app-list-container::before,#app-list-container::after{display:none;}}
        .search-container{overflow:hidden;width:36px;height:36px;box-sizing:border-box;border-radius:999px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;padding:0 8px;margin-left:8px;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);transition:width 0.45s cubic-bezier(0.2,0.8,0.2,1),background 0.3s ease,border-color 0.3s ease,border-radius 0.45s cubic-bezier(0.2,0.8,0.2,1);cursor:pointer;flex-shrink:0;}
        .bottom-bar-actions{position:absolute;right:8px;align-self:center;display:flex;align-items:center;gap:8px;flex-shrink:0;}
        .search-icon{color:var(--text-color);font-size:14px;flex-shrink:0;width:auto;height:auto;opacity:0.65;}
        #search-bar{width:100%;background:transparent;border:none;outline:none;color:var(--text-color);font-size:13px;margin-left:8px;padding:0;opacity:0;transform:translateX(10px);transition:opacity 0.25s ease,transform 0.45s cubic-bezier(0.2,0.8,0.2,1);caret-color:var(--text-color);-webkit-text-fill-color:var(--text-color);}
        #search-bar::placeholder{color:color-mix(in srgb,var(--text-color) 42%,transparent);opacity:1;}
        .search-container:hover{width:120px;background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.12);}
        .search-container:hover #search-bar{opacity:1;transform:translateX(0);}
        .search-container.open{width:120px;background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.12);border-radius:999px;}
        .search-container.open #search-bar{opacity:1;transform:translateX(0);}
        #bottom-bar #support-chat-btn.app-page-chat{position:relative;top:auto;right:auto;left:auto;margin-left:0;width:32px;height:32px;background:none;border:none;box-shadow:none;color:var(--text-color);padding:0;backdrop-filter:none;-webkit-backdrop-filter:none;transform:none;}
        #bottom-bar #support-chat-btn.app-page-chat:hover{background:none;box-shadow:none;transform:none;color:var(--text-color);}
        .search-container.open + #support-chat-btn.app-page-chat,
        .search-container:hover + #support-chat-btn.app-page-chat{display:none!important;}
        #clear-search{display:none!important;}
        .group-letter{font-weight:bold;margin-bottom:5px;color:var(--text-color);}
        .group-block{background:var(--group-block-bg);border-radius:14px;padding:10px;margin-bottom:15px;border:1px solid var(--border-color);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);}
        #app-list,#app-list ul{list-style:none;padding:0;margin:0;}
        #app-list li{width:100%;box-sizing:border-box;margin-bottom:10px;padding:10px 12px;background:var(--app-item-bg);border-radius:12px;position:relative;border:1px solid transparent;transition:background .2s,border-color .2s,transform .15s;}
        #app-list a,#app-list a.app-tile-link{color:var(--link-color);text-decoration:none;display:flex;align-items:center;justify-content:space-between;gap:12px;min-width:0;max-width:none;width:100%;box-sizing:border-box;}
        #app-list a:hover{color:var(--link-hover-color);}
        .sidebar-app-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:left;order:1;}
        .sidebar-app-icon-wrap{width:18px;height:18px;display:flex;align-items:center;justify-content:center;flex-shrink:0;order:2;margin-left:auto;}
        .sidebar-app-icon{width:18px;height:18px;object-fit:contain;display:block;}
        .sidebar-app-icon-svg{display:block;width:18px;height:18px;background:linear-gradient(135deg,var(--app-icon-grad-start) 0%,var(--app-icon-grad-end) 100%);-webkit-mask-image:var(--icon-src);mask-image:var(--icon-src);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain;}
        .tooltip{display:none;position:fixed;background-color:var(--tooltip-bg);color:#fff;padding:5px 10px;border-radius:4px;white-space:nowrap;z-index:20000;pointer-events:none;box-shadow:0 2px 10px rgba(0,0,0,0.3);font-size:12px;transform:translateX(-50%);}
        .tooltip::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:var(--tooltip-bg);}
        .sidebar-button{background:none;border:none;cursor:pointer;color:var(--text-color);width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;position:relative;flex-shrink:0;}
        .sidebar-button svg{width:20px;height:20px;}
        #settings-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);}
        #qr-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);}
        #qr-content{background:var(--modal-bg);padding:0;border-radius:var(--casa-radius);color:var(--text-color);width:90%;max-width:400px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;position:relative;margin:2% auto;z-index:10001;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #close-qr{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-qr:hover{color:#cc0000;}
        #email-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);}
        #email-form{background:var(--modal-bg);padding:0;border-radius:var(--casa-radius);color:var(--text-color);position:relative;min-width:350px;max-width:500px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;margin:2% auto;z-index:10001;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #close-email{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-email:hover{color:#cc0000;}
        #users-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);}
        #users-content{background:var(--modal-bg);padding:0;border-radius:var(--casa-radius);color:var(--text-color);position:relative;width:95%;height:95%;max-width:none;max-height:none;overflow:hidden;display:flex;flex-direction:column;margin:2% auto;z-index:10001;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #close-users{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-users:hover{color:#cc0000;}
        .users-table{width:100%;border-collapse:collapse;margin-top:15px;}
        .users-table th,.users-table td{padding:12px;text-align:left;border-bottom:1px solid var(--border-color);}
        .users-table th{background-color:var(--table-header-bg);font-weight:bold;}
        .users-table tr:hover{background-color:var(--table-row-hover);}
        .role-select{padding:5px;border-radius:4px;border:1px solid var(--border-color);background:var(--modal-bg);color:var(--text-color);}
        #users-search{width:100%;padding:10px;margin-bottom:15px;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--text-color);box-sizing:border-box;padding-left:34px;}



        /* Telegram Bot Modal */
        #tg-bot-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.12);justify-content:center;align-items:center;z-index:11000;}
        #tg-bot-content{background:var(--modal-bg);padding:40px 24px 24px 24px;border-radius:var(--casa-radius);color:var(--text-color);position:relative;width:90%;max-width:480px;max-height:90vh;overflow-y:auto;z-index:11001;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #close-tg-bot{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:11002;}
        .tg-bot-field{margin-bottom:14px;}
        .tg-bot-field label{display:block;font-size:12px;font-weight:bold;color:#888;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.5px;}
        .tg-bot-field input{width:100%;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;background:var(--modal-bg);color:var(--text-color);font-size:14px;box-sizing:border-box;font-family:monospace;}
        .tg-bot-field input:focus{outline:none;border-color:var(--primary-color);}
        .tg-bot-hint{font-size:12px;color:#888;margin-top:4px;line-height:1.4;}
        .tg-bot-actions{display:flex;gap:10px;margin-top:20px;}
        .tg-save-btn{flex:1;padding:10px;background:var(--primary-color);color:#fff;border:none;border-radius:50px;cursor:pointer;font-size:14px;font-weight:bold;}
        .tg-test-btn{padding:10px 18px;background:var(--group-block-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:24px;cursor:pointer;font-size:14px;}
        .tg-test-btn:hover{background:var(--app-item-bg);}
        .tg-status{margin-top:10px;font-size:13px;text-align:center;min-height:20px;}


        /* Delete User Modal */
        #delete-user-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.12);justify-content:center;align-items:center;z-index:95000;}
        #delete-user-content{background:var(--modal-bg);padding:40px 24px 24px 24px;border-radius:var(--casa-radius);color:var(--text-color);position:relative;width:92%;max-width:700px;max-height:90vh;overflow-y:auto;z-index:95001;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #close-delete-user{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:12002;}
        .delete-user-files-list{max-height:280px;overflow-y:auto;border:1px solid var(--border-color);border-radius:6px;margin:12px 0;padding:4px 0;}
        .delete-user-file-row{padding:6px 12px;border-bottom:1px solid var(--border-color);font-size:12px;display:flex;justify-content:space-between;align-items:center;}
        .delete-user-file-row:last-child{border-bottom:none;}
        .delete-user-file-app{background:var(--primary-color);color:#fff;border-radius:4px;padding:2px 6px;font-size:11px;flex-shrink:0;margin-right:8px;}
        .delete-user-file-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .delete-user-file-size{color:#888;font-size:11px;flex-shrink:0;margin-left:8px;}
        .total-size-badge{display:inline-block;background:var(--primary-color);color:#fff;border-radius:20px;padding:4px 14px;font-size:13px;font-weight:bold;margin-left:8px;}
        .delete-files-toggle{display:flex;align-items:center;gap:10px;margin:14px 0;padding:12px;background:var(--group-block-bg);border-radius:8px;cursor:pointer;}
        .delete-files-toggle input[type=checkbox]{width:18px;height:18px;cursor:pointer;accent-color:#ff4444;}
        /* Confirmation slider */
        .confirm-slider-wrapper{margin:16px 0 8px 0;}
        .confirm-slider-label{font-size:13px;color:#888;margin-bottom:6px;}
        .confirm-slider-track{position:relative;width:100%;height:44px;background:var(--group-block-bg);border-radius:22px;overflow:hidden;border:1px solid var(--border-color);}
        .confirm-slider-fill{position:absolute;top:0;left:0;height:100%;background:linear-gradient(90deg,#ff4444,#cc0000);border-radius:22px;transition:width 0.05s linear;pointer-events:none;}
        .confirm-slider-input{position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;cursor:pointer;margin:0;z-index:2;}
        .confirm-slider-thumb-label{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:13px;font-weight:bold;pointer-events:none;z-index:3;user-select:none;color:var(--text-color);}
        .do-delete-btn{width:100%;padding:12px;background:#cc0000;color:#fff;border:none;border-radius:24px;cursor:pointer;font-size:15px;font-weight:bold;margin-top:8px;transition:all 0.2s;}
        .do-delete-btn:disabled{opacity:0.4;cursor:not-allowed;}
        .do-delete-btn:not(:disabled):hover{background:#aa0000;}
        #reset-user-password-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.12);justify-content:center;align-items:center;z-index:95000;}
        #reset-user-password-content{background:var(--modal-bg);padding:40px 24px 24px 24px;border-radius:var(--casa-radius);color:var(--text-color);position:relative;width:92%;max-width:520px;max-height:90vh;overflow-y:auto;z-index:95001;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #close-reset-user-password{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:12002;}
        .reset-password-field-row{display:flex;gap:8px;align-items:stretch;margin:12px 0;}
        .reset-password-field-row input{flex:1;padding:12px;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--text-color);font-family:monospace;font-size:14px;}
        .reset-password-regen-btn{flex-shrink:0;width:44px;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--primary-color);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;}
        .reset-password-regen-btn:hover{background:var(--group-block-bg);}
        .confirm-slider-fill.confirm-slider-fill-primary{background:linear-gradient(90deg,var(--primary-color),color-mix(in srgb,var(--primary-color) 65%,#000));}
        .do-reset-pwd-btn{width:100%;padding:12px;background:var(--primary-color);color:#fff;border:none;border-radius:24px;cursor:pointer;font-size:15px;font-weight:bold;margin-top:8px;transition:all 0.2s;}
        .do-reset-pwd-btn:disabled{opacity:0.4;cursor:not-allowed;}
        .do-reset-pwd-btn:not(:disabled):hover{opacity:0.9;}
        #reset-password-success{display:none;margin-top:16px;padding:14px;border:1px solid color-mix(in srgb,#3fb950 35%,var(--border-color));border-radius:8px;background:color-mix(in srgb,#3fb950 10%,transparent);}
        #reset-password-success .reset-password-copy-row{display:flex;gap:8px;margin-top:10px;}
        #reset-password-success input{flex:1;padding:10px;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--text-color);font-family:monospace;font-size:13px;}
        .reset-password-copy-btn{padding:10px 14px;background:var(--primary-color);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;white-space:nowrap;}
        .no-files-msg{text-align:center;color:#888;padding:20px;font-size:13px;}
        .scanning-msg{text-align:center;color:var(--primary-color);padding:20px;font-size:13px;}
        #delete-user-scan-progress{margin-top:16px;padding:16px;background:rgba(0,0,0,0.02);border-radius:8px;}
        #scan-progress-bar{background:linear-gradient(90deg, var(--primary-color), rgba(255,255,255,0.2));height:100%;width:0%;transition:width 0.3s ease;border-radius:8px;position:relative;overflow:hidden;}
        #scan-progress-bar::after{content:'';position:absolute;top:0;left:0;bottom:0;right:0;background:linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);animation:shimmer 2s infinite;}
        @keyframes shimmer{0%{transform:translateX(-100%);}100%{transform:translateX(100%)}}
        #scan-time-remaining{color:#888;font-size:12px;}
        #scan-speed{color:#666;font-size:11px;}
        #stats-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);}
        #edit-stats-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.12);justify-content:center;align-items:center;z-index:10001;}
        #edit-stats-content{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:var(--casa-radius);color:var(--text-color);position:relative;width:90%;height:85vh;max-width:1200px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;margin:2% auto;z-index:10002;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #close-edit-stats{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10003;}
        #settings-form{background:var(--modal-bg);padding:0;border-radius:var(--casa-radius);color:var(--text-color);position:relative;min-width:300px;max-width:520px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;margin:2% auto;z-index:10001;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #settings-form .modal-scroll-body{overflow-x:hidden;overflow-y:auto;flex:1;min-height:0;padding:40px 20px 20px 20px;border-radius:inherit;-webkit-overflow-scrolling:touch;}
        #settings-form .form-row{display:flex;align-items:center;margin-bottom:15px;justify-content:center;}
        #settings-form .form-row label{flex:0 0 100px;font-weight:bold;margin-right:10px;}
        #settings-form .form-row select,
        #settings-form .form-row input[type="color"]{flex:0 0 200px;max-width:200px;}
        #settings-form select {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23<?php echo $config['theme']==='dark'?'fff':'000';?>' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 14px;
            padding-right: 30px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }
        .language-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 0;
        }
        .flag {
            width: 20px;
            height: 15px;
            display: inline-block;
            background-size: cover;
            background-position: center;
            border-radius: 2px;
            margin-left: 8px;
            vertical-align: middle;
        }
        select option {
            display: flex;
            align-items: center;
            padding: 8px;
        }
        select option .flag {
            display: inline-block;
            margin-left: 8px;
        }
        .flag-gb { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 30"><clipPath id="t"><path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z"/></clipPath><path d="M0,0 v30 h60 v-30 z" fill="%23012469"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="%23fff" stroke-width="6"/><path d="M0,0 L60,30 M60,0 L0,30" clip-path="url(%23t)" stroke="%23C8102E" stroke-width="4"/><path d="M30,0 v30 M0,15 h60" stroke="%23fff" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="%23C8102E" stroke-width="6"/></svg>'); }
        .flag-ru { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 9 6"><rect fill="%23fff" width="9" height="6"/><rect fill="%230052b4" y="2" width="9" height="2"/><rect fill="%23d52b1e" y="4" width="9" height="2"/></svg>'); }
        .flag-ua { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 12"><rect fill="%230057b8" width="20" height="6"/><rect fill="%23ffd800" y="6" width="20" height="6"/></svg>'); }
        .flag-by { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="200" fill="%23d52b1e"/><rect y="200" width="900" height="200" fill="%23ffffff"/><rect y="400" width="900" height="200" fill="%234281c1"/></svg>'); }
        .flag-pl { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 9 6"><rect fill="%23fff" width="9" height="6"/><rect fill="%23dc143c" y="3" width="9" height="3"/></svg>'); }
        .flag-cz { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 20"><rect fill="%23ffffff" width="30" height="20"/><rect fill="%23d7141a" y="10" width="30" height="10"/><polygon points="0,0 15,10 0,20" fill="%2311457e"/></svg>'); }
        .custom-select-wrap{position:relative;flex:0 0 200px;max-width:200px;height:32px;}
        .custom-select-trigger{display:flex;align-items:center;justify-content:space-between;padding:0 8px 0 12px;border:1px solid var(--border-color);border-radius:10px;background:color-mix(in srgb,var(--modal-bg) 80%,transparent);color:var(--text-color);cursor:pointer;user-select:none;height:32px;box-sizing:border-box;font-size:14px;line-height:1.2;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);}
        .custom-select-trigger:hover{border-color:var(--primary-color);}
        .custom-select-text{display:flex;align-items:center;gap:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0;}
        .custom-select-arrow{flex-shrink:0;opacity:.6;transition:transform .2s;}
        .custom-select-wrap.open .custom-select-arrow{transform:rotate(180deg);}
        .custom-select-dropdown{display:none;position:fixed;background:var(--modal-bg);border:1px solid var(--border-color);border-radius:12px;box-shadow:var(--glass-shadow);z-index:100080;overflow:hidden;min-width:160px;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));max-height:min(280px,50vh);overflow-y:auto;}
        .custom-select-wrap.open .custom-select-dropdown{display:block;}
        .custom-select-option{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;cursor:pointer;font-size:13px;transition:background .15s;}
        .custom-select-option span:first-child{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .custom-select-option:hover{background:var(--group-block-bg);}
        .custom-select-option.active{color:var(--primary-color);font-weight:600;}
        .custom-select-option svg{flex-shrink:0;opacity:.7;}
        .custom-select-badge{display:inline-block;padding:1px 6px;margin-left:6px;background:var(--primary-color);color:#fff;border-radius:8px;font-size:10px;font-weight:600;white-space:nowrap;flex-shrink:0;}
        #icon-style-dropdown .custom-select-option{gap:4px;padding-right:8px;}
        #icon-style-dropdown .custom-select-badge{max-width:64px;min-width:0;overflow:hidden;text-overflow:ellipsis;padding:1px 4px;margin-left:2px;border-radius:6px;font-size:9px;line-height:1.3;flex:0 1 auto;}
        .custom-select-flag{width:20px;height:15px;display:inline-block;background-size:cover;background-position:center;border-radius:2px;flex-shrink:0;}
        .custom-select-flag.flag-ru{position:relative;overflow:hidden;}
        .custom-select-flag.flag-ru::after{content:"";position:absolute;left:-3px;top:6px;width:26px;height:3px;background:#111;border:1px solid #fff;transform:rotate(-35deg);transform-origin:center;box-sizing:border-box;}
        #icon-sort-dropdown .custom-select-option{gap:4px;padding-right:8px;white-space:nowrap;}
        #icon-sort-dropdown .custom-select-badge{max-width:64px;min-width:0;overflow:hidden;text-overflow:ellipsis;padding:1px 4px;margin-left:2px;border-radius:6px;font-size:9px;line-height:1.3;flex:0 1 auto;}

        #wallpaper-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:100090;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);}
        #wallpaper-modal-content{background:var(--modal-bg);border:1px solid var(--border-color);border-radius:var(--casa-radius);box-shadow:var(--glass-shadow);backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));width:min(720px,calc(100vw - 32px));max-height:min(85vh,720px);overflow:hidden;display:flex;flex-direction:column;position:relative;}
        #wallpaper-modal-content .modal-scroll-body{padding:48px 20px 20px;}
        .wallpaper-picker{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;width:100%;}
        .wallpaper-open-btn{width:100%;height:32px;border:1px solid var(--border-color);border-radius:10px;background:color-mix(in srgb,var(--modal-bg) 70%,transparent);color:var(--text-color);cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;gap:8px;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);overflow:hidden;position:relative;}
        .wallpaper-open-btn-preview{position:absolute;inset:0;opacity:0.35;background-size:cover;background-position:center;pointer-events:none;}
        .wallpaper-open-btn-label{position:relative;z-index:1;font-weight:600;}
        .wallpaper-picker{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;width:100%;}
        .wallpaper-option{position:relative;aspect-ratio:1.4;border-radius:10px;border:2px solid transparent;cursor:pointer;overflow:hidden;background:var(--group-block-bg);box-sizing:border-box;padding:0;}
        .wallpaper-option.active{border-color:var(--primary-color);box-shadow:0 0 0 1px color-mix(in srgb,var(--primary-color) 40%,transparent);}
        .wallpaper-option span{position:absolute;inset:0;display:flex;align-items:flex-end;justify-content:center;padding:4px;font-size:9px;font-weight:600;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.6);background:linear-gradient(transparent 40%,rgba(0,0,0,.45));pointer-events:none;text-align:center;line-height:1.1;}
        .wallpaper-option[data-value="default"]{background:radial-gradient(ellipse at 30% 20%,color-mix(in srgb,var(--primary-color) 45%,transparent),var(--bg-color));}
        .app-desc-popover{position:fixed;z-index:100050;max-width:min(320px,calc(100vw - 24px));padding:0;border:none;background:transparent;color:var(--text-color);pointer-events:none;overflow:visible;opacity:0;visibility:hidden;transition:opacity .45s ease,visibility .45s ease;}
        .app-desc-popover.visible{opacity:1;visibility:visible;}
        .app-desc-popover-inner{position:relative;display:block;padding:14px 18px;border-radius:14px;background:var(--tooltip-bg);border:1px solid var(--border-color);box-shadow:var(--glass-shadow);font-size:13px;line-height:1.5;overflow:visible;}
        .app-desc-popover-inner::before{content:'';position:absolute;top:50%;width:14px;height:14px;background:inherit;border-left:1px solid var(--border-color);border-bottom:1px solid var(--border-color);transform:translateY(-50%) rotate(45deg);}
        .app-desc-popover.left-side .app-desc-popover-inner::before{left:-7px;}
        .app-desc-popover.right-side .app-desc-popover-inner::before{right:-7px;transform:translateY(-50%) rotate(-135deg);}
        .app-desc-text{display:block;overflow:visible;word-break:break-word;text-align:center;}
        .tooltip{backdrop-filter:blur(16px) saturate(160%);-webkit-backdrop-filter:blur(16px) saturate(160%);border:1px solid var(--border-color);border-radius:10px;box-shadow:var(--glass-shadow);}
        .modal-scroll-body{overflow:auto;flex:1;min-height:0;-webkit-overflow-scrolling:touch;}
        #shared-files-content,#sessions-content,#profile-content,#qr-content,#email-form,#users-content,#stats-content,#tg-bot-content,#delete-user-content,#reset-user-password-content,#edit-stats-content,#shortcuts-content{overflow:hidden;}
        #shared-files-content{border-radius:var(--casa-radius);backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #sessions-content{border-radius:var(--casa-radius);backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);padding:0;display:flex;flex-direction:column;}
        #sessions-content .modal-scroll-body{padding-top:10px;}
        .modal-header{display:flex;justify-content:space-between;align-items:center;padding:15px 20px;border-bottom:1px solid var(--border-color);}
        .modal-header-left h2{margin:0;font-size:18px;font-weight:600;}
        #sessions-content .modal-scroll-body{padding:0 20px 20px;}
        #profile-content,#qr-content,#email-form,#users-content{padding:0;display:flex;flex-direction:column;}
        #profile-content .modal-scroll-body{padding:30px;}
        #qr-content .modal-scroll-body{padding:30px;}
        #qr-content .modal-scroll-body:first-child{padding-top:10px;}
        #email-form .modal-scroll-body{padding:40px 20px 20px;}
        #users-content .modal-scroll-body{padding:40px 20px 20px;}
        #tg-bot-modal,#delete-user-modal,#reset-user-password-modal,#edit-stats-modal,#profile-modal{backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);background:rgba(6,10,16,0.28);}
        #shortcuts-content{background:var(--modal-bg);border-radius:var(--casa-radius);border:1px solid var(--border-color);box-shadow:var(--glass-shadow);backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));overflow:auto;max-height:90vh;}
        .settings-table{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));grid-auto-rows:max-content;align-items:start;align-content:start;gap:1px;width:100%;height:auto;margin-bottom:15px;border:1px solid var(--border-color);border-radius:14px;background:var(--border-color);position:relative;overflow:visible;}
        .settings-table-cell{display:flex;align-self:start;box-sizing:border-box;flex-direction:column;gap:4px;height:auto;min-height:0;padding:6px 8px;background:color-mix(in srgb,var(--modal-bg) 55%,transparent);overflow:visible;}
        .settings-table-cell label{font-weight:bold;font-size:13px;text-align:left;line-height:1.3;}
        .settings-table-cell .custom-select-wrap{width:100%;max-width:none;flex:none;align-self:stretch;}
        .settings-table-cell input[type="color"]{width:100%;height:32px;padding:2px;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);cursor:pointer;box-sizing:border-box;}
        .policy-link-icon{width:16px;height:16px;vertical-align:-3px;margin-right:4px;stroke:currentColor;}
        #policy-modal-overlay{display:none;position:fixed;inset:0;z-index:30000;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);justify-content:center;align-items:center;}
        #policy-modal-overlay.active{display:flex;}
        #policy-modal{width:80vw;height:80vh;max-width:90vw;max-height:90vh;border-radius:var(--casa-radius);overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--glass-shadow);border:1px solid var(--border-color);padding:6px;}
        #policy-modal iframe{flex:1;width:100%;border:none;border-radius:calc(var(--casa-radius) - 6px);}
        #policy-modal-close{position:absolute;top:10px;right:14px;width:36px;height:36px;border-radius:50%;border:none;background:var(--modal-bg);color:var(--text-color);font-size:20px;cursor:pointer;z-index:30001;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.2);transition:opacity 0.2s;}
        #policy-modal-close:hover{opacity:0.7;}
        #stats-content{background:var(--modal-bg);padding:60px 20px 20px 20px;border-radius:var(--casa-radius);color:var(--text-color);position:relative;width:90%;height:85vh;max-width:1000px;max-height:90vh;display:flex;flex-direction:column;overflow-y:auto;margin:2% auto;z-index:10001;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #stats-nav-box{border:1px solid var(--border-color);border-radius:12px;padding:15px;margin-bottom:20px;background:var(--group-block-bg);flex-shrink:0;}
        .stats-nav-buttons{display:flex;flex-direction:column;gap:15px;justify-content:center;align-items:center;}
        .stats-nav-btn{background:var(--primary-color);color:white;border:none;padding:10px 15px;border-radius:4px;cursor:pointer;display:flex;align-items:center;gap:5px;transition:all 0.3s;}
        .stats-nav-btn:hover{opacity:0.9;transform:translateY(-1px);}
        .stats-nav-btn:disabled{opacity:0.5;cursor:not-allowed;}
        .stats-nav-label{font-size:13px;font-weight:500;}
        .stats-nav-row{display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap;}
        .stats-nav-row .stats-nav-btn{padding:8px 12px;}
        .stats-year-month-label{font-weight:600;font-size:14px;padding:8px 16px;background:var(--group-block-bg);border:1px solid var(--border-color);border-radius:6px;min-width:140px;text-align:center;white-space:nowrap;}
        #stats-controls{display:flex;gap:10px;width:100%;justify-content:center;}
        #stats-controls button{background:var(--primary-color);color:white;border:none;padding:10px 15px;border-radius:4px;cursor:pointer;transition:all 0.3s;}
        #stats-controls button:hover{opacity:0.9;transform:translateY(-1px);}
        #stats-controls button.active{background:var(--primary-color);opacity:1;font-weight:bold;}
        .stats-edit-button{margin-left:auto;}
        #app-iframe{width:100%;height:100vh;border:none;position:fixed;top:0;left:0;z-index:1;box-sizing:border-box;}
        #content{padding-top:0;position:fixed;left:0;width:100%;height:100vh;box-sizing:border-box;overflow:hidden;}
        #content.has-app-rail{--rail-w:48px;}
        #content.has-app-rail #app-iframe{left:var(--rail-w);width:calc(100% - var(--rail-w));}
        #app-icon-rail{--rail-w:48px;--rail-icon:28px;position:fixed;top:0;left:0;width:var(--rail-w);height:100%;z-index:1000;background:color-mix(in srgb,var(--bg-color) 18%,transparent);backdrop-filter:blur(28px) saturate(220%) brightness(1.06);-webkit-backdrop-filter:blur(28px) saturate(220%) brightness(1.06);border-right:1px solid color-mix(in srgb,var(--border-color) 28%,transparent);box-sizing:border-box;display:flex;flex-direction:column;align-items:center;gap:6px;padding-top:calc(var(--shell-menu-button-top) + var(--shell-menu-button-height) + 10px);padding-bottom:10px;padding-left:4px;padding-right:4px;overflow:hidden;overscroll-behavior:contain;scrollbar-width:none;-ms-overflow-style:none;transition:background-color .35s ease,border-color .35s ease,color .35s ease;}
        body.light-theme #app-icon-rail{background:rgba(240,244,248,0.20);}
        #content.app-edge-immich{--app-edge-bg:<?php echo $config['theme']==='dark'?'#000000':'#ffffff';?>;--app-edge-border:<?php echo $config['theme']==='dark'?'#1f2937':'#e5e7eb';?>;--app-edge-text:<?php echo $config['theme']==='dark'?'#f9fafb':'#111827';?>;}
        #content.app-edge-gitea{--app-edge-bg:<?php echo $config['theme']==='dark'?'#1e1f20':'#f6f8fa';?>;--app-edge-border:<?php echo $config['theme']==='dark'?'#30363d':'#d0d7de';?>;--app-edge-text:<?php echo $config['theme']==='dark'?'#f0f6fc':'#24292f';?>;}
        #content.app-edge-plane{--app-edge-bg:<?php echo $config['theme']==='dark'?'#0c0d0e':'#ffffff';?>;--app-edge-border:<?php echo $config['theme']==='dark'?'#1e293b':'#e5e7eb';?>;--app-edge-text:<?php echo $config['theme']==='dark'?'#f8fafc':'#111827';?>;}
        #content.app-edge-dawarich{--app-edge-bg:<?php echo $config['theme']==='dark'?'#1a1a1a':'#ffffff';?>;--app-edge-border:<?php echo $config['theme']==='dark'?'#1e293b':'#e5e7eb';?>;--app-edge-text:<?php echo $config['theme']==='dark'?'#f8fafc':'#0f172a';?>;}
        #content.app-edge-x{--app-edge-bg:<?php echo $config['theme']==='dark'?'#000000':'#ffffff';?>;--app-edge-border:<?php echo $config['theme']==='dark'?'#2f3336':'#cfd9de';?>;--app-edge-text:<?php echo $config['theme']==='dark'?'#e7e9ea':'#0f1419';?>;}
        #content.app-edge-lopaka{--app-edge-bg:<?php echo $config['theme']==='dark'?'#0a0a0a':'#ffffff';?>;--app-edge-border:<?php echo $config['theme']==='dark'?'#1f2937':'#e5e7eb';?>;--app-edge-text:<?php echo $config['theme']==='dark'?'#f9fafb':'#111827';?>;}
        #content.edge-app-shell #app-icon-rail{background:var(--app-edge-bg);border-color:var(--app-edge-border);color:var(--app-edge-text);backdrop-filter:none;-webkit-backdrop-filter:none;}
        #content.edge-app-shell #app-iframe{background:var(--app-edge-bg);}
        #content.edge-app-shell .app-rail-link,#content.edge-app-shell .app-rail-service-name{color:var(--app-edge-text);}
        #content.edge-app-shell #menu-button{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1003;}
        #app-icon-rail::-webkit-scrollbar{display:none;}
        .app-rail-link{display:flex;align-items:center;justify-content:center;width:var(--rail-icon);height:var(--rail-icon);border-radius:8px;flex-shrink:0;text-decoration:none;color:var(--text-color);background:transparent;transition:background-color 0.2s,transform 0.15s;}
        .app-rail-link:hover{background:color-mix(in srgb,var(--primary-color) 22%,transparent);transform:translateY(-1px);}
        .app-rail-link.active{background:color-mix(in srgb,var(--primary-color) 80%,transparent);}
        .app-rail-icon-svg{display:block;width:62%;height:62%;background-color:currentColor;-webkit-mask-image:var(--icon-src);mask-image:var(--icon-src);-webkit-mask-size:contain;mask-size:contain;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;}
        .app-rail-link.active .app-rail-icon-svg{background-color:#fff;}
        .app-rail-icon{width:62%;height:62%;object-fit:contain;border-radius:4px;}
        .app-rail-divider{width:60%;height:1px;background:color-mix(in srgb,var(--border-color) 40%,transparent);flex-shrink:0;margin:2px 0;}
        .app-rail-scroll{display:contents;}
        .app-rail-service-name{display:none;}
        @media (max-width:768px){
            #content.has-app-rail{--rail-w:40px;}
            #app-icon-rail{--rail-w:40px;--rail-icon:24px;gap:4px;padding-top:calc(var(--shell-menu-button-mobile-top) + var(--shell-menu-button-mobile-height) + 8px);padding-bottom:8px;padding-left:3px;padding-right:3px;}
            #content.has-app-rail.rail-top-on-mobile{--rail-h:48px;--rail-w:0px;}
            #content.has-app-rail.rail-top-on-mobile #app-iframe{left:0!important;width:100%!important;top:var(--rail-h);height:calc(100% - var(--rail-h));}
            #content.has-app-rail.rail-top-on-mobile #app-icon-rail{flex-direction:row;width:100%;height:var(--rail-h);border-right:none;border-bottom:1px solid color-mix(in srgb,var(--border-color) 28%,transparent);padding:0;overflow:hidden;gap:0;position:relative;}
            #content.edge-app-shell.rail-top-on-mobile #app-icon-rail{border-bottom-color:var(--app-edge-border);}
            #content.has-app-rail.rail-top-on-mobile .app-rail-scroll{display:flex;flex-direction:row;align-items:center;gap:6px;padding:0 8px 0 calc(var(--shell-menu-button-mobile-left) + var(--shell-menu-button-mobile-width) + 8px);overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none;height:100%;flex:1;}
            #content.has-app-rail.rail-top-on-mobile .app-rail-scroll::-webkit-scrollbar{display:none;}
            #content.has-app-rail.rail-top-on-mobile .app-rail-divider{width:1px;height:60%;flex-shrink:0;margin:0 2px;}
            #content.has-app-rail.rail-top-on-mobile .app-rail-service-name{display:block;writing-mode:horizontal-tb;transform:none;font-size:13px;font-weight:600;letter-spacing:.04em;color:var(--text-color);opacity:.7;white-space:nowrap;padding:0 8px;max-width:110px;overflow:hidden;text-overflow:ellipsis;flex-shrink:0;user-select:none;}
        }
        /* border-box: height includes padding — otherwise padding is added outside height and the grid is clipped by #content (last tiles unreachable). */
        #app-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(234px,1fr));gap:26px 18px;box-sizing:border-box;padding:24px;padding-top:calc(70px + 16px);padding-bottom:max(48px,calc(28px + env(safe-area-inset-bottom,0px)));overflow-y:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;height:100%;max-height:100%;scrollbar-color:var(--primary-color) var(--group-block-bg);align-content:flex-start;justify-items:center;scroll-padding-top:12px;scroll-padding-bottom:max(32px,env(safe-area-inset-bottom,0px));-webkit-mask-image:linear-gradient(to bottom,transparent 0px,black 48px);mask-image:linear-gradient(to bottom,transparent 0px,black 48px);}
        /* Fill viewport; use dvh on mobile so iframe/content match visible area (100vh exceeds it when browser chrome is shown). */
        @supports (height:100dvh){
            #content{height:100dvh;min-height:100dvh;max-height:100dvh;}
            #app-iframe{height:100dvh;min-height:100dvh;max-height:100dvh;}
            @media (max-width:768px){
                #content.has-app-rail.rail-top-on-mobile #app-iframe{height:calc(100dvh - var(--rail-h));min-height:calc(100dvh - var(--rail-h));max-height:calc(100dvh - var(--rail-h));}
            }
        }
        #app-grid::-webkit-scrollbar{width:8px;height:8px;}
        #app-grid::-webkit-scrollbar-track{background:var(--group-block-bg);border-radius:4px;}
        #app-grid::-webkit-scrollbar-thumb{background:var(--primary-color);border-radius:4px;}
        #app-grid::-webkit-scrollbar-thumb:hover{background:color-mix(in srgb,var(--primary-color)80%,black);}
        .app-tile-link{text-decoration:none;color:inherit;display:block;width:100%;max-width:148px;}
        .app-tile-link:hover{opacity:1;}
        .app-tile{background:transparent;border-radius:0;padding:6px;overflow:visible;transition:transform 0.2s ease;border:none;display:flex;flex-direction:column;align-items:center;gap:10px;}
        @media (hover: hover) and (pointer: fine){.app-tile-link:hover .app-tile{transform:translateY(-6px);}}
        .app-tile-link.app-tile-key-selected .app-icon-wrap{box-shadow:0 0 0 3px color-mix(in srgb,var(--primary-color) 35%,transparent),0 14px 30px color-mix(in srgb,var(--primary-color) 22%,transparent);transform:scale(1.04);}
        .app-popular-badge{position:absolute;top:-2px;right:-2px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;z-index:2;padding:0;background:transparent;border:none;pointer-events:none;filter:drop-shadow(0 2px 8px rgba(211,47,47,0.55));}
        .app-popular-badge svg{width:30px;height:30px;display:block;}
        .app-name{font-weight:bold;margin:0;text-align:center;color:var(--primary-color);font-size:14px;letter-spacing:.01em;line-height:1.25;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;order:2;position:relative;z-index:1;}
.app-name span{position:relative;z-index:1;display:inline-block;}
.app-name::after{content:'';position:absolute;inset:0;pointer-events:none;background:var(--app-item-bg);border-radius:12px;backdrop-filter:blur(40px) saturate(180%);-webkit-backdrop-filter:blur(40px) saturate(180%);mask:linear-gradient(90deg,transparent 0% 10%,var(--app-item-bg) 10% 90%,transparent 90% 100%),linear-gradient(-90deg,transparent 0% 10%,var(--app-item-bg) 10% 90%,transparent 90% 100%);-webkit-mask:linear-gradient(90deg,transparent 0% 10%,var(--app-item-bg) 10% 90%,transparent 90% 100%),linear-gradient(-90deg,transparent 0% 10%,var(--app-item-bg) 10% 90%,transparent 90% 100%);opacity:0.8;z-index:0;}
        .app-tile iframe{width:100%;height:150px;border:none;border-radius:4px;pointer-events:none;}
        button,input[type="submit"]{background-color:var(--primary-color);color:#fff;border:none;padding:10px;cursor:pointer;border-radius:4px;}
        input.settings-save-btn{background-color:var(--primary-color);color:#fff;border:none;padding:12px 24px;cursor:pointer;border-radius:50px;font-weight:600;display:block;margin-left:auto;}
        button:hover,input[type="submit"]:hover{opacity:0.8;}
        .notification{position:fixed;bottom:24px;right:20px;padding:10px 14px 10px 16px;border-radius:8px;color:var(--text-color);font-weight:400;z-index:101200;opacity:0;transform:translateX(30px);transition:all 0.25s ease;max-width:320px;font-size:13px;box-shadow:0 2px 12px rgba(0,0,0,0.18);display:flex;align-items:flex-start;gap:10px;border-left:4px solid var(--primary-color);background:var(--modal-bg);}
        .notification.show{opacity:1;transform:translateX(0);}
        .notification.swiping-out{opacity:0;transform:translateX(100vw);transition:all 0.4s ease-in;}
        .notification.success{border-left-color:#00C851;background:var(--modal-bg);}
        .notification.success .notification-icon{stroke:#00C851;fill:none;}
        .notification.error{border-left-color:#ff4444;background:var(--modal-bg);}
        .notification.error .notification-icon{stroke:#ff4444;fill:none;}
        .notification.info{border-left-color:var(--primary-color);background:var(--modal-bg);}
        .notification.info .notification-icon{stroke:var(--primary-color);fill:none;}
        .notification-icon{width:18px;height:18px;flex-shrink:0;margin-top:1px;}
        .notification-content{flex:1;line-height:1.4;color:var(--text-color);}
        .notification-close{background:none;border:none;color:var(--text-color);cursor:pointer;opacity:0.4;padding:0;width:18px;height:18px;display:flex;align-items:center;justify-content:center;border-radius:4px;transition:all 0.15s;flex-shrink:0;font-size:16px;}
        .notification-close:hover{opacity:0.8;background:rgba(128,128,128,0.15);}
        .notification-countdown{position:absolute;bottom:0;left:0;height:3px;background:var(--primary-color);border-radius:0 0 0 8px;transition:width 1s linear;}
        #shared-files-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);}
        #shared-files-content{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:var(--casa-radius);color:var(--text-color);position:relative;width:90%;height:80vh;max-width:900px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;margin:2% auto;z-index:10001;box-sizing:border-box;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #close-shared-files{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-shared-files:hover{color:#cc0000;}
        #shared-files-search{width:100%;padding:10px;margin-bottom:0;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--text-color);box-sizing:border-box;height:40px;padding-left:34px;}
        .shared-files-search-container .search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:16px;height:16px;opacity:.65;pointer-events:none;}
        .shared-files-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:nowrap;gap:10px;}
        .shared-files-search-container{position:relative;flex:1 1 auto;min-width:140px;height:40px;display:flex;align-items:center;}
        #shared-files-search{padding-right:35px;}
        #shared-files-clear-search{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#ff4444;font-size:18px;cursor:pointer;display:none;width:20px;height:20px;align-items:center;justify-content:center;border-radius:50%;}
        #shared-files-clear-search:hover{background-color:rgba(255,68,68,0.1);}
.shared-files-actions{display:flex;gap:8px;align-items:center;flex-wrap:nowrap;flex:0 0 auto;}
    .shared-files-actions > *{height:40px;box-sizing:border-box;flex-shrink:0;}
    .shared-files-actions select{width:auto;min-width:0;padding:0 10px;border-radius:6px;border:1px solid var(--border-color);background:var(--modal-bg);color:var(--text-color);font-size:13px;}
    #shared-files-sort,#shared-files-group{height:40px;}
    @media(max-width:768px){
      .shared-files-actions{flex:1 1 auto; gap:4px; min-width:0; flex-wrap:nowrap; overflow-x:auto;}
      .shared-files-actions > *{flex-shrink:0; min-width:0; max-width:100px; padding:0 8px;}
    }
    @media(max-width:480px){
      .shared-files-actions{gap:3px;}
      .shared-files-actions > *{max-width:70px; padding:0 6px;}
    }
        #refresh-shared-files,#delete-all-files-btn,#delete-filtered-files,#delete-selected-shared-files{height:40px;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;padding:0 12px;}
        #refresh-shared-files{width:40px;padding:0;}
        .delete-filtered-btn{background:#ff9800;color:white;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;display:none;align-items:center;gap:5px;}
        .delete-filtered-btn:hover{background:#f57c00;}
        #sessions-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);}
        #sessions-content{background:var(--modal-bg);padding:0;border-radius:var(--casa-radius);color:var(--text-color);position:relative;width:90%;height:80vh;max-width:800px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;margin:2% auto;z-index:10001;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);box-shadow:var(--glass-shadow);}
        #close-sessions{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-sessions:hover{color:#cc0000;}
        .sessions-table{width:100%;border-collapse:collapse;margin-top:15px;}
        .sessions-table th,.sessions-table td{padding:12px;text-align:left;border-bottom:1px solid var(--border-color);}
        .sessions-table th{background-color:var(--table-header-bg);font-weight:bold;}
        .sessions-table tr:hover{background-color:var(--table-row-hover);}
        .trusted-devices-table{width:100%;border-collapse:collapse;margin-top:10px;}
        .trusted-devices-table th,.trusted-devices-table td{padding:10px;text-align:left;border-bottom:1px solid var(--border-color);font-size:13px;}
        .trusted-devices-table th{background-color:var(--table-header-bg);font-weight:bold;font-size:12px;}
        .trusted-devices-table tr:hover{background-color:var(--table-row-hover);}
        .current-trusted-device{background-color:rgba(76,175,80,0.1)!important;border-left:3px solid #4CAF50;}
        .remove-trusted-btn{background:none;border:1px solid #ff4444;color:#ff4444;padding:4px 8px;border-radius:4px;cursor:pointer;font-size:12px;}
        .remove-trusted-btn:hover{background:#ff4444;color:white;}
        #add-trusted-device-btn{transition:transform 0.15s;}
        #add-trusted-device-btn:hover{transform:scale(1.01);}
        .current-session{background-color:rgba(0,123,255,0.1)!important;}
        .terminate-btn{background:#ff4444;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;}
        .terminate-btn:hover{background:#cc0000;}
        #app-access-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);}
        #app-access-content{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:8px;color:var(--text-color);position:relative;width:80%;height:80vh;max-width:700px;max-height:90vh;overflow:auto;margin:2% auto;z-index:10001;}
        #close-app-access{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-app-access:hover{color:#cc0000;}
        .settings-buttons-container{display:flex;gap:10px;justify-content:center;margin:15px 0;flex-wrap:wrap;}
.settings-buttons-box{background:var(--group-block-bg);border-radius:16px;padding:15px;margin:20px 0;display:flex;gap:15px;justify-content:center;flex-wrap:nowrap;overflow-x:auto;overflow-y:visible;border:1px solid var(--border-color);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);}
.settings-button-wrapper{position:relative;overflow:visible;flex:0 0 1;min-width:0;margin:0 -2px;}
        .settings-button-wrapper .tooltip{position:fixed;bottom:auto;left:auto;transform:none;margin-bottom:0;pointer-events:none;z-index:2147483647;}
        body>.tooltip{background:var(--group-block-bg);color:var(--text-color);padding:6px 12px;border-radius:8px;font-size:13px;border:1px solid var(--border-color);white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,.3);pointer-events:none;}
        .settings-buttons-end-group{display:flex;align-items:stretch;gap:15px;margin-left:auto;flex-wrap:nowrap;}
        .settings-buttons-divider{width:1px;align-self:stretch;min-height:34px;background:var(--border-color);opacity:.75;flex:0 0 1px;}
        .shortcuts-help-btn{background:color-mix(in srgb,var(--primary-color) 14%,transparent) !important;border:1px solid color-mix(in srgb,var(--primary-color) 40%,transparent) !important;color:var(--primary-color) !important;}
        .shortcuts-help-btn:hover{background:color-mix(in srgb,var(--primary-color) 24%,transparent) !important;border-color:var(--primary-color) !important;}
        @media(max-width:480px){
  .settings-buttons-box{gap:10px;padding:10px;overflow-x:auto;}
  .settings-button-wrapper{min-width:32px;flex-shrink:0;}
}
@media (hover:none) and (pointer:coarse){.shortcuts-help-wrap{display:none !important;}.settings-buttons-divider{display:none !important;}}
        #shortcuts-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);}
        #shortcuts-content{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:8px;color:var(--text-color);position:relative;width:90%;max-width:640px;max-height:90vh;overflow:auto;margin:2% auto;z-index:10001;box-sizing:border-box;}
        #close-shortcuts{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-shortcuts:hover{color:#cc0000;}
        .shortcuts-table{width:100%;border-collapse:collapse;margin-top:15px;}
        .shortcuts-table th,.shortcuts-table td{padding:12px;text-align:left;border-bottom:1px solid var(--border-color);}
        .shortcuts-table th{background-color:var(--table-header-bg);font-weight:bold;}
        .shortcuts-table code{display:inline-flex;align-items:center;justify-content:center;min-width:32px;padding:4px 8px;border-radius:8px;background:var(--group-block-bg);border:1px solid var(--border-color);font-family:'Courier New',monospace;font-size:13px;color:var(--primary-color);}
        .info-warning{display:flex;align-items:flex-start;gap:10px;margin-top:18px;padding:12px 14px;border-radius:10px;background:color-mix(in srgb,#f59e0b 14%,transparent);border:1px solid color-mix(in srgb,#f59e0b 35%,transparent);color:var(--text-color);font-size:13px;line-height:1.45;backdrop-filter:blur(2px);}
        .info-warning-icon{flex:0 0 20px;color:#f59e0b;margin-top:1px;}
        #shared-files-list-container{overflow-x:hidden;overflow-y:auto;flex:1;min-height:0;}
        #shared-files-list-container::-webkit-scrollbar{width:6px;}
        #shared-files-list-container::-webkit-scrollbar-track{background:var(--scrollbar-track);border-radius:3px;}
        #shared-files-list-container::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb);border-radius:3px;}
        #shared-files-list-container::-webkit-scrollbar-thumb:hover{background:var(--scrollbar-thumb-hover);}
        #shared-files-list-container{scrollbar-width:thin;scrollbar-color:var(--scrollbar-thumb) var(--scrollbar-track);}
        .qr-container{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;}
        .domain-info{margin-top:20px;text-align:center;background:var(--group-block-bg);padding:15px;border-radius:10px;width:100%;max-width:350px;}
        .domain-url{font-family:monospace;font-size:14px;word-break:break-all;margin:10px 0;padding:10px;background:var(--modal-bg);border-radius:5px;border:1px solid var(--border-color);}
        .copy-link-btn{margin-top:15px;background:var(--primary-color);color:white;border:none;padding:10px 20px;border-radius:25px;cursor:pointer;display:flex;align-items:center;gap:8px;font-weight:600;transition:all 0.3s ease;}
        .copy-link-btn:hover{opacity:0.9;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
        @keyframes shimmer{0%{transform:translateX(-100%) translateY(-100%) rotate(45deg);}100%{transform:translateX(100%) translateY(100%) rotate(45deg);}}
        .policy-links{display:flex;justify-content:center;align-items:center;gap:15px;margin-top:20px;padding-top:20px;padding-bottom:20px;border-top:1px solid var(--border-color);border-bottom:1px solid var(--border-color);text-align:center;width:100%;}
        .policy-link{color:var(--primary-color);text-decoration:none;font-size:14px;transition:opacity 0.3s;}
        .policy-link:hover{opacity:0.8;text-decoration:underline;}
        .policy-dot{width:4px;height:4px;border-radius:50%;background:var(--text-color);flex-shrink:0;}
        .device-icon,.browser-icon{width:24px;height:24px;display:inline-block;vertical-align:middle;margin-right:8px;}
        .device-browser-info{display:flex;align-items:center;gap:10px;}
        .device-browser-item{display:flex;align-items:center;gap:5px;}
        .windows-icon{color:#00ADEF;}
        .linux-icon{color:#FCC624;}
        .android-icon{color:#3DDC84;}
        .ios-icon{color:#374151;}
        .macos-icon{color:#999999;}
        .unknown-device-icon{color:#999999;}
        .chrome-icon{color:#4285F4;}
        .firefox-icon{color:#FF7139;}
        .safari-icon{color:#1B73BA;}
        .edge-icon{color:#0078D7;}
        .opera-icon{color:#FF1B2D;}
        .unknown-browser-icon{color:#999999;}
        <?php if($config['theme']==='dark'):?>
            #qr-content,#sessions-content,#shared-files-content,#users-content,#email-form,#settings-form,#stats-content,#app-access-content,#profile-content,#edit-stats-content{scrollbar-width:thin;scrollbar-color:#555 #222;}
            #qr-content::-webkit-scrollbar,#sessions-content::-webkit-scrollbar,#shared-files-content::-webkit-scrollbar,#users-content::-webkit-scrollbar,#email-form::-webkit-scrollbar,#settings-form::-webkit-scrollbar,#stats-content::-webkit-scrollbar,#app-access-content::-webkit-scrollbar,#profile-content::-webkit-scrollbar,#edit-stats-content::-webkit-scrollbar{width:10px;}
            #qr-content::-webkit-scrollbar-track,#sessions-content::-webkit-scrollbar-track,#shared-files-content::-webkit-scrollbar-track,#users-content::-webkit-scrollbar-track,#email-form::-webkit-scrollbar-track,#settings-form::-webkit-scrollbar-track,#stats-content::-webkit-scrollbar-track,#app-access-content::-webkit-scrollbar-track,#profile-content::-webkit-scrollbar-track,#edit-stats-content::-webkit-scrollbar-track{background:#222;border-radius:5px;}
            #qr-content::-webkit-scrollbar-thumb,#sessions-content::-webkit-scrollbar-thumb,#shared-files-content::-webkit-scrollbar-thumb,#users-content::-webkit-scrollbar-thumb,#email-form::-webkit-scrollbar-thumb,#settings-form::-webkit-scrollbar-thumb,#stats-content::-webkit-scrollbar-thumb,#app-access-content::-webkit-scrollbar-thumb,#profile-content::-webkit-scrollbar-thumb,#edit-stats-content::-webkit-scrollbar-thumb{background:#555;border-radius:5px;border:2px solid #222;}
            #qr-content::-webkit-scrollbar-thumb:hover,#sessions-content::-webkit-scrollbar-thumb:hover,#shared-files-content::-webkit-scrollbar-thumb:hover,#users-content::-webkit-scrollbar-thumb:hover,#email-form::-webkit-scrollbar-thumb:hover,#settings-form::-webkit-scrollbar-thumb:hover,#stats-content::-webkit-scrollbar-thumb:hover,#app-access-content::-webkit-scrollbar-thumb:hover,#profile-content::-webkit-scrollbar-thumb:hover,#edit-stats-content::-webkit-scrollbar-thumb:hover{background:#666;}
        <?php endif;?>
        <?php if($config['theme']==='light'):?>
            #sessions-content,#shared-files-content,#users-content,#email-form,#settings-form,#stats-content,#qr-content,#app-access-content,#profile-content,#edit-stats-content{scrollbar-width:thin;scrollbar-color:#ccc #f0f0f0;}
            #sessions-content::-webkit-scrollbar,#shared-files-content::-webkit-scrollbar,#users-content::-webkit-scrollbar,#email-form::-webkit-scrollbar,#settings-form::-webkit-scrollbar,#stats-content::-webkit-scrollbar,#qr-content::-webkit-scrollbar,#app-access-content::-webkit-scrollbar,#profile-content::-webkit-scrollbar,#edit-stats-content::-webkit-scrollbar{width:10px;}
            #sessions-content::-webkit-scrollbar-track,#shared-files-content::-webkit-scrollbar-track,#users-content::-webkit-scrollbar-track,#email-form::-webkit-scrollbar-track,#settings-form::-webkit-scrollbar-track,#stats-content::-webkit-scrollbar-track,#qr-content::-webkit-scrollbar-track,#app-access-content::-webkit-scrollbar-track,#profile-content::-webkit-scrollbar-track,#edit-stats-content::-webkit-scrollbar-track{background:#f0f0f0;border-radius:5px;}
            #sessions-content::-webkit-scrollbar-thumb,#shared-files-content::-webkit-scrollbar-thumb,#users-content::-webkit-scrollbar-thumb,#email-form::-webkit-scrollbar-thumb,#settings-form::-webkit-scrollbar-thumb,#stats-content::-webkit-scrollbar-thumb,#qr-content::-webkit-scrollbar-thumb,#app-access-content::-webkit-scrollbar-thumb,#profile-content::-webkit-scrollbar-thumb,#edit-stats-content::-webkit-scrollbar-thumb{background:#ccc;border-radius:5px;border:2px solid #f0f0f0;}
            #sessions-content::-webkit-scrollbar-thumb:hover,#shared-files-content::-webkit-scrollbar-thumb:hover,#users-content::-webkit-scrollbar-thumb:hover,#email-form::-webkit-scrollbar-thumb:hover,#settings-form::-webkit-scrollbar-thumb:hover,#stats-content::-webkit-scrollbar-thumb:hover,#qr-content::-webkit-scrollbar-thumb:hover,#app-access-content::-webkit-scrollbar-thumb:hover,#profile-content::-webkit-scrollbar-thumb:hover,#edit-stats-content::-webkit-scrollbar-thumb:hover{background:#aaa;}
        <?php endif;?>
        .qr-wrapper{position:relative;padding:16px;border-radius:var(--qr-radius);background:<?php echo $config['theme']==='dark'?'#111':'#f0f0f0';?>;overflow:hidden;margin:20px auto;width:300px;height:300px;display:flex;align-items:center;justify-content:center;}
        body,#auth-form,#settings-form,#email-form,#users-content,#stats-content,#qr-content,#sessions-content,#shared-files-content,#app-access-content,#profile-content,#edit-stats-content{color:var(--text-color)!important;}
        a,button,input,select,textarea{color:var(--text-color)!important;}
        .modal-content,.auth-content,.profile-section{color:var(--text-color)!important;}
        .users-table,.sessions-table,.trusted-devices-table{color:var(--text-color)!important;}
        .users-table th,.users-table td,.sessions-table th,.sessions-table td,.trusted-devices-table th,.trusted-devices-table td{color:var(--text-color)!important;border-color:var(--border-color)!important;}
        #sidebar,#app-list,#app-list a{color:var(--text-color)!important;}
        .form-row label{color:var(--text-color)!important;}
        .qr-wrapper::before{content:"";position:absolute;inset:-50%;background:conic-gradient(from 0deg,var(--primary-color),color-mix(in srgb,var(--primary-color)80%,white),color-mix(in srgb,var(--primary-color)60%,white),var(--primary-color),color-mix(in srgb,var(--primary-color)80%,black),color-mix(in srgb,var(--primary-color)60%,black),var(--primary-color));animation:spin 6s linear infinite;z-index:0;border-radius:var(--qr-radius);}
        .qr-wrapper::after{content:"";position:absolute;inset:var(--qr-border-size);border-radius:calc(var(--qr-radius) - var(--qr-border-size));background:var(--modal-bg);z-index:1;}
        #qr-code{position:relative;z-index:2;padding:16px;border-radius:16px;background:#ffffff;display:flex;align-items:center;justify-content:center;animation:pulse 2s infinite;box-shadow:0 10px 30px rgba(0,0,0,0.3);width:256px;height:256px;overflow:hidden;}
        #qr-code canvas{border-radius:12px!important;}
        <?php if($config['theme']==='light'):?>
            .qr-wrapper{background:#f5f5f5;}
            .qr-wrapper::after{background:#ffffff;box-shadow:inset 0 0 20px rgba(0,0,0,0.1);}
        <?php endif;?>
        @keyframes pulse{0%{transform:scale(1);opacity:1;}50%{transform:scale(1.04);opacity:0.9;}100%{transform:scale(1);opacity:1;}}
        @keyframes spin{to{transform:rotate(360deg);}}
        #close-settings,#close-stats,#close-qr,#close-email,#close-users,#close-sessions,#close-app-access,#close-shared-files,#close-profile,#close-edit-stats{position:absolute;top:10px;right:10px;background:none;border:none;color:var(--text-color);font-size:20px;cursor:pointer;padding:5px;z-index:10002;transition:color 0.3s;}
        @media(prefers-color-scheme:dark){#close-settings,#close-stats,#close-qr,#close-email,#close-users,#close-sessions,#close-app-access,#close-shared-files,#close-profile,#close-edit-stats{color:#333333;}}
        #close-settings:hover,#close-stats:hover,#close-qr:hover,#close-email:hover,#close-users:hover,#close-sessions:hover,#close-app-access:hover,#close-shared-files:hover,#close-profile:hover,#close-edit-stats:hover{color:#ff0000!important;}
        .edit-stats-buttons{display:flex;gap:10px;margin-top:15px;justify-content:flex-end;}
        .edit-stats-buttons button{padding:10px 20px;border-radius:24px;}
        #stats-hot-table{height:calc(85vh - 150px);width:100%;}
        .stats-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
        .edit-stats-icon-button{background:var(--primary-color);color:white;border:none;width:40px;height:40px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.3s;}
        .edit-stats-icon-button:hover{opacity:0.9;transform:scale(1.1);}
        .edit-stats-icon-button svg{width:20px;height:20px;fill:white;}
        .users-search-container{position:relative;margin-bottom:15px;}
        #users-search{padding-right:35px;}
        #users-clear-search{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#ff4444;font-size:18px;cursor:pointer;display:none;width:20px;height:20px;align-items:center;justify-content:center;border-radius:50%;}
        #users-clear-search:hover{background-color:rgba(255,68,68,0.1);}
        @media (min-width: 1400px) {
            #app-grid { grid-template-columns: repeat(auto-fill, minmax(234px, 1fr)); }
        }
        @media (min-width: 1025px) and (max-width: 1399px) {
            #app-grid { grid-template-columns: repeat(auto-fill, minmax(213px, 1fr)); }
        }
        @media (min-width: 769px) and (max-width: 1024px) {
            #app-grid { grid-template-columns: repeat(auto-fill, minmax(213px, 1fr)); }
        }
        @media (max-width: 768px) {
            #app-grid { grid-template-columns: 1fr; }
            #app-grid { grid-template-columns: 1fr; }
            .notification{bottom:20px;right:20px;left:20px;max-width:none;width:auto;}
            #stats-controls{width:100%;justify-content:center;flex-wrap:wrap;}
            #stats-controls button{flex:1;min-width:calc(33.333% - 7px);padding:8px;font-size:12px;}
            .stats-year-buttons,.stats-month-buttons{width:100%;justify-content:center;}
        }
        @media(max-width:768px){
            #auth-form{margin:2% auto;}
            .users-table,.sessions-table,.trusted-devices-table{font-size:12px;}
            .users-table th,.users-table td,.sessions-table th,.sessions-table td,.trusted-devices-table th,.trusted-devices-table td{padding:6px;}
            #shared-files-content,#sessions-content,#users-content{width:95%;height:90%;padding:20px 10px;margin:5% auto;}
            .shared-files-header{flex-wrap:wrap;align-items:stretch;}
            .shared-files-search-container{flex:1 1 100%;min-width:0;display:flex;align-items:center;}
            .shared-files-actions{flex:1 1 100%;flex-wrap:wrap;}
            .shared-files-actions select{width:100%;}
            .terminate-btn,.delete-user-btn{padding:4px 8px;font-size:11px;}
            #settings-form,#auth-form,#email-form{min-width:90%;max-width:90%;padding:20px 15px;margin:5% auto;}
            .settings-table{grid-template-columns:1fr;}
            #stats-content{width:95%;height:90vh;padding:50px 10px 10px 10px;margin:5% auto;}
            .stats-nav-buttons{gap:10px;}
            .stats-nav-btn{padding:8px 12px;font-size:12px;}
            .stats-nav-label{font-size:12px;}
            #stats-controls{gap:8px;}
            #stats-controls button{padding:10px 12px;font-size:13px;flex:1;min-width:calc(33.333% - 6px);}
            .stats-nav-row{flex-wrap:wrap;gap:8px;}
            .stats-nav-row .stats-nav-btn{flex:0 0 auto;}
            .stats-year-month-label{min-width:100%;font-size:13px;padding:8px 12px;}
            #stats-canvas-container{min-height:300px;}
            .chartjs-legend{font-size:10px!important;}
            #content{padding-top:0;height:100dvh;min-height:100dvh;max-height:100dvh;}
            #app-iframe{height:100dvh;min-height:100dvh;max-height:100dvh;}
            #profile-content{width:95%;padding:15px 10px;margin:5% auto;}
            #app-grid{padding-top:calc(70px + 12px);grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;height:100%;max-height:100%;padding-bottom:max(48px,calc(28px + env(safe-area-inset-bottom,0px)));}
            #bottom-bar{gap:5px;}
            .search-container:hover{width:100px;}
            .notification{bottom:20px;right:20px;left:20px;max-width:none;width:auto;}
        }
        @media (max-width:480px){
            #app-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
            #app-grid .app-icon-wrap{width:100%;height:auto;aspect-ratio:1/1;padding:14px;}
            #app-grid .app-name{max-width:100%;}
        }
        #shared-files-list-container,#sessions-list-container,#users-content .users-table{-webkit-overflow-scrolling:touch;overflow-x:auto;}
        #shared-files-table{width:100%;border-collapse:collapse;table-layout:auto;min-width:100%;}
        @media (max-width: 768px) {
            #shared-files-table{display: block; overflow-x: auto; white-space: nowrap;}
            #shared-files-table th, #shared-files-table td{padding: 8px 12px; white-space: nowrap; min-width: 120px;}
        }
        select{width:100%;padding:8px;border:1px solid var(--border-color);border-radius:4px;background-color:var(--modal-bg);color:var(--text-color);font-size:14px;cursor:pointer;appearance:none;-webkit-appearance:none;-moz-appearance:none;transition:border-color 0.3s,box-shadow 0.3s;}
        select:hover{border-color:var(--primary-color);}
        select:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 5px var(--primary-color);}
        select option{background-color:var(--modal-bg);color:var(--text-color);}
        input[type="color"]{width:100%;height:34px;padding:2px;border:1px solid var(--border-color);border-radius:4px;background-color:var(--modal-bg);cursor:pointer;transition:border-color 0.3s,box-shadow 0.3s;}
        input[type="color"]:hover{border-color:var(--primary-color);}
        input[type="color"]:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 5px var(--primary-color);}
        #close-settings,#close-stats,#close-edit-stats{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-settings:hover,#close-stats:hover,#close-edit-stats:hover{color:#cc0000;}
.app-icon-wrap{width:187px;height:187px;border-radius:var(--casa-tile-radius);background:var(--icon-bg-color);padding:18px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;order:1;border:1px solid var(--border-color);backdrop-filter:blur(40px) saturate(180%);-webkit-backdrop-filter:blur(40px) saturate(180%);box-shadow:0 10px 28px rgba(0,0,0,0.14);transition:transform .2s ease,box-shadow .2s ease;overflow:hidden;position:relative;}
.app-icon-wrap::before{content:'';position:absolute;inset:0;pointer-events:none;background:inherit;border-radius:inherit;mask:radial-gradient(circle at center, #fff 0%, transparent 80%);-webkit-mask:radial-gradient(circle at center, #fff 0%, transparent 80%);opacity:0.7;}
@media (hover: hover) and (pointer: fine){.app-tile-link:hover .app-icon-wrap{transform:scale(1.08) translateY(-5px);box-shadow:0 0 0 2px rgba(255,255,255,0.55),0 0 36px color-mix(in srgb,var(--primary-color) 58%,transparent),0 26px 56px rgba(0,0,0,0.32);background:color-mix(in srgb,var(--icon-bg-color) 65%,var(--primary-color) 35%);filter:brightness(1.1) saturate(1.08);overflow:hidden;}}
.app-icon{width:100%;height:100%;object-fit:contain;display:block;overflow:hidden;}
.app-icon-wrap object.app-icon{width:100%;height:100%;pointer-events:none;display:block;overflow:hidden;}
.app-icon-wrap span.app-icon-svg{overflow:hidden;}
        .sidebar-app-icon-wrap object.sidebar-app-icon{width:18px;height:18px;pointer-events:none;display:block;}
        .app-icon-wrap object.app-icon,
        .app-icon-wrap img.app-icon[src$=".svg"]{filter:brightness(0.72) contrast(1.18) saturate(1.22);}
        .sidebar-app-icon-wrap object.sidebar-app-icon,
        .sidebar-app-icon-wrap img.sidebar-app-icon[src$=".svg"]{filter:brightness(0.72) contrast(1.18) saturate(1.22);}

        .app-icon-svg{display:block;width:100%;height:100%;background:radial-gradient(circle at var(--g-hx,28%) var(--g-hy,22%),color-mix(in srgb,var(--primary-color) var(--g-light,32%),white) 0%,transparent var(--g-fade,56%)),linear-gradient(var(--g-angle,135deg),color-mix(in srgb,var(--primary-color) var(--g-start,55%),white) 0%,var(--primary-color) var(--g-mid,48%),color-mix(in srgb,var(--primary-color) var(--g-end,58%),black) 100%);-webkit-mask-image:var(--icon-src);mask-image:var(--icon-src);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain;}
        #stats-canvas-container{flex:1;position:relative;min-height:400px;width:100%;margin-top:10px;overflow:hidden;}
        #stats-canvas{width:100%!important;height:100%!important;display:block;min-height:400px;}
/* Weather widget in top-bar */
        .weather-widget {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0 12px;
            font-size: 13px;
            color: var(--text-color);
            white-space: nowrap;
            flex-shrink: 0;
            cursor: pointer;
        }
        #sidebarWeatherWidget {
            flex-shrink: 0;
            display: flex;
            width: 100%;
            box-sizing: border-box;
            margin: calc(var(--shell-menu-button-top) + var(--shell-menu-button-height) - 20px + 8px) 0 16px 0;
            justify-content: center;
            border-radius: 12px;
            flex-wrap: wrap;
            padding: 10px 0;
            background: color-mix(in srgb, var(--group-block-bg) 70%, transparent);
            border: 1px solid var(--border-color);
        }
        @media (max-width:768px){
            #sidebarWeatherWidget{margin-top:calc(var(--shell-menu-button-mobile-top) + var(--shell-menu-button-mobile-height) - 20px + 8px);}
        }
        .weather-widget .weather-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 30px;
            color: var(--primary-color);
        }
        .weather-widget .weather-icon svg,
        .weather-widget .weather-icon img {
            width: 36px;
            height: 30px;
            display: block;
        }
        .weather-widget .weather-temp {
            font-weight: 600;
            font-size: 14px;
        }
        .weather-widget .weather-location-sep {
            opacity: 0.4;
            font-size: 14px;
            margin: 0 2px;
        }
        .weather-widget .weather-pin-icon {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            stroke: #ef4444;
        }
        body.light-theme .weather-widget .weather-pin-icon,
        body.dark-theme .weather-widget .weather-pin-icon {
            stroke: #ef4444;
        }
        .weather-widget .weather-location {
            opacity: 0.75;
            font-size: 12px;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .weather-widget .weather-wind {
            opacity: 0.7;
            font-size: 12px;
        }
        .weather-widget .weather-perm {
            font-size: 12px;
            color: #f59e0b;
            cursor: default;
            max-width: 100%;
            text-align: center;
            line-height: 1.4;
            word-wrap: break-word;
            white-space: normal;
            display: none;
        }
        #sidebarWeatherWidget .weather-perm {
            order: 2;
            width: 100%;
        }
        #sidebarWeatherWidget .weather-pin-icon {
            order: 1;
        }
        @media (min-width: 601px) and (max-width: 1200px) {
            #top-bar-title {
                position: static;
                transform: none;
                flex: 1 1 auto;
                min-width: 0;
                text-align: center;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                margin-left: 0;
                order: 3;
            }
            #weatherWidget {
                order: 2;
            }
            #top-bar #notifications-btn {
                position: relative;
                right: auto;
                top: auto;
                margin-left: auto;
                order: 4;
            }
            #top-bar #support-chat-btn {
                position: relative;
                right: auto;
                top: auto;
                order: 4;
            }
        }
        @media (max-width: 600px) {
            #top-bar {
                position: fixed;
            }
            #top-bar-title,
            #weatherWidget {
                left: 50%;
                max-width: calc(100vw - 48px);
                overflow: hidden;
                position: absolute;
                text-overflow: ellipsis;
                transform: translateX(-50%);
                padding: 0 8px;
                box-sizing: border-box;
            }
            #top-bar-title {
                margin-left: 0;
                white-space: nowrap;
                opacity: 1;
                transition: opacity .6s ease;
                font-size:15px;
            }
            #weatherWidget {
                justify-content: center;
                margin: 0;
                opacity: 0;
                pointer-events: none;
                transition: opacity .6s ease;
                font-size:11px;
            }
            #top-bar.mobile-info-show-weather #top-bar-title {
                opacity: 0;
                pointer-events: none;
            }
            #top-bar.mobile-info-show-weather #weatherWidget {
                opacity: 1;
                pointer-events: auto;
            }
            .weather-widget .weather-wind, .weather-widget .weather-location, .weather-widget .weather-pin-icon, .weather-widget .weather-location-sep {
                display: none;
            }
            .weather-perm {
                font-size:11px;
                max-width:80px;
            }
        }
        @media (max-width:360px){
            #top-bar-title{font-size:14px;}
            .weather-widget .weather-icon{width:26px;height:22px;}
            .weather-widget .weather-icon svg,
            .weather-widget .weather-icon img{width:26px;height:22px;}
            .weather-widget .weather-temp{font-size:12px;}
            .weather-widget .weather-location{max-width:60px;font-size:11px;}
            .weather-widget .weather-location-sep{font-size:12px;}
.weather-widget{gap:3px;}
     }
     .check-label {
      display: flex;
      align-items: center;
      gap: 8px;
      color: inherit;
      font-size: inherit;
      cursor: pointer;
      position: relative;
    }

    .checkbox {
      width: 20px;
      height: 20px;
      position: absolute;
      opacity: 0;
      margin: 0;
      cursor: pointer;
    }

    .checkbox-box {
      width: 20px;
      height: 20px;
      border-radius: 4px;
      border: 2px solid currentColor;
      background: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: background 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
    }

    .checkbox-box svg {
      width: 14px;
      height: 11px;
    }

    .checkbox-box svg path {
      stroke-dasharray: 20;
      stroke-dashoffset: 20;
      transition: stroke-dashoffset 0.4s ease 0.05s;
    }

    .checkbox:checked ~ .checkbox-box {
      background: #22c55e;
      border-color: #22c55e;
      transform: scale(1.05);
    }

    .checkbox:checked ~ .checkbox-box svg path {
      stroke-dashoffset: 0;
    }

    .checkbox:active ~ .checkbox-box {
      transform: scale(0.9);
    }
 </style>

<style>
        /* ── Weather Forecast Modal ── */
        .weather-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(6,10,16,0.28);
            z-index: 50000;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(24px) saturate(160%);
            -webkit-backdrop-filter: blur(24px) saturate(160%);
            animation: fadeIn 0.25s ease;
        }
        .weather-modal-overlay.active {
            display: flex;
        }
        .weather-modal {
            background: var(--modal-bg);
            border-radius: 30px;
            width: 92%;
            max-width: 680px;
            max-height: 90vh;
            overflow: auto;
            box-shadow: 0 24px 80px rgba(0,0,0,0.5);
            animation: slideUp 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            color: var(--text-color);
            display: flex;
            flex-direction: column;
        }
        .weather-modal-close {
            position: absolute;
            top: 24px;
            right: 16px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--group-block-bg);
            color: var(--text-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: background 0.2s;
            z-index: 10;
        }
        .weather-modal-close:hover {
            background: var(--border-color);
        }
        .weather-wind-chart-btn,
        .weather-settings-btn {
            position: absolute;
            top: 24px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--group-block-bg);
            color: var(--text-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            z-index: 10;
        }
        .weather-settings-btn {
            right: 60px;
        }
        .weather-wind-chart-btn {
            right: 104px;
        }
        .weather-wind-chart-btn:hover,
        .weather-settings-btn:hover {
            background: var(--border-color);
        }
        .weather-wind-chart-btn svg,
        .weather-settings-btn svg {
            width: 18px;
            height: 18px;
        }
        .weather-modal-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px 148px 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(180deg, color-mix(in srgb, var(--group-block-bg) 85%, transparent), transparent);
        }
        .weather-modal-body {
            padding: 20px 24px;
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) var(--modal-bg);
        }
        .weather-modal-body::-webkit-scrollbar {
            width: 8px;
        }
        .weather-modal-body::-webkit-scrollbar-track {
            background: var(--modal-bg);
        }
        .weather-modal-body::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }
        .weather-modal-body::-webkit-scrollbar-thumb:hover {
            background: color-mix(in srgb, var(--border-color) 70%, var(--text-color));
        }
        .weather-settings-modal {
            position: relative;
            width: 92%;
            max-width: 720px;
            max-height: 90vh;
            overflow: auto;
            background: var(--modal-bg);
            border-radius: 30px;
            padding: 0;
            box-shadow: 0 24px 80px rgba(0,0,0,0.5);
            border: 0;
            color: var(--text-color);
        }
        #weatherSettingsModalContent {
            display: flex;
            flex-direction: column;
            max-height: 90vh;
            overflow: hidden;
        }
        .weather-settings-header {
            padding: 24px 72px 20px 24px;
            border-bottom: 0;
            background: var(--modal-bg);
        }
        .weather-settings-header h3 {
            margin: 0 0 18px 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
        }
        .location-switch {
            position: relative;
            display: flex;
            width: min(100%, 430px);
            padding: 4px;
            background: var(--modal-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            margin: 0;
            box-sizing: border-box;
        }
        .location-switch .slider {
            position: absolute;
            top: 4px;
            bottom: 4px;
            left: 4px;
            width: calc(50% - 4px);
            background: var(--modal-bg);
            border-radius: 16px;
            box-shadow: 0 1px 8px rgba(0,0,0,.14);
            transition: transform .25s ease;
        }
        .location-switch.city .slider {
            transform: translateX(100%);
        }
        .location-switch button {
            position: relative;
            z-index: 1;
            width: 50%;
            padding: 12px 14px;
            border: 0;
            background: var(--modal-bg);
            border-radius: 16px;
            color: color-mix(in srgb, var(--text-color) 55%, transparent);
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.25;
            cursor: pointer;
            transition: color .2s;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .location-switch button svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            stroke: currentColor;
        }
        .location-switch button.active svg {
            stroke: #dc2626;
        }
        .location-switch button.active {
            color: var(--primary-color);
        }
        #weatherCitySelection {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 22px 24px 0;
            background: var(--modal-bg);
        }
        .weather-location-selectors {
            display: flex;
            flex-direction: column;
            gap: 14px;
            align-items: center;
        }
        .weather-selector {
            display: flex;
            flex-direction: column;
            gap: 0;
            width: min(100%, 560px);
            margin: 0 auto;
        }
        .weather-selector-title {
            display: none;
        }
        .weather-selector .custom-select {
            position: relative;
            width: 100%;
        }
        .weather-selector .custom-select-selected {
            min-height: 50px;
            padding: 12px 18px;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            background: var(--modal-bg);
            color: var(--text-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-sizing: border-box;
            box-shadow: none;
        }
        .weather-selector .custom-select-selected:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 16%, transparent);
        }
        .weather-selector .custom-select-selected span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: flex;
            align-items: center;
        }
        .weather-selector .custom-select-selected::after {
            content: "▼";
            font-size: 10px;
            margin-left: 10px;
            opacity: .55;
            flex-shrink: 0;
        }
        .weather-selector .custom-select-options {
            display: none;
            position: fixed;
            max-height: min(280px, 36vh);
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            background: var(--modal-bg);
            box-shadow: 0 16px 40px rgba(0,0,0,.22);
            z-index: 100200;
            width: min(560px, calc(100vw - 32px));
        }
        .weather-selector .custom-select-options.show {
            display: block;
        }
        .weather-selector .custom-select-option {
            padding: 11px 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            color: var(--text-color);
        }
        .weather-selector .custom-select-option:hover,
        .weather-selector .custom-select-option.active {
            background: var(--group-block-bg);
        }
        .weather-selector .custom-select-option img {
            width: 20px;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .weather-selection-list {
            max-height: 280px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--modal-bg);
        }
        .weather-selection-empty {
            padding: 16px;
            text-align: center;
            color: color-mix(in srgb, var(--text-color) 60%, transparent);
            font-size: 14px;
        }
        .weather-search-container {
            position: relative;
        }
        .weather-search-input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--modal-bg);
            color: var(--text-color);
            font-size: 14px;
            margin-bottom: 12px;
            box-sizing: border-box;
        }
        .weather-search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 16%, transparent);
        }
        .weather-selected-country {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            margin-bottom: 12px;
            border: 1px solid color-mix(in srgb, var(--primary-color) 35%, transparent);
            border-radius: 20px;
            background: var(--modal-bg);
            font-size: 14px;
            color: var(--text-color);
        }
        .weather-selected-country .flag {
            width: 24px;
            height: 16px;
            object-fit: cover;
            border-radius: 2px;
        }
        .weather-selected-country button {
            margin-left: auto;
            border: 0;
            background: transparent;
            color: var(--primary-color);
            font: inherit;
            font-size: 13px;
            cursor: pointer;
            padding: 4px 0;
        }
        .weather-selection-list {
            max-height: min(34vh, 300px);
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--modal-bg);
            margin: 0;
            transition: opacity .2s ease, transform .2s ease;
        }
        .weather-selection-item {
            width: 100%;
            padding: 13px 14px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-color);
            background: transparent;
            border-left: 0;
            border-right: 0;
            border-top: 0;
            font: inherit;
            text-align: left;
        }
        .weather-selection-item span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .weather-selection-item:last-child {
            border-bottom: none;
        }
        .weather-selection-item:hover, .weather-selection-item.active {
            background: color-mix(in srgb, var(--primary-color) 10%, transparent);
        }
        .weather-selection-item.selected {
            background: var(--primary-color);
            color: white;
        }
        .weather-selection-item small {
            margin-left: auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            opacity: 0.62;
            font-size: 12px;
        }
        .weather-selection-empty {
            padding: 18px 14px;
            text-align: center;
            color: var(--text-color);
            opacity: 0.62;
            font-size: 13px;
        }
        .weather-selection-item .flag {
            width: 24px;
            height: 18px;
            margin-right: 12px;
            border: 1px solid rgba(0,0,0,0.1);
            object-fit: cover;
            object-position: center;
            flex-shrink: 0;
        }
        .weather-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: color-mix(in srgb, var(--text-color) 7%, transparent);
            border: 1px solid var(--border-color);
            border-radius: 9px;
            color: var(--primary-color);
            cursor: pointer;
            padding: 8px 10px;
            margin-bottom: 12px;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.2s, border-color 0.2s;
        }
        .weather-back-btn:hover {
            background: color-mix(in srgb, var(--primary-color) 12%, transparent);
            border-color: color-mix(in srgb, var(--primary-color) 32%, var(--border-color));
        }
        .weather-settings-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 18px 24px 24px;
            border-top: 1px solid var(--border-color);
            background: color-mix(in srgb, var(--group-block-bg) 42%, transparent);
        }
        .weather-settings-cancel, .weather-settings-save {
            padding: 11px 18px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border-color);
            min-width: 104px;
        }
        .weather-settings-cancel {
            background: var(--group-block-bg);
            color: var(--text-color);
        }
        .weather-settings-cancel:hover {
            background: var(--border-color);
        }
        .weather-settings-save {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .weather-settings-save:hover {
            opacity: 0.9;
        }
        .weather-geo-prompt {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
            text-align: center;
            padding: 42px 24px;
            opacity: 0.86;
        }
        .weather-loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: var(--text-color);
            opacity: 0.7;
        }
        @media (max-width: 480px) {
            .weather-modal-close {
                top: 20px;
                right: 12px;
                width: 34px;
                height: 34px;
            }
            .weather-wind-chart-btn {
                top: 20px;
                right: 92px;
                width: 34px;
                height: 34px;
            }
            .weather-settings-btn {
                top: 20px;
                right: 54px;
                width: 34px;
                height: 34px;
            }
            .weather-modal-header {
                padding: 56px 132px 16px 16px;
            }
            .weather-settings-modal {
                width: 95%;
            }
            .weather-settings-header {
                padding: 58px 16px 16px;
            }
            .location-switch {
                width: 100%;
            }
            #weatherCitySelection {
                padding: 14px 16px 0;
            }
            .weather-step {
                padding: 14px;
                gap: 12px;
            }
            .weather-step-dot {
                width: 36px;
                height: 36px;
                border-radius: 10px;
            }
            .weather-selection-list {
                max-height: 260px;
            }
            .weather-settings-actions {
                flex-direction: column-reverse;
                padding: 14px 16px 16px;
            }
            .location-switch button {
                font-size: 12px;
                gap: 6px;
            }
            .weather-settings-cancel, .weather-settings-save {
                width: 100%;
            }
        }
        .weather-modal-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px 56px 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(180deg, color-mix(in srgb, var(--group-block-bg) 85%, transparent), transparent);
        }
        .weather-modal-header-icon {
            flex-shrink: 0;
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: var(--group-block-bg);
            box-shadow: inset 0 0 0 1px var(--border-color);
        }
        .weather-modal-header-icon img {
            width: 42px;
            height: 42px;
        }
        .weather-modal-header-info {
            flex: 1;
            min-width: 0;
        }
        .weather-modal-title {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            opacity: 0.65;
            margin-bottom: 4px;
        }
        .weather-modal-city {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.2;
        }
        .weather-modal-current {
            font-size: 14px;
            opacity: 0.75;
            margin-top: 6px;
            line-height: 1.45;
        }
        @media (max-width: 480px) {
            .weather-modal-header {
                padding: 56px 16px 16px;
            }
        }
        .weather-modal-body {
            padding: 20px 24px 0;
        }
        .weather-weekly {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 0;
        }
        .weather-day-row {
            display: grid;
            grid-template-columns: minmax(92px, 1.15fr) 34px 38px minmax(0, 1.2fr) auto auto;
            align-items: center;
            gap: 10px;
            padding: 11px 12px;
            border-radius: 14px;
            background: var(--group-block-bg);
            transition: background 0.2s;
        }
        .weather-day-row:hover {
            background: var(--border-color);
        }
        .weather-day-name {
            font-size: 13px;
            font-weight: 600;
            line-height: 1.25;
            word-break: break-word;
        }
        .weather-day-date {
            font-size: 12px;
            opacity: 0.6;
            text-align: center;
        }
        .weather-day-icon-wrap {
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .weather-day-icon-wrap img {
            width: 30px;
            height: 30px;
        }
        .weather-day-desc {
            font-size: 13px;
            opacity: 0.75;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .weather-day-temps {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }
        .weather-day-temp-high {
            color: var(--text-color);
        }
        .weather-day-temp-low {
            opacity: 0.5;
        }
        .weather-day-precip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
            color: #2563eb;
            flex-shrink: 0;
            min-width: 72px;
            max-width: 92px;
            padding: 5px 8px;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.12);
            border: 1px solid rgba(37, 99, 235, 0.18);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .weather-day-precip.is-dry {
            color: var(--text-color);
            opacity: 0.55;
            background: color-mix(in srgb, var(--group-block-bg) 80%, transparent);
            border-color: var(--border-color);
        }
        .weather-day-precip svg {
            width: 12px;
            height: 12px;
            flex-shrink: 0;
        }
        .weather-chart-wrapper {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            background: var(--group-block-bg);
            border-radius: 16px;
            padding: 16px 16px 12px;
        }
        .weather-chart-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .weather-chart-container {
            width: 100%;
            position: relative;
        }
        .weather-chart-container canvas {
            width: 100%;
            height: 220px;
            display: block;
            border-radius: 8px;
        }
        .weather-chart-legend {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-top: 10px;
            font-size: 12px;
            opacity: 0.75;
        }
        .weather-chart-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .weather-chart-legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .weather-location-enable {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 0;
            padding: 11px 16px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--primary-color);
            color: #fff;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }
        .weather-location-enable svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
        }
        .weather-modal::-webkit-scrollbar {
            width: 6px;
        }
        .weather-modal::-webkit-scrollbar-track {
            background: transparent;
        }
        .weather-modal::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @media (max-width: 500px) {
            .weather-modal {
                border-radius: 26px;
            }
            .weather-modal-header {
                padding: 18px 52px 16px 16px;
            }
            .weather-modal-body {
                padding: 16px 16px 0;
            }
            .weather-day-row {
                grid-template-columns: minmax(78px, 1fr) 28px 32px minmax(0, 1fr) auto;
                grid-template-areas:
                    "name name name name temps"
                    "date icon desc precip precip";
                gap: 8px 10px;
            }
            .weather-day-name { grid-area: name; }
            .weather-day-date { grid-area: date; text-align: left; }
            .weather-day-icon-wrap { grid-area: icon; }
            .weather-day-desc { grid-area: desc; }
            .weather-day-temps { grid-area: temps; justify-self: end; }
            .weather-day-precip { grid-area: precip; justify-self: end; max-width: none; }
            .weather-day-temps {
                font-size: 12px;
                gap: 4px;
            }
            .weather-chart-container canvas {
                height: 190px;
            }
            .weather-modal-header-icon {
                width: 48px;
                height: 48px;
            }
            .weather-modal-header-icon img {
                width: 36px;
                height: 36px;
            }
            .weather-modal-city {
                font-size: 18px;
            }
        }
    </style>
</head>
<?php
$_guideCfg = require __DIR__ . '/../onboarding/config.php';
$_guideEnabled = !empty($_guideCfg['enabled'])
    && $path === '/main'
    && $isHomeDashboard
    && isset($_SESSION['user']);
$_wp = $savedWallpaper ?? ($config['wallpaper'] ?? 'default');
$_wpUrl = ap_wallpaper_asset_url($_wp);
$_bodyClasses = [];
if ($path === '/main' && $selectedApp && in_array($selectedApp, array_map(function($appData){return $appData['folder'];}, $apps), true)) { $_bodyClasses[] = 'shell-app-open'; }
$_bodyClasses[] = ($_wp === 'default') ? 'wallpaper-default' : 'wallpaper-custom';
$_bodyClasses[] = ($config['theme'] === 'dark') ? 'theme-dark' : 'theme-light';
$_bodyClasses[] = ($config['theme'] === 'dark') ? 'dark-theme' : 'light-theme';
?>
<body class="<?php echo my_htmlspecialchars(implode(' ', $_bodyClasses)); ?>"<?php if ($_wpUrl !== ''): ?> style="--wallpaper-url:url('<?php echo my_htmlspecialchars($_wpUrl); ?>')"<?php endif; ?>>
<?php if($path === '/login' || $path === '/register' || $path === '/one-time-pin'): ?>
<div id="auth-top-bar">
    <div class="auth-top-bar-left">
        <a href="/" class="auth-brand-link" title="<?php echo my_htmlspecialchars($translations['home_tooltip'] ?? 'Home'); ?>">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
        </a>
    </div>
    <div class="auth-top-bar-right">
        <div class="theme-toggle" id="authThemeToggle">
            <button data-theme="dark" class="<?php echo $config['theme'] === 'dark' ? 'active' : ''; ?>">🌙 Dark</button>
            <button data-theme="light" class="<?php echo $config['theme'] === 'light' ? 'active' : ''; ?>">☀️ Light</button>
        </div>
        <div class="lang-selector" id="authLangSelector" data-value="<?php echo my_htmlspecialchars($config['language']); ?>">
            <button type="button" class="lang-selector-btn" id="authLangSelectorButton" aria-haspopup="listbox" aria-expanded="false" aria-label="Language">
                <span class="lang-flag lang-flag-<?php echo my_htmlspecialchars($config['language']); ?>"></span>
                <span class="lang-selector-label"><?php $__authLangLabels=['en'=>'English','uk'=>'Українська','ru'=>'Русский','be'=>'Беларуская','pl'=>'Polski','cs'=>'Čeština']; echo my_htmlspecialchars($__authLangLabels[$config['language']] ?? ucfirst($config['language'])); ?></span>
                <span class="lang-selector-chevron"></span>
            </button>
            <div class="lang-selector-menu" id="authLangSelectorMenu" role="listbox">
                <button type="button" class="lang-selector-option <?php echo $config['language'] === 'en' ? 'active' : ''; ?>" data-lang="en" role="option" aria-selected="<?php echo $config['language'] === 'en' ? 'true' : 'false'; ?>"><span class="lang-flag lang-flag-en"></span><span>English</span></button>
                <button type="button" class="lang-selector-option <?php echo $config['language'] === 'uk' ? 'active' : ''; ?>" data-lang="uk" role="option" aria-selected="<?php echo $config['language'] === 'uk' ? 'true' : 'false'; ?>"><span class="lang-flag lang-flag-uk"></span><span>Українська</span></button>
                <button type="button" class="lang-selector-option <?php echo $config['language'] === 'ru' ? 'active' : ''; ?>" data-lang="ru" role="option" aria-selected="<?php echo $config['language'] === 'ru' ? 'true' : 'false'; ?>"><span class="lang-flag lang-flag-ru"></span><span>Русский</span></button>
                <button type="button" class="lang-selector-option <?php echo $config['language'] === 'be' ? 'active' : ''; ?>" data-lang="be" role="option" aria-selected="<?php echo $config['language'] === 'be' ? 'true' : 'false'; ?>"><span class="lang-flag lang-flag-be"></span><span>Беларуская</span></button>
                <button type="button" class="lang-selector-option <?php echo $config['language'] === 'pl' ? 'active' : ''; ?>" data-lang="pl" role="option" aria-selected="<?php echo $config['language'] === 'pl' ? 'true' : 'false'; ?>"><span class="lang-flag lang-flag-pl"></span><span>Polski</span></button>
                <button type="button" class="lang-selector-option <?php echo $config['language'] === 'cs' ? 'active' : ''; ?>" data-lang="cs" role="option" aria-selected="<?php echo $config['language'] === 'cs' ? 'true' : 'false'; ?>"><span class="lang-flag lang-flag-cs"></span><span>Čeština</span></button>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    var TOPBAR_THEME_COOKIE='sk_theme';
    var BROWSER_THEME_COOKIE='browser_theme';
    function compactAuthTheme(theme){
        if(theme==='auto'){theme=(window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}
        var dark=theme==='dark';
        var r=document.documentElement;
        r.style.setProperty('--bg-color',dark?'#0f1419':'#e8eef5');
        r.style.setProperty('--text-color',dark?'#f4f7fb':'#1a2332');
        r.style.setProperty('--modal-bg',dark?'rgba(18,24,32,0.62)':'rgba(255,255,255,0.68)');
        r.style.setProperty('--border-color',dark?'rgba(255,255,255,0.14)':'rgba(15,30,50,0.14)');
        r.style.setProperty('--group-block-bg',dark?'rgba(255,255,255,0.10)':'rgba(255,255,255,0.38)');
        r.style.setProperty('--glass-shadow',dark?'0 18px 50px rgba(0,0,0,0.45)':'0 18px 50px rgba(30,50,80,0.14)');
        document.body.classList.toggle('dark-theme',dark);
        document.body.classList.toggle('light-theme',!dark);
        var tg=document.getElementById('authThemeToggle');
        if(tg){tg.querySelectorAll('button').forEach(function(b){b.classList.toggle('active',b.dataset.theme===theme);});}
    }
    window.applyAuthTheme=function(theme){
        if(typeof window.applyThemeRuntime==='function'){window.applyThemeRuntime(theme);return;}
        compactAuthTheme(theme);
    };
    var toggle=document.getElementById('authThemeToggle');
    if(toggle){
        toggle.querySelectorAll('button').forEach(function(btn){
            btn.addEventListener('click',function(){
                var theme=this.dataset.theme;
                window.applyAuthTheme(theme);
                document.cookie=TOPBAR_THEME_COOKIE+'='+theme+'; path=/; max-age=31536000; samesite=Lax';
                document.cookie=BROWSER_THEME_COOKIE+'='+theme+'; path=/; max-age=86400; samesite=Lax';
                var url=new URL(window.location.href);
                url.searchParams.set('theme',theme);
                window.history.replaceState({},'',url);
            });
        });
    }
    var langSel=document.getElementById('authLangSelector');
    var langBtn=document.getElementById('authLangSelectorButton');
    var langMenu=document.getElementById('authLangSelectorMenu');
    if(langSel&&langBtn&&langMenu){
        langBtn.addEventListener('click',function(e){
            e.stopPropagation();
            langSel.classList.toggle('open');
            langBtn.setAttribute('aria-expanded',langSel.classList.contains('open')?'true':'false');
        });
        langMenu.querySelectorAll('.lang-selector-option').forEach(function(opt){
            opt.addEventListener('click',function(){
                window.setLanguage(opt.dataset.lang);
                langSel.classList.remove('open');
                langBtn.setAttribute('aria-expanded','false');
            });
        });
        document.addEventListener('click',function(e){
            if(!langSel.contains(e.target)){
                langSel.classList.remove('open');
                langBtn.setAttribute('aria-expanded','false');
            }
        });
    }
    window.setLanguage=function(lang){
        document.cookie='sk_language='+lang+'; path=/; max-age=31536000; samesite=Lax';
        var url=new URL(window.location.href);
        url.searchParams.set('lang',lang);
        window.history.replaceState({},'',url);
        window.location.reload();
    };
})();
</script>
<?php endif; ?>
<?php if($showTopBarTitle): ?>
<div id="top-bar">
    <?php if($isHomeDashboard): ?>
    <button id="menu-button" type="button" aria-label="<?php echo my_htmlspecialchars($translations['nav_menu_title'] ?? 'Menu'); ?>" title="<?php echo my_htmlspecialchars($translations['nav_menu_title'] ?? 'Menu'); ?>"><span class="menu-button-glyph" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/></svg></span></button>
    <a id="home-root-button" href="/" aria-label="<?php echo my_htmlspecialchars($translations['home_tooltip']); ?>" title="<?php echo my_htmlspecialchars($translations['home_tooltip']); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
    <?php endif; ?>
    <div id="top-bar-title"><?php echo my_htmlspecialchars($translations['shell_application'] ?? 'Shell Application'); ?></div>
    <div class="weather-widget weather-widget-instance" id="weatherWidget">
        <span class="weather-icon"></span>
        <span class="weather-temp">--°C</span>
        <span class="weather-location-sep">|</span>
        <svg class="weather-pin-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
        <span class="weather-location">--</span>
        <span class="weather-wind"></span>
        <span class="weather-perm" style="display:none"><?php echo my_htmlspecialchars($translations['weather_perm_denied'] ?? 'Please allow location access'); ?></span>
    </div>
    <?php if($path==='/main'&&!($selectedApp&&in_array($selectedApp,array_map(function($appData){return $appData['folder'];},$apps)))):?>
        <button type="button" id="notifications-btn" title="<?php echo my_htmlspecialchars($translations['notifications'] ?? 'Notifications'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['notifications'] ?? 'Notifications'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8a6 6 0 0 0-12 0c0 4.499-1.411 5.956-2.738 7.326"/></svg>
            <span id="notifications-unread-badge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;border-radius:12px;padding:2px 6px;font-size:11px;font-weight:700;min-width:18px;text-align:center;box-shadow:0 2px 8px rgba(239,68,68,0.5);"></span>
        </button>
        <button type="button" id="support-chat-btn" title="<?php echo my_htmlspecialchars($translations['support_chat'] ?? 'Support Chat'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
            <span id="chat-unread-badge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;border-radius:12px;padding:2px 6px;font-size:11px;font-weight:700;min-width:18px;text-align:center;box-shadow:0 2px 8px rgba(239,68,68,0.5);"></span>
        </button>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php if(isset($showOtpPage)&&$showOtpPage):
$clientIpOtp=trim(explode(',',($_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??'0.0.0.0'))[0]);
$otpRemainingLock=getIpLockoutSeconds($clientIpOtp,'otp');
?>
<style>
.otp-wrap{position:fixed;top:var(--auth-topbar-h);right:0;bottom:0;left:0;display:flex;align-items:center;justify-content:center;z-index:2000;overflow:hidden;}
.otp-wrap .gradient-bg{position:absolute;top:0;left:0;width:100%;height:100%;background:var(--auth-gradient);opacity:0.8;z-index:0;pointer-events:none;}
.otp-card{background:var(--modal-bg);padding:30px;border-radius:var(--casa-radius);width:90%;max-width:360px;box-shadow:var(--glass-shadow);position:relative;z-index:1;backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);}
.otp-title{font-size:22px;font-weight:700;margin-bottom:6px;text-align:center;}
.otp-subtitle{font-size:13px;opacity:.6;text-align:center;margin-bottom:20px;line-height:1.5;}
.otp-warning{font-size:12px;color:#ff9800;background:rgba(255,152,0,.1);border:1px solid rgba(255,152,0,.4);border-radius:6px;padding:8px 12px;margin-bottom:16px;text-align:center;}
.otp-display{display:flex;gap:8px;justify-content:center;margin-bottom:16px;}
.otp-digit{width:44px;height:56px;border:2px solid var(--border-color);border-radius:8px;font-size:26px;font-weight:700;text-align:center;background:var(--bg-color);color:var(--text-color);line-height:56px;transition:border-color .2s;display:inline-block;}
.otp-digit.filled{border-color:var(--primary-color);}
.otp-digit.cursor{border-color:var(--primary-color);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary-color) 25%,transparent);}
.otp-hidden-input{position:absolute;opacity:0;width:1px;height:1px;top:0;left:0;}
.otp-numpad{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;}
.otp-key{padding:14px;font-size:20px;font-weight:600;border:1px solid var(--border-color);border-radius:8px;background:var(--group-block-bg);color:var(--text-color);cursor:pointer;text-align:center;user-select:none;-webkit-tap-highlight-color:transparent;transition:background .15s;}
.otp-key:active,.otp-key:hover{background:var(--primary-color);color:#fff;border-color:var(--primary-color);}
.otp-key.del{font-size:16px;}
.otp-submit-btn{width:100%;padding:12px;background:var(--primary-color);color:#fff;border:none;border-radius:24px;font-size:16px;font-weight:600;cursor:pointer;margin-bottom:12px;}
.otp-submit-btn:hover{opacity:.9;}
.otp-submit-btn:disabled{opacity:.45;cursor:not-allowed;}
.otp-error{color:#ff4444;font-size:13px;text-align:center;margin-bottom:10px;min-height:20px;}
.otp-back-wrap{text-align:center;margin-top:4px;}
.otp-back{display:inline-flex;align-items:center;justify-content:center;gap:8px;margin:0 auto;padding:10px 20px;border-radius:24px;background:var(--group-block-bg);color:var(--primary-color);font-size:14px;font-weight:600;text-decoration:none;border:1px solid color-mix(in srgb,var(--primary-color) 35%,transparent);transition:background .2s,transform .2s,box-shadow .2s,border-color .2s;}
.otp-back:hover{text-decoration:none;background:color-mix(in srgb,var(--primary-color) 14%,var(--modal-bg));transform:translateY(-1px);box-shadow:0 4px 14px rgba(0,0,0,.15);border-color:color-mix(in srgb,var(--primary-color) 55%,transparent);}
.otp-back:active{transform:translateY(0);}
.otp-back svg{flex-shrink:0;width:18px;height:18px;fill:currentColor;opacity:.95;}
@media(min-width:768px){.otp-numpad{display:none;}}
</style>
<div class="otp-wrap">
    <div class="gradient-bg"></div>
    <div class="otp-card">
        <div class="otp-title"><?php echo my_htmlspecialchars($translations['otp_page_title']??'One-Time PIN Login'); ?></div>
        <div class="otp-subtitle"><?php echo my_htmlspecialchars($translations['otp_subtitle']??'Enter the 6-digit one-time code to sign in.'); ?></div>
        <div class="otp-warning"><?php echo my_htmlspecialchars($translations['otp_warning_attempts']??'⚠ You have 3 attempts. After 3 wrong codes your IP will be blocked for 3 minutes.'); ?></div>
        <div class="otp-error" id="otp-err"><?php echo my_htmlspecialchars($otpError??''); ?></div>
        <form method="post" action="/one-time-pin" id="otp-form">
            <input type="hidden" name="verify_otp" value="1">
            <input type="hidden" name="otp_pin" id="otp-pin-val">
            <div class="otp-display" id="otp-boxes">
                <?php for($i=0;$i<6;$i++): ?><div class="otp-digit<?php echo $i===0?' cursor':'';?>" id="otp-d<?php echo $i;?>"></div><?php endfor; ?>
            </div>
            <input type="text" id="otp-real" class="otp-hidden-input" inputmode="numeric" maxlength="6" autocomplete="one-time-code" autofocus>
            <div class="otp-numpad" id="otp-numpad">
                <?php for($n=1;$n<=9;$n++): ?><button type="button" class="otp-key" data-key="<?php echo $n;?>"><?php echo $n;?></button><?php endfor; ?>
                <div></div>
                <button type="button" class="otp-key" data-key="0">0</button>
                <button type="button" class="otp-key del" data-key="del">⌫</button>
            </div>
            <button type="submit" class="otp-submit-btn" id="otp-submit-btn"<?php echo $otpRemainingLock>0?' disabled':'';?>><?php echo my_htmlspecialchars($translations['otp_submit']??'Sign In with PIN'); ?></button>
        </form>
        <div class="otp-back-wrap"><a href="/login" class="otp-back"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg><span><?php echo my_htmlspecialchars($translations['back_to_login']??'Back to Login'); ?></span></a></div>
    </div>
</div>
<script>
(function(){
    var digits=[];
    var input=document.getElementById('otp-real');
    var pinVal=document.getElementById('otp-pin-val');
    var form=document.getElementById('otp-form');
    var errEl=document.getElementById('otp-err');
    var submitBtn=document.getElementById('otp-submit-btn');
    function renderBoxes(){
        for(var i=0;i<6;i++){
            var box=document.getElementById('otp-d'+i);
            box.textContent=digits[i]||'';
            box.className='otp-digit';
            if(digits[i])box.classList.add('filled');
            if(i===digits.length&&digits.length<6)box.classList.add('cursor');
        }
    }
    function push(ch){if(digits.length<6&&/[0-9]/.test(ch)){digits.push(ch);renderBoxes();}}
    function pop(){if(digits.length>0){digits.pop();renderBoxes();}}
    document.querySelectorAll('.otp-key').forEach(function(btn){
        btn.addEventListener('click',function(){var k=this.dataset.key;if(k==='del')pop();else push(k);});
    });
    input.addEventListener('keydown',function(e){
        if(/^[0-9]$/.test(e.key)){push(e.key);e.preventDefault();}
        else if(e.key==='Backspace'){pop();e.preventDefault();}
        else if(e.key==='Enter'){doSubmit();e.preventDefault();}
    });
    input.addEventListener('paste',function(e){
        var t=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').substring(0,6);
        digits=t.split('');renderBoxes();e.preventDefault();
    });
    document.getElementById('otp-boxes').addEventListener('click',function(){input.focus();});
    function doSubmit(){
        if(digits.length!==6){errEl.textContent=<?php echo json_encode($translations['otp_enter_6_digits']??'Please enter all 6 digits'); ?>;return;}
        pinVal.value=digits.join('');form.submit();
    }
    form.addEventListener('submit',function(e){
        if(digits.length!==6){e.preventDefault();errEl.textContent=<?php echo json_encode($translations['otp_enter_6_digits']??'Please enter all 6 digits'); ?>;return;}
        pinVal.value=digits.join('');
    });
    var lockMessageTemplate=<?php echo json_encode($translations['otp_ip_locked']??'IP blocked. Try in %d sec.'); ?>;
    function formatLockMessage(sec){
        return lockMessageTemplate.replace('%d', String(Math.max(0, sec)));
    }
    var remaining=<?php echo (int)$otpRemainingLock;?>;
    if(remaining>0){
        var interval=setInterval(function(){
            remaining--;
            if(remaining<=0){clearInterval(interval);errEl.textContent='';if(submitBtn)submitBtn.disabled=false;}
            else{errEl.textContent=formatLockMessage(remaining);}
        },1000);
        if(errEl.textContent==='')errEl.textContent=formatLockMessage(remaining);
    }
    renderBoxes();
    input.focus();
    if(window.applyAuthTheme){window.applyAuthTheme(<?php echo json_encode($config['theme']); ?>);}
})();
</script>
<?php
ob_end_flush();
exit;
endif; // showOtpPage
?>
<?php if($showLogin): ?>
<div id="auth-modal">
    <div class="gradient-bg"></div>
    <div id="auth-form">
        <?php if($path === '/register' && isset($pendingGoogleData) && $pendingGoogleData): ?>
            <div class="auth-tabs" style="display: none;"></div>
            <?php if(isset($showReVerifyForm)&&$showReVerifyForm): ?>
            <div id="set-password-content" class="auth-content active" style="text-align:center;">
                <div style="font-size:48px;margin-bottom:12px;">&#x2709;&#xFE0F;</div>
                <h3 style="margin:0 0 8px 0;color:var(--text-color);"><?php echo my_htmlspecialchars($translations['email_not_verified_title']); ?></h3>
                <p style="font-size:14px;opacity:.7;margin:0 0 20px 0;line-height:1.5;"><?php echo my_htmlspecialchars($translations['email_not_verified_desc']); ?></p>
                <form method="post" action="/register">
                    <input type="hidden" name="email" value="<?php echo my_htmlspecialchars($reVerifyEmail??$pendingGoogleData['email']); ?>">
                    <button type="submit" name="resend_verification" class="auth-button"><?php echo my_htmlspecialchars($translations['resend_verification_link']); ?></button>
                </form>
                <div style="margin-top:15px;"><a href="/login" style="color:var(--primary-color);text-decoration:none;"><?php echo my_htmlspecialchars($translations['back_to_login']); ?></a></div>
            </div>
            <?php elseif(isset($showVerificationSent)&&$showVerificationSent): ?>
            <div id="set-password-content" class="auth-content active" style="text-align:center;">
                <div style="font-size:48px;margin-bottom:12px;">&#x1F4EC;</div>
                <div class="auth-success"><?php echo my_htmlspecialchars($authSuccess??''); ?></div>
                <p style="font-size:14px;opacity:.7;margin:16px 0;line-height:1.5;"><?php echo my_htmlspecialchars($translations['click_link_to_verify']); ?></p>
                <div style="margin-top:15px;"><a href="/login" style="color:var(--primary-color);text-decoration:none;"><?php echo my_htmlspecialchars($translations['back_to_login']); ?></a></div>
            </div>
            <?php else: ?>
            <div id="set-password-content" class="auth-content active">
                <h2><?php echo my_htmlspecialchars($translations['set_password']); ?></h2>
                <?php if(isset($_GET['new_oauth_user']) || isset($_GET['new_google_user'])): ?>
                <div class="auth-error" style="margin-bottom:10px;">
                    <?php echo my_htmlspecialchars($translations['oauth_user_not_found'] ?? $translations['google_user_not_found']); ?>
                </div>
                <?php elseif(isset($_GET['password_required']) || !empty($_SESSION['pending_set_password_only'])): ?>
                <div class="auth-error" style="margin-bottom:10px;">
                    <?php echo my_htmlspecialchars($translations['password_required']); ?>
                </div>
                <?php endif; ?>
                <?php if(isset($authError)):?><div class="auth-error"><?php echo my_htmlspecialchars($authError); ?></div><?php endif;?>
                <div class="google-user-info">
                    <?php if($pendingGoogleData['photo'] !== 'default_avatar.png'): ?>
                        <img src="<?php echo my_htmlspecialchars(app_avatar_src($pendingGoogleData['photo'])); ?>" alt="Google Avatar" class="google-avatar">
                    <?php endif; ?>
                    <div class="google-email"><?php echo my_htmlspecialchars($pendingGoogleData['email']); ?></div>
                    <div class="google-name"><?php echo my_htmlspecialchars($pendingGoogleData['name']); ?></div>
                </div>
                <form method="post" action="/register">
                    <input type="hidden" name="email" value="<?php echo my_htmlspecialchars($pendingGoogleData['email']); ?>">
                    <input type="hidden" name="name" value="<?php echo my_htmlspecialchars($pendingGoogleData['name']); ?>">
                    <input type="hidden" name="photo" value="<?php echo my_htmlspecialchars($pendingGoogleData['photo']); ?>">
                    <input type="hidden" name="reg_method" value="<?php echo my_htmlspecialchars($pendingGoogleData['reg_method'] ?? (($pendingGoogleData['provider'] ?? '') === 'github' ? 'GitHub' : 'Google')); ?>">
                    <div class="password-input-container">
                        <input type="password" name="password" id="register-password" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['set_password']); ?>" required>
                        <button type="button" class="password-toggle" data-target="register-password">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="password-input-container">
                        <input type="password" name="confirm_password" id="register-confirm-password" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['confirm_set_password']); ?>" required>
                        <button type="button" class="password-toggle" data-target="register-confirm-password">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                    <div id="register-password-match-msg" style="font-size:13px;margin:-6px 0 8px 0;min-height:18px;text-align:left;"></div>
                    <button type="submit" name="register_complete" class="auth-button" disabled><?php echo my_htmlspecialchars(!empty($_SESSION['pending_set_password_only']) ? $translations['save'] : $translations['register']); ?></button>
                </form>
                <div style="text-align:center;margin-top:15px;">
                    <span><?php echo my_htmlspecialchars($translations['already_have_account']); ?></span>
                    <span class="auth-link" onclick="window.location.href='/login'"><?php echo my_htmlspecialchars($translations['login']); ?></span>
                </div>
            </div>
            <?php endif; ?>
        <?php elseif($path === '/register' && isset($showVerificationMessage) && $showVerificationMessage): ?>
            <div class="auth-tabs" style="display: none;"></div>
            <div id="verification-content" class="auth-content active">
                <h2><?php echo my_htmlspecialchars($translations['verify_account'] ?? 'Verify Account'); ?></h2>
                <div class="auth-success"><?php echo my_htmlspecialchars($translations['verification_link_sent']); ?></div>
                <p style="text-align:center;margin:20px 0;"><?php echo my_htmlspecialchars($translations['click_link_to_verify']); ?></p>
                <p style="text-align:center;margin:0 0 20px 0;font-size:14px;opacity:.75;line-height:1.5;"><?php echo my_htmlspecialchars($translations['verification_check_spam_hint'] ?? 'If you do not see the message in your inbox, check the Spam or Junk folder.'); ?></p>
                <p style="text-align:center;margin:20px 0;"><?php echo my_htmlspecialchars($verificationEmail); ?></p>
                <form method="post" action="/register" style="text-align:center;margin-bottom:16px;">
                    <input type="hidden" name="email" value="<?php echo my_htmlspecialchars($verificationEmail ?? ''); ?>">
                    <button type="submit" name="resend_verification" class="auth-button"><?php echo my_htmlspecialchars($translations['resend_verification_link']); ?></button>
                </form>
                <div style="text-align:center;margin-top:15px;">
                    <span class="auth-link" onclick="window.location.href='/login'"><?php echo my_htmlspecialchars($translations['login']); ?></span>
                </div>
            </div>
        <?php else: ?>
        <?php if(isset($showReVerifyForm)&&$showReVerifyForm): ?>
            <?php if(isset($authError)):?><div class="auth-error"><?php echo my_htmlspecialchars($authError); ?></div><?php endif;?>
            <div style="text-align:center;padding:10px 0;">
                <div style="font-size:48px;margin-bottom:12px;">&#x2709;&#xFE0F;</div>
                <h3 style="margin:0 0 8px 0;color:var(--text-color);"><?php echo my_htmlspecialchars($translations['email_not_verified_title']); ?></h3>
                <p style="font-size:14px;opacity:.7;margin:0 0 20px 0;line-height:1.5;"><?php echo my_htmlspecialchars($translations['email_not_verified_desc']); ?></p>
                <form method="post" action="/login">
                    <input type="hidden" name="email" value="<?php echo my_htmlspecialchars($reVerifyEmail??''); ?>">
                    <button type="submit" name="resend_verification" class="auth-button"><?php echo my_htmlspecialchars($translations['resend_verification_link']); ?></button>
                </form>
                <div style="margin-top:15px;"><span class="auth-link" onclick="location.href='/login'"><?php echo my_htmlspecialchars($translations['back_to_login']); ?></span></div>
            </div>
        <?php elseif(isset($showVerificationSent)&&$showVerificationSent): ?>
            <div style="text-align:center;padding:10px 0;">
                <div style="font-size:48px;margin-bottom:12px;">&#x1F4EC;</div>
                <div class="auth-success"><?php echo my_htmlspecialchars($authSuccess??''); ?></div>
                <p style="font-size:14px;opacity:.7;margin:16px 0;line-height:1.5;"><?php echo my_htmlspecialchars($translations['click_link_to_verify']); ?></p>
                <div style="margin-top:15px;"><span class="auth-link" onclick="location.href='/login'"><?php echo my_htmlspecialchars($translations['back_to_login']); ?></span></div>
            </div>
        <?php else: ?>
            <div class="auth-tabs">
                <button class="auth-tab <?php echo $path==='/login'?'active':''; ?>" data-tab="login"><?php echo my_htmlspecialchars($translations['login']); ?></button>
                <button class="auth-tab <?php echo $path==='/register'?'active':''; ?>" data-tab="register"><?php echo my_htmlspecialchars($translations['register']); ?></button>
            </div>
            <?php if(isset($authError)):?>
                <div class="auth-error"><?php echo my_htmlspecialchars($authError); ?></div>
            <?php endif;?>
            <?php if(isset($authSuccess)):?>
                <div class="auth-success"><?php echo my_htmlspecialchars($authSuccess); ?></div>
            <?php endif;?>
            <div id="login-content" class="auth-content <?php echo $path==='/login'?'active':''; ?>">
                <form method="post" action="/login">
                    <input type="email" name="email" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['email']); ?>" required>
                    <div class="password-input-container">
                        <input type="password" name="password" id="login-password" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['password']); ?>" required>
                        <button type="button" class="password-toggle" data-target="login-password">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                    <button type="submit" name="login" class="auth-button"><?php echo my_htmlspecialchars($translations['login']); ?></button>
                </form>
                <div style="text-align:center;margin:15px 0;">
                    <span class="auth-link" id="show-forgot"><?php echo my_htmlspecialchars($translations['forgot_password']); ?></span>
                </div>
                <div class="oauth-providers-bar">
                    <?php if(oauthProviderConfigured('github')): ?>
                    <a href="/login/oauth/start?provider=github" class="oauth-provider-btn oauth-github" title="GitHub" aria-label="GitHub">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-1.35-3.42-3.3-4.365-3.3-4.365-.825-.705 0-.705 0-.705 1.125 0 1.815 1.215 1.815 1.215 1.02 1.725 2.685 1.23 3.345.945.105-.75.435-1.23.795-1.515-2.79-.315-5.73-1.395-5.73-6.195 0-1.365.495-2.475 1.305-3.345-.135-.315-.57-1.575.135-3.285 0 0 1.065-.33 3.495 1.275a12.06 12.06 0 0 1 3.15-.42c1.065 0 2.145.15 3.15.42 2.43-1.605 3.495-1.275 3.495-1.275.705 1.71.27 2.97.135 3.285.81.87 1.305 1.98 1.305 3.345 0 4.815-2.955 5.865-5.745 6.195.45.39.855 1.155.855 2.325 0 1.68-.015 3.03-.015 3.435 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if(oauthProviderConfigured('google')): ?>
                    <a href="/login/oauth/start?provider=google" class="oauth-provider-btn oauth-google" title="Google" aria-label="Google">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
                <?php if(oauthProviderConfigured('github')): ?>
                <p style="text-align:center;font-size:12px;opacity:.75;margin:4px 16px 0;line-height:1.45;"><?php echo my_htmlspecialchars($translations['github_sso_hint'] ?? 'If your GitHub account uses Google sign-in, choose Continue with Google on the GitHub page (ignore the passkey error).'); ?></p>
                <?php endif; ?>
                <div style="text-align:center;margin-top:15px;">
                    <span><?php echo my_htmlspecialchars($translations['dont_have_account']); ?></span>
                    <span class="auth-link" data-tab="register"><?php echo my_htmlspecialchars($translations['register']); ?></span>
                </div>
            </div>
            <div id="register-content" class="auth-content <?php echo $path==='/register'?'active':''; ?>">
                <form method="post" action="/register">
                    <input type="email" name="email" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['email']); ?>" required>
                    <input type="text" name="name" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['name'] ?? 'Name'); ?>">
                    <button type="submit" name="register_initial" class="auth-button"><?php echo my_htmlspecialchars($translations['register']); ?></button>
                </form>
                <div class="oauth-providers-bar">
                    <?php if(oauthProviderConfigured('github')): ?>
                    <a href="/login/oauth/start?provider=github" class="oauth-provider-btn oauth-github" title="GitHub" aria-label="GitHub">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-1.35-3.42-3.3-4.365-3.3-4.365-.825-.705 0-.705 0-.705 1.125 0 1.815 1.215 1.815 1.215 1.02 1.725 2.685 1.23 3.345.945.105-.75.435-1.23.795-1.515-2.79-.315-5.73-1.395-5.73-6.195 0-1.365.495-2.475 1.305-3.345-.135-.315-.57-1.575.135-3.285 0 0 1.065-.33 3.495 1.275a12.06 12.06 0 0 1 3.15-.42c1.065 0 2.145.15 3.15.42 2.43-1.605 3.495-1.275 3.495-1.275.705 1.71.27 2.97.135 3.285.81.87 1.305 1.98 1.305 3.345 0 4.815-2.955 5.865-5.745 6.195.45.39.855 1.155.855 2.325 0 1.68-.015 3.03-.015 3.435 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if(oauthProviderConfigured('google')): ?>
                    <a href="/login/oauth/start?provider=google" class="oauth-provider-btn oauth-google" title="Google" aria-label="Google">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
                <?php if(oauthProviderConfigured('github')): ?>
                <p style="text-align:center;font-size:12px;opacity:.75;margin:4px 16px 0;line-height:1.45;"><?php echo my_htmlspecialchars($translations['github_sso_hint'] ?? 'If your GitHub account uses Google sign-in, choose Continue with Google on the GitHub page (ignore the passkey error).'); ?></p>
                <?php endif; ?>
                <div style="text-align:center;margin-top:15px;">
                    <span><?php echo my_htmlspecialchars($translations['already_have_account']); ?></span>
                    <span class="auth-link" data-tab="login"><?php echo my_htmlspecialchars($translations['login']); ?></span>
                </div>
            </div>
            <div id="forgot-content" class="auth-content">
                <?php if(!isset($showResetForm)||!$showResetForm):?>
                    <form method="post" action="/login">
                        <input type="email" name="email" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['email']); ?>" required>
                        <button type="submit" name="send_reset_code" class="auth-button"><?php echo my_htmlspecialchars($translations['send_reset_code']); ?></button>
                    </form>
                <?php else:?>
                    <form method="post" action="/login">
                        <input type="hidden" name="email" value="<?php echo my_htmlspecialchars($resetEmail??($_POST['email']??'')); ?>">
                        <input type="text" name="reset_code" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['reset_code']); ?>" required>
                        <div class="password-input-container">
                            <input type="password" name="new_password" id="reset-password" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['new_password']); ?>" required>
                            <button type="button" class="password-toggle" data-target="reset-password">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="password-input-container">
                            <input type="password" name="confirm_password" id="reset-confirm-password" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['confirm_password']); ?>" required>
                            <button type="button" class="password-toggle" data-target="reset-confirm-password">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                </svg>
                            </button>
                        </div>
                        <div id="reset-password-match-msg" class="password-verify-msg"></div>
                        <button type="submit" name="reset_password" class="auth-button" id="reset-password-submit"><?php echo my_htmlspecialchars($translations['reset_password']); ?></button>
                    </form>
                <?php endif;?>
                <div style="text-align:center;margin-top:15px;">
                    <span class="auth-link" data-tab="login"><?php echo my_htmlspecialchars($translations['login']); ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
        <div class="policy-links">
            <a href="javascript:void(0)" onclick="openPolicyModal('/privacy?hideBack=1&theme=<?php echo urlencode($config['theme']); ?>&lang=<?php echo urlencode($language); ?>&color=<?php echo urlencode($config['color']); ?>')" style="color:var(--primary-color);text-decoration:none;"><?php echo my_htmlspecialchars($translations['privacy_policy']??'Privacy Policy'); ?></a>
            <span class="policy-dot"></span>
            <a href="javascript:void(0)" onclick="openPolicyModal('/terms?hideBack=1&theme=<?php echo urlencode($config['theme']); ?>&lang=<?php echo urlencode($language); ?>&color=<?php echo urlencode($config['color']); ?>')" style="color:var(--primary-color);text-decoration:none;"><?php echo my_htmlspecialchars($translations['terms_of_service']??'Terms of Service'); ?></a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php if(isset($_SESSION['user'])):?>
    <?php $profileModalInitialOpen=isset($_GET['profile'])||isset($_POST['reset_photo'])||isset($_POST['oauth_unlink'])||isset($_GET['oauth_linked'])||isset($_GET['oauth_error']); ?>
    <div id="profile-modal"<?php echo $profileModalInitialOpen?' style="display:flex;"':''; ?>>
        <div id="profile-content">
            <button id="close-profile" type="button">&times;</button>
        <div class="modal-scroll-body">
            <h2><?php echo my_htmlspecialchars($translations['user_profile']); ?></h2>
            <?php if(isset($profileError)):?><div class="auth-error"><?php echo my_htmlspecialchars($profileError); ?></div><?php endif;?>
            <?php if(isset($profileSuccess)):?><div class="auth-success"><?php echo my_htmlspecialchars($profileSuccess); ?></div><?php endif;?>
            <div class="profile-section">
                <img src="<?php echo my_htmlspecialchars(app_avatar_src($_SESSION['user']['photo']??'default_avatar.png')); ?>" alt="User Avatar" class="profile-avatar">
                <h3><?php echo my_htmlspecialchars($_SESSION['user']['name']); ?></h3>
                <p class="email-row"><?php echo my_htmlspecialchars($_SESSION['user']['email']); ?><button type="button" class="copy-email-btn" data-email="<?php echo my_htmlspecialchars($_SESSION['user']['email']); ?>" title="Copy email"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button></p>
                <p><strong><?php echo my_htmlspecialchars($translations['user_role']); ?>:</strong>
                    <?php $role=$_SESSION['user']['role']??0;
                    if($role==2)echo my_htmlspecialchars($translations['admin']);
                    elseif($role==1)echo my_htmlspecialchars($translations['manager']);
                    else echo my_htmlspecialchars($translations['guest']);?>
                </p>
            </div>
            <div class="profile-section">
                <?php $_showGooglePhotoReset=isset($userOAuthConnections['google']) && (string)($userOAuthConnections['google']['provider_photo']??'')!=='' && str_starts_with((string)($_SESSION['user']['photo']??''),'uploads/avatars/'); ?>
                <h3><?php echo my_htmlspecialchars($translations['change_photo']); ?></h3>
                <button type="button" class="change-photo-button" id="show-photo-form">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-repeat-icon lucide-repeat"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>
                    <?php echo my_htmlspecialchars($translations['change_photo_button'] ?? 'Change Photo'); ?>
                </button>
                <form method="post" enctype="multipart/form-data" class="photo-form" id="photo-form" action="/main">
                    <input type="file" name="photo" accept="image/*" class="auth-input">
                    <button type="submit" name="change_photo" class="profile-action-button"><?php echo my_htmlspecialchars($translations['upload_photo']); ?></button>
                </form>
                <?php if($_showGooglePhotoReset): ?>
                <form method="post" action="/main" class="photo-form" id="reset-photo-form" style="margin-top:8px;">
                    <button type="submit" name="reset_photo" class="profile-action-button"><?php echo my_htmlspecialchars($translations['reset_to_default']); ?></button>
                </form>
                <?php endif; ?>
            </div>
            <div class="profile-section" id="password-section">
                <h3><?php echo my_htmlspecialchars($translations['change_password']); ?></h3>
                <button type="button" class="change-password-button" id="show-password-form">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-repeat-icon lucide-repeat"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>
                    <?php echo my_htmlspecialchars($translations['change_password']); ?>
                </button>
                <div class="password-change-box" id="password-change-box">
                    <form method="post" class="password-form" id="password-form" action="/main">
                        <div id="password-verify-step">
                            <div class="password-input-container">
                                <input type="password" name="current_password" id="current-password" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['current_password']); ?>" autocomplete="current-password" required>
                                <button type="button" class="password-toggle" data-target="current-password">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                </button>
                            </div>
                            <button type="button" class="auth-button" id="verify-current-password"><?php echo my_htmlspecialchars($translations['verify_password'] ?? 'Verify'); ?></button>
                            <div id="current-password-msg" class="password-verify-msg"></div>
                        </div>
                        <div id="password-new-step" class="password-new-fields" style="display:none">
                            <div class="password-input-row">
                                <div class="password-input-container">
                                    <input type="password" name="new_password" id="change-new-password" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['new_password']); ?>" autocomplete="new-password" disabled>
                                    <button type="button" class="password-toggle" data-target="change-new-password">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                        </svg>
                                    </button>
                                </div>
                                <button type="button" class="password-generate-btn" id="generate-change-password" title="<?php echo my_htmlspecialchars($translations['generate_password'] ?? 'Generate password'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['generate_password'] ?? 'Generate password'); ?>" data-pwd-target="change-new-password" data-confirm-target="change-confirm-password" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/><path d="M17.8 11.8 19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2 19 5"/><path d="m3 21 9-9"/><path d="M12.2 6.2 11 5"/></svg>
                                </button>
                            </div>
                            <div class="password-input-container">
                                <input type="password" name="confirm_password" id="change-confirm-password" class="auth-input" placeholder="<?php echo my_htmlspecialchars($translations['confirm_password']); ?>" autocomplete="new-password" disabled>
                                <button type="button" class="password-toggle" data-target="change-confirm-password">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                </button>
                            </div>
                            <div id="change-password-match-msg" class="password-verify-msg"></div>
                            <button type="submit" name="change_password" class="auth-button" id="change-password-submit" disabled><?php echo my_htmlspecialchars($translations['change_password']); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="profile-section">
                <h3><?php echo my_htmlspecialchars($translations['connected_accounts'] ?? 'Connected accounts'); ?></h3>
                <div class="oauth-accounts-list">
                    <div class="oauth-account-item">
                        <div class="oauth-account-icon oauth-email">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="m4 7 8 6 8-6"/></svg>
                        </div>
                        <div class="oauth-account-meta">
                            <div class="oauth-account-title"><?php echo my_htmlspecialchars($translations['email'] ?? 'Email'); ?></div>
                            <div class="oauth-account-sub"><?php echo my_htmlspecialchars($_SESSION['user']['email']); ?></div>
                        </div>
                        <span class="oauth-status-icon connected" title="<?php echo my_htmlspecialchars($translations['connected'] ?? 'Connected'); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                    </div>
                    <?php foreach(['github' => 'GitHub', 'google' => 'Google'] as $_oauthProvider => $_oauthLabel): ?>
                    <?php $_oauthLinked = isset($userOAuthConnections[$_oauthProvider]); ?>
                    <div class="oauth-account-item">
                        <div class="oauth-account-icon oauth-<?php echo my_htmlspecialchars($_oauthProvider); ?>">
                            <?php if($_oauthProvider === 'github'): ?>
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-1.35-3.42-3.3-4.365-3.3-4.365-.825-.705 0-.705 0-.705 1.125 0 1.815 1.215 1.815 1.215 1.02 1.725 2.685 1.23 3.345.945.105-.75.435-1.23.795-1.515-2.79-.315-5.73-1.395-5.73-6.195 0-1.365.495-2.475 1.305-3.345-.135-.315-.57-1.575.135-3.285 0 0 1.065-.33 3.495 1.275a12.06 12.06 0 0 1 3.15-.42c1.065 0 2.145.15 3.15.42 2.43-1.605 3.495-1.275 3.495-1.275.705 1.71.27 2.97.135 3.285.81.87 1.305 1.98 1.305 3.345 0 4.815-2.955 5.865-5.745 6.195.45.39.855 1.155.855 2.325 0 1.68-.015 3.03-.015 3.435 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
                            <?php else: ?>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="oauth-account-meta">
                            <div class="oauth-account-title"><?php echo my_htmlspecialchars($_oauthLabel); ?></div>
                            <div class="oauth-account-sub"><?php echo my_htmlspecialchars($_oauthLinked ? ($userOAuthConnections[$_oauthProvider]['provider_email'] ?: $userOAuthConnections[$_oauthProvider]['provider_name'] ?: $_oauthLabel) : ($translations['oauth_not_connected'] ?? 'Not connected')); ?></div>
                        </div>
                        <div class="oauth-account-actions">
                            <?php if($_oauthLinked): ?>
                            <span class="oauth-status-icon connected" title="<?php echo my_htmlspecialchars($translations['connected'] ?? 'Connected'); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <form method="post" action="/main" style="margin:0;display:inline-flex;">
                                <input type="hidden" name="provider" value="<?php echo my_htmlspecialchars($_oauthProvider); ?>">
                                <button type="submit" name="oauth_unlink" class="oauth-action-btn unlink" title="<?php echo my_htmlspecialchars($translations['unlink'] ?? 'Unlink'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['unlink'] ?? 'Unlink'); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="oauth-status-icon disconnected" title="<?php echo my_htmlspecialchars($translations['oauth_not_connected'] ?? 'Not connected'); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </span>
                            <?php if(oauthProviderConfigured($_oauthProvider)): ?>
                            <a href="/login/oauth/start?provider=<?php echo urlencode($_oauthProvider); ?>&mode=link" class="oauth-action-btn connect" title="<?php echo my_htmlspecialchars($translations['connect'] ?? 'Connect'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['connect'] ?? 'Connect'); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="profile-section">
                <form method="post" action="/main">
                    <button type="submit" name="logout" class="logout-button">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                        <?php echo my_htmlspecialchars($translations['logout']); ?>
                    </button>
                </form>
            </div>
        </div><!-- /.modal-scroll-body -->
        </div>
    </div>
<?php endif;?>
<?php if($isRootUser):?>
    <div id="email-modal">
        <form id="email-form" method="post" action="/main">
            <button id="close-email" type="button">&times;</button>
        <div class="modal-scroll-body">
            <h2><?php echo my_htmlspecialchars($translations['email_settings']); ?></h2>
            <?php if(isset($emailSettingsSuccess)):?><div class="auth-success"><?php echo my_htmlspecialchars($emailSettingsSuccess); ?></div><?php endif;?>
            <?php if(isset($emailSettingsError)):?><div class="auth-error"><?php echo my_htmlspecialchars($emailSettingsError); ?></div><?php endif;?>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['google_client_id']); ?>:</label><input type="text" name="google_client_id" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['google_client_id']); ?>" required></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['google_client_secret']); ?>:</label><input type="text" name="google_client_secret" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['google_client_secret']); ?>" required></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['github_client_id'] ?? 'GitHub Client ID'); ?>:</label><input type="text" name="github_client_id" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['github_client_id'] ?? ''); ?>"></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['github_client_secret'] ?? 'GitHub Client Secret'); ?>:</label><input type="text" name="github_client_secret" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['github_client_secret'] ?? ''); ?>"></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['oauth_callback_hint'] ?? 'OAuth callback URL'); ?>:</label><input type="text" class="auth-input" value="<?php echo my_htmlspecialchars(oauthLoginRedirectUri()); ?>" readonly></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['smtp_host']); ?>:</label><input type="text" name="smtp_host" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['smtp_host']); ?>" required></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['smtp_port']); ?>:</label><input type="number" name="smtp_port" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['smtp_port']); ?>" required></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['smtp_username']); ?>:</label><input type="text" name="smtp_username" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['smtp_username']); ?>" required></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['smtp_password']); ?>:</label><input type="password" name="smtp_password" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['smtp_password']); ?>" required></div>
            <input type="submit" name="save_email_settings" value="<?php echo my_htmlspecialchars($translations['save']); ?>">
        </div>
</form>
    </div>
<?php endif;?>
<?php if($isAdmin):?>
    <!-- Telegram Bot Modal (admin only) -->
    <?php $tgCfg = loadTgBotConfig(); ?>
    <div id="tg-bot-modal">
        <div id="tg-bot-content">
            <button id="close-tg-bot" type="button">&times;</button>
            <h2 style="margin-bottom:4px;"><?php echo my_htmlspecialchars($translations['bot_settings'] ?? 'Bot Settings'); ?></h2>
            <p style="font-size:12px;color:#888;margin-bottom:20px;"><?php echo my_htmlspecialchars($translations['tg_bot_desc'] ?? 'Configure your Telegram bot to receive notifications about new registrations.'); ?></p>

            <div class="tg-bot-field">
                <label><?php echo my_htmlspecialchars($translations['tg_bot_token'] ?? 'Bot Token'); ?></label>
                <input type="text" id="tg-bot-token" value="<?php echo my_htmlspecialchars($tgCfg['bot_token'] ?? ''); ?>" placeholder="1234567890:ABCDef...">
                <div class="tg-bot-hint"><?php echo my_htmlspecialchars($translations['tg_bot_token_hint'] ?? 'Get your token from @BotFather on Telegram.'); ?></div>
            </div>
            <div class="tg-bot-field">
                <label><?php echo my_htmlspecialchars($translations['tg_public_base_url'] ?? 'Public site URL'); ?></label>
                <input type="text" id="tg-bot-webapp-url" value="<?php echo my_htmlspecialchars($tgCfg['webapp_base_url'] ?? ''); ?>" placeholder="https://serverkherson.xyz" autocomplete="off">
                <div class="tg-bot-hint"><?php echo my_htmlspecialchars($translations['tg_public_base_url_hint'] ?? ''); ?></div>
            </div>

            <?php
            $authorizedChats = loadAuthorizedTelegramSessions();
            if(!empty($authorizedChats)):
            ?>
            <div class="tg-bot-field">
                <label><?php echo my_htmlspecialchars($translations['tg_recipients'] ?? 'Recipients'); ?></label>
                <div style="background:var(--group-block-bg);border-radius:6px;padding:8px 12px;font-size:13px;font-family:monospace;line-height:1.8;">
                    <?php foreach($authorizedChats as $s): ?>
                        <div><?php echo my_htmlspecialchars(($s['email'] ?? '') . ' · ' . ($s['chat_id'] ?? '')); ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="tg-bot-hint"><?php echo my_htmlspecialchars($translations['tg_recipients_hint'] ?? ''); ?></div>
            </div>
            <?php endif; ?>
            <div class="tg-bot-field">
                <label><?php echo my_htmlspecialchars($translations['tg_link_account'] ?? 'Bind account'); ?></label>
                <select id="tg-link-email" class="auth-input" style="margin-bottom:0;">
                    <?php foreach(loadUsers() as $em=>$u): if((int)($u['role'] ?? 0) < 2) continue; ?>
                        <option value="<?php echo my_htmlspecialchars($em); ?>"<?php echo $em===($_SESSION['user']['email']??'')?' selected':''; ?>><?php echo my_htmlspecialchars($em); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="tg-bot-hint"><?php echo my_htmlspecialchars($translations['tg_link_hint'] ?? 'Generate a one-time code and send it to the Telegram bot.'); ?></div>
            </div>
            <div class="tg-bot-field">
                <label><?php echo my_htmlspecialchars($translations['tg_link_token'] ?? 'One-time code'); ?></label>
                <input type="text" id="tg-link-token" value="" readonly placeholder="--------">
            </div>

            <div class="tg-bot-actions">
                <button type="button" class="tg-save-btn" id="save-tg-bot"><?php echo my_htmlspecialchars($translations['save'] ?? 'Save'); ?></button>
                <button type="button" class="tg-test-btn" id="generate-tg-link-token"><?php echo my_htmlspecialchars($translations['tg_link_generate'] ?? 'Generate code'); ?></button>
                <button type="button" class="tg-test-btn" id="test-tg-bot"><?php echo my_htmlspecialchars($translations['tg_test'] ?? 'Test'); ?></button>
            </div>
            <div class="tg-status" id="tg-status"></div>
        </div>
    </div>

    <!-- Delete User Modal (admin only) -->
    <div id="delete-user-modal">
        <div id="delete-user-content">
            <button id="close-delete-user" type="button">&times;</button>
            <h2 id="delete-user-title"><?php echo my_htmlspecialchars($translations['delete_user'] ?? 'Delete User'); ?></h2>
            <p id="delete-user-email-display" style="color:#888;font-size:13px;margin-bottom:12px;"></p>

            <div id="delete-user-files-section">
                <div class="scanning-msg" id="delete-user-scanning"><?php echo my_htmlspecialchars($translations['scanning_files'] ?? 'Scanning user files...'); ?></div>

                <!-- Progress bar for scanning -->
                <div id="delete-user-scan-progress" style="display:none;margin-top:16px;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                        <div style="flex:1;">
                            <div style="background:var(--border-color);border-radius:8px;height:8px;overflow:hidden;position:relative;">
                                <div id="scan-progress-bar" style="background:var(--primary-color);height:100%;width:0%;transition:width 0.3s ease;border-radius:8px;"></div>
                            </div>
                        </div>
                        <span id="scan-progress-percent" style="font-size:13px;font-weight:600;color:var(--primary-color);min-width:45px;text-align:right;">0%</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:#888;">
                        <span id="scan-progress-files">Scanning... 0 files</span>
                        <span id="scan-time-remaining">Calculating time...</span>
                    </div>
                    <div style="font-size:11px;color:#666;margin-top:4px;text-align:center;">
                        <span id="scan-speed"></span>
                    </div>
                </div>

                <div id="delete-user-files-loaded" style="display:none;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                        <strong><?php echo my_htmlspecialchars($translations['user_files'] ?? 'User files'); ?>:</strong>
                        <span id="delete-user-file-count" style="color:#888;font-size:13px;"></span>
                        <span class="total-size-badge" id="delete-user-total-size"></span>
                    </div>
                    <div class="delete-user-files-list" id="delete-user-files-list"></div>
<label class="delete-files-toggle check-label">
                         <input type="checkbox" id="delete-files-checkbox" class="checkbox">
                         <span class="checkbox-box"><svg width="24" height="18" viewBox="0 0 24 18" fill="none"><path d="M2 9L8.5 15.5L22 2" stroke="#111113" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                         <span><?php echo my_htmlspecialchars($translations['delete_user_files_also'] ?? 'Also delete all user files listed above'); ?></span>
                     </label>
                    <!-- Slider section (shown only when checkbox is checked) -->
                    <div id="delete-slider-section" style="display:none;">
                        <div class="confirm-slider-wrapper">
                            <div class="confirm-slider-label"><?php echo my_htmlspecialchars($translations['drag_to_confirm'] ?? 'Drag slider to the end to confirm deletion'); ?></div>
                            <div class="confirm-slider-track">
                                <div class="confirm-slider-fill" id="slider-fill"></div>
                                <input type="range" class="confirm-slider-input" id="confirm-slider" min="0" max="100" value="0">
                                <div class="confirm-slider-thumb-label" id="slider-label">➜</div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="do-delete-btn" id="do-delete-user-btn" disabled><?php echo my_htmlspecialchars($translations['delete_user'] ?? 'Delete User'); ?></button>
                </div>
            </div>
            <input type="hidden" id="delete-user-target-email" value="">
        </div>
    </div>

    <div id="reset-user-password-modal">
        <div id="reset-user-password-content">
            <button id="close-reset-user-password" type="button">&times;</button>
            <h2 id="reset-user-password-title"><?php echo my_htmlspecialchars($translations['reset_user_password'] ?? 'Reset password'); ?></h2>
            <p id="reset-user-password-email-display" style="color:#888;font-size:13px;margin-bottom:12px;"></p>
            <p style="font-size:13px;opacity:.8;margin:0 0 8px 0;"><?php echo my_htmlspecialchars($translations['reset_user_password_hint'] ?? 'A strong password will be set for this user. Share it securely after reset.'); ?></p>
            <div id="reset-password-form-section">
                <label style="font-size:12px;opacity:.7;display:block;margin-bottom:6px;"><?php echo my_htmlspecialchars($translations['new_password'] ?? 'New password'); ?></label>
                <div class="reset-password-field-row">
                    <input type="text" id="reset-user-password-value" readonly autocomplete="off">
                    <button type="button" class="reset-password-regen-btn" id="regen-reset-user-password" title="<?php echo my_htmlspecialchars($translations['generate_password'] ?? 'Generate password'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['generate_password'] ?? 'Generate password'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/><path d="M17.8 11.8 19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2 19 5"/><path d="m3 21 9-9"/><path d="M12.2 6.2 11 5"/></svg>
                    </button>
                </div>
                <div class="confirm-slider-wrapper">
                    <div class="confirm-slider-label"><?php echo my_htmlspecialchars($translations['drag_to_confirm_reset_password'] ?? 'Drag slider to the end to confirm password reset'); ?></div>
                    <div class="confirm-slider-track">
                        <div class="confirm-slider-fill confirm-slider-fill-primary" id="reset-pwd-slider-fill"></div>
                        <input type="range" class="confirm-slider-input" id="reset-pwd-confirm-slider" min="0" max="100" value="0">
                        <div class="confirm-slider-thumb-label" id="reset-pwd-slider-label">➜</div>
                    </div>
                </div>
                <button type="button" class="do-reset-pwd-btn" id="do-reset-user-password-btn" disabled><?php echo my_htmlspecialchars($translations['reset_user_password'] ?? 'Reset password'); ?></button>
            </div>
            <div id="reset-password-success">
                <div style="color:#3fb950;font-weight:600;font-size:14px;" id="reset-password-success-msg"></div>
                <div class="reset-password-copy-row">
                    <input type="text" id="reset-password-success-value" readonly>
                    <button type="button" class="reset-password-copy-btn" id="copy-reset-user-password"><?php echo my_htmlspecialchars($translations['copy_password'] ?? 'Copy password'); ?></button>
                </div>
            </div>
            <input type="hidden" id="reset-user-password-target-email" value="">
        </div>
    </div>
<?php endif;?>
<?php if($isRootUser):?>
    <div id="users-modal">
        <div id="users-content">
            <button id="close-users" type="button">&times;</button>
        <div class="modal-scroll-body">
            <h2><?php echo my_htmlspecialchars($translations['user_management']); ?></h2>
            <?php if(isset($userManagementSuccess)):?><div class="auth-success"><?php echo my_htmlspecialchars($userManagementSuccess); ?></div><?php endif;?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
                <div class="users-search-container" style="flex:1;min-width:160px;margin-bottom:0;">
                    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
                    <input type="text" id="users-search" placeholder="<?php echo my_htmlspecialchars($translations['search_users']); ?>">
                    <button id="users-clear-search" type="button">&times;</button>
                </div>
                <button type="button" id="refresh-users-btn" class="sidebar-button" style="background:var(--primary-color);color:white;border:none;padding:8px 14px;border-radius:4px;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap;" title="<?php echo my_htmlspecialchars($translations['refresh_users'] ?? 'Refresh users list'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                    <?php echo my_htmlspecialchars($translations['refresh_users'] ?? 'Refresh'); ?>
                </button>
            </div>
            <h3><?php echo my_htmlspecialchars($translations['users_list']); ?></h3>
            <div id="users-table-container">
            <?php
            // Merge confirmed users and pending (unverified) users for display
            $pendingForDisplay = loadPendingUsers();
            $allDisplayUsers = $usersList;
            foreach($pendingForDisplay as $pendingEmail => $pendingData){
                if(!isset($allDisplayUsers[$pendingEmail])){
                    $pendingData['_pending'] = true;
                    $allDisplayUsers[$pendingEmail] = $pendingData;
                }
            }
            ?>
            <form method="post" id="users-roles-form" action="/main">
                <table class="users-table">
                    <thead><tr><th><?php echo my_htmlspecialchars($translations['name']??'Name'); ?></th><th><?php echo my_htmlspecialchars($translations['email']??'Email'); ?></th><th><?php echo my_htmlspecialchars($translations['user_role']??'Role'); ?></th><th><?php echo my_htmlspecialchars($translations['language']??'Language'); ?></th><th><?php echo my_htmlspecialchars($translations['theme']??'Theme'); ?></th><th><?php echo my_htmlspecialchars($translations['ap_col_status']??'Status'); ?></th><th><?php echo my_htmlspecialchars($translations['ap_col_date']??'Created'); ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach($allDisplayUsers as $email=>$userData):?>
                        <tr data-email="<?php echo my_htmlspecialchars($email); ?>" data-name="<?php echo my_htmlspecialchars($userData['name']); ?>">
                            <td><?php echo my_htmlspecialchars($userData['name']); ?></td>
                            <td><?php echo my_htmlspecialchars($email); ?></td>
                            <td><?php if(!empty($userData['_pending'])):?><span style="color:#aaa;font-size:12px;"><?php echo my_htmlspecialchars($translations['guest']); ?></span><?php else:?><select name="user_roles[<?php echo my_htmlspecialchars($email); ?>]" class="role-select"><option value="0" <?php echo ($userData['role']??0)==0?'selected':''; ?>><?php echo my_htmlspecialchars($translations['guest']); ?></option><option value="1" <?php echo ($userData['role']??0)==1?'selected':''; ?>><?php echo my_htmlspecialchars($translations['manager']); ?></option><option value="2" <?php echo ($userData['role']??0)==2?'selected':''; ?>><?php echo my_htmlspecialchars($translations['admin']); ?></option></select><?php endif;?></td>
                            <td><?php echo my_htmlspecialchars($userData['language']??'en'); ?></td>
                            <td><?php echo my_htmlspecialchars($userData['theme']??'light'); ?></td>
                            <td><?php if(!empty($userData['_pending'])):?><span style="color:#ff9800;font-weight:bold;">⏳ <?php echo my_htmlspecialchars($translations['pending_verification'] ?? 'Pending verification'); ?></span><?php elseif(empty($userData['verified'])):?><span style="color:#ff4444;"><?php echo my_htmlspecialchars($translations['email_not_verified']); ?></span><?php else:?><span style="color:#00C851;">✓ <?php echo my_htmlspecialchars($translations['verified'] ?? 'Verified'); ?></span><?php endif;?></td>
                            <td><?php echo my_htmlspecialchars($userData['created_at']??'Unknown'); ?></td>
                            <td><?php if($email!==($_SESSION['user']['email']??'')):?><button type="button" class="delete-user-btn open-delete-user-modal" data-user-email="<?php echo my_htmlspecialchars($email); ?>" data-user-name="<?php echo my_htmlspecialchars($userData['name']); ?>" data-is-pending="<?php echo !empty($userData['_pending'])?'1':'0'; ?>"><?php echo my_htmlspecialchars($translations['delete_user']); ?></button><?php else:?><span style="color:var(--primary-color);font-weight:bold;">Current User</span><?php endif;?></td>
                        </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
                <div style="margin-top:20px;text-align:center;"><input type="submit" name="save_user_roles" value="<?php echo my_htmlspecialchars($translations['save_roles']); ?>" class="auth-button"></div>
            </form>
            </div><!-- #users-table-container -->
        </div><!-- /.modal-scroll-body -->
        </div>
    </div>
<?php endif;?>
<?php if(isset($_SESSION['user'])):?>
    <div id="sessions-modal">
        <div id="sessions-content">
            <div class="modal-header"><div class="modal-header-left"><h2><?php echo my_htmlspecialchars($translations['session_management']); ?></h2></div><button id="close-sessions" type="button">&times;</button></div>
        <div class="modal-scroll-body">
            <h2><?php echo my_htmlspecialchars($translations['session_management']); ?></h2>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;"><h3 style="margin:0;"><?php echo my_htmlspecialchars($translations['active_sessions']); ?></h3><button id="refresh-sessions" class="sidebar-button" style="background:var(--primary-color);color:white;border:none;padding:8px;border-radius:4px;cursor:pointer;" title="<?php echo my_htmlspecialchars($translations['refresh']); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg></button></div>
            <?php if(isset($sessionManagementSuccess)):?><div class="auth-success"><?php echo my_htmlspecialchars($sessionManagementSuccess); ?></div><?php endif;?>
            <div id="sessions-list-container">
                <?php if(empty($activeSessions)):?><p><?php echo my_htmlspecialchars($translations['no_active_sessions']); ?></p>
                <?php else:?>
                    <table class="sessions-table">
                        <thead><tr><th><?php echo my_htmlspecialchars($translations['session_id']); ?></th><th><?php echo my_htmlspecialchars($translations['device']); ?></th><th><?php echo my_htmlspecialchars($translations['browser']); ?></th><th><?php echo my_htmlspecialchars($translations['login_time']); ?></th><th><?php echo my_htmlspecialchars($translations['last_activity']); ?></th><th><?php echo my_htmlspecialchars($translations['ip_address']); ?></th><th><?php echo my_htmlspecialchars($translations['terminate_session']); ?></th></tr></thead>
                        <tbody>
                        <?php foreach($activeSessions as $sessionId=>$session):?>
                            <tr class="<?php echo $sessionId===session_id()?'current-session':''; ?>">
                                <td><?php echo my_htmlspecialchars(substr($sessionId,0,10).'...'); ?><?php if($sessionId===session_id()):?><span style="color:var(--primary-color);font-weight:bold;">(<?php echo my_htmlspecialchars($translations['current_session']); ?>)</span><?php endif;?></td>
                                <td><div class="device-browser-item"><?php $device=$session['device']??'unknown_device';$deviceClass=$device.'-icon';$deviceText=$translations[$device]??$translations['unknown_device'];?><div class="device-icon <?php echo $deviceClass; ?>"><?php if($device==='windows'):?><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M0 3.449L9.75 2.1v9.451H0m10.949-9.602L24 0v11.4H10.949M0 12.6h9.75v9.451L0 20.699M10.949 12.6H24V24l-13.051-1.801"/></svg><?php elseif($device==='linux'):?><svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" id="Linux--Streamline-Simple-Icons" height="24" width="24">
  <desc>
    Linux Streamline Icon: https://streamlinehq.com
  </desc>
  <title>Linux</title>
  <path d="M12.504 0c-0.155 0 -0.315 0.008 -0.48 0.021 -4.226 0.333 -3.105 4.807 -3.17 6.298 -0.076 1.092 -0.3 1.953 -1.05 3.02 -0.885 1.051 -2.127 2.75 -2.716 4.521 -0.278 0.832 -0.41 1.684 -0.287 2.489a0.424 0.424 0 0 0 -0.11 0.135c-0.26 0.268 -0.45 0.6 -0.663 0.839 -0.199 0.199 -0.485 0.267 -0.797 0.4 -0.313 0.136 -0.658 0.269 -0.864 0.68 -0.09 0.189 -0.136 0.394 -0.132 0.602 0 0.199 0.027 0.4 0.055 0.536 0.058 0.399 0.116 0.728 0.04 0.97 -0.249 0.68 -0.28 1.145 -0.106 1.484 0.174 0.334 0.535 0.47 0.94 0.601 0.81 0.2 1.91 0.135 2.774 0.6 0.926 0.466 1.866 0.67 2.616 0.47 0.526 -0.116 0.97 -0.464 1.208 -0.946 0.587 -0.003 1.23 -0.269 2.26 -0.334 0.699 -0.058 1.574 0.267 2.577 0.2 0.025 0.134 0.063 0.198 0.114 0.333l0.003 0.003c0.391 0.778 1.113 1.132 1.884 1.071 0.771 -0.06 1.592 -0.536 2.257 -1.306 0.631 -0.765 1.683 -1.084 2.378 -1.503 0.348 -0.199 0.629 -0.469 0.649 -0.853 0.023 -0.4 -0.2 -0.811 -0.714 -1.376v-0.097l-0.003 -0.003c-0.17 -0.2 -0.25 -0.535 -0.338 -0.926 -0.085 -0.401 -0.182 -0.786 -0.492 -1.046h-0.003c-0.059 -0.054 -0.123 -0.067 -0.188 -0.135a0.357 0.357 0 0 0 -0.19 -0.064c0.431 -1.278 0.264 -2.55 -0.173 -3.694 -0.533 -1.41 -1.465 -2.638 -2.175 -3.483 -0.796 -1.005 -1.576 -1.957 -1.56 -3.368 0.026 -2.152 0.236 -6.133 -3.544 -6.139zm0.529 3.405h0.013c0.213 0 0.396 0.062 0.584 0.198 0.19 0.135 0.33 0.332 0.438 0.533 0.105 0.259 0.158 0.459 0.166 0.724 0 -0.02 0.006 -0.04 0.006 -0.06v0.105a0.086 0.086 0 0 1 -0.004 -0.021l-0.004 -0.024a1.807 1.807 0 0 1 -0.15 0.706 0.953 0.953 0 0 1 -0.213 0.335 0.71 0.71 0 0 0 -0.088 -0.042c-0.104 -0.045 -0.198 -0.064 -0.284 -0.133a1.312 1.312 0 0 0 -0.22 -0.066c0.05 -0.06 0.146 -0.133 0.183 -0.198 0.053 -0.128 0.082 -0.264 0.088 -0.402v-0.02a1.21 1.21 0 0 0 -0.061 -0.4c-0.045 -0.134 -0.101 -0.2 -0.183 -0.333 -0.084 -0.066 -0.167 -0.132 -0.267 -0.132h-0.016c-0.093 0 -0.176 0.03 -0.262 0.132a0.8 0.8 0 0 0 -0.205 0.334 1.18 1.18 0 0 0 -0.09 0.4v0.019c0.002 0.089 0.008 0.179 0.02 0.267 -0.193 -0.067 -0.438 -0.135 -0.607 -0.202a1.635 1.635 0 0 1 -0.018 -0.2v-0.02a1.772 1.772 0 0 1 0.15 -0.768c0.082 -0.22 0.232 -0.406 0.43 -0.533a0.985 0.985 0 0 1 0.594 -0.2zm-2.962 0.059h0.036c0.142 0 0.27 0.048 0.399 0.135 0.146 0.129 0.264 0.288 0.344 0.465 0.09 0.199 0.14 0.4 0.153 0.667v0.004c0.007 0.134 0.006 0.2 -0.002 0.266v0.08c-0.03 0.007 -0.056 0.018 -0.083 0.024 -0.152 0.055 -0.274 0.135 -0.393 0.2 0.012 -0.09 0.013 -0.18 0.003 -0.267v-0.015c-0.012 -0.133 -0.04 -0.2 -0.082 -0.333a0.613 0.613 0 0 0 -0.166 -0.267 0.248 0.248 0 0 0 -0.183 -0.064h-0.021c-0.071 0.006 -0.13 0.04 -0.186 0.132a0.552 0.552 0 0 0 -0.12 0.27 0.944 0.944 0 0 0 -0.023 0.33v0.015c0.012 0.135 0.037 0.2 0.08 0.334 0.046 0.134 0.098 0.2 0.166 0.268 0.01 0.009 0.02 0.018 0.034 0.024 -0.07 0.057 -0.117 0.07 -0.176 0.136a0.304 0.304 0 0 1 -0.131 0.068 2.62 2.62 0 0 1 -0.275 -0.402 1.772 1.772 0 0 1 -0.155 -0.667 1.759 1.759 0 0 1 0.08 -0.668 1.43 1.43 0 0 1 0.283 -0.535c0.128 -0.133 0.26 -0.2 0.418 -0.2zm1.37 1.706c0.332 0 0.733 0.065 1.216 0.399 0.293 0.2 0.523 0.269 1.052 0.468h0.003c0.255 0.136 0.405 0.266 0.478 0.399v-0.131a0.571 0.571 0 0 1 0.016 0.47c-0.123 0.31 -0.516 0.643 -1.063 0.842v0.002c-0.268 0.135 -0.501 0.333 -0.775 0.465 -0.276 0.135 -0.588 0.292 -1.012 0.267a1.139 1.139 0 0 1 -0.448 -0.067 3.566 3.566 0 0 1 -0.322 -0.198c-0.195 -0.135 -0.363 -0.332 -0.612 -0.465v-0.005h-0.005c-0.4 -0.246 -0.616 -0.512 -0.686 -0.71 -0.07 -0.268 -0.005 -0.47 0.193 -0.6 0.224 -0.135 0.38 -0.271 0.483 -0.336 0.104 -0.074 0.143 -0.102 0.176 -0.131h0.002v-0.003c0.169 -0.202 0.436 -0.47 0.839 -0.601 0.139 -0.036 0.294 -0.065 0.466 -0.065zm2.8 2.142c0.358 1.417 1.196 3.475 1.735 4.473 0.286 0.534 0.855 1.659 1.102 3.024 0.156 -0.005 0.33 0.018 0.513 0.064 0.646 -1.671 -0.546 -3.467 -1.089 -3.966 -0.22 -0.2 -0.232 -0.335 -0.123 -0.335 0.59 0.534 1.365 1.572 1.646 2.757 0.13 0.535 0.16 1.104 0.021 1.67 0.067 0.028 0.135 0.06 0.205 0.067 1.032 0.534 1.413 0.938 1.23 1.537v-0.043c-0.06 -0.003 -0.12 0 -0.18 0h-0.016c0.151 -0.467 -0.182 -0.825 -1.065 -1.224 -0.915 -0.4 -1.646 -0.336 -1.77 0.465 -0.008 0.043 -0.013 0.066 -0.018 0.135 -0.068 0.023 -0.139 0.053 -0.209 0.064 -0.43 0.268 -0.662 0.669 -0.793 1.187 -0.13 0.533 -0.17 1.156 -0.205 1.869v0.003c-0.02 0.334 -0.17 0.838 -0.319 1.35 -1.5 1.072 -3.58 1.538 -5.348 0.334a2.645 2.645 0 0 0 -0.402 -0.533 1.45 1.45 0 0 0 -0.275 -0.333c0.182 0 0.338 -0.03 0.465 -0.067a0.615 0.615 0 0 0 0.314 -0.334c0.108 -0.267 0 -0.697 -0.345 -1.163 -0.345 -0.467 -0.931 -0.995 -1.788 -1.521 -0.63 -0.4 -0.986 -0.87 -1.15 -1.396 -0.165 -0.534 -0.143 -1.085 -0.015 -1.645 0.245 -1.07 0.873 -2.11 1.274 -2.763 0.107 -0.065 0.037 0.135 -0.408 0.974 -0.396 0.751 -1.14 2.497 -0.122 3.854a8.123 8.123 0 0 1 0.647 -2.876c0.564 -1.278 1.743 -3.504 1.836 -5.268 0.048 0.036 0.217 0.135 0.289 0.202 0.218 0.133 0.38 0.333 0.59 0.465 0.21 0.201 0.477 0.335 0.876 0.335 0.039 0.003 0.075 0.006 0.11 0.006 0.412 0 0.73 -0.134 0.997 -0.268 0.29 -0.134 0.52 -0.334 0.74 -0.4h0.005c0.467 -0.135 0.835 -0.402 1.044 -0.7zm2.185 8.958c0.037 0.6 0.343 1.245 0.882 1.377 0.588 0.134 1.434 -0.333 1.791 -0.765l0.211 -0.01c0.315 -0.007 0.577 0.01 0.847 0.268l0.003 0.003c0.208 0.199 0.305 0.53 0.391 0.876 0.085 0.4 0.154 0.78 0.409 1.066 0.486 0.527 0.645 0.906 0.636 1.14l0.003 -0.007v0.018l-0.003 -0.012c-0.015 0.262 -0.185 0.396 -0.498 0.595 -0.63 0.401 -1.746 0.712 -2.457 1.57 -0.618 0.737 -1.37 1.14 -2.036 1.191 -0.664 0.053 -1.237 -0.2 -1.574 -0.898l-0.005 -0.003c-0.21 -0.4 -0.12 -1.025 0.056 -1.69 0.176 -0.668 0.428 -1.344 0.463 -1.897 0.037 -0.714 0.076 -1.335 0.195 -1.814 0.12 -0.465 0.308 -0.797 0.641 -0.984l0.045 -0.022zm-10.814 0.049h0.01c0.053 0 0.105 0.005 0.157 0.014 0.376 0.055 0.706 0.333 1.023 0.752l0.91 1.664 0.003 0.003c0.243 0.533 0.754 1.064 1.189 1.637 0.434 0.598 0.77 1.131 0.729 1.57v0.006c-0.057 0.744 -0.48 1.148 -1.125 1.294 -0.645 0.135 -1.52 0.002 -2.395 -0.464 -0.968 -0.536 -2.118 -0.469 -2.857 -0.602 -0.369 -0.066 -0.61 -0.2 -0.723 -0.4 -0.11 -0.2 -0.113 -0.602 0.123 -1.23v-0.004l0.002 -0.003c0.117 -0.334 0.03 -0.752 -0.027 -1.118 -0.055 -0.401 -0.083 -0.71 0.043 -0.94 0.16 -0.334 0.396 -0.4 0.69 -0.533 0.294 -0.135 0.64 -0.202 0.915 -0.47h0.002v-0.002c0.256 -0.268 0.445 -0.601 0.668 -0.838 0.19 -0.201 0.38 -0.336 0.663 -0.336zm7.159 -9.074c-0.435 0.201 -0.945 0.535 -1.488 0.535 -0.542 0 -0.97 -0.267 -1.28 -0.466 -0.154 -0.134 -0.28 -0.268 -0.373 -0.335 -0.164 -0.134 -0.144 -0.333 -0.074 -0.333 0.109 0.016 0.129 0.134 0.199 0.2 0.096 0.066 0.215 0.2 0.36 0.333 0.292 0.2 0.68 0.467 1.167 0.467 0.485 0 1.053 -0.267 1.398 -0.466 0.195 -0.135 0.445 -0.334 0.648 -0.467 0.156 -0.136 0.149 -0.267 0.279 -0.267 0.128 0.016 0.034 0.134 -0.147 0.332a8.097 8.097 0 0 1 -0.69 0.468zm-1.082 -1.583V5.64c-0.006 -0.02 0.013 -0.042 0.029 -0.05 0.074 -0.043 0.18 -0.027 0.26 0.004 0.063 0 0.16 0.067 0.15 0.135 -0.006 0.049 -0.085 0.066 -0.135 0.066 -0.055 0 -0.092 -0.043 -0.141 -0.068 -0.052 -0.018 -0.146 -0.008 -0.163 -0.065zm-0.551 0c-0.02 0.058 -0.113 0.049 -0.166 0.066 -0.047 0.025 -0.086 0.068 -0.14 0.068 -0.05 0 -0.13 -0.02 -0.136 -0.068 -0.01 -0.066 0.088 -0.133 0.15 -0.133 0.08 -0.031 0.184 -0.047 0.259 -0.005 0.019 0.009 0.036 0.03 0.03 0.05v0.02h0.003z" fill="#000000" stroke-width="1"></path>
</svg><?php elseif($device==='android'):?><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M17.6 9.48l1.84-3.18c.16-.31.04-.69-.26-.85-.3-.16-.68-.04-.85.26l-1.88 3.24c-2.86-1.21-6.08-1.21-8.94 0L5.65 5.71c-.16-.3-.54-.42-.85-.26-.3.16-.42.54-.26.85L6.4 9.48C3.3 11.25 1.28 14.44 1 18h22c-.28-3.56-2.3-6.75-5.4-8.52zM7 15.25c-.69 0-1.25-.56-1.25-1.25s.56-1.25 1.25-1.25 1.25.56 1.25 1.25-.56 1.25-1.25 1.25zm10 0c-.69 0-1.25-.56-1.25-1.25s.56-1.25 1.25-1.25 1.25.56 1.25 1.25-.56 1.25-1.25 1.25z"/></svg><?php elseif($device==='ios'):?><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.31-2.33 1.05-3.11z"/></svg><?php elseif($device==='macos'):?><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.31-2.33 1.05-3.11z"/></svg><?php else:?><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg><?php endif;?></div><span><?php echo my_htmlspecialchars($deviceText); ?></span></div></td>
                                <td><div class="device-browser-item"><?php $browser=$session['browser']??'unknown_browser';$browserClass=$browser.'-icon';$browserText=$translations[$browser]??$translations['unknown_browser'];?><div class="browser-icon <?php echo $browserClass; ?>"><?php echo get_browser_icon_svg($browser); ?></div><span><?php echo my_htmlspecialchars($browserText); ?></span></div></td>
                                <td><?php echo date('Y-m-d H:i:s',$session['login_time']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s',$session['last_activity']); ?></td>
                                <td><?php echo my_htmlspecialchars($session['ip_address']??'Unknown'); ?></td>
                                <td><?php if($sessionId!==session_id()):?><form method="post" style="display:inline;"><input type="hidden" name="session_id" value="<?php echo my_htmlspecialchars($sessionId); ?>"><button type="submit" name="terminate_session" class="terminate-btn"><?php echo my_htmlspecialchars($translations['terminate_session']); ?></button></form><?php else:?><span style="color:#666;"><?php echo my_htmlspecialchars($translations['current_session']); ?></span><?php endif;?></td>
                            </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                    <div style="margin-top:20px;"><form method="post"><button type="submit" name="terminate_all_sessions" class="auth-button" style="background:#ff4444;"><?php echo my_htmlspecialchars($translations['terminate_all_sessions']); ?></button></form></div>
                <?php endif;?>
            </div>
            <hr style="margin:20px 0;border:1px solid var(--border-color);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;"><h3 style="margin:0;"><?php echo my_htmlspecialchars($translations['trusted_devices']); ?></h3><button id="refresh-trusted" class="sidebar-button" style="background:var(--primary-color);color:white;border:none;padding:8px;border-radius:4px;cursor:pointer;" title="<?php echo my_htmlspecialchars($translations['refresh']); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg></button></div>
            <p style="font-size:13px;opacity:.7;margin-bottom:15px;"><?php echo my_htmlspecialchars($translations['trusted_devices_desc']); ?></p>
            <div id="add-trusted-device-container">
                <?php if(!$isCurrentDeviceTrusted):?>
                <div style="margin-bottom:15px;"><button type="button" id="add-trusted-device-btn" class="sidebar-button" style="background:#4CAF50;color:white;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;font-size:14px;width:100%;"><?php echo my_htmlspecialchars($translations['add_trusted_device']); ?></button></div>
                <?php endif;?>
            </div>
            <div id="trusted-devices-container">
                <?php if(empty($trustedDevices)):?>
                    <p id="no-trusted-msg"><?php echo my_htmlspecialchars($translations['no_trusted_devices']); ?></p>
                <?php else:?>
                    <table class="trusted-devices-table">
                        <thead><tr><th><?php echo my_htmlspecialchars($translations['trusted_ip']); ?></th><th><?php echo my_htmlspecialchars($translations['device_name']); ?></th><th><?php echo my_htmlspecialchars($translations['trusted_since']); ?></th><th><?php echo my_htmlspecialchars($translations['trusted_last_seen']); ?></th><th></th></tr></thead>
                        <tbody>
                        <?php $currentFp = getDeviceFingerprint(); foreach($trustedDevices as $dev):?>
                            <tr class="<?php echo ($dev['fingerprint'] ?? '') === $currentFp ? 'current-trusted-device' : ''; ?>">
                                <td><?php echo my_htmlspecialchars($dev['ip'] ?? '—'); ?></td>
                                <td><?php echo my_htmlspecialchars($dev['device_name'] ?? $translations['unknown_device']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', $dev['added_at'] ?? 0); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', $dev['last_seen'] ?? 0); ?></td>
                                <td><button type="button" class="remove-trusted-btn" data-fingerprint="<?php echo my_htmlspecialchars($dev['fingerprint'] ?? ''); ?>" data-token="<?php echo my_htmlspecialchars($dev['device_token'] ?? ''); ?>" data-name="<?php echo my_htmlspecialchars($dev['device_name'] ?? ''); ?>"><?php echo my_htmlspecialchars($translations['remove_trusted_device']); ?></button></td>
                            </tr>
                        <?php endforeach;?>
                        </tbody>
                    </table>
                <?php endif;?>
            </div>
        </div><!-- /.modal-scroll-body -->
        </div>
    </div>
<?php endif;?>
<div id="shared-files-modal">
    <div id="shared-files-content">
        <button id="close-shared-files" type="button">&times;</button>
        <h2 style="margin:0 0 12px 0;flex-shrink:0;"><?php echo my_htmlspecialchars($translations['shared_files_management']); ?></h2>
        <?php if(isset($sharedFilesSuccess)):?><div class="auth-success" style="flex-shrink:0;"><?php echo my_htmlspecialchars($sharedFilesSuccess); ?></div><?php endif;?>
        <div class="shared-files-header" style="flex-shrink:0;">
            <div class="shared-files-search-container">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
                <input type="text" id="shared-files-search" placeholder="<?php echo my_htmlspecialchars($translations['search_files']); ?>">
                <button id="shared-files-clear-search" type="button">&times;</button>
            </div>
            <div class="shared-files-actions">
                <select id="shared-files-sort">
                    <option value="created_desc"><?php echo my_htmlspecialchars($translations['share_sort_newest'] ?? 'Newest'); ?></option>
                    <option value="size_desc"><?php echo my_htmlspecialchars($translations['ap_sort_size'] ?? 'Size'); ?>: <?php echo my_htmlspecialchars($translations['share_sort_biggest'] ?? 'biggest'); ?></option>
                    <option value="size_asc"><?php echo my_htmlspecialchars($translations['ap_sort_size'] ?? 'Size'); ?>: <?php echo my_htmlspecialchars($translations['share_sort_smallest'] ?? 'smallest'); ?></option>
                    <option value="name_asc"><?php echo my_htmlspecialchars($translations['ap_sort_name'] ?? 'Name'); ?> A-Z</option>
                </select>
                <select id="shared-files-group">
                    <option value="none"><?php echo my_htmlspecialchars($translations['share_sort_no_grouping'] ?? 'No grouping'); ?></option>
                    <option value="user"><?php echo my_htmlspecialchars($translations['share_sort_group_user'] ?? 'Group by user'); ?></option>
                </select>
                <button id="delete-selected-shared-files" class="delete-filtered-btn" style="display:none;">Delete selected</button>
                <button id="refresh-shared-files" class="sidebar-button" style="background:var(--primary-color);color:white;border:none;padding:8px;border-radius:4px;cursor:pointer;" title="<?php echo my_htmlspecialchars($translations['refresh_files']); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                </button>
                <button id="delete-filtered-files" class="delete-filtered-btn" style="display:none;"><?php echo my_htmlspecialchars($translations['delete_filtered_files'] ?? 'Delete Filtered'); ?></button>
                <?php if(!empty($sharedFiles)):?>
                    <button type="button" id="delete-all-files-btn" class="terminate-btn" style="background:#ff4444;color:white;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['delete_all_files']); ?></button>
                <?php endif;?>
            </div>
        </div>
        <div id="shared-files-list-container">
            <?php if(empty($sharedFiles)):?>
                <p style="text-align:center;opacity:.5;padding:20px 0;"><?php echo my_htmlspecialchars($translations['no_shared_files']); ?></p>
            <?php else:?>
                <table id="shared-files-table" style="width:100%;border-collapse:collapse;table-layout:fixed;">
                    <colgroup>
                        <col style="width:12%">
                        <col style="width:20%">
                        <col style="width:28%">
                        <col style="width:24%">
                        <col style="width:16%">
                    </colgroup>
                    <thead>
                        <tr style="background:var(--table-header-bg);">
                            <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border-color);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo my_htmlspecialchars($translations['share_id']); ?></th>
                            <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border-color);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo my_htmlspecialchars($translations['file_name']); ?></th>
                            <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border-color);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo my_htmlspecialchars($translations['file_path']); ?></th>
                            <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border-color);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo my_htmlspecialchars($translations['expires'] ?? 'Expires'); ?></th>
                            <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border-color);white-space:nowrap;"><?php echo my_htmlspecialchars($translations['delete_file']); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sharedFiles as $shareId=>$fileData):
                            $_sfUrl = $fileData['file_path'] ?? ($fileData['url'] ?? '');
                            $_sfName = $fileData['file_name'] ?? ($_sfUrl !== '' ? basename($_sfUrl) : '');
                            $_sfExpiresOption = (string)($fileData['expires_option'] ?? 'never');
                            $_sfExpires = isset($fileData['expires']) && $fileData['expires'] !== null ? (int)$fileData['expires'] : null;
                            $_sfExpiryLabelMap = [
                                'never' => $translations['share_expiration_never'] ?? 'Never',
                                '1h' => $translations['share_expiration_1h'] ?? '1 hour',
                                '1d' => $translations['share_expiration_1d'] ?? '1 day',
                                '1w' => $translations['share_expiration_1w'] ?? '1 week',
                                '1m' => $translations['share_expiration_1m'] ?? '1 month',
                                '3m' => $translations['share_expiration_3m'] ?? '3 months',
                                '6m' => $translations['share_expiration_6m'] ?? 'Half a year',
                                '1y' => $translations['share_expiration_1y'] ?? '1 year',
                            ];
                            $_sfExpiryText = $_sfExpires ? date('Y-m-d H:i', $_sfExpires) : ($translations['share_expiration_never'] ?? 'Never');
                        ?>
                            <tr data-filename="<?php echo my_htmlspecialchars($_sfName); ?>" data-filepath="<?php echo my_htmlspecialchars($_sfUrl); ?>" data-shareid="<?php echo my_htmlspecialchars($shareId); ?>" style="border-bottom:1px solid var(--border-color);">
                                <td style="padding:8px 10px;font-size:11px;font-family:'Courier New',monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;opacity:.65;"><?php echo my_htmlspecialchars(substr($shareId,0,10).'…'); ?></td>
                                <td class="file-name" style="padding:8px 10px;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo my_htmlspecialchars($_sfName); ?>"><?php echo $_sfName !== '' ? my_htmlspecialchars($_sfName) : '<em style="opacity:.4">—</em>'; ?></td>
                                <td style="padding:8px 10px;font-size:11px;font-family:'Courier New',monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;opacity:.7;" title="<?php echo my_htmlspecialchars($_sfUrl); ?>"><?php if($_sfUrl===''){echo '<em style="opacity:.4">—</em>';}elseif($isRootUser){echo my_htmlspecialchars($_sfUrl);}else{echo my_htmlspecialchars('…/'.$_sfName);}?></td>
                                <td style="padding:6px 10px;">
                                    <select class="sf-expiry-select" data-shareid="<?php echo my_htmlspecialchars($shareId); ?>" style="width:100%;min-width:0;padding:6px 8px;border:1px solid var(--border-color);border-radius:6px;background:var(--input-bg);color:var(--text-color);font-size:12px;">
                                        <?php foreach($_sfExpiryLabelMap as $_sfOptionValue => $_sfOptionLabel): ?>
                                            <option value="<?php echo my_htmlspecialchars($_sfOptionValue); ?>" <?php echo $_sfExpiresOption === $_sfOptionValue ? 'selected' : ''; ?>><?php echo my_htmlspecialchars($_sfOptionLabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td style="padding:6px 10px;white-space:nowrap;"><button type="button" class="terminate-btn sf-delete-btn" data-shareid="<?php echo my_htmlspecialchars($shareId); ?>" style="padding:4px 10px;font-size:12px;"><?php echo my_htmlspecialchars($translations['delete_file']); ?></button></td>
                            </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            <?php endif;?>
        </div>
    </div>
</div>
<div id="qr-modal">
    <div id="qr-content">
        <div class="modal-header"><div class="modal-header-left"><h2><?php echo my_htmlspecialchars($translations['qr_code_title']); ?></h2></div><button id="close-qr" type="button">&times;</button></div>
        <div class="modal-scroll-body">
        <div class="qr-container">
            <h2><?php echo my_htmlspecialchars($translations['qr_code_title']); ?></h2>
            <div class="qr-wrapper"><div id="qr-code"></div></div>
            <div class="domain-info"><h4><?php echo my_htmlspecialchars($translations['current_domain']); ?></h4><div class="domain-url" id="current-domain-url"><?php echo my_htmlspecialchars($currentDomain); ?></div><button class="copy-link-btn" id="copy-domain-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg><?php echo my_htmlspecialchars($translations['copy_link']); ?></button></div>
        </div>
        </div><!-- /.modal-scroll-body -->
    </div>
</div>
<?php if($path!=='/login'&&$path!=='/register'):?>
    <?php if(!$isHomeDashboard): ?><button id="menu-button" type="button" aria-label="<?php echo my_htmlspecialchars($translations['nav_menu_title'] ?? 'Menu'); ?>" title="<?php echo my_htmlspecialchars($translations['nav_menu_title'] ?? 'Menu'); ?>"><span class="menu-button-glyph" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/></svg></span></button><?php endif; ?>
    <div id="sidebar">
        <?php if(!$isHomeDashboard): ?>
        <div class="weather-widget weather-widget-instance" id="sidebarWeatherWidget">
            <span class="weather-icon"></span>
            <span class="weather-temp">--°C</span>
            <span class="weather-location-sep">|</span>
            <svg class="weather-pin-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
            <span class="weather-location">--</span>
            <span class="weather-wind"></span>
            <span class="weather-perm" style="display:none"><?php echo my_htmlspecialchars($translations['weather_perm_denied'] ?? 'Please allow location access'); ?></span>
        </div>
        <?php endif; ?>
        <div id="app-list-container">
            <ul id="app-list">
                <?php foreach($groupedApps as $letter=>$group):?><div class="group-letter"><?php echo my_htmlspecialchars($letter); ?></div><div class="group-block"><ul><?php foreach($group as $name=>$appData):?><li data-tooltip="<?php echo my_htmlspecialchars($translations['open_app_tooltip'].' '.$name); ?>"><a href="<?php echo my_htmlspecialchars($appData['href']); ?>" target="<?php echo my_htmlspecialchars($appData['target']); ?>" rel="noopener" class="app-tile-link"><span class="sidebar-app-name"><?php echo my_htmlspecialchars($name); ?></span><span class="sidebar-app-icon-wrap"><?php if(!empty($appData['icon_masked']) && !empty($appData['is_svg'])): ?><span class="sidebar-app-icon-svg" style="--icon-src:url('<?php echo my_htmlspecialchars($appData['icon']); ?>');" aria-hidden="true"></span><?php elseif(($appData['icon_style']??'')==='animated' && !empty($appData['is_svg'])): ?><object class="sidebar-app-icon" type="image/svg+xml" data="<?php echo my_htmlspecialchars($appData['icon']); ?>" aria-hidden="true"></object><?php else: ?><img src="<?php echo my_htmlspecialchars($appData['icon']); ?>" alt="" class="sidebar-app-icon"><?php endif; ?></span></a><span class="tooltip"></span></li><?php endforeach;?></ul></div><?php endforeach;?>
            </ul>
        </div>
        <div id="bottom-bar">
            <div class="search-container">
                <i class="search-icon fa-solid fa-magnifying-glass"></i>
                <input type="text" id="search-bar" placeholder="<?php echo my_htmlspecialchars($translations['search_placeholder']); ?>">
                <button id="clear-search" style="display:none;">&times;</button>
            </div>
            <?php if(!$isHomeDashboard&&isset($_SESSION['user'])):?><button type="button" id="support-chat-btn" class="sidebar-button app-page-chat" title="<?php echo my_htmlspecialchars($translations['chat'] ?? 'Chat'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['chat'] ?? 'Chat'); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg><span id="chat-unread-badge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;border-radius:12px;padding:2px 6px;font-size:11px;font-weight:700;min-width:18px;text-align:center;box-shadow:0 2px 8px rgba(239,68,68,0.5);"></span></button><?php endif;?>
            <div class="bottom-bar-actions">
                <?php if(!$isHomeDashboard): ?><a href="/main" class="sidebar-button" data-tooltip="<?php echo my_htmlspecialchars($translations['home_tooltip']); ?>" title="<?php echo my_htmlspecialchars($translations['home']); ?>"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a><?php endif; ?>
                <button id="settings-button" class="sidebar-button" data-tooltip="<?php echo my_htmlspecialchars($translations['settings_title']); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14,12.94a1.43,1.43,0,0,0,0-1.88l2.11-1.65a.48.48,0,0,0,.12-.61l-2-3.46a.49.49,0,0,0-.6-.21l-2.49,1a5.14,5.14,0,0,0-1.62-.94l-.38-2.65A.48.48,0,0,0,13.81,2H10.19a.48.48,0,0,0-.48.41L9.33,5.06a5.14,5.14,0,0,0-1.62.94l-2.49-1a.49.49,0,0,0-.6.21l-2,3.46a.48.48,0,0,0,.12.61L4.86,11.06a1.43,1.43,0,0,0,0,1.88L2.75,14.59a.48.48,0,0,0-.12.61l2,3.46a.49.49,0,0,0,.6.21l2.49-1a5.14,5.14,0,0,0,1.62.94l.38,2.65a.48.48,0,0,0,.48.41h3.62a.48.48,0,0,0,.48-.41l.38-2.65a5.14,5.14,0,0,0,1.62-.94l2.49,1a.49.49,0,0,0,.6-.21l2-3.46a.48.48,0,0,0-.12-.61ZM12,15.5A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/></svg></button>
                <?php if(isset($_SESSION['user'])):?><div class="user-profile" id="user-profile"><img src="<?php echo my_htmlspecialchars(app_avatar_src($_SESSION['user']['photo']??'default_avatar.png')); ?>" alt="User Avatar" class="user-avatar"><div class="user-name"><?php echo my_htmlspecialchars($_SESSION['user']['name']); ?></div></div><?php endif;?>
            </div>
        </div>
    </div>
<?php endif;?>
<div id="settings-modal">
    <form id="settings-form" method="post" action="/main">
        <button id="close-settings" type="button">&times;</button>
        <div class="modal-scroll-body">
        <h2><?php echo my_htmlspecialchars($translations['settings_title']); ?></h2>
        <div class="settings-table">
                <div class="settings-table-cell">
                    <label><?php echo my_htmlspecialchars($translations['language']); ?>:</label>
                    <div class="custom-select-wrap" id="lang-select-wrap">
                        <input type="hidden" name="language" id="lang-input" value="<?php echo my_htmlspecialchars($config['language']); ?>">
                        <div class="custom-select-trigger" id="lang-trigger">
                            <span class="custom-select-text"></span>
                            <svg class="custom-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <div class="custom-select-dropdown" id="lang-dropdown">
                            <div class="custom-select-option" data-value="en"><span>English</span><span class="custom-select-flag flag flag-gb"></span></div>
                            <div class="custom-select-option" data-value="ru"><span>Русский</span><span class="custom-select-flag flag flag-ru"></span></div>
                            <div class="custom-select-option" data-value="uk"><span>Українська</span><span class="custom-select-flag flag flag-ua"></span></div>
                            <div class="custom-select-option" data-value="be"><span>Беларуская</span><span class="custom-select-flag flag flag-by"></span></div>
                            <div class="custom-select-option" data-value="pl"><span>Polski</span><span class="custom-select-flag flag flag-pl"></span></div>
                            <div class="custom-select-option" data-value="cs"><span>Čeština</span><span class="custom-select-flag flag flag-cz"></span></div>
                        </div>
                    </div>
                </div>
                <div class="settings-table-cell">
                    <label><?php echo my_htmlspecialchars($translations['theme']); ?>:</label>
                    <div class="custom-select-wrap" id="theme-select-wrap">
                        <input type="hidden" name="theme" id="theme-input" value="<?php echo my_htmlspecialchars($savedTheme); ?>">
                        <div class="custom-select-trigger" id="theme-trigger">
                            <span class="custom-select-text"></span>
                            <svg class="custom-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <div class="custom-select-dropdown" id="theme-dropdown">
                            <div class="custom-select-option" data-value="light"><span><?php echo my_htmlspecialchars($translations['theme_light']); ?></span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg></div>
                            <div class="custom-select-option" data-value="dark"><span><?php echo my_htmlspecialchars($translations['theme_dark']); ?></span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg></div>
                            <div class="custom-select-option" data-value="auto"><span><?php echo my_htmlspecialchars($translations['theme_auto']); ?></span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg></div>
                        </div>
                    </div>
                </div>
                <div class="settings-table-cell">
                    <label><?php echo my_htmlspecialchars($translations['icon_sort']); ?>:</label>
                    <div class="custom-select-wrap" id="icon-sort-select-wrap">
                        <input type="hidden" name="icon_sort_order" id="icon-sort-input" value="<?php echo my_htmlspecialchars($savedIconSortOrder); ?>">
                        <div class="custom-select-trigger" id="icon-sort-trigger">
                            <span class="custom-select-text"></span>
                            <svg class="custom-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <div class="custom-select-dropdown" id="icon-sort-dropdown">
                            <div class="custom-select-option" data-value="alpha">
                                <span><?php echo my_htmlspecialchars($translations['icon_sort_alpha']); ?></span>
                                <span class="custom-select-badge"><?php echo my_htmlspecialchars($translations['icon_sort_recommended']); ?></span>
                            </div>
                            <div class="custom-select-option" data-value="popularity">
                                <span><?php echo my_htmlspecialchars($translations['icon_sort_popular']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="settings-table-cell">
                    <label><?php echo my_htmlspecialchars($translations['icon_style'] ?? 'Icon style'); ?>:</label>
                    <div class="custom-select-wrap" id="icon-style-select-wrap">
                        <input type="hidden" name="icon_style" id="icon-style-input" value="<?php echo my_htmlspecialchars($savedIconStyle ?? 'animated'); ?>">
                        <div class="custom-select-trigger" id="icon-style-trigger">
                            <span class="custom-select-text"></span>
                            <svg class="custom-select-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <div class="custom-select-dropdown" id="icon-style-dropdown">
                            <div class="custom-select-option" data-value="monochrome"><span><?php echo my_htmlspecialchars($translations['icon_style_monochrome'] ?? 'Monochrome'); ?></span></div>
                            <div class="custom-select-option" data-value="multicolor"><span><?php echo my_htmlspecialchars($translations['icon_style_multicolor'] ?? 'Multicolor'); ?></span></div>
                            <div class="custom-select-option" data-value="animated"><span><?php echo my_htmlspecialchars($translations['icon_style_animated'] ?? 'Animated'); ?></span><span class="custom-select-badge"><?php echo my_htmlspecialchars($translations['icon_sort_recommended'] ?? 'Recommended'); ?></span></div>
                        </div>
                    </div>
                </div>
                <div class="settings-table-cell">
                    <label><?php echo my_htmlspecialchars($translations['color']); ?>:</label>
                    <input type="color" name="color" value="<?php echo my_htmlspecialchars($config['color']); ?>">
                </div>
                <div class="settings-table-cell">
                    <label><?php echo my_htmlspecialchars($translations['wallpaper'] ?? 'Background'); ?>:</label>
                    <input type="hidden" name="wallpaper" id="wallpaper-input" value="<?php echo my_htmlspecialchars($savedWallpaper ?? 'default'); ?>">
                    <button type="button" id="wallpaper-open-btn" class="wallpaper-open-btn" title="<?php echo my_htmlspecialchars($translations['wallpaper_choose'] ?? 'Choose background'); ?>">
                        <?php $wpPreview = ap_wallpaper_asset_url($savedWallpaper ?? 'default'); ?>
                        <?php if ($wpPreview !== ''): ?><span class="wallpaper-open-btn-preview" style="background-image:url('<?php echo my_htmlspecialchars($wpPreview); ?>');"></span><?php endif; ?>
                        <span class="wallpaper-open-btn-label"><?php echo my_htmlspecialchars($translations['wallpaper_choose'] ?? 'Choose background'); ?></span>
                    </button>
                </div>
        </div>

        <div class="policy-links">
            <a href="javascript:void(0)" class="policy-link" onclick="openPolicyModal('/terms?hideBack=1&theme=<?php echo urlencode($config['theme']); ?>&lang=<?php echo urlencode($language); ?>&color=<?php echo urlencode($config['color']); ?>')"><svg class="policy-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><?php echo my_htmlspecialchars($translations['view_terms']??'Terms of Service'); ?></a>
            <span class="policy-dot"></span>
            <a href="javascript:void(0)" class="policy-link" onclick="openPolicyModal('/privacy?hideBack=1&theme=<?php echo urlencode($config['theme']); ?>&lang=<?php echo urlencode($language); ?>&color=<?php echo urlencode($config['color']); ?>')"><svg class="policy-link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><?php echo my_htmlspecialchars($translations['view_privacy']??'Privacy Policy'); ?></a>
        </div>
        <div class="settings-buttons-box">
            <?php if(isset($_SESSION['user']) && (int)($_SESSION['user']['role'] ?? 0) >= 1): ?>
            <div class="settings-button-wrapper">
                <button id="news-portal-open-btn" type="button" class="sidebar-button" onclick="const sm=document.getElementById('settings-modal');if(sm)sm.style.display='none';if(typeof window.openNewsPortal==='function')window.openNewsPortal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg>
                </button>
                <span class="tooltip"><?php echo my_htmlspecialchars($translations['news_portal_title'] ?? 'News Portal'); ?></span>
            </div>
            <?php endif; ?>
            <div class="settings-button-wrapper">
                <button id="qr-button" type="button" class="sidebar-button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M1 1h8v8H1zM3 3v4h4V3zM1 15h8v8H1zM3 17v4h4v-4zM15 1h8v8h-8zM17 3v4h4V3zM15 15h8v8h-8zM17 17v4h4v-4z"/></svg>
                </button>
                <span class="tooltip" id="qr-tooltip"><?php echo my_htmlspecialchars($translations['qr_code']); ?></span>
            </div>
            <div class="settings-button-wrapper">
                <button id="shared-files-button" type="button" class="sidebar-button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                </button>
                <span class="tooltip" id="shared-files-tooltip"><?php echo my_htmlspecialchars($translations['shared_files_management']); ?></span>
            </div>
            <?php if(isset($_SESSION['user'])):?>
            <div class="settings-button-wrapper">
                <button id="mcp-setup-open-btn" type="button" class="sidebar-button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="M12 18v4"/><path d="m4.93 4.93 2.83 2.83"/><path d="m16.24 16.24 2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="m4.93 19.07 2.83-2.83"/><path d="m16.24 7.76 2.83-2.83"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <span class="tooltip" id="mcp-setup-tooltip"><?php echo my_htmlspecialchars($translations['mcp_setup_title'] ?? 'MCP for AI agents'); ?></span>
            </div>
            <?php endif;?>
            <?php if(isset($_SESSION['user'])):?>
            <div class="settings-button-wrapper">
                <button id="sessions-button" type="button" class="sidebar-button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                </button>
                <span class="tooltip" id="sessions-tooltip"><?php echo my_htmlspecialchars($translations['session_management']); ?></span>
            </div>
            <?php endif;?>
            <?php if(isset($_SESSION['user'])):?>
            <div class="settings-button-wrapper">
                <button id="otp-gen-open-btn" type="button" class="sidebar-button" onclick="openOtpGeneratorModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                </button>
                <span class="tooltip" id="otp-gen-tooltip"><?php echo my_htmlspecialchars($translations['otp_generate_btn']??'Generate One-Time PIN'); ?></span>
            </div>
            <?php endif;?>
            <div class="settings-buttons-end-group">
            <div class="settings-button-wrapper shortcuts-help-wrap">
                <button id="shortcuts-help-button" type="button" class="sidebar-button shortcuts-help-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-info-icon lucide-info"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </button>
                <span class="tooltip" id="shortcuts-help-tooltip"><?php echo my_htmlspecialchars($translations['keyboard_shortcuts'] ?? 'Keyboard Shortcuts'); ?></span>
            </div>
            <?php if($isAdmin):?>
            <div class="settings-buttons-divider" aria-hidden="true"></div>
            <div class="settings-button-wrapper admin-panel-wrap">
                <button id="open-admin-panel-btn" type="button" class="sidebar-button admin-panel-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 4l5 2.18V11c0 3.5-2.33 6.79-5 7.93C9.33 17.79 7 14.5 7 11V7.18L12 5zm-1 3v4h2V8h-2zm0 6v2h2v-2h-2z"/></svg>
                </button>
                <span class="tooltip" id="admin-panel-btn-tooltip"><?php echo my_htmlspecialchars($translations['admin_panel_title'] ?? 'Admin Panel'); ?></span>
            </div>
            <?php endif;?>
            </div>
        </div>
        <input type="hidden" name="current_app" value="<?php echo my_htmlspecialchars($selectedApp); ?>">
        <input type="submit" name="settings" value="<?php echo my_htmlspecialchars($translations['save']); ?>" class="settings-save-btn">

        </div><!-- /.modal-scroll-body -->
</form>
</div>
<div id="shortcuts-modal">
    <div id="shortcuts-content">
        <button id="close-shortcuts" type="button">&times;</button>
        <h2 style="margin:0 0 12px 0;"><?php echo my_htmlspecialchars($translations['keyboard_shortcuts'] ?? 'Keyboard Shortcuts'); ?></h2>
        <p style="margin:0 0 12px 0;opacity:.75;"><?php echo my_htmlspecialchars($translations['keyboard_shortcuts_desc'] ?? 'Hotkeys available on the home screen with app tiles.'); ?></p>
        <?php if (!empty($_guideEnabled)): ?>
        <button id="guide-reopen-btn" type="button" onclick="window.startOnboardingTour&&window.startOnboardingTour()" style="display:flex;align-items:center;gap:8px;margin:0 0 14px 0;padding:9px 16px;border-radius:12px;border:1px solid color-mix(in srgb,var(--primary-color) 45%,transparent);background:color-mix(in srgb,var(--primary-color) 14%,transparent);color:var(--primary-color);font:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:background .2s;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg><?php echo my_htmlspecialchars($translations['guide_reopen'] ?? 'Watch guide'); ?></button>
        <?php endif; ?>
        <table class="shortcuts-table">
            <thead>
                <tr>
                    <th><?php echo my_htmlspecialchars($translations['keyboard_shortcuts_key'] ?? 'Key'); ?></th>
                    <th><?php echo my_htmlspecialchars($translations['keyboard_shortcuts_action'] ?? 'Action'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>A</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_admin_panel'] ?? 'Open Admin Panel'); ?><?php if(!$isAdmin): ?> <span style="opacity:.6;">(<?php echo my_htmlspecialchars($translations['admin_only'] ?? 'admin only'); ?>)</span><?php endif; ?></td></tr>
                <tr><td><code>S</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_sessions'] ?? 'Open Session Management'); ?></td></tr>
                <tr><td><code>F</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_shared_files'] ?? 'Open Shared Files'); ?></td></tr>
                <tr><td><code>Q</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_qr'] ?? 'Open QR Code'); ?></td></tr>
                <tr><td><code>N</code></td><td><?php echo my_htmlspecialchars($translations['news_portal_title'] ?? 'News Portal'); ?></td></tr>
                <tr><td><code>P</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_profile'] ?? 'Open Profile'); ?></td></tr>
                <?php if(isset($_SESSION['user'])): ?><tr><td><code>M</code></td><td><?php echo my_htmlspecialchars($translations['mcp_setup_title'] ?? 'MCP for AI agents'); ?></td></tr><?php endif; ?>
                <tr><td><code>H</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_support_chat'] ?? 'Open Support Chat'); ?></td></tr>
                <tr><td><code>← ↑ ↓ →</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_arrows'] ?? 'Move between app tiles'); ?></td></tr>
                <tr><td><code>Enter</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_enter'] ?? 'Open selected app'); ?></td></tr>
                <tr><td><code>/</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_search'] ?? 'Focus app search'); ?></td></tr>
                <tr><td><code>I</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_shortcuts'] ?? 'Open keyboard shortcuts'); ?></td></tr>
            </tbody>
        </table>
        <div class="info-warning" role="note">
            <svg class="info-warning-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            <span><?php echo my_htmlspecialchars($translations['account_delete_warning'] ?? 'To completely delete your account data, please contact support.'); ?></span>
        </div>
    </div>
</div>
<?php require __DIR__ . '/news_portal_modal.php'; ?>
<?php
$selectedAppData=null;
if($selectedApp){foreach($apps as $appData){if($appData['folder']===$selectedApp){$selectedAppData=$appData;break;}}}
$showAppFrame=$path==='/main'&&$selectedAppData!==null;
$showAppRail=$showAppFrame&&!empty($selectedAppData['app_rail']);
?>
<?php $railTopApps=['immich','gitea','plane','dawarich','lopaka']; ?>
<?php $edgeAppShellApps=['immich','gitea','plane','dawarich','x','X','lopaka']; ?>
<div id="content"<?php if($showAppFrame):?> class="mobile-vv-shell app-edge-<?php echo my_htmlspecialchars(strtolower((string)$selectedApp)); ?><?php echo $showAppRail?' has-app-rail':''; ?><?php echo ($showAppRail&&in_array($selectedApp,$railTopApps,true))?' rail-top-on-mobile':''; ?><?php echo ($showAppRail&&in_array($selectedApp,$edgeAppShellApps,true))?' edge-app-shell':''; ?>"<?php endif;?>>
    <?php if($showAppFrame):?>
        <?php if($showAppRail):?>
        <div id="app-icon-rail">
            <div class="app-rail-scroll">
            <a href="/main" class="app-rail-link app-rail-home" title="<?php echo my_htmlspecialchars($translations['home_tooltip'] ?? $translations['home'] ?? 'Home'); ?>"><svg viewBox="0 0 24 24" fill="currentColor" width="60%" height="60%"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
            <span class="app-rail-service-name"><?php echo my_htmlspecialchars(array_key_first(array_filter($apps,function($a)use($selectedApp){return $a['folder']===$selectedApp;}))?? ucfirst((string)$selectedApp)); ?></span>
            </div>
        </div>
        <?php endif;?>
        <iframe id="app-iframe" name="devtools-app" title="Application: <?php echo my_htmlspecialchars($selectedApp); ?>" class="mobile-vv-fill" src="apps/<?php echo urlencode($selectedApp); ?>/<?php echo $selectedApp === 'lopaka' ? '' : 'index.php'; ?>?lang=<?php echo urlencode($language); ?>&theme=<?php echo urlencode($themeForAppLink); ?>&color=<?php echo urlencode($config['color']); ?><?php echo $userDataForApps?'&user_data='.urlencode(base64_encode(json_encode($userDataForApps))):''; ?>"></iframe>
    <?php elseif($path==='/main'):?>
        <?php if(!$showTopBarTitle):?>
        <button type="button" id="notifications-btn" class="notifications-floating" title="<?php echo my_htmlspecialchars($translations['notifications'] ?? 'Notifications'); ?>" style="position:fixed;top:10px;right:64px;z-index:9999;width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,<?php echo $config['color']; ?> 22%,transparent) 0%,color-mix(in srgb,<?php echo $config['color']; ?> 10%,transparent) 100%);color:<?php echo $config['color']; ?>;border:1px solid color-mix(in srgb,<?php echo $config['color']; ?> 32%,transparent);box-shadow:0 2px 10px color-mix(in srgb,<?php echo $config['color']; ?> 18%,transparent);cursor:pointer;display:flex;align-items:center;justify-content:center;" aria-label="<?php echo my_htmlspecialchars($translations['notifications'] ?? 'Notifications'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8a6 6 0 0 0-12 0c0 4.499-1.411 5.956-2.738 7.326"/></svg>
            <span id="notifications-unread-badge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;border-radius:12px;padding:2px 6px;font-size:11px;font-weight:700;min-width:18px;text-align:center;box-shadow:0 2px 8px rgba(239,68,68,0.5);"></span>
        </button>
        <button type="button" id="support-chat-btn" class="support-chat-floating" title="<?php echo my_htmlspecialchars($translations['support_chat'] ?? 'Support Chat'); ?>" style="position:fixed;top:10px;right:16px;z-index:9999;width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,<?php echo $config['color']; ?> 28%,transparent) 0%,color-mix(in srgb,<?php echo $config['color']; ?> 14%,transparent) 100%);color:<?php echo $config['color']; ?>;border:1px solid color-mix(in srgb,<?php echo $config['color']; ?> 35%,transparent);box-shadow:0 2px 10px color-mix(in srgb,<?php echo $config['color']; ?> 22%,transparent);cursor:pointer;display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
            <span id="chat-unread-badge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;border-radius:12px;padding:2px 6px;font-size:11px;font-weight:700;min-width:18px;text-align:center;box-shadow:0 2px 8px rgba(239,68,68,0.5);"></span>
        </button>
        <?php endif; ?>
        <div id="app-grid">
                        <?php $mostPopularKey=''; $maxVisits=-1; foreach($apps as $n=>$d){if($d['visits']>$maxVisits){$maxVisits=$d['visits'];$mostPopularKey=$n;}} foreach($apps as $name=>$appData): $_ga=mt_rand(110,165);$_ghx=mt_rand(18,40);$_ghy=mt_rand(14,34);$_gl=mt_rand(24,44);$_gf=mt_rand(46,66);$_gs=mt_rand(48,66);$_gm=mt_rand(38,62);$_ge=mt_rand(50,74); ?>
                <a href="<?php echo my_htmlspecialchars($appData['href']); ?>" target="<?php echo my_htmlspecialchars($appData['target']); ?>" rel="noopener" class="app-tile-link" data-app-desc="<?php echo my_htmlspecialchars($appData['description'] ?? ''); ?>"><div class="app-tile" style="position: relative; overflow: visible;"><?php if ($name === $mostPopularKey): ?><div class="app-popular-badge" aria-label="Popular"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#ff3b30" stroke="#fff" stroke-width="0.6" d="M12 2.5l2.47 5.01 5.53.8-4 3.9.94 5.5L12 15.9l-4.94 2.81.94-5.5-4-3.9 5.53-.8L12 2.5z"/></svg></div><?php endif; ?><div class="app-icon-wrap"><?php if(!empty($appData['icon_masked']) && !empty($appData['is_svg'])): ?><span class="app-icon-svg" style="--icon-src:url('<?php echo my_htmlspecialchars($appData['icon']); ?>');--g-angle:<?php echo $_ga; ?>deg;--g-hx:<?php echo $_ghx; ?>%;--g-hy:<?php echo $_ghy; ?>%;--g-light:<?php echo $_gl; ?>%;--g-fade:<?php echo $_gf; ?>%;--g-start:<?php echo $_gs; ?>%;--g-mid:<?php echo $_gm; ?>%;--g-end:<?php echo $_ge; ?>%;" aria-label="<?php echo my_htmlspecialchars($name); ?>" role="img"></span><?php elseif(($appData['icon_style']??'')==='animated' && !empty($appData['is_svg'])): ?><object class="app-icon" type="image/svg+xml" data="<?php echo my_htmlspecialchars($appData['icon']); ?>" aria-label="<?php echo my_htmlspecialchars($name); ?>"></object><?php else: ?><img src="<?php echo my_htmlspecialchars($appData['icon']); ?>" alt="<?php echo my_htmlspecialchars($name); ?>" class="app-icon"><?php endif; ?></div><div class="app-name"><?php echo my_htmlspecialchars($name); ?></div></div></a>
            <?php endforeach; ?>
        </div>
    <?php endif;?>
</div>
<?php $wpCatalog = ap_wallpaper_catalog(); ?>
<div id="wallpaper-modal">
    <div id="wallpaper-modal-content">
        <button id="close-wallpaper-modal" type="button" style="position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;z-index:2;">&times;</button>
        <div class="modal-scroll-body">
            <h2><?php echo my_htmlspecialchars($translations['wallpaper_choose'] ?? 'Choose background'); ?></h2>
            <div class="wallpaper-picker" id="wallpaper-picker">
<?php foreach ($wpCatalog as $wpId => $wpMeta): ?>
                <button type="button" class="wallpaper-option<?php echo ($savedWallpaper ?? 'default') === $wpId ? ' active' : ''; ?>" data-value="<?php echo my_htmlspecialchars($wpId); ?>"<?php if ($wpId !== 'default'): ?> style="<?php echo my_htmlspecialchars(ap_wallpaper_preview_style($wpId)); ?>"<?php endif; ?> title="<?php echo my_htmlspecialchars($translations[$wpMeta['i18n']] ?? $wpId); ?>"><span><?php echo my_htmlspecialchars($translations[$wpMeta['i18n']] ?? $wpId); ?></span></button>
<?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<div id="app-desc-popover" class="app-desc-popover" role="tooltip"><span class="app-desc-popover-inner"><span class="app-desc-text"></span></span></div>
<div id="notifications-panel" role="region" aria-live="polite">
    <div class="notifications-head">
        <span><?php echo my_htmlspecialchars($translations['notifications'] ?? 'Notifications'); ?></span>
        <div class="notifications-actions">
            <button type="button" id="notifications-enable-btn"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span><?php echo my_htmlspecialchars($translations['enable'] ?? 'Enable'); ?></span></button>
            <button type="button" id="notifications-mark-read-btn"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span><?php echo my_htmlspecialchars($translations['mark_read'] ?? 'Read'); ?></span></button>
        </div>
    </div>
    <div id="notifications-list"></div>
</div>

<?php require __DIR__ . '/modals/support_chat.php'; ?>

<style>
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
@keyframes supportDelModalIn {
    from { transform: translateY(16px) scale(.96); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}
@keyframes float {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    25% { transform: translate(10px, -10px) rotate(5deg); }
    50% { transform: translate(-5px, 5px) rotate(-5deg); }
    75% { transform: translate(-10px, -5px) rotate(3deg); }
}
@keyframes pulse {
    0%, 100% { box-shadow: 0 8px 24px var(--chat-color-shadow); }
    50% { box-shadow: 0 8px 32px var(--chat-color-glow), 0 0 0 8px var(--chat-color-ring); }
}
.chat-bg-animation {
    position: absolute;
    width: 100%;
    height: 100%;
    background-image:
        radial-gradient(circle at 20% 30%, var(--chat-color-light) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, var(--chat-color-medium) 0%, transparent 50%),
        radial-gradient(circle at 40% 80%, var(--chat-color-lighter) 0%, transparent 50%);
    animation: float 20s ease-in-out infinite;
}
#support-chat-btn.has-unread {
    animation: pulse 2s ease-in-out infinite;
}
.chat-messages-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(120, 120, 130, 0.45) transparent;
}
.chat-messages-scroll::-webkit-scrollbar {
    width: 8px;
}
.chat-messages-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.chat-messages-scroll::-webkit-scrollbar-thumb {
    background: rgba(120, 120, 130, 0.42);
    border-radius: 100px;
    border: 2px solid transparent;
    background-clip: padding-box;
}
.chat-messages-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(120, 120, 130, 0.62);
    border: 2px solid transparent;
    background-clip: padding-box;
}
#chat-input,#chat-user-search-input{background:var(--modal-bg);color:var(--text-color);caret-color:var(--text-color);-webkit-text-fill-color:var(--text-color);}
#chat-input::placeholder,#chat-user-search-input::placeholder{color:color-mix(in srgb,var(--text-color) 42%,transparent);opacity:1;}
.chat-list-row-active{border-color:var(--primary-color)!important;box-shadow:0 0 0 2px color-mix(in srgb,var(--primary-color) 38%,transparent);background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 14%,transparent),color-mix(in srgb,var(--primary-color) 7%,transparent))!important;}
#chat-messages .chat-msg-bubble{min-width:0;max-width:min(70%,92vw);overflow:hidden;box-sizing:border-box;}
#chat-messages .chat-msg-bubble .chat-msg-media-wrap{max-width:100%;margin-bottom:8px;border-radius:12px;overflow:hidden;line-height:0;}
#chat-messages .chat-msg-bubble .chat-msg-media-wrap img,#chat-messages .chat-msg-bubble .chat-msg-media-wrap video{max-width:100%;max-height:min(52vh,360px);width:auto;height:auto;object-fit:contain;display:block;vertical-align:top;cursor:pointer;-webkit-tap-highlight-color:transparent;}
@media (max-width:640px){
#chat-messages .chat-msg-bubble{max-width:min(94vw,calc(100vw - 48px));}
#chat-messages .chat-msg-bubble .chat-msg-media-wrap img,#chat-messages .chat-msg-bubble .chat-msg-media-wrap video{max-height:min(48vh,320px);}
}
#support-chat-lightbox-inner img,#support-chat-lightbox-inner video{max-width:96vw;max-height:86vh;width:auto;height:auto;object-fit:contain;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.5);}
#chat-admin-mobile-back,#admin-chat-list-close{display:none;}
@media (max-width:768px){
#support-chat-modal #chat-modal-container.support-chat-admin-mobile{flex-direction:column!important;width:100%!important;height:100%!important;max-height:none!important;border-radius:0!important;}
#support-chat-modal #chat-modal-container.support-chat-admin-mobile #admin-chat-list{width:100%!important;max-height:none!important;border-right:none!important;border-bottom:1px solid var(--border-color);min-height:0;}
#support-chat-modal #chat-modal-container.support-chat-admin-mobile.chat-mobile-phase-list #chat-messages-container{display:none!important;}
#support-chat-modal #chat-modal-container.support-chat-admin-mobile.chat-mobile-phase-chat #admin-chat-list{display:none!important;}
#support-chat-modal #chat-modal-container.support-chat-admin-mobile.chat-mobile-phase-chat #chat-messages-container{flex:1!important;min-height:0!important;width:100%!important;display:flex!important;}
#support-chat-modal #chat-modal-container.support-chat-admin-mobile #admin-chat-list>div:last-child{flex:1;min-height:0;}
#support-chat-modal #chat-modal-container.support-chat-admin-mobile.chat-mobile-phase-chat #chat-admin-mobile-back{display:flex!important;}
#support-chat-modal #chat-modal-container.support-chat-admin-mobile.chat-mobile-phase-list #admin-chat-list-close{display:flex!important;}
}
.chat-media-tool-btn.chat-media-tool-active{background:rgba(255,255,255,.22)!important;border-color:#fff!important;}
#chat-media-editor-modal{touch-action:none;}
@keyframes voicePulse {
    0% { transform: scale(1); opacity: 0.5; }
    100% { transform: scale(1.8); opacity: 0; }
}
#chat-voice-modal #chat-voice-icon.recording {
    animation: pulse 2s ease-in-out infinite;
}
.chat-msg-text-file {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: rgba(99, 102, 241, 0.12);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 8px;
}
.chat-msg-text-file:hover {
    background: rgba(99, 102, 241, 0.2);
    transform: translateY(-1px);
}
.chat-msg-text-file svg {
    flex-shrink: 0;
}
.chat-msg-audio-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 2px;
    min-width: 210px;
    max-width: 280px;
}
.chat-msg-audio-play {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--chat-color);
    border: none;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.15s, opacity 0.15s;
}
.chat-msg-audio-wrap[data-own="1"] .chat-msg-audio-play {
    background: rgba(255,255,255,0.22);
    border: 1.5px solid rgba(255,255,255,0.45);
}
.chat-msg-audio-play:hover { transform: scale(1.1); opacity: 0.88; }
.chat-msg-audio-waveform {
    flex: 1;
    height: 36px;
    cursor: pointer;
    display: block;
}
.chat-msg-audio-time {
    flex-shrink: 0;
    font-size: 11px;
    opacity: 0.75;
    font-variant-numeric: tabular-nums;
    min-width: 30px;
    text-align: right;
}
.chat-msg-code-block {
    margin: 8px 0;
    border-radius: 8px;
    overflow: hidden;
    background: rgba(0, 0, 0, 0.6);
}
.chat-msg-code-header {
    padding: 6px 12px;
    background: rgba(0, 0, 0, 0.3);
    font-size: 11px;
    color: rgba(255, 255, 255, 0.6);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.chat-msg-code-copy {
    padding: 4px 8px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 4px;
    color: rgba(255, 255, 255, 0.8);
    cursor: pointer;
    font-size: 11px;
}
.chat-msg-code-copy:hover {
    background: rgba(255, 255, 255, 0.2);
}
.chat-msg-code-content {
    padding: 12px;
    overflow-x: auto;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.5;
    color: #e5e7eb;
}
.chat-msg-code-content code {
    background: none;
    padding: 0;
    border-radius: 0;
}
.chat-msg-inline-code {
    background: rgba(0, 0, 0, 0.2);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 0.9em;
}
.chat-msg-quote {
    border-left: 3px solid var(--chat-color);
    padding-left: 12px;
    margin: 8px 0;
    opacity: 0.8;
    font-style: italic;
}
</style>

<?php if(isset($domainSaved)&&$domainSaved):?><script>document.addEventListener('DOMContentLoaded',function(){if(typeof showTempNotification==='function'){showTempNotification(<?php echo json_encode($translations['domain_saved']); ?>,'success');}});</script><?php endif;?>
<?php if(isset($domainError)&&$domainError):?><script>document.addEventListener('DOMContentLoaded',function(){if(typeof showTempNotification==='function'){showTempNotification(<?php echo json_encode($translations['domain_save_error']); ?>,'error');}});</script><?php endif;?>
<script>
var T=<?php echo json_encode([
    'loading_btn'=>$translations['loading_btn']??'Loading...',
    'logging_out'=>$translations['logging_out']??'Logging out...',
    'add_trusted_device'=>$translations['add_trusted_device']??'Add This Device',
    'add_trusted_device_confirm'=>$translations['add_trusted_device_confirm']??'Add this device to your trusted list?',
    'device_added_trusted'=>$translations['device_added_trusted']??'Device added to trusted list',
    'device_removed_trusted'=>$translations['device_removed_trusted']??'Device removed from trusted list',
    'trusted_device_restore_failed'=>$translations['trusted_device_restore_failed']??'Saved session on this device could not be restored. Sign in again to renew it.',
    'trusted_device_storage_failed'=>$translations['trusted_device_storage_failed']??'The browser did not fully save the remembered-device state. Automatic restore on this PC may not work until storage/cookies are allowed.',
    'cookie_consent_title'=>$translations['cookie_consent_title']??'Cookie preferences',
    'cookie_consent_message'=>$translations['cookie_consent_message']??'We use cookies to keep your session active, remember this device, save settings, and improve security. Please choose whether to allow these cookies on this site.',
    'cookie_consent_accept'=>$translations['cookie_consent_accept']??'Accept',
    'cookie_consent_decline'=>$translations['cookie_consent_decline']??'Decline',
    'cookie_consent_saved'=>$translations['cookie_consent_saved']??'Cookie preference saved',
    'remove_trusted_device'=>$translations['remove_trusted_device']??'Remove from Trusted',
    'no_trusted_devices'=>$translations['no_trusted_devices']??'No trusted devices',
    'trusted_ip'=>$translations['trusted_ip']??'Trusted IP',
    'device_name'=>$translations['device_name']??'Device Name',
    'trusted_since'=>$translations['trusted_since']??'Trusted Since',
    'trusted_last_seen'=>$translations['trusted_last_seen']??'Last Seen',
    'delete_user_confirm'=>$translations['delete_user_confirm']??'Are you sure?',
    'network_error'=>$translations['network_error']??'Network error',
    'session_refreshed'=>$translations['fm_session_refreshed']??'Sessions list updated',
    'cancel'=>$translations['cancel']??'Cancel',
    'confirm_label'=>$translations['save']??'Confirm',
    'new_device_detected'=>$translations['new_device_detected']??'New Device Detected',
    'new_device_notification'=>$translations['new_device_notification']??'This appears to be a new device. Would you like to add it to your trusted devices?',
    'add_device_trusted'=>$translations['add_device_trusted']??'Add This Device',
    'device_now_trusted'=>$translations['device_now_trusted']??'This device is now trusted — you won\'t be auto-logged out.',
    'support_chat_title'=>$translations['support_chat_title']??'Chat with Support',
    'support_chat_admin_title'=>$translations['support_chat_admin_title']??'Support Chat Management',
    'support_chat_placeholder'=>$translations['support_chat_placeholder']??'Type your message...',
    'support_chat_send'=>$translations['support_chat_send']??'Send',
    'support_chat_no_messages'=>$translations['support_chat_no_messages']??'No messages yet',
    'support_chat_deleted'=>$translations['support_chat_deleted']??'Message deleted',
    'support_chat_select_user'=>$translations['support_chat_select_user']??'Select a user to view their chat',
    'support_chat_no_chats'=>$translations['support_chat_no_chats']??'No active chats',
    'support_chat_file_too_large'=>$translations['support_chat_file_too_large']??'File size must not exceed 20MB',
    'support_chat_unsupported_type'=>$translations['support_chat_unsupported_type']??'Only images and videos are allowed',
    'support_chat_new_title'=>$translations['support_chat_new_title']??'New chat',
    'support_chat_search_placeholder'=>$translations['support_chat_search_placeholder']??'Search by name or email…',
    'support_chat_no_search_results'=>$translations['support_chat_no_search_results']??'No users found',
    'support_chat_cannot_admin'=>$translations['support_chat_cannot_admin']??'Cannot start chat with admin users',
    'support_chat_delete_confirm'=>$translations['support_chat_delete_confirm']??'Delete this entire chat? This cannot be undone.',
    'support_chat_delete_modal_title'=>$translations['support_chat_delete_modal_title']??'Delete this chat?',
    'support_chat_delete_modal_body'=>$translations['support_chat_delete_modal_body']??'All messages in this conversation will be permanently removed.',
    'support_chat_delete_btn'=>$translations['support_chat_delete_btn']??'Delete permanently',
    'support_chat_deleted_ok'=>$translations['support_chat_deleted_ok']??'Chat deleted',
    'support_chat_msg_empty'=>$translations['support_chat_msg_empty']??'Message cannot be empty',
    'support_chat_loading'=>$translations['support_chat_loading']??'Loading...',
    'support_chat_back_list'=>$translations['support_chat_back_list']??'Back to chats',
    'support_chat_editor_crop'=>$translations['support_chat_editor_crop']??'Crop',
    'support_chat_editor_arrow'=>$translations['support_chat_editor_arrow']??'Arrow',
    'support_chat_editor_draw'=>$translations['support_chat_editor_draw']??'Draw',
    'support_chat_editor_clear'=>$translations['support_chat_editor_clear']??'Clear marks',
    'support_chat_editor_apply_crop'=>$translations['support_chat_editor_apply_crop']??'Apply crop',
    'support_chat_editor_send'=>$translations['support_chat_editor_send']??'Send',
    'support_chat_editor_finish_crop'=>$translations['support_chat_editor_finish_crop']??'Apply or cancel crop first',
    'delete_this_command'=>$translations['delete_this_command']??'Delete this command?',
    'qr_library_not_loaded'=>$translations['qr_library_not_loaded']??'QR Code library not loaded',
    'qr_refresh_page'=>$translations['qr_refresh_page']??'Please refresh the page',
    'qr_generation_failed'=>$translations['qr_generation_failed']??'Failed to generate QR code',
    'qr_generation_failed_detail'=>$translations['qr_generation_failed_detail']??'QR code generation failed',
    'browser_no_audio_recording'=>$translations['browser_no_audio_recording']??'Your browser does not support audio recording',
    'unknown_error'=>$translations['unknown_error']??'Unknown error',
    'failed_to_load_file'=>$translations['failed_to_load_file']??'Failed to load file:',
    'recording_release_to_send'=>$translations['recording_release_to_send']??'Recording... Release to send',
    'weather_loading'=>$translations['weather_loading']??'Loading weather...',
    'weather_denied'=>$translations['weather_denied']??'Location access denied. Enable it to see local weather.',
    'weather_denied_msg'=>$translations['weather_denied_msg']??'Please allow location access in your browser settings to see local weather',
    'weather_allow_location'=>$translations['weather_allow_location']??'Allow location',
    'weather_no_geo'=>$translations['weather_no_geo']??'Geolocation not supported',
    'weather_settings_hint'=>$translations['weather_settings_hint']??'Go to browser site settings and allow location',
    'weather_forecast_title'=>$translations['weather_forecast_title']??'7-day forecast',
    'weather_chart_title'=>$translations['weather_chart_title']??'Temperature trend',
    'weather_high'=>$translations['weather_high']??'High',
    'weather_low'=>$translations['weather_low']??'Low',
    'weather_today'=>$translations['weather_today']??'Today',
    'weather_tomorrow'=>$translations['weather_tomorrow']??'Tomorrow',
    'weather_wind'=>$translations['weather_wind']??'Wind',
    'weather_precip'=>$translations['weather_precip']??'Precipitation',
    'weather_precip_mm'=>$translations['weather_precip_mm']??'%s mm',
    'weather_precip_none'=>$translations['weather_precip_none']??'No rain',
    'weather_loading_forecast'=>$translations['weather_loading_forecast']??'Loading forecast...',
    'weather_no_forecast'=>$translations['weather_no_forecast']??'No forecast data available',
    'weather_fetch_forecast_error'=>$translations['weather_fetch_forecast_error']??'Failed to load forecast',
    'weather_click_forecast'=>$translations['weather_click_forecast']??'Click to see 7-day forecast',
    'weather_desc_clear'=>$translations['weather_desc_clear']??'Clear',
    'weather_desc_mainly_clear'=>$translations['weather_desc_mainly_clear']??'Mainly clear',
    'weather_desc_partly_cloudy'=>$translations['weather_desc_partly_cloudy']??'Partly cloudy',
    'weather_desc_overcast'=>$translations['weather_desc_overcast']??'Overcast',
    'weather_desc_fog'=>$translations['weather_desc_fog']??'Fog',
    'weather_desc_drizzle'=>$translations['weather_desc_drizzle']??'Drizzle',
    'weather_desc_rain'=>$translations['weather_desc_rain']??'Rain',
    'weather_desc_snow'=>$translations['weather_desc_snow']??'Snow',
    'weather_desc_showers'=>$translations['weather_desc_showers']??'Rain showers',
    'weather_desc_thunderstorm'=>$translations['weather_desc_thunderstorm']??'Thunderstorm',
    'weather_desc_cloudy'=>$translations['weather_desc_cloudy']??'Cloudy',
    'weather_weekday_0'=>$translations['weather_weekday_0']??'Sunday',
    'weather_weekday_1'=>$translations['weather_weekday_1']??'Monday',
    'weather_weekday_2'=>$translations['weather_weekday_2']??'Tuesday',
    'weather_weekday_3'=>$translations['weather_weekday_3']??'Wednesday',
    'weather_weekday_4'=>$translations['weather_weekday_4']??'Thursday',
    'weather_weekday_5'=>$translations['weather_weekday_5']??'Friday',
    'weather_weekday_6'=>$translations['weather_weekday_6']??'Saturday',
    'weather_wind_chart_title'=>$translations['weather_wind_chart_title']??'Wind speed',
    'weather_wind_chart_btn'=>$translations['weather_wind_chart_btn']??'Show wind chart',
    'weather_wind_chart_back'=>$translations['weather_wind_chart_back']??'Back to forecast',
    'weather_wind_speed_unit'=>$translations['weather_wind_speed_unit']??'km/h',
]); ?>;
    function openPolicyModal(url){
        let overlay = document.getElementById('policy-modal-overlay');
        if(!overlay){
            overlay = document.createElement('div');
            overlay.id = 'policy-modal-overlay';
            overlay.innerHTML = '<div id="policy-modal" style="position:relative;background:var(--modal-bg);"><button id="policy-modal-close" type="button">&times;</button><iframe src="'+url+'"></iframe></div>';
            document.body.appendChild(overlay);
            overlay.addEventListener('click', e => { if(e.target === overlay) closePolicyModal(); });
            overlay.querySelector('#policy-modal-close').addEventListener('click', closePolicyModal);
        } else {
            overlay.querySelector('iframe').src = url;
        }
        overlay.classList.add('active');
    }
    function closePolicyModal(){
        const overlay = document.getElementById('policy-modal-overlay');
        if(overlay){ overlay.classList.remove('active'); }
    }
    document.addEventListener('DOMContentLoaded',function(){
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const browserTheme = prefersDark ? 'dark' : 'light';
    document.cookie = "browser_theme=" + browserTheme + "; path=/; max-age=86400";
        applyThemeRuntime(<?php echo $showLogin ? ($themeFromUrl ? json_encode($config['theme']) : 'browserTheme') : 'getSelectedThemeValue()'; ?>);

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',applyAutoTheme);
        showNotification();

        <?php if(!$showLogin): ?>
        const trustedPromptKey='trusted_device_prompt_due';
        function scheduleTrustedPrompt(delayMs){
            const due=Date.now()+Math.max(0,delayMs);
            try{localStorage.setItem(trustedPromptKey,String(due));}catch(e){}
            setTimeout(function(){
                try{localStorage.removeItem(trustedPromptKey);}catch(e){}
                showNewDeviceNotification();
            },Math.max(0,delayMs));
        }
        <?php if($newDeviceLoginNotificationDelayMs !== null): ?>
        scheduleTrustedPrompt(<?php echo (int)$newDeviceLoginNotificationDelayMs; ?>);
        <?php else: ?>
        try{
            const storedTrustedPromptDue=parseInt(localStorage.getItem(trustedPromptKey)||'0',10);
            if(storedTrustedPromptDue>0){
                scheduleTrustedPrompt(storedTrustedPromptDue-Date.now());
            }
        }catch(e){}
        <?php endif; ?>
        <?php endif; ?>

        const menuButton=document.getElementById('menu-button');
        const sidebar=document.getElementById('sidebar');
        function bindAppModalMenuLayer(){
            const iframe=document.getElementById('app-iframe');
            if(!iframe||!menuButton)return;
            let observer=null;
            const selectors='dialog[open],[aria-modal="true"],[role="dialog"],.modal,.modal-overlay,.overlay,.lightbox,[id*="Modal"],[id*="modal"],[class*="Modal"],[class*="modal"],[id*="Overlay"],[id*="overlay"],[class*="Overlay"],[class*="overlay"],[id*="Lightbox"],[id*="lightbox"],[class*="Lightbox"],[class*="lightbox"]';
            const contentSelectors='.modal-content,[class*="modal-content"],[class*="ModalContent"],[class*="dialog-content"],[class*="DialogContent"],[class*="lightbox-inner"],[class*="LightboxInner"],[role="document"]';
            function isVisibleLayer(el,win){
                if(!el||el===el.ownerDocument.body||el===el.ownerDocument.documentElement)return false;
                if(el.getAttribute('aria-hidden')==='true')return false;
                const cs=win.getComputedStyle(el);
                if(cs.display==='none'||cs.visibility==='hidden'||Number(cs.opacity)===0)return false;
                const rect=el.getBoundingClientRect();
                if(rect.width<2||rect.height<2)return false;
                if(rect.bottom<=0||rect.right<=0||rect.top>=win.innerHeight||rect.left>=win.innerWidth)return false;
                return true;
            }
            function rectsOverlap(a,b){
                return a.left<b.right&&a.right>b.left&&a.top<b.bottom&&a.bottom>b.top;
            }
            function layerRects(el,win){
                const rect=el.getBoundingClientRect();
                const isBackdrop=rect.width>=win.innerWidth*0.8&&rect.height>=win.innerHeight*0.8;
                if(isBackdrop){
                    const content=Array.from(el.querySelectorAll(contentSelectors)).filter(child=>isVisibleLayer(child,win));
                    if(content.length)return content.map(child=>child.getBoundingClientRect());
                    if(el.matches('dialog[open]'))return [rect];
                    return [];
                }
                return [rect];
            }
            function sync(){
                let hasOverlap=false;
                try{
                    const doc=iframe.contentDocument||iframe.contentWindow.document;
                    const win=iframe.contentWindow;
                    const iframeRect=iframe.getBoundingClientRect();
                    const menuRect=menuButton.getBoundingClientRect();
                    hasOverlap=Array.from(doc.querySelectorAll(selectors)).some(el=>{
                        if(!isVisibleLayer(el,win))return false;
                        return layerRects(el,win).some(rect=>rectsOverlap(menuRect,{
                            left:iframeRect.left+rect.left,
                            right:iframeRect.left+rect.right,
                            top:iframeRect.top+rect.top,
                            bottom:iframeRect.top+rect.bottom
                        }));
                    });
                }catch(e){}
                document.body.classList.toggle('app-modal-open',hasOverlap);
                if(hasOverlap&&sidebar&&sidebar.classList.contains('open'))closeSidebarMenu();
            }
            function scheduleSync(){
                sync();
            }
            function attach(){
                if(observer)observer.disconnect();
                document.body.classList.remove('app-modal-open');
                try{
                    const doc=iframe.contentDocument||iframe.contentWindow.document;
                    observer=new MutationObserver(scheduleSync);
                    observer.observe(doc.documentElement,{attributes:true,childList:true,subtree:true,attributeFilter:['class','style','open','aria-hidden','aria-modal','role']});
                    sync();
                }catch(e){}
            }
            iframe.addEventListener('load',attach);
            attach();
        }
        bindAppModalMenuLayer();
        const settingsButton=document.getElementById('settings-button');
        const settingsModal=document.getElementById('settings-modal');
        const searchBar=document.getElementById('search-bar');
        const clearSearch=document.getElementById('clear-search');
        const appList=document.getElementById('app-list');
        const appItems=appList?appList.querySelectorAll('li'):[];
        const closeSettings=document.getElementById('close-settings');
        let tooltipTimeout;
        let activeTooltip = null;
        const authModal=document.getElementById('auth-modal');
        const authTabs=document.querySelectorAll('.auth-tab');
        const authContents=document.querySelectorAll('.auth-content');
        const showForgotLink=document.getElementById('show-forgot');
        const qrButton=document.getElementById('qr-button');
        const qrModal=document.getElementById('qr-modal');
        const closeQr=document.getElementById('close-qr');
        const qrCodeElement=document.getElementById('qr-code');
        const currentDomainUrl=document.getElementById('current-domain-url');
        const copyDomainBtn=document.getElementById('copy-domain-btn');
        const emailButton=document.getElementById('email-button');
        const emailModal=document.getElementById('email-modal');
        const closeEmail=document.getElementById('close-email');
        const usersButton=document.getElementById('users-button');
        const usersModal=document.getElementById('users-modal');
        const closeUsers=document.getElementById('close-users');
        const usersSearch=document.getElementById('users-search');
        const usersClearSearch=document.getElementById('users-clear-search');
        const userRows=document.querySelectorAll('.users-table tbody tr');
        const sessionsButton=document.getElementById('sessions-button');
        const sessionsModal=document.getElementById('sessions-modal');
        const closeSessions=document.getElementById('close-sessions');
        const userProfile=document.getElementById('user-profile');
        const profileModal=document.getElementById('profile-modal');
        const closeProfile=document.getElementById('close-profile');
        const shortcutsHelpButton=document.getElementById('shortcuts-help-button');
        const shortcutsModal=document.getElementById('shortcuts-modal');
        const closeShortcuts=document.getElementById('close-shortcuts');
        const showPasswordForm=document.getElementById('show-password-form');
        const passwordForm=document.getElementById('password-form');
        const passwordChangeBox=document.getElementById('password-change-box');
        const verifyCurrentPasswordBtn=document.getElementById('verify-current-password');
        const passwordNewStep=document.getElementById('password-new-step');
        const generateChangePasswordBtn=document.getElementById('generate-change-password');
        const currentPasswordInput=document.getElementById('current-password');
        const currentPasswordMsg=document.getElementById('current-password-msg');
        let currentPasswordVerified=false;
        const showPhotoForm=document.getElementById('show-photo-form');
        const photoForm=document.getElementById('photo-form');
        const resetPhotoForm=document.getElementById('reset-photo-form');
        const sharedFilesSearch=document.getElementById('shared-files-search');
        const sharedFilesClearSearch=document.getElementById('shared-files-clear-search');
        const sharedFilesTable=document.getElementById('shared-files-table');
        const deleteFilteredBtn = document.getElementById('delete-filtered-files');

        let currentStatsData = <?php echo json_encode($visitStats); ?>;
        let currentFilteredFileIds = [];
        const isHomeDashboard = <?php echo $isHomeDashboard ? 'true' : 'false'; ?>;
        const isAdminUser = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        const appGrid=document.getElementById('app-grid');
        const searchContainer=document.querySelector('.search-container');
        let selectedAppTileIndex = -1;

        function sanitizeAppSearchValue(value) {
            return String(value || '')
                .replace(/[^\p{L}\p{N}\s]/gu, '')
                .replace(/\s+/g, ' ')
                .trimStart();
        }
        function clearAppSearch(keepFocus) {
            if(!searchBar) return;
            searchBar.value = '';
            searchBar.dispatchEvent(new Event('input', { bubbles: true }));
            if(clearSearch) clearSearch.style.display = 'none';
            if(!keepFocus && document.activeElement === searchBar){
                searchBar.blur();
            }
        }
        function syncAppPageChatButton(){
            const btn=document.querySelector('#support-chat-btn.app-page-chat');
            if(!btn) return;
            const hidden=(searchContainer&&(searchContainer.classList.contains('open')||searchContainer.matches(':hover')))||(searchBar&&searchBar.value.trim()!=='');
            btn.style.display=hidden?'none':'flex';
        }
        function closeSidebarMenu() {
            if(!sidebar) return false;
            const wasOpen = sidebar.classList.contains('open');
            sidebar.classList.remove('open');
            clearAppSearch(false);
            syncAppPageChatButton();
            return wasOpen;
        }
        function getAppTileLinks() {
            return appGrid ? Array.from(appGrid.querySelectorAll('.app-tile-link')) : [];
        }
        function setSelectedAppTile(index) {
            const tiles = getAppTileLinks();
            tiles.forEach((tile, tileIndex) => {
                tile.classList.toggle('app-tile-key-selected', tileIndex === index);
            });
            if(index >= 0 && tiles[index]){
                selectedAppTileIndex = index;
                tiles[index].scrollIntoView({block:'nearest', inline:'nearest'});
            } else {
                selectedAppTileIndex = -1;
            }
        }
        function pickNextTileIndex(direction) {
            const tiles = getAppTileLinks();
            if(!tiles.length) return -1;
            if(selectedAppTileIndex < 0 || !tiles[selectedAppTileIndex]) return 0;
            const currentRect = tiles[selectedAppTileIndex].getBoundingClientRect();
            const currentCx = currentRect.left + currentRect.width / 2;
            const currentCy = currentRect.top + currentRect.height / 2;
            let bestIndex = selectedAppTileIndex;
            let bestScore = Infinity;
            tiles.forEach((tile, index) => {
                if(index === selectedAppTileIndex) return;
                const rect = tile.getBoundingClientRect();
                const cx = rect.left + rect.width / 2;
                const cy = rect.top + rect.height / 2;
                const dx = cx - currentCx;
                const dy = cy - currentCy;
                if(direction === 'left' && dx >= -8) return;
                if(direction === 'right' && dx <= 8) return;
                if(direction === 'up' && dy >= -8) return;
                if(direction === 'down' && dy <= 8) return;
                const primary = (direction === 'left' || direction === 'right') ? Math.abs(dx) : Math.abs(dy);
                const secondary = (direction === 'left' || direction === 'right') ? Math.abs(dy) : Math.abs(dx);
                const score = primary * 1000 + secondary;
                if(score < bestScore){
                    bestScore = score;
                    bestIndex = index;
                }
            });
            return bestIndex;
        }

        function showTooltip(element, text) {
            if(activeTooltip) activeTooltip.style.display = 'none';
            const rect = element.getBoundingClientRect();
            const tooltip = document.querySelector(`#${element.id}-tooltip`) || (() => {
                const t = document.createElement('span');
                t.className = 'tooltip';
                t.id = element.id + '-tooltip';
                t.textContent = text;
                document.body.appendChild(t);
                return t;
            })();
            tooltip.textContent = text;
            tooltip.style.display = 'block';
            tooltip.style.left = rect.left + (rect.width / 2) + 'px';
            tooltip.style.top = rect.top - 30 + 'px';
            activeTooltip = tooltip;
        }

        function hideTooltip() {
            if(activeTooltip) {
                activeTooltip.style.display = 'none';
                activeTooltip = null;
            }
        }

        document.querySelectorAll('.sidebar-button').forEach(btn => {
            btn.addEventListener('mouseenter', (e) => {
                const text = btn.getAttribute('data-tooltip') || btn.getAttribute('title');
                if(text) showTooltip(btn, text);
            });
            btn.addEventListener('mouseleave', hideTooltip);
        });

        if(searchBar){
            searchBar.addEventListener('beforeinput', function(e){
                if(!e.data || e.inputType && !e.inputType.startsWith('insert')) return;
                if(/[^\p{L}\p{N}\s]/u.test(e.data)) e.preventDefault();
            });
            searchBar.addEventListener('input', function(){
                const sanitized = sanitizeAppSearchValue(this.value);
                if(this.value !== sanitized){
                    const prevLen = this.value.length;
                    const nextLen = sanitized.length;
                    this.value = sanitized;
                    try {
                        const pos = Math.max(0, nextLen - Math.max(0, prevLen - (this.selectionStart || prevLen)));
                        this.setSelectionRange(pos, pos);
                    } catch(e) {}
                }
            });
            searchBar.addEventListener('paste', function(e){
                const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
                const sanitized = sanitizeAppSearchValue(text);
                if(text === sanitized) return;
                e.preventDefault();
                const start = this.selectionStart || 0;
                const end = this.selectionEnd || 0;
                const nextValue = this.value.slice(0, start) + sanitized + this.value.slice(end);
                this.value = sanitizeAppSearchValue(nextValue);
                const pos = start + sanitized.length;
                try { this.setSelectionRange(pos, pos); } catch(err) {}
                this.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }

        // Settings box tooltips — move to body to escape backdrop-filter stacking context
        document.querySelectorAll('.settings-button-wrapper').forEach(wrapper => {
            const tooltip = wrapper.querySelector('.tooltip');
            const btn = wrapper.querySelector('button');
            if(!tooltip || !btn) return;
            document.body.appendChild(tooltip);
            btn.addEventListener('mouseenter', () => {
                const rect = btn.getBoundingClientRect();
                tooltip.style.display = 'block';
                tooltip.style.position = 'fixed';
                tooltip.style.left = (rect.left + rect.width/2) + 'px';
                tooltip.style.top = (rect.top - 36) + 'px';
                tooltip.style.transform = 'translateX(-50%)';
                tooltip.style.zIndex = '2147483647';
                tooltip.style.pointerEvents = 'none';
                tooltip.style.whiteSpace = 'nowrap';
            });
            btn.addEventListener('mouseleave', () => {
                tooltip.style.display = 'none';
            });
        });

        if(usersSearch && usersClearSearch && userRows.length > 0) {
            usersSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                usersClearSearch.style.display = query ? 'flex' : 'none';

                userRows.forEach(row => {
                    const email = row.getAttribute('data-email').toLowerCase();
                    const name = row.getAttribute('data-name').toLowerCase();
                    if(email.includes(query) || name.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            usersClearSearch.addEventListener('click', function() {
                usersSearch.value = '';
                usersSearch.dispatchEvent(new Event('input'));
                usersClearSearch.style.display = 'none';
            });
        }

        if(sharedFilesSearch && sharedFilesClearSearch && sharedFilesTable) {
            sharedFilesSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                sharedFilesClearSearch.style.display = query ? 'flex' : 'none';

                const rows = sharedFilesTable.querySelectorAll('tbody tr');
                currentFilteredFileIds = [];

                rows.forEach(row => {
                    const fileName = row.querySelector('.file-name')?.textContent.toLowerCase() || '';
                    if(fileName.includes(query)) {
                        row.style.display = '';
                        const shareId = row.getAttribute('data-shareid');
                        if(shareId) currentFilteredFileIds.push(shareId);
                    } else {
                        row.style.display = 'none';
                    }
                });

                if(deleteFilteredBtn) {
                    if(query && currentFilteredFileIds.length > 0) {
                        deleteFilteredBtn.style.display = 'inline-flex';
                        deleteFilteredBtn.textContent = `Delete ${currentFilteredFileIds.length} file(s)`;
                    } else {
                        deleteFilteredBtn.style.display = 'none';
                    }
                }
            });

            sharedFilesClearSearch.addEventListener('click', function() {
                sharedFilesSearch.value = '';
                sharedFilesSearch.dispatchEvent(new Event('input'));
            });

            if(deleteFilteredBtn) {
                deleteFilteredBtn.addEventListener('click', function() {
                    if(currentFilteredFileIds.length === 0) return;
                    sfConfirm('Are you sure you want to delete ' + currentFilteredFileIds.length + ' file(s)?', ()=>{
                        const formData = new FormData();
                        formData.append('delete_filtered_files', '1');
                        formData.append('ajax', '1');
                        currentFilteredFileIds.forEach(id=>formData.append('file_ids[]',id));
                        fetch('/main', {method:'POST',body:formData}).then(r=>r.json()).then(data=>{
                            if(data.success){ showTempNotification(data.message,'success'); renderSharedFilesTable(data.files||{}); }
                            else { showTempNotification(T.error_deleting,'error'); }
                        }).catch(()=>showTempNotification('Network error','error'));
                    });
                });
            }
        }

        if(showPhotoForm) {
            showPhotoForm.addEventListener('click', function(){
                if(photoForm) photoForm.classList.toggle('active');
                if(resetPhotoForm) resetPhotoForm.classList.toggle('active');
            });
        }

        function updateThemeBasedOnSystem(){const isDarkMode=window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.setAttribute('data-theme',isDarkMode?'dark':'light');}
        function applyThemeToAuthModal(theme){const authModal=document.getElementById('auth-modal');if(!authModal)return;const gradientBg=authModal.querySelector('.gradient-bg');if(gradientBg){gradientBg.style.background='';}}
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',updateThemeBasedOnSystem);
        if(document.querySelector('select[name="theme"] option[value="auto"]:checked')){updateThemeBasedOnSystem();}
        function getSelectedThemeValue(){const themeInput=document.getElementById('theme-input');const themeSelect=document.querySelector('select[name="theme"]');return themeInput?themeInput.value:(themeSelect?themeSelect.value:'light');}
        function resolveTheme(theme){return theme==='auto'?(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):theme;}
        function updateEdgeAppShellTheme(theme){const content=document.getElementById('content');if(!content)return;if(content.classList.contains('app-edge-gitea')){content.style.setProperty('--app-edge-bg',theme==='dark'?'#1e1f20':'#f6f8fa');content.style.setProperty('--app-edge-border',theme==='dark'?'#30363d':'#d0d7de');content.style.setProperty('--app-edge-text',theme==='dark'?'#f0f6fc':'#24292f');}else if(content.classList.contains('app-edge-immich')){content.style.setProperty('--app-edge-bg',theme==='dark'?'#000000':'#ffffff');content.style.setProperty('--app-edge-border',theme==='dark'?'#1f2937':'#e5e7eb');content.style.setProperty('--app-edge-text',theme==='dark'?'#f9fafb':'#111827');}else if(content.classList.contains('app-edge-plane')){content.style.setProperty('--app-edge-bg',theme==='dark'?'#0c0d0e':'#ffffff');content.style.setProperty('--app-edge-border',theme==='dark'?'#1e293b':'#e5e7eb');content.style.setProperty('--app-edge-text',theme==='dark'?'#f8fafc':'#111827');}else if(content.classList.contains('app-edge-dawarich')){content.style.setProperty('--app-edge-bg',theme==='dark'?'#1a1a1a':'#ffffff');content.style.setProperty('--app-edge-border',theme==='dark'?'#1e293b':'#e5e7eb');content.style.setProperty('--app-edge-text',theme==='dark'?'#f8fafc':'#0f172a');}else if(content.classList.contains('app-edge-x')){content.style.setProperty('--app-edge-bg',theme==='dark'?'#000000':'#ffffff');content.style.setProperty('--app-edge-border',theme==='dark'?'#2f3336':'#cfd9de');content.style.setProperty('--app-edge-text',theme==='dark'?'#e7e9ea':'#0f1419');}}
        function updateOmniRouteEdgeTheme(theme){const root=document.documentElement;if(theme==='dark'){root.style.setProperty('--ap-omniroute-edge-bg','#0b0d10');root.style.setProperty('--ap-omniroute-edge-border','#1b2330');root.style.setProperty('--ap-omniroute-edge-text','#e5edf7');root.style.setProperty('--ap-omniroute-edge-hover','#121823');root.style.setProperty('--ap-omniroute-edge-active','#172033');root.style.setProperty('--ap-omniroute-edge-active-border','#2b3b52');}else{root.style.setProperty('--ap-omniroute-edge-bg','#f8fafc');root.style.setProperty('--ap-omniroute-edge-border','#dbe3ed');root.style.setProperty('--ap-omniroute-edge-text','#111827');root.style.setProperty('--ap-omniroute-edge-hover','#eef3f8');root.style.setProperty('--ap-omniroute-edge-active','#e6edf5');root.style.setProperty('--ap-omniroute-edge-active-border','#c7d3e0');}}
        function applyThemeRuntime(theme){const resolved=resolveTheme(theme);document.body.classList.toggle('dark-theme',resolved==='dark');document.body.classList.toggle('light-theme',resolved!=='dark');document.body.classList.toggle('theme-dark',resolved==='dark');document.body.classList.toggle('theme-light',resolved!=='dark');const authThemeToggle=document.getElementById('authThemeToggle');if(authThemeToggle){authThemeToggle.querySelectorAll('button').forEach(b=>b.classList.toggle('active',b.dataset.theme===resolved));}updateCSSVariables(resolved);applyThemeToAuthModal(resolved);updateAppIframeTheme(resolved);updateEdgeAppShellTheme(resolved);updateOmniRouteEdgeTheme(resolved);if(typeof injectOmniRouteTheme==='function')injectOmniRouteTheme();}
        window.applyThemeRuntime=applyThemeRuntime;
        function detectAndApplySystemTheme(){if(getSelectedThemeValue()==='auto')applyThemeRuntime('auto');}
        function updateCSSVariables(theme){const root=document.documentElement;if(theme==='dark'){root.style.setProperty('--bg-color','#0f1419');root.style.setProperty('--text-color','#f4f7fb');root.style.setProperty('--sidebar-bg','rgba(16,22,30,0.58)');root.style.setProperty('--group-block-bg','rgba(255,255,255,0.10)');root.style.setProperty('--app-item-bg','rgba(255,255,255,0.12)');root.style.setProperty('--tooltip-bg','rgba(18,22,28,0.82)');root.style.setProperty('--modal-bg','rgba(18,24,32,0.62)');root.style.setProperty('--border-color','rgba(255,255,255,0.14)');root.style.setProperty('--scrollbar-track','rgba(255,255,255,0.04)');root.style.setProperty('--scrollbar-thumb','rgba(255,255,255,0.22)');root.style.setProperty('--scrollbar-thumb-hover','rgba(255,255,255,0.35)');root.style.setProperty('--icon-bg-color','rgba(18,24,32,0.8)');root.style.setProperty('--link-color','#e8eef5');root.style.setProperty('--link-hover-color',getComputedStyle(root).getPropertyValue('--primary-color'));root.style.setProperty('--table-header-bg','rgba(255,255,255,0.08)');root.style.setProperty('--table-row-hover','rgba(255,255,255,0.10)');root.style.setProperty('--app-icon-grad-start','color-mix(in srgb,var(--primary-color) 72%,white)');root.style.setProperty('--app-icon-grad-end','color-mix(in srgb,var(--primary-color) 88%,black)');}else{root.style.setProperty('--bg-color','#e8eef5');root.style.setProperty('--text-color','#1a2332');root.style.setProperty('--sidebar-bg','rgba(255,255,255,0.62)');root.style.setProperty('--group-block-bg','rgba(255,255,255,0.20)');root.style.setProperty('--app-item-bg','rgba(255,255,255,0.20)');root.style.setProperty('--tooltip-bg','rgba(255,255,255,0.88)');root.style.setProperty('--modal-bg','rgba(255,255,255,0.68)');root.style.setProperty('--border-color','rgba(15,30,50,0.14)');root.style.setProperty('--scrollbar-track','rgba(0,0,0,0.04)');root.style.setProperty('--scrollbar-thumb','rgba(0,0,0,0.2)');root.style.setProperty('--scrollbar-thumb-hover','rgba(0,0,0,0.35)');root.style.setProperty('--icon-bg-color','rgba(255,255,255,0.20)');root.style.setProperty('--link-color','#1a2332');root.style.setProperty('--link-hover-color',getComputedStyle(root).getPropertyValue('--primary-color'));root.style.setProperty('--table-header-bg','rgba(255,255,255,0.78)');root.style.setProperty('--table-row-hover','rgba(0,0,0,0.05)');root.style.setProperty('--app-icon-grad-start','color-mix(in srgb,var(--primary-color) 84%,black)');root.style.setProperty('--app-icon-grad-end','color-mix(in srgb,var(--primary-color) 65%,black)');}}
        function updateScrollbarStyles(theme){/* handled by CSS variables updated in updateCSSVariables */}        function applyAutoTheme(){if(getSelectedThemeValue()==='auto'){applyThemeRuntime('auto');updateScrollbarStyles(resolveTheme('auto'));}}
        function updateAppIframeTheme(theme){const iframe=document.getElementById('app-iframe');if(iframe){const url=new URL(iframe.src,window.location.href);url.searchParams.set('theme',theme);const newSrc=url.toString();if(newSrc!==iframe.src){iframe.src=newSrc;}}}
        document.querySelectorAll('.password-toggle').forEach(button=>{button.addEventListener('click',function(){const targetId=this.getAttribute('data-target');const input=document.getElementById(targetId);const icon=this.querySelector('svg');if(input){if(input.type==='password'){input.type='text';icon.innerHTML='<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';}else{input.type='password';icon.innerHTML='<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';}}});});
        document.addEventListener('keydown',function(e){if(e.key==='ArrowDown'||e.key==='ArrowUp'){var ae=document.activeElement;if(!ae||ae.tagName!=='INPUT')return;var form=ae.closest('form');if(!form)return;var inputs=Array.from(form.querySelectorAll('input.auth-input[type="email"],input.auth-input[type="password"],input.auth-input[type="text"]:not([type="hidden"])'));var idx=inputs.indexOf(ae);if(idx===-1)return;var next=e.key==='ArrowDown'?idx+1:idx-1;if(next>=0&&next<inputs.length){e.preventDefault();inputs[next].focus();}}});
        const refreshSessionsButton=document.getElementById('refresh-sessions');
        if(refreshSessionsButton){refreshSessionsButton.addEventListener('click',function(){refreshSessionsList();});}
        // ── Trusted Devices JS ──
        const BROWSER_DEVICE_ID_COOKIE = 'ap_device_id';
        function getCookieValue(name) {
            const prefix = name + '=';
            const parts = document.cookie ? document.cookie.split('; ') : [];
            for (let i = 0; i < parts.length; i++) {
                if (parts[i].indexOf(prefix) === 0) return decodeURIComponent(parts[i].slice(prefix.length));
            }
            return '';
        }
        function generateBrowserDeviceId() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID().replace(/-/g, '').toLowerCase();
            }
            const bytes = new Uint8Array(16);
            if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
                window.crypto.getRandomValues(bytes);
                return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
            }
            return (Date.now().toString(16) + Math.random().toString(16).slice(2)).padEnd(32, '0').slice(0, 32);
        }
        function writeCookie(name, value, maxAgeSeconds) {
            document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAgeSeconds + '; samesite=Lax';
        }
        function ensureBrowserDeviceId() {
            let value = getCookieValue(BROWSER_DEVICE_ID_COOKIE);
            if (!/^[a-f0-9]{32,128}$/i.test(value || '')) {
                value = generateBrowserDeviceId();
                writeCookie(BROWSER_DEVICE_ID_COOKIE, value, 31536000);
            }
            return value;
        }
        function trustedDeviceRequestHeaders() {
            return {
                'Content-Type': 'application/json',
                'X-Device-Id': ensureBrowserDeviceId()
            };
        }
        function privacyRequestHeaders() {
            return trustedDeviceRequestHeaders();
        }
        function closeCookieConsentModal() {
            const existing = document.getElementById('cookie-consent-overlay');
            if (existing) existing.remove();
        }
        function saveCookieConsent(status) {
            return fetch('/main?api=privacy&action=save_cookie_consent', {
                method: 'POST',
                headers: privacyRequestHeaders(),
                body: JSON.stringify({status: status})
            }).then(r => r.json());
        }
        function showCookieConsentModal() {
            if (document.getElementById('cookie-consent-overlay')) return;
            fetch('/main?api=privacy&action=cookie_consent_prompted', {
                method: 'POST',
                headers: privacyRequestHeaders(),
                body: JSON.stringify({})
            }).catch(() => {});

            const overlay = document.createElement('div');
            overlay.id = 'cookie-consent-overlay';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(6,10,16,0.28);display:flex;align-items:flex-end;justify-content:center;z-index:100002;padding:20px;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);';
            const box = document.createElement('div');
            box.style.cssText = 'width:min(680px,100%);background:var(--modal-bg,#fff);color:var(--text-color,#000);border:1px solid var(--border-color,#ccc);border-radius:16px;padding:18px 18px 16px;box-shadow:var(--glass-shadow,0 20px 60px rgba(0,0,0,0.28));backdrop-filter:blur(var(--glass-blur,32px)) saturate(var(--glass-sat,180%));-webkit-backdrop-filter:blur(var(--glass-blur,32px)) saturate(var(--glass-sat,180%));';
            box.innerHTML =
                '<div style="font-size:16px;font-weight:600;margin-bottom:8px;">' + escHtml(T.cookie_consent_title || 'Cookie preferences') + '</div>' +
                '<div style="font-size:13px;line-height:1.55;opacity:.88;margin-bottom:14px;">' + escHtml(T.cookie_consent_message || 'We use cookies to keep your session active, remember this device, save settings, and improve security. Please choose whether to allow these cookies on this site.') + '</div>' +
                '<div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">' +
                    '<button type="button" id="cookie-consent-decline" style="padding:9px 16px;border:1px solid var(--border-color,#ccc);border-radius:8px;background:transparent;color:var(--text-color,#000);cursor:pointer;">' + escHtml(T.cookie_consent_decline || 'Decline') + '</button>' +
                    '<button type="button" id="cookie-consent-accept" style="padding:9px 16px;border:none;border-radius:8px;background:var(--primary-color,#007bff);color:#fff;cursor:pointer;">' + escHtml(T.cookie_consent_accept || 'Accept') + '</button>' +
                '</div>';
            overlay.appendChild(box);
            document.body.appendChild(overlay);

            const finish = (status) => {
                saveCookieConsent(status)
                    .then((data) => {
                        if (!data || !data.ok) throw new Error((data && data.error) || 'save_failed');
                        closeCookieConsentModal();
                        if (typeof showTempNotification === 'function') {
                            showTempNotification(T.cookie_consent_saved || 'Cookie preference saved', 'success');
                        }
                    })
                    .catch(() => {
                        if (typeof showTempNotification === 'function') {
                            showTempNotification(T.network_error || 'Network error', 'error');
                        }
                    });
            };
            document.getElementById('cookie-consent-accept').addEventListener('click', () => finish('accepted'));
            document.getElementById('cookie-consent-decline').addEventListener('click', () => finish('declined'));
        }
        function scheduleCookieConsentPrompt() {
            const delayMs = 2000 + Math.floor(Math.random() * 5000);
            window.setTimeout(() => {
                fetch('/main?api=privacy&action=cookie_consent_status', {
                    method: 'POST',
                    headers: privacyRequestHeaders(),
                    body: JSON.stringify({})
                })
                .then(r => r.json())
                .then(data => {
                    if (data && data.ok && data.should_prompt) {
                        showCookieConsentModal();
                    }
                })
                .catch(() => {});
            }, delayMs);
        }
        ensureBrowserDeviceId();
        <?php if(!$showLogin): ?>
        scheduleCookieConsentPrompt();
        <?php endif; ?>
        function showTrustedConfirm(message, callback, buttonLabel) {
            var existing = document.getElementById('trusted-confirm-overlay');
            if(existing) existing.remove();
            var overlay = document.createElement('div');
            overlay.id = 'trusted-confirm-overlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);display:flex;justify-content:center;align-items:center;z-index:100001;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);';
            var box = document.createElement('div');
            box.style.cssText = 'background:var(--modal-bg,#fff);color:var(--text-color,#000);padding:30px 25px;border-radius:12px;max-width:400px;width:90%;box-shadow:var(--glass-shadow,0 20px 60px rgba(0,0,0,0.3));border:1px solid var(--border-color,#ccc);backdrop-filter:blur(var(--glass-blur,32px)) saturate(var(--glass-sat,180%));-webkit-backdrop-filter:blur(var(--glass-blur,32px)) saturate(var(--glass-sat,180%));';
            box.innerHTML = '<p style="margin:0 0 20px;font-size:14px;line-height:1.5;">'+message+'</p>'
                +'<div style="display:flex;gap:10px;justify-content:flex-end;">'
                +'<button id="trusted-cancel-btn" style="padding:8px 18px;border:1px solid var(--border-color,#ccc);border-radius:6px;background:var(--sidebar-bg,#eee);color:var(--text-color,#000);cursor:pointer;font-size:13px;">'+(T.cancel||'Cancel')+'</button>'
                +'<button id="trusted-ok-btn" style="padding:8px 18px;border:none;border-radius:6px;background:var(--primary-color,#007bff);color:#fff;cursor:pointer;font-size:13px;">'+(buttonLabel||T.confirm_label||'Confirm')+'</button>'
                +'</div>';
            overlay.appendChild(box);
            document.body.appendChild(overlay);
            document.getElementById('trusted-cancel-btn').onclick = function(){ overlay.remove(); };
            document.getElementById('trusted-ok-btn').onclick = function(){ overlay.remove(); callback(true); };
            overlay.addEventListener('click', function(e){ if(e.target===overlay){ overlay.remove(); } });
        }

        // Simple encryption/decryption for localStorage (uses server key)
        const TRUSTED_DEVICE_STORAGE_KEY = 'trusted_device_token';

        function saveTrustedDeviceToken(encryptedToken) {
            try {
                localStorage.setItem(TRUSTED_DEVICE_STORAGE_KEY, encryptedToken);
                return true;
            } catch(e) {
                console.warn('Could not save trusted device token:', e);
                return false;
            }
        }

        function getTrustedDeviceToken() {
            try {
                return localStorage.getItem(TRUSTED_DEVICE_STORAGE_KEY);
            } catch(e) {
                console.warn('Could not read trusted device token:', e);
                return null;
            }
        }

        function clearTrustedDeviceToken() {
            try {
                localStorage.removeItem(TRUSTED_DEVICE_STORAGE_KEY);
            } catch(e) {
                console.warn('Could not clear trusted device token:', e);
            }
        }
        function persistTrustedDeviceClientState(encryptedToken) {
            if (!encryptedToken) return true;
            const saved = saveTrustedDeviceToken(encryptedToken);
            if (saved) {
                try{localStorage.removeItem(trustedPromptKey);}catch(e){}
            }
            if (!saved) {
                showTempNotification(T.trusted_device_storage_failed || 'The browser did not fully save the remembered-device state. Automatic restore on this PC may not work until storage/cookies are allowed.', 'error');
            }
            return saved;
        }

        function removeTrustedDeviceByToken(encryptedToken, callback) {
            fetch('/main?api=client_auth&action=remove_trusted_device', {
                method: 'POST',
                headers: trustedDeviceRequestHeaders(),
                body: JSON.stringify({token: encryptedToken})
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    clearTrustedDeviceToken();
                    showTempNotification(T.device_removed_trusted || 'Device removed', 'success');
                    if (typeof refreshTrustedDevices === 'function') {
                        refreshTrustedDevices();
                    }
                    if (callback) callback(true, data);
                } else {
                    showTempNotification(data.error || 'Error', 'error');
                    if (callback) callback(false, data);
                }
            })
            .catch(() => {
                showTempNotification(T.network_error || 'Network error', 'error');
                if (callback) callback(false, null);
            });
        }

        function addCurrentDeviceToTrusted(){
            showTrustedConfirm(T.add_trusted_device_confirm||'Add this device to your trusted list? You will stay logged in even after the session timeout.', function(ok){
                if(!ok) return;
                fetch('/main?api=client_auth&action=add_trusted_device',{method:'POST',headers:trustedDeviceRequestHeaders(),body:JSON.stringify({})})
                .then(r=>r.json()).then(data=>{
                    if(data.ok){
                        persistTrustedDeviceClientState(data.encrypted_token || '');
                        showTempNotification(T.device_added_trusted||'Device added','success');
                        refreshTrustedDevices();
                    }
                    else{showTempNotification(data.error||'Error','error');}
                }).catch(()=>showTempNotification(T.network_error||'Network error','error'));
            });
        }
        function bindAddTrustedDeviceButton(){
            const btn=document.getElementById('add-trusted-device-btn');
            if(btn){btn.addEventListener('click',addCurrentDeviceToTrusted);}
        }
        bindAddTrustedDeviceButton();
        const refreshTrustedBtn=document.getElementById('refresh-trusted');
        if(refreshTrustedBtn){refreshTrustedBtn.addEventListener('click',function(){refreshTrustedDevices();});}

        // Use event delegation for remove buttons (fixes the issue with dynamically created buttons)
        document.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.remove-trusted-btn');
            if (removeBtn) {
                const fp = removeBtn.getAttribute('data-fingerprint');
                const token = removeBtn.getAttribute('data-token');
                const name = removeBtn.getAttribute('data-name') || '';
                showTrustedConfirm((T.remove_trusted_device || 'Remove from Trusted') + (name ? ' — ' + name : '') + '?', function(ok) {
                    if (!ok) return;
                    if (token) {
                        removeTrustedDeviceByToken(token, function() { refreshTrustedDevices(); });
                    } else {
                        fetch('/main?api=client_auth&action=remove_trusted_device', {
                            method: 'POST',
                            headers: trustedDeviceRequestHeaders(),
                            body: JSON.stringify({fingerprint: fp})
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.ok) {
                                clearTrustedDeviceToken();
                                showTempNotification(T.device_removed_trusted || 'Device removed', 'success');
                                refreshTrustedDevices();
                            } else { showTempNotification(data.error || 'Error', 'error'); }
                        })
                        .catch(() => showTempNotification(T.network_error || 'Network error', 'error'));
                    }
                }, T.delete_label || 'Delete');
            }
        });

        function refreshTrustedDevices(){
            fetch('/main?api=client_auth&action=list_trusted_devices')
            .then(r=>r.json()).then(data=>{
                if(!data.ok)return;
                const container=document.getElementById('trusted-devices-container');
                if(!container)return;
                const devices=data.devices||[];
                const noTrusted=T.no_trusted_devices||'No trusted devices';
                const addContainer=document.getElementById('add-trusted-device-container');
                if(addContainer){
                    addContainer.innerHTML=data.is_current_trusted?'':'<div style="margin-bottom:15px;"><button type="button" id="add-trusted-device-btn" class="sidebar-button" style="background:#4CAF50;color:white;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;font-size:14px;width:100%;">'+(T.add_trusted_device||'Add This Device')+'</button></div>';
                    bindAddTrustedDeviceButton();
                }
                if(devices.length===0){
                    container.innerHTML='<p id="no-trusted-msg">'+noTrusted+'</p>';
                    return;
                }
                let html='';
                const currentFp=data.current_fingerprint||'';
                html+='<table class="trusted-devices-table"><thead><tr><th>'+(T.trusted_ip||'IP')+'</th><th>'+(T.device_name||'Device Name')+'</th><th>'+(T.trusted_since||'Trusted Since')+'</th><th>'+(T.trusted_last_seen||'Last Seen')+'</th><th></th></tr></thead><tbody>';
                devices.forEach(function(d){
                    const isCurrent=(d.fingerprint||'')=== currentFp;
                    const deviceToken = d.device_token || '';
                    html+='<tr class="'+(isCurrent?'current-trusted-device':'')+'"><td>'+escHtml(d.ip||'—')+'</td><td>'+escHtml(d.device_name||'Unknown')+'</td><td>'+formatDate(d.added_at)+'</td><td>'+formatDate(d.last_seen)+'</td><td><button type="button" class="remove-trusted-btn" data-fingerprint="'+escHtml(d.fingerprint||'')+'" data-token="'+escHtml(deviceToken)+'" data-name="'+escHtml(d.device_name||'')+'">'+(T.remove_trusted_device||'Remove from Trusted')+'</button></td></tr>';
                });
                html+='</tbody></table>';
                container.innerHTML=html;
            }).catch(()=>{});
        }
        function formatDate(ts){if(!ts)return'—';const d=new Date(ts*1000);return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+' '+String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')+':'+String(d.getSeconds()).padStart(2,'0');}
        const SHARE_EXPIRY_OPTIONS = <?php echo json_encode([
            ['never', $translations['share_expiration_never'] ?? 'Never'],
            ['1h', $translations['share_expiration_1h'] ?? '1 hour'],
            ['1d', $translations['share_expiration_1d'] ?? '1 day'],
            ['1w', $translations['share_expiration_1w'] ?? '1 week'],
            ['1m', $translations['share_expiration_1m'] ?? '1 month'],
            ['3m', $translations['share_expiration_3m'] ?? '3 months'],
            ['6m', $translations['share_expiration_6m'] ?? 'Half a year'],
            ['1y', $translations['share_expiration_1y'] ?? '1 year'],
        ], JSON_UNESCAPED_UNICODE); ?>;
        const SHARE_EXPIRY_NEVER_TEXT = <?php echo json_encode($translations['share_expiration_never'] ?? 'Never', JSON_UNESCAPED_UNICODE); ?>;
        function getShareExpiryOptions(){
            return SHARE_EXPIRY_OPTIONS;
        }
        function buildShareExpirySelect(currentValue, shareId){
            const value = currentValue || 'never';
            let html = '<select class="sf-expiry-select" data-shareid="' + shareId + '" style="width:100%;min-width:120px;">';
            getShareExpiryOptions().forEach(function(option){
                const optionValue = option[0];
                const label = option[1];
                html += '<option value="' + optionValue + '"' + (value === optionValue ? ' selected' : '') + '>' + label + '</option>';
            });
            return html + '</select>';
        }
        // Find this block (roughly in the middle of the file where modals are initialized)
        const sharedFilesButton = document.getElementById('shared-files-button');
        const sharedFilesModal = document.getElementById('shared-files-modal');
        const closeSharedFiles = document.getElementById('close-shared-files');
        const refreshSharedFiles = document.getElementById('refresh-shared-files');

        if(sharedFilesButton){
            sharedFilesButton.addEventListener('click',function(){
                closeAllModals();
                if(sharedFilesModal){
                    sharedFilesModal.style.display='flex';
                    loadSharedFilesList();
                }
            });

            if(closeSharedFiles){
                closeSharedFiles.addEventListener('click',function(){
                    if(sharedFilesModal){
                        sharedFilesModal.style.display='none';
                    }
                });
            }

            if(sharedFilesModal){
                sharedFilesModal.addEventListener('click',function(e){
                    if(e.target===sharedFilesModal){
                        sharedFilesModal.style.display='none';
                    }
                });
            }
        }

        if(refreshSharedFiles){
            refreshSharedFiles.addEventListener('click',function(){
                loadSharedFilesList();
            });
        }
        const sharedFilesSort = document.getElementById('shared-files-sort');
        const sharedFilesGroup = document.getElementById('shared-files-group');
        if(sharedFilesSort) sharedFilesSort.addEventListener('change', function(){ loadSharedFilesList(); });
        if(sharedFilesGroup) sharedFilesGroup.addEventListener('change', function(){ loadSharedFilesList(); });

        const SHARED_FILE_DELETE_TEXT = <?php echo json_encode($translations['delete_file'] ?? 'Delete'); ?>;
        function sharedFileText(value){
            return String(value == null ? '' : value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function renderSharedFilesTable(files){
            const container = document.getElementById('shared-files-list-container');
            if(!container) return;
            const sortBy = (document.getElementById('shared-files-sort')||{}).value || 'created_desc';
            const groupBy = (document.getElementById('shared-files-group')||{}).value || 'none';
            if(!files || Object.keys(files).length === 0){
                container.innerHTML = '<p style="text-align:center;opacity:.5;padding:20px 0;"><?php echo addslashes(my_htmlspecialchars($translations['no_shared_files'])); ?></p>';
                return;
            }
            const isRoot = <?php echo json_encode($isRootUser); ?>;
            let rows = '';
            const entries = Object.keys(files).map(function(key){ return [key, files[key]]; }).sort(function(a,b){
                const af = a[1] || {};
                const bf = b[1] || {};
                if (sortBy === 'size_desc') return Number(bf.file_size_bytes||0)-Number(af.file_size_bytes||0);
                if (sortBy === 'size_asc') return Number(af.file_size_bytes||0)-Number(bf.file_size_bytes||0);
                if (sortBy === 'name_asc') return String(af.file_name||'').localeCompare(String(bf.file_name||''));
                return String(bf.created_at||'').localeCompare(String(af.created_at||''));
            });
            let currentGroup = '';
            entries.forEach(function(entry){
                const shareId = entry[0];
                const fileData = entry[1] || {};
                const url = fileData.file_path || '';
                const fname = fileData.file_name || (url ? url.split(/[\\/]/).pop() : '');
                const pathDisplay = url === '' ? '—' : (isRoot ? url : ('…/'+fname));
                const userEmail = fileData.user_email || '';
                const userName = userEmail ? '<span style="font-size:10px;opacity:.6;display:block;">' + sharedFileText(userEmail) + '</span>' : '';
                const expiresAt = fileData.expires ? formatDate(Number(fileData.expires)) : SHARE_EXPIRY_NEVER_TEXT;
                const expiresOption = fileData.expires_option || 'never';
                if (groupBy === 'user' && currentGroup !== userEmail) {
                    currentGroup = userEmail;
                    rows += '<tr><td colspan="5" style="padding:8px 10px;background:var(--table-header-bg);font-size:12px;font-weight:700;">' + sharedFileText(userEmail || 'Unknown user') + '</td></tr>';
                }
rows += '<tr data-filename="' + sharedFileText(fname) + '" data-filepath="' + sharedFileText(url) + '" data-shareid="' + sharedFileText(shareId) + '" style="border-bottom:1px solid var(--border-color);">'
                     + '<td style="padding:8px 10px;font-size:11px;font-family:\'Courier New\',monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;opacity:.65;"><label class="check-label" style="display:flex;gap:8px;align-items:center;"><input class="sf-row-check checkbox" type="checkbox" value="' + sharedFileText(shareId) + '"><span class="checkbox-box"><svg width="14" height="11" viewBox="0 0 24 18" fill="none"><path d="M2 9L8.5 15.5L22 2" stroke="#111113" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg></span><span>' + sharedFileText(shareId.substring(0,10)) + '…' + userName + '</span></label></td>'
                    + '<td class="file-name" style="padding:8px 10px;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + (fname ? sharedFileText(fname) : '<em style="opacity:.4">—</em>') + '</td>'
                    + '<td style="padding:8px 10px;font-size:11px;font-family:\'Courier New\',monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;opacity:.7;" title="' + sharedFileText(url) + '">' + sharedFileText(pathDisplay) + '</td>'
                    + '<td style="padding:8px 10px;white-space:normal;">' + buildShareExpirySelect(expiresOption, shareId) + '</td>'
                    + '<td style="padding:6px 10px;white-space:nowrap;"><button type="button" class="terminate-btn sf-delete-btn" data-shareid="' + sharedFileText(shareId) + '" style="padding:4px 10px;font-size:12px;">' + sharedFileText(SHARED_FILE_DELETE_TEXT) + '</button></td>'
                    + '</tr>';
            });
            container.innerHTML = '<table id="shared-files-table" style="width:100%;border-collapse:collapse;table-layout:fixed;"><colgroup><col style="width:14%"><col style="width:24%"><col style="width:30%"><col style="width:18%"><col style="width:14%"></colgroup><thead><tr style="background:var(--table-header-bg);"><th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border-color);">Share ID / User</th><th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border-color);">File Name</th><th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border-color);">File Path</th><th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border-color);">Expires</th><th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border-color);">Actions</th></tr></thead><tbody>' + rows + '</tbody></table>';
            bindSharedFilesEvents();
        }

        function bindSharedFilesEvents(){
            /* individual delete */
            document.querySelectorAll('.sf-delete-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    const sid = this.dataset.shareid;
                    sfConfirm('<?php echo addslashes(my_htmlspecialchars($translations['delete_share_confirm'])); ?>', function(){
                        const fd=new FormData(); fd.append('delete_shared_file','1'); fd.append('ajax','1'); fd.append('share_id',sid);
                        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
                            if(d.success){ showTempNotification(d.message||'Deleted','success'); renderSharedFilesTable(d.files||{}); }
                            else { showTempNotification('Error','error'); }
                        });
                    });
                });
            });
            /* search filter rebind */
            const tbl = document.getElementById('shared-files-table');
            const src = document.getElementById('shared-files-search');
            if(src && tbl){
                const handler = function(){
                    const q = src.value.toLowerCase();
                    tbl.querySelectorAll('tbody tr').forEach(function(r){
                        const fn = (r.querySelector('.file-name')||{}).textContent||'';
                        r.style.display = fn.toLowerCase().includes(q) ? '' : 'none';
                    });
                };
                src.removeEventListener('input', src._sfHandler);
                src._sfHandler = handler;
                src.addEventListener('input', handler);
            }
            document.querySelectorAll('.sf-expiry-select').forEach(function(sel){
                sel.addEventListener('change', function(){
                    const sid = this.dataset.shareid;
                    const fd = new FormData();
                    fd.append('update_shared_file_expiration', '1');
                    fd.append('ajax', '1');
                    fd.append('share_id', sid);
                    fd.append('expires_option', this.value || 'never');
                    fetch('/main', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
                        if(d.success){
                            renderSharedFilesTable(d.files||{});
                            showTempNotification(d.message||'Updated','success');
                        } else {
                            showTempNotification(d.message||'Error','error');
                        }
                    }).catch(function(){showTempNotification('Error','error');});
                });
            });
            const rowChecks = document.querySelectorAll('.sf-row-check');
            const deleteSelectedBtn = document.getElementById('delete-selected-shared-files');
            const refreshSelectionState = function(){
                const selected = Array.from(rowChecks).filter(function(x){return x.checked;});
                if(deleteSelectedBtn) deleteSelectedBtn.style.display = selected.length ? 'inline-flex' : 'none';
            };
            rowChecks.forEach(function(ch){ch.addEventListener('change', refreshSelectionState);});
            if(deleteSelectedBtn){
                deleteSelectedBtn.onclick = function(){
                    const ids = Array.from(document.querySelectorAll('.sf-row-check')).filter(function(x){return x.checked;}).map(function(x){return x.value;});
                    if(!ids.length) return;
                    sfConfirm('Delete selected shared links?', function(){
                        const fd=new FormData();fd.append('delete_filtered_files','1');fd.append('ajax','1');fd.append('file_ids',JSON.stringify(ids));
                        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
                            if(d.success){renderSharedFilesTable(d.files||{});showTempNotification(d.message||'Deleted','success');}
                        });
                    });
                };
            }
        }

        function loadSharedFilesList(){
            const refreshButton=document.getElementById('refresh-shared-files');
            if(refreshButton){ refreshButton.disabled=true; refreshButton.style.opacity='0.6'; }
            const fd=new FormData(); fd.append('get_shared_files','1'); fd.append('ajax','1');
            fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
                if(d.success) renderSharedFilesTable(d.files||{});
                if(refreshButton){ refreshButton.disabled=false; refreshButton.style.opacity='1'; }
            }).catch(function(){ if(refreshButton){ refreshButton.disabled=false; refreshButton.style.opacity='1'; } });
        }

        /* sfConfirm — modal instead of alert/confirm */
        function sfConfirm(msg, onOk){
            const md = document.getElementById('fm-confirm-modal');
            if(!md){ if(confirm(msg)) onOk(); return; }
            document.getElementById('fm-confirm-msg').textContent = msg;
            md.style.display='flex';
            const ok  = document.getElementById('fm-confirm-ok');
            const can = document.getElementById('fm-confirm-cancel');
            function cleanup(){ md.style.display='none'; ok.onclick=null; can.onclick=null; }
            ok.onclick  = function(){ cleanup(); onOk(); };
            can.onclick = function(){ cleanup(); };
        }

        /* delete all */
        const deleteAllBtn = document.getElementById('delete-all-files-btn');
        if(deleteAllBtn){
            deleteAllBtn.addEventListener('click', function(){
                sfConfirm('<?php echo addslashes(my_htmlspecialchars($translations['delete_all_confirm'])); ?>', ()=>{
                    const fd=new FormData(); fd.append('delete_all_shared_files','1'); fd.append('ajax','1');
                    fetch('/main',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
                        if(d.success){ showTempNotification(d.message||'Deleted','success'); renderSharedFilesTable({}); }
                        else { showTempNotification('Error','error'); }
                    });
                });
            });
        }

        /* bind events on initial static table (PHP-rendered on page load) */
        bindSharedFilesEvents();
        if(settingsButton){settingsButton.addEventListener('click',(e)=>{e.preventDefault();closeAllModals();if(settingsModal){settingsModal.style.display='flex';}});}
        if(qrButton&&qrCodeElement){qrButton.addEventListener('click',()=>{closeAllModals();if(qrModal){qrModal.style.display='flex';const domain=currentDomainUrl.textContent.trim();if(typeof QRCode==='undefined'){qrCodeElement.innerHTML='<div style="text-align: center; padding: 20px;"><p style="color: red;">'+T.qr_library_not_loaded+'</p><p>'+T.qr_refresh_page+'</p></div>';return;}qrCodeElement.innerHTML='';try{const tempContainer=document.createElement('div');const qr=new QRCode(tempContainer,{text:domain,width:256,height:256,colorDark:"#000000",colorLight:"#ffffff",correctLevel:QRCode.CorrectLevel.H});setTimeout(()=>{const canvas=tempContainer.querySelector('canvas');if(canvas){qrCodeElement.innerHTML='';qrCodeElement.appendChild(canvas);canvas.style.display='block';canvas.style.margin='0 auto';canvas.style.borderRadius='12px';canvas.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';}else{qrCodeElement.innerHTML='<p style="color: red; text-align: center;">'+T.qr_generation_failed+'</p>';}},100);}catch(error){console.error('QR Code error:',error);qrCodeElement.innerHTML='<div style="text-align: center; padding: 20px;"><p style="color: red;">'+T.qr_generation_failed_detail+'</p><p style="font-size: 12px;">'+error.message+'</p></div>';}}});}
        if(copyDomainBtn){copyDomainBtn.addEventListener('click',function(){const domain=currentDomainUrl.textContent;navigator.clipboard.writeText(domain).then(()=>{const originalText=copyDomainBtn.innerHTML;copyDomainBtn.innerHTML='<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Copied!';copyDomainBtn.style.background='#00C851';setTimeout(()=>{copyDomainBtn.innerHTML=originalText;copyDomainBtn.style.background='';},2000);}).catch(err=>{console.error('Failed to copy: ',err);});});}
        function closeAllModals(){const modals=[settingsModal,qrModal,emailModal,usersModal,profileModal,sessionsModal,sharedFilesModal,authModal,deleteUserModal,document.getElementById('reset-user-password-modal'),tgBotModal,shortcutsModal,document.getElementById('wallpaper-modal'),document.getElementById('npo-prompt-modal'),document.getElementById('mcp-setup-modal')];modals.forEach(modal=>{if(modal){modal.style.display='none';}});const np=document.getElementById('news-portal-overlay');if(np&&np.classList.contains('active')){np.classList.remove('active');if(window.location.hash==='#news-workflow')history.pushState(null,'',window.location.pathname+window.location.search);}else if(np){np.classList.remove('active');}closePolicyModal();}

        // ===== Telegram Bot Modal =====
        const tgBotModal = document.getElementById('tg-bot-modal');
        const tgBotButton = document.getElementById('tg-bot-button');
        const closeTgBot = document.getElementById('close-tg-bot');
        const saveTgBot = document.getElementById('save-tg-bot');
        const testTgBot = document.getElementById('test-tg-bot');
        const generateTgLinkToken = document.getElementById('generate-tg-link-token');
        const tgStatus = document.getElementById('tg-status');

        if(tgBotButton){ tgBotButton.addEventListener('click',()=>{ closeAllModals(); if(tgBotModal) tgBotModal.style.display='flex'; }); }
        if(closeTgBot){ closeTgBot.addEventListener('click',()=>{ if(tgBotModal) tgBotModal.style.display='none'; }); }
        if(tgBotModal){ tgBotModal.addEventListener('click',(e)=>{ if(e.target===tgBotModal) tgBotModal.style.display='none'; }); }

        function tgBotRequest(action){
            const token = document.getElementById('tg-bot-token')?.value.trim() || '';
            const webBase = document.getElementById('tg-bot-webapp-url')?.value.trim() || '';
            const fd = new FormData();
            fd.append(action, '1');
            fd.append('ajax', '1');
            fd.append('tg_bot_token', token);
            fd.append('tg_webapp_base_url', webBase);
            return fetch('/main', {method:'POST', body:fd}).then(r=>r.text()).then(t=>{
                try{ return JSON.parse(t); }
                catch(e){ console.error('TG response:', t); return {success:false,error:'Server error'}; }
            });
        }

        if(saveTgBot){
            saveTgBot.addEventListener('click',()=>{
                if(tgStatus) tgStatus.textContent = '...';
                tgBotRequest('save_tg_bot_config').then(d=>{
                    if(tgStatus) tgStatus.style.color = d.success ? '#00C851' : '#ff4444';
                    if(tgStatus) tgStatus.textContent = d.success ? <?php echo json_encode($translations['tg_saved'] ?? 'Saved successfully', JSON_UNESCAPED_UNICODE); ?> : ('Error: '+(d.error||'unknown'));
                });
            });
        }
        if(testTgBot){
            testTgBot.addEventListener('click',()=>{
                if(tgStatus) tgStatus.textContent = <?php echo json_encode($translations['tg_testing'] ?? 'Sending test message...', JSON_UNESCAPED_UNICODE); ?>;
                tgBotRequest('test_tg_bot').then(d=>{
                    if(tgStatus) tgStatus.style.color = d.success ? '#00C851' : '#ff4444';
                    if(tgStatus) tgStatus.textContent = d.success ? <?php echo json_encode($translations['tg_test_ok'] ?? 'Test message sent successfully', JSON_UNESCAPED_UNICODE); ?> : <?php echo json_encode($translations['tg_test_fail'] ?? 'Failed — check token and chat ID', JSON_UNESCAPED_UNICODE); ?>;
                });
            });
        }
        if(generateTgLinkToken){
            generateTgLinkToken.addEventListener('click',()=>{
                const fd = new FormData();
                fd.append('generate_tg_link_token','1');
                fd.append('ajax','1');
                fd.append('tg_link_email', document.getElementById('tg-link-email')?.value || '');
                if(tgStatus) tgStatus.textContent = '...';
                fetch('/main',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
                    if(d.success){
                        const out=document.getElementById('tg-link-token');
                        if(out) out.value=d.token;
                    }
                    if(tgStatus) tgStatus.style.color = d.success ? '#00C851' : '#ff4444';
                    if(tgStatus) tgStatus.textContent = d.success ? d.token : (d.error || 'Error');
                }).catch(()=>{if(tgStatus){tgStatus.style.color='#ff4444';tgStatus.textContent='Network error';}});
            });
        }
        // ===== Delete User Modal =====
        const deleteUserModal = document.getElementById('delete-user-modal');
        const closeDeleteUser = document.getElementById('close-delete-user');
        const doDeleteUserBtn = document.getElementById('do-delete-user-btn');
        const deleteFilesCheckbox = document.getElementById('delete-files-checkbox');
        const deleteSliderSection = document.getElementById('delete-slider-section');
        const confirmSlider = document.getElementById('confirm-slider');
        const sliderFill = document.getElementById('slider-fill');
        const sliderLabel = document.getElementById('slider-label');
        let sliderConfirmed = false;

        if(closeDeleteUser){ closeDeleteUser.addEventListener('click', () => { if(deleteUserModal) deleteUserModal.style.display = 'none'; }); }
        if(deleteUserModal){ deleteUserModal.addEventListener('click', (e) => { if(e.target === deleteUserModal) deleteUserModal.style.display = 'none'; }); }

        if(deleteFilesCheckbox){
            deleteFilesCheckbox.addEventListener('change', function(){
                if(deleteSliderSection){
                    deleteSliderSection.style.display = this.checked ? 'block' : 'none';
                }
                if(!this.checked){
                    // Without file deletion, enable button directly
                    if(doDeleteUserBtn) doDeleteUserBtn.disabled = false;
                    sliderConfirmed = false;
                    if(confirmSlider) { confirmSlider.value = 0; updateSlider(0); }
                } else {
                    if(doDeleteUserBtn) doDeleteUserBtn.disabled = true;
                    sliderConfirmed = false;
                    if(confirmSlider) { confirmSlider.value = 0; updateSlider(0); }
                }
            });
        }

        function updateSlider(val){
            if(sliderFill) sliderFill.style.width = val + '%';
            if(sliderLabel){
                if(val >= 98){
                    sliderLabel.textContent = '✓';
                    sliderLabel.style.color = '#cc0000';
                } else {
                    sliderLabel.textContent = '➜';
                    sliderLabel.style.color = '';
                }
            }
        }

        if(confirmSlider){
            confirmSlider.addEventListener('input', function(){
                const val = parseInt(this.value);
                updateSlider(val);
                if(val >= 98){
                    sliderConfirmed = true;
                    if(doDeleteUserBtn) doDeleteUserBtn.disabled = false;
                } else {
                    sliderConfirmed = false;
                    if(doDeleteUserBtn) doDeleteUserBtn.disabled = true;
                }
            });
            // Snap back if not fully dragged
            confirmSlider.addEventListener('change', function(){
                if(parseInt(this.value) < 98){
                    this.value = 0;
                    updateSlider(0);
                    sliderConfirmed = false;
                    if(doDeleteUserBtn) doDeleteUserBtn.disabled = true;
                }
            });
        }

        document.querySelectorAll('.open-delete-user-modal').forEach(btn => {
            btn.addEventListener('click', function(){
                const email = this.getAttribute('data-user-email');
                const name = this.getAttribute('data-user-name');
                openDeleteUserModal(email, name);
            });
        });

        function openDeleteUserModal(email, name){
            const emailDisplay = document.getElementById('delete-user-email-display');
            const scanning = document.getElementById('delete-user-scanning');
            const progressSection = document.getElementById('delete-user-scan-progress');
            const progressBar = document.getElementById('scan-progress-bar');
            const progressPercent = document.getElementById('scan-progress-percent');
            const progressFiles = document.getElementById('scan-progress-files');
            const timeRemaining = document.getElementById('scan-time-remaining');
            const scanSpeed = document.getElementById('scan-speed');
            const filesLoaded = document.getElementById('delete-user-files-loaded');
            const filesList = document.getElementById('delete-user-files-list');
            const fileCount = document.getElementById('delete-user-file-count');
            const totalSize = document.getElementById('delete-user-total-size');
            const targetEmail = document.getElementById('delete-user-target-email');

            if(emailDisplay) emailDisplay.textContent = name + ' (' + email + ')';
            if(targetEmail) targetEmail.value = email;
            if(scanning) scanning.style.display = 'none'; // Hide the old scanning message
            if(progressSection) progressSection.style.display = 'block'; // Show progress bar immediately
            if(filesLoaded) filesLoaded.style.display = 'none';
            if(deleteFilesCheckbox) { deleteFilesCheckbox.checked = false; }
            if(deleteSliderSection) deleteSliderSection.style.display = 'none';
            if(confirmSlider) { confirmSlider.value = 0; updateSlider(0); }
            sliderConfirmed = false;
            if(doDeleteUserBtn) doDeleteUserBtn.disabled = false;

            closeAllModals();
            if(deleteUserModal) deleteUserModal.style.display = 'flex';

            // Start progress animation
            const startTime = Date.now();
            let progressInterval;
            const totalEstimate = 3000; // Estimate 3 seconds for scanning

            progressInterval = setInterval(() => {
                const elapsed = Date.now() - startTime;
                const progress = Math.min((elapsed / totalEstimate) * 100, 95); // Cap at 95% until complete

                if(progressBar) progressBar.style.width = progress + '%';
                if(progressPercent) progressPercent.textContent = Math.round(progress) + '%';

                // Calculate estimated time remaining
                const remaining = Math.max(0, Math.ceil((totalEstimate - elapsed) / 1000));
                if(timeRemaining) {
                    if(remaining > 0) {
                        timeRemaining.textContent = '<?php echo my_htmlspecialchars($translations['time_remaining'] ?? 'Time remaining'); ?>: ' + remaining + 's';
                    } else {
                        timeRemaining.textContent = '<?php echo my_htmlspecialchars($translations['almost_done'] ?? 'Almost done...'); ?>';
                    }
                }

                // Show files being scanned (simulated)
                const scannedCount = Math.floor((progress / 100) * 100); // Simulated count
                if(progressFiles) progressFiles.textContent = '<?php echo my_htmlspecialchars($translations['scanning_progress'] ?? 'Scanning'); ?>... ' + scannedCount + ' files';

                // Show scan speed
                if(scanSpeed) {
                    const filesPerSec = (scannedCount / (elapsed / 1000)).toFixed(0);
                    scanSpeed.textContent = '<?php echo my_htmlspecialchars($translations['scan_speed'] ?? 'Speed'); ?>: ~' + filesPerSec + ' files/sec';
                }
            }, 100);

            // Scan files via AJAX
            const formData = new FormData();
            formData.append('scan_user_files', '1');
            formData.append('ajax', '1');
            formData.append('user_email', email);
            fetch('/main', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    // Stop progress animation
                    clearInterval(progressInterval);

                    // Set to 100%
                    if(progressBar) progressBar.style.width = '100%';
                    if(progressPercent) progressPercent.textContent = '100%';
                    if(timeRemaining) timeRemaining.textContent = '<?php echo my_htmlspecialchars($translations['complete'] ?? 'Complete'); ?>!';

                    // Small delay to show completion
                    setTimeout(() => {
                        if(scanning) scanning.style.display = 'none';
                        if(progressSection) progressSection.style.display = 'none';
                        if(filesLoaded) filesLoaded.style.display = 'block';
                        if(data.success){
                            const files = data.files || [];
                            if(fileCount) fileCount.textContent = '(' + files.length + ' <?php echo my_htmlspecialchars($translations['files'] ?? 'files'); ?>)';
                            if(totalSize) totalSize.textContent = data.total_size_human || '0 B';
                            if(filesList){
                                if(files.length === 0){
                                    filesList.innerHTML = '<div class="no-files-msg"><?php echo my_htmlspecialchars($translations['no_user_files'] ?? 'No user files found in configured paths'); ?></div>';
                                } else {
                                    filesList.innerHTML = files.map(f =>
                                        `<div class="delete-user-file-row">
                                            <span class="delete-user-file-app">${escHtml(f.app)}</span>
                                            <span class="delete-user-file-name" title="${escHtml(f.path)}">${escHtml(f.name)}</span>
                                            <span class="delete-user-file-size">${formatBytesJS(f.size)}</span>
                                        </div>`
                                    ).join('');
                                }
                            }
                        }
                    }, 300);
                }).catch(() => {
                    clearInterval(progressInterval);
                    if(scanning) scanning.style.display = 'none';
                    if(progressSection) progressSection.style.display = 'none';
                    if(filesLoaded) filesLoaded.style.display = 'block';
                    if(filesList) filesList.innerHTML = '<div class="no-files-msg"><?php echo my_htmlspecialchars($translations['no_user_files'] ?? 'No user files found in configured paths'); ?></div>';
                });
        }

        if(doDeleteUserBtn){
            doDeleteUserBtn.addEventListener('click', function(){
                const email = document.getElementById('delete-user-target-email')?.value;
                const withFiles = deleteFilesCheckbox && deleteFilesCheckbox.checked;
                if(!email) return;
                if(withFiles && !sliderConfirmed) return;

                this.disabled = true;
                this.textContent = '<?php echo my_htmlspecialchars($translations['deleting'] ?? 'Deleting...'); ?>';

                const formData = new FormData();
                formData.append('delete_user_with_files', '1');
                formData.append('ajax', '1');
                formData.append('user_email', email);
                if(withFiles) formData.append('delete_files', '1');

                fetch('/main', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if(data.success){
                            showTempNotification(data.message, 'success');
                            if(deleteUserModal) deleteUserModal.style.display = 'none';
                            // Remove the user row from admin panel table immediately
                            const row = document.querySelector(`#ap-users-table tbody tr[data-email="${CSS.escape(email)}"]`);
                            if(row) row.remove();
                            // Full refresh via global refreshUsersList
                            if(window.refreshUsersList) window.refreshUsersList();
                            else refreshUsersList();
                        } else {
                            showTempNotification(data.message||T.error_del_user,'error');
                            this.disabled = false;
                            this.textContent = '<?php echo my_htmlspecialchars($translations['delete_user'] ?? 'Delete User'); ?>';
                        }
                    }).catch(() => {
                        showTempNotification(T.error_del_user,'error');
                        this.disabled = false;
                        this.textContent = '<?php echo my_htmlspecialchars($translations['delete_user'] ?? 'Delete User'); ?>';
                    });
            });
        }

        // Refresh users list via AJAX
        function refreshUsersList(){
            const btn = document.getElementById('refresh-users-btn');
            const container = document.getElementById('users-table-container');
            if(btn){ btn.disabled = true; btn.style.opacity = '0.6'; }
            const fd = new FormData();
            fd.append('get_users_table','1');
            fd.append('ajax','1');
            fetch('/main',{method:'POST',body:fd})
                .then(r=>r.json())
                .then(data=>{
                    if(data.success && container){
                        // Replace only tbody rows to keep the form intact
                        const tbody = container.querySelector('.users-table tbody');
                        if(tbody) tbody.innerHTML = data.rows;
                        // Re-attach delete/block listeners on new rows
                        attachUserRowListeners();
                    }
                    if(btn){ btn.disabled=false; btn.style.opacity='1'; }
                    showTempNotification('<?php echo my_htmlspecialchars($translations['refresh_users'] ?? 'Users list refreshed'); ?>','success');
                }).catch(()=>{
                    if(btn){ btn.disabled=false; btn.style.opacity='1'; }
                });
        }



        // Attach listeners to user row buttons (used after AJAX refresh)
        function attachUserRowListeners(){
            document.querySelectorAll('.open-delete-user-modal').forEach(btn => {
                btn.addEventListener('click', function(){
                    openDeleteUserModal(this.getAttribute('data-user-email'), this.getAttribute('data-user-name'));
                });
            });
        }

        const refreshUsersBtn = document.getElementById('refresh-users-btn');
        if(refreshUsersBtn){ refreshUsersBtn.addEventListener('click', refreshUsersList); }

        // Password validation (global policy: min 6 chars, letter + digit)
        (function(){
            const PASSWORD_MIN_LENGTH = <?php echo PASSWORD_MIN_LENGTH; ?>;
            function isPasswordValid(p){
                return p.length >= PASSWORD_MIN_LENGTH && /\p{L}/u.test(p) && /\d/.test(p);
            }
            function passwordStrengthMessage(p){
                if(!p.length) return '';
                if(p.length < PASSWORD_MIN_LENGTH) return '<?php echo my_htmlspecialchars($translations['password_too_short'] ?? 'Password must be at least 6 characters'); ?>';
                if(!/\p{L}/u.test(p)) return '<?php echo my_htmlspecialchars($translations['password_needs_letter'] ?? 'Password must contain at least one letter'); ?>';
                if(!/\d/.test(p)) return '<?php echo my_htmlspecialchars($translations['password_needs_digit'] ?? 'Password must contain at least one digit'); ?>';
                return '';
            }
            window.isPasswordValid = isPasswordValid;
            window.passwordStrengthMessage = passwordStrengthMessage;
            function setupPasswordValidation(pwdId, confirmId, matchMsgId, submitSelector){
                const pwd     = document.getElementById(pwdId);
                const confirm = document.getElementById(confirmId);
                const matchEl = document.getElementById(matchMsgId);
                if(!pwd || !confirm) return;
                function validate(){
                    const p = pwd.value;
                    const c = confirm.value;
                    const strong = isPasswordValid(p);
                    const strengthMsg = passwordStrengthMessage(p);
                    if(matchEl){
                        if(c.length === 0){
                            matchEl.textContent = strengthMsg;
                            matchEl.style.color = strengthMsg ? '#ff9800' : '';
                        } else if(!strong){
                            matchEl.textContent = strengthMsg;
                            matchEl.style.color = '#ff4444';
                        } else if(p === c){
                            matchEl.textContent = '<?php echo my_htmlspecialchars($translations['passwords_match'] ?? '✓ Passwords match'); ?>';
                            matchEl.style.color = '#00C851';
                        } else {
                            matchEl.textContent = '<?php echo my_htmlspecialchars($translations['password_mismatch'] ?? 'Passwords do not match'); ?>';
                            matchEl.style.color = '#ff4444';
                        }
                    }
                    const form = pwd.closest('form');
                    if(form){
                        const submitBtn = form.querySelector(submitSelector || 'button[type="submit"],input[type="submit"]');
                        if(submitBtn) submitBtn.disabled = !(strong && p === c && c.length > 0);
                    }
                }
                pwd.addEventListener('input', validate);
                confirm.addEventListener('input', validate);
                validate();
            }
            setupPasswordValidation('register-password','register-confirm-password','register-password-match-msg','button[name="register_complete"]');
            setupPasswordValidation('reset-password','reset-confirm-password','reset-password-match-msg','#reset-password-submit');
            setupPasswordValidation('change-new-password','change-confirm-password','change-password-match-msg','#change-password-submit');
            function generateSecurePassword(length){
                const letters='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
                const digits='23456789';
                const all=letters+digits;
                const minLen=Math.max(PASSWORD_MIN_LENGTH,length||12);
                const buf=new Uint32Array(minLen);
                crypto.getRandomValues(buf);
                const chars=[letters[buf[0]%letters.length],digits[buf[1]%digits.length]];
                for(let i=2;i<minLen;i++) chars.push(all[buf[i]%all.length]);
                for(let i=chars.length-1;i>0;i--){
                    const j=buf[i]%(i+1);
                    const t=chars[i];chars[i]=chars[j];chars[j]=t;
                }
                return chars.join('');
            }
            window.generateSecurePassword = generateSecurePassword;
            document.querySelectorAll('.password-generate-btn').forEach(function(btn){
                btn.addEventListener('click',function(){
                    const pwd=document.getElementById(btn.getAttribute('data-pwd-target')||'');
                    const confirm=document.getElementById(btn.getAttribute('data-confirm-target')||'');
                    if(!pwd||pwd.disabled) return;
                    const value=generateSecurePassword(12);
                    pwd.value=value;
                    if(confirm) confirm.value=value;
                    pwd.dispatchEvent(new Event('input',{bubbles:true}));
                    if(confirm) confirm.dispatchEvent(new Event('input',{bubbles:true}));
                });
            });
        })();

        function escHtml(str){ const d=document.createElement('div');d.appendChild(document.createTextNode(str));return d.innerHTML; }
        function formatBytesJS(bytes){ if(bytes===0)return'0 B';const u=['B','KB','MB','GB','TB'];const e=Math.min(Math.floor(Math.log(bytes)/Math.log(1024)),u.length-1);return(bytes/Math.pow(1024,e)).toFixed(2)+' '+u[e]; }
        function refreshSessionsList(){const refreshButton=document.getElementById('refresh-sessions');const originalHTML=refreshButton.innerHTML;refreshButton.innerHTML='<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46A7.93 7.93 0 0020 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74A7.93 7.93 0 004 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>';refreshButton.style.opacity='0.7';refreshButton.disabled=true;setTimeout(()=>{showTempNotification(T.session_refreshed,'success');refreshButton.innerHTML=originalHTML;refreshButton.style.opacity='1';refreshButton.disabled=false;},1000);}
        function showTempNotification(message,type='info'){
            const notification=document.createElement('div');
            notification.className=`notification temp-notification ${type}`;
            const iconPath = type==='success'
                ? '<path d="M20 6L9 17l-5-5"/>'
                : type==='error'
                ? '<path d="M18 6L6 18M6 6l12 12"/>'
                : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
            notification.innerHTML = `
                <svg class="notification-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    ${iconPath}
                </svg>
                <span class="notification-content">${message}</span>
                <button class="notification-close">&times;</button>
            `;
            document.body.appendChild(notification);
            const closeBtn = notification.querySelector('.notification-close');
            closeBtn.addEventListener('click', () => {
                notification.classList.remove('show');
                setTimeout(() => { notification.remove(); updateTempNotificationStack(); }, 250);
            });
            updateTempNotificationStack();
            setTimeout(() => { notification.classList.add('show'); }, 50);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => { if(notification.parentNode) notification.remove(); updateTempNotificationStack(); }, 250);
            }, 4000);
        }
        function updateTempNotificationStack(){
            let offset=0;
            Array.from(document.querySelectorAll('.temp-notification')).reverse().forEach(function(el){
                el.style.bottom=(24+offset)+'px';
                offset+=el.offsetHeight+10;
            });
        }
        (function(){
            const notificationButtons=Array.from(document.querySelectorAll('#notifications-btn'));
            const btn=notificationButtons.find(function(el){return el.offsetParent!==null;})||notificationButtons[0]||null;
            const panel=document.getElementById('notifications-panel');
            const list=document.getElementById('notifications-list');
            const badges=Array.from(document.querySelectorAll('#notifications-unread-badge'));
            const enableBtn=document.getElementById('notifications-enable-btn');
            const readBtn=document.getElementById('notifications-mark-read-btn');
            // Delegate click on copy buttons inside the notifications list
            if(list){
                list.addEventListener('click',function(e){
                    const btn=e.target.closest('.notification-row-copy');
                    if(!btn)return;
                    const row=btn.closest('.notification-row');
                    if(!row)return;
                    const title=row.querySelector('.notification-row-title')?row.querySelector('.notification-row-title').textContent:'';
                    const meta=row.querySelector('.notification-row-meta')?row.querySelector('.notification-row-meta').textContent:'';
                    const msgEl=row.querySelector('.notification-row-message');
                    const msg=msgEl?msgEl.textContent:'';
                    const text=title+(meta?'\n'+meta:'')+(msg?'\n'+msg:'');
                    navigator.clipboard.writeText(text).then(function(){
                        if(typeof showTempNotification==='function'){
                            showTempNotification('Copied!','success');
                        }
                    }).catch(function(err){console.error('Copy failed: ',err);});
                });
            }
            let lastSeenId=0;
            const enableBtnText=enableBtn?enableBtn.querySelector('span'):null;
            const enableBtnLabel=enableBtnText?enableBtnText.textContent:'Enable';
            window.ApNotificationPopover=window.ApNotificationPopover||function(trigger,target){
                function open(){if(target)target.classList.add('open');}
                function close(){if(target)target.classList.remove('open');}
                function toggle(){if(!target)return;target.classList.contains('open')?close():open();}
                function isOpen(){return !!(target&&target.classList.contains('open'));}
                if(trigger&&target){
                    document.addEventListener('click',function(e){
                        if(isOpen()&&!target.contains(e.target)&&!notificationButtons.some(function(button){return button.contains(e.target);}))close();
                    });
                    window.addEventListener('resize',close);
                    document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
                }
                return {open:open,close:close,toggle:toggle,isOpen:isOpen};
            };
            const notificationPopover=window.ApNotificationPopover(btn,panel);
            function notificationHeaders(){return {'Content-Type':'application/json'};}
            function syncEnableButton(){
                if(!enableBtn)return;
                const granted='Notification'in window&&Notification.permission==='granted';
                enableBtn.disabled=granted;
                enableBtn.setAttribute('aria-disabled',granted?'true':'false');
                if(enableBtnText)enableBtnText.textContent=granted?'Enabled':enableBtnLabel;
            }
            function setBadge(count){
                badges.forEach(function(badge){
                    if(count>0){badge.textContent=count>99?'99+':String(count);badge.style.display='block';}
                    else{badge.style.display='none';}
                });
            }
            const copySvg='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
            function rowHtml(n){
                const dt=new Date((Number(n.created_at)||0)*1000).toLocaleString();
                const app=n.app_id?` · ${escHtml(n.app_id)}`:'';
                const msg=n.message?`<div class="notification-row-message">${escHtml(n.message)}</div>`:'';
                const sev=escHtml((n.severity||'info').toLowerCase());
                const icons={
                    info:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
                    warning:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
                    error:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
                    success:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>',
                    critical:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
                };
                const iconClass=icons[sev]?'severity-'+sev:'severity-default';
                const iconHtml=icons[sev]||icons.info;
                const unreadDot=Number(n.is_read)?'':`<div class="notification-row-unread-dot"></div>`;
                return `<div class="notification-row ${Number(n.is_read)?'':'unread'}" data-id="${Number(n.id)||0}"><div class="notification-row-icon ${iconClass}">${iconHtml}</div><div class="notification-row-body"><div class="notification-row-title">${escHtml(n.title||'Notification')}</div><div class="notification-row-meta">${sev}${app} · ${dt}</div>${msg}</div><button class="notification-row-copy" title="Copy to clipboard">${copySvg}</button>${unreadDot}</div>`;
            }
            async function loadNotifications(showBrowser){
                if(!btn||!list)return;
                try{
                    const r=await fetch('/main?api=notifications&action=list&limit=40');
                    const d=await r.json();
                    if(!d.ok)return;
                    setBadge(Number(d.unread)||0);
                    list.innerHTML=(d.items||[]).map(rowHtml).join('')||'<div class="notification-row" style="justify-content:center;opacity:.5;padding:24px 16px;"><div class="notification-row-message">No notifications</div></div>';
                    const newest=(d.items||[])[0];
                    if(showBrowser&&newest&&Number(newest.id)>lastSeenId&&Number(newest.is_read)===0&&'Notification'in window&&Notification.permission==='granted'){
                        new Notification(newest.title||'Notification',{body:newest.message||newest.app_id||''});
                    }
                    if(newest)lastSeenId=Math.max(lastSeenId,Number(newest.id)||0);
                }catch(e){}
            }
            if(notificationButtons.length&&panel){
                notificationButtons.forEach(function(button){
                    button.addEventListener('click',function(e){
                    e.stopPropagation();
                    notificationPopover.toggle();
                    syncEnableButton();
                    loadNotifications(false);
                    });
                });
            }
            if(readBtn){
                readBtn.addEventListener('click',function(){
                    var confettiColors=['#22c55e','#f43f5e','#eab308','#3b82f6','#fff'];
                    readBtn.classList.remove('mark-read-done','mark-read-pop');
                    void readBtn.offsetWidth;
                    readBtn.classList.add('mark-read-done','mark-read-pop');
                    for(var i=0;i<14;i++){
                        var p=document.createElement('div');
                        p.className='mark-read-confetti';
                        p.style.background=confettiColors[Math.floor(Math.random()*confettiColors.length)];
                        p.style.setProperty('--sx',Math.random()*readBtn.offsetWidth+'px');
                        p.style.setProperty('--dx',(Math.random()-0.5)*80+'px');
                        p.style.setProperty('--dy',(-40-Math.random()*50)+'px');
                        p.style.setProperty('--rot',(Math.random()-0.5)*360+'deg');
                        readBtn.appendChild(p);
                        p.addEventListener('animationend',function(){this.remove();});
                    }
                    setTimeout(function(){readBtn.classList.remove('mark-read-done');},1500);
                    fetch('/main?api=notifications',{method:'POST',headers:notificationHeaders(),body:JSON.stringify({action:'mark_read'})}).then(function(){loadNotifications(false);});
                });
            }
            if(list){
                list.addEventListener('click',function(e){
                    const row=e.target.closest('.notification-row.unread[data-id]');
                    if(!row||!list.contains(row))return;
                    const id=Number(row.getAttribute('data-id'))||0;
                    if(!id)return;
                    fetch('/main?api=notifications',{method:'POST',headers:notificationHeaders(),body:JSON.stringify({action:'mark_read',id:id})}).then(function(){loadNotifications(false);});
                });
            }
            if(enableBtn){
                enableBtn.addEventListener('click',async function(){
                    let browser=0;
                    if('Notification'in window){
                        const p=Notification.permission==='granted'?'granted':await Notification.requestPermission();
                        browser=p==='granted'?1:0;
                    }
                    syncEnableButton();
                    fetch('/main?api=notifications',{method:'POST',headers:notificationHeaders(),body:JSON.stringify({action:'save_settings',browser_enabled:browser,telegram_enabled:1})}).then(function(){
                        showTempNotification(browser?'Notifications enabled':'Telegram notifications enabled','success');
                    });
                });
            }
            syncEnableButton();
            loadNotifications(false);
            setInterval(function(){loadNotifications(true);},45000);
            document.addEventListener('visibilitychange',function(){if(!document.hidden)loadNotifications(false);});
        })();

        function showNewDeviceNotification(){
            // Check if already has a notification showing
            const existingNotification=document.querySelector('.temp-notification');
            if(existingNotification){existingNotification.remove();}

            const notification=document.createElement('div');
            notification.className='notification temp-notification info';

            // Function to update z-index based on modal state
            function updateNotificationZIndex() {
                const anyModalOpen = document.querySelector('.modal[style*="display: flex"], .modal[style*="display:block"], #auth-modal[style*="display: flex"], #auth-modal[style*="display:flex"], #profile-modal[style*="display: flex"], #profile-modal[style*="display:flex"], #settings-modal[style*="display: flex"], #settings-modal[style*="display:flex"], #email-modal[style*="display: flex"], #email-modal[style*="display:flex"], #users-modal[style*="display: flex"], #users-modal[style*="display:flex"], #sessions-modal[style*="display: flex"], #sessions-modal[style*="display:flex"], #qr-modal[style*="display: flex"], #qr-modal[style*="display:flex"], #shared-files-modal[style*="display: flex"], #shared-files-modal[style*="display:flex"], #news-portal-overlay.active');
                notification.style.zIndex = anyModalOpen ? '9999' : '15000';
            }

            // Initial z-index check
            updateNotificationZIndex();

            // Observe modal display changes
            const modalObserver = new MutationObserver(() => {
                updateNotificationZIndex();
            });

            // Watch all modals for display changes
            const modalIds = ['auth-modal', 'profile-modal', 'settings-modal', 'email-modal', 'users-modal', 'sessions-modal', 'qr-modal', 'shared-files-modal'];
            modalIds.forEach(id => {
                const modal = document.getElementById(id);
                if (modal) {
                    modalObserver.observe(modal, { attributes: true, attributeFilter: ['style'] });
                }
            });

            notification.innerHTML = `
                <svg class="notification-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span class="notification-content">${T.new_device_notification}</span>
                <button class="add-trusted-device-btn" style="background:#4CAF50;color:white;border:none;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:11px;margin-left:8px;white-space:nowrap;">${T.add_device_trusted}</button>
                <button class="notification-close">&times;</button>
                <div class="notification-countdown" style="width:100%;"></div>
            `;
            document.body.appendChild(notification);

            // Countdown timer: 15 seconds
            let countdown = 15;
            const countdownBar = notification.querySelector('.notification-countdown');
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownBar.style.width = ((countdown / 15) * 100) + '%';
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);

            const closeBtn = notification.querySelector('.notification-close');
            closeBtn.addEventListener('click', () => {
                notification.classList.remove('show');
                clearInterval(countdownInterval);
                modalObserver.disconnect();
                setTimeout(() => notification.remove(), 250);
            });

            const addBtn = notification.querySelector('.add-trusted-device-btn');
            addBtn.addEventListener('click', () => {
                // Add this device as trusted
                clearInterval(countdownInterval);
                fetch('/main?api=client_auth&action=add_trusted_device',{method:'POST',headers:trustedDeviceRequestHeaders(),body:JSON.stringify({})})
                .then(r=>r.json()).then(data=>{
                    if(data.ok){
                        persistTrustedDeviceClientState(data.encrypted_token || '');
                        showTempNotification(T.device_now_trusted||'Device added','success');
                        notification.classList.remove('show');
                        modalObserver.disconnect();
                        setTimeout(() => notification.remove(), 250);
                        if (typeof refreshTrustedDevices === 'function') {
                            refreshTrustedDevices();
                        }
                    } else {
                        showTempNotification(data.error||'Error','error');
                    }
                }).catch(()=>showTempNotification(T.network_error||'Network error','error'));
            });

            setTimeout(() => { notification.classList.add('show'); }, 50);

            // Auto-hide after 15 seconds with swipe animation
            setTimeout(() => {
                clearInterval(countdownInterval);
                notification.classList.remove('show');
                notification.classList.add('swiping-out');
                modalObserver.disconnect();
                setTimeout(() => { if(notification.parentNode) notification.remove(); }, 400);
            }, 15000);
        }

        if(userProfile){userProfile.addEventListener('click',()=>{closeAllModals();if(profileModal){profileModal.style.display='flex';}});}
        function resetPasswordChangeForm(){
            currentPasswordVerified=false;
            if(currentPasswordInput){currentPasswordInput.value='';currentPasswordInput.disabled=false;currentPasswordInput.readOnly=false;}
            if(currentPasswordMsg){currentPasswordMsg.textContent='';currentPasswordMsg.style.color='';}
            if(verifyCurrentPasswordBtn){verifyCurrentPasswordBtn.disabled=false;verifyCurrentPasswordBtn.style.display='';}
            if(passwordNewStep){
                passwordNewStep.style.display='none';
                passwordNewStep.querySelectorAll('input').forEach(function(input){
                    input.value='';
                    input.disabled=true;
                });
            }
            if(generateChangePasswordBtn) generateChangePasswordBtn.disabled=true;
            const changeMatchMsg=document.getElementById('change-password-match-msg');
            if(changeMatchMsg){changeMatchMsg.textContent='';changeMatchMsg.style.color='';}
            const changeSubmit=document.getElementById('change-password-submit');
            if(changeSubmit) changeSubmit.disabled=true;
        }
        function closePasswordChangeBox(){
            if(passwordChangeBox) passwordChangeBox.classList.remove('active');
            resetPasswordChangeForm();
        }
        if(closeProfile){closeProfile.addEventListener('click',()=>{if(profileModal){profileModal.style.display='none';}closePasswordChangeBox();if(photoForm){photoForm.classList.remove('active');}if(resetPhotoForm){resetPhotoForm.classList.remove('active');}});}
        if(profileModal){profileModal.addEventListener('click',(e)=>{if(e.target===profileModal){profileModal.style.display='none';closePasswordChangeBox();if(photoForm){photoForm.classList.remove('active');}if(resetPhotoForm){resetPhotoForm.classList.remove('active');}}});}
        document.querySelectorAll('.copy-email-btn').forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault();var email=this.getAttribute('data-email');var b=this;try{navigator.clipboard.writeText(email).then(function(){b.classList.add('copied');setTimeout(function(){b.classList.remove('copied');},1200);},function(){b.classList.add('failed');setTimeout(function(){b.classList.remove('failed');},1200);});}catch(err){b.classList.add('failed');setTimeout(function(){b.classList.remove('failed');},1200);}});});
        if(showPasswordForm){showPasswordForm.addEventListener('click',()=>{
            if(passwordChangeBox){
                passwordChangeBox.classList.toggle('active');
                if(!passwordChangeBox.classList.contains('active')) resetPasswordChangeForm();
            }
        });}
        if(verifyCurrentPasswordBtn && currentPasswordInput){
            verifyCurrentPasswordBtn.addEventListener('click',()=>{
                const pwd=currentPasswordInput.value;
                if(!pwd){
                    currentPasswordInput.focus();
                    return;
                }
                verifyCurrentPasswordBtn.disabled=true;
                const fd=new FormData();
                fd.append('verify_current_password','1');
                fd.append('ajax','1');
                fd.append('current_password',pwd);
                fetch('/main',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
                    if(data.success){
                        currentPasswordVerified=true;
                        currentPasswordInput.readOnly=true;
                        verifyCurrentPasswordBtn.style.display='none';
                        if(currentPasswordMsg){
                            currentPasswordMsg.textContent='<?php echo my_htmlspecialchars($translations['current_password_verified'] ?? 'Current password verified'); ?>';
                            currentPasswordMsg.style.color='#00C851';
                        }
                        if(passwordNewStep){
                            passwordNewStep.style.display='block';
                            passwordNewStep.querySelectorAll('input').forEach(function(input){input.disabled=false;});
                        }
                        if(generateChangePasswordBtn) generateChangePasswordBtn.disabled=false;
                    }else{
                        currentPasswordVerified=false;
                        verifyCurrentPasswordBtn.disabled=false;
                        if(currentPasswordMsg){
                            currentPasswordMsg.textContent=data.error||'<?php echo my_htmlspecialchars($translations['wrong_current_password'] ?? 'Wrong current password'); ?>';
                            currentPasswordMsg.style.color='#ff4444';
                        }
                    }
                }).catch(()=>{
                    verifyCurrentPasswordBtn.disabled=false;
                    if(currentPasswordMsg){
                        currentPasswordMsg.textContent='<?php echo my_htmlspecialchars($translations['network_error'] ?? 'Network error'); ?>';
                        currentPasswordMsg.style.color='#ff4444';
                    }
                });
            });
            currentPasswordInput.addEventListener('input',()=>{
                if(currentPasswordVerified){
                    currentPasswordVerified=false;
                    if(passwordNewStep){
                        passwordNewStep.style.display='none';
                        passwordNewStep.querySelectorAll('input').forEach(function(input){
                            input.value='';
                            input.disabled=true;
                        });
                    }
                    if(generateChangePasswordBtn) generateChangePasswordBtn.disabled=true;
                    verifyCurrentPasswordBtn.style.display='';
                    verifyCurrentPasswordBtn.disabled=false;
                    if(currentPasswordMsg){currentPasswordMsg.textContent='';currentPasswordMsg.style.color='';}
                }
            });
        }
        if(passwordForm){
            passwordForm.addEventListener('submit',(e)=>{
                if(!currentPasswordVerified){
                    e.preventDefault();
                    if(verifyCurrentPasswordBtn) verifyCurrentPasswordBtn.click();
                }
            });
        }
        if(authTabs.length > 0){ authTabs.forEach(tab=>{tab.addEventListener('click',function(){const tabName=this.getAttribute('data-tab');authTabs.forEach(t=>t.classList.remove('active'));this.classList.add('active');authContents.forEach(content=>{content.classList.remove('active');if(content.id===tabName+'-content'){content.classList.add('active');}});});}); }
        if(showForgotLink){showForgotLink.addEventListener('click',function(){authTabs.forEach(t=>t.classList.remove('active'));authContents.forEach(content=>{content.classList.remove('active');if(content.id==='forgot-content'){content.classList.add('active');}});});}
        document.querySelectorAll('.auth-link[data-tab]').forEach(link=>{link.addEventListener('click',function(){const tabName=this.getAttribute('data-tab');authTabs.forEach(tab=>{if(tab.getAttribute('data-tab')===tabName){tab.click();}});});});
        document.querySelectorAll('.auth-button[type="submit"]').forEach(btn=>{
            btn.closest('form')?.addEventListener('submit',function(){
                if(btn.dataset.loading) return false;
                btn.dataset.loading='1';
                btn.textContent=T.loading_btn||'Loading...';
                btn.style.pointerEvents='none';
                btn.style.opacity='0.7';
            });
        });
        (function(){
            var logoutForm=document.querySelector('form button[name="logout"]')?.closest('form');
            if(!logoutForm)return;
            logoutForm.addEventListener('submit',function(e){
                e.preventDefault();
                var overlay=document.createElement('div');
                overlay.style.cssText='position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,0);display:flex;align-items:center;justify-content:center;flex-direction:column;gap:16px;transition:opacity 0.6s ease,background 0.6s ease;';
                var text=document.createElement('div');
                text.textContent=T.logging_out||'Logging out...';
                text.style.cssText='color:#fff;font-size:20px;font-weight:600;letter-spacing:0.5px;opacity:0;transition:opacity 0.5s ease 0.2s;';
                overlay.appendChild(text);
                document.body.appendChild(overlay);
                requestAnimationFrame(function(){
                    overlay.style.background='rgba(0,0,0,0.85)';
                    overlay.style.opacity='1';
                    text.style.opacity='1';
                });
                setTimeout(function(){
                    var input=document.createElement('input');
                    input.type='hidden';input.name='logout';input.value='1';
                    logoutForm.appendChild(input);
                    logoutForm.submit();
                },800);
            });
        })();
        if(emailButton){emailButton.addEventListener('click',()=>{closeAllModals();if(emailModal){emailModal.style.display='flex';}});if(closeEmail){closeEmail.addEventListener('click',()=>{if(emailModal){emailModal.style.display='none';}});}if(emailModal){emailModal.addEventListener('click',(e)=>{if(e.target===emailModal){emailModal.style.display='none';}});}}
        if(usersButton){usersButton.addEventListener('click',()=>{closeAllModals();if(usersModal){usersModal.style.display='flex';}});if(closeUsers){closeUsers.addEventListener('click',()=>{if(usersModal){usersModal.style.display='none';}});}if(usersModal){usersModal.addEventListener('click',(e)=>{if(e.target===usersModal){usersModal.style.display='none';}});}}
        if(sessionsButton){sessionsButton.addEventListener('click',()=>{closeAllModals();if(sessionsModal){sessionsModal.style.display='flex';}});if(closeSessions){closeSessions.addEventListener('click',()=>{if(sessionsModal){sessionsModal.style.display='none';}});}if(sessionsModal){sessionsModal.addEventListener('click',(e)=>{if(e.target===sessionsModal){sessionsModal.style.display='none';}});}}
        const modalButtons=[qrButton,emailButton,usersButton,sessionsButton].filter(btn=>btn);modalButtons.forEach(button=>{if(button){button.addEventListener('click',(e)=>{e.preventDefault();e.stopPropagation();});}});
        if(menuButton){menuButton.addEventListener('click',()=>{ if(sidebar && sidebar.classList.contains('open')) closeSidebarMenu(); else if(sidebar) sidebar.classList.add('open'); syncAppPageChatButton(); });}
        if(settingsButton){settingsButton.addEventListener('click',()=>{closeAllModals();if(settingsModal){settingsModal.style.display='flex';}});}
        if(closeSettings){closeSettings.addEventListener('click',()=>{if(settingsModal)settingsModal.style.display='none';});}
        if(settingsModal){settingsModal.addEventListener('click',(e)=>{if(e.target===settingsModal){settingsModal.style.display='none';}});}
        (function(){
            function initCustomSelect(wrapId,inputId){
                var wrap=document.getElementById(wrapId);
                var input=document.getElementById(inputId);
                if(!wrap||!input)return;
                var trigger=wrap.querySelector('.custom-select-trigger');
                var dropdown=wrap.querySelector('.custom-select-dropdown');
                if(!trigger||!dropdown)return;
                var options=dropdown.querySelectorAll('.custom-select-option');
                function updateText(){
                    var val=input.value;
                    var txt='';
                    options.forEach(function(o){
                        if(o.getAttribute('data-value')===val){
                            txt=o.querySelector('span:first-child')?o.querySelector('span:first-child').textContent:o.querySelector('span').textContent;
                            o.classList.add('active');
                        }else{
                            o.classList.remove('active');
                        }
                    });
                    var textEl=trigger.querySelector('.custom-select-text');
                    textEl.textContent='';
                    var activeOpt=dropdown.querySelector('.custom-select-option[data-value="'+val+'"]');
                    textEl.appendChild(document.createTextNode(txt));
                    if(activeOpt){
                        var icon=activeOpt.querySelector('.custom-select-flag,svg');
                        if(icon)textEl.appendChild(icon.cloneNode(true));
                    }
                }
                function closeDropdown(){
                    wrap.classList.remove('open');
                    if(dropdown._portalParent){
                        dropdown.style.display='none';
                        dropdown._portalParent.appendChild(dropdown);
                        dropdown._portalParent=null;
                    }
                }
                function positionDropdown(){
                    var r=trigger.getBoundingClientRect();
                    dropdown.style.left=r.left+'px';
                    dropdown.style.top=(r.bottom+4)+'px';
                    dropdown.style.width=r.width+'px';
                    if(dropdown.id==='icon-sort-dropdown'){
                        dropdown.style.width='max-content';
                        var w=Math.min(Math.max(r.width,dropdown.scrollWidth+2),window.innerWidth-r.left-12);
                        dropdown.style.width=w+'px';
                    }
                    var dh=dropdown.offsetHeight||0;
                    if(r.bottom+4+dh>window.innerHeight-8){
                        dropdown.style.top=Math.max(8,r.top-dh-4)+'px';
                    }
                }
                function openDropdown(){
                    document.querySelectorAll('.custom-select-wrap.open').forEach(function(w){
                        if(w!==wrap){
                            if(w._closeCustomSelect)w._closeCustomSelect();
                            else w.classList.remove('open');
                        }
                    });
                    wrap.classList.add('open');
                    if(!dropdown._portalParent){
                        dropdown._portalParent=wrap;
                        document.body.appendChild(dropdown);
                    }
                    dropdown.style.display='block';
                    positionDropdown();
                }
                updateText();
                trigger.addEventListener('click',function(e){e.stopPropagation();
                    if(wrap.classList.contains('open'))closeDropdown();
                    else openDropdown();
                });
                options.forEach(function(opt){opt.addEventListener('click',function(){input.value=opt.getAttribute('data-value');updateText();closeDropdown();input.dispatchEvent(new Event('change',{bubbles:true}));});});
                wrap._closeCustomSelect=closeDropdown;
            }
            initCustomSelect('lang-select-wrap','lang-input');
            initCustomSelect('theme-select-wrap','theme-input');
            initCustomSelect('icon-sort-select-wrap','icon-sort-input');
            initCustomSelect('icon-style-select-wrap','icon-style-input');
            var themeInput=document.getElementById('theme-input');
            if(themeInput)themeInput.addEventListener('change',function(){applyThemeRuntime(themeInput.value);});
            document.addEventListener('click',function(e){
                document.querySelectorAll('.custom-select-wrap.open').forEach(function(w){
                    if(w.contains(e.target))return;
                    if(e.target&&e.target.closest&&e.target.closest('.custom-select-dropdown'))return;
                    if(w._closeCustomSelect)w._closeCustomSelect();
                    else w.classList.remove('open');
                });
            });
            window.addEventListener('resize',function(){
                document.querySelectorAll('.custom-select-wrap.open').forEach(function(w){
                    var t=w.querySelector('.custom-select-trigger');
                    var id=(w.id||'').replace('-wrap','-dropdown');
                    var d=document.getElementById(id);
                    if(!t||!d||d.parentElement!==document.body)return;
                    var r=t.getBoundingClientRect();
                    d.style.left=r.left+'px';
                    d.style.top=(r.bottom+4)+'px';
                    d.style.width=r.width+'px';
                });
            });
            var wallpaperModal=document.getElementById('wallpaper-modal');
            var wallpaperOpenBtn=document.getElementById('wallpaper-open-btn');
            var closeWallpaperModal=document.getElementById('close-wallpaper-modal');
            var wallpaperPicker=document.getElementById('wallpaper-picker');
            var wallpaperInput=document.getElementById('wallpaper-input');
            function updateWallpaperPreviewBtn(val){
                if(!wallpaperOpenBtn)return;
                var preview=wallpaperOpenBtn.querySelector('.wallpaper-open-btn-preview');
                var opt=wallpaperPicker?wallpaperPicker.querySelector('.wallpaper-option[data-value="'+val+'"]'):null;
                var bg=opt&&opt.getAttribute('style')?((opt.getAttribute('style').match(/background-image:[^;]+/)||[])[0]||''):'';
                if(!preview&&bg){
                    preview=document.createElement('span');
                    preview.className='wallpaper-open-btn-preview';
                    wallpaperOpenBtn.insertBefore(preview,wallpaperOpenBtn.firstChild);
                }
                if(preview){
                    if(bg){preview.setAttribute('style',bg);preview.style.display='';}
                    else preview.remove();
                }
            }
            if(wallpaperOpenBtn&&wallpaperModal){wallpaperOpenBtn.addEventListener('click',function(){wallpaperModal.style.display='flex';});}
            if(closeWallpaperModal&&wallpaperModal){closeWallpaperModal.addEventListener('click',function(){wallpaperModal.style.display='none';});}
            if(wallpaperModal){wallpaperModal.addEventListener('click',function(e){if(e.target===wallpaperModal)wallpaperModal.style.display='none';});}
            if(wallpaperPicker&&wallpaperInput){
                wallpaperPicker.addEventListener('click',function(e){
                    var btn=e.target.closest('.wallpaper-option');
                    if(!btn)return;
                    wallpaperPicker.querySelectorAll('.wallpaper-option').forEach(function(b){b.classList.remove('active');});
                    btn.classList.add('active');
                    var val=btn.getAttribute('data-value')||'default';
                    wallpaperInput.value=val;
                    updateWallpaperPreviewBtn(val);
                    if(wallpaperModal)wallpaperModal.style.display='none';
                });
                updateWallpaperPreviewBtn(wallpaperInput.value||'default');
            }
        })();
        (function(){
            var pop=document.getElementById('app-desc-popover');
            var grid=document.getElementById('app-grid');
            if(!pop||!grid)return;
            var hideTimer=null;
            var showTimer=null;
            function hidePop(){if(hideTimer){clearTimeout(hideTimer);hideTimer=null;}if(showTimer){clearTimeout(showTimer);showTimer=null;}pop.classList.remove('visible');pop.classList.remove('left-side','right-side');}
            function show(el){
                var desc=el.getAttribute('data-app-desc')||'';
                var textEl=pop.querySelector('.app-desc-text');
                if(!desc||!textEl){hidePop();return;}
                textEl.textContent=desc;
                var r=el.getBoundingClientRect();
                var pw=pop.offsetWidth||260;
                var ph=pop.offsetHeight||48;
                var viewportWidth=window.innerWidth;
                var tileCenter=r.left+r.width/2;
                var isRightSide=tileCenter>viewportWidth/2;
                var left=r.right+14;
                var placeLeft=(left+pw>viewportWidth-8)||isRightSide;
                if(placeLeft){
                    left=Math.max(8,r.left-pw-14);
                    pop.classList.add('right-side');
                    pop.classList.remove('left-side');
                }else{
                    pop.classList.add('left-side');
                    pop.classList.remove('right-side');
                }
                var top=r.top+r.height/2-ph/2;
                top=Math.min(window.innerHeight-ph-8, Math.max(8, top));
                pop.style.left=left+'px';
                pop.style.top=top+'px';
                pop.classList.add('visible');
            }
            grid.addEventListener('mouseover',function(e){
                var tile=e.target.closest('.app-tile-link');
                if(tile&&grid.contains(tile)){
                    if(hideTimer){clearTimeout(hideTimer);hideTimer=null;}
                    if(showTimer){clearTimeout(showTimer);}
                    showTimer=setTimeout(function(){show(tile);},2500);
                    return;
                }
                hidePop();
            });
            grid.addEventListener('mouseleave',function(){hidePop();});
        })();
        if(shortcutsHelpButton){shortcutsHelpButton.addEventListener('click',()=>{if(shortcutsModal){shortcutsModal.style.display='flex';}});}
        if(closeShortcuts){closeShortcuts.addEventListener('click',()=>{if(shortcutsModal)shortcutsModal.style.display='none';});}
        if(shortcutsModal){shortcutsModal.addEventListener('click',(e)=>{if(e.target===shortcutsModal){shortcutsModal.style.display='none';}});}
        function isTypingContext(target){
            if(!target) return false;
            if(target.closest && target.closest('.CodeMirror, .monaco-editor, .handsontableInputHolder, #chat-text-editor-modal')) return true;
            const tag = target.tagName ? target.tagName.toLowerCase() : '';
            return tag === 'input' || tag === 'textarea' || tag === 'select' || !!target.isContentEditable;
        }
        function isAnyBlockingModalOpen(){
            const modals = [settingsModal, qrModal, emailModal, usersModal, profileModal, sessionsModal, sharedFilesModal, authModal, shortcutsModal, document.getElementById('mcp-setup-modal')];
            const np = document.getElementById('news-portal-overlay');
            return modals.some(modal => modal && modal.style.display === 'flex') || (np && np.classList.contains('active'));
        }
        function openShortcutsModal(){
            closeAllModals();
            if(shortcutsModal){
                shortcutsModal.style.display = 'flex';
            }
        }
        function closeEscapableModals(){
            const escapableModalIds = [
                'settings-modal',
                'shortcuts-modal',
                'qr-modal',
                'email-modal',
                'users-modal',
                'profile-modal',
                'sessions-modal',
                'shared-files-modal',
                'auth-modal',
                'delete-user-modal',
                'reset-user-password-modal',
                'tg-bot-modal',
                'support-chat-modal',
                'mcp-setup-modal',
                'otp-gen-modal'
            ];
            let closedAny = false;
            escapableModalIds.forEach(function(id){
                const modal = document.getElementById(id);
                if(modal && modal.style.display === 'flex'){
                    modal.style.display = 'none';
                    closedAny = true;
                }
            });
            return closedAny;
        }
        document.addEventListener('keydown', function(e){
            const code = e.code || '';
            if(code === 'Escape'){
                let handled = false;
                if(sidebar && sidebar.classList.contains('open') && searchBar && searchBar.value.trim() !== ''){
                    clearAppSearch(false);
                    handled = true;
                } else {
                if(selectedAppTileIndex >= 0){
                    setSelectedAppTile(-1);
                    handled = true;
                }
                if(closeSidebarMenu()){
                    handled = true;
                }
                if(closeEscapableModals()){
                    handled = true;
                }
                }
                if(handled){
                    e.preventDefault();
                }
                return;
            }
            if(e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;
            if(isTypingContext(e.target) || isAnyBlockingModalOpen()) return;
            if(!code) return;
            if(code === 'KeyM' && typeof window.openMcpSetupModal === 'function'){
                e.preventDefault();
                window.openMcpSetupModal();
                return;
            }
            if(!isHomeDashboard) return;
            if(code === 'ArrowLeft' || code === 'ArrowRight' || code === 'ArrowUp' || code === 'ArrowDown'){
                e.preventDefault();
                const directionMap = {
                    ArrowLeft: 'left',
                    ArrowRight: 'right',
                    ArrowUp: 'up',
                    ArrowDown: 'down'
                };
                setSelectedAppTile(pickNextTileIndex(directionMap[code]));
                return;
            }
            if(code === 'Enter' && selectedAppTileIndex >= 0){
                const tiles = getAppTileLinks();
                if(tiles[selectedAppTileIndex]){
                    e.preventDefault();
                    tiles[selectedAppTileIndex].click();
                    return;
                }
            }
            if(code === 'Slash'){
                e.preventDefault();
                if(sidebar && !sidebar.classList.contains('open')){
                    sidebar.classList.add('open');
                    syncAppPageChatButton();
                }
                if(searchBar){
                    setTimeout(function(){
                        searchBar.focus();
                        searchBar.select();
                    }, 0);
                }
                return;
            }
            if(code === 'KeyA' && isAdminUser && document.getElementById('open-admin-panel-btn')){
                e.preventDefault();
                document.getElementById('open-admin-panel-btn').click();
                return;
            }
            if(code === 'KeyS' && sessionsButton){
                e.preventDefault();
                sessionsButton.click();
                return;
            }
            if(code === 'KeyF' && document.getElementById('shared-files-button')){
                e.preventDefault();
                document.getElementById('shared-files-button').click();
                return;
            }
            if(code === 'KeyQ' && qrButton){
                e.preventDefault();
                qrButton.click();
                return;
            }
            if(code === 'KeyN' && typeof window.openNewsPortal === 'function'){
                e.preventDefault();
                window.openNewsPortal();
                return;
            }
            if(code === 'KeyP' && userProfile){
                e.preventDefault();
                userProfile.click();
                return;
            }
            if(code === 'KeyH' && document.getElementById('support-chat-btn')){
                e.preventDefault();
                document.getElementById('support-chat-btn').click();
                return;
            }
            if(code === 'KeyI'){
                e.preventDefault();
                openShortcutsModal();
            }
        });
        if(appGrid){
            appGrid.addEventListener('click', function(e){
                const tile = e.target.closest('.app-tile-link');
                if(!tile) return;
                const tiles = getAppTileLinks();
                const index = tiles.indexOf(tile);
                if(index >= 0) setSelectedAppTile(index);
            });
        }
        if(closeQr){closeQr.addEventListener('click',()=>{qrModal.style.display='none';});}
        if(searchBar){
            searchBar.addEventListener('input',(e)=>{
                const query=e.target.value.toLowerCase();
                appItems.forEach(item=>{
                    const text=item.textContent.toLowerCase();
                    const match = text.includes(query);
                    item.style.display=match?'block':'none';
                });
                if(clearSearch) clearSearch.style.display = query ? 'flex' : 'none';
                const groups=appList.querySelectorAll('.group-block');
                groups.forEach(group=>{
                    const visibleItems=Array.from(group.querySelectorAll('li')).filter(li=>li.style.display!=='none');
                    group.style.display=visibleItems.length>0?'block':'none';
                    if(group.previousElementSibling&&group.previousElementSibling.classList.contains('group-letter')){
                        group.previousElementSibling.style.display=visibleItems.length>0?'block':'none';
                    }
                });
                syncAppPageChatButton();
            });
        }
        if(clearSearch){
            clearSearch.addEventListener('click',()=>{
                if(searchBar){
                    clearAppSearch(true);
                }
            });
        }
        function expandAppSearch(){
            if(!searchContainer||!searchContainer.parentElement) return false;
            const bar=searchContainer.parentElement;
            const actions=bar.querySelector('.bottom-bar-actions');
            const gap=parseFloat(getComputedStyle(bar).gap)||0;
            const barRect=bar.getBoundingClientRect();
            const containerRect=searchContainer.getBoundingClientRect();
            let avail=barRect.right-containerRect.left;
            if(actions){
                const actionsRect=actions.getBoundingClientRect();
                avail=actionsRect.left-containerRect.left-gap;
            }
            avail=Math.max(36,Math.min(avail,barRect.width-8));
            searchContainer.style.width=avail+'px';
            searchContainer.classList.add('open');
            return true;
        }
        function collapseAppSearch(){
            if(!searchContainer) return;
            searchContainer.classList.remove('open');
            searchContainer.style.width='';
        }
        if(searchContainer){
            searchContainer.addEventListener('click',function(e){
                if(searchContainer.classList.contains('open')&&e.target===searchBar){
                    return;
                }
                expandAppSearch();
                if(searchBar) searchBar.focus();
            });
        }
        if(searchBar){
            searchBar.addEventListener('focus',function(){
                expandAppSearch();
                syncAppPageChatButton();
            });
            searchBar.addEventListener('blur',function(){
                collapseAppSearch();
                syncAppPageChatButton();
            });
            searchBar.addEventListener('keydown',function(e){
                if(e.key==='Escape'){
                    searchBar.blur();
                }
            });
        }
        window.addEventListener('resize',function(){
            if(searchContainer&&searchContainer.classList.contains('open')){
                expandAppSearch();
            }
            syncAppPageChatButton();
        });
        if(searchContainer){
            searchContainer.addEventListener('mouseenter',syncAppPageChatButton);
            searchContainer.addEventListener('mouseleave',function(){setTimeout(syncAppPageChatButton, 450);});
        }
        syncAppPageChatButton();
        document.querySelectorAll('#app-list a, a[href="index.php"]').forEach(link=>{link.addEventListener('click',()=>{closeSidebarMenu();});});
        function showNotification(){const notification=document.getElementById('domain-notification');if(notification){setTimeout(()=>{notification.classList.add('show');},100);setTimeout(()=>{notification.classList.remove('show');setTimeout(()=>{if(notification.parentNode){notification.remove();}},400);},4000);}}
        document.addEventListener('click',(e)=>{
            if(!sidebar || !menuButton) return;
            if(!sidebar.contains(e.target) && !menuButton.contains(e.target) && sidebar.classList.contains('open')){
                const modals=[settingsModal,qrModal,emailModal,usersModal,profileModal,sessionsModal,authModal];
                let isModalClick=false;
                modals.forEach(modal=>{if(modal&&modal.contains(e.target)){isModalClick=true;}});
                if(!isModalClick){closeSidebarMenu();}
            }
        });
    });
</script>

<?php require __DIR__ . '/modals/admin_panel.php'; ?>
<?php
// Collect all flash messages from this page load and emit them as push notifications
$flashMessages = [];
if(isset($_GET['oauth_linked'])){
    $flashMessages[] = ['msg'=>$translations['oauth_linked_success'] ?? 'Account linked successfully', 'type'=>'success'];
}
if(isset($_GET['oauth_error'])){
    $flashMessages[] = ['msg'=>(string)$_GET['oauth_error'], 'type'=>'error'];
}
if(isset($profileSuccess))       $flashMessages[] = ['msg'=>$profileSuccess,       'type'=>'success'];
if(isset($profileError))         $flashMessages[] = ['msg'=>$profileError,          'type'=>'error'];
if(isset($emailSettingsSuccess)) $flashMessages[] = ['msg'=>$emailSettingsSuccess,  'type'=>'success'];
if(isset($emailSettingsError))   $flashMessages[] = ['msg'=>$emailSettingsError,   'type'=>'error'];
if(isset($userManagementSuccess))$flashMessages[] = ['msg'=>$userManagementSuccess, 'type'=>'success'];
if(isset($sessionManagementSuccess)) $flashMessages[] = ['msg'=>$sessionManagementSuccess,'type'=>'success'];
if(isset($sharedFilesSuccess))   $flashMessages[] = ['msg'=>$sharedFilesSuccess,   'type'=>'success'];
if(isset($trustedDeviceRestoreNotice) && $trustedDeviceRestoreNotice !== '') $flashMessages[] = ['msg'=>$trustedDeviceRestoreNotice, 'type'=>'error'];
if(!empty($flashMessages)):
$openPasswordOnLoad = isset($_POST['change_password']) && (isset($profileError) || isset($profileSuccess));
$openPhotoOnLoad = isset($_POST['change_photo']) && (isset($profileError) || isset($profileSuccess));
$openProfileOnLoad = isset($_GET['profile']) || isset($_POST['reset_photo']) || isset($_POST['oauth_unlink']) || isset($_GET['oauth_linked']) || isset($_GET['oauth_error']);
?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    if(typeof showTempNotification !== 'function') return;
    var flashes = <?php echo json_encode($flashMessages); ?>;
    flashes.forEach(function(f, i){
        setTimeout(function(){ showTempNotification(f.msg, f.type); }, i * 500);
    });
    <?php if($openPasswordOnLoad): ?>
    var profileModal=document.getElementById('profile-modal');
    var passwordChangeBox=document.getElementById('password-change-box');
    if(profileModal) profileModal.style.display='flex';
    if(passwordChangeBox) passwordChangeBox.classList.add('active');
    <?php elseif($openPhotoOnLoad): ?>
    var profileModal=document.getElementById('profile-modal');
    var photoForm=document.getElementById('photo-form');
    var resetPhotoForm=document.getElementById('reset-photo-form');
    if(profileModal) profileModal.style.display='flex';
    if(photoForm) photoForm.classList.add('active');
    if(resetPhotoForm) resetPhotoForm.classList.add('active');
    <?php elseif($openProfileOnLoad): ?>
    var profileModal=document.getElementById('profile-modal');
    if(profileModal) profileModal.style.display='flex';
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/modals/utility_modals.php'; ?>
<?php require __DIR__ . '/modals/mcp_setup.php'; ?>
<?php require __DIR__ . '/modals/support_chat_logic.php'; ?>
<script>
(function(){
function injectAppMobileChrome(){
var iframe=document.getElementById('app-iframe');
if(!iframe)return;
iframe.classList.add('mobile-vv-fill');
function inject(){
try{
var doc=iframe.contentDocument||iframe.contentWindow.document;
if(window.MobileBrowserChrome&&typeof window.MobileBrowserChrome.inject==='function')window.MobileBrowserChrome.inject(doc);
}catch(e){}
}
iframe.addEventListener('load',inject);
if(iframe.contentDocument&&iframe.contentDocument.readyState!=='loading')inject();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',injectAppMobileChrome);
else injectAppMobileChrome();
})();
</script>

<?php require __DIR__ . '/modals/weather_modals.php'; ?>
<?php
if ($_guideEnabled):
    require_once __DIR__ . '/../onboarding/guide_lang.php';
    $_guideStrings = onboarding_strings($language);
    if (!empty($_guideCfg['dev_force_start'])) {
        $_guideAuto = true;
    } else {
        $_guideAuto = (int)($_SESSION['user']['role'] ?? 0) < 2
            && (!empty($_SESSION['user']['onboarding_pending']) && empty($_SESSION['user']['onboarding_done']));
    }
?>
<link rel="stylesheet" href="/project/onboarding/guide.css?v=<?php echo filemtime(__DIR__ . '/../onboarding/guide.css'); ?>">
<link rel="stylesheet" href="/project/assets/weather.css">
<script>window.__guideData = <?php echo json_encode($_guideStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;window.__guideForce = <?php echo empty($_guideCfg['dev_force_start']) ? 'false' : 'true'; ?>;window.__guideAuto = <?php echo $_guideAuto ? 'true' : 'false'; ?>;</script>
<script src="/project/onboarding/guide_core.js?v=<?php echo filemtime(__DIR__ . '/../onboarding/guide_core.js'); ?>" defer></script><!--
    if (weatherCountriesLoading) return;
    weatherCountriesLoading = true;
    const countrySelect = document.getElementById('weatherCountrySelect');
    countrySelect.innerHTML = '<option value="">' + escHtml(wt('weather_loading', 'Loading...')) + '</option>';
    countrySelect.disabled = true;

    weatherFetchJson('/project/weather_settings.php?action=countries')
        .then(data => {
            if (data.countries && data.countries.length) {
                weatherSettings.countries = data.countries;
                weatherCountriesLoaded = true;
                renderCountriesForSelect();
            } else {
                throw new Error('No countries');
            }
        })
        .catch(() => {
            countrySelect.innerHTML = '<option value="">' + escHtml(wt('weather_fetch_error', 'Failed to load countries')) + '</option>';
        })
        .finally(() => {
            weatherCountriesLoading = false;
            countrySelect.disabled = false;
        });
}

function renderCountriesForSelect() {
    const countrySelect = document.getElementById('weatherCountrySelect');
    countrySelect.innerHTML = '<option value="">' + escHtml(wt('weather_select_country', 'Select country')) + '</option>';

    weatherSettings.countries.forEach(country => {
        const option = document.createElement('option');
        option.value = country.iso2;
        option.textContent = country.name;
        option.dataset.name = country.name;
        if (weatherSettings.selected_country_code === country.iso2) {
            option.selected = true;
        }
        countrySelect.appendChild(option);
    });

    if (weatherSettings.selected_country_code) {
        loadCitiesForSelect('');
    }
}



    if (countryCode) {
        loadCitiesForSelect('');
    }
}

function loadCitiesForSelect(query) {
    if (!weatherSettings.selected_country_code) {
        const citySelect = document.getElementById('weatherCitySelect');
        citySelect.innerHTML = '<option value="">' + escHtml(wt('weather_select_city', 'Select city')) + '</option>';
        citySelect.disabled = true;
        return;
    }

    const citySelect = document.getElementById('weatherCitySelect');
    citySelect.innerHTML = '<option value="">' + escHtml(wt('weather_loading', 'Loading...')) + '</option>';
    citySelect.disabled = true;

    weatherFetchJson('/project/weather_settings.php?action=city_search&country=' + encodeURIComponent(weatherSettings.selected_country || '') + '&country_code=' + encodeURIComponent(String(weatherSettings.selected_country_code || '').toUpperCase()) + '&q=' + encodeURIComponent(query))
    .then(data => {
        if (data.cities) {
            weatherSettings.cities = data.cities;
            renderCitiesForSelect();
        } else {
            throw new Error('No cities');
        }
    })
    .catch(() => {
        citySelect.innerHTML = '<option value="">' + escHtml(wt('weather_fetch_error', 'Failed to load cities')) + '</option>';
    });
}

function renderCitiesForSelect() {
    const citySelect = document.getElementById('weatherCitySelect');
    const customSelectOptions = document.getElementById('weatherCityCustomSelect');
    if (!citySelect || !customSelectOptions) return;
    
    citySelect.innerHTML = '<option value="">' + escHtml(wt('weather_select_city', 'Select city')) + '</option>';
    customSelectOptions.innerHTML = '<div class="custom-select-option" data-value="" onclick="selectCustomOption(this, \'weatherCityCustomSelect\', \'weatherCitySelect\')">' + escHtml(wt('weather_select_city', 'Select city')) + '</div>';

    weatherSettings.cities.forEach(city => {
        const option = document.createElement('option');
        option.value = city.name + '|' + city.lat + '|' + city.lon;
        option.dataset.city = JSON.stringify(city);
        if (weatherSettings.selected_city === city.name && weatherSettings.selected_lat === city.lat && weatherSettings.selected_lon === city.lon) {
            option.selected = true;
        }
        option.textContent = city.name + (city.admin1 ? ', ' + city.admin1 : '') + ', ' + city.country;
        citySelect.appendChild(option);
        
        const customOption = document.createElement('div');
        customOption.className = 'custom-select-option';
        customOption.dataset.value = city.name + '|' + city.lat + '|' + city.lon;
        customOption.textContent = city.name + (city.admin1 ? ', ' + city.admin1 : '') + ', ' + city.country;
        customOption.onclick = function() { selectCustomOption(this, 'weatherCityCustomSelect', 'weatherCitySelect'); };
        customSelectOptions.appendChild(customOption);
    });

    // Update selected value in custom select
    const selectedCity = citySelect.querySelector('option:checked');
    if (selectedCity && selectedCity.value) {
        const customSelected = document.querySelector('#weatherCityCustomSelect .custom-select-option[data-value="' + selectedCity.value + '"]');
        if (customSelected) {
            document.querySelector('#weatherCityCustomSelect + .custom-select-selected span').textContent = customSelected.textContent;
        }
    }

    citySelect.disabled = false;
}



function restoreSavedCitySelection() {
    if (!weatherSettings.selected_city || weatherSettings.selected_lat == null || weatherSettings.selected_lon == null) return;

    const countrySelect = document.getElementById('weatherCountrySelect');
    if (countrySelect && weatherSettings.selected_country_code) {
        for (let i = 0; i < countrySelect.options.length; i++) {
            if (countrySelect.options[i].value === weatherSettings.selected_country_code) {
                countrySelect.selectedIndex = i;
                 onCountryChangeCustom(countrySelect);
                break;
            }
        }
    }
}
</script>-->
<?php endif; ?>
<script>
(function(){
  var frames = {
    app: { name: 'devtools-app', title: 'Application Shell', selector: 'iframe[name="devtools-app"]' },
    docker: { name: 'devtools-docker', title: 'Docker Manager', selector: 'iframe[name="devtools-docker"]' },
    performance: { name: 'devtools-performance', title: 'Performance Monitor', selector: 'iframe[name="devtools-performance"]' },
    backup: { name: 'devtools-backup', title: 'Backup Dashboard', selector: 'iframe[name="devtools-backup"]' },
    docs: { name: 'devtools-docs', title: 'Documentation', selector: 'iframe[name="devtools-docs"]' },
    db: { name: 'devtools-db', title: 'Database Admin', selector: 'iframe[name="devtools-db"]' },
    omniroute: { name: 'devtools-omniroute', title: 'OmniRoute', selector: 'iframe[name="devtools-omniroute"]' }
  };

  window.__devtools = {
    frames: frames,
    list: function() {
      var result = [];
      Object.keys(frames).forEach(function(key) {
        var f = frames[key];
        var el = document.querySelector(f.selector);
        result.push({
          key: key,
          name: f.name,
          title: f.title,
          loaded: el ? !!el.contentWindow : false,
          url: el ? el.src : 'not found'
        });
      });
      console.table(result);
      return result;
    },
    focus: function(key) {
      var f = frames[key];
      if (!f) { console.error('Unknown frame: ' + key + '. Use __devtools.list() to see options.'); return; }
      var el = document.querySelector(f.selector);
      if (!el) { console.error('Frame not found in DOM: ' + f.selector); return; }
      try {
        el.contentWindow.focus();
        console.log('Focused: ' + f.title + ' (' + f.name + ')');
      } catch(e) {
        console.log('Frame found but cross-origin: ' + f.title + '. URL: ' + el.src);
      }
    },
    inspect: function(key) {
      var f = frames[key];
      if (!f) { console.error('Unknown frame: ' + key); return; }
      var el = document.querySelector(f.selector);
      if (!el) { console.error('Frame not found: ' + f.selector); return; }
      console.log('Frame: ' + f.title);
      console.log('Name: ' + f.name);
      console.log('URL: ' + el.src);
      console.log('Dimensions: ' + el.offsetWidth + 'x' + el.offsetHeight);
      console.log('Tip: In DevTools, use the frame selector dropdown (top of Elements panel) to switch to "' + f.name + '"');
    },
    help: function() {
      console.log('=== DevTools Frame Helper ===');
      console.log('Commands:');
      console.log('  __devtools.list()          - List all frames with status');
      console.log('  __devtools.focus("key")    - Focus a frame (app, docker, performance, backup, docs, db, omniroute)');
      console.log('  __devtools.inspect("key")  - Show frame details');
      console.log('  __devtools.help()          - Show this help');
      console.log('');
      console.log('Keyboard shortcuts (Ctrl+Shift+):');
      console.log('  1 = App Shell    2 = Docker      3 = Performance');
      console.log('  4 = Backup       5 = Docs        6 = Database');
      console.log('  7 = OmniRoute');
      console.log('');
      console.log('DevTips:');
      console.log('  - Elements panel > top dropdown > select frame name to inspect inside');
      console.log('  - Console panel > dropdown > switch to frame context to run JS inside');
      console.log('  - Each iframe now has name+title attributes for easy identification');
    }
  };

  var keyMap = { '1': 'app', '2': 'docker', '3': 'performance', '4': 'backup', '5': 'docs', '6': 'db', '7': 'omniroute' };
  document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && keyMap[e.key]) {
      e.preventDefault();
      window.__devtools.focus(keyMap[e.key]);
    }
  });

  console.log('%c[DevTools Helper] Ready! Type __devtools.help() for commands.', 'color: #22c55e; font-weight: bold;');
})();
</script>
</body>
</html>
