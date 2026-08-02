<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Viewer - <?= htmlspecialchars($currentFileName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.7/viewer.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { 
            width: 100%; 
            height: 100%; 
            overflow: hidden; 
        }
        
        /* Theme variables from parent */
        :root[data-theme="light"] {
            --bg: #808080;
            --toolbar-bg: rgba(0,0,0,0.8);
            --toolbar-hover: rgba(0,0,0,0.95);
            --nav-bg: rgba(255,255,255,0.2);
            --nav-hover: rgba(255,255,255,0.3);
            --icon-color: #333333;
            --counter-bg: rgba(255,255,255,0.9);
            --counter-text: #333333;
            --counter-border: rgba(0,0,0,0.2);
        }
        :root[data-theme="dark"] {
            --bg: #2a2a2a;
            --toolbar-bg: rgba(255,255,255,0.15);
            --toolbar-hover: rgba(255,255,255,0.25);
            --nav-bg: rgba(0,0,0,0.3);
            --nav-hover: rgba(0,0,0,0.5);
            --icon-color: #ffffff;
            --counter-bg: rgba(255,255,255,0.15);
            --counter-text: #ffffff;
            --counter-border: rgba(255,255,255,0.2);
        }
        
        body { 
            background: var(--bg) !important; 
            transition: background 0.3s ease;
        }
        
        .viewer-container { 
            position: fixed;
            top: 0; left: 0; 
            width: 100vw; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            z-index: 9999;
            background: var(--bg);
        }
        
        #image { display: none; }
        
        .viewer-canvas img { 
            cursor: grab !important; 
        }
        .viewer-canvas img:active { 
            cursor: grabbing !important; 
        }
        
        .viewer-navbar, .viewer-title { display: none !important; }
        .viewer-toolbar > .viewer-toolbar-group:not(:first-child) { display: none !important; }
        
        /* Toolbar always on top and visible */
        .viewer-toolbar {
            opacity: 0.9 !important; 
            position: fixed !important; 
            top: 20px !important; 
            left: 50% !important; 
            transform: translateX(-50%) !important; 
            z-index: 10000 !important; 
            background: var(--toolbar-bg) !important; 
            border-radius: 10px !important; 
            padding: 5px !important;
            transition: opacity 0.3s ease;
        }
        
        /* Navigation buttons */
        .nav-buttons {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 10001;
            pointer-events: none;
        }
        
        .nav-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--nav-bg);
            border: 2px solid rgba(255,255,255,0.3);
            color: var(--icon-color);
            font-size: 24px;
            display: flex;
            padding-bottom: 6px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: auto;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .nav-btn:hover {
            background: var(--nav-hover);
            transform: scale(1.1);
        }
        
        .nav-btn:active {
            transform: scale(0.95);
        }
        
        .nav-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Mini map for location */
        .location-map {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 200px;
            height: 200px;
            border-radius: 12px;
            overflow: hidden;
            z-index: 10002;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            border: 2px solid var(--counter-border);
            display: none;
        }

        .location-map iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        /* Current position indicator */
        .position-indicator {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--counter-bg);
            color: var(--counter-text);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            z-index: 10002;
            backdrop-filter: blur(5px);
            border: 1px solid var(--counter-border);
        }
        
        /* Image counter - in the top right corner */
        .image-counter {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--counter-bg);
            color: var(--counter-text);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            z-index: 10002;
            backdrop-filter: blur(5px);
            border: 1px solid var(--counter-border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        /* Dark theme specific styles */
        body[data-theme="dark"] .nav-btn {
            color: #ffffff;
            border-color: rgba(255,255,255,0.3);
        }
        
        /* Light theme specific styles */
        body[data-theme="light"] .nav-btn {
            color: #333333;
            border-color: rgba(0,0,0,0.2);
        }
        
        body[data-theme="light"] .position-indicator,
        body[data-theme="light"] .image-counter {
            background: rgba(255,255,255,0.9);
            color: #333333;
            border: 1px solid rgba(0,0,0,0.2);
        }
        
        /* Show toolbar on hover/zoom */
        .viewer-container:hover .viewer-toolbar,
        .viewer-zooming .viewer-toolbar,
        .viewer-moving .viewer-canvas .viewer-toolbar {
            opacity: 0.95 !important;
        }
        /* Explicit counter styles depending on theme */
        body[data-theme="dark"] .image-counter {
            color: #ffffff !important;
            background: rgba(0, 0, 0, 0.7) !important;
        }

        body[data-theme="light"] .image-counter {
            color: #333333 !important;
            background: rgba(255, 255, 255, 0.9) !important;
        }

        /* Same for the position indicator */
        body[data-theme="dark"] .position-indicator {
            color: #ffffff !important;
            background: rgba(0, 0, 0, 0.7) !important;
        }

        body[data-theme="light"] .position-indicator {
            color: #333333 !important;
            background: rgba(255, 255, 255, 0.9) !important;
        }

        .nav-buttons.is-hidden {
            display: none !important;
        }
    </style>
    <script><?php echo file_get_contents(dirname(__DIR__, 4) . '/assets/file-load-progress.js'); ?></script>
</head>
<body data-theme="<?= htmlspecialchars($theme) ?>"
      data-accent-color="<?= htmlspecialchars($color) ?>"
      data-current-index="<?= $currentIndex ?>"
      data-images='<?= htmlspecialchars(json_encode($images)) ?>'
      data-folder="<?= htmlspecialchars($folder) ?>"
      data-full-path="<?= htmlspecialchars($filePath) ?>">

    <div class="viewer-container">
        <?php
        // Extract relative path for API (remove storage/{user}/ prefix)
        $apiPath = $filePath;
        if (preg_match('#^storage/[^/]+/(?:\.[^/]+/)?(.+)$#', $filePath, $matches)) {
            $apiPath = $matches[1];
        }
        ?>
        <img id="image" alt="Image" data-initial-src="<?= htmlspecialchars($apiUrl) ?>?action=raw&path=<?= urlencode($apiPath) ?>&root=<?= urlencode($root) ?>">
    </div>
    
    <!-- Navigation buttons -->
    <div class="nav-buttons<?= count($images) <= 1 ? ' is-hidden' : '' ?>">
        <button class="nav-btn prev-btn" <?= $currentIndex <= 0 ? 'disabled' : '' ?>>←</button>
        <button class="nav-btn next-btn" <?= $currentIndex >= count($images) - 1 ? 'disabled' : '' ?>>→</button>
    </div>
    
    <!-- Position indicator -->
    <div class="position-indicator">
        <?= htmlspecialchars($currentFileName) ?>
    </div>
    
    <!-- Image counter -->
    <div class="image-counter">
        <?= ($currentIndex + 1) ?> / <?= count($images) ?>
    </div>

    <!-- Location mini map -->
    <div class="location-map" id="locationMap"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.7/viewer.min.js"></script>
    <script>
<?php require __DIR__ . '/../public/assets/js/page-script1.js.php'; ?>
</script>
</body>
</html>
