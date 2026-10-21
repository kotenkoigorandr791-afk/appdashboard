<?php
$shellTheme = (string)($GLOBALS['APPDASHBOARD_KB_THEME'] ?? '');
if ($shellTheme === 'device') {
    $shellTheme = 'auto';
}
if ($shellTheme === '' || !in_array($shellTheme, ['light', 'dark', 'auto'], true)) {
    $shellTheme = (string)$this->user->getTheme();
    if ($shellTheme === 'device') {
        $shellTheme = 'auto';
    }
}
$cssTheme = $shellTheme === 'dark' ? 'dark' : ($shellTheme === 'auto' ? 'auto' : 'light');
$bodyClass = $shellTheme === 'dark' ? 'kb-dark' : '';
$shellProjects = isset($board_selector) && is_array($board_selector) ? $board_selector : [];
$shellProject = isset($project) && is_array($project) ? $project : [];
?>
<!DOCTYPE html>
<html lang="<?= $this->app->jsLang() ?>"<?php if ($this->app->isRtlLanguage()): ?> dir="rtl"<?php endif; ?> data-kb-theme="<?= $this->text->e($shellTheme) ?>"<?= $shellTheme === 'dark' ? ' class="kb-dark"' : '' ?>>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="robots" content="noindex,nofollow">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="referrer" content="no-referrer">
        <?php if (function_exists('app_sentry_browser_script')) app_sentry_browser_script('kanboard'); ?>
        <script>
        (function () {
          var t = <?= json_encode($shellTheme, JSON_UNESCAPED_SLASHES) ?>;
          try {
            if (window.parent && window.parent !== window) {
              var p = new URLSearchParams(window.parent.location.search || '').get('theme');
              if (p) t = String(p).toLowerCase();
            }
          } catch (e) {}
          if (t === 'device') t = 'auto';
          if (t !== 'dark' && t !== 'auto' && t !== 'light') t = 'light';
          var dark = t === 'dark';
          if (t === 'auto') {
            try { dark = window.matchMedia('(prefers-color-scheme: dark)').matches; } catch (e2) { dark = false; }
          }
          var root = document.documentElement;
          root.setAttribute('data-kb-theme', t);
          root.classList.toggle('kb-dark', dark);
          if (dark) {
            var s = document.createElement('style');
            s.id = 'kb-dark-boot';
            s.textContent = 'html.kb-dark,html.kb-dark body{background:#18181b!important;color:#fafafa!important}';
            root.appendChild(s);
          }
          window.__kbShellTheme = t;
          window.__kbShellDark = dark;
        })();
        </script>

        <?php if (isset($board_public_refresh_interval)): ?>
            <meta http-equiv="refresh" content="<?= $board_public_refresh_interval ?>">
        <?php endif ?>

        <?= $this->asset->colorCss() ?>
        <?= $this->asset->css('assets/css/vendor.min.css') ?>
        <?php if (! isset($not_editable)): ?>
            <?= $this->asset->css('assets/css/'.$cssTheme.'.min.css') ?>
        <?php else: ?>
            <?= $this->asset->css('assets/css/light.min.css') ?>
        <?php endif ?>
        <?= $this->asset->css('assets/css/print.min.css', true, 'print') ?>
        <?= $this->asset->customCss() ?>

        <?php if (! isset($not_editable)): ?>
            <?= $this->asset->js('assets/js/vendor.min.js') ?>
            <?= $this->asset->js('assets/js/app.min.js') ?>
        <?php endif ?>

        <?= $this->hook->asset('css', 'template:layout:css') ?>
        <?= $this->hook->asset('js', 'template:layout:js') ?>

        <link rel="icon" href="<?= $this->url->dir() ?>assets/img/adaptive-favicon.svg" type="image/svg+xml">
        <link rel="icon" type="image/png" href="<?= $this->url->dir() ?>assets/img/favicon.png">
        <link rel="apple-touch-icon" href="<?= $this->url->dir() ?>assets/img/touch-icon-iphone.png">
        <link rel="apple-touch-icon" sizes="72x72" href="<?= $this->url->dir() ?>assets/img/touch-icon-ipad.png">
        <link rel="apple-touch-icon" sizes="114x114" href="<?= $this->url->dir() ?>assets/img/touch-icon-iphone-retina.png">
        <link rel="apple-touch-icon" sizes="144x144" href="<?= $this->url->dir() ?>assets/img/touch-icon-ipad-retina.png">

        <title>
            <?php if (isset($page_title)): ?>
                <?= $this->text->e($page_title) ?>
            <?php elseif (isset($title)): ?>
                <?= $this->text->e($title) ?>
            <?php else: ?>
                Kanboard
            <?php endif ?>
        </title>

        <?= $this->hook->render('template:layout:head') ?>
    </head>
    <body class="kb-circle <?= $bodyClass ?>"
          data-status-url="<?= $this->url->href('UserAjaxController', 'status') ?>"
          data-login-url="<?= $this->url->href('AuthController', 'login') ?>"
          data-keyboard-shortcut-url="<?= $this->url->href('DocumentationController', 'shortcuts') ?>"
          data-timezone="<?= $this->app->getTimezone() ?>"
          data-js-date-format="<?= $this->app->getJsDateFormat() ?>"
          data-js-time-format="<?= $this->app->getJsTimeFormat() ?>"
    >
    <script>
    (function () {
      if (window.__kbShellDark) document.body.classList.add('kb-dark');
      else document.body.classList.remove('kb-dark');
      if (window.__kbShellTheme) document.documentElement.setAttribute('data-kb-theme', window.__kbShellTheme);
      try {
        var phone = window.matchMedia('(max-width: 640px)').matches;
        if (phone) document.body.classList.add('kb-sidebar-collapsed');
        else document.body.classList.remove('kb-sidebar-collapsed');
      } catch (e) {}
    })();
    </script>

    <?php if (isset($no_layout) && $no_layout): ?>
        <?= $this->app->flashMessage() ?>
        <?= $content_for_layout ?>
    <?php else: ?>
        <?= $this->hook->render('template:layout:top') ?>
        <div class="kb-app-shell">
            <?= $this->render('appDashboard:shell/sidebar', [
                'board_selector' => $shellProjects,
                'project' => $shellProject,
            ]) ?>
            <div class="kb-app-main">
                <?= $this->render('header', array(
                    'title' => $title,
                    'description' => isset($description) ? $description : '',
                    'board_selector' => isset($board_selector) ? $board_selector : array(),
                    'project' => isset($project) ? $project : array(),
                )) ?>
                <section class="page kb-page">
                    <?= $this->app->flashMessage() ?>
                    <?= $content_for_layout ?>
                </section>
            </div>
        </div>
    <?php endif ?>
    <?= $this->hook->render('template:layout:bottom') ?>
    </body>
</html>
