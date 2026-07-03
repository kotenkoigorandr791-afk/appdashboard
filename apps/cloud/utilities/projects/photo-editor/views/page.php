<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($t['title']); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($color); ?>;
            --bg-color: <?php echo $theme === 'dark' ? '#1f1f1f' : '#f8f9fa'; ?>;
            --text-color: <?php echo $theme === 'dark' ? '#e9ecef' : '#212529'; ?>;
            --panel-bg: <?php echo $theme === 'dark' ? '#2d2d2d' : '#ffffff'; ?>;
            --border-color: <?php echo $theme === 'dark' ? '#495057' : '#dee2e6'; ?>;
            --input-bg: <?php echo $theme === 'dark' ? '#383838' : '#ffffff'; ?>;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex; height: 100vh; background-color: var(--bg-color); color: var(--text-color); overflow: hidden;
        }

        #controls {
            width: 320px; background: var(--panel-bg); padding: 20px; overflow-y: auto; border-right: 1px solid var(--border-color);
        }
        
        <?php if ($theme === 'dark'): ?>
        #controls::-webkit-scrollbar,
        .emoji-accordion .emoji-content::-webkit-scrollbar {
            width: 8px;
        }
        #controls::-webkit-scrollbar-track,
        .emoji-accordion .emoji-content::-webkit-scrollbar-track {
            background: #2d2d2d;
        }
        #controls::-webkit-scrollbar-thumb,
        .emoji-accordion .emoji-content::-webkit-scrollbar-thumb {
            background-color: #555; border-radius: 4px; border: 2px solid #2d2d2d;
        }
        #controls::-webkit-scrollbar-thumb:hover,
        .emoji-accordion .emoji-content::-webkit-scrollbar-thumb:hover {
            background-color: #666;
        }
        <?php endif; ?>

        #canvas-container { 
            flex: 1; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 20px; 
            overflow: hidden;
            position: relative;
        }
        #canvas-box { 
            position: relative; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
            border-radius: 8px; 
            overflow: hidden; 
            background-color: <?php echo $theme === 'dark' ? '#2d2d2d' : '#ffffff'; ?>;
        }
        canvas { display: block; }
        .control-group { margin-bottom: 24px; }
        .control-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: <?php echo $theme === 'dark' ? '#adb5bd' : '#495057'; ?>; }
        .control-group input[type="text"], .control-group select, .control-group button {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px;
            background: var(--input-bg); color: var(--text-color); font-size: 14px; transition: all 0.2s ease;
        }
        .control-group button {
            background: var(--primary-color); color: white; border: none; cursor: pointer; display: flex;
            align-items: center; justify-content: center; gap: 8px; font-weight: 500;
        }
        .control-group button:hover { filter: brightness(1.1); }
        .control-group button.secondary {
            background: var(--border-color); color: var(--text-color);
        }
        .button-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .button-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
        .control-group input[type="range"] { width: 100%; accent-color: var(--primary-color); }
        .slider-label { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 14px; }
        
        .emoji-accordion .emoji-category { margin-bottom: 5px; }
        .emoji-accordion .emoji-header {
            background: <?php echo $theme === 'dark' ? '#3a3a3a' : '#f1f3f5'; ?>; padding: 8px 12px; border-radius: 4px; cursor: pointer;
            user-select: none; font-weight: 500; font-size: 14px; display: flex; justify-content: space-between; align-items: center;
        }
        .emoji-accordion .emoji-header::after { content: '›'; font-size: 20px; transition: transform 0.2s; }
        .emoji-accordion .emoji-header.active::after { transform: rotate(90deg); }
        .emoji-accordion .emoji-content {
            display: none; padding: 8px; max-height: 150px; overflow-y: auto;
            background: <?php echo $theme === 'dark' ? '#272727' : '#f8f9fa'; ?>; border-radius: 0 0 4px 4px;
        }
        .emoji { font-size: 24px; cursor: pointer; display: inline-block; margin: 4px; padding: 4px; border-radius: 4px; transition: background 0.2s; }
        .emoji:hover { background: <?php echo $theme === 'dark' ? '#444' : '#e9ecef'; ?>; }
        
        /* Modal styles */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); 
            display: flex; justify-content: center; align-items: center; z-index: 1000;
        }
        .modal {
            background: var(--panel-bg); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 90%; max-width: 400px; overflow: hidden;
        }
        .modal-header {
            padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; 
            justify-content: space-between; align-items: center;
        }
        .modal-title { font-weight: 600; font-size: 18px; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-color); }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; }
        .modal-btn { 
            padding: 8px 16px; border-radius: 4px; border: none; cursor: pointer; font-weight: 500; 
            margin-left: 10px; 
        }
        .modal-btn-primary { background: var(--primary-color); color: white; }
        .modal-btn-secondary { background: var(--border-color); color: var(--text-color); }
        
        /* Context Menu */
        .context-menu {
            position: absolute; background: var(--panel-bg); border: 1px solid var(--border-color); 
            border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; min-width: 180px;
            display: none;
        }
        .context-menu-item {
            padding: 10px 15px; cursor: pointer; font-size: 14px; border-bottom: 1px solid var(--border-color);
            transition: background 0.2s;
        }
        .context-menu-item:last-child { border-bottom: none; }
        .context-menu-item:hover { background: <?php echo $theme === 'dark' ? '#3a3a3a' : '#f1f3f5'; ?>; }
        
        /* Number input styles */
        .slider-with-input { display: flex; gap: 10px; align-items: center; }
        .slider-with-input input[type="range"] { flex: 1; }
        .slider-with-input input[type="number"] { 
            width: 70px; padding: 6px; border: 1px solid var(--border-color); border-radius: 4px;
            background: var(--input-bg); color: var(--text-color);
        }
        
        /* Zoom controls */
        .zoom-controls {
            position: absolute; top: 20px; right: 20px; display: flex; flex-direction: column; gap: 5px;
            background: var(--panel-bg); padding: 10px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 100;
        }
        .zoom-controls button {
            width: 40px; height: 40px; border-radius: 4px; background: var(--primary-color); color: white;
            border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
        .zoom-controls button:hover { filter: brightness(1.1); }
        .zoom-display {
            text-align: center; font-size: 12px; padding: 5px; color: var(--text-color);
        }
        
        /* Pan mode indicator */
        .pan-indicator {
            position: absolute; top: 20px; left: 50%; transform: translateX(-50%); 
            background: var(--primary-color); color: white; padding: 6px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 500; display: none; z-index: 100;
        }
        
        .photo-nav-buttons {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 200;
            pointer-events: none;
        }
        .photo-nav-buttons.is-hidden { display: none !important; }
        .photo-nav-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: color-mix(in srgb, var(--panel-bg) 70%, transparent);
            border: 2px solid var(--border-color);
            color: var(--text-color);
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .photo-nav-btn:hover { filter: brightness(1.08); }
        .photo-nav-btn:disabled { opacity: 0.35; cursor: not-allowed; pointer-events: none; }
        @media (max-width: 768px) {
            body { flex-direction: column; } #controls { width: 100%; max-height: 40vh; } #canvas-container { padding: 10px; }
            .zoom-controls { top: 10px; right: 10px; }
        }
    </style>
    <script><?php echo file_get_contents(dirname(__DIR__, 4) . '/assets/file-load-progress.js'); ?></script>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>"
      data-accent-color="<?php echo htmlspecialchars($color); ?>"
      data-current-index="<?php echo (int)$currentIndex; ?>"
      data-images='<?php echo htmlspecialchars(json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
      data-folder="<?php echo htmlspecialchars($folder); ?>"
      data-full-path="<?php echo htmlspecialchars($path); ?>"
      data-api-url="<?php echo htmlspecialchars($apiUrl); ?>"
      data-root="<?php echo htmlspecialchars($root); ?>"
      data-user="<?php echo htmlspecialchars($currentUser); ?>"
      data-lang="<?php echo htmlspecialchars($lang); ?>">
    <div id="controls">
        <div class="control-group">
            <div class="button-grid-3">
                <button id="zoom-in-btn" title="<?php echo htmlspecialchars($t['zoom_in']); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                </button>
                <button id="reset-view-btn" title="<?php echo htmlspecialchars($t['reset_view']); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                </button>
                <button id="zoom-out-btn" title="<?php echo htmlspecialchars($t['zoom_out']); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                </button>
            </div>
        </div>

        <div class="control-group">
            <div class="button-grid">
                <button id="pan-btn" class="secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 3v18H3"/><path d="M7 15l5 5 5-5"/></svg>
                    <span id="pan-text"><?php echo htmlspecialchars($t['pan_mode']); ?></span>
                </button>
                <button id="crop-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6.13 1L6 16a2 2 0 0 0 2 2h15"></path><path d="M1 6.13L16 6a2 2 0 0 1 2 2v15"></path></svg>
                    <span id="crop-text"><?php echo htmlspecialchars($t['crop']); ?></span>
                </button>
            </div>
        </div>

        <div class="control-group">
            <div class="button-grid">
                <button id="rotate-left">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 2v6h6M2.66 15.57a10 10 0 1 0 .57-8.38"/></svg>
                    <?php echo htmlspecialchars($t['rotate_left']); ?>
                </button>
                <button id="rotate-right">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38"/></svg>
                    <?php echo htmlspecialchars($t['rotate_right']); ?>
                </button>
            </div>
        </div>
        
        <div class="control-group">
            <div class="button-grid">
                <button id="draw-btn">
                     <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    <span id="draw-text"><?php echo htmlspecialchars($t['draw']); ?></span>
                </button>
                <button id="undo-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-undo-icon lucide-undo"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                    <?php echo htmlspecialchars($t['undo']); ?>
                </button>
            </div>
        </div>
        
        <!-- Единые настройки цвета и толщины -->
        <div id="color-width-settings">
            <div class="control-group">
                <label for="drawing-color"><?php echo htmlspecialchars($t['color']); ?></label>
                <input type="color" id="drawing-color" value="<?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>" style="width:100%; height: 40px; padding: 4px; border: 1px solid var(--border-color); border-radius: 6px;">
            </div>
             <div class="control-group">
                <label class="slider-label" for="line-width"><?php echo htmlspecialchars($t['line_width']); ?>: <span id="line-width-val">3</span></label>
                <input type="range" id="line-width" min="1" max="20" value="3">
            </div>
        </div>

        <div class="control-group">
            <label><?php echo htmlspecialchars($t['shapes']); ?></label>
            <div class="button-grid">
                <button id="line-btn" data-shape="line">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line></svg>
                    <?php echo htmlspecialchars($t['line']); ?>
                </button>
                <button id="rect-btn" data-shape="rectangle">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect></svg>
                    <?php echo htmlspecialchars($t['rectangle']); ?>
                </button>
                <button id="circle-btn" data-shape="circle">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>
                    <?php echo htmlspecialchars($t['circle']); ?>
                </button>
                <button id="arrow-btn" data-shape="arrow">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    <?php echo htmlspecialchars($t['arrow']); ?>
                </button>
            </div>
        </div>

        <div class="control-group">
            <label for="text-input"><?php echo htmlspecialchars($t['add_text']); ?></label>
            <input type="text" id="text-input" placeholder="<?php echo htmlspecialchars($t['text_placeholder']); ?>">
            <button id="add-text" style="margin-top: 10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>
                <?php echo htmlspecialchars($t['add_text']); ?>
            </button>
        </div>

        <div class="control-group">
            <label><?php echo htmlspecialchars($t['emojis']); ?></label>
            <div id="emoji-accordion" class="emoji-accordion"></div>
        </div>

        <div class="control-group">
            <label for="filter-select"><?php echo htmlspecialchars($t['filters']); ?></label>
            <select id="filter-select">
                <option value=""><?php echo htmlspecialchars($t['no_filter']); ?></option>
                <option value="grayscale">Grayscale</option> <option value="sepia">Sepia</option> <option value="invert">Invert</option>
                <option value="brownie">Brownie</option> <option value="vintage">Vintage</option> <option value="kodachrome">Kodachrome</option>
                <option value="technicolor">Technicolor</option> <option value="polaroid">Polaroid</option> <option value="blackwhite">Black & White</option>
            </select>
        </div>

        <div class="control-group">
            <label class="slider-label" for="brightness"><?php echo htmlspecialchars($t['brightness']); ?>:</label>
            <div class="slider-with-input">
                <input type="range" id="brightness" min="-100" max="100" value="0">
                <input type="number" id="brightness-input" min="-100" max="100" value="0" step="1">
            </div>
            <label class="slider-label" for="contrast"><?php echo htmlspecialchars($t['contrast']); ?>:</label>
            <div class="slider-with-input">
                <input type="range" id="contrast" min="-100" max="100" value="0">
                <input type="number" id="contrast-input" min="-100" max="100" value="0" step="1">
            </div>
            <label class="slider-label" for="saturation"><?php echo htmlspecialchars($t['saturation']); ?>:</label>
            <div class="slider-with-input">
                <input type="range" id="saturation" min="-100" max="100" value="0">
                <input type="number" id="saturation-input" min="-100" max="100" value="0" step="1">
            </div>
            <label class="slider-label" for="hue"><?php echo htmlspecialchars($t['hue']); ?>:</label>
            <div class="slider-with-input">
                <input type="range" id="hue" min="-180" max="180" value="0">
                <input type="number" id="hue-input" min="-180" max="180" value="0" step="1">
            </div>
            <label class="slider-label" for="blur"><?php echo htmlspecialchars($t['blur']); ?>:</label>
            <div class="slider-with-input">
                <input type="range" id="blur" min="0" max="1" step="0.01" value="0">
                <input type="number" id="blur-input" min="0" max="1" step="0.01" value="0">
            </div>
        </div>

        <div class="control-group">
            <button id="save-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                <?php echo htmlspecialchars($t['save']); ?>
            </button>
        </div>
    </div>

    <div id="canvas-container">
        <div class="photo-nav-buttons<?php echo count($images) <= 1 ? ' is-hidden' : ''; ?>">
            <button type="button" class="photo-nav-btn photo-prev-btn" id="photo-prev-btn" aria-label="Previous"<?php echo $currentIndex <= 0 ? ' disabled' : ''; ?>>←</button>
            <button type="button" class="photo-nav-btn photo-next-btn" id="photo-next-btn" aria-label="Next"<?php echo $currentIndex >= count($images) - 1 ? ' disabled' : ''; ?>>→</button>
        </div>
        <div id="canvas-box">
            <canvas id="canvas"></canvas>
        </div>
        <div class="zoom-controls">
            <button id="zoom-in-btn-top">+</button>
            <div class="zoom-display" id="zoom-display">100%</div>
            <button id="zoom-out-btn-top">-</button>
        </div>
        <div class="pan-indicator" id="pan-indicator"><?php echo htmlspecialchars($t['pan_mode']); ?></div>
    </div>

    <!-- Context Menu -->
    <div id="context-menu" class="context-menu">
        <div class="context-menu-item" id="context-delete"><?php echo htmlspecialchars($t['delete']); ?></div>
        <div class="context-menu-item" id="context-change-color"><?php echo htmlspecialchars($t['change_color']); ?></div>
        <div class="context-menu-item" id="context-change-text" style="display:none;"><?php echo htmlspecialchars($t['change_text']); ?></div>
        <div class="context-menu-item" id="context-bring-front"><?php echo htmlspecialchars($t['bring_to_front']); ?></div>
        <div class="context-menu-item" id="context-send-back"><?php echo htmlspecialchars($t['send_to_back']); ?></div>
    </div>

    <!-- Modal for messages -->
    <div id="modal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title" id="modal-title"><?php echo htmlspecialchars($t['success']); ?></div>
                <button class="modal-close" id="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="modal-body">
                <?php echo htmlspecialchars($t['image_saved']); ?>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-primary" id="modal-ok"><?php echo htmlspecialchars($t['ok']); ?></button>
            </div>
        </div>
    </div>

    <script>
<?php require __DIR__ . '/../public/assets/js/page-script1.js.php'; ?>
</script>
</body>
</html>
