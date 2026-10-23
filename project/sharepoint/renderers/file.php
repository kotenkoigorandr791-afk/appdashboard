<?php
final class FileRenderer extends BaseRenderer
{
    private string $path;
    private string $name;
    private string $type;
    private string $contentUrl;
    private string $downloadUrl;

    public function __construct(Share $share, Resource $resource, array $context = [])
    {
        parent::__construct($share, $resource, $context);
        $this->path = (string)$resource->get('path');
        $this->name = basename((string)$resource->getTitle());
        $this->type = sp_detect_file_type($this->path);
        $fileParam = (string)($context['file'] ?? '');
        $this->contentUrl = sp_public_url($share->token, '/content') . ($fileParam !== '' ? '?file=' . rawurlencode($fileParam) : '');
        if ($fileParam !== '') {
            $segments = array_map('rawurlencode', explode('/', str_replace('\\', '/', $fileParam)));
            $this->downloadUrl = sp_public_url($share->token, '/download/' . implode('/', $segments));
        } else {
            $this->downloadUrl = sp_public_url($share->token, '/download');
        }
    }

    protected function pageTitle(): string
    {
        return $this->name !== '' ? $this->name : parent::pageTitle();
    }

    protected function resourceTypeLabel(): string
    {
        return sp_t('file');
    }

    public function render(): string
    {
        $size = is_file($this->path) ? (int)filesize($this->path) : 0;
        $mime = sp_file_mime($this->path, $this->name);
        $body = match ($this->type) {
            'image' => $this->renderImage(),
            'video' => $this->renderVideo($mime),
            'audio' => $this->renderAudio($mime),
            'pdf' => $this->renderPdf(),
            'office' => $this->renderOffice(),
            'text' => $this->renderText($size),
            'archive' => $this->renderStatic('folder_zip', sp_t('archive')),
            '3dmodel' => $this->renderStatic('view_in_ar', '3D'),
            default => $this->renderStatic('draft', sp_t('binary')),
        };
        $meta = '<div class="sp-chips sp-chips--meta">'
            . '<span class="md3-chip md3-chip--filter"><span class="material-symbols-outlined">tag</span>' . sp_h(strtoupper($this->type)) . '</span>'
            . '<span class="md3-chip md3-chip--filter"><span class="material-symbols-outlined">data_usage</span>' . sp_h(sp_format_bytes($size)) . '</span>'
            . '<span class="md3-chip md3-chip--filter"><span class="material-symbols-outlined">description</span>' . sp_h($mime) . '</span>'
            . $this->dataModeBadge() . '</div>';
        // Files opened from a shared folder keep an explicit way back to the
        // listing (browser history also works, but a visible control matches
        // Explorer-style browsing and helps on touch devices).
        $back = '';
        if (($this->context['file'] ?? '') !== '' && $this->share->resource_type === 'folder') {
            $back = '<a class="md3-btn md3-btn--text sp-viewer__back" href="?subpath=' . sp_h(rawurlencode((string)($this->context['subpath'] ?? ''))) . '">'
                . '<span class="material-symbols-outlined">arrow_back</span>' . sp_h(sp_t('back')) . '</a>';
        }
        $content = '<section class="sp-viewer md3-card md3-card--elevated" aria-label="' . sp_h($this->name) . '">
  ' . $back . '
  <header class="sp-viewer__header">
    <div class="sp-viewer__heading">
      ' . sp_file_icon_html($this->name, 'sp-viewer__type-icon') . '
      <div>
        <h2 class="md3-typescale-title-large">' . sp_h($this->name) . '</h2>
        ' . $meta . '
      </div>
    </div>
    <div class="sp-viewer__tools">
      <button class="md3-btn md3-btn--icon md3-btn--text" data-sp-copy="' . sp_h(sp_public_url($this->share->token)) . '" aria-label="' . sp_h(sp_t('copy_link')) . '">
        <span class="material-symbols-outlined">content_copy</span>
      </button>
      ' . ($this->share->hasPermission('download') ? '<a class="md3-btn md3-btn--tonal" href="' . sp_h($this->downloadUrl) . '"><span class="material-symbols-outlined">download</span>' . sp_h(sp_t('download')) . '</a>' : '') . '
    </div>
  </header>
  <div class="sp-viewer__body sp-viewer__body--' . sp_h($this->type) . '">
    ' . $body . '
  </div>
</section>';
        if ($this->share->description !== '') {
            $content .= '<section class="md3-card md3-card--outlined sp-description"><div class="md3-card__content">'
                . '<p class="md3-typescale-body-large">' . nl2br(sp_h($this->share->description)) . '</p></div></section>';
        }
        return $this->layout($content, ['title' => $this->name, 'body_class' => 'sp-file-page']);
    }

    private function renderImage(): string
    {
        return '<div class="sp-image-stage" data-sp-image-stage>
  <img id="sp-image" src="' . sp_h($this->contentUrl) . '" alt="' . sp_h($this->name) . '" class="sp-image" data-sp-image draggable="false">
  <div class="sp-image-minimap" data-sp-image-minimap hidden aria-hidden="true">
    <img class="sp-image-minimap__img" data-sp-image-minimap-img src="' . sp_h($this->contentUrl) . '" alt="" draggable="false">
    <span class="sp-image-minimap__view" data-sp-image-minimap-view></span>
  </div>
  <div class="sp-image-tools md3-card md3-card--elevated" role="toolbar" aria-label="' . sp_h(sp_t('image_tools')) . '">
    <button class="md3-btn md3-btn--icon md3-btn--text" data-sp-image-zoom="in" aria-label="' . sp_h(sp_t('zoom_in')) . '" title="' . sp_h(sp_t('zoom_in')) . '"><span class="material-symbols-outlined">zoom_in</span></button>
    <button class="md3-btn md3-btn--icon md3-btn--text" data-sp-image-zoom="out" aria-label="' . sp_h(sp_t('zoom_out')) . '" title="' . sp_h(sp_t('zoom_out')) . '"><span class="material-symbols-outlined">zoom_out</span></button>
    <span class="sp-image-tools__scale" data-sp-image-scale aria-live="polite">100%</span>
    <button class="md3-btn md3-btn--icon md3-btn--text" data-sp-image-rotate="-90" aria-label="' . sp_h(sp_t('rotate_left')) . '" title="' . sp_h(sp_t('rotate_left')) . '"><span class="material-symbols-outlined">rotate_left</span></button>
    <button class="md3-btn md3-btn--icon md3-btn--text" data-sp-image-rotate="90" aria-label="' . sp_h(sp_t('rotate_right')) . '" title="' . sp_h(sp_t('rotate_right')) . '"><span class="material-symbols-outlined">rotate_right</span></button>
    <button class="md3-btn md3-btn--icon md3-btn--text" data-sp-image-reset aria-label="' . sp_h(sp_t('reset_zoom')) . '" title="' . sp_h(sp_t('reset_zoom')) . '"><span class="material-symbols-outlined">restart_alt</span></button>
  </div>
</div>';
    }

    private function renderVideo(string $mime): string
    {
        $play = sp_h(sp_t('play'));
        $pause = sp_h(sp_t('pause'));
        $mute = sp_h(sp_t('mute'));
        $unmute = sp_h(sp_t('unmute'));
        $fullscreen = sp_h(sp_t('fullscreen'));
        $exitFullscreen = sp_h(sp_t('exit_fullscreen'));
        $seek = sp_h(sp_t('seek'));
        return '<div class="sp-video-player" data-sp-video-player data-label-play="' . $play . '" data-label-pause="' . $pause . '" data-label-mute="' . $mute . '" data-label-unmute="' . $unmute . '" data-label-fullscreen="' . $fullscreen . '" data-label-exit-fullscreen="' . $exitFullscreen . '">
  <video class="sp-video" data-sp-video playsinline preload="metadata" tabindex="0">
    <source src="' . sp_h($this->contentUrl) . '" type="' . sp_h($mime) . '">
  </video>
  <button class="sp-video-player__overlay" type="button" data-sp-video-toggle aria-label="' . $play . '"><span class="material-symbols-outlined" aria-hidden="true">play_arrow</span></button>
  <div class="sp-video-controls">
    <button class="sp-video-btn" type="button" data-sp-video-toggle aria-label="' . $play . '"><span class="material-symbols-outlined" aria-hidden="true">play_arrow</span></button>
    <span class="sp-video-time" data-sp-video-current>0:00</span>
    <input class="sp-video-seek" type="range" min="0" max="0" step="0.1" value="0" data-sp-video-seek aria-label="' . $seek . '">
    <span class="sp-video-time" data-sp-video-duration>0:00</span>
    <button class="sp-video-btn" type="button" data-sp-video-mute aria-label="' . $mute . '"><span class="material-symbols-outlined" aria-hidden="true">volume_up</span></button>
    <input class="sp-video-volume" type="range" min="0" max="1" step="0.05" value="1" data-sp-video-volume aria-label="' . $mute . '">
    <button class="sp-video-btn" type="button" data-sp-video-fullscreen aria-label="' . $fullscreen . '"><span class="material-symbols-outlined" aria-hidden="true">fullscreen</span></button>
  </div>
</div>';
    }

    private function renderAudio(string $mime): string
    {
        return '<div class="sp-audio-card">
  <span class="material-symbols-outlined sp-audio-card__icon">graphic_eq</span>
  <div class="sp-audio-card__title md3-typescale-title-medium">' . sp_h($this->name) . '</div>
  <audio class="sp-audio" controls preload="metadata"><source src="' . sp_h($this->contentUrl) . '" type="' . sp_h($mime) . '"></audio>
</div>';
    }

    private function renderPdf(): string
    {
        $prev = sp_h(sp_t('previous_page'));
        $next = sp_h(sp_t('next_page'));
        $zoomIn = sp_h(sp_t('zoom_in'));
        $zoomOut = sp_h(sp_t('zoom_out'));
        $fit = sp_h(sp_t('fit_width'));
        $fullscreen = sp_h(sp_t('fullscreen'));
        $pageLabel = sp_h(sp_t('page'));
        $base = sp_h($this->contentUrl);
        return '<div class="sp-pdf-shell" data-sp-pdf>
  <div class="sp-pdf-toolbar" role="toolbar" aria-label="' . sp_h(sp_t('pdf_tools')) . '">
    <div class="sp-pdf-toolbar__group">
      <button type="button" class="md3-btn md3-btn--icon md3-btn--text" data-sp-pdf-prev aria-label="' . $prev . '" title="' . $prev . '"><span class="material-symbols-outlined">chevron_left</span></button>
      <label class="sp-pdf-page">
        <input type="number" min="1" step="1" value="1" inputmode="numeric" data-sp-pdf-page aria-label="' . $pageLabel . '" title="' . $pageLabel . '">
        <span class="sp-pdf-page__total-wrap" data-sp-pdf-total-wrap hidden>
          <span class="sp-pdf-page__sep" aria-hidden="true">/</span>
          <span class="sp-pdf-page__total" data-sp-pdf-total aria-live="polite"></span>
        </span>
      </label>
      <button type="button" class="md3-btn md3-btn--icon md3-btn--text" data-sp-pdf-next aria-label="' . $next . '" title="' . $next . '"><span class="material-symbols-outlined">chevron_right</span></button>
    </div>
    <span class="sp-pdf-toolbar__divider" aria-hidden="true"></span>
    <div class="sp-pdf-toolbar__group">
      <button type="button" class="md3-btn md3-btn--icon md3-btn--text" data-sp-pdf-zoom-out aria-label="' . $zoomOut . '" title="' . $zoomOut . '"><span class="material-symbols-outlined">remove</span></button>
      <span class="sp-pdf-zoom" data-sp-pdf-zoom aria-live="polite">100%</span>
      <button type="button" class="md3-btn md3-btn--icon md3-btn--text" data-sp-pdf-zoom-in aria-label="' . $zoomIn . '" title="' . $zoomIn . '"><span class="material-symbols-outlined">add</span></button>
      <button type="button" class="md3-btn md3-btn--icon md3-btn--text" data-sp-pdf-fit aria-label="' . $fit . '" title="' . $fit . '"><span class="material-symbols-outlined">fit_screen</span></button>
    </div>
    <span class="sp-pdf-toolbar__spacer" aria-hidden="true"></span>
    <button type="button" class="md3-btn md3-btn--icon md3-btn--text" data-sp-pdf-fullscreen aria-label="' . $fullscreen . '" title="' . $fullscreen . '"><span class="material-symbols-outlined">fullscreen</span></button>
  </div>
  <div class="sp-pdf-stage">
    <iframe class="sp-pdf" src="' . $base . '#toolbar=0&amp;navpanes=0&amp;zoom=100" title="' . sp_h($this->name) . '"></iframe>
  </div>
</div>';
    }

    private function renderText(int $size): string
    {
        if ($size > 10485760) {
            return $this->renderStatic('warning', sp_t('file_too_large'));
        }
        $raw = sp_file_bytes($this->path, $this->share->owner_email);
        if ($raw === false) {
            return $this->renderStatic('warning', sp_t('resource_unavailable'));
        }
        if (strlen($raw) > 10485760) {
            return $this->renderStatic('warning', sp_t('file_too_large'));
        }
        return '<div class="sp-text-toolbar">
  <span class="md3-chip md3-chip--filter"><span class="material-symbols-outlined">code</span>' . sp_h(strtoupper(pathinfo($this->name, PATHINFO_EXTENSION) ?: 'TEXT')) . '</span>
  <button class="md3-btn md3-btn--text" data-sp-copy-text="#sp-text-content"><span class="material-symbols-outlined">content_copy</span>' . sp_h(sp_t('copy_all')) . '</button>
</div>
<pre id="sp-text-content" class="sp-text" tabindex="0">' . sp_h($raw) . '</pre>';
    }

    private function renderStatic(string $icon, string $label): string
    {
        return '<div class="sp-static-preview">
  <span class="material-symbols-outlined sp-static-preview__icon">' . sp_h($icon) . '</span>
  <h3 class="md3-typescale-title-large">' . sp_h($label) . '</h3>
  <p class="md3-typescale-body-medium">' . sp_h(sp_t('preview_unavailable')) . '</p>
  <a class="md3-btn md3-btn--filled" href="' . sp_h($this->downloadUrl) . '">
    <span class="material-symbols-outlined">download</span>' . sp_h(sp_t('download_file')) . '
  </a>
</div>';
    }

    private function renderOffice(): string
    {
        $docUrl = strlen((string)getenv('DOCUMENT_PUBLIC_URL')) > 0 ? rtrim((string)getenv('DOCUMENT_PUBLIC_URL'), '/') : 'http://document.localhost';
        $ext = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
        $docTypes = ['doc' => 'word', 'docx' => 'word', 'odt' => 'word', 'rtf' => 'word', 'txt' => 'word',
            'xls' => 'cell', 'xlsx' => 'cell', 'ods' => 'cell', 'csv' => 'cell',
            'ppt' => 'slide', 'pptx' => 'slide', 'odp' => 'slide'];
        $documentType = $docTypes[$ext] ?? 'word';
        $key = md5($this->path . (is_file($this->path) ? filemtime($this->path) : 0) . $this->share->id);
        $callback = sp_public_url($this->share->token, '/content?callback=1');
        $config = [
            'documentType' => $documentType,
            'type' => 'desktop',
            'document' => ['fileType' => $ext, 'key' => $key, 'title' => $this->name, 'url' => $this->contentUrl],
            'editorConfig' => [
                'mode' => 'view',
                'callbackUrl' => $callback,
                'lang' => sp_lang(),
                'user' => ['id' => 'guest_' . $this->share->id, 'name' => 'Guest Viewer'],
                'customization' => [
                    'goback' => ['url' => 'javascript:window.parent.postMessage({type:"closeOnlyOfficeEditor"},"*")'],
                    'toolbar' => false, 'header' => false, 'statusBar' => false, 'leftMenu' => false,
                    'compactHeader' => true, 'compactToolbar' => true, 'hideRightMenu' => true,
                    'chat' => false, 'comments' => false, 'plugins' => false, 'help' => false,
                    'compact' => true, 'reviewChanges' => false, 'macros' => false,
                    'integrationMode' => 'embed', 'uiTheme' => sp_theme() === 'dark' ? 'theme-dark' : 'theme-light',
                ],
            ],
        ];
        $secret = trim((string)getOnlyofficeJwtSecret());
        if ($secret !== '') {
            $config['token'] = sp_jwt_encode($config, $secret);
        }
        return '<link rel="stylesheet" href="' . sp_h($docUrl) . '/web-apps/apps/api/documents/api.js">
<div class="sp-office-wrap">
  <div id="sp-office-editor" class="sp-office"></div>
  <noscript><a class="md3-btn md3-btn--filled" href="' . sp_h($this->downloadUrl) . '">' . sp_h(sp_t('download_file')) . '</a></noscript>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  if (!window.DocsAPI) return;
  new DocsAPI.DocEditor("sp-office-editor", ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');
});
</script>';
    }
}
