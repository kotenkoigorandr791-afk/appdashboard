<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTML Editor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--accent-soft:color-mix(in srgb, <?php echo $color;?> 24%, transparent);--accent-soft-strong:color-mix(in srgb, <?php echo $color;?> 34%, transparent);--accent-border:color-mix(in srgb, <?php echo $color;?> 62%, transparent);--accent-text:color-mix(in srgb, <?php echo $color;?> 90%, white 10%);--danger-soft:color-mix(in srgb, #c42b1c 24%, transparent);--danger-soft-strong:color-mix(in srgb, #c42b1c 34%, transparent);--danger-border:color-mix(in srgb, #c42b1c 62%, transparent);--danger-text:color-mix(in srgb, #c42b1c 90%, white 10%);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#1a1a2e;color:#e0e0e0;min-height:100vh;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;overflow:hidden;}
        .container{width:100vw;height:100vh;display:flex;flex-direction:column;}
        .editor-container{display:flex;flex:1;min-height:0;position:relative;}
        .resizer{width:8px;flex:0 0 8px;background:#252526;cursor:col-resize;position:relative;z-index:20;touch-action:none;}
        .resizer:hover{background:<?php echo $color;?>;}
        body.is-resizing,body.is-resizing *{cursor:col-resize!important;user-select:none!important;}
        body.is-resizing .preview-panel iframe,body.is-resizing .preview-frame-wrap{pointer-events:none!important;}
        .resize-shield{position:fixed;inset:0;z-index:9999;cursor:col-resize;touch-action:none;background:transparent;}
        .editor-panel,.preview-panel{flex:1;min-width:300px;display:flex;flex-direction:column;background:#1e1e1e;overflow:hidden;}
        .editor-toolbar{display:flex;padding:8px 10px;background:#252526;border-bottom:1px solid #333;flex-wrap:wrap;gap:5px;align-items:center;}
        .toolbar-group{display:flex;gap:3px;padding-right:10px;border-right:1px solid #444;height:32px;align-items:center;}
        .toolbar-group:last-child{border-right:none;}
        .toolbar-btn{background:#2d2d2d;color:#e0e0e0;border:1px solid #444;padding:5px 8px;border-radius:4px;cursor:pointer;font-size:0.8rem;display:flex;align-items:center;justify-content:center;min-width:32px;height:30px;transition:all 0.2s;}
        .toolbar-btn:hover{background:#3d3d3d;}
        .toolbar-btn.primary{background:var(--accent-soft);border-color:var(--accent-border);color:var(--accent-text);}
        .toolbar-btn.primary:hover{background:var(--accent-soft-strong);}
        .toolbar-btn.danger{background:var(--danger-soft);border-color:var(--danger-border);color:var(--danger-text);}
        .toolbar-btn.danger:hover{background:var(--danger-soft-strong);}
        .toolbar-btn.disabled{opacity:0.4;pointer-events:none;background:#2d2d2d;border-color:#444;color:#666;}
        .toolbar-btn i{font-size:0.8rem;}
        #editor-container{flex:1;overflow:hidden;border:none;outline:none;box-shadow:none;}
        #editor-container:focus,#editor-container:focus-within,.monaco-editor,.monaco-editor.focused,.monaco-editor .overflow-guard,.monaco-editor .inputarea,.monaco-editor textarea{border:none!important;outline:none!important;box-shadow:none!important;}
        .preview-header{padding:8px 10px;background:#252526;border-bottom:1px solid #333;display:flex;justify-content:space-between;align-items:center;}
        .preview-title{font-size:0.9rem;font-weight:600;color:#aaa;}
        .preview-actions{display:flex;gap:8px;}
        .preview-panel{display:flex;flex-direction:column;}
        #preview{flex:1;min-height:0;padding:0;overflow:hidden;background:#fff;position:relative;display:flex;flex-direction:column;}
        #preview.fullscreen{position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:1000;background:#fff;padding:0;}
        #preview iframe{width:100%;height:100%;border:none;background:#fff;display:block;color-scheme:dark;}
        body.light-theme #preview iframe{color-scheme:light;}
        #preview.fullscreen .fullscreen-header{position:fixed;top:0;left:0;right:0;background:#252526;padding:10px 15px;display:flex;justify-content:space-between;align-items:center;z-index:1001;border-bottom:1px solid #333;}
        #preview.fullscreen .preview-frame-wrap{margin-top:50px;height:calc(100% - 50px);}
        .preview-frame-wrap{flex:1;width:100%;height:100%;position:relative;}
        .modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:2000;align-items:center;justify-content:center;}
        .modal-content{background:#252526;border-radius:8px;padding:20px;min-width:300px;max-width:500px;box-shadow:0 5px 20px rgba(0,0,0,0.5);border:1px solid <?php echo $color;?>;}
        .modal-header{font-size:1.2rem;font-weight:600;margin-bottom:15px;padding:0 6px 10px;border-bottom:1px solid <?php echo $color;?>;color:<?php echo $color;?>;display:flex;justify-content:space-between;align-items:center;}
        .modal-close{background:none;border:none;color:#aaa;font-size:1.5rem;cursor:pointer;padding:0;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:4px;}
        .modal-close:hover{background:var(--danger-soft);color:var(--danger-text);}
        .modal-body{margin-bottom:20px;}
        .modal-footer{display:flex;justify-content:flex-end;gap:10px;}
        .modal-btn{padding:8px 16px;border:none;border-radius:4px;cursor:pointer;font-size:0.9rem;font-weight:500;}
        .modal-btn-primary{background:var(--accent-soft);border:1px solid var(--accent-border);color:var(--accent-text);}
        .modal-btn-secondary{background:color-mix(in srgb, #555 72%, transparent);color:#f3f4f6;border:1px solid color-mix(in srgb, #777 40%, transparent);}
        .status-message{position:fixed;top:20px;right:20px;background:var(--accent-soft);border:1px solid var(--accent-border);color:var(--accent-text);padding:10px 20px;border-radius:4px;z-index:2001;display:none;font-weight:500;}
        body.light-theme{background:#f5f5f5;color:#333;}
        body.light-theme .resizer{background:#e0e0e0;}
        body.light-theme .toolbar-group{border-right-color:#ccc;}
        body.light-theme .editor-panel,body.light-theme .preview-panel{background:#ffffff;border:1px solid #ddd;}
        body.light-theme .editor-toolbar{background:#f0f0f0;border-bottom:1px solid #ddd;scrollbar-color:#bbb #f0f0f0;}
        body.light-theme .preview-header{background:#f0f0f0;border-bottom:1px solid #ddd;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-track{background:#f0f0f0;border-radius:4px;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-thumb{background:#bbb;border-radius:4px;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-corner{background:#f0f0f0;}
        body.light-theme .toolbar-btn{background:#ffffff;color:#333;border:1px solid #ccc;}
        body.light-theme .toolbar-btn:hover{background:#e6e6e6;}
        body.light-theme .toolbar-btn.primary{background:var(--accent-soft);border-color:var(--accent-border);color:var(--accent-text);}
        body.light-theme .toolbar-btn.danger{background:var(--danger-soft);border-color:var(--danger-border);color:var(--danger-text);}
        body.light-theme .modal-content{background:#ffffff;color:#333;}
        .toolbar-btn.exit-fullscreen{background:var(--danger-soft);border-color:var(--danger-border);color:var(--danger-text);}
        .preview-actions-mobile{display:none;}
        @media (max-width:768px){
            .editor-container{flex-direction:column;}
            .resizer{width:100%;height:8px;cursor:row-resize;}
            .editor-panel,.preview-panel{min-width:100%;min-height:250px;}
            .preview-header{display:none;}
            .preview-actions-mobile{display:flex !important;gap:4px;flex-shrink:0;}
        }
    </style>
<style><?php readfile(dirname(__DIR__, 2) . '/shared/assets/editor-ui.css'); ?></style>
<style><?php readfile(dirname(__DIR__, 2) . '/shared/assets/editor-toolbar.css'); ?></style>
<style>#saveBtn{display:none!important}</style>
</head>
<body class="<?php echo $theme === 'light' ? 'light-theme' : ''; ?>">
    <div class="container">
        <div class="editor-container">
            <div class="editor-panel">
                <div class="editor-toolbar">
                    <div class="toolbar-group">
                        <button class="toolbar-btn primary" id="saveBtn" title="Save (Ctrl+S)" data-i18n-title="saveTooltip"><i class="fas fa-save"></i></button>
                        <button class="toolbar-btn danger" id="clearBtn" title="Clear all content" data-i18n-title="clearTooltip"><i class="fas fa-broom"></i></button>
                    </div>
                    <div class="toolbar-group">
                        <button class="toolbar-btn" id="undoBtn" title="Undo (Ctrl+Z)" data-i18n-title="undoTooltip"><i class="fas fa-undo"></i></button>
                        <button class="toolbar-btn" id="redoBtn" title="Redo (Ctrl+Y)" data-i18n-title="redoTooltip"><i class="fas fa-redo"></i></button>
                    </div>
                    <div class="toolbar-group preview-actions-mobile" id="mobilePreviewActions">
                        <button class="toolbar-btn" id="fullscreenBtnMobile" title="Fullscreen preview" data-i18n-title="fullscreenTooltip"><i class="fas fa-expand"></i></button>
                        <button class="toolbar-btn" id="refreshPreviewBtnMobile" title="Refresh preview" data-i18n-title="refreshPreviewTooltip"><i class="fas fa-sync-alt"></i></button>
                        <button class="toolbar-btn exit-fullscreen" id="exitFullscreenBtnMobile" title="Exit fullscreen" data-i18n-title="exitFullscreenTooltip" style="display:none;"><i class="fas fa-compress"></i></button>
                    </div>
                </div>
                <div id="editor-container"></div>
            </div>
            <div class="resizer"></div>
            <div class="preview-panel">
                <div class="preview-header">
                    <div class="preview-title" data-i18n="preview">Preview</div>
                    <div class="preview-actions" id="desktopPreviewActions">
                        <button class="toolbar-btn" id="fullscreenBtn" title="Fullscreen preview" data-i18n-title="fullscreenTooltip"><i class="fas fa-expand"></i></button>
                        <button class="toolbar-btn" id="refreshPreviewBtn" title="Refresh preview" data-i18n-title="refreshPreviewTooltip"><i class="fas fa-sync-alt"></i></button>
                        <button class="toolbar-btn exit-fullscreen" id="exitFullscreenBtn" title="Exit fullscreen" data-i18n-title="exitFullscreenTooltip" style="display:none;"><i class="fas fa-compress"></i></button>
                    </div>
                </div>
                <div id="preview">
                    <div class="fullscreen-header" style="display:none;">
                        <div class="preview-title" data-i18n="preview">Preview</div>
                        <div class="preview-actions">
                            <button class="toolbar-btn" id="fullscreenRefreshBtn" title="Refresh preview" data-i18n-title="refreshPreviewTooltip"><i class="fas fa-sync-alt"></i></button>
                            <button class="toolbar-btn exit-fullscreen" id="fullscreenExitBtn" title="Exit fullscreen" data-i18n-title="exitFullscreenTooltip"><i class="fas fa-compress"></i></button>
                        </div>
                    </div>
                    <div class="preview-frame-wrap">
                        <iframe id="previewFrame" name="cloud-html-preview" title="HTML Preview" sandbox="allow-scripts allow-same-origin allow-forms allow-modals allow-popups"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="confirmClearModal">
        <div class="modal-content">
            <div class="modal-header">
                <span data-i18n="confirmClearTitle">Confirm Clear</span>
                <button class="modal-close" onclick="document.getElementById('confirmClearModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <p data-i18n="confirmClearText">Are you sure you want to clear all content? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary" id="cancelClearBtn" data-i18n="cancel">Cancel</button>
                <button class="modal-btn modal-btn-primary" id="confirmClearBtn" data-i18n="clear">Clear</button>
            </div>
        </div>
    </div>
    <div class="status-message" id="statusMessage"></div>
    <script src="<?= htmlspecialchars($assetsBaseUrl) ?>/monaco-shared.js?v=3"></script>
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js"></script>
    <script src="<?= htmlspecialchars($assetsBaseUrl) ?>/monaco-touch-selection.js?v=4"></script>
    <script src="<?= htmlspecialchars($assetsBaseUrl) ?>/monaco-iframe-collab.js?v=2"></script>
    <script src="<?= htmlspecialchars($assetsBaseUrl) ?>/monaco-ai-iframe.js"></script>
    <script>
<?php require __DIR__ . '/../public/assets/js/page-script2.js.php'; ?>
</script>
<script><?php readfile(dirname(__DIR__, 2) . '/shared/assets/editor-ui.js'); ?></script>
<script><?php readfile(dirname(__DIR__, 2) . '/shared/assets/editor-review.js'); ?></script>
<script><?php readfile(dirname(__DIR__, 2) . '/shared/assets/editor-toolbar.js'); ?></script>
</body>
</html>
