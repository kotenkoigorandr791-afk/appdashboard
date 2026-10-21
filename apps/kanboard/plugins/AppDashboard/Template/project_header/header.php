<?php
$isTaskPage = $this->app->getRouterController() === 'TaskViewController';
?>
<?php if ($isTaskPage): ?>
    <?php /* Cycle UI: project chrome is redundant on task pages. */ ?>
<?php else: ?>
<div class="project-header kb-project-header kb-project-header-slim">
    <div class="filter-box-component kb-project-filter">
        <?= $this->render('project_header/search', array(
            'project' => $project,
            'filters' => $filters,
            'custom_filters_list' => isset($custom_filters_list) ? $custom_filters_list : array(),
            'users_list' => isset($users_list) ? $users_list : array(),
            'categories_list' => isset($categories_list) ? $categories_list : array(),
        )) ?>
    </div>
</div>
<?php endif ?>
