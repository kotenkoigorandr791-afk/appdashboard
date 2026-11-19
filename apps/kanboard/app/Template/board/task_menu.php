<?php /* Lightweight task menu: the actions list is fetched on first open (see Asset/board-menu.js) */ ?>
<div class="dropdown kb-task-menu"
     data-kb-menu-url="<?= $this->url->href('BoardAjaxController', 'taskMenu', array('project_id' => $task['project_id'], 'task_id' => $task['id'], 'redirect' => 'board')) ?>">
    <a href="#" class="dropdown-menu dropdown-menu-link-icon" aria-haspopup="true"><strong>#<?= $task['id'] ?> <i class="fa fa-caret-down"></i></strong></a>
    <ul><li class="kb-task-menu-loading"><i class="fa fa-spinner fa-pulse"></i></li></ul>
</div>
