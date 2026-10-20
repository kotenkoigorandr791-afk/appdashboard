<?php
final class StepRoutineRenderer extends BaseRenderer implements AppRendererInterface
{
    public function render(): string
    {
        return $this->renderPage($this->share, $this->resource, $this->context);
    }

    public function renderPage(Share $share, Resource $resource, array $context): string
    {
        $handler = new StepRoutineShareHandler();
        $data = $handler->loadData($share, $resource, $context);
        $total = (int)($data['total'] ?? 0);
        $average = (int)($data['average'] ?? 0);
        $best = (int)($data['best'] ?? 0);
        $today = (int)($data['today'] ?? 0);
        $goal = (int)($data['goal'] ?? 10000);
        $goalPct = min(100, (int)round($today / max(1, $goal) * 100));
        $content = '<div class="sp-app-page sp-steps" data-share-id="' . (int)$share->id . '">
  <section class="sp-app-head md3-card md3-card--elevated">
    <div>
      <div class="sp-chips">' . $this->dataModeBadge() . '<span class="md3-chip md3-chip--filter"><span class="material-symbols-outlined">flag</span>' . number_format($goal) . ' ' . sp_h(sp_t('goal')) . '</span></div>
      <h2 class="md3-typescale-headline-medium">' . sp_h($this->pageTitle()) . '</h2>
      ' . ($share->description !== '' ? '<p class="md3-typescale-body-large sp-muted">' . nl2br(sp_h($share->description)) . '</p>' : '') . '
      ' . $this->permissionChips() . '
    </div>
    <div class="sp-app-head__actions">
      <button class="md3-btn md3-btn--tonal" data-sp-export-steps><span class="material-symbols-outlined">download</span>' . sp_h(sp_t('export')) . '</button>
      <button class="md3-btn md3-btn--text" data-sp-print><span class="material-symbols-outlined">print</span>' . sp_h(sp_t('print')) . '</button>
    </div>
  </section>

  <div class="sp-stat-grid">
    <article class="md3-card md3-card--outlined sp-stat"><span class="material-symbols-outlined">today</span><strong>' . number_format($today) . '</strong><span>' . sp_h(sp_t('steps_today')) . '</span></article>
    <article class="md3-card md3-card--outlined sp-stat"><span class="material-symbols-outlined">functions</span><strong>' . number_format($average) . '</strong><span>' . sp_h(sp_t('average')) . '</span></article>
    <article class="md3-card md3-card--outlined sp-stat"><span class="material-symbols-outlined">emoji_events</span><strong>' . number_format($best) . '</strong><span>' . sp_h(sp_t('best')) . '</span></article>
    <article class="md3-card md3-card--outlined sp-stat"><span class="material-symbols-outlined">stairs</span><strong>' . number_format($total) . '</strong><span>' . sp_h(sp_t('total')) . '</span></article>
  </div>

  <section class="md3-card md3-card--outlined sp-steps__goal">
    <div class="md3-card__content">
      <div class="sp-section-head">
        <h3 class="md3-typescale-title-large">' . sp_h(sp_t('goal')) . '</h3>
        <span class="md3-chip md3-chip--assist">' . $goalPct . '%</span>
      </div>
      <div class="sp-progress sp-progress--large" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $goalPct . '">
        <span class="sp-progress__bar" style="width:' . $goalPct . '%"></span>
      </div>
      <p class="md3-typescale-body-medium sp-muted">' . number_format($today) . ' / ' . number_format($goal) . '</p>
    </div>
  </section>

  <section class="md3-card md3-card--outlined sp-chart-card">
    <div class="md3-card__content">
      <div class="sp-chart-toolbar">
        <h3 class="md3-typescale-title-large">' . sp_h(sp_t('days')) . '</h3>
        <label class="sp-lang-picker"><span class="material-symbols-outlined">date_range</span>
          <select class="md3-select" data-sp-steps-period>
            <option value="7">7 ' . sp_h(sp_t('days')) . '</option>
            <option value="30" selected>30 ' . sp_h(sp_t('days')) . '</option>
            <option value="90">90 ' . sp_h(sp_t('days')) . '</option>
          </select>
        </label>
      </div>
      <div class="sp-steps-chart" data-sp-steps-chart></div>
    </div>
  </section>

  <section class="md3-card md3-card--outlined">
    <div class="md3-card__content">
      <h3 class="md3-typescale-title-large">' . sp_h(sp_t('days')) . '</h3>
      <div class="sp-table-wrap">
        <table class="md3-data-table sp-data-table">
          <thead><tr><th>' . sp_h(sp_t('created')) . '</th><th>' . sp_h(sp_t('steps')) . '</th><th>' . sp_h(sp_t('goal')) . '</th></tr></thead>
          <tbody data-sp-steps-table></tbody>
        </table>
      </div>
    </div>
  </section>
</div>
<script type="application/json" id="sp-steps-data">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
        return $this->layout($content, ['title' => $this->pageTitle(), 'body_class' => 'sp-app-page-body']);
    }
}
