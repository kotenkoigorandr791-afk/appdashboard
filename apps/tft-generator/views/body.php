<body class="dark">
    <canvas id="gears-bg"></canvas>

    <div id="top-bar">
        <div class="toolbar-edge toolbar-edge--left">
        <button onclick="toggleLeftPanel()" data-key="settings_btn"><i class="fas fa-bars"></i></button>
        </div>
        <div class="toolbar-tools">
        <button id="tool-point" onclick="setTool('point')" data-key="point"><i class="fas fa-circle-dot"></i> <span>Point</span></button>
        <button id="tool-line" onclick="setTool('line')" data-key="line"><i class="fas fa-slash"></i> <span>Line</span></button>
        <button id="tool-circle" onclick="setTool('circle')" data-key="circle"><i class="fas fa-circle"></i> <span>Circle</span></button>
        <button id="tool-rect" onclick="setTool('rect')" data-key="rect"><i class="fas fa-square-full"></i> <span>Rect</span></button>
        <button id="tool-roundrect" onclick="setTool('roundrect')" data-key="roundrect"><i class="fas fa-square" style="border-radius: 4px;"></i> <span>Round Rect</span></button>
        <button id="tool-triangle" onclick="setTool('triangle')" data-key="triangle"><svg class="toolbar-triangle-icon" viewBox="0 0 16 16" aria-hidden="true"><polygon fill="currentColor" points="8 2 14 14 2 14"/></svg> <span>Triangle</span></button>
        <button id="tool-dimension" onclick="setTool('dimension')" data-key="dimension"><i class="fas fa-ruler-combined"></i> <span data-key="dimension">Dimension</span></button>
        <button id="tool-selection" onclick="setTool('selection')" data-key="selection"><i class="fas fa-mouse-pointer"></i> <span>Selection</span></button>
        <button id="tool-text" onclick="setTool('text')" data-key="text" title="Text tool (T)"><i class="fas fa-font"></i> <span data-key="text"></span></button>
        <button id="tool-move" onclick="setTool('move')" data-key="move"><i class="fas fa-arrows-alt"></i> <span>Move</span></button>
        <button id="tool-eraser" onclick="setTool('eraser')" data-key="eraser"><i class="fas fa-eraser"></i> <span>Eraser</span></button>
        <button id="tool-clear" onclick="clearCanvas()" data-key="clear_canvas"><i class="fas fa-trash"></i> <span>Clear Canvas</span></button>
        <button id="btn-undo" onclick="undo()" data-key="undo" title="Undo (Ctrl+Z)"><i class="fas fa-undo"></i> <span data-key="undo_btn">Undo</span></button>
        <button id="btn-redo" onclick="redo()" data-key="redo" title="Redo (Ctrl+Y)"><i class="fas fa-redo"></i> <span data-key="redo_btn">Redo</span></button>
        </div>
        <div class="toolbar-edge toolbar-edge--right">
        <button onclick="toggleRightPanel()" data-key="drawing_settings_btn"><i class="fas fa-palette"></i></button>
        </div>
    </div>
    <div class="canvas-container">
        <div class="canvas-with-rulers" id="draw-viewport">
            <div class="ruler-corner"></div>
            <canvas class="ruler-skin" id="ruler-top" width="400" height="30"></canvas>
            <div class="ruler-corner"></div>
            <canvas class="ruler-skin" id="ruler-left" width="30" height="400"></canvas>
            <div class="canvas-cell"><canvas id="canvas"></canvas></div>
            <canvas class="ruler-skin" id="ruler-right" width="30" height="400"></canvas>
            <div class="ruler-corner"></div>
            <canvas class="ruler-skin" id="ruler-bottom" width="400" height="30"></canvas>
            <div class="ruler-corner"></div>
        </div>
    </div>
    <div id="dim-edit-modal" style="display:none;">
        <button type="button" class="modal-close-btn" id="close-dim-edit" onclick="closeDimEditModal()" aria-label="Close">&times;</button>
        <label data-key="dim_edit_label">Length (px):</label>
        <input type="number" id="dim-value-input" min="1" step="1" onkeydown="event.stopPropagation(); if(event.key==='Escape')closeDimEditModal(); if(event.key==='Enter'){event.preventDefault();applyDimEditFromModal();}">
        <button type="button" onclick="applyDimEditFromModal()" data-key="dim_edit_apply">Apply</button>
    </div>
    <div id="dim-place-modal" style="display:none;">
        <button type="button" class="modal-close-btn" id="close-dim-place" onclick="closeDimPlaceModal(true)" aria-label="Close">&times;</button>
        <label data-key="dim_offset_label">Offset (px)</label>
        <input type="number" id="dim-offset-input" step="1" oninput="onDimOffsetFieldInput()" onkeydown="event.stopPropagation(); if(event.key==='Escape')closeDimPlaceModal(true);">
        <label data-key="dim_length_label">Length (px)</label>
        <input type="number" id="dim-length-live-input" min="1" step="1" oninput="onDimLengthLiveInput()" onkeydown="event.stopPropagation(); if(event.key==='Escape')closeDimPlaceModal(true); if(event.key==='Enter'){event.preventDefault();confirmDimPlaceModal();}">
        <button type="button" onclick="confirmDimPlaceModal()" data-key="dim_place_ok">OK</button>
    </div>
    <div id="output-dock">
        <div class="output-dock-top">
            <div class="output-dock-xy-group">
                <span class="output-dock-xy-label" data-key="xy_position">XY position</span>
                <span id="canvas-coords">X: —  Y: —</span>
            </div>
            <button type="button" id="generate-code" onclick="generateCode()" data-key="generate_code">Generate Code</button>
        </div>
        <div class="output-dock-code">
            <pre><code id="code_output" class="language-cpp"></code></pre>
            <button type="button" id="copy-code" onclick="copyCode()" title="">
                <svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><polyline points="20 6 9 17 4 12"/></svg>
            </button>
        </div>
        <div id="footer" data-key="footer"><i>Created by Ihor Kotenko ©</i></div>
    </div>
    <div id="left-panel">
        <div class="mobile-panel-header">
            <span data-key="settings">Settings</span>
            <button class="mobile-close" onclick="toggleLeftPanel()">&times;</button>
        </div>
        <div class="panel-body-scroll">
        <label data-key="screen_object">Screen Object:</label>
        <input id="screen-object" type="text" value="tft" onchange="saveConfig()">
        <hr>
        <label data-key="canvas_width">Canvas Width:</label>
        <input id="cwidth" type="number" value="320" min="1" onchange="saveConfig()">
        <hr>
        <label data-key="canvas_height">Canvas Height:</label>
        <input id="cheight" type="number" value="240" min="1" onchange="saveConfig()">
        <hr>
        <button onclick="setCanvasSize()" data-key="set_size">Set Size</button>
        <hr>
        <label data-key="bg_color">Background Color:</label>
        <input id="bgcolor" type="color" value="#000000" onchange="setBgColor(this.value)">
        <hr>
        <label data-key="scale">Scale:</label>
        <input id="scale" type="range" min="0.1" max="10" step="0.1" value="1" onchange="setScale(this.value)">
        <hr>
        <label data-key="origin">Origin:</label>
        <select id="origin-select" onchange="setOrigin(this.value)">
            <option value="top-left" data-key="top_left">Top-Left</option>
            <option value="top-right" data-key="top_right">Top-Right</option>
            <option value="bottom-left" data-key="bottom_left">Bottom-Left</option>
            <option value="bottom-right" data-key="bottom_right">Bottom-Right</option>
        </select>
        <hr>
        </div>
        <div class="panel-footer panel-footer--left">
            <button type="button" id="hotkeys-panel-btn" onclick="openHotkeysModal()" title="Hotkeys"><i class="fas fa-circle-info"></i></button>
        </div>
        <input type="checkbox" id="touch-mode" style="display:none">
    </div>
    <div id="right-panel">
        <div class="mobile-panel-header">
            <span data-key="drawing_settings">Drawing Settings</span>
            <button class="mobile-close" onclick="toggleRightPanel()">&times;</button>
        </div>
        <div class="panel-body-scroll">
        <div class="fill-container"><label data-key="fill">Fill:</label><label class="switch"><input type="checkbox" id="fill" onchange="saveConfig()"><span class="slider"></span></label></div>
        <hr>
        <label data-key="corner_radius">Corner Radius:</label>
        <input id="radius" type="number" value="5" min="0" onchange="saveConfig()">
        <hr>
        <label data-key="draw_color">Drawing Color:</label>
        <input id="drawcolor" type="color" value="#ffffff" onchange="currentColor = this.value; saveConfig(); redraw();">
        <hr>
        <div id="quick-colors">
            <div class="quick-color" style="background-color: #ff0000" data-color="0xF800" onclick="setQuickColor(this)" oncontextmenu="openColorModal(this); return false;"></div>
            <div class="quick-color" style="background-color: #00ff00" data-color="0x07E0" onclick="setQuickColor(this)" oncontextmenu="openColorModal(this); return false;"></div>
            <div class="quick-color" style="background-color: #0000ff" data-color="0x001F" onclick="setQuickColor(this)" oncontextmenu="openColorModal(this); return false;"></div>
            <div class="quick-color" style="background-color: #ffff00" data-color="0xFFE0" onclick="setQuickColor(this)" oncontextmenu="openColorModal(this); return false;"></div>
            <div class="quick-color" style="background-color: #ff00ff" data-color="0xF81F" onclick="setQuickColor(this)" oncontextmenu="openColorModal(this); return false;"></div>
        </div>
        <hr>
        <div class="cursor-container">
            <label data-key="cursor_constrain">Constrain Cursor:</label>
            <label class="switch">
                <input type="checkbox" id="cursor-constrain" onchange="updateCursorConstrain(); saveConfig();">
                <span class="slider"></span>
            </label>
        </div>
        <hr>
        <div class="snap-container">
            <label data-key="object_snap">Object Snap:</label>
            <label class="switch">
                <input type="checkbox" id="object-snap" onchange="updateObjectSnap(); saveConfig();">
                <span class="slider"></span>
            </label>
        </div>
        </div>
        <div class="panel-footer panel-footer--right">
            <button type="button" id="popup-toggle" onclick="togglePopup()" title="Menu"><i class="fas fa-ellipsis-v"></i></button>
        </div>
    </div>
    <div id="popup">
        <button type="button" class="modal-close-btn" id="close-popup" onclick="togglePopup()" aria-label="Close">&times;</button>
        <button onclick="document.getElementById('load-png').click()" data-key="load_png"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> Load PNG</button>
        <input id="load-png" type="file" accept="image/png" style="display:none" onchange="loadBgImage(this.files[0])">
        <button onclick="saveCanvas()" data-key="save_canvas"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Save Canvas</button>
        <button onclick="saveConfigFile()" data-key="save_config"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Config</button>
        <button onclick="document.getElementById('load-config').click()" data-key="load_config"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> Load Config</button>
        <input id="load-config" type="file" accept="application/json" style="display:none" onchange="loadConfigFile(this.files[0])">
        <button onclick="openCodeModal()" data-key="load_code"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg> Load Code</button>
        <button onclick="openColorVarsModal()" title="Color Variables Database"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.9 0 1.5-.7 1.5-1.5 0-.4-.1-.7-.4-1-.3-.3-.4-.7-.4-1.1 0-.8.7-1.5 1.5-1.5H16c3.3 0 6-2.7 6-6 0-5.5-4.5-9-10-9z"/></svg> Color Variables</button>
        <button onclick="openGearsSettings()" title="Gears Settings"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/></svg> Gears Animation</button>
    </div>
    <div id="gears-settings-modal">
        <button type="button" class="modal-close-btn" id="close-gears-settings" onclick="closeGearsSettings()" aria-label="Close">&times;</button>
        <h3><i class="fas fa-cogs"></i> Gears Animation Settings</h3>
        <hr>
        <label for="gears-speed">Animation Speed:</label>
        <div class="slider-label-container">
            <span id="speed-value">Normal</span>
        </div>
        <input id="gears-speed" type="range" min="0" max="2" step="0.1" value="1">
        <hr>
        <label for="gears-count">Number of Gears:</label>
        <div class="slider-label-container">
            <span id="count-value">Medium</span>
        </div>
        <input id="gears-count" type="range" min="0" max="2" step="0.1" value="1">
        <hr>
        <label for="gears-panel-opacity">Panel Opacity:</label>
        <div class="slider-label-container">
            <span id="opacity-value">0.8</span>
        </div>
        <input id="gears-panel-opacity" type="range" min="0.1" max="1" step="0.05" value="0.8">
        <hr>
        <label>Gear Color Mode:</label>
        <div class="color-mode-options">
            <label class="radio-option">
                <input type="radio" name="gears-color" value="random" checked>
                <span>Random Colors</span>
            </label>
            <label class="radio-option">
                <input type="radio" name="gears-color" value="solid">
                <span>Solid Color</span>
            </label>
        </div>
        <div id="gears-solid-color" style="display:none;">
            <label for="gears-color-picker">Choose Color:</label>
            <input id="gears-color-picker" type="color" value="#8888ff">
        </div>
        <hr>
        <div class="fill-container"><label data-key="gears_enable">Enable Animation:</label><label class="switch"><input type="checkbox" id="gears-enable" checked onchange="quickSaveGearsToggle()"><span class="slider"></span></label></div>
        <hr>
        <button onclick="saveGearsSettings()" class="save-btn"><i class="fas fa-save"></i> Save Settings</button>
        <button onclick="applyGearsDefaults()" class="defaults-btn"><i class="fas fa-undo"></i> Reset to Defaults</button>
    </div>
    <div id="color-modal">
        <button type="button" class="modal-close-btn" id="close-color-modal" onclick="closeColorModal()" aria-label="Close">&times;</button>
        <label data-key="select_color">Select Color:</label>
        <input id="color-input" type="color">
        <label data-key="enter_color">Enter Color (e.g., #FF0000, rgb(255,0,0), red):</label>
        <input id="color-text" type="text">
        <button onclick="applyColor()" data-key="apply_color">Apply</button>
    </div>

    <!-- Color Variable Prompt Modal (shown during code load for unknown colors) -->
    <div id="color-var-prompt-modal">
        <button type="button" class="modal-close-btn" id="close-color-var-prompt" onclick="closeColorVarPrompt()" aria-label="Close">&times;</button>
        <h3><i class="fas fa-question-circle"></i> Unknown Color Variable</h3>
        <hr>
        <p id="color-var-prompt-message">Enter the 565 color code or pick a color:</p>
        <label>Variable name:</label>
        <input id="color-var-name" type="text" readonly style="background:#555; font-weight:bold;">
        <label>565 Color Code (e.g., 0xF800):</label>
        <input id="color-var-code" type="text" placeholder="0xF800">
        <label>Or pick a color:</label>
        <div id="color-var-palette" style="display:flex; gap:5px; flex-wrap:wrap; margin:10px 0;">
            <div class="quickcolor" style="background:#ff0000" data-code="0xF800" onclick="pickColorVarColor(this)"></div>
            <div class="quickcolor" style="background:#00ff00" data-code="0x07E0" onclick="pickColorVarColor(this)"></div>
            <div class="quickcolor" style="background:#0000ff" data-code="0x001F" onclick="pickColorVarColor(this)"></div>
            <div class="quickcolor" style="background:#ffff00" data-code="0xFFE0" onclick="pickColorVarColor(this)"></div>
            <div class="quickcolor" style="background:#ff00ff" data-code="0xF81F" onclick="pickColorVarColor(this)"></div>
            <div class="quickcolor" style="background:#00ffff" data-code="0x07FF" onclick="pickColorVarColor(this)"></div>
            <div class="quickcolor" style="background:#ffffff" data-code="0xFFFF" onclick="pickColorVarColor(this)"></div>
            <div class="quickcolor" style="background:#000000" data-code="0x0000" onclick="pickColorVarColor(this)"></div>
            <div class="quickcolor" style="background:#ffa500" data-code="0xFD20" onclick="pickColorVarColor(this)"></div>
            <div class="quickcolor" style="background:#800080" data-code="0x8010" onclick="pickColorVarColor(this)"></div>
        </div>
        <input id="color-var-picker" type="color" value="#ff0000">
        <div style="display:flex; gap:10px; margin-top:10px;">
            <button onclick="saveColorVar()" style="flex:1;"><i class="fas fa-save"></i> Save & Continue</button>
            <button onclick="skipColorVar()" style="flex:1; background:#666;">Skip this variable</button>
        </div>
    </div>

    <!-- Color Variables Database Modal -->
    <div id="colorvars-modal">
        <button type="button" class="modal-close-btn" id="close-colorvars-modal" onclick="closeColorVarsModal()" aria-label="Close">&times;</button>
        <h3><i class="fas fa-palette"></i> Color Variables Database</h3>
        <hr>
        <div style="display:flex; gap:8px; margin-bottom:10px; flex-wrap:wrap;">
            <input id="new-colorvar-name" type="text" placeholder="Variable name (e.g., Maincolor)" style="flex:1; min-width:140px;">
            <input id="new-colorvar-code" type="text" placeholder="0xF800" style="width:90px;">
            <input id="new-colorvar-hex" type="color" value="#ff0000" style="width:40px; height:36px; cursor:pointer;">
            <button onclick="addColorVar()" style="white-space:nowrap;"><i class="fas fa-plus"></i> Add</button>
        </div>
        <hr>
        <div id="colorvars-list" style="max-height:50vh; overflow-y:auto;"></div>
        <hr>
        <button onclick="exportColorVars()" style="width:48%;"><i class="fas fa-download"></i> Export</button>
        <button onclick="document.getElementById('import-colorvars').click()" style="width:48%;"><i class="fas fa-upload"></i> Import</button>
        <input id="import-colorvars" type="file" accept="application/json" style="display:none" onchange="importColorVars(this.files[0])">
    </div>
    <div id="code-modal">
        <div id="code-modal-header">
            <span data-key="paste_code">Paste TFT Code:</span>
            <button type="button" class="modal-close-btn" id="close-code-modal" onclick="closeCodeModal()" aria-label="Close">&times;</button>
        </div>
        <div id="code-modal-toolbar">
            <button onclick="codeModalPaste()" title="Paste"><i class="fas fa-paste"></i> Paste</button>
            <button onclick="codeModalSelectAll()" title="Select All"><i class="fas fa-check-square"></i> Select All</button>
            <button onclick="codeModalClear()" title="Clear"><i class="fas fa-times"></i> Clear</button>
            <span id="code-load-status" style="margin-left: auto; display: none; padding: 4px 12px; border-radius: 4px; font-size: 13px;"></span>
        </div>
        <div id="monaco-editor-container"></div>
        <div id="code-modal-footer">
            <button onclick="loadCode()" data-key="load_code"><i class="fas fa-upload"></i> Load</button>
            <button onclick="viewInsertionStatus()" title="View what was inserted"><i class="fas fa-info-circle"></i> Status</button>
            <button onclick="closeCodeModal()">Close</button>
        </div>
    </div>
    <div id="text-modal">
        <button type="button" class="modal-close-btn" id="close-text-modal" onclick="closeTextModal()" aria-label="Close">&times;</button>
        <label data-key="enter_text">Enter Text:</label>
        <input id="text-input" type="text" placeholder="Enter text here">
        <label data-key="text_size">Text Size:</label>
        <select id="text-size-select">
            <option value="1">1x (6x8 px)</option>
            <option value="2">2x (12x16 px)</option>
            <option value="4">4x (24x32 px)</option>
            <option value="8">8x (48x64 px)</option>
        </select>
        <button onclick="applyText()" data-key="apply">Apply</button>
    </div>
    <div id="toast"></div>
    <div id="snap-indicator"></div>
    
    <div id="hotkeys-overlay" onclick="closeHotkeysModal()"></div>
    <div id="hotkeys-modal" class="modal">
        <div class="modal-content">
            <button type="button" class="modal-close-btn" onclick="closeHotkeysModal()" aria-label="Close">&times;</button>
            <h3 data-key="hotkeys_title">Hotkeys</h3>
            <ul>
                <li data-key="point_hotkey">Point: P</li>
                <li data-key="line_hotkey">Line: E</li>
                <li data-key="circle_hotkey">Circle: C</li>
                <li data-key="rect_hotkey">Rectangle: R</li>
                <li data-key="roundrect_hotkey">Round Rectangle: O</li>
                <li data-key="triangle_hotkey">Triangle: T</li>
                <li data-key="selection_hotkey">Selection: S</li>
                <li data-key="text_hotkey">Text: T</li>
                <li data-key="move_hotkey">Move: M</li>
                <li data-key="eraser_hotkey">Eraser: D</li>
                <li data-key="clear_canvas_hotkey">Clear Canvas: X</li>
                <li data-key="undo_hotkey">Undo: Ctrl+Z</li>
                <li data-key="redo_hotkey">Redo: Ctrl+Y / Ctrl+Shift+Z</li>
                <li data-key="dimension_hotkey">Dimension: I</li>
                <li data-key="copy_shapes_hotkey">Copy shapes: Ctrl+C</li>
                <li data-key="paste_shapes_hotkey">Paste shapes: Ctrl+V</li>
            </ul>
        </div>
    </div>

