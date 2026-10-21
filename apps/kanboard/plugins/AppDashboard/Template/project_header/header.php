<div class="project-header kb-project-header">
    <?= $this->hook->render('template:project:header:before', array('project' => $project)) ?>

    <div class="dropdown-component">
        <?= $this->render('project_header/dropdown', array('project' => $project, 'board_view' => $board_view)) ?>
    </div>
    <div class="views-switcher-component">
        <?= $this->render('project_header/views', array('project' => $project, 'filters' => $filters)) ?>
    </div>
    <div class="filter-box-component kb-project-filter">
        <?= $this->render('project_header/search', array(
            'project' => $project,
            'filters' => $filters,
            'custom_filters_list' => isset($custom_filters_list) ? $custom_filters_list : array(),
            'users_list' => isset($users_list) ? $users_list : array(),
            'categories_list' => isset($categories_list) ? $categories_list : array(),
        )) ?>
    </div>
    <div class="kb-project-header-actions">
        <?php if ($this->user->hasProjectAccess('TaskCreationController', 'show', $project['id'])): ?>
            <?= $this->url->link('<i class="fa fa-plus fa-fw" aria-hidden="true"></i>'.t('New task'), 'TaskCreationController', 'show', array('project_id' => $project['id']), false, 'js-modal-large kb-btn kb-btn-primary') ?>
        <?php endif ?>
    </div>

    <?= $this->hook->render('template:project:header:after', array('project' => $project)) ?>
</div>
