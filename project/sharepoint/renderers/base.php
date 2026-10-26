<?php
interface AppRendererInterface
{
    public function renderPage(Share $share, Resource $resource, array $context): string;
}

abstract class BaseRenderer
{
    protected Share $share;
    protected Resource $resource;
    protected array $context;

    public function __construct(Share $share, Resource $resource, array $context = [])
    {
        $this->share = $share;
        $this->resource = $resource;
        $this->context = $context;
    }

    abstract public function render(): string;

    protected function appIcon(): string
    {
        return ShareRegistry::appMeta($this->share->resource_app)['icon'];
    }

    protected function appName(): string
    {
        return ShareRegistry::appMeta($this->share->resource_app)['label'];
    }

    protected function resourceTypeLabel(): string
    {
        if ($this->share->resource_type === 'app') {
            $catalog = ShareRegistry::entityCatalog();
            return $catalog[$this->share->entity_type] ?? 'Interactive app';
        }
        return $this->share->resource_type === 'folder' ? sp_t('folder') : sp_t('file');
    }

    protected function pageTitle(): string
    {
        return $this->share->title !== '' ? $this->share->title : $this->resource->getTitle();
    }

    protected function dataModeBadge(): string
    {
        if ($this->share->snapshot_data !== null) {
            $date = $this->share->snapshot_created_at ? date('M j, Y', strtotime($this->share->snapshot_created_at)) : '';
            return '<span class="md3-chip md3-chip--assist"><span class="material-symbols-outlined">photo_camera</span>'
                . sp_h(sp_t('snapshot_from', ['date' => $date])) . '</span>';
        }
        return '<span class="md3-chip md3-chip--assist"><span class="material-symbols-outlined">sync</span>'
            . sp_h(sp_t('live_data')) . '</span>';
    }

    protected function permissionChips(): string
    {
        $icons = ['view' => 'visibility', 'download' => 'download', 'interact' => 'touch_app', 'edit' => 'edit', 'share' => 'share'];
        $html = '<div class="sp-chips">';
        foreach ($this->share->permissions as $permission) {
            $html .= '<span class="md3-chip md3-chip--filter"><span class="material-symbols-outlined">'
                . ($icons[$permission] ?? 'check') . '</span>' . sp_h(sp_t($permission)) . '</span>';
        }
        $html .= '</div>';
        return $html;
    }

    protected function renderHeader(): string
    {
        $token = rawurlencode($this->share->token);
        $activeLang = sp_lang();
        $languageOptions = '';
        foreach (sp_languages() as $code => $label) {
            $active = $code === $activeLang;
            $languageOptions .= '<a class="sp-lang-option' . ($active ? ' is-active' : '') . '" href="?lang=' . rawurlencode($code) . '" role="menuitemradio" aria-checked="' . ($active ? 'true' : 'false') . '" data-sp-lang="' . sp_h($code) . '">'
                . '<span class="sp-lang-option__code">' . sp_h(strtoupper($code)) . '</span>'
                . '<span class="sp-lang-option__name">' . sp_h($label) . '</span>'
                . '<span class="material-symbols-outlined sp-lang-option__check" aria-hidden="true">check</span>'
                . '</a>';
        }
        $download = '';
        if ($this->share->hasPermission('download') && $this->share->resource_type === 'file') {
            $suffix = '/download';
            $download = '<a class="md3-btn md3-btn--icon md3-btn--text sp-header-action" href="' . sp_h(sp_public_url($this->share->token, $suffix))
                . '" aria-label="' . sp_h(sp_t('download')) . '" title="' . sp_h(sp_t('download')) . '">'
                . '<span class="material-symbols-outlined">download</span></a>';
        }
        return '
<header class="md3-top-app-bar sp-top-bar" role="banner">
  <div class="sp-top-bar__lead">
    <span class="sp-app-badge material-symbols-outlined" aria-hidden="true">' . sp_h($this->appIcon()) . '</span>
    <div class="sp-top-bar__titles">
      <h1 class="md3-top-app-bar__title">' . sp_h($this->pageTitle()) . '</h1>
      <span class="sp-top-bar__subtitle">' . sp_h($this->appName()) . ' · ' . sp_h($this->resourceTypeLabel()) . '</span>
    </div>
  </div>
  <div class="sp-top-bar__actions">
    <button class="md3-btn md3-btn--icon md3-btn--text sp-header-action" data-sp-theme-cycle aria-label="' . sp_h(sp_t('theme')) . '" title="' . sp_h(sp_t('theme')) . '">
      <svg class="sp-theme-icon sp-theme-icon--sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path></svg>
      <svg class="sp-theme-icon sp-theme-icon--moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path></svg>
    </button>
    ' . $download . '
    <button class="md3-btn md3-btn--icon md3-btn--text sp-header-action" data-md3-menu-trigger="sp-more-menu" aria-label="' . sp_h(sp_t('more')) . '">
      <span class="material-symbols-outlined">more_vert</span>
    </button>
  </div>
</header>
<nav id="sp-more-menu" class="md3-menu sp-menu" role="menu" aria-label="' . sp_h(sp_t('more')) . '">
  <button class="md3-menu__item" role="menuitem" data-sp-copy="' . sp_h(sp_public_url($this->share->token)) . '">
    <span class="material-symbols-outlined">content_copy</span>' . sp_h(sp_t('copy_link')) . '
  </button>
  <button class="md3-menu__item" role="menuitem" data-sp-qr>
    <span class="material-symbols-outlined">qr_code_2</span>' . sp_h(sp_t('qr_code')) . '
  </button>
  <div class="md3-menu__divider"></div>
  <button class="md3-menu__item" role="menuitem" data-sp-language>
    <span class="material-symbols-outlined">language</span>' . sp_h(sp_t('language')) . '
    <span class="sp-menu__value">' . sp_h(sp_lang_name()) . '</span>
  </button>
</nav>
<div class="md3-dialog" id="sp-qr-dialog" role="dialog" aria-modal="true" aria-labelledby="sp-qr-title">
  <div class="md3-dialog__scrim" data-md3-dialog-close></div>
  <div class="md3-dialog__container sp-qr-dialog__container">
    <div class="md3-dialog__header"><h2 class="md3-dialog__title" id="sp-qr-title">' . sp_h(sp_t('qr_code')) . '</h2></div>
    <div class="md3-dialog__content sp-qr-dialog__content">
      <div id="sp-qr-canvas" class="sp-qr-canvas" data-sp-qr-value="' . sp_h(sp_public_url($this->share->token)) . '"></div>
    </div>
    <div class="md3-dialog__actions">
      <button class="md3-btn md3-btn--text" data-md3-dialog-close>' . sp_h(sp_t('close')) . '</button>
      <button class="md3-btn md3-btn--filled" data-sp-copy="' . sp_h(sp_public_url($this->share->token)) . '">
        <span class="material-symbols-outlined">content_copy</span>' . sp_h(sp_t('copy_link')) . '
      </button>
    </div>
  </div>
</div>
<div class="md3-dialog sp-lang-dialog" id="sp-language-dialog" role="dialog" aria-modal="true" aria-labelledby="sp-language-title">
  <div class="md3-dialog__scrim" data-md3-dialog-close></div>
  <div class="md3-dialog__container sp-lang-dialog__container">
    <div class="md3-dialog__header"><h2 class="md3-dialog__title" id="sp-language-title">' . sp_h(sp_t('language')) . '</h2></div>
    <div class="md3-dialog__content sp-lang-list" role="menu">' . $languageOptions . '</div>
    <div class="md3-dialog__actions">
      <button class="md3-btn md3-btn--text" data-md3-dialog-close>' . sp_h(sp_t('close')) . '</button>
    </div>
  </div>
</div>
<div id="sp-snackbar" class="sp-snackbar" role="status" aria-live="polite"></div>
';
    }

    protected function layout(string $content, array $options = []): string
    {
        $title = $options['title'] ?? $this->pageTitle();
        $embed = !empty($options['embed']);
        $bodyClass = $options['body_class'] ?? '';
        $theme = sp_theme();
        $themeAttr = $theme === 'auto' ? '' : $theme;
        $header = $embed ? '' : $this->renderHeader();
        $lang = sp_lang();
        $description = $this->share->description !== '' ? $this->share->description : $this->pageTitle();
        return '<!DOCTYPE html>
<html lang="' . sp_h($lang) . '"' . ($themeAttr ? ' data-theme="' . sp_h($themeAttr) . '"' : '') . '>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="sp-csrf" content="' . sp_h(sp_csrf_token()) . '">
<title>' . sp_h($title) . ' · SharePoint</title>
<link rel="icon" type="image/svg+xml" href="' . sp_h(sp_asset('icon.svg')) . '">
<meta name="description" content="' . sp_h($description) . '">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="preload" href="' . sp_h(sp_asset('fonts/material-symbols-outlined.woff2')) . '" as="font" type="font/woff2" crossorigin>
<script>(function(d){var r=function(){d.documentElement.classList.add("sp-icons-ready")};setTimeout(r,3000);if(d.fonts&&d.fonts.load){d.fonts.load("24px \'Material Symbols Outlined\'","home").then(r,r)}else{r()}})(document);</script>
<link rel="stylesheet" href="' . sp_h(sp_asset('css/md3-tokens.css')) . '">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/md3-components.css')) . '">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/md3-layout.css')) . '">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/sharepoint.css')) . '">
</head>
<body class="sp-body ' . sp_h($bodyClass) . ($embed ? ' sp-embed' : '') . '" data-sp-token="' . sp_h($this->share->token) . '" data-sp-share-id="' . (int)$this->share->id . '">
<a class="sp-skip-link" href="#sp-main">' . sp_h(sp_t('skip_to_content')) . '</a>
' . $header . '
<main id="sp-main" class="sp-main">
' . $content . '
</main>
<footer class="sp-footer">
  <span class="sp-footer__brand"><span class="material-symbols-outlined">hub</span>SharePoint</span>
  ' . sp_footer_version_html() . '
  <span class="sp-footer__meta">' . sp_h(sp_t('shared_link')) . ' · ' . sp_h(sp_t('created')) . ' ' . sp_h(sp_time_ago($this->share->created_at)) . '</span>
</footer>
<script>window.SP_I18N = ' . json_encode(sp_translations(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>
<script src="' . sp_h(sp_asset('js/md3-components.js')) . '" defer></script>
<script src="' . sp_h(sp_asset('js/qr-code.js')) . '" defer></script>
<script src="' . sp_h(sp_asset('js/sharepoint.js')) . '" defer></script>
</body>
</html>';
    }

    public function renderErrorPage(string $reason, int $status = 200): string
    {
        if ($status > 0) {
            http_response_code($status);
        }
        $reason = strtolower($reason);
        $config = [
            'expired' => ['icon' => 'schedule', 'title' => sp_t('expired'), 'text' => sp_t('expired')],
            'revoked' => ['icon' => 'block', 'title' => sp_t('revoked'), 'text' => sp_t('revoked')],
            'not_found' => ['icon' => 'link_off', 'title' => sp_t('not_found'), 'text' => sp_t('not_found')],
            'max_views_exceeded' => ['icon' => 'visibility_off', 'title' => sp_t('views_exceeded'), 'text' => sp_t('views_exceeded')],
            'resource_unavailable' => ['icon' => 'cloud_off', 'title' => sp_t('resource_unavailable'), 'text' => sp_t('resource_unavailable')],
            'login_required' => ['icon' => 'lock', 'title' => sp_t('login_required'), 'text' => sp_t('login_required')],
            'user_not_allowed' => ['icon' => 'block', 'title' => sp_t('user_not_allowed'), 'text' => sp_t('user_not_allowed')],
        ];
        $view = $config[$reason] ?? ['icon' => 'error', 'title' => sp_t('not_found'), 'text' => sp_t('not_found')];
        $content = '<section class="sp-empty md3-card md3-card--outlined" role="alert">
  <span class="material-symbols-outlined sp-empty__icon" aria-hidden="true">' . sp_h($view['icon']) . '</span>
  <h2 class="md3-typescale-headline-small">' . sp_h($view['title']) . '</h2>
  <p class="md3-typescale-body-large">' . sp_h($view['text']) . '</p>
  <a class="md3-btn md3-btn--filled" href="/"><span class="material-symbols-outlined">home</span>' . sp_h(sp_t('app_dashboard')) . '</a>
</section>';
        return $this->layout($content, ['title' => $view['title']]);
    }
}
