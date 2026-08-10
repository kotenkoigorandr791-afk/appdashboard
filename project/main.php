<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
    <title><?php echo my_htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" href="icon.png" type="image/png">
    <?php require __DIR__ . '/../mobile_chrome.php'; ?>
    <?php require_once __DIR__ . '/../browser-icons.php'; ?>
    <?php if($path==='/main' && !$showLogin): ?>
    <script src="https://unpkg.com/qrcodejs@1.0.0/qrcode.min.js" defer></script>
    <?php endif; ?>
    <style>
        :root{--bg-color:<?php echo $config['theme']==='dark'?'#333':'#fff';?>;--text-color:<?php echo $config['theme']==='dark'?'#fff':'#1f2937';?>;--primary-color:<?php echo my_htmlspecialchars($config['color']);?>;--sidebar-bg:<?php echo $config['theme']==='dark'?'#444':'#f0f0f0';?>;--group-block-bg:<?php echo $config['theme']==='dark'?'#555':'#e9e9e9';?>;--app-item-bg:<?php echo $config['theme']==='dark'?'#666':'#d9d9d9';?>;--tooltip-bg:<?php echo $config['theme']==='dark'?'#666':'#333';?>;--modal-bg:<?php echo $config['theme']==='dark'?'#555':'#fff';?>;--border-color:<?php echo $config['theme']==='dark'?'#666':'#ccc';?>;--icon-bg-color:<?php echo $config['theme']==='dark'?'#444':'#f0f0f0';?>;--link-color:<?php echo $config['theme']==='dark'?'#ddd':'#333';?>;--link-hover-color:var(--primary-color);--table-header-bg:<?php echo $config['theme']==='dark'?'#444':'#f0f0f0';?>;--table-row-hover:<?php echo $config['theme']==='dark'?'#555':'#f5f5f5';?>;--scrollbar-track:<?php echo $config['theme']==='dark'?'#222':'#f0f0f0';?>;--scrollbar-thumb:<?php echo $config['theme']==='dark'?'#555':'#c0c0c0';?>;--scrollbar-thumb-hover:<?php echo $config['theme']==='dark'?'#777':'#999';?>;--app-icon-grad-start:<?php echo $config['theme']==='dark'?'color-mix(in srgb,var(--primary-color) 72%,white)':'color-mix(in srgb,var(--primary-color) 84%,black)';?>;--app-icon-grad-end:<?php echo $config['theme']==='dark'?'color-mix(in srgb,var(--primary-color) 88%,black)':'color-mix(in srgb,var(--primary-color) 65%,black)';?>;--shell-menu-button-left:<?php echo (int)($selectedAppMenuButton['left']??20); ?>px;--shell-menu-button-top:<?php echo (int)($selectedAppMenuButton['top']??13); ?>px;--shell-menu-button-width:<?php echo (int)($selectedAppMenuButton['width']??40); ?>px;--shell-menu-button-height:<?php echo (int)($selectedAppMenuButton['height']??40); ?>px;--shell-menu-button-mobile-left:<?php echo (int)($selectedAppMenuButtonMobile['left']??($selectedAppMenuButton['left']??20)); ?>px;--shell-menu-button-mobile-top:<?php echo (int)($selectedAppMenuButtonMobile['top']??($selectedAppMenuButton['top']??13)); ?>px;--shell-menu-button-mobile-width:<?php echo (int)($selectedAppMenuButtonMobile['width']??($selectedAppMenuButton['width']??40)); ?>px;--shell-menu-button-mobile-height:<?php echo (int)($selectedAppMenuButtonMobile['height']??($selectedAppMenuButton['height']??40)); ?>px;}
        /* Global styled scrollbars */
        *{scrollbar-width:thin;scrollbar-color:var(--scrollbar-thumb) var(--scrollbar-track);}
        *::-webkit-scrollbar{width:6px;height:6px;}
        *::-webkit-scrollbar-track{background:var(--scrollbar-track);border-radius:3px;}
        *::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb);border-radius:3px;}
        *::-webkit-scrollbar-thumb:hover{background:var(--scrollbar-thumb-hover);}
        *::-webkit-scrollbar-corner{background:var(--scrollbar-track);}
        <?php if($showLogin): ?>
        #auth-modal{display:flex;position:fixed;top:0;left:0;width:100%;height:100%;justify-content:center;align-items:center;z-index:2000;}
        #auth-modal .gradient-bg{position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(135deg, var(--primary-color) 0%, <?php echo $config['theme']==='dark'?'#000000':'#ffffff';?> 50%, var(--primary-color) 100%);opacity:0.8;z-index:-1;}
        #auth-form{background:var(--modal-bg);padding:30px;border-radius:12px;color:var(--text-color);width:90%;max-width:400px;box-shadow:0 10px 30px rgba(0,0,0,0.3);max-height:90vh;overflow-y:auto;position:relative;z-index:2001;}
        #auth-form .back-button{position:static;margin:20px auto 0;background:var(--primary-color);color:white;border:none;padding:12px 20px;border-radius:24px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:600;transition:all 0.3s ease;width:auto;min-width:200px;}
        #auth-form .back-button:hover{opacity:0.9;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
        #auth-form .back-button svg{width:20px;height:20px;fill:white;}
        .auth-tabs{display:flex;margin-bottom:20px;border-bottom:1px solid var(--border-color);}
        .auth-tab{flex:1;padding:12px;text-align:center;background:none;border:none;color:var(--text-color);cursor:pointer;border-bottom:2px solid transparent;}
        .auth-tab.active{border-bottom-color:var(--primary-color);color:var(--primary-color);}
        .auth-content{display:none;}
        .auth-content.active{display:block;}
        <?php endif; ?>
        .auth-input{width:100%;padding:12px;margin-bottom:15px;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--text-color);box-sizing:border-box;}
        .auth-button{width:100%;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:24px;cursor:pointer;margin-bottom:10px;}
        .auth-button:hover{opacity:0.9;}
        .auth-link{color:var(--primary-color);cursor:pointer;text-decoration:underline;}
        .auth-error{color:#ff4444;margin-bottom:15px;text-align:center;}
        .auth-success{color:#00C851;margin-bottom:15px;text-align:center;}
        <?php if($showLogin): ?>
        .google-user-info{display:flex;flex-direction:column;align-items:center;margin:20px 0;padding:20px;background:var(--group-block-bg);border-radius:8px;}
        .google-avatar{width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--primary-color);margin-bottom:10px;}
        .google-email{font-weight:bold;margin-bottom:5px;}
        .google-name{color:var(--text-color);opacity:0.8;}
        <?php endif; ?>
        .password-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--primary-color);cursor:pointer;padding:5px;border-radius:4px;transition:background-color 0.3s;z-index:5;}
        .password-toggle:hover{background-color:rgba(0,0,0,0.1);}
        .password-toggle:active{background-color:rgba(0,0,0,0.2);}
        .password-input-container{position:relative;margin-bottom:15px;}
        .password-input-container .auth-input{padding-right:40px;margin-bottom:0;}
        .password-input-row{display:flex;align-items:stretch;gap:8px;margin-bottom:15px;}
        .password-input-row .password-input-container{flex:1;margin-bottom:0;}
        .password-generate-btn{flex-shrink:0;width:44px;min-height:44px;align-self:stretch;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--primary-color);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;transition:background-color 0.2s,opacity 0.2s;}
        .password-generate-btn:hover:not(:disabled){background:var(--group-block-bg);}
        .password-generate-btn:disabled{opacity:0.45;cursor:not-allowed;}
        .password-toggle svg{fill:var(--primary-color);}
        .user-profile{display:flex;align-items:center;gap:8px;cursor:pointer;padding:4px 8px;border-radius:20px;transition:background-color 0.3s;position:relative;height:40px;overflow:visible;flex-shrink:0;}
        .user-profile:hover{background-color:var(--group-block-bg);}
        .user-avatar{width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid var(--primary-color);flex-shrink:0;}
        .user-name{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:var(--tooltip-bg);color:white;padding:4px 8px;border-radius:4px;display:none;z-index:10000;margin-bottom:8px;white-space:nowrap;pointer-events:none;box-shadow:0 2px 10px rgba(0,0,0,0.2);}
        .user-profile:hover .user-name{display:block;}
        .user-name::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:var(--tooltip-bg);}
        .logout-button{background:#ff4444;color:white;border:none;padding:10px 15px;border-radius:24px;cursor:pointer;width:100%;margin-top:10px;display:flex;align-items:center;justify-content:center;gap:8px;}
        .change-password-button{background:var(--primary-color);color:white;border:none;padding:12px 20px;border-radius:24px;cursor:pointer;width:60%;max-width:300px;margin:10px auto;display:flex;align-items:center;justify-content:center;gap:10px;font-weight:600;transition:all 0.3s ease;}
        .change-password-button:hover{opacity:0.9;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
        .change-photo-button{background:var(--primary-color);color:white;border:none;padding:12px 20px;border-radius:24px;cursor:pointer;width:60%;max-width:300px;margin:10px auto;display:flex;align-items:center;justify-content:center;gap:10px;font-weight:600;transition:all 0.3s ease;}
        .change-photo-button:hover{opacity:0.9;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
        #profile-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);justify-content:center;align-items:center;z-index:10000;}
        #profile-content{background:var(--modal-bg);padding:30px;border-radius:12px;color:var(--text-color);width:50%;max-width:500px;max-height:90vh;overflow-y:auto;position:relative;margin:2% auto;z-index:10001;}
        @media (max-width: 768px) {
            #profile-content { margin: 5% auto; width: 95%; }
            #edit-stats-content, #stats-content, #shared-files-content, #sessions-content, #users-content { margin: 5% auto; }
        }
        #close-profile{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
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
        .password-change-box{display:none;margin-top:15px;padding:18px;background:var(--group-block-bg);border:1px solid var(--border-color);border-radius:8px;width:100%;max-width:400px;box-sizing:border-box;}
        .password-change-box.active{display:block;}
        .password-change-box .password-form{display:block;margin:0;padding:0;background:transparent;border-radius:0;width:100%;max-width:none;}
        .password-verify-msg{font-size:13px;margin:-6px 0 10px 0;min-height:18px;text-align:left;}
        .password-new-fields input:disabled{opacity:0.55;cursor:not-allowed;}
        body{margin:0;padding:0;font-family:Arial,sans-serif;background-color:var(--bg-color);color:var(--text-color);overflow:hidden;}
        html,body{margin:0;padding:0;overflow:hidden;height:100%;}
        #top-bar{position:fixed;top:0;left:0;width:100%;height:50px;background-color:var(--sidebar-bg);border-bottom:1px solid var(--border-color);display:flex;align-items:center;padding:0 16px 0 20px;box-sizing:border-box;z-index:998;gap:12px;}
        #top-bar-title{font-weight:bold;font-size:18px;margin-left:40px;flex:1;min-width:0;}
        #support-chat-btn{position:relative;top:auto;right:auto;margin-left:auto;flex-shrink:0;width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 28%,transparent) 0%,color-mix(in srgb,var(--primary-color) 14%,transparent) 100%);color:var(--primary-color);border:1px solid color-mix(in srgb,var(--primary-color) 35%,transparent);box-shadow:0 2px 10px color-mix(in srgb,var(--primary-color) 22%,transparent);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.2s,box-shadow 0.2s,background 0.2s;backdrop-filter:blur(8px);}
        #support-chat-btn:hover{transform:scale(1.06);box-shadow:0 4px 14px color-mix(in srgb,var(--primary-color) 30%,transparent);background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 40%,transparent) 0%,color-mix(in srgb,var(--primary-color) 22%,transparent) 100%);}
        #support-chat-btn svg{width:22px;height:22px;stroke:currentColor;}
	        #menu-button{position:fixed;top:var(--shell-menu-button-top);left:var(--shell-menu-button-left);cursor:pointer;color:var(--text-color);z-index:1002;padding:0;border:none;outline:none;width:var(--shell-menu-button-width);height:var(--shell-menu-button-height);}
	        <?php if(!empty($selectedAppMenuButtonScroll)): ?>#menu-button{position:absolute;}<?php endif; ?>
	        <?php foreach(($selectedAppMenuButtonStyle??[]) as $prop=>$value): ?>#menu-button{<?php echo my_htmlspecialchars((string)$prop); ?>:<?php echo my_htmlspecialchars((string)$value); ?>;}<?php endforeach; ?>
	        #menu-button .menu-button-glyph{display:flex;align-items:center;justify-content:center;line-height:1;}
        <?php if($isHomeDashboard): ?>
        #top-bar{justify-content:flex-start;}
        #top-bar-title{flex:1 1 auto;margin-left:0;text-align:center;}
        #menu-button{position:static;background:none;width:36px;height:36px;box-shadow:none;backdrop-filter:none;-webkit-backdrop-filter:none;transform:none;display:flex;align-items:center;justify-content:center;border-radius:50%;}
        #home-root-button{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--text-color);text-decoration:none;background:none;flex:0 0 auto;margin-left:0;}
        #home-root-button svg{width:21px;height:21px;}
        #home-root-button:hover,#menu-button:hover{background:var(--group-block-bg);opacity:1;}
        #top-bar #support-chat-btn{position:absolute;right:16px;top:5px;margin-left:0;}
        #menu-button .menu-button-glyph{width:auto;height:auto;font-size:24px;transform:none;text-shadow:0 2px 4px rgba(0,0,0,0.2);}
        <?php else: ?>
        #menu-button{width:var(--shell-menu-button-width);height:var(--shell-menu-button-height);display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--sidebar-bg) 36%,transparent);border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,0.1);backdrop-filter:blur(16px) saturate(140%);-webkit-backdrop-filter:blur(16px) saturate(140%);transform:none;}
        #menu-button .menu-button-glyph{width:100%;height:100%;font-size:calc(var(--shell-menu-button-height) * 0.5);transform:translateY(-1px);text-shadow:0 2px 4px rgba(0,0,0,0.16);}
	        @media (max-width:768px){
	            #menu-button{top:var(--shell-menu-button-mobile-top);left:var(--shell-menu-button-mobile-left);width:var(--shell-menu-button-mobile-width);height:var(--shell-menu-button-mobile-height);}
	            <?php foreach(($selectedAppMenuButtonMobileStyle??[]) as $prop=>$value): ?>#menu-button{<?php echo my_htmlspecialchars((string)$prop); ?>:<?php echo my_htmlspecialchars((string)$value); ?>;}<?php endforeach; ?>
	            #menu-button .menu-button-glyph{font-size:calc(var(--shell-menu-button-mobile-height) * 0.5);}
	        }
        <?php endif; ?>
        #sidebar{position:fixed;top:0;left:-320px;width:320px;height:100%;background-color:var(--sidebar-bg);transition:left 0.3s;padding:20px 20px 20px 20px;box-sizing:border-box;z-index:999;border-top-right-radius:20px;border-bottom-right-radius:20px;display:flex;flex-direction:column;}
        #sidebar.open{left:0;}
        #app-list-container::-webkit-scrollbar{display:none;}
        #bottom-bar{position:absolute;bottom:20px;left:20px;right:20px;display:flex;align-items:center;gap:8px;flex-wrap:nowrap;}
        #app-list-container{margin-top:0;padding-top:0;max-height:none;flex:1;min-height:0;overflow-y:auto;scrollbar-width:none;-ms-overflow-style:none;-webkit-mask-image:linear-gradient(to bottom,transparent 0px,black 80px,black calc(100% - 60px),transparent 100%);mask-image:linear-gradient(to bottom,transparent 0px,black 80px,black calc(100% - 60px),transparent 100%);}
        #bottom-bar{position:relative;bottom:auto;left:auto;right:auto;flex-shrink:0;padding-top:8px;}
        .search-container{position:relative;flex:1 1 auto;min-width:0;}
        .search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:16px;height:16px;opacity:.65;pointer-events:none;}
        #search-bar{width:100%;padding:10px;box-sizing:border-box;border-radius:8px;border:1px solid var(--border-color);padding-right:35px;padding-left:34px;background:var(--modal-bg);color:var(--text-color);caret-color:var(--text-color);-webkit-text-fill-color:var(--text-color);}
        #search-bar::placeholder{color:color-mix(in srgb,var(--text-color) 42%,transparent);opacity:1;}
        #clear-search{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#ff4444;font-size:18px;cursor:pointer;display:none;width:20px;height:20px;align-items:center;justify-content:center;border-radius:50%;}
        #clear-search:hover{background-color:rgba(255,68,68,0.1);}
        .group-letter{font-weight:bold;margin-bottom:5px;color:var(--text-color);}
        .group-block{background-color:var(--group-block-bg);border-radius:8px;padding:10px;margin-bottom:15px;}
        #app-list,#app-list ul{list-style:none;padding:0;margin:0;}
        #app-list li{width:100%;box-sizing:border-box;margin-bottom:10px;padding:10px;background-color:var(--app-item-bg);border-radius:8px;position:relative;}
        #app-list a{color:var(--link-color);text-decoration:none;display:flex;align-items:center;justify-content:space-between;gap:12px;min-width:0;}
        #app-list a:hover{color:var(--link-hover-color);}
        .sidebar-app-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .sidebar-app-icon-wrap{width:18px;height:18px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
        .sidebar-app-icon{width:18px;height:18px;object-fit:contain;display:block;}
        .sidebar-app-icon-svg{display:block;width:18px;height:18px;background:linear-gradient(135deg,var(--app-icon-grad-start) 0%,var(--app-icon-grad-end) 100%);-webkit-mask-image:var(--icon-src);mask-image:var(--icon-src);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain;}
        .tooltip{display:none;position:fixed;background-color:var(--tooltip-bg);color:#fff;padding:5px 10px;border-radius:4px;white-space:nowrap;z-index:20000;pointer-events:none;box-shadow:0 2px 10px rgba(0,0,0,0.3);font-size:12px;transform:translateX(-50%);}
        .tooltip::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:var(--tooltip-bg);}
        .sidebar-button{background:none;border:none;cursor:pointer;color:var(--text-color);width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;position:relative;flex-shrink:0;}
        .sidebar-button svg{width:20px;height:20px;}
        #settings-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:10000;}
        #qr-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:10000;}
        #qr-content{background:var(--modal-bg);padding:30px;border-radius:12px;color:var(--text-color);width:90%;max-width:400px;max-height:90vh;overflow-y:auto;position:relative;margin:2% auto;z-index:10001;}
        #close-qr{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-qr:hover{color:#cc0000;}
        #email-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:10000;}
        #email-form{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:8px;color:var(--text-color);position:relative;min-width:350px;max-width:500px;max-height:90vh;overflow-y:auto;margin:2% auto;z-index:10001;}
        #close-email{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-email:hover{color:#cc0000;}
        #users-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:10000;}
        #users-content{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:8px;color:var(--text-color);position:relative;width:95%;height:95%;max-width:none;max-height:none;overflow:auto;margin:2% auto;z-index:10001;}
        #close-users{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-users:hover{color:#cc0000;}
        .users-table{width:100%;border-collapse:collapse;margin-top:15px;}
        .users-table th,.users-table td{padding:12px;text-align:left;border-bottom:1px solid var(--border-color);}
        .users-table th{background-color:var(--table-header-bg);font-weight:bold;}
        .users-table tr:hover{background-color:var(--table-row-hover);}
        .role-select{padding:5px;border-radius:4px;border:1px solid var(--border-color);background:var(--modal-bg);color:var(--text-color);}
        #users-search{width:100%;padding:10px;margin-bottom:15px;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--text-color);box-sizing:border-box;padding-left:34px;}

        /* Telegram Bot Modal */
        #tg-bot-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);justify-content:center;align-items:center;z-index:11000;}
        #tg-bot-content{background:var(--modal-bg);padding:40px 24px 24px 24px;border-radius:12px;color:var(--text-color);position:relative;width:90%;max-width:480px;max-height:90vh;overflow-y:auto;z-index:11001;}
        #close-tg-bot{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:11002;}
        .tg-bot-field{margin-bottom:14px;}
        .tg-bot-field label{display:block;font-size:12px;font-weight:bold;color:#888;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.5px;}
        .tg-bot-field input{width:100%;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;background:var(--modal-bg);color:var(--text-color);font-size:14px;box-sizing:border-box;font-family:monospace;}
        .tg-bot-field input:focus{outline:none;border-color:var(--primary-color);}
        .tg-bot-hint{font-size:12px;color:#888;margin-top:4px;line-height:1.4;}
        .tg-bot-actions{display:flex;gap:10px;margin-top:20px;}
        .tg-save-btn{flex:1;padding:10px;background:var(--primary-color);color:#fff;border:none;border-radius:24px;cursor:pointer;font-size:14px;font-weight:bold;}
        .tg-test-btn{padding:10px 18px;background:var(--group-block-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:24px;cursor:pointer;font-size:14px;}
        .tg-test-btn:hover{background:var(--app-item-bg);}
        .tg-status{margin-top:10px;font-size:13px;text-align:center;min-height:20px;}


        /* Delete User Modal */
        #delete-user-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);justify-content:center;align-items:center;z-index:95000;}
        #delete-user-content{background:var(--modal-bg);padding:40px 24px 24px 24px;border-radius:12px;color:var(--text-color);position:relative;width:92%;max-width:700px;max-height:90vh;overflow-y:auto;z-index:95001;}
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
        #reset-user-password-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);justify-content:center;align-items:center;z-index:95000;}
        #reset-user-password-content{background:var(--modal-bg);padding:40px 24px 24px 24px;border-radius:12px;color:var(--text-color);position:relative;width:92%;max-width:520px;max-height:90vh;overflow-y:auto;z-index:95001;}
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
        #stats-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:10000;}
        #edit-stats-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:10001;}
        #edit-stats-content{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:8px;color:var(--text-color);position:relative;width:90%;height:85vh;max-width:1200px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;margin:2% auto;z-index:10002;}
        #close-edit-stats{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10003;}
        #settings-form{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:8px;color:var(--text-color);position:relative;min-width:300px;max-width:500px;max-height:90vh;overflow-y:auto;margin:2% auto;z-index:10001;}
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
        #stats-content{background:var(--modal-bg);padding:60px 20px 20px 20px;border-radius:8px;color:var(--text-color);position:relative;width:90%;height:85vh;max-width:1000px;max-height:90vh;display:flex;flex-direction:column;overflow-y:auto;margin:2% auto;z-index:10001;}
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
        #app-icon-rail{--rail-w:48px;--rail-icon:28px;position:fixed;top:0;left:0;width:var(--rail-w);height:100%;z-index:1000;background:color-mix(in srgb,var(--bg-color) 18%,transparent);backdrop-filter:blur(28px) saturate(220%) brightness(1.06);-webkit-backdrop-filter:blur(28px) saturate(220%) brightness(1.06);border-right:1px solid color-mix(in srgb,var(--border-color) 28%,transparent);box-sizing:border-box;display:flex;flex-direction:column;align-items:center;gap:6px;padding-top:calc(var(--shell-menu-button-top) + var(--shell-menu-button-height) + 10px);padding-bottom:10px;padding-left:4px;padding-right:4px;overflow-y:auto;scrollbar-width:none;-ms-overflow-style:none;}
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
            #content.has-app-rail.rail-top-on-mobile .app-rail-scroll{display:flex;flex-direction:row;align-items:center;gap:6px;padding:0 8px 0 calc(var(--shell-menu-button-mobile-left) + var(--shell-menu-button-mobile-width) + 8px);overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none;height:100%;flex:1;}
            #content.has-app-rail.rail-top-on-mobile .app-rail-scroll::-webkit-scrollbar{display:none;}
            #content.has-app-rail.rail-top-on-mobile .app-rail-divider{width:1px;height:60%;flex-shrink:0;margin:0 2px;}
            #content.has-app-rail.rail-top-on-mobile .app-rail-service-name{display:block;writing-mode:horizontal-tb;transform:none;font-size:13px;font-weight:600;letter-spacing:.04em;color:var(--text-color);opacity:.7;white-space:nowrap;padding:0 8px;max-width:110px;overflow:hidden;text-overflow:ellipsis;flex-shrink:0;user-select:none;}
        }
        /* border-box: height includes padding — otherwise padding is added outside height and the grid is clipped by #content (last tiles unreachable). */
        #app-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;box-sizing:border-box;padding:20px;padding-top:calc(70px + 12px);padding-bottom:max(48px,calc(28px + env(safe-area-inset-bottom,0px)));overflow-y:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;height:100%;max-height:100%;scrollbar-color:var(--primary-color) var(--group-block-bg);align-content:flex-start;scroll-padding-top:12px;scroll-padding-bottom:max(32px,env(safe-area-inset-bottom,0px));}
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
        .app-tile-link{text-decoration:none;color:inherit;display:block;}
        .app-tile-link:hover{opacity:0.8;}
        .app-tile{background-color:var(--app-item-bg);border-radius:8px;padding:10px;overflow:hidden;transition:opacity 0.3s,transform 0.18s ease,box-shadow 0.18s ease,border-color 0.18s ease;border:1px solid transparent;}
        .app-tile-link.app-tile-key-selected .app-tile{border-color:var(--primary-color);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary-color) 28%,transparent),0 12px 28px color-mix(in srgb,var(--primary-color) 18%,transparent);transform:translateY(-2px);}
        .app-name{font-weight:bold;margin-bottom:10px;text-align:center;color:var(--link-color);}
        .app-tile iframe{width:100%;height:150px;border:none;border-radius:4px;pointer-events:none;}
        button,input[type="submit"]{background-color:var(--primary-color);color:#fff;border:none;padding:10px;cursor:pointer;border-radius:4px;}
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

        #shared-files-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:10000;}
        #shared-files-content{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:8px;color:var(--text-color);position:relative;width:90%;height:80vh;max-width:900px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;margin:2% auto;z-index:10001;box-sizing:border-box;}
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
        #refresh-shared-files,#delete-all-files-btn,#delete-filtered-files,#delete-selected-shared-files{height:40px;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;padding:0 12px;}
        #refresh-shared-files{width:40px;padding:0;}
        .delete-filtered-btn{background:#ff9800;color:white;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;display:none;align-items:center;gap:5px;}
        .delete-filtered-btn:hover{background:#f57c00;}
        #sessions-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:10000;}
        #sessions-content{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:8px;color:var(--text-color);position:relative;width:90%;height:80vh;max-width:800px;max-height:90vh;overflow:auto;overflow-x:auto;-webkit-overflow-scrolling:touch;margin:2% auto;z-index:10001;}
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
        #app-access-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:10000;}
        #app-access-content{background:var(--modal-bg);padding:40px 20px 20px 20px;border-radius:8px;color:var(--text-color);position:relative;width:80%;height:80vh;max-width:700px;max-height:90vh;overflow:auto;margin:2% auto;z-index:10001;}
        #close-app-access{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-app-access:hover{color:#cc0000;}
        .settings-buttons-container{display:flex;gap:10px;justify-content:center;margin:15px 0;flex-wrap:wrap;}
        .settings-buttons-box{background:var(--group-block-bg);border-radius:12px;padding:15px;margin:20px 0;display:flex;gap:15px;justify-content:center;flex-wrap:wrap;overflow:visible;}
        .settings-button-wrapper{position:relative;overflow:visible;}
        .settings-button-wrapper .tooltip{position:fixed;bottom:auto;left:auto;transform:none;margin-bottom:0;display:none;pointer-events:none;z-index:99999;}
        .settings-button-wrapper:hover .tooltip{display:none;}/* JS handles positioning */
        .settings-buttons-end-group{display:flex;align-items:stretch;gap:15px;margin-left:auto;flex-wrap:wrap;}
        .settings-buttons-divider{width:1px;align-self:stretch;min-height:34px;background:var(--border-color);opacity:.75;flex:0 0 1px;}
        .shortcuts-help-btn{background:color-mix(in srgb,var(--primary-color) 14%,transparent) !important;border:1px solid color-mix(in srgb,var(--primary-color) 40%,transparent) !important;color:var(--primary-color) !important;}
        .shortcuts-help-btn:hover{background:color-mix(in srgb,var(--primary-color) 24%,transparent) !important;border-color:var(--primary-color) !important;}
        #shortcuts-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;z-index:10000;}
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
        .policy-links{display:flex;justify-content:center;align-items:center;gap:15px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border-color);text-align:center;width:100%;}
        .policy-link{color:var(--primary-color);text-decoration:none;font-size:14px;transition:opacity 0.3s;}
        .policy-link:hover{opacity:0.8;text-decoration:underline;}
        #policy-modal-overlay{display:none;position:fixed;inset:0;z-index:10002;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);justify-content:center;align-items:center;}
        #policy-modal-overlay.active{display:flex;}
        #policy-modal{width:80vw;height:80vh;max-width:90vw;max-height:90vh;border-radius:var(--casa-radius);overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--glass-shadow);border:1px solid var(--border-color);padding:6px;}
        #policy-modal iframe{flex:1;width:100%;border:none;border-radius:calc(var(--casa-radius) - 6px);}
        #policy-modal-close{position:absolute;top:10px;right:14px;width:36px;height:36px;border-radius:50%;border:none;background:var(--modal-bg);color:var(--text-color);font-size:20px;cursor:pointer;z-index:10003;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.2);transition:opacity 0.2s;}
        #policy-modal-close:hover{opacity:0.7;}
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
            #app-grid { grid-template-columns: repeat(4, 1fr); }
        }
        @media (min-width: 1025px) and (max-width: 1399px) {
            #app-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (min-width: 769px) and (max-width: 1024px) {
            #app-grid { grid-template-columns: repeat(3, 1fr); }
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
            #app-grid{padding-top:calc(70px + 12px);grid-template-columns:1fr;gap:15px;height:100%;max-height:100%;padding-bottom:max(48px,calc(28px + env(safe-area-inset-bottom,0px)));}
            #bottom-bar{gap:5px;}
            .search-container{min-width:0;}
            .notification{bottom:20px;right:20px;left:20px;max-width:none;width:auto;}
        }
        #shared-files-list-container,#sessions-list-container,#users-content .users-table{-webkit-overflow-scrolling:touch;overflow-x:auto;}
        select{width:100%;padding:8px;border:1px solid var(--border-color);border-radius:4px;background-color:var(--modal-bg);color:var(--text-color);font-size:14px;cursor:pointer;appearance:none;-webkit-appearance:none;-moz-appearance:none;transition:border-color 0.3s,box-shadow 0.3s;}
        select:hover{border-color:var(--primary-color);}
        select:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 5px var(--primary-color);}
        select option{background-color:var(--modal-bg);color:var(--text-color);}
        input[type="color"]{width:100%;height:34px;padding:2px;border:1px solid var(--border-color);border-radius:4px;background-color:var(--modal-bg);cursor:pointer;transition:border-color 0.3s,box-shadow 0.3s;}
        input[type="color"]:hover{border-color:var(--primary-color);}
        input[type="color"]:focus{outline:none;border-color:var(--primary-color);box-shadow:0 0 5px var(--primary-color);}
        #close-settings,#close-stats,#close-edit-stats{position:absolute;top:10px;right:10px;background:none;border:none;color:#ff0000;font-size:20px;cursor:pointer;padding:5px;z-index:10002;}
        #close-settings:hover,#close-stats:hover,#close-edit-stats:hover{color:#cc0000;}
        .app-icon-wrap{width:100%;height:150px;border-radius:4px;background-color:var(--icon-bg-color);padding:10px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;}
        .app-icon{width:100%;height:100%;object-fit:contain;}
        .app-icon-svg{display:block;width:100%;height:100%;background:radial-gradient(circle at var(--g-hx,28%) var(--g-hy,22%),color-mix(in srgb,var(--primary-color) var(--g-light,32%),white) 0%,transparent var(--g-fade,56%)),linear-gradient(var(--g-angle,135deg),color-mix(in srgb,var(--primary-color) var(--g-start,55%),white) 0%,var(--primary-color) var(--g-mid,48%),color-mix(in srgb,var(--primary-color) var(--g-end,58%),black) 100%);-webkit-mask-image:var(--icon-src);mask-image:var(--icon-src);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain;}
        #stats-canvas-container{flex:1;position:relative;min-height:400px;width:100%;margin-top:10px;overflow:hidden;}
        #stats-canvas{width:100%!important;height:100%!important;display:block;min-height:400px;}
        #constellation-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.85;
        }

        body {
            position: relative;
        }
        /* Weather Widget */
        .weather-widget {
            background: var(--modal-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px 20px;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-height: 72px;
        }
        .weather-widget:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            transform: translateY(-1px);
        }
        .weather-icon-wrap {
            flex-shrink: 0;
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--group-block-bg);
            transition: background 0.3s;
        }
        .weather-icon-wrap svg {
            width: 48px;
            height: 48px;
        }
        .weather-info {
            flex: 1;
            min-width: 0;
        }
        .weather-city {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 2px 0;
            color: var(--text-color);
        }
        .weather-temp-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .weather-temp {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-color);
            line-height: 1;
        }
        .weather-desc {
            font-size: 13px;
            color: var(--text-color);
            opacity: 0.75;
        }
        .weather-wind {
            font-size: 13px;
            color: var(--text-color);
            opacity: 0.75;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .weather-wind svg {
            width: 16px;
            height: 16px;
        }
        .weather-loading {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-color);
            opacity: 0.6;
            font-size: 14px;
        }
        .weather-loading-spinner {
            width: 20px;
            height: 20px;
            border: 3px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: weatherSpin 0.8s linear infinite;
        }
        @keyframes weatherSpin {
            to { transform: rotate(360deg); }
        }
        .weather-error {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-color);
            font-size: 14px;
            opacity: 0.75;
        }
        .weather-error svg {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
            stroke: #ff9800;
        }
        .weather-deny-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            margin-left: auto;
            flex-shrink: 0;
            transition: opacity 0.2s;
        }
        .weather-deny-btn:hover {
            opacity: 0.85;
        }
        /* Weather icon colors - now using inline colors in SVG */
        .weather-icon-wrap {
            color: inherit;
        }
        @media (max-width: 480px) {
            .weather-widget {
                padding: 12px 14px;
                gap: 12px;
                min-height: 60px;
            }
            .weather-icon-wrap {
                width: 48px;
                height: 48px;
            }
            .weather-icon-wrap svg {
                width: 36px;
                height: 36px;
            }
            .weather-temp {
                font-size: 22px;
            }
            .weather-city {
                font-size: 14px;
            }
        }

        /* ── Weather Forecast Modal ── */
        .weather-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 200000;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(6px);
            animation: fadeIn 0.25s ease;
        }
        .weather-modal-overlay.active {
            display: flex;
        }
         .weather-modal {
             background: var(--bg-color);
             border-radius: 24px;
             width: 92%;
             max-width: 680px;
             max-height: 90vh;
             overflow-y: auto;
             box-shadow: 0 24px 80px rgba(0,0,0,0.5);
             animation: slideUp 0.35s cubic-bezier(0.4, 0, 0.2, 1);
             position: relative;
             padding: 28px 24px 24px;
         }
         .weather-settings-modal {
             background: color-mix(in srgb, var(--modal-bg) 85%, transparent);
             backdrop-filter: blur(12px);
             -webkit-backdrop-filter: blur(12px);
         }
         .weather-settings-modal .location-selector,
         .weather-settings-modal .location-dropdown {
             background: color-mix(in srgb, var(--modal-bg) 90%, transparent);
             backdrop-filter: blur(10px);
             -webkit-backdrop-filter: blur(10px);
             border: 1px solid var(--border-color);
         }
         .custom-select-option.active {
             background: color-mix(in srgb, var(--primary-color) 15%, transparent);
             backdrop-filter: blur(8px);
             -webkit-backdrop-filter: blur(8px);
         }
        .weather-modal-close {
            position: absolute;
            top: 16px;
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
            top: 16px;
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
        .weather-wind-chart-btn {
            right: 104px;
        }
        .weather-settings-btn {
            right: 60px;
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
            gap: 14px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }
        .weather-modal-header-icon {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--group-block-bg);
        }
        .weather-modal-header-icon svg {
            width: 40px;
            height: 40px;
        }
        .weather-modal-header-info {
            flex: 1;
        }
        .weather-modal-city {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
        }
        .weather-modal-current {
            font-size: 14px;
            color: var(--text-color);
            opacity: 0.7;
            margin-top: 2px;
        }
        .weather-modal-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
            margin: 0 0 14px 0;
        }
        /* ── Weekly Forecast Grid ── */
        .weather-weekly {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 24px;
        }
        .weather-day-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 14px;
            background: var(--group-block-bg);
            transition: background 0.2s;
        }
        .weather-day-row:hover {
            background: var(--border-color);
        }
        .weather-day-name {
            width: 40px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
            flex-shrink: 0;
        }
        .weather-day-date {
            width: 44px;
            font-size: 12px;
            color: var(--text-color);
            opacity: 0.6;
            flex-shrink: 0;
        }
        .weather-day-icon-wrap {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .weather-day-icon-wrap svg {
            width: 30px;
            height: 30px;
        }
        .weather-day-desc {
            flex: 1;
            font-size: 13px;
            color: var(--text-color);
            opacity: 0.7;
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
        }
        .weather-day-temp-high {
            color: var(--text-color);
        }
        .weather-day-temp-low {
            color: var(--text-color);
            opacity: 0.5;
        }
        .weather-day-precip {
            display: flex;
            align-items: center;
            gap: 3px;
            font-size: 12px;
            color: #3b82f6;
            flex-shrink: 0;
            min-width: 32px;
            justify-content: flex-end;
        }
        .weather-day-precip svg {
            width: 14px;
            height: 14px;
        }
        /* ── Temperature Chart ── */
        .weather-chart-wrapper {
            background: var(--group-block-bg);
            border-radius: 16px;
            padding: 16px 16px 12px;
            margin-top: 16px;
            position: relative;
        }
        .weather-chart-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 12px;
        }
        .weather-chart-container {
            width: 100%;
            position: relative;
        }
        .weather-chart-container canvas {
            width: 100%;
            height: 200px;
            display: block;
            border-radius: 8px;
        }
        .weather-chart-legend {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-top: 10px;
            font-size: 12px;
            color: var(--text-color);
            opacity: 0.7;
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
        /* ── Scrollbar for modal ── */
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
        @media (max-width: 500px) {
            .weather-modal {
                padding: 20px 16px 16px;
                border-radius: 20px;
            }
            .weather-day-name {
                width: 32px;
                font-size: 12px;
            }
            .weather-day-date {
                width: 36px;
                font-size: 11px;
            }
            .weather-day-icon-wrap svg {
                width: 24px;
                height: 24px;
            }
            .weather-day-desc {
                font-size: 12px;
            }
            .weather-day-temps {
                font-size: 12px;
                gap: 4px;
            }
            .weather-chart-container canvas {
                height: 160px;
            }
            .weather-modal-header-icon {
                width: 42px;
                height: 42px;
            }
            .weather-modal-header-icon svg {
                width: 32px;
                height: 32px;
            }
            .weather-modal-city {
                font-size: 17px;
            }
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
</head>
<body<?php if($path==='/main'&&$selectedApp&&in_array($selectedApp,array_map(function($appData){return $appData['folder'];},$apps))): ?> class="shell-app-open"<?php endif; ?>>
    <canvas id="constellation-bg"></canvas>
<?php if($showTopBarTitle): ?>
<div id="top-bar">
    <?php if($isHomeDashboard): ?>
    <button id="menu-button" type="button" aria-label="<?php echo my_htmlspecialchars($translations['nav_menu_title'] ?? 'Menu'); ?>" title="<?php echo my_htmlspecialchars($translations['nav_menu_title'] ?? 'Menu'); ?>"><span class="menu-button-glyph" aria-hidden="true">&#9776;</span></button>
    <a id="home-root-button" href="/" aria-label="<?php echo my_htmlspecialchars($translations['home_tooltip']); ?>" title="<?php echo my_htmlspecialchars($translations['home_tooltip']); ?>"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
    <?php endif; ?>
    <div id="top-bar-title">Shell Application</div>
    <?php if($path==='/main'&&!($selectedApp&&in_array($selectedApp,array_map(function($appData){return $appData['folder'];},$apps)))):?>
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
.otp-wrap{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;z-index:2000;overflow:hidden;}
.otp-wrap .gradient-bg{position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(135deg, var(--primary-color) 0%, <?php echo $config['theme']==='dark'?'#000000':'#ffffff';?> 50%, var(--primary-color) 100%);opacity:0.8;z-index:0;pointer-events:none;}
.otp-card{background:var(--modal-bg);padding:30px;border-radius:12px;width:90%;max-width:360px;box-shadow:0 10px 30px rgba(0,0,0,.3);position:relative;z-index:1;}
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
                <?php if(isset($_GET['new_google_user'])): ?>
                <div class="auth-error" style="margin-bottom:10px;">
                    <?php echo my_htmlspecialchars($translations['google_user_not_found']); ?>
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
                    <input type="hidden" name="reg_method" value="Google">
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
                <p style="text-align:center;margin:20px 0;"><?php echo my_htmlspecialchars($verificationEmail); ?></p>
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
                <a href="https://accounts.google.com/o/oauth2/auth?client_id=<?php echo urlencode($authConfig['google_client_id']); ?>&redirect_uri=<?php echo urlencode($googleRedirectUri); ?>&response_type=code&scope=email%20profile" style="text-decoration:none;display:block;text-align:center;margin:10px 0;">
                    <img src="google.png" alt="Login with Google" style="width:40px;height:40px;cursor:pointer;border-radius:12px;">
                </a>
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
                <div style="text-align:center;margin:15px 0;">
                    <a href="https://accounts.google.com/o/oauth2/auth?client_id=<?php echo urlencode($authConfig['google_client_id']); ?>&redirect_uri=<?php echo urlencode($googleRedirectUri); ?>&response_type=code&scope=email%20profile" style="text-decoration:none;">
                        <img src="google.png" alt="Register with Google" style="width:40px;height:40px;cursor:pointer;border-radius:12px;">
                    </a>
                </div>
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
        <button class="back-button" onclick="window.location.href='/'">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
            </svg>
            <?php echo my_htmlspecialchars($translations['back_to_home'] ?? 'Back to Home'); ?>
        </button>
    </div>
</div>
<?php endif; ?>
<?php if(isset($_SESSION['user'])):?>
    <div id="profile-modal">
        <div id="profile-content">
            <button id="close-profile" type="button">&times;</button>
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
                <h3><?php echo my_htmlspecialchars($translations['change_photo']); ?></h3>
                <button type="button" class="change-photo-button" id="show-photo-form">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-repeat-icon lucide-repeat"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>
                    <?php echo my_htmlspecialchars($translations['change_photo_button'] ?? 'Change Photo'); ?>
                </button>
                <form method="post" enctype="multipart/form-data" class="photo-form" id="photo-form" action="/main">
                    <input type="file" name="photo" accept="image/*" class="auth-input">
                    <button type="submit" name="change_photo" class="profile-action-button"><?php echo my_htmlspecialchars($translations['upload_photo']); ?></button>
                </form>
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
                <form method="post" action="/main">
                    <button type="submit" name="logout" class="logout-button">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                        <?php echo my_htmlspecialchars($translations['logout']); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif;?>
<?php if($isRootUser):?>
    <div id="email-modal">
        <form id="email-form" method="post" action="/main">
            <button id="close-email" type="button">&times;</button>
            <h2><?php echo my_htmlspecialchars($translations['email_settings']); ?></h2>
            <?php if(isset($emailSettingsSuccess)):?><div class="auth-success"><?php echo my_htmlspecialchars($emailSettingsSuccess); ?></div><?php endif;?>
            <?php if(isset($emailSettingsError)):?><div class="auth-error"><?php echo my_htmlspecialchars($emailSettingsError); ?></div><?php endif;?>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['google_client_id']); ?>:</label><input type="text" name="google_client_id" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['google_client_id']); ?>" required></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['google_client_secret']); ?>:</label><input type="text" name="google_client_secret" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['google_client_secret']); ?>" required></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['smtp_host']); ?>:</label><input type="text" name="smtp_host" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['smtp_host']); ?>" required></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['smtp_port']); ?>:</label><input type="number" name="smtp_port" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['smtp_port']); ?>" required></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['smtp_username']); ?>:</label><input type="text" name="smtp_username" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['smtp_username']); ?>" required></div>
            <div class="form-row"><label><?php echo my_htmlspecialchars($translations['smtp_password']); ?>:</label><input type="password" name="smtp_password" class="auth-input" value="<?php echo my_htmlspecialchars($authConfig['smtp_password']); ?>" required></div>
            <input type="submit" name="save_email_settings" value="<?php echo my_htmlspecialchars($translations['save']); ?>">
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
            $authorizedChats = loadAuthorizedChats();
            if(!empty($authorizedChats)):
            ?>
            <div class="tg-bot-field">
                <label><?php echo my_htmlspecialchars($translations['tg_recipients'] ?? 'Recipients'); ?></label>
                <div style="background:var(--group-block-bg);border-radius:6px;padding:8px 12px;font-size:13px;font-family:monospace;line-height:1.8;">
                    <?php foreach($authorizedChats as $cid): ?>
                        <div><?php echo my_htmlspecialchars($cid); ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="tg-bot-hint"><?php echo my_htmlspecialchars($translations['tg_recipients_hint'] ?? ''); ?></div>
            </div>
            <?php endif; ?>

            <div class="tg-bot-actions">
                <button type="button" class="tg-save-btn" id="save-tg-bot"><?php echo my_htmlspecialchars($translations['save'] ?? 'Save'); ?></button>
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
        </div>
    </div>
<?php endif;?>
<?php if(isset($_SESSION['user'])):?>
    <div id="sessions-modal">
        <div id="sessions-content">
            <button id="close-sessions" type="button">&times;</button>
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
            <?php if(!$isCurrentDeviceTrusted):?>
            <div style="margin-bottom:15px;"><button type="button" id="add-trusted-device-btn" class="sidebar-button" style="background:#4CAF50;color:white;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;font-size:14px;width:100%;"><?php echo my_htmlspecialchars($translations['add_trusted_device']); ?></button></div>
            <?php endif;?>
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
        <button id="close-qr" type="button">&times;</button>
        <div class="qr-container">
            <h2><?php echo my_htmlspecialchars($translations['qr_code_title']); ?></h2>
            <div class="qr-wrapper"><div id="qr-code"></div></div>
            <div class="domain-info"><h4><?php echo my_htmlspecialchars($translations['current_domain']); ?></h4><div class="domain-url" id="current-domain-url"><?php echo my_htmlspecialchars($currentDomain); ?></div><button class="copy-link-btn" id="copy-domain-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg><?php echo my_htmlspecialchars($translations['copy_link']); ?></button></div>
        </div>
    </div>
</div>
<?php if($path!=='/login'&&$path!=='/register'):?>
    <button id="menu-button" type="button" aria-label="<?php echo my_htmlspecialchars($translations['nav_menu_title'] ?? 'Menu'); ?>" title="<?php echo my_htmlspecialchars($translations['nav_menu_title'] ?? 'Menu'); ?>"><span class="menu-button-glyph" aria-hidden="true">&#9776;</span></button>
    <div id="sidebar">
        <div id="app-list-container">
            <ul id="app-list">
                <?php foreach($groupedApps as $letter=>$group):?><div class="group-letter"><?php echo my_htmlspecialchars($letter); ?></div><div class="group-block"><ul><?php foreach($group as $name=>$appData):?><li data-tooltip="<?php echo my_htmlspecialchars($translations['open_app_tooltip'].' '.$name); ?>"><a href="<?php echo my_htmlspecialchars($appData['href']); ?>" target="<?php echo my_htmlspecialchars($appData['target']); ?>" rel="noopener" class="app-tile-link"><span class="sidebar-app-name"><?php echo my_htmlspecialchars($name); ?></span><span class="sidebar-app-icon-wrap"><?php if(!empty($appData['is_svg'])): ?><span class="sidebar-app-icon-svg" style="--icon-src:url('<?php echo my_htmlspecialchars($appData['icon']); ?>');" aria-hidden="true"></span><?php else: ?><img src="<?php echo my_htmlspecialchars($appData['icon']); ?>" alt="" class="sidebar-app-icon"><?php endif; ?></span></a><span class="tooltip"></span></li><?php endforeach;?></ul></div><?php endforeach;?>
            </ul>
        </div>
        <div id="bottom-bar">
            <div class="search-container">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
                <input type="text" id="search-bar" placeholder="<?php echo my_htmlspecialchars($translations['search_placeholder']); ?>">
                <button id="clear-search" style="display:none;">&times;</button>
            </div>
            <?php if(!$isHomeDashboard): ?><a href="/main" class="sidebar-button" data-tooltip="<?php echo my_htmlspecialchars($translations['home_tooltip']); ?>" title="<?php echo my_htmlspecialchars($translations['home']); ?>"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a><?php endif; ?>
            <button id="settings-button" class="sidebar-button" data-tooltip="<?php echo my_htmlspecialchars($translations['settings_title']); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14,12.94a1.43,1.43,0,0,0,0-1.88l2.11-1.65a.48.48,0,0,0,.12-.61l-2-3.46a.49.49,0,0,0-.6-.21l-2.49,1a5.14,5.14,0,0,0-1.62-.94l-.38-2.65A.48.48,0,0,0,13.81,2H10.19a.48.48,0,0,0-.48.41L9.33,5.06a5.14,5.14,0,0,0-1.62.94l-2.49-1a.49.49,0,0,0-.6.21l-2,3.46a.48.48,0,0,0,.12.61L4.86,11.06a1.43,1.43,0,0,0,0,1.88L2.75,14.59a.48.48,0,0,0-.12.61l2,3.46a.49.49,0,0,0,.6.21l2.49-1a5.14,5.14,0,0,0,1.62.94l.38,2.65a.48.48,0,0,0,.48.41h3.62a.48.48,0,0,0,.48-.41l.38-2.65a5.14,5.14,0,0,0,1.62-.94l2.49,1a.49.49,0,0,0,.6-.21l2-3.46a.48.48,0,0,0-.12-.61ZM12,15.5A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/></svg></button>
            <?php if(isset($_SESSION['user'])):?><div class="user-profile" id="user-profile"><img src="<?php echo my_htmlspecialchars(app_avatar_src($_SESSION['user']['photo']??'default_avatar.png')); ?>" alt="User Avatar" class="user-avatar"><div class="user-name"><?php echo my_htmlspecialchars($_SESSION['user']['name']); ?></div></div><?php endif;?>
        </div>
    </div>
<?php endif;?>
<div id="settings-modal">
    <form id="settings-form" method="post" action="/main">
        <button id="close-settings" type="button">&times;</button>
        <h2><?php echo my_htmlspecialchars($translations['settings_title']); ?></h2>
        <div class="form-row">
            <label><?php echo my_htmlspecialchars($translations['language']); ?>:</label>
            <select name="language">
                <option value="en" <?php echo $config['language']==='en'?'selected':'';?>>English <span class="flag flag-gb"></span></option>
                <option value="ru" <?php echo $config['language']==='ru'?'selected':'';?>>Русский</option>
                <option value="uk" <?php echo $config['language']==='uk'?'selected':'';?>>Українська <span class="flag flag-ua"></span></option>
                <option value="be" <?php echo $config['language']==='be'?'selected':'';?>>Беларуская <span class="flag flag-by"></span></option>
                <option value="pl" <?php echo $config['language']==='pl'?'selected':'';?>>Polski <span class="flag flag-pl"></span></option>
                <option value="cs" <?php echo $config['language']==='cs'?'selected':'';?>>Čeština <span class="flag flag-cz"></span></option>
            </select>
        </div>
        <div class="form-row">
            <label><?php echo my_htmlspecialchars($translations['theme']); ?>:</label>
            <select name="theme">
                <option value="light" <?php echo $savedTheme==='light'?'selected':''; ?>><?php echo my_htmlspecialchars($translations['theme_light']); ?></option>
                <option value="dark"  <?php echo $savedTheme==='dark'?'selected':''; ?>><?php echo my_htmlspecialchars($translations['theme_dark']); ?></option>
                <option value="auto"  <?php echo $savedTheme==='auto'?'selected':''; ?>><?php echo my_htmlspecialchars($translations['theme_auto']); ?></option>
            </select>
        </div>
        <div class="form-row">
            <label><?php echo my_htmlspecialchars($translations['color']); ?>:</label>
            <input type="color" name="color" value="<?php echo my_htmlspecialchars($config['color']); ?>">
        </div>
        <div class="policy-links">
            <a href="javascript:void(0)" class="policy-link" onclick="openPolicyModal('/terms?hideBack=1&theme=<?php echo urlencode($config['theme']); ?>&lang=<?php echo urlencode($language); ?>&color=<?php echo urlencode($config['color']); ?>')"><?php echo my_htmlspecialchars($translations['view_terms']??'Terms of Service'); ?></a>
            <span class="policy-dot"></span>
            <a href="javascript:void(0)" class="policy-link" onclick="openPolicyModal('/privacy?hideBack=1&theme=<?php echo urlencode($config['theme']); ?>&lang=<?php echo urlencode($language); ?>&color=<?php echo urlencode($config['color']); ?>')"><?php echo my_htmlspecialchars($translations['view_privacy']??'Privacy Policy'); ?></a>
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
        <input type="submit" name="settings" value="<?php echo my_htmlspecialchars($translations['save']); ?>">
    </form>
</div>
<div id="shortcuts-modal">
    <div id="shortcuts-content">
        <button id="close-shortcuts" type="button">&times;</button>
        <h2 style="margin:0 0 12px 0;"><?php echo my_htmlspecialchars($translations['keyboard_shortcuts'] ?? 'Keyboard Shortcuts'); ?></h2>
        <p style="margin:0 0 12px 0;opacity:.75;"><?php echo my_htmlspecialchars($translations['keyboard_shortcuts_desc'] ?? 'Hotkeys available on the home screen with app tiles.'); ?></p>
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
                <tr><td><code>P</code></td><td><?php echo my_htmlspecialchars($translations['shortcut_profile'] ?? 'Open Profile'); ?></td></tr>
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
<?php $railTopApps=['immich','gitea','plane']; ?>
<div id="content"<?php if($showAppFrame):?> class="mobile-vv-shell<?php echo $showAppRail?' has-app-rail':''; ?><?php echo ($showAppRail&&in_array($selectedApp,$railTopApps))?' rail-top-on-mobile':''; ?>"<?php endif;?>>
    <?php if($showAppFrame):?>
        <?php if($showAppRail):?>
        <div id="app-icon-rail">
            <div class="app-rail-scroll">
            <a href="/main" class="app-rail-link app-rail-home" title="<?php echo my_htmlspecialchars($translations['home_tooltip'] ?? $translations['home'] ?? 'Home'); ?>"><svg viewBox="0 0 24 24" fill="currentColor" width="60%" height="60%"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
            <span class="app-rail-service-name"><?php echo my_htmlspecialchars(array_key_first(array_filter($apps,function($a)use($selectedApp){return $a['folder']===$selectedApp;}))?? ucfirst((string)$selectedApp)); ?></span>
            </div>
        </div>
        <?php endif;?>
        <iframe id="app-iframe" class="mobile-vv-fill" src="apps/<?php echo urlencode($selectedApp); ?>/index.php?lang=<?php echo urlencode($language); ?>&theme=<?php echo urlencode($themeForAppLink); ?>&color=<?php echo urlencode($config['color']); ?><?php echo $userDataForApps?'&user_data='.urlencode(base64_encode(json_encode($userDataForApps))):''; ?>"></iframe>
    <?php elseif($path==='/main'):?>
        <?php if(!$showTopBarTitle):?>
        <button type="button" id="support-chat-btn" class="support-chat-floating" title="<?php echo my_htmlspecialchars($translations['support_chat'] ?? 'Support Chat'); ?>" style="position:fixed;top:10px;right:16px;z-index:9999;width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,color-mix(in srgb,<?php echo $config['color']; ?> 28%,transparent) 0%,color-mix(in srgb,<?php echo $config['color']; ?> 14%,transparent) 100%);color:<?php echo $config['color']; ?>;border:1px solid color-mix(in srgb,<?php echo $config['color']; ?> 35%,transparent);box-shadow:0 2px 10px color-mix(in srgb,<?php echo $config['color']; ?> 22%,transparent);cursor:pointer;display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
            <span id="chat-unread-badge" style="display:none;position:absolute;top:-4px;right:-4px;background:#ef4444;color:white;border-radius:12px;padding:2px 6px;font-size:11px;font-weight:700;min-width:18px;text-align:center;box-shadow:0 2px 8px rgba(239,68,68,0.5);"></span>
        </button>
        <?php endif; ?>
        <div id="weatherWidget" class="weather-widget" style="display:none;">
            <div class="weather-loading">
                <div class="weather-loading-spinner"></div>
                <span><?php echo my_htmlspecialchars($translations['weather_loading'] ?? 'Loading weather...'); ?></span>
            </div>
        </div>
        <div id="app-grid">
            <?php foreach($apps as $name=>$appData):?>
                <?php $_ga=mt_rand(110,165);$_ghx=mt_rand(18,40);$_ghy=mt_rand(14,34);$_gl=mt_rand(24,44);$_gf=mt_rand(46,66);$_gs=mt_rand(48,66);$_gm=mt_rand(38,62);$_ge=mt_rand(50,74); ?>
                <a href="<?php echo my_htmlspecialchars($appData['href']); ?>" target="<?php echo my_htmlspecialchars($appData['target']); ?>" rel="noopener" class="app-tile-link"><div class="app-tile"><div class="app-name"><?php echo my_htmlspecialchars($name); ?></div><div class="app-icon-wrap"><?php if(!empty($appData['is_svg'])): ?><span class="app-icon-svg" style="--icon-src:url('<?php echo my_htmlspecialchars($appData['icon']); ?>');--g-angle:<?php echo $_ga; ?>deg;--g-hx:<?php echo $_ghx; ?>%;--g-hy:<?php echo $_ghy; ?>%;--g-light:<?php echo $_gl; ?>%;--g-fade:<?php echo $_gf; ?>%;--g-start:<?php echo $_gs; ?>%;--g-mid:<?php echo $_gm; ?>%;--g-end:<?php echo $_ge; ?>%;" aria-label="<?php echo my_htmlspecialchars($name); ?>" role="img"></span><?php else: ?><img src="<?php echo my_htmlspecialchars($appData['icon']); ?>" alt="<?php echo my_htmlspecialchars($name); ?>" class="app-icon"><?php endif; ?></div></div></a>
            <?php endforeach;?>
        </div>
    <?php endif;?>
</div>

<!-- Support Chat Modal -->
<div id="support-chat-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:100000;align-items:center;justify-content:center;backdrop-filter:blur(8px);animation:fadeIn 0.3s ease;">
    <div id="chat-modal-container" style="background:var(--bg-color);border-radius:24px;width:<?php echo (isset($_SESSION['user']) && (int)($_SESSION['user']['role'] ?? 0) >= 2) ? '95%' : '90%'; ?>;max-width:<?php echo (isset($_SESSION['user']) && (int)($_SESSION['user']['role'] ?? 0) >= 2) ? '1400px' : '900px'; ?>;height:85%;max-height:min(700px,85vh);min-height:0;display:flex;flex-direction:<?php echo (isset($_SESSION['user']) && (int)($_SESSION['user']['role'] ?? 0) >= 2) ? 'row' : 'column'; ?>;box-shadow:0 20px 60px rgba(0,0,0,0.4);overflow:hidden;position:relative;animation:slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
        <!-- Animated background -->
        <div style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;">
            <div class="chat-bg-animation"></div>
        </div>

        <!-- Admin: Chat list sidebar (hidden by default, shown via hash) -->
        <div id="admin-chat-list" style="display:none;width:320px;min-height:0;flex-shrink:0;border-right:1px solid var(--border-color);flex-direction:column;position:relative;z-index:1;background:rgba(0,0,0,0.02);">
            <div style="padding:24px;background:linear-gradient(135deg, var(--chat-color-light) 0%, var(--chat-color-medium) 100%);border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <h2 style="margin:0;font-size:20px;font-weight:600;color:var(--text-color);flex:1;min-width:0;">Support Chats</h2>
                <button type="button" id="admin-chat-list-close" title="<?php echo my_htmlspecialchars($translations['close'] ?? 'Close'); ?>" style="flex-shrink:0;width:40px;height:40px;border:none;border-radius:12px;background:rgba(255,255,255,0.12);color:var(--text-color);font-size:24px;line-height:1;cursor:pointer;align-items:center;justify-content:center;" aria-label="<?php echo my_htmlspecialchars($translations['close'] ?? 'Close'); ?>">&times;</button>
            </div>
            <div style="flex:1;min-height:0;overflow-y:auto;padding:16px;">
                <button id="new-chat-btn" style="width:100%;padding:14px;background:transparent;color:<?php echo $config['color']; ?>;border:1px solid <?php echo $config['color']; ?>;border-radius:12px;cursor:pointer;font-weight:600;margin-bottom:16px;transition:all 0.2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.background='<?php echo $config['color']; ?>11';this.style.boxShadow='0 6px 20px <?php echo $config['color']; ?>33';" onmouseout="this.style.transform='translateY(0)';this.style.background='transparent';this.style.boxShadow='none';">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:8px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php echo my_htmlspecialchars($translations['support_chat_new_title'] ?? 'New chat'); ?>
                </button>
                <div id="chat-list-container"></div>
            </div>
        </div>

        <!-- Chat messages area -->
        <div id="chat-messages-container" style="flex:1;min-height:0;overflow:hidden;display:flex;flex-direction:column;position:relative;z-index:1;">
            <!-- Header with gradient -->
            <div style="padding:24px;background:linear-gradient(135deg, var(--chat-color-light) 0%, var(--chat-color-medium) 100%);border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                    <button type="button" id="chat-admin-mobile-back" style="flex-shrink:0;width:40px;height:40px;border:none;border-radius:12px;background:rgba(255,255,255,0.12);color:var(--text-color);cursor:pointer;align-items:center;justify-content:center;padding:0;" title="<?php echo my_htmlspecialchars($translations['support_chat_back_list'] ?? 'Chats'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['support_chat_back_list'] ?? 'Back to chats'); ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button>
                    <h2 id="chat-modal-title" style="margin:0;font-size:22px;font-weight:600;color:var(--text-color);background:linear-gradient(135deg, <?php echo $config['color']; ?> 0%, <?php echo $config['color']; ?>cc 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo my_htmlspecialchars($translations['support_chat_title'] ?? 'Chat with Support'); ?></h2>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php if ($isAdmin): ?>
                    <button id="delete-chat-btn" style="display:none;background:rgba(255,59,48,0.15);border:1px solid rgba(255,59,48,0.3);font-size:14px;cursor:pointer;color:#ff3b30;padding:8px 16px;border-radius:10px;transition:all 0.2s;font-weight:500;" onmouseover="this.style.background='rgba(255,59,48,0.25)';" onmouseout="this.style.background='rgba(255,59,48,0.15)';">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Delete Chat
                    </button>
                    <?php endif; ?>
                    <button id="close-chat-modal" style="background:rgba(255,255,255,0.1);border:none;font-size:28px;cursor:pointer;color:var(--text-color);padding:0;width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:12px;transition:all 0.2s;" onmouseover="this.style.background='rgba(255,0,0,0.2)';this.style.transform='rotate(90deg)';" onmouseout="this.style.background='rgba(255,255,255,0.1)';this.style.transform='rotate(0deg)';">&times;</button>
                </div>
            </div>

            <div id="chat-messages-area" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
                <div id="chat-messages" class="chat-messages-scroll" tabindex="-1" style="flex:1;overflow-y:auto;overflow-x:hidden;padding:24px;display:flex;flex-direction:column;gap:16px;min-height:0;-webkit-overflow-scrolling:touch;outline:none;"></div>

                <div id="chat-composer-row" style="padding:20px;background:rgba(255,255,255,0.02);border-top:1px solid var(--border-color);display:flex;gap:12px;align-items:center;">
                    <input type="file" id="chat-file-input" accept="image/*,video/*,audio/*,text/*,.txt,.md,.json,.xml,.csv,.log,.html,.css,.js,.py,.java,.c,.cpp,.php,.rb,.go,.rs,.sql,.yaml,.yml,.sh,.bat,.ini,.conf,.config" style="display:none;">
                    <button type="button" id="chat-cancel-edit-btn" style="display:none;flex-shrink:0;padding:10px 14px;border-radius:12px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);cursor:pointer;font-size:13px;font-weight:600;"><?php echo my_htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
                    <button type="button" id="chat-upload-btn" title="<?php echo my_htmlspecialchars($translations['support_chat_upload'] ?? 'Upload file'); ?>" style="flex-shrink:0;background:linear-gradient(135deg, var(--chat-color-light) 0%, var(--chat-color-medium) 100%);color:var(--text-color);border:1px solid var(--chat-color-border);padding:10px;border-radius:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';" aria-label="<?php echo my_htmlspecialchars($translations['support_chat_upload'] ?? 'Upload file'); ?>"><svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="22" height="20" viewBox="0 0 1280 1188" preserveAspectRatio="xMidYMid meet" aria-hidden="true"><g transform="translate(0,1188) scale(0.1,-0.1)" fill="currentColor" stroke="none"><path d="M7835 11870 c-555 -59 -1064 -296 -1524 -710 -160 -144 -371 -399 -492 -595 -61 -98 -2515 -4298 -2621 -4485 -466 -822 -641 -1663 -477 -2295 67 -259 222 -503 409 -644 367 -277 835 -291 1345 -42 249 122 483 292 724 526 249 242 463 509 654 815 141 227 1322 2258 1340 2305 25 65 33 196 17 266 -49 211 -237 359 -455 359 -179 0 -320 -86 -421 -256 -22 -38 -301 -514 -619 -1059 -318 -544 -602 -1030 -633 -1080 -283 -463 -627 -821 -973 -1014 -93 -52 -216 -92 -299 -98 -71 -5 -77 -4 -107 21 -41 34 -68 95 -89 197 -25 122 -15 389 20 559 65 316 185 633 354 935 131 234 2581 4424 2640 4515 296 458 770 777 1250 841 134 18 345 6 467 -25 125 -33 279 -107 383 -186 503 -379 624 -1191 283 -1895 -61 -127 -3739 -6430 -3822 -6550 -464 -673 -1395 -921 -2144 -570 -114 53 -767 425 -900 513 -206 136 -439 401 -554 631 -238 475 -240 1035 -6 1502 34 66 813 1402 2808 4814 341 583 636 1094 656 1135 31 65 36 86 39 167 5 107 -6 160 -52 248 -77 148 -240 245 -411 245 -129 -1 -230 -44 -325 -140 -57 -57 -103 -131 -391 -625 -180 -308 -942 -1613 -1694 -2900 -752 -1287 -1390 -2380 -1418 -2430 -150 -265 -258 -596 -303 -925 -24 -170 -24 -490 0 -660 56 -412 198 -791 420 -1124 111 -165 187 -258 330 -401 220 -220 326 -294 815 -575 620 -356 719 -402 1035 -485 258 -68 358 -79 676 -79 308 0 403 10 650 70 356 88 754 285 1036 514 192 157 394 378 531 585 60 90 3729 6364 3816 6525 146 271 261 622 314 960 22 145 25 576 5 715 -65 432 -208 788 -440 1095 -72 95 -236 265 -327 340 -275 225 -609 374 -975 435 -112 19 -430 27 -545 15z"/></g></svg></button>
                    <button type="button" id="chat-voice-btn" title="<?php echo my_htmlspecialchars($translations['support_chat_voice'] ?? 'Voice message'); ?>" style="flex-shrink:0;background:linear-gradient(135deg, var(--chat-color-light) 0%, var(--chat-color-medium) 100%);color:var(--text-color);border:1px solid var(--chat-color-border);padding:10px;border-radius:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';" aria-label="<?php echo my_htmlspecialchars($translations['support_chat_voice'] ?? 'Voice message'); ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg></button>
                    <input type="text" id="chat-input" placeholder="<?php echo my_htmlspecialchars($translations['support_chat_placeholder'] ?? 'Type your message...'); ?>" style="flex:1;min-width:0;padding:14px 18px;border:1px solid var(--border-color);border-radius:20px;background:var(--modal-bg);color:var(--text-color);font-size:15px;transition:all 0.2s;" onfocus="this.style.borderColor='<?php echo $config['color']; ?>';this.style.boxShadow='0 0 0 3px <?php echo $config['color']; ?>22';" onblur="this.style.borderColor='var(--border-color)';this.style.boxShadow='none';">
                    <button type="button" id="chat-send-btn" title="<?php echo my_htmlspecialchars($translations['support_chat_send'] ?? 'Send'); ?>" style="flex-shrink:0;width:48px;height:48px;padding:0;border-radius:50%;border:none;background:linear-gradient(135deg, <?php echo $config['color']; ?> 0%, <?php echo $config['color']; ?>dd 100%);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.2s,box-shadow 0.2s;box-shadow:0 4px 12px <?php echo $config['color']; ?>66;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px <?php echo $config['color']; ?>88';" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px <?php echo $config['color']; ?>66';" aria-label="<?php echo my_htmlspecialchars($translations['support_chat_send'] ?? 'Send'); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.714 3.048a.498.498 0 0 0-.683.627l2.843 7.627a2 2 0 0 1 0 1.396l-2.842 7.627a.498.498 0 0 0 .682.627l18-8.5a.5.5 0 0 0 0-.904z"/><path d="M6 12h16"/></svg></button>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="chat-media-editor-modal" style="display:none;position:fixed;inset:0;z-index:100110;background:rgba(0,0,0,.94);flex-direction:column;box-sizing:border-box;padding:max(8px,env(safe-area-inset-top)) max(8px,env(safe-area-inset-right)) max(8px,env(safe-area-inset-bottom)) max(8px,env(safe-area-inset-left));" aria-modal="true" role="dialog">
    <div style="flex-shrink:0;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;padding:8px 4px;border-bottom:1px solid rgba(255,255,255,.12);">
        <button type="button" id="chat-media-tool-crop" class="chat-media-tool-btn" style="padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;font-size:14px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_crop'] ?? 'Crop'); ?></button>
        <button type="button" id="chat-media-tool-arrow" class="chat-media-tool-btn" style="padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;font-size:14px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_arrow'] ?? 'Arrow'); ?></button>
        <button type="button" id="chat-media-tool-draw" class="chat-media-tool-btn" style="padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;font-size:14px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_draw'] ?? 'Draw'); ?></button>
        <button type="button" id="chat-media-tool-clear" style="padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.06);color:#fca5a5;font-size:14px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_clear'] ?? 'Clear marks'); ?></button>
        <button type="button" id="chat-media-apply-crop" style="display:none;padding:10px 14px;border-radius:12px;border:1px solid #4ade80;background:rgba(34,197,94,.25);color:#bbf7d0;font-size:14px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_apply_crop'] ?? 'Apply crop'); ?></button>
    </div>
    <div id="chat-media-editor-scroll" style="flex:1;min-height:0;overflow:auto;display:flex;align-items:center;justify-content:center;-webkit-overflow-scrolling:touch;">
        <div id="chat-media-editor-stage" style="position:relative;touch-action:none;margin:auto;">
            <canvas id="chat-media-editor-base" style="display:block;max-width:92vw;vertical-align:top;"></canvas>
            <canvas id="chat-media-editor-layer" style="position:absolute;left:0;top:0;display:block;max-width:92vw;pointer-events:auto;"></canvas>
        </div>
    </div>
    <div style="flex-shrink:0;display:flex;gap:12px;justify-content:stretch;padding:12px 8px;border-top:1px solid rgba(255,255,255,.12);">
        <button type="button" id="chat-media-editor-cancel" style="flex:1;padding:14px;border-radius:14px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:#fff;font-size:16px;font-weight:600;cursor:pointer;"><?php echo my_htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
        <button type="button" id="chat-media-editor-send" style="flex:1;padding:14px;border-radius:14px;border:none;background:linear-gradient(135deg,<?php echo $config['color']; ?> 0%,<?php echo $config['color']; ?>cc 100%);color:#fff;font-size:16px;font-weight:700;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_send'] ?? 'Send'); ?></button>
    </div>
</div>
<div id="support-chat-lightbox" style="display:none;position:fixed;inset:0;z-index:100110;background:rgba(0,0,0,.94);align-items:center;justify-content:center;flex-direction:column;box-sizing:border-box;padding:max(12px,env(safe-area-inset-top)) max(12px,env(safe-area-inset-right)) max(12px,env(safe-area-inset-bottom)) max(12px,env(safe-area-inset-left));" aria-modal="true" role="dialog">
    <button type="button" id="support-chat-lightbox-close" style="position:absolute;top:max(10px,env(safe-area-inset-top));right:max(14px,env(safe-area-inset-right));width:44px;height:44px;border:none;border-radius:50%;background:rgba(255,255,255,.14);color:#fff;font-size:22px;line-height:1;cursor:pointer;z-index:2;display:flex;align-items:center;justify-content:center;">×</button>
    <div id="support-chat-lightbox-inner" style="max-width:100%;max-height:88vh;width:100%;display:flex;align-items:center;justify-content:center;overflow:auto;touch-action:manipulation;-webkit-overflow-scrolling:touch;"></div>
</div>
<div id="support-chat-delete-modal" style="display:none;position:fixed;inset:0;z-index:100108;align-items:center;justify-content:center;padding:max(16px,env(safe-area-inset-top)) max(16px,env(safe-area-inset-right)) max(16px,env(safe-area-inset-bottom)) max(16px,env(safe-area-inset-left));box-sizing:border-box;background:rgba(0,0,0,.58);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);" role="alertdialog" aria-modal="true" aria-labelledby="support-chat-delete-heading" tabindex="-1">
    <div id="support-chat-delete-card" style="width:100%;max-width:420px;border-radius:22px;overflow:hidden;box-shadow:0 28px 80px rgba(0,0,0,.5),0 0 0 1px color-mix(in srgb,var(--border-color) 80%,transparent);background:linear-gradient(165deg,color-mix(in srgb,var(--modal-bg) 96%,#6366f1 4%) 0%,var(--modal-bg) 55%);animation:supportDelModalIn .32s ease;">
        <div style="padding:28px 26px 20px;text-align:center;border-bottom:1px solid color-mix(in srgb,var(--border-color) 70%,transparent);">
            <div style="width:64px;height:64px;margin:0 auto 18px;border-radius:50%;background:linear-gradient(145deg,rgba(239,68,68,.25),rgba(220,38,38,.12));display:flex;align-items:center;justify-content:center;box-shadow:0 8px 28px rgba(239,68,68,.25);">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            </div>
            <h2 id="support-chat-delete-heading" style="margin:0 0 10px;font-size:20px;font-weight:700;color:var(--text-color);letter-spacing:-.02em;"><?php echo my_htmlspecialchars($translations['support_chat_delete_modal_title'] ?? 'Delete this chat?'); ?></h2>
            <div id="support-chat-delete-email" style="font-size:14px;font-weight:600;color:<?php echo my_htmlspecialchars($config['color']); ?>;word-break:break-all;line-height:1.45;margin-bottom:12px;"></div>
            <p style="margin:0;font-size:14px;line-height:1.55;color:var(--text-color);opacity:.82;"><?php echo my_htmlspecialchars($translations['support_chat_delete_modal_body'] ?? 'All messages in this conversation will be permanently removed.'); ?></p>
        </div>
        <div style="display:flex;gap:12px;padding:18px 20px 20px;justify-content:stretch;background:color-mix(in srgb,var(--modal-bg) 88%,transparent);">
            <button type="button" id="support-chat-delete-cancel" style="flex:1;padding:14px 16px;border-radius:14px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);font-size:15px;font-weight:600;cursor:pointer;"><?php echo my_htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
            <button type="button" id="support-chat-delete-confirm" style="flex:1;padding:14px 16px;border-radius:14px;border:none;background:linear-gradient(135deg,#ef4444 0%,#b91c1c 100%);color:#fff;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 6px 22px rgba(239,68,68,.4);"><?php echo my_htmlspecialchars($translations['support_chat_delete_btn'] ?? 'Delete permanently'); ?></button>
        </div>
    </div>
</div>
<div id="chat-user-search-modal" style="display:none;position:fixed;inset:0;z-index:100002;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;backdrop-filter:blur(6px);">
    <div style="background:var(--modal-bg);border:1px solid var(--border-color);border-radius:16px;width:92%;max-width:440px;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,0.35);overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--border-color);font-weight:600;font-size:17px;color:var(--text-color);"><?php echo my_htmlspecialchars($translations['support_chat_new_title'] ?? 'New chat'); ?></div>
        <div style="padding:14px 16px 0;">
            <input type="search" id="chat-user-search-input" autocomplete="off" placeholder="<?php echo my_htmlspecialchars($translations['support_chat_search_placeholder'] ?? 'Search by name or email…'); ?>" style="width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid var(--border-color);border-radius:12px;background:var(--input-bg);color:var(--text-color);font-size:15px;">
        </div>
        <div id="chat-user-search-results" style="flex:1;min-height:120px;max-height:280px;overflow-y:auto;margin:12px 16px;padding:4px;border:1px solid var(--border-color);border-radius:12px;background:rgba(0,0,0,0.03);"></div>
        <div style="padding:14px 16px 16px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--border-color);">
            <button type="button" id="chat-user-search-cancel" style="padding:10px 18px;border-radius:10px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);cursor:pointer;font-size:14px;"><?php echo my_htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
        </div>
    </div>
</div>

<!-- Monaco Editor Modal for Text Files -->
<div id="chat-text-editor-modal" style="display:none;position:fixed;inset:0;z-index:100111;background:var(--modal-bg);flex-direction:column;" aria-modal="true" role="dialog">
    <div style="flex-shrink:0;display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-color);background:var(--group-block-bg);">
        <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
            <h3 id="chat-text-editor-title" style="margin:0;font-size:16px;font-weight:600;color:var(--text-color);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">File Viewer</h3>
            <span id="chat-text-editor-readonly-badge" style="display:none;padding:4px 10px;background:rgba(59,130,246,0.15);color:#3b82f6;border-radius:6px;font-size:12px;font-weight:600;">Read-only</span>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="button" id="chat-text-editor-save" style="display:none;padding:8px 16px;border-radius:10px;border:none;background:linear-gradient(135deg,<?php echo $config['color']; ?> 0%,<?php echo $config['color']; ?>dd 100%);color:#fff;cursor:pointer;font-size:13px;font-weight:700;">Save Changes</button>
            <button type="button" id="chat-text-editor-close" style="padding:8px;border-radius:10px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);cursor:pointer;font-size:20px;line-height:1;width:36px;height:36px;display:flex;align-items:center;justify-content:center;">×</button>
        </div>
    </div>
    <div id="chat-text-editor-container" style="flex:1;min-height:0;"></div>
</div>

<!-- Voice Recording Modal -->
<div id="chat-voice-modal" style="display:none;position:fixed;inset:0;z-index:100111;background:rgba(0,0,0,0.85);align-items:center;justify-content:center;backdrop-filter:blur(10px);" aria-modal="true" role="dialog">
    <div style="background:var(--modal-bg);border:1px solid var(--border-color);border-radius:20px;width:92%;max-width:400px;padding:32px 24px;box-shadow:0 20px 60px rgba(0,0,0,0.5);text-align:center;">
        <div style="margin-bottom:24px;">
            <div id="chat-voice-icon" style="width:80px;height:80px;margin:0 auto 16px;border-radius:50%;background:linear-gradient(135deg,<?php echo $config['color']; ?>22 0%,<?php echo $config['color']; ?>11 100%);display:flex;align-items:center;justify-content:center;position:relative;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="<?php echo $config['color']; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                <div id="chat-voice-pulse" style="display:none;position:absolute;inset:0;border-radius:50%;background:<?php echo $config['color']; ?>33;animation:voicePulse 1.5s ease-out infinite;"></div>
            </div>
            <h3 style="margin:0 0 8px;font-size:18px;font-weight:600;color:var(--text-color);">Voice Message</h3>
            <p id="chat-voice-status" style="margin:0;font-size:14px;color:var(--text-color);opacity:0.7;">Click to start recording</p>
            <div id="chat-voice-timer" style="display:none;margin-top:12px;font-size:24px;font-weight:700;color:<?php echo $config['color']; ?>;font-variant-numeric:tabular-nums;">0:00</div>
        </div>
        <canvas id="chat-voice-waveform" width="320" height="80" style="width:100%;max-width:320px;height:80px;margin:0 auto 24px;display:none;border-radius:12px;background:rgba(0,0,0,0.1);"></canvas>
        <div style="display:flex;gap:12px;justify-content:center;">
            <button type="button" id="chat-voice-cancel" style="flex:1;padding:14px;border-radius:14px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);font-size:15px;font-weight:600;cursor:pointer;">Cancel</button>
            <button type="button" id="chat-voice-record" style="flex:1;padding:14px;border-radius:14px;border:none;background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);color:#fff;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                <span id="chat-voice-record-icon">●</span>
                <span id="chat-voice-record-text">Record</span>
            </button>
            <button type="button" id="chat-voice-send" style="display:none;flex:1;padding:14px;border-radius:14px;border:none;background:linear-gradient(135deg,<?php echo $config['color']; ?> 0%,<?php echo $config['color']; ?>dd 100%);color:#fff;font-size:15px;font-weight:700;cursor:pointer;">Send</button>
        </div>
    </div>
</div>

<!-- Text Formatting Context Menu -->
<div id="chat-format-menu" style="display:none;position:fixed;background:var(--modal-bg);border:1px solid var(--border-color);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.3);padding:6px;z-index:100112;min-width:180px;">
    <div class="chat-format-option" data-format="bold" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="font-weight:700;width:20px;">B</span>
        <span>Bold</span>
    </div>
    <div class="chat-format-option" data-format="italic" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="font-style:italic;width:20px;">I</span>
        <span>Italic</span>
    </div>
    <div class="chat-format-option" data-format="strikethrough" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="text-decoration:line-through;width:20px;">S</span>
        <span>Strikethrough</span>
    </div>
    <div class="chat-format-option" data-format="code" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="font-family:monospace;width:20px;">&lt;/&gt;</span>
        <span>Code</span>
    </div>
    <div class="chat-format-option" data-format="quote" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="width:20px;">"</span>
        <span>Quote</span>
    </div>
    <div class="chat-format-option" data-format="monospace" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="font-family:monospace;width:20px;">M</span>
        <span>Monospace</span>
    </div>
</div>

<script>
// Set CSS variables for chat colors
document.documentElement.style.setProperty('--chat-color', '<?php echo $config['color']; ?>');
document.documentElement.style.setProperty('--chat-color-light', '<?php echo $config['color']; ?>11');
document.documentElement.style.setProperty('--chat-color-medium', '<?php echo $config['color']; ?>22');
document.documentElement.style.setProperty('--chat-color-lighter', '<?php echo $config['color']; ?>08');
document.documentElement.style.setProperty('--chat-color-shadow', '<?php echo $config['color']; ?>66');
document.documentElement.style.setProperty('--chat-color-glow', '<?php echo $config['color']; ?>99');
document.documentElement.style.setProperty('--chat-color-ring', '<?php echo $config['color']; ?>33');
document.documentElement.style.setProperty('--chat-color-border', '<?php echo $config['color']; ?>44');
</script>

<!-- Context Menu -->
<div id="chat-context-menu" style="display:none;position:fixed;background:var(--modal-bg);border:1px solid var(--border-color);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.3);padding:8px;z-index:100001;min-width:160px;">
    <div id="context-edit" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:8px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit
    </div>
    <div id="context-delete" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:#ef4444;font-size:14px;transition:background 0.2s;" onmouseover="this.style.background='rgba(239, 68, 68, 0.1)';" onmouseout="this.style.background='transparent';">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:8px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        Delete
    </div>
</div>

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
    'user_analytics'=>$translations['user_analytics']??'User Analytics',
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
    'weather_timeout'=>$translations['weather_timeout']??'Weather request timed out',
    'weather_no_data'=>$translations['weather_no_data']??'No weather data',
    'weather_fetch_error'=>$translations['weather_fetch_error']??'Failed to load weather',
    'weather_unknown'=>$translations['weather_unknown']??'Unknown location',
    'weather_settings_hint'=>$translations['weather_settings_hint']??'Go to browser site settings and allow location',
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
        detectAndApplySystemTheme();
        applyAutoTheme();
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',applyAutoTheme);
        showNotification();
        
        // Show new device notification immediately if flag is set
        <?php if(isset($_SESSION['new_device_login']) && $_SESSION['new_device_login']): ?>
        showNewDeviceNotification();
        <?php
        // Clear the flag after showing notification
        unset($_SESSION['new_device_login']);
        endif; ?>
        
        const menuButton=document.getElementById('menu-button');
        const sidebar=document.getElementById('sidebar');
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
        const sharedFilesSearch=document.getElementById('shared-files-search');
        const sharedFilesClearSearch=document.getElementById('shared-files-clear-search');
        const sharedFilesTable=document.getElementById('shared-files-table');
        const deleteFilteredBtn = document.getElementById('delete-filtered-files');

        let currentStatsData = <?php echo json_encode($visitStats); ?>;
        let currentFilteredFileIds = [];
        const isHomeDashboard = <?php echo $isHomeDashboard ? 'true' : 'false'; ?>;
        const isAdminUser = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        const appGrid=document.getElementById('app-grid');
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
        function closeSidebarMenu() {
            if(!sidebar) return false;
            const wasOpen = sidebar.classList.contains('open');
            sidebar.classList.remove('open');
            clearAppSearch(false);
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

        // Settings box tooltips — use JS-positioned fixed tooltips to avoid scrollbar/clipping
        document.querySelectorAll('.settings-button-wrapper').forEach(wrapper => {
            const tooltip = wrapper.querySelector('.tooltip');
            const btn = wrapper.querySelector('button');
            if(!tooltip || !btn) return;
            btn.addEventListener('mouseenter', () => {
                const rect = btn.getBoundingClientRect();
                tooltip.style.display = 'block';
                tooltip.style.position = 'fixed';
                tooltip.style.left = (rect.left + rect.width/2) + 'px';
                tooltip.style.top = (rect.top - 36) + 'px';
                tooltip.style.transform = 'translateX(-50%)';
                tooltip.style.zIndex = '99999';
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
            });
        }

        function updateThemeBasedOnSystem(){const isDarkMode=window.matchMedia('(prefers-color-scheme: dark)').matches;document.documentElement.setAttribute('data-theme',isDarkMode?'dark':'light');}
        function applyThemeToAuthModal(theme){const authModal=document.getElementById('auth-modal');if(!authModal)return;const gradientBg=authModal.querySelector('.gradient-bg');if(gradientBg){if(theme==='dark'){gradientBg.style.background='linear-gradient(135deg, var(--primary-color) 0%, #000000 50%, var(--primary-color) 100%)';}else{gradientBg.style.background='linear-gradient(135deg, var(--primary-color) 0%, #ffffff 50%, var(--primary-color) 100%)';}}}
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',updateThemeBasedOnSystem);
        if(document.querySelector('select[name="theme"] option[value="auto"]:checked')){updateThemeBasedOnSystem();}
        function detectAndApplySystemTheme(){const themeSelect=document.querySelector('select[name="theme"]');const isAutoTheme=themeSelect&&themeSelect.value==='auto';if(isAutoTheme){const prefersDark=window.matchMedia('(prefers-color-scheme: dark)').matches;const systemTheme=prefersDark?'dark':'light';updateCSSVariables(systemTheme);applyThemeToAuthModal(systemTheme);updateAppIframeTheme(systemTheme);}}
        function updateCSSVariables(theme){const root=document.documentElement;if(theme==='dark'){root.style.setProperty('--bg-color','#333');root.style.setProperty('--text-color','#fff');root.style.setProperty('--sidebar-bg','#444');root.style.setProperty('--group-block-bg','#555');root.style.setProperty('--app-item-bg','#666');root.style.setProperty('--tooltip-bg','#666');root.style.setProperty('--modal-bg','#555');root.style.setProperty('--border-color','#666');root.style.setProperty('--scrollbar-track','#222');root.style.setProperty('--scrollbar-thumb','#555');root.style.setProperty('--scrollbar-thumb-hover','#777');root.style.setProperty('--icon-bg-color','#444');root.style.setProperty('--link-color','#ddd');root.style.setProperty('--link-hover-color',getComputedStyle(root).getPropertyValue('--primary-color'));root.style.setProperty('--table-header-bg','#444');root.style.setProperty('--table-row-hover','#555');root.style.setProperty('--app-icon-grad-start','color-mix(in srgb,var(--primary-color) 72%,white)');root.style.setProperty('--app-icon-grad-end','color-mix(in srgb,var(--primary-color) 88%,black)');}else{root.style.setProperty('--bg-color','#fff');root.style.setProperty('--text-color','#1f2937');root.style.setProperty('--sidebar-bg','#f0f0f0');root.style.setProperty('--group-block-bg','#e9e9e9');root.style.setProperty('--app-item-bg','#d9d9d9');root.style.setProperty('--tooltip-bg','#333');root.style.setProperty('--modal-bg','#fff');root.style.setProperty('--border-color','#ccc');root.style.setProperty('--scrollbar-track','#f0f0f0');root.style.setProperty('--scrollbar-thumb','#c0c0c0');root.style.setProperty('--scrollbar-thumb-hover','#999');root.style.setProperty('--icon-bg-color','#f0f0f0');root.style.setProperty('--link-color','#333');root.style.setProperty('--link-hover-color',getComputedStyle(root).getPropertyValue('--primary-color'));root.style.setProperty('--table-header-bg','#f0f0f0');root.style.setProperty('--table-row-hover','#f5f5f5');root.style.setProperty('--app-icon-grad-start','color-mix(in srgb,var(--primary-color) 84%,black)');root.style.setProperty('--app-icon-grad-end','color-mix(in srgb,var(--primary-color) 65%,black)');}}
        function updateScrollbarStyles(theme){/* handled by CSS variables updated in updateCSSVariables */}        function applyAutoTheme(){const themeSelect=document.querySelector('select[name="theme"]');if(themeSelect&&themeSelect.value==='auto'){const prefersDark=window.matchMedia('(prefers-color-scheme: dark)').matches;const systemTheme=prefersDark?'dark':'light';updateCSSVariables(systemTheme);updateScrollbarStyles(systemTheme);}}
        function updateAppIframeTheme(theme){const iframe=document.getElementById('app-iframe');if(iframe){const src=iframe.src;const newSrc=src.replace(/theme=[^&]*/,'theme='+theme);if(newSrc!==src){iframe.src=newSrc;}}}
        document.querySelectorAll('.password-toggle').forEach(button=>{button.addEventListener('click',function(){const targetId=this.getAttribute('data-target');const input=document.getElementById(targetId);const icon=this.querySelector('svg');if(input){if(input.type==='password'){input.type='text';icon.innerHTML='<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';}else{input.type='password';icon.innerHTML='<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';}}});});
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
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:flex-end;justify-content:center;z-index:100002;padding:20px;';
            const box = document.createElement('div');
            box.style.cssText = 'width:min(680px,100%);background:var(--modal-bg,#fff);color:var(--text-color,#000);border:1px solid var(--border-color,#ccc);border-radius:16px;padding:18px 18px 16px;box-shadow:0 20px 60px rgba(0,0,0,0.28);';
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
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;justify-content:center;align-items:center;z-index:100001;';
            var box = document.createElement('div');
            box.style.cssText = 'background:var(--modal-bg,#fff);color:var(--text-color,#000);padding:30px 25px;border-radius:12px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);';
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
        
        const addTrustedBtn=document.getElementById('add-trusted-device-btn');
        if(addTrustedBtn){addTrustedBtn.addEventListener('click',function(){
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
        });}
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
                const currentIp=data.current_ip||'';
                const noTrusted=T.no_trusted_devices||'No trusted devices';
                if(devices.length===0){
                    container.innerHTML='<p id="no-trusted-msg">'+noTrusted+'</p><div style="margin-bottom:15px;"><button type="button" id="add-trusted-device-btn" class="sidebar-button" style="background:#4CAF50;color:white;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;font-size:14px;width:100%;">'+(T.add_trusted_device||'Add This Device')+'</button></div>';
                    const newBtn=document.getElementById('add-trusted-device-btn');
                    if(newBtn){newBtn.addEventListener('click',function(){
                        showTrustedConfirm(T.add_trusted_device_confirm||'Add this device to your trusted list? You will stay logged in even after the session timeout.', function(ok){
                            if(!ok) return;
                            fetch('/main?api=client_auth&action=add_trusted_device',{method:'POST',headers:trustedDeviceRequestHeaders(),body:JSON.stringify({})})
                            .then(r=>r.json()).then(d=>{
                                if(d.ok){
                                    persistTrustedDeviceClientState(d.encrypted_token || '');
                                    showTempNotification(T.device_added_trusted||'Device added','success');
                                    refreshTrustedDevices();
                                }
                                else{showTempNotification(d.error||'Error','error');}
                            }).catch(()=>showTempNotification(T.network_error||'Network error','error'));
                        });
                    });}
                    return;
                }
                let html='<div style="margin-bottom:15px;"><button type="button" id="add-trusted-device-btn" class="sidebar-button" style="background:#4CAF50;color:white;border:none;padding:10px 16px;border-radius:4px;cursor:pointer;font-size:14px;width:100%;">'+(T.add_trusted_device||'Add This Device')+'</button></div>';
                const currentFp=data.current_fingerprint||'';
                html+='<table class="trusted-devices-table"><thead><tr><th>'+(T.trusted_ip||'IP')+'</th><th>'+(T.device_name||'Device Name')+'</th><th>'+(T.trusted_since||'Trusted Since')+'</th><th>'+(T.trusted_last_seen||'Last Seen')+'</th><th></th></tr></thead><tbody>';
                devices.forEach(function(d){
                    const isCurrent=(d.fingerprint||'')=== currentFp;
                    const deviceToken = d.device_token || '';
                    html+='<tr class="'+(isCurrent?'current-trusted-device':'')+'"><td>'+escHtml(d.ip||'—')+'</td><td>'+escHtml(d.device_name||'Unknown')+'</td><td>'+formatDate(d.added_at)+'</td><td>'+formatDate(d.last_seen)+'</td><td><button type="button" class="remove-trusted-btn" data-fingerprint="'+escHtml(d.fingerprint||'')+'" data-token="'+escHtml(deviceToken)+'" data-name="'+escHtml(d.device_name||'')+'">'+(T.remove_trusted_device||'Remove from Trusted')+'</button></td></tr>';
                });
                html+='</tbody></table>';
                container.innerHTML=html;
                const btn=document.getElementById('add-trusted-device-btn');
                if(btn){btn.style.display='none';}
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
                    + '<td style="padding:8px 10px;font-size:11px;font-family:\'Courier New\',monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;opacity:.7;" title="' + sharedFileText(url) + '"><span style="display: inline-block; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; direction: rtl; text-align: left;">' + sharedFileText(pathDisplay) + '</span></td>'
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
        function closeAllModals(){const modals=[settingsModal,qrModal,emailModal,usersModal,profileModal,sessionsModal,sharedFilesModal,authModal,deleteUserModal,document.getElementById('reset-user-password-modal'),tgBotModal,shortcutsModal];modals.forEach(modal=>{if(modal){modal.style.display='none';}});const np=document.getElementById('news-portal-overlay');if(np)np.classList.remove('active');closePolicyModal();}

        // ===== Telegram Bot Modal =====
        const tgBotModal = document.getElementById('tg-bot-modal');
        const tgBotButton = document.getElementById('tg-bot-button');
        const closeTgBot = document.getElementById('close-tg-bot');
        const saveTgBot = document.getElementById('save-tg-bot');
        const testTgBot = document.getElementById('test-tg-bot');
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
            const existingNotification=document.querySelector('.temp-notification');
            if(existingNotification){existingNotification.remove();}
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
                setTimeout(() => notification.remove(), 250);
            });
            setTimeout(() => { notification.classList.add('show'); }, 50);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => { if(notification.parentNode) notification.remove(); }, 250);
            }, 4000);
        }
        
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
        if(closeProfile){closeProfile.addEventListener('click',()=>{if(profileModal){profileModal.style.display='none';}closePasswordChangeBox();if(photoForm){photoForm.classList.remove('active');}});}
        if(profileModal){profileModal.addEventListener('click',(e)=>{if(e.target===profileModal){profileModal.style.display='none';closePasswordChangeBox();if(photoForm){photoForm.classList.remove('active');}}});}
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
        if(usersButton){
            usersButton.addEventListener('click',()=>{closeAllModals();if(usersModal){usersModal.style.display='flex';}});
            if(closeUsers){closeUsers.addEventListener('click',()=>{if(usersModal){usersModal.style.display='none';}});}
            if(usersModal){
                usersModal.addEventListener('click',(e)=>{if(e.target===usersModal){usersModal.style.display='none';}});
            }
        }
        
        if(sessionsButton){sessionsButton.addEventListener('click',()=>{closeAllModals();if(sessionsModal){sessionsModal.style.display='flex';}});if(closeSessions){closeSessions.addEventListener('click',()=>{if(sessionsModal){sessionsModal.style.display='none';}});}if(sessionsModal){sessionsModal.addEventListener('click',(e)=>{if(e.target===sessionsModal){sessionsModal.style.display='none';}});}}
        const modalButtons=[qrButton,emailButton,usersButton,sessionsButton].filter(btn=>btn);modalButtons.forEach(button=>{if(button){button.addEventListener('click',(e)=>{e.preventDefault();e.stopPropagation();});}});
        if(menuButton){menuButton.addEventListener('click',()=>{ if(sidebar && sidebar.classList.contains('open')) closeSidebarMenu(); else sidebar.classList.add('open'); });}
        if(settingsButton){settingsButton.addEventListener('click',()=>{closeAllModals();if(settingsModal){settingsModal.style.display='flex';}});}
        if(closeSettings){closeSettings.addEventListener('click',()=>{if(settingsModal)settingsModal.style.display='none';});}
        if(settingsModal){settingsModal.addEventListener('click',(e)=>{if(e.target===settingsModal){settingsModal.style.display='none';}});}
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
            const modals = [settingsModal, qrModal, emailModal, usersModal, profileModal, sessionsModal, sharedFilesModal, authModal, shortcutsModal];
            const np = document.getElementById('news-portal-overlay');
            return modals.some(modal => modal && modal.style.display === 'flex') || (np && np.classList.contains('active'));
        }
        function openShortcutsModal(){
            if(settingsModal && settingsModal.style.display !== 'flex'){
                closeAllModals();
                settingsModal.style.display = 'flex';
            }
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
            if(!isHomeDashboard) return;
            if(e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;
            if(isTypingContext(e.target) || isAnyBlockingModalOpen()) return;
            if(!code) return;
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
            });
        }
        if(clearSearch){
            clearSearch.addEventListener('click',()=>{
                if(searchBar){
                    clearAppSearch(true);
                }
            });
        }
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


        // stars

    const canvas = document.getElementById("constellation-bg");
    const ctx = canvas.getContext("2d");
    let stars = [];
    let constellationRafId = 0;
    let constellationActive = true;
    const STAR_COUNT = 80;

    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    window.addEventListener("resize", resize);
    resize();

    function initStars() {
        stars = [];
        for (let i = 0; i < STAR_COUNT; i++) {
            stars.push({
                x: Math.random()*canvas.width,
                y: Math.random()*canvas.height,
                vx: (Math.random()-0.5)*0.2,
                vy: (Math.random()-0.5)*0.2
            });
        }
    }
    initStars();

    function draw() {

        ctx.clearRect(0,0,canvas.width,canvas.height);

        ctx.fillStyle="rgba(150,150,150,0.8)";
        ctx.strokeStyle="rgba(150,150,150,0.15)";

        for(let i=0;i<stars.length;i++){

            let s=stars[i];

            s.x+=s.vx;
            s.y+=s.vy;

            if(s.x<0||s.x>canvas.width) s.vx*=-1;
            if(s.y<0||s.y>canvas.height) s.vy*=-1;

            ctx.beginPath();
            ctx.arc(s.x,s.y,1.2,0,Math.PI*2);
            ctx.fill();

            for(let j=i+1;j<stars.length;j++){
                let s2=stars[j];
                let dx=s.x-s2.x;
                let dy=s.y-s2.y;
                let dist=Math.sqrt(dx*dx+dy*dy);

                if(dist<120){
                    ctx.beginPath();
                    ctx.moveTo(s.x,s.y);
                    ctx.lineTo(s2.x,s2.y);
                    ctx.stroke();
                }
            }
        }

        if(!constellationActive) return;
        constellationRafId=requestAnimationFrame(draw);
    }
    document.addEventListener("visibilitychange",()=>{
        if(document.hidden){
            constellationActive=false;
            if(constellationRafId) cancelAnimationFrame(constellationRafId);
        }else{
            if(!constellationActive){
                constellationActive=true;
                draw();
            }
        }
    });
    draw();
</script>

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
#ap-dynamic-root {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

/* Top Bar */
#ap-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--sidebar-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 0 20px;
    height: 52px;
    flex-shrink: 0;
}
#ap-topbar .ap-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
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
/* Close button — icon-only circle */
#ap-close-btn {
    width: 36px;
    height: 36px;
    background: none;
    border: 1.5px solid var(--border-color);
    color: var(--text-color);
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s;
    padding: 0;
}
#ap-close-btn:hover {
    background: color-mix(in srgb, #f85149 15%, transparent);
    color: #f85149;
    border-color: #f85149;
    transform: rotate(90deg);
}

/* Body */
#ap-body { display: flex; flex: 1; min-height: 0; overflow: hidden; }
.ap-app-access-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:8px;max-height:min(62vh,520px);overflow-y:auto;padding:4px 2px;}
.ap-app-access-tile{display:flex;flex-direction:column;align-items:center;gap:4px;padding:8px 6px;border:1px solid var(--border-color);border-radius:8px;background:var(--group-block-bg);text-align:center;}
.ap-app-access-icon{width:40px;height:40px;object-fit:contain;border-radius:6px;}
.ap-app-access-icon-svg{display:block;width:40px;height:40px;background:radial-gradient(circle at var(--g-hx,34%) var(--g-hy,30%),color-mix(in srgb,var(--primary-color) 22%,white) 0%,transparent 62%),linear-gradient(145deg,color-mix(in srgb,var(--primary-color) 38%,white) 0%,color-mix(in srgb,var(--primary-color) 78%,black) 100%);-webkit-mask-image:var(--icon-src);mask-image:var(--icon-src);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain;}
.ap-app-access-title{font-size:10px;line-height:1.2;font-weight:600;max-width:100%;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;}
.ap-app-access-level{width:100%;font-size:10px;padding:2px 4px;border-radius:4px;border:1px solid var(--border-color);background:var(--bg-color);color:var(--text-color);}
.ap-world-map{position:relative;height:360px;border:1px solid var(--border-color);border-radius:8px;background:color-mix(in srgb,var(--group-block-bg) 86%,var(--primary-color) 14%);overflow:hidden;margin-bottom:10px;}
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
.ap-gitea-icon {
    display: inline-block;
    width: 16px;
    height: 16px;
    flex: 0 0 auto;
    background-color: currentColor;
    -webkit-mask-image: url('/apps/gitea/icon.svg');
    mask-image: url('/apps/gitea/icon.svg');
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
    -webkit-mask-position: center;
    mask-position: center;
    -webkit-mask-size: contain;
    mask-size: contain;
}
.ap-card-title .ap-gitea-icon {
    width: 20px;
    height: 20px;
    margin-right: 6px;
    vertical-align: middle;
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
#ap-stats-hot-table .handsontable td {
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
#ap-stats-hot-table .handsontable .htCore thead tr:first-child th {
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
        <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor" style="color:var(--primary-color);animation:_ap_spin 1s linear infinite;flex-shrink:0;"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46A7.93 7.93 0 0020 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74A7.93 7.93 0 004 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
        <span id="ap-loading-msg" style="font-size:14px;opacity:.6;font-family:sans-serif;"><?php echo my_htmlspecialchars($translations['admin_panel_loading']??'Loading Admin Panel...'); ?></span>
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
window.apLoadMonaco = window.apLoadMonaco || function(){
    if(window.monaco) return Promise.resolve(window.monaco);
    if(window.__apMonacoPromise) return window.__apMonacoPromise;
    var localVs='/project/assets/monaco-0.45.0/vs';
    var cdnVs='https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs';
    function loadScript(src){
        return new Promise(function(resolve,reject){
            var existing=document.querySelector('script[src="'+src+'"]');
            var s=existing||document.createElement('script');
            s.addEventListener('load',function(){s.dataset.apLoaded='1';resolve();},{once:true});
            s.addEventListener('error',function(){reject(new Error('Failed to load '+src));},{once:true});
            if(existing&&existing.dataset.apLoaded==='1') setTimeout(resolve,0);
            else if(existing&&existing.readyState==='complete') setTimeout(resolve,0);
            else if(!existing){s.src=src;s.async=false;document.head.appendChild(s);}
        });
    }
    function loadEditor(vsPath){
        return new Promise(function(resolve,reject){
            try{
                require.config({paths:{vs:vsPath}});
                require(['vs/editor/editor.main'],function(){
                    if(typeof define==='function'&&(!require.defined||!require.defined('vs/editor/editor.api'))) define('vs/editor/editor.api',[],function(){return window.monaco;});
                    resolve(window.monaco);
                },reject);
            }catch(e){reject(e);}
        });
    }
    function tryLoad(vsPath){
        var ready=typeof require==='function'?Promise.resolve():loadScript(vsPath+'/loader.js');
        return ready.then(function(){return loadEditor(vsPath);});
    }
    window.__apMonacoPromise = tryLoad(localVs).catch(function(){return tryLoad(cdnVs);});
    return window.__apMonacoPromise;
};

var panelHash={'ap-users':'usermanagement','ap-email':'emailsetting','ap-apps-access':'appaccess','ap-telegram':'telegram','ap-stats':'stats','ap-ssh':'sshterminal','ap-jsonfiles':'jsonfiles','ap-backup':'backup','ap-docs':'documentation','ap-db':'database','ap-omniroute':'omniroute','ap-docker':'docker','ap-docker-manager':'dockermanager','ap-performance':'performance'};
var hashPanel={'usermanagement':'ap-users','emailsetting':'ap-email','appaccess':'ap-apps-access','telegram':'ap-telegram','stats':'ap-stats','sshterminal':'ap-ssh','jsonfiles':'ap-jsonfiles','backup':'ap-backup','documentation':'ap-docs','database':'ap-db','omniroute':'ap-omniroute','docker':'ap-docker','dockermanager':'ap-docker-manager','performance':'ap-performance'};
var OMNIROUTE_URL='/omniroute-bridge?next=/admin-omniroute/home';
function loadOmniRouteIframe(){
    var frame=document.getElementById('ap-omniroute-frame');
    if(!frame) return;
    var target=frame.getAttribute('data-src')||OMNIROUTE_URL;
    if(frame.dataset.loaded===target) return;
    frame.dataset.loaded=target;
    frame.src=target;
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
    'github_pull_settings'=>$translations['github_pull_settings']??'Pull через GitHub',
    'github_update'=>$translations['github_update']??'Обновить',
    'github_refresh'=>$translations['github_refresh']??'Refresh',
    'github_pull_ok'=>$translations['github_pull_ok']??'Updated successfully',
    'github_pull_up_to_date'=>$translations['github_pull_up_to_date']??'Already up to date',
    'github_no_changes'=>$translations['github_no_changes']??'No pending changes',
    'github_pull_confirm'=>$translations['github_pull_confirm']??'Pull changes from main and restart the container?',
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
function openAdminPanel(target){
    if(settingsM) settingsM.style.display='none';
    overlay.classList.add('active');
    document.body.style.overflow='hidden';
    if(!apLoaded){skeleton.style.display='flex';dynRoot.style.display='none';loadPanel(target||'ap-users');}
    else openPanel(target||'ap-users');
}
function closeAdminPanel(){overlay.classList.remove('active');document.body.style.overflow='';clearHash();}

/* ── AJAX load ── */
function loadPanel(target, year){
    var fd=new FormData(); 
    fd.append('get_admin_panel','1'); 
    fd.append('ajax','1');
    if(year) fd.append('stats_year', year);
    var msgEl=document.getElementById('ap-loading-msg');
    fetch('/main',{method:'POST',body:fd})
        .then(function(r){
            if(!r.ok) return r.text().then(function(t){throw new Error('HTTP '+r.status+': '+t.substring(0,300));});
            return r.text().then(function(t){
                // Strip any leading PHP warnings/notices before the JSON
                var jsonStart = t.indexOf('{');
                if(jsonStart > 0){ t = t.substring(jsonStart); }
                try{ return JSON.parse(t); }
                catch(e){ throw new Error('Invalid JSON. Server returned: '+t.substring(0,300)); }
            });
        })
        .then(function(d){
            if(!d||!d.success){if(msgEl)msgEl.textContent='Error: '+(d&&d.error?d.error:'Unknown error');return;}
            apLoaded=true;
            try{apStatsData=JSON.parse(d.stats||'{}');}catch(e){apStatsData={};}
            // Store available years from backend
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
        .catch(function(err){if(msgEl)msgEl.textContent='Error: '+(err.message||'Unknown error');console.error('[AdminPanel]',err);});
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
    });});
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
            if(name==='github'&&typeof window.apGithubLoadStatus==='function'){window.apGithubLoadStatus();}
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
    var seBtn=dynRoot.querySelector('#ap-save-email'), eStatus=dynRoot.querySelector('#ap-email-status');
    if(seBtn) seBtn.addEventListener('click',function(){
        setStatus(eStatus,T.saving,'info');
        var fd=new FormData(); fd.append('save_email_settings','1'); fd.append('ajax','1');
        ['gcid','gcsecret','smtp-host','smtp-port','smtp-user','smtp-pass'].forEach(function(k){var el=dynRoot.querySelector('#ap-'+k);if(el)fd.append(k.replace('smtp-','smtp_').replace('gcid','google_client_id').replace('gcsecret','google_client_secret').replace('smtp_host','smtp_host').replace('smtp_port','smtp_port').replace('smtp_user','smtp_username').replace('smtp_pass','smtp_password'),el.value);});
        // simpler explicit approach:
        fd.set('google_client_id',    (dynRoot.querySelector('#ap-gcid')||{value:''}).value);
        fd.set('google_client_secret',(dynRoot.querySelector('#ap-gcsecret')||{value:''}).value);
        fd.set('smtp_host',           (dynRoot.querySelector('#ap-smtp-host')||{value:''}).value);
        fd.set('smtp_port',           (dynRoot.querySelector('#ap-smtp-port')||{value:587}).value);
        fd.set('smtp_username',       (dynRoot.querySelector('#ap-smtp-user')||{value:''}).value);
        fd.set('smtp_password',       (dynRoot.querySelector('#ap-smtp-pass')||{value:''}).value);
        fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){setStatus(eStatus,d.success?('+ '+T.email_saved):('x '+(d.error||T.error)),d.success?'success':'error');if(d.success&&typeof showTempNotification!=='undefined')showTempNotification(T.email_saved,'success');}).catch(function(){setStatus(eStatus,'x '+T.error,'error');});
    });
    /* telegram */
    var tgTok=dynRoot.querySelector('#ap-tg-token'), tgSt=dynRoot.querySelector('#ap-tg-status');
    function tgReq(a){var fd=new FormData();fd.append(a,'1');fd.append('ajax','1');fd.append('tg_bot_token',tgTok?tgTok.value.trim():'');var tw=dynRoot.querySelector('#ap-tg-webapp-url');fd.append('tg_webapp_base_url',tw?tw.value.trim():'');return fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).catch(function(){return{success:false,error:'Network error'};});}
    var tgSv=dynRoot.querySelector('#ap-tg-save'),tgTs=dynRoot.querySelector('#ap-tg-test');
    if(tgSv) tgSv.addEventListener('click',function(){setStatus(tgSt,T.saving,'info');tgReq('save_tg_bot_config').then(function(d){setStatus(tgSt,d.success?('+ '+T.tg_saved):('x '+(d.error||T.error)),d.success?'success':'error');});});
    if(tgTs) tgTs.addEventListener('click',function(){setStatus(tgSt,'...','info');tgReq('test_tg_bot').then(function(d){setStatus(tgSt,d.success?('+ '+T.tg_test_ok):('x '+(d.error||T.tg_test_fail)),d.success?'success':'error');});});
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
    (function(){
        var ghSt=dynRoot.querySelector('#ap-github-pull-status'),ghRefresh=dynRoot.querySelector('#ap-github-refresh'),ghPull=dynRoot.querySelector('#ap-github-pull'),ghChanges=dynRoot.querySelector('#ap-github-changes'),ghLocal=dynRoot.querySelector('#ap-github-local-head'),ghRemote=dynRoot.querySelector('#ap-github-remote-head');
        function renderGithubStatus(d){
            if(!d||!d.success){setStatus(ghSt,'x '+(d&&d.error?d.error:T.error),'error');if(ghChanges&&d&&d.output)ghChanges.textContent=d.output;return;}
            if(ghLocal)ghLocal.textContent=(d.branch||'main')+' @ '+(d.local_head||'—');
            if(ghRemote)ghRemote.textContent='origin/main @ '+(d.remote_head||'—');
            if(ghChanges){
                if(d.up_to_date){ghChanges.textContent=T.github_no_changes||'No pending changes';}
                else{
                    var txt='';
                    if(d.commits)txt+=d.commits+'\n\n';
                    if(d.stat)txt+=d.stat;
                    ghChanges.textContent=txt.trim()||('+'+(d.behind||0)+' commit(s)');
                }
            }
            if(ghPull)ghPull.disabled=!!d.up_to_date;
            setStatus(ghSt,d.up_to_date?(T.github_pull_up_to_date||'Up to date'):((d.behind||0)+' commit(s) available'),d.up_to_date?'success':'info');
        }
        window.apGithubLoadStatus=function(){
            if(!ghChanges)return;
            setStatus(ghSt,T.fm_loading||'Loading…','info');
            var fd=new FormData();fd.append('git_pull_status','1');fd.append('ajax','1');
            fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(renderGithubStatus).catch(function(){setStatus(ghSt,'x '+T.error,'error');});
        };
        if(ghRefresh)ghRefresh.addEventListener('click',window.apGithubLoadStatus);
        if(ghPull)ghPull.addEventListener('click',function(){
            var msg=T.github_pull_confirm||'Pull changes from main and restart the container?';
            if(typeof fmConfirm==='function'){fmConfirm(msg,function(){doGithubPull();});return;}
            if(!confirm(msg))return;
            doGithubPull();
        });
        function doGithubPull(){
            setStatus(ghSt,T.saving,'info');if(ghPull)ghPull.disabled=true;if(ghRefresh)ghRefresh.disabled=true;
            var fd=new FormData();fd.append('git_pull_update','1');fd.append('ajax','1');
            fetch('/main',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
                if(d.success){
                    setStatus(ghSt,'+ '+(d.message||T.github_pull_ok||'Updated'),'success');
                    if(typeof showTempNotification!=='undefined')showTempNotification(d.message||T.github_pull_ok||'Updated','success');
                    if(d.restarted){setTimeout(function(){location.reload();},2500);}
                    else if(typeof window.apGithubLoadStatus==='function')window.apGithubLoadStatus();
                }else{
                    setStatus(ghSt,'x '+(d.error||T.error),'error');
                }
            }).catch(function(){setStatus(ghSt,'x '+T.error,'error');}).finally(function(){if(ghRefresh)ghRefresh.disabled=false;if(ghPull)ghPull.disabled=false;});
        }
    })();
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
    function renderWorldMap(){if(!worldMap)return;var countries={};try{countries=JSON.parse(worldMap.dataset.countries||'{}')||{};}catch(e){}try{blockedCountries=JSON.parse(worldMap.dataset.selected||'[]')||[];}catch(e){blockedCountries=[];}worldMap.innerHTML='';apLoadAmChartsMaps().then(function(){if(!window.am5||!window.am5map||!window.am5geodata_worldLow)return;if(worldMap._apMapRoot)worldMap._apMapRoot.dispose();var root=am5.Root.new(worldMap);worldMap._apMapRoot=root;root.setThemes([am5themes_Animated.new(root)]);var chart=root.container.children.push(am5map.MapChart.new(root,{panX:'translateX',panY:'translateY',wheelX:'zoom',wheelY:'zoom',projection:am5map.geoMercator()}));var series=chart.series.push(am5map.MapPolygonSeries.new(root,{geoJSON:am5geodata_worldLow,exclude:['AQ']}));var normal=am5.color(0x64748b),active=am5.color(0xef4444),stroke=am5.color(0xffffff);function apCountryCode(target){return target.dataItem&&target.dataItem.dataContext&&target.dataItem.dataContext.id;}function apply(target){var code=apCountryCode(target);target.set('fill',blockedCountries.indexOf(code)!==-1?active:normal);}series.mapPolygons.template.setAll({tooltipText:'{name}',interactive:true,fill:normal,stroke:stroke,strokeWidth:.5});series.mapPolygons.template.adapters.add('tooltipText',function(text,target){var code=target.dataItem&&target.dataItem.dataContext&&target.dataItem.dataContext.id;return countries[code]||text;});series.mapPolygons.template.events.on('click',function(ev){var target=ev.target,code=target.dataItem&&target.dataItem.dataContext&&target.dataItem.dataContext.id;if(!code||!countries[code])return;var i=blockedCountries.indexOf(code);if(i===-1)blockedCountries.push(code);else blockedCountries.splice(i,1);apply(target);});series.mapPolygons.template.events.on('pointerover',function(ev){var code=apCountryCode(ev.target);ev.target.set('fill',blockedCountries.indexOf(code)!==-1?active:am5.color(0x3b82f6));});series.mapPolygons.template.events.on('pointerout',function(ev){apply(ev.target);});series.events.on('datavalidated',function(){series.mapPolygons.each(apply);chart.goHome(0);});chart.set('zoomControl',am5map.ZoomControl.new(root,{}));}).catch(function(){worldMap.textContent='Map library failed to load';});}
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
    var apIsDark = getComputedStyle(document.documentElement).getPropertyValue('--bg-color').trim() === '#333';

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
    var isDark = document.documentElement.style.getPropertyValue('--bg-color')==='#333'
        || window.getComputedStyle(document.documentElement).getPropertyValue('--bg-color').trim()==='#333';
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

})();
<?php endif; ?>
</script>

<?php endif; ?><?php
// Collect all flash messages from this page load and emit them as push notifications
$flashMessages = [];
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
    <?php endif; ?>
});
</script>
<?php endif; ?>

<!-- Universal confirm/alert modal -->
<div id="fm-confirm-modal" style="display:none;position:fixed;inset:0;z-index:101000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
  <div style="background:var(--modal-bg);border:1px solid var(--border-color);border-radius:10px;padding:24px 28px;min-width:300px;max-width:420px;width:90%;box-shadow:0 8px 40px rgba(0,0,0,.35);">
    <div id="fm-confirm-msg" style="font-size:14px;font-family:sans-serif;color:var(--text-color);margin-bottom:20px;line-height:1.5;"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;">
      <button id="fm-confirm-cancel" type="button" style="background:var(--group-block-bg);border:1px solid var(--border-color);color:var(--text-color);border-radius:6px;padding:7px 18px;font-size:13px;cursor:pointer;">Cancel</button>
      <button id="fm-confirm-ok"     type="button" style="background:#dc3545;border:none;color:#fff;border-radius:6px;padding:7px 18px;font-size:13px;cursor:pointer;font-weight:600;">OK</button>
    </div>
  </div>
</div>
<style>
@keyframes fmAlertIn{from{opacity:0;transform:translateY(14px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
#fm-alert-modal.fm-alert-open{display:flex!important;align-items:center;justify-content:center;padding:max(16px,env(safe-area-inset-top)) max(16px,env(safe-area-inset-right)) max(16px,env(safe-area-inset-bottom)) max(16px,env(safe-area-inset-left));box-sizing:border-box;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);background:rgba(0,0,0,.52)!important;}
#fm-alert-modal.fm-alert-open #fm-alert-card{animation:fmAlertIn .32s cubic-bezier(.22,1,.36,1) forwards;}
#fm-alert-card{max-width:min(420px,100%);width:100%;border-radius:20px;overflow:hidden;box-shadow:0 28px 80px rgba(0,0,0,.45),0 0 0 1px color-mix(in srgb,var(--border-color) 65%,transparent);background:linear-gradient(165deg,color-mix(in srgb,var(--modal-bg) 94%,var(--primary-color) 6%) 0%,var(--modal-bg) 50%);position:relative;}
#fm-alert-card-inner{padding:28px 26px 22px;text-align:center;}
#fm-alert-icon-wrap{width:56px;height:56px;margin:0 auto 16px;border-radius:50%;background:linear-gradient(145deg,color-mix(in srgb,var(--primary-color) 35%,transparent),color-mix(in srgb,var(--primary-color) 12%,transparent));display:flex;align-items:center;justify-content:center;box-shadow:0 8px 28px color-mix(in srgb,var(--primary-color) 25%,transparent);}
#fm-alert-title{font-size:18px;font-weight:700;color:var(--text-color);letter-spacing:-.02em;margin:0 0 10px;line-height:1.3;}
#fm-alert-msg{font-size:15px;line-height:1.55;color:var(--text-color);opacity:.9;word-wrap:break-word;margin:0;}
#fm-alert-actions{padding:0 22px 22px;display:flex;justify-content:stretch;}
#fm-alert-ok{flex:1;padding:14px 20px;border:none;border-radius:14px;font-size:15px;font-weight:700;cursor:pointer;color:#fff;background:linear-gradient(135deg,var(--primary-color) 0%,color-mix(in srgb,var(--primary-color) 78%,#000) 100%);box-shadow:0 6px 22px color-mix(in srgb,var(--primary-color) 42%,transparent);transition:transform .15s,box-shadow .15s;}
#fm-alert-ok:hover{transform:translateY(-1px);box-shadow:0 8px 26px color-mix(in srgb,var(--primary-color) 50%,transparent);}
</style>
<div id="fm-alert-modal" style="display:none;position:fixed;inset:0;z-index:101000;flex-direction:column;" aria-hidden="true" role="presentation">
  <div id="fm-alert-card" role="alertdialog" aria-modal="true" aria-labelledby="fm-alert-title" aria-describedby="fm-alert-msg" onclick="event.stopPropagation()">
    <div id="fm-alert-card-inner">
      <div id="fm-alert-icon-wrap" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg></div>
      <h2 id="fm-alert-title" style="font-size:18px;font-weight:700;color:var(--text-color);letter-spacing:-.02em;margin:0 0 10px;line-height:1.3;"><?php echo my_htmlspecialchars($translations['alert_modal_title'] ?? 'Notice'); ?></h2>
      <p id="fm-alert-msg" style="font-size:15px;line-height:1.55;color:var(--text-color);opacity:.9;word-wrap:break-word;margin:0;"></p>
    </div>
    <div id="fm-alert-actions">
      <button type="button" id="fm-alert-ok"><?php echo my_htmlspecialchars($translations['confirm_label'] ?? $translations['save'] ?? 'OK'); ?></button>
    </div>
  </div>
</div>
<!-- Media viewer modal -->
<div id="fm-media-modal" style="display:none;position:fixed;inset:0;z-index:99998;background:rgba(0,0,0,.92);align-items:center;justify-content:center;flex-direction:column;">
  <button id="fm-media-close" type="button" style="position:absolute;top:18px;right:24px;background:none;border:none;color:#fff;font-size:28px;cursor:pointer;z-index:2;line-height:1;">&times;</button>
  <div id="fm-media-content" style="max-width:92vw;max-height:88vh;display:flex;align-items:center;justify-content:center;"></div>
  <div id="fm-media-name" style="color:rgba(255,255,255,.55);font-size:12px;margin-top:12px;font-family:'Courier New',monospace;"></div>
</div>

<!-- ══ OTP Generator Modal ══════════════════════════════════════════════════ -->
<div id="otp-gen-modal" style="display:none;position:fixed;inset:0;z-index:20000;background:rgba(6,10,16,0.28);align-items:center;justify-content:center;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);">
  <div style="background:var(--modal-bg);padding:30px;border-radius:12px;width:90%;max-width:400px;box-shadow:0 10px 30px rgba(0,0,0,.4);position:relative;text-align:center;max-height:90vh;overflow-y:auto;">
    <button onclick="closeOtpGeneratorModal()" style="position:absolute;top:10px;right:10px;background:none;border:none;color:#ff4444;font-size:22px;cursor:pointer;line-height:1;padding:4px;">&times;</button>

    <!-- Title -->
    <div style="font-size:20px;font-weight:700;margin-bottom:6px;color:var(--text-color);"><?php echo my_htmlspecialchars($translations['otp_gen_title']??'Generate One-Time PIN'); ?></div>
    <div style="font-size:13px;opacity:.6;margin-bottom:20px;line-height:1.5;"><?php echo my_htmlspecialchars($translations['otp_gen_subtitle']??'Share this code to allow one-time login into your account.'); ?></div>

    <!-- TTL selector -->
    <label style="font-size:13px;display:block;text-align:left;margin-bottom:6px;color:var(--text-color);"><?php echo my_htmlspecialchars($translations['otp_ttl_label']??'Valid for:'); ?></label>
    <select id="otp-ttl-select" style="width:100%;padding:10px;margin-bottom:16px;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--text-color);font-size:14px;box-sizing:border-box;">
        <option value="60"><?php echo my_htmlspecialchars($translations['otp_ttl_1min']??'1 minute'); ?></option>
        <option value="180"><?php echo my_htmlspecialchars($translations['otp_ttl_3min']??'3 minutes'); ?></option>
        <option value="300" selected><?php echo my_htmlspecialchars($translations['otp_ttl_5min']??'5 minutes (default)'); ?></option>
        <option value="600"><?php echo my_htmlspecialchars($translations['otp_ttl_10min']??'10 minutes'); ?></option>
    </select>

    <!-- Warning -->
    <div style="font-size:12px;color:#ff9800;background:rgba(255,152,0,.1);border:1px solid rgba(255,152,0,.35);border-radius:6px;padding:9px 12px;margin-bottom:18px;line-height:1.5;">
        &#9888; <?php echo my_htmlspecialchars($translations['otp_warning_3attempts']??'Recipient has only 3 attempts to enter the code. After 3 failures, their IP is blocked for 3 minutes.'); ?>
    </div>

    <!-- Generate button -->
    <button onclick="doGenerateOtp()" id="otp-gen-btn" style="width:100%;padding:12px;background:var(--primary-color);color:#fff;border:none;border-radius:24px;font-size:15px;font-weight:600;cursor:pointer;margin-bottom:16px;transition:opacity .2s;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px;"><path d="M12 1C8.676 1 6 3.676 6 7v1H4a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1h-2V7c0-3.324-2.676-6-6-6zm0 2c2.276 0 4 1.724 4 4v1H8V7c0-2.276 1.724-4 4-4zm0 9a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/></svg>
        <?php echo my_htmlspecialchars($translations['otp_generate']??'Generate PIN'); ?>
    </button>

    <!-- Result box (hidden until generated) -->
    <div id="otp-gen-result" style="display:none;border:1px solid var(--border-color);border-radius:8px;padding:18px;background:var(--group-block-bg);">
        <div style="font-size:12px;opacity:.55;margin-bottom:8px;"><?php echo my_htmlspecialchars($translations['otp_your_pin']??'Your one-time PIN:'); ?></div>
        <div id="otp-gen-pin-display" style="font-size:46px;font-weight:900;letter-spacing:10px;color:var(--primary-color);margin-bottom:6px;font-family:monospace;">------</div>
        <div id="otp-gen-expiry" style="font-size:12px;opacity:.45;margin-bottom:14px;"></div>

        <div style="font-size:12px;opacity:.55;margin-bottom:6px;"><?php echo my_htmlspecialchars($translations['otp_link_hint']??'Send the recipient this link:'); ?></div>
        <div id="otp-gen-link-box" style="font-family:monospace;font-size:13px;background:var(--modal-bg);border:1px solid var(--border-color);padding:8px 10px;border-radius:6px;word-break:break-all;margin-bottom:12px;text-align:left;"></div>

        <button onclick="copyOtpLink()" style="padding:8px 20px;background:var(--primary-color);color:#fff;border:none;border-radius:20px;cursor:pointer;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
            <?php echo my_htmlspecialchars($translations['copy_link']??'Copy Link'); ?>
        </button>
    </div>

    <div id="otp-gen-error" style="color:#ff4444;font-size:13px;margin-top:12px;min-height:18px;"></div>
  </div>
</div>

<script>
function openOtpGeneratorModal(){
    var m=document.getElementById('otp-gen-modal');
    if(!m)return;
    m.style.display='flex';
    document.getElementById('otp-gen-result').style.display='none';
    document.getElementById('otp-gen-error').textContent='';
    document.getElementById('otp-gen-pin-display').textContent='------';
}
function closeOtpGeneratorModal(){
    var m=document.getElementById('otp-gen-modal');
    if(m)m.style.display='none';
}
var _otpGenModal=document.getElementById('otp-gen-modal');
if(_otpGenModal){_otpGenModal.addEventListener('click',function(e){if(e.target===this)closeOtpGeneratorModal();});}
function doGenerateOtp(){
    var ttl=document.getElementById('otp-ttl-select').value;
    var errEl=document.getElementById('otp-gen-error');
    var btn=document.getElementById('otp-gen-btn');
    errEl.textContent='';
    btn.disabled=true;
    btn.style.opacity='.6';
    var fd=new FormData();
    fd.append('generate_otp','1');
    fd.append('ajax','1');
    fd.append('ttl_seconds',ttl);
    fetch(window.location.pathname,{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(data){
            btn.disabled=false;btn.style.opacity='1';
            if(data.success){
                document.getElementById('otp-gen-result').style.display='block';
                document.getElementById('otp-gen-pin-display').textContent=data.pin;
                var mins=Math.round(data.expires_in/60);
                document.getElementById('otp-gen-expiry').textContent=
                    <?php echo json_encode($translations['otp_expires_in']??'Expires in'); ?> + ' ' + mins + ' ' + <?php echo json_encode($translations['otp_minutes']??'min'); ?>;
                var link=location.origin+'/one-time-pin';
                document.getElementById('otp-gen-link-box').textContent=link;
            }else{
                errEl.textContent=data.error||<?php echo json_encode($translations['fm_network_error']??'Error'); ?>;
            }
        })
        .catch(function(){
            btn.disabled=false;btn.style.opacity='1';
            errEl.textContent=<?php echo json_encode($translations['fm_network_error']??'Network error'); ?>;
        });
}
function copyOtpLink(){
    var link=document.getElementById('otp-gen-link-box').textContent;
    if(!link)return;
    navigator.clipboard.writeText(link).then(function(){
        if(typeof showTempNotification==='function')
            showTempNotification(<?php echo json_encode($translations['fm_copied']??'Copied!'); ?>,'success');
    }).catch(function(){
        // Fallback for older browsers
        var ta=document.createElement('textarea');
        ta.value=link;ta.style.position='fixed';ta.style.opacity='0';
        document.body.appendChild(ta);ta.select();
        try{document.execCommand('copy');}catch(e){}
        document.body.removeChild(ta);
        if(typeof showTempNotification==='function')
            showTempNotification(<?php echo json_encode($translations['fm_copied']??'Copied!'); ?>,'success');
    });
}

// Support Chat functionality
(function() {
    const chatModal = document.getElementById('support-chat-modal');
    const chatBtn = document.getElementById('support-chat-btn');
    const closeChatBtn = document.getElementById('close-chat-modal');
    const chatInput = document.getElementById('chat-input');
    const chatSendBtn = document.getElementById('chat-send-btn');
    const chatMessages = document.getElementById('chat-messages');
    const chatUploadBtn = document.getElementById('chat-upload-btn');
    const chatFileInput = document.getElementById('chat-file-input');
    const chatMessagesArea = document.getElementById('chat-messages-area');
    const adminChatList = document.getElementById('admin-chat-list');
    const chatListContainer = document.getElementById('chat-list-container');
    const chatModalTitle = document.getElementById('chat-modal-title');
    const contextMenu = document.getElementById('chat-context-menu');
    const contextEdit = document.getElementById('context-edit');
    const contextDelete = document.getElementById('context-delete');
    const deleteChatBtn = document.getElementById('delete-chat-btn');
    const newChatBtn = document.getElementById('new-chat-btn');
    const supportChatDeleteModal = document.getElementById('support-chat-delete-modal');
    const supportChatDeleteEmail = document.getElementById('support-chat-delete-email');
    const supportChatDeleteCard = document.getElementById('support-chat-delete-card');
    const supportChatDeleteCancel = document.getElementById('support-chat-delete-cancel');
    const supportChatDeleteConfirm = document.getElementById('support-chat-delete-confirm');
    let chatSendBtnHtml = '';
    if (chatSendBtn) {
        chatSendBtnHtml = chatSendBtn.innerHTML;
    }
    if (!chatModal) {
        return;
    }

    let currentChatUser = null;
    let isAdmin = <?php echo json_encode(isset($_SESSION['user']) && (int)($_SESSION['user']['role'] ?? 0) >= 2); ?>;
    let currentUserEmail = <?php echo json_encode($_SESSION['user']['email'] ?? ''); ?>;
    let chatPollInterval = null;
    let lastMessagesHash = '';
    let editingMessageId = null;
    let contextMessageId = null;
    let messagesData = [];
    let lastAdminChatList = [];
    let chatOldestId = null;
    let chatMaxId = 0;
    let chatHasMoreOlder = false;
    let chatLoadingOlder = false;
    let chatScrollWired = false;
    let chatLightboxWired = false;
    let chatNavKeysWired = false;
    const adminDraftEdits = {};
    const userSearchModal = document.getElementById('chat-user-search-modal');
    const userSearchInput = document.getElementById('chat-user-search-input');
    const userSearchResults = document.getElementById('chat-user-search-results');
    const userSearchCancel = document.getElementById('chat-user-search-cancel');
    function chatAlert(msg) {
        fmAlert(msg);
    }
    function escChat(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    function escAttrChat(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;');
    }
    const supportChatLightbox = document.getElementById('support-chat-lightbox');
    const supportChatLightboxInner = document.getElementById('support-chat-lightbox-inner');
    const supportChatLightboxClose = document.getElementById('support-chat-lightbox-close');
    const chatCancelEditBtn = document.getElementById('chat-cancel-edit-btn');
    const chatAdminMobileBack = document.getElementById('chat-admin-mobile-back');
    const adminChatListCloseBtn = document.getElementById('admin-chat-list-close');
    let adminMobilePhase = 'chat';
    let chatMediaEditorOpen = false;
    function isAdminMobileMatch() {
        return isAdmin && typeof window.matchMedia === 'function' && window.matchMedia('(max-width:768px)').matches;
    }
    function syncAdminMobileLayout() {
        var cont = document.getElementById('chat-modal-container');
        if (!cont || !chatModal) {
            return;
        }
        if (!isAdmin || !isAdminMobileMatch()) {
            cont.classList.remove('support-chat-admin-mobile', 'chat-mobile-phase-list', 'chat-mobile-phase-chat');
            return;
        }
        cont.classList.add('support-chat-admin-mobile');
        if (adminMobilePhase === 'list') {
            cont.classList.remove('chat-mobile-phase-chat');
            cont.classList.add('chat-mobile-phase-list');
        } else {
            cont.classList.remove('chat-mobile-phase-list');
            cont.classList.add('chat-mobile-phase-chat');
        }
    }
    function openChatMediaEditor(file, onDone) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            onDone(file);
            return;
        }
        var modal = document.getElementById('chat-media-editor-modal');
        var base = document.getElementById('chat-media-editor-base');
        var layer = document.getElementById('chat-media-editor-layer');
        var btnCrop = document.getElementById('chat-media-tool-crop');
        var btnArrow = document.getElementById('chat-media-tool-arrow');
        var btnDraw = document.getElementById('chat-media-tool-draw');
        var btnClear = document.getElementById('chat-media-tool-clear');
        var btnApplyCrop = document.getElementById('chat-media-apply-crop');
        var btnCancel = document.getElementById('chat-media-editor-cancel');
        var btnSend = document.getElementById('chat-media-editor-send');
        if (!modal || !base || !layer || !btnApplyCrop) {
            onDone(file);
            return;
        }
        var bctx = base.getContext('2d');
        var lctx = layer.getContext('2d');
        var ann = [];
        var cropMode = false;
        var crop = { x: 0, y: 0, w: 0, h: 0 };
        var tool = 'draw';
        var drag = null;
        var tmpArrow = null;
        var img = new Image();
        var blobUrl = URL.createObjectURL(file);
        function cleanup() {
            chatMediaEditorOpen = false;
            modal.style.display = 'none';
            document.body.style.overflow = '';
            URL.revokeObjectURL(blobUrl);
            img.onload = null;
            img.onerror = null;
            img.src = '';
            layer.removeEventListener('pointerdown', onPtrDown);
            layer.removeEventListener('pointermove', onPtrMove);
            layer.removeEventListener('pointerup', onPtrUp);
            layer.removeEventListener('pointercancel', onPtrUp);
        }
        function setToolActive() {
            [btnCrop, btnArrow, btnDraw].forEach(function(b) {
                if (b) {
                    b.classList.remove('chat-media-tool-active');
                }
            });
            if (cropMode && btnCrop) {
                btnCrop.classList.add('chat-media-tool-active');
            } else if (tool === 'arrow' && btnArrow) {
                btnArrow.classList.add('chat-media-tool-active');
            } else if (btnDraw) {
                btnDraw.classList.add('chat-media-tool-active');
            }
        }
        function drawCropChrome() {
            var cw = layer.width;
            var ch = layer.height;
            lctx.clearRect(0, 0, cw, ch);
            lctx.fillStyle = 'rgba(0,0,0,0.55)';
            lctx.fillRect(0, 0, cw, ch);
            lctx.globalCompositeOperation = 'destination-out';
            lctx.fillRect(crop.x, crop.y, crop.w, crop.h);
            lctx.globalCompositeOperation = 'source-over';
            lctx.strokeStyle = '#fff';
            lctx.lineWidth = 2;
            lctx.strokeRect(crop.x, crop.y, crop.w, crop.h);
            var H = 14;
            [[crop.x, crop.y], [crop.x + crop.w, crop.y], [crop.x, crop.y + crop.h], [crop.x + crop.w, crop.y + crop.h]].forEach(function(pt) {
                lctx.fillStyle = '#fff';
                lctx.fillRect(pt[0] - H / 2, pt[1] - H / 2, H, H);
            });
        }
        function drawAnn() {
            if (cropMode) {
                drawCropChrome();
                return;
            }
            var cw = layer.width;
            var ch = layer.height;
            lctx.clearRect(0, 0, cw, ch);
            lctx.lineCap = 'round';
            lctx.lineJoin = 'round';
            ann.forEach(function(p) {
                if (p.t === 'arrow') {
                    lctx.strokeStyle = '#ff3b30';
                    lctx.lineWidth = 4;
                    lctx.beginPath();
                    lctx.moveTo(p.x1, p.y1);
                    lctx.lineTo(p.x2, p.y2);
                    lctx.stroke();
                    var ang = Math.atan2(p.y2 - p.y1, p.x2 - p.x1);
                    var L = 16;
                    lctx.fillStyle = '#ff3b30';
                    lctx.beginPath();
                    lctx.moveTo(p.x2, p.y2);
                    lctx.lineTo(p.x2 - L * Math.cos(ang - Math.PI / 6), p.y2 - L * Math.sin(ang - Math.PI / 6));
                    lctx.lineTo(p.x2 - L * Math.cos(ang + Math.PI / 6), p.y2 - L * Math.sin(ang + Math.PI / 6));
                    lctx.closePath();
                    lctx.fill();
                } else if (p.t === 'path' && p.pts && p.pts.length > 1) {
                    lctx.strokeStyle = '#ffcc00';
                    lctx.lineWidth = 4;
                    lctx.beginPath();
                    lctx.moveTo(p.pts[0][0], p.pts[0][1]);
                    for (var i = 1; i < p.pts.length; i++) {
                        lctx.lineTo(p.pts[i][0], p.pts[i][1]);
                    }
                    lctx.stroke();
                }
            });
            if (tmpArrow) {
                lctx.strokeStyle = '#ff3b30';
                lctx.lineWidth = 4;
                lctx.setLineDash([6, 4]);
                lctx.beginPath();
                lctx.moveTo(tmpArrow.x1, tmpArrow.y1);
                lctx.lineTo(tmpArrow.x2, tmpArrow.y2);
                lctx.stroke();
                lctx.setLineDash([]);
            }
            if (drag && drag.k === 'draw' && drag.pts && drag.pts.length > 1) {
                lctx.strokeStyle = '#ffcc00';
                lctx.lineWidth = 4;
                lctx.lineCap = 'round';
                lctx.beginPath();
                lctx.moveTo(drag.pts[0][0], drag.pts[0][1]);
                for (var j = 1; j < drag.pts.length; j++) {
                    lctx.lineTo(drag.pts[j][0], drag.pts[j][1]);
                }
                lctx.stroke();
            }
        }
        function clampCrop() {
            var cw = base.width;
            var ch = base.height;
            crop.w = Math.max(24, Math.min(crop.w, cw));
            crop.h = Math.max(24, Math.min(crop.h, ch));
            crop.x = Math.max(0, Math.min(crop.x, cw - crop.w));
            crop.y = Math.max(0, Math.min(crop.y, ch - crop.h));
        }
        function hitCrop(mx, my) {
            var H = 18;
            if (Math.abs(mx - crop.x) <= H && Math.abs(my - crop.y) <= H) {
                return 'nw';
            }
            if (Math.abs(mx - (crop.x + crop.w)) <= H && Math.abs(my - crop.y) <= H) {
                return 'ne';
            }
            if (Math.abs(mx - crop.x) <= H && Math.abs(my - (crop.y + crop.h)) <= H) {
                return 'sw';
            }
            if (Math.abs(mx - (crop.x + crop.w)) <= H && Math.abs(my - (crop.y + crop.h)) <= H) {
                return 'se';
            }
            if (mx > crop.x && mx < crop.x + crop.w && my > crop.y && my < crop.y + crop.h) {
                return 'move';
            }
            return null;
        }
        function clientToCanvas(e) {
            var r = layer.getBoundingClientRect();
            var sx = layer.width / r.width;
            var sy = layer.height / r.height;
            return { x: (e.clientX - r.left) * sx, y: (e.clientY - r.top) * sy };
        }
        function applyCrop() {
            if (crop.w < 16 || crop.h < 16) {
                return;
            }
            var tmp = document.createElement('canvas');
            tmp.width = crop.w;
            tmp.height = crop.h;
            tmp.getContext('2d').drawImage(base, crop.x, crop.y, crop.w, crop.h, 0, 0, crop.w, crop.h);
            base.width = crop.w;
            base.height = crop.h;
            bctx.drawImage(tmp, 0, 0);
            layer.width = crop.w;
            layer.height = crop.h;
            ann = [];
            cropMode = false;
            btnApplyCrop.style.display = 'none';
            var pad = Math.round(Math.min(base.width, base.height) * 0.06);
            crop = { x: pad, y: pad, w: base.width - 2 * pad, h: base.height - 2 * pad };
            drawAnn();
            setToolActive();
        }
        function onPtrDown(e) {
            if (e.button !== 0) {
                return;
            }
            e.preventDefault();
            var p = clientToCanvas(e);
            if (cropMode) {
                var h = hitCrop(p.x, p.y);
                if (!h) {
                    return;
                }
                drag = { k: 'crop', h: h, sx: p.x, sy: p.y, o: { x: crop.x, y: crop.y, w: crop.w, h: crop.h } };
                layer.setPointerCapture(e.pointerId);
                return;
            }
            if (tool === 'arrow') {
                tmpArrow = { x1: p.x, y1: p.y, x2: p.x, y2: p.y };
                layer.setPointerCapture(e.pointerId);
                return;
            }
            if (tool === 'draw') {
                drag = { k: 'draw', pts: [[p.x, p.y]] };
                layer.setPointerCapture(e.pointerId);
            }
        }
        function onPtrMove(e) {
            if (!drag && !tmpArrow) {
                return;
            }
            e.preventDefault();
            var p = clientToCanvas(e);
            if (drag && drag.k === 'crop') {
                var dx = p.x - drag.sx;
                var dy = p.y - drag.sy;
                var o = drag.o;
                if (drag.h === 'move') {
                    crop.x = o.x + dx;
                    crop.y = o.y + dy;
                } else if (drag.h === 'se') {
                    crop.w = o.w + dx;
                    crop.h = o.h + dy;
                } else if (drag.h === 'nw') {
                    crop.x = o.x + dx;
                    crop.y = o.y + dy;
                    crop.w = o.w - dx;
                    crop.h = o.h - dy;
                } else if (drag.h === 'ne') {
                    crop.y = o.y + dy;
                    crop.w = o.w + dx;
                    crop.h = o.h - dy;
                } else if (drag.h === 'sw') {
                    crop.x = o.x + dx;
                    crop.w = o.w - dx;
                    crop.h = o.h + dy;
                }
                clampCrop();
                drawAnn();
                return;
            }
            if (tmpArrow) {
                tmpArrow.x2 = p.x;
                tmpArrow.y2 = p.y;
                drawAnn();
                return;
            }
            if (drag && drag.k === 'draw') {
                drag.pts.push([p.x, p.y]);
                drawAnn();
            }
        }
        function onPtrUp(e) {
            if (drag && drag.k === 'crop') {
                drag = null;
                try {
                    layer.releasePointerCapture(e.pointerId);
                } catch (er) {}
                return;
            }
            if (tmpArrow) {
                var p = clientToCanvas(e);
                var d = Math.hypot(p.x - tmpArrow.x1, p.y - tmpArrow.y1);
                if (d > 6) {
                    ann.push({ t: 'arrow', x1: tmpArrow.x1, y1: tmpArrow.y1, x2: p.x, y2: p.y });
                }
                tmpArrow = null;
                drawAnn();
                try {
                    layer.releasePointerCapture(e.pointerId);
                } catch (er2) {}
                return;
            }
            if (drag && drag.k === 'draw') {
                if (drag.pts.length > 1) {
                    ann.push({ t: 'path', pts: drag.pts.slice() });
                }
                drag = null;
                drawAnn();
                try {
                    layer.releasePointerCapture(e.pointerId);
                } catch (er3) {}
            }
        }
        chatMediaEditorOpen = true;
        modal.style.display = 'flex';
        modal.style.flexDirection = 'column';
        modal.style.alignItems = 'stretch';
        document.body.style.overflow = 'hidden';
        img.onload = function() {
            var maxW = Math.min(window.innerWidth * 0.92, img.naturalWidth);
            var maxH = Math.min(window.innerHeight * 0.56, img.naturalHeight);
            var ratio = Math.min(maxW / img.naturalWidth, maxH / img.naturalHeight, 1);
            var dw = Math.max(1, Math.round(img.naturalWidth * ratio));
            var dh = Math.max(1, Math.round(img.naturalHeight * ratio));
            base.width = dw;
            base.height = dh;
            layer.width = dw;
            layer.height = dh;
            bctx.drawImage(img, 0, 0, dw, dh);
            var pad = Math.round(Math.min(dw, dh) * 0.06);
            crop = { x: pad, y: pad, w: dw - 2 * pad, h: dh - 2 * pad };
            ann = [];
            cropMode = false;
            tool = 'draw';
            btnApplyCrop.style.display = 'none';
            drawAnn();
            setToolActive();
            layer.addEventListener('pointerdown', onPtrDown);
            layer.addEventListener('pointermove', onPtrMove);
            layer.addEventListener('pointerup', onPtrUp);
            layer.addEventListener('pointercancel', onPtrUp);
        };
        img.onerror = function() {
            chatAlert(T.support_chat_unsupported_type || 'Invalid image');
            cleanup();
            onDone(file);
        };
        img.src = blobUrl;
        if (btnCrop) {
            btnCrop.onclick = function() {
                cropMode = !cropMode;
                if (cropMode) {
                    ann = [];
                    btnApplyCrop.style.display = 'inline-block';
                } else {
                    btnApplyCrop.style.display = 'none';
                }
                drawAnn();
                setToolActive();
            };
        }
        if (btnArrow) {
            btnArrow.onclick = function() {
                cropMode = false;
                btnApplyCrop.style.display = 'none';
                tool = 'arrow';
                drawAnn();
                setToolActive();
            };
        }
        if (btnDraw) {
            btnDraw.onclick = function() {
                cropMode = false;
                btnApplyCrop.style.display = 'none';
                tool = 'draw';
                drawAnn();
                setToolActive();
            };
        }
        if (btnClear) {
            btnClear.onclick = function() {
                if (!cropMode) {
                    ann = [];
                }
                tmpArrow = null;
                drawAnn();
            };
        }
        btnApplyCrop.onclick = function() {
            applyCrop();
        };
        if (btnCancel) {
            btnCancel.onclick = function() {
                cleanup();
                onDone(null);
            };
        }
        if (btnSend) {
            btnSend.onclick = function() {
                if (cropMode) {
                    chatAlert(T.support_chat_editor_finish_crop || 'Apply or cancel crop first');
                    return;
                }
                var out = document.createElement('canvas');
                out.width = base.width;
                out.height = base.height;
                var ox = out.getContext('2d');
                ox.drawImage(base, 0, 0);
                ox.drawImage(layer, 0, 0);
                out.toBlob(function(blob) {
                    cleanup();
                    if (!blob) {
                        onDone(file);
                        return;
                    }
                    var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    onDone(new File([blob], name, { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.92);
            };
        }
    }
    let lightboxZoomState = null;
    function openSupportChatLightbox(url, mediaType) {
        if (!supportChatLightbox || !supportChatLightboxInner || !url) {
            return;
        }
        supportChatLightboxInner.innerHTML = '';
        var mt = mediaType === 'video' ? 'video' : 'image';
        if (mt === 'video') {
            var v = document.createElement('video');
            v.setAttribute('controls', '');
            v.setAttribute('playsinline', '');
            v.src = url;
            supportChatLightboxInner.appendChild(v);
        } else {
            var wrapper = document.createElement('div');
            wrapper.style.cssText = 'position:relative;max-width:96vw;max-height:86vh;overflow:hidden;';
            var im = document.createElement('img');
            im.src = url;
            im.alt = '';
            im.style.cssText = 'max-width:100%;max-height:86vh;width:auto;height:auto;object-fit:contain;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.5);cursor:default;transform-origin:center center;user-select:none;-webkit-user-select:none;';
            wrapper.appendChild(im);

            var minimap = document.createElement('div');
            minimap.style.cssText = 'display:none;position:absolute;bottom:16px;right:16px;width:120px;height:120px;border:2px solid rgba(255,255,255,0.5);border-radius:8px;overflow:hidden;background:rgba(0,0,0,0.7);box-shadow:0 4px 12px rgba(0,0,0,0.6);';
            var minimapImg = document.createElement('img');
            minimapImg.src = url;
            minimapImg.style.cssText = 'width:100%;height:100%;object-fit:contain;opacity:0.6;';
            minimap.appendChild(minimapImg);

            var minimapViewport = document.createElement('div');
            minimapViewport.style.cssText = 'position:absolute;border:2px solid rgba(255,255,255,0.9);background:rgba(255,255,255,0.2);pointer-events:none;';
            minimap.appendChild(minimapViewport);
            wrapper.appendChild(minimap);

            lightboxZoomState = {
                scale: 1,
                translateX: 0,
                translateY: 0,
                isDragging: false,
                startX: 0,
                startY: 0,
                img: im,
                wrapper: wrapper,
                minimap: minimap,
                minimapViewport: minimapViewport
            };

            im.addEventListener('wheel', function(e) {
                e.preventDefault();
                var delta = e.deltaY > 0 ? -0.3 : 0.3;
                lightboxZoomState.scale = Math.max(1, Math.min(5, lightboxZoomState.scale + delta));
                if (lightboxZoomState.scale === 1) {
                    lightboxZoomState.translateX = 0;
                    lightboxZoomState.translateY = 0;
                    im.style.cursor = 'default';
                    minimap.style.display = 'none';
                } else {
                    im.style.cursor = 'grab';
                    minimap.style.display = 'block';
                }
                updateImageTransform();
                updateMinimapViewport();
            }, {passive: false});

            im.addEventListener('mousedown', function(e) {
                if (lightboxZoomState.scale > 1) {
                    e.preventDefault();
                    lightboxZoomState.isDragging = true;
                    lightboxZoomState.startX = e.clientX - lightboxZoomState.translateX;
                    lightboxZoomState.startY = e.clientY - lightboxZoomState.translateY;
                    im.style.cursor = 'grabbing';
                }
            });

            document.addEventListener('mousemove', function(e) {
                if (lightboxZoomState && lightboxZoomState.isDragging) {
                    lightboxZoomState.translateX = e.clientX - lightboxZoomState.startX;
                    lightboxZoomState.translateY = e.clientY - lightboxZoomState.startY;
                    updateImageTransform();
                    updateMinimapViewport();
                }
            });

            document.addEventListener('mouseup', function() {
                if (lightboxZoomState && lightboxZoomState.isDragging) {
                    lightboxZoomState.isDragging = false;
                    if (lightboxZoomState.img && lightboxZoomState.scale > 1) {
                        lightboxZoomState.img.style.cursor = 'grab';
                    }
                }
            });

            document.addEventListener('keydown', function(e) {
                if (!lightboxZoomState || lightboxZoomState.scale <= 1 || supportChatLightbox.style.display !== 'flex') {
                    return;
                }
                var step = 30;
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    lightboxZoomState.translateX += step;
                    updateImageTransform();
                    updateMinimapViewport();
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    lightboxZoomState.translateX -= step;
                    updateImageTransform();
                    updateMinimapViewport();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    lightboxZoomState.translateY += step;
                    updateImageTransform();
                    updateMinimapViewport();
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    lightboxZoomState.translateY -= step;
                    updateImageTransform();
                    updateMinimapViewport();
                }
            });

            supportChatLightboxInner.appendChild(wrapper);
        }
        supportChatLightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function updateImageTransform() {
        if (lightboxZoomState && lightboxZoomState.img) {
            lightboxZoomState.img.style.transform = 'scale(' + lightboxZoomState.scale + ') translate(' + (lightboxZoomState.translateX / lightboxZoomState.scale) + 'px, ' + (lightboxZoomState.translateY / lightboxZoomState.scale) + 'px)';
        }
    }

    function updateMinimapViewport() {
        if (!lightboxZoomState || !lightboxZoomState.minimapViewport) return;
        var viewportWidth = 100 / lightboxZoomState.scale;
        var viewportHeight = 100 / lightboxZoomState.scale;
        var viewportX = 50 - (lightboxZoomState.translateX / lightboxZoomState.scale / lightboxZoomState.img.offsetWidth * 100) - viewportWidth / 2;
        var viewportY = 50 - (lightboxZoomState.translateY / lightboxZoomState.scale / lightboxZoomState.img.offsetHeight * 100) - viewportHeight / 2;
        lightboxZoomState.minimapViewport.style.width = viewportWidth + '%';
        lightboxZoomState.minimapViewport.style.height = viewportHeight + '%';
        lightboxZoomState.minimapViewport.style.left = viewportX + '%';
        lightboxZoomState.minimapViewport.style.top = viewportY + '%';
    }
    function closeSupportChatLightbox() {
        if (!supportChatLightbox) {
            return;
        }
        supportChatLightbox.style.display = 'none';
        if (supportChatLightboxInner) {
            supportChatLightboxInner.innerHTML = '';
        }
        lightboxZoomState = null;
        document.body.style.overflow = '';
    }
    function clearAdminEditDraftForCurrentChat() {
        if (isAdmin && currentChatUser && adminDraftEdits[currentChatUser]) {
            delete adminDraftEdits[currentChatUser];
        }
    }
    function cancelEditUI() {
        editingMessageId = null;
        if (chatInput) {
            chatInput.value = '';
        }
        if (chatFileInput) {
            chatFileInput.value = '';
        }
        var preview = document.getElementById('edit-media-preview');
        if (preview) {
            preview.remove();
        }
        if (chatCancelEditBtn) {
            chatCancelEditBtn.style.display = 'none';
        }
        if (chatSendBtn) {
            chatSendBtn.innerHTML = chatSendBtnHtml;
            chatSendBtn.style.width = '';
            chatSendBtn.style.minWidth = '';
            chatSendBtn.style.height = '';
            chatSendBtn.style.padding = '';
            chatSendBtn.style.borderRadius = '';
            chatSendBtn.style.fontSize = '';
            chatSendBtn.style.fontWeight = '';
            chatSendBtn.style.background = 'linear-gradient(135deg, var(--chat-color) 0%, var(--chat-color)dd 100%)';
            chatSendBtn.style.boxShadow = '0 4px 12px var(--chat-color-shadow)';
        }
        if (chatUploadBtn) {
            chatUploadBtn.style.display = 'flex';
        }
    }
    function wireChatLightbox() {
        if (!chatMessages || chatLightboxWired) {
            return;
        }
        chatLightboxWired = true;
        chatMessages.addEventListener('click', function(e) {
            var img = e.target.closest('img.chat-msg-media-open');
            if (img) {
                openSupportChatLightbox(img.getAttribute('data-full-src') || img.src, 'image');
                e.preventDefault();
            }
        });
    }
    function wireChatNavKeys() {
        if (!chatModal || chatNavKeysWired) {
            return;
        }
        chatNavKeysWired = true;
        chatModal.addEventListener('keydown', function(e) {
            if (chatModal.style.display === 'none') {
                return;
            }
            var lbOpen = supportChatLightbox && supportChatLightbox.style.display === 'flex';
            if (e.key === 'Escape') {
                if (chatMediaEditorOpen) {
                    var cbtn = document.getElementById('chat-media-editor-cancel');
                    if (cbtn) {
                        cbtn.click();
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                if (lbOpen) {
                    closeSupportChatLightbox();
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                if (editingMessageId) {
                    clearAdminEditDraftForCurrentChat();
                    cancelEditUI();
                    e.preventDefault();
                    e.stopPropagation();
                }
                return;
            }
            if (lbOpen) {
                return;
            }
            var ae = document.activeElement;
            var inComposer = ae === chatInput || (ae && ae.getAttribute && ae.getAttribute('id') === 'chat-user-search-input');
            if (inComposer) {
                return;
            }
            if (ae && (ae.tagName === 'INPUT' || ae.tagName === 'TEXTAREA' || ae.tagName === 'SELECT')) {
                return;
            }
            if (e.key === 'ArrowUp' && chatMessages) {
                chatMessages.scrollTop -= 88;
                e.preventDefault();
                return;
            }
            if (e.key === 'ArrowDown' && chatMessages) {
                chatMessages.scrollTop += 88;
                e.preventDefault();
                return;
            }
            if (!isAdmin || !lastAdminChatList.length || !currentChatUser) {
                return;
            }
            var emails = lastAdminChatList.map(function(c) {
                return c.user_email;
            });
            var idx = emails.indexOf(currentChatUser);
            if (idx < 0) {
                return;
            }
            if (e.key === 'ArrowRight' && idx < emails.length - 1) {
                window.selectChat(emails[idx + 1]);
                e.preventDefault();
            } else if (e.key === 'ArrowLeft' && idx > 0) {
                window.selectChat(emails[idx - 1]);
                e.preventDefault();
            }
        }, true);
    }
    wireChatNavKeys();
    if (supportChatLightbox) {
        supportChatLightbox.addEventListener('click', function(e) {
            if (e.target === supportChatLightbox) {
                closeSupportChatLightbox();
            }
        });
    }
    if (supportChatLightboxClose) {
        supportChatLightboxClose.addEventListener('click', function(e) {
            e.stopPropagation();
            closeSupportChatLightbox();
        });
    }
    if (chatCancelEditBtn) {
        chatCancelEditBtn.addEventListener('click', function() {
            if (editingMessageId) {
                clearAdminEditDraftForCurrentChat();
                cancelEditUI();
            }
        });
    }
    function scrollChatToBottom(aggressive) {
        if (!chatMessages) {
            return;
        }
        const run = function() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        };
        run();
        requestAnimationFrame(function() {
            run();
            requestAnimationFrame(run);
        });
        if (aggressive) {
            [40, 120, 300].forEach(function(t) {
                setTimeout(run, t);
            });
            setTimeout(function() {
                chatMessages.querySelectorAll('img,video').forEach(function(el) {
                    el.addEventListener('load', run, { once: true });
                    el.addEventListener('loadeddata', run, { once: true });
                });
            }, 0);
        }
    }
    if (chatBtn) {
        chatBtn.addEventListener('click', function() {
            if (isAdmin) {
                // For admins, use hash-based navigation
                window.location.hash = '#support-chat';
            } else {
                chatModal.style.display = 'flex';
                currentChatUser = currentUserEmail;
                lastMessagesHash = '';
                messagesData = [];
                chatOldestId = null;
                chatMaxId = 0;
                chatHasMoreOlder = false;
                loadMessages(false);
                startPolling();
            }
        });
    }

    // Handle hash-based navigation for admin
    function handleHash() {
        const hash = window.location.hash;
        if (hash === '#support-chat' && isAdmin) {
            // Show fullscreen admin chat
            chatModal.style.display = 'flex';
            const container = document.getElementById('chat-modal-container');
            if (container) {
                container.style.width = '100%';
                container.style.height = '100%';
                container.style.maxWidth = 'none';
                container.style.maxHeight = 'none';
                container.style.minHeight = '0';
                container.style.borderRadius = '0';
                container.style.flexDirection = 'row';
            }
            if (adminChatList) {
                adminChatList.style.display = 'flex';
            }
            if (isAdminMobileMatch()) {
                adminMobilePhase = currentChatUser ? 'chat' : 'list';
            } else {
                adminMobilePhase = 'chat';
            }
            syncAdminMobileLayout();
            loadChatList();
        } else if (hash !== '#support-chat') {
            chatModal.style.display = 'none';
            stopPolling();
        }
    }

    // Listen for hash changes
    window.addEventListener('hashchange', handleHash);
    handleHash(); // Check on load

    if (closeChatBtn) {
        closeChatBtn.addEventListener('click', function() {
            if (isAdmin) {
                if (currentChatUser && editingMessageId) {
                    adminDraftEdits[currentChatUser] = { id: editingMessageId, text: chatInput ? chatInput.value : '' };
                    cancelEditUI();
                }
                window.location.hash = '';
            } else {
                chatModal.style.display = 'none';
                stopPolling();
            }
        });
    }

    if (chatModal) {
        chatModal.addEventListener('click', function(e) {
            if (e.target === chatModal) {
                if (isAdmin) {
                    if (currentChatUser && editingMessageId) {
                        adminDraftEdits[currentChatUser] = { id: editingMessageId, text: chatInput ? chatInput.value : '' };
                        cancelEditUI();
                    }
                    window.location.hash = '';
                } else {
                    chatModal.style.display = 'none';
                    stopPolling();
                }
            }
        });
    }

    if (chatSendBtn) {
        chatSendBtn.addEventListener('click', () => editingMessageId ? saveEdit() : sendMessage());
    }

    if (chatInput) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                editingMessageId ? saveEdit() : sendMessage();
            }
        });
    }

    if (chatUploadBtn) {
        chatUploadBtn.addEventListener('click', function() {
            chatFileInput.click();
        });
    }

    // Voice recording button
    const chatVoiceBtn = document.getElementById('chat-voice-btn');
    if (chatVoiceBtn) {
        let voicePressTimer = null;
        let isRecording = false;

        // Desktop and mobile: press and hold to record
        chatVoiceBtn.addEventListener('mousedown', function(e) {
            e.preventDefault();
            startVoiceRecording();
        });

        chatVoiceBtn.addEventListener('mouseup', function(e) {
            e.preventDefault();
            if (isRecording) {
                stopVoiceRecording();
            }
        });

        chatVoiceBtn.addEventListener('mouseleave', function(e) {
            if (isRecording) {
                cancelVoiceRecording();
            }
        });

        // Touch events for mobile
        chatVoiceBtn.addEventListener('touchstart', function(e) {
            e.preventDefault();
            startVoiceRecording();
        });

        chatVoiceBtn.addEventListener('touchend', function(e) {
            e.preventDefault();
            if (isRecording) {
                stopVoiceRecording();
            }
        });

        chatVoiceBtn.addEventListener('touchcancel', function(e) {
            if (isRecording) {
                cancelVoiceRecording();
            }
        });

        function resetVoiceRecordingUI() {
            chatVoiceBtn.style.background = 'linear-gradient(135deg, var(--chat-color-light) 0%, var(--chat-color-medium) 100%)';
            chatVoiceBtn.style.transform = 'scale(1)';
            chatVoiceBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>';
            if (chatInput) chatInput.placeholder = T.support_chat_placeholder || 'Type your message...';
        }
        function getMicErrorMessage(err) {
            const name = err && err.name ? err.name : '';
            if (!window.isSecureContext) return 'Voice recording requires HTTPS (or localhost).';
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError') return 'Microphone permission is blocked for this site. Allow microphone in browser settings and reload the page.';
            if (name === 'NotFoundError' || name === 'DevicesNotFoundError') return 'No microphone detected. Connect a microphone and try again.';
            if (name === 'NotReadableError' || name === 'TrackStartError') return 'Microphone is busy in another app or tab. Close other apps using mic and try again.';
            if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') return 'Microphone constraints are not supported on this device.';
            return 'Could not start voice recording. Please try again.';
        }
        function startVoiceRecording() {
            if (isRecording) return;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                if (typeof showTempNotification === 'function') {
                    showTempNotification('Your browser does not support audio recording', 'error');
                } else {
                    alert(T.browser_no_audio_recording);
                }
                return;
            }

            const preferredMime = typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : '';
            const recorderOpts = preferredMime ? { mimeType: preferredMime } : undefined;
            navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } })
                .then(function(stream) {
                    isRecording = true;
                    mediaRecorder = recorderOpts ? new MediaRecorder(stream, recorderOpts) : new MediaRecorder(stream);
                    audioChunks = [];

                    mediaRecorder.ondataavailable = function(e) {
                        audioChunks.push(e.data);
                    };

                    mediaRecorder.onstop = function() {
                        stream.getTracks().forEach(track => track.stop());
                        if (animationFrame) cancelAnimationFrame(animationFrame);
                        if (audioContext) audioContext.close();
                    };

                    mediaRecorder.start();
                    recordingStartTime = Date.now();

                    // Visual feedback
                    chatVoiceBtn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                    chatVoiceBtn.style.transform = 'scale(1.1)';
                    chatVoiceBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" stroke="#fff" fill="#ef4444"/></svg>';

                    // Show recording indicator
                    if (chatInput) {
                        chatInput.placeholder = T.recording_release_to_send;
                    }

                    // Visualize audio (optional - can add waveform to button)
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    analyser = audioContext.createAnalyser();
                    const source = audioContext.createMediaStreamSource(stream);
                    source.connect(analyser);
                    analyser.fftSize = 256;
                })
                .catch(function(err) {
                    console.error('Microphone access denied:', err);
                    const msg = getMicErrorMessage(err);
                    if (typeof showTempNotification === 'function') {
                        showTempNotification(msg, 'error');
                    } else {
                        alert(msg);
                    }
                    isRecording = false;
                });
        }

        function stopVoiceRecording() {
            if (!isRecording || !mediaRecorder) return;

            mediaRecorder.stop();
            isRecording = false;

            resetVoiceRecordingUI();
            // Send voice message
            setTimeout(function() {
                if (audioChunks.length > 0) {
                    const recorderMime = mediaRecorder && mediaRecorder.mimeType ? mediaRecorder.mimeType : 'audio/webm';
                    const uploadMime = recorderMime.split(';')[0] || 'audio/webm';
                    const audioBlob = new Blob(audioChunks, { type: uploadMime });
                    const audioFile = new File([audioBlob], 'voice-' + Date.now() + '.weba', { type: uploadMime });
                    sendMessage(audioFile);
                    audioChunks = [];
                }
            }, 100);
        }

        function cancelVoiceRecording() {
            if (!isRecording || !mediaRecorder) return;

            mediaRecorder.stop();
            isRecording = false;
            audioChunks = [];

            resetVoiceRecordingUI();
        }
    }

    // Text formatting context menu
    const chatFormatMenu = document.getElementById('chat-format-menu');
    if (chatInput && chatFormatMenu) {
        let formatMenuTimeout;
        let touchStartTime;
        let touchStartPos;

        // Desktop: right-click
        chatInput.addEventListener('contextmenu', function(e) {
            if (this.selectionStart !== this.selectionEnd) {
                e.preventDefault();
                showFormatMenu(e.pageX, e.pageY);
            }
        });

        // Mobile: long press
        chatInput.addEventListener('touchstart', function(e) {
            touchStartTime = Date.now();
            touchStartPos = { x: e.touches[0].clientX, y: e.touches[0].clientY };
            formatMenuTimeout = setTimeout(function() {
                if (chatInput.selectionStart !== chatInput.selectionEnd) {
                    const touch = e.touches[0];
                    showFormatMenu(touch.pageX, touch.pageY);
                }
            }, 500);
        });

        chatInput.addEventListener('touchmove', function(e) {
            if (touchStartPos) {
                const dx = e.touches[0].clientX - touchStartPos.x;
                const dy = e.touches[0].clientY - touchStartPos.y;
                if (Math.abs(dx) > 10 || Math.abs(dy) > 10) {
                    clearTimeout(formatMenuTimeout);
                }
            }
        });

        chatInput.addEventListener('touchend', function() {
            clearTimeout(formatMenuTimeout);
        });

        // Format menu options
        document.querySelectorAll('.chat-format-option').forEach(function(opt) {
            opt.addEventListener('click', function(e) {
                e.stopPropagation();
                const format = this.dataset.format;
                applyTextFormat(format);
                chatFormatMenu.style.display = 'none';
            });
        });

        document.addEventListener('click', function() {
            if (chatFormatMenu) chatFormatMenu.style.display = 'none';
        });
    }

    function showFormatMenu(x, y) {
        if (!chatFormatMenu) return;
        chatFormatMenu.style.display = 'block';
        chatFormatMenu.style.left = x + 'px';
        chatFormatMenu.style.top = y + 'px';

        // Adjust if off-screen
        const rect = chatFormatMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            chatFormatMenu.style.left = (x - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            chatFormatMenu.style.top = (y - rect.height) + 'px';
        }
    }

    function applyTextFormat(format) {
        if (!chatInput) return;

        const start = chatInput.selectionStart;
        const end = chatInput.selectionEnd;
        const selectedText = chatInput.value.substring(start, end);

        if (!selectedText) return;

        let formattedText = '';
        switch (format) {
            case 'bold':
                formattedText = '**' + selectedText + '**';
                break;
            case 'italic':
                formattedText = '*' + selectedText + '*';
                break;
            case 'strikethrough':
                formattedText = '~~' + selectedText + '~~';
                break;
            case 'code':
                formattedText = '`' + selectedText + '`';
                break;
            case 'quote':
                formattedText = '> ' + selectedText;
                break;
            case 'monospace':
                formattedText = '```\n' + selectedText + '\n```';
                break;
        }

        const newValue = chatInput.value.substring(0, start) + formattedText + chatInput.value.substring(end);
        chatInput.value = newValue;
        chatInput.focus();
        chatInput.setSelectionRange(start, start + formattedText.length);
    }

    // Monaco editor modal handlers
    const textEditorModal = document.getElementById('chat-text-editor-modal');
    const textEditorClose = document.getElementById('chat-text-editor-close');

    if (textEditorClose) {
        textEditorClose.addEventListener('click', function() {
            if (textEditorModal) textEditorModal.style.display = 'none';
            if (monacoEditor) {
                monacoEditor.dispose();
                monacoEditor = null;
            }
        });
    }

    // Voice recording modal - removed, now using press-and-hold on button
    let mediaRecorder = null;
    let audioChunks = [];
    let recordingStartTime = 0;
    let audioContext = null;
    let analyser = null;
    let animationFrame = null;

    if (chatFileInput) {
        chatFileInput.addEventListener('change', function() {
            if (this.files.length === 0) {
                return;
            }
            var f = this.files[0];
            function putFile(file) {
                var dt = new DataTransfer();
                dt.items.add(file);
                chatFileInput.files = dt.files;
            }
            if (editingMessageId) {
                if (f.type.indexOf('image/') === 0) {
                    openChatMediaEditor(f, function(out) {
                        chatFileInput.value = '';
                        if (!out) {
                            return;
                        }
                        putFile(out);
                    });
                } else {
                    putFile(f);
                }
                return;
            }
            if (f.type.indexOf('image/') === 0) {
                openChatMediaEditor(f, function(out) {
                    chatFileInput.value = '';
                    if (!out) {
                        return;
                    }
                    sendMessage(out);
                });
            } else {
                sendMessage(f);
                chatFileInput.value = '';
            }
        });
    }
    if (chatAdminMobileBack) {
        chatAdminMobileBack.addEventListener('click', function() {
            adminMobilePhase = 'list';
            syncAdminMobileLayout();
        });
    }
    if (adminChatListCloseBtn) {
        adminChatListCloseBtn.addEventListener('click', function() {
            if (isAdmin) {
                if (currentChatUser && editingMessageId) {
                    adminDraftEdits[currentChatUser] = { id: editingMessageId, text: chatInput ? chatInput.value : '' };
                    cancelEditUI();
                }
                window.location.hash = '';
            }
        });
    }
    window.addEventListener('resize', function() {
        syncAdminMobileLayout();
    });

    // Context menu
    document.addEventListener('click', () => {
        if (contextMenu) contextMenu.style.display = 'none';
    });

    if (contextEdit) {
        contextEdit.addEventListener('click', function(e) {
            e.stopPropagation();
            startEdit(contextMessageId);
            contextMenu.style.display = 'none';
        });
    }

    if (contextDelete) {
        contextDelete.addEventListener('click', function(e) {
            e.stopPropagation();
            deleteMessage(contextMessageId);
            contextMenu.style.display = 'none';
        });
    }

    function loadChatList() {
        fetch('?api=support_chat&action=get_chat_list')
            .then(r => {
                if (!r.ok || r.headers.get('content-length') === '0') {
                    throw new Error('Server returned ' + r.status + ' with empty body');
                }
                return r.json();
            })
            .then(data => {
                if (data.ok) {
                    var arr = data.chats || [];
                    if (arr.length) {
                        arr.sort(function(a, b) {
                            return (b.last_message_time || 0) - (a.last_message_time || 0);
                        });
                    }
                    renderChatList(arr);
                    if (isAdmin && arr.length) {
                        var keep = currentChatUser && arr.some(function(c) {
                            return c.user_email === currentChatUser;
                        });
                        if (!keep) {
                            window.selectChat(arr[0].user_email);
                        } else {
                            loadMessages(false);
                            startPolling();
                        }
                    }
                } else {
                    console.error('Failed to load chat list:', data.error);
                }
            })
            .catch(err => console.error('Error loading chat list:', err));
    }

    function renderChatList(chats) {
        if (!chatListContainer) {
            return;
        }
        lastAdminChatList = chats || [];
        if (!chats.length) {
            chatListContainer.innerHTML = '<p style="text-align:center;color:var(--text-color);opacity:0.6;">' + (T.support_chat_no_chats || 'No active chats') + '</p>';
            return;
        }
        chatListContainer.innerHTML = '';
        chats.forEach(function(chat) {
            var row = document.createElement('div');
            row.className = 'chat-list-row';
            if (currentChatUser === chat.user_email) {
                row.classList.add('chat-list-row-active');
            }
            row.style.cssText = 'padding:18px;background:linear-gradient(135deg,rgba(99,102,241,.05) 0%,rgba(168,85,247,.05) 100%);border:1px solid var(--border-color);border-radius:16px;cursor:pointer;margin-bottom:12px;transition:transform .2s,box-shadow .2s,border-color .2s,background .2s;';
            row.addEventListener('mouseenter', function() {
                row.style.transform = 'translateY(-2px)';
                row.style.boxShadow = '0 4px 12px rgba(99,102,241,.2)';
            });
            row.addEventListener('mouseleave', function() {
                row.style.transform = 'translateY(0)';
                row.style.boxShadow = 'none';
            });
            var title = document.createElement('div');
            title.style.cssText = 'font-weight:600;color:var(--text-color);margin-bottom:6px;font-size:15px;line-height:1.35;';
            var nm = (chat.user_name && String(chat.user_name).trim()) ? String(chat.user_name).trim() : '';
            if (nm) {
                title.appendChild(document.createTextNode(nm + ' '));
                var emSpan = document.createElement('span');
                emSpan.style.cssText = 'font-weight:500;opacity:.78;font-size:13px;word-break:break-all;';
                emSpan.textContent = chat.user_email;
                title.appendChild(emSpan);
            } else {
                title.textContent = chat.user_email;
            }
            if (chat.user_role >= 2) {
                var adm = document.createElement('span');
                adm.style.cssText = 'font-size:11px;opacity:.6;';
                adm.textContent = ' (Admin)';
                title.appendChild(adm);
            }
            var meta = document.createElement('div');
            meta.style.cssText = 'font-size:13px;color:var(--text-color);opacity:.6;';
            meta.textContent = chat.message_count + ' messages • ' + new Date(chat.last_message_time * 1000).toLocaleString();
            row.appendChild(title);
            row.appendChild(meta);
            row.addEventListener('click', function() {
                window.selectChat(chat.user_email);
            });
            chatListContainer.appendChild(row);
        });
    }

    function openUserSearchModal() {
        if (!userSearchModal) {
            return;
        }
        userSearchModal.style.display = 'flex';
        if (userSearchInput) {
            userSearchInput.value = '';
            userSearchInput.focus();
        }
        if (userSearchResults) {
            userSearchResults.innerHTML = '<p style="text-align:center;padding:20px;opacity:0.55;color:var(--text-color);font-size:14px;">' + escChat(T.support_chat_search_placeholder || 'Type to search') + '</p>';
        }
        runUserSearch('');
    }
    function closeUserSearchModal() {
        if (userSearchModal) {
            userSearchModal.style.display = 'none';
        }
    }
    function runUserSearch(q) {
        if (!userSearchResults) {
            return;
        }
        fetch('?api=support_chat&action=search_users&q=' + encodeURIComponent(q))
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                if (!data.ok) {
                    userSearchResults.innerHTML = '<p style="padding:16px;color:#ef4444;">' + escChat(data.error || 'Error') + '</p>';
                    return;
                }
                if (!data.users || data.users.length === 0) {
                    userSearchResults.innerHTML = '<p style="text-align:center;padding:20px;opacity:0.55;color:var(--text-color);">' + escChat(T.support_chat_no_search_results || 'No users found') + '</p>';
                    return;
                }
                userSearchResults.innerHTML = '';
                data.users.forEach(function(u) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.style.cssText = 'display:block;width:100%;text-align:left;padding:12px 14px;margin:0 0 6px 0;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-color);color:var(--text-color);cursor:pointer;font-size:14px;';
                    if (u.name && String(u.name).trim()) {
                        btn.innerHTML = escChat(u.name) + '<br><span style="font-size:12px;opacity:0.65;">' + escChat(u.email) + '</span>';
                    } else {
                        btn.textContent = u.email;
                    }
                    var em = u.email;
                    btn.addEventListener('click', function() {
                        closeUserSearchModal();
                        window.selectChat(em);
                    });
                    userSearchResults.appendChild(btn);
                });
            })
            .catch(function() {
                userSearchResults.innerHTML = '<p style="padding:16px;color:#ef4444;">' + escChat(T.network_error || 'Network error') + '</p>';
            });
    }
    if (newChatBtn) {
        newChatBtn.addEventListener('click', function() {
            openUserSearchModal();
        });
    }
    if (userSearchCancel) {
        userSearchCancel.addEventListener('click', closeUserSearchModal);
    }
    if (userSearchModal) {
        userSearchModal.addEventListener('click', function(e) {
            if (e.target === userSearchModal) {
                closeUserSearchModal();
            }
        });
    }
    if (userSearchInput) {
        userSearchInput.addEventListener('input', function() {
            var v = userSearchInput.value;
            if (userSearchTimer) {
                clearTimeout(userSearchTimer);
            }
            userSearchTimer = setTimeout(function() {
                runUserSearch(v.trim());
            }, 280);
        });
    }

    window.selectChat = function(userEmail) {
        if (isAdmin && currentChatUser && currentChatUser !== userEmail && editingMessageId) {
            adminDraftEdits[currentChatUser] = { id: editingMessageId, text: chatInput ? chatInput.value : '' };
            cancelEditUI();
        }
        currentChatUser = userEmail;
        if (chatMessagesArea) {
            chatMessagesArea.style.display = 'flex';
        }
        if (chatModalTitle) {
            chatModalTitle.textContent = 'Chat: ' + userEmail;
        }
        if (deleteChatBtn && isAdmin) {
            deleteChatBtn.style.display = 'block';
        }
        lastMessagesHash = '';
        messagesData = [];
        chatOldestId = null;
        chatMaxId = 0;
        chatHasMoreOlder = false;
        if (chatMessages) {
            chatMessages.innerHTML = '<p style="text-align:center;color:var(--text-color);opacity:0.7;padding:40px;">' + (T.support_chat_loading || 'Loading...') + '</p>';
        }
        if (isAdmin && lastAdminChatList.length) {
            renderChatList(lastAdminChatList);
        }
        loadMessages(false);
        startPolling();
        adminMobilePhase = 'chat';
        syncAdminMobileLayout();
    };

    function wireChatScroll() {
        if (!chatMessages || chatScrollWired) {
            return;
        }
        chatScrollWired = true;
        chatMessages.addEventListener('scroll', function() {
            if (!chatHasMoreOlder || chatLoadingOlder || !currentChatUser) {
                return;
            }
            if (chatMessages.scrollTop < 120 || shouldPrefetchOlderByPosition()) {
                loadOlderChunk();
            }
        });
    }
    function shouldPrefetchOlderByPosition() {
        if (!chatMessages) {
            return false;
        }
        var bubbles = chatMessages.querySelectorAll('.chat-msg-bubble');
        if (!bubbles.length) {
            return chatMessages.scrollTop < 120;
        }
        var wrapRect = chatMessages.getBoundingClientRect();
        var firstVisible = bubbles.length;
        for (var i = 0; i < bubbles.length; i++) {
            var b = bubbles[i];
            if (b.getBoundingClientRect().bottom >= wrapRect.top + 4) {
                firstVisible = i + 1;
                break;
            }
        }
        return firstVisible <= 10;
    }

    function loadOlderChunk() {
        if (!currentChatUser || !chatOldestId || chatLoadingOlder || !chatHasMoreOlder || !chatMessages) {
            return;
        }
        chatLoadingOlder = true;
        var prevH = chatMessages.scrollHeight;
        var prevT = chatMessages.scrollTop;
        var url = (isAdmin ? '?api=support_chat&action=get_messages&user_email=' + encodeURIComponent(currentChatUser) : '?api=support_chat&action=get_messages') + '&before_id=' + chatOldestId + '&limit=30';
        fetch(url)
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                chatLoadingOlder = false;
                if (!data.ok || data.mode !== 'older') {
                    return;
                }
                var byId = {};
                messagesData.forEach(function(m) {
                    byId[m.id] = m;
                });
                (data.messages || []).forEach(function(m) {
                    byId[m.id] = m;
                });
                messagesData = Object.keys(byId).map(function(k) {
                    return byId[k];
                }).sort(function(a, b) {
                    return a.id - b.id;
                });
                chatHasMoreOlder = !!data.has_more_older;
                chatOldestId = messagesData.length ? messagesData[0].id : null;
                lastMessagesHash = JSON.stringify(messagesData);
                renderMessages(messagesData, { preserveScroll: true, prevHeight: prevH, prevTop: prevT });
            })
            .catch(function() {
                chatLoadingOlder = false;
            });
    }

    function loadMessages(silent, opts) {
        if (!currentChatUser) {
            return;
        }
        silent = !!silent;
        opts = opts || {};
        var forceScrollBottom = !!opts.forceScrollBottom;
        var full = !!opts.full;
        var base = isAdmin ? '?api=support_chat&action=get_messages&user_email=' + encodeURIComponent(currentChatUser) : '?api=support_chat&action=get_messages';
        var url = base;
        if (full) {
            url += '&full=1';
        } else if (silent && chatMaxId > 0) {
            url += '&since_id=' + chatMaxId;
        } else if (!silent) {
            url += '&limit=30';
        } else {
            return;
        }
        fetch(url)
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                if (!data.ok) {
                    console.error('Failed to load messages:', data.error);
                    return;
                }
                wireChatScroll();
                wireChatLightbox();
                if (full || data.mode === 'full') {
                    messagesData = data.messages || [];
                    chatOldestId = messagesData.length ? messagesData[0].id : null;
                    chatMaxId = 0;
                    messagesData.forEach(function(m) {
                        chatMaxId = Math.max(chatMaxId, m.id);
                    });
                    chatHasMoreOlder = false;
                    lastMessagesHash = JSON.stringify(messagesData);
                    renderMessages(messagesData, { forceScrollBottom: forceScrollBottom });
                    maybeRestoreAdminEditDraft();
                    if (!silent) {
                        markAsRead();
                    }
                    return;
                }
                if (silent && data.mode === 'delta') {
                    if (!data.messages || !data.messages.length) {
                        if (forceScrollBottom) {
                            scrollChatToBottom(true);
                        }
                        return;
                    }
                    var wb = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 80;
                    var seen = {};
                    messagesData.forEach(function(m) {
                        seen[m.id] = true;
                    });
                    data.messages.forEach(function(m) {
                        if (!seen[m.id]) {
                            messagesData.push(m);
                            seen[m.id] = true;
                        }
                    });
                    messagesData.sort(function(a, b) {
                        return a.id - b.id;
                    });
                    chatMaxId = 0;
                    messagesData.forEach(function(m) {
                        chatMaxId = Math.max(chatMaxId, m.id);
                    });
                    lastMessagesHash = JSON.stringify(messagesData);
                    renderMessages(messagesData, { forceScrollBottom: wb || forceScrollBottom });
                    if (wb || forceScrollBottom) {
                        markAsRead();
                    }
                    return;
                }
                if (!silent && data.mode === 'initial') {
                    messagesData = data.messages || [];
                    chatOldestId = data.min_id != null ? data.min_id : null;
                    chatMaxId = data.max_id != null ? data.max_id : 0;
                    chatHasMoreOlder = !!data.has_more_older;
                    if (!messagesData.length) {
                        chatOldestId = null;
                        chatMaxId = 0;
                        chatHasMoreOlder = false;
                    }
                    lastMessagesHash = JSON.stringify(messagesData);
                    renderMessages(messagesData, { forceScrollBottom: forceScrollBottom });
                    maybeRestoreAdminEditDraft();
                    markAsRead();
                }
            })
            .catch(function(err) {
                console.error('Error loading messages:', err);
            });
    }

    function maybeRestoreAdminEditDraft() {
        if (!isAdmin || !currentChatUser) {
            return;
        }
        var d = adminDraftEdits[currentChatUser];
        if (!d || !d.id) {
            return;
        }
        var exists = messagesData.some(function(m) {
            return m.id === d.id && !m.is_deleted;
        });
        if (!exists) {
            delete adminDraftEdits[currentChatUser];
            return;
        }
        delete adminDraftEdits[currentChatUser];
        startEdit(d.id);
        if (chatInput) {
            chatInput.value = d.text != null ? d.text : '';
        }
    }

    function renderMessages(messages, opts) {
        if (!chatMessages) {
            return;
        }
        opts = opts || {};
        var forceScrollBottom = !!opts.forceScrollBottom;
        var preserveScroll = !!opts.preserveScroll;
        var prevHeight = opts.prevHeight;
        var prevTop = opts.prevTop;

        if (messages.length === 0) {
            chatMessages.innerHTML = '<p style="text-align:center;color:var(--text-color);opacity:0.6;padding:40px;">' + (T.support_chat_no_messages || 'No messages yet') + '</p>';
            if (forceScrollBottom) {
                scrollChatToBottom(false);
            }
            return;
        }

        const wasAtBottom = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 80;

        chatMessages.innerHTML = messages.map(msg => {
            const isOwn = msg.sender_email === currentUserEmail;
            const date = new Date(msg.created_at * 1000);
            const timeStr = date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

            let content = '';
            if (msg.is_deleted) {
                content = '<em style="opacity:0.5;font-size:13px;">🗑️ ' + (T.support_chat_deleted || 'Message deleted') + '</em>';
            } else {
                if (msg.media_path) {
                    const u = escAttrChat(msg.media_path);
                    if (msg.media_type === 'image') {
                        content = '<div class="chat-msg-media-wrap"><img class="chat-msg-media-open" data-full-src="' + u + '" src="' + u + '" alt=""></div>';
                    } else if (msg.media_type === 'video') {
                        content = '<div class="chat-msg-media-wrap"><video controls playsinline><source src="' + u + '"></video></div>';
                    } else if (msg.media_type === 'audio') {
                        const audioId = 'audio-' + msg.id;
                        content = '<div class="chat-msg-audio-wrap" data-audio-src="' + u + '" data-audio-id="' + audioId + '" data-own="' + (isOwn ? '1' : '0') + '">' +
                            '<button class="chat-msg-audio-play" onclick="toggleAudioPlay(\'' + audioId + '\', this)">' +
                            '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>' +
                            '</button>' +
                            '<canvas class="chat-msg-audio-waveform" width="200" height="36" data-audio-id="' + audioId + '"></canvas>' +
                            '<span class="chat-msg-audio-time">0:00</span>' +
                            '<audio id="' + audioId + '" src="' + u + '" preload="metadata"></audio>' +
                            '</div>';
                    } else if (msg.media_type === 'text') {
                        const fileName = u.split('/').pop();
                        const ext = fileName.split('.').pop().toLowerCase();
                        content = '<div class="chat-msg-text-file" onclick="openTextFileEditor(\'' + u + '\', \'' + escAttrChat(fileName) + '\')">' +
                            '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>' +
                            '<span style="font-size:13px;font-weight:600;">' + escChat(fileName) + '</span>' +
                            '<span style="font-size:11px;opacity:0.7;text-transform:uppercase;">' + ext + '</span>' +
                            '</div>';
                    }
                }
                if (msg.message) {
                    content += '<div style="word-wrap:break-word;line-height:1.5;">' + formatChatMessage(msg.message) + '</div>';
                }
            }

            const readStatus = isOwn && !msg.is_deleted ? (msg.is_read ? '✓✓' : '✓') : '';
            const editedLabel = msg.is_edited ? '<span style="font-size:10px;opacity:0.5;margin-left:6px;">edited</span>' : '';

            return `<div style="display:flex;justify-content:${isOwn ? 'flex-end' : 'flex-start'};margin-bottom:4px;">
                <div class="chat-msg-bubble" data-msg-id="${msg.id}" style="background:${isOwn ? 'linear-gradient(135deg, var(--chat-color) 0%, var(--chat-color)dd 100%)' : 'var(--group-block-bg)'};color:${isOwn ? 'white' : 'var(--text-color)'};padding:12px 16px;border-radius:${isOwn ? '20px 20px 4px 20px' : '20px 20px 20px 4px'};position:relative;box-shadow:0 2px 8px rgba(0,0,0,0.1);cursor:context-menu;" oncontextmenu="showContextMenu(event, ${msg.id}, ${isOwn}, ${msg.is_deleted})">
                    ${content}
                    <div style="font-size:11px;opacity:0.7;margin-top:6px;display:flex;align-items:center;justify-content:space-between;">
                        <span>${timeStr}${editedLabel}</span>
                        ${readStatus ? `<span style="margin-left:8px;">${readStatus}</span>` : ''}
                    </div>
                </div>
            </div>`;
        }).join('');

        requestAnimationFrame(function() {
            document.querySelectorAll('.chat-msg-audio-wrap').forEach(function(wrap) {
                const canvas = wrap.querySelector('.chat-msg-audio-waveform');
                const src = wrap.dataset.audioSrc;
                if (canvas && src) loadAudioWaveform(canvas, src);
            });
        });

        if (preserveScroll && prevHeight != null && prevTop != null) {
            requestAnimationFrame(function() {
                chatMessages.scrollTop = chatMessages.scrollHeight - prevHeight + prevTop;
            });
        } else if (forceScrollBottom || wasAtBottom) {
            scrollChatToBottom(!!forceScrollBottom);
        }
    }

    window.showContextMenu = function(e, msgId, isOwn, isDeleted) {
        e.preventDefault();
        if (isDeleted) return;
        if (!isOwn && !isAdmin) return;

        contextMessageId = msgId;
        contextMenu.style.display = 'block';
        contextMenu.style.left = e.pageX + 'px';
        contextMenu.style.top = e.pageY + 'px';

        contextEdit.style.display = isOwn ? 'block' : 'none';
        contextDelete.style.display = 'block';
    };

    function startEdit(messageId) {
        var oldPv = document.getElementById('edit-media-preview');
        if (oldPv) {
            oldPv.remove();
        }
        editingMessageId = messageId;

        // Find message data
        const msgData = messagesData.find(m => m.id === messageId);
        if (!msgData) return;

        // Set text
        chatInput.value = msgData.message || '';
        chatInput.focus();

        // Show current media preview if exists
        if (msgData.media_path) {
            const preview = document.createElement('div');
            preview.id = 'edit-media-preview';
            preview.style.cssText = 'padding:10px;background:rgba(99,102,241,0.1);border-radius:12px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;';

            if (msgData.media_type === 'image') {
                preview.innerHTML = `<img src="${msgData.media_path}" style="max-width:100px;max-height:100px;border-radius:8px;"><span style="color:var(--text-color);font-size:13px;">Current image (upload new to replace)</span>`;
            } else {
                preview.innerHTML = `<video src="${msgData.media_path}" style="max-width:100px;max-height:100px;border-radius:8px;"></video><span style="color:var(--text-color);font-size:13px;">Current video (upload new to replace)</span>`;
            }

            chatInput.parentElement.insertBefore(preview, chatInput.parentElement.firstChild);
        }

        if (chatCancelEditBtn) {
            chatCancelEditBtn.style.display = 'block';
        }
        if (chatSendBtn) {
            chatSendBtn.textContent = 'Save';
            chatSendBtn.style.width = 'auto';
            chatSendBtn.style.minWidth = '72px';
            chatSendBtn.style.height = '48px';
            chatSendBtn.style.padding = '0 16px';
            chatSendBtn.style.borderRadius = '24px';
            chatSendBtn.style.fontSize = '14px';
            chatSendBtn.style.fontWeight = '600';
            chatSendBtn.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
            chatSendBtn.style.boxShadow = '0 4px 12px rgba(245, 158, 11, 0.4)';
        }
        if (chatUploadBtn) {
            chatUploadBtn.style.display = 'flex';
        }
    }

    function saveEdit() {
        if (!editingMessageId) return;

        const newMessage = chatInput.value.trim();
        const newFile = chatFileInput.files[0];
        const msgRow = messagesData.find(m => m.id === editingMessageId);
        const hadKeepableMedia = !!(msgRow && msgRow.media_path && !msgRow.is_deleted);

        if (!newMessage && !newFile && !hadKeepableMedia) {
            if (typeof showTempNotification === 'function') {
                showTempNotification(T.support_chat_msg_empty || 'Message cannot be empty', 'error');
            } else {
                alert(T.support_chat_msg_empty || 'Message cannot be empty');
            }
            return;
        }

        const formData = new FormData();
        formData.append('action', 'edit_message');
        formData.append('message_id', editingMessageId);
        formData.append('message', newMessage);
        if (newFile) {
            if (newFile.size > 20 * 1024 * 1024) {
                if (typeof showTempNotification === 'function') {
                    showTempNotification(T.support_chat_file_too_large || 'File size must not exceed 20MB', 'error');
                } else {
                    alert(T.support_chat_file_too_large || 'File size must not exceed 20MB');
                }
                return;
            }
            formData.append('media', newFile);
        }

        fetch('?api=support_chat', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                clearAdminEditDraftForCurrentChat();
                cancelEditUI();
                loadMessages(false, { forceScrollBottom: true, full: true });
            } else {
                if (typeof showTempNotification === 'function') {
                    showTempNotification('Error: ' + (data.error || T.unknown_error), 'error');
                } else {
                    alert('Error: ' + (data.error || T.unknown_error));
                }
            }
        })
        .catch(err => {
            console.error('Error editing message:', err);
            if (typeof showTempNotification === 'function') {
                showTempNotification(T.network_error || 'Network error', 'error');
            } else {
                alert(T.network_error || 'Network error');
            }
        });
    }

    function sendMessage(file) {
        const message = chatInput ? chatInput.value.trim() : '';

        if (!message && !file) return;
        if (!currentChatUser) return;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('message', message);
        if (isAdmin) {
            formData.append('user_email', currentChatUser);
        }
        if (file) {
            if (file.size > 20 * 1024 * 1024) {
                if (typeof showTempNotification === 'function') {
                    showTempNotification(T.support_chat_file_too_large || 'File size must not exceed 20MB', 'error');
                } else {
                    alert(T.support_chat_file_too_large || 'File size must not exceed 20MB');
                }
                return;
            }
            formData.append('media', file);
        }

        fetch('?api=support_chat', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                if (chatInput) chatInput.value = '';
                if (chatFileInput) chatFileInput.value = '';
                if (chatMaxId > 0) {
                    loadMessages(true, { forceScrollBottom: true });
                } else {
                    loadMessages(false, { forceScrollBottom: true });
                }
            } else {
                if (typeof showTempNotification === 'function') {
                    showTempNotification('Error: ' + (data.error || T.unknown_error), 'error');
                } else {
                    alert('Error: ' + (data.error || T.unknown_error));
                }
            }
        })
        .catch(err => {
            console.error('Error sending message:', err);
            if (typeof showTempNotification === 'function') {
                showTempNotification(T.network_error || 'Network error', 'error');
            } else {
                alert(T.network_error || 'Network error');
            }
        });
    }

    function deleteMessage(messageId) {
        const formData = new FormData();
        formData.append('action', 'delete_message');
        formData.append('message_id', messageId);

        fetch('?api=support_chat', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                loadMessages(false, { full: true });
            } else {
                if (typeof showTempNotification === 'function') {
                    showTempNotification('Error: ' + (data.error || T.unknown_error), 'error');
                } else {
                    alert('Error: ' + (data.error || T.unknown_error));
                }
            }
        })
        .catch(err => console.error('Error deleting message:', err));
    }

    // Format chat message with markdown-like syntax
    function formatChatMessage(text) {
        if (!text) return '';

        // Escape HTML first
        text = escChat(text);

        // Code blocks with language (```lang\ncode\n```)
        text = text.replace(/```(\w+)?\n([\s\S]*?)```/g, function(match, lang, code) {
            lang = lang || 'text';
            const codeId = 'code-' + Math.random().toString(36).substr(2, 9);
            return '<div class="chat-msg-code-block">' +
                '<div class="chat-msg-code-header">' +
                '<span>' + escChat(lang) + '</span>' +
                '<button class="chat-msg-code-copy" onclick="copyChatCode(\'' + codeId + '\')">Copy</button>' +
                '</div>' +
                '<div class="chat-msg-code-content"><code id="' + codeId + '">' + code.trim() + '</code></div>' +
                '</div>';
        });

        // Inline code (`code`)
        text = text.replace(/`([^`]+)`/g, '<span class="chat-msg-inline-code">$1</span>');

        // Bold (**text** or __text__)
        text = text.replace(/\*\*([^\*]+)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/__([^_]+)__/g, '<strong>$1</strong>');

        // Italic (*text* or _text_)
        text = text.replace(/\*([^\*]+)\*/g, '<em>$1</em>');
        text = text.replace(/_([^_]+)_/g, '<em>$1</em>');

        // Strikethrough (~~text~~)
        text = text.replace(/~~([^~]+)~~/g, '<del>$1</del>');

        // Quote (> text)
        text = text.replace(/^&gt;\s*(.+)$/gm, '<div class="chat-msg-quote">$1</div>');

        // Line breaks
        text = text.replace(/\n/g, '<br>');

        return text;
    }

    // Copy code block content
    window.copyChatCode = function(codeId) {
        const codeEl = document.getElementById(codeId);
        if (!codeEl) return;

        const text = codeEl.textContent;
        navigator.clipboard.writeText(text).then(function() {
            const btn = codeEl.closest('.chat-msg-code-block').querySelector('.chat-msg-code-copy');
            if (btn) {
                const orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = orig; }, 2000);
            }
        }).catch(function(err) {
            console.error('Failed to copy:', err);
        });
    };

    // Audio playback
    const audioPlayers = {};
    window.toggleAudioPlay = function(audioId, btn) {
        const audio = document.getElementById(audioId);
        if (!audio) return;

        if (audio.paused) {
            // Pause all other audio
            Object.keys(audioPlayers).forEach(function(id) {
                if (id !== audioId && audioPlayers[id]) {
                    audioPlayers[id].pause();
                    audioPlayers[id].currentTime = 0;
                }
            });

            audio.play();
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>';
            audioPlayers[audioId] = audio;

            audio.ontimeupdate = function() {
                updateAudioProgress(audioId);
            };
            audio.onended = function() {
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
                audio.currentTime = 0;
                const _wrap = audio.closest('.chat-msg-audio-wrap');
                const _timeEl = _wrap ? _wrap.querySelector('.chat-msg-audio-time') : null;
                const _canvas = _wrap ? _wrap.querySelector('.chat-msg-audio-waveform') : null;
                const fmtT = function(s) { s=Math.floor(s)||0; return Math.floor(s/60)+':'+(s%60<10?'0':'')+(s%60); };
                if (_timeEl && audio.duration) _timeEl.textContent = fmtT(audio.duration);
                if (_canvas) {
                    const peaks = _canvas.dataset.peaks ? JSON.parse(_canvas.dataset.peaks) : null;
                    drawAudioWaveform(_canvas, 0, peaks);
                }
            };
        } else {
            audio.pause();
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
        }
    };

    function updateAudioProgress(audioId) {
        const audio = document.getElementById(audioId);
        if (!audio) return;
        const wrap = audio.closest('.chat-msg-audio-wrap');
        if (!wrap) return;
        const timeEl = wrap.querySelector('.chat-msg-audio-time');
        const canvas = wrap.querySelector('.chat-msg-audio-waveform');
        const fmtT = function(s) { s = Math.floor(s)||0; return Math.floor(s/60)+':'+(s%60<10?'0':'')+(s%60); };
        if (timeEl && audio.duration) timeEl.textContent = fmtT(audio.currentTime);
        if (canvas && audio.duration) {
            const peaks = canvas.dataset.peaks ? JSON.parse(canvas.dataset.peaks) : null;
            drawAudioWaveform(canvas, audio.currentTime / audio.duration, peaks);
        }
    }

    function loadAudioWaveform(canvas, src) {
        const audioId = canvas.dataset.audioId;
        const audio = document.getElementById(audioId);
        const wrap = canvas.closest('.chat-msg-audio-wrap');
        const timeEl = wrap ? wrap.querySelector('.chat-msg-audio-time') : null;
        const fmtT = function(s) { s=Math.floor(s)||0; return Math.floor(s/60)+':'+(s%60<10?'0':'')+(s%60); };
        if (audio) {
            const applyDur = function() { if (audio.duration && isFinite(audio.duration) && timeEl) timeEl.textContent = fmtT(audio.duration); };
            if (audio.readyState >= 1) applyDur(); else audio.addEventListener('loadedmetadata', applyDur);
        }
        canvas.addEventListener('click', function(e) {
            if (!audio || !audio.duration) return;
            audio.currentTime = ((e.clientX - canvas.getBoundingClientRect().left) / canvas.getBoundingClientRect().width) * audio.duration;
            updateAudioProgress(audioId);
        });
        if (canvas.dataset.peaks) { drawAudioWaveform(canvas, 0, JSON.parse(canvas.dataset.peaks)); return; }
        drawAudioWaveform(canvas, 0, null);
        fetch(src)
            .then(function(r) { return r.arrayBuffer(); })
            .then(function(buf) {
                const ac = new (window.AudioContext || window.webkitAudioContext)();
                return ac.decodeAudioData(buf).then(function(decoded) { ac.close(); return decoded; });
            })
            .then(function(decoded) {
                const raw = decoded.getChannelData(0);
                const bars = 50;
                const block = Math.floor(raw.length / bars);
                const peaks = [];
                for (let i = 0; i < bars; i++) {
                    let max = 0;
                    for (let j = 0; j < block; j++) { const v = Math.abs(raw[i*block+j]); if (v > max) max = v; }
                    peaks.push(max);
                }
                const maxP = Math.max.apply(null, peaks.concat([0.01]));
                const norm = peaks.map(function(p) { return p / maxP; });
                canvas.dataset.peaks = JSON.stringify(norm);
                drawAudioWaveform(canvas, 0, norm);
            })
            .catch(function() {});
    }
    function drawAudioWaveform(canvas, progress, peaks) {
        if (!canvas) return;
        progress = progress || 0;
        const ctx = canvas.getContext('2d');
        const w = canvas.clientWidth || canvas.offsetWidth || 200;
        const h = canvas.clientHeight || canvas.offsetHeight || 36;
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
        ctx.scale(dpr, dpr);
        const wrap = canvas.closest('.chat-msg-audio-wrap');
        const isOwn = !!(wrap && wrap.dataset.own === '1');
        const chatColor = getComputedStyle(document.documentElement).getPropertyValue('--chat-color').trim() || '#6366f1';
        const bars = peaks ? peaks.length : 48;
        const gap = 2;
        const barW = Math.max(2, (w - gap * (bars - 1)) / bars);
        ctx.clearRect(0, 0, w, h);
        for (let i = 0; i < bars; i++) {
            let amp;
            if (peaks) {
                amp = 0.1 + peaks[i] * 0.88;
            } else {
                const seed = canvas.dataset.audioId || '0';
                const x = Math.sin(i * 12.9898 + seed.length) * 43758.5453;
                amp = 0.15 + (x - Math.floor(x)) * 0.55;
            }
            const barH = Math.max(3, amp * h);
            const x = i * (barW + gap);
            const y = (h - barH) / 2;
            const r = Math.min(barW / 2, 2);
            const played = i / bars < progress;
            ctx.fillStyle = played
                ? (isOwn ? 'rgba(255,255,255,0.95)' : chatColor)
                : (isOwn ? 'rgba(255,255,255,0.28)' : 'rgba(140,145,155,0.38)');
            ctx.beginPath();
            if (ctx.roundRect) {
                ctx.roundRect(x, y, barW, barH, r);
            } else {
                ctx.moveTo(x + r, y);
                ctx.arcTo(x+barW, y, x+barW, y+barH, r);
                ctx.arcTo(x+barW, y+barH, x, y+barH, r);
                ctx.arcTo(x, y+barH, x, y, r);
                ctx.arcTo(x, y, x+barW, y, r);
                ctx.closePath();
            }
            ctx.fill();
        }
    }

    // Open text file in Monaco editor
    window.openTextFileEditor = function(filePath, fileName) {
        const modal = document.getElementById('chat-text-editor-modal');
        const title = document.getElementById('chat-text-editor-title');
        const container = document.getElementById('chat-text-editor-container');
        const saveBtn = document.getElementById('chat-text-editor-save');
        const readonlyBadge = document.getElementById('chat-text-editor-readonly-badge');

        if (!modal || !container) return;

        title.textContent = fileName;
        modal.style.display = 'flex';

        // Fetch file content via API
        fetch('?api=support_chat&action=read_file&file=' + encodeURIComponent(filePath))
            .then(r => r.json())
            .then(data => {
                if (!data.ok) {
                    throw new Error(data.error || 'Failed to load file');
                }

                const content = data.content;

                // Detect language from extension
                const ext = fileName.split('.').pop().toLowerCase();
                const langMap = {
                    'js': 'javascript', 'ts': 'typescript', 'py': 'python',
                    'java': 'java', 'c': 'c', 'cpp': 'cpp', 'cs': 'csharp',
                    'php': 'php', 'rb': 'ruby', 'go': 'go', 'rs': 'rust',
                    'sql': 'sql', 'html': 'html', 'css': 'css', 'json': 'json',
                    'xml': 'xml', 'yaml': 'yaml', 'yml': 'yaml', 'md': 'markdown',
                    'sh': 'shell', 'bat': 'bat', 'txt': 'plaintext'
                };
                const language = langMap[ext] || 'plaintext';

                // Load Monaco Editor if not loaded
                if (!window.monaco) {
                    loadMonacoEditor(function() {
                        createMonacoEditor(container, content, language, filePath, fileName);
                    });
                } else {
                    createMonacoEditor(container, content, language, filePath, fileName);
                }

                // Show as read-only for now
                readonlyBadge.style.display = 'inline-block';
                saveBtn.style.display = 'none';
            })
            .catch(err => {
                console.error('Failed to load file:', err);
                if (typeof showTempNotification === 'function') {
                    showTempNotification(T.failed_to_load_file + ' ' + err.message, 'error');
                } else {
                    alert(T.failed_to_load_file + ' ' + err.message);
                }
                modal.style.display = 'none';
            });
    };

    let monacoEditor = null;
    function createMonacoEditor(container, content, language, filePath, fileName) {
        container.innerHTML = '';

        const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs';

        monacoEditor = monaco.editor.create(container, {
            value: content,
            language: language,
            theme: theme,
            readOnly: true,
            automaticLayout: true,
            minimap: { enabled: true },
            fontSize: 14,
            lineNumbers: 'on',
            scrollBeyondLastLine: false,
            wordWrap: 'on'
        });
    }

    function loadMonacoEditor(callback) {
        window.apLoadMonaco().then(callback).catch(function(e){console.error('Failed to load Monaco:', e);});
    }

    function closeSupportChatDeleteModal() {
        if (!supportChatDeleteModal) {
            return;
        }
        supportChatDeleteModal.style.display = 'none';
        if (supportChatDeleteConfirm) {
            supportChatDeleteConfirm.disabled = false;
        }
        if (supportChatDeleteCancel) {
            supportChatDeleteCancel.disabled = false;
        }
    }
    function executeSupportChatDelete() {
        if (!currentChatUser || !isAdmin) {
            return;
        }
        var ue = currentChatUser;
        if (supportChatDeleteConfirm) {
            supportChatDeleteConfirm.disabled = true;
        }
        if (supportChatDeleteCancel) {
            supportChatDeleteCancel.disabled = true;
        }
        const formData = new FormData();
        formData.append('action', 'delete_chat');
        formData.append('user_email', ue);
        fetch('?api=support_chat', {
            method: 'POST',
            body: formData
        })
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                if (data.ok) {
                    closeSupportChatDeleteModal();
                    chatAlert(T.support_chat_deleted_ok || 'Chat deleted');
                    currentChatUser = null;
                    if (chatMessagesArea) {
                        chatMessagesArea.style.display = 'none';
                    }
                    if (deleteChatBtn) {
                        deleteChatBtn.style.display = 'none';
                    }
                    if (chatModalTitle) {
                        chatModalTitle.textContent = 'Support Chats';
                    }
                    adminMobilePhase = 'list';
                    syncAdminMobileLayout();
                    loadChatList();
                    if (chatModal) {
                        chatModal.style.display = 'none';
                    }
                    stopPolling();
                } else {
                    chatAlert('Error: ' + (data.error || 'Unknown error'));
                    if (supportChatDeleteConfirm) {
                        supportChatDeleteConfirm.disabled = false;
                    }
                    if (supportChatDeleteCancel) {
                        supportChatDeleteCancel.disabled = false;
                    }
                }
            })
            .catch(function(err) {
                console.error('Error deleting chat:', err);
                chatAlert(T.network_error || 'Network error');
                if (supportChatDeleteConfirm) {
                    supportChatDeleteConfirm.disabled = false;
                }
                if (supportChatDeleteCancel) {
                    supportChatDeleteCancel.disabled = false;
                }
            });
    }
    function openSupportChatDeleteModal() {
        if (!currentChatUser || !isAdmin) {
            return;
        }
        if (!supportChatDeleteModal) {
            var fb = T.support_chat_delete_confirm || 'Delete this entire chat? This cannot be undone.';
            if (typeof fmConfirm === 'function') {
                fmConfirm(fb, executeSupportChatDelete);
            } else if (window.confirm(fb)) {
                executeSupportChatDelete();
            }
            return;
        }
        if (supportChatDeleteEmail) {
            supportChatDeleteEmail.textContent = currentChatUser;
        }
        supportChatDeleteModal.style.display = 'flex';
        requestAnimationFrame(function() {
            supportChatDeleteModal.focus();
        });
    }
    function wireSupportChatDeleteModal() {
        if (!supportChatDeleteModal || supportChatDeleteModal._scDelWired) {
            return;
        }
        supportChatDeleteModal._scDelWired = true;
        supportChatDeleteModal.addEventListener('click', function(e) {
            if (e.target === supportChatDeleteModal) {
                closeSupportChatDeleteModal();
            }
        });
        if (supportChatDeleteCard) {
            supportChatDeleteCard.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
        if (supportChatDeleteCancel) {
            supportChatDeleteCancel.addEventListener('click', closeSupportChatDeleteModal);
        }
        if (supportChatDeleteConfirm) {
            supportChatDeleteConfirm.addEventListener('click', executeSupportChatDelete);
        }
        window.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') {
                return;
            }
            if (!supportChatDeleteModal || supportChatDeleteModal.style.display !== 'flex') {
                return;
            }
            closeSupportChatDeleteModal();
            e.preventDefault();
            e.stopPropagation();
        }, true);
    }
    function deleteChat() {
        if (!currentChatUser || !isAdmin) {
            return;
        }
        wireSupportChatDeleteModal();
        openSupportChatDeleteModal();
    }

    if (deleteChatBtn) {
        deleteChatBtn.addEventListener('click', deleteChat);
    }

    function markAsRead() {
        if (!currentChatUser) return;

        const url = isAdmin
            ? `?api=support_chat&action=mark_read&user_email=${encodeURIComponent(currentChatUser)}`
            : '?api=support_chat&action=mark_read';

        fetch(url).catch(err => console.error('Error marking as read:', err));
    }

    function startPolling() {
        stopPolling();
        chatPollInterval = setInterval(() => {
            loadMessages(true);
            checkUnreadMessages();
        }, 3000);
    }

    function stopPolling() {
        if (chatPollInterval) {
            clearInterval(chatPollInterval);
            chatPollInterval = null;
        }
    }

    var unreadRequestInFlight = false;
    var lastUnreadWarnAt = 0;
    function checkUnreadMessages() {
        if (unreadRequestInFlight) return;
        unreadRequestInFlight = true;
        fetch('?api=support_chat&action=get_unread_count')
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                if (!data.ok) {
                    return;
                }
                updateChatButton(data.count || 0);
            })
            .catch(function(err) {
                var now = Date.now();
                if (now - lastUnreadWarnAt > 30000) {
                    lastUnreadWarnAt = now;
                    console.warn('Error checking unread:', err && err.message ? err.message : err);
                }
            })
            .finally(function() {
                unreadRequestInFlight = false;
            });
    }

    function updateChatButton(count) {
        const btn = document.getElementById('support-chat-btn');
        const badge = document.getElementById('chat-unread-badge');
        if (!btn) {
            return;
        }
        if (count > 0) {
            btn.classList.add('has-unread');
            if (badge) {
                badge.style.display = 'block';
                badge.textContent = count > 99 ? '99+' : count;
            }
        } else {
            btn.classList.remove('has-unread');
            if (badge) badge.style.display = 'none';
        }
    }

    // Check unread on page load
    if (chatBtn) {
        setTimeout(checkUnreadMessages, 1000);
        setInterval(checkUnreadMessages, 30000); // Check every 30 seconds
    }
})();
</script>

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


<!-- Weather Widget Forecast Modal -->
<div id="weatherModalOverlay" class="weather-modal-overlay" onclick="closeWeatherModal(event)">
    <div class="weather-modal" onclick="event.stopPropagation()">
        <button type="button" class="weather-modal-close" onclick="closeWeatherModal()" aria-label="Close">&times;</button>
        <button type="button" class="weather-settings-btn" id="weatherSettingsBtn" onclick="openWeatherSettings()" title="Settings" style="display:none;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.54 3.46a10 10 0 0 1 7.92 12.54"/><path d="M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z"/><path d="M2 12h20"/><path d="M4.93 4.93l14.14 14.14"/></svg>
        </button>
        <button type="button" class="weather-wind-chart-btn" id="weatherWindChartBtn" onclick="toggleWindChart()" title="Show wind chart" style="display:none;">
            <svg id="weatherWindChartIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></svg>
        </button>
        <div id="weatherModalContent">
            <div class="weather-loading" style="display:flex;justify-content:center;padding:40px 0;">
                <div class="weather-loading-spinner"></div>
                <span>Loading forecast...</span>
            </div>
        </div>
    </div>
</div>

<script>
/* ── Enhanced Weather Widget with 7-Day Forecast Modal ── */
(function(){
var weatherWidget, weatherStore;
var windChartVisible = false;

function init() {
    weatherStore = { city: '', lat: 0, lon: 0, current: null, daily: null };
    weatherWidget = document.getElementById('weatherWidget');
    if(!weatherWidget) return;
    
    /* Set cursor and click handler */
    weatherWidget.style.cursor = 'pointer';
    weatherWidget.title = 'Click to see 7-day forecast';
    weatherWidget.addEventListener('click', function(e) {
        showWeatherModal();
    });
    
    initWeather();
}

if(document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

/* ── Store for modal data ─ */

/* Simple HTML escaper */
function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

var WEEKDAY_NAMES = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

/* ── SVG icons for each weather type ── */
var WEATHER_ICONS = {
    sunny: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="#FFD700" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5" fill="#FFD700" opacity="0.2"/><circle cx="12" cy="12" r="5" stroke="#FFD700" stroke-width="2"/><line x1="12" y1="1" x2="12" y2="3" stroke="#FFD700"/><line x1="12" y1="21" x2="12" y2="23" stroke="#FFD700"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64" stroke="#FFD700"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" stroke="#FFD700"/><line x1="1" y1="12" x2="3" y2="12" stroke="#FFD700"/><line x1="21" y1="12" x2="23" y2="12" stroke="#FFD700"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36" stroke="#FFD700"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" stroke="#FFD700"/></svg>',
    partly_cloudy: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M17.66 6.34l1.41-1.41" stroke="#FFD700"/><path d="M8 12a4 4 0 0 1 4-4 4 4 0 0 1 3.5 2.1 3 3 0 0 1 .5 5.9H8a4 4 0 0 1 0-8z" fill="#FFFFFF" stroke="#94A3B8" stroke-width="1"/></svg>',
    cloudy: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z" fill="#94A3B8" stroke="#64748B"/></svg>',
    overcast: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15h18M3 18h18M3 12h18" stroke="#94A3B8" opacity="0.6"/><path d="M8 12a4 4 0 0 1 4-4 4 4 0 0 1 3.5 2.1 3 3 0 0 1 .5 5.9H8a4 4 0 0 1 0-8z" fill="#64748B" opacity="0.8"/></svg>',
    fog: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12" stroke="#CBD5E1"/><line x1="3" y1="15" x2="21" y2="15" stroke="#CBD5E1"/><line x1="3" y1="18" x2="21" y2="18" stroke="#CBD5E1"/><line x1="3" y1="9" x2="21" y2="9" stroke="#CBD5E1" opacity="0.6"/></svg>',
    drizzle: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12a4 4 0 0 1 4-4 4 4 0 0 1 3.5 2.1 3 3 0 0 1 .5 5.9H8a4 4 0 0 1 0-8z" fill="#93C5FD" opacity="0.6" stroke="#60A5FA"/><line x1="8" y1="16" x2="8" y2="17" stroke="#60A5FA"/><line x1="12" y1="16" x2="12" y2="17" stroke="#60A5FA"/><line x1="16" y1="16" x2="16" y2="17" stroke="#60A5FA"/><line x1="10" y1="18" x2="10" y2="19" stroke="#60A5FA"/><line x1="14" y1="18" x2="14" y2="19" stroke="#60A5FA"/></svg>',
    rain: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12a4 4 0 0 1 4-4 4 4 0 0 1 3.5 2.1 3 3 0 0 1 .5 5.9H8a4 4 0 0 1 0-8z" fill="#60A5FA" opacity="0.4" stroke="#3B82F6"/><line x1="8" y1="16" x2="7" y2="19" stroke="#3B82F6"/><line x1="12" y1="16" x2="11" y2="21" stroke="#3B82F6"/><line x1="16" y1="16" x2="15" y2="19" stroke="#3B82F6"/><line x1="10" y1="18" x2="9" y2="20" stroke="#3B82F6"/><line x1="14" y1="18" x2="13" y2="20" stroke="#3B82F6"/></svg>',
    snow: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 12a4 4 0 0 1 4-4 4 4 0 0 1 3.5 2.1 3 3 0 0 1 .5 5.9H8a4 4 0 0 1 0-8z" fill="#BAE6FD" opacity="0.6" stroke="#0EA5E9"/><circle cx="8" cy="18" r="1.5" fill="#FFFFFF"/><circle cx="12" cy="20" r="1.5" fill="#FFFFFF"/><circle cx="16" cy="18" r="1.5" fill="#FFFFFF"/><circle cx="10" cy="22" r="1.2" fill="#FFFFFF"/><circle cx="14" cy="22" r="1.2" fill="#FFFFFF"/><path d="M10 24h4" stroke="#FFFFFF" stroke-width="1.5" stroke-linecap="round"/></svg>',
    thunderstorm: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 18a2 2 0 0 0 0-4h-1.5m-9-1A4 4 0 0 1 8 6a5 5 0 0 1 8.9-2.6A3.5 3.5 0 0 1 17 11.5" stroke="#7C3AED"/><polyline points="13 13 11 17 14 17 12 21" stroke="#A855F7"/><polygon points="13,4 11,8 15,8 13,12" fill="#FEF3C7" stroke="#F59E0B" stroke-width="1"/></svg>',
    windy: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5h6a3 3 0 0 1 0 6H9m0 0H4m5 0h4a3 3 0 0 1 0 6H9m0 0H5" stroke="#94A3B8"/><path d="M15 10c0 2-1 4-3 4s-3-2-3-4" stroke="#64748B" stroke-width="1.5" opacity="0.7"/></svg>'
};

/* ── Smaller icon set for daily rows ── */
var DAILY_ICONS = {};
for(var k in WEATHER_ICONS) {
    DAILY_ICONS[k] = WEATHER_ICONS[k].replace(/width="48"/g,'').replace(/height="48"/g,'');
}

/* ── WMO Weather Codes ── */
function getWeatherInfo(code, isDay) {
    var key, desc;
    if(code === 0) {
        key = 'sunny';
        desc = 'Clear';
    } else if(code <= 2) {
        key = isDay ? 'partly_cloudy' : 'cloudy';
        desc = code === 1 ? 'Mainly clear' : 'Partly cloudy';
    } else if(code === 3) {
        key = 'overcast';
        desc = 'Overcast';
    } else if(code >= 45 && code <= 48) {
        key = 'fog';
        desc = 'Foggy';
    } else if(code >= 51 && code <= 55) {
        key = 'drizzle';
        desc = 'Drizzle';
    } else if(code >= 61 && code <= 65) {
        key = 'rain';
        desc = 'Rain';
    } else if(code >= 71 && code <= 77) {
        key = 'snow';
        desc = 'Snow';
    } else if(code >= 80 && code <= 82) {
        key = 'rain';
        desc = 'Rain showers';
    } else if(code >= 95 && code <= 99) {
        key = 'thunderstorm';
        desc = 'Thunderstorm';
    } else {
        key = 'cloudy';
        desc = 'Cloudy';
    }
    return { key: key, desc: desc };
}

/* ── Format date ── */
function formatDate(dateStr) {
    var d = new Date(dateStr + 'T12:00:00');
    return d.getDate();
}
function getDayName(dateStr) {
    var d = new Date(dateStr + 'T12:00:00');
    var today = new Date();
    today.setHours(12,0,0,0);
    var diff = Math.round((d.getTime() - today.getTime()) / 86400000);
    if(diff === 0) return 'Today';
    if(diff === 1) return 'Tomorrow';
    return WEEKDAY_NAMES[d.getDay()];
}

/* ── Render the widget ── */
function renderWeather(city, temp, windSpeed, code, isDay) {
    var info = getWeatherInfo(code, isDay);
    var iconSvg = WEATHER_ICONS[info.key] || WEATHER_ICONS.cloudy;
    weatherWidget.style.display = 'flex';
    weatherWidget.innerHTML = 
        '<div class="weather-icon-wrap">' + iconSvg + '</div>' +
        '<div class="weather-info">' +
            '<div class="weather-city">' + escHtml(city) + '</div>' +
            '<div class="weather-temp-row">' +
                '<span class="weather-temp">' + Math.round(temp) + '°C</span>' +
                '<span class="weather-desc">' + escHtml(info.desc) + '</span>' +
                '<span class="weather-wind">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5h6a3 3 0 0 1 0 6H9m0 0H4m5 0h4a3 3 0 0 1 0 6H9m0 0H5"/></svg>' +
                    escHtml(windSpeed) + ' m/s' +
                '</span>' +
            '</div>' +
        '</div>';
    /* Cursor is already set via addEventListener above */
}

/* ── Show error state ── */
function showWeatherError(message, showBtn) {
    weatherWidget.style.display = 'flex';
    var html = '<div class="weather-error">' +
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
        '<span>' + escHtml(message) + '</span>';
    if(showBtn) {
        html += '<button type="button" class="weather-deny-btn" onclick="openLocationSettings()">' +
            (T.weather_allow_location || 'Allow location') +
        '</button>';
    }
    html += '</div>';
    weatherWidget.innerHTML = html;
}

/* ── Show loading state ── */
function showWeatherLoading() {
    weatherWidget.style.display = 'flex';
    weatherWidget.innerHTML = 
        '<div class="weather-loading">' +
            '<div class="weather-loading-spinner"></div>' +
            '<span>' + (T.weather_loading || 'Loading weather...') + '</span>' +
        '</div>';
}

/* ── Location settings helper ── */
window.openLocationSettings = function() {
    if(typeof showTempNotification === 'function') {
        showTempNotification(T.weather_denied_msg || 'Please allow location access in your browser settings to see local weather', 'error');
    }
    if(navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({name:'geolocation'}).then(function(result) {
            if(result.state === 'denied') {
                if(typeof showTempNotification === 'function') {
                    showTempNotification(T.weather_settings_hint || 'Go to browser site settings and allow location', 'info');
                }
            }
        });
    }
};

/* ═══════════════════════════════════════════════
   ── WEATHER MODAL: Weekly Forecast & Chart ──
   ═══════════════════════════════════════════════ */
var modalOverlay = document.getElementById('weatherModalOverlay');
var modalContent = document.getElementById('weatherModalContent');

function showWeatherModal() {
    if(!modalOverlay) return;
    
    /* If daily data is already loaded, show it immediately */
    if(weatherStore.daily && weatherStore.daily.time) {
        showModalWithData();
        return;
    }
    
    /* If we have coordinates, try to load forecast on demand */
    if(weatherStore.lat && weatherStore.lon) {
        modalContent.innerHTML = '<div class="weather-loading" style="display:flex;justify-content:center;padding:40px 0;">' +
            '<div class="weather-loading-spinner"></div>' +
            '<span>Loading forecast...</span>' +
        '</div>';
        modalOverlay.style.display = 'flex';
        modalOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        var url = 'https://api.open-meteo.com/v1/forecast?latitude=' + weatherStore.lat + '&longitude=' + weatherStore.lon +
            '&daily=weathercode,temperature_2m_max,temperature_2m_min,precipitation_sum,windspeed_10m_max' +
            '&timezone=auto&forecast_days=7';
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if(data && data.daily) {
                    weatherStore.daily = data.daily;
                    showModalWithData();
                } else {
                    modalContent.innerHTML = '<div style="text-align:center;padding:40px;opacity:0.6">No forecast data available</div>';
                }
            })
            .catch(function() {
                modalContent.innerHTML = '<div style="text-align:center;padding:40px;opacity:0.6">Failed to load forecast</div>';
            });
        return;
    }
    
    modalContent.innerHTML = '<div style="text-align:center;padding:40px;opacity:0.6">No forecast data available</div>';
    modalOverlay.style.display = 'flex';
    modalOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function showModalWithData() {
    modalOverlay.style.display = 'flex';
    modalOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    windChartVisible = false;
    var windBtn = document.getElementById('weatherWindChartBtn');
    var settingsBtn = document.getElementById('weatherSettingsBtn');
    if (windBtn) {
        windBtn.style.display = (weatherStore.daily && weatherStore.daily.windspeed_10m_max) ? 'flex' : 'none';
    }
    if (settingsBtn) {
        settingsBtn.style.display = 'flex';
    }
    renderWeeklyForecast();
}

window.closeWeatherModal = function(e) {
    if(e && e.target !== modalOverlay && e.target !== e.currentTarget) return;
    if(modalOverlay) {
        modalOverlay.style.display = 'none';
        modalOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
};

/* Close on Escape key */
document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('active')) {
        closeWeatherModal();
    }
});

/* ── Render 7-day forecast & chart ── */
function renderWeeklyForecast() {
    var daily = weatherStore.daily;
    var current = weatherStore.current;
    var city = weatherStore.city;
    if(!daily || !daily.time) {
        modalContent.innerHTML = '<div style="text-align:center;padding:40px;opacity:0.6">No forecast data available</div>';
        return;
    }
    
    var html = '';
    
    /* Header with current weather */
    var curInfo = current ? getWeatherInfo(current.weathercode, current.is_day !== 0) : { key:'sunny', desc:'Clear' };
    var curIcon = current ? (WEATHER_ICONS[curInfo.key] || WEATHER_ICONS.cloudy) : WEATHER_ICONS.sunny;
    html += '<div class="weather-modal-header">' +
        '<div class="weather-modal-header-icon">' + curIcon + '</div>' +
        '<div class="weather-modal-header-info">' +
            '<div class="weather-modal-city">' + escHtml(city) + '</div>' +
            '<div class="weather-modal-current">' +
                (current ? Math.round(current.temperature) + '°C &middot; ' + escHtml(curInfo.desc) + ' &middot; Wind ' + current.windspeed + ' m/s' : '') +
            '</div>' +
        '</div>' +
    '</div>';
    
    /* 7-day rows */
    html += '<div class="weather-weekly">';
    for(var i = 0; i < daily.time.length; i++) {
        var dateStr = daily.time[i];
        var wCode = daily.weathercode[i];
        var isDay = 1;
        var wInfo = getWeatherInfo(wCode, isDay);
        var iconSvg = WEATHER_ICONS[wInfo.key] || WEATHER_ICONS.cloudy;
        var dayName = getDayName(dateStr);
        var dayDate = formatDate(dateStr);
        var high = Math.round(daily.temperature_2m_max[i]);
        var low = Math.round(daily.temperature_2m_min[i]);
        var precip = daily.precipitation_sum ? Math.round(daily.precipitation_sum[i] * 10) / 10 : 0;
        
        html += '<div class="weather-day-row">' +
            '<div class="weather-day-name">' + escHtml(dayName) + '</div>' +
            '<div class="weather-day-date">' + dayDate + '</div>' +
            '<div class="weather-day-icon-wrap">' + iconSvg + '</div>' +
            '<div class="weather-day-desc">' + escHtml(wInfo.desc) + '</div>' +
            '<div class="weather-day-temps">' +
                '<span class="weather-day-temp-high">' + high + '°</span>' +
                '<span class="weather-day-temp-low">' + low + '°</span>' +
            '</div>' +
            '<div class="weather-day-precip">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#3b82f6" stroke="none"><path d="M12 2C7.58 2 4 5.58 4 10c0 3.5 2.33 6.35 5.5 7.5L12 22l2.5-4.5C17.67 16.35 20 13.5 20 10c0-4.42-3.58-8-8-8z"/></svg>' +
                (precip > 0 ? precip + 'mm' : '0') +
            '</div>' +
        '</div>';
    }
    html += '</div>';
    
    /* Temperature chart */
    html += '<div class="weather-chart-wrapper">' +
        '<div class="weather-chart-title">Temperature trend (7 days)</div>' +
        '<div class="weather-chart-container">' +
            '<canvas id="weatherChartCanvas" width="600" height="200"></canvas>' +
        '</div>' +
        '<div class="weather-chart-legend">' +
            '<div class="weather-chart-legend-item"><span class="weather-chart-legend-dot" style="background:#ef4444"></span> High</div>' +
            '<div class="weather-chart-legend-item"><span class="weather-chart-legend-dot" style="background:#3b82f6"></span> Low</div>' +
        '</div>' +
    '</div>';
    
    modalContent.innerHTML = html;
    
    /* Draw chart after DOM update */
    setTimeout(function() {
        drawTemperatureChart(daily);
    }, 50);
}

/* ── Draw temperature chart using Canvas ── */
function drawTemperatureChart(daily) {
    var canvas = document.getElementById('weatherChartCanvas');
    if(!canvas || !daily || !daily.time || daily.time.length === 0) return;
    
    var ctx = canvas.getContext('2d');
    var W = canvas.width;
    var H = canvas.height;
    var pad = { top: 20, right: 20, bottom: 32, left: 40 };
    var chartW = W - pad.left - pad.right;
    var chartH = H - pad.top - pad.bottom;
    
    var n = daily.time.length;
    var highs = [];
    var lows = [];
    for(var i = 0; i < n; i++) {
        highs.push(daily.temperature_2m_max[i]);
        lows.push(daily.temperature_2m_min[i]);
    }
    
    /* Find min/max for scaling */
    var allTemps = highs.concat(lows);
    var minTemp = Math.min.apply(null, allTemps);
    var maxTemp = Math.max.apply(null, allTemps);
    var range = maxTemp - minTemp;
    if(range < 2) { minTemp -= 1; maxTemp += 1; range = 2; }
    var padding = range * 0.1;
    minTemp -= padding;
    maxTemp += padding;
    range = maxTemp - minTemp;
    
    function yFromTemp(t) { return pad.top + chartH - ((t - minTemp) / range) * chartH; }
    function xFromIdx(idx) { return pad.left + (idx / (n - 1)) * chartW; }
    
    /* Clear */
    ctx.clearRect(0, 0, W, H);
    
    /* Detect dark mode */
    var isDark = document.body.classList.contains('dark-theme') || 
                 getComputedStyle(document.body).getPropertyValue('--bg-color')?.trim()?.startsWith('#1') ||
                 false;
    var textColor = isDark ? '#94a3b8' : '#64748b';
    var gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
    
    /* ── Draw horizontal grid lines ── */
    ctx.strokeStyle = gridColor;
    ctx.lineWidth = 1;
    ctx.setLineDash([4, 4]);
    var gridSteps = 5;
    for(var g = 0; g <= gridSteps; g++) {
        var y = pad.top + (g / gridSteps) * chartH;
        ctx.beginPath();
        ctx.moveTo(pad.left, y);
        ctx.lineTo(W - pad.right, y);
        ctx.stroke();
        
        /* Temperature labels */
        var tempVal = maxTemp - (g / gridSteps) * range;
        ctx.fillStyle = textColor;
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        ctx.fillText(Math.round(tempVal) + '°', pad.left - 6, y);
    }
    ctx.setLineDash([]);
    
    /* ── Draw day labels on x-axis ── */
    ctx.fillStyle = textColor;
    ctx.font = '11px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    for(var d = 0; d < n; d++) {
        var x = xFromIdx(d);
        var dayLabel = getDayName(daily.time[d]);
        ctx.fillText(dayLabel, x, H - pad.bottom + 8);
    }
    
    /* ── Helper: draw a curved line ── */
    function drawCurvedLine(data, color, fillColor) {
        ctx.beginPath();
        for(var i = 0; i < n; i++) {
            var x = xFromIdx(i);
            var y = yFromTemp(data[i]);
            if(i === 0) {
                ctx.moveTo(x, y);
            } else {
                var prevX = xFromIdx(i - 1);
                var prevY = yFromTemp(data[i - 1]);
                var cpX1 = (prevX + x) / 2;
                var cpX2 = (prevX + x) / 2;
                ctx.bezierCurveTo(cpX1, prevY, cpX2, y, x, y);
            }
        }
        
        /* Fill area under curve */
        if(fillColor) {
            var lastX = xFromIdx(n - 1);
            var lastY = yFromTemp(data[n - 1]);
            ctx.lineTo(lastX, pad.top + chartH);
            ctx.lineTo(xFromIdx(0), pad.top + chartH);
            ctx.closePath();
            ctx.fillStyle = fillColor;
            ctx.fill();
            
            /* Redraw the line on top */
            ctx.beginPath();
            for(var j = 0; j < n; j++) {
                var x2 = xFromIdx(j);
                var y2 = yFromTemp(data[j]);
                if(j === 0) {
                    ctx.moveTo(x2, y2);
                } else {
                    var px2 = xFromIdx(j - 1);
                    var py2 = yFromTemp(data[j - 1]);
                    ctx.bezierCurveTo((px2 + x2) / 2, py2, (px2 + x2) / 2, y2, x2, y2);
                }
            }
        }
        
        ctx.strokeStyle = color;
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.stroke();
    }
    
    /* ── Draw low temp area (behind high) ── */
    drawCurvedLine(lows, '#3b82f6', 'rgba(59,130,246,0.10)');
    
    /* ── Draw high temp line ── */
    drawCurvedLine(highs, '#ef4444', 'rgba(239,68,68,0.08)');
    
    /* ── Draw data points ── */
    function drawPoints(data, color) {
        for(var i = 0; i < n; i++) {
            var x = xFromIdx(i);
            var y = yFromTemp(data[i]);
            ctx.beginPath();
            ctx.arc(x, y, 4, 0, Math.PI * 2);
            ctx.fillStyle = '#fff';
            ctx.fill();
            ctx.strokeStyle = color;
            ctx.lineWidth = 2.5;
            ctx.stroke();
            
            /* Temperature value above point */
            ctx.fillStyle = color;
            ctx.font = 'bold 10px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(Math.round(data[i]) + '°', x, y - 8);
        }
    }
    drawPoints(lows, '#3b82f6');
    drawPoints(highs, '#ef4444');
}

/* ── Open weather settings ── */
function openWeatherSettings() {
    console.log('Weather settings clicked');
    // TODO: Implement settings modal or redirect
    alert('Weather settings will be implemented here');
}

/* ── Wind chart toggle & draw ── */
function toggleWindChart() {
    var btn = document.getElementById('weatherWindChartBtn');
    if (!windChartVisible) {
        windChartVisible = true;
        drawWindChart(weatherStore.daily);
        if (btn) btn.title = T.weather_wind_chart_back || 'Back to forecast';
    } else {
        windChartVisible = false;
        renderWeeklyForecast();
        if (btn) btn.title = T.weather_wind_chart_btn || 'Show wind chart';
    }
}
function drawWindChart(daily) {
    if (!daily || !daily.time || !daily.windspeed_10m_max) return;
    var n = daily.time.length;
    var winds = [];
    for (var i = 0; i < n; i++) {
        winds.push(daily.windspeed_10m_max[i] != null ? daily.windspeed_10m_max[i] : 0);
    }
    var isDark = document.body.classList.contains('dark-theme') ||
                 getComputedStyle(document.body).getPropertyValue('--bg-color')?.trim()?.startsWith('#1') ||
                 false;
    var bg = isDark ? '#1e293b' : '#ffffff';
    var textColor = isDark ? '#94a3b8' : '#64748b';
    var gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
    var lineColor = '#10b981';
    var fillColor = 'rgba(16,185,129,0.10)';
    var minW = Math.min.apply(null, winds);
    var maxW = Math.max.apply(null, winds);
    var range = maxW - minW;
    if (range < 2) { minW = Math.max(0, minW - 1); maxW = maxW + 1; range = maxW - minW; }
    var padVal = range * 0.15;
    minW = Math.max(0, minW - padVal);
    maxW += padVal;
    range = maxW - minW;
    var vw = 640, vh = 280;
    var pad = { top: 40, right: 20, bottom: 60, left: 50 };
    var cW = vw - pad.left - pad.right;
    var cH = vh - pad.top - pad.bottom;
    function yP(v) { return pad.top + cH - ((v - minW) / range) * cH; }
    function xP(idx) { return n <= 1 ? pad.left + cW / 2 : pad.left + (idx / (n - 1)) * cW; }
    var title = T.weather_wind_chart_title || 'Wind speed';
    var unit = T.weather_wind_speed_unit || 'km/h';
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + vw + ' ' + vh + '" style="width:100%;height:auto;display:block;font-family:sans-serif;">';
    svg += '<rect width="' + vw + '" height="' + vh + '" fill="' + bg + '" rx="12"/>';
    svg += '<text x="' + (vw / 2) + '" y="24" text-anchor="middle" fill="' + textColor + '" font-size="14" font-weight="600">' + escHtml(title) + '</text>';
    var gridSteps = 4;
    for (var g = 0; g <= gridSteps; g++) {
        var gy = pad.top + (g / gridSteps) * cH;
        var gVal = maxW - (g / gridSteps) * range;
        svg += '<line x1="' + pad.left + '" y1="' + gy + '" x2="' + (vw - pad.right) + '" y2="' + gy + '" stroke="' + gridColor + '" stroke-width="1" stroke-dasharray="4,4"/>';
        svg += '<text x="' + (pad.left - 6) + '" y="' + gy + '" text-anchor="end" dominant-baseline="middle" fill="' + textColor + '" font-size="10">' + Math.round(gVal) + '</text>';
    }
    var pts = [];
    for (var i = 0; i < n; i++) {
        pts.push({ x: xP(i), y: yP(winds[i]) });
    }
    var fillPath = 'M' + pts[0].x + ',' + pts[0].y;
    for (var i = 1; i < n; i++) {
        var cx1 = (pts[i - 1].x + pts[i].x) / 2;
        fillPath += ' C' + cx1 + ',' + pts[i - 1].y + ' ' + cx1 + ',' + pts[i].y + ' ' + pts[i].x + ',' + pts[i].y;
    }
    fillPath += ' L' + pts[n - 1].x + ',' + (pad.top + cH) + ' L' + pts[0].x + ',' + (pad.top + cH) + ' Z';
    svg += '<path d="' + fillPath + '" fill="' + fillColor + '"/>';
    var linePath = 'M' + pts[0].x + ',' + pts[0].y;
    for (var i = 1; i < n; i++) {
        var cx1 = (pts[i - 1].x + pts[i].x) / 2;
        linePath += ' C' + cx1 + ',' + pts[i - 1].y + ' ' + cx1 + ',' + pts[i].y + ' ' + pts[i].x + ',' + pts[i].y;
    }
    svg += '<path d="' + linePath + '" fill="none" stroke="' + lineColor + '" stroke-width="3" stroke-linejoin="round"/>';
    for (var i = 0; i < n; i++) {
        svg += '<circle cx="' + pts[i].x + '" cy="' + pts[i].y + '" r="4" fill="#fff" stroke="' + lineColor + '" stroke-width="2.5"/>';
        svg += '<text x="' + pts[i].x + '" y="' + (pts[i].y - 10) + '" text-anchor="middle" fill="' + lineColor + '" font-size="10" font-weight="bold">' + Math.round(winds[i]) + '</text>';
        var dayLabel = getDayName(daily.time[i]);
        svg += '<text x="' + pts[i].x + '" y="' + (pad.top + cH + 18) + '" text-anchor="middle" fill="' + textColor + '" font-size="10">' + escHtml(dayLabel) + '</text>';
    }
    svg += '</svg>';
    var unitLabel = '<div style="text-align:center;margin-top:6px;font-size:12px;opacity:0.6">' + escHtml(unit) + '</div>';
    modalContent.innerHTML = '<div style="padding:16px 8px;">' + svg + unitLabel + '</div>';
}

/* ── Main init ── */
function initWeather() {
    if(!navigator.geolocation) {
        showWeatherError(T.weather_no_geo || 'Geolocation not supported', false);
        return;
    }
    
    showWeatherLoading();
    
    var timeoutId = setTimeout(function() {
        showWeatherError(T.weather_timeout || 'Weather request timed out', false);
    }, 15000);
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            clearTimeout(timeoutId);
            var lat = position.coords.latitude;
            var lon = position.coords.longitude;
            weatherStore.lat = lat;
            weatherStore.lon = lon;
            
            /* Fetch current + 7-day forecast from Open-Meteo */
            var weatherUrl = 'https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lon +
                '&current=temperature_2m,weathercode,windspeed,is_day' +
                '&daily=weathercode,temperature_2m_max,temperature_2m_min,precipitation_sum,windspeed_10m_max' +
                '&timezone=auto&forecast_days=7';
            
            fetch(weatherUrl)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var cur = data.current_weather || data.current;
                    if(!data || !cur) {
                        showWeatherError(T.weather_no_data || 'No weather data', false);
                        return;
                    }
                    var cw = data.current_weather || data.current;
                    weatherStore.current = cw;
                    weatherStore.daily = data.daily || null;
                    
                    var temp = cw.temperature || cw.temperature_2m;
                    var windSpeed = cw.windspeed;
                    var code = cw.weathercode || cw.weather_code;
                    var isDay = (cw.is_day !== undefined) ? cw.is_day !== 0 : 1;
                    
                    /* Reverse geocode for city name */
                    var geoUrl = 'https://geocoding-api.open-meteo.com/v1/reverse?latitude=' + lat + '&longitude=' + lon + '&language=en';
                    fetch(geoUrl)
                        .then(function(r2) { return r2.json(); })
                        .then(function(geo) {
                            var city = '';
                            if(geo && geo.results && geo.results.length > 0) {
                                city = geo.results[0].name || '';
                                if(geo.results[0].country) {
                                    city += ', ' + geo.results[0].country;
                                }
                            }
                            if(!city) {
                                city = T.weather_unknown || 'Unknown location';
                            }
                            weatherStore.city = city;
                            renderWeather(city, temp, windSpeed, code, isDay);
                        })
                        .catch(function() {
                            weatherStore.city = T.weather_unknown || 'Unknown location';
                            renderWeather(weatherStore.city, temp, windSpeed, code, isDay);
                        });
                })
                .catch(function() {
                    showWeatherError(T.weather_fetch_error || 'Failed to load weather', false);
                });
        },
        function(error) {
            clearTimeout(timeoutId);
            if(error.code === error.PERMISSION_DENIED) {
                showWeatherError(T.weather_denied || 'Location access denied. Enable it to see local weather.', true);
            } else if(error.code === error.TIMEOUT) {
                showWeatherError(T.weather_timeout || 'Location request timed out', false);
            } else {
                showWeatherError(T.weather_no_geo || 'Could not determine location', false);
            }
        },
        {
            enableHighAccuracy: false,
            timeout: 10000,
            maximumAge: 300000
        }
    );
}

})();
</script>
</body>
</html>
