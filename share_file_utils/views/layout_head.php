<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language); ?>">
<head>
<link rel="icon" type="image/png" href="share.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo htmlspecialchars($fileName); ?></title>
<?php if ($fileType === 'text'): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/loader.min.js"></script>
<script>
(function() {
    var oldDefine = window.define;
    var restoreDefine = false;
    if (typeof window.define === 'function' && window.define.amd) {
        restoreDefine = true;
        window.define = undefined;
    }
    var script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/marked@4.3.0/marked.min.js';
    script.async = false;
    script.onload = function() {
        window.markedLib = window.marked || (window.marked && window.marked.default) || window.marked;
        if (restoreDefine) window.define = oldDefine;
    };
    script.onerror = function() { if (restoreDefine) window.define = oldDefine; };
    document.head.appendChild(script);
})();
</script>
<script>window.MathJax = {tex:{inlineMath:[['\\(','\\)'],['$','$']],displayMath:[['$$','$$'],['\\[','\\]']],processEscapes:true,processEnvironments:true,packages:['base','ams','noerrors','noundefined']},options:{skipHtmlTags:['script','noscript','style','textarea','pre','code'],ignoreHtmlClass:'tex2jax_ignore',processHtmlClass:'tex2jax_process'},loader:{load:['[tex]/ams','[tex]/noerrors','[tex]/noundefined']},startup:{pageReady:()=>MathJax.startup.defaultPageReady().then(()=>{if(window.markdownContentReady)window.markdownContentReady();})}};</script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js"></script>
<?php endif; ?>
<?php if ($fileType === 'pdf'): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<?php endif; ?>
<?php if ($fileType === '3dmodel'): ?>
<script type="importmap">{"imports":{"three":"https://unpkg.com/three@0.170.0/build/three.module.js","three/addons/":"https://unpkg.com/three@0.170.0/examples/jsm/"}}</script>
<?php endif; ?>
<?php if ($fileType === 'office'): ?>
<script src="<?php echo htmlspecialchars($DOC_SERV_URL); ?>/web-apps/apps/api/documents/api.js"></script>
<?php endif; ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(SFU_ASSET_BASE . 'css/main.php?' . http_build_query(['theme' => $theme, 'color' => $color, 'fullscreen' => $isFullscreen ? '1' : '0', 'folder' => $isFolder ? '1' : '0', 'v' => filemtime(SFU_DIR . '/assets/css/main.php')])); ?>">
</head>
