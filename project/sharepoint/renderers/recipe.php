<?php
final class RecipeRenderer extends BaseRenderer implements AppRendererInterface
{
    public function render(): string
    {
        return $this->renderPage($this->share, $this->resource, $this->context);
    }

    public function renderPage(Share $share, Resource $resource, array $context): string
    {
        $handler = new RecipeShareHandler();
        $data = $handler->loadData($share, $resource, $context);
        $recipe = $data['recipe'] ?? [];
        $steps = $recipe['steps'] ?? [];
        $image = !empty($recipe['image']) ? sp_public_url($share->token, '/content/image') : '';
        $ingredients = array_values(array_filter($steps, static fn(array $step): bool => ($step['type'] ?? '') === 'product'));
        $instructions = array_values(array_filter($steps, static fn(array $step): bool => ($step['type'] ?? '') !== 'product'));
        $shareId = (int)$share->id;
        $permissions = $this->permissionChips();

        $ingredientHtml = '';
        foreach ($ingredients as $index => $item) {
            $qty = '';
            if (!empty($item['quantity']['value'])) {
                $qty = $item['quantity']['value'] . ' ' . ($item['quantity']['unit'] ?? '');
            }
            $ingredientHtml .= '<li class="sp-check-row" data-sp-check="ingredient-' . $index . '">
  <label class="sp-check-row__label"><input type="checkbox" class="md3-checkbox" data-sp-persist="ingredient-' . $index . '"><span class="md3-typescale-body-large">' . sp_h((string)$item['content']) . '</span></label>
  ' . ($qty !== '' ? '<span class="md3-chip md3-chip--filter">' . sp_h(trim($qty)) . '</span>' : '') . '
</li>';
        }

        $stepHtml = '';
        foreach ($instructions as $index => $step) {
            $timer = $step['timer'] ?? null;
            $seconds = 0;
            if (is_array($timer)) {
                $seconds = ((int)($timer['hours'] ?? 0)) * 3600 + ((int)($timer['minutes'] ?? 0)) * 60;
            }
            $stepHtml .= '<li class="sp-step" data-sp-step="' . $index . '">
  <span class="sp-step__number">' . ($index + 1) . '</span>
  <div class="sp-step__body">
    <label class="sp-step__check"><input type="checkbox" class="md3-checkbox" data-sp-persist="step-' . $index . '"><span class="md3-typescale-body-large">' . nl2br(sp_h((string)$step['content'])) . '</span></label>
    ' . ($seconds > 0 ? '<div class="sp-timer" data-sp-timer data-seconds="' . $seconds . '">
      <span class="md3-chip md3-chip--suggestion sp-timer__display"><span class="material-symbols-outlined">timer</span><span data-sp-timer-value>' . gmdate('i:s', $seconds) . '</span></span>
      <button class="md3-btn md3-btn--icon md3-btn--tonal" data-sp-timer-start aria-label="' . sp_h(sp_t('start_timer')) . '"><span class="material-symbols-outlined">play_arrow</span></button>
      <button class="md3-btn md3-btn--icon md3-btn--text" data-sp-timer-reset aria-label="' . sp_h(sp_t('reset_timer')) . '"><span class="material-symbols-outlined">restart_alt</span></button>
    </div>' : '') . '
  </div>
</li>';
        }

        $content = '<div class="sp-app-page sp-recipe" data-share-id="' . $shareId . '">
  <section class="sp-hero md3-card md3-card--elevated">
    <div class="sp-hero__media' . ($image === '' ? ' sp-hero__media--empty' : '') . '">
      ' . ($image !== '' ? '<img src="' . sp_h($image) . '" alt="' . sp_h((string)($recipe['title'] ?? '')) . '" loading="eager" onerror="this.style.display=\'none\';this.parentElement.classList.add(\'sp-hero__media--empty\')">' : '') . '
      <span class="material-symbols-outlined sp-hero__fallback">restaurant_menu</span>
    </div>
    <div class="sp-hero__content">
      <div class="sp-hero__top">' . $this->dataModeBadge() . '</div>
      <h2 class="md3-typescale-headline-medium">' . sp_h((string)($recipe['title'] ?? $this->pageTitle())) . '</h2>
      ' . ($share->description !== '' ? '<p class="md3-typescale-body-large sp-muted">' . nl2br(sp_h($share->description)) . '</p>' : '') . '
      ' . $permissions . '
      <div class="sp-hero__actions">
        <button class="md3-btn md3-btn--filled" data-sp-print><span class="material-symbols-outlined">print</span>' . sp_h(sp_t('print')) . '</button>
        <button class="md3-btn md3-btn--tonal" data-sp-export-recipe><span class="material-symbols-outlined">download</span>' . sp_h(sp_t('export')) . '</button>
        <label class="sp-lang-picker"><span class="material-symbols-outlined">language</span>
          <select class="md3-select" onchange="location.search=\'?lang=\'+this.value">
            ' . $this->langOptions($data['lang'] ?? sp_lang()) . '
          </select>
        </label>
      </div>
    </div>
  </section>

  <div class="sp-grid sp-grid--2 sp-recipe__grid">
    <section class="md3-card md3-card--outlined sp-recipe__card">
      <div class="md3-card__content">
        <h3 class="md3-typescale-title-large">' . sp_h(sp_t('ingredients')) . '</h3>
        <ul class="sp-check-list">' . ($ingredientHtml !== '' ? $ingredientHtml : '<li class="sp-muted md3-typescale-body-medium">' . sp_h(sp_t('no_items')) . '</li>') . '</ul>
      </div>
    </section>

    <section class="md3-card md3-card--outlined sp-recipe__card">
      <div class="md3-card__content">
        <div class="sp-section-head">
          <h3 class="md3-typescale-title-large">' . sp_h(sp_t('steps')) . '</h3>
          <span class="md3-chip md3-chip--assist" data-sp-progress-text>0 / ' . count($instructions) . '</span>
        </div>
        <div class="sp-progress" role="progressbar" aria-valuemin="0" aria-valuemax="' . count($instructions) . '" aria-valuenow="0">
          <span class="sp-progress__bar" data-sp-progress-bar style="width:0%"></span>
        </div>
        <ol class="sp-step-list">' . ($stepHtml !== '' ? $stepHtml : '<li class="sp-muted md3-typescale-body-medium">' . sp_h(sp_t('no_items')) . '</li>') . '</ol>
      </div>
    </section>
  </div>
</div>
<script type="application/json" id="sp-recipe-data">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';

        return $this->layout($content, ['title' => (string)($recipe['title'] ?? $this->pageTitle()), 'body_class' => 'sp-app-page-body']);
    }

    private function langOptions(string $current): string
    {
        $labels = ['en' => 'English', 'uk' => 'Українська', 'ru' => 'Русский', 'be' => 'Беларуская'];
        $html = '';
        foreach ($labels as $code => $label) {
            $html .= '<option value="' . $code . '"' . ($code === $current ? ' selected' : '') . '>' . sp_h($label) . '</option>';
        }
        return $html;
    }
}
