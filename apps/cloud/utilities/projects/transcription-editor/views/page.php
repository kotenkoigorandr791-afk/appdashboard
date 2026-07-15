<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['title']; ?></title>
    <script><?php echo file_get_contents(dirname(__DIR__, 4) . '/assets/audio-player.js'); ?></script>
    <script><?php echo file_get_contents(dirname(__DIR__, 4) . '/assets/file-load-progress.js'); ?></script>
    <style>
        :root {
            --accent: <?php echo $color; ?>;
            --accent-soft: color-mix(in srgb, var(--accent) 24%, transparent);
            --accent-soft-strong: color-mix(in srgb, var(--accent) 34%, transparent);
            --accent-border: color-mix(in srgb, var(--accent) 62%, transparent);
            --accent-text: color-mix(in srgb, var(--accent) 90%, white 10%);
            --bg: <?php echo $theme === 'dark' ? '#1a1a1a' : '#ffffff'; ?>;
            --fg: <?php echo $theme === 'dark' ? '#ffffff' : '#000000'; ?>;
            --panel: <?php echo $theme === 'dark' ? '#2a2a2a' : '#f5f5f5'; ?>;
            --border: <?php echo $theme === 'dark' ? '#444' : '#ddd'; ?>;
            --muted: <?php echo $theme === 'dark' ? '#888' : '#666'; ?>;
            --scrollbar-bg: <?php echo $theme === 'dark' ? '#2a2a2a' : '#f5f5f5'; ?>;
            --scrollbar-thumb: <?php echo $theme === 'dark' ? '#555' : '#ccc'; ?>;
            --scrollbar-thumb-hover: <?php echo $theme === 'dark' ? '#666' : '#aaa'; ?>;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: var(--bg);
            color: var(--fg);
            height: 100vh;
            overflow: hidden;
        }
        
        .editor-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: 20px;
            gap: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .toolbar-spacer {
            flex: 1 1 auto;
        }
        
        .language-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-right: 20px;
        }
        
        .language-group {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 8px;
        }
        
        .language-label {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: 1px solid var(--border);
            background: var(--panel);
            color: var(--fg);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn.primary {
            background: var(--accent-soft);
            color: var(--accent-text);
            border-color: var(--accent-border);
        }

        .btn.primary:hover {
            background: var(--accent-soft-strong);
        }

        .btn.danger {
            background: rgba(220, 53, 69, 0.58);
            color: white;
            border-color: rgba(220, 53, 69, 0.52);
        }

        .btn.danger:hover {
            background: rgba(220, 53, 69, 0.76);
            border-color: rgba(220, 53, 69, 0.68);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            flex: 1;
            min-height: 0;
        }
        
        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .audio-panel {
            /* Специальный класс для панели с аудио */
            overflow: hidden;
        }
        
        .panel h3 {
            margin-bottom: 15px;
            color: var(--accent);
        }
        
        .transcription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .transcription-header h3 {
            margin: 0;
        }

        #copy-btn {
            position: relative;
            overflow: hidden;
            padding: 4px 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            background: var(--accent-soft);
            color: var(--accent-text);
            border-color: var(--accent-border);
            box-shadow: none;
            transition: transform 0.18s ease, background 0.22s ease, border-color 0.22s ease, color 0.22s ease, box-shadow 0.22s ease;
        }

        #copy-btn:hover {
            background: var(--accent-soft-strong);
        }

        #copy-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 0%, color-mix(in srgb, var(--accent) 12%, white 88%) 35%, color-mix(in srgb, var(--accent) 28%, white 72%) 50%, transparent 65%);
            transform: translateX(-130%);
            opacity: 0;
            pointer-events: none;
        }

        #copy-btn.copy-success {
            background: var(--accent-soft-strong);
            color: var(--accent-text);
            border-color: var(--accent-border);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 18%, transparent);
            animation: copyButtonPop 0.34s ease;
        }

        #copy-btn.copy-success::after {
            opacity: 1;
            animation: copyButtonSweep 0.7s ease;
        }

        #copy-btn.copy-error {
            border-color: #dc3545;
            color: #dc3545;
            animation: copyButtonShake 0.34s ease;
        }

        @keyframes copyButtonPop {
            0% { transform: scale(1); }
            45% { transform: scale(1.08); }
            100% { transform: scale(1); }
        }

        @keyframes copyButtonSweep {
            0% { transform: translateX(-130%); }
            100% { transform: translateX(130%); }
        }

        @keyframes copyButtonShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
        }
        
        .audio-player-container {
            width: 100%;
            margin-bottom: 15px;
        }
        
        .transcription-area {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        textarea {
            flex: 1;
            background: var(--bg);
            color: var(--fg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 12px;
            font-family: monospace;
            resize: none;
            min-height: 200px;
            overflow-y: auto;
        }
        
        .progress-container {
            width: 132px;
            height: 28px;
            display: none;
            align-items: center;
            gap: 8px;
            padding: 4px 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--panel);
        }

        .progress-bar {
            width: 0%;
            height: 4px;
            background: var(--accent);
            border-radius: 999px;
            transition: width 0.3s;
        }

        .progress-track {
            flex: 1;
            height: 4px;
            overflow: hidden;
            border-radius: 999px;
            background: var(--border);
        }

        .progress-text {
            min-width: 32px;
            color: var(--fg);
            font-size: 12px;
            line-height: 1;
            text-align: right;
        }
        
        .status {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            display: none;
        }
        
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .file-info {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 10px;
            margin-top: 10px;
        }
        
        select {
            padding: 8px;
            background: var(--panel);
            color: var(--fg);
            border: 1px solid var(--border);
            border-radius: 4px;
        }
        
        .debug-info {
            font-size: 12px;
            color: var(--muted);
            margin-top: 5px;
        }
        
        .debug-overlay {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: var(--bg);
            border-top: 2px solid var(--accent-border);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
            max-height: 50vh;
            display: none;
            flex-direction: column;
            transition: transform 0.25s ease, opacity 0.25s ease;
            transform: translateY(0);
            opacity: 1;
        }
        
        .debug-overlay.open {
            display: flex;
        }

        .debug-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 14px;
            background: var(--panel);
            border-bottom: 1px solid var(--border);
            font-size: 12px;
            color: var(--muted);
            cursor: pointer;
            user-select: none;
        }

        .debug-close-btn {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            padding: 2px 6px;
            border-radius: 4px;
            transition: all 0.15s;
        }

        .debug-close-btn:hover {
            color: var(--fg);
            background: color-mix(in srgb, var(--border) 40%, transparent);
        }

        .debug-toggle-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--accent-border);
            background: var(--accent-soft);
            color: var(--accent-text);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
            flex: 0 0 auto;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
            opacity: 0;
            transform: scale(0.92);
            pointer-events: none;
        }

        .debug-toggle-btn.visible {
            opacity: 1;
            transform: scale(1);
            pointer-events: auto;
        }

        .debug-toggle-btn:hover {
            background: var(--accent-soft-strong);
            transform: translateY(-1px);
        }

        .debug-toggle-btn:active {
            transform: translateY(0);
        }

        .debug-toggle-btn.open {
            background: var(--accent-soft);
            border-color: var(--accent);
        }

        #debug-content {
            font-family: 'Courier New', monospace;
            line-height: 1.6;
            flex: 1;
            overflow-y: auto;
            padding: 10px 14px;
            max-height: calc(50vh - 40px);
        }

        #debug-content .log-entry {
            padding: 4px 0;
            border-bottom: 1px solid var(--border);
        }

        #debug-content .log-entry:last-child {
            border-bottom: none;
        }

        #debug-content .log-time {
            color: <?php echo $theme === 'dark' ? '#888' : '#666'; ?>;
            font-weight: bold;
        }

        #debug-content .log-separator {
            color: <?php echo $theme === 'dark' ? '#666' : '#999'; ?>;
        }

        #debug-content .log-message {
            color: var(--fg);
        }

        #debug-content .log-progress {
            color: var(--accent);
            font-weight: bold;
        }

        #debug-content .log-percentage {
            color: <?php echo $theme === 'dark' ? '#4CAF50' : '#2E7D32'; ?>;
            font-weight: bold;
        }

        #debug-content .log-json {
            color: <?php echo $theme === 'dark' ? '#9C27B0' : '#7B1FA2'; ?>;
            font-style: italic;
        }

        #debug-content .log-success {
            color: <?php echo $theme === 'dark' ? '#4CAF50' : '#2E7D32'; ?>;
            font-weight: bold;
        }

        #debug-content .log-error {
            color: <?php echo $theme === 'dark' ? '#f44336' : '#c62828'; ?>;
            font-weight: bold;
        }

        #debug-content .log-info {
            color: <?php echo $theme === 'dark' ? '#2196F3' : '#1565C0'; ?>;
        }

        #debug-content .log-key {
            color: <?php echo $theme === 'dark' ? '#FF9800' : '#E65100'; ?>;
        }
        
        /* Custom scrollbar styles for desktop */
        textarea::-webkit-scrollbar,
        #debug-content::-webkit-scrollbar {
            width: 8px;
        }
        
        textarea::-webkit-scrollbar-track,
        #debug-content::-webkit-scrollbar-track {
            background: var(--scrollbar-bg);
            border-radius: 4px;
        }
        
        textarea::-webkit-scrollbar-thumb,
        #debug-content::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 4px;
        }
        
        textarea::-webkit-scrollbar-thumb:hover,
        #debug-content::-webkit-scrollbar-thumb:hover {
            background: var(--scrollbar-thumb-hover);
        }
        
        /* Firefox scrollbar for desktop */
        textarea,
        #debug-content {
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-bg);
        }
        
        /* Mobile styles */
        @media (max-width: 768px) {
            .editor-container {
                padding: 10px;
                gap: 15px;
            }
            
            .header {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .controls {
                justify-content: center;
                gap: 8px;
            }
            
            .language-controls {
                margin-right: 0;
                justify-content: center;
            }
            
            .content {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr 1fr;
                gap: 15px;
            }
            
            .panel {
                padding: 15px;
                height: 100%;
                min-height: 0;
            }
            
            .audio-panel {
                /* На мобильных включаем прокрутку для всей панели аудио */
                overflow-y: auto;
            }
            
            .debug-overlay {
                max-height: 60vh;
            }
            
            #debug-content {
                max-height: calc(60vh - 40px);
            }
            
            .panel h3 {
                margin-bottom: 10px;
            }
            
            textarea {
                min-height: 150px;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 14px;
            }
            
            select {
                padding: 6px;
                font-size: 14px;
            }
            
            /* Скрываем скроллбары на мобильных устройствах */
            .audio-panel::-webkit-scrollbar,
            .panel::-webkit-scrollbar {
                display: none;
            }
            
            .audio-panel,
            .panel {
                -ms-overflow-style: none;  /* IE and Edge */
                scrollbar-width: none;  /* Firefox */
            }
        }
        
        /* Small mobile devices */
        @media (max-width: 480px) {
            .editor-container {
                padding: 8px;
                gap: 10px;
            }
            
            .panel {
                padding: 12px;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .language-controls {
                flex-direction: column;
                gap: 8px;
            }
            
            .language-group {
                flex-direction: row;
                justify-content: center;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            select {
                width: 100%;
            }
        }
    </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>" data-accent-color="<?php echo htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="editor-container">
        <div class="header">
            <h2><?php echo $t['title']; ?></h2>
            <div class="controls">
                <div class="language-controls">
                    <div class="language-group">
                        <div class="language-label"><?php echo $t['transcription_language']; ?></div>
                        <select id="transcription-language-select">
                            <option value="pl">Polish</option>
                            <option value="en">English</option>
                            <option value="ru">Russian</option>
                            <option value="uk">Ukrainian</option>
                            <option value="be">Belarusian</option>
                            <option value="de">German</option>
                            <option value="fr">French</option>
                            <option value="es">Spanish</option>
                        </select>
                    </div>
                </div>
                <div class="progress-container" id="progress-container">
                    <div class="progress-track"><div class="progress-bar" id="progress-bar"></div></div>
                    <span class="progress-text" id="progress-text">0%</span>
                </div>
                <button class="btn primary" id="btn-start"><?php echo $t['start_transcription']; ?></button>
                <button class="btn danger" id="btn-stop" style="display:none;"><?php echo $t['stop_transcription']; ?></button>
                <button id="btn-save" class="btn primary" style="display:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <span>Save</span>
                </button>
                <div class="toolbar-spacer"></div>
                <?php if ($canViewDebugLog): ?>
                <button class="debug-toggle-btn" id="debug-toggle-btn" title="<?php echo $t['debug_information']; ?>" aria-label="<?php echo $t['debug_information']; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="content">
            <div class="panel audio-panel">
                <h3><?php echo $t['audio_file']; ?></h3>
                <div class="file-info" id="file-info">
                    <?php if ($sourcePath && file_exists($sourcePath)): ?>
                        <div class="debug-info" style="color: green;"><?php echo $t['file_exists']; ?> (<?php echo formatFileSize(filesize($sourcePath)); ?>)</div>
                        <div class="debug-info">Format: <?php echo strtoupper(pathinfo($sourcePath, PATHINFO_EXTENSION)); ?> (<?php echo $audioMimeType; ?>)</div>
                    <?php else: ?>
                        <div class="debug-info" style="color: red;"><?php echo $t['file_not_found']; ?></div>
                    <?php endif; ?>
                </div>
                <div class="audio-player-container" id="audio-player-container" data-audio-url="<?php echo htmlspecialchars($audioStreamUrl, ENT_QUOTES, 'UTF-8'); ?>"></div>
                
                <div class="status" id="status"></div>
                
                <?php if ($canViewDebugLog): ?>
                <div class="debug-overlay" id="debug-overlay">
                    <div class="debug-header">
                        <strong><?php echo $t['debug_information']; ?></strong>
                        <button class="debug-close-btn" id="debug-close-btn" title="Close">✕</button>
                    </div>
                    <div id="debug-content"></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="panel">
                <div class="transcription-header">
                    <h3><?php echo $t['transcription']; ?></h3>
                    <button id="copy-btn" class="btn primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <span id="copy-btn-text">Copy</span>
                    </button>
                </div>
                <div class="transcription-area">
                    <textarea id="transcription-text" placeholder="<?php echo $t['transcription_placeholder']; ?>"></textarea>
                </div>
            </div>
        </div>
    </div>

    <script>
<?php require __DIR__ . '/../public/assets/js/page-script1.js.php'; ?>
</script>
</body>
</html>
