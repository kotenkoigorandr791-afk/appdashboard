<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TFT Code Generator</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" type="image/png" href="icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link id="hljs-theme" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.0/styles/dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.0/languages/cpp.min.js"></script>
    <?php
    require_once dirname(__DIR__, 3) . '/project/i18n_helpers.php';
    $tftI18n = ap_load_i18n_all_langs(dirname(__DIR__, 1));
    ?>
    <script>
        var translations = <?php echo json_encode($tftI18n, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script>var require = { paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } };</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
    <script>
        function updatePanelPositions() {
            const topBar = document.getElementById('top-bar');
            if (!topBar) return;
            const topBarHeight = topBar.offsetHeight;
            document.documentElement.style.setProperty('--top-bar-height', `${topBarHeight}px`);
            const cc = document.querySelector('.canvas-container');
            if (cc) cc.style.marginTop = `${topBarHeight}px`;
            const lp = document.getElementById('left-panel');
            const rp = document.getElementById('right-panel');
            if (lp) { lp.style.top = ''; lp.style.height = ''; lp.style.bottom = ''; }
            if (rp) { rp.style.top = ''; rp.style.height = ''; rp.style.bottom = ''; }
        }
        window.addEventListener('resize', function() { updatePanelPositions(); });
        document.addEventListener('DOMContentLoaded', function() {
            updatePanelPositions();
            requestAnimationFrame(updatePanelPositions);
        });
    </script>
</head>
