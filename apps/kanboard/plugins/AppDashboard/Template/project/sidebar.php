<?php
$kbIcon = static function (string $name): string {
    $icons = [
        'summary' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h5A1.5 1.5 0 0 1 12 5.5v5A1.5 1.5 0 0 1 10.5 12h-5A1.5 1.5 0 0 1 4 10.5v-5Z"/><path d="M14 5.5A1.5 1.5 0 0 1 15.5 4H18.5A1.5 1.5 0 0 1 20 5.5v2A1.5 1.5 0 0 1 18.5 9h-3A1.5 1.5 0 0 1 14 7.5v-2Z"/><path d="M14 12.5A1.5 1.5 0 0 1 15.5 11H18.5A1.5 1.5 0 0 1 20 12.5v5A1.5 1.5 0 0 1 18.5 19h-3A1.5 1.5 0 0 1 14 17.5v-5Z"/><path d="M4 15.5A1.5 1.5 0 0 1 5.5 14h5A1.5 1.5 0 0 1 12 15.5V18.5A1.5 1.5 0 0 1 10.5 20h-5A1.5 1.5 0 0 1 4 18.5v-3Z"/>',
        'filter' => '<path d="M4 5h16"/><path d="M7 12h10"/><path d="M10 19h4"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
        'predefined' => '<path d="M8 4h7l3 3v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/><path d="M15 4v3h3"/><path d="M9 12h6"/><path d="M9 16h6"/>',
        'public' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>',
        'bell' => '<path d="M6 17h12l-1.2-1.8a6.4 6.4 0 0 1-1-3.4V9a3.8 3.8 0 1 0-7.6 0v2.8c0 1.2-.35 2.4-1 3.4Z"/><path d="M10 17a2 2 0 0 0 4 0"/>',
        'integrations' => '<path d="M12 3v4"/><path d="M12 17v4"/><path d="M3 12h4"/><path d="M17 12h4"/><circle cx="12" cy="12" r="3"/><path d="m5.6 5.6 2.5 2.5"/><path d="m15.9 15.9 2.5 2.5"/><path d="m18.4 5.6-2.5 2.5"/><path d="m8.1 15.9-2.5 2.5"/>',
        'columns' => '<rect x="4" y="4" width="5" height="16" rx="1"/><rect x="10.5" y="4" width="5" height="16" rx="1"/><rect x="17" y="4" width="3" height="16" rx="1"/>',
        'swimlanes' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/><circle cx="8" cy="7" r="1.4"/><circle cx="14" cy="12" r="1.4"/><circle cx="10" cy="17" r="1.4"/>',
        'categories' => '<path d="M4 8.5A1.5 1.5 0 0 1 5.5 7H9l2 2h7.5A1.5 1.5 0 0 1 20 10.5v7A1.5 1.5 0 0 1 18.5 19h-13A1.5 1.5 0 0 1 4 17.5v-9Z"/>',
        'tags' => '<path d="M4 11.5V5.8A1.8 1.8 0 0 1 5.8 4H11.5L20 12.5 12.5 20Z"/><circle cx="8.2" cy="8.2" r="1.1"/>',
        'permissions' => '<path d="M16 19v-1.4a3.1 3.1 0 0 0-3.1-3.1H7.1A3.1 3.1 0 0 0 4 17.6V19"/><circle cx="10" cy="8" r="3"/><path d="M19 11v4"/><path d="M17 13h4"/>',
        'roles' => '<path d="M12 3 5 6.2v4.5c0 4.4 3 7.8 7 8.8 4-1 7-4.4 7-8.8V6.2Z"/><path d="M9.5 12.2 11.2 14l3.5-3.8"/>',
        'actions' => '<path d="M13 3 5.5 13.2H12L11 21l7.5-10.2H12Z"/>',
        'duplicate' => '<rect x="8" y="8" width="11" height="11" rx="1.5"/><path d="M5.5 15.5H5A1.5 1.5 0 0 1 3.5 14V5A1.5 1.5 0 0 1 5 3.5h9A1.5 1.5 0 0 1 15.5 5v.5"/>',
        'import' => '<path d="M12 4v10"/><path d="m8.5 10.5 3.5 3.5 3.5-3.5"/><path d="M5 18h14"/>',
        'close' => '<circle cx="12" cy="12" r="8"/><path d="m9 9 6 6"/><path d="m15 9-6 6"/>',
        'open' => '<circle cx="12" cy="12" r="8"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'remove' => '<path d="M5 8h14"/><path d="M9 8V6.5A1.5 1.5 0 0 1 10.5 5h3A1.5 1.5 0 0 1 15 6.5V8"/><path d="M7.5 8l.7 10.2A1.5 1.5 0 0 0 9.7 19.5h4.6a1.5 1.5 0 0 0 1.5-1.3L16.5 8"/>',
    ];
    $path = $icons[$name] ?? $icons['summary'];
    return '<svg class="kb-sidebar-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.$path.'</svg>';
};
$kbLink = static function ($helper, string $label, string $controller, string $action, array $params, string $icon) use ($kbIcon): string {
    return '<a href="'.$helper->url->href($controller, $action, $params).'">'.$kbIcon($icon).'<span>'.$helper->text->e($label).'</span></a>';
};
?>
<div class="sidebar">
    <ul>
        <li <?= $this->app->checkMenuSelection('ProjectViewController', 'show') ?>>
            <?= $kbLink($this, t('Summary'), 'ProjectViewController', 'show', array('project_id' => $project['id']), 'summary') ?>
        </li>
        <?php if ($this->user->hasProjectAccess('CustomFilterController', 'index', $project['id'])): ?>
        <li <?= $this->app->checkMenuSelection('CustomFilterController') ?>>
            <?= $kbLink($this, t('Custom filters'), 'CustomFilterController', 'index', array('project_id' => $project['id']), 'filter') ?>
        </li>
        <?php endif ?>

        <?php if ($this->user->hasProjectAccess('ProjectEditController', 'show', $project['id'])): ?>
            <li <?= $this->app->checkMenuSelection('ProjectEditController') ?>>
                <?= $kbLink($this, t('Edit project'), 'ProjectEditController', 'show', array('project_id' => $project['id']), 'edit') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('ProjectPredefinedContentController') ?>>
                <?= $kbLink($this, t('Predefined contents'), 'ProjectPredefinedContentController', 'show', array('project_id' => $project['id']), 'predefined') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('ProjectViewController', 'share') ?>>
                <?= $kbLink($this, t('Public access'), 'ProjectViewController', 'share', array('project_id' => $project['id']), 'public') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('ProjectViewController', 'notifications') ?>>
                <?= $kbLink($this, t('Notifications'), 'ProjectViewController', 'notifications', array('project_id' => $project['id']), 'bell') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('ProjectViewController', 'integrations') ?>>
                <?= $kbLink($this, t('Integrations'), 'ProjectViewController', 'integrations', array('project_id' => $project['id']), 'integrations') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('ColumnController') ?>>
                <?= $kbLink($this, t('Columns'), 'ColumnController', 'index', array('project_id' => $project['id']), 'columns') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('SwimlaneController') ?>>
                <?= $kbLink($this, t('Swimlanes'), 'SwimlaneController', 'index', array('project_id' => $project['id']), 'swimlanes') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('CategoryController') ?>>
                <?= $kbLink($this, t('Categories'), 'CategoryController', 'index', array('project_id' => $project['id']), 'categories') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('ProjectTagController') ?>>
                <?= $kbLink($this, t('Tags'), 'ProjectTagController', 'index', array('project_id' => $project['id']), 'tags') ?>
            </li>
            <?php if ($project['is_private'] == 0): ?>
            <li <?= $this->app->checkMenuSelection('ProjectPermissionController') ?>>
                <?= $kbLink($this, t('Permissions'), 'ProjectPermissionController', 'index', array('project_id' => $project['id']), 'permissions') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('ProjectRoleController') ?>>
                <?= $kbLink($this, t('Custom roles'), 'ProjectRoleController', 'show', array('project_id' => $project['id']), 'roles') ?>
            </li>
            <?php endif ?>
            <li <?= $this->app->checkMenuSelection('ActionController') ?>>
                <?= $kbLink($this, t('Automatic actions'), 'ActionController', 'index', array('project_id' => $project['id']), 'actions') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('ProjectViewController', 'duplicate') ?>>
                <?= $kbLink($this, t('Duplicate'), 'ProjectViewController', 'duplicate', array('project_id' => $project['id']), 'duplicate') ?>
            </li>
            <li <?= $this->app->checkMenuSelection('ProjectViewController', 'importTasks') ?>>
                <?= $kbLink($this, t('Import tasks from another project'), 'ProjectViewController', 'importTasks', array('project_id' => $project['id']), 'import') ?>
            </li>
            <?php if ($project['is_active']): ?>
                <li>
                    <a href="<?= $this->url->href('ProjectStatusController', 'confirmDisable', array('project_id' => $project['id'])) ?>" class="js-modal-confirm">
                        <?= $kbIcon('close') ?><span><?= t('Close this project') ?></span>
                    </a>
                </li>
            <?php else: ?>
                <li>
                    <a href="<?= $this->url->href('ProjectStatusController', 'confirmEnable', array('project_id' => $project['id'])) ?>" class="js-modal-confirm">
                        <?= $kbIcon('open') ?><span><?= t('Open this project') ?></span>
                    </a>
                </li>
            <?php endif ?>
            <?php if ($this->user->hasProjectAccess('ProjectStatusController', 'remove', $project['id'])): ?>
                <li>
                    <a href="<?= $this->url->href('ProjectStatusController', 'confirmRemove', array('project_id' => $project['id'])) ?>" class="js-modal-confirm">
                        <?= $kbIcon('remove') ?><span><?= t('Remove') ?></span>
                    </a>
                </li>
            <?php endif ?>
        <?php endif ?>

        <?= $this->hook->render('template:project:sidebar', array('project' => $project)) ?>
    </ul>
</div>
