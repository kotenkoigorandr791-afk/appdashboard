<?php
function renderAddForm() {
    global $lang, $theme, $color, $lang_array, $view_mode, $display_mode;
    ob_start(); ?>
    <!DOCTYPE html>
    <html lang="<?= $lang ?>" data-theme="<?= $theme ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $lang_array['add_route'] ?></title>
        <style>
            :root { --primary-color: <?= $color ?>; --bg-color: <?= $theme === 'dark' ? '#1a1a1a' : '#ffffff' ?>; --text-color: <?= $theme === 'dark' ? '#ffffff' : '#000000' ?>; --card-bg: <?= $theme === 'dark' ? '#2d2d2d' : '#f5f5f5' ?>; --scrollbar-bg: <?= $theme === 'dark' ? '#2d2d2d' : '#f5f5f5' ?>; --scrollbar-thumb: <?= $theme === 'dark' ? '#555' : '#ccc' ?>; }
            ::-webkit-scrollbar { width: 12px; }
            ::-webkit-scrollbar-track { background: var(--scrollbar-bg); }
            ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 6px; border: 2px solid var(--scrollbar-bg); }
            ::-webkit-scrollbar-thumb:hover { background: var(--primary-color); }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); transition: all 0.3s ease; }
            header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background-color: var(--card-bg); margin-bottom: 2rem; position: relative; }
            .header-content { display: flex; align-items: center; gap: 1rem; width: 100%; justify-content: flex-start; }
            .btn { padding: 0.65rem 1.15rem; border: none; border-radius: 999px; background-color: var(--primary-color); color: white; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
            .btn-icon { width: 20px; height: 20px; fill: currentColor; }
            .modal-close { position: absolute; top: 50%; right: 1.25rem; transform: translateY(-50%); width: 46px; height: 46px; min-width: 46px; padding: 0; border-radius: 999px; border: 2px solid rgba(220, 38, 38, 0.72); background: rgba(220, 38, 38, 0.08); color: #dc2626; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; transition: transform 0.14s ease, background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease; box-shadow: inset 0 1px 0 rgba(255,255,255,0.1); }
            .modal-close:hover { background: rgba(220, 38, 38, 0.14); border-color: rgba(220, 38, 38, 0.92); box-shadow: 0 10px 24px rgba(220, 38, 38, 0.16); }
            .modal-close:active { transform: translateY(-50%) scale(0.9) rotate(8deg); background: rgba(220, 38, 38, 0.2); }
            .modal-close svg { width: 18px; height: 18px; stroke: currentColor; fill: none; }
            .route-form { max-width: 500px; margin: 2rem auto; padding: 2rem; background-color: var(--card-bg); border-radius: 28px; }
            .form-group { margin-bottom: 1rem; }
            .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
            .form-group input { width: 100%; padding: 0.85rem 1rem; border: 1px solid #ddd; border-radius: 18px; background-color: var(--bg-color); color: var(--text-color); font-size: 1rem; }
            .file-info { margin-top: 0.5rem; font-size: 0.9rem; color: #666; }
            [data-theme="dark"] { --bg-color: #1a1a1a; --text-color: #ffffff; --card-bg: #2d2d2d; --scrollbar-bg: #2d2d2d; --scrollbar-thumb: #555; }
            [data-theme="light"] { --bg-color: #ffffff; --text-color: #000000; --card-bg: #f5f5f5; --scrollbar-bg: #f5f5f5; --scrollbar-thumb: #ccc; }
            @media (max-width: 768px) { header { padding: 1rem; flex-direction: column; align-items: flex-start; } .header-content { flex-direction: column; align-items: flex-start; } .route-form { margin: 1rem; padding: 1.5rem; } .btn { padding: 0.4rem 0.8rem; font-size: 0.9rem; }.search-container { 
                    width: 100%; 
                    margin: 0 0 0.5rem 0; /* Изменено с margin: 0; */
                    order: 1;
                    max-width: none; /* Убираем ограничение на мобильных */
                } 
            }
            @media (max-width: 480px) { header { padding: 0.5rem; } .header-content h2 { font-size: 1.25rem; } .route-form { padding: 1rem; } .btn { padding: 0.3rem 0.6rem; font-size: 0.8rem; } .modal-close { right: 1rem; width: 42px; height: 42px; min-width: 42px; } }
        </style>
    </head>
    <body>
        <header>
            <div class="header-content">
                <h2 style="margin-left: 5%;"><?= $lang_array['add_route'] ?></h2>
            </div>
            <a href="?lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="modal-close" aria-label="Close">
                <svg viewBox="0 0 24 24" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </a>
        </header>
        <main>
            <form method="POST" enctype="multipart/form-data" class="route-form">
                <input type="hidden" name="action" value="add_route">
                <div class="form-group">
                    <label for="name"><?= $lang_array['route_name'] ?>:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="route_file"><?= $lang_array['route_file'] ?> (KML/CSV/HTML/GPX):</label>
                    <input type="file" id="route_file" name="route_file" accept=".kml,.csv,.html,.gpx" required onchange="updateFileName(this)">
                    <div class="file-info">Supported formats: KML, CSV, HTML (with coordinates in JavaScript), GPX</div>
                </div>
                <button type="submit" class="btn"><?= $lang_array['add_route'] ?></button>
            </form>
        </main>
        <script>
            function updateFileName(input) {
                if (input.files.length > 0) {
                    const fileName = input.files[0].name;
                    const nameWithoutExt = fileName.replace(/\.[^/.]+$/, "");
                    document.getElementById('name').value = nameWithoutExt;
                }
            }
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function renderAddFolderForm() {
    global $lang, $theme, $color, $lang_array, $view_mode, $display_mode;
    ob_start(); ?>
    <!DOCTYPE html>
    <html lang="<?= $lang ?>" data-theme="<?= $theme ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $lang_array['add_folder'] ?></title>
        <style>
            :root { --primary-color: <?= $color ?>; --bg-color: <?= $theme === 'dark' ? '#1a1a1a' : '#ffffff' ?>; --text-color: <?= $theme === 'dark' ? '#ffffff' : '#000000' ?>; --card-bg: <?= $theme === 'dark' ? '#2d2d2d' : '#f5f5f5' ?>; --scrollbar-bg: <?= $theme === 'dark' ? '#2d2d2d' : '#f5f5f5' ?>; --scrollbar-thumb: <?= $theme === 'dark' ? '#555' : '#ccc' ?>; }
            ::-webkit-scrollbar { width: 12px; }
            ::-webkit-scrollbar-track { background: var(--scrollbar-bg); }
            ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 6px; border: 2px solid var(--scrollbar-bg); }
            ::-webkit-scrollbar-thumb:hover { background: var(--primary-color); }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); transition: all 0.3s ease; }
            header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background-color: var(--card-bg); margin-bottom: 2rem; position: relative; }
            .header-content { display: flex; align-items: center; gap: 1rem; width: 100%; justify-content: flex-start; }
            .btn { padding: 0.65rem 1.15rem; border: none; border-radius: 999px; background-color: var(--primary-color); color: white; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
            .btn-icon { width: 20px; height: 20px; fill: currentColor; }
            .modal-close { position: absolute; top: 50%; right: 1.25rem; transform: translateY(-50%); width: 46px; height: 46px; min-width: 46px; padding: 0; border-radius: 999px; border: 2px solid rgba(220, 38, 38, 0.72); background: rgba(220, 38, 38, 0.08); color: #dc2626; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; transition: transform 0.14s ease, background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease; box-shadow: inset 0 1px 0 rgba(255,255,255,0.1); }
            .modal-close:hover { background: rgba(220, 38, 38, 0.14); border-color: rgba(220, 38, 38, 0.92); box-shadow: 0 10px 24px rgba(220, 38, 38, 0.16); }
            .modal-close:active { transform: translateY(-50%) scale(0.9) rotate(8deg); background: rgba(220, 38, 38, 0.2); }
            .modal-close svg { width: 18px; height: 18px; stroke: currentColor; fill: none; }
            .route-form { max-width: 500px; margin: 2rem auto; padding: 2rem; background-color: var(--card-bg); border-radius: 28px; }
            .form-group { margin-bottom: 1rem; }
            .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
            .form-group input { width: 100%; padding: 0.85rem 1rem; border: 1px solid #ddd; border-radius: 18px; background-color: var(--bg-color); color: var(--text-color); font-size: 1rem; }
            .file-info { margin-top: 0.5rem; font-size: 0.9rem; color: #666; }
            [data-theme="dark"] { --bg-color: #1a1a1a; --text-color: #ffffff; --card-bg: #2d2d2d; --scrollbar-bg: #2d2d2d; --scrollbar-thumb: #555; }
            [data-theme="light"] { --bg-color: #ffffff; --text-color: #000000; --card-bg: #f5f5f5; --scrollbar-bg: #f5f5f5; --scrollbar-thumb: #ccc; }
            @media (max-width: 768px) { header { padding: 1rem; flex-direction: column; align-items: flex-start; } .header-content { flex-direction: column; align-items: flex-start; } .route-form { margin: 1rem; padding: 1.5rem; } .btn { padding: 0.4rem 0.8rem; font-size: 0.9rem; } }
            @media (max-width: 480px) { header { padding: 0.5rem; } .header-content h2 { font-size: 1.25rem; } .route-form { padding: 1rem; } .btn { padding: 0.3rem 0.6rem; font-size: 0.8rem; } .modal-close { right: 1rem; width: 42px; height: 42px; min-width: 42px; } }
        </style>
    </head>
    <body>
        <header>
            <div class="header-content">
                <h2 style="margin-left: 5%;"><?= $lang_array['add_folder'] ?></h2>
            </div>
            <a href="?lang=<?= $lang ?>&theme=<?= $theme ?>&color=<?= urlencode($color) ?>&view_mode=<?= $view_mode ?>&display_mode=<?= $display_mode ?>" class="modal-close" aria-label="Close">
                <svg viewBox="0 0 24 24" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </a>
        </header>
        <main>
            <form method="POST" enctype="multipart/form-data" class="route-form">
                <input type="hidden" name="action" value="add_folder">
                <div class="form-group">
                    <label for="name"><?= $lang_array['route_name'] ?> (Base Name):</label>
                    <input type="text" id="name" name="name" required placeholder="e.g., Bike Route">
                </div>
                <div class="form-group">
                    <label for="folder_files">Select Folder Files:</label>
                    <input type="file" id="folder_files" name="folder_files[]" accept=".kml,.csv,.html,.gpx" multiple required webkitdirectory directory>
                    <div class="file-info">Select multiple KML/CSV/HTML/GPX files from a folder</div>
                </div>
                <button type="submit" class="btn"><?= $lang_array['add_folder'] ?></button>
            </form>
        </main>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
