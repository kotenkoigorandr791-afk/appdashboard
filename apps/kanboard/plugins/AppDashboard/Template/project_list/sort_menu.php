<div class="dropdown">
    <a href="#" class="dropdown-menu dropdown-menu-link-icon kb-sort-menu"><strong><?= t('Sort') ?> <i class="fa fa-caret-down"></i></strong></a>
    <ul>
        <li>
            <i class="fa fa-hashtag fa-fw" aria-hidden="true"></i>
            <?= $paginator->order(t('Project ID'), \Kanboard\Model\ProjectModel::TABLE.'.id') ?>
        </li>
        <li>
            <i class="fa fa-font fa-fw" aria-hidden="true"></i>
            <?= $paginator->order(t('Project name'), \Kanboard\Model\ProjectModel::TABLE.'.name') ?>
        </li>
        <li>
            <i class="fa fa-toggle-on fa-fw" aria-hidden="true"></i>
            <?= $paginator->order(t('Status'), \Kanboard\Model\ProjectModel::TABLE.'.is_active') ?>
        </li>
        <li>
            <i class="fa fa-calendar fa-fw" aria-hidden="true"></i>
            <?= $paginator->order(t('Start date'), \Kanboard\Model\ProjectModel::TABLE.'.start_date') ?>
        </li>
        <li>
            <i class="fa fa-calendar-check-o fa-fw" aria-hidden="true"></i>
            <?= $paginator->order(t('End date'), \Kanboard\Model\ProjectModel::TABLE.'.end_date') ?>
        </li>
        <li>
            <i class="fa fa-globe fa-fw" aria-hidden="true"></i>
            <?= $paginator->order(t('Public'), \Kanboard\Model\ProjectModel::TABLE.'.is_public') ?>
        </li>
        <li>
            <i class="fa fa-lock fa-fw" aria-hidden="true"></i>
            <?= $paginator->order(t('Personal'), \Kanboard\Model\ProjectModel::TABLE.'.is_private') ?>
        </li>
    </ul>
</div>
