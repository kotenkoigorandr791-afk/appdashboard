<?= $this->hook->render('template:app:filters-helper:before', isset($project) ? array('project' => $project) : array()) ?>
<div class="dropdown">
    <a href="#" class="dropdown-menu dropdown-menu-link-icon" title="<?= t('Default filters') ?>" aria-label="<?= t('Default filters') ?>"><i class="fa fa-filter"></i></a>
    <ul>
        <li><a href="#" class="filter-helper filter-reset" data-filter="<?= isset($reset) ? $reset : '' ?>" title="<?= t('Keyboard shortcut: "%s"', 'r') ?>"><i class="fa fa-refresh fa-fw" aria-hidden="true"></i><?= t('Reset filters') ?></a></li>
        <li><a href="#" class="filter-helper" data-filter="status:open assignee:me"><i class="fa fa-user fa-fw" aria-hidden="true"></i><?= t('My tasks') ?></a></li>
        <li><a href="#" class="filter-helper" data-filter="status:open assignee:me due:tomorrow"><i class="fa fa-clock-o fa-fw" aria-hidden="true"></i><?= t('My tasks due tomorrow') ?></a></li>
        <li><a href="#" class="filter-helper" data-filter="status:open due:tomorrow"><i class="fa fa-calendar fa-fw" aria-hidden="true"></i><?= t('Tasks due tomorrow') ?></a></li>
        <li><a href="#" class="filter-helper" data-filter="status:open due:yesterday"><i class="fa fa-calendar-o fa-fw" aria-hidden="true"></i><?= t('Tasks due yesterday') ?></a></li>
        <li><a href="#" class="filter-helper" data-filter="status:closed"><i class="fa fa-check-square-o fa-fw" aria-hidden="true"></i><?= t('Closed tasks') ?></a></li>
        <li><a href="#" class="filter-helper" data-filter="status:open"><i class="fa fa-folder-open-o fa-fw" aria-hidden="true"></i><?= t('Open tasks') ?></a></li>
    </ul>
</div>
<?= $this->hook->render('template:app:filters-helper:after', isset($project) ? array('project' => $project) : array()) ?>
