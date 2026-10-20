<?php
final class FolderRenderer extends BaseRenderer
{
    public function render(): string
    {
        $base = (string)$this->resource->get('path');
        $subpath = (string)($this->context['subpath'] ?? '');
        $selected = (string)($this->context['file'] ?? '');
        if ($selected !== '') {
            $filePath = sp_safe_join($base, $selected);
            if ($filePath !== null && is_file($filePath)) {
                $fileResource = new Resource('file', $selected, 'cloud', basename($filePath), [
                    'path' => $filePath,
                    'name' => basename($filePath),
                    'owner_email' => $this->share->owner_email,
                ]);
                return (new FileRenderer($this->share, $fileResource, ['file' => $selected]))->render();
            }
        }

        $listing = ShareFolderHandler::listItems($this->resource, $subpath);
        $items = $listing['items'] ?? [];
        $current = (string)($listing['subpath'] ?? '');
        $totalSize = 0;
        foreach ($items as $item) {
            $totalSize += (int)$item['size'];
        }

        $breadcrumb = '<nav class="sp-breadcrumb md3-typescale-body-medium" aria-label="' . sp_h(sp_t('breadcrumb')) . '"><a href="' . sp_h(sp_public_url($this->share->token)) . '">'
            . sp_folder_icon_html('sp-breadcrumb__folder') . sp_h($this->pageTitle()) . '</a>';
        $segments = $current === '' ? [] : explode('/', str_replace('\\', '/', $current));
        $acc = '';
        foreach ($segments as $segment) {
            if ($segment === '') continue;
            $acc = $acc === '' ? $segment : $acc . '/' . $segment;
            $breadcrumb .= '<span class="sp-breadcrumb__sep material-symbols-outlined">chevron_right</span>'
                . '<a href="?subpath=' . rawurlencode($acc) . '">' . sp_h($segment) . '</a>';
        }
        $breadcrumb .= '</nav>';

        $cards = '';
        if ($items === []) {
            $cards = '<div class="sp-empty sp-empty--inline"><span class="material-symbols-outlined sp-empty__icon">folder_off</span>'
                . '<p class="md3-typescale-body-large">' . sp_h(sp_t('empty_folder')) . '</p></div>';
        } else {
            foreach ($items as $item) {
                $relative = (string)$item['path'];
                $relative = str_replace('\\', '/', $relative);
                $segments = array_map('rawurlencode', array_filter(explode('/', $relative), 'strlen'));
                $encoded = implode('/', $segments);
                if ($item['is_dir']) {
                    $href = '?subpath=' . rawurlencode($relative);
                    $icon = sp_folder_icon_html('sp-folder-item__icon');
                    $meta = sp_t('folder');
                } else {
                    $href = '?subpath=' . rawurlencode($current) . '&file=' . rawurlencode($relative);
                    $icon = sp_file_icon_html((string)$item['name'], 'sp-folder-item__icon');
                    $meta = sp_format_bytes((int)$item['size']);
                }
                $download = !$item['is_dir'] && $this->share->hasPermission('download')
                    ? '<a class="md3-btn md3-btn--icon md3-btn--text sp-item__download" href="' . sp_h(sp_public_url($this->share->token, '/download/' . $encoded)) . '" aria-label="' . sp_h(sp_t('download')) . '" title="' . sp_h(sp_t('download')) . '"><span class="material-symbols-outlined">download</span></a>'
                    : '';
                $cards .= '<article class="sp-folder-item md3-card md3-card--outlined">
  <a class="sp-folder-item__link" href="' . sp_h($href) . '">
    ' . $icon . '
    <span class="sp-folder-item__text">
      <span class="md3-typescale-body-large sp-folder-item__name">' . sp_h((string)$item['name']) . '</span>
      <span class="md3-typescale-body-small sp-folder-item__meta">' . sp_h($meta) . '</span>
    </span>
  </a>
  ' . $download . '
</article>';
            }
        }

        $tree = $this->renderTree($base, $current);
        $content = '<section class="sp-folder">
  <div class="sp-folder-layout">
    <aside class="sp-tree-panel" aria-label="' . sp_h(sp_t('folder_tree')) . '">
      <div class="sp-tree-panel__head"><span class="material-symbols-outlined">account_tree</span><span>' . sp_h(sp_t('folder')) . '</span></div>
      <nav class="sp-tree" data-sp-tree>
        <a class="sp-tree__root' . ($current === '' ? ' is-active' : '') . '" href="?">' . sp_folder_icon_html('sp-tree__folder-icon') . '<span class="sp-tree__name">' . sp_h($this->pageTitle()) . '</span></a>
        <ul class="sp-tree__list">' . $tree . '</ul>
      </nav>
    </aside>
    <div class="sp-folder__main">
      <div class="sp-folder__head">
        <div>' . $breadcrumb . '</div>
        <span class="md3-chip md3-chip--assist"><span class="material-symbols-outlined">inventory_2</span>' . count($items) . ' ' . sp_h(sp_t('items')) . ' · ' . sp_h(sp_format_bytes($totalSize)) . '</span>
      </div>
      ' . ($current !== '' ? '<a class="md3-btn md3-btn--text sp-up" href="?subpath=' . rawurlencode(dirname($current) === '.' ? '' : dirname($current)) . '"><span class="material-symbols-outlined">arrow_upward</span>' . sp_h(sp_t('parent_folder')) . '</a>' : '') . '
      <div class="sp-folder-grid">' . $cards . '</div>
    </div>
  </div>
</section>';
        if ($this->share->description !== '') {
            $content .= '<section class="md3-card md3-card--outlined sp-description"><div class="md3-card__content"><p class="md3-typescale-body-large">' . nl2br(sp_h($this->share->description)) . '</p></div></section>';
        }
        return $this->layout($content, ['title' => $this->pageTitle(), 'body_class' => 'sp-folder-page']);
    }

    private function renderTree(string $base, string $current, string $relativeDir = '', int $depth = 0, int &$count = 0): string
    {
        if ($count > 250 || $depth > 5) {
            return '';
        }
        $dir = $relativeDir === '' ? $base : sp_safe_join($base, $relativeDir);
        if ($dir === null || !is_dir($dir)) {
            return '';
        }
        $directories = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($full)) {
                continue;
            }
            $directories[] = $entry;
        }
        sort($directories, SORT_NATURAL | SORT_FLAG_CASE);
        $html = '';
        foreach ($directories as $entry) {
            if ($count++ > 250) {
                break;
            }
            $relative = $relativeDir === '' ? $entry : $relativeDir . '/' . $entry;
            $isOpen = $current === $relative || str_starts_with($current, $relative . '/');
            $children = '';
            if ($depth < 4 && ($isOpen || $depth < 1)) {
                $children = $this->renderTree($base, $current, $relative, $depth + 1, $count);
            }
            $active = $current === $relative ? ' is-active' : '';
            $open = ($isOpen || $depth < 1) ? ' open' : '';
            $html .= '<li class="sp-tree__node">' . '<details class="sp-tree__branch"' . $open . '>' .
                '<summary class="sp-tree__summary"><span class="material-symbols-outlined sp-tree__chevron">chevron_right</span>' . sp_folder_icon_html('sp-tree__folder-icon') .
                '<a class="sp-tree__link' . $active . '" href="?subpath=' . rawurlencode($relative) . '" title="' . sp_h($entry) . '">' . sp_h($entry) . '</a></summary>' .
                ($children !== '' ? '<ul class="sp-tree__list">' . $children . '</ul>' : '') .
                '</details></li>';
        }
        return $html;
    }

}
