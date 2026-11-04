<?php foreach ($tasks as $task): ?>
    <?= $this->render($not_editable ? 'board/task_public' : 'board/task_private', array(
        'project' => $project,
        'task' => $task,
        'board_highlight_period' => $board_highlight_period,
        'not_editable' => $not_editable,
    )) ?>
<?php endforeach ?>
