    <script>
        hljs.highlightAll();
        var canvas = document.getElementById('canvas');
        var ctx = canvas.getContext('2d');
        var shapes = [];
        var currentTool = null;
        var drawing = false;
        var startX, startY;
        var triPoints = [];
        var dimDraft = null;
        var dimPlacing = false;
        var dimPendingOff = 24;
        var shapeClipboard = null;
        var shapeClipboardSourceIndices = [];
        var dimEditingIndex = -1;
        var dimPlaceIsNew = false;
        var dimLengthLiveApplyTimer = null;
        var dimHoverCanvasX = null;
        var dimHoverCanvasY = null;
        var bgColor = '#000000';
        var currentColor = '#ffffff';
        var scale = 1;
        var currentLang = 'en';
        var currentTheme = 'dark';
        var bgImage = null;
        var hovered = null;
        var leftOpen = false;
        var rightOpen = false;
        var selectedShapes = [];
        var drawingSelect = false;
        var selectStartX, selectStartY;
        var dragging = false;
        var dragPivotStartX, dragPivotStartY;
        var dragShapeSnapshots = null;
        var currentOrigin = 'top-left';
        var screenObject = 'tft';
        var currentQuickColorElement = null;
        var quickColors = [{hex: '#ff0000', code: '0xF800'},{hex: '#00ff00', code: '0x07E0'},{hex: '#0000ff', code: '0x001F'},{hex: '#ffff00', code: '0xFFE0'},{hex: '#ff00ff', code: '0xF81F'}];
        var cursorConstrain = false;
        var objectSnap = false;
        var snapRadius = 5;
        var RULER_BAND = 30;
        var textModalOpen = false;
        
        // Gears settings variables
        var gearsSpeed = 1;
        var gearsCount = 1;
        var gearsPanelOpacity = 0.8;
        var gearsColorMode = 'random';
        var gearsSolidColor = '#8888ff';
        var gearsEnabled = true;
        
        // Undo/Redo history (up to 1000 states)
        var drawHistory = [];
        var historyIndex = -1;
        var MAX_HISTORY = 1000;
        
        function saveHistory() {
            // Remove any future states beyond current index
            drawHistory = drawHistory.slice(0, historyIndex + 1);
            // Save deep copy of current shapes
            drawHistory.push(JSON.parse(JSON.stringify(shapes)));
            if (drawHistory.length > MAX_HISTORY) drawHistory.shift();
            historyIndex = drawHistory.length - 1;
            updateUndoRedoButtons();
        }
        
        function undo() {
            if (historyIndex > 0) {
                historyIndex--;
                shapes = JSON.parse(JSON.stringify(drawHistory[historyIndex]));
                selectedShapes = [];
                redraw();
                saveCanvasData();
                showToast(translations[currentLang].undo_message || 'Undo');
                updateUndoRedoButtons();
            }
        }
        
        function redo() {
            if (historyIndex < drawHistory.length - 1) {
                historyIndex++;
                shapes = JSON.parse(JSON.stringify(drawHistory[historyIndex]));
                selectedShapes = [];
                redraw();
                saveCanvasData();
                showToast(translations[currentLang].redo_message || 'Redo');
                updateUndoRedoButtons();
            }
        }
        
        function updateUndoRedoButtons() {
            const undoBtn = document.getElementById('btn-undo');
            const redoBtn = document.getElementById('btn-redo');
            if (undoBtn) undoBtn.disabled = historyIndex <= 0;
            if (redoBtn) redoBtn.disabled = historyIndex >= drawHistory.length - 1;
        }
        // Direct touch support (no virtual cursor — fingers draw directly)
        var touchMode = false; // kept for config compat but not used for enabling
        var isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
        // Canvas pan state (middle-mouse or two-finger)
        var isPanning = false;
        var panStartX = 0, panStartY = 0;
        var canvasOffsetX = 0, canvasOffsetY = 0;
        var panTouchStartX = 0, panTouchStartY = 0;
        var panTouchStartOffX = 0, panTouchStartOffY = 0;
        // Touch state
        var lastTapTime = 0;
        var touchStartDistance = 0;
        var touchPanActive = false;  // two-finger pan active
        var touchDrawActive = false; // single-finger draw active

        function rgbToHex(rgb) { if (/^#[0-9A-F]{6}$/i.test(rgb)) return rgb.toUpperCase(); rgb = rgb.match(/\d+/g); if (rgb && rgb.length === 3) { return '#' + rgb.map(x => { x = parseInt(x).toString(16).toUpperCase(); return (x.length === 1) ? '0' + x : x; }).join(''); } return rgb; }
        function hexTo565(hex) { let r = parseInt(hex.substr(1,2), 16) >> 3; let g = parseInt(hex.substr(3,2), 16) >> 2; let b = parseInt(hex.substr(5,2), 16) >> 3; return `0x${((r << 11) | (g << 5) | b).toString(16).toUpperCase().padStart(4, '0')}`; }
        function _565ToHex(color565) { if (typeof color565 !== 'string' || !color565.startsWith('0x')) return '#ffffff'; let num = parseInt(color565, 16); let r = ((num >> 11) & 0x1F) << 3; let g = ((num >> 5) & 0x3F) << 2; let b = (num & 0x1F) << 3; return `#${((r << 16) | (g << 8) | b).toString(16).padStart(6, '0').toUpperCase()}`; }

        var config = <?php echo json_encode($config); ?>;
        config.userEmail = '<?php echo addslashes(get_current_user_from_sessions()); ?>';
        var canvasData = <?php echo json_encode($canvasData); ?>;
        var gearsConfig = <?php echo json_encode($gearsConfig); ?>;

        // URL parameters
        var urlParams = new URLSearchParams(window.location.search);
        var urlTheme = urlParams.get('theme');
        var urlLang = urlParams.get('lang');

        function loadSavedConfig() {
            if (urlTheme && (urlTheme === 'light' || urlTheme === 'dark')) {
                currentTheme = urlTheme;
            }
            if (urlLang) {
                currentLang = urlLang;
            }
            canvas.width = config.w || 320;
            canvas.height = config.h || 240;
            bgColor = config.bgColor || '#000000';
            currentColor = config.drawColor || '#ffffff';
            scale = config.scale || 1;
            canvasOffsetX = config.canvasOffsetX || 0;
            canvasOffsetY = config.canvasOffsetY || 0;
            document.getElementById('fill').checked = config.fill === true;
            document.getElementById('radius').value = config.radius || 5;
            document.getElementById('cwidth').value = canvas.width;
            document.getElementById('cheight').value = canvas.height;
            document.getElementById('bgcolor').value = bgColor;
            document.getElementById('drawcolor').value = currentColor;
            document.getElementById('scale').value = scale;
            currentOrigin = config.origin || 'top-left';
            document.getElementById('origin-select').value = currentOrigin;
            screenObject = config.screenObject || 'tft';
            document.getElementById('screen-object').value = screenObject;
            cursorConstrain = config.cursorConstrain || false;
            document.getElementById('cursor-constrain').checked = cursorConstrain;
            objectSnap = config.objectSnap || false;
            document.getElementById('object-snap').checked = objectSnap;
            if (config.quickColors && config.quickColors.length === 5) { quickColors = config.quickColors; updateQuickColors(); }
            
            // Load gears settings from user DB
            if (gearsConfig && gearsConfig.speed !== undefined) gearsSpeed = gearsConfig.speed;
            if (gearsConfig && gearsConfig.count !== undefined) gearsCount = gearsConfig.count;
            if (gearsConfig && gearsConfig.panelOpacity !== undefined) gearsPanelOpacity = gearsConfig.panelOpacity;
            if (gearsConfig && gearsConfig.enabled !== undefined) gearsEnabled = gearsConfig.enabled;
            if (gearsConfig && gearsConfig.colorMode) gearsColorMode = gearsConfig.colorMode;
            if (gearsConfig && gearsConfig.solidColor) gearsSolidColor = gearsConfig.solidColor;
            applyGearsSettingsToUI();
            applyPanelOpacity();
            
            shapes = canvasData.shapes || [];
            bgColor = canvasData.bgColor || bgColor;
            // Initialize drawHistory with loaded state
            drawHistory = [JSON.parse(JSON.stringify(shapes))];
            historyIndex = 0;
            updateUndoRedoButtons();
            setLanguage(currentLang);
            setTheme(currentTheme);
            setScale(scale);
            redraw();
            paintRulers(null, null);
        }

        function updateQuickColors() {
            const container = document.getElementById('quick-colors');
            container.innerHTML = '';
            quickColors.forEach(color => {
                const div = document.createElement('div');
                div.className = 'quick-color';
                div.style.backgroundColor = color.hex;
                div.dataset.color = color.code;
                div.title = translations[currentLang].color_tooltip;
                div.onclick = () => setQuickColor(div);
                div.oncontextmenu = () => { openColorModal(div); return false; };
                container.appendChild(div);
            });
        }

        function setQuickColor(element) { currentColor = rgbToHex(element.style.backgroundColor); document.getElementById('drawcolor').value = currentColor; saveConfig(); redraw(); }
        function openColorModal(element) { currentQuickColorElement = element; document.getElementById('color-modal').style.display = 'block'; document.getElementById('color-input').value = rgbToHex(element.style.backgroundColor); document.getElementById('color-text').value = ''; }
        function closeColorModal() { document.getElementById('color-modal').style.display = 'none'; currentQuickColorElement = null; }
        function applyColor() {
            let color = document.getElementById('color-input').value;
            let textInput = document.getElementById('color-text').value.trim();
            if (textInput) { const testDiv = document.createElement('div'); testDiv.style.color = textInput; if (testDiv.style.color) { color = rgbToHex(testDiv.style.color); } }
            if (currentQuickColorElement && color) {
                const index = Array.from(document.getElementById('quick-colors').children).indexOf(currentQuickColorElement);
                quickColors[index] = { hex: color, code: hexTo565(color) };
                currentQuickColorElement.style.backgroundColor = color;
                currentQuickColorElement.dataset.color = hexTo565(color);
                currentColor = color;
                document.getElementById('drawcolor').value = color;
                updateQuickColors();
                saveConfig();
                redraw();
            }
            closeColorModal();
        }

        function openCodeModal() {
            document.getElementById('code-modal').style.display = 'flex';
            if (monacoEditor) {
                // Restore saved code from localStorage instead of clearing
                const savedCode = localStorage.getItem('tft_modal_code') || '';
                monacoEditor.setValue(savedCode);
                setTimeout(() => monacoEditor.focus(), 150);
                // Restore decorations
                const savedDecorations = localStorage.getItem('tft_modal_decorations');
                if (savedDecorations) {
                    try {
                        applyDecorations(JSON.parse(savedDecorations));
                    } catch(e) {}
                }
            }
        }
        function closeCodeModal() { document.getElementById('code-modal').style.display = 'none'; }

        // ── Gears Settings Modal ──────────────────────────────────────────
        function openGearsSettings() {
            document.getElementById('gears-settings-modal').style.display = 'block';
            document.getElementById('gears-speed').value = gearsSpeed;
            document.getElementById('gears-count').value = gearsCount;
            document.getElementById('gears-panel-opacity').value = gearsPanelOpacity;
            document.getElementById('gears-enable').checked = gearsEnabled;
            document.getElementById('gears-color-picker').value = gearsSolidColor;
            // Set radio buttons
            const radios = document.getElementsByName('gears-color');
            for (const radio of radios) {
                radio.checked = (radio.value === gearsColorMode);
            }
            document.getElementById('gears-solid-color').style.display = (gearsColorMode === 'solid') ? 'block' : 'none';
            updateGearsSettingsLabels();
        }

        function closeGearsSettings() {
            document.getElementById('gears-settings-modal').style.display = 'none';
        }

        function applyGearsSettingsToUI() {
            document.getElementById('gears-speed').value = gearsSpeed;
            document.getElementById('gears-count').value = gearsCount;
            document.getElementById('gears-panel-opacity').value = gearsPanelOpacity;
            document.getElementById('gears-enable').checked = gearsEnabled;
            document.getElementById('gears-color-picker').value = gearsSolidColor;
            const radios = document.getElementsByName('gears-color');
            for (const radio of radios) {
                radio.checked = (radio.value === gearsColorMode);
            }
            document.getElementById('gears-solid-color').style.display = (gearsColorMode === 'solid') ? 'block' : 'none';
            updateGearsSettingsLabels();
        }

        function updateGearsSettingsLabels() {
            const speedVal = parseFloat(document.getElementById('gears-speed').value);
            const countVal = parseFloat(document.getElementById('gears-count').value);
            const opacityVal = parseFloat(document.getElementById('gears-panel-opacity').value);
            
            document.getElementById('speed-value').textContent = speedVal < 0.5 ? 'Slow' : speedVal < 1.2 ? 'Normal' : 'Fast';
            document.getElementById('count-value').textContent = countVal < 0.7 ? 'Few' : countVal < 1.4 ? 'Medium' : 'Many';
            document.getElementById('opacity-value').textContent = opacityVal.toFixed(2);
        }

        function applyPanelOpacity() {
            document.documentElement.style.setProperty('--panel-opacity', gearsPanelOpacity);
        }

        function quickSaveGearsToggle() {
            gearsEnabled = document.getElementById('gears-enable').checked;
            
            // Save to database
            const settings = {
                type: 'gears_settings',
                data: {
                    speed: gearsSpeed,
                    count: gearsCount,
                    panelOpacity: gearsPanelOpacity,
                    enabled: gearsEnabled,
                    colorMode: gearsColorMode,
                    solidColor: gearsSolidColor
                }
            };
            fetch('', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(settings) })
                .then(r => r.json())
                .catch(() => {});
            
            // Clear canvas and restart animation loop
            if (typeof initGears === 'function') {
                initGears();
            }
        }

        function saveGearsSettings() {
            gearsSpeed = parseFloat(document.getElementById('gears-speed').value);
            gearsCount = parseFloat(document.getElementById('gears-count').value);
            gearsPanelOpacity = parseFloat(document.getElementById('gears-panel-opacity').value);
            gearsEnabled = document.getElementById('gears-enable').checked;
            gearsColorMode = document.querySelector('input[name="gears-color"]:checked').value;
            gearsSolidColor = document.getElementById('gears-color-picker').value;
            
            applyPanelOpacity();
            updateGearsSettingsLabels();
            
            // Save to database
            const settings = {
                type: 'gears_settings',
                data: {
                    speed: gearsSpeed,
                    count: gearsCount,
                    panelOpacity: gearsPanelOpacity,
                    enabled: gearsEnabled,
                    colorMode: gearsColorMode,
                    solidColor: gearsSolidColor
                }
            };
            fetch('', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(settings) })
                .then(r => r.json())
                .catch(() => {});
            
            // Reinit gears immediately with new settings (no page reload)
            if (typeof initGears === 'function') {
                initGears();
            }
            
            showToast('Gears settings saved and applied!');
            closeGearsSettings();
        }

        function applyGearsDefaults() {
            gearsSpeed = 1;
            gearsCount = 1;
            gearsPanelOpacity = 0.8;
            gearsEnabled = true;
            gearsColorMode = 'random';
            gearsSolidColor = '#8888ff';
            
            document.getElementById('gears-speed').value = 1;
            document.getElementById('gears-count').value = 1;
            document.getElementById('gears-panel-opacity').value = 0.8;
            document.getElementById('gears-enable').checked = true;
            document.getElementById('gears-color-picker').value = '#8888ff';
            document.querySelector('input[name="gears-color"][value="random"]').checked = true;
            document.getElementById('gears-solid-color').style.display = 'none';
            updateGearsSettingsLabels();
            applyPanelOpacity();
            
            if (typeof initGears === 'function') initGears();
            showToast('Reset to default settings!');
        }

        // Radio button change handler
        document.addEventListener('DOMContentLoaded', function() {
            const colorRadios = document.querySelectorAll('input[name="gears-color"]');
            colorRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    document.getElementById('gears-solid-color').style.display = 
                        (this.value === 'solid') ? 'block' : 'none';
                });
            });
            
            // Update labels on slider change
            ['gears-speed', 'gears-count', 'gears-panel-opacity'].forEach(id => {
                document.getElementById(id).addEventListener('input', updateGearsSettingsLabels);
            });
        });


        function codeModalPaste() {
            if (navigator.clipboard && navigator.clipboard.readText) {
                navigator.clipboard.readText().then(text => {
                    if (monacoEditor) {
                        monacoEditor.setValue(text);
                        monacoEditor.focus();
                    }
                }).catch(() => {
                    if (monacoEditor) monacoEditor.focus();
                    showToast('Use Ctrl+V or long-press to paste');
                });
            } else {
                if (monacoEditor) monacoEditor.focus();
                showToast('Use Ctrl+V or long-press to paste');
            }
        }

        function codeModalSelectAll() {
            if (monacoEditor) {
                monacoEditor.focus();
                monacoEditor.setSelection(monacoEditor.getModel().getFullModelRange());
            }
        }

        function codeModalClear() {
            if (monacoEditor) monacoEditor.setValue('');
        }

        
        // Color variables database (per-user, stored in localStorage)
        function getUserEmailForColorVars() {
            // Try to get email from server-side rendered config
            if (typeof config !== 'undefined' && config.userEmail) return config.userEmail;
            return 'default_user';
        }
        function getColorVarsDB() {
            const email = getUserEmailForColorVars();
            try {
                return JSON.parse(localStorage.getItem('tft_colorvars_' + email) || '{}');
            } catch(e) { return {}; }
        }
        function saveColorVarsDB(db) {
            const email = getUserEmailForColorVars();
            localStorage.setItem('tft_colorvars_' + email, JSON.stringify(db));
        }
        function getColorVarCode(varName) {
            const db = getColorVarsDB();
            return db[varName] || null;
        }
        function addColorVarToDB(name, code565, hexColor) {
            const db = getColorVarsDB();
            db[name] = { code: code565, hex: hexColor };
            saveColorVarsDB(db);
        }
        function removeColorVarFromDB(name) {
            const db = getColorVarsDB();
            delete db[name];
            saveColorVarsDB(db);
        }

        // Color variable prompt state
        var colorVarPromptResolve = null;
        var colorVarPromptVarName = '';

        // Smart split by semicolons respecting quoted strings and parentheses
        function splitBySemicolons(code) {
            const statements = [];
            let current = '';
            let inSingleQuote = false;
            let inDoubleQuote = false;
            let escapeNext = false;

            for (let i = 0; i < code.length; i++) {
                const ch = code[i];

                if (escapeNext) {
                    current += ch;
                    escapeNext = false;
                    continue;
                }

                if (ch === '\\') {
                    current += ch;
                    escapeNext = true;
                    continue;
                }

                if (ch === '"' && !inSingleQuote) {
                    inDoubleQuote = !inDoubleQuote;
                    current += ch;
                    continue;
                }

                if (ch === "'" && !inDoubleQuote) {
                    inSingleQuote = !inSingleQuote;
                    current += ch;
                    continue;
                }

                if (ch === ';' && !inSingleQuote && !inDoubleQuote) {
                    const trimmed = current.trim();
                    if (trimmed) statements.push(trimmed);
                    current = '';
                    continue;
                }

                current += ch;
            }

            // Don't forget the last statement
            const trimmed = current.trim();
            if (trimmed) statements.push(trimmed);

            return statements;
        }
        function parseTftMethodCall(line) {
            const trimmed = line.replace(/;+\s*$/, '').trim();
            const m = trimmed.match(/^(\w+)\.(\w+)\s*\(/);
            if (!m) return null;
            const obj = m[1], method = m[2];
            const openIdx = m[0].length - 1;
            let depth = 0, inS = false, inD = false, esc = false;
            for (let i = openIdx; i < trimmed.length; i++) {
                const ch = trimmed[i];
                if (esc) { esc = false; continue; }
                if ((inS || inD) && ch === '\\') { esc = true; continue; }
                if (!inS && !inD) {
                    if (ch === '(') { depth++; continue; }
                    if (ch === ')') {
                        depth--;
                        if (depth === 0) return { obj, method, argsStr: trimmed.slice(openIdx + 1, i), rest: trimmed.slice(i + 1).trim() };
                        continue;
                    }
                    if (ch === '"') { inD = true; continue; }
                    if (ch === "'") { inS = true; continue; }
                } else {
                    if (ch === '"' && inD) { inD = false; continue; }
                    if (ch === "'" && inS) { inS = false; continue; }
                }
            }
            return null;
        }
        function splitArgsAtDepthZero(argsStr) {
            const parts = [];
            let cur = '';
            let depth = 0, inS = false, inD = false, esc = false;
            for (let i = 0; i < argsStr.length; i++) {
                const ch = argsStr[i];
                if (esc) { cur += ch; esc = false; continue; }
                if ((inS || inD) && ch === '\\') { esc = true; cur += ch; continue; }
                if (!inS && !inD) {
                    if (ch === '(') { depth++; cur += ch; continue; }
                    if (ch === ')') { depth--; cur += ch; continue; }
                    if (ch === ',' && depth === 0) { parts.push(cur.trim()); cur = ''; continue; }
                    if (ch === '"') { inD = true; cur += ch; continue; }
                    if (ch === "'") { inS = true; cur += ch; continue; }
                } else {
                    if (ch === '"' && inD) { inD = false; cur += ch; continue; }
                    if (ch === "'" && inS) { inS = false; cur += ch; continue; }
                }
                cur += ch;
            }
            if (cur.trim()) parts.push(cur.trim());
            return parts;
        }
        function cppUnescapeLiteral(inner, q) {
            return inner.replace(/\\(.)/g, function(_, c) {
                if (c === 'n') return '\n';
                if (c === 't') return '\t';
                if (c === 'r') return '\r';
                if (c === q) return q;
                return c;
            });
        }
        function parsePrintFirstArg(argTrim) {
            const a = argTrim.trim();
            const fm = a.match(/^F\s*\(\s*(["'])([\s\S]*?)\1\s*\)$/i);
            if (fm) return { kind: 'literal', text: cppUnescapeLiteral(fm[2], fm[1]) };
            const qm = a.match(/^(["'])([\s\S]*?)\1$/);
            if (qm) return { kind: 'literal', text: cppUnescapeLiteral(qm[2], qm[1]) };
            if (/^[A-Za-z_][A-Za-z0-9_]*$/.test(a)) return { kind: 'ident', name: a };
            return { kind: 'expr', raw: a };
        }
        function escapeCppStringContent(s) {
            return s.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r').replace(/\t/g, '\\t');
        }

        async function loadCode() {
            const code = monacoEditor ? monacoEditor.getValue().trim() : '';
            if (!code) return;
            saveHistory();
            shapes = [];

            // Smart split by semicolons respecting quoted strings
            const allStatements = splitBySemicolons(code);

            // Track which statements were successfully parsed and inserted
            const statementStatus = [];
            let newScreenObject = 'tft';

            const methodsWithTrailingColor = new Set(['fillScreen','fillCircle','drawCircle','fillRect','drawRect','fillRoundRect','drawRoundRect','drawLine','drawFastHLine','drawFastVLine','fillTriangle','drawTriangle','drawPixel']);
            const builtInColors = {
                'RED': '0xF800', 'GREEN': '0x07E0', 'BLUE': '0x001F',
                'YELLOW': '0xFFE0', 'MAGENTA': '0xF81F', 'CYAN': '0x07FF',
                'WHITE': '0xFFFF', 'BLACK': '0x0000', 'Orange': '0xFD20',
                'Maincolor': null, 'Red': '0xF800', 'Green': '0x07E0',
                'Blue': '0x001F', 'Greencolor': '0x07E0', 'NAVY': '0x000F', 'DARKGREEN': '0x03E0',
                'DARKCYAN': '0x03EF', 'TEAL': '0x0410', 'MAROON': '0x7800',
                'OLIVE': '0x7BE0', 'PURPLE': '0x780F', 'GRAY': '0x7BEF',
                'SILVER': '0xC618', 'ORANGE': '0xFD20', 'SKYBLUE': '0x06FF',
                'PINK': '0xFE19'
            };

            // First pass: check for unknown color variables that need user input
            const unknownColorVars = new Set();
            for (let rawStatement of allStatements) {
                let line = rawStatement.replace(/\/\/.*$/, '').trim();
                if (!line) continue;
                if (line.startsWith('//') || line.startsWith('/*')) continue;
                const pc = parseTftMethodCall(line);
                if (!pc) continue;
                if (!methodsWithTrailingColor.has(pc.method)) continue;
                const args = splitArgsAtDepthZero(pc.argsStr);
                if (args.length > 0) {
                    const lastArg = args[args.length - 1];
                    if (!lastArg.startsWith('0x') && !lastArg.startsWith('0X') && isNaN(lastArg) && !lastArg.startsWith('"') && !lastArg.startsWith("'")) {
                        if (!builtInColors.hasOwnProperty(lastArg)) {
                            // Completely unknown variable — check user DB
                            const existingCode = getColorVarCode(lastArg);
                            if (!existingCode) {
                                unknownColorVars.add(lastArg);
                            }
                        } else if (!builtInColors[lastArg]) {
                            // Known name but null value (e.g., Maincolor) — check user DB
                            const existingCode = getColorVarCode(lastArg);
                            if (!existingCode) {
                                unknownColorVars.add(lastArg);
                            }
                        }
                    }
                }
            }

            // Prompt user for each unknown color variable
            const resolvedColorVars = {};
            for (const varName of unknownColorVars) {
                const result = await promptColorVariable(varName);
                if (result) {
                    resolvedColorVars[varName] = result.code;
                    // Save to user's color database
                    addColorVarToDB(varName, result.code, result.hex);
                }
            }

            // Second pass: process statements (already properly split)
            for (let rawLine of allStatements) {
                    // Strip inline comments (// ...) but preserve the original for status
                    let line = rawLine.replace(/\/\/.*$/, '').trim();
                    if (!line) {
                        statementStatus.push({ statement: rawLine, inserted: false, isComment: true, error: '' });
                        continue;
                    }
                    if (line.startsWith('/*') && line.includes('*/')) {
                        statementStatus.push({ statement: rawLine, inserted: false, isComment: true, error: '' });
                        continue;
                    }

                    const pc = parseTftMethodCall(line);
                    if (!pc) {
                        statementStatus.push({ statement: rawLine, inserted: false, error: 'Not a valid tft.method(...) statement' });
                        continue;
                    }
                    const { obj, method, argsStr } = pc;
                    newScreenObject = obj;
                    let args = splitArgsAtDepthZero(argsStr);
                    let color = '#ffffff';
                    let color565 = '0xFFFF';
                    let colorResolved = true;
                    if (methodsWithTrailingColor.has(method) && args.length > 0) {
                        const lastArg = args[args.length - 1];
                        if (!lastArg.startsWith('0x') && !lastArg.startsWith('0X') && isNaN(lastArg) && !lastArg.startsWith('"') && !lastArg.startsWith("'")) {
                            if (builtInColors.hasOwnProperty(lastArg)) {
                                const builtin = builtInColors[lastArg];
                                if (builtin) {
                                    color565 = builtin;
                                    color = _565ToHex(builtin);
                                } else {
                                    if (resolvedColorVars[lastArg]) {
                                        color565 = resolvedColorVars[lastArg];
                                        color = _565ToHex(color565);
                                    } else {
                                        const dbCode = getColorVarCode(lastArg);
                                        if (dbCode) {
                                            color565 = dbCode;
                                            color = _565ToHex(dbCode);
                                        } else {
                                            colorResolved = false;
                                        }
                                    }
                                }
                            } else if (resolvedColorVars[lastArg]) {
                                color565 = resolvedColorVars[lastArg];
                                color = _565ToHex(color565);
                            } else {
                                const dbCode = getColorVarCode(lastArg);
                                if (dbCode) {
                                    color565 = dbCode;
                                    color = _565ToHex(dbCode);
                                } else {
                                    colorResolved = false;
                                }
                            }
                        } else if (lastArg.startsWith('0x') || lastArg.startsWith('0X')) {
                            color = _565ToHex(lastArg);
                            color565 = lastArg;
                        }
                    }
                    if (methodsWithTrailingColor.has(method) && !colorResolved) {
                        color = '#ffffff';
                        color565 = '0xFFFF';
                    }

                    let parsed = false;
                    if (method === 'fillScreen') { bgColor = color; document.getElementById('bgcolor').value = bgColor; parsed = true; }
                    else if (method === 'fillCircle' || method === 'drawCircle') { if (args.length >= 4) { shapes.push({ type: 'circle', x: parseInt(args[0]), y: parseInt(args[1]), r: parseInt(args[2]), fill: method === 'fillCircle', color565, colorHex: color }); parsed = true; } else { parsed = false; } }
                    else if (method === 'fillRect' || method === 'drawRect') { if (args.length >= 5) { shapes.push({ type: 'rect', x: parseInt(args[0]), y: parseInt(args[1]), w: parseInt(args[2]), h: parseInt(args[3]), fill: method === 'fillRect', color565, colorHex: color }); parsed = true; } else { parsed = false; } }
                    else if (method === 'fillRoundRect' || method === 'drawRoundRect') { if (args.length >= 6) { shapes.push({ type: 'roundrect', x: parseInt(args[0]), y: parseInt(args[1]), w: parseInt(args[2]), h: parseInt(args[3]), r: parseInt(args[4]), fill: method === 'fillRoundRect', color565, colorHex: color }); parsed = true; } else { parsed = false; } }
                    else if (method === 'drawLine') { if (args.length >= 5) { shapes.push({ type: 'line', x1: parseInt(args[0]), y1: parseInt(args[1]), x2: parseInt(args[2]), y2: parseInt(args[3]), color565, colorHex: color }); parsed = true; } else { parsed = false; } }
                    else if (method === 'drawFastHLine') { if (args.length >= 4) { let x = parseInt(args[0]), y = parseInt(args[1]), w = parseInt(args[2]); shapes.push({ type: 'line', x1: x, y1: y, x2: x + w, y2: y, color565, colorHex: color }); parsed = true; } }
                    else if (method === 'drawFastVLine') { if (args.length >= 4) { let x = parseInt(args[0]), y = parseInt(args[1]), h = parseInt(args[2]); shapes.push({ type: 'line', x1: x, y1: y, x2: x, y2: y + h, color565, colorHex: color }); parsed = true; } }
                    else if (method === 'fillTriangle' || method === 'drawTriangle') { if (args.length >= 7) { shapes.push({ type: 'triangle', x1: parseInt(args[0]), y1: parseInt(args[1]), x2: parseInt(args[2]), y2: parseInt(args[3]), x3: parseInt(args[4]), y3: parseInt(args[5]), fill: method === 'fillTriangle', color565, colorHex: color }); parsed = true; } }
                    else if (method === 'drawPixel') { if (args.length >= 3) { shapes.push({ type: 'point', x: parseInt(args[0]), y: parseInt(args[1]), color565, colorHex: color }); parsed = true; } }
                    else if (method === 'setTextSize' || method === 'setTextColor' || method === 'setCursor' || method === 'print' || method === 'println') { parsed = true; }

                    if (parsed) {
                        statementStatus.push({ statement: line, inserted: true });
                    } else {
                        let errorMsg = 'Could not parse: wrong number of arguments or unknown method';
                        // Check for parseInt NaN
                        const nums = args.map(a => parseInt(a));
                        if (nums.some(n => isNaN(n))) {
                            errorMsg = 'Invalid numeric argument in statement';
                        }
                        statementStatus.push({ statement: line, inserted: false, error: errorMsg });
                    }
                }

            screenObject = newScreenObject;
            document.getElementById('screen-object').value = screenObject;

            // Parse text elements from setCursor/print statements
            parseTextElements(allStatements, resolvedColorVars, builtInColors);

            redraw();
            saveConfig();
            saveCanvasData();

            // Store statement status in localStorage and apply decorations
            const loadId = Date.now().toString();
            localStorage.setItem('tft_load_status_' + loadId, JSON.stringify(statementStatus));
            localStorage.setItem('tft_load_latest', loadId);
            localStorage.setItem('tft_modal_decorations', JSON.stringify(statementStatus));

            // Apply decorations in Monaco editor
            applyDecorations(statementStatus);

            // DO NOT close modal - show toast instead
            const insertedCount = statementStatus.filter(s => s.inserted).length;
            const notInsertedCount = statementStatus.filter(s => !s.inserted && !s.isComment).length;
            showToast(`Loaded: ${insertedCount} inserted, ${notInsertedCount} not inserted`);

            // Update code output
            document.getElementById('code_output').innerHTML = code;
            hljs.highlightElement(document.getElementById('code_output'));
        }

        function parseTextElements(statements, resolvedVars, builtInColors) {
            let currentText = { x: 0, y: 0, text: '', size: 1, color565: '0xFFFF', colorHex: '#ffffff' };
            let inTextBlock = false;

            for (let rawLine of statements) {
                let line = rawLine.replace(/\/\/.*$/, '').trim();
                if (!line) continue;

                const pc = parseTftMethodCall(line);
                if (!pc) continue;
                const { obj, method, argsStr } = pc;
                const args = splitArgsAtDepthZero(argsStr);

                if (method === 'setCursor' && args.length >= 2) {
                    currentText.x = parseInt(args[0]);
                    currentText.y = parseInt(args[1]);
                    inTextBlock = true;
                } else if (method === 'setTextSize' && args.length >= 1) {
                    currentText.size = parseInt(args[0]);
                    inTextBlock = true;
                } else if (method === 'setTextColor') {
                    let col = args[0];
                    if (col.startsWith('0x') || col.startsWith('0X')) {
                        currentText.color565 = col;
                        currentText.colorHex = _565ToHex(col);
                    } else if (builtInColors && builtInColors.hasOwnProperty(col) && builtInColors[col]) {
                        currentText.color565 = builtInColors[col];
                        currentText.colorHex = _565ToHex(currentText.color565);
                    } else if (resolvedVars && resolvedVars[col]) {
                        currentText.color565 = resolvedVars[col];
                        currentText.colorHex = _565ToHex(currentText.color565);
                    } else {
                        // Check user's color DB
                        const dbCode = getColorVarCode(col);
                        if (dbCode) {
                            currentText.color565 = dbCode;
                            currentText.colorHex = _565ToHex(dbCode);
                        }
                        // If still unknown, keep previous color (don't break)
                    }
                    inTextBlock = true;
                } else if ((method === 'print' || method === 'println') && args.length >= 1) {
                    const p = parsePrintFirstArg(args[0]);
                    let text = '';
                    if (p.kind === 'literal') text = p.text;
                    else if (p.kind === 'ident') text = '<' + p.name + '>';
                    else text = '<' + p.raw + '>';
                    if (inTextBlock) {
                        let charWidth = 6 * currentText.size;
                        let w = text.length * charWidth;
                        let h = 8 * currentText.size;
                        saveHistory();
                        shapes.push({ type: 'text', x: currentText.x, y: currentText.y, text: text, size: currentText.size, colorHex: currentText.colorHex, color565: currentText.color565, w: w, h: h });
                        currentText.x += w;
                    }
                }
            }
        }

        function applyDecorations(statementStatus) {
            if (!monacoEditor) return;
            const model = monacoEditor.getModel();
            if (!model) return;

            const decorations = [];
            const lines = model.getLinesContent();
            let stmtIndex = 0;

            for (let lineNum = 0; lineNum < lines.length; lineNum++) {
                const lineContent = lines[lineNum].trim();
                if (!lineContent) continue;

                // Try to match ALL statements that could belong to this line
                // (handles multi-statement lines like: tft.setCursor(10, 12); tft.println("text");)
                let matchedAny = true;
                while (matchedAny && stmtIndex < statementStatus.length) {
                    matchedAny = false;
                    // Search from stmtIndex for the NEXT matching statement on THIS line
                    for (let i = stmtIndex; i < statementStatus.length; i++) {
                        const status = statementStatus[i];
                        if (status.isComment) {
                            // Comments don't get decorations but advance past them
                            stmtIndex = i + 1;
                            matchedAny = true;
                            break;
                        }
                        const searchStr = status.statement.substring(0, Math.min(30, status.statement.length));
                        if (lineContent.includes(searchStr)) {
                            const options = {
                                isWholeLine: true,
                                glyphMarginHoverMessage: { value: '' }
                            };

                            if (status.inserted) {
                                options.className = 'line-inserted';
                                options.glyphMarginClassName = 'glyphicon-inserted';
                                options.glyphMarginHoverMessage.value = '✓ Inserted into canvas';
                            } else if (status.error) {
                                options.className = 'line-not-inserted';
                                options.glyphMarginClassName = 'glyphicon-not-inserted';
                                options.glyphMarginHoverMessage.value = '✗ ' + status.error;
                                options.hoverMessage = { value: '**Error:** ' + status.error + '\n\n`' + status.statement + '`' };
                            } else {
                                options.className = 'line-not-inserted';
                                options.glyphMarginClassName = 'glyphicon-not-inserted';
                                options.glyphMarginHoverMessage.value = '✗ Not parsed';
                            }

                            decorations.push({
                                range: new monaco.Range(lineNum + 1, 1, lineNum + 1, lineContent.length + 1),
                                options
                            });
                            stmtIndex = i + 1;
                            matchedAny = true;
                            break;
                        }
                    }
                }
            }

            if (monacoEditor._decorationIds) {
                monacoEditor.deltaDecorations(monacoEditor._decorationIds, []);
            }
            monacoEditor._decorationIds = monacoEditor.deltaDecorations([], decorations);
        }

        function viewInsertionStatus() {
            const latestId = localStorage.getItem('tft_load_latest');
            if (!latestId) { showToast('No load history available'); return; }

            const statementStatus = JSON.parse(localStorage.getItem('tft_load_status_' + latestId) || '[]');
            if (statementStatus.length === 0) { showToast('No status data available'); return; }

            const inserted = statementStatus.filter(s => s.inserted).length;
            const notInserted = statementStatus.filter(s => !s.inserted && !s.isComment).length;
            const comments = statementStatus.filter(s => s.isComment).length;

            const statusEl = document.getElementById('code-load-status');
            statusEl.style.display = 'inline-block';
            statusEl.textContent = `✓${inserted} ✗${notInserted}` + (comments > 0 ? ` //${comments}` : '');
            statusEl.style.backgroundColor = notInserted === 0 ? 'rgba(40, 167, 69, 0.3)' : 'rgba(255, 193, 7, 0.3)';
            statusEl.style.color = notInserted === 0 ? '#28a745' : '#ffc107';

            const notInsertedItems = statementStatus.filter(s => !s.inserted && !s.isComment).slice(0, 5);
            if (notInsertedItems.length > 0) {
                const msg = notInsertedItems.map(s => {
                    let m = '• ' + s.statement.substring(0, 60);
                    if (s.error) m += ' — ' + s.error;
                    return m;
                }).join('\n');
                showToast(msg);
            } else {
                showToast('All statements successfully inserted!');
            }
        }

        // ── Color Variable Prompt ─────────────────────────────────────────
        function promptColorVariable(varName) {
            return new Promise(function(resolve) {
                colorVarPromptVarName = varName;
                colorVarPromptResolve = resolve;

                document.getElementById('color-var-name').value = varName;
                document.getElementById('color-var-code').value = '';
                document.getElementById('color-var-picker').value = '#ff0000';
                document.getElementById('color-var-prompt-modal').style.display = 'block';

                // Check if already in DB
                const existing = getColorVarCode(varName);
                if (existing) {
                    document.getElementById('color-var-code').value = existing;
                }
            });
        }

        function pickColorVarColor(el) {
            document.querySelectorAll('#color-var-palette .quickcolor').forEach(e => e.classList.remove('selected'));
            el.classList.add('selected');
            const code = el.dataset.code;
            const hex = _565ToHex(code);
            document.getElementById('color-var-code').value = code;
            document.getElementById('color-var-picker').value = hex;
        }

        function saveColorVar() {
            const code = document.getElementById('color-var-code').value.trim();
            const hex = document.getElementById('color-var-picker').value;

            if (!code) {
                showToast('Enter a 565 color code (e.g., 0xF800)');
                return;
            }

            // Convert hex picker to 565 if code wasn't provided properly
            let finalCode = code.startsWith('0x') || code.startsWith('0X') ? code : '0x' + code;
            document.getElementById('color-var-code').value = finalCode;

            closeColorVarPrompt();
            if (colorVarPromptResolve) {
                colorVarPromptResolve({ code: finalCode, hex: hex });
            }
        }

        function skipColorVar() {
            closeColorVarPrompt();
            if (colorVarPromptResolve) {
                colorVarPromptResolve(null); // Skip — variable won't be resolved
            }
        }

        function closeColorVarPrompt() {
            document.getElementById('color-var-prompt-modal').style.display = 'none';
            document.querySelectorAll('#color-var-palette .quickcolor').forEach(e => e.classList.remove('selected'));
        }

        // ── Color Variables Database Modal ────────────────────────────────
        function openColorVarsModal() {
            document.getElementById('colorvars-modal').style.display = 'block';
            renderColorVarsList();
            togglePopup(); // close popup
        }

        function closeColorVarsModal() {
            document.getElementById('colorvars-modal').style.display = 'none';
        }
        function renderColorVarsList() {
            const db = getColorVarsDB();
            const container = document.getElementById('colorvars-list');
            const entries = Object.entries(db);

            if (entries.length === 0) {
                container.innerHTML = '<p style="opacity:0.6; text-align:center; padding:20px;">No color variables saved yet.</p>';
                return;
            }

            container.innerHTML = entries.map(([name, val]) => `
                <div class="colorvar-item">
                    <div class="colorvar-swatch" style="background:${val.hex}"></div>
                    <span class="colorvar-name">${name}</span>
                    <span class="colorvar-code">${val.code}</span>
                    <button class="colorvar-delete" onclick="deleteColorVar('${name}')" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            `).join('');
        }

        function addColorVar() {
            const name = document.getElementById('new-colorvar-name').value.trim();
            let code = document.getElementById('new-colorvar-code').value.trim();
            const hex = document.getElementById('new-colorvar-hex').value;

            if (!name) { showToast('Enter a variable name'); return; }
            if (!code) {
                // Convert hex to 565
                const r = parseInt(hex.slice(1,3), 16) >> 3;
                const g = parseInt(hex.slice(3,5), 16) >> 2;
                const b = parseInt(hex.slice(5,7), 16) >> 3;
                code = '0x' + ((r << 11) | (g << 5) | b).toString(16).toUpperCase().padStart(4, '0');
            }
            if (!code.startsWith('0x') && !code.startsWith('0X')) code = '0x' + code;

            addColorVarToDB(name, code, hex);
            document.getElementById('new-colorvar-name').value = '';
            document.getElementById('new-colorvar-code').value = '';
            renderColorVarsList();
            showToast(`Color variable "${name}" saved!`);
        }

        function deleteColorVar(name) {
            if (!confirm('Delete color variable "' + name + '"?')) return;
            removeColorVarFromDB(name);
            renderColorVarsList();
            showToast(`Deleted "${name}"`);
        }

        function exportColorVars() {
            const db = getColorVarsDB();
            const json = JSON.stringify(db, null, 2);
            const blob = new Blob([json], { type: 'application/json' });
            const a = document.createElement('a');
            a.download = 'color_variables.json';
            a.href = URL.createObjectURL(blob);
            a.click();
        }

        function importColorVars(file) {
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const imported = JSON.parse(e.target.result);
                    const db = getColorVarsDB();
                    Object.assign(db, imported);
                    saveColorVarsDB(db);
                    renderColorVarsList();
                    showToast(`Imported ${Object.keys(imported).length} color variables!`);
                } catch(err) {
                    showToast('Invalid file format');
                }
            };
            reader.readAsText(file);
        }

        // Sync color pickers with code fields
        document.addEventListener('DOMContentLoaded', function() {
            // Color vars modal: hex → code sync
            const cvHex = document.getElementById('new-colorvar-hex');
            const cvCode = document.getElementById('new-colorvar-code');
            if (cvHex && cvCode) {
                cvHex.addEventListener('input', function() {
                    const hex = this.value;
                    const r = parseInt(hex.slice(1,3), 16) >> 3;
                    const g = parseInt(hex.slice(3,5), 16) >> 2;
                    const b = parseInt(hex.slice(5,7), 16) >> 3;
                    cvCode.value = '0x' + ((r << 11) | (g << 5) | b).toString(16).toUpperCase().padStart(4, '0');
                });
            }

            // Color var prompt: picker → code sync
            const promptPicker = document.getElementById('color-var-picker');
            const promptCode = document.getElementById('color-var-code');
            if (promptPicker && promptCode) {
                promptPicker.addEventListener('input', function() {
                    const hex = this.value;
                    const r = parseInt(hex.slice(1,3), 16) >> 3;
                    const g = parseInt(hex.slice(3,5), 16) >> 2;
                    const b = parseInt(hex.slice(5,7), 16) >> 3;
                    promptCode.value = '0x' + ((r << 11) | (g << 5) | b).toString(16).toUpperCase().padStart(4, '0');
                    document.querySelectorAll('#color-var-palette .quickcolor').forEach(e => e.classList.remove('selected'));
                });
            }

            // Code field → picker sync for prompt
            if (promptCode && promptPicker) {
                promptCode.addEventListener('input', function() {
                    const val = this.value.trim();
                    if (val.startsWith('0x') || val.startsWith('0X')) {
                        try {
                            const hex = _565ToHex(val);
                            promptPicker.value = hex;
                            document.querySelectorAll('#color-var-palette .quickcolor').forEach(e => {
                                e.classList.toggle('selected', e.dataset.code.toLowerCase() === val.toLowerCase());
                            });
                        } catch(e) {}
                    }
                });
            }
        });

        function saveConfig() {
            screenObject = document.getElementById('screen-object').value;
            let settings = { 
                lang: currentLang, 
                theme: currentTheme, 
                w: canvas.width, 
                h: canvas.height, 
                bgColor: bgColor, 
                drawColor: currentColor, 
                scale: scale,
                canvasOffsetX: canvasOffsetX,
                canvasOffsetY: canvasOffsetY,
                fill: document.getElementById('fill').checked, 
                radius: parseInt(document.getElementById('radius').value || 5), 
                origin: currentOrigin, 
                screenObject: screenObject, 
                cursorConstrain: document.getElementById('cursor-constrain').checked, 
                objectSnap: document.getElementById('object-snap').checked, 
                quickColors: quickColors,
                touchMode: false
            };
            fetch('', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(settings, null, 2) }).then(r => r.json()).catch(() => {});
        }

        // Versioned canvas save — prevents out-of-order responses overwriting newer data
        var _saveCanvasVersion = 0;
        var _saveCanvasTimer = null;

        function saveCanvasData() {
            // Debounce: collapse rapid calls into one, after 120ms of quiet
            clearTimeout(_saveCanvasTimer);
            _saveCanvasTimer = setTimeout(_doSaveCanvas, 120);
        }

        function _doSaveCanvas() {
            _saveCanvasVersion++;
            const thisVersion = _saveCanvasVersion;
            const body = JSON.stringify({
                type: 'canvas',
                version: thisVersion,
                data: { shapes: JSON.parse(JSON.stringify(shapes)), bgColor: bgColor }
            });
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                keepalive: true,
                body: body
            }).then(r => r.json()).catch(() => {
                setTimeout(() => {
                    fetch('', { method: 'POST', headers: { 'Content-Type': 'application/json' }, keepalive: true, body: body })
                        .catch(() => {});
                }, 400);
            });
        }

        // Flush canvas immediately (e.g. before page unload)
        window.addEventListener('beforeunload', function() {
            clearTimeout(_saveCanvasTimer);
            const body = JSON.stringify({
                type: 'canvas',
                data: { shapes: JSON.parse(JSON.stringify(shapes)), bgColor: bgColor }
            });
            navigator.sendBeacon('', new Blob([body], { type: 'application/json' }));
        });

        function clearCanvas() {
            saveHistory();
            shapes = [];
            selectedShapes = [];
            triPoints = [];
            drawing = false;
            dimDraft = null;
            dimPlacing = false;
            closeDimEditModal();
            closeDimPlaceModal(false);
            redraw();
            saveCanvasData();
        }

        function saveConfigFile() {
            let settings = { lang: currentLang, theme: currentTheme, w: canvas.width, h: canvas.height, bgColor: bgColor, drawColor: currentColor, scale: scale, canvasOffsetX: canvasOffsetX, canvasOffsetY: canvasOffsetY, fill: document.getElementById('fill').checked, radius: parseInt(document.getElementById('radius').value), origin: currentOrigin, screenObject: screenObject, cursorConstrain: cursorConstrain, objectSnap: document.getElementById('object-snap').checked, quickColors: quickColors, touchMode: false };
            let json = JSON.stringify(settings, null, 2);
            let blob = new Blob([json], {type: 'application/json'});
            let url = URL.createObjectURL(blob);
            let a = document.createElement('a');
            a.download = 'config.json';
            a.href = url;
            a.click();
        }

        function loadConfigFile(file) {
            let reader = new FileReader();
            reader.onload = function(e) {
                let settings = JSON.parse(e.target.result);
                currentLang = settings.lang;
                currentTheme = settings.theme;
                canvas.width = settings.w;
                canvas.height = settings.h;
                bgColor = settings.bgColor;
                currentColor = settings.drawColor;
                scale = settings.scale;
                document.getElementById('fill').checked = settings.fill;
                document.getElementById('radius').value = settings.radius;
                document.getElementById('cwidth').value = canvas.width;
                document.getElementById('cheight').value = canvas.height;
                document.getElementById('bgcolor').value = bgColor;
                document.getElementById('drawcolor').value = currentColor;
                document.getElementById('scale').value = scale;
                currentOrigin = settings.origin || 'top-left';
                document.getElementById('origin-select').value = currentOrigin;
                screenObject = settings.screenObject || 'tft';
                document.getElementById('screen-object').value = screenObject;
                cursorConstrain = settings.cursorConstrain || false;
                document.getElementById('cursor-constrain').checked = cursorConstrain;
                objectSnap = settings.objectSnap || false;
                document.getElementById('object-snap').checked = objectSnap;
                if (settings.quickColors && settings.quickColors.length === 5) { quickColors = settings.quickColors; updateQuickColors(); }
                setLanguage(currentLang);
                setTheme(currentTheme);
                setScale(scale);
                saveConfig();
                redraw();
            };
            reader.readAsText(file);
        }
        
        function setLanguage(lang) {
            currentLang = lang;
            document.querySelectorAll('[data-key]').forEach(el => {
                let key = el.getAttribute('data-key');
                if (translations[lang][key]) {
                    if (el.tagName === 'BUTTON' && el.querySelector('span')) { el.querySelector('span').textContent = translations[lang][key]; } 
                    else if (el.tagName === 'BUTTON') { el.textContent = translations[lang][key]; }
                    else { el.textContent = translations[lang][key]; }
                }
                if (translations[lang][key + '_tooltip']) { el.title = translations[lang][key + '_tooltip']; }
            });
            document.title = translations[lang].title;
            const td = document.getElementById('tool-dimension');
            if (td && translations[lang].dimension_tooltip) td.title = translations[lang].dimension_tooltip;
            const copyBtn = document.getElementById('copy-code');
            if (copyBtn && translations[lang].copy_code_tooltip) copyBtn.title = translations[lang].copy_code_tooltip;
            updateQuickColors();
            saveConfig();
            updateOutputDockInsets();
            updatePanelPositions();
        }

        function setTheme(theme) {
            currentTheme = theme;
            document.body.className = theme;
            // Switch hljs theme
            const hljsLink = document.getElementById('hljs-theme');
            if (hljsLink) {
                hljsLink.href = theme === 'dark'
                    ? 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.0/styles/dark.min.css'
                    : 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.0/styles/default.min.css';
            }
            // Sync Monaco theme
            if (typeof monacoEditor !== 'undefined' && monacoEditor && typeof monaco !== 'undefined') {
                monaco.editor.setTheme(theme === 'dark' ? 'vs-dark' : 'vs');
            }
            if (!(typeof urlTheme !== 'undefined' && urlTheme && urlTheme === theme)) saveConfig();
        }
        
        function setCanvasSize() { canvas.width = parseInt(document.getElementById('cwidth').value); canvas.height = parseInt(document.getElementById('cheight').value); saveHistory(); saveConfig(); redraw(); paintRulers(null, null); }
        function setBgColor(color) { bgColor = color; saveConfig(); saveCanvasData(); redraw(); }
        function setScale(s) { 
            scale = parseFloat(s); 
            applyCanvasTransform();
            saveConfig();
        }
        function setOrigin(o) { currentOrigin = o; saveConfig(); redraw(); }

        function toggleTouchMode() {
            // Legacy: no-op, kept so old configs don't break
            saveConfig();
        }

        function applyCanvasTransform() {
            const vp = document.getElementById('draw-viewport');
            const t = `translate(${canvasOffsetX}px, ${canvasOffsetY}px) scale(${scale})`;
            if (vp) { vp.style.transform = t; vp.style.transformOrigin = '0 0'; }
            canvas.style.transform = 'none';
            canvas.style.imageRendering = 'pixelated';
            requestAnimationFrame(function() { paintRulers(null, null); });
        }

        function canvasPxPerScreenPx() {
            const rect = canvas.getBoundingClientRect();
            const rw = rect.width || 1, rh = rect.height || 1;
            return Math.min(canvas.width / rw, canvas.height / rh);
        }
        function clientToCanvas(clientX, clientY) {
            const rect = canvas.getBoundingClientRect();
            const rw = rect.width || 1, rh = rect.height || 1;
            return {
                x: (clientX - rect.left) * canvas.width / rw,
                y: (clientY - rect.top) * canvas.height / rh
            };
        }

        // ── DIRECT TOUCH HANDLERS ──────────────────────────────────────────

        function handleTouchStart(e) {
            e.preventDefault();
            const touches = e.touches;

            if (touches.length === 1) {
                // Single finger — draw
                touchPanActive = false;
                touchDrawActive = true;
                const pos = clientToCanvas(touches[0].clientX, touches[0].clientY);

                // Double-tap detection
                const now = Date.now();
                if (now - lastTapTime < 300) {
                    handleDoubleTap(pos.x, pos.y);
                    lastTapTime = 0;
                    return;
                }
                lastTapTime = now;

                // Fire mousedown
                fireMouseEvent('mousedown', touches[0].clientX, touches[0].clientY, 0);

            } else if (touches.length === 2) {
                // Two fingers — start pan + pinch
                touchDrawActive = false;
                touchPanActive = true;
                touchStartDistance = Math.hypot(
                    touches[1].clientX - touches[0].clientX,
                    touches[1].clientY - touches[0].clientY
                );
                panTouchStartX = (touches[0].clientX + touches[1].clientX) / 2;
                panTouchStartY = (touches[0].clientY + touches[1].clientY) / 2;
                panTouchStartOffX = canvasOffsetX;
                panTouchStartOffY = canvasOffsetY;
                // cancel any active draw
                fireMouseEvent('mouseup', touches[0].clientX, touches[0].clientY, 0);
                drawing = false;
            }
        }

        function handleTouchMove(e) {
            e.preventDefault();
            const touches = e.touches;

            if (touches.length === 1 && touchDrawActive && !touchPanActive) {
                fireMouseEvent('mousemove', touches[0].clientX, touches[0].clientY, 0);

            } else if (touches.length === 2 && touchPanActive) {
                // Pinch zoom
                const dist = Math.hypot(
                    touches[1].clientX - touches[0].clientX,
                    touches[1].clientY - touches[0].clientY
                );
                if (touchStartDistance > 0) {
                    let newScale = scale * (dist / touchStartDistance);
                    newScale = Math.max(0.1, Math.min(10, newScale));
                    touchStartDistance = dist;
                    scale = newScale;
                    document.getElementById('scale').value = newScale;
                }
                // Two-finger pan
                const midX = (touches[0].clientX + touches[1].clientX) / 2;
                const midY = (touches[0].clientY + touches[1].clientY) / 2;
                canvasOffsetX = panTouchStartOffX + (midX - panTouchStartX);
                canvasOffsetY = panTouchStartOffY + (midY - panTouchStartY);
                applyCanvasTransform();
                saveConfig();
            }
        }

        function handleTouchEnd(e) {
            e.preventDefault();
            if (touchDrawActive && e.changedTouches.length > 0) {
                const t = e.changedTouches[0];
                fireMouseEvent('mouseup', t.clientX, t.clientY, 0);
            }
            if (e.touches.length < 2) {
                touchPanActive = false;
                touchStartDistance = 0;
            }
            if (e.touches.length === 0) {
                touchDrawActive = false;
            }
        }

        function handleDoubleTap(x, y) {
            if (currentTool === 'triangle' && triPoints.length > 0 && triPoints.length < 3) {
                triPoints.push({x, y});
                if (triPoints.length === 3) {
                    let fill = document.getElementById('fill').checked;
                    saveHistory();
                    shapes.push({ type: 'triangle', x1: triPoints[0].x, y1: triPoints[0].y, x2: triPoints[1].x, y2: triPoints[1].y, x3: triPoints[2].x, y3: triPoints[2].y, fill, color565: hexTo565(currentColor), colorHex: currentColor });
                    triPoints = [];
                    redraw();
                    saveCanvasData();
                }
            } else if (drawing) {
                drawing = false;
                saveCanvasData();
            }
        }

        // Fire a synthetic mouse event at given client coords
        function fireMouseEvent(type, clientX, clientY, button) {
            canvas.dispatchEvent(new MouseEvent(type, {
                bubbles: true, cancelable: true,
                clientX, clientY, button
            }));
        }

        // Stubs kept so old references don't throw
        function enableTouchMode() {}
        function disableTouchMode() {}
        function openTouchHelpModal() {}
        function closeTouchHelpModal() {}
        function updateVirtualCursorPosition() {}

        function toggleLeftPanel() {
            if (window.innerWidth <= 768) {
                leftOpen = !leftOpen;
                let panel = document.getElementById('left-panel');
                if (leftOpen) {
                    panel.classList.add('active');
                    document.body.classList.add('panel-open');
                } else {
                    panel.classList.remove('active');
                    document.body.classList.remove('panel-open');
                }
            } else {
                leftOpen = !leftOpen;
                let panel = document.getElementById('left-panel');
                panel.style.left = leftOpen ? '0px' : '-250px';
            }
            updateCodeWidth();
        }

        function toggleRightPanel() {
            if (window.innerWidth <= 768) {
                rightOpen = !rightOpen;
                let panel = document.getElementById('right-panel');
                if (rightOpen) {
                    panel.classList.add('active');
                    document.body.classList.add('panel-open');
                } else {
                    panel.classList.remove('active');
                    document.body.classList.remove('panel-open');
                }
            } else {
                rightOpen = !rightOpen;
                let panel = document.getElementById('right-panel');
                panel.style.right = rightOpen ? '0px' : '-250px';
            }
            updateCodeWidth();
        }

        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                let leftPanel = document.getElementById('left-panel');
                let rightPanel = document.getElementById('right-panel');
                let topBar = document.getElementById('top-bar');
                
                if (!leftPanel.contains(e.target) && !rightPanel.contains(e.target) && 
                    !e.target.closest('button[onclick="toggleLeftPanel()"]') && 
                    !e.target.closest('button[onclick="toggleRightPanel()"]')) {
                    
                    leftPanel.classList.remove('active');
                    rightPanel.classList.remove('active');
                    document.body.classList.remove('panel-open');
                    leftOpen = false;
                    rightOpen = false;
                }
            }
        });
        
        function updateOutputDockInsets() {
            const root = document.documentElement;
            const dock = document.getElementById('output-dock');
            if (!dock) return;
            if (window.innerWidth <= 768) {
                root.style.setProperty('--output-dock-left-inset', '0px');
                root.style.setProperty('--output-dock-right-inset', '0px');
                root.style.removeProperty('--output-dock-pad-left');
                dock.classList.remove('output-dock--solid-bg');
                return;
            }
            root.style.setProperty('--output-dock-left-inset', leftOpen ? '250px' : '0px');
            root.style.setProperty('--output-dock-right-inset', rightOpen ? '250px' : '0px');
            if (leftOpen) {
                root.style.setProperty('--output-dock-pad-left', '12px');
                dock.classList.add('output-dock--solid-bg');
            } else {
                root.style.removeProperty('--output-dock-pad-left');
                dock.classList.remove('output-dock--solid-bg');
            }
        }
        function updateCodeWidth() {
            updateOutputDockInsets();
            const pre = document.querySelector('#output-dock pre');
            const foot = document.getElementById('footer');
            if (pre) { pre.style.width = '100%'; pre.style.marginLeft = '0'; }
            if (foot) { foot.style.width = '100%'; foot.style.marginLeft = '0'; }
            updatePanelPositions();
        }

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                let leftPanel = document.getElementById('left-panel');
                let rightPanel = document.getElementById('right-panel');
                
                leftPanel.classList.remove('active');
                rightPanel.classList.remove('active');
                document.body.classList.remove('panel-open');
                
                leftPanel.style.left = leftOpen ? '0px' : '-250px';
                rightPanel.style.right = rightOpen ? '0px' : '-250px';
            }
            if (touchMode) {
                updateVirtualCursorPosition();
            }
            updateCodeWidth();
            paintRulers(null, null);
        });
        
        function togglePopup() { let popup = document.getElementById('popup'); popup.style.display = popup.style.display === 'block' ? 'none' : 'block'; }
        function loadBgImage(file) {
            let reader = new FileReader();
            reader.onload = function(e) { let img = new Image(); img.onload = function() { bgImage = img; redraw(); }; img.src = e.target.result; };
            reader.readAsDataURL(file);
        }
        function saveCanvas() { let a = document.createElement('a'); a.download = 'canvas.png'; a.href = canvas.toDataURL('image/png'); a.click(); }
        function copyTextFallback(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            ta.setSelectionRange(0, text.length);
            let ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
            return ok;
        }
        function copyCode() {
            const codeEl = document.getElementById('code_output');
            const copyBtn = document.getElementById('copy-code');
            if (!codeEl || !copyBtn) return;
            const code = codeEl.innerText;
            const copyIcon = copyBtn.querySelector('.copy-icon');
            const checkIcon = copyBtn.querySelector('.check-icon');
            function done() {
                copyBtn.classList.add('copied');
                if (copyIcon) copyIcon.style.display = 'none';
                if (checkIcon) checkIcon.style.display = 'block';
                const toast = document.getElementById('toast');
                if (toast) {
                    toast.textContent = translations[currentLang].code_copied;
                    toast.style.display = 'block';
                }
                setTimeout(function() {
                    copyBtn.classList.remove('copied');
                    if (copyIcon) copyIcon.style.display = 'block';
                    if (checkIcon) checkIcon.style.display = 'none';
                    if (toast) toast.style.display = 'none';
                }, 1500);
            }
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(code).then(done).catch(function() {
                    if (copyTextFallback(code)) done();
                });
                return;
            }
            if (copyTextFallback(code)) done();
        }
        function closeDimEditModal() {
            const m = document.getElementById('dim-edit-modal');
            if (!m || m.style.display !== 'block') return false;
            m.style.display = 'none';
            dimEditingIndex = -1;
            return true;
        }
        function openDimEditForSelectedDimension() {
            if (selectedShapes.length !== 1) return;
            const idx = selectedShapes[0];
            const d = shapes[idx];
            if (!d || d.type !== 'dimension') return;
            syncAttachedDimensions();
            const L = Math.hypot(d.x2 - d.x1, d.y2 - d.y1);
            dimEditingIndex = idx;
            document.getElementById('dim-value-input').value = String(Math.round(d.drivingValue != null ? d.drivingValue : L));
            document.getElementById('dim-edit-modal').style.display = 'block';
            setTimeout(() => document.getElementById('dim-value-input').focus(), 50);
        }
        function applyDimEditFromModal() {
            if (dimEditingIndex < 0) return;
            const v = parseFloat(document.getElementById('dim-value-input').value);
            if (!isFinite(v) || v < 1) { showToast(translations[currentLang].dim_edit_invalid || 'Invalid'); return; }
            const dim = shapes[dimEditingIndex];
            if (!dim || dim.type !== 'dimension') { closeDimEditModal(); return; }
            saveHistory();
            if (!applyDimensionDrivingValue(dim, v)) showToast(translations[currentLang].dim_edit_failed || 'Cannot apply');
            closeDimEditModal();
            redraw();
            saveCanvasData();
            updateUndoRedoButtons();
        }
        function dimPlaceModalOpen() { const m = document.getElementById('dim-place-modal'); return m && m.style.display === 'block'; }
        function finishDimensionOffsetAndOpenModal() {
            if (!dimPlacing || !dimDraft || dimDraft.x2 === undefined) return;
            saveHistory();
            shapes.push({ type: 'dimension', x1: dimDraft.x1, y1: dimDraft.y1, x2: dimDraft.x2, y2: dimDraft.y2, off: dimPendingOff, a0: dimDraft.a0, a1: dimDraft.a1, drivingValue: null });
            dimDraft = null;
            dimPlacing = false;
            dimEditingIndex = shapes.length - 1;
            dimPlaceIsNew = true;
            openDimPlaceModal();
            redraw();
            saveCanvasData();
            updateUndoRedoButtons();
        }
        function openDimPlaceModal() {
            if (dimLengthLiveApplyTimer) { clearTimeout(dimLengthLiveApplyTimer); dimLengthLiveApplyTimer = null; }
            const dim = shapes[dimEditingIndex];
            if (!dim || dim.type !== 'dimension') return;
            syncAttachedDimensions();
            const L = Math.hypot(dim.x2 - dim.x1, dim.y2 - dim.y1);
            document.getElementById('dim-offset-input').value = String(Math.round(dim.off || 0));
            document.getElementById('dim-length-live-input').value = String(Math.max(1, Math.round(dim.drivingValue != null ? dim.drivingValue : L)));
            document.getElementById('dim-place-modal').style.display = 'block';
            setTimeout(function() { document.getElementById('dim-length-live-input').focus(); }, 50);
        }
        function onDimOffsetFieldInput() {
            if (dimEditingIndex < 0) return;
            const dim = shapes[dimEditingIndex];
            if (!dim || dim.type !== 'dimension') return;
            const v = parseFloat(document.getElementById('dim-offset-input').value);
            if (!isFinite(v)) return;
            dim.off = v;
            redraw();
        }
        function onDimLengthLiveInput(immediate) {
            function applyNow() {
                if (dimEditingIndex < 0) return;
                const dim = shapes[dimEditingIndex];
                if (!dim || dim.type !== 'dimension') return;
                const v = parseFloat(document.getElementById('dim-length-live-input').value);
                if (!isFinite(v) || v < 1) return;
                applyDimensionDrivingValue(dim, v);
                redraw();
            }
            if (immediate) {
                if (dimLengthLiveApplyTimer) { clearTimeout(dimLengthLiveApplyTimer); dimLengthLiveApplyTimer = null; }
                applyNow();
                return;
            }
            if (dimLengthLiveApplyTimer) clearTimeout(dimLengthLiveApplyTimer);
            dimLengthLiveApplyTimer = setTimeout(function() { dimLengthLiveApplyTimer = null; applyNow(); }, 300);
        }
        function confirmDimPlaceModal() {
            onDimLengthLiveInput(true);
            document.getElementById('dim-place-modal').style.display = 'none';
            dimPlaceIsNew = false;
            dimEditingIndex = -1;
            saveCanvasData();
            updateUndoRedoButtons();
        }
        function closeDimPlaceModal(cancel) {
            const m = document.getElementById('dim-place-modal');
            if (!m || m.style.display !== 'block') return false;
            if (dimLengthLiveApplyTimer) { clearTimeout(dimLengthLiveApplyTimer); dimLengthLiveApplyTimer = null; }
            m.style.display = 'none';
            if (cancel && dimPlaceIsNew && dimEditingIndex >= 0) {
                shapes.splice(dimEditingIndex, 1);
                shiftDimRefsAfterDeleteAt(dimEditingIndex);
                repairSelectionIndicesAfterDelete(dimEditingIndex);
                saveCanvasData();
                updateUndoRedoButtons();
            }
            dimPlaceIsNew = false;
            dimEditingIndex = -1;
            redraw();
            return true;
        }
        function setTool(tool) { currentTool = tool; triPoints = []; drawing = false; dimDraft = null; dimPlacing = false; closeDimEditModal(); closeDimPlaceModal(true); updateToolHighlights(); }
        function updateToolHighlights() {
            document.querySelectorAll('#top-bar button[id^="tool-"]').forEach(btn => {
                btn.classList.remove('active');
                if (currentTool && btn.id === 'tool-' + currentTool) { btn.classList.add('active'); }
            });
        }
        
        function codeModalIsOpen() { const m = document.getElementById('code-modal'); return m && m.style.display === 'flex'; }
        function offsetShapeClone(s, dx, dy) {
            const o = JSON.parse(JSON.stringify(s));
            if (o.type === 'point') { o.x += dx; o.y += dy; }
            else if (o.type === 'line') { o.x1 += dx; o.y1 += dy; o.x2 += dx; o.y2 += dy; }
            else if (o.type === 'circle') { o.x += dx; o.y += dy; }
            else if (o.type === 'rect' || o.type === 'roundrect') { o.x += dx; o.y += dy; }
            else if (o.type === 'triangle') { o.x1 += dx; o.y1 += dy; o.x2 += dx; o.y2 += dy; o.x3 += dx; o.y3 += dy; }
            else if (o.type === 'text') { o.x += dx; o.y += dy; }
            else if (o.type === 'dimension') { o.x1 += dx; o.y1 += dy; o.x2 += dx; o.y2 += dy; }
            return o;
        }
        function copySelectedShapesToClipboard() {
            if (!selectedShapes.length) return;
            const sorted = selectedShapes.slice().sort((a, b) => a - b);
            shapeClipboard = sorted.map(i => JSON.parse(JSON.stringify(shapes[i])));
            shapeClipboardSourceIndices = sorted.slice();
            showToast(translations[currentLang].shapes_copied || 'Copied');
        }
        function pasteShapesFromClipboard() {
            if (!shapeClipboard || !shapeClipboard.length) return;
            saveHistory();
            const start = shapes.length;
            if (shapeClipboardSourceIndices.length !== shapeClipboard.length) {
                shapeClipboard.forEach((raw) => {
                    const s = offsetShapeClone(raw, 10, 10);
                    if (s.type === 'dimension') { delete s.a0; delete s.a1; delete s.drivingValue; }
                    shapes.push(s);
                });
            } else {
                const idxMap = {};
                shapeClipboardSourceIndices.forEach((oldIdx, j) => { idxMap[oldIdx] = start + j; });
                shapeClipboard.forEach((raw) => {
                    const s = offsetShapeClone(raw, 10, 10);
                    if (s.type === 'dimension' && s.a0 && s.a1) {
                        const na = idxMap[s.a0.si], nb = idxMap[s.a1.si];
                        if (na === undefined || nb === undefined) { delete s.a0; delete s.a1; delete s.drivingValue; }
                        else { s.a0 = { si: na, k: s.a0.k }; s.a1 = { si: nb, k: s.a1.k }; }
                    }
                    shapes.push(s);
                });
            }
            selectedShapes = shapeClipboard.map((_, i) => start + i);
            redraw();
            saveCanvasData();
            updateUndoRedoButtons();
            showToast(translations[currentLang].shapes_pasted || 'Pasted');
        }
        document.addEventListener('keydown', function(e) {
            if (textModalOpen) return;
            if (codeModalIsOpen()) return;
            if (dimPlaceModalOpen()) {
                if (e.key === 'Escape') { e.preventDefault(); closeDimPlaceModal(true); return; }
                if (e.key === 'Enter' && (!e.target || (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA'))) { e.preventDefault(); confirmDimPlaceModal(); return; }
            }
            if (e.altKey) return;
            
            // Map keyboard layout characters to English equivalents for hotkey support
            const layoutMap = {
                // Russian layout mapping
                'л': 'l', 'д': 'e', 'с': 'c', 'к': 'r', 'щ': 'o', 'е': 't', 'ш': 'i', 'ы': 's', 'ь': 'm', 'у': 'x', 'з': 'p', 'о': 'j',
                'Л': 'L', 'Д': 'E', 'С': 'C', 'К': 'R', 'Щ': 'O', 'Е': 'T', 'Ш': 'I', 'Ы': 'S', 'Ь': 'M', 'У': 'X', 'З': 'P', 'О': 'J',
                // Ukrainian layout mapping  
                'л': 'l', 'д': 'e', 'с': 'c', 'к': 'r', 'щ': 'o', 'е': 't', 'ш': 'i', 'і': 's', 'ь': 'm', 'х': 'x', 'з': 'p', 'о': 'j',
                'Л': 'L', 'Д': 'E', 'С': 'C', 'К': 'R', 'Щ': 'O', 'Е': 'T', 'Ш': 'I', 'І': 'S', 'Ь': 'M', 'Х': 'X', 'З': 'P', 'О': 'J',
                'ж': ';', 'Ж': ':', 'ю': '.', 'Ю': '>', 'я': 'a', 'Я': 'A', 'ч': 'w', 'Ч': 'W', 'г': 'u', 'Г': 'U', 'н': 'y', 'Н': 'Y',
                'ї': ']', 'Ї': '}', 'є': '[', 'Є': '{', 'ґ': '\\', 'Ґ': '|'
            };
            
            const key = layoutMap[e.key] || e.key;
            const normalizedKey = key.toLowerCase();
            const mod = e.ctrlKey || e.metaKey;
            if (mod) {
                if (normalizedKey === 'c' && selectedShapes.length > 0) { e.preventDefault(); copySelectedShapesToClipboard(); return; }
                if (normalizedKey === 'v' && shapeClipboard && shapeClipboard.length) { e.preventDefault(); pasteShapesFromClipboard(); return; }
                if (!e.shiftKey && normalizedKey === 'z') { e.preventDefault(); undo(); return; }
                if (normalizedKey === 'y' || (e.shiftKey && normalizedKey === 'z')) { e.preventDefault(); redo(); return; }
            }
            if (!mod) {
                if (normalizedKey === 'l') { setTool('line'); } 
                else if (normalizedKey === 'p') { setTool('point'); } 
                else if (normalizedKey === 'c') { setTool('circle'); } 
                else if (normalizedKey === 'r') { setTool('rect'); } 
                else if (normalizedKey === 'o') { setTool('roundrect'); } 
                else if (normalizedKey === 't') { setTool('triangle'); } 
                else if (normalizedKey === 'i') { setTool('dimension'); } 
                else if (normalizedKey === 's') { setTool('selection'); } 
                else if (normalizedKey === 'm') { setTool('move'); } 
                else if (normalizedKey === 'e') { setTool('eraser'); } 
                else if (normalizedKey === 'x') { clearCanvas(); }
            }
            if (key === 'Escape') {
                if (closeDimPlaceModal(true)) return;
                if (closeDimEditModal()) return;
                currentTool = null; drawing = false; triPoints = []; dimDraft = null; dimPlacing = false; selectedShapes = []; updateToolHighlights(); redraw(); closeHotkeysModal(); closeTextModal(); closeTouchHelpModal();
            }
            else if (key === 'Enter') {
                if (dimPlaceModalOpen()) return;
                if (currentTool === 'dimension' && dimPlacing && dimDraft && dimDraft.x2 !== undefined) { e.preventDefault(); finishDimensionOffsetAndOpenModal(); return; }
                const dm = document.getElementById('dim-edit-modal');
                if (dm && dm.style.display === 'block') { e.preventDefault(); applyDimEditFromModal(); return; }
                if (selectedShapes.length === 1 && shapes[selectedShapes[0]] && shapes[selectedShapes[0]].type === 'dimension') { e.preventDefault(); openDimEditForSelectedDimension(); }
            }
            else if (e.key === 'Delete' && selectedShapes.length > 0) {
                saveHistory();
                const sorted = selectedShapes.slice().sort((a, b) => b - a);
                for (const i of sorted) { shapes.splice(i, 1); shiftDimRefsAfterDeleteAt(i); }
                selectedShapes = [];
                redraw();
                saveCanvasData();
            }
            else if (normalizedKey === 'j') { setTool('text'); }
        });
        
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 2000);
        }
        function shiftDimRefsAfterDeleteAt(removedIndex) {
            for (const s of shapes) {
                if (s.type !== 'dimension' || !s.a0 || !s.a1) continue;
                if (s.a0.si === removedIndex || s.a1.si === removedIndex) { delete s.a0; delete s.a1; delete s.drivingValue; }
                else {
                    if (s.a0.si > removedIndex) s.a0.si--;
                    if (s.a1.si > removedIndex) s.a1.si--;
                }
            }
        }
        function repairSelectionIndicesAfterDelete(removedIndex) {
            if (hovered !== null) {
                if (hovered === removedIndex) hovered = null;
                else if (hovered > removedIndex) hovered--;
            }
            selectedShapes = selectedShapes.filter(function(i) { return i !== removedIndex; }).map(function(i) { return i > removedIndex ? i - 1 : i; });
        }
        function distToSegParam(px, py, x1, y1, x2, y2) {
            const dx = x2 - x1, dy = y2 - y1;
            const L2 = dx * dx + dy * dy;
            let t = L2 < 1e-10 ? 0 : Math.max(0, Math.min(1, ((px - x1) * dx + (py - y1) * dy) / L2));
            const qx = x1 + t * dx, qy = y1 + t * dy;
            return { t, x: qx, y: qy, d: Math.hypot(px - qx, py - qy) };
        }
        function dimSnapToleranceCanvas() { return 18 / Math.max(canvasPxPerScreenPx(), 1e-6); }
        function classifyLineSegmentDimAxis(sh, k0, k1) {
            if (!k0.startsWith('line:t:') || !k1.startsWith('line:t:')) return null;
            const dy = sh.y2 - sh.y1, dx = sh.x2 - sh.x1;
            if (Math.abs(dy) < 1e-6) return 'hz';
            if (Math.abs(dx) < 1e-6) return 'vt';
            return null;
        }
        function classifyTriEdgeDimAxis(sh, k0, k1) {
            if (k0.slice(4, 6) !== k1.slice(4, 6)) return null;
            const tag = k0.slice(4, 6);
            let xa, ya, xb, yb;
            if (tag === '01') { xa = sh.x1; ya = sh.y1; xb = sh.x2; yb = sh.y2; }
            else if (tag === '12') { xa = sh.x2; ya = sh.y2; xb = sh.x3; yb = sh.y3; }
            else if (tag === '20') { xa = sh.x3; ya = sh.y3; xb = sh.x1; yb = sh.y1; }
            else return null;
            const dy = yb - ya, dx = xb - xa;
            if (Math.abs(dy) < 1e-6) return 'hz';
            if (Math.abs(dx) < 1e-6) return 'vt';
            return null;
        }
        function classifyDimAxis(k0, k1, sh0, sh1) {
            if (!sh0 || !sh1 || !k0 || !k1) return null;
            if (sh0 === sh1 && sh0.type === 'line') return classifyLineSegmentDimAxis(sh0, k0, k1);
            if (sh0 === sh1 && sh0.type === 'triangle') return classifyTriEdgeDimAxis(sh0, k0, k1);
            if (sh0 !== sh1) return null;
            const m0 = k0.match(/^(rect|text):([TRBL]):/), m1 = k1.match(/^(rect|text):([TRBL]):/);
            if (!m0 || !m1) return null;
            const s0 = m0[2], s1 = m1[2];
            if ((s0 === 'L' && s1 === 'R') || (s0 === 'R' && s1 === 'L')) return 'hz';
            if ((s0 === 'T' && s1 === 'B') || (s0 === 'B' && s1 === 'T')) return 'vt';
            if (s0 === s1 && (s0 === 'T' || s0 === 'B')) return 'hz';
            if (s0 === s1 && (s0 === 'L' || s0 === 'R')) return 'vt';
            return null;
        }
        function parseRectOrTextSide(k) {
            const m = (k || '').match(/^(rect|text):([TRBL]):/);
            return m ? m[2] : null;
        }
        function parseTriEdgeTag(k) {
            const m = (k || '').match(/^tri:([012][012]):/);
            return m ? m[1] : null;
        }
        function allowsRectTextDimSides(s0, s1) {
            if (s0 === s1) return true;
            if ((s0 === 'T' && s1 === 'B') || (s0 === 'B' && s1 === 'T')) return true;
            if ((s0 === 'L' && s1 === 'R') || (s0 === 'R' && s1 === 'L')) return true;
            return false;
        }
        function isValidDimensionSecondAnchor(a0, cand) {
            const sh0 = shapes[a0.si], sh1 = shapes[cand.si];
            if (!sh0 || !sh1) return false;
            if (sh0 === sh1 && (sh0.type === 'rect' || sh0.type === 'roundrect' || sh0.type === 'text')) {
                const s0 = parseRectOrTextSide(a0.k), s1 = parseRectOrTextSide(cand.k);
                if (!s0 || !s1) return false;
                return allowsRectTextDimSides(s0, s1);
            }
            if (sh0 === sh1 && sh0.type === 'triangle') {
                const e0 = parseTriEdgeTag(a0.k), e1 = parseTriEdgeTag(cand.k);
                if (!e0 || !e1) return false;
                return e0 === e1;
            }
            return true;
        }
        function findNearestDimAnchor(mx, my) {
            const maxD = dimSnapToleranceCanvas();
            let best = null, bd = maxD;
            for (let si = 0; si < shapes.length; si++) {
                const sh = shapes[si];
                if (sh.type === 'dimension') continue;
                if (sh.type === 'line') {
                    const o = distToSegParam(mx, my, sh.x1, sh.y1, sh.x2, sh.y2);
                    if (o.d < bd) { bd = o.d; const tu = Math.max(0.0001, Math.min(0.9999, o.t)); best = { x: o.x, y: o.y, si, k: 'line:t:' + tu.toFixed(6) }; }
                } else if (sh.type === 'rect' || sh.type === 'roundrect') {
                    const x = sh.x, y = sh.y, w = sh.w, h = sh.h;
                    const edges = [[x, y, x + w, y, 'T'], [x + w, y, x + w, y + h, 'R'], [x + w, y + h, x, y + h, 'B'], [x, y + h, x, y, 'L']];
                    for (let e = 0; e < edges.length; e++) {
                        const o = distToSegParam(mx, my, edges[e][0], edges[e][1], edges[e][2], edges[e][3]);
                        if (o.d < bd) {
                            bd = o.d;
                            let tu = o.t;
                            if (tu <= 1e-4) tu = 0;
                            else if (tu >= 1 - 1e-4) tu = 1;
                            const side = edges[e][4];
                            const pr = rectLikeEdgePoint(sh.x, sh.y, sh.w, sh.h, side, tu);
                            best = { x: pr.x, y: pr.y, si, k: 'rect:' + side + ':' + tu.toFixed(6) };
                        }
                    }
                } else if (sh.type === 'triangle') {
                    const seg = [[sh.x1, sh.y1, sh.x2, sh.y2, '01'], [sh.x2, sh.y2, sh.x3, sh.y3, '12'], [sh.x3, sh.y3, sh.x1, sh.y1, '20']];
                    for (let e = 0; e < 3; e++) {
                        const o = distToSegParam(mx, my, seg[e][0], seg[e][1], seg[e][2], seg[e][3]);
                        if (o.d < bd) { bd = o.d; const tu = Math.max(0.0001, Math.min(0.9999, o.t)); best = { x: o.x, y: o.y, si, k: 'tri:' + seg[e][4] + ':' + tu.toFixed(6) }; }
                    }
                } else if (sh.type === 'circle') {
                    const dx = mx - sh.x, dy = my - sh.y;
                    const ang = Math.atan2(dy, dx);
                    const px = sh.x + sh.r * Math.cos(ang), py = sh.y + sh.r * Math.sin(ang);
                    const d = Math.hypot(mx - px, my - py);
                    if (d < bd) { bd = d; best = { x: px, y: py, si, k: 'circle:rim:' + ang.toFixed(8) }; }
                } else if (sh.type === 'text') {
                    const x = sh.x, y = sh.y, w = sh.w, h = sh.h;
                    const edges = [[x, y, x + w, y, 'T'], [x + w, y, x + w, y + h, 'R'], [x + w, y + h, x, y + h, 'B'], [x, y + h, x, y, 'L']];
                    for (let e = 0; e < edges.length; e++) {
                        const o = distToSegParam(mx, my, edges[e][0], edges[e][1], edges[e][2], edges[e][3]);
                        if (o.d < bd) {
                            bd = o.d;
                            let tu = o.t;
                            if (tu <= 1e-4) tu = 0;
                            else if (tu >= 1 - 1e-4) tu = 1;
                            const side = edges[e][4];
                            const pr = rectLikeEdgePoint(sh.x, sh.y, sh.w, sh.h, side, tu);
                            best = { x: pr.x, y: pr.y, si, k: 'text:' + side + ':' + tu.toFixed(6) };
                        }
                    }
                }
            }
            return best;
        }
        function rectLikeEdgePoint(rx, ry, rw, rh, side, tRaw) {
            const tau = Math.max(0, Math.min(1, tRaw));
            const x = rx, y = ry, w = rw, h = rh;
            if (side === 'T') return { x: x + tau * w, y };
            if (side === 'R') return { x: x + w, y: y + tau * h };
            if (side === 'B') return { x: x + (1 - tau) * w, y: y + h };
            if (side === 'L') return { x, y: y + (1 - tau) * h };
            return null;
        }
        function getAnchorPoint(s, k) {
            if (!s || !k) return null;
            if (k.startsWith('line:t:') && s.type === 'line') { const t = parseFloat(k.slice(7)); const tau = Math.max(0, Math.min(1, t)); return { x: s.x1 + tau * (s.x2 - s.x1), y: s.y1 + tau * (s.y2 - s.y1) }; }
            if ((s.type === 'rect' || s.type === 'roundrect') && k.startsWith('rect:')) {
                const side = k[5];
                const t = parseFloat(k.slice(7));
                return rectLikeEdgePoint(s.x, s.y, s.w, s.h, side, t);
            }
            if (s.type === 'triangle' && k.startsWith('tri:')) {
                const tag = k.slice(4, 6);
                const t = parseFloat(k.slice(7));
                const tau = Math.max(0, Math.min(1, t));
                if (tag === '01') return { x: s.x1 + tau * (s.x2 - s.x1), y: s.y1 + tau * (s.y2 - s.y1) };
                if (tag === '12') return { x: s.x2 + tau * (s.x3 - s.x2), y: s.y2 + tau * (s.y3 - s.y2) };
                if (tag === '20') return { x: s.x3 + tau * (s.x1 - s.x3), y: s.y3 + tau * (s.y1 - s.y3) };
            }
            if (s.type === 'text' && k.startsWith('text:')) {
                const side = k[5];
                const t = parseFloat(k.slice(7));
                return rectLikeEdgePoint(s.x, s.y, s.w, s.h, side, t);
            }
            if (s.type === 'circle' && k.startsWith('circle:rim:')) { const ang = parseFloat(k.slice(11)); return { x: s.x + s.r * Math.cos(ang), y: s.y + s.r * Math.sin(ang) }; }
            if (s.type === 'point' && k === 'point:p') return { x: s.x, y: s.y };
            if (s.type === 'line') {
                if (k === 'line:a') return { x: s.x1, y: s.y1 };
                if (k === 'line:b') return { x: s.x2, y: s.y2 };
                if (k === 'line:m') return { x: (s.x1 + s.x2) / 2, y: (s.y1 + s.y2) / 2 };
            }
            if (s.type === 'circle') {
                if (k === 'circle:c') return { x: s.x, y: s.y };
                if (k === 'circle:e') return { x: s.x + s.r, y: s.y };
                if (k === 'circle:w') return { x: s.x - s.r, y: s.y };
                if (k === 'circle:s') return { x: s.x, y: s.y + s.r };
                if (k === 'circle:n') return { x: s.x, y: s.y - s.r };
            }
            if (s.type === 'rect' || s.type === 'roundrect') {
                const x = s.x, y = s.y, w = s.w, h = s.h;
                const map = { 'rect:nw': [x, y], 'rect:n': [x + w / 2, y], 'rect:ne': [x + w, y], 'rect:e': [x + w, y + h / 2], 'rect:se': [x + w, y + h], 'rect:s': [x + w / 2, y + h], 'rect:sw': [x, y + h], 'rect:w': [x, y + h / 2] };
                if (map[k]) return { x: map[k][0], y: map[k][1] };
            }
            if (s.type === 'triangle') {
                if (k === 'tri:v0') return { x: s.x1, y: s.y1 };
                if (k === 'tri:v1') return { x: s.x2, y: s.y2 };
                if (k === 'tri:v2') return { x: s.x3, y: s.y3 };
                if (k === 'tri:e01m') return { x: (s.x1 + s.x2) / 2, y: (s.y1 + s.y2) / 2 };
                if (k === 'tri:e12m') return { x: (s.x2 + s.x3) / 2, y: (s.y2 + s.y3) / 2 };
                if (k === 'tri:e20m') return { x: (s.x3 + s.x1) / 2, y: (s.y3 + s.y1) / 2 };
            }
            if (s.type === 'text') {
                const x = s.x, y = s.y, w = s.w, h = s.h;
                const map = { 'text:nw': [x, y], 'text:n': [x + w / 2, y], 'text:ne': [x + w, y], 'text:e': [x + w, y + h / 2], 'text:se': [x + w, y + h], 'text:s': [x + w / 2, y + h], 'text:sw': [x, y + h], 'text:w': [x, y + h / 2] };
                if (map[k]) return { x: map[k][0], y: map[k][1] };
            }
            return null;
        }
        function setRectEdgeParam(s, side, t, nx, ny) {
            const eps = 1e-4;
            const tau = Math.max(0, Math.min(1, t));
            const x = s.x, y = s.y;
            if (side === 'T') {
                if (tau > eps) s.w = Math.max(1, (nx - x) / tau);
                else s.w = Math.max(1, nx - x);
                return true;
            }
            if (side === 'B') {
                if (tau > eps && tau < 1 - eps) s.w = Math.max(1, (nx - x) / (1 - tau));
                else if (tau <= eps) s.w = Math.max(1, nx - x);
                else {
                    const right = x + s.w;
                    s.x = nx;
                    s.w = Math.max(1, right - nx);
                }
                return true;
            }
            if (side === 'R') {
                s.w = Math.max(1, nx - x);
                if (tau > eps) {
                    const nh = (ny - y) / tau;
                    if (isFinite(nh) && nh >= 1) s.h = Math.max(1, nh);
                }
                return true;
            }
            if (side === 'L') {
                if (Math.abs(nx - x) < 0.75) {
                    if (tau > eps && tau < 1 - eps) {
                        const nh = (ny - y) / (1 - tau);
                        if (isFinite(nh) && nh >= 1) s.h = Math.max(1, nh);
                    } else if (tau <= eps) s.h = Math.max(1, ny - y);
                    else {
                        const bot = y + s.h;
                        s.y = ny;
                        s.h = Math.max(1, bot - ny);
                    }
                    return true;
                }
                const R = x + s.w;
                s.x = nx;
                s.w = Math.max(1, R - nx);
                if (tau > eps && tau < 1 - eps) {
                    const nh = (ny - y) / (1 - tau);
                    if (isFinite(nh) && nh >= 1) s.h = Math.max(1, nh);
                }
                return true;
            }
            return false;
        }
        function setRectCorner(s, c, x, y) {
            const rx = s.x, ry = s.y, rw = s.w, rh = s.h, R = rx + rw, B = ry + rh;
            if (c === 'nw') { s.w = Math.max(1, R - x); s.h = Math.max(1, B - y); s.x = x; s.y = y; return true; }
            if (c === 'ne') { s.w = Math.max(1, x - rx); s.h = Math.max(1, B - y); s.y = y; return true; }
            if (c === 'sw') { s.w = Math.max(1, R - x); s.h = Math.max(1, y - ry); s.x = x; return true; }
            if (c === 'se') { s.w = Math.max(1, x - rx); s.h = Math.max(1, y - ry); return true; }
            if (c === 'n' && Math.abs(y - ry) < 8) { s.w = Math.max(1, 2 * (x - rx)); return true; }
            if (c === 's' && Math.abs(y - B) < 8) { s.w = Math.max(1, 2 * (x - rx)); return true; }
            if (c === 'e' && Math.abs(x - R) < 8) { s.h = Math.max(1, 2 * (y - ry)); return true; }
            if (c === 'w' && Math.abs(x - rx) < 8) { const oldR = R; s.x = Math.min(x, oldR - 1); s.w = Math.max(1, oldR - s.x); s.h = Math.max(1, 2 * (y - ry)); return true; }
            return false;
        }
        function setAnchorPoint(shape, k, x, y) {
            if (!shape || !k) return false;
            if (k.startsWith('line:t:') && shape.type === 'line') { const t = parseFloat(k.slice(7)); const tau = Math.max(0.001, Math.min(0.999, t)); shape.x2 = shape.x1 + (x - shape.x1) / tau; shape.y2 = shape.y1 + (y - shape.y1) / tau; return true; }
            if (k.startsWith('tri:01:') && shape.type === 'triangle') { const t = parseFloat(k.slice(7)); const tau = Math.max(0.001, Math.min(0.999, t)); shape.x2 = shape.x1 + (x - shape.x1) / tau; shape.y2 = shape.y1 + (y - shape.y1) / tau; return true; }
            if (k.startsWith('tri:12:') && shape.type === 'triangle') { const t = parseFloat(k.slice(7)); const tau = Math.max(0.001, Math.min(0.999, t)); shape.x3 = shape.x2 + (x - shape.x2) / tau; shape.y3 = shape.y2 + (y - shape.y2) / tau; return true; }
            if (k.startsWith('tri:20:') && shape.type === 'triangle') { const t = parseFloat(k.slice(7)); const tau = Math.max(0.001, Math.min(0.999, t)); shape.x1 = shape.x3 + (x - shape.x3) / tau; shape.y1 = shape.y3 + (y - shape.y3) / tau; return true; }
            if ((shape.type === 'rect' || shape.type === 'roundrect') && /^rect:[TRBL]:/.test(k)) { const side = k[5]; const t = parseFloat(k.slice(7)); return setRectEdgeParam(shape, side, t, x, y); }
            if (shape.type === 'text' && /^text:[TRBL]:/.test(k)) { const side = k[5]; const t = parseFloat(k.slice(7)); return setRectEdgeParam(shape, side, t, x, y); }
            if (k.startsWith('circle:rim:') && shape.type === 'circle') { shape.r = Math.max(1, Math.hypot(x - shape.x, y - shape.y)); return true; }
            if (shape.type === 'line') {
                if (k === 'line:a') { shape.x1 = x; shape.y1 = y; return true; }
                if (k === 'line:b') { shape.x2 = x; shape.y2 = y; return true; }
                if (k === 'line:m') { const mx = (shape.x1 + shape.x2) / 2, my = (shape.y1 + shape.y2) / 2; const dx = x - mx, dy = y - my; shape.x1 += dx; shape.y1 += dy; shape.x2 += dx; shape.y2 += dy; return true; }
            }
            if (shape.type === 'circle' && k !== 'circle:c') { const dx = x - shape.x, dy = y - shape.y; shape.r = Math.max(1, Math.hypot(dx, dy)); return true; }
            if ((shape.type === 'rect' || shape.type === 'roundrect') && k.startsWith('rect:')) return setRectCorner(shape, k.slice(5), x, y);
            if (shape.type === 'text' && k.startsWith('text:')) return setRectCorner(shape, k.slice(5), x, y);
            if (shape.type === 'triangle') {
                if (k === 'tri:v0') { shape.x1 = x; shape.y1 = y; return true; }
                if (k === 'tri:v1') { shape.x2 = x; shape.y2 = y; return true; }
                if (k === 'tri:v2') { shape.x3 = x; shape.y3 = y; return true; }
                if (k === 'tri:e01m') { const mx = (shape.x1 + shape.x2) / 2, my = (shape.y1 + shape.y2) / 2, dx = x - mx, dy = y - my; shape.x1 += dx; shape.y1 += dy; shape.x2 += dx; shape.y2 += dy; return true; }
                if (k === 'tri:e12m') { const mx = (shape.x2 + shape.x3) / 2, my = (shape.y2 + shape.y3) / 2, dx = x - mx, dy = y - my; shape.x2 += dx; shape.y2 += dy; shape.x3 += dx; shape.y3 += dy; return true; }
                if (k === 'tri:e20m') { const mx = (shape.x3 + shape.x1) / 2, my = (shape.y3 + shape.y1) / 2, dx = x - mx, dy = y - my; shape.x3 += dx; shape.y3 += dy; shape.x1 += dx; shape.y1 += dy; return true; }
            }
            if (shape.type === 'point' && k === 'point:p') { shape.x = x; shape.y = y; return true; }
            return false;
        }
        function syncAttachedDimensions() {
            for (let i = 0; i < shapes.length; i++) {
                const d = shapes[i];
                if (d.type !== 'dimension' || !d.a0 || !d.a1) continue;
                const s0 = shapes[d.a0.si], s1 = shapes[d.a1.si];
                if (!s0 || !s1) continue;
                const p0 = getAnchorPoint(s0, d.a0.k), p1 = getAnchorPoint(s1, d.a1.k);
                if (!p0 || !p1) continue;
                d.x1 = p0.x; d.y1 = p0.y; d.x2 = p1.x; d.y2 = p1.y;
                const Lg = Math.hypot(d.x2 - d.x1, d.y2 - d.y1);
                if (d.drivingValue != null && Math.abs(Lg - d.drivingValue) > Math.max(0.75, Math.abs(d.drivingValue) * 0.02)) delete d.drivingValue;
            }
        }
        function applyDimensionDrivingValue(dim, T) {
            if (!dim.a0 || !dim.a1 || !isFinite(T) || T <= 0) return false;
            const s0 = shapes[dim.a0.si], s1 = shapes[dim.a1.si];
            if (!s0 || !s1) return false;
            syncAttachedDimensions();
            const p0 = getAnchorPoint(s0, dim.a0.k), p1 = getAnchorPoint(s1, dim.a1.k);
            if (!p0 || !p1) return false;
            const L = Math.hypot(p1.x - p0.x, p1.y - p0.y);
            if (L < 0.01) return false;
            if (Math.abs(T - L) <= 0.5) {
                dim.drivingValue = T;
                syncAttachedDimensions();
                return true;
            }

            // Circle radius common case
            if (s0.type === 'circle' && dim.a0.k === 'circle:c' && dim.a1.k && dim.a1.k.startsWith('circle:rim:')) {
                s0.r = Math.max(1, T);
                dim.drivingValue = T;
                syncAttachedDimensions();
                return true;
            }

            // If both anchors belong to the same rectangle, try to set exact width/height
            if (dim.a0.si === dim.a1.si && (s0.type === 'rect' || s0.type === 'roundrect')) {
                const rect = s0;
                const m0 = (dim.a0.k || '').match(/^rect:([TRBL]):([0-9.]+)/);
                const m1 = (dim.a1.k || '').match(/^rect:([TRBL]):([0-9.]+)/);
                if (m0 && m1) {
                    const side0 = m0[1], side1 = m1[1];
                    const t0 = parseFloat(m0[2]);
                    const t1 = parseFloat(m1[2]);
                    const eps = 1e-4;
                    // Both anchors on the same horizontal edge -> measure along X (width)
                    if (side0 === side1 && (side0 === 'T' || side0 === 'B')) {
                        const dTau = Math.abs(t1 - t0);
                        const newW = dTau > eps ? Math.max(1, T / dTau) : Math.max(1, T);
                        const oldBottom = rect.y + rect.h;
                        rect.w = newW;
                        if (side0 === 'T') rect.x = p0.x - t0 * newW;
                        else rect.x = p0.x - (1 - t0) * newW;
                        if (side0 === 'B') rect.y = oldBottom - rect.h;
                        dim.drivingValue = T;
                        syncAttachedDimensions();
                        return true;
                    }
                    if (side0 === side1 && (side0 === 'L' || side0 === 'R')) {
                        const dTau = Math.abs(t1 - t0);
                        const newH = dTau > eps ? Math.max(1, T / dTau) : Math.max(1, T);
                        const oldRight = rect.x + rect.w;
                        rect.h = newH;
                        if (side0 === 'L') rect.y = p0.y - (1 - t0) * newH;
                        else rect.y = p0.y - t0 * newH;
                        if (side0 === 'R') rect.x = oldRight - rect.w;
                        dim.drivingValue = T;
                        syncAttachedDimensions();
                        return true;
                    }
                    const alignedVert = Math.abs(p0.x - p1.x) <= 2 || Math.abs(t0 - t1) < 0.08;
                    if (((side0 === 'T' && side1 === 'B') || (side0 === 'B' && side1 === 'T')) && alignedVert) {
                        const newH = Math.max(1, T);
                        if (side0 === 'T') rect.y = p0.y; else rect.y = p1.y - newH;
                        rect.h = newH;
                        dim.drivingValue = T;
                        syncAttachedDimensions();
                        return true;
                    }
                    const alignedHoriz = Math.abs(p0.y - p1.y) <= 2 || Math.abs(t0 - t1) < 0.08;
                    if (((side0 === 'L' && side1 === 'R') || (side0 === 'R' && side1 === 'L')) && alignedHoriz) {
                        const newW = Math.max(1, T);
                        if (side0 === 'L') rect.x = p0.x; else rect.x = p1.x - newW;
                        rect.w = newW;
                        dim.drivingValue = T;
                        syncAttachedDimensions();
                        return true;
                    }
                }
            }

            // If both anchors are on the same line, set its length exactly
            if (dim.a0.si === dim.a1.si && s0.type === 'line') {
                const line = s0;
                const dx = line.x2 - line.x1, dy = line.y2 - line.y1;
                const oldLen = Math.hypot(dx, dy);
                if (oldLen < 1e-6) return false;
                const scale = T / oldLen;
                line.x2 = line.x1 + dx * scale;
                line.y2 = line.y1 + dy * scale;
                dim.drivingValue = T;
                syncAttachedDimensions();
                return true;
            }

            // Fallback: move the anchor along p0->p1 vector so distance becomes T
            const nx = p0.x + (p1.x - p0.x) * T / L;
            const ny = p0.y + (p1.y - p0.y) * T / L;
            if (!setAnchorPoint(s1, dim.a1.k, nx, ny)) {
                const nx0 = p1.x - (p1.x - p0.x) * T / L;
                const ny0 = p1.y - (p1.y - p0.y) * T / L;
                if (!setAnchorPoint(s0, dim.a0.k, nx0, ny0)) return false;
            }
            [dim.a0.si, dim.a1.si].forEach(function(si) {
                const sh = shapes[si];
                if (sh && (sh.type === 'rect' || sh.type === 'roundrect' || sh.type === 'text')) { if (sh.w < 1) sh.w = 1; if (sh.h < 1) sh.h = 1; }
            });
            dim.drivingValue = T;
            syncAttachedDimensions();
            return true;
        }
        function layoutRulerCanvases() {
            // High DPI with adaptive zoom scaling for better clarity
            const baseDpr = (window.devicePixelRatio || 1) * 4;
            const zoomFactor = Math.max(1, scale);
            const dpr = baseDpr * Math.sqrt(zoomFactor);
            
            const w = Math.max(1, canvas.width);
            const h = Math.max(1, canvas.height);
            [['ruler-top', w, RULER_BAND], ['ruler-bottom', w, RULER_BAND]].forEach(([id, rw, rh]) => {
                const c = document.getElementById(id);
                if (!c) return;
                c.width = Math.max(1, Math.floor(rw * dpr));
                c.height = Math.max(1, Math.floor(rh * dpr));
                c.style.width = rw + 'px';
                c.style.height = rh + 'px';
                c._dpr = dpr;
            });
            [['ruler-left', RULER_BAND, h], ['ruler-right', RULER_BAND, h]].forEach(([id, rw, rh]) => {
                const c = document.getElementById(id);
                if (!c) return;
                c.width = Math.max(1, Math.floor(rw * dpr));
                c.height = Math.max(1, Math.floor(rh * dpr));
                c.style.width = rw + 'px';
                c.style.height = rh + 'px';
                c._dpr = dpr;
            });
        }
        function rulerNiceMajorStep(logicalSpan, rulerCssSpan) {
            if (logicalSpan <= 0 || rulerCssSpan <= 0) return 10;
            const pxPerUnit = rulerCssSpan / logicalSpan;
            let raw = 40 / pxPerUnit;  // Adjusted for more frequent major marks
            if (raw < 1) raw = 1;
            const p10 = Math.pow(10, Math.floor(Math.log10(raw)));
            const n = raw / p10;
            const f = n <= 1 ? 1 : n <= 1.5 ? 1.5 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10;
            return Math.min(logicalSpan, Math.max(1, Math.round(f * p10)));
        }
        function paintRulers(ptrX, ptrY) {
            const rt = document.getElementById('ruler-top'), rl = document.getElementById('ruler-left'), rb = document.getElementById('ruler-bottom'), rr = document.getElementById('ruler-right');
            if (!rt || !canvas) return;
            layoutRulerCanvases();
            const W = canvas.width, H = canvas.height;
            const dpr = rt._dpr || 1;
            const dark = document.body.classList.contains('dark');
            
            // Enhanced color scheme with better hierarchy
            const colors = {
                major: dark ? '#ffffff' : '#000000',
                subMajor: dark ? '#d0d0d0' : '#1a1a1a',
                quarter: dark ? '#a8a8a8' : '#444444',
                eighth: dark ? '#808080' : '#666666',
                sixteenth: dark ? '#606060' : '#888888',
                tiny: dark ? '#484848' : '#aaaaaa',
                bg: dark ? '#1a1a1a' : '#f5f5f5',
                border: dark ? '#404040' : '#d0d0d0',
                current: '#ff6b6b'
            };

            const drawH = (el, logicalW, isBottom = false) => {
                const ctxr = el.getContext('2d');
                const cw = el.width, ch = el.height;
                ctxr.setTransform(1, 0, 0, 1, 0, 0);
                ctxr.imageSmoothingEnabled = true;
                
                // Enhanced background with gradient
                const grad = ctxr.createLinearGradient(0, 0, 0, ch);
                grad.addColorStop(0, colors.bg);
                grad.addColorStop(1, dark ? '#222222' : '#eeeeee');
                ctxr.fillStyle = grad;
                ctxr.fillRect(0, 0, cw, ch);
                
                // Border line
                ctxr.strokeStyle = colors.border;
                ctxr.lineWidth = Math.max(1, dpr);
                ctxr.beginPath();
                if (isBottom) {
                    ctxr.moveTo(0, 0.5);
                    ctxr.lineTo(cw, 0.5);
                } else {
                    ctxr.moveTo(0, ch - 0.5);
                    ctxr.lineTo(cw, ch - 0.5);
                }
                ctxr.stroke();

                const cssW = el.getBoundingClientRect().width;
                const majorEvery = rulerNiceMajorStep(logicalW, cssW);
                const pxPerUnit = logicalW > 0 ? cssW / logicalW : 1;
                
                let minorStep = 1;
                if (pxPerUnit < 0.3) minorStep = Math.max(1, Math.ceil(0.25 / pxPerUnit));
                if (logicalW > 2000 && minorStep === 1) minorStep = 2;

                const fontPx = Math.max(12, Math.round(12 * Math.sqrt(dpr)));
                
                // Helper to determine tick hierarchy level
                const getTickLevel = (lx, major) => {
                    if (major) return 0;
                    const half = majorEvery / 2;
                    if (lx % half === 0) return 1;
                    const quarter = majorEvery / 4;
                    if (lx % quarter === 0) return 2;
                    const eighth = majorEvery / 8;
                    if (lx % eighth === 0) return 3;
                    const sixteenth = majorEvery / 16;
                    if (lx % sixteenth === 0) return 4;
                    const t32 = majorEvery / 32;
                    return lx % t32 === 0 ? 5 : 6;
                };

                const drawTick = (lx) => {
                    const px = logicalW > 0 ? Math.round((lx / logicalW) * cw) + 0.5 : 0.5;
                    const major = majorEvery > 0 && lx % majorEvery === 0;
                    const level = getTickLevel(lx, major);
                    
                    let heights = [
                        Math.round(18 * dpr),  // major
                        Math.round(12 * dpr),  // 1/2
                        Math.round(9 * dpr),   // 1/4
                        Math.round(6 * dpr),   // 1/8
                        Math.round(4 * dpr),   // 1/16
                        Math.round(3 * dpr),   // 1/32
                        Math.round(2 * dpr)    // 1/64
                    ];
                    let hgt = heights[level] || heights[6];
                    
                    let strokeColors = [
                        colors.major,
                        colors.subMajor,
                        colors.quarter,
                        colors.eighth,
                        colors.sixteenth,
                        colors.tiny,
                        colors.tiny
                    ];
                    let lineWidths = [
                        Math.max(0.83, 0.83 * dpr),
                        Math.max(0.67, 0.67 * dpr),
                        Math.max(0.5, 0.5 * dpr),
                        Math.max(0.4, 0.4 * dpr),
                        Math.max(0.33, 0.33 * dpr),
                        Math.max(0.27, 0.27 * dpr),
                        Math.max(0.2, 0.2 * dpr)
                    ];

                    ctxr.beginPath();
                    ctxr.strokeStyle = strokeColors[level] || colors.major;
                    ctxr.lineWidth = lineWidths[level] || lineWidths[6];
                    if (isBottom) {
                        ctxr.moveTo(px, 0);
                        ctxr.lineTo(px, hgt);
                    } else {
                        ctxr.moveTo(px, ch);
                        ctxr.lineTo(px, ch - hgt);
                    }
                    ctxr.stroke();
                    
                    if (major) {
                        ctxr.fillStyle = colors.major;
                        ctxr.font = `bold ${fontPx}px 'Segoe UI', Roboto, sans-serif`;
                        ctxr.textAlign = 'center';
                        ctxr.textBaseline = 'middle';
                        const textY = isBottom ? Math.round(ch - 5 * dpr) : Math.round(5 * dpr);
                        
                        // Enhanced text shadow for better readability
                        ctxr.lineWidth = Math.max(1.5, Math.round(2 * dpr));
                        ctxr.strokeStyle = dark ? 'rgba(0,0,0,0.9)' : 'rgba(255,255,255,0.95)';
                        ctxr.strokeText(String(lx), Math.round(px), textY);
                        ctxr.fillText(String(lx), Math.round(px), textY);
                    }
                };
                
                for (let lx = 0; lx <= logicalW; lx += minorStep) drawTick(lx);
                if (logicalW > 0 && logicalW % minorStep !== 0) drawTick(logicalW);
                
                // Current position indicator
                if (ptrX != null && ptrX >= 0 && ptrX <= W && logicalW > 0) {
                    const ix = Math.round((ptrX / W) * cw) + 0.5;
                    ctxr.strokeStyle = colors.current;
                    ctxr.lineWidth = Math.max(0.83, 0.83 * dpr);
                    ctxr.globalAlpha = 0.7;
                    ctxr.beginPath();
                    ctxr.moveTo(ix, 0);
                    ctxr.lineTo(ix, ch);
                    ctxr.stroke();
                    ctxr.globalAlpha = 1.0;
                }
            };

            const drawV = (el, logicalH, isRight = false) => {
                const ctxr = el.getContext('2d');
                const cw = el.width, ch = el.height;
                ctxr.setTransform(1, 0, 0, 1, 0, 0);
                ctxr.imageSmoothingEnabled = true;
                
                // Enhanced background with gradient
                const grad = ctxr.createLinearGradient(0, 0, cw, 0);
                grad.addColorStop(0, colors.bg);
                grad.addColorStop(1, dark ? '#222222' : '#eeeeee');
                ctxr.fillStyle = grad;
                ctxr.fillRect(0, 0, cw, ch);
                
                // Border line
                ctxr.strokeStyle = colors.border;
                ctxr.lineWidth = Math.max(1, dpr);
                ctxr.beginPath();
                if (isRight) {
                    ctxr.moveTo(0.5, 0);
                    ctxr.lineTo(0.5, ch);
                } else {
                    ctxr.moveTo(cw - 0.5, 0);
                    ctxr.lineTo(cw - 0.5, ch);
                }
                ctxr.stroke();

                const cssH = el.getBoundingClientRect().height;
                const majorEvery = rulerNiceMajorStep(logicalH, cssH);
                const pxPerUnit = logicalH > 0 ? cssH / logicalH : 1;
                
                let minorStep = 1;
                if (pxPerUnit < 0.3) minorStep = Math.max(1, Math.ceil(0.25 / pxPerUnit));
                if (logicalH > 2000 && minorStep === 1) minorStep = 2;

                const fontPx = Math.max(12, Math.round(12 * Math.sqrt(dpr)));

                const getTickLevel = (ly, major) => {
                    if (major) return 0;
                    const half = majorEvery / 2;
                    if (ly % half === 0) return 1;
                    const quarter = majorEvery / 4;
                    if (ly % quarter === 0) return 2;
                    const eighth = majorEvery / 8;
                    if (ly % eighth === 0) return 3;
                    const sixteenth = majorEvery / 16;
                    if (ly % sixteenth === 0) return 4;
                    const t32 = majorEvery / 32;
                    return ly % t32 === 0 ? 5 : 6;
                };

                const drawTick = (ly) => {
                    const py = logicalH > 0 ? Math.round((ly / logicalH) * ch) + 0.5 : 0.5;
                    const major = majorEvery > 0 && ly % majorEvery === 0;
                    const level = getTickLevel(ly, major);
                    
                    let widths = [
                        Math.round(18 * dpr),
                        Math.round(12 * dpr),
                        Math.round(9 * dpr),
                        Math.round(6 * dpr),
                        Math.round(4 * dpr),
                        Math.round(3 * dpr),
                        Math.round(2 * dpr)
                    ];
                    let wdt = widths[level] || widths[6];
                    
                    let strokeColors = [
                        colors.major,
                        colors.subMajor,
                        colors.quarter,
                        colors.eighth,
                        colors.sixteenth,
                        colors.tiny,
                        colors.tiny
                    ];
                    let lineWidths = [
                        Math.max(0.83, 0.83 * dpr),
                        Math.max(0.67, 0.67 * dpr),
                        Math.max(0.5, 0.5 * dpr),
                        Math.max(0.4, 0.4 * dpr),
                        Math.max(0.33, 0.33 * dpr),
                        Math.max(0.27, 0.27 * dpr),
                        Math.max(0.2, 0.2 * dpr)
                    ];

                    ctxr.beginPath();
                    ctxr.strokeStyle = strokeColors[level] || colors.major;
                    ctxr.lineWidth = lineWidths[level] || lineWidths[6];
                    if (isRight) {
                        ctxr.moveTo(0, py);
                        ctxr.lineTo(wdt, py);
                    } else {
                        ctxr.moveTo(cw, py);
                        ctxr.lineTo(cw - wdt, py);
                    }
                    ctxr.stroke();
                    
                    if (major) {
                        ctxr.save();
                        if (isRight) {
                            ctxr.translate(Math.round(cw - 5 * dpr), py);
                        } else {
                            ctxr.translate(Math.round(5 * dpr), py);
                        }
                        ctxr.rotate(-Math.PI / 2);
                        ctxr.fillStyle = colors.major;
                        ctxr.font = `bold ${fontPx}px 'Segoe UI', Roboto, sans-serif`;
                        ctxr.textAlign = 'center';
                        ctxr.textBaseline = 'middle';
                        
                        // Enhanced text shadow for better readability
                        ctxr.lineWidth = Math.max(1.5, Math.round(2 * dpr));
                        ctxr.strokeStyle = dark ? 'rgba(0,0,0,0.9)' : 'rgba(255,255,255,0.95)';
                        ctxr.strokeText(String(ly), 0, 0);
                        ctxr.fillText(String(ly), 0, 0);
                        ctxr.restore();
                    }
                };
                
                for (let ly = 0; ly <= logicalH; ly += minorStep) drawTick(ly);
                if (logicalH > 0 && logicalH % minorStep !== 0) drawTick(logicalH);
                
                // Current position indicator
                if (ptrY != null && ptrY >= 0 && ptrY <= H && logicalH > 0) {
                    const iy = Math.round((ptrY / H) * ch) + 0.5;
                    ctxr.strokeStyle = colors.current;
                    ctxr.lineWidth = Math.max(0.83, 0.83 * dpr);
                    ctxr.globalAlpha = 0.7;
                    ctxr.beginPath();
                    ctxr.moveTo(0, iy);
                    ctxr.lineTo(cw, iy);
                    ctxr.stroke();
                    ctxr.globalAlpha = 1.0;
                }
            };

            drawH(rt, W, false);  // ruler-top - false = ticks point up
            drawH(rb, W, true);   // ruler-bottom - true = ticks point down
            drawV(rl, H, false);  // ruler-left - false = ticks point right
            drawV(rr, H, true);   // ruler-right - true = ticks point left
        }

        function getSnapPoints(shape) {
            let points = [];
            if (shape.type === 'point') { points.push({ x: shape.x, y: shape.y, type: 'endpoint', icon: 'fa-square' }); } 
            else if (shape.type === 'line') { points.push({ x: shape.x1, y: shape.y1, type: 'endpoint', icon: 'fa-square' }); points.push({ x: shape.x2, y: shape.y2, type: 'endpoint', icon: 'fa-square' }); points.push({ x: (shape.x1 + shape.x2) / 2, y: (shape.y1 + shape.y2) / 2, type: 'midpoint', icon: 'fa-minus' }); } 
            else if (shape.type === 'circle') { points.push({ x: shape.x, y: shape.y, type: 'center', icon: 'fa-circle' }); points.push({ x: shape.x + shape.r, y: shape.y, type: 'endpoint', icon: 'fa-square' }); points.push({ x: shape.x - shape.r, y: shape.y, type: 'endpoint', icon: 'fa-square' }); points.push({ x: shape.x, y: shape.y + shape.r, type: 'endpoint', icon: 'fa-square' }); points.push({ x: shape.x, y: shape.y - shape.r, type: 'endpoint', icon: 'fa-square' }); } 
            else if (shape.type === 'rect' || shape.type === 'roundrect') { points.push({ x: shape.x, y: shape.y, type: 'corner', icon: 'fa-square' }); points.push({ x: shape.x + shape.w, y: shape.y, type: 'corner', icon: 'fa-square' }); points.push({ x: shape.x, y: shape.y + shape.h, type: 'corner', icon: 'fa-square' }); points.push({ x: shape.x + shape.w, y: shape.y + shape.h, type: 'corner', icon: 'fa-square' }); points.push({ x: shape.x + shape.w / 2, y: shape.y, type: 'midpoint', icon: 'fa-minus' }); points.push({ x: shape.x + shape.w / 2, y: shape.y + shape.h, type: 'midpoint', icon: 'fa-minus' }); points.push({ x: shape.x, y: shape.y + shape.h / 2, type: 'midpoint', icon: 'fa-minus' }); points.push({ x: shape.x + shape.w, y: shape.y + shape.h / 2, type: 'midpoint', icon: 'fa-minus' }); } 
            else if (shape.type === 'triangle') { points.push({ x: shape.x1, y: shape.y1, type: 'corner', icon: 'fa-square' }); points.push({ x: shape.x2, y: shape.y2, type: 'corner', icon: 'fa-square' }); points.push({ x: shape.x3, y: shape.y3, type: 'corner', icon: 'fa-square' }); points.push({ x: (shape.x1 + shape.x2) / 2, y: (shape.y1 + shape.y2) / 2, type: 'midpoint', icon: 'fa-minus' }); points.push({ x: (shape.x2 + shape.x3) / 2, y: (shape.y2 + shape.y3) / 2, type: 'midpoint', icon: 'fa-minus' }); points.push({ x: (shape.x3 + shape.x1) / 2, y: (shape.y3 + shape.y1) / 2, type: 'midpoint', icon: 'fa-minus' }); }
            else if (shape.type === 'dimension') { points.push({ x: shape.x1, y: shape.y1, type: 'endpoint', icon: 'fa-square' }); points.push({ x: shape.x2, y: shape.y2, type: 'endpoint', icon: 'fa-square' }); }
            return points;
        }

        function findNearestSnapPoint(mx, my) {
            let nearest = null;
            let minDist = snapRadius / Math.max(canvasPxPerScreenPx(), 1e-6);
            for (let shape of shapes) {
                let points = getSnapPoints(shape);
                for (let point of points) {
                    let dist = Math.sqrt((mx - point.x) ** 2 + (my - point.y) ** 2);
                    if (dist < minDist) { minDist = dist; nearest = point; }
                }
            }
            return nearest;
        }
        function dimensionPerpOffset(mx, my, x1, y1, x2, y2) {
            let vx = x2 - x1, vy = y2 - y1;
            let L = Math.hypot(vx, vy);
            let px, py;
            if (L < 1e-6) { px = 0; py = 1; } else { px = -vy / L; py = vx / L; }
            const mx0 = (x1 + x2) / 2, my0 = (y1 + y2) / 2;
            return -((mx - mx0) * px + (my - my0) * py);
        }
        function dimensionPerpOffsetSmart(mx, my, x1, y1, x2, y2, a0, a1) {
            if (!a0 || !a1) return dimensionPerpOffset(mx, my, x1, y1, x2, y2);
            const sh0 = shapes[a0.si], sh1 = shapes[a1.si];
            if (!sh0 || !sh1) return dimensionPerpOffset(mx, my, x1, y1, x2, y2);
            const axis = classifyDimAxis(a0.k, a1.k, sh0, sh1);
            if (axis === 'hz') { const ym = (y1 + y2) / 2; return dimensionPerpOffset(mx, my, x1, ym, x2, ym); }
            if (axis === 'vt') { const xm = (x1 + x2) / 2; return dimensionPerpOffset(mx, my, xm, y1, xm, y2); }
            return dimensionPerpOffset(mx, my, x1, y1, x2, y2);
        }
        function getDimensionLayout(s) {
            const sx1 = s.x1, sy1 = s.y1, sx2 = s.x2, sy2 = s.y2, off = s.off || 0;
            let axis = null;
            if (s.a0 && s.a1) {
                const sh0 = shapes[s.a0.si], sh1 = shapes[s.a1.si];
                if (sh0 && sh1) axis = classifyDimAxis(s.a0.k, s.a1.k, sh0, sh1);
            }
            if (axis === 'hz') {
                const ym = (sy1 + sy2) / 2, yDim = ym + off;
                return {
                    axis: 'hz', geomLen: Math.abs(sx2 - sx1),
                    ext1a: { x: sx1, y: sy1 }, ext1b: { x: sx1, y: yDim },
                    ext2a: { x: sx2, y: sy2 }, ext2b: { x: sx2, y: yDim },
                    dimA: { x: sx1, y: yDim }, dimB: { x: sx2, y: yDim }
                };
            }
            if (axis === 'vt') {
                const xm = (sx1 + sx2) / 2, xDim = xm + off;
                return {
                    axis: 'vt', geomLen: Math.abs(sy2 - sy1),
                    ext1a: { x: sx1, y: sy1 }, ext1b: { x: xDim, y: sy1 },
                    ext2a: { x: sx2, y: sy2 }, ext2b: { x: xDim, y: sy2 },
                    dimA: { x: xDim, y: sy1 }, dimB: { x: xDim, y: sy2 }
                };
            }
            const dx = sx2 - sx1, dy = sy2 - sy1, gl = Math.hypot(dx, dy);
            let px, py;
            if (gl < 1e-6) { px = 0; py = 1; } else { px = -dy / gl; py = dx / gl; }
            const ox = px * off, oy = py * off;
            return {
                axis: 'skew', geomLen: gl,
                ext1a: { x: sx1, y: sy1 }, ext1b: { x: sx1 + ox, y: sy1 + oy },
                ext2a: { x: sx2, y: sy2 }, ext2b: { x: sx2 + ox, y: sy2 + oy },
                dimA: { x: sx1 + ox, y: sy1 + oy }, dimB: { x: sx2 + ox, y: sy2 + oy }
            };
        }
        function drawDimensionAux(s) {
            const lay = getDimensionLayout(s);
            const stroke = '#a0a0a0';
            const e1x = lay.ext1b.x, e1y = lay.ext1b.y, e2x = lay.ext2b.x, e2y = lay.ext2b.y;
            ctx.save();
            ctx.strokeStyle = stroke;
            ctx.fillStyle = stroke;
            ctx.lineWidth = 1;
            ctx.setLineDash([3, 3]);
            ctx.beginPath(); ctx.moveTo(lay.ext1a.x, lay.ext1a.y); ctx.lineTo(e1x, e1y); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(lay.ext2a.x, lay.ext2a.y); ctx.lineTo(e2x, e2y); ctx.stroke();
            ctx.setLineDash([]);
            ctx.beginPath(); ctx.moveTo(lay.dimA.x, lay.dimA.y); ctx.lineTo(lay.dimB.x, lay.dimB.y); ctx.stroke();
            const cx = (lay.dimA.x + lay.dimB.x) / 2, cy = (lay.dimA.y + lay.dimB.y) / 2;
            const labelNum = (typeof s.drivingValue === 'number' && !isNaN(s.drivingValue)) ? s.drivingValue : lay.geomLen;
            const label = String(Math.round(Math.max(0, labelNum)));
            ctx.font = '11px sans-serif';
            ctx.textBaseline = 'middle';
            const tw = ctx.measureText(label).width;
            const colBg = document.body.classList.contains('dark') ? 'rgba(40,40,40,0.95)' : 'rgba(240,240,240,0.95)';
            ctx.fillStyle = colBg;
            ctx.fillRect(cx - tw / 2 - 4, cy - 9, tw + 8, 18);
            const lx = cx - tw / 2, ly = cy;
            ctx.lineWidth = 1;
            ctx.lineJoin = 'round';
            ctx.strokeStyle = document.body.classList.contains('dark') ? 'rgba(0,0,0,0.5)' : 'rgba(255,255,255,0.55)';
            ctx.strokeText(label, lx, ly);
            ctx.fillStyle = stroke;
            ctx.fillText(label, lx, ly);
            ctx.restore();
        }
        function updateCoordsLabel(canvasX, canvasY) {
            const el = document.getElementById('canvas-coords');
            if (el) el.textContent = 'X: ' + Math.floor(canvasX) + '  Y: ' + Math.floor(canvasY);
        }
        function dimSnapDotsAlongSegment(mx, my, x1, y1, x2, y2, tol) {
            const o = distToSegParam(mx, my, x1, y1, x2, y2);
            if (o.d > tol) return [];
            return [{ x: x1, y: y1 }, { x: (x1 + x2) / 2, y: (y1 + y2) / 2 }, { x: x2, y: y2 }];
        }
        function drawDimensionSnapHighlights(mx, my) {
            if (mx == null || my == null) return;
            const tol = dimSnapToleranceCanvas();
            const rad = 5;
            const drawn = new Set();
            ctx.save();
            function dot(px, py) {
                const key = Math.round(px * 4) + ':' + Math.round(py * 4);
                if (drawn.has(key)) return;
                drawn.add(key);
                ctx.beginPath();
                ctx.arc(px, py, rad, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(0, 188, 212, 0.35)';
                ctx.strokeStyle = '#00bcd4';
                ctx.lineWidth = 2;
                ctx.fill();
                ctx.stroke();
            }
            for (let si = 0; si < shapes.length; si++) {
                const sh = shapes[si];
                if (sh.type === 'dimension') continue;
                if (sh.type === 'point') continue;
                if (sh.type === 'line') {
                    dimSnapDotsAlongSegment(mx, my, sh.x1, sh.y1, sh.x2, sh.y2, tol).forEach(function(p) { dot(p.x, p.y); });
                    continue;
                }
                if (sh.type === 'rect' || sh.type === 'roundrect') {
                    const x = sh.x, y = sh.y, w = sh.w, h = sh.h;
                    const edges = [[x, y, x + w, y], [x + w, y, x + w, y + h], [x + w, y + h, x, y + h], [x, y + h, x, y]];
                    for (let e = 0; e < 4; e++) {
                        dimSnapDotsAlongSegment(mx, my, edges[e][0], edges[e][1], edges[e][2], edges[e][3], tol).forEach(function(p) { dot(p.x, p.y); });
                    }
                    continue;
                }
                if (sh.type === 'triangle') {
                    const seg = [[sh.x1, sh.y1, sh.x2, sh.y2], [sh.x2, sh.y2, sh.x3, sh.y3], [sh.x3, sh.y3, sh.x1, sh.y1]];
                    for (let e = 0; e < 3; e++) {
                        dimSnapDotsAlongSegment(mx, my, seg[e][0], seg[e][1], seg[e][2], seg[e][3], tol).forEach(function(p) { dot(p.x, p.y); });
                    }
                    continue;
                }
                if (sh.type === 'circle') {
                    const d = Math.abs(Math.hypot(mx - sh.x, my - sh.y) - sh.r);
                    if (d <= tol) {
                        dot(sh.x + sh.r, sh.y);
                        dot(sh.x - sh.r, sh.y);
                        dot(sh.x, sh.y + sh.r);
                        dot(sh.x, sh.y - sh.r);
                    }
                    continue;
                }
                if (sh.type === 'text') {
                    const x = sh.x, y = sh.y, w = sh.w, h = sh.h;
                    const edges = [[x, y, x + w, y], [x + w, y, x + w, y + h], [x + w, y + h, x, y + h], [x, y + h, x, y]];
                    for (let e = 0; e < 4; e++) {
                        dimSnapDotsAlongSegment(mx, my, edges[e][0], edges[e][1], edges[e][2], edges[e][3], tol).forEach(function(p) { dot(p.x, p.y); });
                    }
                }
            }
            ctx.restore();
        }
        var mouseButtonDown = false;
        var eraserHistorySaved = false;

        function handleMouseDown(e) {
            if (e.button !== 0) return; // only left button draws
            mouseButtonDown = true;
            eraserHistorySaved = false;
            const pt = clientToCanvas(e.clientX, e.clientY);
            let x = pt.x, y = pt.y;
            if (currentTool === 'dimension') {
                const rawX = pt.x, rawY = pt.y;
                if (dimPlacing) {
                    dimPendingOff = dimensionPerpOffsetSmart(rawX, rawY, dimDraft.x1, dimDraft.y1, dimDraft.x2, dimDraft.y2, dimDraft.a0, dimDraft.a1);
                    if (Math.abs(dimPendingOff) < 8) dimPendingOff = dimPendingOff >= 0 ? 24 : -24;
                    finishDimensionOffsetAndOpenModal();
                    return;
                }
                const da = findNearestDimAnchor(rawX, rawY);
                if (!da) { showToast(translations[currentLang].dim_snap_hint || 'Snap to edge'); mouseButtonDown = false; return; }
                x = da.x; y = da.y;
                if (!dimDraft) {
                    dimDraft = { a0: { si: da.si, k: da.k }, x1: da.x, y1: da.y };
                    redraw();
                    return;
                }
                if (!isValidDimensionSecondAnchor(dimDraft.a0, da)) {
                    showToast(translations[currentLang].dim_invalid_pair || 'Use parallel sides or the same edge');
                    mouseButtonDown = false;
                    return;
                }
                dimDraft.a1 = { si: da.si, k: da.k };
                dimDraft.x2 = da.x;
                dimDraft.y2 = da.y;
                dimPlacing = true;
                dimPendingOff = dimensionPerpOffsetSmart(rawX, rawY, dimDraft.x1, dimDraft.y1, dimDraft.x2, dimDraft.y2, dimDraft.a0, dimDraft.a1);
                if (Math.abs(dimPendingOff) < 8) dimPendingOff = dimPendingOff >= 0 ? 24 : -24;
                redraw();
                return;
            }
            if (objectSnap) { let snap = findNearestSnapPoint(x, y); if (snap) { x = snap.x; y = snap.y; } }
            if (currentTool === 'point') {
                saveHistory();
                shapes.push({ type: 'point', x: x, y: y, color565: hexTo565(currentColor), colorHex: currentColor });
                redraw();
                saveCanvasData();
            } 
            else if (currentTool === 'text') {
                startX = x;
                startY = y;
                if (objectSnap) { let snap = findNearestSnapPoint(x, y); if (snap) { startX = snap.x; startY = snap.y; } }
                openTextModal();
                return;
            }
            else if (['line', 'circle', 'rect', 'roundrect'].includes(currentTool)) {
                if (drawing) {
                    let fill = document.getElementById('fill').checked;
                    saveHistory();
                    if (currentTool === 'line') {
                        if (cursorConstrain) { let deltaX = Math.abs(x - startX); let deltaY = Math.abs(y - startY); if (deltaX > deltaY) { y = startY; } else { x = startX; } }
                        shapes.push({ type: 'line', x1: startX, y1: startY, x2: x, y2: y, color565: hexTo565(currentColor), colorHex: currentColor });
                    } 
                    else if (currentTool === 'circle') {
                        let r = Math.sqrt((x - startX)**2 + (y - startY)**2);
                        shapes.push({ type: 'circle', x: startX, y: startY, r: r, fill: fill, color565: hexTo565(currentColor), colorHex: currentColor });
                    } 
                    else if (currentTool === 'rect') {
                        let tx = Math.min(startX, x); let ty = Math.min(startY, y); let tw = Math.abs(x - startX); let th = Math.abs(y - startY);
                        shapes.push({ type: 'rect', x: tx, y: ty, w: tw, h: th, fill: fill, color565: hexTo565(currentColor), colorHex: currentColor });
                    } 
                    else {
                        let tx = Math.min(startX, x); let ty = Math.min(startY, y); let tw = Math.abs(x - startX); let th = Math.abs(y - startY); let rr = parseInt(document.getElementById('radius').value);
                        shapes.push({ type: 'roundrect', x: tx, y: ty, w: tw, h: th, r: rr, fill: fill, color565: hexTo565(currentColor), colorHex: currentColor });
                    }
                    drawing = false;
                    redraw();
                    saveCanvasData();
                } else { startX = x; startY = y; drawing = true; }
            } 
            else if (currentTool === 'triangle') {
                triPoints.push({x: x, y: y});
                if (triPoints.length === 3) {
                    let fill = document.getElementById('fill').checked;
                    saveHistory();
                    shapes.push({ type: 'triangle', x1: triPoints[0].x, y1: triPoints[0].y, x2: triPoints[1].x, y2: triPoints[1].y, x3: triPoints[2].x, y3: triPoints[2].y, fill: fill, color565: hexTo565(currentColor), colorHex: currentColor });
                    triPoints = [];
                    redraw();
                    saveCanvasData();
                } else { redraw(); }
            } 
            else if (currentTool === 'eraser') {
                for (let i = shapes.length - 1; i >= 0; i--) {
                    if (hitTest(shapes[i], x, y)) {
                        saveHistory();
                        eraserHistorySaved = true;
                        shapes.splice(i, 1);
                        shiftDimRefsAfterDeleteAt(i);
                        redraw();
                        saveCanvasData();
                        break;
                    }
                }
            } 
            else if (currentTool === 'selection') { selectStartX = x; selectStartY = y; drawingSelect = true; selectedShapes = []; } 
            else if (currentTool === 'move') {
                for (let i = shapes.length - 1; i >= 0; i--) {
                    if (hitTest(shapes[i], x, y)) {
                        if (!selectedShapes.includes(i)) { selectedShapes = [i]; }
                        saveHistory();
                        dragging = true;
                        dragShapeSnapshots = {};
                        let uminX = Infinity, uminY = Infinity, umaxX = -Infinity, umaxY = -Infinity;
                        for (let j of selectedShapes) {
                            dragShapeSnapshots[j] = JSON.parse(JSON.stringify(shapes[j]));
                            let b = getBoundingBox(shapes[j]);
                            uminX = Math.min(uminX, b.x);
                            uminY = Math.min(uminY, b.y);
                            umaxX = Math.max(umaxX, b.x + b.w);
                            umaxY = Math.max(umaxY, b.y + b.h);
                        }
                        dragPivotStartX = (uminX + umaxX) / 2;
                        dragPivotStartY = (uminY + umaxY) / 2;
                        startX = x;
                        startY = y;
                        redraw();
                        break;
                    }
                }
            }
        }

        function updateCursorConstrain() {
            cursorConstrain = document.getElementById('cursor-constrain').checked;
            showToast(cursorConstrain ? translations[currentLang].constrain_enabled : translations[currentLang].constrain_disabled);
        }

        function updateObjectSnap() {
            objectSnap = document.getElementById('object-snap').checked;
            showToast(objectSnap ? translations[currentLang].snap_enabled : translations[currentLang].snap_disabled);
        }

        function handleMouseMove(e) {
            if (isPanning) return;
            const pt = clientToCanvas(e.clientX, e.clientY);
            let x = pt.x, y = pt.y;
            if (currentTool === 'dimension') { dimHoverCanvasX = x; dimHoverCanvasY = y; } else { dimHoverCanvasX = null; dimHoverCanvasY = null; }
            updateCoordsLabel(x, y);
            paintRulers(x, y);
            if (cursorConstrain && drawing) {
                let constrainedX = x;
                let constrainedY = y;
                let deltaX = Math.abs(x - startX);
                let deltaY = Math.abs(y - startY);
                if (deltaX > deltaY) {
                    constrainedY = startY;
                } else {
                    constrainedX = startX;
                }
                x = constrainedX;
                y = constrainedY;
            }
            
            let snap = null;
            if (objectSnap) {
                snap = findNearestSnapPoint(x, y);
                if (snap) {
                    x = snap.x;
                    y = snap.y;
                    let snapIndicator = document.getElementById('snap-indicator');
                    snapIndicator.style.left = `${e.clientX + 10}px`;
                    snapIndicator.style.top = `${e.clientY - 10}px`;
                    snapIndicator.innerHTML = `<i class="fas ${snap.icon}"></i> ${snap.type}`;
                    snapIndicator.style.display = 'block';
                } else { document.getElementById('snap-indicator').style.display = 'none'; }
            } else { document.getElementById('snap-indicator').style.display = 'none'; }
            if (currentTool === 'dimension') {
                let px = x, py = y;
                const dsn = findNearestDimAnchor(x, y);
                if (dsn) { px = dsn.x; py = dsn.y; }
                if (dimPlacing && dimDraft && dimDraft.x2 !== undefined) {
                    dimPendingOff = dimensionPerpOffsetSmart(x, y, dimDraft.x1, dimDraft.y1, dimDraft.x2, dimDraft.y2, dimDraft.a0, dimDraft.a1);
                    redraw();
                    drawDimensionAux({ x1: dimDraft.x1, y1: dimDraft.y1, x2: dimDraft.x2, y2: dimDraft.y2, off: dimPendingOff, drivingValue: null, a0: dimDraft.a0, a1: dimDraft.a1 });
                    return;
                }
                if (dimDraft && dimDraft.x2 === undefined) {
                    redraw();
                    ctx.save();
                    ctx.strokeStyle = '#888888';
                    ctx.setLineDash([4, 4]);
                    ctx.beginPath(); ctx.moveTo(dimDraft.x1, dimDraft.y1); ctx.lineTo(px, py); ctx.stroke();
                    ctx.setLineDash([]);
                    ctx.restore();
                    return;
                }
                let newHovered = null;
                for (let i = shapes.length - 1; i >= 0; i--) { if (hitTest(shapes[i], x, y)) { newHovered = i; break; } }
                hovered = newHovered;
                redraw();
                return;
            }
            // Eraser: continuously erase while dragging
            if (currentTool === 'eraser' && mouseButtonDown) {
                let erased = false;
                for (let i = shapes.length - 1; i >= 0; i--) {
                    if (hitTest(shapes[i], x, y)) {
                        if (!erased && !eraserHistorySaved) {
                            saveHistory();
                            eraserHistorySaved = true;
                        }
                        shapes.splice(i, 1);
                        shiftDimRefsAfterDeleteAt(i);
                        erased = true;
                    }
                }
                if (erased) { redraw(); saveCanvasData(); }
                return;
            }

            if (drawing && ['line', 'circle', 'rect', 'roundrect'].includes(currentTool)) {
                redraw();
                ctx.strokeStyle = currentColor;
                ctx.fillStyle = currentColor;
                ctx.lineWidth = 1;
                if (currentTool === 'line') { ctx.beginPath(); ctx.moveTo(startX, startY); ctx.lineTo(x, y); ctx.stroke(); } 
                else if (currentTool === 'circle') { let r = Math.sqrt((x - startX)**2 + (y - startY)**2); ctx.beginPath(); ctx.arc(startX, startY, r, 0, 2 * Math.PI); if (document.getElementById('fill').checked) { ctx.fill(); } else { ctx.stroke(); } } 
                else if (currentTool === 'rect') { let tx = Math.min(startX, x); let ty = Math.min(startY, y); let tw = Math.abs(x - startX); let th = Math.abs(y - startY); if (document.getElementById('fill').checked) { ctx.fillRect(tx, ty, tw, th); } else { ctx.strokeRect(tx, ty, tw, th); } } 
                else if (currentTool === 'roundrect') { let tx = Math.min(startX, x); let ty = Math.min(startY, y); let tw = Math.abs(x - startX); let th = Math.abs(y - startY); let rr = parseInt(document.getElementById('radius').value); drawRoundRectPath(tx, ty, tw, th, rr); if (document.getElementById('fill').checked) { ctx.fill(); } else { ctx.stroke(); } }
            } 
            else if (currentTool === 'triangle' && triPoints.length > 0) {
                redraw();
                ctx.strokeStyle = currentColor;
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(triPoints[0].x, triPoints[0].y);
                for (let i = 1; i < triPoints.length; i++) { ctx.lineTo(triPoints[i].x, triPoints[i].y); }
                ctx.lineTo(x, y);
                ctx.stroke();
            } 
            else if (drawingSelect && currentTool === 'selection') {
                redraw();
                ctx.strokeStyle = '#00ff00';
                ctx.lineWidth = 1;
                ctx.setLineDash([5, 5]);
                ctx.strokeRect(Math.min(selectStartX, x), Math.min(selectStartY, y), Math.abs(x - selectStartX), Math.abs(y - selectStartY));
                ctx.setLineDash([]);
            } 
            else if (dragging && currentTool === 'move' && dragShapeSnapshots) {
                let pivotX = dragPivotStartX + (x - startX);
                let pivotY = dragPivotStartY + (y - startY);
                if (objectSnap) {
                    let snap = findNearestSnapPoint(pivotX, pivotY);
                    if (snap) { pivotX = snap.x; pivotY = snap.y; }
                }
                let deltaX = pivotX - dragPivotStartX;
                let deltaY = pivotY - dragPivotStartY;
                for (let i of selectedShapes) {
                    let src = dragShapeSnapshots[i];
                    if (!src) continue;
                    let s = shapes[i];
                    if (s.type === 'point') { s.x = src.x + deltaX; s.y = src.y + deltaY; }
                    else if (s.type === 'line') { s.x1 = src.x1 + deltaX; s.y1 = src.y1 + deltaY; s.x2 = src.x2 + deltaX; s.y2 = src.y2 + deltaY; }
                    else if (s.type === 'circle') { s.x = src.x + deltaX; s.y = src.y + deltaY; }
                    else if (s.type === 'rect' || s.type === 'roundrect') { s.x = src.x + deltaX; s.y = src.y + deltaY; }
                    else if (s.type === 'triangle') { s.x1 = src.x1 + deltaX; s.y1 = src.y1 + deltaY; s.x2 = src.x2 + deltaX; s.y2 = src.y2 + deltaY; s.x3 = src.x3 + deltaX; s.y3 = src.y3 + deltaY; }
                    else if (s.type === 'text') { s.x = src.x + deltaX; s.y = src.y + deltaY; }
                    else if (s.type === 'dimension') { s.x1 = src.x1 + deltaX; s.y1 = src.y1 + deltaY; s.x2 = src.x2 + deltaX; s.y2 = src.y2 + deltaY; }
                }
                redraw();
            } else {
                let newHovered = null;
                for (let i = shapes.length - 1; i >= 0; i--) { if (hitTest(shapes[i], x, y)) { newHovered = i; break; } }
                if (newHovered !== hovered) { hovered = newHovered; redraw(); }
            }

            if (currentTool === 'move' && !dragging) {
                let newHovered = null;
                for (let i = shapes.length - 1; i >= 0; i--) { if (hitTest(shapes[i], x, y)) { newHovered = i; break; } }
                if (newHovered !== hovered) { hovered = newHovered; redraw(); }
            }
        }

        function handleMouseUp(e) {
            mouseButtonDown = false;
            eraserHistorySaved = false;
            const endPt = clientToCanvas(e.clientX, e.clientY);
            let endX = endPt.x, endY = endPt.y;
            if (objectSnap) {
                let snap = findNearestSnapPoint(endX, endY);
                if (snap) { endX = snap.x; endY = snap.y; }
            }

            if (drawingSelect && currentTool === 'selection') {
                if (objectSnap) { let snap = findNearestSnapPoint(endX, endY); if (snap) { endX = snap.x; endY = snap.y; } }
                let leftToRight = endX > selectStartX;
                let selectRect = { x: Math.min(selectStartX, endX), y: Math.min(selectStartY, endY), w: Math.abs(endX - selectStartX), h: Math.abs(endY - selectStartY) };
                selectedShapes = [];
                for (let i = 0; i < shapes.length; i++) { if (leftToRight ? isFullyEnclosed(shapes[i], selectRect) : isIntersecting(shapes[i], selectRect)) { selectedShapes.push(i); } }
                drawingSelect = false;
                redraw();
            } else if (dragging && currentTool === 'move') {
                dragging = false;
                dragShapeSnapshots = null;
                saveCanvasData();
            }
        }
        // Also reset mouseButtonDown if mouse leaves window
        document.addEventListener('mouseup', function(e) { if (e.button === 0) { mouseButtonDown = false; eraserHistorySaved = false; } });

        canvas.addEventListener('mousedown', handleMouseDown);
        canvas.addEventListener('mousemove', handleMouseMove);
        canvas.addEventListener('mouseup', handleMouseUp);
        canvas.addEventListener('mouseleave', function() { const el = document.getElementById('canvas-coords'); if (el) el.textContent = 'X: —  Y: —'; dimHoverCanvasX = null; dimHoverCanvasY = null; redraw(); paintRulers(null, null); });
        canvas.addEventListener('contextmenu', function(e) {
            const p = clientToCanvas(e.clientX, e.clientY);
            const x = p.x, y = p.y;
            for (let i = shapes.length - 1; i >= 0; i--) {
                if (shapes[i].type === 'dimension' && hitTest(shapes[i], x, y)) {
                    e.preventDefault();
                    dimEditingIndex = i;
                    dimPlaceIsNew = false;
                    selectedShapes = [i];
                    openDimPlaceModal();
                    redraw();
                    return;
                }
            }
        });

        // Always register touch events for direct finger drawing
        canvas.addEventListener('touchstart', handleTouchStart, { passive: false });
        canvas.addEventListener('touchmove', handleTouchMove, { passive: false });
        canvas.addEventListener('touchend', handleTouchEnd, { passive: false });
        canvas.addEventListener('touchcancel', handleTouchEnd, { passive: false });

        // Middle-mouse button pan
        canvas.addEventListener('mousedown', function(e) {
            if (e.button === 1) {
                e.preventDefault();
                isPanning = true;
                panStartX = e.clientX - canvasOffsetX;
                panStartY = e.clientY - canvasOffsetY;
            }
        });
        document.addEventListener('mousemove', function(e) {
            if (isPanning) {
                canvasOffsetX = e.clientX - panStartX;
                canvasOffsetY = e.clientY - panStartY;
                applyCanvasTransform();
            }
        });
        document.addEventListener('mouseup', function(e) {
            if (e.button === 1 && isPanning) {
                isPanning = false;
                saveConfig();
            }
        });

        canvas.addEventListener('wheel', function(e) { 
            e.preventDefault(); 
            let delta = e.deltaY < 0 ? 0.1 : -0.1; 
            let oldScale = scale;
            let newScale = scale + delta; 
            if (newScale < 0.1) newScale = 0.1; 
            if (newScale > 10) newScale = 10; 
            if (newScale === oldScale) return;
            const vp = document.getElementById('draw-viewport');
            const rect = vp.getBoundingClientRect();
            const fx = rect.left + rect.width * 0.5;
            const fy = rect.top + rect.height * 0.5;
            const r = newScale / oldScale;
            canvasOffsetX += (fx - rect.left) * (1 - r);
            canvasOffsetY += (fy - rect.top) * (1 - r);
            scale = newScale;
            applyCanvasTransform();
            document.getElementById('scale').value = newScale;
            saveConfig();
        });

        function isFullyEnclosed(shape, rect) { let bbox = getBoundingBox(shape); return bbox.x >= rect.x && bbox.y >= rect.y && (bbox.x + bbox.w) <= (rect.x + rect.w) && (bbox.y + bbox.h) <= (rect.y + rect.h); }
        function isIntersecting(shape, rect) { let bbox = getBoundingBox(shape); return !(bbox.x + bbox.w < rect.x || bbox.y + bbox.h < rect.y || bbox.x > rect.x + rect.w || bbox.y > rect.y + rect.h); }
        
        function openTextModal() { document.getElementById('text-modal').style.display = 'block'; document.getElementById('text-input').focus(); textModalOpen = true; }
        function openHotkeysModal() { document.getElementById('hotkeys-modal').style.display = 'block'; document.getElementById('hotkeys-overlay').style.display = 'block'; }
        function closeHotkeysModal() { document.getElementById('hotkeys-modal').style.display = 'none'; document.getElementById('hotkeys-overlay').style.display = 'none'; }
        window.addEventListener('click', function(event) { var modal = document.getElementById('hotkeys-modal'); if (event.target === modal) { closeHotkeysModal(); } });
        function closeTextModal() { document.getElementById('text-modal').style.display = 'none'; textModalOpen = false; }
        function applyText() {
            let text = document.getElementById('text-input').value.trim();
            if (!text) { showToast('Enter some text!'); return; }
            let size = parseInt(document.getElementById('text-size-select').value);
            let charWidth = 6 * size;
            let w = text.length * charWidth;
            let h = 8 * size;
            saveHistory();
            shapes.push({ type: 'text', x: startX, y: startY, text: text, size: size, colorHex: currentColor, color565: hexTo565(currentColor), w: w, h: h });
            redraw();
            saveCanvasData();
            closeTextModal();
            document.getElementById('text-input').value = '';
        }

        function getBoundingBox(shape) {
            let minX, minY, maxX, maxY;
            if (shape.type === 'point') { minX = shape.x; minY = shape.y; maxX = shape.x + 1; maxY = shape.y + 1; } 
            else if (shape.type === 'line') { minX = Math.min(shape.x1, shape.x2); minY = Math.min(shape.y1, shape.y2); maxX = Math.max(shape.x1, shape.x2); maxY = Math.max(shape.y1, shape.y2); } 
            else if (shape.type === 'circle') { minX = shape.x - shape.r; minY = shape.y - shape.r; maxX = shape.x + shape.r; maxY = shape.y + shape.r; } 
            else if (shape.type === 'rect' || shape.type === 'roundrect') { minX = shape.x; minY = shape.y; maxX = shape.x + shape.w; maxY = shape.y + shape.h; } 
            else if (shape.type === 'triangle') { minX = Math.min(shape.x1, shape.x2, shape.x3); minY = Math.min(shape.y1, shape.y2, shape.y3); maxX = Math.max(shape.x1, shape.x2, shape.x3); maxY = Math.max(shape.y1, shape.y2, shape.y3); } 
            else if (shape.type === 'text') { minX = shape.x; minY = shape.y; maxX = shape.x + shape.w; maxY = shape.y + shape.h; }
            else if (shape.type === 'dimension') {
                const lay = getDimensionLayout(shape);
                const pts = [lay.ext1a, lay.ext1b, lay.ext2a, lay.ext2b, lay.dimA, lay.dimB];
                minX = Math.min.apply(null, pts.map(function(p) { return p.x; }));
                minY = Math.min.apply(null, pts.map(function(p) { return p.y; }));
                maxX = Math.max.apply(null, pts.map(function(p) { return p.x; }));
                maxY = Math.max.apply(null, pts.map(function(p) { return p.y; }));
            }
            return {x: minX, y: minY, w: maxX - minX, h: maxY - minY};
        }

        function drawRoundRectPath(x, y, w, h, r) {
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.lineTo(x + w - r, y);
            ctx.quadraticCurveTo(x + w, y, x + w, y + r);
            ctx.lineTo(x + w, y + h - r);
            ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
            ctx.lineTo(x + r, y + h);
            ctx.quadraticCurveTo(x, y + h, x, y + h - r);
            ctx.lineTo(x, y + r);
            ctx.quadraticCurveTo(x, y, x + r, y);
            ctx.closePath();
        }

        function redraw() {
            syncAttachedDimensions();
            ctx.fillStyle = bgColor;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            if (bgImage) { ctx.drawImage(bgImage, 0, 0, canvas.width, canvas.height); }
            for (let i = 0; i < shapes.length; i++) {
                let s = shapes[i];
                ctx.strokeStyle = s.colorHex;
                ctx.fillStyle = s.colorHex;
                ctx.lineWidth = 1;
                if (s.type === 'point') { ctx.fillRect(s.x, s.y, 1, 1); } 
                else if (s.type === 'line') { ctx.beginPath(); ctx.moveTo(s.x1, s.y1); ctx.lineTo(s.x2, s.y2); ctx.stroke(); } 
                else if (s.type === 'circle') { ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, 2 * Math.PI); if (s.fill) ctx.fill(); else ctx.stroke(); } 
                else if (s.type === 'rect') { if (s.fill) ctx.fillRect(s.x, s.y, s.w, s.h); else ctx.strokeRect(s.x, s.y, s.w, s.h); } 
                else if (s.type === 'roundrect') { drawRoundRectPath(s.x, s.y, s.w, s.h, s.r); if (s.fill) ctx.fill(); else ctx.stroke(); } 
                else if (s.type === 'triangle') { ctx.beginPath(); ctx.moveTo(s.x1, s.y1); ctx.lineTo(s.x2, s.y2); ctx.lineTo(s.x3, s.y3); ctx.closePath(); if (s.fill) ctx.fill(); else ctx.stroke(); } 
                else if (s.type === 'text') { ctx.font = `${s.size * 8}px monospace`; ctx.textBaseline = 'top'; ctx.fillStyle = s.colorHex; ctx.fillText(s.text, s.x, s.y); }
                else if (s.type === 'dimension') { drawDimensionAux(s); }
                if (selectedShapes.includes(i)) { highlightShape(s); }
            }
            if (hovered !== null && shapes[hovered]) { highlightShape(shapes[hovered]); }
            if (currentTool === 'dimension' && dimHoverCanvasX != null && dimHoverCanvasY != null) { drawDimensionSnapHighlights(dimHoverCanvasX, dimHoverCanvasY); }
        }

        function highlightShape(s) {
            if (!s || !s.type) return;
            ctx.strokeStyle = '#ffff00';
            ctx.lineWidth = 2;
            if (s.type === 'point') { ctx.beginPath(); ctx.arc(s.x + 0.5, s.y + 0.5, 2, 0, 2 * Math.PI); ctx.stroke(); } 
            else if (s.type === 'line') { ctx.beginPath(); ctx.moveTo(s.x1, s.y1); ctx.lineTo(s.x2, s.y2); ctx.stroke(); } 
            else if (s.type === 'circle') { ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, 2 * Math.PI); ctx.stroke(); } 
            else if (s.type === 'rect') { ctx.strokeRect(s.x, s.y, s.w, s.h); } 
            else if (s.type === 'roundrect') { drawRoundRectPath(s.x, s.y, s.w, s.h, s.r); ctx.stroke(); } 
            else if (s.type === 'triangle') { ctx.beginPath(); ctx.moveTo(s.x1, s.y1); ctx.lineTo(s.x2, s.y2); ctx.lineTo(s.x3, s.y3); ctx.closePath(); ctx.stroke(); } 
            else if (s.type === 'text') { ctx.strokeRect(s.x, s.y, s.w, s.h); }
            else if (s.type === 'dimension') { let b = getBoundingBox(s); ctx.strokeRect(b.x, b.y, b.w, b.h); }
            ctx.lineWidth = 1;
        }

        function distToLine(px, py, x1, y1, x2, y2) { var dx = x2 - x1; var dy = y2 - y1; var len2 = dx * dx + dy * dy; if (len2 === 0) return Math.sqrt((px - x1) * (px - x1) + (py - y1) * (py - y1)); var proj = ((px - x1) * dx + (py - y1) * dy) / len2; proj = Math.max(0, Math.min(1, proj)); var qx = x1 + proj * dx; var qy = y1 + proj * dy; dx = px - qx; dy = py - qy; return Math.sqrt(dx * dx + dy * dy); }
        function pointInTriangle(px, py, ax, ay, bx, by, cx, cy) { var v0x = cx - ax; var v0y = cy - ay; var v1x = bx - ax; var v1y = by - ay; var v2x = px - ax; var v2y = py - ay; var dot00 = v0x * v0x + v0y * v0y; var dot01 = v0x * v1x + v0y * v1y; var dot02 = v0x * v2x + v0y * v2y; var dot11 = v1x * v1x + v1y * v1y; var dot12 = v1x * v2x + v1y * v2y; var invDenom = 1 / (dot00 * dot11 - dot01 * dot01); var u = (dot11 * dot02 - dot01 * dot12) * invDenom; var v = (dot00 * dot12 - dot01 * dot02) * invDenom; return (u >= 0) && (v >= 0) && (u + v <= 1); }

        function hitTest(shape, mx, my) {
            var hs = 1 / Math.max(canvasPxPerScreenPx(), 1e-6);
            var edgeTol = 2 * hs;
            var hit = false;
            if (shape.type === 'point') { hit = mx >= shape.x && mx <= shape.x + 1 && my >= shape.y && my <= shape.y + 1; } 
            else if (shape.type === 'line') { var dist = distToLine(mx, my, shape.x1, shape.y1, shape.x2, shape.y2); hit = dist <= edgeTol; } 
            else if (shape.type === 'circle') { var dx = mx - shape.x; var dy = my - shape.y; var dist = Math.sqrt(dx * dx + dy * dy); if (shape.fill) { hit = dist <= shape.r; } else { hit = Math.abs(dist - shape.r) <= edgeTol; } } 
            else if (shape.type === 'rect' || shape.type === 'roundrect') { var left = shape.x; var right = shape.x + shape.w; var top = shape.y; var bottom = shape.y + shape.h; if (shape.fill) { hit = mx >= left && mx <= right && my >= top && my <= bottom; } else { var d1 = distToLine(mx, my, left, top, right, top); var d2 = distToLine(mx, my, right, top, right, bottom); var d3 = distToLine(mx, my, right, bottom, left, bottom); var d4 = distToLine(mx, my, left, bottom, left, top); hit = Math.min(d1, d2, d3, d4) <= edgeTol; } } 
            else if (shape.type === 'triangle') { if (shape.fill) { hit = pointInTriangle(mx, my, shape.x1, shape.y1, shape.x2, shape.y2, shape.x3, shape.y3); } else { var d1 = distToLine(mx, my, shape.x1, shape.y1, shape.x2, shape.y2); var d2 = distToLine(mx, my, shape.x2, shape.y2, shape.x3, shape.y3); var d3 = distToLine(mx, my, shape.x3, shape.y3, shape.x1, shape.y1); hit = Math.min(d1, d2, d3) <= edgeTol; } }
            else if (shape.type === 'text') { hit = mx >= shape.x && mx <= shape.x + shape.w && my >= shape.y && my <= shape.y + shape.h; }
            else if (shape.type === 'dimension') {
                const lay = getDimensionLayout(shape);
                const thr = 6 * hs;
                hit = distToLine(mx, my, lay.ext1a.x, lay.ext1a.y, lay.ext1b.x, lay.ext1b.y) <= thr || distToLine(mx, my, lay.ext2a.x, lay.ext2a.y, lay.ext2b.x, lay.ext2b.y) <= thr || distToLine(mx, my, lay.dimA.x, lay.dimA.y, lay.dimB.x, lay.dimB.y) <= thr;
            }
            return hit;
        }

        function generateCode() {
            let code = `${screenObject}.fillScreen(${hexTo565(bgColor)});\n`;
            for (let s of shapes) {
                if (s.type === 'dimension') continue;
                let col = s.color565;
                let transformed = transformShape(s);
                if (transformed.type === 'point') { let rx = Math.round(transformed.x); let ry = Math.round(transformed.y); code += `${screenObject}.drawPixel(${rx}, ${ry}, ${col});\n`; } 
                else if (transformed.type === 'line') { let x1 = Math.round(transformed.x1); let y1 = Math.round(transformed.y1); let x2 = Math.round(transformed.x2); let y2 = Math.round(transformed.y2); if (y1 === y2) { let startX = Math.min(x1, x2); let endX = Math.max(x1, x2); code += `${screenObject}.drawFastHLine(${startX}, ${y1}, ${endX - startX}, ${col});\n`; } else if (x1 === x2) { let startY = Math.min(y1, y2); let endY = Math.max(y1, y2); code += `${screenObject}.drawFastVLine(${x1}, ${startY}, ${endY - startY}, ${col});\n`; } else { code += `${screenObject}.drawLine(${x1}, ${y1}, ${x2}, ${y2}, ${col});\n`; } } 
                else if (transformed.type === 'circle') { let func = transformed.fill ? 'fillCircle' : 'drawCircle'; let cx = Math.round(transformed.x); let cy = Math.round(transformed.y); let r = Math.round(transformed.r); code += `${screenObject}.${func}(${cx}, ${cy}, ${r}, ${col});\n`; } 
                else if (transformed.type === 'rect') { let func = transformed.fill ? 'fillRect' : 'drawRect'; let rx = Math.round(transformed.x); let ry = Math.round(transformed.y); let rw = Math.round(transformed.w); let rh = Math.round(transformed.h); code += `${screenObject}.${func}(${rx}, ${ry}, ${rw}, ${rh}, ${col});\n`; } 
                else if (transformed.type === 'roundrect') { let func = transformed.fill ? 'fillRoundRect' : 'drawRoundRect'; let rx = Math.round(transformed.x); let ry = Math.round(transformed.y); let rw = Math.round(transformed.w); let rh = Math.round(transformed.h); let rr = Math.round(transformed.r); code += `${screenObject}.${func}(${rx}, ${ry}, ${rw}, ${rh}, ${rr}, ${col});\n`; } 
                else if (transformed.type === 'triangle') { let func = transformed.fill ? 'fillTriangle' : 'drawTriangle'; let x1 = Math.round(transformed.x1); let y1 = Math.round(transformed.y1); let x2 = Math.round(transformed.x2); let y2 = Math.round(transformed.y2); let x3 = Math.round(transformed.x3); let y3 = Math.round(transformed.y3); code += `${screenObject}.${func}(${x1}, ${y1}, ${x2}, ${y2}, ${x3}, ${y3}, ${col});\n`; }
                else if (transformed.type === 'text') {
                    code += `${screenObject}.setTextColor(${s.color565});\n`;
                    code += `${screenObject}.setTextSize(${s.size});\n`;
                    code += `${screenObject}.setCursor(${Math.round(transformed.x)}, ${Math.round(transformed.y)});\n`;
                    code += `${screenObject}.print(F("${escapeCppStringContent(s.text)}"));\n`;
                }
            }
            document.getElementById('code_output').innerHTML = code;
            hljs.highlightElement(document.getElementById('code_output'));
            const preOut = document.querySelector('#output-dock pre');
            if (preOut) { preOut.scrollTop = 0; preOut.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
        }

        function transformShape(s) {
            let transformed = {...s};
            if (currentOrigin.includes('right')) {
                if ('x' in s) transformed.x = canvas.width - s.x - (s.w || 0);
                if ('x1' in s) transformed.x1 = canvas.width - s.x1;
                if ('x2' in s) transformed.x2 = canvas.width - s.x2;
                if ('x3' in s) transformed.x3 = canvas.width - s.x3;
            }
            if (currentOrigin.includes('bottom')) {
                if ('y' in s) transformed.y = canvas.height - s.y - (s.h || 0);
                if ('y1' in s) transformed.y1 = canvas.height - s.y1;
                if ('y2' in s) transformed.y2 = canvas.height - s.y2;
                if ('y3' in s) transformed.y3 = canvas.height - s.y3;
            }
            if (s.type === 'text') { if (currentOrigin.includes('right')) { transformed.x = canvas.width - s.x - s.w; } if (currentOrigin.includes('bottom')) { transformed.y = canvas.height - s.y - s.h; } }
            return transformed;
        }

        // Monaco editor instance
        var monacoEditor = null;

        // Initialize Monaco editor
        require(['vs/editor/editor.main'], function() {
            monacoEditor = monaco.editor.create(document.getElementById('monaco-editor-container'), {
                value: '',
                language: 'cpp',
                theme: currentTheme === 'dark' ? 'vs-dark' : 'vs',
                minimap: { enabled: true, maxColumn: 60, scale: 1 },
                fontSize: 14,
                fontWeight: 'bold',
                lineNumbers: 'on',
                scrollBeyondLastLine: false,
                wordWrap: 'on',
                automaticLayout: true,
                glyphMargin: true,
                glyphMarginWidth: 20,
                bracketPairColorization: { enabled: true },
                suggestOnTriggerCharacters: true,
                tabSize: 4,
                cursorBlinking: 'smooth',
                cursorSmoothCaretAnimation: 'on',
                smoothScrolling: true,
                padding: { top: 10, bottom: 10 },
                rulers: [40, 80, 120, 160],
            });

            // Persist code to localStorage on every change
            monacoEditor.onDidChangeModelContent(function() {
                const code = monacoEditor.getValue();
                localStorage.setItem('tft_modal_code', code);
            });

            // Register custom semantic tokens provider for TFT functions
            monaco.languages.registerDocumentSemanticTokensProvider('cpp', {
                getLegend: function() {
                    return {
                        tokenTypes: ['function', 'parameter', 'property', 'type'],
                        tokenModifiers: ['declaration', 'readonly']
                    };
                },
                provideDocumentSemanticTokens: function(model) {
                    const lines = model.getLinesContent();
                    const tokens = [];
                    let prevLine = 0;
                    let prevChar = 0;

                    const tftMethods = [
                        'fillScreen', 'drawPixel', 'drawLine', 'drawFastHLine', 'drawFastVLine',
                        'drawRect', 'fillRect', 'drawRoundRect', 'fillRoundRect',
                        'drawCircle', 'fillCircle', 'drawTriangle', 'fillTriangle',
                        'setCursor', 'print', 'println', 'setTextColor', 'setTextSize',
                        'setRotation', 'invertDisplay', 'setAddrWindow', 'pushColor',
                        'pushColors', 'readPixel', 'getRotation', 'height', 'width',
                        'drawBitmap', 'fillEllipse', 'drawEllipse'
                    ];

                    for (let lineIdx = 0; lineIdx < lines.length; lineIdx++) {
                        const line = lines[lineIdx];
                        const regex = /\.(\w+)\s*\(/g;
                        let m;
                        while ((m = regex.exec(line)) !== null) {
                            const methodName = m[1];
                            if (tftMethods.includes(methodName)) {
                                const deltaLine = lineIdx - prevLine;
                                const startChar = m.index + 1; // skip the '.'
                                const deltaChar = deltaLine === 0 ? startChar - prevChar : startChar;
                                prevLine = lineIdx;
                                prevChar = startChar;
                                tokens.push(deltaLine, deltaChar, methodName.length, 0, 0); // function type
                            }
                        }
                    }

                    return { data: new Uint32Array(tokens), resultId: undefined };
                },
                releaseDocumentSemanticTokens: function() {}
            });
        });

        loadSavedConfig();
        if (window.innerWidth <= 768) {
            leftOpen = false;
            rightOpen = false;
            document.getElementById('left-panel').classList.remove('active');
            document.getElementById('right-panel').classList.remove('active');
        } else {
            leftOpen = true;
            rightOpen = true;
            document.getElementById('left-panel').style.left = '0px';
            document.getElementById('right-panel').style.right = '0px';
        }
        updateCodeWidth();
        updateToolHighlights();

        // ── Animated Gears Background ──────────────────────────────────────────
        (function() {
            const gearsCanvas = document.getElementById('gears-bg');
            if (!gearsCanvas) return;
            const gctx = gearsCanvas.getContext('2d');
            
            function resizeGearsCanvas() {
                gearsCanvas.width = window.innerWidth;
                gearsCanvas.height = window.innerHeight;
                initGears();
            }
            
            // Generate random gradient colors for a gear
            function generateGearColors() {
                if (gearsColorMode === 'solid') {
                    // Use solid color with slight gradient variation
                    const baseColor = gearsSolidColor;
                    return {
                        color1: baseColor,
                        color2: baseColor,
                        color3: baseColor,
                        isSolid: true
                    };
                }
                
                // Random colors
                const hue1 = Math.random() * 360;
                const hue2 = (hue1 + 40 + Math.random() * 60) % 360;
                const hue3 = (hue2 + 40 + Math.random() * 60) % 360;
                
                return {
                    color1: `hsla(${hue1}, 80%, 60%, 0.6)`,
                    color2: `hsla(${hue2}, 80%, 65%, 0.55)`,
                    color3: `hsla(${hue3}, 80%, 60%, 0.6)`,
                    isSolid: false
                };
            }
            
            // Gear class
            class Gear {
                constructor(x, y, radius, teeth, rotationSpeed, phase) {
                    this.x = x;
                    this.y = y;
                    this.radius = radius;
                    this.teeth = teeth;
                    this.rotation = phase || 0;
                    this.rotationSpeed = rotationSpeed;
                    this.toothDepth = radius * 0.18;
                    // Wider teeth: increased from 0.35 to 0.45
                    this.toothWidth = (Math.PI * 2) / teeth * 0.45;
                    this.colors = generateGearColors();
                    // Random spoke rotation offset to avoid alignment
                    this.spokeOffset = Math.random() * Math.PI * 2;
                }
                
                draw(ctx, time) {
                    this.rotation = this.rotationSpeed * time;
                    
                    // Create gradient for this gear
                    const gradient = ctx.createLinearGradient(
                        this.x - this.radius - this.toothDepth, 
                        this.y - this.radius - this.toothDepth,
                        this.x + this.radius + this.toothDepth, 
                        this.y + this.radius + this.toothDepth
                    );
                    
                    gradient.addColorStop(0, this.colors.color1);
                    gradient.addColorStop(0.5, this.colors.color2);
                    gradient.addColorStop(1, this.colors.color3);
                    
                    ctx.save();
                    ctx.translate(this.x, this.y);
                    ctx.rotate(this.rotation);
                    
                    // Draw gear outline with trapezoidal teeth
                    const outerRadius = this.radius + this.toothDepth;
                    const innerRadius = this.radius - this.toothDepth * 0.5;
                    const teethCount = this.teeth;
                    const toothAngleWidth = this.toothWidth;
                    
                    ctx.beginPath();
                    
                    for (let i = 0; i < teethCount; i++) {
                        const toothCenterAngle = (Math.PI * 2 * i) / teethCount;
                        
                        // Angles for trapezoid tooth
                        const halfTooth = toothAngleWidth / 2;
                        // Wider tip relative to base (0.65 = 65% of half-tooth width)
                        const tipHalfAngle = halfTooth * 0.65;
                        
                        // Tooth base starts earlier to create wider base
                        const toothBaseStart = toothCenterAngle - halfTooth * 0.8;
                        const toothBaseEnd = toothCenterAngle + halfTooth * 0.8;
                        
                        // Tooth tip angles
                        const toothTipStart = toothCenterAngle - tipHalfAngle;
                        const toothTipEnd = toothCenterAngle + tipHalfAngle;
                        
                        // Gap boundaries
                        const gapStart = toothCenterAngle - halfTooth;
                        
                        if (i === 0) {
                            ctx.moveTo(Math.cos(gapStart) * innerRadius, Math.sin(gapStart) * innerRadius);
                        }
                        
                        // Arc in gap (valley)
                        ctx.arc(0, 0, innerRadius, gapStart, toothBaseStart, false);
                        
                        // Left side of tooth (up to tip)
                        ctx.lineTo(Math.cos(toothTipStart) * outerRadius, Math.sin(toothTipStart) * outerRadius);
                        
                        // Tooth tip (flat)
                        ctx.lineTo(Math.cos(toothTipEnd) * outerRadius, Math.sin(toothTipEnd) * outerRadius);
                        
                        // Right side of tooth (down from tip)
                        ctx.lineTo(Math.cos(toothBaseEnd) * innerRadius, Math.sin(toothBaseEnd) * innerRadius);
                        
                        // Arc in gap to next tooth
                        const nextToothCenter = (Math.PI * 2 * ((i + 1) % teethCount)) / teethCount;
                        const nextGapStart = nextToothCenter - halfTooth;
                        ctx.arc(0, 0, innerRadius, toothBaseEnd, nextGapStart, false);
                    }
                    
                    ctx.closePath();
                    ctx.strokeStyle = gradient;
                    ctx.lineWidth = 3;
                    ctx.stroke();
                    
                    // Draw inner circle (hole)
                    ctx.beginPath();
                    const holeRadius = this.radius * 0.15;
                    ctx.arc(0, 0, holeRadius, 0, Math.PI * 2);
                    ctx.strokeStyle = gradient;
                    ctx.lineWidth = 2;
                    ctx.stroke();
                    
                    // Draw spokes with random offset
                    const spokes = Math.min(Math.floor(this.teeth / 2), 6);
                    for (let i = 0; i < spokes; i++) {
                        const angle = (Math.PI * 2 * i) / spokes + this.spokeOffset;
                        ctx.beginPath();
                        ctx.moveTo(Math.cos(angle) * holeRadius, Math.sin(angle) * holeRadius);
                        ctx.lineTo(Math.cos(angle) * innerRadius, Math.sin(angle) * innerRadius);
                        ctx.strokeStyle = gradient;
                        ctx.lineWidth = 2;
                        ctx.stroke();
                    }
                    
                    ctx.restore();
                }
            }
            
            let gears = [];

            function initGears() {
                gears = [];
                const width = gearsCanvas.width;
                const height = gearsCanvas.height;

                // Map gearsCount slider (0-2) to actual spacing multiplier
                // 0 = Few (more space between gears), 2 = Many (less space)
                const countMultiplier = 1.5 - (gearsCount * 0.35); // 1.5 to 0.8
                
                // Create gears with much more size contrast
                // Four distinct sizes: huge, large, medium, small
                const gearTypes = [
                    { radius: 100, teeth: 20, baseSpeed: 0.04 },   // Huge
                    { radius: 65, teeth: 14, baseSpeed: 0.06 },    // Large
                    { radius: 40, teeth: 10, baseSpeed: 0.1 },     // Medium
                    { radius: 25, teeth: 8, baseSpeed: 0.15 }      // Small
                ];

                // Base spacing is determined by gear types and count setting
                const baseSpacing = 140 * countMultiplier;
                const spacingX = baseSpacing;
                const spacingY = baseSpacing * 0.92;

                const cols = Math.ceil(width / spacingX) + 2;
                const rows = Math.ceil(height / spacingY) + 2;

                for (let row = -1; row < rows; row++) {
                    for (let col = -1; col < cols; col++) {
                        // Hexagonal packing with offset
                        const x = col * spacingX + (row % 2) * (spacingX / 2);
                        const y = row * spacingY;

                        // Select gear type based on position pattern
                        const typeIndex = ((row * 3 + col * 7) % gearTypes.length + gearTypes.length) % gearTypes.length;
                        const gearType = gearTypes[typeIndex];

                        // Alternate rotation direction for meshing
                        const direction = ((row + col) % 2 === 0) ? 1 : -1;
                        const speed = gearType.baseSpeed * direction * gearsSpeed;

                        // Phase offset for meshing teeth
                        const phase = ((row + col) % 2 === 0) ? 0 : Math.PI / gearType.teeth;

                        const gear = new Gear(
                            x, y,
                            gearType.radius,
                            gearType.teeth,
                            speed,
                            phase
                        );

                        gears.push(gear);
                    }
                }
            }
            
            let animationTime = 0;
            
            function animateGears() {
                animationTime += 0.016; // ~60fps increment
                
                gctx.fillStyle = getComputedStyle(document.body).backgroundColor;
                gctx.fillRect(0, 0, gearsCanvas.width, gearsCanvas.height);
                // Only draw gears if animation is enabled
                if (gearsEnabled) {
                    // Draw each gear
                    for (const gear of gears) {
                        // Only draw if within viewport (with margin)
                        if (gear.x > -150 && gear.x < gearsCanvas.width + 150 &&
                            gear.y > -150 && gear.y < gearsCanvas.height + 150) {
                            gear.draw(gctx, animationTime);
                        }
                    }
                }
                
                requestAnimationFrame(animateGears);
            }
            
            // Initialize and start animation
            window.addEventListener('resize', resizeGearsCanvas);
            resizeGearsCanvas();
            animateGears();
            
            // Expose initGears to global scope for settings updates
            window.initGears = initGears;
        })();
    </script>
