<!DOCTYPE html>
<html lang="<?= $lang ?>" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes">
    <title><?= htmlspecialchars($archiveName) ?> — <?= $i18n['title'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.7.8/plyr.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.7.8/plyr.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/editor/editor.main.min.css">
    <script src="<?= htmlspecialchars(cloud_url("assets")) ?>/monaco-shared.js?v=2"></script>
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        :root {
            --bg: #1e1e1e;
            --fg: #d4d4d4;
            --panel: #252526;
            --border: #3c3c3c;
            --accent: #007acc;
            --hover: #2a2d2e;
            --selected: #005f99;
            --folder-color: <?= $folderColor ?>;
            --header-bg: #2d2d2d;
            --plyr-color-main: <?= $folderColor ?>;
            --header-height: 36px;
            --preview-header-height: 36px;
        }
        [data-theme="light"] {
            --bg: #f3f3f3;
            --fg: #1e1e1e;
            --panel: #ffffff;
            --border: #d4d4d4;
            --accent: #007acc;
            --hover: #e0e0e0;
            --selected: #cce8ff;
            --folder-color: <?= $folderColor ?>;
            --header-bg: #ffffff;
            --plyr-color-main: <?= $folderColor ?>;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        .fa-solid::before, .fa-regular::before { content: "•"; }
        .fa-arrow-left::before { content: "←"; }
        .fa-arrow-up::before { content: "↑"; }
        .fa-home::before { content: "⌂"; }
        .fa-file-zipper::before { content: "▣"; }
        .fa-cubes::before { content: "▦"; }
        .fa-lock-open::before { content: "⌕"; }
        .fa-eye::before { content: "◉"; }
        .fa-eye-slash::before { content: "◎"; }
        .fa-circle-exclamation::before { content: "!"; }
        .fa-folder-open::before { content: "□"; }
        .fa-folder::before { content: "▸"; }
        .fa-music::before { content: "♪"; }
        .fa-video::before { content: "▶"; }
        .fa-image::before { content: "▧"; }
        .fa-file-pdf::before { content: "PDF"; font-size: 10px; }
        .fa-file-word::before, .fa-file-lines::before { content: "DOC"; font-size: 10px; }
        .fa-file::before { content: "□"; }
        .fa-chevron-left::before { content: "‹"; }
        .fa-chevron-right::before { content: "›"; }
        .fa-list::before { content: "☰"; }
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif; background: var(--bg); color: var(--fg); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        header { 
            background: var(--panel); 
            border-bottom: 1px solid var(--border); 
            padding: 4px 12px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            font-size: 13px; 
            flex-shrink: 0; 
            height: var(--header-height);
        }
        
        .btn { 
            background: var(--panel); 
            border: 1px solid var(--border); 
            color: var(--fg); 
            padding: 4px 10px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 12px; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            text-decoration: none; 
            transition: all 0.2s; 
            white-space: nowrap; 
        }
        
        .btn:hover { 
            background: var(--hover); 
            border-color: var(--accent); 
        }
        
        .btn i { 
            font-size: 12px; 
        }
        
        .breadcrumbs { 
            flex: 1; 
            display: flex; 
            align-items: center; 
            gap: 4px; 
            background: var(--bg); 
            border: 1px solid var(--border); 
            border-radius: 4px; 
            padding: 2px 6px; 
            min-width: 200px; 
            overflow-x: auto; 
            white-space: nowrap; 
            scrollbar-width: thin; 
            height: 28px;
        }
        
        .breadcrumb-item { 
            display: inline-flex; 
            align-items: center; 
            color: var(--fg); 
            text-decoration: none; 
            padding: 2px 4px; 
            border-radius: 3px; 
            font-size: 12px; 
        }
        
        .breadcrumb-item:hover { 
            background: var(--hover); 
        }
        
        .breadcrumb-separator { 
            color: #888; 
            margin: 0 2px; 
        }
        
        .archive-info { 
            display: flex; 
            align-items: center; 
            gap: 6px; 
            padding: 0 6px; 
            color: #888; 
            border-left: 1px solid var(--border); 
            margin-left: 4px; 
            font-size: 12px;
            height: 26px;
        }
        
        .container { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
            min-height: 0; 
            height: calc(100vh - var(--header-height)); 
            position: relative; 
        }
        
        .file-list-container { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
            min-height: 0; 
            transition: all 0.3s ease; 
        }
        
        .has-preview .file-list-container { 
            display: none; 
        }
        
        .file-list-header { 
            display: flex; 
            padding: 6px 16px; 
            background: var(--header-bg); 
            border-bottom: 2px solid var(--border); 
            font-weight: 600; 
            font-size: 12px; 
            align-items: center; 
            flex-shrink: 0; 
        }
        
        .header-name { 
            flex: 1; 
            padding-left: 36px; 
        }
        
        .header-size { 
            width: 80px; 
            text-align: right; 
            padding: 0 10px; 
        }
        
        .header-date { 
            width: 160px; 
            text-align: right; 
        }
        
        .file-list { 
            flex: 1; 
            overflow-y: auto; 
            padding: 0; 
            margin: 0; 
            min-height: 0; 
        }
        
        .file-item { 
            display: flex; 
            align-items: center; 
            padding: 8px 16px; 
            cursor: pointer; 
            user-select: none; 
            border-bottom: 1px solid var(--border); 
            transition: background 0.15s; 
        }
        
        .file-item:hover { 
            background: var(--hover); 
        }
        
        .file-item.selected { 
            background: var(--selected); 
            color: white; 
        }
        
        .icon { 
            width: 24px; 
            text-align: center; 
            margin-right: 12px; 
            font-size: 16px; 
            flex-shrink: 0; 
        }
        
        .file-name { 
            flex: 1; 
            min-width: 0; 
            overflow: hidden; 
            text-overflow: ellipsis; 
            white-space: nowrap; 
            font-size: 13px; 
        }
        
        .file-size { 
            width: 80px; 
            text-align: right; 
            padding: 0 10px; 
            color: #888; 
            font-size: 12px; 
            flex-shrink: 0; 
        }
        
        .file-date { 
            width: 160px; 
            text-align: right; 
            color: #888; 
            font-size: 12px; 
            flex-shrink: 0; 
        }
        
        .preview { 
            position: fixed; 
            top: var(--header-height); 
            left: 0; 
            right: 0; 
            bottom: 0; 
            display: flex; 
            flex-direction: column; 
            background: var(--panel); 
            border-top: 1px solid var(--border); 
            z-index: 1000; 
        }
        
        .preview-content { 
            flex: 1; 
            min-height: 0; 
            height: calc(100vh - var(--header-height)); 
            padding: 0 !important; 
            margin: 0 !important; 
            display: flex; 
            flex-direction: column; 
            background: var(--bg); 
            position: relative; 
        }
        
        .image-container { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            width: 100%; 
            height: 100%; 
            background: var(--bg); 
            padding: 0; 
            margin: 0; 
        }
        
        .image-container img { 
            max-width: 100%; 
            max-height: 100%; 
            width: auto; 
            height: auto; 
            object-fit: contain; 
            cursor: pointer; 
            display: block; 
            padding: 0; 
            margin: 0; 
            border: none; 
        }
        
        .preview-content video { 
            width: 100%; 
            height: 100%; 
            max-height: 100%; 
            padding: 0; 
            margin: 0; 
            display: block; 
            background: black; 
            object-fit: contain; 
        }
        
        .audio-player-container { 
            display: flex; 
            flex-direction: column; 
            width: 100%; 
            height: 100%; 
            padding: 0; 
            margin: 0; 
        }
        
        .custom-audio-player { 
            background: var(--bg); 
            padding: 12px 16px; 
            margin: 0; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            flex-shrink: 0; 
            border-bottom: 1px solid var(--border); 
        }
        
        .custom-audio-player audio { 
            width: 100%; 
        }
        
        #monaco-container { 
            width: 100%; 
            height: 100% !important; 
            min-height: 0; 
            border: none; 
            outline: none;
            box-shadow: none;
            border-radius: 0; 
            padding: 0; 
            margin: 0; 
        }
        #monaco-container:focus,
        #monaco-container:focus-within,
        .monaco-editor,
        .monaco-editor.focused,
        .monaco-editor .overflow-guard,
        .monaco-editor .inputarea,
        .monaco-editor textarea {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }
        
        .pdf-container { 
            display: flex; 
            flex-direction: column; 
            width: 100%; 
            height: 100%; 
            overflow: hidden; 
            background: var(--bg); 
        }
        
        .pdf-toolbar { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            padding: 6px 12px; 
            background: var(--header-bg); 
            border-bottom: 1px solid var(--border); 
            flex-wrap: wrap; 
            height: 36px;
            font-size: 12px;
        }
        
        .pdf-toolbar button { 
            background: var(--panel); 
            border: 1px solid var(--border); 
            color: var(--fg); 
            padding: 4px 8px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 11px; 
            display: inline-flex; 
            align-items: center; 
            gap: 4px; 
        }
        
        .pdf-toolbar button:hover { 
            background: var(--hover); 
        }
        
        .pdf-toolbar button:disabled { 
            opacity: 0.5; 
            cursor: not-allowed; 
        }
        
        .pdf-toolbar span { 
            color: var(--fg); 
            font-size: 12px; 
        }
        
        .pdf-viewer { 
            flex: 1; 
            overflow: auto; 
            padding: 16px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            background: var(--bg); 
        }
        
        .pdf-page { 
            margin-bottom: 16px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.3); 
            background: white; 
        }
        
        .pdf-page canvas { 
            display: block; 
            width: 100%; 
            height: auto; 
        }
        
        .playlist { 
            background: var(--bg); 
            padding: 10px; 
            margin: 0; 
            flex: 1; 
            min-height: 0; 
            overflow-y: auto; 
            border-top: 1px solid var(--border); 
        }
        
        .playlist-item { 
            display: flex; 
            align-items: center; 
            padding: 8px 12px; 
            border-radius: 4px; 
            cursor: pointer; 
            gap: 10px; 
            font-size: 12px;
        }
        
        .playlist-item:hover { 
            background: var(--hover); 
        }
        
        .playlist-item.active { 
            background: var(--selected); 
            color: white; 
        }
        
        .playlist-item i { 
            color: <?= $folderColor ?>; 
        }
        
        .plyr { 
            width: 100%; 
            height: 100%; 
            max-height: 100%; 
        }
        
        .plyr--video { 
            height: 100%; 
        }
        
        .empty-folder { 
            padding: 60px 20px; 
            text-align: center; 
            color: #888; 
            font-size: 14px; 
        }
        
        .office-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px;
            background: <?= $folderColor ?>;
            color: <?= $theme === 'light' ? '#000' : '#fff' ?>;
            border-radius: 10px;
            font-size: 9px;
            margin-left: 6px;
        }
        
        .zip-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px;
            background: #e67e22;
            color: white;
            border-radius: 10px;
            font-size: 9px;
            margin-left: 6px;
        }
        
        .archive-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px;
            background: #9b59b6;
            color: white;
            border-radius: 10px;
            font-size: 9px;
            margin-left: 6px;
        }
        
        @media (max-width: 768px) {
            header { padding: 4px 6px; }
            .btn span { display: none; }
            .btn i { font-size: 14px; }
            .btn { padding: 6px 10px; }
            .file-date, .header-date { display: none; }
            .file-size, .header-size { width: 60px; }
            .file-name, .header-name { padding-left: 0; }
            .preview { top: var(--header-height); }
            .custom-audio-player { flex-direction: column; align-items: stretch; }
            .breadcrumbs { max-width: 100%; }
            .archive-info { display: none; }
            .header-size { width: 50px; }
        }
        /* Scrollbar styles in the dark theme */
        [data-theme="dark"] *::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        [data-theme="dark"] *::-webkit-scrollbar-track {
            background: #2d2d2d; /* dark track background */
            border-radius: 5px;
        }

        [data-theme="dark"] *::-webkit-scrollbar-thumb {
            background: #555; /* dark thumb */
            border-radius: 5px;
            border: 2px solid #2d2d2d;
        }

        [data-theme="dark"] *::-webkit-scrollbar-thumb:hover {
            background: #666; /* slightly lighter on hover */
        }

        [data-theme="dark"] *::-webkit-scrollbar-corner {
            background: #2d2d2d;
        }

        /* For Firefox in the dark theme */
        [data-theme="dark"] * {
            scrollbar-width: thin;
            scrollbar-color: #555 #2d2d2d;
        }
        .app-modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0, 0, 0, 0.55);
            z-index: 5000;
        }
        .app-modal-overlay.visible {
            display: flex;
        }
        .app-modal-dialog {
            width: min(420px, calc(100vw - 32px));
            background: var(--panel);
            color: var(--fg);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 18px 48px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        .app-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
        }
        .app-modal-title {
            font-size: 15px;
            font-weight: 600;
        }
        .app-modal-close {
            background: transparent;
            border: none;
            color: var(--fg);
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }
        .app-modal-body {
            padding: 18px 16px 10px;
            line-height: 1.5;
        }
        .app-modal-footer {
            display: flex;
            justify-content: flex-end;
            padding: 0 16px 16px;
        }
        .password-field {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .password-field input {
            min-width: 0;
        }
        .password-toggle {
            width: 30px;
            height: 30px;
            justify-content: center;
            padding: 0;
        }
    </style>
</head>
<body>

<header>
    <div class="btn-group">
        <?php if ($viewFile): ?>
            <a href="<?= buildUrl(['view' => null, 'path' => $currentPath]) ?>" class="btn">
                <i class="fa-solid fa-arrow-left"></i>
                <span><?= $i18n['back'] ?></span>
            </a>
            <a href="<?= buildUrl(['download_from_zip' => '1', 'file' => urlencode($viewFile)]) ?>" class="btn" title="<?= htmlspecialchars($i18n['download_file']) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </a>
        <?php elseif (count($archiveChain) > 1): ?>
            <!-- Return to the parent archive -->
            <?php 
            $parentArchive = $archiveChain[count($archiveChain) - 2];
            $parentChain = array_slice($archiveChain, 0, -1);
            ?>
            <a href="?archive=<?= urlencode($parentArchive['path']) ?>&parent=<?= urlencode(json_encode(array_slice($parentChain, 0, -1))) ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&folderColor=<?= urlencode($folderColor) ?>&path=<?= urlencode($parentArchive['internal_path'] ?? '') ?>" class="btn">
                <i class="fa-solid fa-arrow-up"></i>
                <span><?= $i18n['back'] ?> (<?= basename($parentArchive['path']) ?>)</span>
            </a>
        <?php elseif ($currentPath !== ''): ?>
            <a href="<?= buildUrl(['path' => getParentPath($currentPath), 'view' => null]) ?>" class="btn">
                <i class="fa-solid fa-arrow-up"></i>
                <span><?= $i18n['back'] ?></span>
            </a>
            <a href="<?= buildUrl(['path' => null, 'view' => null]) ?>" class="btn">
                <i class="fa-solid fa-home"></i>
                <span><?= $i18n['root'] ?></span>
            </a>
        <?php endif; ?>
    </div>
    
    <div class="breadcrumbs">
        <!-- Display the archive chain -->
        <?php foreach ($archiveChain as $index => $arch): ?>
            <?php if ($index > 0): ?>
                <span class="breadcrumb-separator">:</span>
            <?php endif; ?>
            <?php if ($index < count($archiveChain) - 1): ?>
                <a href="?archive=<?= urlencode($arch['path']) ?>&parent=<?= urlencode(json_encode(array_slice($archiveChain, 0, $index))) ?>&lang=<?= $lang ?>&theme=<?= $theme ?>&folderColor=<?= urlencode($folderColor) ?>&path=<?= urlencode($arch['internal_path'] ?? '') ?>" class="breadcrumb-item">
                    <i class="fa-solid fa-file-zipper" style="color:<?= $folderColor ?>; margin-right:2px;"></i>
                    <?= htmlspecialchars(basename($arch['name'])) ?>
                </a>
            <?php else: ?>
                <span class="breadcrumb-item" style="color: var(--folder-color);">
                    <i class="fa-solid fa-file-zipper" style="color:<?= $folderColor ?>; margin-right:2px;"></i>
                    <?= htmlspecialchars(basename($arch['name'])) ?>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Display the inner path in the current archive -->
        <?php foreach ($breadcrumbs as $crumb): ?>
            <span class="breadcrumb-separator">/</span>
            <a href="<?= buildUrl(['path' => $crumb['path'], 'view' => null]) ?>" class="breadcrumb-item">
                <?= htmlspecialchars($crumb['name']) ?>
            </a>
        <?php endforeach; ?>
        
        <?php if ($viewFile): ?>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-item" style="color: var(--folder-color);">
                <?= htmlspecialchars(basename($viewFile)) ?>
            </span>
        <?php endif; ?>
    </div>
    
    <div class="archive-info">
        <i class="fa-solid fa-cubes"></i>
        <span><?= count($files) ?> <?= $i18n['files'] ?></span>
    </div>
    <?php if ($archiveNeedsPassword): ?>
        <form method="post" class="password-field">
            <input class="archive-password-input" type="password" name="archive_password" value="<?= htmlspecialchars($archivePassword) ?>" autocomplete="current-password" placeholder="Password" style="width:130px;padding:5px 8px;border:1px solid var(--border);background:var(--bg);color:var(--fg);border-radius:4px;font-size:12px;">
            <button class="btn password-toggle" type="button" title="Show password"><i class="fa-solid fa-eye"></i></button>
            <button class="btn" type="submit"><i class="fa-solid fa-lock-open"></i></button>
        </form>
    <?php endif; ?>
</header>

<div id="app-modal" class="app-modal-overlay" aria-hidden="true">
    <div class="app-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="app-modal-title">
        <div class="app-modal-header">
            <div id="app-modal-title" class="app-modal-title"></div>
            <button type="button" class="app-modal-close" id="app-modal-close" aria-label="<?= htmlspecialchars($i18n['close']) ?>">&times;</button>
        </div>
        <div id="app-modal-message" class="app-modal-body"></div>
        <div class="app-modal-footer">
            <button type="button" class="btn" id="app-modal-ok"><?= htmlspecialchars($i18n['close']) ?></button>
        </div>
    </div>
</div>

<div class="container <?= $viewFile ? 'has-preview' : '' ?>">
    <div class="file-list-container">
        <div class="file-list-header">
            <div class="header-name">
                <i class="fa-solid fa-folder" style="color:var(--folder-color); margin-right:8px;"></i>
                <?= $i18n['name'] ?>
            </div>
            <div class="header-size"><?= $i18n['size'] ?></div>
            <div class="header-date"><?= $i18n['date'] ?></div>
        </div>
        
        <div class="file-list" id="fileList">
            <?php if ($error): ?>
                <div style="padding:60px 20px; text-align:center; color:#e74c3c; font-size:16px;">
                    <i class="fa-solid fa-circle-exclamation" style="margin-right:10px;"></i>
                    <?= htmlspecialchars($error) ?>
                    <?php if ($archiveNeedsPassword): ?>
                        <form method="post" class="password-field" style="max-width:360px;margin:18px auto 0;">
                            <input class="archive-password-input" type="password" name="archive_password" autocomplete="current-password" placeholder="Password" style="flex:1;padding:8px;border:1px solid var(--border);background:var(--panel);color:var(--fg);border-radius:4px;">
                            <button class="btn password-toggle" type="button" title="Show password"><i class="fa-solid fa-eye"></i></button>
                            <button class="btn" type="submit">Open</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php elseif (empty($files)): ?>
                <div class="empty-folder">
                    <i class="fa-solid fa-folder-open" style="font-size: 48px; margin-bottom: 16px; color: #888;"></i>
                    <p><?= $i18n['folder_empty'] ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($files as $item): ?>
                    <?php 
                    if ($item['type'] === 'folder') {
                        $url = buildUrl([
                            'path' => $item['path'],
                            'view' => null
                        ]);
                    } else {
                        $url = buildUrl([
                            'view' => $item['path'],
                            'path' => $currentPath
                        ]);
                    }
                    
                    $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                    $isOffice = in_array($ext, ['doc', 'docx', 'odt', 'rtf', 'txt', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp']);
                    $isZip = in_array($ext, ['zip', '7z', 'rar'], true) || str_ends_with(strtolower($item['name']), '.tar.gz') || str_ends_with(strtolower($item['name']), '.tgz');
                    ?>
                    <div class="file-item <?= ($viewFile === $item['path'] || (!$viewFile && $currentPath === $item['path'])) ? 'selected' : '' ?>"
                         data-type="<?= $item['type'] ?>"
                         data-path="<?= htmlspecialchars($item['path']) ?>"
                         onclick="window.location.href='<?= $url ?>'">
                        <span class="icon">
                            <?php if ($item['type'] === 'folder'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="<?= $folderColor ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
                            <?php else: ?>
                                <img src="../../../../icons/<?= rawurlencode($ext ?: 'txt') ?>.svg" alt="" width="20" height="20" onerror="this.onerror=null;this.src='../../../../icons/txt.svg';">
                            <?php endif; ?>
                        </span>
                        <span class="file-name" title="<?= htmlspecialchars($item['name']) ?>">
                            <?= htmlspecialchars($item['name']) ?>
                            <?php if ($isOffice): ?>
                                <span class="office-badge">
                                    <?= strtoupper($ext) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($isZip): ?>
                                <span class="zip-badge">
                                    ZIP
                                </span>
                            <?php endif; ?>
                        </span>
                        <span class="file-size"><?= $item['size'] ?></span>
                        <span class="file-date"><?= $item['date'] ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($viewFile && !$error): ?>
    <div class="preview">
        <div class="preview-content">
            <?php if ($previewType === 'image'): ?>
                <div class="image-container">
                    <img id="preview-image" src="<?= $previewContent ?>" alt="<?= htmlspecialchars(basename($viewFile)) ?>">
                </div>
            <?php elseif ($previewType === 'video'): ?>
                <video id="video-player" playsinline controls>
                    <source src="data:<?= $mimeType ?>;base64,<?= $previewContent ?>" type="<?= $mimeType ?>">
                </video>
            <?php elseif ($previewType === 'audio'): ?>
                <div class="audio-player-container">
                    <div class="custom-audio-player">
                        <i class="fa-solid fa-music" style="color:<?= $folderColor ?>; font-size:20px;"></i>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:12px; margin-bottom:4px; color:#888;"><?= htmlspecialchars(basename($viewFile)) ?></div>
                            <audio id="audio-player" controls style="width:100%;">
                                <source src="data:<?= $mimeType ?>;base64,<?= $previewContent ?>" type="<?= $mimeType ?>">
                            </audio>
                        </div>
                    </div>
                    <?php if (!empty($audioFiles)): ?>
                    <div class="playlist">
                        <div style="padding:8px 12px; border-bottom:1px solid var(--border); margin-bottom:8px; font-weight:600; font-size:12px;">
                            <i class="fa-solid fa-list" style="color:<?= $folderColor ?>; margin-right:8px;"></i>
                            <?= $i18n['playlist'] ?> (<?= count($audioFiles) ?>)
                        </div>
                        <?php foreach ($audioFiles as $audio): ?>
                            <div class="playlist-item <?= ($viewFile === $audio['path']) ? 'active' : '' ?>"
                                 onclick="window.location.href='<?= buildUrl(['view' => $audio['path'], 'path' => $currentPath]) ?>'">
                                <i class="fa-solid fa-music"></i>
                                <span style="flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($audio['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($previewType === 'pdf'): ?>
                <div class="pdf-container">
                    <div class="pdf-toolbar">
                        <button id="pdf-prev" disabled><i class="fa-solid fa-chevron-left"></i> <?= $i18n['back'] ?></button>
                        <span id="pdf-page-info"><?= $i18n['pdf_page'] ?> <span id="pdf-current-page">1</span> / <span id="pdf-total-pages">0</span></span>
                        <button id="pdf-next" disabled><?= $i18n['next'] ?? 'Next' ?> <i class="fa-solid fa-chevron-right"></i></button>
                        <select id="pdf-scale" style="margin-left: auto; background: var(--panel); color: var(--fg); border: 1px solid var(--border); padding: 3px 6px; border-radius: 3px; font-size:11px;">
                            <option value="auto">Auto</option>
                            <option value="page-width">Page Width</option>
                            <option value="page-height">Page Height</option>
                            <option value="0.5">50%</option>
                            <option value="0.75">75%</option>
                            <option value="1" selected>100%</option>
                            <option value="1.25">125%</option>
                            <option value="1.5">150%</option>
                            <option value="2">200%</option>
                        </select>
                    </div>
                    <div id="pdf-viewer" class="pdf-viewer"></div>
                </div>
            <?php elseif ($previewType === 'monaco'): ?>
                <div id="monaco-container"></div>
            <?php else: ?>
                <div style="padding: 40px; background: var(--bg); text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                    <i class="fa-solid fa-file" style="font-size: 48px; color: #777; margin-bottom: 16px;"></i>
                    <p style="color: #888; font-size: 14px;"><?= htmlspecialchars($previewContent) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
<?php require __DIR__ . '/../public/assets/js/main-script1.js.php'; ?>
</script>

</body>
</html>
