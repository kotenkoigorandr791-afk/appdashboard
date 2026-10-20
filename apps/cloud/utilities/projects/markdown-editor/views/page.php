<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markdown Editor with LaTeX Support</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <!-- Remove polyfill.io which causes an error -->
    <!-- Load MathJax directly with correct settings -->
    <script>
<?php require __DIR__ . '/../public/assets/js/page-script1.js.php'; ?>
</script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js"></script>
    <style>
        :root{--accent-soft:color-mix(in srgb, <?php echo $color;?> 24%, transparent);--accent-soft-strong:color-mix(in srgb, <?php echo $color;?> 34%, transparent);--accent-border:color-mix(in srgb, <?php echo $color;?> 62%, transparent);--accent-text:color-mix(in srgb, <?php echo $color;?> 90%, white 10%);--danger-soft:color-mix(in srgb, #c42b1c 24%, transparent);--danger-soft-strong:color-mix(in srgb, #c42b1c 34%, transparent);--danger-border:color-mix(in srgb, #c42b1c 62%, transparent);--danger-text:color-mix(in srgb, #c42b1c 90%, white 10%);--success-soft:color-mix(in srgb, #10b981 24%, transparent);--success-soft-strong:color-mix(in srgb, #10b981 34%, transparent);--success-border:color-mix(in srgb, #10b981 62%, transparent);--success-text:color-mix(in srgb, #10b981 90%, white 10%);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#1a1a2e;color:#e0e0e0;min-height:100vh;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;overflow:hidden;}
        .container{width:100vw;height:100vh;display:flex;flex-direction:column;}
        .editor-container{display:flex;flex:1;min-height:0;position:relative;}
        .resizer{width:8px;background:#252526;cursor:col-resize;position:relative;z-index:10;}
        .resizer:hover{background:<?php echo $color;?>;}
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
        .toolbar-select{background:#2d2d2d;color:#e0e0e0;border:1px solid #444;padding:4px 6px;border-radius:4px;font-size:0.8rem;height:30px;min-width:70px;cursor:pointer;}
        .toolbar-select:focus{outline:none;border-color:<?php echo $color;?>;}
        .toolbar-select.disabled{opacity:0.4;pointer-events:none;}
        #editor-container{flex:1;overflow:hidden;border:none;outline:none;box-shadow:none;}
        #editor-container:focus,#editor-container:focus-within,.monaco-editor,.monaco-editor.focused,.monaco-editor .overflow-guard,.monaco-editor .inputarea,.monaco-editor textarea{border:none!important;outline:none!important;box-shadow:none!important;}
        .preview-header{padding:8px 10px;background:#252526;border-bottom:1px solid #333;display:flex;justify-content:space-between;align-items:center;}
        .preview-title{font-size:0.9rem;font-weight:600;color:#aaa;}
        .preview-actions{display:flex;gap:8px;}
        #preview{flex:1;padding:15px;overflow-y:auto;background:#1e1e1e;}
        #preview.fullscreen{position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:1000;background:#1e1e1e;padding:20px;}
        #preview::-webkit-scrollbar{width:8px;height:8px;}
        #preview::-webkit-scrollbar-track{background:#252526;border-radius:4px;}
        #preview::-webkit-scrollbar-thumb{background:#555;border-radius:4px;}
        #preview::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        #preview::-webkit-scrollbar-corner{background:#252526;}
        .editor-toolbar::-webkit-scrollbar{height:6px;width:6px;}
        .editor-toolbar::-webkit-scrollbar-track{background:#252526;border-radius:4px;}
        .editor-toolbar::-webkit-scrollbar-thumb{background:#555;border-radius:4px;}
        .editor-toolbar::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        .editor-toolbar::-webkit-scrollbar-corner{background:#252526;}
        .preview-content{max-width:100%;word-wrap:break-word;user-select:text;-webkit-user-select:text;-moz-user-select:text;-ms-user-select:text;}
        .preview-content h1{color:<?php echo $color;?>;border-bottom:2px solid <?php echo $color;?>;padding-bottom:5px;margin:20px 0 15px 0;font-size:1.8rem;}
        .preview-content h2{color:<?php echo $color;?>;opacity:0.9;margin:18px 0 12px 0;font-size:1.5rem;}
        .preview-content h3{color:<?php echo $color;?>;opacity:0.8;margin:16px 0 10px 0;font-size:1.3rem;}
        .preview-content h4{color:<?php echo $color;?>;opacity:0.7;margin:14px 0 8px 0;font-size:1.1rem;}
        .preview-content strong{color:#d4d4d4;}
        .preview-content em{color:#ce9178;}
        .preview-content code{background:#2d2d2d;color:#e0e0e0;padding:2px 4px;border-radius:3px;font-family:'Consolas',monospace;}
        .preview-content pre{background:#2d2d2d;padding:12px;border-radius:4px;overflow-x:auto;margin:10px 0;border-left:4px solid <?php echo $color;?>;}
        .preview-content pre code{background:none;padding:0;}
        .preview-content blockquote{border-left:4px solid <?php echo $color;?>;padding-left:15px;margin:12px 0;color:#aaa;font-style:italic;background:rgba(0,0,0,0.1);padding:10px 15px;border-radius:0 4px 4px 0;}
        .preview-content ul,.preview-content ol{margin-left:20px;margin-bottom:10px;}
        .preview-content a{color:<?php echo $color;?>;text-decoration:none;}
        .preview-content a:hover{text-decoration:underline;opacity:0.9;}
        .preview-content table{border-collapse:collapse;width:100%;margin:12px 0;border:1px solid #404040;}
        .preview-content th{background:#252526;color:<?php echo $color;?>;padding:8px;border:1px solid #404040;}
        .preview-content td{padding:8px;border:1px solid #404040;}
        .preview-content hr{border:none;height:2px;background:<?php echo $color;?>;margin:20px 0;opacity:0.5;}
        .preview-content img{max-width:100%;border-radius:4px;margin:10px 0;border:1px solid #404040;}
        
        /* LaTeX styles */
        .preview-content .math{color:<?php echo $color;?>;font-weight:bold;}
        .preview-content .MathJax { color: <?php echo $color;?> !important; outline: none; }
        .preview-content mjx-container { color: <?php echo $color;?> !important; margin: 0 2px; }
        .preview-content mjx-container[display="true"] { display: block; margin: 1em 0; text-align: center; }
        .preview-content mjx-container[display="true"] + br { display: none; }
        
        .modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:2000;align-items:center;justify-content:center;}
        .modal-content{background:#252526;border-radius:8px;padding:20px;min-width:300px;max-width:500px;box-shadow:0 5px 20px rgba(0,0,0,0.5);border:1px solid <?php echo $color;?>;max-height:90vh;overflow-y:auto;}
        .modal-content::-webkit-scrollbar{width:8px;height:8px;}
        .modal-content::-webkit-scrollbar-track{background:#252526;border-radius:4px;}
        .modal-content::-webkit-scrollbar-thumb{background:#555;border-radius:4px;}
        .modal-content::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        .modal-content::-webkit-scrollbar-corner{background:#252526;}
        .modal-header{font-size:1.2rem;font-weight:600;margin-bottom:15px;padding:0 6px 10px;border-bottom:1px solid <?php echo $color;?>;color:<?php echo $color;?>;display:flex;justify-content:space-between;align-items:center;}
        .modal-close{background:none;border:none;color:#aaa;font-size:1.5rem;cursor:pointer;padding:0;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:4px;}
        .modal-close:hover{background:var(--danger-soft);color:var(--danger-text);}
        .modal-body{margin-bottom:20px;}
        .modal-footer{display:flex;justify-content:flex-end;gap:10px;}
        .modal-input{width:100%;padding:8px 12px;margin:8px 0;background:#1e1e1e;border:1px solid #444;border-radius:4px;color:#e0e0e0;font-size:1rem;}
        .modal-input:focus{outline:none;border-color:<?php echo $color;?>;}
        .modal-btn{padding:8px 16px;border:none;border-radius:4px;cursor:pointer;font-size:0.9rem;font-weight:500;transition:all 0.2s;}
        .modal-btn-primary{background:var(--accent-soft);border:1px solid var(--accent-border);color:var(--accent-text);}
        .modal-btn-primary:hover{background:var(--accent-soft-strong);}
        .modal-btn-secondary{background:color-mix(in srgb, #555 72%, transparent);color:#f3f4f6;border:1px solid color-mix(in srgb, #777 40%, transparent);}
        .modal-btn-secondary:hover{background:#666;}
        .table-size-inputs{display:flex;gap:15px;margin:15px 0;}
        .table-size-input{flex:1;}
        .size-label{display:block;margin-bottom:5px;font-size:0.9rem;color:#aaa;}
        .status-message{position:fixed;top:20px;right:20px;background:var(--accent-soft);border:1px solid var(--accent-border);color:var(--accent-text);padding:10px 20px;border-radius:4px;z-index:2001;display:none;box-shadow:0 3px 10px rgba(0,0,0,0.3);font-weight:500;}
        body.light-theme{background:#f5f5f5;color:#333;}
        body.light-theme .resizer{background:#e0e0e0;}
        body.light-theme .toolbar-group{border-right-color:#ccc;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-track{background:#f0f0f0;border-radius:2px;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-thumb{background:#bbb;border-radius:2px;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        body.light-theme .editor-toolbar{scrollbar-color:#bbb #f0f0f0;}
        body.light-theme .editor-panel,body.light-theme .preview-panel{background:#ffffff;border:1px solid #ddd;}
        body.light-theme .editor-toolbar{background:#f0f0f0;border-bottom:1px solid #ddd;}
        body.light-theme .preview-header{background:#f0f0f0;border-bottom:1px solid #ddd;}
        body.light-theme .toolbar-btn{background:#ffffff;color:#333;border:1px solid #ccc;}
        body.light-theme .toolbar-btn:hover{background:#e6e6e6;}
        body.light-theme .toolbar-btn.primary{background:var(--accent-soft);border-color:var(--accent-border);color:var(--accent-text);}
        body.light-theme .toolbar-btn.danger{background:var(--danger-soft);border-color:var(--danger-border);color:var(--danger-text);}
        body.light-theme .toolbar-btn.disabled{opacity:0.4;background:#f0f0f0;border-color:#ccc;color:#999;}
        body.light-theme .toolbar-select{background:#ffffff;color:#333;border:1px solid #ccc;}
        body.light-theme #preview{background:#ffffff;}
        body.light-theme #preview.fullscreen{background:#ffffff;}
        body.light-theme .preview-content h1,body.light-theme .preview-content h2,body.light-theme .preview-content h3,body.light-theme .preview-content h4{color:<?php echo $color;?>;}
        body.light-theme .preview-content a{color:<?php echo $color;?>;}
        body.light-theme .preview-content code{background:#f0f0f0;color:#333;}
        body.light-theme .preview-content pre{background:#f0f0f0;border-left-color:<?php echo $color;?>;}
        body.light-theme .preview-content table{border-color:#d4d8de;}
        body.light-theme .preview-content th{background:#e5e7eb;color:#4b5563;border-color:#d4d8de;}
        body.light-theme .preview-content td{border-color:#d4d8de;color:#333;}
        body.light-theme .modal-content{background:#ffffff;color:#333;border:1px solid <?php echo $color;?>;}
        body.light-theme .modal-header{border-bottom-color:<?php echo $color;?>;}
        body.light-theme .modal-body p{color:#4b5563;}
        body.light-theme .modal-input{background:#ffffff;color:#333;border:1px solid #ccc;}
        body.light-theme .size-label{color:#4b5563;}
        body.light-theme #preview::-webkit-scrollbar-track{background:#f0f0f0;border-radius:4px;}
        body.light-theme #preview::-webkit-scrollbar-thumb{background:#bbb;border-radius:4px;}
        body.light-theme #preview::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        body.light-theme #preview::-webkit-scrollbar-corner{background:#f0f0f0;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-track{background:#f0f0f0;border-radius:4px;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-thumb{background:#bbb;border-radius:4px;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        body.light-theme .editor-toolbar::-webkit-scrollbar-corner{background:#f0f0f0;}
        body.light-theme .modal-content::-webkit-scrollbar-track{background:#f0f0f0;}
        body.light-theme .modal-content::-webkit-scrollbar-thumb{background:#bbb;}
        body.light-theme .modal-content::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        body.light-theme .modal-content::-webkit-scrollbar-corner{background:#f0f0f0;}
        .toolbar-btn.exit-fullscreen{background:var(--danger-soft);border-color:var(--danger-border);color:var(--danger-text);}
        .toolbar-btn.exit-fullscreen:hover{background:var(--danger-soft-strong);}
        .mjx-math{color:<?php echo $color;?> !important;}
        .MathJax{color:<?php echo $color;?> !important;}
        mjx-container[jax="CHTML"]{color:<?php echo $color;?> !important;}
        @media (max-width:768px){
            .editor-container{flex-direction:column;}
            .resizer{width:100%;height:8px;cursor:row-resize;}
            .editor-panel,.preview-panel{min-width:100%;min-height:250px;}
            .editor-toolbar{padding:6px 8px;gap:4px;height:44px;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;white-space:nowrap;-webkit-overflow-scrolling:touch;scrollbar-width:thin;scrollbar-color:#555 #252526;}
            .editor-toolbar::-webkit-scrollbar{height:4px;}
            .editor-toolbar::-webkit-scrollbar-track{background:#252526;border-radius:2px;}
            .editor-toolbar::-webkit-scrollbar-thumb{background:#555;border-radius:2px;}
            .editor-toolbar::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;opacity:0.8;}
            .toolbar-group{height:32px;padding-right:8px;gap:2px;margin-bottom:0;flex-shrink:0;border-right:1px solid #444;border-bottom:none;}
            .toolbar-group:last-child{border-right:none;padding-right:0;}
            .preview-header{padding:6px 8px;display:none;}
            .preview-actions-mobile{display:flex !important;gap:4px;flex-shrink:0;}
            .toolbar-btn{min-width:32px;height:30px;padding:4px 6px;font-size:0.75rem;margin:0;flex-shrink:0;}
            .toolbar-btn i{font-size:0.75rem;}
            .toolbar-select{min-width:60px;height:30px;font-size:0.75rem;padding:4px 6px;flex-shrink:0;}
            #preview{padding:10px;}
            .preview-content h1{font-size:1.4rem;margin:15px 0 10px 0;}
            .preview-content h2{font-size:1.2rem;margin:12px 0 8px 0;}
            .preview-content h3{font-size:1.1rem;margin:10px 0 6px 0;}
            .preview-content h4{font-size:1rem;margin:8px 0 5px 0;}
            .modal-content{padding:15px;min-width:250px;max-width:90%;}
            .status-message{top:10px;right:10px;left:10px;text-align:center;}
        }
        @media (max-width:480px){
            .editor-toolbar{padding:5px 6px;height:42px;}
            .toolbar-group{padding-right:6px;gap:1px;}
            .toolbar-btn{min-width:30px;height:28px;padding:3px 5px;font-size:0.7rem;}
            .toolbar-btn i{font-size:0.7rem;}
            .toolbar-select{min-width:55px;height:28px;font-size:0.7rem;padding:3px 5px;}
            .modal-content{min-width:220px;padding:12px;}
        }
        @media (max-width:360px){
            .editor-toolbar{height:40px;padding:4px 5px;}
            .toolbar-group{padding-right:5px;}
            .toolbar-btn{min-width:28px;height:26px;padding:2px 4px;font-size:0.65rem;}
            .toolbar-btn i{font-size:0.65rem;}
            .toolbar-select{min-width:50px;height:26px;font-size:0.65rem;padding:2px 4px;}
            .modal-content{min-width:200px;padding:10px;}
        }
        #preview.fullscreen .fullscreen-header{position:fixed;top:0;left:0;right:0;background:#252526;padding:10px 15px;display:flex;justify-content:space-between;align-items:center;z-index:1001;border-bottom:1px solid #333;}
        #preview.fullscreen .fullscreen-title{color:#aaa;font-size:0.9rem;font-weight:600;}
        #preview.fullscreen .fullscreen-actions{display:flex;gap:8px;}
        #preview.fullscreen .preview-content{margin-top:50px;}
        .preview-actions-mobile{display:none;}
        .translation-modal-content {
            max-width: 800px;
            width: 90%;
            background: #252526;
            border-radius: 16px;
            padding: 0; /* Change padding to 0 */
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            border: 1px solid <?php echo $color;?>;
            max-height: 90vh;
            overflow: hidden; /* Add this */
            display: flex;
            flex-direction: column;
        }
        .translation-modal-content .modal-body {
            padding: 24px; /* Move the padding here */
            overflow-y: auto;
            flex: 1;
        }
                .translation-modal-content .modal-body::-webkit-scrollbar{width:8px;height:8px;}
        .translation-modal-content .modal-body::-webkit-scrollbar-track{background:#252526;border-radius:4px;}
        .translation-modal-content .modal-body::-webkit-scrollbar-thumb{background:#555;border-radius:4px;}
        .translation-modal-content .modal-body::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        .translation-modal-content .modal-body::-webkit-scrollbar-corner{background:#252526;}

        .translation-container{display:flex;flex-direction:column;gap:20px;width:100%;overflow-x:hidden;}
        .translation-header{display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;background:#1e1e1e;padding:16px;border-radius:12px;border:1px solid #444;width:100%;box-sizing:border-box;}
        .translation-lang-select{flex:1;min-width:150px;}
        .translation-lang-select label{display:block;margin-bottom:6px;font-weight:600;color:<?php echo $color;?>;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;}
        .translation-lang-select select{width:100%;padding:10px 12px;border:1px solid #444;border-radius:8px;background:#2d2d2d;color:#e0e0e0;font-size:14px;cursor:pointer;transition:border-color 0.2s;}
        .translation-lang-select select:hover{border-color:<?php echo $color;?>;}
        .translation-lang-select select:focus{outline:none;border-color:<?php echo $color;?>;box-shadow:0 0 0 2px rgba(86,156,214,0.1);}
        .translation-settings-btn{padding:10px;background:#2d2d2d;border:1px solid #444;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#e0e0e0;margin-bottom:0px;transition:all 0.2s;}
        .translation-settings-btn:hover{border-color:var(--accent-border);background:var(--accent-soft);color:var(--accent-text);}
        .translation-settings-btn svg{width:20px;height:20px;}
        .translation-text-box{display:flex;flex-direction:column;gap:8px;width:100%;box-sizing:border-box;}
        .translation-text-box label{font-weight:600;color:<?php echo $color;?>;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;}
        .translation-text-box textarea {
            width: 100%;
            padding: 16px;
            border: 1px solid #444;
            border-radius: 12px;
            background: #1e1e1e;
            color: #e0e0e0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
            line-height: 1.6;
            min-height: 150px;
            resize: vertical;
            transition: border-color 0.2s, opacity 0.3s ease;
            box-sizing: border-box;
            opacity: 1;
            
            /* Custom scrollbar for dark theme */
            scrollbar-width: thin;
            scrollbar-color: #555 #2d2d2d;
        }

        .translation-text-box textarea::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .translation-text-box textarea::-webkit-scrollbar-track {
            background: #2d2d2d;
            border-radius: 4px;
        }

        .translation-text-box textarea::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .translation-text-box textarea::-webkit-scrollbar-thumb:hover {
            background: <?php echo $color;?>;
        }

        .translation-text-box textarea::-webkit-scrollbar-corner {
            background: #2d2d2d;
        }

        /* For light theme */
        body.light-theme .translation-text-box textarea {
            background: #ffffff;
            color: #333;
            border: 1px solid #ccc;
            
            /* Custom scrollbar for light theme */
            scrollbar-width: thin;
            scrollbar-color: #bbb #f0f0f0;
        }

        body.light-theme .translation-text-box textarea::-webkit-scrollbar-track {
            background: #f0f0f0;
        }

        body.light-theme .translation-text-box textarea::-webkit-scrollbar-thumb {
            background: #bbb;
        }

        body.light-theme .translation-text-box textarea::-webkit-scrollbar-thumb:hover {
            background: <?php echo $color;?>;
        }

        body.light-theme .translation-text-box textarea::-webkit-scrollbar-corner {
            background: #f0f0f0;
        }
        .translation-buttons{display:flex;gap:12px;justify-content:flex-end;margin-top:8px;flex-wrap:wrap;}
        .translation-buttons .btn{min-width:120px;padding:12px 20px;border-radius:8px;font-weight:600;transition:all 0.2s;border:none;cursor:pointer;font-size:14px;background:#2d2d2d;color:#e0e0e0;border:1px solid #444;}
        .translation-buttons .btn.primary{background:var(--accent-soft);border-color:var(--accent-border);color:var(--accent-text);}
        .translation-buttons .btn.success{background:var(--success-soft);border-color:var(--success-border);color:var(--success-text);}
        .translation-buttons .btn.success:hover{background:var(--success-soft-strong);}
        .translation-loading{display:inline-block;width:16px;height:16px;border:2px solid currentColor;border-top-color:transparent;border-radius:50%;animation:spin 0.6s linear infinite;margin-right:8px;}
        @keyframes spin{to{transform:rotate(360deg);}}
        .settings-modal-content{max-width:500px;width:90%;background:#252526;border-radius:16px;padding:24px;overflow-x:hidden;border:1px solid <?php echo $color;?>;max-height:90vh;overflow-y:auto;}
        .settings-modal-content::-webkit-scrollbar{width:8px;height:8px;}
        .settings-modal-content::-webkit-scrollbar-track{background:#252526;border-radius:4px;}
        .settings-modal-content::-webkit-scrollbar-thumb{background:#555;border-radius:4px;}
        .settings-modal-content::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        .settings-modal-content::-webkit-scrollbar-corner{background:#252526;}
        .settings-group{margin-bottom:24px;width:100%;box-sizing:border-box;}
        .settings-group label{display:block;margin-bottom:8px;font-weight:600;color:<?php echo $color;?>;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;}
        .settings-group input,.settings-group select{width:100%;padding:12px;border:1px solid #444;border-radius:8px;background:#1e1e1e;color:#e0e0e0;font-size:14px;margin-bottom:12px;transition:border-color 0.2s;box-sizing:border-box;}
        .settings-group input:focus,.settings-group select:focus{outline:none;border-color:<?php echo $color;?>;box-shadow:0 0 0 2px rgba(86,156,214,0.1);}
        .model-select-container{display:flex;gap:10px;align-items:center;}
        .model-select-container select{flex:1;margin-bottom:0;}
        .model-select-container button{padding:12px 16px;white-space:nowrap;border-radius:8px;background:#2d2d2d;border:1px solid #444;color:#e0e0e0;cursor:pointer;transition:all 0.2s;}
        .model-select-container button:hover{border-color:var(--accent-border);background:var(--accent-soft);color:var(--accent-text);}
        @media (max-width:768px){
            .translation-modal-content{width:95%;padding:16px;max-height:90vh;overflow-y:auto;overflow-x:hidden;}
            .translation-header{flex-direction:column;align-items:stretch;gap:12px;padding:12px;}
            .translation-lang-select{min-width:100%;}
            .translation-settings-btn{width:100%;margin-top:4px;}
            .translation-text-box textarea{min-height:120px;padding:12px;}
            .translation-buttons{flex-direction:column;gap:8px;}
            .translation-buttons .btn{width:100%;min-width:auto;}
        }
        body.light-theme .translation-modal-content{background:#ffffff;color:#333;border:1px solid <?php echo $color;?>;}
        body.light-theme .translation-header{background:#f5f5f5;border:1px solid #ddd;}
        body.light-theme .translation-lang-select select{background:#ffffff;color:#333;border:1px solid #ccc;}
        body.light-theme .translation-settings-btn{background:#ffffff;color:#333;border:1px solid #ccc;}
        body.light-theme .translation-text-box textarea{background:#ffffff;color:#333;border:1px solid #ccc;}
        body.light-theme .translation-text-box textarea:read-only{background:#f5f5f5;}
        body.light-theme .translation-buttons .btn{background:#f0f0f0;color:#333;border:1px solid #ccc;}
        body.light-theme .settings-modal-content{background:#ffffff;color:#333;border:1px solid <?php echo $color;?>;}
        body.light-theme .settings-group input,body.light-theme .settings-group select{background:#ffffff;color:#333;border:1px solid #ccc;}
        body.light-theme .model-select-container button{background:#f0f0f0;color:#333;border:1px solid #ccc;}
        body.light-theme .translation-modal-content::-webkit-scrollbar-track,body.light-theme .settings-modal-content::-webkit-scrollbar-track{background:#f0f0f0;}
        body.light-theme .translation-modal-content::-webkit-scrollbar-thumb,body.light-theme .settings-modal-content::-webkit-scrollbar-thumb{background:#bbb;}
        body.light-theme .translation-modal-content::-webkit-scrollbar-thumb:hover,body.light-theme .settings-modal-content::-webkit-scrollbar-thumb:hover{background:<?php echo $color;?>;}
        body.light-theme .modal-close{color:#666;}
        body.light-theme .modal-close:hover{background:var(--danger-soft);color:var(--danger-text);}
        body.light-theme #pdfStatus{color:#4b5563 !important;}
        .toolbar-btn.default {
            background: #2d2d2d;
            border-color: #444;
            color: #e0e0e0;
        }

        .toolbar-btn.default:hover {
            background: #3d3d3d;
        }

        body.light-theme .toolbar-btn.default {
            background: #ffffff;
            border-color: #ccc;
            color: #333;
        }

        body.light-theme .toolbar-btn.default:hover {
            background: #e6e6e6;
        }
    </style>
<style><?php readfile(dirname(__DIR__, 2) . '/shared/assets/editor-ui.css'); ?></style>
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
                        <button class="toolbar-btn primary" id="exportPdfBtn" title="Export to PDF" data-i18n-title="exportPdfTooltip"><i class="fas fa-file-pdf"></i></button>
                    </div>
                    <div class="toolbar-group">
                        <button class="toolbar-btn" id="boldBtn" title="Bold (Ctrl+B)" data-i18n-title="boldTooltip"><i class="fas fa-bold"></i></button>
                        <button class="toolbar-btn" id="italicBtn" title="Italic (Ctrl+I)" data-i18n-title="italicTooltip"><i class="fas fa-italic"></i></button>
                        <button class="toolbar-btn" id="underlineBtn" title="Underline (Ctrl+U)" data-i18n-title="underlineTooltip"><i class="fas fa-underline"></i></button>
                        <button class="toolbar-btn" id="strikeBtn" title="Strikethrough" data-i18n-title="strikeTooltip"><i class="fas fa-strikethrough"></i></button>
                    </div>
                    <div class="toolbar-group">
                        <select class="toolbar-select" id="headingSelect" title="Heading" data-i18n-title="headingTooltip">
                            <option value="" data-i18n="normalText">Normal text</option>
                            <option value="h1" data-i18n="heading1">H1</option>
                            <option value="h2" data-i18n="heading2">H2</option>
                            <option value="h3" data-i18n="heading3">H3</option>
                            <option value="h4" data-i18n="heading4">H4</option>
                        </select>
                    </div>
                    <div class="toolbar-group">
                        <button class="toolbar-btn" id="ulistBtn" title="Bulleted list" data-i18n-title="bulletListTooltip"><i class="fas fa-list-ul"></i></button>
                        <button class="toolbar-btn" id="olistBtn" title="Numbered list" data-i18n-title="numberedListTooltip"><i class="fas fa-list-ol"></i></button>
                        <button class="toolbar-btn" id="checklistBtn" title="Task list" data-i18n-title="taskListTooltip"><i class="fas fa-tasks"></i></button>
                    </div>
                    <div class="toolbar-group">
                        <button class="toolbar-btn" id="linkBtn" title="Insert link" data-i18n-title="linkTooltip"><i class="fas fa-link"></i></button>
                        <button class="toolbar-btn" id="codeBtn" title="Insert code" data-i18n-title="codeTooltip"><i class="fas fa-code"></i></button>
                        <button class="toolbar-btn" id="quoteBtn" title="Insert quote" data-i18n-title="quoteTooltip"><i class="fas fa-quote-right"></i></button>
                    </div>
                    <div class="toolbar-group">
                        <button class="toolbar-btn" id="tableBtn" title="Insert table" data-i18n-title="tableTooltip"><i class="fas fa-table"></i></button>
                        <button class="toolbar-btn" id="hrBtn" title="Horizontal line" data-i18n-title="hrTooltip"><i class="fas fa-minus"></i></button>
                        <button class="toolbar-btn" id="mathBtn" title="Insert math formula" data-i18n-title="mathTooltip"><i class="fas fa-square-root-alt"></i></button>
                        <button class="toolbar-btn" id="undoBtn" title="Undo (Ctrl+Z)" data-i18n-title="undoTooltip"><i class="fas fa-undo"></i></button>
                        <button class="toolbar-btn" id="redoBtn" title="Redo (Ctrl+Y)" data-i18n-title="redoTooltip"><i class="fas fa-redo"></i></button>
                    </div>
                    <div class="toolbar-group">
                        <button class="toolbar-btn default" id="translateBtnToolbar" title="Translate selected text" data-i18n-title="translateTooltip">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-languages-icon lucide-languages">
                                <path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>
                            </svg>
                        </button>
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
                        <div class="fullscreen-title" data-i18n="preview">Preview</div>
                        <div class="fullscreen-actions">
                            <button class="toolbar-btn" id="fullscreenRefreshBtn" title="Refresh preview" data-i18n-title="refreshPreviewTooltip"><i class="fas fa-sync-alt"></i></button>
                            <button class="toolbar-btn exit-fullscreen" id="fullscreenExitBtn" title="Exit fullscreen" data-i18n-title="exitFullscreenTooltip"><i class="fas fa-compress"></i></button>
                        </div>
                    </div>
                    <div class="preview-content"></div>
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
    <div class="modal-overlay" id="tableModal">
        <div class="modal-content">
            <div class="modal-header">
                <span data-i18n="insertTableTitle">Insert Table</span>
                <button class="modal-close" onclick="document.getElementById('tableModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-size-inputs">
                    <div class="table-size-input">
                        <label class="size-label" data-i18n="rows">Rows</label>
                        <input type="number" id="tableRows" class="modal-input" min="1" max="20" value="3">
                    </div>
                    <div class="table-size-input">
                        <label class="size-label" data-i18n="columns">Columns</label>
                        <input type="number" id="tableCols" class="modal-input" min="1" max="10" value="3">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary" id="cancelTableBtn" data-i18n="cancel">Cancel</button>
                <button class="modal-btn modal-btn-primary" id="insertTableBtn" data-i18n="insert">Insert</button>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="mathModal">
        <div class="modal-content">
            <div class="modal-header">
                <span data-i18n="insertMathTitle">Insert Math Formula</span>
                <button class="modal-close" onclick="document.getElementById('mathModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <p data-i18n="mathExamples">Examples: \(E = mc^2\), $$\int_a^b f(x)dx$$</p>
                <textarea id="mathFormula" class="modal-input" rows="4" placeholder="Enter LaTeX formula here..." data-i18n-placeholder="mathPlaceholder"></textarea>
                <div class="table-size-inputs">
                    <div class="table-size-input">
                        <label class="size-label"><input type="radio" name="mathType" value="inline" checked> Inline (\(...\))</label>
                    </div>
                    <div class="table-size-input">
                        <label class="size-label"><input type="radio" name="mathType" value="block"> Block ($$...$$)</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary" id="cancelMathBtn" data-i18n="cancel">Cancel</button>
                <button class="modal-btn modal-btn-primary" id="insertMathBtn" data-i18n="insert">Insert</button>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="pdfModal">
        <div class="modal-content">
            <div class="modal-header">
                <span data-i18n="exportPdfTitle">Export to PDF</span>
                <button class="modal-close" onclick="document.getElementById('pdfModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <p data-i18n="exportPdfText">The PDF will be generated using pandoc and saved in the same folder.</p>
                <div class="table-size-inputs">
                    <div class="table-size-input">
                        <label class="size-label" data-i18n="fontFamily">Font family</label>
                        <select id="pdfFont" class="modal-input">
                            <option value="Liberation Serif">Liberation Serif</option>
                            <option value="DejaVu Serif">DejaVu Serif</option>
                            <option value="Times New Roman">Times New Roman</option>
                            <option value="Arial">Arial</option>
                            <option value="Helvetica">Helvetica</option>
                        </select>
                    </div>
                    <div class="table-size-input">
                        <label class="size-label" data-i18n="paperSize">Paper size</label>
                        <select id="paperSize" class="modal-input">
                            <option value="a4">A4</option>
                            <option value="letter">Letter</option>
                            <option value="legal">Legal</option>
                        </select>
                    </div>
                </div>
                <p id="pdfStatus" style="margin-top:10px; color:#aaa; font-size:0.9rem;"></p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary" id="cancelPdfBtn" data-i18n="cancel">Cancel</button>
                <button class="modal-btn modal-btn-primary" id="generatePdfBtn" data-i18n="generatePdf">Generate PDF</button>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="translationModal">
        <div class="modal-content translation-modal-content">
            <div class="modal-header">
                <span data-i18n="translationWindow">Translation</span>
                <button class="modal-close" onclick="document.getElementById('translationModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <div class="translation-container">
                    <div class="translation-header">
                        <div class="translation-lang-select">
                            <label for="sourceLang" data-i18n="sourceLang">Source language</label>
                            <select id="sourceLang">
                                <option value="auto" data-i18n="autoDetect">Auto-detect</option>
                                <option value="en">English</option>
                                <option value="ru">Русский</option>
                                <option value="uk">Українська</option>
                                <option value="be">Беларуская</option>
                                <option value="de">Deutsch</option>
                                <option value="fr">Français</option>
                                <option value="es">Español</option>
                                <option value="it">Italiano</option>
                                <option value="pl">Polski</option>
                                <option value="zh">中文</option>
                                <option value="ja">日本語</option>
                            </select>
                        </div>
                        <div class="translation-lang-select">
                            <label for="targetLang" data-i18n="targetLang">Target language</label>
                            <select id="targetLang">
                                <option value="en">English</option>
                                <option value="ru" selected>Русский</option>
                                <option value="uk">Українська</option>
                                <option value="be">Беларуская</option>
                                <option value="de">Deutsch</option>
                                <option value="fr">Français</option>
                                <option value="es">Español</option>
                                <option value="it">Italiano</option>
                                <option value="pl">Polski</option>
                                <option value="zh">中文</option>
                                <option value="ja">日本語</option>
                            </select>
                        </div>
                    </div>
                    <div class="translation-text-box">
                        <label for="sourceText" data-i18n="translate">Text to translate</label>
                        <textarea id="sourceText" placeholder="Enter text to translate..." data-i18n-placeholder="translatePlaceholder"></textarea>
                    </div>
                    <div class="translation-text-box">
                        <label for="targetText" data-i18n="translation">Translation</label>
                        <textarea id="targetText" readonly placeholder="Translation will appear here..." data-i18n-placeholder="translationPlaceholder"></textarea>
                    </div>
                    <div class="translation-buttons">
                        <button class="btn" id="translateAgainBtn" data-i18n="translateAgain">Translate again</button>
                        <button class="btn primary" id="translateBtn" data-i18n="translateButton">Translate</button>
                        <button class="btn success" id="replaceTextBtn" data-i18n="replaceText">Replace text</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="status-message" id="statusMessage"></div>
    <script src="<?= htmlspecialchars($assetsBaseUrl) ?>/monaco-shared.js?v=3"></script>
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js"></script>
    <script src="<?= htmlspecialchars($assetsBaseUrl) ?>/monaco-touch-selection.js?v=4"></script>
    <script src="<?= htmlspecialchars($assetsBaseUrl) ?>/monaco-iframe-collab.js?v=1"></script>
    <script src="<?= htmlspecialchars($assetsBaseUrl) ?>/monaco-ai-iframe.js"></script>
    <script>
<?php require __DIR__ . '/../public/assets/js/page-script2.js.php'; ?>
</script>
<script><?php readfile(dirname(__DIR__, 2) . '/shared/assets/editor-ui.js'); ?></script>
</body>
</html>
