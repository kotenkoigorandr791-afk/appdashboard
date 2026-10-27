<?php
$userId = (int) $this->user->getId();
$currentProjectId = !empty($project['id']) ? (int) $project['id'] : 0;
$projects = [];
if (!empty($board_selector) && is_array($board_selector)) {
    foreach ($board_selector as $pid => $pname) {
        $projects[(int) $pid] = (string) $pname;
    }
}
if ($currentProjectId > 0 && !empty($project['name'])) {
    $projects = [$currentProjectId => (string) $project['name']] + $projects;
}
ksort($projects);
$controller = (string) $this->app->getRouterController();
$action = (string) $this->app->getRouterAction();
$isDashboard = $controller === 'DashboardController';
$isMyTasks = $isDashboard && $action === 'tasks';
$isOverview = $isDashboard && $action === 'show';
$isProjects = $isDashboard && $action === 'projects';
$isSearch = $controller === 'SearchController';
$isActivity = $controller === 'ActivityController';
$isBoard = $controller === 'BoardViewController';
$icon = static function (string $name): string {
    $paths = [
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 3.5 3.5"/>',
        'inbox' => '<path d="M4 6.5A1.5 1.5 0 0 1 5.5 5h13A1.5 1.5 0 0 1 20 6.5v11A1.5 1.5 0 0 1 18.5 19h-13A1.5 1.5 0 0 1 4 17.5v-11Z"/><path d="m4 8 7.2 5.1a1.4 1.4 0 0 0 1.6 0L20 8"/>',
        'issues' => '<circle cx="12" cy="12" r="8"/><path d="m9.2 12.2 1.9 1.9 3.8-4"/>',
        'views' => '<rect x="4" y="5" width="16" height="3" rx="1"/><rect x="4" y="10.5" width="16" height="3" rx="1"/><rect x="4" y="16" width="10" height="3" rx="1"/>',
        'overview' => '<rect x="4" y="4" width="7" height="7" rx="1.2"/><rect x="13" y="4" width="7" height="7" rx="1.2"/><rect x="4" y="13" width="7" height="7" rx="1.2"/><rect x="13" y="13" width="7" height="7" rx="1.2"/>',
        'projects' => '<path d="M4 8.2A1.7 1.7 0 0 1 5.7 6.5H10l1.8 2H18.3A1.7 1.7 0 0 1 20 10.2v7.1A1.7 1.7 0 0 1 18.3 19H5.7A1.7 1.7 0 0 1 4 17.3Z"/>',
        'board' => '<rect x="3.5" y="5" width="5" height="14" rx="1"/><rect x="9.5" y="5" width="5" height="14" rx="1"/><rect x="15.5" y="5" width="5" height="14" rx="1"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'menu' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'team' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 18.5a5.5 5.5 0 0 1 11 0"/><circle cx="16.5" cy="9" r="2.2"/><path d="M15 18.5a4 4 0 0 1 5.5 0"/>',
    ];
    $path = $paths[$name] ?? $paths['overview'];
    return '<svg class="kb-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';
};
$fullname = $this->user->getFullname();
$initial = mb_strtoupper(mb_substr($fullname !== '' ? $fullname : 'U', 0, 1));
?>
<aside class="kb-app-sidebar" id="kb-app-sidebar" aria-label="<?= t('Navigation') ?>">
    <div class="kb-app-sidebar-header">
        <div class="kb-org-switcher">
            <span class="kb-org-avatar" aria-hidden="true"><?= $this->text->e($initial) ?></span>
            <div class="kb-org-meta">
                <span class="kb-org-name"><?= $this->text->e($fullname ?: t('Workspace')) ?></span>
                <span class="kb-org-sub"><?= t('Tasks') ?></span>
            </div>
        </div>
    </div>
    <nav class="kb-app-sidebar-nav">
        <div class="kb-nav-group">
            <ul class="kb-nav-list">
                <li class="<?= $isSearch ? 'active' : '' ?>">
                    <a href="<?= $this->url->href('SearchController', 'index') ?>"><?= $icon('search') ?><span><?= t('Search') ?></span></a>
                </li>
                <li class="<?= $isActivity ? 'active' : '' ?>">
                    <a href="<?= $this->url->href('ActivityController', 'user') ?>" class="js-modal-medium"><?= $icon('inbox') ?><span><?= t('Inbox') ?></span></a>
                </li>
                <li class="<?= $isMyTasks ? 'active' : '' ?>">
                    <a href="<?= $this->url->href('DashboardController', 'tasks', ['user_id' => $userId]) ?>"><?= $icon('issues') ?><span><?= t('My tasks') ?></span></a>
                </li>
                <li class="<?= $isOverview ? 'active' : '' ?>">
                    <a href="<?= $this->url->href('DashboardController', 'show', ['user_id' => $userId]) ?>"><?= $icon('views') ?><span><?= t('Overview') ?></span></a>
                </li>
            </ul>
        </div>
        <div class="kb-nav-group">
            <div class="kb-nav-label"><?= t('Favorites') ?></div>
            <ul class="kb-nav-list">
                <li class="<?= $isMyTasks ? 'active' : '' ?>">
                    <a href="<?= $this->url->href('DashboardController', 'tasks', ['user_id' => $userId]) ?>"><?= $icon('issues') ?><span><?= t('My tasks') ?></span></a>
                </li>
                <li class="<?= $isProjects ? 'active' : '' ?>">
                    <a href="<?= $this->url->href('DashboardController', 'projects', ['user_id' => $userId]) ?>"><?= $icon('projects') ?><span><?= t('Projects') ?></span></a>
                </li>
            </ul>
        </div>
        <div class="kb-nav-group">
            <div class="kb-nav-label"><?= t('Your teams') ?></div>
            <ul class="kb-nav-list">
                <?php if (empty($projects)): ?>
                    <li>
                        <a href="<?= $this->url->href('DashboardController', 'projects', ['user_id' => $userId]) ?>">
                            <?= $icon('team') ?><span><?= t('Projects') ?></span>
                        </a>
                    </li>
                <?php else: ?>
                    <?php foreach ($projects as $pid => $pname): ?>
                        <li class="<?= $currentProjectId === (int) $pid ? 'active' : '' ?>">
                            <a href="<?= $this->url->href('BoardViewController', 'show', ['project_id' => $pid]) ?>" title="<?= $this->text->e($pname) ?>">
                                <?= $icon('board') ?><span><?= $this->text->e($pname) ?></span>
                            </a>
                        </li>
                    <?php endforeach ?>
                <?php endif ?>
            </ul>
        </div>
        <div class="kb-nav-group" style="margin-top:auto;padding:8px 10px 12px;">
            <button type="button" class="kb-sidebar-mobile-btn" id="kb-open-mobile-app">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                <span><?= t('Mobile app') ?></span>
            </button>
            <button type="button" class="kb-sidebar-mobile-btn kb-sidebar-settings-btn" id="kb-open-settings">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.11-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.14.37.4.7.74.94.27.19.6.3.95.3H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1Z"></path></svg>
                <span><?= t('Settings') ?></span>
            </button>
        </div>
    </nav>
</aside>
<button type="button" class="kb-sidebar-fab" id="kb-sidebar-fab" title="<?= t('Open sidebar') ?>" aria-label="<?= t('Open sidebar') ?>">
    <?= $icon('menu') ?>
    <span><?= t('Menu') ?></span>
</button>
