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

        .voice-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-right: 20px;
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

        .panel h3 {
            margin-bottom: 15px;
            color: var(--accent);
        }

        .text-area {
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
        *::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        *::-webkit-scrollbar-track {
            background: var(--scrollbar-bg);
            border-radius: 4px;
        }
        *::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 4px;
        }
        *::-webkit-scrollbar-thumb:hover {
            background: var(--scrollbar-thumb-hover);
        }
        * {
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-bg);
        }

        .audio-player-container {
            width: 100%;
            margin-top: 15px;
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

        .file-info {
            color: var(--muted);
            font-size: 14px;
            margin-top: 10px;
        }

        select {
            padding: 8px;
            background: var(--panel);
            color: var(--fg);
            border: 1px solid var(--border);
            border-radius: 4px;
        }

        label {
            font-size: 12px;
            color: var(--muted);
            margin-right: 5px;
        }

        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body data-theme="<?php echo $theme; ?>" data-accent-color="<?php echo htmlspecialchars(ltrim(urldecode((string)$color), '#%'), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="editor-container">
        <div class="header">
            <h2><?php echo $t['title']; ?></h2>
            <div class="controls">
                <div class="voice-controls">
                    <label><?php echo $t['select_language']; ?>:</label>
                    <select id="tts-language">
                        <option value="en">English</option>
                        <option value="ru">Русский</option>
                        <option value="uk">Українська</option>
                        <option value="pl">Polski</option>
                        <option value="de">Deutsch</option>
                        <option value="fr">Français</option>
                        <option value="es">Español</option>
                    </select>

                    <label><?php echo $t['voice_speed']; ?>:</label>
                    <select id="tts-speed">
                        <option value="0.75">0.75x</option>
                        <option value="1.0" selected>1.0x</option>
                        <option value="1.25">1.25x</option>
                        <option value="1.5">1.5x</option>
                    </select>
                </div>

                <div class="progress-container" id="progress-container">
                    <div class="progress-track"><div class="progress-bar" id="progress-bar"></div></div>
                    <span class="progress-text" id="progress-text">0%</span>
                </div>
                <button id="btn-generate" class="btn primary"><?php echo $t['generate_audio']; ?></button>
                <button id="btn-stop" class="btn danger" style="display: none;"><?php echo $t['stop_generation']; ?></button>
                <button id="btn-save-server" class="btn primary" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <span><?php echo $t['save']; ?></span>
                </button>
            </div>
        </div>

        <div class="status" id="status"></div>

        <div class="content">
            <div class="panel">
                <h3><?php echo $t['text_file']; ?></h3>
                <div class="text-area">
                    <textarea id="text-content" readonly><?php echo htmlspecialchars($textContent); ?></textarea>
                    <div class="file-info">
                        <?php if ($sourcePath): ?>
                            <?php echo $t['file_exists']; ?> (<?php echo strlen($textContent); ?> chars)
                        <?php else: ?>
                            <?php echo $t['file_not_found']; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h3><?php echo $t['generated_audio']; ?></h3>
                <div id="audio-container">
                    <p class="file-info"><?php echo $t['no_audio_yet']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
<?php require __DIR__ . '/../public/assets/js/page-script1.js.php'; ?>
</script>
</body>
</html>
