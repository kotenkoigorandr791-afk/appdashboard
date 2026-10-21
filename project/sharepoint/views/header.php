<?php
if (!function_exists('sp_public_shell')) {
    /**
     * Full-page MD3 shell used by password/status screens that do not have a Resource.
     */
    function sp_public_shell(string $title, string $body, ?Share $share = null, int $status = 200): string
    {
        if ($status > 0) {
            http_response_code($status);
        }
        $theme = sp_theme();
        $themeAttr = $theme === 'auto' ? '' : ' data-theme="' . sp_h($theme) . '"';
        $appIcon = 'hub';
        $appName = 'SharePoint';
        if ($share) {
            $meta = ShareRegistry::appMeta($share->resource_app);
            $appIcon = $meta['icon'];
            $appName = $meta['label'];
        }
        return '<!DOCTYPE html>
<html lang="' . sp_h(sp_lang()) . '"' . $themeAttr . '>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . sp_h($title) . ' · SharePoint</title>
<link rel="icon" type="image/svg+xml" href="' . sp_h(sp_asset('icon.svg')) . '">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/md3-tokens.css')) . '">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/md3-components.css')) . '">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/md3-layout.css')) . '">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/sharepoint.css')) . '">
</head>
<body class="sp-body sp-status-body">
<main class="sp-status-main">
  <div class="sp-status-brand">
    <span class="sp-app-badge material-symbols-outlined">' . sp_h($appIcon) . '</span>
    <span class="md3-typescale-title-medium">' . sp_h($appName) . '</span>
  </div>
  ' . $body . '
  <footer class="sp-footer"><span class="sp-footer__brand"><span class="material-symbols-outlined">hub</span>SharePoint</span></footer>
</main>
<script src="' . sp_h(sp_asset('js/md3-components.js')) . '" defer></script>
</body>
</html>';
    }
}
