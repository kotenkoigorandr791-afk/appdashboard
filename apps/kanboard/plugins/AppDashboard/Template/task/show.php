<?= $this->hook->render('template:task:show:top', array('task' => $task, 'project' => $project)) ?>

<div class="kb-task-simple">
    <?= $this->render('task/details', array(
        'task' => $task,
        'tags' => $tags,
        'project' => $project,
        'editable' => $this->user->hasProjectAccess('TaskModificationController', 'edit', $project['id']),
    )) ?>

    <?= $this->hook->render('template:task:show:before-description', array('task' => $task, 'project' => $project)) ?>
    <?= $this->render('task/description', array('task' => $task)) ?>

    <?= $this->hook->render('template:task:show:before-subtasks', array('task' => $task, 'project' => $project)) ?>
    <?= $this->render('subtask/show', array(
        'task' => $task,
        'subtasks' => $subtasks,
        'project' => $project,
        'editable' => $this->user->hasProjectAccess('SubtaskController', 'edit', $project['id']),
    )) ?>

    <?= $this->hook->render('template:task:show:before-attachments', array('task' => $task, 'project' => $project)) ?>
    <?= $this->render('task_file/show', array(
        'task' => $task,
        'files' => $files,
        'images' => $images
    )) ?>

    <?= $this->hook->render('template:task:show:before-comments', array('task' => $task, 'project' => $project)) ?>
    <?= $this->render('task_comments/show', array(
        'task' => $task,
        'comments' => $comments,
        'project' => $project,
        'editable' => $this->user->hasProjectAccess('CommentController', 'edit', $project['id']),
    )) ?>
</div>

<?= $this->hook->render('template:task:show:bottom', array('task' => $task, 'project' => $project)) ?>
