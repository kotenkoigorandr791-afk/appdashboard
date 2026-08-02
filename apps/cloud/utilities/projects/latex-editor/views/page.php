<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?php echo $t['title'] . htmlspecialchars($filePath); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/codemirror.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/theme/<?php echo $theme === 'light' ? 'default' : 'material-darker'; ?>.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.14/addon/hint/show-hint.min.css">
  <!-- Monaco Editor -->
  <link rel="stylesheet" data-name="vs/editor/editor.main" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/editor/editor.main.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root { <?php foreach ($currentTheme as $variable => $value): ?><?php echo $variable; ?>: <?php echo $value; ?>; <?php endforeach; ?> --accent-color: <?php echo $accentColor; ?>; --accent-soft: color-mix(in srgb, var(--accent-color) 24%, transparent); --accent-soft-strong: color-mix(in srgb, var(--accent-color) 34%, transparent); --accent-border: color-mix(in srgb, var(--accent-color) 62%, transparent); --accent-text: color-mix(in srgb, var(--accent-color) 90%, white 10%); --success-color: #28a745; --success-soft: color-mix(in srgb, var(--success-color) 24%, transparent); --success-soft-strong: color-mix(in srgb, var(--success-color) 34%, transparent); --success-border: color-mix(in srgb, var(--success-color) 62%, transparent); --success-text: color-mix(in srgb, var(--success-color) 90%, white 10%); --warning-color: #c89600; --warning-soft: color-mix(in srgb, var(--warning-color) 30%, transparent); --warning-soft-strong: color-mix(in srgb, var(--warning-color) 40%, transparent); --warning-border: color-mix(in srgb, var(--warning-color) 62%, transparent); --warning-text: color-mix(in srgb, var(--warning-color) 72%, black 28%); --error-color: #ff6b6b; --error-soft: color-mix(in srgb, var(--error-color) 24%, transparent); --error-soft-strong: color-mix(in srgb, var(--error-color) 34%, transparent); --error-border: color-mix(in srgb, var(--error-color) 62%, transparent); --error-text: color-mix(in srgb, var(--error-color) 90%, white 10%); --mobile-header-height: 50px; --mobile-footer-height: 50px; }
    ::-webkit-scrollbar { width: 6px; height: 6px; } ::-webkit-scrollbar-track { background: var(--scrollbar-bg); } ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 3px; } ::-webkit-scrollbar-thumb:hover { background: #6a6a6a; } html { scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-bg); scrollbar-width: thin; }
    html, body { height: 100%; width: 100%; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin:0; background: var(--bg-color); color: var(--text-color); font-size: 14px; overflow: hidden; height: 100%; }
    #main { display: flex; height: 100%; min-height: 0; min-width: 0; width: 100%; box-sizing: border-box; }
    #editor-panel { display: flex; flex-direction: column; width: 50%; min-width: 300px; min-height: 0; padding: 0; flex-shrink: 0; box-sizing: border-box; }
    #pdf-panel { width: 50%; min-width: 300px; min-height: 0; display:flex; flex-direction:column; flex: 1 1 auto; background: <?php echo $theme === 'light' ? '#f9f9f9' : '#525659'; ?>; box-sizing: border-box; }
    #resizer { width: 5px; cursor: col-resize; background: #000; user-select: none; z-index: 10; }
    #resizer:hover { background: var(--accent-color); }
    
    /* Tablet layout - horizontal split like desktop (overridden by portrait media query) */
    .tablet-layout #main { flex-direction: row; height: 100%; }
    .tablet-layout #editor-panel { width: 50%; height: 100%; order: 0; }
    .tablet-layout #pdf-panel { width: 50%; height: 100%; order: 2; }
    .tablet-layout #resizer { width: 5px; height: 100%; cursor: col-resize; order: 1; }
    @media (orientation: portrait) {
      .tablet-layout #main { flex-direction: column; }
      .tablet-layout #editor-panel { width: 100%; height: 50%; order: 1; }
      .tablet-layout #pdf-panel { width: 100%; height: 50%; order: 3; }
      .tablet-layout #resizer { width: 100%; height: 5px; cursor: row-resize; order: 2; }
    }
    
    /* Mobile layout - vertical split with editor on top */
    body.mobile-layout {
      display: flex;
      flex-direction: column;
      min-height: 0;
      height: 100%;
      height: 100dvh;
    }
    body.mobile-layout #main {
      flex: 1 1 0;
      flex-direction: column;
      min-height: 0;
      overflow: hidden;
    }
    body.mobile-layout #mobile-header,
    body.mobile-layout #mobile-footer {
      flex-shrink: 0;
    }
    body.mobile-layout #editor-panel {
      width: 100%;
      flex: 1 1 0;
      min-height: 0;
      order: 1;
    }
    body.mobile-layout #pdf-panel {
      width: 100%;
      flex: 1 1 0;
      min-height: 0;
      order: 3;
    }
    body.mobile-layout #pdf-view {
      min-height: 0;
    }
    body.mobile-layout #resizer {
      flex-shrink: 0;
      width: 100%;
      height: 5px;
      cursor: row-resize;
      order: 2;
    }
    body.mobile-layout #resizer:hover {
      background: var(--accent-color);
    }
    /* All portrait screens: vertical layout with editor on top, preview on bottom */
    @media (orientation: portrait) {
      #main { flex-direction: column; }
      #editor-panel { width: 100% !important; height: 50%; min-width: 0; order: 1; }
      #pdf-panel { width: 100% !important; height: 50%; min-width: 0; order: 3; }
      #resizer { width: 100%; height: 5px; cursor: row-resize; order: 2; }
      body.mobile-layout #editor-panel,
      body.mobile-layout #pdf-panel {
        height: auto;
        flex: 1 1 0;
      }
    }
    
    #mobile-header { display: none; height: var(--mobile-header-height); background: var(--panel-bg); border-bottom: 1px solid var(--border-color); padding: 0 15px; align-items: center; justify-content: space-between; }
    .mobile-layout #mobile-header { display: flex; }
    .tablet-layout #mobile-header { display: none; }
    
    #mobile-title { font-weight: bold; font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%; }
    #mobile-footer { display: none; height: var(--mobile-footer-height); background: var(--panel-bg); border-top: 1px solid var(--border-color); padding: 0 8px; align-items: center; justify-content: space-around; gap: 6px; flex-wrap: nowrap; }
    .mobile-layout #mobile-footer { display: flex; }
    .tablet-layout #mobile-footer { display: none; }

    
    /* Special style for the compile button in the mobile version */
    .mobile-layout #mobile-compile-btn {
      background: var(--accent-soft);
      font-weight: bold;
      border-color: var(--accent-border);
      color: var(--accent-text);
      position: relative;
      padding-left: 30px;
    }
    
    .mobile-layout #mobile-compile-btn.status-compiling {
      background: var(--warning-soft);
      border-color: var(--warning-border);
      color: var(--warning-text);
    }
    
    .mobile-layout #mobile-compile-btn.status-ok {
      background: var(--success-soft);
      border-color: var(--success-border);
      color: var(--success-text);
    }
    
    .mobile-layout #mobile-compile-btn.status-error {
      background: var(--error-soft);
      border-color: var(--error-border);
      color: var(--error-text);
    }
    
    .mobile-layout #mobile-compile-btn .btn-status-indicator {
      position: absolute;
      left: 8px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 14px;
    }
    
    #helper-bar { display: flex; align-items: center; padding: 8px 12px; background: var(--panel-bg); border-bottom: 1px solid var(--border-color); flex-shrink: 0; gap: 10px; flex-wrap: wrap; }
    .mobile-layout #helper-bar { display: none; }
    .tablet-layout #helper-bar { display: flex; }
    .fullscreen-editor #helper-bar { display: flex !important; }
    
    .btn { padding: 6px 12px; border: 1px solid var(--border-color); background: var(--btn-bg); color: var(--text-color); border-radius: 4px; cursor:pointer; transition: background .2s; white-space: nowrap; font-size: 13px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .btn:hover { background: #4a4d51; }
    #compile-btn { background: var(--accent-soft); font-weight: bold; border-color: var(--accent-border); color: var(--accent-text); }
    #compile-btn:hover { background: var(--accent-soft-strong); }
    #save-pdf-btn { background: var(--success-soft); font-weight: bold; border-color: var(--success-border); color: var(--success-text); }
    #save-pdf-btn:hover { background: var(--success-soft-strong); }
    #insert-select, #template-select { background: var(--btn-bg); color: var(--text-color); border: 1px solid var(--border-color); border-radius: 4px; padding: 5px 8px; font-family: inherit; }
    #insert-select:hover, #template-select:hover { background-color: #4a4d51; }
    #insert-select option, #template-select option { background: var(--panel-bg); color: var(--text-color); }
    #insert-select optgroup, #template-select optgroup { font-weight: bold; color: var(--accent-color); }
    #status-indicator { margin-left: auto; font-size: 0.9em; white-space: nowrap; flex-shrink: 0; }
    .status-compiling { color: var(--warning-color); } 
    .status-ok { color: #32cd32; } 
    .status-error { color: #ff6b6b; }
    #latex-editor-container { flex: 1 1 auto; min-height: 0; position: relative; overflow: hidden; border: none; outline: none; box-shadow: none; }
    #monaco-editor { position: absolute; top:0; left:0; right:0; bottom:0; width:100%; height:100%; }
    #latex-editor-container:focus, #latex-editor-container:focus-within,
    #monaco-editor:focus, #monaco-editor:focus-within,
    .monaco-editor, .monaco-editor.focused, .monaco-editor .overflow-guard,
    .monaco-editor .inputarea, .monaco-editor textarea {
      border: none !important;
      outline: none !important;
      box-shadow: none !important;
    }
    
    /* Monaco Editor custom colors for LaTeX */
    .monaco-editor .mtk1 { color: <?php echo $theme === 'light' ? '#000000' : '#ffffff'; ?>; } /* Default text */
    .monaco-editor .mtk2 { color: <?php echo $theme === 'light' ? '#000000' : '#d4d4d4'; ?>; } /* Text */
    .monaco-editor .mtk3 { color: <?php echo $theme === 'light' ? '#098658' : '#6a9955'; ?>; } /* Comments */
    .monaco-editor .mtk4 { color: <?php echo $theme === 'light' ? '#800000' : '#d16969'; ?>; } /* Keywords */
    .monaco-editor .mtk5 { color: <?php echo $theme === 'light' ? '#0451a5' : '#9cdcfe'; ?>; } /* Functions */
    .mtk6 { color: <?php echo $theme === 'light' ? '#a31515' : '#ce9178'; ?>; } /* Strings */
    .mtk7 { color: <?php echo $theme === 'light' ? '#0000ff' : '#569cd6'; ?>; } /* Numbers */
    .mtk8 { color: <?php echo $theme === 'light' ? '#267f99' : '#4ec9b0'; ?>; } /* Other */
    
    #pdf-view { flex-grow: 1; overflow-y: auto; padding: 15px; text-align: center; position: relative; }
    #pdf-view canvas { margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); max-width: 100%; }
    #pdf-placeholder { color: #aaa; font-style: italic; margin-top: 50px; }
    #mobile-insert-menu { display: none; position: fixed; bottom: var(--mobile-footer-height); left: 0; right: 0; background: var(--panel-bg); border-top: 1px solid var(--border-color); padding: 15px; max-height: 60vh; overflow-y: auto; z-index: 1000; }
    .mobile-insert-visible #mobile-insert-menu { display: block; }
    .mobile-insert-group { margin-bottom: 15px; }
    .mobile-insert-group h4 { margin: 0 0 8px 0; color: var(--accent-color); font-size: 14px; }
    .mobile-insert-btn { display: block; width: 100%; padding: 10px; margin-bottom: 5px; background: var(--btn-bg); border: 1px solid var(--border-color); color: var(--text-color); border-radius: 4px; text-align: left; cursor: pointer; }
    .mobile-insert-btn:hover { background: #4a4d51; }
    #mobile-hint { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 15px 20px; border-radius: 8px; text-align: center; z-index: 2000; font-size: 14px; }
    .mobile-hint-visible #mobile-hint { display: block; }
    .pdf-loading { color: #aaa; font-style: italic; text-align: center; padding: 50px; font-size: 16px; }
    .pdf-page { margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); max-width: 100%; display: block; margin-left: auto; margin-right: auto; }
    .error-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(139,0,0,0.9); color: white; padding: 20px; overflow-y: auto; z-index: 100; font-family: monospace; white-space: pre-wrap; }
    .error-close { position: absolute; top: 10px; right: 10px; background: #ff4444; border: none; color: white; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-weight: bold; }
    @media (max-width: 480px) { .mobile-btn { font-size: 11px; padding: 5px 2px; min-height: 30px; max-width: 65px; } #mobile-title { font-size: 14px; } #mobile-footer { padding: 0 6px; gap: 4px; } }
    .dropdown { position: relative; display: inline-block; }
    .dropdown-content { display: none; position: absolute; background: var(--panel-bg); min-width: 200px; box-shadow: 0 8px 16px rgba(0,0,0,0.2); z-index: 10000; border: 1px solid var(--border-color); border-radius: 4px; max-height: 300px; overflow-y: auto; }
    .dropdown-content.show { display: block; }
    .dropdown-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color); }
    .dropdown-item:hover { background: var(--btn-bg); }
    .dropdown-group { padding: 6px 12px; font-weight: bold; color: var(--accent-color); background: var(--bg-color); border-bottom: 1px solid var(--border-color); }
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 20000; align-items: center; justify-content: center; }
    .modal-content { background: var(--panel-bg); padding: 20px; border-radius: 8px; max-width: 90%; max-height: 90%; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
    .modal-title { font-size: 18px; font-weight: bold; color: var(--accent-color); }
    .modal-close { background: none; border: none; font-size: 24px; color: var(--text-color); cursor: pointer; }
    .modal-body { margin-bottom: 15px; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 10px; }
    .symbol-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin-bottom: 15px; }
    .symbol-btn { padding: 10px; background: var(--btn-bg); border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; text-align: center; font-size: 16px; transition: all 0.2s; color: var(--text-color);}
    .symbol-btn:hover { background: var(--accent-soft); border-color: var(--accent-border); color: var(--accent-text); }
    .fullscreen-editor { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 3000; background: var(--bg-color); }
    .fullscreen-editor #editor-panel { width: 100%; height: 100%; }
    .fullscreen-editor #helper-bar { display: flex !important; position: relative; padding: 10px 15px; z-index: 2;}
    .fullscreen-editor #mobile-footer { display: none; }
    .fullscreen-editor #mobile-header { display: none; }
    .fullscreen-editor #resizer { display: none; }
    .fullscreen-editor #pdf-panel { display: none; }
    .fullscreen-editor .modal {z-index: 40000  !important;}
    .fullscreen-editor .dropdown-content { z-index: 35000  !important;}
    .table-editor-container { display: flex; flex-direction: column; gap: 15px; }
    .table-editor-tabs { display: flex; border-bottom: 1px solid var(--border-color); }
    .table-editor-tab { padding: 10px 15px; cursor: pointer; border: 1px solid transparent; border-bottom: none; border-radius: 4px 4px 0 0; }
    .table-editor-tab.active { background: var(--btn-bg); border-color: var(--border-color); }
    .table-editor-content { display: none; }
    .table-editor-content.active { display: block; }
    .table-preview-container { overflow-x: auto; margin-bottom: 15px; border: 1px solid var(--border-color); padding: 10px; border-radius: 4px; max-height: 300px; overflow-y: auto; }
    .table-preview { border-collapse: collapse; width: 100%; min-width: 400px; }
    .table-preview td, .table-preview th { border: 1px solid var(--border-color); padding: 8px; text-align: center; min-width: 80px; }
    .table-preview th { background: var(--btn-bg); }
    .table-preview td { background: var(--bg-color); }
    .table-preview td.selected { background: var(--accent-soft); border-color: var(--accent-border); color: var(--accent-text); }
    .table-preview td.merged { background: #ffeb3b; color: #000; }
    .table-preview td.editing { background: #e3f2fd; }
    .table-controls-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 15px; }
    .table-control-btn { padding: 8px 12px; background: var(--btn-bg); border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; text-align: center; }
    .table-control-btn:hover { background: var(--accent-soft); border-color: var(--accent-border); color: var(--accent-text); }
    .table-settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .table-setting { display: flex; flex-direction: column; gap: 5px; }
    .table-setting label { font-weight: bold; color: var(--accent-color); }
    .table-setting input, .table-setting select { padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--btn-bg); color: var(--text-color); }
    .latex-preview { background: var(--code-bg); padding: 15px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; max-height: 200px; overflow-y: auto; margin-bottom: 15px; }
    .error-overlay { 
      position: absolute; 
      top: 10px; 
      left: 10px; 
      right: 10px; 
      bottom: 10px; 
      background: rgba(139,0,0,0.95); 
      color: white; 
      padding: 15px; 
      border-radius: 12px; 
      z-index: 100; 
      font-family: monospace; 
      display: flex; 
      flex-direction: column; 
      border: 2px solid #ff4444;
    }
    .error-header { 
      display: flex; 
      justify-content: space-between; 
      align-items: center; 
      margin-bottom: 15px; 
      padding-bottom: 10px; 
      border-bottom: 1px solid rgba(255,255,255,0.3); 
    }
    .error-buttons { 
      display: flex; 
      gap: 10px; 
    }
    .error-btn { 
      padding: 8px 16px; 
      border: none; 
      border-radius: 6px; 
      cursor: pointer; 
      font-weight: bold; 
      transition: all 0.2s; 
    }
    .copy-btn { 
      background: var(--success-soft); 
      border: 1px solid var(--success-border);
      color: var(--success-text); 
    }
    .copy-btn:hover { 
      background: var(--success-soft-strong); 
    }
    .close-btn { 
      background: var(--error-soft); 
      border: 1px solid var(--error-border);
      color: var(--error-text); 
    }
    .close-btn:hover { 
      background: var(--error-soft-strong); 
    }
    .error-content { 
      flex: 1; 
      overflow-y: auto; 
      background: rgba(0,0,0,0.3); 
      padding: 15px; 
      border-radius: 8px; 
    }
    .error-content pre { 
      margin: 0; 
      white-space: pre-wrap; 
      font-size: 12px; 
      line-height: 1.4; 
    }
    .notification-modal { 
      max-width: 400px; 
      text-align: center; 
    }
    .formatting-toolbar { display: flex; align-items: center; padding: 8px 10px; background: <?php echo $theme === 'light' ? '#f0f0f0' : '#252526'; ?>; border-bottom: 1px solid <?php echo $theme === 'light' ? '#ddd' : '#333'; ?>; gap: 5px; flex-wrap: wrap; }
    .formatting-btn { background: <?php echo $theme === 'light' ? '#ffffff' : '#2d2d2d'; ?>; color: <?php echo $theme === 'light' ? '#333' : '#e0e0e0'; ?>; border: 1px solid <?php echo $theme === 'light' ? '#ccc' : '#444'; ?>; padding: 5px 8px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; min-width: 32px; height: 30px; transition: all 0.2s; }
    .formatting-btn:hover { background: <?php echo $theme === 'light' ? '#e6e6e6' : '#3d3d3d'; ?>; border-color: <?php echo $theme === 'light' ? '#ccc' : '#444'; ?>; color: <?php echo $theme === 'light' ? '#333' : '#e0e0e0'; ?>; }
    .formatting-btn.active { background: var(--accent-soft-strong); color: var(--accent-text); border-color: var(--accent-border); }
    .formatting-btn i { font-size: 0.8rem; line-height: 1; }
    .formatting-btn svg { width: 16px; height: 16px; flex-shrink: 0; display: block; }
    .formatting-toolbar .toolbar-group { display: flex; gap: 3px; padding-right: 10px; border-right: 1px solid <?php echo $theme === 'light' ? '#ccc' : '#444'; ?>; height: 32px; align-items: center; flex-shrink: 0; }
    .formatting-toolbar .toolbar-group:last-child { border-right: none; padding-right: 0; }
    .pdf-settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .pdf-setting { display: flex; flex-direction: column; gap: 5px; }
    .pdf-setting label { font-weight: bold; color: var(--accent-color); }
    .pdf-setting input, .pdf-setting select { padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--btn-bg); color: var(--text-color); }
    .pdf-options { margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color); }
    .mobile-layout #formatting-toolbar {
      display: flex !important;
      flex-wrap: nowrap !important;
      overflow-x: auto !important;
      white-space: nowrap !important;
      -webkit-overflow-scrolling: touch;
      padding: 6px 8px !important;
      gap: 4px !important;
    }
    .mobile-layout #formatting-toolbar::-webkit-scrollbar {
      height: 4px;
    }
    .mobile-layout #formatting-toolbar::-webkit-scrollbar-track {
      background: var(--scrollbar-bg);
    }
    .mobile-layout #formatting-toolbar::-webkit-scrollbar-thumb {
      background: var(--accent-color);
      border-radius: 2px;
    }
    .mobile-layout .formatting-btn {
      flex-shrink: 0 !important;
      min-width: 32px !important;
      height: 30px !important;
      padding: 4px 6px !important;
    }
    .mobile-layout .formatting-btn i { font-size: 0.75rem; }
    .mobile-layout .formatting-toolbar .toolbar-group { height: 32px; padding-right: 8px; gap: 2px; }
    .tablet-layout #formatting-toolbar {
      display: flex !important;
    }
    .fullscreen-editor #formatting-toolbar {
      display: flex !important;
    }
    .mobile-layout #mobile-header {
      display: none !important;
    }
    .mobile-layout #mobile-footer {
      display: flex !important;
    }
    .tablet-layout #mobile-header,
    .tablet-layout #mobile-footer {
      display: none !important;
    }
    .fullscreen-editor #mobile-header {
      display: none !important;
    }
    .fullscreen-editor #mobile-footer {
      display: none !important;
    }
    .fullscreen-editor #helper-bar {
      display: flex !important;
    }
    .fullscreen-editor #formatting-toolbar {
      flex-wrap: nowrap !important;
      overflow-x: auto !important;
      white-space: nowrap !important;
    }
    .fullscreen-editor #formatting-toolbar::-webkit-scrollbar {
      height: 4px;
    }
    .fullscreen-editor #formatting-toolbar::-webkit-scrollbar-track {
      background: var(--scrollbar-bg);
    }
    .fullscreen-editor #formatting-toolbar::-webkit-scrollbar-thumb {
      background: var(--accent-color);
      border-radius: 2px;
    }
    .fullscreen-editor .formatting-btn {
      flex-shrink: 0 !important;
      min-width: 32px !important;
      height: 30px !important;
    }
    .fullscreen-editor #compile-btn,
    .fullscreen-editor #save-pdf-btn,
    .fullscreen-editor #status-indicator {
      display: none !important;
    }
    .fullscreen-editor #helper-bar {
      flex-wrap: nowrap !important;
      overflow-x: auto !important;
      white-space: nowrap !important;
      padding: 8px 10px !important;
    }
    .fullscreen-editor #helper-bar .btn {
      flex-shrink: 0;
      min-width: auto;
      padding: 6px 10px;
    }
    .fullscreen-editor #save-btn,
    .fullscreen-editor #template-btn,
    .fullscreen-editor #insert-btn {
      max-width: 120px;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .fullscreen-editor #helper-bar .btn svg {
      width: 14px;
      height: 14px;
    }
    .fullscreen-editor #helper-bar {
      gap: 6px !important;
    }
    .fullscreen-editor #formatting-toolbar {
      flex-wrap: nowrap !important;
      overflow-x: auto !important;
      padding: 6px 8px !important;
      gap: 4px !important;
    }
    .formatting-btn:disabled {
      opacity: 0.4;
      cursor: not-allowed;
      pointer-events: none;
      background: <?php echo $theme === 'light' ? '#f0f0f0' : '#2d2d2d'; ?>;
      border-color: <?php echo $theme === 'light' ? '#ccc' : '#444'; ?>;
      color: <?php echo $theme === 'light' ? '#999' : '#666'; ?>;
    }
    .formatting-btn:disabled:hover {
      background: <?php echo $theme === 'light' ? '#f0f0f0' : '#2d2d2d'; ?>;
      color: <?php echo $theme === 'light' ? '#999' : '#666'; ?>;
    }
    
    /* Notification Container */
    .notification-container {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 10000;
      display: flex;
      flex-direction: column;
      gap: 10px;
      max-width: 350px;
    }
    
    .notification {
      background: var(--panel-bg);
      border-left: 4px solid var(--accent-color);
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      color: var(--text-color);
      font-size: 14px;
      animation: slideIn 0.3s ease-out;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      backdrop-filter: blur(10px);
      border: 1px solid var(--border-color);
    }
    
    .notification.success {
      border-left-color: #10b981;
    }
    
    .notification.error {
      border-left-color: #ef4444;
    }
    
    .notification.warning {
      border-left-color: #f59e0b;
    }
    
    .notification.info {
      border-left-color: var(--accent-color);
    }
    
    .notification-icon {
      width: 24px;
      height: 24px;
      flex-shrink: 0;
    }
    
    .notification-icon svg {
      width: 100%;
      height: 100%;
      stroke: currentColor;
    }
    
    .notification-content {
      flex: 1;
    }
    
    .notification-title {
      font-weight: 600;
      margin-bottom: 4px;
      font-size: 15px;
    }
    
    .notification-message {
      opacity: 0.9;
      font-size: 13px;
      line-height: 1.4;
    }
    
    .notification-close {
      background: none;
      border: none;
      color: var(--text-color);
      cursor: pointer;
      opacity: 0.6;
      padding: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: opacity 0.2s;
    }
    
    .notification-close:hover {
      opacity: 1;
    }
    
    .notification-close svg {
      width: 16px;
      height: 16px;
    }
    
    @keyframes slideIn {
      from {
        transform: translateX(100%);
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
        transform: translateX(100%);
        opacity: 0;
      }
    }
    
    /* Modern design for the translation modal */
    .translation-modal-content {
      max-width: 800px;
      width: 90%;
      background: var(--panel-bg);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      overflow-x: hidden;
    }
    
    .translation-container {
      display: flex;
      flex-direction: column;
      gap: 20px;
      width: 100%;
      overflow-x: hidden;
    }
    
    .translation-header {
      display: flex;
      gap: 16px;
      align-items: flex-end;
      flex-wrap: wrap;
      background: var(--bg-color);
      padding: 16px;
      border-radius: 12px;
      border: 1px solid var(--border-color);
      width: 100%;
      box-sizing: border-box;
    }
    
    .translation-lang-select {
      flex: 1;
      min-width: 150px;
    }
    .formatting-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }

    .formatting-btn:disabled:hover {
        background: <?php echo $theme === 'light' ? '#f0f0f0' : '#2d2d2d'; ?>;
        color: <?php echo $theme === 'light' ? '#999' : '#666'; ?>;
    }

    .translation-lang-select label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: var(--accent-color);
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .translation-lang-select select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background: var(--btn-bg);
      color: var(--text-color);
      font-size: 14px;
      cursor: pointer;
      transition: border-color 0.2s;
    }
    
    .translation-lang-select select:hover {
      border-color: var(--accent-color);
    }
    
    .translation-lang-select select:focus {
      outline: none;
      border-color: var(--accent-color);
      box-shadow: 0 0 0 2px rgba(var(--accent-color-rgb), 0.1);
    }
    
    .translation-settings-btn {
      padding: 10px;
      background: var(--btn-bg);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--text-color);
      margin-bottom: 0px;
      transition: all 0.2s;
    }
    
    .translation-settings-btn:hover {
      border-color: var(--accent-border);
      background: var(--accent-soft);
      color: var(--accent-text);
    }
    
    .translation-settings-btn svg {
      width: 20px;
      height: 20px;
    }
    
    .translation-text-box {
      display: flex;
      flex-direction: column;
      gap: 8px;
      width: 100%;
      box-sizing: border-box;
    }
    
    .translation-text-box label {
      font-weight: 600;
      color: var(--accent-color);
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .translation-text-box textarea {
      width: 100%;
      padding: 16px;
      border: 1px solid var(--border-color);
      border-radius: 12px;
      background: var(--bg-color);
      color: var(--text-color);
      font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
      font-size: 14px;
      line-height: 1.6;
      min-height: 150px;
      resize: vertical;
      transition: border-color 0.2s;
      box-sizing: border-box;
    }
    
    .translation-text-box textarea:focus {
      outline: none;
      border-color: var(--accent-color);
      box-shadow: 0 0 0 2px rgba(var(--accent-color-rgb), 0.1);
    }
    
    .translation-text-box textarea:read-only {
      background: var(--btn-bg);
      border-color: var(--border-color);
    }
    
    .translation-buttons {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      margin-top: 8px;
      flex-wrap: wrap;
    }
    
    .translation-buttons .btn {
      min-width: 120px;
      padding: 12px 20px;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.2s;
    }
    
    .translation-buttons .btn.primary {
      background: var(--accent-soft);
      border-color: var(--accent-border);
      color: var(--accent-text);
    }
    
    .translation-buttons .btn.success {
      background: var(--success-soft);
      border-color: var(--success-border);
      color: var(--success-text);
    }
    
    .translation-buttons .btn.success:hover {
      background: var(--success-soft-strong);
    }
    
    .translation-loading {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid currentColor;
      border-top-color: transparent;
      border-radius: 50%;
      animation: spin 0.6s linear infinite;
      margin-right: 8px;
    }
    
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    
    /* Mobile styles for the translation modal */
    @media (max-width: 768px) {
      .translation-modal-content {
        width: 95%;
        padding: 16px;
        max-height: 90vh;
        overflow-y: auto;
        overflow-x: hidden;
      }
      
      .translation-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        padding: 12px;
      }
      
      .translation-lang-select {
        min-width: 100%;
      }
      
      .translation-settings-btn {
        width: 100%;
        margin-top: 4px;
      }
      
      .translation-text-box textarea {
        min-height: 120px;
        padding: 12px;
      }
      
      .translation-buttons {
        flex-direction: column;
        gap: 8px;
      }
      
      .translation-buttons .btn {
        width: 100%;
        min-width: auto;
      }
    }
    
    /* Settings Modal Styles */
    .settings-modal-content {
      max-width: 500px;
      width: 90%;
      background: var(--panel-bg);
      border-radius: 16px;
      padding: 24px;
      overflow-x: hidden;
    }
    
    .settings-group {
      margin-bottom: 24px;
      width: 100%;
      box-sizing: border-box;
    }
    
    .settings-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--accent-color);
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .settings-group input,
    .settings-group select {
      width: 100%;
      padding: 12px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background: var(--bg-color);
      color: var(--text-color);
      font-size: 14px;
      margin-bottom: 12px;
      transition: border-color 0.2s;
      box-sizing: border-box;
    }
    
    .settings-group input:focus,
    .settings-group select:focus {
      outline: none;
      border-color: var(--accent-color);
      box-shadow: 0 0 0 2px rgba(var(--accent-color-rgb), 0.1);
    }
    
    .model-select-container {
      display: flex;
      gap: 10px;
      align-items: center;
    }
    
    .model-select-container select {
      flex: 1;
      margin-bottom: 0;
    }
    
    .model-select-container button {
      padding: 12px 16px;
      white-space: nowrap;
      border-radius: 8px;
      background: var(--btn-bg);
      border: 1px solid var(--border-color);
      color: var(--text-color);
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .model-select-container button:hover {
      border-color: var(--accent-border);
      background: var(--accent-soft);
      color: var(--accent-text);
    }
    .mobile-btn { 
      flex: 0 0 auto; 
      padding: 6px 12px; 
      border: 1px solid var(--border-color); 
      background: var(--btn-bg); 
      color: var(--text-color); 
      border-radius: 4px; 
      cursor: pointer; 
      transition: background .2s; 
      font-size: 12px; 
      text-align: center; 
      min-height: 32px; 
      display: inline-flex; 
      align-items: center; 
      justify-content: center; 
      white-space: nowrap;
      max-width: none;
    }

    .mobile-btn:hover { 
      background: #4a4d51; 
    }

    .mobile-btn.primary { 
      background: var(--accent-soft); 
      font-weight: bold; 
      border-color: var(--accent-border); 
      color: var(--accent-text); 
    }

    .mobile-btn.success { 
      background: var(--success-soft); 
      font-weight: bold; 
      border-color: var(--success-border); 
      color: var(--success-text); 
    }

    .mobile-btn svg {
      width: 16px;
      height: 16px;
      margin: 0;
    }

    /* Specifically for the save button (with icon) */
    #mobile-save-btn {
      padding: 6px 8px;
    }

    /* For the compile button with a status indicator */
    #mobile-compile-btn {
      position: relative;
      padding-left: 30px; /* Leave room for the indicator */
    }

    #mobile-compile-btn .btn-status-indicator {
      position: absolute;
      left: 8px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 14px;
    }

    #mobile-compile-btn.status-compiling {
      background: var(--warning-soft);
      border-color: var(--warning-border);
      color: var(--warning-text);
    }

    #mobile-compile-btn.status-ok {
      background: var(--success-soft);
      border-color: var(--success-border);
      color: var(--success-text);
    }

    #mobile-compile-btn.status-error {
      background: var(--error-soft);
      border-color: var(--error-border);
      color: var(--error-text);
    }

    /* Footer container */
    #mobile-footer { 
      display: none; 
      height: var(--mobile-footer-height); 
      background: var(--panel-bg); 
      border-top: 1px solid var(--border-color); 
      padding: 0 8px; 
      align-items: center; 
      justify-content: center; /* Center the buttons */
      gap: 8px; 
      flex-wrap: nowrap; 
      overflow-x: auto; 
      -webkit-overflow-scrolling: touch;
    }

    /* Hide scrollbar for a clean look */
    #mobile-footer::-webkit-scrollbar {
      display: none;
    }
      /* Fix for fullscreen mode on mobile */
.fullscreen-editor #latex-editor-container {
  height: calc(100vh - 100px) !important; /* Subtract the toolbar height */
  flex-grow: 1 !important;
}

.fullscreen-editor #monaco-editor {
  height: 100% !important;
  width: 100% !important;
  position: absolute !important;
}

.fullscreen-editor #editor-panel {
  width: 100% !important;
  height: 100vh !important;
  display: flex !important;
  flex-direction: column !important;
}

/* Remove height limits for the mobile layout in fullscreen mode */
.fullscreen-editor.mobile-layout #editor-panel,
.fullscreen-editor.mobile-layout #main,
.fullscreen-editor.mobile-layout {
  height: 100vh !important;
  overflow: hidden !important;
}

.fullscreen-editor #formatting-toolbar {
  flex-shrink: 0 !important;
}

.fullscreen-editor #helper-bar {
  flex-shrink: 0 !important;
}
    /* Adaptation for small screens */
    @media (max-width: 480px) { 
      .mobile-btn { 
        font-size: 11px; 
        padding: 5px 10px; 
        min-height: 30px; 
      }
      
      #mobile-save-btn {
        padding: 5px 6px;
      }
      
      #mobile-compile-btn {
        padding-left: 28px;
      }
      
      #mobile-compile-btn .btn-status-indicator {
        left: 6px;
        font-size: 12px;
      }
      
      #mobile-footer { 
        padding: 0 6px; 
        gap: 6px; 
      } 
    }

    /* If buttons do not fit and a scrollbar appears,
      centering is preserved for the visible area */
    #mobile-footer::before,
    #mobile-footer::after {
      content: '';
      flex: 0 0 auto;
    }
    /* RGB values for accent color */
    :root {
      --accent-color-rgb: <?php echo hexdec(substr($accentColor, 1, 2)) . ',' . hexdec(substr($accentColor, 3, 2)) . ',' . hexdec(substr($accentColor, 5, 2)); ?>;
    }
    
    /* Monaco Editor specific styles */
    .monaco-editor .margin {
      background-color: var(--code-bg) !important;
    }
    .monaco-editor .monaco-editor-background {
      background-color: var(--code-bg) !important;
    }
    .monaco-editor .minimap {
      background-color: var(--code-bg) !important;
    }
    .monaco-editor .line-numbers {
      color: <?php echo $theme === 'light' ? '#888' : '#666'; ?> !important;
    }
    .monaco-editor .current-line {
      border: none !important;
      background-color: <?php echo $theme === 'light' ? 'rgba(0,0,0,0.05)' : 'rgba(255,255,255,0.05)'; ?> !important;
    }
    .monaco-editor .selected-text {
      background-color: rgba(var(--accent-color-rgb), 0.25) !important;
    }
    
    /* Comment button styles */
    .formatting-btn[data-command="comment"] {
      background-color: <?php echo $theme === 'light' ? '#ffffff' : '#2d2d2d'; ?>;
    }
    .formatting-btn[data-command="comment"].active {
      background-color: var(--accent-soft-strong);
      border-color: var(--accent-border);
      color: var(--accent-text);
    }
    
    .fullscreen-editor .dropdown-content {
  z-index: 100000 !important;
  position: fixed !important;
}

.fullscreen-editor .modal {
  z-index: 200000 !important;
}

.fullscreen-editor #template-dropdown,
.fullscreen-editor #insert-dropdown {
  position: fixed !important;
  max-height: 80vh !important;
  overflow-y: auto !important;
  top: 50% !important;
  left: 50% !important;
  transform: translate(-50%, -50%) !important;
  width: 90% !important;
  max-width: 400px !important;
  background: var(--panel-bg) !important;
  border-radius: 12px !important;
  box-shadow: 0 10px 40px rgba(0,0,0,0.3) !important;
}
#monaco-editor .view-lines {
  -webkit-user-select: text !important;
  user-select: text !important;
  touch-action: pan-y !important;  /* allow vertical scroll */
}

/* Enlarge the tap area for the cursor */
#monaco-editor .cursor {
  min-width: 3px !important;
}

/* Remove the conflict with the scrollbar on touch */
#monaco-editor .scrollbar {
  touch-action: none;
}
/* For mobile devices in fullscreen mode */
@media (max-width: 768px) {
  .fullscreen-editor .dropdown-content {
    width: 95% !important;
    max-height: 70vh !important;
  }
}

/* Styles for the code listing modal */
.listing-modal-content {
  max-width: 1200px;
  width: 95%;
  background: var(--panel-bg);
  border-radius: 16px;
  padding: 24px;
  overflow-x: hidden;
}

.listing-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.listing-editor-container {
  display: flex;
  flex-direction: column;
  gap: 10px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  overflow: hidden;
}

.listing-code-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  background: var(--btn-bg);
  border-bottom: 1px solid var(--border-color);
}

.listing-code-header h4 {
  margin: 0;
  color: var(--accent-color);
}

.listing-code-editor {
  height: 300px;
  width: 100%;
}

.listing-settings {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-top: 15px;
}

.listing-settings-group {
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 15px;
}

.listing-settings-group h5 {
  margin: 0 0 15px 0;
  color: var(--accent-color);
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 8px;
}

.listing-setting-row {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 15px;
}

.listing-setting-row label {
  font-weight: 600;
  font-size: 12px;
  color: var(--accent-color);
}

.listing-setting-row input,
.listing-setting-row select {
  padding: 8px 12px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  background: var(--btn-bg);
  color: var(--text-color);
  font-size: 13px;
}

.listing-setting-row input[type="color"] {
  height: 40px;
  padding: 4px;
  cursor: pointer;
}

.listing-setting-row.checkbox {
  flex-direction: row;
  align-items: center;
  gap: 8px;
}

.listing-setting-row.checkbox input {
  width: 16px;
  height: 16px;
}

.listing-preview {
  margin-top: 20px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 15px;
  background: var(--bg-color);
  max-height: 200px;
  overflow-y: auto;
}

.listing-preview h5 {
  margin: 0 0 10px 0;
  color: var(--accent-color);
  font-size: 14px;
}

.listing-preview pre {
  margin: 0;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-size: 12px;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.listing-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
}

@media (max-width: 768px) {
  .listing-settings {
    grid-template-columns: 1fr;
  }
  
  .listing-modal-content {
    padding: 16px;
  }
}

/* Add styles for the listing button */
#insert-listing-btn {
  background: var(--accent-soft);
  color: var(--accent-text);
  border-color: var(--accent-border);
}

#insert-listing-btn:hover {
  background: var(--accent-soft-strong);
}
  </style>
</head>
<body class="<?php 
  if ($isMobile) {
    echo $isTablet ? 'tablet-layout' : 'mobile-layout';
  } 
?>">
  <!-- Notification Container -->
  <div id="notification-container" class="notification-container"></div>

  <div id="template-modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><?php echo $t['select_template']; ?></div>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body" id="template-modal-body"></div>
    </div>
  </div>
  <div id="symbols-modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><?php echo $t['choose_symbol']; ?></div>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <h4><?php echo $t['greek_letters']; ?></h4>
        <div class="symbol-grid" id="greek-symbols"></div>
        <h4><?php echo $t['math_operators']; ?></h4>
        <div class="symbol-grid" id="math-symbols"></div>
        <h4><?php echo $t['arrows']; ?></h4>
        <div class="symbol-grid" id="arrow-symbols"></div>
        <h4><?php echo $t['relations']; ?></h4>
        <div class="symbol-grid" id="relation-symbols"></div>
        <h4><?php echo $t['other_symbols']; ?></h4>
        <div class="symbol-grid" id="other-symbols"></div>
      </div>
      <div class="modal-footer">
        <button class="btn" id="symbols-cancel"><?php echo $t['cancel']; ?></button>
      </div>
    </div>
  </div>
  <div id="table-modal" class="modal">
    <div class="modal-content" style="max-width: 95%; width: 95%;">
      <div class="modal-header">
        <div class="modal-title"><?php echo $t['table_editor']; ?></div>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="table-editor-container">
          <div class="table-editor-tabs">
            <div class="table-editor-tab active" data-tab="settings"><?php echo $t['table_settings']; ?></div>
            <div class="table-editor-tab" data-tab="latex">LaTeX Code</div>
          </div>
          <div class="table-editor-content active" id="settings-tab">
            <div class="table-settings-grid">
              <div class="table-setting">
                <label for="table-rows"><?php echo $t['table_rows']; ?></label>
                <input type="number" id="table-rows" min="1" max="20" value="3">
              </div>
              <div class="table-setting">
                <label for="table-cols"><?php echo $t['table_cols']; ?></label>
                <input type="number" id="table-cols" min="1" max="10" value="3">
              </div>
              <div class="table-setting">
                <label for="table-border"><?php echo $t['table_border']; ?></label>
                <select id="table-border">
                  <option value="normal"><?php echo $t['table_normal']; ?></option>
                  <option value="thick"><?php echo $t['table_thick']; ?></option>
                  <option value="none"><?php echo $t['table_none']; ?></option>
                </select>
              </div>
              <div class="table-setting">
                <label for="table-caption"><?php echo $t['table_caption']; ?></label>
                <input type="text" id="table-caption" placeholder="Table caption">
              </div>
              <div class="table-setting">
                <label for="table-label"><?php echo $t['table_label']; ?></label>
                <input type="text" id="table-label" placeholder="tab:example">
              </div>
            </div>
            <div style="grid-column: 1 / -1; height: 15px;"></div>
            <div class="table-controls-grid">
              <button class="table-control-btn" id="add-row"><?php echo $t['add_row']; ?></button>
              <button class="table-control-btn" id="add-column"><?php echo $t['add_column']; ?></button>
              <button class="table-control-btn" id="remove-row"><?php echo $t['remove_row']; ?></button>
              <button class="table-control-btn" id="remove-column"><?php echo $t['remove_column']; ?></button>
              <button class="table-control-btn" id="merge-cells"><?php echo $t['merge_cells']; ?></button>
              <button class="table-control-btn" id="split-cells"><?php echo $t['split_cells']; ?></button>
            </div>
            <div class="table-preview-container">
              <h4><?php echo $t['table_preview']; ?></h4>
              <table class="table-preview" id="table-preview"></table>
            </div>
          </div>
          <div class="table-editor-content" id="latex-tab">
            <div class="latex-preview" id="latex-preview"></div>
            <div style="text-align: center;">
              <button class="btn primary" id="insert-latex"><?php echo $t['insert']; ?></button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn" id="table-cancel"><?php echo $t['cancel']; ?></button>
        <button class="btn primary" id="generate-latex"><?php echo $t['generate_latex']; ?></button>
      </div>
    </div>
  </div>
  <div id="pdf-modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><?php echo $t['pdf_settings']; ?></div>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="pdf-settings-grid">
          <div class="pdf-setting">
            <label for="pdf-pages"><?php echo $t['pdf_pages']; ?></label>
            <input type="text" id="pdf-pages" placeholder="1-3,5,7-" value="-">
          </div>
          <div class="pdf-setting">
            <label for="pdf-scale"><?php echo $t['pdf_scale']; ?></label>
            <input type="number" id="pdf-scale" step="0.1" value="1.0">
          </div>
          <div class="pdf-setting">
            <label for="pdf-orientation"><?php echo $t['pdf_orientation']; ?></label>
            <select id="pdf-orientation">
              <option value="portrait"><?php echo $t['pdf_portrait']; ?></option>
              <option value="landscape"><?php echo $t['pdf_landscape']; ?></option>
            </select>
          </div>
        </div>
        <div class="pdf-options">
          <label><input type="checkbox" id="pdf-add-toc"> <?php echo $t['pdf_add_to_toc']; ?></label>
          <div id="pdf-toc-settings" style="margin-top: 10px; display: none;">
            <div class="pdf-settings-grid">
              <div class="pdf-setting">
                <label for="pdf-toc-level"><?php echo $t['pdf_toc_level']; ?></label>
                <select id="pdf-toc-level">
                  <option value="section"><?php echo $t['pdf_section']; ?></option>
                  <option value="subsection"><?php echo $t['pdf_subsection']; ?></option>
                  <option value="subsubsection"><?php echo $t['pdf_subsubsection']; ?></option>
                </select>
              </div>
              <div class="pdf-setting">
                <label for="pdf-toc-title"><?php echo $t['pdf_toc_title']; ?></label>
                <input type="text" id="pdf-toc-title" placeholder="Document Title">
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn" id="pdf-cancel"><?php echo $t['cancel']; ?></button>
        <button class="btn" id="pdf-insert-simple"><?php echo $t['pdf_just_insert']; ?></button>
        <button class="btn primary" id="pdf-insert"><?php echo $t['insert']; ?></button>
      </div>
    </div>
  </div>
  
  <!-- Translation Modal -->
  <div id="translation-modal" class="modal">
    <div class="modal-content translation-modal-content">
      <div class="modal-header">
        <div class="modal-title"><?php echo $t['translation_window']; ?></div>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="translation-container">
          <div class="translation-header">
            <div class="translation-lang-select">
              <label for="source-lang"><?php echo $t['source_lang']; ?></label>
              <select id="source-lang">
                <option value="auto"><?php echo $t['auto_detect']; ?></option>
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
              <label for="target-lang"><?php echo $t['target_lang']; ?></label>
              <select id="target-lang">
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
            <label for="source-text"><?php echo $t['translate']; ?></label>
            <textarea id="source-text" placeholder="<?php echo $t['translate']; ?>..."></textarea>
          </div>
          
          <div class="translation-text-box">
            <label for="target-text"><?php echo $t['translation']; ?></label>
            <textarea id="target-text" readonly placeholder="<?php echo $t['translation']; ?>..."></textarea>
          </div>
          
          <div class="translation-buttons">
            <button class="btn" id="translate-again-btn"><?php echo $t['translate_again']; ?></button>
            <button class="btn primary" id="translate-btn"><?php echo $t['translate_button']; ?></button>
            <button class="btn success" id="replace-text-btn"><?php echo $t['replace_text']; ?></button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Listing Modal -->
  <div id="listing-modal" class="modal">
    <div class="modal-content listing-modal-content">
      <div class="modal-header">
        <div class="modal-title"><?php echo $t['listing_editor']; ?></div>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="listing-container">
          <div class="listing-editor-container">
            <div class="listing-code-header">
              <h4><?php echo $t['listing_code']; ?></h4>
              <button class="btn" id="listing-copy-code" title="<?php echo $t['copy']; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
              </button>
            </div>
            <div id="listing-code-editor" class="listing-code-editor"></div>
          </div>
          
          <div class="listing-settings">
            <div class="listing-settings-group">
              <h5><?php echo $t['listing_general']; ?></h5>
              <div class="listing-setting-row">
                <label for="listing-language"><?php echo $t['listing_language']; ?></label>
                <select id="listing-language">
                  <option value="Python">Python</option>
                  <option value="C">C</option>
                  <option value="C++">C++</option>
                  <option value="Java">Java</option>
                  <option value="JavaScript">JavaScript</option>
                  <option value="PHP">PHP</option>
                  <option value="Ruby">Ruby</option>
                  <option value="Perl">Perl</option>
                  <option value="HTML">HTML</option>
                  <option value="CSS">CSS</option>
                  <option value="XML">XML</option>
                  <option value="SQL">SQL</option>
                  <option value="Bash">Bash</option>
                  <option value="Matlab">Matlab</option>
                  <option value="R">R</option>
                </select>
              </div>
              <div class="listing-setting-row">
                <label for="listing-caption"><?php echo $t['listing_caption']; ?></label>
                <input type="text" id="listing-caption" value="Source code">
              </div>
              <div class="listing-setting-row">
                <label for="listing-label"><?php echo $t['listing_label']; ?></label>
                <input type="text" id="listing-label" value="lst:code">
              </div>
            </div>
            
            <div class="listing-settings-group">
              <h5><?php echo $t['listing_basic_style']; ?></h5>
              <div class="listing-setting-row">
                <label for="listing-font-family"><?php echo $t['listing_font_family']; ?></label>
                <select id="listing-font-family">
                  <option value="ttfamily"><?php echo $t['font_ttfamily']; ?></option>
                  <option value="rmfamily"><?php echo $t['font_rmfamily']; ?></option>
                  <option value="sffamily"><?php echo $t['font_sffamily']; ?></option>
                </select>
              </div>
              <div class="listing-setting-row">
                <label for="listing-font-size"><?php echo $t['listing_font_size']; ?></label>
                <select id="listing-font-size">
                  <option value="tiny"><?php echo $t['font_tiny']; ?></option>
                  <option value="scriptsize"><?php echo $t['font_small']; ?></option>
                  <option value="small" selected><?php echo $t['font_normal']; ?></option>
                  <option value="normalsize"><?php echo $t['font_large']; ?></option>
                  <option value="large"><?php echo $t['font_huge']; ?></option>
                </select>
              </div>
              <div class="listing-setting-row">
                <label for="listing-tabsize"><?php echo $t['listing_tabsize']; ?></label>
                <input type="number" id="listing-tabsize" min="1" max="8" value="4">
              </div>
            </div>
            
            <div class="listing-settings-group">
              <h5><?php echo $t['listing_colors']; ?></h5>
              <div class="listing-setting-row">
                <label for="listing-keyword-color"><?php echo $t['listing_keyword_color']; ?></label>
                <input type="color" id="listing-keyword-color" value="#0000ff">
              </div>
              <div class="listing-setting-row">
                <label for="listing-comment-color"><?php echo $t['listing_comment_color']; ?></label>
                <input type="color" id="listing-comment-color" value="#008000">
              </div>
              <div class="listing-setting-row">
                <label for="listing-string-color"><?php echo $t['listing_string_color']; ?></label>
                <input type="color" id="listing-string-color" value="#ff0000">
              </div>
              <div class="listing-setting-row">
                <label for="listing-background"><?php echo $t['listing_background']; ?></label>
                <input type="color" id="listing-background" value="#f0f0f0">
              </div>
            </div>
            
            <div class="listing-settings-group">
              <h5><?php echo $t['listing_advanced']; ?></h5>
              <div class="listing-setting-row">
                <label for="listing-frame"><?php echo $t['listing_frame']; ?></label>
                <select id="listing-frame">
                  <option value="none"><?php echo $t['frame_none']; ?></option>
                  <option value="single" selected><?php echo $t['frame_single']; ?></option>
                  <option value="double"><?php echo $t['frame_double']; ?></option>
                  <option value="shadowbox"><?php echo $t['frame_shadowbox']; ?></option>
                </select>
              </div>
              <div class="listing-setting-row">
                <label for="listing-numbers"><?php echo $t['listing_numbers']; ?></label>
                <select id="listing-numbers">
                  <option value="none"><?php echo $t['numbers_none']; ?></option>
                  <option value="left" selected><?php echo $t['numbers_left']; ?></option>
                  <option value="right"><?php echo $t['numbers_right']; ?></option>
                </select>
              </div>
              <div class="listing-setting-row">
                <label for="listing-number-style"><?php echo $t['listing_number_style']; ?></label>
                <select id="listing-number-style">
                  <option value="tiny"><?php echo $t['font_tiny']; ?></option>
                  <option value="scriptsize"><?php echo $t['font_small']; ?></option>
                  <option value="small" selected><?php echo $t['font_normal']; ?></option>
                </select>
              </div>
              <div class="listing-setting-row">
                <label for="listing-stepnumber"><?php echo $t['listing_stepnumber']; ?></label>
                <input type="number" id="listing-stepnumber" min="1" max="10" value="1">
              </div>
              <div class="listing-setting-row">
                <label for="listing-captionpos"><?php echo $t['listing_captionpos']; ?></label>
                <select id="listing-captionpos">
                  <option value="t"><?php echo $t['position_top']; ?></option>
                  <option value="b" selected><?php echo $t['position_bottom']; ?></option>
                </select>
              </div>
              <div class="listing-setting-row checkbox">
                <input type="checkbox" id="listing-breaklines" checked>
                <label for="listing-breaklines"><?php echo $t['listing_breaklines']; ?></label>
              </div>
              <div class="listing-setting-row checkbox">
                <input type="checkbox" id="listing-showspaces">
                <label for="listing-showspaces"><?php echo $t['listing_showspaces']; ?></label>
              </div>
              <div class="listing-setting-row checkbox">
                <input type="checkbox" id="listing-showtabs">
                <label for="listing-showtabs"><?php echo $t['listing_showtabs']; ?></label>
              </div>
              <div class="listing-setting-row checkbox">
                <input type="checkbox" id="listing-showstringspaces">
                <label for="listing-showstringspaces"><?php echo $t['listing_showstringspaces']; ?></label>
              </div>
            </div>
          </div>
          
          <div class="listing-preview">
            <h5><?php echo $t['listing_preview']; ?></h5>
            <pre id="listing-preview-code"></pre>
          </div>
        </div>
      </div>
      <div class="listing-footer">
        <button class="btn" id="listing-cancel"><?php echo $t['cancel']; ?></button>
        <button class="btn primary" id="listing-insert"><?php echo $t['listing_insert']; ?></button>
      </div>
    </div>
  </div>
  
  <div id="notification-modal" class="modal">
    <div class="modal-content notification-modal">
      <div class="modal-body" style="text-align: center;">
        <div class="notification-icon">ℹ️</div>
        <div id="notification-message">Сообщение</div>
      </div>
      <div class="modal-footer" style="justify-content: center;">
        <button class="btn primary" id="notification-ok">OK</button>
      </div>
    </div>
  </div>
  <div id="mobile-header">
    <div id="mobile-title"><?php echo $t['mobile_title'] . htmlspecialchars(basename($filePath)); ?></div>
    <div id="mobile-status" class="status-ok"><?php echo $t['ready']; ?></div>
  </div>
  <div id="main">
    <div id="editor-panel">
      <div id="formatting-toolbar" class="formatting-toolbar">
        <div class="toolbar-group">
          <button class="formatting-btn" data-command="bold" title="<?php echo $t['bold']; ?>"><i class="fas fa-bold"></i></button>
          <button class="formatting-btn" data-command="italic" title="<?php echo $t['italic']; ?>"><i class="fas fa-italic"></i></button>
          <button class="formatting-btn" data-command="underline" title="<?php echo $t['underline']; ?>"><i class="fas fa-underline"></i></button>
          <button class="formatting-btn" data-command="strikethrough" title="<?php echo $t['strikethrough']; ?>"><i class="fas fa-strikethrough"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="formatting-btn" data-command="alignLeft" title="<?php echo $t['align_left']; ?>"><i class="fas fa-align-left"></i></button>
          <button class="formatting-btn" data-command="alignCenter" title="<?php echo $t['align_center']; ?>"><i class="fas fa-align-center"></i></button>
          <button class="formatting-btn" data-command="alignRight" title="<?php echo $t['align_right']; ?>"><i class="fas fa-align-right"></i></button>
          <button class="formatting-btn" data-command="alignJustify" title="<?php echo $t['align_justify']; ?>"><i class="fas fa-align-justify"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="formatting-btn" data-command="superscript" title="<?php echo $t['superscript']; ?>"><i class="fas fa-superscript"></i></button>
          <button class="formatting-btn" data-command="subscript" title="<?php echo $t['subscript']; ?>"><i class="fas fa-subscript"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="formatting-btn" data-command="quote" title="<?php echo $t['quote']; ?>"><i class="fas fa-quote-right"></i></button>
          <button class="formatting-btn" data-command="comment" title="<?php echo $t['comment']; ?>"><i class="fas fa-comment"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="formatting-btn" data-command="clearFormatting" title="<?php echo $t['clear_formatting']; ?>"><i class="fas fa-eraser"></i></button>
          <button class="formatting-btn" data-command="undo" title="<?php echo $t['undo']; ?>"><i class="fas fa-undo"></i></button>
          <button class="formatting-btn" data-command="redo" title="<?php echo $t['redo']; ?>"><i class="fas fa-redo"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="formatting-btn" id="translate-btn-toolbar" data-command="translate" title="<?php echo $t['translate']; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-languages-icon lucide-languages"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>
          </button>
        </div>
      </div>
      <div id="helper-bar">
        <button id="compile-btn" class="btn">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <path d="M7 4v16l13 -8z"/>
          </svg>
          <?php echo $t['compile']; ?>
        </button>
        <button id="save-pdf-btn" class="btn">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
            <?php echo $t['save_pdf']; ?>
        </button>
        <button id="fullscreen-btn" class="btn"><?php echo $t['fullscreen_editor']; ?></button>
        <div class="dropdown">
          <button id="template-btn" class="btn"><?php echo $t['select_template']; ?></button>
          <div id="template-dropdown" class="dropdown-content">
            <?php if (!empty($templatesData['templates'])): ?>
              <?php foreach ($templatesData['templates'] as $key => $template): ?>
                <div class="dropdown-item" data-value="<?php echo $key; ?>">
                  <?php echo htmlspecialchars($template['names'][$lang] ?? $template['names']['en']); ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="dropdown-item"><?php echo $t['no_templates']; ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="dropdown">
          <button id="insert-btn" class="btn"><?php echo $t['insert_element']; ?></button>
          <div id="insert-dropdown" class="dropdown-content">
            <div class="dropdown-group"><?php echo $t['document_structure']; ?></div>
            <div class="dropdown-item" data-value="part"><?php echo $t['part']; ?></div>
            <div class="dropdown-item" data-value="chapter"><?php echo $t['chapter']; ?></div>
            <div class="dropdown-item" data-value="section"><?php echo $t['section']; ?></div>
            <div class="dropdown-item" data-value="subsection"><?php echo $t['subsection']; ?></div>
            <div class="dropdown-item" data-value="subsubsection"><?php echo $t['subsubsection']; ?></div>
            <div class="dropdown-group"><?php echo $t['environments']; ?></div>
            <div class="dropdown-item" data-value="figure"><?php echo $t['figure']; ?></div>
            <div class="dropdown-item" data-value="table"><?php echo $t['table']; ?></div>
            <div class="dropdown-item" data-value="itemize"><?php echo $t['itemize']; ?></div>
            <div class="dropdown-item" data-value="enumerate"><?php echo $t['enumerate']; ?></div>
            <div class="dropdown-item" data-value="verbatim"><?php echo $t['verbatim']; ?></div>
            <div class="dropdown-group"><?php echo $t['mathematics']; ?></div>
            <div class="dropdown-item" data-value="equation"><?php echo $t['equation']; ?></div>
            <div class="dropdown-item" data-value="align"><?php echo $t['align']; ?></div>
            <div class="dropdown-item" data-value="frac"><?php echo $t['frac']; ?></div>
            <div class="dropdown-item" data-value="sqrt"><?php echo $t['sqrt']; ?></div>
            <div class="dropdown-item" data-value="matrix"><?php echo $t['matrix']; ?></div>
            <div class="dropdown-item" data-value="pmatrix"><?php echo $t['pmatrix']; ?></div>
            <div class="dropdown-item" data-value="sum"><?php echo $t['sum']; ?></div>
            <div class="dropdown-item" data-value="int"><?php echo $t['int']; ?></div>
            <div class="dropdown-item" data-value="symbols"><?php echo $t['symbols']; ?></div>
            <div class="dropdown-group"><?php echo $t['formatting_references']; ?></div>
            <div class="dropdown-item" data-value="textbf"><?php echo $t['textbf']; ?></div>
            <div class="dropdown-item" data-value="textit"><?php echo $t['textit']; ?></div>
            <div class="dropdown-item" data-value="label"><?php echo $t['label']; ?></div>
            <div class="dropdown-item" data-value="ref"><?php echo $t['ref']; ?></div>
            <div class="dropdown-item" data-value="insert_pdf"><?php echo $t['insert_pdf']; ?></div>
            <!-- Add an item for the code listing -->
            <div class="dropdown-item" data-value="listing"><?php echo $t['insert_listing']; ?></div>
          </div>
        </div>
        <div id="status-indicator" class="status-ok"><?php echo $t['ready']; ?></div>
      </div>
      <div id="latex-editor-container">
        <div id="monaco-editor"></div>
      </div>
    </div>
    <div id="resizer"></div>
    <div id="pdf-panel">
      <div id="pdf-view">
        <div id="pdf-placeholder"><?php echo $t['pdf_will_appear']; ?></div>
      </div>
    </div>
  </div>
  <div id="mobile-footer">
    <button id="mobile-compile-btn" class="mobile-btn primary">
      <span class="btn-status-indicator"></span>
      <?php echo $t['compile']; ?>
    </button>
    <button id="mobile-save-pdf-btn" class="mobile-btn success"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> &nbsp;<?php echo $t['save_pdf']; ?></button>
    <button id="mobile-insert-btn" class="mobile-btn"><?php echo $t['mobile_insert']; ?></button>
    <button id="mobile-fullscreen-btn" class="mobile-btn"><?php echo $t['fullscreen_editor']; ?></button>
  </div>
  <div id="mobile-insert-menu">
    <div class="mobile-insert-group">
      <h4><?php echo $t['document_structure']; ?></h4>
      <button class="mobile-insert-btn" data-value="part"><?php echo $t['part']; ?></button>
      <button class="mobile-insert-btn" data-value="chapter"><?php echo $t['chapter']; ?></button>
      <button class="mobile-insert-btn" data-value="section"><?php echo $t['section']; ?></button>
      <button class="mobile-insert-btn" data-value="subsection"><?php echo $t['subsection']; ?></button>
      <button class="mobile-insert-btn" data-value="subsubsection"><?php echo $t['subsubsection']; ?></button>
    </div>
    <div class="mobile-insert-group">
      <h4><?php echo $t['environments']; ?></h4>
      <button class="mobile-insert-btn" data-value="figure"><?php echo $t['figure']; ?></button>
      <button class="mobile-insert-btn" data-value="table"><?php echo $t['table']; ?></button>
      <button class="mobile-insert-btn" data-value="itemize"><?php echo $t['itemize_list']; ?></button>
      <button class="mobile-insert-btn" data-value="enumerate"><?php echo $t['enumerate_list']; ?></button>
      <button class="mobile-insert-btn" data-value="verbatim"><?php echo $t['verbatim']; ?></button>
    </div>
    <div class="mobile-insert-group">
      <h4><?php echo $t['mathematics']; ?></h4>
      <button class="mobile-insert-btn" data-value="equation"><?php echo $t['equation']; ?></button>
      <button class="mobile-insert-btn" data-value="align"><?php echo $t['align']; ?></button>
      <button class="mobile-insert-btn" data-value="frac"><?php echo $t['frac']; ?></button>
      <button class="mobile-insert-btn" data-value="sqrt"><?php echo $t['sqrt']; ?></button>
      <button class="mobile-insert-btn" data-value="matrix"><?php echo $t['matrix']; ?></button>
      <button class="mobile-insert-btn" data-value="pmatrix"><?php echo $t['pmatrix']; ?></button>
      <button class="mobile-insert-btn" data-value="sum"><?php echo $t['sum']; ?></button>
      <button class="mobile-insert-btn" data-value="int"><?php echo $t['int']; ?></button>
      <button class="mobile-insert-btn" data-value="symbols"><?php echo $t['symbols']; ?></button>
    </div>
    <div class="mobile-insert-group">
      <h4><?php echo $t['formatting_references']; ?></h4>
      <button class="mobile-insert-btn" data-value="textbf"><?php echo $t['textbf']; ?></button>
      <button class="mobile-insert-btn" data-value="textit"><?php echo $t['textit']; ?></button>
      <button class="mobile-insert-btn" data-value="label"><?php echo $t['label']; ?></button>
      <button class="mobile-insert-btn" data-value="ref"><?php echo $t['ref']; ?></button>
      <button class="mobile-insert-btn" data-value="insert_pdf"><?php echo $t['insert_pdf']; ?></button>
      <!-- Add an item for the code listing in the mobile menu -->
      <button class="mobile-insert-btn" data-value="listing"><?php echo $t['insert_listing']; ?></button>
    </div>
  </div>
  <div id="mobile-hint">
    <?php echo $t['mobile_hint']; ?>
  </div>
  
  
    <!-- PDF.js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
  <script>
<?php require __DIR__ . '/../public/assets/js/page-script1.js.php'; ?>
</script>

  
  <!-- Monaco Editor Scripts -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/loader.min.js"></script>
  <script src="<?= htmlspecialchars($assetsBaseUrl) ?>/monaco-touch-selection.js"></script>
  <script src="<?= htmlspecialchars($assetsBaseUrl) ?>/monaco-ai-iframe.js"></script>

  
  <script>
<?php require __DIR__ . '/../public/assets/js/page-script2.js.php'; ?>
</script>
</body>
</html>
