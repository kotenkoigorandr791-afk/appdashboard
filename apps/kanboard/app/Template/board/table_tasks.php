<!-- task row -->
<tr class="board-swimlane board-swimlane-tasks-<?= $swimlane['id'] ?><?= $swimlane['task_limit'] && $swimlane['nb_tasks'] > $swimlane['task_limit'] ? ' board-task-list-limit' : '' ?>">
    <?php foreach ($swimlane['columns'] as $column): ?>
        <td class="
            board-column-<?= $column['id'] ?>
            <?= $column['task_limit'] > 0 && $column['column_nb_open_tasks'] > $column['task_limit'] ? 'board-task-list-limit' : '' ?>
            "
        >

            <!-- tasks list -->
            <div
                class="board-task-list board-column-expanded <?= $this->projectRole->isSortableColumn($column['project_id'], $column['id']) ? 'sortable-column' : '' ?>"
                data-column-id="<?= $column['id'] ?>"
                data-swimlane-id="<?= $swimlane['id'] ?>"
                data-task-limit="<?= $column['task_limit'] ?>">

                <?= $this->render('board/task_rows', array(
                    'project' => $project,
                    'tasks' => $column['tasks'],
                    'board_highlight_period' => $board_highlight_period,
                    'not_editable' => $not_editable,
                )) ?>

                <?php if (! $not_editable && ! empty($column['has_more'])): ?>
                    <div class="kb-board-more"
                         data-kb-board-more="1"
                         data-kb-url="<?= $this->url->href('BoardAjaxController', 'tasks', array(
                             'project_id' => $project['id'],
                             'swimlane_id' => $swimlane['id'],
                             'column_id' => $column['id'],
                             'offset' => $column['nb_loaded'],
                             'limit' => 50,
                             'mode' => 'board',
                         )) ?>"
                         data-kb-offset="<?= $column['nb_loaded'] ?>"
                         data-kb-total="<?= $column['total_tasks'] ?>">
                        <a href="#"><?= t('Load more tasks') ?></a>
                    </div>
                <?php endif ?>
            </div>

            <!-- column in collapsed mode (rotated text) -->
            <div class="board-column-collapsed board-task-list sortable-column"
                data-column-id="<?= $column['id'] ?>"
                data-swimlane-id="<?= $swimlane['id'] ?>"
                data-task-limit="<?= $column['task_limit'] ?>">
                <div class="board-rotation-wrapper">
                    <div class="board-column-title board-rotation board-toggle-column-view" data-column-id="<?= $column['id'] ?>" title="<?= $this->text->e($column['title']) ?>">
                        <i class="fa fa-plus-square" title="<?= t('Show this column') ?>" role="button" aria-label="<?= t('Show this column') ?>"></i> <?= $this->text->e($column['title']) ?>
                    </div>
                </div>
            </div>
        </td>
    <?php endforeach ?>
</tr>
