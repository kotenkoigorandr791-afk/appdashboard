<?php
header('Content-Type: text/css; charset=utf-8');
$theme = (($_GET['theme'] ?? 'light') === 'dark') ? 'dark' : 'light';
$color = rawurldecode((string) ($_GET['color'] ?? '4299e1'));
$color = str_replace('#', '', $color);
if (!preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
    $color = '4299e1';
}
$isFullscreen = isset($_GET['fullscreen']) && $_GET['fullscreen'] === '1';
$isFolder = isset($_GET['folder']) && $_GET['folder'] === '1';
$bgImage = '../images/background_' . ($theme === 'dark' ? 'dark' : 'light') . '_opt.jpg';
$glassViewerBg = $theme === 'dark' ? 'rgba(10,10,10,0.38)' : 'rgba(255,255,255,0.52)';
$glassPanelBg = $theme === 'dark' ? 'rgba(10,10,10,0.58)' : 'rgba(255,255,255,0.78)';
$glassBlur = 'blur(18px) saturate(1.25)';
?>

* {margin:0;padding:0;box-sizing:border-box;}
html,body {height:100%;overflow:hidden;}

body::before {
    content:'';
    position:fixed;
    inset:0;
    z-index:0;
    pointer-events:none;
    background-image:url('<?php echo htmlspecialchars($bgImage, ENT_QUOTES); ?>');
    background-size:cover;
    background-position:center;
    background-repeat:no-repeat;
    opacity:1;
}
body {font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;color:<?php echo $theme==='dark'?'#e0e0e0':'#333333'; ?>;-webkit-text-size-adjust:100%;position:relative;overflow:hidden;background:transparent;}
<?php if (($fileData['disable_copy'] ?? 0) === 1 || ($fileData['view_only'] ?? 0) === 1): ?>.viewer-container,.viewer-container *{-webkit-user-select:none;user-select:none;}<?php endif; ?>

<?php if ($isFullscreen): ?>body.fullscreen-mode {background:<?php echo $theme==='dark'?'#000':'#fff'; ?>;overflow:auto;}<?php endif; ?>
.content-wrapper {display:flex;flex-direction:column;height:100vh;padding:0;gap:0;position:relative;z-index:1;}
.content-wrapper > * {margin:0 !important;}
<?php if ($isFullscreen): ?>.content-wrapper.fullscreen {padding:0;height:100%;}.header.fullscreen {position:fixed;top:0;left:0;right:0;z-index:1000;background:<?php echo $theme==='dark'?'rgba(0,0,0,0.9)':'rgba(255,255,255,0.95)'; ?>;backdrop-filter:blur(10px);border-radius:0;border:none;border-bottom:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.08)'; ?>;padding:8px 16px;}.viewer-container.fullscreen {flex:1;border-radius:0;border:none;margin-top:60px;height:calc(100vh - 100px);}.file-info.fullscreen,.footer.fullscreen {display:none;}.image-controls {position:absolute;bottom:20px;left:50%;transform:translateX(-50%);display:flex;gap:8px;background:<?php echo $theme==='dark'?'rgba(0,0,0,0.8)':'rgba(255,255,255,0.9)'; ?>;padding:8px 12px;border-radius:20px;backdrop-filter:blur(10px);border:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.2)':'rgba(0,0,0,0.1)'; ?>;z-index:10;}<?php endif; ?>
.header {display:flex;flex-direction:column;gap:0;padding:0;background:<?php echo $glassPanelBg; ?>;backdrop-filter:<?php echo $glassBlur; ?>;-webkit-backdrop-filter:<?php echo $glassBlur; ?>;border-radius:0;border:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.25)':'rgba(0,0,0,0.18)'; ?>;border-bottom:none;}
@media (min-width: 768px) {.header {flex-direction:row;justify-content:space-between;align-items:center;}}
.title-section {display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.08)'; ?>;}
@media (min-width: 768px) {.title-section {border-bottom:none;border-right:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.08)'; ?>;}}
.file-title {font-size:16px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:300px;}
.file-badge {padding:4px 10px;border-radius:4px;font-size:11px;font-weight:600;background:rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.7);text-transform:uppercase;letter-spacing:0.5px;}
.actions {display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:8px;}
.btn {border:none;border-radius:50px;padding:10px;font-size:13px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all 0.2s ease;background:rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.3);color:inherit;border:1px solid rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.5);text-decoration:none;width:40px;height:40px;justify-content:center;}
.btn-outline {background:rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.3);color:inherit;border:1px solid rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.5);}
.btn:hover {background:rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.5);transform:translateY(-2px);box-shadow:0 4px 12px rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.4);}
.btn svg {opacity:0.9;}
.file-info {display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:<?php echo $glassPanelBg; ?>;backdrop-filter:<?php echo $glassBlur; ?>;-webkit-backdrop-filter:<?php echo $glassBlur; ?>;border-radius:0;border:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.25)':'rgba(0,0,0,0.18)'; ?>;border-top:none;border-bottom:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.14)':'rgba(0,0,0,0.1)'; ?>;font-size:12px;box-shadow:0 1px 0 <?php echo $theme==='dark'?'rgba(255,255,255,0.05)':'rgba(255,255,255,0.55)'; ?>;}
.info-group {display:flex;gap:20px;}
.info-label {opacity:0.6;font-weight:500;}
.viewer-container {flex:1;border-radius:0;overflow:hidden;background:<?php echo $glassViewerBg; ?>;backdrop-filter:<?php echo $glassBlur; ?>;-webkit-backdrop-filter:<?php echo $glassBlur; ?>;border:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.25)':'rgba(0,0,0,0.18)'; ?>;border-top:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.08)'; ?>;position:relative;}
.image-viewer {width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:auto;padding:20px;background:transparent;}
.viewer-image {max-width:100%;max-height:100%;object-fit:contain;cursor:zoom-in;border-radius:8px;transition:transform 0.3s ease;}
.viewer-image.zoomed {cursor:zoom-out;}
.image-controls {position:absolute;bottom:20px;left:50%;transform:translateX(-50%);display:flex;gap:8px;background:<?php echo $glassPanelBg; ?>;backdrop-filter:<?php echo $glassBlur; ?>;-webkit-backdrop-filter:<?php echo $glassBlur; ?>;padding:8px 12px;border-radius:20px;border:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.35)':'rgba(0,0,0,0.25)'; ?>;z-index:10;}
.control-btn {background:none;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:13px;color:inherit;display:flex;align-items:center;gap:4px;}
.control-btn:hover {background:<?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.05)'; ?>;}
.monaco-container {width:100%;height:100%;position:relative;background:transparent;}
.monaco-container .monaco-editor,
.monaco-container .monaco-editor .monaco-editor-background,
.monaco-container .monaco-editor .margin,
.monaco-container .monaco-editor .minimap,
.monaco-container .monaco-editor .minimap-shadow-hidden {background-color:transparent!important;}
.monaco-container .monaco-editor .current-line {border:none!important;background-color:<?php echo $theme==='dark'?'rgba(255,255,255,0.06)':'rgba(0,0,0,0.04)'; ?>!important;}
.markdown-preview-container {width:100%;height:100%;overflow-y:auto;padding:20px;background:transparent;}
.markdown-preview-content {max-width:900px;margin:0 auto;padding:20px;background:<?php echo $theme==='dark'?'rgba(255,255,255,0.02)':'rgba(0,0,0,0.01)'; ?>;border-radius:8px;color:<?php echo $theme==='dark'?'#e0e0e0':'#333333'; ?>;}
.markdown-preview-content h1{color:#<?php echo $color; ?>;border-bottom:2px solid #<?php echo $color; ?>;padding-bottom:5px;margin:20px 0 15px 0;font-size:1.8rem;}
.markdown-preview-content h2{color:#<?php echo $color; ?>;opacity:0.9;margin:18px 0 12px 0;font-size:1.5rem;}
.markdown-preview-content h3{color:#<?php echo $color; ?>;opacity:0.8;margin:16px 0 10px 0;font-size:1.3rem;}
.markdown-preview-content h4{color:#<?php echo $color; ?>;opacity:0.7;margin:14px 0 8px 0;font-size:1.1rem;}
.markdown-preview-content p{margin:10px 0;line-height:1.6;color:<?php echo $theme==='dark'?'#d4d4d4':'#333333'; ?>;}
.markdown-preview-content strong{color:<?php echo $theme==='dark'?'#d4d4d4':'#000'; ?>;}
.markdown-preview-content em{color:<?php echo $theme==='dark'?'#ce9178':'#a31515'; ?>;}
.markdown-preview-content code{background:<?php echo $theme==='dark'?'#2d2d2d':'#f5f5f5'; ?>;color:<?php echo $theme==='dark'?'#e0e0e0':'#333'; ?>;padding:2px 6px;border-radius:3px;font-family:'Consolas',monospace;font-size:0.9em;}
.markdown-preview-content pre{background:<?php echo $theme==='dark'?'#2d2d2d':'#f5f5f5'; ?>;padding:12px;border-radius:4px;overflow-x:auto;margin:10px 0;border-left:4px solid #<?php echo $color; ?>;}
.markdown-preview-content pre code{background:none;padding:0;color:<?php echo $theme==='dark'?'#d4d4d4':'#333333'; ?>;}
.markdown-preview-content blockquote{border-left:4px solid #<?php echo $color; ?>;padding-left:15px;margin:12px 0;color:<?php echo $theme==='dark'?'#aaa':'#666'; ?>;font-style:italic;background:<?php echo $theme==='dark'?'rgba(255,255,255,0.03)':'rgba(0,0,0,0.03)'; ?>;padding:10px 15px;border-radius:0 4px 4px 0;}
.markdown-preview-content ul,.markdown-preview-content ol{margin-left:20px;margin-bottom:10px;color:<?php echo $theme==='dark'?'#d4d4d4':'#333333'; ?>;}
.markdown-preview-content li{margin:5px 0;}
.markdown-preview-content a{color:#<?php echo $color; ?>;text-decoration:none;}
.markdown-preview-content a:hover{text-decoration:underline;opacity:0.9;}
.markdown-preview-content table{border-collapse:collapse;width:100%;margin:12px 0;border:1px solid <?php echo $theme==='dark'?'#404040':'#ddd'; ?>;}
.markdown-preview-content th{background:<?php echo $theme==='dark'?'#252526':'#f0f0f0'; ?>;color:#<?php echo $color; ?>;padding:8px;border:1px solid <?php echo $theme==='dark'?'#404040':'#ddd'; ?>;}
.markdown-preview-content td{padding:8px;border:1px solid <?php echo $theme==='dark'?'#404040':'#ddd'; ?>;color:<?php echo $theme==='dark'?'#d4d4d4':'#333333'; ?>;}
.markdown-preview-content hr{border:none;height:2px;background:#<?php echo $color; ?>;margin:20px 0;opacity:0.5;}
.markdown-preview-content img{max-width:100%;border-radius:4px;margin:10px 0;border:1px solid <?php echo $theme==='dark'?'#404040':'#ddd'; ?>;}
.markdown-preview-content .math{color:#<?php echo $color; ?>;font-weight:bold;}
.markdown-preview-content .MathJax{color:#<?php echo $color; ?> !important;outline:none;}
.markdown-preview-content mjx-container{color:#<?php echo $color; ?> !important;margin:0 2px;}
.markdown-view-toggle{position:absolute;top:20px;right:20px;z-index:100;display:flex;gap:4px;background:<?php echo $glassPanelBg; ?>;padding:4px;border-radius:8px;backdrop-filter:<?php echo $glassBlur; ?>;-webkit-backdrop-filter:<?php echo $glassBlur; ?>;border:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.2)':'rgba(0,0,0,0.1)'; ?>;}
.markdown-view-toggle button{background:none;border:none;padding:8px 12px;border-radius:6px;cursor:pointer;color:inherit;display:flex;align-items:center;justify-content:center;transition:all 0.2s ease;}
.markdown-view-toggle button:hover{background:<?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.05)'; ?>;}
.markdown-view-toggle button.active{background:#<?php echo $color; ?>;color:#fff;}
.video-viewer,.audio-viewer {width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:transparent;}
.onlyoffice-viewer {width:100%;height:100%;position:relative;background:transparent;}
video,audio {max-width:90%;max-height:90%;}

/* PDF.js viewer styles - улучшенная версия */
.pdf-viewer-container {
    width: 100%;
    height: 100%;
    overflow: auto;
    background: transparent;
    position: relative;
    scrollbar-width: thin;
    scrollbar-color: <?php echo $theme==='dark'?'#666 #333':'#999 #ccc'; ?>;
}
.pdf-viewer-container::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}
.pdf-viewer-container::-webkit-scrollbar-track {
    background: <?php echo $theme==='dark'?'#333':'#ccc'; ?>;
}
.pdf-viewer-container::-webkit-scrollbar-thumb {
    background: <?php echo $theme==='dark'?'#666':'#999'; ?>;
    border-radius: 5px;
}
.pdf-viewer-container::-webkit-scrollbar-thumb:hover {
    background: <?php echo $theme==='dark'?'#888':'#666'; ?>;
}
.pdf-viewer {
    position: relative;
    margin: 0 auto;
    padding: 20px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 100%;
}
.page-wrapper {
    position: relative;
    margin: 10px auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    background: #fff;
    border-radius: 4px;
    overflow: hidden;
}
.pdf-controls {
    position: sticky;
    top: 0;
    left: 0;
    width: 100%;
    background: <?php echo $glassPanelBg; ?>;
    backdrop-filter: <?php echo $glassBlur; ?>;
    -webkit-backdrop-filter: <?php echo $glassBlur; ?>;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    border-bottom: 1px solid <?php echo $theme==='dark'?'#444':'#ddd'; ?>;
    box-shadow: 0 12px 28px <?php echo $theme==='dark'?'rgba(0,0,0,0.32)':'rgba(0,0,0,0.12)'; ?>;
    z-index: 10;
    flex-wrap: wrap;
}
.content-wrapper.with-sidebar .pdf-controls {
    padding: 8px;
    gap: 8px;
}
.content-wrapper.with-sidebar .toolbar-group {
    gap: 4px;
    padding: 0 6px;
}
.content-wrapper.with-sidebar .control-btn {
    padding: 5px 8px;
}
.content-wrapper.with-sidebar .pdf-controls input[type="text"] {
    width: 44px;
    padding: 5px 3px;
}
.content-wrapper.with-sidebar .zoom-select-container {
    min-width: 72px;
}
.content-wrapper.with-sidebar .zoom-select {
    padding: 5px 20px 5px 7px;
}
.toolbar-group {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0 10px;
    border-right: 1px solid <?php echo $theme==='dark'?'#444':'#ddd'; ?>;
}
.toolbar-group:last-child {
    border-right: 0;
}
.download-file-btn {
    width: auto;
    min-width: 140px;
    padding: 10px 16px;
}
.pdf-controls input[type="text"] {
    width: 50px;
    text-align: center;
    padding: 6px 4px;
    border-radius: 4px;
    border: 1px solid <?php echo $theme==='dark'?'#444':'#ddd'; ?>;
    background: <?php echo $theme==='dark'?'#2a2a2a':'#fff'; ?>;
    color: inherit;
    font-size: 14px;
}
.pdf-controls input[type="range"] {
    width: 120px;
    cursor: pointer;
    background: <?php echo $theme==='dark'?'#444':'#ddd'; ?>;
    height: 4px;
    border-radius: 2px;
    -webkit-appearance: none;
}
.pdf-controls input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #<?php echo $color; ?>;
    cursor: pointer;
}
.zoom-select-container {
    position: relative;
    display: inline-flex;
    align-items: center;
    min-width: 80px;
}
.zoom-select {
    appearance: none;
    -webkit-appearance: none;
    background: <?php echo $theme==='dark'?'#2a2a2a':'#f5f5f5'; ?>;
    border: 1px solid <?php echo $theme==='dark'?'#444':'#ddd'; ?>;
    border-radius: 4px;
    color: inherit;
    font-size: 13px;
    padding: 6px 24px 6px 8px;
    cursor: pointer;
    width: 100%;
    outline: none;
}
.zoom-select:hover {
    border-color: #<?php echo $color; ?>;
}
.zoom-select-arrow {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    width: 14px;
    height: 14px;
    pointer-events: none;
    fill: none;
    stroke: <?php echo $theme==='dark'?'#aaa':'#666'; ?>;
}
.progress-container {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #1a1a1a;
    z-index: 1000;
    overflow: hidden;
}
.progress-bar {
    height: 100%;
    background: #<?php echo $color; ?>;
    width: 0%;
    transition: width 0.3s ease;
    box-shadow: 0 0 5px #<?php echo $color; ?>;
}
.progress-text {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #fff;
    font-size: 32px;
    font-weight: 300;
    opacity: 0.8;
    z-index: 1001;
    pointer-events: none;
    text-shadow: 0 2px 8px rgba(0,0,0,0.5);
}
.pdf-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

/* 3D model viewer styles - улучшенная версия */
.model-viewer-container {
    width: 100%;
    height: 100%;
    position: relative;
    overflow: hidden;
    background: transparent;
}
#model-canvas {
    width: 100%;
    height: 100%;
    display: block;
    outline: none;
}
.model-controls {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    background: <?php echo $theme==='dark'?'rgba(30,30,30,0.9)':'rgba(255,255,255,0.9)'; ?>;
    padding: 8px 16px;
    border-radius: 30px;
    backdrop-filter: blur(10px);
    border: 1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.2)':'rgba(0,0,0,0.1)'; ?>;
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.model-controls .control-btn {
    background: none;
    border: none;
    padding: 8px 14px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 13px;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.model-controls .control-btn:hover {
    background: <?php echo $theme==='dark'?'rgba(255,255,255,0.2)':'rgba(0,0,0,0.1)'; ?>;
    transform: translateY(-2px);
}
.model-info {
    position: absolute;
    top: 20px;
    right: 20px;
    background: <?php echo $theme==='dark'?'rgba(0,0,0,0.7)':'rgba(255,255,255,0.7)'; ?>;
    padding: 8px 16px;
    border-radius: 20px;
    backdrop-filter: blur(10px);
    font-size: 12px;
    color: <?php echo $theme==='dark'?'#fff':'#333'; ?>;
    z-index: 10;
}
.spinner {border:3px solid <?php echo $theme==='dark'?'#333':'#f3f3f3'; ?>;border-top:3px solid #<?php echo $color; ?>;border-radius:50%;width:40px;height:40px;animation:spin 2s linear infinite;margin:0 auto 15px;}@keyframes spin {0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
.error-view {display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:20px;padding:40px;text-align:center;background:transparent;}
.error-icon {width:64px;height:64px;opacity:0.5;}
.footer {padding:8px 0;text-align:center;font-size:12px;color:<?php echo $theme==='dark'?'rgba(255,255,255,0.6)':'rgba(0,0,0,0.5)'; ?>;border-top:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.08)'; ?>;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;}
.footer-content {display:flex;align-items:center;justify-content:center;gap:16px;width:100%;}
.social-links {display:flex;gap:12px;}
.social-link {display:flex;align-items:center;gap:4px;color:inherit;text-decoration:none;font-weight:500;transition:all 0.2s ease;padding:4px 8px;border-radius:6px;font-size:12px;}
.social-link:hover {background:<?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.06)'; ?>;transform:translateY(-1px);}
.settings-modal,.info-modal {position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);backdrop-filter:blur(5px);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px;}
.settings-content,.info-content {background:<?php echo $theme==='dark'?'#1a1a1a':'#fff'; ?>;border-radius:16px;padding:24px;width:100%;max-width:500px;max-height:80vh;overflow-y:auto;border:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.1)'; ?>;}
.settings-title,.info-title {font-size:18px;font-weight:600;margin-bottom:20px;text-align:center;}
.settings-row {margin-bottom:16px;}
.settings-row label {display:block;margin-bottom:6px;font-weight:500;font-size:14px;}
.settings-row select,.settings-row input[type="color"] {width:100%;padding:10px;border-radius:8px;border:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.2)':'rgba(0,0,0,0.15)'; ?>;background:<?php echo $theme==='dark'?'#2a2a2a':'#f5f5f5'; ?>;color:inherit;font-size:14px;}
.settings-actions,.info-actions {display:flex;justify-content:flex-end;gap:12px;margin-top:24px;}
.settings-actions .btn,.info-actions .btn {width:auto;height:auto;min-height:40px;padding:10px 16px;border-radius:8px;}
.features-list {display:flex;flex-direction:column;gap:12px;}
.feature-item {display:flex;align-items:flex-start;gap:10px;padding:12px;background:<?php echo $theme==='dark'?'rgba(255,255,255,0.05)':'rgba(0,0,0,0.03)'; ?>;border-radius:8px;border:1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.08)'; ?>;}
.feature-icon {color:#<?php echo $color; ?>;flex-shrink:0;margin-top:2px;}
.feature-text {flex:1;}
.feature-category {font-size:16px;font-weight:600;margin-bottom:12px;color:#<?php echo $color; ?>;padding-bottom:6px;border-bottom:2px solid rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.2);}
/* Toast уведомление в стиле цитаты */
.toast-notification {
    position: fixed;
    bottom: 24px;
    right: 24px;
    max-width: 320px;
    background: <?php echo $theme==='dark' ? '#2d2d2d' : '#ffffff'; ?>;
    border-left: 4px solid #<?php echo $color; ?>;
    border-radius: 8px;
    padding: 16px 20px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
    transform: translateX(400px);
    opacity: 0;
    transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55), opacity 0.2s ease;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    pointer-events: none;
    backdrop-filter: blur(10px);
    border: 1px solid <?php echo $theme==='dark' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)'; ?>;
}

.toast-notification.show {
    transform: translateX(0);
    opacity: 1;
    pointer-events: auto;
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.toast-icon {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #<?php echo $color; ?>;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.3);
}

.toast-message {
    flex: 1;
    font-size: 14px;
    font-weight: 500;
    color: <?php echo $theme==='dark' ? '#e0e0e0' : '#333333'; ?>;
    line-height: 1.5;
    word-break: break-word;
}

.toast-close {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border: none;
    background: none;
    cursor: pointer;
    color: <?php echo $theme==='dark' ? '#888' : '#999'; ?>;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
}

.toast-close:hover {
    color: #<?php echo $color; ?>;
}

.toast-close svg {
    width: 18px;
    height: 18px;
    fill: currentColor;
}

/* Анимация появления */
@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}

.toast-notification.slide-out {
    animation: slideOut 0.3s ease forwards;
}

/* Folder sidebar styles */
.content-wrapper.with-sidebar {
    flex-direction: row;
    gap: 0;
}

.folder-sidebar {
    width: clamp(280px, 35vw, 400px);
    flex: 0 0 clamp(280px, 35vw, 400px);
    background: transparent;
    border-right: 1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.25)':'rgba(0,0,0,0.18)'; ?>;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.sidebar-resizer {
    flex: 0 0 8px;
    width: 8px;
    cursor: col-resize;
    touch-action: none;
    border-right: 1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.18)':'rgba(0,0,0,0.14)'; ?>;
    border-left: 1px solid transparent;
}
.sidebar-resizer:hover,.sidebar-resizer.dragging {
    background: rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.24);
    border-left-color: rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.45);
    border-right-color: rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.45);
}

.sidebar-header {
    padding: 16px;
    border-bottom: 1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.25)':'rgba(0,0,0,0.18)'; ?>;
}

.sidebar-header h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 8px;
    color: <?php echo $theme==='dark'?'#e0e0e0':'#333'; ?>;
}

.breadcrumb {
    font-size: 12px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}

.breadcrumb a {
    color: #<?php echo $color; ?>;
    text-decoration: none;
    transition: opacity 0.2s;
}

.breadcrumb a:hover {
    opacity: 0.7;
}

.breadcrumb-separator {
    color: <?php echo $theme==='dark'?'rgba(255,255,255,0.3)':'rgba(0,0,0,0.3)'; ?>;
    margin: 0 2px;
}

.file-count {
    font-size: 12px;
    color: <?php echo $theme==='dark'?'rgba(255,255,255,0.5)':'rgba(0,0,0,0.5)'; ?>;
}

.file-list {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}

.file-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.file-table thead {
    position: sticky;
    top: 0;
    background: transparent;
    z-index: 1;
}

.file-table th {
    text-align: left;
    padding: 10px 12px;
    font-weight: 600;
    color: <?php echo $theme==='dark'?'rgba(255,255,255,0.7)':'rgba(0,0,0,0.7)'; ?>;
    border-bottom: 1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.08)'; ?>;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.file-row {
    transition: all 0.2s;
    border-bottom: 1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.05)':'rgba(0,0,0,0.05)'; ?>;
}

.file-row.is-file {
    cursor: pointer;
}

.file-row.is-file:hover {
    background: rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.12);
}

.file-row.is-folder {
    cursor: pointer;
}

.file-row.is-folder:hover {
    background: rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.12);
}

.file-row.selected {
    background: rgba(<?php echo hexdec(substr($color,0,2)); ?>, <?php echo hexdec(substr($color,2,2)); ?>, <?php echo hexdec(substr($color,4,2)); ?>, 0.18);
    border-left: 3px solid #<?php echo $color; ?>;
}

.file-row td {
    padding: 10px 12px;
    color: <?php echo $theme==='dark'?'#e0e0e0':'#333'; ?>;
}

.file-name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.file-icon {
    flex-shrink: 0;
    color: <?php echo $theme==='dark'?'rgba(255,255,255,0.6)':'rgba(0,0,0,0.5)'; ?>;
    display: flex;
    align-items: center;
}

.file-row.is-folder .file-icon {
    color: #<?php echo $color; ?>;
}

.file-name {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-size-cell {
    color: <?php echo $theme==='dark'?'rgba(255,255,255,0.5)':'rgba(0,0,0,0.5)'; ?>;
    white-space: nowrap;
    width: 80px;
}

.file-date-cell {
    color: <?php echo $theme==='dark'?'rgba(255,255,255,0.5)':'rgba(0,0,0,0.5)'; ?>;
    white-space: nowrap;
    width: 130px;
    font-size: 12px;
}

.main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}


/* Mobile responsive */
@media (max-width: 560px) {
    .content-wrapper.with-sidebar {
        flex-direction: column;
        height: 100dvh;
    }

    .folder-sidebar {
        width: 100%;
        flex: 0 0 auto;
        flex-basis: auto;
        max-height: 34dvh;
        border-right: none;
        border-bottom: 1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.1)':'rgba(0,0,0,0.08)'; ?>;
    }
    .sidebar-header {
        padding: 10px 12px;
    }
    .sidebar-header h3 {
        font-size: 14px;
        margin-bottom: 4px;
    }
    .file-count,.breadcrumb {
        font-size: 11px;
        margin-bottom: 4px;
    }
    .sidebar-resizer {
        display: none;
    }
    .sidebar-footer {
        display: none;
    }
    .main-content {
        min-height: 0;
        flex: 1 1 auto;
    }
    .header {
        flex-shrink: 0;
    }
    .actions {
        gap: 6px;
    }
    .btn {
        width: 38px;
        height: 38px;
    }
    .file-info {
        flex-shrink: 0;
        overflow-x: auto;
    }
    .viewer-container {
        min-height: 0;
    }
    .pdf-controls {
        padding: 8px 6px;
        gap: 6px;
        justify-content: flex-start;
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: none;
    }
    .pdf-controls::-webkit-scrollbar {
        display: none;
    }
    .toolbar-group {
        padding: 0 4px;
        gap: 3px;
        flex: 0 0 auto;
    }

    .file-date-cell {
        display: none;
    }

    .file-size-cell {
        width: 60px;
        font-size: 11px;
    }
    .file-table th,.file-row td {
        padding: 8px 10px;
    }
}

.sidebar-footer {
    padding: 12px 16px;
    border-top: 1px solid <?php echo $theme==='dark'?'rgba(255,255,255,0.25)':'rgba(0,0,0,0.18)'; ?>;
    background: transparent;
}

.footer-text {
    font-size: 11px;
    color: <?php echo $theme==='dark'?'rgba(255,255,255,0.5)':'rgba(0,0,0,0.5)'; ?>;
    margin-bottom: 8px;
    text-align: center;
}

.footer-links {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.footer-link {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: <?php echo $theme==='dark'?'rgba(255,255,255,0.6)':'rgba(0,0,0,0.6)'; ?>;
    text-decoration: none;
    transition: all 0.2s;
}

.footer-link:hover {
    color: #<?php echo $color; ?>;
}

.footer-link svg {
    opacity: 0.7;
}
