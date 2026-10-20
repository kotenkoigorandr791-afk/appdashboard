<?php
final class FilmRenderer extends BaseRenderer implements AppRendererInterface
{
    public function render(): string
    {
        return $this->renderPage($this->share, $this->resource, $this->context);
    }

    public function renderPage(Share $share, Resource $resource, array $context): string
    {
        $handler = new FilmShareHandler();
        $data = $handler->loadData($share, $resource, $context);
        $film = $data['film'] ?? [];
        $poster = !empty($film['poster']) ? sp_public_url($share->token, '/content/poster') : '';
        $genres = $film['genre'] ?? '';
        if (is_array($genres)) {
            $genres = implode(', ', array_map('strval', $genres));
        }
        $cast = $film['cast'] ?? '';
        if (is_array($cast)) {
            $cast = implode(', ', array_slice(array_map(static function ($actor) {
                return is_array($actor) ? ($actor['name'] ?? '') : (string)$actor;
            }, $cast), 0, 8));
        }
        $rating = $film['rating'] ?? '';
        $trailer = (string)($film['trailer'] ?? '');
        $content = '<div class="sp-app-page sp-film" data-share-id="' . (int)$share->id . '">
  <section class="sp-film__hero md3-card md3-card--elevated">
    <div class="sp-film__poster-wrap">
      ' . ($poster !== '' ? '<img class="sp-film__poster" src="' . sp_h($poster) . '" alt="' . sp_h((string)($film['title'] ?? '')) . '" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">' : '') . '
      <div class="sp-film__poster-fallback"' . ($poster !== '' ? ' style="display:none"' : '') . '><span class="material-symbols-outlined">movie</span></div>
    </div>
    <div class="sp-film__info">
      <div class="sp-chips">' . $this->dataModeBadge() . ($rating !== '' ? '<span class="md3-chip md3-chip--assist"><span class="material-symbols-outlined">star</span>' . sp_h((string)$rating) . '</span>' : '') . '</div>
      <h2 class="md3-typescale-headline-large">' . sp_h((string)($film['title'] ?? $this->pageTitle())) . '</h2>
      <div class="sp-film__meta md3-typescale-body-large">
        ' . (!empty($film['year']) ? '<span>' . sp_h((string)$film['year']) . '</span>' : '') . '
        ' . ($genres !== '' ? '<span>' . sp_h((string)$genres) . '</span>' : '') . '
        ' . (!empty($film['duration']) ? '<span>' . sp_h((string)$film['duration']) . '</span>' : '') . '
      </div>
      ' . ($share->description !== '' ? '<p class="md3-typescale-body-large sp-muted">' . nl2br(sp_h($share->description)) . '</p>' : '') . '
      ' . $this->permissionChips() . '
      <div class="sp-hero__actions">
        ' . ($trailer !== '' && preg_match('#^https?://#', $trailer) ? '<a class="md3-btn md3-btn--filled" href="' . sp_h($trailer) . '" target="_blank" rel="noopener noreferrer"><span class="material-symbols-outlined">play_circle</span>' . sp_h(sp_t('watch_trailer')) . '</a>' : '') . '
        <button class="md3-btn md3-btn--text" data-sp-print><span class="material-symbols-outlined">print</span>' . sp_h(sp_t('print')) . '</button>
      </div>
    </div>
  </section>
  ' . (!empty($film['description']) ? '<section class="md3-card md3-card--outlined"><div class="md3-card__content"><h3 class="md3-typescale-title-large">' . sp_h(sp_t('description')) . '</h3><p class="md3-typescale-body-large">' . nl2br(sp_h((string)$film['description'])) . '</p></div></section>' : '') . '
  <div class="sp-grid sp-grid--2">
    ' . (!empty($film['director']) ? '<section class="md3-card md3-card--outlined"><div class="md3-card__content"><h3 class="md3-typescale-title-medium">' . sp_h(sp_t('director')) . '</h3><p class="md3-typescale-body-large">' . sp_h((string)$film['director']) . '</p></div></section>' : '') . '
    ' . ($cast !== '' ? '<section class="md3-card md3-card--outlined"><div class="md3-card__content"><h3 class="md3-typescale-title-medium">' . sp_h(sp_t('cast')) . '</h3><p class="md3-typescale-body-large">' . sp_h((string)$cast) . '</p></div></section>' : '') . '
  </div>
</div>';
        return $this->layout($content, ['title' => (string)($film['title'] ?? $this->pageTitle()), 'body_class' => 'sp-app-page-body']);
    }
}
