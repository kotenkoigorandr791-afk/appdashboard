<?php
final class ExpenseChartRenderer extends BaseRenderer implements AppRendererInterface
{
    public function render(): string
    {
        return $this->renderPage($this->share, $this->resource, $this->context);
    }

    public function renderPage(Share $share, Resource $resource, array $context): string
    {
        $handler = new ExpenseChartShareHandler();
        $data = $handler->loadData($share, $resource, $context);
        $currency = (string)($data['currency'] ?? 'USD');
        $total = (float)($data['total'] ?? 0);
        $monthly = $data['monthly'] ?? [];
        $average = $monthly ? $total / count($monthly) : 0;
        $content = '<div class="sp-app-page sp-expense" data-share-id="' . (int)$share->id . '">
  <section class="sp-app-head md3-card md3-card--elevated">
    <div>
      <div class="sp-chips">' . $this->dataModeBadge() . '<span class="md3-chip md3-chip--filter"><span class="material-symbols-outlined">payments</span>' . sp_h($currency) . '</span></div>
      <h2 class="md3-typescale-headline-medium">' . sp_h($this->pageTitle()) . '</h2>
      ' . ($share->description !== '' ? '<p class="md3-typescale-body-large sp-muted">' . nl2br(sp_h($share->description)) . '</p>' : '') . '
      ' . $this->permissionChips() . '
    </div>
    <div class="sp-app-head__actions">
      <button class="md3-btn md3-btn--tonal" data-sp-export-csv><span class="material-symbols-outlined">table_view</span>' . sp_h(sp_t('csv')) . '</button>
      <button class="md3-btn md3-btn--text" data-sp-print><span class="material-symbols-outlined">print</span>' . sp_h(sp_t('print')) . '</button>
    </div>
  </section>

  <div class="sp-stat-grid">
    <article class="md3-card md3-card--outlined sp-stat"><span class="material-symbols-outlined">savings</span><strong>' . sp_h($currency) . ' ' . number_format($total, 2) . '</strong><span>' . sp_h(sp_t('total')) . '</span></article>
    <article class="md3-card md3-card--outlined sp-stat"><span class="material-symbols-outlined">calendar_month</span><strong>' . sp_h($currency) . ' ' . number_format($average, 2) . '</strong><span>' . sp_h(sp_t('average')) . '</span></article>
    <article class="md3-card md3-card--outlined sp-stat"><span class="material-symbols-outlined">category</span><strong>' . count($data['categories'] ?? []) . '</strong><span>' . sp_h(sp_t('categories') ?? 'Categories') . '</span></article>
    <article class="md3-card md3-card--outlined sp-stat"><span class="material-symbols-outlined">monitoring</span><strong>' . count($monthly) . '</strong><span>' . sp_h(sp_t('months') ?? 'Months') . '</span></article>
  </div>

  <section class="md3-card md3-card--outlined sp-chart-card">
    <div class="md3-card__content">
      <div class="sp-chart-toolbar">
        <div class="sp-chip-group" role="group" aria-label="' . sp_h(sp_t('chart_type')) . '">
          <button class="md3-chip md3-chip--filter md3-chip--selected" data-sp-chart-kind="bar">' . sp_h(sp_t('bar')) . '</button>
          <button class="md3-chip md3-chip--filter" data-sp-chart-kind="line">' . sp_h(sp_t('line')) . '</button>
          <button class="md3-chip md3-chip--filter" data-sp-chart-kind="pie">' . sp_h(sp_t('pie')) . '</button>
        </div>
        <label class="sp-lang-picker"><span class="material-symbols-outlined">date_range</span>
          <select class="md3-select" data-sp-chart-period>
            <option value="6">6 ' . sp_h(sp_t('months') ?? 'months') . '</option>
            <option value="12" selected>12 ' . sp_h(sp_t('months') ?? 'months') . '</option>
            <option value="0">' . sp_h(sp_t('total')) . '</option>
          </select>
        </label>
      </div>
      <div class="sp-chart-wrap"><canvas id="sp-expense-chart" width="960" height="420" role="img" aria-label="' . sp_h(sp_t('expense_chart')) . '"></canvas></div>
      <div class="sp-chart-legend" data-sp-chart-legend></div>
    </div>
  </section>

  <section class="md3-card md3-card--outlined">
    <div class="md3-card__content">
      <h3 class="md3-typescale-title-large">' . sp_h(sp_t('categories') ?? 'Categories') . '</h3>
      <div class="sp-bar-list" data-sp-category-list></div>
    </div>
  </section>
</div>
<script type="application/json" id="sp-expense-data">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
        return $this->layout($content, ['title' => $this->pageTitle(), 'body_class' => 'sp-app-page-body']);
    }
}
