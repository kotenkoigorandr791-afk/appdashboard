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
                $parent = str_replace('\\', '/', dirname($selected));
                if ($parent === '.' || $parent === '/') {
                    $parent = '';
                }
                return (new FileRenderer($this->share, $fileResource, [
                    'file' => $selected,
                    'subpath' => $parent,
                ]))->render();
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

        $rows = '';
        $listHead = '';
        if ($items === []) {
            $rows = '<div class="sp-empty sp-empty--inline"><span class="material-symbols-outlined sp-empty__icon">folder_off</span>'
                . '<p class="md3-typescale-body-large">' . sp_h(sp_t('empty_folder')) . '</p></div>';
        } else {
            $listHead = '<div class="sp-folder-list__head" aria-hidden="true">
          <span class="sp-folder-list__col-name">' . sp_h(sp_t('file')) . '</span>
          <span class="sp-folder-list__col-meta">' . sp_h(sp_t('size')) . '</span>
          <span class="sp-folder-list__col-date">' . sp_h(sp_t('modified')) . '</span>
          <span class="sp-folder-list__col-action"></span>
        </div>';
            foreach ($items as $item) {
                $relative = str_replace('\\', '/', (string)$item['path']);
                $segments = array_map('rawurlencode', array_filter(explode('/', $relative), 'strlen'));
                $encoded = implode('/', $segments);
                $mtime = !empty($item['mtime']) ? date('M j, Y', (int)$item['mtime']) : '';
                if ($item['is_dir']) {
                    $href = '?subpath=' . rawurlencode($relative);
                    $icon = sp_folder_icon_html('sp-folder-row__icon');
                    $meta = sp_t('folder');
                    $type = 'folder';
                } else {
                    $href = '?subpath=' . rawurlencode($current) . '&file=' . rawurlencode($relative);
                    $icon = sp_file_icon_html((string)$item['name'], 'sp-folder-row__icon');
                    $meta = sp_format_bytes((int)$item['size']);
                    $type = 'file';
                }
                $download = !$item['is_dir'] && $this->share->hasPermission('download')
                    ? '<a class="md3-btn md3-btn--icon md3-btn--text sp-folder-row__action" href="' . sp_h(sp_public_url($this->share->token, '/download/' . $encoded)) . '" aria-label="' . sp_h(sp_t('download')) . '" title="' . sp_h(sp_t('download')) . '"><span class="material-symbols-outlined">download</span></a>'
                    : '';
                $rows .= '<div class="sp-folder-row" data-type="' . $type . '" role="listitem">
  <a class="sp-folder-row__link" href="' . sp_h($href) . '">
    ' . $icon . '
    <span class="sp-folder-row__name">' . sp_h((string)$item['name']) . '</span>
    <span class="sp-folder-row__meta">' . sp_h($meta) . '</span>
    <span class="sp-folder-row__date">' . sp_h($mtime) . '</span>
  </a>
  ' . $download . '
</div>';
            }
        }

        $treeCount = 0;
        $tree = $this->renderTree($base, $current, $selected, '', 0, $treeCount);
        $parentHref = $current !== ''
            ? '?subpath=' . rawurlencode(dirname($current) === '.' ? '' : str_replace('\\', '/', dirname($current)))
            : '';

        $content = '<section class="sp-folder">
  <div class="sp-folder-layout">
    <div class="sp-tree-backdrop" data-sp-tree-backdrop hidden></div>
    <aside class="sp-tree-panel" id="sp-tree-panel" aria-label="' . sp_h(sp_t('folder_tree')) . '">
      <div class="sp-tree-panel__head">
        <span class="material-symbols-outlined" aria-hidden="true">account_tree</span>
        <span>' . sp_h(sp_t('folder_tree')) . '</span>
        <button type="button" class="md3-btn md3-btn--icon md3-btn--text sp-tree-panel__close" data-sp-tree-close aria-label="' . sp_h(sp_t('close')) . '">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <nav class="sp-tree" data-sp-tree>
        <a class="sp-tree__root' . ($current === '' && $selected === '' ? ' is-active' : '') . '" href="?">'
            . sp_folder_icon_html('sp-tree__folder-icon') . '<span class="sp-tree__name">' . sp_h($this->pageTitle()) . '</span></a>
        <ul class="sp-tree__list sp-tree__list--root">' . $tree . '</ul>
      </nav>
    </aside>
    <div class="sp-folder__main">
      <div class="sp-folder__toolbar">
        <button type="button" class="md3-btn md3-btn--outlined sp-tree-toggle" data-sp-tree-toggle aria-controls="sp-tree-panel" aria-expanded="false">
          <span class="material-symbols-outlined">account_tree</span>' . sp_h(sp_t('folder_tree')) . '
        </button>
        <div class="sp-folder__crumb">' . $breadcrumb . '</div>
        <span class="md3-chip md3-chip--assist sp-folder__count"><span class="material-symbols-outlined">inventory_2</span>' . count($items) . ' ' . sp_h(sp_t('items')) . ($totalSize > 0 ? ' · ' . sp_h(sp_format_bytes($totalSize)) : '') . '</span>
      </div>
      ' . ($parentHref !== '' ? '<a class="md3-btn md3-btn--text sp-up" href="' . sp_h($parentHref) . '"><span class="material-symbols-outlined">arrow_upward</span>' . sp_h(sp_t('parent_folder')) . '</a>' : '') . '
      <div class="sp-folder-list" role="list">
        ' . $listHead . $rows . '
      </div>
    </div>
  </div>
</section>';
        if ($this->share->description !== '') {
            $content .= '<section class="md3-card md3-card--outlined sp-description"><div class="md3-card__content"><p class="md3-typescale-body-large">' . nl2br(sp_h($this->share->description)) . '</p></div></section>';
        }
        return $this->layout($content, ['title' => $this->pageTitle(), 'body_class' => 'sp-folder-page']);
    }

    private function renderTree(string $base, string $current, string $selectedFile, string $relativeDir = '', int $depth = 0, int &$count = 0): string
    {
        if ($count > 400 || $depth > 8) {
            return '';
        }
        $dir = $relativeDir === '' ? $base : sp_safe_join($base, $relativeDir);
        if ($dir === null || !is_dir($dir)) {
            return '';
        }
        $entries = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            $entries[] = [
                'name' => $entry,
                'is_dir' => is_dir($full),
            ];
        }
        usort($entries, static function (array $a, array $b): int {
            if ($a['is_dir'] !== $b['is_dir']) {
                return $b['is_dir'] <=> $a['is_dir'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        $html = '';
        foreach ($entries as $entry) {
            if ($count++ > 400) {
                break;
            }
            $name = $entry['name'];
            $relative = $relativeDir === '' ? $name : $relativeDir . '/' . $name;
            if ($entry['is_dir']) {
                $isOpen = $current === $relative || str_starts_with($current, $relative . '/')
                    || ($selectedFile !== '' && (str_starts_with($selectedFile, $relative . '/') || dirname(str_replace('\\', '/', $selectedFile)) === $relative));
                $children = '';
                // Preload two levels so collapsed folders still expand; deeper only along the open path.
                if ($depth < 7 && ($isOpen || $depth <= 1)) {
                    $children = $this->renderTree($base, $current, $selectedFile, $relative, $depth + 1, $count);
                }
                $active = $current === $relative && $selectedFile === '' ? ' is-active' : '';
                $open = $isOpen ? ' open' : '';
                $html .= '<li class="sp-tree__node sp-tree__node--branch">';
                $html .= '<details class="sp-tree__branch"' . $open . '>'
                    . '<summary class="sp-tree__summary">'
                    . '<span class="material-symbols-outlined sp-tree__chevron" aria-hidden="true">chevron_right</span>'
                    . sp_folder_icon_html('sp-tree__folder-icon')
                    . '<a class="sp-tree__link' . $active . '" href="?subpath=' . rawurlencode($relative) . '" title="' . sp_h($name) . '">' . sp_h($name) . '</a>'
                    . '</summary>'
                    . ($children !== '' ? '<ul class="sp-tree__list">' . $children . '</ul>' : '')
                    . '</details></li>';
            } else {
                $parent = $relativeDir;
                $href = '?subpath=' . rawurlencode($parent) . '&file=' . rawurlencode($relative);
                $active = $selectedFile === $relative ? ' is-active' : '';
                $html .= '<li class="sp-tree__node sp-tree__node--file">'
                    . '<div class="sp-tree__summary sp-tree__summary--leaf">'
                    . '<span class="sp-tree__chevron-spacer" aria-hidden="true"></span>'
                    . sp_file_icon_html($name, 'sp-tree__file-icon')
                    . '<a class="sp-tree__link' . $active . '" href="' . sp_h($href) . '" title="' . sp_h($name) . '">' . sp_h($name) . '</a>'
                    . '</div></li>';
            }
        }
        return $html;
    }
}
