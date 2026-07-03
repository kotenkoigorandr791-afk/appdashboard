<!DOCTYPE html>
    <html lang="<?= $lang ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ONLYOFFICE - <?php echo htmlspecialchars(basename($officeFilePath)); ?></title>
        <script src="<?php echo $DOC_SERV_URL; ?>/web-apps/apps/api/documents/api.js"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            body, html {
                margin: 0;
                padding: 0;
                height: 100%;
                width: 100%;
                overflow: hidden; /* Запрещаем прокрутку на уровне body */
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
                background-color: <?php echo $currentTheme['backgroundColor']; ?>;
                display: flex;
                flex-direction: column;
            }
            
            .app-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 4px 12px;
                background: <?php echo ($theme === 'light') ? '#ffffff' : '#252526'; ?>;
                border-bottom: 1px solid <?php echo ($theme === 'light') ? '#d4d4d4' : '#3c3c3c'; ?>;
                height: 36px;
                flex-shrink: 0; /* Запрещаем сжатие шапки */
                box-sizing: border-box;
            }
            
            .btn {
                background: <?php echo ($theme === 'light') ? '#f3f3f3' : '#2d2d2d'; ?>;
                border: 1px solid <?php echo ($theme === 'light') ? '#d4d4d4' : '#3c3c3c'; ?>;
                color: <?php echo ($theme === 'light') ? '#1e1e1e' : '#d4d4d4'; ?>;
                padding: 4px 10px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                text-decoration: none;
                transition: all 0.2s;
                flex-shrink: 0;
            }
            
            .file-title {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                font-weight: 500;
                color: <?php echo ($theme === 'light') ? '#1e1e1e' : '#d4d4d4'; ?>;
                max-width: 50%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                flex-shrink: 1;
            }
            
            .user-badge {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 2px 10px;
                background: <?php echo ($theme === 'light') ? '#f0f0f0' : '#3c3c3c'; ?>;
                border-radius: 20px;
                font-size: 11px;
                color: <?php echo ($theme === 'light') ? '#333' : '#fff'; ?>;
                height: 26px;
                flex-shrink: 0;
            }
            
            #office-editor {
                flex: 1; /* Занимает всё оставшееся место */
                width: 100%;
                min-height: 0; /* Важно для flex-контейнера */
                height: calc(100vh - 36px); /* Резервный вариант */
            }
            
            /* Контейнер для всего содержимого */
            .office-container {
                display: flex;
                flex-direction: column;
                height: 100vh;
                width: 100vw;
                overflow: hidden;
            }
            
            .loading {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                text-align: center;
                color: #888;
            }
            
            .spinner {
                border: 3px solid <?php echo ($theme === 'light') ? '#f3f3f3' : '#333'; ?>;
                border-top: 3px solid <?= $folderColor ?>;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 2s linear infinite;
                margin: 0 auto 15px;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <div class="office-container">
        <div class="app-header">
            <div class="file-title">
                <i class="fa-solid fa-file-word"></i>
                <span><?php echo htmlspecialchars(basename($officeFilePath)); ?></span>
            </div>
            
            <div class="user-badge">
                <i class="fa-solid fa-cubes"></i>
                <span><?php echo $VIEWER_USER; ?></span>
            </div>
            
            <a href="<?= buildUrl(['view' => null, 'path' => $currentPath]) ?>" class="btn">
                <i class="fa-solid fa-arrow-left"></i>
                <span><?= $i18n['back'] ?></span>
            </a>
        </div>
        
        <div id="office-editor"></div>
        
        <script>
<?php require __DIR__ . '/../public/assets/js/office-script1.js.php'; ?>
</script>


        </div> <!-- закрытие app-header -->
            <div id="office-editor"></div>
        </div> <!-- закрытие office-container -->
    </body>
    
    </html>
